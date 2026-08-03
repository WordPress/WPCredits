<?php
/**
 * The mentor dashboard skin.
 *
 * The plugin renders the page; this file loads the stylesheet that dresses it and
 * hands the script the one thing the rendered markup does not carry in a form a
 * browser can compare: internship end dates as `Y-m-d`.
 *
 * Nothing here re-renders a student, invents a field, or writes anything. Every
 * value comes from the plugin's own public API for the mentor whose list is
 * already on screen.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many days ahead counts as "ending soon".
 *
 * @return int
 */
function wpcredits_ending_soon_days() {
	/**
	 * Filter the ending-soon window.
	 *
	 * @param int $days Default 60.
	 */
	return max( 1, (int) apply_filters( 'wpcredits_ending_soon_days', 60 ) );
}

/**
 * How long a student can go without a note before they need a call.
 *
 * Matches the plugin's own wording on the page — "no note in the last 30 days" —
 * so the grouping and the notice cannot contradict each other.
 *
 * @return int
 */
function wpcredits_stale_note_days() {
	/**
	 * Filter the stale-note window.
	 *
	 * @param int $days Default 30.
	 */
	return max( 1, (int) apply_filters( 'wpcredits_stale_note_days', 30 ) );
}

/*
 * The Slack link used to be the theme's job, and is not any more.
 *
 * The plugin renders the handle as a link itself, to a target its own
 * `wpcpm_slack_url` filter owns. The script's `linkSlack()` had become unreachable
 * either way — it bailed when the cell already held an `<a>`, and bailed again when
 * the cell was "Not set" — so it was doing nothing but keeping a reason to call
 * `__()` against the *plugin's* text domain, which a theme should not do: the string
 * is not in the theme's own catalog, and matching a translated label to find a
 * table row breaks on any wording change.
 *
 * `wpcredits_slack_chat_url()` and its filter went with it. Filter
 * `wpcpm_slack_url` in the plugin instead.
 */

/**
 * The mentor whose list is being rendered.
 *
 * Mirrors the plugin's own resolution — `?wpcpm_mentor=` for program managers,
 * otherwise the viewer, otherwise the first mentor for a manager with nothing
 * selected — because the plugin keeps that logic private and the script needs to
 * be handed data for the same list the page is showing. Read-only: if this ever
 * disagrees with the plugin, the effect is a student with no end date in the
 * payload, which the script treats as unknown rather than guessing.
 *
 * @return WP_User|null
 */
function wpcredits_dashboard_mentor() {
	if ( ! wpcredits_plugin_active() || ! class_exists( 'WPCPM_Roles' ) || ! is_user_logged_in() ) {
		return null;
	}

	// The plugin's own answer, whenever it has one. This used to be reimplemented here
	// because the plugin kept it private, and the copy drifted in the one way that
	// matters: it tested for the Mentor *role*, which an administrator who also mentors
	// never has — the sync refuses to touch an administrator's roles. So the plugin
	// rendered their own students while this handed the script the first mentor by name,
	// and because the two are joined on Airtable record ID, nothing matched: every row
	// lost its end date and note count and the triage bands came out wrong. Exactly the
	// bug that was reported as "administrator Isotta Peira sees no mentors or students".
	if ( method_exists( 'WPCPM_Mentors_Dashboard', 'current_mentor' ) ) {
		$mentor = WPCPM_Mentors_Dashboard::current_mentor();

		return $mentor instanceof WP_User ? $mentor : null;
	}

	// Fallback for a plugin older than that method. Deliberately still the role-only
	// test, because `is_mentor()` may not exist there either; it is wrong for
	// administrator-mentors in the way described above, and cannot be fixed from here.
	$viewer     = wp_get_current_user();
	$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );

	if ( $can_manage ) {
		// Read-only view state — which mentor a manager is inspecting — and the plugin's own
		// query var. Annotated on the read rather than on an `isset()` above it: a
		// `phpcs:ignore` only covers its own line, and one placed a line early silently
		// stops applying.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
		$requested = isset( $_GET['wpcpm_mentor'] ) ? absint( wp_unslash( $_GET['wpcpm_mentor'] ) ) : 0;
		$candidate = $requested ? get_user_by( 'id', $requested ) : null;

		if ( $candidate instanceof WP_User && WPCPM_Roles::user_has_role( $candidate, WPCPM_Roles::ROLE_MENTOR ) ) {
			return $candidate;
		}
	}

	if ( WPCPM_Roles::user_has_role( $viewer, WPCPM_Roles::ROLE_MENTOR ) ) {
		return $viewer;
	}

	if ( $can_manage ) {
		$mentors = get_users(
			array(
				'role'    => WPCPM_Roles::ROLE_MENTOR,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => 1,
			)
		);

		return ! empty( $mentors[0] ) ? $mentors[0] : null;
	}

	return null;
}

