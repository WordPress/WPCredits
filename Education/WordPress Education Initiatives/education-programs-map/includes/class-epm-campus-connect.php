<?php
/**
 * Pulls WordPress Campus Connect events in from the Airtable "WordCamps" table
 * (synced there from central.wordcamp.org) and turns them into markers on the map.
 *
 * This is deliberately a separate sync from EPM_Airtable, because the source is
 * shaped nothing like an institutions table:
 *
 * - It is a table of *events*, not institutions, so several events can share one
 *   location and have to be rolled up into a single marker.
 * - Every record already carries Latitude/Longitude, so this sync never touches
 *   EPM_Geocoder and has no per-record rate-limit sleep.
 * - "Country" is plain text here, not a link to a separate Countries table.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Campus_Connect {

	const OPTION_NAME      = 'epm_campus_connect_settings';
	const LAST_RESULT_NAME = 'epm_campus_connect_last_result';

	/**
	 * Marks the institutions table rows this sync owns. Rows tagged with this are
	 * the only ones its hiding pass may touch — see EPM_DB::hide_airtable_records_except().
	 */
	const SOURCE = 'campus_connect';

	/**
	 * Imported markers are tagged with the existing WPCC program, so they appear
	 * under the map's current "WPCC (WordPress Campus Connect)" filter rather than
	 * introducing a second Campus-Connect-flavoured filter button.
	 */
	const TARGET_PROGRAM = 'wpcc';

	/**
	 * Airtable fields this sync reads. "Event Type" is a formula field on the
	 * source table that distinguishes Campus Connect events from WordCamps.
	 *
	 * @var string[]
	 */
	const FIELDS = array(
		'Name',
		'City',
		'Country',
		'Latitude',
		'Longitude',
		'Start Date',
		'End Date',
		'Site URL',
		'Central Link',
		'Venue Name',
		'Organizer',
	);

	/**
	 * Default connection settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'token'          => '',
			'base_id'        => '',
			'table_name'     => 'WordCamps',
			'filter_formula' => "{Event Type}='Campus Connect'",
			'auto_sync'      => false,
		);
	}

	/**
	 * Get the current connection settings, merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION_NAME );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * Save the connection settings.
	 *
	 * @param array $settings Settings to save.
	 */
	public static function save_settings( array $settings ) {
		update_option( self::OPTION_NAME, $settings, false );
	}

	/**
	 * Whether enough settings are present to attempt a sync.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$settings = self::get_settings();

		return '' !== $settings['token'] && '' !== $settings['base_id'] && '' !== $settings['table_name'];
	}

	/**
	 * Whether the weekly cron should run this sync.
	 *
	 * @return bool
	 */
	public static function is_auto_sync_ready() {
		$settings = self::get_settings();

		return ! empty( $settings['auto_sync'] ) && self::is_configured();
	}

	/**
	 * Save the outcome of a sync for display in the admin screen.
	 *
	 * @param array|WP_Error $result  Return value of run_sync().
	 * @param string         $trigger Either 'manual' or 'auto'.
	 */
	public static function store_result( $result, $trigger ) {
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
			$record['events']  = $result['events'];
			$record['skipped'] = $result['skipped'];
		}

		update_option( self::LAST_RESULT_NAME, $record, false );
	}

	/**
	 * Get the outcome of the most recent sync, if any.
	 *
	 * @return array|null
	 */
	public static function get_last_result() {
		$stored = get_option( self::LAST_RESULT_NAME );

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Clip a value to fit its database column.
	 *
	 * Venue names in the source are free text and occasionally enormous — one
	 * Ugandan event names eleven schools in a single 255-character field, which
	 * overflows the name column and makes the whole insert fail.
	 *
	 * The budget is counted in BYTES, not characters, because the institutions
	 * table can be latin1 (it inherits whatever charset the site had when the table
	 * was created), and a latin1 VARCHAR(191) holds 191 bytes. Clipping to 191
	 * characters is not enough: 191 characters of text containing curly quotes runs
	 * to 197 bytes and is still rejected. mb_strcut() trims to a byte budget without
	 * splitting a multi-byte character in half.
	 *
	 * @param string $value  Value to clip.
	 * @param int    $length Maximum length in bytes.
	 * @return string
	 */
	private static function clip( $value, $length = 191 ) {
		if ( strlen( $value ) <= $length ) {
			return $value;
		}

		$ellipsis = '…';

		return mb_strcut( $value, 0, $length - strlen( $ellipsis ) ) . $ellipsis;
	}

	/**
	 * The key that decides which events share a marker.
	 *
	 * Events are grouped by city, which matches what the map is for ("city-level
	 * markers") and correctly merges repeat activity — Ajmer, for example, has held
	 * three Campus Connect events at three different colleges. Grouping by
	 * coordinates instead would merge almost nothing, and would wrongly merge
	 * distinct venues that happen to geocode within a few hundred metres.
	 *
	 * Records with no City fall back to their own record ID, so each becomes its own
	 * marker rather than all of a country's city-less events collapsing together.
	 *
	 * @param string $city      City name, possibly empty.
	 * @param string $country   Country name.
	 * @param string $record_id Airtable record ID, used as the fallback key.
	 * @return string
	 */
	private static function group_key( $city, $country, $record_id ) {
		if ( '' === $city ) {
			return 'rec:' . $record_id;
		}

		return 'city:' . strtolower( $city . '|' . $country );
	}

	/**
	 * Normalize an Airtable record into the event shape this sync works with,
	 * or return null when it cannot be placed on the map.
	 *
	 * @param array $record Raw Airtable record.
	 * @return array|null
	 */
	private static function prepare_event( $record ) {
		$fields = $record['fields'] ?? array();

		// Unlike the institutions sync, coordinates come straight from Airtable, so a
		// record without them cannot be rescued by geocoding and is simply skipped.
		if ( ! isset( $fields['Latitude'] ) || ! isset( $fields['Longitude'] ) ) {
			return null;
		}

		$venue = sanitize_text_field( trim( (string) ( $fields['Venue Name'] ?? '' ) ) );
		$name  = sanitize_text_field( trim( (string) ( $fields['Name'] ?? '' ) ) );

		$url = trim( (string) ( $fields['Site URL'] ?? '' ) );
		if ( '' === $url ) {
			$url = trim( (string) ( $fields['Central Link'] ?? '' ) );
		}

		return array(
			'id'        => $record['id'],
			'city'      => sanitize_text_field( trim( (string) ( $fields['City'] ?? '' ) ) ),
			'country'   => sanitize_text_field( trim( (string) ( $fields['Country'] ?? '' ) ) ),
			'latitude'  => (float) $fields['Latitude'],
			'longitude' => (float) $fields['Longitude'],
			// Fall back to the event title when a record has no venue, so the marker
			// still has something specific to show.
			'venue'     => '' !== $venue ? $venue : $name,
			'name'      => $name,
			'date'      => sanitize_text_field( trim( (string) ( $fields['Start Date'] ?? '' ) ) ),
			'endDate'   => sanitize_text_field( trim( (string) ( $fields['End Date'] ?? '' ) ) ),
			'url'       => '' !== $url ? esc_url_raw( $url ) : '',
			'organizer' => sanitize_text_field( trim( (string) ( $fields['Organizer'] ?? '' ) ) ),
		);
	}

	/**
	 * Group prepared events by location, newest event first within each group.
	 *
	 * @param array[] $events Prepared events.
	 * @return array<string,array[]>
	 */
	private static function group_events( array $events ) {
		$groups = array();

		foreach ( $events as $event ) {
			$key = self::group_key( $event['city'], $event['country'], $event['id'] );

			$groups[ $key ][] = $event;
		}

		foreach ( $groups as $key => $group ) {
			usort(
				$group,
				static function ( $a, $b ) {
					return strcmp( $b['date'], $a['date'] );
				}
			);

			$groups[ $key ] = $group;
		}

		return $groups;
	}

	/**
	 * Pull Campus Connect events from Airtable, roll them up per location, and
	 * upsert one institutions-table row per location. Rows from a previous run
	 * whose location no longer appears are hidden rather than deleted, matching
	 * how the institutions sync behaves.
	 *
	 * @return array{created:int,updated:int,hidden:int,events:int,skipped:string[]}|WP_Error
	 */
	public static function run_sync() {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'epm_campus_connect_not_configured', __( 'Add an Airtable token, base ID, and table name before syncing.', 'education-programs-map' ) );
		}

		$settings = self::get_settings();

		$params = array( 'fields' => self::FIELDS );
		if ( '' !== $settings['filter_formula'] ) {
			$params['filterByFormula'] = $settings['filter_formula'];
		}

		$records = EPM_Airtable_Client::fetch_all( $settings['token'], $settings['base_id'], $settings['table_name'], $params );
		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$prepared = array();
		$skipped  = array();

		foreach ( $records as $record ) {
			$event = self::prepare_event( $record );

			if ( null === $event ) {
				$name      = trim( (string) ( $record['fields']['Name'] ?? '' ) );
				$skipped[] = ( '' !== $name ? $name : $record['id'] ) . ' — ' . __( 'no coordinates in Airtable', 'education-programs-map' );
				continue;
			}

			$prepared[] = $event;
		}

		$created     = 0;
		$updated     = 0;
		$matched_ids = array();

		foreach ( self::group_events( $prepared ) as $group ) {
			$newest = $group[0];

			// A single-event location is named after its venue; a location with several
			// is named after the city, since the individual venues differ and are listed
			// in the marker's popup instead.
			$name = count( $group ) > 1 && '' !== $newest['city'] ? $newest['city'] : $newest['venue'];
			if ( '' === $name ) {
				$name = '' !== $newest['city'] ? $newest['city'] : $newest['country'];
			}

			$key         = self::group_key( $newest['city'], $newest['country'], $newest['id'] );
			$airtable_id = 'cc:' . md5( $key );

			$events = array_map(
				static function ( $event ) {
					return array(
						'venue'     => $event['venue'],
						'date'      => $event['date'],
						'endDate'   => $event['endDate'],
						'url'       => $event['url'],
						'organizer' => $event['organizer'],
					);
				},
				$group
			);

			$data = array(
				'name'             => self::clip( $name ),
				'city'             => self::clip( $newest['city'] ),
				'country'          => self::clip( $newest['country'] ),
				'latitude'         => $newest['latitude'],
				'longitude'        => $newest['longitude'],
				'programs'         => array( self::TARGET_PROGRAM ),
				'event_count'      => count( $group ),
				'website'          => '',
				'wpcc_url'         => self::clip( $newest['url'], 255 ),
				'student_club_url' => '',
				'airtable_id'      => $airtable_id,
				'source'           => self::SOURCE,
				'events'           => $events,
				'hidden'           => false,
			);

			$existing = EPM_DB::get_by_airtable_id( $airtable_id );

			// Recorded before the save is attempted: a marker that already exists should
			// not be hidden just because one sync run failed to update it.
			$matched_ids[] = $airtable_id;

			if ( $existing ) {
				// Keep any program or Student Club link an admin added by hand; everything
				// else about these rows is owned by this sync.
				$data['programs']         = array_unique( array_merge( EPM_DB::parse_programs( $existing->programs ), array( self::TARGET_PROGRAM ) ) );
				$data['student_club_url'] = $existing->student_club_url;
				$data['website']          = $existing->website;

				$saved = EPM_DB::update( $existing->id, $data );
			} else {
				$saved = EPM_DB::insert( $data );
			}

			// Counting without checking would over-report the number of markers built,
			// hiding the fact that a row never made it into the database at all.
			if ( is_wp_error( $saved ) ) {
				$skipped[] = self::clip( $name, 80 ) . ' — ' . __( 'could not be saved to the database', 'education-programs-map' );
				continue;
			}

			if ( $existing ) {
				++$updated;
			} else {
				++$created;
			}
		}

		$hidden = EPM_DB::hide_airtable_records_except( self::TARGET_PROGRAM, $matched_ids, self::SOURCE );

		return array(
			'created' => $created,
			'updated' => $updated,
			'hidden'  => $hidden,
			'events'  => count( $prepared ),
			'skipped' => $skipped,
		);
	}
}
