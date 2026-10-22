<?php
/**
 * The Sponsor Dashboard: a sponsor's sponsorship, its offers, its mentors and its company.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page a sponsor's representatives sign in to.
 *
 * `WPCPM_Administrators_Dashboard`'s shape for the page itself (one page the module creates
 * and adopts by slug, gated with `metadata_exists()`, a block and a shortcode that both reach
 * `render()`) and `WPCPM_Institutions_Dashboard`'s for login routing (design spec of
 * 4 September 2026, section 4): the sponsor's agreement is optional in this release
 * (`WPCPM_Sponsor_Policy::ungated()` is every action), so unlike the institution page there is
 * no locked branch here - a member's claim is never conditional on anything this page draws.
 *
 * Every card on the page is drawn in the shell the Administrator Dashboard's cards share,
 * `WPCPM_Dashboard_Card`: a section around a disclosure keyed `wpcpm-sponsor-<card>`, so `leave()`
 * can open the one a flash names, and a link to that anchor opens it through the dashboards'
 * opener, `assets/js/dashboard-open.js`. Each card opens by what it holds and says its state on its
 * row, with its action after the fold while it is folded, where it has one. The cards live in their
 * own classes; this class owns the page, the gate, the identity, the At a glance strip whose tiles
 * link to the cards, the four groups the cards are drawn in, the read-only People card, and below
 * the cards the program's updates and resources with the program contact.
 */
final class WPCPM_Sponsors_Dashboard {

	/** The shortcode, for a page the plugin did not create. */
	const SHORTCODE = 'wpcpm_sponsor_dashboard';
	/** The block. */
	const BLOCK = 'wpcpm/sponsor-dashboard';
	/** The page the plugin created, by ID. */
	const OPT_PAGE = 'wpcpm_sponsor_page_id';
	/** The stylesheet handle; the theme's skin depends on it when it is registered. */
	const STYLE = 'wpcpm-sponsor-dashboard';
	/** The slug, which is also the theme template's name: `page-sponsor-dashboard.html`. */
	const SLUG = 'sponsor-dashboard';
	/** Whether the title rename for TITLE_VERSION has run. */
	const OPT_TITLE_FIXED = 'wpcpm_sponsor_page_title_fixed';
	/** Bumped when the product renames the page; `maybe_rename_page()` follows once. */
	const TITLE_VERSION = 1;
	/** Titles a previous version gave the page. None yet. */
	const OLD_TITLES = array();
	/** The flash channel every card's handler ends on. */
	const FLASH = 'sponsor_dashboard';
	/** The module id `WPCPM_Dashboards::nothing_to_show()` knows. */
	const MODULE = 'sponsors';
	/**
	 * The most characters of an event's title the At a glance tile carries: about as long as the
	 * other tiles' labels, so the title leaves room for how many joined.
	 */
	const GLANCE_TITLE = 24;

