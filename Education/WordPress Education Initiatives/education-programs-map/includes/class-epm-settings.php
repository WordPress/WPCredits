<?php
/**
 * Stores and validates the plugin's global display settings (e.g. map size).
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Settings {

	const OPTION_NAME = 'epm_settings';

	/**
	 * Default values used until an admin saves the settings screen.
	 *
	 * @return array<string,string>
	 */
	public static function defaults() {
		return array(
			'width'  => '100%',
			'height' => '520px',
		);
	}

	/**
	 * Get the current settings, merged with defaults for any missing keys.
	 *
	 * @return array<string,string>
	 */
	public static function get() {
		$saved = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Validate a CSS dimension (e.g. "520px", "100%", "60vh").
	 *
	 * @param mixed $value Raw value to check.
	 * @return bool
	 */
	public static function is_valid_dimension( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^\d+(\.\d+)?(px|%|vh|vw|em|rem)$/', trim( $value ) );
	}
}
