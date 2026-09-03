<?php
/**
 * Institutions module - the two CSV exports.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The roster export and the single-student export, as files a spreadsheet opens.
 *
 * **Everything a school may read on a screen it may take away as a file, and nothing else.**
 * The roster export is the roster: the same rows, from the same index, dropped by the same
 * rule, grouped by the same four groups, narrowed by the same cohort. The single-student
 * export is the student's card, plus the course grades that card is the one screen to show
 * (design spec 7.5). Neither reaches past what the fence already allowed, and both ask
 * `WPCPM_Institution_Policy::decide()` with `ACT_EXPORT` and pass the answer through
 * `scope()` before a byte is written, so a ground that one day narrows fields narrows the
 * files as well as the pages.
 *
 * Two properties are the whole reason this class exists rather than a loop in a handler.
 *
 * **A UTF-8 BOM is written first.** Excel reads a BOM-less UTF-8 CSV as the machine's legacy
 * code page, and every accented letter then arrives as two wrong characters. The program's
 * rosters are made of such names: Universidad Fidelitas carries an accent in the base, and so
 * do most of the Spanish, Portuguese and Polish student names underneath it. A school opening
 * a file of its own students to find their names broken has been handed a worse artefact than
 * none. The import side refuses a Latin-1 file for the same reason, from the other direction.
 *
 * **Any cell whose first character is `=`, `+`, `-` or `@` is prefixed with an apostrophe.**
 * Those four characters begin a formula in every spreadsheet this file will be opened in, and
 * a formula in an imported cell has been a remote-code-execution route in Excel for years.
 * This is the export side of the rule `WPCPM_Institution_Import` enforces by refusing a name
 * that starts with one; here the value is already in the base, put there by somebody the
 * program cannot go back and ask, so it is neutralised rather than refused. Refusing at this
 * end would mean a school unable to export its own roster because of one row it did not write.
 *
 * **`accessibility` is absent from both files**, asserted in `bin/test-institution-export.php`
 * over the header row of each. It sits on the Students table and inside the cached
 * `wpcpm_student_program` block, which is where two of these columns are read from, so both
 * sources this class touches carry it. It was disclosed to the program to be accommodated,
 * not to the school (design spec 7.5 and decision 14). That is why every cell is written out
 * by name here and no row is ever built by looping over a cached block.
 *
 * There is no ceiling on either export and there should not be: the roster export reads an
 * option the reader is already looking at, and the largest institution in the base has 41
 * students. The single-student export makes one live claim and one record read, which is what
 * opening the same student's card already costs.
 */
class WPCPM_Institution_Export {

	/** The roster export. A `wp_nonce_url()` link on the dashboard. */
	const ACTION_ROSTER = 'wpcpm_export_roster';

	/** The single-student export. Nonce keyed to the subject account. */
	const ACTION_STUDENT = 'wpcpm_export_student';

	/** The single-student export's argument: which account to write out. */
	const ARG_STUDENT = 'wpcpm_export_student_id';

	/**
	 * The cohort filter's argument, which is `WPCPM_Institution_Roster_View`'s.
	 *
	 * Spelled out rather than aliased to that class's constant, which would make this file
	 * unloadable before that one for the sake of one string. `bin/test-institution-export.php`
	 * asserts the two are equal, so a rename there fails a test here instead of quietly
	 * exporting the whole roster from a link that asked for one semester.
	 */
	const ARG_COHORT = 'wpcpm_cohort';

	/**
	 * The UTF-8 byte order mark, written before the first row of every file.
	 *
	 * Not optional and not a setting: see the class docblock. Excel is the program's most
	 * common reader and it needs this to read UTF-8 at all.
	 */
	const BOM = "\xEF\xBB\xBF";

	/** The apostrophe a risky cell is prefixed with, which every spreadsheet reads as "text". */
	const NEUTRALISER = "'";

	/**
	 * The characters that begin a formula.
	 *
	 * The same four `WPCPM_Institution_Import` refuses in a name, listed here as data so the
	 * two halves of one rule can be read side by side.
	 */
	const FORMULA_LEADERS = array( '=', '+', '-', '@' );

	/** One column separator, for every locale. See `line()` for why it is not the semicolon. */
	const DELIMITER = ',';

	/** CRLF, per RFC 4180. See `csv()`. */
	const EOL = "\r\n";

