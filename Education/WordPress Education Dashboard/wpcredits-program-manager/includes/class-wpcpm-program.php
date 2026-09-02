<?php
/**
 * The program a student is on, as people say it rather than as Airtable stores it.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates Airtable's `Status` values into program names and course links.
 *
 * Airtable calls the two tracks `In Sensei` and `In Sensei 50h`, which is internal
 * shorthand from an earlier name for the program. Students and mentors know them as
 * the 150-hour and 50-hour WordPress Credits Program, and that is what every screen
 * says. The raw values stay the storage format — the sync matches on them, the
 * settings list them, and nothing here rewrites what is saved.
 *
 * Kept in one place because the mapping is needed on both dashboards and in two
 * different shapes (a label and a course URL), and two copies would disagree.
 */
class WPCPM_Program {

	/** Airtable status for the 150-hour track. */
	const STATUS_150H = 'In Sensei';

	/** Airtable status for the 50-hour track. */
	const STATUS_50H = 'In Sensei 50h';

	/** Airtable status for the developer track. */
	const STATUS_DEV = 'Developer Track';

	/**
	 * The program name for a status, or the status itself if it is not a track.
	 *
	 * `Graduate` and `Dropped out` pass straight through: they are the *state* of a
	 * student rather than a program, and inventing a display name for them would put
	 * a course label on somebody who has finished.
	 *
	 * @param string $status Airtable status.
	 * @return string
	 */
	public static function label( $status ) {
		$status = trim( (string) $status );
		$labels = self::labels();

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * The status → program name map.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		$labels = array(
			self::STATUS_150H => __( 'WordPress Credits Program 150h', 'wpcredits-program-manager' ),
			self::STATUS_50H  => __( 'WordPress Credits Program 50h', 'wpcredits-program-manager' ),
			// Maps to itself, which is not a redundant entry: `is_track()` tests membership of
			// this map, and that is what gates the feedback surveys and the course button. Remove
			// the row as tidying and the surveys go quiet for this track, silently. The other two
			// statuses are internal shorthand and need translating; this one is already the name
			// students and mentors use, so screen and base say the same thing.
			self::STATUS_DEV  => __( 'Developer Track', 'wpcredits-program-manager' ),
		);

		/**
		 * Filter the program names shown for each Airtable status.
		 *
		 * @param array<string, string> $labels Status to display name.
		 */
		return (array) apply_filters( 'wpcpm_program_labels', $labels );
	}

	/**
	 * The Learn WordPress course for a status.
	 *
	 * @param string $status Airtable status.
	 * @return string URL, or an empty string when the status has no course.
	 */
	public static function course_url( $status ) {
		$status  = trim( (string) $status );
		$courses = self::courses();

		return isset( $courses[ $status ] ) ? $courses[ $status ] : '';
	}

	/**
	 * The status → course URL map.
	 *
	 * @return array<string, string>
	 */
	public static function courses() {
		$courses = array(
			self::STATUS_150H => 'https://learn.wordpress.org/course/wordpress-credits/',
			self::STATUS_50H  => 'https://learn.wordpress.org/course/50-hours-wordpress-credits/',
			self::STATUS_DEV  => 'https://learn.wordpress.org/course/wordpress-credits-developer-track/',
		);

		/**
		 * Filter the Learn WordPress course for each Airtable status.
		 *
		 * @param array<string, string> $courses Status to course URL.
		 */
		return (array) apply_filters( 'wpcpm_program_courses', $courses );
	}

	/**
	 * Whether a status is one of the tracks, as opposed to a finished state.
	 *
	 * @param string $status Airtable status.
	 * @return bool
	 */
	public static function is_track( $status ) {
		return isset( self::labels()[ trim( (string) $status ) ] );
	}

	/**
	 * Which track a status is, as a short key.
	 *
	 * **This replaced an `is_50h` boolean carried on every synced row.** Two tracks fit a boolean;
	 * three do not, and a second flag beside the first would give four states for three tracks with
	 * "both true" representable and meaningless. The status is already stored on every row by both
	 * syncs, and the boolean was derived from it anyway — so the track is derived where it is
	 * needed and nothing has to be kept in step. A fourth track is one entry in this map.
	 *
	 * @param string $status Airtable status.
	 * @return string `150h`, `50h`, `dev`, or an empty string for a finished state.
	 */
	public static function track( $status ) {
		$tracks = array(
			self::STATUS_150H => '150h',
			self::STATUS_50H  => '50h',
			self::STATUS_DEV  => 'dev',
		);

		$status = trim( (string) $status );

		return isset( $tracks[ $status ] ) ? $tracks[ $status ] : '';
	}

	/**
	 * The badge modifier for a status, as the dashboards paint it.
	 *
	 * The track where there is one, so a fourth track is one entry in `track()` and nothing
	 * here. Paused and Pending graduation are on no track and are not finished either: they
	 * are a student who is still the mentor's, and painting them in the 150-hour colour said
	 * they were still working when the point of the status is that they are not. Every other
	 * status keeps the plain badge.
	 *
	 * @param string $status Airtable status.
	 * @return string A modifier for `wpcpm-badge--`, or an empty string for the plain badge.
	 */
	public static function badge( $status ) {
		$track = self::track( $status );

		if ( '' !== $track ) {
			return '150h' === $track ? 'sensei' : $track;
		}

		$others = array(
			'Paused'              => 'paused',
			'Pending graduation'  => 'pending',
		);

		$status = trim( (string) $status );

		return isset( $others[ $status ] ) ? $others[ $status ] : '';
	}
}
