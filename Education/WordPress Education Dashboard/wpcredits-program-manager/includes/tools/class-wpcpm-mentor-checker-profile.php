<?php
/**
 * Reads a WordPress.org profile's contribution history and looks for a course completion.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether a WordPress.org profile records a given learn.wordpress.org course.
 *
 * A profile page renders only the first ten contribution-history entries; the rest
 * are paged in from an admin-ajax endpoint (`wporg_p2_timeline`) that is gated by a
 * nonce printed into the page markup. This class scrapes that nonce from page one
 * and then pages through the history until it finds the course or runs out of pages.
 */
class WPCPM_Mentor_Checker_Profile {

	const PROFILE_BASE  = 'https://profiles.wordpress.org/';
	const AJAX_ENDPOINT = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';
	const AJAX_ACTION   = 'wporg_p2_timeline';
	const CACHE_PREFIX  = 'wpcpm_checker_profile_';

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array|null $settings Optional settings override.
	 */
	public function __construct( $settings = null ) {
		$this->settings = is_array( $settings ) ? $settings : WPCPM_Mentor_Checker::config();
	}

	/**
	 * Reduce whatever the Airtable "WordPress profile" field holds to a username.
	 *
	 * The field is free text, so in practice it contains full profile URLs, URLs with
	 * a trailing `/profile/`, scheme-less URLs, `@handles`, bare usernames, and the
	 * occasional misspelled host. Everything is reduced to the last meaningful path
	 * segment.
	 *
	 * @param string $raw Raw field value.
	 * @return string Lower-cased username, or an empty string when nothing usable was found.
	 */
	public static function normalize_username( $raw ) {
		$value = trim( (string) $raw );
		if ( '' === $value ) {
			return '';
		}

		// Strip a leading @handle marker and any surrounding angle brackets.
		$value = trim( $value, " \t\n\r\0\x0B<>" );
		$value = ltrim( $value, '@' );

		// Drop the scheme, then any query string or fragment.
		$value = preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $value );
		$value = preg_split( '/[?#]/', $value )[0];

		if ( false !== strpos( $value, '/' ) ) {
			$parts = array_values( array_filter( explode( '/', $value ), 'strlen' ) );

			// The first segment is the host when it looks like a domain.
			if ( ! empty( $parts ) && false !== strpos( $parts[0], '.' ) ) {
				array_shift( $parts );
			}

			// Trailing BuddyPress components are not part of the username.
			$parts = array_values(
				array_diff(
					$parts,
					array( 'profile', 'profiles', 'activity', 'badges', 'notifications', 'messages' )
				)
			);

			$value = empty( $parts ) ? '' : end( $parts );
		}

		// WordPress.org usernames allow letters, numbers, dashes, dots and underscores.
		$value = preg_replace( '/[^A-Za-z0-9._\-]/', '', $value );

