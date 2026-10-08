<?php
/**
 * Core's two text cleaners, `sanitize_text_field()` and `sanitize_textarea_field()`, as 7.1.2
 * writes them, for the suites that load a class which counts, draws or mails a typed text with
 * `WPCPM_Typed_Text`.
 *
 * A stand-in on `strip_tags()` alone writes no `&lt;` and keeps a line break in a one-line field,
 * so a count, a box or a mail that forgot the cleaner's entities would pass every check made with
 * it. The same function stands in the typed text suite (bin/test-typed-text.php) and the Offers
 * suite (bin/test-sponsor-offers.php), each compared there with core.
 *
 * Loaded with `require_once __DIR__ . '/stubs/cleaners.php';` from a suite's header, in place of
 * its own two cleaners.
 */

/**
 * Core's `_sanitize_text_fields()`, as 7.1.2 writes it, which both cleaners call: invalid UTF-8
 * read as nothing; a "<" with no ">" before the next "<" or the end escaped as `esc_html()` escapes
 * it, an entity it finds kept (`wp_pre_kses_less_than()`); every tag stripped and the ends trimmed
 * (`wp_strip_all_tags()`); a "<" a line feed follows written `&lt;`; for a one-line field every run
 * of white space one space; and every percent octet removed. The stretch it escapes is written as
 * `esc_html()` writes it, a reference typed out in it included (`wpcpm_test_escape()`).
 *
 * With `$remove` false, the three steps that take text away are switched off: invalid UTF-8 is kept
 * (each bad sequence as "?"), no tag is stripped, and no percent octet removed. Every other step
 * runs as before, so what it returns differs from the real cleaner's answer exactly when the real
 * cleaner took away more than white space. A suite reads a loss from that without reading any
 * entity.
 *
 * @param string $str           What was posted.
 * @param bool   $keep_newlines Whether the field is a text area.
 * @param bool   $remove        Whether the steps that take text away run. Default true.
 * @return string
 */
function wpcpm_test_clean( $str, $keep_newlines, $remove = true ) {
	$filtered = (string) $str;

	if ( ! mb_check_encoding( $filtered, 'UTF-8' ) ) {
		if ( $remove ) {
			return '';
		}

		$filtered = mb_scrub( $filtered, 'UTF-8' );
	}

	if ( false !== strpos( $filtered, '<' ) ) {
		$filtered = preg_replace_callback(
			'%<[^>]*?((?=<)|>|$)%',
			function ( $matches ) {
				return false === strpos( $matches[0], '>' ) ? wpcpm_test_escape( $matches[0] ) : $matches[0];
			},
			$filtered
		);
		$filtered = trim( $remove ? strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $filtered ) ) : $filtered );
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}

	if ( ! $keep_newlines ) {
		$filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );
	}

	$filtered = trim( $filtered );
	$found    = false;

	while ( $remove && preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
		$found    = true;
	}

	if ( $found ) {
		$filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
	}

	return $filtered;
}

/**
 * Core's `esc_html()` on a valid UTF-8 text, as 7.1.2 writes it: `wp_kses_normalize_entities()`,
 * then `htmlspecialchars()` with `$double_encode` false.
 *
 * A reference typed out in the stretch the cleaner escapes is written in core's form, not kept as
 * typed: a number core takes (`&#9;`, `&#0039;`) with three digits at least and no more zeros
 * (`&#009;`, `&#039;`), a hexadecimal one with a small "x" and no leading zeros (`&#X0041;` as
 * `&#x41;`), and one core does not take (`&#0;`, `&#1;`, `&#XD800;`) with its "&" written `&amp;`
 * and a hexadecimal "X" written small. Names are left to `htmlspecialchars()`, which keeps the
 * HTML 4.01 names and writes the "&" of any other as `&amp;`: core keeps the names in
 * `$allowedentitynames`, which are those same 252 and "apos", and `htmlspecialchars()` then writes
 * `&apos;` as `&amp;apos;` too. Measured equal to core's cleaners on every typing of a sweep of
 * typed-out references in a dozen places and 300,000 random ones.
 *
 * @param string $text A stretch from a "<" with no ">" after it.
 * @return string
 */
function wpcpm_test_escape( $text ) {
	$text = str_replace( '&', '&amp;', (string) $text );
	$text = preg_replace_callback(
		'/&amp;#(0*[1-9][0-9]{0,6});/',
		function ( $m ) {
			return wpcpm_test_valid_unicode( (int) $m[1] ) ? '&#' . str_pad( ltrim( $m[1], '0' ), 3, '0', STR_PAD_LEFT ) . ';' : '&amp;#' . $m[1] . ';';
		},
		$text
	);
	$text = preg_replace_callback(
		'/&amp;#[Xx](0*[1-9A-Fa-f][0-9A-Fa-f]{0,5});/',
		function ( $m ) {
			return wpcpm_test_valid_unicode( hexdec( $m[1] ) ) ? '&#x' . ltrim( $m[1], '0' ) . ';' : '&amp;#x' . $m[1] . ';';
		},
		$text
	);
	$text = preg_replace( '/&amp;([A-Za-z]{2,8}[0-9]{0,2});/', '&$1;', $text );

	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false );
}

/**
 * Core's `valid_unicode()`: a tab, a line feed, a carriage return, or a code point XML allows.
 *
 * @param int $i A code point.
 * @return bool
 */
function wpcpm_test_valid_unicode( $i ) {
	$i = (int) $i;

	return 0x9 === $i || 0xA === $i || 0xD === $i || ( 0x20 <= $i && $i <= 0xD7FF ) || ( 0xE000 <= $i && $i <= 0xFFFD ) || ( 0x10000 <= $i && $i <= 0x10FFFF );
}

/**
 * Core's one-line cleaner.
 *
 * @param string $str What was posted.
 * @return string
 */
function sanitize_text_field( $str ) {
	return wpcpm_test_clean( $str, false );
}

/**
 * Core's text area cleaner: the same, with the line breaks kept.
 *
 * @param string $str What was posted.
 * @return string
 */
function sanitize_textarea_field( $str ) {
	return wpcpm_test_clean( $str, true );
}
