<?php
/**
 * What the four abilities answer, and the schemas that say so.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The four answers of Claude access, Phase A (1.123.30): counts, institution names, stages and labels, and
 * no person.
 *
 * Each builder reads what the plugin already keeps and the dashboards already show, through the reader the
 * dashboard itself uses, so Claude's number and the screen's cannot differ: the Programs and Sponsors cards,
 * the attention strip and the syncs' health, the Engagement tab's report, the institutions and roster
 * indexes, the sponsors store, the mentor application feed and the published outcome figures. Nothing here
 * calls Airtable, WordPress.org or any other address, and nothing writes. Every answer says when its sources
 * were read, so "as of" is always known.
 *
 * A builder returns what `WPCPM_Abilities::answer()` takes: `answer`, the array the caller gets, and
 * `records`, the record ID of every record-shaped row the answer carries and nothing else (never a name,
 * never a stage). A builder never returns an error: nothing found is an answer with an empty list, and input
 * it cannot read is ignored as if it were absent, since core's schema check has already refused malformed
 * input before the build runs. A reader that throws is `answer()`'s to turn into its one fixed sentence.
 *
 * Three rules hold for every field, and the tests pin each:
 *
 * - **No person.** An institution's contact, the person who accepted its agreement, an account that acts for
 *   it, a student, a mentor, a sponsor's contact and the words of a sync's error never leave: the answers
 *   count them or leave them out, and no code here so much as names a field that holds one.
 * - **Typed text is cleaned.** Every string a person or a sync wrote (an institution's name, a stage, a
 *   country, a city, a track or tier label) goes through `WPCPM_Abilities::text()`, so it is one line of at
 *   most 120 characters without control or zero-width characters.
 * - **Times are ISO 8601 in UTC,** or null when the source has never been read, through
 *   `WPCPM_Abilities::time()`.
 *
 * A map whose keys are data (the tracks by label, the institutions by stage, the sponsors by tier) is built
 * with `map()`, so an empty one is `{}` in the JSON and never `[]`, which a client checking the answer
 * against its object schema would refuse.
 *
 * The schemas live beside the builders they describe, and `WPCPM_Abilities::definitions()` hands them to
 * the Abilities API. An object with fixed keys is closed (`additionalProperties` false) and names every key
 * it can hold; a map says what its values are instead, as its keys are not known until the answer is built.
 */
class WPCPM_Ability_Answers {

	/** The outcome figures an answer may carry, as the homepage shows them. */
	const FIGURES = array( 'graduation', 'recommend', 'keep' );

	/**
	 * The Engagement tab's headline figures, in the order its report lists them. The report's summary is
	 * passed on key for key, each an integer; a key the report grows later is left out until this list and
	 * the schema name it.
	 */
	const ENGAGEMENT_KEYS = array( 'active', 'signed_in', 'seen_30', 'seen_7', 'never', 'acted', 'idle', 'with_students', 'with_students_hours', 'with_students_link', 'with_students_notes', 'with_students_seen_30', 'calls_held', 'calls_upcoming', 'calls_noted', 'sessions_held', 'sessions_upcoming', 'sessions_noted', 'staff' );

	/** The most institutions one lookup returns. */
	const MATCHES_MAX = 5;

	/** The fewest characters of a name a lookup searches for. */
	const NAME_MIN = 3;

	/** The groups a roster puts an institution's students in. */
	const GROUPS = array( 'current', 'waiting', 'finished', 'not_started' );

	/*
	 * --------------------------------------------------------------------
	 * The builders
	 * --------------------------------------------------------------------
	 */

