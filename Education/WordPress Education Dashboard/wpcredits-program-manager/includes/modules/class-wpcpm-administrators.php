<?php
/**
 * Administrators module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 4 - Administrators.
 *
 * Program managers use the built-in Administrator role rather than a custom one,
 * so this module adds no role: it grants the program capabilities to
 * Administrator and reports who holds them.
 *
 * Its screen is the accounts screen every audience's module shares (`WPCPM_Accounts_Screen`), in
 * two tabs: Accounts, every administrator in a WordPress list table, as the other audiences'
 * screens list theirs, and Capabilities, the program capabilities the role is granted. What is the
 * administrators' own is here: the words, the table, the button to the Administrator Dashboard
 * above the tabs, and the Capabilities tab. Administrators are WordPress's own accounts, never
 * invited by the plugin and never synced from Airtable, so the screen has no invitations and no
 * Sync tab.
 */
class WPCPM_Administrators extends WPCPM_Module {

	use WPCPM_Accounts_Screen;

	/**
	 * The action one row's invitation is sent under, whose nonce the shared screen's `handle_invite()`
	 * checks. Named for that reference alone and never hooked: administrators are never invited by
	 * the plugin, so no `admin_post_` action reaches the handler here.
	 */
	const ACTION_INVITE = 'wpcpm_administrators_invite';

	/** The screen's tab the accounts are on, which every link and form of the list returns to. */
	const TAB_ACCOUNTS = 'accounts';

	/** The screen's tab the program capabilities the Administrator role is granted are listed on. */
	const TAB_CAPABILITIES = 'capabilities';

	/**
	 * The screen's tabs, slug => label, in the bar's order: the accounts, the tab the screen opens
	 * on, then the capabilities. English, as a constant has to hold them; `tab_labels()` translates
	 * them.
	 */
	const TABS = array(
		self::TAB_ACCOUNTS     => 'Accounts',
		self::TAB_CAPABILITIES => 'Capabilities',
	);

	/**
	 * The user option the rows-per-page choice is kept in: the Administrator accounts table's own
	 * name, here too, because the save is hooked at boot, long before that class is loaded.
	 */
	const PER_PAGE_OPTION = 'wpcpm_administrators_per_page';

