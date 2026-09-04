<?php
/**
 * One capability stand-in for the suites, and it looks at the capability.
 *
 * Fifty of the fifty-four suites used to stub `current_user_can()` as a boolean that never
 * read its argument, so a handler checking `read` or `edit_posts` instead of
 * `WPCPM_Roles::CAP_MANAGE` passed every suite that covered it. This stand-in keeps each
 * suite's own way of saying who the manager is (`$GLOBALS['manage']` as a flag or a list of
 * user IDs, `$GLOBALS['caps']`, `$GLOBALS['can_manage']`, `$GLOBALS['manager_can']`) and
 * grants that person exactly one capability, the program's own. Anything else a handler asks
 * for is refused unless the suite grants it by name in `$GLOBALS['grants'][ <user id> ]`.
 *
 * Loaded with `require_once __DIR__ . '/stubs/caps.php';` from a suite's header, in place of
 * its own two functions. A suite whose stand-in already reads the capability keeps its own.
 */

/**
 * Whether the suite's fixtures make this user a program manager.
 *
 * @param mixed $user User ID or object.
 * @return bool
 */
function wpcpm_stub_is_manager( $user ) {
	$id = is_object( $user ) ? (int) $user->ID : (int) $user;

	if ( isset( $GLOBALS['manage'] ) ) {
		if ( is_array( $GLOBALS['manage'] ) ) {
			return in_array( $id, $GLOBALS['manage'], true );
		}

		return (bool) $GLOBALS['manage'];
	}

	// `can_manage` first: the one suite that uses it also carries a `caps` list of another
	// meaning (the capabilities a role should hold), which must not read as "is a manager".
	foreach ( array( 'can_manage', 'manager_can', 'caps' ) as $key ) {
		if ( ! isset( $GLOBALS[ $key ] ) ) {
			continue;
		}

		if ( is_array( $GLOBALS[ $key ] ) ) {
			return ! empty( $GLOBALS[ $key ][ $id ] ) || in_array( 'wpcpm_manage_program', $GLOBALS[ $key ], true );
		}

		return (bool) $GLOBALS[ $key ];
	}

	return false;
}

function user_can( $user, $cap, ...$args ) {
	$id = is_object( $user ) ? (int) $user->ID : (int) $user;

	if ( isset( $GLOBALS['grants'][ $id ] ) && in_array( $cap, (array) $GLOBALS['grants'][ $id ], true ) ) {
		return true;
	}

	return 'wpcpm_manage_program' === $cap && wpcpm_stub_is_manager( $user );
}

function current_user_can( $cap, ...$args ) {
	// A suite that says "the current user can" with a flag and no user ID means the manager
	// capability, and only that one.
	// It wins over a `manage` list too: the suites that keep both use the flag for the current
	// request and the list for the users the code looks up by ID.
	if ( 'wpcpm_manage_program' === $cap && isset( $GLOBALS['caps'] ) && ! is_array( $GLOBALS['caps'] ) && ! isset( $GLOBALS['can_manage'] ) ) {
		return (bool) $GLOBALS['caps'];
	}

	return user_can( isset( $GLOBALS['uid'] ) ? $GLOBALS['uid'] : 0, $cap, ...$args );
}
