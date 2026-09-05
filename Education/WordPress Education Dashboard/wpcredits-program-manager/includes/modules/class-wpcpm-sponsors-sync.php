<?php
/**
 * The sponsors sync: Team Members and Sponsors, read nightly into the index.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Four phases, budgeted and resumable, the institutions sync's machine with a smaller job.
 *
 * 1. `team`: the Team Members table read whole into the team option; it is what turns a
 *    `Person of contact` link into a name.
 * 2. `records`: the Sponsors table read whole (thirty rows, one page) into the index.
 * 3. `logos`: for every Approved sponsor whose Airtable logo differs from the one the site
 *    holds, the file is copied into the Media Library through `WPCPM_Image_Upload`, one
 *    sponsor per step, because an Airtable attachment URL expires within hours. A logo the
 *    sponsor uploaded on the site is never overwritten.
 * 4. `revoke`: accounts of sponsors no longer Approved are handled per `sponsor_on_inactive`.
 *
 * Nothing is ever provisioned by the sync (design spec of 4 September 2026, decision 9).
 * The state option holds the cursor and is written after every step, so a budget stop
 * mid-phase resumes where it stopped; the lock is `add_option()`'s test-and-set with a
 * stale takeover past `LOCK_TIMEOUT`.
 */
final class WPCPM_Sponsors_Sync {

	const CRON_DAILY = 'wpcpm_sponsors_daily';
	const CRON_TICK  = 'wpcpm_sponsors_sync_tick';

	const OPT_STATE  = 'wpcpm_sponsors_state';
	const OPT_REPORT = 'wpcpm_sponsors_report';
	const OPT_LAST   = 'wpcpm_sponsors_last_sync';
	const OPT_ERROR  = 'wpcpm_sponsors_last_error';
	const OPT_LOCK   = 'wpcpm_sponsors_lock';

	const BUDGET       = 18;
	const BUDGET_AJAX  = 8;
	const LOCK_TIMEOUT = 120;

	/** Hours after the institutions run: the way that one sits after the students run. */
	const SCHEDULE_OFFSET_HOURS = 4;

	/**
	 * The Sponsors table's columns, by the keys the code uses. A plain array, like the
	 * institutions sync's: every name is pinned by bin/fixtures/sponsors-table-fields.json and
	 * asserted by bin/test-sponsors-sync.php. The name column can be overridden by the
	 * `sponsors_name_field` setting.
	 *
	 * @return array<string, string>
	 */
	public static function fields() {
		return array(
			'name'              => 'Company Name',
			'website'           => 'Website',
			'contact_person'    => 'Contact Person Full Name',
			'contact_email'     => 'Contact Email',
			'status'            => 'Status',
			'option'            => 'Sponsorship options',
			'support'           => 'How would you like to support WP Credits?',
			'product_type'      => 'Type of product',
			'offer'             => 'Offer',
			'instructions'      => 'Brief instructions',
			'more_info'         => 'More info link',
			'coupon_link'       => 'Coupon code/discount link',
			'anything'          => "Anything else you'd like to share.",
			'manager'           => 'Person of contact',
			'mentors'           => 'Mentors',
			'logo'              => 'Logo',
			'consent'           => 'Privacy Policy Compliance',
			'agr_status'        => 'Agreement Status',
			'agr_accepted_on'   => 'Agreement Accepted On',
			'agr_document'      => 'Agreement Document',
			'interests'         => 'Sponsorship interests',
			'dashboard_account' => 'Dashboard account',
		);
	}

	/**
	 * The Team Members table's columns.
	 *
	 * @return array<string, string>
	 */
	public static function team_fields() {
		return array(
			'name'     => 'Name',
			'email'    => 'Email',
			'calendly' => 'Calendly link',
		);
	}

	/**
	 * The phases, with the weight each carries in the progress bar and its expected steps.
	 *
	 * @return array<string, array{label: string, weight: int, steps: int}>
	 */
	public static function phases() {
		return array(
			'team'    => array(
				'label'  => __( 'Reading the program team', 'wpcredits-program-manager' ),
				'weight' => 10,
				'steps'  => 1,
			),
			'records' => array(
				'label'  => __( 'Reading sponsors', 'wpcredits-program-manager' ),
				'weight' => 30,
				'steps'  => 1,
			),
			'logos'   => array(
				'label'  => __( 'Copying logos into the Media Library', 'wpcredits-program-manager' ),
				'weight' => 45,
				'steps'  => 14,
			),
			'revoke'  => array(
				'label'  => __( 'Reviewing sponsors that are no longer Approved', 'wpcredits-program-manager' ),
				'weight' => 15,
				'steps'  => 1,
			),
		);
	}

