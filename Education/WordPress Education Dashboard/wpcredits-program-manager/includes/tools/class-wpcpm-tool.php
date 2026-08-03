<?php
/**
 * Base class for the plugin's tools.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A tool: a one-off job an administrator runs against program data.
 *
 * Tools are deliberately not modules. A module owns an audience, a role and the
 * content those people see; a tool owns an operation. Keeping them apart means
 * the four modules stay a stable description of the program while tools come and
 * go as the program needs them.
 */
abstract class WPCPM_Tool {

	/**
	 * Tool identifier, used in the admin page slug.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human-readable tool name.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * One-line description shown on the Tools screen.
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
	 * Whether the tool has everything it needs to run.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return WPCPM_Settings::is_connected();
	}

	/**
	 * A short status line for the Tools screen.
	 *
	 * @return string
	 */
	public function status_line() {
		return '';
	}

	/**
	 * Admin page slug.
	 *
	 * @return string
	 */
	public function page_slug() {
		return 'wpcpm-tool-' . $this->id();
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
	 * Render the tool's admin screen.
	 */
	abstract public function render_admin_page();
}
