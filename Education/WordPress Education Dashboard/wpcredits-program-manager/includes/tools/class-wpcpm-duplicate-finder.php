<?php
/**
 * Student Duplicate Finder.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The tool: the scan's controls, the list, the confirmation, and the delete.
 *
 * A tool and not a module (spec decision 3.1): it owns an operation, not an audience, and on
 * screen it sits under Modules with the other tools. Every screen and every handler is behind
 * `WPCPM_Roles::CAP_MANAGE`.
 *
 * The flow is two deliberate steps by one manager (the owner's answer of 11 September 2026):
 * tick rows, press Review selection, read the confirmation, press Delete. The confirmation is a
 * page and not a change, so it is posted to this screen; the delete is a change, so it goes to
 * `admin-post.php`, under a nonce tied to exactly the rows the confirmation listed.
 *
 * **The stored report is the menu, not the authority** (decision 3.6). The delete handler reads
 * the selected rows and their siblings from Airtable again, asks the database again what the site
 * points at, and asks `WPCPM_Duplicate_Rules::recheck()` again, before it keeps a sealed copy of
 * each row (decision 3.7) and deletes, children first (decision 3.8).
 *
 * The three sync handlers are copied from `WPCPM_Sync_Module`, in its order (the capability, then
 * the nonce), because that class serves modules and this is a tool (decision 3.9).
 */
class WPCPM_Duplicate_Finder extends WPCPM_Tool {

	const ACTION_SCAN   = 'wpcpm_duplicates_scan_now';
	const ACTION_CANCEL = 'wpcpm_duplicates_cancel';
	const ACTION_TICK   = 'wpcpm_duplicates_progress';
	const ACTION_REVIEW = 'wpcpm_duplicates_review';
	const ACTION_DELETE = 'wpcpm_duplicates_delete';
	const ACTION_VIEW   = 'wpcpm_duplicates_view';

	/** Flash channel for this screen's outcomes. */
	const FLASH = 'duplicates';

	/** A student in a posted form: `WPCPM_Duplicate_Rules::key()`'s sixteen hex characters. */
	const KEY_PATTERN = '/^[0-9a-f]{16}$/D';

	/** A row in a posted form: its table, a colon, its record ID. */
	const PAIR_PATTERN = '/^(students|reports|feedback):rec[A-Za-z0-9]{14}$/D';

	/**
	 * Tool identifier.
	 *
	 * @return string
	 */
	public function id() {
		return 'duplicate-finder';
	}

	/**
	 * Human-readable tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Student Duplicate Finder', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Modules screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Finds students who have more than one row in Students, Students Reports or Feedback, and deletes the older rows a program manager selects and confirms.', 'wpcredits-program-manager' );
	}

	/**
	 * The last scan, in a line.
	 *
	 * @return string
	 */
	public function status_line() {
		$report = WPCPM_Duplicates_Scan::report();

		if ( ! $report ) {
			return __( 'No scan has run yet.', 'wpcredits-program-manager' );
		}

		$count = (int) $report['counts']['addresses'];

		return sprintf(
			/* translators: 1: number of duplicated students, 2: date and time of the scan. */
			_n( '%1$s duplicated student, read %2$s.', '%1$s duplicated students, read %2$s.', $count, 'wpcredits-program-manager' ),
			number_format_i18n( $count ),
			wp_date( 'Y-m-d H:i', (int) $report['read'] )
		);
	}

