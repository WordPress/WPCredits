<?php
/**
 * Mentors module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 2 - Mentors.
 *
 * Provisions Mentor accounts from Airtable, gives each mentor a private page
 * listing their assigned students, and reports on the last sync.
 *
 * Its screen is the accounts screen every audience's module shares (`WPCPM_Accounts_Screen`).
 * What is the mentors' own is here: the words, the table, the invitations card, and the Sync tab
 * with its warning while names are still unread.
 */
class WPCPM_Mentors extends WPCPM_Sync_Module {

	use WPCPM_Accounts_Screen;

	const ACTION_SYNC   = 'wpcpm_mentors_sync';
	const ACTION_CANCEL = 'wpcpm_mentors_cancel';
	const ACTION_INVITE = 'wpcpm_mentors_invite';

	/** Admin-post action for inviting everybody who has never been invited. */
	const ACTION_BULK = 'wpcpm_mentors_bulk_invite';
	const ACTION_TICK   = 'wpcpm_mentors_tick';

	/** The screen's tab the accounts are on, which every link and form of the list returns to. */
	const TAB_ACCOUNTS = 'accounts';

	/** The screen's tab the Airtable sync is on, which the sync's forms return to. */
	const TAB_SYNC = 'sync';

	/**
	 * The screen's tabs, slug => label, in the bar's order: the accounts, the tab the screen opens
	 * on, then the sync. English, as a constant has to hold them; `tab_labels()` translates them.
	 */
	const TABS = array(
		self::TAB_ACCOUNTS => 'Accounts',
		self::TAB_SYNC     => 'Sync',
	);

	/**
	 * The user option the rows-per-page choice is kept in: the Mentor accounts table's own name, here
	 * too, because the save is hooked at boot, long before that class is loaded.
	 */
	const PER_PAGE_OPTION = 'wpcpm_mentors_per_page';

