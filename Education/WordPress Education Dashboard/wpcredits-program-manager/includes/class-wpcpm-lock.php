<?php
/**
 * A lock one request holds over a name for a while, kept as one row of the options table.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets one request at a time do something to one thing, such as send one account its invitation.
 *
 * **The contract.**
 *
 * - `take( $name, $ttl )` answers a token when this request now holds the lock, and '' when another
 *   request holds it. A lock holds until it is let go or until `$ttl` seconds have passed, whichever
 *   comes first; after that the next request to ask takes it over.
 * - `release( $name, $token )` lets the lock go only while its row still holds that token, so a
 *   request whose lock ran out and was taken over never lets go of its successor's.
 * - `until( $name )` says when a held lock runs out, for a caller that tells somebody how long to
 *   wait. It decides nothing: only `take()` decides who holds the lock.
 * - The caller takes the lock, does its work inside `try`, and lets go in `finally`, so an exception
 *   lets go too. A request that ends without reaching `finally` (a fatal error, a killed process)
 *   holds the lock for `$ttl` at most.
 * - The name is the caller's: its own prefix, so uninstall can sweep the rows by it, and what it
 *   locks.
 *
 * **Three statements, and no options API.** Each step is one SQL statement that the database
 * answers by the unique key on `option_name`, written straight to the options table and never read
 * through the object cache:
 *
 * - take: `INSERT IGNORE`, which writes the row only when no row of the name is there, and reports 1,
 *   or 0 when one is there;
 * - take over: one `UPDATE` matching only a row whose time has passed, so of two requests taking over
 *   one lock that ran out at the same moment, the second finds the time the first wrote and matches
 *   nothing;
 * - let go: one `DELETE` matching this request's own value.
 *
 * Every write changes the row's value or leaves no row, so what the database reports is the same
 * whatever client flags the connection was opened with. `add_option()` would not do: it reads the
 * name first, through the object cache and core's list of options known not to be there, then
 * writes with an upsert that overwrites a row the read did not see, so two requests can both be told
 * they wrote the row.
 *
 * **The row.** `option_value` is the time the lock runs to, a Unix time, digits only, and that
 * value is the holder's token. Digits only because the take-over reads it with `CAST( option_value
 * AS UNSIGNED )`: a value the CAST had to cut short would be only a warning in WordPress's own
 * session, but error 1292 in one of MySQL's strict modes (a db.php drop-in, or the
 * `incompatible_sql_modes` filter, can leave the session in one), and the take-over would never
 * run. The token tells the holders apart because a take-over matches only a row whose time has
 * passed and writes now and the lifetime, which is always later; so a holder whose lock ran out and
 * was taken over holds a token no row has any more, and its `release()` deletes nothing. A row that
 * holds no time at all, which this class never writes, reads as 0 in WordPress's own session and is
 * taken over. `autoload` is `off`, so the row never loads with every page. Nothing reads or writes
 * the name through `get_option()` and the rest, so the options cache never holds a copy of it, and
 * a row is gone once its lock is let go.
 */
final class WPCPM_Lock {

	/**
	 * Take the lock on a name.
	 *
	 * @param string $name The option row's name: the caller's prefix and what it locks.
	 * @param int    $ttl  How long the lock holds at most, in seconds; one at the least.
	 * @return string The token `release()` takes, which is the row's own value, the time the lock
	 *                runs to, or '' while another request holds the lock.
	 */
	public static function take( $name, $ttl ) {
		global $wpdb;

		$name  = (string) $name;
		$now   = time();
		$token = (string) ( $now + max( 1, (int) $ttl ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A test-and-set the options cache must not answer: the unique key on option_name decides.
		$taken = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, $token ) );

		if ( 1 !== (int) $taken ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Takes over only a lock whose time has passed, in one statement.
			$taken = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST( option_value AS UNSIGNED ) <= %d", $token, $name, $now ) );
		}

		return 1 === (int) $taken ? $token : '';
	}

	/**
	 * Let the lock on a name go, if its row still holds this request's token.
	 *
	 * @param string $name  The option row's name, as it was taken.
	 * @param string $token What `take()` answered.
	 * @return bool Whether a row was deleted.
	 */
	public static function release( $name, $token ) {
		global $wpdb;

		if ( '' === (string) $token ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lets go of this request's own row only, in one statement.
		$gone = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", (string) $name, (string) $token ) );

		return 1 === (int) $gone;
	}

	/**
	 * When the lock on a name runs out, as its row says: a Unix time, or 0 when no row is there.
	 *
	 * @param string $name The option row's name.
	 * @return int
	 */
	public static function until( $name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read straight from the table, as the lock is written.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", (string) $name ) );

		return null === $value ? 0 : (int) $value;
	}
}
