<?php
/**
 * A WordPress.org profile's translation activity, read from its whole contributions timeline.
 *
 * A port of fetch_polyglots_rows() and fetch_translation_stats() in the upstream builder
 * (scripts/build_dashboard.py in WordPress/WPCredits, "Count every page of each profile's
 * translation activity"). The profile page prints only the newest ten rows of the timeline,
 * every type mixed; the older rows load through admin-ajax as a visitor pages back. Summing
 * the page alone, which the sync did before 1.5.1, missed everything older.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Reads one profile and answers its Suggested / Translated / Reviewed string counts.
 *
 * PRIVACY: nothing here logs anything. A username beside a count or an error is per-student
 * data, so an answer says how the read went and never whose it was; the sync adds those up
 * into counts of profiles (WPCT_Sync::phase_scrape()). The place a call hands back holds the
 * profile's numeric id and nonce, and lives in the sync state only until the read is finished.
 */
class WPCT_Timeline {

	/** Where a profile lives; the username follows. */
	const PROFILE_URL = 'https://profiles.wordpress.org/';

	/** The timeline's endpoint, used unless the page names another one on the same host. */
	const AJAX_URL = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

	/** The endpoint's action. */
	const ACTION = 'wporg_p2_timeline';

	/** The one contribution type asked for: each type pages through its own history. */
	const FILTER = 'polyglots';

	/** Seconds to wait for one WordPress.org request: the profile fetch timeout the sync always had. */
	const TIMEOUT = 12;

	/** Timeline pages read for one profile before the rest is left out (ten rows to a page). */
	const MAX_PAGES = 100;

	/** Microseconds between two timeline pages, the upstream build's 0.2 seconds. */
	const PAUSE = 200000;

	/**
	 * One Polyglots row of the timeline, its title line captured. The page and the endpoint
	 * print rows with the same markup. The upstream pattern, unchanged.
	 */
	const ROW_RE = '/data-type="polyglots"[^>]*>.*?class="title-ln">(.*?)<\/div>/s';

	/** The activity a title line names. The upstream pattern, unchanged. */
	const ACTIVITY_RE = '/(Suggested|Translated|Reviewed) (\d+) strings?/';