	/**
	 * `wpcpm/program-counts`: the program's figures for the current semester, as the Administrator Dashboard
	 * counts them.
	 *
	 * The students per track are the Programs card's own numbers, by the track's label; the sponsors are the
	 * Sponsors card's, with how many of the approved are test sponsors; the institutions per stage count the
	 * index's rows. It names no record, so its log row counts none.
	 *
	 * @return array `answer` and `records` (empty).
	 */
	public static function program_counts() {
		$programs = WPCPM_Administrators_Cards::programs();
		$labels   = WPCPM_Administrators_Cards::tracks();
		$mentors  = WPCPM_Mentor_Engagement::report( time() )['summary'];
		$sponsors = WPCPM_Administrators_Cards::sponsors();
		$today    = WPCPM_Mentor_Application_Feed::build()['today'];
		$figures  = WPCPM_Outcome_Figures::published();
		$index    = WPCPM_Institutions_Index::read();

		// A track is named by its label. Two tracks that read the same after cleaning are one key, their
		// numbers added, so an answer never holds a key twice.
		$tracks = array();

		foreach ( (array) $programs['tracks'] as $key => $counts ) {
			$label = WPCPM_Abilities::text( isset( $labels[ $key ] ) ? (string) $labels[ $key ] : (string) $key );

			if ( ! isset( $tracks[ $label ] ) ) {
				$tracks[ $label ] = array(
					'in_progress' => 0,
					'signed_up'   => 0,
				);
			}

			$tracks[ $label ]['in_progress'] += (int) $counts['in_progress'];
			$tracks[ $label ]['signed_up']   += (int) $counts['signed_up'];
		}

		$stages = array();

		foreach ( WPCPM_Institutions_Index::by_stage() as $stage => $rows ) {
			$label            = self::stage( $stage );
			$stages[ $label ] = ( isset( $stages[ $label ] ) ? $stages[ $label ] : 0 ) + count( $rows );
		}

		$test = 0;

		foreach ( WPCPM_Sponsors_Index::rows() as $row ) {
			if ( is_array( $row ) && isset( $row['status'] ) && WPCPM_Sponsors_Index::STATUS_APPROVED === $row['status'] && WPCPM_Sponsors_Index::is_test( $row ) ) {
				++$test;
			}
		}

		$tiers = array();

		if ( isset( $sponsors['tiers'] ) && is_array( $sponsors['tiers'] ) ) {
			foreach ( $sponsors['tiers'] as $tier => $count ) {
				$label           = WPCPM_Abilities::text( (string) $tier );
				$tiers[ $label ] = ( isset( $tiers[ $label ] ) ? $tiers[ $label ] : 0 ) + (int) $count;
			}
		}

		// The figures the homepage may show: none at all when no figure may be shown, else those that may.
		$outcomes = array( 'read_at' => WPCPM_Abilities::time( isset( $figures['read'] ) ? $figures['read'] : 0 ) );

		foreach ( self::FIGURES as $name ) {
			if ( isset( $figures['figures'][ $name ] ) ) {
				$outcomes[ $name ] = array(
					'share' => (int) $figures['figures'][ $name ]['share'],
					'count' => (int) $figures['figures'][ $name ]['count'],
					'n'     => (int) $figures['figures'][ $name ]['n'],
				);
			}
		}

		return array(
			'answer'  => array(
				'semester'            => WPCPM_Abilities::text( $programs['semester'] ),
				'students'            => array(
					'tracks'                     => self::map( $tracks ),
					'finished_this_semester'     => (int) $programs['finished'],
					'institutions_with_students' => count( (array) $programs['rows'] ),
					'read_at'                    => WPCPM_Abilities::time( $programs['read'] ),
				),
				'mentors'             => array(
					'active' => (int) $mentors['active'],
					'staff'  => (int) $mentors['staff'],
				),
				'institutions'        => array(
					'by_stage' => self::map( $stages ),
					'read_at'  => WPCPM_Abilities::time( $index['read'] ),
				),
				'sponsors'            => array(
					'approved'             => (int) $sponsors['approved'],
					'test_approved'        => $test,
					'with_accounts'        => (int) $sponsors['with_accounts'],
					'live_offers'          => (int) $sponsors['live_offers'],
					'claims_this_semester' => (int) $sponsors['claims_semester'],
					'tiers'                => self::map( $tiers ),
				),
				'mentor_applications' => array(
					'open'             => (int) $today['open'],
					'selected_waiting' => (int) $today['selected'],
				),
				'outcome_figures'     => $outcomes,
			),
			'records' => array(),
		);
	}

