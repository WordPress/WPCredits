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
 * `wp wpcredits sync_mentors` runs every phase to completion in the foreground.
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
	 *     wp wpcredits sync_mentors
	 *     wp wpcredits sync_mentors --dry-run
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
	 *     wp wpcredits mentor mentor-one
	 *     wp wpcredits mentor https://profiles.wordpress.org/mentor-one/
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
	 *     wp wpcredits check_mentors
	 *     wp wpcredits check_mentors --promote
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
		$began    = time();
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

		// The run's end sent what it promoted to Slack (`WPCPM_Mentor_Checker_Slack`); say how that went,
		// nothing about a message older than the run, and how many wait if its send could not go.
		$slack = WPCPM_Mentor_Checker_Slack::status_sentence( $began );

		if ( '' !== $slack ) {
			WP_CLI::log( $slack );
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
	 * Put the four original tracks into the Track Builder, from the seeds the plugin ships.
	 *
	 * Each is created published, its definition and its published copy both the seed, and the
	 * published tracks are compiled, so the site runs the four at once. A seed whose status or key the
	 * Track Builder already holds is passed over, so running this twice creates nothing the second
	 * time. A site does this once on its own, the first time it runs the Track Builder; the command is
	 * for a site that needs it again. Every seed is tried before the exit code says that one failed,
	 * since a second run creates only what is missing (the design's decision 33): a seed WordPress
	 * refused to publish is not left behind as a draft for that run to pass over.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits seed_tracks
	 */
	public function seed_tracks() {
		$failed = array();

		// `seed()` compiles once it has tried every seed, so the command compiles nothing of its own.
		foreach ( WPCPM_Track_Store::seed() as $key => $result ) {
			if ( is_wp_error( $result ) ) {
				$failed[] = (string) $key;
				WP_CLI::warning( sprintf( '%s: %s', $key, $result->get_error_message() ) );
			} elseif ( $result ) {
				WP_CLI::log( sprintf( '%-6s created as track %d and published', $key, $result ) );
			} else {
				WP_CLI::log( sprintf( '%-6s already in the Track Builder', $key ) );
			}
		}

		if ( array() !== $failed ) {
			WP_CLI::error( sprintf( 'Not created: %s. The others are in the Track Builder; run the command again once the reason is put right.', implode( ', ', $failed ) ) );
		}

		WP_CLI::success( __( 'The four original tracks are in the Track Builder, and every published track is compiled.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Show the outcome figures the site counts for its homepage, or read them again from Airtable.
	 *
	 * With no option, prints the stored set, when it was read, what the homepage may show of it, and
	 * the last attempt. The weekly event reads the figures every Monday after the public dashboard's
	 * build; --refresh reads them at once, without waiting for it.
	 *
	 * ## OPTIONS
	 *
	 * [--refresh]
	 * : Read Airtable now, and store the figures when every guard holds.
	 *
	 * [--dry-run]
	 * : With --refresh: print the counts, graduates (G) and dropouts (D), and for each survey question the answers of Likely (P) of every answer to it (N), and the name the base gives each of the four field IDs, and store nothing.
	 *
	 * [--accept]
	 * : With --refresh: store the figures although they fell or moved more than the guards allow, once a person has checked them. A count of zero is never stored.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits outcome_figures
	 *     wp wpcredits outcome_figures --refresh --dry-run
	 *     wp wpcredits outcome_figures --refresh
	 *     wp wpcredits outcome_figures --refresh --accept
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function outcome_figures( $args, $assoc_args ) {
		unset( $args );

		$refresh = ! empty( $assoc_args['refresh'] );
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$accept  = ! empty( $assoc_args['accept'] );

		if ( ( $dry_run || $accept ) && ! $refresh ) {
			WP_CLI::error( '--dry-run and --accept go with --refresh.' );
		}

		if ( $dry_run && $accept ) {
			WP_CLI::error( 'Use --dry-run or --accept, not both.' );
		}

		if ( ! $refresh ) {
			$this->outcome_figures_stored();

			return;
		}

		$run = WPCPM_Outcome_Figures::refresh(
			array(
				'dry_run' => $dry_run,
				'accept'  => $accept,
			)
		);

		if ( 'not_connected' === $run['result'] ) {
			WP_CLI::error( 'Airtable is not configured.' );
		}

		if ( in_array( $run['result'], array( 'busy', 'failed' ), true ) ) {
			WP_CLI::error( $run['message'] );
		}

		$this->outcome_figures_counts( $run );

		if ( $dry_run ) {
			$names = WPCPM_Outcome_Figures::field_names();

			if ( is_wp_error( $names ) ) {
				WP_CLI::warning( sprintf( 'The base\'s names for the four fields could not be read: %s', $names->get_error_message() ) );
			} else {
				WP_CLI::log( 'The base\'s names for the four field IDs:' );

				foreach ( $names as $id => $name ) {
					WP_CLI::log( sprintf( '  %-20s %s', $id, '' === $name ? '(not in its table)' : $name ) );
				}
			}

			WP_CLI::success( 'Dry run complete - nothing was stored.' );

			return;
		}

		if ( 'held' === $run['result'] ) {
			// A count of zero is held whatever the flag, so --accept is no way out of it.
			$next = 'zero' === $run['reason']
				? ' Nothing was stored. --accept never stores a count of zero; a zero usually means a renamed status or answer, so check the base with --refresh --dry-run.'
				: ' Nothing was stored. Once the counts above are checked, --refresh --accept stores them.';

			WP_CLI::error( $run['message'] . $next );
		}

		WP_CLI::success( __( 'The outcome figures are stored.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The stored set, what the homepage may show of it, and the last attempt.
	 */
	private function outcome_figures_stored() {
		$set   = WPCPM_Outcome_Figures::stored();
		$shown = WPCPM_Outcome_Figures::published();
		$last  = WPCPM_Outcome_Figures::status();

		if ( array() === $set ) {
			WP_CLI::log( 'No figures are stored yet.' );
		} else {
			WP_CLI::log( sprintf( 'Stored, read %s UTC:', gmdate( 'Y-m-d H:i', (int) $set['read'] ) ) );

			foreach ( WPCPM_Outcome_Figures::FIGURES as $key ) {
				WP_CLI::log( sprintf( '  %-11s %3d%%, %d of %d', $key, $set[ $key ]['share'], $set[ $key ]['count'], $set[ $key ]['n'] ) );
			}
		}

		WP_CLI::log( sprintf( 'Shown on the homepage: %s', array() === $shown ? 'none' : implode( ', ', array_keys( $shown['figures'] ) ) ) );

		if ( array() !== $last ) {
			// The run option is read as the syncs row reads it: a part missing, or not the type a run
			// writes (the option edited by hand, say), is left out rather than printed as a warning.
			$time    = isset( $last['time'] ) && is_int( $last['time'] ) ? $last['time'] : 0;
			$result  = is_string( $last['result'] ) ? $last['result'] : 'unknown';
			$message = isset( $last['message'] ) && is_string( $last['message'] ) ? $last['message'] : '';

			WP_CLI::log( sprintf( 'Last attempt%s: %s%s', $time > 0 ? ', ' . gmdate( 'Y-m-d H:i', $time ) . ' UTC' : '', $result, '' === $message ? '' : ' - ' . $message ) );
		}

		WP_CLI::success( 'Nothing was read or stored.' );
	}

	/**
	 * What a read counted: the four counts of the parity check, and each share.
	 *
	 * @param array $run What `WPCPM_Outcome_Figures::refresh()` returned.
	 */
	private function outcome_figures_counts( array $run ) {
		$graduation = $run['figures']['graduation'];

		WP_CLI::log( sprintf( 'Students Reports: %d rows read', $run['rows']['reports'] ) );
		WP_CLI::log( sprintf( '  Graduates (G):           %d', $graduation['count'] ) );
		WP_CLI::log( sprintf( '  Dropped out (D):         %d', $graduation['n'] - $graduation['count'] ) );
		WP_CLI::log( sprintf( '  Graduation share:        %s of %d', $this->outcome_share( $graduation['share'] ), $graduation['n'] ) );
		WP_CLI::log( sprintf( 'Feedback: %d rows read (P: the answers of Likely, N: every answer to the question)', $run['rows']['feedback'] ) );

		$questions = array(
			'recommend' => 'Would recommend',
			'keep'      => 'Keep contributing',
		);

		foreach ( $questions as $key => $label ) {
			$figure = $run['figures'][ $key ];

			WP_CLI::log( sprintf( '  %-24s P %d of N %d, %s', $label . ':', $figure['count'], $figure['n'], $this->outcome_share( $figure['share'] ) ) );
		}
	}

	/**
	 * A share as the command prints it.
	 *
	 * @param int|null $share The share, or null when nothing was counted.
	 * @return string
	 */
	private function outcome_share( $share ) {
		return null === $share ? 'no share' : $share . '%';
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
