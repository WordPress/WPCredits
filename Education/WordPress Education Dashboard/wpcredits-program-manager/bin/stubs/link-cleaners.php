<?php
/**
 * Core's `esc_url_raw()` and `wp_check_invalid_utf8()`, as 7.1.2 writes them, for the suites that
 * read a link as typed (`WPCPM_Request::posted_verbatim()`, which runs its value through
 * `wp_check_invalid_utf8()`) and store it through `esc_url_raw()`.
 *
 * A stand-in that hands the link back as it came cannot show what core does to one: `esc_url_raw()`
 * keeps a "%20" or the "%C3%B3" of an accented letter, and takes out a "%0A" or a "%0D", a line break
 * written as a code, wherever it stands; and `wp_check_invalid_utf8()` empties a text that is not
 * valid UTF-8. A check made on an identity stand-in passes whatever core would do.
 *
 * Copied from core 7.1.2 with these differences, none of which a link the suites post reaches: the
 * `clean_url` and `kses_allowed_protocols` filters are not run, as no suite adds one; the site's
 * charset is UTF-8; the warning core gives for a link the "%0A" step leaves empty is silenced, its
 * answer kept; and the newer PHP functions core calls are written with `strpos()`, `reset()` and a
 * loop, so the file loads on every PHP the plugin runs on. A link holding "[" or "]" is parsed with
 * the suite's own `wp_parse_url()`.
 *
 * Loaded with `require_once __DIR__ . '/stubs/link-cleaners.php';` from a suite's header, in place
 * of its own `esc_url_raw()` and `wp_check_invalid_utf8()`.
 */

/**
 * Core's `esc_url_raw()`: `esc_url()` in its 'db' context, which writes no entity.
 *
 * @param string        $url       The link.
 * @param string[]|null $protocols The schemes a link may have; null for core's list.
 * @return string
 */
function esc_url_raw( $url, $protocols = null ) {
	if ( '' === $url ) {
		return $url;
	}

	$url = str_replace( ' ', '%20', ltrim( $url ) );
	$url = preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );

	if ( '' === $url ) {
		return $url;
	}

	if ( 0 !== stripos( $url, 'mailto:' ) ) {
		$strip = array( '%0d', '%0a', '%0D', '%0A' );
		$url   = _deep_replace( $strip, $url );
	}

	$url = str_replace( ';//', '://', $url );

	// Core reads `$url[0]` here even when the line above left nothing, which warns and makes the
	// link "http://", a link the shape checks after it refuse. The answer is kept, the warning is
	// silenced.
	if ( false === strpos( $url, ':' ) && ! in_array( @$url[0], array( '/', '#', '?' ), true ) && // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Core reads the offset unguarded; the warning is not what the suites check.
		! preg_match( '/^[a-z0-9-]+?\.php/i', $url )
	) {
		$first  = is_array( $protocols ) ? reset( $protocols ) : null;
		$scheme = 'https' === $first ? 'https://' : 'http://';
		$url    = $scheme . $url;
	}

	if ( false !== strpos( $url, '[' ) || false !== strpos( $url, ']' ) ) {
		$parsed = wp_parse_url( $url );
		$front  = '';

		if ( isset( $parsed['scheme'] ) ) {
			$front .= $parsed['scheme'] . '://';
		} elseif ( '/' === $url[0] ) {
			$front .= '//';
		}

		if ( isset( $parsed['user'] ) ) {
			$front .= $parsed['user'];
		}

		if ( isset( $parsed['pass'] ) ) {
			$front .= ':' . $parsed['pass'];
		}

		if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) ) {
			$front .= '@';
		}

		if ( isset( $parsed['host'] ) ) {
			$front .= $parsed['host'];
		}

		if ( isset( $parsed['port'] ) ) {
			$front .= ':' . $parsed['port'];
		}

		$end_dirty = str_replace( $front, '', $url );
		$end_clean = str_replace( array( '[', ']' ), array( '%5B', '%5D' ), $end_dirty );
		$url       = str_replace( $end_dirty, $end_clean, $url );
	}

	if ( '/' === $url[0] ) {
		return $url;
	}

	if ( ! is_array( $protocols ) ) {
		$protocols = array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );
	}

	$good_protocol_url = wp_kses_bad_protocol( $url, $protocols );

	if ( strtolower( $good_protocol_url ) !== strtolower( $url ) ) {
		return '';
	}

	return $good_protocol_url;
}

/**
 * Core's `_deep_replace()`: each needle taken out until none is left.
 *
 * @param string|string[] $search  What to take out.
 * @param string          $subject The text.
 * @return string
 */
function _deep_replace( $search, $subject ) {
	$subject = (string) $subject;

	$count = 1;
	while ( $count ) {
		$subject = str_replace( $search, '', $subject, $count );
	}

	return $subject;
}

