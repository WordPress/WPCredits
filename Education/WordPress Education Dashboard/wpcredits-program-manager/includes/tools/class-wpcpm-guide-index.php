<?php
/**
 * The guide index's two tables: one row a passage, one row a term of a passage.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The store behind Need help?'s answers: the passages of the site's guides, its FAQ and the WordPress
 * Credits handbook, and the terms each holds, which is all the ranking (`WPCPM_Guide_Search`) reads.
 *
 * A source (`page:558`, `handbook:96111`) is written whole and replaced whole: `write()` deletes what
 * the source had, then writes its passages. Which sources there are and when to write them is
 * `WPCPM_Guide_Sources`' business; this class only stores. The state option records per source what
 * was written and when, so the daily run can tell which pages moved.
 *
 * The SQL runs on MariaDB, where the site lives, and on WordPress's SQLite integration, where its local
 * copies and the suites run: every value goes through `$wpdb->prepare()`, an IN list gets one placeholder
 * a value, and no statement uses MySQL's own syntax (no `INSERT IGNORE`, no multi-table `DELETE`). Only
 * `schema()`, which SQLite never reads, is MySQL's.
 */
final class WPCPM_Guide_Index {

	const SCHEMA_VERSION = 1;
	const OPT_VERSION    = 'wpcpm_guide_index_version';
	const OPT_STATE      = 'wpcpm_guide_index_state';

	/** The terms' column, in characters. A longer term is cut, on its way in and in a query alike. */
	const TERM_CHARS = 40;

	/** The most rows one INSERT of terms writes, and the most values one IN list holds. */
	const BATCH = 500;

	/** The longest address the passages' column holds. A longer one is not cut into another link. */
	const URL_CHARS = 500;

	/** SMALLINT UNSIGNED's ceiling, for `words` and `tf`. */
	const SMALL_MAX = 65535;

	/** The `source` column, in characters. */
	const SOURCE_CHARS = 64;

	/** The `kind` and `audience` columns, in characters. */
	const KEY_CHARS = 20;

	/** The most characters of a section title `headings()` gives. */
	const HEADING_CHARS = 80;

	/**
	 * The passages' table.
	 *
	 * @return string
	 */
	public static function chunks_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpcpm_guide_chunks';
	}

	/**
	 * The terms' table.
	 *
	 * @return string
	 */
	public static function terms_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpcpm_guide_terms';
	}

	/**
	 * Both tables as `dbDelta()` reads them: one column a line, two spaces after PRIMARY KEY, the two
	 * statements apart at their semicolons.
	 *
	 * The term compares byte for byte (utf8mb4_bin): under the site's accent-blind collation "cafe" and
	 * "café" are one key, and a passage holding both would have its terms refused as a duplicate.
	 *
	 * @return string
	 */
	public static function schema(): string {
		global $wpdb;

		$chunks  = self::chunks_table();
		$terms   = self::terms_table();
		$collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$chunks} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
