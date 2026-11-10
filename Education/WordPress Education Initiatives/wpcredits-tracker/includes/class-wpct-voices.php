<?php
/**
 * The students' quotes ("What students say").
 *
 * The plugin ships six, each shared with the student's consent (data/voices.json). An
 * Administrator can replace them on the WPCredits-Tracker admin screen; nothing is read from
 * Airtable, because a quote is published only when somebody has checked that the student agreed.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCT_Voices {

	const OPTION = 'wpct_voices';
	const MAX    = 12;

	/**
	 * The quotes the plugin ships with.
	 *
	 * @return array
	 */
	public static function defaults() {
		$file = WPCT_DIR . 'data/voices.json';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$decoded = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return self::clean( $decoded );
	}

	/**
	 * The quotes to show: the saved ones, or the shipped ones when none are saved.
	 *
	 * @return array Each array( q, who, country, cc, lang ).
	 */
	public static function get() {
		$saved = self::clean( get_option( self::OPTION, array() ) );
		return $saved ? $saved : self::defaults();
	}

	/**
	 * Whether the site has saved quotes of its own.
	 *
	 * @return bool
	 */
	public static function is_custom() {
		return array() !== self::clean( get_option( self::OPTION, array() ) );
	}

	/**
	 * Rows made safe and regular: a row without a quote is dropped, a country code is two
	 * capital letters or nothing, a language is a short tag or nothing, at most MAX rows.
	 * Used for what is saved, what is read back and what is shipped alike.
	 *
	 * @param mixed $rows Whatever was posted, stored or decoded.
	 * @return array
	 */
	public static function clean( $rows ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$q = self::text( $row, 'q', true );
			if ( '' === $q ) {
				continue;
			}
			$cc   = strtoupper( self::text( $row, 'cc' ) );
			$lang = strtolower( self::text( $row, 'lang' ) );
			$out[] = array(
				'q'       => $q,
				'who'     => self::text( $row, 'who' ),
				'country' => self::text( $row, 'country' ),
				'cc'      => preg_match( '/^[A-Z]{2}$/', $cc ) ? $cc : '',
				'lang'    => preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $lang ) ? $lang : '',
			);
			if ( count( $out ) >= self::MAX ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * One field of a row as plain text.
	 *
	 * @param array  $row       The row.
	 * @param string $key       The field.
	 * @param bool   $multiline Whether line breaks are kept.
	 * @return string
	 */
	private static function text( array $row, $key, $multiline = false ) {
		if ( ! isset( $row[ $key ] ) || ! is_string( $row[ $key ] ) ) {
			return '';
		}
		$value = $multiline ? sanitize_textarea_field( $row[ $key ] ) : sanitize_text_field( $row[ $key ] );
		return trim( $value );
	}

	/**
	 * The sanitizer the settings form saves through (WordPress hands it the values unslashed).
	 *
	 * @param mixed $input The posted rows.
	 * @return array
	 */
	public static function sanitize( $input ) {
		return self::clean( $input );
	}
}
