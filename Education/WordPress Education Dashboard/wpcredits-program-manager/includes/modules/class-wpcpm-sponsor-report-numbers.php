<?php
/**
 * The figures an annual sponsor report prints, each one defined here and nowhere else.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one reader of the program's records for a period: the program's students, one track's
 * students, and one sponsor's sponsorship, each between two days a caller names.
 *
 * **The period is always the caller's.** Nothing here reads the clock: a report is the account of
 * a term, so every figure is asked for two explicit `Y-m-d` days, and a report generated again a
 * month later answers the same question it answered the first time, against what the records hold
 * by then. The events held are the one list the clock still reaches, through the records: the
 * events store counts an event once it is over, so a report drafted before its term ends lists the
 * events over by then, and one generated again after the term lists them all. Days are compared as
 * strings, the cohort class's rule: a day is a calendar fact, and a timestamp would pin it to one
 * timezone's midnight.
 *
 * **Counts, the mentors' names, and nothing else about a person** (spec section 10). The rows read
 * here carry students' names, addresses, sites and schools, and an event holds the people on it and
 * its call link; what leaves is a number, a mentor's name and profile, and the company's own facts,
 * an event's title among them. `bin/test-sponsor-report.php` walks every answer for the students and
 * institutions it seeds, and for the people on the events it seeds, the call link and the
 * description.
 *
 * The rules, once:
 *
 * - The rows are every institution's roster, and the students the Students table files under no
 *   institution (`WPCPM_Roster_Index::unlinked()`), which add no institution and no country. The
 *   accounts placed from the reports side alone (`WPCPM_Roster_Index::unlinked_for()`) are not
 *   Students rows, and stay outside.
 * - A row, or a mentor's student, counts unless its status is one the roster never shows
 *   (`WPCPM_Roster_Index::NEVER_SHOWN`) or is not a person who signed up (`WPCPM_Cohort::NOT_SIGNED_UP`).
 * - **started**: the start day is in the period. **finished**: the status is Graduate and the end
 *   day is in the period, or, with no end day, the start day is.
 * - **active**: started on or before the period's last day and not ended before its first. No end
 *   day means still going only for a current status, the `student_statuses` setting; a row whose
 *   status is not current (Graduate, Dropped out and the like) and has no end day is active only
 *   when it started in the period. A row with no start day is never counted as started, but it
 *   passes "started on or before the last day" by the letter of the rule (`'' <= until`): with an
 *   end day not before the period it is active, so a graduate counted as finished in the year she
 *   finished is active in it too, and with no dates at all it is active while its status is
 *   current.
 * - **hours**: the sum of the active rows' hours, one decimal kept: each student's hours to date,
 *   not the hours logged inside the period, so a student active in two terms is in both;
 *   **contributions**: the active rows with a contribution team; **teams**: the distinct teams
 *   among them, a row naming several counting each; **institutions**: the distinct institutions of
 *   the active rows; **countries**: the distinct countries of those institutions, as the
 *   institutions index records them.
 * - A day is the leading `Y-m-d` of a value and must be a real day; anything else is no day.
 */
final class WPCPM_Sponsor_Report_Numbers {

	/** The program's figures, in the order every answer holds them: `hours` a float, the rest ints. */
	const COUNTS = array( 'started', 'finished', 'active', 'hours', 'contributions', 'teams', 'institutions', 'countries' );

	/**
	 * A track's figures: the program's but `finished`. The record keeps no graduate's track, so a
	 * track could only ever say that no one finished; see `track()`.
	 */
	const TRACK_COUNTS = array( 'started', 'active', 'hours', 'contributions', 'teams', 'institutions', 'countries' );

	/** The status a student who finished carries, spelled as the base spells it. */
	const STATUS_GRADUATE = 'Graduate';

	/**
	 * The program's figures for a period, over every institution's roster and the students filed
	 * under none.
	 *
	 * Every institution the institutions index holds, each roster as the students sync last wrote
	 * it, and the Students rows that name no institution (`WPCPM_Roster_Index::unlinked()`), which
	 * count in every figure but `institutions` and `countries`.
	 *
	 * @param string $from  The period's first day, `Y-m-d`.
	 * @param string $until The period's last day, `Y-m-d`.
	 * @return array{started: int, finished: int, active: int, hours: float, contributions: int, teams: int, institutions: int, countries: int}
	 *         All zero for a period that is not two days in order.
	 */
	public static function program( $from, $until ) {
		return self::count( self::rows(), $from, $until );
	}

