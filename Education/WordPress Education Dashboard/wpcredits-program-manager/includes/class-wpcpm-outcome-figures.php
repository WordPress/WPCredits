<?php
/**
 * The program's three outcome figures, counted by the site with the public dashboard's definitions.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The graduation share, the share of graduates who would recommend the program and the share who
 * plan to keep contributing, read from Airtable once a week and stored with their counts and the
 * time they were read.
 *
 * **The definitions are the public impact dashboard's, exactly.** That dashboard,
 * https://wordpress.github.io/WPCredits/, is built from the same base by WordPress/WPCredits'
 * `scripts/build_dashboard.py` and `scripts/template.html`; a figure on the site's homepage that
 * disagreed with it, even by its rounding, would be one of the two telling a reader something
 * wrong. So `compute()` holds the definitions and nothing else: the field IDs the dashboard reads,
 * the statuses and answers it counts, the way it reads a cell (a select's name, trimmed and
 * lowercased), the way it decides a student record is there at all (its name is present and not the
 * empty string, as Python's `if not name` decides), and how each share is rounded (the graduation
 * share as the page's `Math.round( grad / finished * 100 )` rounds it, the division first; the two
 * survey shares half to even, as Python's `round()` in `_pct_positive()` does). It is a pure
 * function from Airtable's rows to the figures, tested on fixtures, so the day the program's records
 * move onto the site only the reader changes.
 *
 * Each figure carries its own denominator. A Feedback row is created for every student whether or
 * not they ever answer, so the rows are never the denominator: a survey share is over the answers to
 * its own question.
 */
final class WPCPM_Outcome_Figures {

	/**
	 * Students Reports: the student's name, read only to test that it is there.
	 *
	 * The four field IDs are the dashboard's own, pinned as it pins them, so the figures read the same
	 * columns whatever the columns are called.
	 *
	 * @var string
	 */
	const FIELD_NAME = 'fldyXVlkChJaO9Q47';

	/**
	 * Students Reports: the student's status, a single select.
	 *
	 * @var string
	 */
	const FIELD_STATUS = 'fldCMdqqJGAUQ9nbV';

	/**
	 * Feedback: how likely the student is to recommend the program, a single select on the final
	 * survey.
	 *
	 * @var string
	 */
	const FIELD_RECOMMEND = 'fldZXbvOTIGad6r90';

	/**
	 * Feedback: how likely the student is to keep contributing to WordPress, a single select on the
	 * final survey.
	 *
	 * @var string
	 */
	const FIELD_KEEP = 'fld8RZMdCLYKtwcLN';

	/**
	 * The status a graduate holds, as the dashboard compares it: trimmed and lowercased.
	 *
	 * @var string
	 */
	const GRADUATE = 'graduate';

	/**
	 * The status a student who dropped out holds, compared the same way.
	 *
	 * @var string
	 */
	const DROPPED_OUT = 'dropped out';

	/**
	 * The one survey answer that counts as yes, compared the same way. "Neither likely nor unlikely"
	 * and "Unlikely" count among the answers only.
	 *
	 * @var string
	 */
	const POSITIVE = 'likely';

	/**
	 * The three figures, in the order the homepage shows them.
	 *
	 * @var string[]
	 */
	const FIGURES = array( 'graduation', 'recommend', 'keep' );

	/**
	 * A character class of what Python's `str.strip()` removes, which the dashboard strips a cell with:
	 * every Unicode space, not only the ASCII ones PHP's `trim()` removes (0x9 to 0xd, 0x1c to 0x20,
	 * 0x85, a no-break space, 0x1680, 0x2000 to 0x200a, the two line separators, 0x202f, 0x205f and an
	 * ideographic space). The set was checked against Python over every code point.
	 *
	 * @var string
	 */
	const SPACE = '[\x{9}-\x{d}\x{1c}-\x{20}\x{85}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}]';

	/**
	 * The last set stored: each figure's share, numerator and n, and the one time all three were
	 * read. Autoloaded, since the homepage reads it on every request, and under a kilobyte.
	 *
	 * @var string
	 */
	const OPTION = 'wpcpm_outcome_figures';

	/**
	 * The last attempt: when, whether it was stored, held or failed, a reason code, its sentence and
	 * the counts it saw. Not autoloaded: the syncs row and WP-CLI read it, nothing else.
	 *
	 * @var string
	 */
	const RUN_OPTION = 'wpcpm_outcome_figures_run';

	/**
	 * The lock a run holds, so the weekly event and a WP-CLI run never overlap: the time it was
	 * taken.
	 *
	 * @var string
	 */
	const LOCK_OPTION = 'wpcpm_outcome_figures_lock';

	/**
	 * How long a lock holds before a run killed halfway is taken to have left it behind: ten minutes.
	 *
	 * @var int
	 */
	const LOCK_TTL = 600;

	/**
	 * The fewest answers or outcomes a figure is shown over. Filtered by
	 * `wpcpm_outcome_figures_min_sample`.
	 *
	 * @var int
	 */
	const MIN_SAMPLE = 30;

	/**
	 * How many days a stored set is shown for: four Mondays in a row without a good read. A set read
	 * at 07:00 UTC on a Monday is 28 days and 5 hours old when the fourth Monday after it makes its
	 * last try at 12:00 UTC, so 29 days hides the figures the day after that try fails. Filtered by
	 * `wpcpm_outcome_figures_max_age`, in days.
	 *
	 * @var int
	 */
	const MAX_AGE_DAYS = 29;

	/**
	 * The most a numerator or an n may fall from the stored set in one run, in percent. The counts
	 * are cumulative and move down by a few only through duplicate clean-ups, so a larger fall means a
	 * filter, a status or a choice changed.
	 *
	 * @var int
	 */
	const MAX_FALL = 10;

	/**
	 * The most a share may move from the stored set in one run, in points. With n above 300 a real
	 * week moves a share by well under one point.
	 *
	 * @var int
	 */
	const MAX_SHIFT = 10;

