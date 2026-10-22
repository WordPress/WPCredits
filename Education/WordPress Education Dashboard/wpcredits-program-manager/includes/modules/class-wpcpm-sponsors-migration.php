<?php
/**
 * The one-time move of the sponsors index from its option onto the site's own records (1.111.0).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the index the sponsors sync held in an option into one private post per sponsor, under
 * the same keys, and says what it did (design of 23 September 2026, decision 7).
 *
 * What a run does, in order: every well-formed row of the held index that the store does not
 * hold yet becomes a post, with its manager mapped from the held Team Members option by address
 * to an account that holds the program capability (the design assigns a sponsor to a program
 * manager and to nobody else); every sponsor whose row carried offer text and which has no offer
 * yet gets its primary offer seeded in draft, as the approval does; each program manager with an
 * account and no booking link gets the one the team held (decision 5); then, when every row is a
 * post and every draft offer that was due exists, the held index, the team, everything the
 * sponsors sync kept and the marks the agreement left for it go.
 *
 * A row the store already holds is never written again. The first run is the only one that
 * normally happens, but while one row keeps failing the others are live: a manager edits them,
 * the agreement and the account flag update them, and a run that re-wrote them from the old
 * option would put back what the site has since changed. Posts are the store from the first run
 * whatever happens, so a row the store refused is written by a later run.
 *
 * A draft offer is different: it is due on every run, whichever run wrote the row, until it
 * exists. The offer can be refused after its row was written, or the request can stop between
 * the two writes, and the held index is the only copy of the offer text, so a sponsor still
 * waiting for its offer is named in the report and keeps the migration open, and the option
 * with it.
 *
 * **One run at a time.** A run takes `OPT_LOCK` before it reads anything and gives it back when
 * it is done. The one-minute transient is only a pace: two first requests
 * after an upgrade can both find it unset, and two runs at once could both find a key the store
 * does not hold yet and both insert it, one sponsor as two posts. The lock is taken with
 * `add_option()`, the way `WPCPM_Sponsor_Approval` takes its own, so a second run finds it held
 * and does nothing, whether it came from `maybe_run()` or from Run again on the Sponsors screen.
 *
 * The report option is the record of every run. No audit row is written: the audit log is keyed
 * by an institution or a sponsor, and a migration is about neither.
 */
final class WPCPM_Sponsors_Migration {

	/**
	 * The report of the last run, autoloaded: every request reads it until the migration is
	 * complete, and one autoloaded row costs less than a query per request for ever (the
	 * reasoning behind `WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED`).
	 */
	const OPT_REPORT = 'wpcpm_sponsors_migrated';

	/** Set for a minute before each run, so a row that keeps failing is retried once a minute rather than on every request. The pace, not the lock: see `OPT_LOCK`. */
	const TRANSIENT_RUNNING = 'wpcpm_sponsors_migrating';

	/**
	 * The option a run holds while it runs, created with `add_option()`: the one request that
	 * creates the row runs, and any other finds it there and does nothing. Never autoloaded:
	 * nothing reads it but a run.
	 */
	const OPT_LOCK = 'wpcpm_sponsors_migrate_lock';

	/**
	 * How long a lock is honored, in seconds: five minutes.
	 *
	 * Far longer than a run over a few dozen rows takes, so a run still inserting is never taken
	 * over and doubled; and short enough that a lock a request died holding keeps the move shut
	 * for minutes, not for good. The pace retries once a minute, so the run after a dead one
	 * comes within a few of them.
	 */
	const LOCK_TIMEOUT = 300;

	/** The keys of a held row that the store keeps; the offer mirror and the logo facts have no place in the new row. */
	const KEPT = array( 'name', 'website', 'status', 'option', 'support', 'product_type', 'anything', 'contact_person', 'contact_email', 'mentors', 'consent', 'created', 'agreement', 'interests', 'dashboard_account' );

	/** The offer mirror of a held row: Airtable's copy of the primary offer, which seeds one in draft. */
	const LEGACY_OFFER = array( 'offer', 'instructions', 'more_info', 'coupon_link' );

	/** What seed_offer() says: this run made the draft offer. */
	const OFFER_SEEDED = 'seeded';

	/** What seed_offer() says: a draft offer was due and there is still none. */
	const OFFER_MISSING = 'missing';

