<?php
/**
 * A meta query, read the way WordPress reads the ones the plugin's screens ask, for every stand-in
 * user query: bin/stubs/accounts-screen.php's, which the screen suites share,
 * bin/test-accounts-table.php's own and bin/test-institutions-screen.php's. One reading, so a fix to
 * how WordPress is modeled reaches all three, and one pin, in bin/test-accounts-table.php.
 *
 * It reads the fixture meta (`$GLOBALS['umeta']`, user ID => key => value) and notes anything it is
 * asked that it does not model in `$GLOBALS['unmodeled']`, which each suite's last check reads.
 *
 * Loaded with `require_once __DIR__ . '/meta-matcher.php';` from bin/stubs/accounts-screen.php, and
 * with `require_once __DIR__ . '/stubs/meta-matcher.php';` from bin/test-accounts-table.php and
 * bin/test-institutions-screen.php.
 */

/**
 * Whether an account meets a meta query: its clauses joined by its relation, AND unless it names OR,
 * as WordPress joins them, each `EXISTS` or `NOT EXISTS` on whether the key is there, and `=`, the
 * compare WordPress reads when a clause names none, on one key holding the clause's value, compared
 * as strings with the clause's trimmed, as WordPress compares it (the reconciliation's live flag);
 * the database's collation would also match a value in another case, which no query here asks.
 *
 * A key given as a list under `EXISTS` is any of them, as WordPress reads it (`meta_key IN (...)`),
 * the way the Invited view asks. WordPress binds one key in the join a `NOT EXISTS` clause makes, so
 * a list there is not modeled; nor is `=` without a value, with a list of values or of keys, nor a
 * clause that is itself a query, nested.
 *
 * @param int   $id      User ID.
 * @param array $clauses The meta query.
 * @return bool
 */
function wpcpm_stub_meta_matches( $id, array $clauses ) {
	$relation = isset( $clauses['relation'] ) ? strtoupper( (string) $clauses['relation'] ) : 'AND';

	if ( 'AND' !== $relation && 'OR' !== $relation ) {
		$GLOBALS['unmodeled'][] = 'meta_query relation ' . $clauses['relation'];
		$relation               = 'AND';
	}

	$asked = 0;
	$met   = 0;

	foreach ( $clauses as $name => $clause ) {
		if ( 'relation' === $name ) {
			continue;
		}

		if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
			$GLOBALS['unmodeled'][] = 'meta_query clause without a key';
			continue;
		}

		$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';

		if ( is_array( $clause['key'] ) && 'EXISTS' !== $compare ) {
			$GLOBALS['unmodeled'][] = 'meta_query key list under ' . $compare;
		}

		$present = false;

		foreach ( (array) $clause['key'] as $key ) {
			if ( isset( $GLOBALS['umeta'][ $id ] ) && array_key_exists( (string) $key, $GLOBALS['umeta'][ $id ] ) ) {
				$present = true;
				break;
			}
		}

		if ( 'EXISTS' === $compare ) {
			$meets = $present;
		} elseif ( 'NOT EXISTS' === $compare ) {
			$meets = ! $present;
		} elseif ( '=' === $compare && is_scalar( $clause['key'] ) && isset( $clause['value'] ) && is_scalar( $clause['value'] ) ) {
			$held  = $present ? $GLOBALS['umeta'][ $id ][ (string) $clause['key'] ] : null;
			$meets = is_scalar( $held ) && (string) $held === trim( (string) $clause['value'] );
		} else {
			$GLOBALS['unmodeled'][] = 'meta_query compare ' . $compare;
			$meets                  = true;
		}

		++$asked;

		if ( $meets ) {
			++$met;
		}
	}

	// A query of no clauses narrows nothing, whichever relation it names.
	return 'OR' === $relation ? ( 0 === $asked || $met > 0 ) : $met === $asked;
}
