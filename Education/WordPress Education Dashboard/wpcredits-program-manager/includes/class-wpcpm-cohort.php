<?php
/**
 * A student's cohort: the half-year their start date falls in.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Semester keys, and the participation counts an institution reads off them.
 *
 * There is no cohort, term or intake column anywhere in the base, and exact start dates
 * fragment: the whole Students table has 41 distinct dates, one institution has 42 students
 * spread over 14 of them, and a real September intake scatters across weeks as paperwork
 * clears. Half-years collapse that cleanly. Measured on 2 September 2026, both boundaries
 * fall in troughs (June 7 rows against July 62; December 1 against January 15) and both
 * intake peaks sit inside one half, so a semester window puts an intake in one bucket
 * without anybody having to maintain a list of term dates.
 *
 * Institutions name the same months differently (February to June is *summer* in Krakow
 * and *spring* in the US), so a key is `YYYY-H1` or `YYYY-H2`, and a label spells out the
 * months. No label here ever carries a season word.
 *
 * All static, no state, beside `WPCPM_Program`. Rows are the roster index's rows: only
 * `status` and `start` are read, nothing else is assumed about them.
 */
class WPCPM_Cohort {

	/** The bucket for rows with no usable start date; a first-class key in every picker and count. */
	const NONE = 'none';

	/** Statuses that are not a person who signed up. `Interested` is a lead, not an enrolment. */
	const NOT_SIGNED_UP = array( 'SPAM', 'Duplicated', 'Interested' );

