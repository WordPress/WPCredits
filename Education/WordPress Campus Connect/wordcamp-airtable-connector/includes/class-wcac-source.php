<?php
/**
 * Read-only client for the public WordCamp.org REST API.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches WordCamps and Meetups from Central, and the per-event post types
 * (sessions, speakers, sponsors) from each camp's own site.
 */
class WCAC_Source {

	/**
	 * Fields requested for the Meetups endpoint.
	 *
	 * Safe to trim here because every meetup field name is a single word --
	 * see wordcamps() for why the camps endpoint cannot do the same.
	 */
	const MEETUP_FIELDS = 'id,slug,status,link,modified_gmt,title';

	/**
	 * Per-run cache of taxonomy term maps, keyed "{site}|{taxonomy}".
	 *
	 * @var array
	 */
	protected $term_cache = array();

	/**
	 * GET a JSON endpoint.
	 *
	 * @param string $url       Absolute URL.
	 * @param array  $overrides Optional 'timeout', 'redirection', 'headers'.
	 *                          A caller passing an Authorization header MUST also
	 *                          pass redirection => 0: with redirection => 5,
	 *                          WP_Http replays request headers to whatever host
	 *                          the redirect lands on.
	 * @return array|WP_Error array( 'body' => mixed, 'total_pages' => int, 'total' => int )
	 */
	public function get( $url, array $overrides = array() ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => isset( $overrides['timeout'] ) ? (int) $overrides['timeout'] : 25,
				'redirection' => isset( $overrides['redirection'] ) ? (int) $overrides['redirection'] : 5,
				'headers'     => array_merge(
					array( 'Accept' => 'application/json' ),
					isset( $overrides['headers'] ) && is_array( $overrides['headers'] ) ? $overrides['headers'] : array()
				),
				'user-agent'  => 'WordCamp Airtable Connector/' . WCAC_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'wcac_source_http',
				sprintf( 'HTTP %d from %s', $code, $url ),
				array( 'status' => $code )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( null === $body ) {
			return new WP_Error( 'wcac_source_json', sprintf( 'Unparseable JSON from %s', $url ) );
		}

		return array(
			'body'        => $body,
			'total_pages' => (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' ),
			'total'       => (int) wp_remote_retrieve_header( $response, 'x-wp-total' ),
		);
	}

	/**
	 * Build a WP REST collection URL.
	 *
	 * @param string $root     Site root, no trailing slash.
	 * @param string $endpoint Collection name, e.g. "wordcamps".
	 * @param array  $args     Query arguments.
	 * @return string
	 */
	protected function url( $root, $endpoint, array $args ) {
		// add_query_arg() runs urlencode_deep() internally, so the values here
		// must be raw -- encoding them first would double-escape the spaces and
		// parentheses in the _fields list.
		return add_query_arg( $args, untrailingslashit( $root ) . '/wp-json/wp/v2/' . $endpoint );
	}

	/**
	 * Fetch one page of WordCamps from Central.
	 *
	 * @param int         $page  1-based page number.
	 * @param string|null $since Optional ISO-8601 datetime; only newer records.
	 * @return array|WP_Error
	 */
	public function wordcamps( $page = 1, $since = null ) {
		// Deliberately no _fields here. Central exposes the wcpt meta under
		// human-readable keys with spaces in them ("Start Date (YYYY-mm-dd)",
		// "Organizer Name", "Event Timezone"), and WordPress parses _fields
		// with wp_parse_list(), which splits on /[\s,]+/ -- whitespace as well
		// as commas. Any name containing a space is shredded into tokens that
		// match nothing, and the field is silently dropped from the response.
		// Asking for the whole payload is the only way to get that meta.
		$args = array(
			'per_page' => 100,
			'page'     => $page,
			'orderby'  => 'modified',
			'order'    => 'desc',
		);

		if ( $since ) {
			$args['modified_after'] = $since;
		}

		return $this->get( $this->url( $this->central_root(), 'wordcamps', $args ) );
	}

	/**
	 * Fetch one page of Meetups from Central.
	 *
	 * @param int         $page  1-based page number.
	 * @param string|null $since Optional ISO-8601 datetime; only newer records.
	 * @return array|WP_Error
	 */
	public function meetups( $page = 1, $since = null ) {
		$args = array(
			'per_page' => 100,
			'page'     => $page,
			'orderby'  => 'modified',
			'order'    => 'desc',
			'_fields'  => self::MEETUP_FIELDS,
		);

		if ( $since ) {
			$args['modified_after'] = $since;
		}

		return $this->get( $this->url( $this->central_root(), 'meetups', $args ) );
	}

	/**
	 * Fetch the Campus Connect details report from Central.
	 *
	 * Not a wp/v2 route, so url() (which hardcodes the namespace) is not used.
	 * The route takes no query parameters -- no per_page, no page, no
	 * modified_after -- and emits no x-wp-totalpages, so total_pages is 0. This
	 * is a single-shot full read on every run; there is no delta mechanism, and
	 * that is what makes the job safely re-runnable after a crash.
	 *
	 * Never add _fields: wp_parse_list() splits on whitespace as well as commas
	 * (see the comment in wordcamps()) and eleven of the report's keys contain
	 * spaces.
	 *
	 * @return array|WP_Error The report's `data` list, or a WP_Error carrying
	 *                        the HTTP status where there was one.
	 */
	public function campus_connect() {
		/*
		 * A file source exists because the Central hop can be unavailable for
		 * reasons no code here can fix: the credential authenticates but the
		 * account holds no role on central.wordcamp.org, so the report route
		 * answers 403. An operator can still run the report in a browser and
		 * export it. Everything downstream -- the preflight gate, the volume
		 * and creation guards, the never-clobber mapper, the failure latch --
		 * is identical either way, because only this one method changes.
		 */
		$file = self::report_file();

		if ( '' !== $file ) {
			return self::campus_connect_from_file( $file );
		}

		$args = $this->central_args( self::report_timeout() );

		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$result = $this->get(
			$this->central_root() . '/wp-json/wordcamp-reports/v1/campus-connect-details',
			$args
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Nothing downstream re-checks the envelope, so an HTML error page that
		// happens to decode, or a maintenance JSON body, must never read here as
		// "zero rows" -- that would be indistinguishable from a genuinely empty
		// report and would look like a clean run that wrote nothing.
		$body = $result['body'];

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'wcac_central_envelope',
				__( 'Campus Connect report returned an unexpected shape.', 'wordcamp-airtable-connector' )
			);
		}

		if ( isset( $body['code'], $body['message'] ) && ! isset( $body['data'][0] ) && ! isset( $body[0] ) ) {
			// A WP_Error serialised as JSON with HTTP 200 (some WAFs and some
			// report failures do this). Its 'data' is an object, not a row list.
			$code = is_scalar( $body['code'] ) ? sanitize_key( (string) $body['code'] ) : '';

			return new WP_Error(
				'wcac_central_envelope',
				sprintf( 'Campus Connect report returned an error body (%s).', $code )
			);
		}

		if ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return new WP_Error(
				'wcac_central_envelope',
				__( 'Campus Connect report returned no data list.', 'wordcamp-airtable-connector' )
			);
		}