	/**
	 * The translation counts of one profile.
	 *
	 * Each call makes at least one request and starts no further one once $deadline has
	 * passed: a long timeline is read across several sync steps instead of holding one step
	 * past its budget. A call that runs out of time answers `resume`, the place to hand back
	 * as $resume on the next call.
	 *
	 * @param string     $username WordPress.org username.
	 * @param int        $deadline Unix time after which no further request is started; 0 for none.
	 * @param array|null $resume   The `resume` value of an earlier call for the same profile.
	 * @return array Either `suggested`, `translated`, `reviewed`, `total` (ints) with `partial`
	 *               (the timeline could not be paged to its end: the profile page's own rows
	 *               were counted, or the pages read before the endpoint stopped answering the
	 *               page asked for), `failed` (the profile could not be fetched, counted as 0)
	 *               and `capped` (the timeline is longer than the page limit; the pages read
	 *               were counted), or `resume` alone when the deadline passed partway.
	 */
	public static function stats( $username, $deadline = 0, $resume = null ) {
		$none  = array( 'suggested' => 0, 'translated' => 0, 'reviewed' => 0 );
		$asked = false; // Whether this call has made a request yet.

		if ( self::is_place( $resume ) ) {
			$job = $resume;
			// The place has been through the options table since pager() checked its endpoint.
			$job['pager']['ajax'] = self::endpoint( (string) $job['pager']['ajax'] );
		} else {
			$html = self::get( self::PROFILE_URL . rawurlencode( $username ) . '/' );
			if ( false === $html || '' === trim( $html ) ) {
				return self::result( $none, 'failed' );
			}
			$page_one = self::counts( $html );
			$pager    = self::pager( $html );
			if ( ! $pager ) {
				// WordPress.org prints a pager (hidden when there is one page) for every timeline
				// that has rows, so only an empty timeline lacks one. Rows without a pager mean
				// the markup changed: count what the page shows and say it is not the whole.
				return self::result( $page_one, false !== strpos( $html, 'class="wp-p2-tlrow' ) ? 'partial' : '' );
			}
			$asked = true;
			$job   = array(
				'pager'     => $pager,
				'page'      => 1,
				'max_pages' => 1,
				'counts'    => $none,
				'page_one'  => $page_one,
			);
		}

		$limit = self::max_pages();
		$paged = false; // Whether this call has read a timeline page yet.
		while ( $job['page'] <= $job['max_pages'] ) {
			if ( $job['page'] > $limit ) {
				return self::result( $job['counts'], 'capped' );
			}
			if ( $asked && $deadline && time() >= $deadline ) {
				return array( 'resume' => $job );
			}
			if ( $paged ) {
				usleep( max( 0, (int) apply_filters( 'wpct_timeline_pause', self::PAUSE ) ) );
			}
			$data  = self::page( $job['pager'], $job['page'] );
			$asked = true;
			$paged = true;
			if ( false === $data ) {
				// The same fallback as upstream: the profile page's own rows, flagged.
				return self::result( $job['page_one'], 'partial' );
			}
			if ( isset( $data['page'] ) && (int) $data['page'] !== $job['page'] ) {
				// Asked for a page past the end, the endpoint answers its last page again, and
				// this page was within the count it announced: the endpoint has stopped honoring
				// the page asked for (a timeline shrinking between two requests a second apart
				// is the other, unlikely reading). Not counted twice, and said to be partial, so
				// a change at WordPress.org shows up as a jump in the tally, not as a quiet drop.
				return self::result( $job['counts'], 'partial' );
			}
			$read = self::counts( $data['html'] );
			foreach ( $read as $kind => $n ) {
				$job['counts'][ $kind ] += $n;
			}
			$job['max_pages'] = isset( $data['max_pages'] ) ? max( 1, (int) $data['max_pages'] ) : 1;
			++$job['page'];
		}

		return self::result( $job['counts'] );
	}

	/**
	 * Add up the Polyglots rows in a piece of timeline markup (a profile page, or one page of
	 * the endpoint's `html`). Rows of any other type are not read, whatever their text says.
	 *
	 * @param string $html Markup.
	 * @return array `suggested`, `translated`, `reviewed` (ints).
	 */
	public static function counts( $html ) {
		$out = array( 'suggested' => 0, 'translated' => 0, 'reviewed' => 0 );
		if ( ! preg_match_all( self::ROW_RE, (string) $html, $rows ) ) {
			return $out;
		}
		foreach ( $rows[1] as $title ) {
			if ( preg_match_all( self::ACTIVITY_RE, $title, $hits, PREG_SET_ORDER ) ) {
				foreach ( $hits as $hit ) {
					$out[ strtolower( $hit[1] ) ] += (int) $hit[2];
				}
			}
		}
		return $out;
	}

	/**
	 * Read the timeline pager out of a profile page: whose timeline, the nonce that opens it,
	 * and where to ask.
	 *
	 * @param string $html Profile page markup.
	 * @return array|false `user_id` (int), `nonce` and `ajax` (strings), or false without a pager.
	 */
	public static function pager( $html ) {
		// The element itself, not the bare attribute name: the page's own script names
		// `[data-tl-pager]` on every profile, a timeline or not.
		if ( ! preg_match( '/<nav[^>]*\bdata-tl-pager\b[^>]*>/i', (string) $html, $nav ) ) {
			return false;
		}
		if ( ! preg_match( '/data-user="(\d+)"/', $nav[0], $user ) || ! preg_match( '/data-nonce="([A-Za-z0-9]+)"/', $nav[0], $nonce ) ) {
			return false;
		}
		$ajax = preg_match( '/data-ajax="([^"]+)"/', $nav[0], $found ) ? $found[1] : '';
		return array(
			'user_id' => (int) $user[1],
			'nonce'   => $nonce[1],
			'ajax'    => self::endpoint( $ajax ),
		);
	}

