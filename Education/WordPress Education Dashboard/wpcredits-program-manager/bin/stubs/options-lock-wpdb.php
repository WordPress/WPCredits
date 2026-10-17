<?php
/**
 * A wpdb for the suites, as far as WPCPM_Lock reaches it: the options table each suite already keeps
 * in `$GLOBALS['opts']`, and the four statements the lock sends, each run as one statement, as the
 * database runs it.
 *
 * - `INSERT IGNORE INTO wp_options (option_name, option_value, autoload) VALUES (...)`: writes the
 *   row only when no row of the name is there, and answers 1, or 0 when one is.
 * - `UPDATE wp_options SET option_value = ... WHERE option_name = ... AND CAST( option_value AS
 *   UNSIGNED ) <= <time>`: rewrites the row only when the number its value starts with is that time
 *   or earlier, a value that starts with no number reading as 0, as MySQL's and SQLite's CAST read
 *   one; answers 1, or 0. In a strict session (`$strict`) a value the CAST has to cut short fails
 *   the statement, as MySQL's error 1292 does.
 * - `DELETE FROM wp_options WHERE option_name = ... AND option_value = ...`: deletes the row only
 *   while it holds that value; answers 1, or 0.
 * - `SELECT option_value FROM wp_options WHERE option_name = ...`, through `get_var()`: the value, or
 *   null.
 *
 * Each statement is kept as it was sent (`$statements`), with the autoload each row was written with
 * (`$autoload`); any other statement is kept in `$unmodeled` and answers false, so nothing passes by
 * being ignored. A check can play another request in the moment before one of the lock's writes
 * (`$before_takeover`, run once, before the first write that follows an INSERT IGNORE that wrote
 * nothing: the moment a request knows the row is there and has not yet written), and can record the
 * order of what a send does (`$GLOBALS['events']`, when it is an array).
 *
 * Loaded with `require_once __DIR__ . '/stubs/options-lock-wpdb.php';` from a suite's header, after
 * its `$GLOBALS['opts']` is set; it installs itself as `$GLOBALS['wpdb']` when the suite has none.
 */
class WPCPM_Test_Options_Wpdb {

	public $prefix  = 'wp_';
	public $options = 'wp_options';

	/** @var string[] Every statement sent, as sent. */
	public $statements = array();

	/** @var string[] Every statement this stand-in does not model. */
	public $unmodeled = array();

	/** @var string[] What a statement failed with, as the database would say it. */
	public $errors = array();

	/** @var array<string, string> Option name => the autoload its row was written with. */
	public $autoload = array();

	/** @var callable|null Another request, played once in the moment before a take-over's write. */
	public $before_takeover = null;

	/**
	 * @var bool Whether the session runs in one of MySQL's strict modes, as a db.php drop-in or the
	 *           `incompatible_sql_modes` filter can leave it: a CAST that has to cut a value short is
	 *           then error 1292, and the statement fails, where core's own session only warns.
	 */
	public $strict = false;

	/** @var bool Whether the last INSERT IGNORE wrote nothing, so the next write is a take-over's. */
	private $insert_missed = false;

