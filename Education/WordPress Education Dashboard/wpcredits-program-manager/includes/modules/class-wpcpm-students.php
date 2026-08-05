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
 * Module 1 — Students.
 *
 * Provisions Student accounts from Airtable and gives each student a Student-level
 * page with their own program details and the mentor assigned to them.
 */
class WPCPM_Students extends WPCPM_Module {

	const ACTION_SYNC   = 'wpcpm_students_sync';
	const ACTION_CANCEL = 'wpcpm_students_cancel';
	const ACTION_INVITE = 'wpcpm_students_invite';
	const ACTION_TICK   = 'wpcpm_students_tick';

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
		WPCPM_Students_Sync::register_cron();
		WPCPM_Students_Dashboard::init();
		WPCPM_Student_Report_Form::init();

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_INVITE, array( $this, 'handle_invite' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );
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
			'wpcpm_student_invited',
		) as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}

	/**
	 * Advance the sync one slice and report progress.
	 */
	public function handle_tick() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
		}

		check_ajax_referer( self::ACTION_TICK, 'nonce' );

		if ( WPCPM_Students_Sync::is_running() ) {
			WPCPM_Students_Sync::run_tick( WPCPM_Students_Sync::BUDGET_AJAX );
		}

		wp_send_json_success( WPCPM_Students_Sync::progress() );
	}

	/**
	 * Start a sync.
	 */
	public function handle_sync() {
		$this->verify( self::ACTION_SYNC );

		$result = WPCPM_Students_Sync::start();

		$this->redirect_back( is_wp_error( $result ) ? 'error' : 'started' );
	}

	/**
	 * Abandon a stuck sync.
	 */
	public function handle_cancel() {
		$this->verify( self::ACTION_CANCEL );

		WPCPM_Students_Sync::cancel();

		$this->redirect_back( 'cancelled' );
	}

	/**
	 * Email one student their login invitation.
	 */
	public function handle_invite() {
		$this->verify( self::ACTION_INVITE );

		$user_id = WPCPM_Request::posted_id( 'user_id' );
		$result  = WPCPM_Students_Sync::send_invite( $user_id );

		$this->redirect_back( is_wp_error( $result ) ? 'error' : 'invited' );
	}

	/**
	 * Capability and nonce check.
	 *
	 * @param string $action Nonce action.
	 */
	private function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Return to the module screen.
	 *
	 * @param string $status Status slug.
	 */
	private function redirect_back( $status ) {
		wp_safe_redirect( add_query_arg( 'wpcpm_status', $status, $this->admin_url() ) );
		exit;
	}

	/**
	 * Render the Students screen.
	 */
	public function render_admin_page() {
		$progress = WPCPM_Students_Sync::progress();
		$report   = get_option( WPCPM_Students_Sync::OPT_REPORT );
		$error    = get_option( WPCPM_Students_Sync::OPT_ERROR );
		$last     = (int) get_option( WPCPM_Students_Sync::OPT_LAST );

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		$status = WPCPM_Request::key( 'wpcpm_status' );

		$messages = array(
			'started'   => array( 'success', __( 'Sync started — progress is shown below and updates as it runs.', 'wpcredits-program-manager' ) ),
			'cancelled' => array( 'info', __( 'Sync canceled.', 'wpcredits-program-manager' ) ),
			'invited'   => array( 'success', __( 'Invitation email sent.', 'wpcredits-program-manager' ) ),
			'error'     => array( 'error', __( 'That action could not be completed.', 'wpcredits-program-manager' ) ),
		);

		if ( isset( $messages[ $status ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $status ][0] ),
				esc_html( $messages[ $status ][1] )
			);
		}

		if ( ! WPCPM_Settings::is_connected() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Airtable is not connected yet, so no students can be synced.', 'wpcredits-program-manager' ),
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

		$this->render_sync_panel( $progress, $last );

		if ( is_array( $report ) ) {
			$this->render_report( $report );
		}

		$this->render_student_list();

		echo '</div>';
	}

	/**
	 * Sync controls and live progress.
	 *
	 * @param array $progress Progress payload.
	 * @param int   $last     Timestamp of the last completed run.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		echo '<p class="description">' . esc_html__( 'Reads each student\'s program details, then their mentor\'s contact details — partly from Airtable, and the rest from the mentor\'s WordPress.org profile. Profile reads are cached for twelve hours.', 'wpcredits-program-manager' ) . '</p>';

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
				esc_html__( 'No progress for over two minutes. The run may have been interrupted — cancel it and start again.', 'wpcredits-program-manager' )
			);

			echo '<noscript><meta http-equiv="refresh" content="15" /></noscript>';
			echo '</div>';

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
	 * The provisioned students.
	 */
	private function render_student_list() {
		$students = get_users(
			array(
				'role'    => WPCPM_Roles::ROLE_STUDENT,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => 500,
			)
		);

		$page_url = WPCPM_Students_Dashboard::page_url();

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Student accounts', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $students ) ) )
		);

		if ( $page_url ) {
			printf(
				'<p>%1$s <a href="%2$s">%2$s</a></p>',
				esc_html__( 'Student page:', 'wpcredits-program-manager' ),
				esc_url( $page_url )
			);
		} else {
			echo '<p class="wpcpm-warning">' . esc_html__( 'The student page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ) . '</p>';
		}

		if ( empty( $students ) ) {
			echo '<p>' . esc_html__( 'No student accounts yet. Run a sync to create them.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Student', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Username', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Program', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Mentor', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Actions', 'wpcredits-program-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $students as $student ) {
			$program = WPCPM_Students_Sync::get_program( $student->ID );
			$mentor  = WPCPM_Students_Sync::get_mentor( $student->ID );
			$invited = (int) get_user_meta( $student->ID, 'wpcpm_student_invited', true );

			echo '<tr>';
			printf(
				'<td><a href="%1$s">%2$s</a></td>',
				esc_url( get_edit_user_link( $student->ID ) ),
				esc_html( $student->display_name )
			);
			printf( '<td><code>%s</code></td>', esc_html( $student->user_login ) );
			printf( '<td>%s</td>', esc_html( ! empty( $program['program'] ) ? $program['program'] : '—' ) );
			printf( '<td>%s</td>', esc_html( ! empty( $mentor['name'] ) ? $mentor['name'] : '—' ) );

			echo '<td class="wpcpm-list__actions">';

			if ( $page_url ) {
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( add_query_arg( 'wpcpm_student_view', $student->ID, $page_url ) ),
					esc_html__( 'View page', 'wpcredits-program-manager' )
				);
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_INVITE );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_INVITE ) . '" />';
			printf( '<input type="hidden" name="user_id" value="%d" />', (int) $student->ID );
			printf(
				'<button type="submit" class="button-link">%s</button>',
				$invited
					? esc_html__( 'Resend invite', 'wpcredits-program-manager' )
					: esc_html__( 'Send invite', 'wpcredits-program-manager' )
			);
			echo '</form>';

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Accounts are created with a random password and no email. Usernames come from the student\'s WordPress.org profile where Airtable has one, and from their email address otherwise.', 'wpcredits-program-manager' ) . '</p>';
		echo '</div>';
	}
}