	/**
	 * The six options the sponsors sync kept. The class that declared them is gone since 1.111.0,
	 * and a site that upgrades holds them until the move onto the site completes: `clean_up()`
	 * removes them then, and the Sponsors module's uninstall removes them from a site uninstalled
	 * before it did. One list, read in both places, so the two cannot drift apart.
	 */
	const SYNC_OPTIONS = array( 'wpcpm_sponsors_state', 'wpcpm_sponsors_report', 'wpcpm_sponsors_last_sync', 'wpcpm_sponsors_last_error', 'wpcpm_sponsors_lock', 'wpcpm_sponsors_sync_shrink' );

	/** The sponsors sync's two cron hooks, gone with its class since 1.111.0; cleared in the same two places, for the same reason. */
	const SYNC_HOOKS = array( 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick' );

	/**
	 * The mark the Collaboration Agreement left on a document whose Airtable write failed, for
	 * the sponsors sync to finish. Nothing writes or reads it since 1.111.0, when the agreement's
	 * record moved onto the site with the rest; `clean_up()` removes the marks left from before.
	 */
	const AGREEMENT_MARK = '_wpcpm_sagr_airtable_pending';

	/**
	 * Run the migration unless it is complete, at most once a minute. Hooked on `init` on every
	 * request, and called at activation.
	 */
	public static function maybe_run() {
		if ( self::report()['complete'] ) {
			return;
		}

		// The pace, set before the run so a request that arrives a moment later waits its turn.
		// Two requests can still read it unset in the same instant; `run()` takes the lock that
		// keeps them from both running.
		if ( false !== get_transient( self::TRANSIENT_RUNNING ) ) {
			return;
		}

		set_transient( self::TRANSIENT_RUNNING, time(), MINUTE_IN_SECONDS );

		self::run();
	}

	/**
	 * The migration itself, under its lock. Safe to run again at any time: see the class docblock.
	 *
	 * The lock is taken before anything is read, so a run that finds another holding it reads,
	 * writes and reports nothing; it is given back after the report is written.
	 *
	 * @return array|null The report it wrote, see `report()`; null when another run held the lock
	 *                    and this one did nothing.
	 */
	public static function run() {
		if ( ! self::lock() ) {
			return null;
		}

		$previous = self::report();
		$team     = self::team();
		$held     = WPCPM_Sponsors_Index::rows();
		$keys     = array();
		$report   = array(
			'complete' => false,
			'rows'     => 0,
			'posts'    => 0,
			'seeded'   => 0,
			'unseeded' => array(),
			'unmapped' => array(),
			'at'       => time(),
			'runs'     => $previous['runs'] + 1,
		);

		// The option is read whatever its `v` says: the store's own VERSION moved to 2 with this
		// release, and a version check here would throw every held row away.
		$stored = get_option( WPCPM_Sponsors_Index::OPT_NAME );
		$old    = ( is_array( $stored ) && isset( $stored['rows'] ) && is_array( $stored['rows'] ) ) ? $stored['rows'] : array();

		foreach ( $old as $key => $old_row ) {
			$key = trim( (string) $key );

			if ( ! WPCPM_Sponsors_Index::is_key( $key ) || ! is_array( $old_row ) ) {
				continue;
			}

			$keys[] = $key;
			$name   = isset( $old_row['name'] ) ? trim( (string) $old_row['name'] ) : '';
			$label  = '' !== $name ? $name : $key;
			$named  = isset( $old_row['manager'] ) ? trim( (string) $old_row['manager'] ) : '';
			$user   = '' === $named ? '' : self::user_for( $named, $team );

			// Listed on every run, not only the one that writes the row: the report says which
			// sponsors the held index gave a manager the site has no program manager for.
			if ( '' !== $named && '' === $user ) {
				$report['unmapped'][] = $label;
			}

			$is_post = isset( $held[ $key ] );

			if ( ! $is_post ) {
				$is_post = WPCPM_Sponsors_Index::insert( self::new_row( $key, $user, $old_row ) );
			}

			$offer = self::seed_offer( $key, $old_row, $is_post );

			if ( self::OFFER_SEEDED === $offer ) {
				++$report['seeded'];
			} elseif ( self::OFFER_MISSING === $offer ) {
				$report['unseeded'][] = $label;
			}
		}

		self::seed_booking_links( $team );

		$now = WPCPM_Sponsors_Index::rows();

		foreach ( $keys as $key ) {
			if ( isset( $now[ $key ] ) ) {
				++$report['posts'];
			}
		}

		$report['rows']     = count( $keys );
		$report['complete'] = $report['posts'] === $report['rows'] && empty( $report['unseeded'] );

		if ( $report['complete'] ) {
			self::clean_up();
		}

		update_option( self::OPT_REPORT, $report, true );

		self::unlock();

		return $report;
	}

	/**
	 * Take the run's lock.
	 *
	 * `add_option()` is the test-and-set, as in `WPCPM_Sponsor_Approval::lock()`: it answers true
	 * only to the request that creates the row. A lock older than `LOCK_TIMEOUT` belonged to a
	 * request that died between taking and giving it back, and is cleared first.
	 *
	 * @return bool Whether the lock was taken.
	 */
	private static function lock() {
		$held = get_option( self::OPT_LOCK );

		if ( false !== $held && ( time() - (int) $held ) > self::LOCK_TIMEOUT ) {
			delete_option( self::OPT_LOCK );
		}

		return (bool) add_option( self::OPT_LOCK, time(), '', false );
	}

	/**
	 * Give the run's lock back.
	 */
	private static function unlock() {
		delete_option( self::OPT_LOCK );
	}

	/**
	 * The report of the last run, or the shape of one when nothing has run yet.
	 *
	 * @return array `complete` (every well-formed row of the held index is a post and every
	 *               draft offer due from it exists), `rows` (how many the held index had), `posts`
	 *               (how many of those the store holds), `seeded` (the draft offers this run
	 *               made), `unseeded` (the sponsors whose row carries offer text and which still
	 *               have no offer), `unmapped` (the sponsors whose manager has no program manager's
	 *               account on the site), `at` (when the run was) and `runs` (how many there have
	 *               been). A sponsor is named by its name, or by its key when the row has none.
	 */
	public static function report() {
		$stored = get_option( self::OPT_REPORT );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'complete' => ! empty( $stored['complete'] ),
			'rows'     => isset( $stored['rows'] ) ? (int) $stored['rows'] : 0,
			'posts'    => isset( $stored['posts'] ) ? (int) $stored['posts'] : 0,
			'seeded'   => isset( $stored['seeded'] ) ? (int) $stored['seeded'] : 0,
			'unseeded' => isset( $stored['unseeded'] ) && is_array( $stored['unseeded'] ) ? array_values( array_map( 'strval', $stored['unseeded'] ) ) : array(),
			'unmapped' => isset( $stored['unmapped'] ) && is_array( $stored['unmapped'] ) ? array_values( array_map( 'strval', $stored['unmapped'] ) ) : array(),
			'at'       => isset( $stored['at'] ) ? (int) $stored['at'] : 0,
			'runs'     => isset( $stored['runs'] ) ? (int) $stored['runs'] : 0,
		);
	}

