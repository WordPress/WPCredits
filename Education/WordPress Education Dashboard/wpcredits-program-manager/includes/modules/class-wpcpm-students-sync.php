<?php
/**
 * Students module — Airtable sync.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provisions Student accounts from Airtable and gives each one their own program
 * details plus the mentor assigned to them.
 *
 * Built on the same resumable, budgeted cron machinery as the Mentors sync, for
 * the same reason: several hundred student records, and a WordPress.org profile
 * read for every mentor, is far more than one request can carry.
 *
 * The expensive phase is `mentors`. A mentor's contact details are only partly in
 * Airtable — the Mentors table has a name, an email and a profile URL and nothing
 * else — so their Slack handle, job line, site and teams come from their
 * WordPress.org profile. That is one HTTP request per mentor, so the results are
 * gathered once per run and shared by every student assigned to them.
 */
class WPCPM_Students_Sync {

	const CRON_DAILY = 'wpcpm_students_daily';
	const CRON_TICK  = 'wpcpm_students_sync_tick';

	const OPT_STATE  = 'wpcpm_students_state';
	const OPT_REPORT = 'wpcpm_students_report';
	const OPT_LAST   = 'wpcpm_students_last_sync';
	const OPT_ERROR  = 'wpcpm_students_last_error';
	const OPT_LOCK   = 'wpcpm_students_lock';

	/** User meta. */
	const META_RECORD_ID = 'wpcpm_student_record_id';
	const META_ACTIVE    = 'wpcpm_student_active';
	const META_PROGRAM   = 'wpcpm_student_program';
	const META_MENTOR    = 'wpcpm_student_mentor';
	const META_UPDATED   = 'wpcpm_student_updated';

	const BUDGET       = 18;
	const BUDGET_AJAX  = 8;
	const LOCK_TIMEOUT = 120;

	/**
	 * Phases, in order. See WPCPM_Mentors_Sync::phases() for how the weights are
	 * used; `mentors` is weighted heavily because it is the only phase that leaves
	 * the site.
	 *
	 * @return array<string, array{label: string, weight: int, steps: int}>
	 */
	public static function phases() {
		return array(
			'reports'   => array(
				'label'  => __( 'Reading student records', 'wpcredits-program-manager' ),
				'weight' => 25,
				'steps'  => 6,
			),
			'mentors'   => array(
				'label'  => __( 'Reading mentor contact details', 'wpcredits-program-manager' ),
				'weight' => 40,
				'steps'  => 20,
			),
			'tutors'    => array(
				'label'  => __( 'Reading tutor assignments', 'wpcredits-program-manager' ),
				'weight' => 15,
				'steps'  => 6,
			),
			'provision' => array(
				'label'  => __( 'Creating and updating student accounts', 'wpcredits-program-manager' ),
				'weight' => 20,
				'steps'  => 10,
			),
		);
	}

	/**
	 * Register cron hooks.
	 */
	public static function register_cron() {
		add_action( self::CRON_DAILY, array( __CLASS__, 'cron_daily' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'run_tick' ) );

		self::schedule();
	}

	/**
	 * Ensure the daily event exists.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
			// Offset from the mentors sync so the two never contend for the same
			// Airtable rate limit or the same PHP worker.
			wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'daily', self::CRON_DAILY );
		}
	}

	/**
	 * Drop all scheduled work.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Daily entry point.
	 */
	public static function cron_daily() {
		if ( ! WPCPM_Settings::get_value( 'auto_sync' ) ) {
			return;
		}

		self::start();
	}