/**
 * Everything the script needs, keyed by Airtable record ID.
 *
 * The record ID is the join: the plugin gives each student card an anchor built
 * from it, so the script can match a row on screen to its row here without the
 * theme having to parse a single rendered date.
 *
 * @param WP_User $mentor Mentor whose list is on screen.
 * @return array<string,array>
 */
function wpcredits_dashboard_students( WP_User $mentor ) {
	$students = array();
	$mentees  = WPCPM_Mentors_Dashboard::get_mentees( $mentor->ID );
	$format   = (string) get_option( 'date_format' );

	foreach ( $mentees as $mentee ) {
		if ( ! is_array( $mentee ) ) {
			continue;
		}

		$record = isset( $mentee['record_id'] ) ? (string) $mentee['record_id'] : '';

		// Without a record ID the plugin omits the anchor, so there is nothing to
		// match this row to. The script leaves such a student ungrouped rather
		// than guessing which pile they belong in.
		if ( '' === $record ) {
			continue;
		}

		$end       = isset( $mentee['end'] ) ? (string) $mentee['end'] : '';
		$timestamp = ( '' !== $end ) ? strtotime( $end ) : false;

		$institution = wpcredits_resolve_linked(
			isset( $mentee['institution'] ) ? (string) $mentee['institution'] : '',
			'institutions'
		);
		$team        = wpcredits_resolve_linked(
			isset( $mentee['team'] ) ? (string) $mentee['team'] : '',
			'teams'
		);

		$students[ $record ] = array(
			'name'        => isset( $mentee['name'] ) ? (string) $mentee['name'] : '',
			'institution' => $institution,
			'team'        => $team,
			'status'      => isset( $mentee['status'] ) ? (string) $mentee['status'] : '',
			'is50h'       => ! empty( $mentee['is_50h'] ),
			// Set by the plugin from the student's Airtable status. Absent on rows
			// cached before the plugin split current from past, where everything
			// reads as current — which is what it was.
			'isPast'      => ! empty( $mentee['is_past'] ),
			'end'         => $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '',
			'endLabel'    => $timestamp ? date_i18n( $format, $timestamp ) : '',
			'notes'       => class_exists( 'WPCPM_Mentor_Notes' )
				? (int) WPCPM_Mentor_Notes::count_notes( $record )
				: 0,
			// Everything a mentor might type into the search box. Built here so the
			// script never has to scrape the rendered row for it.
			'search'      => wpcredits_search_haystack( $mentee, $institution, $team ),
		);
	}

	return $students;
}

/**
 * Turn a stored Airtable linked-record value into the name it points at.
 *
 * Rows cached before the plugin resolved linked records still hold raw record
 * IDs, which is why the plugin resolves them again at render time and why this
 * does too — showing "recA1B2C3" in a column would be worse than showing nothing.
 * Guarded on the method so a future plugin version dropping it degrades to the
 * stored value rather than a fatal.
 *
 * @param string $value Stored value.
 * @param string $type  Either `institutions` or `teams`.
 * @return string
 */
