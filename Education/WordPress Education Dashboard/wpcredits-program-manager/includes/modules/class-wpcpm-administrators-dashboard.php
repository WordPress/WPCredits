<?php
/**
 * The Administrator Dashboard: every queue on one page, for program managers.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page program managers work from.
 *
 * Built exactly like the Institution Dashboard (design of 4 September 2026, decision 1):
 * one page the module creates and adopts by slug, gated to the administrator level with
 * `metadata_exists()`, a block and a shortcode that both reach `render()`, and one
 * `render()` that calls the cards in order. The order of the calls is the spec's, and it
 * is the whole of it.
 */
final class WPCPM_Administrators_Dashboard {
	/** The shortcode, for a page the plugin did not create. */
	const SHORTCODE = 'wpcpm_administrator_dashboard';
	/** The block. */
	const BLOCK = 'wpcpm/administrator-dashboard';
	/** The page the plugin created, by ID. */
	const OPT_PAGE = 'wpcpm_administrator_page_id';
	/** The stylesheet handle; the theme's skin depends on it when it is registered. */
	const STYLE = 'wpcpm-administrator-dashboard';
	/** The slug, which is also the theme template's name: `page-administrator-dashboard.html`. */
	const SLUG = 'administrator-dashboard';
	/** Whether the title rename for TITLE_VERSION has run. */
	const OPT_TITLE_FIXED = 'wpcpm_administrator_page_title_fixed';
	/** Bumped when the product renames the page; `maybe_rename_page()` follows once. */
	const TITLE_VERSION = 1;
	/** Titles a previous version gave the page. None yet. */
	const OLD_TITLES = array();
	/** The module id `WPCPM_Dashboards::nothing_to_show()` knows. */
	const MODULE = 'administrators';

	/**
	 * Hooks. Called from `WPCPM_Administrators::boot()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_rename_page' ), 20 );
	}

	/**
	 * The stylesheet, the shortcode and the block.
	 */
	public static function register() {
		wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/administrator.css', array(), WPCPM_VERSION );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
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
		return __( 'Administrator Dashboard', 'wpcredits-program-manager' );
	}

