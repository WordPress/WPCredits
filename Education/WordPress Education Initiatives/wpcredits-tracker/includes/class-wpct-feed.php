<?php
/**
 * The sponsors feed of the WordPress Education Dashboard: the count of approved sponsors the
 * tracker shows comes from here since 1.5.0, not from the Airtable Sponsors table.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Reads the feed once per sync and answers the one number the tracker needs.
 */
class WPCT_Feed {

	/** Seconds to wait for the feed; it answers from an edge cache in well under one. */
	const TIMEOUT = 15;

	/**
	 * The feed's address: the saved one when it is an https URL, else the live default.
	 *
	 * @param array $settings The plugin's settings.
	 * @return string
	 */
	public static function url( array $settings ) {
		$saved = isset( $settings['feed_url'] ) ? trim( (string) $settings['feed_url'] ) : '';
		if ( '' !== $saved && self::is_acceptable( $saved ) ) {
			return $saved;
		}
		return WPCT_FEED_URL;
	}

	/**
	 * Whether an address may serve as the feed: https, and one WordPress itself would request.
	 *
	 * The one rule for the two places that ask it, the settings page at save time and url() at
	 * read time, so they cannot drift apart: a value one accepts and the other refuses would be
	 * saved and then silently replaced by the default.
	 *
	 * @param string $url The address.
	 * @return bool
	 */
	public static function is_acceptable( $url ) {
		$url = trim( (string) $url );
		return '' !== $url && 0 === strpos( $url, 'https://' ) && false !== wp_http_validate_url( $url );
	}

	/**
	 * Fetch and validate the feed.
	 *
	 * @param string $url The feed's address.
	 * @return array|WP_Error The decoded feed (`sponsors`, `applications`, `generated`), or an error
	 *                        coded `wpct_feed_http`, `wpct_feed_status` or `wpct_feed_shape`.
	 */
	public static function fetch( $url ) {
		// The safe transport rejects a redirect to a loopback/private/link-local address the
		// same way url() already rejects one as the initial address; the plain, unsafe variant
		// would follow such a redirect unvalidated. The size cap is belt-and-suspenders: the
		// real feed answers in a few kilobytes, so a truncated body just fails the shape check
		// below rather than reading an unbounded response into memory.
		$resp = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => self::TIMEOUT,
				'limit_response_size' => 1048576, // 1 MB.
				'headers'             => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			/* translators: %s: the transport's own error message. */
			return new WP_Error( 'wpct_feed_http', sprintf( __( 'The sponsors feed could not be reached: %s', 'wpcredits-tracker' ), $resp->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( 200 !== $code ) {
			/* translators: %d: the HTTP status code the feed answered with. */
			return new WP_Error( 'wpct_feed_status', sprintf( __( 'The sponsors feed answered with status %d.', 'wpcredits-tracker' ), $code ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $body ) || ! isset( $body['sponsors'] ) || ! is_array( $body['sponsors'] ) ) {
			return new WP_Error( 'wpct_feed_shape', __( 'The sponsors feed answered with something that is not the feed.', 'wpcredits-tracker' ) );
		}
		return $body;
	}

	/**
	 * How many sponsors the feed lists: every sponsor in it is approved, by the feed's own rule.
	 *
	 * @param array $feed The decoded feed.
	 * @return int
	 */
	public static function approved_count( array $feed ) {
		return isset( $feed['sponsors'] ) && is_array( $feed['sponsors'] ) ? count( $feed['sponsors'] ) : 0;
	}
}
