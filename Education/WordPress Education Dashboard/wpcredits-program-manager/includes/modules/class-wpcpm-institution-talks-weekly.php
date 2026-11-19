<?php
/**
 * Institutions module - the Monday email of follow-ups due, to each Administrator (Institutions off Airtable,
 * release 2, Task 17).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Once a week, on Monday at 07:00 UTC, each Administrator is emailed the follow-ups due in their own Mine of the
 * Talks view, the lines their menu's count counts.
 *
 * **One rule.** What an email lists is `WPCPM_Institution_Talks_View::due_for()`: the recipient's Mine (their schools,
 * the not assigned ones in their area, and the not assigned ones in nobody's area) read through
 * `WPCPM_Institution_Talks::due()`, the one list of what is due. Nothing here decides again who sees what or which
 * stage counts. The email has two parts, each left out with its heading when it has no line: the recipient's own
 * schools, then the not assigned ones. A recipient with no line gets no email.
 *
 * **Who is mailed.** An account that manages the program (`WPCPM_Institutions::managers()`) and has an area or at
 * least one school assigned to it: the capability is also granted to every WordPress administrator account, which
 * must not be mailed for its capability alone.
 *
 * **When.** A daily event at 07:00 UTC, on the clock only in site mode (`sync_schedule()` takes it off when the
 * setting is off); it does nothing on any day but Monday (UTC), nothing before 07:00 UTC (Sunday's event run late,
 * after midnight), nothing outside site mode, and nothing a second time in an ISO week: the week is noted under the
 * lock before the first email goes. A Monday whose event only runs on Tuesday sends nothing that week. Each email is built in its reader's
 * language by `WPCPM_Mail::send()`, one recipient to an email, and nothing about a recipient is written to a log.
 */
final class WPCPM_Institution_Talks_Weekly {

	/** The daily event. */
	const CRON_DAILY = 'wpcpm_institution_talks_daily';

	/** The ISO week (`o-W`, UTC) the last emails went in. */
	const OPT_WEEK = 'wpcpm_institution_talks_week';

	/** The lock one run holds (`WPCPM_Lock`). */
	const LOCK = 'wpcpm_institution_talks_lock';

	/** How long a run holds the lock at most, in seconds. */
	const LOCK_TTL = 600;

	/** The email's id in the catalog. */
	const MAIL = 'institution-follow-ups-weekly';

	/** The hour of the day the event runs at, UTC. */
	const HOUR = 7;

	/**
	 * Hang the run on its hook, and keep the event on the clock in site mode and off it otherwise.
	 */
	public static function init() {
		add_action( self::CRON_DAILY, array( __CLASS__, 'run_daily' ) );
		add_action( 'init', array( __CLASS__, 'sync_schedule' ), 20 );
	}

	/**
	 * In site mode, put the daily event on the clock at the next 07:00 UTC when it is not there; outside it, take the
	 * event off the clock, so the setting going off removes it at the next page load.
	 */
	public static function sync_schedule() {
		$next = wp_next_scheduled( self::CRON_DAILY );

		if ( WPCPM_Institutions_Index::on_site() ) {
			if ( ! $next ) {
				wp_schedule_event( self::next_run( time() ), 'daily', self::CRON_DAILY );
			}

			return;
		}

		if ( $next ) {
			self::unschedule();
		}
	}

	/**
	 * Take the daily event off the clock.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
	}

	/**
	 * The next 07:00 UTC after a time.
	 *
	 * @param int $now Unix time.
	 * @return int
	 */
	public static function next_run( $now ) {
		$now   = (int) $now;
		$today = $now - ( $now % DAY_IN_SECONDS ) + self::HOUR * HOUR_IN_SECONDS;

		return $today > $now ? $today : $today + DAY_IN_SECONDS;
	}

	/**
	 * The hook's callback.
	 */
	public static function run_daily() {
		self::run();
	}

	/**
	 * Who may be mailed: the Administrators the site knows by their work, an area or at least one school assigned to
	 * them.
	 *
	 * @return WP_User[]
	 */
	public static function recipients() {
		$assigned = array();

		foreach ( WPCPM_Institution_Talks_View::facts() as $one ) {
			if ( is_array( $one ) && isset( $one['assigned'] ) && (int) $one['assigned'] > 0 ) {
				$assigned[ (int) $one['assigned'] ] = true;
			}
		}

		$out = array();

		foreach ( WPCPM_Institutions::managers() as $user ) {
			if ( $user instanceof WP_User && ( isset( $assigned[ (int) $user->ID ] ) || WPCPM_Institution_Areas::has_area( (int) $user->ID ) ) ) {
				$out[] = $user;
			}
		}

		return $out;
	}

