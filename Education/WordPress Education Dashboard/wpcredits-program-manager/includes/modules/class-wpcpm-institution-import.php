<?php
/**
 * Reading a list of students an institution wants enrolled.
 *
 * This file is the half of the import that touches nothing: it takes the text of a CSV, or the
 * single row a one-student form posts, and turns it into cleaned rows with a verdict each. It
 * opens no socket, writes no post and creates nothing in Airtable. The staging, the duplicate
 * ladder against the base, the ceilings and the creation loop are separate pieces, so that the
 * part a school's file reaches first can be read, and tested, without any of that in the way.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parse and clean the rows of an institution's import.
 *
 * **Nothing here trusts the file.** It arrives from a person outside the program, describing
 * people who are not on this site yet, and it is on its way to a spreadsheet a program manager
 * will open and to an automation that puts a name in a subject line. So the rules below refuse
 * more than a CSV reader usually would, and they refuse loudly: a row that cannot be cleaned is
 * named with its line number rather than quietly dropped, because a school that pasted thirty
 * students and got twenty-eight has to be told which two and why.
 */
final class WPCPM_Institution_Import {

	/**
	 * The most rows one batch may carry.
	 *
	 * A term's intake at the largest institution in the program is a long way under this. What
	 * the ceiling is really for is the file that is not an intake at all: a whole address book,
	 * or a probe. Refused with the count, before a row is parsed.
	 *
	 * @var int
	 */
	const MAX_ROWS = 300;

	/**
	 * The most bytes of CSV that will be read.
	 *
	 * 256KB is around ten times the largest plausible intake as text. The check is on the bytes
	 * rather than on the row count because the row count is not known until the thing has been
	 * parsed, which is the work this ceiling exists to avoid doing.
	 *
	 * @var int
	 */
	const MAX_BYTES = 262144;

	/**
	 * The longest a tutor's name may be.
	 *
	 * The column in the base is free text. This is the form's own ceiling, kept here so a paste
	 * cannot carry a paragraph into a field every roster prints in a table cell.
	 *
	 * @var int
	 */
	const MAX_TUTOR = 120;

	/**
	 * A row that can be created.
	 *
	 * @var string
	 */
	const OK = 'ok';

	/**
	 * A row that failed cleaning: named, and not created.
	 *
	 * @var string
	 */
	const INVALID = 'invalid';

	/**
	 * Two rows of the same file describing the same person. Neither is created.
	 *
	 * @var string
	 */
	const DUPLICATE_FILE = 'duplicate-file';

	/**
	 * The columns this import understands, and what a school may call them.
	 *
	 * Spellings rather than a schema: the file comes from whatever the school's registry
	 * exports, and refusing "E-mail" because the code wanted "email" would send a person back to
	 * edit a header row by hand. Everything is compared after `header_key()` has flattened case,
	 * spaces, dots and dashes, so `Full Name`, `full_name` and `FULL-NAME` are one spelling and
	 * do not each need a line here.
	 *
	 * A column outside this list is not an error. It is listed back to the school so they can
	 * see it was ignored, which is friendlier than refusing a file for carrying a `Notes` column
	 * the registry always exports.
	 *
	 * @return array<string, string[]> Canonical key to accepted header spellings.
	 */
	public static function aliases() {
		$aliases = array(
			'name'           => array( 'name', 'full_name', 'fullname', 'student', 'student_name', 'nombre', 'imie_i_nazwisko' ),
			'email'          => array( 'email', 'e_mail', 'mail', 'email_address', 'correo' ),
			'profile'        => array( 'profile', 'wp_profile', 'wordpress_profile', 'wordpress_org_profile', 'wordpress_org', 'wporg', 'username', 'handle' ),
			'field_of_study' => array( 'field_of_study', 'field', 'study', 'studies', 'course_of_study' ),
			'tutor'          => array( 'tutor', 'tutor_name', 'supervisor' ),
			// Read only to be checked against the batch, never stored on a row: the program and
			// the start date are properties of the whole import, chosen on the form.
			'start_date'     => array( 'start_date', 'start', 'starts', 'internship_start_date' ),
			'program'        => array( 'program', 'programme', 'status', 'track' ),
		);

		/**
		 * Filter the header spellings an import accepts.
		 *
		 * @param array<string, string[]> $aliases Canonical key to accepted spellings.
		 */
		return (array) apply_filters( 'wpcpm_import_aliases', $aliases );
	}

