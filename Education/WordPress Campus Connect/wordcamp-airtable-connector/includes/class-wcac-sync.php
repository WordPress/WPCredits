<?php
/**
 * Sync orchestration: the job queue and the workers that drain it.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and drains a queue of sync jobs.
 *
 * Work is queued rather than run inline because a full backfill crawls ~1,500
 * camp sites; each cron tick drains as much of the queue as fits in a time
 * budget and then hands back control.
 */
class WCAC_Sync {

	const OPT_QUEUE = 'wcac_queue';
	const OPT_CAMPS = 'wcac_camps';
	const OPT_STATE = 'wcac_state';
	const LOCK      = 'wcac_lock';

	// The Campus Connect job's own lock. Separate from LOCK because the whole
	// point of it is to be held for the length of a cc job -- 45s of report
	// plus ~15 PATCHes -- without extending the lock the five other syncs run
	// under.
	const CC_LOCK = 'wcac_cc_running';

	// Seconds that marker survives. Long enough for a slow report and a chunk
	// loop that meets Airtable's 30s rate-limit sleep; short enough that a
	// process killed mid-run holds back Campus Connect only, and only for a
	// quarter of an hour.
	const CC_LOCK_TTL = 900;

	// Rows CREATED in one run before the job latches off. The destination is
	// hand-curated and this connector has no delete verb, so creation is the
	// one direction a later run cannot undo. Small on purpose: a real batch of
	// new events is accepted in one command, a wrong one is permanent.
	const CC_MAX_CREATES = 10;

	/**
	 * Source API client.
	 *
	 * @var WCAC_Source
	 */
	protected $source;