	/**
	 * The held team, keyed by Team Members record ID: `name`, `email`, `calendly`. Read
	 * directly and whatever its `v` says, for the same reason as the index.
	 *
	 * @return array[]
	 */
	private static function team() {
		$stored = get_option( WPCPM_Sponsors_Index::OPT_TEAM );

		return ( is_array( $stored ) && isset( $stored['rows'] ) && is_array( $stored['rows'] ) ) ? $stored['rows'] : array();
	}

	/**
	 * The program manager behind a Team Members record, by its address.
	 *
	 * An account at that address that does not hold the program capability maps to nobody: the
	 * design assigns a sponsor to a program manager, and a sponsor left without one is listed in
	 * the report for a manager to assign by hand.
	 *
	 * @param string $team_id A Team Members record ID.
	 * @param array  $team    The held team.
	 * @return string The user ID as a string, the shape a row's `manager` takes, or ''.
	 */
	private static function user_for( $team_id, array $team ) {
		$member = ( isset( $team[ $team_id ] ) && is_array( $team[ $team_id ] ) ) ? $team[ $team_id ] : array();
		$email  = isset( $member['email'] ) ? trim( (string) $member['email'] ) : '';

		if ( '' === $email ) {
			return '';
		}

		$user = get_user_by( 'email', $email );

		return ( $user instanceof WP_User && user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) ? (string) $user->ID : '';
	}