	/**
	 * Core's `prepare()` for %s and %d: a string quoted as MySQL reads it, a number as a number.
	 *
	 * @param string $query The statement with its placeholders.
	 * @param mixed  ...$args The values, in order.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$i = 0;

		return preg_replace_callback(
			'/%[sd]/',
			function ( $m ) use ( &$i, $args ) {
				$value = $args[ $i++ ] ?? '';

				return '%d' === $m[0] ? (string) (int) $value : "'" . addslashes( (string) $value ) . "'";
			},
			$query
		);
	}

	/**
	 * Run one statement.
	 *
	 * @param string $sql The statement.
	 * @return int|false Rows affected, or false for a statement not modeled.
	 */
	public function query( $sql ) {
		$sql = (string) $sql;
		$q   = "'((?:[^'\\\\]|\\\\.)*)'";

		if ( preg_match( '/^INSERT IGNORE INTO ' . $this->options . ' \(option_name, option_value, autoload\) VALUES \(' . $q . ', ' . $q . ', ' . $q . '\)$/', $sql, $m ) ) {
			$this->sent( 'insert', $sql );
			$name = stripslashes( $m[1] );

			if ( array_key_exists( $name, $GLOBALS['opts'] ) ) {
				$this->insert_missed = true;
				return 0;
			}

			$this->insert_missed      = false;
			$GLOBALS['opts'][ $name ] = stripslashes( $m[2] );
			$this->autoload[ $name ]  = stripslashes( $m[3] );
			return 1;
		}

		if ( preg_match( '/^UPDATE ' . $this->options . ' SET option_value = ' . $q . ' WHERE option_name = ' . $q . ' AND CAST\( option_value AS UNSIGNED \) <= (\d+)$/', $sql, $m ) ) {
			$this->takeover();
			$this->sent( 'update', $sql );
			$name = stripslashes( $m[2] );

			if ( $this->strict && array_key_exists( $name, $GLOBALS['opts'] ) && 1 !== preg_match( '/^\d+$/', (string) $GLOBALS['opts'][ $name ] ) ) {
				$this->errors[] = 'Truncated incorrect INTEGER value: ' . $GLOBALS['opts'][ $name ];
				return false;
			}

			if ( ! array_key_exists( $name, $GLOBALS['opts'] ) || self::leading_number( $GLOBALS['opts'][ $name ] ) > (int) $m[3] ) {
				return 0;
			}

			$GLOBALS['opts'][ $name ] = stripslashes( $m[1] );
			return 1;
		}

		if ( preg_match( '/^DELETE FROM ' . $this->options . ' WHERE option_name = ' . $q . ' AND option_value = ' . $q . '$/', $sql, $m ) ) {
			$this->takeover();
			$this->sent( 'delete', $sql );
			$name = stripslashes( $m[1] );

			if ( ! array_key_exists( $name, $GLOBALS['opts'] ) || (string) $GLOBALS['opts'][ $name ] !== stripslashes( $m[2] ) ) {
				return 0;
			}

			unset( $GLOBALS['opts'][ $name ], $this->autoload[ $name ] );
			return 1;
		}

		$this->unmodeled[] = $sql;

		return false;
	}

	/**
	 * One value from one row: the lock's read of when a held lock runs out.
	 *
	 * @param string $sql The statement.
	 * @return string|null
	 */
	public function get_var( $sql ) {
		if ( preg_match( "/^SELECT option_value FROM " . $this->options . " WHERE option_name = '((?:[^'\\\\]|\\\\.)*)'$/", (string) $sql, $m ) ) {
			$this->sent( 'select', $sql );
			$name = stripslashes( $m[1] );

			return array_key_exists( $name, $GLOBALS['opts'] ) ? (string) $GLOBALS['opts'][ $name ] : null;
		}

		$this->unmodeled[] = (string) $sql;

		return null;
	}

	/**
	 * The number a value starts with, as MySQL's `CAST( ... AS UNSIGNED )` and SQLite's CAST read it:
	 * 0 for a value that starts with none.
	 *
	 * @param mixed $value The stored value.
	 * @return int
	 */
	public static function leading_number( $value ) {
		return preg_match( '/^\s*(\d+)/', (string) $value, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * Keep a statement, and its kind among a send's events when a check records them.
	 *
	 * @param string $kind insert, update, delete or select.
	 * @param string $sql  The statement.
	 */
	private function sent( $kind, $sql ) {
		$this->statements[] = $sql;

		if ( isset( $GLOBALS['events'] ) && is_array( $GLOBALS['events'] ) ) {
			$GLOBALS['events'][] = 'lock ' . $kind;
		}
	}

	/** Play the other request a check planted, once, in the moment before a take-over's write. */
	private function takeover() {
		if ( $this->insert_missed && is_callable( $this->before_takeover ) ) {
			$run                   = $this->before_takeover;
			$this->before_takeover = null;
			$this->insert_missed   = false;
			$run();
		}

		$this->insert_missed = false;
	}
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new WPCPM_Test_Options_Wpdb();
}
