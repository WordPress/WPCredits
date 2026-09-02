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
 * is a count in this phase: account creation ships in Phase 2, and until then the sync says
 * how many accounts it would make rather than making any. `revoke` closes the gate and
 * detaches the members of every institution that has left the pipeline.
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
				'label'  => __( 'Counting institution accounts to create', 'wpcredits-program-manager' ),
				'weight' => 25,
				'steps'  => 1,
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
					/* translators: %s: number of institutions that would get an account. */
					__( '%s institutions would be provisioned', 'wpcredits-program-manager' ),
					number_format_i18n( $get( 'would_provision' ) )
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
	 * Phase 3 - count the institutions that would get an account.
	 *
	 * A count and nothing more in this phase: account creation is Phase 2 of the module,
	 * and until it ships the sync reports what it would do rather than doing it. Even the
	 * count is behind `institution_provision`, which is off by default for the reason the
	 * welcome email is. The rule counted is the one Phase 2 will apply: Confirmed, with a
	 * contact address an account can be made from, a settled agreement (the gate is what
	 * stops a partner that signed years ago being told its first step is to sign), and no
	 * membership history, live or former, because after the first account membership is
	 * managed on the site.
	 *
	 * @param array $state    Sync state, by reference.
	 * @param array $settings Plugin settings.
	 * @return true
	 */
	private static function phase_provision( array &$state, array $settings ) {
		if ( ! empty( $settings['institution_provision'] ) ) {
			$count = 0;

			foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
				if ( 'Confirmed' !== $row['stage'] || ! is_email( $row['contact_email'] ) ) {
					continue;
				}

				if ( ! WPCPM_Institution_Agreement::is_settled( $record_id ) ) {
					continue;
				}

				if ( ! empty( WPCPM_Institution_Members::members_of( $record_id ) ) || ! empty( WPCPM_Institution_Members::former_members_of( $record_id ) ) ) {
					continue;
				}

				++$count;
			}

			$state['stats']['would_provision'] = $count;

			if ( $count > 0 ) {
				$state['notices'][] = sprintf(
					/* translators: %s: number of institutions. */
					_n(
						'%s Confirmed institution with a settled agreement has no account yet. Account creation ships in the next phase of the Institutions module; nothing was created.',
						'%s Confirmed institutions with a settled agreement have no account yet. Account creation ships in the next phase of the Institutions module; nothing was created.',
						$count,
						'wpcredits-program-manager'
					),
					number_format_i18n( $count )
				);
			}
		}

		$state['phase'] = 'revoke';

		return true;
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
			'countries'       => 0,
			'records_seen'    => 0,
			'nameless'        => 0,
			'skipped'         => 0,
			'rebuilt'         => 0,
			'settled'         => 0,
			'would_provision' => 0,
			'locked'          => 0,
			'revoked'         => 0,
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
