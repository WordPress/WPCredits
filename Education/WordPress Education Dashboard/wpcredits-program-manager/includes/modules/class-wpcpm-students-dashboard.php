<?php
/**
 * Students module — the student's own program page.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the page each student sees: their own program details, and the mentor
 * assigned to them with enough contact detail to actually reach them.
 *
 * One page for everyone, rendered against the logged-in user — the same shape as
 * the mentor page, and for the same reason: a per-student permalink would invite
 * reading somebody else's. Program managers can inspect any student's view via
 * `?wpcpm_student_view=<id>`.
 */
class WPCPM_Students_Dashboard {

	const SHORTCODE = 'wpcpm_student_dashboard';
	const BLOCK     = 'wpcpm/student-dashboard';
	const OPT_PAGE  = 'wpcpm_student_page_id';
	const STYLE     = 'wpcpm-student-dashboard';
	const SCRIPT    = 'wpcpm-student-feedback';

	/**
	 * Hooks.
	 */
	/**
	 * Option: which title revision has been applied to the page.
	 *
	 * A number, not a flag. It started as a flag, and that was wrong the first time the
	 * page was renamed twice: an install that had already run the migration was skipped
	 * for ever, so it kept a title two revisions old. Comparing a revision means each
	 * rename runs once and only once, however many there have been.
	 */
	const OPT_TITLE_FIXED = 'wpcpm_student_page_title_fixed';

	/** The current title revision. Bump this whenever the page's title changes. */
	const TITLE_VERSION = 3;

