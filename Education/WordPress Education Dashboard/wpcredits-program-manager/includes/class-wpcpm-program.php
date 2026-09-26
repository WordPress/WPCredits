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
 * Translates Airtable's `Status` values into program names, course links, hours and track keys.
 *
 * Airtable calls the two first tracks `In Sensei` and `In Sensei 50h`, which is internal
 * shorthand from an earlier name for the program. Students and mentors know them as
 * the 150-hour and 50-hour WordPress Credits Program, and that is what every screen
 * says. The raw values stay the storage format - the sync matches on them, the
 * settings list them, and nothing here rewrites what is saved.
 *
 * **The five maps hold no rows of their own.** Each starts empty and its filter fills it from the
 * tracks the site runs, compiled by the Track Builder (`WPCPM_Tracks`), the program's four
 * original tracks among them. Kept in one place because the mapping is needed on every dashboard
 * and in several shapes (a name, a course, hours, a key), and two copies would disagree.
 *
 * The four statuses below are the program's four original tracks'. `WPCPM_Tracks::RESERVED_PAIRS`
 * and `RESERVED_LABELS` read them, to lock each track to its key; the settings' default
 * "Currently mentoring" list and `WPCPM_Institutions::AUTOMATION_STATUSES` spell the same four,
 * and the syncs match students on them.
 */
class WPCPM_Program {

	/** Airtable status for the 150-hour track. */
	const STATUS_150H = 'In Sensei';

	/** Airtable status for the 50-hour track. */
	const STATUS_50H = 'In Sensei 50h';

	/** Airtable status for the developer track. */
	const STATUS_DEV = 'Developer Track';

	/** Airtable status for the designer track. */
	const STATUS_DESIGN = 'Designer Track';

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
	 * The status → program name map, one row per track the site runs.
	 *
	 * **A track is a row here, even one whose name is its status**, as the Developer and Designer
	 * Tracks' are: `is_track()` tests membership of this map, and that is what gates the feedback
	 * surveys and the course button, so a track missing from it goes quiet, silently. The rows are
	 * the compiled tracks' names (`WPCPM_Tracks::filter_labels()`), in the order the index keeps
	 * them.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		/**
		 * Filter the program names shown for each Airtable status.
		 *
		 * The Track Builder fills it with its compiled tracks, the map's only rows.
		 *
		 * @param array<string, string> $labels Status to display name: empty, for the tracks to fill.
		 */
		return (array) apply_filters( 'wpcpm_program_labels', array() );
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
	 * The status → course URL map: the Learn course each track's definition names.
	 *
	 * @return array<string, string>
	 */
	public static function courses() {
		/**
		 * Filter the Learn WordPress course for each Airtable status.
		 *
		 * The Track Builder fills it with its compiled tracks' courses, the map's only rows.
		 *
		 * @param array<string, string> $courses Status to course URL: empty, for the tracks to fill.
		 */
		return (array) apply_filters( 'wpcpm_program_courses', array() );
	}

	/**
	 * The Learn WordPress course post ID for a status.
	 *
	 * @param string $status Airtable status.
	 * @return int Post ID, or 0 when the status has no course.
	 */
	public static function course_id( $status ) {
		$status = trim( (string) $status );
		$ids    = self::course_ids();

		return isset( $ids[ $status ] ) ? (int) $ids[ $status ] : 0;
	}

	/**
	 * The status => Learn course post ID map.
	 *
	 * The same courses as `courses()`, by the ID Learn gives each: `wp/v2/courses?slug=` on
	 * learn.wordpress.org answers it for the slug in the course's address, and a track's definition
	 * keeps the ID its link last resolved to. Kept beside the links rather than derived from them,
	 * because the join with Learn is by ID - the public course structure at
	 * `sensei-internal/v1/course-structure/<id>` and the Learn Link design both key on it - and a
	 * request to Learn on every page to turn a link into an ID is a request nothing should wait for.
	 *
	 * @return array<string, int>
	 */
	public static function course_ids() {
		/**
		 * Filter the Learn WordPress course post ID for each Airtable status.
		 *
		 * The Track Builder fills it with its compiled tracks' courses (1.100.0), the map's only
		 * rows.
		 *
		 * @param array<string, int> $ids Status to course post ID: empty, for the tracks to fill.
		 */
		return (array) apply_filters( 'wpcpm_program_course_ids', array() );
	}

