<?php
/**
 * Student registration - the Students row a registration writes (1.123.54).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one row in Airtable's Students table that a registration on the site gets (design of 5
 * November 2026, sections 3 and 4).
 *
 * **The row is created once and is Airtable's afterwards.** The site never updates it and never
 * deletes it: it is created, and the students sync reads it from then on like any other row. Status
 * is not sent, so the base's own automation and the people who run the program keep it.
 *
 * **The cells are shaped by each column's type**, read from the base's schema, because
 * `WPCPM_Airtable::create_records()` sends no `typecast` and a value of the wrong kind is a refusal
 * of the whole row. A name the base does not hold (a language, an institution, a field of study) is
 * left out by name and the rest of the row is written; a column the table lacks, or one of a type
 * the site does not write, is left out the same way. What was left out is answered, so the
 * administrators can be told.
 *
 * **The Languages table is copied** (`LANGUAGES_OPTION`) so that the form can offer the languages and
 * the row can link them without a read of Airtable on a public request. The copy is read from the
 * table the Students table's Languages column links, once a day, or again after a create Airtable
 * refused, since a kept language may be a row deleted since. Unlike the Mentors row's names, the copy
 * keeps each name as Airtable spells it.
 *
 * Nothing here reads or writes on its own: the callers pass the client, and nothing here changes the
 * registration or an account.
 */
final class WPCPM_Student_Airtable_Row {

	/**
	 * The answers a registration carries that become cells, by the key of
	 * `WPCPM_Mentors_Sync::fields()` that names each one's column, in the order of the row. There is
	 * no Status: Airtable keeps it empty.
	 *
	 * @var array<string, string>
	 */
	const ANSWERS = array(
		'name'        => 'student_record_name',
		'email'       => 'student_email',
		'institution' => 'student_institution',
		'study'       => 'student_study',
		'tutor'       => 'student_tutor',
		'tutor_email' => 'student_tutor_email',
		'start'       => 'student_start',
		'end'         => 'student_end',
		'hours'       => 'student_hours',
		'languages'   => 'student_languages',
		'preferred'   => 'student_preferred_language',
		'access'      => 'student_access',
		'english'     => 'student_english',
		'privacy'     => 'student_privacy',
		'track_name'  => 'student_requested_track',
	);

	/**
	 * What the site writes into a column of each type, by answer: `text` the answer as typed,
	 * `institution` the record ID of the institution, `choice` the column's choice of that name,
	 * `date` a day, `number` a number, `languages` the record IDs of the languages named, and `box`
	 * a tick. A type not listed for an answer is one the site does not write.
	 *
	 * @var array<string, array<string, string>>
	 */
	const MODES = array(
		'name'        => array(
			'singleLineText' => 'text',
			'multilineText'  => 'text',
		),
		'email'       => array(
			'email'          => 'text',
			'singleLineText' => 'text',
		),
		'institution' => array(
			'multipleRecordLinks' => 'institution',
		),
		'study'       => array(
			'singleSelect' => 'choice',
		),
		'tutor'       => array(
			'singleLineText' => 'text',
			'multilineText'  => 'text',
		),
		'tutor_email' => array(
			'email'          => 'text',
			'singleLineText' => 'text',
		),
		'start'       => array(
			'date' => 'date',
		),
		'end'         => array(
			'date' => 'date',
		),
		'hours'       => array(
			'number' => 'number',
		),
		'languages'   => array(
			'multipleRecordLinks' => 'languages',
		),
		'preferred'   => array(
			'multipleRecordLinks' => 'languages',
		),
		'access'      => array(
			'multilineText'  => 'text',
			'singleLineText' => 'text',
			'richText'       => 'text',
		),
		'english'     => array(
			'checkbox' => 'box',
		),
		'privacy'     => array(
			'checkbox' => 'box',
		),
		// The track's name as the student saw it, or Not sure yet (#304): text, so a new track needs no change in Airtable.
		'track_name'  => array(
			'singleLineText' => 'text',
			'multilineText'  => 'text',
		),
	);

