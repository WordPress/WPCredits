<?php
/**
 * Institutions module - Airtable sync.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the Institutions and Countries tables into the site's own copies and keeps the
 * agreement gate in step with them.
 *
 * Built on the same resumable, budgeted cron machinery as the Students sync, and shaped
 * like it on purpose: the state option, the phase table, the lock, the tick budget and the
 * progress payload are the same, so a reader who knows one knows the other. The work is
 * far smaller (106 records, two pages) but it still leaves the site, and a run that cannot
 * be resumed is a run that fails on the one night the API is slow.
 *
 * Four phases. `countries` refreshes the routing map, first, because the records phase
 * names each institution's country through it. `records` reads the Institutions table with
 * an explicit column list, writes `WPCPM_Institutions_Index` in one go, then rebuilds every
 * `wpcpm_agreement_<record>` option from what the base says (T12 in the design). `provision`
 * creates the one account an institution starts with from its `Contact Email`, and only for
 * an institution with no membership history at all. `revoke` closes the gate and detaches
 * the members of every institution that has left the pipeline.
 */
class WPCPM_Institutions_Sync {

	const CRON_DAILY = 'wpcpm_institutions_sync_daily';

	const CRON_TICK = 'wpcpm_institutions_sync_tick';

	const OPT_STATE  = 'wpcpm_institutions_state';
	const OPT_REPORT = 'wpcpm_institutions_report';
	const OPT_LAST   = 'wpcpm_institutions_last_sync';
	const OPT_ERROR  = 'wpcpm_institutions_last_error';
	const OPT_LOCK   = 'wpcpm_institutions_lock';

	const BUDGET       = 18;
	const BUDGET_AJAX  = 8;
	const LOCK_TIMEOUT = 120;

	/**
	 * How many hours after the students sync's next run the daily run is placed.
	 *
	 * Offset so the two never contend for the same Airtable rate limit or the same PHP
	 * worker on the run that follows an upgrade, which is when both have the most to do.
	 * Not a multiple of three: the students sync recurs every three hours, so six would put
	 * this run on one of its slots and WP-Cron would fire both in the same request.
	 * Hours, not seconds: a constant expression on `HOUR_IN_SECONDS` would be evaluated
	 * the first time the class is touched, and a test that loads it before defining the
	 * time constants would fatal there instead of in the method that uses it.
	 */
	const SCHEDULE_OFFSET_HOURS = 4;

	/**
	 * Agreement options rebuilt per slice, once the index is written.
	 *
	 * Each rebuild is an option write and a post query; twenty-five of them sit well inside
	 * one browser-driven tick and still move the bar between slices.
	 */
	const REBUILD_BATCH = 25;

	/**
	 * Accounts created per slice of the provision phase.
	 *
	 * Small on purpose. Each one is a user insert, a stamp, an audit row and a queued
	 * invitation, and a slice that runs long is a slice the browser poll cannot show the end
	 * of. The pending list lives in the run state, so the next tick carries on where this one
	 * stopped rather than starting the phase again.
	 */
	const PROVISION_BATCH = 5;

	/**
	 * The `WP_Error` code every refused provisioning carries.
	 *
	 * One code with the reason in its data, rather than a code per reason: callers switch on
	 * `get_error_data()['reason']` against the `BLOCK_` constants, and a caller that only
	 * wants to know whether it worked reads `is_wp_error()` and nothing else.
	 */
	const PROVISION_ERROR = 'wpcpm_provision_refused';

	/*
	 * Why an institution may not be provisioned, in the order `provision_block()` decides
	 * them: the cheap facts from the index first, then the two membership queries, then the
	 * agreement and the account lookup, so most of the expensive questions are only asked
	 * about a row that has passed every free one.
	 *
	 * Membership is asked before the agreement even though it is the dearer question, because
	 * the two answers are not interchangeable: an institution that already has an account is
	 * that whatever its agreement says, and a revocation deletes the gate's option while
	 * leaving the account and the stage exactly where they were.
	 */
	const BLOCK_NOT_INDEXED   = 'not_indexed';
	const BLOCK_NOT_CONFIRMED = 'not_confirmed';
	const BLOCK_NO_EMAIL      = 'no_email';
	const BLOCK_HAS_MEMBER    = 'has_member';
	const BLOCK_FORMER_MEMBER = 'former_member';
	const BLOCK_NO_AGREEMENT  = 'no_agreement';
	const BLOCK_CONFLICT      = 'account_exists';

