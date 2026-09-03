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
			$student = WPCPM_Institution_Student_View::requested();

			if ( $student > 0 && WPCPM_Institution_Student_View::shows( $student ) ) {
				WPCPM_Institution_Student_View::render( $record_id, $student, $context );

				return;
			}
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
			$cohort = self::default_cohort( $cohorts );
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

		echo '<section class="wpcpm-roster">';
		printf( '<h2 class="wpcpm-roster__title">%s</h2>', esc_html__( 'Students', 'wpcredits-program-manager' ) );

		self::render_filters( $record_id, $cohorts, $cohort, $filters, ! empty( $context['can_manage'] ) );
		self::render_strip( $rows, $cohort, $read );

		$groups = WPCPM_Roster_Index::groups( $record_id, $cohort );

		foreach ( self::group_labels() as $key => $label ) {
			if ( '' !== $filters['status'] && $filters['status'] !== $key ) {
				continue;
			}

			$group = ( isset( $groups[ $key ] ) && is_array( $groups[ $key ] ) ) ? $groups[ $key ] : array();

			if ( '' !== $filters['search'] ) {
				$group = self::narrow( $group, $filters['search'] );
			}

			self::render_group( $key, $label, $group, $columns, $here, $filters );
		}

		// The fifth list is nobody's group: these students have no Students row, so they have
		// no status and no cohort to be filed under. A group filter is a request for one of
		// the four, and the list stays out of the answer rather than pretending to be a fifth
		// bucket of it. The search still narrows it, because a search is a search.
		if ( '' === $filters['status'] ) {
			self::render_unlinked( WPCPM_Roster_Index::unlinked_for( $record_id ), $filters['search'] );
		}

		self::read_line( $read, 'wpcpm-roster__read wpcpm-roster__read--footer' );

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
	 * "Did not start" is the honest name for the last one: they are the applicants who never
	 * began, which is the first question an institution asks about a cohort.
	 *
	 * @return array<string, string> Group key to heading.
	 */
	public static function group_labels() {
		return array(
			'current'     => __( 'Current', 'wpcredits-program-manager' ),
			'waiting'     => __( 'Waiting for a mentor', 'wpcredits-program-manager' ),
			'finished'    => __( 'Finished', 'wpcredits-program-manager' ),
			'not_started' => __( 'Did not start', 'wpcredits-program-manager' ),
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
	 * The picker and the filter bar: one form, because they are one question.
	 *
	 * A GET form, so what it produces is a URL. Every control's current value is drawn from
	 * the URL that reached this page, which is what makes a filtered roster something a
	 * colleague can be sent.
	 *
	 * @param string $record_id  Institutions record ID, which the export control's own fence needs.
	 * @param array  $cohorts    Cohort key to the number who signed up.
	 * @param string $cohort     The chosen cohort.
	 * @param array  $filters    The chosen group and search term.
	 * @param bool   $can_manage Whether the viewer is a program manager.
	 */
	private static function render_filters( $record_id, array $cohorts, $cohort, array $filters, $can_manage ) {
		echo '<form class="wpcpm-roster__filters" method="get">';

		// Without pretty permalinks the page is addressed by query string, which a GET form
		// would otherwise discard, resubmitting to the site root. The same guard the mentor
		// switcher carries, for the same reason.
		if ( ! get_option( 'permalink_structure' ) ) {
			$queried = get_queried_object_id();

			if ( $queried ) {
				printf( '<input type="hidden" name="page_id" value="%d" />', (int) $queried );
			}
		}

		// Only a manager's switcher argument is honoured, so only a manager's form carries
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

		// One wrapper per field, and not one around the lot: the stylesheet lays a field out
		// as its label above its control, so three fields sharing a wrapper stacked label,
		// control, label, control down the page instead of sitting side by side.
		echo '<p class="wpcpm-roster__filter">';
		printf(
			'<label for="wpcpm-roster-cohort">%s</label> ',
			esc_html__( 'Cohort', 'wpcredits-program-manager' )
		);
		printf( '<select name="%s" id="wpcpm-roster-cohort">', esc_attr( self::ARG_COHORT ) );

		foreach ( $cohorts as $key => $signed_up ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $key, $cohort, false ),
				esc_html(
					sprintf(
						/* translators: 1: cohort name, e.g. "January to June 2026", 2: how many students signed up. */
						__( '%1$s (%2$s)', 'wpcredits-program-manager' ),
						WPCPM_Cohort::label( $key ),
						number_format_i18n( $signed_up )
					)
				)
			);
		}

		echo '</select>';
		echo '</p>';

		echo '<p class="wpcpm-roster__filter">';
		printf(
			'<label for="wpcpm-roster-status">%s</label> ',
			esc_html__( 'Group', 'wpcredits-program-manager' )
		);
		printf( '<select name="%s" id="wpcpm-roster-status">', esc_attr( self::ARG_STATUS ) );
		printf(
			'<option value=""%1$s>%2$s</option>',
			selected( '', $filters['status'], false ),
			esc_html__( 'All students', 'wpcredits-program-manager' )
		);

		foreach ( self::group_labels() as $key => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $key, $filters['status'], false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		echo '</p>';

		echo '<p class="wpcpm-roster__filter">';
		printf(
			'<label for="wpcpm-roster-search">%1$s</label> <input type="search" id="wpcpm-roster-search" name="%2$s" value="%3$s" placeholder="%4$s" />',
			esc_html__( 'Search', 'wpcredits-program-manager' ),
			esc_attr( self::ARG_SEARCH ),
			esc_attr( $filters['search'] ),
			esc_attr__( 'Name, email, WordPress.org, tutor', 'wpcredits-program-manager' )
		);

		echo '</p>';

		// The actions are not a field: they carry no label, and they sit on the baseline of
		// the controls beside them rather than below a caption of their own.
		echo '<p class="wpcpm-roster__actions">';
		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Show', 'wpcredits-program-manager' )
		);

		if ( '' !== $filters['status'] || '' !== $filters['search'] ) {
			printf(
				' <a class="wpcpm-roster__clear" href="%1$s">%2$s</a>',
				esc_url( remove_query_arg( array( self::ARG_STATUS, self::ARG_SEARCH, self::ARG_STUDENT ) ) ),
				esc_html__( 'Clear the filters', 'wpcredits-program-manager' )
			);
		}

		self::render_export( $record_id, $cohort );

		echo '</p>';
		echo '</form>';
	}

	/**
	 * The roster export, as the third control in the actions paragraph.
	 *
	 * A link and not a button: the export is a GET, `WPCPM_Institution_Export::roster_url()`
	 * returns a nonced `admin-post.php` address, and a submit button inside this form would
	 * post the filter bar's own fields to the page instead.
	 *
	 * **The fence is asked again, with `ACT_EXPORT`, rather than inferred from the roster being
	 * on screen.** `handle_roster()` decides on its own `decide( ACT_EXPORT, ... )` and refuses
	 * with `wp_die()`, so the only control worth drawing is one that agrees with that call. It
	 * is a separate row of `WPCPM_Institution_Policy::grounds()` from `ACT_VIEW_ROSTER`, which
	 * is the point of that map being written out in full: today the two rows carry the same
	 * grounds and the agreement gate of decision 6 covers both, so an institution whose
	 * agreement is outstanding reaches neither - but narrowing either row is a one-line diff
	 * there, and the reserved project ground of section 12 would land on some rows and not
	 * others. Reading the answer to the wrong question would then leave a link whose only
	 * destination is a refusal page, and a control that leads to a refusal is worse than no
	 * control: it reads as the program being broken rather than as permission being absent.
	 *
	 * The cohort travels in the link, so the file is the semester on screen. Nothing else does:
	 * `roster_url()` reads the manager switcher out of the request for itself, and
	 * `handle_roster()` resolves the institution for itself (design spec 5.5), because an
	 * institution that arrived inside a link is an institution nobody answered for.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $cohort    The chosen cohort.
	 */
	private static function render_export( $record_id, $cohort ) {
		// `wpcredits-program-manager.php` requires this file before it requires the export
		// module, so the class is not defined while this one is being loaded. The same guard
		// the student detail view is called behind, for the same reason: a missing link costs a
		// school one convenience, an undefined class costs it the whole dashboard.
		if ( ! class_exists( 'WPCPM_Institution_Export' ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EXPORT,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		// **The label names the cohort, not the rows on screen.** `roster_matrix()` narrows by
		// cohort and by nothing else, so the group filter and the search box narrow this page
		// and leave the file whole. One institution has forty-two students on the program at
		// once; a reader who searched for one of them and pressed a link promising "these
		// students" would open a file holding the other forty-one.
		printf(
			' <a class="wpcpm-roster__export" href="%1$s">%2$s</a>',
			esc_url( WPCPM_Institution_Export::roster_url( $cohort ) ),
			esc_html__( 'Download this cohort (CSV)', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The comparison strip: this cohort against the calendar semester before it.
	 *
	 * Two numbers each, which is all decision 11 allows: how many signed up and how many
	 * graduated. An empty previous semester says so in words rather than printing zeros,
	 * because "0 signed up, 0 graduated" reads as a failure and "no students started in July
	 * to December 2025" reads as what happened.
	 *
	 * @param array  $rows   The institution's index rows.
	 * @param string $cohort The chosen cohort.
	 * @param int    $read   Unix time the index was read.
	 */
	private static function render_strip( array $rows, $cohort, $read ) {
		$now      = WPCPM_Cohort::participation( $rows, $cohort );
		$previous = WPCPM_Cohort::previous( $cohort );

		echo '<section class="wpcpm-roster__strip">';
		printf(
			'<h3 class="wpcpm-roster__strip-title">%s</h3>',
			esc_html( WPCPM_Cohort::label( $cohort ) )
		);

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

		printf( '<p class="wpcpm-roster__strip-now">%s</p>', esc_html( implode( ' ', $facts ) ) );

		if ( '' === $previous ) {
			// NONE has no semester before it, so there is nothing to compare against. Said
			// plainly, because a silent strip would look like a missing number.
			printf(
				'<p class="wpcpm-roster__strip-then">%s</p>',
				esc_html__( 'These students have no start date, so there is no earlier semester to compare them with.', 'wpcredits-program-manager' )
			);
		} else {
			$then = WPCPM_Cohort::participation( $rows, $previous );

			if ( 0 === $then['signed_up'] ) {
				printf(
					'<p class="wpcpm-roster__strip-then">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: the earlier semester, e.g. "July to December 2025". */
							__( 'No students started in %s.', 'wpcredits-program-manager' ),
							WPCPM_Cohort::label( $previous )
						)
					)
				);
			} else {
				printf(
					'<p class="wpcpm-roster__strip-then">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: the earlier semester, 2: how many signed up then, 3: how many of them graduated. */
							__( 'Compared with %1$s: %2$s signed up, %3$s graduated.', 'wpcredits-program-manager' ),
							WPCPM_Cohort::label( $previous ),
							number_format_i18n( $then['signed_up'] ),
							number_format_i18n( $then['graduated'] )
						)
					)
				);
			}
		}

		self::read_line( $read, 'wpcpm-roster__read wpcpm-roster__read--strip' );

		echo '</section>';
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
	 * One of the four groups: its heading, its count, its explanation and its table.
	 *
	 * **Every group is a disclosure.** Finished and Did not start start closed, as design spec
	 * 7.5 asks: they are counted every time and read rarely. Current and Waiting start open,
	 * and fold: the product owner asked for the same chevron on every panel of the page, and
	 * a heading that cannot be folded beside three that can is a control that is there on one
	 * row and gone on the next. An empty group still prints, with its zero, so that a group a
	 * filter emptied is visibly empty rather than missing.
	 *
	 * **A long group starts closed whichever group it is.** One institution has forty-two
	 * students on the program at once, and an open list of forty-two cards buries everything
	 * under it: the other groups, the people, the agreement. Past `OPEN_MAX` the group starts
	 * closed with its count on the row, which is the number a school is usually looking for
	 * anyway, and one press opens it. Below that it starts open, because a list of six that
	 * has to be opened is a list that has been hidden for no reason.
	 *
	 * @param string $key     Group key.
	 * @param string $label   Group heading.
	 * @param array  $rows    The group's rows, already filtered.
	 * @param array  $columns The columns the fence permits.
	 * @param string $here    The current URL, without the detail view's argument.
	 * @param array  $filters The chosen group and search term.
	 */
	private static function render_group( $key, $label, array $rows, array $columns, $here, array $filters ) {
		$count = count( $rows );

		// **Every group folds, and the two a school works from start open.** Current and
		// Waiting used to be plain headings while Finished and Not started were disclosures,
		// so half the page could be closed and half could not, and a reader who had folded
		// the finished students away reached for the same chevron on the current ones and
		// found nothing there. One shape for all four; what differs is only whether the group
		// starts open, which the two active groups do unless they are long enough to push the
		// rest of the page off the screen.
		$open = ! in_array( $key, array( 'finished', 'not_started' ), true ) && $count <= self::OPEN_MAX;

		printf( '<section class="wpcpm-group wpcpm-roster__group wpcpm-roster__group--%s">', esc_attr( $key ) );

		printf( '<details class="wpcpm-group__disclosure"%s>', $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><span class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html( $label ),
			esc_html( number_format_i18n( $count ) )
		);
		echo '<div class="wpcpm-group__body">';

		printf( '<p class="wpcpm-muted wpcpm-roster__note">%s</p>', esc_html( self::group_note( $key ) ) );

		if ( 0 === $count ) {
			printf(
				'<p class="wpcpm-roster__empty">%s</p>',
				esc_html(
					( '' !== $filters['search'] || '' !== $filters['status'] )
						? __( 'No students in this group match the filters.', 'wpcredits-program-manager' )
						: __( 'No students in this group.', 'wpcredits-program-manager' )
				)
			);
		} else {
			self::render_table( $label, $rows, $columns, $here );
		}

		echo '</div></details>';
		echo '</section>';
	}

	/**
	 * What a group means, in the words design spec 7.5 uses for it.
	 *
	 * @param string $key Group key.
	 * @return string
	 */
	private static function group_note( $key ) {
		$notes = array(
			'current'     => __( 'On the program now, with a mentor and a report record.', 'wpcredits-program-manager' ),
			'waiting'     => __( 'Signed up, with no report record yet. The mentor column says whether a mentor has been assigned.', 'wpcredits-program-manager' ),
			'finished'    => __( 'Mentoring has finished. Their details are kept for reference.', 'wpcredits-program-manager' ),
			'not_started' => __( 'Applicants who never began the program.', 'wpcredits-program-manager' ),
		);

		return isset( $notes[ $key ] ) ? $notes[ $key ] : '';
	}

	/**
	 * One group's students, a card each.
	 *
	 * Not a table. Eleven columns do not fit a page at any width worth having, and the table
	 * this used to be answered that by scrolling sideways inside its own box: a school looking
	 * for a tutor's name had to drag the roster across to find it, and the page it sits on is
	 * the only one in the plugin that behaved that way. The Mentor Report Card had already
	 * settled the question for a list of students you have to read rather than compare, so
	 * this is that component: a disclosure a student at a time, the name and what identifies
	 * them on the closed row, everything else inside.
	 *
	 * The classes are the mentor card's own, deliberately. Sharing the markup means sharing
	 * the stylesheet, the chevron, the focus ring and the print rules, and it means the two
	 * pages cannot drift apart the next time either is touched.
	 *
	 * Every permitted column becomes a row, and a column the fence removed is simply not
	 * there; an empty one still prints its label, because a blank a school can see is
	 * information and a row that vanishes is a question about whether the page is broken.
	 *
	 * @param string $label   The group's heading, for the list's accessible name.
	 * @param array  $rows    The group's rows.
	 * @param array  $columns The columns the fence permits.
	 * @param string $here    The current URL, without the detail view's argument.
	 */
	private static function render_table( $label, array $rows, array $columns, $here ) {
		if ( empty( $columns ) ) {
			return;
		}

		printf( '<ul class="wpcpm-roster__students" aria-label="%s">', esc_attr( $label ) );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			self::render_student( $row, $columns, $here, count( $rows ) );
		}

		echo '</ul>';
	}

	/**
	 * One student, as the card the Mentor Report Card draws.
	 *
	 * Open when the group holds one student, closed otherwise, which is the rule the mentor
	 * card follows: collapsing a list of one helps nobody, and a list of twelve is unreadable
	 * without it.
	 *
	 * @param array  $row     The index row.
	 * @param array  $columns The columns the fence permits.
	 * @param string $here    The current URL, without the detail view's argument.
	 * @param int    $total   How many students are in this group.
	 */
	private static function render_student( array $row, array $columns, $here, $total ) {
		$cells   = self::cells( $row, $here );
		$name    = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		$name    = '' === $name ? __( 'Unnamed student', 'wpcredits-program-manager' ) : $name;
		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

		// `wpcpm-mentee` as well as the list class: on the mentor page the card's border sits
		// on the element wrapping the disclosure, not on the disclosure itself, and the
		// wrapper here is this list item. Without it the cards would be borderless rows that
		// only look like the mentor page's from the summary line inwards.
		echo '<li class="wpcpm-mentee wpcpm-roster__student-item">';
		printf( '<details class="wpcpm-mentee__disclosure wpcpm-roster__card"%s>', 1 === (int) $total ? ' open' : '' );
		echo '<summary class="wpcpm-mentee__summary">';
		echo '<div class="wpcpm-mentee__identity">';

		// The name is a heading and, where the student has an account, the way into their own
		// card. The link is inside the summary, so it is reached by the keyboard before the
		// disclosure opens rather than only after.
		printf(
			'<h4 class="wpcpm-mentee__name">%s</h4>',
			$user_id > 0
				? sprintf(
					'<a class="wpcpm-roster__student" href="%1$s">%2$s</a>',
					esc_url( add_query_arg( self::ARG_STUDENT, $user_id, $here ) ),
					esc_html( $name )
				)
				: esc_html( $name )
		);

		// The program badge, from the same place the mentor card takes it, so one student
		// wears the same colour on both pages.
		$program = self::program_of( $row );

		if ( '' !== $program ) {
			printf(
				'<span class="wpcpm-badge%1$s">%2$s</span>',
				'' === WPCPM_Program::badge( $program ) ? '' : esc_attr( ' wpcpm-badge--' . WPCPM_Program::badge( $program ) ),
				esc_html( WPCPM_Program::label( $program ) )
			);
		}

		// Enough to tell one closed card from another without opening it: when they were here
		// and who mentored them, which is what a school scans a roster for.
		// The dates, and the mentor's name if there is one. The Mentor column's own cell is not
		// what goes here: where there is no name it carries a sentence explaining whether one
		// has been assigned, which is the right answer in a labelled row and, repeated under
		// every name in a group of eight, is noise.
		$preview = array_filter(
			array(
				self::plain( isset( $cells['students|Start Date'] ) ? $cells['students|Start Date'] : '' ),
				self::mentor_name_of( $row ),
			),
			'strlen'
		);

		if ( ! empty( $preview ) ) {
			printf( '<span class="wpcpm-mentee__preview">%s</span>', esc_html( implode( ' · ', $preview ) ) );
		}

		echo '</div>';
		echo '<span class="wpcpm-mentee__toggle" aria-hidden="true"></span>';
		echo '</summary>';

		echo '<div class="wpcpm-mentee__body">';
		printf(
			'<table class="wpcpm-mentee__table"><caption class="screen-reader-text">%s</caption><tbody>',
			esc_html(
				sprintf(
					/* translators: %s: student name. */
					__( 'Program details for %s', 'wpcredits-program-manager' ),
					$name
				)
			)
		);

		foreach ( $columns as $key => $heading ) {
			// The name is the card's own heading; repeating it as the first row of its body
			// would be the page telling the reader something they are already looking at.
			if ( 'students|Full Name' === $key ) {
				continue;
			}

			$value = ( isset( $cells[ $key ] ) && '' !== $cells[ $key ] ) ? $cells[ $key ] : '';
			$empty = ( '' === $value );

			printf( '<tr class="wpcpm-mentee__row%1$s wpcpm-roster__row--%2$s">', $empty ? ' is-empty' : '', esc_attr( self::column_slug( $key ) ) );
			printf(
				'<th scope="row" data-label="%1$s"><span class="wpcpm-mentee__label">%2$s</span></th>',
				esc_attr__( 'Field', 'wpcredits-program-manager' ),
				esc_html( $heading )
			);
			printf(
				'<td class="wpcpm-mentee__value" data-label="%1$s">%2$s</td>',
				esc_attr__( 'Value', 'wpcredits-program-manager' ),
				$empty ? esc_html( self::blank_text() ) : $value // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by cells(), which escapes every value it interpolates.
			);
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</details>';
		echo '</li>';
	}

	/**
	 * The mentor's name for a card's preview line, or nothing.
	 *
	 * Read from the same cached block `cells()` reads, so the closed row and the Mentor row
	 * inside cannot disagree about who it is.
	 *
	 * @param array $row The index row.
	 * @return string
	 */
	private static function mentor_name_of( array $row ) {
		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

		if ( $user_id < 1 ) {
			return '';
		}

		$mentor = get_user_meta( $user_id, WPCPM_Students_Sync::META_MENTOR, true );

		return ( is_array( $mentor ) && isset( $mentor['name'] ) && is_scalar( $mentor['name'] ) )
			? trim( (string) $mentor['name'] )
			: '';
	}

	/**
	 * A cell's text, with any markup taken off.
	 *
	 * The preview line on a closed card is plain text inside a span, and `cells()` returns
	 * markup for the columns that carry a link or a badge. Stripping is what lets the preview
	 * reuse those values instead of computing them a second way and drifting from them.
	 *
	 * @param string $html A cell's markup.
	 * @return string
	 */
	private static function plain( $html ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * One student's cells, keyed as `columns()` is, each already escaped.
	 *
	 * The index row carries what the Students table holds; the team, the website and the hours
	 * live on the Students Reports row and reach this page through `wpcpm_student_program`,
	 * which the students sync writes for every account. **Every value taken out of that block
	 * is read out by name, one at a time**: it also carries the student's accessibility
	 * disclosure, which was made to the program and is not the school's to read, so a block
	 * printed wholesale is a disclosure this page has no right to make.
	 *
	 * @param array  $row  An index row.
	 * @param string $here The current URL, without the detail view's argument.
	 * @return array<string, string> Column key to cell markup.
	 */
	private static function cells( array $row, $here ) {
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

		$name = $get( 'name' );

		if ( '' === $name ) {
			$name = __( '(no name)', 'wpcredits-program-manager' );
		}

		// The detail view is reached by user ID, so a student whose account has not been
		// created is a name and not a link. The link carries the cohort and the filters with
		// it, so the browser's Back is not the only way back to the row.
		$student = ( $user_id > 0 )
			? sprintf(
				'<a class="wpcpm-roster__student" href="%1$s">%2$s</a>',
				esc_url( add_query_arg( self::ARG_STUDENT, $user_id, $here ) ),
				esc_html( $name )
			)
			: sprintf( '<span class="wpcpm-roster__student">%s</span>', esc_html( $name ) );

		$end      = $get( 'end' );
		$username = $get( 'username' );

		return array(
			'students|Full Name'             => $student,
			'students|Status'                => self::program_cell( $cached( $program, 'program' ), $get( 'status' ) ),
			'students|Start Date'            => self::dates_cell( $get( 'start' ), $end ),
			'students|End Date'              => self::days_cell( $end ),
			'students|Mentor'                => self::mentor_cell( $mentor_name, ! empty( $row['has_mentor'] ), ! empty( $row['reports'] ) ),
			'students|WP Profile'            => '' === $username ? '' : sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( 'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/' ),
				esc_html( $username )
			),
			'reports|Main Contribution Team' => esc_html( $team ),
			'reports|Personal Website URL'   => self::website_cell( $website ),
			'students|Your field of study'   => esc_html( $get( 'field_of_study' ) ),
			'students|Tutor '                => esc_html( $get( 'tutor' ) ),
			'reports|Hours'                  => self::hours_cell( $hours, $track ),
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
	 * @param array $row The index row.
	 * @return string
	 */
	private static function program_of( array $row ) {
		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

		if ( $user_id < 1 ) {
			return '';
		}

		$program = get_user_meta( $user_id, WPCPM_Students_Sync::META_PROGRAM, true );

		return ( is_array( $program ) && isset( $program['program'] ) && is_scalar( $program['program'] ) )
			? trim( (string) $program['program'] )
			: '';
	}

	/**
	 * The Program column, from the right vocabulary.
	 *
	 * The column is named "Program", and the program is the Students Reports status: Sensei,
	 * 50 hours, the Developer Track, paused, pending. Only a row with an account carries it,
	 * in the program meta the students sync writes. The index row's own `status` is the
	 * Students table's pipeline status, which is a different list (Interested, Not moving
	 * forward, Fail, and the cohort names), so it is printed plainly, and only when there is no
	 * program to show: a row that never started has no program, and hiding its status would
	 * make it vanish from a list whose whole point is that nothing does.
	 *
	 * @param string $program The reports status, or ''.
	 * @param string $status  The pipeline status, or ''.
	 * @return string Cell markup.
	 */
	private static function program_cell( $program, $status ) {
		$program = trim( (string) $program );

		if ( '' !== $program ) {
			return self::badge( $program );
		}

		$status = trim( (string) $status );

		return '' === $status ? '' : sprintf( '<span class="wpcpm-muted">%s</span>', esc_html( $status ) );
	}

	/**
	 * The program badge for one Airtable status, or an empty string when there is no status.
	 *
	 * One calculation, drawn by the roster table and by the fifth list, so two lists on one
	 * page cannot paint the same student's track in two different colours.
	 *
	 * @param string $status Airtable status.
	 * @return string
	 */
	private static function badge( $status ) {
		$status = trim( (string) $status );

		if ( '' === $status ) {
			return '';
		}

		$modifier = WPCPM_Program::badge( $status );

		return sprintf(
			'<span class="%1$s">%2$s</span>',
			esc_attr( 'wpcpm-badge' . ( '' !== $modifier ? ' wpcpm-badge--' . $modifier : '' ) ),
			esc_html( WPCPM_Program::label( $status ) )
		);
	}

	/**
	 * The dates cell: both dates when there are both, and a named gap when there is no start.
	 *
	 * Design spec 7.5 gives a row with no start date a "Set start date" link into the edit
	 * form. The form is Phase 4, so this phase names the gap and stops there: a link that
	 * went nowhere would be worse than the marker it replaced.
	 *
	 * @param string $start Start date, `Y-m-d`.
	 * @param string $end   End date, `Y-m-d`.
	 * @return string
	 */
	private static function dates_cell( $start, $end ) {
		if ( '' !== $start ) {
			return esc_html( WPCPM_Mentors_Dashboard::format_dates( $start, $end ) );
		}

		// No start date is a fact about the record rather than an empty cell, and it is the
		// one every roster filter has to file somewhere, so it is named here whether or not
		// an end date happens to be recorded beside it.
		$cell = sprintf(
			'<span class="wpcpm-roster__nostart">%s</span>',
			esc_html__( 'No start date', 'wpcredits-program-manager' )
		);

		if ( '' !== $end ) {
			$cell .= sprintf(
				'<br /><span class="wpcpm-muted">%s</span>',
				esc_html( WPCPM_Mentors_Dashboard::format_dates( '', $end ) )
			);
		}

		return $cell;
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
	 * The mentor cell: the mentor's name, or which kind of nothing this is.
	 *
	 * "A mentor is assigned" and "no mentor yet" are different answers to the school's
	 * question, and design spec 7.5 asks for both: the first is waiting on the automation
	 * that creates the report record, the second is waiting on the program.
	 *
	 * @param string $name       The mentor's name from the cached card, if there is one.
	 * @param bool   $has_mentor  Whether the Students row links a mentor.
	 * @param bool   $has_report  Whether the index knows of a Students Reports row.
	 * @return string
	 */
	private static function mentor_cell( $name, $has_mentor, $has_report = false ) {
		if ( '' !== $name ) {
			return esc_html( $name );
		}

		if ( ! $has_mentor ) {
			return sprintf( '<span class="wpcpm-muted">%s</span>', esc_html__( 'No mentor yet.', 'wpcredits-program-manager' ) );
		}

		// **An empty name has two causes and the sentence used to name only one of them.** It
		// said the report record had not been created yet, which for most rows was simply
		// untrue: the index was holding that record's ID while the sentence denied it. The
		// missing thing was the name, not the record. Saying which of the two it is costs one
		// value the row already carries, and getting it wrong tells a school its student is
		// further behind than they are.
		return sprintf(
			'<span class="wpcpm-muted">%s</span>',
			esc_html(
				$has_report
					? __( 'A mentor is assigned. Their name has not reached this page yet.', 'wpcredits-program-manager' )
					: __( 'A mentor is assigned. The report record has not been created yet.', 'wpcredits-program-manager' )
			)
		);
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
	 * **A track worked to no target prints no denominator.** `WPCPM_Program::hours_target()`
	 * answers 0 for the Developer Track, which is worked to a body of merged contributions
	 * rather than to a clock, and 0 again for a status its map has never heard of, because a
	 * track the program adds and does not count hours for is a supported state and not an
	 * omission. Both read "12 h", never "12 of 0".
	 *
	 * Three things the live column does that this cell has to survive, read off the base rather
	 * than assumed:
	 *
	 * - The value is **fractional** for some students (6.2, 135.5), so it is printed to as many
	 *   places as the base sent it with, counted by `hours_decimals()` off the string itself.
	 *   An `intval()` here would print 6 for 6.2 and quietly round a term's work down.
	 * - It runs **past the target** for others (400 against a 150-hour track). That is printed
	 *   as it stands: "400 of 150" is what the records say, and clamping to the target would
	 *   hide an overrun from the one party paying attention to it.
	 * - An unset cell is **absent, not zero**. '' returns '' so the row says "Not recorded",
	 *   while a logged 0 prints "0 of 150": a student nobody has logged for and a student who
	 *   has done nothing are different answers to a school's question.
	 *
	 * @param string $hours  The value as the base holds it, or ''.
	 * @param string $status The status the target is read from.
	 * @return string Cell markup.
	 */
	private static function hours_cell( $hours, $status ) {
		$hours = trim( (string) $hours );

		if ( '' === $hours ) {
			return '';
		}

		// A Number column that has stopped being a number is printed as it stands rather than
		// cast: `(float) 'n/a'` is 0.0, and a cell reading "0 of 150" would tell a school its
		// student had done nothing on the strength of a field type somebody changed.
		if ( ! is_numeric( $hours ) ) {
			return esc_html( $hours );
		}

		$logged = number_format_i18n( (float) $hours, self::hours_decimals( $hours ) );

		if ( ! WPCPM_Program::has_hours_target( $status ) ) {
			return esc_html(
				sprintf(
					/* translators: %s: hours logged, e.g. "12" or "6.2". */
					__( '%s h', 'wpcredits-program-manager' ),
					$logged
				)
			);
		}

		return esc_html(
			sprintf(
				/* translators: 1: hours logged, 2: the track's target in hours. */
				__( '%1$s of %2$s', 'wpcredits-program-manager' ),
				$logged,
				number_format_i18n( WPCPM_Program::hours_target( $status ) )
			)
		);
	}

	/**
	 * How many decimal places one hours value is printed to, at most two.
	 *
	 * **Counted off the string the base sent, not off the float.** A fixed 0 would print 136 for
	 * 135.5 and rewrite a student's work with a display decision; a fixed 1 or 2 would print
	 * "150.0" for the student who logged exactly 150, because `number_format_i18n()` pads. So
	 * the value decides how it is written, and it decides from its own digits: comparing a
	 * float against its own rounding to answer the same question means trusting an equality
	 * that binary fractions do not owe anybody.
	 *
	 * Trailing zeros do not count, so a "6.20" somebody typed reads as "6.2", and two places is
	 * the ceiling: these are hours entered by hand on a form, not a measurement.
	 *
	 * @param string $hours The value as the base holds it, already known to be numeric.
	 * @return int 0, 1 or 2.
	 */
	private static function hours_decimals( $hours ) {
		$dot = strpos( (string) $hours, '.' );

		if ( false === $dot ) {
			return 0;
		}

		return min( 2, strlen( rtrim( substr( (string) $hours, $dot + 1 ), '0' ) ) );
	}

	/**
	 * The students with an account and a report record whose Students row does not exist.
	 *
	 * Nothing is silently dropped: these people are on the program, the roster groups cannot
	 * hold them because the groups are built from Students rows, and an institution that
	 * counted its students without them would be short. Read-only, and the line says whose
	 * job the missing record is.
	 *
	 * @param mixed  $rows   What `WPCPM_Roster_Index::unlinked_for()` returned.
	 * @param string $search The search term, or ''.
	 */
	private static function render_unlinked( $rows, $search ) {
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

		if ( empty( $people ) ) {
			return;
		}

		echo '<section class="wpcpm-group wpcpm-roster__group wpcpm-roster__group--unlinked">';
		printf(
			'<h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3>',
			esc_html__( 'Not yet in the Students table', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $people ) ) )
		);
		printf(
			'<p class="wpcpm-muted wpcpm-roster__note">%s</p>',
			esc_html__( 'These students are reporting on the program, but the program records have no enrolment row for them yet, so they are not counted in the groups above. A program manager needs to complete the record.', 'wpcredits-program-manager' )
		);

		echo '<ul class="wpcpm-roster__unlinked">';

		foreach ( $people as $person ) {
			echo '<li>';
			printf( '<span class="wpcpm-roster__student">%s</span>', esc_html( '' !== $person['name'] ? $person['name'] : $person['email'] ) );

			if ( '' !== $person['email'] && '' !== $person['name'] ) {
				printf( ' <span class="wpcpm-muted">%s</span>', esc_html( $person['email'] ) );
			}

			$badge = self::badge( $person['status'] );

			if ( '' !== $badge ) {
				echo ' ' . $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by badge(), which escapes every value it interpolates.
			}

			echo '</li>';
		}

		echo '</ul>';
		echo '</section>';
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
	 * @param array  $row    An index row, or one of the fifth list's people.
	 * @param string $search The search term.
	 * @return bool
	 */
	private static function matches( array $row, $search ) {
		foreach ( array( 'name', 'email', 'username', 'tutor', 'field_of_study' ) as $field ) {
			if ( ! isset( $row[ $field ] ) || ! is_scalar( $row[ $field ] ) ) {
				continue;
			}

			if ( false !== stripos( (string) $row[ $field ], $search ) ) {
				return true;
			}
		}

		return false;
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
	 * What an empty cell says.
	 *
	 * A named gap rather than a blank, for the reason the mentor card gives: a gap in the
	 * program's records should look like a gap in the program's records and not like a cell
	 * this page forgot to fill.
	 *
	 * @return string
	 */
	private static function blank() {
		return sprintf(
			'<span class="wpcpm-muted">%s</span>',
			esc_html( self::blank_text() )
		);
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
	 * When the numbers on this page were read.
	 *
	 * Printed by the strip and again by the footer, because both carry counts and a reader
	 * who scrolled past the first one is looking at the second.
	 *
	 * @param int    $read    Unix time the index was read.
	 * @param string $classes The paragraph's classes.
	 */
	private static function read_line( $read, $classes ) {
		$read = (int) $read;

		if ( $read <= 0 ) {
			printf(
				'<p class="%1$s">%2$s</p>',
				esc_attr( $classes ),
				esc_html__( 'These students have not been read from the program records yet.', 'wpcredits-program-manager' )
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
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $read ),
					human_time_diff( $read, time() )
				)
			)
		);
	}
}