	/**
	 * The most pages one table is read in. Students Reports is about five pages and Feedback about
	 * nine; a table still paging after fifty is not the table the figures were written for, and a
	 * count over part of it would be a figure from an incomplete read.
	 *
	 * @var int
	 */
	const MAX_PAGES = 50;

	/**
	 * The most characters of an error's own sentence a failed attempt keeps. Airtable's message is
	 * stored as it came, and an odd answer from upstream must not fill the attempt's option or the
	 * syncs row.
	 *
	 * @var int
	 */
	const MAX_MESSAGE = 300;

	/**
	 * The weekly event, on WordPress's own `weekly` recurrence, at 07:00 UTC on Mondays.
	 *
	 * @var string
	 */
	const HOOK = 'wpcpm_outcome_figures_weekly';

	/**
	 * A try again an hour later, after a run that failed or found the dashboard not built yet.
	 *
	 * @var string
	 */
	const RETRY_HOOK = 'wpcpm_outcome_figures_retry';

	/**
	 * The hour, UTC, the weekly run starts: 41 minutes after the latest finish among the public
	 * dashboard's last seven scheduled builds (06:18:51 UTC on 5 October 2026), an hour after its
	 * workflow's own start. UTC, as the workflow's cron is, so the site's clock changes never move it.
	 *
	 * @var int
	 */
	const RUN_HOUR = 7;

	/**
	 * The hour, UTC, of a Monday's last try: it reads Airtable whether or not the dashboard has built,
	 * and after it the next try is the next Monday.
	 *
	 * @var int
	 */
	const LAST_HOUR = 12;

	/**
	 * The public impact dashboard, whose page carries the date of its last build as `builtAt`.
	 *
	 * @var string
	 */
	const DASHBOARD_URL = 'https://wordpress.github.io/WPCredits/';

	/**
	 * The most of the dashboard's page read, in bytes: a megabyte, about seven times the page of
	 * October 2026 (154 KB). A page cut short of its build date reads as one that holds none, and the
	 * run reads Airtable at once.
	 *
	 * @var int
	 */
	const PAGE_BYTES = 1048576;

	/**
	 * What may be shown, read once a request.
	 *
	 * @var array|null
	 */
	private static $published = null;

	/**
	 * Stand-in for `time()`. Null means the real clock. See for_tests().
	 *
	 * @var callable|null
	 */
	private static $clock = null;

	/**
	 * Hook the weekly event and its retries, and keep the event on its Monday.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_scheduled' ) );
		add_action( self::RETRY_HOOK, array( __CLASS__, 'run_scheduled' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Schedule the weekly event, on activation and on `init`: on the next Monday at 07:00 UTC when
	 * there is none, and put back there when it has drifted. An event already at 07:00 UTC on a
	 * Monday is left alone, overdue or not: WordPress runs it and keeps its phase.
	 */
	public static function schedule() {
		$next = wp_next_scheduled( self::HOOK );

		if ( $next && '1 07:00:00' === gmdate( 'N H:i:s', (int) $next ) ) {
			return;
		}

		if ( $next ) {
			wp_clear_scheduled_hook( self::HOOK );
		}

		wp_schedule_event( self::next_run( self::now() ), 'weekly', self::HOOK );
	}

	/**
	 * The next Monday at 07:00 UTC, at or after a time.
	 *
	 * @param int $now Unix time.
	 * @return int
	 */
	public static function next_run( $now ) {
		$at = self::monday_of( $now ) + self::RUN_HOUR * HOUR_IN_SECONDS;

		return $at >= $now ? $at : $at + WEEK_IN_SECONDS;
	}

	/**
	 * Clear the weekly event and any retry, on deactivation (`wpcpm_deactivate()`).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::RETRY_HOOK );
	}

	/**
	 * The weekly event and its retries: a run that waits for the dashboard's Monday build.
	 */
	public static function run_scheduled() {
		self::refresh( array( 'wait' => true ) );
	}

	/**
	 * Read Airtable, count the three figures, and store them when every guard holds.
	 *
	 * Two reads through `WPCPM_Airtable`, with fields by ID: Students Reports, the name and the
	 * status, filtered on the status column to the two statuses counted, then Feedback, the two
	 * answers, every row. Any request that fails ends the run with nothing stored. The name is read
	 * only to test that it is there and is never kept: the set and the run hold counts alone.
	 *
	 * A scheduled run (`wait`) first reads the public dashboard's build date. When the dashboard has
	 * not built yet that Monday it reads nothing from Airtable and tries again an hour later; when the
	 * page cannot be read, or at the try at 12:00 UTC, it reads Airtable anyway. A scheduled run that
	 * fails, or finds another read running, tries again an hour later too, the last try at 12:00 UTC;
	 * a held one does not: a person decides. A scheduled run never stores the first set: with no set
	 * stored yet it is held, so a person compares its counts with the dashboard's and stores it. A run
	 * from WP-CLI reads at once and schedules nothing.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type bool $dry_run Count and report, and store nothing: neither the set nor the attempt.
	 *     @type bool $accept  Store the set although guards 3 and 4 trip, after a person has checked
	 *                         it: true and nothing else. A zero numerator is never stored.
	 *     @type bool $wait    Wait for the dashboard's Monday build, and try again on a failure: the
	 *                         weekly event's run. Ignored with `dry_run`: a dry run reads at once and
	 *                         records and schedules nothing.
	 * }
	 * @return array {
	 *     What the run did.
	 *
	 *     @type string $result  `stored`, `held`, `failed`, `waiting`, `dry_run`, `busy` or
	 *                           `not_connected`.
	 *     @type string $reason  A code: '' for a run that stored, else `zero`, `fell`, `moved`,
	 *                           `first`, `refused`, `rate_limited`, `airtable`, `unreachable`,
	 *                           `pages`, `no_table`, `not_saved`, `dashboard`, `busy` or
	 *                           `not_connected`.
	 *     @type string $message The sentence for the reason, '' for a run that stored.
	 *     @type array  $figures What `compute()` counted, or none when nothing was read.
	 *     @type array  $rows    How many rows each read returned: `reports`, `feedback`.
	 * }
	 */
	public static function refresh( array $args = array() ) {
		$args     = array_merge(
			array(
				'dry_run' => false,
				'accept'  => false,
				'wait'    => false,
			),
			$args
		);
		$now      = self::now();
		$settings = WPCPM_Settings::get();

		// A dry run never waits for the build and never books a retry: it would otherwise record a
		// waiting attempt, which a dry run must not.
		if ( ! empty( $args['dry_run'] ) ) {
			$args['wait'] = false;
		}

		if ( empty( $settings['api_token'] ) || empty( $settings['base_id'] ) ) {
			return self::result( 'not_connected', 'not_connected', __( 'Not connected.', 'wpcredits-program-manager' ) );
		}

		if ( ! self::lock( $now ) ) {
			// The weekly event finding another read running, a parity check from WP-CLI say, tries again
			// an hour later rather than leaving the Monday without a read.
			if ( ! empty( $args['wait'] ) && ! self::is_last_try( $now ) ) {
				self::retry_later( $now );
			}

			return self::result( 'busy', 'busy', __( 'Another read of the outcome figures is running. Try again in a few minutes.', 'wpcredits-program-manager' ) );
		}

		try {
			return self::run( $args, $now, $settings );
		} finally {
			self::unlock();
		}
	}

