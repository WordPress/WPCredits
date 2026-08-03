<?php
/**
 * Builds WordPress.org profile photo URLs.
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a profiles.wordpress.org URL into an avatar image URL.
 *
 * Uses WordPress.org's official avatar redirect,
 * https://wordpress.org/grav-redirect.php?user=USERNAME&s=SIZE , which 302s to
 * the user's Gravatar (falling back to an identicon when the user has none).
 *
 * The URL is used directly as an <img> src, so the visitor's browser loads each
 * avatar in parallel — no server-side lookups, cron, or caching required.
 */
class CREDPRME_Avatars {

	const GRAV_REDIRECT = 'https://wordpress.org/grav-redirect.php';

	/**
	 * Build the avatar URL for a WordPress.org profile, at the requested size.
	 *
	 * @param string $profile_url A https://profiles.wordpress.org/{username}/ URL.
	 * @param int    $size        Desired square size in pixels.
	 * @return string Avatar image URL, or '' if the profile URL isn't usable.
	 */
	public static function get_url( $profile_url, $size = 160 ) {
		$username = self::username_from_url( $profile_url );
		if ( '' === $username ) {
			return '';
		}

		$size = max( 24, min( 512, (int) $size ) );

		return add_query_arg(
			array(
				'user' => $username,
				's'    => $size,
			),
			self::GRAV_REDIRECT
		);
	}

	/**
	 * Extract and sanitize the username from a profiles.wordpress.org URL.
	 *
	 * @param string $profile_url Profile URL.
	 * @return string Sanitized username, or '' if not a wordpress.org profile URL.
	 */
	private static function username_from_url( $profile_url ) {
		$profile_url = trim( (string) $profile_url );
		if ( '' === $profile_url ) {
			return '';
		}

		$host = wp_parse_url( $profile_url, PHP_URL_HOST );
		if ( ! $host || ! preg_match( '/(^|\.)wordpress\.org$/i', $host ) ) {
			return ''; // Only trust wordpress.org profile URLs.
		}

		$path  = (string) wp_parse_url( $profile_url, PHP_URL_PATH );
		$parts = array_values( array_filter( explode( '/', $path ) ) );
		if ( empty( $parts ) ) {
			return '';
		}

		// Usernames are alphanumeric with hyphens/underscores.
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $parts[0] );
	}
}
