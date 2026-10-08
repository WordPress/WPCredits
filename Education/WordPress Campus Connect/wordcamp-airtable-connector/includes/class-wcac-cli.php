<?php
/**
 * WP-CLI commands.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Drive the WordCamp Airtable Connector from the command line.
 *
 * The full backfill crawls ~1,500 camp sites. Through WP-Cron that takes many
 * hours of five-minute slices; run it here instead and it finishes in one go.
 */
class WCAC_CLI {

	/**
	 * Rewrite stored settings that a newer version stores differently.
	 *
	 * wcac_maybe_upgrade() is hooked on admin_init, which never fires under
	 * WP-CLI, so a site driven entirely from the command line kept a 1.0.x
	 * source site with a "user:pass@" authority in wp_options indefinitely --
	 * through every backup and staging clone. Every read strips it, so nothing
	 * leaked either way; this is the half that gets it out of the row. A
	 * cron-only site still needs the same call on the cron path.
	 *
	 * WP-CLI never exposes a constructor as a subcommand: it skips every method
	 * whose name begins with a double underscore.
	 *
	 * @return void
	 */
	public function __construct() {
		WCAC_Settings::maybe_upgrade();
	}

	/**
	 * Run a sync to completion.
	 *
	 * ## OPTIONS
	 *
	 * [--full]
	 * : Queue a complete backfill instead of an incremental sync.
	 *
	 * [--resume]
	 * : Do not queue anything new; just drain whatever is already pending.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac sync
	 *     wp wcac sync --full
	 *     wp wcac sync --resume
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	public function sync( $args, $assoc_args ) {
		if ( ! WCAC_Settings::is_configured() ) {
			WP_CLI::error( 'No Airtable token or base ID configured. Set them on the WordCamp Sync screen first.' );
		}

		$sync = new WCAC_Sync();

		if ( ! empty( $assoc_args['resume'] ) ) {
			WP_CLI::log( sprintf( 'Resuming: %d jobs pending.', $sync->pending() ) );
		} elseif ( ! empty( $assoc_args['full'] ) ) {
			$sync->enqueue_full();
			WP_CLI::log( 'Queued a full backfill.' );
		} else {
			$sync->enqueue_incremental();
			WP_CLI::log( 'Queued an incremental sync.' );
		}

		$slices = 0;

		while ( $sync->pending() > 0 ) {
			// A generous budget per slice: there is no web request to time out
			// here, and each slice re-reads the queue from the database.
			$result  = $sync->run_slice( 60 );
			$slices++;

			$state = $sync->state();

			WP_CLI::log(
				sprintf(
					'  slice %d: %d jobs done, %d pending, %d created, %d updated, %d errors',
					$slices,
					$result['processed'],
					$result['remaining'],
					$state['created'],
					$state['updated'],
					$state['errors']
				)
			);

			if ( 0 === $result['processed'] && $result['remaining'] > 0 ) {
				WP_CLI::warning( 'A slice made no progress -- is another run holding the lock? Stopping.' );
				break;
			}
		}

		$state = $sync->state();

		WP_CLI::success(
			sprintf(
				'Done. %d created, %d updated, %d errors.',
				$state['created'],
				$state['updated'],
				$state['errors']
			)
		);
	}

	/**
	 * Show the current queue and counters.
	 *
	 * The errors counter is cumulative since the last full backfill, so a green
	 * last slice never means "Campus Connect is current" -- that is what the
	 * campus rows below are for. Those name rows CREATED separately from rows
	 * updated, because a create is the outcome nothing here can undo, and they
	 * carry the consecutive-failure counter and the block reason, which used to
	 * be readable only as a "yes" with no history behind it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac status
	 *
	 * @return void
	 */
	public function status() {
		$sync    = new WCAC_Sync();
		$state   = $sync->state();
		$cred    = WCAC_Settings::central_credential();
		$print   = WCAC_Settings::credential_fingerprint();
		$blocked = $this->campus_blocked( $state );

		$stamped = (int) $this->cc( $state, 'cc_preflight_ok', 0 );
		$table   = WCAC_Settings::table_for( 'campus_connect' );
		$stale   = $stamped > 0 && ( (string) $this->cc( $state, 'cc_preflight_table', '' ) !== (string) $table );

		// The same four-way verdict the settings screen renders, in the same
		// order and the same words, because an operator reads one surface and
		// acts on the other. A configured site whose preflight has not passed
		// is not "ready": the job returns immediately without that gate, so
		// saying "ready" here sent people hunting for a fault elsewhere.
		if ( $blocked ) {
			$campus = 'blocked';
		} elseif ( ! WCAC_Settings::campus_connect_ready() ) {
			// "off" alone read as "nothing works". The scheduled run is what
			// the toggle stops; an attended --from-file import is unaffected.
			$campus = 'scheduled run off; import with --from-file still works';
		} elseif ( $stamped > 0 && ! $stale ) {
			$campus = 'ready';
		} else {
			$campus = 'configured, but the preflight below has not passed';
		}

		if ( ! $stamped ) {
			$preflight = 'not run';
		} elseif ( $stale ) {
			$preflight = 'stale: the table ID changed since it was run';
		} else {
			$preflight = 'ok ' . $this->stamp( $stamped );
		}

		$gaps = array_merge(
			array_keys( (array) $this->cc( $state, 'cc_unmapped', array() ) ),
			array_keys( (array) $this->cc( $state, 'cc_absent', array() ) )
		);

		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'key' => 'configured', 'value' => WCAC_Settings::is_configured() ? 'yes' : 'no' ),
				array( 'key' => 'base', 'value' => WCAC_Settings::get( 'base_id' ) ),
				array( 'key' => 'jobs pending', 'value' => $sync->pending() ),
				array( 'key' => 'camps cached', 'value' => count( $sync->camps() ) ),
				array( 'key' => 'mode', 'value' => $state['mode'] ? $state['mode'] : '-' ),
				array( 'key' => 'created', 'value' => $state['created'] ),
				array( 'key' => 'updated', 'value' => $state['updated'] ),
				array( 'key' => 'errors', 'value' => $state['errors'] ),
				array( 'key' => 'last slice', 'value' => $this->stamp( $state['last_run'] ) ),
				array( 'key' => 'campus connect', 'value' => $campus ),
				array( 'key' => 'central credential', 'value' => 'none' === $cred['source'] ? 'missing' : $cred['source'] ),
				array( 'key' => 'credential fingerprint', 'value' => '' === $print ? '-' : $print ),
				array( 'key' => 'campus blocked', 'value' => $this->block_text( $state, $blocked ) ),
				array( 'key' => 'campus consecutive failures', 'value' => $this->fails_text( $state ) ),
				array( 'key' => 'campus preflight', 'value' => $preflight ),
				array( 'key' => 'campus last run', 'value' => $this->stamp( $this->cc( $state, 'cc_last_ok', 0 ) ) ),
				array( 'key' => 'campus rows', 'value' => sprintf( '%s / %d read', $this->written_text( $state ), (int) $this->cc( $state, 'cc_last_rows', 0 ) ) ),
				array( 'key' => 'campus status gaps', 'value' => $gaps ? implode( ', ', $gaps ) : 'none' ),
				array( 'key' => 'campus series-event', 'value' => $this->cc( $state, 'cc_series', 0 ) ? 'syncing' : 'not returned by this credential' ),
				array( 'key' => 'campus last error', 'value' => $this->cc( $state, 'cc_last_error', '' ) ? $this->cc( $state, 'cc_last_error', '' ) : '-' ),
			),
			array( 'key', 'value' )
		);
	}

	/**
	 * Discard the pending queue.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac clear
	 *
	 * @return void
	 */
	public function clear() {
		$sync    = new WCAC_Sync();
		$pending = $sync->pending();

		$sync->clear_queue();

		WP_CLI::success( sprintf( 'Discarded %d pending jobs.', $pending ) );
	}

	/**
	 * Check that the configured token can reach the configured base.
	 *
	 * When a Central credential is stored it is probed too, against the identity
	 * route rather than the report -- the report generates every Campus Connect
	 * event server-side and this command is meant to be quick. A successful
	 * probe clears a credential-shaped Campus Connect latch and nothing else: a
	 * volume, creates, partial-write or contract block is evidence about rows,
	 * not about the credential, and stands until an operator clears it by hand.
	 * Use central-check for the full picture.
	 *
	 * The exit code reports Airtable and nothing else. A Central credential that
	 * Central rejects is a warning here, not an error: this command predates
	 * Campus Connect and is wired into monitoring that watches whether the five
	 * original syncs can still write, which a revoked application password does
	 * not affect. central-check is the command that exits non-zero for Central.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac test
	 *
	 * @return void
	 *
	 * The identity probe proves the credential authenticates. It does NOT prove
	 * the report route answers: a 403 there means the account authenticates but
	 * holds neither the campus_connect_viewer subrole nor view_wordcamp_reports,
	 * and reporting that as success is how an operator concludes they are
	 * finished when Campus Connect cannot read a thing. The default therefore says plainly what was not checked, and
	 * --deep fetches the report so monitoring can opt in to the expensive
	 * answer rather than paying for it on every run.
	 *
	 * ## OPTIONS
	 *
	 * [--deep]
	 * : Also fetch the Campus Connect report. Generates the whole report on
	 *   Central, so it is slow; do not put it in a frequent cron.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac test
	 *     wp wcac test --deep
	 */
	public function test( $args = array(), $assoc_args = array() ) {
		$result = ( new WCAC_Airtable() )->ping( WCAC_Settings::table_for( 'wordcamps' ) );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( ! WCAC_Settings::central_ready() ) {
			WP_CLI::success( 'Airtable responded. No Central credential is stored, so Campus Connect was not probed.' );
			return;
		}

		$probe = ( new WCAC_Source() )->central_identity_probe();

		if ( ! $probe['authenticated'] ) {
			WP_CLI::warning(
				sprintf(
					'Airtable responded, but Central rejected the credential (HTTP %d). Run: wp wcac central-check',
					$probe['status']
				)
			);

			WP_CLI::success( 'Airtable responded. Campus Connect cannot authenticate, which leaves the other five syncs unaffected.' );
			return;
		}

		if ( ! ( new WCAC_Sync() )->clear_campus_block() ) {
			WP_CLI::warning( 'A Campus Connect block is still in force; it was not set by the credential and this command does not clear it. See: wp wcac status' );
		}

		if ( ! empty( $assoc_args['deep'] ) ) {
			$report = ( new WCAC_Source() )->campus_connect();

			if ( is_wp_error( $report ) ) {
				$status = $this->error_status( $report );

				WP_CLI::warning(
					sprintf(
						'The credential authenticates, but the Campus Connect report route did not answer%s: %s Run: wp wcac central-check',
						$status ? sprintf( ' (HTTP %d)', $status ) : '',
						$report->get_error_message()
					)
				);

				WP_CLI::success( 'Airtable responded and Central accepted the credential, but Campus Connect cannot read the report. The other five syncs are unaffected.' );

				return;
			}

			WP_CLI::success( sprintf( 'Airtable responded, Central accepted the credential, and the report returned %d Campus Connect event(s).', count( $report ) ) );

			return;
		}

		WP_CLI::success( 'Airtable responded and Central accepted the credential. The report route itself was not probed -- run "wp wcac test --deep" or "wp wcac central-check" to confirm Campus Connect can actually read it.' );
	}

	/**
	 * Inspect and run the Campus Connect sync.
	 *
	 * Every mode reads the whole report in one shot: the route takes no page and
	 * no since parameter, so there is nothing to page through and nothing to
	 * resume. Under WP-CLI that read is allowed 45 seconds.
	 *
	 * ## OPTIONS
	 *
	 * [--statuses]
	 * : Reconcile every Status slug the report returns against the Airtable
	 *   field. Exits non-zero if anything is ABSENT or UNMAPPED.
	 *
	 * [--preflight]
	 * : Read back the WordCamp ID column and prove it is present and unique on
	 *   every existing row. Stamps the gate the sync job requires.
	 *
	 * [--dry-run]
	 * : Show a real before/after diff for the cells that would change. Writes
	 *   nothing.
	 *
	 * [--run]
	 * : Queue and drain a single Campus Connect job.
	 *
	 * [--accept-growth]
	 * : With --run, accept a report that has grown past the volume guard.
	 *
	 * [--clear-block]
	 * : With --run, discard a standing failure latch first. Asks for
	 *   confirmation on the terminal and cannot be answered by cron. Never put
	 *   this in a crontab line.
	 *
	 * [--from-file=<path>]
	 * : Read the report from an exported Campus Connect Details file instead of
	 *   from Central. For when the credential cannot reach the report route and
	 *   an operator has exported it from the browser instead. Takes the tab- or
	 *   comma-separated export with its header row. Every guard still applies,
	 *   because only the source of the rows changes. Lasts for this command
	 *   only: a later cron run goes back to the network. Not valid with
	 *   --preflight, which reads Airtable and never the report.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac campus-connect --statuses
	 *     wp wcac campus-connect --preflight
	 *     wp wcac campus-connect --dry-run
	 *     wp wcac campus-connect --run
	 *     wp wcac campus-connect --run --clear-block
	 *     wp wcac campus-connect --dry-run --from-file=/tmp/campus-connect.tsv
	 *     wp wcac campus-connect --run --from-file=/tmp/campus-connect.tsv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 * @return void
	 *
	 * @subcommand campus-connect
	 */
	public function campus_connect( $args, $assoc_args ) {
		$modes = array_keys(
			array_filter(
				array(
					'statuses'  => ! empty( $assoc_args['statuses'] ),
					'preflight' => ! empty( $assoc_args['preflight'] ),
					'dry-run'   => ! empty( $assoc_args['dry-run'] ),
					'run'       => ! empty( $assoc_args['run'] ),
				)
			)
		);

		if ( 1 !== count( $modes ) ) {
			WP_CLI::error( 'Choose exactly one of --statuses, --preflight, --dry-run or --run. (--accept-growth and --clear-block are modifiers for --run.)' );
		}

		// A modifier silently ignored is worse than a refusal here: --clear-block
		// is the operator's acknowledgement, and a run that swallowed it would
		// leave the latch standing while the terminal said nothing about it.
		if ( ! empty( $assoc_args['clear-block'] ) && 'run' !== $modes[0] ) {
			WP_CLI::error( '--clear-block is a modifier for --run.' );
		}

		$from_file = isset( $assoc_args['from-file'] ) ? trim( (string) $assoc_args['from-file'] ) : '';

		if ( '' !== $from_file ) {
			// Refused rather than ignored: --preflight reads the Airtable table
			// and never the report, so accepting a file here would tell the
			// operator their export had been checked when it had not.
			if ( 'preflight' === $modes[0] ) {
				WP_CLI::error( '--from-file has no effect on --preflight, which reads Airtable and never the report. Run --preflight on its own.' );
			}

			// Parsed once here so a bad file fails on the terminal, before any
			// job is queued, rather than inside a drained job where it would
			// read as a source failure and arm the latch.
			$probe = WCAC_Source::campus_connect_from_file( $from_file );

			if ( is_wp_error( $probe ) ) {
				WP_CLI::error( sprintf( 'Could not read the report file: %s', $probe->get_error_message() ) );
			}

			WCAC_Source::set_report_file( $from_file );

			WP_CLI::log(
				sprintf(
					'Reading the report from %s (%d rows). Central is not contacted; every other guard still applies.',
					$from_file,
					count( $probe )
				)
			);
		}

		/*
		 * Gate on what this invocation actually needs, not on what the
		 * scheduled job needs. campus_connect_ready() answers "will the cron
		 * run this", which folds together three unrelated things: a Central
		 * credential, the operator's toggle, and a destination table. A
		 * file-sourced import needs only the table, and --preflight reads
		 * Airtable and never the report, so requiring all three turned
		 * "stop the cron reaching for Central" into "stop importing at all".
		 */
		$needs_central = '' === $from_file && 'preflight' !== $modes[0];
		$this->require_campus_ready( $needs_central );

		switch ( $modes[0] ) {
			case 'statuses':
				$this->campus_statuses();
				break;

			case 'preflight':
				$this->campus_preflight();
				break;

			case 'dry-run':
				$this->campus_dry_run();
				break;

			case 'run':
				$this->campus_run( ! empty( $assoc_args['accept-growth'] ), ! empty( $assoc_args['clear-block'] ) );
				break;
		}
	}

	/**
	 * Diagnose access to central.wordcamp.org.
	 *
	 * This is the command a 401 points at, and it is step 0 of the runbook: run
	 * it before creating anything. A 401 has three causes and they are not
	 * distinguishable from the report route alone -- the credential is wrong or
	 * revoked, the account cannot use application passwords, or something in
	 * front of Central strips the Authorization header before PHP sees it. The
	 * anonymous scheme read and the identity probe tell them apart.
	 *
	 * Never prints the username or the password. The fingerprint is twelve hex
	 * characters of a salted hash and is not reversible.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac central-check
	 *
	 * @return void
	 *
	 * @subcommand central-check
	 */
	public function central_check() {
		$source = new WCAC_Source();
		$cred   = WCAC_Settings::central_credential();
		$advert = $source->central_auth_advertised();

		if ( ! $advert['ok'] ) {
			$schemes = 'could not read: ' . $advert['error'];
		} elseif ( empty( $advert['schemes'] ) ) {
			$schemes = 'none advertised';
		} else {
			$schemes = implode( ', ', $advert['schemes'] );
		}

		$probe    = array( 'status' => 0, 'authenticated' => false );
		$identity = 'not run (no credential stored)';

		if ( 'none' !== $cred['source'] ) {
			$probe    = $source->central_identity_probe();
			$identity = $probe['authenticated']
				? 'authenticated'
				: sprintf( 'rejected (HTTP %d)', $probe['status'] );
		}

		$report = $source->campus_connect();
		$status = 0;
		$rows   = 0;

		if ( is_wp_error( $report ) ) {
			$status = $this->error_status( $report );
			$route  = $report->get_error_message() . ( $status ? sprintf( ' (HTTP %d)', $status ) : '' );
		} else {
			$rows  = count( $report );
			$route = sprintf( 'ok, %d row(s)', $rows );
		}

		$fingerprint = WCAC_Settings::credential_fingerprint();

		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'key' => 'source site', 'value' => WCAC_Settings::source_root() ),
				array( 'key' => 'credential source', 'value' => 'none' === $cred['source'] ? 'missing' : $cred['source'] ),
				array( 'key' => 'credential fingerprint', 'value' => '' === $fingerprint ? '-' : $fingerprint ),
				array( 'key' => 'advertised auth', 'value' => $schemes ),
				array( 'key' => 'identity probe', 'value' => $identity ),
				array( 'key' => 'report route', 'value' => $route ),
			),
			array( 'key', 'value' )
		);

		if ( ! is_wp_error( $report ) ) {
			WP_CLI::success( sprintf( 'Central access works: %d Campus Connect event(s) readable.', $rows ) );
			return;
		}

		foreach ( $this->central_advice( $report, $status, $probe, $advert ) as $line ) {
			WP_CLI::log( $line );
		}

		WP_CLI::warning( 'Campus Connect cannot sync until the report route answers.' );
		WP_CLI::halt( 1 );
	}

	/**
	 * Store or remove the Central credential.
	 *
	 * The password is read from STDIN, never from argv, so it stays out of ps
	 * and out of shell history. At a terminal it is only prompted for once echo
	 * suppression has been proven to work; where it cannot be proven the command
	 * refuses to prompt and asks for the value to be piped in instead, so it
	 * never lands in scrollback or a CI job log. Nothing here prints, returns or
	 * logs the value.
	 *
	 * Storing is refused while WCAC_CENTRAL_USER and WCAC_CENTRAL_APP_PASSWORD
	 * are defined, matching the settings screen: a row those constants override
	 * is never read, so writing one only leaves a second copy to leak. Removing
	 * a database copy stays available, because that is how the copy goes away.
	 *
	 * ## OPTIONS
	 *
	 * [--user=<login>]
	 * : The login name of the account on Central.
	 *
	 * [--forget]
	 * : Remove the stored credential.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wcac central-credential --user=example
	 *     wp wcac central-credential --user=example < secret.txt
	 *     wp wcac central-credential --forget
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 * @return void
	 *
	 * @subcommand central-credential
	 */
	public function central_credential( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['forget'] ) ) {
			WCAC_Settings::forget_central();
			WP_CLI::success( 'Stored Central credential removed. A wp-config.php constant, if you use one, is untouched.' );
			return;
		}

		// The settings screen refuses this outright when wp-config.php defines
		// the credential. Without the same guard here a CLI store writes a
		// database row the constants override: inert, never read by
		// central_credential(), and confirmed back with the CONSTANT's
		// fingerprint, because credential_fingerprint() short-circuits to it.
		// set_secret() then sees the same fingerprint either side of the write
		// and fires no action, so there is no signal of any kind. --forget is
		// deliberately outside this guard, above: a database copy must stay
		// removable while the constants are in place.
		if ( 'constant' === WCAC_Settings::central_credential()['source'] ) {
			WP_CLI::error( 'The Central credential is defined in wp-config.php on this server, so storing one here would have no effect. Remove WCAC_CENTRAL_USER and WCAC_CENTRAL_APP_PASSWORD to manage it from the command line, or run --forget to delete a database copy.' );
		}

		$user = isset( $assoc_args['user'] ) ? trim( (string) $assoc_args['user'] ) : '';

		if ( '' === $user ) {
			WP_CLI::error( 'Pass the account name with --user=<login>, or --forget to remove the stored credential.' );
		}

		if ( false !== strpos( $user, ':' ) ) {
			WP_CLI::error( 'A colon cannot be used in an HTTP Basic auth username.' );
		}

		$user = sanitize_user( $user, false );

		if ( '' === $user ) {
			WP_CLI::error( 'That user name is empty once sanitised.' );
		}

		$secret = $this->read_secret();

		if ( '' === $secret ) {
			WP_CLI::error( 'No application password was read from STDIN; nothing was stored.' );
		}

		WCAC_Settings::set_secret( 'central_user', $user );
		WCAC_Settings::set_secret( 'central_app_password', $secret );

		unset( $secret );

		WP_CLI::success( sprintf( 'Credential stored. Fingerprint: %s', WCAC_Settings::credential_fingerprint() ) );
		WP_CLI::log( 'Check it with: wp wcac central-check' );
	}

	/* ---------------------------------------------------------------------
	 * Campus Connect helpers. Private, deliberately: WP-CLI turns every public
	 * method on this class into a subcommand.
	 * ------------------------------------------------------------------ */

	/**
	 * Stop, naming what is missing, unless this invocation can proceed.
	 *
	 * Deliberately not WCAC_Settings::campus_connect_ready(), which answers a
	 * different question: whether the SCHEDULED job will run. An operator who
	 * switches the toggle off is saying "stop reaching for Central on a timer",
	 * not "refuse an attended import I am running myself".
	 *
	 * @param bool $needs_central Whether this invocation will read the report
	 *                            over the network. False for --preflight, which
	 *                            reads Airtable, and for any --from-file run.
	 * @return void
	 */
	private function require_campus_ready( $needs_central ) {
		$missing = array();

		// Needed by every mode: there is nothing to read from or write to
		// without it.
		if ( '' === WCAC_Settings::table_for( 'campus_connect' ) ) {
			$missing[] = 'no Campus Connect Events table ID is configured';
		}

		if ( $needs_central ) {
			if ( ! WCAC_Settings::central_ready() ) {
				$missing[] = 'no Central credential is stored (run: wp wcac central-credential --user=<login>)';
			}

			if ( empty( WCAC_Settings::get( 'sync_campus_connect' ) ) ) {
				$missing[] = 'the Campus Connect sync is switched off on the WordCamp Sync screen, so this command will not reach for Central. Pass --from-file=<path> to import an export instead';
			}
		}

		if ( $missing ) {
			WP_CLI::error( 'Campus Connect is not ready: ' . implode( '; ', $missing ) . '.' );
		}
	}

	/**
	 * Stop early when the plugin has no Airtable credentials at all.
	 *
	 * @return void
	 */
	private function require_airtable() {
		if ( ! WCAC_Settings::is_configured() ) {
			WP_CLI::error( 'No Airtable token or base ID configured. Set them on the WordCamp Sync screen first.' );
		}
	}

	/**
	 * Fetch the report, or stop with a message that names the HTTP status.
	 *
	 * @return array The report's data list.
	 */
	private function campus_rows() {
		$rows = ( new WCAC_Source() )->campus_connect();

		if ( is_wp_error( $rows ) ) {
			$status = $this->error_status( $rows );

			WP_CLI::error(
				sprintf(
					'Could not read the Campus Connect report%s: %s Run "wp wcac central-check" to tell the causes apart.',
					$status ? sprintf( ' (HTTP %d)', $status ) : '',
					$rows->get_error_message()
				)
			);
		}

		if ( empty( $rows ) ) {
			WP_CLI::error( 'The Campus Connect report returned no rows. Nothing to inspect.' );
		}

		$this->report_key_drift( $rows );

		return $rows;
	}

	/**
	 * Warn about a report whose key set no longer matches the contract.
	 *
	 * A renamed key is otherwise a permanent, completely silent no-op: the
	 * column simply stops updating and the run still reports a clean pass.
	 *
	 * @param array $rows The report's data list.
	 * @return void
	 */
	private function report_key_drift( array $rows ) {
		$keys = WCAC_Mapper::campus_key_report( $rows );

		if ( ! empty( $keys['missing'] ) ) {
			WP_CLI::warning(
				sprintf(
					'The report is missing %d documented key(s): %s. Those columns cannot update.',
					count( $keys['missing'] ),
					implode( ', ', $keys['missing'] )
				)
			);
		}

		if ( ! empty( $keys['unexpected'] ) ) {
			WP_CLI::log(
				sprintf(
					'Note: %d key(s) the plugin does not know: %s',
					count( $keys['unexpected'] ),
					implode( ', ', $keys['unexpected'] )
				)
			);
		}

		if ( ! $keys['series'] ) {
			WP_CLI::log( 'Note: Series Event is absent from this report. That key is only sent to a caller holding manage_options.' );
		}
	}

	/**
	 * Map every report row exactly as the sync job would, and tally what it did.
	 *
	 * @param array $rows The report's data list.
	 * @return array Records keyed by merge value, plus counters.
	 */
	private function campus_map( array $rows ) {
		$allowed = WCAC_Mapper::campus_allowed_status();

		$out = array(
			'read'          => count( $rows ),
			'records'       => array(),
			'skipped'       => 0,
			'collisions'    => array(),
			'unmapped'      => array(),
			'absent'        => array(),
			'missing'       => 0,
			'date_unparsed' => 0,
			'int_unparsed'  => 0,
		);

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$out['skipped']++;
				continue;
			}

			$mapped = WCAC_Mapper::campus_event( $row, $allowed );

			$out['date_unparsed'] += (int) $mapped['notes']['date_unparsed'];
			$out['int_unparsed']  += (int) $mapped['notes']['int_unparsed'];

			if ( 'unmapped' === $mapped['status'] || 'absent' === $mapped['status'] ) {
				$bucket = $mapped['status'];
				$slug   = '' === $mapped['slug'] ? '(blank)' : $mapped['slug'];

				$out[ $bucket ][ $slug ] = isset( $out[ $bucket ][ $slug ] ) ? $out[ $bucket ][ $slug ] + 1 : 1;
			} elseif ( 'missing' === $mapped['status'] ) {
				$out['missing']++;
			}

			if ( $mapped['id'] < 1 ) {
				$out['skipped']++;
				continue;
			}

			if ( isset( $out['records'][ $mapped['id'] ] ) ) {
				$out['collisions'][] = $mapped['id'];
			}

			// Last write wins, exactly as the job does it, so the diff shows
			// what would really be sent.
			$out['records'][ $mapped['id'] ] = $mapped['fields'];
		}

		return $out;
	}

	/**
	 * --statuses: reconcile every Status the report carries with the field.
	 *
	 * @return void
	 */
	private function campus_statuses() {
		$rows    = $this->campus_rows();
		$allowed = WCAC_Mapper::campus_allowed_status();
		$labels  = array_values( WCAC_Mapper::CAMPUS_STATUS );
		$tally   = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$raw = isset( $row['Status'] ) && is_scalar( $row['Status'] ) ? trim( (string) $row['Status'] ) : '';
			$key = '' === $raw ? '(blank)' : $raw;

			if ( isset( $tally[ $key ] ) ) {
				$tally[ $key ]['rows']++;
				continue;
			}

			$resolved = WCAC_Mapper::campus_status( $raw, $allowed );
			$verdict  = $this->status_verdict( $resolved['why'] );
			$label    = isset( WCAC_Mapper::CAMPUS_STATUS[ $raw ] ) ? WCAC_Mapper::CAMPUS_STATUS[ $raw ] : '';

			if ( '' === $label && in_array( $raw, $labels, true ) ) {
				// A display label rather than a slug. campus_status() calls that
				// UNMAPPED when the option is not present, so ABSENT is not the
				// only "add the option" signal.
				$label = $raw;
			}

			if ( null !== $resolved['label'] ) {
				$exists = 'yes';
			} elseif ( '' !== $label ) {
				$exists = 'no';
			} else {
				$exists = 'n/a';
			}

			$tally[ $key ] = array(
				'slug'           => $key,
				'rows'           => 1,
				'would write'    => null === $resolved['label'] ? '-' : $resolved['label'],
				'option exists?' => $exists,
				'verdict'        => $verdict,
				'label'          => $label,
			);
		}

		uasort(
			$tally,
			function ( $a, $b ) {
				return $b['rows'] - $a['rows'];
			}
		);

		WP_CLI\Utils\format_items(
			'table',
			array_values( $tally ),
			array( 'slug', 'rows', 'would write', 'option exists?', 'verdict' )
		);

		$blocked = array();
		$add     = array();

		foreach ( $tally as $entry ) {
			if ( 'ABSENT' !== $entry['verdict'] && 'UNMAPPED' !== $entry['verdict'] ) {
				continue;
			}

			$blocked[] = $entry['slug'];

			if ( '' !== $entry['label'] ) {
				$add[ $entry['label'] ] = true;
			}
		}

		if ( empty( $blocked ) ) {
			WP_CLI::success( sprintf( 'Every Status in the report resolves to an option this site may write (%d row(s) read).', count( $rows ) ) );
			return;
		}

		if ( ! empty( $add ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Add these option(s) to the Status field in Airtable with exactly this spelling,' );
			WP_CLI::log( 'then paste the same text into Approved Status options on the settings screen:' );

			foreach ( array_keys( $add ) as $label ) {
				WP_CLI::log( '  ' . $label );
			}
		}

		WP_CLI::warning(
			sprintf(
				'%d Status value(s) would be left untouched: %s',
				count( $blocked ),
				implode( ', ', $blocked )
			)
		);

		WP_CLI::halt( 1 );
	}

	/**
	 * The operator-facing verdict for a campus_status() reason code.
	 *
	 * @param string $why Reason code.
	 * @return string
	 */
	private function status_verdict( $why ) {
		$map = array(
			''         => 'OK',
			'missing'  => 'MISSING',
			'unmapped' => 'UNMAPPED',
			'absent'   => 'ABSENT',
		);

		return isset( $map[ $why ] ) ? $map[ $why ] : 'UNKNOWN';
	}

	/**
	 * --preflight: prove the merge column is present and unique, and stamp the
	 * gate the sync job refuses to run without.
	 *
	 * A blank merge value is invisible to the write path: the upsert matches
	 * nothing, so the curated blank-ID row stays exactly where it is and a fresh
	 * synced row appears beside it, permanently, because this plugin has no
	 * delete verb. A duplicated value is worse -- Airtable fails the whole
	 * ten-record chunk it lands in.
	 *
	 * @return void
	 */
	private function campus_preflight() {
		$this->require_airtable();

		$table = WCAC_Settings::table_for( 'campus_connect' );
		$sync  = new WCAC_Sync();
		$cells = ( new WCAC_Airtable() )->list_records( $table, array( 'WordCamp ID' ) );

		if ( is_wp_error( $cells ) ) {
			$sync->set_campus_preflight( false, '' );
			WP_CLI::error( sprintf( 'Could not read %s: %s', $table, $cells->get_error_message() ) );
		}

		$blank = array();
		$odd   = array();
		$byid  = array();

		foreach ( $cells as $record_id => $row ) {
			$value = isset( $row['WordCamp ID'] ) ? $row['WordCamp ID'] : null;

			if ( null === $value || '' === $value ) {
				$blank[] = $record_id;
				continue;
			}

			if ( ! is_scalar( $value ) || ! is_numeric( trim( (string) $value ) ) ) {
				$odd[] = $record_id;
				continue;
			}

			$id = (string) (int) trim( (string) $value );

			$byid[ $id ][] = $record_id;
		}

		$dupes = array();

		foreach ( $byid as $id => $records ) {
			if ( count( $records ) > 1 ) {
				$dupes[ $id ] = $records;
			}
		}

		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'key' => 'table', 'value' => $table ),
				array( 'key' => 'rows read', 'value' => count( $cells ) ),
				array( 'key' => 'blank WordCamp ID', 'value' => count( $blank ) ),
				array( 'key' => 'non-numeric WordCamp ID', 'value' => count( $odd ) ),
				array( 'key' => 'duplicated WordCamp ID', 'value' => count( $dupes ) ),
			),
			array( 'key', 'value' )
		);

		if ( ! empty( $blank ) ) {
			WP_CLI::log( 'Blank merge value on record(s): ' . $this->id_list( $blank ) );
		}

		if ( ! empty( $odd ) ) {
			WP_CLI::log( 'Non-numeric merge value on record(s): ' . $this->id_list( $odd ) );
		}

		foreach ( $dupes as $id => $records ) {
			WP_CLI::log( sprintf( 'WordCamp ID %s is on %d records: %s', $id, count( $records ), $this->id_list( $records ) ) );
		}

		$this->preflight_drift( $byid );

		if ( ! empty( $blank ) || ! empty( $odd ) || ! empty( $dupes ) ) {
			$sync->set_campus_preflight( false, '' );

			WP_CLI::error(
				sprintf(
					'%d blank, %d non-numeric and %d duplicated merge value(s). Fix them in Airtable, then run this again.',
					count( $blank ),
					count( $odd ),
					count( $dupes )
				)
			);
		}

		$sync->set_campus_preflight( true, $table );

		// What the stamp actually unblocks depends on whether the scheduled run
		// is switched on. Saying "the sync job will now run" to an operator who
		// has deliberately switched it off is how a gate gets blamed for
		// silence it is not causing.
		$unblocks = WCAC_Settings::campus_connect_ready()
			? 'the scheduled sync will now run'
			: 'the scheduled sync is switched off, so this unblocks an attended run only (--run, with --from-file while Central is unreachable)';

		WP_CLI::success(
			sprintf(
				'%d row(s) in %s carry a present, unique WordCamp ID. Preflight stamped; %s.',
				count( $cells ),
				$table,
				$unblocks
			)
		);
	}

	/**
	 * Name the drift in BOTH directions, destructive one first.
	 *
	 * Airtable rows the report no longer mentions are harmless: nothing here
	 * deletes them. Report IDs with no Airtable row are not, because that is
	 * exactly the set performUpsert would CREATE on the next run -- one new
	 * permanent row each, beside the curated ones, on a table this plugin has
	 * no verb to delete from. Reporting only the harmless direction told an
	 * operator "drift: none" on the run before 142 orphans appeared.
	 *
	 * Still informational: a genuinely new camp upstream is an ordinary create,
	 * so this names the set and does not fail the preflight.
	 *
	 * @param array $byid Merge value => Airtable record IDs.
	 * @return void
	 */
	private function preflight_drift( array $byid ) {
		$rows = ( new WCAC_Source() )->campus_connect();

		if ( is_wp_error( $rows ) ) {
			WP_CLI::warning( 'Could not read the report, so drift was not checked: ' . $rows->get_error_message() );
			return;
		}

		$live = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = WCAC_Mapper::campus_int( isset( $row['ID'] ) ? $row['ID'] : null );

			if ( null !== $id && $id > 0 ) {
				$live[ (string) $id ] = true;
			}
		}

		$gone   = array_values( array_diff( array_keys( $byid ), array_keys( $live ) ) );
		$unseen = array_values( array_diff( array_keys( $live ), array_keys( $byid ) ) );

		if ( empty( $gone ) && empty( $unseen ) ) {
			WP_CLI::log(
				sprintf(
					'Drift: none in either direction. Every one of the %d report row(s) has an Airtable row, and every Airtable row is in the report.',
					count( $live )
				)
			);
			return;
		}

		// The direction that writes. Named first, and as a warning, because it
		// is the only one of the two an operator has to act on before a run.
		if ( ! empty( $unseen ) ) {
			WP_CLI::warning(
				sprintf(
					'Drift: %d report ID(s) have no Airtable row, so the next run would CREATE %d row(s): %s. A created row is permanent -- this plugin has no delete verb. Check them against the table before running: an edited or cleared WordCamp ID cell reaches here as a create, and looks identical to a genuinely new camp.',
					count( $unseen ),
					count( $unseen ),
					$this->id_list( $unseen )
				)
			);
		}

		if ( ! empty( $gone ) ) {
			WP_CLI::log(
				sprintf(
					'Drift: %d Airtable row(s) whose WordCamp ID is not in the report: %s. Nothing deletes them.',
					count( $gone ),
					$this->id_list( $gone )
				)
			);
		}

		WP_CLI::log( sprintf( 'Drift checked against %d report row(s) and %d Airtable merge value(s).', count( $live ), count( $byid ) ) );
	}

	/**
	 * --dry-run: the only review the 142 hand-curated rows get.
	 *
	 * Prints a real before/after for every cell that would change, not the
	 * computed payload: a payload tells you what would be sent, and what matters
	 * here is which curated cell would be overwritten with what.
	 *
	 * @return void
	 */
	private function campus_dry_run() {
		$this->require_airtable();

		$rows   = $this->campus_rows();
		$mapped = $this->campus_map( $rows );
		$table  = WCAC_Settings::table_for( 'campus_connect' );

		// Synced At is deliberately absent. The sync stamps it on every row on
		// every run, so including it here would report 142 changed cells for
		// ever and bury the handful that actually matter. The comparison loop
		// below skips any field missing from this list, so leaving it out is
		// all that is needed.
		$fields = array(
			'WordCamp ID',
			'Name',
			'Status',
			'Institution Name',
			'Start Date',
			'End Date',
			'City',
			'Country',
			'Anticipated Attendees',
			'Actual Attendees',
			'Series Event',
			'Created',
			'URL',
		);

		$client = new WCAC_Airtable();
		$series = true;
		$cells  = $client->list_records( $table, $fields );

		if ( is_wp_error( $cells ) && 422 === $this->error_status( $cells ) ) {
			// Series Event is the one field in that list the sync writes only
			// when the report carries it, so it is the one that may not exist
			// as a column -- and Airtable answers an unknown name in fields[]
			// with a 422 for the whole read. Losing the only review these rows
			// get over one column would be the worse failure, so drop it and
			// read once more. Only a 422 is retried: a 401, a 429 or a 5xx
			// would fail again and does not deserve a second request.
			$first  = $cells;
			$fields = array_values( array_diff( $fields, array( 'Series Event' ) ) );
			$cells  = $client->list_records( $table, $fields );

			if ( is_wp_error( $cells ) ) {
				WP_CLI::error( sprintf( 'Could not read %s: %s', $table, $first->get_error_message() ) );
			}

			$series = false;

			WP_CLI::warning(
				sprintf(
					'Reading %s with Series Event failed (%s), so it was read without that column and Series Event is not in this diff -- the sync still writes it.',
					$table,
					$first->get_error_message()
				)
			);
		}

		if ( is_wp_error( $cells ) ) {
			WP_CLI::error( sprintf( 'Could not read %s: %s', $table, $cells->get_error_message() ) );
		}

		$current = array();
		$dupes   = array();
		$unjoin  = 0;

		foreach ( $cells as $row ) {
			$value = isset( $row['WordCamp ID'] ) ? $row['WordCamp ID'] : null;

			if ( null === $value || '' === $value || ! is_scalar( $value ) || ! is_numeric( trim( (string) $value ) ) ) {
				$unjoin++;
				continue;
			}

			$id = (int) trim( (string) $value );

			if ( isset( $current[ $id ] ) ) {
				$dupes[ $id ] = true;
				continue;
			}

			$current[ $id ] = $row;
		}

		$changes  = array();
		$creates  = array();
		$touched  = array();
		$by_field = array();

		foreach ( $mapped['records'] as $id => $payload ) {
			if ( ! isset( $current[ $id ] ) ) {
				$creates[] = $id;
				continue;
			}

			if ( isset( $dupes[ $id ] ) ) {
				continue;
			}

			foreach ( $payload as $field => $value ) {
				if ( 'WordCamp ID' === $field ) {
					continue;
				}

				if ( ! in_array( $field, $fields, true ) ) {
					continue;
				}

				$now = array_key_exists( $field, $current[ $id ] ) ? $current[ $id ][ $field ] : null;

				if ( ! $this->cell_differs( $now, $value ) ) {
					continue;
				}

				$changes[] = array(
					'WordCamp ID' => $id,
					'field'       => $field,
					'current'     => $this->cell_text( $now ),
					'new'         => $this->cell_text( $value ),
				);

				$touched[ $id ]     = true;
				$by_field[ $field ] = isset( $by_field[ $field ] ) ? $by_field[ $field ] + 1 : 1;
			}
		}

		if ( empty( $changes ) ) {
			WP_CLI::log( 'No existing cell would change.' );
		} else {
			WP_CLI\Utils\format_items(
				'table',
				$changes,
				array( 'WordCamp ID', 'field', 'current', 'new' )
			);

			WP_CLI::log( 'Values longer than 120 characters are shortened for display only.' );
		}

		if ( ! empty( $by_field ) ) {
			arsort( $by_field );

			$summary = array();

			foreach ( $by_field as $field => $count ) {
				$summary[] = array( 'field' => $field, 'cells' => $count );
			}

			WP_CLI\Utils\format_items( 'table', $summary, array( 'field', 'cells' ) );
		}

		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'key' => 'report rows', 'value' => $mapped['read'] ),
				array( 'key' => 'rows to send', 'value' => count( $mapped['records'] ) ),
				array( 'key' => 'would create', 'value' => count( $creates ) ),
				array( 'key' => 'would update', 'value' => count( $touched ) ),
				array( 'key' => 'cells changed', 'value' => count( $changes ) ),
				array( 'key' => 'dropped, no usable ID', 'value' => $mapped['skipped'] ),
				array( 'key' => 'duplicate IDs in report', 'value' => count( $mapped['collisions'] ) ),
				array( 'key' => 'Airtable rows not joinable', 'value' => $unjoin ),
				array( 'key' => 'Airtable duplicate IDs', 'value' => count( $dupes ) ),
				array( 'key' => 'status missing', 'value' => $mapped['missing'] ),
				array( 'key' => 'status unmapped', 'value' => array_sum( $mapped['unmapped'] ) ),
				array( 'key' => 'status option absent', 'value' => array_sum( $mapped['absent'] ) ),
				array( 'key' => 'dates not parsed', 'value' => $mapped['date_unparsed'] ),
				array( 'key' => 'numbers not numeric', 'value' => $mapped['int_unparsed'] ),
			),
			array( 'key', 'value' )
		);

		if ( ! empty( $creates ) ) {
			WP_CLI::warning(
				sprintf(
					'Would CREATE %d row(s), WordCamp ID(s): %s. Each is a new permanent row beside the curated ones; this plugin has no delete verb.',
					count( $creates ),
					$this->id_list( $creates )
				)
			);
		}

		if ( ! empty( $mapped['collisions'] ) ) {
			WP_CLI::warning( 'The report carries the same WordCamp ID more than once: ' . $this->id_list( array_values( array_unique( $mapped['collisions'] ) ) ) . '. Only the last one would be sent.' );
		}

		if ( ! empty( $dupes ) || $unjoin > 0 ) {
			WP_CLI::warning( 'Some Airtable rows could not be joined on WordCamp ID, so their cells are not in this diff. Run: wp wcac campus-connect --preflight' );
		}

		foreach ( array( 'unmapped', 'absent' ) as $bucket ) {
			if ( empty( $mapped[ $bucket ] ) ) {
				continue;
			}

			WP_CLI::warning(
				sprintf(
					'Status %s on %d row(s): %s. Those cells would be left untouched. Run: wp wcac campus-connect --statuses',
					$bucket,
					array_sum( $mapped[ $bucket ] ),
					implode( ', ', array_keys( $mapped[ $bucket ] ) )
				)
			);
		}

		if ( $series ) {
			$sends = false;

			foreach ( $mapped['records'] as $payload ) {
				if ( array_key_exists( 'Series Event', $payload ) ) {
					$sends = true;
					break;
				}
			}

			if ( ! $sends ) {
				WP_CLI::log( 'Series Event is in this diff, but the report did not send that key, so no Series Event cell would change.' );
			}
		}

		WP_CLI::success( 'Dry run only. Nothing was written to Airtable and no gate was stamped.' );
	}

	/**
	 * Whether a stored cell and a computed value actually differ.
	 *
	 * @param mixed $current Value Airtable holds; null when the cell is empty.
	 * @param mixed $new     Value the mapper produced.
	 * @return bool
	 */
	private function cell_differs( $current, $new ) {
		if ( null === $current ) {
			return true;
		}

		if ( is_array( $current ) || is_array( $new ) ) {
			return wp_json_encode( $current ) !== wp_json_encode( $new );
		}

		if ( is_bool( $current ) ) {
			$current = $current ? 1 : 0;
		}

		$now  = trim( (string) $current );
		$next = trim( (string) $new );

		if ( is_numeric( $now ) && is_numeric( $next ) ) {
			return (float) $now !== (float) $next;
		}

		// A date field configured as date-time comes back as an ISO datetime.
		// Without this every date would read as changed on every row, which
		// would bury the diffs that matter.
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $next ) && 0 === strpos( $now, $next . 'T' ) ) {
			return false;
		}

		return $now !== $next;
	}

	/**
	 * A cell rendered for a terminal column.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	private function cell_text( $value ) {
		if ( null === $value ) {
			return '(blank)';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( ! is_scalar( $value ) ) {
			$value = wp_json_encode( $value );
		}

		$text = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );

		if ( '' === $text ) {
			return '(blank)';
		}

		return mb_strlen( $text ) > 120 ? mb_substr( $text, 0, 117 ) . '...' : $text;
	}

	/**
	 * A capped, comma-separated list of identifiers.
	 *
	 * @param array $ids  Identifiers.
	 * @param int   $show How many to name.
	 * @return string
	 */
	private function id_list( array $ids, $show = 20 ) {
		$show = max( 1, (int) $show );

		if ( count( $ids ) <= $show ) {
			return implode( ', ', $ids );
		}

		return implode( ', ', array_slice( $ids, 0, $show ) ) . sprintf( ' and %d more', count( $ids ) - $show );
	}

	/**
	 * --run: queue one Campus Connect job and drain just that job.
	 *
	 * The lock check is not politeness. The cron keeps running while an operator
	 * works through the runbook, and two independent writers against one 5 req/s
	 * per-base limit makes Airtable answer 429 -- which this plugin absorbs with
	 * a 30 second sleep, up to three times, in a slice that did not cause it.
	 *
	 * A standing failure latch stops this command dead. Queueing is not evidence
	 * that anything was put right, so --run on its own no longer clears one: it
	 * prints the reason and exits non-zero, which is what makes the natural
	 * crontab line safe. --clear-block is the operator saying they read that
	 * reason and chose to discard it, and it prompts on the terminal, so cron
	 * cannot answer it even if the flag is pasted into the crontab.
	 *
	 * @param bool $accept_growth Waive the volume guard for this run.
	 * @param bool $clear_block   Discard a standing latch, on acknowledgement.
	 * @return void
	 */
	private function campus_run( $accept_growth, $clear_block = false ) {
		$this->require_airtable();

		if ( get_transient( WCAC_Sync::LOCK ) ) {
			WP_CLI::error( 'Another sync slice holds the lock. Wait for it to finish, or press Discard queue first.' );
		}

		$sync   = new WCAC_Sync();
		$before = $sync->state();

		// Before allow_campus_growth_once(), deliberately. That call arms a
		// state flag, and an error raised after it would leave the flag armed
		// and invisible for whatever runs next, unattended.
		if ( $this->campus_blocked( $before ) ) {
			$why = $this->cc( $before, 'cc_block_why', 'no reason recorded' );

			if ( ! $clear_block ) {
				WP_CLI::log( 'Reason: ' . $why );
				WP_CLI::error( 'Campus Connect is blocked and this command no longer clears it. Put the cause right, then run: wp wcac campus-connect --run --clear-block' );
			}

			WP_CLI::log( 'The Campus Connect sync is blocked. Reason: ' . $why );

			// One argument on purpose. WP_CLI::confirm() only skips the prompt
			// when $assoc_args['yes'] is set, so there is no --yes escape
			// hatch here: under cron STDIN is /dev/null, the read fails, the
			// answer is not "y" and WP-CLI halts non-zero. That is the
			// attended/unattended test enforced mechanically.
			WP_CLI::confirm( 'Clear that block and run now?' );
		}

		if ( $accept_growth ) {
			/*
			 * One flag waives three guards -- volume, creates and the zero-write
			 * alarm -- and it is re-armed on every invocation, so a crontab line
			 * carrying it prevents every block on every run, for ever, with no
			 * human ever seeing one. Gate it exactly as --clear-block is gated:
			 * one argument, so there is no --yes escape hatch, and under cron
			 * STDIN is /dev/null and WP-CLI halts non-zero.
			 */
			WP_CLI::log( 'This waives the volume guard, the creates guard and the zero-write alarm for this run. Those guards are what stop a report regression writing rows that cannot be deleted.' );
			WP_CLI::confirm( 'Waive them for this run?' );

			$sync->allow_campus_growth_once();
			WP_CLI::log( 'Volume guard waived for this run.' );
		}

		if ( $sync->queue_campus_now( $clear_block ) ) {
			WP_CLI::log( 'Queued a Campus Connect job at the head of the queue.' );
		} else {
			WP_CLI::log( 'A Campus Connect job was already pending; it was moved to the head of the queue.' );
		}

		$slices  = 0;
		$jobs    = 0;
		$drained = array();

		while ( $this->campus_queued( $sync ) ) {
			// run_slice() shifts jobs off the head one at a time and push()
			// only ever appends, so the jobs a slice ran are the first
			// "processed" entries of the queue as it stood before the slice.
			$queued = $sync->queue();

			// A one second budget runs at most one SLOW job: run_slice() tests
			// the budget between jobs and never inside one. It is not a
			// guarantee of one job, though -- the Campus Connect job returns
			// instantly when the preflight gate is unstamped, which leaves the
			// budget on the clock for whatever sits behind it. Hence the tally
			// below, and the warning that names what else ran.
			$result = $sync->run_slice( 1, 'cc' );
			$slices++;
			$jobs += (int) $result['processed'];

			foreach ( array_slice( $queued, 0, (int) $result['processed'] ) as $job ) {
				$to = is_array( $job ) && isset( $job['to'] ) && is_scalar( $job['to'] ) ? (string) $job['to'] : '';

				if ( 'cc' === $to ) {
					continue;
				}

				$name             = $this->job_name( $to );
				$drained[ $name ] = isset( $drained[ $name ] ) ? $drained[ $name ] + 1 : 1;
			}

			if ( 0 === $result['processed'] ) {
				WP_CLI::warning( 'A slice made no progress -- is another run holding the lock? Stopping.' );
				break;
			}

			if ( $slices >= 50 ) {
				WP_CLI::warning( sprintf( 'Stopped after 50 slices (%d job(s)) with a Campus Connect job still pending.', $jobs ) );
				break;
			}
		}

		$state = $sync->state();

		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'key' => 'jobs run', 'value' => $jobs ),
				array( 'key' => 'slices', 'value' => $slices ),
				array( 'key' => 'other jobs run', 'value' => empty( $drained ) ? 'none' : $this->job_summary( $drained ) ),
				array( 'key' => 'rows read', 'value' => $this->cc( $state, 'cc_last_rows', 0 ) ),
				array( 'key' => 'rows written', 'value' => $this->written_text( $state ) ),
				array( 'key' => 'consecutive failures', 'value' => $this->fails_text( $state ) ),
				array( 'key' => 'blocked', 'value' => $this->block_text( $state, $this->campus_blocked( $state ) ) ),
				array( 'key' => 'last ok', 'value' => $this->stamp( $this->cc( $state, 'cc_last_ok', 0 ) ) ),
				array( 'key' => 'last error', 'value' => $this->cc( $state, 'cc_last_error', '' ) ? $this->cc( $state, 'cc_last_error', '' ) : '-' ),
			),
			array( 'key', 'value' )
		);

		WP_CLI::log( 'Per-row detail is in the log: wp wcac status, or the WordCamp Sync screen.' );

		if ( ! empty( $drained ) ) {
			WP_CLI::warning(
				sprintf(
					'This run also advanced %d queued job(s) that are not Campus Connect: %s. They fetched from wordcamp.org and wrote to their own Airtable tables.',
					array_sum( $drained ),
					$this->job_summary( $drained )
				)
			);
		}

		if ( $this->campus_blocked( $state ) ) {
			WP_CLI::error( 'Campus Connect is blocked: ' . $this->cc( $state, 'cc_block_why', 'no reason recorded' ) );
		}

		if ( (int) $this->cc( $state, 'cc_last_ok', 0 ) > (int) $this->cc( $before, 'cc_last_ok', 0 ) ) {
			$split = $this->cc_written( $state );

			// A create is the one outcome that cannot be undone from here, so
			// it is said out loud rather than folded into a total. On a table
			// of hand-curated rows the expected number is zero except when a
			// genuinely new camp has appeared upstream.
			if ( null !== $split['created'] && $split['created'] > 0 ) {
				WP_CLI::warning(
					sprintf(
						'%d row(s) were CREATED in %s, not updated. A created row is permanent: this plugin has no delete verb. Run "wp wcac campus-connect --preflight" to see which report IDs have no row of their own.',
						$split['created'],
						WCAC_Settings::table_for( 'campus_connect' )
					)
				);
			}

			WP_CLI::success(
				sprintf(
					'%d of %d row(s) written: %s.',
					$split['written'],
					(int) $this->cc( $state, 'cc_last_rows', 0 ),
					null === $split['created']
						? 'that run did not record how many were created'
						: sprintf( '%d created, %d updated', $split['created'], $split['updated'] )
				)
			);
			return;
		}

		WP_CLI::error(
			sprintf(
				'The Campus Connect job did not finish cleanly. Consecutive failures: %d (the sync blocks itself at 5). Last error: %s',
				(int) $this->cc( $state, 'cc_fails', 0 ),
				$this->cc( $state, 'cc_last_error', 'none recorded' )
			)
		);
	}

	/**
	 * The operator-facing name of a queue job kind.
	 *
	 * @param string $to Job code.
	 * @return string
	 */
	private function job_name( $to ) {
		$map = array(
			'wc'  => 'WordCamps',
			'mu'  => 'Meetups',
			'kid' => 'sessions/speakers/sponsors',
			'cc'  => 'Campus Connect',
		);

		if ( isset( $map[ $to ] ) ) {
			return $map[ $to ];
		}

		return '' === $to ? 'unrecognised job' : $to;
	}

	/**
	 * A "name (count)" list of the jobs a run advanced.
	 *
	 * @param array $jobs Job name => count.
	 * @return string
	 */
	private function job_summary( array $jobs ) {
		$out = array();

		foreach ( $jobs as $name => $count ) {
			$out[] = sprintf( '%s (%d)', $name, $count );
		}

		return implode( ', ', $out );
	}

	/**
	 * Is a Campus Connect job still sitting in the queue?
	 *
	 * @param WCAC_Sync $sync Sync instance.
	 * @return bool
	 */
	private function campus_queued( WCAC_Sync $sync ) {
		foreach ( $sync->queue() as $job ) {
			if ( isset( $job['to'] ) && 'cc' === $job['to'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read a state key that an older stored state may not carry yet.
	 *
	 * @param array  $state   Run state.
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	private function cc( array $state, $key, $default = '' ) {
		return array_key_exists( $key, $state ) ? $state[ $key ] : $default;
	}

	/**
	 * Split the last Campus Connect write into rows created and rows updated.
	 *
	 * cc_last_written is created PLUS updated, and those are not the same
	 * event. An update overwrites a curated cell; a create adds a row beside
	 * the curated ones that nothing here can remove, because this plugin has no
	 * delete verb. One combined number cannot tell 142 ordinary updates from
	 * 142 orphans, which is why every surface that shows the total shows the
	 * split beside it.
	 *
	 * created is null when the run state does not carry the split -- a state
	 * written before the split was recorded, or an install that has never run
	 * the job. Callers say so rather than printing a zero they cannot stand
	 * behind.
	 *
	 * @param array $state Run state.
	 * @return array written, created and updated; created and updated are null
	 *               together when the split was not recorded.
	 */
	private function cc_written( array $state ) {
		$written = (int) $this->cc( $state, 'cc_last_written', 0 );
		$created = $this->cc( $state, 'cc_last_created', null );
		$updated = $this->cc( $state, 'cc_last_updated', null );

		if ( null === $created && null === $updated ) {
			return array(
				'written' => $written,
				'created' => null,
				'updated' => null,
			);
		}

		// Either half is enough: the other is the remainder of the total.
		$created = null === $created ? max( 0, $written - (int) $updated ) : (int) $created;
		$updated = null === $updated ? max( 0, $written - $created ) : (int) $updated;

		return array(
			'written' => $written,
			'created' => $created,
			'updated' => $updated,
		);
	}

	/**
	 * The last write as a table cell: total, then the created/updated split.
	 *
	 * @param array $state Run state.
	 * @return string
	 */
	private function written_text( array $state ) {
		$split = $this->cc_written( $state );

		if ( null !== $split['created'] ) {
			return sprintf(
				'%d written (%d created, %d updated)',
				$split['written'],
				$split['created'],
				$split['updated']
			);
		}

		if ( $split['written'] < 1 ) {
			return '0 written';
		}

		return sprintf( '%d written (created/updated not recorded by that run)', $split['written'] );
	}

	/**
	 * The consecutive-failure counter, with the number it latches on.
	 *
	 * Invisible everywhere until now, which made a run that had already failed
	 * four times look exactly like a first failure.
	 *
	 * @param array $state Run state.
	 * @return string
	 */
	private function fails_text( array $state ) {
		$fails = (int) $this->cc( $state, 'cc_fails', 0 );

		if ( $fails < 1 ) {
			return '0';
		}

		return sprintf( '%d (the sync blocks itself at 5 in a row)', $fails );
	}

	/**
	 * Whether Campus Connect is blocked, and the reason either way.
	 *
	 * A block that has since been cleared is reported too. cc_block_why outlives
	 * the block, and "no" on its own hides the fact that the sync stopped itself
	 * earlier for a reason nobody has read.
	 *
	 * @param array $state   Run state.
	 * @param bool  $blocked Whether the block is still in force.
	 * @return string
	 */
	private function block_text( array $state, $blocked ) {
		$since = (int) $this->cc( $state, 'cc_block', 0 );
		$why   = (string) $this->cc( $state, 'cc_block_why', '' );
		$why   = '' === $why ? 'no reason recorded' : $why;

		if ( $blocked ) {
			// The reason is printed verbatim and the command is fenced off
			// behind a separator rather than a full stop: several of the stored
			// reasons end in a WP-CLI flag, and a period appended to one of
			// those travels with it into the shell when it is copied.
			return sprintf( 'yes, since %s: %s | Clear it with: wp wcac campus-connect --run --clear-block', $this->stamp( $since ), $why );
		}

		if ( $since > 0 ) {
			return sprintf( 'no; a block set %s was cleared: %s', $this->stamp( $since ), $why );
		}

		return 'no';
	}

	/**
	 * Whether a Campus Connect block is in force.
	 *
	 * Delegates rather than restating the rule. This used to hand-copy a
	 * twelve-hour formula that also lived in the sync class and on the settings
	 * screen; the latch has no expiry now, and one caller of the verdict cannot
	 * drift away from the others.
	 *
	 * @param array $state Run state.
	 * @return bool
	 */
	private function campus_blocked( array $state ) {
		return WCAC_Sync::cc_block_active( $state );
	}

	/**
	 * A UTC timestamp for display.
	 *
	 * @param int $when Unix timestamp.
	 * @return string
	 */
	private function stamp( $when ) {
		$when = (int) $when;

		return $when ? gmdate( 'Y-m-d H:i:s', $when ) . ' UTC' : '-';
	}

	/**
	 * The HTTP status carried by an error, if any.
	 *
	 * @param WP_Error $error Error object.
	 * @return int Zero when the error was not an HTTP response.
	 */
	private function error_status( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
	}

	/**
	 * What a failed report read most likely means.
	 *
	 * @param WP_Error $error  The failure.
	 * @param int      $status HTTP status, zero when there was none.
	 * @param array    $probe  central_identity_probe() result.
	 * @param array    $advert central_auth_advertised() result.
	 * @return array Lines to print.
	 */
	private function central_advice( WP_Error $error, $status, array $probe, array $advert ) {
		if ( 'wcac_central_no_credential' === $error->get_error_code() ) {
			return array( 'Store one with: wp wcac central-credential --user=<login>' );
		}

		if ( 'wcac_central_insecure' === $error->get_error_code() ) {
			return array( 'The Source site setting must be an https URL before a credential is attached to it.' );
		}

		if ( 401 === $status ) {
			$lines = array(
				'A 401 has three causes and they need telling apart:',
				'  1. the username or application password is wrong, or the password was revoked;',
				'  2. that account cannot create application passwords on Central at all;',
				'  3. something in front of Central strips the Authorization header before PHP sees it.',
			);

			if ( $probe['authenticated'] ) {
				$lines[] = 'The identity probe DID authenticate, so the credential itself is good -- cause 3 is unlikely and the report route is refusing for its own reasons.';
			} elseif ( empty( $advert['schemes'] ) ) {
				$lines[] = 'Central advertises no non-cookie authentication scheme, which fits causes 2 and 3. Confirm with a human that Users -> Profile -> Application Passwords renders for them.';
			} else {
				$lines[] = 'Central does advertise a non-cookie scheme, so cause 1 is the most likely: issue a fresh application password and store it again.';
			}

			return $lines;
		}

		if ( 403 === $status ) {
			return array(
				'Authenticated, but that account holds neither the campus_connect_viewer subrole nor view_wordcamp_reports on Central.',
				'Ask for campus_connect_viewer: it grants view_campus_connect_report, which opens this one report and nothing else. Subroles are granted in $wcorg_subroles on Central.',
			);
		}

		if ( 404 === $status ) {
			return array( 'The report route is not there. It may have been renamed, or not deployed to this host.' );
		}

		if ( $status >= 300 && $status < 400 ) {
			return array( 'Central redirected the request and the credential was deliberately not followed to the new host. Check the Source site setting.' );
		}

		return array( 'No HTTP status to go on. Check the Source site setting and the plugin log.' );
	}

	/**
	 * Read one line from STDIN without echoing it.
	 *
	 * Echo suppression is proven before anything is read, never assumed. A
	 * feature test cannot settle it: shell_exec() returns null both when the
	 * function is disabled and when the command ran and printed nothing, and
	 * Windows has no stty at all -- which is how the old predicate came to
	 * print "not echoed" over a terminal that was echoing. So the probe is
	 * `stty -g`: a non-empty answer proves stty exists, runs, and hands back a
	 * state to restore afterwards. When the answer is empty at a terminal this
	 * refuses to prompt, because a password typed into scrollback cannot be
	 * taken back whereas an error can be acted on.
	 *
	 * Terminal-ness itself is settled by stream_isatty(), which is core since
	 * PHP 7.2 and present on Windows, rather than by posix_isatty() alone: the
	 * old predicate collapsed to "no terminal" on a host without ext-posix --
	 * slim Docker images and Windows both -- and read the password silently
	 * with echo left on, which is the very trigger this fix exists for.
	 *
	 * When STDIN is not a terminal -- the documented `< secret.txt` form --
	 * there is no terminal to echo to, so the value is read silently with no
	 * prompt and no stty, exactly as before.
	 *
	 * @return string The value, canonicalised; empty when nothing was read.
	 */
	private function read_secret() {
		$saved = '';

		if ( function_exists( 'stream_isatty' ) ) {
			$tty = @stream_isatty( STDIN ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- warns on a closed or non-stream STDIN.
		} elseif ( function_exists( 'posix_isatty' ) ) {
			$tty = @posix_isatty( STDIN ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- warns on a closed or non-stream STDIN.
		} else {
			// Neither test is available, so whether this is a terminal cannot be
			// established. Assume it is: refusing a piped secret is recoverable
			// in one command, printing a typed one into scrollback is not.
			$tty = true;
		}

		if ( $tty && function_exists( 'shell_exec' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- probe for a working stty before a single character is typed.
			$saved = trim( (string) shell_exec( 'stty -g 2>/dev/null' ) );
		}

		if ( $tty && '' === $saved ) {
			WP_CLI::error( 'This terminal cannot suppress echo, so the application password would be printed. Pipe it in instead: wp wcac central-credential --user=<login> < secret.txt' );
		}

		if ( $tty ) {
			WP_CLI::log( 'Application password (not echoed):' );

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- the only portable way to stop a terminal echoing a secret.
			shell_exec( 'stty -echo' );
		}

		try {
			$line = fgets( STDIN );
		} finally {
			if ( $tty ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- restore the exact state the probe captured, not a blanket "stty echo".
				shell_exec( 'stty ' . escapeshellarg( $saved ) );
				WP_CLI::log( '' );
			}
		}

		if ( false === $line ) {
			return '';
		}

		return WCAC_Settings::normalise_secret( $line );
	}
}