	/**
	 * The store's row for a held row: the keys that survive, the mapped manager, empty notes.
	 *
	 * @param string $key     Sponsor key.
	 * @param string $user    The manager's user ID, or ''.
	 * @param array  $old_row The held row.
	 * @return array
	 */
	private static function new_row( $key, $user, array $old_row ) {
		$row = array(
			'record_id' => $key,
			'manager'   => $user,
			'notes'     => '',
		);

		foreach ( self::KEPT as $field ) {
			if ( array_key_exists( $field, $old_row ) ) {
				$row[ $field ] = $old_row[ $field ];
			}
		}

		// A held sponsor named by the program's convention for a test, the word "TEST" first
		// (`WPCPM_Sponsors_Index::is_test_name()`), comes onto the site as a test sponsor, never
		// listed publicly (1.123.18).
		$row['test'] = isset( $old_row['name'] ) && WPCPM_Sponsors_Index::is_test_name( (string) $old_row['name'] );

		return $row;
	}

	/**
	 * Seed a sponsor's primary offer in draft from its held offer text, as the approval does. It
	 * is due while the held row carries offer text and the sponsor has no offer, on every run
	 * until it exists.
	 *
	 * @param string $key     Sponsor key.
	 * @param array  $old_row The held row.
	 * @param bool   $is_post Whether the store holds the sponsor's row: seed() reads it, so a
	 *                        row the store refused waits for the run that writes it.
	 * @return string OFFER_SEEDED when this run made the offer, OFFER_MISSING when one was due
	 *                and there is still none, '' when none was due.
	 */
	private static function seed_offer( $key, array $old_row, $is_post ) {
		$legacy = array();

		foreach ( self::LEGACY_OFFER as $field ) {
			$legacy[ $field ] = isset( $old_row[ $field ] ) ? (string) $old_row[ $field ] : '';
		}

		if ( '' === trim( implode( '', $legacy ) ) || ! empty( WPCPM_Sponsor_Offers::offers_of( $key ) ) ) {
			return '';
		}

		$offer = $is_post ? WPCPM_Sponsor_Offers::seed( $key, $legacy ) : null;

		if ( is_int( $offer ) && $offer > 0 ) {
			return self::OFFER_SEEDED;
		}

		// Asked again rather than read off the answer: an offer that exists is not due, whatever
		// the call that made it said.
		return empty( WPCPM_Sponsor_Offers::offers_of( $key ) ) ? self::OFFER_MISSING : '';
	}

	/**
	 * Give each program manager the booking link the held team knew them by, when they have an
	 * account, hold the program capability and have no link of their own yet. A link already
	 * there is the manager's own choice and is kept.
	 *
	 * @param array $team The held team.
	 */
	private static function seed_booking_links( array $team ) {
		foreach ( $team as $member ) {
			if ( ! is_array( $member ) ) {
				continue;
			}

			$email = isset( $member['email'] ) ? trim( (string) $member['email'] ) : '';
			$link  = isset( $member['calendly'] ) ? trim( (string) $member['calendly'] ) : '';

			if ( '' === $email || '' === $link ) {
				continue;
			}

			$user = get_user_by( 'email', $email );

			if ( ! $user instanceof WP_User || ! user_can( $user, WPCPM_Roles::CAP_MANAGE ) || '' !== WPCPM_Manager_Booking::url_for( $user->ID ) ) {
				continue;
			}

			WPCPM_Manager_Booking::set( $user->ID, $link );
		}
	}

	/**
	 * Remove what the store no longer needs once every row is a post: the held index, the team,
	 * the sponsors sync's own options and hooks, and the marks the agreement left for that sync.
	 */
	private static function clean_up() {
		delete_option( WPCPM_Sponsors_Index::OPT_NAME );
		delete_option( WPCPM_Sponsors_Index::OPT_TEAM );

		foreach ( self::SYNC_OPTIONS as $option ) {
			delete_option( $option );
		}

		foreach ( self::SYNC_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		// Once, with the rest: an agreement whose Airtable write failed
		// before this release still carries the mark that asked the sync to finish it. Nothing
		// finishes that write any more, so the mark would only sit on the document for good.
		delete_post_meta_by_key( self::AGREEMENT_MARK );
	}
}
