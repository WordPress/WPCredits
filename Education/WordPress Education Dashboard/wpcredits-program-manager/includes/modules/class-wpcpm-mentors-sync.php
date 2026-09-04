<?php
/**
 * Mentors module - Airtable sync.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provisions Mentor accounts from Airtable and attaches each mentor's assigned
 * students to their user record.
 *
 * The run is a resumable state machine driven by WP-Cron. Roughly 90 mentors,
 * 290 student reports and every student row is more than one request can carry,
 * so each tick works to a time budget, saves its position and schedules the next
 * tick. That also means a fatal mid-run leaves recoverable state rather than a
 * half-provisioned site with no record of where it stopped.
 *
 * Phase order matters: `revoke` can only run once `mentors` has seen every
 * active record, otherwise a mentor not yet paged in would look inactive.
 */
class WPCPM_Mentors_Sync {

	const CRON_DAILY = 'wpcpm_mentors_daily';
	const CRON_TICK  = 'wpcpm_mentors_sync_tick';

	const OPT_STATE   = 'wpcpm_mentors_state';
	const OPT_REPORT  = 'wpcpm_mentors_report';
	const OPT_LAST    = 'wpcpm_mentors_last_sync';
	const OPT_ERROR   = 'wpcpm_mentors_last_error';
	const OPT_LOCK    = 'wpcpm_mentors_lock';
	const OPT_FIELDS  = 'wpcpm_mentors_field_meta';
	const OPT_LOOKUPS = 'wpcpm_mentors_lookups';

	/**
	 * Schema version for the stored lookup maps.
	 *
	 * Bump this whenever a bug could have written *wrong* names into the maps, as
	 * opposed to none. Version 1 picked the wrong column and stored values like
	 * "Confirmed" as an institution name; a stale map is discarded rather than
	 * trusted, so the page falls back to "Not set" and asks for a sync instead of
	 * confidently showing the wrong thing.
	 */
	const LOOKUPS_VERSION = 3;

	/** User meta. */
	const META_RECORD_ID  = 'wpcpm_mentor_record_id';
	const META_PROFILE    = 'wpcpm_mentor_profile';
	const META_ACTIVE     = 'wpcpm_mentor_active';
	const META_MENTEES    = 'wpcpm_mentees';
	const META_COUNT      = 'wpcpm_mentee_count';
	const META_PAST_COUNT = 'wpcpm_mentee_past_count';
	const META_UPDATED    = 'wpcpm_mentees_updated';

	/** Seconds of work per cron tick, leaving room under a 30s PHP limit. */
	const BUDGET = 18;

	/**
	 * Shorter budget for browser-driven ticks.
	 *
	 * The admin screen polls for progress, and each poll performs a slice of the
	 * work. A shorter slice means the reported numbers move every few seconds
	 * rather than every eighteen, which is the difference between "working" and
	 * "hung" to anyone watching.
	 */
	const BUDGET_AJAX = 8;

	/** How long a tick may hold the lock before it is treated as dead. */
	const LOCK_TIMEOUT = 120;

	/**
	 * The phases of a run, in order.
	 *
	 * `weight` is that phase's share of the progress bar. `steps` is a rough
	 * expected number of slices, used only to interpolate the bar *within* a
	 * phase - Airtable's list endpoint never reports a total record count, so a
	 * genuinely exact percentage is not available. The bar is therefore honest at
	 * phase boundaries and an estimate in between, which is why the live record
	 * counts are shown alongside it rather than instead of it.
	 *
	 * @return array<string, array{label: string, weight: int, steps: int}>
	 */
	public static function phases() {
		return array(
			'schema'   => array(
				'label'  => __( 'Reading field descriptions from Airtable', 'wpcredits-program-manager' ),
				'weight' => 5,
				'steps'  => 1,
			),
			'lookups'  => array(
				'label'  => __( 'Reading institution and team names', 'wpcredits-program-manager' ),
				'weight' => 5,
				'steps'  => 2,
			),
			'mentors'  => array(
				'label'  => __( 'Provisioning mentor accounts', 'wpcredits-program-manager' ),
				'weight' => 22,
				'steps'  => 1,
			),
			'revoke'   => array(
				'label'  => __( 'Reviewing mentors who are no longer active', 'wpcredits-program-manager' ),
				'weight' => 8,
				'steps'  => 1,
			),
			'reports'  => array(
				'label'  => __( 'Reading student reports', 'wpcredits-program-manager' ),
				'weight' => 25,
				'steps'  => 3,
			),
			'students' => array(
				'label'  => __( 'Reading tutor assignments', 'wpcredits-program-manager' ),
				'weight' => 20,
				'steps'  => 6,
			),
			'assign'   => array(
				'label'  => __( 'Assigning students to mentors', 'wpcredits-program-manager' ),
				'weight' => 15,
				'steps'  => 1,
			),
		);
	}

	/**
	 * Airtable field names.
	 *
	 * Names rather than IDs because `filterByFormula` only accepts names. Note
	 * that the Students table's tutor column really is called `Tutor ` with a
	 * trailing space - dropping it silently returns no tutor at all.
	 *
	 * @return array<string, string>
	 */
	public static function fields() {
		return (array) apply_filters(
			'wpcpm_mentors_fields',
			array(
				// Mentors table.
				'mentor_name'         => 'Full Name',
				'mentor_profile'      => 'WordPress profile',
				'mentor_email'        => 'Email',
				'mentor_status'       => 'Status',
				// Students Reports table.
				'report_name'         => 'Name',
				'report_email'        => 'Email',
				'report_status'       => 'Status',
				'report_mentor'       => 'Mentor',
				'report_instituton'   => 'Educational institution',
				'report_profile'      => 'WordPress Profile',
				'report_slack'        => 'Slack Name',
				'report_team'         => 'Main Contribution Team',
				'report_website'      => 'Personal Website URL',
				'report_start'        => 'Internship Start Date',
				'report_end'          => 'Internship End Date',
				// The hours a student has logged, against the target their status carries in
				// `WPCPM_Program::hours_target()`. A number in the base, kept as the string
				// every other cell on this row is, and formatted where it is printed: the
				// Developer Track's target is 0, so a renderer that reached for "n of 150"
				// here would invent a denominator for a track that has none.
				//
				// **Two things the live column does that a renderer has to survive**, read
				// off the base rather than assumed: the value is fractional for some students
				// (6.2, 135.5), so `intval()` would quietly round a term's work down; and it
				// runs past the target for others (400 against a 150-hour track), so a bar
				// drawn from it needs clamping and a percentage needs no upper faith. An unset
				// cell is absent rather than 0, which is a student nobody has logged for yet
				// and not a student who has done nothing.
				'report_hours'        => 'Hours',
				'report_link'         => 'Personal link',
				'report_link_50h'     => '50h personal link',
				'report_link_dev'     => 'Dev Track ONLY personal link',
				// Students table, for the tutor join.
				'student_email'       => 'Email',
				'student_tutor'       => 'Tutor ',
				'student_tutors'      => 'Tutors official',
				// Both live only in the Students table, not in Students Reports, which is
				// why they arrive through the same email-keyed join the tutor does.
				'student_study'       => 'Your field of study',
				'student_access'      => 'Accessibility needs',
				// The rest of the Students table the students sync's Students-table pass
				// reads for the per-institution roster index (design spec section 8.1).
				// `Educational Institutions` is plural with a capital I, and `Start Date`
				// is this table's own column, not the reports table's `Internship Start
				// Date`; every name here is asserted against
				// bin/fixtures/students-table-fields.json by bin/test-students-sync.php.
				'student_record_name' => 'Full Name',
				'student_status'      => 'Status',
				'student_institution' => 'Educational Institutions',
				'student_start'       => 'Start Date',
				'student_end'         => 'End Date',
				'student_mentor'      => 'Mentor',
				'student_profile'     => 'WP Profile',
				'student_import_key'  => 'Site import key',
			)
		);
	}

