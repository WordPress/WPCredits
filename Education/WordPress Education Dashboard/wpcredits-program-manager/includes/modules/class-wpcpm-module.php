<?php
/**
 * Base class for the five program modules.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A program module: one audience, one role, one admin screen.
 *
 * Subclasses override boot() to add their hooks, and each draws its own screen in
 * render_admin_page(). Modules must not depend on each other.
 *
 * What a module's screen needs to answer a press is here too, for every module, with a sync
 * (`WPCPM_Sync_Module`) or without one: the capability-then-nonce check (`verify()`), the way
 * back to one of its tabs (`tab_url()`, from its `TABS`), and the notice for the outcome a press
 * flashed on the module's own channel (`flash_key()`, `render_notice_from()`).
 */
abstract class WPCPM_Module {

	/**
	 * The screen's tabs, slug => label, in the order its bar draws them, the first the one the
	 * screen opens on: none for a screen drawn in one piece.
	 *
	 * Declared here, so the way back to a tab (`tab_url()`) reads the tabs of whichever module is
	 * pressed; a module drawn in tabs redeclares it. The labels are English, as a constant has to hold
	 * them, and the module translates them where its bar prints them.
	 */
	const TABS = array();

	/**
	 * The hidden field a form on a tab names its tab in, so the press comes back to that tab: the
	 * name the Settings screen's forms and the accounts lists' row links carry their tab in too.
	 */
	const TAB_FIELD = 'wpcpm_tab';

	/**
	 * Module identifier, used in the admin page slug.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human-readable module name.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * The submenu title, which may carry markup the plain label cannot.
	 *
	 * `label()` is also the screen's `<h1>` and is escaped there, so a module that wants a
	 * pending-count bubble in the menu (the Institutions review queue) overrides this and
	 * leaves `label()` alone. Whatever this returns is printed by `add_submenu_page()` as
	 * core prints its own "Comments" bubble: the module escapes the text itself.
	 *
	 * @return string
	 */
	public function menu_label() {
		return $this->label();
	}

	/**
	 * The user role this module manages.
	 *
	 * @return string
	 */
	abstract public function role();

	/**
	 * One-line description shown on the module screen.
	 *
	 * @return string
	 */
	abstract public function description();

	/**
	 * Register hooks. Called on `plugins_loaded`.
	 */
	public function boot() {}

	/**
	 * One-time setup on plugin activation.
	 */
	public function activate() {}

	/**
	 * Tear down scheduled work on plugin deactivation.
	 */
	public function deactivate() {}

	/**
	 * Data removal on uninstall.
	 */
	public function uninstall() {}

	/**
	 * Admin page slug for this module.
	 *
	 * @return string
	 */
	public function page_slug() {
		return 'wpcpm-' . $this->id();
	}

	/**
	 * Admin page URL.
	 *
	 * @return string
	 */
	public function admin_url() {
		return admin_url( 'admin.php?page=' . $this->page_slug() );
	}

	/**
	 * The flash channel this module's screen reads its outcomes from.
	 *
	 * @return string
	 */
	abstract protected function flash_key();

	/**
	 * The capability first, then the nonce, then nothing else: what a press on a module's screen is
	 * checked with before anything of it is read.
	 *
	 * @param string $action The nonce action.
	 */
	protected function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * The module's screen at one of its tabs, or the screen's own address for a tab it does not have.
	 *
	 * @param string $tab A key of TABS.
	 * @return string
	 */
	protected function tab_url( $tab ) {
		$tab = (string) $tab;

		return isset( static::TABS[ $tab ] ) ? add_query_arg( 'tab', $tab, $this->admin_url() ) : $this->admin_url();
	}

	/**
	 * The outcome the last press left, taken (so it shows once), or ''.
	 *
	 * @return string
	 */
	protected function taken_status() {
		return sanitize_key( (string) WPCPM_Flash::take( $this->flash_key() ) );
	}

	/**
	 * Print the notice for the outcome the last press flashed, from this map of outcomes alone.
	 *
	 * For a screen drawn in tabs, which gives each tab the outcomes of the presses made on it: a
	 * press comes back to the tab it was made on, so its notice prints there. The outcome is taken
	 * whether or not the map knows it, so one that came back to the other tab cannot wait to surface
	 * on a later page, under a press that did not leave it.
	 *
	 * @param array $messages Status => notice type and sentence.
	 */
	protected function render_notice_from( array $messages ) {
		$status = $this->taken_status();

		if ( '' === $status || ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $this->notice_sentence( $status, (string) $messages[ $status ][1] ) )
		);
	}

	/**
	 * The sentence one status prints, for a screen whose wording depends on what a press left.
	 *
	 * A seam, and the S5 review is why there is one: a message map is built for every status at
	 * once and by anything that wants a sentence out of it, so a screen that has to read a
	 * one-shot value cannot read it there without the first passer-by consuming it. This runs
	 * once, on the one status being printed. The default is the map's own sentence.
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from the map.
	 * @return string
	 */
	protected function notice_sentence( $status, $sentence ) {
		return (string) $sentence;
	}

	/**
	 * Draw the module's admin screen.
	 *
	 * Every module draws its own, so there is none here to fall back to: the Students and Mentors
	 * modules take theirs from the accounts screen they share (`WPCPM_Accounts_Screen`), and the
	 * Institutions, Sponsors and Administrators modules each write their own.
	 */
	abstract public function render_admin_page();
}
