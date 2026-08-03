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