	/**
	 * One track's figures for a period: the program's, over the rows whose status is the track's
	 * when the report is generated, and without `finished`.
	 *
	 * A status names its track through `WPCPM_Program::track()`, the key the rest of the plugin
	 * reads. **The record keeps no graduate's track:** a student who graduated carries `Graduate`,
	 * which maps to no track, and a roster row holds no track of its own, so a track could only
	 * ever say no one finished, and the figure is left out rather than printed as a zero. A student
	 * who graduated from the track or left it is in none of its figures; an empty key, which every
	 * finished state maps to, is no track at all.
	 *
	 * @param string $key   A track key, as `WPCPM_Program::track()` answers it.
	 * @param string $from  The period's first day, `Y-m-d`.
	 * @param string $until The period's last day, `Y-m-d`.
	 * @return array `program()`'s figures but `finished` (`TRACK_COUNTS`), then `label`, the track's
	 *               name; all zero, with an empty label, for an empty key.
	 */
	public static function track( $key, $from, $until ) {
		$key  = trim( (string) $key );
		$rows = array();

		if ( '' !== $key ) {
			foreach ( self::rows() as $row ) {
				if ( WPCPM_Program::track( $row['status'] ) === $key ) {
					$rows[] = $row;
				}
			}
		}

		$counts = self::count( $rows, $from, $until );
		$out    = array();

		foreach ( self::TRACK_COUNTS as $figure ) {
			$out[ $figure ] = $counts[ $figure ];
		}

		$out['label'] = '' === $key ? '' : self::track_label( $key );

		return $out;
	}

	/**
	 * One sponsor's sponsorship for a period.
	 *
	 * **The tier is the one held on the period's last day**, `WPCPM_Sponsor_Tiers::effective()` on
	 * that day: the recorded tier while the term runs to it, a Friend after, so a lapsed
	 * sponsor's report is the report of the tier it held. The funded line is shown from Backer up,
	 * the ladder's `funded_line` rule, and the events are the ones that tier is invited to.
	 *
	 * **The mentors** are the ones Active and linked to the sponsor when the report is generated, by
	 * name and profile, each with the students it guided whose start day is in the period, from the
	 * mentor's own cached students; a mentor who guided the sponsor's students and left before the
	 * draft is not named, and a mentor with no account here has none to count. **The claims** are
	 * counted by month over the months the period touches, through
	 * `WPCPM_Sponsor_Claims::stats_for_months()`, so a period that does not start on the first of a
	 * month counts its first and last months whole: a claim is dated to its month and nothing finer.
	 * An offer is listed when it drew a claim in those months. **The posts** are the sponsor's posts
	 * published in the period, those still published and those the daily run has since put dormant:
	 * a lapsed sponsor's posts go dormant once the grace date has passed, dated as they were
	 * published, and were published in the period all the same. **The events held** are the events
	 * store's answer (`WPCPM_Sponsor_Event::held_in()`): the sponsor's approved events over by the
	 * time the report is generated whose end falls in the period, each its title, its start, its
	 * length and how many people were on it, and never who they were, the call link or the
	 * description; they are the events that took place, whatever the tier held on the last day.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row, read for `tier`, `tier_until` and `funded_line`.
	 * @param string $from   The period's first day, `Y-m-d`.
	 * @param string $until  The period's last day, `Y-m-d`.
	 * @return array{tier: string, tier_label: string, from: string, until: string, funded_line: string, mentors: array[], months: array, offers: array, posts: int, events: string[], events_held: array[]}
	 *         `mentors` each `name`, `profile`, `students`; `months` `Y-m` => claims; `offers` each
	 *         `title`, `claims`, `series` (`Y-m` => claims); `events_held` each `title`, `start` (a
	 *         UTC timestamp), `minutes` and `joined`, soonest first; a period that is not two days in
	 *         order counts nothing, and a last day that is not a day reads as a Friend's.
	 */
	public static function sponsorship( $record, array $row, $from, $until ) {
		$record = trim( (string) $record );
		$from   = (string) $from;
		$until  = (string) $until;
		$valid  = self::valid( $from, $until );

		// Without a real last day `effective()` would read today, and nothing here does.
		$tier   = WPCPM_Sponsor_Tiers::is_date( $until ) ? WPCPM_Sponsor_Tiers::effective( $row, $until ) : WPCPM_Sponsor_Tiers::MENTOR;
		$claims = $valid ? self::claims( $record, $from, $until ) : array(
			'months' => array(),
			'offers' => array(),
		);

		// Asked only where the events store is loaded, as the module reaches each of its stores: a
		// request that loads the report without it reads no event held, rather than a fatal.
		$held = $valid && class_exists( 'WPCPM_Sponsor_Event' ) ? WPCPM_Sponsor_Event::held_in( $record, $from, $until ) : array();

		return array(
			'tier'        => $tier,
			'tier_label'  => WPCPM_Sponsor_Tiers::label( $tier ),
			'from'        => $from,
			'until'       => $until,
			'funded_line' => WPCPM_Sponsor_Tiers::includes( $tier, 'funded_line' ) && isset( $row['funded_line'] ) ? trim( (string) $row['funded_line'] ) : '',
			'mentors'     => self::mentors( $record, $valid ? $from : '', $valid ? $until : '' ),
			'months'      => $claims['months'],
			'offers'      => $claims['offers'],
			'posts'       => $valid ? self::posts( $record, $from, $until ) : 0,
			'events'      => array_values( WPCPM_Sponsor_Tiers::events( $tier ) ),
			'events_held' => $held,
		);
	}

