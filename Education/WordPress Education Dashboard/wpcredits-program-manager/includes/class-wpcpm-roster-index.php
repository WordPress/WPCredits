<?php
/**
 * The per-institution roster index.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One option per institution holding that institution's Students rows, plus the two
 * program-wide ones: the rows that name no institution, and the counts.
 *
 * Rebuilt by the students sync's `finish()` (design spec section 8.1). A `WP_User_Query`
 * cannot return a student who has no account, and the institution side needs exactly
 * those: the imported row whose account does not exist yet, the applicant who never
 * started, the graduate whose account was never made. So the roster is the Students
 * table, read once per run into these options, and every institution-side surface reads
 * the index and prints its read time rather than paging Airtable per render.
 *
 * Two other writers touch single rows between runs, and during them: `insert()` for a
 * student an import or a manager's link just created, `update()` for a status or a date a
 * school just changed. Both stamp the row with the time they wrote it, and `write_all()`
 * keeps a stamped row over the sync's copy when the stamp is later than the run's read
 * time. A students run takes minutes and reads the Students table in the middle of them;
 * without that rule a graduate press made during the provision phase was reverted when
 * the run finished, and the page then called the reverted row fresh.
 *
 * The rows hold what the roster needs and nothing a student told the program in
 * confidence: no accessibility disclosure, no free text. `clean()` enforces that at
 * every write, so a caller that hands over a wider row cannot widen the index.
 */
class WPCPM_Roster_Index {

	/** One option per institution: the prefix followed by the Institutions record ID. */
	const OPT_PREFIX = 'wpcpm_roster_';

	/** The rows that name no institution. Manager screen only. */
	const OPT_UNLINKED = 'wpcpm_roster_unlinked';

	/** Participation per institution per cohort, and the reconciliation summary. */
	const OPT_COUNTS = 'wpcpm_roster_counts';

	/**
	 * Envelope version; an option written by another version is discarded on read.
	 *
	 * Bumped to 4 when `hours` joined `KEYS`. A version 3 row has no such key, so a roster
	 * reading one back would print an empty hours cell for every student on it until the
	 * next sync finished - and an empty hours cell reads as "nobody has done anything"
	 * rather than "not read yet", which is the one thing that column must never say.
	 */
	const VERSION = 4;

	/**
	 * The keys a row holds, in the order they are stored.
	 *
	 * Anything else a caller passes is dropped, which is the point: the Students table
	 * also carries `Accessibility needs` and `Notes`, and neither may reach an index an
	 * institution reads from.
	 */
	const KEYS = array(
		'record_id',
		'name',
		'email',
		'email_key',
		'status',
		'institution',
		'start',
		'end',
		'has_mentor',
		'username',
		'field_of_study',
		'tutor',
		// **Both are on the Students Reports row, and both used to be read only off the
		// student's WordPress account.** That meant a school saw a mentor's name and a
		// contribution team for the two students in a cohort who happen to have signed in
		// here, and nothing for the other thirteen: at one university, thirteen of fifteen
		// rows said "A mentor is assigned. The report record has not been created yet." about
		// students whose report record this very index was already holding. The sync has both
		// values in hand when it joins the two tables, so it writes them here.
		'mentor_name',
		'team',
		'website',
		// **A Students Reports column like the three above, and here for the same reason.**
		// `Hours` is on 612 of the Students Reports rows, and "how far along are they" is the
		// question a school asks straight after "who is mentoring them". Read only off
		// `wpcpm_student_program`, it would be answered for the students who have signed in
		// here and blank for the rest, which at one university is two rows of fifteen.
		//
		// Kept as the string the base sends, and formatted where it is printed. The live
		// column is fractional for some students (6.2, 135.5), so an `intval()` on the way in
		// would round a term's work down, and it runs past the target for others (400 against
		// a 150-hour track), so nothing may treat the target as a ceiling.
		'hours',
		'import_key',
		'reports',
		'user_id',
		// **Unix time of the last write this site made to the row, 0 for a row as the sync
		// read it.** Stamped by `insert()` and `update()` and by nothing a caller passes, and
		// read by `write_all()`, which keeps a row stamped at or after the run's read time
		// over the sync's copy: the sync read the table before the edit and would otherwise
		// put the old value back. Added without a `VERSION` bump on purpose - a stored row
		// without the key reads as 0, which is the truth about it, and a bump would have
		// emptied every roster on the site until the next run finished.
		'touched',
	);