	/**
	 * Which `fields()` key holds a track's reporting-form link.
	 *
	 * The three formula columns are all populated on every record, so reading the wrong one gives a
	 * working link to the wrong form - a failure that looks like success until a student fills in
	 * somebody else's questions. Hence a map rather than a conditional.
	 *
	 * A finished student has no track. They keep the 150-hour link, which is the form most of them
	 * filled in, rather than no link at all.
	 *
	 * @param string $track Track key from `WPCPM_Program::track()`.
	 * @return string A key of `fields()`.
	 */
	public static function link_field( $track ) {
		$links = array(
			'150h' => 'report_link',
			'50h'  => 'report_link_50h',
			'dev'  => 'report_link_dev',
		);

		return isset( $links[ $track ] ) ? $links[ $track ] : 'report_link';
	}

	/**
	 * Register cron hooks and the daily schedule.
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
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_DAILY );
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
	 * Daily entry point; respects the auto-sync setting.
	 */
	public static function cron_daily() {
		if ( ! WPCPM_Settings::get_value( 'auto_sync' ) ) {
			return;
		}

		self::start();
	}

	/**
	 * Begin a run, replacing any state left over from an abandoned one.
	 *
	 * No work happens here on purpose. Doing the first slice inline would block
	 * the click that started the sync for up to a full budget with nothing on
	 * screen - exactly the "is it stuck?" moment this is meant to avoid. Instead
	 * the state is written, a cron tick is queued as a safety net, and the caller
	 * redirects straight to a screen that reports progress and drives the
	 * remaining ticks itself.
	 *
	 * @param bool $run_first_tick Process one slice before returning. For WP-CLI,
	 *                             which has no browser to drive the ticks.
	 * @return true|WP_Error
	 */
	public static function start( $run_first_tick = false ) {
		if ( ! WPCPM_Settings::is_connected() ) {
			$error = new WP_Error( 'wpcpm_not_connected', __( 'Add an Airtable Personal Access Token and Base ID before syncing.', 'wpcredits-program-manager' ) );
			update_option( self::OPT_ERROR, $error->get_error_message(), false );

			return $error;
		}

		// Refused before any state is written, like the missing token: a run with no status
		// to filter by would read the whole base, and the screen has to say so rather than
		// count it as a run that finished.
		$error = self::empty_statuses_error( WPCPM_Settings::get(), true );

		if ( is_wp_error( $error ) ) {
			update_option( self::OPT_ERROR, $error->get_error_message(), false );

			return $error;
		}

		delete_option( self::OPT_LOCK );

		update_option(
			self::OPT_STATE,
			array(
				'phase'   => 'schema',
				'offset'  => null,
				'started' => time(),
				'touched' => time(),
				'steps'   => array(),
				'mentors' => array(),
				'reports' => array(),
				// All three are email-keyed maps filled by the same pass over the Students
				// table. They auto-vivify, so leaving two of them out worked - but the
				// asymmetry is what made a missing "Field of study" read as a code difference
				// between it and the tutor rather than as two caches of different ages.
				'tutors'  => array(),
				'study'   => array(),
				'access'  => array(),
				'stats'   => self::empty_stats(),
				'notices' => array(),
			),
			false
		);

		delete_option( self::OPT_ERROR );

		// Safety net: if the browser is closed, cron carries the run to the end.
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
	 * Current progress, for the admin screen and the AJAX poll.
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
	 * Exact at phase boundaries, interpolated within a phase from the number of
	 * slices done against `steps`, and capped below the boundary so the bar never
	 * claims a phase is finished before it is.
	 *
	 * @param array $state Sync state.
	 * @return int 0-100.
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
		$fraction = min( 0.9, $complete / $steps );

		return (int) min( 99, round( $done + ( $phases[ $phase ]['weight'] * $fraction ) ) );
	}

	/**
	 * A live one-line summary of what the current phase has achieved so far.
	 *
	 * @param string $phase Current phase.
	 * @param array  $stats Running statistics.
	 * @return string
	 */
	private static function phase_detail( $phase, array $stats ) {
		$get = static function ( $key ) use ( $stats ) {
			return isset( $stats[ $key ] ) ? (int) $stats[ $key ] : 0;
		};

		switch ( $phase ) {
			case 'schema':
				return __( 'Reading each column\'s description…', 'wpcredits-program-manager' );

			case 'lookups':
				return sprintf(
					/* translators: %s: number of linked names read. */
					__( '%s institution and team names read', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'lookups' ) )
				);

			case 'mentors':
				return sprintf(
					/* translators: 1: mentors read, 2: accounts created, 3: accounts linked. */
					__( '%1$s mentors read · %2$s created · %3$s linked', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'mentors_seen' ) ),
					number_format_i18n( $get( 'created' ) ),
					number_format_i18n( $get( 'linked' ) + $get( 'updated' ) )
				);

