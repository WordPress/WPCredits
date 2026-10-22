<?php
/**
 * The public feed of Approved sponsors and monthly application counts (1.111.0).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET /wp-json/wpcpm/v1/sponsors`: what the WPCredits Tracker and the Credits Monthly Report
 * read now that the Airtable Sponsors table is an archive nothing on the site writes or reads
 * any more (design of 23 September 2026, section 6; section 3 decision 8). Those two tools live
 * in other repositories and switch to this feed in their own releases, later; until then the
 * numbers they read from the archive simply stop moving.
 *
 * The answer carries only what the handbook already publishes about a sponsor - its name, logo,
 * website and that it is a sponsor - plus counts. It never carries a contact person, an address,
 * a note a manager wrote, a status other than Approved, or a sponsor below Approved: a Paused or
 * an In review company is a fact for this site's managers, not for a public script. Nor does it
 * list or count a test sponsor (1.123.18), which is never listed publicly, nor count an application
 * or a sponsor with a test name (1.123.23, `WPCPM_Sponsors_Index::is_test_name()`).
 *
 * The route is public (`permission_callback` is `__return_true`) because both readers are
 * anonymous scripts with no account here, the same reason the route is cached for fifteen
 * minutes and metered through `WPCPM_Ceiling` like every other public route in this plugin: a
 * burst of requests costs the site one `approved()` read and one read of the application posts,
 * not one per request. `WPCPM_Sponsors_Index::forget()` drops the cached answer the moment a
 * writer changes a sponsor or its logo, so the fifteen minutes are a ceiling on a quiet site,
 * never a delay on a sponsor a manager just changed. The application counts are not dropped
 * that way: a new application, or one marked as spam, reaches them within the fifteen minutes,
 * which a count read once a month does not notice.
 */
final class WPCPM_Sponsors_Feed {

	/** Registered under `wpcpm/v1`. */
	const ROUTE = '/sponsors';

	/** The built answer, held for fifteen minutes; dropped early by `WPCPM_Sponsors_Index::forget()`. */
	const TRANSIENT = 'wpcpm_sponsors_feed';

	/** This route's own name for `WPCPM_Form_Guard`'s per-address ceiling. */
	const CEILING = 'sponsors-feed';

