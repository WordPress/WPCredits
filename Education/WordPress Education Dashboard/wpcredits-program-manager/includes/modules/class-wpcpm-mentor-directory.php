<?php
/**
 * The public list of the program's active mentors, kept on the site (1.123.62).
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a public mentor directory shows of each active mentor: the name, the WordPress.org profile,
 * the country, the languages, whether the mentor is sponsored and by whom. Nothing else of a
 * mentor's record is kept here, and no address.
 *
 * Who sponsors a mentor is the site's own record (each sponsor holds its mentors), so it is not
 * stored with the list: it is read as the list is asked for, from the Approved sponsors that are
 * not test sponsors, the ones the Partners page names, and a change on the Sponsors screen
 * shows at once.
 *
 * The mentors sync writes the list as it reads the Mentors table (`row()` for each mentor,
 * `store()` once the table is read), so the list is as fresh as the last finished run. In the base
 * Country and Languages are links to tables of their own, which a record gives as record IDs: the
 * sync learns the two linked tables from the schema (`linked_tables()`), reads their names in its
 * lookups phase and hands them to `row()`. A list with a name it could not read is not stored
 * (`complete()`): the last whole list stands instead. A plugin
 * that draws a directory asks for it through the filter `wpcpm_public_mentors` and never reads
 * Airtable itself: the day the mentors' record moves onto the site, whatever writes the record
 * calls `store()` and every reader follows.
 *
 * The filter passes its value through until a run has stored the list, so a reader can tell "no
 * list yet" (null, and it keeps its own source) from "no mentors" (an empty list).
 */
final class WPCPM_Mentor_Directory {

	/** The stored list: `v`, `read` (when the run finished reading) and `rows`. Not autoloaded. */
	const OPTION = 'wpcpm_mentors_directory';

	/** The filter a directory asks: null in, the list out once there is one. */
	const FILTER = 'wpcpm_public_mentors';

	/** The stored shape's version. */
	const VERSION = 1;

	/**
	 * Answer the filter.
	 */
	public static function init() {
		add_filter( self::FILTER, array( __CLASS__, 'answer' ) );
	}

	/** The two answers the base may hold as links, each with its key in `WPCPM_Mentors_Sync::fields()`. */
	const LINKED = array(
		'country'   => 'mentor_country',
		'languages' => 'mentor_languages',
	);

	/**
	 * Which tables the Mentors table's Country and Languages columns link to, read from the schema.
	 *
	 * @param array  $schema        The base's schema, as `WPCPM_Airtable::fetch_schema()` answers it.
	 * @param string $mentors_table The Mentors table's ID, or its name.
	 * @param array  $fields        `WPCPM_Mentors_Sync::fields()`.
	 * @return array<string, string> `country` and `languages` to a table ID, each only when its
	 *                               column is a link.
	 */
	public static function linked_tables( array $schema, $mentors_table, array $fields ) {
		$columns = array();

		foreach ( $schema as $table_id => $table ) {
			if ( (string) $table_id === (string) $mentors_table || ( isset( $table['name'] ) && (string) $table['name'] === (string) $mentors_table ) ) {
				$columns = ( isset( $table['columns'] ) && is_array( $table['columns'] ) ) ? $table['columns'] : array();
				break;
			}
		}

		$linked = array();

		foreach ( self::LINKED as $answer => $key ) {
			$column = ( isset( $fields[ $key ], $columns[ $fields[ $key ] ] ) && is_array( $columns[ $fields[ $key ] ] ) ) ? $columns[ $fields[ $key ] ] : array();

			if ( isset( $column['type'], $column['options']['linkedTableId'] ) && 'multipleRecordLinks' === $column['type'] && '' !== (string) $column['options']['linkedTableId'] ) {
				$linked[ $answer ] = (string) $column['options']['linkedTableId'];
			}
		}

		return $linked;
	}

	/**
	 * One mentor's public facts, from their Mentors-table cells.
	 *
	 * The profile column is free text (an address, an address without its scheme, a handle), so it is
	 * reduced to the username and written back as the profile's address, or '' when no username can
	 * be read from it. Country is one value and Languages several; each is read whether the cell
	 * arrives as text, as a list of names or as a list of record IDs. A record ID is replaced by its
	 * name from `$names`; one whose table's names were not read marks the row `unread`, and one the
	 * table no longer holds is left out.
	 *
	 * @param array $cells  The record's `fields`.
	 * @param array $fields `WPCPM_Mentors_Sync::fields()`.
	 * @param array $names  `country` and `languages`, each a map of record ID to name, or absent
	 *                      when that table's names were not read.
	 * @return array `name`, `profile`, `country`, `languages` (string[]), `sponsored` (bool), and
	 *               `unread` (true) only when a link could not be named.
	 */
	public static function row( array $cells, array $fields, array $names = array() ) {
		$cell = static function ( $key ) use ( $cells, $fields ) {
			return isset( $fields[ $key ], $cells[ $fields[ $key ] ] ) ? $cells[ $fields[ $key ] ] : '';
		};

		$unread    = false;
		$countries = self::listed( $cell( 'mentor_country' ), isset( $names['country'] ) && is_array( $names['country'] ) ? $names['country'] : null, $unread );
		$languages = self::listed( $cell( 'mentor_languages' ), isset( $names['languages'] ) && is_array( $names['languages'] ) ? $names['languages'] : null, $unread );
		$username  = WPCPM_Mentor_Checker_Profile::normalize_username( WPCPM_Airtable::flatten( $cell( 'mentor_profile' ) ) );

		$row = array(
			'name'      => trim( WPCPM_Airtable::flatten( $cell( 'mentor_name' ) ) ),
			'profile'   => '' === $username ? '' : 'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/',
			'country'   => implode( ', ', $countries ),
			'languages' => $languages,
			'sponsored' => 'Yes' === trim( WPCPM_Airtable::flatten( $cell( 'mentor_sponsored' ) ) ),
		);

		if ( $unread ) {
			$row['unread'] = true;
		}

		return $row;
	}