			case 'revoke':
				return sprintf(
					/* translators: %s: number of mentors whose role was revoked. */
					__( '%s no longer active', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'revoked' ) )
				);

			case 'reports':
				return sprintf(
					/* translators: %s: number of student reports read. */
					__( '%s student reports read', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'students_seen' ) )
				);

			case 'students':
				return __( 'Joining tutors to students by email…', 'wpcredits-program-manager' );

			case 'assign':
				return sprintf(
					/* translators: 1: assignments written, 2: mentors with students. */
					__( '%1$s assignments across %2$s mentors', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'assigned' ) ),
					number_format_i18n( $get( 'mentors_with_students' ) )
				);
		}

		return '';
	}

	/**
	 * Abandon a stuck run.
	 */
	public static function cancel() {
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Claim the right to run a tick.
	 *
	 * Now that both the browser poll and cron can drive a run, two ticks could
	 * otherwise process the same Airtable page and double-count. add_option()
	 * fails when the row already exists, which makes the claim atomic at the
	 * database level rather than a read-then-write race.
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		if ( add_option( self::OPT_LOCK, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( self::OPT_LOCK );

		// A lock older than the timeout belonged to a tick that died mid-run;
		// leaving it in place would strand the sync forever.
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
	 * @param int|null $budget Seconds of work to attempt. Defaults to BUDGET.
	 * @return void
	 */
	public static function run_tick( $budget = null ) {
		$state = get_option( self::OPT_STATE );

		if ( ! is_array( $state ) || empty( $state['phase'] ) || 'done' === $state['phase'] ) {
			return;
		}

		if ( ! self::acquire_lock() ) {
			return; // Another tick is already working; its progress is what the UI polls.
		}

		$budget   = ( null === $budget ) ? self::BUDGET : max( 1, (int) $budget );
		$deadline = microtime( true ) + $budget;
		$airtable = new WPCPM_Airtable();
		$settings = WPCPM_Settings::get();

		while ( microtime( true ) < $deadline && 'done' !== $state['phase'] ) {
			$phase_before = $state['phase'];

			switch ( $state['phase'] ) {
				case 'schema':
					$result = self::phase_schema( $state, $airtable, $settings );
					break;
				case 'lookups':
					$result = self::phase_lookups( $state, $airtable, $settings );
					break;
				case 'mentors':
					$result = self::phase_mentors( $state, $airtable, $settings );
					break;
				case 'revoke':
					$result = self::phase_revoke( $state, $settings );
					break;
				case 'reports':
					$result = self::phase_reports( $state, $airtable, $settings );
					break;
				case 'students':
					$result = self::phase_students( $state, $airtable, $settings );
					break;
				case 'assign':
					$result = self::phase_assign( $state );
					break;
				default:
					$state['phase'] = 'done';
					$result         = true;
					break;
			}

			// Count the slice against its phase so the bar can interpolate, and
			// stamp the clock so a genuinely wedged run can be told apart from a
			// slow one.
			if ( ! isset( $state['steps'][ $phase_before ] ) ) {
				$state['steps'][ $phase_before ] = 0;
			}
			++$state['steps'][ $phase_before ];
			$state['touched'] = time();

			if ( is_wp_error( $result ) ) {
				update_option( self::OPT_ERROR, $result->get_error_message(), false );
				// Keep the state so the run can be resumed once the cause is fixed.
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

		// More to do: hand off to the next tick. The browser poll normally gets
		// there first; this is the fallback for a closed tab.
		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_TICK );
		}
	}

	/**
	 * Phase 0 - read each field's description straight from Airtable.
	 *
	 * One request, and deliberately non-fatal: descriptions are documentation, so
	 * a token without the `schema.bases:read` scope should degrade to the built-in
	 * fallback text rather than stop ninety accounts being provisioned.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true
	 */
	private static function phase_schema( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$schema = $airtable->fetch_schema();

		if ( is_wp_error( $schema ) ) {
			$state['notices'][] = sprintf(
				/* translators: %s: error message from Airtable. */
				__( 'Could not read field descriptions from Airtable, so the built-in descriptions are being shown instead. Add the "schema.bases:read" scope to the token to use the descriptions written in Airtable. (%s)', 'wpcredits-program-manager' ),
				$schema->get_error_message()
			);
		} else {
			$reports  = isset( $schema[ $settings['reports_table'] ]['fields'] ) ? $schema[ $settings['reports_table'] ]['fields'] : array();
			$students = isset( $schema[ $settings['students_table'] ]['fields'] ) ? $schema[ $settings['students_table'] ]['fields'] : array();

			// Which column holds each table's display value. Kept because a records
			// response never says, and phase_lookups() needs to ask for it by name.
			$primaries = array();
			foreach ( $schema as $table_id => $table ) {
				if ( ! empty( $table['primary'] ) ) {
					$primaries[ $table_id ] = $table['primary'];
				}
			}

			update_option(
				self::OPT_FIELDS,
				array(
					'reports'   => $reports,
					'students'  => $students,
					'primaries' => $primaries,
					'read'      => time(),
				),
				false
			);

			$described = count( array_filter( array_merge( array_values( $reports ), array_values( $students ) ), 'strlen' ) );

			$state['stats']['described'] = $described;
		}

		$state['phase']  = 'lookups';
		$state['offset'] = null;

		return true;
	}

	/**
	 * Phase 0b - build record ID → name maps for the linked-record fields.
	 *
	 * The REST API returns a linked-record cell as an array of bare record IDs -
	 * `["recGzpWO43cQnVYEw"]` - not as objects carrying the linked record's name.
	 * Without this phase the mentor page shows raw `rec…` IDs where the
	 * institution and contribution team should be.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_lookups( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$tables = array(
			'institutions' => array( $settings['institutions_table'], $settings['institutions_name_field'] ),
			'teams'        => array( $settings['teams_table'], $settings['teams_name_field'] ),
			// Sponsors, for the report form's Company field - a linked record like the other two,
			// so it needs the same record-ID-to-name catalog before a student can be offered a
			// choice that is safe to write back.
			'companies'    => array( $settings['sponsors_table'], $settings['sponsors_name_field'] ),
		);

		if ( ! isset( $state['lookups'] ) || ! is_array( $state['lookups'] ) ) {
			$state['lookups'] = array();
		}

		$primaries = self::primary_fields();

		foreach ( $tables as $key => $config ) {
			if ( isset( $state['lookups'][ $key ] ) ) {
				continue; // Already read on an earlier tick.
			}

			list( $table, $fallback_field ) = $config;

			// Ask for exactly one column - the table's primary field, whose name
			// comes from the schema when available and from a setting otherwise.
			// An earlier version requested every field and took the first string it
			// found, on the assumption that the primary field is returned first.
			// It is not: that read Institutions → "Current Stage" and Contribution
			// areas → "Students Reports copy", so institutions showed "Confirmed"
			// and teams showed a list of student names.
			$name_field = ! empty( $primaries[ $table ] ) ? $primaries[ $table ] : $fallback_field;

			$records = $airtable->fetch_all( $table, array( 'fields' => array( $name_field ) ) );

			if ( is_wp_error( $records ) ) {
				return $records;
			}

			$map = array();

			foreach ( $records as $record ) {
				if ( empty( $record['id'] ) || empty( $record['fields'] ) || ! is_array( $record['fields'] ) ) {
					continue;
				}

				$name = trim( WPCPM_Airtable::flatten( isset( $record['fields'][ $name_field ] ) ? $record['fields'][ $name_field ] : '' ) );

				if ( '' !== $name ) {
					$map[ (string) $record['id'] ] = $name;
				}
			}

			// Airtable silently ignores an unknown field name and returns records
			// with no fields at all, which would otherwise produce an empty map and
			// no explanation.
			if ( empty( $map ) && ! empty( $records ) ) {
				$state['notices'][] = sprintf(
					/* translators: 1: Airtable field name, 2: Airtable table ID. */
					__( 'Read %2$s but found no names in the column "%1$s". Check the name field setting for that table.', 'wpcredits-program-manager' ),
					$name_field,
					$table
				);
			}

			$state['lookups'][ $key ]   = $map;
			$state['stats']['lookups'] += count( $map );

			// Persisted, not just held in the run state: the state option is deleted
			// when the run finishes, and the mentor page needs these maps at render
			// time to resolve any record ID an earlier sync already stored.
			$stored         = get_option( self::OPT_LOOKUPS );
			$stored         = is_array( $stored ) ? $stored : array();
			$stored[ $key ] = $map;
			$stored['read'] = time();
			$stored['v']    = self::LOOKUPS_VERSION;
			update_option( self::OPT_LOOKUPS, $stored, false );

			// One table per slice, so the progress bar moves between them.
			return true;
		}

		$state['phase']  = 'mentors';
		$state['offset'] = null;

		return true;
	}

	/**
	 * Primary field names per table ID, as last read from the schema.
	 *
	 * @return array<string, string>
	 */
	public static function primary_fields() {
		$stored = get_option( self::OPT_FIELDS );

		return ( is_array( $stored ) && ! empty( $stored['primaries'] ) ) ? (array) $stored['primaries'] : array();
	}

	/**
	 * Turn a linked-record cell into a comma-separated list of names.
	 *
	 * An ID with no entry in the map is dropped rather than printed: a raw
	 * `recXXXXXXXXXXXXXX` on the page is worse than showing nothing, and the row
	 * then reads as "Not set", which is at least honest.
	 *
	 * @param mixed $value Raw cell value.
	 * @param array $map   Record ID => name.
	 * @return string
	 */
	private static function resolve_links( $value, array $map ) {
		$ids = WPCPM_Airtable::link_ids( $value );

		if ( empty( $ids ) ) {
			// Not a link cell after all - a lookup or plain text field.
			return WPCPM_Airtable::flatten( $value );
		}

		$names = array();

		foreach ( $ids as $id ) {
			if ( isset( $map[ $id ] ) ) {
				$names[] = $map[ $id ];
			}
		}

		return implode( ', ', $names );
	}

	/**
	 * The record ID → name maps as last read from Airtable.
	 *
	 * @return array{institutions: array, teams: array}
	 */
	public static function lookups() {
		$stored = get_option( self::OPT_LOOKUPS );

		// Maps written by an older, buggier version held the wrong column's values,
		// so they are discarded outright rather than shown.
		if ( is_array( $stored ) && (int) ( isset( $stored['v'] ) ? $stored['v'] : 0 ) !== self::LOOKUPS_VERSION ) {
			$stored = array();
		}

		return array(
			'institutions' => ( is_array( $stored ) && ! empty( $stored['institutions'] ) ) ? (array) $stored['institutions'] : array(),
			'teams'        => ( is_array( $stored ) && ! empty( $stored['teams'] ) ) ? (array) $stored['teams'] : array(),
			'companies'    => ( is_array( $stored ) && ! empty( $stored['companies'] ) ) ? (array) $stored['companies'] : array(),
		);
	}

	/**
	 * Whether a string is an Airtable record ID.
	 *
	 * An alias of `WPCPM_Airtable::is_record_id()`, kept for one release. The pattern lived
	 * here first, which meant the whole plugin, the Institutions policy included, was reading a
	 * fact about Airtable off the Mentors sync; the client now owns it and new code calls the
	 * client. Removed with the next major version.
	 *
	 * @param string $value Value to test.
	 * @return bool
	 */
	public static function is_record_id( $value ) {
		return WPCPM_Airtable::is_record_id( $value );
	}

	/**
	 * Resolve a stored value that may still contain record IDs.
	 *
	 * Applied when rendering, not only when syncing. Mentee rows are cached in
	 * user meta, so rows written before linked-record resolution existed still hold
	 * raw IDs - and fixing the sync alone would leave those on screen until someone
	 * happened to re-sync. An ID with no name is dropped rather than printed, so the
	 * row reads "Not set" instead of leaking `recXXXXXXXXXXXXXX`.
	 *
	 * @param string $value Stored value; may be a name, an ID, or a list of either.
	 * @param string $type  Either `institutions` or `teams`.
	 * @return string
	 */
	public static function resolve_stored( $value, $type ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		// Fast path: nothing that looks like an ID, so it is already resolved.
		if ( false === strpos( $value, 'rec' ) ) {
			return $value;
		}

		$lookups = self::lookups();
		$map     = isset( $lookups[ $type ] ) ? $lookups[ $type ] : array();
		$out     = array();

		foreach ( explode( ',', $value ) as $part ) {
			$part = trim( $part );

			if ( '' === $part ) {
				continue;
			}

			if ( ! self::is_record_id( $part ) ) {
				$out[] = $part;
				continue;
			}

			if ( isset( $map[ $part ] ) ) {
				$out[] = $map[ $part ];
			}
		}

		return implode( ', ', $out );
	}

	/**
	 * Whether any mentor's stored students still hold unresolved record IDs.
	 *
	 * Drives the "run a sync" prompt on the Mentors screen.
	 *
	 * @return bool
	 */
	public static function has_unresolved_links() {
		$lookups = self::lookups();

		// With no maps read yet, every linked value is unresolvable by definition.
		if ( empty( $lookups['institutions'] ) && empty( $lookups['teams'] ) ) {
			$mentors = get_users(
				array(
					'role'       => WPCPM_Roles::ROLE_MENTOR,
					'number'     => 1,
					'fields'     => 'ID',
					'meta_query' => array(
						array(
							'key'     => self::META_MENTEES,
							'compare' => 'EXISTS',
						),
					),
				)
			);

			return ! empty( $mentors );
		}

		return false;
	}

	/**
	 * The refusal a sync answers when it has no statuses to filter the base by.
	 *
	 * `formula_in()` turns an empty list into an empty formula, and `fetch_page()` reads an
	 * empty formula as "no filter": a blank status setting does not fetch nobody, it fetches
	 * every row of the table. For the Students Reports table that is an account, a Student role
	 * and an institution stamp for every SPAM and rejected row; for the mentors table, a Mentor
	 * role for everyone who ever applied; and a blanked current-student list on its own revokes
	 * every current student on the next run, because none of them is fetched any more. Each of
	 * those runs would finish with no error recorded. `WPCPM_Settings::save()` puts the default
	 * back when a manager blanks a field; this is for an option stored before that guard, and
	 * it takes the shape of the missing-token refusal so every caller that reports that one
	 * reports this one too.
	 *
	 * @param array $settings      Plugin settings.
	 * @param bool  $mentor_status Whether the caller also filters mentors and so needs `mentor_status`.
	 * @return WP_Error|null Null when the lists are usable.
	 */
	public static function empty_statuses_error( array $settings, $mentor_status = false ) {
		if ( empty( self::tracked_statuses( $settings )['active'] ) ) {
			return new WP_Error( 'wpcpm_no_statuses', __( 'No current student status is set, so a sync would read every row of the Students Reports table as a current student. Add at least one under "Currently mentoring" on the Settings screen before syncing.', 'wpcredits-program-manager' ) );
		}

		if ( $mentor_status && '' === trim( isset( $settings['mentor_status'] ) ? (string) $settings['mentor_status'] : '' ) ) {
			return new WP_Error( 'wpcpm_no_statuses', __( 'The mentor status to sync is blank, so a sync would give every row of the mentors table a Mentor account whatever its status. Set it on the Settings screen before syncing.', 'wpcredits-program-manager' ) );
		}

		return null;
	}

	/**
	 * The Airtable statuses this module tracks, split into current and finished.
	 *
	 * A pure reading of the settings: an empty `active` list is returned as empty, not
	 * defaulted, because two other places already refuse it. `WPCPM_Settings::save()` puts the
	 * default back when a manager blanks the field, and both syncs' `start()` refuse to run on
	 * an empty list through `empty_statuses_error()`, since an empty list is an empty formula
	 * and an empty formula fetches the whole table.
	 *
	 * @param array|null $settings Optional settings override.
	 * @return array{active: string[], past: string[], all: string[]}
	 */
	public static function tracked_statuses( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : WPCPM_Settings::get();

		$clean = static function ( $values ) {
			return array_values( array_unique( array_filter( array_map( 'trim', (array) $values ), 'strlen' ) ) );
		};

		$active = $clean( isset( $settings['student_statuses'] ) ? $settings['student_statuses'] : array() );
		$past   = $clean( isset( $settings['past_statuses'] ) ? $settings['past_statuses'] : array() );

		// A status listed in both lists counts as active. A configuration slip
		// should never quietly move a mentor's current student into the archive.
		$past = array_values( array_diff( $past, $active ) );

		return array(
			'active' => $active,
			'past'   => $past,
			'all'    => array_merge( $active, $past ),
		);
	}

	/**
	 * Field descriptions as last read from Airtable.
	 *
	 * @return array{reports: array, students: array}
	 */
	public static function field_meta() {
		$stored = get_option( self::OPT_FIELDS );

		return array(
			'reports'  => ( is_array( $stored ) && ! empty( $stored['reports'] ) ) ? (array) $stored['reports'] : array(),
			'students' => ( is_array( $stored ) && ! empty( $stored['students'] ) ) ? (array) $stored['students'] : array(),
		);
	}

	/**
	 * The description to show for one field.
	 *
	 * Airtable's own description wins whenever the team has written one, with the
	 * fallbacks below used until then.
	 *
	 * Nothing renders these since the Description column was dropped from the
	 * student table in 1.9.0. Kept as a public API - the `wpcpm_field_descriptions`
	 * filter is documented, and the schema read that feeds it is needed anyway for
	 * `primary_fields()`, which is how linked records resolve to names.
	 *
	 * @param string $table Either `reports` or `students`.
	 * @param string $field Airtable field name.
	 * @return string
	 */
	public static function field_description( $table, $field ) {
		$meta   = self::field_meta();
		$stored = isset( $meta[ $table ] ) ? (array) $meta[ $table ] : array();
		$needle = trim( (string) $field );

		// Compared on trimmed names: the Students column is named `Tutor ` with a
		// trailing space, so an exact match against `Tutor` would silently miss
		// the description written in Airtable.
		foreach ( $stored as $name => $description ) {
			if ( '' !== trim( (string) $description ) && trim( (string) $name ) === $needle ) {
				return trim( (string) $description );
			}
		}

		$fallbacks = apply_filters(
			'wpcpm_field_descriptions',
			array(
				'Status'                  => __( 'Where the student currently is in the program.', 'wpcredits-program-manager' ),
				'Email'                   => __( 'The student\'s email address, as recorded in the program. Also what their tutor is matched on.', 'wpcredits-program-manager' ),
				// One row shows both dates, so this covers the pair.
				'Internship Start Date'   => __( 'When the student\'s internship starts and finishes.', 'wpcredits-program-manager' ),
				'Internship End Date'     => __( 'When the student\'s internship finishes.', 'wpcredits-program-manager' ),
				'Tutor'                   => __( 'The teacher at the student\'s institution who supervises their internship.', 'wpcredits-program-manager' ),
				'Educational institution' => __( 'The school or university the student comes from.', 'wpcredits-program-manager' ),
				'WordPress Profile'       => __( 'The student\'s profile on WordPress.org, where their contributions are recorded.', 'wpcredits-program-manager' ),
				'Slack Name'              => __( 'The student\'s handle in the Making WordPress Slack, for contacting them.', 'wpcredits-program-manager' ),
				'Main Contribution Team'  => __( 'The WordPress contributor team the student\'s project belongs to.', 'wpcredits-program-manager' ),
				'Personal Website URL'    => __( 'The website the student built during the program.', 'wpcredits-program-manager' ),
				'Personal link'           => __( 'The student\'s reporting form, prefilled for their record.', 'wpcredits-program-manager' ),
				'50h personal link'       => __( 'The reporting form for the 50-hour track, prefilled for their record.', 'wpcredits-program-manager' ),
			)
		);

		// The Students column is literally named with a trailing space.
		$key = trim( $field );

		return isset( $fallbacks[ $key ] ) ? (string) $fallbacks[ $key ] : '';
	}

	/**
	 * Phase 1 - page through active mentors, provisioning an account for each.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_mentors( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$fields = self::fields();

		$page = $airtable->fetch_page(
			$settings['mentors_table'],
			array(
				'formula' => $airtable->formula_in( $fields['mentor_status'], array( $settings['mentor_status'] ) ),
				'fields'  => array( $fields['mentor_name'], $fields['mentor_profile'], $fields['mentor_email'], $fields['mentor_status'] ),
				'offset'  => $state['offset'],
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		foreach ( $page['records'] as $record ) {
			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

			$mentor = array(
				'record_id' => isset( $record['id'] ) ? (string) $record['id'] : '',
				'name'      => trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_name'] ] ) ? $cells[ $fields['mentor_name'] ] : '' ) ),
				'profile'   => trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_profile'] ] ) ? $cells[ $fields['mentor_profile'] ] : '' ) ),
				'email'     => trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_email'] ] ) ? $cells[ $fields['mentor_email'] ] : '' ) ),
			);

			if ( '' === $mentor['record_id'] ) {
				continue;
			}

			++$state['stats']['mentors_seen'];

			$user_id = self::provision_mentor( $mentor, $state );

			$state['mentors'][ $mentor['record_id'] ] = array(
				'user_id' => $user_id,
				'name'    => $mentor['name'],
			);
		}

		$state['offset'] = $page['offset'];

		if ( empty( $page['offset'] ) ) {
			$state['phase']  = 'revoke';
			$state['offset'] = null;
		}

		return true;
	}

	/**
	 * Create or link the WordPress account for one mentor.
	 *
	 * @param array $mentor Mentor data from Airtable.
	 * @param array $state  Sync state, by reference.
	 * @return int User ID, or 0 when the mentor could not be provisioned.
	 */
	private static function provision_mentor( array $mentor, array &$state ) {
		$login = self::wporg_username( $mentor['profile'] );
		$email = sanitize_email( $mentor['email'] );

		if ( '' === $login ) {
			$state['notices'][] = sprintf(
				/* translators: 1: mentor name, 2: raw profile value from Airtable. */
				__( 'Skipped %1$s - no WordPress.org username could be read from "%2$s".', 'wpcredits-program-manager' ),
				$mentor['name'] ? $mentor['name'] : $mentor['record_id'],
				$mentor['profile']
			);
			++$state['stats']['skipped'];

			return 0;
		}

		if ( ! is_email( $email ) ) {
			$state['notices'][] = sprintf(
				/* translators: %s: mentor name. */
				__( 'Skipped %s - Airtable has no valid email address, and WordPress cannot create an account without one.', 'wpcredits-program-manager' ),
				$mentor['name'] ? $mentor['name'] : $login
			);
			++$state['stats']['skipped'];

			return 0;
		}

		// Identify in order of reliability: the record ID we stored ourselves, then
		// the email address, then the login. Anything else risks writing a mentor's
		// data onto a stranger's account.
		$user = self::find_by_record_id( $mentor['record_id'] );
		$how  = 'record';

		if ( ! $user ) {
			$user = get_user_by( 'email', $email );
			$how  = 'email';
		}

		if ( ! $user ) {
			$user = get_user_by( 'login', $login );
			$how  = 'login';
		}

		if ( $user instanceof WP_User ) {
			// A login already claimed by a different mentor record is a genuine
			// clash - two people cannot share one account.
			$claimed = (string) get_user_meta( $user->ID, self::META_RECORD_ID, true );

			if ( '' !== $claimed && $claimed !== $mentor['record_id'] ) {
				$state['notices'][] = sprintf(
					/* translators: 1: mentor name, 2: WordPress username. */
					__( 'Skipped %1$s - the account "%2$s" is already linked to a different Airtable mentor record. Resolve the duplicate in Airtable.', 'wpcredits-program-manager' ),
					$mentor['name'] ? $mentor['name'] : $login,
					$user->user_login
				);
				++$state['stats']['conflicts'];

				return 0;
			}

			self::link_existing_user( $user, $mentor, $how, $state );

			return $user->ID;
		}

		return self::create_mentor_user( $login, $email, $mentor, $state );
	}

	/**
	 * Attach mentor data to an account that already exists.
	 *
	 * @param WP_User $user   Existing user.
	 * @param array   $mentor Mentor data.
	 * @param string  $how    How the user was matched: record, email or login.
	 * @param array   $state  Sync state, by reference.
	 */
	private static function link_existing_user( WP_User $user, array $mentor, $how, array &$state ) {
		$was_linked = '' !== (string) get_user_meta( $user->ID, self::META_RECORD_ID, true );

		update_user_meta( $user->ID, self::META_RECORD_ID, $mentor['record_id'] );
		update_user_meta( $user->ID, self::META_PROFILE, $mentor['profile'] );
		update_user_meta( $user->ID, self::META_ACTIVE, 1 );

		$is_admin  = in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true );
		$is_mentor = in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true );

		if ( ! $is_mentor && ! $is_admin ) {
			// add_role() rather than set_role(): the account may legitimately be an
			// author or editor as well, and a sync should not demote anyone.
			$user->add_role( WPCPM_Roles::ROLE_MENTOR );
			$state['notices'][] = sprintf(
				/* translators: 1: WordPress username, 2: mentor name. */
				__( 'Added the Mentor role to the existing account "%1$s" (%2$s).', 'wpcredits-program-manager' ),
				$user->user_login,
				$mentor['name']
			);
		}

		if ( $mentor['profile'] && $mentor['profile'] !== $user->user_url ) {
			wp_update_user(
				array(
					'ID'       => $user->ID,
					'user_url' => esc_url_raw( $mentor['profile'] ),
				)
			);
		}

		if ( $was_linked ) {
			++$state['stats']['updated'];
		} else {
			++$state['stats']['linked'];
			if ( 'login' === $how ) {
				$state['notices'][] = sprintf(
					/* translators: 1: WordPress username, 2: mentor name. */
					__( 'Linked mentor record to the pre-existing account "%1$s" (%2$s) by username. Confirm it is the same person.', 'wpcredits-program-manager' ),
					$user->user_login,
					$mentor['name']
				);
			}
		}
	}

	/**
	 * Create a fresh Mentor account.
	 *
	 * @param string $login  WordPress.org username.
	 * @param string $email  Email address.
	 * @param array  $mentor Mentor data.
	 * @param array  $state  Sync state, by reference.
	 * @return int User ID, or 0 on failure.
	 */
	private static function create_mentor_user( $login, $email, array $mentor, array &$state ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $mentor['name'] ? $mentor['name'] : $login,
				'nickname'     => $mentor['name'] ? $mentor['name'] : $login,
				'user_url'     => esc_url_raw( $mentor['profile'] ),
				'role'         => WPCPM_Roles::ROLE_MENTOR,
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

		update_user_meta( $user_id, self::META_RECORD_ID, $mentor['record_id'] );
		update_user_meta( $user_id, self::META_PROFILE, $mentor['profile'] );
		update_user_meta( $user_id, self::META_ACTIVE, 1 );

		++$state['stats']['created'];

		// wp_insert_user() sends nothing on its own. Accounts are created with a
		// random password, so an invite is the only way in - but sending ~90 of
		// them is a decision for a human, hence the opt-in setting.
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
	 * Phase 2 - handle mentors that are no longer Active in Airtable.
	 *
	 * @param array $state    Sync state, by reference.
	 * @param array $settings Plugin settings.
	 * @return true
	 */
	private static function phase_revoke( array &$state, array $settings ) {
		$active_ids = array_keys( is_array( $state['mentors'] ) ? $state['mentors'] : array() );

		$linked = get_users(
			array(
				'number'     => -1,
				'fields'     => 'ID',
				'meta_query' => array(
					array(
						'key'     => self::META_RECORD_ID,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $linked as $user_id ) {
			$record_id = (string) get_user_meta( $user_id, self::META_RECORD_ID, true );

			if ( '' === $record_id || in_array( $record_id, $active_ids, true ) ) {
				continue;
			}

			update_user_meta( $user_id, self::META_ACTIVE, 0 );
			// Their student list is no longer theirs to see.
			delete_user_meta( $user_id, self::META_MENTEES );

			if ( 'revoke' === $settings['on_inactive'] ) {
				$user = new WP_User( $user_id );

				// Never touch an administrator's roles, and never delete an account.
				if ( ! in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true )
					&& in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true ) ) {
					$user->remove_role( WPCPM_Roles::ROLE_MENTOR );

					if ( empty( $user->roles ) ) {
						$user->set_role( 'subscriber' );
					}

					++$state['stats']['revoked'];
				}
			}
		}

		$state['phase']  = 'reports';
		$state['offset'] = null;

		return true;
	}

	/**
	 * Phase 3 - page through the student reports that belong to the program.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_reports( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$fields = self::fields();

		$wanted = array(
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
			$fields['report_hours'],
			$fields['report_link'],
			$fields['report_link_50h'],
			$fields['report_link_dev'],
		);

		$statuses = self::tracked_statuses( $settings );

		$page = $airtable->fetch_page(
			$settings['reports_table'],
			array(
				// Current and finished students in one pass: the mentor page shows
				// both, in separate sections.
				'formula' => $airtable->formula_in( $fields['report_status'], $statuses['all'] ),
				'fields'  => $wanted,
				'offset'  => $state['offset'],
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		foreach ( $page['records'] as $record ) {
			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

			$mentor_ids = WPCPM_Airtable::link_ids( isset( $cells[ $fields['report_mentor'] ] ) ? $cells[ $fields['report_mentor'] ] : array() );

			if ( empty( $mentor_ids ) ) {
				++$state['stats']['unassigned'];
				continue;
			}

			$status  = WPCPM_Airtable::flatten( isset( $cells[ $fields['report_status'] ] ) ? $cells[ $fields['report_status'] ] : '' );
			$profile = WPCPM_Airtable::flatten( isset( $cells[ $fields['report_profile'] ] ) ? $cells[ $fields['report_profile'] ] : '' );
			$email   = WPCPM_Airtable::flatten( isset( $cells[ $fields['report_email'] ] ) ? $cells[ $fields['report_email'] ] : '' );

			// Each track has its own reporting form. All three formula fields are always
			// populated, so the status is what decides which one is the student's real link.
			$track = WPCPM_Program::track( $status );
			$key   = $fields[ self::link_field( $track ) ];
			$link  = WPCPM_Airtable::flatten( isset( $cells[ $key ] ) ? $cells[ $key ] : '' );

			$row = array(
				'record_id'      => isset( $record['id'] ) ? (string) $record['id'] : '',
				'name'           => trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['report_name'] ] ) ? $cells[ $fields['report_name'] ] : '' ) ),
				'email'          => $email,
				'email_key'      => strtolower( trim( $email ) ),
				'status'         => $status,
				'is_past'        => in_array( $status, $statuses['past'], true ),
				'start'          => WPCPM_Airtable::flatten( isset( $cells[ $fields['report_start'] ] ) ? $cells[ $fields['report_start'] ] : '' ),
				'end'            => WPCPM_Airtable::flatten( isset( $cells[ $fields['report_end'] ] ) ? $cells[ $fields['report_end'] ] : '' ),
				// Linked records: resolved through the maps built in phase_lookups(),
				// because the API sends only record IDs here.
				'institution'    => self::resolve_links(
					isset( $cells[ $fields['report_instituton'] ] ) ? $cells[ $fields['report_instituton'] ] : '',
					isset( $state['lookups']['institutions'] ) ? (array) $state['lookups']['institutions'] : array()
				),
				'profile'        => $profile,
				'username'       => self::wporg_username( $profile ),
				'slack'          => WPCPM_Airtable::flatten( isset( $cells[ $fields['report_slack'] ] ) ? $cells[ $fields['report_slack'] ] : '' ),
				'team'           => self::resolve_links(
					isset( $cells[ $fields['report_team'] ] ) ? $cells[ $fields['report_team'] ] : '',
					isset( $state['lookups']['teams'] ) ? (array) $state['lookups']['teams'] : array()
				),
				'website'        => WPCPM_Airtable::flatten( isset( $cells[ $fields['report_website'] ] ) ? $cells[ $fields['report_website'] ] : '' ),
				'hours'          => WPCPM_Airtable::flatten( isset( $cells[ $fields['report_hours'] ] ) ? $cells[ $fields['report_hours'] ] : '' ),
				'link'           => $link,
				'tutor'          => '',
				// Joined from the Students table below, by email.
				'field_of_study' => '',
				'accessibility'  => '',
				'mentor_ids'     => $mentor_ids,
			);

			$state['reports'][] = $row;

			if ( $row['is_past'] ) {
				++$state['stats']['past_seen'];
			} else {
				++$state['stats']['students_seen'];
			}
		}

		$state['offset'] = $page['offset'];

		if ( empty( $page['offset'] ) ) {
			$state['phase']  = 'students';
			$state['offset'] = null;
		}

		return true;
	}

	/**
	 * Phase 4 - build an email → tutor map from the Students table.
	 *
	 * Tutor lives only on Students, while every other field the mentor page shows
	 * lives on Students Reports, so the two have to be joined on email. The whole
	 * table is read rather than the In-Sensei slice: a report and its student
	 * record can disagree about status, and a mismatch should not silently cost
	 * the mentor the tutor's name.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_students( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$fields = self::fields();

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
				// Fall back to the linked Tutors table when the text field is blank.
				$tutor = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['student_tutors'] ] ) ? $cells[ $fields['student_tutors'] ] : '' ) );
			}

			if ( '' !== $tutor ) {
				$state['tutors'][ $email ] = $tutor;
			}

			// Parallel maps rather than more keys on the tutor map, so a run already in
			// flight when this shipped keeps working: a missing key just reads as absent.
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
			$state['phase']  = 'assign';
			$state['offset'] = null;
		}

		return true;
	}

	/**
	 * Phase 5 - group the reports by mentor and write them to user meta.
	 *
	 * @param array $state Sync state, by reference.
	 * @return true
	 */
	private static function phase_assign( array &$state ) {
		$by_mentor = array();

		foreach ( (array) $state['reports'] as $row ) {
			if ( isset( $state['study'][ $row['email_key'] ] ) ) {
				$row['field_of_study'] = $state['study'][ $row['email_key'] ];
			}

			if ( isset( $state['access'][ $row['email_key'] ] ) ) {
				$row['accessibility'] = $state['access'][ $row['email_key'] ];
			}

			if ( isset( $state['tutors'][ $row['email_key'] ] ) ) {
				$row['tutor'] = $state['tutors'][ $row['email_key'] ];
			}

			$mentor_ids = $row['mentor_ids'];
			unset( $row['mentor_ids'], $row['email_key'] );

			foreach ( $mentor_ids as $mentor_id ) {
				if ( ! isset( $state['mentors'][ $mentor_id ] ) ) {
					// A student assigned to a mentor who is not Active - real, and
					// worth surfacing rather than dropping on the floor.
					++$state['stats']['orphaned'];
					continue;
				}

				$by_mentor[ $mentor_id ][] = $row;
			}
		}

		foreach ( (array) $state['mentors'] as $record_id => $mentor ) {
			$user_id = isset( $mentor['user_id'] ) ? (int) $mentor['user_id'] : 0;

			if ( ! $user_id ) {
				continue;
			}

			$rows = isset( $by_mentor[ $record_id ] ) ? $by_mentor[ $record_id ] : array();

			usort( $rows, array( __CLASS__, 'compare_mentees' ) );

			if ( empty( $rows ) ) {
				delete_user_meta( $user_id, self::META_MENTEES );
				delete_user_meta( $user_id, self::META_COUNT );
				delete_user_meta( $user_id, self::META_PAST_COUNT );
			} else {
				$active = 0;
				$past   = 0;
				foreach ( $rows as $row ) {
					if ( ! empty( $row['is_past'] ) ) {
						++$past;
					} else {
						++$active;
					}
				}

				update_user_meta( $user_id, self::META_MENTEES, $rows );
				// Stored separately so the admin list can show count columns without
				// unserializing every mentor's full student array. META_COUNT stays
				// the *current* count, which is what it has always meant.
				update_user_meta( $user_id, self::META_COUNT, $active );
				update_user_meta( $user_id, self::META_PAST_COUNT, $past );

				$state['stats']['assigned'] += count( $rows );
				++$state['stats']['mentors_with_students'];
			}

			update_user_meta( $user_id, self::META_UPDATED, time() );
		}

		$state['phase'] = 'done';

		return true;
	}

	/**
	 * Sort mentees by internship end date, soonest first, then by name.
	 *
	 * Rows with no end date sort last - an unknown date is not an urgent one.
	 *
	 * @param array $a First row.
	 * @param array $b Second row.
	 * @return int
	 */
	public static function compare_mentees( $a, $b ) {
		$a_past = ! empty( $a['is_past'] );
		$b_past = ! empty( $b['is_past'] );

		// Current students first, so the stored order already matches the page.
		if ( $a_past !== $b_past ) {
			return $a_past ? 1 : -1;
		}

		$a_end = isset( $a['end'] ) ? (string) $a['end'] : '';
		$b_end = isset( $b['end'] ) ? (string) $b['end'] : '';

		if ( ( '' === $a_end ) !== ( '' === $b_end ) ) {
			return ( '' === $a_end ) ? 1 : -1;
		}

		if ( $a_end !== $b_end ) {
			// Current students by soonest deadline; finished students by most
			// recently finished, which is the useful end of each list.
			return $a_past ? strcmp( $b_end, $a_end ) : strcmp( $a_end, $b_end );
		}

		return strcasecmp(
			isset( $a['name'] ) ? $a['name'] : '',
			isset( $b['name'] ) ? $b['name'] : ''
		);
	}

	/**
	 * Store the run summary and clear the working state.
	 *
	 * @param array $state Final state.
	 */
	private static function finish( array $state ) {
		$report = array(
			'stats'    => isset( $state['stats'] ) ? $state['stats'] : self::empty_stats(),
			'notices'  => isset( $state['notices'] ) ? array_slice( (array) $state['notices'], 0, 100 ) : array(),
			'started'  => isset( $state['started'] ) ? (int) $state['started'] : 0,
			'finished' => time(),
		);

		update_option( self::OPT_REPORT, $report, false );
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
			'described'             => 0,
			'lookups'               => 0,
			'mentors_seen'          => 0,
			'created'               => 0,
			'linked'                => 0,
			'updated'               => 0,
			'invited'               => 0,
			'revoked'               => 0,
			'skipped'               => 0,
			'conflicts'             => 0,
			'students_seen'         => 0,
			'past_seen'             => 0,
			'assigned'              => 0,
			'unassigned'            => 0,
			'orphaned'              => 0,
			'mentors_with_students' => 0,
		);
	}

	/**
	 * Find the account already linked to an Airtable mentor record.
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
	 * Reduce a WordPress.org profile reference to its username.
	 *
	 * The Airtable column is free text and in practice holds full URLs, bare
	 * usernames, `@handles`, URLs ending in `/profile/`, `http://` variants and at
	 * least one misspelled host, so every shape collapses to the last path
	 * segment rather than being parsed strictly.
	 *
	 * @param string $raw Raw profile value.
	 * @return string Lowercased username, or an empty string.
	 */
	public static function wporg_username( $raw ) {
		$value = trim( (string) $raw );

		if ( '' === $value ) {
			return '';
		}

		$value = trim( $value, " \t\n\r\0\x0B<>" );
		$value = ltrim( $value, '@' );

		// Drop the scheme, then any query string or fragment.
		$value = preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $value );
		$value = preg_split( '/[?#]/', $value )[0];

		if ( false !== strpos( $value, '/' ) ) {
			$parts = array_values( array_filter( explode( '/', $value ), 'strlen' ) );

			// The first segment is the host when it looks like a domain.
			if ( ! empty( $parts ) && false !== strpos( $parts[0], '.' ) ) {
				array_shift( $parts );
			}

			// Trailing BuddyPress components are not part of the username.
			$parts = array_values(
				array_diff( $parts, array( 'profile', 'profiles', 'activity', 'badges', 'notifications', 'messages' ) )
			);

			$value = empty( $parts ) ? '' : end( $parts );
		}

		// WordPress.org usernames allow letters, numbers, dashes, dots, underscores.
		$value = preg_replace( '/[^A-Za-z0-9._\-]/', '', $value );

		return strtolower( $value );
	}

	/**
	 * Send one mentor their login invitation.
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public static function send_invite( $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'wpcpm_no_user', __( 'That user does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( ! in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true ) ) {
			return new WP_Error( 'wpcpm_not_mentor', __( 'That account does not hold the Mentor role.', 'wpcredits-program-manager' ) );
		}

		wp_new_user_notification( $user->ID, null, 'user' );
		update_user_meta( $user->ID, 'wpcpm_mentor_invited', time() );

		return true;
	}
}