	/**
	 * The columns that end up on a student's record.
	 *
	 * `start_date` and `program` are deliberately absent: they are read from a file only to be
	 * checked against the batch, and a row never carries its own.
	 *
	 * @return string[]
	 */
	public static function columns() {
		return array( 'name', 'email', 'profile', 'field_of_study', 'tutor' );
	}

	/**
	 * Turn the text of a CSV into rows.
	 *
	 * @param string $text  The file's bytes, or the contents of the paste box.
	 * @param array  $batch Batch values to check a `start_date` or `program` column against:
	 *                      `status` and `start`. An empty array skips that check.
	 * @return array{ok:bool,problem:string,detail:array,rows:array,unknown:array,delimiter:string}
	 *         `problem` is one of `empty`, `too_large`, `not_utf8`, `no_header`, `no_columns`,
	 *         `no_rows`, `too_many_rows`, `batch_mismatch`, and `detail` carries whatever the
	 *         caller needs to say it: a count, a list of missing columns, a list of line numbers.
	 */
	public static function parse( $text, array $batch = array() ) {
		$text = (string) $text;

		if ( strlen( $text ) > self::MAX_BYTES ) {
			return self::refuse(
				'too_large',
				array(
					'bytes' => strlen( $text ),
					'max'   => self::MAX_BYTES,
				)
			);
		}

		// A byte order mark is what Excel writes and what every naive parser then reads as part
		// of the first header's name, so `Name` arrives as `\xEF\xBB\xBFName` and matches
		// nothing. Stripped before the encoding check, since the mark is valid UTF-8 and would
		// pass it either way.
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );

