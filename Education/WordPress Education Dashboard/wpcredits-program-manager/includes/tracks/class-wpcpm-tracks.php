<?php
/**
 * The Track Builder's tracks, as the live site reads them.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hands the compiled tracks to the program map, the form, the chips and the automation guard.
 *
 * **The live site never reads a `wpcpm_track` post.** `WPCPM_Program::labels()` runs for every
 * row of every roster, which rules out a query, so the tracks are compiled into options
 * (`WPCPM_Track_Store::compile()`) and everything here reads only those: `OPT_TRACKS`,
 * autoloaded and small, and one `OPT_FIELDS_PREFIX` option per track holding its form, read on
 * the pages that draw one. The design gives a second reason for the options, that editing a
 * published track must not change the form students see until somebody publishes the change,
 * and it holds because the compile reads what was published, never what was last saved
 * (`WPCPM_Track_Store::META_PUBLISHED`).
 *
 * A row whose source is `builtin` is a migrated track its PHP still runs (the design's decision
 * 3.5): every callback skips it, so the hand-written code stays authoritative until a Program
 * Administrator switches the track to its definition.
 */
final class WPCPM_Tracks {

	/** The compiled index: status => `WPCPM_Track_Definition::row()`. Autoloaded. */
	const OPT_TRACKS = 'wpcpm_tracks';

	/** One compiled form per track key, not autoloaded. */
	const OPT_FIELDS_PREFIX = 'wpcpm_track_fields_';

	/**
	 * The report columns the syncs own, by their key in `WPCPM_Mentors_Sync::fields()`.
	 *
	 * `handle_save()` writes the columns a form names, so a question bound to one of these would
	 * let a student change their own status, mentor, institution or dates. The five report keys
	 * missing here - profile, Slack, team, website and hours - are questions on every form, which
	 * is why the sync maps them as well. `report_instituton` is spelled as the sync spells it.
	 */
	const SYNC_COLUMNS = array( 'report_name', 'report_email', 'report_status', 'report_mentor', 'report_instituton', 'report_start', 'report_end', 'report_link', 'report_link_50h', 'report_link_dev' );

	/**
	 * The compiled index, read once a request.
	 *
	 * @var array|null
	 */
	private static $rows = null;

	/**
	 * Compiled forms read this request, by track key.
	 *
	 * @var array<string, array>
	 */
	private static $forms = array();

	/**
	 * Above zero while the PHP maps are read without the compiled tracks.
	 *
	 * @var int
	 */
	private static $suspended = 0;

	/**
	 * Hook the tracks into the program map, the form and the stylesheet.
	 */
	public static function init() {
		add_filter( 'wpcpm_program_labels', array( __CLASS__, 'filter_labels' ) );
		add_filter( 'wpcpm_program_courses', array( __CLASS__, 'filter_courses' ) );
		add_filter( 'wpcpm_program_hours_targets', array( __CLASS__, 'filter_hours' ) );
		add_filter( 'wpcpm_program_tracks', array( __CLASS__, 'filter_tracks' ) );
		add_filter( 'wpcpm_program_course_ids', array( __CLASS__, 'filter_course_ids' ) );
		add_filter( 'wpcpm_report_form_fields', array( __CLASS__, 'filter_fields' ), 10, 2 );

		// After `init` 10, where the dashboards register the stylesheet the chips live in.
		add_action( 'init', array( __CLASS__, 'add_badge_styles' ), 20 );
	}

	/**
	 * Every compiled track, the ones their PHP still runs included.
	 *
	 * @return array<string, array> Status => row.
	 */
	public static function rows() {
		if ( null === self::$rows ) {
			$stored     = get_option( self::OPT_TRACKS, array() );
			self::$rows = is_array( $stored ) ? $stored : array();
		}

		return self::$rows;
	}

	/**
	 * The tracks the site runs from their definitions.
	 *
	 * @return array<string, array> Status => row.
	 */
	public static function live() {
		if ( self::$suspended > 0 ) {
			return array();
		}

		return array_filter(
			self::rows(),
			static function ( $row ) {
				return is_array( $row ) && isset( $row['source'], $row['key'] ) && 'definition' === $row['source'];
			}
		);
	}

	/**
	 * Forget what was read this request, after a compile has written new options.
	 */
	public static function flush() {
		self::$rows  = null;
		self::$forms = array();
	}