	/**
	 * The site's copy of the Languages table, not autoloaded: `read` (when it was read, Unix time),
	 * `table` (the linked table's ID), `names` (record ID to name, A to Z) and `stale` (a create was
	 * refused since). Removed with the plugin (`WPCPM_Students::uninstall()`).
	 *
	 * @var string
	 */
	const LANGUAGES_OPTION = 'wpcpm_student_languages';

	/*
	 * The cells
	 * --------------------------------------------------------------------
	 */

	/**
	 * The cells a registration writes, by each column's type.
	 *
	 * @param array $answers The stored answers: the keys of `ANSWERS`, and `institution_name`, the
	 *                       name the institution was chosen by.
	 * @param array $table   The Students table, as `fetch_schema()` gives one table.
	 * @param array $links   `institutions` and `languages`, each a record ID to a name.
	 * @return array `cells` (column name to value) and `left_out` (column name to `why` and `values`).
	 */
	public static function cells_for( array $answers, array $table, array $links ) {
		$fields   = WPCPM_Mentors_Sync::fields();
		$columns  = isset( $table['columns'] ) && is_array( $table['columns'] ) ? $table['columns'] : array();
		$cells    = array();
		$left_out = array();

		foreach ( self::ANSWERS as $answer => $key ) {
			$items = self::items_of( $answer, isset( $answers[ $answer ] ) ? $answers[ $answer ] : '' );

			if ( array() === $items ) {
				continue;
			}

			$name = $fields[ $key ];

			if ( ! isset( $columns[ $name ] ) || ! is_array( $columns[ $name ] ) ) {
				$left_out[ $name ] = self::entry( 'no-column' );
				continue;
			}

			$type = isset( $columns[ $name ]['type'] ) ? (string) $columns[ $name ]['type'] : '';

			if ( ! isset( self::MODES[ $answer ][ $type ] ) ) {
				$left_out[ $name ] = self::entry( 'type', array( $type ) );
				continue;
			}

			$shaped = self::shape( self::MODES[ $answer ][ $type ], $items, $columns[ $name ], $answers, $links );

			if ( null !== $shaped['cell'] ) {
				$cells[ $name ] = $shaped['cell'];
			}

			if ( null !== $shaped['left'] ) {
				$left_out[ $name ] = $shaped['left'];
			}
		}

		return array(
			'cells'    => $cells,
			'left_out' => $left_out,
		);
	}

	/**
	 * One answer's cell, for a column of a type the site writes.
	 *
	 * @param string $mode    A value of `MODES`.
	 * @param array  $items   The answer's non-empty items, trimmed (one for a single answer).
	 * @param array  $column  The column's `type` and `options`.
	 * @param array  $answers All the answers, for the institution's name.
	 * @param array  $links   See `cells_for()`.
	 * @return array `cell` (null for none to send) and `left` (a `left_out` entry, or null).
	 */
	private static function shape( $mode, array $items, array $column, array $answers, array $links ) {
		$none = array(
			'cell' => null,
			'left' => null,
		);

		switch ( $mode ) {
			case 'text':
				// The text as the person typed it, not as the form's cleaners stored it.
				return array(
					'cell' => WPCPM_Typed_Text::mail_text( $items[0] ),
					'left' => null,
				);

			case 'date':
				return array(
					'cell' => $items[0],
					'left' => null,
				);

			case 'number':
				return array(
					'cell' => (float) $items[0],
					'left' => null,
				);

			case 'box':
				return array(
					'cell' => '0' !== $items[0],
					'left' => null,
				);

			case 'choice':
				$choice = self::choice_in( $column, $items[0] );

				return array(
					'cell' => '' === $choice ? null : $choice,
					'left' => '' === $choice ? self::entry( 'unmatched', array( WPCPM_Typed_Text::mail_text( $items[0] ) ) ) : null,
				);

			case 'institution':
				$held = isset( $links['institutions'] ) && is_array( $links['institutions'] ) ? $links['institutions'] : array();

				if ( isset( $held[ $items[0] ] ) ) {
					return array(
						'cell' => array( $items[0] ),
						'left' => null,
					);
				}

				$name = self::text_of( $answers, 'institution_name' );

				return array(
					'cell' => null,
					'left' => self::entry( 'unmatched', array( '' !== $name ? WPCPM_Typed_Text::mail_text( $name ) : $items[0] ) ),
				);

			case 'languages':
				$known = array();

				foreach ( isset( $links['languages'] ) && is_array( $links['languages'] ) ? $links['languages'] : array() as $id => $name ) {
					$key = self::key_of( $name );

					if ( '' !== $key && ! isset( $known[ $key ] ) ) {
						$known[ $key ] = (string) $id;
					}
				}

				$ids     = array();
				$missing = array();

				foreach ( $items as $item ) {
					$key = self::key_of( $item );

					if ( isset( $known[ $key ] ) ) {
						$ids[] = $known[ $key ];
					} else {
						$missing[] = WPCPM_Typed_Text::mail_text( $item );
					}
				}

				$ids = array_values( array_unique( $ids ) );

				return array(
					'cell' => array() === $ids ? null : $ids,
					'left' => array() === $missing ? null : self::entry( 'unmatched', $missing ),
				);
		}

		return $none;
	}

