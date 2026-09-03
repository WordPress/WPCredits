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
 *
 * Since the Institutions module the `tutors` phase is also the institution side's one
 * read of the Students table (design spec section 8.1): it builds a row per Students
 * record, `provision` joins each report to those rows by email and stamps the account
 * with its institution's record ID, and `finish()` writes the per-institution roster
 * index through `WPCPM_Roster_Index`. Nothing else pages that table for the roster.
 */
class WPCPM_Students_Sync {

	/**
	 * The recurring run.
	 *
	 * The hook keeps its old name although the run is no longer daily: the string is what WordPress
	 * stored in the cron array on every site already running this, and renaming it would leave that
	 * event behind with nothing listening to it — a sync that never fires and no error to say so.
	 */
	const CRON_AUTO = 'wpcpm_students_daily';

	const CRON_TICK = 'wpcpm_students_sync_tick';

	/** Our own interval: core offers hourly, twicedaily, daily and weekly, and none of them is this. */
	const EVERY_THREE_HOURS = 'wpcpm_three_hours';

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

	/**
	 * The Institutions record ID the student belongs to, on its own key (design decision 1).
	 *
	 * A record ID and never a name: `resolve_stored()` returns '' for an ID the lookups map
	 * does not know, and a fence built on name equality would match every unresolved
	 * student against every unresolved institution. **Deleted rather than written empty**:
	 * `meta_value => ''` with the default compare matches every row holding an empty
	 * string, so an empty stamp is a fence that fails open. Whose word the stamp is on is
	 * `institution_source` inside `META_PROGRAM`, and it has three values: `students` when
	 * the Students table answered, `reports` when that table has no row for the address at
	 * all and the reports-side link stood in, and `''` when duplicate rows disagreed. The key
	 * is written on every account the sync reaches, whichever way it went, **including the
	 * runs that delete the stamp**: `students` with no stamp means the authority was asked
	 * and said the student belongs to no institution. So the key's presence is the sync's
	 * word that it looked, and its absence is the only state that means an account was never
	 * described - which is what the manager screen counts as a broken sync.
	 */
	const META_INSTITUTION = 'wpcpm_student_institution';

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
			// The key stays `tutors` although the phase now reads the whole table: a run in
			// flight resumes from a stored phase name, and a renamed key would strand it.
			'tutors'    => array(
				'label'  => __( 'Reading the Students table', 'wpcredits-program-manager' ),
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
		// Before `schedule()`, and on every request rather than only when scheduling: cron reads
		// the interval back when it decides what to run next, and an event on a schedule WordPress
		// cannot find is silently dropped from the queue.
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Three hours, well above the 15-minute floor the sniff guards.

		add_action( self::CRON_AUTO, array( __CLASS__, 'cron_auto' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'run_tick' ) );

		self::schedule();
	}

	/**
	 * Add the three-hour interval.
	 *
	 * @param array $schedules Registered schedules.
	 * @return array
	 */
	public static function cron_interval( $schedules ) {
		$schedules = is_array( $schedules ) ? $schedules : array();

		$schedules[ self::EVERY_THREE_HOURS ] = array(
			'interval' => 3 * HOUR_IN_SECONDS,
			'display'  => __( 'Every three hours', 'wpcredits-program-manager' ),
		);

		return $schedules;
	}

	/**
	 * Ensure the recurring event exists, on the interval this version wants.
	 *
	 * **The recurrence is checked, not just the existence.** An event already in the cron array
	 * keeps whatever schedule it was created with — so a site that has been running the daily sync
	 * would have gone on running it daily forever, with the code here saying three hours and no
	 * sign of the disagreement anywhere.
	 */
	public static function schedule() {
		$event = wp_get_scheduled_event( self::CRON_AUTO );

		if ( $event && isset( $event->schedule ) && self::EVERY_THREE_HOURS === $event->schedule ) {
			return;
		}

		if ( $event ) {
			wp_clear_scheduled_hook( self::CRON_AUTO );
		}

		// Offset from the mentors sync so the two never contend for the same Airtable rate limit or
		// the same PHP worker. It only holds for the first run — after that the two cadences drift
		// apart anyway — but it keeps them off each other on the one run that follows an upgrade,
		// which is when both are most likely to have work to do.
		wp_schedule_event( time() + ( 30 * MINUTE_IN_SECONDS ), self::EVERY_THREE_HOURS, self::CRON_AUTO );
	}