		return strtolower( $value );
	}

	/**
	 * Check a mentor's profile for the configured course.
	 *
	 * @param string $raw_profile Raw Airtable profile value (URL or username).
	 * @param bool   $use_cache   Whether a cached result may be returned.
	 * @return array {
	 *     @type bool   $completed  True when the course completion was found.
	 *     @type string $state      One of: completed, not_completed, no_username, not_found, error.
	 *     @type string $username   Normalized username ('' when it could not be derived).
	 *     @type string $message    Human-readable explanation.
	 *     @type int    $timestamp  Unix timestamp of the matching entry, 0 when unknown.
	 *     @type int    $pages      Number of history pages read.
	 *     @type bool   $cached     Whether the result came from the cache.
	 * }
	 */
	public function check( $raw_profile, $use_cache = true ) {
		$username = self::normalize_username( $raw_profile );

		if ( '' === $username ) {
			return $this->result(
				false,
				'no_username',
				'',
				__( 'No WordPress.org username could be read from the profile field.', 'wpcredits-program-manager' )
			);
		}

		$cache_key = $this->cache_key( $username );
		$ttl       = (int) $this->settings['profile_cache_ttl'];

		if ( $use_cache && $ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				return $cached;
			}
		}

		$result = $this->scan_profile( $username );

		// Only cache settled answers; transient failures should be retried.
		if ( $ttl > 0 && in_array( $result['state'], array( 'completed', 'not_completed', 'not_found' ), true ) ) {
			set_transient( $cache_key, $result, $ttl );
		}

		return $result;
	}

	/**
	 * Fetch and scan a profile's full contribution history.
	 *
	 * @param string $username WordPress.org username.
	 * @return array See check().
	 */
	private function scan_profile( $username ) {
		$profile_url = self::PROFILE_BASE . rawurlencode( $username ) . '/';

		$body = $this->get( $profile_url );

		if ( is_wp_error( $body ) ) {
			$state = ( 404 === (int) $body->get_error_data( 'status' ) ) ? 'not_found' : 'error';
			return $this->result(
				false,
				$state,
				$username,
				'not_found' === $state
					/* translators: %s: WordPress.org username. */
					? sprintf( __( 'No WordPress.org profile exists at profiles.wordpress.org/%s.', 'wpcredits-program-manager' ), $username )
					: $body->get_error_message()
			);
		}

		$pages = 1;

		$match = $this->find_match( $body );
		if ( false !== $match ) {
			return $this->result( true, 'completed', $username, __( 'Course completion found in the profile history.', 'wpcredits-program-manager' ), $match, $pages );
		}

		$pager = $this->parse_pager( $body );
		if ( ! $pager ) {
			// Short histories render without a paginator, so page one was everything.
			return $this->result( false, 'not_completed', $username, $this->not_found_message(), 0, $pages );
		}

		$max_pages = max( 1, (int) $this->settings['max_pages'] );
		$filter    = 'all' === $this->settings['timeline_filter'] ? 'all' : 'meta';

		for ( $page = 1; $page <= $max_pages; $page++ ) {
			$data = $this->get_timeline_page( $pager['user_id'], $pager['nonce'], $filter, $page );

			if ( is_wp_error( $data ) ) {
				return $this->result( false, 'error', $username, $data->get_error_message(), 0, $pages );
			}

			++$pages;

			$html  = isset( $data['html'] ) ? (string) $data['html'] : '';
			$match = $this->find_match( $html );

			if ( false !== $match ) {
				return $this->result( true, 'completed', $username, __( 'Course completion found in the profile history.', 'wpcredits-program-manager' ), $match, $pages );
			}

			if ( empty( $data['has_next'] ) ) {
				return $this->result( false, 'not_completed', $username, $this->not_found_message(), 0, $pages );
			}
		}

		// The history was longer than the configured page cap, so "not completed"
		// would be a guess. Report it as incomplete instead of a negative.
		return $this->result(
			false,
			'error',
			$username,
			sprintf(
				/* translators: %d: configured maximum number of history pages. */
				__( 'Stopped after the %d-page limit without reaching the end of the history. Raise "Maximum history pages per mentor" and re-check.', 'wpcredits-program-manager' ),
				$max_pages
			),
			0,
			$pages
		);
	}

	/**
	 * Look for a history entry that records the configured course completion.
	 *
	 * The history is a flat list of `wp-p2-tlrow` blocks. Both the completion phrase
	 * and the course identity have to appear inside the *same* block, so that a
	 * mentor who merely blogged about the course is not counted as having taken it.
	 *
	 * @param string $html History markup.
	 * @return int|false Unix timestamp of the matching entry (0 if absent), or false when there is no match.
	 */
	private function find_match( $html ) {
		if ( '' === trim( (string) $html ) || false === strpos( $html, 'wp-p2-tlrow' ) ) {
			return false;
		}

		$rows = preg_split( '/(?=<div class="wp-p2-tlrow)/', $html );

		foreach ( $rows as $row ) {
			if ( ! $this->row_has_phrase( $row ) || ! $this->row_has_course( $row ) ) {
				continue;
			}

			if ( preg_match( '/data-ts="(\d+)"/', $row, $ts ) ) {
				return (int) $ts[1];
			}

			return 0;
		}

		return false;
	}

	/**
	 * Whether a history entry carries the completion phrase.
	 *
	 * @param string $row Entry markup.
	 * @return bool
	 */
	private function row_has_phrase( $row ) {
		$phrase = trim( (string) $this->settings['completion_phrase'] );
		if ( '' === $phrase ) {
			return true;
		}
		return false !== stripos( $this->plain_text( $row ), $this->plain_text( $phrase ) );
	}

	/**
	 * Whether a history entry points at the configured course.
	 *
	 * The course slug in the learn.wordpress.org link is the reliable signal; the
	 * course title is only a fallback for entries rendered without a link.
	 *
	 * @param string $row Entry markup.
	 * @return bool
	 */
	private function row_has_course( $row ) {
		$slug = trim( (string) $this->settings['course_slug'] );

		if ( '' !== $slug && preg_match( '#learn\.WordPress\.org/course/' . preg_quote( $slug, '#' ) . '(?![a-z0-9\-])#i', $row ) ) {
			return true;
		}

		$title = trim( (string) $this->settings['course_title'] );
		if ( '' !== $title && false !== stripos( $this->plain_text( $row ), $this->plain_text( $title ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Flatten markup to comparable plain text.
	 *
	 * Entities are decoded and curly quotes folded to straight ones, so a title typed
	 * with a straight apostrophe still matches the `&#8217;` in WordPress.org's markup.
	 *
	 * @param string $value Markup or text.
	 * @return string
	 */
	private function plain_text( $value ) {
		$text = wp_strip_all_tags( (string) $value );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace(
			array( "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xC2\xA0" ),
			array( "'", "'", '"', '"', ' ' ),
			$text
		);
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Read the paginator's user ID and nonce out of a profile page.
	 *
	 * @param string $html Profile page markup.
	 * @return array|false Array with user_id and nonce, or false when no paginator is present.
	 */
	private function parse_pager( $html ) {
		if ( ! preg_match( '/<nav[^>]*\bdata-tl-pager\b[^>]*>/i', $html, $nav ) ) {
			return false;
		}

		if ( ! preg_match( '/data-user="(\d+)"/', $nav[0], $user ) ) {
			return false;
		}
		if ( ! preg_match( '/data-nonce="([A-Za-z0-9]+)"/', $nav[0], $nonce ) ) {
			return false;
		}

		return array(
			'user_id' => (int) $user[1],
			'nonce'   => $nonce[1],
		);
	}

	/**
	 * Request one page of contribution history.
	 *
	 * @param int    $user_id WordPress.org user ID.
	 * @param string $nonce   Nonce scraped from the profile page.
	 * @param string $filter  History filter (meta or all).
	 * @param int    $page    1-based page number.
	 * @return array|WP_Error Response payload with html and has_next keys.
	 */
	private function get_timeline_page( $user_id, $nonce, $filter, $page ) {
		$url = add_query_arg(
			array(
				'action'  => self::AJAX_ACTION,
				'user_id' => $user_id,
				'filter'  => $filter,
				'page'    => $page,
				'nonce'   => $nonce,
			),
			self::AJAX_ENDPOINT
		);

		$body = $this->get( $url, array( 'X-Requested-With' => 'XMLHttpRequest' ) );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || empty( $decoded['success'] ) || ! isset( $decoded['data'] ) ) {
			return new WP_Error(
				'wpcpm_checker_timeline_error',
				__( 'WordPress.org rejected the contribution-history request. It may have changed; try the "All contributions" filter or re-run the check.', 'wpcredits-program-manager' )
			);
		}

		return is_array( $decoded['data'] ) ? $decoded['data'] : array();
	}

	/**
	 * Perform a GET request against WordPress.org.
	 *
	 * @param string $url     Absolute URL.
	 * @param array  $headers Extra request headers.
	 * @return string|WP_Error Response body.
	 */
	private function get( $url, array $headers = array() ) {
		$delay = (int) $this->settings['request_delay'];
		if ( $delay > 0 ) {
			usleep( $delay * 1000 );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => sprintf( 'WPCreditsProgramManager/%s; %s', WPCPM_VERSION, home_url( '/' ) ),
				'headers'    => array_merge( array( 'Accept' => '*/*' ), $headers ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'wpcpm_checker_http_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'WordPress.org returned HTTP %d.', 'wpcredits-program-manager' ),
					$code
				),
				array( 'status' => $code )
			);
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * The message used when a full history was read without a match.
	 *
	 * @return string
	 */
	private function not_found_message() {
		return sprintf(
			/* translators: %s: course title. */
			__( 'No "%s" completion in the profile history.', 'wpcredits-program-manager' ),
			$this->settings['course_title']
		);
	}

	/**
	 * Assemble a result array.
	 *
	 * @param bool   $completed Whether the course was found.
	 * @param string $state     Result state.
	 * @param string $username  Normalized username.
	 * @param string $message   Human-readable explanation.
	 * @param int    $timestamp Timestamp of the matching entry.
	 * @param int    $pages     Number of pages read.
	 * @return array
	 */
	private function result( $completed, $state, $username, $message, $timestamp = 0, $pages = 0 ) {
		return array(
			'completed' => (bool) $completed,
			'state'     => $state,
			'username'  => $username,
			'message'   => $message,
			'timestamp' => (int) $timestamp,
			'pages'     => (int) $pages,
			'cached'    => false,
		);
	}

	/**
	 * Cache key for a username, scoped to the course being looked for.
	 *
	 * @param string $username Normalized username.
	 * @return string
	 */
	private function cache_key( $username ) {
		return self::CACHE_PREFIX . md5(
			implode(
				'|',
				array(
					$username,
					$this->settings['course_slug'],
					$this->settings['course_title'],
					$this->settings['completion_phrase'],
					$this->settings['timeline_filter'],
				)
			)
		);
	}

	/**
	 * Delete every cached profile result.
	 */
	public static function flush_cache() {
		global $wpdb;

		$like         = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $like_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