	/**
	 * Statuses that are never shown to an institution as a person.
	 *
	 * `SPAM` is somebody's abuse of the public form, and `Duplicated` is a row naming a
	 * student who is already on the list under another record. Neither is a person the
	 * school sent, and printing them would ask a school to explain a stranger. Dropped
	 * before the cohort filter and before every count, so no total can carry them either.
	 */
	const NEVER_SHOWN = array( 'SPAM', 'Duplicated' );

	/**
	 * The option name for one institution.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	public static function option_name( $record_id ) {
		return self::OPT_PREFIX . trim( (string) $record_id );
	}

	/**
	 * The empty envelope, for a roster that has never been written.
	 *
	 * @return array{v: int, read: int, rows: array}
	 */
	private static function empty_shape() {
		return array(
			'v'    => self::VERSION,
			'read' => 0,
			'rows' => array(),
		);
	}

	/**
	 * Read one stored envelope, or the empty shape when it is absent or from another version.
	 *
	 * @param string $option Option name.
	 * @return array{v: int, read: int, rows: array}
	 */
	private static function read_option( $option ) {
		$stored = get_option( $option );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			return self::empty_shape();
		}

		return array(
			'v'    => self::VERSION,
			'read' => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'rows' => isset( $stored['rows'] ) && is_array( $stored['rows'] ) ? $stored['rows'] : array(),
		);
	}

	/**
	 * One institution's roster envelope.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array{v: int, read: int, rows: array} Rows keyed by Students record ID.
	 */
	public static function read( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return self::empty_shape();
		}

		return self::read_option( self::option_name( $record_id ) );
	}

	/**
	 * One institution's rows.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array Rows keyed by Students record ID.
	 */
	public static function rows( $record_id ) {
		return self::read( $record_id )['rows'];
	}

	/**
	 * The rows that name no institution.
	 *
	 * @return array Rows keyed by Students record ID.
	 */
	public static function unlinked() {
		return self::read_option( self::OPT_UNLINKED )['rows'];
	}

	/**
	 * Participation per institution per cohort, and the reconciliation summary.
	 *
	 * @return array{v: int, read: int, institutions: array, reconciliation: array}
	 */
	public static function counts() {
		$stored = get_option( self::OPT_COUNTS );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			$stored = array();
		}

		return array(
			'v'              => self::VERSION,
			'read'           => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'institutions'   => isset( $stored['institutions'] ) && is_array( $stored['institutions'] ) ? $stored['institutions'] : array(),
			'reconciliation' => isset( $stored['reconciliation'] ) && is_array( $stored['reconciliation'] ) ? $stored['reconciliation'] : array(),
		);
	}

	/**
	 * One institution's rows in the four groups the dashboard prints.
	 *
	 * | Group         | Rows                                                        |
	 * | `current`     | a tracked current status, with a Students Reports row       |
	 * | `waiting`     | a tracked current status, with none: nobody has been assigned |
	 * | `finished`    | a tracked past status                                       |
	 * | `not_started` | everything else: `Not moving forward`, `Fail`, no status at all |
	 *
	 * The two tracked lists come from the settings through
	 * `WPCPM_Mentors_Sync::tracked_statuses()`, so the roster and the two syncs cannot
	 * disagree about what "current" means. `not_started` is the residue rather than a
	 * third list, which is what keeps the four groups exhaustive: a status the base grows
	 * and nobody adds to the settings shows up under a heading with its own status printed
	 * beside it, instead of vanishing from a school's roster with no trace. `SPAM` and
	 * `Duplicated` are the one exception and are dropped outright.
	 *
	 * The cohort filter runs **first**, before any row is placed, so the groups, their
	 * lengths and the comparison strip above them all describe the same semester. Note
	 * that a group's length and `WPCPM_Cohort::participation()`'s `signed_up` answer
	 * different questions: participation also drops `Interested`, which is a lead rather
	 * than an enrolment, while a lead that somehow reached a roster is still shown here.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $cohort    A `WPCPM_Cohort` key to narrow to; anything else shows every row.
	 * @return array{current: array, waiting: array, finished: array, not_started: array}
	 *         Each a set of rows keyed by Students record ID, in index order.
	 */
	public static function groups( $record_id, $cohort = '' ) {
		$groups = array(
			'current'     => array(),
			'waiting'     => array(),
			'finished'    => array(),
			'not_started' => array(),
		);

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return $groups;
		}

		$tracked = WPCPM_Mentors_Sync::tracked_statuses();
		$active  = isset( $tracked['active'] ) ? (array) $tracked['active'] : array();
		$past    = isset( $tracked['past'] ) ? (array) $tracked['past'] : array();
		$narrow  = WPCPM_Cohort::is_key( $cohort );

		foreach ( self::rows( $record_id ) as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = trim( isset( $row['status'] ) ? (string) $row['status'] : '' );

			if ( in_array( $status, self::NEVER_SHOWN, true ) ) {
				continue;
			}

			if ( $narrow && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) !== $cohort ) {
				continue;
			}

			if ( in_array( $status, $active, true ) ) {
				// `reports` is the Students Reports rows behind this student, and the
				// automation that creates one fires on a mentor being assigned. So an empty
				// list is "nobody is mentoring them yet", which is the single question an
				// institution asks most often about a student it has sent. `has_mentor` sits
				// on the row beside it, so the rarer "a mentor is named but no report record
				// exists" can be told apart on the screen rather than here.
				$groups[ empty( $row['reports'] ) ? 'waiting' : 'current' ][ $key ] = $row;
				continue;
			}

			if ( in_array( $status, $past, true ) ) {
				$groups['finished'][ $key ] = $row;
				continue;
			}

			$groups['not_started'][ $key ] = $row;
		}

		return $groups;
	}

	/**
	 * The institution's students whose Students row the site could not find.
	 *
	 * The fifth list on the dashboard, "Not yet in the Students table": accounts the
	 * students sync could only place from the reports side, because the Students table
	 * has no row for their address at all. Nineteen such rows exist, seven of them with
	 * accounts. They are real students of this institution and are listed read-only, with
	 * a line saying a program manager has to complete the record - because the alternative
	 * is a school being told it has fewer students than it sent, with nothing to point at.
	 *
	 * The database compares meta under its own collation, which does not tell `recABC`
	 * from `recabc`; Airtable record IDs do, so every hit is checked again in PHP. Rows go
	 * through `clean()` like every other row in this index, which is what guarantees the
	 * accessibility disclosure on the program meta cannot arrive here by the back door.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array Rows in the index shape, keyed by user ID; `record_id` is empty because
	 *               that is exactly what these students do not have.
	 */
	public static function unlinked_for( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$users = get_users(
			array(
				'number'     => -1,
				'meta_key'   => WPCPM_Students_Sync::META_INSTITUTION,
				'meta_value' => $record_id,
			)
		);

		$rows = array();

		foreach ( (array) $users as $user ) {
			if ( ! $user instanceof WP_User || ! $user->exists() ) {
				continue;
			}

			if ( ! self::same_record( get_user_meta( $user->ID, WPCPM_Students_Sync::META_INSTITUTION, true ), $record_id ) ) {
				continue;
			}

			$program = get_user_meta( $user->ID, WPCPM_Students_Sync::META_PROGRAM, true );

			if ( ! is_array( $program ) ) {
				continue;
			}

			$source = isset( $program['institution_source'] ) ? (string) $program['institution_source'] : '';

			// The sync's own word for how it placed this account. `students` means the
			// authority answered and this student is on the roster proper; only `reports`
			// means the Students table was asked and had nothing.
			if ( 'reports' !== $source ) {
				continue;
			}

			$report = isset( $program['record_id'] ) ? (string) $program['record_id'] : '';

			$rows[ $user->ID ] = self::clean(
				array(
					// No Students record: that is the whole point of this list, and putting the
					// reports record here instead would hand a caller an ID that looks like a
					// Students row and is not one.
					'record_id'      => '',
					'name'           => isset( $program['name'] ) ? $program['name'] : $user->display_name,
					'email'          => isset( $program['email'] ) ? $program['email'] : $user->user_email,
					'status'         => isset( $program['program'] ) ? $program['program'] : '',
					'institution'    => $record_id,
					// The reports-side dates, because there is no Students row to take them
					// from. The roster's own dates come from the Students table; these do not,
					// and the screen says so.
					'start'          => isset( $program['start'] ) ? $program['start'] : '',
					'end'            => isset( $program['end'] ) ? $program['end'] : '',
					'has_mentor'     => ! empty( get_user_meta( $user->ID, WPCPM_Students_Sync::META_MENTOR, true ) ),
					'username'       => isset( $program['username'] ) ? $program['username'] : '',
					'field_of_study' => isset( $program['field_of_study'] ) ? $program['field_of_study'] : '',
					'tutor'          => isset( $program['tutor'] ) ? $program['tutor'] : '',
					'reports'        => WPCPM_Mentors_Sync::is_record_id( $report ) ? array( $report ) : array(),
					'user_id'        => $user->ID,
				)
			);
		}

		return $rows;
	}

	/**
	 * Write every option from one completed sync.
	 *
	 * Called once, at the end of a run, so a run that fails part-way leaves last run's
	 * options in place rather than a half-written index. An institution that had rows last
	 * run and has none now is written empty with this run's read time, not deleted: a
	 * roster that reads "0 students as of today" is the truth, and one that reads "never
	 * read" is not.
	 *
	 * **A row written on this site since the run began is kept, not replaced.** The run's
	 * rows are the Students table as it stood when the `tutors` phase paged it, and the
	 * provision phase that follows takes minutes; a graduate press or an import slice that
	 * lands in those minutes has already changed Airtable and the roster, and the sync's
	 * copy of that row is the older of the two. So each stored row's `touched` stamp is
	 * compared with the run's read time, and a row stamped at or after it stands, on the
	 * roster the sync would have emptied as well as on the ones it refilled. The next run
	 * reads the table after the edit and replaces the kept row with its own. An
	 * institution whose only rows are kept ones stays in the counts, with no participation
	 * to report yet, so `index_subject()`'s walk and the sweep below both still reach it.
	 *
	 * @param array $by_institution Rows grouped by Institutions record ID, each keyed by Students record ID.
	 * @param array $unlinked       Rows with no institution, keyed by Students record ID.
	 * @param array $counts         Institution => cohort key => participation buckets.
	 * @param array $reconciliation The reconciliation summary for the manager screen.
	 * @param int   $read           Unix time the Students table was read.
	 */
	public static function write_all( array $by_institution, array $unlinked, array $counts, array $reconciliation, $read ) {
		$read = (int) $read;
		$kept = array();

		foreach ( $by_institution as $record_id => $rows ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$record_id = trim( (string) $record_id );

			$kept[ $record_id ] = self::write_institution( $record_id, is_array( $rows ) ? $rows : array(), $read );
		}

		// Last run's institutions, from the counts option, so a stale roster is emptied
		// without a LIKE query over the options table on every run.
		foreach ( array_keys( self::counts()['institutions'] ) as $record_id ) {
			if ( isset( $kept[ $record_id ] ) || ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$kept[ $record_id ] = self::write_institution( $record_id, array(), $read );
		}

		foreach ( $kept as $record_id => $count ) {
			if ( $count > 0 && ! isset( $counts[ $record_id ] ) ) {
				$counts[ $record_id ] = array();
			}
		}

		update_option(
			self::OPT_UNLINKED,
			array(
				'v'    => self::VERSION,
				'read' => $read,
				'rows' => self::clean_rows( $unlinked ),
			),
			false
		);

		update_option(
			self::OPT_COUNTS,
			array(
				'v'              => self::VERSION,
				'read'           => $read,
				'institutions'   => $counts,
				'reconciliation' => $reconciliation,
			),
			false
		);
	}

	/**
	 * Write one institution's roster from the sync's rows, keeping what was written since the read.
	 *
	 * @param string $record_id Institutions record ID, well-formed.
	 * @param array  $rows      The sync's rows for it, in any key order; empty for a roster the run found no rows for.
	 * @param int    $read      Unix time the Students table was read.
	 * @return int How many stored rows were kept over the sync's copy.
	 */
	private static function write_institution( $record_id, array $rows, $read ) {
		$rows = self::clean_rows( $rows );
		$kept = 0;

		// The sync's copy is by definition not a write made here, whatever the caller put
		// under the key: a stamp that arrived with it would make the next run keep a row
		// nobody on this site had touched.
		foreach ( $rows as $students_record => $row ) {
			$rows[ $students_record ]['touched'] = 0;
		}

		foreach ( self::read_option( self::option_name( $record_id ) )['rows'] as $students_record => $stored ) {
			if ( ! is_array( $stored ) ) {
				continue;
			}

			// At or after, not strictly after: the read time is the run's start, and the
			// table was paged some minutes into it, so a row written in the run's first
			// second was written before the read as well as after the start.
			$touched = isset( $stored['touched'] ) ? (int) $stored['touched'] : 0;

			if ( $touched < $read ) {
				continue;
			}

			$rows[ $students_record ] = $stored;
			++$kept;
		}

		update_option(
			self::option_name( $record_id ),
			array(
				'v'    => self::VERSION,
				'read' => $read,
				'rows' => $rows,
			),
			false
		);

		return $kept;
	}

	/**
	 * Add or replace one row on one institution's roster, keeping its read time.
	 *
	 * For the import path, so a student created a moment ago is on the roster now rather
	 * than after the next sync. The read time is the sync's, deliberately: the rest of the
	 * roster is still as old as it was. The row itself is stamped `touched` with the time
	 * of this write, whatever the caller sent under that key, which is what lets a run
	 * already in flight keep it rather than write the row out of the roster it was just put on.
	 *
	 * **The institution is registered in the counts option as well**, with no participation
	 * to report yet. The counts name the rosters the last sync wrote, and they are the only
	 * list of rosters the site has: `WPCPM_Institution_Roster::index_subject()` walks them to
	 * place a Students record and `write_all()` walks them to sweep stale rosters. An
	 * institution that had no Students rows at the last sync, which is exactly the newly
	 * approved one running its first import, was on neither walk, so every student it
	 * created was on its roster page and unknown to the fence until the next run.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $row       Row in the index shape; `record_id` is the Students record ID.
	 */
	public static function insert( $record_id, array $row ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$row['touched'] = time();
		$row            = self::clean( $row );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $row['record_id'] ) ) {
			return;
		}

		$stored = self::read( $record_id );

		$stored['rows'][ $row['record_id'] ] = $row;

		update_option( self::option_name( $record_id ), $stored, false );

		$counts = self::counts();

		if ( ! isset( $counts['institutions'][ $record_id ] ) ) {
			$counts['institutions'][ $record_id ] = array();

			update_option( self::OPT_COUNTS, $counts, false );
		}
	}

	/**
	 * Merge named keys into one stored row, keeping the roster's read time.
	 *
	 * For the edit form, so a date a school changed a moment ago is on its roster now rather
	 * than after tomorrow's sync. The read time is deliberately left where it was: one row is
	 * fresh and the other thirty-nine are as old as they were, and a page that moved the whole
	 * roster's timestamp because one cell changed would say something untrue about all of them.
	 *
	 * Only the keys `KEYS` names are merged, which is the same fence `clean()` applies on every
	 * other write to this index: a caller that hands over a wider row cannot widen the index,
	 * and the accessibility disclosure has no way in through here either. Three of those keys
	 * are refused even so. `record_id` is the row's identity, and moving it would leave the row
	 * filed under a key that no longer names it; `institution` is the fence's anchor, and a
	 * caller that could rewrite it could move a student onto another school's roster - which is
	 * precisely the thing this module's fence exists to make impossible; `touched` is this
	 * index's own word about when the row was last written here, set below to the time of this
	 * write, and a caller that could date it could make `write_all()` keep a stale row forever.
	 *
	 * @param string $record_id       Institutions record ID whose roster holds the row.
	 * @param string $students_record Students record ID of the row.
	 * @param array  $changes         Values to merge, keyed by the index's own key names.
	 * @return bool Whether the option was written.
	 */
	public static function update( $record_id, $students_record, array $changes ) {
		$record_id       = trim( (string) $record_id );
		$students_record = trim( (string) $students_record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) || ! WPCPM_Mentors_Sync::is_record_id( $students_record ) ) {
			return false;
		}

		$stored = self::read( $record_id );

		// An institution with no roster written yet, or a row this roster does not hold, is
		// not something to insert: `insert()` is the call that adds a row, and a caller that
		// reached here with a record this index has never seen is asking about somebody
		// else's student or about a sync that has not run.
		if ( ! isset( $stored['rows'][ $students_record ] ) || ! is_array( $stored['rows'][ $students_record ] ) ) {
			return false;
		}

		$row     = $stored['rows'][ $students_record ];
		$changed = false;

		foreach ( $changes as $key => $value ) {
			if ( ! in_array( $key, self::KEYS, true ) || in_array( $key, array( 'record_id', 'institution', 'touched' ), true ) ) {
				continue;
			}

			$row[ $key ] = $value;
			$changed     = true;
		}

		if ( ! $changed ) {
			return false;
		}

		$row['touched'] = time();
		$row            = self::clean( $row );

		// `clean()` keeps whatever `record_id` the row carried, so this only fails if the
		// stored row was already unfindable - in which case writing it back under a key it
		// does not name would make the index disagree with itself.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $row['record_id'] ) || 0 !== strcmp( $row['record_id'], $students_record ) ) {
			return false;
		}

		$stored['rows'][ $students_record ] = $row;

		update_option( self::option_name( $record_id ), $stored, false );

		return true;
	}

	/**
	 * Remove every roster option. Called on uninstall.
	 *
	 * The per-institution options are named by prefix rather than enumerable, so the names
	 * are read with a LIKE and each is deleted through `delete_option()`, which also drops
	 * it from the object cache; a raw DELETE would leave a cached copy behind on a host
	 * with a persistent cache. The two named options start with the same prefix, and are
	 * deleted by name as well so that a prefix change never orphans them.
	 */
	public static function delete_all() {
		global $wpdb;

		$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPT_PREFIX ) . '%'
			)
		);

		foreach ( (array) $names as $name ) {
			delete_option( (string) $name );
		}

		delete_option( self::OPT_UNLINKED );
		delete_option( self::OPT_COUNTS );
	}

	/**
	 * Whether two values are the same institution record ID.
	 *
	 * Both must be well-formed before they are compared, for the reason
	 * `WPCPM_Institution_Members::same()` gives and this index has to repeat: two empty
	 * strings are equal and are not the same institution, and a fence built on that shape
	 * matches every unstamped student against every unnamed institution. Kept as one named
	 * helper so the comparison is not written inline anywhere in this file.
	 *
	 * @param string $one One value, as the database returned it.
	 * @param string $two The record ID asked for.
	 * @return bool
	 */
	private static function same_record( $one, $two ) {
		$one = trim( (string) $one );
		$two = trim( (string) $two );

		return WPCPM_Mentors_Sync::is_record_id( $one )
			&& WPCPM_Mentors_Sync::is_record_id( $two )
			&& 0 === strcmp( $one, $two );
	}

	/**
	 * Clean a set of rows, re-keyed by Students record ID.
	 *
	 * @param array $rows Rows in any key order.
	 * @return array
	 */
	private static function clean_rows( array $rows ) {
		$out = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row = self::clean( $row );

			// A row is keyed by its record ID; one that is not a record ID cannot be
			// found again, joined, or inserted over, so it is not a row.
			if ( ! WPCPM_Mentors_Sync::is_record_id( $row['record_id'] ) ) {
				continue;
			}

			$out[ $row['record_id'] ] = $row;
		}

		return $out;
	}

	/**
	 * A row reduced to the index shape, every key present and typed.
	 *
	 * @param array $row Row from the sync or from an import.
	 * @return array
	 */
	private static function clean( array $row ) {
		$out = array();

		foreach ( self::KEYS as $key ) {
			$value = isset( $row[ $key ] ) ? $row[ $key ] : null;

			switch ( $key ) {
				case 'has_mentor':
					$out[ $key ] = (bool) $value;
					break;

				case 'user_id':
					$out[ $key ] = (int) $value;
					break;

				case 'touched':
					// A row without the key, or with rubbish under it, was never written here.
					$out[ $key ] = max( 0, (int) $value );
					break;

				case 'reports':
					$ids = array();

					foreach ( is_array( $value ) ? $value : array() as $id ) {
						if ( WPCPM_Mentors_Sync::is_record_id( $id ) ) {
							$ids[] = trim( (string) $id );
						}
					}

					$out[ $key ] = array_values( array_unique( $ids ) );
					break;

				default:
					$out[ $key ] = is_scalar( $value ) ? trim( (string) $value ) : '';
					break;
			}
		}

		// The key derives from the address; a caller that sends one without the other still
		// gets a row the email join can find. Lowercased here whichever way it arrived, because
		// the join is a comparison of these keys and one row in the wrong case is a student who
		// silently belongs to nobody.
		if ( '' === $out['email_key'] && '' !== $out['email'] ) {
			$out['email_key'] = $out['email'];
		}

		$out['email_key'] = strtolower( $out['email_key'] );

		return $out;
	}
}