	/**
	 * The flash channel the shared screen carries a press on the ticked accounts' count on, beside
	 * the outcome (`leave()`, `notice_sentence()`). Named for this screen, whose list offers no press
	 * on ticked accounts, so nothing is ever carried on it.
	 */
	const FLASH_DETAIL = 'administrators_admin_detail';

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'administrators';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Administrators', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_ADMIN;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Program managers use the built-in WordPress Administrator role. They can read every access level and run every sync.', 'wpcredits-program-manager' );
	}

	/**
	 * This module needs no provisioning, so its screen is informational rather
	 * than a placeholder for missing work.
	 *
	 * @return bool
	 */
	public function is_implemented() {
		return true;
	}

	/**
	 * Boot the module's front end, the Administrator Dashboard, and its screen.
	 *
	 * No `admin_post_` handler: the screen's one form is its list's, a GET form to the screen itself,
	 * and administrators are never invited by the plugin.
	 */
	public function boot() {
		WPCPM_Administrators_Dashboard::init();

		// The screen's own load hook, once the menu exists, and its rows-per-page save.
		$this->boot_screen();
	}

	/**
	 * Activation: the page exists and is gated before anybody can reach it.
	 */
	public function activate() {
		WPCPM_Administrators_Dashboard::ensure_page();
	}

	/**
	 * Uninstall: the page's two options, and every manager's rows-per-page choice for the list. The
	 * page itself is content and stays, as the other dashboards' pages do.
	 */
	public function uninstall() {
		delete_option( WPCPM_Administrators_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED );

		// A manager's rows-per-page choice for the Administrator accounts, which core keeps as user meta.
		delete_metadata( 'user', 0, self::PER_PAGE_OPTION, '', true );
	}

	/**
	 * The Administrators screen's flash channel, which the module base asks every module to name.
	 *
	 * Nothing on this screen leaves an outcome on it, and the screen prints none
	 * (`render_tab_accounts()`): the shared screen writes to it only on the way back from an
	 * invitation, which administrators are never sent.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return 'administrators_admin';
	}

	/**
	 * No sync: administrators are WordPress's own accounts, never read from Airtable.
	 *
	 * The shared screen asks a module for its sync only in its one row's invitation handler
	 * (`handle_invite()`), and this screen hooks no invitation handler: no `admin_post_` action is
	 * added for it, since administrators are never invited by the plugin, so nothing ever asks here.
	 *
	 * @return string
	 */
	protected function sync_class() {
		return '';
	}

	/**
	 * The Administrator accounts table: the list the screen draws, and whose rule the rows-per-page
	 * save keeps.
	 *
	 * @return string
	 */
	protected static function table_class() {
		return 'WPCPM_Administrators_Table';
	}

	/**
	 * The words the screen prints for administrators, in the places every accounts screen prints its
	 * audience's: the keys `WPCPM_Accounts_Screen::screen_words()` lists.
	 *
	 * @return array<string, string>
	 */
	protected function screen_words() {
		return array(
			'heading_views'      => __( 'Filter administrator accounts list', 'wpcredits-program-manager' ),
			'heading_pagination' => __( 'Administrator accounts list navigation', 'wpcredits-program-manager' ),
			'heading_list'       => __( 'Administrator accounts list', 'wpcredits-program-manager' ),
			// Never printed: the warning is for accounts that arrive by the Airtable sync, and
			// administrators do not, so the Accounts tab here never warns (`render_tab_accounts()`).
			'not_connected'      => '',
			'list_heading'       => __( 'Administrator accounts', 'wpcredits-program-manager' ),
			'page_label'         => __( 'Administrator Dashboard:', 'wpcredits-program-manager' ),
			'page_missing'       => __( 'The Administrator Dashboard page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ),
			'search'             => __( 'Search administrators', 'wpcredits-program-manager' ),
			'list_note'          => __( 'Program managers use the built-in WordPress Administrator role and are never invited by the plugin: their accounts are added and managed on the Users screen.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The Administrator Dashboard's address, which the button above the tabs opens and the list card
	 * names, or '' while the page is missing.
	 *
	 * @return string
	 */
	protected function dashboard_url() {
		return WPCPM_Administrators_Dashboard::page_url();
	}

	/**
	 * Where the list stands, as the request says: its search, sort and page, each encoded, the empty
	 * ones left out. No view: the list has one.
	 *
	 * What the list's form comes back to when it is sent (`list_url()`). Rebuilt from what the list
	 * reads rather than copied from the address, so the form's own fields (its nonce) are never
	 * carried. Each value is encoded, because `add_query_arg()` sets a value as it is given.
	 *
	 * @return array<string, string>
	 */
	public static function list_state() {
		return array_map(
			static function ( $value ) {
				return rawurlencode( (string) $value );
			},
			array_filter(
				array(
					's'       => WPCPM_Request::text( 's' ),
					'orderby' => WPCPM_Request::key( 'orderby' ),
					'order'   => WPCPM_Request::key( 'order' ),
					'paged'   => WPCPM_Request::id( 'paged' ),
				)
			)
		);
	}

	/**
	 * The tabs as the bar prints them: TABS, each label translated.
	 *
	 * Each is written out here, because the translation tools collect a string only where it is
	 * written as one. The shared screen's own writes out Accounts and Sync; this screen's second tab
	 * is Capabilities.
	 *
	 * @return array<string, string> Slug => label.
	 */
	private static function tab_labels() {
		$translated = array(
			self::TAB_ACCOUNTS     => __( 'Accounts', 'wpcredits-program-manager' ),
			self::TAB_CAPABILITIES => __( 'Capabilities', 'wpcredits-program-manager' ),
		);

		return array_merge( self::TABS, array_intersect_key( $translated, self::TABS ) );
	}

	/**
	 * Render the screen: the button to the Administrator Dashboard, the tab bar, then the tab shown.
	 *
	 * The button is the screen's primary action whichever tab is shown, so it stands above the bar.
	 * Accounts holds the list, Capabilities the program capabilities the role is granted.
	 */
	public function render_admin_page() {
		$tab       = self::tab();
		$dashboard = $this->dashboard_url();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		if ( '' !== $dashboard ) {
			printf(
				'<p><a class="button button-primary" href="%1$s">%2$s</a> %3$s</p>',
				esc_url( $dashboard ),
				esc_html__( 'Open the Administrator Dashboard', 'wpcredits-program-manager' ),
				esc_html__( 'Every queue on one page, with its decisions.', 'wpcredits-program-manager' )
			);
		}

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tab_labels(), $tab );

		if ( self::TAB_CAPABILITIES === $tab ) {
			$this->render_tab_capabilities();
		} else {
			$this->render_tab_accounts();
		}

		echo '</div>';
	}

	/**
	 * The Accounts tab: the administrator accounts list alone.
	 *
	 * Without what the other audiences' Accounts tabs print above their lists: no press on this
	 * screen flashes an outcome, administrators are never invited by the plugin, and they do not
	 * arrive by a sync, so there are no notices, no invitations card and no warning while Airtable
	 * is not connected.
	 */
	private function render_tab_accounts() {
		$this->render_accounts_list();
	}

	/**
	 * The Capabilities tab: the program capabilities the Administrator role is granted, as the screen
	 * has always listed them.
	 */
	private function render_tab_capabilities() {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Program capabilities', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p>' . esc_html__( 'Granted to the Administrator role on activation:', 'wpcredits-program-manager' ) . '</p>';
		echo '<ul class="wpcpm-caps">';
		foreach ( WPCPM_Roles::administrator_caps() as $cap ) {
			echo '<li><code>' . esc_html( $cap ) . '</code></li>';
		}
		echo '</ul>';
		echo '</div>';
	}
}
