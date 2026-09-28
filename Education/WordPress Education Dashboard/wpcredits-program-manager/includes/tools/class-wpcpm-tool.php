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
 * A tool: a job a program manager runs against program data, on a screen of its own.
 *
 * Tools are deliberately not audiences. An audience owns a role and the content the people holding
 * it see; a tool owns an operation. Keeping them apart means the audiences stay a stable
 * description of the program while tools come and go as the program needs them. A tool that keeps
 * settings of its own draws them on its screen, in a Settings section at the top
 * (`render_settings()`), rather than on the Settings screen.
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
	 * A short status line for the Tools screen and the Overview; for a tool that cannot run, why not,
	 * which both screens print as the card's warning.
	 *
	 * The reason here goes with the readiness here, the Airtable connection. A tool that is ready by
	 * another test says, in its own status line, what that test found missing.
	 *
	 * @return string '' for a ready tool with nothing to report.
	 */
	public function status_line() {
		return $this->is_ready() ? '' : __( 'Airtable is not connected yet, so this tool cannot run.', 'wpcredits-program-manager' );
	}

	/**
	 * The settings the tool keeps and draws in the Settings section of its own screen: the keys of its
	 * scope, `tool:` and its ID, in `WPCPM_Settings::scopes()`, which is what a Save of the section
	 * writes (bin/test-settings.php holds the two lists, and the form, together).
	 *
	 * @return string[] None by default, and a tool that keeps none draws no Settings section.
	 */
	public function settings_keys() {
		return array();
	}

	/**
	 * The scope the tool's settings are saved under, which its Settings section posts.
	 *
	 * @return string A key of `WPCPM_Settings::scopes()` for a tool that keeps settings.
	 */
	public function settings_scope() {
		return 'tool:' . $this->id();
	}

	/**
	 * The Settings section at the top of the tool's screen: the notice its last Save left, its
	 * heading, what `settings_intro()` says, and one form holding the tool's settings and a Save.
	 *
	 * The form opens as every settings form does (`WPCPM_Settings_Screen::open_settings_form()`),
	 * posting to this screen's own address and naming the tool's scope, and the handler every
	 * settings form posts to (`WPCPM_Settings_Screen::handle_settings_save()`) writes those settings
	 * and no other and sends the manager back here. The Save's ID is the tool's own, since a tool's
	 * screen has buttons of its own. Nothing is drawn for a tool that keeps no settings.
	 *
	 * The intro is a plain description paragraph, as the first line of the cards beside it is: the
	 * Settings screen's intros sit closer under their headings, but those headings stand on no card,
	 * and here the card's heading has its own rule and padding.
	 */
	public function render_settings() {
		if ( array() === $this->settings_keys() ) {
			return;
		}

		WPCPM_Settings_Screen::render_outcome_notice();

		$intro   = $this->settings_intro();
		$heading = __( 'Settings', 'wpcredits-program-manager' );

		echo '<div class="wpcpm-card">';
		printf( '<h2 id="settings">%s</h2>', esc_html( $heading ) );

		if ( '' !== $intro['sentence'] ) {
			printf( '<p class="description">%s</p>', esc_html( $intro['sentence'] ) );
			WPCPM_Settings_Rows::details_after( $intro['details'], $heading );
		}

		WPCPM_Settings_Screen::open_settings_form( $this->settings_scope() );
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->render_settings_rows();

		echo '</tbody></table>';
		submit_button( __( 'Save settings', 'wpcredits-program-manager' ), 'primary', 'submit', true, array( 'id' => 'wpcpm-save-' . $this->id() ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * What the tool's Settings section says under its heading, when it says anything: one sentence,
	 * and the rest in a Details fold under it.
	 *
	 * @return array `sentence`, and `details`, a string or a paragraph a string; '' for none.
	 */
	protected function settings_intro() {
		return array(
			'sentence' => '',
			'details'  => '',
		);
	}

	/**
	 * The rows of the tool's Settings section, one per setting, each drawn by `WPCPM_Settings_Rows`
	 * or in its markup, with the value stored (`WPCPM_Settings::get()`), and posting under its
	 * setting's key.
	 */
	protected function render_settings_rows() {}

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
