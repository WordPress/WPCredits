<?php
/**
 * WP-CLI commands.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage the WPCredits program from the command line.
 *
 * A CLI path matters here because the sync is cron-driven: on a site with
 * WP-Cron disabled, or when a run has to be reproduced while reading the output,
 * `wp wpcredits sync-mentors` runs every phase to completion in the foreground.
 */
class WPCPM_CLI {

	/**
	 * Sync mentors and their assigned students from Airtable.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what the sync would read without creating or changing any account.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits sync-mentors
	 *     wp wpcredits sync-mentors --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function sync_mentors( $args, $assoc_args ) {
		unset( $args );

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$this->dry_run();

			return;
		}

		// There is no browser here to drive the ticks, so the CLI runs them itself.
		$started = WPCPM_Mentors_Sync::start( true );

		if ( is_wp_error( $started ) ) {
			WP_CLI::error( $started->get_error_message() );
		}

		$guard = 0;
		$seen  = '';

		while ( WPCPM_Mentors_Sync::is_running() && $guard < 200 ) {
			$progress = WPCPM_Mentors_Sync::progress();

			// One line per slice, so a long run visibly advances instead of
			// sitting silent for minutes.
			$line = sprintf(
				'[%3d%%] %s - %s',
				(int) $progress['percent'],
				$progress['label'] ? rtrim( $progress['label'], '…' ) : $progress['phase'],
				$progress['detail']
			);

			if ( $line !== $seen ) {
				WP_CLI::log( $line );
				$seen = $line;
			}

			WPCPM_Mentors_Sync::run_tick();
			++$guard;

			$error = get_option( WPCPM_Mentors_Sync::OPT_ERROR );
			if ( $error ) {
				WP_CLI::error( $error );
			}
		}

		WP_CLI::log( '[100%] ' . __( 'Finished.', 'wpcredits-program-manager' ) );

		$report = get_option( WPCPM_Mentors_Sync::OPT_REPORT );
		$stats  = ( is_array( $report ) && isset( $report['stats'] ) ) ? $report['stats'] : array();

		foreach ( $stats as $key => $value ) {
			WP_CLI::log( sprintf( '%-24s %s', $key, $value ) );
		}

		$notices = ( is_array( $report ) && ! empty( $report['notices'] ) ) ? $report['notices'] : array();
		foreach ( $notices as $notice ) {
			WP_CLI::warning( $notice );
		}

		WP_CLI::success( __( 'Mentor sync complete.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Show what one mentor's page will list.
	 *
	 * ## OPTIONS
	 *
	 * <mentor>
	 * : The mentor's WordPress username, email, or WordPress.org profile URL.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits mentor clk87
	 *     wp wpcredits mentor https://profiles.wordpress.org/clk87/
	 *
	 * @param array $args Positional arguments.
	 */
	public function mentor( $args ) {
		$needle = isset( $args[0] ) ? (string) $args[0] : '';

		$user = get_user_by( 'login', $needle );

		if ( ! $user && is_email( $needle ) ) {
			$user = get_user_by( 'email', $needle );
		}

		if ( ! $user ) {
			$login = WPCPM_Mentors_Sync::wporg_username( $needle );
			$user  = $login ? get_user_by( 'login', $login ) : false;
		}

		if ( ! $user ) {
			WP_CLI::error( sprintf( 'No account found for "%s".', $needle ) );
		}

		$mentees = WPCPM_Mentors_Dashboard::get_mentees( $user->ID );

		WP_CLI::log( sprintf( '%s (%s) - %d student(s)', $user->display_name, $user->user_login, count( $mentees ) ) );

		if ( empty( $mentees ) ) {
			return;
		}

		$rows = array();
		foreach ( $mentees as $mentee ) {
			$rows[] = array(
				'name'        => isset( $mentee['name'] ) ? $mentee['name'] : '',
				'status'      => isset( $mentee['status'] ) ? $mentee['status'] : '',
				'start'       => isset( $mentee['start'] ) ? $mentee['start'] : '',
				'end'         => isset( $mentee['end'] ) ? $mentee['end'] : '',
				'tutor'       => isset( $mentee['tutor'] ) ? $mentee['tutor'] : '',
				'institution' => isset( $mentee['institution'] ) ? $mentee['institution'] : '',
				'wporg'       => isset( $mentee['username'] ) ? $mentee['username'] : '',
				'slack'       => isset( $mentee['slack'] ) ? $mentee['slack'] : '',
				'team'        => isset( $mentee['team'] ) ? $mentee['team'] : '',
			);
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array( 'name', 'status', 'start', 'end', 'tutor', 'institution', 'wporg', 'slack', 'team' )
		);
	}