source varchar(64) NOT NULL DEFAULT '',
kind varchar(20) NOT NULL DEFAULT '',
audience varchar(20) NOT NULL DEFAULT '',
post_id bigint(20) unsigned NOT NULL DEFAULT 0,
title varchar(255) NOT NULL DEFAULT '',
heading varchar(255) NOT NULL DEFAULT '',
url varchar(500) NOT NULL DEFAULT '',
body mediumtext NOT NULL,
words smallint(5) unsigned NOT NULL DEFAULT 0,
hash char(32) NOT NULL DEFAULT '',
indexed_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY source (source),
KEY hash (hash)
) {$collate};
CREATE TABLE {$terms} (
term varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
chunk_id bigint(20) unsigned NOT NULL DEFAULT 0,
tf smallint(5) unsigned NOT NULL DEFAULT 0,
PRIMARY KEY  (term,chunk_id),
KEY chunk_id (chunk_id)
) {$collate};";
	}

	/**
	 * Create or upgrade both tables, on `init`, as the mail log does: updates arrive by replacing files,
	 * so the activation hook cannot be relied on. The version is stamped only once both tables are
	 * there, so a creation that failed is tried again on the next load.
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::OPT_VERSION, 0 ) >= self::SCHEMA_VERSION ) {
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( self::schema() );

		if ( ! self::exists() ) {
			return;
		}

		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
	}

	/**
	 * Whether both tables are there.
	 *
	 * @return bool
	 */
	public static function exists(): bool {
		global $wpdb;

		foreach ( array( self::chunks_table(), self::terms_table() ) as $table ) {
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Tables of the plugin's own, asked for when the schema is checked.
				return false;
			}
		}

		return true;
	}

	/**
	 * Drop both tables, and forget the schema's version and the state with them, so an index that is
	 * gone is not read as built. For uninstall (`WPCPM_Guide_Sources::uninstall()`).
	 */
	public static function drop(): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::terms_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::chunks_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.

		delete_option( self::OPT_VERSION );
		delete_option( self::OPT_STATE );
	}

	/**
	 * Write one source whole: what it had is deleted, then each passage is written with its terms.
	 *
	 * A passage's `url` is the source's address with `#<anchor>` when the passage has one, through
	 * `esc_url_raw()`; a passage whose address does not survive, or would not fit its column, is not
	 * written (the plan's Ruling 7), nor one with no text. `hash` is md5 of the body, so the ranking can
	 * tell a passage two guides share (the Mentor guide carries the Student guide) and give it once.
	 *
	 * A write that fails is recorded as one: if the source's old rows cannot be deleted nothing new is
	 * written, a passage the database refuses is not counted, and either way the state's entry gets an
	 * `error` and an empty `modified`, so the next run (which skips a source whose `modified` is the
	 * page's) writes it again. A source key, kind or audience longer than its column is refused before
	 * any SQL, as MariaDB would refuse the row where SQLite takes it.
	 *
	 * A passage's terms are `WPCPM_Guide_Text::counts()` of the page's title and the heading path
	 * together, so the title's terms count twice in every passage of the page, as a heading's do (the
	 * plan's Ruling T2-1): the handbook's "Launch Your WordPress Credits Program" is the page that answers
	 * a university launching one, though none of its sections' headings says so. The stored `heading`
	 * stays the section's path. `words` is the passage's length in those terms, which is the length BM25
	 * must divide by for its term frequencies to mean what they say.
	 *
	 * @param string $source   The source's key, such as `page:558` or `handbook:96111`.
	 * @param array  $meta     `kind`, `audience`, `post_id`, `title`, `url` (the page's, no anchor), `modified`.
	 * @param array  $passages `WPCPM_Guide_Text::passages()`' rows: `heading`, `anchor`, `body`.
	 * @return int The passages written.
	 */
	public static function write( string $source, array $meta, array $passages ): int {
		global $wpdb;

		$kind     = sanitize_key( isset( $meta['kind'] ) ? (string) $meta['kind'] : '' );
		$audience = sanitize_key( isset( $meta['audience'] ) ? (string) $meta['audience'] : '' );
		$post_id  = absint( isset( $meta['post_id'] ) ? $meta['post_id'] : 0 );
		$title    = self::text( isset( $meta['title'] ) ? $meta['title'] : '', 255 );
		$page_url = isset( $meta['url'] ) ? (string) $meta['url'] : '';
		$modified = isset( $meta['modified'] ) ? (string) $meta['modified'] : '';
		$now      = current_time( 'mysql', true );
		$written  = 0;
		$entry    = array(
			'kind'     => $kind,
			'audience' => $audience,
			'post_id'  => $post_id,
			'title'    => $title,
		);

		if ( mb_strlen( $source, 'UTF-8' ) > self::SOURCE_CHARS || mb_strlen( $kind, 'UTF-8' ) > self::KEY_CHARS || mb_strlen( $audience, 'UTF-8' ) > self::KEY_CHARS ) {
			self::record( $source, $entry, '', 0, __( 'The source\'s key, kind or audience is too long for the index.', 'wpcredits-program-manager' ) );
			return 0;
		}

		if ( ! self::delete_rows( $source ) ) {
			self::record( $source, $entry, '', 0, self::write_error() );
			return 0;
		}

		$failed = false;

		foreach ( $passages as $passage ) {
			$body = self::text( isset( $passage['body'] ) ? $passage['body'] : '', 0 );

			if ( '' === trim( $body ) ) {
				continue;
			}

			$anchor = isset( $passage['anchor'] ) ? trim( (string) $passage['anchor'] ) : '';
			$url    = esc_url_raw( '' === $anchor ? $page_url : $page_url . '#' . $anchor );

			if ( '' === $url || mb_strlen( $url, 'UTF-8' ) > self::URL_CHARS ) {
				continue;
			}

			$heading = self::text( isset( $passage['heading'] ) ? trim( (string) $passage['heading'] ) : '', 0 );
			$counts  = self::cut_counts( WPCPM_Guide_Text::counts( self::trail( $title, $heading ), $body ) );

			$data = array(
				'source'     => $source,
				'kind'       => $kind,
				'audience'   => $audience,
				'post_id'    => $post_id,
				'title'      => $title,
				'heading'    => self::text( $heading, 255 ),
				'url'        => $url,
				'body'       => $body,
				'words'      => min( self::SMALL_MAX, array_sum( $counts ) ),
				'hash'       => md5( $body ),
				'indexed_at' => $now,
			);

			if ( false === $wpdb->insert( self::chunks_table(), $data, array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The table is ours.
				$failed = true;
				continue;
			}

			$chunk_id = (int) $wpdb->insert_id;

			if ( ! self::insert_terms( $chunk_id, $counts ) ) {
				// A passage without its terms would count in N and never be found: take it back.
				self::take_back( $chunk_id );
				$failed = true;
				continue;
			}

			++$written;
		}

		if ( $failed ) {
			self::record( $source, $entry, '', $written, self::write_error() );
		} else {
			self::record( $source, $entry, $modified, $written, '' );
		}

		return $written;
	}

	/**
	 * Store a source's entry in the state. With an error, `modified` is '' whatever the page's is.
	 *
	 * @param string $source   The source's key.
	 * @param array  $entry    `kind`, `audience`, `post_id`, `title`.
	 * @param string $modified The page's modified time, or ''.
	 * @param int    $passages The passages written.
	 * @param string $error    Why the write failed, or '' when it did not.
	 */
	private static function record( string $source, array $entry, string $modified, int $passages, string $error ): void {
		$entry['modified']   = '' === $error ? $modified : '';
		$entry['passages']   = $passages;
		$entry['indexed_at'] = time();

		if ( '' !== $error ) {
			$entry['error'] = $error;
		}

		$state                       = self::state();
		$state['sources'][ $source ] = $entry;
		update_option( self::OPT_STATE, $state, false );
	}

	/**
	 * The state's sentence for a write the database refused. Fixed, with nothing of the database's own
	 * message, which can name tables and values.
	 *
	 * @return string
	 */
	private static function write_error(): string {
		return __( 'The database refused a write. The next run writes this source again.', 'wpcredits-program-manager' );
	}

	/**
	 * Take a source out of the index: its passages, their terms, its entry in the state. When the rows
	 * cannot be deleted the entry stays, marked with an error and no `modified`, so the source is not
	 * forgotten while its passages are still read.
	 *
	 * @param string $source The source's key.
	 */
	public static function remove( string $source ): void {
		if ( ! self::delete_rows( $source ) ) {
			$sources = self::sources();
			$entry   = isset( $sources[ $source ] ) ? $sources[ $source ] : array(
				'kind'     => '',
				'audience' => '',
				'post_id'  => 0,
				'title'    => '',
			);
			self::record( $source, $entry, '', isset( $entry['passages'] ) ? (int) $entry['passages'] : 0, self::write_error() );
			return;
		}

		$state = self::state();

		if ( isset( $state['sources'][ $source ] ) ) {
			unset( $state['sources'][ $source ] );
			update_option( self::OPT_STATE, $state, false );
		}
	}

	/**
	 * The index's state: `sources` (per key: `kind`, `audience`, `post_id`, `title`, `modified`,
	 * `passages`, `indexed_at`) and, once a whole rebuild has run, `built_for`.
	 *
	 * @return array
	 */
	public static function state(): array {
		$state = get_option( self::OPT_STATE, array() );
		$state = is_array( $state ) ? $state : array();

		if ( ! isset( $state['sources'] ) || ! is_array( $state['sources'] ) ) {
			$state['sources'] = array();
		}

		return $state;
	}

	/**
	 * The sources the index holds, from the state.
	 *
	 * @return array<string, array>
	 */
	public static function sources(): array {
		return self::state()['sources'];
	}

	/**
	 * Record the plugin version a whole rebuild ran under, so a release that moves it schedules one.
	 *
	 * @param string $version The plugin's version.
	 */
	public static function mark_built( string $version ): void {
		$state              = self::state();
		$state['built_for'] = $version;
		update_option( self::OPT_STATE, $state, false );
	}

	/**
	 * The passages in the index.
	 *
	 * @return int
	 */
	public static function count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::chunks_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- No value in it; the table is ours and changes on every write.
	}

	/**
	 * What BM25 needs of the whole index: the passages and their average length in terms.
	 *
	 * @return array{count: int, avg: float}
	 */
	public static function stats(): array {
		global $wpdb;

		$row = $wpdb->get_row( 'SELECT COUNT(*) AS n, AVG(words) AS avg FROM ' . self::chunks_table(), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- No value in it; the table is ours and changes on every write.

		return array(
			'count' => is_array( $row ) ? (int) $row['n'] : 0,
			'avg'   => is_array( $row ) ? (float) $row['avg'] : 0.0,
		);
	}

	/**
	 * How many passages of the whole index hold each term, whoever may read them.
	 *
	 * @param string[] $terms Terms.
	 * @return array<string, int> Each term asked (cut to the column), 0 where no passage has it.
	 */
	public static function df( array $terms ): array {
		global $wpdb;

		$table = self::terms_table();
		$terms = self::cut_terms( $terms );
		$df    = array_fill_keys( $terms, 0 );

		foreach ( array_chunk( $terms, self::BATCH ) as $batch ) {
			$in = implode( ', ', array_fill( 0, count( $batch ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per term, built above; the table is ours.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT term, COUNT(*) AS n FROM {$table} WHERE term IN ($in) GROUP BY term", $batch ), ARRAY_A );

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( array_key_exists( (string) $row['term'], $df ) ) {
					$df[ (string) $row['term'] ] = (int) $row['n'];
				}
			}
		}

		return $df;
	}

	/**
	 * The term rows of the passages the audiences may read, each with what the ranking needs of its
	 * passage, less the sources left out. Ordered by passage, then term.
	 *
	 * @param string[] $terms     Terms.
	 * @param string[] $audiences Audiences, such as `public` and `student`.
	 * @param string[] $exclude   Source keys whose passages are left out, such as `page:2746`.
	 * @return array<int, array{term: string, chunk_id: int, tf: int, words: int, source: string, kind: string, audience: string, hash: string}>
	 */
	public static function postings( array $terms, array $audiences, array $exclude = array() ): array {
		global $wpdb;

		$terms     = self::cut_terms( $terms );
		$audiences = self::audiences( $audiences );
		$exclude   = self::audiences( $exclude ); // Keys as audiences are read: strings, each once, no empty one.

		if ( array() === $terms || array() === $audiences ) {
			return array();
		}

		$t_in = implode( ', ', array_fill( 0, count( $terms ), '%s' ) );
		$a_in = implode( ', ', array_fill( 0, count( $audiences ), '%s' ) );
		$x_in = array() === $exclude ? '' : ' AND c.source NOT IN (' . implode( ', ', array_fill( 0, count( $exclude ), '%s' ) ) . ')';
		$sql  = 'SELECT t.term, t.chunk_id, t.tf, c.words, c.source, c.kind, c.audience, c.hash FROM ' . self::terms_table() . ' t INNER JOIN ' . self::chunks_table() . " c ON c.id = t.chunk_id WHERE t.term IN ($t_in) AND c.audience IN ($a_in)$x_in ORDER BY t.chunk_id, t.term";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per term, per audience and per source left out, built above; the tables are ours.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $terms, $audiences, $exclude ) ), ARRAY_A );
		$out  = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[] = array(
				'term'     => (string) $row['term'],
				'chunk_id' => (int) $row['chunk_id'],
				'tf'       => (int) $row['tf'],
				'words'    => (int) $row['words'],
				'source'   => (string) $row['source'],
				'kind'     => (string) $row['kind'],
				'audience' => (string) $row['audience'],
				'hash'     => (string) $row['hash'],
			);
		}

		return $out;
	}

	/**
	 * The `page:` sources whose passages carry an audience, as the passages' table holds them (not the
	 * state, which a failed write can leave behind it), sorted.
	 *
	 * @param string $audience An audience, such as `public`.
	 * @return string[] Source keys, such as `page:2746`.
	 */
	public static function page_sources( string $audience ): array {
		global $wpdb;

		$table = self::chunks_table();
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT source FROM {$table} WHERE audience = %s AND source LIKE %s", $audience, $wpdb->esc_like( 'page:' ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours; asked at each question, so never cached.
		$out   = array_values( array_unique( array_map( 'strval', is_array( $rows ) ? $rows : array() ) ) );

		sort( $out );

		return $out;
	}

	/**
	 * The section titles of one audience's guide, for the search-words call: the distinct non-empty
	 * `heading` values of that audience's `guide` passages, each cut to HEADING_CHARS characters, in the
	 * index's order (the guide's own), at most `$limit`. No FAQ or handbook title is among them.
	 *
	 * @param string $audience The guide's audience, such as `mentor`.
	 * @param int    $limit    The most titles given.
	 * @return string[]
	 */
	public static function headings( string $audience, int $limit = 250 ): array {
		global $wpdb;

		if ( '' === $audience || $limit < 1 ) {
			return array();
		}

		$table = self::chunks_table();
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT heading FROM {$table} WHERE audience = %s AND kind = %s AND heading <> '' ORDER BY id", $audience, 'guide' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours; asked at each question, so never cached.
		$out   = array();

		foreach ( is_array( $rows ) ? $rows : array() as $heading ) {
			$heading = trim( mb_substr( trim( (string) $heading ), 0, self::HEADING_CHARS, 'UTF-8' ) );

			if ( '' === $heading || in_array( $heading, $out, true ) ) {
				continue;
			}

			$out[] = $heading;

			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Whole passages by id, in the order asked. An id the index lacks is left out.
	 *
	 * @param int[] $ids Passage ids.
	 * @return array<int, array> Rows: `id`, `source`, `kind`, `audience`, `post_id`, `title`, `heading`,
	 *                           `url`, `body`, `words`, `hash`, `indexed_at`.
	 */
	public static function rows( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		if ( array() === $ids ) {
			return array();
		}

		$table = self::chunks_table();
		$found = array();

		foreach ( array_chunk( $ids, self::BATCH ) as $batch ) {
			$in = implode( ', ', array_fill( 0, count( $batch ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per id, built above; the table is ours.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ($in)", $batch ), ARRAY_A );

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$row['id']      = (int) $row['id'];
				$row['post_id'] = (int) $row['post_id'];
				$row['words']   = (int) $row['words'];

				$found[ $row['id'] ] = $row;
			}
		}

		$out = array();

		foreach ( $ids as $id ) {
			if ( isset( $found[ $id ] ) ) {
				$out[] = $found[ $id ];
			}
		}

		return $out;
	}

	/**
	 * Audiences as the index reads them: strings, each once, no empty one.
	 *
	 * @param array $audiences Audiences.
	 * @return string[]
	 */
	public static function audiences( array $audiences ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $audiences ),
					function ( string $audience ): bool {
						return '' !== $audience;
					}
				)
			)
		);
	}

	/**
	 * Terms as the column holds them: cut to TERM_CHARS characters, de-duplicated, no empty one.
	 *
	 * @param string[] $terms Terms.
	 * @return string[]
	 */
	public static function cut_terms( array $terms ): array {
		$out = array();

		foreach ( $terms as $term ) {
			$term = mb_substr( (string) $term, 0, self::TERM_CHARS, 'UTF-8' );

			if ( '' !== $term ) {
				$out[ $term ] = true;
			}
		}

		return array_map( 'strval', array_keys( $out ) );
	}

	/**
	 * Term frequencies with each term cut to the column, two that cut alike merged, each at most the
	 * column's ceiling.
	 *
	 * @param array<string, int> $counts Term => frequency.
	 * @return array<string, int>
	 */
	private static function cut_counts( array $counts ): array {
		$out = array();

		foreach ( $counts as $term => $tf ) {
			$term = mb_substr( (string) $term, 0, self::TERM_CHARS, 'UTF-8' );

			if ( '' === $term ) {
				continue;
			}

			$out[ $term ] = min( self::SMALL_MAX, ( isset( $out[ $term ] ) ? $out[ $term ] : 0 ) + (int) $tf );
		}

		return $out;
	}

	/**
	 * The page's title above a passage's heading path, as the passage's terms read them.
	 *
	 * @param string $title   The page's title.
	 * @param string $heading The section's path, or ''.
	 * @return string
	 */
	private static function trail( string $title, string $heading ): string {
		$title = trim( $title );

		if ( '' === $title ) {
			return $heading;
		}

		return '' === $heading ? $title : $title . ' > ' . $heading;
	}

	/**
	 * Write a passage's terms, BATCH rows a statement.
	 *
	 * @param int                $chunk_id The passage.
	 * @param array<string, int> $counts   Term => frequency, already cut.
	 * @return bool Whether every statement went in.
	 */
	private static function insert_terms( int $chunk_id, array $counts ): bool {
		global $wpdb;

		$table = self::terms_table();

		foreach ( array_chunk( $counts, self::BATCH, true ) as $batch ) {
			$values = array();

			foreach ( $batch as $term => $tf ) {
				array_push( $values, (string) $term, $chunk_id, (int) $tf );
			}

			$rows = implode( ', ', array_fill( 0, count( $batch ), '(%s, %d, %d)' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Three placeholders per row, built above; the table is ours.
			if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (term, chunk_id, tf) VALUES $rows", $values ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Delete a source's passages and their terms.
	 *
	 * @param string $source The source's key.
	 * @return bool Whether every statement went through. A failed read of the ids is a failure: the
	 *              rows cannot be known to be gone.
	 */
	private static function delete_rows( string $source ): bool {
		global $wpdb;

		$table = self::chunks_table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE source = %s", $source ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours; a read that must be fresh.

		if ( '' !== (string) $wpdb->last_error || ! is_array( $ids ) ) {
			return false;
		}

		return self::delete_chunks( array_map( 'intval', $ids ) );
	}

	/**
	 * Delete passages by id, their terms first, stopping at the first statement that fails. A passage
	 * is never left without its terms but with its row, so whatever is left is still found by its
	 * source and deleted by the next write.
	 *
	 * @param int[] $ids Passage ids.
	 * @return bool Whether every statement went through.
	 */
	private static function delete_chunks( array $ids ): bool {
		global $wpdb;

		$terms  = self::terms_table();
		$chunks = self::chunks_table();

		foreach ( array_chunk( $ids, self::BATCH ) as $batch ) {
			$in = implode( ', ', array_fill( 0, count( $batch ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per id, built above; the tables are ours.
			if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$terms} WHERE chunk_id IN ($in)", $batch ) ) ) {
				return false;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- As above.
			if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$chunks} WHERE id IN ($in)", $batch ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Take back a passage whose terms did not all go in: its terms, then its row, each tried whatever
	 * the other's outcome, so its row goes even when its terms' table cannot be reached. What stays is
	 * the next write's to delete, which the state's error asks for.
	 *
	 * @param int $chunk_id The passage.
	 */
	private static function take_back( int $chunk_id ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::terms_table() . ' WHERE chunk_id = %d', $chunk_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- The table is ours; the id is a placeholder.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::chunks_table() . ' WHERE id = %d', $chunk_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- The table is ours; the id is a placeholder.
	}

	/**
	 * A text made valid UTF-8 (invalid bytes replaced, as the site's utf8mb4 columns would refuse the
	 * whole row) and, with a length, cut to it in characters.
	 *
	 * @param mixed $text  Text.
	 * @param int   $chars The column's length in characters, or 0 for no cut.
	 * @return string
	 */
	private static function text( $text, int $chars ): string {
		$text = (string) $text;

		if ( '' !== $text && ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = (string) mb_scrub( $text, 'UTF-8' );
		}

		return $chars > 0 && mb_strlen( $text, 'UTF-8' ) > $chars ? mb_substr( $text, 0, $chars, 'UTF-8' ) : $text;
	}
}
