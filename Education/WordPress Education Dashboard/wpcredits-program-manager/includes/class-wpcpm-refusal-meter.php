<?php
/**
 * The per-account refusal meter.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts an account's refused claims for the day and locks it when the day is full.
 *
 * Extracted from `WPCPM_Institution_Roster` for the Sponsors module (design spec of
 * 4 September 2026, decision 1): the roster kept the two ceiling keys, the twenty-a-day
 * ceiling and the once-a-day lock as private methods, and the sponsor roster needs the same
 * three with its own key stem. One class and two scopes, so the two meters cannot drift.
 *
 * The rule, unchanged from the roster: nobody and a manager are not metered (a manager's
 * refusals are the site's mistakes to fix, not an attacker's probing); the claim that fills
 * the bucket locks the account (`is_locked()` reads a full bucket as locked); and the lock is
 * recorded once a day whatever else happens, through a limit-of-one claim on a second key,
 * the same test-and-set the dwell token relies on, so two requests filling the bucket
 * together write one row between them. Who writes that row is the caller's business: the
 * institution roster files it under the institution, the sponsor roster under the sponsor.
 */
final class WPCPM_Refusal_Meter {

	/** Refused claims an account may collect in a day before it is locked until tomorrow. */
	const PER_DAY = 20;

	/** Stem of the bucket that counts refusals; joined to the scope as `claim-refused`. */
	const STEM_REFUSED = 'refused';

	/** Stem of the once-a-day lock record; joined to the scope as `claim-locked`. */
	const STEM_LOCKED = 'locked';

	/**
	 * Count one refusal against an account, and say whether this one locked it.
	 *
	 * @param string       $scope The meter: `claim` for the institution roster, `sponsor` for the sponsor roster.
	 * @param WP_User|null $actor The acting account, already resolved.
	 * @return bool True the one time a refusal fills the day's bucket and records the lock, so the caller logs it.
	 */
	public static function refuse( $scope, $actor ) {
		if ( ! self::metered( $actor ) ) {
			return false;
		}

		WPCPM_Ceiling::claim( self::key( $scope, self::STEM_REFUSED, $actor ), self::PER_DAY, DAY_IN_SECONDS );

		return self::is_locked( $scope, $actor )
			&& (bool) WPCPM_Ceiling::claim( self::key( $scope, self::STEM_LOCKED, $actor ), 1, DAY_IN_SECONDS );
	}

	/**
	 * Whether this account's refused claims have filled today's bucket in this scope.
	 *
	 * @param string       $scope The meter.
	 * @param WP_User|null $actor The acting account, already resolved.
	 * @return bool
	 */
	public static function is_locked( $scope, $actor ) {
		if ( ! self::metered( $actor ) ) {
			return false;
		}

		return WPCPM_Ceiling::count( self::key( $scope, self::STEM_REFUSED, $actor ), DAY_IN_SECONDS ) >= self::PER_DAY;
	}

	/**
	 * The accounts among a set whose write path is locked for the rest of today.
	 *
	 * Read from the ceiling buckets themselves rather than from a list kept beside them, so a
	 * notice and the lock cannot disagree. The caller passes the bounded set it cares about
	 * (the live members of its module); anything that is not a user is skipped.
	 *
	 * @param string $scope The meter.
	 * @param array  $users Candidates, usually `WP_User[]`.
	 * @return WP_User[]
	 */
	public static function locked_among( $scope, array $users ) {
		$locked = array();

		foreach ( $users as $user ) {
			if ( $user instanceof WP_User && self::is_locked( $scope, $user ) ) {
				$locked[] = $user;
			}
		}

		return $locked;
	}

	/**
	 * The ceiling key for one scope, one stem and one account.
	 *
	 * `<scope>-<stem>` is the stem the roster has always used (`claim-refused`, `claim-locked`),
	 * so the buckets a live site holds keep counting across this extraction.
	 *
	 * @param string  $scope The meter.
	 * @param string  $stem  `STEM_REFUSED` or `STEM_LOCKED`.
	 * @param WP_User $actor The acting account.
	 * @return string
	 */
	public static function key( $scope, $stem, WP_User $actor ) {
		return WPCPM_Ceiling::key( sanitize_key( $scope ) . '-' . $stem, (string) $actor->ID );
	}

	/**
	 * Whether an account is one the meter counts at all.
	 *
	 * @param mixed $actor Whatever the caller resolved.
	 * @return bool
	 */
	private static function metered( $actor ) {
		return $actor instanceof WP_User && $actor->exists() && ! user_can( $actor, WPCPM_Roles::CAP_MANAGE );
	}
}