function wpcredits_resolve_linked( $value, $type ) {
	if ( class_exists( 'WPCPM_Mentors_Sync' ) && method_exists( 'WPCPM_Mentors_Sync', 'resolve_stored' ) ) {
		return (string) WPCPM_Mentors_Sync::resolve_stored( $value, $type );
	}

	return (string) $value;
}

/**
 * The searchable text for one student.
 *
 * @param array  $mentee      Student row.
 * @param string $institution Resolved institution name.
 * @param string $team        Resolved team name.
 * @return string Lowercased, space separated.
 */
function wpcredits_search_haystack( array $mentee, $institution, $team ) {
	$parts = array(
		isset( $mentee['name'] ) ? $mentee['name'] : '',
		$institution,
		$team,
		isset( $mentee['status'] ) ? $mentee['status'] : '',
		isset( $mentee['username'] ) ? $mentee['username'] : '',
		isset( $mentee['email'] ) ? $mentee['email'] : '',
		isset( $mentee['slack'] ) ? $mentee['slack'] : '',
		isset( $mentee['tutor'] ) ? $mentee['tutor'] : '',
	);

	$parts = array_filter( array_map( 'trim', array_map( 'strval', $parts ) ), 'strlen' );

	return function_exists( 'mb_strtolower' )
		? mb_strtolower( implode( ' ', $parts ) )
		: strtolower( implode( ' ', $parts ) );
}

/**
 * Load the skin and the script on the mentor page.
 *
 * Registered at priority 20 so the plugin's own stylesheet is already registered
 * and can be declared a dependency. Without that the plugin's CSS — enqueued from
 * inside its render callback, and therefore printed late — would land after this
 * one and win every tie.
 */
