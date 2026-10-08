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
 * of white space one space; and every percent octet removed. One thing of core's is left out: in
 * the stretch it escapes, core also writes a numeric entity typed out with fewer than three digits
 * with three (`&#62;` as `&#062;`). No typing in a suite that loads this holds one.
 *
 * @param string $str           What was posted.
 * @param bool   $keep_newlines Whether the field is a text area.
 * @return string
 */
function wpcpm_test_clean( $str, $keep_newlines ) {
	$filtered = (string) $str;

	if ( ! mb_check_encoding( $filtered, 'UTF-8' ) ) {
		return '';
	}

	if ( false !== strpos( $filtered, '<' ) ) {
		$filtered = preg_replace_callback(
			'%<[^>]*?((?=<)|>|$)%',
			function ( $matches ) {
				return false === strpos( $matches[0], '>' ) ? htmlspecialchars( $matches[0], ENT_QUOTES, 'UTF-8', false ) : $matches[0];
			},
			$filtered
		);
		$filtered = trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $filtered ) ) );
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}

	if ( ! $keep_newlines ) {
		$filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );
	}

	$filtered = trim( $filtered );
	$found    = false;

	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
		$found    = true;
	}

	if ( $found ) {
		$filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
	}

	return $filtered;
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
