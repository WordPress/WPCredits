<?php
/**
 * Reads the public details off a WordPress.org profile.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pulls a person's contact details from their WordPress.org profile.
 *
 * Used to fill the gaps Airtable does not carry. The Mentors table holds a name,
 * an email and a profile URL and nothing else, so a mentor's Slack handle, job
 * line, website and teams can only come from their profile.
 *
 * Everything is read from the profile hero - the block at the top of
 * `profiles.wordpress.org/<user>/`. Parsing HTML is a contract with someone
 * else's markup, so every field is independent: a change to one class costs that
 * one field and leaves the rest, and a profile that cannot be read at all yields
 * null rather than an error the caller has to handle.
 */
class WPCPM_WPorg_Profile {

	const BASE         = 'https://profiles.wordpress.org/';
	const CACHE_PREFIX = 'wpcpm_wporg_';
	const CACHE_TTL    = 12 * HOUR_IN_SECONDS;

	/**
	 * Read a profile.
	 *
	 * @param string $username  WordPress.org username.
	 * @param bool   $use_cache Whether a cached read may be returned.
	 * @return array|null {
	 *     @type string   $username Normalized username.
	 *     @type string   $url      Profile URL.
	 *     @type string   $name     Display name.
	 *     @type string   $handle   `@username` as the profile prints it.
	 *     @type string   $jobline  Role and employer, e.g. "Developer at Example".
	 *     @type string   $location Free-text location.
	 *     @type string   $joined   e.g. "joined Sep 2014".
	 *     @type string   $slack    Slack display name, without the "Slack: " prefix.
	 *     @type string   $website  Link to their site.
	 *     @type string   $website_label Domain as shown.
	 *     @type string   $github   GitHub URL.
	 *     @type string[] $teams    Contribution teams.
	 *     @type string   $avatar   Avatar URL.
	 * }
	 */
	public static function get( $username, $use_cache = true ) {
		$username = WPCPM_Mentors_Sync::wporg_username( $username );

		if ( '' === $username ) {
			return null;
		}

		$key = self::CACHE_PREFIX . md5( $username );

		if ( $use_cache ) {
			$cached = get_transient( $key );

			// An empty array is a cached "no profile", which is worth remembering
			// too - otherwise every sync retries every dead username.
			if ( is_array( $cached ) ) {
				return empty( $cached ) ? null : $cached;
			}
		}

		$body = self::fetch( $username );

		if ( is_wp_error( $body ) ) {
			// Not cached: a timeout should be retried, unlike a missing profile.
			return null;
		}

		$profile = self::parse( $username, (string) $body );

		set_transient( $key, null === $profile ? array() : $profile, self::CACHE_TTL );

		return $profile;
	}