function wpcredits_dashboard_assets() {
	// Styles dress the shell both pages share; the script only has work to do on
	// the mentor page, whose list it regroups.
	if ( ! wpcredits_is_dashboard_page() ) {
		return;
	}

	$deps = array( 'wpcredits-style' );

	if ( wp_style_is( WPCPM_Mentors_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Mentors_Dashboard::STYLE;
	}

	if ( class_exists( 'WPCPM_Students_Dashboard' ) && wp_style_is( WPCPM_Students_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Students_Dashboard::STYLE;
	}

	// The call calendar, for the same reason as the two above — and it is the sharpest
	// case of it. The plugin enqueues this one from inside a render callback that runs
	// during `the_content`, long after this hook, so without the dependency it prints
	// *after* the theme's sheet and wins every tie. Nothing looks wrong today only
	// because every theme rule for the calendar is prefixed `.wpc-dashboard-page` and
	// outweighs the plugin's single class; one unprefixed rule added later would lose
	// silently, which is the worst way to find this out.
	if ( class_exists( 'WPCPM_Call_Calendar' ) && wp_style_is( WPCPM_Call_Calendar::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Call_Calendar::STYLE;
	}

	wp_enqueue_style(
		'wpcredits-dashboard',
		get_theme_file_uri( 'assets/css/dashboard.css' ),
		$deps,
		WPCREDITS_VERSION
	);

	wp_enqueue_style(
		'wpcredits-dashboard-print',
		get_theme_file_uri( 'assets/css/print.css' ),
		array( 'wpcredits-dashboard' ),
		WPCREDITS_VERSION,
		'print'
	);

	if ( ! wpcredits_is_mentor_page() ) {
		return;
	}

	$mentor = wpcredits_dashboard_mentor();

	if ( ! $mentor instanceof WP_User ) {
		return;
	}

	wp_enqueue_script(
		'wpcredits-dashboard',
		get_theme_file_uri( 'assets/js/dashboard.js' ),
		array(),
		WPCREDITS_VERSION,
		true
	);

	wp_localize_script( 'wpcredits-dashboard', 'wpcreditsDashboard', wpcredits_dashboard_data( $mentor ) );
}
add_action( 'wp_enqueue_scripts', 'wpcredits_dashboard_assets', 20 );

/**
 * The payload handed to the script.
 *
 * @param WP_User $mentor Mentor whose list is on screen.
 * @return array
 */
function wpcredits_dashboard_data( WP_User $mentor ) {
	return array(
		'students' => wpcredits_dashboard_students( $mentor ),
		// The site's today, not the browser's: a mentor traveling should see the
		// same grouping as the program manager looking at the same list.
		'today'    => wp_date( 'Y-m-d' ),
		'windows'  => array(
			'endingSoon' => wpcredits_ending_soon_days(),
			'staleNote'  => wpcredits_stale_note_days(),
		),
		'groups'   => array(
			// Order matters: a student falls into the first group they match, so a
			// student who needs a call is never filed under "ending soon" instead.
			array(
				'key'   => 'call',
				'label' => __( 'Need a call', 'wpcredits-theme' ),
			),
			array(
				'key'   => 'ending',
				'label' => __( 'Ending soon', 'wpcredits-theme' ),
			),
			array(
				'key'   => 'ok',
				'label' => __( 'On track', 'wpcredits-theme' ),
			),
		),
		'i18n'     => array(
			'searchLabel'   => __( 'Search students', 'wpcredits-theme' ),
			'searchHint'    => __( 'Search students, institutions or teams', 'wpcredits-theme' ),
			'clearSearch'   => __( 'Clear the search', 'wpcredits-theme' ),
			/* translators: 1: matching students, 2: total students. */
			'matchCount'    => __( '%1$s of %2$s students match', 'wpcredits-theme' ),
			'noMatches'     => __( 'No students match that search.', 'wpcredits-theme' ),
			/* translators: %s: number of matches within one group. */
			'groupMatches'  => __( '%s match', 'wpcredits-theme' ),
			'collapsedHint' => __( 'Some browsers do not search inside collapsed sections with Ctrl+F. Use Expand all first if you are looking for something specific.', 'wpcredits-theme' ),
			'noNotes'       => __( 'No notes', 'wpcredits-theme' ),
			'addNote'       => __( 'Add a note', 'wpcredits-theme' ),
			'details'       => __( 'Details', 'wpcredits-theme' ),
			/* translators: %s: number of days. */
			'daysLeft'      => __( '%s days', 'wpcredits-theme' ),
			'endedAlready'  => __( 'Ended', 'wpcredits-theme' ),
			'needCall'      => __( 'need a call', 'wpcredits-theme' ),
			'endingSoon'    => __( 'ending soon', 'wpcredits-theme' ),
			'onTrack'       => __( 'on track', 'wpcredits-theme' ),
			'showAll'       => __( 'Show all students', 'wpcredits-theme' ),
			'ordering'      => __( 'Ordered by internship end date within each group, soonest first.', 'wpcredits-theme' ),
			/* translators: %s: student's name. */
			'noteFor'       => __( 'Add a note for %s', 'wpcredits-theme' ),
			/* translators: %s: internship end date. */
			'until'         => __( 'until %s', 'wpcredits-theme' ),
		),
		'icons'    => array(
			'search' => wpcredits_get_icon( 'search', 16 ),
			'close'  => wpcredits_get_icon( 'close', 15 ),
			'people' => wpcredits_get_icon( 'people', 20 ),
		),
	);
}

/**
 * Whether the handbook assistant is available to whoever is reading this page.
 *
 * One question, asked of the plugin, rather than the theme reimplementing the answer. The
 * plugin's own check already covers the assistant being switched off, the handbook never
 * having been synced, the reader being logged out and the configured audience — and any of
 * those reimplemented here would be a second copy to keep in step, which is exactly how the
 * mentor-page mismatch happened.
 *
 * The guard is `method_exists`, not `class_exists`: the class has existed since the
 * assistant shipped, but a site on an older plugin has it without this method.
 *
 * @return bool
 */
function wpcredits_handbook_available() {
	if ( ! method_exists( 'WPCPM_Handbook_Assistant', 'is_available' ) ) {
		return false;
	}

	return (bool) WPCPM_Handbook_Assistant::is_available();
}
