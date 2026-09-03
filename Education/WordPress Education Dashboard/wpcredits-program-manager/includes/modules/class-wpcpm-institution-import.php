<?php
/**
 * Reading a list of students an institution wants enrolled.
 *
 * This file reads and decides; it never writes. It takes the text of a CSV, or the single row a
 * one-student form posts, turns it into cleaned rows, and checks each of them against the two
 * Airtable tables and this site's accounts to give it a verdict. Nothing here creates a record,
 * writes a post, sends mail or touches a superglobal: the staging, the ceilings, the form and
 * the creation loop are separate pieces, and a test asserts this file reaches none of them.
 *
 * The reading is in two halves that can be used apart. `parse()` and `clean_rows()` need no
 * network at all, which is what lets the shape of a school's file be tested exhaustively; only
 * `check_against_base()` goes out, and it only ever reads.
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
	 * This student is already on this institution's roster. Not created, and said plainly.
	 *
	 * @var string
	 */
	const EXISTS_HERE = 'exists-here';

	/**
	 * A hit somewhere the institution is not allowed to be told about. Not created.
	 *
	 * **The one verdict whose reason is never shown.** The hit may be a student at another
	 * university, a row nobody has linked yet, or an account on this site belonging to a mentor
	 * or a program manager. Answering each of those differently would turn a preview into a
	 * membership oracle: paste three hundred addresses, read three hundred different answers,
	 * and learn who is in the program. Every one of them gets the same sentence, and the reason
	 * is stored for a program manager to read.
	 *
	 * @var string
	 */
	const BLOCKED = 'blocked';

	/**
	 * Somebody of a similar name is already on this roster. A warning, not a refusal.
	 *
	 * @var string
	 */
	const NEAR_NAME = 'near-name';

	/**
	 * How many addresses go into one query against the base.
	 *
	 * The formula is an `OR()` of one test per value and Airtable has a ceiling on its length,
	 * so three hundred rows are asked about in six requests rather than one that is refused or
	 * three hundred that are throttled.
	 *
	 * @var int
	 */
	const CHUNK = 50;

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
			// Written by the ladder, and present from the start so every row has one shape:
			// `detail` is what the institution is shown about this verdict, `manager_reason` is
			// what only a program manager sees. A renderer reading a key that exists on some
			// rows and not others is how one of them ends up printed by accident.
			'detail'         => array(),
			'manager_reason' => '',
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
	 * Check cleaned rows against the base and this site.
	 *
	 * **Six steps, and the institution learns the answer to only one of them.** A row that hits
	 * this institution's own roster is told so, in detail, because it is their own list. A row
	 * that hits anything else is told it cannot be imported from here and nothing more: which
	 * other university, whether the person has an account, whether a record exists at all are
	 * all facts about people outside this school, and a preview that answered them per row would
	 * be a lookup service for anyone who could paste a list of addresses.
	 *
	 * Rows already refused by the cleaner are not looked up: they are not going to be created,
	 * and asking about them would spend requests to change nothing.
	 *
	 * @param array          $rows        Rows from `clean_rows()`.
	 * @param string         $institution Airtable Institutions record ID this import is for.
	 * @param WPCPM_Airtable $airtable    A configured client.
	 * @param array          $settings    Plugin settings, for the two table IDs.
	 * @return array|WP_Error The rows with their verdicts, or the first error from the base.
	 */
	public static function check_against_base( array $rows, $institution, $airtable, array $settings ) {
		$fields  = WPCPM_Mentors_Sync::fields();
		$pending = array();

		foreach ( $rows as $index => $row ) {
			if ( self::OK === $row['verdict'] ) {
				$pending[ $index ] = $row;
			}
		}

		if ( empty( $pending ) ) {
			return $rows;
		}

		$emails  = array();
		$handles = array();

		foreach ( $pending as $row ) {
			if ( '' !== $row['email_key'] ) {
				$emails[] = $row['email_key'];
			}

			// Under three characters is not a handle worth a substring search: `FIND('an', ...)`
			// matches most of the base and every one of those rows would then be discarded in
			// PHP, which is a request spent to learn nothing.
			if ( '' !== $row['handle'] && mb_strlen( $row['handle'] ) >= 3 ) {
				$handles[] = $row['handle'];
			}
		}

		$students = self::lookup_by_email( $airtable, $settings['students_table'], $fields['student_email'], $emails, $fields, 'students' );

		if ( is_wp_error( $students ) ) {
			return $students;
		}

		$reports = self::lookup_by_email( $airtable, $settings['reports_table'], $fields['report_email'], $emails, $fields, 'reports' );

		if ( is_wp_error( $reports ) ) {
			return $reports;
		}

		$by_handle = self::lookup_by_handle( $airtable, $settings, $fields, $handles );

		if ( is_wp_error( $by_handle ) ) {
			return $by_handle;
		}

		$roster = WPCPM_Roster_Index::rows( $institution );

		foreach ( $pending as $index => $row ) {
			$rows[ $index ] = self::judge( $row, $institution, $students, $reports, $by_handle, $roster );
		}

		return $rows;
	}

	/**
	 * One row's verdict, given everything the ladder found.
	 *
	 * The order is deliberate. This institution's own roster is checked first, so a school
	 * re-uploading last term's file is told "already on your roster" rather than the blank
	 * refusal, which would be both unhelpful and untrue. Everything else collapses to one
	 * answer.
	 *
	 * @param array  $row         A cleaned row.
	 * @param string $institution The institution this import is for.
	 * @param array  $students    Students rows by email key.
	 * @param array  $reports     Students Reports rows by email key.
	 * @param array  $by_handle   Rows from either table, by handle.
	 * @param array  $roster      This institution's index rows.
	 * @return array
	 */
	private static function judge( array $row, $institution, array $students, array $reports, array $by_handle, array $roster ) {
		$student = isset( $students[ $row['email_key'] ] ) ? $students[ $row['email_key'] ] : null;
		$report  = isset( $reports[ $row['email_key'] ] ) ? $reports[ $row['email_key'] ] : null;

		if ( is_array( $student ) && in_array( $institution, $student['institutions'], true ) ) {
			$row['verdict'] = self::EXISTS_HERE;
			$row['detail']  = array(
				'name'   => $student['name'],
				'status' => $student['status'],
				'start'  => $student['start'],
				'record' => $student['record_id'],
			);

			return $row;
		}

		// A reports row on this institution with no Students row behind it is a state only a
		// program manager can finish, and saying so is more use than the blank refusal: the
		// school is not being kept from anything, the record is half-made.
		if ( ! is_array( $student ) && is_array( $report ) && in_array( $institution, $report['institutions'], true ) ) {
			$row['verdict'] = self::EXISTS_HERE;
			$row['detail']  = array(
				'name'         => $report['name'],
				'status'       => $report['status'],
				'start'        => '',
				'record'       => '',
				'reports_only' => true,
			);

			return $row;
		}

		// **From here down, one answer.** What differs between these branches is only what a
		// program manager is told afterwards.
		$reason = '';

		if ( is_array( $student ) ) {
			$reason = empty( $student['institutions'] )
				? 'students row with no institution'
				: 'students row at another institution';
		} elseif ( is_array( $report ) ) {
			$reason = 'students reports row elsewhere';
		} elseif ( '' !== $row['handle'] && isset( $by_handle[ $row['handle'] ] ) ) {
			$reason = 'wordpress.org profile already on a record';
		} elseif ( '' !== $row['email'] && get_user_by( 'email', $row['email'] ) ) {
			// A site account of any kind: a student, a mentor, a program manager, another
			// school's member. Which of those it is, is exactly what must not be answered.
			$reason = 'account on this site';
		}

		if ( '' !== $reason ) {
			$row['verdict']        = self::BLOCKED;
			$row['manager_reason'] = $reason;
			// Nothing shown. The sentence the institution sees is one constant, written where
			// the preview is rendered, so no branch here can accidentally vary it.
			$row['detail'] = array();

			return $row;
		}

		$near = self::near_name( $row['name'], $roster );

		if ( '' !== $near ) {
			// Soft: the row is still created. Two people at one university do share a name, and
			// refusing on a resemblance would make the school argue with a robot about it.
			$row['verdict'] = self::NEAR_NAME;
			$row['detail']  = array( 'near' => $near );
		}

		return $row;
	}

	/**
	 * Rows of one table whose address is in this list, keyed by lowercased address.
	 *
	 * @param WPCPM_Airtable $airtable A configured client.
	 * @param string         $table    Table ID.
	 * @param string         $column   The email column's name in that table.
	 * @param string[]       $emails   Lowercased addresses.
	 * @param array          $fields   `WPCPM_Mentors_Sync::fields()`.
	 * @param string         $kind     `students` or `reports`, for the columns to read back.
	 * @return array|WP_Error
	 */
	private static function lookup_by_email( $airtable, $table, $column, array $emails, array $fields, $kind ) {
		$found = array();

		if ( empty( $emails ) ) {
			return $found;
		}

		$wanted = 'students' === $kind
			? array( $fields['student_record_name'], $fields['student_email'], $fields['student_status'], $fields['student_institution'], $fields['student_start'] )
			: array( $fields['report_name'], $fields['report_email'], $fields['report_status'], $fields['report_instituton'] );

		foreach ( array_chunk( array_unique( $emails ), self::CHUNK ) as $chunk ) {
			$records = $airtable->fetch_all(
				$table,
				array(
					// The third argument is what makes this a comparison of mailboxes rather
					// than of spellings: the base holds addresses as they were typed.
					'formula' => $airtable->formula_in( $column, $chunk, true ),
					'fields'  => $wanted,
				)
			);

			if ( is_wp_error( $records ) ) {
				return $records;
			}

			foreach ( $records as $record ) {
				$row = self::shape_hit( $record, $fields, $kind );

				if ( '' !== $row['email_key'] ) {
					$found[ $row['email_key'] ] = $row;
				}
			}
		}

		return $found;
	}

	/**
	 * Rows of either table carrying one of these handles, keyed by handle.
	 *
	 * **`FIND()` finds candidates; PHP decides.** The base holds profiles as URLs, so a handle
	 * has to be looked for inside them, and a substring search says yes to `ann` inside
	 * `joanna`. Every value that comes back is put through the same normaliser the import used
	 * on the file and compared for exact equality, which also means a row holding
	 * `profiles.wordpress.org/annak` and one holding `https://profiles.wordpress.org/annak/`
	 * both match, and neither can be defeated by writing the URL a third way.
	 *
	 * @param WPCPM_Airtable $airtable A configured client.
	 * @param array          $settings Plugin settings.
	 * @param array          $fields   `WPCPM_Mentors_Sync::fields()`.
	 * @param string[]       $handles  Handles of three characters or more.
	 * @return array|WP_Error
	 */
	private static function lookup_by_handle( $airtable, array $settings, array $fields, array $handles ) {
		$found = array();

		if ( empty( $handles ) ) {
			return $found;
		}

		$tables = array(
			array( $settings['students_table'], $fields['student_profile'] ),
			array( $settings['reports_table'], $fields['report_profile'] ),
		);

		foreach ( $tables as $pair ) {
			list( $table, $column ) = $pair;

			foreach ( array_chunk( array_unique( $handles ), self::CHUNK ) as $chunk ) {
				$records = $airtable->fetch_all(
					$table,
					array(
						'formula' => $airtable->formula_contains( $column, $chunk ),
						'fields'  => array( $column ),
					)
				);

				if ( is_wp_error( $records ) ) {
					return $records;
				}

				foreach ( $records as $record ) {
					$cells  = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();
					$value  = WPCPM_Airtable::flatten( isset( $cells[ $column ] ) ? $cells[ $column ] : '' );
					$handle = WPCPM_Mentors_Sync::wporg_username( $value );

					if ( '' !== $handle && in_array( $handle, $chunk, true ) ) {
						$found[ $handle ] = true;
					}
				}
			}
		}

		return $found;
	}

	/**
	 * The fields of one hit, in the shape `judge()` reads.
	 *
	 * @param array  $record One Airtable record.
	 * @param array  $fields `WPCPM_Mentors_Sync::fields()`.
	 * @param string $kind   `students` or `reports`.
	 * @return array
	 */
	private static function shape_hit( array $record, array $fields, $kind ) {
		$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();
		$get   = function ( $column ) use ( $cells ) {
			return WPCPM_Airtable::flatten( isset( $cells[ $column ] ) ? $cells[ $column ] : '' );
		};

		if ( 'students' === $kind ) {
			$email = $get( $fields['student_email'] );

			return array(
				'record_id'    => isset( $record['id'] ) ? (string) $record['id'] : '',
				'name'         => $get( $fields['student_record_name'] ),
				'email_key'    => strtolower( trim( $email ) ),
				'status'       => $get( $fields['student_status'] ),
				'start'        => $get( $fields['student_start'] ),
				'institutions' => WPCPM_Airtable::link_ids( isset( $cells[ $fields['student_institution'] ] ) ? $cells[ $fields['student_institution'] ] : array() ),
			);
		}

		$email = $get( $fields['report_email'] );

		return array(
			'record_id'    => isset( $record['id'] ) ? (string) $record['id'] : '',
			'name'         => $get( $fields['report_name'] ),
			'email_key'    => strtolower( trim( $email ) ),
			'status'       => $get( $fields['report_status'] ),
			'start'        => '',
			'institutions' => WPCPM_Airtable::link_ids( isset( $cells[ $fields['report_instituton'] ] ) ? $cells[ $fields['report_instituton'] ] : array() ),
		);
	}

	/**
	 * A name already on this roster that this one resembles, or ''.
	 *
	 * Compared on the letters alone, with case, spacing and punctuation removed, so that
	 * "Anna Kowalska" and "anna kowalska" are one person and "Anna Kowalska-Nowak" is not. The
	 * point is a school re-typing somebody they already sent, not fuzzy matching: this is a
	 * warning attached to a row that still gets created, and a cleverer comparison would only
	 * produce warnings nobody can act on.
	 *
	 * @param string $name   The cleaned name from the file.
	 * @param array  $roster This institution's index rows.
	 * @return string The roster name it resembles, or ''.
	 */
	private static function near_name( $name, array $roster ) {
		$key = self::name_key( $name );

		if ( '' === $key ) {
			return '';
		}

		foreach ( $roster as $row ) {
			$other = isset( $row['name'] ) ? (string) $row['name'] : '';

			if ( '' !== $other && self::name_key( $other ) === $key ) {
				return $other;
			}
		}

		return '';
	}

	/**
	 * A name reduced to its letters and digits, lowercased.
	 *
	 * @param string $name A name.
	 * @return string
	 */
	private static function name_key( $name ) {
		$key = preg_replace( '/[^\p{L}\p{N}]+/u', '', (string) $name );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $key ) : strtolower( (string) $key );
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
