<?php
/**
 * The cohort timeline: how many students are on the program each month ahead, and whether the
 * active mentors have the places for them (design of 10 November 2026).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One reader and one set of rules, so the Administrator Dashboard's card and the timeline view
 * cannot disagree.
 *
 * This file reads and computes; it draws nothing. `WPCPM_Cohort_Timeline_View` draws.
 *
 * `compute()` holds every rule and reads nothing, so `bin/test-cohort-timeline.php` pins each one on
 * plain arrays; `classify_row()` and `classify_registration()` turn a roster row and a site
 * registration into the one shape `compute()` takes; `read()` reads the site once. Dates are read off
 * their `Y-m-d` strings, never through a timestamp, for the reason `WPCPM_Cohort::key()` gives: a
 * student who starts on 1 March must not be filed under February on a site set west of UTC.
 *
 * Counts only: no name, no email and no status leaves this class. An intake is described by its
 * institutions' names and its tracks, which an Administrator already reads on every roster.
 */
final class WPCPM_Cohort_Timeline {

	/** A student already in progress: a tracked active status, start on or before today. */
	const KIND_CONTINUING = 'continuing';

	/** A confirmed student who has not started yet: a tracked active status, start after today. */
	const KIND_STARTING = 'starting';

	/** A registration a program manager has not placed yet: no status, or a site registration. */
	const KIND_REVIEW = 'review';

	/** The kinds, in the order a column stacks them from its baseline. */
	const KINDS = array( self::KIND_CONTINUING, self::KIND_STARTING, self::KIND_REVIEW );

	/** How many months the timeline shows, the current one first. */
	const MONTHS = 12;

	/** How many months the card and the summary look ahead. */
	const HORIZON = 6;

	/**
	 * The months a window with no usable end date is given (builder's call, spec section 3): a row
	 * that never ends would hold a mentor's place for ever.
	 */
	const DEFAULT_MONTHS = 4;

	/** The setting's default and its bounds. */
	const DEFAULT_RATIO = 3;
	const MIN_RATIO     = 1;
	const MAX_RATIO     = 20;

	/** A month whose whole load fits the places. */
	const STATE_OK = 'ok';

	/** A month whose confirmed load fits and whose registrations in review do not. */
	const STATE_TIGHT = 'tight';

	/** A month whose confirmed load alone is more than the places. */
	const STATE_SHORT = 'short';

	/**
	 * The report the card and the view draw, read from the site once.
	 *
	 * @return array What `compute()` returns, plus `read`: the oldest roster read time, 0 for never.
	 */
	public static function report() {
		$input = self::read();
		$out   = self::compute( $input['students'], $input['mentors'], $input['ratio'], $input['today'] );

		$out['read'] = $input['read'];

		return $out;
	}