	/**
	 * Add each live track's name to `WPCPM_Program::labels()`.
	 *
	 * @param array $labels Status => name.
	 * @return array
	 */
	public static function filter_labels( $labels ) {
		foreach ( self::live() as $status => $row ) {
			$labels[ $status ] = (string) $row['label'];
		}

		return $labels;
	}

	/**
	 * Add each live track's Learn course link, or take the map's away when the definition has none.
	 *
	 * Taking away matters for a built-in track switched to its definition: the definition is then
	 * what the track is, and a course it no longer names must not survive from the PHP.
	 *
	 * @param array $courses Status => course URL.
	 * @return array
	 */
	public static function filter_courses( $courses ) {
		foreach ( self::live() as $status => $row ) {
			if ( isset( $row['course_url'] ) && '' !== $row['course_url'] ) {
				$courses[ $status ] = (string) $row['course_url'];
			} else {
				unset( $courses[ $status ] );
			}
		}

		return $courses;
	}

	/**
	 * Add each live track's hours target, or take the row away for a track that counts none.
	 *
	 * A missing row is how `WPCPM_Program::hours_targets()` says there is no target (decision 1.9
	 * of the design), so a definition without one removes the row rather than writing 0.
	 *
	 * @param array $targets Status => hours.
	 * @return array
	 */
	public static function filter_hours( $targets ) {
		foreach ( self::live() as $status => $row ) {
			if ( isset( $row['hours'] ) ) {
				$targets[ $status ] = (int) $row['hours'];
			} else {
				unset( $targets[ $status ] );
			}
		}

		return $targets;
	}

	/**
	 * Add each live track's key to `WPCPM_Program::track()`.
	 *
	 * @param array $tracks Status => track key.
	 * @return array
	 */
	public static function filter_tracks( $tracks ) {
		foreach ( self::live() as $status => $row ) {
			$tracks[ $status ] = (string) $row['key'];
		}

		return $tracks;
	}

	/**
	 * Add each live track's Learn course post ID, or take the map's away when there is none.
	 *
	 * @param array $ids Status => Learn course post ID.
	 * @return array
	 */
	public static function filter_course_ids( $ids ) {
		foreach ( self::live() as $status => $row ) {
			if ( ! empty( $row['course_id'] ) ) {
				$ids[ $status ] = (int) $row['course_id'];
			} else {
				unset( $ids[ $status ] );
			}
		}

		return $ids;
	}

	/**
	 * A live track's form, in place of what `WPCPM_Student_Report_Form::fields()` built.
	 *
	 * **A live track whose form option is missing gets an empty form, never the one it was
	 * handed.** For a key it does not know, `fields()` builds the 150-hour set, and drawing that
	 * for an authored track would have its students writing another track's columns. An empty
	 * form writes nothing.
	 *
	 * @param array  $fields What `fields()` built.
	 * @param string $track  Track key.
	 * @return array
	 */
	public static function filter_fields( $fields, $track ) {
		foreach ( self::live() as $row ) {
			if ( (string) $row['key'] === (string) $track ) {
				return self::form( (string) $row['key'] );
			}
		}

		return $fields;
	}

	/**
	 * One track's compiled form, read once a request.
	 *
	 * @param string $key Track key.
	 * @return array
	 */
	public static function form( $key ) {
		if ( ! isset( self::$forms[ $key ] ) ) {
			$stored              = get_option( self::OPT_FIELDS_PREFIX . $key, array() );
			self::$forms[ $key ] = is_array( $stored ) ? $stored : array();
		}

		return self::$forms[ $key ];
	}

	/**
	 * The live tracks whose reports automation item somebody has ticked.
	 *
	 * @return string[] Statuses.
	 */
	public static function confirmed_automation_statuses() {
		$statuses = array();

		foreach ( self::live() as $status => $row ) {
			if ( ! empty( $row['automation'] ) ) {
				$statuses[] = (string) $status;
			}
		}

		return $statuses;
	}

	/**
	 * One chip rule per authored track.
	 *
	 * None for the built-in keys, a built-in track switched to its definition included: their
	 * rules are hand-written, in the plugin's stylesheet and in the theme's.
	 *
	 * @return string CSS, or an empty string when there is nothing to paint.
	 */
	public static function badge_css() {
		$rules = array();

		foreach ( self::live() as $row ) {
			if ( in_array( $row['key'], WPCPM_Track_Definition::RESERVED_KEYS, true ) ) {
				continue;
			}

			$rule = WPCPM_Track_Palette::badge_rule( (string) $row['key'], isset( $row['hue'] ) ? (string) $row['hue'] : '' );

			if ( '' !== $rule ) {
				$rules[] = $rule;
			}
		}

		return implode( "\n", $rules );
	}

