<?php
/**
 * The semester report's screen, its handlers, and the document they print.
 *
 * Kept apart from `WPCPM_Semester_Report`, which generates and stores and never learns who
 * is asking, exactly as `WPCPM_Institution_Import_Form` is kept apart from
 * `WPCPM_Institution_Import`. This file is the half that touches the request: it reads
 * `$_POST` and the query string, resolves who the reader is, asks the policy, and hands
 * everything else to that module.
 *
 * The split matters more here than it does for the import, because this module's whole
 * subject is consent. Which students may be named, whose words may be quoted and what is
 * withheld are decided in the other file against the students' own answers; nothing in
 * here can widen any of it. The handler that saves cannot touch a quote's text, the
 * handler that prints cannot see a student the live consent check has dropped, and the
 * only thing a form on this screen adds to the document is the institution's own prose.
 *
 * @package WPCreditsProgramManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Draw the semester report, edit it, and print it.
 *
 * One `render_document()` draws both surfaces. A report that read one way on the dashboard
 * and another on paper would make the screen a preview of something else, and the thing a
 * school checks before sending a report to its rector is the screen.
 */
final class WPCPM_Semester_Report_Screen {

	/**
	 * The cohort the address asks to edit: `?wpcpm_report=2026-H1` on the dashboard page.
	 *
	 * A cohort key and never a post ID. The report a reader may open is the one their own
	 * institution has for that semester, and `find()` looks it up from the institution the
	 * page has already resolved, so there is no post ID in the address to walk.
	 */
	const ARG = 'wpcpm_report';

	/** The `admin_post_` action that generates a report for one cohort. */
	const ACTION_GENERATE = 'wpcpm_report_generate';

	/** The `admin_post_` action the editing form posts to. */
	const ACTION_SAVE = 'wpcpm_report_save';

	/** The `admin_post_` action that re-reads the students' consent. */
	const ACTION_REFRESH_CONSENT = 'wpcpm_report_refresh_consent';

	/** The `admin_post_` action that marks a report final. */
	const ACTION_FINAL = 'wpcpm_report_final';

	/** The `admin_post_` action that opens a final report for editing again. */
	const ACTION_REOPEN = 'wpcpm_report_reopen';

	/** The `admin_post_` action that puts one revision back. */
	const ACTION_RESTORE = 'wpcpm_report_restore';

	/** The `admin_post_` action that asks students the two permission questions. */
	const ACTION_ASK = 'wpcpm_report_ask';

	/** The `admin_post_` **GET** action that prints the standalone document. */
	const ACTION_PRINT = 'wpcpm_report_print';

	/** Where a message about a report is left for the reader. */
	const FLASH = 'institution_report';

	/** The stylesheet handle, registered so the screen and the document share one URL. */
	const STYLE = 'wpcpm-report-print';

	/** The stylesheet, inlined into the printed document and enqueued on the screen. */
	const STYLE_FILE = 'assets/css/report-print.css';

	/** The print script's handle. */
	const SCRIPT = 'wpcpm-report-print';

	/** The print script. */
	const SCRIPT_FILE = 'assets/js/report-print.js';

	/** Prefix of the `add_option()` row that holds one institution-and-cohort generation. */
	const LOCK_PREFIX = 'wpcpm_report_gen_';

	/** Longer than a generation can take, after which a lock belonged to a dead request. */
	const LOCK_TIMEOUT = 300;

	/** Prefix of the option holding the students an `ACTION_ASK` press could not reach yet. */
	const QUEUE_PREFIX = 'wpcpm_report_ask_';

	/** The cron hook that sends the rest of an `ACTION_ASK` press. */
	const CRON_ASK = 'wpcpm_report_ask_queue';

	/** How many consent mails one press, or one drain of the queue, sends. */
	const ASK_PER_RUN = 25;

	/** Never a second consent mail to the same student inside this. */
	const ASK_AGAIN_AFTER = 30 * DAY_IN_SECONDS;

	/** User meta stamping when a student was last asked. Design spec section 9. */
	const META_ASKED = 'wpcpm_report_consent_asked';

	/** User meta holding one editor's refused save, so a race cannot eat their paragraph. */
	const META_STASH = 'wpcpm_report_stash';

	/** The mail log's label for the consent request. */
	const MAIL_CONTEXT = 'report-consent';

	/**
	 * Hooks.
	 *
	 * The style and the script are registered on `init` rather than on an enqueue hook for
	 * the reason `WPCPM_Agreement_Generate` records: the printed document is echoed from
	 * `admin-post.php`, where no enqueue pass runs, and the registration exists so that the
	 * URL the document prints and the URL the site would enqueue are one string in one place.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );

		add_action( 'admin_post_' . self::ACTION_GENERATE, array( __CLASS__, 'handle_generate' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_REFRESH_CONSENT, array( __CLASS__, 'handle_refresh_consent' ) );
		add_action( 'admin_post_' . self::ACTION_FINAL, array( __CLASS__, 'handle_final' ) );
		add_action( 'admin_post_' . self::ACTION_REOPEN, array( __CLASS__, 'handle_reopen' ) );
		add_action( 'admin_post_' . self::ACTION_RESTORE, array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_post_' . self::ACTION_ASK, array( __CLASS__, 'handle_ask' ) );
		add_action( 'admin_post_' . self::ACTION_PRINT, array( __CLASS__, 'handle_print' ) );

		add_action( self::CRON_ASK, array( __CLASS__, 'drain_ask' ) );
	}

	/**
	 * Register (but do not enqueue) the report's stylesheet and print script.
	 *
	 * The stylesheet depends on the institution dashboard's, which is where the tokens, the
	 * card rhythm and the disclosure component come from. One file dresses both surfaces:
	 * on the dashboard it inherits those tokens, and in the printed document, which has no
	 * theme and no other stylesheet, it falls back to its own values. Two files would be two
	 * chances for the screen and the paper to disagree about what the report looks like,
	 * which is exactly what a school checks before it sends one out.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			$deps = array();

			if ( class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
				WPCPM_Institutions_Dashboard::register_assets();
				$deps[] = WPCPM_Institutions_Dashboard::STYLE;
			}

			wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . self::STYLE_FILE, $deps, WPCPM_VERSION );
		}

		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script( self::SCRIPT, WPCPM_PLUGIN_URL . self::SCRIPT_FILE, array(), WPCPM_VERSION, true );
		}
	}

	/**
	 * The URL the printed document loads its script from.
	 *
	 * Built rather than read back out of the registry, for the reason
	 * `WPCPM_Agreement_Generate::script_url()` records: the document is echoed from a request
	 * that may not have reached `init` in the ordinary way, and a URL that is sometimes there
	 * and sometimes empty is worse than one that is always the same.
	 *
	 * @return string
	 */
	public static function script_url() {
		return WPCPM_PLUGIN_URL . self::SCRIPT_FILE . '?ver=' . rawurlencode( WPCPM_VERSION );
	}

	/*
	 * The screen
	 * --------------------------------------------------------------------
	 */

	/**
	 * Draw the semester report card on the institution dashboard.
	 *
	 * **Nothing is drawn at all when the data half is absent.** Phase 6 lands as two files
	 * and a checkout may hold one of them, so a missing `WPCPM_Semester_Report` leaves a gap
	 * on the page rather than a fatal on the page a partner institution was invited to. That
	 * is the same promise `WPCPM_Institutions_Dashboard::card()` makes about this class.
	 *
	 * `$context` is taken and read only for `can_manage`: every card on this page is called
	 * the same way, and a section that took a different shape would be a special case in
	 * that loop for no gain.
	 *
	 * @param string $record  Airtable Institutions record ID, already resolved by the page.
	 * @param array  $context The dashboard's context.
	 */
	public static function render( $record, array $context = array() ) {
		if ( ! class_exists( 'WPCPM_Semester_Report' ) || ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		$record = trim( (string) $record );

		// The render-from-cache pattern of design spec 5.4. The dashboard has already asked
		// whether this reader may be here at all; this asks the narrower question, because a
		// school that may read its roster is not automatically a school that may read a
		// document quoting its students.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_VIEW_SEMESTER_REPORT,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		wp_enqueue_style( self::STYLE );

		// **Read once, here, and carried down**, the way the import form reads its flash:
		// the disclosure has to know whether there is a message before the message is
		// printed, and `WPCPM_Flash::take()` empties the channel.
		$flash = WPCPM_Flash::take( self::FLASH );
		$flash = is_array( $flash ) ? $flash : array();
		$said  = ! empty( $flash['status'] ) ? (string) $flash['status'] : '';

		$cohort = self::cohort_from_request();
		$report = '' === $cohort ? null : WPCPM_Semester_Report::find( $record, $cohort );

		echo '<section class="wpcpm-institution__card wpcpm-report-card" id="wpcpm-report">';

		// **Folded by default, and open when there is something to answer.** Writing a
		// semester report is a once-a-term act and this is the longest section on the page;
		// left open it pushes the roster, the people and the agreement off the screen for
		// every visit that came to read the list. It opens by itself when the address names
		// a cohort or the last press left something to say, which are the two occasions the
		// page is asking the reader for something rather than offering.
		printf(
			'<details class="wpcpm-group wpcpm-group__disclosure wpcpm-report-card__disclosure"%s>',
			( '' !== $cohort || '' !== $said ) ? ' open' : ''
		);

		printf(
			'<summary class="wpcpm-group__summary"><span class="wpcpm-group__title">%1$s</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Semester report', 'wpcredits-program-manager' )
		);

		echo '<div class="wpcpm-group__body wpcpm-report-card__body">';

		self::render_message( $flash );

		if ( $report instanceof WP_Post ) {
			self::render_editor( $report, $record );
		} else {
			self::render_index( $record, $cohort, ! empty( $context['can_manage'] ) );
		}

		echo '</div>';
		echo '</details>';
		echo '</section>';
	}

	/**
	 * The cohort the address asks for, or an empty string when it asks for nothing usable.
	 *
	 * Public for the reason `WPCPM_Institution_Roster_View::cohort_from_request()` is: two
	 * readers of one argument are two chances to disagree about which semester is on screen.
	 *
	 * @return string A cohort key, or ''.
	 */
	public static function cohort_from_request() {
		$asked = WPCPM_Request::text( self::ARG );

		return WPCPM_Cohort::is_key( $asked ) ? $asked : '';
	}

	/**
	 * The address of one cohort's report on the dashboard page.
	 *
	 * @param string $cohort A cohort key.
	 * @return string
	 */
	public static function report_url( $cohort ) {
		$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? WPCPM_Institutions_Dashboard::page_url() : '';

		if ( '' === $page || ! WPCPM_Cohort::is_key( $cohort ) ) {
			return '';
		}

		return add_query_arg( self::ARG, (string) $cohort, $page ) . '#wpcpm-report';
	}