	/**
	 * Airtable client.
	 *
	 * @var WCAC_Airtable
	 */
	protected $airtable;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->source   = new WCAC_Source();
		$this->airtable = new WCAC_Airtable();
	}

	/* ---------------------------------------------------------------------
	 * Queue primitives
	 * ------------------------------------------------------------------ */

	/**
	 * The pending queue.
	 *
	 * @return array
	 */
	public function queue() {
		$queue = get_option( self::OPT_QUEUE, array() );

		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Replace the queue.
	 *
	 * @param array $queue Jobs.
	 * @return void
	 */
	protected function set_queue( array $queue ) {
		update_option( self::OPT_QUEUE, array_values( $queue ), false );
	}

	/**
	 * Append jobs to the queue.
	 *
	 * @param array $jobs List of job arrays.
	 * @return void
	 */
	protected function push( array $jobs ) {
		if ( empty( $jobs ) ) {
			return;
		}

		$this->set_queue( array_merge( $this->queue(), $jobs ) );
	}

	/**
	 * How many jobs are pending.
	 *
	 * @return int
	 */
	public function pending() {
		return count( $this->queue() );
	}

	/**
	 * Discard all pending work.
	 *
	 * @return void
	 */
	public function clear_queue() {
		delete_option( self::OPT_QUEUE );
	}

	/* ---------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */

	/**
	 * Read the run state.
	 *
	 * The cc_* keys belong to the Campus Connect job. They live inside
	 * wcac_state rather than an option row of their own so that uninstall.php,
	 * which already deletes wcac_state, keeps cleaning up after them.
	 *
	 * @return array
	 */
	public function state() {
		$state = get_option( self::OPT_STATE, array() );

		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'mode'               => '',
				'started'            => 0,
				'last_run'           => 0,
				'finished'           => 0,
				'created'            => 0,
				'updated'            => 0,
				'errors'             => 0,
				'last_sync'          => '',
				'cc_block'           => 0,
				'cc_block_why'       => '',
				'cc_block_kind'      => '',
				'cc_fails'           => 0,
				'cc_partial'         => 0,
				'cc_last_ok'         => 0,
				'cc_last_error'      => '',
				'cc_last_rows'       => 0,
				'cc_last_written'    => 0,
				'cc_high_rows'       => 0,
				'cc_keys'            => array(),
				'cc_series'          => 0,
				'cc_unmapped'        => array(),
				'cc_absent'          => array(),
				'cc_zeroes'          => array(),
				'cc_zeroes_set'      => 0,
				'cc_preflight_ok'    => 0,
				'cc_preflight_table' => '',
				'cc_growth_once'     => 0,
			)
		);
	}

	/**
	 * Merge values into the run state.
	 *
	 * @param array $patch Values to set.
	 * @return void
	 */
	protected function set_state( array $patch ) {
		update_option( self::OPT_STATE, array_merge( $this->state(), $patch ), false );
	}

	/**
	 * Add to the created/updated/error counters.
	 *
	 * @param int $created Created rows.
	 * @param int $updated Updated rows.
	 * @param int $errors  Errors.
	 * @return void
	 */
	protected function tally( $created = 0, $updated = 0, $errors = 0 ) {
		$state = $this->state();

		$this->set_state(
			array(
				'created' => $state['created'] + $created,
				'updated' => $state['updated'] + $updated,
				'errors'  => $state['errors'] + $errors,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Camp cache (id => Airtable record ID, site URL, start date)
	 * ------------------------------------------------------------------ */

	/**
	 * The local camp cache.
	 *
	 * @return array
	 */
	public function camps() {
		$camps = get_option( self::OPT_CAMPS, array() );

		return is_array( $camps ) ? $camps : array();
	}

	/**
	 * Merge entries into the camp cache.
	 *
	 * @param array $patch wcid => array( 'r' => recId, 's' => site, 'd' => start date ).
	 * @return void
	 */
	protected function remember_camps( array $patch ) {
		if ( empty( $patch ) ) {
			return;
		}

		update_option( self::OPT_CAMPS, $patch + $this->camps(), false );
	}

	/* ---------------------------------------------------------------------
	 * Enqueueing
	 * ------------------------------------------------------------------ */

	/**
	 * Queue a complete backfill of everything that is enabled.
	 *
	 * @return int Number of jobs queued.
	 */
	public function enqueue_full() {
		// Nothing clears the Campus Connect latch here any more. Queueing is
		// not evidence that anything was put right, and both of these paths are
		// also what an unattended `wp wcac sync` in a crontab calls on every
		// cycle -- which made cc_fails unable to hold more than 1, made the
		// five-in-a-row escalation in cc_fail() unreachable, and re-armed a
		// revoked credential once an hour for ever.
		//
		// Nor does the latch expire. A wall clock cannot tell an attended run
		// from an unattended one, so a latch it releases is released onto the
		// next cron tick, and the destination table has no delete verb to undo
		// what that tick writes. Every route back to a running sync is named in
		// clear_campus_block() and in queue_campus_now()'s $ack argument, and
		// each of them is a person acting on a reason they have read.

		$this->clear_queue();

		$jobs = array();

		if ( WCAC_Settings::get( 'sync_wordcamps' ) ) {
			$jobs[] = array( 'to' => 'wc', 'page' => 1, 'since' => null );
		}

		if ( WCAC_Settings::get( 'sync_meetups' ) ) {
			$jobs[] = array( 'to' => 'mu', 'page' => 1, 'since' => null );
		}

		// The report takes no page and no since parameter, so the full and the
		// incremental Campus Connect job are the same single job.
		if ( WCAC_Settings::campus_connect_ready() ) {
			$jobs[] = array( 'to' => 'cc' );
		}

		$this->push( $jobs );

		$this->set_state(
			array(
				'mode'     => 'full',
				'started'  => time(),
				'finished' => 0,
				'created'  => 0,
				'updated'  => 0,
				'errors'   => 0,
			)
		);

		WCAC_Logger::log( 'info', 'Full backfill queued.' );

		return count( $jobs );
	}

	/**
	 * Queue an incremental sync: whatever changed inside the lookback window,
	 * plus a refresh of the per-event data for camps in the active window.
	 *
	 * @return int Number of jobs queued.
	 */
	public function enqueue_incremental() {
		// See enqueue_full() for why the Campus Connect latch is not cleared
		// here. This is the path a crontab entry running `wp wcac sync` takes,
		// so clearing it here was clearing it on a schedule.

		$since = gmdate( 'Y-m-d\TH:i:s', time() - ( (int) WCAC_Settings::get( 'lookback_hours' ) * HOUR_IN_SECONDS ) );
		$jobs  = array();

		if ( WCAC_Settings::get( 'sync_wordcamps' ) ) {
			$jobs[] = array( 'to' => 'wc', 'page' => 1, 'since' => $since );
		}

		if ( WCAC_Settings::get( 'sync_meetups' ) ) {
			$jobs[] = array( 'to' => 'mu', 'page' => 1, 'since' => $since );
		}

		// $since is inapplicable here: the report route accepts no query
		// parameters, so the incremental job is the same full re-read as the
		// backfill one.
		if ( WCAC_Settings::campus_connect_ready() ) {
			$jobs[] = array( 'to' => 'cc' );
		}

		// Sessions and sponsors change on the camp's own site without touching
		// the camp post on Central, so changed-camps alone would miss them.
		// Refresh every camp whose event window is live: ended in the last 30
		// days, or starts within the next year.
		if ( WCAC_Settings::get( 'sync_children' ) ) {
			$floor   = gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
			$ceiling = gmdate( 'Y-m-d', time() + YEAR_IN_SECONDS );

			foreach ( $this->camps() as $wcid => $camp ) {
				if ( empty( $camp['s'] ) || empty( $camp['d'] ) ) {
					continue;
				}

				if ( $camp['d'] >= $floor && $camp['d'] <= $ceiling ) {
					$jobs[] = array( 'to' => 'kid', 'wcid' => (int) $wcid, 'site' => $camp['s'] );
				}
			}
		}

		$jobs = $this->without_queued_duplicates( $jobs );

		$this->push( $jobs );

		$this->set_state(
			array(
				'mode'     => 'incremental',
				'started'  => time(),
				'finished' => 0,
			)
		);

		WCAC_Logger::log( 'info', sprintf( 'Incremental sync queued (%d jobs, since %s).', count( $jobs ), $since ) );

		return count( $jobs );
	}

	/* ---------------------------------------------------------------------
	 * Draining
	 * ------------------------------------------------------------------ */

	/**
	 * Process jobs until the time budget runs out or the queue empties.
	 *
	 * $only confines a slice to one kind of job, and exists for
	 * `wp wcac campus-connect --run`: a command named after one table must not
	 * write to the other five. The Campus Connect job returns instantly when
	 * the preflight gate has not been stamped -- the default state on exactly
	 * the site whose operator is typing that command -- and because the budget
	 * is only ever tested BETWEEN jobs, an instant return leaves the whole of
	 * it on the clock for whatever sits behind it in the queue. With $only set
	 * the loop stops at the first job of another kind, and stops WITHOUT
	 * shifting it, so nothing is dropped, reordered or run unreported.
	 *
	 * Every other caller leaves it empty, in which case the loop behaves
	 * exactly as it always has.
	 *
	 * @param int|null $budget Seconds to spend; defaults to the configured budget.
	 * @param string   $only   Job type ('cc', 'wc', 'mu', 'kid') to confine the
	 *                         slice to; '' runs whatever is at the head.
	 * @return array array( 'processed' => int, 'remaining' => int )
	 */
	public function run_slice( $budget = null, $only = '' ) {
		if ( ! WCAC_Settings::is_configured() ) {
			return array( 'processed' => 0, 'remaining' => $this->pending() );
		}

		// A slow slice can still be running when the next tick fires.
		if ( get_transient( self::LOCK ) ) {
			return array( 'processed' => 0, 'remaining' => $this->pending() );
		}

		$budget = null === $budget ? (int) WCAC_Settings::get( 'time_budget' ) : (int) $budget;
		$only   = (string) $only;

		set_transient( self::LOCK, 1, max( 60, $budget * 3 ) );

		$start     = microtime( true );
		$processed = 0;

		try {
			while ( microtime( true ) - $start < $budget ) {
				$queue = $this->queue();

				if ( empty( $queue ) ) {
					break;
				}

				// Peek, do not shift: a job of another kind has to stay in the
				// queue, in its place, for an ordinary slice to run later.
				if ( '' !== $only ) {
					$head = reset( $queue );

					if ( ! is_array( $head ) || ! isset( $head['to'] ) || $only !== $head['to'] ) {
						break;
					}
				}

				$job = array_shift( $queue );
				$this->set_queue( $queue );

				// The budget is only ever checked BETWEEN jobs, and the loop
				// measures wall-clock time, so every second a job spends inside
				// an HTTP request counts against it -- not just the throttle
				// sleeps. The Campus Connect job is the one job that cannot be
				// split: a report fetch of up to 45s plus ~15 PATCHes, and a
				// single Airtable 429 costs 30-90s inside one request() call.
				// It can therefore overrun a 20s budget on its own. The default
				// lock is exactly 60s (max( 60, $budget * 3 ) above), so
				// without this extension the next tick would enter run_slice()
				// alongside it and both processes would do a non-atomic
				// read-modify-write on wcac_queue.
				//
				// Extended only when PHP is genuinely running from the command
				// line: `wp wcac ...`, or a crontab entry invoking wp-cron.php
				// with the CLI binary. There is no request to lose there, so a
				// long lock cannot be orphaned by anything short of a kill -9.
				//
				// wp_doing_cron() is deliberately NOT part of this test, even
				// though WCAC_Source::report_timeout() uses it to pick a report
				// timeout. It is TRUE inside wp-cron.php, and WordPress by
				// default fetches wp-cron.php over loopback HTTP -- so on a
				// default install it is an FPM request like any other and
				// request_terminate_timeout kills it without running the finally
				// below. A 300s lock stranded there returns processed => 0 from
				// every following tick and stalls every WordCamps/Meetups/
				// Sessions job for five minutes (ten, since the kill also skips
				// the WCAC_CONTINUE_HOOK scheduling below). That is the stall
				// this predicate exists to avoid, and it is worst on that path:
				// report_timeout() grants the report 45s there. php_sapi_name()
				// is what separates the two, because a real system cron runs
				// under the 'cli' SAPI and the loopback fetch does not.
				//
				// The web and loopback-cron paths therefore keep the 60s lock
				// (max( 60, $budget * 3 ) above) and accept the narrower hazard
				// instead: a cc job outliving 60s lets the next tick enter
				// run_slice() beside it. Bounded by one job, and the same lock
				// the five other syncs have always run under.
				$long_lock = ( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === php_sapi_name();

				if ( $long_lock && is_array( $job ) && isset( $job['to'] ) && 'cc' === $job['to'] ) {
					set_transient( self::LOCK, 1, max( 300, $budget * 3 ) );
				}

				$this->run_job( $job );

				if ( $long_lock && is_array( $job ) && isset( $job['to'] ) && 'cc' === $job['to'] ) {
					/*
					 * Put the lock back to the ordinary TTL. The extension exists for
					 * the Campus Connect job only; leaving it raised would make the
					 * five other syncs' jobs in this same slice inherit a 300s lock,
					 * so a kill during one of those would stall the queue five times
					 * longer than it ever did before this sync existed.
					 */
					set_transient( self::LOCK, 1, max( 60, $budget * 3 ) );
				}
				$processed++;
			}
		} finally {
			delete_transient( self::LOCK );
		}

		$remaining = $this->pending();

		$this->set_state(
			array(
				'last_run'  => time(),
				'finished'  => $remaining ? 0 : time(),
				'last_sync' => $remaining ? $this->state()['last_sync'] : WCAC_Mapper::now(),
			)
		);

		// Keep going sooner than the five-minute recurring tick while there is
		// a backlog, so a backfill is not paced by the cron interval. This uses
		// its own hook so the check is not satisfied by the recurring event.
		if ( $remaining && ! wp_next_scheduled( WCAC_CONTINUE_HOOK ) ) {
			wp_schedule_single_event( time() + 30, WCAC_CONTINUE_HOOK );
		}

		return array( 'processed' => $processed, 'remaining' => $remaining );
	}

	/**
	 * The HTTP status carried by a source error, if any.
	 *
	 * @param WP_Error $error Error object.
	 * @return int Zero when the error was not an HTTP response.
	 */
	protected function error_status( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
	}

	/* ---------------------------------------------------------------------
	 * Campus Connect: de-duplication, failure latch, per-run state
	 * ------------------------------------------------------------------ */

	/**
	 * Drop jobs that are already pending.
	 *
	 * Only 'cc' is deduped. The report is the most expensive request in the
	 * plugin -- a full server-side generation on Central plus ~15 PATCHes --
	 * and three presses of "Sync changes now" would run it three times for no
	 * new information, because every run is the same complete re-read. The
	 * wc/mu/kid jobs are left alone: their duplication is cheap and their
	 * behaviour must not change.
	 *
	 * @param array $jobs Jobs about to be pushed.
	 * @return array
	 */
	protected function without_queued_duplicates( array $jobs ) {
		if ( ! $this->campus_job_pending() ) {
			return $jobs;
		}

		$keep = array();

		foreach ( $jobs as $job ) {
			if ( is_array( $job ) && isset( $job['to'] ) && 'cc' === $job['to'] ) {
				continue;
			}

			$keep[] = $job;
		}

		return $keep;
	}

	/**
	 * Whether a Campus Connect job is running in this or another process.
	 *
	 * @return bool
	 */
	protected function cc_running() {
		return (bool) get_transient( self::CC_LOCK );
	}

	/**
	 * Whether a Campus Connect job is already waiting in the queue, or running.
	 *
	 * The running half matters because run_slice() shifts a job off the queue
	 * and persists the shortened queue BEFORE running it. Without it the queue
	 * answers "no cc job" for the whole of the most expensive run in the
	 * plugin, and the dedupe above stacks a second copy behind the first.
	 *
	 * @return bool
	 */
	protected function campus_job_pending() {
		if ( $this->cc_running() ) {
			return true;
		}

		foreach ( $this->queue() as $job ) {
			if ( is_array( $job ) && isset( $job['to'] ) && 'cc' === $job['to'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is a Campus Connect block in force?
	 *
	 * A latch, not a timeout. It is cleared by an acknowledged operator action
	 * and by nothing on a clock: the destination table has no delete verb, so a
	 * block released by a wall clock releases it onto an unattended run.
	 *
	 * Public and static because the admin screen and the CLI both need this
	 * verdict and both used to hand-copy the formula. One rule, one place.
	 *
	 * @param array $state Run state.
	 * @return bool
	 */
	public static function cc_block_active( array $state ) {
		return isset( $state['cc_block'] ) && (int) $state['cc_block'] > 0;
	}

	/**
	 * Is the Campus Connect job currently blocked?
	 *
	 * A latch, not a timeout: nothing on a clock clears it. cc_block_active()
	 * owns the rule; this is the instance-side reader, and campus_connect_run()
	 * is the one caller that changes behaviour on it.
	 *
	 * @return bool
	 */
	protected function cc_blocked() {
		return self::cc_block_active( $this->state() );
	}

	/**
	 * The stored reason, for display.
	 *
	 * Never contains a credential or the Central username: these strings are
	 * printed on the admin screen and pasted into support tickets.
	 *
	 * @return string
	 */
	protected function cc_block_reason() {
		$state = $this->state();

		return (string) $state['cc_block_why'];
	}

	/**
	 * Latch the Campus Connect job off.
	 *
	 * $kind decides which surfaces are allowed to clear it. An 'access' block is
	 * about the credential or the transport, so credential-shaped evidence -- a
	 * successful Central test, a saved password -- is a real answer to it. A
	 * 'data' block is about what the report contained, or about what a run wrote
	 * into a table with no delete verb, and nothing a credential can prove says
	 * anything about that; only an acknowledged operator action clears one. See
	 * clear_campus_block().
	 *
	 * 'data' is the default, so a call site added later that forgets the
	 * argument fails safe.
	 *
	 * @param string $why  Operator-facing reason.
	 * @param string $kind 'access' for a credential- or transport-shaped block,
	 *                     'data' for anything about the report or the writes.
	 * @return void
	 */
	protected function cc_block( $why, $kind = 'data' ) {
		$why  = (string) $why;
		$kind = 'access' === $kind ? 'access' : 'data';

		$this->set_state(
			array(
				'cc_block'      => time(),
				'cc_block_why'  => $why,
				'cc_block_kind' => $kind,
			)
		);

		WCAC_Logger::log( 'error', 'Campus Connect sync blocked. ' . $why );
	}

	/**
	 * Clear the latch and the consecutive-failure counter.
	 *
	 * Reached only from clear_campus_block(), which answers an access-shaped
	 * block with credential-shaped evidence, and from queue_campus_now() when
	 * its caller passes an operator acknowledgement. Neither enqueue path calls
	 * it, and no clock calls it: a latch set by the volume guard, the creates
	 * guard, the partial-write counter or the contract check stands until a
	 * person discards it. That is what makes cc_fails able to reach five and
	 * what makes a block outlast every later tick rather than the next one.
	 *
	 * The kind is cleared with the latch. Leaving a stale 'access' behind would
	 * let a later data-shaped block be cleared by a successful credential test.
	 *
	 * Writes nothing when there is nothing to clear, so a repeated press does
	 * not rewrite wcac_state.
	 *
	 * @return void
	 */
	protected function cc_unblock() {
		$state = $this->state();

		if ( ! $state['cc_block'] && '' === (string) $state['cc_block_why'] && '' === (string) $state['cc_block_kind'] && ! $state['cc_fails'] ) {
			return;
		}

		$this->set_state(
			array(
				'cc_block'      => 0,
				'cc_block_why'  => '',
				'cc_block_kind' => '',
				'cc_fails'      => 0,
			)
		);
	}

	/**
	 * Clear an access-shaped latch from outside the class.
	 *
	 * Hooked to wcac_central_credential_changed from the plugin bootstrap, so
	 * fixing the credential re-arms the job without a settings-form save. It is
	 * deliberately NOT called on every WCAC_Settings::save(): an operator
	 * editing an unrelated field must not put a 401ing job back into rotation,
	 * least of all when browser autofill may just have replaced the password.
	 *
	 * Credential-shaped evidence clears credential-shaped blocks and nothing
	 * else. A successful Airtable ping and Central identity probe say nothing
	 * about a volume, creates, partial-write or contract block, and those are
	 * the blocks that stand between an unattended run and rows that cannot be
	 * deleted. `wp wcac test` is a plausible nightly crontab line; without this
	 * split it would clear a creates block on evidence about something else, and
	 * an unattended clear is indistinguishable from an expiry. Only the two
	 * acknowledged surfaces clear those: the admin "Clear the block and sync
	 * Campus Connect now" button and `wp wcac campus-connect --run
	 * --clear-block`.
	 *
	 * A block written before this key existed carries no kind and is treated as
	 * data, which is the safe reading.
	 *
	 * @return bool True when a block was cleared or none was set.
	 */
	public function clear_campus_block() {
		$state = $this->state();

		if ( ! self::cc_block_active( $state ) ) {
			/*
			 * Nothing is blocked, so there is nothing to clear -- and falling
			 * through to cc_unblock() here would zero cc_fails. `wp wcac test`
			 * calls this, and a nightly test is a plausible crontab line, so
			 * that fall-through reset the counter every night and made the
			 * five-in-a-row latch unreachable again. The five-count is the
			 * whole of the wait-and-see mechanism now the latch has no expiry.
			 */
			return true;
		}

		if ( 'access' !== (string) $state['cc_block_kind'] ) {
			return false;
		}

		$this->cc_unblock();

		return true;
	}

	/**
	 * Put a Campus Connect job at the head of the queue.
	 *
	 * Unshifted rather than appended because the only callers are the explicit
	 * "Sync Campus Connect now" button and `wp wcac campus-connect --run`,
	 * which are unambiguous operator intent. The ordinary enqueue paths still
	 * append.
	 *
	 * A job that is already pending is MOVED to the head rather than left
	 * where it is. `wp wcac campus-connect --run` drains the queue a job at a
	 * time until no cc job remains, so a cc job sitting behind a backfill
	 * backlog would have that command fetch and upsert up to fifty wc/mu/kid
	 * jobs into the five other tables -- real writes, from a command named
	 * campus-connect, reported nowhere in its own output. Putting cc at the
	 * head is only half of that: the caller must also pass 'cc' as run_slice()'s
	 * $only, or a cc job that returns instantly -- the ungated preflight case --
	 * leaves budget for the next job in the queue to run behind it.
	 *
	 * Queueing is not evidence that anything was put right, so this no longer
	 * clears the latch on its own. $ack is the caller asserting that a person
	 * read the block reason and chose to discard it; only the admin button
	 * inside the red notice and `--run --clear-block` at an attended terminal
	 * pass true. The crontab line `wp wcac campus-connect --run` cannot.
	 *
	 * The blocked-and-not-acknowledged branch returns false, which collides with
	 * the "was already pending" meaning of false. Both callers therefore test
	 * the block themselves first and never reach it; the branch is defence in
	 * depth for a third caller, not the message path.
	 *
	 * @param bool $ack Discard a standing block, on operator acknowledgement.
	 * @return bool True when the job is new; false when one was already
	 *              pending and has been moved to the head of the queue.
	 */
	public function queue_campus_now( $ack = false ) {
		if ( $this->cc_blocked() ) {
			if ( ! $ack ) {
				WCAC_Logger::log( 'warn', 'Campus Connect was not queued: the sync is blocked. ' . $this->cc_block_reason() );

				return false;
			}

			WCAC_Logger::log( 'warn', 'Campus Connect block discarded by hand. It said: ' . $this->cc_block_reason() );
			/*
			 * cc_partial is reset here and NOT in cc_unblock(), because
			 * cc_unblock() is also reached by `wp wcac test`. A credential
			 * that works says nothing about ten rows Airtable keeps
			 * rejecting, so a nightly test must not hand back the
			 * five-run grace period. An acknowledged clear is a person
			 * saying they have looked, which is different.
			 */
			$this->set_state( array( 'cc_partial' => 0 ) );
			$this->cc_unblock();
		}

		$pending = false;
		$keep    = array();

		foreach ( $this->queue() as $job ) {
			if ( is_array( $job ) && isset( $job['to'] ) && 'cc' === $job['to'] ) {
				$pending = true;

				continue;
			}

			$keep[] = $job;
		}

		array_unshift( $keep, array( 'to' => 'cc' ) );
		$this->set_queue( $keep );

		return ! $pending;
	}

	/**
	 * Accept one report that has grown past the volume guard.
	 *
	 * Set by `wp wcac campus-connect --run --clear-block --accept-growth` and consumed by the
	 * next cc_volume_ok(). It is state rather than a job-payload key so that
	 * job_campus_connect() stays argument-free and run_job() keeps calling it
	 * with nothing.
	 *
	 * One "I have reviewed this run" covers all three readings of the same
	 * report: the row count here, the rows it creates in cc_creates_ok(), and
	 * the zero-write profile it leaves behind. Splitting them into three flags
	 * would mean an operator who accepted a bigger report still had to answer
	 * for the creates and the zeroes that came with it, from a second and third
	 * command that do not exist.
	 *
	 * @return void
	 */
	public function allow_campus_growth_once() {
		$this->set_state( array( 'cc_growth_once' => 1 ) );
	}

	/**
	 * Record the outcome of the CLI preflight.
	 *
	 * The table ID is stored alongside the timestamp because pointing
	 * tbl_campus_connect at a different table invalidates everything the
	 * preflight proved about the old one.
	 *
	 * @param bool   $ok    Whether the destination passed.
	 * @param string $table The table ID that was checked.
	 * @return void
	 */
	public function set_campus_preflight( $ok, $table ) {
		$this->set_state(
			array(
				'cc_preflight_ok'    => $ok ? time() : 0,
				'cc_preflight_table' => $ok ? (string) $table : '',
			)
		);
	}

	/**
	 * Record a successful (possibly partial) Campus Connect run.
	 *
	 * Patches only the keys it owns: a run that wrote 60 of 142 rows must not
	 * blank the key set, the Status gaps or the preflight stamp that the rest
	 * of the screen is reporting from.
	 *
	 * A partial run still resets cc_fails -- the report was fetched and most
	 * rows landed, so the counter that watches for a dead report must not fire
	 * -- but the failed chunks are counted separately in cc_partial. One row
	 * carrying a value Airtable rejects fails its whole ten-record chunk on
	 * every run; without a counter of its own that starvation is permanent and
	 * never escalates, because each run resets the only latch that exists.
	 *
	 * cc_last_error is owned here too. Leaving it alone let the admin's Campus
	 * Connect error row keep showing a stale string from an unrelated earlier
	 * failure long after the run that wrote it.
	 *
	 * cc_last_created is written here and is deliberately absent from
	 * state()'s defaults: the admin tells "no rows were created" apart from "a
	 * state row written before this key existed", and it can only do that while
	 * the key is genuinely missing on the older row.
	 *
	 * @param int    $written Rows created plus updated.
	 * @param int    $read    Rows the report returned.
	 * @param int    $chunks  Chunks that failed during this run.
	 * @param string $reason  Message from the last failed chunk.
	 * @param int    $created Rows created during this run.
	 * @return void
	 */
	protected function cc_ok( $written, $read, $chunks = 0, $reason = '', $created = 0 ) {
		$state  = $this->state();
		$chunks = (int) $chunks;
		$high   = (int) $state['cc_high_rows'];
		$patch  = array(
			'cc_fails'        => 0,
			'cc_block'        => 0,
			'cc_block_why'    => '',
			'cc_block_kind'   => '',
			'cc_last_ok'      => time(),
			'cc_last_rows'    => (int) $read,
			'cc_last_written' => (int) $written,
			'cc_last_created' => (int) $created,
			// Seeded on the first successful run and then left where it is.
			// Raising it to every accepted run's row count made the volume
			// guard ratchet instead of guard: from 142 its ceiling ran 213,
			// 320, 480, 720, 1080, 1620, so six accepted runs reached the
			// number cc_volume_ok() was written to refuse, with nothing blocked
			// and nothing logged on the way. The mark now moves only where an
			// operator has explicitly accepted a bigger report, in
			// cc_volume_ok().
			'cc_high_rows'    => $high > 0 ? $high : (int) $read,
		);

		if ( $chunks < 1 ) {
			$patch['cc_partial']    = 0;
			$patch['cc_last_error'] = '';

			$this->set_state( $patch );

			return;
		}

		$partial   = (int) $state['cc_partial'] + 1;
		$unwritten = max( 0, (int) $read - (int) $written );
		$why       = sprintf(
			'%d of %d rows were not written; %d chunk(s) failed. Last reason: %s',
			$unwritten,
			(int) $read,
			$chunks,
			(string) $reason
		);

		$patch['cc_partial']    = $partial;
		$patch['cc_last_error'] = $why;

		$this->set_state( $patch );

		if ( $partial >= 5 ) {
			$this->cc_block( sprintf( 'The same Campus Connect rows have failed to write on five runs in a row. %s', $why ) );
		}
	}

	/**
	 * Classify, count and record one Campus Connect failure.
	 *
	 * Every failure path goes through here. Causes that a retry cannot answer
	 * -- no credential, insecure transport, HTTP 401, HTTP 403 -- latch at
	 * once; everything else, including a route that 404s and a redirect that
	 * loses the credential, latches only after five consecutive failures, so
	 * the job neither loops forever into a 200-entry log ring nor gives up on a
	 * blip. The five-count is the whole of the wait-and-see mechanism now that
	 * the latch has no expiry, which is why the 404 and the 3xx sit on it.
	 *
	 * The partial counters the Airtable client attaches are tallied, because
	 * rows it committed before failing really are in the base.
	 *
	 * @param WP_Error $error Failure.
	 * @return void
	 */
	protected function cc_fail( WP_Error $error ) {
		$data   = $error->get_error_data();
		$data   = is_array( $data ) ? $data : array();
		$code   = (string) $error->get_error_code();
		$status = $this->error_status( $error );
		$state  = $this->state();
		$fails  = (int) $state['cc_fails'] + 1;

		$this->tally(
			isset( $data['created'] ) ? (int) $data['created'] : 0,
			isset( $data['updated'] ) ? (int) $data['updated'] : 0,
			1
		);

		$why = '';

		if ( 'wcac_central_no_credential' === $code ) {
			$why = 'No Central credential is configured, so the Campus Connect report cannot be requested.';
		} elseif ( 'wcac_central_insecure' === $code ) {
			$why = $error->get_error_message();
		} elseif ( 401 === $status ) {
			$why = 'Central rejected the credential (HTTP 401). Three things cause this: the username or application password is wrong, the password was revoked, or this host strips the Authorization header before PHP sees it. Run "wp wcac central-check" to tell them apart.';
		} elseif ( 403 === $status ) {
			$why = 'Authenticated, but that Central account does not hold view_wordcamp_reports (HTTP 403).';
		} elseif ( 404 === $status ) {
			$why = 'The Campus Connect report route is not there (HTTP 404). It may have been renamed.';
		} elseif ( $status >= 300 && $status < 400 ) {
			$why = sprintf( 'Central redirected the request (HTTP %d) and the credential was not followed to the new host. Check the Source site setting.', $status );
		}

		/*
		 * A 404 and a redirect are the two classified causes a retry can still
		 * answer, so they ride the five-in-a-row counter rather than latching on
		 * sight. Removing the twelve-hour expiry took away the only retry they
		 * ever had; the arming rule absorbs that instead. Neither writes a row on
		 * any of those five runs, so the wait costs nothing in the destination
		 * table, and cc_fails climbs visibly on the status screen throughout.
		 * What is left latching immediately -- no credential, insecure transport,
		 * 401, 403 -- is exactly the set a retry cannot fix. Of those only the
		 * first three are recorded as 'access': a 403 says the credential is
		 * fine and the account lacks the capability, so a credential test is no
		 * evidence against it.
		 *
		 * $reason still prefers the classified sentence, so the operator reads
		 * the same explanation on run 1 as before; only the latch is deferred.
		 */
		$immediate = '' !== $why && 404 !== $status && ! ( $status >= 300 && $status < 400 );

		/*
		 * Only a credential-shaped fault may be stood down by a credential
		 * test. A 403 concedes in its own reason string that the password is
		 * fine and the ACCOUNT lacks the capability; a 404 or a redirect is a
		 * route problem. `wp wcac test` proves exactly one thing -- that the
		 * credential authenticates -- so letting it clear those meant a
		 * nightly cron stood the block down on evidence about something else.
		 */
		$credential_shaped = 'wcac_central_no_credential' === $code
			|| 'wcac_central_insecure' === $code
			|| 401 === $status;
		$reason    = '' !== $why ? $why : $error->get_error_message();

		$this->set_state(
			array(
				'cc_fails'      => $fails,
				'cc_last_error' => $reason,
			)
		);

		// Status code and route only. The Central username is a third party's
		// WordPress.org login and log lines are pasted into public issues.
		WCAC_Logger::log(
			'error',
			sprintf(
				'Campus Connect sync failed at wordcamp-reports/v1/campus-connect-details (%s, HTTP %d): %s',
				$code,
				$status,
				$reason
			)
		);

		if ( $immediate ) {
			$this->cc_block( $why, $credential_shaped ? 'access' : 'data' );

			return;
		}

		if ( $fails >= 5 ) {
			/*
			 * cc_fail() funnels far more than credential faults -- a failed
			 * Airtable upsert, an empty report, a lost ID key all arrive here.
			 * Hard-coding 'access' let `wp wcac test` stand those down on
			 * evidence about the credential, which says nothing about them.
			 * $why is non-empty only for the classified access-shaped causes.
			 */
			$this->cc_block( sprintf( 'Campus Connect has failed five times in a row and will not run again until that is cleared. Last reason: %s', $reason ), $credential_shaped ? 'access' : 'data' );
		}
	}

	/**
	 * Refuse a report that has grown implausibly since the last good run.
	 *
	 * Omit-when-empty and the absence of a delete verb make SHRINKAGE harmless.
	 * Growth is not: campus-connect-details is outside this plugin's control,
	 * and a query regression there that starts returning every WordCamp would
	 * CREATE ~1,500 rows in the destination table. There is no delete verb, so
	 * that is a manual cleanup of every one of them. One-sided guard: it costs
	 * nothing on the way down.
	 *
	 * @param int  $incoming Row count in this run's report.
	 * @param bool $accepted Operator passed --accept-growth for this run.
	 * @return bool True to proceed.
	 */
	protected function cc_volume_ok( $incoming, $accepted = false ) {
		$incoming = (int) $incoming;
		$state    = $this->state();
		$high     = (int) $state['cc_high_rows'];

		if ( $accepted ) {
			// The flag itself is consumed at run entry, not here: this guard
			// sits behind six early returns, and clearing it only on arrival
			// left it armed and invisible whenever one of those fired first.
			$this->set_state( array( 'cc_high_rows' => $incoming ) );

			return true;
		}

		if ( $high < 1 ) {
			return true;
		}

		$ceiling = max( $high + 50, (int) ceil( $high * 1.5 ) );

		if ( $incoming <= $ceiling ) {
			return true;
		}

		WCAC_Logger::log(
			'error',
			sprintf(
				'Campus Connect report returned %d rows against a high-water mark of %d (ceiling %d). Nothing was written.',
				$incoming,
				$high,
				$ceiling
			)
		);

		$this->cc_block(
			sprintf(
				'The Campus Connect report jumped from %d rows to %d in one run. Check the report on Central, then accept it with: wp wcac campus-connect --run --clear-block --accept-growth',
				$high,
				$incoming
			)
		);

		return false;
	}

	/**
	 * Alarm on record CREATION, which is the direction nothing can undo.
	 *
	 * performUpsert matches on WordCamp ID: a match updates, a miss CREATES.
	 * The sync never reads the destination back, so it cannot know in advance
	 * which side of that a row falls on, and the table holds 142 hand-curated
	 * rows this connector has no verb to delete. An edited WordCamp ID cell in
	 * Airtable, or Central changing what ID means, therefore puts orphans
	 * beside the curated rows with every counter reading normal:
	 * cc_volume_ok() measures the INCOMING row count, which neither of those
	 * changes, and the run's own log line folds creates into an info line about
	 * totals.
	 *
	 * The creates have happened by the time this runs -- there is no earlier
	 * moment at which they are knowable without reading the table back -- so
	 * what it can do is say so in its own line, and stop the next run from
	 * compounding it. The latch it sets sits on no enqueue path and on no clock,
	 * so it stops every later run and not merely the next one: a weekly job that
	 * created 142 orphans once does not create 142 more seven days on.
	 *
	 * @param int  $created  Rows created during this run.
	 * @param bool $accepted Whether the operator accepted this run in advance.
	 * @return void
	 */
	protected function cc_creates_ok( $created, $accepted ) {
		$created = (int) $created;

		if ( $created < 1 ) {
			return;
		}

		if ( $accepted || $created < self::CC_MAX_CREATES ) {
			WCAC_Logger::log(
				'warn',
				sprintf(
					'Campus Connect created %d row(s) that the destination table did not already hold. Check them against the report: a created row is a new row, and this connector has no delete verb.',
					$created
				)
			);

			return;
		}

		$this->cc_block(
			sprintf(
				'Campus Connect created %d rows in one run, against a limit of %d. A merge-key miss creates rather than updates, so those rows are new and permanent. Check the WordCamp ID column in Airtable and the ID field in the report on Central before running it again. If the creates are right, accept them with: wp wcac campus-connect --run --clear-block --accept-growth',
				$created,
				self::CC_MAX_CREATES
			)
		);
	}

	/**
	 * Dispatch a single job.
	 *
	 * @param array $job Job array.
	 * @return void
	 */
	protected function run_job( $job ) {
		if ( ! is_array( $job ) || empty( $job['to'] ) ) {
			return;
		}

		switch ( $job['to'] ) {
			case 'wc':
				$this->job_wordcamps( $job );
				break;
			case 'mu':
				$this->job_meetups( $job );
				break;
			case 'kid':
				$this->job_event_data( $job );
				break;
			case 'cc':
				$this->job_campus_connect();
				break;
		}
	}

	/**
	 * Sync one page of WordCamps, fanning out pages and child jobs from page 1.
	 *
	 * @param array $job Job array.
	 * @return void
	 */
	protected function job_wordcamps( array $job ) {
		$page   = max( 1, (int) $job['page'] );
		$since  = isset( $job['since'] ) ? $job['since'] : null;
		$result = $this->source->wordcamps( $page, $since );

		if ( is_wp_error( $result ) ) {
			// WP answers a request past the last page with 400
			// (rest_post_invalid_page_number). That is the normal way a
			// paginated crawl ends, not a failure worth counting.
			if ( 400 === $this->error_status( $result ) ) {
				return;
			}

			$this->tally( 0, 0, 1 );
			WCAC_Logger::log( 'error', 'WordCamps fetch failed: ' . $result->get_error_message(), array( 'page' => $page ) );

			return;
		}

		$posts = is_array( $result['body'] ) ? $result['body'] : array();

		if ( 1 === $page && $result['total_pages'] > 1 ) {
			$more = array();

			for ( $p = 2; $p <= $result['total_pages']; $p++ ) {
				$more[] = array( 'to' => 'wc', 'page' => $p, 'since' => $since );
			}

			$this->push( $more );
		}

		if ( empty( $posts ) ) {
			return;
		}

		$rows = array_map( array( 'WCAC_Mapper', 'wordcamp' ), $posts );
		$sent = $this->airtable->upsert( WCAC_Settings::table_for( 'wordcamps' ), $rows, 'WordCamp ID' );

		if ( is_wp_error( $sent ) ) {
			$this->tally( 0, 0, 1 );
			WCAC_Logger::log( 'error', 'WordCamps upsert failed: ' . $sent->get_error_message(), array( 'page' => $page ) );

			return;
		}

		$this->tally( $sent['created'], $sent['updated'] );

		// Remember each camp's Airtable record ID, site and start date, so the
		// child jobs can link rows without re-reading the table.
		$cache = array();
		$kids  = array();

		foreach ( $rows as $row ) {
			$wcid = (int) $row['WordCamp ID'];
			$site = isset( $row['Site URL'] ) ? $row['Site URL'] : '';

			$cache[ $wcid ] = array(
				'r' => isset( $sent['ids'][ (string) $wcid ] ) ? $sent['ids'][ (string) $wcid ] : '',
				's' => $site,
				'd' => isset( $row['Start Date'] ) ? (string) $row['Start Date'] : '',
			);

			if ( WCAC_Settings::get( 'sync_children' ) && $site ) {
				$kids[] = array( 'to' => 'kid', 'wcid' => $wcid, 'site' => $site );
			}
		}

		$this->remember_camps( $cache );
		$this->push( $kids );
	}

	/**
	 * Sync one page of Meetups.
	 *
	 * @param array $job Job array.
	 * @return void
	 */
	protected function job_meetups( array $job ) {
		$page   = max( 1, (int) $job['page'] );
		$since  = isset( $job['since'] ) ? $job['since'] : null;
		$result = $this->source->meetups( $page, $since );

		if ( is_wp_error( $result ) ) {
			if ( 400 === $this->error_status( $result ) ) {
				return;
			}

			$this->tally( 0, 0, 1 );
			WCAC_Logger::log( 'error', 'Meetups fetch failed: ' . $result->get_error_message(), array( 'page' => $page ) );

			return;
		}

		$posts = is_array( $result['body'] ) ? $result['body'] : array();

		if ( 1 === $page && $result['total_pages'] > 1 ) {
			$more = array();

			for ( $p = 2; $p <= $result['total_pages']; $p++ ) {
				$more[] = array( 'to' => 'mu', 'page' => $p, 'since' => $since );
			}

			$this->push( $more );
		}

		if ( empty( $posts ) ) {
			return;
		}

		$rows = array_map( array( 'WCAC_Mapper', 'meetup' ), $posts );
		$sent = $this->airtable->upsert( WCAC_Settings::table_for( 'meetups' ), $rows, 'Meetup ID' );

		if ( is_wp_error( $sent ) ) {
			$this->tally( 0, 0, 1 );
			WCAC_Logger::log( 'error', 'Meetups upsert failed: ' . $sent->get_error_message(), array( 'page' => $page ) );

			return;
		}

		$this->tally( $sent['created'], $sent['updated'] );
	}

	/**
	 * Run the Campus Connect job, and never two of them at once.
	 *
	 * The slice lock is max( 60, budget * 3 ), so 60s by default on the web and
	 * loopback-cron paths, and a cc job is ordinarily longer than that: 45s of
	 * report plus ~15 PATCHes, and one Airtable 429 adds a 30s sleep inside a
	 * single request. run_slice() also shifts the job off the queue and
	 * persists the shortened queue BEFORE running it, so nothing in the queue
	 * says a run is in progress. A cron tick starting a cc job at t=0 and an
	 * hourly `wp wcac sync` at t=61 therefore put two Campus Connect upserts
	 * against one table in the air together -- and two performUpserts carrying
	 * a merge value that is not yet in the base both miss and both create. That
	 * is a permanent duplicate pair on a table with no delete verb, which then
	 * fails every future chunk that contains that ID.
	 *
	 * This marker is the cc job's own lock, held for the job's real duration on
	 * every SAPI. Extending the slice lock instead would hand the same 300s to
	 * the five other syncs' jobs, and strand it on a killed FPM request where
	 * the finally never runs -- a five-minute stall for wc/mu/kid that they
	 * never had before this sync existed. A lock of its own costs them nothing:
	 * the worst a stranded marker can do is skip Campus Connect for a quarter
	 * of an hour.
	 *
	 * It is a bound, not a mutex. Two processes reaching the check inside the
	 * same millisecond would both pass it, and a job that outruns CC_LOCK_TTL
	 * loses it; both of those leave the plugin exactly where it stood before.
	 * What the marker removes is the ordinary sequential window, which is the
	 * one that is minutes wide.
	 *
	 * @return void
	 */
	protected function job_campus_connect() {
		if ( $this->cc_running() ) {
			WCAC_Logger::log( 'warn', 'Campus Connect job skipped: another Campus Connect run is still going. It will be picked up by a later sync.' );

			return;
		}

		set_transient( self::CC_LOCK, time(), self::CC_LOCK_TTL );

		try {
			$this->campus_connect_run();
		} finally {
			delete_transient( self::CC_LOCK );
		}
	}

	/**
	 * Sync the Campus Connect Events table from Central's authenticated report.
	 *
	 * Unlike every other job here this one is INDIVISIBLE: the route takes no
	 * page and no since parameter, emits no x-wp-totalpages, and returns every
	 * event on every call. A realistic run is the report fetch (up to 45s under
	 * cron or WP-CLI) plus ~15 PATCHes at 300-500ms each. run_slice() checks its
	 * budget only between jobs and measures wall-clock time, so this job's
	 * requests -- not merely the Airtable throttle sleeps -- count against the
	 * budget, and this is the one job that can consume a whole 20s tick by
	 * itself and overrun it. run_slice() extends the concurrency lock for it
	 * under WP-CLI, and job_campus_connect() holds a lock of its own, on every
	 * SAPI, for as long as this runs.
	 *
	 * run_slice() also shifts the job off the queue and PERSISTS the shortened
	 * queue before running it, so a fatal mid-job loses the job outright, with
	 * perhaps 60 of 142 rows written and no log line. That is tolerable ONLY
	 * because this job is a full idempotent re-read: re-running it repairs a
	 * half-finished run exactly. Do not add a since parameter or any resume
	 * state -- it would break that property, and nothing here retries.
	 *
	 * Note also that no cron path re-enqueues this job. The cron tick only
	 * drains; enqueue_incremental() is reached from the admin button and
	 * `wp wcac sync` only. A lost job stays lost until a human acts.
	 *
	 * Three things this must NOT copy from its neighbours: the 400-means-past-
	 * the-last-page early return (a wp/v2 pagination convention that would
	 * swallow a genuine bad request here), the page fan-out (total_pages is 0
	 * for a route outside wp/v2), and sync_collection() (which downgrades a
	 * source error to a warning and returns without tallying, so a 401 would
	 * read as a clean run that wrote nothing).
	 *
	 * @return void
	 */
	protected function campus_connect_run() {
		if ( $this->cc_blocked() ) {
			// Gating only the enqueue is not enough: jobs already sitting in
			// the queue when the latch arms would still fire, and three presses
			// of "Sync changes now" would mean three more rejected Basic auth
			// attempts against a third party's server.
			WCAC_Logger::log( 'warn', 'Campus Connect job skipped: the sync is blocked. ' . $this->cc_block_reason() );

			return;
		}

		$table = WCAC_Settings::table_for( 'campus_connect' );

		/*
		 * Consume --accept-growth here, before any early return can strand it.
		 * The flag waives three separate guards (volume, creates and the zero
		 * profile), and it used to survive a run that never reached them -- a
		 * maintenance page, an empty report, a source error. It would then sit
		 * armed and invisible, since no surface renders it, and silence all
		 * three guards on some later unattended run the operator never saw.
		 * Reading it once per run means it can only ever waive the run the
		 * operator was actually looking at.
		 */
		$accepted = ! empty( $this->state()['cc_growth_once'] );

		if ( $accepted ) {
			$this->set_state( array( 'cc_growth_once' => 0 ) );
		}

		$state = $this->state();

		// A blank or duplicated merge value in the destination is invisible to
		// the write path and unrecoverable once it has created duplicate rows,
		// so the documented preflight is an enforced gate, not a checklist item.
		if ( empty( $state['cc_preflight_ok'] ) || (string) $state['cc_preflight_table'] !== $table ) {
			// Not tallied as an error. This fires on every cron tick and every
			// press of "Sync changes now", so counting it climbed the cumulative
			// Errors figure once per tick for what is a configuration gate, with
			// nothing anywhere naming the cause. The reason goes into
			// cc_last_error, which the admin's Campus Connect error row already
			// renders, so the skip explains itself without a new surface.
			$this->set_state(
				array(
					'cc_last_error' => 'This table has not passed preflight, so nothing was written. Run: wp wcac campus-connect --preflight',
				)
			);
			WCAC_Logger::log( 'error', 'Campus Connect job skipped: this table has not passed preflight. Run: wp wcac campus-connect --preflight' );

			return;
		}

		$rows = $this->source->campus_connect();

		if ( is_wp_error( $rows ) ) {
			$this->cc_fail( $rows );

			return;
		}

		// Zero rows is never a legitimate answer for a table with 142 curated
		// members, and it is what a maintenance page or a broken report query
		// looks like once the envelope check has passed. Routed through
		// cc_fail() so it latches rather than repeating forever.
		if ( empty( $rows ) ) {
			$this->cc_fail( new WP_Error( 'wcac_central_empty', 'Campus Connect report returned no rows; nothing written.' ) );

			return;
		}

		$keys = WCAC_Mapper::campus_key_report( $rows );

		if ( in_array( 'ID', $keys['missing'], true ) ) {
			$this->cc_fail( new WP_Error( 'wcac_central_contract', 'The report no longer contains an ID key; every row would be dropped.' ) );

			return;
		}

		if ( ! empty( $keys['missing'] ) ) {
			WCAC_Logger::log(
				'warn',
				sprintf(
					'Campus Connect report is missing %d documented key(s): %s. Those columns will not update.',
					count( $keys['missing'] ),
					implode( ', ', $keys['missing'] )
				)
			);
		}

		if ( ! empty( $keys['unexpected'] ) ) {
			WCAC_Logger::log(
				'info',
				sprintf(
					'Campus Connect report carries %d key(s) this plugin does not map: %s.',
					count( $keys['unexpected'] ),
					implode( ', ', $keys['unexpected'] )
				)
			);
		}

		// A rename is otherwise a permanent, completely silent no-op -- City
		// and Country would simply stop updating and the run would still report
		// "142 updated". Comparing against the stored set is also the only
		// thing that can see the 13-vs-14 key capability difference in BOTH
		// directions.
		$known = is_array( $state['cc_keys'] ) ? $state['cc_keys'] : array();

		if ( ! empty( $known ) && $known !== $keys['observed'] ) {
			$gone  = array_values( array_diff( $known, $keys['observed'] ) );
			$fresh = array_values( array_diff( $keys['observed'], $known ) );

			WCAC_Logger::log(
				'warn',
				sprintf(
					'Campus Connect report key set changed since the last run. Gone: %s. New: %s.',
					empty( $gone ) ? 'none' : implode( ', ', $gone ),
					empty( $fresh ) ? 'none' : implode( ', ', $fresh )
				)
			);
		}

		$allowed  = WCAC_Mapper::campus_allowed_status();
		$records  = array();
		$dupes    = array();
		$unmapped = array();
		$absent   = array();
		$zeroing  = array();
		$skipped  = 0;
		$blank    = 0;
		$dates    = 0;
		$ints     = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				++$skipped;

				continue;
			}

			$mapped = WCAC_Mapper::campus_event( $row, $allowed );
			$dates += (int) $mapped['notes']['date_unparsed'];
			$ints  += (int) $mapped['notes']['int_unparsed'];
			$slug   = '' === $mapped['slug'] ? '(blank)' : $mapped['slug'];

			if ( 'unmapped' === $mapped['status'] ) {
				$unmapped[ $slug ] = isset( $unmapped[ $slug ] ) ? $unmapped[ $slug ] + 1 : 1;
			} elseif ( 'absent' === $mapped['status'] ) {
				$absent[ $slug ] = isset( $absent[ $slug ] ) ? $absent[ $slug ] + 1 : 1;
			} elseif ( 'missing' === $mapped['status'] ) {
				++$blank;
			}

			if ( $mapped['id'] < 1 ) {
				++$skipped;

				continue;
			}

			// De-duplicate on the merge value before chunking. Two records
			// matching the same existing row inside one request fail the whole
			// ten-record chunk; in different chunks the second silently
			// overwrites the first, order-dependently.
			if ( isset( $records[ $mapped['id'] ] ) ) {
				$dupes[] = $mapped['id'];
			}

			// Count what this row would zero. campus_int() returns int 0 and
			// campus_text() returns the string "0", and both are emitted
			// deliberately -- a wrong non-zero count has to be correctable back
			// down. Counting is the cheap half of that bargain.
			//
			// Collected per merge value, in step with $records, for two
			// reasons: a duplicated WordCamp ID must count once for the row
			// that survives rather than once per row read, and the chunks that
			// fail later have to be subtracted before any of this can be
			// described as written. An empty list is stored too, so a later
			// duplicate carrying a real count clears an earlier zero one.
			$zeroed = array();

			foreach ( $mapped['fields'] as $field => $value ) {
				if ( 0 === $value || '0' === $value ) {
					$zeroed[] = $field;
				}
			}

			$zeroing[ $mapped['id'] ] = $zeroed;
			$records[ $mapped['id'] ] = $mapped['fields'];
		}

		$this->set_state(
			array(
				'cc_unmapped' => $unmapped,
				'cc_absent'   => $absent,
			)
		);

		if ( ! empty( $dupes ) ) {
			WCAC_Logger::log(
				'warn',
				sprintf(
					'Campus Connect report carried %d duplicate WordCamp ID(s); the last row for each won: %s.',
					count( $dupes ),
					implode( ', ', array_unique( $dupes ) )
				)
			);
		}

		// A Campus Connect row with no post ID is not an ordinary condition.
		if ( $skipped > 0 ) {
			$this->tally( 0, 0, 1 );
			WCAC_Logger::log( 'error', sprintf( '%d Campus Connect row(s) had no usable ID and were dropped.', $skipped ) );
		}

		if ( empty( $records ) ) {
			$this->cc_block( 'Every Campus Connect row was dropped for want of an ID -- the report contract has changed.' );

			return;
		}

		/*
		 * One acceptance covers the row count, the rows this run creates and
		 * the zero-write profile it leaves behind, because all three are
		 * readings of the same report. $accepted was consumed at the top of
		 * this run; see campus_connect_run().
		 */
		if ( ! $this->cc_volume_ok( count( $rows ), $accepted ) ) {
			return;
		}

		// Persisted here rather than at the point of comparison: a run refused
		// by the volume guard must not consume the key-drift baseline, or the
		// next run would compare against the refused run's key set and stay
		// silent about a rename that arrived in the same regression.
		$this->set_state(
			array(
				'cc_keys'   => $keys['observed'],
				'cc_series' => $keys['series'] ? 1 : 0,
			)
		);

		$sent = $this->airtable->upsert(
			$table,
			array_values( $records ),
			'WordCamp ID',
			array(
				// typecast stays TRUE, as on all five other tables. With it
				// false a single drifted singleSelect option is a 422, and
				// upsert() would abandon every LATER chunk -- on a
				// server-stable feed that starves the same rows on every future
				// run, forever. The safety property lives in
				// WCAC_Mapper::campus_allowed_status() instead, which is
				// per-label and cannot truncate a run.
				'typecast'          => true,
				'continue_on_error' => true,
			)
		);

		if ( is_wp_error( $sent ) ) {
			$this->cc_fail( $sent );

			return;
		}

		$created = (int) $sent['created'];
		$updated = (int) $sent['updated'];
		$written = $created + $updated;
		$failed  = isset( $sent['errors'] ) && is_array( $sent['errors'] ) ? $sent['errors'] : array();
		$shown   = 0;

		foreach ( $failed as $entry ) {
			if ( $shown >= 5 ) {
				WCAC_Logger::log( 'error', sprintf( '%d further Campus Connect chunk(s) failed for the same kind of reason.', count( $failed ) - $shown ) );

				break;
			}

			WCAC_Logger::log(
				'error',
				sprintf(
					'Campus Connect chunk %d failed (%s); WordCamp IDs %s were not written.',
					(int) $entry['chunk'],
					(string) $entry['message'],
					implode( ', ', (array) $entry['keys'] )
				)
			);

			++$shown;
		}

		// Every chunk failed. That is a run failure, not a partial success:
		// treating it as one would reset the consecutive-failure counter and a
		// persistent 422 could then never reach the latch.
		if ( 0 === $written && ! empty( $failed ) ) {
			$last = end( $failed );

			$this->cc_fail(
				new WP_Error(
					'wcac_airtable_all_chunks',
					sprintf( 'Every Campus Connect chunk failed; nothing was written. Last reason: %s', isset( $last['message'] ) ? $last['message'] : '' )
				)
			);

			return;
		}

		$this->tally( $created, $updated, count( $failed ) );

		// One aggregated line per category, never one per row: WCAC_Logger::log()
		// is a full read-modify-write of a 200-entry option on every call, so
		// 142 per-row lines would be 142 option rewrites and would flush every
		// other entry out of the ring.
		WCAC_Logger::log(
			'info',
			sprintf(
				'Campus Connect: %d rows read, %d written (%d created, %d updated).',
				count( $rows ),
				$written,
				$created,
				$updated
			)
		);

		// The zero write is the one unbounded overwrite path left: 0 is not
		// "unparsed", not a Status gap and not a volume change, so a report
		// regression that starts sending 0 for every row would blank every
		// curated count and no existing counter would move. Naming the fields
		// puts it in the log the moment it happens rather than in a downstream
		// dashboard a week later.
		//
		// Two things decide whether that line is worth reading. Failed chunks
		// are subtracted first, so "wrote" describes what Airtable accepted
		// and not what the payload asked for. And the profile is compared with
		// the previous run's, because a zero is the ordinary state of a
		// boolean column -- Series Event is 0 on every camp that is not part
		// of a series, which is nearly all of them -- so a count that does not
		// move cannot be the alarm. A field that is new, or above where it was,
		// raises the line to warn; that is the shape the 142-row regression
		// this counter exists for actually has, and an unchanging one is not
		// allowed to bury it.
		$unwritten = array();

		foreach ( $failed as $entry ) {
			$keys = isset( $entry['keys'] ) ? (array) $entry['keys'] : array();

			foreach ( $keys as $key ) {
				$unwritten[ (string) $key ] = true;
			}
		}

		$zeroes = array();

		foreach ( $zeroing as $id => $fields ) {
			if ( isset( $unwritten[ (string) $id ] ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$zeroes[ $field ] = isset( $zeroes[ $field ] ) ? $zeroes[ $field ] + 1 : 1;
			}
		}

		// cc_zeroes_set separates "no baseline yet" from "a baseline of no
		// zeroes at all". Without it the FIRST healthy run of every install
		// compared against array(), found every field above 0 and warned --
		// which is how an operator learns to read this line as noise.
		$seeded = ! empty( $state['cc_zeroes_set'] );
		$was    = is_array( $state['cc_zeroes'] ) ? $state['cc_zeroes'] : array();
		$risen  = array();
		$parts  = array();

		foreach ( $zeroes as $field => $count ) {
			$before  = isset( $was[ $field ] ) ? (int) $was[ $field ] : 0;
			$parts[] = sprintf( '%s (%d)', $field, $count );

			if ( $seeded && $count > $before ) {
				$risen[] = sprintf( '%s %d -> %d', $field, $before, $count );
			}
		}

		// Written on this path only -- after the volume guard and after the
		// upsert -- so a refused or failed run never becomes the baseline the
		// next one is measured against. And NOT written by a run that raised
		// the alarm, unless the operator accepted that run: writing it there
		// made the alarm one-shot, because the run that warned became the
		// baseline the next run was measured against. A report regression
		// sending 0 for all 142 rows warned once and then logged info for ever
		// while it went on overwriting. Holding the last accepted profile is
		// what makes the warning repeat for as long as the condition does.
		if ( ! $seeded || empty( $risen ) || $accepted ) {
			$this->set_state(
				array(
					'cc_zeroes'     => $zeroes,
					'cc_zeroes_set' => 1,
				)
			);
		}

		if ( ! $seeded ) {
			if ( ! empty( $zeroes ) ) {
				WCAC_Logger::log(
					'info',
					sprintf(
						'Campus Connect wrote a zero or "0" to %d cell(s). There was no previous profile to compare against, so this run becomes the baseline: %s.',
						array_sum( $zeroes ),
						implode( ', ', $parts )
					)
				);
			}
		} elseif ( ! empty( $risen ) ) {
			WCAC_Logger::log(
				'warn',
				sprintf(
					'Campus Connect zero writes are up on the last accepted run: %s. It wrote a zero or "0" to %d cell(s) in all: %s.%s',
					implode( ', ', $risen ),
					array_sum( $zeroes ),
					implode( ', ', $parts ),
					$accepted ? '' : ' This repeats every run until the counts come back down, or until a run is accepted with: wp wcac campus-connect --run --clear-block --accept-growth'
				)
			);
		} elseif ( ! empty( $zeroes ) ) {
			WCAC_Logger::log(
				'info',
				sprintf(
					'Campus Connect wrote a zero or "0" to %d cell(s), no field above the last accepted run: %s.',
					array_sum( $zeroes ),
					implode( ', ', $parts )
				)
			);
		}

		if ( ! empty( $unmapped ) ) {
			WCAC_Logger::log(
				'warn',
				sprintf(
					'Campus Connect: %d row(s) carried a Status this plugin does not know: %s. Status was left untouched on those rows.',
					array_sum( $unmapped ),
					implode( ', ', array_keys( $unmapped ) )
				)
			);
		}

		if ( ! empty( $absent ) ) {
			WCAC_Logger::log(
				'warn',
				sprintf(
					'Campus Connect: %d row(s) map to a Status option that does not exist in Airtable: %s. Add the option, then list the label in the settings screen.',
					array_sum( $absent ),
					implode( ', ', array_keys( $absent ) )
				)
			);
		}

		if ( $blank > 0 ) {
			WCAC_Logger::log( 'info', sprintf( 'Campus Connect: %d row(s) had no Status at all; those cells were left untouched.', $blank ) );
		}

		if ( $dates > 0 ) {
			WCAC_Logger::log( 'warn', sprintf( 'Campus Connect: %d date value(s) could not be parsed and were left untouched.', $dates ) );
		}

		if ( $ints > 0 ) {
			WCAC_Logger::log( 'warn', sprintf( 'Campus Connect: %d numeric value(s) were not numeric and were left untouched.', $ints ) );
		}

		// A run that wrote most rows but lost a chunk is not a clean run.
		// cc_ok() needs to know that, or the same ten rows can starve on
		// every run while the consecutive-failure counter is reset each time.
		$last_chunk = empty( $failed ) ? array() : end( $failed );

		$this->cc_ok(
			$written,
			count( $rows ),
			count( $failed ),
			isset( $last_chunk['message'] ) ? (string) $last_chunk['message'] : '',
			$created
		);

		// After cc_ok() and never before it: cc_ok() clears cc_block, so a
		// latch set here first would be wiped by the run that set it.
		$this->cc_creates_ok( $created, $accepted );
	}

	/**
	 * Sync sessions, speakers and sponsors for one camp.
	 *
	 * Each collection is handled independently: an archived camp that serves
	 * sessions but 404s on sponsors should still contribute its sessions.
	 *
	 * @param array $job Job array.
	 * @return void
	 */
	protected function job_event_data( array $job ) {
		$wcid = (int) $job['wcid'];
		$site = (string) $job['site'];

		if ( ! $wcid || ! $site ) {
			return;
		}

		$camps    = $this->camps();
		$camp_rec = isset( $camps[ $wcid ]['r'] ) && $camps[ $wcid ]['r'] ? $camps[ $wcid ]['r'] : null;

		$this->sync_collection(
			$site,
			'sessions',
			'Session Key',
			function ( $post ) use ( $wcid, $camp_rec, $site ) {
				return WCAC_Mapper::session( $post, $wcid, $camp_rec, $this->source->term_map( $site, 'session_track' ) );
			}
		);

		$this->sync_collection(
			$site,
			'speakers',
			'Speaker Key',
			function ( $post ) use ( $wcid, $camp_rec ) {
				return WCAC_Mapper::speaker( $post, $wcid, $camp_rec );
			}
		);

		$this->sync_collection(
			$site,
			'sponsors',
			'Sponsor Key',
			function ( $post ) use ( $wcid, $camp_rec, $site ) {
				return WCAC_Mapper::sponsor( $post, $wcid, $camp_rec, $this->source->term_map( $site, 'sponsor_level' ) );
			}
		);
	}

	/**
	 * Fetch one per-event collection from a camp site and upsert it.
	 *
	 * @param string   $site        Camp site root.
	 * @param string   $collection  sessions|speakers|sponsors.
	 * @param string   $merge_field Airtable merge key.
	 * @param callable $map         Maps one post to an Airtable field array.
	 * @return void
	 */
	protected function sync_collection( $site, $collection, $merge_field, callable $map ) {
		$posts = $this->source->event_posts( $site, $collection );

		if ( is_wp_error( $posts ) ) {
			// Camps with no schedule, archived camps and non-camp event sites
			// all 404 here. That is ordinary, so it is a warning, not an error.
			WCAC_Logger::log(
				'warn',
				sprintf( 'No %s at %s (%s)', $collection, $site, $posts->get_error_message() )
			);

			return;
		}

		if ( empty( $posts ) ) {
			return;
		}

		$rows = array_map( $map, $posts );
		$sent = $this->airtable->upsert( WCAC_Settings::table_for( $collection ), $rows, $merge_field );

		if ( is_wp_error( $sent ) ) {
			$this->tally( 0, 0, 1 );
			WCAC_Logger::log(
				'error',
				sprintf( '%s upsert failed for %s: %s', ucfirst( $collection ), $site, $sent->get_error_message() )
			);

			return;
		}

		$this->tally( $sent['created'], $sent['updated'] );
	}
}
