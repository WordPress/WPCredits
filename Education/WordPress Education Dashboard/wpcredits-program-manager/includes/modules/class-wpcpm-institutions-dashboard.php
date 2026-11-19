<?php
/**
 * Institutions module - the page a partner institution signs in to.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shell of the institution dashboard: who is looking, which institution, and how much
 * of it they may be shown.
 *
 * One page, not one page per institution, for the reason the mentor page gives: a
 * per-institution permalink is a URL somebody can guess, and there are 105 records to
 * guess from. The page renders against the logged-in viewer, and which institution that
 * is comes from one place, `WPCPM_Institution_Roster::resolve_institution()`, so the
 * switcher, the roster, the People card and every handler agree about whose dashboard
 * this is.
 *
 * The page is drawn in the dashboards' frame, a view per menu item (`views_for()`, `view()`).
 * The branch that matters is the locked one. An account whose Collaboration Agreement
 * nobody has accepted is offered the Overview and the Agreement and nothing else, and any
 * other address draws it the Overview: no comparison strip, no filter bar, no roster, no
 * footer. It has no roster yet, and a
 * "0 students" line on the page a rector was invited to reads as data loss rather than as
 * a step that has not happened. Hiding is a courtesy and never the fence: the fence is
 * `WPCPM_Institution_Policy`, which refuses the same account's direct requests inside
 * `ground_member()` whatever this page chose to draw.
 */
class WPCPM_Institutions_Dashboard {

	const SHORTCODE = 'wpcpm_institution_dashboard';
	const BLOCK     = 'wpcpm/institution-dashboard';
	const OPT_PAGE  = 'wpcpm_institution_page_id';
	const STYLE     = 'wpcpm-institution-dashboard';

	/** The key of the Semester reports view's lede in `leads()`, the one sentence kept from the page's modules of before. */
	const MODULE_REPORT = 'report';

	/**
	 * The page's slug.
	 *
	 * Chosen once and never renamed, the way the mentor page's was: the theme matches its
	 * template on it, and a rename breaks that and every link anyone has been sent.
	 */
	const SLUG = 'institution-dashboard';

	/** The query argument that names a view, the Sponsor Dashboard's and the Administrator Dashboard's own. */
	const VIEW_ARG = 'wpcpm_view';
	/** The views, each a menu item with its own address. The Overview is the page with no argument. */
	const VIEW_OVERVIEW       = 'overview';
	const VIEW_STUDENTS       = 'students';
	const VIEW_ENROLL         = 'enroll';
	const VIEW_REPORTS        = 'reports';
	const VIEW_INSTITUTION    = 'institution';
	const VIEW_AGREEMENT      = 'agreement';
	const VIEW_EVENTS         = 'events';
	const VIEW_ADMINISTRATORS = 'administrators';
	/** The guide and the program's updates, pages of their own drawn in this page's frame. */
	const VIEW_GUIDE   = 'guide';
	const VIEW_UPDATES = 'updates';
	/** The frame's key for this page, as the theme names it in `add_theme_support( 'wpcpm-dashboard-frame' )`. */
	const FRAME_PAGE = 'institution';

	/** The kinds of the Overview's rows (`waiting_items()`): the reader acts, it is said without waiting, or news. */
	const KIND_WAITS   = 'waits';
	const KIND_NEUTRAL = 'neutral';
	const KIND_NEWS    = 'news';

	/**
	 * The Students view's narrowed lists (1.123.98): the argument that names one, and its keys, each the list behind one
	 * row of the Overview: students with no sign-in on record, placements that ended and are still open, students
	 * waiting to graduate, and students who finish within 30 days (`only_rows()`).
	 */
	const ONLY_ARG         = 'wpcpm_only';
	const ONLY_NO_SIGN_IN  = 'no-sign-in';
	const ONLY_ENDED_OPEN  = 'ended-open';
	const ONLY_PENDING     = 'pending-graduation';
	const ONLY_FINISHING   = 'finishing';
	const ONLY_KEYS        = array( self::ONLY_NO_SIGN_IN, self::ONLY_ENDED_OPEN, self::ONLY_PENDING, self::ONLY_FINISHING );
	const FINISHING_WITHIN = 30;

	/**
	 * What `views_for()` and `waiting_items()` worked out in this request, by `memo_key()`. `forget()` clears it.
	 *
	 * @var array<string, array>
	 */
	private static $memo = array();

	/**
	 * The compact Viewing as institution an Administrator's head prints at the end of its first row while the page's
	 * surface is on (Task 14), drawn by `render()` and printed once by `render_head()`; '' otherwise.
	 *
	 * @var string
	 */
	private static $viewing = '';

	/** The cards each view draws, by their anchors without `wpcpm-`: what `card_url()` routes by. */
	const VIEW_CARDS = array(
		'students'       => array( 'roster', 'student-status', 'student-form', 'student-invite' ),
		'enroll'         => array( 'import' ),
		'reports'        => array( 'report' ),
		'institution'    => array( 'details', 'people', 'tutors' ),
		'agreement'      => array( 'agreement' ),
		'events'         => array( 'sevents' ),
		'administrators' => array( 'talks', 'for-administrators', 'admin-answers', 'admin-history' ),
	);

	/**
	 * The anchors the page sent readers to before it had views, which name no card: the live page's `handle_move()`
	 * came back to `#wpcpm-module-<module>` for its three modules (students, report, people), each now the content of a
	 * view. Kept so a saved or shared address opens that view (`anchor_views()`), as every card's anchor does.
	 */
	const MODULE_ANCHORS = array(
		'students'    => array( 'module-students' ),
		'reports'     => array( 'module-report' ),
		'institution' => array( 'module-people' ),
	);

	/**
	 * Records which title revision this site has been brought up to.
	 *
	 * A counter rather than a flag, for the reason the student page found out the hard way:
	 * a boolean records only *that* a rename happened, so a site renamed once is skipped for
	 * ever and keeps a title a revision old.
	 */
	const OPT_TITLE_FIXED = 'wpcpm_institution_page_title_fixed';

	/** The current title revision. Bump this whenever the page's title changes. */
	const TITLE_VERSION = 1;

	/**
	 * Page titles this plugin has shipped for the institution page.
	 *
	 * Empty, because "Institution Dashboard" is the first (open question 14). The mechanism
	 * ships from day one anyway, so renaming the page is one line here plus a bump of
	 * TITLE_VERSION rather than a migration somebody has to invent later.
	 */
	const OLD_TITLES = array();