	/**
	 * Every semester this institution has students in, and what it has written about each.
	 *
	 * The list of cohorts is derived here rather than read off the roster view, whose own
	 * list is private: what matters is that the semesters offered are the semesters the
	 * roster shows, and both are derived from the same index rows by the same function.
	 *
	 * @param string $record     Institutions record ID.
	 * @param string $asked      The cohort the address named, when no report exists for it.
	 * @param bool   $can_manage Whether the viewer holds CAP_MANAGE.
	 */
	private static function render_index( $record, $asked, $can_manage ) {
		$may_edit = self::may_edit( $record );

		printf(
			'<p class="wpcpm-report-card__lede">%s</p>',
			esc_html__( 'A semester report is a snapshot of one term: how many students took part, what they worked on, and what they said about it. It names a student only if that student said it may.', 'wpcredits-program-manager' )
		);

		if ( '' !== $asked ) {
			printf(
				'<p class="wpcpm-report-card__empty">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: a semester, e.g. "January to June 2026". */
						__( 'There is no report for %s yet.', 'wpcredits-program-manager' ),
						WPCPM_Cohort::label( $asked )
					)
				)
			);
		}

		$cohorts = self::cohorts_of( $record );

		if ( empty( $cohorts ) ) {
			printf(
				'<p class="wpcpm-report-card__empty">%s</p>',
				esc_html__( 'There are no students on this roster yet, so there is no semester to report on.', 'wpcredits-program-manager' )
			);

			return;
		}

		echo '<ul class="wpcpm-report-card__cohorts">';

		foreach ( $cohorts as $cohort ) {
			$post = WPCPM_Semester_Report::find( $record, $cohort );

			echo '<li class="wpcpm-report-card__cohort">';
			printf( '<span class="wpcpm-report-card__cohort-name">%s</span>', esc_html( WPCPM_Cohort::label( $cohort ) ) );

			if ( $post instanceof WP_Post ) {
				$url = self::report_url( $cohort );

				printf(
					'<span class="wpcpm-report-card__state">%s</span>',
					esc_html( self::state_label( WPCPM_Semester_Report::state( $post ) ) )
				);

				if ( '' !== $url ) {
					printf(
						'<a class="wpcpm-report-card__open" href="%1$s">%2$s</a>',
						esc_url( $url ),
						esc_html__( 'Open', 'wpcredits-program-manager' )
					);
				}
			} elseif ( $may_edit ) {
				self::render_generate_form( $record, $cohort, $can_manage );
			} else {
				printf(
					'<span class="wpcpm-report-card__state">%s</span>',
					esc_html__( 'Not written yet', 'wpcredits-program-manager' )
				);
			}

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * The button that reads a semester out of the program records and writes the first draft.
	 *
	 * The switcher travels on the action URL for a manager, and only for a manager, for the
	 * reason `WPCPM_Institution_Panel::form_start()` records: the handler works out which
	 * institution it is acting for from the request, and a POST to `admin-post.php` carries
	 * the form's fields and none of the query string of the page the button was on.
	 *
	 * @param string $record     Institutions record ID.
	 * @param string $cohort     The cohort to generate.
	 * @param bool   $can_manage Whether the viewer holds CAP_MANAGE.
	 */
	private static function render_generate_form( $record, $cohort, $can_manage ) {
		$url = admin_url( 'admin-post.php' );

		if ( $can_manage ) {
			$url = add_query_arg( array( WPCPM_Institution_Roster::ARG_VIEW => $record ), $url );
		}

		printf(
			'<form class="wpcpm-report-card__generate" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( $url ),
			esc_attr__( 'Reading the program records', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION_GENERATE . '_' . $record );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_GENERATE ) );
		printf( '<input type="hidden" name="cohort" value="%s" />', esc_attr( $cohort ) );

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Write the first draft', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * One report, open for editing, with the document under the form that shapes it.
	 *
	 * The manager flag the other cards take is not among the arguments: nothing on this
	 * screen posts a switcher, because every form here names a report and each handler
	 * resolves the institution from that report's own meta.
	 *
	 * @param WP_Post $post   The report.
	 * @param string  $record Institutions record ID.
	 */
	private static function render_editor( WP_Post $post, $record ) {
		$state    = WPCPM_Semester_Report::state( $post );
		$snapshot = WPCPM_Semester_Report::snapshot( $post );
		$may_edit = self::may_edit( $record ) && 'final' !== $state;
		$page     = class_exists( 'WPCPM_Institutions_Dashboard' ) ? WPCPM_Institutions_Dashboard::page_url() : '';

		if ( '' !== $page ) {
			printf(
				'<p class="wpcpm-report-card__back"><a href="%1$s">%2$s</a></p>',
				esc_url( remove_query_arg( self::ARG, $page ) . '#wpcpm-report' ),
				esc_html__( 'Back to the other semesters', 'wpcredits-program-manager' )
			);
		}

		self::render_header( $post, $state, $snapshot );

		// **The stash is printed before the form, and its values go into the boxes.** A
		// refused save is the one moment somebody has words on this page that the site does
		// not hold, and telling them so after the boxes had already been repainted from the
		// stored copy would be telling them their paragraph is gone.
		$stash = self::stash_for( $post );

		if ( ! empty( $stash ) ) {
			printf(
				'<p class="wpcpm-report-card__stash">%s</p>',
				esc_html__( 'The words below are the ones you last sent, which were not saved because a colleague had saved this report in the meantime. Read what they wrote, above, then save again to keep yours.', 'wpcredits-program-manager' )
			);
		}

		if ( $may_edit ) {
			self::render_form( $post, $snapshot, $stash );
		} else {
			printf(
				'<p class="wpcpm-report-card__note">%s</p>',
				'final' === $state
					? esc_html__( 'This report is final. Reopen it to change anything.', 'wpcredits-program-manager' )
					: esc_html__( 'You can read this report here. Changing it is something a colleague with editing rights does.', 'wpcredits-program-manager' )
			);
		}

		self::render_actions( $post, $state, $record );
		self::render_revisions( $post );

		echo '<div class="wpcpm-report-card__preview">';
		printf( '<h3 class="wpcpm-report-card__preview-title">%s</h3>', esc_html__( 'The report as it prints', 'wpcredits-program-manager' ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_document() escapes every value it interpolates; escaping it again would print its own markup.
		echo self::render_document( $post, false );

		echo '</div>';
	}

	/**
	 * What the report is: the semester, the state, when it was generated and last edited.
	 *
	 * @param WP_Post $post     The report.
	 * @param string  $state    `draft` or `final`.
	 * @param array   $snapshot The stored snapshot.
	 */
	private static function render_header( WP_Post $post, $state, array $snapshot ) {
		$cohort    = isset( $snapshot['cohort'] ) ? (string) $snapshot['cohort'] : (string) get_post_meta( $post->ID, WPCPM_Semester_Report::META_COHORT, true );
		$generated = isset( $snapshot['generated'] ) ? (int) $snapshot['generated'] : (int) get_post_meta( $post->ID, WPCPM_Semester_Report::META_GENERATED, true );
		$format    = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		printf(
			'<h3 class="wpcpm-report-card__title">%1$s <span class="wpcpm-report-card__state">%2$s</span></h3>',
			esc_html( WPCPM_Cohort::label( $cohort ) ),
			esc_html( self::state_label( $state ) )
		);

		if ( $generated > 0 ) {
			printf(
				'<p class="wpcpm-report-card__fact">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: date and time. */
						__( 'Read from the program records on %s.', 'wpcredits-program-manager' ),
						wp_date( $format, $generated )
					)
				)
			);
		}

		$edited = get_post_modified_time( 'U', true, $post );

		if ( $edited ) {
			printf(
				'<p class="wpcpm-report-card__fact">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: date and time. */
						__( 'Last edited on %s.', 'wpcredits-program-manager' ),
						wp_date( $format, (int) $edited )
					)
				)
			);
		}
	}

	/**
	 * The editing form: one card per section, and the quote picker inside Student Feedback.
	 *
	 * **The generated part is printed above the box and is not in it.** What the program
	 * records say is not the institution's to retype, and a textarea holding a merged copy
	 * of both would make every regeneration a merge conflict somebody has to resolve by
	 * hand. The box holds the institution's own words and nothing else.
	 *
	 * @param WP_Post $post     The report.
	 * @param array   $snapshot The stored snapshot.
	 * @param array   $stash    A refused save's values, or an empty array.
	 */
	private static function render_form( WP_Post $post, array $snapshot, array $stash ) {
		$sections = WPCPM_Semester_Report::sections();
		$stored   = self::stored_sections( $post );
		$choices  = self::stored_choices( $post );
		$live     = self::live_consent( $post );

		if ( ! empty( $stash['sections'] ) && is_array( $stash['sections'] ) ) {
			$stored = array_merge( $stored, $stash['sections'] );
		}

		if ( ! empty( $stash['choices'] ) && is_array( $stash['choices'] ) ) {
			$choices = array_merge( $choices, $stash['choices'] );
		}

		self::form_start( 'wpcpm-report-card__form', self::ACTION_SAVE, self::ACTION_SAVE . '_' . $post->ID, __( 'Saving', 'wpcredits-program-manager' ), $post->ID );

		// **The stale-save fence, and the whole reason this field exists.** Decision 13 puts
		// several equal people on one institution, so two of them can be writing the same
		// paragraph at once. The value is what the post said when this page was drawn; the
		// handler compares it and refuses rather than overwriting a colleague's save.
		printf(
			'<input type="hidden" name="modified" value="%s" />',
			esc_attr( (string) $post->post_modified_gmt )
		);

		foreach ( $sections as $key => $section ) {
			self::render_section_field( $key, $section, $stored, $snapshot );

			if ( 'feedback' === $key ) {
				self::render_quote_picker( $live, $choices );
			}
		}

		printf(
			'<p class="wpcpm-report-card__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
			esc_html__( 'Save the report', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * One section's read-only summary, its box, and its hide toggle.
	 *
	 * @param string $key      Section key.
	 * @param array  $section  Its definition from `WPCPM_Semester_Report::sections()`.
	 * @param array  $stored   Stored section values.
	 * @param array  $snapshot The stored snapshot.
	 */
	private static function render_section_field( $key, array $section, array $stored, array $snapshot ) {
		$title  = isset( $section['title'] ) ? (string) $section['title'] : (string) $key;
		$id     = 'wpcpm-report-text-' . sanitize_html_class( $key );
		$value  = isset( $stored[ $key ]['text'] ) ? (string) $stored[ $key ]['text'] : self::default_text( $section );
		$hidden = ! empty( $stored[ $key ]['hidden'] );

		printf( '<fieldset class="wpcpm-report-card__section" id="wpcpm-report-field-%s">', esc_attr( sanitize_html_class( $key ) ) );
		printf( '<legend class="wpcpm-report-card__section-title">%s</legend>', esc_html( $title ) );

		if ( ! empty( $section['generated'] ) ) {
			printf(
				'<p class="wpcpm-report-card__generated">%s</p>',
				esc_html__( 'This section is read from the program records. What you write below is printed underneath it.', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<p class="wpcpm-field"><label for="%1$s">%2$s</label><textarea id="%1$s" name="%3$s" rows="5" maxlength="%4$d" class="wpcpm-report-card__text">%5$s</textarea></p>',
			esc_attr( $id ),
			esc_html__( 'What you want to say here', 'wpcredits-program-manager' ),
			esc_attr( self::text_field( $key ) ),
			(int) WPCPM_Semester_Report::MAX_TEXT,
			esc_textarea( $value )
		);

		printf(
			'<p class="wpcpm-report-card__hide"><label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label></p>',
			esc_attr( self::hidden_field( $key ) ),
			$hidden ? ' checked="checked"' : '',
			esc_html__( 'Leave this section out of the report', 'wpcredits-program-manager' )
		);

		// The one number a school asks about while it edits: how many students this section
		// would be about. Printed for the generated sections only, since the rest are prose.
		$count = self::section_count( $key, $snapshot );

		if ( $count >= 0 ) {
			printf(
				'<p class="wpcpm-report-card__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: a number of entries. */
						_n( '%s entry is generated here.', '%s entries are generated here.', $count, 'wpcredits-program-manager' ),
						number_format_i18n( $count )
					)
				)
			);
		}

		echo '</fieldset>';
	}

	/**
	 * The quote picker: include, translate, and name the author when the author allowed it.
	 *
	 * **The quote's own text is printed and is never in a box.** A school translating a
	 * student's words is adding to them; a school able to edit them is speaking for the
	 * student under the student's name. `handle_save()` reads no field that could carry the
	 * original, so there is no field here that could hold one.
	 *
	 * **Show-name is offered only when the student answered "Yes, with my name".** For every
	 * other released quote the control is absent rather than disabled, because a disabled
	 * control is a thing somebody looks for a way around.
	 *
	 * @param array $live    What `live_consent()` read.
	 * @param array $choices Stored choices.
	 */
	private static function render_quote_picker( array $live, array $choices ) {
		$quotes = $live['quotes'];

		echo '<fieldset class="wpcpm-report-card__quotes">';
		printf( '<legend class="wpcpm-report-card__section-title">%s</legend>', esc_html__( 'The quotes your students released', 'wpcredits-program-manager' ) );

		// **"Not read" is not "nobody said anything".** A school looking at an empty picker
		// after an unreachable read would conclude its students had released nothing, and
		// would go and ask them again.
		if ( empty( $live['ok'] ) ) {
			printf(
				'<p class="wpcpm-report-card__empty">%s</p>',
				esc_html__( 'The students\' answers could not be read just now, so nothing is shown here. Nothing has been changed. Try again shortly.', 'wpcredits-program-manager' )
			);

			echo '</fieldset>';

			return;
		}

		if ( empty( $quotes ) ) {
			printf(
				'<p class="wpcpm-report-card__empty">%s</p>',
				esc_html__( 'No student has released a quote for this semester. Nothing they wrote is shown here until they do.', 'wpcredits-program-manager' )
			);

			echo '</fieldset>';

			return;
		}

		foreach ( $quotes as $quote ) {
			$id = isset( $quote['id'] ) ? (string) $quote['id'] : '';

			if ( '' === $id ) {
				continue;
			}

			$choice      = isset( $choices[ $id ] ) && is_array( $choices[ $id ] ) ? $choices[ $id ] : array();
			$include     = ! array_key_exists( 'include', $choice ) || ! empty( $choice['include'] );
			$named       = ! empty( $quote['named'] );
			$show_name   = $named && ( ! array_key_exists( 'show_name', $choice ) || ! empty( $choice['show_name'] ) );
			$translation = isset( $choice['translation'] ) ? (string) $choice['translation'] : '';
			$field_id    = 'wpcpm-report-translation-' . sanitize_html_class( $id );

			echo '<div class="wpcpm-report-card__quote">';

			// The marker `submitted_values()` reads before it believes an unticked box. A
			// checkbox that is not ticked and a checkbox that was never drawn post the same
			// nothing, and only the form knows which it was.
			printf( '<input type="hidden" name="%s" value="1" />', esc_attr( self::offered_field( $id ) ) );

			printf(
				'<blockquote class="wpcpm-report-card__quote-text">%s</blockquote>',
				esc_html( isset( $quote['text'] ) ? (string) $quote['text'] : '' )
			);

			$name = isset( $quote['name'] ) ? trim( (string) $quote['name'] ) : '';

			if ( $named && '' !== $name ) {
				printf( '<p class="wpcpm-report-card__quote-name">%s</p>', esc_html( $name ) );
			}

			printf(
				'<p class="wpcpm-report-card__quote-choice"><label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label></p>',
				esc_attr( self::include_field( $id ) ),
				$include ? ' checked="checked"' : '',
				esc_html__( 'Print this quote in the report', 'wpcredits-program-manager' )
			);

			if ( $named ) {
				printf(
					'<p class="wpcpm-report-card__quote-choice"><label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label></p>',
					esc_attr( self::name_field( $id ) ),
					$show_name ? ' checked="checked"' : '',
					esc_html__( 'Print their name with it, as they agreed', 'wpcredits-program-manager' )
				);
			}

			printf(
				'<p class="wpcpm-field"><label for="%1$s">%2$s</label><textarea id="%1$s" name="%3$s" rows="3" maxlength="%4$d" class="wpcpm-report-card__text">%5$s</textarea><span class="wpcpm-field__hint">%6$s</span></p>',
				esc_attr( $field_id ),
				esc_html__( 'English translation, if the quote is in another language', 'wpcredits-program-manager' ),
				esc_attr( self::translation_field( $id ) ),
				(int) WPCPM_Semester_Report::MAX_TEXT,
				esc_textarea( $translation ),
				esc_html__( 'The original is always printed first. Translate it yourself; nothing here is machine translated.', 'wpcredits-program-manager' )
			);

			echo '</div>';
		}

		echo '</fieldset>';
	}

	/**
	 * The four buttons beside the form: print, re-read consent, final, reopen, ask.
	 *
	 * @param WP_Post $post   The report.
	 * @param string  $state  `draft` or `final`.
	 * @param string  $record Institutions record ID.
	 */
	private static function render_actions( WP_Post $post, $state, $record ) {
		$may_edit = self::may_edit( $record );

		echo '<div class="wpcpm-report-card__actions">';

		printf(
			'<p class="wpcpm-report-card__action"><a class="wpcpm-button" href="%1$s">%2$s</a></p>',
			esc_url( self::print_url( $post->ID ) ),
			esc_html__( 'Print this report', 'wpcredits-program-manager' )
		);

		if ( ! $may_edit ) {
			echo '</div>';

			return;
		}

		// **Allowed on a final report, deliberately.** A student who withdraws their consent
		// has to be able to reach a document that has already been marked done, or "final"
		// would be a way of freezing an answer somebody has since changed.
		self::render_button_form(
			self::ACTION_REFRESH_CONSENT,
			$post->ID,
			__( 'Checking with the program records', 'wpcredits-program-manager' ),
			__( 'Check the students\' answers again', 'wpcredits-program-manager' )
		);

		if ( 'final' === $state ) {
			self::render_button_form(
				self::ACTION_REOPEN,
				$post->ID,
				__( 'Reopening', 'wpcredits-program-manager' ),
				__( 'Reopen for editing', 'wpcredits-program-manager' )
			);
		} else {
			self::render_button_form(
				self::ACTION_FINAL,
				$post->ID,
				__( 'Marking final', 'wpcredits-program-manager' ),
				__( 'Mark this report final', 'wpcredits-program-manager' )
			);
		}

		// **No ask control here, by decision.** The document says how many students have not
		// answered and why; the send itself is a program manager's, because the institution is
		// the party that benefits from a yes and must not be the party that asks for it. The
		// sentence is drawn rather than the button so that a school reading a short list knows
		// the count is answerable and who to ask, instead of reading it as a dead end.
		printf(
			'<p class="wpcpm-report-card__note">%s</p>',
			esc_html__( 'Students who have not answered yet can be sent a request to answer. A program manager sends it, and nobody is asked twice in thirty days; once they have answered, check the students\' answers again to bring the new ones in.', 'wpcredits-program-manager' )
		);

		echo '</div>';
	}

	/**
	 * A one-button form posting to `admin-post.php` with a nonce keyed to the report.
	 *
	 * @param string $action The `admin_post_` action.
	 * @param int    $post_id The report.
	 * @param string $busy   What the pressed control says while the request is in flight.
	 * @param string $label  The button.
	 */
	private static function render_button_form( $action, $post_id, $busy, $label ) {
		printf(
			'<form class="wpcpm-report-card__action" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $busy )
		);

		wp_nonce_field( $action . '_' . (int) $post_id );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="report" value="%d" />', (int) $post_id );
		printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html( $label ) );

		echo '</form>';
	}

	/**
	 * The consent-request button, for the manager screen to draw.
	 *
	 * The one control on a report that lives outside the dashboard. It is here rather than in
	 * `WPCPM_Institutions` so that the action name and the nonce it is keyed to are written
	 * once, beside the handler that checks them: a button whose nonce string drifts from its
	 * handler's fails as a refusal a reader cannot explain.
	 *
	 * Drawing it is not the permission check. `handle_ask()` requires the management
	 * capability whatever this does, and the manager screen is behind that capability already.
	 *
	 * @param int $post_id The report.
	 */
	public static function render_ask_form( $post_id ) {
		self::render_button_form(
			self::ACTION_ASK,
			$post_id,
			__( 'Sending', 'wpcredits-program-manager' ),
			__( 'Ask the students', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The saved versions of this report, each with a way back to it.
	 *
	 * Kept folded: it is a list nobody reads until something has gone wrong, and it is the
	 * longest thing on an already long section.
	 *
	 * @param WP_Post $post The report.
	 */
	private static function render_revisions( WP_Post $post ) {
		$revisions = wp_get_post_revisions( $post->ID, array( 'posts_per_page' => 20 ) );

		if ( empty( $revisions ) ) {
			return;
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		echo '<details class="wpcpm-report-card__revisions">';
		printf( '<summary>%s</summary>', esc_html__( 'Earlier versions of this report', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-report-card__revision-list">';

		foreach ( $revisions as $revision ) {
			$author = get_userdata( (int) $revision->post_author );

			echo '<li class="wpcpm-report-card__revision">';

			printf(
				'<span class="wpcpm-report-card__revision-when">%s</span>',
				esc_html(
					sprintf(
						/* translators: 1: date and time, 2: the person who saved it. */
						__( '%1$s by %2$s', 'wpcredits-program-manager' ),
						wp_date( $format, (int) get_post_time( 'U', true, $revision ) ),
						$author instanceof WP_User ? $author->display_name : __( 'somebody who has left', 'wpcredits-program-manager' )
					)
				)
			);

			printf(
				'<form class="wpcpm-report-card__restore" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Putting it back', 'wpcredits-program-manager' )
			);

			wp_nonce_field( self::ACTION_RESTORE . '_' . (int) $revision->ID );

			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_RESTORE ) );
			printf( '<input type="hidden" name="revision" value="%d" />', (int) $revision->ID );
			printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Put this version back', 'wpcredits-program-manager' ) );

			echo '</form>';
			echo '</li>';
		}

		echo '</ul>';
		echo '</details>';
	}

	/**
	 * Open a form posting to `admin-post.php`, with its nonce and its action.
	 *
	 * @param string $css          Class attribute for the form.
	 * @param string $action       The `admin_post_` action name.
	 * @param string $nonce_action The nonce action, keyed to the report.
	 * @param string $busy         What the pressed control says while the request is in flight.
	 * @param int    $post_id      The report the form acts on.
	 */
	private static function form_start( $css, $action, $nonce_action, $busy, $post_id ) {
		// **No switcher travels on a report form**, unlike the agreement panel's forms. Every
		// handler these post to resolves the institution from the report post they name, which
		// is the rule for every post-keyed route in this module, so a manager acting on behalf
		// needs nothing on the action URL and a hidden record would be a second answer waiting
		// to disagree with the post's own meta.
		printf(
			'<form class="%1$s" method="post" action="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_attr( $css ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $busy )
		);

		wp_nonce_field( $nonce_action );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="report" value="%d" />', (int) $post_id );
	}

	/*
	 * The document, screen and paper alike
	 * --------------------------------------------------------------------
	 */

	/**
	 * The report itself. One function, two surfaces.
	 *
	 * **Consent is re-read here, on every single render.** The snapshot says who agreed on
	 * the day it was generated; `consent_check()` says who agrees now. A student who changes
	 * their answer to No disappears from this drawing and from the next print without
	 * anybody regenerating anything, which is the promise `13-student-feedback.md` makes on
	 * the site's behalf. The snapshot is still what supplies the numbers, the teams and the
	 * events, because those are facts about a term rather than statements about a person.
	 *
	 * **Every link prints its URL as visible text.** A printed report is read on paper by
	 * somebody who cannot click, and a report whose links say "here" is a report whose links
	 * are gone the moment it leaves the screen.
	 *
	 * @param WP_Post $post      The report.
	 * @param bool    $for_print Whether this is the standalone printed document.
	 * @return string HTML. Every interpolated value is escaped.
	 */
	public static function render_document( WP_Post $post, $for_print ) {
		$snapshot = WPCPM_Semester_Report::snapshot( $post );
		$live     = self::live_consent( $post );
		$sections = WPCPM_Semester_Report::sections();
		$stored   = self::stored_sections( $post );
		$choices  = self::stored_choices( $post );

		$students = $live['students'];
		$quotes   = $live['quotes'];
		$dropped  = $live['dropped'];

		$out  = '<article class="wpcpm-report-doc">';
		$out .= sprintf( '<h1 class="wpcpm-report-doc__title">%s</h1>', esc_html( get_the_title( $post ) ) );

		$cohort = isset( $snapshot['cohort'] ) ? (string) $snapshot['cohort'] : '';

		if ( '' !== $cohort ) {
			$out .= sprintf( '<p class="wpcpm-report-doc__cohort">%s</p>', esc_html( WPCPM_Cohort::label( $cohort ) ) );
		}

		// **A consent read that failed stops the whole document**, for the reason a failed
		// read stops a whole generation: a report showing Participation and Contribution
		// Teams with an empty Student Projects and no quotes under Student Feedback looks
		// finished, and a school would print it. Nothing about the term is worth more than
		// the risk of a sheet that quietly leaves out every student who said yes.
		if ( empty( $live['ok'] ) ) {
			$out .= sprintf(
				'<p class="wpcpm-report-doc__unreadable">%s</p>',
				esc_html__( 'The students\' answers could not be read just now, and this report is not shown without them. Nothing has been changed. Try again shortly.', 'wpcredits-program-manager' )
			);

			return $out . '</article>';
		}

		// **The withdrawal line is on the screen and not on the paper.** It is a message to
		// the person editing, telling them why a quote they remember is missing. On a sheet
		// handed to a rector it would say something about a student that the student's
		// withdrawal was meant to stop this document saying.
		if ( ! $for_print && $dropped > 0 ) {
			$out .= sprintf(
				'<p class="wpcpm-report-doc__withdrawn">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: a number of quotes. */
						_n(
							'%s quote has been withdrawn by its author since this draft was generated, and is not shown.',
							'%s quotes have been withdrawn by their authors since this draft was generated, and are not shown.',
							$dropped,
							'wpcredits-program-manager'
						),
						number_format_i18n( $dropped )
					)
				)
			);
		}

		foreach ( $sections as $key => $section ) {
			if ( ! empty( $stored[ $key ]['hidden'] ) ) {
				continue;
			}

			$body = self::section_body( $key, $section, $snapshot, $students, $quotes, $choices );
			$text = isset( $stored[ $key ]['text'] ) ? (string) $stored[ $key ]['text'] : self::default_text( $section );
			$text = self::paragraphs( $text );

			if ( '' === $body && '' === $text ) {
				continue;
			}

			$out .= sprintf(
				'<section class="wpcpm-report-doc__section wpcpm-report-doc__section--%s">',
				esc_attr( sanitize_html_class( $key ) )
			);

			$out .= sprintf(
				'<h2 class="wpcpm-report-doc__heading">%s</h2>',
				esc_html( isset( $section['title'] ) ? (string) $section['title'] : (string) $key )
			);

			$out .= $text;
			$out .= $body;
			$out .= '</section>';
		}

		$out .= '</article>';

		return $out;
	}

	/**
	 * What the students say **now**, normalised, with a flag saying whether it was read.
	 *
	 * `consent_check()` reads the live Feedback rows, so it is the one call on this screen
	 * that can fail for a reason that has nothing to do with the reader. Its answer is
	 * shaped here, once, so that every surface tells the two failures apart: "nobody has
	 * released anything" and "the answers could not be read" look identical in an empty
	 * array and mean opposite things to the person looking at the page.
	 *
	 * @param WP_Post $post The report.
	 * @return array{ok: bool, students: array, quotes: array, dropped: int}
	 */
	private static function live_consent( WP_Post $post ) {
		$live = WPCPM_Semester_Report::consent_check( $post );
		$ok   = is_array( $live ) && isset( $live['students'] ) && is_array( $live['students'] ) && isset( $live['quotes'] ) && is_array( $live['quotes'] );

		return array(
			'ok'       => $ok,
			'students' => $ok ? $live['students'] : array(),
			'quotes'   => $ok ? $live['quotes'] : array(),
			'dropped'  => ( $ok && isset( $live['dropped'] ) ) ? (int) $live['dropped'] : 0,
		);
	}

	/**
	 * The generated half of one section, or an empty string for a section that has none.
	 *
	 * @param string $key      Section key.
	 * @param array  $section  Its definition.
	 * @param array  $snapshot The stored snapshot.
	 * @param array  $students The live consenting students.
	 * @param array  $quotes   The live released quotes.
	 * @param array  $choices  Stored per-quote choices.
	 * @return string
	 */
	private static function section_body( $key, array $section, array $snapshot, array $students, array $quotes, array $choices ) {
		if ( empty( $section['generated'] ) ) {
			return '';
		}

		if ( 'participation' === $key ) {
			return self::participation_body( $snapshot );
		}

		if ( 'teams' === $key ) {
			return self::teams_body( $snapshot );
		}

		if ( 'projects' === $key ) {
			return self::projects_body( $students, $snapshot );
		}

		if ( 'recognition' === $key ) {
			return self::events_body( $students );
		}

		if ( 'feedback' === $key ) {
			return self::quotes_body( $quotes, $choices );
		}

		return '';
	}

	/**
	 * The participation table, and the semester before it.
	 *
	 * The previous cohort contributes two numbers and only two, because that is what the
	 * index can answer for a semester nobody has generated a report for. `has_rows` false
	 * says the site holds nothing for it, which is a different sentence from two zeroes.
	 *
	 * @param array $snapshot The stored snapshot.
	 * @return string
	 */
	private static function participation_body( array $snapshot ) {
		$counts = ( isset( $snapshot['participation'] ) && is_array( $snapshot['participation'] ) ) ? $snapshot['participation'] : array();

		if ( empty( $counts ) ) {
			return '';
		}

		$labels = array(
			'signed_up'   => __( 'Students on the program', 'wpcredits-program-manager' ),
			'active'      => __( 'Taking part now', 'wpcredits-program-manager' ),
			'graduated'   => __( 'Graduated', 'wpcredits-program-manager' ),
			'pending'     => __( 'Waiting to graduate', 'wpcredits-program-manager' ),
			'withdrawn'   => __( 'Withdrew', 'wpcredits-program-manager' ),
			'not_started' => __( 'Did not start', 'wpcredits-program-manager' ),
			'other'       => __( 'Other', 'wpcredits-program-manager' ),
		);

		$out = '<table class="wpcpm-report-doc__table"><tbody>';

		foreach ( $labels as $name => $label ) {
			// `other` exists so a status the base grows shows up as a number rather than
			// vanishing; printing a row of zero for it on every report would be noise.
			if ( 'other' === $name && empty( $counts[ $name ] ) ) {
				continue;
			}

			$out .= sprintf(
				'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
				esc_html( $label ),
				esc_html( number_format_i18n( isset( $counts[ $name ] ) ? (int) $counts[ $name ] : 0 ) )
			);
		}

		$out .= '</tbody></table>';

		$previous = ( isset( $snapshot['previous'] ) && is_array( $snapshot['previous'] ) ) ? $snapshot['previous'] : array();
		$key      = isset( $previous['key'] ) ? (string) $previous['key'] : '';

		if ( '' !== $key ) {
			$out .= sprintf(
				'<p class="wpcpm-report-doc__note">%s</p>',
				esc_html(
					empty( $previous['has_rows'] )
						? sprintf(
							/* translators: %s: a semester, e.g. "July to December 2025". */
							__( 'There are no records here for %s, so there is nothing to compare this term against.', 'wpcredits-program-manager' ),
							WPCPM_Cohort::label( $key )
						)
						: sprintf(
							/* translators: 1: a semester, 2: students who signed up then, 3: students who graduated then. */
							__( 'In %1$s, %2$s students took part and %3$s graduated.', 'wpcredits-program-manager' ),
							WPCPM_Cohort::label( $key ),
							number_format_i18n( isset( $previous['signed_up'] ) ? (int) $previous['signed_up'] : 0 ),
							number_format_i18n( isset( $previous['graduated'] ) ? (int) $previous['graduated'] : 0 )
						)
				)
			);
		}

		$out .= self::read_line( $snapshot );

		return $out;
	}

	/**
	 * The contribution teams and how many students worked on each.
	 *
	 * @param array $snapshot The stored snapshot.
	 * @return string
	 */
	private static function teams_body( array $snapshot ) {
		$teams = ( isset( $snapshot['teams'] ) && is_array( $snapshot['teams'] ) ) ? $snapshot['teams'] : array();

		if ( empty( $teams ) ) {
			return '';
		}

		$out = '<ul class="wpcpm-report-doc__teams">';

		foreach ( $teams as $team ) {
			$out .= sprintf(
				'<li class="wpcpm-report-doc__team">%1$s <span class="wpcpm-report-doc__count">%2$s</span></li>',
				esc_html( isset( $team['team'] ) ? (string) $team['team'] : '' ),
				esc_html( number_format_i18n( isset( $team['count'] ) ? (int) $team['count'] : 0 ) )
			);
		}

		$out .= '</ul>';

		return $out;
	}

	/**
	 * One row per consenting student: the name or the blog host they chose, and their links.
	 *
	 * `display` is already whichever of the two that student's own answer asked for, so
	 * nothing here decides between them: this file has no business knowing which it is.
	 *
	 * @param array $students The live consenting students.
	 * @param array $snapshot The stored snapshot, for the withheld line.
	 * @return string
	 */
	private static function projects_body( array $students, array $snapshot ) {
		$out = '';

		if ( ! empty( $students ) ) {
			$out .= '<ul class="wpcpm-report-doc__students">';

			foreach ( $students as $student ) {
				$out .= '<li class="wpcpm-report-doc__student">';
				$out .= sprintf(
					'<p class="wpcpm-report-doc__student-name">%s</p>',
					esc_html( isset( $student['display'] ) ? (string) $student['display'] : '' )
				);

				$website = isset( $student['website'] ) ? (string) $student['website'] : '';

				if ( '' !== $website ) {
					$out .= self::link_line( __( 'Website', 'wpcredits-program-manager' ), $website );
				}

				$links = ( isset( $student['links'] ) && is_array( $student['links'] ) ) ? $student['links'] : array();

				foreach ( $links as $link ) {
					$url = isset( $link['url'] ) ? (string) $link['url'] : '';

					if ( '' === $url ) {
						continue;
					}

					$out .= self::link_line( isset( $link['label'] ) ? (string) $link['label'] : '', $url );
				}

				$out .= '</li>';
			}

			$out .= '</ul>';
		}

		$out .= self::withheld_line( $snapshot );

		return $out;
	}

	/**
	 * The events students took part in, identical addresses grouped with their count.
	 *
	 * **Regrouped from the students who consent now, not read off the snapshot.** Section 5
	 * is "per consenting student" in the same sense section 4 is, and the stored `events`
	 * list is a set of totals: a student who has since withdrawn cannot be subtracted from a
	 * number, only left out of the sum. Each snapshot student carries their own URLs for
	 * exactly this, so the grouping is done again over whoever `consent_check()` returned.
	 * Without it a withdrawal would drop somebody's name from Student Projects and go on
	 * counting them here, which is the withdrawal not being honoured.
	 *
	 * @param array $students The live consenting students.
	 * @return string
	 */
	private static function events_body( array $students ) {
		$events = WPCPM_Semester_Report::group_events( $students );

		if ( empty( $events ) ) {
			return '';
		}

		$out = '<ul class="wpcpm-report-doc__events">';

		foreach ( $events as $event ) {
			$url = isset( $event['url'] ) ? (string) $event['url'] : '';

			if ( '' === $url ) {
				continue;
			}

			$count = isset( $event['count'] ) ? (int) $event['count'] : 0;

			$out .= '<li class="wpcpm-report-doc__event">';
			$out .= sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $url ) );

			if ( $count > 1 ) {
				$out .= sprintf(
					' <span class="wpcpm-report-doc__count">%s</span>',
					esc_html(
						sprintf(
							/* translators: %s: a number of students. */
							_n( '%s student', '%s students', $count, 'wpcredits-program-manager' ),
							number_format_i18n( $count )
						)
					)
				);
			}

			$out .= '</li>';
		}

		$out .= '</ul>';

		return $out;
	}

	/**
	 * The released quotes, the original first and the institution's translation under it.
	 *
	 * A missing choice counts as included and, for a student who said "Yes, with my name",
	 * as named: both are the answer the student gave, and a freshly generated report that
	 * silently dropped every quote until somebody ticked eight boxes would read as a report
	 * whose students said nothing.
	 *
	 * @param array $quotes  The live released quotes.
	 * @param array $choices Stored per-quote choices.
	 * @return string
	 */
	private static function quotes_body( array $quotes, array $choices ) {
		$out = '';

		foreach ( $quotes as $quote ) {
			$id = isset( $quote['id'] ) ? (string) $quote['id'] : '';

			if ( '' === $id ) {
				continue;
			}

			$choice = isset( $choices[ $id ] ) && is_array( $choices[ $id ] ) ? $choices[ $id ] : array();

			if ( array_key_exists( 'include', $choice ) && empty( $choice['include'] ) ) {
				continue;
			}

			$out .= '<figure class="wpcpm-report-doc__quote">';
			$out .= sprintf(
				'<blockquote class="wpcpm-report-doc__quote-text">%s</blockquote>',
				esc_html( isset( $quote['text'] ) ? (string) $quote['text'] : '' )
			);

			$translation = isset( $choice['translation'] ) ? trim( (string) $choice['translation'] ) : '';

			if ( '' !== $translation ) {
				$out .= sprintf(
					'<p class="wpcpm-report-doc__translation"><span class="wpcpm-report-doc__translation-label">%1$s</span> %2$s</p>',
					esc_html__( 'English:', 'wpcredits-program-manager' ),
					esc_html( $translation )
				);
			}

			// The name is printed only when the student said "Yes, with my name" **and** the
			// institution left that choice alone. Two conditions, and the student's is first:
			// a stored `show_name` on a quote whose author has since changed their answer
			// cannot put the name back, because `named` is read live.
			$named = ! empty( $quote['named'] ) && ( ! array_key_exists( 'show_name', $choice ) || ! empty( $choice['show_name'] ) );
			$name  = isset( $quote['name'] ) ? trim( (string) $quote['name'] ) : '';

			if ( $named && '' !== $name ) {
				$out .= sprintf( '<figcaption class="wpcpm-report-doc__quote-name">%s</figcaption>', esc_html( $name ) );
			}

			$out .= '</figure>';
		}

		return $out;
	}

	/**
	 * One labelled link whose visible text is its own address.
	 *
	 * @param string $label What the link is.
	 * @param string $url   Where it goes.
	 * @return string
	 */
	private static function link_line( $label, $url ) {
		$label = trim( (string) $label );

		return sprintf(
			'<p class="wpcpm-report-doc__link">%1$s<a href="%2$s">%3$s</a></p>',
			'' === $label ? '' : sprintf( '<span class="wpcpm-report-doc__link-label">%s</span> ', esc_html( $label ) ),
			esc_url( $url ),
			esc_html( $url )
		);
	}

	/**
	 * The line that says how many students are not on the list, and why.
	 *
	 * Three counts and no names. A school reading "two students have not answered" can go
	 * and ask them; a school reading nothing would think the term had eleven students in it.
	 *
	 * @param array $snapshot The stored snapshot.
	 * @return string
	 */
	private static function withheld_line( array $snapshot ) {
		$withheld = ( isset( $snapshot['withheld'] ) && is_array( $snapshot['withheld'] ) ) ? $snapshot['withheld'] : array();

		$no_answer = isset( $withheld['no_answer'] ) ? (int) $withheld['no_answer'] : 0;
		$declined  = isset( $withheld['declined'] ) ? (int) $withheld['declined'] : 0;
		$ambiguous = isset( $withheld['ambiguous'] ) ? (int) $withheld['ambiguous'] : 0;

		if ( $no_answer + $declined + $ambiguous < 1 ) {
			return '';
		}

		$parts = array();
		$lines = array();

		if ( $no_answer > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: a number of students. */
				_n( '%s has not answered yet', '%s have not answered yet', $no_answer, 'wpcredits-program-manager' ),
				number_format_i18n( $no_answer )
			);
		}

		if ( $declined > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: a number of students. */
				_n( '%s asked not to be listed', '%s asked not to be listed', $declined, 'wpcredits-program-manager' ),
				number_format_i18n( $declined )
			);
		}

		if ( ! empty( $parts ) ) {
			$lines[] = sprintf(
				/* translators: %s: a list of reasons, e.g. "2 have not answered yet, 1 asked not to be listed". */
				__( 'Students not listed above: %s.', 'wpcredits-program-manager' ),
				implode( __( ', ', 'wpcredits-program-manager' ), $parts )
			);
		}

		// Its own sentence, not a third item in the list above. An unmatched record is about a
		// student's project links, not about their being listed: a student who released their
		// name is printed above with no links, so folding this number into "not listed above"
		// named somebody in two places at once and made the numbers overrun the cohort.
		if ( $ambiguous > 0 ) {
			$lines[] = sprintf(
				/* translators: %s: a number of students. */
				_n(
					'The program records of %s student could not be matched to a single row, so no project links are shown for them.',
					'The program records of %s students could not be matched to a single row, so no project links are shown for them.',
					$ambiguous,
					'wpcredits-program-manager'
				),
				number_format_i18n( $ambiguous )
			);
		}

		return sprintf(
			'<p class="wpcpm-report-doc__withheld">%s</p>',
			esc_html( implode( ' ', $lines ) )
		);
	}

	/**
	 * When the program records this report was built from were read.
	 *
	 * The index's read time and never the generation's: the whole point of a per-institution
	 * index is that every surface says how old the rows on it are, and a report generated at
	 * noon from an index read at dawn was built on dawn's numbers.
	 *
	 * @param array $snapshot The stored snapshot.
	 * @return string
	 */
	private static function read_line( array $snapshot ) {
		$read = isset( $snapshot['read'] ) ? (int) $snapshot['read'] : 0;

		if ( $read <= 0 ) {
			return '';
		}

		return sprintf(
			'<p class="wpcpm-report-doc__read">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: a date. */
					__( 'These numbers were read from the program records on %s.', 'wpcredits-program-manager' ),
					wp_date( get_option( 'date_format' ), $read )
				)
			)
		);
	}

	/**
	 * An institution's own prose, as paragraphs.
	 *
	 * `wpautop()` would be the obvious call and is the wrong one: it accepts markup, and this
	 * text arrives from a textarea a school types into. Splitting on blank lines and escaping
	 * each paragraph means the worst a paste of HTML can do is print itself.
	 *
	 * @param string $text What the institution wrote.
	 * @return string
	 */
	private static function paragraphs( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return '';
		}

		$out = '';

		foreach ( preg_split( '/\R{2,}/', $text ) as $chunk ) {
			$chunk = trim( (string) $chunk );

			if ( '' === $chunk ) {
				continue;
			}

			$out .= sprintf( '<p class="wpcpm-report-doc__p">%s</p>', nl2br( esc_html( $chunk ) ) );
		}

		return $out;
	}

	/*
	 * The handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Read one semester out of the program records and write the first draft.
	 *
	 * **The order is the module's rule.** The institution is resolved from the reader's own
	 * stamp or from a manager's switcher and never from the form; the cheap decision refuses
	 * before anything else happens; the nonce is checked before a single request reaches
	 * Airtable; and the lock is taken before the reads begin. A cross-site post therefore
	 * costs this site nothing at all.
	 *
	 * The lock is keyed to the institution **and the cohort**, so two people generating two
	 * different semesters do not queue behind one another, while two presses of the same
	 * button produce one document.
	 */
	public static function handle_generate() {
		if ( ! class_exists( 'WPCPM_Semester_Report' ) ) {
			self::bounce( 'unavailable' );
		}

		$institution = WPCPM_Institution_Roster::resolve_institution(
			wp_get_current_user(),
			current_user_can( WPCPM_Roles::CAP_MANAGE )
		);

		if ( '' === $institution ) {
			self::bounce( 'refused' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_SEMESTER_REPORT,
			WPCPM_Institution_Policy::subject_institution( $institution )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( self::ACTION_GENERATE . '_' . $institution );

		$cohort = WPCPM_Request::posted_text( 'cohort' );

		// `is_key()` admits NONE, and NONE is not a semester: it is the students whose start
		// date the base does not hold. A report about them would be a report nobody can name,
		// so the picker never offers it and the handler refuses it as well.
		if ( ! WPCPM_Cohort::is_key( $cohort ) || WPCPM_Cohort::NONE === $cohort ) {
			self::bounce( 'bad-cohort' );
		}

		if ( WPCPM_Semester_Report::find( $institution, $cohort ) instanceof WP_Post ) {
			self::bounce( 'already-generated', array(), $cohort );
		}

		if ( ! self::lock( $institution, $cohort ) ) {
			self::bounce( 'locked', array(), $cohort );
		}

		$generated = WPCPM_Semester_Report::generate( $institution, $cohort );

		self::unlock( $institution, $cohort );

		// **The message goes back verbatim.** A generation that could not read the Projects
		// table is not a report with an empty Projects section: it is no report at all, and
		// the reader has to be told which half of the world was unreachable so they know
		// whether to try again or to ask somebody.
		if ( is_wp_error( $generated ) ) {
			self::bounce( 'generate-failed', array( 'why' => $generated->get_error_message() ), $cohort );
		}

		self::leave( 'generated', array(), $cohort );
	}

	/**
	 * Save the institution's own words, its hide toggles and its quote choices.
	 *
	 * **The order is the module's rule.** The report is read first because everything else
	 * is derived from it: the institution comes off the post's own meta and never off the
	 * form, so a member of one school posting another school's report ID is decided against
	 * the school that report belongs to and gets the one refusal. The decision is cheap and
	 * is made before the nonce, and the nonce is keyed to the report.
	 *
	 * **Only what is in the snapshot is read.** The section keys come from
	 * `WPCPM_Semester_Report::sections()` and the quote ids from the stored snapshot, so the
	 * field names this handler looks for are the site's own. There is no field name that
	 * could carry a quote's text, which is what makes "the original is never editable here"
	 * a property of the code rather than a promise about the form.
	 */
	public static function handle_save() {
		$post = self::posted_report();

		// The same word for a report that is not here and one that is not this reader's: the
		// difference between the two is exactly the fact the fence keeps, and post IDs count
		// upwards, so a handler that said "no longer here" for a stranger's guess and "not
		// something you can do" for a real one would let any member count every other
		// school's reports. `handle_print()` makes the same choice with `unknown()`.
		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'refused' );
		}

		$decision = self::decide_edit( $post );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( self::ACTION_SAVE . '_' . $post->ID );

		if ( 'final' === WPCPM_Semester_Report::state( $post ) ) {
			self::bounce( 'is-final', array(), self::cohort_of( $post ) );
		}

		$values = self::submitted_values( $post );

		// **The stale-save fence.** Decision 13 puts several equal people on one institution.
		// The submitted text is stashed before the refusal, because losing a colleague's
		// paragraph to a race is the failure this whole comparison exists to prevent: the
		// reader gets their own words back in the boxes, above the version that won.
		if ( WPCPM_Request::posted_text( 'modified' ) !== (string) $post->post_modified_gmt ) {
			self::stash( $post, $values );
			self::bounce( 'stale', array(), self::cohort_of( $post ) );
		}

		// **The meta is written before the post, and the order is load-bearing.** WordPress
		// takes the revision from inside `wp_update_post()`, copying the post and the meta
		// registered with `revisions_enabled` as they stand at that moment. Written the other
		// way round, every revision would hold the previous save's sections and choices, and
		// a restore would put back a version nobody ever saw.
		update_post_meta( $post->ID, WPCPM_Semester_Report::META_SECTIONS, $values['sections'] );
		update_post_meta( $post->ID, WPCPM_Semester_Report::META_CHOICES, $values['choices'] );

		// `post_content` holds a plain-text rendering of the narrative, for one reason: it is
		// what the revision diff screen compares. Meta registered with `revisions_enabled` is
		// restored with a revision but is not shown in the diff, so a report whose content
		// never changed would offer a list of versions that all looked identical.
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => self::narrative_text( $values['sections'] ),
			)
		);

		self::clear_stash();
		self::leave( 'saved', array(), self::cohort_of( $post ) );
	}

	/**
	 * Re-read the students' answers into a stored report.
	 *
	 * **Allowed on a final report.** Every other write is refused once a report is marked
	 * done, and this one is not, because a student withdrawing their consent must be able to
	 * reach a document that has already been finished. "Final" is the institution saying it
	 * has stopped writing, not the students losing the ability to change their minds.
	 */
	public static function handle_refresh_consent() {
		$post = self::posted_report();

		// The same word for a report that is not here and one that is not this reader's: the
		// difference between the two is exactly the fact the fence keeps, and post IDs count
		// upwards, so a handler that said "no longer here" for a stranger's guess and "not
		// something you can do" for a real one would let any member count every other
		// school's reports. `handle_print()` makes the same choice with `unknown()`.
		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'refused' );
		}

		$decision = self::decide_edit( $post );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( self::ACTION_REFRESH_CONSENT . '_' . $post->ID );

		$refreshed = WPCPM_Semester_Report::refresh_consent( $post );

		if ( is_wp_error( $refreshed ) ) {
			self::bounce( 'consent-failed', array( 'why' => $refreshed->get_error_message() ), self::cohort_of( $post ) );
		}

		self::leave( 'consent-refreshed', array(), self::cohort_of( $post ) );
	}

	/**
	 * Mark a report final.
	 */
	public static function handle_final() {
		self::change_state( self::ACTION_FINAL, 'final', 'marked-final' );
	}

	/**
	 * Open a final report for editing again.
	 */
	public static function handle_reopen() {
		self::change_state( self::ACTION_REOPEN, 'draft', 'reopened' );
	}

	/**
	 * The two state buttons, which differ in one word each.
	 *
	 * Written once because two copies of four checks is two chances for one of them to lose
	 * the fence, and because the order of those checks is the thing that has to be identical.
	 *
	 * @param string $action The `admin_post_` action, for the nonce.
	 * @param string $state  The state to write.
	 * @param string $said   What to tell the reader afterwards.
	 */
	private static function change_state( $action, $state, $said ) {
		$post = self::posted_report();

		// The same word for a report that is not here and one that is not this reader's: the
		// difference between the two is exactly the fact the fence keeps, and post IDs count
		// upwards, so a handler that said "no longer here" for a stranger's guess and "not
		// something you can do" for a real one would let any member count every other
		// school's reports. `handle_print()` makes the same choice with `unknown()`.
		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'refused' );
		}

		$decision = self::decide_edit( $post );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( $action . '_' . $post->ID );

		update_post_meta( $post->ID, WPCPM_Semester_Report::META_STATE, $state );

		self::leave( $said, array(), self::cohort_of( $post ) );
	}

	/**
	 * Put one earlier version of a report back.
	 *
	 * **The revision's `post_parent` is read first, before anything else.** A revision is a
	 * post of its own, and the only thing that ties it to an institution is the post it is a
	 * revision of. Deciding against the revision, or against a report ID the form also
	 * carried, would let somebody restore one institution's version into another's document
	 * by posting two IDs that do not belong together. There is one ID in this form and the
	 * parent is derived from it.
	 */
	public static function handle_restore() {
		$revision_id = self::posted_revision();
		$revision    = $revision_id > 0 ? wp_get_post_revision( $revision_id ) : null;

		// `refused` for a missing revision, a revision of something else and a stranger's
		// report alike, for the reason the other handlers give: revision IDs count upwards too.
		if ( ! $revision instanceof WP_Post ) {
			self::bounce( 'refused' );
		}

		// **The parent first, and taken from the revision, never from the form.** The report
		// this restores is whichever one the revision belongs to; a posted report ID beside a
		// posted revision ID would be two things that need not belong together, and checking
		// the policy against the posted one would let a member of A roll back B's report by
		// naming B's revision and A's report.
		$post = get_post( (int) $revision->post_parent );

		if ( ! class_exists( 'WPCPM_Semester_Report' ) || ! $post instanceof WP_Post || WPCPM_Semester_Report::POST_TYPE !== $post->post_type ) {
			self::bounce( 'refused' );
		}

		$decision = self::decide_edit( $post );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( self::ACTION_RESTORE . '_' . $revision->ID );

		if ( 'final' === WPCPM_Semester_Report::state( $post ) ) {
			self::bounce( 'is-final', array(), self::cohort_of( $post ) );
		}

		$restored = wp_restore_post_revision( $revision->ID );

		self::leave( $restored ? 'restored' : 'not-restored', array(), self::cohort_of( $post ) );
	}

	/**
	 * Ask the students who have written something and not said whether it may be used.
	 *
	 * **Program managers only** (design spec section 14, open question 2, decided 3 September
	 * 2026), and the capability is required here on top of whatever the policy says. This
	 * button writes to real students asking them to say yes to being named in a document, and
	 * the institution is the party that benefits from the yes: it must not be the party that
	 * sends the reminder. The addresses belong to the program rather than to the school, so
	 * the send belongs to the program too. A member reaching this action by hand is refused
	 * with the same message as anybody else, and nothing is sent and nothing stamped.
	 *
	 * Three further rules, each about not being a nuisance to somebody who is not being paid
	 * to read this site's mail:
	 *
	 * - **never twice inside thirty days**, stamped on the student's own account, so two
	 *   managers pressing this on the same afternoon send one message between them and a
	 *   term's worth of presses send at most one a month;
	 * - **at most `ASK_PER_RUN` a press**, so nothing here can put four hundred messages
	 *   into a queue in one request;
	 * - **the rest are queued** rather than dropped, and go out on cron under the same rules,
	 *   re-asking about the account that pressed before each batch.
	 */
	public static function handle_ask() {
		// Before the post is even looked up, and before the policy: the policy would allow a
		// member of this institution, and this is the one action on the report that a member
		// may not take however well placed they otherwise are. Checked ahead of the lookup so
		// the answer a non-manager gets does not depend on whether the ID they posted names a
		// report, which would be a way to count them.
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			self::bounce( 'ask-refused' );
		}

		$post = self::posted_report();

		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'refused' );
		}

		$decision = self::decide_edit( $post );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( self::ACTION_ASK . '_' . $post->ID );

		$record = WPCPM_Semester_Report::institution_of( $post );
		$cohort = self::cohort_of( $post );

		$candidates = self::ask_list( $post );

		if ( is_wp_error( $candidates ) ) {
			self::bounce( 'ask-unread', array( 'why' => $candidates->get_error_message() ), $cohort, $record );
		}

		if ( empty( $candidates ) ) {
			self::bounce( 'ask-nobody', array(), $cohort, $record );
		}

		$now  = array_slice( $candidates, 0, self::ASK_PER_RUN );
		$rest = array_slice( $candidates, self::ASK_PER_RUN );
		$sent = self::ask_send( $now, $post );

		self::queue_ask( $post, $rest, get_current_user_id() );

		// Back to this institution's own report screen, carrying the switcher argument. A
		// manager presses this from the manager screen, where every institution's reports are
		// listed together; without the argument the redirect would land them on whichever
		// institution is their fallback, reading another school's withheld count as the
		// answer to what they just did.
		self::leave(
			'asked',
			array(
				'detail' => array(
					'sent'   => $sent,
					'queued' => count( $rest ),
				),
			),
			$cohort,
			$record
		);
	}

	/**
	 * Print one report as a standalone document, and stop.
	 *
	 * A `GET` handler, so the order is the one design spec 7.9 sets: the capability and the
	 * membership through `decide()`, and **then** the nonce. Both come before anything else,
	 * and they have to: drawing the document re-reads the students' consent, which is a live
	 * read, and it writes the export stamp. A cross-site link therefore causes neither.
	 *
	 * **A post ID that is not this reader's gets the unknown-report message, byte for byte
	 * the same as one that does not exist.** Anything else is a membership oracle: a refusal
	 * that says "not yours" for a real ID and "no such report" for a made-up one lets
	 * somebody walk the ID space and learn which institutions have written reports and how
	 * many.
	 */
	public static function handle_print() {
		$post_id = self::requested_report();
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! class_exists( 'WPCPM_Semester_Report' ) || ! $post instanceof WP_Post || WPCPM_Semester_Report::POST_TYPE !== $post->post_type ) {
			self::unknown();
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_VIEW_SEMESTER_REPORT,
			WPCPM_Institution_Policy::subject_post( $post, WPCPM_Semester_Report::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::unknown();
		}

		check_admin_referer( self::ACTION_PRINT . '_' . $post->ID );

		// What `_wpcpm_report_exported` is for: the manager screen's question is "has this
		// school actually used the report it wrote", and the answer is the day it last
		// printed one. It records that the document was served, not that it was complete: a
		// consent read that failed prints a sheet saying so, and that is still a print.
		update_post_meta( $post->ID, WPCPM_Semester_Report::META_EXPORTED, time() );

		self::send( self::document( $post ) );
	}

	/**
	 * Send the rest of an `ACTION_ASK` press.
	 *
	 * **Both gates are asked again, about the account that pressed**, and in the same order
	 * the handler asks them: the management capability first, then the policy. A manager whose
	 * capability has since been taken away must not have a queue still writing to students in
	 * their name, and neither must one whose institution has since been revoked. This is the
	 * rule `WPCPM_Institution_Create` applies to every slice of an import that runs after the
	 * request that started it has ended, with the extra gate open question 2 added.
	 *
	 * @param int $post_id The report the queue belongs to.
	 */
	public static function drain_ask( $post_id ) {
		$post_id = (int) $post_id;
		$name    = self::QUEUE_PREFIX . $post_id;
		$queued  = get_option( $name );

		if ( ! is_array( $queued ) || empty( $queued['ids'] ) ) {
			delete_option( $name );

			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! class_exists( 'WPCPM_Semester_Report' ) || WPCPM_Semester_Report::POST_TYPE !== $post->post_type ) {
			delete_option( $name );

			return;
		}

		$actor = isset( $queued['actor'] ) ? (int) $queued['actor'] : 0;

		if ( $actor < 1 || ! user_can( $actor, WPCPM_Roles::CAP_MANAGE ) ) {
			delete_option( $name );

			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_SEMESTER_REPORT,
			WPCPM_Institution_Policy::subject_post( $post, WPCPM_Semester_Report::META_INSTITUTION ),
			$actor
		);

		if ( empty( $decision['allowed'] ) ) {
			delete_option( $name );

			return;
		}

		$ids  = array_values( array_filter( array_map( 'intval', (array) $queued['ids'] ) ) );
		$now  = array_slice( $ids, 0, self::ASK_PER_RUN );
		$rest = array_slice( $ids, self::ASK_PER_RUN );

		self::ask_send( $now, $post );

		// `queue_ask()` deletes the row when nothing is left, so the queue clears itself and
		// there is one place that writes it.
		self::queue_ask( $post, $rest, $actor );
	}

	/*
	 * What the handlers read and write
	 * --------------------------------------------------------------------
	 */

	/**
	 * The report a posted form names, or null.
	 *
	 * Its own method rather than a line inside each handler, for the reason
	 * `bin/check-references.php` enforces about the other direction: a reader that goes
	 * looking in the wrong superglobal fails silently, and one named method is one place to
	 * check which one it reads.
	 *
	 * @return WP_Post|null
	 */
	private static function posted_report() {
		if ( ! class_exists( 'WPCPM_Semester_Report' ) ) {
			return null;
		}

		$post = get_post( WPCPM_Request::posted_id( 'report' ) );

		return ( $post instanceof WP_Post && WPCPM_Semester_Report::POST_TYPE === $post->post_type ) ? $post : null;
	}

	/**
	 * The revision a posted form names.
	 *
	 * @return int
	 */
	private static function posted_revision() {
		return WPCPM_Request::posted_id( 'revision' );
	}

	/**
	 * The report the print link names.
	 *
	 * Read from the query string, which is where a `wp_nonce_url()` link puts it, and kept
	 * out of the handler for the reason `bin/check-references.php` records.
	 *
	 * @return int
	 */
	private static function requested_report() {
		return WPCPM_Request::id( 'report' );
	}

	/**
	 * The decision every write on this screen is made under.
	 *
	 * The institution comes off the post's own meta, so a report ID from another school is
	 * decided against that school and gets the one refusal.
	 *
	 * @param WP_Post $post The report.
	 * @return array What `decide()` returned.
	 */
	private static function decide_edit( WP_Post $post ) {
		return WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_SEMESTER_REPORT,
			WPCPM_Institution_Policy::subject_post( $post, WPCPM_Semester_Report::META_INSTITUTION )
		);
	}

	/**
	 * Whether this reader may edit this institution's reports.
	 *
	 * The same question the handlers ask, asked again before a control is drawn, so nobody
	 * is shown a button that leads to a refusal.
	 *
	 * @param string $record Institutions record ID.
	 * @return bool
	 */
	private static function may_edit( $record ) {
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_SEMESTER_REPORT,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		return ! empty( $decision['allowed'] );
	}

	/**
	 * Everything the editing form submitted, cleaned to what the site itself knows about.
	 *
	 * The section keys are the server's and the quote ids come out of the stored snapshot,
	 * so a posted field naming anything else is not read at all rather than being read and
	 * then rejected. That is the difference between a handler that ignores an unknown quote
	 * id and one that could be talked into storing a choice about a quote nobody released.
	 *
	 * @param WP_Post $post The report.
	 * @return array{sections: array, choices: array}
	 */
	private static function submitted_values( WP_Post $post ) {
		$sections = array();

		foreach ( WPCPM_Semester_Report::sections() as $key => $section ) {
			$text = WPCPM_Request::posted_lines( self::text_field( $key ) );

			$sections[ $key ] = array(
				'text'   => mb_substr( $text, 0, (int) WPCPM_Semester_Report::MAX_TEXT ),
				'hidden' => '' !== WPCPM_Request::posted_text( self::hidden_field( $key ) ),
			);
		}

		$snapshot = WPCPM_Semester_Report::snapshot( $post );
		$quotes   = ( isset( $snapshot['quotes'] ) && is_array( $snapshot['quotes'] ) ) ? $snapshot['quotes'] : array();

		// **Start from what is stored, and let the form overwrite only what it drew.** The
		// picker draws the live quotes, and the live list can be shorter than the snapshot's:
		// the consent read failed and it drew nothing, or a colleague pressed "check the
		// students' answers again" between this reader opening the form and pressing Save (the
		// refresh deliberately does not move `post_modified_gmt`, so the stale fence does not
		// fire). An unticked box and a box that was never drawn post the same nothing, and
		// reading both as "unticked" un-included every released quote and deleted every
		// translation on an ordinary Save that then said "Saved." Each drawn quote carries a
		// marker, and a quote without one keeps whatever the school last chose for it. Ids
		// outside the snapshot are still ignored: the marker widens nothing, it only says
		// which of the snapshot's quotes this form was in a position to speak for.
		$stored  = WPCPM_Semester_Report::choices( $post );
		$choices = array();

		foreach ( $quotes as $quote ) {
			$id = isset( $quote['id'] ) ? (string) $quote['id'] : '';

			if ( '' === $id ) {
				continue;
			}

			if ( '' === WPCPM_Request::posted_text( self::offered_field( $id ) ) ) {
				if ( isset( $stored[ $id ] ) && is_array( $stored[ $id ] ) ) {
					$choices[ $id ] = $stored[ $id ];
				}

				continue;
			}

			$translation = WPCPM_Request::posted_lines( self::translation_field( $id ) );

			$choices[ $id ] = array(
				'include'     => '' !== WPCPM_Request::posted_text( self::include_field( $id ) ),
				'translation' => mb_substr( $translation, 0, (int) WPCPM_Semester_Report::MAX_TEXT ),
				// Stored whatever the student said, and read back beside the live `named`
				// flag: a student who withdraws their name is not shown one, and a student
				// who gives it back does not need the school to tick the box again.
				'show_name'   => '' !== WPCPM_Request::posted_text( self::name_field( $id ) ),
			);
		}

		return array(
			'sections' => $sections,
			'choices'  => $choices,
		);
	}

	/**
	 * The narrative as plain text, for the revision diff.
	 *
	 * @param array $sections The submitted sections.
	 * @return string
	 */
	private static function narrative_text( array $sections ) {
		$defined = WPCPM_Semester_Report::sections();
		$lines   = array();

		foreach ( $sections as $key => $section ) {
			$text = isset( $section['text'] ) ? trim( (string) $section['text'] ) : '';

			if ( '' === $text ) {
				continue;
			}

			$title   = isset( $defined[ $key ]['title'] ) ? (string) $defined[ $key ]['title'] : (string) $key;
			$lines[] = $title . "\n" . $text;
		}

		return implode( "\n\n", $lines );
	}

	/*
	 * The stash
	 * --------------------------------------------------------------------
	 */

	/**
	 * Keep a refused save's words, so a race cannot eat somebody's paragraph.
	 *
	 * One stash per account, keyed to the report it was written for. Not a list: the failure
	 * this answers is one person's save landing a second after a colleague's, and a person
	 * who has just been told their words were not saved is looking at the page that offers
	 * them back rather than opening a second report.
	 *
	 * @param WP_Post $post   The report.
	 * @param array   $values What `submitted_values()` read.
	 */
	private static function stash( WP_Post $post, array $values ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		update_user_meta(
			$user_id,
			self::META_STASH,
			array(
				'report'   => (int) $post->ID,
				'at'       => time(),
				'sections' => isset( $values['sections'] ) ? (array) $values['sections'] : array(),
				'choices'  => isset( $values['choices'] ) ? (array) $values['choices'] : array(),
			)
		);
	}

	/**
	 * The stash waiting for one report, or an empty array.
	 *
	 * @param WP_Post $post The report.
	 * @return array
	 */
	private static function stash_for( WP_Post $post ) {
		$stash = get_user_meta( get_current_user_id(), self::META_STASH, true );

		// Which report it was written for, or 0 for no stash at all. Read as one value so that
		// "there is nothing stashed" and "what is stashed belongs to another report" give the
		// same answer, which is what the caller wants: neither is this report's words, and
		// putting one report's paragraphs into another's boxes would be the worse of the two
		// failures this whole mechanism exists to avoid.
		$written_for = ( is_array( $stash ) && isset( $stash['report'] ) ) ? (int) $stash['report'] : 0;

		return ( $written_for > 0 && $written_for === (int) $post->ID ) ? (array) $stash : array();
	}

	/**
	 * Throw the stash away, once a save has landed.
	 */
	private static function clear_stash() {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			delete_user_meta( $user_id, self::META_STASH );
		}
	}

	/*
	 * Asking for consent
	 * --------------------------------------------------------------------
	 */

	/**
	 * The students this press would write to, in roster order.
	 *
	 * **The data half decides who they are.** It is the half that reads the Feedback rows and
	 * therefore the only one that can tell a student who has written something and not said
	 * whether it may be used from one who has written nothing at all. The distinction is the
	 * whole point of the message: the spec's set is "a candidate answer and no permission
	 * answer", and a list built here from the site's own stamps would be that set plus every
	 * student who never filled the form in, which is a mailing to people with nothing to
	 * release. An empty answer means nobody, not everybody.
	 *
	 * Nobody who was asked inside `ASK_AGAIN_AFTER` is in the list. A failed read is returned
	 * as the error rather than as an empty list, because "nobody is waiting to be asked" and
	 * "we could not find out who is" are the same empty array and opposite answers.
	 *
	 * @param WP_Post $post The report.
	 * @return int[]|WP_Error User IDs.
	 */
	private static function ask_list( WP_Post $post ) {
		$ids = WPCPM_Semester_Report::consent_candidates( $post );

		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		$ready = array();

		foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $user_id ) {
			if ( self::asked_recently( $user_id ) ) {
				continue;
			}

			$ready[] = $user_id;
		}

		return $ready;
	}

	/**
	 * Whether this student has had one of these inside `ASK_AGAIN_AFTER`.
	 *
	 * @param int $user_id The student.
	 * @return bool
	 */
	private static function asked_recently( $user_id ) {
		$last = (int) get_user_meta( (int) $user_id, self::META_ASKED, true );

		return $last > 0 && ( time() - $last ) < self::ASK_AGAIN_AFTER;
	}

	/**
	 * Write to each of them, and stamp the ones that went.
	 *
	 * The stamp is written **before** the send rather than after it. A message the mail
	 * server accepted and then bounced is one message; a message this loop retried on the
	 * next press because the stamp had not been written is a second one to somebody who
	 * already had the first. Erring towards not writing again is the right way round for a
	 * message nobody asked to receive.
	 *
	 * @param int[]   $user_ids The students.
	 * @param WP_Post $post     The report.
	 * @return int How many were written to.
	 */
	private static function ask_send( array $user_ids, WP_Post $post ) {
		$sent   = 0;
		$cohort = self::cohort_of( $post );

		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;

			// Re-asked here as well as in `ask_list()`, because a queue drained days later
			// is a different moment: a student asked by a colleague in the meantime must not
			// get a second copy out of a list that was built before that happened.
			if ( $user_id < 1 || self::asked_recently( $user_id ) ) {
				continue;
			}

			update_user_meta( $user_id, self::META_ASKED, time() );

			if ( WPCPM_Mail::send( $user_id, self::MAIL_CONTEXT, self::ask_message( $cohort ) ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * The consent message.
	 *
	 * **It names no institution and quotes nothing.** The student knows which school they
	 * are at, and a message repeating a sentence they wrote about their term back at them,
	 * in mail, to prompt them to release it, would be the site using their words to ask
	 * permission to use their words.
	 *
	 * @param string $cohort The cohort key, for the semester the report covers.
	 * @return callable A builder for `WPCPM_Mail::send()`. It is handed the recipient and
	 *                  takes no argument: the message says nothing about the person it is
	 *                  written to beyond what their own locale does to the wording.
	 */
	private static function ask_message( $cohort ) {
		return function () use ( $cohort ) {
			$page = class_exists( 'WPCPM_Students_Dashboard' ) ? WPCPM_Students_Dashboard::page_url() : '';

			$body = sprintf(
				/* translators: %s: a semester, e.g. "January to June 2026". */
				__( 'Your institution is writing its report on %s.', 'wpcredits-program-manager' ),
				WPCPM_Cohort::label( $cohort )
			) . "\n\n";

			$body .= __( 'Two questions on your own form decide what it may say about you: whether your institution may list you in the report, and whether it may quote the feedback you wrote. Both are yours to answer, and either answer is fine. Nothing you wrote is shown to your institution until you have said it may be.', 'wpcredits-program-manager' ) . "\n\n";

			if ( '' !== $page ) {
				$body .= __( 'Your answers live on your dashboard:', 'wpcredits-program-manager' ) . "\n" . $page . "\n\n";
			}

			$body .= __( 'If you would rather not answer, you do not have to. You will not be asked about this report again.', 'wpcredits-program-manager' );

			return array(
				'subject' => __( 'May your institution mention you in its semester report?', 'wpcredits-program-manager' ),
				'body'    => $body,
			);
		};
	}

	/**
	 * Keep the students one press could not reach, and arrange for them to be written to.
	 *
	 * @param WP_Post $post  The report.
	 * @param int[]   $rest  Who is left.
	 * @param int     $actor The account whose authority the queue runs under.
	 */
	private static function queue_ask( WP_Post $post, array $rest, $actor ) {
		$name = self::QUEUE_PREFIX . (int) $post->ID;

		if ( empty( $rest ) ) {
			delete_option( $name );

			return;
		}

		update_option(
			$name,
			array(
				'actor' => (int) $actor,
				'ids'   => array_values( array_filter( array_map( 'intval', $rest ) ) ),
			),
			false
		);

		$args = array( (int) $post->ID );

		if ( ! wp_next_scheduled( self::CRON_ASK, $args ) ) {
			wp_schedule_single_event( time() + 300, self::CRON_ASK, $args );
		}
	}

	/**
	 * Every generate lock and ask queue this screen has left behind.
	 *
	 * Both are keyed to a report's post ID, so neither has a name that can be written down
	 * here; `uninstall.php` calls this rather than carrying a list it could not keep. A lock
	 * is an option rather than a transient on purpose - it has to outlive a request that
	 * died halfway through a generation - and an option nobody deletes is an option forever.
	 *
	 * @return int How many rows went.
	 */
	public static function delete_all() {
		global $wpdb;

		$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( self::LOCK_PREFIX ) . '%',
				$wpdb->esc_like( self::QUEUE_PREFIX ) . '%'
			)
		);

		$gone = 0;

		foreach ( (array) $names as $name ) {
			$gone += delete_option( (string) $name ) ? 1 : 0;
		}

		return $gone;
	}

	/*
	 * The printed document
	 * --------------------------------------------------------------------
	 */

	/**
	 * The report as a standalone HTML document.
	 *
	 * No theme part, no `wp_head()`, no enqueue pass: this is echoed from `admin-post.php`
	 * and the stylesheet is inlined, the way the generated agreement is. A theme's header on
	 * a document a school prints and sends to its ministry would put this site's navigation
	 * on the first page of it.
	 *
	 * @param WP_Post $post The report.
	 * @return string A complete HTML document.
	 */
	private static function document( WP_Post $post ) {
		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="utf-8" />' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";

		// The document names one institution's students and is served to one reader. Nothing
		// about it belongs in an index.
		$html .= '<meta name="robots" content="noindex, nofollow" />' . "\n";

		// The browser proposes the title as the PDF's filename, which is why the report's own
		// title is the whole of it.
		$html .= '<title>' . esc_html( get_the_title( $post ) ) . '</title>' . "\n";
		$html .= '<style>' . "\n" . self::stylesheet() . "\n" . '</style>' . "\n";
		$html .= '</head>' . "\n";
		$html .= '<body class="wpcpm-report-print">' . "\n";
		$html .= self::render_document( $post, true ) . "\n";
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Echoed from admin-post.php with no theme, no `wp_head()` and no `wp_footer()`: there is no enqueue pass to print a handle. The handle is registered all the same, and `script_url()` is the URL it was registered under.
		$html .= '<script src="' . esc_url( self::script_url() ) . '"></script>' . "\n";
		$html .= '</body>' . "\n";
		$html .= '</html>' . "\n";

		return $html;
	}

	/**
	 * The stylesheet, ready to inline.
	 *
	 * Read off disk rather than held as a string so it can be edited as CSS and so the
	 * screen and the paper are dressed by one file. Dropped whole if it ever carries the two
	 * characters that end a tag, in that order, which would close the style element early
	 * and print the remainder of the file as text: an unstyled report is still the whole
	 * report, and a document that broke halfway down is not.
	 *
	 * @return string
	 */
	private static function stylesheet() {
		$path = WPCPM_PLUGIN_DIR . self::STYLE_FILE;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The plugin's own stylesheet, read by an absolute path inside its own directory; WP_Filesystem would ask for credentials on a request that has none to give.
		$css = (string) file_get_contents( $path );

		if ( false !== strpos( $css, '</' ) ) {
			return '';
		}

		return trim( $css );
	}

	/**
	 * Send one document and stop.
	 *
	 * Never cached. The page carries one institution's students by name and is built for one
	 * reader; a shared cache holding it would serve one school's report to the next.
	 *
	 * @param string $document A complete HTML document.
	 */
	private static function send( $document ) {
		nocache_headers();

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		echo $document; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A whole HTML document built by document(), which escapes every value it interpolates; escaping it again would print its own markup.
		exit;
	}

	/**
	 * The one message a request for a report this reader may not have gets.
	 *
	 * Byte for byte the same whether the post belongs to another institution, is some other
	 * kind of post, or does not exist. Anything else answers a question the reader is not
	 * entitled to ask.
	 */
	private static function unknown() {
		wp_die( esc_html__( 'That report is not on this site.', 'wpcredits-program-manager' ), 404 );
	}

	/**
	 * The nonced address of one report's printed document.
	 *
	 * No switcher travels on it: the handler resolves the institution from the report post
	 * itself, which is the rule for every post-keyed route in this module.
	 *
	 * @param int $post_id The report.
	 * @return string
	 */
	public static function print_url( $post_id ) {
		$post_id = (int) $post_id;

		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION_PRINT,
					'report' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_PRINT . '_' . $post_id
		);
	}

	/*
	 * Small readers, the lock, and the words
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take the generation lock for one institution and cohort.
	 *
	 * `add_option()` is the test-and-set: one INSERT that reports failure when the row
	 * already exists, so two presses arriving together cannot both read the base. A lock
	 * older than `LOCK_TIMEOUT` belonged to a request that died between taking it and
	 * releasing it, and is cleared, since otherwise that one semester could never be
	 * generated again.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $cohort      Cohort key.
	 * @return bool Whether the lock was taken.
	 */
	private static function lock( $institution, $cohort ) {
		$name = self::lock_name( $institution, $cohort );

		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( $name );

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		update_option( $name, time(), false );

		return true;
	}

	/**
	 * Release the generation lock.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $cohort      Cohort key.
	 */
	private static function unlock( $institution, $cohort ) {
		delete_option( self::lock_name( $institution, $cohort ) );
	}

	/**
	 * The lock's option name.
	 *
	 * Hashed, because an option name is 191 characters and a record ID plus a cohort key
	 * plus the prefix is close enough to that to be worth not thinking about again.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $cohort      Cohort key.
	 * @return string
	 */
	private static function lock_name( $institution, $cohort ) {
		return self::LOCK_PREFIX . md5( trim( (string) $institution ) . '|' . trim( (string) $cohort ) );
	}

	/**
	 * The semesters this institution has students in, newest first.
	 *
	 * @param string $record Institutions record ID.
	 * @return string[] Cohort keys.
	 */
	private static function cohorts_of( $record ) {
		$keys = array();

		foreach ( WPCPM_Roster_Index::rows( $record ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' );

			// A report about "no start date" is a report about nothing anybody can name, and
			// the cohort picker on the roster treats it the same way.
			if ( WPCPM_Cohort::NONE !== $key ) {
				$keys[ $key ] = true;
			}
		}

		$keys = array_keys( $keys );

		// The arguments are reversed, exactly as the roster's own cohort picker reverses
		// them: a school opening this section is nearly always writing about the term that
		// has just ended, and the oldest semester at the top would make the newest the one
		// they have to scroll for. NONE is already out of the list, so nothing has to be
		// held at one end.
		usort(
			$keys,
			static function ( $a, $b ) {
				return WPCPM_Cohort::compare( $b, $a );
			}
		);

		return $keys;
	}

	/**
	 * Which semester a report is about.
	 *
	 * @param WP_Post $post The report.
	 * @return string
	 */
	private static function cohort_of( WP_Post $post ) {
		return (string) get_post_meta( $post->ID, WPCPM_Semester_Report::META_COHORT, true );
	}

	/**
	 * The stored per-section values.
	 *
	 * @param WP_Post $post The report.
	 * @return array
	 */
	private static function stored_sections( WP_Post $post ) {
		$stored = get_post_meta( $post->ID, WPCPM_Semester_Report::META_SECTIONS, true );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * The stored per-quote choices.
	 *
	 * @param WP_Post $post The report.
	 * @return array
	 */
	private static function stored_choices( WP_Post $post ) {
		$stored = get_post_meta( $post->ID, WPCPM_Semester_Report::META_CHOICES, true );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * A section's supplied wording, for the four that have one.
	 *
	 * @param array $section A section definition.
	 * @return string
	 */
	private static function default_text( array $section ) {
		return isset( $section['default'] ) ? (string) $section['default'] : '';
	}

	/**
	 * How many entries a generated section would print, or -1 for a section with none.
	 *
	 * @param string $key      Section key.
	 * @param array  $snapshot The stored snapshot.
	 * @return int
	 */
	private static function section_count( $key, array $snapshot ) {
		$of = array(
			'teams'       => 'teams',
			'projects'    => 'students',
			'recognition' => 'events',
			'feedback'    => 'quotes',
		);

		if ( ! isset( $of[ $key ] ) || ! isset( $snapshot[ $of[ $key ] ] ) || ! is_array( $snapshot[ $of[ $key ] ] ) ) {
			return -1;
		}

		return count( $snapshot[ $of[ $key ] ] );
	}

	/**
	 * What a state is called on screen.
	 *
	 * @param string $state `draft` or `final`.
	 * @return string
	 */
	private static function state_label( $state ) {
		return 'final' === $state
			? __( 'Final', 'wpcredits-program-manager' )
			: __( 'Draft', 'wpcredits-program-manager' );
	}

	/**
	 * The form field holding one section's prose.
	 *
	 * @param string $key Section key.
	 * @return string
	 */
	private static function text_field( $key ) {
		return 'section_text_' . sanitize_key( $key );
	}

	/**
	 * The form field holding one section's hide toggle.
	 *
	 * @param string $key Section key.
	 * @return string
	 */
	private static function hidden_field( $key ) {
		return 'section_hidden_' . sanitize_key( $key );
	}

	/**
	 * The form field holding one quote's include flag.
	 *
	 * @param string $id Quote id.
	 * @return string
	 */
	private static function include_field( $id ) {
		return 'quote_include_' . sanitize_key( $id );
	}

	/**
	 * The form field holding one quote's show-name flag.
	 *
	 * @param string $id Quote id.
	 * @return string
	 */
	private static function name_field( $id ) {
		return 'quote_name_' . sanitize_key( $id );
	}

	/**
	 * The form field holding one quote's translation.
	 *
	 * @param string $id Quote id.
	 * @return string
	 */
	private static function translation_field( $id ) {
		return 'quote_translation_' . sanitize_key( $id );
	}

	/**
	 * The hidden marker a drawn quote carries, so an absent checkbox can be told from an
	 * absent quote.
	 *
	 * @param string $id Quote id.
	 * @return string
	 */
	private static function offered_field( $id ) {
		return 'quote_offered_' . sanitize_key( $id );
	}

	/**
	 * Leave a message and go back to the report.
	 *
	 * `$record` is for the one handler a program manager reaches from outside the dashboard.
	 * A member never needs it: `resolve_institution()` already puts them on their own
	 * institution, and the argument would be their own record ID either way.
	 *
	 * @param string $status What happened.
	 * @param array  $extra  Anything the message needs.
	 * @param string $cohort The semester to reopen, when there is one.
	 * @param string $record Institutions record ID to switch to, for a manager.
	 */
	private static function leave( $status, array $extra = array(), $cohort = '', $record = '' ) {
		WPCPM_Flash::set(
			self::FLASH,
			array_merge( array( 'status' => (string) $status ), $extra )
		);

		$url = WPCPM_Cohort::is_key( $cohort ) ? self::report_url( $cohort ) : '';

		if ( '' === $url ) {
			$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? WPCPM_Institutions_Dashboard::page_url() : '';
			$url  = ( '' === $page ? home_url( '/' ) : $page ) . '#wpcpm-report';
		}

		if ( '' !== (string) $record ) {
			$url = add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Leave a refusal and go back.
	 *
	 * @param string $status Why.
	 * @param array  $detail Anything the sentence needs.
	 * @param string $cohort The semester to reopen, when there is one.
	 * @param string $record Institutions record ID to switch to, for a manager.
	 */
	private static function bounce( $status, array $detail = array(), $cohort = '', $record = '' ) {
		self::leave( $status, empty( $detail ) ? array() : array( 'detail' => $detail ), $cohort, $record );
	}

	/**
	 * Print whatever the last press left to say.
	 *
	 * @param array $flash The flash `render()` read.
	 */
	private static function render_message( array $flash ) {
		$key = ! empty( $flash['status'] ) ? (string) $flash['status'] : '';

		if ( '' === $key ) {
			return;
		}

		$said   = self::messages();
		$text   = isset( $said[ $key ] ) ? $said[ $key ] : $said['unknown'];
		$detail = ( isset( $flash['detail'] ) && is_array( $flash['detail'] ) ) ? $flash['detail'] : array();
		$sums   = self::summary_sentence( $key, $detail );

		if ( '' !== $sums ) {
			$text = $sums;
		}

		printf(
			'<p class="wpcpm-report-card__message wpcpm-report-card__message--%1$s">%2$s</p>',
			esc_attr( sanitize_html_class( $key ) ),
			esc_html( $text )
		);
	}

	/**
	 * The sentences that have to carry a number or a reason, or ''.
	 *
	 * @param string $key    The status.
	 * @param array  $detail What the handler counted.
	 * @return string
	 */
	private static function summary_sentence( $key, array $detail ) {
		if ( 'asked' === $key ) {
			$sent   = isset( $detail['sent'] ) ? (int) $detail['sent'] : 0;
			$queued = isset( $detail['queued'] ) ? (int) $detail['queued'] : 0;

			if ( $queued > 0 ) {
				return sprintf(
					/* translators: 1: messages sent now, 2: messages still to go. */
					__( '%1$s students have been asked. %2$s more are queued and will be written to shortly.', 'wpcredits-program-manager' ),
					number_format_i18n( $sent ),
					number_format_i18n( $queued )
				);
			}

			return sprintf(
				/* translators: %s: a number of students. */
				_n( '%s student has been asked.', '%s students have been asked.', $sent, 'wpcredits-program-manager' ),
				number_format_i18n( $sent )
			);
		}

		// **The generation's own words, verbatim.** `WPCPM_Airtable` says which read failed
		// and why, and a school looking at "the report could not be written" with no reason
		// has nothing to tell the person they are about to ask for help.
		if ( ( 'generate-failed' === $key || 'consent-failed' === $key || 'ask-unread' === $key ) && ! empty( $detail['why'] ) ) {
			return sprintf(
				/* translators: %s: the reason, from the program records. */
				__( 'That did not work: %s', 'wpcredits-program-manager' ),
				(string) $detail['why']
			);
		}

		return '';
	}

	/**
	 * Every sentence this screen can say about what just happened.
	 *
	 * In one map so a refusal cannot be written twice in two wordings, and so the ones an
	 * outsider can trigger can be read together and checked for saying too much.
	 *
	 * @return array<string, string>
	 */
	public static function messages() {
		return array(
			'generated'         => __( 'The first draft is ready. Nothing in it has been sent anywhere.', 'wpcredits-program-manager' ),
			'generate-failed'   => __( 'The report could not be written, because the program records could not be read. Nothing was saved. Try again shortly.', 'wpcredits-program-manager' ),
			'already-generated' => __( 'There is already a report for that semester.', 'wpcredits-program-manager' ),
			'locked'            => __( 'That report is being written right now. Give it a moment and look again.', 'wpcredits-program-manager' ),
			'saved'             => __( 'Saved.', 'wpcredits-program-manager' ),
			// The one refusal that is not about permission, and the only one worth spelling
			// out: the reader has words on screen that the site does not hold, and the next
			// sentence they read has to be about getting them back.
			'stale'             => __( 'Someone at your institution saved this report after you opened it. Nothing you sent was lost: your words are in the boxes below, above the version that was saved.', 'wpcredits-program-manager' ),
			'is-final'          => __( 'That report is final. Reopen it before changing anything.', 'wpcredits-program-manager' ),
			'marked-final'      => __( 'This report is marked final. Students can still withdraw their consent, and it will still be taken out of the document when they do.', 'wpcredits-program-manager' ),
			'reopened'          => __( 'This report is open for editing again.', 'wpcredits-program-manager' ),
			'restored'          => __( 'That version is back.', 'wpcredits-program-manager' ),
			'not-restored'      => __( 'That version could not be put back.', 'wpcredits-program-manager' ),
			'consent-refreshed' => __( 'The students\' answers have been read again, and the report shows what they say now.', 'wpcredits-program-manager' ),
			'consent-failed'    => __( 'The students\' answers could not be read just now. The report is unchanged. Try again shortly.', 'wpcredits-program-manager' ),
			'asked'             => __( 'The students have been asked.', 'wpcredits-program-manager' ),
			'ask-nobody'        => __( 'There is nobody to ask: everybody in this semester who has written something has either answered or been asked in the last thirty days.', 'wpcredits-program-manager' ),
			// Said in the same words to a member as to a stranger. Naming the capability
			// would tell whoever tried exactly which one to go looking for.
			'ask-refused'       => __( 'Only a program manager can send that request. The students who have not answered are counted below; ask a program manager to write to them.', 'wpcredits-program-manager' ),
			'ask-unread'        => __( 'Nobody was written to, because the program records could not be read and there is no way to tell who has already answered. Try again shortly.', 'wpcredits-program-manager' ),
			'bad-cohort'        => __( 'Choose the semester the report is about.', 'wpcredits-program-manager' ),
			'refused'           => __( 'That is not something you can do here.', 'wpcredits-program-manager' ),
			'unavailable'       => __( 'Semester reports are not available on this site yet.', 'wpcredits-program-manager' ),
			'unknown'           => __( 'Something about that report could not be read.', 'wpcredits-program-manager' ),
		);
	}
}