	/**
	 * Run the Mentor Status Checker.
	 *
	 * Checks every mentor holding the source status against their WordPress.org
	 * profile for the mentor's course completion. Reports only unless --promote is
	 * given, because promoting writes to the shared Airtable base.
	 *
	 * ## OPTIONS
	 *
	 * [--promote]
	 * : Also move mentors who qualify to the target status in Airtable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits check-mentors
	 *     wp wpcredits check-mentors --promote
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function check_mentors( $args, $assoc_args ) {
		unset( $args );

		if ( ! WPCPM_Mentor_Checker::is_configured() ) {
			WP_CLI::error( 'Airtable is not configured.' );
		}

		$promote  = ! empty( $assoc_args['promote'] );
		$settings = WPCPM_Mentor_Checker::config();
		$runner   = new WPCPM_Mentor_Checker_Runner( $settings );
		$seen     = 0;

		WP_CLI::log(
			sprintf(
				'Checking mentors with status "%s" for "%s"%s',
				$settings['source_status'],
				$settings['course_title'],
				$promote ? sprintf( ' - will promote to "%s".', $settings['target_status'] ) : ' - report only.'
			)
		);

		// One line per mentor, so a run over ~60 profiles visibly progresses
		// instead of sitting silent for several minutes.
		$result = $runner->run_all(
			$promote,
			static function ( $row ) use ( &$seen ) {
				++$seen;
				WP_CLI::log(
					sprintf(
						'[%3d] %-28s %-16s %s',
						$seen,
						mb_substr( isset( $row['name'] ) ? $row['name'] : '', 0, 28 ),
						isset( $row['state'] ) ? $row['state'] : '',
						isset( $row['action_note'] ) && '' !== $row['action_note'] ? $row['action_note'] : ( isset( $row['message'] ) ? $row['message'] : '' )
					)
				);
			}
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		foreach ( $result['summary'] as $key => $value ) {
			WP_CLI::log( sprintf( '%-12s %s', $key, $value ) );
		}

		WP_CLI::success( __( 'Mentor status check complete.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Re-create the custom roles and their capabilities.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits roles
	 */
	public function roles() {
		WPCPM_Roles::register();

		foreach ( WPCPM_Roles::custom_roles() as $slug => $role ) {
			$exists = get_role( $slug ) instanceof WP_Role;
			WP_CLI::log( sprintf( '%-22s %s', $slug, $exists ? 'ok' : 'MISSING' ) );
		}

		WP_CLI::success( __( 'Roles registered.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Read Airtable and report the totals without writing anything.
	 */
	private function dry_run() {
		if ( ! WPCPM_Settings::is_connected() ) {
			WP_CLI::error( 'Airtable is not configured.' );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$fields   = WPCPM_Mentors_Sync::fields();

		$mentors = $airtable->fetch_all(
			$settings['mentors_table'],
			array(
				'formula' => $airtable->formula_in( $fields['mentor_status'], array( $settings['mentor_status'] ) ),
				'fields'  => array( $fields['mentor_name'], $fields['mentor_profile'], $fields['mentor_email'] ),
			)
		);

		if ( is_wp_error( $mentors ) ) {
			WP_CLI::error( $mentors->get_error_message() );
		}

		$reports = $airtable->fetch_all(
			$settings['reports_table'],
			array(
				'formula' => $airtable->formula_in( $fields['report_status'], (array) $settings['student_statuses'] ),
				'fields'  => array( $fields['report_name'], $fields['report_mentor'], $fields['report_status'] ),
			)
		);

		if ( is_wp_error( $reports ) ) {
			WP_CLI::error( $reports->get_error_message() );
		}

		$unusable   = 0;
		$unassigned = 0;

		foreach ( $mentors as $record ) {
			$cells   = isset( $record['fields'] ) ? $record['fields'] : array();
			$profile = WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_profile'] ] ) ? $cells[ $fields['mentor_profile'] ] : '' );

			if ( '' === WPCPM_Mentors_Sync::wporg_username( $profile ) ) {
				++$unusable;
				WP_CLI::warning( sprintf( 'No username in profile value: "%s"', $profile ) );
			}
		}

		foreach ( $reports as $record ) {
			$cells = isset( $record['fields'] ) ? $record['fields'] : array();

			if ( empty( WPCPM_Airtable::link_ids( isset( $cells[ $fields['report_mentor'] ] ) ? $cells[ $fields['report_mentor'] ] : array() ) ) ) {
				++$unassigned;
			}
		}

		WP_CLI::log( sprintf( 'Active mentors:            %d', count( $mentors ) ) );
		WP_CLI::log( sprintf( '  without a usable name:  %d', $unusable ) );
		WP_CLI::log( sprintf( 'Student reports in scope:  %d', count( $reports ) ) );
		WP_CLI::log( sprintf( '  with no mentor linked:  %d', $unassigned ) );
		WP_CLI::success( 'Dry run complete - nothing was written.' );
	}
}