	/**
	 * How many hours a status is worked towards, or 0 for no target.
	 *
	 * @param string $status Airtable status.
	 * @return int Hours, or 0 when this status is worked to no target at all.
	 */
	public static function hours_target( $status ) {
		$targets = self::hours_targets();
		$status  = trim( (string) $status );

		return isset( $targets[ $status ] ) ? (int) $targets[ $status ] : 0;
	}

	/**
	 * The status to hours map: the hours target each track's definition names.
	 *
	 * **The Developer Track is 0 and that is a value, not a gap.** It is worked to a body of
	 * merged contributions rather than to a clock, so a page that printed "12 of 150" for one
	 * of its students would be inventing a denominator the program does not have. Everything
	 * that shows hours asks `has_hours_target()` first and prints "12 h" for this track.
	 *
	 * **The Designer Track is 150, and the contrast with the Developer Track is the point.** Its
	 * Learn page states 150 hours, so its students are worked to that clock and their pages say
	 * "12 of 150" like the 150-hour track's. Two tracks added a fortnight apart answer this
	 * question differently, which is why the answer is each track's own rather than a rule about
	 * tracks, and why neither figure is inherited from the other.
	 *
	 * **A track may be absent from this map entirely, and nothing is to require otherwise.**
	 * Not every track the program adds will count hours, so a missing row means no target, the
	 * same answer the Developer Track's explicit 0 gives. Anything that treats a track's
	 * absence here as a defect - a guard, a validation, an assertion that the two maps have
	 * the same keys - turns hours into a condition of adding a track, which they are not.
	 *
	 * @return array<string, int>
	 */
	public static function hours_targets() {
		/**
		 * Filter the hours each Airtable status is worked towards.
		 *
		 * The Track Builder fills it with its compiled tracks' targets, the map's only rows.
		 *
		 * @param array<string, int> $targets Status to hours, 0 meaning no target: empty, for the
		 *                                    tracks to fill.
		 */
		return (array) apply_filters( 'wpcpm_program_hours_targets', array() );
	}

	/**
	 * Whether a status is worked towards a number of hours at all.
	 *
	 * A status this map has never heard of answers no, the same as the Developer Track does,
	 * because both mean "do not print a denominator" and that is the only question a caller
	 * asks here. Callers that need to tell an unknown status from a known one without a target
	 * have `is_track()` for it, which is the question `labels()` answers.
	 *
	 * @param string $status Airtable status.
	 * @return bool
	 */
	public static function has_hours_target( $status ) {
		return self::hours_target( $status ) > 0;
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
	 * syncs, and the boolean was derived from it anyway - so the track is derived where it is
	 * needed and nothing has to be kept in step. A track is one entry in this map, which its
	 * definition fills.
	 *
	 * @param string $status Airtable status.
	 * @return string The key of the track holding the status (`150h`, `50h`, `dev` and `design` for
	 *                the four original tracks), or an empty string for a status no track holds.
	 */
	public static function track( $status ) {
		/**
		 * Filter the short key each Airtable status is a track under.
		 *
		 * The Track Builder fills it with its compiled tracks (1.100.0), beside
		 * `wpcpm_program_labels`, the map's only rows. The key is what
		 * `WPCPM_Student_Report_Form::fields()`, `badge()` and the Administrator Dashboard's tiles
		 * are keyed on, so a status that reaches `labels()` without reaching this map is a track
		 * with no form of its own: `fields()` answers the 150-hour track's form for a key no track
		 * holds.
		 *
		 * @param array<string, string> $tracks Status to track key: empty, for the tracks to fill.
		 */
		$tracks = (array) apply_filters( 'wpcpm_program_tracks', array() );

		$status = trim( (string) $status );

		return isset( $tracks[ $status ] ) ? (string) $tracks[ $status ] : '';
	}

	/**
	 * The badge modifier for a status, as the dashboards paint it.
	 *
	 * The track where there is one, so a new track is one entry in `track()` and nothing
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

		$others = self::states();

		$status = trim( (string) $status );

		return isset( $others[ $status ] ) ? $others[ $status ] : '';
	}

	/**
	 * The two states on no track, with the chip each is painted.
	 *
	 * Paused and Pending graduation are still their mentor's students, so they are painted apart
	 * from both a working track and the plain finished badge. Public since 1.100.0 because the
	 * Track Builder refuses both as a track's status: a track called Paused would put a course
	 * on somebody who has stopped.
	 *
	 * @return array<string, string> Status => badge modifier.
	 */
	public static function states() {
		return array(
			'Paused'             => 'paused',
			'Pending graduation' => 'pending',
		);
	}
}
