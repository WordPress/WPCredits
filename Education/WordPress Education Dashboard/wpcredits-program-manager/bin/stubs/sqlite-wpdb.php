<?php
/**
 * A wpdb for the suites, backed by an in-memory SQLite database (PDO).
 *
 * Models the part of core's wpdb the mail log uses: `prefix`, `prepare()` with %s, %d, %f and %%,
 * `esc_like()`, `insert()`, `query()`, `get_results()`, `get_var()`, `get_row()`, `get_col()`,
 * `get_charset_collate()`, `suppress_errors()`, `insert_id` and `last_error`. A suite creates the
 * table in SQLite's dialect (`WPCPM_Test_Wpdb::create_mail_log()`); the plugin's MySQL schema is
 * pinned as text by the suite instead.
 *
 * Two statements are rewritten on the way in, as WordPress's SQLite integration (3.x, the one the
 * local copies run) rewrites them: `SHOW TABLES LIKE 'name'` is answered from sqlite_master,
 * because SQLite has no SHOW, and every `LIKE '<literal>'` gains ` ESCAPE '\'`, because MySQL reads
 * a backslash in a LIKE pattern as its escape and SQLite reads none unless told. A pattern escaped
 * with `esc_like()` therefore means here what it means on the live site.
 *
 * Loaded with `require __DIR__ . '/stubs/sqlite-wpdb.php';` from a suite's header.
 */
class WPCPM_Test_Wpdb {

	public $prefix     = 'wp_';
	public $insert_id  = 0;
	public $last_error = '';
	public $queries    = array();

	private $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT );
	}

	/** The mail log's table, in SQLite's dialect. */
	public function create_mail_log() {
		$this->pdo->exec(
			"CREATE TABLE {$this->prefix}wpcpm_mail_log ( id INTEGER PRIMARY KEY AUTOINCREMENT, sent_at TEXT NOT NULL, to_email TEXT NOT NULL DEFAULT '', user_id INTEGER DEFAULT NULL, to_name TEXT NOT NULL DEFAULT '', recipient_type TEXT NOT NULL DEFAULT '', module TEXT NOT NULL DEFAULT '', template TEXT NOT NULL DEFAULT '', subject TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT '', error TEXT NOT NULL DEFAULT '', is_test INTEGER NOT NULL DEFAULT 0, delivery TEXT DEFAULT NULL, delivery_at TEXT DEFAULT NULL )"
		);
	}

	/** Take the mail log's table away, as a site whose upgrade has not run has none. */
	public function drop_mail_log() {
		$this->pdo->exec( "DROP TABLE IF EXISTS {$this->prefix}wpcpm_mail_log" );
	}

	/** Every row of the mail log's table, every column, as stored. */
	public function mail_log_rows() {
		$st = $this->pdo->query( "SELECT * FROM {$this->prefix}wpcpm_mail_log ORDER BY id" );

		return false === $st ? array() : $st->fetchAll( PDO::FETCH_ASSOC );
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function suppress_errors( $suppress = true ) {
		return true;
	}

	/** Core's: LIKE's own characters, and the backslash, each escaped with a backslash. */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$i   = 0;
		$pdo = $this->pdo;

		return preg_replace_callback(
			'/%%|%[sdf]/',
			function ( $m ) use ( &$i, $args, $pdo ) {
				if ( '%%' === $m[0] ) {
					return '%';
				}

				$v = array_key_exists( $i, $args ) ? $args[ $i ] : '';
				++$i;

				if ( '%d' === $m[0] ) {
					return (string) (int) $v;
				}

				if ( '%f' === $m[0] ) {
					return (string) (float) $v;
				}

				return $pdo->quote( (string) $v );
			},
			(string) $query
		);
	}

	private function translate( $sql ) {
		if ( preg_match( "/^\s*SHOW TABLES LIKE '([^']+)'\s*$/i", $sql, $m ) ) {
			// The name as LIKE reads it once its escapes are read: `wp\_x` is `wp_x`.
			return "SELECT name FROM sqlite_master WHERE type = 'table' AND name = '" . preg_replace( '/\\\\(.)/', '$1', $m[1] ) . "'";
		}

		// As the SQLite integration 3.x does: every LIKE escapes with a backslash.
		return (string) preg_replace( "/(\\bLIKE\\s+'(?:[^']|'')*')/i", "$1 ESCAPE '\\\\'", (string) $sql );
	}

	private function run( $sql ) {
		$sql              = $this->translate( (string) $sql );
		$this->queries[]  = $sql;
		$this->last_error = '';
		$st               = $this->pdo->query( $sql );

		if ( false === $st ) {
			$info             = $this->pdo->errorInfo();
			$this->last_error = (string) ( $info[2] ?? 'error' );
		}

		return $st;
	}

	public function query( $sql ) {
		$st = $this->run( $sql );

		return false === $st ? false : $st->rowCount();
	}

	public function insert( $table, array $data, $format = null ) {
		$cols  = array_keys( $data );
		$marks = implode( ', ', array_fill( 0, count( $cols ), '?' ) );
		$sql   = 'INSERT INTO ' . $table . ' (' . implode( ', ', $cols ) . ') VALUES (' . $marks . ')';
		$st    = $this->pdo->prepare( $sql );

		$this->queries[]  = $sql;
		$this->last_error = '';

		if ( false === $st || false === $st->execute( array_values( $data ) ) ) {
			$info             = false === $st ? $this->pdo->errorInfo() : $st->errorInfo();
			$this->last_error = (string) ( $info[2] ?? 'error' );
			return false;
		}

		$this->insert_id = (int) $this->pdo->lastInsertId();

		return 1;
	}

	public function get_results( $sql, $output = 'OBJECT' ) {
		$st = $this->run( $sql );

		if ( false === $st ) {
			return array();
		}

		$rows = $st->fetchAll( PDO::FETCH_ASSOC );

		return 'ARRAY_A' === $output ? $rows : array_map( function ( $r ) { return (object) $r; }, $rows );
	}

	public function get_row( $sql, $output = 'OBJECT' ) {
		$rows = $this->get_results( $sql, $output );

		return isset( $rows[0] ) ? $rows[0] : null;
	}

	public function get_var( $sql ) {
		$st = $this->run( $sql );

		if ( false === $st ) {
			return null;
		}

		$v = $st->fetchColumn();

		return false === $v ? null : $v;
	}

	public function get_col( $sql ) {
		$st = $this->run( $sql );

		return false === $st ? array() : $st->fetchAll( PDO::FETCH_COLUMN );
	}
}