/**
 * Core's `wp_kses_bad_protocol()`: a link whose scheme is not allowed, written in any disguise,
 * loses it.
 *
 * @param string   $content           The link.
 * @param string[] $allowed_protocols The schemes allowed.
 * @return string
 */
function wp_kses_bad_protocol( $content, $allowed_protocols ) {
	$content = wp_kses_no_null( $content );

	if (
		( 0 === strpos( $content, 'https://' ) && in_array( 'https', $allowed_protocols, true ) ) ||
		( 0 === strpos( $content, 'http://' ) && in_array( 'http', $allowed_protocols, true ) )
	) {
		return $content;
	}

	$iterations = 0;

	do {
		$original_content = $content;
		$content          = wp_kses_bad_protocol_once( $content, $allowed_protocols );
	} while ( $original_content !== $content && ++$iterations < 6 );

	if ( $original_content !== $content ) {
		return '';
	}

	return $content;
}

/**
 * Core's `wp_kses_no_null()`: the control characters and a backslashed zero taken out.
 *
 * @param string     $content The text.
 * @param array|null $options Whether to take out a backslashed zero, as `slash_zero`.
 * @return string
 */
function wp_kses_no_null( $content, $options = null ) {
	if ( ! isset( $options['slash_zero'] ) ) {
		$options = array( 'slash_zero' => 'remove' );
	}

	$content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content );
	if ( 'remove' === $options['slash_zero'] ) {
		$content = preg_replace( '/\\\\+0+/', '', $content );
	}

	return $content;
}

/**
 * Core's `wp_kses_bad_protocol_once()`: one pass at the scheme.
 *
 * @param string   $content           The link.
 * @param string[] $allowed_protocols The schemes allowed.
 * @param int      $count             How deep a `feed:` has gone.
 * @return string
 */
function wp_kses_bad_protocol_once( $content, $allowed_protocols, $count = 1 ) {
	$content  = preg_replace( '/(&#0*58(?![;0-9])|&#x0*3a(?![;a-f0-9]))/i', '$1;', $content );
	$content2 = preg_split( '/:|&#0*58;|&#x0*3a;|&colon;/i', $content, 2 );

	if ( isset( $content2[1] ) && ! preg_match( '%/\?%', $content2[0] ) ) {
		$content  = trim( $content2[1] );
		$protocol = wp_kses_bad_protocol_once2( $content2[0], $allowed_protocols );
		if ( 'feed:' === $protocol ) {
			if ( $count > 2 ) {
				return '';
			}
			$content = wp_kses_bad_protocol_once( $content, $allowed_protocols, ++$count );
			if ( empty( $content ) ) {
				return $content;
			}
		}
		$content = $protocol . $content;
	}

	return $content;
}

/**
 * Core's `wp_kses_bad_protocol_once2()`: a scheme, read plain, if it is allowed.
 *
 * @param string   $scheme            The scheme as written.
 * @param string[] $allowed_protocols The schemes allowed.
 * @return string The scheme and its colon, or ''.
 */
function wp_kses_bad_protocol_once2( $scheme, $allowed_protocols ) {
	$scheme = preg_replace_callback( '/&#([0-9]+);/', static function ( $m ) { return chr( $m[1] ); }, $scheme );
	$scheme = preg_replace_callback( '/&#[Xx]([0-9A-Fa-f]+);/', static function ( $m ) { return chr( hexdec( $m[1] ) ); }, $scheme );
	$scheme = preg_replace( '/\s/', '', $scheme );
	$scheme = wp_kses_no_null( $scheme );
	$scheme = strtolower( $scheme );

	foreach ( (array) $allowed_protocols as $protocol ) {
		if ( strtolower( $protocol ) === $scheme ) {
			return "$scheme:";
		}
	}

	return '';
}

/**
 * Core's `wp_check_invalid_utf8()` on a site whose charset is UTF-8: a text that is not valid UTF-8
 * is emptied, or with `$strip` has each bad sequence written as U+FFFD.
 *
 * @param string $text  The text.
 * @param bool   $strip Whether to keep the text with its bad sequences replaced.
 * @return string
 */
function wp_check_invalid_utf8( $text, $strip = false ) {
	$text = (string) $text;

	if ( 0 === strlen( $text ) ) {
		return '';
	}

	if ( mb_check_encoding( $text, 'UTF-8' ) ) {
		return $text;
	}

	if ( ! $strip ) {
		return '';
	}

	$previous = mb_substitute_character();
	mb_substitute_character( 0xFFFD );
	$scrubbed = mb_scrub( $text, 'UTF-8' );
	mb_substitute_character( $previous );

	return $scrubbed;
}