	/**
	 * What may be shown: the stored set, less any figure under the minimum sample, and nothing at all
	 * once the set is older than the maximum age. Read once a request.
	 *
	 * @return array Empty when no figure may be shown; else `read`, the Unix time the set was read, and
	 *               `figures`, `graduation`, `recommend` and `keep` in that order, those that may be
	 *               shown, each `array( 'share' => int, 'count' => int, 'n' => int )`.
	 */
	public static function published() {
		if ( null !== self::$published ) {
			return self::$published;
		}

		$set             = get_option( self::OPTION, array() );
		self::$published = array();
		$minimum         = self::min_sample();
		$shown           = array();

		if ( ! self::is_set( $set ) || self::is_aged( $set ) ) {
			return self::$published;
		}

		foreach ( self::FIGURES as $key ) {
			if ( $set[ $key ]['n'] >= $minimum && $set[ $key ]['count'] > 0 ) {
				$shown[ $key ] = array(
					'share' => (int) $set[ $key ]['share'],
					'count' => (int) $set[ $key ]['count'],
					'n'     => (int) $set[ $key ]['n'],
				);
			}
		}

		if ( array() !== $shown ) {
			self::$published = array(
				'read'    => (int) $set['read'],
				'figures' => $shown,
			);
		}

		return self::$published;
	}

	/**
	 * The last attempt, as the run recorded it: `time`, `result` (`stored`, `held`, `failed` or
	 * `waiting`), `reason`, `message`, `counts`, each figure's `count` and `n`, `monday`, the UTC date
	 * of the Monday the attempt belongs to, and `tries`, the attempts recorded for that Monday; none
	 * before the first run.
	 *
	 * @return array
	 */
	public static function status() {
		$run = get_option( self::RUN_OPTION, array() );

		return is_array( $run ) && isset( $run['result'] ) ? $run : array();
	}

	/**
	 * The stored set when it is whole, whatever its age and samples, for WP-CLI; none otherwise.
	 *
	 * @return array `read`, then `graduation`, `recommend` and `keep`, each its share, numerator and n.
	 */
	public static function stored() {
		$set = get_option( self::OPTION, array() );

		return self::is_set( $set ) ? $set : array();
	}

	/**
	 * The names the base gives the four pinned field IDs, from its table metadata, for the parity check
	 * of `wp wpcredits outcome_figures --refresh --dry-run`: they confirm the IDs are the student's name
	 * and status and the final survey's two questions. Reading the metadata needs the token's
	 * `schema.bases:read` scope.
	 *
	 * @return array<string, string>|WP_Error Field ID => its name, '' for an ID its table does not hold.
	 */
	public static function field_names() {
		$settings = WPCPM_Settings::get();
		$schema   = ( new WPCPM_Airtable( $settings ) )->fetch_schema();

		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		$tables = array(
			array( (string) $settings['reports_table'], array( self::FIELD_NAME, self::FIELD_STATUS ) ),
			array( (string) $settings['feedback_table'], array( self::FIELD_RECOMMEND, self::FIELD_KEEP ) ),
		);
		$names  = array();

		foreach ( $tables as $table ) {
			foreach ( $table[1] as $id ) {
				$names[ $id ] = isset( $schema[ $table[0] ]['ids'][ $id ] ) ? (string) $schema[ $table[0] ]['ids'][ $id ] : '';
			}
		}

		return $names;
	}

	/**
	 * The figures' row in the syncs `WPCPM_Administrators_Cards::health()` returns, so the Overview and
	 * the Administrator Dashboard both show it, in the shape every sync's row has.
	 *
	 * Its name opens the Connection tab of Settings, where the token and the base a failure names are
	 * fixed. Its last run is when the stored set was read, so a run that failed or was held does not
	 * read as a fresh figure; its next run is the next try, a retry or the weekly event. Its note says
	 * what the last attempt did when that was not to store: held, with the guard's reason and counts,
	 * failed, with the error's sentence, or waiting for the dashboard's build, for as long as the try
	 * that ends the wait is booked; that the site is not connected; that the stored set is older than
	 * the maximum age, so the page shows nothing, after the attempt's own sentence when there is one;
	 * or, for a set stored with a figure under the minimum sample, which figure the page leaves out and
	 * its n. The Overview prints the note under the last run, and the dashboard's card prints it as the
	 * row's last error, which is where it reads `progress`. An attempt recorded before the Monday and
	 * its tries were kept is told the same: the row reads only its result and its sentence, and an
	 * attempt held or failed with no sentence, or one that is not a string, is told in a sentence of
	 * the row's own.
	 *
	 * @return array `label`, `progress` (`running`, `phase`, `label`, `error`), `last`, `next`,
	 *               `screen` and `note`.
	 */
	public static function sync_row() {
		$set   = get_option( self::OPTION, array() );
		$retry = (int) wp_next_scheduled( self::RETRY_HOOK );
		$note  = self::note( $set, $retry );
		$tries = array_filter( array( $retry, (int) wp_next_scheduled( self::HOOK ) ) );

		return array(
			'label'    => __( 'Outcome figures', 'wpcredits-program-manager' ),
			'progress' => array(
				'running' => self::is_locked( self::now() ),
				'phase'   => '',
				'label'   => __( 'Reading Airtable', 'wpcredits-program-manager' ),
				'error'   => $note,
			),
			'last'     => self::is_set( $set ) ? (int) $set['read'] : 0,
			'next'     => array() === $tries ? 0 : min( $tries ),
			'screen'   => WPCPM_Settings_Screen::settings_url( 'connection' ),
			'note'     => $note,
		);
	}

