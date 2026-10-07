<?php
/**
 * Pure normalisation of Campus Connect report rows. No WordPress dependency
 * - this file must load and run under plain PHP so it can be unit-tested.
 *
 * @package WPCC_Tracker
 */

class WPCCT_Normalize {

	const STATUS_COMPLETED = 'completed';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_PLANNING  = 'planning';
	const STATUS_EXCLUDED  = 'excluded';

	const REGIONS = array(
		'Asia',
		'Europe',
		'Africa',
		'Latin America and Caribbean',
		'North America',
	);

	/**
	 * Apply a WordPress filter when running inside WordPress; pass the value
	 * through untouched when running under the bare-PHP test harness.
	 *
	 * @param string $hook  Filter name.
	 * @param mixed  $value Value to filter.
	 * @return mixed
	 */
	public static function filter( $hook, $value ) {
		return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value ) : $value;
	}

	/**
	 * Map a Campus Connect Details status to a reporting bucket.
	 *
	 * The report emits eleven statuses. Unknown statuses are excluded rather
	 * than guessed at, so a new upstream status never silently inflates a
	 * headline figure - it shows up as a shortfall instead.
	 *
	 * @param string $status Raw status from the report.
	 * @return string One of the STATUS_* constants.
	 */
	public static function status_bucket( $status ) {
		$map    = self::status_map();
		$status = trim( (string) $status );

		return isset( $map[ $status ] ) ? $map[ $status ] : self::STATUS_EXCLUDED;
	}

	/**
	 * The Status to bucket map, after the filter.
	 *
	 * Factored out so status_bucket() and is_known_status() read exactly the
	 * same thing. Asking the filter separately from two places invites them to
	 * disagree, and the disagreement would be invisible: a status the bucketer
	 * knows but the reporter calls unmapped, or the reverse.
	 *
	 * @return array Status => one of the STATUS_* constants.
	 */
	public static function status_map() {
		return self::filter(
			'wpcct_status_buckets',
			array(
				'Closed'                                      => self::STATUS_COMPLETED,
				'Scheduled'                                   => self::STATUS_SCHEDULED,
				'In Pre-Planning'                             => self::STATUS_PLANNING,
				'Needs Vetting'                               => self::STATUS_PLANNING,
				'Needs Orientation/Interview'                 => self::STATUS_PLANNING,
				'Interview/Orientation Scheduled'             => self::STATUS_PLANNING,
				'Approved for Pre-Planning Pending Agreement' => self::STATUS_PLANNING,
				'Needs to Fill Out Listing'                   => self::STATUS_PLANNING,
				'On Hold'                                     => self::STATUS_PLANNING,
				'Cancelled'                                   => self::STATUS_EXCLUDED,
				'Declined'                                    => self::STATUS_EXCLUDED,
			)
		);
	}

	/**
	 * Is this Status one the bucket map actually knows?
	 *
	 * status_bucket() cannot answer it. Its fallback is STATUS_EXCLUDED, so
	 * 'Cancelled', the report's later spelling 'Canceled' and a value nobody
	 * has ever seen all come back identical. The fallback is the safe
	 * direction, because a strange status must never inflate a headline
	 * figure, but it is a silent one: a new planning stage upstream would
	 * shrink the planning count with nothing anywhere to show for it.
	 *
	 * Callers use this to REPORT, never to bucket. Nothing about an
	 * unrecognised status changes where its event is counted.
	 *
	 * @param string $status Raw Status from the report; trimmed here, exactly
	 *                       as status_bucket() trims it.
	 * @return bool
	 */
	public static function is_known_status( $status ) {
		$map = self::status_map();

		return isset( $map[ trim( (string) $status ) ] );
	}

	/**
	 * Resolve a country value that is not quite a country name.
	 *
	 * The report's Country column is free text filled in by organisers, so it
	 * occasionally holds a state or a region instead.
	 *
	 * @param string $country Raw country value.
	 * @return string Canonical country name.
	 */
	public static function canonical_country( $country ) {
		$aliases = self::filter(
			'wpcct_country_aliases',
			array(
				'Maharashtra, India' => 'India',
			)
		);

		$country = trim( (string) $country );

		return isset( $aliases[ $country ] ) ? $aliases[ $country ] : $country;
	}

	/**
	 * Map a country to one of the five reporting regions.
	 *
	 * Returns an empty string for anything unmapped. Callers must count those
	 * separately and surface them rather than bucketing them somewhere - a new
	 * country should appear as an admin notice, not as a silent Asia entry.
	 *
	 * @param string $country Raw or canonical country name.
	 * @return string Region name, or '' when unmapped.
	 */
	public static function region_for( $country ) {
		$map = self::filter(
			'wpcct_region_map',
			array(
				// Asia.
				'India'                => 'Asia',
				'Indonesia'            => 'Asia',
				'Bangladesh'           => 'Asia',
				'Malaysia'             => 'Asia',
				'Philippines'          => 'Asia',
				'Nepal'                => 'Asia',
				'Pakistan'             => 'Asia',
				'Japan'                => 'Asia',
				'Taiwan'               => 'Asia',
				'Hong Kong'            => 'Asia',
				'United Arab Emirates' => 'Asia',
				'Lebanon'              => 'Asia',
				// Europe.
				'Spain'                => 'Europe',
				'Italy'                => 'Europe',
				'Croatia'              => 'Europe',
				'Poland'               => 'Europe',
				'Ukraine'              => 'Europe',
				// Africa.
				'Uganda'               => 'Africa',
				'Nigeria'              => 'Africa',
				'Egypt'                => 'Africa',
				'Namibia'              => 'Africa',
				'Tanzania'             => 'Africa',
				// Latin America and Caribbean.
				'Costa Rica'           => 'Latin America and Caribbean',
				'Guatemala'            => 'Latin America and Caribbean',
				'Nicaragua'            => 'Latin America and Caribbean',
				'Brazil'               => 'Latin America and Caribbean',
				'El Salvador'          => 'Latin America and Caribbean',
				// North America.
				'United States'        => 'North America',
			)
		);

		$country = self::canonical_country( $country );

		return isset( $map[ $country ] ) ? $map[ $country ] : '';
	}

	/**
	 * Is this row a test submission rather than a real event?
	 *
	 * Three events name fictional countries. Two sit in planning statuses, so
	 * without this they inflate both the planning count and the institution
	 * count.
	 *
	 * @param array $row Report row keyed by column header.
	 * @return bool
	 */
	public static function is_denied( $row ) {
		$countries = self::filter(
			'wpcct_excluded_rows',
			array( 'Tomorrowland', 'Atlantis', 'Wonderland' )
		);

		$country = self::canonical_country( isset( $row['Country'] ) ? $row['Country'] : '' );

		return in_array( $country, $countries, true );
	}

	/**
	 * Actual attendance for a row.
	 *
	 * This is the only attendance figure that may be aggregated. It is clean
	 * integer data and is populated only on completed events.
	 *
	 * @param array $row Report row.
	 * @return int
	 */
	public static function attendees( $row ) {
		$raw = isset( $row['Actual Attendees'] ) ? $row['Actual Attendees'] : '';

		// Airtable's API returns a number field as a JSON number, not a string.
		if ( is_int( $raw ) ) {
			return $raw > 0 ? $raw : 0;
		}
		if ( is_float( $raw ) ) {
			// A fractional headcount is not a headcount.
			return ( $raw > 0 && floor( $raw ) === $raw ) ? (int) $raw : 0;
		}

		$value = trim( (string) $raw );

		/* Only a plain integer, optionally grouped into thousands, is a
		   headcount. Stripping every non-digit instead would turn '12.5' into
		   125 - a silent 10x error in a headline figure - and '80-100' into
		   80100. Anything that is not unambiguously a count reads as 0. */
		if ( ! preg_match( '/^(?:\d+|\d{1,3}(?:[, ]\d{3})+)$/', $value ) ) {
			return 0;
		}

		return (int) preg_replace( '/[^0-9]/', '', $value );
	}

	/**
	 * Anticipated attendance, verbatim.
	 *
	 * Deliberately a string. The report's column is free text filled in by
	 * organisers - real values include '80-100', '40-60 attendees' and
	 * 'Ideally dozens.' Summing it produces nonsense (596,477,463 across the
	 * 2026-08-27 export), so this value is displayed per event and never
	 * aggregated. There is intentionally no total_anticipated() counterpart.
	 *
	 * @param array $row Report row.
	 * @return string
	 */
	public static function anticipated( $row ) {
		$raw = isset( $row['Number of Anticipated Attendees'] ) ? $row['Number of Anticipated Attendees'] : '';

		return trim( (string) $raw );
	}

	/**
	 * Comparison key for counting distinct institutions.
	 *
	 * @param array $row Report row.
	 * @return string Empty when the row names no institution.
	 */
	public static function institution_key( $row ) {
		$raw = isset( $row['Institution Name'] ) ? $row['Institution Name'] : '';

		return strtolower( trim( (string) $raw ) );
	}

	/**
	 * Decode HTML entities the Campus Connect Details report leaves in its
	 * CSV export (e.g. '&amp;' for '&', '&#039;' for an apostrophe).
	 *
	 * Decodes exactly one round: '&amp;amp;' becomes '&amp;', not '&', so a
	 * value that legitimately contains an escaped entity is not corrupted.
	 *
	 * @param string $value Raw value, possibly HTML-entity-encoded.
	 * @return string Decoded value.
	 */
	public static function decode( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Normalise report rows into the public data blob.
	 *
	 * The blob is what gets stored in the wpcct_data option and inlined into
	 * pages, so it carries aggregates and a deliberately narrow per-event
	 * field set - never organiser names or contact details.
	 *
	 * @param array $rows   Report rows keyed by column header.
	 * @param array $coords WordCamp ID => array( lat, lng, site ).
	 * @param array $source Age of the source data, not of this blob. Keys:
	 *                      'synced'  ISO-8601 UTC, newest Synced At across the
	 *                                Airtable rows, so when Central was last
	 *                                read. '' when no row carries one, which
	 *                                means Central has never been read.
	 *                      'created' ISO-8601 UTC, newest Airtable createdTime,
	 *                                so when the rows first appeared. Only a
	 *                                fallback: it does not move when a row is
	 *                                updated.
	 * @return array
	 */
	public static function build( $rows, $coords = array(), $source = array() ) {
		$year = self::filter( 'wpcct_current_year', gmdate( 'Y' ) );

		$totals = array(
			'completed'           => 0,
			'scheduled'           => 0,
			'planning'            => 0,
			'attendees'           => 0,
			'completed_this_year' => 0,
			'countries'           => 0,
			'institutions'        => 0,
		);

		$regions = array();
		foreach ( self::REGIONS as $region ) {
			$regions[ $region ] = array(
				'completed'    => 0,
				'scheduled'    => 0,
				'planning'     => 0,
				'institutions' => 0,
				'attendees'    => 0,
			);
		}

		$pipeline           = array();
		$timeline           = array();
		$markers            = array();
		$events             = array();
		$unmapped           = array();
		$unmapped_statuses  = array();
		$countries_seen     = array();
		$institutions_seen  = array();
		$region_institutions = array();

		foreach ( $rows as $row ) {
			// Central's export HTML-encodes free text. Decode before anything
			// derives a lookup key from these fields - canonical_country(),
			// region_for(), is_denied() and institution_key() all read them,
			// and an entity-bearing value would otherwise fail to match. URL
			// is decoded for the same reason: an '&amp;' left in a query
			// string survives into the rendered href and breaks the link.
			foreach ( array( 'Name', 'Institution Name', 'City', 'Country', 'Number of Anticipated Attendees', 'URL' ) as $field ) {
				if ( isset( $row[ $field ] ) ) {
					$row[ $field ] = self::decode( $row[ $field ] );
				}
			}

			if ( self::is_denied( $row ) ) {
				continue;
			}

			$status = trim( (string) ( isset( $row['Status'] ) ? $row['Status'] : '' ) );
			$bucket = self::status_bucket( $status );

			// The pipeline block shows every raw status, excluded ones included.
			$pipeline[ $status ] = ( isset( $pipeline[ $status ] ) ? $pipeline[ $status ] : 0 ) + 1;

			/* Recorded here, above the excluded check, because an unrecognised
			   status IS excluded by the fallback and would never reach a
			   collector placed after it. Reporting only; the bucket above is
			   already decided and nothing here changes it. 'Cancelled' and
			   'Declined' are known exclusions and so are not reported. */
			if ( ! self::is_known_status( $status ) ) {
				$unmapped_statuses[ $status ] = ( isset( $unmapped_statuses[ $status ] ) ? $unmapped_statuses[ $status ] : 0 ) + 1;
			}

			if ( self::STATUS_EXCLUDED === $bucket ) {
				continue;
			}

			$country = self::canonical_country( isset( $row['Country'] ) ? $row['Country'] : '' );
			$region  = self::region_for( $country );
			$people  = self::attendees( $row );
			$start   = trim( (string) ( isset( $row['Start Date (YYYY-mm-dd)'] ) ? $row['Start Date (YYYY-mm-dd)'] : '' ) );
			$inst    = self::institution_key( $row );

			$totals[ $bucket ]++;
			$totals['attendees'] += $people;

			if ( self::STATUS_COMPLETED === $bucket && 0 === strpos( $start, (string) $year ) ) {
				$totals['completed_this_year']++;
			}

			if ( '' === $region ) {
				$unmapped[ $country ] = ( isset( $unmapped[ $country ] ) ? $unmapped[ $country ] : 0 ) + 1;
			} else {
				/* wpcct_region_map is a documented extension point - the
				   settings screen tells admins to use it - so a filter may
				   legitimately name a region outside self::REGIONS. Give it a
				   complete zeroed row rather than autovivifying a partial one
				   (and emitting undefined-index notices on the ++). */
				if ( ! isset( $regions[ $region ] ) ) {
					$regions[ $region ] = array(
						'completed'    => 0,
						'scheduled'    => 0,
						'planning'     => 0,
						'institutions' => 0,
						'attendees'    => 0,
					);
				}

				$countries_seen[ $country ] = true;
				$regions[ $region ][ $bucket ]++;
				$regions[ $region ]['attendees'] += $people;
				if ( '' !== $inst ) {
					$region_institutions[ $region ][ $inst ] = true;
				}
			}

			if ( '' !== $inst ) {
				$institutions_seen[ $inst ] = true;
			}

			// Timeline buckets by month; the four rows with no start date drop out.
			if ( preg_match( '/^(\d{4}-\d{2})-\d{2}$/', $start, $m ) ) {
				if ( ! isset( $timeline[ $m[1] ] ) ) {
					$timeline[ $m[1] ] = array( 'completed' => 0, 'scheduled' => 0, 'attendees' => 0 );
				}
				if ( isset( $timeline[ $m[1] ][ $bucket ] ) ) {
					$timeline[ $m[1] ][ $bucket ]++;
				}
				$timeline[ $m[1] ]['attendees'] += $people;
			}

			$id    = trim( (string) ( isset( $row['ID'] ) ? $row['ID'] : '' ) );
			$event = array(
				'id'          => $id,
				'name'        => trim( (string) ( isset( $row['Name'] ) ? $row['Name'] : '' ) ),
				'institution' => trim( (string) ( isset( $row['Institution Name'] ) ? $row['Institution Name'] : '' ) ),
				'city'        => trim( (string) ( isset( $row['City'] ) ? $row['City'] : '' ) ),
				'country'     => $country,
				'region'      => $region,
				'status'      => $status,
				'bucket'      => $bucket,
				'start'       => $start,
				'end'         => trim( (string) ( isset( $row['End Date (YYYY-mm-dd)'] ) ? $row['End Date (YYYY-mm-dd)'] : '' ) ),
				'attendees'   => $people,
				'anticipated' => self::anticipated( $row ),
				'url'         => trim( (string) ( isset( $row['URL'] ) ? $row['URL'] : '' ) ),
			);
			$events[] = $event;

			if ( isset( $coords[ $id ] ) ) {
				$markers[] = array(
					'id'          => $id,
					'lat'         => (float) $coords[ $id ]['lat'],
					'lng'         => (float) $coords[ $id ]['lng'],
					'name'        => $event['name'],
					'institution' => $event['institution'],
					'city'        => $event['city'],
					'country'     => $country,
					'start'       => $start,
					'attendees'   => $people,
					'bucket'      => $bucket,
					'url'         => isset( $coords[ $id ]['site'] ) ? $coords[ $id ]['site'] : $event['url'],
				);
			}
		}

		foreach ( $region_institutions as $region => $set ) {
			$regions[ $region ]['institutions'] = count( $set );
		}

		$totals['countries']    = count( $countries_seen );
		$totals['institutions'] = count( $institutions_seen );

		ksort( $timeline );
		arsort( $pipeline );

		usort(
			$events,
			function ( $a, $b ) {
				return strcmp( $b['start'], $a['start'] );
			}
		);

		return array(
			'generated'          => gmdate( 'c' ),

			/* When the UNDERLYING data was last written, which is not the same
			   as 'generated' above. 'generated' is when this blob was built
			   from Airtable; these two are when Airtable itself was last
			   written from Central ('synced') and when its rows first appeared
			   ('created'). They travel in the blob rather than in options of
			   their own precisely because a failed sync keeps the previous
			   blob: a separate option would go on advancing while the data it
			   described stood still. Both are ISO-8601 UTC, or '' when the
			   caller could not establish them. */
			'source_synced'      => isset( $source['synced'] ) ? $source['synced'] : '',
			'source_created'     => isset( $source['created'] ) ? $source['created'] : '',
			'totals'             => $totals,
			'regions'            => $regions,
			'pipeline'           => $pipeline,
			'timeline'           => $timeline,
			'markers'            => $markers,
			'events'             => $events,
			'unmapped_countries' => $unmapped,

			/* Statuses the bucket map does not know. Their events are still
			   counted, in the excluded bucket, exactly as before this existed:
			   the surface reports, it does not re-bucket. Without it a renamed
			   upstream status is a silent shortfall in a headline figure,
			   which is the one failure mode the country report already
			   existed to prevent for regions. */
			'unmapped_statuses'  => $unmapped_statuses,
		);
	}

	/**
	 * Is a built blob plausible enough to publish?
	 *
	 * The sync's other guards count records as they arrive from Airtable, and
	 * so cannot see a field rename: the Events table is addressed by field
	 * name, so renaming one still delivers every record - they simply lose
	 * that column. This is the guard that looks at what came out the other end
	 * rather than at what went in.
	 *
	 * Two checks, each aimed at a rename that empties the report:
	 *
	 * 1. No completed, scheduled or planned event at all. That is what a lost
	 *    'Status' column produces: every row falls to the excluded bucket, and
	 *    a programme with nothing live is a normalisation failure, not news.
	 * 2. Every regional row zero while events are piling up in
	 *    unmapped_countries. That is what a lost 'Country' column produces:
	 *    the totals still look healthy, but the map is empty, the region table
	 *    is all zeroes and every live event is filed as unmappable.
	 *
	 * It is deliberately not a general health check, and it is honest about
	 * its reach: a lost 'Start Date' or 'Actual Attendees' column still
	 * publishes - the events remain real and correctly bucketed, they just
	 * lose their timeline placement or their attendance. Those degrade a
	 * section; they do not empty the report.
	 *
	 * Kept here, rather than inline in the sync, so it can be exercised by the
	 * test harness without WordPress.
	 *
	 * @param array $blob Blob as returned by build().
	 * @return bool True when the blob may safely replace the stored one.
	 */
	public static function is_plausible( array $blob ): bool {
		if ( empty( $blob['totals'] ) || ! is_array( $blob['totals'] ) ) {
			return false;
		}

		$live = 0;
		foreach ( array( 'completed', 'scheduled', 'planning' ) as $bucket ) {
			$live += isset( $blob['totals'][ $bucket ] ) ? (int) $blob['totals'][ $bucket ] : 0;
		}

		if ( $live < 1 ) {
			return false;
		}

		if ( empty( $blob['events'] ) || ! is_array( $blob['events'] ) ) {
			return false;
		}

		$regional = 0;
		if ( ! empty( $blob['regions'] ) && is_array( $blob['regions'] ) ) {
			foreach ( $blob['regions'] as $region ) {
				foreach ( array( 'completed', 'scheduled', 'planning' ) as $bucket ) {
					$regional += isset( $region[ $bucket ] ) ? (int) $region[ $bucket ] : 0;
				}
			}
		}

		$unmapped = ( ! empty( $blob['unmapped_countries'] ) && is_array( $blob['unmapped_countries'] ) )
			? array_sum( $blob['unmapped_countries'] )
			: 0;

		if ( 0 === $regional && $unmapped > 0 ) {
			return false;
		}

		return true;
	}
}