	/**
	 * What a column's choice list calls a name, matched trimmed and without regard to case.
	 *
	 * @param array  $column The column's `options`, among them its `choices`.
	 * @param string $name   A name.
	 * @return string The choice as the base spells it, or '' when it offers none by that name.
	 */
	private static function choice_in( array $column, $name ) {
		$wanted  = self::key_of( $name );
		$choices = isset( $column['options']['choices'] ) && is_array( $column['options']['choices'] ) ? $column['options']['choices'] : array();

		foreach ( $choices as $choice ) {
			if ( is_array( $choice ) && isset( $choice['name'] ) && is_scalar( $choice['name'] ) && self::key_of( $choice['name'] ) === $wanted ) {
				return (string) $choice['name'];
			}
		}

		return '';
	}

	/**
	 * The non-empty items of an answer, trimmed: the one text of a single answer, the names of the
	 * languages, and nothing for an empty one.
	 *
	 * @param string $answer The answer's key.
	 * @param mixed  $value  What was stored.
	 * @return string[]
	 */
	private static function items_of( $answer, $value ) {
		$list  = 'languages' === $answer ? (array) $value : array( $value );
		$items = array();

		foreach ( $list as $item ) {
			if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$items[] = trim( (string) $item );
			}
		}

		return $items;
	}

	/**
	 * One text of the answers, trimmed, or '' with none.
	 *
	 * @param array  $answers The answers.
	 * @param string $key     The key.
	 * @return string
	 */
	private static function text_of( array $answers, $key ) {
		return isset( $answers[ $key ] ) && is_scalar( $answers[ $key ] ) ? trim( (string) $answers[ $key ] ) : '';
	}

	/**
	 * A name as names are compared: as typed (not as stored), trimmed and lowercased.
	 *
	 * @param string $name A name.
	 * @return string
	 */
	private static function key_of( $name ) {
		return mb_strtolower( trim( WPCPM_Typed_Text::mail_text( (string) $name ) ) );
	}

	/**
	 * One entry of `left_out`.
	 *
	 * @param string   $why    `no-column`, `type` or `unmatched`.
	 * @param string[] $values The names left out, or the column's type.
	 * @return array
	 */
	private static function entry( $why, array $values = array() ) {
		return array(
			'why'    => $why,
			'values' => $values,
		);
	}

	/**
	 * What was left out of a row, one sentence for each entry, joined by a space; '' for none.
	 *
	 * @param array $left_out Column name to `why` and `values`, as `cells_for()` answers it.
	 * @return string
	 */
	public static function left_out_sentence( array $left_out ) {
		$sentences = array();

		foreach ( $left_out as $column => $entry ) {
			$column = (string) $column;
			$values = isset( $entry['values'] ) ? array_map( 'strval', (array) $entry['values'] ) : array();

			switch ( isset( $entry['why'] ) ? $entry['why'] : '' ) {
				case 'no-column':
					/* translators: %s: the name of a column of the Students table. */
					$sentences[] = sprintf( __( '%s: the Students table has no such column.', 'wpcredits-program-manager' ), $column );
					break;

				case 'type':
					/* translators: 1: the name of a column of the Students table, 2: its type in Airtable, such as rating. */
					$sentences[] = sprintf( __( '%1$s: the column is a %2$s, which the site does not write.', 'wpcredits-program-manager' ), $column, implode( ', ', $values ) );
					break;

				case 'unmatched':
					/* translators: 1: the name of a column of the Students table, 2: the names the base does not hold, separated by commas. */
					$sentences[] = sprintf( __( '%1$s: not in the base: %2$s.', 'wpcredits-program-manager' ), $column, implode( ', ', $values ) );
					break;
			}
		}

		return implode( ' ', $sentences );
	}

	/*
	 * The table, the lookup and the create
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Students table, as the schema gives it.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @return array|WP_Error The table; any error as it came.
	 */
	public static function students_table( WPCPM_Airtable $airtable ) {
		$read = $airtable->cached_schema();

		if ( is_wp_error( $read ) ) {
			return $read;
		}

		return WPCPM_Airtable::table_in( isset( $read['schema'] ) && is_array( $read['schema'] ) ? $read['schema'] : array(), self::setting( 'students_table' ) );
	}

	/**
	 * The rows that already hold an address, found in the Students table's address column trimmed
	 * and without regard to case.
	 *
	 * @param string         $email    The address.
	 * @param WPCPM_Airtable $airtable The client.
	 * @return string[]|WP_Error The record IDs found; an error for an empty address or a failed read.
	 */
	public static function find_by_email( $email, WPCPM_Airtable $airtable ) {
		$fields  = WPCPM_Mentors_Sync::fields();
		$formula = $airtable->formula_in( $fields['student_email'], array( (string) $email ), true, true );

		if ( '' === $formula ) {
			return new WP_Error( 'wpcpm_student_no_email', __( 'There is no address to look for.', 'wpcredits-program-manager' ) );
		}

		$page = $airtable->fetch_page(
			self::setting( 'students_table' ),
			array(
				'formula' => $formula,
				'fields'  => array( $fields['student_email'] ),
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$ids = array();

		foreach ( $page['records'] as $record ) {
			if ( is_array( $record ) && isset( $record['id'] ) && WPCPM_Airtable::is_record_id( $record['id'] ) ) {
				$ids[] = trim( (string) $record['id'] );
			}
		}

		return $ids;
	}

	/**
	 * Create the row. Any error marks the copy of the Languages table stale, since a language the
	 * copy still holds may be a row deleted since.
	 *
	 * @param array          $cells    Column name to value, as `cells_for()` answers.
	 * @param WPCPM_Airtable $airtable The client.
	 * @return string|WP_Error The new record's ID.
	 */
	public static function create( array $cells, WPCPM_Airtable $airtable ) {
		$created = $airtable->create_records( self::setting( 'students_table' ), array( array( 'fields' => $cells ) ) );

		if ( ! is_wp_error( $created ) ) {
			foreach ( $created as $id ) {
				if ( WPCPM_Airtable::is_record_id( $id ) ) {
					return trim( (string) $id );
				}
			}

			$created = new WP_Error( 'wpcpm_student_no_record_id', __( 'Airtable did not answer with a record ID.', 'wpcredits-program-manager' ) );
		}

		$copy          = self::copy();
		$copy['stale'] = true;
		update_option( self::LANGUAGES_OPTION, $copy, false );

		return $created;
	}

	/*
	 * The copy of the Languages table
	 * --------------------------------------------------------------------
	 */

	/**
	 * The languages the site holds, record ID to name, A to Z without regard to case.
	 *
	 * @return array<string, string>
	 */
	public static function languages() {
		return self::copy()['names'];
	}

	/**
	 * Read the Languages table into the copy, when the copy is empty, a day old or stale.
	 *
	 * The table is the one the Students table's Languages column links, and its first column is the
	 * name. A row with no name or no record ID is passed over, and so is a name held twice (the first
	 * row's stands). A failed read keeps the old copy.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @param int            $now      The time, Unix; 0 for now.
	 * @return bool|WP_Error True when it read, false when the copy served; the error of a failed read.
	 */
	public static function refresh_languages( WPCPM_Airtable $airtable, $now = 0 ) {
		$now  = (int) $now > 0 ? (int) $now : time();
		$copy = self::copy();

		if ( array() !== $copy['names'] && ! $copy['stale'] && $now - $copy['read'] < DAY_IN_SECONDS && $copy['read'] <= $now ) {
			return false;
		}

		$table = self::students_table( $airtable );

		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$fields = WPCPM_Mentors_Sync::fields();
		$linked = isset( $table['columns'][ $fields['student_languages'] ]['options']['linkedTableId'] ) ? (string) $table['columns'][ $fields['student_languages'] ]['options']['linkedTableId'] : '';
		$read   = '' === $linked ? null : $airtable->cached_schema();

		if ( is_wp_error( $read ) ) {
			return $read;
		}

		$primary = null !== $read && isset( $read['schema'][ $linked ]['primary'] ) ? (string) $read['schema'][ $linked ]['primary'] : '';

		if ( '' === $primary ) {
			return new WP_Error( 'wpcpm_student_no_languages_table', __( 'The Languages table, or its first column, is not in the base\'s schema.', 'wpcredits-program-manager' ) );
		}

		$records = $airtable->fetch_all( $linked, array( 'fields' => array( $primary ) ) );

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$names = array();
		$seen  = array();

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['id'] ) || ! WPCPM_Airtable::is_record_id( $record['id'] ) ) {
				continue;
			}

			$name = trim( WPCPM_Airtable::flatten( isset( $record['fields'][ $primary ] ) ? $record['fields'][ $primary ] : '' ) );
			$key  = mb_strtolower( $name );

			if ( '' === $name || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;

			$names[ trim( (string) $record['id'] ) ] = $name;
		}

		update_option(
			self::LANGUAGES_OPTION,
			array(
				'read'  => $now,
				'table' => $linked,
				'names' => self::sorted( $names ),
				'stale' => false,
			),
			false
		);

		return true;
	}

	/**
	 * The copy as stored, in its whole shape.
	 *
	 * @return array `read`, `table`, `names` and `stale`.
	 */
	private static function copy() {
		$stored = get_option( self::LANGUAGES_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$names  = array();

		foreach ( isset( $stored['names'] ) && is_array( $stored['names'] ) ? $stored['names'] : array() as $id => $name ) {
			if ( WPCPM_Airtable::is_record_id( $id ) && is_scalar( $name ) && '' !== trim( (string) $name ) ) {
				$names[ (string) $id ] = trim( (string) $name );
			}
		}

		return array(
			'read'  => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'table' => isset( $stored['table'] ) ? (string) $stored['table'] : '',
			'names' => self::sorted( $names ),
			'stale' => ! empty( $stored['stale'] ),
		);
	}

	/**
	 * Names A to Z without regard to case, the record ID breaking a tie.
	 *
	 * @param array<string, string> $names Record ID to name.
	 * @return array<string, string>
	 */
	private static function sorted( array $names ) {
		$ids   = array_map( 'strval', array_keys( $names ) );
		$lower = array_map( 'mb_strtolower', array_values( $names ) );

		array_multisort( $lower, SORT_ASC, SORT_STRING, $ids, SORT_ASC, SORT_STRING );

		$sorted = array();

		foreach ( $ids as $id ) {
			$sorted[ $id ] = $names[ $id ];
		}

		return $sorted;
	}

	/**
	 * The institutions the site holds, record ID to name trimmed.
	 *
	 * @return array<string, string>
	 */
	public static function institution_links() {
		$links = array();

		foreach ( WPCPM_Institutions_Index::rows() as $id => $row ) {
			$links[ (string) $id ] = is_array( $row ) && isset( $row['name'] ) && is_scalar( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		}

		return $links;
	}

	/**
	 * One setting, as text.
	 *
	 * @param string $key The setting.
	 * @return string
	 */
	private static function setting( $key ) {
		$value = WPCPM_Settings::get_value( $key );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