	/**
	 * Rename the page once per TITLE_VERSION, only when it still carries an old title.
	 */
	public static function maybe_rename_page() {
		if ( (int) get_option( self::OPT_TITLE_FIXED ) >= self::TITLE_VERSION ) {
			return;
		}

		// Written first, whatever happens next: a rename that fails should not be retried
		// on every request of every visitor.
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
	 * A site that has the page but not the option (restored from a backup, or migrated)
	 * adopts it rather than creating a second one at `administrator-dashboard-2`.
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

		// The second argument is what makes a failure a WP_Error rather than 0: without it,
		// is_wp_error( $page_id ) below can never be true, the same way WPCPM_Institutions_
		// Dashboard::ensure_page() asks.
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
	 * Gate the page to the administrator level, once.
	 *
	 * `metadata_exists()` and not the value: the level is registered with a default of
	 * `public`, so `get_post_meta()` reads a brand-new page as deliberately public and never
	 * gates it, which is how the Institution Dashboard first came up on the live site.
	 *
	 * @param int $page_id The page.
	 */
	private static function gate_page( $page_id ) {
		if ( ! metadata_exists( 'post', (int) $page_id, WPCPM_Content_Access::META_KEY ) ) {
			update_post_meta( (int) $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_ADMIN );
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
	 * The page. The block and the shortcode both land here.
	 *
	 * The capability is checked here as well as by the page's level, because a page can be
	 * reached through the shortcode in some other post.
	 *
	 * @param array $attributes `title`.
	 * @return string
	 */
	public static function render( $attributes = array() ) {
		$atts = shortcode_atts( array( 'title' => '' ), is_array( $attributes ) ? $attributes : array(), self::SHORTCODE );

		wp_enqueue_style( self::STYLE );

		// Every form on this page prints `data-wpcpm-once`; the guard is inert without the
		// script, and the Institution Dashboard shipped once that way.
		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( 'wpcpm-forms' );

		if ( ! is_user_logged_in() ) {
			// Same shape as WPCPM_Institutions_Dashboard::render() for a logged-out visitor: a
			// sentence and a way back in, not a dead end nobody without an account can do
			// anything with.
			return self::notice(
				__( 'Please log in to see the Administrator Dashboard.', 'wpcredits-program-manager' ),
				wp_login_url( get_permalink() ),
				__( 'Log in', 'wpcredits-program-manager' )
			);
		}

		$viewer = wp_get_current_user();

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return self::notice( WPCPM_Dashboards::nothing_to_show( self::MODULE, false ) );
		}

		$data   = WPCPM_Administrators_Cards::collect();
		$counts = WPCPM_Administrators_Cards::counts( $data );

		ob_start();

		echo '<div class="wpcpm-dashboard wpcpm-administrator">';

		WPCPM_Two_Factor::prompt( $viewer );

		if ( '' !== trim( (string) $atts['title'] ) ) {
			printf( '<h2 class="wpcpm-dashboard__title">%s</h2>', esc_html( $atts['title'] ) );
		}

		self::render_messages();

		WPCPM_Administrators_Cards::render_strip( $counts );
		WPCPM_Administrators_Cards::render_applications( $data['applications'] );
		WPCPM_Administrators_Cards::render_agreements( $data['agreements'] );
		WPCPM_Administrators_Cards::render_reports( $data['reports'] );
		WPCPM_Administrators_Cards::render_requests( $data['requests'] );
		WPCPM_Administrators_Cards::render_programs( $data['programs'] );
		WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] );

		self::render_help();

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * What the last decision left to say, in the words the wp-admin queue would use.
	 *
	 * Every handler this page posts to flashes on the Institutions screen's channel, so the
	 * four message maps are merged here as that screen merges them. Taken once, so it shows
	 * once, here or there. Draft now (WPCPM_Semester_Report_Screen::ACTION_DRAFT) is the one
	 * decision this page posts that flashes somewhere else, on its own screen's channel, so it
	 * is read separately below rather than merged into this map (since 1.92.0, decision 2 of
	 * the final review: a successful draft never flashes here at all, because it leaves this
	 * page for the editor; only a refusal does).
	 */
	private static function render_messages() {
		$status = sanitize_key( (string) WPCPM_Flash::take( WPCPM_Institutions::FLASH ) );

		if ( '' !== $status ) {
			$messages = array();

			if ( method_exists( 'WPCPM_Institutions', 'queue_messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Institutions::queue_messages() );
			}

			if ( class_exists( 'WPCPM_Institution_Panel' ) && method_exists( 'WPCPM_Institution_Panel', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Institution_Panel::messages() );
			}

			if ( class_exists( 'WPCPM_Institution_Request' ) && method_exists( 'WPCPM_Institution_Request', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Institution_Request::messages() );
			}

			if ( class_exists( 'WPCPM_Sync_Module' ) && method_exists( 'WPCPM_Sync_Module', 'sync_messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Sync_Module::sync_messages() );
			}

			if ( isset( $messages[ $status ] ) && is_array( $messages[ $status ] ) ) {
				printf(
					'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--%1$s">%2$s</p>',
					esc_attr( (string) $messages[ $status ][0] ),
					esc_html( (string) $messages[ $status ][1] )
				);
			}
		}

		self::render_report_message();
	}

	/**
	 * What a refused Draft now left to say, read on the semester report screen's own flash
	 * channel and in its own words: `message_for()` is the one place that decides what a
	 * status there means, and this page must not spell the same refusal differently.
	 */
	private static function render_report_message() {
		if ( ! class_exists( 'WPCPM_Semester_Report_Screen' ) || ! method_exists( 'WPCPM_Semester_Report_Screen', 'message_for' ) ) {
			return;
		}

		$flash = WPCPM_Flash::take( WPCPM_Semester_Report_Screen::FLASH );

		if ( ! is_array( $flash ) || empty( $flash['status'] ) ) {
			return;
		}

		list( $class, $text ) = WPCPM_Semester_Report_Screen::message_for( $flash );

		if ( '' === $text ) {
			return;
		}

		// Only bounce() honours the return field, so every report message that lands here is a
		// refusal: a successful Draft now opens the new draft in the editor instead. The error
		// tone is what the theme colours; the status keeps the screen's own modifier for a
		// rule that wants one sentence in particular.
		printf(
			'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error wpcpm-dashboard__message--%1$s">%2$s</p>',
			esc_attr( $class ),
			esc_html( $text )
		);
	}

	/**
	 * The Updates column and the program manager guide, last on the page.
	 */
	private static function render_help() {
		if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) || ! method_exists( 'WPCPM_Handbook_Assistant', 'render_resources' ) ) {
			return;
		}

		echo WPCPM_Handbook_Assistant::render_resources( 'administrator' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_resources() escapes every value it interpolates.
	}

	/**
	 * One sentence in the dashboard's shell, for the viewer who gets no page, with an optional
	 * button - the shape WPCPM_Institutions_Dashboard::notice() also draws for its own
	 * logged-out visitor.
	 *
	 * @param string $message     Escaped text, or text with the one link `nothing_to_show()` adds.
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
