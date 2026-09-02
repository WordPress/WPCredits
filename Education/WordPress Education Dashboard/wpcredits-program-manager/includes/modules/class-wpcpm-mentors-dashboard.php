<?php
/**
 * Mentors module — the mentor's own students page.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the page each mentor sees: the students assigned to them in Airtable.
 *
 * There is one page, not one page per mentor. The page renders against the
 * logged-in user, so every mentor gets their own view of it and no mentor can
 * reach another's list by guessing a URL — which a per-mentor permalink would
 * invite. Program managers can inspect any mentor's view via `?wpcpm_mentor=<id>`.
 */
class WPCPM_Mentors_Dashboard {

	const SHORTCODE = 'wpcpm_mentor_dashboard';
	const BLOCK     = 'wpcpm/mentor-dashboard';
	const OPT_PAGE  = 'wpcpm_mentor_page_id';
	const STYLE     = 'wpcpm-mentor-dashboard';
	const SCRIPT    = 'wpcpm-mentor-dashboard';

	/** The triage, search and counts laid over the rendered list. */
	const TRIAGE_STYLE  = 'wpcpm-triage';
	const TRIAGE_SCRIPT = 'wpcpm-triage';

	/**
	 * Records which title revision this site has been brought up to.
	 *
	 * A counter rather than a flag, for the reason the student page found out the hard
	 * way: a boolean records only *that* a rename happened, so a site renamed once is
	 * skipped for ever and keeps a title a revision old.
	 */
	const OPT_TITLE_FIXED = 'wpcpm_mentor_page_title_fixed';

	/** The current title revision. Bump this whenever the page's title changes. */
	const TITLE_VERSION = 1;

	/**
	 * Page titles this plugin has shipped for the mentor page.
	 *
	 * Only a title the plugin itself created is ever replaced — anything a site has
	 * renamed by hand is theirs and is left alone.
	 */
	const OLD_TITLES = array( 'My Students' );

	/**
	 * Where a student's Slack handle links to.
	 *
	 * The program's channel, not the person: Slack has no public per-user URL, so
	 * every handle points at the place a mentor would go to use it. The same target
	 * for every student, by design.
	 */
	const SLACK_URL = 'https://wordpress.slack.com/archives/C0959D2M3T8';

	/**
	 * Hook up the shortcode, block and styles.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		// After `register`, and once only. `ensure_page()` sets a title when it *creates*
		// the page, so an install that predates a wording change keeps the old one for ever.
		add_action( 'init', array( __CLASS__, 'maybe_rename_page' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_style' ) );

		// Make the page reachable. Without these a mentor has an account, a page
		// gated to their level, and no route to it: logging in drops them on a
		// wp-admin screen that shows them nothing.
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'replace_admin_dashboard' ) );
	}

	/**
	 * Bring an existing page's title in line with the current wording.
	 *
	 * Runs at most once per site: the flag is written whatever the outcome, so a site that
	 * has deliberately renamed the page is not asked again on every request.
	 *
	 * The *slug* is deliberately untouched. It is `mentor-dashboard`, the theme matches its
	 * template on it, and renaming it would break that and every existing link.
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
				'post_title' => __( 'Mentor Report Card', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * Whether this user should be treated as a mentor for routing purposes.
	 *
	 * Recognised the same way the toolbar link recognises them, then narrowed twice.
	 *
	 * Program managers are excluded — they need wp-admin. So is anyone who can write posts:
	 * an account that happens to hold the Mentor role *and* an editor or author role has a
	 * legitimate reason to be in the admin, and bouncing them out of it would be worse than
	 * the problem being solved. Those two exclusions are also what makes it safe to start
	 * from `is_mentor()`, which counts an administrator linked to a record.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	private static function should_route( $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		// The same test the toolbar link uses — the role *or* an Airtable link — and not the
		// role alone. `is_mentor()` counts somebody matched to a record without holding the role,
		// which is how a mentor provisioned before the role existed, or one whose role was
		// removed and restored by hand, is still recognised. Testing the role here meant the
		// toolbar offered them their page while the redirects behaved as though they were not
		// a mentor at all: they logged in and stayed on the wp-admin dashboard.
		if ( ! self::is_mentor( $user ) ) {
			return false;
		}

		// ...but not somebody whose mentoring has ended. Going inactive removes the role and sets
		// this flag to 0 while deliberately leaving the Airtable link in place, so
		// `is_mentor()` still says yes — and the page it would send them to has nothing on it.
		// A held role outranks the flag: that is an explicit grant, and an account given the
		// role by hand has no flag at all.
		if ( ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR )
			&& '0' === (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_ACTIVE, true ) ) {
			return false;
		}

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_posts' ) ) {
			return false;
		}

		return (bool) WPCPM_Settings::get_value( 'mentor_home' );
	}

	/**
	 * Send mentors to their students page when they log in.
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
	 * Use the students page in place of the wp-admin Dashboard.
	 *
	 * Only the Dashboard itself is redirected. `profile.php` is deliberately left
	 * alone so a mentor can still change their own password and name.
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
	 * Add a persistent link to the mentor page in the admin bar.
	 *
	 * The redirects cover arriving; this covers getting back, from anywhere on the
	 * site. Shown to program managers too, since they can inspect the page.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar instance.
	 */
	public static function admin_bar_link( $admin_bar ) {
		if ( ! $admin_bar instanceof WP_Admin_Bar ) {
			return;
		}

		// Anyone with a list of their own gets the link, including an
		// administrator who also mentors; a manager who mentors nobody is only ever
		// looking at someone else's.
		$is_mentor  = self::is_mentor();
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );

		if ( ! $is_mentor && ! $can_manage ) {
			return;
		}

		$page = self::page_url();