	/**
	 * The fewest answers or outcomes a figure is shown over.
	 *
	 * @return int
	 */
	public static function min_sample() {
		return max( 1, (int) apply_filters( 'wpcpm_outcome_figures_min_sample', self::MIN_SAMPLE ) );
	}

	/**
	 * How many days a stored set is shown for.
	 *
	 * @return int
	 */
	public static function max_age_days() {
		return max( 1, (int) apply_filters( 'wpcpm_outcome_figures_max_age', self::MAX_AGE_DAYS ) );
	}

	/**
	 * Replace the clock, and forget what this request read. Test-only: nothing in the plugin calls it.
	 *
	 * @param callable|null $clock Returns the current Unix time, or null for the real clock.
	 */
	public static function for_tests( $clock = null ) {
		self::$clock     = $clock;
		self::$published = null;
	}

	/**
	 * The three figures from Airtable's rows.
	 *
	 * @param array[] $reports  Students Reports records as Airtable returns them with fields by ID:
	 *                          each `array( 'id' => ..., 'fields' => array( field ID => value ) )`.
	 * @param array[] $feedback Feedback records, the same shape.
	 * @return array<string, array{share: int|null, count: int, n: int}> `graduation`, `recommend` and
	 *                                                                    `keep`, each its share (null
	 *                                                                    when nothing was counted), its
	 *                                                                    numerator and its n.
	 */
	public static function compute( array $reports, array $feedback ) {
		$graduates = 0;
		$dropouts  = 0;

		foreach ( $reports as $record ) {
			$fields = self::fields_of( $record );

			if ( ! array_key_exists( self::FIELD_NAME, $fields ) || ! self::truthy( $fields[ self::FIELD_NAME ] ) ) {
				continue;
			}

			$status = self::key( isset( $fields[ self::FIELD_STATUS ] ) ? $fields[ self::FIELD_STATUS ] : null );

			if ( self::GRADUATE === $status ) {
				++$graduates;
			} elseif ( self::DROPPED_OUT === $status ) {
				++$dropouts;
			}
		}

		$n = $graduates + $dropouts;

		return array(
			'graduation' => array(
				'share' => $n > 0 ? self::half_up( $graduates, $n ) : null,
				'count' => $graduates,
				'n'     => $n,
			),
			'recommend'  => self::answers( $feedback, self::FIELD_RECOMMEND ),
			'keep'       => self::answers( $feedback, self::FIELD_KEEP ),
		);
	}

	/**
	 * One survey figure: the answers to one question that are not empty, and the ones among them that
	 * are the positive answer.
	 *
	 * @param array[] $feedback Feedback records.
	 * @param string  $field    The question's field ID.
	 * @return array{share: int|null, count: int, n: int}
	 */
	private static function answers( array $feedback, $field ) {
		$positive = 0;
		$answered = 0;

		foreach ( $feedback as $record ) {
			$fields = self::fields_of( $record );
			$answer = self::key( isset( $fields[ $field ] ) ? $fields[ $field ] : null );

			if ( '' === $answer ) {
				continue;
			}

			++$answered;

			if ( self::POSITIVE === $answer ) {
				++$positive;
			}
		}

		return array(
			'share' => $answered > 0 ? self::half_even( $positive, $answered ) : null,
			'count' => $positive,
			'n'     => $answered,
		);
	}

	/**
	 * A record's fields, or none when the record is not the shape Airtable sends.
	 *
	 * @param mixed $record One record.
	 * @return array
	 */
	private static function fields_of( $record ) {
		return is_array( $record ) && isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();
	}