		// **Refused rather than repaired.** A Latin-1 export is the common way this arrives and
		// the temptation is to convert it, but a converter has to guess the source encoding, and
		// guessing wrong turns "Fidélitas" into "FidÃ©litas" on a record that is then created,
		// synced and printed on a report. A person can re-export as UTF-8 in one click; nobody
		// can un-mangle a name once it is in the base.
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			return self::refuse( 'not_utf8', array() );
		}

		if ( '' === trim( $text ) ) {
			return self::refuse( 'empty', array() );
		}

		$delimiter = self::delimiter( $text );
		$lines     = self::read_csv( $text, $delimiter );

		if ( empty( $lines ) ) {
			return self::refuse( 'no_header', array() );
		}

		$header  = array_shift( $lines );
		$map     = self::map_header( $header );
		$missing = array_values( array_diff( array( 'name', 'email' ), array_keys( $map['columns'] ) ) );

		// Name and email are what a student record cannot be created without. Everything else
		// this import reads is optional, so a file with only those two columns is a valid file.
		if ( ! empty( $missing ) ) {
			return self::refuse(
				'no_columns',
				array(
					'missing' => $missing,
					'found'   => array_keys( $map['columns'] ),
				)
			);
		}

		$rows     = array();
		$mismatch = array();
		$line     = 1;

		foreach ( $lines as $cells ) {
			++$line;

			// A trailing newline gives one empty row, and a school's export often carries a run
			// of them. An empty row is not a person and not a mistake, so it is dropped without
			// being counted or named.
			if ( self::is_blank( $cells ) ) {
				continue;
			}

			$row = array( 'line' => $line );

			foreach ( $map['columns'] as $key => $index ) {
				$row[ $key ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
			}

			// The program and the start date belong to the batch, so a column carrying them may
			// only agree. Disagreeing is not a row-level problem to be cleaned away: it means
			// the file describes a different import from the one the form describes, and one of
			// the two is wrong in a way only the person can settle.
			if ( ! empty( $batch ) && ! self::agrees_with_batch( $row, $batch ) ) {
				$mismatch[] = $line;
			}

			foreach ( array( 'start_date', 'program' ) as $checked ) {
				unset( $row[ $checked ] );
			}

			$rows[] = $row;

			if ( count( $rows ) > self::MAX_ROWS ) {
				return self::refuse( 'too_many_rows', array( 'max' => self::MAX_ROWS ) );
			}
		}

		if ( ! empty( $mismatch ) ) {
			return self::refuse( 'batch_mismatch', array( 'lines' => $mismatch ) );
		}

		if ( empty( $rows ) ) {
			return self::refuse( 'no_rows', array() );
		}

		return array(
			'ok'        => true,
			'problem'   => '',
			'detail'    => array(),
			'rows'      => $rows,
			'unknown'   => $map['unknown'],
			'delimiter' => $delimiter,
		);
	}

	/**
	 * Clean one row and give it a verdict.
	 *
	 * **The rule the whole method follows**: a field a record cannot be created without refuses
	 * the row when it fails; an optional field that fails is dropped, named in `warnings`, and
	 * the row goes on. So a mistyped field of study costs a school one column and not one
	 * student, while a name that is not a name costs them the row, which is the only honest
	 * answer when the record would carry it.
	 *
	 * @param array $raw One row from `parse()`.
	 * @return array The row with `verdict`, `problems` and `warnings`, plus `email_key` and
	 *               `handle` for the duplicate ladder to compare on.
	 */
	public static function clean_row( array $raw ) {
		$row = array(
			'line'           => isset( $raw['line'] ) ? (int) $raw['line'] : 0,
			'name'           => '',
			'email'          => '',
			'email_key'      => '',
			'profile'        => '',
			'handle'         => '',
			'field_of_study' => '',
			'tutor'          => '',
			'verdict'        => self::OK,
			'problems'       => array(),
			'warnings'       => array(),
		);

		$raw_name = isset( $raw['name'] ) ? (string) $raw['name'] : '';
		$name     = self::text( $raw_name );

		if ( '' === $name ) {
			$row['problems'][] = 'name_missing';
		} elseif ( self::has_control( $raw_name ) ) {
			// **On the raw cell, and before `text()` has run.** `sanitize_text_field()` removes
			// tabs and newlines, so a check made after it can never see the thing it is looking
			// for: a name arriving with a tab in it is a row torn out of another table, and it
			// would have been silently repaired into a plausible name instead of refused.
			$row['problems'][] = 'name_control';
		} elseif ( self::is_formula( $name ) ) {
			// **A name is exported to a spreadsheet by every program manager who runs a report,
			// and it lands in the subject line of the welcome automation.** A leading `=`, `+`,
			// `-` or `@` is a formula to Excel, Numbers and Sheets alike, so a cell reading
			// `=HYPERLINK(...)` becomes a live link in somebody's download. No real name begins
			// with those characters, so refusing costs nobody anything. Prefixing an apostrophe
			// is what the exports do; here, where the value is about to be created rather than
			// printed, the row is refused instead - the apostrophe would end up in the base and
			// then in the student's certificate.
			$row['problems'][] = 'name_formula';
		} elseif ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 200 ) {
			$row['problems'][] = 'name_length';
		} else {
			$row['name'] = $name;
		}

		$email = sanitize_email( isset( $raw['email'] ) ? trim( (string) $raw['email'] ) : '' );

		if ( '' === $email ) {
			$row['problems'][] = 'email_missing';
		} elseif ( ! is_email( $email ) ) {
			$row['problems'][] = 'email_invalid';
		} else {
			$row['email'] = $email;
			// Everything downstream compares on this and never on the printed address: Airtable
			// holds addresses as they were typed, and `Anna@uek.krakow.pl` and
			// `anna@uek.krakow.pl` are the same mailbox and the same person.
			$row['email_key'] = strtolower( trim( $email ) );
		}

		$profile = isset( $raw['profile'] ) ? trim( (string) $raw['profile'] ) : '';

		if ( '' !== $profile ) {
			$handle = WPCPM_Mentors_Sync::wporg_username( $profile );

			if ( '' === $handle ) {
				// Optional, so the row survives without it. Named, because a school that pasted
				// a column of profiles would otherwise never learn that none of them were read.
				$row['warnings'][] = 'profile_unreadable';
			} else {
				$row['handle'] = $handle;
				// Stored canonically rather than as typed. The base holds these as URLs, the
				// duplicate ladder compares handles, and a column of `@handle`, `handle` and
				// three spellings of the URL is what a school's file actually contains.
				$row['profile'] = 'https://profiles.wordpress.org/' . $handle . '/';
			}
		}

		$study = isset( $raw['field_of_study'] ) ? trim( (string) $raw['field_of_study'] ) : '';

		if ( '' !== $study ) {
			$matched = self::match_choice( $study, WPCPM_Institution_Student_Form::choices( 'field_of_study' ) );

			if ( '' === $matched ) {
				// `create_records()` sends no typecast, so a value spelled any other way is a 422
				// for the whole record. Dropping the field is what keeps one misspelling from
				// costing the school the student.
				$row['warnings'][] = 'field_of_study_unknown';
			} else {
				$row['field_of_study'] = $matched;
			}
		}

		$raw_tutor = isset( $raw['tutor'] ) ? (string) $raw['tutor'] : '';
		$tutor     = self::text( $raw_tutor );

		if ( '' !== $tutor ) {
			if ( self::is_formula( $tutor ) || self::has_control( $raw_tutor ) ) {
				// The same reasoning as the name, and the same characters. Optional here, so it
				// is dropped rather than refusing a student their place over a staff member's
				// name in the wrong column.
				$row['warnings'][] = 'tutor_rejected';
			} elseif ( mb_strlen( $tutor ) > self::MAX_TUTOR ) {
				$row['warnings'][] = 'tutor_too_long';
			} else {
				$row['tutor'] = $tutor;
			}
		}

		if ( ! empty( $row['problems'] ) ) {
			$row['verdict'] = self::INVALID;
		}

		return $row;
	}

	/**
	 * Clean every row, then find the people described twice in one file.
	 *
	 * **Both rows are blocked, never one of them.** A file listing the same address twice is a
	 * file whose author has lost track of it, and picking one of the two to create would be this
	 * plugin deciding which of a school's two lines was the real one. Naming both, with the line
	 * numbers, hands that back to the person who can answer it.
	 *
	 * The comparison runs on `email_key` and on the WordPress.org handle separately, because two
	 * rows can carry one person under two addresses, and a handle is as much an identity here as
	 * a mailbox is.
	 *
	 * @param array $rows Rows from `parse()`.
	 * @return array Cleaned rows, in file order.
	 */
	public static function clean_rows( array $rows ) {
		$clean = array();

		foreach ( $rows as $raw ) {
			$clean[] = self::clean_row( (array) $raw );
		}

		foreach ( array( 'email_key', 'handle' ) as $field ) {
			$seen = array();

			foreach ( $clean as $index => $row ) {
				$value = isset( $row[ $field ] ) ? (string) $row[ $field ] : '';

				// A row already refused for its own reasons is not compared: it is not going to
				// be created, so calling it somebody's duplicate as well would only bury the
				// reason it was actually refused.
				if ( '' === $value || self::INVALID === $row['verdict'] ) {
					continue;
				}

				if ( ! isset( $seen[ $value ] ) ) {
					$seen[ $value ] = $index;
					continue;
				}

				$first = $seen[ $value ];

				foreach ( array( $first, $index ) as $hit ) {
					$clean[ $hit ]['verdict'] = self::DUPLICATE_FILE;
				}

				// Each row names the other, so neither preview line is the one that has to be
				// read alongside its partner to make sense.
				$clean[ $index ]['duplicate_of'] = $clean[ $first ]['line'];
				$clean[ $first ]['duplicate_of'] = $clean[ $index ]['line'];
			}
		}

		return $clean;
	}

	/**
	 * Which delimiter a file uses, read from its first line.
	 *
	 * Excel writes semicolons in most of the countries this program runs in, because the comma
	 * is the decimal separator there. Guessing from the header rather than from the whole file
	 * keeps a comma inside a quoted name from voting.
	 *
	 * @param string $text The file.
	 * @return string One character.
	 */
	private static function delimiter( $text ) {
		$first = preg_split( '/\r\n|\r|\n/', $text );
		$first = isset( $first[0] ) ? $first[0] : '';

		$counts = array(
			','  => substr_count( $first, ',' ),
			';'  => substr_count( $first, ';' ),
			"\t" => substr_count( $first, "\t" ),
		);

		arsort( $counts );
		$best = key( $counts );

		// A single-column file has none of them, and a comma is the answer that makes
		// `fgetcsv()` return that one column rather than nothing.
		return $counts[ $best ] > 0 ? $best : ',';
	}

	/**
	 * Read the text as CSV.
	 *
	 * Through `php://temp` and `fgetcsv()` rather than `explode()` and `str_getcsv()` per line,
	 * because a quoted field may contain the line ending: a school's file with an address on two
	 * lines inside quotes is valid CSV, and splitting on newlines first would tear it in half and
	 * then complain about the half.
	 *
	 * @param string $text      The file.
	 * @param string $delimiter One character.
	 * @return array[] Rows of cells.
	 */
	private static function read_csv( $text, $delimiter ) {
		// `php://temp` is memory, not the filesystem. WP_Filesystem exists so a plugin does not
		// write into a hosting account's files directly; nothing here touches a file at all, and
		// routing an in-memory stream through it would need a real temporary file, which is the
		// opposite of what this module promises about never storing a school's list.
		//
		// The ignore sits on the line above the call and not above this paragraph: it applies to
		// the next line only, and it spent one commit above four lines of prose, silencing them.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( 'php://temp', 'r+' );

		if ( false === $handle ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- as above.
		fwrite( $handle, $text );
		rewind( $handle );

		$rows = array();

		// The assignment is in the condition because that is the shape `fgetcsv()` has: it
		// answers with a row or with false, and reading it twice per iteration would parse the
		// file twice. The silence is for a malformed enclosure, which it warns about rather than
		// refusing, and a PHP warning printed into a dashboard page is not how this reports a
		// bad file - `parse()` does that, with a key the caller turns into a sentence.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $cells = @fgetcsv( $handle, 0, $delimiter, '"', '"' ) ) ) {
			$rows[] = is_array( $cells ) ? $cells : array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
		fclose( $handle );

		return $rows;
	}

	/**
	 * Match the header row against the columns this import knows.
	 *
	 * @param array $header The first row's cells.
	 * @return array{columns:array<string,int>,unknown:string[]}
	 */
	private static function map_header( array $header ) {
		$aliases = self::aliases();
		$columns = array();
		$unknown = array();

		foreach ( $header as $index => $cell ) {
			$key   = self::header_key( $cell );
			$found = '';

			if ( '' === $key ) {
				continue;
			}

			foreach ( $aliases as $canonical => $spellings ) {
				if ( in_array( $key, $spellings, true ) ) {
					$found = $canonical;
					break;
				}
			}

			if ( '' === $found ) {
				$unknown[] = trim( (string) $cell );
				continue;
			}

			// First wins. A file with two `Email` columns is odd but not fatal, and reading the
			// first is at least predictable; taking the last would depend on which the export
			// happened to put where.
			if ( ! isset( $columns[ $found ] ) ) {
				$columns[ $found ] = (int) $index;
			}
		}

		return array(
			'columns' => $columns,
			'unknown' => $unknown,
		);
	}

	/**
	 * Flatten a header cell to something comparable.
	 *
	 * @param string $cell As written in the file.
	 * @return string
	 */
	private static function header_key( $cell ) {
		$key = strtolower( trim( (string) $cell ) );
		$key = preg_replace( '/[^a-z0-9]+/', '_', $key );

		return trim( (string) $key, '_' );
	}

	/**
	 * Whether a row's own `start_date` and `program` cells agree with the batch.
	 *
	 * Empty agrees with anything: a school whose export always carries the columns should not
	 * have to strip them. A program may be named by the status the base holds or by the label
	 * this plugin prints, because both are things a person could reasonably have typed.
	 *
	 * @param array $row   One parsed row.
	 * @param array $batch `status` and `start`.
	 * @return bool
	 */
	private static function agrees_with_batch( array $row, array $batch ) {
		$start = isset( $row['start_date'] ) ? trim( (string) $row['start_date'] ) : '';

		$wanted = trim( (string) ( isset( $batch['start'] ) ? $batch['start'] : '' ) );

		if ( '' !== $start && $wanted !== $start ) {
			return false;
		}

		$program = isset( $row['program'] ) ? strtolower( trim( (string) $row['program'] ) ) : '';

		if ( '' === $program ) {
			return true;
		}

		$status = trim( (string) ( isset( $batch['status'] ) ? $batch['status'] : '' ) );
		$names  = array( strtolower( $status ), strtolower( WPCPM_Program::label( $status ) ) );

		return in_array( $program, $names, true );
	}

	/**
	 * A text cell, with its whitespace collapsed.
	 *
	 * `sanitize_text_field()` already drops tabs and newlines, so the control-character test has
	 * to run on the value before this does. Collapsing runs of spaces is what turns a name
	 * pasted out of a PDF into the name that goes on a certificate.
	 *
	 * @param string $raw The cell.
	 * @return string
	 */
	private static function text( $raw ) {
		$value = sanitize_text_field( (string) $raw );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Whether a value would be read as a formula by a spreadsheet.
	 *
	 * @param string $value Cleaned text.
	 * @return bool
	 */
	private static function is_formula( $value ) {
		return '' !== $value && false !== strpos( '=+-@', substr( $value, 0, 1 ) );
	}

	/**
	 * Whether a value carries a character that breaks a row apart downstream.
	 *
	 * **Callers pass the raw cell, never the cleaned one.** `sanitize_text_field()` removes
	 * tabs and newlines, so this run after it would always answer no and the rule would look
	 * enforced while enforcing nothing. That is how it was written first, and the suite caught
	 * it: a name with a tab in it was being repaired into a plausible name rather than refused.
	 *
	 * @param string $value The cell, as the file had it.
	 * @return bool
	 */
	private static function has_control( $value ) {
		return (bool) preg_match( '/[\t\r\n|]/', (string) $value );
	}

	/**
	 * Match a value against a list of choices, ignoring case and spacing.
	 *
	 * @param string   $value   What the file said.
	 * @param string[] $choices The base's own values.
	 * @return string The choice as the base spells it, or ''.
	 */
	private static function match_choice( $value, array $choices ) {
		$wanted = self::header_key( $value );

		foreach ( $choices as $choice ) {
			if ( self::header_key( $choice ) === $wanted ) {
				return (string) $choice;
			}
		}

		return '';
	}

	/**
	 * Whether every cell of a row is empty.
	 *
	 * @param array $cells One row.
	 * @return bool
	 */
	private static function is_blank( array $cells ) {
		foreach ( $cells as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A refusal, in the shape `parse()` promises.
	 *
	 * @param string $problem Key.
	 * @param array  $detail  Whatever the caller needs to say it.
	 * @return array
	 */
	private static function refuse( $problem, array $detail ) {
		return array(
			'ok'        => false,
			'problem'   => (string) $problem,
			'detail'    => $detail,
			'rows'      => array(),
			'unknown'   => array(),
			'delimiter' => ',',
		);
	}
}
