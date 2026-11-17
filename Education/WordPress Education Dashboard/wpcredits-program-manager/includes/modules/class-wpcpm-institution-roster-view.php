<?php
/**
 * Institutions module - the roster an institution reads about its own students.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The cohort picker, the comparison strip, the filter bar and the four roster groups.
 *
 * Everything drawn here comes from `WPCPM_Roster_Index` and from the two cached blocks of
 * user meta the students sync writes. No render reaches Airtable: a roster is a page a
 * school refreshes, and a live read per render would page the whole Students table on
 * every visit. That is also why every count on this screen prints the index's read time.
 * A stale number that looks fresh is worse than an honest "read four hours ago", and an
 * institution comparing two semesters is doing arithmetic on numbers whose age it has to
 * be able to see.
 *
 * **`accessibility` is absent from every column.** It is on the Students table and it is
 * inside `wpcpm_student_program`, so both sources this class reads carry it; it was
 * disclosed to the program and not to the school. `columns()` is the whole list of what is
 * printed, every cell is built from that list, and `bin/test-institution-roster-view.php`
 * renders a fixture student who has a disclosure and asserts the value appears nowhere.
 *
 * The cohort, the group and the search term are GET arguments read before anything is
 * drawn, so a filtered roster is a URL a colleague can be sent and lands on the same rows.
 * Nothing here writes, so nothing here checks a nonce; what it does check is the fence,
 * through `decide( ACT_VIEW_ROSTER )` in the render-from-cache pattern of design spec 5.4,
 * because a renderer that trusts its caller is a renderer that leaks the first time
 * somebody calls it from somewhere new.
 */
class WPCPM_Institution_Roster_View {

	/** The cohort picker's GET argument (design spec 7.7). Validated with `WPCPM_Cohort::is_key()`. */
	const ARG_COHORT = 'wpcpm_cohort';

	/** The group filter's GET argument: one of `group_labels()`'s keys, or empty for all of them. */
	const ARG_STATUS = 'wpcpm_roster_status';

	/** The search box's GET argument. */
	const ARG_SEARCH = 'wpcpm_roster_search';

	/** The detail view's GET argument, which the student column links to (design spec 7.5). */
	const ARG_STUDENT = 'wpcpm_institution_student';

	/**
	 * The manager switcher's argument, owned by `WPCPM_Institution_Roster` (design spec 5.5).
	 *
	 * Named here only so the filter form can carry it: a GET form does not resubmit the query
	 * string it was drawn in, and without this hidden field a manager who filtered the roster
	 * of the institution they had switched to would be dropped back on their own default one.
	 * Nothing in this class decides anything from it.
	 */
	const ARG_VIEW = 'wpcpm_institution_view';

	/**
	 * The Institution Dashboard's view argument, and the view this roster is drawn in: the filter form carries both on
	 * the parts (design 6.1), so a filtered roster lands back on the Students view. Named here and not read off the
	 * dashboard's class, which this one does not need loaded.
	 */
	const ARG_DASHBOARD_VIEW = 'wpcpm_view';
	const DASHBOARD_VIEW     = 'students';

	/** How much of a search term is read. Longer than any name, address or tutor in the base. */
	const SEARCH_MAX = 100;

	/**
	 * Draw the roster for one institution.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $context   `can_manage` (bool), `cohort` (string), `filters` (array) and
	 *                          `read` (int). Each is read from the request when the caller
	 *                          does not supply it, so this renders correctly on its own.
	 */
	public static function render( $record_id, array $context ) {
		self::$manages = ! empty( $context['can_manage'] );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$record_id = trim( (string) $record_id );

		// The render-from-cache pattern of design spec 5.4. The dashboard has already asked
		// the same question, and asking it again costs one array walk: the fence is the only
		// thing between an index option and a school, and it is not the caller's to skip.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_VIEW_ROSTER,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			// The empty state, never a list and never the refusal text: a message that told a
			// refused viewer *why* would answer a question they are not entitled to ask.
			printf(
				'<p class="wpcpm-roster__empty">%s</p>',
				esc_html__( 'There are no students to show here yet.', 'wpcredits-program-manager' )
			);

			return;
		}

		// One student's card instead of the list, when the request asks for one and the fence
		// allows it. A user ID the fence refuses, whether a stranger's or another institution's
		// or an account that is not a student at all, falls through to the roster rather than to
		// an error: "no such student" and "not your student" have to read the same way from
		// outside.
		if ( class_exists( 'WPCPM_Institution_Student_View' ) ) {
			$student = self::card_student();

			if ( $student > 0 ) {
				WPCPM_Institution_Student_View::render( $record_id, $student, $context );

				return;
			}
		}

		// A narrowed list the Overview's row opens (1.123.98), in place of the roster, when the request names one the reader
		// is offered and it holds a student; otherwise the roster as ever.
		$only = self::only_from_request( $record_id );

		if ( '' !== $only ) {
			self::render_only( $record_id, $only, $context );

			return;
		}

		$columns = WPCPM_Institution_Policy::scope( $decision, self::columns() );
		$rows    = WPCPM_Roster_Index::rows( $record_id );
		$read    = isset( $context['read'] ) ? (int) $context['read'] : (int) WPCPM_Roster_Index::read( $record_id )['read'];
		$filters = ( isset( $context['filters'] ) && is_array( $context['filters'] ) )
			? self::clean_filters( $context['filters'] )
			: self::filters_from_request();

		$cohorts = self::cohort_counts( $rows );
		$cohort  = ( isset( $context['cohort'] ) && WPCPM_Cohort::is_key( $context['cohort'] ) )
			? (string) $context['cohort']
			: self::cohort_from_request();

		if ( '' === $cohort ) {
			$cohort = self::opening_cohort( $record_id );
		}

		// The chosen cohort is always an option, even when nobody in it signed up, so the
		// picker can never show a selection the reader cannot see. Sorted again once it is
		// in: it was added to a list that had already been ordered, and left where it landed
		// it would print after "No start date" whatever semester it is.
		if ( ! isset( $cohorts[ $cohort ] ) ) {
			$cohorts[ $cohort ] = WPCPM_Cohort::participation( $rows, $cohort )['signed_up'];
			$cohorts            = self::sort_cohorts( $cohorts );
		}

		$here = remove_query_arg( self::ARG_STUDENT );

		// Drawn with the shared parts (Institution Dashboard, Task 15): the wrapper carries no inset of its own, so the
		// view's content starts at its head's left edge, and the anchor the view's card is linked by (`#wpcpm-roster`).
		echo '<section class="wpcpm-roster__view" id="wpcpm-roster">';

		// The Institution Dashboard titles the module this roster sits in (1.96.4); anywhere
		// else the roster names itself.
		if ( empty( $context['module_titled'] ) ) {
			echo WPCPM_UI::heading( __( 'Students', 'wpcredits-program-manager' ), 'h2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().
		}

		self::render_filters_parts( $record_id, $cohorts, $cohort, $filters, ! empty( $context['can_manage'] ) );
		self::render_summary( $rows, $cohort );

		$groups = WPCPM_Roster_Index::groups( $record_id, $cohort );

		foreach ( self::group_labels() as $key => $label ) {
			if ( '' !== $filters['status'] && $filters['status'] !== $key ) {
				continue;
			}

			$group = ( isset( $groups[ $key ] ) && is_array( $groups[ $key ] ) ) ? $groups[ $key ] : array();

			if ( '' !== $filters['search'] ) {
				$group = self::narrow( $group, $filters['search'] );
			}

			self::render_group_parts( $key, $label, $group, $columns, $here, $filters );
		}

		// The fifth list is nobody's group: these students have no Students row, so they have
		// no status and no cohort to be filed under. A group filter is a request for one of
		// the four, and the list stays out of the answer rather than pretending to be a fifth
		// bucket of it. The search still narrows it, because a search is a search.
		if ( '' === $filters['status'] ) {
			self::render_unlinked_parts( WPCPM_Roster_Index::unlinked_for( $record_id ), $filters['search'] );
		}

		// The Institution Dashboard prints the read line once, at the foot of the page, when it
		// titles the module this roster sits in (1.96.5); anywhere else the roster closes with it.
		if ( empty( $context['module_titled'] ) ) {
			self::read_line( $read, 'wpcpm-roster__read wpcpm-roster__read--footer' );
		}

		echo '</section>';
	}

	/**
	 * The narrowed list the request names (`WPCPM_Institutions_Dashboard::ONLY_ARG`), when it is one of the four, the
	 * reader is offered it (`only_offered()`: a tutor is not offered the two that wait) and it holds a student; '' for
	 * anything else, which draws the ordinary roster.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string A key of `WPCPM_Institutions_Dashboard::ONLY_KEYS`, or ''.
	 */
	private static function only_from_request( $record_id ) {
		if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
			return '';
		}