	/**
	 * `wpcpm/waiting-for-decision`: what the Administrator Dashboard's attention strip lists, and when each
	 * sync last ran and runs next.
	 *
	 * The tiles are `counts( collect() )`, the call the Overview screen makes, so Claude's number and the
	 * strip's cannot differ. A tile's `open` is the address its tile opens: its own, or the dashboard page at
	 * the card the tile points to, and empty when there is no published page to open it on. A sync is its key,
	 * its label, its two times and whether it needs a look, and nothing of its progress: the words of an
	 * error are the site's.
	 *
	 * Whether a sync needs a look is the Syncs and health card's own answer, asked of the same entry of
	 * `health()` with the switch `health()` read (Automatic sync, on when it did not say, as the card reads
	 * it), so the number of syncs that say so here is the number on the card. The outcome figures' weekly
	 * read has a rule of its own on the card, and is asked by that rule instead.
	 *
	 * @return array `answer` and `records` (empty).
	 */
	public static function waiting() {
		$tiles  = WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
		$health = WPCPM_Administrators_Cards::health();
		$page   = (string) WPCPM_Administrators_Dashboard::page_url();
		$auto   = ! isset( $health['auto_sync'] ) || ! empty( $health['auto_sync'] );
		$list   = array();
		$syncs  = array();

		foreach ( $tiles as $key => $tile ) {
			if ( ! empty( $tile['url'] ) ) {
				$open = (string) $tile['url'];
			} elseif ( '' !== $page && ! empty( $tile['card'] ) ) {
				$open = $page . '#wpcpm-' . $tile['card'];
			} else {
				$open = '';
			}

			$list[] = array(
				'key'   => (string) $key,
				'label' => WPCPM_Abilities::text( $tile['label'] ),
				'count' => (int) $tile['n'],
				'shown' => isset( $tile['shown'] ) ? (string) $tile['shown'] : (string) (int) $tile['n'],
				'open'  => $open,
			);
		}

		foreach ( (array) $health['syncs'] as $key => $sync ) {
			if ( ! is_array( $sync ) ) {
				continue;
			}

			$syncs[] = array(
				'key'        => (string) $key,
				'label'      => WPCPM_Abilities::text( isset( $sync['label'] ) ? $sync['label'] : '' ),
				'last_run'   => WPCPM_Abilities::time( isset( $sync['last'] ) ? $sync['last'] : 0 ),
				'next_run'   => WPCPM_Abilities::time( isset( $sync['next'] ) ? $sync['next'] : 0 ),
				'needs_look' => 'outcome_figures' === $key ? WPCPM_Administrators_Cards::figures_need_look() : WPCPM_Administrators_Cards::sync_needs_look( $sync, $auto ),
			);
		}

		return array(
			'answer'  => array(
				'tiles'           => $list,
				'nothing_waiting' => array() === $list,
				'syncs'           => $syncs,
			),
			'records' => array(),
		);
	}

	/**
	 * `wpcpm/mentor-engagement-summary`: the Engagement tab's headline figures as of a day.
	 *
	 * A day counts as of its last second, 23:59:59 UTC, and never as of a time that has not come: today and
	 * any day after it count as of now, as does a value that is no date (the schema has refused one before the
	 * build runs; a caller that gets here with one is answered as if it had left the date out). The answer
	 * says the time it used.
	 *
	 * @param mixed $input `as_of`, a `Y-m-d` date, or nothing.
	 * @return array `answer` and `records` (empty).
	 */
	public static function engagement( $input = null ) {
		$now  = time();
		$used = $now;

		if ( is_array( $input ) && isset( $input['as_of'] ) && is_string( $input['as_of'] ) && 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $input['as_of'], $day ) && checkdate( (int) $day[2], (int) $day[3], (int) $day[1] ) ) {
			$end = gmmktime( 23, 59, 59, (int) $day[2], (int) $day[3], (int) $day[1] );

			// A day before the Unix epoch has no time to count as of, and the report takes a time of zero
			// or less for now, so it is left out like any other date that cannot be used.
			if ( $end > 0 ) {
				$used = min( $end, $now );
			}
		}

		$report  = WPCPM_Mentor_Engagement::report( $used );
		$summary = array();

		foreach ( self::ENGAGEMENT_KEYS as $key ) {
			$summary[ $key ] = isset( $report['summary'][ $key ] ) ? (int) $report['summary'][ $key ] : 0;
		}

