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
 * Since 1.123.78 the page is drawn in the dashboards' frame (`WPCPM_Dashboard_Frame`, the version 3 concepts the
 * owner approved on 10 November 2026), as the Administrator Dashboard is: a menu down the side and a view for each
 * of its items, each at its own address (`?wpcpm_view=`). The Overview leads with what the company's support did,
 * the figures, what waits on the company's people as rows with a status tag in words, the offers in play as the
 * homepage's program cards and the mentors looking for a sponsor as a door; Your sponsorship (while the tiers are
 * launched), Offers, Students reached, Mentors to sponsor, Your company and Agreement each draw their cards. The
 * At a glance strip, the Resources block and the four movable sections of earlier releases are gone: what the
 * strip counted is the Overview's rows and the menu's counts, the Resources' links are the menu's, and a view per
 * section leaves nothing to arrange.
 *
 * Every card is drawn in the shell the Administrator Dashboard's cards share, `WPCPM_Dashboard_Card`: a section
 * around a disclosure keyed `wpcpm-sponsor-<card>`, so `leave()` can open the one a flash names, and a link to
 * that anchor opens it through the dashboards' opener, `assets/js/dashboard-open.js`. Every link to a card goes
 * through `card_url()`, which knows the view that holds it. The cards live in their own classes; this class owns
 * the page, the gate, the frame, the Overview and the read-only People card.
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
	 * The prefix of the option a company's order of the page's four sections was kept in, before the sponsor
	 * key. Unused since 1.123.78, when each section became a view of its own in the frame; kept so uninstall
	 * still finds the options a company saved.
	 */
	const OPT_MODULES_PREFIX = 'wpcpm_sponsor_modules_';

	/** The query argument that names a view (1.123.78), the Administrator Dashboard's own. */
	const VIEW_ARG = 'wpcpm_view';
	/** The views, each a menu item with its own address (1.123.78). The Overview is the page with no argument. */
	const VIEW_OVERVIEW    = 'overview';
	const VIEW_SPONSORSHIP = 'sponsorship';
	const VIEW_OFFERS      = 'offers';
	const VIEW_REACHED     = 'reached';
	const VIEW_MENTORS     = 'mentors';
	const VIEW_COMPANY     = 'company';
	const VIEW_AGREEMENT   = 'agreement';
	/** The Sponsor guide and the program's updates, pages of their own drawn in this page's frame. */
	const VIEW_GUIDE   = 'guide';
	const VIEW_UPDATES = 'updates';
	/** The frame's key for this page, as the theme names it in `add_theme_support( 'wpcpm-dashboard-frame' )`. */
	const FRAME_PAGE = 'sponsor';
	/** The Offers card's New offer disclosure, which New offer opens: `WPCPM_Sponsor_Offers::NEW_ID`. */
	const OFFER_NEW = 'wpcpm-offer-new';

	/**
	 * The cards each view draws, by their anchor keys, `wpcpm-sponsor-<key>`: what `card_url()` routes by. A key
	 * not listed is on the Overview.
	 */
	const VIEW_CARDS = array(
		'sponsorship' => array( 'sponsorship', 'events', 'reports', 'badge' ),
		'offers'      => array( 'offers', 'posts' ),
		'reached'     => array( 'usage' ),
		'mentors'     => array( 'mentors', 'looking' ),
		'company'     => array( 'profile', 'people' ),
		'agreement'   => array( 'agreement' ),
	);

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
	 * share a shell - the color tokens, the "view as" switcher, the disclosure cards, the
	 * muted-text treatment - and defining that once means a theme styling one page has already
	 * styled this one. It depends on the Administrator Dashboard's too, for the same reason: the
	 * Overview's quiet lines and the cards' rows in the frame are that page's rules. Each sheet is
	 * registered here first by the class that owns it, since WordPress prints nothing for a sheet
	 * whose dependency nobody registered.
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
	 * The view being asked for (1.123.78): one of the page's views, or the Overview. Your sponsorship only
	 * while the tiers are launched, as its cards are drawn only then.
	 *
	 * @return string A `VIEW_*` key.
	 */
	public static function view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read, which changes nothing.
		$asked = isset( $_GET[ self::VIEW_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::VIEW_ARG ] ) ) : '';

		if ( ! array_key_exists( $asked, self::VIEW_CARDS ) ) {
			return self::VIEW_OVERVIEW;
		}

		if ( self::VIEW_SPONSORSHIP === $asked && ! WPCPM_Sponsor_Tiers::live() ) {
			return self::VIEW_OVERVIEW;
		}

		return $asked;
	}

	/**
	 * A view's address: the page's own, with `?wpcpm_view=` for any view but the Overview, and, when given, the
	 * sponsor an Administrator is looking at, so moving between views keeps it. A member's page never reads that
	 * argument (`WPCPM_Sponsor_Roster::resolve_sponsor()`), so a mail sent to a sponsor's people and its
	 * Administrator alike may carry it.
	 *
	 * @param string $view   A `VIEW_*` key.
	 * @param string $record The sponsor's key, for an Administrator's link, or '' for a member's own page.
	 * @return string '' while the page has no address.
	 */
	public static function view_url( $view = self::VIEW_OVERVIEW, $record = '' ) {
		$url = self::page_url();

		if ( '' === $url ) {
			return '';
		}

		$args = array();

		if ( self::VIEW_OVERVIEW !== $view && array_key_exists( (string) $view, self::VIEW_CARDS ) ) {
			$args[ self::VIEW_ARG ] = sanitize_key( (string) $view );
		}

		$record = is_scalar( $record ) ? trim( (string) $record ) : '';

		if ( '' !== $record && WPCPM_Sponsors_Index::is_key( $record ) ) {
			$args[ WPCPM_Sponsor_Roster::ARG_VIEW ] = $record;
		}

		return array() === $args ? $url : add_query_arg( $args, $url );
	}

	/**
	 * The view that draws a card.
	 *
	 * @param string $card The card's anchor key, as in `wpcpm-sponsor-<key>`.
	 * @return string A `VIEW_*` key: the Overview for a card no view lists.
	 */
	public static function view_of( $card ) {
		foreach ( self::VIEW_CARDS as $view => $cards ) {
			if ( in_array( (string) $card, $cards, true ) ) {
				return $view;
			}
		}

		return self::VIEW_OVERVIEW;
	}

	/**
	 * A link to a card at the view that draws it (1.123.78): the mails, the wp-admin screens, the assistant's
	 * answers and every card's way back after a press land there, and the dashboards' opener opens the card.
	 *
	 * @param string $card   The card's anchor key.
	 * @param string $record The sponsor's key, for an Administrator's link, or ''.
	 * @param string $item   An anchor inside the card in place of the card's own, or ''.
	 * @return string '' while the page has no address.
	 */
	public static function card_url( $card, $record = '', $item = '' ) {
		$url = self::view_url( self::view_of( $card ), $record );

		if ( '' === $url ) {
			return '';
		}

		return $url . '#' . ( '' !== (string) $item ? (string) $item : 'wpcpm-sponsor-' . sanitize_key( (string) $card ) );
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

		// A specific destination was asked for - honor it, the way the institution page does:
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

		// The page prints the outcome inside the card this goes back to (SDB-01, SDB-02, 1.123.58).
		if ( '' !== $flash['card'] ) {
			WPCPM_Dashboard_Card::target( 'wpcpm-sponsor-' . $flash['card'] );
		}

		// Back to the view that draws the card (1.123.78), with the sponsor an Administrator was looking at; a
		// member's stamp is the sponsor, so the argument is not needed and not added.
		$record = is_scalar( $record ) && current_user_can( WPCPM_Roles::CAP_MANAGE ) ? trim( (string) $record ) : '';
		$url    = '' !== (string) $card ? self::card_url( (string) $card, $record ) : self::view_url( self::VIEW_OVERVIEW, $record );

		// No page: home, with the card's anchor as before.
		if ( '' === $url ) {
			$url = home_url( '/' ) . ( '' !== (string) $card ? '#wpcpm-sponsor-' . sanitize_key( (string) $card ) : '' );
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

		$view = self::view();

		WPCPM_Dashboard_Card::forget();

		ob_start();

		// The frame (1.123.78): the menu down the side, a view per item, and the main area as wide as the window,
		// as the Administrator Dashboard's.
		WPCPM_Dashboard_Frame::open( self::frame_args( $view, $record, $row, $context ) );

		printf( '<div class="wpcpm-dashboard wpcpm-sponsor wpcpm-sponsor--%s">', esc_attr( $view ) );

		WPCPM_Two_Factor::prompt( $viewer );

		// The heading is the block's or the shortcode's own attribute, empty by default so the
		// page's title is not printed twice; the page title itself is title()'s business.
		$atts = shortcode_atts( array( 'title' => '' ), is_array( $attributes ) ? $attributes : array(), self::SHORTCODE );

		if ( '' !== trim( (string) $atts['title'] ) ) {
			printf( '<h2 class="wpcpm-dashboard__title">%s</h2>', esc_html( trim( (string) $atts['title'] ) ) );
		}

		if ( $can_manage ) {
			self::render_switcher( $record, $view );
		}

		// The outcome is held for the card the handler named, which prints it at the top of its body; the
		// slot says it here only when no card did (1.123.58), such as a card on another view.
		list( $tone, $text ) = self::message_of( $flash );

		WPCPM_Dashboard_Card::hold( WPCPM_Dashboard_Card::targeted(), $tone, $text );
		echo WPCPM_Dashboard_Card::SLOT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed comment.

		if ( self::VIEW_OVERVIEW === $view ) {
			self::render_overview( $record, $row, is_array( $stored ) ? $stored : null, $context );
		} else {
			self::render_view( $view, $record, $row, is_array( $stored ) ? $stored : null, $context );
		}

		echo '</div>';

		WPCPM_Dashboard_Frame::close();

		return WPCPM_Dashboard_Card::settle( (string) ob_get_clean() );
	}

	/**
	 * The menu (1.123.78): Overview, Your sponsorship while the tiers are launched, Offers, Students reached,
	 * Mentors to sponsor, Your company and Agreement, each its own view, and Program updates while a sponsor
	 * may read one; under Help the Sponsor guide and the company's contact at the program. A view's count is
	 * the status tag of what waits in it, as the Administrator Dashboard's.
	 *
	 * @param string $view    The view being read, a `VIEW_*` key.
	 * @param string $record  Sponsor key.
	 * @param array  $row     The sponsor's row, or the empty row.
	 * @param array  $context What every card is handed.
	 * @return array What `WPCPM_Dashboard_Frame::open()` takes.
	 */
	private static function frame_args( $view, $record, array $row, array $context ) {
		// The sponsor an Administrator is looking at travels with every link; a member's own page needs none.
		$managed = ! empty( $context['can_manage'] ) ? $record : '';
		$counts  = self::waiting_by_view( self::waiting_items( $record, $row ) );
		$badge   = static function ( $n ) {
			/* translators: %s: how many things wait for the sponsor's people. */
			return $n > 0 ? sprintf( _n( '%s waits', '%s wait', $n, 'wpcredits-program-manager' ), number_format_i18n( $n ) ) : '';
		};

		$views = array(
			self::VIEW_OVERVIEW    => array( __( 'Overview', 'wpcredits-program-manager' ), 'home' ),
			self::VIEW_SPONSORSHIP => array( __( 'Your sponsorship', 'wpcredits-program-manager' ), 'award' ),
			self::VIEW_OFFERS      => array( __( 'Offers', 'wpcredits-program-manager' ), 'tag' ),
			self::VIEW_REACHED     => array( __( 'Students reached', 'wpcredits-program-manager' ), 'users' ),
			self::VIEW_MENTORS     => array( __( 'Mentors to sponsor', 'wpcredits-program-manager' ), 'profile' ),
			self::VIEW_COMPANY     => array( __( 'Your company', 'wpcredits-program-manager' ), 'building' ),
			self::VIEW_AGREEMENT   => array( __( 'Agreement', 'wpcredits-program-manager' ), 'file' ),
		);

		$items = array();

		foreach ( $views as $key => $item ) {
			if ( self::VIEW_SPONSORSHIP === $key && ! WPCPM_Sponsor_Tiers::live() ) {
				continue;
			}

			// The Overview's count is everything that waits; a view's is what waits in it.
			$n = self::VIEW_OVERVIEW === $key ? array_sum( $counts ) : ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 );

			$items[] = WPCPM_Dashboard_Frame::item(
				$item[0],
				self::view_url( $key, $managed ),
				$item[1],
				array(
					'current' => $key === $view,
					'badge'   => $badge( $n ),
					'tone'    => 'red',
				)
			);
		}

		// The program's updates for sponsors, after the views: the newest a sponsor may read, which opens in the
		// frame with the others listed beside it, as on the Administrator Dashboard. Only while there is one.
		$newest = class_exists( 'WPCPM_Updates' ) ? WPCPM_Updates::posts( 1, 'sponsor' ) : array();

		if ( ! empty( $newest ) ) {
			// With the sponsor an Administrator is looking at, so the update draws in this sponsor's frame (1.123.79).
			$items[] = WPCPM_Dashboard_Frame::item( __( 'Program updates', 'wpcredits-program-manager' ), self::keep_sponsor( (string) get_permalink( $newest[0] ), $managed ), 'inbox', array( 'current' => self::VIEW_UPDATES === $view ) );
		}

		$help  = array();
		$guide = self::guide_page();

		if ( $guide instanceof WP_Post ) {
			// The sponsor an Administrator is looking at travels to the guide too, so its menu leads back to it.
			$help[] = WPCPM_Dashboard_Frame::item( __( 'Sponsor guide', 'wpcredits-program-manager' ), self::keep_sponsor( (string) get_permalink( $guide ), $managed ), 'book', array( 'current' => self::VIEW_GUIDE === $view ) );
		}

		// Write to the program: the company's own contact there, when one is assigned and has an address.
		$manager = '' !== $record ? WPCPM_Sponsors_Index::manager_of( $record ) : null;
		$email   = is_array( $manager ) && isset( $manager['email'] ) ? sanitize_email( (string) $manager['email'] ) : '';

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
	 * The menu for a page of its own drawn in this page's frame: the Sponsor guide and each program update a
	 * sponsor reads (1.123.78). The same items and counts as the page's own, with that page marked as where
	 * the reader is: for the sponsor the reader acts for, or, for an Administrator, the sponsor the address
	 * names, the one they were looking at, carried by the menu's links (the release's review). An Administrator
	 * whose address names none gets the items without counts, never another company's.
	 *
	 * @param string $view `VIEW_GUIDE` or `VIEW_UPDATES`.
	 * @return array What `WPCPM_Dashboard_Frame::open()` takes.
	 */
	public static function menu_args( $view ) {
		$view   = in_array( $view, array( self::VIEW_GUIDE, self::VIEW_UPDATES ), true ) ? $view : self::VIEW_GUIDE;
		$manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$record = $manage ? self::requested_record() : (string) WPCPM_Sponsor_Members::sponsor_of( wp_get_current_user() );

		$stored = '' !== $record ? WPCPM_Sponsors_Index::row( $record ) : null;
		$row    = is_array( $stored ) ? $stored : WPCPM_Sponsors_Index::empty_row();

		return self::frame_args( $view, $record, $row, array( 'can_manage' => $manage ) );
	}

	/**
	 * The sponsor an Administrator's address names (1.123.79): the request's key, shaped like one and still in
	 * the store, the roster's own test. '' for a reader who is no Administrator, whose page is their own
	 * company's, and for an address that names none.
	 *
	 * @return string Sponsor key, or ''.
	 */
	public static function requested_record() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return '';
		}

		$asked = (string) WPCPM_Sponsor_Roster::requested_view();

		return '' !== $asked && WPCPM_Sponsors_Index::is_key( $asked ) && WPCPM_Sponsors_Index::has( $asked ) ? $asked : '';
	}

	/**
	 * A link that keeps the sponsor an Administrator is looking at (1.123.79): the address with the sponsor's
	 * key, so the page it opens draws that sponsor's frame. As it is when there is no sponsor to keep.
	 *
	 * @param string $url    The address.
	 * @param string $record Sponsor key, or ''.
	 * @return string
	 */
	public static function keep_sponsor( $url, $record ) {
		$url = (string) $url;

		return '' !== (string) $record && '' !== $url ? add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, (string) $record, $url ) : $url;
	}

	/**
	 * The Sponsor guide's page, while it is published: the page `bin/build-docs.php` builds and the release
	 * publishes at /sponsor-guide/.
	 *
	 * @return WP_Post|null
	 */
	public static function guide_page() {
		$guide = get_page_by_path( 'sponsor-guide' );

		return $guide instanceof WP_Post && 'publish' === $guide->post_status ? $guide : null;
	}

	/**
	 * The page's name, the page's one H1, when the theme leaves the page to the frame; the theme's own
	 * template prints it otherwise. With the day the company became a sponsor after it, when the record
	 * holds one.
	 *
	 * @param array $row The sponsor's row.
	 * @return string Markup, escaped, or ''.
	 */
	private static function page_name( array $row ) {
		if ( ! WPCPM_Dashboard_Frame::owns_page( self::FRAME_PAGE ) ) {
			return '';
		}

		$since = self::sponsor_since( $row );

		if ( '' === $since ) {
			return sprintf( '<h1 class="wpcpm-head__eyebrow">%s</h1>', esc_html( self::title() ) );
		}

		return sprintf(
			'<h1 class="wpcpm-head__eyebrow">%1$s <span class="wpcpm-sponsor__since">&middot; %2$s</span></h1>',
			esc_html( self::title() ),
			esc_html(
				sprintf(
					/* translators: %s: a month and year, such as "March 2026". */
					__( 'sponsor since %s', 'wpcredits-program-manager' ),
					$since
				)
			)
		);
	}

	/**
	 * A view other than the Overview: its head, then its cards. A view whose cards print nothing says so in
	 * one line rather than standing empty.
	 *
	 * @param string     $view    A `VIEW_*` key.
	 * @param string     $record  Sponsor key.
	 * @param array      $row     The sponsor's row, or the empty row.
	 * @param array|null $stored  The row the store holds, or null.
	 * @param array      $context What every card is handed.
	 */
	private static function render_view( $view, $record, array $row, $stored, array $context ) {
		$heads = array(
			self::VIEW_SPONSORSHIP => array( __( 'Your sponsorship', 'wpcredits-program-manager' ), self::sponsorship_lead( $record, $row, $stored ) ),
			self::VIEW_OFFERS      => array( __( 'Offers', 'wpcredits-program-manager' ), self::offers_lead( $record ) ),
			self::VIEW_REACHED     => array( __( 'Students reached', 'wpcredits-program-manager' ), __( 'How many people claimed each of your offers, this month and in all. The program shows you numbers, never names.', 'wpcredits-program-manager' ) ),
			self::VIEW_MENTORS     => array( __( 'Mentors to sponsor', 'wpcredits-program-manager' ), __( 'The mentors your company sponsors, and the ones looking for a sponsor.', 'wpcredits-program-manager' ) ),
			self::VIEW_COMPANY     => array( __( 'Your company', 'wpcredits-program-manager' ), __( 'Your company\'s details and logo, and the people who can sign in for it.', 'wpcredits-program-manager' ) ),
			self::VIEW_AGREEMENT   => array( __( 'Collaboration Agreement', 'wpcredits-program-manager' ), __( 'The agreement between your company and the program, and the signed copy on file.', 'wpcredits-program-manager' ) ),
		);

		$head = isset( $heads[ $view ] ) ? $heads[ $view ] : array( self::title(), '' );

		self::render_head( $row, $head[0], $head[1] );

		ob_start();

		// Every card of a view is drawn open (1.123.80, the owner's ask): the view is the card's own page.
		WPCPM_Dashboard_Card::unfold( true );

		foreach ( self::view_owners( $view ) as $owner ) {
			if ( 'people' === $owner ) {
				self::render_people( $record, $context );
				continue;
			}

			self::card( $owner, $record, $context );
		}

		WPCPM_Dashboard_Card::unfold( false );

		$cards = trim( (string) ob_get_clean() );

		if ( '' === $cards ) {
			printf( '<p class="wpcpm-overview__quiet">%s</p>', esc_html__( 'Nothing to show here yet.', 'wpcredits-program-manager' ) );

			return;
		}

		printf( '<div class="wpcpm-sponsor__cards">%s</div>', $cards ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each card escapes what it prints.
	}

	/**
	 * The classes that draw each view's cards, in the order they are drawn; `people` is this class's own.
	 *
	 * @param string $view A `VIEW_*` key.
	 * @return string[]
	 */
	private static function view_owners( $view ) {
		$owners = array(
			self::VIEW_SPONSORSHIP => array( 'WPCPM_Sponsor_Tier_Card', 'WPCPM_Sponsor_Report_Card', 'WPCPM_Sponsor_Badge' ),
			self::VIEW_OFFERS      => array( 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Posts' ),
			self::VIEW_REACHED     => array( 'WPCPM_Sponsor_Usage' ),
			self::VIEW_MENTORS     => array( 'WPCPM_Sponsor_Mentors' ),
			self::VIEW_COMPANY     => array( 'WPCPM_Sponsor_Profile', 'people' ),
			self::VIEW_AGREEMENT   => array( 'WPCPM_Sponsor_Agreement_Card' ),
		);

		return isset( $owners[ $view ] ) ? $owners[ $view ] : array();
	}

	/**
	 * The head of a view: the page's H1, the view's title and one sentence under it.
	 *
	 * @param array  $row   The sponsor's row.
	 * @param string $title The view's title.
	 * @param string $lede  The sentence under it, or ''.
	 */
	private static function render_head( array $row, $title, $lede ) {
		echo '<header class="wpcpm-head"><div class="wpcpm-head__copy">';
		echo self::page_name( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page_name().
		printf( '<h2 class="wpcpm-head__title">%s</h2>', esc_html( $title ) );

		if ( '' !== (string) $lede ) {
			printf( '<p class="wpcpm-head__lede">%s</p>', esc_html( $lede ) );
		}

		echo '</div></header>';
	}

	/**
	 * The Offers view's sentence: what the company gives, and what it wrote while the Posts card is drawn.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	private static function offers_lead( $record ) {
		return self::installed( 'WPCPM_Sponsor_Posts' ) && method_exists( 'WPCPM_Sponsor_Posts', 'draws' ) && WPCPM_Sponsor_Posts::draws( $record )
			? __( 'What your company gives the program\'s students and mentors, and what it wrote for them.', 'wpcredits-program-manager' )
			: __( 'What your company gives the program\'s students and mentors.', 'wpcredits-program-manager' );
	}

	/**
	 * What waits on the sponsor's people (1.123.78): what the At a glance strip counted, each kind once, read
	 * the way its card reads it so a row and its card never disagree, in the strip's order. Each is a row on
	 * the Overview and a count beside its view in the menu.
	 *
	 * - the tier's term in its last `WPCPM_Sponsor_Tiers::NOTICE_DAYS` days, or ended, while the tiers are
	 *   launched, where the Sponsorship card says how to renew it;
	 * - the offers an Administrator returned with a note, the offers running low on codes and the offers not
	 *   switched on yet, each offer counted once (`WPCPM_Sponsor_Offers::waiting_counts()`);
	 * - the posts returned with a note, while the Posts card is drawn;
	 * - the event returned, only where the Events card can show its note (the planner's allowance is '');
	 * - the Collaboration Agreement returned or revoked, while the card asks the company for a file;
	 * - the company profile's missing details.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row, or the empty row.
	 * @return array[] Each `card` (the anchor key of the card that holds it), `count`, `title` (the row's
	 *                 words, the count in them), `why` (one sentence) and `action` (the link's words).
	 */
	public static function waiting_items( $record, array $row ) {
		$items = array();

		// Nothing for a sponsor the store holds no row for, a member's stamp that outlived its sponsor: the
		// page is drawn on the empty row, whose floor (no tier, a profile with every detail missing) is no
		// fact about the sponsor, as the At a glance strip drew nothing for it.
		if ( '' === (string) $record || ! WPCPM_Sponsors_Index::is_key( (string) $record ) || ! is_array( WPCPM_Sponsors_Index::row( (string) $record ) ) ) {
			return $items;
		}

		$today = WPCPM_Sponsor_Tiers::today();
		$tier  = WPCPM_Sponsor_Tiers::effective( $row, $today );
		$tiers = WPCPM_Sponsor_Tiers::live() && self::installed( 'WPCPM_Sponsor_Tier_Card' );

		if ( $tiers ) {
			$left = WPCPM_Sponsor_Tier_Card::term_days_left( $row, $today );

			if ( null !== $left && $left <= WPCPM_Sponsor_Tiers::NOTICE_DAYS ) {
				$day     = WPCPM_Sponsor_Tiers::format_day( (string) $row['tier_until'] );
				$items[] = array(
					'card'   => WPCPM_Sponsor_Tier_Card::CARD,
					'count'  => 1,
					'title'  => $left < 0
						/* translators: %s: the last day of the term that ended. */
						? sprintf( __( 'Your term ended on %s', 'wpcredits-program-manager' ), $day )
						/* translators: %s: the last day of the term. */
						: sprintf( __( 'Your term ends on %s', 'wpcredits-program-manager' ), $day ),
					'why'    => __( 'Your sponsorship says how to renew it.', 'wpcredits-program-manager' ),
					'action' => __( 'See your sponsorship', 'wpcredits-program-manager' ),
				);
			}
		}

		if ( self::installed( 'WPCPM_Sponsor_Offers' ) ) {
			// Exclusive, in this order: an offer counts once, under the first kind it matches.
			$offers = WPCPM_Sponsor_Offers::waiting_counts( $record );

			if ( $offers['returned'] > 0 ) {
				$items[] = array(
					'card'   => WPCPM_Sponsor_Offers::CARD,
					'count'  => (int) $offers['returned'],
					/* translators: %s: how many offers. */
					'title'  => sprintf( _n( '%s offer returned with a note', '%s offers returned with a note', $offers['returned'], 'wpcredits-program-manager' ), number_format_i18n( $offers['returned'] ) ),
					'why'    => __( 'An Administrator wrote a note on what to change.', 'wpcredits-program-manager' ),
					'action' => __( 'Open the offers', 'wpcredits-program-manager' ),
				);
			}

			if ( $offers['low'] > 0 ) {
				$items[] = array(
					'card'   => WPCPM_Sponsor_Offers::CARD,
					'count'  => (int) $offers['low'],
					/* translators: %s: how many offers. */
					'title'  => sprintf( _n( '%s offer running low on codes', '%s offers running low on codes', $offers['low'], 'wpcredits-program-manager' ), number_format_i18n( $offers['low'] ) ),
					'why'    => __( 'Fewer codes are left than the number you set for a warning. Add more, or pause the offer.', 'wpcredits-program-manager' ),
					'action' => __( 'Open the offers', 'wpcredits-program-manager' ),
				);
			}

			if ( $offers['draft'] > 0 ) {
				$items[] = array(
					'card'   => WPCPM_Sponsor_Offers::CARD,
					'count'  => (int) $offers['draft'],
					/* translators: %s: how many offers. */
					'title'  => sprintf( _n( '%s offer not switched on yet', '%s offers not switched on yet', $offers['draft'], 'wpcredits-program-manager' ), number_format_i18n( $offers['draft'] ) ),
					'why'    => __( 'Nobody can claim an offer until it is switched on.', 'wpcredits-program-manager' ),
					'action' => __( 'Open the offers', 'wpcredits-program-manager' ),
				);
			}
		}

		// The posts are counted first and the card's test asked only for a sponsor that has some to count.
		$posts = self::installed( 'WPCPM_Sponsor_Posts' ) ? WPCPM_Sponsor_Posts::returned_count( $record ) : 0;

		if ( $posts > 0 && WPCPM_Sponsor_Posts::draws( $record ) ) {
			$items[] = array(
				'card'   => WPCPM_Sponsor_Posts::CARD,
				'count'  => (int) $posts,
				/* translators: %s: how many posts. */
				'title'  => sprintf( _n( '%s post returned with a note', '%s posts returned with a note', $posts, 'wpcredits-program-manager' ), number_format_i18n( $posts ) ),
				'why'    => __( 'An Administrator wrote a note on what to change.', 'wpcredits-program-manager' ),
				'action' => __( 'Open the posts', 'wpcredits-program-manager' ),
			);
		}

		// The Events card prints the note only inside the planner's plan form, drawn while its allowance is ''.
		if ( $tiers && class_exists( 'WPCPM_Sponsor_Event' ) && class_exists( 'WPCPM_Sponsor_Event_Planner' ) && '' !== WPCPM_Sponsor_Event::returned_note( $record ) && '' === WPCPM_Sponsor_Event_Planner::allowance( $record, $row, $tier ) ) {
			$items[] = array(
				'card'   => WPCPM_Sponsor_Tier_Card::CARD_EVENTS,
				'count'  => 1,
				'title'  => __( 'Your event plan was returned with a note', 'wpcredits-program-manager' ),
				'why'    => __( 'An Administrator wrote a note on what to change.', 'wpcredits-program-manager' ),
				'action' => __( 'Open the events', 'wpcredits-program-manager' ),
			);
		}

		if ( self::installed( 'WPCPM_Sponsor_Agreement_Card' ) ) {
			$agreement = WPCPM_Sponsor_Agreement_Card::waiting_state( $record );

			if ( WPCPM_Sponsor_Agreement::SUMMARY_RETURNED === $agreement || WPCPM_Sponsor_Agreement::SUMMARY_REVOKED === $agreement ) {
				$items[] = array(
					'card'   => WPCPM_Sponsor_Agreement_Card::CARD,
					'count'  => 1,
					'title'  => WPCPM_Sponsor_Agreement::SUMMARY_RETURNED === $agreement
						? __( 'Collaboration Agreement returned', 'wpcredits-program-manager' )
						: __( 'Collaboration Agreement revoked', 'wpcredits-program-manager' ),
					'why'    => __( 'The program needs a new signed copy from your company.', 'wpcredits-program-manager' ),
					'action' => __( 'Open the agreement', 'wpcredits-program-manager' ),
				);
			}
		}

		if ( self::installed( 'WPCPM_Sponsor_Profile' ) ) {
			$missing = WPCPM_Sponsor_Profile::missing( $row );

			if ( $missing > 0 ) {
				$items[] = array(
					'card'   => WPCPM_Sponsor_Profile::CARD,
					'count'  => (int) $missing,
					/* translators: %s: how many details. */
					'title'  => sprintf( _n( '%s company profile detail missing', '%s company profile details missing', $missing, 'wpcredits-program-manager' ), number_format_i18n( $missing ) ),
					'why'    => __( 'Fill in what is missing on the company profile.', 'wpcredits-program-manager' ),
					'action' => __( 'Complete the profile', 'wpcredits-program-manager' ),
				);
			}
		}

		return $items;
	}

	/**
	 * How much waits in each view, for the menu's counts.
	 *
	 * @param array[] $items What `waiting_items()` returned.
	 * @return array<string, int> View key to count.
	 */
	private static function waiting_by_view( array $items ) {
		$counts = array();

		foreach ( $items as $item ) {
			$view            = self::view_of( (string) $item['card'] );
			$counts[ $view ] = ( isset( $counts[ $view ] ) ? $counts[ $view ] : 0 ) + (int) $item['count'];
		}

		return $counts;
	}

	/**
	 * The Overview (1.123.78, the version 3 concepts): a head that leads with what the company's support did,
	 * the figures, what waits on the company's people, the offers in play as the homepage's program cards, and
	 * the mentors looking for a sponsor as a door.
	 *
	 * @param string     $record  Sponsor key.
	 * @param array      $row     The sponsor's row, or the empty row.
	 * @param array|null $stored  The row the store holds, or null: a member's stamp that outlived its sponsor
	 *                            is drawn on the empty row, whose floor is no fact about anybody.
	 * @param array      $context What every card is handed.
	 */
	private static function render_overview( $record, array $row, $stored, array $context ) {
		$managed = ! empty( $context['can_manage'] ) ? $record : '';
		$claims  = self::installed( 'WPCPM_Sponsor_Offers' ) && class_exists( 'WPCPM_Sponsor_Claims' );
		$stats   = $claims && self::offers_to( 'WPCPM_Sponsor_Claims', 'stats' ) ? (array) WPCPM_Sponsor_Claims::stats( $record ) : array();
		$reached = $claims && self::offers_to( 'WPCPM_Sponsor_Claims', 'people_reached' ) ? (int) WPCPM_Sponsor_Claims::people_reached( $record ) : 0;
		$mentors = self::installed( 'WPCPM_Sponsor_Mentors' ) ? WPCPM_Sponsor_Mentors::linked( $record ) : array();

		self::render_overview_head( $record, $row, $reached, $mentors, $managed, ! empty( $context['can_manage'] ) );

		// The figures and what waits are facts about the sponsor, so neither is drawn on the empty row.
		if ( is_array( $stored ) ) {
			self::render_figures( $stats, $reached, $mentors, self::past_offers( $record ) );
			self::render_waiting( self::waiting_items( $record, $row ), $managed );
		}
		self::render_offer_cards( $record, $stats, $managed );
		self::render_door( $managed );
	}

	/**
	 * The Overview's head: the logo or the company's initials, the page's H1, the company's name, a thank-you
	 * that says what its support did this year, and New offer.
	 *
	 * @param string $record     Sponsor key.
	 * @param array  $row        The sponsor's row.
	 * @param int    $reached    People who claimed one of the company's offers this year.
	 * @param array  $mentors    What `WPCPM_Sponsor_Mentors::linked()` returned, or an empty array.
	 * @param string $managed    The sponsor an Administrator is looking at, or ''.
	 * @param bool   $can_manage Whether the reader is an Administrator, who also reads the record's status.
	 */
	private static function render_overview_head( $record, array $row, $reached, array $mentors, $managed, $can_manage ) {
		$name      = trim( (string) $row['name'] );
		$name      = '' === $name ? __( 'Unnamed sponsor', 'wpcredits-program-manager' ) : $name;
		$logo      = WPCPM_Sponsors_Index::display_logo( $record );
		$sponsored = isset( $mentors['mentors'] ) ? count( (array) $mentors['mentors'] ) + (int) $mentors['others'] : 0;
		$thanks    = array();

		if ( $reached > 0 ) {
			/* translators: %s: how many people claimed one of the company's offers this year. */
			$thanks[] = sprintf( _n( 'Thank you. This year your offers reached %s person.', 'Thank you. This year your offers reached %s people.', $reached, 'wpcredits-program-manager' ), number_format_i18n( $reached ) );
		}

		if ( $sponsored > 0 ) {
			$thanks[] = 0 === $reached
				/* translators: %s: how many mentors the company sponsors. */
				? sprintf( _n( 'Thank you. Your company sponsors %s mentor.', 'Thank you. Your company sponsors %s mentors.', $sponsored, 'wpcredits-program-manager' ), number_format_i18n( $sponsored ) )
				/* translators: %s: how many mentors the company sponsors. */
				: sprintf( _n( 'Your company sponsors %s mentor.', 'Your company sponsors %s mentors.', $sponsored, 'wpcredits-program-manager' ), number_format_i18n( $sponsored ) );
		}

		if ( array() === $thanks ) {
			$thanks[] = __( 'Thank you for supporting the program\'s students and mentors. Your offers, your mentors and your company each have a page in the menu.', 'wpcredits-program-manager' );
		}

		echo '<header class="wpcpm-head wpcpm-head--logo">';
		echo '<div class="wpcpm-sponsor__logo">';

		if ( is_array( $logo ) ) {
			// The site's own attachment, never a link to a file held somewhere else, which can expire within hours.
			printf( '<img class="wpcpm-sponsor__logo-image" src="%1$s" alt="" loading="lazy" decoding="async" />', esc_url( $logo['url'] ) );
		} else {
			printf( '<span class="wpcpm-sponsor__initials" aria-hidden="true">%s</span>', esc_html( self::initials( $name ) ) );
		}

		echo '</div><div class="wpcpm-head__copy">';
		echo self::page_name( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page_name().
		printf( '<h2 class="wpcpm-head__title">%s</h2>', esc_html( $name ) );
		printf( '<p class="wpcpm-head__lede">%s</p>', esc_html( implode( ' ', $thanks ) ) );

		if ( $can_manage && '' !== trim( (string) $row['status'] ) ) {
			/* translators: %s: the sponsor's status in the program. */
			printf( '<p class="wpcpm-head__facts">%s</p>', esc_html( sprintf( __( 'Status: %s', 'wpcredits-program-manager' ), trim( (string) $row['status'] ) ) ) );
		}

		if ( self::installed( 'WPCPM_Sponsor_Offers' ) ) {
			printf(
				'<div class="wpcpm-head__actions"><a class="wpcpm-btn wpcpm-btn--secondary" href="%1$s">%2$s</a></div>',
				esc_url( self::view_url( self::VIEW_OFFERS, $managed ) . '#' . self::OFFER_NEW ),
				esc_html__( 'New offer', 'wpcredits-program-manager' )
			);
		}

		echo '</div></header>';
	}

	/**
	 * The figures, as the homepage draws them: the people the company's offers reached this year, the codes
	 * claimed of the codes loaded with the codes left where they run out first, and the mentors the company
	 * sponsors. A figure with nothing to count is left out; with none, there is no band.
	 *
	 * @param array $stats   What `WPCPM_Sponsor_Claims::stats()` returned, or an empty array.
	 * @param int   $reached People who claimed one of the company's offers this year.
	 * @param array $mentors What `WPCPM_Sponsor_Mentors::linked()` returned, or an empty array.
	 * @param array $past    The ids of the offers that are over, ended or past their last day, as keys.
	 */
	private static function render_figures( array $stats, $reached, array $mentors, array $past = array() ) {
		$cells = array();

		if ( $reached > 0 ) {
			$cells[] = array(
				'value' => esc_html( number_format_i18n( $reached ) ),
				/* translators: a figure's label, under the number of people. */
				'label' => _n( 'person reached this year', 'people reached this year', $reached, 'wpcredits-program-manager' ),
				'base'  => __( 'who claimed one of your offers', 'wpcredits-program-manager' ),
			);
		}

		// The codes: only offers with a pool that are not over, as the Overview's cards are, an offer switched on
		// but past its last day included (the release's review); the codes left named where they run out first.
		$claimed = 0;
		$loaded  = 0;
		$fewest  = null;

		foreach ( isset( $stats['offers'] ) ? (array) $stats['offers'] : array() as $id => $offer ) {
			if ( WPCPM_Sponsor_Offers::KIND_CODES !== $offer['kind'] || WPCPM_Sponsor_Offers::STATE_ENDED === $offer['state'] || isset( $past[ (int) $id ] ) ) {
				continue;
			}

			$claimed += (int) $offer['claimed'];
			$loaded  += (int) $offer['claimed'] + (int) $offer['available'];

			if ( WPCPM_Sponsor_Offers::STATE_LIVE === $offer['state'] && ( null === $fewest || (int) $offer['available'] < (int) $fewest['available'] ) ) {
				$fewest = $offer;
			}
		}

		if ( $loaded > 0 ) {
			$cells[] = array(
				// The homepage's shape for a part of a whole: the part, then the whole small after a slash.
				'value' => esc_html( number_format_i18n( $claimed ) ) . '<small> / ' . esc_html( number_format_i18n( $loaded ) ) . '</small>',
				'label' => __( 'codes claimed', 'wpcredits-program-manager' ),
				'base'  => null === $fewest
					? __( 'in the offers not ended', 'wpcredits-program-manager' )
					/* translators: 1: how many codes are left, 2: the offer's title. */
					: sprintf( _n( '%1$s left in %2$s', '%1$s left in %2$s', (int) $fewest['available'], 'wpcredits-program-manager' ), number_format_i18n( (int) $fewest['available'] ), (string) $fewest['title'] ),
			);
		}

		$sponsored = isset( $mentors['mentors'] ) ? count( (array) $mentors['mentors'] ) + (int) $mentors['others'] : 0;

		if ( $sponsored > 0 ) {
			$teaching = 0;

			foreach ( (array) $mentors['mentors'] as $mentor ) {
				if ( (int) $mentor['current'] > 0 ) {
					++$teaching;
				}
			}

			$cells[] = array(
				'value' => esc_html( number_format_i18n( $sponsored ) ),
				'label' => _n( 'mentor you sponsor', 'mentors you sponsor', $sponsored, 'wpcredits-program-manager' ),
				/* translators: %s: how many of the mentors have students now. */
				'base'  => sprintf( _n( '%s with students now', '%s with students now', $teaching, 'wpcredits-program-manager' ), number_format_i18n( $teaching ) ),
			);
		}

		if ( array() === $cells ) {
			return;
		}

		printf( '<section class="wpcpm-figures wpcpm-figures--%1$d" aria-label="%2$s">', count( $cells ), esc_attr__( 'What your support did', 'wpcredits-program-manager' ) );

		foreach ( $cells as $cell ) {
			printf(
				'<div class="wpcpm-figure"><p class="wpcpm-figure__value">%1$s</p><p class="wpcpm-figure__label">%2$s</p><p class="wpcpm-figure__base">%3$s</p></div>',
				$cell['value'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where each value is built above.
				esc_html( $cell['label'] ),
				esc_html( $cell['base'] )
			);
		}

		echo '</section>';

		printf(
			'<p class="wpcpm-figures__source">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: today's date. */
					__( 'As of %s, from the claims on this site and the program\'s mentor records.', 'wpcredits-program-manager' ),
					wp_date( get_option( 'date_format' ) )
				)
			)
		);
	}

	/**
	 * What waits on the company's people: each kind a plain row with its status tag in words, the sentence
	 * that says why, and the link to the view and card that hold it (the owner, 10 November 2026: a status tag,
	 * never a colored edge). Nothing waiting is one quiet line.
	 *
	 * @param array[] $items   What `waiting_items()` returned.
	 * @param string  $managed The sponsor an Administrator is looking at, or ''.
	 */
	private static function render_waiting( array $items, $managed ) {
		if ( array() === $items ) {
			printf( '<p class="wpcpm-overview__quiet wpcpm-sponsor__nothing">%s</p>', esc_html__( 'Nothing waits for you.', 'wpcredits-program-manager' ) );

			return;
		}

		printf( '<section class="wpcpm-sponsor__waiting" aria-labelledby="wpcpm-sponsor-overview-waiting"><h2 class="wpcpm-sponsor__group-title" id="wpcpm-sponsor-overview-waiting">%s</h2>', esc_html__( 'Waiting for you', 'wpcredits-program-manager' ) );

		foreach ( $items as $item ) {
			printf(
				'<div class="wpcpm-sponsor__wait"><span class="wpcpm-status wpcpm-status--red">%1$s</span><p class="wpcpm-sponsor__wait-text"><strong>%2$s</strong> <span>%3$s</span></p><a class="wpcpm-btn wpcpm-btn--secondary" href="%4$s">%5$s</a></div>',
				esc_html__( 'Waits', 'wpcredits-program-manager' ),
				esc_html( (string) $item['title'] ),
				esc_html( (string) $item['why'] ),
				esc_url( self::card_url( (string) $item['card'], $managed ) ),
				esc_html( (string) $item['action'] )
			);
		}

		echo '</section>';
	}

	/**
	 * The offers in play as the homepage's program cards: the status tag, the title, what you get, the facts
	 * and the claims a month; a link to change the offer and, once anybody claimed it, to the claims.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $stats   What `WPCPM_Sponsor_Claims::stats()` returned, or an empty array.
	 * @param string $managed The sponsor an Administrator is looking at, or ''.
	 */
	private static function render_offer_cards( $record, array $stats, $managed ) {
		if ( ! self::installed( 'WPCPM_Sponsor_Offers' ) || ! self::offers_to( 'WPCPM_Sponsor_Offers', 'render_card' ) ) {
			return;
		}

		$offers = array();

		foreach ( WPCPM_Sponsor_Offers::offers_of( $record ) as $offer ) {
			if ( ! WPCPM_Sponsor_Offers::is_past( $offer ) ) {
				$offers[] = $offer;
			}
		}

		printf( '<section class="wpcpm-sponsor__offers" aria-labelledby="wpcpm-sponsor-overview-offers"><h2 class="wpcpm-sponsor__group-title" id="wpcpm-sponsor-overview-offers">%s</h2>', esc_html__( 'Your offers', 'wpcredits-program-manager' ) );

		if ( array() === $offers ) {
			printf(
				'<p class="wpcpm-overview__quiet">%1$s <a href="%2$s">%3$s</a></p></section>',
				esc_html__( 'No offer is running.', 'wpcredits-program-manager' ),
				esc_url( self::view_url( self::VIEW_OFFERS, $managed ) . '#' . self::OFFER_NEW ),
				esc_html__( 'Create one', 'wpcredits-program-manager' )
			);

			return;
		}

		echo '<div class="wpcpm-offer-cards">';

		foreach ( $offers as $offer ) {
			$numbers = isset( $stats['offers'][ $offer['id'] ] ) ? (array) $stats['offers'][ $offer['id'] ] : array();

			WPCPM_Sponsor_Offers::render_card(
				$offer,
				$numbers,
				isset( $stats['months'] ) ? (array) $stats['months'] : array(),
				array(
					'change'  => self::card_url( WPCPM_Sponsor_Offers::CARD, $managed, 'wpcpm-offer-' . (int) $offer['id'] ),
					'claimed' => self::card_url( WPCPM_Sponsor_Usage::CARD, $managed ),
				)
			);
		}

		echo '</div></section>';
	}

	/**
	 * The mentors looking for a sponsor, as a door to the view that lists them, while any are.
	 *
	 * @param string $managed The sponsor an Administrator is looking at, or ''.
	 */
	private static function render_door( $managed ) {
		if ( ! self::installed( 'WPCPM_Sponsor_Mentors' ) ) {
			return;
		}

		$looking = count( WPCPM_Sponsor_Mentors::looking() );

		if ( $looking <= 0 ) {
			return;
		}

		printf(
			'<a class="wpcpm-sponsor__door" href="%1$s">%2$s<span class="wpcpm-sponsor__door-text"><strong>%3$s</strong> <span>%4$s</span></span><span class="wpcpm-sponsor__door-cue">%5$s</span></a>',
			esc_url( self::card_url( WPCPM_Sponsor_Mentors::CARD_LOOKING, $managed ) ),
			WPCPM_Icons::svg( 'users', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
			/* translators: %s: how many mentors are looking for a sponsor. */
			esc_html( sprintf( _n( '%s mentor is looking for a sponsor', '%s mentors are looking for a sponsor', $looking, 'wpcredits-program-manager' ), number_format_i18n( $looking ) ) ),
			esc_html__( 'Each gives a few hours a week to students on the program.', 'wpcredits-program-manager' ),
			esc_html__( 'See who', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The company's offers that are over: ended, or switched on and past their last day (`is_past()`), by id.
	 *
	 * @param string $record Sponsor key.
	 * @return array<int, true>
	 */
	private static function past_offers( $record ) {
		$past = array();

		if ( ! self::offers_to( 'WPCPM_Sponsor_Offers', 'offers_of' ) || ! self::offers_to( 'WPCPM_Sponsor_Offers', 'is_past' ) ) {
			return $past;
		}

		foreach ( WPCPM_Sponsor_Offers::offers_of( $record ) as $offer ) {
			if ( WPCPM_Sponsor_Offers::is_past( $offer ) ) {
				$past[ (int) $offer['id'] ] = true;
			}
		}

		return $past;
	}

	/**
	 * The month and year the company became a sponsor, from the record's `created` day, or ''.
	 *
	 * @param array $row The sponsor's row.
	 * @return string
	 */
	private static function sponsor_since( array $row ) {
		$created = isset( $row['created'] ) ? trim( (string) $row['created'] ) : '';

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $created, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}

		$time = gmmktime( 12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] );

		return (string) wp_date( _x( 'F Y', 'a month and year, as in "sponsor since March 2026"', 'wpcredits-program-manager' ), $time, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The Your sponsorship section's line: the cards the section draws for this sponsor, named in the
	 * section's order (1.123.22), where every sponsor read "Your tier, your events, your reports and
	 * your badge." though a Friend has none of the three and a Supporter or a Backer no events.
	 *
	 * Each card is asked the test that decides whether it draws: the Events card, the tier card's
	 * second, the tier card's own `draws_events()` (the card the Overview's row for a returned event
	 * asks about, through the planner's allowance, which implies that test); the Reports card
	 * whether the sponsor has a final report (`WPCPM_Sponsor_Report_Card::finals()`); the badge
	 * whether the store holds the sponsor's row and the row is eligible for one
	 * (`WPCPM_Sponsor_Badge::eligible()`). The tier card always draws while
	 * the launch switch is on, so every line names the tier first. Each of the eight lines is one whole
	 * sentence, so a translator meets each as it reads. While the switch is off the section draws
	 * nothing and is not printed, so nothing is asked and the line is empty.
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
	 * it closed), a row's on the Overview among them. All three are enqueued before any card is drawn,
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
	 * named in `view_owners()`, so each view's order is fixed in one place - a card that is not
	 * named there is not on the dashboard, and a view none of whose cards drew says so in a line.
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
	 * Whether a class this page reads is loaded with the method it calls: a checkout, or a suite's stand-in,
	 * may hold a card's class from before the method came (1.123.78).
	 *
	 * @param string $owner  The class.
	 * @param string $method The method.
	 * @return bool
	 */
	private static function offers_to( $owner, $method ) {
		return class_exists( $owner ) && method_exists( $owner, $method );
	}

	/**
	 * Whether a card's class is installed: the one question `card()` asks before it draws a card, and
	 * `waiting_items()` before it counts what waits in that card.
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
	 * In the frame (1.123.78) the form keeps the view being read, so an Administrator moving to another
	 * sponsor stays on the same view.
	 *
	 * @param string $current Sponsor key currently being viewed.
	 * @param string $view    The view being read, a `VIEW_*` key.
	 */
	private static function render_switcher( $current, $view = self::VIEW_OVERVIEW ) {
		WPCPM_Dashboards::render_switcher(
			array(
				'id'      => 'wpcpm-sponsor-switcher',
				'name'    => WPCPM_Sponsor_Roster::ARG_VIEW,
				'options' => WPCPM_Sponsor_Roster::switcher_options(),
				'current' => $current,
				'label'   => __( 'Viewing as sponsor', 'wpcredits-program-manager' ),
				'find'    => __( 'Find a sponsor', 'wpcredits-program-manager' ),
				'none'    => __( 'No sponsors match that search.', 'wpcredits-program-manager' ),
				'keep'    => self::VIEW_OVERVIEW !== $view ? array( self::VIEW_ARG => $view ) : array(),
			)
		);
	}

	/**
	 * The flash a card's handler left, as a tone and a sentence in the reader's words, with the values it carries put where
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
	 * @return array{0: string, 1: string} The status's tone and the sentence, a space and the detail, or
	 *                                     the detail alone; two empty strings where nothing is said.
	 */
	private static function message_of( array $flash ) {
		$status = isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';

		if ( '' === $status ) {
			return array( '', '' );
		}

		$messages = self::messages();

		if ( ! isset( $messages[ $status ] ) || ! is_array( $messages[ $status ] ) ) {
			return array( '', '' );
		}

		$own      = (string) $messages[ $status ][1];
		$sentence = self::filled( $own, isset( $flash['args'] ) && is_array( $flash['args'] ) ? $flash['args'] : array() );
		$detail   = isset( $flash['detail'] ) ? trim( (string) $flash['detail'] ) : '';

		if ( '' === $sentence && ( '' !== $own || '' === $detail ) ) {
			return array( '', '' );
		}

		return array(
			(string) $messages[ $status ][0],
			'' !== $detail ? ( '' !== $sentence ? $sentence . ' ' : '' ) . $detail : $sentence,
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
