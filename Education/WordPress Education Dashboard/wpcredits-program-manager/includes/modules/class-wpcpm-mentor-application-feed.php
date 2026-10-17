<?php
/**
 * Mentors module - the public feed of mentor application counts (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET /wp-json/wpcpm/v1/mentor-applications`: how many people applied to mentor, were selected,
 * became mentors, were declined and lapsed, per calendar month since the first application, and how
 * many applications are open and how many selected and waiting for the course today (design of
 * 16 October 2026, section 3, decision 10). The Credits Monthly Report and the WPCredits Tracker read
 * it, in releases of their own, where they read the Airtable Mentors table's Status until now.
 *
 * **Counts, never people.** The answer holds month keys, numbers and the time it was built: no name,
 * address, username, reference or note, ever. The months come from `WPCPM_Mentor_Application::
 * month_counts_of()` over the rows the site holds and the counts deleted rows left behind
 * (`kept_counts()`), so retention never rewrites a month the feed has already shown.
 *
 * Public, as both readers are anonymous scripts, metered per address through `WPCPM_Ceiling` and
 * cached for fifteen minutes: a burst of requests costs the site one read of the rows, not one per
 * request. A new application reaches the counts within the fifteen minutes, which a figure read once
 * a month does not notice.
 */
final class WPCPM_Mentor_Application_Feed {

	/** Registered under `wpcpm/v1`. */
	const ROUTE = '/mentor-applications';

	/** The built answer, held for fifteen minutes. */
	const TRANSIENT = 'wpcpm_mentor_applications_feed';

	/** This route's own name for the per-address ceiling. */
	const CEILING = 'mentor-applications-feed';

	/** Requests one address may make in an hour. */
	const PER_HOUR = 60;

	/** How long an answer is kept and may be kept by a cache on the way, in seconds. */
	const CACHE_SECONDS = 900;

	/**
	 * Hook the route.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Register the feed: public, and taking no argument, since the answer is the same for everybody
	 * who asks in the same fifteen minutes.
	 */
	public static function register_route() {
		register_rest_route(
			'wpcpm/v1',
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_counts' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The feed: metered first, then the cached answer or a fresh one.
	 *
	 * @param WP_REST_Request $request The request. Unused: the route reads no argument.
	 * @return WP_REST_Response
	 */
	public static function rest_counts( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- the REST dispatcher always hands the callback a request; see the docblock.
		if ( ! WPCPM_Ceiling::claim( WPCPM_Form_Guard::actor_key( self::CEILING ), self::PER_HOUR, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'wpcpm_feed_busy',
					'message' => __( 'Too many requests. Try again in an hour.', 'wpcredits-program-manager' ),
				),
				429
			);
		}

		$answer = get_transient( self::TRANSIENT );

		if ( ! is_array( $answer ) ) {
			$answer = self::build();

			set_transient( self::TRANSIENT, $answer, self::CACHE_SECONDS );
		}

		$response = new WP_REST_Response( $answer, 200 );
		$response->header( 'Cache-Control', 'public, max-age=' . self::CACHE_SECONDS );

		return $response;
	}

	/**
	 * The answer, built fresh.
	 *
	 * @param int $now A Unix time, or 0 for now.
	 * @return array `months` (`Y-m` => `received`, `selected`, `activated`, `declined`, `lapsed`, an
	 *               object while there are none), `today` (`open`, `selected`), `generated` (`c`).
	 */
	public static function build( $now = 0 ) {
		$now    = $now > 0 ? (int) $now : time();
		$months = self::months( $now );

		return array(
			'months'    => empty( $months ) ? new stdClass() : $months,
			'today'     => array(
				'open'     => WPCPM_Mentor_Application::waiting_count(),
				'selected' => WPCPM_Mentor_Application::count_in( array( WPCPM_Mentor_Application::STATE_SELECTED ) ),
			),
			'generated' => gmdate( 'c', $now ),
		);
	}

	/**
	 * Every calendar month from the first one anything was counted in to this one, on the site's
	 * clock, each with its five counts, a month with nothing a month of zeros; none before the first
	 * application. A kept count is left out when it is not a row of counts, or sits under a key that is
	 * not a month of the year or comes after this month.
	 *
	 * @param int $now A Unix time.
	 * @return array<string, array{received: int, selected: int, activated: int, declined: int, lapsed: int}>
	 */
	private static function months( $now ) {
		// Only an array can be a month's row of counts: anything else under a key would stop the build
		// when a live row counts in the same month.
		$counted = array_filter( WPCPM_Mentor_Application::kept_counts(), 'is_array' );

		foreach ( WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() ) as $post ) {
			foreach ( WPCPM_Mentor_Application::month_counts_of( $post ) as $month => $row ) {
				foreach ( $row as $key => $count ) {
					$counted[ $month ][ $key ] = ( isset( $counted[ $month ][ $key ] ) ? (int) $counted[ $month ][ $key ] : 0 ) + (int) $count;
				}
			}
		}

		// The walk is over a whole number for each month, from the first key that is a month of the year
		// to this month, so that nothing the option holds can keep it going: a key such as 2026-13 is no
		// month, and a key after this month is ignored, as no writer stamps a time that has not come.
		$last  = self::month_index( wp_date( 'Y-m', $now ) );
		$known = array();

		foreach ( array_keys( $counted ) as $month ) {
			$at = self::month_index( $month );

			if ( $at >= 0 && $at <= $last ) {
				$known[] = $at;
			}
		}

		if ( empty( $known ) ) {
			return array();
		}

		$out  = array();
		$zero = array_fill_keys( array( 'received', 'selected', 'activated', 'declined', 'lapsed' ), 0 );

		for ( $at = min( $known ); $at <= $last; $at++ ) {
			$month         = sprintf( '%04d-%02d', intdiv( $at, 12 ), $at % 12 + 1 );
			$out[ $month ] = array_merge( $zero, array_intersect_key( isset( $counted[ $month ] ) ? array_map( 'intval', (array) $counted[ $month ] ) : array(), $zero ) );
		}

		return $out;
	}

	/**
	 * A month's place in the line of months: its year times twelve, plus its month counted from zero.
	 * The walk counts these places and never compares written keys, whose order is not that of the
	 * months once the year passes 9999.
	 *
	 * @param mixed $month A key such as `2027-03`.
	 * @return int The place, or -1 for anything that is not a month of the year written that way.
	 */
	private static function month_index( $month ) {
		if ( 1 !== preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', (string) $month, $found ) ) {
			return -1;
		}

		return (int) $found[1] * 12 + (int) $found[2] - 1;
	}
}
