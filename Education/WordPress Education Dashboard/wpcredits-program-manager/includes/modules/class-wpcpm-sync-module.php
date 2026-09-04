<?php
/**
 * The shape shared by the three modules that own an Airtable sync.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * A module with a sync: the Students, Mentors and Institutions modules.
 *
 * Each of the three used to carry its own copy of the same three handlers (start, cancel, the
 * AJAX tick), the same capability-then-nonce check, the same redirect, and a map of outcome
 * sentences that had drifted three ways: one screen said "Sync started" with a dash and one
 * without, one told the reader to "see the error below" and two did not, and the outcome
 * travelled by a query argument on two screens and by a per-user flash on the third. Hand
 * copies are how one fix lands in one place; this class is where the three now agree.
 *
 * **The outcome travels by flash, on every screen.** A status in the URL is bookmarkable and
 * shareable, and a bookmarked "Sync started" is a lie every time it is opened; the flash is
 * read once by the person who pressed the button and gone. The Institutions screen worked
 * that way already; the other two now do.
 *
 * A module says which sync it owns (`sync_class()`), which flash channel its screen reads
 * (`flash_key()`) and, where the sync's tick method has another name, how to tick it
 * (`run_sync_tick()`). Everything else here is the same for all three, on purpose.
 */
abstract class WPCPM_Sync_Module extends WPCPM_Module {

	/**
	 * The three `admin_post_` / `wp_ajax_` action names every module overrides.
	 *
	 * Declared here so the contract is written down and a reference to `static::ACTION_SYNC`
	 * resolves on this class; a module that forgot one would post to '' and fail its own
	 * suite's handler scan. PHP lets a subclass redeclare a class constant, which is the
	 * whole mechanism.
	 */
	const ACTION_SYNC   = '';
	const ACTION_CANCEL = '';
	const ACTION_TICK   = '';

	/**
	 * The sync class this module owns, as a class name.
	 *
	 * @return string
	 */
	abstract protected function sync_class();

	/**
	 * The flash channel this module's screen reads its outcomes from.
	 *
	 * @return string
	 */
	abstract protected function flash_key();

	/**
	 * Run one slice of the sync, inside the AJAX budget.
	 *
	 * The Students and Mentors syncs call it `run_tick()`; the Institutions sync calls it
	 * `tick()`, which is why this is a method and not a call.
	 *
	 * @param int $budget Seconds.
	 */
	protected function run_sync_tick( $budget ) {
		$sync = $this->sync_class();
		$sync::run_tick( $budget );
	}

	/**
	 * The AJAX tick behind the progress bar: run a slice if a run is on, answer with progress.
	 */
	public function handle_tick() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
		}

		check_ajax_referer( static::ACTION_TICK, 'nonce' );

		$sync = $this->sync_class();

		if ( $sync::is_running() ) {
			$this->run_sync_tick( $sync::BUDGET_AJAX );
		}

		wp_send_json_success( $sync::progress() );
	}

	/**
	 * Start a run.
	 */
	public function handle_sync() {
		$this->verify( static::ACTION_SYNC );

		$sync   = $this->sync_class();
		$result = $sync::start();

		$this->redirect_back( is_wp_error( $result ) ? 'error' : 'started' );
	}

	/**
	 * Cancel the run in progress.
	 */
	public function handle_cancel() {
		$this->verify( static::ACTION_CANCEL );

		$sync = $this->sync_class();
		$sync::cancel();

		$this->redirect_back( 'cancelled' );
	}

	/**
	 * The capability first, then the nonce, then nothing else: every admin-post handler on
	 * the three screens opens with this.
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
	 * Back to the module's screen, with the outcome flashed for the person who pressed.
	 *
	 * @param string $status An outcome key the screen's message map knows.
	 */
	protected function redirect_back( $status ) {
		WPCPM_Flash::set( $this->flash_key(), $status );
		wp_safe_redirect( $this->admin_url() );
		exit;
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
	 * The three outcomes every sync screen can flash, in one wording.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice type and sentence.
	 */
	public static function sync_messages() {
		return array(
			'started'   => array( 'success', __( 'Sync started. Progress is shown below and updates as it runs.', 'wpcredits-program-manager' ) ),
			'cancelled' => array( 'info', __( 'Sync canceled.', 'wpcredits-program-manager' ) ),
			'error'     => array( 'error', __( 'That action could not be completed. See the error below.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Print the notice for the outcome the last press flashed, if the map knows it.
	 *
	 * @param array $extra The screen's own outcomes, merged over the shared three.
	 */
	protected function render_status_notice( array $extra = array() ) {
		$status   = $this->taken_status();
		$messages = array_merge( self::sync_messages(), $extra );

		if ( '' === $status || ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $messages[ $status ][1] )
		);
	}
}
