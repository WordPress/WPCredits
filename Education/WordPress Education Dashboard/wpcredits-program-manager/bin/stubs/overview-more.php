<?php
/**
 * What the Institution Dashboard's Overview asks since 1.123.98 (`WPCPM_Institutions_Dashboard::only_rows()`, the rows
 * of students with no sign-in on record, placements that ended and are still open, students waiting to graduate and
 * students who finish soon), for a suite that holds no stand-in of its own: each piece only when the suite has not
 * declared it, so a suite that stands in for one keeps its own. The roster index and the sign-in record are the real
 * classes, read through the suite's own `get_option()` and `get_user_meta()`; the accounts' meta read
 * (`cache_users()`) is logged in `$GLOBALS['cache_users_calls']`, one entry per call.
 *
 * @package WPCreditsProgramManager
 */

// phpcs:disable

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $time = null, $zone = null ) { return gmdate( $format, null === $time ? time() : (int) $time ); }
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $id, $key = '', $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $key ] ?? ''; }
}

if ( ! function_exists( 'cache_users' ) ) {
	function cache_users( $ids ) { $GLOBALS['cache_users_calls'][] = array_values( (array) $ids ); }
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) { return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : false; }
}

if ( ! class_exists( 'WPCPM_Roles' ) ) {
	/** The one question the Overview's sign-in fact asks of an account (fix round 1, B2). */
	class WPCPM_Roles {
		const ROLE_STUDENT = 'wpcpm_student';
		public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, (array) ( $user->roles ?? array() ), true ); }
	}
}

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
		/** The settings' defaults (`WPCPM_Settings`): the statuses the program counts as active, and as past. */
		public static function tracked_statuses( $settings = null ) {
			$active = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
			$past   = array( 'Graduate', 'Dropped out' );
			return array( 'active' => $active, 'past' => $past, 'all' => array_merge( $active, $past ) );
		}
	}
}

if ( ! class_exists( 'WPCPM_Cohort' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
}

if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
}

if ( ! class_exists( 'WPCPM_Sign_Ins' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
}

if ( ! class_exists( 'WPCPM_Program' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
}

if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	class WPCPM_Students_Sync {
		const META_PENDING = 'wpcpm_student_pending_since';
		const META_PROGRAM = 'wpcpm_student_program';
	}
}

/** One roster envelope as `WPCPM_Roster_Index` stores it, for `get_option()`. */
function wpcpm_test_roster( array $rows, $read = 0 ) {
	return array( 'v' => WPCPM_Roster_Index::VERSION, 'read' => (int) $read, 'rows' => $rows );
}