	/**
	 * A cell of one or several values as a list of trimmed, non-empty names, each once, in the
	 * cell's order.
	 *
	 * @param mixed      $value  A list, or text with commas between the values.
	 * @param array|null $names  Record ID to name for this cell's linked table, or null with none read.
	 * @param bool       $unread Set to true when a record ID is met and there are no names to read it by.
	 * @return string[]
	 */
	private static function listed( $value, $names, &$unread ) {
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		$clean = array();

		foreach ( $items as $item ) {
			$item = trim( WPCPM_Airtable::flatten( $item ) );

			if ( 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', $item ) ) {
				if ( null === $names ) {
					$unread = true;
					continue;
				}

				$item = isset( $names[ $item ] ) ? trim( (string) $names[ $item ] ) : '';
			}

			if ( '' !== $item && ! in_array( $item, $clean, true ) ) {
				$clean[] = $item;
			}
		}

		return $clean;
	}

	/**
	 * Whether every row of a read names its countries and languages, so the list may be stored.
	 *
	 * @param array[] $rows `row()` results.
	 * @return bool
	 */
	public static function complete( array $rows ) {
		foreach ( $rows as $row ) {
			if ( ! empty( $row['unread'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Keep the list a finished read of the Mentors table gave. A mentor with no name is left out.
	 *
	 * @param array[] $rows `row()` results, keyed by the mentor's record ID, which is kept as each
	 *                      row's key: it is what a sponsor's record names its mentors by.
	 */
	public static function store( array $rows ) {
		$kept = array();

		foreach ( $rows as $record => $row ) {
			if ( is_array( $row ) && isset( $row['name'] ) && '' !== $row['name'] ) {
				unset( $row['unread'] );
				$kept[ (string) $record ] = $row;
			}
		}

		update_option(
			self::OPTION,
			array(
				'v'    => self::VERSION,
				'read' => time(),
				'rows' => $kept,
			),
			false
		);
	}

	/**
	 * The list, or null before a run has stored one: each mentor's stored facts and `sponsors`, the
	 * names of the sponsors whose record holds the mentor, A to Z. A mentor with a sponsor named is
	 * `sponsored` whatever the Mentors table's own column says. The record IDs are not given out.
	 *
	 * @return array[]|null
	 */
	public static function rows() {
		$stored = get_option( self::OPTION );

		if ( ! is_array( $stored ) || ! isset( $stored['v'], $stored['rows'] ) || self::VERSION !== (int) $stored['v'] || ! is_array( $stored['rows'] ) ) {
			return null;
		}

		$sponsors = self::sponsors_by_mentor();
		$rows     = array();

		foreach ( $stored['rows'] as $record => $row ) {
			$row['sponsors'] = isset( $sponsors[ (string) $record ] ) ? $sponsors[ (string) $record ] : array();

			if ( array() !== $row['sponsors'] ) {
				$row['sponsored'] = true;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * The names of the sponsors that may be named in public, by the record ID of each mentor they
	 * sponsor: Approved, named and not a test sponsor, as the Partners page has it.
	 *
	 * @return array<string, string[]>
	 */
	private static function sponsors_by_mentor() {
		$by_mentor = array();

		if ( ! class_exists( 'WPCPM_Sponsors_Index' ) ) {
			return $by_mentor;
		}

		foreach ( WPCPM_Sponsors_Index::approved() as $row ) {
			$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

			if ( '' === $name || WPCPM_Sponsors_Index::is_test( $row ) || empty( $row['mentors'] ) || ! is_array( $row['mentors'] ) ) {
				continue;
			}

			foreach ( $row['mentors'] as $mentor ) {
				$mentor = (string) $mentor;

				if ( ! isset( $by_mentor[ $mentor ] ) || ! in_array( $name, $by_mentor[ $mentor ], true ) ) {
					$by_mentor[ $mentor ][] = $name;
				}
			}
		}

		foreach ( $by_mentor as $mentor => $names ) {
			natcasesort( $names );
			$by_mentor[ $mentor ] = array_values( $names );
		}

		return $by_mentor;
	}

	/**
	 * When the stored list was read, as a Unix time, or 0 with none.
	 *
	 * @return int
	 */
	public static function read_at() {
		$stored = get_option( self::OPTION );

		return ( is_array( $stored ) && isset( $stored['read'] ) ) ? (int) $stored['read'] : 0;
	}

	/**
	 * The filter's answer: the list once there is one, and the value given until then.
	 *
	 * @param mixed $given What the filter was called with (null, by the contract).
	 * @return mixed
	 */
	public static function answer( $given ) {
		$rows = self::rows();

		return null === $rows ? $given : $rows;
	}
}