		return array(
			'answer'  => array(
				'as_of'   => WPCPM_Abilities::time( $used ),
				'summary' => $summary,
			),
			'records' => array(),
		);
	}

	/**
	 * `wpcpm/institution-summary`: one institution's pipeline stage, Collaboration Agreement, member count
	 * and students per group.
	 *
	 * Found by its record ID, or by three or more characters of its name (case-insensitive, in the name as
	 * it is shown, cleaned). At most five come back, by name A to Z, and `more` says there were others. An
	 * institution is an organization, so its contact, the person who accepted its agreement and the accounts
	 * that act for it are left out: the members are counted and never named. The students are counted per
	 * group, for the semester asked for or for every semester. The records of the matches go to the log.
	 * The answer says when each of its two sources was read: `read_at` is the roster's (the students per
	 * group), `institutions_read_at` the institutions index's (the names, stages, places and agreements).
	 *
	 * @param mixed $input `institution` (a record ID or part of a name) and `cohort` (`YYYY-H1` or
	 *                     `YYYY-H2`, optional).
	 * @return array `answer` and `records` (the record IDs of the matches).
	 */
	public static function institutions( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$needle = isset( $input['institution'] ) ? WPCPM_Abilities::text( $input['institution'] ) : '';
		$cohort = '';

		if ( isset( $input['cohort'] ) && WPCPM_Cohort::is_key( $input['cohort'] ) && WPCPM_Cohort::NONE !== $input['cohort'] ) {
			$cohort = $input['cohort'];
		}

		$found = array();

		if ( WPCPM_Mentors_Sync::is_record_id( $needle ) ) {
			$row = WPCPM_Institutions_Index::row( $needle );

			if ( is_array( $row ) ) {
				$found[] = array(
					'id'   => $needle,
					'name' => WPCPM_Abilities::text( isset( $row['name'] ) ? $row['name'] : '' ),
					'row'  => $row,
				);
			}
		} elseif ( mb_strlen( $needle, 'UTF-8' ) >= self::NAME_MIN ) {
			foreach ( WPCPM_Institutions_Index::rows() as $id => $row ) {
				$name = WPCPM_Abilities::text( is_array( $row ) && isset( $row['name'] ) ? $row['name'] : '' );

				if ( is_array( $row ) && false !== mb_stripos( $name, $needle, 0, 'UTF-8' ) ) {
					$found[] = array(
						'id'   => (string) $id,
						'name' => $name,
						'row'  => $row,
					);
				}
			}
		}

		usort(
			$found,
			static function ( $a, $b ) {
				$order = strcmp( mb_strtolower( $a['name'], 'UTF-8' ), mb_strtolower( $b['name'], 'UTF-8' ) );

				return 0 !== $order ? $order : strcmp( $a['id'], $b['id'] );
			}
		);

		$more    = count( $found ) > self::MATCHES_MAX;
		$found   = array_slice( $found, 0, self::MATCHES_MAX );
		$matches = array();
		$records = array();

		foreach ( $found as $match ) {
			$row       = $match['row'];
			$agreement = isset( $row['agreement'] ) && is_array( $row['agreement'] ) ? $row['agreement'] : array();
			$groups    = WPCPM_Roster_Index::groups( $match['id'], $cohort );
			$students  = array();

			foreach ( self::GROUPS as $group ) {
				$students[ $group ] = isset( $groups[ $group ] ) ? count( (array) $groups[ $group ] ) : 0;
			}

			$students['cohort'] = '' !== $cohort ? $cohort : null;

			// The country's name when the base has one, else the country as the record holds it.
			$country = WPCPM_Abilities::text( isset( $row['country_name'] ) ? $row['country_name'] : '' );

			if ( '' === $country ) {
				$country = WPCPM_Abilities::text( isset( $row['country'] ) ? $row['country'] : '' );
			}

			$matches[] = array(
				'record_id' => $match['id'],
				'name'      => $match['name'],
				'stage'     => self::stage( isset( $row['stage'] ) ? $row['stage'] : '' ),
				'country'   => $country,
				'city'      => WPCPM_Abilities::text( isset( $row['city'] ) ? $row['city'] : '' ),
				'agreement' => array(
					'status'       => WPCPM_Abilities::text( isset( $agreement['status'] ) ? $agreement['status'] : '' ),
					'kind'         => WPCPM_Abilities::text( isset( $agreement['kind'] ) ? $agreement['kind'] : '' ),
					'submitted_on' => WPCPM_Abilities::text( isset( $agreement['submitted_on'] ) ? $agreement['submitted_on'] : '' ),
					'accepted_on'  => WPCPM_Abilities::text( isset( $agreement['accepted_on'] ) ? $agreement['accepted_on'] : '' ),
					'signed_on'    => WPCPM_Abilities::text( isset( $agreement['signed_on'] ) ? $agreement['signed_on'] : '' ),
					'has_document' => ! empty( $agreement['has_document'] ),
				),
				'members'   => count( (array) WPCPM_Institution_Members::members_of( $match['id'] ) ),
				'students'  => $students,
			);

			$records[] = $match['id'];
		}

		return array(
			'answer'  => array(
				'matches'              => $matches,
				'more'                 => $more,
				'read_at'              => WPCPM_Abilities::time( WPCPM_Roster_Index::counts()['read'] ),
				'institutions_read_at' => WPCPM_Abilities::time( WPCPM_Institutions_Index::read()['read'] ),
			),
			'records' => $records,
		);
	}

	/**
	 * A pipeline stage as an answer names it: cleaned, and the stage a record with none is counted in.
	 *
	 * @param mixed $stage A stage as the index holds it.
	 * @return string
	 */
	private static function stage( $stage ) {
		$stage = WPCPM_Abilities::text( $stage );

		return '' !== $stage ? $stage : __( 'No stage', 'wpcredits-program-manager' );
	}

	/**
	 * A map whose keys are data, as an object: `{}` in the JSON when it is empty, where an empty array would
	 * be `[]`, and the same shape whatever its keys are (a key such as "0" would make a list of it).
	 *
	 * @param array $values Key => value.
	 * @return object
	 */
	private static function map( array $values ) {
		return (object) $values;
	}

	/*
	 * --------------------------------------------------------------------
	 * The schemas
	 * --------------------------------------------------------------------
	 */

	/**
	 * What an ability takes, for `wp_register_ability()`'s `input_schema`.
	 *
	 * Two abilities take nothing and declare nothing. The engagement summary's input is optional, so its
	 * schema says `default` is an empty list, which core hands to a call that gives no input at all.
	 * `as_of` is checked by its pattern as well as by its format, as core does not check the format `date`.
	 *
	 * @param string $method The builder's name: `program_counts`, `waiting`, `engagement` or `institutions`.
	 * @return array|null The schema, or null for an ability that takes no input.
	 */
	public static function input_schema( $method ) {
		if ( 'engagement' === $method ) {
			return array(
				'type'                 => 'object',
				'properties'           => array(
					'as_of' => array(
						'type'        => 'string',
						'format'      => 'date',
						'pattern'     => '^\d{4}-\d{2}-\d{2}$',
						'description' => __( 'The day to count as of, as Y-m-d. A past day counts as of the end of that day in UTC; today and any day to come count as of now. Leave it out to count as of now.', 'wpcredits-program-manager' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			);
		}

		if ( 'institutions' === $method ) {
			return array(
				'type'                 => 'object',
				'properties'           => array(
					'institution' => array(
						'type'        => 'string',
						'minLength'   => self::NAME_MIN,
						'maxLength'   => WPCPM_Abilities::TEXT_MAX,
						'description' => __( 'An institution\'s record ID, or three or more characters of its name. At most five institutions are returned.', 'wpcredits-program-manager' ),
					),
					'cohort'      => array(
						'type'        => 'string',
						'pattern'     => '^\d{4}-H[12]$',
						'description' => __( 'A semester, YYYY-H1 for January to June or YYYY-H2 for July to December. Leave it out to count every semester.', 'wpcredits-program-manager' ),
					),
				),
				'required'             => array( 'institution' ),
				'additionalProperties' => false,
			);
		}

		return null;
	}

	/**
	 * What an ability answers, for `wp_register_ability()`'s `output_schema`: every key the answer can hold.
	 *
	 * An object with fixed keys is closed. The three maps whose keys are data say what their values are
	 * instead. A key that is only there while it has something to say (an outcome figure that may not be
	 * shown) is a property that is not required. `WPCPM_Abilities::definitions()` adds the schema's
	 * description, the sentence that tells the reader a name in an answer is data.
	 *
	 * @param string $method The builder's name.
	 * @return array The schema.
	 */
	public static function output_schema( $method ) {
		switch ( $method ) {
			case 'program_counts':
				return self::program_counts_schema();

			case 'waiting':
				return self::waiting_schema();

			case 'engagement':
				return self::engagement_schema();

			case 'institutions':
				return self::institutions_schema();
		}

		return array();
	}

	/**
	 * The schema of `program_counts()`.
	 *
	 * @return array
	 */
	private static function program_counts_schema() {
		$figure = self::object(
			array(
				'share' => self::integer( __( 'The share, as a whole percent.', 'wpcredits-program-manager' ) ),
				'count' => self::integer( __( 'How many, the share\'s numerator.', 'wpcredits-program-manager' ) ),
				'n'     => self::integer( __( 'Out of how many, the share\'s denominator.', 'wpcredits-program-manager' ) ),
			)
		);

		return self::object(
			array(
				'semester'            => self::text_of( __( 'The current semester: YYYY-H1 for January to June, YYYY-H2 for July to December.', 'wpcredits-program-manager' ) ),
				'students'            => self::object(
					array(
						'tracks'                     => self::keyed(
							self::object(
								array(
									'in_progress' => self::integer( __( 'Students in progress on this track now.', 'wpcredits-program-manager' ) ),
									'signed_up'   => self::integer( __( 'Students who signed up on this track this semester.', 'wpcredits-program-manager' ) ),
								)
							),
							__( 'Per track, by the track\'s label.', 'wpcredits-program-manager' )
						),
						'finished_this_semester'     => self::integer( __( 'Students who graduated this semester, across every track.', 'wpcredits-program-manager' ) ),
						'institutions_with_students' => self::integer( __( 'Institutions with at least one student in progress.', 'wpcredits-program-manager' ) ),
						'read_at'                    => self::when( __( 'When the roster was last read, ISO 8601 in UTC; null when it never was.', 'wpcredits-program-manager' ) ),
					)
				),
				'mentors'             => self::object(
					array(
						'active' => self::integer( __( 'Active mentors, staff not counted.', 'wpcredits-program-manager' ) ),
						'staff'  => self::integer( __( 'Active mentors who are program staff.', 'wpcredits-program-manager' ) ),
					)
				),
				'institutions'        => self::object(
					array(
						'by_stage' => self::keyed( self::integer( __( 'Institutions at this pipeline stage.', 'wpcredits-program-manager' ) ), __( 'Institutions per pipeline stage; "No stage" counts those with none.', 'wpcredits-program-manager' ) ),
						'read_at'  => self::when( __( 'When the institutions were last read, ISO 8601 in UTC; null when they never were.', 'wpcredits-program-manager' ) ),
					)
				),
				'sponsors'            => self::object(
					array(
						'approved'             => self::integer( __( 'Approved sponsors, test sponsors included.', 'wpcredits-program-manager' ) ),
						'test_approved'        => self::integer( __( 'Of the approved sponsors, how many are test sponsors.', 'wpcredits-program-manager' ) ),
						'with_accounts'        => self::integer( __( 'Approved sponsors that hold an account.', 'wpcredits-program-manager' ) ),
						'live_offers'          => self::integer( __( 'Offers live today.', 'wpcredits-program-manager' ) ),
						'claims_this_semester' => self::integer( __( 'Claims made since this semester began.', 'wpcredits-program-manager' ) ),
						'tiers'                => self::keyed( self::integer( __( 'Approved sponsors in this tier.', 'wpcredits-program-manager' ) ), __( 'Approved sponsors per tier.', 'wpcredits-program-manager' ) ),
					)
				),
				'mentor_applications' => self::object(
					array(
						'open'             => self::integer( __( 'Mentor applications waiting for review.', 'wpcredits-program-manager' ) ),
						'selected_waiting' => self::integer( __( 'Mentor applications selected and waiting for the course.', 'wpcredits-program-manager' ) ),
					)
				),
				'outcome_figures'     => self::object(
					array(
						'read_at'    => self::when( __( 'When the outcome figures were last read, ISO 8601 in UTC; null when no figure may be shown.', 'wpcredits-program-manager' ) ),
						'graduation' => $figure,
						'recommend'  => $figure,
						'keep'       => $figure,
					),
					array( 'read_at' ),
					__( 'Only the figures that may be shown are there.', 'wpcredits-program-manager' )
				),
			)
		);
	}

	/**
	 * The schema of `waiting()`.
	 *
	 * @return array
	 */
	private static function waiting_schema() {
		return self::object(
			array(
				'tiles'           => array(
					'type'        => 'array',
					'description' => __( 'What waits for a decision, in the order the dashboard lists it. Empty when nothing waits.', 'wpcredits-program-manager' ),
					'items'       => self::object(
						array(
							'key'   => self::text_of( __( 'The tile\'s name.', 'wpcredits-program-manager' ) ),
							'label' => self::text_of( __( 'What the tile counts.', 'wpcredits-program-manager' ) ),
							'count' => self::integer( __( 'How many wait. Past the screen\'s ceiling this is a lower bound.', 'wpcredits-program-manager' ) ),
							'shown' => self::text_of( __( 'The number as the dashboard prints it, for example 25+.', 'wpcredits-program-manager' ) ),
							'open'  => self::text_of( __( 'The address that opens it; empty when there is no published dashboard page to open it on.', 'wpcredits-program-manager' ) ),
						)
					),
				),
				'nothing_waiting' => array(
					'type'        => 'boolean',
					'description' => __( 'True when no tile is listed.', 'wpcredits-program-manager' ),
				),
				'syncs'           => array(
					'type'        => 'array',
					'description' => __( 'Each sync, with when it last ran and runs next.', 'wpcredits-program-manager' ),
					'items'       => self::object(
						array(
							'key'        => self::text_of( __( 'The sync\'s name.', 'wpcredits-program-manager' ) ),
							'label'      => self::text_of( __( 'The sync, as the dashboard names it.', 'wpcredits-program-manager' ) ),
							'last_run'   => self::when( __( 'When it last ran, ISO 8601 in UTC; null when it never has.', 'wpcredits-program-manager' ) ),
							'next_run'   => self::when( __( 'When it runs next, ISO 8601 in UTC; null when none is booked.', 'wpcredits-program-manager' ) ),
							'needs_look' => array(
								'type'        => 'boolean',
								'description' => __( 'Whether the dashboard counts it among the syncs that need a look: its last run left an error, or, while Automatic sync is on, none finished in the last six hours. The outcome figures\' weekly read needs a look when it was held or failed or has stopped.', 'wpcredits-program-manager' ),
							),
						)
					),
				),
			)
		);
	}

	/**
	 * The schema of `engagement()`.
	 *
	 * @return array
	 */
	private static function engagement_schema() {
		$summary = array();

		foreach ( self::ENGAGEMENT_KEYS as $key ) {
			$summary[ $key ] = self::integer();
		}

		return self::object(
			array(
				'as_of'   => array(
					'type'        => 'string',
					'format'      => 'date-time',
					'description' => __( 'The time the figures were counted as of, ISO 8601 in UTC.', 'wpcredits-program-manager' ),
				),
				'summary' => self::object( $summary, null, __( 'The Engagement tab\'s figures: active mentors, how many signed in, were seen in 30 and in 7 days, never signed in, acted or stayed idle, those with students and what they set up, the calls and group sessions held, upcoming and noted, and the staff.', 'wpcredits-program-manager' ) ),
			)
		);
	}

	/**
	 * The schema of `institutions()`.
	 *
	 * @return array
	 */
	private static function institutions_schema() {
		$students = array();

		foreach ( self::GROUPS as $group ) {
			$students[ $group ] = self::integer();
		}

		$students['cohort'] = array(
			'type'        => array( 'string', 'null' ),
			'description' => __( 'The semester the counts are for; null for every semester.', 'wpcredits-program-manager' ),
		);

		return self::object(
			array(
				'matches'              => array(
					'type'        => 'array',
					'description' => __( 'The institutions found, by name A to Z; at most five. Empty when none was found.', 'wpcredits-program-manager' ),
					'items'       => self::object(
						array(
							'record_id' => self::text_of( __( 'The institution\'s record ID.', 'wpcredits-program-manager' ) ),
							'name'      => self::text_of( __( 'The institution\'s name.', 'wpcredits-program-manager' ) ),
							'stage'     => self::text_of( __( 'Its pipeline stage; "No stage" when it has none.', 'wpcredits-program-manager' ) ),
							'country'   => self::text_of( __( 'Its country\'s name; empty when none is recorded.', 'wpcredits-program-manager' ) ),
							'city'      => self::text_of( __( 'Its city; empty when none is recorded.', 'wpcredits-program-manager' ) ),
							'agreement' => self::object(
								array(
									'status'       => self::text_of( __( 'The Collaboration Agreement\'s status; empty when there is none.', 'wpcredits-program-manager' ) ),
									'kind'         => self::text_of( __( 'The kind of agreement; empty when there is none.', 'wpcredits-program-manager' ) ),
									'submitted_on' => self::text_of( __( 'The day it was submitted, as Y-m-d; empty when it was not.', 'wpcredits-program-manager' ) ),
									'accepted_on'  => self::text_of( __( 'The day the program accepted it, as Y-m-d; empty when it has not.', 'wpcredits-program-manager' ) ),
									'signed_on'    => self::text_of( __( 'The day it was signed, as Y-m-d; empty when it was not.', 'wpcredits-program-manager' ) ),
									'has_document' => array(
										'type'        => 'boolean',
										'description' => __( 'Whether a signed document is on file.', 'wpcredits-program-manager' ),
									),
								)
							),
							'members'   => self::integer( __( 'How many accounts act for the institution. None is named.', 'wpcredits-program-manager' ) ),
							'students'  => self::object( $students, null, __( 'Its students per group, for the semester asked for or for every semester.', 'wpcredits-program-manager' ) ),
						)
					),
				),
				'more'                 => array(
					'type'        => 'boolean',
					'description' => __( 'True when more institutions matched than are listed.', 'wpcredits-program-manager' ),
				),
				'read_at'              => self::when( __( 'When the roster, where the students per group come from, was last read, ISO 8601 in UTC; null when it never was.', 'wpcredits-program-manager' ) ),
				'institutions_read_at' => self::when( __( 'When the institutions, where the names, stages, places and agreements come from, were last read, ISO 8601 in UTC; null when they never were.', 'wpcredits-program-manager' ) ),
			)
		);
	}

	/**
	 * An object with fixed keys: closed, every key named, and, unless told otherwise, every key required.
	 *
	 * @param array      $properties  Key => schema.
	 * @param array|null $required    The keys always there; null for all of them.
	 * @param string     $description What the object is.
	 * @return array
	 */
	private static function object( array $properties, $required = null, $description = '' ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => null === $required ? array_keys( $properties ) : $required,
			'additionalProperties' => false,
		);

		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		return $schema;
	}

	/**
	 * A map whose keys are data: it names no keys and says what each value is.
	 *
	 * @param array  $values      The schema of every value.
	 * @param string $description What the map is.
	 * @return array
	 */
	private static function keyed( array $values, $description ) {
		return array(
			'type'                 => 'object',
			'description'          => $description,
			'additionalProperties' => $values,
		);
	}

	/**
	 * A whole number.
	 *
	 * @param string $description What it counts; '' for none.
	 * @return array
	 */
	private static function integer( $description = '' ) {
		return self::described( array( 'type' => 'integer' ), $description );
	}

	/**
	 * A string.
	 *
	 * @param string $description What it is.
	 * @return array
	 */
	private static function text_of( $description ) {
		return self::described( array( 'type' => 'string' ), $description );
	}

	/**
	 * A schema with its description, when it has one.
	 *
	 * @param array  $schema      The schema.
	 * @param string $description What it is; '' for none.
	 * @return array
	 */
	private static function described( array $schema, $description ) {
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		return $schema;
	}

	/**
	 * A time: ISO 8601 in UTC, or null when the source has never been read.
	 *
	 * @param string $description What was read, and that null means never.
	 * @return array
	 */
	private static function when( $description ) {
		return self::described(
			array(
				'type'   => array( 'string', 'null' ),
				'format' => 'date-time',
			),
			$description
		);
	}
}
