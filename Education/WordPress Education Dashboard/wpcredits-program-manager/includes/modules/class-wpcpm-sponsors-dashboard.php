<?php
/**
 * The Sponsor Dashboard: a sponsor's profile, its mentors, its interests and its people.
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
 * Every card this page owns (`profile`, `mentors`, `interests`, `people`) is a canonical
 * disclosure, keyed `wpcpm-sponsor-<card>` so `leave()` can open the one a flash names. The
 * three acting cards live in their own files (Task 9); this class owns the page, the gate, the
 * identity, the program contact and the read-only people list.
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
	 * styled this one.
	 */
	public static function register_assets() {
		WPCPM_Mentors_Dashboard::register_assets();

		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/sponsor.css',
				array( WPCPM_Mentors_Dashboard::STYLE ),
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
	 * Membership first, then three exclusions, exactly as the institution page's own
	 * `should_route()` reads: a mentor is excluded, because an account that both mentors and
	 * represents a sponsor is routed to the Mentor Report Card, matching the institution page's
	 * own rule for exactly that account shape; a program manager or anyone who can write posts
	 * needs wp-admin and is left alone.
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
	 * @param string $status A key of `messages()`.
	 * @param string $card   The card to open, or ''.
	 * @param string $record The sponsor a manager was looking at, or ''.
	 * @param string $detail A sentence for the reader after the status's own, or ''. Trimmed
	 *                       to three hundred characters: a status names the outcome, the
	 *                       detail names the line (plan ruling 10).
	 */
	public static function leave( $status, $card, $record = '', $detail = '' ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'status' => sanitize_key( (string) $status ),
				'card'   => sanitize_key( (string) $card ),
				'detail' => mb_substr( sanitize_text_field( (string) $detail ), 0, 300 ),
			)
		);

		$url = self::page_url();
		$url = '' === $url ? home_url( '/' ) : $url;

		if ( '' !== (string) $record && current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$url = add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, (string) $record, $url );
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
	 * The union of `WPCPM_Sponsor_Profile::messages()`, `WPCPM_Sponsor_Interests::messages()`
	 * and `WPCPM_Sponsor_Mentors::messages()`, each guarded by `class_exists()` so a checkout
	 * missing a later phase's card still renders the page. `refused` is the base: every card's
	 * own copy of it says the same thing, so whichever wins the merge reads identically.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to notice class and sentence.
	 */
	public static function messages() {
		$messages = array(
			'refused' => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);

		foreach ( array( 'WPCPM_Sponsor_Profile', 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Interests', 'WPCPM_Sponsor_Mentors' ) as $card ) {
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

		$row     = WPCPM_Sponsors_Index::row( $record );
		$row     = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
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
		self::render_help( $record );

		foreach ( array( 'WPCPM_Sponsor_Profile', 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Posts', 'WPCPM_Sponsor_Mentors', 'WPCPM_Sponsor_Interests', 'WPCPM_Sponsor_Logo', 'WPCPM_Sponsor_Agreement_Card' ) as $owner ) {
			self::card( $owner, $record, $context );
		}

		self::render_people( $record, $context );

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Register (once) and enqueue the double-submit guard every form on this page carries.
	 */
	private static function enqueue_forms() {
		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( 'wpcpm-forms' );
	}

	/**
	 * Draw one of the dashboard's cards, if the piece that owns it is installed.
	 *
	 * Phase S2 to S4's cards land as separate commits and any one of them may be absent from a
	 * checkout, so a missing card leaves a gap on the page rather than a fatal. The owners are
	 * named now, in the S1 through S4 order, so the page order is fixed once and each phase
	 * fills its own gap - a card that is not called from `render()` is not on the dashboard.
	 *
	 * @param string $owner     Class that owns the card.
	 * @param string $record_id Sponsors record ID being rendered.
	 * @param array  $context   What every card is handed.
	 */
	private static function card( $owner, $record_id, array $context ) {
		if ( ! class_exists( $owner ) || ! method_exists( $owner, 'render' ) ) {
			return;
		}

		call_user_func( array( $owner, 'render' ), $record_id, $context );
	}

	/**
	 * A "view as" control for program managers.
	 *
	 * Keyed by record and not by user, because with several members per sponsor a user is the
	 * wrong unit: two accounts at one company are one entry here. Only a manager ever sees it,
	 * and `resolve_sponsor()` does not even read the argument for anyone else, so a member
	 * appending it to the URL changes nothing.
	 *
	 * @param string $current Sponsors record ID currently being viewed.
	 */
	private static function render_switcher( $current ) {
		$options = WPCPM_Sponsor_Roster::switcher_options();

		// One sponsor is not a choice, and a select with a single option is a control that
		// cannot do anything.
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
			'<label for="wpcpm-sponsor-switcher">%s</label> ',
			esc_html__( 'Viewing as sponsor', 'wpcredits-program-manager' )
		);
		printf( '<select name="%s" id="wpcpm-sponsor-switcher">', esc_attr( WPCPM_Sponsor_Roster::ARG_VIEW ) );

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
	 * The flash a card's handler left, in the reader's words.
	 *
	 * Nothing when the status is empty or unknown to `messages()`: a stale or hand-edited flash
	 * must not print a blank notice.
	 *
	 * @param array $flash `status`, `card`.
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

		$detail = isset( $flash['detail'] ) ? trim( (string) $flash['detail'] ) : '';

		printf(
			'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--%1$s">%2$s%3$s</p>',
			esc_attr( (string) $messages[ $status ][0] ),
			esc_html( (string) $messages[ $status ][1] ),
			'' !== $detail ? ' <span class="wpcpm-dashboard__detail">' . esc_html( $detail ) . '</span>' : ''
		);
	}

	/**
	 * The identity header: the sponsor's logo or initials, its name, website, product and the
	 * facts a manager alone is shown.
	 *
	 * @param string $record     Sponsors record ID.
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
			// The site's own attachment, copied by the sync or uploaded by the sponsor: never an
			// Airtable URL, which expires within hours.
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

		if ( '' !== trim( (string) $row['product_type'] ) ) {
			printf( '<p class="wpcpm-sponsor__fact">%s</p>', esc_html( trim( (string) $row['product_type'] ) ) );
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
						/* translators: %s: the Airtable Status. */
						__( 'Status: %s', 'wpcredits-program-manager' ),
						trim( (string) $row['status'] )
					)
				)
			);
		}

		echo '</div></header>';
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
	 * The base holds addresses typed by people, so a bare `example.com` is common: given a
	 * scheme rather than dropped.
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
	 * The Resources section, with this sponsor's own program contact in it.
	 *
	 * The section is `WPCPM_Handbook_Assistant::render_resources()`, asked for the sponsor
	 * audience: its own handbook page, the program's Slack channel, the announcements at that
	 * access level, and the assistant. The one thing added is the person.
	 *
	 * @param string $record Sponsors record ID.
	 */
	private static function render_help( $record ) {
		if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) || ! method_exists( 'WPCPM_Handbook_Assistant', 'render_resources' ) ) {
			return;
		}

		echo WPCPM_Handbook_Assistant::render_resources( 'sponsor', self::contact_block( $record ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the assistant and by contact_block(), both of which escape what they interpolate.
	}

	/**
	 * Who this sponsor writes to, as the Resources section's last word.
	 *
	 * Resolved through `WPCPM_Sponsors_Index::manager_of()`, which reads the assigned Team
	 * Members row at read time so a renamed manager shows correctly the morning after the sync.
	 * A sponsor with nobody assigned prints nothing at all rather than a heading with a blank
	 * under it (ruling 6: nothing, not an empty heading).
	 *
	 * @param string $record Sponsors record ID.
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
	 * do not invite each other in this release (design spec of 4 September 2026, section 14).
	 *
	 * @param string $record  Sponsors record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	private static function render_people( $record, array $context ) {
		$members = WPCPM_Sponsor_Members::members_of( $record );

		// A section around the disclosure, as every card on this page and the Institution Dashboard's
		// semester report: the section owns the card rhythm (the rule above it, the room), the
		// disclosure only folds. The same shape on both pages is what lets them share one skin.
		echo '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-people" class="wpcpm-group wpcpm-group__disclosure">';
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'People', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $members ) ) )
		);
		echo '<div class="wpcpm-group__body">';

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
				esc_url( admin_url( 'admin.php?page=wpcpm-sponsors' ) ),
				esc_html__( 'Attach or remove accounts on the Sponsors screen.', 'wpcredits-program-manager' )
			);
		} else {
			echo '<p class="wpcpm-student__note">' . esc_html__( 'To add a colleague, write to your contact at the program.', 'wpcredits-program-manager' ) . '</p>';
		}

		echo '</div></details></section>';
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
