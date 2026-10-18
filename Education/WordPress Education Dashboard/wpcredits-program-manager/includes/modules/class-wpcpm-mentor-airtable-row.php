<?php
/**
 * Mentors module - the Mentors row an activation writes (1.122.33).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one row in Airtable's Mentors table that a newly active mentor gets (design of 16 October
 * 2026, section 3, decision 7, and section 7).
 *
 * **What is written.** Full Name, WordPress profile, Email and Status always, and the applicant's
 * answers whose columns exist: the country and the languages as links where the names match, the
 * contribution areas, the hours, the teacher answer and the free text. The three sponsorship
 * columns stay empty, since the form does not ask them. After that the row is Airtable's: the site
 * never updates it again, and never deletes a mentor's row. The one cell it ever changes is the
 * Status of the row that already holds the person, a returning mentor's own or the one found by the
 * address, which it sets back to the status the mentors sync reads.
 *
 * **The cells are shaped by each column's type**, read from the base's schema, because
 * `WPCPM_Airtable::create_records()` sends no `typecast` and a value of the wrong kind is a refusal
 * of the whole row. A name the base does not hold (a language, a contribution area, a country) is
 * left out by name and the rest of the row is written; a column the table lacks, or one of a type
 * the site does not write, is left out the same way; and when the token is refused the schema (a
 * 401 or a 403: it lacks the `schema.bases:read` scope), the four cells alone. Any other failure to
 * read the schema (a timeout, a 5xx, a base or table that is not there) stops the write as an
 * unreadable linked table does, because the row is never updated and would stay without its
 * answers. What was left out is answered, so the application can say so.
 *
 * **A linked table that cannot be read stops the write**, because the site never updates a row
 * afterwards and a row made without its links would stay without them for good. The linked tables'
 * names are read once a day each and kept (`LINKS_TRANSIENT`), and let go when Airtable refuses a
 * create, since a kept name may be a linked row deleted since.
 *
 * **The check row.** The Airtable base is the only place the whole row can be tried, and the launch
 * wants to know before the switch is turned on. `check_row()` writes one synthetic row, reads it
 * back and deletes it, with a Status that neither the mentors sync nor the Mentor Status Checker
 * reads (`CHECK_STATUS`), so the next sync cannot turn it into an account with a welcome that
 * carries the private links; a row left behind is searched for before and after, and named when it
 * cannot be removed. It is the only thing here that ever deletes anything.
 *
 * Nothing here reads or writes on a public request, and nothing here changes the application, an
 * account or the history: the callers do that with what these methods answer.
 */
final class WPCPM_Mentor_Airtable_Row {

	/**
	 * The answers an application carries that become cells, by the key of
	 * `WPCPM_Mentors_Sync::fields()` that names each one's column, in the order of the row.
	 *
	 * @var array<string, string>
	 */
	const ANSWERS = array(
		'country'   => 'mentor_country',
		'languages' => 'mentor_languages',
		'expertise' => 'mentor_expertise',
		'hours'     => 'mentor_hours',
		'teacher'   => 'mentor_teacher',
		'anything'  => 'mentor_anything',
	);

	/**
	 * What the site writes into a column of each type, by answer: `link` is the record IDs of the
	 * linked table's rows named so, `choice` and `choices` the names among the column's choices,
	 * `join` the names as one text, `text` the answer as one text, `number` a number and `box` a
	 * tick. A type not listed for an answer is one the site does not write.
	 *
	 * @var array<string, array<string, string>>
	 */
	const MODES = array(
		'country'   => array(
			'multipleRecordLinks' => 'link',
			'singleSelect'        => 'choice',
			'singleLineText'      => 'text',
		),
		'languages' => array(
			'multipleRecordLinks' => 'link',
			'multipleSelects'     => 'choices',
			'singleLineText'      => 'join',
			'multilineText'       => 'join',
		),
		'expertise' => array(
			'multipleRecordLinks' => 'link',
			'multipleSelects'     => 'choices',
			'singleLineText'      => 'join',
			'multilineText'       => 'join',
		),
		'hours'     => array(
			'number'         => 'number',
			'singleLineText' => 'text',
		),
		'teacher'   => array(
			'singleSelect'   => 'choice',
			'checkbox'       => 'box',
			'singleLineText' => 'text',
		),
		'anything'  => array(
			'singleLineText' => 'text',
			'multilineText'  => 'text',
			'richText'       => 'text',
		),
	);

	/**
	 * The names of the linked tables' rows, by table: when each was read and the names, kept a day
	 * (`link_names()`), or until Airtable refuses a create (`write()`). Removed with the plugin.
	 *
	 * @var string
	 */
	const LINKS_TRANSIENT = 'wpcpm_mapp_link_names';

	/**
	 * The Status the check row is written with. It must be a choice of the Status column, and not
	 * the status the mentors sync reads (`mentor_status`) nor either of the two the checker reads
	 * (`checker_source_status`, `checker_target_status`), so neither reads the row. Whether an
	 * automation in the base fires on it is something the site cannot see: the launch checklist has
	 * the owner read the automations.
	 *
	 * @var string
	 */
	const CHECK_STATUS = 'Inactive';

	/**
	 * The name the check row is written with. With `CHECK_STATUS` it is how the cleanup after a
	 * create that erred knows a row it finds is the check row, and not somebody's.
	 *
	 * @var string
	 */
	const CHECK_NAME = 'Mentor row check';

	/*
	 * The cells
	 * --------------------------------------------------------------------
	 */