	/**
	 * Attach the chip rules to the stylesheet every dashboard page loads.
	 *
	 * The stylesheet is registered first, because an inline rule for a handle that is not
	 * registered is dropped without a word; `register_assets()` registers it at most once.
	 */
	public static function add_badge_styles() {
		$css = self::badge_css();

		if ( '' === $css ) {
			return;
		}

		WPCPM_Mentors_Dashboard::register_assets();
		wp_add_inline_style( WPCPM_Mentors_Dashboard::STYLE, $css );
	}

	/**
	 * The column names the syncs own, which no question may write.
	 *
	 * @return string[]
	 */
	public static function reserved_columns() {
		$map     = WPCPM_Mentors_Sync::fields();
		$columns = array();

		foreach ( self::SYNC_COLUMNS as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$columns[] = (string) $map[ $key ];
			}
		}

		return $columns;
	}

	/**
	 * What `WPCPM_Track_Definition::validate()` needs to know about the site.
	 *
	 * `$own_status` leaves that status out of the others unconditionally, which is the hole the
	 * T2a plan's decision 4 describes: a new track could take one of the four built-in statuses
	 * under a key of its own. The store's `context()` is the one that locks, leaving a track's own
	 * status out only when the track is locked to it, and no caller in the plugin passes a status
	 * now.
	 *
	 * @param string $own_status The status of the track being checked, left out of the others.
	 * @param bool   $compiled   False to read the program map as its PHP alone describes it, which
	 *                           is how the store checks a track against the others it compiles.
	 * @return array
	 */
	public static function validation_context( $own_status = '', $compiled = true ) {
		if ( ! $compiled ) {
			return self::unfiltered(
				static function () use ( $own_status ) {
					return self::validation_context( $own_status );
				}
			);
		}

		$tracks = array();
		$labels = array();

		foreach ( WPCPM_Program::labels() as $status => $label ) {
			if ( (string) $status !== (string) $own_status ) {
				$tracks[ $status ] = WPCPM_Program::track( $status );
				$labels[ $status ] = (string) $label;
			}
		}

		$settings = WPCPM_Settings::get();
		$refused  = array_merge(
			isset( $settings['past_statuses'] ) ? (array) $settings['past_statuses'] : array(),
			array_keys( WPCPM_Program::states() )
		);

		return array(
			'tracks'           => $tracks,
			'labels'           => $labels,
			'refused_statuses' => array_values( array_unique( $refused ) ),
			'reserved_columns' => self::reserved_columns(),
			'locked'           => null,
		);
	}

	/**
	 * What the PHP says of a status, compiled tracks aside: the name, course and hours a built-in
	 * track's definition must match before it may switch (the design's decision 3.5).
	 *
	 * @param string $status Status.
	 * @return array `label`, `course_url`, `course_id` (0 for none) and `hours` (null for none).
	 */
	public static function builtin_row( $status ) {
		return self::unfiltered(
			static function () use ( $status ) {
				$hours = WPCPM_Program::hours_targets();

				return array(
					'label'      => (string) WPCPM_Program::label( $status ),
					'course_url' => (string) WPCPM_Program::course_url( $status ),
					'course_id'  => (int) WPCPM_Program::course_id( $status ),
					'hours'      => isset( $hours[ $status ] ) ? (int) $hours[ $status ] : null,
				);
			}
		);
	}

	/**
	 * The key the PHP gives a status, compiled tracks aside: one of the four built-in keys, or an
	 * empty string for a status no built-in track holds.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function builtin_key( $status ) {
		return self::unfiltered(
			static function () use ( $status ) {
				return WPCPM_Program::is_track( $status ) ? (string) WPCPM_Program::track( $status ) : '';
			}
		);
	}

	/**
	 * Run a read of the program map with no compiled track in it.
	 *
	 * @param callable $read The read.
	 * @return mixed What it returned.
	 */
	private static function unfiltered( callable $read ) {
		++self::$suspended;

		try {
			return $read();
		} finally {
			--self::$suspended;
		}
	}
}
