<?php
/**
 * Manages the list of program types (WPCC, WPCredits, Student Club, and any
 * custom programs added by an administrator).
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Programs {

	const OPTION_NAME = 'epm_programs';

	/**
	 * Built-in programs, used until an admin customizes the list.
	 *
	 * @return array<string,string>
	 */
	public static function defaults() {
		return array(
			'wpcc'         => __( 'WPCC (WordPress Campus Connect)', 'education-programs-map' ),
			'wpcredits'    => __( 'WPCredits', 'education-programs-map' ),
			'student_club' => __( 'Student Club', 'education-programs-map' ),
		);
	}

	/**
	 * Marker colours for the three built-in programs, taken from the WordPress
	 * Education Initiatives theme palette (theme.json): "primary" (WordPress Blue),
	 * "purple", and "green". WPCC keeps the primary blue the map has always used.
	 *
	 * @return array<string,string>
	 */
	public static function default_colors() {
		return array(
			'wpcc'         => '#3858e9',
			'wpcredits'    => '#8a54d6',
			'student_club' => '#1a9e78',
		);
	}

	/**
	 * Remaining palette colours, handed out in order to any custom program an admin
	 * adds. Ordered strongest-first so the weakest colour on the map's very light
	 * basemap (the yellow) is the last one reached for.
	 *
	 * @return string[]
	 */
	public static function fallback_colors() {
		return array( '#34c19a', '#2e49d9', '#f6c445', '#6e6e6e' );
	}

	/**
	 * Get a colour for every current program, as key => hex.
	 *
	 * Built-in programs always keep their own colour; custom programs are assigned
	 * from the fallback palette in the order they appear, so a given program keeps
	 * the same colour from one page load to the next.
	 *
	 * @return array<string,string>
	 */
	public static function get_colors() {
		$defaults = self::default_colors();
		$fallback = self::fallback_colors();

		$colors = array();
		$next   = 0;

		foreach ( array_keys( self::get_all() ) as $key ) {
			if ( isset( $defaults[ $key ] ) ) {
				$colors[ $key ] = $defaults[ $key ];
				continue;
			}

			$colors[ $key ] = $fallback[ $next % count( $fallback ) ];
			++$next;
		}

		return $colors;
	}

	/**
	 * Get all programs as key => label pairs.
	 *
	 * @return array<string,string>
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_NAME );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return self::defaults();
		}

		return $saved;
	}

	/**
	 * Whether a program key currently exists.
	 *
	 * @param string $key Program key.
	 * @return bool
	 */
	public static function exists( $key ) {
		$programs = self::get_all();
		return isset( $programs[ $key ] );
	}

	/**
	 * Add a new program from an admin-supplied label, generating a unique key.
	 *
	 * @param string $label Human-readable program name.
	 * @return string|WP_Error The new program's key, or WP_Error on failure.
	 */
	public static function add( $label ) {
		$label = trim( $label );

		if ( '' === $label ) {
			return new WP_Error( 'epm_program_empty', __( 'Program name cannot be empty.', 'education-programs-map' ) );
		}

		$programs = self::get_all();

		$base_key = sanitize_key( sanitize_title( $label ) );
		if ( '' === $base_key ) {
			return new WP_Error( 'epm_program_invalid', __( 'Program name must contain at least one letter or number.', 'education-programs-map' ) );
		}

		$key   = $base_key;
		$index = 2;
		while ( isset( $programs[ $key ] ) ) {
			$key = $base_key . '-' . $index;
			++$index;
		}

		$programs[ $key ] = $label;
		update_option( self::OPTION_NAME, $programs );

		return $key;
	}

	/**
	 * Delete a program.
	 *
	 * @param string $key Program key.
	 * @return bool
	 */
	public static function delete( $key ) {
		$programs = self::get_all();

		if ( ! isset( $programs[ $key ] ) ) {
			return false;
		}

		unset( $programs[ $key ] );
		update_option( self::OPTION_NAME, $programs );

		return true;
	}
}