	/**
	 * The date the consent checkbox joined the Airtable form.
	 *
	 * Every consent-ticked record was created on or after it and none before; the manager
	 * screen's consent report counts rows against it. Recorded here, beside the sync that
	 * stores `created`, so the two are read together.
	 */
	const CONSENT_QUESTION_ADDED = '2026-07-20';

	/**
	 * Phases, in order. See WPCPM_Mentors_Sync::phases() for how the weights are used;
	 * `records` is weighted heavily because it is the phase that pages the base and then
	 * rebuilds an option per institution.
	 *
	 * @return array<string, array{label: string, weight: int, steps: int}>
	 */
	public static function phases() {
		return array(
			'countries' => array(
				'label'  => __( 'Reading the Countries table', 'wpcredits-program-manager' ),
				'weight' => 15,
				'steps'  => 1,
			),
			'records'   => array(
				'label'  => __( 'Reading the Institutions table', 'wpcredits-program-manager' ),
				'weight' => 45,
				'steps'  => 7,
			),
			'provision' => array(
				'label'  => __( 'Creating the institution accounts', 'wpcredits-program-manager' ),
				'weight' => 25,
				// Four slices, not one: the phase creates accounts `PROVISION_BATCH` at a
				// time, and a bar that sat still through all of them would read as a stall.
				'steps'  => 4,
			),
			'revoke'    => array(
				'label'  => __( 'Checking which institutions have left the pipeline', 'wpcredits-program-manager' ),
				'weight' => 15,
				'steps'  => 1,
			),
		);
	}

	/**
	 * The Institutions columns the records phase asks for, keyed by what the row calls them.
	 *
	 * An explicit list, and only ever this list. The table has 45 columns and most of the
	 * rest are prose: the application's "why are you interested" answer, comments, notes,
	 * a department. None of that belongs in an option every manager screen reads. The name
	 * column may be overridden by the `institutions_name_field` setting, as the mentors sync
	 * allows; every other name is pinned by bin/fixtures/institutions-table-fields.json.
	 *
	 * Reading a named column is also the fix for a bug this table has already produced:
	 * `WPCPM_Mentors_Sync::phase_lookups()` once asked for every field and took the first
	 * string it found, on the assumption that the primary field comes first. It does not.
	 * That read Institutions as "Current Stage", and every student's institution rendered
	 * as "Confirmed".
	 *
	 * @return array<string, string>
	 */
	public static function fields() {
		return array(
			'name'             => 'Name',
			'stage'            => 'Current Stage',
			'country'          => 'Country',
			'city'             => 'City',
			'website'          => 'Website',
			'contact_person'   => 'Contact Person',
			'contact_email'    => 'Contact Email',
			'confirmed_on'     => 'Confirmed on',
			'consent'          => 'Privacy Policy Compliance',
			'agr_status'       => 'Agreement Status',
			'agr_kind'         => 'Agreement Kind',
			'agr_accepted_on'  => 'Agreement Accepted On',
			'agr_signed_on'    => 'Agreement Signed On',
			'agr_accepted_by'  => 'Agreement Accepted By',
			'agr_document'     => 'Agreement Document',
			'agr_submitted_on' => 'Agreement Submitted On',
			'agr_template'     => 'Agreement Template Version',
		);
	}

	/**
	 * Register cron hooks.
	 */
	public static function register_cron() {
		add_action( self::CRON_DAILY, array( __CLASS__, 'cron_daily' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'tick' ) );

		self::schedule();
	}

	/**
	 * Ensure the daily event exists.
	 *
	 * Placed `SCHEDULE_OFFSET_HOURS` after the students sync's next run when one is
	 * scheduled, and that far from now when none is. It only holds for the first run; after that the
	 * two cadences drift apart on their own.
	 */
	public static function schedule() {
		if ( wp_next_scheduled( self::CRON_DAILY ) ) {
			return;
		}

		$anchor = (int) wp_next_scheduled( WPCPM_Students_Sync::CRON_AUTO );

		if ( $anchor <= 0 ) {
			$anchor = time();
		}

		wp_schedule_event( $anchor + ( self::SCHEDULE_OFFSET_HOURS * HOUR_IN_SECONDS ), 'daily', self::CRON_DAILY );
	}

	/**
	 * Drop all scheduled work.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * On plugin activation: the schedule, and a first read of the Countries table so the
	 * application form and the queue can route before the first sync has run.
	 *
	 * A failed read is not an activation error. The map stays empty, the manager screen
	 * says so, and the first sync fills it.
	 */
	public static function activate() {
		self::schedule();

		if ( WPCPM_Settings::is_connected() ) {
			WPCPM_Countries::refresh();
		}
	}

