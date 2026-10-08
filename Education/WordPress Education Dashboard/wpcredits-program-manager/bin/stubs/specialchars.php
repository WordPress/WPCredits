<?php
/**
 * Core's `wp_specialchars_decode()`, as 7.1.2 writes it, for the suites that load a class that
 * counts or draws a text with `WPCPM_Typed_Text`.
 *
 * It reads the five entities `&lt;`, `&gt;`, `&amp;`, `&quot;` and `&#039;`, with the numeric forms
 * core lists for each, back once, and no other entity. A stand-in built on `html_entity_decode()`
 * would read `&copy;` too, which core does not.
 *
 * Loaded with `require_once __DIR__ . '/stubs/specialchars.php';` from a suite's header.
 */

/**
 * Converts a number of special characters back to their entities' characters.
 *
 * @param string     $text        The text.
 * @param string|int $quote_style Which quote marks to read back.
 * @return string
 */
function wp_specialchars_decode( $text, $quote_style = ENT_NOQUOTES ) {
	$text = (string) $text;

	if ( '' === $text || false === strpos( $text, '&' ) ) {
		return $text;
	}

	if ( empty( $quote_style ) ) {
		$quote_style = ENT_NOQUOTES;
	} elseif ( ! in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) ) {
		$quote_style = ENT_QUOTES;
	}

	$single      = array( '&#039;' => '\'', '&#x27;' => '\'' );
	$single_preg = array( '/&#0*39;/' => '&#039;', '/&#x0*27;/i' => '&#x27;' );
	$double      = array( '&quot;' => '"', '&#034;' => '"', '&#x22;' => '"' );
	$double_preg = array( '/&#0*34;/' => '&#034;', '/&#x0*22;/i' => '&#x22;' );
	$others      = array( '&lt;' => '<', '&#060;' => '<', '&gt;' => '>', '&#062;' => '>', '&amp;' => '&', '&#038;' => '&', '&#x26;' => '&' );
	$others_preg = array( '/&#0*60;/' => '&#060;', '/&#0*62;/' => '&#062;', '/&#0*38;/' => '&#038;', '/&#x0*26;/i' => '&#x26;' );

	if ( ENT_QUOTES === $quote_style ) {
		$translation      = array_merge( $single, $double, $others );
		$translation_preg = array_merge( $single_preg, $double_preg, $others_preg );
	} elseif ( ENT_COMPAT === $quote_style || 'double' === $quote_style ) {
		$translation      = array_merge( $double, $others );
		$translation_preg = array_merge( $double_preg, $others_preg );
	} elseif ( 'single' === $quote_style ) {
		$translation      = array_merge( $single, $others );
		$translation_preg = array_merge( $single_preg, $others_preg );
	} else {
		$translation      = $others;
		$translation_preg = $others_preg;
	}

	$text = preg_replace( array_keys( $translation_preg ), array_values( $translation_preg ), $text );

	return strtr( $text, $translation );
}
