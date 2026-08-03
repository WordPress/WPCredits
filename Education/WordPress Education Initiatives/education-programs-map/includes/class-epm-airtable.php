<?php
/**
 * Pulls institutions in from Airtable and keeps them in sync with the local
 * institutions table. Each program (WPCC, WPCredits, Student Club) has its own
 * independent connection, since each can live in a completely different Airtable base.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Airtable {

	const OPTION_NAME      = 'epm_airtable_settings';
	const LAST_RESULT_NAME = 'epm_airtable_last_result';
	const CRON_HOOK        = 'epm_airtable_auto_sync';
	const CRON_INTERVAL    = 'epm_weekly';

	/**
	 * The three programs that can each have their own Airtable connection.
	 *
	 * @var string[]
	 */
	const PROGRAM_KEYS = array( 'wpcc', 'wpcredits', 'student_club' );

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- interval is intentionally 7 days; see register_schedule().
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_syncs' ) );
	}

	/**
	 * Register the "every 7 days" cron interval used for auto-sync.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function register_schedule( $schedules ) {
		$schedules[ self::CRON_INTERVAL ] = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display'  => __( 'Every 7 Days', 'education-programs-map' ),
		);

		return $schedules;
	}

	/**
	 * Schedule the recurring sync if any program has auto-sync enabled and
	 * configured; unschedule it if none do. Call this after any connection is saved.
	 */
	public static function maybe_schedule() {
		$any_enabled = false;

		foreach ( self::PROGRAM_KEYS as $program ) {
			$settings = self::get_settings( $program );
			if ( ! empty( $settings['auto_sync'] ) && self::is_configured( $program ) ) {
				$any_enabled = true;
				break;
			}
		}

		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( $any_enabled && ! $scheduled ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		} elseif ( ! $any_enabled && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback: run a sync for every program that has auto-sync enabled.
	 */
	public static function run_scheduled_syncs() {
		foreach ( self::PROGRAM_KEYS as $program ) {
			$settings = self::get_settings( $program );
			if ( ! empty( $settings['auto_sync'] ) && self::is_configured( $program ) ) {
				self::store_result( $program, self::run_sync( $program ), 'auto' );
			}
		}
	}

	/**
	 * Save the outcome of a sync (manual or automatic) for display in the admin screen.
	 *
	 * @param string          $program Program key the sync was run for.
	 * @param array|WP_Error  $result  Return value of run_sync().
	 * @param string          $trigger Either 'manual' or 'auto'.
	 */
	public static function store_result( $program, $result, $trigger ) {
		$all = get_option( self::LAST_RESULT_NAME );
		$all = is_array( $all ) ? $all : array();

		$record = array(
			'trigger'   => $trigger,
			'timestamp' => time(),
		);

		if ( is_wp_error( $result ) ) {
			$record['error'] = $result->get_error_message();
		} else {
			$record['created'] = $result['created'];
			$record['updated'] = $result['updated'];
			$record['hidden']  = $result['hidden'];
			$record['skipped'] = $result['skipped'];
		}

		$all[ $program ] = $record;

		update_option( self::LAST_RESULT_NAME, $all, false );
	}

	/**
	 * Get the outcome of the most recent sync for a program, if any.
	 *
	 * @param string $program Program key.
	 * @return array|null
	 */
	public static function get_last_result( $program ) {
		$all = get_option( self::LAST_RESULT_NAME );
		return is_array( $all ) && isset( $all[ $program ] ) ? $all[ $program ] : null;
	}

	/**
	 * Default connection settings for a single program.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'token'           => '',
			'base_id'         => '',
			'table_name'      => 'Institutions',
			'countries_table' => 'Countries',
			'filter_formula'  => "{Current Stage}='Confirmed'",
			'auto_sync'       => false,
		);
	}

	/**
	 * Get the current connection settings for one program, merged with defaults.
	 *
	 * @param string $program Program key.
	 * @return array<string,mixed>
	 */
	public static function get_settings( $program ) {
		$all    = get_option( self::OPTION_NAME );
		$all    = is_array( $all ) ? $all : array();
		$stored = isset( $all[ $program ] ) && is_array( $all[ $program ] ) ? $all[ $program ] : array();

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Save the connection settings for one program, leaving the others untouched.
	 *
	 * @param string $program  Program key.
	 * @param array  $settings Settings to save for that program.
	 */
	public static function save_settings( $program, array $settings ) {
		$all             = get_option( self::OPTION_NAME );
		$all             = is_array( $all ) ? $all : array();
		$all[ $program ] = $settings;

		update_option( self::OPTION_NAME, $all, false );
	}

	/**
	 * Whether enough settings are present to attempt a sync for a program.
	 *
	 * @param string $program Program key.
	 * @return bool
	 */
	public static function is_configured( $program ) {
		$settings = self::get_settings( $program );
		return '' !== $settings['token'] && '' !== $settings['base_id'] && '' !== $settings['table_name'];
	}

	/**
	 * Build an Airtable API request URL, encoding repeated params (e.g. fields[]) correctly.
	 *
	 * @param array  $settings Connection settings for the relevant program.
	 * @param string $table    Table name or ID.
	 * @param array  $params   Query parameters; array values become repeated `key[]=` params.
	 * @return string
	 */
	private static function build_url( $settings, $table, $params ) {
		$parts = array();

		foreach ( $params as $key => $value ) {
			foreach ( (array) $value as $single ) {
				$parts[] = rawurlencode( is_array( $value ) ? $key . '[]' : $key ) . '=' . rawurlencode( $single );
			}
		}

		$url = 'https://api.airtable.com/v0/' . rawurlencode( $settings['base_id'] ) . '/' . rawurlencode( $table );

		return $parts ? $url . '?' . implode( '&', $parts ) : $url;
	}

	/**
	 * Perform a single GET request against the Airtable REST API.
	 *
	 * @param array  $settings Connection settings for the relevant program.
	 * @param string $table    Table name or ID.
	 * @param array  $params   Query parameters.
	 * @return array|WP_Error Decoded response body, or WP_Error on failure.
	 */
	private static function api_get( $settings, $table, $params = array() ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- vip_safe_wp_remote_get() only exists on WordPress VIP hosting; this plugin targets general WordPress.
		$response = wp_remote_get(
			self::build_url( $settings, $table, $params ),
			array(
				// phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- runs only during an occasional admin-triggered or weekly cron sync, never on a public page load.
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['token'],
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
	 * @param array  $settings Connection settings for the relevant program.
	 * @param string $table    Table name or ID.
	 * @param array  $params   Query parameters.
	 * @return array|WP_Error
	 */
	private static function fetch_all( $settings, $table, $params = array() ) {
		$records = array();
		$offset  = null;

		do {
			$page_params = $params;
			if ( $offset ) {
				$page_params['offset'] = $offset;
			}

			$page = self::api_get( $settings, $table, $page_params );
			if ( is_wp_error( $page ) ) {
				return $page;
			}

			$records = array_merge( $records, $page['records'] ?? array() );
			$offset  = $page['offset'] ?? null;
		} while ( $offset );

		return $records;
	}

	/**
	 * Build a record-ID => name map for the linked "Countries" table.
	 *
	 * @param array  $settings Connection settings for the relevant program.
	 * @param string $table    Countries table name or ID.
	 * @return array<string,string>|WP_Error
	 */
	private static function fetch_country_names( $settings, $table ) {
		$records = self::fetch_all( $settings, $table, array( 'fields' => array( 'Name' ) ) );

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$map = array();
		foreach ( $records as $record ) {
			$map[ $record['id'] ] = $record['fields']['Name'] ?? '';
		}

		return $map;
	}

	/**
	 * Pull matching institution records from a program's Airtable base, geocode
	 * them, and upsert them into the local institutions table. Any previously-synced
	 * institution (for this program) whose Airtable record no longer matches the
	 * filter is hidden from the public map rather than deleted.
	 *
	 * @param string $program Program key to sync and assign to imported institutions.
	 * @return array{created:int,updated:int,hidden:int,skipped:string[]}|WP_Error
	 */
	public static function run_sync( $program ) {
		if ( ! self::is_configured( $program ) ) {
			return new WP_Error( 'epm_airtable_not_configured', __( 'Add an Airtable token, base ID, and table name before syncing.', 'education-programs-map' ) );
		}

		$settings = self::get_settings( $program );

		$countries = self::fetch_country_names( $settings, $settings['countries_table'] );
		if ( is_wp_error( $countries ) ) {
			return $countries;
		}

		$fields_to_fetch = array( 'Name', 'City', 'Country', 'Website' );
		$params          = array( 'fields' => $fields_to_fetch );
		if ( '' !== $settings['filter_formula'] ) {
			$params['filterByFormula'] = $settings['filter_formula'];
		}

		$records = self::fetch_all( $settings, $settings['table_name'], $params );
		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$created     = 0;
		$updated     = 0;
		$skipped     = array();
		$matched_ids = array();

		foreach ( $records as $record ) {
			$fields = $record['fields'] ?? array();
			$name   = sanitize_text_field( trim( (string) ( $fields['Name'] ?? '' ) ) );
			$city   = sanitize_text_field( trim( (string) ( $fields['City'] ?? '' ) ) );

			if ( '' === $name || '' === $city ) {
				$skipped[] = ( '' !== $name ? $name : $record['id'] ) . ' — ' . __( 'missing name or city', 'education-programs-map' );
				continue;
			}

			$country = '';
			foreach ( (array) ( $fields['Country'] ?? array() ) as $country_id ) {
				if ( ! empty( $countries[ $country_id ] ) ) {
					$country = sanitize_text_field( $countries[ $country_id ] );
					break;
				}
			}

			$location = EPM_Geocoder::geocode( $city, $country );
			if ( ! $location ) {
				$skipped[] = "{$name} — " . __( 'could not determine coordinates', 'education-programs-map' );
				continue;
			}

			$website = trim( (string) ( $fields['Website'] ?? '' ) );
			if ( '' !== $website && ! preg_match( '#^https?://#i', $website ) ) {
				$website = 'https://' . $website;
			}

			$data = array(
				'name'             => $name,
				'city'             => $city,
				'country'          => $country,
				'latitude'         => $location['lat'],
				'longitude'        => $location['lng'],
				'programs'         => array( $program ),
				'event_count'      => 0,
				'website'          => $website ? esc_url_raw( $website ) : '',
				'wpcc_url'         => '',
				'student_club_url' => '',
				'airtable_id'      => $record['id'],
				'hidden'           => false,
			);

			$existing = EPM_DB::get_by_airtable_id( $record['id'] );

			if ( $existing ) {
				// Keep manually-added programs/links intact; only refresh the Airtable-sourced fields.
				$data['programs']         = array_unique( array_merge( EPM_DB::parse_programs( $existing->programs ), array( $program ) ) );
				$data['wpcc_url']         = $existing->wpcc_url;
				$data['student_club_url'] = $existing->student_club_url;
				$data['event_count']      = $existing->event_count;

				EPM_DB::update( $existing->id, $data );
				++$updated;
			} else {
				EPM_DB::insert( $data );
				++$created;
			}

			$matched_ids[] = $record['id'];

			// Be a good citizen towards the free geocoding service; avoid hammering it.
			usleep( 500000 );
		}

		$hidden = EPM_DB::hide_airtable_records_except( $program, $matched_ids );

		return array(
			'created' => $created,
			'updated' => $updated,
			'hidden'  => $hidden,
			'skipped' => $skipped,
		);
	}
}
