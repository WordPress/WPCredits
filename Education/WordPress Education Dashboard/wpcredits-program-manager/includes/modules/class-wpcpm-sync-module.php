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
 * The Sponsors module extends this class too and syncs nothing: its sponsors are the site's own
 * records. It keeps the class for the screen's shared parts, the way back to the tab a press was
 * made on (`redirect_back()`) and the notice the press leaves there (`render_status_notice()`);
 * its `sync_class()` names no class, its `sync_messages()` holds none of the sync's outcomes, and
 * the sync handlers it inherits are hooked by nothing.
 *
 * Each of the three used to carry its own copy of the same three handlers (start, cancel, the
 * AJAX tick), the same capability-then-nonce check, the same redirect, and a map of outcome
 * sentences that had drifted three ways: one screen said "Sync started" with a dash and one
 * without, one told the reader to "see the error below" and two did not, and the outcome
 * traveled by a query argument on two screens and by a per-user flash on the third. Hand
 * copies are how one fix lands in one place; this class is where the three now agree.
 *
 * **The outcome travels by flash, on every screen.** A status in the URL is bookmarkable and
 * shareable, and a bookmarked "Sync started" is a lie every time it is opened; the flash is
 * read once by the person who pressed the button and gone. The Institutions screen worked
 * that way already; the other two now do.
 *
 * A module says which sync it owns (`sync_class()`), which flash channel its screen reads
 * (`flash_key()`) and, where the sync's tick method has another name, how to tick it
 * (`run_sync_tick()`). Everything else here is the same for all three, on purpose. The
 * capability-then-nonce check, the way back to a tab and the notice a press leaves are the module
 * base's (`WPCPM_Module`), which asks every module for its flash channel and serves a module
 * without a sync too.
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
	 * Run anyway (1.123.58): the action a module whose sync the count check can stop posts to go on
	 * past the stop. The Mentors and Students modules name theirs; the Institutions sync has its own
	 * empty-read guard and no Run anyway, so its module names none and hooks nothing.
	 */
	const ACTION_ACCEPT = '';

	/**
	 * The counts the `finished` flash carried, once it is taken (`take_finished()`), for its sentence.
	 *
	 * @var array{created: int, refreshed: int}
	 */
	private $finished = array(
		'created'   => 0,
		'refreshed' => 0,
	);

	/**
	 * The sync class this module owns, as a class name.
	 *
	 * @return string
	 */
	abstract protected function sync_class();

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
	 *
	 * A run the count check stopped is not on (`is_running()`), so no slice is run for it, and the
	 * progress answers `stopped` with both counts.
	 *
	 * The tick that finishes the run flashes `finished` (1.123.58), with the report's counts
	 * (`finished_counts()`), on the channel the Sync tab reads it from (`finished_channel()`), so the
	 * Sync tab the poll reloads says what the run did.
	 * Finished is a run that was on before the slice, is not on or stopped after it, and whose report
	 * was written since the slice began: a run canceled meanwhile leaves an older report.
	 */
	public function handle_tick() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
		}

		check_ajax_referer( static::ACTION_TICK, 'nonce' );

		$sync = $this->sync_class();

		if ( $sync::is_running() ) {
			$began = time();

			$this->run_sync_tick( $sync::BUDGET_AJAX );

			$report = get_option( $sync::OPT_REPORT );

			if ( ! $sync::is_running() && ! $this->sync_stopped() && is_array( $report ) && isset( $report['finished'] ) && (int) $report['finished'] >= $began ) {
				$stats = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();

				WPCPM_Flash::set( $this->finished_channel(), array_merge( array( 'status' => 'finished' ), $this->finished_counts( $stats ) ) );
			}
		}

		wp_send_json_success( $sync::progress() );
	}

	/**
	 * The flash channel the `finished` notice travels on (1.123.58): the screen's own, unless the
	 * screen's channel has readers elsewhere that take only a word from it, as the Institutions
	 * screen's has.
	 *
	 * @return string
	 */
	protected function finished_channel() {
		return $this->flash_key();
	}

	/**
	 * Whether the count check holds the module's run stopped: never for a sync that has no such check.
	 *
	 * @return bool
	 */
	private function sync_stopped() {
		$sync = $this->sync_class();

		return method_exists( $sync, 'is_stopped' ) && $sync::is_stopped();
	}

	/**
	 * What the `finished` notice counts, from the finished run's report: the accounts it created, and
	 * the accounts it refreshed, which are the ones it linked and the ones it updated.
	 *
	 * @param array $stats The report's statistics.
	 * @return array{created: int, refreshed?: int} A sync that refreshes no account answers `created` only.
	 */
	protected function finished_counts( array $stats ) {
		$count = static function ( $key ) use ( $stats ) {
			return isset( $stats[ $key ] ) ? (int) $stats[ $key ] : 0;
		};

		return array(
			'created'   => $count( 'created' ),
			'refreshed' => $count( 'linked' ) + $count( 'updated' ),
		);
	}

	/**
	 * Start a run. A run already going is not started over (1.123.58): the press comes back with
	 * `running`, above the progress of the run that is.
	 */
	public function handle_sync() {
		$this->verify( static::ACTION_SYNC );

		$sync   = $this->sync_class();
		$result = $sync::start();

		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'wpcpm_sync_running' === $result->get_error_code() ? 'running' : 'error' );
		}

		$this->redirect_back( 'started' );
	}

	/**
	 * Run anyway: let the run the count check stopped go on from where it stopped (1.123.58).
	 *
	 * The capability, then the nonce, then the state: a run the guard did not stop, or no run at all,
	 * has nothing to accept, and the press comes back saying so.
	 */
	public function handle_accept() {
		$this->verify( static::ACTION_ACCEPT );

		$sync = $this->sync_class();

		$this->redirect_back( $sync::accept() ? 'accepted' : 'not-stopped' );
	}

	/**
	 * The Run anyway form, printed by a Sync tab after the stop's notice while the run is stopped
	 * (1.123.58). It asks its question before it posts, since it takes the role from every linked
	 * account the run did not read, and it names the tab so the press comes back to it.
	 *
	 * @param string $question The question asked before the press is sent.
	 * @param string $tab      The tab the form is on.
	 */
	protected function render_run_anyway( $question, $tab ) {
		printf(
			'<form method="post" action="%1$s" data-wpcpm-confirm="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $question )
		);
		wp_nonce_field( static::ACTION_ACCEPT );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( static::ACTION_ACCEPT ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( $tab ) );
		printf( '<p><button type="submit" class="button">%s</button></p>', esc_html__( 'Run anyway', 'wpcredits-program-manager' ) );
		echo '</form>';
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
	 * Back to the module's screen, with the outcome flashed for the person who pressed: to the tab
	 * the press was made on, when the screen is drawn in tabs.
	 *
	 * The tab is the one the form names in its hidden field, and only one the screen has: a form
	 * that names none, or one the screen does not have, comes back to the screen's own address,
	 * where a screen drawn in tabs shows its first.
	 *
	 * The Administrator Dashboard posts the same decisions with a return field; the
	 * allowlist in `WPCPM_Return` decides, and a missing or foreign value is this screen.
	 *
	 * @param string $status An outcome key the screen's message map knows.
	 */
	protected function redirect_back( $status ) {
		WPCPM_Flash::set( $this->flash_key(), $status );

		$url = $this->tab_url( WPCPM_Request::posted_key( self::TAB_FIELD ) );

		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $url ) : $url );
		exit;
	}

	/**
	 * The outcomes every sync screen can flash, in one wording: the three every sync has, `running`
	 * and `finished` (1.123.58), and Run anyway's two (1.123.58), which only a screen whose sync the
	 * count check can stop flashes. `finished` carries its two counts on the flash, and is worded
	 * from them where it is printed (`notice_sentence()`).
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice type and sentence.
	 */
	public static function sync_messages() {
		return array(
			'started'     => array( 'success', __( 'Sync started. Progress is shown below and updates as it runs.', 'wpcredits-program-manager' ) ),
			'running'     => array( 'info', __( 'A sync is already running. Its progress is shown below.', 'wpcredits-program-manager' ) ),
			/* translators: 1: how many accounts the sync created, 2: how many it refreshed. */
			'finished'    => array( 'success', __( 'Sync finished. Accounts created: %1$s. Accounts refreshed: %2$s.', 'wpcredits-program-manager' ) ),
			'cancelled'   => array( 'info', __( 'Sync canceled.', 'wpcredits-program-manager' ) ),
			'error'       => array( 'error', __( 'That action could not be completed. See the error below.', 'wpcredits-program-manager' ) ),
			'accepted'    => array( 'success', __( 'The sync goes on from where it stopped, with the counts it read.', 'wpcredits-program-manager' ) ),
			'not-stopped' => array( 'info', __( 'That sync was not stopped by its count check, so there was nothing to run anyway.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The words for `error` on a tab that prints no error below the notice, in one wording.
	 *
	 * The sync's own words (`sync_messages()`) point to the last sync's error, which a screen drawn in
	 * tabs prints on the tab that runs the sync alone; on its other tabs the sentence would point
	 * nowhere, so this one sends the reader to the screen again. The Institutions screen prints it on
	 * every tab but Sync and storage and Accounts; the Sponsors screen, which runs no sync and prints
	 * no error below the notice on any tab, on every tab but Accounts. On both, Accounts words `error`
	 * for the invitation.
	 *
	 * @return array{0: string, 1: string} Notice type and sentence.
	 */
	public static function failed_message() {
		return array( 'error', __( 'That action could not be completed. Reload the screen and try again.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The outcome the last press left, taken (so it shows once), or '': the `finished` a tick left is
	 * an array that carries its counts (1.123.58), whose status is read here (`take_finished()`).
	 *
	 * @return string
	 */
	protected function taken_status() {
		$value = WPCPM_Flash::take( $this->flash_key() );

		if ( is_array( $value ) ) {
			return $this->take_finished( $value );
		}

		return sanitize_key( (string) $value );
	}

	/**
	 * Read a `finished` flash: its counts are kept for `notice_sentence()`, and its status answered.
	 *
	 * @param mixed $value What a flash channel held.
	 * @return string 'finished', or '' for anything else.
	 */
	protected function take_finished( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['status'] ) || 'finished' !== $value['status'] ) {
			return '';
		}

		$this->finished = array(
			'created'   => isset( $value['created'] ) ? (int) $value['created'] : 0,
			'refreshed' => isset( $value['refreshed'] ) ? (int) $value['refreshed'] : 0,
		);

		return 'finished';
	}

	/**
	 * The sentence one status prints: `finished` worded from the counts its flash carried (1.123.58),
	 * every other status as the base words it.
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from the map.
	 * @return string
	 */
	protected function notice_sentence( $status, $sentence ) {
		if ( 'finished' !== (string) $status ) {
			return parent::notice_sentence( $status, $sentence );
		}

		return sprintf(
			(string) $sentence,
			number_format_i18n( $this->finished['created'] ),
			number_format_i18n( $this->finished['refreshed'] )
		);
	}

	/**
	 * Print the notice for the outcome the last press flashed, if the map knows it.
	 *
	 * Here, beside the sync's three outcomes it starts from (`sync_messages()`), rather than on the
	 * module base with the notice it prints through (`render_notice_from()`).
	 *
	 * @param array $extra The screen's own outcomes, merged over the shared three.
	 */
	protected function render_status_notice( array $extra = array() ) {
		$this->render_notice_from( array_merge( static::sync_messages(), $extra ) );
	}
}
