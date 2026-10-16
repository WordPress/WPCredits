<?php
/**
 * When each account last signed in, and last used the site.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two dates on every account: the last completed sign-in and the first signed-in page view of the
 * latest day. The Engagement tab, the Administrator Dashboard's Mentor engagement card and
 * `wp wpcredits mentor_engagement` read them (1.122.26); WordPress itself keeps neither.
 *
 * **A sign-in counts once it completes.** The Two Factor plugin (0.16.0 on the live site) hooks
 * `wp_login` at PHP_INT_MAX and, for an account that owes a code, destroys the password session,
 * prints the code form and exits there. So every other `wp_login` callback, this one included,
 * runs after the password and before the code, whether or not the code is ever entered. An account
 * that owes a code is therefore recorded on `two_factor_user_authenticated`, which the plugin fires
 * once the code is accepted, and never on `wp_login`. Nothing in that plugin fires `wp_login` a
 * second time; one request records one sign-in all the same, should a later release do so.
 *
 * **A visit is a day, not a request.** The first signed-in page view of the site's day is written,
 * and nothing more until the day changes, so the check costs no query (WordPress has loaded the
 * account's meta to read its roles) and at most one write a day. AJAX (the wp-admin heartbeat),
 * cron, REST, XML-RPC and WP-CLI are not visits.
 *
 * Every account is recorded, not only mentors (the owner's decision D4 of 15 October 2026), and
 * any future way of signing in calls `record_sign_in()`.
 */
final class WPCPM_Sign_Ins {
	/** User meta: Unix time the last sign-in completed. */
	const META_SIGN_IN = 'wpcpm_last_sign_in';

	/** User meta: Unix time of the first signed-in page view of the latest day. */
	const META_VISIT = 'wpcpm_last_visit';

	/** Option: the seed's schema version; the seed runs while it is below SCHEMA_VERSION. */
	const OPT_VERSION = 'wpcpm_sign_ins_version';

	/** Option: when the seed ran. A sign-in before it is approximate. */
	const OPT_SINCE = 'wpcpm_sign_ins_since';

	/** Bumped when the seed has to run again. */
	const SCHEMA_VERSION = 1;

	/** Accounts the seed reads at a time. */
	const SEED_BATCH = 200;

	/** The user meta the seed reads: Two Factor's last accepted app code and last emailed code. */
	const TRACE_APP  = '_two_factor_totp_last_successful_login';
	const TRACE_CODE = '_two_factor_email_token_timestamp';

	/**
	 * Accounts whose sign-in this request has recorded.
	 *
	 * @var array<int, true>
	 */
	private static $recorded = array();

