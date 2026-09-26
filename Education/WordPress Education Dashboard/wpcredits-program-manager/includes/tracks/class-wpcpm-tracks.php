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
 * **The index is the program map's only source.** `WPCPM_Program`'s five maps start empty and
 * the filters here fill them, one row per live track, so a status the index does not hold is on no
 * track: no name, no course and no hours target, and its students read the 150-hour track's form,
 * as every student on no track does (the design's decision 34).
 *
 * The program's four original tracks keep their statuses and keys by `RESERVED_PAIRS`, the lock
 * the store checks them with (the design's decision 36): no other track may hold one of those
 * statuses under another key, or one of those keys under another status.
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
	 * The program's four original tracks: each one's Airtable status against its key.
	 *
	 * The lock that keeps them compiling (the design's decision 36). `WPCPM_Track_Store` locks a
	 * definition holding one of these statuses to its key, published or not and whatever the post
	 * carries. A definition holding one of the statuses under another key is refused as
	 * `status_reserved`, and one holding one of the keys under another status as `key_reserved`
	 * (`WPCPM_Track_Definition`); a second definition claiming a whole pair passes both, and is
	 * refused by the original track's own post, which holds the pair (`status_taken`, `key_taken`).
	 * Written out rather than read from the program map, because the map is what a compile fills: a
	 * lock read from it would hold a track only while something else named it, and every compile, a
	 * Settings save included, would leave all four out as holding reserved keys (the final review of
	 * T2a). Held to the seed files by bin/test-tracks.php and bin/test-track-definitions.php.
	 */
	const RESERVED_PAIRS = array(
		WPCPM_Program::STATUS_150H   => '150h',
		WPCPM_Program::STATUS_50H    => '50h',
		WPCPM_Program::STATUS_DEV    => 'dev',
		WPCPM_Program::STATUS_DESIGN => 'design',
	);

	/**
	 * The four original tracks' names, exactly as their seeds in `includes/tracks/seeds/` spell them.
	 *
	 * What `validation_context()` names one of them by while no live row does, so a new track is
	 * kept off the four names as it is off their statuses. Held to the seed files by
	 * bin/test-tracks.php.
	 */
	const RESERVED_LABELS = array(
		WPCPM_Program::STATUS_150H   => 'WordPress Credits Program 150h',
		WPCPM_Program::STATUS_50H    => 'WordPress Credits Program 50h',
		WPCPM_Program::STATUS_DEV    => 'Developer Track',
		WPCPM_Program::STATUS_DESIGN => 'Designer Track',
	);

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
	 * Every compiled track, as the index holds it.
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
	 * The tracks the site runs: every compiled row with a key.
	 *
	 * **A row's `source` is not read.** A compile no longer writes one, since every track runs from
	 * its definition, but the rows a site compiled before carry it: `definition` on a track that was
	 * switched to its definition, and `builtin` on one published and never switched, whose published
	 * copy the preflight held to the hand-written form it ran from. Each is read as the rows a compile
	 * writes now, so no track leaves the program map between an upgrade and the next compile: a read
	 * that still wanted `definition` would drop every row the moment a compile stopped writing it.
	 *
	 * @return array<string, array> Status => row.
	 */
	public static function live() {
		return array_filter(
			self::rows(),
			static function ( $row ) {
				return is_array( $row ) && isset( $row['key'] );
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
	 * The map starts empty, so taking away matters only for a link another filter put there for the
	 * status: the definition is what the track is, and a course it does not name must not survive.
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
	 * The form a track key reads: the live track's own, or the 150-hour track's for any other key.
	 *
	 * `WPCPM_Student_Report_Form::fields()` hands this an empty form, and this is where every form
	 * comes from. **A key no live track holds reads the 150-hour track's compiled form**, the one
	 * most students on no track filled in, which is what such a key read while the hand-written
	 * 150-hour set was the fallback (the design's decision 34). With the 150-hour track not live
	 * either, what it was handed comes back: nothing, from `fields()`.
	 *
	 * **A live track whose form option is missing gets an empty form, never another track's.**
	 * Drawing the 150-hour form for an authored track would have its students writing another track's
	 * columns, and an empty form writes nothing.
	 *
	 * @param array  $fields What `fields()` handed over: an empty form.
	 * @param string $track  Track key.
	 * @return array
	 */
	public static function filter_fields( $fields, $track ) {
		$keys = array();

		foreach ( self::live() as $row ) {
			$keys[] = (string) $row['key'];
		}

		if ( in_array( (string) $track, $keys, true ) ) {
			return self::form( (string) $track );
		}

		$fallback = self::RESERVED_PAIRS[ WPCPM_Program::STATUS_150H ];

		return in_array( $fallback, $keys, true ) ? self::form( $fallback ) : $fields;
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
	 * The live tracks whose reports automation item is ticked, by a person or by the site when it
	 * published one of the four original tracks (`WPCPM_Track_Store::META_AUTOMATION`).
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
	 * None for the reserved keys, the four original tracks' among them: their rules are written into
	 * the plugin's stylesheet and the theme's.
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
	 * The key one of the four original tracks' statuses is locked to.
	 *
	 * The status is trimmed first, as every read of one is, and then compared exactly, as the program
	 * map compares one: a status a capital away is not one of the four, and `status_taken`, which
	 * folds case, is what keeps a new track off it.
	 *
	 * @param string $status Airtable status.
	 * @return string `150h`, `50h`, `dev` or `design`, or an empty string for any other status.
	 */
	public static function reserved_key( $status ) {
		$status = trim( (string) $status );

		return isset( self::RESERVED_PAIRS[ $status ] ) ? self::RESERVED_PAIRS[ $status ] : '';
	}

	/**
	 * One of the four original tracks' names, as its seed spells it.
	 *
	 * @param string $status Airtable status.
	 * @return string The name, or an empty string for any other status.
	 */
	public static function reserved_label( $status ) {
		$status = trim( (string) $status );

		return isset( self::RESERVED_LABELS[ $status ] ) ? self::RESERVED_LABELS[ $status ] : '';
	}

	/**
	 * Whether a status is one of the four original tracks'.
	 *
	 * @param string $status Airtable status.
	 * @return bool
	 */
	public static function is_reserved( $status ) {
		return '' !== self::reserved_key( $status );
	}

	/**
	 * What `WPCPM_Track_Definition::validate()` needs to know about the site.
	 *
	 * **The four original tracks come from `RESERVED_PAIRS`, not from the program map** (the
	 * design's decision 36): each status against its key, named by the live row the site runs it
	 * from, or by its seed (`RESERVED_LABELS`) while there is none. The map is what a compile fills,
	 * so a context read from it alone would lose the four whenever they are not compiled, and with
	 * them what keeps a new track off their statuses and names. With `$compiled`, every track the map
	 * knows is written after them; for any of the four the map holds that rewrites the same key and
	 * name, since the lock compiles each on its own key and the map names each as this does.
	 *
	 * `$own_status` leaves that status out of the others unconditionally, which is the hole the
	 * T2a plan's decision 4 describes: `status_taken` then says nothing of it, so a new track could
	 * take it under a key of its own, unless it is one of the four original statuses, which
	 * `status_reserved` refuses whatever the context holds. The store's `context()` is the one that
	 * locks, leaving a track's own status out only when the track is locked to it, and no caller in
	 * the plugin passes a status now.
	 *
	 * @param string $own_status The status of the track being checked, left out of the others.
	 * @param bool   $compiled   False to leave out every track the site runs but the four original
	 *                           tracks, which is how the store checks a track against the others it
	 *                           compiles.
	 * @return array
	 */
	public static function validation_context( $own_status = '', $compiled = true ) {
		$tracks = array();
		$labels = array();
		$live   = self::live();

		foreach ( self::RESERVED_PAIRS as $status => $key ) {
			$tracks[ $status ] = $key;
			$labels[ $status ] = isset( $live[ $status ]['label'] ) ? (string) $live[ $status ]['label'] : self::reserved_label( $status );
		}

		if ( $compiled ) {
			foreach ( WPCPM_Program::labels() as $status => $label ) {
				$tracks[ $status ] = WPCPM_Program::track( $status );
				$labels[ $status ] = (string) $label;
			}
		}

		unset( $tracks[ (string) $own_status ], $labels[ (string) $own_status ] );

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
}