	/**
	 * Begin a run.
	 *
	 * @param bool $run_first_tick Process one slice before returning, for WP-CLI.
	 * @return true|WP_Error
	 */
	public static function start( $run_first_tick = false ) {
		if ( ! WPCPM_Settings::is_connected() ) {
			$error = new WP_Error( 'wpcpm_not_connected', __( 'Add an Airtable Personal Access Token and Base ID before syncing.', 'wpcredits-program-manager' ) );
			update_option( self::OPT_ERROR, $error->get_error_message(), false );

			return $error;
		}

		delete_option( self::OPT_LOCK );

		update_option(
			self::OPT_STATE,
			array(
				'phase'    => 'reports',
				'offset'   => null,
				'cursor'   => 0,
				'started'  => time(),
				'touched'  => time(),
				'steps'    => array(),
				'students' => array(),
				'mentors'  => array(),
				'pending'  => array(),
				// Three email-keyed maps from one pass over the Students table; listed together
				// so a later reader does not have to work out why only one of them was declared.
				'tutors'   => array(),
				'study'    => array(),
				'access'   => array(),
				'stats'    => self::empty_stats(),
				'notices'  => array(),
			),
			false
		);

		delete_option( self::OPT_ERROR );

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 10, self::CRON_TICK );
		}

		if ( $run_first_tick ) {
			self::run_tick();
		}

		return true;
	}

	/**
	 * Whether a run is in progress.
	 *
	 * @return bool
	 */
	public static function is_running() {
		$state = get_option( self::OPT_STATE );

		return is_array( $state ) && ! empty( $state['phase'] ) && 'done' !== $state['phase'];
	}

	/**
	 * Abandon a run.
	 */
	public static function cancel() {
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Progress for the admin screen and the AJAX poll.
	 *
	 * @return array
	 */
	public static function progress() {
		$state   = get_option( self::OPT_STATE );
		$state   = is_array( $state ) ? $state : array();
		$phase   = isset( $state['phase'] ) ? (string) $state['phase'] : '';
		$phases  = self::phases();
		$stats   = isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : self::empty_stats();
		$running = self::is_running();

		$order   = array_keys( $phases );
		$index   = array_search( $phase, $order, true );
		$index   = ( false === $index ) ? 0 : (int) $index;
		$started = isset( $state['started'] ) ? (int) $state['started'] : 0;
		$touched = isset( $state['touched'] ) ? (int) $state['touched'] : $started;

		return array(
			'running'    => $running,
			'phase'      => $phase,
			'label'      => isset( $phases[ $phase ]['label'] ) ? $phases[ $phase ]['label'] . '…' : '',
			'detail'     => self::phase_detail( $phase, $stats ),
			'percent'    => self::percent( $state ),
			'step'       => $running ? $index + 1 : count( $order ),
			'step_total' => count( $order ),
			/* translators: 1: current phase number, 2: total number of phases. */
			'step_label' => $running ? sprintf( __( 'Step %1$d of %2$d', 'wpcredits-program-manager' ), $index + 1, count( $order ) ) : '',
			'stats'      => $stats,
			'elapsed'    => $started ? max( 0, time() - $started ) : 0,
			'idle'       => $touched ? max( 0, time() - $touched ) : 0,
			'error'      => (string) get_option( self::OPT_ERROR, '' ),
			'stalled'    => $running && $touched && ( time() - $touched ) > self::LOCK_TIMEOUT,
		);
	}

	/**
	 * Estimated completion percentage.
	 *
	 * @param array $state Sync state.
	 * @return int
	 */
	private static function percent( array $state ) {
		$phase = isset( $state['phase'] ) ? (string) $state['phase'] : '';

		if ( '' === $phase || 'done' === $phase ) {
			return 100;
		}

		$phases = self::phases();

		if ( ! isset( $phases[ $phase ] ) ) {
			return 0;
		}

		$done = 0;
		foreach ( $phases as $slug => $config ) {
			if ( $slug === $phase ) {
				break;
			}
			$done += $config['weight'];
		}

		$steps    = max( 1, (int) $phases[ $phase ]['steps'] );
		$complete = isset( $state['steps'][ $phase ] ) ? (int) $state['steps'][ $phase ] : 0;

		return (int) min( 99, round( $done + ( $phases[ $phase ]['weight'] * min( 0.9, $complete / $steps ) ) ) );
	}

	/**
	 * A live summary of the current phase.
	 *
	 * @param string $phase Phase.
	 * @param array  $stats Statistics.
	 * @return string
	 */
	private static function phase_detail( $phase, array $stats ) {
		$get = static function ( $key ) use ( $stats ) {
			return isset( $stats[ $key ] ) ? (int) $stats[ $key ] : 0;
		};

		switch ( $phase ) {
			case 'reports':
				return sprintf(
					/* translators: %s: number of student records read. */
					__( '%s student records read', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'students_seen' ) )
				);

			case 'mentors':
				return sprintf(
					/* translators: 1: mentors read, 2: how many had a readable profile. */
					__( '%1$s mentors · %2$s WordPress.org profiles read', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'mentors_seen' ) ),
					number_format_i18n( $get( 'profiles_read' ) )
				);

			case 'tutors':
				return __( 'Joining tutors to students by email…', 'wpcredits-program-manager' );

			case 'provision':
				return sprintf(
					/* translators: 1: accounts created, 2: accounts linked or refreshed. */
					__( '%1$s created · %2$s linked', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'created' ) ),
					number_format_i18n( $get( 'linked' ) + $get( 'updated' ) )
				);
		}

		return '';
	}

	/**
	 * Claim the right to run a tick.
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		if ( add_option( self::OPT_LOCK, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( self::OPT_LOCK );

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		update_option( self::OPT_LOCK, time(), false );

		return true;
	}

	/**
	 * Release the tick lock.
	 */
	private static function release_lock() {
		delete_option( self::OPT_LOCK );
	}

	/**
	 * Process one slice of work.
	 *
	 * @param int|null $budget Seconds of work to attempt.
	 */
	public static function run_tick( $budget = null ) {
		$state = get_option( self::OPT_STATE );

		if ( ! is_array( $state ) || empty( $state['phase'] ) || 'done' === $state['phase'] ) {
			return;
		}

		if ( ! self::acquire_lock() ) {
			return;
		}

		$budget   = ( null === $budget ) ? self::BUDGET : max( 1, (int) $budget );
		$deadline = microtime( true ) + $budget;
		$airtable = new WPCPM_Airtable();
		$settings = WPCPM_Settings::get();

		while ( microtime( true ) < $deadline && 'done' !== $state['phase'] ) {
			$before = $state['phase'];

			switch ( $state['phase'] ) {
				case 'reports':
					$result = self::phase_reports( $state, $airtable, $settings );
					break;
				case 'mentors':
					$result = self::phase_mentors( $state, $airtable, $settings );
					break;
				case 'tutors':
					$result = self::phase_tutors( $state, $airtable, $settings );
					break;
				case 'provision':
					$result = self::phase_provision( $state, $settings );
					break;
				default:
					$state['phase'] = 'done';
					$result         = true;
					break;
			}

			if ( ! isset( $state['steps'][ $before ] ) ) {
				$state['steps'][ $before ] = 0;
			}
			++$state['steps'][ $before ];
			$state['touched'] = time();

			if ( is_wp_error( $result ) ) {
				update_option( self::OPT_ERROR, $result->get_error_message(), false );
				update_option( self::OPT_STATE, $state, false );
				self::release_lock();

				return;
			}

			update_option( self::OPT_STATE, $state, false );
		}

		self::release_lock();

		if ( 'done' === $state['phase'] ) {
			self::finish( $state );

			return;
		}

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_TICK );
		}
	}

	/**
	 * Phase 1 — page through the student records in scope.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_reports( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$fields   = WPCPM_Mentors_Sync::fields();
		$statuses = WPCPM_Mentors_Sync::tracked_statuses( $settings );

		$page = $airtable->fetch_page(
			$settings['reports_table'],
			array(
				'formula' => $airtable->formula_in( $fields['report_status'], $statuses['all'] ),
				'fields'  => array(
					$fields['report_name'],
					$fields['report_email'],
					$fields['report_status'],
					$fields['report_mentor'],
					$fields['report_instituton'],
					$fields['report_profile'],
					$fields['report_slack'],
					$fields['report_team'],
					$fields['report_website'],
					$fields['report_start'],
					$fields['report_end'],
					$fields['report_link'],
					$fields['report_link_50h'],
				),
				'offset'  => $state['offset'],
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		foreach ( $page['records'] as $record ) {
			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

			$read = static function ( $key ) use ( $cells, $fields ) {
				return WPCPM_Airtable::flatten( isset( $cells[ $fields[ $key ] ] ) ? $cells[ $fields[ $key ] ] : '' );
			};

			$status  = $read( 'report_status' );
			$is_50h  = ( false !== stripos( $status, '50h' ) );
			$email   = $read( 'report_email' );
			$profile = $read( 'report_profile' );

			$mentor_ids = WPCPM_Airtable::link_ids( isset( $cells[ $fields['report_mentor'] ] ) ? $cells[ $fields['report_mentor'] ] : array() );

			$state['students'][] = array(
				'record_id'      => isset( $record['id'] ) ? (string) $record['id'] : '',
				'name'           => trim( $read( 'report_name' ) ),
				'email'          => $email,
				'email_key'      => strtolower( trim( $email ) ),
				// The track is the program: there is no separate Program column in
				// Airtable, and the status is also what decides which reporting form
				// applies.
				'program'        => $status,
				'is_50h'         => $is_50h,
				'is_past'        => in_array( $status, $statuses['past'], true ),
				'start'          => $read( 'report_start' ),
				'end'            => $read( 'report_end' ),
				'institution'    => WPCPM_Mentors_Sync::resolve_stored(
					WPCPM_Airtable::flatten( isset( $cells[ $fields['report_instituton'] ] ) ? $cells[ $fields['report_instituton'] ] : '' ),
					'institutions'
				),
				'profile'        => $profile,
				'username'       => WPCPM_Mentors_Sync::wporg_username( $profile ),
				'slack'          => $read( 'report_slack' ),
				'team'           => WPCPM_Mentors_Sync::resolve_stored(
					WPCPM_Airtable::flatten( isset( $cells[ $fields['report_team'] ] ) ? $cells[ $fields['report_team'] ] : '' ),
					'teams'
				),
				'website'        => $read( 'report_website' ),
				'link'           => $is_50h ? $read( 'report_link_50h' ) : $read( 'report_link' ),
				// Filled by the email-keyed pass over the Students table, below. Declared here so
				// the row's shape is the same whether or not that pass found anything.
				'tutor'          => '',
				'field_of_study' => '',
				'accessibility'  => '',
				'mentor_id'      => ! empty( $mentor_ids ) ? $mentor_ids[0] : '',
			);

			++$state['stats']['students_seen'];

			// Collect the mentors whose profiles the next phase has to read.
			if ( ! empty( $mentor_ids ) && ! in_array( $mentor_ids[0], $state['pending'], true ) ) {
				$state['pending'][] = $mentor_ids[0];
			}
		}

		$state['offset'] = $page['offset'];

		if ( empty( $page['offset'] ) ) {
			$state['phase']  = 'mentors';
			$state['offset'] = null;
			$state['cursor'] = 0;
		}

		return true;
	}

	/**
	 * Phase 2 — build each mentor's contact card.
	 *
	 * Airtable gives a name, an email and a profile URL. Everything else a student
	 * would use to reach their mentor — Slack handle, job line, site, teams — comes
	 * from the WordPress.org profile, one request each. A handful per slice keeps
	 * any single tick short.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_mentors( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$pending = isset( $state['pending'] ) ? array_values( (array) $state['pending'] ) : array();

		if ( empty( $pending ) ) {
			$state['phase']  = 'tutors';
			$state['offset'] = null;

			return true;
		}

		$fields = WPCPM_Mentors_Sync::fields();
		$batch  = array_splice( $pending, 0, 4 );

		foreach ( $batch as $record_id ) {
			$record = $airtable->get_record( $settings['mentors_table'], $record_id );

			if ( is_wp_error( $record ) ) {
				// One unreadable mentor should not stop the run; the students who
				// have them keep everything else and lose only the contact card.
				$state['notices'][] = sprintf(
					/* translators: 1: Airtable record ID, 2: error message. */
					__( 'Could not read mentor %1$s: %2$s', 'wpcredits-program-manager' ),
					$record_id,
					$record->get_error_message()
				);
				continue;
			}

			$cells = isset( $record['fields'] ) ? (array) $record['fields'] : array();

			$name    = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_name'] ] ) ? $cells[ $fields['mentor_name'] ] : '' ) );
			$email   = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_email'] ] ) ? $cells[ $fields['mentor_email'] ] : '' ) );
			$profile = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_profile'] ] ) ? $cells[ $fields['mentor_profile'] ] : '' ) );
			$user    = WPCPM_Mentors_Sync::wporg_username( $profile );

			$card = array(
				'record_id'     => $record_id,
				'name'          => $name,
				'email'         => $email,
				'username'      => $user,
				'profile'       => '' !== $user ? 'https://profiles.wordpress.org/' . rawurlencode( $user ) . '/' : $profile,
				'slack'         => '',
				'jobline'       => '',
				'location'      => '',
				'website'       => '',
				'website_label' => '',
				'github'        => '',
				'teams'         => array(),
				'avatar'        => '',
			);

			$read = ( '' !== $user ) ? WPCPM_WPorg_Profile::get( $user ) : null;

			if ( is_array( $read ) ) {
				++$state['stats']['profiles_read'];

				// Airtable is authoritative for name and email — a program manager
				// maintains those. The profile only fills what Airtable has no column
				// for, and never overwrites a value already present.
				$card['name']          = '' !== $card['name'] ? $card['name'] : $read['name'];
				$card['slack']         = $read['slack'];
				$card['jobline']       = $read['jobline'];
				$card['location']      = $read['location'];
				$card['website']       = $read['website'];
				$card['website_label'] = $read['website_label'];
				$card['github']        = $read['github'];
				$card['teams']         = $read['teams'];
				$card['avatar']        = $read['avatar'];
			} elseif ( '' !== $user ) {
				$state['notices'][] = sprintf(
					/* translators: %s: WordPress.org username. */
					__( 'No readable WordPress.org profile for mentor @%s, so their Slack handle and site are missing.', 'wpcredits-program-manager' ),
					$user
				);
			}

			$state['mentors'][ $record_id ] = $card;
			++$state['stats']['mentors_seen'];
		}

		$state['pending'] = $pending;

		if ( empty( $pending ) ) {
			$state['phase']  = 'tutors';
			$state['offset'] = null;
		}

		return true;
	}

	/**
	 * Phase 3 — the tutor join, on email, exactly as the Mentors sync does it.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_tutors( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$fields = WPCPM_Mentors_Sync::fields();

		$page = $airtable->fetch_page(
			$settings['students_table'],
			array(
				'fields' => array(
					$fields['student_email'],
					$fields['student_tutor'],
					$fields['student_tutors'],
					$fields['student_study'],
					$fields['student_access'],
				),
				'offset' => $state['offset'],
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		foreach ( $page['records'] as $record ) {
			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();
			$email = strtolower( trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['student_email'] ] ) ? $cells[ $fields['student_email'] ] : '' ) ) );

			if ( '' === $email ) {
				continue;
			}

			$tutor = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['student_tutor'] ] ) ? $cells[ $fields['student_tutor'] ] : '' ) );

			if ( '' === $tutor ) {
				$tutor = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['student_tutors'] ] ) ? $cells[ $fields['student_tutors'] ] : '' ) );
			}

			if ( '' !== $tutor ) {
				$state['tutors'][ $email ] = $tutor;
			}

			// Both live only in the Students table, so this email-keyed pass is the only
			// place they can be picked up. Parallel maps, so a run already in flight when
			// this shipped keeps working — a missing key reads as absent.
			$study = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['student_study'] ] ) ? $cells[ $fields['student_study'] ] : '' ) );

			if ( '' !== $study ) {
				$state['study'][ $email ] = $study;
			}

			$access = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['student_access'] ] ) ? $cells[ $fields['student_access'] ] : '' ) );

			if ( '' !== $access ) {
				$state['access'][ $email ] = $access;
			}
		}

		$state['offset'] = $page['offset'];

		if ( empty( $page['offset'] ) ) {
			$state['phase']  = 'provision';
			$state['offset'] = null;
			$state['cursor'] = 0;
		}

		return true;
	}

	/**
	 * Phase 4 — create or update each student's account and write their details.
	 *
	 * Resumable by index: creating a few hundred users is well past one request,
	 * and a tick that runs out mid-list must carry on where it stopped rather than
	 * start again.
	 *
	 * @param array $state    Sync state, by reference.
	 * @param array $settings Plugin settings.
	 * @return true
	 */
	private static function phase_provision( array &$state, array $settings ) {
		$students = isset( $state['students'] ) ? (array) $state['students'] : array();
		$cursor   = isset( $state['cursor'] ) ? (int) $state['cursor'] : 0;
		$slice    = array_slice( $students, $cursor, 15, true );

		if ( empty( $slice ) ) {
			self::revoke_departed( $state, $settings );

			$state['phase'] = 'done';

			return true;
		}

		foreach ( $slice as $index => $student ) {
			$state['cursor'] = $index + 1;

			if ( '' === $student['record_id'] ) {
				continue;
			}

			// Past students keep the account they already have but are not newly
			// provisioned: an account is for someone who needs to sign in.
			$user_id = self::provision_student( $student, $state, ! $student['is_past'] );

			if ( ! $user_id ) {
				continue;
			}

			if ( isset( $state['tutors'][ $student['email_key'] ] ) ) {
				$student['tutor'] = $state['tutors'][ $student['email_key'] ];
			}

			if ( isset( $state['study'][ $student['email_key'] ] ) ) {
				$student['field_of_study'] = $state['study'][ $student['email_key'] ];
			}

			if ( isset( $state['access'][ $student['email_key'] ] ) ) {
				$student['accessibility'] = $state['access'][ $student['email_key'] ];
			}

			$mentor = ( '' !== $student['mentor_id'] && isset( $state['mentors'][ $student['mentor_id'] ] ) )
				? $state['mentors'][ $student['mentor_id'] ]
				: array();

			if ( empty( $mentor ) ) {
				++$state['stats']['no_mentor'];
			}

			$program = $student;
			unset( $program['email_key'], $program['mentor_id'] );

			update_user_meta( $user_id, self::META_RECORD_ID, $student['record_id'] );
			update_user_meta( $user_id, self::META_ACTIVE, $student['is_past'] ? 0 : 1 );
			update_user_meta( $user_id, self::META_PROGRAM, $program );
			update_user_meta( $user_id, self::META_MENTOR, $mentor );
			update_user_meta( $user_id, self::META_UPDATED, time() );

			++$state['stats']['assigned'];
		}

		return true;
	}

	/**
	 * Create or link the WordPress account for one student.
	 *
	 * @param array $student Student row.
	 * @param array $state   Sync state, by reference.
	 * @param bool  $may_create Whether a missing account may be created.
	 * @return int User ID, or 0.
	 */
	private static function provision_student( array $student, array &$state, $may_create = true ) {
		$email = sanitize_email( $student['email'] );

		// Identify by the record we stored, then by email. Never by login: student
		// logins are derived from an email address when there is no WordPress.org
		// profile, which is far too weak a signal to claim an existing account on.
		$user = self::find_by_record_id( $student['record_id'] );

		if ( ! $user && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
		}

		if ( $user instanceof WP_User ) {
			$claimed = (string) get_user_meta( $user->ID, self::META_RECORD_ID, true );

			if ( '' !== $claimed && $claimed !== $student['record_id'] ) {
				$state['notices'][] = sprintf(
					/* translators: 1: student name, 2: WordPress username. */
					__( 'Skipped %1$s — the account "%2$s" is already linked to a different student record.', 'wpcredits-program-manager' ),
					$student['name'] ? $student['name'] : $student['record_id'],
					$user->user_login
				);
				++$state['stats']['conflicts'];

				return 0;
			}

			$was_linked = ( '' !== $claimed );

			// add_role(), and never on an administrator: the same rule the Mentors
			// sync follows, for the same reason.
			if ( ! in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true )
				&& ! in_array( WPCPM_Roles::ROLE_STUDENT, (array) $user->roles, true )
				&& ! $student['is_past'] ) {
				$user->add_role( WPCPM_Roles::ROLE_STUDENT );
			}

			if ( $was_linked ) {
				++$state['stats']['updated'];
			} else {
				++$state['stats']['linked'];
			}

			return $user->ID;
		}

		if ( ! $may_create ) {
			return 0;
		}

		if ( ! is_email( $email ) ) {
			$state['notices'][] = sprintf(
				/* translators: %s: student name. */
				__( 'Skipped %s — Airtable has no valid email address, and WordPress cannot create an account without one.', 'wpcredits-program-manager' ),
				$student['name'] ? $student['name'] : $student['record_id']
			);
			++$state['stats']['skipped'];

			return 0;
		}

		$login   = self::unique_login( $student );
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $student['name'] ? $student['name'] : $login,
				'nickname'     => $student['name'] ? $student['name'] : $login,
				'user_url'     => esc_url_raw( $student['profile'] ),
				'role'         => WPCPM_Roles::ROLE_STUDENT,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$state['notices'][] = sprintf(
				/* translators: 1: WordPress username, 2: error message. */
				__( 'Could not create the account "%1$s": %2$s', 'wpcredits-program-manager' ),
				$login,
				$user_id->get_error_message()
			);
			++$state['stats']['skipped'];

			return 0;
		}

		++$state['stats']['created'];

		if ( WPCPM_Settings::get_value( 'send_welcome_email' ) ) {
			// Queued, not sent. A first sync creates around ninety accounts inside one
			// request, and ninety sends from there means a timeout or a host's hourly mail
			// limit somewhere in the middle with no way to know how far it got.
			WPCPM_Mail::queue_invite( $user_id );
			++$state['stats']['invited'];
		}

		return (int) $user_id;
	}

	/**
	 * A free username for a student.
	 *
	 * Their WordPress.org handle when they have one, since that is the name they
	 * already use in the project; otherwise the local part of their email. Only
	 * ~7 in 10 students have a profile recorded, so the fallback is the common case
	 * rather than an edge one.
	 *
	 * @param array $student Student row.
	 * @return string
	 */
	private static function unique_login( array $student ) {
		$base = $student['username'];

		if ( '' === $base ) {
			$base = strtolower( (string) strstr( $student['email'], '@', true ) );
			$base = preg_replace( '/[^a-z0-9._\-]/', '', $base );
		}

		$base = sanitize_user( $base, true );

		if ( '' === $base ) {
			$base = 'student';
		}

		$login = $base;
		$n     = 1;

		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}

		return $login;
	}

	/**
	 * Take the Student role away from anyone no longer in the synced set.
	 *
	 * The account is kept — it is a person, not plugin state — but their access to
	 * Student-level content is not.
	 *
	 * @param array $state    Sync state, by reference.
	 * @param array $settings Plugin settings.
	 */
	private static function revoke_departed( array &$state, array $settings ) {
		$known = array();

		foreach ( (array) $state['students'] as $student ) {
			if ( ! empty( $student['record_id'] ) && empty( $student['is_past'] ) ) {
				$known[] = $student['record_id'];
			}
		}

		$linked = get_users(
			array(
				'number'     => -1,
				'fields'     => 'ID',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- Bounded by provisioned students.
					array(
						'key'     => self::META_RECORD_ID,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $linked as $user_id ) {
			$record = (string) get_user_meta( $user_id, self::META_RECORD_ID, true );

			if ( '' === $record || in_array( $record, $known, true ) ) {
				continue;
			}

			update_user_meta( $user_id, self::META_ACTIVE, 0 );

			if ( 'revoke' !== $settings['student_on_inactive'] ) {
				continue;
			}

			$user = new WP_User( $user_id );

			if ( in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true )
				|| ! in_array( WPCPM_Roles::ROLE_STUDENT, (array) $user->roles, true ) ) {
				continue;
			}

			$user->remove_role( WPCPM_Roles::ROLE_STUDENT );

			if ( empty( $user->roles ) ) {
				$user->set_role( 'subscriber' );
			}

			++$state['stats']['revoked'];
		}
	}

	/**
	 * The account already linked to a student record.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return WP_User|null
	 */
	private static function find_by_record_id( $record_id ) {
		$users = get_users(
			array(
				'meta_key'   => self::META_RECORD_ID,
				'meta_value' => $record_id,
				'number'     => 1,
			)
		);

		return ! empty( $users[0] ) ? $users[0] : null;
	}

	/**
	 * Store the run summary and clear the working state.
	 *
	 * @param array $state Final state.
	 */
	private static function finish( array $state ) {
		update_option(
			self::OPT_REPORT,
			array(
				'stats'    => isset( $state['stats'] ) ? $state['stats'] : self::empty_stats(),
				'notices'  => isset( $state['notices'] ) ? array_slice( (array) $state['notices'], 0, 100 ) : array(),
				'started'  => isset( $state['started'] ) ? (int) $state['started'] : 0,
				'finished' => time(),
			),
			false
		);

		update_option( self::OPT_LAST, time(), false );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * A zeroed statistics array.
	 *
	 * @return array<string, int>
	 */
	public static function empty_stats() {
		return array(
			'students_seen' => 0,
			'mentors_seen'  => 0,
			'profiles_read' => 0,
			'created'       => 0,
			'linked'        => 0,
			'updated'       => 0,
			'invited'       => 0,
			'assigned'      => 0,
			'revoked'       => 0,
			'skipped'       => 0,
			'conflicts'     => 0,
			'no_mentor'     => 0,
		);
	}

	/**
	 * A student's own program details.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_program( $user_id ) {
		$user_id = (int) $user_id;
		$program = get_user_meta( $user_id, self::META_PROGRAM, true );
		$program = is_array( $program ) ? $program : array();

		return self::heal( $program, $user_id );
	}

	/**
	 * Fields the mentor's copy of a student can supply when this student's own row lacks them.
	 *
	 * **Two syncs write two caches of the same student, and they are not run together.** The
	 * mentors sync fills `wpcpm_mentees` on the mentor; this one fills `wpcpm_student_program` on
	 * the student. Whenever a field is added to both, whichever sync has not been run since is a
	 * page showing "Not set" for data that is sitting in the other cache — which is how *Field of
	 * study* came to be missing from every one of 301 student rows while 546 of 558 mentor rows
	 * had it.
	 *
	 * So rather than telling people to run a sync, the value is borrowed at render time. This is
	 * the same self-healing `resolve_stored()` already gives institutions and teams, and the third
	 * time this trap has produced a page that looked broken while the code was correct.
	 */
	const HEALABLE = array( 'field_of_study', 'accessibility' );

	/**
	 * Fill blanks in a student's own row from the mentor's copy of the same student.
	 *
	 * Only ever fills what is missing or empty, so a value this sync wrote always wins, and it
	 * costs nothing on a row that is already complete — the common case returns before looking
	 * anything up.
	 *
	 * @param array $program The student's stored row.
	 * @param int   $user_id Student user ID.
	 * @return array
	 */
	private static function heal( array $program, $user_id ) {
		$missing = array();

		foreach ( self::HEALABLE as $key ) {
			if ( '' === trim( (string) ( isset( $program[ $key ] ) ? $program[ $key ] : '' ) ) ) {
				$missing[] = $key;
			}
		}

		if ( empty( $missing ) ) {
			return $program;
		}

		$row = self::mentor_side_row( $user_id );

		foreach ( $missing as $key ) {
			if ( isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
				$program[ $key ] = $row[ $key ];
			}
		}

		return $program;
	}

	/**
	 * The mentor's copy of one student's row, or an empty array.
	 *
	 * Memoized per request, including the "there is none" answer: a student whose mentor has no
	 * account would otherwise pay for the lookup on every call.
	 *
	 * @param int $user_id Student user ID.
	 * @return array
	 */
	private static function mentor_side_row( $user_id ) {
		static $cache = array();

		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}

		$cache[ $user_id ] = array();

		$record = trim( (string) get_user_meta( $user_id, self::META_RECORD_ID, true ) );
		$mentor = self::get_mentor( $user_id );
		$mentor = isset( $mentor['record_id'] ) ? trim( (string) $mentor['record_id'] ) : '';

		if ( '' === $record || '' === $mentor ) {
			return $cache[ $user_id ];
		}

		// The student's card names the mentor by Airtable record, and the mentors sync stamps that
		// record on the mentor's WordPress account — so this is the join between the two caches.
		$users = get_users(
			array(
				'meta_key'   => WPCPM_Mentors_Sync::META_RECORD_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_key -- Indexed, and one row.
				'meta_value' => $mentor, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_value -- Exact match on a record ID.
				'number'     => 1,
				// A flat list of IDs, for the reason given in `WPCPM_Mentor_Calls::scan_for_mentor()`:
				// asking for rows makes core's user-meta cache warn on this site's stack.
				'fields'     => 'ID',
			)
		);

		if ( empty( $users ) ) {
			return $cache[ $user_id ];
		}

		$rows = get_user_meta( WPCPM_Roles::id_of( $users[0] ), WPCPM_Mentors_Sync::META_MENTEES, true );

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( is_array( $row ) && isset( $row['record_id'] ) && $row['record_id'] === $record ) {
				$cache[ $user_id ] = $row;
				break;
			}
		}

		return $cache[ $user_id ];
	}

	/**
	 * The contact card for a student's mentor.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_mentor( $user_id ) {
		$mentor = get_user_meta( (int) $user_id, self::META_MENTOR, true );

		return is_array( $mentor ) ? $mentor : array();
	}

	/**
	 * Send one student their login invitation.
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public static function send_invite( $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'wpcpm_no_user', __( 'That user does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( ! in_array( WPCPM_Roles::ROLE_STUDENT, (array) $user->roles, true ) ) {
			return new WP_Error( 'wpcpm_not_student', __( 'That account does not hold the Student role.', 'wpcredits-program-manager' ) );
		}

		wp_new_user_notification( $user->ID, null, 'user' );
		update_user_meta( $user->ID, 'wpcpm_student_invited', time() );

		return true;
	}
}