	/**
	 * Hooks. Called from `WPCPM_Sponsors::boot()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_rename_page' ), 20 );

		// Make the page reachable, the way the institution page is: without these a sponsor's
		// representative has an account and a page gated to their level, and no route to it.
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'replace_admin_dashboard' ) );
	}

	/**
	 * The stylesheet, the shortcode and the block.
	 */
	public static function register() {
		self::register_assets();

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/sponsor-dashboard';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
		}
	}

	/**
	 * Register (but do not enqueue) the stylesheet.
	 *
	 * It depends on the mentor stylesheet, exactly as the institution page's does: the pages
	 * share a shell - the colour tokens, the "view as" switcher, the disclosure cards, the
	 * muted-text treatment - and defining that once means a theme styling one page has already
	 * styled this one. It depends on the Administrator Dashboard's too, for the same reason: the
	 * At a glance strip is that page's attention strip, its tiles dressed by that page's rules, so
	 * the two strips cannot drift apart. Each sheet is registered here first by the class that owns
	 * it, since WordPress prints nothing for a sheet whose dependency nobody registered.
	 */
	public static function register_assets() {
		WPCPM_Mentors_Dashboard::register_assets();
		WPCPM_Administrators_Dashboard::register_assets();

		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/sponsor.css',
				array( WPCPM_Mentors_Dashboard::STYLE, WPCPM_Administrators_Dashboard::STYLE ),
				WPCPM_VERSION
			);
		}
	}

	/**
	 * The block's render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * The page title, written once.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Sponsor Dashboard', 'wpcredits-program-manager' );
	}

	/**
	 * Rename the page once per TITLE_VERSION, only when it still carries an old title.
	 */
	public static function maybe_rename_page() {
		if ( (int) get_option( self::OPT_TITLE_FIXED ) >= self::TITLE_VERSION ) {
			return;
		}

		// Written first, whatever happens next: a rename that fails should not be retried on
		// every request of every visitor.
		update_option( self::OPT_TITLE_FIXED, self::TITLE_VERSION, false );

		$page_id = (int) get_option( self::OPT_PAGE );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( $page instanceof WP_Post && in_array( (string) $page->post_title, self::OLD_TITLES, true ) ) {
			wp_update_post(
				array(
					'ID'         => $page_id,
					'post_title' => self::title(),
				)
			);
		}
	}

	/**
	 * The page: the stored one, else the one at the slug, else a new one. Gated either way.
	 *
	 * A site that has the page but not the option (restored from a backup, or migrated) adopts
	 * it rather than creating a second one at `sponsor-dashboard-2`.
	 *
	 * @return int The page ID, or 0.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id ) {
			$page = get_post( $page_id );

			if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
				self::gate_page( $page_id );

				return $page_id;
			}
		}

		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
			update_option( self::OPT_PAGE, (int) $existing->ID, false );
			self::gate_page( (int) $existing->ID );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => self::title(),
				'post_name'    => self::SLUG,
				'post_content' => '<!-- wp:' . self::BLOCK . ' /-->',
			),
			true
		);

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, false );
		self::gate_page( (int) $page_id );

		return (int) $page_id;
	}

	/**
	 * Gate the page to the sponsor level, once.
	 *
	 * `metadata_exists()` and not the value: the level is registered with a default of
	 * `public`, so `get_post_meta()` reads a brand-new page as deliberately public and never
	 * gates it, which is how the Institution Dashboard first came up on the live site.
	 *
	 * @param int $page_id The page.
	 */
	private static function gate_page( $page_id ) {
		if ( ! metadata_exists( 'post', (int) $page_id, WPCPM_Content_Access::META_KEY ) ) {
			update_post_meta( (int) $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_SPONSOR );
		}
	}

	/**
	 * The page's address, or '' while there is no published page.
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

	/**
	 * Whether this account acts for a sponsor right now.
	 *
	 * Membership, never the role, for the reason the institution entry gives: an account keeps
	 * `ROLE_SPONSOR` until a manager takes it away, and this question is about the people
	 * currently acting for a sponsor.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function is_member( $user = null ) {
		return WPCPM_Sponsor_Members::is_member( $user );
	}

	/**
	 * Whether this user should be routed to the sponsor page.
	 *
	 * Membership first, then three exclusions. The first two match the institution page's own
	 * `should_route()`: a mentor is excluded, because an account that both mentors and
	 * represents a sponsor is routed to the Mentor Report Card, matching the institution page's
	 * own rule for exactly that account shape; a program manager needs wp-admin and is left
	 * alone. The third exclusion deliberately diverges: anyone who edits other people's posts
	 * is left alone too, because a sponsor member is granted posting capabilities by
	 * WPCPM_Sponsor_Posts while an institution member never is.
	 *
	 * That last clause used to ask `edit_posts`. It was written in Phase S1, when a sponsor's
	 * representative held no capability at all, and Phase S3 then granted `edit_posts`,
	 * `delete_posts` and `upload_files` to every member of every sponsor whose posting flag is
	 * on, which is the default: the clause excluded exactly the people the routing exists for,
	 * so `login_redirect()` and `replace_admin_dashboard()` were both dead on a default install
	 * and the `sponsor_home` setting did nothing (FSPON-3). `edit_others_posts` asks what was
	 * meant: an editor or an administrator works in wp-admin, a representative writing the
	 * sponsor's own posts does not.
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

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_others_posts' ) ) {
			return false;
		}

		return (bool) WPCPM_Settings::get_value( 'sponsor_home' );
	}

	/**
	 * Send a sponsor's representatives to their page when they log in.
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

		// A specific destination was asked for - honour it, the way the institution page does:
		// overriding would take them somewhere they did not ask to go.
		if ( WPCPM_Request::is_explicit_redirect( $requested_redirect_to ) ) {
			return $redirect_to;
		}

		return $page;
	}

	/**
	 * Use the sponsor page in place of the wp-admin Dashboard.
	 *
	 * Only the Dashboard itself is redirected; `profile.php` is left alone so a member can
	 * still change their own password and name.
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
	 * Flash an outcome and go back to the page, at the card that produced it.
	 *
	 * Every card's handler ends here (design spec of 4 September 2026, section 5.4: "every
	 * outcome travels by flash on channel `sponsor_dashboard`, taken once at the top"). A
	 * manager came through the switcher and goes back through it; a member's stamp is the
	 * sponsor, so the argument is not needed and not added.
	 *
	 * @param string   $status A key of `messages()`.
	 * @param string   $card   The card to open, or ''.
	 * @param string   $record The sponsor a manager was looking at, as a sponsor key, or ''. A key
	 *                         is a migrated record ID or a site key `mint_key()` made, so it is
	 *                         checked with `WPCPM_Sponsors_Index::is_key()`, not the record-ID
	 *                         check, once trimmed; anything else is left out of the address, as on
	 *                         the Institution Dashboard.
	 * @param string   $detail A sentence for the reader after the status's own, or ''. Trimmed
	 *                         to three hundred characters: a status names the outcome, the
	 *                         detail names the line.
	 * @param string[] $args   The values the status's sentence names, `%1$s` and on, in its order;
	 *                         none for a sentence that names none. Kept beside the status rather
	 *                         than joined to it here, so a sentence that names a day stays one
	 *                         sentence, a card's own words, filled in where the flash is read.
	 */
	public static function leave( $status, $card, $record = '', $detail = '', array $args = array() ) {
		$flash = array(
			'status' => sanitize_key( (string) $status ),
			'card'   => sanitize_key( (string) $card ),
			'detail' => mb_substr( sanitize_text_field( (string) $detail ), 0, 300 ),
		);

		if ( array() !== $args ) {
			$flash['args'] = array_map( 'sanitize_text_field', array_map( 'strval', array_values( $args ) ) );
		}

		WPCPM_Flash::set( self::FLASH, $flash );

		$url = self::page_url();
		$url = '' === $url ? home_url( '/' ) : $url;

		$record = is_scalar( $record ) ? trim( (string) $record ) : '';

		if ( WPCPM_Sponsors_Index::is_key( $record ) && current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$url = add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $record, $url );
		}

		if ( '' !== (string) $card ) {
			$url .= '#wpcpm-sponsor-' . sanitize_key( (string) $card );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Every card's outcomes, in the reader's words.
	 *
	 * The union of every card's own `messages()`, in the order the page draws the cards, each
	 * guarded by `class_exists()` so a checkout missing a later phase's card still renders the
	 * page. `refused` is the base: every card's own copy of it says the same thing, so whichever
	 * wins the merge reads identically. Every card `render()` draws is in the list, the badge card
	 * (1.113.0) and the Reports card (1.115.0) too, whose own lists are empty while they have no
	 * form, so the cards drawn and the cards merged stay one list. The Events card's planner
	 * (1.117.0) follows the tier card that draws it, and the logo the profile card that holds it:
	 * their presses leave through this page's `leave()` like every card's. So does a decision of the
	 * offers' review (1.123.18) posted without the Administrator Dashboard's way back, which follows
	 * the Offers card it decides for. Offer more, the interests card, left the page in 1.123.22, and its
	 * statuses with it: a flash one left before then names a status the page no longer knows, and
	 * prints nothing, as every unknown status prints nothing.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to notice class and sentence.
	 */
	public static function messages() {
		$messages = array(
			'refused' => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);

		foreach ( array( 'WPCPM_Sponsor_Tier_Card', 'WPCPM_Sponsor_Event_Planner', 'WPCPM_Sponsor_Report_Card', 'WPCPM_Sponsor_Badge', 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Offer_Review', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Posts', 'WPCPM_Sponsor_Mentors', 'WPCPM_Sponsor_Profile', 'WPCPM_Sponsor_Logo', 'WPCPM_Sponsor_Agreement_Card' ) as $card ) {
			if ( class_exists( $card ) && method_exists( $card, 'messages' ) ) {
				$messages = array_merge( $messages, (array) call_user_func( array( $card, 'messages' ) ) );
			}
		}

		return $messages;
	}

	/**
	 * The page. The block and the shortcode both land here.
	 *
	 * @param array $attributes `title`.
	 * @return string
	 */
	public static function render( $attributes = array() ) {
		self::register_assets();
		wp_enqueue_style( self::STYLE );
		self::enqueue_forms();

		if ( ! is_user_logged_in() ) {
			return self::notice(
				__( 'Sign in to see the Sponsor Dashboard.', 'wpcredits-program-manager' ),
				wp_login_url( get_permalink() ),
				__( 'Sign in', 'wpcredits-program-manager' )
			);
		}

		$viewer     = wp_get_current_user();
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$record     = WPCPM_Sponsor_Roster::resolve_sponsor( $viewer, $can_manage );

		if ( '' === $record ) {
			return self::notice( WPCPM_Dashboards::nothing_to_show( self::MODULE, $can_manage ) );
		}

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_VIEW_DASHBOARD, WPCPM_Sponsor_Policy::subject_sponsor( $record ), $viewer );

		if ( empty( $decision['allowed'] ) ) {
			// The same sentence as "no sponsor": the page never says "not yours".
			return self::notice( WPCPM_Dashboards::nothing_to_show( self::MODULE, $can_manage ) );
		}

		$stored  = WPCPM_Sponsors_Index::row( $record );
		$row     = is_array( $stored ) ? $stored : WPCPM_Sponsors_Index::empty_row();
		$flash   = WPCPM_Flash::take( self::FLASH );
		$flash   = is_array( $flash ) ? $flash : array();
		$context = array(
			'can_manage' => $can_manage,
			'open'       => isset( $flash['card'] ) ? (string) $flash['card'] : '',
			'viewer'     => $viewer,
		);

		ob_start();

		echo '<div class="wpcpm-dashboard wpcpm-sponsor">';

		WPCPM_Two_Factor::prompt( $viewer );

		// The heading is the block's or the shortcode's own attribute, empty by default so the
		// page's title is not printed twice; the page title itself is title()'s business.
		$atts = shortcode_atts( array( 'title' => '' ), is_array( $attributes ) ? $attributes : array(), self::SHORTCODE );

		if ( '' !== trim( (string) $atts['title'] ) ) {
			printf( '<h2 class="wpcpm-dashboard__title">%s</h2>', esc_html( trim( (string) $atts['title'] ) ) );
		}

		if ( $can_manage ) {
			self::render_switcher( $record );
		}

		self::render_message( $flash );
		self::render_identity( $record, $row, $can_manage );
		self::render_glance( $record, is_array( $stored ) ? $stored : null, $context );

		// Four groups, in the order the owner approved on 27 September 2026, which is the order a
		// sponsor works through the page: the sponsorship itself (the tier card's two cards, the tier
		// with its term and the events, then the reports and the badge, all four drawn only while the
		// tier ladder's launch switch is on), what the company offers (offers and codes, usage and its
		// posts), the mentors (its own and the ones looking for a sponsor, both drawn by one class),
		// and the company last (the profile and logo, the people who act for it, which is this class's
		// own card, and the Collaboration Agreement). Each group is a heading over its cards, so the
		// four read as groups and not as one long list with the same hairline between every card (the
		// owner, 6 September 2026). Offer more, what else the company would support, left the second
		// group in 1.123.22: its six choices were the sponsor application's before the levels, and its
		// Tell us said what the Sponsorship card's Talk to your program contact says.
		$groups = array(
			array(
				'id'    => 'sponsorship',
				'title' => __( 'Your sponsorship', 'wpcredits-program-manager' ),
				'lead'  => self::sponsorship_lead( $record, $row, is_array( $stored ) ? $stored : null ),
				'cards' => array( 'WPCPM_Sponsor_Tier_Card', 'WPCPM_Sponsor_Report_Card', 'WPCPM_Sponsor_Badge' ),
			),
			array(
				'id'    => 'offer',
				'title' => __( 'What you offer', 'wpcredits-program-manager' ),
				// What the company wrote for them is its posts: named only while the group draws the
				// Posts card, asked of the card's own test, which below Backer draws it only while the
				// sponsor has a post of its own (1.123.22). Two whole sentences, one for each.
				'lead'  => self::installed( 'WPCPM_Sponsor_Posts' ) && method_exists( 'WPCPM_Sponsor_Posts', 'draws' ) && WPCPM_Sponsor_Posts::draws( $record )
					? __( 'What your company gives the program\'s students and mentors, and what it wrote for them.', 'wpcredits-program-manager' )
					: __( 'What your company gives the program\'s students and mentors.', 'wpcredits-program-manager' ),
				'cards' => array( 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Posts' ),
			),
			array(
				'id'    => 'mentors',
				'title' => __( 'Mentors', 'wpcredits-program-manager' ),
				'lead'  => __( 'The mentors your company sponsors, and the ones looking for a sponsor.', 'wpcredits-program-manager' ),
				'cards' => array( 'WPCPM_Sponsor_Mentors' ),
			),
			array(
				'id'    => 'company',
				'title' => __( 'Your company', 'wpcredits-program-manager' ),
				'lead'  => __( 'Your company\'s details and logo, the people who can sign in for it, and the Collaboration Agreement.', 'wpcredits-program-manager' ),
				'cards' => array( 'WPCPM_Sponsor_Profile', 'people', 'WPCPM_Sponsor_Agreement_Card' ),
			),
		);

		foreach ( $groups as $group ) {
			// Each group in the one shape both dashboards draw their groups in, whose classes are this
			// page's unless a page asks for its own: a group whose cards all print nothing, or nothing
			// but white space, is not printed at all, rather than a heading and a sentence over nothing.
			// The first group's cards print nothing while the launch switch is off, so until the program
			// announces the tiers the page does not name that group either. What the group's cards hold
			// is said once, in the group's own line, rather than inside each folded card (the owner, 6
			// September 2026); the cards keep only the notes that belong to a state.
			WPCPM_Dashboard_Card::group(
				array(
					'id'    => $group['id'],
					'title' => $group['title'],
					'lead'  => $group['lead'],
					'cards' => static function () use ( $group, $record, $context ) {
						foreach ( $group['cards'] as $owner ) {
							if ( 'people' === $owner ) {
								self::render_people( $record, $context );
								continue;
							}

							self::card( $owner, $record, $context );
						}
					},
				)
			);
		}

		// Below the cards, where the design the owner approved on 27 September 2026 puts them: the
		// cards answer how the sponsorship is doing without a click, so they take the first screen,
		// and what the program publishes, its guide and the program contact follow them.
		self::render_help( $record );

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * The Your sponsorship group's line: the cards the group draws for this sponsor, named in the
	 * group's order (1.123.22), where every sponsor read "Your tier, your events, your reports and
	 * your badge." though a Friend has none of the three and a Supporter or a Backer no events.
	 *
	 * Each card is asked the test that decides whether it draws, as the At a glance strip asks it: the
	 * Events card, the tier card's second, the tier card's own `draws_events()`; the Reports card
	 * whether the sponsor has a final report (`WPCPM_Sponsor_Report_Card::finals()`); the badge
	 * whether the store holds the sponsor's row and the row is eligible for one
	 * (`WPCPM_Sponsor_Badge::eligible()`). The tier card always draws while the launch switch is on,
	 * so every line names the tier first. Each of the eight lines is one whole sentence, so a
	 * translator meets each as it reads. While the switch is off the group draws nothing and is not
	 * printed, so nothing is asked and the line is empty.
	 *
	 * @param string     $record Sponsor key.
	 * @param array      $row    The sponsor's row, or the empty row, as the tier card reads it.
	 * @param array|null $stored The row the store holds, as the badge card reads it, or null.
	 * @return string
	 */
	private static function sponsorship_lead( $record, array $row, $stored ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return '';
		}

		$events  = self::installed( 'WPCPM_Sponsor_Tier_Card' ) && WPCPM_Sponsor_Tier_Card::draws_events( $record, $row, WPCPM_Sponsor_Tiers::effective( $row, WPCPM_Sponsor_Tiers::today() ) );
		$reports = self::installed( 'WPCPM_Sponsor_Report_Card' ) && class_exists( 'WPCPM_Sponsor_Report' ) && array() !== WPCPM_Sponsor_Report_Card::finals( $record );
		$badge   = self::installed( 'WPCPM_Sponsor_Badge' ) && is_array( $stored ) && WPCPM_Sponsor_Badge::eligible( $stored );

		if ( $events ) {
			if ( $reports ) {
				return $badge
					? __( 'Your tier, your events, your reports and your badge.', 'wpcredits-program-manager' )
					: __( 'Your tier, your events and your reports.', 'wpcredits-program-manager' );
			}

			return $badge
				? __( 'Your tier, your events and your badge.', 'wpcredits-program-manager' )
				: __( 'Your tier and your events.', 'wpcredits-program-manager' );
		}

		if ( $reports ) {
			return $badge
				? __( 'Your tier, your reports and your badge.', 'wpcredits-program-manager' )
				: __( 'Your tier and your reports.', 'wpcredits-program-manager' );
		}

		return $badge
			? __( 'Your tier and your badge.', 'wpcredits-program-manager' )
			: __( 'Your tier and what it includes.', 'wpcredits-program-manager' );
	}

	/**
	 * Register (once) and enqueue the page's three scripts: the double-submit guard every form on
	 * this page carries, the Copy button the Badge card carries for its embed code, and the
	 * dashboards' opener, which the shell registers (`WPCPM_Dashboard_Card::enqueue_opener()`) and
	 * which opens the card a link lands on (a browser following a link to a closed disclosure leaves
	 * it closed), an At a glance tile's among them. All three are enqueued before any card is drawn,
	 * so none waits on which cards the page holds; the Copy script does nothing on a page without a
	 * Copy button, and the opener nothing until a fragment names a card or a link to one is pressed.
	 */
	private static function enqueue_forms() {
		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( 'wpcpm-forms' );

		if ( ! wp_script_is( 'wpcpm-copy', 'registered' ) ) {
			wp_register_script( 'wpcpm-copy', WPCPM_PLUGIN_URL . 'assets/js/copy.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( 'wpcpm-copy' );

		WPCPM_Dashboard_Card::enqueue_opener();
	}

	/**
	 * Draw one of the dashboard's cards, if the piece that owns it is installed.
	 *
	 * Phase S2 to S4's cards landed as separate commits and any one of them may be absent from a
	 * checkout, so a missing card leaves a gap on the page rather than a fatal. The owners are
	 * named in `render()`'s groups, so the page order is fixed in one place - a card that is not
	 * called from `render()` is not on the dashboard, and a group none of whose cards drew is not
	 * printed.
	 *
	 * @param string $owner     Class that owns the card.
	 * @param string $record_id Sponsor key being rendered.
	 * @param array  $context   What every card is handed.
	 */
	private static function card( $owner, $record_id, array $context ) {
		if ( ! self::installed( $owner ) ) {
			return;
		}

		call_user_func( array( $owner, 'render' ), $record_id, $context );
	}

	/**
	 * Whether a card's class is installed: the one question `card()` asks before it draws a card, and
	 * the At a glance strip before it draws that card's tile.
	 *
	 * @param string $owner Class that owns the card.
	 * @return bool
	 */
	private static function installed( $owner ) {
		return class_exists( $owner ) && method_exists( $owner, 'render' );
	}

	/**
	 * A "view as" control for program managers.
	 *
	 * Keyed by record and not by user, because with several members per sponsor a user is the
	 * wrong unit: two accounts at one company are one entry here. Only a manager ever sees it,
	 * and `resolve_sponsor()` does not even read the argument for anyone else, so a member
	 * appending it to the URL changes nothing. Drawn by `WPCPM_Dashboards`, the one form all four
	 * dashboards share, as the Institution Dashboard draws its own.
	 *
	 * @param string $current Sponsor key currently being viewed.
	 */
	private static function render_switcher( $current ) {
		WPCPM_Dashboards::render_switcher(
			array(
				'id'      => 'wpcpm-sponsor-switcher',
				'name'    => WPCPM_Sponsor_Roster::ARG_VIEW,
				'options' => WPCPM_Sponsor_Roster::switcher_options(),
				'current' => $current,
				'label'   => __( 'Viewing as sponsor', 'wpcredits-program-manager' ),
				'find'    => __( 'Find a sponsor', 'wpcredits-program-manager' ),
				'none'    => __( 'No sponsors match that search.', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * The flash a card's handler left, in the reader's words, with the values it carries put where
	 * its sentence names them (`filled()`), and the detail after it.
	 *
	 * A status whose sentence is empty carries its whole message as the detail: a refusal for words
	 * the cleaner would take (the profile's and the Offers card's), whose sentence names the box and
	 * how to keep every word. It prints the detail alone.
	 *
	 * Nothing when the status is empty or unknown to `messages()`, when its sentence names more
	 * values than the flash carries, or when it has neither a sentence nor a detail: a stale or
	 * hand-edited flash must not print a blank notice, or a placeholder.
	 *
	 * @param array $flash `status`, `card`, `detail` and, when its sentence names values, `args`.
	 */
	private static function render_message( array $flash ) {
		$status = isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';

		if ( '' === $status ) {
			return;
		}

		$messages = self::messages();

		if ( ! isset( $messages[ $status ] ) || ! is_array( $messages[ $status ] ) ) {
			return;
		}

		$own      = (string) $messages[ $status ][1];
		$sentence = self::filled( $own, isset( $flash['args'] ) && is_array( $flash['args'] ) ? $flash['args'] : array() );
		$detail   = isset( $flash['detail'] ) ? trim( (string) $flash['detail'] ) : '';

		if ( '' === $sentence && ( '' !== $own || '' === $detail ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--%1$s">%2$s%3$s</p>',
			esc_attr( (string) $messages[ $status ][0] ),
			esc_html( $sentence ),
			'' !== $detail ? ( '' !== $sentence ? ' ' : '' ) . '<span class="wpcpm-dashboard__detail">' . esc_html( $detail ) . '</span>' : ''
		);
	}

	/**
	 * A card's sentence with a flash's values in its placeholders, `%1$s` and on, or '' when it names
	 * more values than the flash carries. A sentence that names none comes back as it is.
	 *
	 * @param string   $sentence The sentence, as the card's `messages()` holds it.
	 * @param string[] $args     The values the flash carries, in the sentence's order.
	 * @return string
	 */
	private static function filled( $sentence, array $args ) {
		$wanted = 0;

		if ( preg_match_all( '/%(?:(\d+)\$)?[sd]/', $sentence, $found ) ) {
			foreach ( $found[1] as $at => $position ) {
				$wanted = max( $wanted, '' === $position ? $at + 1 : (int) $position );
			}
		}

		if ( 0 === $wanted ) {
			return $sentence;
		}

		return count( $args ) < $wanted ? '' : vsprintf( $sentence, array_map( 'strval', array_values( $args ) ) );
	}

	/**
	 * The identity header: the sponsor's logo or initials, its name, website, contact and the
	 * facts a manager alone is shown, and, once the tier ladder is announced, its badge at the
	 * header's right - the owner asked (26 September 2026, with a screenshot) for the tier to
	 * read at a glance in the same place the company itself is named. The type of product left it
	 * in 1.123.22: the application no longer asks it and nothing public shows it, so it is no fact
	 * to print under the company's name; the record keeps it for the Sponsors screen.
	 *
	 * @param string $record     Sponsor key.
	 * @param array  $row        From `WPCPM_Sponsors_Index::row()`, or `empty_row()`.
	 * @param bool   $can_manage Whether the viewer holds CAP_MANAGE.
	 */
	private static function render_identity( $record, array $row, $can_manage ) {
		$name    = trim( (string) $row['name'] );
		$name    = '' === $name ? __( 'Unnamed sponsor', 'wpcredits-program-manager' ) : $name;
		$website = self::website_url( $row['website'] );
		$logo    = WPCPM_Sponsors_Index::display_logo( $record );

		echo '<header class="wpcpm-sponsor__identity">';
		echo '<div class="wpcpm-sponsor__logo">';

		if ( is_array( $logo ) ) {
			// The site's own attachment, copied in before 1.111.0 or uploaded by the sponsor: never
			// a link to a file held somewhere else, which can expire within hours.
			printf( '<img class="wpcpm-sponsor__logo-image" src="%1$s" alt="" loading="lazy" decoding="async" />', esc_url( $logo['url'] ) );
		} else {
			printf( '<span class="wpcpm-sponsor__initials" aria-hidden="true">%s</span>', esc_html( self::initials( $name ) ) );
		}

		echo '</div>';
		echo '<div class="wpcpm-sponsor__details">';
		printf( '<p class="wpcpm-sponsor__name">%s</p>', esc_html( $name ) );

		if ( '' !== $website ) {
			printf(
				'<p class="wpcpm-sponsor__website"><a href="%1$s" rel="external noopener">%2$s</a></p>',
				esc_url( $website ),
				esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) )
			);
		}

		// The contact is the sponsor's own data, shown to its members and to managers.
		$contact = array_values( array_filter( array( trim( (string) $row['contact_person'] ), trim( (string) $row['contact_email'] ) ) ) );

		if ( ! empty( $contact ) ) {
			printf(
				'<p class="wpcpm-sponsor__fact wpcpm-sponsor__contact">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the contact person and address. */
						__( 'Contact: %s', 'wpcredits-program-manager' ),
						implode( ', ', $contact )
					)
				)
			);
		}

		if ( $can_manage && '' !== trim( (string) $row['status'] ) ) {
			printf(
				'<p class="wpcpm-sponsor__fact wpcpm-sponsor__status">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the sponsor's status in the program. */
						__( 'Status: %s', 'wpcredits-program-manager' ),
						trim( (string) $row['status'] )
					)
				)
			);
		}

		echo '</div>';

		// The badge, right of the details, in the space the header otherwise leaves empty:
		// nothing while the launch switch is off, for a sponsor whose tier carries no badge, or
		// for a row with no slug to build its address from. At the versioned address, which names
		// the tier and the year the badge draws, so a change of level shows here at once rather
		// than once a browser's copy of the image has expired (1.123.22).
		if ( WPCPM_Sponsor_Tiers::live() && WPCPM_Sponsor_Badge::eligible( $row ) ) {
			$slug = (string) $row['slug'];

			if ( '' !== $slug ) {
				printf(
					'<div class="wpcpm-sponsor__badge"><img class="wpcpm-sponsor__badge-image" src="%1$s" alt="%2$s" width="%3$d" height="%4$d" loading="lazy" decoding="async" /></div>',
					esc_url( WPCPM_Sponsor_Badge::versioned_url( $row ) ),
					esc_attr( WPCPM_Sponsor_Badge::light_alt( $row ) ),
					(int) WPCPM_Sponsor_Badge::WIDTH,
					(int) WPCPM_Sponsor_Badge::HEIGHT
				);
			}
		}

		echo '</header>';
	}

	/**
	 * The first letter of each of the first two words of a name, upper-cased.
	 *
	 * @param string $name The sponsor's name.
	 * @return string
	 */
	private static function initials( $name ) {
		$name = trim( (string) $name );

		if ( '' === $name ) {
			return '?';
		}

		$letters = '';

		foreach ( array_slice( preg_split( '/\s+/', $name ), 0, 2 ) as $word ) {
			if ( '' !== $word ) {
				$letters .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
		}

		return '' === $letters ? '?' : $letters;
	}

	/**
	 * A sponsor's website as a link, or an empty string.
	 *
	 * The store holds websites as people typed them, many of the migrated ones without a scheme,
	 * so a bare `example.com` is common: given a scheme rather than dropped.
	 *
	 * @param string $raw The stored value.
	 * @return string
	 */
	private static function website_url( $raw ) {
		$website = trim( (string) $raw );

		if ( '' === $website ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $website ) ) {
			$website = 'https://' . $website;
		}

		return esc_url_raw( $website, array( 'http', 'https' ) );
	}

	/**
	 * The At a glance strip under the header (the owner, 27 September 2026): a tile for each figure a
	 * sponsor reads first, each a link to the card that holds it.
	 *
	 * The Administrator Dashboard's attention strip (`WPCPM_Administrators_Cards::render_strip()`),
	 * its tiles markup for markup, so one set of rules dresses both (`register_assets()`), inside a
	 * section that is a card of this page, named for a screen reader and with no heading of its own.
	 * Each tile is one link around its number line and its label, and a tile at zero is muted, not
	 * hidden (the Administrator Dashboard draws no zero). A tile links to its card's anchor, which
	 * `assets/js/dashboard-open.js` opens, and is drawn only when its card is (`glance_tiles()`).
	 *
	 * Nothing for a sponsor the store holds no row for, a member's stamp that outlived its sponsor:
	 * the page is drawn on the empty row, whose floor (no tier, no mentor) is no fact about the
	 * sponsor. And nothing when fewer than two tiles are drawn: one tile is not a strip.
	 *
	 * @param string     $record  Sponsor key.
	 * @param array|null $row     The row `render()` read from `WPCPM_Sponsors_Index::row()`, or null
	 *                            when the store holds none.
	 * @param array      $context `can_manage`, `open`, `viewer`.
	 */
	private static function render_glance( $record, $row, array $context ) {
		if ( ! is_array( $row ) ) {
			return;
		}

		$tiles = self::glance_tiles( $record, $row, $context );

		if ( count( $tiles ) < 2 ) {
			return;
		}

		printf( '<section class="wpcpm-sponsor__card wpcpm-attention wpcpm-sponsor__glance" id="wpcpm-sponsor-glance" aria-label="%s">', esc_attr__( 'At a glance', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-attention__tiles">';

		foreach ( $tiles as $tile ) {
			printf(
				'<li class="wpcpm-attention__tile%1$s"><a class="wpcpm-attention__link" href="#wpcpm-sponsor-%2$s"><span class="wpcpm-attention__n">%3$s</span><span class="wpcpm-attention__l">%4$s</span></a></li>',
				$tile['zero'] ? ' wpcpm-attention__tile--zero' : '',
				esc_attr( $tile['card'] ),
				esc_html( $tile['value'] ),
				esc_html( $tile['label'] )
			);
		}

		echo '</ul></section>';
	}

	/**
	 * The strip's tiles, in the order the owner approved: the tier, the next event, the offers, the
	 * mentors and the yearly reports. Each is drawn only when its card is, asked the way that card
	 * asks it, and reads its figure through that card's own readers, so a tile and its card never
	 * disagree:
	 *
	 * - the tier, while the launch switch is on: the effective tier, and the running term's last day
	 *   with the days left in it, or on that day that it ends today, as the card's row says it; the
	 *   day the term ended once it has, the one fact a sponsor whose term lapsed wants; or no term;
	 * - the next event, while the Events card is drawn (`glance_event_tile()`);
	 * - the offers live today, with the claims this month;
	 * - the mentors the company sponsors, the count the card gives beside its title;
	 * - the yearly reports, the final ones, while the Reports card is drawn, which is while there is
	 *   a final one.
	 *
	 * Every tile the tiers decide is drawn only while `WPCPM_Sponsor_Tiers::live()`, as its card is:
	 * until the launch nothing a sponsor sees names a tier. Every number is written the way the site
	 * writes numbers.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $row     The sponsor's row.
	 * @param array  $context `viewer`, whose clock the event's day is read on.
	 * @return array[] Each `card` (its anchor's key), `value` (the number line, as printed), `label`
	 *                 and `zero` (whether the tile is muted).
	 */
	private static function glance_tiles( $record, array $row, array $context ) {
		$tiles = array();
		$live  = WPCPM_Sponsor_Tiers::live();
		$today = WPCPM_Sponsor_Tiers::today();
		$tier  = WPCPM_Sponsor_Tiers::effective( $row, $today );

		if ( $live && self::installed( 'WPCPM_Sponsor_Tier_Card' ) ) {
			// The Sponsorship card's own reading of the term, the one its row reads: a term counts only
			// on a recorded tier above Friend, and runs through its last day.
			$tiles[] = array(
				'card'  => WPCPM_Sponsor_Tier_Card::CARD,
				'value' => WPCPM_Sponsor_Tiers::label( $tier ),
				'label' => self::glance_term( WPCPM_Sponsor_Tier_Card::term_days_left( $row, $today ), (string) $row['tier_until'] ),
				'zero'  => false,
			);

			$event = self::glance_event_tile( $record, $row, $tier, $context );

			if ( null !== $event ) {
				$tiles[] = $event;
			}
		}

		if ( self::installed( 'WPCPM_Sponsor_Offers' ) && method_exists( 'WPCPM_Sponsor_Offers', 'live_count' ) && class_exists( 'WPCPM_Sponsor_Claims' ) && method_exists( 'WPCPM_Sponsor_Claims', 'stats' ) ) {
			$offers = (int) WPCPM_Sponsor_Offers::live_count( $record );
			$stats  = WPCPM_Sponsor_Claims::stats( $record );
			$month  = isset( $stats['totals']['month'] ) ? (int) $stats['totals']['month'] : 0;

			$tiles[] = array(
				'card'  => WPCPM_Sponsor_Offers::CARD,
				'value' => number_format_i18n( $offers ),
				'label' => sprintf(
					/* translators: %s: how many times the sponsor's offers were claimed this month. */
					_n( 'offers live, %s claim this month', 'offers live, %s claims this month', $month, 'wpcredits-program-manager' ),
					number_format_i18n( $month )
				),
				'zero'  => 0 === $offers,
			);
		}

		if ( self::installed( 'WPCPM_Sponsor_Mentors' ) && method_exists( 'WPCPM_Sponsor_Mentors', 'linked' ) ) {
			$linked  = WPCPM_Sponsor_Mentors::linked( $record );
			$mentors = count( (array) $linked['mentors'] ) + (int) $linked['others'];

			$tiles[] = array(
				'card'  => WPCPM_Sponsor_Mentors::CARD,
				'value' => number_format_i18n( $mentors ),
				'label' => __( 'mentors sponsored', 'wpcredits-program-manager' ),
				'zero'  => 0 === $mentors,
			);
		}

		if ( $live && self::installed( 'WPCPM_Sponsor_Report_Card' ) && class_exists( 'WPCPM_Sponsor_Report' ) ) {
			// The Reports card's own list, the one its row counts: the final reports, a draft being the
			// program's to write.
			$finals = count( WPCPM_Sponsor_Report_Card::finals( $record ) );

			if ( $finals > 0 ) {
				$tiles[] = array(
					'card'  => WPCPM_Sponsor_Report_Card::CARD,
					'value' => number_format_i18n( $finals ),
					'label' => _n( 'yearly report', 'yearly reports', $finals, 'wpcredits-program-manager' ),
					'zero'  => false,
				);
			}
		}

		return $tiles;
	}

	/**
	 * The tier tile's line, from the term the Sponsorship card reads: the last day with the days left
	 * in it, or, on that day, that the term ends today, the card's own word for it; the day the term
	 * ended once it has; or no term.
	 *
	 * @param int|null $left  `WPCPM_Sponsor_Tier_Card::term_days_left()`.
	 * @param string   $until The row's `tier_until`, the term's last day.
	 * @return string
	 */
	private static function glance_term( $left, $until ) {
		if ( null === $left ) {
			return __( 'no term', 'wpcredits-program-manager' );
		}

		$day = WPCPM_Sponsor_Tiers::format_day( $until );

		if ( $left < 0 ) {
			/* translators: %s: the last day of the term that ended. */
			return sprintf( __( 'term ended %s', 'wpcredits-program-manager' ), $day );
		}

		if ( 0 === $left ) {
			/* translators: %s: the last day of the term, which is today. */
			return sprintf( __( 'to %s, ending today', 'wpcredits-program-manager' ), $day );
		}

		return sprintf(
			/* translators: 1: the last day of the term, 2: how many days are left in it. */
			_n( 'to %1$s, %2$s day', 'to %1$s, %2$s days', $left, 'wpcredits-program-manager' ),
			$day,
			number_format_i18n( $left )
		);
	}

	/**
	 * The next event's tile, while the Events card is drawn; null otherwise.
	 *
	 * Asked of the card's own test, `WPCPM_Sponsor_Tier_Card::draws_events()`, the one the card's
	 * `render()` keeps: it is drawn while the sponsor holds the planner or has an event of its own
	 * still to take place. The sponsor's events still to take place are read once, handed to the test
	 * and read for the figure, the soonest of them, the one the planner lists first: its day, on the
	 * viewer's clock as the card's row reads it (`WPCPM_Sponsor_Tier_Card::event_day()`) but short, a
	 * month and a day, so a long date takes no second line and every tile keeps one height, the row
	 * keeping the day whole; its title; and how many joined, or, while the program has not approved
	 * it, that it waits for the program, since nobody can join it yet. A sponsor holding the planner with nothing to take place reads None, the
	 * tile's zero, muted as the others' zeros are. Behind class_exists(), as the card's planner is:
	 * without the event store there is no event to read.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $row     The sponsor's row.
	 * @param string $tier    The effective tier.
	 * @param array  $context `viewer`.
	 * @return array|null As `glance_tiles()` lists a tile.
	 */
	private static function glance_event_tile( $record, array $row, $tier, array $context ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Event' ) ) {
			return null;
		}

		// Read once, for the card's own test and for the tile's figure alike.
		$upcoming = WPCPM_Sponsor_Event::upcoming_of( $record );

		if ( ! WPCPM_Sponsor_Tier_Card::draws_events( $record, $row, $tier, $upcoming ) ) {
			return null;
		}

		if ( array() === $upcoming ) {
			return array(
				'card'  => WPCPM_Sponsor_Tier_Card::CARD_EVENTS,
				'value' => __( 'None', 'wpcredits-program-manager' ),
				'label' => __( 'no event planned', 'wpcredits-program-manager' ),
				'zero'  => true,
			);
		}

		$facts  = WPCPM_Sponsor_Event::facts( $upcoming[0] );
		$viewer = isset( $context['viewer'] ) && $context['viewer'] instanceof WP_User ? $context['viewer'] : wp_get_current_user();

		return array(
			'card'  => WPCPM_Sponsor_Tier_Card::CARD_EVENTS,
			'value' => WPCPM_Sponsor_Tier_Card::event_day(
				$facts,
				$viewer,
				/* translators: the short day of the At a glance event tile, a month and a day; see https://www.php.net/manual/datetime.format.php */
				__( 'M j', 'wpcredits-program-manager' )
			),
			'label' => WPCPM_Sponsor_Event::STATE_PLANNED === $facts['state']
				? sprintf(
					/* translators: %s: the event's title, cut to fit the tile. */
					__( '%s, waiting for the program', 'wpcredits-program-manager' ),
					self::glance_title( $facts['title'] )
				)
				: sprintf(
					/* translators: 1: the event's title, cut to fit the tile, 2: how many people have joined it. */
					_n( '%1$s, %2$s joined', '%1$s, %2$s joined', (int) $facts['joined'], 'wpcredits-program-manager' ),
					self::glance_title( $facts['title'] ),
					number_format_i18n( (int) $facts['joined'] )
				),
			'zero'  => false,
		);
	}

	/**
	 * An event's title cut to the tile: whole when it fits in GLANCE_TITLE characters, else to the
	 * last word that ends within them past their middle, a word that ends on the last of them
	 * included, or at the limit itself when none does, with an ellipsis. Measured and cut as the text
	 * it is: a title a sponsor member saves is stored with its ampersand as an entity, so entities
	 * are decoded first, and none is counted as several characters or cut in two; the tile escapes
	 * the text where it prints it. Cut by characters, not bytes, so no character is halved; what
	 * follows the title is never what is cut.
	 *
	 * @param string $title The event's title, as stored.
	 * @return string The title as text, to be escaped where it is printed.
	 */
	private static function glance_title( $title ) {
		$title = trim( html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( mb_strlen( $title ) <= self::GLANCE_TITLE ) {
			return $title;
		}

		// One character past the limit, so a space right after it, which ends a word that fits
		// exactly, is found.
		$space = mb_strrpos( mb_substr( $title, 0, self::GLANCE_TITLE + 1 ), ' ' );
		$cut   = mb_substr( $title, 0, self::GLANCE_TITLE );

		if ( false !== $space && $space > self::GLANCE_TITLE / 2 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, ' ,.;:' ) . '…';
	}

	/**
	 * The Resources section, with this sponsor's own program contact in it, below the cards.
	 *
	 * The section is `WPCPM_Handbook_Assistant::render_resources()`, asked for the sponsor
	 * audience: its own handbook page, the program's Slack channel, the announcements at that
	 * access level, and the assistant. The one thing added is the person, in a third column of its
	 * own: this page alone asks for three, so the updates, the resources and the program contact
	 * read as one strip at the foot of the page. A sponsor with nobody assigned gets the two.
	 *
	 * @param string $record Sponsor key.
	 */
	private static function render_help( $record ) {
		if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) || ! method_exists( 'WPCPM_Handbook_Assistant', 'render_resources' ) ) {
			return;
		}

		echo WPCPM_Handbook_Assistant::render_resources( 'sponsor', self::contact_block( $record ), 3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the assistant and by contact_block(), both of which escape what they interpolate.
	}

	/**
	 * Who this sponsor writes to, as the Resources section's third column.
	 *
	 * Resolved through `WPCPM_Sponsors_Index::manager_of()`, which reads the assigned manager's
	 * account at read time, so a manager who renames themselves shows the new name at once.
	 * A sponsor with nobody assigned prints nothing at all rather than a heading with a blank
	 * under it.
	 *
	 * @param string $record Sponsor key.
	 * @return string Escaped markup, or ''.
	 */
	private static function contact_block( $record ) {
		$manager = WPCPM_Sponsors_Index::manager_of( $record );

		if ( ! is_array( $manager ) ) {
			return '';
		}

		$name     = isset( $manager['name'] ) ? trim( (string) $manager['name'] ) : '';
		$email    = isset( $manager['email'] ) ? trim( (string) $manager['email'] ) : '';
		$calendly = isset( $manager['calendly'] ) ? trim( (string) $manager['calendly'] ) : '';

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
			esc_html__( 'They look after your sponsorship. Write to them about anything the guide does not answer.', 'wpcredits-program-manager' )
		);

		return $out . '</div>';
	}

	/**
	 * The people card: who has an account for this sponsor, read-only for everybody.
	 *
	 * Attaching and removing are manager actions on the wp-admin Sponsors screen, and sponsors
	 * do not invite each other in this release (design spec of 4 September 2026, section 14). A
	 * manager's link opens this sponsor's accounts there (`WPCPM_Sponsors::accounts_view_url()`).
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	private static function render_people( $record, array $context ) {
		$members = WPCPM_Sponsor_Members::members_of( $record );

		// The shell every card on this page is drawn in (WPCPM_Dashboard_Card), the same shape as the
		// Institution Dashboard's semester report, which is what lets the two pages share one skin.
		// Never opened by a flash: no handler lands on this card.
		WPCPM_Dashboard_Card::open(
			array(
				'key'   => 'people',
				'title' => __( 'People', 'wpcredits-program-manager' ),
				'count' => count( $members ),
				'state' => sprintf(
					/* translators: %s: how many accounts act for the sponsor. */
					_n( '%s account', '%s accounts', count( $members ), 'wpcredits-program-manager' ),
					number_format_i18n( count( $members ) )
				),
			)
		);

		if ( empty( $members ) ) {
			echo '<p>' . esc_html__( 'No account acts for this sponsor yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<ul class="wpcpm-sponsor__people">';

			foreach ( $members as $member ) {
				printf( '<li>%1$s <span class="wpcpm-sponsor__muted">%2$s</span></li>', esc_html( $member->display_name ), esc_html( $member->user_email ) );
			}

			echo '</ul>';
		}

		if ( ! empty( $context['can_manage'] ) ) {
			printf(
				'<p class="wpcpm-student__note"><a href="%1$s">%2$s</a></p>',
				esc_url( WPCPM_Sponsors::accounts_view_url( $record ) ),
				esc_html__( 'Attach or remove accounts on the Sponsors screen.', 'wpcredits-program-manager' )
			);
		} else {
			echo '<p class="wpcpm-student__note">' . esc_html__( 'To add a colleague, write to your contact at the program.', 'wpcredits-program-manager' ) . '</p>';
		}

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * One sentence in the dashboard's shell, for the viewer who gets no page, with an optional
	 * button - the shape `WPCPM_Administrators_Dashboard::notice()` also draws.
	 *
	 * @param string $message     Text, possibly carrying `nothing_to_show()`'s one link.
	 * @param string $action_url  Optional action URL.
	 * @param string $action_text Optional action label.
	 * @return string
	 */
	private static function notice( $message, $action_url = '', $action_text = '' ) {
		$html = '<div class="wpcpm-dashboard wpcpm-dashboard--notice"><p>' . wp_kses( (string) $message, array( 'a' => array( 'href' => array() ) ) ) . '</p>';

		if ( $action_url && $action_text ) {
			$html .= '<p><a class="wpcpm-button" href="' . esc_url( $action_url ) . '">' . esc_html( $action_text ) . '</a></p>';
		}

		return $html . '</div>';
	}
}