	/**
	 * Hooks. Called from `wpcpm_bootstrap()`.
	 */
	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'on_wp_login' ), 10, 2 );
		add_action( 'two_factor_user_authenticated', array( __CLASS__, 'on_two_factor' ), 10, 1 );
		add_action( 'template_redirect', array( __CLASS__, 'on_visit' ), 10, 1 );
		add_action( 'admin_init', array( __CLASS__, 'on_visit' ), 10, 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ), 10, 1 );
	}

	/**
	 * A password accepted: a sign-in, unless the account still owes a code.
	 *
	 * @param string  $user_login The username.
	 * @param WP_User $user       The account.
	 */
	public static function on_wp_login( $user_login, $user ) {
		unset( $user_login );

		if ( ! $user instanceof WP_User || self::code_owed( (int) $user->ID ) ) {
			return;
		}

		self::record_sign_in( (int) $user->ID );
	}

	/**
	 * A code accepted: the sign-in completes here.
	 *
	 * @param WP_User $user The account.
	 */
	public static function on_two_factor( $user ) {
		if ( $user instanceof WP_User ) {
			self::record_sign_in( (int) $user->ID );
		}
	}

	/**
	 * Record a completed sign-in, and the visit it is. A time only moves forward: one earlier than
	 * the stored sign-in writes nothing, so a late call can never put an account back in time.
	 *
	 * @param int $user_id The account.
	 * @param int $time    When; 0 for now.
	 */
	public static function record_sign_in( $user_id, $time = 0 ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || isset( self::$recorded[ $user_id ] ) ) {
			return;
		}

		self::$recorded[ $user_id ] = true;

		$time   = (int) $time > 0 ? (int) $time : time();
		$stored = get_user_meta( $user_id, self::META_SIGN_IN, true );

		if ( ! is_numeric( $stored ) || (int) $stored < $time ) {
			update_user_meta( $user_id, self::META_SIGN_IN, $time );
		}

		self::record_visit( $user_id, $time );
	}

	/**
	 * Whether the account has a recorded sign-in: a time above zero, as the seed reads one. An
	 * account the seed could not place, or whose key holds anything but a time, reads as never
	 * signed in. A visit alone is not a sign-in.
	 *
	 * @param int $user_id The account.
	 * @return bool
	 */
	public static function has_signed_in( $user_id ) {
		$stored = get_user_meta( (int) $user_id, self::META_SIGN_IN, true );

		return is_numeric( $stored ) && (int) $stored > 0;
	}

	/**
	 * A signed-in page view, front end (`template_redirect`) or wp-admin (`admin_init`).
	 */
	public static function on_visit() {
		$user_id = (int) get_current_user_id();

		if ( $user_id <= 0 || ! self::counts_as_visit() ) {
			return;
		}

		self::record_visit( $user_id, time() );
	}

	/**
	 * Write the visit when the stored one is from an earlier site day, or is not a time at all. A
	 * time earlier than the stored one is never written, so a visit only moves forward.
	 *
	 * @param int $user_id The account.
	 * @param int $time    When.
	 */
	public static function record_visit( $user_id, $time ) {
		$stored = get_user_meta( (int) $user_id, self::META_VISIT, true );
		$last   = is_numeric( $stored ) ? (int) $stored : 0;

		if ( $last > 0 && ( (int) $time < $last || wp_date( 'Y-m-d', $last ) === wp_date( 'Y-m-d', (int) $time ) ) ) {
			return;
		}

		update_user_meta( (int) $user_id, self::META_VISIT, (int) $time );
	}

	/**
	 * Give every account the sign-in the site can already tell, once, on an Administrator's first
	 * wp-admin page after the update (the owner's decision D3 of 15 October 2026).
	 *
	 * The version is written before the seed runs, as `WPCPM_Administrators_Dashboard::
	 * maybe_rename_page()` writes its own: a seed that failed halfway must not run again on every
	 * request of every Administrator. The accounts it missed keep no sign-in until their next one.
	 */
	public static function maybe_seed() {
		if ( (int) get_option( self::OPT_VERSION ) >= self::SCHEMA_VERSION || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
		update_option( self::OPT_SINCE, time(), false );

		self::seed();
	}

	/**
	 * Every account without a sign-in takes its latest trace; an account with one keeps it. No visit
	 * is written: visits start at the release.
	 *
	 * @return int How many accounts were given a sign-in.
	 */
	public static function seed() {
		$given = 0;
		$paged = 1;
		$read  = 0;

		do {
			// Through `WPCPM_Roles::id_of()`: on this program's site `get_users()` hands back row objects
			// whatever `fields` asks for, and `intval` would turn every one of them into 1.
			$ids = array_map(
				array( 'WPCPM_Roles', 'id_of' ),
				(array) get_users(
					array(
						'fields'  => 'ID',
						'number'  => self::SEED_BATCH,
						'paged'   => $paged,
						'orderby' => 'ID',
						'order'   => 'ASC',
					)
				)
			);

			if ( $ids ) {
				update_meta_cache( 'user', $ids );
			}

			foreach ( $ids as $user_id ) {
				$stored = get_user_meta( $user_id, self::META_SIGN_IN, true );

				if ( is_numeric( $stored ) && (int) $stored > 0 ) {
					continue;
				}

				$latest = self::latest_trace( $user_id );

				if ( $latest > 0 ) {
					update_user_meta( $user_id, self::META_SIGN_IN, $latest );
					++$given;
				}
			}

			$read = count( $ids );
			++$paged;
		} while ( self::SEED_BATCH === $read );

		return $given;
	}

	/**
	 * The latest sign-in the site can tell for an account: the `login` time of each session
	 * WordPress still holds, Two Factor's last accepted app code, and its last emailed code (sent at
	 * the password step, so an approximation). Anything that is not a number reads as 0.
	 *
	 * @param int $user_id The account.
	 * @return int Unix time, or 0.
	 */
	public static function latest_trace( $user_id ) {
		$times  = array( 0 );
		$tokens = get_user_meta( (int) $user_id, 'session_tokens', true );

		foreach ( is_array( $tokens ) ? $tokens : array() as $token ) {
			if ( is_array( $token ) && isset( $token['login'] ) && is_numeric( $token['login'] ) ) {
				$times[] = (int) $token['login'];
			}
		}

		foreach ( array( self::TRACE_APP, self::TRACE_CODE ) as $key ) {
			$value = get_user_meta( (int) $user_id, $key, true );

			if ( is_numeric( $value ) ) {
				$times[] = (int) $value;
			}
		}

		return max( $times );
	}

	/**
	 * When the seed ran: sign-ins before it are approximate. 0 before it has run.
	 *
	 * @return int
	 */
	public static function since() {
		return (int) get_option( self::OPT_SINCE, 0 );
	}

	/**
	 * Forget which sign-ins this request recorded. Test-only: nothing in the plugin calls it.
	 */
	public static function for_tests() {
		self::$recorded = array();
	}

	/**
	 * Whether the account still owes Two Factor's code, as Two Factor itself decides it: through
	 * the providers the account has, which `WPCPM_Two_Factor::enabled_providers()` fills by role.
	 *
	 * @param int $user_id The account.
	 * @return bool
	 */
	private static function code_owed( $user_id ) {
		return WPCPM_Two_Factor::available() && Two_Factor_Core::is_user_using_two_factor( $user_id );
	}

	/**
	 * Whether this request is a person looking at a page.
	 *
	 * @return bool
	 */
	private static function counts_as_visit() {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		foreach ( array( 'REST_REQUEST', 'XMLRPC_REQUEST', 'WP_CLI' ) as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				return false;
			}
		}

		return true;
	}
}