	/**
	 * The semester containing a start date, as `YYYY-H1` or `YYYY-H2`, or NONE.
	 *
	 * H1 is 1 January to 30 June, H2 is 1 July to 31 December. Read from the string, never
	 * through a timestamp: strtotime() on a date-only string lands at midnight in whatever
	 * timezone PHP has, and a student who started on 1 July could be filed under June on a
	 * site set west of UTC. Pattern AND checkdate(), so 2026-02-31 is NONE rather than March,
	 * and a datetime is NONE so a field type change surfaces as an empty cohort in the tests.
	 * Verified against the base on 2 September 2026: both boundaries fall between intakes.
	 *
	 * @param mixed $date A `Y-m-d` string, as the roster index stores `start`.
	 * @return string
	 */
	public static function key( $date ) {
		if ( ! is_scalar( $date ) ) {
			return self::NONE;
		}

		// Trimmed because a stray space is not a missing date, and filing a whole intake under
		// "No start date" for one would be the wrong kind of surprise. A datetime still fails
		// the pattern after trimming, which is the surprise this method is meant to give.
		$date = trim( (string) $date );

		// The `D` modifier makes `$` mean the very end of the string, as it does in is_key(),
		// where nothing is trimmed and a request value ending in a newline must not be a key.
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m ) ) {
			return self::NONE;
		}

		$year  = (int) $m[1];
		$month = (int) $m[2];
		$day   = (int) $m[3];

		if ( ! checkdate( $month, $day, $year ) ) {
			return self::NONE;
		}

		return sprintf( '%04d-H%d', $year, $month <= 6 ? 1 : 2 );
	}

	/**
	 * Whether a value is a cohort key: `YYYY-H1`, `YYYY-H2` or NONE.
	 *
	 * For request arguments, which is why it accepts anything and answers false for a
	 * non-string rather than casting: `wpcpm_cohort[]=` arrives as an array.
	 *
	 * @param mixed $value Whatever the request carried.
	 * @return bool
	 */
	public static function is_key( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		return self::NONE === $value || 1 === preg_match( '/^\d{4}-H[12]$/D', $value );
	}

	/**
	 * The calendar semester before a key: 2026-H1 becomes 2025-H2, 2026-H2 becomes 2026-H1.
	 *
	 * NONE has no predecessor, and neither does junk; both give an empty string so a caller
	 * can tell "nothing to compare against" from a semester that merely holds no rows.
	 *
	 * @param string $key A cohort key.
	 * @return string A key, or an empty string.
	 */
	public static function previous( $key ) {
		$parts = self::parts( $key );

		if ( null === $parts ) {
			return '';
		}

		if ( 2 === $parts['half'] ) {
			return $parts['year'] . '-H1';
		}

		$year = (int) $parts['year'] - 1;

		// Year 0000 is a key the pattern admits and checkdate() never produces; there is nothing
		// before it that is still a key.
		return $year < 0 ? '' : sprintf( '%04d-H2', $year );
	}

	/**
	 * The semester today falls in, on the site's clock.
	 *
	 * @return string
	 */
	public static function current() {
		return self::key( wp_date( 'Y-m-d' ) );
	}

	/**
	 * What a key is called on screen: the months spelled out, never a season.
	 *
	 * The year is printed as four plain digits rather than through number_format_i18n(),
	 * which would put a thousands separator into "2,026" on most locales.
	 *
	 * @param string $key A cohort key.
	 * @return string 'January to June 2026', 'July to December 2026', 'No start date', or an
	 *                empty string for a value that is not a key.
	 */
	public static function label( $key ) {
		if ( self::NONE === $key ) {
			return __( 'No start date', 'wpcredits-program-manager' );
		}

		$parts = self::parts( $key );

		if ( null === $parts ) {
			return '';
		}

		if ( 1 === $parts['half'] ) {
			/* translators: %s: the four-digit year. */
			return sprintf( __( 'January to June %s', 'wpcredits-program-manager' ), $parts['year'] );
		}

		/* translators: %s: the four-digit year. */
		return sprintf( __( 'July to December %s', 'wpcredits-program-manager' ), $parts['year'] );
	}

	/**
	 * The first and last day of a semester, as `Y-m-d` strings.
	 *
	 * Strings rather than timestamps for the reason key() gives: a day boundary is a calendar
	 * fact, and a timestamp would pin it to one timezone's midnight.
	 *
	 * @param string $key A cohort key.
	 * @return array{from: string, to: string} Both empty for NONE and for anything that is not a key.
	 */
	public static function range( $key ) {
		$parts = self::parts( $key );

		if ( null === $parts ) {
			return array(
				'from' => '',
				'to'   => '',
			);
		}

		if ( 1 === $parts['half'] ) {
			return array(
				'from' => $parts['year'] . '-01-01',
				'to'   => $parts['year'] . '-06-30',
			);
		}

		return array(
			'from' => $parts['year'] . '-07-01',
			'to'   => $parts['year'] . '-12-31',
		);
	}

	/**
	 * Chronological order for usort(), with NONE last.
	 *
	 * A byte comparison is the chronological one for these keys: four-digit years compare
	 * as numbers, and `H1` sorts before `H2` within a year. NONE is not a time, so it goes
	 * after every semester whichever way the caller reads the list.
	 *
	 * @param string $a A cohort key.
	 * @param string $b A cohort key.
	 * @return int -1, 0 or 1.
	 */
	public static function compare( $a, $b ) {
		$a_none = self::NONE === $a;
		$b_none = self::NONE === $b;

		if ( $a_none || $b_none ) {
			return ( $a_none ? 1 : 0 ) - ( $b_none ? 1 : 0 );
		}

		return strcmp( (string) $a, (string) $b ) <=> 0;
	}

	/**
	 * Participation counts for one cohort over index rows. The seven buckets sum to signed_up
	 * by construction, and the two-number comparison decision 11 allows is signed_up and
	 * graduated read off this array. Nothing deeper lives here, deliberately.
	 *
	 * A row belongs to the cohort when key() of its start date is the key asked for, so NONE
	 * counts the rows with no usable date. `graduated` is the exact status, not the loose
	 * regex `WPCPM_Student_Feedback::is_graduate()` uses for a screen, because a report says
	 * a number and the number has to be reproducible from the grid. `pending` is printed
	 * separately until open question 1 of the design spec says whether it counts as graduated.
	 * `other` exists so a status the base grows later shows up as a number rather than vanishing.
	 *
	 * @param array  $rows Roster index rows; only `status` and `start` are read.
	 * @param string $key  A cohort key.
	 * @return array{signed_up: int, graduated: int, pending: int, active: int, withdrawn: int, not_started: int, other: int}
	 */
	public static function participation( array $rows, $key ) {
		$counts = array(
			'signed_up'   => 0,
			'graduated'   => 0,
			'pending'     => 0,
			'active'      => 0,
			'withdrawn'   => 0,
			'not_started' => 0,
			'other'       => 0,
		);

		if ( ! self::is_key( $key ) ) {
			return $counts;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$start = isset( $row['start'] ) ? $row['start'] : '';

			if ( self::key( $start ) !== $key ) {
				continue;
			}

			$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

			if ( in_array( $status, self::NOT_SIGNED_UP, true ) ) {
				continue;
			}

			++$counts['signed_up'];
			++$counts[ self::bucket( $status ) ];
		}

		return $counts;
	}

	/**
	 * Which participation bucket a signed-up status falls in.
	 *
	 * The order matters only in that `Paused` is a track for this purpose: a paused student
	 * is still enrolled, and a report that filed them under `other` would understate the
	 * class. Statuses already in NOT_SIGNED_UP never reach here.
	 *
	 * @param string $status A trimmed Airtable status.
	 * @return string One of the participation() keys other than `signed_up`.
	 */
	private static function bucket( $status ) {
		if ( 'Graduate' === $status ) {
			return 'graduated';
		}

		if ( 'Pending graduation' === $status ) {
			return 'pending';
		}

		if ( 'Paused' === $status || WPCPM_Program::is_track( $status ) ) {
			return 'active';
		}

		if ( 'Dropped out' === $status || 'Fail' === $status ) {
			return 'withdrawn';
		}

		if ( 'Not moving forward' === $status ) {
			return 'not_started';
		}

		return 'other';
	}

	/**
	 * A semester key taken apart, or null for NONE and for anything that is not a key.
	 *
	 * @param string $key A cohort key.
	 * @return array{year: string, half: int}|null The year as its four digits, the half as 1 or 2.
	 */
	private static function parts( $key ) {
		if ( ! is_string( $key ) || 1 !== preg_match( '/^(\d{4})-H([12])$/D', $key, $m ) ) {
			return null;
		}

		return array(
			'year' => $m[1],
			'half' => (int) $m[2],
		);
	}
}