	/**
	 * Hook the two cron events and make sure the daily one is on the clock.
	 */
	public static function register_cron() {
		add_action( self::CRON_DAILY, array( __CLASS__, 'cron_daily' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'run_tick' ) );

		self::schedule();
	}

	/**
	 * Ensure the daily event exists, `SCHEDULE_OFFSET_HOURS` after the institutions sync's next
	 * run when one is scheduled and that far from now when none is. It only holds for the first
	 * run; after that the cadences drift apart on their own, as the other syncs' do.
	 */
	public static function schedule() {
		if ( wp_next_scheduled( self::CRON_DAILY ) ) {
			return;
		}

		$anchor = class_exists( 'WPCPM_Institutions_Sync' ) ? (int) wp_next_scheduled( WPCPM_Institutions_Sync::CRON_DAILY ) : 0;

		if ( $anchor <= 0 ) {
			$anchor = time();
		}

		wp_schedule_event( $anchor + ( self::SCHEDULE_OFFSET_HOURS * HOUR_IN_SECONDS ), 'daily', self::CRON_DAILY );
	}

	/**
	 * Activation: the schedule.
	 */
	public static function activate() {
		self::schedule();
	}

	/**
	 * Deactivation: both events off the clock, the working state gone.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
		wp_clear_scheduled_hook( self::CRON_TICK );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
	}

	/**
	 * The nightly run.
	 */
	public static function cron_daily() {
		if ( self::is_running() ) {
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
				'phase'   => 'team',
				'offset'  => null,
				'started' => time(),
				'touched' => time(),
				'steps'   => array(),
				// The index rows as they accumulate over the pages, keyed by record ID.
				'rows'    => array(),
				// The Approved sponsors whose logos the logos phase still has to look at.
				'logos'   => array(),
				'stats'   => self::empty_stats(),
				'notices' => array(),
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
	 * Stop a run.
	 */
	public static function cancel() {
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
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
	 * When the last run finished, unix time, or 0.
	 *
	 * @return int
	 */
	public static function last_read() {
		return (int) get_option( self::OPT_LAST, 0 );
	}

	/**
	 * Process one slice of work. Named `run_tick()` so `WPCPM_Sync_Module` needs no override.
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
				case 'team':
					$result = self::phase_team( $state, $airtable, $settings );
					break;
				case 'records':
					$result = self::phase_records( $state, $airtable, $settings );
					break;
				case 'logos':
					$result = self::phase_logos( $state );
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
	 * Progress for the admin screen and the AJAX poll: the keys assets/js/admin.js reads.
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
	 * A zeroed statistics array.
	 *
	 * @return array<string, int>
	 */
	public static function empty_stats() {
		return array(
			'team_seen'     => 0,
			'records_seen'  => 0,
			'approved'      => 0,
			'nameless'      => 0,
			'skipped'       => 0,
			'logos_copied'  => 0,
			'logos_kept'    => 0,
			'logos_refused' => 0,
			'revoked'       => 0,
			'inactive_kept' => 0,
		);
	}

	/**
	 * Phase 1: the program team, read whole.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable The client.
	 * @param array          $settings Settings.
	 * @return true|WP_Error
	 */
	private static function phase_team( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$fields  = self::team_fields();
		$records = $airtable->fetch_all(
			$settings['team_members_table'],
			array( 'fields' => array_values( $fields ) )
		);

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		// A filtered view or a renamed table answers with zero records rather than an error,
		// the same shape phase_records() below guards against for a genuinely empty sponsors
		// read against a held index. The team option is what turns a `Person of contact` link
		// into a name and an address for every sponsor at once, so blanking it silently would
		// strip every program contact from the site overnight.
		if ( empty( $records ) && ! empty( WPCPM_Sponsors_Index::team() ) ) {
			return new WP_Error(
				'wpcpm_team_empty',
				__( 'Read no rows from the Team Members table, but the site already holds program contacts. A filtered view or a renamed table would blank every one of them overnight, so nothing was changed.', 'wpcredits-program-manager' )
			);
		}

		$rows = array();

		foreach ( (array) $records as $record ) {
			$record_id = isset( $record['id'] ) ? trim( (string) $record['id'] ) : '';

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

			$rows[ $record_id ] = array(
				'name'     => WPCPM_Airtable::flatten( isset( $cells[ $fields['name'] ] ) ? $cells[ $fields['name'] ] : '' ),
				'email'    => WPCPM_Airtable::flatten( isset( $cells[ $fields['email'] ] ) ? $cells[ $fields['email'] ] : '' ),
				'calendly' => WPCPM_Airtable::flatten( isset( $cells[ $fields['calendly'] ] ) ? $cells[ $fields['calendly'] ] : '' ),
			);
			++$state['stats']['team_seen'];
		}

		WPCPM_Sponsors_Index::write_team( $rows, $state['started'] );

		$state['phase']  = 'records';
		$state['offset'] = null;

		return true;
	}

	/**
	 * Phase 2: every sponsor's public columns, into the index in one write.
	 *
	 * @param array          $state    Sync state, by reference.
	 * @param WPCPM_Airtable $airtable The client.
	 * @param array          $settings Settings.
	 * @return true|WP_Error
	 */
	private static function phase_records( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$fields = self::fields();

		if ( ! empty( $settings['sponsors_name_field'] ) ) {
			$fields['name'] = (string) $settings['sponsors_name_field'];
		}

		$page = $airtable->fetch_page(
			$settings['sponsors_table'],
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

			$name = $read( 'name' );

			if ( '' === trim( $name ) ) {
				++$state['stats']['nameless'];
				$state['notices'][] = sprintf(
					/* translators: %s: Airtable record ID. */
					__( 'Record %s has no Company Name in Airtable. It is listed under its record ID.', 'wpcredits-program-manager' ),
					$record_id
				);
			}

			$manager_ids = WPCPM_Airtable::link_ids( isset( $cells[ $fields['manager'] ] ) ? $cells[ $fields['manager'] ] : array() );
			$support     = isset( $cells[ $fields['support'] ] ) && is_array( $cells[ $fields['support'] ] ) ? array_values( array_map( 'strval', $cells[ $fields['support'] ] ) ) : array();
			$attachments = isset( $cells[ $fields['logo'] ] ) && is_array( $cells[ $fields['logo'] ] ) ? $cells[ $fields['logo'] ] : array();
			$status      = trim( $read( 'status' ) );

			$state['rows'][ $record_id ] = array(
				'record_id'         => $record_id,
				'name'              => $name,
				'website'           => trim( $read( 'website' ) ),
				'status'            => $status,
				'option'            => trim( $read( 'option' ) ),
				'support'           => $support,
				'product_type'      => trim( $read( 'product_type' ) ),
				'offer'             => trim( $read( 'offer' ) ),
				'instructions'      => trim( $read( 'instructions' ) ),
				'more_info'         => trim( $read( 'more_info' ) ),
				'coupon_link'       => trim( $read( 'coupon_link' ) ),
				'anything'          => trim( $read( 'anything' ) ),
				'contact_person'    => trim( $read( 'contact_person' ) ),
				'contact_email'     => strtolower( trim( $read( 'contact_email' ) ) ),
				'manager'           => ( ! empty( $manager_ids ) && WPCPM_Mentors_Sync::is_record_id( $manager_ids[0] ) ) ? $manager_ids[0] : '',
				'mentors'           => WPCPM_Airtable::link_ids( isset( $cells[ $fields['mentors'] ] ) ? $cells[ $fields['mentors'] ] : array() ),
				'logo'              => self::first_attachment( $attachments ),
				'consent'           => ! empty( $cells[ $fields['consent'] ] ),
				'created'           => self::date_part( isset( $record['createdTime'] ) ? $record['createdTime'] : '' ),
				'agreement'         => array(
					'status'       => trim( $read( 'agr_status' ) ),
					'accepted_on'  => self::date_part( $read( 'agr_accepted_on' ) ),
					'has_document' => '' !== trim( $read( 'agr_document' ) ),
				),
				'interests'         => trim( $read( 'interests' ) ),
				'dashboard_account' => ! empty( $cells[ $fields['dashboard_account'] ] ),
			);

			++$state['stats']['records_seen'];

			if ( WPCPM_Sponsors_Index::STATUS_APPROVED === $status ) {
				++$state['stats']['approved'];
			}
		}

		$state['offset'] = $page['offset'];

		if ( ! empty( $page['offset'] ) ) {
			return true;
		}

		// Records that carry no fields is the unknown-column signature: Airtable answers a read
		// naming a column that does not exist in the base with 200 and records that have none
		// of the fields asked for, rather than an error. That must refuse even on a first run,
		// before any index has ever been written - the plan's release order creates the five
		// new columns first, but the code must not depend on the order.
		if ( $state['stats']['records_seen'] > 0 && 0 === self::named_rows( $state['rows'] ) ) {
			return new WP_Error(
				'wpcpm_sponsors_no_fields',
				sprintf(
					/* translators: 1: records read, 2: Airtable table ID. */
					__( 'Read %1$d records from %2$s but none came back with a name or a status: a column the sync asks for does not exist in the base yet. Nothing was written.', 'wpcredits-program-manager' ),
					(int) $state['stats']['records_seen'],
					$settings['sponsors_table']
				)
			);
		}

		// Airtable answers a read for an unknown field name with records carrying no fields
		// rather than an error, and a filtered or renamed table answers 200 with no records at
		// all. Neither is an empty table, and neither may replace a good index: the revoke
		// phase reads the index back and would detach every sponsor account on the site.
		$held = WPCPM_Sponsors_Index::rows();

		if ( 0 === self::named_rows( $state['rows'] ) && ! empty( $held ) ) {
			return new WP_Error(
				'wpcpm_sponsors_no_fields',
				sprintf(
					/* translators: %s: Airtable table ID. */
					__( 'Read %s but no record came back with a name or a status. Check the sponsors table and name field settings; the stored index was kept.', 'wpcredits-program-manager' ),
					$settings['sponsors_table']
				)
			);
		}

		WPCPM_Sponsors_Index::write( $state['rows'], $state['started'] );

		$state['logos']  = array_keys( WPCPM_Sponsors_Index::approved() );
		$state['phase']  = 'logos';
		$state['offset'] = null;

		return true;
	}

	/**
	 * Phase 3: one Approved sponsor's logo per step.
	 *
	 * @param array $state Sync state, by reference.
	 * @return true
	 */
	private static function phase_logos( array &$state ) {
		if ( empty( $state['logos'] ) ) {
			$state['phase'] = 'revoke';

			return true;
		}

		$record = (string) array_shift( $state['logos'] );
		$row    = WPCPM_Sponsors_Index::row( $record );

		if ( ! is_array( $row ) || empty( $row['logo']['url'] ) ) {
			return true;
		}

		$held = WPCPM_Sponsors_Index::logo_record( $record );

		// A logo the sponsor uploaded on the site is theirs; Airtable's never replaces it.
		if ( 'site' === $held['source'] ) {
			++$state['stats']['logos_kept'];

			return true;
		}

		// The same Airtable attachment, already copied and still in the library: nothing to do.
		if ( '' !== $held['airtable_id'] && $held['airtable_id'] === (string) $row['logo']['id'] && $held['colour'] > 0 && get_post( $held['colour'] ) ) {
			++$state['stats']['logos_kept'];

			return true;
		}

		$type = strtolower( (string) $row['logo']['type'] );

		if ( ! isset( WPCPM_Image_Upload::TYPES[ $type ] ) ) {
			++$state['stats']['logos_refused'];
			$state['notices'][] = sprintf(
				/* translators: 1: company name, 2: the attachment's type. */
				__( 'The logo of %1$s in Airtable is %2$s, which the site does not take (PNG, JPEG or WebP only). Ask the sponsor for another format.', 'wpcredits-program-manager' ),
				trim( (string) $row['name'] ),
				'' === $type ? __( 'of no known type', 'wpcredits-program-manager' ) : $type
			);

			return true;
		}

		// Downloaded before it is sized: a multi-megabyte attachment is a request the sync
		// makes every night, for a file nobody will ever serve past this check.
		if ( (int) $row['logo']['size'] > WPCPM_Image_Upload::max_bytes( array() ) ) {
			++$state['stats']['logos_refused'];
			$state['notices'][] = sprintf(
				/* translators: 1: company name, 2: the file's size in KB, 3: the site's ceiling in KB. */
				__( 'The logo of %1$s in Airtable is %2$s KB, larger than the %3$s KB this site allows. Ask the sponsor for a smaller file, or raise the ceiling in settings.', 'wpcredits-program-manager' ),
				trim( (string) $row['name'] ),
				number_format_i18n( (int) round( $row['logo']['size'] / 1024 ) ),
				number_format_i18n( (int) round( WPCPM_Image_Upload::max_bytes( array() ) / 1024 ) )
			);

			return true;
		}

		$copied = WPCPM_Image_Upload::sideload(
			(string) $row['logo']['url'],
			(string) $row['logo']['filename'],
			0,
			sprintf(
				/* translators: %s: company name. */
				__( '%s logo (color)', 'wpcredits-program-manager' ),
				trim( (string) $row['name'] )
			)
		);

		if ( is_wp_error( $copied ) ) {
			++$state['stats']['logos_refused'];
			$state['notices'][] = sprintf(
				/* translators: 1: company name, 2: why. */
				__( 'The logo of %1$s could not be copied: %2$s', 'wpcredits-program-manager' ),
				trim( (string) $row['name'] ),
				$copied->get_error_message()
			);

			return true;
		}

		WPCPM_Sponsors_Index::write_logo_record(
			$record,
			array(
				'colour'      => (int) $copied,
				'source'      => 'airtable',
				'airtable_id' => (string) $row['logo']['id'],
			)
		);
		++$state['stats']['logos_copied'];

		return true;
	}

	/**
	 * Phase 4: the accounts of sponsors that are no longer Approved.
	 *
	 * @param array $state    Sync state, by reference.
	 * @param array $settings Settings.
	 * @return true
	 */
	private static function phase_revoke( array &$state, array $settings ) {
		$rows    = WPCPM_Sponsors_Index::rows();
		$revoke  = isset( $settings['sponsor_on_inactive'] ) && 'revoke' === $settings['sponsor_on_inactive'];
		$members = get_users(
			array(
				'number' => -1,
				'fields' => 'ID',
				'role'   => WPCPM_Roles::ROLE_SPONSOR,
			)
		);
		$emptied = array();

		foreach ( (array) $members as $user_id ) {
			// This program's host returns stdClass rows for a `fields => 'ID'` query no
			// matter what is asked for; a bare (int) cast on that object yields 1 (with a
			// warning) and every account past the first reads as account 1, so revoke becomes
			// a silent no-op. id_of() reads the real ID whatever shape came back.
			$user_id = WPCPM_Roles::id_of( $user_id );
			$record  = WPCPM_Sponsor_Members::sponsor_of( $user_id );

			if ( '' === $record ) {
				continue;
			}

			if ( isset( $rows[ $record ] ) && WPCPM_Sponsors_Index::STATUS_APPROVED === $rows[ $record ]['status'] ) {
				continue;
			}

			if ( ! $revoke ) {
				++$state['stats']['inactive_kept'];
				continue;
			}

			$result = WPCPM_Sponsor_Members::detach( $user_id, WPCPM_Sponsor_Members::REASON_REVOKED, 0 );

			if ( is_wp_error( $result ) ) {
				$state['notices'][] = sprintf(
					/* translators: 1: user ID, 2: error message. */
					__( 'Could not detach account %1$d: %2$s', 'wpcredits-program-manager' ),
					$user_id,
					$result->get_error_message()
				);
				continue;
			}

			++$state['stats']['revoked'];
			$emptied[ $record ] = true;
		}

		// The base's checkbox says whether a sponsor has a site account; a sponsor whose last
		// account was just detached has none. Through the module, which owns every write to
		// that column, and only when the module is there to be asked.
		foreach ( array_keys( $emptied ) as $record ) {
			if ( empty( WPCPM_Sponsor_Members::members_of( $record ) ) && class_exists( 'WPCPM_Sponsors' ) && method_exists( 'WPCPM_Sponsors', 'mark_dashboard_account' ) ) {
				call_user_func( array( 'WPCPM_Sponsors', 'mark_dashboard_account' ), $record, false );
			}
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
	 * Claim the right to run a tick: `add_option()`'s test-and-set, with a stale takeover.
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
	 * Estimated completion percentage, from the phases' weights and their steps so far.
	 *
	 * @param array $state Sync state.
	 * @return int
	 */
	private static function percent( array $state ) {
		$phases = self::phases();
		$phase  = isset( $state['phase'] ) ? (string) $state['phase'] : '';

		if ( '' === $phase || 'done' === $phase ) {
			return isset( $state['phase'] ) ? 100 : 0;
		}

		$done = 0;

		foreach ( $phases as $key => $spec ) {
			if ( $key === $phase ) {
				$steps    = isset( $state['steps'][ $key ] ) ? (int) $state['steps'][ $key ] : 0;
				$expected = max( 1, (int) $spec['steps'] );
				$done    += (int) round( $spec['weight'] * min( 1, $steps / $expected ) );
				break;
			}

			$done += (int) $spec['weight'];
		}

		return max( 0, min( 99, $done ) );
	}

	/**
	 * One line about the phase, from the counts.
	 *
	 * @param string $phase The phase.
	 * @param array  $stats The counts.
	 * @return string
	 */
	private static function phase_detail( $phase, array $stats ) {
		switch ( $phase ) {
			case 'team':
				/* translators: %d: team members read. */
				return sprintf( __( '%d team members read', 'wpcredits-program-manager' ), (int) $stats['team_seen'] );
			case 'records':
				/* translators: 1: sponsors read, 2: of them Approved. */
				return sprintf( __( '%1$d sponsors read, %2$d Approved', 'wpcredits-program-manager' ), (int) $stats['records_seen'], (int) $stats['approved'] );
			case 'logos':
				/* translators: 1: logos copied, 2: kept, 3: refused. */
				return sprintf( __( '%1$d logos copied, %2$d kept, %3$d refused', 'wpcredits-program-manager' ), (int) $stats['logos_copied'], (int) $stats['logos_kept'], (int) $stats['logos_refused'] );
			case 'revoke':
				/* translators: 1: accounts detached, 2: accounts kept. */
				return sprintf( __( '%1$d accounts detached, %2$d kept', 'wpcredits-program-manager' ), (int) $stats['revoked'], (int) $stats['inactive_kept'] );
		}

		return '';
	}

	/**
	 * The first attachment of an Airtable attachments cell, reduced to what the site keeps.
	 *
	 * @param array $attachments The cell.
	 * @return array `id`, `url`, `filename`, `type`, `size`, `width`, `height`; empty when there is none.
	 */
	private static function first_attachment( array $attachments ) {
		$first = reset( $attachments );

		if ( ! is_array( $first ) || empty( $first['url'] ) ) {
			return array();
		}

		return array(
			'id'       => isset( $first['id'] ) ? (string) $first['id'] : '',
			'url'      => (string) $first['url'],
			'filename' => isset( $first['filename'] ) ? (string) $first['filename'] : '',
			'type'     => isset( $first['type'] ) ? strtolower( (string) $first['type'] ) : '',
			'size'     => isset( $first['size'] ) ? (int) $first['size'] : 0,
			'width'    => isset( $first['width'] ) ? (int) $first['width'] : 0,
			'height'   => isset( $first['height'] ) ? (int) $first['height'] : 0,
		);
	}

	/**
	 * The `Y-m-d` part of an Airtable date or datetime, or ''.
	 *
	 * @param string $value The cell.
	 * @return string
	 */
	private static function date_part( $value ) {
		$value = trim( (string) $value );

		return 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})/', $value, $m ) ? $m[1] : '';
	}

	/**
	 * How many rows carry a name or a status: the test for "Airtable answered with nothing".
	 *
	 * @param array $rows Rows as built.
	 * @return int
	 */
	private static function named_rows( array $rows ) {
		$named = 0;

		foreach ( $rows as $row ) {
			if ( '' !== trim( (string) $row['name'] ) || '' !== (string) $row['status'] ) {
				++$named;
			}
		}

		return $named;
	}
}