	/** The flash channel that carries a press on the ticked accounts' count and kind, beside the outcome. */
	const FLASH_DETAIL = 'mentors_admin_detail';

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'mentors';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Mentors', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_MENTOR;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Mentor accounts are created from the Airtable Mentors table, using each mentor\'s WordPress.org username. Every mentor gets a Mentor-level page listing the students assigned to them.', 'wpcredits-program-manager' );
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		WPCPM_Mentors_Sync::register_cron();
		WPCPM_Mentors_Dashboard::init();
		WPCPM_Mentor_Notes::init();
		WPCPM_Mentor_Availability::init();
		WPCPM_Mentor_Calls::init();
		WPCPM_Call_Calendar::init();
		WPCPM_Group_Sessions::init();

		// The hourly call reminders, put back on the clock whenever they are missing, as the
		// sync above does for its own job: the activation hook is the only other caller and
		// it never fires on this site's deploy path.
		add_action( 'init', array( 'WPCPM_Mentor_Calls', 'schedule' ), 20 );

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_INVITE, array( $this, 'handle_invite' ) );
		add_action( 'admin_post_' . self::ACTION_BULK, array( $this, 'handle_bulk_invite' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );

		// The screen's own load hook, once the menu exists, and its rows-per-page save.
		$this->boot_screen();
	}

	/**
	 * Activation: schedule the recurring sync and create the mentor page.
	 */
	public function activate() {
		WPCPM_Mentors_Sync::schedule();
		WPCPM_Mentor_Calls::schedule();
		WPCPM_Mentors_Dashboard::ensure_page();
	}

	/**
	 * Deactivation: stop all scheduled work.
	 */
	public function deactivate() {
		WPCPM_Mentors_Sync::unschedule();
		WPCPM_Mentor_Calls::unschedule();
		WPCPM_Mail::clear_queue();
	}

	/**
	 * Uninstall: drop the module's own options, user meta and notes.
	 *
	 * Accounts are left alone - they are people, not plugin state.
	 */
	public function uninstall() {
		WPCPM_Mentor_Notes::delete_all();
		WPCPM_Mentor_Calls::delete_all();
		// Prefix-named, so it cannot be swept by listing keys: booking locks left by
		// requests that died holding one, and the per-student resolved-mentor cache.
		WPCPM_Mentor_Calls::flush_cache();
		WPCPM_WPorg_Profile::flush_cache();

		delete_option( WPCPM_Mentors_Sync::OPT_STATE );
		delete_option( WPCPM_Mentors_Sync::OPT_REPORT );
		delete_option( WPCPM_Mentors_Sync::OPT_LAST );
		delete_option( WPCPM_Mentors_Sync::OPT_ERROR );
		delete_option( WPCPM_Mentors_Sync::OPT_LOCK );
		delete_option( WPCPM_Mentors_Sync::OPT_FIELDS );
		delete_option( WPCPM_Mentors_Sync::OPT_LOOKUPS );
		delete_option( WPCPM_Mentors_Sync::OPT_SPONSORSHIP );
		delete_option( WPCPM_Mentors_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Mentors_Dashboard::OPT_TITLE_FIXED );

		foreach ( array(
			WPCPM_Mentors_Sync::META_RECORD_ID,
			WPCPM_Mentors_Sync::META_PROFILE,
			WPCPM_Mentors_Sync::META_ACTIVE,
			WPCPM_Mentors_Sync::META_MENTEES,
			WPCPM_Mentors_Sync::META_COUNT,
			WPCPM_Mentors_Sync::META_PAST_COUNT,
			WPCPM_Mentors_Sync::META_UPDATED,
			WPCPM_Mentor_Availability::META,
			// Set on students as well as mentors, but it exists only because the call
			// calendar needs a per-person clock, so it goes with the calendar.
			WPCPM_Mentor_Availability::META_TIMEZONE,
			'wpcpm_mentor_invited',
			// A manager's rows-per-page choice for the Mentor accounts, which core keeps as user meta.
			self::PER_PAGE_OPTION,
		) as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}

	/**
	 * The sync this module owns.
	 *
	 * @return string
	 */
	protected function sync_class() {
		return 'WPCPM_Mentors_Sync';
	}

	/**
	 * The flash channel the Mentors screen reads its outcomes from.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return 'mentors_admin';
	}

	/**
	 * The Mentor accounts table: the list the screen draws, whose role and stamp the invitations
	 * read, and whose rule the rows-per-page save keeps.
	 *
	 * @return string
	 */
	protected static function table_class() {
		return 'WPCPM_Mentors_Table';
	}

	/**
	 * The words the screen prints for mentors, in the places every accounts screen prints its
	 * audience's: the keys `WPCPM_Accounts_Screen::screen_words()` lists.
	 *
	 * @return array<string, string>
	 */
	protected function screen_words() {
		return array(
			'heading_views'      => __( 'Filter mentor accounts list', 'wpcredits-program-manager' ),
			'heading_pagination' => __( 'Mentor accounts list navigation', 'wpcredits-program-manager' ),
			'heading_list'       => __( 'Mentor accounts list', 'wpcredits-program-manager' ),
			'not_connected'      => __( 'Airtable is not connected yet, so no mentors can be synced.', 'wpcredits-program-manager' ),
			'list_heading'       => __( 'Mentor accounts', 'wpcredits-program-manager' ),
			'page_label'         => __( 'Mentor Dashboard:', 'wpcredits-program-manager' ),
			'page_missing'       => __( 'The mentor page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ),
			'search'             => __( 'Search mentors', 'wpcredits-program-manager' ),
			'list_note'          => __( 'Accounts are created with a random password and no email. "Send invite" emails that mentor a password-reset link so they can set their own.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The Mentor Report Card's address, which the list card names, or '' while the page is missing.
	 *
	 * @return string
	 */
	protected function dashboard_url() {
		return WPCPM_Mentors_Dashboard::page_url();
	}

	/**
	 * Queue an invitation for every mentor who has never had one, then back to the Accounts tab, as
	 * every other press on the tab comes back to it.
	 *
	 * Queued rather than sent here: `send_invite()` sends immediately, which is right for one row
	 * and would time out somewhere in the middle of two hundred. The queue is drained by cron a
	 * batch at a time, which is what it was built for.
	 *
	 * The count a press on the ticked accounts carries is that press's alone, and this button leaves
	 * the same outcomes with none: `leave()` empties that channel, so a count never printed is not
	 * shown under these.
	 */
	public function handle_bulk_invite() {
		$this->verify( self::ACTION_BULK );

		$pending = self::never_invited();

		if ( empty( $pending ) ) {
			$this->leave( $this->accounts_url(), 'invites-none' );
		}

		WPCPM_Mail::queue_invites( $pending );

		$this->leave( $this->accounts_url(), 'invites-queued' );
	}

	/**
	 * Where the list stands, as the request says: its view, search, sort and page, each encoded, the
	 * empty ones left out.
	 *
	 * What a press in the list comes back to (`list_url()`), and what each row's invitation link
	 * carries, so the invitation comes back to the same place. Rebuilt from what the list reads rather
	 * than copied from the address, so the form's own fields (its nonce, the ticked accounts, the
	 * action chosen) are never carried. Each value is encoded, because `add_query_arg()` sets a value
	 * as it is given.
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
					'wpcpm_view' => WPCPM_Request::key( 'wpcpm_view' ),
					's'          => WPCPM_Request::text( 's' ),
					'orderby'    => WPCPM_Request::key( 'orderby' ),
					'order'      => WPCPM_Request::key( 'order' ),
					'paged'      => WPCPM_Request::id( 'paged' ),
				)
			)
		);
	}

	/**
	 * The Sync tab: the sync's notices, the warning while Airtable is not connected, the last run's
	 * error, the warning while names are still unread, the Airtable sync card and the last run's
	 * report.
	 */
	private function render_tab_sync() {
		$report = get_option( WPCPM_Mentors_Sync::OPT_REPORT );
		$error  = get_option( WPCPM_Mentors_Sync::OPT_ERROR );

		$this->render_notice_from( self::sync_messages() );
		$this->render_not_connected();

		if ( $error ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( $error )
			);
		}

		// Student rows are cached in user meta, so institution and team names only appear once a
		// sync has read the tables they live in. On this tab, since what it asks for is a sync.
		if ( WPCPM_Mentors_Sync::has_unresolved_links() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Institution and team names have not been read yet.', 'wpcredits-program-manager' ),
				esc_html__( 'Airtable sends those two fields as record IDs, which the sync turns into names. Run a sync to fill them in - until then the mentor page leaves them blank rather than showing an ID.', 'wpcredits-program-manager' )
			);
		}

		$this->render_sync_panel( WPCPM_Mentors_Sync::progress(), (int) get_option( WPCPM_Mentors_Sync::OPT_LAST ) );

		if ( is_array( $report ) ) {
			$this->render_report( $report );
		}
	}

	/**
	 * The invitations card, on the Accounts tab: every mentor never invited.
	 *
	 * Its button posts the tab, so the press comes back to it. Its Stop and Dismiss are the mail
	 * layer's own forms, which name no tab: they come back to the address they were pressed on, this
	 * tab, and Stop's outcome waits on the flash channel this screen reads, which the card names in
	 * its form, so "Sending stopped." prints here.
	 */
	private function render_invitations() {
		WPCPM_Mail::render_invite_card(
			array(
				'action'  => self::ACTION_BULK,
				'pending' => self::never_invited(),
				'noun'    => __( 'mentors', 'wpcredits-program-manager' ),
				'hidden'  => array( self::TAB_FIELD => self::TAB_ACCOUNTS ),
				'flash'   => $this->flash_key(),
			)
		);
	}

	/**
	 * The Airtable sync card, on the Sync tab: the live progress and Cancel sync while a run is on,
	 * and otherwise when the last run finished and the button that starts one. Both forms name the
	 * tab, so the press comes back to it.
	 *
	 * @param array $progress Progress data.
	 * @param int   $last     Timestamp of the last completed sync.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		if ( $progress['running'] ) {
			$this->render_progress( $progress );

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_CANCEL );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CANCEL ) . '" />';
			$this->tab_field( self::TAB_SYNC );
			submit_button( __( 'Cancel sync', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			if ( $last ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: human-readable time difference. */
							__( 'Last completed %s ago.', 'wpcredits-program-manager' ),
							human_time_diff( $last, time() )
						)
					)
				);
			} else {
				echo '<p>' . esc_html__( 'No sync has run yet.', 'wpcredits-program-manager' ) . '</p>';
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_SYNC );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SYNC ) . '" />';
			$this->tab_field( self::TAB_SYNC );
			submit_button( __( 'Sync mentors now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Format a duration as a clock value.
	 *
	 * `human_time_diff()` rounds to "1 min" and would sit unchanged for a whole
	 * minute, which is the opposite of what a progress readout needs.
	 *
	 * @param int $seconds Elapsed seconds.
	 * @return string
	 */
	public static function format_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );

		if ( $seconds >= HOUR_IN_SECONDS ) {
			return sprintf( '%d:%02d:%02d', intdiv( $seconds, HOUR_IN_SECONDS ), intdiv( $seconds % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ), $seconds % MINUTE_IN_SECONDS );
		}

		return sprintf( '%d:%02d', intdiv( $seconds, MINUTE_IN_SECONDS ), $seconds % MINUTE_IN_SECONDS );
	}

	/**
	 * The live progress readout.
	 *
	 * Rendered server-side with the current values so it is already populated on
	 * first paint - and so it still reports real progress with JavaScript off,
	 * where cron drives the run and the meta-refresh fallback updates the page.
	 *
	 * @param array $progress Progress payload from WPCPM_Mentors_Sync::progress().
	 */
	private function render_progress( array $progress ) {
		$percent = isset( $progress['percent'] ) ? (int) $progress['percent'] : 0;

		printf(
			'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
			esc_attr( self::ACTION_TICK ),
			esc_attr( wp_create_nonce( self::ACTION_TICK ) )
		);

		echo '<p class="wpcpm-progress__head">';
		echo '<span class="spinner is-active" aria-hidden="true"></span> ';
		printf( '<strong data-wpcpm-label>%s</strong>', esc_html( $progress['label'] ) );
		printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( $progress['step_label'] ) );
		echo '</p>';

		printf(
			'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar>
				<div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div>
			</div>',
			(int) $percent,
			esc_attr__( 'Sync progress', 'wpcredits-program-manager' )
		);

		echo '<p class="wpcpm-progress__meta">';
		printf( '<span data-wpcpm-percent>%s</span>', esc_html( sprintf( '%d%%', $percent ) ) );
		echo ' · ';
		printf( '<span data-wpcpm-detail>%s</span>', esc_html( $progress['detail'] ) );
		echo ' · ';
		/* translators: %s: elapsed time as a clock value, e.g. "2:05". */
		$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
		printf(
			'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
			esc_attr( $elapsed_label ),
			esc_html( sprintf( $elapsed_label, self::format_duration( (int) $progress['elapsed'] ) ) )
		);
		echo '</p>';

		// Only shown if the run genuinely stops advancing, so the reassuring
		// message above never has to compete with a silent failure.
		printf(
			'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
			$progress['stalled'] ? '' : ' hidden',
			esc_html__( 'No progress for over two minutes. The run may have been interrupted - cancel it and start again.', 'wpcredits-program-manager' )
		);

		echo '<p class="description">' . esc_html__( 'The sync works in short bursts so it never hits a PHP timeout. Progress above updates every few seconds; you can safely leave this page and come back.', 'wpcredits-program-manager' ) . '</p>';

		echo '<noscript><p class="description">' . esc_html__( 'JavaScript is off, so this page reloads every 15 seconds to show progress. The sync itself continues either way.', 'wpcredits-program-manager' ) . '</p>';
		echo '<meta http-equiv="refresh" content="15" /></noscript>';

		echo '</div>';
	}

	/**
	 * The last run's numbers and warnings.
	 *
	 * @param array $report Stored report.
	 */
	private function render_report( array $report ) {
		$stats = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();

		$labels = array(
			'described'     => __( 'Field descriptions read from Airtable', 'wpcredits-program-manager' ),
			'mentors_seen'  => __( 'Active mentors in Airtable', 'wpcredits-program-manager' ),
			'created'       => __( 'Accounts created', 'wpcredits-program-manager' ),
			'linked'        => __( 'Existing accounts linked', 'wpcredits-program-manager' ),
			'updated'       => __( 'Accounts refreshed', 'wpcredits-program-manager' ),
			'invited'       => __( 'Invitations sent', 'wpcredits-program-manager' ),
			'revoked'       => __( 'Mentor role revoked (no longer active)', 'wpcredits-program-manager' ),
			'students_seen' => __( 'Current student reports read', 'wpcredits-program-manager' ),
			'past_seen'     => __( 'Past student reports read', 'wpcredits-program-manager' ),
			'assigned'      => __( 'Student assignments written', 'wpcredits-program-manager' ),
			'unassigned'    => __( 'Students with no mentor in Airtable', 'wpcredits-program-manager' ),
			'orphaned'      => __( 'Students assigned to a non-active mentor', 'wpcredits-program-manager' ),
			'skipped'       => __( 'Mentors skipped (missing data)', 'wpcredits-program-manager' ),
			'conflicts'     => __( 'Account conflicts', 'wpcredits-program-manager' ),
		);

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Last sync report', 'wpcredits-program-manager' ) . '</h2>';
		echo '<table class="wpcpm-table"><tbody>';

		foreach ( $labels as $key => $label ) {
			if ( ! isset( $stats[ $key ] ) ) {
				continue;
			}

			printf(
				'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
				esc_html( $label ),
				esc_html( number_format_i18n( (int) $stats[ $key ] ) )
			);
		}

		echo '</tbody></table>';

		$notices = isset( $report['notices'] ) ? (array) $report['notices'] : array();

		if ( ! empty( $notices ) ) {
			echo '<h3>' . esc_html__( 'Warnings', 'wpcredits-program-manager' ) . '</h3>';
			echo '<ul class="wpcpm-notices">';
			foreach ( $notices as $notice ) {
				echo '<li>' . esc_html( $notice ) . '</li>';
			}
			echo '</ul>';
		}

		echo '</div>';
	}
}
