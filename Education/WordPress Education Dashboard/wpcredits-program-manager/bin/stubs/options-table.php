<?php
/**
 * The options table, for the one statement a lock reads it with: the row a lock is held as, past
 * every cache.
 *
 * The sponsor events' locks and the sponsor reports' read the row they are about to take straight
 * from the table (`$wpdb->get_var()`), since on a site with a persistent object cache a request's
 * own copy of the options can say a row is absent that another request has just written. Every
 * suite that takes one of those locks needs that read answered, and most of them never had a
 * database object at all. This answers it from `$GLOBALS['opts']`, the options each suite keeps,
 * as the database hands a value back: the row's value as text, or null when there is no row.
 * Whatever else is sent is answered null and kept in `$GLOBALS['unmodeled']`, where the suite keeps
 * that list, for a check to refuse.
 *
 * A suite whose own database object models other statements (the events, the reports, the Sponsors
 * screen) keeps it, and answers its `get_var()` through `wpcpm_test_option_row()`, so the read is
 * parsed in one place. This file sets the global only for a suite that has none.
 *
 * Loaded with `require_once __DIR__ . '/stubs/options-table.php';` from a suite's header.
 */

if ( ! function_exists( 'wpcpm_test_option_row' ) ) {
	/**
	 * The one read a lock sends, answered from the suite's options: the row's value, as the database
	 * hands it back, or null for no row and for any other statement.
	 *
	 * @param string $query The statement, as `prepare()` wrote it.
	 * @return string|null
	 */
	function wpcpm_test_option_row( $query ) {
		if ( 1 !== preg_match( "/^SELECT option_value FROM wp_options WHERE option_name = '((?:[^']|'')*)'$/", (string) $query, $match ) ) {
			if ( isset( $GLOBALS['unmodeled'] ) && is_array( $GLOBALS['unmodeled'] ) ) {
				$GLOBALS['unmodeled'][] = 'get_var: ' . $query;
			}

			return null;
		}

		$name = str_replace( "''", "'", $match[1] );

		if ( ! isset( $GLOBALS['opts'] ) || ! is_array( $GLOBALS['opts'] ) || ! array_key_exists( $name, $GLOBALS['opts'] ) ) {
			return null;
		}

		$value = $GLOBALS['opts'][ $name ];

		return is_scalar( $value ) ? (string) $value : serialize( $value );
	}
}

if ( ! class_exists( 'WPCPM_Test_Options_Table' ) ) {
	/**
	 * The database object of a suite that had none: the options table's name, `prepare()` quoting as
	 * SQL does, and the one read.
	 */
	class WPCPM_Test_Options_Table {
		public $options = 'wp_options';

		public function prepare( $query, ...$args ) {
			$next = 0;

			return preg_replace_callback(
				'/%[sd]/',
				static function ( $m ) use ( $args, &$next ) {
					$arg = isset( $args[ $next ] ) ? $args[ $next ] : '';
					++$next;

					return '%d' === $m[0] ? (string) (int) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
				},
				$query
			);
		}

		public function get_var( $query ) {
			return wpcpm_test_option_row( $query );
		}
	}
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new WPCPM_Test_Options_Table();
}