	/**
	 * On plugin deactivation: stop the scheduled work. Options and accounts stay.
	 */
	public static function deactivate() {
		self::unschedule();
	}

	/**
	 * Unix time of the last completed run, 0 when there has not been one.
	 *
	 * @return int
	 */
	public static function last_read() {
		return (int) get_option( self::OPT_LAST, 0 );
	}

	/**
	 * Recurring entry point.
	 */
	public static function cron_daily() {
		if ( ! WPCPM_Settings::get_value( 'auto_sync' ) ) {
			return;
		}

		// A run in progress is left alone, unless it has stalled: the same rule as the
		// students sync, for the same reason. `start()` wipes the state, and a slow run
		// restarted from the top by its own schedule would never finish.
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
				'phase'     => 'countries',
				'offset'    => null,
				'started'   => time(),
				'touched'   => time(),
				'steps'     => array(),
				// The index rows as they accumulate over the pages, keyed by record ID.
				'rows'      => array(),
				// Drive links, keyed by record ID, kept out of the index on purpose: they
				// belong on the per-institution agreement option and go there through
				// `rebuild()`. Held here only between the page that read them and the
				// slice that rebuilds them.
				'documents' => array(),
				// Set once the index is written; the records phase then rebuilds.
				'indexed'   => false,
				'rebuild'   => array(),
				'stats'     => self::empty_stats(),
				'notices'   => array(),
			),
			false
		);

		delete_option( self::OPT_ERROR );

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 10, self::CRON_TICK );
		}

		if ( $run_first_tick ) {
			self::tick();
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
	 * The keys are what assets/js/admin.js reads: `running`, `label`, `step_label`,
	 * `percent`, `detail`, `elapsed` and `stalled`; the rest are for the server-rendered
	 * panel and the same for both syncs.
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
			case 'countries':
				return __( 'Reading the country routing map…', 'wpcredits-program-manager' );

			case 'records':
				return sprintf(
					/* translators: 1: institution records read, 2: agreement records rebuilt. */
					__( '%1$s institutions read · %2$s agreement records rebuilt', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'records_seen' ) ),
					number_format_i18n( $get( 'rebuilt' ) )
				);

			case 'provision':
				return sprintf(
					/* translators: 1: accounts created, 2: addresses that already had an account. */
					__( '%1$s accounts created · %2$s addresses already taken', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'provisioned' ) ),
					number_format_i18n( $get( 'conflicts' ) )
				);

			case 'revoke':
				return sprintf(
					/* translators: 1: agreements locked, 2: memberships revoked. */
					__( '%1$s agreements locked · %2$s memberships revoked', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'locked' ) ),
					number_format_i18n( $get( 'revoked' ) )
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
	public static function tick( $budget = null ) {
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
				case 'countries':
					$result = self::phase_countries( $state, $airtable );
					break;
				case 'records':
					$result = self::phase_records( $state, $airtable, $settings );
					break;
				case 'provision':
					$result = self::phase_provision( $state, $settings );
					break;
				case 'revoke':
					$result = self::phase_revoke( $state, $settings );
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
	 * Phase 1 - refresh the country routing map.
	 *
	 * First, because the records phase resolves each institution's country name through
	 * it. A failed read stops the run: the same request that could not read Countries is
	 * not going to read Institutions either, and the error is better reported once.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @return true|WP_Error
	 */
	private static function phase_countries( array &$state, WPCPM_Airtable $airtable ) {
		$result = WPCPM_Countries::refresh( $airtable );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$state['stats']['countries'] = count( WPCPM_Countries::all() );
		$state['phase']              = 'records';
		$state['offset']             = null;

		return true;
	}

	/**
	 * Phase 2 - page through the Institutions table, write the index, rebuild the gate.
	 *
	 * Two halves under one phase key. While `indexed` is false, each slice reads one page
	 * and accumulates rows; the last page writes the index in a single `write()`, so an
	 * error on any page leaves the previous index untouched. Once written, each slice
	 * rebuilds `REBUILD_BATCH` agreement options from the columns just read, the Drive link
	 * included, which is how a manager typing `On file` and a link into the grid settles an
	 * institution on the next run (T12).
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_records( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		if ( ! empty( $state['indexed'] ) ) {
			return self::rebuild_slice( $state );
		}

		$fields = self::fields();

		if ( ! empty( $settings['institutions_name_field'] ) ) {
			$fields['name'] = (string) $settings['institutions_name_field'];
		}

		$page = $airtable->fetch_page(
			$settings['institutions_table'],
			array(
				'fields' => array_values( $fields ),
				'offset' => $state['offset'],
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		foreach ( $page['records'] as $record ) {
			$record_id = isset( $record['id'] ) ? trim( (string) $record['id'] ) : '';

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				++$state['stats']['skipped'];
				continue;
			}

			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

			$read = static function ( $key ) use ( $cells, $fields ) {
				return WPCPM_Airtable::flatten( isset( $cells[ $fields[ $key ] ] ) ? $cells[ $fields[ $key ] ] : '' );
			};

			$country_ids = WPCPM_Airtable::link_ids( isset( $cells[ $fields['country'] ] ) ? $cells[ $fields['country'] ] : array() );
			$country     = ( ! empty( $country_ids ) && WPCPM_Mentors_Sync::is_record_id( $country_ids[0] ) ) ? $country_ids[0] : '';
			$document    = trim( $read( 'agr_document' ) );
			$name        = $read( 'name' );

			if ( '' === trim( $name ) ) {
				++$state['stats']['nameless'];
				$state['notices'][] = sprintf(
					/* translators: %s: Airtable record ID. */
					__( 'Record %s has no Name in Airtable. It is in the pipeline under its record ID.', 'wpcredits-program-manager' ),
					$record_id
				);
			}

			$state['rows'][ $record_id ] = array(
				'record_id'      => $record_id,
				// As stored, trailing space and all: the index is the one place that must
				// agree with the base byte for byte. Renderers trim.
				'name'           => $name,
				'stage'          => trim( $read( 'stage' ) ),
				'country'        => $country,
				'country_name'   => '' !== $country ? WPCPM_Countries::name_of( $country ) : '',
				'city'           => trim( $read( 'city' ) ),
				'website'        => trim( $read( 'website' ) ),
				'contact_person' => trim( $read( 'contact_person' ) ),
				'contact_email'  => strtolower( trim( $read( 'contact_email' ) ) ),
				'created'        => self::date_part( isset( $record['createdTime'] ) ? $record['createdTime'] : '' ),
				'consent'        => ! empty( $cells[ $fields['consent'] ] ),
				'confirmed_on'   => self::date_part( $read( 'confirmed_on' ) ),
				'agreement'      => array(
					'status'           => trim( $read( 'agr_status' ) ),
					'kind'             => trim( $read( 'agr_kind' ) ),
					'accepted_on'      => self::date_part( $read( 'agr_accepted_on' ) ),
					'signed_on'        => self::date_part( $read( 'agr_signed_on' ) ),
					'accepted_by'      => trim( $read( 'agr_accepted_by' ) ),
					'submitted_on'     => self::date_part( $read( 'agr_submitted_on' ) ),
					'template_version' => trim( $read( 'agr_template' ) ),
					'has_document'     => '' !== $document,
				),
			);

			if ( '' !== $document ) {
				$state['documents'][ $record_id ] = $document;
			}

			++$state['stats']['records_seen'];
		}

		$state['offset'] = $page['offset'];

		if ( ! empty( $page['offset'] ) ) {
			return true;
		}

		// Airtable answers a read for an unknown field name with records carrying no
		// fields rather than an error, and a filtered or renamed table answers 200 with no
		// records at all. Neither is an empty table, and neither may replace a good index:
		// the revoke phase reads the index back and would detach every member on the site.
		$held = WPCPM_Institutions_Index::rows();

		if ( 0 === self::named_rows( $state['rows'] ) && ! empty( $held ) ) {
			return new WP_Error(
				'wpcpm_institutions_no_fields',
				sprintf(
					/* translators: %s: Airtable table ID. */
					__( 'Read %s but no record came back with a name or a stage. Check the institutions table and name field settings; the stored index was kept.', 'wpcredits-program-manager' ),
					$settings['institutions_table']
				)
			);
		}

		WPCPM_Institutions_Index::write( $state['rows'], $state['started'] );

		$state['indexed'] = true;
		$state['rebuild'] = array_keys( $state['rows'] );
		$state['offset']  = null;

		return true;
	}

	/**
	 * Rebuild one slice of agreement options from the columns the records phase read.
	 *
	 * @param array $state Sync state, by reference.
	 * @return true
	 */
	private static function rebuild_slice( array &$state ) {
		$pending = isset( $state['rebuild'] ) ? array_values( (array) $state['rebuild'] ) : array();
		$batch   = array_splice( $pending, 0, self::REBUILD_BATCH );

		foreach ( $batch as $record_id ) {
			if ( ! isset( $state['rows'][ $record_id ] ) ) {
				continue;
			}

			$block = $state['rows'][ $record_id ]['agreement'];

			$option = WPCPM_Institution_Agreement::rebuild(
				$record_id,
				array(
					'status'           => $block['status'],
					'kind'             => $block['kind'],
					'accepted_on'      => $block['accepted_on'],
					'signed_on'        => $block['signed_on'],
					'accepted_by'      => $block['accepted_by'],
					'document'         => isset( $state['documents'][ $record_id ] ) ? (string) $state['documents'][ $record_id ] : '',
					'submitted_on'     => $block['submitted_on'],
					'template_version' => $block['template_version'],
				)
			);

			++$state['stats']['rebuilt'];

			if ( is_array( $option ) && ! empty( $option['settled'] ) ) {
				++$state['stats']['settled'];
			}
		}

		$state['rebuild'] = $pending;

		if ( empty( $pending ) ) {
			$state['phase']     = 'provision';
			$state['documents'] = array();
		}

		return true;
	}

	/**
	 * Phase 3 - create the one account each Confirmed institution starts with.
	 *
	 * Behind `institution_provision`, which is off by default for the reason the welcome
	 * email is: the first run of a sync that mails people is a decision for a human. With it
	 * off the phase does nothing at all, and the manager screen's provisioning card is the
	 * way in instead.
	 *
	 * The candidate list is every Confirmed row of the index this run has just written, built
	 * once and then worked through `PROVISION_BATCH` at a time, with the remainder in the run
	 * state so a tick that ends mid-phase is resumed rather than restarted. Whether each
	 * candidate is actually provisioned is `provision_block()`'s decision and nowhere else's,
	 * so the nightly run and the two buttons on the manager screen cannot come to different
	 * answers about the same institution.
	 *
	 * @param array $state    Sync state, by reference.
	 * @param array $settings Plugin settings.
	 * @return true
	 */
	private static function phase_provision( array &$state, array $settings ) {
		if ( empty( $settings['institution_provision'] ) ) {
			$state['phase'] = 'revoke';

			return true;
		}

		// A run that started before this release carries a stats array with none of the four
		// keys this phase counts into. Filled in rather than incremented from nothing, because
		// the upgrade lands between two ticks of a run that is already going.
		$state['stats'] = array_merge( self::empty_stats(), (array) $state['stats'] );

		if ( ! isset( $state['provision'] ) ) {
			$candidates = array();

			// Only the Confirmed rows are candidates, and that is read from the index rather
			// than asked of `provision_block()` here: the other hundred rows would each cost
			// an option read to be told what the stage already says.
			foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
				if ( 'Confirmed' === trim( (string) $row['stage'] ) ) {
					$candidates[] = $record_id;
				}
			}

			$state['provision'] = $candidates;
		}

		$pending = array_values( (array) $state['provision'] );
		$batch   = array_splice( $pending, 0, self::PROVISION_BATCH );

		foreach ( $batch as $record_id ) {
			self::provision_candidate( $state, (string) $record_id );
		}

		$state['provision'] = $pending;

		if ( empty( $pending ) ) {
			$state['phase'] = 'revoke';
		}

		return true;
	}

	/**
	 * One candidate: provision it, or count and name why not.
	 *
	 * The block is asked for before `provision()` is called and again inside it. That is
	 * deliberate: `provision()` is public and is also what the manager screen's buttons
	 * press, so it cannot trust a caller to have checked, and the run needs the reason in
	 * order to count and report it. Both passes are a handful of reads about one institution.
	 *
	 * A conflict is named in the notices because it is the one outcome a person has to
	 * resolve: the address Airtable holds already belongs to an account, which is a conflict
	 * and not a match, and adopting it would hand an institution's roster to whoever that is.
	 *
	 * @param array  $state     Sync state, by reference.
	 * @param string $record_id Institutions record ID.
	 */
	private static function provision_candidate( array &$state, $record_id ) {
		$reason = self::provision_block( $record_id );

		if ( '' !== $reason && self::BLOCK_CONFLICT !== $reason ) {
			++$state['stats']['provision_skipped'];

			return;
		}

		$row  = WPCPM_Institutions_Index::row( $record_id );
		$name = is_array( $row ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record_id;

		if ( self::BLOCK_CONFLICT === $reason ) {
			++$state['stats']['conflicts'];
			$state['notices'][] = sprintf(
				/* translators: 1: institution name, 2: why it was skipped. */
				__( 'No account was created for %1$s: %2$s', 'wpcredits-program-manager' ),
				$name,
				self::provision_message( $reason )
			);

			return;
		}

		$result = self::provision( $record_id, 0 );

		if ( is_wp_error( $result ) ) {
			++$state['stats']['provision_failed'];
			$state['notices'][] = sprintf(
				/* translators: 1: institution name, 2: error message. */
				__( 'Could not create the account for %1$s: %2$s', 'wpcredits-program-manager' ),
				$name,
				$result->get_error_message()
			);

			return;
		}

		++$state['stats']['provisioned'];
	}

	/**
	 * Why this institution may not be provisioned from its `Contact Email` right now, or ''.
	 *
	 * The one copy of the rule, read by the nightly run, by the manager screen's card and by
	 * both of its buttons. In order, and the order is the point:
	 *
	 * - a row the index holds, at `Confirmed`: provisioning is what the program does once it
	 *   has said yes, and the index is the site's own copy of that answer;
	 * - a `Contact Email` WordPress can make an account from;
	 * - no live member and no former member. **This is the rule that matters most**: without
	 *   it the sync re-creates a removed contact's account every night, for ever. After the
	 *   first account, membership is managed on the site. It is asked before the agreement,
	 *   ahead of its own cost, because a revocation deletes the agreement option and leaves
	 *   the account and the stage alone (design spec 7.4, T8). Asked the other way round, an
	 *   institution that already has an account is reported as one waiting for its first
	 *   agreement: the worklist tells a manager to record one for a partner that was
	 *   provisioned months ago, the row is missing from the count of the ones that already
	 *   have an account, and the bulk button stays shut for every other institution while it
	 *   is there;
	 * - a settled agreement, because an account opened before the agreement is recorded is a
	 *   partner that signed years ago being emailed that its first step is to sign;
	 * - and last, an address that already belongs to an account. That is a conflict and not a
	 *   match: found-by-email with no membership could be a student, a mentor or a person who
	 *   registered here for something else, and adopting them would hand a school's roster to
	 *   somebody who never asked for it.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string One of the `BLOCK_` constants, or '' when an account may be created.
	 */
	public static function provision_block( $record_id ) {
		$record_id = trim( (string) $record_id );
		$row       = WPCPM_Mentors_Sync::is_record_id( $record_id ) ? WPCPM_Institutions_Index::row( $record_id ) : null;

		if ( ! is_array( $row ) ) {
			return self::BLOCK_NOT_INDEXED;
		}

		if ( 'Confirmed' !== trim( (string) $row['stage'] ) ) {
			return self::BLOCK_NOT_CONFIRMED;
		}

		$email = trim( (string) $row['contact_email'] );

		if ( ! is_email( $email ) ) {
			return self::BLOCK_NO_EMAIL;
		}

		if ( ! empty( WPCPM_Institution_Members::members_of( $record_id ) ) ) {
			return self::BLOCK_HAS_MEMBER;
		}

		// The agreement is asked before the former-member test, and after the live-member one,
		// on purpose. A revoked institution that still has its account is "already has an
		// account", whatever the agreement says. But an institution with no recorded agreement
		// and only a former member is not: it must go on holding the bulk button shut and be
		// listed with "record the agreement", or a school whose contact once left would slip
		// out of the one list that says what a manager still has to do for it.
		if ( ! WPCPM_Institution_Agreement::is_settled( $record_id ) ) {
			return self::BLOCK_NO_AGREEMENT;
		}

		if ( ! empty( WPCPM_Institution_Members::former_members_of( $record_id ) ) ) {
			return self::BLOCK_FORMER_MEMBER;
		}

		if ( get_user_by( 'email', $email ) instanceof WP_User ) {
			return self::BLOCK_CONFLICT;
		}

		return '';
	}

	/**
	 * What a block means, in the words the run report and the manager screen both use.
	 *
	 * One wording per reason, in one place, so the sentence a manager reads beside the button
	 * and the sentence in the nightly run's notices cannot drift apart.
	 *
	 * @param string $reason One of the `BLOCK_` constants.
	 * @return string
	 */
	public static function provision_message( $reason ) {
		switch ( (string) $reason ) {
			case self::BLOCK_NOT_INDEXED:
				return __( 'The pipeline index does not hold that institution. Run the institutions sync, then try again.', 'wpcredits-program-manager' );

			case self::BLOCK_NOT_CONFIRMED:
				return __( 'Only a Confirmed institution is given an account. Move it to Confirmed in Airtable first.', 'wpcredits-program-manager' );

			case self::BLOCK_NO_EMAIL:
				return __( 'Airtable holds no Contact Email for it, and WordPress cannot create an account without an address.', 'wpcredits-program-manager' );

			case self::BLOCK_NO_AGREEMENT:
				return __( 'No agreement is recorded for it. Record the one on file with its Drive link, or accept a signed one, before the account is created.', 'wpcredits-program-manager' );

			case self::BLOCK_HAS_MEMBER:
				return __( 'It already has a member. After the first account, membership is managed on this site.', 'wpcredits-program-manager' );

			case self::BLOCK_FORMER_MEMBER:
				return __( 'It has had a member before. Add the account by hand rather than provisioning it again.', 'wpcredits-program-manager' );

			case self::BLOCK_CONFLICT:
				return __( 'The Contact Email already belongs to an account on this site. That is a conflict, not a match: add that account as a member by hand if it is the right person.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * Create the one account an institution starts with.
	 *
	 * Refuses first, through `provision_block()`, whoever the caller is: a stale page, a
	 * second press of the button and the nightly run all arrive here, and the checks are
	 * cheap next to the account they prevent.
	 *
	 * The account, then the stamp, then the invitation, in that order and each only if the
	 * one before it worked. An account that could not be stamped is left standing rather than
	 * deleted, because deleting a person's account to tidy up a failed write is worse than the
	 * mess it tidies: the next run finds an address that already has an account, names it as
	 * a conflict, and stops, which is a person's problem to look at rather than a loop. What
	 * it does not do is send that account an invitation, because an invitation to an account
	 * that cannot act for anybody is worse than none.
	 *
	 * The invitation is not behind `send_welcome_email`. Provisioning is itself the opt-in,
	 * and an institution account nobody is told about is an account nobody uses: the password
	 * is random, so the mail is the only way in.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param int    $actor_id  Who is doing it; 0 for the sync.
	 * @return int|WP_Error The new account's ID.
	 */
	public static function provision( $record_id, $actor_id = 0 ) {
		$record_id = trim( (string) $record_id );
		$reason    = self::provision_block( $record_id );

		if ( '' !== $reason ) {
			return new WP_Error( self::PROVISION_ERROR, self::provision_message( $reason ), array( 'reason' => $reason ) );
		}

		$row   = WPCPM_Institutions_Index::row( $record_id );
		$email = trim( (string) $row['contact_email'] );
		$name  = trim( (string) $row['contact_person'] );

		if ( '' === $name ) {
			$name = trim( (string) $row['name'] );
		}

		// A record with neither a contact person nor a name is one of the blank rows the base
		// collects; its ID is at least something a manager can search the grid for.
		if ( '' === $name ) {
			$name = $record_id;
		}

		$login   = self::unique_login( $email );
		$user_id = WPCPM_Roles::insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $name,
				'nickname'     => $name,
				'role'         => WPCPM_Roles::ROLE_INSTITUTION,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$attached = WPCPM_Institution_Members::attach( (int) $user_id, $record_id, WPCPM_Institution_Members::HOW_PROVISIONED, absint( $actor_id ) );

		if ( is_wp_error( $attached ) ) {
			return $attached;
		}

		WPCPM_Mail::queue_invites( array( (int) $user_id ) );

		return (int) $user_id;
	}

	/**
	 * A free username, from the local part of the contact address.
	 *
	 * The same shape the students sync uses for a student with no WordPress.org handle, for
	 * the same reason: the address is the only identifier Airtable gives us, and a login is
	 * not an identity here. Plenty of institutions will send `info@`, so the numeric suffix
	 * is the common case rather than an edge one; what matters is that it is free.
	 *
	 * @param string $email Contact address.
	 * @return string
	 */
	private static function unique_login( $email ) {
		$base = strtolower( (string) strstr( (string) $email, '@', true ) );
		$base = preg_replace( '/[^a-z0-9._\-]/', '', $base );
		$base = sanitize_user( $base, true );

		if ( '' === $base ) {
			$base = 'institution';
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
	 * Phase 4 - close the gate on every institution that has left the pipeline.
	 *
	 * The live members are the accounts holding the Institution role: `attach()` grants it
	 * and `detach()` removes it, and an administrator is never a member. Each one's live
	 * institution comes from `institution_of()`, so a stamp that is malformed or inactive is
	 * skipped here the way the policy skips it. An institution has left when the index no
	 * longer holds it (deleted from the base, or never read) or its stage is outside
	 * `institution_active_stages`.
	 *
	 * The agreement option is deleted first and always, because a lost write must leave the
	 * institution locked rather than open; `is_settled()` reads that option and nothing else.
	 * The membership goes only under `institution_on_inactive` = `revoke`, through
	 * `detach()` rather than a second copy of its steps, with actor 0 so the log says the
	 * sync did it.
	 *
	 * @param array $state    Sync state, by reference.
	 * @param array $settings Plugin settings.
	 * @return true
	 */
	private static function phase_revoke( array &$state, array $settings ) {
		$active = isset( $settings['institution_active_stages'] ) ? (array) $settings['institution_active_stages'] : array();
		$rows   = WPCPM_Institutions_Index::rows();

		// The gate closes for every institution that has left the active stages, whether or
		// not anybody holds an account for it: the option is what `is_settled()` reads, and an
		// institution the program has stopped working with must not keep an open one waiting
		// for the day somebody is attached to it. Deleting rather than rewriting leaves it
		// locked, which is the direction a lost write should fail in.
		foreach ( WPCPM_Institution_Agreement::stored_records() as $record_id ) {
			$in_index = isset( $rows[ $record_id ] );

			if ( $in_index && in_array( (string) $rows[ $record_id ]['stage'], $active, true ) ) {
				continue;
			}

			delete_option( WPCPM_Institution_Agreement::option_name( $record_id ) );
			++$state['stats']['locked'];
		}

		$members = get_users(
			array(
				'number' => -1,
				'fields' => 'ID',
				'role'   => WPCPM_Roles::ROLE_INSTITUTION,
			)
		);

		foreach ( $members as $user_id ) {
			$user      = new WP_User( (int) $user_id );
			$record_id = (string) WPCPM_Institution_Members::institution_of( $user );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$in_index = isset( $rows[ $record_id ] );
			$stage    = $in_index ? (string) $rows[ $record_id ]['stage'] : '';

			if ( $in_index && in_array( $stage, $active, true ) ) {
				continue;
			}

			if ( 'revoke' !== $settings['institution_on_inactive'] ) {
				continue;
			}

			$result = WPCPM_Institution_Members::detach( $user->ID, 'revoked', 0 );

			if ( is_wp_error( $result ) ) {
				$state['notices'][] = sprintf(
					/* translators: 1: user ID, 2: error message. */
					__( 'Could not revoke the membership of account %1$d: %2$s', 'wpcredits-program-manager' ),
					$user->ID,
					$result->get_error_message()
				);
				continue;
			}

			++$state['stats']['revoked'];
		}

		$state['phase'] = 'done';

		return true;
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
			'countries'         => 0,
			'records_seen'      => 0,
			'nameless'          => 0,
			'skipped'           => 0,
			'rebuilt'           => 0,
			'settled'           => 0,
			// The provision phase's four numbers: accounts made, Confirmed institutions that
			// were not ready for one, addresses that already belong to an account, and
			// attempts that failed outright. Kept apart from `skipped`, which is the records
			// phase's count of rows it could not read.
			'provisioned'       => 0,
			'provision_skipped' => 0,
			'conflicts'         => 0,
			'provision_failed'  => 0,
			'locked'            => 0,
			'revoked'           => 0,
		);
	}

	/**
	 * The `Y-m-d` part of an Airtable date or timestamp, or '' when there is none.
	 *
	 * Date columns arrive as `2025-06-26`; `createdTime` as `2025-07-17T13:13:11.000Z`. Only
	 * the day matters to anything that reads the index, and a value in any other shape is
	 * not a date the screens can sort.
	 *
	 * @param mixed $value Raw cell value.
	 * @return string
	 */
	private static function date_part( $value ) {
		$value = trim( WPCPM_Airtable::flatten( $value ) );

		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', $value, $m ) ? $m[1] : '';
	}

	/**
	 * How many rows carry a name or a stage.
	 *
	 * @param array $rows Index rows.
	 * @return int
	 */
	private static function named_rows( array $rows ) {
		$named = 0;

		foreach ( $rows as $row ) {
			if ( '' !== trim( (string) $row['name'] ) || '' !== (string) $row['stage'] ) {
				++$named;
			}
		}

		return $named;
	}
}
