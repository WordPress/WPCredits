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
 * The branch that matters is the locked one. An account whose Collaboration Agreement
 * nobody has accepted sees the identity header and the agreement panel and nothing else:
 * no comparison strip, no filter bar, no roster, no footer. It has no roster yet, and a
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

	/**
	 * The page's slug.
	 *
	 * Chosen once and never renamed, the way the mentor page's was: the theme matches its
	 * template on it, and a rename breaks that and every link anyone has been sent.
	 */
	const SLUG = 'institution-dashboard';

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
	 * The three pages share a shell - the colour tokens, the "view as" switcher, the detail
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
	 * Program managers are excluded - they need wp-admin - and so is anyone who can write
	 * posts, for the reason the mentor page gives: bouncing an editor out of the admin is
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

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_posts' ) ) {
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
		// an invitation and were bounced through the login form. Honour it; overriding would
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

		if ( ! is_user_logged_in() ) {
			return self::notice(
				__( 'Please log in to see your institution on this site.', 'wpcredits-program-manager' ),
				wp_login_url( self::current_url() ),
				__( 'Log in', 'wpcredits-program-manager' )
			);
		}

		$viewer     = wp_get_current_user();
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$record     = WPCPM_Institution_Roster::resolve_institution( $viewer, $can_manage );

		if ( '' === $record ) {
			// `WPCPM_Dashboards`, and not a sentence of this page's own: the three dashboards
			// answer the same question, and two copies of it are two wordings to keep true. It
			// names no institution, which is the point - a page that told a stranger which school
			// it would have shown would be a membership oracle, the shape
			// `WPCPM_Institution_Policy::refusal()` exists to avoid everywhere else.
			return self::notice( WPCPM_Dashboards::nothing_to_show( 'institutions', $can_manage ), '', '', false );
		}

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
		$member  = WPCPM_Institution_Policy::GROUND_MEMBER === $decision['ground'];
		$profile = self::profile( $record, $member ? $viewer : null );

		ob_start();

		echo '<div class="wpcpm-dashboard wpcpm-institution">';

		// The viewer's own account, never the institution being looked at: chrome rather than
		// content, which is why it sits above the header and outside the locked branch. Where
		// a member actually is is the only place a prompt to protect the account is seen, since
		// members are routed away from wp-admin the moment they sign in.
		WPCPM_Two_Factor::prompt( $viewer );

		if ( '' !== $atts['title'] ) {
			echo '<h2 class="wpcpm-dashboard__title">' . esc_html( $atts['title'] ) . '</h2>';
		}

		if ( $can_manage ) {
			self::render_switcher( $record );
		}

		self::render_identity( $profile );

		if ( $can_manage && ! $settled ) {
			self::render_banner( $record );
		}

		// Where to go with a question, directly under the institution's name rather than at the
		// foot of the page. The two Report Cards end on this section, and for them that is
		// right: a mentor comes to work through a list and the resources are what is left when
		// they are done. A school arrives with a question more often than with a task, and the
		// answer was three screens down past a roster they had not come to read. Drawn for a
		// locked account as well, which is the one most likely to need somebody to ask.
		self::render_help( $record );

		$context = self::context( $record, $can_manage );

		// The agreement panel first, and for a locked account last as well. It draws itself
		// from the summary state, so on a settled dashboard it is the piece that draws nothing
		// and the card at the foot carries the accepted date instead.
		self::card( 'WPCPM_Institution_Panel', $record, $context );

		if ( ! $locked ) {
			self::card( 'WPCPM_Institution_Roster_View', $record, $context );
			// After the roster and before the people: a school enrolling students has just
			// read the list it is adding to, and the question the form answers is the one they
			// arrived with. It draws nothing at all unless the site takes imports, so on every
			// site that has not switched them on this line costs one method call.
			self::card( 'WPCPM_Institution_Import_Form', $record, $context );
			// After the roster and the enrolment form, before the people: the report is written
			// from the roster a school has just read, and it folds closed unless the address
			// names a semester, so on an ordinary visit it is one row with a chevron.
			self::card( 'WPCPM_Semester_Report_Screen', $record, $context );
			self::card( 'WPCPM_Institution_People', $record, $context );
			self::card( 'WPCPM_Institution_Agreement_Card', $record, $context );
		}

		echo '</div>';

		return (string) ob_get_clean();
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
			// The read time of the data this page shows, printed by the roster's footer. The
			// index's own and never "now": the whole point of a per-institution index is that
			// every surface says how old the rows on it are.
			'read'       => (int) WPCPM_Roster_Index::read( $record_id )['read'],
		);
	}

	/**
	 * Draw one of the dashboard's cards, if the piece that owns it is installed.
	 *
	 * Phase 2's cards land as separate commits and any one of them may be absent from a
	 * checkout, so a missing card leaves a gap on the page rather than a fatal on the page a
	 * partner institution was invited to. Named as a string and called through
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
	 * The index row is what the last sync read, and the sync runs daily. The stamp is written
	 * once, by `WPCPM_Institution_Members::attach()`, and no sync refreshes it: read first, it
	 * would freeze the name, the city, the stage and the website at the day the account was
	 * attached, on a header that carries no read date to say so.
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
	 * @param string $website The institution's website, already normalised to a URL.
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
		// read as far as it was fetched, which is the old behaviour and still better than
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
	 * The Resources section, with this institution's own contact in it.
	 *
	 * The section is `WPCPM_Handbook_Assistant::render_resources()`, the one the Student and
	 * Mentor Report Cards end on, asked for the institution audience: its own handbook page,
	 * the program's Slack channel, the announcements at that access level, and the assistant.
	 * Written that way rather than as a Help section of this page's own so the three cards
	 * cannot come to offer help in three different shapes.
	 *
	 * The one thing added is the person. Every institution is routed to a program manager by
	 * country, and until now that name appeared only in a revoked agreement's panel, which is
	 * the worst possible moment to meet it for the first time.
	 *
	 * @param string $record Institutions record ID.
	 */
	private static function render_help( $record ) {
		if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) || ! method_exists( 'WPCPM_Handbook_Assistant', 'render_resources' ) ) {
			return;
		}

		echo WPCPM_Handbook_Assistant::render_resources( 'institution', self::contact_block( $record ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the assistant and by contact_block(), both of which escape what they interpolate.
	}

	/**
	 * Who this institution writes to, as the Resources section's last word.
	 *
	 * The routing is by country and lives in `WPCPM_Countries`, which holds a name, an address
	 * and sometimes a booking link for each. A country the program has not routed yet prints
	 * nothing at all rather than a heading with a blank under it: an empty contact block would
	 * read as "you have nobody", which is not what an unrouted country means.
	 *
	 * @param string $record Institutions record ID.
	 * @return string Escaped markup, or ''.
	 */
	private static function contact_block( $record ) {
		$row = WPCPM_Institutions_Index::row( $record );

		$country = ( is_array( $row ) && isset( $row['country'] ) ) ? trim( (string) $row['country'] ) : '';

		if ( '' === $country || ! class_exists( 'WPCPM_Countries' ) ) {
			return '';
		}

		$routing = WPCPM_Countries::routing( $country );

		if ( ! is_array( $routing ) ) {
			return '';
		}

		$name     = isset( $routing['manager'] ) ? trim( (string) $routing['manager'] ) : '';
		$email    = isset( $routing['email'] ) ? trim( (string) $routing['email'] ) : '';
		$calendly = isset( $routing['calendly'] ) ? trim( (string) $routing['calendly'] ) : '';

		// A record ID in the name means the linked manager was never resolved to a person; it
		// is a database key and nothing to show a school.
		if ( '' !== $name && WPCPM_Mentors_Sync::is_record_id( $name ) ) {
			$name = '';
		}

		if ( '' === $name && '' === $email && '' === $calendly ) {
			return '';
		}

		$out  = '<div class="wpcpm-resources__contact">';
		$out .= sprintf(
			'<h3 class="wpcpm-student__heading">%s</h3>',
			esc_html__( 'Your contact at the program', 'wpcredits-program-manager' )
		);

		if ( '' !== $name ) {
			$out .= sprintf( '<p class="wpcpm-resources__contact-name">%s</p>', esc_html( $name ) );
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
			$out .= '<p class="wpcpm-resources__contact-links">' . implode( ' &middot; ', $lines ) . '</p>';
		}

		$out .= sprintf(
			'<p class="wpcpm-student__note">%s</p>',
			esc_html__( 'They look after institutions in your country. Write to them about anything the guide does not answer.', 'wpcredits-program-manager' )
		);

		return $out . '</div>';
	}

	/**
	 * The identity header.
	 *
	 * A paragraph and not a heading, the same shape the mentor page's own name uses: the page
	 * is "Institution Dashboard" and that `<h1>` is the page's. Two of them is not a document
	 * outline.
	 *
	 * @param array $profile Facts from `profile()`.
	 */
	private static function render_identity( array $profile ) {
		$name = '' !== $profile['name']
			? $profile['name']
			// Two records in the base carry no name at all. Saying so is better than an empty
			// line, and the manager screen is where it gets fixed.
			: __( 'Unnamed institution', 'wpcredits-program-manager' );

		$website = self::website_url( $profile['website'] );

		echo '<header class="wpcpm-institution__identity">';

		// The institution's own site icon beside its name, which is the nearest thing an
		// institution has to the profile photo a mentor and a student get. Resolved from the
		// website the program records and cached, and printed only when the site actually
		// answered with one: an icon that 404s is a broken image where a crest should be, and
		// a placeholder crest for a university that has its own is worse than nothing.
		$icon = self::site_icon( $website );

		// The icon sits beside the whole block rather than beside the title line: the name, the
		// place, the site and the two facts are one identity, and an icon against the first
		// line of it reads as a bullet on that line instead. The same arrangement the Mentor
		// Report Card uses for a mentor's photo.
		if ( '' !== $icon ) {
			// **`onerror` because the server proving it and the reader loading it are two
			// different questions.** The HEAD below happens on this site, from a host that can
			// reach the institution's server; a program manager on another continent may not,
			// and a university that answers Warsaw in 40ms can time out from anywhere else. An
			// `<img>` that fails leaves a browser's broken-image glyph beside the name, which
			// is worse than the nothing this feature promises when there is no icon. Removing
			// itself takes the gap with it. The handler is the same shape as the confirm on
			// the People card: one expression, no script file, nothing to load.
			printf(
				'<img class="wpcpm-institution__icon" src="%1$s" alt="" width="48" height="48" loading="lazy" decoding="async" onerror="this.remove()" />',
				esc_url( $icon )
			);
		}

		echo '<div class="wpcpm-institution__details">';
		printf( '<p class="wpcpm-institution__name">%s</p>', esc_html( $name ) );

		// Sentences rather than a row of values joined by punctuation, so a missing city or a
		// missing contact leaves no stray separator behind.
		// One fact to a line, in the order somebody reads them: where the institution is, how to
		// reach it, where the program has got to with it, and who the program writes to. They
		// used to run together in one sentence, which kept a missing city from leaving a stray
		// separator behind; each line carries its own label now, so a line that is not there is
		// simply not there and nothing needs joining.
		//
		// No full stops: these are labelled values on their own lines, not sentences, and a
		// trailing point on "Stage: Confirmed" reads as a typo rather than as grammar.
		$place = array_values( array_filter( array( $profile['city'], $profile['country_name'] ) ) );

		if ( ! empty( $place ) ) {
			printf( '<p class="wpcpm-dashboard__intro">%s</p>', esc_html( implode( ', ', $place ) ) );
		}

		if ( '' !== $website ) {
			printf(
				'<p class="wpcpm-institution__website"><a href="%1$s" rel="external noopener">%2$s</a></p>',
				esc_url( $website ),
				esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) )
			);
		}

		if ( '' !== $profile['stage'] ) {
			printf(
				'<p class="wpcpm-institution__fact wpcpm-institution__stage">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the Airtable Current Stage, e.g. "Confirmed". */
						__( 'Stage: %s', 'wpcredits-program-manager' ),
						$profile['stage']
					)
				)
			);
		}

		if ( '' !== $profile['contact_person'] ) {
			printf(
				'<p class="wpcpm-institution__fact wpcpm-institution__contact">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the institution's named contact person. */
						__( 'Contact: %s', 'wpcredits-program-manager' ),
						$profile['contact_person']
					)
				)
			);
		}

		echo '</div>';
		echo '</header>';
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
	 * The banner a manager sees over an institution whose agreement is not settled.
	 *
	 * It names the state because the manager's next move depends on it: a submitted copy is
	 * theirs to review, a `none` is the institution's to act on, and a disagreement between
	 * the two sources is neither until somebody looks. The members of this institution see
	 * the panel instead, and this banner never reaches them.
	 *
	 * @param string $record_id Institutions record ID.
	 */
	private static function render_banner( $record_id ) {
		$summary = WPCPM_Institution_Agreement::summary( $record_id );

		echo '<div class="wpcpm-institution__banner">';
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: the agreement state in words, e.g. "not started". */
					__( 'This institution\'s agreement is not settled: %s. Members see only the agreement panel.', 'wpcredits-program-manager' ),
					self::state_label( $summary['state'] )
				)
			)
		);

		// The other half of the predicate, named rather than left to be guessed at: the gate is
		// closed when either source says so, and a manager reading "a signed copy is waiting"
		// needs to know whether the base is what is holding it.
		if ( '' !== $summary['airtable_status'] ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the Airtable Agreement Status, e.g. "Awaiting review". */
						__( 'Airtable records the Agreement Status as %s.', 'wpcredits-program-manager' ),
						$summary['airtable_status']
					)
				)
			);
		}

		echo '</div>';
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
	 * member appending it to the URL changes nothing.
	 *
	 * @param string $current Institutions record ID currently being viewed.
	 */
	private static function render_switcher( $current ) {
		$options = WPCPM_Institution_Roster::switcher_options();

		// One institution is not a choice, and a select with a single option is a control that
		// cannot do anything. The mentor page's switcher makes the same call.
		if ( count( $options ) < 2 ) {
			return;
		}

		echo '<form class="wpcpm-dashboard__switcher" method="get">';

		// Without pretty permalinks the page is addressed by query string, which a GET form
		// would otherwise discard - resubmitting to the site root.
		if ( ! get_option( 'permalink_structure' ) ) {
			$queried = get_queried_object_id();

			if ( $queried ) {
				printf( '<input type="hidden" name="page_id" value="%d" />', (int) $queried );
			}
		}

		printf(
			'<label for="wpcpm-institution-switcher">%s</label> ',
			esc_html__( 'Viewing as institution', 'wpcredits-program-manager' )
		);
		// The roster's constant, not a copy: `resolve_institution()` is what reads this field,
		// and a switcher posting a name it does not read is a control that does nothing.
		printf( '<select name="%s" id="wpcpm-institution-switcher">', esc_attr( WPCPM_Institution_Roster::ARG_VIEW ) );

		foreach ( $options as $record_id => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $record_id ),
				selected( $record_id, $current, false ),
				esc_html( $label )
			);
		}

		echo '</select> ';
		printf( '<button type="submit" class="wpcpm-button">%s</button>', esc_html__( 'Show', 'wpcredits-program-manager' ) );
		printf(
			'<span class="wpcpm-dashboard__switcher-note">%s</span>',
			esc_html__( 'Only program managers see this control.', 'wpcredits-program-manager' )
		);
		echo '</form>';
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