		if ( '' === $page ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'wpcpm-my-students',
				// Named for the page it opens, the way the student side's link is. A mentor
				// following "My Students" and landing on "Mentor Report Card" has to work
				// out for themselves that they are in the right place.
				'title' => $is_mentor
					? __( 'Mentor Report Card', 'wpcredits-program-manager' )
					: __( 'Mentor Dashboard', 'wpcredits-program-manager' ),
				'href'  => $page,
				'meta'  => array(
					'title' => $is_mentor
						? __( 'The students assigned to you', 'wpcredits-program-manager' )
						: __( 'Inspect any mentor\'s students', 'wpcredits-program-manager' ),
				),
			)
		);
	}

	/**
	 * Register the shortcode, the block and the stylesheet.
	 */
	public static function register() {
		self::register_assets();

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/mentor-dashboard';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type(
				$block_dir,
				array( 'render_callback' => array( __CLASS__, 'render_block' ) )
			);
		}
	}

	/**
	 * Register (but do not enqueue) the stylesheet.
	 */
	public static function register_assets() {
		// Guarded per asset, not with one early return: this runs both on `init`
		// and again for the editor, and a single guard on the stylesheet would skip
		// registering the script on the second call.
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/dashboard.css',
				// Core registers `dashicons` on the front end too. Declared here rather
				// than enqueued at each render site, so it cannot be forgotten at one.
				array( 'dashicons' ),
				WPCPM_VERSION
			);
		}

		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::SCRIPT,
				WPCPM_PLUGIN_URL . 'assets/js/dashboard.js',
				array(),
				WPCPM_VERSION,
				true
			);
		}

		if ( ! wp_style_is( self::TRIAGE_STYLE, 'registered' ) ) {
			wp_register_style(
				self::TRIAGE_STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/triage.css',
				array( self::STYLE ),
				WPCPM_VERSION
			);
		}

		if ( ! wp_script_is( self::TRIAGE_SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::TRIAGE_SCRIPT,
				WPCPM_PLUGIN_URL . 'assets/js/triage.js',
				array(),
				WPCPM_VERSION,
				true
			);
		}
	}

	/**
	 * What the triage script needs to group, count and search the rendered list.
	 *
	 * Everything here is already on the page or already in this plugin — the script fetches
	 * nothing and writes nothing, and no student appears who was not rendered. It is handed over
	 * as data rather than scraped out of the markup so that changing a column's wording does not
	 * quietly change which pile a student lands in.
	 *
	 * Which mentor's list this is comes from `current_mentor()`, the same answer the render
	 * uses. The theme used to work it out for itself and the two disagreed for an administrator
	 * who also mentors — the rows are joined on Airtable record ID, so nothing matched and every
	 * row lost its end date and note count. There is only one answer now, by construction.
	 *
	 * @param int $mentor_id Mentor whose list is on screen.
	 * @return array
	 */
	public static function triage_data( $mentor_id ) {
		$students = array();
		$format   = (string) get_option( 'date_format' );

		foreach ( self::get_mentees( (int) $mentor_id ) as $mentee ) {
			if ( ! is_array( $mentee ) ) {
				continue;
			}

			$record = isset( $mentee['record_id'] ) ? (string) $mentee['record_id'] : '';

			// Without a record ID the card carries no anchor, so there is nothing to match this
			// row to. Such a student is left ungrouped rather than guessed at.
			if ( '' === $record ) {
				continue;
			}

			$end   = isset( $mentee['end'] ) ? (string) $mentee['end'] : '';
			$stamp = ( '' !== $end ) ? strtotime( $end ) : false;

			$institution = WPCPM_Mentors_Sync::resolve_stored(
				isset( $mentee['institution'] ) ? (string) $mentee['institution'] : '',
				'institutions'
			);
			$team        = WPCPM_Mentors_Sync::resolve_stored(
				isset( $mentee['team'] ) ? (string) $mentee['team'] : '',
				'teams'
			);

			$students[ $record ] = array(
				'name'        => isset( $mentee['name'] ) ? (string) $mentee['name'] : '',
				'institution' => $institution,
				'team'        => $team,
				'status'      => isset( $mentee['status'] ) ? (string) $mentee['status'] : '',
				'isPast'      => ! empty( $mentee['is_past'] ),
				'end'         => $stamp ? gmdate( 'Y-m-d', $stamp ) : '',
				'endLabel'    => $stamp ? date_i18n( $format, $stamp ) : '',
				'notes'       => (int) WPCPM_Mentor_Notes::count_notes( $record ),
				// Everything a mentor might type into the box, built here so the script never
				// has to read it back out of the rendered row.
				'search'      => self::search_haystack( $mentee, $institution, $team ),
			);
		}

		return array(
			'students' => $students,
			// The site's today, not the browser's: a mentor travelling should see the same
			// grouping as the program manager looking at the same list.
			'today'    => wp_date( 'Y-m-d' ),
			'windows'  => array(
				/**
				 * Filter how near the end of an internship counts as ending soon.
				 *
				 * @param int $days Default 60.
				 */
				'endingSoon' => max( 1, (int) apply_filters( 'wpcpm_ending_soon_days', 60 ) ),
				/**
				 * Filter how long a student can go without a note before they need a call.
				 *
				 * Matches the wording on the card — "no note in the last 30 days" — so the
				 * grouping and the notice cannot contradict each other.
				 *
				 * @param int $days Default 30.
				 */
				'staleNote'  => max( 1, (int) apply_filters( 'wpcpm_stale_note_days', 30 ) ),
			),
			'groups'   => array(
				// Order matters: a student falls into the first group they match, so somebody
				// who needs a call is never filed under "ending soon" instead.
				array(
					'key'   => 'call',
					'label' => __( 'Need a call', 'wpcredits-program-manager' ),
				),
				array(
					'key'   => 'ending',
					'label' => __( 'Ending soon', 'wpcredits-program-manager' ),
				),
				array(
					'key'   => 'ok',
					'label' => __( 'On track', 'wpcredits-program-manager' ),
				),
			),
			'i18n'     => array(
				'searchLabel'   => __( 'Search students', 'wpcredits-program-manager' ),
				'searchHint'    => __( 'Search students, institutions or teams', 'wpcredits-program-manager' ),
				'clearSearch'   => __( 'Clear the search', 'wpcredits-program-manager' ),
				/* translators: 1: matching students, 2: total students. */
				'matchCount'    => __( '%1$s of %2$s students match', 'wpcredits-program-manager' ),
				'noMatches'     => __( 'No students match that search.', 'wpcredits-program-manager' ),
				/* translators: %s: number of matches within one group. */
				'groupMatches'  => __( '%s match', 'wpcredits-program-manager' ),
				'collapsedHint' => __( 'Some browsers do not search inside collapsed sections with Ctrl+F. Use Expand all first if you are looking for something specific.', 'wpcredits-program-manager' ),
				'noNotes'       => __( 'No notes', 'wpcredits-program-manager' ),
				'addNote'       => __( 'Add a note', 'wpcredits-program-manager' ),
				'details'       => __( 'Details', 'wpcredits-program-manager' ),
				/* translators: %s: number of days. */
				'daysLeft'      => __( '%s days', 'wpcredits-program-manager' ),
				'endedAlready'  => __( 'Ended', 'wpcredits-program-manager' ),
				'needCall'      => __( 'need a call', 'wpcredits-program-manager' ),
				'endingSoon'    => __( 'ending soon', 'wpcredits-program-manager' ),
				'onTrack'       => __( 'on track', 'wpcredits-program-manager' ),
				'showAll'       => __( 'Show all students', 'wpcredits-program-manager' ),
				'ordering'      => __( 'Ordered by internship end date within each group, soonest first.', 'wpcredits-program-manager' ),
				/* translators: %s: student's name. */
				'noteFor'       => __( 'Add a note for %s', 'wpcredits-program-manager' ),
				/* translators: %s: internship end date. */
				'until'         => __( 'until %s', 'wpcredits-program-manager' ),
			),
			'icons'    => array(
				'search' => WPCPM_Icons::ui( 'search', 16 ),
				'close'  => WPCPM_Icons::ui( 'close', 15 ),
				'people' => WPCPM_Icons::ui( 'people', 20 ),
			),
		);
	}

	/**
	 * The searchable text for one student.
	 *
	 * Lowercased once here so the script can compare without doing it per keystroke.
	 *
	 * @param array  $mentee      Student row.
	 * @param string $institution Resolved institution name.
	 * @param string $team        Resolved team name.
	 * @return string
	 */
	private static function search_haystack( array $mentee, $institution, $team ) {
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

		// `strlen` as the filter, not the default: a student whose only searchable value is "0"
		// is a real row, and the default callback would drop it.
		$parts = array_filter( array_map( 'trim', array_map( 'strval', $parts ) ), 'strlen' );

		// Names in this program are not all ASCII, so a byte-wise lowercase would miss matches.
		return function_exists( 'mb_strtolower' )
			? mb_strtolower( implode( ' ', $parts ) )
			: strtolower( implode( ' ', $parts ) );
	}

	/**
	 * Load the stylesheet in the editor so the server-rendered preview matches
	 * the front end.
	 */
	public static function enqueue_editor_style() {
		self::register_assets();
		wp_enqueue_style( self::STYLE );
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
		wp_enqueue_script( self::SCRIPT );

		// The triage, the counts and the search. Enqueued only for the mentor whose list this
		// is: it is the rendered list that is being grouped, so there is nothing to hand over
		// when the card is a notice rather than a list.
		$viewer = self::current_mentor();

		if ( $viewer instanceof WP_User ) {
			wp_enqueue_style( self::TRIAGE_STYLE );
			wp_enqueue_script( self::TRIAGE_SCRIPT );
			wp_localize_script( self::TRIAGE_SCRIPT, 'wpcpmTriage', self::triage_data( $viewer->ID ) );
		}

		// Where the report bodies come from, and the one message the script has to say for
		// itself. Localized here rather than printed in the markup so the strings stay
		// translatable and the endpoint is built by WordPress.
		wp_localize_script(
			self::SCRIPT,
			'wpcpmDashboard',
			array(
				'reportEndpoint' => esc_url_raw( rest_url( 'wpcpm/v1/report/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'strings'        => array(
					'failed' => __( 'That report could not be loaded just now. Close this and open it again to retry.', 'wpcredits-program-manager' ),
				),
			)
		);

		if ( ! is_user_logged_in() ) {
			return self::notice(
				__( 'Please log in to see the students assigned to you.', 'wpcredits-program-manager' ),
				wp_login_url( self::current_url() ),
				__( 'Log in', 'wpcredits-program-manager' )
			);
		}

		$viewer     = wp_get_current_user();
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$mentor     = self::resolve_mentor( $viewer, $can_manage );

		if ( ! $mentor instanceof WP_User ) {
			return self::notice( WPCPM_Dashboards::nothing_to_show( 'mentors', $can_manage ), '', '', false );
		}

		$mentees = self::get_mentees( $mentor->ID );
		$updated = (int) get_user_meta( $mentor->ID, WPCPM_Mentors_Sync::META_UPDATED, true );

		ob_start();

		echo '<div class="wpcpm-dashboard">';

		// Where a mentor actually is, which is the only place a prompt to protect the account
		// will be seen: the plugin's own controls live on the wp-admin profile screen, and
		// mentors are routed away from wp-admin the moment they sign in. The reader's own
		// account, never the one being looked at: an administrator inspecting a mentor's list is
		// being told about their own sign-in, not about somebody else's.
		WPCPM_Two_Factor::prompt( wp_get_current_user() );

		if ( ! empty( $atts['title'] ) ) {
			echo '<h2 class="wpcpm-dashboard__title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		if ( $can_manage ) {
			self::render_mentor_switcher( $mentor );
		}

		echo '<header class="wpcpm-dashboard__mentor">';

		self::render_avatar(
			self::mentor_username( $mentor ),
			$mentor->user_email,
			$mentor->display_name,
			80
		);

		echo '<div class="wpcpm-dashboard__mentor-identity">';
		// A paragraph, not a heading — the same shape as the student card's own name. This
		// was an `<h1>` while the page had no title of its own; the page is "Mentor Report
		// Card" now, and that `<h1>` is the page's. Two of them is not a document outline.
		printf(
			'<p class="wpcpm-dashboard__mentor-name">%s</p>',
			esc_html( $mentor->display_name )
		);
		// Split by the flag the sync sets from the student's Airtable status, not by
		// date: a student can be past their end date and still be mentored, or have
		// graduated early.
		$current = array();
		$past    = array();

		foreach ( $mentees as $mentee ) {
			if ( ! empty( $mentee['is_past'] ) ) {
				$past[] = $mentee;
			} else {
				$current[] = $mentee;
			}
		}

		// One line, both facts. They answer the same question — "is this list current?" —
		// and `updated` used to sit alone at the foot of the card, which is why the theme
		// had to lift it up here with JavaScript.
		$facts = array(
			sprintf(
				/* translators: %s: number of students. */
				_n(
					'%s student currently assigned to you.',
					'%s students currently assigned to you.',
					count( $current ),
					'wpcredits-program-manager'
				),
				number_format_i18n( count( $current ) )
			),
		);

		if ( $updated ) {
			$facts[] = sprintf(
				/* translators: %s: human-readable time difference, e.g. "2 hours". */
				__( 'Last updated %s ago.', 'wpcredits-program-manager' ),
				human_time_diff( $updated, time() )
			);
		}

		printf(
			'<p class="wpcpm-dashboard__intro">%s</p>',
			esc_html( implode( ' ', $facts ) )
		);
		echo '</div>';

		echo '</header>';

		// Before the student list: a diary and the hours behind it are what a mentor
		// acts on, where the list is what they refer to. It sits outside the groups on
		// purpose — see the note in WPCPM_Call_Calendar::render_mentor().
		WPCPM_Call_Calendar::render_mentor( $mentor );

		if ( empty( $mentees ) ) {
			echo '<p class="wpcpm-dashboard__empty">' . esc_html__( 'No students are assigned to you right now. This page updates automatically when the program data changes.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			// Hidden until scripted: without JavaScript each student still opens on
			// its own, so buttons that could not work should not be offered.
			if ( count( $mentees ) > 1 ) {
				printf(
					'<div class="wpcpm-dashboard__bulk" data-wpcpm-bulk hidden><button type="button" class="wpcpm-button" data-wpcpm-expand>%1$s</button> <button type="button" class="wpcpm-button" data-wpcpm-collapse>%2$s</button></div>',
					esc_html__( 'Expand all', 'wpcredits-program-manager' ),
					esc_html__( 'Collapse all', 'wpcredits-program-manager' )
				);
			}

			// Current students.
			echo '<section class="wpcpm-group">';
			printf(
				'<h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3>',
				esc_html__( 'Currently mentoring', 'wpcredits-program-manager' ),
				esc_html( number_format_i18n( count( $current ) ) )
			);

			if ( empty( $current ) ) {
				echo '<p class="wpcpm-dashboard__empty">' . esc_html__( 'No students are assigned to you at the moment.', 'wpcredits-program-manager' ) . '</p>';
			} else {
				self::render_mentee_list( $current, $mentor->ID );
			}
			echo '</section>';

			// Finished students, behind their own disclosure: useful to keep, but not
			// what a mentor came to the page for.
			if ( ! empty( $past ) ) {
				$open = self::group_contains_focus( $past );

				echo '<section class="wpcpm-group wpcpm-group--past">';
				printf(
					'<details class="wpcpm-group__disclosure"%s>',
					$open ? ' open' : ''
				);
				printf(
					'<summary class="wpcpm-group__summary"><span class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
					esc_html__( 'Past students', 'wpcredits-program-manager' ),
					esc_html( number_format_i18n( count( $past ) ) )
				);
				echo '<div class="wpcpm-group__body">';
				printf(
					'<p class="wpcpm-dashboard__empty">%s</p>',
					esc_html__( 'Mentoring for these students has finished. Their details and your notes are kept for reference.', 'wpcredits-program-manager' )
				);
				self::render_mentee_list( $past, $mentor->ID );
				echo '</div>';
				echo '</details>';
				echo '</section>';
			}
		}

		// Offered where these people already are, rather than only from a link in the header
		// they may never have noticed.
		// Not through `wp_kses_post()`: it strips `<svg>` outright, which would silently
		// remove the Slack mark. This is the plugin's own markup, escaped as it is built.
		echo WPCPM_Handbook_Assistant::render_resources( 'mentor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by render_resources(), which escapes every value it interpolates.

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Which mentor's list to show, for the current request.
	 *
	 * Public API, and the only supported answer to the question. A theme dressing this
	 * page has to be handed data for the same mentor the page is rendering, and while
	 * this was private the theme reimplemented it — with a role-only mentor test, which
	 * silently disagreed for an administrator who also mentors. The sync never gives an
	 * administrator the Mentor role, so the plugin resolved them to themselves and the
	 * copy resolved them to whichever mentor sorted first.
	 *
	 * @return WP_User|null
	 */
	public static function current_mentor() {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		return self::resolve_mentor( wp_get_current_user(), current_user_can( WPCPM_Roles::CAP_MANAGE ) );
	}

	/**
	 * Which mentor's list to show.
	 *
	 * @param WP_User $viewer     Logged-in user.
	 * @param bool    $can_manage Whether the viewer may inspect other mentors.
	 * @return WP_User|null
	 */
	private static function resolve_mentor( WP_User $viewer, $can_manage ) {
		// Only a manager may inspect somebody else's list, so the argument is not even read
		// for anyone else.
		$requested = $can_manage ? WPCPM_Request::id( 'wpcpm_mentor' ) : 0;

		if ( $requested ) {
			$candidate = get_user_by( 'id', $requested );

			if ( $candidate instanceof WP_User && self::is_mentor( $candidate ) ) {
				return $candidate;
			}
		}

		// Their own list first, always. An administrator who is also an Active
		// mentor in Airtable never receives the Mentor *role* — the sync refuses to
		// touch an administrator's roles — so a role check alone dropped them
		// through to the branch below and showed them somebody else's students.
		if ( self::is_mentor( $viewer ) ) {
			return $viewer;
		}

		// A program manager who mentors nobody sees the first mentor, so the page is
		// inspectable without hand-building a query string.
		if ( $can_manage ) {
			$mentors = self::all_mentors();

			return ! empty( $mentors[0] ) ? $mentors[0] : null;
		}

		return null;
	}

	/**
	 * Where a student's Slack handle links to.
	 *
	 * @return string
	 */
	public static function slack_url() {
		/**
		 * Filter the Slack link target.
		 *
		 * @param string $url Defaults to the program's Making WordPress channel.
		 */
		return (string) apply_filters( 'wpcpm_slack_url', self::SLACK_URL );
	}

	/**
	 * Whether a user has a student list of their own.
	 *
	 * True for anyone holding the Mentor role, and for anyone the sync matched to
	 * an Airtable mentor record whatever their role — which is how administrators
	 * who also mentor are recognized.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function is_mentor( $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR ) ) {
			return true;
		}

		return '' !== trim( (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true ) );
	}

	/**
	 * A "view as" control for program managers.
	 *
	 * @param WP_User $current Mentor currently being viewed.
	 */
	private static function render_mentor_switcher( WP_User $current ) {
		$mentors = self::all_mentors();

		if ( count( $mentors ) < 2 ) {
			return;
		}

		echo '<form class="wpcpm-dashboard__switcher" method="get">';

		// Without pretty permalinks the page is addressed by query string, which a
		// GET form would otherwise discard — resubmitting to the site root.
		if ( ! get_option( 'permalink_structure' ) ) {
			$queried = get_queried_object_id();

			if ( $queried ) {
				printf( '<input type="hidden" name="page_id" value="%d" />', (int) $queried );
			}
		}

		echo '<label for="wpcpm-mentor-switcher">' . esc_html__( 'Viewing as mentor', 'wpcredits-program-manager' ) . '</label> ';
		echo '<select name="wpcpm_mentor" id="wpcpm-mentor-switcher">';

		foreach ( $mentors as $mentor ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $mentor->ID,
				selected( $mentor->ID, $current->ID, false ),
				esc_html( $mentor->display_name )
			);
		}

		echo '</select> ';
		echo '<button type="submit" class="wpcpm-button">' . esc_html__( 'Show', 'wpcredits-program-manager' ) . '</button>';
		echo '<span class="wpcpm-dashboard__switcher-note">' . esc_html__( 'Only administrators see this control.', 'wpcredits-program-manager' ) . '</span>';
		echo '</form>';
	}

	/**
	 * Every account holding the Mentor role.
	 *
	 * Public because the call calendar needs it: finding a student's mentor from the
	 * mentor's side is the fallback for when the student's own mentor card has not been
	 * rebuilt since their mentor's account was created.
	 *
	 * @return WP_User[]
	 */
	public static function all_mentors() {
		$mentors = get_users(
			array(
				'role'    => WPCPM_Roles::ROLE_MENTOR,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => 500,
			)
		);

		// Anyone linked to an Airtable mentor record but without the role —
		// administrators, whose roles the sync leaves alone. Without this they were
		// missing from the switcher and could not select their own list.
		$linked = get_users(
			array(
				'orderby'    => 'display_name',
				'order'      => 'ASC',
				'number'     => 500,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- Bounded by the number of provisioned mentors.
					array(
						'key'     => WPCPM_Mentors_Sync::META_RECORD_ID,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$seen = array();
		$all  = array();

		foreach ( array_merge( $mentors, $linked ) as $mentor ) {
			if ( ! $mentor instanceof WP_User || isset( $seen[ $mentor->ID ] ) ) {
				continue;
			}

			$seen[ $mentor->ID ] = true;
			$all[]               = $mentor;
		}

		usort(
			$all,
			static function ( $a, $b ) {
				return strcasecmp( $a->display_name, $b->display_name );
			}
		);

		return $all;
	}

	/**
	 * Render one group of students.
	 *
	 * @param array[] $mentees   Student rows.
	 * @param int     $mentor_id Mentor whose page is being viewed.
	 */
	private static function render_mentee_list( array $mentees, $mentor_id ) {
		echo '<div class="wpcpm-mentees">';

		foreach ( $mentees as $mentee ) {
			self::render_mentee( $mentee, count( $mentees ), $mentor_id );
		}

		echo '</div>';
	}

	/**
	 * Whether a group holds the student the request is focused on.
	 *
	 * Keeps the Past students section open when a note was just saved against
	 * someone inside it, which would otherwise be hidden twice over.
	 *
	 * @param array[] $mentees Student rows.
	 * @return bool
	 */
	private static function group_contains_focus( array $mentees ) {
		$focus = WPCPM_Mentor_Notes::focused_student();

		if ( '' === $focus ) {
			return false;
		}

		foreach ( $mentees as $mentee ) {
			if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === $focus ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A mentor's WordPress.org username.
	 *
	 * Derived from the profile URL stored at sync time rather than from
	 * `user_login`, because an account that already existed and was *linked* to a
	 * mentor record can have a login that is not their WordPress.org handle.
	 *
	 * @param WP_User $mentor Mentor.
	 * @return string
	 */
	private static function mentor_username( WP_User $mentor ) {
		$profile   = (string) get_user_meta( $mentor->ID, WPCPM_Mentors_Sync::META_PROFILE, true );
		$from_meta = WPCPM_Mentors_Sync::wporg_username( $profile );

		return '' !== $from_meta ? $from_meta : $mentor->user_login;
	}

	/**
	 * The students assigned to a mentor.
	 *
	 * @param int $user_id Mentor user ID.
	 * @return array[]
	 */
	public static function get_mentees( $user_id ) {
		$mentees = get_user_meta( (int) $user_id, WPCPM_Mentors_Sync::META_MENTEES, true );

		return is_array( $mentees ) ? $mentees : array();
	}

	/**
	 * How many students a mentor has, without loading the full list.
	 *
	 * @param int $user_id Mentor user ID.
	 * @return int
	 */
	public static function get_mentee_count( $user_id ) {
		return (int) get_user_meta( (int) $user_id, WPCPM_Mentors_Sync::META_COUNT, true );
	}

	/**
	 * Render one student card.
	 *
	 * @param array $mentee    Student row.
	 * @param int   $total     How many students are being listed, so a single one
	 *                         can be shown already open.
	 * @param int   $mentor_id Whose page this is, so a note saved by a program
	 *                         manager returns to the mentor they were inspecting.
	 */
	private static function render_mentee( array $mentee, $total = 0, $mentor_id = 0 ) {
		$get = static function ( $key ) use ( $mentee ) {
			return isset( $mentee[ $key ] ) ? (string) $mentee[ $key ] : '';
		};

		$name        = $get( 'name' );
		$status      = $get( 'status' );
		$institution = WPCPM_Mentors_Sync::resolve_stored( $get( 'institution' ), 'institutions' );
		$team        = WPCPM_Mentors_Sync::resolve_stored( $get( 'team' ), 'teams' );

		$record  = $get( 'record_id' );
		$focused = ( '' !== $record && WPCPM_Mentor_Notes::focused_student() === $record );

		// The anchor is omitted without a record ID — an empty `id` would repeat on
		// every such card, and nothing can link to it anyway.
		printf(
			'<article class="wpcpm-mentee"%s>',
			( '' !== $record ) ? ' id="' . esc_attr( WPCPM_Mentor_Notes::anchor( $record ) ) . '"' : ''
		);

		// A native <details> rather than a scripted toggle: it collapses without
		// JavaScript, is keyboard operable and screen-reader announced for free, and
		// a mentor with sixty students gets a list they can actually scan. Opened by
		// default only when there is a single student, where collapsing helps nobody,
		// or when a note was just saved against this one — a fragment never reaches
		// the server, so returning to a closed card would hide the result.
		printf(
			'<details class="wpcpm-mentee__disclosure"%s>',
			( 1 === (int) $total || $focused ) ? ' open' : ''
		);

		echo '<summary class="wpcpm-mentee__summary">';

		self::render_avatar( $get( 'username' ), $get( 'email' ), $name, 48 );

		echo '<div class="wpcpm-mentee__identity">';
		echo '<h3 class="wpcpm-mentee__name">' . esc_html( $name ? $name : __( 'Unnamed student', 'wpcredits-program-manager' ) ) . '</h3>';
		if ( '' !== $status ) {
			// The modifier is the track where there is one, so a fourth track is one entry in
			// `WPCPM_Program` and nothing here. A paused student and one awaiting graduation get
			// their own, because they are still on this list and are not still working; a
			// finished student keeps the plain badge.
			$badge = WPCPM_Program::badge( $status );

			printf(
				'<span class="wpcpm-badge%1$s">%2$s</span>',
				'' === $badge ? '' : esc_attr( ' wpcpm-badge--' . $badge ),
				esc_html( WPCPM_Program::label( $status ) )
			);
		}

		// Enough to identify a student while collapsed, so the list is useful
		// without opening every row.
		$note_count = ( '' !== $record ) ? WPCPM_Mentor_Notes::count_notes( $record ) : 0;

		$preview = array_filter(
			array(
				$institution,
				$get( 'end' ) ? sprintf(
					/* translators: %s: internship end date. */
					__( 'until %s', 'wpcredits-program-manager' ),
					date_i18n( get_option( 'date_format' ), strtotime( $get( 'end' ) ) )
				) : '',
				// Shown collapsed so a mentor can see at a glance who they have
				// spoken to and who they have not.
				$note_count ? sprintf(
					/* translators: %s: number of notes. */
					_n( '%s note', '%s notes', $note_count, 'wpcredits-program-manager' ),
					number_format_i18n( $note_count )
				) : '',
			),
			'strlen'
		);

		if ( ! empty( $preview ) ) {
			echo '<span class="wpcpm-mentee__preview">' . esc_html( implode( ' · ', $preview ) ) . '</span>';
		}

		echo '</div>';

		echo '<span class="wpcpm-mentee__toggle" aria-hidden="true"></span>';
		echo '</summary>';

		echo '<div class="wpcpm-mentee__body">';

		// Declared as data so each field is one line to read. Rows are rendered even
		// when empty — silently dropping a blank value is indistinguishable from the
		// page forgetting the field, which is exactly how a missing institution
		// reads as a bug.
		$fields = array(
			array(
				'label' => __( 'Program', 'wpcredits-program-manager' ),
				// The program as people say it, linked to the course it runs on. The
				// Airtable status stays the storage value and is never shown.
				'value' => WPCPM_Program::label( $status ),
				'url'   => WPCPM_Program::course_url( $status ),
			),
			array(
				'label' => __( 'Internship duration', 'wpcredits-program-manager' ),
				'value' => self::format_dates( $get( 'start' ), $get( 'end' ) ),
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
				// Resolved above as well as at sync time: rows cached before
				// linked-record resolution existed still hold raw record IDs.
				'value' => $institution,
			),
			array(
				'label' => __( 'WordPress.org', 'wpcredits-program-manager' ),
				'value' => $get( 'username' ) ? '@' . $get( 'username' ) : '',
				'url'   => self::wporg_profile_url( $get( 'username' ), $get( 'profile' ) ),
				'icon'  => 'profile',
			),
			array(
				'label'    => __( 'Email', 'wpcredits-program-manager' ),
				'value'    => $get( 'email' ),
				// A mailto: link, so the obvious next action is one click away. Not
				// opened in a new tab — that leaves an empty one behind once the mail
				// client takes over.
				'url'      => $get( 'email' ) ? 'mailto:' . $get( 'email' ) : '',
				'external' => false,
				'icon'     => 'email',
			),
			array(
				'label' => __( 'Slack', 'wpcredits-program-manager' ),
				'value' => $get( 'slack' ),
				// Only linked when there is a handle to link; an empty row renders
				// "Not set" and drops the URL anyway.
				'url'   => $get( 'slack' ) ? self::slack_url() : '',
				'icon'  => 'slack',
			),
			array(
				'label'     => __( 'Contribution teams', 'wpcredits-program-manager' ),
				'value'     => $team,
				// Each team name links to its own site on make.wordpress.org, so the
				// cell is built as HTML rather than as one value with one URL.
				'html'      => WPCPM_Contribution_Teams::links( $team ),
				// The team's own icon labels the row, and a question mark labels it when
				// no team has been chosen.
				'icon_html' => WPCPM_Contribution_Teams::label_icon( $team ),
			),
			array(
				'label' => __( 'Personal website', 'wpcredits-program-manager' ),
				'value' => self::pretty_url( $get( 'website' ) ),
				'url'   => self::normalize_url( $get( 'website' ) ),
				'icon'  => 'website',
			),
			// Last, and only on the mentor's side: it is the one field here a mentor may
			// need to act on before a call, and it is the student's own disclosure.
			array(
				'label' => __( 'Accessibility needs', 'wpcredits-program-manager' ),
				'value' => $get( 'accessibility' ),
				// Blank here is an answer, not a gap: the student has none.
				'blank' => __( 'None', 'wpcredits-program-manager' ),
			),
		);

		// No <thead>: with two columns of label-and-value, "Field / Value" headings
		// only repeat what every row already says. The caption keeps the table
		// identifiable to a screen reader without printing anything.
		printf(
			'<table class="wpcpm-mentee__table"><caption class="screen-reader-text">%s</caption><tbody>',
			esc_html(
				sprintf(
					/* translators: %s: student name. */
					__( 'Program details for %s', 'wpcredits-program-manager' ),
					$name ? $name : __( 'this student', 'wpcredits-program-manager' )
				)
			)
		);

		foreach ( $fields as $field ) {
			self::render_row( $field );
		}

		echo '</tbody></table>';

		// The mentor's own notes on this student, beside the table. Rendered before the report so
		// that the source order matches what the grid draws — table and notes side by side, the
		// report underneath both — and so the single-column layout on a phone reads the same way.
		WPCPM_Mentor_Notes::render( $record, $name, $mentor_id );

		// The student's own report form, **read only**, in a disclosure of its own. The route it is
		// fetched from renders it as a record rather than as something to change, whoever is
		// looking: a mentor cannot edit it, and neither can a program manager reading a mentor's
		// page, because this is the mentor's view of it. Managers edit a report from the student's
		// own card, where the form belongs to the student it is about.
		//
		// **The body arrives when it is opened.** Reading a report costs an Airtable request, and a
		// mentor with sixty students would pay for sixty of them to look at one — so the card
		// ships the disclosure and the script fetches the one that gets opened.
		//
		// This is the one control on the page that needs JavaScript, and it says so rather than
		// spinning: everything else here — the disclosures, the notes form, printing — works
		// without it, so a silent "Loading…" would be the only dead thing on the page.
		if ( '' !== $record && WPCPM_Students_Sync::user_for_record( $record ) instanceof WP_User ) {
			printf(
				'<details class="wpcpm-report__disclosure wpcpm-mentee__report" data-wpcpm-report="%1$s">'
					. '<summary class="wpcpm-report__toggle">%2$s</summary>'
					. '<div class="wpcpm-report__body" data-wpcpm-report-body>'
					. '<p class="wpcpm-student__note">%3$s</p>'
					. '<noscript><p class="wpcpm-student__note">%4$s</p></noscript>'
					. '</div>'
					. '</details>',
				esc_attr( $record ),
				esc_html__( 'Report form', 'wpcredits-program-manager' ),
				esc_html__( 'Loading…', 'wpcredits-program-manager' ),
				esc_html__( 'This one needs JavaScript: the report is fetched when you open it, so that a page listing sixty students does not read sixty reports nobody asked for.', 'wpcredits-program-manager' )
			);
		}
		echo '</div>'; // .wpcpm-mentee__body
		echo '</details>';
		echo '</article>';
	}

	/**
	 * A tooltip naming the Airtable table and column a value came from.
	 *
	 * @param string $field Airtable field name.
	 * @param string $table Airtable table name. Defaults to Students Reports.
	 * @return string
	 */
	private static function source_title( $field, $table = '' ) {
		if ( '' === $table ) {
			$table = __( 'Students Reports', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: 1: Airtable table name, 2: Airtable field name. */
			__( 'Airtable · %1$s → %2$s', 'wpcredits-program-manager' ),
			$table,
			$field
		);
	}

	/**
	 * Render a profile photo from the person's WordPress.org profile.
	 *
	 * WordPress.org serves profile photos from Gravatar keyed on the hash of the
	 * *wordpress.org* account email, which this plugin never sees — so the hash
	 * cannot be derived locally. `grav-redirect.php` is wordpress.org's own
	 * username → avatar redirect, which avoids both scraping the profile page and
	 * storing a hash that would go stale when someone changes their photo. It
	 * returns a placeholder image rather than an error for an unknown user.
	 *
	 * Falls back to the program email's Gravatar for the students who have no
	 * WordPress.org profile recorded yet.
	 *
	 * @param string $username WordPress.org username.
	 * @param string $email    Email address, used only as a fallback.
	 * @param string $name     Person's name, for the alt text.
	 * @param int    $size     Requested pixel size.
	 */
	private static function render_avatar( $username, $email, $name, $size = 64 ) {
		$size = max( 24, (int) $size );
		$url  = self::avatar_url( $username, $email, $size );

		if ( '' === $url ) {
			return;
		}

		printf(
			'<img class="wpcpm-avatar" src="%1$s" srcset="%2$s 2x" width="%3$d" height="%3$d" alt="%4$s" title="%5$s" loading="lazy" decoding="async" />',
			esc_url( $url ),
			esc_url( self::avatar_url( $username, $email, $size * 2 ) ),
			(int) $size,
			esc_attr(
				$name
					/* translators: %s: person's name. */
					? sprintf( __( 'Profile photo of %s', 'wpcredits-program-manager' ), $name )
					: __( 'Profile photo', 'wpcredits-program-manager' )
			),
			esc_attr(
				$username
					? __( 'Photo from their WordPress.org profile', 'wpcredits-program-manager' )
					: __( 'Photo from Gravatar — no WordPress.org profile recorded', 'wpcredits-program-manager' )
			)
		);
	}

	/**
	 * Build an avatar URL for a WordPress.org username, or an email fallback.
	 *
	 * @param string $username WordPress.org username.
	 * @param string $email    Email address.
	 * @param int    $size     Pixel size.
	 * @return string Empty string when neither is available.
	 */
	public static function avatar_url( $username, $email, $size = 64 ) {
		$username = trim( (string) $username );
		$size     = max( 24, (int) $size );

		if ( '' !== $username ) {
			return add_query_arg(
				array(
					'user' => $username,
					's'    => $size,
					'd'    => 'mm',
				),
				'https://wordpress.org/grav-redirect.php'
			);
		}

		$email = trim( (string) $email );

		if ( is_email( $email ) ) {
			return (string) get_avatar_url(
				$email,
				array(
					'size'    => $size,
					'default' => 'mm',
				)
			);
		}

		return '';
	}

	/**
	 * The canonical profile URL for a username, falling back to the raw value.
	 *
	 * Rebuilt from the handle because the Airtable column holds a few malformed
	 * hosts that would otherwise produce a dead link.
	 *
	 * @param string $username Parsed username.
	 * @param string $raw      Raw profile value.
	 * @return string
	 */
	private static function wporg_profile_url( $username, $raw ) {
		if ( '' !== trim( (string) $username ) ) {
			return 'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/';
		}

		return trim( (string) $raw );
	}

	/**
	 * Ensure a URL has a scheme.
	 *
	 * Airtable's URL fields happily hold scheme-less values such as
	 * `personalweb729.wordpress.com`. Left alone that becomes a relative link to a
	 * path on this site; `esc_url()` would rescue it by prepending `http://`, but
	 * https is the better default in 2026.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}

		return $url;
	}

	/**
	 * Strip the scheme from a URL for display.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function pretty_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		return (string) preg_replace( '#^https?://#', '', untrailingslashit( $url ) );
	}

	/**
	 * Format the internship date range for display.
	 *
	 * @param string $start Start date, `Y-m-d`.
	 * @param string $end   End date, `Y-m-d`.
	 * @return string Empty string when neither date is set.
	 */
	public static function format_dates( $start, $end ) {
		$format = get_option( 'date_format' );
		$from   = $start ? date_i18n( $format, strtotime( $start ) ) : '';
		$to     = $end ? date_i18n( $format, strtotime( $end ) ) : '';

		if ( '' === $from && '' === $to ) {
			return '';
		}

		if ( '' !== $from && '' !== $to ) {
			/* translators: 1: start date, 2: end date. */
			return sprintf( __( '%1$s – %2$s', 'wpcredits-program-manager' ), $from, $to );
		}

		if ( '' !== $from ) {
			/* translators: %s: start date. */
			return sprintf( __( 'From %s', 'wpcredits-program-manager' ), $from );
		}

		/* translators: %s: end date. */
		return sprintf( __( 'Until %s', 'wpcredits-program-manager' ), $to );
	}

	/**
	 * Render one row of a student's details table.
	 *
	 * Three columns: the field, its value, and its description. The description
	 * is whatever Airtable holds for that column, falling back to the plugin's
	 * own wording until someone writes one in Airtable.
	 *
	 * An empty value renders as a muted placeholder rather than being skipped, so
	 * a gap in Airtable is visibly a gap in Airtable rather than a row that looks
	 * like the page forgot it.
	 *
	 * @param array $field {
	 *     The row to draw.
	 *
	 *     @type string $label     Visible label.
	 *     @type string $value     Display value; may be empty. Also decides whether the row
	 *                             counts as empty, even when `html` supplies the cell.
	 *     @type string $url       Optional URL to wrap the value in.
	 *     @type bool   $external  Whether the link opens in a new tab. Default true.
	 *     @type string $html      Pre-built, already-escaped cell markup, for a value that is
	 *                             several links rather than one. Takes precedence over `url`.
	 *     @type string $icon      Key into WPCPM_Icons for a fixed row icon.
	 *     @type string $icon_html Pre-built icon markup, for a row whose icon depends on its
	 *                             value. Takes precedence over `icon`.
	 *     @type string $blank     What an empty value *means*, when it means something. Shown as
	 *                             an ordinary value, and the row is not flagged as missing.
	 * }
	 */
	private static function render_row( array $field ) {
		$label    = isset( $field['label'] ) ? (string) $field['label'] : '';
		$value    = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';
		$url      = isset( $field['url'] ) ? trim( (string) $field['url'] ) : '';
		$external = ! isset( $field['external'] ) || (bool) $field['external'];
		$blank    = isset( $field['blank'] ) ? trim( (string) $field['blank'] ) : '';

		// **An empty value is not always missing data.** Most blanks on this card are something to
		// chase — an unset institution is a gap in the records — and the amber row says so. But a
		// student who wrote nothing under *Accessibility needs* has answered: they do not have any.
		// Showing that as "Not set" in amber asks a mentor to go and get an answer that is already
		// in, and quietly suggests the student left a form half-finished.
		//
		// So a field may declare what its blank means. It reads as an ordinary value and the row
		// keeps normal styling.
		if ( '' === $value && '' !== $blank ) {
			$value = $blank;
		}

		$empty = ( '' === $value );

		// `html` is for a value that is several links rather than one — a student can be
		// on more than one contribution team, so the whole cell is built by the caller
		// and already escaped. `value` still has to be set: it is what decides whether
		// the row counts as empty, and it is what a plain-text context would show.
		$html = isset( $field['html'] ) ? (string) $field['html'] : '';

		printf( '<tr class="wpcpm-mentee__row%s">', $empty ? ' is-empty' : '' );

		// `icon_html` for a row whose icon depends on its value — the contribution team's
		// changes with the team, and is a question mark when none is chosen. `icon` is the
		// fixed case, a key into WPCPM_Icons.
		$icon = isset( $field['icon_html'] )
			? (string) $field['icon_html']
			: ( isset( $field['icon'] ) ? WPCPM_Icons::svg( $field['icon'] ) : '' );

		printf(
			'<th scope="row" data-label="%1$s"><span class="wpcpm-mentee__label">%2$s%3$s</span></th>',
			esc_attr__( 'Field', 'wpcredits-program-manager' ),
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from WPCPM_Icons; kses would lowercase viewBox and break it.
			esc_html( $label )
		);

		printf( '<td class="wpcpm-mentee__value" data-label="%s">', esc_attr__( 'Value', 'wpcredits-program-manager' ) );

		if ( $empty ) {
			printf(
				'<span class="wpcpm-mentee__missing">%s</span>',
				esc_html__( 'Not set', 'wpcredits-program-manager' )
			);
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

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * A standalone notice, optionally with an action link.
	 *
	 * @param string $message     Message text.
	 * @param string $action_url  Optional action URL.
	 * @param string $action_text Optional action label.
	 * @param bool   $escape      Whether to escape the message. Pass false only for
	 *                              markup this class built itself.
	 * @return string
	 */
	private static function notice( $message, $action_url = '', $action_text = '', $escape = true ) {
		$html = '<div class="wpcpm-dashboard wpcpm-dashboard--notice"><p>' . ( $escape ? esc_html( $message ) : wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) ) . '</p>';

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
	 * Create the mentor page if it is missing, and gate it to Mentor level.
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

		$existing = get_page_by_path( 'mentor-dashboard' );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, $existing->ID, false );
			self::gate_page( $existing->ID );

			return $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Mentor Report Card', 'wpcredits-program-manager' ),
				'post_name'    => 'mentor-dashboard',
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
	 * Restrict the mentor page to Mentor level.
	 *
	 * @param int $page_id Page ID.
	 */
	private static function gate_page( $page_id ) {
		if ( ! get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ) ) {
			update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_MENTOR );
		}
	}

	/**
	 * The mentor page's permalink, if it exists.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		// Must be published, not merely existing: `get_post_status()` returns
		// 'trash' for a trashed page, which is truthy — and now that logins redirect
		// here, that would land every mentor on a 404.
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}
}
