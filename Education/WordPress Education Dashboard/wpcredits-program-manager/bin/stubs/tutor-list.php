<?php
/**
 * What a suite needs to load the tutor list (`WPCPM_Institution_Tutors`) beside the People card.
 *
 * The list is kept in the institution's record (`WPCPM_Institution_Record`, which asks `WPCPM_Airtable` whether an ID
 * is a record ID) and every write runs under the school's lock (`WPCPM_Lock`, named by
 * `WPCPM_Institution_Request::school_lock_name()`). The stand-ins are the real contracts and nothing more, and each
 * is defined only when the suite has not already: `$GLOBALS['lock_held']` names a lock another request holds, and
 * `$GLOBALS['locks']` records every lock taken, in order.
 *
 * Loaded with `require_once __DIR__ . '/stubs/tutor-list.php';` before the classes it stands behind.
 */

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	class WPCPM_Airtable {
		public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
	}
}

if ( ! class_exists( 'WPCPM_Lock' ) ) {
	class WPCPM_Lock {
		public static function take( $name, $ttl ) {
			if ( isset( $GLOBALS['lock_held'] ) && $GLOBALS['lock_held'] === $name ) {
				return '';
			}

			$GLOBALS['locks'][] = $name;

			return 'token-' . count( $GLOBALS['locks'] );
		}
		public static function release( $name, $token ) { $GLOBALS['released'][] = $name; return true; }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Request' ) ) {
	class WPCPM_Institution_Request {
		const LOCK_TTL = 300;
		public static function school_lock_name( $institution ) { return 'wpcpm_lock_details_' . trim( (string) $institution ); }
	}
}