		$key    = WPCPM_Request::key( WPCPM_Institutions_Dashboard::ONLY_ARG );
		$viewer = wp_get_current_user();

		if ( ! in_array( $key, WPCPM_Institutions_Dashboard::ONLY_KEYS, true ) || ! $viewer instanceof WP_User || ! WPCPM_Institutions_Dashboard::only_offered( $key, (string) $record_id, $viewer, self::$manages ) ) {
			return '';
		}

		return array() === WPCPM_Institutions_Dashboard::only_rows( (string) $record_id, $key ) ? '' : $key;
	}

	/**
	 * One narrowed list on the parts (1.123.98), in the roster's place and under its anchor: one sentence that says what
	 * the list is, in its Overview row's words with its count, then "Show all students", a quiet link to the Students
	 * view; then one list, a student per row: the name (to the student's card when there is an account), the program as
	 * the roster's tag, and as facts the cohort and the dates. No filter bar, no groups, no cohort picker.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $key       A key of `WPCPM_Institutions_Dashboard::ONLY_KEYS`.
	 * @param array  $context   What the roster is handed.
	 */
	private static function render_only( $record_id, $key, array $context ) {
		$rows    = WPCPM_Institutions_Dashboard::only_rows( (string) $record_id, $key );
		$here    = remove_query_arg( self::ARG_STUDENT );
		$managed = self::$manages ? (string) $record_id : '';

		// The list's own class keys the narrow form of its rows (a name keeps its line, its tag goes under it).
		echo '<section class="wpcpm-roster__view wpcpm-roster__view--only" id="wpcpm-roster">';
		// The way back to the whole roster: a quiet link in a line of its own, at the text's edge, as the people card's
		// link to the Institutions screen is (a quiet button's own padding set its words 8px in from the lines around it).
		$all = WPCPM_UI::intro( '', '', '', sprintf( '<a href="%1$s">%2$s</a>', esc_url( WPCPM_Institutions_Dashboard::view_url( WPCPM_Institutions_Dashboard::VIEW_STUDENTS, $managed ) ), esc_html__( 'Show all students', 'wpcredits-program-manager' ) ) );

		// The list of students who have not signed in yet counts accounts made since the sign-in record began alone, and
		// says so, with the day the record began (fix round 1, B2): the record's day, never a student's.
		$record_line = '';

		if ( WPCPM_Institutions_Dashboard::ONLY_NO_SIGN_IN === $key && WPCPM_Sign_Ins::since() > 0 ) {
			/* translators: %s: the day the site began recording sign-ins, in the site's date format. */
			$record_line = WPCPM_UI::intro( '', 'muted', '', sprintf( esc_html__( 'Accounts made before %s are not counted: the site kept no sign-in record before then.', 'wpcredits-program-manager' ), WPCPM_UI::whole( (string) wp_date( (string) get_option( 'date_format' ), WPCPM_Sign_Ins::since() ) ) ) );
		}

		echo WPCPM_UI::intro( WPCPM_Institutions_Dashboard::only_title( $key, count( $rows ) ) . '.' ) . $record_line . $all; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.

		$list = WPCPM_UI::list_open( array( 'facts' => 'under' ) );

		foreach ( $rows as $row ) {
			$name    = trim( (string) ( $row['name'] ?? '' ) );
			$name    = '' === $name ? __( 'Unnamed student', 'wpcredits-program-manager' ) : $name;
			$user_id = (int) ( $row['user_id'] ?? 0 );
			$start   = (string) ( $row['start'] ?? '' );
			$program = self::program_of( $row );
			$facts   = array();

			if ( '' !== trim( $start ) ) {
				$facts[] = WPCPM_Cohort::label( WPCPM_Cohort::key( $start ) );
			}

			$facts[] = array( 'html' => self::dates_line( trim( $start ), trim( (string) ( $row['end'] ?? '' ) ) ) );

			$list .= WPCPM_UI::row(
				array(
					'name'  => $name,
					// A row whose account no longer exists has no card to open: its name is drawn without a link.
					'href'  => $user_id > 0 && get_userdata( $user_id ) instanceof WP_User ? (string) add_query_arg( self::ARG_STUDENT, $user_id, $here ) : '',
					'tag'   => '' === $program ? null : array(
						'words' => WPCPM_Program::status_words( $program ),
						'tone'  => WPCPM_Program::tone( $program ),
					),
					'facts' => $facts,
				)
			);
		}

		echo $list . WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row().

		// The Institution Dashboard prints the read line once, at the foot of the page, as for the roster.
		if ( empty( $context['module_titled'] ) ) {
			self::read_line( (int) WPCPM_Roster_Index::read( $record_id )['read'], 'wpcpm-roster__read wpcpm-roster__read--footer' );
		}

		echo '</section>';
	}

	/**
	 * The cohort the request asks for, or an empty string when it asks for nothing usable.
	 *
	 * Public so the dashboard can fill `$context['cohort']` with the same answer this class
	 * would reach on its own, rather than two places parsing one argument differently.
	 *
	 * @return string A cohort key, or ''.
	 */
	public static function cohort_from_request() {
		$asked = WPCPM_Request::text( self::ARG_COHORT );

		return WPCPM_Cohort::is_key( $asked ) ? $asked : '';
	}

	/**
	 * The group and the search term the request asks for.
	 *
	 * Public for the same reason as `cohort_from_request()`.
	 *
	 * @return array{status: string, search: string}
	 */
	public static function filters_from_request() {
		return self::clean_filters(
			array(
				'status' => WPCPM_Request::key( self::ARG_STATUS ),
				'search' => WPCPM_Request::text( self::ARG_SEARCH ),
			)
		);
	}

	/**
	 * The four groups, in the order design spec 7.5 lists them, with the names it gives them.
	 *
	 * "Not on the program now" is the honest name for the last one: it holds the students who have
	 * not started yet, the applicants who never began (the first question an institution asks
	 * about a cohort) and the students who stopped before finishing. Only the heading is plain;
	 * the group's key and the statuses filed in it are the base's own words.
	 *
	 * @return array<string, string> Group key to heading.
	 */
	public static function group_labels() {
		return array(
			'current'     => __( 'Current', 'wpcredits-program-manager' ),
			'waiting'     => __( 'Waiting for a mentor', 'wpcredits-program-manager' ),
			'finished'    => __( 'Finished', 'wpcredits-program-manager' ),
			'not_started' => __( 'Not on the program now', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Every column the roster prints, keyed `<table>|<column>` as the fence's `scope()` is.
	 *
	 * The list is design spec 7.5's, and what is *not* in it is the point: there is no
	 * `students|Accessibility needs` row, and adding one is a visible diff against a failing
	 * assertion. `Tutor ` carries its trailing space because that is the column's name in the
	 * base, the same way design spec 7.8's edit allowlist spells it.
	 *
	 * Two columns name the cell they derive from rather than one of their own: `Dates` prints
	 * the start and the end together, and `Days left` counts from the end date. A ground that
	 * one day scopes fields would have to split the dates cell to drop an end date; nothing
	 * shipped scopes them, and `scope()` returns the whole list for every ground there is.
	 *
	 * Hours are `reports|Hours`, named for the table and the column the value is read from,
	 * the way every other key here is. What the cell says is `hours_cell()`'s decision and not
	 * this list's: a track worked to no target prints no denominator, and a student nobody has
	 * logged for prints the same "Not recorded" every other empty cell does, because an hours
	 * column that read "0" for them would say they had done nothing.
	 *
	 * @return array<string, string> Column key to heading.
	 */
	public static function columns() {
		return array(
			'students|Full Name'             => __( 'Student', 'wpcredits-program-manager' ),
			'students|Status'                => __( 'Program', 'wpcredits-program-manager' ),
			'students|Start Date'            => __( 'Dates', 'wpcredits-program-manager' ),
			'students|End Date'              => __( 'Days left', 'wpcredits-program-manager' ),
			'students|Mentor'                => __( 'Mentor', 'wpcredits-program-manager' ),
			'students|WP Profile'            => __( 'WordPress.org', 'wpcredits-program-manager' ),
			'reports|Main Contribution Team' => __( 'Team', 'wpcredits-program-manager' ),
			'reports|Personal Website URL'   => __( 'Website', 'wpcredits-program-manager' ),
			'students|Your field of study'   => __( 'Field of study', 'wpcredits-program-manager' ),
			'students|Tutor '                => __( 'Tutor', 'wpcredits-program-manager' ),
			'reports|Hours'                  => __( 'Hours', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * How many students signed up in each cohort the institution has, newest first.
	 *
	 * Counted through `WPCPM_Cohort::participation()` so the picker, the strip and the
	 * semester report cannot disagree about what a cohort holds: SPAM, Duplicated and
	 * Interested rows are not people who signed up, and they are excluded once, there.
	 *
	 * A cohort nobody signed up in is dropped. It would be an option that draws an empty
	 * roster, and the only way to have one is a bucket holding nothing but rows this program
	 * does not count. "No start date" is subject to the same rule, which is design spec
	 * 7.7's "only when n is above zero".
	 *
	 * @param array $rows The institution's index rows.
	 * @return array<string, int> Cohort key to the number who signed up, NONE last.
	 */
	public static function cohort_counts( array $rows ) {
		$counts = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' );

			if ( isset( $counts[ $key ] ) ) {
				continue;
			}

			$signed_up = WPCPM_Cohort::participation( $rows, $key )['signed_up'];

			if ( $signed_up > 0 ) {
				$counts[ $key ] = $signed_up;
			}
		}

		return self::sort_cohorts( $counts );
	}

	/**
	 * The picker's order: semesters newest first, then "No start date".
	 *
	 * Its own method because the list is added to after it is counted. `render()` puts the
	 * chosen cohort in when nobody signed up in it, and a cohort appended to a sorted list
	 * lands after "No start date", which is not the order design spec 7.7 asks for and reads
	 * as a bug in the picker rather than as a cohort that happens to be empty. Both paths
	 * sort through this one comparison instead.
	 *
	 * @param array $counts Cohort key to the number who signed up.
	 * @return array<string, int> The same counts, in the order the picker prints them.
	 */
	private static function sort_cohorts( array $counts ) {
		uksort(
			$counts,
			static function ( $a, $b ) {
				// NONE is not a semester, so it sorts last whichever way round the comparison
				// runs; the semesters themselves are reversed, newest first.
				if ( WPCPM_Cohort::NONE === $a || WPCPM_Cohort::NONE === $b ) {
					return WPCPM_Cohort::compare( $a, $b );
				}

				return WPCPM_Cohort::compare( $b, $a );
			}
		);

		return $counts;
	}

	/**
	 * The cohort the Students view opens on when its address names none: `default_cohort()` over the school's own
	 * rows. The Institution Dashboard's Overview counts its figures in this cohort, so the figures and the roster a
	 * click away read one answer.
	 *
	 * @param string $record Institutions record ID.
	 * @return string A cohort key.
	 */
	public static function opening_cohort( string $record ): string {
		return (string) self::default_cohort( self::cohort_counts( WPCPM_Roster_Index::rows( $record ) ) );
	}

	/**
	 * Which cohort a reader who asked for none is shown.
	 *
	 * This semester when the institution has students in it, and otherwise the newest one it
	 * has: an institution whose intake was last February should not open on an empty page in
	 * September. With no rows at all it is this semester, so the picker still names today.
	 *
	 * @param array $counts What `cohort_counts()` returned.
	 * @return string A cohort key.
	 */
	private static function default_cohort( array $counts ) {
		$current = WPCPM_Cohort::current();

		if ( isset( $counts[ $current ] ) ) {
			return $current;
		}

		$keys = array_keys( $counts );

		return isset( $keys[0] ) ? (string) $keys[0] : $current;
	}

	/**
	 * The filter form's hidden fields: the page's ID where the site has no pretty addresses, and the
	 * school an Administrator is viewing, carried by an Administrator's form alone.
	 *
	 * @param bool $can_manage Whether the viewer is an Administrator.
	 */
	private static function hidden_fields( $can_manage ) {
		// Without pretty permalinks the page is addressed by query string, which a GET form
		// would otherwise discard, resubmitting to the site root. The same guard the mentor
		// switcher carries, for the same reason.
		if ( ! get_option( 'permalink_structure' ) ) {
			$queried = get_queried_object_id();

			if ( $queried ) {
				printf( '<input type="hidden" name="page_id" value="%d" />', (int) $queried );
			}
		}

		// Only a manager's switcher argument is honored, so only a manager's form carries
		// one. A member who arrived with one in the URL had it ignored, and echoing it back
		// into their form would put a record ID nobody answered for into every link they
		// send on from here.
		$view = $can_manage ? WPCPM_Request::text( self::ARG_VIEW ) : '';

		if ( WPCPM_Mentors_Sync::is_record_id( $view ) ) {
			printf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				esc_attr( self::ARG_VIEW ),
				esc_attr( $view )
			);
		}
	}

	/**
	 * The roster export's address when the export's own fence allows it, '' otherwise: asked of `ACT_EXPORT` and not
	 * of the roster's own answer, because a tutor who may read the list may still be refused the file.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $cohort    The chosen cohort.
	 * @return string
	 */
	private static function export_url( $record_id, $cohort ) {
		// `wpcredits-program-manager.php` requires this file before it requires the export
		// module, so the class is not defined while this one is being loaded. The same guard
		// the student detail view is called behind, for the same reason: a missing link costs a
		// school one convenience, an undefined class costs it the whole dashboard.
		if ( ! class_exists( 'WPCPM_Institution_Export' ) ) {
			return '';
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EXPORT,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			return '';
		}

		return (string) WPCPM_Institution_Export::roster_url( $cohort );
	}

	/**
	 * The filter bar on the parts (design 6.1): today's GET form, today's names and values, and the view it belongs to;
	 * one row of fields (Cohort, Group, Search, each its label above its box), then Show (secondary), Clear the filters
	 * (quiet, while a filter is set) and the export (quiet, behind its own fence). Under 800px of main area the fields
	 * stack at full width (parts.css).
	 *
	 * @param string $record_id  Institutions record ID, which the export control's own fence needs.
	 * @param array  $cohorts    Cohort key to the number who signed up.
	 * @param string $cohort     The chosen cohort.
	 * @param array  $filters    The chosen group and search term.
	 * @param bool   $can_manage Whether the viewer is an Administrator.
	 */
	private static function render_filters_parts( $record_id, array $cohorts, $cohort, array $filters, $can_manage ) {
		echo '<form class="wpcpm-roster__bar wpcpm-ui-block" method="get">';

		// The same hidden fields as today's form (`hidden_fields()`), then the view's own.
		self::hidden_fields( $can_manage );

		// The Institution Dashboard's view: the form leads back to the Students view it was drawn on.
		printf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( self::ARG_DASHBOARD_VIEW ),
			esc_attr( self::DASHBOARD_VIEW )
		);

		$cohort_options = array();

		foreach ( $cohorts as $key => $signed_up ) {
			$cohort_options[ (string) $key ] = sprintf(
				/* translators: 1: cohort name, e.g. "January to June 2026", 2: how many students signed up. */
				__( '%1$s (%2$s)', 'wpcredits-program-manager' ),
				WPCPM_Cohort::label( $key ),
				number_format_i18n( $signed_up )
			);
		}

		$group_options = array_merge( array( '' => __( 'All students', 'wpcredits-program-manager' ) ), self::group_labels() );

		// The three fields, in one row of fields.
		$fields  = WPCPM_UI::field(
			array(
				'id'      => 'wpcpm-roster-cohort',
				'name'    => self::ARG_COHORT,
				'label'   => __( 'Cohort', 'wpcredits-program-manager' ),
				'type'    => 'select',
				'options' => $cohort_options,
				'value'   => (string) $cohort,
			)
		);
		$fields .= WPCPM_UI::field(
			array(
				'id'      => 'wpcpm-roster-status',
				'name'    => self::ARG_STATUS,
				'label'   => __( 'Group', 'wpcredits-program-manager' ),
				'type'    => 'select',
				'options' => $group_options,
				'value'   => (string) $filters['status'],
			)
		);
		// Today's hint as the field's hint, and no placeholder: the parts print none that repeats a hint (design 2.4).
		$fields .= WPCPM_UI::field(
			array(
				'id'    => 'wpcpm-roster-search',
				'name'  => self::ARG_SEARCH,
				'label' => __( 'Search', 'wpcredits-program-manager' ),
				'type'  => 'search',
				'value' => (string) $filters['search'],
				'hint'  => self::search_hint( $can_manage ),
			)
		);

		echo '<div class="wpcpm-ui-fields">' . $fields . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::field().

		$buttons = array(
			WPCPM_UI::button(
				array(
					'label' => __( 'Show', 'wpcredits-program-manager' ),
					'kind'  => 'secondary',
				)
			),
		);

		if ( '' !== $filters['status'] || '' !== $filters['search'] ) {
			$buttons[] = WPCPM_UI::button(
				array(
					'label' => __( 'Clear the filters', 'wpcredits-program-manager' ),
					'kind'  => 'quiet',
					'href'  => remove_query_arg( array( self::ARG_STATUS, self::ARG_SEARCH, self::ARG_STUDENT ) ),
				)
			);
		}

		$export = self::export_url( $record_id, $cohort );

		if ( '' !== $export ) {
			$buttons[] = WPCPM_UI::button(
				array(
					'label' => __( 'Download this cohort (CSV)', 'wpcredits-program-manager' ),
					'kind'  => 'quiet',
					'href'  => $export,
				)
			);
		}

		echo WPCPM_UI::actions( $buttons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::button() and WPCPM_UI::actions().
		echo '</form>';
	}

	/**
	 * The cohort summary on the parts (design 6.1): a plain block, its heading the cohort's name, then today's two
	 * sentences; no colored edge.
	 *
	 * @param array  $rows   The institution's index rows.
	 * @param string $cohort The chosen cohort.
	 */
	private static function render_summary( array $rows, $cohort ) {
		$sentences = self::strip_sentences( $rows, $cohort );

		echo '<section class="wpcpm-roster__summary wpcpm-ui-block">'
			. WPCPM_UI::heading( WPCPM_Cohort::label( $cohort ), 'h3' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().
			. WPCPM_UI::intro( $sentences[0] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
			. WPCPM_UI::intro( $sentences[1] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
			. '</section>';
	}

	/**
	 * One group on the parts (design 6.1; the controller's ruling 1 and the planner's P7): a card fold of its own,
	 * drawn through the fold's head, open by today's rule (`fold_open()`), its title the group's name and its count a
	 * phrase in words; the group's sentence its first line, today's empty line when it holds nobody, then the students.
	 *
	 * @param string $key     Group key.
	 * @param string $label   Group heading.
	 * @param array  $rows    The group's rows, already filtered.
	 * @param array  $columns The columns the fence permits.
	 * @param string $here    The current URL, without the detail view's argument.
	 * @param array  $filters The chosen group and search term.
	 */
	private static function render_group_parts( $key, $label, array $rows, array $columns, $here, array $filters ) {
		$count = count( $rows );
		$open  = ! in_array( $key, array( 'finished', 'not_started' ), true ) && $count <= self::OPEN_MAX;

		self::fold_open( $key, $label, $count, $open );

		echo WPCPM_UI::intro( self::group_note( $key ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

		if ( 0 === $count ) {
			$empty = ( '' !== $filters['search'] || '' !== $filters['status'] )
				? __( 'No students in this group match the filters.', 'wpcredits-program-manager' )
				: __( 'No students in this group.', 'wpcredits-program-manager' );

			echo WPCPM_UI::list_empty( $empty ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().
		} elseif ( ! empty( $columns ) ) {
			// The group's students are a list of their folds (Task 28, W6), as the live page's are a list of names.
			echo WPCPM_UI::inner_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.

			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					echo WPCPM_UI::inner_item(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
					self::render_student_parts( $row, $columns, $here, $count );
					echo WPCPM_UI::inner_item_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
				}
			}

			echo WPCPM_UI::inner_list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
		}

		echo '</div></details></section>';
	}

	/**
	 * A group's card fold, up to its body: the section with the group's classes and anchor, the disclosure, the head
	 * through `WPCPM_UI::fold_head()` with the count in words, and the body's opening tag.
	 *
	 * @param string $key   Group key (or `unlinked`).
	 * @param string $title The group's name.
	 * @param int    $count How many students it holds.
	 * @param bool   $open  Whether it is drawn open.
	 */
	private static function fold_open( $key, $title, $count, $open ) {
		printf(
			'<section class="wpcpm-ui-fold-card wpcpm-roster__group wpcpm-roster__group--%1$s" id="wpcpm-roster-%1$s"><details class="wpcpm-ui-fold"%2$s><summary class="wpcpm-ui-fold__head">',
			esc_attr( $key ),
			$open ? ' open' : ''
		);
		$head = WPCPM_UI::fold_head(
			array(
				'size'   => 'card',
				'title'  => $title,
				'phrase' => sprintf(
					/* translators: %s: a number of students. */
					_n( '%s student', '%s students', $count, 'wpcredits-program-manager' ),
					number_format_i18n( $count )
				),
			)
		);

		echo $head . '</summary><div class="wpcpm-ui-fold__body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::fold_head().
	}

	/**
	 * One student on the parts (design 6.1): an inner fold, its media the 44px photo or the name's first letter, its
	 * title the name (a link to the student's card when there is an account) as it is, its second
	 * piece the dates whole and the mentor, the program's status a tag at its end; its body a key and value table of the
	 * permitted columns. Open when the group holds one student, as today.
	 *
	 * @param array  $row     The index row.
	 * @param array  $columns The columns the fence permits.
	 * @param string $here    The current URL, without the detail view's argument.
	 * @param int    $total   How many students are in this group.
	 */
	private static function render_student_parts( array $row, array $columns, $here, $total ) {
		$values  = self::values( $row );
		$cells   = self::cells_parts( $values );
		$name    = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		$name    = '' === $name ? __( 'Unnamed student', 'wpcredits-program-manager' ) : $name;
		$user_id = (int) $values['user_id'];

		// The portrait, keyed on the WordPress.org username alone (never the address: a school's page asks no gravatar
		// service about its students' mail); a row without one shows the name's first letter, so the names line up.
		ob_start();
		WPCPM_Mentors_Dashboard::render_avatar( (string) $values['username'], '', $name, 44 );
		// Decoration beside the name the head already says: an empty alt, so the name is not heard twice.
		$portrait = (string) preg_replace( '/ alt="[^"]*"/', ' alt=""', trim( (string) ob_get_clean() ), 1 );

		$title = $user_id > 0
			? '<a href="' . esc_url( add_query_arg( self::ARG_STUDENT, $user_id, $here ) ) . '">' . esc_html( $name ) . '</a>'
			: esc_html( $name );

		// The closed row's second piece: the dates and the mentor's name, as today's preview line holds them.
		$second = array_filter(
			array(
				self::dates_line( (string) $values['start'], (string) $values['end'] ),
				'' !== self::mentor_name_of( $row ) ? esc_html( self::mentor_name_of( $row ) ) : '',
			),
			static function ( $piece ) {
				return '' !== $piece;
			}
		);

		$program = self::program_of( $row );
		$tag     = '' !== $program ? self::tag( $program ) : '';

		$fold = WPCPM_UI::inner_open(
			array(
				'title'       => $name,
				'title_html'  => $title,
				'second_html' => implode( ' · ', $second ),
				'portrait'    => $portrait,
				'initials'    => WPCPM_UI::initials( $name ),
				'tag'         => $tag,
				'open'        => 1 === (int) $total,
			)
		);

		// The markup inner_open() is given is escaped above: render_avatar(), esc_url(), esc_html(), dates_line(), tag().
		echo $fold; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open() and above.

		$label = array(
			'key'    => 'label',
			'label'  => '',
			'kind'   => 'text',
			'header' => true,
		);
		$value = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'text',
		);
		// An address may break anywhere (the global constraints: a cell holding an address or a website is a token).
		$token = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'token',
		);

		$table = WPCPM_UI::table_open(
			array( $label, $value ),
			array(
				'keyvalue' => true,
				'caption'  => sprintf(
					/* translators: %s: student name. */
					__( 'Program details for %s', 'wpcredits-program-manager' ),
					$name
				),
			)
		);

		foreach ( $columns as $key => $heading ) {
			// The name is the fold's own title, as it is today's card's heading.
			if ( 'students|Full Name' === $key ) {
				continue;
			}

			$cell = isset( $cells[ $key ] ) ? $cells[ $key ] : array( '', '' );
			$html = '' === $cell[0] ? esc_html( self::blank_text() ) : $cell[0];
			$kind = '' === $cell[0] ? 'empty' : $cell[1];

			$table .= WPCPM_UI::row_open( array( 'class' => 'wpcpm-roster__row--' . self::column_slug( $key ) ) )
				. WPCPM_UI::cell( $label, esc_html( $heading ) )
				. WPCPM_UI::cell( in_array( $key, array( 'students|WP Profile', 'reports|Personal Website URL' ), true ) ? $token : $value, $html, $kind )
				. WPCPM_UI::row_close();
		}

		echo $table . WPCPM_UI::table_close() . WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value built by cells_parts(), which escapes what it interpolates.
	}

	/**
	 * One student's cells on the parts, keyed as `columns()` is: each the cell's markup, already escaped, and its kind
	 * ('' or `empty`, a value kept in words). The values are `values()`'s, the ones today's cells are built from; an
	 * empty cell is '' and the table prints "Not recorded".
	 *
	 * @param array $values What `values()` returned.
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function cells_parts( array $values ) {
		$mentor = (string) $values['mentor_name'];

		return array(
			'students|Status'                => array( self::program_parts( (string) $values['program'], (string) $values['status'], (string) $values['course'] ), '' ),
			'students|Start Date'            => array( self::dates_parts( (string) $values['start'], (string) $values['end'] ), '' ),
			'students|End Date'              => array( self::days_cell( (string) $values['end'] ), '' ),
			// Today's sentence when there is no name, in words and muted (the empty kind).
			'students|Mentor'                => '' !== $mentor
				? array( esc_html( $mentor ), '' )
				: array( esc_html( self::mentor_words( ! empty( $values['has_mentor'] ), ! empty( $values['has_report'] ) ) ), 'empty' ),
			'students|WP Profile'            => array(
				'' === (string) $values['username'] ? '' : sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( 'https://profiles.wordpress.org/' . rawurlencode( (string) $values['username'] ) . '/' ),
					esc_html( (string) $values['username'] )
				),
				'',
			),
			'reports|Main Contribution Team' => array( esc_html( (string) $values['team'] ), '' ),
			'reports|Personal Website URL'   => array( self::website_cell( (string) $values['website'] ), '' ),
			'students|Your field of study'   => array( esc_html( (string) $values['field_of_study'] ), '' ),
			'students|Tutor '                => array( esc_html( (string) $values['tutor'] ), '' ),
			'reports|Hours'                  => array( self::hours_cell( (string) $values['hours'], (string) $values['track'] ), '' ),
		);
	}

	/**
	 * The Program cell on the parts: the program in words, since the row's head already carries it as its status tag
	 * (Gate B, B9: the tag was printed twice, and a student's card says its Program in words too); a course status that
	 * differs from it, which the head does not carry, stays a status tag before it; a pipeline status in plain words.
	 *
	 * @param string $program The reports status, or ''.
	 * @param string $status  The pipeline status, or ''.
	 * @param string $course  The status of the course the student is on or took, or ''.
	 * @return string Cell markup.
	 */
	private static function program_parts( $program, $status, $course ) {
		$program = trim( (string) $program );
		$course  = trim( (string) $course );

		if ( '' !== $course && $course !== $program ) {
			return self::tag( $course ) . ( '' !== $program ? ' ' . esc_html( WPCPM_Program::status_words( $program ) ) : '' );
		}

		if ( '' !== $program ) {
			return esc_html( WPCPM_Program::status_words( $program ) );
		}

		$status = trim( (string) $status );

		return '' === $status ? '' : esc_html( WPCPM_Program::status_words( $status ) );
	}

	/**
	 * A status as the parts' tag, in `WPCPM_Program::status_words()`'s words, green for a track (on the program) and
	 * neutral otherwise, as today's badge colors a track and leaves a state plain.
	 *
	 * @param string $status Airtable status.
	 * @return string Escaped markup, or ''.
	 */
	private static function tag( $status ) {
		$status = trim( (string) $status );

		if ( '' === $status ) {
			return '';
		}

		return WPCPM_UI::tag( WPCPM_Program::status_words( $status ), WPCPM_Program::tone( $status ) );
	}

	/**
	 * The two dates as the mentor card words them (`WPCPM_Mentors_Dashboard::format_dates()`), each date whole.
	 *
	 * @param string $start Start date, `Y-m-d`.
	 * @param string $end   End date, `Y-m-d`.
	 * @return string Escaped markup, or '' when there is neither.
	 */
	private static function dates_whole( $start, $end ) {
		$format = (string) get_option( 'date_format' );
		$from   = '' !== $start ? (string) date_i18n( $format, strtotime( $start ) ) : '';
		$to     = '' !== $end ? (string) date_i18n( $format, strtotime( $end ) ) : '';

		if ( '' !== $from && '' !== $to ) {
			/* translators: 1: start date, 2: end date. Joined by the word "to", not a hyphen: WordPress would texturize a spaced hyphen into an en dash once this renders on the page. */
			return sprintf( esc_html__( '%1$s to %2$s', 'wpcredits-program-manager' ), WPCPM_UI::whole( $from ), WPCPM_UI::whole( $to ) );
		}

		if ( '' !== $from ) {
			/* translators: %s: start date. */
			return sprintf( esc_html__( 'From %s', 'wpcredits-program-manager' ), WPCPM_UI::whole( $from ) );
		}

		if ( '' !== $to ) {
			/* translators: %s: end date. */
			return sprintf( esc_html__( 'Until %s', 'wpcredits-program-manager' ), WPCPM_UI::whole( $to ) );
		}

		return '';
	}

	/**
	 * The Dates cell on the parts: each date whole, the end of a row with no start date its
	 * second line.
	 *
	 * @param string $start Start date, `Y-m-d`.
	 * @param string $end   End date, `Y-m-d`.
	 * @return string Escaped markup.
	 */
	private static function dates_parts( $start, $end ) {
		if ( '' !== $start ) {
			return self::dates_whole( $start, $end );
		}

		$cell = esc_html__( 'No start date', 'wpcredits-program-manager' );

		return '' !== $end ? $cell . WPCPM_UI::cell_sub( '', self::dates_whole( '', $end ) ) : $cell;
	}

	/**
	 * The dates as a closed row's second piece shows them: the dates whole, or today's "No start date" with the end
	 * after it.
	 *
	 * @param string $start Start date, `Y-m-d`.
	 * @param string $end   End date, `Y-m-d`.
	 * @return string Escaped markup, or ''.
	 */
	private static function dates_line( $start, $end ) {
		if ( '' !== $start ) {
			return self::dates_whole( $start, $end );
		}

		$line = esc_html__( 'No start date', 'wpcredits-program-manager' );

		return '' !== $end ? $line . ' · ' . self::dates_whole( '', $end ) : $line;
	}

	/**
	 * The students with an account and a report record whose Students row does not exist, on the parts (design 6.1):
	 * a card fold like a group, open, its count in words, today's sentence, then a list of rows: the name and the status
	 * tag, and for an Administrator the address as a fact. The same people as today (`unlinked_people()`).
	 *
	 * @param mixed  $rows   What `WPCPM_Roster_Index::unlinked_for()` returned.
	 * @param string $search The search term, or ''.
	 */
	private static function render_unlinked_parts( $rows, $search ) {
		$people = self::unlinked_people( $rows, $search );

		if ( empty( $people ) ) {
			return;
		}

		self::fold_open( 'unlinked', __( 'Enrollment not recorded yet', 'wpcredits-program-manager' ), count( $people ), true );

		echo WPCPM_UI::intro( __( 'These students are reporting their progress, but the program records hold no enrollment for them yet, so they are not counted in the groups above. An Administrator needs to complete the record.', 'wpcredits-program-manager' ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

		$list = WPCPM_UI::list_open();

		foreach ( $people as $person ) {
			$status = trim( (string) $person['status'] );

			// The address is an Administrator's to see, never the school's (`unlinked_label()`).
			$list .= WPCPM_UI::row(
				array(
					'name'  => self::unlinked_label( $person ),
					'tag'   => '' === $status ? null : array(
						'words' => WPCPM_Program::status_words( $status ),
						'tone'  => WPCPM_Program::tone( $status ),
					),
					'facts' => ( self::$manages && '' !== $person['email'] && '' !== $person['name'] )
						? array( (string) $person['email'] )
						: array(),
				)
			);
		}

		echo $list . WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row().
		echo '</div></details></section>';
	}

	/**
	 * The strip's two sentences, as text: this cohort's two numbers, then the semester before it (or why there is none
	 * to compare with). Both branches print these words.
	 *
	 * @param array  $rows   The institution's index rows.
	 * @param string $cohort The chosen cohort.
	 * @return array{0: string, 1: string}
	 */
	private static function strip_sentences( array $rows, $cohort ) {
		$now      = WPCPM_Cohort::participation( $rows, $cohort );
		$previous = WPCPM_Cohort::previous( $cohort );

		$facts = array(
			sprintf(
				/* translators: %s: number of students. */
				_n(
					'%s student signed up.',
					'%s students signed up.',
					$now['signed_up'],
					'wpcredits-program-manager'
				),
				number_format_i18n( $now['signed_up'] )
			),
			sprintf(
				/* translators: %s: number of students. */
				_n(
					'%s has graduated.',
					'%s have graduated.',
					$now['graduated'],
					'wpcredits-program-manager'
				),
				number_format_i18n( $now['graduated'] )
			),
		);

		$now_line = implode( ' ', $facts );

		if ( '' === $previous ) {
			// NONE has no semester before it, so there is nothing to compare against. Said
			// plainly, because a silent strip would look like a missing number.
			return array( $now_line, __( 'These students have no start date, so there is no earlier semester to compare them with.', 'wpcredits-program-manager' ) );
		}

		$then = WPCPM_Cohort::participation( $rows, $previous );

		if ( 0 === $then['signed_up'] ) {
			return array(
				$now_line,
				sprintf(
					/* translators: %s: the earlier semester, e.g. "July to December 2025". */
					__( 'No students started in %s.', 'wpcredits-program-manager' ),
					WPCPM_Cohort::label( $previous )
				),
			);
		}

		return array(
			$now_line,
			sprintf(
				/* translators: 1: the earlier semester, 2: how many signed up then, 3: how many of them graduated. */
				__( 'Compared with %1$s: %2$s signed up, %3$s graduated.', 'wpcredits-program-manager' ),
				WPCPM_Cohort::label( $previous ),
				number_format_i18n( $then['signed_up'] ),
				number_format_i18n( $then['graduated'] )
			),
		);
	}

	/**
	 * How many students a group may show before it starts closed.
	 *
	 * Twelve fits a screen with the sections below it still in sight, which is the thing a
	 * long list takes away. It is a display choice and nothing depends on it: the count on
	 * the row is the same number either way, and the filters read the whole group.
	 */
	const OPEN_MAX = 12;

	/**
	 * Whether the reader of the render in progress is a program manager.
	 *
	 * Set once by `render()` from the dashboard's context, which already asked the
	 * capability, and read where a student's address would be printed or searched: those
	 * two places are several private calls deep, and the flag is what the page knows rather
	 * than a second capability check that a suite would have to stub.
	 *
	 * @var bool
	 */
	private static $manages = false;

	/**
	 * What a group means, in the words a school uses for it.
	 *
	 * The two signals a student is filed by are a mentor assigned and a progress record started
	 * (the student's own report, which the program calls a record and a school calls their
	 * progress). Both are named, because a student with a progress record and no mentor link is
	 * still Current, and "with a mentor assigned" alone would promise a name the Mentor row then
	 * denies.
	 *
	 * @param string $key Group key.
	 * @return string
	 */
	private static function group_note( $key ) {
		$notes = array(
			'current'     => __( 'With a mentor assigned or a progress record already started, students waiting to graduate included.', 'wpcredits-program-manager' ),
			'waiting'     => __( 'Signed up, with no mentor assigned and no progress record yet.', 'wpcredits-program-manager' ),
			'finished'    => __( 'Mentoring has finished. Their details are kept for reference.', 'wpcredits-program-manager' ),
			'not_started' => __( 'Students who have not started yet, never began the program, or stopped before finishing it.', 'wpcredits-program-manager' ),
		);

		return isset( $notes[ $key ] ) ? $notes[ $key ] : '';
	}

	/**
	 * The mentor's name for a card's preview line, or nothing.
	 *
	 * Read the way `values()` reads it, so the closed row and the Mentor row inside cannot
	 * disagree about who it is: the account's cached block when the account has a name, and
	 * otherwise the name the sync lent the index row from the joined report record. The
	 * second half is what a finished student without an account gets (1.117.7); before it the
	 * preview read the account alone and printed nothing for them while the opened card named
	 * the mentor.
	 *
	 * @param array $row The index row.
	 * @return string
	 */
	private static function mentor_name_of( array $row ) {
		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
		$cached  = '';

		if ( $user_id > 0 ) {
			$mentor = get_user_meta( $user_id, WPCPM_Students_Sync::META_MENTOR, true );
			$cached = ( is_array( $mentor ) && isset( $mentor['name'] ) && is_scalar( $mentor['name'] ) )
				? trim( (string) $mentor['name'] )
				: '';
		}

		if ( '' !== $cached ) {
			return $cached;
		}

		return isset( $row['mentor_name'] ) && is_scalar( $row['mentor_name'] ) ? trim( (string) $row['mentor_name'] ) : '';
	}

	/**
	 * One student's values, as text, read one way: the index row first and the cached
	 * account blocks where the account is the fresher source (see each line). Every value taken out of the cached
	 * program block is read out by name, one at a time, so a block of another shape reads as blank, never wrong.
	 *
	 * @param array $row An index row.
	 * @return array{user_id: int, name: string, program: string, status: string, course: string, start: string, end: string, mentor_name: string, has_mentor: bool, has_report: bool, username: string, team: string, website: string, field_of_study: string, tutor: string, hours: string, track: string}
	 */
	private static function values( array $row ) {
		$get = static function ( $key ) use ( $row ) {
			return ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) ? trim( (string) $row[ $key ] ) : '';
		};

		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
		$program = $user_id > 0 ? get_user_meta( $user_id, WPCPM_Students_Sync::META_PROGRAM, true ) : array();
		$mentor  = $user_id > 0 ? get_user_meta( $user_id, WPCPM_Students_Sync::META_MENTOR, true ) : array();
		$cached  = static function ( $block, $key ) {
			return ( is_array( $block ) && isset( $block[ $key ] ) && is_scalar( $block[ $key ] ) ) ? trim( (string) $block[ $key ] ) : '';
		};

		// **The index first, the account second.** Both of these live on the Students Reports
		// row, and this page used to read them only through the student's WordPress account -
		// which most students on a school's roster do not have. At one university that meant
		// two rows of fifteen named a mentor and the other thirteen said a mentor was assigned
		// but the report record did not exist yet, about students whose report record the index
		// was holding at the time. The account is still preferred where there is one, because
		// it is written by the same sync and is the fresher of the two when a student edits
		// their own report between runs.
		$mentor_name = '' !== $cached( $mentor, 'name' ) ? $cached( $mentor, 'name' ) : $get( 'mentor_name' );
		$team        = '' !== $cached( $program, 'team' ) ? $cached( $program, 'team' ) : $get( 'team' );
		$website     = '' !== $cached( $program, 'website' ) ? $cached( $program, 'website' ) : $get( 'website' );

		// The same order, and `'' !==` earns its keep here in a way it does not above: "0" is a
		// real number of hours, so a truthiness test would fall past a cleared count through to
		// the index's older copy and print a number the student no longer claims.
		$hours = '' !== $cached( $program, 'hours' ) ? $cached( $program, 'hours' ) : $get( 'hours' );

		// The track the hours target is read from: the Students Reports status where the
		// account carries one, the Students row's status otherwise, which is the order
		// `program_cell()` prints those two in. Neither has to name a track - a graduate names
		// none - and `WPCPM_Program::hours_target()` answers 0 for anything it has not heard
		// of, which is what makes "150 h" the right cell for a student whose track is behind
		// them rather than an invented "150 of 150".
		$track = '' !== $cached( $program, 'program' ) ? $cached( $program, 'program' ) : $get( 'status' );

		// A student waiting to graduate is read against the course they took, which the account
		// keeps (`WPCPM_Program::course_status()`), so the target is the one the Program cell names.
		$course = WPCPM_Program::course_status( $cached( $program, 'program' ), $user_id );
		$track  = '' !== $course ? $course : $track;

		return array(
			'user_id'        => $user_id,
			'name'           => $get( 'name' ),
			'program'        => $cached( $program, 'program' ),
			'status'         => $get( 'status' ),
			'course'         => $course,
			'start'          => $get( 'start' ),
			'end'            => $get( 'end' ),
			'mentor_name'    => $mentor_name,
			'has_mentor'     => ! empty( $row['has_mentor'] ),
			'has_report'     => ! empty( $row['reports'] ),
			'username'       => $get( 'username' ),
			'team'           => $team,
			'website'        => $website,
			'field_of_study' => $get( 'field_of_study' ),
			'tutor'          => $get( 'tutor' ),
			'hours'          => $hours,
			'track'          => $track,
		);
	}

	/**
	 * A column key as a class name.
	 *
	 * The cells already carry the heading in `data-label`, for the stacked layout on a phone,
	 * but a heading is translated and a stylesheet cannot key off it: on a Polish site every
	 * rule written against `data-label` would stop matching. The column key is the plugin's
	 * own, so the class made from it is stable in every language, which is what lets the
	 * stylesheet say that a date must not wrap without saying it about every cell.
	 *
	 * @param string $key Column key, e.g. `students|Start Date`.
	 * @return string
	 */
	private static function column_slug( $key ) {
		return str_replace( array( '|', ' ' ), '-', strtolower( (string) $key ) );
	}

	/**
	 * The reports status a card's badge is drawn from.
	 *
	 * The same value `program_cell()` prefers, read the same way: the program is the Students
	 * Reports status, which only an account-bearing row carries in its program meta. A row
	 * without one has no program yet and gets no badge, rather than a badge of the pipeline
	 * status from the other vocabulary.
	 *
	 * **One exception, the past statuses.** The sync creates no account for a student in a
	 * past status, so a student who dropped out or graduated before an account existed has
	 * none, ever. Their closed row was dates and a portrait and nothing else while the card
	 * inside named the mentor and the hours (a Liceo La Paz roster, 29 September 2026).
	 * "Graduate" and "Dropped out" are spelled the same in both tables, so for a row without
	 * an account they are badged off the Students row; an active pipeline status still is not,
	 * because a Current student without a joined report record has no program to show.
	 *
	 * @param array $row The index row.
	 * @return string
	 */
	private static function program_of( array $row ) {
		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

		if ( $user_id < 1 ) {
			$status  = trim( (string) ( isset( $row['status'] ) ? $row['status'] : '' ) );
			$tracked = WPCPM_Mentors_Sync::tracked_statuses();
			$past    = isset( $tracked['past'] ) ? (array) $tracked['past'] : array();

			return in_array( $status, $past, true ) ? $status : '';
		}

		$program = get_user_meta( $user_id, WPCPM_Students_Sync::META_PROGRAM, true );

		return ( is_array( $program ) && isset( $program['program'] ) && is_scalar( $program['program'] ) )
			? trim( (string) $program['program'] )
			: '';
	}

	/**
	 * The days-left cell, counted from the end date.
	 *
	 * @param string $end End date, `Y-m-d`.
	 * @return string
	 */
	private static function days_cell( $end ) {
		$days = self::days_between( wp_date( 'Y-m-d' ), $end );

		if ( null === $days ) {
			return '';
		}

		if ( 0 === $days ) {
			return esc_html__( 'Ends today', 'wpcredits-program-manager' );
		}

		if ( $days > 0 ) {
			return esc_html(
				sprintf(
					/* translators: %s: number of days. */
					_n( '%s day left', '%s days left', $days, 'wpcredits-program-manager' ),
					number_format_i18n( $days )
				)
			);
		}

		return esc_html(
			sprintf(
				/* translators: %s: number of days. */
				_n( 'Ended %s day ago', 'Ended %s days ago', abs( $days ), 'wpcredits-program-manager' ),
				number_format_i18n( abs( $days ) )
			)
		);
	}

	/**
	 * Which kind of nothing a mentor cell with no name is, in today's words: no mentor yet, or a
	 * mentor assigned whose name has not reached this page, and why.
	 *
	 * @param bool $has_mentor Whether the Students row links a mentor.
	 * @param bool $has_report Whether the index knows of a Students Reports row.
	 * @return string
	 */
	private static function mentor_words( $has_mentor, $has_report ) {
		if ( ! $has_mentor ) {
			return __( 'No mentor yet.', 'wpcredits-program-manager' );
		}

		return $has_report
			? __( 'A mentor is assigned. Their name has not reached this page yet.', 'wpcredits-program-manager' )
			: __( 'A mentor is assigned. Their name shows here once the student\'s progress record reaches this page.', 'wpcredits-program-manager' );
	}

	/**
	 * The website cell: a link when the value is one, and the text when it is not.
	 *
	 * @param string $website Whatever the student wrote in the reporting form.
	 * @return string
	 */
	private static function website_cell( $website ) {
		if ( '' === $website ) {
			return '';
		}

		if ( 1 !== preg_match( '#^https?://#i', $website ) ) {
			return esc_html( $website );
		}

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $website ),
			esc_html( (string) preg_replace( '#^https?://#i', '', untrailingslashit( $website ) ) )
		);
	}

	/**
	 * The hours cell: what a student has logged, against their track's target if it has one.
	 *
	 * The words are `WPCPM_Program::hours_text()`'s, which the Mentor Report Card prints too, so
	 * the two pages cannot come to write the same student's count two ways: "12 of 150", "12 h"
	 * for a track worked to no target, a fraction as the base sent it, a count past the target as
	 * it stands, and nothing at all for a cell nobody has logged, which this card prints as its
	 * "Not recorded" while a logged 0 reads "0 of 150".
	 *
	 * @param string $hours  The value as the base holds it, or ''.
	 * @param string $status The status the target is read from.
	 * @return string Cell markup.
	 */
	private static function hours_cell( $hours, $status ) {
		$text = WPCPM_Program::hours_text( $hours, $status );

		// An empty cell is this card's gap, which the row prints for itself.
		if ( '' === $text ) {
			return '';
		}

		// Escaped whichever branch wrote it: a value that is not a number reaches here as it
		// stands, and a field type changed in the base can put anything in it.
		return esc_html( $text );
	}

	/**
	 * The fifth list's people, one shape each, the search applied.
	 *
	 * @param mixed  $rows   What `WPCPM_Roster_Index::unlinked_for()` returned.
	 * @param string $search The search term, or ''.
	 * @return array[] As `unlinked_person()` returns each.
	 */
	private static function unlinked_people( $rows, $search ) {
		$people = array();

		foreach ( (array) $rows as $key => $row ) {
			$person = self::unlinked_person( $key, $row );

			if ( '' === $person['name'] && '' === $person['email'] ) {
				continue;
			}

			if ( '' !== $search && ! self::matches( $person, $search ) ) {
				continue;
			}

			$people[] = $person;
		}

		return $people;
	}

	/**
	 * The name the fifth list prints for one person: the name, an Administrator's address where there is none, then
	 * the username, then today's sentence for nobody named.
	 *
	 * @param array $person As `unlinked_person()` returns it.
	 * @return string
	 */
	private static function unlinked_label( array $person ) {
		$label = $person['name'];

		if ( '' === $label ) {
			$label = self::$manages && '' !== $person['email'] ? $person['email'] : $person['username'];
		}

		if ( '' === $label ) {
			$label = __( 'A student whose name is not recorded yet', 'wpcredits-program-manager' );
		}

		return $label;
	}

	/**
	 * One row of the fifth list, whichever shape it arrived in.
	 *
	 * The list is built from user meta rather than from the index, so it is read defensively:
	 * a row in the index's shape, a row carrying only an account, or a bare user ID all
	 * describe the same person, and a display list is the wrong place to be strict about
	 * which one another class chose to hand over.
	 *
	 * @param string|int $key The list's key, which may be the user ID.
	 * @param mixed      $row The row.
	 * @return array{user_id: int, name: string, email: string, username: string, tutor: string, field_of_study: string, status: string}
	 */
	private static function unlinked_person( $key, $row ) {
		$person = array(
			'user_id'        => 0,
			'name'           => '',
			'email'          => '',
			'username'       => '',
			'tutor'          => '',
			'field_of_study' => '',
			'status'         => '',
		);

		if ( is_array( $row ) ) {
			foreach ( $person as $field => $unused ) {
				if ( 'user_id' === $field ) {
					continue;
				}

				$person[ $field ] = ( isset( $row[ $field ] ) && is_scalar( $row[ $field ] ) ) ? trim( (string) $row[ $field ] ) : '';
			}

			// The Students-row shape calls the track `status`; the cached program block calls
			// the same value `program`. Either one names the track, and neither is authoritative
			// enough here to be worth insisting on.
			if ( '' === $person['status'] && isset( $row['program'] ) && is_scalar( $row['program'] ) ) {
				$person['status'] = trim( (string) $row['program'] );
			}

			$person['user_id'] = isset( $row['user_id'] ) ? (int) $row['user_id'] : ( is_numeric( $key ) ? (int) $key : 0 );
		} elseif ( is_numeric( $row ) ) {
			$person['user_id'] = (int) $row;
		}

		if ( $person['user_id'] > 0 && ( '' === $person['name'] || '' === $person['email'] ) ) {
			$user = get_user_by( 'id', $person['user_id'] );

			if ( $user instanceof WP_User ) {
				$person['name']  = '' !== $person['name'] ? $person['name'] : trim( (string) $user->display_name );
				$person['email'] = '' !== $person['email'] ? $person['email'] : trim( (string) $user->user_email );
			}
		}

		return $person;
	}

	/**
	 * The rows of one group whose own fields match the search term.
	 *
	 * @param array  $rows   The group's rows.
	 * @param string $search The search term.
	 * @return array
	 */
	private static function narrow( array $rows, $search ) {
		$out = array();

		foreach ( $rows as $key => $row ) {
			if ( is_array( $row ) && self::matches( $row, $search ) ) {
				$out[ $key ] = $row;
			}
		}

		return $out;
	}

	/**
	 * Whether one row answers a search.
	 *
	 * The row's own fields and nothing else: reading the mentor's name would mean fetching
	 * every student's cached card before the first one is drawn, and a roster is filtered far
	 * more often than it is searched by mentor. Case-insensitive and byte-wise, so an
	 * accented name matches when it is typed the way it is spelled and not otherwise.
	 *
	 * **The Students record ID is the one field read whole.** It is an identifier and not a
	 * word: a part of it would match other rows, and Airtable tells `recABC` from `recabc`, so
	 * it is compared exactly, as the base spells it. A manager is given it (1.123.48) because
	 * it is the ID Claude gives for a student in place of a name, and a student with no account
	 * has no row on the Student accounts list: this roster is where an Administrator puts the
	 * name to the ID.
	 *
	 * @param array  $row    An index row, or one of the fifth list's people.
	 * @param string $search The search term.
	 * @return bool
	 */
	private static function matches( array $row, $search ) {
		foreach ( array_keys( self::search_fields( self::$manages ) ) as $field ) {
			if ( ! isset( $row[ $field ] ) || ! is_scalar( $row[ $field ] ) ) {
				continue;
			}

			if ( 'record_id' === $field ) {
				if ( 0 === strcmp( trim( (string) $row[ $field ] ), trim( $search ) ) ) {
					return true;
				}

				continue;
			}

			if ( false !== stripos( (string) $row[ $field ], $search ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The fields a search reads, each with the word its box's hint names it by, in the order the
	 * hint lists them.
	 *
	 * The address is searchable by a manager only, as only a manager is shown it (`unlinked_label()`): a
	 * search box that answers "does anyone here have this address" is a directory lookup, whatever
	 * the list beside it prints, so an institution's member is not given it. The Students record ID
	 * is a manager's for the same reason, and for the one it was added for (1.123.48): an
	 * Administrator puts a name to the ID Claude gave for a student. One list for the
	 * search (`matches()`) and for its hint (`search_hint()`): until 1.123.26 the hint was one
	 * sentence for both readers and promised a member an email search their box never ran.
	 *
	 * @param bool $manages Whether the reader can manage the program.
	 * @return array<string, string> Row field => the hint's word for it.
	 */
	private static function search_fields( $manages ) {
		$fields = array(
			'name'           => __( 'Name', 'wpcredits-program-manager' ),
			'email'          => __( 'email', 'wpcredits-program-manager' ),
			'username'       => __( 'WordPress.org username', 'wpcredits-program-manager' ),
			'tutor'          => __( 'tutor', 'wpcredits-program-manager' ),
			'field_of_study' => __( 'field of study', 'wpcredits-program-manager' ),
			'record_id'      => __( 'Students record ID', 'wpcredits-program-manager' ),
		);

		if ( ! $manages ) {
			unset( $fields['email'], $fields['record_id'] );
		}

		return $fields;
	}

	/**
	 * The search box's hint: exactly what this reader's search reads, from `search_fields()`.
	 *
	 * @param bool $manages Whether the reader can manage the program.
	 * @return string "Name, WordPress.org username, tutor, field of study" for an institution's
	 *                member, with "email" after the name and "Students record ID" last for a manager.
	 */
	private static function search_hint( $manages ) {
		return implode( ', ', self::search_fields( (bool) $manages ) );
	}

	/**
	 * A group and a search term reduced to what this class will act on.
	 *
	 * @param array $filters Whatever the caller or the request supplied.
	 * @return array{status: string, search: string}
	 */
	private static function clean_filters( array $filters ) {
		$status = ( isset( $filters['status'] ) && is_scalar( $filters['status'] ) ) ? sanitize_key( (string) $filters['status'] ) : '';
		$search = ( isset( $filters['search'] ) && is_scalar( $filters['search'] ) ) ? trim( sanitize_text_field( (string) $filters['search'] ) ) : '';
		$groups = self::group_labels();

		return array(
			'status' => isset( $groups[ $status ] ) ? $status : '',
			'search' => self::clip( $search ),
		);
	}

	/**
	 * A search term cut to `SEARCH_MAX` characters.
	 *
	 * @param string $search The term.
	 * @return string
	 */
	private static function clip( $search ) {
		if ( strlen( $search ) <= self::SEARCH_MAX ) {
			return $search;
		}

		// Cut by characters, not bytes: substr() alone can halve a multibyte character and
		// hand the field back an invalid byte to print. The `u` modifier makes the dot a
		// character and never splits one; input that is not valid UTF-8 fails the match and
		// falls back to the byte cut, which is no worse than the string it was given.
		if ( 1 === preg_match( '/^.{0,' . (int) self::SEARCH_MAX . '}/us', $search, $m ) ) {
			return $m[0];
		}

		return substr( $search, 0, self::SEARCH_MAX );
	}

	/**
	 * Whole days between two `Y-m-d` dates, or null when either is not one.
	 *
	 * Both ends are read as calendar days at UTC midnight rather than through strtotime():
	 * the distance between two dates is a calendar fact, and a local midnight would make the
	 * day a clock change falls in one hour short of a whole day and round the wrong way.
	 *
	 * @param string $from The earlier date.
	 * @param string $to   The later date.
	 * @return int|null
	 */
	private static function days_between( $from, $to ) {
		$start = self::midnight( $from );
		$end   = self::midnight( $to );

		if ( null === $start || null === $end ) {
			return null;
		}

		return (int) round( ( $end - $start ) / DAY_IN_SECONDS );
	}

	/**
	 * A `Y-m-d` string as a UTC midnight timestamp, or null when it is not one.
	 *
	 * @param mixed $date A date string.
	 * @return int|null
	 */
	private static function midnight( $date ) {
		if ( ! is_scalar( $date ) ) {
			return null;
		}

		$date = trim( (string) $date );

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m ) ) {
			return null;
		}

		$year  = (int) $m[1];
		$month = (int) $m[2];
		$day   = (int) $m[3];

		if ( ! checkdate( $month, $day, $year ) ) {
			return null;
		}

		return (int) gmmktime( 0, 0, 0, $month, $day, $year );
	}

	/**
	 * What an empty value says, as words.
	 *
	 * The card's value cell is styled by `is-empty` on its row, the way the mentor card's is,
	 * so it wants the sentence without the span `blank()` wraps it in. One place, so the two
	 * cannot come to say different things.
	 *
	 * @return string
	 */
	private static function blank_text() {
		return __( 'Not recorded', 'wpcredits-program-manager' );
	}

	/**
	 * The student whose card the roster draws in the list's place: the one the request names, when the fence lets the
	 * reader see that student's card; 0 otherwise, and the list is drawn. One rule for the roster and for the
	 * Institution Dashboard's Students view, whose read line is then about one student (Gate B, B6).
	 *
	 * @return int User ID, or 0.
	 */
	public static function card_student() {
		if ( ! class_exists( 'WPCPM_Institution_Student_View' ) ) {
			return 0;
		}

		$student = (int) WPCPM_Institution_Student_View::requested();

		return $student > 0 && WPCPM_Institution_Student_View::shows( $student ) ? $student : 0;
	}

	/**
	 * When the numbers on this page were read.
	 *
	 * A page holds it once, in one of two places: the roster's own footer where the roster
	 * stands alone, and the Institution Dashboard's foot where the dashboard titles the module
	 * the roster sits in (`$context['module_titled']`), in which case the roster prints none.
	 * The strip never prints it. The time is shown in the site's date and time formats.
	 *
	 * On one student's card (the Students view while it shows a card in the list's place, Gate B, B6) the words are for
	 * one student.
	 *
	 * @param int    $read    Unix time the index was read.
	 * @param string $classes The paragraph's classes.
	 * @param bool   $one     Whether the line is about one student's card.
	 */
	public static function read_line( $read, $classes, $one = false ) {
		$read = (int) $read;

		if ( $read <= 0 ) {
			printf(
				'<p class="%1$s">%2$s</p>',
				esc_attr( $classes ),
				$one
					? esc_html__( 'This student has not been read from the program records yet.', 'wpcredits-program-manager' )
					: esc_html__( 'These students have not been read from the program records yet.', 'wpcredits-program-manager' )
			);

			return;
		}

		printf(
			'<p class="%1$s">%2$s</p>',
			esc_attr( $classes ),
			esc_html(
				sprintf(
					/* translators: 1: date and time, 2: human-readable time difference, e.g. "2 hours". */
					__( 'Read from the program records on %1$s (%2$s ago).', 'wpcredits-program-manager' ),
					WPCPM_Dates::moment( $read ),
					human_time_diff( $read, time() )
				)
			)
		);
	}
}
