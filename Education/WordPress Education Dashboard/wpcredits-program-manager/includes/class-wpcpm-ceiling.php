<?php
/**
 * Counted claims in fixed time windows: the one rate-limit primitive.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many times something may happen in a window, kept as one option per key and window.
 *
 * Every ceiling in the Institutions module is this class: the public form's five submissions
 * an hour per address and forty a day for the whole site, the dwell token's single use, an
 * institution's signed-agreement uploads and template generations per day. Each of those was
 * about to become its own counter with its own shape and its own race; one primitive means
 * the race is thought about once.
 *
 * Time is cut into windows of a fixed length. Every window has a number, the bucket,
 * `floor( time() / $window )`, and a key's claims in one bucket are one row in the options
 * table named after the pair. The row is created with `add_option()`, which is one INSERT
 * that reports failure when the row already exists, so two requests arriving in the same
 * instant cannot both be the first. That is what makes a limit of 1 exact, and the dwell
 * token relies on it: a harvested token is refused on its second use however close the two
 * uses are. Above 1 the count is read and written back, and two requests that read the same
 * number can each add one and land on the same total, so a ceiling of five can admit a sixth
 * under a burst. That is the price of not taking a lock on every submission, and every
 * ceiling here is a nuisance control rather than an entitlement.
 *
 * The first-claim row carries no timestamp, deliberately. `add_option()` is an
 * `INSERT ... ON DUPLICATE KEY UPDATE` that reports success by rows affected, so a second
 * first claim writing a different value would overwrite the winner's row and be told it won.
 * Two first claims write byte-identical rows, and the second is told the truth.
 *
 * Nothing here is autoloaded. There is a row per key per window and each lives for a few
 * hours, and a row that loads on every page of the site to say how many times a form was
 * posted last Tuesday is exactly the kind of option that accumulates until the site is slow.
 *
 * The window and the bucket are stored inside the value rather than parsed out of the name.
 * The name is a hash, so a key can be anything (an address hash, a record ID, a token) and
 * nothing about it is readable in the options table, and a hash cannot be dated. `sweep()`
 * reads the value instead and removes what is old by the row's own clock, whichever window
 * it was cut for.
 */
class WPCPM_Ceiling {

	/** Option name prefix. The rest is `md5( key . '|' . bucket )`. */
	const PREFIX = 'wpcpm_ceiling_';

	/** The daily cron that removes rows whose window has long passed. */
	const CRON_SWEEP = 'wpcpm_ceiling_sweep';

	/**
	 * How many windows behind the current one a row is kept.
	 *
	 * Two, so a row is never swept while a request that read the bucket number a moment
	 * before the boundary could still be counting against it, and so the previous window is
	 * still there to read when somebody asks why a form was refused an hour ago.
	 */
	const KEEP_WINDOWS = 2;

	/** Value shape version, so a later shape can tell an older row from a broken one. */
	const VERSION = 1;

	/**
	 * Hook the sweep. Scheduling it is the module's job at activation.
	 */
	public static function init() {
		add_action( self::CRON_SWEEP, array( __CLASS__, 'sweep' ) );
	}

	/**
	 * Claim one of the `$limit` places in the current window for a key.
	 *
	 * True means the claim was counted and the caller may proceed. False means the window is
	 * full, or the limit admits nobody, or another request created the row in the same
	 * instant: that request's claim counts and this one is refused, which is wrong only in
	 * the safe direction and only in that instant.
	 *
	 * **`$amount` is for ceilings counted in things rather than in events.** The import's
	 * six-hundred-rows-a-day is one claim of however many rows the file holds, not six hundred
	 * claims: a loop calling this once per row would write six hundred option rows to learn
	 * one answer, and would admit a file that goes over the line halfway through. Claiming the
	 * whole amount at once means a batch that does not fit is refused before a row is parsed,
	 * which is where the spec puts it.
	 *
	 * A claim larger than the whole limit can never fit and is refused rather than clamped, so
	 * a caller cannot get a free pass by asking for too much.
	 *
	 * @param string $key    What is being limited, e.g. `'dwell:' . $hash`. Never a raw
	 *                       address: callers pass `wp_hash()` of one.
	 * @param int    $limit  How many claims a window admits.
	 * @param int    $window Window length in seconds.
	 * @param int    $amount How much of the window's allowance this claim takes. One by
	 *                       default, which is every caller that counts events.
	 * @return bool Whether the claim was counted.
	 */
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$limit  = (int) $limit;
		$amount = max( 1, (int) $amount );
		$window = self::window( $window );
		$bucket = self::bucket( $window );
		$name   = self::option_name( $key, $bucket );

		if ( $limit < 1 || $amount > $limit ) {
			return false;
		}

		$row = get_option( $name );