	/**
	 * Hooks. The schedules are put on the clock here and not only at activation, because sites
	 * update by dropping in files and never re-activate.
	 */
	public function boot() {
		WPCPM_Duplicates_Scan::register_cron();

		add_action( 'init', array( 'WPCPM_Duplicate_Vault', 'register' ) );
		add_action( 'init', array( 'WPCPM_Duplicate_Vault', 'schedule' ), 20 );
		add_action( WPCPM_Duplicate_Vault::CRON_PURGE, array( 'WPCPM_Duplicate_Vault', 'purge' ), 10, 0 );

		add_action( 'admin_post_' . self::ACTION_SCAN, array( $this, 'handle_scan' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Activation: the scan's schedule.
	 */
	public function activate() {
		WPCPM_Duplicates_Scan::activate();
	}

	/**
	 * Deactivation: both schedules off the clock. The report and the copies stay.
	 */
	public function deactivate() {
		WPCPM_Duplicates_Scan::deactivate();
		WPCPM_Duplicate_Vault::deactivate();
	}

	/**
	 * Uninstall: the report, the schedules, and every copy with its log entry.
	 */
	public function uninstall() {
		WPCPM_Duplicates_Scan::uninstall();
		WPCPM_Duplicate_Vault::delete_all();
	}

	/**
	 * The screen's own stylesheet and script, on this screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, $this->page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'wpcpm-duplicates', WPCPM_PLUGIN_URL . 'assets/css/duplicate-finder.css', array( 'wpcpm-admin' ), WPCPM_VERSION );
		wp_enqueue_script( 'wpcpm-duplicates', WPCPM_PLUGIN_URL . 'assets/js/duplicate-finder.js', array(), WPCPM_VERSION, true );
	}

	/**
	 * Start a scan now.
	 */
	public function handle_scan() {
		$this->verify( self::ACTION_SCAN );

		// A second start() would drop the tick lock the running scan holds, leaving two ticks
		// writing one state.
		if ( WPCPM_Duplicates_Scan::is_running() ) {
			$this->redirect_back( array( 'status' => 'running' ) );
		}

		$result = WPCPM_Duplicates_Scan::start();

		$this->redirect_back( array( 'status' => is_wp_error( $result ) ? 'error' : 'started' ) );
	}

	/**
	 * Cancel the scan in progress. The last good list stays.
	 */
	public function handle_cancel() {
		$this->verify( self::ACTION_CANCEL );

		WPCPM_Duplicates_Scan::cancel();

		$this->redirect_back( array( 'status' => 'cancelled' ) );
	}

	/**
	 * The AJAX tick behind the progress bar: run a slice if a scan is on, answer with progress.
	 */
	public function handle_tick() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
		}

		check_ajax_referer( self::ACTION_TICK, 'nonce' );

		if ( WPCPM_Duplicates_Scan::is_running() ) {
			WPCPM_Duplicates_Scan::run_tick( WPCPM_Duplicates_Scan::BUDGET_AJAX );
		}

		wp_send_json_success( WPCPM_Duplicates_Scan::progress() );
	}

	/**
	 * Delete what the confirmation listed, after asking every question again (spec 7.3).
	 */
	public function handle_delete() {
		// The capability first, before the selection is even read (spec 7.3 step 1).
		$this->require_manager();

		$keys  = WPCPM_Request::posted_list( 'wpcpm_students', self::KEY_PATTERN );
		$pairs = WPCPM_Request::posted_list( 'wpcpm_rows', self::PAIR_PATTERN );

		// The nonce is tied to the rows the confirmation listed, worked out again from the stored
		// report, so a token taken from one confirmation cannot delete a different set, even when a
		// scan rewrote the report between the two presses (spec 7.2).
		$rows = WPCPM_Duplicate_Rules::expand( WPCPM_Duplicates_Scan::report(), $keys, $pairs )['rows'];

		check_admin_referer( self::ACTION_DELETE . '_' . self::rows_hash( $rows ) );

		$this->redirect_back( WPCPM_Duplicate_Delete::run( $keys, $pairs, get_current_user_id() ) );
	}

	/**
	 * The screen: a copy, the confirmation, or the list.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$copy = WPCPM_Request::id( 'wpcpm_copy' );

		if ( $copy > 0 ) {
			$this->render_copy_view( $copy );
			return;
		}

		if ( '' !== WPCPM_Request::posted_key( 'wpcpm_review' ) ) {
			$this->render_confirm_view();
			return;
		}

		WPCPM_Duplicate_Finder_Screen::render_list(
			array(
				'report'   => WPCPM_Duplicates_Scan::report(),
				'progress' => WPCPM_Duplicates_Scan::progress(),
				'last'     => WPCPM_Duplicates_Scan::last_read(),
				'next'     => (int) wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_SCAN ),
				'enabled'  => ! empty( WPCPM_Settings::get()['duplicate_delete_enabled'] ),
				'log'      => WPCPM_Duplicate_Vault::entries( 50 ),
				'flash'    => WPCPM_Flash::take( self::FLASH ),
				'url'      => $this->admin_url(),
				'checked'  => $this->posted_back(),
			)
		);
	}

	/**
	 * The key a confirmation's nonce is tied to: the rows it lists, as sorted table:record pairs.
	 *
	 * @param array $rows `WPCPM_Duplicate_Rules::expand()`'s rows.
	 * @return string Sixteen hex characters.
	 */
	public static function rows_hash( array $rows ) {
		$pairs = array();

		foreach ( $rows as $row ) {
			$pairs[] = $row['table'] . ':' . $row['id'];
		}

		sort( $pairs );

		return substr( md5( implode( ',', $pairs ) ), 0, 16 );
	}

	/**
	 * The confirmation: what a press of Delete would do, for the manager to read.
	 */
	private function render_confirm_view() {
		check_admin_referer( self::ACTION_REVIEW );

		$keys   = WPCPM_Request::posted_list( 'wpcpm_students', self::KEY_PATTERN );
		$pairs  = WPCPM_Request::posted_list( 'wpcpm_rows', self::PAIR_PATTERN );
		$report = WPCPM_Duplicates_Scan::report();
		$chosen = WPCPM_Duplicate_Rules::expand( $report, $keys, $pairs );

		WPCPM_Duplicate_Finder_Screen::render_confirm(
			array(
				'report'  => $report,
				'chosen'  => $chosen,
				'keys'    => $keys,
				'pairs'   => $pairs,
				'nonce'   => self::ACTION_DELETE . '_' . self::rows_hash( $chosen['rows'] ),
				'enabled' => ! empty( WPCPM_Settings::get()['duplicate_delete_enabled'] ),
				'running' => WPCPM_Duplicates_Scan::is_running(),
				'url'     => $this->admin_url(),
			)
		);
	}

	/**
	 * One copy, unsealed, behind a nonce tied to it.
	 *
	 * @param int $copy The copy's post ID.
	 */
	private function render_copy_view( $copy ) {
		check_admin_referer( self::ACTION_VIEW . '_' . (int) $copy );

		WPCPM_Duplicate_Finder_Screen::render_copy( (int) $copy, WPCPM_Duplicate_Vault::view( (int) $copy ), $this->admin_url() );
	}

	/**
	 * The ticks Back to the list carries, so the manager returns to the selection they reviewed.
	 *
	 * @return array `keys` and `pairs`, or nothing when the list was not reached from the confirmation.
	 */
	private function posted_back() {
		if ( '' === WPCPM_Request::posted_key( 'wpcpm_back' ) ) {
			return array();
		}

		check_admin_referer( self::ACTION_REVIEW );

		return array(
			'keys'  => WPCPM_Request::posted_list( 'wpcpm_students', self::KEY_PATTERN ),
			'pairs' => WPCPM_Request::posted_list( 'wpcpm_rows', self::PAIR_PATTERN ),
		);
	}

	/**
	 * The capability first, then the nonce, then nothing else.
	 *
	 * @param string $action The nonce action.
	 */
	private function verify( $action ) {
		$this->require_manager();

		check_admin_referer( $action );
	}

	/**
	 * Refuse anybody who does not manage the program.
	 */
	private function require_manager() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}
	}

	/**
	 * Back to the screen, with what happened flashed for the person who pressed.
	 *
	 * @param array $outcome `status`, and what the notice says beside it.
	 */
	private function redirect_back( array $outcome ) {
		WPCPM_Flash::set( self::FLASH, $outcome );
		wp_safe_redirect( $this->admin_url() );
		exit;
	}
}
