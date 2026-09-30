<?php
/**
 * Students module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 1 - Students.
 *
 * Provisions Student accounts from Airtable and gives each student a Student-level
 * page with their own program details and the mentor assigned to them.
 *
 * Its screen is the accounts screen every audience's module shares (`WPCPM_Accounts_Screen`).
 * What is the students' own is here: the words, the table, the school the list and its
 * invitations card are narrowed to, and the Sync tab.
 */
class WPCPM_Students extends WPCPM_Sync_Module {

	use WPCPM_Accounts_Screen;

	const ACTION_SYNC   = 'wpcpm_students_sync';
	const ACTION_CANCEL = 'wpcpm_students_cancel';
	const ACTION_INVITE = 'wpcpm_students_invite';

	/** Admin-post action for inviting everybody who has never been invited. */
	const ACTION_BULK = 'wpcpm_students_bulk_invite';
	const ACTION_TICK   = 'wpcpm_students_tick';

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
	 * The user option the rows-per-page choice is kept in: the Student accounts table's own name, here
	 * too, because the save is hooked at boot, long before that class is loaded.
	 */
	const PER_PAGE_OPTION = 'wpcpm_students_per_page';

	/** The flash channel that carries a press on the ticked accounts' count and kind, beside the outcome. */
	const FLASH_DETAIL = 'students_admin_detail';

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'students';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Students', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_STUDENT;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Student accounts are created from the Airtable Students Reports table. Every student gets a Student-level page with their program details and the mentor assigned to them.', 'wpcredits-program-manager' );
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		WPCPM_Students_Sync::register_cron();
		WPCPM_Students_Dashboard::init();
		WPCPM_Student_Report_Form::init();
		WPCPM_Student_Feedback::init();

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_INVITE, array( $this, 'handle_invite' ) );
		add_action( 'admin_post_' . self::ACTION_BULK, array( $this, 'handle_bulk_invite' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );

		// The screen's own load hook, once the menu exists, and its rows-per-page save.
		$this->boot_screen();
	}

	/**
	 * Activation: schedule the sync and create the page.
	 */
	public function activate() {
		WPCPM_Students_Sync::schedule();
		WPCPM_Students_Dashboard::ensure_page();
	}

	/**
	 * Deactivation: stop scheduled work.
	 */
	public function deactivate() {
		WPCPM_Students_Sync::unschedule();
	}

	/**
	 * Uninstall: drop options and user meta. Accounts are left alone.
	 */
	public function uninstall() {
		delete_option( WPCPM_Students_Sync::OPT_STATE );
		delete_option( WPCPM_Students_Sync::OPT_REPORT );
		delete_option( WPCPM_Students_Sync::OPT_LAST );
		delete_option( WPCPM_Students_Sync::OPT_ERROR );
		delete_option( WPCPM_Students_Sync::OPT_LOCK );
		delete_option( WPCPM_Students_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Students_Dashboard::OPT_TITLE_FIXED );

		WPCPM_WPorg_Profile::flush_cache();

		foreach ( array(
			WPCPM_Students_Sync::META_RECORD_ID,
			WPCPM_Students_Sync::META_ACTIVE,
			WPCPM_Students_Sync::META_PROGRAM,
			WPCPM_Students_Sync::META_MENTOR,
			WPCPM_Students_Sync::META_UPDATED,
			WPCPM_Student_Feedback::META_RECORD,
			WPCPM_Student_Feedback::META_RECORD_PLACEMENT,
			'wpcpm_student_invited',
			// A manager's rows-per-page choice for the Student accounts, which core keeps as user meta.
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
		return 'WPCPM_Students_Sync';
	}

	/**
	 * The flash channel the Students screen reads its outcomes from.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return 'students_admin';
	}

	/**
	 * The Student accounts table: the list the screen draws, whose role and stamp the invitations
	 * read, and whose rule the rows-per-page save keeps.
	 *
	 * @return string
	 */
	protected static function table_class() {
		return 'WPCPM_Students_Table';
	}

	/**
	 * The words the screen prints for students, in the places every accounts screen prints its
	 * audience's: the keys `WPCPM_Accounts_Screen::screen_words()` lists.
	 *
	 * @return array<string, string>
	 */
	protected function screen_words() {
		return array(
			'heading_views'      => __( 'Filter student accounts list', 'wpcredits-program-manager' ),
			'heading_pagination' => __( 'Student accounts list navigation', 'wpcredits-program-manager' ),
			'heading_list'       => __( 'Student accounts list', 'wpcredits-program-manager' ),
			'not_connected'      => __( 'Airtable is not connected yet, so no students can be synced.', 'wpcredits-program-manager' ),
			'list_heading'       => __( 'Student accounts', 'wpcredits-program-manager' ),
			'page_label'         => __( 'Student page:', 'wpcredits-program-manager' ),
			'page_missing'       => __( 'The student page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ),
			'search'             => __( 'Search students', 'wpcredits-program-manager' ),
			'list_note'          => __( 'Accounts are created with a random password and no email. Usernames come from the student\'s WordPress.org profile where Airtable has one, and from their email address otherwise.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The student page's address, which the list card names, or '' while the page is missing.
	 *
	 * @return string
	 */
	protected function dashboard_url() {
		return WPCPM_Students_Dashboard::page_url();
	}

	/**
	 * Queue an invitation for everybody who has never had one, at the institution the list was
	 * narrowed to if it was, then back to the list at that institution on the Accounts tab, as every
	 * other press on the tab comes back to the list as it stood.
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

		// From the posted field, not the URL: `admin-post.php` never sees the list's query string,
		// so reading the filter from there would send to every student while the screen that
		// offered the button was showing one institution.
		$institution = WPCPM_Request::posted_text( 'wpcpm_institution' );
		$pending     = WPCPM_Mail::only_institution( self::never_invited(), $institution );
		$back        = '' === $institution ? $this->accounts_url() : add_query_arg( 'wpcpm_institution', rawurlencode( $institution ), $this->accounts_url() );

		if ( empty( $pending ) ) {
			$this->leave( $back, 'invites-none' );
		}

		WPCPM_Mail::queue_invites( $pending );

		$this->leave( $back, 'invites-queued' );
	}

	/**
	 * Where the list stands, as the request says: its view, search, institution, sort and page, each
	 * encoded, the empty ones left out.
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
					'wpcpm_view'        => WPCPM_Request::key( 'wpcpm_view' ),
					's'                 => WPCPM_Request::text( 's' ),
					'wpcpm_institution' => self::institution_filter(),
					'orderby'           => WPCPM_Request::key( 'orderby' ),
					'order'             => WPCPM_Request::key( 'order' ),
					'paged'             => WPCPM_Request::id( 'paged' ),
				)
			)
		);
	}

	/**
	 * The Sync tab: the sync's notices, the warning while Airtable is not connected, the last run's
	 * error, the Airtable sync card and the last run's report.
	 */
	private function render_tab_sync() {
		$report = get_option( WPCPM_Students_Sync::OPT_REPORT );
		$error  = get_option( WPCPM_Students_Sync::OPT_ERROR );

		$this->render_notice_from( self::sync_messages() );
		$this->render_not_connected();

		if ( $error ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( $error )
			);
		}

		$this->render_sync_panel( WPCPM_Students_Sync::progress(), (int) get_option( WPCPM_Students_Sync::OPT_LAST ) );

		if ( is_array( $report ) ) {
			$this->render_report( $report );
		}
	}

	/**
	 * The invitations card, on the Accounts tab: everybody never invited, narrowed to the
	 * institution the list is showing.
	 *
	 * Its button posts the institution and the tab, so the press queues whom the card counted and
	 * comes back to the list at that institution. Its Stop and Dismiss are the mail layer's own
	 * forms, which name no tab: they come back to the address they were pressed on, this tab, and
	 * Stop's outcome waits on the flash channel this screen reads, which the card names in its form.
	 */
	private function render_invitations() {
		// **The card obeys the filter the list is showing.** Offering "invite 241 students" under a
		// list narrowed to one institution is how somebody emails two hundred people by accident.
		$filter  = self::institution_filter();
		$pending = WPCPM_Mail::only_institution( self::never_invited(), $filter );

		WPCPM_Mail::render_invite_card(
			array(
				'action'  => self::ACTION_BULK,
				'pending' => $pending,
				'noun'    => '' === $filter
					? __( 'students', 'wpcredits-program-manager' )
					/* translators: %s: institution name. */
					: sprintf( __( 'students at %s', 'wpcredits-program-manager' ), $filter ),
				'hidden'  => array(
					'wpcpm_institution' => $filter,
					self::TAB_FIELD     => self::TAB_ACCOUNTS,
				),
				'flash'   => $this->flash_key(),
			)
		);
	}

	/**
	 * The Airtable sync card, on the Sync tab: the live progress and Cancel sync while a run is on,
	 * and otherwise when the last run finished and the button that starts one. Both forms name the
	 * tab, so the press comes back to it.
	 *
	 * @param array $progress Progress payload.
	 * @param int   $last     Timestamp of the last completed run.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		echo '<p class="description">' . esc_html__( 'Reads each student\'s program details, then their mentor\'s contact details - partly from Airtable, and the rest from the mentor\'s WordPress.org profile. Profile reads are cached for twelve hours.', 'wpcredits-program-manager' ) . '</p>';

		if ( $progress['running'] ) {
			printf(
				'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
				esc_attr( self::ACTION_TICK ),
				esc_attr( wp_create_nonce( self::ACTION_TICK ) )
			);

			echo '<p class="wpcpm-progress__head"><span class="spinner is-active" aria-hidden="true"></span> ';
			printf( '<strong data-wpcpm-label>%s</strong>', esc_html( $progress['label'] ) );
			printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( $progress['step_label'] ) );
			echo '</p>';

			printf(
				'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar><div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div></div>',
				(int) $progress['percent'],
				esc_attr__( 'Sync progress', 'wpcredits-program-manager' )
			);

			echo '<p class="wpcpm-progress__meta">';
			printf( '<span data-wpcpm-percent>%d%%</span> · ', (int) $progress['percent'] );
			printf( '<span data-wpcpm-detail>%s</span> · ', esc_html( $progress['detail'] ) );
			/* translators: %s: elapsed time as a clock value. */
			$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
			printf(
				'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
				esc_attr( $elapsed_label ),
				esc_html( sprintf( $elapsed_label, WPCPM_Mentors::format_duration( (int) $progress['elapsed'] ) ) )
			);
			echo '</p>';

			printf(
				'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
				$progress['stalled'] ? '' : ' hidden',
				esc_html__( 'No progress for over two minutes. The run may have been interrupted - cancel it and start again.', 'wpcredits-program-manager' )
			);

			echo '<noscript><meta http-equiv="refresh" content="15" /></noscript>';
			echo '</div>';

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
			submit_button( __( 'Sync students now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * The last run's numbers.
	 *
	 * @param array $report Stored report.
	 */
	private function render_report( array $report ) {
		$stats = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();

		$labels = array(
			'students_seen' => __( 'Student records read', 'wpcredits-program-manager' ),
			'mentors_seen'  => __( 'Mentors read', 'wpcredits-program-manager' ),
			'profiles_read' => __( 'WordPress.org profiles read', 'wpcredits-program-manager' ),
			'created'       => __( 'Accounts created', 'wpcredits-program-manager' ),
			'linked'        => __( 'Existing accounts linked', 'wpcredits-program-manager' ),
			'updated'       => __( 'Accounts refreshed', 'wpcredits-program-manager' ),
			'assigned'      => __( 'Program details written', 'wpcredits-program-manager' ),
			'invited'       => __( 'Invitations sent', 'wpcredits-program-manager' ),
			'revoked'       => __( 'Student role revoked (no longer in the program)', 'wpcredits-program-manager' ),
			'no_mentor'     => __( 'Students with no mentor assigned', 'wpcredits-program-manager' ),
			'skipped'       => __( 'Students skipped (missing data)', 'wpcredits-program-manager' ),
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

	/**
	 * Which institution the screen is narrowed to, if any.
	 *
	 * The name itself rather than an ID: institutions arrive from Airtable as linked records that
	 * the sync has already resolved to names on each student's row, and there is no institution
	 * post or term on this site to hold an ID against.
	 *
	 * @return string Resolved institution name, or an empty string for all of them.
	 */
	public static function institution_filter() {
		return WPCPM_Request::text( 'wpcpm_institution' );
	}
}