		if ( false === $row ) {
			// The first claim in this window. `add_option()` is the test-and-set: one INSERT
			// that fails when the row is already there, so two first claims arriving together
			// cannot both succeed, and a limit of 1 is exact. Never autoloaded.
			return (bool) add_option( $name, self::row( $window, $bucket, $amount ), '', false );
		}

		$count = self::count_of( $row );

		if ( $count + $amount > $limit ) {
			return false;
		}

		update_option( $name, self::row( $window, $bucket, $count + $amount ), false );

		return true;
	}

	/**
	 * How many claims the current window holds for a key.
	 *
	 * @param string $key    What is being limited.
	 * @param int    $window Window length in seconds, the same one `claim()` was given.
	 * @return int
	 */
	public static function count( $key, $window ) {
		$window = self::window( $window );

		return self::count_of( get_option( self::option_name( $key, self::bucket( $window ) ) ) );
	}

	/**
	 * Remove the rows whose window is long past. The daily cron body.
	 *
	 * A row is judged by its own window and bucket, read from its value: the name says
	 * nothing about when it was cut, and a row cut for a day-long window is still current
	 * when an hour-long one from the same moment is stale. Anything under the prefix this
	 * class cannot read goes too, because nothing will ever claim against it again and
	 * nothing else will ever remove it.
	 *
	 * @return int How many rows were removed.
	 */
	public static function sweep() {
		$swept = 0;

		foreach ( self::names() as $name ) {
			if ( ! self::is_stale( get_option( $name ) ) ) {
				continue;
			}

			delete_option( $name );
			++$swept;
		}

		return $swept;
	}

	/**
	 * Remove every row, current or not. Called on uninstall.
	 *
	 * @return int How many rows were removed.
	 */
	public static function delete_all() {
		$names = self::names();

		foreach ( $names as $name ) {
			delete_option( $name );
		}

		return count( $names );
	}

	/**
	 * Build a key from parts.
	 *
	 * Each part goes through `sanitize_key()` except one that is a hash, which is kept whole.
	 * Callers pass `wp_hash()` of an address rather than the address, and that is the point
	 * of the rule: a raw address sanitised loses its dots, so two addresses could share a key,
	 * and a hashed one is never readable from the options table.
	 *
	 * @param string ...$parts Key parts, joined with `:`.
	 * @return string
	 */
	public static function key( ...$parts ) {
		$clean = array();

		foreach ( $parts as $part ) {
			$part = (string) $part;

			$clean[] = preg_match( '/^[0-9a-f]{32,128}$/i', $part ) ? $part : sanitize_key( $part );
		}

		return implode( ':', $clean );
	}

	/**
	 * Every option name under the prefix.
	 *
	 * @return string[]
	 */
	private static function names() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rows are addressable only by exact name; this runs from the daily sweep and from uninstall.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::PREFIX ) . '%' ) );

		return array_map( 'strval', (array) $names );
	}

	/**
	 * Whether a stored row is older than `KEEP_WINDOWS` windows of its own length.
	 *
	 * @param mixed $row The option value.
	 * @return bool
	 */
	private static function is_stale( $row ) {
		if ( ! is_array( $row ) || empty( $row['window'] ) || ! isset( $row['bucket'] ) ) {
			return true;
		}

		$window = self::window( $row['window'] );

		return ( self::bucket( $window ) - (int) $row['bucket'] ) > self::KEEP_WINDOWS;
	}

	/**
	 * The option name for a key in a bucket.
	 *
	 * @param string $key    What is being limited.
	 * @param int    $bucket Bucket number.
	 * @return string
	 */
	private static function option_name( $key, $bucket ) {
		return self::PREFIX . md5( (string) $key . '|' . (int) $bucket );
	}

	/**
	 * The current bucket number for a window length.
	 *
	 * @param int $window Window length in seconds, already floored by `window()`.
	 * @return int
	 */
	private static function bucket( $window ) {
		return (int) floor( time() / $window );
	}

	/**
	 * A usable window length: whole seconds, at least one.
	 *
	 * @param mixed $window Window length as given.
	 * @return int
	 */
	private static function window( $window ) {
		return max( 1, (int) $window );
	}

	/**
	 * The stored row. Deterministic for a given count; see the class docblock for why.
	 *
	 * @param int $window Window length in seconds.
	 * @param int $bucket Bucket number.
	 * @param int $count  Claims so far.
	 * @return array
	 */
	private static function row( $window, $bucket, $count ) {
		return array(
			'v'      => self::VERSION,
			'window' => (int) $window,
			'bucket' => (int) $bucket,
			'count'  => (int) $count,
		);
	}

	/**
	 * The count held by a stored row, or zero for anything that is not one.
	 *
	 * @param mixed $row The option value.
	 * @return int
	 */
	private static function count_of( $row ) {
		if ( ! is_array( $row ) || ! isset( $row['count'] ) ) {
			return 0;
		}

		return max( 0, (int) $row['count'] );
	}
}