	/**
	 * What `compute()` takes, read from the site.
	 *
	 * The roster index is read once per institution, as `WPCPM_Administrators_Cards::programs()` reads
	 * it, plus the rows that name no institution; a Students record held twice is counted once. A
	 * site registration is counted while it is not in the index yet: waiting, failed, or written to a
	 * Students record the index does not hold. Test institutions are left out of every number.
	 *
	 * @return array{students: array[], mentors: int, ratio: int, today: string, read: int}
	 */
	public static function read() {
		$today   = (string) wp_date( 'Y-m-d' );
		$tracked = WPCPM_Mentors_Sync::tracked_statuses();
		$active  = isset( $tracked['active'] ) ? (array) $tracked['active'] : array();
		$tests   = array_fill_keys( WPCPM_Test_Institutions::test_ids(), true );
		$names   = array();
		$records = array();

		foreach ( WPCPM_Institutions_Index::rows() as $record => $row ) {
			$names[ (string) $record ] = is_array( $row ) && isset( $row['name'] ) && is_scalar( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		}

		$institutions = array_unique( array_merge( array_keys( $names ), array_map( 'strval', array_keys( (array) WPCPM_Roster_Index::counts()['institutions'] ) ) ) );
		$rosters      = array();
		$read         = 0;

		foreach ( $institutions as $record ) {
			$record = (string) $record;

			if ( isset( $tests[ $record ] ) ) {
				continue;
			}

			$envelope  = WPCPM_Roster_Index::read( $record );
			$this_read = isset( $envelope['read'] ) ? (int) $envelope['read'] : 0;

			if ( $this_read > 0 && ( 0 === $read || $this_read < $read ) ) {
				$read = $this_read;
			}

			$rosters[] = array(
				'name' => isset( $names[ $record ] ) ? $names[ $record ] : '',
				'rows' => isset( $envelope['rows'] ) && is_array( $envelope['rows'] ) ? $envelope['rows'] : array(),
			);
		}

		$rosters[] = array(
			'name' => '',
			'rows' => WPCPM_Roster_Index::unlinked(),
		);

		$students = array();

		foreach ( $rosters as $roster ) {
			foreach ( $roster['rows'] as $key => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$id = isset( $row['record_id'] ) && is_scalar( $row['record_id'] ) && '' !== trim( (string) $row['record_id'] ) ? trim( (string) $row['record_id'] ) : (string) $key;

				if ( isset( $records[ $id ] ) ) {
					continue;
				}

				$records[ $id ] = true;
				$student        = self::classify_row( $row, $active, $today, $roster['name'] );

				if ( null !== $student ) {
					$students[] = $student;
				}
			}
		}

		if ( class_exists( 'WPCPM_Student_Registration' ) ) {
			$states = array( WPCPM_Student_Registration::STATE_WAITING, WPCPM_Student_Registration::STATE_FAILED, WPCPM_Student_Registration::STATE_WRITTEN );

			foreach ( WPCPM_Student_Registration::in_states( $states ) as $post ) {
				if ( WPCPM_Student_Registration::is_test_registration( $post, array_keys( $tests ) ) ) {
					continue;
				}

				$outcome = WPCPM_Student_Registration::outcome_of( $post );
				$record  = isset( $outcome['record'] ) && is_scalar( $outcome['record'] ) ? trim( (string) $outcome['record'] ) : '';

				if ( '' !== $record && isset( $records[ $record ] ) ) {
					continue;
				}

				$fields  = WPCPM_Student_Registration::fields_of( $post );
				$school  = isset( $fields['institution'] ) && is_scalar( $fields['institution'] ) ? trim( (string) $fields['institution'] ) : '';
				$student = self::classify_registration( $fields, $today, isset( $names[ $school ] ) ? $names[ $school ] : '' );

				if ( null !== $student ) {
					$students[] = $student;
				}
			}
		}

		$mentors = 0;

		if ( class_exists( 'WPCPM_Mentor_Engagement' ) ) {
			$summary = WPCPM_Mentor_Engagement::report()['summary'];
			$mentors = isset( $summary['active'] ) ? (int) $summary['active'] : 0;
		}

		return array(
			'students' => $students,
			'mentors'  => $mentors,
			'ratio'    => self::ratio(),
			'today'    => $today,
			'read'     => $read,
		);
	}

	/**
	 * Students per mentor, as the setting holds it, inside its bounds.
	 *
	 * @return int
	 */
	public static function ratio() {
		$ratio = (int) WPCPM_Settings::get_value( 'students_per_mentor', self::DEFAULT_RATIO );

		return max( self::MIN_RATIO, min( self::MAX_RATIO, $ratio ) );
	}

	/**
	 * A roster row as a student on the timeline, or null for a row the timeline leaves out.
	 *
	 * Left out: `SPAM` and `Duplicated` (`WPCPM_Roster_Index::NEVER_SHOWN`), `Interested` (a lead,
	 * `WPCPM_Cohort::NOT_SIGNED_UP`), a past or any other status, and a row with no usable start date.
	 * A tracked active status is continuing or starting by its start date against today; a row with
	 * no status is a registration waiting for a program manager.
	 *
	 * @param array  $row         A roster index row.
	 * @param array  $active      The tracked active statuses.
	 * @param string $today       Today, `Y-m-d`.
	 * @param string $institution The institution's name, or ''.
	 * @return array|null `kind`, `start`, `end`, `estimated`, `overdue`, `track`, `mentor`, `institution`.
	 */
	public static function classify_row( array $row, array $active, $today, $institution = '' ) {
		$status = isset( $row['status'] ) && is_scalar( $row['status'] ) ? trim( (string) $row['status'] ) : '';

		if ( in_array( $status, WPCPM_Roster_Index::NEVER_SHOWN, true ) || in_array( $status, WPCPM_Cohort::NOT_SIGNED_UP, true ) ) {
			return null;
		}

		if ( '' === $status ) {
			$kind = self::KIND_REVIEW;
		} elseif ( in_array( $status, $active, true ) ) {
			$start = self::day( isset( $row['start'] ) ? $row['start'] : '' );
			$kind  = ( '' !== $start && $start > (string) $today ) ? self::KIND_STARTING : self::KIND_CONTINUING;
		} else {
			return null;
		}

		return self::student(
			$kind,
			isset( $row['start'] ) ? $row['start'] : '',
			isset( $row['end'] ) ? $row['end'] : '',
			$today,
			array(
				'track'       => WPCPM_Program::track( $status ),
				'mentor'      => self::KIND_REVIEW !== $kind && ! WPCPM_Roster_Index::is_waiting( $row ),
				'institution' => (string) $institution,
			)
		);
	}

	/**
	 * A site registration as a student on the timeline: in review, dated by its preferred start and
	 * estimated end, with no mentor and no track yet.
	 *
	 * @param array  $fields      The registration's answers.
	 * @param string $today       Today, `Y-m-d`.
	 * @param string $institution The institution's name, or ''.
	 * @return array|null
	 */
	public static function classify_registration( array $fields, $today, $institution = '' ) {
		return self::student(
			self::KIND_REVIEW,
			isset( $fields['start'] ) ? $fields['start'] : '',
			isset( $fields['end'] ) ? $fields['end'] : '',
			$today,
			array(
				'track'       => '',
				'mentor'      => false,
				'institution' => (string) $institution,
			)
		);
	}

	/**
	 * The one shape `compute()` takes, with the window settled.
	 *
	 * No usable start: null. No usable end, or an end before the start: DEFAULT_MONTHS after the start,
	 * marked `estimated`. A student in progress whose end has passed is still the mentor's, so the
	 * window is held open to today and marked `overdue`. A window in review that is already over is
	 * a registration nobody will place, and is left out.
	 *
	 * @param string $kind  One of KINDS.
	 * @param mixed  $start The start date.
	 * @param mixed  $end   The end date.
	 * @param string $today Today, `Y-m-d`.
	 * @param array  $extra `track`, `mentor`, `institution`.
	 * @return array|null
	 */
	private static function student( $kind, $start, $end, $today, array $extra ) {
		$start = self::day( $start );

		if ( '' === $start ) {
			return null;
		}

		$end       = self::day( $end );
		$estimated = false;
		$overdue   = false;

		if ( '' === $end || $end < $start ) {
			$end       = self::add_months( $start, self::DEFAULT_MONTHS );
			$estimated = true;
		}

		if ( $end < (string) $today ) {
			if ( self::KIND_CONTINUING !== $kind ) {
				return null;
			}

			$end     = (string) $today;
			$overdue = true;
		}

		return array(
			'kind'        => $kind,
			'start'       => $start,
			'end'         => $end,
			'estimated'   => $estimated,
			'overdue'     => $overdue,
			'track'       => (string) $extra['track'],
			'mentor'      => (bool) $extra['mentor'],
			'institution' => (string) $extra['institution'],
		);
	}

	/**
	 * Every number the card and the view draw. Reads nothing.
	 *
	 * @param array[] $students What `classify_row()` and `classify_registration()` return.
	 * @param int     $mentors  The active mentors.
	 * @param int     $ratio    Students per mentor.
	 * @param string  $today    Today, `Y-m-d`.
	 * @param int     $count    How many months, the current one first.
	 * @return array{months: array[], bands: array[], summary: array, mentors: int, ratio: int, places: int, today: string}
	 */
	public static function compute( array $students, $mentors, $ratio, $today, $count = self::MONTHS ) {
		$mentors = max( 0, (int) $mentors );
		$ratio   = max( self::MIN_RATIO, min( self::MAX_RATIO, (int) $ratio ) );
		$places  = $mentors * $ratio;
		$first   = substr( (string) $today, 0, 7 ) . '-01';
		$months  = array();
		$count   = max( 1, (int) $count );

		for ( $i = 0; $i < $count; $i++ ) {
			$from = self::add_months( $first, $i );
			$key  = substr( $from, 0, 7 );

			$months[ $key ] = array(
				'key'          => $key,
				'from'         => $from,
				'to'           => self::last_day( $from ),
				'cohort'       => WPCPM_Cohort::key( $from ),
				'load'         => array_fill_keys( self::KINDS, 0 ),
				'confirmed'    => 0,
				'total'        => 0,
				'starts'       => array(
					'confirmed' => 0,
					'review'    => 0,
				),
				'ending'       => 0,
				'unassigned'   => 0,
				'tracks'       => array(),
				'institutions' => array(),
			);
		}

		$estimated = 0;
		$overdue   = 0;

		foreach ( $students as $student ) {
			if ( ! is_array( $student ) || ! isset( $student['kind'], $student['start'], $student['end'] ) || ! in_array( $student['kind'], self::KINDS, true ) ) {
				continue;
			}

			$kind    = (string) $student['kind'];
			$review  = self::KIND_REVIEW === $kind;
			$counted = false;

			foreach ( $months as $key => $month ) {
				if ( $student['start'] > $month['to'] || $student['end'] < $month['from'] ) {
					continue;
				}

				$counted = true;
				++$months[ $key ]['load'][ $kind ];
				++$months[ $key ]['total'];

				if ( ! $review ) {
					++$months[ $key ]['confirmed'];
				}

				if ( $review || empty( $student['mentor'] ) ) {
					++$months[ $key ]['unassigned'];
				}

				if ( substr( $student['start'], 0, 7 ) === $key && $student['start'] > (string) $today ) {
					++$months[ $key ]['starts'][ $review ? 'review' : 'confirmed' ];

					$track = '' !== (string) $student['track'] ? (string) $student['track'] : '';
					$name  = isset( $student['institution'] ) ? trim( (string) $student['institution'] ) : '';

					$months[ $key ]['tracks'][ $track ]      = ( isset( $months[ $key ]['tracks'][ $track ] ) ? $months[ $key ]['tracks'][ $track ] : 0 ) + 1;
					$months[ $key ]['institutions'][ $name ] = ( isset( $months[ $key ]['institutions'][ $name ] ) ? $months[ $key ]['institutions'][ $name ] : 0 ) + 1;
				}

				if ( ! $review && empty( $student['overdue'] ) && substr( $student['end'], 0, 7 ) === $key ) {
					++$months[ $key ]['ending'];
				}
			}

			if ( $counted && ! empty( $student['estimated'] ) ) {
				++$estimated;
			}

			if ( $counted && ! empty( $student['overdue'] ) ) {
				++$overdue;
			}
		}

		foreach ( $months as $key => $month ) {
			arsort( $months[ $key ]['institutions'] );
			arsort( $months[ $key ]['tracks'] );

			$months[ $key ]['places']            = $places;
			$months[ $key ]['balance_confirmed'] = $places - $month['confirmed'];
			$months[ $key ]['balance_total']     = $places - $month['total'];
			$months[ $key ]['needed']            = (int) ceil( $month['total'] / $ratio );
			$months[ $key ]['state']             = self::state( $month['confirmed'], $month['total'], $places );
		}

		$months = array_values( $months );

		return array(
			'months'  => $months,
			'bands'   => self::bands( $months ),
			'summary' => self::summary( $months, $mentors, $ratio, $places, $estimated, $overdue ),
			'mentors' => $mentors,
			'ratio'   => $ratio,
			'places'  => $places,
			'today'   => (string) $today,
		);
	}

	/**
	 * A month's state against the places.
	 *
	 * @param int $confirmed The confirmed load.
	 * @param int $total     The whole load.
	 * @param int $places    The places.
	 * @return string One of the STATE_* values.
	 */
	public static function state( $confirmed, $total, $places ) {
		if ( (int) $confirmed > (int) $places ) {
			return self::STATE_SHORT;
		}

		return (int) $total > (int) $places ? self::STATE_TIGHT : self::STATE_OK;
	}

	/**
	 * The half-year bands over the months: each cohort key and how many of the months it spans.
	 *
	 * @param array[] $months The months, in order.
	 * @return array[] `key`, `span`.
	 */
	private static function bands( array $months ) {
		$bands = array();

		foreach ( $months as $month ) {
			$last = count( $bands ) - 1;

			if ( $last >= 0 && $bands[ $last ]['key'] === $month['cohort'] ) {
				++$bands[ $last ]['span'];
				continue;
			}

			$bands[] = array(
				'key'  => $month['cohort'],
				'span' => 1,
			);
		}

		return $bands;
	}

	/**
	 * The next HORIZON months in a few numbers.
	 *
	 * @param array[] $months    The months, in order.
	 * @param int     $mentors   Active mentors.
	 * @param int     $ratio     Students per mentor.
	 * @param int     $places    The places.
	 * @param int     $estimated Students whose end date was estimated.
	 * @param int     $overdue   Students in progress past their end date.
	 * @return array
	 */
	private static function summary( array $months, $mentors, $ratio, $places, $estimated, $overdue ) {
		$ahead          = array_slice( $months, 0, self::HORIZON );
		$out            = array(
			'horizon'            => count( $ahead ),
			'starting_confirmed' => 0,
			'starting_review'    => 0,
			'peak_month'         => '',
			'peak_load'          => 0,
			'first_short'        => '',
			'first_tight'        => '',
			'state'              => self::STATE_OK,
			'needed'             => 0,
			'recruit'            => 0,
			'recruit_confirmed'  => 0,
			'mentors'            => (int) $mentors,
			'ratio'              => (int) $ratio,
			'places'             => (int) $places,
			'estimated'          => (int) $estimated,
			'overdue'            => (int) $overdue,
		);
		$peak_confirmed = 0;

		foreach ( $ahead as $month ) {
			$out['starting_confirmed'] += $month['starts']['confirmed'];
			$out['starting_review']    += $month['starts']['review'];

			if ( $month['total'] > $out['peak_load'] ) {
				$out['peak_load']  = $month['total'];
				$out['peak_month'] = $month['key'];
			}

			$peak_confirmed = max( $peak_confirmed, $month['confirmed'] );

			if ( self::STATE_SHORT === $month['state'] && '' === $out['first_short'] ) {
				$out['first_short'] = $month['key'];
			}

			if ( self::STATE_OK !== $month['state'] && '' === $out['first_tight'] ) {
				$out['first_tight'] = $month['key'];
			}
		}

		if ( '' !== $out['first_short'] ) {
			$out['state'] = self::STATE_SHORT;
		} elseif ( '' !== $out['first_tight'] ) {
			$out['state'] = self::STATE_TIGHT;
		}

		$out['needed']            = (int) ceil( $out['peak_load'] / $ratio );
		$out['recruit']           = max( 0, $out['needed'] - (int) $mentors );
		$out['recruit_confirmed'] = max( 0, (int) ceil( $peak_confirmed / $ratio ) - (int) $mentors );

		return $out;
	}

	/**
	 * A `Y-m-d` date as given, or '' for anything else: pattern and checkdate(), as `WPCPM_Cohort::key()`.
	 *
	 * @param mixed $value A date.
	 * @return string
	 */
	public static function day( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * A `Y-m-d` date some months later, the day held where the month has it and clamped where it
	 * does not (31 January plus one month is 28 or 29 February, never 3 March).
	 *
	 * @param string $date   A `Y-m-d` date.
	 * @param int    $months How many months.
	 * @return string
	 */
	public static function add_months( $date, $months ) {
		$year  = (int) substr( $date, 0, 4 );
		$month = (int) substr( $date, 5, 2 ) - 1 + (int) $months;
		$day   = (int) substr( $date, 8, 2 );

		$year += intdiv( $month, 12 );
		$month = $month % 12 + 1;
		$days  = (int) gmdate( 't', gmmktime( 12, 0, 0, $month, 1, $year ) );

		return sprintf( '%04d-%02d-%02d', $year, $month, min( $day, $days ) );
	}

	/**
	 * The last day of a date's month.
	 *
	 * @param string $date A `Y-m-d` date.
	 * @return string
	 */
	public static function last_day( $date ) {
		$year  = (int) substr( $date, 0, 4 );
		$month = (int) substr( $date, 5, 2 );

		return sprintf( '%04d-%02d-%02d', $year, $month, (int) gmdate( 't', gmmktime( 12, 0, 0, $month, 1, $year ) ) );
	}
}