	/**
	 * Drop all scheduled work.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_AUTO );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Recurring entry point.
	 */
	public static function cron_auto() {
		if ( ! WPCPM_Settings::get_value( 'auto_sync' ) ) {
			return;
		}

		// **A run in progress is left alone.** `start()` wipes the state and begins again, which was
		// harmless while this fired once a day and a run finished in minutes — at three hours it is
		// not: a slow run would be restarted from the top by the next tick, and a site whose runs
		// take longer than the gap would sync forever without ever finishing one.
		//
		// Unless it has stalled: a run whose ticks stopped is not going to finish on its own, and
		// refusing to start on account of it would be worse than the restart it is avoiding.
		$progress = self::progress();

		if ( ! empty( $progress['running'] ) && empty( $progress['stalled'] ) ) {
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
				// The roster: one row per Students record, keyed by its record ID, in the
				// shape `WPCPM_Roster_Index` stores. Roughly doubles the state option, which
				// is why the row holds only what the index needs.
				'rows'     => array(),
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
				return sprintf(
					/* translators: %s: number of Students table rows read. */
					__( '%s Students rows read', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'rows_read' ) )
				);

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
					$fields['report_link_dev'],
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
			$track   = WPCPM_Program::track( $status );
			$email   = $read( 'report_email' );
			$profile = $read( 'report_profile' );

			$mentor_ids      = WPCPM_Airtable::link_ids( isset( $cells[ $fields['report_mentor'] ] ) ? $cells[ $fields['report_mentor'] ] : array() );
			$institution_ids = WPCPM_Airtable::link_ids( isset( $cells[ $fields['report_instituton'] ] ) ? $cells[ $fields['report_instituton'] ] : array() );

			$state['students'][] = array(
				'record_id'      => isset( $record['id'] ) ? (string) $record['id'] : '',
				'name'           => trim( $read( 'report_name' ) ),
				'email'          => $email,
				'email_key'      => strtolower( trim( $email ) ),
				// The track is the program: there is no separate Program column in
				// Airtable, and the status is also what decides which reporting form
				// applies.
				'program'        => $status,
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
				// All three formula fields are always populated, so the track is what decides
				// which one is this student's real link. Unknown track falls back to the
				// 150-hour link rather than to nothing: a finished student keeps a working
				// link to the form they filled in.
				'link'           => $read( WPCPM_Mentors_Sync::link_field( $track ) ),
				// Filled by the email-keyed pass over the Students table, below. Declared here so
				// the row's shape is the same whether or not that pass found anything.
				'tutor'          => '',
				'field_of_study' => '',
				'accessibility'  => '',
				'mentor_id'      => ! empty( $mentor_ids ) ? $mentor_ids[0] : '',
				// The reports-side institution link kept as an ID beside the resolved name:
				// the stamp of last resort for an account whose Students row the email join
				// cannot find. Stripped before the row becomes `wpcpm_student_program`.
				'institution_id' => ( ! empty( $institution_ids ) && WPCPM_Mentors_Sync::is_record_id( $institution_ids[0] ) ) ? (string) $institution_ids[0] : '',
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
	 * Phase 3 - the Students table, read once, for two things at once.
	 *
	 * The three email-keyed maps that give each account its tutor, field of study and
	 * accessibility needs, exactly as the Mentors sync builds them; and, since the
	 * Institutions module, the roster: one row per Students record, keyed by its record
	 * ID, in the shape `WPCPM_Roster_Index` stores (design spec section 8.1). One pass,
	 * because a second phase paging the same 800 rows at a second cadence with a second
	 * date rule is how two surfaces come to disagree about the same student.
	 *
	 * The row holds no free text and never the accessibility disclosure: that was told to
	 * the program, not to the school, and the index is what an institution reads.
	 * `Accessibility needs` still feeds the map behind `wpcpm_student_program`, as before.
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
					$fields['student_record_name'],
					$fields['student_email'],
					$fields['student_status'],
					$fields['student_institution'],
					$fields['student_start'],
					$fields['student_end'],
					$fields['student_mentor'],
					$fields['student_profile'],
					$fields['student_tutor'],
					$fields['student_tutors'],
					$fields['student_study'],
					$fields['student_access'],
					$fields['student_import_key'],
				),
				'offset' => $state['offset'],
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		// A run already in flight when this shipped has no `rows`; it gets an empty set here
		// and `finish()` treats a missing key, not an empty one, as "leave the index alone".
		if ( ! isset( $state['rows'] ) || ! is_array( $state['rows'] ) ) {
			$state['rows'] = array();
		}

		foreach ( $page['records'] as $record ) {
			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

			$read = static function ( $key ) use ( $cells, $fields ) {
				return trim( WPCPM_Airtable::flatten( isset( $cells[ $fields[ $key ] ] ) ? $cells[ $fields[ $key ] ] : '' ) );
			};

			$email     = $read( 'student_email' );
			$email_key = strtolower( $email );
			$tutor     = $read( 'student_tutor' );

			if ( '' === $tutor ) {
				$tutor = $read( 'student_tutors' );
			}

			// Both live only in the Students table, so this email-keyed pass is the only
			// place they can be picked up. Parallel maps, so a run already in flight when
			// this shipped keeps working — a missing key reads as absent.
			$study  = $read( 'student_study' );
			$access = $read( 'student_access' );

			if ( '' !== $email_key ) {
				if ( '' !== $tutor ) {
					$state['tutors'][ $email_key ] = $tutor;
				}

				if ( '' !== $study ) {
					$state['study'][ $email_key ] = $study;
				}

				if ( '' !== $access ) {
					$state['access'][ $email_key ] = $access;
				}
			}

			$record_id = isset( $record['id'] ) ? trim( (string) $record['id'] ) : '';

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			// The first ID of the link and nothing else: 797 of 800 rows link to exactly one
			// institution and none to more, so a second ID would be a data error to surface,
			// not a second school to file the student under.
			$links       = WPCPM_Airtable::link_ids( isset( $cells[ $fields['student_institution'] ] ) ? $cells[ $fields['student_institution'] ] : array() );
			$institution = ( ! empty( $links ) && WPCPM_Mentors_Sync::is_record_id( $links[0] ) ) ? trim( (string) $links[0] ) : '';
			$mentors     = WPCPM_Airtable::link_ids( isset( $cells[ $fields['student_mentor'] ] ) ? $cells[ $fields['student_mentor'] ] : array() );

			$state['rows'][ $record_id ] = array(
				'record_id'      => $record_id,
				'name'           => $read( 'student_record_name' ),
				'email'          => $email,
				'email_key'      => $email_key,
				'status'         => $read( 'student_status' ),
				'institution'    => $institution,
				// Stored as read. `WPCPM_Cohort::key()` files anything that is not a plain
				// date under "no start date", so a field type change in the base surfaces
				// as an empty cohort rather than being papered over here.
				'start'          => $read( 'student_start' ),
				'end'            => $read( 'student_end' ),
				'has_mentor'     => ! empty( $mentors ),
				'username'       => WPCPM_Mentors_Sync::wporg_username( $read( 'student_profile' ) ),
				'field_of_study' => $study,
				'tutor'          => $tutor,
				'import_key'     => $read( 'student_import_key' ),
				// Both filled by `phase_provision()`, which is where the reports and the
				// accounts are; declared here so every row has the same shape.
				'reports'        => array(),
				'user_id'        => 0,
			);

			// Not `++`: a run that started under the previous version has no such key yet.
			$state['stats']['rows_read'] = ( isset( $state['stats']['rows_read'] ) ? (int) $state['stats']['rows_read'] : 0 ) + 1;
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

		$by_email = self::rows_by_email( $state );

		foreach ( $slice as $index => $student ) {
			$state['cursor'] = $index + 1;

			if ( '' === $student['record_id'] ) {
				continue;
			}

			// The Students rows behind this report, by email, resolved before the account:
			// a row's `reports` list is about the record in Airtable and is filled whether
			// or not an account exists, because a graduate with no account is still a
			// finished student on the roster.
			$join = self::join_students_rows( $state, $student, $by_email );

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
			unset( $program['email_key'], $program['mentor_id'], $program['institution_id'] );

			// Whose word the stamp below is on, beside the resolved name the cards print.
			$program['institution_source'] = $join['source'];

			update_user_meta( $user_id, self::META_RECORD_ID, $student['record_id'] );
			update_user_meta( $user_id, self::META_ACTIVE, $student['is_past'] ? 0 : 1 );
			update_user_meta( $user_id, self::META_PROGRAM, $program );
			update_user_meta( $user_id, self::META_MENTOR, $mentor );
			update_user_meta( $user_id, self::META_UPDATED, time() );

			// The stamp the institution fence reads. Deleted, never written empty: see the
			// constant. Counted only where the reports side stood in for an address the
			// Students table has no row for at all, so the manager screen can say how many
			// records that table still lacks. A row that exists and names no institution is
			// not one of those: it leaves the fence closed and is counted on the
			// reconciliation card, under the rows with no institution.
			if ( WPCPM_Mentors_Sync::is_record_id( $join['institution'] ) ) {
				update_user_meta( $user_id, self::META_INSTITUTION, $join['institution'] );

				if ( 'reports' === $join['source'] ) {
					$state['stats']['stamped_from_reports'] = ( isset( $state['stats']['stamped_from_reports'] ) ? (int) $state['stats']['stamped_from_reports'] : 0 ) + 1;
				}
			} else {
				delete_user_meta( $user_id, self::META_INSTITUTION );
			}

			foreach ( $join['rows'] as $record_id ) {
				$state['rows'][ $record_id ]['user_id'] = (int) $user_id;
			}

			++$state['stats']['assigned'];
		}

		return true;
	}

	/**
	 * The Students rows' record IDs by email key, built once per tick.
	 *
	 * @param array $state Sync state.
	 * @return array<string, string[]>
	 */
	private static function rows_by_email( array $state ) {
		$by_email = array();
		$rows     = isset( $state['rows'] ) && is_array( $state['rows'] ) ? $state['rows'] : array();

		foreach ( $rows as $record_id => $row ) {
			$key = isset( $row['email_key'] ) ? (string) $row['email_key'] : '';

			if ( '' === $key ) {
				continue;
			}

			$by_email[ $key ][] = (string) $record_id;
		}

		return $by_email;
	}

	/**
	 * Which institution a report's student belongs to, and on whose word.
	 *
	 * The Students table is the authority (design decision 2), reached by email because
	 * `Students.Students Reports` is empty on every row of both tables. Exactly one row, or
	 * several that agree on the institution, is the Students side's answer. No row at all is
	 * the seven reports-only accounts the first spec dropped from the fence while their
	 * mentors still saw them: the reports-side link stands in, marked `reports` so the
	 * dashboard can say a program manager needs to complete the record. Several rows that
	 * disagree are one address filed under two schools; nobody here can say which, so the
	 * stamp goes and the conflict is counted, and neither row is given the account or the
	 * report, because a disputed identity on both rosters is worse than on neither.
	 *
	 * A Students row that exists and names no institution is not the same case, and must not
	 * be read as one: the authority answered, and its answer is blank. The stamp is deleted
	 * rather than written empty (design decision 1) and the source stays `students`, because
	 * the stamp is the fence key, and opening the fence for an institution the Students table
	 * does not name is the failure decision 1 exists to prevent. The row is still joined to
	 * its report and its account, and is counted once, as an institution-less row on the
	 * reconciliation card and on the manager's `wpcpm_roster_unlinked` list.
	 *
	 * @param array $state    Sync state, by reference: `reports` is appended on the matched rows.
	 * @param array $student  The reports row.
	 * @param array $by_email Students record IDs by email key.
	 * @return array{institution: string, source: string, rows: string[]}
	 */
	private static function join_students_rows( array &$state, array $student, array $by_email ) {
		$email_key    = isset( $student['email_key'] ) ? (string) $student['email_key'] : '';
		$matches      = ( '' !== $email_key && isset( $by_email[ $email_key ] ) ) ? $by_email[ $email_key ] : array();
		$from_reports = ( isset( $student['institution_id'] ) && WPCPM_Mentors_Sync::is_record_id( $student['institution_id'] ) ) ? (string) $student['institution_id'] : '';

		if ( empty( $matches ) ) {
			return array(
				'institution' => $from_reports,
				'source'      => 'reports',
				'rows'        => array(),
			);
		}

		$institutions = array();

		foreach ( $matches as $record_id ) {
			$institutions[] = isset( $state['rows'][ $record_id ]['institution'] ) ? (string) $state['rows'][ $record_id ]['institution'] : '';
		}

		$institutions = array_values( array_unique( $institutions ) );

		if ( count( $institutions ) > 1 ) {
			$state['notices'][] = sprintf(
				/* translators: %s: student name. */
				__( 'No institution recorded for %s: duplicate email in the Students table, filed under more than one institution.', 'wpcredits-program-manager' ),
				$student['name'] ? $student['name'] : $student['record_id']
			);
			++$state['stats']['conflicts'];

			return array(
				'institution' => '',
				'source'      => '',
				'rows'        => array(),
			);
		}

		foreach ( $matches as $record_id ) {
			$reports = isset( $state['rows'][ $record_id ]['reports'] ) ? (array) $state['rows'][ $record_id ]['reports'] : array();

			if ( ! in_array( $student['record_id'], $reports, true ) ) {
				$reports[] = $student['record_id'];
			}

			$state['rows'][ $record_id ]['reports'] = $reports;
		}

		// '' when the row names no institution, and `phase_provision()` deletes the stamp on
		// it: the Students side has spoken either way, so the source is its word either way.
		return array(
			'institution' => $institutions[0],
			'source'      => 'students',
			'rows'        => $matches,
		);
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
		$known  = array();
		$synced = array();

		foreach ( (array) $state['students'] as $student ) {
			if ( empty( $student['record_id'] ) ) {
				continue;
			}

			// Everyone this run read, finished or not: a graduate keeps their institution
			// stamp, because their school's semester report is about them. Only a record
			// the run did not see at all has left.
			$synced[] = $student['record_id'];

			if ( empty( $student['is_past'] ) ) {
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

			// An identity that outlives the link is a fence that fails open (design decision
			// 1), so this goes whatever `student_on_inactive` says about the role.
			if ( ! in_array( $record, $synced, true ) ) {
				delete_user_meta( $user_id, self::META_INSTITUTION );
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
	 * The account behind an Airtable student record, if there is one.
	 *
	 * Public because the mentor's page needs it: a mentee row carries the record ID, and
	 * offering to open that student's report form means resolving it to an account first.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return WP_User|null
	 */
	public static function user_for_record( $record_id ) {
		$record_id = is_string( $record_id ) ? trim( $record_id ) : '';

		return '' === $record_id ? null : self::find_by_record_id( $record_id );
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

		// The roster index, from the rows the Students-table pass built. Only here, so a
		// run that fails part-way leaves last run's options in place. A state with no
		// `rows` key at all is a run that started under the previous version and finished
		// under this one; it leaves the index alone rather than writing an empty one.
		if ( isset( $state['rows'] ) && is_array( $state['rows'] ) ) {
			self::write_roster( $state );
		}

		update_option( self::OPT_LAST, time(), false );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Group the rows by institution and write the index, the counts and the reconciliation.
	 *
	 * The counts come from `WPCPM_Cohort::participation()` per institution per cohort key,
	 * the same function the comparison strip and the semester report read, so the three
	 * can only disagree about when the table was read, and each prints that.
	 *
	 * @param array $state Final state.
	 */
	private static function write_roster( array $state ) {
		$by_institution = array();
		$unlinked       = array();

		foreach ( $state['rows'] as $record_id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$institution = isset( $row['institution'] ) ? (string) $row['institution'] : '';

			if ( WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
				$by_institution[ $institution ][ $record_id ] = $row;
			} else {
				$unlinked[ $record_id ] = $row;
			}
		}

		$counts = array();

		foreach ( $by_institution as $institution => $rows ) {
			$keys = array();

			foreach ( $rows as $row ) {
				$keys[ WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) ] = true;
			}

			$keys = array_keys( $keys );
			usort( $keys, array( 'WPCPM_Cohort', 'compare' ) );

			$counts[ $institution ] = array();

			foreach ( $keys as $key ) {
				$counts[ $institution ][ $key ] = WPCPM_Cohort::participation( $rows, $key );
			}
		}

		WPCPM_Roster_Index::write_all(
			$by_institution,
			$unlinked,
			$counts,
			self::reconciliation( $state ),
			isset( $state['started'] ) ? (int) $state['started'] : time()
		);
	}

	/**
	 * The manager's reconciliation card, counted once from what both passes read.
	 *
	 * Every number is a row a program manager may need to touch, counted rather than
	 * listed: the card says "31 Students rows have no report", and the grid is where the
	 * rows are. Reports rows are the ones the run fetched, which is the tracked statuses
	 * only, so a disagreement with a status the sync never asks for cannot be seen here.
	 *
	 * @param array $state Final state.
	 * @return array{students_without_reports: array, reports_without_students: array, status_disagreements: int, duplicate_emails: array, no_institution: int, no_start_date: array}
	 */
	private static function reconciliation( array $state ) {
		$rows     = $state['rows'];
		$reports  = isset( $state['students'] ) ? (array) $state['students'] : array();
		$by_email = self::rows_by_email( $state );

		$out = array(
			'students_without_reports' => array(),
			'reports_without_students' => array(),
			'status_disagreements'     => 0,
			'duplicate_emails'         => array(),
			'no_institution'           => 0,
			'no_start_date'            => array(),
		);

		$report_emails = array();

		foreach ( $reports as $report ) {
			$key = isset( $report['email_key'] ) ? (string) $report['email_key'] : '';

			if ( '' !== $key ) {
				$report_emails[ $key ] = true;
			}
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			$key    = isset( $row['email_key'] ) ? (string) $row['email_key'] : '';

			// A row with no address has no report either: email is the only join.
			if ( '' === $key || ! isset( $report_emails[ $key ] ) ) {
				self::tally( $out['students_without_reports'], $status );
			}

			if ( ! WPCPM_Mentors_Sync::is_record_id( isset( $row['institution'] ) ? $row['institution'] : '' ) ) {
				++$out['no_institution'];
			}

			if ( '' === trim( (string) ( isset( $row['start'] ) ? $row['start'] : '' ) ) ) {
				self::tally( $out['no_start_date'], $status );
			}
		}

		foreach ( $reports as $report ) {
			$key    = isset( $report['email_key'] ) ? (string) $report['email_key'] : '';
			$status = isset( $report['program'] ) ? trim( (string) $report['program'] ) : '';

			if ( '' === $key || ! isset( $by_email[ $key ] ) ) {
				self::tally( $out['reports_without_students'], $status );
				continue;
			}

			// One disagreement per report row, however many Students rows share its address.
			foreach ( $by_email[ $key ] as $record_id ) {
				$theirs = isset( $rows[ $record_id ]['status'] ) ? trim( (string) $rows[ $record_id ]['status'] ) : '';

				if ( $theirs !== $status ) {
					++$out['status_disagreements'];
					break;
				}
			}
		}

		// An address on more than one row, counted once per institution it is filed under:
		// the base's nine are all inside one school, and a pair split across two shows up
		// on both, which is where it needs to be seen.
		foreach ( $by_email as $record_ids ) {
			if ( count( $record_ids ) < 2 ) {
				continue;
			}

			$institutions = array();

			foreach ( $record_ids as $record_id ) {
				$institutions[ isset( $rows[ $record_id ]['institution'] ) ? (string) $rows[ $record_id ]['institution'] : '' ] = true;
			}

			foreach ( array_keys( $institutions ) as $institution ) {
				self::tally( $out['duplicate_emails'], $institution );
			}
		}

		return $out;
	}

	/**
	 * Count one more under a key, creating the key on first sight.
	 *
	 * @param array  $bucket Counts by key, by reference.
	 * @param string $key    The key; '' is a key like any other, for the empty status.
	 */
	private static function tally( array &$bucket, $key ) {
		$key = (string) $key;

		$bucket[ $key ] = isset( $bucket[ $key ] ) ? (int) $bucket[ $key ] + 1 : 1;
	}

	/**
	 * A zeroed statistics array.
	 *
	 * @return array<string, int>
	 */
	public static function empty_stats() {
		return array(
			'students_seen'        => 0,
			'mentors_seen'         => 0,
			'profiles_read'        => 0,
			'created'              => 0,
			'linked'               => 0,
			'updated'              => 0,
			'invited'              => 0,
			'assigned'             => 0,
			'revoked'              => 0,
			'skipped'              => 0,
			'conflicts'            => 0,
			'no_mentor'            => 0,
			// The Students-table pass: rows read, and accounts whose institution had to
			// come from the reports side because the Students table has no row for the
			// address. A row that exists and names no institution is not one of these.
			'rows_read'            => 0,
			'stamped_from_reports' => 0,
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

		$record    = trim( (string) get_user_meta( $user_id, self::META_RECORD_ID, true ) );
		$mentor_id = self::mentor_user_id( $user_id );

		if ( '' === $record || ! $mentor_id ) {
			return $cache[ $user_id ];
		}

		$rows = get_user_meta( $mentor_id, WPCPM_Mentors_Sync::META_MENTEES, true );

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( is_array( $row ) && isset( $row['record_id'] ) && $row['record_id'] === $record ) {
				$cache[ $user_id ] = $row;
				break;
			}
		}

		return $cache[ $user_id ];
	}

	/**
	 * The WordPress account of a student's mentor, or 0.
	 *
	 * The student's card names the mentor by Airtable record, and the mentors sync stamps that
	 * record on the mentor's WordPress account — so this is the join between the two caches.
	 *
	 * @param int $user_id Student user ID.
	 * @return int
	 */
	private static function mentor_user_id( $user_id ) {
		$mentor = self::get_mentor( $user_id );
		$mentor = isset( $mentor['record_id'] ) ? trim( (string) $mentor['record_id'] ) : '';

		if ( '' === $mentor ) {
			return 0;
		}

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

		return empty( $users ) ? 0 : (int) WPCPM_Roles::id_of( $users[0] );
	}

	/**
	 * Carry what a student just saved into the two cached copies of their row.
	 *
	 * **The report form and the cards read the same four Airtable columns, but not at the same
	 * time.** *WordPress Profile*, *Slack Name*, *Main Contribution Team* and *Personal Website
	 * URL* live in the Students Reports table; the form writes them live, while the cards read the
	 * copy this sync left behind. So a student who filled in their team saw *Not set* on their own
	 * card until the next sync ran — the answer was in Airtable, and the page was showing a
	 * week-old snapshot of it.
	 *
	 * Both copies are updated, because there are two: the student's own `wpcpm_student_program`,
	 * and the row inside their mentor's `wpcpm_mentees`. Updating one and not the other is the
	 * failure `heal()` exists to paper over, and this is a chance not to create it.
	 *
	 * No Airtable request: the values being written are the ones just accepted from the form.
	 *
	 * @param int   $user_id Student user ID.
	 * @param array $cells   Cells as sent to Airtable, keyed by column name.
	 * @return bool Whether anything was carried over.
	 */
	public static function apply_report( $user_id, array $cells ) {
		$user_id = (int) $user_id;
		$fields  = WPCPM_Mentors_Sync::fields();

		// Keyed by the *configured* column names rather than by literals, so a base that renames a
		// column renames it here too — the same map the sync itself reads.
		$map = array(
			$fields['report_profile'] => 'profile',
			$fields['report_slack']   => 'slack',
			$fields['report_team']    => 'team',
			$fields['report_website'] => 'website',
			$fields['report_hours']   => 'hours',
		);

		$changed = array();

		foreach ( $map as $column => $key ) {
			// `array_key_exists`, not `isset`: the save loop only includes a column when the form
			// posted it, and an answer cleared to "" is still an answer.
			if ( ! array_key_exists( $column, $cells ) ) {
				continue;
			}

			$value = WPCPM_Airtable::flatten( $cells[ $column ] );

			if ( 'team' === $key ) {
				// A linked-record column is written as record IDs; the cards store names.
				$value = WPCPM_Mentors_Sync::resolve_stored( $value, 'teams' );
			}

			$changed[ $key ] = $value;

			if ( 'profile' === $key ) {
				// Stored beside the URL, and it is what the card links and labels.
				$changed['username'] = WPCPM_Mentors_Sync::wporg_username( $value );
			}
		}

		if ( empty( $changed ) ) {
			return false;
		}

		$program = get_user_meta( $user_id, self::META_PROGRAM, true );

		if ( is_array( $program ) && ! empty( $program ) ) {
			update_user_meta( $user_id, self::META_PROGRAM, array_merge( $program, $changed ) );
		}

		$record    = trim( (string) get_user_meta( $user_id, self::META_RECORD_ID, true ) );
		$mentor_id = self::mentor_user_id( $user_id );

		if ( '' === $record || ! $mentor_id ) {
			return true;
		}

		$rows  = get_user_meta( $mentor_id, WPCPM_Mentors_Sync::META_MENTEES, true );
		$rows  = is_array( $rows ) ? $rows : array();
		$found = false;

		foreach ( $rows as $i => $row ) {
			if ( is_array( $row ) && isset( $row['record_id'] ) && (string) $row['record_id'] === $record ) {
				$rows[ $i ] = array_merge( $row, $changed );
				$found      = true;
				break;
			}
		}

		if ( $found ) {
			update_user_meta( $mentor_id, WPCPM_Mentors_Sync::META_MENTEES, $rows );
		}

		return true;
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