	/**
	 * Page titles this plugin has shipped for the student page.
	 *
	 * Only a title the plugin itself created is ever replaced — anything a site has
	 * renamed by hand is theirs and is left alone. Every past title stays on this list,
	 * so a site on any earlier revision converges on the current one.
	 */
	const OLD_TITLES = array( 'My Programme', 'My Program', 'My Profile' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		// After `register`, and once only. `ensure_page()` sets a title when it *creates*
		// the page, so an install that predates a wording change keeps the old one for
		// ever — which is how "My Programme" survived the switch to American spelling.
		add_action( 'init', array( __CLASS__, 'maybe_rename_page' ), 20 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'replace_admin_dashboard' ) );
	}

	/**
	 * Bring an existing page's title in line with the current wording.
	 *
	 * Runs at most once per site: the flag is written whatever the outcome, so a site
	 * that has deliberately renamed the page is not asked again on every request.
	 *
	 * The *slug* is deliberately untouched. It is `student-dashboard`, the theme matches
	 * its template on it, and renaming it would break that and every existing link.
	 */
	public static function maybe_rename_page() {
		if ( (int) get_option( self::OPT_TITLE_FIXED ) >= self::TITLE_VERSION ) {
			return;
		}

		update_option( self::OPT_TITLE_FIXED, self::TITLE_VERSION, false );

		$page_id = (int) get_option( self::OPT_PAGE );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page instanceof WP_Post || ! in_array( $page->post_title, self::OLD_TITLES, true ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $page->ID,
				'post_title' => __( 'Student Report Card', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * Register the shortcode, block and stylesheet.
	 */
	public static function register() {
		// Depends on the mentor stylesheet on purpose. The two pages share a shell —
		// the identity header, the "view as" switcher, the detail tables, badges and
		// buttons — and defining that once means they cannot drift apart, and that a
		// theme styling one page has already styled the other.
		WPCPM_Mentors_Dashboard::register_assets();

		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/student.css',
				array( WPCPM_Mentors_Dashboard::STYLE ),
				WPCPM_VERSION
			);
		}

		// The surveys' only script. In the footer and with no dependencies: it reads rules out of
		// the markup and hides two questions, so nothing on the page waits for it.
		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::SCRIPT,
				WPCPM_PLUGIN_URL . 'assets/js/feedback.js',
				array(),
				WPCPM_VERSION,
				true
			);
		}

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/student-dashboard';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
		}
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * Whether a user has program details of their own.
	 *
	 * Role or record link, for the same reason the mentor side does it: an account
	 * can be matched to an Airtable student record without holding the role.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function is_student( $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT ) ) {
			return true;
		}

		return '' !== trim( (string) get_user_meta( $user->ID, WPCPM_Students_Sync::META_RECORD_ID, true ) );
	}

	/**
	 * Render the page.
	 *
	 * @param array $atts Shortcode or block attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts( array( 'title' => '' ), is_array( $atts ) ? $atts : array(), self::SHORTCODE );

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );

		if ( ! is_user_logged_in() ) {
			return self::notice(
				__( 'Please log in to see your program details.', 'wpcredits-program-manager' ),
				wp_login_url( self::page_url() ? self::page_url() : home_url( '/' ) ),
				__( 'Log in', 'wpcredits-program-manager' )
			);
		}

		$viewer     = wp_get_current_user();
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$student    = self::resolve_student( $viewer, $can_manage );

		if ( ! $student instanceof WP_User ) {
			return self::notice( WPCPM_Dashboards::nothing_to_show( 'students', $can_manage ), '', '', false );
		}

		$program = WPCPM_Students_Sync::get_program( $student->ID );
		$mentor  = WPCPM_Students_Sync::get_mentor( $student->ID );
		$updated = (int) get_user_meta( $student->ID, WPCPM_Students_Sync::META_UPDATED, true );

		ob_start();

		echo '<div class="wpcpm-dashboard wpcpm-student">';

		if ( ! empty( $atts['title'] ) ) {
			echo '<h2 class="wpcpm-student__title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		if ( $can_manage ) {
			self::render_switcher( $student );
		}

		echo '<div class="wpcpm-student__grid">';

		// Unconditional now. The section owns the student's own identity card, so it has to
		// render even when there is no program data yet — otherwise a student waiting on
		// their first sync sees a page with nobody on it.
		self::render_program( $program, $student );

		self::render_mentor( $mentor );

		echo '</div>';

		// The page's actions, directly under the reference columns. Not part of either
		// column, and above the calendar rather than below it: the course and the report
		// form are what a student opens this page to reach, and they were sitting at the
		// foot of a section tall enough — a month grid and a day's worth of slots — to
		// push them off the screen.
		if ( ! empty( $program ) ) {
			self::render_links( $program, $student );
			self::render_report_form( $program, $student );
		}

		// Outside the grid, spanning the card. It holds a month calendar, which wants more
		// width than half a card gives it — and it splits into its own two columns, booked
		// calls beside the picker.
		WPCPM_Call_Calendar::render_student( $student, $can_manage );

		// Always rendered: the section holds the Student guide, which is a handbook link and
		// has nothing to do with whether an AI provider is configured. Whether the "Need help?"
		// button appears beside it is decided inside, from the audience setting — asked of the
		// audience rather than of the viewer, so a manager inspecting a student does not see it
		// on the student's own page while the student never would.
		// Not through `wp_kses_post()`: it strips `<svg>` outright, which would silently
		// remove the Slack mark. This is the plugin's own markup, escaped as it is built.
		echo WPCPM_Handbook_Assistant::render_resources( 'student' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by render_resources(), which escapes every value it interpolates.

		if ( $updated ) {
			printf(
				'<p class="wpcpm-dashboard__updated">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: human-readable time difference. */
						__( 'Last updated %s ago.', 'wpcredits-program-manager' ),
						human_time_diff( $updated, time() )
					)
				)
			);
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Whose details to show.
	 *
	 * @param WP_User $viewer     Logged-in user.
	 * @param bool    $can_manage Whether they may inspect others.
	 * @return WP_User|null
	 */
	private static function resolve_student( WP_User $viewer, $can_manage ) {
		// Only a manager may look at somebody else's page, so the argument is not even read
		// for anyone else.
		$requested = $can_manage ? WPCPM_Request::id( 'wpcpm_student_view' ) : 0;

		if ( $requested ) {
			$candidate = get_user_by( 'id', $requested );

			if ( $candidate instanceof WP_User && self::is_student( $candidate ) ) {
				return $candidate;
			}
		}

		// Their own details first, so an administrator who is also a student is not
		// shown somebody else's.
		if ( self::is_student( $viewer ) ) {
			return $viewer;
		}

		if ( $can_manage ) {
			$students = self::all_students();

			return ! empty( $students[0] ) ? $students[0] : null;
		}

		return null;
	}

	/**
	 * Every account with program details.
	 *
	 * @return WP_User[]
	 */
	private static function all_students() {
		$users = get_users(
			array(
				'orderby'    => 'display_name',
				'order'      => 'ASC',
				'number'     => 1000,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- Bounded by provisioned students.
					array(
						'key'     => WPCPM_Students_Sync::META_RECORD_ID,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return is_array( $users ) ? $users : array();
	}

	/**
	 * A "view as" control for program managers.
	 *
	 * @param WP_User $current Student being viewed.
	 */
	private static function render_switcher( WP_User $current ) {
		$students = self::all_students();

		if ( count( $students ) < 2 ) {
			return;
		}

		echo '<form class="wpcpm-dashboard__switcher" method="get">';

		if ( ! get_option( 'permalink_structure' ) ) {
			$queried = get_queried_object_id();
			if ( $queried ) {
				printf( '<input type="hidden" name="page_id" value="%d" />', (int) $queried );
			}
		}

		echo '<label for="wpcpm-student-switcher">' . esc_html__( 'Viewing as student', 'wpcredits-program-manager' ) . '</label> ';
		echo '<select name="wpcpm_student_view" id="wpcpm-student-switcher">';

		foreach ( $students as $student ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $student->ID,
				selected( $student->ID, $current->ID, false ),
				esc_html( $student->display_name )
			);
		}

		echo '</select> ';
		echo '<button type="submit" class="wpcpm-button">' . esc_html__( 'Show', 'wpcredits-program-manager' ) . '</button>';
		echo '<span class="wpcpm-dashboard__switcher-note">' . esc_html__( 'Only administrators see this control.', 'wpcredits-program-manager' ) . '</span>';
		echo '</form>';
	}

	/**
	 * The student's own identity block.
	 *
	 * @param WP_User $student Student.
	 * @param array   $program Program details.
	 */
	private static function render_identity( WP_User $student, array $program ) {
		$username = isset( $program['username'] ) ? (string) $program['username'] : '';

		// The same three-element shape as the mentor card — portrait, body, name — and the
		// same classes on the portrait, so the two columns are built the same way rather
		// than merely looking similar. It used to be a full-width `<header>` above both
		// columns, which is why it was sized like a page header.
		echo '<div class="wpcpm-student__card">';

		printf(
			'<img class="wpcpm-avatar wpcpm-avatar--lg" src="%1$s" width="88" height="88" alt="%2$s" loading="lazy" decoding="async" />',
			// Requested at twice the rendered size: these come from a fixed-size endpoint,
			// and asking for 88 gives a soft image on any retina screen.
			esc_url( WPCPM_Mentors_Dashboard::avatar_url( $username, $student->user_email, 176 ) ),
			esc_attr(
				sprintf(
					/* translators: %s: student name. */
					__( 'Profile photo of %s', 'wpcredits-program-manager' ),
					$student->display_name
				)
			)
		);

		echo '<div class="wpcpm-student__card-body">';
		// A paragraph, not a heading. It sits *inside* the section whose `<h3>` is directly
		// above it, and the `<h2>` it used to be would have nested a higher level inside a
		// lower one — which is a real outline error, not a styling preference.
		printf( '<p class="wpcpm-student__name">%s</p>', esc_html( $student->display_name ) );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * The student's program details.
	 *
	 * @param array   $program Program details.
	 * @param WP_User $student The student whose page this is.
	 */
	private static function render_program( array $program, WP_User $student ) {
		$student_id = (int) $student->ID;
		$get        = static function ( $key ) use ( $program ) {
			return isset( $program[ $key ] ) ? (string) $program[ $key ] : '';
		};

		$username = $get( 'username' );
		$website  = $get( 'website' );
		$email    = '' !== $get( 'email' ) ? $get( 'email' ) : (string) $student->user_email;
		$team     = WPCPM_Mentors_Sync::resolve_stored( $get( 'team' ), 'teams' );
		// `program` is the display name the sync stored; `status` is the raw Airtable
		// value the label and the course link are both keyed on.
		$status = '' !== $get( 'status' ) ? $get( 'status' ) : $get( 'program' );

		$fields = array(
			array(
				'label' => __( 'Program', 'wpcredits-program-manager' ),
				'value' => WPCPM_Program::label( $status ),
				// The Learn WordPress course for their track, so the syllabus is one click
				// from the page that says which track they are on.
				'url'   => WPCPM_Program::course_url( $status ),
			),
			array(
				'label' => __( 'Internship duration', 'wpcredits-program-manager' ),
				'value' => WPCPM_Mentors_Dashboard::format_dates( $get( 'start' ), $get( 'end' ) ),
				'icon'  => 'calendar',
			),
			array(
				'label' => __( 'Field of study', 'wpcredits-program-manager' ),
				'value' => $get( 'field_of_study' ),
			),
			array(
				'label' => __( 'Tutor', 'wpcredits-program-manager' ),
				'value' => $get( 'tutor' ),
			),
			array(
				'label' => __( 'Educational institution', 'wpcredits-program-manager' ),
				'value' => WPCPM_Mentors_Sync::resolve_stored( $get( 'institution' ), 'institutions' ),
			),
			array(
				'label' => __( 'WordPress.org', 'wpcredits-program-manager' ),
				'value' => '' !== $username ? '@' . $username : '',
				'url'   => '' !== $username ? 'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/' : '',
				'icon'  => 'profile',
				// The student maintains this one, so the row carries its own editor.
			),
			array(
				'label'    => __( 'Email', 'wpcredits-program-manager' ),
				// Airtable's value when there is one, and the account's own otherwise —
				// about three in ten students have no email in the program records, and the
				// account they are reading this page from always has one.
				'value'    => '' !== $get( 'email' ) ? $get( 'email' ) : $student->user_email,
				'url'      => '' !== $email ? 'mailto:' . $email : '',
				// Not opened in a new tab: that leaves an empty one behind once the mail
				// client takes over.
				'external' => false,
				'icon'     => 'email',
				// No editor. A student's email is their account's, changed under their
				// WordPress profile, and writing it back to Airtable from here would put the
				// two out of step with no way to tell which is right.
			),
			array(
				'label' => __( 'Slack', 'wpcredits-program-manager' ),
				'value' => $get( 'slack' ),
				'url'   => $get( 'slack' ) ? WPCPM_Mentors_Dashboard::slack_url() : '',
				'icon'  => 'slack',
			),
			array(
				'label'     => __( 'Contribution teams', 'wpcredits-program-manager' ),
				'value'     => $team,
				'html'      => WPCPM_Contribution_Teams::links( $team ),
				'icon_html' => WPCPM_Contribution_Teams::label_icon( $team ),
			),
			array(
				'label' => __( 'Personal website', 'wpcredits-program-manager' ),
				'value' => '' !== $website ? preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) : '',
				'url'   => WPCPM_Mentors_Dashboard::normalize_url( $website ),
				'icon'  => 'website',
			),
		);

		echo '<section class="wpcpm-student__section">';
		echo '<h3 class="wpcpm-student__heading">' . esc_html__( 'My profile', 'wpcredits-program-manager' ) . '</h3>';

		self::render_identity( $student, $program );

		// Nothing synced yet. The identity card above still stands, so the section is not
		// empty — and rendering the table here would draw ten rows of "Not set", which
		// looks like missing data rather than data that has not arrived.
		if ( empty( $program ) ) {
			echo '<p class="wpcpm-dashboard__empty">' . esc_html__( 'Your program details have not been read from Airtable yet. They appear here after the next sync.', 'wpcredits-program-manager' ) . '</p>';
			echo '</section>';

			return;
		}

		echo '<table class="wpcpm-student__table"><tbody>';

		foreach ( $fields as $field ) {
			self::render_row( $field, $program, $student_id );
		}

		echo '</tbody></table>';
		echo '</section>';
	}

	/**
	 * The two places a student actually goes: their course, and their report form.
	 *
	 * Its own section, below the two columns rather than tucked under the program table.
	 * They are the only *actions* on this page — everything above is reference — and a
	 * button at the foot of one column reads as belonging to that column rather than to
	 * the page.
	 *
	 * Two sections, one per errand: **My course** and **Report form**. They were one section
	 * holding both buttons, which read as a single task with two links rather than as the two
	 * separate things a student does — the course is what they work through, the report form is
	 * what they file.
	 *
	 * Stacked, and each renders only if its own link exists, so a student with no report form
	 * gets one section rather than a heading over an empty row. Both are rendered outside
	 * `.wpcpm-student__grid`, so neither is placed into one of its two columns.
	 *
	 * **My course has two columns of its own** since 1.49.0: the button, and the hours box the
	 * report form used to open with. Hours is the one number a student updates without having
	 * anything else to report, and it was behind a disclosure with twenty other questions.
	 *
	 * @param array   $program The student's cached program array.
	 * @param WP_User $student The student the page is being drawn for.
	 */
	private static function render_links( array $program, WP_User $student ) {
		$status = ! empty( $program['status'] ) ? (string) $program['status'] : ( isset( $program['program'] ) ? (string) $program['program'] : '' );
		$course = WPCPM_Program::course_url( $status );
		$report = isset( $program['link'] ) ? (string) $program['link'] : '';

		if ( '' !== $course ) {
			// Two columns: the button that opens the course, and the one number a student
			// updates without having anything else to report. Anything more would be the
			// report form, which is the section below.
			echo '<section class="wpcpm-student__section wpcpm-student__links wpcpm-student__links--course">';
			echo '<h3 class="wpcpm-student__heading">' . esc_html__( 'My course', 'wpcredits-program-manager' ) . '</h3>';
			echo '<div class="wpcpm-student__course-cols">';

			printf(
				'<p class="wpcpm-student__actions"><a class="wpcpm-button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
				esc_url( $course ),
				esc_html__( 'Open your course', 'wpcredits-program-manager' )
			);

			WPCPM_Student_Report_Form::render_hours( $student, $program );

			echo '</div></section>';
		}

		// The report form is a section of its own with the fields in it, rendered by the caller —
		// `render_links()` only owns the two link sections.
	}

	/**
	 * The Report form section: the fields, and the prefilled form as a second route.
	 *
	 * @param array   $program The student's cached program row.
	 * @param WP_User $student The student.
	 */
	private static function render_report_form( array $program, WP_User $student ) {
		// The prefilled Airtable form is deliberately *not* linked from here any more. The fields
		// below are the report now, and offering a second route to the same record invited two
		// people — or one person twice — to fill the same thing in two ways.
		echo '<section class="wpcpm-student__section wpcpm-student__links wpcpm-student__links--report" id="wpcpm-report-form">';
		echo '<h3 class="wpcpm-student__heading">' . esc_html__( 'Report form', 'wpcredits-program-manager' ) . '</h3>';

		WPCPM_Student_Report_Form::render( $student, $program );

		// Under the report, in the same section and in disclosures of the same kind — because to a
		// student they are the same errand: the things this page asks them to fill in. They are not
		// the same record, though, and the note below the first one says so: the report is what
		// counts towards their credits, and the surveys are not marked, not seen by their
		// institution, and can be left alone without consequence.
		WPCPM_Student_Feedback::render( $student, $program );

		echo '</section>';
	}

	/**
	 * The mentor assigned to this student.
	 *
	 * @param array $mentor Mentor contact card.
	 */
	private static function render_mentor( array $mentor ) {
		echo '<section class="wpcpm-student__section wpcpm-student__section--mentor">';
		echo '<h3 class="wpcpm-student__heading">' . esc_html__( 'My mentor', 'wpcredits-program-manager' ) . '</h3>';

		// Parenthesized deliberately. PHP binds `&&` tighter than `||`, so this already
		// meant "no mentor at all, or one with neither a name nor a handle" — but a reader
		// has to know the precedence table to be sure, and a later edit that adds a third
		// clause would change the meaning silently.
		if ( empty( $mentor ) || ( empty( $mentor['name'] ) && empty( $mentor['username'] ) ) ) {
			echo '<p class="wpcpm-dashboard__empty">' . esc_html__( 'No mentor is assigned to you yet. Your program manager will introduce you to one.', 'wpcredits-program-manager' ) . '</p>';
			echo '</section>';

			return;
		}

		$get = static function ( $key ) use ( $mentor ) {
			return isset( $mentor[ $key ] ) ? (string) $mentor[ $key ] : '';
		};

		$username = $get( 'username' );
		$name     = $get( 'name' );

		echo '<div class="wpcpm-mentor-card">';

		printf(
			'<img class="wpcpm-avatar wpcpm-avatar--lg" src="%1$s" width="88" height="88" alt="%2$s" loading="lazy" decoding="async" />',
			esc_url( '' !== $get( 'avatar' ) ? $get( 'avatar' ) : WPCPM_Mentors_Dashboard::avatar_url( $username, $get( 'email' ), 176 ) ),
			esc_attr(
				sprintf(
					/* translators: %s: mentor name. */
					__( 'Profile photo of %s', 'wpcredits-program-manager' ),
					$name ? $name : $username
				)
			)
		);

		echo '<div class="wpcpm-mentor-card__body">';
		printf( '<p class="wpcpm-mentor-card__name">%s</p>', esc_html( $name ? $name : '@' . $username ) );

		if ( '' !== $get( 'jobline' ) ) {
			printf( '<p class="wpcpm-mentor-card__jobline">%s</p>', esc_html( $get( 'jobline' ) ) );
		}

		if ( '' !== $get( 'location' ) ) {
			printf( '<p class="wpcpm-mentor-card__location">%s</p>', esc_html( $get( 'location' ) ) );
		}

		$teams = ( isset( $mentor['teams'] ) && is_array( $mentor['teams'] ) ) ? $mentor['teams'] : array();

		if ( ! empty( $teams ) ) {
			echo '<p class="wpcpm-mentor-card__teams">';
			printf( '<span class="wpcpm-mentor-card__teams-label">%s</span> ', esc_html__( 'Teams', 'wpcredits-program-manager' ) );
			foreach ( $teams as $team ) {
				// Linked for the same reason the student's team is: a team name is only
				// useful next to the place you would go to join it. `links()` escapes.
				printf( '<span class="wpcpm-badge">%s</span> ', wp_kses_post( WPCPM_Contribution_Teams::links( $team ) ) );
			}
			echo '</p>';
		}

		echo '</div>';
		echo '</div>';

		echo '<table class="wpcpm-student__table"><tbody>';

		self::render_row(
			array(
				'label'    => __( 'Email', 'wpcredits-program-manager' ),
				'value'    => $get( 'email' ),
				'url'      => $get( 'email' ) ? 'mailto:' . $get( 'email' ) : '',
				'external' => false,
				'icon'     => 'email',
			)
		);
		self::render_row(
			array(
				'label' => __( 'Slack', 'wpcredits-program-manager' ),
				'value' => $get( 'slack' ),
				'url'   => $get( 'slack' ) ? WPCPM_Mentors_Dashboard::slack_url() : '',
				'icon'  => 'slack',
			)
		);
		self::render_row(
			array(
				'label' => __( 'WordPress.org', 'wpcredits-program-manager' ),
				'value' => '' !== $username ? '@' . $username : '',
				'url'   => $get( 'profile' ),
				'icon'  => 'profile',
			)
		);
		self::render_row(
			array(
				'label' => __( 'Website', 'wpcredits-program-manager' ),
				'value' => $get( 'website_label' ),
				'url'   => $get( 'website' ),
				'icon'  => 'website',
			)
		);
		self::render_row(
			array(
				'label' => __( 'GitHub', 'wpcredits-program-manager' ),
				'value' => '' !== $get( 'github' ) ? preg_replace( '#^https?://#', '', untrailingslashit( $get( 'github' ) ) ) : '',
				'url'   => $get( 'github' ),
				'icon'  => 'code',
			)
		);

		echo '</tbody></table>';

		echo '</section>';
	}

	/**
	 * One labeled row.
	 *
	 * @param array $field      Field definition; see WPCPM_Mentors_Dashboard::render_row().
	 * @param array $program    The student's program details, for a row with an editor.
	 * @param int   $student_id Whose details these are; 0 to render no editor.
	 */
	private static function render_row( array $field, array $program = array(), $student_id = 0 ) {
		$label    = isset( $field['label'] ) ? (string) $field['label'] : '';
		$value    = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';
		$url      = isset( $field['url'] ) ? trim( (string) $field['url'] ) : '';
		$external = ! isset( $field['external'] ) || (bool) $field['external'];
		$empty    = ( '' === $value );

		// A cell the caller built itself, for a value that is several links rather than
		// one — see the note on the mentor table's own version.
		$html = isset( $field['html'] ) ? (string) $field['html'] : '';

		// `icon_html` for a row whose icon depends on its value — the contribution team's
		// changes with the team, and is a question mark when none is chosen. `icon` is the
		// fixed case, a key into WPCPM_Icons.
		$icon = isset( $field['icon_html'] )
			? (string) $field['icon_html']
			: ( isset( $field['icon'] ) ? WPCPM_Icons::svg( $field['icon'] ) : '' );

		printf( '<tr class="wpcpm-student__row%s">', $empty ? ' is-empty' : '' );

		// The icon goes in the label, not the value: it identifies the *kind* of contact
		// detail, which is what the label says, and a row with no value still has one.
		printf(
			'<th scope="row"><span class="wpcpm-student__label">%1$s%2$s</span></th>',
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from WPCPM_Icons; kses would lowercase viewBox and break it.
			esc_html( $label )
		);

		// A wrapper inside the cell rather than making the `<td>` itself a flex container:
		// changing a cell's `display` takes it out of the table layout algorithm, and the
		// column widths go with it.
		echo '<td><div class="wpcpm-student__cell"><span class="wpcpm-student__value">';

		if ( $empty ) {
			printf( '<span class="wpcpm-student__missing">%s</span>', esc_html__( 'Not set', 'wpcredits-program-manager' ) );
		} elseif ( '' !== $html ) {
			echo wp_kses_post( $html );
		} elseif ( '' !== $url ) {
			printf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( $url ),
				$external ? ' target="_blank" rel="noopener noreferrer"' : '',
				esc_html( $value )
			);
		} else {
			echo esc_html( $value );
		}

		echo '</span>';

		echo '</div></td></tr>';
	}

	/**
	 * A standalone notice.
	 *
	 * @param string $message     Message.
	 * @param string $action_url  Optional action URL.
	 * @param string $action_text Optional action label.
	 * @param bool   $escape      Whether to escape the message. Pass false only for
	 *                              markup this class built itself.
	 * @return string
	 */
	private static function notice( $message, $action_url = '', $action_text = '', $escape = true ) {
		$html = '<div class="wpcpm-dashboard wpcpm-student wpcpm-student--notice"><p>' . ( $escape ? esc_html( $message ) : wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) ) . '</p>';

		if ( $action_url && $action_text ) {
			$html .= '<p><a class="wpcpm-button" href="' . esc_url( $action_url ) . '">' . esc_html( $action_text ) . '</a></p>';
		}

		return $html . '</div>';
	}

	/**
	 * Whether this user should be routed to their report card.
	 *
	 * Recognised the same way the toolbar link recognises them, then narrowed twice: not a
	 * program manager, not anyone who can write posts. Those two exclusions are also what
	 * makes it safe to start from `is_student()`, which counts somebody linked to an Airtable
	 * record without holding the role.
	 *
	 * @param int|WP_User|null $user Optional user.
	 * @return bool
	 */
	private static function should_route( $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		// The same test the toolbar link uses — the role *or* an Airtable link — and not the
		// role alone. `is_student()` counts somebody matched to a record without holding the role,
		// which is how a student provisioned before the role existed, or one whose role was
		// removed and restored by hand, is still recognised. Testing the role here meant the
		// toolbar offered them their page while the redirects behaved as though they were not
		// a student at all: they logged in and stayed on the wp-admin dashboard.
		if ( ! self::is_student( $user ) ) {
			return false;
		}

		// ...but not somebody whose studenting has ended. Going inactive removes the role and sets
		// this flag to 0 while deliberately leaving the Airtable link in place, so
		// `is_student()` still says yes — and the page it would send them to has nothing on it.
		// A held role outranks the flag: that is an explicit grant, and an account given the
		// role by hand has no flag at all.
		if ( ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT )
			&& '0' === (string) get_user_meta( $user->ID, WPCPM_Students_Sync::META_ACTIVE, true ) ) {
			return false;
		}

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_posts' ) ) {
			return false;
		}

		return (bool) WPCPM_Settings::get_value( 'student_home' );
	}

	/**
	 * Send students to their program page when they log in.
	 *
	 * @param string           $redirect_to           Intended destination.
	 * @param string           $requested_redirect_to Requested destination.
	 * @param WP_User|WP_Error $user                  The user.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! $user instanceof WP_User || ! self::should_route( $user ) ) {
			return $redirect_to;
		}

		$page = self::page_url();

		if ( '' === $page ) {
			return $redirect_to;
		}

		// A specific destination was asked for — typically because they followed a link to
		// gated content and were bounced through the login form. Honour it; overriding would
		// take them somewhere they did not ask to go.
		//
		// Asked through `is_explicit_redirect()` rather than by testing for a non-empty
		// string, which is what this used to do and which was never true of an ordinary
		// login: core's form carries a hidden `redirect_to` defaulting to `admin_url()`, so
		// the guard fired every single time and this filter never redirected anybody. What
		// actually delivered them was the `admin_init` fallback below, one hop later.
		if ( WPCPM_Request::is_explicit_redirect( $requested_redirect_to ) ) {
			return $redirect_to;
		}

		return $page;
	}

	/**
	 * Use the program page in place of the wp-admin Dashboard.
	 */
	public static function replace_admin_dashboard() {
		if ( wp_doing_ajax() || ! self::should_route() ) {
			return;
		}

		global $pagenow;

		if ( 'index.php' !== $pagenow ) {
			return;
		}

		$page = self::page_url();

		if ( '' === $page ) {
			return;
		}

		wp_safe_redirect( $page );
		exit;
	}

	/**
	 * Add a toolbar link.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar.
	 */
	public static function admin_bar_link( $admin_bar ) {
		if ( ! $admin_bar instanceof WP_Admin_Bar ) {
			return;
		}

		$is_student = self::is_student();

		if ( ! $is_student && ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$page = self::page_url();

		if ( '' === $page ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'wpcpm-my-program',
				'title' => $is_student
					? __( 'Student Report Card', 'wpcredits-program-manager' )
					: __( 'Student Dashboard', 'wpcredits-program-manager' ),
				'href'  => $page,
			)
		);
	}

	/**
	 * Create the student page if it is missing, gated to Student level.
	 *
	 * @return int Page ID, or 0.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id ) {
			$existing = get_post( $page_id );
			if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
				self::gate_page( $page_id );

				return $page_id;
			}
		}

		$existing = get_page_by_path( 'student-dashboard' );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, $existing->ID, false );
			self::gate_page( $existing->ID );

			return $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Student Report Card', 'wpcredits-program-manager' ),
				'post_name'    => 'student-dashboard',
				'post_content' => '<!-- wp:' . self::BLOCK . ' /-->',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, false );
		self::gate_page( (int) $page_id );

		return (int) $page_id;
	}

	/**
	 * Restrict the page to Student level.
	 *
	 * @param int $page_id Page ID.
	 */
	private static function gate_page( $page_id ) {
		if ( ! get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ) ) {
			update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_STUDENT );
		}
	}

	/**
	 * The page's permalink, if it is published.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}
}