	/**
	 * Hook up the shortcode, block, styles and routing.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		// After `register`, and once only. `ensure_page()` sets a title when it *creates* the
		// page, so an install that predates a wording change keeps the old one for ever.
		add_action( 'init', array( __CLASS__, 'maybe_rename_page' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_style' ) );

		// Make the page reachable. Without these a member has an account, a page gated to
		// their level, and no route to it: signing in drops them on a wp-admin screen that
		// shows them nothing at all.
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'replace_admin_dashboard' ) );
	}

	/**
	 * Bring an existing page's title in line with the current wording.
	 *
	 * Runs at most once per site: the flag is written whatever the outcome, so a site that
	 * has deliberately renamed the page is not asked again on every request.
	 *
	 * The *slug* is deliberately untouched, for the reason SLUG records.
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
				'post_title' => __( 'Institution Dashboard', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * Register the shortcode, the block and the stylesheet.
	 */
	public static function register() {
		self::register_assets();

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/institution-dashboard';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type(
				$block_dir,
				array( 'render_callback' => array( __CLASS__, 'render_block' ) )
			);
		}
	}

	/**
	 * Register (but do not enqueue) the stylesheet.
	 *
	 * It depends on the mentor stylesheet on purpose, exactly as the student page's does.
	 * The three pages share a shell - the color tokens, the "view as" switcher, the detail
	 * tables, badges, buttons and the muted-text treatment - and defining that once means
	 * they cannot drift apart, and that a theme styling one page has already styled this one.
	 */
	public static function register_assets() {
		WPCPM_Mentors_Dashboard::register_assets();

		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/institution.css',
				array( WPCPM_Mentors_Dashboard::STYLE ),
				WPCPM_VERSION
			);
		}
	}

	/**
	 * Load the stylesheet in the editor so the server-rendered preview matches the front end.
	 */
	public static function enqueue_editor_style() {
		self::register_assets();
		wp_enqueue_style( self::STYLE );
	}

	/**
	 * Whether this account acts for an institution right now.
	 *
	 * Membership, never the role: an account keeps `ROLE_INSTITUTION` until a manager takes
	 * it away, and this question is about the people currently acting for an institution.
	 * `WPCPM_Notices::applies_to()`'s `institution` case answers it the same way.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function is_member( $user = null ) {
		return WPCPM_Institution_Members::is_member( $user );
	}

	/**
	 * Whether this user should be routed to the institution page.
	 *
	 * Membership first, then three exclusions.
	 *
	 * There is no equivalent of the mentor page's second "still active" test, because
	 * `is_member()` already requires a well-formed stamp *and* the active flag: revocation
	 * moves the stamp and zeroes the flag, so an ex-member fails this on the first line.
	 *
	 * Program managers are excluded - they need wp-admin - and so is anyone who can edit
	 * others' posts (an editor or an administrator), for the reason the mentor page gives: bouncing an editor out of the admin is
	 * worse than the problem being solved. Mentors are excluded too, and that one is a
	 * decision rather than a safety valve: open question 12 settles that an account that
	 * both mentors and acts for an institution lands on the Mentor Report Card, because
	 * mentoring is the time-critical half while a member's first task is the agreement,
	 * which the invitation links to directly. Both pages stay one click apart in the toolbar.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	private static function should_route( $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( ! self::is_member( $user ) ) {
			return false;
		}

		if ( WPCPM_Mentors_Dashboard::is_mentor( $user ) ) {
			return false;
		}

		// `edit_others_posts`, not `edit_posts`: the clause means "this account works in wp-admin",
		// and a participant who can write posts (a contributor, or a sponsor's representative)
		// still belongs on their dashboard (WordPress/WPCredits#295, 1.123.59).
		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_others_posts' ) ) {
			return false;
		}

		return (bool) WPCPM_Settings::get_value( 'institution_home' );
	}

	/**
	 * Send members to their institution page when they log in.
	 *
	 * @param string           $redirect_to           Where WordPress intends to send them.
	 * @param string           $requested_redirect_to Where the request asked to go.
	 * @param WP_User|WP_Error $user                  The user, or an error.
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

		// A specific destination was asked for - typically because they followed the link in
		// an invitation and were bounced through the login form. Honor it; overriding would
		// take them somewhere they did not ask to go.
		//
		// Asked through `is_explicit_redirect()` rather than by testing for a non-empty
		// string: core's form carries a hidden `redirect_to` defaulting to `admin_url()`, so
		// a non-empty test fires on every ordinary login and the filter never redirects.
		if ( WPCPM_Request::is_explicit_redirect( $requested_redirect_to ) ) {
			return $redirect_to;
		}

		return $page;
	}

	/**
	 * Use the institution page in place of the wp-admin Dashboard.
	 *
	 * Only the Dashboard itself is redirected. `profile.php` is deliberately left alone so a
	 * member can still change their own password and name.
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
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * The page title, written once. The Sponsor Dashboard's twin is `WPCPM_Sponsors_Dashboard::title()`.
	 *
	 * @return string
	 */
	public static function title(): string {
		return __( 'Institution Dashboard', 'wpcredits-program-manager' );
	}

	/**
	 * The view the address asks for: one of the page's views, or the Overview. Never gated: which views the
	 * reader may open is `view()`'s question. An address with no `wpcpm_view`, as every link sent before the
	 * views existed is, is read for the card it was made for: a student, a roster filter, an enrollment batch
	 * or a semester report. A `wpcpm_view` the page does not know is the Overview, with no inference. The
	 * Sponsor Dashboard's twin is the first half of `WPCPM_Sponsors_Dashboard::view()`, which has no old
	 * addresses to read.
	 *
	 * @return string A `VIEW_*` key.
	 */
	public static function asked_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read, which changes nothing.
		if ( isset( $_GET[ self::VIEW_ARG ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
			$asked = is_scalar( $_GET[ self::VIEW_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::VIEW_ARG ] ) ) : '';

			return array_key_exists( $asked, self::VIEW_CARDS ) ? $asked : self::VIEW_OVERVIEW;
		}

		$students = array(
			WPCPM_Institution_Roster_View::ARG_STUDENT,
			WPCPM_Institution_Roster_View::ARG_COHORT,
			WPCPM_Institution_Roster_View::ARG_STATUS,
			WPCPM_Institution_Roster_View::ARG_SEARCH,
		);

		foreach ( $students as $arg ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
			if ( isset( $_GET[ $arg ] ) ) {
				return self::VIEW_STUDENTS;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		if ( isset( $_GET[ WPCPM_Institution_Import_Form::ARG_BATCH ] ) ) {
			return self::VIEW_ENROLL;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		if ( isset( $_GET[ WPCPM_Semester_Report_Screen::ARG ] ) ) {
			return self::VIEW_REPORTS;
		}

		return self::VIEW_OVERVIEW;
	}

	/**
	 * A view's address: the page's own, with `?wpcpm_view=` for any view but the Overview, then the arguments
	 * given, then, when given, the school an Administrator is looking at, so moving between views keeps it. A
	 * member's page never reads that argument (`WPCPM_Institution_Roster::resolve_institution()`), so a link sent
	 * to a school's people and its Administrator alike may carry it. The Sponsor Dashboard's twin is
	 * `WPCPM_Sponsors_Dashboard::view_url()`, which takes no arguments of its own. Each argument's name and value
	 * is encoded here, because `add_query_arg()` puts new ones in as given: a search such as "Smith & Sons" stays
	 * one argument. The address is not escaped: a caller uses `esc_url()` in markup and `wp_safe_redirect()` for a
	 * redirect.
	 *
	 * @param string $view   A `VIEW_*` key.
	 * @param string $record The school's record ID, for an Administrator's link, or '' for a member's own page.
	 * @param array  $args   More query arguments, name to value, unencoded; an empty or non-scalar value is left out.
	 * @return string '' while the page has no address.
	 */
	public static function view_url( string $view = self::VIEW_OVERVIEW, string $record = '', array $args = array() ): string {
		$url = self::page_url();

		if ( '' === $url ) {
			return '';
		}

		$query = array();

		if ( self::VIEW_OVERVIEW !== $view && array_key_exists( $view, self::VIEW_CARDS ) ) {
			$query[ self::VIEW_ARG ] = sanitize_key( $view );
		}

		foreach ( $args as $name => $value ) {
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$query[ rawurlencode( (string) $name ) ] = rawurlencode( (string) $value );
			}
		}

		$record = trim( $record );

		if ( '' !== $record && WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			$query[ WPCPM_Institution_Roster::ARG_VIEW ] = $record;
		}

		return array() === $query ? $url : (string) add_query_arg( $query, $url );
	}

	/**
	 * The view that draws a card. The Sponsor Dashboard's twin is `WPCPM_Sponsors_Dashboard::view_of()`.
	 *
	 * @param string $card The card's anchor without `wpcpm-`.
	 * @return string A `VIEW_*` key: the Overview for a card no view lists.
	 */
	public static function view_of( string $card ): string {
		foreach ( self::VIEW_CARDS as $view => $cards ) {
			if ( in_array( $card, $cards, true ) ) {
				return $view;
			}
		}

		return self::VIEW_OVERVIEW;
	}

	/**
	 * A link to a card at the view that draws it: the mails, the guide, the wp-admin screens and every card's
	 * way back after a press land there, and the dashboards' opener opens the card. The Sponsor Dashboard's
	 * twin is `WPCPM_Sponsors_Dashboard::card_url()`. `$item` is an anchor id: only its letters, digits, hyphens
	 * and underscores are kept, and an item left with none is the card's own anchor. The address is not escaped:
	 * a caller uses `esc_url()` in markup and `wp_safe_redirect()` for a redirect.
	 *
	 * @param string $card   The card's anchor without `wpcpm-`.
	 * @param string $record The school's record ID, for an Administrator's link, or ''.
	 * @param string $item   An anchor id inside the card in place of the card's own, or ''.
	 * @param array  $args   More query arguments, as `view_url()` takes them.
	 * @return string '' while the page has no address.
	 */
	public static function card_url( string $card, string $record = '', string $item = '', array $args = array() ): string {
		$url = self::view_url( self::view_of( $card ), $record, $args );

		if ( '' === $url ) {
			return '';
		}

		$item = (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $item );

		return $url . '#' . ( '' !== $item ? $item : 'wpcpm-' . sanitize_key( $card ) );
	}

	/**
	 * The address of the view that holds a card, with the school an Administrator's request names
	 * (`requested_record()`), and no anchor: a way back that has no referer to return to, for a handler that adds
	 * its own anchor. Not escaped: `wp_safe_redirect()` for a redirect.
	 *
	 * @param string $card The card's anchor without `wpcpm-`.
	 * @return string '' while the page has no address.
	 */
	public static function way_back( string $card ): string {
		return self::view_url( self::view_of( $card ), self::requested_record() );
	}

	/**
	 * The school an Administrator's address names: the request's record ID, trimmed, shaped like one and still
	 * in the index. '' for a reader who is no Administrator, whose page is their own school's, and for an
	 * address that names none. Read from the query string or the form, as the roster reads it. The Sponsor
	 * Dashboard's twin is `WPCPM_Sponsors_Dashboard::requested_record()`.
	 *
	 * @return string Record ID, or ''.
	 */
	public static function requested_record(): string {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return '';
		}

		$asked = trim( (string) WPCPM_Request::text( WPCPM_Institution_Roster::ARG_VIEW ) );

		if ( '' === $asked ) {
			$asked = trim( (string) WPCPM_Request::posted_text( WPCPM_Institution_Roster::ARG_VIEW ) );
		}

		return '' !== $asked && WPCPM_Mentors_Sync::is_record_id( $asked ) && WPCPM_Institutions_Index::has( $asked ) ? $asked : '';
	}

	/**
	 * A link that keeps the school an Administrator is looking at: the address with the school's record ID, so
	 * the page it opens draws that school. As it is when there is no address, or no school to keep: a value that is
	 * not a record ID is never added. The Sponsor Dashboard's twin is `WPCPM_Sponsors_Dashboard::keep_sponsor()`.
	 * The address is not escaped: a caller uses `esc_url()` in markup and `wp_safe_redirect()` for a redirect.
	 *
	 * @param string $url    The address.
	 * @param string $record Record ID, or ''.
	 * @return string
	 */
	public static function keep_institution( string $url, string $record ): string {
		$record = trim( $record );

		if ( '' === $url || ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return $url;
		}

		return (string) add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, rawurlencode( $record ), $url );
	}

	/**
	 * The views a reader may open on a school's page, in menu order: each asked from the same policy
	 * question its cards ask, so a menu never offers a view whose cards would refuse the reader.
	 *
	 * The Overview and the Agreement always; Students and Institution when the roster may be viewed;
	 * Enroll students while enrollment is switched on and a student may be added; Semester reports
	 * when the semester report may be viewed (and the report class is there, as its card asks); Events
	 * on the reader's own page alone (never a tutor's, never an Administrator's looking at a school),
	 * when the page is not locked (`render()`'s own test) and the events list has something to show;
	 * Administrators for a reader who can manage the program. A locked school's people are refused the
	 * gated questions, so they get the Overview and the Agreement alone; an Administrator is never
	 * gated, so they get every view the school's own representative would but Events. No card is drawn
	 * to answer it. The events list is asked last, and only on the reader's own page that is not
	 * locked; it only peeks at the outcome waiting on its channel.
	 *
	 * Worked out once a request for a record, a reader and `$can_manage` (`forget()` clears it), so the menu and
	 * the Overview share one answer.
	 *
	 * @param string  $record     The school's Institutions record ID, resolved by the page.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @return string[] View keys.
	 */
	public static function views_for( string $record, WP_User $viewer, bool $can_manage ): array {
		$key = self::memo_key( 'views', $record, $viewer, $can_manage );

		if ( ! isset( self::$memo[ $key ] ) ) {
			self::$memo[ $key ] = self::work_out_views( $record, $viewer, $can_manage );
		}

		return self::$memo[ $key ];
	}

	/**
	 * `views_for()` itself, asked once a request.
	 *
	 * @param string  $record     The school's Institutions record ID.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @return string[] View keys.
	 */
	private static function work_out_views( string $record, WP_User $viewer, bool $can_manage ): array {
		$subject = WPCPM_Institution_Policy::subject_institution( $record );
		$may     = static function ( string $act ) use ( $subject, $viewer ): bool {
			$decision = WPCPM_Institution_Policy::decide( $act, $subject, $viewer );

			return ! empty( $decision['allowed'] );
		};
		$roster  = $may( WPCPM_Institution_Policy::ACT_VIEW_ROSTER );
		$views   = array( self::VIEW_OVERVIEW );

		if ( $roster ) {
			$views[] = self::VIEW_STUDENTS;
		}

		if ( WPCPM_Institution_Import_Form::enabled() && $may( WPCPM_Institution_Policy::ACT_ADD_STUDENT ) ) {
			$views[] = self::VIEW_ENROLL;
		}

		// The card's own guard: a checkout without the report class draws no report.
		if ( class_exists( 'WPCPM_Semester_Report' ) && $may( WPCPM_Institution_Policy::ACT_VIEW_SEMESTER_REPORT ) ) {
			$views[] = self::VIEW_REPORTS;
		}

		if ( $roster ) {
			$views[] = self::VIEW_INSTITUTION;
		}

		$views[] = self::VIEW_AGREEMENT;

		$own_page = (string) WPCPM_Institution_Members::institution_of( $viewer ) === $record
			&& WPCPM_Institution_Members::LEVEL_TUTOR !== WPCPM_Institution_Members::level_of( $viewer );

		// `render()`'s own test, `! $locked`: an Administrator is never locked out of a school.
		$locked = ! $can_manage && ! WPCPM_Institution_Agreement::is_settled( $record );

		if ( $own_page && ! $locked && class_exists( 'WPCPM_Sponsor_Event_List' ) && WPCPM_Sponsor_Event_List::shows( $viewer ) ) {
			$views[] = self::VIEW_EVENTS;
		}

		if ( $can_manage ) {
			$views[] = self::VIEW_ADMINISTRATORS;
		}

		return $views;
	}

	/**
	 * The views an Administrator with no school chosen is listed, without counts: every view a school
	 * may have but Events, which is a school's own, Enroll students while enrollment is switched on, and
	 * Semester reports while the report class is there.
	 *
	 * @return string[] View keys.
	 */
	public static function choice_views(): array {
		$views = array( self::VIEW_OVERVIEW, self::VIEW_STUDENTS );

		if ( WPCPM_Institution_Import_Form::enabled() ) {
			$views[] = self::VIEW_ENROLL;
		}

		if ( class_exists( 'WPCPM_Semester_Report' ) ) {
			$views[] = self::VIEW_REPORTS;
		}

		return array_merge( $views, array( self::VIEW_INSTITUTION, self::VIEW_AGREEMENT, self::VIEW_ADMINISTRATORS ) );
	}

	/**
	 * What waits for the reader on a school's page, what is said to them without waiting, and what is news for
	 * them: the Overview's rows, and through `waiting_by_view()` the menu's counts. A row waits (`kind` 'waits',
	 * tagged "Waits", red, counted) only when this reader has to act on it (the owner's rule); a row about
	 * something somebody else or the site is doing is said with a neutral tag and counted nowhere (`kind`
	 * 'neutral'); news is for the school's own readers, after the rest, tagged "New", counted nowhere (`kind`
	 * 'news', the controller's ruling 4). Every why is the sentence the card that holds the row prints today,
	 * called from that card's class, never retyped.
	 *
	 * In this order:
	 *
	 * - an Administrator, while the agreement is not settled: one row, the banner's sentences (the planner's
	 *   ruling P1). It waits while the Administrator acts on it (a signed copy waiting for review, or the site and
	 *   Airtable disagreeing: accepted or on file); while the school acts (none recorded, generated and not
	 *   uploaded, returned, revoked) it is "With the school";
	 * - a school's reader the agreement asks (`ACT_AGREEMENT`), while it is not settled and the school is the one
	 *   to act (none, generated, returned, revoked): the agreement waits. A signed copy waiting for review is no
	 *   row for the school, and a tutor, whom the agreement does not ask, gets none;
	 * - a checked list of students not yet created waits; a creation in progress carries on by itself and is
	 *   "In progress";
	 * - the school's facts (1.123.98), with the news and only when `$news` asks: the students who have not signed in
	 *   yet, those who finish within 30 days, the placements that ended and are still open and the students waiting to
	 *   graduate, each `kind` 'neutral' with no tag, counted nowhere, for a reader offered its list (`only_offered()`),
	 *   an Administrator looking at the school included;
	 * - news, for a reader who is not an Administrator: each semester report approved in the last 30 days,
	 *   newest first, and the details request approved or declined in the last 30 days while no newer one is
	 *   sent, for a reader the details card is drawn for (`ACT_EDIT_DETAILS`: never a tutor). A stamp later than now
	 *   is not news.
	 *
	 * The reader's views are worked out first, and nothing is read for a view the reader does not have. Worked
	 * out once a request for a record, a reader and `$can_manage` (`forget()` clears it), so the menu and the
	 * Overview share one answer. The 30 days are read from `now()`. The news is worked out only when `$news` asks
	 * for it, which only the Overview does, the one view that prints it: the menu's counts on every view count the
	 * rows that wait and nothing else (Task 28, W5), so they read no report list and no details outcome.
	 *
	 * @param string  $record     The school's Institutions record ID, resolved by the page.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @param bool    $news       Whether the news rows are worked out and returned after the others.
	 * @return array[] Each `card` (a `VIEW_CARDS` key), `count` (int), `title`, `why`, `action` (the link's words),
	 *                 `kind` ('waits', 'neutral' or 'news'), `tag` (the tag's words: "Waits", "With the school",
	 *                 "In progress" or "New"), `tone` ('red' for a row that waits, '' for the rest), `args` (query
	 *                 arguments for `card_url()`) and `news` (bool, `kind` is 'news').
	 */
	public static function waiting_items( string $record, WP_User $viewer, bool $can_manage, bool $news = true ): array {
		$rows = array();

		foreach ( $news ? array( 'items', 'news' ) : array( 'items' ) as $part ) {
			$key = self::memo_key( $part, $record, $viewer, $can_manage );

			if ( ! isset( self::$memo[ $key ] ) ) {
				self::$memo[ $key ] = self::work_out_items( $record, $viewer, $can_manage, $part );
			}

			$rows = array_merge( $rows, self::$memo[ $key ] );
		}

		return $rows;
	}

	/**
	 * `waiting_items()` itself, worked out once a request, a part at a time: `items` the rows that wait or are said
	 * without waiting, `news` the news.
	 *
	 * @param string  $record     The school's Institutions record ID.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @param string  $part       `items` or `news`.
	 * @return array[] The rows.
	 */
	private static function work_out_items( string $record, WP_User $viewer, bool $can_manage, string $part ): array {
		$views = self::views_for( $record, $viewer, $can_manage );
		$has   = static function ( string $view ) use ( $views ): bool {
			return in_array( $view, $views, true );
		};
		$row   = static function ( string $card, string $title, string $why, string $action, string $kind, string $tag, array $args = array() ): array {
			return array(
				'card'   => $card,
				'count'  => 1,
				'title'  => $title,
				'why'    => $why,
				'action' => $action,
				'kind'   => $kind,
				'tag'    => $tag,
				'tone'   => self::KIND_WAITS === $kind ? 'red' : '',
				'args'   => $args,
				'news'   => self::KIND_NEWS === $kind,
			);
		};
		$waits = __( 'Waits', 'wpcredits-program-manager' );
		$items = array();
		$news  = array();
		$first = 'news' !== $part;

		if ( $first && $has( self::VIEW_AGREEMENT ) && ! WPCPM_Institution_Agreement::is_settled( $record ) ) {
			$open = __( 'Open the agreement', 'wpcredits-program-manager' );

			if ( $can_manage ) {
				$summary = WPCPM_Institution_Agreement::summary( $record );
				$theirs  = in_array( (string) $summary['state'], array( WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, WPCPM_Institution_Agreement::SUMMARY_ON_FILE ), true );
				$items[] = $row(
					'agreement',
					__( 'The agreement is not settled', 'wpcredits-program-manager' ),
					implode( ' ', self::banner_sentences( $record ) ),
					$open,
					$theirs ? self::KIND_WAITS : self::KIND_NEUTRAL,
					$theirs ? $waits : __( 'With the school', 'wpcredits-program-manager' )
				);
			} else {
				$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_AGREEMENT, WPCPM_Institution_Policy::subject_institution( $record ), $viewer );

				if ( ! empty( $decision['allowed'] ) ) {
					$summary = WPCPM_Institution_Agreement::summary( $record );
					$state   = (string) $summary['state'];
					$titles  = array(
						WPCPM_Institution_Agreement::SUMMARY_NONE      => __( 'Collaboration Agreement not signed yet', 'wpcredits-program-manager' ),
						WPCPM_Institution_Agreement::SUMMARY_GENERATED => __( 'Collaboration Agreement not signed yet', 'wpcredits-program-manager' ),
						WPCPM_Institution_Agreement::SUMMARY_RETURNED  => __( 'Collaboration Agreement returned', 'wpcredits-program-manager' ),
						WPCPM_Institution_Agreement::SUMMARY_REVOKED   => __( 'Collaboration Agreement revoked', 'wpcredits-program-manager' ),
					);

					if ( isset( $titles[ $state ] ) ) {
						$items[] = $row( 'agreement', $titles[ $state ], WPCPM_Institution_Panel::state_sentence( $state, $summary ), $open, self::KIND_WAITS, $waits );
					}
				}
			}
		}

		$batch = $first && $has( self::VIEW_ENROLL ) ? (int) WPCPM_Institution_Import::active_for( $record ) : 0;
		$list  = $batch > 0 ? WPCPM_Institution_Import::batch( $batch ) : null;

		if ( is_array( $list ) ) {
			$running = WPCPM_Institution_Import::STATE_CREATING === $list['state'];
			$items[] = $row(
				'import',
				$running ? __( 'A list of students is being created', 'wpcredits-program-manager' ) : __( 'A checked list of students waits to be created', 'wpcredits-program-manager' ),
				WPCPM_Institution_Import_Form::waiting_sentence( $batch, $record, $list ),
				__( 'Open the list', 'wpcredits-program-manager' ),
				$running ? self::KIND_NEUTRAL : self::KIND_WAITS,
				$running ? __( 'In progress', 'wpcredits-program-manager' ) : $waits
			);
		}

		if ( $first ) {
			return $items;
		}

		// Facts of the school, said without waiting, with no tag and counted nowhere (1.123.98; fix round 1, B1: the
		// owner's decision), in the order a placement runs: the students who have not signed in yet, those who finish
		// within 30 days, the placements that ended and are still open, the students waiting to graduate. Each for a reader
		// offered its list (`only_offered()`), an Administrator looking at the school included, since they are the school's
		// facts and not "your" news; worked out only on the Overview, which alone prints them, so the menu's count never
		// asks for them and no other view reads the school's accounts because of them.
		$see = __( 'See them', 'wpcredits-program-manager' );

		foreach ( array( self::ONLY_NO_SIGN_IN, self::ONLY_FINISHING, self::ONLY_ENDED_OPEN, self::ONLY_PENDING ) as $only ) {
			if ( ! self::only_offered( $only, $record, $viewer, $can_manage ) ) {
				continue;
			}

			$students = self::only_rows( $record, $only );
			$count    = count( $students );

			if ( $count < 1 ) {
				continue;
			}

			$items[] = $row( 'roster', self::only_title( $only, $count ), self::only_line( $only, $students, $record ), $see, self::KIND_NEUTRAL, '', array( self::ONLY_ARG => $only ) );
		}

		// News is the school's own: an Administrator looking at a school is told nothing as "your" report or request.
		if ( $can_manage ) {
			return $items;
		}

		$now   = self::now();
		$since = $now - 30 * DAY_IN_SECONDS;
		$fresh = static function ( int $at ) use ( $now, $since ): bool {
			return $at > 0 && $at >= $since && $at <= $now;
		};
		$new   = __( 'New', 'wpcredits-program-manager' );

		if ( $has( self::VIEW_REPORTS ) ) {
			$approved = array();

			foreach ( WPCPM_Semester_Report::reports_of( $record ) as $cohort => $post ) {
				$cohort = (string) $cohort;
				$stamp  = WPCPM_Semester_Report::approved_at( $post );

				if ( '' === $cohort || WPCPM_Semester_Report::STATE_APPROVED !== WPCPM_Semester_Report::state( $post ) || empty( $stamp['at'] ) || ! $fresh( (int) $stamp['at'] ) ) {
					continue;
				}

				$approved[] = array( $cohort, (int) $stamp['at'] );
			}

			// Newest first; two approved in the same second keep the index's order, the newer cohort first.
			usort(
				$approved,
				static function ( array $a, array $b ): int {
					return $b[1] <=> $a[1];
				}
			);

			$format = get_option( 'date_format' );

			foreach ( $approved as $report ) {
				$news[] = $row(
					'report',
					/* translators: %s: the semester, e.g. "January to June 2026". */
					sprintf( __( 'Your semester report for %s is ready', 'wpcredits-program-manager' ), WPCPM_Cohort::label( $report[0] ) ),
					/* translators: %s: a date. */
					sprintf( __( 'Approved on %s', 'wpcredits-program-manager' ), wp_date( $format, $report[1] ) ),
					__( 'Read the report', 'wpcredits-program-manager' ),
					self::KIND_NEWS,
					$new,
					array( 'wpcpm_report' => $report[0] )
				);
			}
		}

		// The details card's own question: a tutor has Your institution and is not drawn the card the row opens.
		$details = $has( self::VIEW_INSTITUTION ) ? WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EDIT_DETAILS, WPCPM_Institution_Policy::subject_institution( $record ), $viewer ) : array();

		if ( ! empty( $details['allowed'] ) ) {
			$outcome = WPCPM_Institution_Request::latest_details_outcome( $record );
			$decided = isset( $outcome['state'] ) && in_array( $outcome['state'], array( WPCPM_Institution_Request::STATE_DONE, WPCPM_Institution_Request::STATE_DECLINED ), true );

			if ( $decided && $fresh( (int) $outcome['closed_at'] ) && 0 === (int) WPCPM_Institution_Request::open_details_for( $record ) ) {
				$why = WPCPM_Institution_Details_Card::outcome_sentence( $outcome );

				if ( '' !== $why ) {
					$news[] = $row( 'details', __( 'Your request was decided', 'wpcredits-program-manager' ), $why, __( 'See your details', 'wpcredits-program-manager' ), self::KIND_NEWS, $new );
				}
			}
		}

		return array_merge( $items, $news );
	}

	/**
	 * How much waits in each view, for the menu's counts: the sum of `count` per view of the rows whose `kind` is
	 * 'waits'. A neutral row and news are counted nowhere (the controller's ruling 4).
	 *
	 * @param array[] $items What `waiting_items()` returned.
	 * @return array<string, int> View key to count.
	 */
	public static function waiting_by_view( array $items ): array {
		$counts = array();

		foreach ( $items as $item ) {
			if ( ! isset( $item['kind'] ) || self::KIND_WAITS !== $item['kind'] ) {
				continue;
			}

			$view            = self::view_of( (string) $item['card'] );
			$counts[ $view ] = ( isset( $counts[ $view ] ) ? $counts[ $view ] : 0 ) + (int) $item['count'];
		}

		return $counts;
	}

	/**
	 * The one moment the Overview's 30-day window reads. A filter, so a suite can hold the clock still: PHP
	 * cannot stand in for `time()`, and `WPCPM_Dates` has no clock of its own.
	 *
	 * @return int A Unix timestamp.
	 */
	private static function now(): int {
		return (int) apply_filters( 'wpcpm_institution_now', time() );
	}

	/**
	 * Today in the site's time zone, `Y-m-d`, read from `now()`: the day the Overview's end dates are counted from.
	 *
	 * @return string
	 */
	public static function today(): string {
		return (string) wp_date( 'Y-m-d', self::now() );
	}

	/**
	 * The words of one narrowed list (1.123.98), for its row on the Overview and for the sentence that heads it on the
	 * Students view: how many students it holds, in the row's own words. '' for a key that is none of `ONLY_KEYS`.
	 *
	 * @param string $key   One of `ONLY_KEYS`.
	 * @param int    $count How many students the list holds.
	 * @return string Not escaped.
	 */
	public static function only_title( string $key, int $count ): string {
		$n = number_format_i18n( $count );

		switch ( $key ) {
			case self::ONLY_NO_SIGN_IN:
				/* translators: %s: a number of students. */
				return sprintf( _n( '%s student has not signed in yet', '%s students have not signed in yet', $count, 'wpcredits-program-manager' ), $n );
			case self::ONLY_ENDED_OPEN:
				/* translators: %s: a number of placements. */
				return sprintf( _n( '%s placement ended and is still open', '%s placements ended and are still open', $count, 'wpcredits-program-manager' ), $n );
			case self::ONLY_PENDING:
				/* translators: %s: a number of students. */
				return sprintf( _n( '%s student is waiting to graduate', '%s students are waiting to graduate', $count, 'wpcredits-program-manager' ), $n );
			case self::ONLY_FINISHING:
				/* translators: %s: a number of students. */
				return sprintf( _n( '%s student finishes within 30 days', '%s students finish within 30 days', $count, 'wpcredits-program-manager' ), $n );
		}

		return '';
	}

	/**
	 * The sentence under one fact's title on the Overview (1.123.98), in words for one student or for several: what to do
	 * about the students who have not signed in yet and about the placements that ended and are still open, what the
	 * program is doing about the students waiting to graduate (with the day the longest wait began, when the site knows
	 * it), and nothing for the students who finish within 30 days.
	 *
	 * @param string $key      One of `ONLY_KEYS`.
	 * @param array  $students The list's students, as `only_rows()` gives them.
	 * @param string $record   The school's Institutions record ID.
	 * @return string Not escaped.
	 */
	private static function only_line( string $key, array $students, string $record ): string {
		$count = count( $students );

		switch ( $key ) {
			case self::ONLY_NO_SIGN_IN:
				return __( 'Open a student to send them an invitation.', 'wpcredits-program-manager' );
			case self::ONLY_ENDED_OPEN:
				return _n( 'The end date has passed and the student is still on the program. Correct the date if the placement goes on.', 'The end dates have passed and the students are still on the program. Correct a date if its placement goes on.', $count, 'wpcredits-program-manager' );
			case self::ONLY_PENDING:
				$line    = __( 'The program is checking their work.', 'wpcredits-program-manager' );
				$longest = self::longest_wait( $students, $record );

				if ( $longest > 0 ) {
					/* translators: %s: a date, in the site's date format. */
					$line .= ' ' . sprintf( _n( 'The wait began on %s.', 'The longest wait began on %s.', $count, 'wpcredits-program-manager' ), wp_date( (string) get_option( 'date_format' ), $longest ) );
				}

				return $line;
		}

		return '';
	}

	/**
	 * Whether this reader is offered one narrowed list: the school's agreement is settled (a school with no settled
	 * agreement is offered none of them, whoever is looking), the reader may open the Students view, and for the two
	 * lists a reader acts on, may also act (resend an invitation, edit a student) or is an Administrator, who is told them
	 * as the school's. A tutor is offered the two lists of facts and not the two that wait. A key that is none of
	 * `ONLY_KEYS` is offered to nobody.
	 *
	 * @param string  $key        The list's key.
	 * @param string  $record     The school's Institutions record ID.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @return bool
	 */
	public static function only_offered( string $key, string $record, WP_User $viewer, bool $can_manage ): bool {
		if ( ! in_array( $key, self::ONLY_KEYS, true ) || ! in_array( self::VIEW_STUDENTS, self::views_for( $record, $viewer, $can_manage ), true ) || ! self::settled( $record ) ) {
			return false;
		}

		$acts = array(
			self::ONLY_NO_SIGN_IN => WPCPM_Institution_Policy::ACT_RESEND_INVITE,
			self::ONLY_ENDED_OPEN => WPCPM_Institution_Policy::ACT_EDIT_STUDENT,
		);

		if ( ! isset( $acts[ $key ] ) || $can_manage ) {
			return true;
		}

		$decision = WPCPM_Institution_Policy::decide( $acts[ $key ], WPCPM_Institution_Policy::subject_institution( $record ), $viewer );

		return ! empty( $decision['allowed'] );
	}

	/**
	 * Whether the school's agreement is settled, asked once a request for a school (`forget()` clears it).
	 *
	 * @param string $record The school's Institutions record ID.
	 * @return bool
	 */
	private static function settled( string $record ): bool {
		$memo = 'settled|' . $record;

		if ( ! isset( self::$memo[ $memo ] ) ) {
			self::$memo[ $memo ] = array( WPCPM_Institution_Agreement::is_settled( $record ) );
		}

		return self::$memo[ $memo ][0];
	}

	/**
	 * The students of one narrowed list (1.123.98), from the school's roster rows of every cohort, in the roster's
	 * order, keyed by Students record ID. Worked out once a request for a school and a key (`forget()` clears it).
	 *
	 * - `no-sign-in`: on the program now or waiting for a mentor, not waiting to graduate (fix round 2: a finished student
	 *   needs no invitation), with an account the site can speak for (`unsigned()`: made since the sign-in record began)
	 *   and no sign-in on record: WHETHER, and nothing more;
	 * - `ended-open`: on the program now or waiting for a mentor, not waiting to graduate, whose end date is before today;
	 * - `pending-graduation`: waiting to graduate;
	 * - `finishing`: on the program now or waiting for a mentor, not waiting to graduate, whose end date is today or within
	 *   `FINISHING_WITHIN` days.
	 *
	 * "On the program now or waiting for a mentor" is the roster's two groups of the statuses the program counts as active
	 * (`WPCPM_Roster_Index::groups()`). The accounts' meta is read in one go first (`prime_accounts()`).
	 *
	 * @param string $record The school's Institutions record ID.
	 * @param string $key    One of `ONLY_KEYS`.
	 * @return array<string, array> Rows in the roster index's shape.
	 */
	public static function only_rows( string $record, string $key ): array {
		$memo = 'only|' . $record . '|' . $key;

		if ( isset( self::$memo[ $memo ] ) ) {
			return self::$memo[ $memo ];
		}

		$out = array();

		if ( in_array( $key, self::ONLY_KEYS, true ) && WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			$groups = WPCPM_Roster_Index::groups( $record );
			$active = array_merge( (array) ( $groups['current'] ?? array() ), (array) ( $groups['waiting'] ?? array() ) );
			$today  = self::today();
			$until  = gmdate( 'Y-m-d', (int) strtotime( $today . ' UTC' ) + self::FINISHING_WITHIN * DAY_IN_SECONDS );

			if ( self::ONLY_NO_SIGN_IN === $key ) {
				self::prime_accounts( $record );
			}

			// The students waiting to graduate are counted under their own fact alone, whichever group holds them; every
			// other list is of students on the program now or waiting for a mentor (fix round 1, B3).

			foreach ( $active as $id => $row ) {
				$status  = trim( (string) ( $row['status'] ?? '' ) );
				$pending = WPCPM_Program::STATUS_PENDING === $status;
				$end     = self::day_of( $row['end'] ?? '' );
				$user_id = (int) ( $row['user_id'] ?? 0 );

				switch ( $key ) {
					case self::ONLY_NO_SIGN_IN:
						$in = ! $pending && self::unsigned( $user_id );
						break;
					case self::ONLY_ENDED_OPEN:
						$in = ! $pending && '' !== $end && $end < $today;
						break;
					case self::ONLY_PENDING:
						$in = $pending;
						break;
					default:
						$in = ! $pending && '' !== $end && $end >= $today && $end <= $until;
				}

				if ( $in ) {
					$out[ (string) $id ] = $row;
				}
			}
		}

		self::$memo[ $memo ] = $out;

		return $out;
	}

	/**
	 * Roster rows without the students waiting to graduate: "on the program now" and "waiting for a mentor" on this page
	 * never count them (fix round 1, B3), since they have a figure and a fact of their own.
	 *
	 * @param array $rows Rows in the roster index's shape.
	 * @return array The same rows less those whose status is Pending graduation, keys kept.
	 */
	private static function not_pending( array $rows ): array {
		return array_filter(
			$rows,
			static function ( $row ): bool {
				return ! is_array( $row ) || WPCPM_Program::STATUS_PENDING !== trim( (string) ( $row['status'] ?? '' ) );
			}
		);
	}

	/**
	 * Whether a roster row's account is one the site can say has not signed in yet (fix round 1, B2): the account exists,
	 * holds the Student role and an address that is an address (the student card's own conditions,
	 * `WPCPM_Institution_Resend::render()`), the site records sign-ins (`WPCPM_Sign_Ins::since()`), the account was made
	 * at or after the moment the record began, so a sign-in it made would be on record, and none is. WHETHER, and nothing
	 * more: `user_registered` is read for this comparison only and printed nowhere.
	 *
	 * @param int $user_id The roster row's account, or 0.
	 * @return bool
	 */
	private static function unsigned( int $user_id ): bool {
		$since = WPCPM_Sign_Ins::since();
		$user  = $user_id > 0 && $since > 0 ? get_userdata( $user_id ) : false;

		if ( ! $user instanceof WP_User || ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT ) || ! is_email( (string) $user->user_email ) ) {
			return false;
		}

		$made = strtotime( (string) $user->user_registered . ' UTC' );

		return false !== $made && $made >= $since && ! WPCPM_Sign_Ins::has_signed_in( $user_id );
	}

	/**
	 * The earliest day the students waiting to graduate were first seen so, from their stamps
	 * (`WPCPM_Students_Sync::META_PENDING`): 0 when none has a stamp, or when the earliest stamp says the student was
	 * already waiting before stamping began, since "began on" would then not be true.
	 *
	 * @param array  $rows   The students waiting to graduate, as `only_rows()` gives them.
	 * @param string $record The school's Institutions record ID.
	 * @return int A Unix timestamp, or 0.
	 */
	private static function longest_wait( array $rows, string $record ): int {
		self::prime_accounts( $record );

		$earliest = 0;
		$before   = false;

		foreach ( $rows as $row ) {
			$user_id = (int) ( $row['user_id'] ?? 0 );
			$stamp   = $user_id > 0 ? get_user_meta( $user_id, WPCPM_Students_Sync::META_PENDING, true ) : '';

			if ( ! is_array( $stamp ) || empty( $stamp['at'] ) ) {
				continue;
			}

			if ( 0 === $earliest || (int) $stamp['at'] < $earliest ) {
				$earliest = (int) $stamp['at'];
				$before   = ! empty( $stamp['before'] );
			}
		}

		return $before ? 0 : $earliest;
	}

	/**
	 * Read the meta of the school's account holders in one go (`cache_users()`), once a request for a school, so a list
	 * that asks each student's meta asks the cache.
	 *
	 * @param string $record The school's Institutions record ID.
	 */
	private static function prime_accounts( string $record ): void {
		$memo = 'accounts|' . $record;

		if ( '' === $record || isset( self::$memo[ $memo ] ) ) {
			return;
		}

		self::$memo[ $memo ] = array();

		$ids = array();

		foreach ( WPCPM_Roster_Index::rows( $record ) as $row ) {
			$user_id = is_array( $row ) ? (int) ( $row['user_id'] ?? 0 ) : 0;

			if ( $user_id > 0 ) {
				$ids[ $user_id ] = $user_id;
			}
		}

		if ( array() !== $ids ) {
			cache_users( array_values( $ids ) );
		}
	}

	/**
	 * A roster date as `Y-m-d`, or '' when it is not a real day.
	 *
	 * @param mixed $date The stored value.
	 * @return string
	 */
	private static function day_of( $date ): string {
		$date = is_scalar( $date ) ? trim( (string) $date ) : '';

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}

		return $date;
	}

	/**
	 * Forget what `views_for()` and `waiting_items()` worked out in this request. Only the suites call it, between
	 * scenes; no code of the plugin draws the page twice in one request today.
	 */
	public static function forget(): void {
		self::$memo = array();
	}

	/**
	 * The memo's key for a question about a record, a reader and whether they can manage the program.
	 *
	 * @param string  $what       'views' or 'items'.
	 * @param string  $record     Institutions record ID.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @return string
	 */
	private static function memo_key( string $what, string $record, WP_User $viewer, bool $can_manage ): string {
		return $what . '|' . $record . '|' . (int) $viewer->ID . '|' . ( $can_manage ? '1' : '0' );
	}

	/**
	 * Render the dashboard.
	 *
	 * @param array $atts Shortcode or block attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		wp_enqueue_style( self::STYLE );

		// Every form on this page prints `data-wpcpm-once`, and until now no screen the page is
		// drawn on loaded the script that reads it, so the guard was inert exactly where it
		// mattered most: two presses of Upload filed two agreements. Registered the way the
		// calendar registers it, so whichever runs first wins and the handle stays one.
		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( 'wpcpm-forms' );

		// A part of this page that folds, Enroll students at `#wpcpm-import` among them, is a
		// `<details>` inside a section whose id is its anchor, and a browser that follows a link to
		// a closed one leaves it closed, the sample list's link hidden inside it. The dashboards'
		// opener, the one the Sponsor Dashboard and the Administrator Dashboard load, opens the part
		// a link lands on.
		WPCPM_Dashboard_Card::enqueue_opener();

		if ( ! is_user_logged_in() ) {
			return self::notice(
				__( 'Log in to see your institution on this site.', 'wpcredits-program-manager' ),
				wp_login_url( self::current_url() ),
				__( 'Log in', 'wpcredits-program-manager' )
			);
		}

		$viewer     = wp_get_current_user();
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );

		// Asked to choose a school, or drawn one: `choosing()`, the one decision `menu_args()` asks too.
		$choosing = self::choosing( $viewer, $can_manage );

		if ( $choosing ) {
			// No school, so no record, no counts, no card's context: the views a school may have, the Overview current.
			$school = array(
				'record'  => '',
				'view'    => self::VIEW_OVERVIEW,
				'views'   => self::choice_views(),
				'counts'  => array(),
				'profile' => array(),
				'context' => array(),
			);
		} else {
			$school = self::school( $viewer, $can_manage );

			if ( is_string( $school ) ) {
				return $school;
			}
		}

		$record  = (string) $school['record'];
		$view    = (string) $school['view'];
		$views   = (array) $school['views'];
		$profile = (array) $school['profile'];
		$context = (array) $school['context'];

		// No control from an earlier render waits for this page's head.
		self::$viewing = '';

		// The page names itself to the switch of the shared parts while it draws (design 3.5), which lists it as live.
		WPCPM_UI::enter( 'institution' );

		try {
			ob_start();

			// The frame: the menu down the side, a view per item, and the main area as wide as the window, as the
			// Sponsor Dashboard's and the Administrator Dashboard's.
			WPCPM_Dashboard_Frame::open( self::frame_args( $view, $record, $views, (array) $school['counts'], $can_manage ) );

			// The root as before, which `bin/test-ui.php` pins line for line: the view is the menu's current item.
			echo '<div class="wpcpm-dashboard wpcpm-institution' . esc_attr( WPCPM_UI::root_class() ) . '">';

			// The viewer's own account, never the institution being looked at: chrome rather than
			// content, which is why it sits above the head and outside every view. Where a member
			// actually is is the only place a prompt to protect the account is seen, since members
			// are routed away from wp-admin the moment they sign in.
			WPCPM_Two_Factor::prompt( $viewer );

			if ( '' !== $atts['title'] ) {
				echo '<h2 class="wpcpm-dashboard__title">' . esc_html( $atts['title'] ) . '</h2>';
			}

			if ( $choosing ) {
				// The choice is the whole main area: no view is drawn.
				self::render_choice();
			} else {
				if ( $can_manage ) {
					// One small control at the end of the head's first row (design 7), on every view.
					ob_start();
					self::render_switcher( $record, $view );
					self::$viewing = (string) ob_get_clean();
				}

				if ( self::VIEW_OVERVIEW === $view ) {
					self::render_overview( $record, $profile, $context, $viewer, $can_manage, $views );
				} else {
					self::render_view( $view, $record, $context, $viewer );
				}
			}

			echo '</div>';

			WPCPM_Dashboard_Frame::close();
		} finally {
			WPCPM_UI::leave();
		}

		return (string) ob_get_clean();
	}

	/**
	 * Whether the reader is asked to choose a school rather than drawn one: the page's decision, and the menu's
	 * (`menu_args()`), so the two never differ. An Administrator whose address names no school, or one that is not in
	 * the index, and who belongs to none is asked which school to look at, rather than drawn the first school with a
	 * member (design 7). Decided before the record is resolved: `resolve_institution()` and its fallback stay as they
	 * are for the handlers and the exports. Nothing is stored, so the choice is asked again whenever the address names
	 * no school (the controller's ruling 3). With fewer than two schools there is nothing to choose, and the page is as
	 * before.
	 *
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @return bool
	 */
	private static function choosing( WP_User $viewer, bool $can_manage ): bool {
		return $can_manage && '' === self::requested_record() && '' === (string) WPCPM_Institution_Members::institution_of( $viewer ) && count( WPCPM_Institution_Roster::switcher_options() ) > 1;
	}

	/**
	 * The menu for a page of its own drawn in this page's frame: the Institution guide and the program's updates for
	 * institutions. The same items and counts as the page's own for the same reader, with that page marked as where
	 * the reader is: a school's own reader gets their own school's, whatever the address says; an Administrator the
	 * school the page would draw them, the one the address names, carried by the menu's links; an Administrator the
	 * page would ask to choose gets the views a school may have, without counts, never another school's. The Sponsor
	 * Dashboard's twin is `WPCPM_Sponsors_Dashboard::menu_args()`.
	 *
	 * @param string $view `VIEW_GUIDE` or `VIEW_UPDATES`; anything else is `VIEW_GUIDE`.
	 * @return array What `WPCPM_Dashboard_Frame::open()` takes.
	 */
	public static function menu_args( string $view ): array {
		$view   = in_array( $view, array( self::VIEW_GUIDE, self::VIEW_UPDATES ), true ) ? $view : self::VIEW_GUIDE;
		$viewer = wp_get_current_user();
		$manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$choose = self::choosing( $viewer, $manage );
		$record = $choose ? '' : (string) WPCPM_Institution_Roster::resolve_institution( $viewer, $manage );

		// A reader the policy gives no claim on the school is drawn the page's "nothing to show", as a reader with no
		// school is (`school()`), so their menu is that reader's too: no view, no count, no link to the school.
		if ( '' !== $record ) {
			$claim  = self::claim( $record, $viewer );
			$record = empty( $claim['allowed'] ) ? '' : $record;
		}

		if ( '' === $record ) {
			// No school: an Administrator the page asks to choose is listed the views a school may have; anybody else,
			// an Administrator for whom no school resolves included, is drawn the page's "nothing to show", so no view.
			return self::frame_args( $view, '', $choose ? self::choice_views() : array(), array(), $manage );
		}

		return self::frame_args(
			$view,
			$record,
			self::views_for( $record, $viewer, $manage ),
			self::waiting_by_view( self::waiting_items( $record, $viewer, $manage, false ) ),
			$manage
		);
	}

	/**
	 * Whether the viewer has any claim on the school, and on which ground: the policy's decision, the one `school()`
	 * draws the page or the notice by, and the one `menu_args()` asks, so the guide's menu never lists a view whose
	 * page refuses the reader.
	 *
	 * @param string  $record The school's Institutions record ID.
	 * @param WP_User $viewer The person looking.
	 * @return array The policy's decision (`allowed`, `ground` and the rest).
	 */
	private static function claim( string $record, WP_User $viewer ): array {
		// The one call that says whether this viewer has any claim on this institution, and on
		// which ground. ACT_AGREEMENT rather than ACT_VIEW_ROSTER because it is the only
		// ungated action: a member whose agreement is outstanding still has a claim, and that
		// member is exactly who the locked branch below is drawn for. Asking the policy rather
		// than comparing the viewer's stamp with the resolved record is also what keeps the
		// only institution-to-institution comparison in this module inside the policy.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_institution( $record ),
			$viewer
		);

		// A tutor has no claim through the agreement, whose ground is a representative's and a
		// manager's, and has one through the view of the roster, which a settled agreement opens and an
		// unsettled one closes: so a tutor of a school whose agreement is not settled is refused here, as
		// somebody with no claim at all, and the locked page's agreement panel is never drawn for them.
		if ( empty( $decision['allowed'] ) ) {
			$decision = WPCPM_Institution_Policy::decide(
				WPCPM_Institution_Policy::ACT_VIEW_ROSTER,
				WPCPM_Institution_Policy::subject_institution( $record ),
				$viewer
			);
		}

		return $decision;
	}

	/**
	 * The school a reader's page is drawn for, and what the page needs of it, or the notice a reader with no claim on
	 * any school is drawn: the school `WPCPM_Institution_Roster::resolve_institution()` names, the policy's answer on
	 * it, the locked branch, the view and the views, what waits, and what every card is handed.
	 *
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @return array|string Each `record`, `view`, `views`, `counts`, `profile` and `context`; or the notice's markup.
	 */
	private static function school( WP_User $viewer, bool $can_manage ) {
		$record = WPCPM_Institution_Roster::resolve_institution( $viewer, $can_manage );

		if ( '' === $record ) {
			// `WPCPM_Dashboards`, and not a sentence of this page's own: the three dashboards
			// answer the same question, and two copies of it are two wordings to keep true. It
			// names no institution, which is the point - a page that told a stranger which school
			// it would have shown would be a membership oracle, the shape
			// `WPCPM_Institution_Policy::refusal()` exists to avoid everywhere else.
			return self::notice( WPCPM_Dashboards::nothing_to_show( 'institutions', $can_manage ), '', '', false );
		}

		$decision = self::claim( $record, $viewer );

		if ( empty( $decision['allowed'] ) ) {
			// Word for word what a viewer with no institution at all is told above, and that is
			// deliberate: the difference between "there is nothing here" and "this one is not
			// yours" is exactly the fact the fence is keeping.
			return self::notice( WPCPM_Dashboards::nothing_to_show( 'institutions', $can_manage ), '', '', false );
		}

		$settled = WPCPM_Institution_Agreement::is_settled( $record );

		// Spec 7.4: the gate hides the dashboard from the institution's own people and never
		// from the manager, who is the person it is waiting for (decision 6, open question 16).
		$locked = ! $can_manage && ! $settled;

		// The viewer's own profile stamp is offered to the header only when the policy matched
		// them as a member, which is what makes that stamp this institution's. It is the
		// fallback and not the source: `profile()` reads the index first, for the reason it
		// records.
		$member  = in_array( $decision['ground'], array( WPCPM_Institution_Policy::GROUND_MEMBER, WPCPM_Institution_Policy::GROUND_TUTOR ), true );
		$profile = self::profile( $record, $member ? $viewer : null );

		// The view the address asks for, while it is one of the reader's: a locked school, a tutor and an Administrator
		// each reach only the views their menu lists (ruling P5), and every other address draws the Overview.
		$view  = self::view( $record, $viewer, $can_manage );
		$views = self::views_for( $record, $viewer, $can_manage );

		// The locked branch, whatever the menu worked out: a school whose agreement is not settled is drawn the
		// Overview or the Agreement and nothing else (Review Focus 3).
		if ( $locked && ! in_array( $view, array( self::VIEW_OVERVIEW, self::VIEW_AGREEMENT ), true ) ) {
			$view = self::VIEW_OVERVIEW;
		}

		$counts  = self::waiting_by_view( self::waiting_items( $record, $viewer, $can_manage, false ) );
		$context = self::context( $record, $can_manage );

		return array(
			'record'  => $record,
			'view'    => $view,
			'views'   => $views,
			'counts'  => $counts,
			'profile' => $profile,
			'context' => $context,
		);
	}

	/**
	 * The choice an Administrator with no school chosen is drawn (design 7), in the frame `render()` opens over the
	 * views a school may have (`choice_views()`), with no counts, the Overview current and each item the view's own
	 * address naming no school, so every item lands on the same choice: the head, "Choose an institution" and one
	 * sentence, then "Viewing as institution", which keeps the view asked for, so the school chosen opens on it. No
	 * card is drawn and nothing is read for a school.
	 */
	private static function render_choice(): void {
		self::render_head(
			esc_html__( 'Choose an institution', 'wpcredits-program-manager' ),
			__( 'Pick the school whose page you want to see. Only Administrators see this.', 'wpcredits-program-manager' )
		);

		self::render_switcher( '', self::asked_view() );
	}

	/**
	 * The view a reader is drawn: the one the address asks for (`asked_view()`, old addresses inferred) when it is
	 * among the views the reader may open (`views_for()`), and the Overview otherwise (the planner's ruling P5). So a
	 * locked school asking for its roster, a tutor asking for Enroll students and an Administrator asking for Events
	 * are each drawn the Overview, and no card of the view they asked for is called. The Sponsor Dashboard's twin is
	 * `WPCPM_Sponsors_Dashboard::view()`.
	 *
	 * @param string  $record     The school's Institutions record ID, resolved by the page.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @return string A `VIEW_*` key.
	 */
	public static function view( string $record, WP_User $viewer, bool $can_manage ): string {
		$asked = self::asked_view();

		return in_array( $asked, self::views_for( $record, $viewer, $can_manage ), true ) ? $asked : self::VIEW_OVERVIEW;
	}

	/**
	 * The menu: the reader's views in their order (`views_for()`), each beside the count of what waits in it as the
	 * frame's red tag, the Overview's the sum; then Program updates while institutions may read one; under Help the
	 * Institution guide, the program's Slack channel and the assigned Administrator's address. The Ask box is the
	 * frame's own. An Administrator's links keep the school they look at; a member's name none. The Sponsor
	 * Dashboard's twin is `WPCPM_Sponsors_Dashboard::frame_args()`.
	 *
	 * @param string   $view       The view being read, a `VIEW_*` key, `VIEW_GUIDE` or `VIEW_UPDATES`.
	 * @param string   $record     The school's Institutions record ID.
	 * @param string[] $views      The reader's views, `views_for()`.
	 * @param array    $counts     What waits in each view, `waiting_by_view()`.
	 * @param bool     $can_manage Whether the viewer can manage the program.
	 * @return array What `WPCPM_Dashboard_Frame::open()` takes.
	 */
	private static function frame_args( string $view, string $record, array $views, array $counts, bool $can_manage ): array {
		// The school an Administrator is looking at travels with every link; a member's own page needs none.
		$managed = $can_manage ? $record : '';
		$badge   = static function ( int $n ): string {
			/* translators: %s: how many things wait for the school's people. */
			return $n > 0 ? sprintf( _n( '%s waits', '%s wait', $n, 'wpcredits-program-manager' ), number_format_i18n( $n ) ) : '';
		};
		$names   = array(
			self::VIEW_OVERVIEW       => array( __( 'Overview', 'wpcredits-program-manager' ), 'home' ),
			self::VIEW_STUDENTS       => array( __( 'Students', 'wpcredits-program-manager' ), 'users' ),
			self::VIEW_ENROLL         => array( __( 'Enroll students', 'wpcredits-program-manager' ), 'profile' ),
			self::VIEW_REPORTS        => array( __( 'Semester reports', 'wpcredits-program-manager' ), 'chart' ),
			self::VIEW_INSTITUTION    => array( __( 'Your institution', 'wpcredits-program-manager' ), 'building' ),
			self::VIEW_AGREEMENT      => array( __( 'Agreement', 'wpcredits-program-manager' ), 'file' ),
			self::VIEW_EVENTS         => array( __( 'Events', 'wpcredits-program-manager' ), 'calendar' ),
			self::VIEW_ADMINISTRATORS => array( __( 'For Administrators', 'wpcredits-program-manager' ), 'eye' ),
		);

		$items = array();

		foreach ( $views as $key ) {
			if ( ! isset( $names[ $key ] ) ) {
				continue;
			}

			// The Overview's count is everything that waits; a view's is what waits in it.
			$n = self::VIEW_OVERVIEW === $key ? (int) array_sum( $counts ) : ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 );

			$items[] = WPCPM_Dashboard_Frame::item(
				$names[ $key ][0],
				self::VIEW_STUDENTS === $key ? self::students_url( $managed ) : self::view_url( $key, $managed ),
				$names[ $key ][1],
				array(
					'current' => $key === $view,
					'badge'   => $badge( $n ),
					'tone'    => 'red',
				)
			);
		}

		// The program's updates for institutions, after the views: the newest one, with the school an Administrator
		// is looking at. Only while there is one.
		$newest = class_exists( 'WPCPM_Updates' ) ? WPCPM_Updates::posts( 1, 'institution' ) : array();

		if ( ! empty( $newest ) ) {
			$items[] = WPCPM_Dashboard_Frame::item( __( 'Program updates', 'wpcredits-program-manager' ), self::keep_institution( (string) get_permalink( $newest[0] ), $managed ), 'inbox', array( 'current' => self::VIEW_UPDATES === $view ) );
		}

		$help   = array();
		$guides = class_exists( 'WPCPM_Handbook_Assistant' ) ? (array) WPCPM_Handbook_Assistant::guides() : array();
		$guide  = isset( $guides['institution'] ) && is_array( $guides['institution'] ) ? $guides['institution'] : array();
		$url    = isset( $guide['url'] ) ? trim( (string) $guide['url'] ) : '';

		if ( '' !== $url ) {
			// The site's own Institution guide draws in this page's frame, so it keeps the school; the handbook's
			// page, the address until this site publishes its own, is another site's and is linked as it is.
			$own = get_page_by_path( WPCPM_Handbook_Assistant::INSTITUTION_GUIDE_PATH );

			if ( $own instanceof WP_Post && 'publish' === $own->post_status && (string) get_permalink( $own ) === $url ) {
				$url = self::keep_institution( $url, $managed );
			}

			$help[] = WPCPM_Dashboard_Frame::item( __( 'Institution guide', 'wpcredits-program-manager' ), $url, 'book', array( 'current' => self::VIEW_GUIDE === $view ) );
		}

		$slack = isset( $guide['slack'] ) ? trim( (string) $guide['slack'] ) : '';

		if ( '' !== $slack ) {
			$help[] = WPCPM_Dashboard_Frame::item( __( '#wpcredits on Slack', 'wpcredits-program-manager' ), $slack, 'slack' );
		}

		// Write to the program: the Administrator the school is assigned to, while one is and has an address.
		$contact = '' !== $record ? WPCPM_Institution_Assignment::contact_of( $record ) : null;
		$email   = is_array( $contact ) && isset( $contact['email'] ) ? sanitize_email( (string) $contact['email'] ) : '';

		if ( '' !== $email ) {
			$help[] = WPCPM_Dashboard_Frame::item( __( 'Write to the program', 'wpcredits-program-manager' ), 'mailto:' . $email, 'email' );
		}

		$pages = array(
			self::VIEW_GUIDE   => self::FRAME_PAGE . '-guide',
			self::VIEW_UPDATES => self::FRAME_PAGE . '-updates',
		);

		return array(
			'page'   => isset( $pages[ $view ] ) ? $pages[ $view ] : self::FRAME_PAGE,
			'label'  => self::title(),
			'groups' => array(
				array(
					'heading' => '',
					'items'   => $items,
				),
			),
			'help'   => $help,
		);
	}

	/**
	 * The Students item's address. While the address names a student (builder's call 3: no back links), it is the
	 * roster as the reader left it, the roster's filters kept; otherwise the view's own address.
	 *
	 * @param string $managed The school an Administrator is looking at, or ''.
	 * @return string Not escaped.
	 */
	private static function students_url( string $managed ): string {
		$view = 'WPCPM_Institution_Roster_View';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read, which changes nothing.
		if ( ! class_exists( $view ) || ! isset( $_GET[ WPCPM_Institution_Roster_View::ARG_STUDENT ] ) ) {
			return self::view_url( self::VIEW_STUDENTS, $managed );
		}

		$args = array();

		foreach ( array( WPCPM_Institution_Roster_View::ARG_COHORT, WPCPM_Institution_Roster_View::ARG_STATUS, WPCPM_Institution_Roster_View::ARG_SEARCH ) as $arg ) {
			$args[ $arg ] = trim( (string) WPCPM_Request::text( $arg ) );
		}

		return self::view_url( self::VIEW_STUDENTS, $managed, $args );
	}

	/**
	 * The page's name, the page's one H1, when the theme leaves the page to the frame; the theme's own template
	 * prints it otherwise. The Sponsor Dashboard's twin is `WPCPM_Sponsors_Dashboard::page_name()`.
	 *
	 * @return string Markup, escaped, or ''.
	 */
	private static function page_name(): string {
		if ( ! WPCPM_Dashboard_Frame::owns_page( self::FRAME_PAGE ) ) {
			return '';
		}

		return sprintf( '<h1 class="wpcpm-head__eyebrow">%s</h1>', esc_html( self::title() ) );
	}

	/**
	 * The head of a view: the site icon or nothing beside it, then the page's H1, the title, one sentence under it,
	 * the facts and the actions. While an Administrator's compact Viewing as institution waits (`$viewing`, the parts
	 * on), the head's first row is the H1 and, at its end, that control, printed once.
	 *
	 * @param string $title   The title, as markup the caller escaped (`esc_html()`, or `WPCPM_UI::breakable()` for a
	 *                        name somebody typed).
	 * @param string $lede    The sentence under it, plain text, or ''.
	 * @param string $actions Markup, escaped by the caller, or ''.
	 * @param string $media   Markup beside the copy, escaped by the caller, or ''.
	 * @param string $facts   Markup under the title, escaped by the caller, or ''.
	 */
	private static function render_head( string $title, string $lede, string $actions = '', string $media = '', string $facts = '' ): void {
		printf( '<header class="wpcpm-head%s">', '' !== $media ? ' wpcpm-head--logo' : '' );
		echo $media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller.
		echo '<div class="wpcpm-head__copy">';

		if ( '' !== self::$viewing ) {
			echo WPCPM_UI::head_row( self::page_name() . self::$viewing ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page_name() and by WPCPM_Dashboards::render_switcher().
			self::$viewing = '';
		} else {
			echo self::page_name(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page_name().
		}
		printf( '<h2 class="wpcpm-head__title">%s</h2>', $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller.

		if ( '' !== $lede ) {
			printf( '<p class="wpcpm-head__lede">%s</p>', esc_html( $lede ) );
		}

		echo $facts . $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller.
		echo '</div></header>';
	}

	/**
	 * The Overview, as the Sponsor Dashboard's is: the school's head, its figures, what waits and what is news for
	 * it, and its contact at the program (design 5).
	 *
	 * @param string  $record     The school's Institutions record ID.
	 * @param array   $profile    Facts from `profile()`.
	 * @param array   $context    What every card is handed (`context()`): the read line's time.
	 * @param WP_User $viewer     The person looking.
	 * @param bool    $can_manage Whether the viewer can manage the program.
	 * @param array   $views      The views the reader may open (`views_for()`).
	 */
	private static function render_overview( string $record, array $profile, array $context, WP_User $viewer, bool $can_manage, array $views ): void {
		$managed = $can_manage ? $record : '';

		self::render_overview_head( $profile, $managed, $can_manage, in_array( self::VIEW_ENROLL, $views, true ) );

		// The figures are the roster's, so they are drawn only for a reader who may open it.
		if ( in_array( self::VIEW_STUDENTS, $views, true ) ) {
			self::render_figures( $record, $context );
		}

		self::render_waiting( self::waiting_items( $record, $viewer, $can_manage ), $managed );

		// What the students work on is the roster's, so it is drawn only for a reader who may open it, as the facts above
		// (1.123.98): never for a school whose agreement is not settled.
		if ( self::only_offered( self::ONLY_PENDING, $record, $viewer, $can_manage ) ) {
			self::render_work( $record );
		}

		echo self::contact_block( $record ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by contact_block(), which escapes what it interpolates.

		// An old link names only an anchor on the bare page, which is now this view: the map sends it on.
		echo self::anchor_views_script( $views, $managed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG and JSON_HEX_AMP by anchor_views_script().
	}

	/**
	 * Where an old link that names only an anchor goes: each anchor of each card of each view the reader may open,
	 * the Overview's left out, to the address of the view that holds it. Links sent before the page had views (in
	 * mails, bookmarks, other pages) name an anchor on the bare page, `#wpcpm-import`, and the bare page is now the
	 * Overview, which holds no card; the dashboards' opener follows this map. Built from the reader's own views, so
	 * it never names a card the reader would not be drawn, and from the one school an Administrator is looking at. The
	 * live page's module anchors (`MODULE_ANCHORS`) follow their view's cards, under the same test.
	 *
	 * @param string[] $views   The reader's views, `views_for()`.
	 * @param string   $managed The school an Administrator is looking at, or '' for a member's own page.
	 * @return array<string, string> Anchor to the view's address, not escaped, in the order of `VIEW_CARDS`.
	 */
	public static function anchor_views( array $views, string $managed ): array {
		$map = array();

		foreach ( self::VIEW_CARDS as $view => $cards ) {
			if ( ! in_array( $view, $views, true ) ) {
				continue;
			}

			$url = self::view_url( $view, $managed );

			if ( '' === $url ) {
				continue;
			}

			foreach ( array_merge( $cards, self::MODULE_ANCHORS[ $view ] ?? array() ) as $card ) {
				$map[ 'wpcpm-' . $card ] = $url;
			}
		}

		return $map;
	}

	/**
	 * The map `anchor_views()` builds, as data for the dashboards' opener: a `<script type="application/json">` no
	 * browser runs, its text encoded with `JSON_HEX_TAG | JSON_HEX_AMP`, so no address in it can close the tag or
	 * start an entity. Nothing when the map is empty.
	 *
	 * @param string[] $views   The reader's views, `views_for()`.
	 * @param string   $managed The school an Administrator is looking at, or ''.
	 * @return string The tag, or ''.
	 */
	public static function anchor_views_script( array $views, string $managed ): string {
		$map  = self::anchor_views( $views, $managed );
		$json = array() === $map ? false : wp_json_encode( $map, JSON_HEX_TAG | JSON_HEX_AMP );

		if ( ! is_string( $json ) ) {
			return '';
		}

		return '<script type="application/json" class="wpcpm-anchor-views">' . $json . '</script>';
	}

	/**
	 * The Overview's head: the school's site icon at 48px as before, its name, one line of facts (where it is, its
	 * website, the contact the program has on record and, for an Administrator, the program's stage), and Enroll
	 * students while the reader may open it (the planner's ruling P3).
	 *
	 * **The stage is the program's, not the school's.** The stage is the program's own word for where an institution
	 * stands in its pipeline (Confirmed, Agreement Sent), which tells a school nothing it can act on and reads as the
	 * program's bookkeeping. It is drawn for a viewer who can manage the program, who reads this page through the
	 * switcher to see what the school sees and what the program holds about it, and for nobody else.
	 *
	 * @param array  $profile    Facts from `profile()`.
	 * @param string $managed    The school an Administrator is looking at, or ''.
	 * @param bool   $can_manage Whether the viewer can manage the program.
	 * @param bool   $enroll     Whether Enroll students is in the reader's menu.
	 */
	private static function render_overview_head( array $profile, string $managed, bool $can_manage, bool $enroll ): void {
		$name = '' !== $profile['name']
			? (string) $profile['name']
			// Two records in the base carry no name at all. Saying so is better than an empty
			// line, and the manager screen is where it gets fixed.
			: __( 'Unnamed institution', 'wpcredits-program-manager' );

		$website = self::website_url( $profile['website'] );

		// The institution's own site icon beside its name, as the header had it (builder's call 10): resolved from the
		// website the program records and cached, printed only when the site answered with one, and taken off the
		// page by the reader's browser when it cannot load it (`site_icon()` says why).
		$icon  = self::site_icon( $website );
		$media = '' !== $icon
			? sprintf( '<img class="wpcpm-institution__icon" src="%1$s" alt="" width="48" height="48" loading="lazy" decoding="async" onerror="this.remove()" />', esc_url( $icon ) )
			: '';

		// Each fact its own, in the order somebody reads them, a missing one simply not there: no separator is left
		// behind, since each fact draws its own (design 2.10).
		$facts = array();
		$place = array_values( array_filter( array( $profile['city'], $profile['country_name'] ) ) );

		if ( ! empty( $place ) ) {
			$facts[] = implode( ', ', $place );
		}

		if ( '' !== $website ) {
			$facts[] = array(
				'html' => sprintf(
					'<a href="%1$s" rel="external noopener">%2$s</a>',
					esc_url( $website ),
					esc_html( (string) preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) )
				),
			);
		}

		if ( '' !== $profile['contact_person'] ) {
			/* translators: %s: the institution's named contact person. */
			$facts[] = sprintf( __( 'Contact: %s', 'wpcredits-program-manager' ), $profile['contact_person'] );
		}

		if ( $can_manage && '' !== $profile['stage'] ) {
			/* translators: %s: the Airtable Current Stage, e.g. "Confirmed". */
			$facts[] = array( 'html' => esc_html( sprintf( __( 'Stage: %s', 'wpcredits-program-manager' ), $profile['stage'] ) ) );
		}

		$line = WPCPM_UI::facts_line( $facts, 'wpcpm-head__facts' );

		$actions = $enroll
			? '<div class="wpcpm-head__actions">' . WPCPM_UI::button(
				array(
					'kind'  => 'secondary',
					'href'  => self::card_url( 'import', $managed ),
					'label' => __( 'Enroll students', 'wpcredits-program-manager' ),
					// The page is the front end's, and so is every part on it.
					'side'  => 'front',
				)
			) . '</div>'
			: '';

		self::render_head( WPCPM_UI::breakable( $name ), '', $actions, $media, $line );
	}

	/**
	 * The figures, as the Sponsor Dashboard draws them, for the cohort the Students view opens on: who signed up, who
	 * graduated, who is waiting to graduate, who is on the program now and who waits for a mentor (1.123.98: five, each
	 * student counted under one of the last four at most). A figure at zero is left out; with none, there is no band.
	 * Under it, the roster's read line, once.
	 *
	 * @param string $record  The school's Institutions record ID.
	 * @param array  $context What every card is handed (`context()`).
	 */
	private static function render_figures( string $record, array $context ): void {
		$cohort = WPCPM_Institution_Roster_View::opening_cohort( $record );
		$counts = WPCPM_Cohort::participation( WPCPM_Roster_Index::rows( $record ), $cohort );
		$groups = WPCPM_Roster_Index::groups( $record, $cohort );
		/* translators: %s: the cohort, e.g. "January to June 2026". */
		$base = sprintf( __( 'in %s', 'wpcredits-program-manager' ), WPCPM_Cohort::label( $cohort ) );
		// The figures are disjoint (1.123.98, fix round 1, B3): a student waiting to graduate is counted under the
		// participation bucket the semester report calls "Waiting to graduate" alone, beside the graduates, and not as on
		// the program now or waiting for a mentor, whichever group the roster keeps them in.
		$cells = array(
			array( (int) $counts['signed_up'], __( 'signed up', 'wpcredits-program-manager' ) ),
			array( (int) $counts['graduated'], __( 'graduated', 'wpcredits-program-manager' ) ),
			array( (int) $counts['pending'], __( 'waiting to graduate', 'wpcredits-program-manager' ) ),
			array( count( self::not_pending( (array) ( $groups['current'] ?? array() ) ) ), __( 'on the program now', 'wpcredits-program-manager' ) ),
			array( count( self::not_pending( (array) ( $groups['waiting'] ?? array() ) ) ), __( 'waiting for a mentor', 'wpcredits-program-manager' ) ),
		);
		$cells = array_values(
			array_filter(
				$cells,
				static function ( array $cell ): bool {
					return $cell[0] > 0;
				}
			)
		);

		if ( array() === $cells ) {
			return;
		}

		printf( '<section class="wpcpm-figures wpcpm-figures--%1$d" aria-label="%2$s">', count( $cells ), esc_attr__( 'Your students at a glance', 'wpcredits-program-manager' ) );

		foreach ( $cells as $cell ) {
			printf(
				'<div class="wpcpm-figure"><p class="wpcpm-figure__value">%1$s</p><p class="wpcpm-figure__label">%2$s</p><p class="wpcpm-figure__base">%3$s</p></div>',
				esc_html( number_format_i18n( $cell[0] ) ),
				esc_html( $cell[1] ),
				esc_html( $base )
			);
		}

		echo '</section>';

		$read = isset( $context['read'] ) ? (int) $context['read'] : (int) WPCPM_Roster_Index::read( $record )['read'];
		WPCPM_Institution_Roster_View::read_line( $read, 'wpcpm-dashboard__updated' );
	}

	/**
	 * What waits for the reader, then what is good to know, as two groups of the parts' rows: each row its tag in
	 * words, its title, the sentence that says why and the one way to the card that holds it.
	 *
	 * "Waiting for you" holds only what waits, with the red tag the other dashboards give it, or the one quiet line
	 * "Nothing waits for you." when nothing does. "Good to know" follows only when there is something to say without
	 * waiting: the rows that carry a neutral tag first, then the news, then the school's facts with no tag (1.123.98:
	 * the students who have not signed in yet, those who finish within 30 days, the placements that ended and are still
	 * open, the students waiting to graduate). Within each of those the rows keep the order `waiting_items()` gave them.
	 *
	 * @param array[] $items   What `waiting_items()` returned.
	 * @param string  $managed The school an Administrator is looking at, or ''.
	 */
	private static function render_waiting( array $items, string $managed ): void {
		$waits = array();
		$known = array();
		$news  = array();
		$facts = array();

		foreach ( $items as $item ) {
			if ( self::KIND_WAITS === $item['kind'] ) {
				$waits[] = $item;
			} elseif ( self::KIND_NEWS === $item['kind'] ) {
				$news[] = $item;
			} elseif ( '' === (string) $item['tag'] ) {
				$facts[] = $item;
			} else {
				$known[] = $item;
			}
		}

		self::render_group( 'waiting', __( 'Waiting for you', 'wpcredits-program-manager' ), $waits, $managed, __( 'Nothing waits for you.', 'wpcredits-program-manager' ) );

		// The rows that carry a tag first, in their order, then the school's facts with no tag (1.123.98, fix round 1, B1).
		$known = array_merge( $known, $news, $facts );

		if ( array() !== $known ) {
			self::render_group( 'news', __( 'Good to know', 'wpcredits-program-manager' ), $known, $managed );
		}
	}

	/**
	 * What the school's students work on (1.123.98), under Good to know: the teams of the opening cohort's students with
	 * how many students are on each, most first, in the parts' key and value table, counted as the semester report counts
	 * its Contribution Teams (`WPCPM_Semester_Report::team_counts()`), from the roster's rows; then one muted line: how
	 * many mentors work with the students on the program now (the roster's `current` group without the students waiting
	 * to graduate), and of the opening cohort's students, how many have hours above zero on record. Each piece only when it has something to say; the heading only when one does. Nothing is said about why
	 * hours are missing: the cause is not known.
	 *
	 * @param string $record The school's Institutions record ID.
	 */
	private static function render_work( string $record ): void {
		$rows   = WPCPM_Roster_Index::rows( $record );
		$cohort = WPCPM_Institution_Roster_View::opening_cohort( $record );
		$teams  = WPCPM_Semester_Report::team_counts( $rows, $cohort );
		$groups = WPCPM_Roster_Index::groups( $record );

		$mentors = array();

		foreach ( self::not_pending( (array) ( $groups['current'] ?? array() ) ) as $row ) {
			$name = trim( (string) ( $row['mentor_name'] ?? '' ) );

			if ( '' !== $name ) {
				$mentors[ strtolower( $name ) ] = true;
			}
		}

		$signed = 0;
		$hours  = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || WPCPM_Cohort::key( $row['start'] ?? '' ) !== $cohort || in_array( trim( (string) ( $row['status'] ?? '' ) ), WPCPM_Cohort::NOT_SIGNED_UP, true ) ) {
				continue;
			}

			++$signed;

			// Hours above zero (fix round 1, B4): a zero is no hours to show.
			$logged = trim( (string) ( $row['hours'] ?? '' ) );

			if ( is_numeric( $logged ) && (float) $logged > 0 ) {
				++$hours;
			}
		}

		$lines = array();

		if ( array() !== $mentors ) {
			/* translators: %s: a number of mentors. */
			$lines[] = sprintf( _n( '%s mentor works with your students.', '%s mentors work with your students.', count( $mentors ), 'wpcredits-program-manager' ), number_format_i18n( count( $mentors ) ) );
		}

		if ( $hours > 0 ) {
			/* translators: 1: how many of the cohort's students have hours on record, 2: how many students the cohort has. */
			$lines[] = sprintf( _n( 'Hours are on record for %1$s of %2$s student.', 'Hours are on record for %1$s of %2$s students.', $signed, 'wpcredits-program-manager' ), number_format_i18n( $hours ), number_format_i18n( $signed ) );
		}

		if ( array() === $teams && array() === $lines ) {
			return;
		}

		$id = 'wpcpm-institution-overview-work';

		printf( '<section class="wpcpm-institution__work" aria-labelledby="%s">', esc_attr( $id ) );
		echo WPCPM_UI::heading( __( 'What your students work on', 'wpcredits-program-manager' ), 'h2', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.

		if ( array() !== $teams ) {
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
			$out   = WPCPM_UI::table_open(
				array( $label, $value ),
				array(
					'keyvalue'   => true,
					'labelledby' => $id,
				)
			);

			foreach ( $teams as $team ) {
				$out .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $label, esc_html( (string) $team['team'] ) )
					/* translators: %s: a number of students. */
					. WPCPM_UI::cell( $value, esc_html( sprintf( _n( '%s student', '%s students', (int) $team['count'], 'wpcredits-program-manager' ), number_format_i18n( (int) $team['count'] ) ) ) )
					. WPCPM_UI::row_close();
			}

			echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and above.
		}

		if ( array() !== $lines ) {
			echo WPCPM_UI::intro( implode( ' ', $lines ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.
		}

		echo '</section>';
	}

	/**
	 * One group of the Overview's rows: its heading, then its rows, or its quiet line when it has none.
	 *
	 * @param string  $key     The group's name in its class and its heading's id: `waiting` or `news`.
	 * @param string  $title   The heading.
	 * @param array[] $items   The rows, as `waiting_items()` gives them.
	 * @param string  $managed The school an Administrator is looking at, or ''.
	 * @param string  $quiet   The line for no row, or '' (a group with no row and no line is not drawn).
	 */
	private static function render_group( string $key, string $title, array $items, string $managed, string $quiet = '' ): void {
		$id = 'wpcpm-institution-overview-' . $key;

		printf( '<section class="wpcpm-institution__%1$s" aria-labelledby="%2$s">', esc_attr( $key ), esc_attr( $id ) );
		echo WPCPM_UI::heading( $title, 'h2', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.

		if ( array() === $items ) {
			printf( '<p class="wpcpm-overview__quiet">%s</p></section>', esc_html( $quiet ) );

			return;
		}

		echo WPCPM_UI::list_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup only.

		foreach ( $items as $item ) {
			$line = WPCPM_UI::row(
				array(
					'name'    => (string) $item['title'],
					'tag'     => array(
						'words' => (string) $item['tag'],
						'tone'  => self::KIND_WAITS === $item['kind'] ? 'red' : '',
					),
					'facts'   => array( (string) $item['why'] ),
					'actions' => array(
						WPCPM_UI::button(
							array(
								'kind'  => 'secondary',
								'href'  => self::card_url( (string) $item['card'], $managed, '', (array) $item['args'] ),
								'label' => (string) $item['action'],
								'side'  => 'front',
							)
						),
					),
				)
			);

			echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.
		}

		echo WPCPM_UI::list_close() . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup only.
	}

	/**
	 * A view other than the Overview: its head, then its cards in their order, each drawn open as on the other
	 * dashboards (1.123.80; the roster's groups keep their own rule). A view whose cards print nothing says so in
	 * one line rather than standing empty. A card takes its own message when it draws, so a view draws only its
	 * own cards and leaves every other card's message for the view that holds it.
	 *
	 * @param string  $view    A `VIEW_*` key other than the Overview.
	 * @param string  $record  The school's Institutions record ID.
	 * @param array   $context What every card is handed.
	 * @param WP_User $viewer  The person looking.
	 */
	private static function render_view( string $view, string $record, array $context, WP_User $viewer ): void {
		$leads = self::leads();
		$heads = array(
			self::VIEW_STUDENTS       => array( __( 'Students', 'wpcredits-program-manager' ), __( 'Your students on the program, by cohort and by where they are, with the list you can download.', 'wpcredits-program-manager' ) ),
			self::VIEW_ENROLL         => array( __( 'Enroll students', 'wpcredits-program-manager' ), __( 'Add students to the program, one at a time or from a list.', 'wpcredits-program-manager' ) ),
			self::VIEW_REPORTS        => array( __( 'Semester reports', 'wpcredits-program-manager' ), $leads[ self::MODULE_REPORT ] ),
			self::VIEW_INSTITUTION    => array( __( 'Your institution', 'wpcredits-program-manager' ), __( 'What the program has on record about your institution, and who can see this page.', 'wpcredits-program-manager' ) ),
			self::VIEW_AGREEMENT      => array( __( 'Collaboration Agreement', 'wpcredits-program-manager' ), __( 'The agreement between your institution and the program, and the signed copy on file.', 'wpcredits-program-manager' ) ),
			self::VIEW_EVENTS         => array( __( 'Events', 'wpcredits-program-manager' ), __( 'Events the program\'s sponsors host, to join with your colleagues.', 'wpcredits-program-manager' ) ),
			self::VIEW_ADMINISTRATORS => array( __( 'For Administrators', 'wpcredits-program-manager' ), __( 'Only Administrators see this section.', 'wpcredits-program-manager' ) ),
		);

		$head = isset( $heads[ $view ] ) ? $heads[ $view ] : array( self::title(), '' );

		self::render_head( esc_html( $head[0] ), $head[1] );

		ob_start();

		WPCPM_Dashboard_Card::unfold( true );

		switch ( $view ) {
			case self::VIEW_STUDENTS:
				// The roster, told that the view titles it, so it prints neither a title nor a read line of its own;
				// the view prints the read line once, at the roster's foot (owner, 1.96.5).
				self::card( 'WPCPM_Institution_Roster_View', $record, array_merge( $context, array( 'module_titled' => true ) ) );

				if ( method_exists( 'WPCPM_Institution_Roster_View', 'read_line' ) && method_exists( 'WPCPM_Roster_Index', 'read' ) ) {
					$read = isset( $context['read'] ) ? (int) $context['read'] : (int) WPCPM_Roster_Index::read( $record )['read'];
					// On one student's card, in the list's place, the line is about that student (Gate B, B6).
					$one = WPCPM_Institution_Roster_View::card_student() > 0;
					WPCPM_Institution_Roster_View::read_line( $read, 'wpcpm-dashboard__updated', $one );
				}
				break;

			case self::VIEW_ENROLL:
				self::card( 'WPCPM_Institution_Import_Form', $record, $context );
				break;

			case self::VIEW_REPORTS:
				self::card( 'WPCPM_Semester_Report_Screen', $record, $context );
				break;

			case self::VIEW_INSTITUTION:
				// The school's own details first, above the people who act for it (Institutions release 2).
				self::card( 'WPCPM_Institution_Details_Card', $record, $context );
				self::card( 'WPCPM_Institution_People', $record, $context );
				break;

			case self::VIEW_AGREEMENT:
				// The panel, which draws itself from the summary state (for an Administrator, the banner's sentences first,
				// `banner_sentences()`) and so draws nothing once it is settled, and the card that carries the accepted date.
				self::card( 'WPCPM_Institution_Panel', $record, $context );
				self::card( 'WPCPM_Institution_Agreement_Card', $record, $context );
				break;

			case self::VIEW_EVENTS:
				// The events from the program's sponsors, for a contact of the school on its own page alone
				// (`views_for()`): a Join puts whoever presses it on the event.
				if ( class_exists( 'WPCPM_Sponsor_Event_List' ) ) {
					WPCPM_Sponsor_Event_List::render( $viewer, WPCPM_Sponsor_Event_List::BACK_INSTITUTION );
				}
				break;

			case self::VIEW_ADMINISTRATORS:
				// The talks with the school first, once the site keeps the record of institutions (Institutions off Airtable,
				// release 2; PROVISIONAL, owner question 1): the card asks the policy and the setting itself.
				if ( ! empty( $context['can_manage'] ) ) {
					self::card( 'WPCPM_Institution_Talks', $record, $context );
				}

				// What the program keeps about the school that its own people are not shown, for an Administrator
				// alone, so a member's page never builds it and never reads the history it holds.
				if ( ! empty( $context['can_manage'] ) && class_exists( 'WPCPM_Institution_History' ) ) {
					echo WPCPM_Institution_History::render( $record ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the class, which escapes what it interpolates.
				}
				break;
		}

		WPCPM_Dashboard_Card::unfold( false );

		$cards = trim( (string) ob_get_clean() );

		if ( '' === $cards ) {
			printf( '<p class="wpcpm-overview__quiet">%s</p>', esc_html__( 'Nothing to show here yet.', 'wpcredits-program-manager' ) );

			return;
		}

		echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each card escapes what it prints.
	}

	/**
	 * What every card on this page is handed.
	 *
	 * The filter bar belongs to the roster view, so the roster view owns its argument names
	 * and is asked for the answer rather than second-guessed: two readers of one URL are two
	 * chances to disagree, and a disagreement here reads as "nothing was filtered" rather
	 * than as an error, which is the kind nobody notices. Reading them once, here, is still
	 * worth doing, because the cohort the picker shows, the cohort the strip compares and the
	 * cohort the groups are built from have to be the same one.
	 *
	 * Asked through `method_exists()` for the reason `card()` gives, and a checkout without
	 * the roster view has nothing to filter anyway.
	 *
	 * @param string $record_id  Institutions record ID being rendered.
	 * @param bool   $can_manage Whether the viewer holds CAP_MANAGE.
	 * @return array
	 */
	private static function context( $record_id, $can_manage ) {
		$view = 'WPCPM_Institution_Roster_View';

		return array(
			'can_manage' => (bool) $can_manage,
			'cohort'     => method_exists( $view, 'cohort_from_request' )
				? (string) call_user_func( array( $view, 'cohort_from_request' ) )
				: '',
			'filters'    => method_exists( $view, 'filters_from_request' )
				? (array) call_user_func( array( $view, 'filters_from_request' ) )
				: array(),
			// The read time of the data this page shows, printed at the foot of the page. The
			// index's own and never "now": the whole point of a per-institution index is that
			// every surface says how old the rows on it are.
			'read'       => (int) WPCPM_Roster_Index::read( $record_id )['read'],
		);
	}

	/**
	 * The ledes kept from the page's modules of before: the Semester reports view's, which says when the report can be
	 * read (the program prepares it, and an Administrator approves it before the institution sees it).
	 *
	 * @return array<string, string>
	 */
	public static function leads() {
		return array(
			self::MODULE_REPORT => __( 'The report the program prepares for your institution each semester, to read and download as a PDF once an Administrator has approved it.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Draw one of the dashboard's cards, if the class that owns it is loaded.
	 *
	 * A card whose class is not loaded leaves a gap on the page rather than a fatal on the page
	 * a partner institution was invited to. Named as a string and called through
	 * `call_user_func()` for that reason: a hard reference would be a parse-time promise this
	 * class cannot keep. The order of the calls is the spec's, and it is the whole of it - a
	 * card that is not called from `render()` is not on the dashboard.
	 *
	 * @param string $owner     Class that owns the card.
	 * @param string $record_id Institutions record ID being rendered.
	 * @param array  $context   What every card is handed.
	 */
	private static function card( $owner, $record_id, array $context ) {
		if ( ! class_exists( $owner ) || ! method_exists( $owner, 'render' ) ) {
			return;
		}

		call_user_func( array( $owner, 'render' ), $record_id, $context );
	}

	/**
	 * The institution's facts for the header, from the index first and the stamp second.
	 *
	 * The index row is what the last sync read, and the sync runs every three hours. The stamp is
	 * written once, by `WPCPM_Institution_Members::attach()`, and no sync refreshes it: read
	 * first, it would freeze the name, the city, the stage and the website at the day the account
	 * was attached, on a header that carries no read date to say so.
	 *
	 * The stamp is still what lets the header render at all for an institution the index has
	 * no row for. `WPCPM_Institutions_Index::read()` discards a stored copy at a version it
	 * does not know, so between a shape change and the next sync no institution has a row,
	 * and a record that has left the synced set never gets one back.
	 *
	 * Key by key rather than one array or the other, because either source can be short of a
	 * field: an index row holds an empty string wherever Airtable does, and an older stamp
	 * simply lacks a key added to the profile later. A manager has no stamp at all -
	 * `attach()` refuses administrators - so for them this is always the index row.
	 *
	 * Values are trimmed on the way out. Ten names in the base end in a space, and the manager
	 * screen already says so; a header is not the place to show it.
	 *
	 * @param string       $record_id Institutions record ID.
	 * @param WP_User|null $member    The viewer, when the policy matched them as a member.
	 * @return array
	 */
	private static function profile( $record_id, $member = null ) {
		$stamped = array();

		if ( $member instanceof WP_User ) {
			$stored = get_user_meta( $member->ID, WPCPM_Institution_Members::META_PROFILE, true );

			if ( is_array( $stored ) ) {
				$stamped = $stored;
			}
		}

		$row = WPCPM_Institutions_Index::row( $record_id );
		$out = array();

		// The index is the fresh source and wins outright whenever it has this institution:
		// `shape()` writes every one of these keys on every row, so an empty value there means
		// the base holds nothing, never that the index has no answer. Falling back key by key
		// would let a stamp taken at attach() time keep saying "Agreement sent" months after
		// the grid moved on, which is the staleness this read exists to end. The stamp is only
		// for an institution the index has not read yet, so the header can still render.
		$source = is_array( $row ) ? $row : $stamped;

		foreach ( array( 'name', 'city', 'country_name', 'stage', 'website', 'contact_person' ) as $key ) {
			$out[ $key ] = isset( $source[ $key ] ) ? trim( (string) $source[ $key ] ) : '';
		}

		return $out;
	}

	/**
	 * How much of an institution's home page is read while looking for its icon.
	 *
	 * Past any real `<head>`, which is the only part of the document this reads: the smaller
	 * cap this replaced cut inside one university's head and reported the site as having no
	 * icon at all. The request's real ceiling is its four-second timeout.
	 *
	 * @var int
	 */
	const MAX_ICON_HTML = 1048576;

	/**
	 * The institution's own site icon, or ''.
	 *
	 * **Resolved on this site and never through a third party.** The obvious way to put a
	 * favicon on a page is to point an `<img>` at somebody's icon service, which tells that
	 * service which institution every program manager looked at and when. This asks the
	 * institution's own site instead: the `<link rel="icon">` it declares, and `/favicon.ico`
	 * if it declares none.
	 *
	 * Cached for a week per site, including the answer "there isn't one", because the failure
	 * is the common case for a university that never set one and re-asking on every page load
	 * would spend two HTTP requests to learn nothing. The cache is keyed by the host, so ten
	 * institutions at one university cost one lookup.
	 *
	 * Only ever an absolute https or http URL on the institution's own host: an icon path is
	 * attacker-controlled data as far as this plugin is concerned, since it comes out of a
	 * document the program does not own.
	 *
	 * @param string $website The institution's website, already normalized to a URL.
	 * @return string An icon URL, or ''.
	 */
	private static function site_icon( $website ) {
		$website = trim( (string) $website );

		if ( '' === $website ) {
			return '';
		}

		$host = strtolower( (string) wp_parse_url( $website, PHP_URL_HOST ) );

		if ( '' === $host ) {
			return '';
		}

		$key    = 'wpcpm_icon_' . md5( $host );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return is_string( $cached ) ? $cached : '';
		}

		$icon = self::discover_icon( $website, $host );

		// A week either way. The answer changes about as often as a university rebrands.
		set_transient( $key, $icon, WEEK_IN_SECONDS );

		return $icon;
	}

	/**
	 * Ask one site for its icon.
	 *
	 * Two requests at most, both short and both with a small ceiling on what is read back: a
	 * page that streams a hundred megabytes must not be able to hold a dashboard open.
	 *
	 * @param string $website The site.
	 * @param string $host    Its host, for keeping the answer on the same site.
	 * @return string
	 */
	private static function discover_icon( $website, $host ) {
		$response = wp_safe_remote_get(
			$website,
			array(
				'timeout'             => 4,
				'redirection'         => 3,
				// **A cap that cut inside the head answered "no icon" for a site that had
				// one.** It was 200KB, which sounds generous for a `<head>` and is not: one
				// university in this program does not close its head until byte 309,827, so
				// its four icon declarations were past the cut and the resolver reported the
				// site as offering none. Nothing failed and nothing was logged; the answer was
				// simply wrong, and cached for a week. The ceiling that actually bounds this
				// request is the four-second timeout above, which no cap changes; this one is
				// here so a page that streams megabytes cannot be read into memory, and a
				// megabyte is past any real head while still being that.
				'limit_response_size' => self::MAX_ICON_HTML,
				'user-agent'          => 'WPCreditsProgramManager/' . WPCPM_VERSION . '; ' . home_url( '/' ),
			)
		);

		$html = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
		$head = stripos( $html, '</head>' );

		// Only the head declares an icon. Cutting there keeps a `<link>` written in the body
		// out of the match, and it is what makes the megabyte above cheap: the regex runs over
		// the head and not over the page. A document whose head never closes within the cap is
		// read as far as it was fetched, which is the old behavior and still better than
		// nothing.
		if ( false !== $head ) {
			$html = substr( $html, 0, $head );
		}

		$href = '';

		// `rel` may carry several words and the order of the attributes is the document's
		// business, so the tag is matched first and its attributes read afterwards.
		if ( '' !== $html && preg_match_all( '/<link\s[^>]*>/i', $html, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				if ( ! preg_match( '/\brel\s*=\s*["\']([^"\']*)["\']/i', $tag, $rel ) ) {
					continue;
				}

				$words = preg_split( '/\s+/', strtolower( trim( $rel[1] ) ) );

				if ( ! in_array( 'icon', (array) $words, true ) ) {
					continue;
				}

				if ( preg_match( '/\bhref\s*=\s*["\']([^"\']+)["\']/i', $tag, $found ) ) {
					$href = html_entity_decode( trim( $found[1] ), ENT_QUOTES, 'UTF-8' );
					break;
				}
			}
		}

		// Two candidates, in order: what the document declares, then the conventional path.
		//
		// **An off-host declaration is skipped rather than fatal to the whole lookup.** Plenty
		// of sites serve their logo from a CDN, which is ordinary and not something to punish;
		// what the same-host rule is for is a document this program does not own pointing a
		// dashboard at an arbitrary third party, and skipping the declaration answers that
		// completely. Giving up at that point did not: one institution declares its logo on
		// Cloudinary and serves a perfectly good `/favicon.ico`, and showed nothing at all.
		$candidates = array();

		if ( '' !== $href ) {
			$candidates[] = self::absolute_icon( $href, $website );
		}

		$candidates[] = rtrim( $website, '/' ) . '/favicon.ico';

		foreach ( $candidates as $url ) {
			if ( '' === $url || strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== $host ) {
				continue;
			}

			$head = wp_safe_remote_head(
				$url,
				array(
					'timeout'     => 4,
					'redirection' => 2,
				)
			);

			if ( is_wp_error( $head ) || 200 !== (int) wp_remote_retrieve_response_code( $head ) ) {
				continue;
			}

			$type = strtolower( (string) wp_remote_retrieve_header( $head, 'content-type' ) );

			// An icon, and not a sign-in page answering 200 with an HTML apology, which is what
			// a missing `/favicon.ico` usually is.
			if ( '' === $type || 0 === strpos( $type, 'image/' ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * One `href` from a document, as an absolute URL on that site.
	 *
	 * @param string $href    The value the document gave.
	 * @param string $website The site it came from.
	 * @return string
	 */
	private static function absolute_icon( $href, $website ) {
		if ( 0 === strpos( $href, '//' ) ) {
			$href = ( 0 === strpos( $website, 'http://' ) ? 'http:' : 'https:' ) . $href;
		}

		if ( preg_match( '#^https?://#i', $href ) ) {
			return esc_url_raw( $href );
		}

		// A data: or javascript: href is not a URL this will follow.
		if ( false !== strpos( $href, ':' ) && ! preg_match( '#^/#', $href ) ) {
			return '';
		}

		$base = rtrim( $website, '/' );

		return esc_url_raw( 0 === strpos( $href, '/' ) ? $base . $href : $base . '/' . $href );
	}

	/**
	 * The Overview's contact card: who this institution writes to at the program, the Overview's last word.
	 *
	 * The contact is the Administrator the institution is assigned to, with their address and, when they
	 * keep one on their account, their booking link. An institution nobody is assigned to prints nothing
	 * at all rather than a heading with a blank under it: an empty contact block would read as "you have
	 * nobody", which is not what an unassigned institution means.
	 *
	 * @param string $record Institutions record ID.
	 * @return string Escaped markup, or ''.
	 */
	private static function contact_block( $record ) {
		$contact = WPCPM_Institution_Assignment::contact_of( $record );

		if ( null === $contact ) {
			return '';
		}

		$name     = trim( (string) $contact['name'] );
		$email    = trim( (string) $contact['email'] );
		$calendly = trim( (string) $contact['calendly'] );

		if ( '' === $name && '' === $email && '' === $calendly ) {
			return '';
		}

		$out  = '<section class="wpcpm-institution__contact">';
		$out .= WPCPM_UI::heading( __( 'Your contact at the program', 'wpcredits-program-manager' ), 'h2' );

		if ( '' !== $name ) {
			$out .= sprintf( '<p class="wpcpm-institution__contact-name">%s</p>', esc_html( $name ) );
		}

		$lines = array();

		if ( '' !== $email ) {
			$lines[] = sprintf(
				'<a href="mailto:%1$s">%2$s</a>',
				esc_attr( $email ),
				esc_html( $email )
			);
		}

		if ( '' !== $calendly ) {
			$lines[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $calendly ),
				esc_html__( 'Book a call', 'wpcredits-program-manager' )
			);
		}

		if ( ! empty( $lines ) ) {
			$out .= '<p class="wpcpm-institution__contact-links">' . implode( ' &middot; ', $lines ) . '</p>';
		}

		$out .= WPCPM_UI::intro( __( 'They look after your institution. Write to them about anything the guide does not answer.', 'wpcredits-program-manager' ), 'muted' );

		return $out . '</section>';
	}

	/**
	 * An institution's website as a link, or an empty string.
	 *
	 * The base holds addresses typed by people, so a bare `example.edu` is common. It is
	 * given a scheme rather than dropped, and anything that is still not a http(s) URL after
	 * that is not printed at all: a header is not a place to render whatever a text field
	 * happens to contain.
	 *
	 * @param string $website The stored value.
	 * @return string
	 */
	private static function website_url( $website ) {
		$website = trim( (string) $website );

		if ( '' === $website ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $website ) ) {
			$website = 'https://' . $website;
		}

		$url = esc_url_raw( $website );

		return ( '' !== $url && '' !== (string) wp_parse_url( $url, PHP_URL_HOST ) ) ? $url : '';
	}

	/**
	 * The banner's sentences, as the Agreement view's panel prints them and the Overview's Administrator row reads them: the
	 * state in words, then, when Airtable records an Agreement Status, what it records. Not escaped.
	 *
	 * The other half of the predicate is named rather than left to be guessed at: the gate is closed when either
	 * source says so, and a manager reading "a signed copy is waiting" needs to know whether the base is what is
	 * holding it.
	 *
	 * On the Agreement view (`$agreement_view`, S2 of 1.123.99) the panel's own sentence for the state is printed under
	 * the banner and names the state in more words, so the banner says each fact once there: for no agreement and a
	 * revoked one the panel's sentence also says what the school's people see, and the first sentence goes whole; for a
	 * generated, submitted or returned copy it keeps what the school's people see and drops the state. The two states
	 * where the sources disagree keep the whole sentence: it alone says Airtable has not confirmed the site's copy (fix
	 * round F3). What Airtable records stays. The Overview's row reads every sentence, as before.
	 *
	 * @param string $record         Institutions record ID.
	 * @param bool   $agreement_view Whether the Agreement view's panel prints them, under its own sentence.
	 * @return string[] None, one sentence, or two.
	 */
	public static function banner_sentences( string $record, bool $agreement_view = false ): array {
		$summary = WPCPM_Institution_Agreement::summary( $record );
		$state   = (string) $summary['state'];
		$said    = array( WPCPM_Institution_Agreement::SUMMARY_NONE, WPCPM_Institution_Agreement::SUMMARY_REVOKED );
		$named   = array( WPCPM_Institution_Agreement::SUMMARY_GENERATED, WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, WPCPM_Institution_Agreement::SUMMARY_RETURNED );

		if ( $agreement_view && in_array( $state, $said, true ) ) {
			$sentences = array();
		} elseif ( $agreement_view && in_array( $state, $named, true ) ) {
			$sentences = array( __( 'This institution\'s agreement is not settled. Its representatives see only the Overview and the Agreement, and its tutors no page.', 'wpcredits-program-manager' ) );
		} else {
			$sentences = array(
				sprintf(
					/* translators: %s: the agreement state in words, e.g. "not started". */
					__( 'This institution\'s agreement is not settled: %s. Its representatives see only the Overview and the Agreement, and its tutors no page.', 'wpcredits-program-manager' ),
					self::state_label( $state )
				),
			);
		}

		if ( '' !== $summary['airtable_status'] ) {
			$sentences[] = sprintf(
				/* translators: %s: the Airtable Agreement Status, e.g. "Awaiting review". */
				__( 'Airtable records the Agreement Status as %s.', 'wpcredits-program-manager' ),
				$summary['airtable_status']
			);
		}

		return $sentences;
	}

	/**
	 * One agreement summary state, in words.
	 *
	 * `accepted` and `on_file` reach this only when the two sources disagree, since the
	 * banner is drawn for an unsettled institution alone. They are worded as what they are:
	 * a site-side acceptance the base has not confirmed.
	 *
	 * @param string $state A summary state from `WPCPM_Institution_Agreement::summary()`.
	 * @return string
	 */
	private static function state_label( $state ) {
		switch ( $state ) {
			case WPCPM_Institution_Agreement::SUMMARY_GENERATED:
				return __( 'the template has been generated and no signed copy uploaded', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_SUBMITTED:
				return __( 'a signed copy is waiting for review', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_RETURNED:
				return __( 'the last copy was returned', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_REVOKED:
				return __( 'revoked', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_ACCEPTED:
				return __( 'accepted on this site, which Airtable has not confirmed', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_ON_FILE:
				return __( 'on file according to this site, which Airtable has not confirmed', 'wpcredits-program-manager' );
		}

		return __( 'not started', 'wpcredits-program-manager' );
	}

	/**
	 * A "view as" control for program managers.
	 *
	 * Keyed by record and not by user, because with several members per institution a user is
	 * the wrong unit: two accounts at one school are one entry here. Only a manager ever sees
	 * it, and `resolve_institution()` does not even read the argument for anyone else, so a
	 * member appending it to the URL changes nothing. Drawn by `WPCPM_Dashboards`, the one form
	 * all four dashboards share: sorted A to Z, in one field that drops down, takes typing and
	 * narrows its list as the Administrator types.
	 *
	 * In the frame the form keeps the view being read, so an Administrator moving to another school stays on the
	 * same view, as the Sponsor Dashboard's does. On the choice of a school `$current` is '' and nothing is selected.
	 *
	 * @param string $current Institutions record ID currently being viewed, or '' while none is chosen.
	 * @param string $view    The view being read, a `VIEW_*` key.
	 */
	private static function render_switcher( string $current, string $view = self::VIEW_OVERVIEW ) {
		WPCPM_Dashboards::render_switcher(
			array(
				'id'      => 'wpcpm-institution-switcher',
				// The roster's constant, not a copy: `resolve_institution()` is what reads this field,
				// and a switcher posting a name it does not read is a control that does nothing.
				'name'    => WPCPM_Institution_Roster::ARG_VIEW,
				'options' => WPCPM_Institution_Roster::switcher_options(),
				'current' => $current,
				'label'   => __( 'Viewing as institution', 'wpcredits-program-manager' ),
				'find'    => __( 'Find an institution', 'wpcredits-program-manager' ),
				'none'    => __( 'No institutions match that search.', 'wpcredits-program-manager' ),
				'keep'    => self::VIEW_OVERVIEW !== $view ? array( self::VIEW_ARG => $view ) : array(),
				// The compact control's empty first option while no school is chosen (Task 14); today's switcher prints none.
				'blank'   => __( 'Choose an institution', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * A standalone notice, optionally with an action link.
	 *
	 * @param string $message     Message text.
	 * @param string $action_url  Optional action URL.
	 * @param string $action_text Optional action label.
	 * @param bool   $escape      Whether to escape the message. Pass false only for markup
	 *                            this plugin built itself, which today means
	 *                            `WPCPM_Dashboards::nothing_to_show()`.
	 * @return string
	 */
	private static function notice( $message, $action_url = '', $action_text = '', $escape = true ) {
		$html = '<div class="wpcpm-dashboard wpcpm-dashboard--notice"><p>'
			. ( $escape ? esc_html( $message ) : wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) )
			. '</p>';

		if ( $action_url && $action_text ) {
			$html .= '<p><a class="wpcpm-button" href="' . esc_url( $action_url ) . '">' . esc_html( $action_text ) . '</a></p>';
		}

		return $html . '</div>';
	}

	/**
	 * The current front-end URL, for login redirects.
	 *
	 * @return string
	 */
	private static function current_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id && get_post_status( $page_id ) ) {
			return (string) get_permalink( $page_id );
		}

		return home_url( '/' );
	}

	/**
	 * Create the institution page if it is missing, and gate it to Institution level.
	 *
	 * @return int Page ID, or 0 on failure.
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

		// A site that has the page but not the option - restored from a backup, or migrated -
		// adopts it rather than creating a second one at `institution-dashboard-2`.
		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, $existing->ID, false );
			self::gate_page( $existing->ID );

			return $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Institution Dashboard', 'wpcredits-program-manager' ),
				'post_name'    => self::SLUG,
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
	 * Restrict the institution page to Institution level.
	 *
	 * Only when nothing is set: a site that has deliberately gated the page to something else
	 * keeps its own answer, and this runs on every activation.
	 *
	 * @param int $page_id Page ID.
	 */
	private static function gate_page( $page_id ) {
		// `metadata_exists()` and not the value: the access level is registered with a default
		// of `public`, which `get_post_meta()` returns for a page that has no row at all, so
		// asking the value would read a brand-new page as deliberately public and never gate it.
		// That is exactly how the Institution Dashboard first came up on the live site.
		if ( ! metadata_exists( 'post', $page_id, WPCPM_Content_Access::META_KEY ) ) {
			update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_INSTITUTION );
		}
	}

	/**
	 * The institution page's permalink, if it exists.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		// Must be published, not merely existing: `get_post_status()` returns 'trash' for a
		// trashed page, which is truthy - and now that logins redirect here, that would land
		// every member on a 404.
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}
}
