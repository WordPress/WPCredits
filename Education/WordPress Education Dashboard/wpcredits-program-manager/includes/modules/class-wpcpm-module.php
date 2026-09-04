<?php
/**
 * Base class for the four program modules.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A program module: one audience, one role, one admin screen.
 *
 * Subclasses override boot() to add their hooks and render_admin_page() to draw
 * their screen. Modules must not depend on each other.
 */
abstract class WPCPM_Module {

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
	 * Whether the module has working functionality yet.
	 *
	 * The Mentors module is built; the other three currently register their role
	 * and reserve their screen.
	 *
	 * @return bool
	 */
	public function is_implemented() {
		return false;
	}

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
	 * How many users currently hold this module's role.
	 *
	 * @return int
	 */
	public function user_count() {
		$query = new WP_User_Query(
			array(
				'role'        => $this->role(),
				'number'      => 1,
				'count_total' => true,
				'fields'      => 'ID',
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Render the module's admin screen.
	 */
	public function render_admin_page() {
		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';
		$this->render_placeholder();
		echo '</div>';
	}

	/**
	 * Shared "reserved for later" panel for modules that are not built yet.
	 */
	protected function render_placeholder() {
		$role   = get_role( $this->role() );
		$exists = $role instanceof WP_Role;

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Status', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p>' . esc_html__( 'The user role for this module is registered and ready. The module\'s own functionality has not been built yet.', 'wpcredits-program-manager' ) . '</p>';
		echo '<table class="wpcpm-table"><tbody>';

		printf(
			'<tr><th scope="row">%1$s</th><td><code>%2$s</code> %3$s</td></tr>',
			esc_html__( 'Role slug', 'wpcredits-program-manager' ),
			esc_html( $this->role() ),
			$exists ? esc_html__( '(registered)', 'wpcredits-program-manager' ) : esc_html__( '(missing - re-activate the plugin)', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Accounts with this role', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( $this->user_count() ) )
		);

		echo '</tbody></table>';
		echo '</div>';
	}
}