	/**
	 * The page limit in force.
	 *
	 * @return int At least 1.
	 */
	public static function max_pages() {
		return max( 1, (int) apply_filters( 'wpct_timeline_max_pages', self::MAX_PAGES ) );
	}

	/**
	 * The endpoint a pager names, when it is a plain https address on profiles.wordpress.org;
	 * the fixed one otherwise. The value comes out of a fetched page, so it is never followed
	 * to another host, port or scheme.
	 *
	 * @param string $address The pager's `data-ajax`.
	 * @return string
	 */
	private static function endpoint( $address ) {
		$parts = wp_parse_url( $address );
		$plain = is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'] )
			&& 'https' === strtolower( $parts['scheme'] )
			&& 'profiles.wordpress.org' === strtolower( $parts['host'] )
			&& ! isset( $parts['port'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] )
			&& ! isset( $parts['query'] ) && ! isset( $parts['fragment'] );
		return $plain ? $address : self::AJAX_URL;
	}

	/**
	 * One page of the timeline, Polyglots rows only.
	 *
	 * @param array $pager What pager() read.
	 * @param int   $page  1-based page number.
	 * @return array|false The answer's `data` (`html`, `page`, `max_pages`, `total`, `has_next`),
	 *                     or false when the endpoint did not answer with rows to read.
	 */
	private static function page( array $pager, $page ) {
		$query = http_build_query(
			array(
				'action'  => self::ACTION,
				'user_id' => $pager['user_id'],
				'filter'  => self::FILTER,
				'page'    => $page,
				'nonce'   => $pager['nonce'],
			),
			'',
			'&'
		);
		$body  = self::get( $pager['ajax'] . '?' . $query, array( 'X-Requested-With' => 'XMLHttpRequest' ) );
		if ( false === $body ) {
			return false;
		}
		$answer = json_decode( $body, true );
		if ( ! is_array( $answer ) || empty( $answer['success'] ) || ! isset( $answer['data']['html'] ) || ! is_string( $answer['data']['html'] ) ) {
			return false;
		}
		return $answer['data'];
	}

	/**
	 * Whether a value is a place stats() handed back: a sync state written by another
	 * version, or by hand, must start the profile over instead of being read as one.
	 *
	 * @param mixed $resume The value given as $resume.
	 * @return bool
	 */
	private static function is_place( $resume ) {
		return is_array( $resume )
			&& isset( $resume['pager']['user_id'], $resume['pager']['nonce'], $resume['pager']['ajax'], $resume['page'], $resume['max_pages'] )
			&& isset( $resume['counts']['suggested'], $resume['counts']['translated'], $resume['counts']['reviewed'] )
			&& isset( $resume['page_one']['suggested'], $resume['page_one']['translated'], $resume['page_one']['reviewed'] );
	}

	/**
	 * GET a WordPress.org address.
	 *
	 * @param string $url     Absolute URL.
	 * @param array  $headers Extra request headers.
	 * @return string|false The body of a 200 answer, false otherwise.
	 */
	private static function get( $url, array $headers = array() ) {
		$args = array(
			'timeout'    => self::TIMEOUT,
			'user-agent' => 'WPCredits-Tracker/' . WPCT_VERSION . '; ' . home_url(),
		);
		if ( $headers ) {
			$args['headers'] = $headers;
		}
		$resp = wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return false;
		}
		return (string) wp_remote_retrieve_body( $resp );
	}

	/**
	 * Shape a final answer.
	 *
	 * @param array  $counts `suggested`, `translated`, `reviewed`.
	 * @param string $flag   'partial', 'failed', 'capped', or '' for a whole timeline.
	 * @return array
	 */
	private static function result( array $counts, $flag = '' ) {
		return array(
			'suggested'  => $counts['suggested'],
			'translated' => $counts['translated'],
			'reviewed'   => $counts['reviewed'],
			'total'      => $counts['suggested'] + $counts['translated'] + $counts['reviewed'],
			'partial'    => 'partial' === $flag,
			'failed'     => 'failed' === $flag,
			'capped'     => 'capped' === $flag,
		);
	}
}
