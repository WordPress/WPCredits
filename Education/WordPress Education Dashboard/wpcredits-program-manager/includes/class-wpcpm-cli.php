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
	 * [--accept]
	 * : Run past the count check: take the Mentor role from every linked account the run did not read, even when Airtable returned under half as many mentors as there are linked accounts. For this run only, once the base has been checked.
	 *
	 * [--restart]
	 * : Start again from the beginning while a sync is already running. Without it the command refuses a sync that is running and has not stalled.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits sync_mentors
	 *     wp wpcredits sync_mentors --dry-run
	 *     wp wpcredits sync_mentors --accept
	 *     wp wpcredits sync_mentors --restart
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

		// There is no browser here to drive the ticks, so the CLI runs them itself. A run already
		// going is not started over unless asked (1.123.58): the command says how.
		$started = WPCPM_Mentors_Sync::start( true, ! empty( $assoc_args['accept'] ), ! empty( $assoc_args['restart'] ) );

		if ( is_wp_error( $started ) && 'wpcpm_sync_running' === $started->get_error_code() ) {
			WP_CLI::error( __( 'A sync is already running. Run the command with --restart to start it again from the beginning.', 'wpcredits-program-manager' ) );
		}

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
			if ( $error && ! WPCPM_Mentors_Sync::is_stopped() ) {
				WP_CLI::error( $error );
			}
		}

		// The count check stopped the run before it took anyone's role: said in the command's own
		// words, which name --accept where the Sync tab names Run anyway (1.123.58).
		if ( WPCPM_Mentors_Sync::is_stopped() ) {
			$stop = WPCPM_Mentors_Sync::progress()['guard'];

			WP_CLI::error( WPCPM_Sync_Guard::command_sentence( (int) $stop['read'], (int) $stop['linked'] ) );
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

		// The lookup the abilities share: a login, then an address, then a WordPress.org profile address.
		$user = WPCPM_Mentors_Dashboard::find_mentor( $needle );

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
	 * Checks every mentor application selected on this site, and every mentor holding the source
	 * status in Airtable (while Airtable is connected and Stop checking mentors in Airtable is not
	 * ticked), against their WordPress.org profile for the mentor's course completion. Reports only
	 * unless --promote is given, because promoting writes to the shared Airtable base and, where Make
	 * mentors from applications is on, makes mentors on this site.
	 *
	 * ## OPTIONS
	 *
	 * [--promote]
	 * : Also move mentors who qualify to the target status in Airtable, and make a mentor of each
	 *   selected application whose course shows (when Make mentors from applications is on).
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

		$promote   = ! empty( $assoc_args['promote'] );
		$began     = time();
		$settings  = WPCPM_Mentor_Checker::config();
		$runner    = new WPCPM_Mentor_Checker_Runner( $settings );
		$seen      = 0;
		$connected = WPCPM_Mentor_Checker::is_configured();
		$reads     = $connected && empty( $settings['skip_airtable'] );
		$how       = $promote ? sprintf( ' - will promote to "%s".', $settings['target_status'] ) : ' - report only.';

		// The sources the run reads, said first: the site's applications always, Airtable's mentors while
		// it is connected and nobody has stopped checking them.
		if ( $reads ) {
			WP_CLI::log( sprintf( 'Checking the applications selected on this site and the Airtable mentors with status "%1$s" for "%2$s"%3$s', $settings['source_status'], $settings['course_title'], $how ) );
		} else {
			WP_CLI::log( sprintf( 'Checking the applications selected on this site for "%1$s"%2$s', $settings['course_title'], $how ) );
		}

		if ( ! $connected ) {
			WP_CLI::log( 'Airtable is not connected: only the applications selected on this site are checked.' );
		}

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
						'' !== WPCPM_Mentor_Checker_Runner::note_of( $row ) ? WPCPM_Mentor_Checker_Runner::note_of( $row ) : ( isset( $row['message'] ) ? $row['message'] : '' )
					)
				);
			}
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		// Airtable's mentors were left out because it could not be read: the counts below are the site's.
		if ( ! empty( $result['airtable_error'] ) ) {
			WP_CLI::warning( sprintf( 'Airtable could not be read, so its mentors were left out of this run: %s', $result['airtable_error'] ) );
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
	 * Fill, once, what the Institutions screens read from the site instead of Airtable.
	 *
	 * Gives each country its contact's Administrator as the area (the regions stay empty: move Administrators
	 * to regions on the Institutions settings afterwards), keeps each Administrator's booking link, assigns
	 * each institution to its country's Administrator or else to the one its Original program manager column
	 * names, and adds each school's tutors from the Tutors table. It never replaces what the site holds
	 * already (areas, a booking link, an assignee, a listed tutor), sends no email, and reads Airtable only
	 * for three tables. Run it with --dry-run first: the summary names each school it cannot assign and why.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print what would be written, and write nothing.
	 *
	 * [--yes]
	 * : Write without asking first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits institutions_seed --dry-run
	 *     wp wpcredits institutions_seed
	 *     wp wpcredits institutions_seed --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function institutions_seed( $args, $assoc_args ) {
		unset( $args );

		$plan = WPCPM_Institution_Seed::plan( $this->seed_client() );

		if ( is_wp_error( $plan ) ) {
			WP_CLI::error( $plan->get_error_message() );
		}

		foreach ( WPCPM_Institution_Seed::summary( $plan ) as $line ) {
			WP_CLI::log( $line );
		}

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			WP_CLI::success( __( 'Dry run: nothing was written.', 'wpcredits-program-manager' ) );

			return;
		}

		WP_CLI::confirm( __( 'Write this to the site?', 'wpcredits-program-manager' ), $assoc_args );

		$done     = WPCPM_Institution_Seed::apply( $plan );
		$assigned = $done['assigned_area'] + $done['assigned_original'];
		$written  = sprintf(
			/* translators: 1: countries given an Administrator, 2: booking links added, 3: institutions assigned, 4: tutors added; each a phrase with its number. */
			__( 'Written: %1$s; %2$s; %3$s; %4$s.', 'wpcredits-program-manager' ),
			/* translators: %d: how many countries. */
			sprintf( _n( '%d country is given an Administrator', '%d countries are given an Administrator', $done['areas'], 'wpcredits-program-manager' ), $done['areas'] ),
			/* translators: %d: how many booking links. */
			sprintf( _n( '%d booking link is added', '%d booking links are added', $done['booking'], 'wpcredits-program-manager' ), $done['booking'] ),
			/* translators: %d: how many institutions. */
			sprintf( _n( '%d institution is assigned', '%d institutions are assigned', $assigned, 'wpcredits-program-manager' ), $assigned ),
			/* translators: %d: how many tutors. */
			sprintf( _n( '%d tutor is added', '%d tutors are added', $done['tutors'], 'wpcredits-program-manager' ), $done['tutors'] )
		);

		if ( array() !== $done['failed'] ) {
			WP_CLI::log( $written );
			WP_CLI::error(
				sprintf(
					/* translators: %s: one sentence for each thing that was not written, run together. */
					__( 'Not everything was written. %s', 'wpcredits-program-manager' ),
					implode( ' ', $done['failed'] )
				)
			);
		}

		WP_CLI::success( $written );
	}

	/**
	 * The Airtable client the seed reads through, or the command's error when Airtable is not connected.
	 *
	 * Not a command: it is not public, and a suite stands it in.
	 *
	 * @return WPCPM_Airtable
	 */
	protected function seed_client() {
		if ( ! WPCPM_Settings::is_connected() ) {
			WP_CLI::error( 'Airtable is not configured.' );
		}

		return new WPCPM_Airtable( WPCPM_Settings::get() );
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
	 * Make the Start here page and the Team Matcher page, as the first-week checklist's switch does.
	 *
	 * A page that already exists, at its slug or in its option, is kept as it is. The Team Matcher page
	 * is made only while the Find Your Team plugin is active.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits start_pages
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function start_pages( $args, $assoc_args ) {
		$ids     = WPCPM_Start_Pages::ensure_pages();
		$start   = WPCPM_Start_Pages::start_url();
		$matcher = WPCPM_Start_Pages::matcher_url();

		if ( 0 < $ids['start'] ) {
			WP_CLI::log( sprintf( 'Start here: page %1$d, %2$s', $ids['start'], $start ? $start : 'not published' ) );
		} else {
			WP_CLI::warning( 'The Start here page could not be made.' );
		}

		if ( 0 < $ids['matcher'] ) {
			WP_CLI::log( sprintf( 'Team Matcher: page %1$d, %2$s', $ids['matcher'], $matcher ? $matcher : 'not published' ) );
		} else {
			WP_CLI::log( 'Team Matcher: no page, because the Find Your Team plugin is not active. Run this again once it is.' );
		}

		if ( 0 < $ids['start'] ) {
			WP_CLI::success( 'The start pages are in place.' );
		}
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
	 * Show how each mentor uses the site: sign-ins, visits, students, schedule, notes and calls.
	 *
	 * Reads only. The figures are the Mentors screen's Engagement tab's and the Administrator
	 * Dashboard's Mentor engagement card's, counted the same way. The per-row dates are UTC.
	 *
	 * ## OPTIONS
	 *
	 * [--summary]
	 * : Print the headline figures instead of one row per mentor.
	 *
	 * [--view=<view>]
	 * : One of the Engagement tab's views: all, active, students, never, quiet, idle, no-hours, staff. Default: all.
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default: table.
	 *
	 * [--now=<time>]
	 * : Count as of this Unix time (a positive whole number) instead of now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits mentor_engagement --summary
	 *     wp wpcredits mentor_engagement --summary --format=json
	 *     wp wpcredits mentor_engagement --view=never --format=csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function mentor_engagement( $args, $assoc_args ) {
		unset( $args );

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$view   = isset( $assoc_args['view'] ) ? (string) $assoc_args['view'] : WPCPM_Mentor_Engagement::VIEW_ALL;
		$views  = WPCPM_Mentor_Engagement::views();

		if ( ! in_array( $format, array( 'table', 'json', 'csv' ), true ) ) {
			WP_CLI::error( 'Use --format=table, json or csv.' );
		}

		if ( ! isset( $views[ $view ] ) ) {
			WP_CLI::error( sprintf( 'There is no view "%1$s". Use one of: %2$s.', $view, implode( ', ', array_keys( $views ) ) ) );
		}

		$now = 0;

		if ( isset( $assoc_args['now'] ) ) {
			$given = $assoc_args['now'];

			if ( ! ( is_int( $given ) || is_string( $given ) ) || ! ctype_digit( (string) $given ) || (int) $given <= 0 ) {
				WP_CLI::error( 'Use --now=<Unix time>.' );
			}

			$now = (int) $given;
		}

		if ( ! empty( $assoc_args['summary'] ) && isset( $assoc_args['view'] ) ) {
			WP_CLI::warning( '--view is ignored with --summary.' );
		}

		$report = WPCPM_Mentor_Engagement::report( $now );

		if ( ! empty( $assoc_args['summary'] ) ) {
			if ( 'json' === $format ) {
				WP_CLI::line( (string) wp_json_encode( $report['summary'] ) );

				return;
			}

			$items = array();

			foreach ( $report['summary'] as $figure => $value ) {
				$items[] = array(
					'figure' => $figure,
					'value'  => $value,
				);
			}

			WP_CLI\Utils\format_items( $format, $items, array( 'figure', 'value' ) );

			return;
		}

		$rows = array();

		foreach ( WPCPM_Mentor_Engagement::sorted( $report['rows'] ) as $row ) {
			if ( WPCPM_Mentor_Engagement::in_view( $row, $view ) ) {
				$rows[] = WPCPM_Mentor_Engagement::export_row( $row );
			}
		}

		WP_CLI\Utils\format_items( $format, $rows, WPCPM_Mentor_Engagement::EXPORT_FIELDS );
	}

	/**
	 * List the latest calls Claude access has logged, newest first.
	 *
	 * Reads only. The rows are the Latest calls table's on Settings > Security, which shows the newest
	 * hundred; this lists up to a thousand. Every call to an ability is a row, answered, refused or failed: the
	 * account that asked, the name of the application password it used, the ability (empty for a call the
	 * door turned away before any ability was named), the outcome and, for a refusal or a failure, the
	 * reason, how many rows of the program's records went out, and the milliseconds the answer took. The
	 * times are the site's own, in its date and time formats. JSON and CSV also say what was asked (input,
	 * as JSON) and which records went out (records, their IDs), after the reason; the table does not, to
	 * stay narrow. A row older than the Keep the log for setting is deleted by the daily sweep.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : How many calls at most, from 1 to 1000; a number outside that is held to the nearest end of it. Default: 100.
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default: table. JSON and CSV add two columns after reason: input (what was asked, as JSON) and records (the IDs of the records that went out).
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits mcp_log
	 *     wp wpcredits mcp_log --limit=500 --format=csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function mcp_log( $args, $assoc_args ) {
		unset( $args );

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$limit  = 100;

		if ( ! in_array( $format, array( 'table', 'json', 'csv' ), true ) ) {
			WP_CLI::error( 'Use --format=table, json or csv.' );
		}

		if ( isset( $assoc_args['limit'] ) ) {
			$given = $assoc_args['limit'];

			if ( ! ( is_int( $given ) || is_string( $given ) ) || 1 !== preg_match( '/^-?\d+$/', (string) $given ) ) {
				WP_CLI::error( 'Use --limit=<a whole number from 1 to 1000>.' );
			}

			$limit = max( 1, min( 1000, (int) $given ) );
		}

		// The table is eight columns wide. JSON and CSV are read by a program or a spreadsheet, which has room
		// for the two that say what was asked and what went out, right after the reason.
		$detail = 'table' !== $format;
		$fields = $detail
			? array( 'when', 'account', 'password', 'ability', 'outcome', 'reason', 'input', 'records', 'rows', 'ms' )
			: array( 'when', 'account', 'password', 'ability', 'outcome', 'reason', 'rows', 'ms' );
		$rows   = array();

		foreach ( WPCPM_Institution_Audit::mcp_entries( $limit ) as $entry ) {
			$data = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : array();
			$user = get_userdata( (int) $entry['actor'] );

			$row = array(
				'when'     => WPCPM_Dates::moment( $entry['time'] ),
				'account'  => $user ? (string) $user->user_login : 'A removed account',
				'password' => isset( $data['password'] ) ? (string) $data['password'] : '',
				'ability'  => isset( $data['ability'] ) ? (string) $data['ability'] : '',
				'outcome'  => isset( $data['outcome'] ) ? (string) $data['outcome'] : '',
				'reason'   => isset( $data['reason'] ) ? (string) $data['reason'] : '',
				'input'    => array_key_exists( 'input', $data ) ? (string) wp_json_encode( $data['input'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '',
				'records'  => isset( $data['records'] ) && is_array( $data['records'] ) ? implode( ', ', array_map( 'strval', $data['records'] ) ) : '',
				'rows'     => isset( $data['rows'] ) ? (int) $data['rows'] : 0,
				'ms'       => isset( $data['ms'] ) ? (int) $data['ms'] : 0,
			);

			$rows[] = array_intersect_key( $row, array_flip( $fields ) );
		}

		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}

	/**
	 * Read a setting, or change one the way Settings > Save does, and say what changed.
	 *
	 * `get` prints the setting: a switch as on or off, a list as its items joined by a comma and a
	 * space, an empty Who is mailed list as every Administrator account, a secret (the tokens and
	 * the Slack webhook) as the screen masks it, never as stored.
	 *
	 * `set` writes one setting through the same rules the Settings screen saves by, so a value the
	 * rule would blank is refused with the sentence the screen gives, and the setting keeps what was
	 * saved. A switch takes 1, 0, on, off, true or false; a list is comma-separated. For the five Who
	 * is mailed lists, the value everyone writes the list empty, which mails every Administrator
	 * account. A status list a track on the live site runs on is left as it was.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : get or set.
	 *
	 * <key>
	 * : The setting's key, such as mentor_applications_enabled.
	 *
	 * [<value>]
	 * : With set: the new value.
	 *
	 * [--dry-run]
	 * : With set: say what would change and write nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits setting get mentor_applications_enabled
	 *     wp wpcredits setting set mentor_lapse_days 120 --dry-run
	 *     wp wpcredits setting set agreement_notify everyone
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function setting( $args, $assoc_args ) {
		$action   = isset( $args[0] ) ? (string) $args[0] : '';
		$key      = isset( $args[1] ) ? (string) $args[1] : '';
		$defaults = WPCPM_Settings::defaults();
		$dry      = ! empty( $assoc_args['dry-run'] );

		if ( ! in_array( $action, array( 'get', 'set' ), true ) || '' === $key || ( 'set' === $action && ! isset( $args[2] ) ) ) {
			WP_CLI::error( 'Use setting get <key>, or setting set <key> <value>.' );
		}

		if ( ! array_key_exists( $key, $defaults ) ) {
			WP_CLI::error( sprintf( 'There is no setting called %s.', $key ) );
		}

		$before = WPCPM_Settings::get_value( $key, $defaults[ $key ] );
		$was    = $this->setting_shown( $key, $before );

		if ( 'get' === $action ) {
			WP_CLI::line( $was );

			return;
		}

		$typed = (string) $args[2];

		if ( 'everyone' === $typed ) {
			if ( ! in_array( $key, WPCPM_Settings::NOTIFY_LISTS, true ) ) {
				WP_CLI::error( sprintf( 'everyone is a value only for the five Who is mailed lists: %s.', implode( ', ', WPCPM_Settings::NOTIFY_LISTS ) ) );
			}

			$value   = '';
			$changes = array(
				$key                                   => '',
				$key . WPCPM_Settings::EVERYONE_SUFFIX => true,
			);
		} else {
			$value   = $this->setting_value( $defaults[ $key ], $typed );
			$changes = array( $key => $value );
		}

		if ( $value === $before ) {
			WP_CLI::log( sprintf( '%1$s is already %2$s. Nothing was written.', $key, $was ) );

			return;
		}

		$saved   = WPCPM_Settings::patch( $changes, $dry );
		$refused = WPCPM_Settings::last_refused();
		$after   = isset( $saved[ $key ] ) ? $saved[ $key ] : $before;

		if ( isset( $refused[ $key ] ) ) {
			WP_CLI::error( sprintf( '%1$s was not changed: %2$s', $key, $refused[ $key ] ) );
		}

		// The status lists are left as they were when a live track runs on a status the change would take
		// out or make past (`WPCPM_Settings::patch()`): the list that comes back is the one that was there.
		if ( in_array( $key, array( 'student_statuses', 'past_statuses' ), true ) && $after === $before ) {
			WP_CLI::error( sprintf( '%s was left as it was: a track on the live site runs on a status this change would take out, or would make past. Take the track off the live site in the Track Builder first.', $key ) );
		}

		if ( $after === $before ) {
			WP_CLI::log( sprintf( '%1$s is already %2$s. Nothing was written.', $key, $was ) );

			return;
		}

		if ( $dry ) {
			// A secret that is not written is masked by the same rule the screen masks a stored one by.
			WP_CLI::log( sprintf( 'Dry run: %1$s would change from %2$s to %3$s. Nothing was written.', $key, $was, $this->setting_shown( $key, $after, true ) ) );

			return;
		}

		WP_CLI::success( sprintf( 'Changed %1$s from %2$s to %3$s.', $key, $was, $this->setting_shown( $key, $after ) ) );
	}

	/**
	 * A setting's value as a person reads it: a switch as on or off, a list joined, a secret masked.
	 *
	 * @param string $key      The setting.
	 * @param mixed  $value    Its value.
	 * @param bool   $unsaved  Whether the value is one that was not stored, so a secret's mask is made
	 *                         from the value itself rather than read from the settings.
	 * @return string
	 */
	private function setting_shown( $key, $value, $unsaved = false ) {
		$maskers = array(
			'api_token'             => 'masked_token',
			'schema_token'          => 'masked_schema_token',
			'handbook_key'          => 'masked_handbook_key',
			'checker_slack_webhook' => 'masked_slack_webhook',
		);

		if ( isset( $maskers[ $key ] ) ) {
			if ( $unsaved ) {
				return '' === (string) $value ? '' : str_repeat( '•', 12 ) . substr( (string) $value, -4 );
			}

			return (string) call_user_func( array( 'WPCPM_Settings', $maskers[ $key ] ) );
		}

		// An empty Who is mailed list mails every Administrator account, so it is named, not printed
		// as nothing (1.123.58, trial T7).
		if ( in_array( $key, WPCPM_Settings::NOTIFY_LISTS, true ) && '' === trim( is_array( $value ) ? implode( '', array_map( 'strval', $value ) ) : (string) $value ) ) {
			return __( 'every Administrator account', 'wpcredits-program-manager' );
		}

		if ( is_bool( $value ) ) {
			return $value ? 'on' : 'off';
		}

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		return (string) $value;
	}

	/**
	 * What was typed after `setting set <key>`, read as the setting's own type: 1, 0, on, off, true or
	 * false for a switch, a comma-separated list for a list, a number for a number.
	 *
	 * @param mixed  $fallback The setting's default, whose type says what it holds.
	 * @param string $typed   What was typed.
	 * @return mixed
	 */
	private function setting_value( $fallback, $typed ) {
		if ( is_bool( $fallback ) ) {
			$word = strtolower( trim( $typed ) );

			if ( in_array( $word, array( '1', 'on', 'true' ), true ) ) {
				return true;
			}

			if ( in_array( $word, array( '0', 'off', 'false' ), true ) ) {
				return false;
			}

			WP_CLI::error( 'A switch takes 1, 0, on, off, true or false.' );
		}

		if ( is_array( $fallback ) ) {
			return array_values(
				array_filter(
					array_map( 'trim', explode( ',', $typed ) ),
					static function ( $value ) {
						return '' !== $value;
					}
				)
			);
		}

		if ( is_int( $fallback ) && is_numeric( $typed ) ) {
			return (int) $typed;
		}

		return $typed;
	}

	/**
	 * Print the Mentors row an application would write, or check that the base takes a whole row.
	 *
	 * With a reference, prints each cell the application would write to the Mentors table, as the
	 * base's columns take it, and what would be left out, with why when the base's columns could not
	 * be read. It reads Airtable and writes nothing. It prints the applicant's answers, so use it on a
	 * test application, not on a real person's.
	 *
	 * With --check, writes one synthetic row with the test mailbox's address and a Status that the
	 * mentors sync and the Mentor Status Checker do not read, reads it back, deletes it and says which
	 * columns the base kept. A create that failed in transport says it failed, never that Airtable
	 * refused it, since it may have reached the base. Run it before switching Make mentors from
	 * applications on. Nothing on this site changes.
	 *
	 * ## OPTIONS
	 *
	 * [<reference>]
	 * : The application's reference, such as MAPP-2026-0503.
	 *
	 * [--check]
	 * : Write the synthetic row, read it back and delete it.
	 *
	 * [--email=<address>]
	 * : With --check: the test mailbox's address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits mentor_airtable_row MAPP-2026-0503
	 *     wp wpcredits mentor_airtable_row --check --email=owner+check@example.test
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function mentor_airtable_row( $args, $assoc_args ) {
		if ( ! WPCPM_Settings::is_connected() ) {
			WP_CLI::error( 'Airtable is not configured.' );
		}

		if ( ! empty( $assoc_args['check'] ) ) {
			$this->mentor_airtable_check( isset( $assoc_args['email'] ) && is_string( $assoc_args['email'] ) ? trim( $assoc_args['email'] ) : '' );

			return;
		}

		$reference = isset( $args[0] ) ? trim( (string) $args[0] ) : '';

		if ( '' === $reference ) {
			WP_CLI::error( 'Give the application\'s reference, such as MAPP-2026-0503, or use --check.' );
		}

		$post = WPCPM_Mentor_Application::by_reference( $reference );

		if ( ! $post instanceof WP_Post ) {
			WP_CLI::error( sprintf( 'No mentor application has the reference %s.', $reference ) );
		}

		$row = WPCPM_Mentor_Airtable_Row::preview( $post, new WPCPM_Airtable() );

		foreach ( $row['cells'] as $column => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'Yes' : 'No';
			} elseif ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			WP_CLI::log( sprintf( '%s: %s', $column, $value ) );
		}

		// With the reasons: nothing else the preview prints says why the columns could not be read.
		$left_out = WPCPM_Mentor_Airtable_Row::left_out_sentence( $row['left_out'], true );

		if ( '' !== $left_out ) {
			WP_CLI::log( $left_out );
		}

		WP_CLI::success( 'Nothing was written to Airtable.' );
	}

	/**
	 * Write one synthetic Mentors row, read it back and delete it, and say what the base kept.
	 *
	 * The command ends in an error while a row may be left in the Mentors table, and while a column
	 * did not take what the site sends, since either holds the launch.
	 *
	 * @param string $email The test mailbox's address.
	 */
	private function mentor_airtable_check( $email ) {
		if ( '' === $email ) {
			WP_CLI::error( 'Give the test mailbox\'s address as --email=<address>.' );
		}

		// Before anything is sent: a process killed in the middle leaves this line in its output,
		// naming the address to look for in the Mentors table.
		WP_CLI::log( sprintf( 'Writing the check row for %s. If this stops before it ends, look for that address in the Mentors table and delete the row by hand.', $email ) );

		$check  = WPCPM_Mentor_Airtable_Row::check_row( $email, new WPCPM_Airtable() );
		$record = (string) $check['record'];

		if ( '' === $record ) {
			// Nothing sent (an address, the columns, a row already holding the address) says why as it is;
			// a create that was sent and refused, or answered no record, says it was Airtable's; and one
			// that failed in transport, which may have reached Airtable, says only that it failed.
			$sent = array() === $check['found_before'] && array() !== $check['written'];

			if ( $sent && ! empty( $check['transport'] ) ) {
				WP_CLI::error( sprintf( 'The check row\'s create failed: %s', $check['error'] ) );
			}

			WP_CLI::error( $sent ? sprintf( 'Airtable refused the check row: %s', $check['error'] ) : $check['error'] );
		}

		WP_CLI::log( sprintf( 'Written: %s', implode( ', ', $check['written'] ) ) );

		if ( '' === $check['read_error'] ) {
			WP_CLI::log( sprintf( 'Kept by the base: %s', implode( ', ', $check['kept'] ) ) );
		}

		foreach ( $check['notes'] as $note ) {
			WP_CLI::log( sprintf( 'Only left out: %s', $note ) );
		}

		foreach ( $check['stops'] as $stop ) {
			WP_CLI::warning( $stop );
		}

		if ( ! $check['deleted'] ) {
			WP_CLI::error( sprintf( 'The check row %1$s was written but could not be deleted: %2$s Delete it in Airtable by hand.', $record, rtrim( (string) $check['delete_error'], '. ' ) . '.' ) );
		}

		if ( '' !== $check['read_error'] ) {
			WP_CLI::error( sprintf( 'The check row %1$s was written and deleted, but it could not be read back: %2$s Run the check again.', $record, rtrim( (string) $check['read_error'], '. ' ) . '.' ) );
		}

		if ( array() !== $check['stops'] ) {
			WP_CLI::error( sprintf( 'The check row %s was written, read back and deleted, but the live base would not take the whole row. Put that right before switching Make mentors from applications on.', $record ) );
		}

		WP_CLI::success( sprintf( 'The check row %s was written, read back and deleted. Nothing on this site changed.', $record ) );
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
