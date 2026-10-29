<?php
/**
 * What the abilities answer, and the schemas that say so.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The answers of Claude access: Phase A's four (1.123.30), counts, institution names, stages and labels and
 * no person; and Phase B's lookups of people, which point to a person by record ID and never by name.
 *
 * Each builder reads what the plugin already keeps and the dashboards already show, through the reader the
 * dashboard itself uses, so Claude's number and the screen's cannot differ: the Programs and Sponsors cards,
 * the attention strip and the syncs' health, the Engagement tab's report, the institutions and roster
 * indexes, the sponsors store, the mentor application feed, the published outcome figures and the cards the
 * students sync keeps on the students' accounts. Nothing here calls Airtable, WordPress.org or any other
 * address, and nothing writes. Every answer says when its sources were read, so "as of" is always known.
 *
 * A builder returns what `WPCPM_Abilities::answer()` takes: `answer`, the array the caller gets, and
 * `records`, the record ID of every record-shaped row the answer carries and nothing else (never a name,
 * never a stage). A builder never returns an error: nothing found is an answer with an empty list, and input
 * it cannot read is ignored as if it were absent, since core's schema check has already refused malformed
 * input before the build runs. A reader that throws is `answer()`'s to turn into its one fixed sentence.
 *
 * A lookup of the mentor applications (`applications()`) points to an applicant by the application's
 * reference and gives five of the closed answers the form keeps (country, the teacher question, languages,
 * contribution areas, hours a week), the day received, the state and the days in it, whether the address is
 * confirmed and the duplicate and held flags. The applicant's name, address and profile, the free-text answer,
 * the history's notes, the checks' signals and the consent evidence never leave: the application's answers
 * and signals are loaded whole, as their accessors give them, and the builder takes five answers and one
 * signal from them by key.
 *
 * Three rules hold for every field, and the tests pin each:
 *
 * - **No person.** An institution's contact, the person who accepted its agreement, an account that acts for
 *   it, a student, a mentor, a sponsor's contact and the words of a sync's error never leave: the answers
 *   count them, leave them out or, in a lookup of people, give their record IDs (the product owner's
 *   decision of 25 October 2026). No code here so much as names a field that holds a person, but one
 *   method, `people_matching()`, which reads the address key and the username of a roster row to compare
 *   them with what was asked and gives back record IDs. A lookup of a mentor (`mentor()`) hands what was
 *   asked to the command's own lookup of an account and reads of the account its Mentors record ID, its
 *   list of students and when it was written, of each student the record ID, status, hours, start and
 *   whether past, and of the mentor's row of the Engagement report its flags and two times.
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

	/** The most characters a lookup of a student takes: room for a WordPress.org profile address. */
	const WHO_MAX = 200;

	/** The most choices of one pick (the languages, the contribution areas) an application's row lists. */
	const PICKED_MAX = 50;

	/** The groups a roster puts an institution's students in. */
	const GROUPS = array( 'current', 'waiting', 'finished', 'not_started' );

	/**
	 * The stage an institution with none is named by and counted under. It is data, a key of the answer
	 * as the stages typed into the records are, so it is not translated: the answer reads the same whatever
	 * language the account that asks reads the site in.
	 */
	const NO_STAGE = 'No stage';

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

				if ( is_array( $row ) && false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $name, $needle, 0, 'UTF-8' ) : stripos( $name, $needle ) ) ) {
					$found[] = array(
						'id'   => (string) $id,
						'name' => $name,
						'row'  => $row,
					);
				}
			}
		}

		// By name as the dashboards order names, accents and case aside, then by record ID.
		usort(
			$found,
			static function ( $a, $b ) {
				$order = WPCPM_Dashboards::compare_names( $a['name'], $b['name'] );

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
	 * `wpcpm/find-student`: every track a student holds, each by its Students record ID, and the student by
	 * none of their names.
	 *
	 * `who` is read by its shape. An email address (no space, one at sign) finds the rows whose address key
	 * it is, in any case; a Students record ID finds that row. Anything else is searched for in the rows'
	 * names, three or more characters of it, case-insensitive, as `institutions()` searches; and when it has
	 * no space in it, it is read as a WordPress.org username or profile address too, and finds the rows
	 * whose username it is. A person is the rows that share one address key, and a row with none is a
	 * person of its own: a row found brings its person, with every track they hold among the rows looked at.
	 *
	 * The rows looked at are every roster's (the institutions `WPCPM_Roster_Index::counts()` names, the
	 * list `WPCPM_Institution_Roster` walks too) and the rows that name no institution. `institution`
	 * narrows them to one roster, by its record ID, or to the rosters of every institution whose name holds
	 * three or more characters of it; an institution that is no text is ignored as if absent, as any input
	 * the schema has already refused. A `Duplicated` or `SPAM` row (`WPCPM_Roster_Index::NEVER_SHOWN`) is
	 * never looked at: it is no track, and finds and brings nobody.
	 *
	 * At most `PEOPLE_MAX` people come back, and `more` says there were others: those found by address,
	 * username, profile address or record ID first, then those found by name; each by their most recent
	 * start, the latest first, a person with no start after the rest; then by record ID. A person's tracks
	 * are in start order, the earliest first and a track with no start last.
	 *
	 * Nothing of a person goes out: not a name, an address, a username, a profile address, a tutor's or a
	 * mentor's name or a website. A track is its record ID and what the program records of it. The mentor
	 * is the Mentors record ID on the card the students sync keeps on the student's account (where the
	 * dashboards read a student's mentor too), given on the one track that mentor belongs to and on no
	 * other (`mentor_tracks()`), judged over every track the person holds, those an institution hides from
	 * the answer too. The course a student waiting to graduate took is the one the screens name,
	 * through `WPCPM_Program::course_status()`. An institution is an organization, so its record ID and
	 * name go out, as in `institutions()`. The fields that hold a person are read in one method,
	 * `people_matching()`, only to compare them with what was asked, and it gives back record IDs. The
	 * records of the tracks go to the log, and the log keeps the input, which is the Administrator's own
	 * words for whom they looked up.
	 *
	 * @param mixed $input `who` (an email address, a WordPress.org username or profile address, a Students
	 *                     record ID or part of a name) and `institution` (a record ID or part of a name,
	 *                     optional).
	 * @return array `answer` and `records` (the record IDs of the tracks listed).
	 */
	public static function students( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$counts  = WPCPM_Roster_Index::counts();
		$rosters = array_keys( (array) $counts['institutions'] );
		$narrow  = isset( $input['institution'] ) ? $input['institution'] : null;
		$pool    = self::roster_pool( $rosters, $narrow );

		// A person's tracks an institution hides still say which track their mentor belongs to, so with an
		// institution asked for, the people found are looked up again among every roster's rows.
		$everyone = '' === WPCPM_Abilities::text( $narrow ) ? null : static function () use ( $rosters ) {
			return self::roster_pool( $rosters, null );
		};

		$people = self::people_matching( $pool, isset( $input['who'] ) && is_string( $input['who'] ) ? $input['who'] : '', $everyone );

		// The order is settled on the rows alone, so only the people listed have their tracks built and
		// their accounts read.
		foreach ( $people as $n => $person ) {
			$starts = array();

			foreach ( $person['ids'] as $record ) {
				$start = self::day( isset( $pool[ $record ]['row']['start'] ) ? $pool[ $record ]['row']['start'] : '' );

				if ( null !== $start ) {
					$starts[] = $start;
				}
			}

			$people[ $n ]['latest'] = array() === $starts ? '' : max( $starts );
			$people[ $n ]['first']  = min( $person['ids'] );
		}

		// Found by address, username, profile address or record ID first; then the most recent start, the
		// latest first and a person with no start last; then by record ID.
		usort(
			$people,
			static function ( $a, $b ) {
				if ( $a['exact'] !== $b['exact'] ) {
					return $a['exact'] ? -1 : 1;
				}

				$order = strcmp( $b['latest'], $a['latest'] );

				return 0 !== $order ? $order : strcmp( $a['first'], $b['first'] );
			}
		);

		$more    = count( $people ) > WPCPM_Abilities::PEOPLE_MAX;
		$listed  = array();
		$records = array();

		foreach ( array_slice( $people, 0, WPCPM_Abilities::PEOPLE_MAX ) as $person ) {
			$rows    = array();
			$tracks  = array();
			$account = false;

			foreach ( $person['ids'] as $record ) {
				$rows[ $record ] = $pool[ $record ]['row'];
			}

			$mentors = self::mentor_tracks( $person['every'] );

			foreach ( $rows as $record => $row ) {
				$tracks[] = self::track( $record, $row, $pool[ $record ]['institution'], isset( $mentors[ $record ] ) ? $mentors[ $record ] : null );
				$account  = $account || ( isset( $row['user_id'] ) && (int) $row['user_id'] > 0 );
			}

			// Earliest start first, a track with no start last, then by record ID.
			usort(
				$tracks,
				static function ( $a, $b ) {
					if ( $a['start'] !== $b['start'] ) {
						if ( null === $a['start'] || null === $b['start'] ) {
							return null === $a['start'] ? 1 : -1;
						}

						return strcmp( $a['start'], $b['start'] );
					}

					return strcmp( $a['record_id'], $b['record_id'] );
				}
			);

			$listed[] = array(
				'has_account' => $account,
				'tracks'      => $tracks,
			);
			$records  = array_merge( $records, array_column( $tracks, 'record_id' ) );
		}

		return array(
			'answer'  => array(
				'people'  => $listed,
				'more'    => $more,
				'read_at' => WPCPM_Abilities::time( $counts['read'] ),
			),
			'records' => $records,
		);
	}

	/**
	 * `wpcpm/mentor-students`: a mentor's students as the Mentor Report Card lists them, each by its record
	 * ID, and the mentor by none of their names.
	 *
	 * `mentor` is a Mentors record ID, or what `wp wpcredits mentor` takes: a login, an email address or a
	 * WordPress.org profile address (`WPCPM_Mentors_Dashboard::find_mentor()`, the command's own lookup).
	 * Only a mentor is found: an account the Engagement tab does not list, which is every account that is not
	 * a mentor's (the Mentor role, or a Mentors record ID on the account), is nobody, so a student's login
	 * finds nobody. Nobody is `found` false, `mentor` null, no students, no more and no time.
	 *
	 * The mentor is their Mentors record ID (null on an account that holds none: a Mentor made by hand), the
	 * Engagement tab's active flag, the tab's view they are filed under (`mentor_view()`), and the dates of
	 * the last sign-in and the last visit, as the tab records them, each only as a day in UTC. The students
	 * are the list the mentors sync keeps on the account, which the page reads too
	 * (`WPCPM_Mentors_Dashboard::get_mentees()`), the current students before the past ones as the page
	 * draws them and each group in the order the sync keeps it, at most `LIST_MAX`, with `more` when there
	 * were others. A row on the list is a Students Reports record, and a student is named as
	 * `wpcpm/find-student` names a track: by the Students record ID of the track that holds that record
	 * among every roster's (`tracks_holding()`, `track_of_report()`, the rule `mentor_tracks()` tells a
	 * track's mentor by), or null when the roster cannot tell. A student with two records on the list is two
	 * entries under one ID, as the page draws two cards. The status and the start are the list's; the hours
	 * are the number the card prints, the student's own account's count first
	 * (`WPCPM_Mentors_Dashboard::logged_count()`, which reads the program row and gives back the number
	 * alone). A row without a Students Reports record ID cannot be matched to a track and is left out, and a
	 * record listed twice is listed once.
	 *
	 * Nothing of a person goes out: not a name, an address, a login, a profile address, a tutor, a Slack
	 * handle, a website or an accessibility answer. The records of the mentor and the students go to the log,
	 * and the log keeps the input, which is the Administrator's own words for whom they looked up.
	 *
	 * @param mixed $input `mentor`, a Mentors record ID, a login, an email address or a WordPress.org profile
	 *                     address.
	 * @return array `answer` and `records` (the mentor's record ID, when they have one, and the Students
	 *               record IDs of the students, once each).
	 */
	public static function mentor( $input = null ) {
		$who  = is_array( $input ) && isset( $input['mentor'] ) && is_string( $input['mentor'] ) ? trim( $input['mentor'] ) : '';
		$user = '' === $who ? null : self::mentor_account( $who );
		$row  = null;

		// The Engagement tab lists every mentor and no one else: a row is what makes an account a mentor
		// here, and it comes before any account's meta is read.
		if ( $user instanceof WP_User ) {
			$report = WPCPM_Mentor_Engagement::report( time() );

			foreach ( is_array( $report ) && isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array() as $candidate ) {
				if ( is_array( $candidate ) && isset( $candidate['id'] ) && (int) $candidate['id'] === (int) $user->ID ) {
					$row = $candidate;
					break;
				}
			}
		}

		if ( null === $row ) {
			return array(
				'answer'  => array(
					'found'    => false,
					'mentor'   => null,
					'students' => array(),
					'more'     => false,
					'read_at'  => null,
				),
				'records' => array(),
			);
		}

		$record = get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true );
		$record = is_scalar( $record ) && WPCPM_Mentors_Sync::is_record_id( $record ) ? trim( (string) $record ) : null;

		// The page lists the current students, then the past ones, each in the order the sync keeps.
		$mentees  = array_values( array_filter( WPCPM_Mentors_Dashboard::get_mentees( $user->ID ), 'is_array' ) );
		$students = array();
		$listed   = array();
		$seen     = array();

		foreach ( array( false, true ) as $past ) {
			foreach ( $mentees as $mentee ) {
				$id = isset( $mentee['record_id'] ) && is_scalar( $mentee['record_id'] ) ? trim( (string) $mentee['record_id'] ) : '';

				if ( ! empty( $mentee['is_past'] ) !== $past || ! WPCPM_Mentors_Sync::is_record_id( $id ) || isset( $seen[ $id ] ) ) {
					continue;
				}

				$seen[ $id ] = true;
				$listed[]    = $mentee;
				$students[]  = array(
					// The Students Reports record for now; the Students record ID of its track below.
					'record_id' => $id,
					'status'    => WPCPM_Abilities::text( isset( $mentee['status'] ) ? $mentee['status'] : '' ),
					// Filled in below, once the list is cut.
					'hours'     => null,
					'start'     => self::day( isset( $mentee['start'] ) ? $mentee['start'] : '' ),
				);
			}
		}

		$more     = count( $students ) > WPCPM_Abilities::LIST_MAX;
		$students = array_slice( $students, 0, WPCPM_Abilities::LIST_MAX );

		// The number the card prints: the student's account's count first, the list's second. Worked out for the
		// rows that are kept and for no other: it looks the student's account up, and a mentor with a hundred
		// students would otherwise cost a hundred lookups for twenty-five rows.
		foreach ( $students as $n => $student ) {
			$students[ $n ]['hours'] = self::hours( WPCPM_Mentors_Dashboard::logged_count( $listed[ $n ] ) );
		}

		$updated = get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_UPDATED, true );
		$tracks  = array();

		// Each student is named as `wpcpm/find-student` names a track, by its Students record ID: the track
		// among every roster's (a `Duplicated` or `SPAM` row is none) that holds the report row the list is
		// made of. The rosters are read once, and only when there is a student to name.
		if ( array() !== $students ) {
			$counts = WPCPM_Roster_Index::counts();

			foreach ( self::roster_pool( array_keys( (array) $counts['institutions'] ), null ) as $track => $entry ) {
				$tracks[ (string) $track ] = $entry['row'];
			}
		}

		foreach ( $students as $n => $student ) {
			$students[ $n ]['record_id'] = self::track_of_report( self::tracks_holding( $student['record_id'], $tracks ), $tracks );
		}

		$named = array_values( array_unique( array_filter( array_column( $students, 'record_id' ) ) ) );

		return array(
			'answer'  => array(
				'found'    => true,
				'mentor'   => array(
					'record_id'    => $record,
					'active'       => ! empty( $row['active'] ),
					'view'         => self::mentor_view( $row ),
					'last_sign_in' => self::date_of( isset( $row['last_sign_in'] ) ? $row['last_sign_in'] : 0 ),
					'last_visit'   => self::date_of( isset( $row['last_visit'] ) ? $row['last_visit'] : 0 ),
				),
				'students' => $students,
				'more'     => $more,
				'read_at'  => WPCPM_Abilities::time( $updated ),
			),
			'records' => array_merge( null === $record ? array() : array( $record ), $named ),
		);
	}

	/**
	 * `wpcpm/mentor-applications`: the mentor applications in one state, newest received first, by reference.
	 *
	 * The state is `open` (the Mentors screen's Waiting for review tab: new, held, more information asked for
	 * or a second opinion wanted; the default), `selected` (waiting for the course) or `lapsed`; anything else
	 * is ignored as if it were not asked. An application filed as spam, declined or already made a mentor is
	 * in none of them, and a row whose state is not one of those asked for when it is read is left out. The
	 * limit is a whole number from 1 to `LIST_MAX`, `LIST_MAX` when it is anything else; `more` is true when
	 * more applications are in the state than are listed.
	 *
	 * An applicant is their application's reference, such as `MAPP-2026-0007`, which the queue prints over the
	 * answers of each application. A row gives the day it was received in UTC, its state, the days it has been
	 * in that state (counted from the history's row for the move into the state: a new one from the day it was
	 * put back in the queue, if it was; one asked for more information, one asked for a second opinion and a
	 * selected one from that day; a lapsed one from the day it lapsed or was withdrawn; else from the day it
	 * arrived), the country, the languages, the hours a week and the contribution areas the applicant ticked,
	 * the teacher question (true for Yes, false for No, null for anything else), whether the address is
	 * confirmed, and the flags: `duplicate` when the checks marked another application in the queue or waiting
	 * for the course as having the same address or username, `held` for a held one. Each of the five answers is
	 * a closed one the form takes from a list or a yes or no; the teacher question is read as true, false or
	 * null and nothing else, and each of the others is kept only when it is on the form's own list of them: the
	 * languages (`WPCPM_Mentor_Application_Form::languages()`), the country (`countries()`, null when it is not
	 * on the list) and the areas (`teams()`, left out when not), which the form's validators take the same way.
	 * An answer edited by hand cannot carry text out, and a list the site has not synced yet is empty, so
	 * nothing is on it: no country and no areas, never what the application holds. A list stops at `PICKED_MAX`.
	 *
	 * The totals are the feed's two numbers of today (`WPCPM_Mentor_Application_Feed::today()`, which the feed's
	 * own `build()` uses too, and which does not walk every application's history): the open applications and the
	 * selected ones waiting for the course, as the Mentors screen and the Administrator Dashboard print them, and
	 * the count of the lapsed ones.
	 *
	 * Nothing of the applicant goes out: not the name, the address, the profile, the free-text answer, the notes
	 * in the history, what the checks raised (but the one flag) or the consent evidence. The answers come from
	 * the accessor whole, as the application class keeps them, and the five above are taken by key; the history
	 * is asked for times alone. The log keeps the input, and its `records` are the references listed.
	 *
	 * @param mixed $input `state` and `limit`.
	 * @return array `answer` and `records` (the references of the rows listed).
	 */
	public static function applications( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$groups = array(
			'open'     => WPCPM_Mentor_Application::open_states(),
			'selected' => array( WPCPM_Mentor_Application::STATE_SELECTED ),
			'lapsed'   => array( WPCPM_Mentor_Application::STATE_LAPSED ),
		);
		$asked  = isset( $input['state'] ) && is_string( $input['state'] ) && isset( $groups[ $input['state'] ] ) ? $input['state'] : 'open';
		$states = $groups[ $asked ];
		$limit  = isset( $input['limit'] ) ? $input['limit'] : null;

		// A whole number from 1 to the list's limit; digits and a whole float are read as the number.
		if ( is_string( $limit ) && 1 === preg_match( '/^\d{1,3}$/', $limit ) ) {
			$limit = (int) $limit;
		} elseif ( is_float( $limit ) && $limit >= 1 && $limit <= WPCPM_Abilities::LIST_MAX && floor( $limit ) === $limit ) {
			$limit = (int) $limit;
		}

		$limit   = is_int( $limit ) && $limit >= 1 && $limit <= WPCPM_Abilities::LIST_MAX ? $limit : WPCPM_Abilities::LIST_MAX;
		$now     = time();
		$rows    = array();
		$records = array();
		$more    = false;

		// The form's own lists, read once. The site holds the countries and the teams once its syncs have run, and
		// until then a list is empty and nothing is on it: privacy over completeness.
		$countries = self::names( WPCPM_Mentor_Application_Form::countries() );
		$teams     = self::names( WPCPM_Mentor_Application_Form::teams() );

		// The list is oldest first; the newest received is listed first.
		foreach ( array_reverse( WPCPM_Mentor_Application::applications( $states ) ) as $post ) {
			$state = WPCPM_Mentor_Application::state_of( $post );

			if ( ! in_array( $state, $states, true ) ) {
				continue;
			}

			if ( count( $rows ) >= $limit ) {
				$more = true;
				break;
			}

			$fields    = WPCPM_Mentor_Application::fields_of( $post );
			$fields    = is_array( $fields ) ? $fields : array();
			$arrived   = (int) get_post_time( 'U', true, $post );
			$since     = self::application_since( $post, $state, $arrived );
			$teacher   = isset( $fields['teacher'] ) && is_string( $fields['teacher'] ) ? strtolower( trim( $fields['teacher'] ) ) : '';
			$reference = WPCPM_Abilities::text( WPCPM_Mentor_Application::reference_of( $post ) );
			$country   = WPCPM_Abilities::text( isset( $fields['country'] ) ? $fields['country'] : '' );
			$flags     = array();

			if ( in_array( WPCPM_Mentor_Application_Form::SIGNAL_DUPLICATE, WPCPM_Mentor_Application::signals_of( $post ), true ) ) {
				$flags[] = 'duplicate';
			}

			if ( WPCPM_Mentor_Application::STATE_HELD === $state ) {
				$flags[] = 'held';
			}

			$rows[]    = array(
				'reference'      => $reference,
				'received'       => self::date_of( $arrived ),
				'state'          => $state,
				'days_in_state'  => $since > 0 ? max( 0, intdiv( $now - $since, DAY_IN_SECONDS ) ) : null,
				'country'        => '' !== $country && in_array( $country, $countries, true ) ? $country : null,
				'languages'      => array_values( array_intersect( self::picked( isset( $fields['languages'] ) ? $fields['languages'] : array() ), WPCPM_Mentor_Application_Form::languages() ) ),
				'hours_per_week' => self::hours( isset( $fields['hours'] ) ? $fields['hours'] : '' ),
				'areas'          => array_values( array_intersect( self::picked( isset( $fields['expertise'] ) ? $fields['expertise'] : array() ), $teams ) ),
				'teacher'        => 'yes' === $teacher ? true : ( 'no' === $teacher ? false : null ),
				'confirmed'      => WPCPM_Mentor_Application::is_confirmed( (int) $post->ID ),
				'flags'          => $flags,
			);
			$records[] = $reference;
		}

		$today = WPCPM_Mentor_Application_Feed::today();

		return array(
			'answer'  => array(
				'applications' => $rows,
				'totals'       => array(
					'open'     => (int) $today['open'],
					'selected' => (int) $today['selected'],
					'lapsed'   => WPCPM_Mentor_Application::count_in( array( WPCPM_Mentor_Application::STATE_LAPSED ) ),
				),
				'more'         => $more,
			),
			'records' => array_values( array_filter( $records, 'strlen' ) ),
		);
	}

	/**
	 * When an application entered the state it is in, as a Unix time: the later of the days it was put back in
	 * the queue for a new one; the day more information, or a second opinion, was last asked for; the day a
	 * selected one was last selected; the day a lapsed one lapsed or was withdrawn. The day it arrived when the
	 * history holds no row for the move, as an older application's may not.
	 *
	 * @param object $post    The application.
	 * @param string $state   Its state.
	 * @param int    $arrived When it arrived, a Unix time, or 0.
	 * @return int A Unix time, or 0 when there is none.
	 */
	private static function application_since( $post, $state, $arrived ) {
		$id = (int) $post->ID;

		switch ( $state ) {
			case WPCPM_Mentor_Application::STATE_INFO:
				$since = WPCPM_Mentor_Application::last_at( $id, WPCPM_Mentor_Application::EVENT_INFO );
				break;

			case WPCPM_Mentor_Application::STATE_SECOND:
				$since = WPCPM_Mentor_Application::last_at( $id, WPCPM_Mentor_Application::EVENT_SECOND );
				break;

			case WPCPM_Mentor_Application::STATE_SELECTED:
				$since = WPCPM_Mentor_Application::last_at( $id, WPCPM_Mentor_Application::EVENT_SELECTED );
				break;

			case WPCPM_Mentor_Application::STATE_LAPSED:
				$since = WPCPM_Mentor_Application::decided_at( $post );
				break;

			default:
				$since = max( WPCPM_Mentor_Application::last_at( $id, WPCPM_Mentor_Application::EVENT_REOPENED ), WPCPM_Mentor_Application::last_at( $id, WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED ) );
		}

		return (int) $since > 0 ? (int) $since : max( 0, (int) $arrived );
	}

	/**
	 * The names of one of the form's lists, cleaned the way an answer is (`WPCPM_Abilities::text()`), so that
	 * what an applicant ticked or chose can be held against them: a name that is not on the list does not go out.
	 *
	 * @param string[] $offered The form's list (`countries()`, `teams()`).
	 * @return string[] The names, each as an answer would carry it; none that cleans to nothing.
	 */
	private static function names( array $offered ) {
		return array_values( array_filter( array_map( array( 'WPCPM_Abilities', 'text' ), $offered ), 'strlen' ) );
	}

	/**
	 * The choices ticked in one pick of the form, as clean lines: each once, in the order ticked, and no more
	 * than `PICKED_MAX`. Anything in the list that is not text is left out, and so is a pick that is not a list.
	 *
	 * @param mixed $value The pick, as the answers keep it.
	 * @return string[]
	 */
	private static function picked( $value ) {
		$out = array();

		foreach ( is_array( $value ) ? $value : array() as $one ) {
			$one = WPCPM_Abilities::text( $one );

			if ( '' !== $one && ! in_array( $one, $out, true ) ) {
				$out[] = $one;
			}

			if ( count( $out ) >= self::PICKED_MAX ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * The account a mentor is asked for by: the one that holds a Mentors record ID, else the one
	 * `wp wpcredits mentor` finds.
	 *
	 * @param string $who A Mentors record ID, a login, an email address or a WordPress.org profile address.
	 * @return WP_User|null
	 */
	private static function mentor_account( $who ) {
		if ( WPCPM_Mentors_Sync::is_record_id( $who ) ) {
			// A flat list of IDs, read through `id_of()`, as every other lookup of an account by its record
			// does: on this site the rows arrive as `stdClass`.
			$found = get_users(
				array(
					'meta_key'   => WPCPM_Mentors_Sync::META_RECORD_ID,
					'meta_value' => $who,
					'number'     => 1,
					'fields'     => 'ID',
				)
			);
			$id    = ! empty( $found[0] ) ? WPCPM_Roles::id_of( $found[0] ) : 0;
			$user  = $id > 0 ? get_user_by( 'id', $id ) : false;

			// The database compares meta under its own collation, which does not tell recABC from recabc;
			// Airtable record IDs do, so the account found is checked again, as the Engagement tab's search is.
			if ( $user instanceof WP_User && trim( (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true ) ) === $who ) {
				return $user;
			}
		}

		return WPCPM_Mentors_Dashboard::find_mentor( $who );
	}

	/**
	 * The Engagement tab's view a mentor is filed under, one of `WPCPM_Mentor_Engagement::views()`.
	 *
	 * A mentor is in several of the tab's views at once (everybody is in All, a mentor with students is
	 * in two or three more), so the one given is the one an Administrator looks first: the Administrators
	 * view for an Administrator who mentors, then the follow-ups in the order the tab prints them (never
	 * signed in, not seen in 30 days, no program action, students and no weekly hours), then With students
	 * now, then Active, and All for a mentor who stopped mentoring, whom no other view holds.
	 *
	 * @param array $row The mentor's row of the Engagement report.
	 * @return string The view's key.
	 */
	private static function mentor_view( array $row ) {
		$views = WPCPM_Mentor_Engagement::views();

		foreach ( array( WPCPM_Mentor_Engagement::VIEW_STAFF, WPCPM_Mentor_Engagement::VIEW_NEVER, WPCPM_Mentor_Engagement::VIEW_QUIET, WPCPM_Mentor_Engagement::VIEW_IDLE, WPCPM_Mentor_Engagement::VIEW_NO_HOURS, WPCPM_Mentor_Engagement::VIEW_STUDENTS, WPCPM_Mentor_Engagement::VIEW_ACTIVE ) as $view ) {
			if ( isset( $views[ $view ] ) && WPCPM_Mentor_Engagement::in_view( $row, $view ) ) {
				return $view;
			}
		}

		return WPCPM_Mentor_Engagement::VIEW_ALL;
	}

	/**
	 * A time the Engagement tab recorded, as the day it fell on in UTC, or null when there is none.
	 *
	 * @param mixed $unix Unix time, a number or text; zero, below zero and anything else is never.
	 * @return string|null `Y-m-d`.
	 */
	private static function date_of( $unix ) {
		$time = WPCPM_Abilities::time( $unix );

		return null === $time ? null : substr( $time, 0, 10 );
	}

	/**
	 * The roster rows a lookup of a student looks at, each with the institution whose roster lists it.
	 *
	 * Every roster and the rows that name no institution, or, with an institution asked for, the roster of
	 * that record ID or the rosters of every institution whose name, as the institutions index holds it,
	 * holds the words. A roster whose institution the index does not hold has no name to be found by. A
	 * `Duplicated` or `SPAM` row, and a row whose record ID is no record ID, is left out.
	 *
	 * @param string[] $rosters     The institutions with a roster.
	 * @param mixed    $institution The institution asked for: a record ID, part of a name, or nothing.
	 * @return array[] Students record ID => `row` and `institution` (its record ID, '' for none).
	 */
	private static function roster_pool( array $rosters, $institution ) {
		$needle = WPCPM_Abilities::text( $institution );
		$read   = array();
		$pool   = array();

		if ( '' === $needle ) {
			$read = $rosters;
		} elseif ( WPCPM_Mentors_Sync::is_record_id( $needle ) ) {
			$read = array( $needle );
		} elseif ( mb_strlen( $needle, 'UTF-8' ) >= self::NAME_MIN ) {
			foreach ( $rosters as $id ) {
				$row  = WPCPM_Institutions_Index::row( $id );
				$name = WPCPM_Abilities::text( is_array( $row ) && isset( $row['name'] ) ? $row['name'] : '' );

				if ( '' !== $name && self::holds( $name, $needle ) ) {
					$read[] = $id;
				}
			}
		}

		$add = static function ( $row, $institution ) use ( &$pool ) {
			if ( ! is_array( $row ) || ! isset( $row['record_id'] ) || ! WPCPM_Mentors_Sync::is_record_id( $row['record_id'] ) ) {
				return;
			}

			$record = trim( (string) $row['record_id'] );
			$status = isset( $row['status'] ) && is_scalar( $row['status'] ) ? trim( (string) $row['status'] ) : '';

			if ( ! isset( $pool[ $record ] ) && ! in_array( $status, WPCPM_Roster_Index::NEVER_SHOWN, true ) ) {
				$pool[ $record ] = array(
					'row'         => $row,
					'institution' => $institution,
				);
			}
		};

		foreach ( $read as $id ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $id ) ) {
				continue;
			}

			foreach ( WPCPM_Roster_Index::rows( trim( (string) $id ) ) as $row ) {
				$add( $row, trim( (string) $id ) );
			}
		}

		if ( '' === $needle ) {
			foreach ( WPCPM_Roster_Index::unlinked() as $row ) {
				$add( $row, '' );
			}
		}

		return $pool;
	}

	/**
	 * The people a lookup of a student finds among the rows looked at, as record IDs.
	 *
	 * The one method that reads the fields of a row that hold a person, the address key, the username and
	 * the name, and only to compare them with what was asked: what it gives back is record IDs, and whether
	 * the person was found by address, username, profile address or record ID (`exact`) or only by name.
	 * The rows sharing an address key are one person, and a row without one is a person of its own.
	 *
	 * Each person found also comes back with every row of theirs among every roster's (`every`), those the
	 * institution asked for left out of the pool among them, for `mentor_tracks()` alone: those rows are
	 * read only when someone was found, and none of them is listed.
	 *
	 * @param array[]       $pool     The rows looked at, by Students record ID (`roster_pool()`).
	 * @param string        $who      What was asked.
	 * @param callable|null $everyone Gives every roster's rows when the pool is narrowed to an institution;
	 *                                null when the pool is every row already.
	 * @return array[] Each person found, in the pool's order: `exact`; `ids`, the record IDs of every row of
	 *                 theirs in the pool; and `every`, each of their rows anywhere, by record ID.
	 */
	private static function people_matching( array $pool, $who, $everyone = null ) {
		$raw   = trim( (string) $who );
		$words = WPCPM_Abilities::text( $who );
		$email = 1 === preg_match( '/^[^@\s]+@[^@\s]+$/D', $raw ) ? strtolower( $raw ) : '';
		$id    = '' === $email && WPCPM_Mentors_Sync::is_record_id( $raw ) ? $raw : '';
		$other = '' === $email && '' === $id;
		$user  = $other && 0 === preg_match( '/\s/u', $raw ) ? WPCPM_Mentors_Sync::typed_username( $raw ) : '';
		$words = $other && mb_strlen( $words, 'UTF-8' ) >= self::NAME_MIN ? $words : '';
		$rows  = array();
		$found = array();

		foreach ( $pool as $record => $entry ) {
			$row    = $entry['row'];
			$key    = strtolower( isset( $row['email_key'] ) && is_scalar( $row['email_key'] ) ? trim( (string) $row['email_key'] ) : '' );
			$person = '' !== $key ? 'address ' . $key : 'row ' . $record;

			$rows[ $person ][] = (string) $record;

			$exact = ( '' !== $email && $key === $email )
				|| ( '' !== $id && (string) $record === $id )
				|| ( '' !== $user && isset( $row['username'] ) && is_scalar( $row['username'] ) && strtolower( trim( (string) $row['username'] ) ) === $user );

			if ( $exact ) {
				$found[ $person ] = true;
			} elseif ( '' !== $words && ! isset( $found[ $person ] ) && self::holds( WPCPM_Abilities::text( isset( $row['name'] ) ? $row['name'] : '' ), $words ) ) {
				$found[ $person ] = false;
			}
		}

		if ( array() === $found ) {
			return array();
		}

		$all   = null === $everyone ? $pool : call_user_func( $everyone );
		$every = array();

		foreach ( $all as $record => $entry ) {
			$key    = strtolower( isset( $entry['row']['email_key'] ) && is_scalar( $entry['row']['email_key'] ) ? trim( (string) $entry['row']['email_key'] ) : '' );
			$person = '' !== $key ? 'address ' . $key : 'row ' . $record;

			if ( isset( $found[ $person ] ) ) {
				$every[ $person ][ (string) $record ] = $entry['row'];
			}
		}

		$out = array();

		foreach ( $found as $person => $exact ) {
			$theirs = isset( $every[ $person ] ) ? $every[ $person ] : array();

			foreach ( $rows[ $person ] as $record ) {
				if ( ! isset( $theirs[ $record ] ) ) {
					$theirs[ $record ] = $pool[ $record ]['row'];
				}
			}

			$out[] = array(
				'exact' => $exact,
				'ids'   => $rows[ $person ],
				'every' => $theirs,
			);
		}

		return $out;
	}

	/**
	 * Which of a person's tracks the mentor on their account belongs to, and that mentor's record ID.
	 *
	 * The card the students sync keeps on an account (`META_MENTOR`) is the mentor of the Students Reports
	 * row the account follows, the one `governing_reports()` chose for the address, whose record ID the same
	 * pass writes on the account (`META_RECORD_ID`). The track it belongs to is the one whose `reports` holds
	 * that record. The sync joins a report to every Students row of its address, so two tracks can hold it:
	 * then the mentor goes to the one of those whose row names a mentor, when exactly one does, and to none
	 * otherwise. An account may also follow a report no track holds, or none: then the mentor goes to the
	 * one track whose row names a mentor, when the person has exactly one such track and it is on that
	 * account, and to none otherwise. A card without a Mentors record ID is no mentor. No other track
	 * carries it: a finished track's mentor is not the mentor of the student's current report. The rows are
	 * every track the person holds, those an institution hides from the answer too, so a visible track is
	 * never given a mentor that belongs to a hidden one.
	 *
	 * @param array[] $rows The person's roster rows, every one of them, by Students record ID.
	 * @return string[] Students record ID => Mentors record ID, for the tracks that have one.
	 */
	private static function mentor_tracks( array $rows ) {
		$accounts = array();
		$named    = array();

		foreach ( $rows as $record => $row ) {
			$account = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

			if ( $account > 0 ) {
				$accounts[ $account ][ $record ] = $row;
			}

			if ( ! empty( $row['has_mentor'] ) ) {
				$named[] = (string) $record;
			}
		}

		$out = array();

		foreach ( $accounts as $account => $tracks ) {
			$card = get_user_meta( $account, WPCPM_Students_Sync::META_MENTOR, true );

			if ( ! is_array( $card ) || ! isset( $card['record_id'] ) || ! WPCPM_Mentors_Sync::is_record_id( $card['record_id'] ) ) {
				continue;
			}

			$followed = get_user_meta( $account, WPCPM_Students_Sync::META_RECORD_ID, true );
			$followed = is_scalar( $followed ) ? trim( (string) $followed ) : '';
			$holding  = self::tracks_holding( $followed, $tracks );
			$track    = self::track_of_report( $holding, $tracks );

			if ( null !== $track ) {
				$out[ $track ] = trim( (string) $card['record_id'] );
			} elseif ( array() === $holding && 1 === count( $named ) && isset( $tracks[ $named[0] ] ) ) {
				// The report is on no track: the person's one track that names a mentor, when it is on this account.
				$out[ $named[0] ] = trim( (string) $card['record_id'] );
			}
		}

		return $out;
	}

	/**
	 * The tracks that hold a Students Reports record: the rows whose `reports` list it.
	 *
	 * The sync joins a report to every Students row of its address, so more than one track can hold it.
	 * What a record that is no record ID is held by is nothing.
	 *
	 * @param string  $report The Students Reports record ID.
	 * @param array[] $rows   Students record ID => roster row.
	 * @return string[] The Students record IDs, in the rows' order.
	 */
	private static function tracks_holding( $report, array $rows ) {
		$holding = array();

		if ( ! WPCPM_Mentors_Sync::is_record_id( $report ) ) {
			return $holding;
		}

		foreach ( $rows as $record => $row ) {
			if ( isset( $row['reports'] ) && is_array( $row['reports'] ) && in_array( $report, $row['reports'], true ) ) {
				$holding[] = (string) $record;
			}
		}

		return $holding;
	}

	/**
	 * The one track a Students Reports record belongs to, of the tracks that hold it: the rule both lookups
	 * of people use, so that a mentor's mentee and a student's mentor are told apart the same way.
	 *
	 * The only track that holds it. When two or more do, the one of them whose row names a mentor, when
	 * exactly one does; otherwise none can be told.
	 *
	 * @param string[] $holding The Students record IDs of the tracks that hold it (`tracks_holding()`).
	 * @param array[]  $rows    Students record ID => roster row, those of `$holding` among them.
	 * @return string|null The Students record ID, or null.
	 */
	private static function track_of_report( array $holding, array $rows ) {
		if ( 1 === count( $holding ) ) {
			return $holding[0];
		}

		$named = array();

		foreach ( $holding as $record ) {
			if ( ! empty( $rows[ $record ]['has_mentor'] ) ) {
				$named[] = $record;
			}
		}

		return count( $holding ) > 1 && 1 === count( $named ) ? $named[0] : null;
	}

	/**
	 * One track of a student as `wpcpm/find-student` answers it: what the program records of a Students row,
	 * and nothing of the person.
	 *
	 * @param string      $record      The Students record ID.
	 * @param array       $row         The roster row.
	 * @param string      $institution The record ID of the institution whose roster lists it; '' for none.
	 * @param string|null $mentor      The Mentors record ID of the mentor this track belongs to, or null
	 *                                 (`mentor_tracks()`).
	 * @return array
	 */
	private static function track( $record, array $row, $institution, $mentor ) {
		$account = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
		$status  = WPCPM_Abilities::text( isset( $row['status'] ) ? $row['status'] : '' );
		$start   = self::day( isset( $row['start'] ) ? $row['start'] : '' );
		$course  = null;
		$place   = null;

		// The course a student waiting to graduate took, as every screen names it: the one their account
		// keeps, and only while a track on this site holds it.
		if ( WPCPM_Program::STATUS_PENDING === $status ) {
			$kept   = WPCPM_Abilities::text( WPCPM_Program::course_status( $status, $account ) );
			$course = '' !== $kept ? $kept : null;
		}

		if ( '' !== $institution ) {
			$index = WPCPM_Institutions_Index::row( $institution );
			$place = array(
				'record_id' => $institution,
				'name'      => WPCPM_Abilities::text( is_array( $index ) && isset( $index['name'] ) ? $index['name'] : '' ),
			);
		}

		return array(
			'record_id'        => $record,
			'status'           => $status,
			'institution'      => $place,
			'cohort'           => null === $start ? null : WPCPM_Cohort::key( $start ),
			'start'            => $start,
			'end'              => self::day( isset( $row['end'] ) ? $row['end'] : '' ),
			'has_mentor'       => ! empty( $row['has_mentor'] ),
			'mentor_record_id' => $mentor,
			'team'             => WPCPM_Abilities::text( isset( $row['team'] ) ? $row['team'] : '' ),
			'hours'            => self::hours( isset( $row['hours'] ) ? $row['hours'] : '' ),
			'reports'          => isset( $row['reports'] ) && is_array( $row['reports'] ) ? count( $row['reports'] ) : 0,
			'waiting_course'   => $course,
		);
	}

	/**
	 * Hours as a row holds them: kept as the base sends them, and some are fractional, so a whole number stays
	 * whole.
	 *
	 * @param mixed $value The value.
	 * @return int|float|null A number, or null when the row holds none.
	 */
	private static function hours( $value ) {
		$hours = is_scalar( $value ) ? trim( (string) $value ) : '';
		$hours = is_numeric( $hours ) ? 0 + $hours : null;

		return is_float( $hours ) && ! is_finite( $hours ) ? null : $hours;
	}

	/**
	 * A day as a roster row holds it, when it is one: `Y-m-d`, a date that exists, or null.
	 *
	 * @param mixed $value The value.
	 * @return string|null
	 */
	private static function day( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return WPCPM_Cohort::NONE !== WPCPM_Cohort::key( $value ) ? $value : null;
	}

	/**
	 * Whether a text holds some words, case-insensitive.
	 *
	 * @param string $text  The text, cleaned.
	 * @param string $words The words, cleaned.
	 * @return bool
	 */
	private static function holds( $text, $words ) {
		return false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $text, $words, 0, 'UTF-8' ) : stripos( $text, $words ) );
	}

	/**
	 * A pipeline stage as an answer names it: cleaned, and the stage a record with none is counted in.
	 *
	 * @param mixed $stage A stage as the index holds it.
	 * @return string
	 */
	private static function stage( $stage ) {
		$stage = WPCPM_Abilities::text( $stage );

		return '' !== $stage ? $stage : self::NO_STAGE;
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
	 * A lookup of a student requires `who`, and a lookup of a mentor `mentor`, each of three characters or
	 * more, so core refuses a shorter one before the build, and takes it up to `WHO_MAX`, the length of a
	 * profile address. The mentor applications take a state and a limit, both optional, and their default is
	 * an empty list, as the engagement summary's is.
	 *
	 * @param string $method The builder's name: `program_counts`, `waiting`, `engagement`, `institutions`,
	 *                       `students`, `mentor` or `applications`.
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

		if ( 'students' === $method ) {
			return array(
				'type'                 => 'object',
				'properties'           => array(
					'who'         => array(
						'type'        => 'string',
						'minLength'   => self::NAME_MIN,
						'maxLength'   => self::WHO_MAX,
						'description' => __( 'The student: an email address, a WordPress.org username or profile address, a Students record ID, or three or more characters of a name. At most ten people are returned, by record ID and never by name.', 'wpcredits-program-manager' ),
					),
					'institution' => array(
						'type'        => 'string',
						'minLength'   => self::NAME_MIN,
						'maxLength'   => WPCPM_Abilities::TEXT_MAX,
						'description' => __( 'An institution\'s record ID, or three or more characters of its name, to look among its students only. Leave it out to look among every institution\'s students and those with none.', 'wpcredits-program-manager' ),
					),
				),
				'required'             => array( 'who' ),
				'additionalProperties' => false,
			);
		}

		if ( 'mentor' === $method ) {
			return array(
				'type'                 => 'object',
				'properties'           => array(
					'mentor' => array(
						'type'        => 'string',
						'minLength'   => self::NAME_MIN,
						'maxLength'   => self::WHO_MAX,
						'description' => __( 'The mentor: a Mentors record ID, a WordPress username, an email address or a WordPress.org profile address. The mentor and the students are returned by record ID and never by name; at most 25 students are returned.', 'wpcredits-program-manager' ),
					),
				),
				'required'             => array( 'mentor' ),
				'additionalProperties' => false,
			);
		}

		if ( 'applications' === $method ) {
			return array(
				'type'                 => 'object',
				'properties'           => array(
					'state' => array(
						'type'        => 'string',
						'enum'        => array( 'open', 'selected', 'lapsed' ),
						'default'     => 'open',
						'description' => __( 'Which applications to list: `open` (the default) for those in the queue, new, held, asked for more information or waiting for a second opinion; `selected` for those waiting for the course; `lapsed` for those that lapsed. An application filed as spam, declined or already made a mentor is never listed.', 'wpcredits-program-manager' ),
					),
					'limit' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => WPCPM_Abilities::LIST_MAX,
						'default'     => WPCPM_Abilities::LIST_MAX,
						'description' => __( 'How many applications to list, the newest received first, from 1 to 25. When more are in the state, `more` says so.', 'wpcredits-program-manager' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			);
		}

		return null;
	}

	/**
	 * What an ability answers, for `wp_register_ability()`'s `output_schema`: every key the answer can hold.
	 *
	 * An object with fixed keys is closed. The three maps whose keys are data say what their values are
	 * instead. A key that is only there while it has something to say (an outcome figure that may not be
	 * shown) is a property that is not required; a value that may be missing (a track's dates, its
	 * institution) is there as null, and its type says so. `WPCPM_Abilities::definitions()` adds the schema's
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

			case 'students':
				return self::students_schema();

			case 'mentor':
				return self::mentor_schema();

			case 'applications':
				return self::applications_schema();
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
						'by_stage' => self::keyed(
							self::integer( __( 'Institutions at this pipeline stage.', 'wpcredits-program-manager' ) ),
							/* translators: "No stage" is a key of the answer, which stays in English: keep it as it is. */
							__( 'Institutions per pipeline stage; "No stage" counts those with none.', 'wpcredits-program-manager' )
						),
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
							/* translators: "No stage" is a value of the answer, which stays in English: keep it as it is. */
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
	 * The schema of `students()`.
	 *
	 * @return array
	 */
	private static function students_schema() {
		$institution = self::object(
			array(
				'record_id' => self::text_of( __( 'The institution\'s record ID.', 'wpcredits-program-manager' ) ),
				'name'      => self::text_of( __( 'The institution\'s name; empty when the institutions index does not hold it.', 'wpcredits-program-manager' ) ),
			)
		);

		$institution['type']        = array( 'object', 'null' );
		$institution['description'] = __( 'The institution whose roster lists the track; null when the row names none.', 'wpcredits-program-manager' );

		$track = self::object(
			array(
				'record_id'        => self::text_of( __( 'The track\'s Students record ID. An Administrator puts the name to it on the site: for a student with an account, search it in the Student accounts list of the Students screen; for any student, with an account or not, search it in the roster of the track\'s institution on the Institution Dashboard, in the cohort the track started in. A track with no institution is on no roster.', 'wpcredits-program-manager' ) ),
				'status'           => self::text_of( __( 'The track or the state the row is in, as the Students table spells it.', 'wpcredits-program-manager' ) ),
				'institution'      => $institution,
				'cohort'           => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'The semester the track started in, YYYY-H1 or YYYY-H2; null when it has no start date.', 'wpcredits-program-manager' ) ),
				'start'            => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'The day the track started, as Y-m-d; null when the row has none.', 'wpcredits-program-manager' ) ),
				'end'              => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'The day the track ended or ends, as Y-m-d; null when the row has none.', 'wpcredits-program-manager' ) ),
				'has_mentor'       => self::described( array( 'type' => 'boolean' ), __( 'Whether the Students row names a mentor.', 'wpcredits-program-manager' ) ),
				'mentor_record_id' => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'The Mentors record ID of the student\'s mentor, on the one track that mentor belongs to: the track whose Students Reports row the student\'s account follows, or, when that cannot be told, the person\'s one track whose row names a mentor. Null on every other track, and when there is no account or it holds no mentor.', 'wpcredits-program-manager' ) ),
				'team'             => self::text_of( __( 'The contribution team; empty when none is recorded.', 'wpcredits-program-manager' ) ),
				'hours'            => self::described( array( 'type' => array( 'number', 'null' ) ), __( 'The hours recorded for the track, a fraction for some; null when none are.', 'wpcredits-program-manager' ) ),
				'reports'          => self::integer( __( 'How many Students Reports rows the track has.', 'wpcredits-program-manager' ) ),
				'waiting_course'   => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'For a track waiting to graduate, the course the student took, as the site\'s screens name it; null otherwise, and when the site no longer runs that course.', 'wpcredits-program-manager' ) ),
			)
		);

		return self::object(
			array(
				'people'  => array(
					'type'        => 'array',
					'description' => __( 'The people found, at most ten: those found by their email address, username, profile address or record ID first, then those whose name holds the words, each with the most recent start first. Empty when none was found. No one is named: an Administrator puts the name to a track\'s record ID on the site, in the Student accounts list for a student with an account and in the Institution Dashboard roster of the track\'s institution for any student.', 'wpcredits-program-manager' ),
					'items'       => self::object(
						array(
							'has_account' => self::described( array( 'type' => 'boolean' ), __( 'Whether the person has an account on this site.', 'wpcredits-program-manager' ) ),
							'tracks'      => array(
								'type'        => 'array',
								'description' => __( 'Every track the person holds, one per Students row, the earliest start first. A row marked Duplicated or SPAM is never one.', 'wpcredits-program-manager' ),
								'items'       => $track,
							),
						)
					),
				),
				'more'    => self::described( array( 'type' => 'boolean' ), __( 'True when more people matched than are listed.', 'wpcredits-program-manager' ) ),
				'read_at' => self::when( __( 'When the roster was last read, ISO 8601 in UTC; null when it never was.', 'wpcredits-program-manager' ) ),
			)
		);
	}

	/**
	 * The schema of `mentor()`.
	 *
	 * @return array
	 */
	private static function mentor_schema() {
		$mentor = self::object(
			array(
				'record_id'    => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'The mentor\'s Mentors record ID. Search it in the Mentor accounts list or on the Engagement tab (the Mentors screen) to find the mentor\'s account. Null for an account that holds none, a mentor made by hand.', 'wpcredits-program-manager' ) ),
				'active'       => self::described( array( 'type' => 'boolean' ), __( 'Whether the mentor is active, as the Engagement tab counts it: false for a mentor who stopped mentoring.', 'wpcredits-program-manager' ) ),
				'view'         => self::described(
					array(
						'type' => 'string',
						'enum' => array( 'all', 'active', 'students', 'never', 'quiet', 'idle', 'no-hours', 'staff' ),
					),
					__( 'The Engagement tab\'s view the mentor is filed under, one of `all`, `active`, `students`, `never`, `quiet`, `idle`, `no-hours` or `staff`: `staff` for an Administrator who mentors, else the first follow-up that holds the mentor (`never` signed in, `quiet` for 30 days, `idle` with no program action, `no-hours` with students and no weekly hours), else `students` for a mentor with students now, `active`, and `all` for a mentor who stopped mentoring.', 'wpcredits-program-manager' )
				),
				'last_sign_in' => self::date_of_day( __( 'The day of the mentor\'s last sign-in, as Y-m-d in UTC; null when they never signed in.', 'wpcredits-program-manager' ) ),
				'last_visit'   => self::date_of_day( __( 'The day of the mentor\'s last visit to the site, as Y-m-d in UTC; null when they never visited.', 'wpcredits-program-manager' ) ),
			)
		);

		$mentor['type']        = array( 'object', 'null' );
		$mentor['description'] = __( 'The mentor; null when no mentor was found. An account that is not a mentor\'s is no mentor.', 'wpcredits-program-manager' );

		$student = self::object(
			array(
				'record_id' => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'The student\'s Students record ID, the one wpcpm/find-student gives for the track, so that the two answers can be matched: the track that holds the Students Reports row the Mentor Report Card lists the student by, or, when several do, the one of them whose row names a mentor if only one does. Null when the roster cannot tell: no track holds that row, or the roster has not been read. A student with two such rows on the list is listed once for each. An Administrator puts the name to it on the site: for a student with an account, search it in the Student accounts list of the Students screen; for any student, ask wpcpm/find-student for it, which gives the track\'s institution, and search it in that institution\'s roster on the Institution Dashboard.', 'wpcredits-program-manager' ) ),
				'status'    => self::text_of( __( 'The track or the state the student is in, as the base spells it.', 'wpcredits-program-manager' ) ),
				'hours'     => self::described( array( 'type' => array( 'number', 'null' ) ), __( 'The hours recorded for the student, a fraction for some; null when none are.', 'wpcredits-program-manager' ) ),
				'start'     => self::date_of_day( __( 'The day the student started, as Y-m-d; null when the list has none.', 'wpcredits-program-manager' ) ),
			)
		);

		return self::object(
			array(
				'found'    => self::described( array( 'type' => 'boolean' ), __( 'Whether a mentor was found. False, with nothing else filled, for a mentor nobody has and for an account that is not a mentor\'s.', 'wpcredits-program-manager' ) ),
				'mentor'   => $mentor,
				'students' => array(
					'type'        => 'array',
					'description' => __( 'The mentor\'s students as the Mentor Report Card lists them, one for each Students Reports row on the list, the current ones before the past ones, at most 25. Empty when the mentor has none. No one is named: each record ID says where on the site to put the name to it.', 'wpcredits-program-manager' ),
					'items'       => $student,
				),
				'more'     => self::described( array( 'type' => 'boolean' ), __( 'True when the mentor has more students than are listed.', 'wpcredits-program-manager' ) ),
				'read_at'  => self::when( __( 'When the mentors sync last wrote this mentor\'s list of students, ISO 8601 in UTC; null when it never did, and when no mentor was found. The Students record IDs are the roster\'s, as it stands: wpcpm/find-student says when it was read.', 'wpcredits-program-manager' ) ),
			)
		);
	}

	/**
	 * The schema of `applications()`.
	 *
	 * @return array
	 */
	private static function applications_schema() {
		$item = self::object(
			array(
				'reference'      => self::text_of( __( 'The application\'s reference, such as MAPP-2026-0007, the one the applicant was shown on the thank-you page and the Mentors screen prints over the answers of each application. Find the application by it in the Find by reference field on the Mentors screen\'s Waiting for review tab; no one is named here.', 'wpcredits-program-manager' ) ),
				'received'       => self::date_of_day( __( 'The day the application was received, as Y-m-d in UTC; null when the site holds no time for it.', 'wpcredits-program-manager' ) ),
				'state'          => self::described(
					array(
						'type' => 'string',
						'enum' => array( 'new', 'held', 'info', 'second', 'selected', 'lapsed' ),
					),
					__( 'The application\'s state: `new`, `held` (the form\'s checks held it for a person to read first), `info` (more information was asked for), `second` (a second opinion is wanted), `selected` (waiting for the course) or `lapsed`.', 'wpcredits-program-manager' )
				),
				'days_in_state'  => self::described(
					array(
						'type'    => array( 'integer', 'null' ),
						'minimum' => 0,
					),
					__( 'Whole days since the application entered its state, counted from the history\'s row for the move into the state: for a new one, since it arrived or was put back in the queue; for one asked for more information or a second opinion, since that was asked; for a selected one, since it was selected; for a lapsed one, since it lapsed or was withdrawn. Null when the site holds no time for it.', 'wpcredits-program-manager' )
				),
				'country'        => self::described( array( 'type' => array( 'string', 'null' ) ), __( 'The country the applicant chose from the form\'s list; null when none was chosen, when what the application holds is not on the form\'s list, and while the site\'s list of countries has not been synced.', 'wpcredits-program-manager' ) ),
				'languages'      => array(
					'type'        => 'array',
					'maxItems'    => self::PICKED_MAX,
					'description' => __( 'The languages the applicant could mentor in, chosen from the form\'s list; empty when none was.', 'wpcredits-program-manager' ),
					'items'       => array( 'type' => 'string' ),
				),
				'hours_per_week' => self::described( array( 'type' => array( 'number', 'null' ) ), __( 'The hours a week the applicant has to mentor, a fraction for some; null when none were given.', 'wpcredits-program-manager' ) ),
				'areas'          => array(
					'type'        => 'array',
					'maxItems'    => self::PICKED_MAX,
					'description' => __( 'The contribution areas the applicant has expertise in, chosen from the form\'s list; empty when none were chosen, and while the site\'s list of contribution teams has not been synced.', 'wpcredits-program-manager' ),
					'items'       => array( 'type' => 'string' ),
				),
				'teacher'        => self::described( array( 'type' => array( 'boolean', 'null' ) ), __( 'The answer to the question whether the applicant is a teacher from a WordPress Credits participating institution: true for Yes, false for No; null when it was not answered.', 'wpcredits-program-manager' ) ),
				'confirmed'      => self::described( array( 'type' => 'boolean' ), __( 'Whether the applicant has confirmed the email address by following the link in the acknowledgement.', 'wpcredits-program-manager' ) ),
				'flags'          => array(
					'type'        => 'array',
					'description' => __( 'What the queue marks the application with: `duplicate` for a possible duplicate (another application in the queue or waiting for the course has the same address or WordPress.org username), and `held` for a held one. Empty when it has neither.', 'wpcredits-program-manager' ),
					'items'       => array(
						'type' => 'string',
						'enum' => array( 'duplicate', 'held' ),
					),
				),
			)
		);

		return self::object(
			array(
				'applications' => array(
					'type'        => 'array',
					'maxItems'    => WPCPM_Abilities::LIST_MAX,
					'description' => __( 'The applications in the state asked for, the newest received first, at most the limit asked for. Empty when there are none. No one is named: look the references up on the site.', 'wpcredits-program-manager' ),
					'items'       => $item,
				),
				'totals'       => self::object(
					array(
						'open'     => self::integer( __( 'How many applications are open (new, held, asked for more information or waiting for a second opinion), the number the Mentors screen and the Administrator Dashboard print.', 'wpcredits-program-manager' ) ),
						'selected' => self::integer( __( 'How many applications are selected and waiting for the course.', 'wpcredits-program-manager' ) ),
						'lapsed'   => self::integer( __( 'How many applications have lapsed.', 'wpcredits-program-manager' ) ),
					),
					null,
					__( 'How many applications are in each state asked about, whichever one is listed.', 'wpcredits-program-manager' )
				),
				'more'         => self::described( array( 'type' => 'boolean' ), __( 'True when more applications are in the state than are listed.', 'wpcredits-program-manager' ) ),
			)
		);
	}

	/**
	 * A day: `Y-m-d`, or null when there is none.
	 *
	 * @param string $description What the day is, and that null means none.
	 * @return array
	 */
	private static function date_of_day( $description ) {
		return self::described(
			array(
				'type'    => array( 'string', 'null' ),
				'pattern' => '^\d{4}-\d{2}-\d{2}$',
			),
			$description
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