	/**
	 * Hook the route.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Register the feed. Public, and taking no arguments: the answer is the same for everyone
	 * who asks in the same fifteen minutes.
	 */
	public static function register_route() {
		register_rest_route(
			'wpcpm/v1',
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_sponsors' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The feed: metered first, then the cached answer or a freshly built one.
	 *
	 * @param WP_REST_Request $request The request. Unused: the route reads no argument and
	 *                                  answers the same body to everyone.
	 * @return WP_REST_Response
	 */
	public static function rest_sponsors( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- the REST dispatcher always hands the callback a request; see the docblock.
		$key = WPCPM_Form_Guard::actor_key( self::CEILING );

		// Metered before anything is read, the same order every public route in this plugin
		// uses: a caller over its budget is refused before the site spends a query on it.
		if ( ! WPCPM_Ceiling::claim( $key, 60, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'wpcpm_feed_busy',
					'message' => __( 'Too many requests. Try again in an hour.', 'wpcredits-program-manager' ),
				),
				429
			);
		}

		$cached = get_transient( self::TRANSIENT );

		if ( ! is_array( $cached ) ) {
			$cached = self::build();

			set_transient( self::TRANSIENT, $cached, 15 * MINUTE_IN_SECONDS );
		}

		$response = new WP_REST_Response( $cached, 200 );
		$response->header( 'Cache-Control', 'public, max-age=900' );

		return $response;
	}

	/**
	 * The answer, built fresh from the store and the application posts.
	 *
	 * @return array `sponsors` (an array list), `applications` (`Y-m` => `received`, `approved`),
	 *               `generated` (`c`, when this was built).
	 */
	public static function build() {
		return array(
			'sponsors'     => self::sponsors(),
			'applications' => self::applications(),
			'generated'    => gmdate( 'c' ),
		);
	}

	/**
	 * The Approved sponsors, by name, holding nothing a sponsor or a manager gave in confidence.
	 *
	 * @return array[] Each `name`, `slug`, `website`, `logo` (an address or ''), `mentors` (a
	 *                  count), `since` (`Y-m`, or '' when the store holds no created date).
	 */
	private static function sponsors() {
		$out = array();

		foreach ( WPCPM_Sponsors_Index::approved() as $key => $row ) {
			// A test sponsor is never listed publicly (1.123.18).
			if ( WPCPM_Sponsors_Index::is_test( $row ) ) {
				continue;
			}

			$logo = WPCPM_Sponsors_Index::display_logo( $key );

			$out[] = array(
				'name'    => (string) $row['name'],
				'slug'    => (string) $row['slug'],
				'website' => (string) $row['website'],
				'logo'    => is_array( $logo ) ? (string) $logo['url'] : '',
				'mentors' => count( (array) $row['mentors'] ),
				'since'   => substr( (string) $row['created'], 0, 7 ),
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $out;
	}

	/**
	 * The last twenty-four months, current month included and every month zero-filled: a month
	 * with nothing is a key holding two zeros, never a missing key. `received` counts the
	 * application posts not in the spam state by the month of `post_date`; `approved` counts the
	 * store's Approved rows by the month of `created`.
	 *
	 * Neither counts a test (1.123.23). The Credits Monthly Report reads `received` as the month's
	 * enquiries and `approved` as its new sponsors, and a company named by the program's convention
	 * for a test, the word "TEST" first, is neither, in any state: an open or rejected application
	 * as much as an approved one, and a sponsor whose flag an Administrator cleared for a trial as
	 * much as one still flagged. `approved` counts the store's rows, not the applications, so it
	 * reads the row's own name. The list is the flag's alone, as every public surface's is.
	 *
	 * @return array<string, array{received: int, approved: int}>
	 */
	private static function applications() {
		$months = self::month_keys();
		$out    = array_fill_keys(
			$months,
			array(
				'received' => 0,
				'approved' => 0,
			)
		);

		// A test sponsor is counted nowhere public (1.123.18): neither among the approvals nor, by
		// the record an approval stamps on its application, among the enquiries; and nor is an
		// application or a sponsor with a test name (1.123.23).
		$tests = array_flip( WPCPM_Sponsors_Index::test_keys() );

		foreach ( self::application_posts( $months[0] ) as $post ) {
			// To the Credits Monthly Report `received` is the month's enquiries, and a row the spam
			// layer caught or a manager marked as spam was never one.
			if ( WPCPM_Sponsor_Application::STATE_SPAM === (string) get_post_meta( $post->ID, WPCPM_Sponsor_Application::META_STATE, true ) ) {
				continue;
			}

			if ( ! empty( $tests ) && isset( $tests[ (string) get_post_meta( $post->ID, WPCPM_Sponsor_Application::META_RECORD, true ) ] ) ) {
				continue;
			}

			if ( WPCPM_Sponsors_Index::is_test_name( WPCPM_Sponsor_Application::name_of_post( $post ) ) ) {
				continue;
			}

			$month = substr( (string) $post->post_date, 0, 7 );

			if ( isset( $out[ $month ] ) ) {
				++$out[ $month ]['received'];
			}
		}

		foreach ( WPCPM_Sponsors_Index::approved() as $row ) {
			if ( WPCPM_Sponsors_Index::is_test( $row ) || WPCPM_Sponsors_Index::is_test_name( (string) $row['name'] ) ) {
				continue;
			}

			$month = substr( (string) $row['created'], 0, 7 );

			if ( isset( $out[ $month ] ) ) {
				++$out[ $month ]['approved'];
			}
		}

		return $out;
	}

	/**
	 * Every application post that can fall in the twenty-four-month window `applications()`
	 * reports: `date_query` keeps a site running a long time from reading every application it
	 * has ever received to throw away all but two years of them on every call (the review of
	 * 1.111.0). The month is a `substr()` of `post_date` away once the objects are in hand.
	 *
	 * @param string $first_month The oldest `Y-m` key `applications()` built its answer with -
	 *                            passed in, not read again here, so the bound and the months
	 *                            reported both come from the one clock reading (the review of
	 *                            1.111.0).
	 * @return WP_Post[]
	 */
	private static function application_posts( $first_month ) {
		return get_posts(
			array(
				'post_type'        => WPCPM_Sponsor_Application::POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'suppress_filters' => true,
				// The bound matches the months this feed reports: the oldest one, at its first day.
				'date_query'       => array(
					array(
						'after'     => $first_month . '-01 00:00:00',
						'inclusive' => true,
					),
				),
			)
		);
	}

	/**
	 * The twenty-four `Y-m` keys ending with the current month on the site's clock.
	 *
	 * `wp_date()`, not `gmdate()`: `post_date` and `created` are both
	 * site-local, and for a few hours at every month's turn UTC's month is not the site's, so a
	 * window ending with UTC's month would leave out the site's newest one, or count an old one.
	 *
	 * @return string[]
	 */
	private static function month_keys() {
		$year  = (int) wp_date( 'Y' );
		$month = (int) wp_date( 'n' );
		$keys  = array();

		for ( $i = 23; $i >= 0; $i-- ) {
			$m = $month - $i;
			$y = $year;

			while ( $m < 1 ) {
				$m += 12;
				--$y;
			}

			$keys[] = sprintf( '%04d-%02d', $y, $m );
		}

		return $keys;
	}
}