	/**
	 * Hours as a report prints them: one decimal when there is one, none when the sum is whole, in
	 * the site's own number format.
	 *
	 * @param float|int|string $hours A sum of hours.
	 * @return string
	 */
	public static function format_hours( $hours ) {
		$hours = round( (float) $hours, 1 );

		return (string) number_format_i18n( $hours, floor( $hours ) === $hours ? 0 : 1 );
	}

	/**
	 * Every roster row that counts, reduced to what the figures read.
	 *
	 * Read from the rosters each time rather than held for the request: a report generated after an
	 * import or a sync in the same request must count what the rosters hold now.
	 *
	 * @return array[] Each `status`, `current` (whether the status is one the program counts as
	 *                 current), `start`, `end` (days or ''), `hours` (float), `teams` (string[]),
	 *                 `institution` and `country` ('' for a student filed under no institution).
	 */
	private static function rows() {
		// The list both syncs read as the students a mentor is working with now, trimmed as they
		// trim it; a Track Builder track's status joins it when the track is published.
		$current   = array_map( 'trim', (array) WPCPM_Settings::get_value( 'student_statuses', array() ) );
		$countries = array();

		foreach ( WPCPM_Institutions_Index::rows() as $key => $institution ) {
			if ( ! is_array( $institution ) ) {
				continue;
			}

			$record = isset( $institution['record_id'] ) ? trim( (string) $institution['record_id'] ) : '';
			$record = '' !== $record ? $record : trim( (string) $key );

			$countries[ $record ] = isset( $institution['country'] ) ? trim( (string) $institution['country'] ) : '';
		}

		$rows = array();

		foreach ( array_keys( $countries ) as $record ) {
			foreach ( WPCPM_Roster_Index::rows( $record ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				// A row names its institution; one that names none is the roster's it sits on.
				$institution = isset( $row['institution'] ) ? trim( (string) $row['institution'] ) : '';
				$institution = '' !== $institution ? $institution : (string) $record;
				$reduced     = self::reduce( $row, $current, $institution, isset( $countries[ $institution ] ) ? $countries[ $institution ] : '' );

				if ( null !== $reduced ) {
					$rows[] = $reduced;
				}
			}
		}

		// The Students rows that name no institution, one option read after the rosters: students of
		// the program all the same, at no institution and in no country.
		foreach ( WPCPM_Roster_Index::unlinked() as $row ) {
			$reduced = is_array( $row ) ? self::reduce( $row, $current, '', '' ) : null;

			if ( null !== $reduced ) {
				$rows[] = $reduced;
			}
		}

		return $rows;
	}

	/**
	 * One roster row reduced to what the figures read, or null for a status that counts nowhere.
	 *
	 * @param array    $row         A roster index row.
	 * @param string[] $current     The statuses the program counts as current.
	 * @param string   $institution The institution the row is counted at, or ''.
	 * @param string   $country     That institution's country, or ''.
	 * @return array|null As `rows()` answers each.
	 */
	private static function reduce( array $row, array $current, $institution, $country ) {
		$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

		if ( ! self::counts_as_student( $status ) ) {
			return null;
		}

		return array(
			'status'      => $status,
			'current'     => in_array( $status, $current, true ),
			'start'       => self::day( isset( $row['start'] ) ? $row['start'] : '' ),
			'end'         => self::day( isset( $row['end'] ) ? $row['end'] : '' ),
			'hours'       => self::hours( isset( $row['hours'] ) ? $row['hours'] : '' ),
			'teams'       => self::teams( isset( $row['team'] ) ? $row['team'] : '' ),
			'institution' => (string) $institution,
			'country'     => (string) $country,
		);
	}

	/**
	 * The figures over a set of rows, by the rules the class names.
	 *
	 * @param array[] $rows  As `rows()` answers them.
	 * @param string  $from  The period's first day.
	 * @param string  $until The period's last day.
	 * @return array The `COUNTS`, in order.
	 */
	private static function count( array $rows, $from, $until ) {
		$out          = array_fill_keys( self::COUNTS, 0 );
		$out['hours'] = 0.0;

		if ( ! self::valid( $from, $until ) ) {
			return $out;
		}

		$hours        = 0.0;
		$teams        = array();
		$institutions = array();
		$countries    = array();

		foreach ( $rows as $row ) {
			$start = $row['start'];
			$end   = $row['end'];

			if ( self::within( $start, $from, $until ) ) {
				++$out['started'];
			}

			if ( self::STATUS_GRADUATE === $row['status'] && self::within( '' !== $end ? $end : $start, $from, $until ) ) {
				++$out['finished'];
			}

			// Started by the period's last day, a row with no start day included, and not ended before
			// its first.
			if ( ( '' !== $start && strcmp( $start, $until ) > 0 ) || ( '' !== $end && strcmp( $end, $from ) < 0 ) ) {
				continue;
			}

			// No end day is still going only for a current status: a past status with no end day,
			// a 2024 graduate whose end was never written, is active only in the period it started in.
			if ( '' === $end && ! $row['current'] && ! self::within( $start, $from, $until ) ) {
				continue;
			}

			++$out['active'];
			$hours += $row['hours'];

			if ( ! empty( $row['teams'] ) ) {
				++$out['contributions'];

				foreach ( $row['teams'] as $team ) {
					$teams[ $team ] = true;
				}
			}

			// A student filed under no institution adds none.
			if ( '' !== $row['institution'] ) {
				$institutions[ $row['institution'] ] = true;
			}

			if ( '' !== $row['country'] ) {
				$countries[ $row['country'] ] = true;
			}
		}

		$out['hours']        = round( $hours, 1 );
		$out['teams']        = count( $teams );
		$out['institutions'] = count( $institutions );
		$out['countries']    = count( $countries );

		return $out;
	}

	/**
	 * The linked Active mentors, each with the students it guided whose start day is in the period.
	 *
	 * @param string $record Sponsor key.
	 * @param string $from   The period's first day, or '' to count nothing.
	 * @param string $until  The period's last day, or '' to count nothing.
	 * @return array[] Each `name`, `profile`, `students`.
	 */
	private static function mentors( $record, $from, $until ) {
		$linked = WPCPM_Sponsor_Mentors::linked( $record );
		$out    = array();

		foreach ( $linked['mentors'] as $mentor ) {
			$user_id  = isset( $mentor['user_id'] ) ? (int) $mentor['user_id'] : 0;
			$students = 0;

			foreach ( $user_id > 0 ? WPCPM_Mentors_Dashboard::get_mentees( $user_id ) : array() as $mentee ) {
				if ( ! is_array( $mentee ) || ! self::counts_as_student( isset( $mentee['status'] ) ? trim( (string) $mentee['status'] ) : '' ) ) {
					continue;
				}

				if ( self::within( self::day( isset( $mentee['start'] ) ? $mentee['start'] : '' ), $from, $until ) ) {
					++$students;
				}
			}

			$out[] = array(
				'name'     => isset( $mentor['name'] ) ? (string) $mentor['name'] : '',
				'profile'  => isset( $mentor['profile'] ) ? (string) $mentor['profile'] : '',
				'students' => $students,
			);
		}

		return $out;
	}

	/**
	 * The claims by month over the months a period touches, and the offers that drew them.
	 *
	 * @param string $record Sponsor key.
	 * @param string $from   The period's first day.
	 * @param string $until  The period's last day.
	 * @return array `months` (`Y-m` => claims, every month present) and `offers` (each `title`,
	 *               `claims`, `series`), the offers in the order the sponsor made them.
	 */
	private static function claims( $record, $from, $until ) {
		$keys   = self::month_keys( $from, $until );
		$months = array_fill_keys( $keys, 0 );
		$offers = array();
		$stats  = WPCPM_Sponsor_Claims::stats_for_months( $record, $keys );

		foreach ( $stats['offers'] as $offer ) {
			$series = array();

			foreach ( $keys as $key ) {
				$series[ $key ] = isset( $offer['series'][ $key ] ) ? (int) $offer['series'][ $key ] : 0;
			}

			$claims = array_sum( $series );

			// No claim in the period is no evidence the offer ran in it: an offer holds no dates of
			// its own, and the claims are the only thing here that does.
			if ( $claims < 1 ) {
				continue;
			}

			foreach ( $series as $key => $count ) {
				$months[ $key ] += $count;
			}

			$offers[] = array(
				'title'  => isset( $offer['title'] ) ? (string) $offer['title'] : '',
				'claims' => $claims,
				'series' => $series,
			);
		}

		return array(
			'months' => $months,
			'offers' => $offers,
		);
	}

	/**
	 * The sponsor's posts published in the period: those still published, and those the daily run
	 * has put dormant since, both by the day they were published. A pending post that was never
	 * published is in neither list.
	 *
	 * @param string $record Sponsor key.
	 * @param string $from   The period's first day.
	 * @param string $until  The period's last day.
	 * @return int
	 */
	private static function posts( $record, $from, $until ) {
		$counted = array();

		foreach ( array_merge( WPCPM_Sponsor_Posts::published_of( $record ), WPCPM_Sponsor_Posts::dormant_of( $record ) ) as $post ) {
			if ( $post instanceof WP_Post && self::within( self::day( $post->post_date ), $from, $until ) ) {
				$counted[ (int) $post->ID ] = true;
			}
		}

		return count( $counted );
	}

	/**
	 * Every `Y-m` month from the first day's to the last day's, oldest first.
	 *
	 * @param string $from  The period's first day.
	 * @param string $until The period's last day.
	 * @return string[]
	 */
	private static function month_keys( $from, $until ) {
		$keys  = array();
		$year  = (int) substr( $from, 0, 4 );
		$month = (int) substr( $from, 5, 2 );
		$last  = substr( $until, 0, 7 );

		do {
			$key    = sprintf( '%04d-%02d', $year, $month );
			$keys[] = $key;

			if ( ++$month > 12 ) {
				$month = 1;
				++$year;
			}
		} while ( strcmp( $key, $last ) < 0 );

		return $keys;
	}

	/**
	 * A track's name from its key, as the program map names the status that carries it.
	 *
	 * @param string $key A track key.
	 * @return string The key itself when no status carries it.
	 */
	private static function track_label( $key ) {
		foreach ( WPCPM_Program::labels() as $status => $label ) {
			if ( WPCPM_Program::track( $status ) === $key ) {
				return (string) $label;
			}
		}

		return $key;
	}

	/**
	 * Whether a status is a person the figures count: not one the roster never shows, and one who
	 * signed up.
	 *
	 * @param string $status A trimmed status.
	 * @return bool
	 */
	private static function counts_as_student( $status ) {
		return ! in_array( $status, WPCPM_Roster_Index::NEVER_SHOWN, true ) && ! in_array( $status, WPCPM_Cohort::NOT_SIGNED_UP, true );
	}

	/**
	 * Whether two values are a period: two real days, the first not after the last.
	 *
	 * @param string $from  The first day.
	 * @param string $until The last day.
	 * @return bool
	 */
	private static function valid( $from, $until ) {
		return WPCPM_Sponsor_Tiers::is_date( $from ) && WPCPM_Sponsor_Tiers::is_date( $until ) && strcmp( $from, $until ) <= 0;
	}

	/**
	 * Whether a day is in a period, both ends included; no day is in none.
	 *
	 * @param string $day   A `Y-m-d` day, or ''.
	 * @param string $from  The period's first day.
	 * @param string $until The period's last day.
	 * @return bool
	 */
	private static function within( $day, $from, $until ) {
		return '' !== $day && strcmp( $day, $from ) >= 0 && strcmp( $day, $until ) <= 0;
	}

	/**
	 * The leading `Y-m-d` of a value, when it is a real day: a date the base sends as a date and
	 * time is still its day, and "2026-02-31" is no day at all.
	 *
	 * @param mixed $value A date as a row stores it.
	 * @return string `Y-m-d`, or ''.
	 */
	private static function day( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})/', $value, $found ) && WPCPM_Sponsor_Tiers::is_date( $found[1] ) ? $found[1] : '';
	}

	/**
	 * Hours as a number: the row keeps the string the base sends, fractional for some students, and
	 * anything that is not a number is no hours rather than a guess.
	 *
	 * @param mixed $value The row's `hours`.
	 * @return float
	 */
	private static function hours( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return is_numeric( $value ) ? max( 0.0, (float) $value ) : 0.0;
	}

	/**
	 * The teams a row names. The sync joins a student's several linked teams with a comma, so a
	 * row naming two is two teams, not a third one spelled with a comma.
	 *
	 * @param mixed $value The row's `team`.
	 * @return string[]
	 */
	private static function teams( $value ) {
		$out = array();

		foreach ( explode( ',', is_scalar( $value ) ? (string) $value : '' ) as $team ) {
			$team = trim( $team );

			if ( '' !== $team ) {
				$out[] = $team;
			}
		}

		return array_values( array_unique( $out ) );
	}
}