	/**
	 * A select cell as the dashboard's `status_key()` reads it: the option's name, from a plain string
	 * or from an object holding a `name`, stripped as Python's `strip()` strips it and lowercased; ''
	 * for anything else, a list, a number or nothing at all.
	 *
	 * The dashboard lowercases with `casefold()`, which `strtolower()` equals for the ASCII option
	 * names the survey and the status carry (Graduate, Dropped out, Likely and the rest).
	 *
	 * @param mixed $value The cell.
	 * @return string
	 */
	private static function key( $value ) {
		if ( is_array( $value ) && array_key_exists( 'name', $value ) ) {
			$value = $value['name'];
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		return strtolower( self::strip( $value ) );
	}

	/**
	 * A string without the whitespace around it, as Python's `strip()` removes it: every Unicode space
	 * at either end. A string that is not valid UTF-8 cannot be matched by character, and is trimmed
	 * of the ASCII spaces alone; a cell read from JSON is always valid, so that is a guard only.
	 *
	 * @param string $value The cell's text.
	 * @return string
	 */
	private static function strip( $value ) {
		$stripped = preg_replace( '/^' . self::SPACE . '+|' . self::SPACE . '+$/u', '', $value );

		return null === $stripped ? trim( $value ) : $stripped;
	}

	/**
	 * Whether a cell counts as there, the way Python's `if not name` decides: anything but nothing,
	 * false, a zero, the empty string, an empty list and an empty object. A name of one space is there:
	 * the dashboard does not trim it.
	 *
	 * @param mixed $value The cell, as `json_decode()` gave it.
	 * @return bool
	 */
	private static function truthy( $value ) {
		return ! ( null === $value || false === $value || 0 === $value || 0.0 === $value || '' === $value || array() === $value );
	}

	/**
	 * The share count / n x 100, rounded as the dashboard's page rounds it: `Math.round( grad /
	 * finished * 100 )`, the division made first in floating point, so a share that is a half in whole
	 * numbers can land just under it (23 of 40 is 57.49999999999999, and 57). The expression is the
	 * page's own, half up on the float JavaScript computes, so the homepage and the dashboard never
	 * differ by a point.
	 *
	 * @param int $count The numerator.
	 * @param int $n     The denominator, above 0.
	 * @return int
	 */
	private static function half_up( $count, $n ) {
		$share = $count / $n * 100;
		$whole = floor( $share );

		return (int) ( $share - $whole >= 0.5 ? $whole + 1 : $whole );
	}

	/**
	 * 100 x count / n, rounded half to even, as Python's `round()` rounds: 12.5 is 12 and 87.5 is 88.
	 *
	 * @param int $count The numerator.
	 * @param int $n     The denominator, above 0.
	 * @return int
	 */
	private static function half_even( $count, $n ) {
		$whole = intdiv( 100 * $count, $n );
		$left  = 100 * $count - $whole * $n;

		if ( 2 * $left > $n || ( 2 * $left === $n && 1 === $whole % 2 ) ) {
			++$whole;
		}

		return $whole;
	}

	/**
	 * One run, under the lock: read, count, guard, store.
	 *
	 * @param array $args     What `refresh()` was asked.
	 * @param int   $now      The run's time.
	 * @param array $settings The plugin's settings.
	 * @return array What `refresh()` returns.
	 */
	private static function run( array $args, $now, array $settings ) {
		$scheduled = ! empty( $args['wait'] );
		$last_try  = self::is_last_try( $now );

		if ( $scheduled && ! $last_try ) {
			$built = self::dashboard_built();

			if ( '' !== $built && $built < gmdate( 'Y-m-d', self::monday_of( $now ) ) ) {
				$message = __( 'Waiting for the dashboard\'s Monday build.', 'wpcredits-program-manager' );

				self::record( $now, 'waiting', 'dashboard', $message, array() );
				self::retry_later( $now );

				return self::result( 'waiting', 'dashboard', $message );
			}
		}

		// The next try is booked before the read, so a run stopped inside it (a fatal, a memory or time
		// limit, the host's runner) still has one. A run that stores or is held takes it off; one that
		// fails keeps it.
		if ( $scheduled && ! $last_try ) {
			self::retry_later( $now );
		}

		$read = self::read( $settings );

		if ( is_wp_error( $read ) ) {
			list( $reason, $message ) = self::failure( $read );

			if ( empty( $args['dry_run'] ) ) {
				self::record( $now, 'failed', $reason, $message, array() );
			}

			// An empty table setting is the site's own, and an hour on it is as empty: no try is kept.
			if ( $scheduled && 'no_table' === $reason ) {
				wp_clear_scheduled_hook( self::RETRY_HOOK );
			}

			return self::result( 'failed', $reason, $message );
		}

		$figures = self::compute( $read['reports'], $read['feedback'] );
		$rows    = array(
			'reports'  => count( $read['reports'] ),
			'feedback' => count( $read['feedback'] ),
		);

		if ( ! empty( $args['dry_run'] ) ) {
			return self::result( 'dry_run', '', '', $figures, $rows );
		}

		$stored = get_option( self::OPTION, array() );

		list( $reason, $message ) = self::guard( $figures, $stored, true === $args['accept'] );

		// The first set is a person's to store, after the parity check with the dashboard (spec 10.2):
		// guards 3 and 4 have nothing to compare a first read with, and a wrong first set would be what
		// every later read is compared with. So the weekly run holds it and says how to check it and
		// store it; a zero keeps its own sentence, since --refresh would hold that one too.
		if ( '' === $reason && $scheduled && ! self::is_set( $stored ) ) {
			$reason  = 'first';
			$message = __( 'Held: the first figures are stored by a person, not by the weekly read. Run wp wpcredits outcome_figures --refresh --dry-run and compare its counts with the WordPress Credits impact dashboard\'s, then store them with wp wpcredits outcome_figures --refresh.', 'wpcredits-program-manager' );
		}

		if ( '' !== $reason ) {
			self::record( $now, 'held', $reason, $message, $figures );
			wp_clear_scheduled_hook( self::RETRY_HOOK );

			return self::result( 'held', $reason, $message, $figures, $rows );
		}

		$set = array( 'read' => $now );

		foreach ( self::FIGURES as $key ) {
			$set[ $key ] = $figures[ $key ];
		}

		update_option( self::OPTION, $set, true );

		// update_option() says false both for a write the database refused and for a value already
		// there, so the option read back is what tells: a set the site did not keep is never stored.
		if ( get_option( self::OPTION, array() ) !== $set ) {
			$message = __( 'Failed: the site could not save the figures.', 'wpcredits-program-manager' );

			self::record( $now, 'failed', 'not_saved', $message, $figures );

			return self::result( 'failed', 'not_saved', $message, $figures, $rows );
		}

		self::record( $now, 'stored', '', '', $figures );
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		self::$published = null;

		return self::result( 'stored', '', '', $figures, $rows );
	}

	/**
	 * The date of the public dashboard's last build, `builtAt` in the data its page carries, or ''
	 * when the page cannot be read or holds no such date.
	 *
	 * @return string `Y-m-d`, a UTC date.
	 */
	private static function dashboard_built() {
		$response = wp_safe_remote_get(
			self::DASHBOARD_URL,
			array(
				'timeout'             => 10,
				'limit_response_size' => self::PAGE_BYTES,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		if ( ! preg_match( '/"builtAt"\s*:\s*"(\d{4}-\d{2}-\d{2})"/', (string) wp_remote_retrieve_body( $response ), $found ) ) {
			return '';
		}

		return $found[1];
	}

	/**
	 * Try again at the next whole hour, unless a try is already booked. Asked only before the
	 * Monday's last try at 12:00 UTC, so the next whole hour is that try at the latest.
	 *
	 * @param int $now The run's time.
	 */
	private static function retry_later( $now ) {
		if ( wp_next_scheduled( self::RETRY_HOOK ) ) {
			return;
		}

		wp_schedule_single_event( ( intdiv( (int) $now, HOUR_IN_SECONDS ) + 1 ) * HOUR_IN_SECONDS, self::RETRY_HOOK );
	}

	/**
	 * Whether a run is at or past its Monday's last try, 12:00:00 UTC: it reads Airtable without
	 * waiting for the dashboard, and books no try after it. The one test of that boundary, for the run
	 * and for a scheduled run that finds the lock held.
	 *
	 * @param int $now The run's time.
	 * @return bool
	 */
	private static function is_last_try( $now ) {
		return $now >= self::monday_of( $now ) + self::LAST_HOUR * HOUR_IN_SECONDS;
	}

	/**
	 * 00:00 UTC on the Monday of a time's week, Monday to Sunday, in UTC.
	 *
	 * @param int $now Unix time.
	 * @return int
	 */
	private static function monday_of( $now ) {
		$midnight = (int) $now - ( (int) $now % DAY_IN_SECONDS );

		return $midnight - ( (int) gmdate( 'N', (int) $now ) - 1 ) * DAY_IN_SECONDS;
	}

	/**
	 * The two reads, every page of each, or the first error.
	 *
	 * @param array $settings The plugin's settings: the token, the base, `reports_table` and
	 *                        `feedback_table`.
	 * @return array|WP_Error `reports` and `feedback`, each a list of records.
	 */
	private static function read( array $settings ) {
		$airtable = new WPCPM_Airtable( $settings );
		$reports  = self::read_all(
			$airtable,
			(string) $settings['reports_table'],
			array(
				'fields'  => array( self::FIELD_NAME, self::FIELD_STATUS ),
				'formula' => $airtable->formula_in( self::status_column(), array( 'Graduate', 'Dropped out' ), true, true ),
				'by_id'   => true,
			)
		);

		if ( is_wp_error( $reports ) ) {
			return $reports;
		}

		$feedback = self::read_all(
			$airtable,
			(string) $settings['feedback_table'],
			array(
				'fields' => array( self::FIELD_RECOMMEND, self::FIELD_KEEP ),
				'by_id'  => true,
			)
		);

		if ( is_wp_error( $feedback ) ) {
			return $feedback;
		}

		return array(
			'reports'  => $reports,
			'feedback' => $feedback,
		);
	}

	/**
	 * Every page of one table, or the first error, or an error once the table pages past
	 * `MAX_PAGES`: a count over part of a table is never made.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @param string         $table    The table ID.
	 * @param array          $args     What `fetch_page()` is asked, the offset aside.
	 * @return array|WP_Error The records.
	 */
	private static function read_all( WPCPM_Airtable $airtable, $table, array $args ) {
		$records = array();
		$offset  = null;
		$pages   = 0;

		do {
			if ( $pages >= self::MAX_PAGES ) {
				return new WP_Error(
					'wpcpm_outcome_pages',
					/* translators: %d: the most pages one table is read in. */
					sprintf( __( 'Airtable was still paging after %d pages, so nothing was counted.', 'wpcredits-program-manager' ), self::MAX_PAGES )
				);
			}

			$args['offset'] = $offset;
			$page           = $airtable->fetch_page( $table, $args );

			if ( is_wp_error( $page ) ) {
				return $page;
			}

			$records = array_merge( $records, $page['records'] );
			$offset  = $page['offset'];
			++$pages;
		} while ( null !== $offset && '' !== $offset );

		return $records;
	}

	/**
	 * The Students Reports status column's name, which the read's formula filters on: the one the
	 * mentors and students syncs filter on every three hours (`WPCPM_Mentors_Sync::fields()`,
	 * filterable by `wpcpm_mentors_fields`), or Status.
	 *
	 * @return string
	 */
	private static function status_column() {
		$fields = class_exists( 'WPCPM_Mentors_Sync' ) ? (array) WPCPM_Mentors_Sync::fields() : array();

		return isset( $fields['report_status'] ) && '' !== (string) $fields['report_status'] ? (string) $fields['report_status'] : 'Status';
	}

	/**
	 * The guards, in order: no numerator is zero; against a stored set, no numerator and no n fell by
	 * more than `MAX_FALL` percent and no share moved by more than `MAX_SHIFT` points. The first to
	 * trip holds the run. The first run on a site has nothing to compare with, and `$accept` skips the
	 * comparison; a zero is held either way.
	 *
	 * @param array $figures What `compute()` counted.
	 * @param mixed $stored  The stored set, as the option holds it.
	 * @param bool  $accept  Whether a person has checked the set.
	 * @return string[] The reason code and its sentence, both '' when every guard holds.
	 */
	private static function guard( array $figures, $stored, $accept ) {
		$names = self::names();

		foreach ( self::FIGURES as $key ) {
			if ( 0 === (int) $figures[ $key ]['count'] ) {
				return array(
					'zero',
					/* translators: %s: what was counted, such as "graduates". */
					sprintf( __( 'Held: no %s were counted.', 'wpcredits-program-manager' ), $names[ $key ]['count'] ),
				);
			}
		}

		if ( $accept || ! self::is_set( $stored ) ) {
			return array( '', '' );
		}

		foreach ( self::FIGURES as $key ) {
			foreach ( array( 'count', 'n' ) as $part ) {
				$before = (int) $stored[ $key ][ $part ];
				$after  = (int) $figures[ $key ][ $part ];

				if ( 100 * $after < ( 100 - self::MAX_FALL ) * $before ) {
					return array(
						'fell',
						sprintf(
							/* translators: 1: what was counted, such as "graduates", 2: the stored count, 3: the count read now, 4: the most a count may fall in one run, in percent. */
							__( 'Held: %1$s fell from %2$s to %3$s, by more than %4$d%%.', 'wpcredits-program-manager' ),
							$names[ $key ][ $part ],
							number_format_i18n( $before ),
							number_format_i18n( $after ),
							self::MAX_FALL
						),
					);
				}
			}
		}

		foreach ( self::FIGURES as $key ) {
			$before = (int) $stored[ $key ]['share'];
			$after  = (int) $figures[ $key ]['share'];

			if ( abs( $after - $before ) > self::MAX_SHIFT ) {
				return array(
					'moved',
					sprintf(
						/* translators: 1: the share's name, such as "the graduation share", 2: the stored share, 3: the share read now, 4: the most a share may move in one run, in points. */
						__( 'Held: %1$s moved from %2$s%% to %3$s%%, by more than %4$d points.', 'wpcredits-program-manager' ),
						$names[ $key ]['share'],
						number_format_i18n( $before ),
						number_format_i18n( $after ),
						self::MAX_SHIFT
					),
				);
			}
		}

		return array( '', '' );
	}

	/**
	 * What each figure's numerator, n and share are called in a held run's sentence.
	 *
	 * @return array<string, array{count: string, n: string, share: string}>
	 */
	private static function names() {
		return array(
			'graduation' => array(
				'count' => __( 'graduates', 'wpcredits-program-manager' ),
				'n'     => __( 'students who graduated or dropped out', 'wpcredits-program-manager' ),
				'share' => __( 'the graduation share', 'wpcredits-program-manager' ),
			),
			'recommend'  => array(
				'count' => __( 'answers of likely to recommend', 'wpcredits-program-manager' ),
				'n'     => __( 'answers to the recommend question', 'wpcredits-program-manager' ),
				'share' => __( 'the would-recommend share', 'wpcredits-program-manager' ),
			),
			'keep'       => array(
				'count' => __( 'answers of likely to keep contributing', 'wpcredits-program-manager' ),
				'n'     => __( 'answers to the keep-contributing question', 'wpcredits-program-manager' ),
				'share' => __( 'the keep-contributing share', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * A failed read's reason code and sentence: Airtable refusing the token (401, 403), asking the
	 * site to wait (429), refusing the request (404, 422 or another 4xx, with Airtable's message), a
	 * table paging past `MAX_PAGES`, a table setting left empty, or anything else (a timeout, a 5xx,
	 * an answer that is not JSON). An error's own sentence is kept to `MAX_MESSAGE` characters.
	 *
	 * @param WP_Error $error What the read failed with.
	 * @return string[] The reason code and its sentence.
	 */
	private static function failure( WP_Error $error ) {
		$data   = (array) $error->get_error_data();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;

		if ( 401 === $status || 403 === $status ) {
			return array( 'refused', __( 'Failed: Airtable refused the token.', 'wpcredits-program-manager' ) );
		}

		if ( 429 === $status ) {
			return array( 'rate_limited', __( 'Failed: Airtable asked the site to wait.', 'wpcredits-program-manager' ) );
		}

		if ( 'wpcpm_no_table' === $error->get_error_code() ) {
			return array( 'no_table', __( 'Failed: the Students Reports table or the Feedback table is not set in Settings.', 'wpcredits-program-manager' ) );
		}

		if ( 'wpcpm_outcome_pages' === $error->get_error_code() ) {
			$reason = 'pages';
		} elseif ( $status >= 400 && $status < 500 ) {
			$reason = 'airtable';
		} else {
			$reason = 'unreachable';
		}

		/* translators: %s: the error's own sentence. */
		return array( $reason, sprintf( __( 'Failed: %s', 'wpcredits-program-manager' ), self::capped( $error->get_error_message() ) ) );
	}

	/**
	 * A sentence cut to `MAX_MESSAGE` characters, and three dots after the cut, when it is longer.
	 *
	 * @param string $text The sentence.
	 * @return string
	 */
	private static function capped( $text ) {
		$text = (string) $text;

		if ( mb_strlen( $text, 'UTF-8' ) <= self::MAX_MESSAGE ) {
			return $text;
		}

		return rtrim( mb_substr( $text, 0, self::MAX_MESSAGE, 'UTF-8' ) ) . '...';
	}

	/**
	 * Write the attempt: its time, result, reason, sentence, the counts it saw, its Monday and how
	 * many attempts that Monday has made, nothing else.
	 *
	 * @param int    $now     The run's time.
	 * @param string $result  `stored`, `held`, `failed` or `waiting`.
	 * @param string $reason  The reason code.
	 * @param string $message Its sentence.
	 * @param array  $figures What `compute()` counted, or none.
	 */
	private static function record( $now, $result, $reason, $message, array $figures ) {
		$monday   = gmdate( 'Y-m-d', self::monday_of( $now ) );
		$previous = self::status();

		update_option(
			self::RUN_OPTION,
			array(
				'time'    => (int) $now,
				'result'  => $result,
				'reason'  => $reason,
				'message' => $message,
				'counts'  => self::counts_of( $figures ),
				'monday'  => $monday,
				'tries'   => ( isset( $previous['monday'], $previous['tries'] ) && $previous['monday'] === $monday ? (int) $previous['tries'] : 0 ) + 1,
			),
			false
		);
	}

	/**
	 * Each figure's numerator and n, the counts a run keeps of what it saw.
	 *
	 * @param array $figures What `compute()` counted, or none.
	 * @return array<string, array{count: int, n: int}>
	 */
	private static function counts_of( array $figures ) {
		$counts = array();

		foreach ( self::FIGURES as $key ) {
			if ( isset( $figures[ $key ]['count'], $figures[ $key ]['n'] ) ) {
				$counts[ $key ] = array(
					'count' => (int) $figures[ $key ]['count'],
					'n'     => (int) $figures[ $key ]['n'],
				);
			}
		}

		return $counts;
	}

	/**
	 * What `refresh()` returns.
	 *
	 * @param string $result  The result.
	 * @param string $reason  The reason code.
	 * @param string $message Its sentence.
	 * @param array  $figures What was counted.
	 * @param array  $rows    The rows each read returned.
	 * @return array
	 */
	private static function result( $result, $reason, $message, array $figures = array(), array $rows = array() ) {
		return array(
			'result'  => $result,
			'reason'  => $reason,
			'message' => $message,
			'figures' => $figures,
			'rows'    => $rows,
		);
	}

	/**
	 * Whether a whole stored set is older than the maximum age, so nothing of it is shown.
	 *
	 * @param array $set A set `is_set()` holds whole.
	 * @return bool
	 */
	private static function is_aged( array $set ) {
		return self::now() - (int) $set['read'] > self::max_age_days() * DAY_IN_SECONDS;
	}

	/**
	 * Whether a stored set is whole: its read time, and each figure's share, numerator and n as whole
	 * numbers that make sense together. A set the option holds in any other shape is no set: nothing
	 * is shown from it and nothing is compared with it.
	 *
	 * @param mixed $set The option's value.
	 * @return bool
	 */
	private static function is_set( $set ) {
		if ( ! is_array( $set ) || ! isset( $set['read'] ) || ! is_int( $set['read'] ) || $set['read'] <= 0 ) {
			return false;
		}

		foreach ( self::FIGURES as $key ) {
			$figure = isset( $set[ $key ] ) && is_array( $set[ $key ] ) ? $set[ $key ] : array();

			foreach ( array( 'share', 'count', 'n' ) as $part ) {
				if ( ! isset( $figure[ $part ] ) || ! is_int( $figure[ $part ] ) || $figure[ $part ] < 0 ) {
					return false;
				}
			}

			if ( $figure['share'] > 100 || $figure['count'] > $figure['n'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Take the lock, or say another run holds it. A lock older than `LOCK_TTL` was left by a run that
	 * never finished, and is taken over.
	 *
	 * @param int $now The run's time.
	 * @return bool
	 */
	private static function lock( $now ) {
		if ( add_option( self::LOCK_OPTION, (int) $now, '', false ) ) {
			return true;
		}

		if ( self::is_locked( $now ) ) {
			return false;
		}

		update_option( self::LOCK_OPTION, (int) $now, false );

		return true;
	}

	/**
	 * Give the lock back.
	 */
	private static function unlock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Whether a run holds the lock at a time: the one test of the lock's age, for a run taking it and
	 * for the syncs row, so the row never says a run is under way when a run would take its lock over.
	 *
	 * @param int $now The time.
	 * @return bool
	 */
	private static function is_locked( $now ) {
		$held = (int) get_option( self::LOCK_OPTION, 0 );

		return $held > 0 && $now - $held < self::LOCK_TTL;
	}

	/**
	 * The syncs row's note: see `sync_row()`.
	 *
	 * @param mixed $set   The stored set, as the option holds it.
	 * @param int   $retry When the retry is booked, 0 for none.
	 * @return string '' when there is nothing to say.
	 */
	private static function note( $set, $retry ) {
		if ( ! WPCPM_Settings::is_connected() ) {
			return __( 'Not connected.', 'wpcredits-program-manager' );
		}

		$run  = self::status();
		$told = array( 'held', 'failed' );
		$aged = '';

		// A wait is told only while the try that ends it is booked. With that try gone (the plugin was
		// deactivated and activated again) nothing waits, and the sentence would go on saying so until
		// the next Monday.
		if ( $retry > 0 ) {
			$told[] = 'waiting';
		}

		// A set past the maximum age shows nothing on the homepage, whatever the last attempt did, and
		// a weekly event that never fired leaves no attempt to say why: the row says it.
		if ( self::is_set( $set ) && self::is_aged( $set ) ) {
			$days = self::max_age_days();
			/* translators: %d: the most days the figures stored are shown for. */
			$aged = sprintf( _n( 'Not shown on the homepage: the figures stored are more than %d day old.', 'Not shown on the homepage: the figures stored are more than %d days old.', $days, 'wpcredits-program-manager' ), $days );
		}

		if ( isset( $run['result'] ) && in_array( $run['result'], $told, true ) ) {
			$said = isset( $run['message'] ) && is_string( $run['message'] ) && '' !== trim( $run['message'] ) ? $run['message'] : self::unsaid( $run['result'] );

			return '' === $aged ? $said : $said . ' ' . $aged;
		}

		if ( '' !== $aged || ! self::is_set( $set ) ) {
			return $aged;
		}

		$minimum = self::min_sample();
		$names   = self::names();
		$under   = array();

		foreach ( self::FIGURES as $key ) {
			if ( $set[ $key ]['n'] < $minimum ) {
				/* translators: 1: a share's name, such as "the graduation share", 2: its n. */
				$under[] = sprintf( __( '%1$s (%2$s)', 'wpcredits-program-manager' ), $names[ $key ]['share'], number_format_i18n( $set[ $key ]['n'] ) );
			}
		}

		if ( array() === $under ) {
			return '';
		}

		return sprintf(
			/* translators: 1: the fewest answers or outcomes a figure is shown over, 2: the shares left out, each with its n. */
			__( 'Not shown on the homepage, with fewer than %1$s answers or outcomes: %2$s.', 'wpcredits-program-manager' ),
			number_format_i18n( $minimum ),
			implode( ', ', $under )
		);
	}

	/**
	 * The row's sentence for an attempt recorded with none, or with one that is not a string: the
	 * option edited by hand, say. `record()` always writes one.
	 *
	 * @param string $result `held`, `failed` or `waiting`.
	 * @return string
	 */
	private static function unsaid( $result ) {
		if ( 'held' === $result ) {
			return __( 'Held: the last read was not stored.', 'wpcredits-program-manager' );
		}

		if ( 'failed' === $result ) {
			return __( 'Failed: the last read stored nothing.', 'wpcredits-program-manager' );
		}

		return __( 'Waiting for the dashboard\'s Monday build.', 'wpcredits-program-manager' );
	}

	/**
	 * The current Unix time.
	 *
	 * @return int
	 */
	private static function now() {
		return null !== self::$clock ? (int) call_user_func( self::$clock ) : time();
	}
}