	/**
	 * Request a profile page.
	 *
	 * @param string $username Normalized username.
	 * @return string|WP_Error Response body.
	 */
	private static function fetch( $username ) {
		$response = wp_remote_get(
			self::BASE . rawurlencode( $username ) . '/',
			array(
				'timeout'    => 15,
				'user-agent' => sprintf( 'WPCreditsProgramManager/%s; %s', WPCPM_VERSION, home_url( '/' ) ),
				'headers'    => array( 'Accept' => 'text/html' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'wpcpm_profile_http',
				sprintf(
					/* translators: 1: WordPress.org username, 2: HTTP status code. */
					__( 'Could not read the WordPress.org profile for %1$s (HTTP %2$d).', 'wpcredits-program-manager' ),
					$username,
					$code
				),
				array( 'status' => $code )
			);
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Pull the fields out of a profile page.
	 *
	 * @param string $username Normalized username.
	 * @param string $html     Page markup.
	 * @return array|null Null when the page carries no profile hero.
	 */
	public static function parse( $username, $html ) {
		$hero = self::hero( $html );

		if ( '' === $hero ) {
			return null;
		}

		$links = self::links( $hero );

		return array(
			'username'      => $username,
			'url'           => self::BASE . rawurlencode( $username ) . '/',
			'name'          => self::text( $hero, 'wp-p2-hero-name' ),
			'handle'        => self::text( $hero, 'wp-p2-handle' ),
			'jobline'       => self::text( $hero, 'wp-p2-jobline' ),
			'location'      => self::text( $hero, 'wp-p2-loc' ),
			'joined'        => self::text( $hero, 'wp-p2-joined' ),
			'slack'         => $links['slack'],
			'website'       => $links['website'],
			'website_label' => $links['website_label'],
			'github'        => $links['github'],
			'teams'         => self::chips( $hero, 'Teams' ),
			'avatar'        => self::avatar( $hero ),
		);
	}

	/**
	 * Isolate the profile hero.
	 *
	 * Scoping every other pattern to this block keeps them from matching the same
	 * class used elsewhere on the page.
	 *
	 * @param string $html Page markup.
	 * @return string
	 */
	private static function hero( $html ) {
		if ( preg_match( '#<header[^>]*\bwp-p2-hero\b.*?</header>#s', $html, $match ) ) {
			return $match[0];
		}

		return '';
	}

	/**
	 * The text content of the first element carrying a class.
	 *
	 * @param string $hero  Hero markup.
	 * @param string $selector Class name.
	 * @return string
	 */
	private static function text( $hero, $selector ) {
		// Both quote styles: the profile's own templates use double quotes, but
		// anything WordPress renders for it - `get_avatar()` in particular - emits
		// single ones, and a pattern that assumes double silently finds nothing.
		$pattern = '#<([a-z0-9]+)[^>]*\bclass=(["\'])[^"\']*\b' . preg_quote( $selector, '#' ) . '\b[^"\']*\2[^>]*>(.*?)</\1>#s';

		if ( ! preg_match( $pattern, $hero, $match ) ) {
			return '';
		}

		return self::plain( $match[3] );
	}

	/**
	 * Read one attribute off a tag, whichever quote style it uses.
	 *
	 * @param string $tag  Tag markup.
	 * @param string $name Attribute name.
	 * @return string
	 */
	private static function attr( $tag, $name ) {
		if ( ! preg_match( '#\b' . preg_quote( $name, '#' ) . '=(["\'])(.*?)\1#s', $tag, $match ) ) {
			return '';
		}

		return html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * The links row: website, GitHub and Slack.
	 *
	 * @param string $hero Hero markup.
	 * @return array{slack: string, website: string, website_label: string, github: string}
	 */
	private static function links( $hero ) {
		$out = array(
			'slack'         => '',
			'website'       => '',
			'website_label' => '',
			'github'        => '',
		);

		if ( ! preg_match( '#<div[^>]*\bwp-p2-links\b[^>]*>(.*?)</div>#s', $hero, $block ) ) {
			return $out;
		}

		if ( ! preg_match_all( '#<a\b([^>]*)>(.*?)</a>#s', $block[1], $anchors, PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $anchors as $anchor ) {
			$href  = self::attr( $anchor[1], 'href' );
			$label = self::plain( $anchor[2] );

			if ( '' === $href ) {
				continue;
			}

			// The Slack row is the only one identified by its text: the href is the
			// generic Making WordPress chat link, shared with nothing else here.
			if ( 0 === stripos( $label, 'slack:' ) ) {
				$out['slack'] = trim( substr( $label, strlen( 'slack:' ) ) );
				continue;
			}

			if ( false !== stripos( $href, 'github.com' ) ) {
				$out['github'] = $href;
				continue;
			}

			// Their own site is served through a redirect, so the href never carries
			// the domain - that is only in the link text.
			if ( false !== stripos( $href, 'website-redirect' ) && '' === $out['website'] ) {
				$out['website']       = $href;
				$out['website_label'] = $label;
			}
		}

		return $out;
	}

	/**
	 * The chips under a labeled row, such as Teams or Languages.
	 *
	 * @param string $hero  Hero markup.
	 * @param string $label Row label, matched case-insensitively.
	 * @return string[]
	 */
	private static function chips( $hero, $label ) {
		if ( ! preg_match_all( '#<div[^>]*\bwp-p2-chip-row\b[^>]*>(.*?)</div>#s', $hero, $rows, PREG_SET_ORDER ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$row_label = self::text( $row[0], 'wp-p2-chip-label' );

			if ( 0 !== strcasecmp( trim( $row_label ), $label ) ) {
				continue;
			}

			if ( ! preg_match_all( '#<span[^>]*\bwp-p2-chip\b(?![a-z-])[^>]*>(.*?)</span>#s', $row[1], $chips, PREG_SET_ORDER ) ) {
				return array();
			}

			$out = array();
			foreach ( $chips as $chip ) {
				$value = self::plain( $chip[1] );
				if ( '' !== $value ) {
					$out[] = $value;
				}
			}

			return $out;
		}

		return array();
	}

	/**
	 * The avatar URL.
	 *
	 * @param string $hero Hero markup.
	 * @return string
	 */
	private static function avatar( $hero ) {
		if ( ! preg_match( '#<img\b[^>]*>#i', $hero, $tag ) ) {
			return '';
		}

		$src = self::attr( $tag[0], 'src' );

		return ( false !== stripos( $src, 'gravatar.com' ) ) ? $src : '';
	}

	/**
	 * Flatten markup to trimmed plain text.
	 *
	 * Tags become spaces rather than nothing: the profile packs adjacent elements
	 * with no whitespace between them, and stripping tags outright would run two
	 * values together.
	 *
	 * @param string $value Markup.
	 * @return string
	 */
	private static function plain( $value ) {
		$text = preg_replace( '#<[^>]+>#', ' ', (string) $value );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );

		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Drop every cached profile read.
	 */
	public static function flush_cache() {
		global $wpdb;

		$like    = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		$timeout = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