		// array_values() normalises an object-shaped `data` map into a list so
		// count() and array_chunk() behave for the caller.
		return array_values( $body['data'] );
	}

	/**
	 * Path the Campus Connect report is read from instead of Central, or ''.
	 *
	 * Static because the sync builds its own WCAC_Source deep inside a job, so
	 * there is no constructor to thread this through. Read at call time, which
	 * means it must be set before the job is drained and only ever lasts for
	 * the current process -- a cron run can never inherit it.
	 *
	 * @var string
	 */
	private static $report_file = '';

	/**
	 * Read the report from this file for the rest of the process.
	 *
	 * @param string $path Absolute path, or '' to go back to the network.
	 * @return void
	 */
	public static function set_report_file( $path ) {
		self::$report_file = is_scalar( $path ) ? (string) $path : '';
	}

	/**
	 * The current file override.
	 *
	 * @return string
	 */
	public static function report_file() {
		return self::$report_file;
	}

	/**
	 * Column names as the report DISPLAYS them, mapped back to the keys the
	 * report's own data list uses, which is what WCAC_Mapper::campus_event()
	 * reads.
	 *
	 * An exported file carries the display names; the REST data list carries
	 * these. Three differ, and silently skipping them would blank a venue,
	 * a city and a country on all 165 rows.
	 */
	const REPORT_FILE_HEADERS = array(
		'Institution Name' => 'Venue Name',
		'City'             => '_venue_city',
		'Country'          => '_venue_country_name',
	);

	/**
	 * Parse an exported Campus Connect Details report.
	 *
	 * Accepts the tab- or comma-separated export with its header row. Returns
	 * the same shape as the REST call: a list of rows keyed by report key, so
	 * nothing downstream can tell the difference.
	 *
	 * @param string $path File path.
	 * @return array|WP_Error
	 */
	public static function campus_connect_from_file( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return new WP_Error( 'wcac_file_unreadable', sprintf( 'Cannot read %s.', $path ) );
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $raw || '' === trim( $raw ) ) {
			return new WP_Error( 'wcac_file_empty', sprintf( '%s is empty.', $path ) );
		}

		// Strip a UTF-8 BOM: a spreadsheet export often carries one, and it
		// would otherwise ride along on the first header name and stop it
		// matching anything.
		if ( 0 === strncmp( $raw, "\xEF\xBB\xBF", 3 ) ) {
			$raw = substr( $raw, 3 );
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$lines = array_values( array_filter( $lines, function ( $l ) { return '' !== trim( $l ); } ) );

		if ( count( $lines ) < 2 ) {
			return new WP_Error( 'wcac_file_empty', sprintf( '%s has a header but no rows.', $path ) );
		}

		/*
		 * The empty escape is RFC 4180: a quote inside a quoted cell is written
		 * by doubling it, and a backslash is just a character. PHP's historical
		 * default treats backslash as an escape, which would corrupt any cell
		 * containing one and is not what the WordPress export produces. It is
		 * passed explicitly because the default is changing.
		 *
		 * Decide the delimiter on the header line, not the whole file: a
		 * free-text cell may well contain a comma, and guessing from the body
		 * would pick wrongly.
		 */
		$tabs      = substr_count( $lines[0], "\t" );
		$commas    = substr_count( $lines[0], ',' );
		$delimiter = ( $tabs > 0 && $tabs >= $commas ) ? "\t" : ',';
		$header    = str_getcsv( $lines[0], $delimiter, '"', '' );
		$header    = array_map( function ( $h ) { return trim( (string) $h ); }, $header );
		$header    = array_map(
			function ( $h ) {
				return isset( self::REPORT_FILE_HEADERS[ $h ] ) ? self::REPORT_FILE_HEADERS[ $h ] : $h;
			},
			$header
		);

		if ( ! in_array( 'ID', $header, true ) ) {
			return new WP_Error(
				'wcac_file_no_id',
				sprintf( '%s has no ID column, so no row could be matched to an Airtable record.', $path )
			);
		}

		$rows  = array();
		$width = count( $header );

		foreach ( array_slice( $lines, 1 ) as $n => $line ) {
			$cells = str_getcsv( $line, $delimiter, '"', '' );

			if ( count( $cells ) !== $width ) {
				return new WP_Error(
					'wcac_file_ragged',
					sprintf(
						'%s line %d has %d cells against %d header columns.',
						$path,
						$n + 2,
						count( $cells ),
						$width
					)
				);
			}

			$rows[] = array_combine( $header, array_map( function ( $c ) { return trim( (string) $c ); }, $cells ) );
		}

		return $rows;
	}

	/**
	 * What non-cookie authentication the source site advertises, read
	 * anonymously. Used to tell "your password is wrong" apart from "this host
	 * strips the Authorization header" in the 401 message.
	 *
	 * @return array array( 'ok' => bool, 'schemes' => string[], 'error' => string )
	 */
	public function central_auth_advertised() {
		$result = $this->get( $this->central_root() . '/wp-json/' );

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'schemes' => array(),
				'error'   => $result->get_error_message(),
			);
		}

		$schemes = array();

		if ( isset( $result['body']['authentication'] ) && is_array( $result['body']['authentication'] ) ) {
			$schemes = array_map( 'strval', array_keys( $result['body']['authentication'] ) );
		}

		return array(
			'ok'      => true,
			'schemes' => $schemes,
			'error'   => '',
		);
	}

	/**
	 * Does the stored credential authenticate at all, independent of the report
	 * route? Distinguishes a revoked password from a route-level 403 and from
	 * an edge that drops Authorization.
	 *
	 * Never returns, and never logs, the username or the response body.
	 *
	 * @return array array( 'status' => int, 'authenticated' => bool )
	 */
	public function central_identity_probe() {
		$args   = $this->central_args( 20 );
		$result = is_wp_error( $args )
			? $args
			: $this->get( $this->central_root() . '/wp-json/wp/v2/users/me?context=edit', $args );

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();

			return array(
				'status'        => isset( $data['status'] ) ? (int) $data['status'] : 0,
				'authenticated' => false,
			);
		}

		return array(
			'status'        => 200,
			'authenticated' => true,
		);
	}

	/**
	 * How long to wait for the Campus Connect report.
	 *
	 * The report generates every Campus Connect event server-side, so 45s is
	 * realistic -- but the admin "Process queue now" button runs through
	 * admin-post.php, and a 45s remote wait under a 30s PHP-FPM
	 * request_terminate_timeout is a hard kill that destroys the job with no
	 * log line (run_slice() persists the shifted queue BEFORE running the job).
	 * So: 45s only where there is no web request to lose.
	 *
	 * @return int Seconds.
	 */
	protected static function report_timeout() {
		$long = ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron();

		return $long ? 45 : 20;
	}

	/**
	 * Request overrides carrying the Central credential.
	 *
	 * Every authenticated call is built here, so campus_connect() and
	 * central_identity_probe() cannot drift apart on the https guard or on
	 * redirection => 0. The credential exists only inside the returned
	 * Authorization header: it is never placed in the URL, never returned in a
	 * WP_Error message, and never passed to WCAC_Logger.
	 *
	 * @param int $timeout Seconds.
	 * @return array|WP_Error Overrides for get(), or the reason there are none.
	 */
	private function central_args( $timeout ) {
		$cred = WCAC_Settings::central_credential();

		if ( ! is_array( $cred ) || empty( $cred['source'] ) || 'none' === $cred['source'] ) {
			return new WP_Error(
				'wcac_central_no_credential',
				__( 'No Central credential configured.', 'wordcamp-airtable-connector' ),
				array( 'status' => 401 )
			);
		}

		// Second line of defence past WCAC_Settings::save(), which cannot see a
		// hand-edited option row or a constant changed after the fact.
		if ( ! $this->central_scheme_ok() ) {
			return new WP_Error(
				'wcac_central_insecure',
				__( 'Refusing to send credentials to a non-https source site.', 'wordcamp-airtable-connector' )
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth, not obfuscation.
		$auth = base64_encode( $cred['user'] . ':' . $cred['pass'] );

		return array(
			'headers'     => array( 'Authorization' => 'Basic ' . $auth ),
			'redirection' => 0,
			'timeout'     => (int) $timeout,
		);
	}

	/**
	 * The source site root, with any user:pass@ authority removed.
	 *
	 * get() bakes the request URL into both of its WP_Error messages, and those
	 * are logged verbatim and printed on the admin screen, so a credential in
	 * the authority would land in wp_options in cleartext. WCAC_Settings::save()
	 * strips userinfo on the way in; this survives a hand-edited option row,
	 * which that guard does not.
	 *
	 * Every read of the root in this class goes through here -- the five
	 * original syncs as well as the two Campus Connect calls -- so none of them
	 * can transmit a legacy authority as Basic auth or log one. The stripping
	 * itself lives in WCAC_Settings::source_root(), the only sanctioned read of
	 * the setting.
	 *
	 * @return string Empty string when there is no usable host.
	 */
	private function central_root() {
		return WCAC_Settings::source_root();
	}

	/**
	 * May the source site be sent an Authorization header?
	 *
	 * https only, unless WCAC_ALLOW_INSECURE_CENTRAL is defined and truthy.
	 *
	 * @return bool
	 */
	private function central_scheme_ok() {
		$scheme = wp_parse_url( $this->central_root(), PHP_URL_SCHEME );

		if ( 'https' === $scheme ) {
			return true;
		}

		return 'http' === $scheme && defined( 'WCAC_ALLOW_INSECURE_CENTRAL' ) && WCAC_ALLOW_INSECURE_CENTRAL;
	}

	/**
	 * Fetch every page of a per-event collection from a camp's own site.
	 *
	 * Camp sites are small (a few hundred posts at most), so this walks the
	 * whole collection rather than queueing per page.
	 *
	 * @param string $site       Camp site root, e.g. "https://europe.wordcamp.org/2026".
	 * @param string $collection One of sessions|speakers|sponsors.
	 * @return array|WP_Error Flat array of post arrays.
	 */
	public function event_posts( $site, $collection ) {
		$out   = array();
		$page  = 1;
		$pages = 1;

		do {
			$result = $this->get(
				$this->url(
					$site,
					$collection,
					array(
						'per_page' => 100,
						'page'     => $page,
						'status'   => 'publish',
					)
				)
			);

			if ( is_wp_error( $result ) ) {
				// An empty collection 400s with rest_post_invalid_page_number
				// once we walk past the end; anything on page 1 is a real error.
				return 1 === $page ? $result : $out;
			}

			if ( ! is_array( $result['body'] ) ) {
				return $out;
			}

			$out   = array_merge( $out, $result['body'] );
			$pages = max( 1, $result['total_pages'] );
			$page++;
		} while ( $page <= $pages && $page <= 20 );

		return $out;
	}

	/**
	 * Term ID => term name map for one taxonomy on one camp site.
	 *
	 * Cached for the lifetime of the request so a camp's sessions and sponsors
	 * do not each re-fetch it.
	 *
	 * @param string $site     Camp site root.
	 * @param string $taxonomy Taxonomy REST base, e.g. "session_track".
	 * @return array
	 */
	public function term_map( $site, $taxonomy ) {
		$key = $site . '|' . $taxonomy;

		if ( isset( $this->term_cache[ $key ] ) ) {
			return $this->term_cache[ $key ];
		}

		$map    = array();
		$result = $this->get(
			$this->url(
				$site,
				$taxonomy,
				array(
					'per_page' => 100,
					'_fields'  => 'id,name',
				)
			)
		);

		if ( ! is_wp_error( $result ) && is_array( $result['body'] ) ) {
			foreach ( $result['body'] as $term ) {
				if ( isset( $term['id'], $term['name'] ) ) {
					$map[ (int) $term['id'] ] = (string) $term['name'];
				}
			}
		}

		$this->term_cache[ $key ] = $map;

		return $map;
	}
}
