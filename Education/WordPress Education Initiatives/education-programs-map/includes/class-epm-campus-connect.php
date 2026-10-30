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
	 * Where events are read from.
	 *
	 * 'central' reads central.wordcamp.org's public WordCamps route directly.
	 * 'airtable' reads the copy of that route synced into the Airtable
	 * "WordCamps" table, which is the original path and stays as the fallback.
	 */
	const SOURCE_CENTRAL  = 'central';
	const SOURCE_AIRTABLE = 'airtable';

	/** Public REST collection holding every public WordCamp, Campus Connect included. */
	const CENTRAL_ENDPOINT = 'https://central.wordcamp.org/wp-json/wp/v2/wordcamps';

	/**
	 * Search term that narrows that route to Campus Connect candidates.
	 *
	 * A narrowing, not the rule: WordPress searches content as well as title,
	 * so the result is still filtered by name in is_campus_connect().
	 */
	const CENTRAL_SEARCH = 'campus connect';

	/**
	 * Hard cap on pages followed.
	 *
	 * The search returns 99 rows today against a page size of 100, so a second
	 * page is weeks away rather than hypothetical. Twenty pages is far past any
	 * real figure and exists only so a wrong X-WP-TotalPages cannot spin here.
	 */
	const CENTRAL_MAX_PAGES = 20;

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
			/* Defaults to the Airtable copy, which is where this install
			   already is. Moving to Central is an explicit change, so an
			   upgrade cannot repoint a live map underneath it. */
			'source'         => self::SOURCE_AIRTABLE,
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

		// The Central route is public, so there is nothing to configure and
		// nothing that can be configured wrongly.
		if ( self::SOURCE_CENTRAL === $settings['source'] ) {
			return true;
		}

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
	 * Read events from Central's public WordCamps route.
	 *
	 * @return array{events:array[],skipped:string[]}|WP_Error
	 */
	private static function read_from_central() {
		$posts = self::fetch_central();

		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$events  = array();
		$skipped = array();

		foreach ( $posts as $post ) {
			// Most of this route is regular WordCamps. Those are not skips and
			// must not be reported as any, or the admin screen would list a
			// thousand of them after every run.
			if ( ! self::is_campus_connect( $post ) ) {
				continue;
			}

			$event = self::prepare_central_event( $post );

			if ( null === $event ) {
				$name      = self::clip( trim( (string) ( $post['Venue Name'] ?? '' ) ), 80 );
				$skipped[] = ( '' !== $name ? $name : (string) ( $post['id'] ?? '?' ) ) . ' — ' . __( 'no venue coordinates on WordCamp Central', 'education-programs-map' );
				continue;
			}

			$events[] = $event;
		}

		return array(
			'events'  => $events,
			'skipped' => $skipped,
		);
	}

	/**
	 * Read events from the Airtable copy of that route.
	 *
	 * The original path, unchanged. It is the fallback when Central cannot be
	 * reached, and the only path that can see anything hand-edited in the base.
	 *
	 * @param array $settings Connection settings.
	 * @return array{events:array[],skipped:string[]}|WP_Error
	 */
	private static function read_from_airtable( $settings ) {
		$params = array( 'fields' => self::FIELDS );

		if ( '' !== $settings['filter_formula'] ) {
			$params['filterByFormula'] = $settings['filter_formula'];
		}

		$records = EPM_Airtable_Client::fetch_all( $settings['token'], $settings['base_id'], $settings['table_name'], $params );

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$events  = array();
		$skipped = array();

		foreach ( $records as $record ) {
			$event = self::prepare_event( $record );

			if ( null === $event ) {
				$name      = trim( (string) ( $record['fields']['Name'] ?? '' ) );
				$skipped[] = ( '' !== $name ? $name : $record['id'] ) . ' — ' . __( 'no coordinates in Airtable', 'education-programs-map' );
				continue;
			}

			$events[] = $event;
		}

		return array(
			'events'  => $events,
			'skipped' => $skipped,
		);
	}

	/**
	 * Fetch every Campus Connect candidate from Central's public route.
	 *
	 * Anonymous on purpose. No token, no base, nothing to expire: the route
	 * answers 200 to an unauthenticated request, which is the whole reason this
	 * sync no longer needs Airtable.
	 *
	 * @return array|WP_Error List of REST payloads.
	 */
	private static function fetch_central() {
		$posts = array();
		$page  = 1;
		$pages = 1;

		do {
			$url = add_query_arg(
				array(
					'per_page' => 100,
					'page'     => $page,
					'search'   => self::CENTRAL_SEARCH,
					// Relevance ordering is not stable across pages; an id sort
					// is, so nothing is read twice or skipped.
					'orderby'  => 'id',
					'order'    => 'asc',
				),
				self::CENTRAL_ENDPOINT
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 25,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( 200 !== $code ) {
				return new WP_Error(
					'epm_campus_connect_http',
					sprintf(
						/* translators: 1: HTTP status code. 2: requested URL. */
						__( 'WordCamp Central answered HTTP %1$d for %2$s.', 'education-programs-map' ),
						$code,
						$url
					)
				);
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			// An error envelope is still valid JSON and can still arrive with a
			// 200. A list of posts is the only shape that may be used.
			if ( ! is_array( $body ) || ( ! empty( $body ) && ! isset( $body[0] ) ) ) {
				return new WP_Error(
					'epm_campus_connect_shape',
					__( 'WordCamp Central did not return a list of events.', 'education-programs-map' )
				);
			}

			$posts = array_merge( $posts, $body );

			if ( 1 === $page ) {
				$pages = max( 1, (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' ) );
			}

			++$page;
		} while ( $page <= $pages && $page <= self::CENTRAL_MAX_PAGES );

		return $posts;
	}

	/**
	 * Normalize a WordCamp post from central.wordcamp.org into the same event
	 * shape prepare_event() produces, or null when it cannot be mapped.
	 *
	 * The Airtable "WordCamps" table was only ever a copy of this route, which
	 * is public: an anonymous request to wp/v2/wordcamps returns every public
	 * Campus Connect event with its venue coordinates already filled in. So
	 * this reads the original instead of the copy, and deliberately returns the
	 * identical shape, so group_events(), group_key() and the marker writer are
	 * untouched by where the events came from.
	 *
	 * The route is about 94% regular WordCamps, so the Campus Connect subset is
	 * picked out by name. That is the rule the Airtable base used too, in a
	 * formula field: IF(FIND("campus connect", LOWER({Name})), ...). It is
	 * default-deny, so an event this does not recognise is left off the map
	 * rather than guessed onto it.
	 *
	 * @param array $post REST payload from wp/v2/wordcamps.
	 * @return array|null
	 */
	public static function prepare_central_event( $post ) {
		if ( ! is_array( $post ) || empty( $post['id'] ) ) {
			return null;
		}

		if ( ! self::is_campus_connect( $post ) ) {
			return null;
		}

		$name = self::central_title( $post );

		$coords = isset( $post['_venue_coordinates'] ) && is_array( $post['_venue_coordinates'] )
			? $post['_venue_coordinates']
			: array();

		$lat = isset( $coords['latitude'] ) ? $coords['latitude'] : '';
		$lng = isset( $coords['longitude'] ) ? $coords['longitude'] : '';

		/* is_numeric(), not a truth test: 0 is a real latitude. Kampala sits at
		   0.3 and Quito at -0.2. It also refuses "n/a", which a (float) cast
		   would silently turn into a marker in the Gulf of Guinea. This sync
		   never geocodes, so a post without usable coordinates cannot be
		   rescued and is skipped, exactly as a record with no Latitude was. */
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}

		$venue = sanitize_text_field( trim( (string) ( $post['Venue Name'] ?? '' ) ) );
		$url   = trim( (string) ( $post['URL'] ?? '' ) );

		if ( '' === $url ) {
			// An event approved but not yet given its own site still has a
			// Central page, which beats a marker with no link.
			$url = trim( (string) ( $post['link'] ?? '' ) );
		}

		return array(
			'id'        => (string) (int) $post['id'],
			'city'      => sanitize_text_field( trim( (string) ( $post['_venue_city'] ?? '' ) ) ),
			'country'   => sanitize_text_field( trim( (string) ( $post['_venue_country_name'] ?? '' ) ) ),
			'latitude'  => (float) $lat,
			'longitude' => (float) $lng,
			// Fall back to the event title when a post has no venue, so the
			// marker still has something specific to show.
			'venue'     => '' !== $venue ? $venue : $name,
			'name'      => $name,
			'date'      => self::central_date( $post['Start Date (YYYY-mm-dd)'] ?? '' ),
			'endDate'   => self::central_date( $post['End Date (YYYY-mm-dd)'] ?? '' ),
			'url'       => '' !== $url ? esc_url_raw( $url ) : '',
			'organizer' => sanitize_text_field( trim( (string) ( $post['Organizer Name'] ?? '' ) ) ),
		);
	}

	/**
	 * Whether a WordCamp post from Central is a Campus Connect event.
	 *
	 * Its own method because the caller needs the question answered separately
	 * from the mapping: a post that is not a Campus Connect event is most of
	 * the route and is skipped silently, while a Campus Connect event with no
	 * coordinates is a real omission worth reporting. Both come back as null
	 * from prepare_central_event(), so the rule lives here rather than being
	 * written out a second time at the call site.
	 *
	 * @param mixed $post REST payload from wp/v2/wordcamps.
	 * @return bool
	 */
	public static function is_campus_connect( $post ) {
		return is_array( $post ) && false !== stripos( self::central_title( $post ), 'campus connect' );
	}

	/**
	 * The decoded title of a WordCamp post.
	 *
	 * wp/v2 renders it into array( 'rendered' => ... ) and HTML-encodes it, so
	 * "KIST College &amp; SS" has to be decoded before it is matched on or put
	 * in a marker popup.
	 *
	 * @param array $post REST payload.
	 * @return string
	 */
	private static function central_title( array $post ) {
		$title = $post['title'] ?? '';

		if ( is_array( $title ) ) {
			$title = $title['rendered'] ?? '';
		}

		if ( ! is_scalar( $title ) ) {
			return '';
		}

		return sanitize_text_field( html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * A WordCamp date, as Y-m-d.
	 *
	 * Central stores these as unix timestamps in meta fields whose names claim
	 * otherwise ("Start Date (YYYY-mm-dd)"). A raw timestamp would render as a
	 * ten-digit number in a marker popup. A value that is already a date is
	 * left alone, because the hand-exported report gives dates rather than
	 * timestamps and there is no reason for this to care which it got.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	private static function central_date( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ctype_digit( $value ) ) {
			// 0, or anything before the epoch, is an absent date rather than
			// 1 January 1970.
			return (int) $value > 0 ? gmdate( 'Y-m-d', (int) $value ) : '';
		}

		return sanitize_text_field( $value );
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

		$prepared = self::SOURCE_CENTRAL === $settings['source']
			? self::read_from_central()
			: self::read_from_airtable( $settings );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$skipped  = $prepared['skipped'];
		$prepared = $prepared['events'];

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