	/**
	 * Hooks.
	 *
	 * No `admin_post_nopriv_` counterpart, unlike the agreement download: these links are
	 * drawn on a dashboard nobody reaches without signing in, so there is no emailed link to
	 * rescue with a redirect to the login form.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_ROSTER, array( __CLASS__, 'handle_roster' ) );
		add_action( 'admin_post_' . self::ACTION_STUDENT, array( __CLASS__, 'handle_student' ) );
	}

	/*
	 * The columns
	 * --------------------------------------------------------------------
	 */

	/**
	 * Every column both files share, keyed `<table>|<column>` as the fence's `scope()` is.
	 *
	 * **The keys are the roster screen's keys**, and `bin/test-institution-export.php` asserts
	 * that every key in `WPCPM_Institution_Roster_View::columns()` appears here. The assertion
	 * is the link between the two lists rather than a shared array, because the headings and
	 * the values legitimately differ - and one shared array would force a screen's phrasing on
	 * a file, or the other way round. What must not differ is which Airtable column each key
	 * names, or a ground that scopes fields would narrow the page and not the file.
	 *
	 * Three of the differences are deliberate:
	 *
	 * - The screen prints both dates in one cell and counts the days left in the next. A file
	 *   gets a start date column and an end date column, because a spreadsheet sorts and
	 *   filters on dates and cannot do either with "3 September 2026 to 3 March 2027"; and
	 *   there is no days-left column at all, because a number that is wrong tomorrow does not
	 *   belong in a file somebody keeps, while an end date is true for as long as the file is.
	 * - `site|group` carries the heading the row sits under on screen. A grouped page turned
	 *   into a flat file loses its headings, and "which of these four lists is this student
	 *   on" is the question the groups exist to answer.
	 * - `site|cohort` is derived from the start date the same way the picker is, so a file
	 *   pulled from a filtered roster says which semester it is a file of.
	 *
	 * **There is no email column**, which is not an omission: the student card writes its rows
	 * out one by one and has no address among them either, because a school reaching its own
	 * students is not something the program's roster is for, and the semester report forbids an
	 * address outright (design spec 7.9). The one address either surface names is the mentor's.
	 *
	 * **The hours column is here now**, and this paragraph used to say the opposite in a bolded
	 * rule: that there was none, because the roster index had nowhere to hold one. It has since,
	 * and the roster screen prints it, so a file without it would no longer match the page it
	 * was taken from. The number is written as the base holds it, unformatted: "12 of 150" is
	 * for reading and a spreadsheet wants something it can add up.
	 *
	 * Both suites hold this pair together, and one of them will go red the next time a column
	 * is added to the screen and not to the file.
	 *
	 * @return array<string, string> Column key to heading.
	 */
	public static function columns() {
		return array(
			'site|group'                     => __( 'Roster group', 'wpcredits-program-manager' ),
			'students|Full Name'             => __( 'Student', 'wpcredits-program-manager' ),
			'students|Status'                => __( 'Program', 'wpcredits-program-manager' ),
			'students|Start Date'            => __( 'Start date', 'wpcredits-program-manager' ),
			'students|End Date'              => __( 'End date', 'wpcredits-program-manager' ),
			'site|cohort'                    => __( 'Cohort', 'wpcredits-program-manager' ),
			'students|Mentor'                => __( 'Mentor', 'wpcredits-program-manager' ),
			'students|WP Profile'            => __( 'WordPress.org', 'wpcredits-program-manager' ),
			'reports|Main Contribution Team' => __( 'Team', 'wpcredits-program-manager' ),
			'reports|Personal Website URL'   => __( 'Website', 'wpcredits-program-manager' ),
			'students|Your field of study'   => __( 'Field of study', 'wpcredits-program-manager' ),
			'students|Tutor '                => __( 'Tutor', 'wpcredits-program-manager' ),
			'reports|Hours'                  => __( 'Hours', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The course grade columns for one track, which only the single-student export carries.
	 *
	 * **Grades appear on one screen and in one file** (design spec 7.5, and open question 5 of
	 * the first spec, kept): the student's own card and the export of that card. They are not
	 * on the roster, not in a count, and not in the semester report, so a school cannot obtain
	 * a grade list of a cohort by pressing one button - which is the difference between a
	 * school reading a student's record and a school ranking its students on the program's data.
	 *
	 * Read from `WPCPM_Student_Report_Form::fields()` rather than written out again, because
	 * the base's spelling of these columns is settled in that file and a second copy is a
	 * second thing to keep in step. The filter is the two properties a grade has there: the
	 * `onboarding` group and a numeric type. That takes the final grades and the three course
	 * marks, and leaves out `WordPress Profile` and `Slack Name` (same group, not numbers, and
	 * both already columns of their own here) and the developer track's two textareas.
	 *
	 * `Hours` is in the `hours` group and so is not picked up, which is what is wanted: hours
	 * are Phase 5's through both syncs, and decision 23 keeps them out of the document a school
	 * writes. A number in a file that no institution surface shows would be this file inventing
	 * a disclosure of its own.
	 *
	 * @param string $track Track key from `WPCPM_Program::track()`: `150h`, `50h` or `dev`.
	 * @return array<string, string> Column key to heading, keyed `reports|<Airtable column>`.
	 */
	public static function grade_columns( $track ) {
		$columns = array();

		foreach ( WPCPM_Student_Report_Form::fields( $track ) as $name => $spec ) {
			$group = isset( $spec['group'] ) ? (string) $spec['group'] : '';
			$type  = isset( $spec['type'] ) ? (string) $spec['type'] : '';

			if ( 'onboarding' !== $group || 'number' !== $type ) {
				continue;
			}

			$columns[ 'reports|' . $name ] = isset( $spec['label'] ) ? (string) $spec['label'] : $name;
		}

		return $columns;
	}

	/**
	 * Every column of the single-student export: the shared ones, then this track's grades.
	 *
	 * `+` rather than `array_merge()`, and here that is the safe direction rather than the
	 * usual trap: the left operand wins, so the day the base names a course the same as one of
	 * the shared columns, the shared column keeps its meaning and the file gains nothing it
	 * cannot explain. `array_merge()` would let the grade quietly take the column over.
	 *
	 * @param string $track Track key from `WPCPM_Program::track()`.
	 * @return array<string, string> Column key to heading.
	 */
	public static function student_columns( $track ) {
		return self::columns() + self::grade_columns( $track );
	}

	/*
	 * Building the files
	 * --------------------------------------------------------------------
	 */

	/**
	 * The roster export, as a matrix: one header row, then one row per student.
	 *
	 * Rows come from `WPCPM_Roster_Index::groups()` and from nothing else, which is what makes
	 * the file and the screen the same list: the cohort narrows first, `SPAM` and `Duplicated`
	 * are dropped by the index's own contract, and the four groups are the four the dashboard
	 * prints, in its order. A second walk over `rows()` here would be a second grouping rule
	 * to keep in step, and the first thing it would disagree about is which rows are people.
	 *
	 * An empty matrix means the decision permitted no columns, which is a refusal and not an
	 * empty roster; a matrix holding only its header means an institution with no students,
	 * which is a file worth sending. The handler tells the two apart.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $cohort    A `WPCPM_Cohort` key to narrow to; anything else exports every row.
	 * @param array  $decision  What `decide( ACT_EXPORT, ... )` returned.
	 * @return array[] Rows of cells, the first being the headings.
	 */
	public static function roster_matrix( $record_id, $cohort, array $decision ) {
		$columns = WPCPM_Institution_Policy::scope( $decision, self::columns() );

		if ( empty( $columns ) ) {
			return array();
		}

		$matrix = array( array_values( $columns ) );
		$groups = WPCPM_Roster_Index::groups( $record_id, $cohort );

		foreach ( WPCPM_Institution_Roster_View::group_labels() as $group => $label ) {
			foreach ( isset( $groups[ $group ] ) ? (array) $groups[ $group ] : array() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$matrix[] = self::pick( $columns, self::row_cells( $row, $label, isset( $row['user_id'] ) ? (int) $row['user_id'] : 0 ) );
			}
		}

		return $matrix;
	}

	/**
	 * The single-student export, as a matrix: one header row and one row.
	 *
	 * The same shape as the roster export on purpose. A school that exports a roster in the
	 * morning and a student in the afternoon can put the second file under the first, and a
	 * two-column "field, value" layout - the obvious way to write one record out - could not be.
	 *
	 * The grades are handed in rather than fetched: reading them is a live disclosure and
	 * belongs behind `handle_student()`'s claim, and a builder that fetched would be a builder
	 * that could be called before the fence. An empty array is a student with no report record
	 * yet, and the grade columns print empty, which is the truth about somebody nobody has
	 * assigned a mentor to.
	 *
	 * @param int     $user_id  The student's account.
	 * @param array   $decision What `decide( ACT_EXPORT, ... )` returned, or the claim's.
	 * @param array   $values   The Students Reports record's fields, or an empty array.
	 * @param WP_User $student  The student's account, already resolved by the caller.
	 * @return array[] Rows of cells, the first being the headings.
	 */
	public static function student_matrix( $user_id, array $decision, array $values, WP_User $student ) {
		$user_id     = (int) $user_id;
		$institution = isset( $decision['institution'] ) ? (string) $decision['institution'] : '';

		// The decision's institution and never the request's, for the reason
		// `WPCPM_Institution_Notes` gives about a notebook: `decide()` has already named the
		// institution this reader was allowed on, and reading the row from any other one would
		// be a second answer to a question that has been answered.
		$row = self::index_row( $institution, $user_id );

		// The program block, opened for `program` and `name` here and for `team` and `website`
		// in `row_cells()`, and never walked: it carries `accessibility` in the same array, two
		// keys along from `field_of_study`, and a loop would print it.
		$program = get_user_meta( $user_id, WPCPM_Students_Sync::META_PROGRAM, true );
		$program = is_array( $program ) ? $program : array();

		$status = self::first( array( self::from( $program, 'program' ), self::from( $row, 'status' ) ) );
		$track  = WPCPM_Program::track( $status );

		$columns = WPCPM_Institution_Policy::scope( $decision, self::student_columns( $track ) );

		if ( empty( $columns ) ) {
			return array();
		}

		// The account's ID and not the index row's. A student placed from the reports side has
		// no Students row at all - the dashboard's fifth list - so their row here is empty, and
		// their team, website and mentor would come out blank if the cells were built from it.
		$cells = self::row_cells( $row, self::group_label( $institution, self::from( $row, 'record_id' ) ), $user_id );

		// The name the student signs in under wins, as it does on their card: the roster's link
		// was clicked on it, and a file naming them something else is a file about somebody else.
		$cells['students|Full Name'] = self::first( array( (string) $student->display_name, self::from( $program, 'name' ), $cells['students|Full Name'] ) );
		$cells['students|Status']    = '' === $status ? '' : WPCPM_Program::label( $status );

		foreach ( array_keys( self::grade_columns( $track ) ) as $key ) {
			$name = substr( $key, strlen( 'reports|' ) );
			$mark = isset( $values[ $name ] ) ? $values[ $name ] : null;

			// `is_scalar()` and not a truth test: a final grade of 0 is a grade, and printing
			// it as an empty cell would turn a mark somebody earned into a course they never took.
			$cells[ $key ] = is_scalar( $mark ) ? (string) $mark : '';
		}

		return array( array_values( $columns ), self::pick( $columns, $cells ) );
	}

	/**
	 * One student's cells, keyed as `columns()` is, as plain text.
	 *
	 * Written out one cell at a time, never a loop over the row or over the cached program
	 * block, because both carry the accessibility disclosure and a loop would carry it here on
	 * the release somebody added a key. Plain text and not the screen's markup: a CSV cell
	 * holding an anchor is a cell nothing can sort on.
	 *
	 * The team and the website live on the Students Reports row and reach this file through
	 * `wpcpm_student_program`, which is the only reason that block is opened at all; two keys
	 * are read out of it and nothing else.
	 *
	 * @param array  $row     An index row, possibly empty.
	 * @param string $group   The heading of the group this row sits under on screen.
	 * @param int    $user_id The student's account, or 0 where there is none. Passed rather
	 *                        than read off the row, because the single-student export has an
	 *                        account for a student whose index row is empty.
	 * @return array<string, string> Column key to cell text.
	 */
	private static function row_cells( array $row, $group, $user_id ) {
		$user_id = (int) $user_id;
		$program = $user_id > 0 ? get_user_meta( $user_id, WPCPM_Students_Sync::META_PROGRAM, true ) : array();
		$mentor  = $user_id > 0 ? get_user_meta( $user_id, WPCPM_Students_Sync::META_MENTOR, true ) : array();
		$program = is_array( $program ) ? $program : array();
		$mentor  = is_array( $mentor ) ? $mentor : array();

		$start    = self::from( $row, 'start' );
		$status   = self::first( array( self::from( $program, 'program' ), self::from( $row, 'status' ) ) );
		$username = self::from( $row, 'username' );

		return array(
			'site|group'                     => (string) $group,
			'students|Full Name'             => self::from( $row, 'name' ),
			// The program the student is on, not the pipeline status: it is what the screen's
			// badge says, and `site|group` above already carries "finished" or "did not start".
			'students|Status'                => '' === $status ? '' : WPCPM_Program::label( $status ),
			// The dates exactly as the base holds them, which is ISO. A spreadsheet reads
			// `2026-09-03` as a date in every locale; a formatted date is a string in most.
			'students|Start Date'            => $start,
			'students|End Date'              => self::from( $row, 'end' ),
			'site|cohort'                    => WPCPM_Cohort::label( WPCPM_Cohort::key( $start ) ),
			'students|Mentor'                => self::from( $mentor, 'name' ),
			// The handle, not the URL. A school pasting this into its own system wants the
			// name; the profile address is one prefix away and is on the card.
			'students|WP Profile'            => $username,
			'reports|Main Contribution Team' => self::from( $program, 'team' ),
			'reports|Personal Website URL'   => self::from( $program, 'website' ),
			'students|Your field of study'   => self::from( $row, 'field_of_study' ),
			'students|Tutor '                => self::from( $row, 'tutor' ),
			'reports|Hours'                  => self::first( array( self::from( $program, 'hours' ), self::from( $row, 'hours' ) ) ),
		);
	}

	/**
	 * The cells the scoped columns name, in the columns' order, missing ones empty.
	 *
	 * The order is the header's and not the cell array's, so a cell added out of order lands
	 * under its own heading rather than shifting every column to its right by one.
	 *
	 * @param array $columns Scoped columns, key to heading.
	 * @param array $cells   Cell text keyed by column key.
	 * @return string[] One row of cells.
	 */
	private static function pick( array $columns, array $cells ) {
		$out = array();

		foreach ( array_keys( $columns ) as $key ) {
			$out[] = ( isset( $cells[ $key ] ) && is_scalar( $cells[ $key ] ) ) ? (string) $cells[ $key ] : '';
		}

		return $out;
	}

	/*
	 * Writing CSV
	 * --------------------------------------------------------------------
	 */

	/**
	 * A matrix as the bytes of a CSV file.
	 *
	 * **The BOM is written here and only here**, before anything else, so no caller can send a
	 * file without one. See the class docblock for what a BOM-less file does to every accented
	 * name in the program.
	 *
	 * CRLF between rows, per RFC 4180. `fputcsv()` writes a bare newline, which Excel reads
	 * happily and Notepad and a handful of older Windows tools show as one very long line; a
	 * school that opens the file in the wrong thing first should still see a table.
	 *
	 * @param array[] $matrix Rows of cells; the first is normally the headings.
	 * @return string The file, ready to send.
	 */
	public static function csv( array $matrix ) {
		$out = self::BOM;

		foreach ( $matrix as $row ) {
			$out .= self::line( is_array( $row ) ? $row : array() ) . self::EOL;
		}

		return $out;
	}

	/**
	 * One row of cells as one CSV line, without its terminator.
	 *
	 * Every cell goes through `cell()` on the way in, headings included, so the neutralising is
	 * a property of writing a line rather than something each builder has to remember.
	 *
	 * The comma and not the semicolon, although Excel in most of the program's countries
	 * *exports* semicolons: the BOM tells Excel this file is UTF-8, and a BOM-marked file is
	 * read with the machine's list separator whatever this end chooses, so the comma costs
	 * nothing and is the one separator every other tool agrees on. The import side detects
	 * either, which is where the asymmetry belongs.
	 *
	 * Through `fputcsv()` rather than joining strings, because quoting is the part of CSV that
	 * goes wrong: a tutor named `O'Brien, Jr.` and a name holding a quotation mark are both
	 * ordinary and both break a hand-rolled writer.
	 *
	 * @param array $cells One row.
	 * @return string
	 */
	private static function line( array $cells ) {
		// `php://temp` is memory, not the filesystem. WP_Filesystem exists so a plugin does not
		// write into a hosting account's files directly; nothing here touches a file at all,
		// and routing an in-memory stream through it would need a real temporary file - which
		// is precisely what an export of a school's students must not leave lying about.
		//
		// Each ignore sits on the line above the call it silences and applies to that line only.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( 'php://temp', 'r+' );

		if ( false === $handle ) {
			return '';
		}

		$safe = array();

		foreach ( $cells as $value ) {
			$safe[] = self::cell( $value );
		}

		// The escape character is disabled. PHP's default is a backslash, which is not part of
		// CSV at all: it makes `C:\path\` come back out unquoted and unreadable to every other
		// parser, RFC 4180 having exactly one escape, the doubled quotation mark.
		fputcsv( $handle, $safe, self::DELIMITER, '"', '' );
		rewind( $handle );

		$line = stream_get_contents( $handle );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		// `fputcsv()` appends its own newline; the terminator is `csv()`'s to choose.
		return rtrim( is_string( $line ) ? $line : '', "\r\n" );
	}

	/**
	 * One cell, with a leading formula character neutralised.
	 *
	 * **A cell that begins `=`, `+`, `-` or `@` is a formula to every spreadsheet this file
	 * will be opened in**, and an imported formula has been a code execution route in Excel for
	 * years. The apostrophe is the one prefix every spreadsheet reads as "the rest of this is
	 * text"; it is stripped again by the same programs on display, so the school sees the value
	 * that is in the base.
	 *
	 * Leading whitespace is looked through rather than trusted, because Excel's own import
	 * trims a cell before deciding whether it is a formula: a first-character test alone would
	 * let ` =HYPERLINK(...)` past, which is the same evasion with a space in front of it.
	 *
	 * Neutralised and never refused. The import refuses, because there the value is being
	 * created and no real name starts with those; here the value already exists in the base,
	 * written by somebody nobody can go back and ask, and refusing would leave a school unable
	 * to export its own roster because of one row it did not write.
	 *
	 * @param mixed $value A cell's value.
	 * @return string
	 */
	public static function cell( $value ) {
		$text = is_scalar( $value ) ? (string) $value : '';

		if ( '' === $text ) {
			return '';
		}

		$leader = ltrim( $text, " \t\n\r\v\f\0" );

		if ( '' === $leader || ! in_array( substr( $leader, 0, 1 ), self::FORMULA_LEADERS, true ) ) {
			return $text;
		}

		return self::NEUTRALISER . $text;
	}

	/*
	 * The handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Write out one institution's roster.
	 *
	 * The nonce is checked first although nothing here reaches Airtable, because the very next
	 * step reads the manager switcher out of the request: a cross-site link must not be able to
	 * pick which institution a signed-in manager exports.
	 *
	 * **The institution is `resolve_institution()`'s answer and is never read from the request
	 * as an institution** (design spec 5.5): a member's own stamp, a manager's switcher, and an
	 * outright refusal when it resolves to nothing, because "no institution" must never be
	 * treated as "any institution".
	 */
	public static function handle_roster() {
		check_admin_referer( self::ACTION_ROSTER );

		$institution = WPCPM_Institution_Roster::resolve_institution( null, current_user_can( WPCPM_Roles::CAP_MANAGE ) );

		if ( '' === $institution ) {
			self::refuse();
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EXPORT,
			WPCPM_Institution_Policy::subject_institution( $institution )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::refuse();
		}

		$cohort = self::requested_cohort();
		$matrix = self::roster_matrix( $institution, $cohort, $decision );

		// An empty matrix is a decision that permitted no column at all, which is a refusal
		// wearing the shape of a file. A matrix of one row is a school with no students yet,
		// and that is a file worth sending: the headings say what would have been in it.
		if ( empty( $matrix ) ) {
			self::refuse();
		}

		self::send( self::csv( $matrix ), self::roster_filename( $institution, $cohort ) );
	}

	/**
	 * Write out one student, with their course grades.
	 *
	 * The live-disclosure pattern of design spec 5.4, in its order: the nonce, then the cheap
	 * decision on the account's own stamp, then `claim()`, and only then the record read that
	 * carries the grades. The read comes last because it is the disclosure: a request the claim
	 * refuses must not have caused it.
	 *
	 * **The claim is the grades' fence, not the row's.** A student waiting for a mentor has no
	 * Students Reports row, because the automation that creates one fires on the assignment; so
	 * there is no record to claim and no grade to disclose, and the export is the cached row the
	 * roster is already showing with its grade columns empty. Refusing instead would make
	 * "waiting for a mentor" an unexportable state, which for a school's first term is most of
	 * its roster.
	 */
	public static function handle_student() {
		$user_id = self::requested_student();

		check_admin_referer( self::ACTION_STUDENT . '_' . $user_id );

		$student = get_userdata( $user_id );

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EXPORT,
			WPCPM_Institution_Policy::subject_student_account( $user_id )
		);

		// "No such account" and "not your student" answer the same way, byte for byte, or the
		// export becomes a membership oracle somebody can walk a user ID at a time.
		if ( ! $student instanceof WP_User || ! $student->exists() || empty( $decision['allowed'] ) ) {
			self::refuse();
		}

		$record = WPCPM_Mentor_Calls::student_record( $user_id );
		$values = array();

		if ( '' !== $record ) {
			$claim = WPCPM_Institution_Roster::claim( $record, WPCPM_Institution_Policy::ACT_EXPORT, WPCPM_Institution_Roster::TYPE_REPORT );

			if ( is_wp_error( $claim ) ) {
				// **Never the error's own words.** `WPCPM_Airtable` writes a message for a
				// developer reading a log: a 401 or 403 on this read appends a sentence naming
				// the token's `data.records:read` scope and its access to the base. The reader
				// here is a member of a university, and a plugin telling them which credential
				// failed and what it is called is a plugin describing its own back end to the
				// outside. The refusal is the one refusal, the same as every other.
				self::refuse();
			}

			// The authoritative decision, on the Students row's own institution link. Everything
			// after this line is scoped by it rather than by the cached answer above.
			$decision = $claim['decision'];
			$values   = WPCPM_Student_Report_Form::values( $record );

			// A read that failed aborts the file, for the reason the semester report aborts a
			// generation: a student's export with every grade column blank looks like a student
			// with no grades, and nothing on the page would say the base could not be reached.
			if ( is_wp_error( $values ) ) {
				self::refuse();
			}
		}

		$matrix = self::student_matrix( $user_id, $decision, (array) $values, $student );

		if ( empty( $matrix ) ) {
			self::refuse();
		}

		self::send( self::csv( $matrix ), self::student_filename( $decision, $student ) );
	}

	/**
	 * Send one file and stop.
	 *
	 * `nosniff` with `attachment` is the pair that matters for a CSV: without it a browser may
	 * decide a file full of angle brackets is HTML and run it on this origin. There is no
	 * content security policy header here, unlike the agreement download's: a PDF has a
	 * scripting model and this does not, and the danger a CSV carries is a formula in the
	 * spreadsheet that opens it, which is `cell()`'s job and not a header's.
	 *
	 * @param string $body The file.
	 * @param string $name The filename offered to the browser.
	 */
	private static function send( $body, $name ) {
		nocache_headers();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store' );
		header( 'Content-Length: ' . strlen( $body ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The CSV itself, sent as the body of an attachment under its own Content-Type; HTML-escaping it would corrupt every cell holding an ampersand or a quotation mark.
		echo $body;
		exit;
	}

	/**
	 * The one refusal, as a page.
	 *
	 * The policy's message by default, byte for byte the same whether the record is somebody
	 * else's, unknown, or on an institution whose agreement is outstanding. A read failure is
	 * the one thing that says something different, and it says what actually happened, so a
	 * school is not told a student of theirs is not theirs because Airtable was down.
	 *
	 * @param string $message Optional message; the policy's refusal by default.
	 */
	private static function refuse( $message = '' ) {
		$message = '' === (string) $message ? WPCPM_Institution_Policy::refusal()->get_error_message() : (string) $message;

		wp_die( esc_html( $message ), 403 );
	}

	/*
	 * Links, names and small readers
	 * --------------------------------------------------------------------
	 */

	/**
	 * The nonced address of one institution's roster export.
	 *
	 * The cohort travels in the link, so the file matches the roster the reader is looking at.
	 * No institution travels in it: the handler resolves that for itself.
	 *
	 * @param string $cohort A `WPCPM_Cohort` key, or '' for every row.
	 * @return string
	 */
	public static function roster_url( $cohort = '' ) {
		$args = array( 'action' => self::ACTION_ROSTER );

		if ( WPCPM_Cohort::is_key( $cohort ) ) {
			$args[ self::ARG_COHORT ] = (string) $cohort;
		}

		// **A manager's switcher choice has to travel, or the file is of somebody else.**
		// `resolve_institution()` reads this argument on the manager branch and otherwise
		// answers with the first institution that has a member, so a manager looking at school
		// B through the switcher pressed Export and got school A, under a filename naming A.
		// It is not a way past the fence: a reader who is not a manager cannot make that branch
		// fire at all, and the value is decided by `resolve_institution()` rather than trusted.
		$view = WPCPM_Request::text( WPCPM_Institution_Roster::ARG_VIEW );

		if ( '' !== $view ) {
			$args[ WPCPM_Institution_Roster::ARG_VIEW ] = $view;
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION_ROSTER );
	}

	/**
	 * The nonced address of one student's export.
	 *
	 * Keyed to the subject account, so a token for exporting one student is not a token for
	 * exporting another.
	 *
	 * @param int $user_id The student's account.
	 * @return string
	 */
	public static function student_url( $user_id ) {
		$user_id = (int) $user_id;

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'          => self::ACTION_STUDENT,
					self::ARG_STUDENT => $user_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_STUDENT . '_' . $user_id
		);
	}

	/**
	 * The roster file's name.
	 *
	 * Built from the institution's own name in the pipeline index and never from anything the
	 * request carried, the way the agreement download builds its filename: a header value
	 * assembled from user input is a header injection waiting for the one browser that does not
	 * mind. The date is the day it was taken, because a roster is a snapshot and two of these
	 * in one folder need telling apart.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $cohort    A `WPCPM_Cohort` key, or ''.
	 * @return string
	 */
	private static function roster_filename( $record_id, $cohort ) {
		$parts = array( self::institution_slug( $record_id ), 'students' );

		if ( WPCPM_Cohort::is_key( $cohort ) ) {
			$parts[] = (string) $cohort;
		}

		$parts[] = wp_date( 'Y-m-d' );

		return sanitize_file_name( implode( '-', $parts ) ) . '.csv';
	}

	/**
	 * One student's file name.
	 *
	 * The student's own name is in it, sanitised to a slug, because a school downloading five
	 * of these needs to tell them apart and the account ID does not help anybody. Sanitised
	 * first, so no name can reach the header as anything but a slug.
	 *
	 * @param array   $decision What the fence returned; its institution names the file.
	 * @param WP_User $student  The student.
	 * @return string
	 */
	private static function student_filename( array $decision, WP_User $student ) {
		$who = sanitize_title( (string) $student->display_name );

		if ( '' === $who ) {
			$who = 'student-' . (int) $student->ID;
		}

		$institution = isset( $decision['institution'] ) ? (string) $decision['institution'] : '';

		return sanitize_file_name( self::institution_slug( $institution ) . '-' . $who . '-' . wp_date( 'Y-m-d' ) ) . '.csv';
	}

	/**
	 * An institution's name as a filename slug, falling back to its record ID.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function institution_slug( $record_id ) {
		$record_id = trim( (string) $record_id );
		$row       = WPCPM_Institutions_Index::row( $record_id );
		$name      = ( is_array( $row ) && isset( $row['name'] ) ) ? trim( (string) $row['name'] ) : '';
		$slug      = sanitize_title( '' === $name ? $record_id : $name );

		return '' === $slug ? 'institution' : $slug;
	}

	/**
	 * Which student the export link names.
	 *
	 * Its own method rather than a line inside `handle_student()`, for the reason
	 * `bin/check-references.php` enforces: `WPCPM_Request::id()` reads the query string, which
	 * is right for a `wp_nonce_url()` link and silently wrong inside a handler that receives a
	 * posted form, and the check flags any such read in a function that checks a nonce.
	 *
	 * @return int
	 */
	private static function requested_student() {
		return WPCPM_Request::id( self::ARG_STUDENT );
	}

	/**
	 * Which cohort the export link narrows to, or '' for every row.
	 *
	 * Read with `text()` and validated by `is_key()`, never trusted: an unknown value exports
	 * the whole roster rather than nothing, because a broken link should give a school its
	 * students and not an empty file it might file away as the truth.
	 *
	 * @return string
	 */
	private static function requested_cohort() {
		$asked = WPCPM_Request::text( self::ARG_COHORT );

		return WPCPM_Cohort::is_key( $asked ) ? $asked : '';
	}

	/**
	 * One student's row in one institution's index, or an empty array.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param int    $user_id   The student's account.
	 * @return array
	 */
	private static function index_row( $record_id, $user_id ) {
		$user_id = (int) $user_id;

		foreach ( WPCPM_Roster_Index::rows( $record_id ) as $row ) {
			if ( is_array( $row ) && isset( $row['user_id'] ) && (int) $row['user_id'] === $user_id ) {
				return $row;
			}
		}

		return array();
	}

	/**
	 * The heading of the group one row sits under on the roster.
	 *
	 * Asked of `WPCPM_Roster_Index::groups()` rather than worked out from the status here,
	 * because the rule that decides which group a student is in belongs to the index and one
	 * copy of it is the reason the file and the screen agree. A row the index does not hold
	 * has no group, which is the honest answer for a student with no Students row.
	 *
	 * @param string $record_id       Institutions record ID.
	 * @param string $students_record Students record ID of the row.
	 * @return string
	 */
	private static function group_label( $record_id, $students_record ) {
		$students_record = trim( (string) $students_record );

		if ( '' === $students_record ) {
			return '';
		}

		$groups = WPCPM_Roster_Index::groups( $record_id );

		foreach ( WPCPM_Institution_Roster_View::group_labels() as $group => $label ) {
			if ( isset( $groups[ $group ][ $students_record ] ) ) {
				return (string) $label;
			}
		}

		return '';
	}

	/**
	 * One key of a cached block or an index row, trimmed, or ''.
	 *
	 * @param array  $block The block.
	 * @param string $key   The key.
	 * @return string
	 */
	private static function from( array $block, $key ) {
		return ( isset( $block[ $key ] ) && is_scalar( $block[ $key ] ) ) ? trim( (string) $block[ $key ] ) : '';
	}

	/**
	 * The first value that is not empty.
	 *
	 * @param string[] $values Candidates, best first.
	 * @return string
	 */
	private static function first( array $values ) {
		foreach ( $values as $value ) {
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}
}
