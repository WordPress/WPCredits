<?php
/**
 * Admin menu and the Tools screen.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the top-level "WPCredits Program" menu, whose every screen is titled with its own name:
 * the Overview, whose screen is `WPCPM_Overview`'s, a screen per audience, the Tools screen and a
 * screen per tool, and Settings, whose screen is `WPCPM_Settings_Screen`'s.
 */
class WPCPM_Admin {

	const MENU_SLUG  = 'wpcpm';
	const TOOLS_SLUG = 'wpcpm-tools';

	/**
	 * The Settings screen, which the menu's last entry opens.
	 *
	 * @var WPCPM_Settings_Screen
	 */
	private $settings;

	/**
	 * Hooks, and the Settings screen, which hooks its own handlers.
	 */
	public function __construct() {
		$this->settings = new WPCPM_Settings_Screen();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the menu: the Overview, a page per audience, the Tools screen and a page per tool,
	 * then Settings.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'WPCredits Program', 'wpcredits-program-manager' ),
			__( 'WPCredits Program', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			self::MENU_SLUG,
			array( $this, 'render_overview' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Overview', 'wpcredits-program-manager' ),
			__( 'Overview', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			self::MENU_SLUG,
			array( $this, 'render_overview' )
		);

		foreach ( WPCPM_Modules::all() as $module ) {
			add_submenu_page(
				self::MENU_SLUG,
				$module->label(),
				// The menu title, not the page title: a module may hang a pending-count bubble on
				// it, and the plain label stays the `<h1>`.
				$module->menu_label(),
				WPCPM_Roles::CAP_MANAGE,
				$module->page_slug(),
				array( $module, 'render_admin_page' )
			);
		}

		// Listed after the audience screens, and called what the Overview and the program manager
		// guide call the parts of the program run on their own: Tools. The slug is the one it always
		// had, so a bookmark still opens it.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tools', 'wpcredits-program-manager' ),
			__( 'Tools', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			self::TOOLS_SLUG,
			array( $this, 'render_tools' )
		);

		foreach ( WPCPM_Tools::all() as $tool ) {
			add_submenu_page(
				self::MENU_SLUG,
				$tool->label(),
				// Indented so the submenu reads as a tool belonging to Tools rather
				// than as another audience.
				'- ' . $tool->label(),
				WPCPM_Roles::CAP_MANAGE,
				$tool->page_slug(),
				array( $tool, 'render_admin_page' )
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'wpcredits-program-manager' ),
			__( 'Settings', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			WPCPM_Settings_Screen::SETTINGS_SLUG,
			array( $this->settings, 'render_settings' )
		);
	}

	/**
	 * The Tools screen: one card per tool.
	 */
	public function render_tools() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html__( 'Tools', 'wpcredits-program-manager' ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html__( 'Parts of the program that can be switched on, run and configured on their own.', 'wpcredits-program-manager' ) . '</p>';

		$tools = WPCPM_Tools::all();

		if ( empty( $tools ) ) {
			echo '<div class="wpcpm-card"><p>' . esc_html__( 'No tools are registered.', 'wpcredits-program-manager' ) . '</p></div>';
			echo '</div>';

			return;
		}

		echo '<div class="wpcpm-modules">';

		foreach ( $tools as $tool ) {
			echo '<div class="wpcpm-module-card">';
			printf(
				'<h2><a href="%1$s">%2$s</a></h2>',
				esc_url( $tool->admin_url() ),
				esc_html( $tool->label() )
			);
			echo '<p>' . esc_html( $tool->description() ) . '</p>';

			// The card's status line, with its rule above it, when the tool has something to say.
			$status = $tool->status_line();

			if ( '' !== $status ) {
				echo '<p class="wpcpm-tool-status">';
				self::render_tool_status( $tool, $status );
				echo '</p>';
			}

			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url( $tool->admin_url() ),
				esc_html__( 'Open tool', 'wpcredits-program-manager' )
			);

			echo '</div>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * A tool's status line, printed the one way on the Tools screen and the Overview: its words, and
	 * for a tool that cannot run the warning they are, since the reason is the tool's to give (the
	 * Airtable connection for most, and for Need help? its switch or its provider).
	 *
	 * The words alone, since each screen puts them in its own place: a line of the tool's card on the
	 * Tools screen, with its rule above it, and a table cell on the Overview. Each screen reads the
	 * line once, and says what it says for a tool with nothing to report.
	 *
	 * @param WPCPM_Tool $tool   The tool.
	 * @param string     $status Its status line (`WPCPM_Tool::status_line()`), not empty.
	 */
	public static function render_tool_status( WPCPM_Tool $tool, $status ) {
		if ( $tool->is_ready() ) {
			echo esc_html( $status );

			return;
		}

		printf( '<span class="wpcpm-warning">%s</span>', esc_html( $status ) );
	}

	/**
	 * Load CSS and JS on this plugin's screens only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcpm-admin',
			WPCPM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPCPM_VERSION
		);

		wp_enqueue_script(
			'wpcpm-admin',
			WPCPM_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			WPCPM_VERSION,
			true
		);

		// The double-press guard, the dashboards' own script: it locks a form that carries
		// `data-wpcpm-once` at its first submit and shows the form's busy word, and leaves every
		// other form alone, so the list tables, their search and Screen Options work as before.
		// The call calendar registers the handle on `init`, which runs in wp-admin too.
		wp_enqueue_script( 'wpcpm-forms' );
	}

	/**
	 * The Overview, the screen the menu's top entry opens, for a program manager alone: the
	 * capability is checked here, as the Tools screen checks it, and `WPCPM_Overview` draws the rest.
	 */
	public function render_overview() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		WPCPM_Overview::render();
	}
}
