<?php
/**
 * `WP_User_Query` as the syncs' count check asks it (1.123.58): `count_total` over a meta query, and
 * nothing else.
 *
 * `WPCPM_Mentors_Sync::linked_active()` and `WPCPM_Students_Sync::linked_active()` count the accounts
 * that hold the record id meta and the active meta 1, and read `get_total()` alone. This answers over
 * the suite's own `$GLOBALS['umeta']` (user ID => key => value), the shape every suite that runs a
 * sync keeps its account meta in: an account holds a clause when its meta has the key, for an `EXISTS`
 * clause, or the key at the value, for any other. Every query's arguments are kept in
 * `$GLOBALS['user_counts']`, so a suite can read the query's shape.
 *
 * Loaded with `require_once __DIR__ . '/stubs/user-count-query.php';` from a suite's header.
 */

$GLOBALS['user_counts'] = array();

class WP_User_Query {
	private $total = 0;
	public function __construct( array $args ) {
		$GLOBALS['user_counts'][] = $args;

		foreach ( $GLOBALS['umeta'] as $meta ) {
			foreach ( isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() as $key => $clause ) {
				if ( 'relation' === $key ) {
					continue;
				}
				if ( ! is_array( $meta ) || ! array_key_exists( $clause['key'], $meta ) ) {
					continue 2;
				}
				if ( ( ! isset( $clause['compare'] ) || 'EXISTS' !== $clause['compare'] ) && (string) $clause['value'] !== (string) $meta[ $clause['key'] ] ) {
					continue 2;
				}
			}

			++$this->total;
		}
	}
	public function get_total() {
		return $this->total;
	}
}
