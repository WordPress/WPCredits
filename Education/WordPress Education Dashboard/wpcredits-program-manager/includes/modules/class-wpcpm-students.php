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
 * What is the students' own is here: the words, the table, the school and the status the list and
 * its invitations card are narrowed to, and the Sync tab.
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
			WPCPM_Students_Sync::META_COURSE,
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
			'page_label'         => __( 'Student Report Card:', 'wpcredits-program-manager' ),
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
	 * Queue an invitation for everybody who has never had one, at the institution and with the status
	 * the list was narrowed to if it was, then back to the list so narrowed on the Accounts tab, as
	 * every other press on the tab comes back to the list as it stood.
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

		// From the posted fields, not the URL: `admin-post.php` never sees the list's query string,
		// so reading the filters from there would send to every student while the screen that
		// offered the button was showing one institution, or one status.
		$institution = WPCPM_Request::posted_text( 'wpcpm_institution' );
		$status      = WPCPM_Request::posted_text( 'wpcpm_student_status' );
		$pending     = WPCPM_Mail::only_status( WPCPM_Mail::only_institution( self::never_invited(), $institution ), $status );
		$back        = $this->accounts_url();
		$narrowed    = array_filter(
			array(
				'wpcpm_institution'    => $institution,
				'wpcpm_student_status' => $status,
			)
		);

		if ( ! empty( $narrowed ) ) {
			// Each value encoded, because `add_query_arg()` sets a value as it is given.
			$back = add_query_arg( array_map( 'rawurlencode', $narrowed ), $back );
		}

		if ( empty( $pending ) ) {
			$this->leave( $back, 'invites-none' );
		}

		WPCPM_Mail::queue_invites( $pending );

		$this->leave( $back, 'invites-queued' );
	}

	/**
	 * Where the list stands, as the request says: its view, search, institution, status, sort and page,
	 * each encoded, the empty ones left out.
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
					'wpcpm_view'           => WPCPM_Request::key( 'wpcpm_view' ),
					's'                    => WPCPM_Request::text( 's' ),
					'wpcpm_institution'    => self::institution_filter(),
					'wpcpm_student_status' => self::status_filter(),
					'orderby'              => WPCPM_Request::key( 'orderby' ),
					'order'                => WPCPM_Request::key( 'order' ),
					'paged'                => WPCPM_Request::id( 'paged' ),
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
	 * institution and the status the list is showing, and how many of them there are left out because
	 * an activation held their invitation (1.122.33), a student who also mentors, which only their own
	 * row sends.
	 *
	 * Its button posts the institution, the status and the tab, so the press queues whom the card
	 * counted and comes back to the list so narrowed. The status is said in the words the list shows
	 * for it (`WPCPM_Program::status_words()`) and posted as it is stored.
	 *
	 * **Where the filters leave no student at all** (a status nobody holds, a school nobody belongs
	 * to, a status nobody at the school holds) the card says so, in the words for the filters it is
	 * narrowed by (`card_words()`), and does not say every one has been invited, which is untrue of a
	 * status nobody holds. The students the filters leave are counted from everybody holding the role
	 * (`everyone()`), invited or not, and only when nobody is left to invite, so the card pays for
	 * that read only then.
	 *
	 * Its Stop and Dismiss are the mail layer's own forms, which name no tab: they come back to the
	 * address they were pressed on, this tab, with the filters in it, and Stop's outcome waits on the
	 * flash channel this screen reads, which the card names in its form.
	 */
	private function render_invitations() {
		// **The card obeys the filters the list is showing.** Offering "invite 241 students" under a
		// list narrowed to one institution, or to one status, is how somebody emails two hundred people
		// by accident.
		$filter  = self::institution_filter();
		$status  = self::status_filter();
		$words   = self::card_words( $filter, $status );
		$pending = WPCPM_Mail::only_status( WPCPM_Mail::only_institution( self::never_invited(), $filter ), $status );
		$held    = WPCPM_Mail::only_status( WPCPM_Mail::only_institution( self::held_back(), $filter ), $status );

		// Nobody to invite and nobody held back: either every student the filters leave was invited, or
		// the filters leave none. The students are counted from everybody holding the role, so the
		// first is told from the second; the card is handed the second's words only then.
		$nobody = empty( $pending ) && empty( $held ) && array() === WPCPM_Mail::only_status( WPCPM_Mail::only_institution( self::everyone(), $filter ), $status );

		WPCPM_Mail::render_invite_card(
			array(
				'action'      => self::ACTION_BULK,
				'pending'     => $pending,
				'held'        => count( $held ),
				'none'        => $nobody ? $words['none'] : '',
				'noun'        => $words['noun'],
				// The button's own words, so its noun and verb agree with the count, one or more.
				'button'      => $words['button'],
				'button_args' => $words['args'],
				'hidden'      => array(
					'wpcpm_institution'    => $filter,
					'wpcpm_student_status' => $status,
					self::TAB_FIELD        => self::TAB_ACCOUNTS,
				),
				'flash'       => $this->flash_key(),
			)
		);
	}

	/**
	 * The words the invitations card says its people in, for the filters the list is narrowed by: the
	 * plural noun the card's sentences take, the sentence it says when the filters leave no student at
	 * all, the button's words as `_n_noop()` holds them, and the values the button takes after the
	 * count.
	 *
	 * Four forms, written out whole rather than joined, so a translation can order the words as its
	 * language does. The sentence for no student is one of them too, in the noun's own order: no
	 * students, no students at a school, none with a status, none at a school with a status.
	 *
	 * @param string $institution The institution the list is narrowed to, or ''.
	 * @param string $status      The status it is narrowed to, as stored, or ''.
	 * @return array{noun: string, none: string, button: array, args: string[]}
	 */
	private static function card_words( $institution, $status ) {
		$words = '' === $status ? '' : WPCPM_Program::status_words( $status );

		if ( '' === $institution && '' === $status ) {
			return array(
				'noun'   => __( 'students', 'wpcredits-program-manager' ),
				'none'   => __( 'There are no students.', 'wpcredits-program-manager' ),
				/* translators: %s: how many students. */
				'button' => _n_noop( 'Invite %s student who has never been invited', 'Invite %s students who have never been invited', 'wpcredits-program-manager' ),
				'args'   => array(),
			);
		}

		if ( '' === $status ) {
			return array(
				/* translators: %s: institution name. */
				'noun'   => sprintf( __( 'students at %s', 'wpcredits-program-manager' ), $institution ),
				/* translators: %s: institution name. */
				'none'   => sprintf( __( 'There are no students at %s.', 'wpcredits-program-manager' ), $institution ),
				/* translators: 1: how many students, 2: institution name. */
				'button' => _n_noop( 'Invite %1$s student at %2$s who has never been invited', 'Invite %1$s students at %2$s who have never been invited', 'wpcredits-program-manager' ),
				'args'   => array( $institution ),
			);
		}

		if ( '' === $institution ) {
			return array(
				/* translators: %s: a student status, such as Paused. */
				'noun'   => sprintf( __( 'students with the status %s', 'wpcredits-program-manager' ), $words ),
				/* translators: %s: a student status, such as Paused. */
				'none'   => sprintf( __( 'There are no students with the status %s.', 'wpcredits-program-manager' ), $words ),
				/* translators: 1: how many students, 2: a student status, such as Paused. */
				'button' => _n_noop( 'Invite %1$s student with the status %2$s who has never been invited', 'Invite %1$s students with the status %2$s who have never been invited', 'wpcredits-program-manager' ),
				'args'   => array( $words ),
			);
		}

		return array(
			/* translators: 1: institution name, 2: a student status, such as Paused. */
			'noun'   => sprintf( __( 'students at %1$s with the status %2$s', 'wpcredits-program-manager' ), $institution, $words ),
			/* translators: 1: institution name, 2: a student status, such as Paused. */
			'none'   => sprintf( __( 'There are no students at %1$s with the status %2$s.', 'wpcredits-program-manager' ), $institution, $words ),
			/* translators: 1: how many students, 2: institution name, 3: a student status, such as Paused. */
			'button' => _n_noop( 'Invite %1$s student at %2$s with the status %3$s who has never been invited', 'Invite %1$s students at %2$s with the status %3$s who have never been invited', 'wpcredits-program-manager' ),
			'args'   => array( $institution, $words ),
		);
	}

	/**
	 * Every Student account, invited or never, by the Student accounts table's own role
	 * (`table_class()`), which the invitations card narrows to the list's filters and counts, to tell
	 * a list that holds nobody from one whose people were all invited.
	 *
	 * The table's classes are loaded first, as `never_invited()` loads them, for the card's button
	 * posts to admin-post.php, where nothing else does.
	 *
	 * @return int[]
	 */
	private static function everyone() {
		wpcpm_load_accounts_tables();

		$table_class = static::table_class();

		return WPCPM_Mail::everyone( $table_class::role() );
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

	/**
	 * Which student status the screen is narrowed to, if any.
	 *
	 * **The argument is `wpcpm_student_status`, not `wpcpm_status`**: the mail layer's Stop and
	 * Dismiss come back to the address they were pressed on with `wpcpm_status` removed, as an old
	 * outcome argument, so a list narrowed by that name would lose its status on the return. The
	 * name is the one `WPCPM_Students_Table::STATUS_ARG` holds.
	 *
	 * @return string Status name, as stored, with a run of white space collapsed as the roster
	 *                reads it (`WPCPM_Students_Sync::status_of()`), or an empty string for all of them.
	 */
	public static function status_filter() {
		return WPCPM_Request::text( 'wpcpm_student_status' );
	}
}