	/**
	 * The Monday run: on a Monday (UTC), in site mode, once in the ISO week, one email to each recipient with follow-ups
	 * due in their Mine.
	 *
	 * @param int $now Unix time; 0 for the current time.
	 * @return int How many emails were handed off.
	 */
	public static function run( $now = 0 ) {
		if ( ! WPCPM_Institutions_Index::on_site() ) {
			return 0;
		}

		$now = $now > 0 ? (int) $now : time();

		if ( '1' !== gmdate( 'N', $now ) || (int) gmdate( 'G', $now ) < self::HOUR ) {
			return 0;
		}

		$week = gmdate( 'o-W', $now );

		if ( (string) get_option( self::OPT_WEEK, '' ) === $week ) {
			return 0;
		}

		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			return 0;
		}

		$sent = 0;

		try {
			// Read again under the lock, from the table: another run may have noted the week since.
			self::forget_week();

			if ( (string) get_option( self::OPT_WEEK, '' ) === $week ) {
				return 0;
			}

			update_option( self::OPT_WEEK, $week, false );

			// The talks as they are now, not as this request may have read them before.
			WPCPM_Institution_Talks_View::forget();

			$today = (string) wp_date( 'Y-m-d', $now );
			$talks = (string) WPCPM_Administrators_Dashboard::view_url( WPCPM_Administrators_Dashboard::VIEW_TALKS );

			foreach ( self::recipients() as $user ) {
				$due = WPCPM_Institution_Talks_View::due_for( (int) $user->ID, $today );

				if ( array() === $due ) {
					continue;
				}

				$handed = WPCPM_Mail::send(
					$user,
					self::MAIL,
					static function () use ( $due, $talks ) {
						$own   = array();
						$loose = array();

						foreach ( $due as $one ) {
							if ( WPCPM_Institution_Talks_View::assignee_of( $one ) > 0 ) {
								$own[] = self::line( $one );
							} else {
								$loose[] = self::line( $one );
							}
						}

						return WPCPM_Mail_Templates::mail(
							self::MAIL,
							array(
								'site_name'             => WPCPM_Mail::site_name(),
								'count'                 => number_format_i18n( count( $due ) ),
								'follow_ups'            => implode( "\r\n", $own ),
								'unassigned_follow_ups' => implode( "\r\n", $loose ),
								'talks_link'            => '' !== $talks ? $talks : home_url( '/' ),
							)
						);
					}
				);

				if ( $handed ) {
					++$sent;
				}
			}
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}

		return $sent;
	}

	/**
	 * Drop what this request's cache holds of the week, its own key and its place in `notoptions` and `alloptions`, so
	 * the next read is the table's (as `WPCPM_Institution_Copy` forgets its pending list): a process answered "absent"
	 * before another noted the week would otherwise send the week's emails again.
	 */
	private static function forget_week() {
		wp_cache_delete( self::OPT_WEEK, 'options' );

		foreach ( array( 'notoptions', 'alloptions' ) as $list ) {
			$cached = wp_cache_get( $list, 'options' );

			if ( is_array( $cached ) && array_key_exists( self::OPT_WEEK, $cached ) ) {
				unset( $cached[ self::OPT_WEEK ] );
				wp_cache_set( $list, $cached, 'options' );
			}
		}
	}

	/**
	 * One follow-up's line: "<name>: <next step>, due <date>: <link>", or "<name>, due <date>: <link>" with no next
	 * step, the link left out while the page has no address.
	 *
	 * @param array $one `WPCPM_Institution_Talks::facts()`.
	 * @return string
	 */
	private static function line( array $one ) {
		$name = trim( (string) preg_replace( '/\s+/u', ' ', (string) $one['name'] ) );
		$name = '' !== $name ? $name : (string) $one['key'];
		$step = trim( (string) preg_replace( '/\s+/u', ' ', (string) $one['next_step'] ) );
		$day  = WPCPM_Dates::day( (string) $one['follow_up'] );
		$url  = WPCPM_Institution_Talks::url( (string) $one['key'] );

		$line = '' !== $step
			? sprintf( /* translators: 1: a school's name, 2: the next step of the talks, 3: a date. */ __( '%1$s: %2$s, due %3$s', 'wpcredits-program-manager' ), $name, $step, $day )
			: sprintf( /* translators: 1: a school's name, 2: a date. */ __( '%1$s, due %2$s', 'wpcredits-program-manager' ), $name, $day );

		return '' !== $url ? $line . ': ' . $url : $line;
	}
}
