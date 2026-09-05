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
 */
class WPCPM_Mentors extends WPCPM_Sync_Module {

	const ACTION_SYNC   = 'wpcpm_mentors_sync';
	const ACTION_CANCEL = 'wpcpm_mentors_cancel';
	const ACTION_INVITE = 'wpcpm_mentors_invite';

	/** Admin-post action for inviting everybody who has never been invited. */
	const ACTION_BULK = 'wpcpm_mentors_bulk_invite';
	const ACTION_TICK   = 'wpcpm_mentors_tick';

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
	 * This module is built.
	 *
	 * @return bool
	 */
	public function is_implemented() {
		return true;
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
	}

	/**
	 * Activation: schedule the daily sync and create the mentor page.
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
		) as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}


	/**
	 * Email one mentor their login invitation.
	 */
	public function handle_invite() {
		$this->verify( self::ACTION_INVITE );

		$user_id = WPCPM_Request::posted_id( 'user_id' );
		$result  = WPCPM_Mentors_Sync::send_invite( $user_id );

		$this->redirect_back( is_wp_error( $result ) ? 'error' : 'invited' );
	}

	/**
	 * Queue an invitation for every mentor who has never had one.
	 *
	 * Queued rather than sent here: `send_invite()` sends immediately, which is right for one row
	 * and would time out somewhere in the middle of two hundred. The queue is drained by cron a
	 * batch at a time, which is what it was built for.
	 */
	public function handle_bulk_invite() {
		$this->verify( self::ACTION_BULK );

		$pending = WPCPM_Mail::never_invited( WPCPM_Roles::ROLE_MENTOR, 'wpcpm_mentor_invited' );

		if ( empty( $pending ) ) {
			$this->redirect_back( 'invites-none' );
		}

		WPCPM_Mail::queue_invites( $pending );

		$this->redirect_back( 'invites-queued' );
	}

	/**
	 * Render the Mentors screen.
	 */
	public function render_admin_page() {
		$progress = WPCPM_Mentors_Sync::progress();
		$report   = get_option( WPCPM_Mentors_Sync::OPT_REPORT );
		$error    = get_option( WPCPM_Mentors_Sync::OPT_ERROR );
		$last     = (int) get_option( WPCPM_Mentors_Sync::OPT_LAST );

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		$this->render_mentor_notice();

		if ( ! WPCPM_Settings::is_connected() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Airtable is not connected yet, so no mentors can be synced.', 'wpcredits-program-manager' ),
				esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ),
				esc_html__( 'Open settings', 'wpcredits-program-manager' )
			);
		}

		if ( $error ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( $error )
			);
		}

		// Student rows are cached in user meta, so institution and team names only
		// appear once a sync has read the tables they live in.
		if ( WPCPM_Mentors_Sync::has_unresolved_links() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Institution and team names have not been read yet.', 'wpcredits-program-manager' ),
				esc_html__( 'Airtable sends those two fields as record IDs, which the sync turns into names. Run a sync to fill them in - until then the mentor page leaves them blank rather than showing an ID.', 'wpcredits-program-manager' )
			);
		}

		$this->render_sync_panel( $progress, $last );

		if ( is_array( $report ) ) {
			$this->render_report( $report );
		}

		$this->render_mentor_list();

		echo '</div>';
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
	 * This screen's own outcomes, over the three every sync screen shares.
	 */
	private function render_mentor_notice() {
		$this->render_status_notice(
			array(
				'invited'         => array( 'success', __( 'Invitation email sent.', 'wpcredits-program-manager' ) ),
				'invites-queued'  => array( 'success', __( 'Invitations queued. They go out in the background - the progress is shown below.', 'wpcredits-program-manager' ) ),
				'invites-none'    => array( 'info', __( 'Nobody was waiting for an invitation.', 'wpcredits-program-manager' ) ),
				'invites-stopped' => array( 'info', __( 'Sending stopped. Invitations already sent cannot be recalled.', 'wpcredits-program-manager' ) ),
			)
		);
	}

	/**
	 * The sync controls and live progress.
	 *
	 * @param array $progress Progress data.
	 * @param int   $last     Timestamp of the last completed sync.
	 */
	private function render_sync_panel( array $progress, $last ) {
		WPCPM_Mail::render_invite_card(
			array(
				'action'  => self::ACTION_BULK,
				'pending' => WPCPM_Mail::never_invited( WPCPM_Roles::ROLE_MENTOR, 'wpcpm_mentor_invited' ),
				'noun'    => __( 'mentors', 'wpcredits-program-manager' ),
			)
		);

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		if ( $progress['running'] ) {
			$this->render_progress( $progress );

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_CANCEL );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CANCEL ) . '" />';
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

	/**
	 * The provisioned mentors and their student counts.
	 */
	private function render_mentor_list() {
		$mentors = get_users(
			array(
				'role'    => WPCPM_Roles::ROLE_MENTOR,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => 500,
			)
		);

		$page_url = WPCPM_Mentors_Dashboard::page_url();

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Mentor accounts', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $mentors ) ) )
		);

		if ( $page_url ) {
			printf(
				'<p>%1$s <a href="%2$s">%2$s</a></p>',
				esc_html__( 'Mentor Dashboard:', 'wpcredits-program-manager' ),
				esc_url( $page_url )
			);
		} else {
			echo '<p class="wpcpm-warning">' . esc_html__( 'The mentor page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ) . '</p>';
		}

		if ( empty( $mentors ) ) {
			echo '<p>' . esc_html__( 'No mentor accounts yet. Run a sync to create them.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Mentor', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Username', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Students', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Past', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Status', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Actions', 'wpcredits-program-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $mentors as $mentor ) {
			$count   = WPCPM_Mentors_Dashboard::get_mentee_count( $mentor->ID );
			$active  = (int) get_user_meta( $mentor->ID, WPCPM_Mentors_Sync::META_ACTIVE, true );
			$invited = (int) get_user_meta( $mentor->ID, 'wpcpm_mentor_invited', true );

			echo '<tr>';
			printf(
				'<td><a href="%1$s">%2$s</a></td>',
				esc_url( get_edit_user_link( $mentor->ID ) ),
				esc_html( $mentor->display_name )
			);
			printf( '<td><code>%s</code></td>', esc_html( $mentor->user_login ) );
			printf( '<td>%s</td>', esc_html( number_format_i18n( $count ) ) );
			printf( '<td>%s</td>', esc_html( number_format_i18n( (int) get_user_meta( $mentor->ID, WPCPM_Mentors_Sync::META_PAST_COUNT, true ) ) ) );
			printf(
				'<td>%s</td>',
				$active
					? esc_html__( 'Active', 'wpcredits-program-manager' )
					: esc_html__( 'Not in Airtable', 'wpcredits-program-manager' )
			);

			echo '<td class="wpcpm-list__actions">';

			if ( $page_url ) {
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( add_query_arg( 'wpcpm_mentor', $mentor->ID, $page_url ) ),
					esc_html__( 'View page', 'wpcredits-program-manager' )
				);
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_INVITE );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_INVITE ) . '" />';
			printf( '<input type="hidden" name="user_id" value="%d" />', (int) $mentor->ID );
			printf(
				'<button type="submit" class="button-link">%s</button>',
				$invited
					? esc_html__( 'Resend invite', 'wpcredits-program-manager' )
					: esc_html__( 'Send invite', 'wpcredits-program-manager' )
			);
			echo '</form>';

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Accounts are created with a random password and no email. "Send invite" emails that mentor a password-reset link so they can set their own.', 'wpcredits-program-manager' ) . '</p>';
		echo '</div>';
	}
}