	/**
	 * The cells an application would write, from the base's schema.
	 *
	 * @param WP_Post    $post   The application.
	 * @param array|null $schema The bare schema, table ID to table, as `fetch_schema()` answers it
	 *                           and `cached_schema()` holds it under `schema`; null when it cannot
	 *                           be read. A schema without the Mentors table is read as none.
	 * @param callable   $names  `( $table_id, $primary )`: the names of a linked table's rows,
	 *                           lowercased, to their record IDs; or a `WP_Error`.
	 * @return array `cells` (column name to value) and `left_out` (column name to `why` and `values`).
	 */
	public static function cells( WP_Post $post, $schema, callable $names ) {
		$answers = array_merge(
			WPCPM_Mentor_Application::fields_of( $post ),
			array(
				'name'    => WPCPM_Mentor_Application::name_of( $post ),
				'email'   => WPCPM_Mentor_Application::email_of( $post ),
				'profile' => WPCPM_Mentor_Application::handle_of( $post ),
			)
		);

		return self::cells_for( $answers, $schema, $names );
	}

	/**
	 * The cells for a set of answers, as the form stores them: `name`, `email`, `profile`,
	 * `country`, `languages`, `expertise`, `hours`, `teacher` and `anything`.
	 *
	 * The four cells the mentors sync reads are always sent: the name as typed, the profile's address
	 * built from the username, the address, and the status the sync reads. Each answer then goes by
	 * its column's type, or is left out and said to be (`left_out_sentence()`). An empty answer sends
	 * nothing and is not left out.
	 *
	 * @param array      $answers The answers.
	 * @param array|null $schema  See `cells()`.
	 * @param callable   $names   See `cells()`.
	 * @return array `cells` and `left_out`, as `cells()` answers.
	 */
	public static function cells_for( array $answers, $schema, callable $names ) {
		$fields = WPCPM_Mentors_Sync::fields();
		$cells  = array(
			$fields['mentor_name']    => WPCPM_Typed_Text::mail_text( self::text_of( $answers, 'name' ) ),
			$fields['mentor_profile'] => WPCPM_Mentor_Application::profile_url( self::text_of( $answers, 'profile' ) ),
			$fields['mentor_email']   => self::text_of( $answers, 'email' ),
			$fields['mentor_status']  => self::setting( 'mentor_status' ),
		);

		$table = is_array( $schema ) ? WPCPM_Airtable::table_in( $schema, self::setting( 'mentors_table' ) ) : null;

		if ( ! is_array( $table ) ) {
			return array(
				'cells'    => $cells,
				'left_out' => array(
					'*' => array(
						'why'    => 'no-schema',
						'values' => array(),
					),
				),
			);
		}

		$columns  = isset( $table['columns'] ) && is_array( $table['columns'] ) ? $table['columns'] : array();
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

			$shaped = self::shape( self::MODES[ $answer ][ $type ], $items, $columns[ $name ], $schema, $names );

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
	 * @param string   $mode   A value of `MODES`.
	 * @param array    $items  The answer's non-empty items, trimmed (one for a single answer).
	 * @param array    $column The column's `type` and `options`.
	 * @param array    $schema The schema, for the linked table.
	 * @param callable $names See `cells()`.
	 * @return array `cell` (null for none to send) and `left` (a `left_out` entry, or null).
	 */
	private static function shape( $mode, array $items, array $column, array $schema, callable $names ) {
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

			case 'number':
				return array(
					'cell' => (float) $items[0],
					'left' => null,
				);

			case 'box':
				return array(
					'cell' => 'yes' === self::key_of( $items[0] ),
					'left' => null,
				);

			case 'join':
				return array(
					'cell' => implode( ', ', $items ),
					'left' => null,
				);

			case 'choice':
			case 'choices':
				$chosen  = array();
				$missing = array();

				foreach ( $items as $item ) {
					$choice = self::choice_in( $column, $item );

					if ( '' !== $choice ) {
						$chosen[] = $choice;
					} else {
						$missing[] = $item;
					}
				}

				$chosen = array_values( array_unique( $chosen ) );

				return array(
					'cell' => array() === $chosen ? null : ( 'choice' === $mode ? $chosen[0] : $chosen ),
					'left' => array() === $missing ? null : self::entry( 'unmatched', $missing ),
				);

			case 'link':
				$linked  = isset( $column['options']['linkedTableId'] ) ? (string) $column['options']['linkedTableId'] : '';
				$primary = ( '' !== $linked && isset( $schema[ $linked ]['primary'] ) ) ? (string) $schema[ $linked ]['primary'] : '';

				if ( '' === $primary ) {
					return array(
						'cell' => null,
						'left' => self::entry( 'unread', array( __( 'The linked table, or its first column, is not in the base\'s schema.', 'wpcredits-program-manager' ) ) ),
					);
				}

				$map = call_user_func( $names, $linked, $primary );

				if ( is_wp_error( $map ) ) {
					return array(
						'cell' => null,
						'left' => self::entry( 'unread', array( $map->get_error_message() ) ),
					);
				}

				$ids     = array();
				$missing = array();

				foreach ( $items as $item ) {
					$key = self::key_of( $item );

					if ( isset( $map[ $key ] ) ) {
						$ids[] = (string) $map[ $key ];
					} else {
						$missing[] = $item;
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
		$wanted = self::key_of( $name );

		foreach ( self::choice_names( $column ) as $choice ) {
			if ( self::key_of( $choice ) === $wanted ) {
				return $choice;
			}
		}

		return '';
	}

	/**
	 * The names of a column's choices.
	 *
	 * @param array $column The column.
	 * @return string[]
	 */
	private static function choice_names( array $column ) {
		$names   = array();
		$choices = isset( $column['options']['choices'] ) && is_array( $column['options']['choices'] ) ? $column['options']['choices'] : array();

		foreach ( $choices as $choice ) {
			if ( is_array( $choice ) && isset( $choice['name'] ) && is_scalar( $choice['name'] ) ) {
				$names[] = (string) $choice['name'];
			}
		}

		return $names;
	}

	/**
	 * The non-empty items of an answer, trimmed: the one text of a single answer, the names of a
	 * list, and nothing for an empty one.
	 *
	 * @param string $answer The answer's key.
	 * @param mixed  $value  What was stored.
	 * @return string[]
	 */
	private static function items_of( $answer, $value ) {
		$list  = in_array( $answer, array( 'languages', 'expertise' ), true ) ? (array) $value : array( $value );
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
	 * A name as names are compared: trimmed and lowercased.
	 *
	 * @param string $name A name.
	 * @return string
	 */
	private static function key_of( $name ) {
		return mb_strtolower( trim( (string) $name ) );
	}

	/**
	 * One setting, as text.
	 *
	 * @param string $key `mentors_table`, `mentor_status`, `checker_source_status` or
	 *                    `checker_target_status`.
	 * @return string
	 */
	private static function setting( $key ) {
		$settings = WPCPM_Settings::get();

		return isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
	}

	/**
	 * One entry of `left_out`.
	 *
	 * @param string   $why    `no-schema`, `no-column`, `type`, `unmatched` or `unread`.
	 * @param string[] $values The names left out, the column's type, or the error.
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
	 * With `$reasons`, what could not be read (`unread`) says why, in the words Airtable or the site
	 * gave: the command line's preview asks for that, since nothing else it prints says it. The
	 * Activated view and the history do not, since a row that could not be read is a refused write,
	 * whose refusal, printed beside it, says it already (`unread_error()`).
	 *
	 * @param array $left_out Column name to `why` and `values`, as `cells()` answers it.
	 * @param bool  $reasons  Whether what could not be read says why.
	 * @return string
	 */
	public static function left_out_sentence( array $left_out, $reasons = false ) {
		$sentences = array();

		foreach ( $left_out as $column => $entry ) {
			$column = (string) $column;
			$values = isset( $entry['values'] ) ? array_map( 'strval', (array) $entry['values'] ) : array();

			switch ( isset( $entry['why'] ) ? $entry['why'] : '' ) {
				case 'no-schema':
					$sentences[] = __( 'The answers were left out: the token cannot read the base\'s columns.', 'wpcredits-program-manager' );
					break;

				case 'no-column':
					/* translators: %s: the name of a column of the Mentors table. */
					$sentences[] = sprintf( __( '%s: the Mentors table has no such column.', 'wpcredits-program-manager' ), $column );
					break;

				case 'type':
					/* translators: 1: the name of a column of the Mentors table, 2: its type in Airtable, such as rating. */
					$sentences[] = sprintf( __( '%1$s: the column is a %2$s, which the site does not write.', 'wpcredits-program-manager' ), $column, implode( ', ', $values ) );
					break;

				case 'unmatched':
					/* translators: 1: the name of a column of the Mentors table, 2: the names the base does not hold, separated by commas. */
					$sentences[] = sprintf( __( '%1$s: not in the base: %2$s.', 'wpcredits-program-manager' ), $column, implode( ', ', $values ) );
					break;

				case 'unread':
					$why = $reasons && isset( $values[0] ) ? self::sentence( $values[0] ) : '';

					if ( '*' === $column && '' !== $why ) {
						/* translators: %s: the error Airtable or the site gave, a sentence. */
						$sentences[] = sprintf( __( 'The base\'s columns could not be read: %s', 'wpcredits-program-manager' ), $why );
						break;
					}

					if ( '*' === $column ) {
						$sentences[] = __( 'The base\'s columns could not be read.', 'wpcredits-program-manager' );
						break;
					}

					if ( '' !== $why ) {
						/* translators: 1: the name of a column of the Mentors table, 2: the error Airtable or the site gave, a sentence. */
						$sentences[] = sprintf( __( '%1$s: its linked table could not be read: %2$s', 'wpcredits-program-manager' ), $column, $why );
						break;
					}

					/* translators: %s: the name of a column of the Mentors table. */
					$sentences[] = sprintf( __( '%s: its linked table could not be read.', 'wpcredits-program-manager' ), $column );
					break;
			}
		}

		return implode( ' ', $sentences );
	}

	/*
	 * The linked names
	 * --------------------------------------------------------------------
	 */

	/**
	 * The names of a linked table's rows, lowercased and trimmed, to their record IDs.
	 *
	 * Read from the table's first column. A name held twice is the first row's; a row with no name or
	 * no record ID is passed over. With `$keep`, the names are kept a day, each table with its own
	 * time, and a table read within the day is not read again; without, they are read from the base
	 * and not kept, which is what a check wants. A read that fails is answered and nothing is kept.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @param string         $table_id The linked table.
	 * @param string         $primary  The name of its first column.
	 * @param bool           $keep     Whether to read from and refresh the kept names.
	 * @return array|WP_Error
	 */
	public static function link_names( WPCPM_Airtable $airtable, $table_id, $primary, $keep = false ) {
		$table_id = (string) $table_id;

		if ( $keep ) {
			$held = self::kept_names( $table_id );

			if ( null !== $held ) {
				return $held;
			}
		}

		$records = $airtable->fetch_all( $table_id, array( 'fields' => array( $primary ) ) );

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$names = array();

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['id'] ) || ! WPCPM_Airtable::is_record_id( $record['id'] ) ) {
				continue;
			}

			$name = self::key_of( WPCPM_Airtable::flatten( isset( $record['fields'][ $primary ] ) ? $record['fields'][ $primary ] : '' ) );

			if ( '' === $name || isset( $names[ $name ] ) ) {
				continue;
			}

			$names[ $name ] = trim( (string) $record['id'] );
		}

		if ( $keep ) {
			self::keep_names( $table_id, $names );
		}

		return $names;
	}

	/**
	 * The names kept for a table, when they were read within the day.
	 *
	 * @param string $table_id The table.
	 * @return array|null
	 */
	private static function kept_names( $table_id ) {
		$held = get_transient( self::LINKS_TRANSIENT );

		if ( ! is_array( $held ) || ! isset( $held[ $table_id ]['read'], $held[ $table_id ]['names'] ) || ! is_array( $held[ $table_id ]['names'] ) ) {
			return null;
		}

		return time() - (int) $held[ $table_id ]['read'] < DAY_IN_SECONDS ? $held[ $table_id ]['names'] : null;
	}

	/**
	 * Keep a table's names, and let go of any table's that are a day old.
	 *
	 * @param string $table_id The table.
	 * @param array  $names    Its names.
	 */
	private static function keep_names( $table_id, array $names ) {
		$held = get_transient( self::LINKS_TRANSIENT );
		$held = is_array( $held ) ? $held : array();
		$now  = time();

		$held[ $table_id ] = array(
			'read'  => $now,
			'names' => $names,
		);

		foreach ( $held as $id => $entry ) {
			if ( ! isset( $entry['read'] ) || $now - (int) $entry['read'] >= DAY_IN_SECONDS ) {
				unset( $held[ $id ] );
			}
		}

		set_transient( self::LINKS_TRANSIENT, $held, DAY_IN_SECONDS );
	}

	/**
	 * What an application would write, read from the base now: `cells()` with the schema the client
	 * holds and the linked names read through it. Reads, never writes to Airtable.
	 *
	 * A token that is refused the schema (a 401 or a 403, the missing `schema.bases:read` scope) gives
	 * the four cells and the answers left out as `no-schema`. Any other schema that cannot be had, a
	 * failed read or a base without the Mentors table, gives the four cells and `unread` under `*`,
	 * which stops a write: the row would never be updated, so it is written whole when the schema can
	 * be read again.
	 *
	 * @param WP_Post        $post     The application.
	 * @param WPCPM_Airtable $airtable The client.
	 * @return array `cells` and `left_out`.
	 */
	public static function preview( WP_Post $post, WPCPM_Airtable $airtable ) {
		$names  = static function ( $table_id, $primary ) use ( $airtable ) {
			return self::link_names( $airtable, $table_id, $primary, true );
		};
		$read   = $airtable->cached_schema();
		$schema = ! is_wp_error( $read ) && isset( $read['schema'] ) && is_array( $read['schema'] ) ? $read['schema'] : null;
		$why    = '';

		if ( is_wp_error( $read ) ) {
			$why = self::is_refused( $read ) ? '' : $read->get_error_message();
		} elseif ( null === $schema ) {
			$why = __( 'Airtable returned no table schema.', 'wpcredits-program-manager' );
		} else {
			$table = WPCPM_Airtable::table_in( $schema, self::setting( 'mentors_table' ) );
			$why   = is_wp_error( $table ) ? $table->get_error_message() : '';
		}

		if ( '' === $why ) {
			return self::cells( $post, $schema, $names );
		}

		$row             = self::cells( $post, null, $names );
		$row['left_out'] = array( '*' => self::entry( 'unread', array( $why ) ) );

		return $row;
	}

	/**
	 * Whether Airtable refused the token the schema: a 401 or a 403, which is how a token without the
	 * `schema.bases:read` scope is answered. It is the one failure to read the schema that is a fact
	 * about the token, and not a moment's trouble.
	 *
	 * @param WP_Error $error What the schema read answered.
	 * @return bool
	 */
	private static function is_refused( WP_Error $error ) {
		$data = (array) $error->get_error_data();

		return isset( $data['status'] ) && in_array( (int) $data['status'], array( 401, 403 ), true );
	}

	/*
	 * The write
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Mentors rows whose Email is an address, compared trimmed and without regard to case.
	 *
	 * @param string         $email    An address.
	 * @param WPCPM_Airtable $airtable The client.
	 * @return string[]|WP_Error The record IDs (one page of rows is read); an error for no address,
	 *                           since a search by nothing would read the table, and for a search
	 *                           Airtable refuses.
	 */
	public static function find_by_email( $email, WPCPM_Airtable $airtable ) {
		$fields = WPCPM_Mentors_Sync::fields();
		$rows   = self::rows_by_email( $email, $airtable, array( $fields['mentor_email'] ) );

		return is_wp_error( $rows ) ? $rows : array_values( array_unique( array_keys( $rows ) ) );
	}

	/**
	 * The Mentors rows whose Email is an address, with the columns asked for.
	 *
	 * @param string         $email    An address.
	 * @param WPCPM_Airtable $airtable The client.
	 * @param string[]       $columns  The columns to read.
	 * @return array|WP_Error Record ID to its fields (one page of rows is read); an error as
	 *                        `find_by_email()` has it.
	 */
	private static function rows_by_email( $email, WPCPM_Airtable $airtable, array $columns ) {
		$fields  = WPCPM_Mentors_Sync::fields();
		$formula = $airtable->formula_in( $fields['mentor_email'], array( (string) $email ), true, true );

		if ( '' === $formula ) {
			return new WP_Error( 'wpcpm_mentor_row_no_email', __( 'There is no email address to search the Mentors table by.', 'wpcredits-program-manager' ) );
		}

		$page = $airtable->fetch_page(
			self::setting( 'mentors_table' ),
			array(
				'formula' => $formula,
				'fields'  => $columns,
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$rows = array();

		foreach ( isset( $page['records'] ) ? (array) $page['records'] : array() as $record ) {
			if ( is_array( $record ) && isset( $record['id'] ) && WPCPM_Airtable::is_record_id( $record['id'] ) ) {
				$rows[ trim( (string) $record['id'] ) ] = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();
			}
		}

		return $rows;
	}

	/**
	 * Write the Mentors row for an application that became a mentor.
	 *
	 * In order: with a record ID given (the account's own row), its Status is set back and the row is
	 * `reactivated`, unless Airtable says the row is gone, which is as if none was given. Otherwise
	 * the table is searched by the applicant's address: two rows or more are a person's to settle; a
	 * row another account on the site holds is not this person's; one row otherwise, whatever its
	 * Status, has it set back and is `adopted`. With none, one row is `created` from `preview()`,
	 * unless a linked table or the schema could not be read, which writes nothing. A blank status
	 * setting writes nothing at all, before any of this: it would blank a row's Status.
	 *
	 * @param WP_Post        $post      The application.
	 * @param string         $record_id The account's own Mentors row, or ''.
	 * @param int            $user_id   The account being made a mentor, which holds no row but its own.
	 * @param WPCPM_Airtable $airtable  The client.
	 * @return array `ok`, `how` (`created`, `reactivated` or `adopted`), `record`, `error` (Airtable's
	 *               words or the site's, '' on success) and `left_out`.
	 */
	public static function write( WP_Post $post, $record_id, $user_id, WPCPM_Airtable $airtable ) {
		$result    = array(
			'ok'       => false,
			'how'      => '',
			'record'   => '',
			'error'    => '',
			'left_out' => array(),
		);
		$record_id = trim( (string) $record_id );

		if ( '' === trim( self::setting( 'mentor_status' ) ) ) {
			$result['error'] = __( 'The Mentor status to sync setting is blank, so nothing was written to Airtable. Fill it in, then press Write the Airtable row again.', 'wpcredits-program-manager' );

			return $result;
		}

		if ( '' !== $record_id ) {
			$set = self::set_status( $record_id, $airtable );

			if ( ! is_wp_error( $set ) ) {
				return array_merge(
					$result,
					array(
						'ok'     => true,
						'how'    => 'reactivated',
						'record' => $record_id,
					)
				);
			}

			if ( ! self::row_is_gone( $set ) ) {
				$result['error'] = $set->get_error_message();

				return $result;
			}
		}

		$found = self::find_by_email( WPCPM_Mentor_Application::email_of( $post ), $airtable );

		if ( is_wp_error( $found ) ) {
			$result['error'] = $found->get_error_message();

			return $result;
		}

		if ( count( $found ) > 1 ) {
			/* translators: %d: how many Mentors rows hold the applicant's address. */
			$result['error'] = sprintf( __( 'Airtable holds %d Mentors rows with this address. Keep one, then press Write the Airtable row again.', 'wpcredits-program-manager' ), count( $found ) );

			return $result;
		}

		if ( 1 === count( $found ) ) {
			$holder = self::other_holder( $found[0], $user_id );

			if ( null !== $holder ) {
				/* translators: %s: the username of the account that holds the Mentors row. */
				$result['error'] = sprintf( __( 'The Mentors row with this address is linked to another account on this site, %s.', 'wpcredits-program-manager' ), $holder->user_login );

				return $result;
			}

			$set = self::set_status( $found[0], $airtable );

			if ( is_wp_error( $set ) ) {
				$result['error'] = $set->get_error_message();

				return $result;
			}

			return array_merge(
				$result,
				array(
					'ok'     => true,
					'how'    => 'adopted',
					'record' => $found[0],
				)
			);
		}

		$row                = self::preview( $post, $airtable );
		$result['left_out'] = $row['left_out'];
		$unread             = self::unread_error( $row['left_out'] );

		if ( '' !== $unread ) {
			$result['error'] = $unread;

			return $result;
		}

		$created = $airtable->create_records( self::setting( 'mentors_table' ), array( array( 'fields' => $row['cells'] ) ) );

		if ( is_wp_error( $created ) ) {
			// A kept name may be a linked row deleted in Airtable since, which would make every create
			// that names it refused until the names were a day old: the next write reads them again.
			delete_transient( self::LINKS_TRANSIENT );

			$result['error'] = $created->get_error_message();

			return $result;
		}

		$record = self::first_record_id( $created );

		if ( '' === $record ) {
			$result['error'] = __( 'Airtable did not answer with a record ID.', 'wpcredits-program-manager' );

			return $result;
		}

		$result['ok']     = true;
		$result['how']    = 'created';
		$result['record'] = $record;

		return $result;
	}

	/**
	 * Set a Mentors row's Status to the status the mentors sync reads: the one cell the site ever
	 * changes.
	 *
	 * @param string         $record_id The row.
	 * @param WPCPM_Airtable $airtable  The client.
	 * @return array|WP_Error
	 */
	private static function set_status( $record_id, WPCPM_Airtable $airtable ) {
		$fields = WPCPM_Mentors_Sync::fields();

		return $airtable->update_records(
			self::setting( 'mentors_table' ),
			array(
				array(
					'id'     => $record_id,
					'fields' => array( $fields['mentor_status'] => self::setting( 'mentor_status' ) ),
				),
			)
		);
	}

	/**
	 * Whether Airtable's refusal says the row is not there: a 404, or the error type it names.
	 *
	 * @param WP_Error $error The refusal.
	 * @return bool
	 */
	private static function row_is_gone( WP_Error $error ) {
		$data = (array) $error->get_error_data();

		return ( isset( $data['status'] ) && 404 === (int) $data['status'] ) || ( isset( $data['error_type'] ) && 'ROW_DOES_NOT_EXIST' === $data['error_type'] );
	}

	/**
	 * The account, other than the one being made a mentor, that holds a Mentors row as its record ID.
	 *
	 * @param string $record_id The row.
	 * @param int    $user_id   The account being made a mentor.
	 * @return WP_User|null
	 */
	private static function other_holder( $record_id, $user_id ) {
		$holders = get_users(
			array(
				'meta_key'   => WPCPM_Mentors_Sync::META_RECORD_ID,
				'meta_value' => $record_id,
			)
		);

		foreach ( (array) $holders as $holder ) {
			if ( $holder instanceof WP_User && (int) $holder->ID !== (int) $user_id ) {
				return $holder;
			}
		}

		return null;
	}

	/**
	 * The sentence that stops a write when a linked table could not be read, or '' when none was.
	 *
	 * @param array $left_out A row's `left_out`.
	 * @return string
	 */
	private static function unread_error( array $left_out ) {
		foreach ( $left_out as $column => $entry ) {
			if ( isset( $entry['why'] ) && 'unread' === $entry['why'] ) {
				$values = isset( $entry['values'] ) ? array_values( (array) $entry['values'] ) : array();
				$error  = isset( $values[0] ) ? (string) $values[0] : '';

				if ( '*' === (string) $column ) {
					/* translators: %s: the error Airtable or the site gave. */
					return sprintf( __( 'The base\'s columns could not be read from Airtable, so nothing was written: %s', 'wpcredits-program-manager' ), $error );
				}

				/* translators: 1: the name of a column of the Mentors table, 2: the error Airtable or the site gave. */
				return sprintf( __( '%1$s could not be read from Airtable, so nothing was written: %2$s', 'wpcredits-program-manager' ), $column, $error );
			}
		}

		return '';
	}

	/**
	 * The first record ID among the IDs a create answered.
	 *
	 * @param mixed $answer What `create_records()` answered.
	 * @return string '' for none.
	 */
	private static function first_record_id( $answer ) {
		foreach ( (array) $answer as $id ) {
			if ( WPCPM_Airtable::is_record_id( $id ) ) {
				return trim( (string) $id );
			}
		}

		return '';
	}

	/*
	 * The check row
	 * --------------------------------------------------------------------
	 */

	/**
	 * The synthetic mentor the check row is written for: no real person, the address given, and the
	 * first choice of each list the form offers, so that every column is tried.
	 *
	 * @param string $email The test mailbox's address.
	 * @return array The answers, as the form stores them.
	 */
	public static function check_answers( $email ) {
		$countries = WPCPM_Mentor_Application_Form::countries();
		$teams     = WPCPM_Mentor_Application_Form::teams();

		return array(
			'name'      => self::CHECK_NAME,
			'email'     => (string) $email,
			'profile'   => 'mentor-row-check',
			'country'   => array() === $countries ? '' : (string) reset( $countries ),
			'languages' => array( 'English' ),
			'expertise' => array() === $teams ? array() : array( (string) reset( $teams ) ),
			'hours'     => '2.5',
			'teacher'   => 'No',
			'anything'  => 'A check row written by the site and deleted at once.',
		);
	}

	/**
	 * Write one synthetic row to the Mentors table, read it back and delete it, to learn what the
	 * live base keeps of the row an activation writes, before an activation does.
	 *
	 * Checked before anything is written: an address; the schema, read from the base now (never the
	 * copy the client keeps, since the check proves what the base takes) and its own error printed when
	 * it cannot be read; the linked tables are readable (read, and not kept); the schema has the Mentors
	 * table; the activation's status, which the check does not write, is a choice of the Status column;
	 * and `CHECK_STATUS` is a choice of it too and not a status the mentors sync or the checker reads.
	 * The table is then searched by the check address, and a row that holds it refuses the check. After
	 * a create that errs, the table is searched again and each row that is the check row (by its name
	 * and its Status) is deleted, since a create whose answer was lost may have stored it; any other
	 * row holding the address is named and left alone, and a search that fails, a delete that is
	 * refused, or a transport error that found no row yet says so, and never reads as "nothing was
	 * stored". Nothing of the program changes: no application, account, history or feed figure.
	 *
	 * @param string         $email    The test mailbox's address.
	 * @param WPCPM_Airtable $airtable The client.
	 * @return array {
	 *     @type string[] $written      The columns sent.
	 *     @type string   $record       The row's record ID, '' when none was stored.
	 *     @type string[] $kept         The columns the read-back held, or that were sent false or empty, which Airtable leaves out of a read.
	 *     @type string[] $missing      The rest: sent, and not read back.
	 *     @type string[] $stops        The sentences that stop the launch sitting.
	 *     @type string[] $notes        The sentences that only inform: a name the base does not hold.
	 *     @type string[] $found_before The rows that held the address before the create.
	 *     @type string[] $cleaned      The rows deleted after a create that erred.
	 *     @type bool     $deleted      Whether the row was deleted.
	 *     @type string   $error        The base's answer to the create or the site's reason, '' when it wrote.
	 *     @type string   $read_error   Why the read-back failed, or ''.
	 *     @type string   $delete_error Why the delete failed, or ''.
	 *     @type bool     $transport    Whether the create failed in transport
	 *                                  (`http_request_failed`), when it may have reached Airtable and
	 *                                  is no refusal of Airtable's.
	 * }
	 */
	public static function check_row( $email, WPCPM_Airtable $airtable ) {
		$result = array(
			'written'      => array(),
			'record'       => '',
			'kept'         => array(),
			'missing'      => array(),
			'stops'        => array(),
			'notes'        => array(),
			'found_before' => array(),
			'cleaned'      => array(),
			'deleted'      => false,
			'error'        => '',
			'read_error'   => '',
			'delete_error' => '',
			'transport'    => false,
		);
		$email  = trim( (string) $email );

		if ( ! is_email( $email ) ) {
			$result['error'] = __( 'The check needs an email address.', 'wpcredits-program-manager' );

			return $result;
		}

		// The base as it is now, never the copy the client keeps: the check proves what the base takes.
		$schema = $airtable->fetch_schema();

		if ( is_wp_error( $schema ) ) {
			$result['error'] = $schema->get_error_message();

			return $result;
		}

		$fields = WPCPM_Mentors_Sync::fields();
		$table  = self::setting( 'mentors_table' );
		$row    = self::cells_for(
			self::check_answers( $email ),
			$schema,
			static function ( $table_id, $primary ) use ( $airtable ) {
				return self::link_names( $airtable, $table_id, $primary );
			}
		);
		$unread = self::unread_error( $row['left_out'] );

		if ( '' !== $unread ) {
			$result['error'] = $unread;

			return $result;
		}

		$mentors = is_array( $schema ) ? WPCPM_Airtable::table_in( $schema, $table ) : new WP_Error( 'wpcpm_airtable_no_schema', __( 'Airtable returned no table schema.', 'wpcredits-program-manager' ) );

		if ( is_wp_error( $mentors ) ) {
			$result['error'] = $mentors->get_error_message();

			return $result;
		}

		$choices    = self::choice_names( isset( $mentors['columns'][ $fields['mentor_status'] ] ) && is_array( $mentors['columns'][ $fields['mentor_status'] ] ) ? $mentors['columns'][ $fields['mentor_status'] ] : array() );
		$activation = self::setting( 'mentor_status' );

		if ( ! in_array( $activation, $choices, true ) ) {
			/* translators: %s: the status an activation writes, such as Active. */
			$result['error'] = sprintf( __( 'The activation\'s status, %s, is not a choice of the Status column.', 'wpcredits-program-manager' ), $activation );

			return $result;
		}

		$read = array_map( array( __CLASS__, 'key_of' ), array( $activation, self::setting( 'checker_source_status' ), self::setting( 'checker_target_status' ) ) );

		if ( ! in_array( self::CHECK_STATUS, $choices, true ) || in_array( self::key_of( self::CHECK_STATUS ), $read, true ) ) {
			/* translators: %s: the status the check row is written with, such as Inactive. */
			$result['error'] = sprintf( __( 'The check row\'s status, %s, is one the mentors sync or the checker reads, or no choice of the Status column.', 'wpcredits-program-manager' ), self::CHECK_STATUS );

			return $result;
		}

		$before = self::find_by_email( $email, $airtable );

		if ( is_wp_error( $before ) ) {
			$result['error'] = $before->get_error_message();

			return $result;
		}

		if ( array() !== $before ) {
			$result['found_before'] = $before;
			/* translators: %s: the record IDs of the Mentors rows that hold the check address, separated by commas. */
			$result['error'] = sprintf( __( 'Rows with the check address are already in the Mentors table: %s. Delete them by hand, then check again.', 'wpcredits-program-manager' ), implode( ', ', $before ) );

			return $result;
		}

		// The Status an activation writes was confirmed above; the check row carries the one nothing reads.
		$cells                             = $row['cells'];
		$cells[ $fields['mentor_status'] ] = self::CHECK_STATUS;
		$result['written']                 = array_map( 'strval', array_keys( $cells ) );
		$created                           = $airtable->create_records( $table, array( array( 'fields' => $cells ) ) );
		$record                            = is_wp_error( $created ) ? '' : self::first_record_id( $created );

		if ( '' === $record ) {
			$message   = is_wp_error( $created ) ? $created->get_error_message() : __( 'Airtable did not answer with a record ID.', 'wpcredits-program-manager' );
			$transport = is_wp_error( $created ) && 'http_request_failed' === $created->get_error_code();
			$after     = self::after_create_error( $airtable, $email, $message, $transport );

			$result['error']     = $after['error'];
			$result['cleaned']   = $after['cleaned'];
			$result['transport'] = $transport;

			return $result;
		}

		$result['record'] = $record;

		try {
			$back = $airtable->get_record( $table, $record );

			if ( is_wp_error( $back ) ) {
				$result['read_error'] = $back->get_error_message();
			} else {
				$held = isset( $back['fields'] ) && is_array( $back['fields'] ) ? $back['fields'] : array();

				foreach ( $cells as $column => $sent ) {
					// A false checkbox and an empty value are left out of a record Airtable reads.
					if ( array_key_exists( $column, $held ) || false === $sent || '' === $sent || array() === $sent ) {
						$result['kept'][] = (string) $column;
					} else {
						$result['missing'][] = (string) $column;
					}
				}
			}
		} finally {
			$gone = $airtable->delete_records( $table, array( $record ) );

			if ( is_wp_error( $gone ) ) {
				$result['delete_error'] = $gone->get_error_message();
			} elseif ( isset( $gone[ $record ] ) ) {
				$result['deleted'] = true;
			} else {
				$result['delete_error'] = __( 'Airtable did not confirm that it deleted the row.', 'wpcredits-program-manager' );
			}
		}

		foreach ( $row['left_out'] as $column => $entry ) {
			$sentence = self::left_out_sentence( array( $column => $entry ) );

			if ( 'unmatched' === $entry['why'] ) {
				$result['notes'][] = $sentence;
			} else {
				$result['stops'][] = $sentence;
			}
		}

		foreach ( $result['missing'] as $column ) {
			/* translators: %s: the name of a column of the Mentors table. */
			$result['stops'][] = sprintf( __( '%s: sent, and not read back.', 'wpcredits-program-manager' ), $column );
		}

		return $result;
	}

	/**
	 * What to do after a create that erred or answered no record ID: it may have stored the row all
	 * the same, so the table is searched by the check address, with the name and the Status of each
	 * row it finds, and every row that is the check row (its name and its Status are the ones the
	 * check writes) is deleted. A row that is not the check row is somebody's: it is named and never
	 * deleted.
	 *
	 * What the output may claim follows from what the search found. "Nothing was stored" is said only
	 * after a search that succeeded, found no row at all and followed an error that was not a
	 * transport error; after a transport error (the request may have reached Airtable, and may still
	 * be stored) a search that finds no check row says Airtable may still store it, and when to look.
	 *
	 * @param WPCPM_Airtable $airtable  The client.
	 * @param string         $email     The check address.
	 * @param string         $message   The create's answer.
	 * @param bool           $transport Whether the create failed in transport, `http_request_failed`.
	 * @return array `error` (the create's answer followed by what the search and the delete found) and
	 *               `cleaned` (the rows deleted).
	 */
	private static function after_create_error( WPCPM_Airtable $airtable, $email, $message, $transport ) {
		$fields = WPCPM_Mentors_Sync::fields();
		$rows   = self::rows_by_email( $email, $airtable, array( $fields['mentor_email'], $fields['mentor_name'], $fields['mentor_status'] ) );

		if ( is_wp_error( $rows ) ) {
			return array(
				'error'   => self::sentence( $message ) . ' ' . sprintf(
					/* translators: %s: the check address, which is what to look for in the Mentors table. */
					__( 'Could not search the Mentors table after the create failed, so a row may have been stored: look for %s in the Mentors table and delete it by hand.', 'wpcredits-program-manager' ),
					$email
				),
				'cleaned' => array(),
			);
		}

		$ours   = array();
		$others = array();

		foreach ( $rows as $id => $cells ) {
			$name   = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_name'] ] ) ? $cells[ $fields['mentor_name'] ] : '' ) );
			$status = trim( WPCPM_Airtable::flatten( isset( $cells[ $fields['mentor_status'] ] ) ? $cells[ $fields['mentor_status'] ] : '' ) );

			if ( self::CHECK_NAME === $name && self::CHECK_STATUS === $status ) {
				$ours[] = (string) $id;
			} else {
				$others[] = (string) $id;
			}
		}

		$cleaned   = array();
		$sentences = array( self::sentence( $message ) );

		if ( array() !== $ours ) {
			$gone = $airtable->delete_records( self::setting( 'mentors_table' ), $ours );

			if ( is_wp_error( $gone ) ) {
				$data = (array) $gone->get_error_data();
				$done = isset( $data['deleted'] ) ? array_map( 'strval', (array) $data['deleted'] ) : array();
			} else {
				$done = array_map( 'strval', array_keys( (array) $gone ) );
			}

			$cleaned = array_values( array_intersect( $ours, $done ) );

			if ( array() !== $cleaned ) {
				$sentences[] = sprintf(
					/* translators: %s: the record IDs of the rows the search found and deleted, separated by commas. */
					_n( 'The search after it found %s and deleted it.', 'The search after it found %s and deleted them.', count( $cleaned ), 'wpcredits-program-manager' ),
					implode( ', ', $cleaned )
				);
			}

			foreach ( array_diff( $ours, $cleaned ) as $stuck ) {
				/* translators: %s: the record ID of a row in the Mentors table. */
				$sentences[] = sprintf( __( 'The row %s could not be deleted: delete it by hand in the Mentors table.', 'wpcredits-program-manager' ), $stuck );
			}
		}

		foreach ( $others as $other ) {
			/* translators: %s: the record ID of a row in the Mentors table that holds the check address. */
			$sentences[] = sprintf( __( 'The row %s holds the check address but is not the check row, so it was not deleted: look at it in the Mentors table.', 'wpcredits-program-manager' ), $other );
		}

		if ( array() === $ours && $transport ) {
			$sentences[] = sprintf(
				/* translators: %s: the check address, which is what to look for in the Mentors table. */
				__( 'The search found no row yet, but Airtable may still store it: look for %s in the Mentors table in a minute and delete it by hand.', 'wpcredits-program-manager' ),
				$email
			);
		} elseif ( array() === $rows ) {
			$sentences[] = __( 'The search after it found nothing, so nothing was stored and nothing was deleted.', 'wpcredits-program-manager' );
		}

		return array(
			'error'   => implode( ' ', $sentences ),
			'cleaned' => $cleaned,
		);
	}

	/**
	 * A sentence that ends: the text given a full stop when it has no ending, so that more can follow.
	 *
	 * @param string $text A message.
	 * @return string
	 */
	private static function sentence( $text ) {
		$text = trim( (string) $text );

		return '' === $text || 1 === preg_match( '/[.!?]$/', $text ) ? $text : $text . '.';
	}
}
