<?php
/**
 * The import of release 2: what Airtable alone holds of the talks, brought over to the site once, before the switch.
 *
 * `wp wpcredits institutions_import [--dry-run] [--skip-comments] [--save=<file>] [--from=<file>] [--yes]`.
 *
 * **What it reads.** Only what the institutions sync does not: on the Institutions table the talks columns,
 * FreeScout, the application's answers, Notes, Comments and "Original program manager" (never Agreement
 * Document: the Drive link is in each agreement option already); the Team Members table, to resolve that
 * column; the Outreach Tracker; the Tutors table; and each record's comments, which need a scope of their
 * own. The index, the stages and the agreements are the last sync run's, so the import asks for a run no
 * older than 30 minutes.
 *
 * **What it writes**, before the switch, with the setting off, so all of it is site data nothing reads yet:
 * - each institution's record keys where the record has none (`fill_empty()`'s rule, never an overwrite):
 *   the talks keys, the help desk link, the application's answers and the agreement's Drive link from the
 *   agreement option;
 * - each Notes cell, Comments cell, record comment and Outreach note as notes, a text over the notes' limit
 *   as several notes in order, each with its source;
 * - an assigned Administrator for a school that has none and no choice of Nobody: by its country's area,
 *   else by the person "Original program manager" links;
 * - each Outreach row as a lead, unless it is the school a name already names (an institution or another
 *   lead, the name trimmed, lowercase, runs of spaces as one): then its notes and links are joined to that
 *   school. A row marked promoted is matched to its institution so, and one that matches nothing comes over
 *   as a lead, named in the summary, to be merged by hand;
 * - each tutor the Tutors table lists and the school's list does not, by the seed's rules;
 * - one `talks_imported` row for each school something was brought to, saying what.
 *
 * **Twice is once.** Every note carries its source (`airtable-notes:<rec>`, `airtable-comments:<rec>`,
 * `airtable-comment:<id>`, `airtable-help-desk:<rec>`, `outreach-notes:<rec>`, `outreach-help-desk:<rec>`,
 * with `:1`, `:2` for the parts of a long text), each lead its row (`outreach:<rec>`), and each is asked
 * before it is written; a field is filled only while it is empty, a tutor only while unlisted, an
 * assignment only while there is none. A run stopped anywhere and run again ends where one whole run ends.
 *
 * **Nothing leaves.** No email (every assignment and lead is made with `$notify` false), no write to Airtable,
 * no sync started, no copy sent, no lead promoted. The read is personal data: nothing of it is kept in an
 * option, a transient or a log, and `save()` refuses a place in a repository or on the site and writes the
 * file for its owner alone.
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, plans and writes the import.
 */
class WPCPM_Institutions_Move {

	/** The Outreach Tracker table. */
	const OUTREACH_TABLE = 'tblQssm68GvGEfAEH';

	/** The Outreach Tracker columns the import reads, by what each becomes (`bin/fixtures/outreach-tracker-fields.json`). */
	const OUTREACH_COLUMNS = array(
		'name'         => 'Ac',
		'city'         => 'City',
		'country'      => 'Country',
		'notes'        => 'Notes',
		'last_contact' => 'Date of last contact',
		'lead_owner'   => 'Lead Owner',
		'help_desk'    => 'HS link (where applicable)',
		'promoted'     => 'Promoted to Institutions',
	);

	/**
	 * The Institutions columns the import reads, by what each becomes: a record key, an application answer
	 * (`application_answers.<key>`), a note (`notes`, `comments`), or the assignment (`original`).
	 */
	const INSTITUTION_COLUMNS = array(
		'next_step'                                => 'Next Step',
		'follow_up'                                => 'Follow-Up Date',
		'last_contact'                             => 'Last Contact Date',
		'estimated_students'                       => 'Estimated number of students who may be interested',
		'help_desk'                                => 'FreeScout',
		'application_answers.department'           => 'Department',
		'application_answers.student_contact_name' => 'Student Contact name',
		'application_answers.internships'          => 'How do your internships or practices typically work?',
		'application_answers.why'                  => 'Why are you interested in offering WordPress Credits to your students?',
		'application_answers.anything_else'        => "Anything else you\u{2019}d like us to know?",
		'application_answers.your_email'           => 'Your Email',
		'notes'                                    => 'Notes',
		'comments'                                 => 'Comments',
		'original'                                 => 'Original program manager',
	);

	/** The history row of a school something was brought over to. */
	const LOG_IMPORTED = 'talks_imported';

	/** The shape of a read, as `save()` writes it and `read_file()` takes it. */
	const VERSION = 1;

	/** How old, in seconds, the last institutions sync may be beside the read. */
	const FRESH = 1800;

	/** The sources, each followed by a record or comment ID. */
	const SOURCE_NOTES            = 'airtable-notes:';
	const SOURCE_COMMENTS         = 'airtable-comments:';
	const SOURCE_COMMENT          = 'airtable-comment:';
	const SOURCE_HELP_DESK        = 'airtable-help-desk:';
	const SOURCE_LEAD             = 'outreach:';
	const SOURCE_OUTREACH_NOTES   = 'outreach-notes:';
	const SOURCE_OUTREACH_DESK    = 'outreach-help-desk:';
	const SOURCE_OUTREACH_NAME    = 'outreach-name:';
	const SOURCE_OUTREACH_CITY    = 'outreach-city:';
	const SOURCE_OUTREACH_COUNTRY = 'outreach-country:';
	const SOURCE_OUTREACH_OWNER   = 'outreach-owner:';

	/** Which part of the import a history row tells (its data's `from`): Airtable's, or an Outreach Tracker row's. */
	const FROM_AIRTABLE = 'airtable';
	const FROM_OUTREACH = 'outreach:';

	/** The record keys a school's talks fill, in the order a sentence names them. */
	const FILLED = array( 'next_step', 'follow_up', 'last_contact', 'estimated_students', 'help_desk', 'lead_owner', 'agreement_document' );

	/** The longest value of a lead's text fields, as `WPCPM_Institution_Leads::MAX_LENGTHS` holds them. */
	const LEAD_LIMIT = 200;

	/** The most students a school can be estimated to send, as the Talks card's. */
	const MAX_STUDENTS = 100000;

	/** Where the summary tells the operator to keep a saved read. */
	const SAVE_PLACE = '~/Projects/wpcredits-airtable-exit/';

	/**
	 * Whether the import may run against a read made at this time.
	 *
	 * The read and the site's last institutions sync must be within 30 minutes of each other, either way: the index,
	 * the stages and the agreements the import writes beside are that sync's. A saved read with no time is refused.
	 * On a rehearsal copy (`wp_get_environment_type()` `local` or `development`) a saved read with a time is taken
	 * whatever its age, since such a copy cannot sync.
	 *
	 * @param int  $read_at When the read was made (now, for a read about to be made).
	 * @param bool $saved   Whether the read came from a file `save()` wrote.
	 * @return true|WP_Error `wpcpm_import_on_site` once the site keeps the record; `wpcpm_import_stale` for a read of
	 *                       Airtable beside an old sync; `wpcpm_import_saved_stale` for a saved read of another time.
	 */
	public static function ready( $read_at, $saved = false ) {
		if ( WPCPM_Institutions_Index::on_site() ) {
			return new WP_Error( 'wpcpm_import_on_site', __( 'The site already keeps the record. The import runs before the switch.', 'wpcredits-program-manager' ) );
		}

		$read_at = (int) $read_at;
		$last    = (int) WPCPM_Institutions_Sync::last_read();

		if ( $saved ) {
			if ( $read_at > 0 && self::rehearsal() ) {
				return true;
			}

			if ( $read_at <= 0 || $last <= 0 || abs( $read_at - $last ) > self::FRESH ) {
				return new WP_Error( 'wpcpm_import_saved_stale', __( 'That saved read is not from this site\'s last sync. Read Airtable again.', 'wpcredits-program-manager' ) );
			}

			return true;
		}

		if ( $read_at <= 0 || $last <= 0 || abs( $read_at - $last ) > self::FRESH ) {
			return new WP_Error( 'wpcpm_import_stale', __( 'Run the institutions sync first: its last read is older than 30 minutes.', 'wpcredits-program-manager' ) );
		}

		return true;
	}

	/**
	 * Whether this site is a rehearsal copy: its environment is `local` or `development`.
	 *
	 * @return bool
	 */
	public static function rehearsal() {
		return function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}

	/**
	 * Read what the import brings over. Writes nothing.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @param array          $options  `comments`: false to leave the record comments unread (`--skip-comments`).
	 * @return array|WP_Error The read (`v`, `read`, `comments_read`, `institutions`, `team`, `outreach`, `tutors`,
	 *                        `comments` record ID => list, `repaired`), or the refusal: `ready()`'s, a read's,
	 *                        and `wpcpm_import_comments` when Airtable refused the record comments.
	 */
	public static function read( WPCPM_Airtable $airtable, array $options = array() ) {
		$now   = time();
		$ready = self::ready( $now );

		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$with_comments = ! isset( $options['comments'] ) || false !== $options['comments'];
		$institutions  = (string) WPCPM_Settings::get_value( 'institutions_table', '' );
		$tables        = array(
			'institutions' => array( $institutions, array_values( self::INSTITUTION_COLUMNS ) ),
			'team'         => array( WPCPM_Institution_Seed::TEAM_TABLE, array( WPCPM_Institution_Seed::TEAM_NAME, WPCPM_Institution_Seed::TEAM_EMAIL ) ),
			'outreach'     => array( self::OUTREACH_TABLE, array_values( self::OUTREACH_COLUMNS ) ),
			'tutors'       => array( (string) WPCPM_Settings::get_value( 'tutors_table', '' ), array( WPCPM_Institution_Seed::TUTOR_NAME, WPCPM_Institution_Seed::TUTOR_EMAIL, WPCPM_Institution_Seed::TUTOR_LINK ) ),
		);
		$read          = array(
			'v'             => self::VERSION,
			'read'          => $now,
			'comments_read' => $with_comments,
		);

		foreach ( $tables as $part => $table ) {
			$rows = $airtable->fetch_all( $table[0], array( 'fields' => $table[1] ) );

			if ( is_wp_error( $rows ) ) {
				return $rows;
			}

			$read[ $part ] = array_values( $rows );
		}

		$read['comments']      = array();
		$read['comments_gone'] = array();

		if ( $with_comments ) {
			$tables_with_comments = array(
				'institutions' => $institutions,
				'outreach'     => self::OUTREACH_TABLE,
			);

			foreach ( $tables_with_comments as $part => $table ) {
				foreach ( $read[ $part ] as $row ) {
					$record_id = is_array( $row ) && isset( $row['id'] ) ? (string) $row['id'] : '';

					if ( ! WPCPM_Airtable::is_record_id( $record_id ) ) {
						continue;
					}

					$comments = $airtable->fetch_comments( $table, $record_id );

					// A record deleted while the read went on: its comments are named, and the rest is read.
					if ( is_wp_error( $comments ) && 404 === self::status_of( $comments ) ) {
						$read['comments_gone'][] = $record_id;

						continue;
					}

					if ( is_wp_error( $comments ) ) {
						return self::comments_refused( $comments );
					}

					if ( array() !== $comments ) {
						$read['comments'][ $record_id ] = $comments;
					}
				}
			}
		}

		$read['repaired'] = 0;

		return $read;
	}

	/**
	 * A read saved by `save()`, read back for a rehearsal on a copy of the site that cannot reach Airtable.
	 *
	 * @param string $path The file.
	 * @return array|WP_Error The read, or `wpcpm_import_file`.
	 */
	public static function read_file( $path ) {
		$path = self::expanded( $path );
		$text = '' !== $path && is_file( $path ) && is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file the operator named on the command line.
		$read = is_string( $text ) ? json_decode( $text, true ) : null;

		if ( ! self::is_read( $read ) ) {
			return new WP_Error( 'wpcpm_import_file', __( 'That file is not a read the import saved.', 'wpcredits-program-manager' ) );
		}

		// Never the file's word: a read handed back from a file is a saved one, whatever the file says.
		$read['saved'] = true;

		return $read;
	}

	/**
	 * A read as the JSON `save()` writes: what was read, repaired where a text was not valid UTF-8.
	 *
	 * @param array $read What `read()` returned.
	 * @return string
	 */
	public static function encode( array $read ) {
		$count             = 0;
		$clean             = self::repaired( $read, $count );
		$clean['repaired'] = ( isset( $read['repaired'] ) ? (int) $read['repaired'] : 0 ) + $count;
		unset( $clean['saved'] );

		return (string) wp_json_encode( $clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Save a read to a file the operator alone can open.
	 *
	 * The file holds personal data, so it is refused inside the plugin's folder, inside WordPress's and inside any
	 * git work tree, and written with mode 0600.
	 *
	 * @param array  $read What `read()` returned.
	 * @param string $path Where.
	 * @return true|WP_Error `wpcpm_import_save_place`, `wpcpm_import_save_folder` or `wpcpm_import_save_write`.
	 */
	public static function save( array $read, $path ) {
		$path   = self::expanded( $path );
		$folder = '' !== $path ? realpath( dirname( $path ) ) : false;

		if ( false === $folder || ! is_dir( $folder ) || is_dir( $path ) ) {
			return new WP_Error( 'wpcpm_import_save_folder', __( 'That folder does not exist. Make it first, for your account alone.', 'wpcredits-program-manager' ) );
		}

		if ( self::in_repository_or_site( $folder ) ) {
			return new WP_Error( 'wpcpm_import_save_place', __( 'That file would sit in a repository or on the site. Save it under ~/Projects/wpcredits-airtable-exit/ instead.', 'wpcredits-program-manager' ) );
		}

		$target = $folder . '/' . basename( $path );

		// A link would put the file wherever it points, a repository or the site among the places.
		if ( is_link( $target ) ) {
			return new WP_Error( 'wpcpm_import_save_place', __( 'That file would sit in a repository or on the site. Save it under ~/Projects/wpcredits-airtable-exit/ instead.', 'wpcredits-program-manager' ) );
		}

		if ( file_exists( $target ) ) {
			chmod( $target, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- The operator's own file of personal data, closed to others before it is written over.
		}

		$mask    = umask( 0077 );
		$written = file_put_contents( $target, self::encode( $read ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A local file the operator named on the command line, outside the site.
		umask( $mask );

		if ( false === $written ) {
			return new WP_Error( 'wpcpm_import_save_write', __( 'The file could not be written.', 'wpcredits-program-manager' ) );
		}

		chmod( $target, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Personal data: the operator's alone.

		return true;
	}

	/**
	 * What the import would write. Writes nothing.
	 *
	 * @param array $read What `read()` or `read_file()` returned.
	 * @return array|WP_Error The plan, or `ready()`'s refusal. Keys: `read`, `comments_read`, `counts` (rows read),
	 *                        `unindexed` (record IDs), `stages` (stage => schools), `schools` (record ID => `name`,
	 *                        `fill`, `application`, `notes`, `assign`), `outreach` (one entry per row brought),
	 *                        `tutors`, `already` (notes on the site already), `unassigned`, `unresolved`, `repaired`.
	 */
	public static function plan( array $read ) {
		if ( ! self::is_read( $read ) ) {
			return new WP_Error( 'wpcpm_import_file', __( 'That file is not a read the import saved.', 'wpcredits-program-manager' ) );
		}

		$saved = ! empty( $read['saved'] );
		$ready = self::ready( (int) $read['read'], $saved );

		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$repaired = 0;
		$read     = self::repaired( $read, $repaired );

		WPCPM_Institutions_Index::forget_cached();

		$plan = array(
			'read'          => (int) $read['read'],
			'saved'         => $saved,
			'rehearsal'     => $saved && self::rehearsal(),
			'comments_read' => ! empty( $read['comments_read'] ),
			'comments_gone' => isset( $read['comments_gone'] ) && is_array( $read['comments_gone'] ) ? array_values( $read['comments_gone'] ) : array(),
			'empty_rows'    => 0,
			'counts'        => array(
				'institutions' => count( $read['institutions'] ),
				'outreach'     => count( $read['outreach'] ),
				'tutors'       => count( $read['tutors'] ),
				'comments'     => 0,
			),
			'unindexed'     => array(),
			'stages'        => self::stages(),
			'schools'       => array(),
			'outreach'      => array(),
			'tutors'        => array(),
			'already'       => 0,
			'unassigned'    => array(),
			'unresolved'    => array(),
			'repaired'      => $repaired + ( isset( $read['repaired'] ) ? (int) $read['repaired'] : 0 ),
		);

		foreach ( $read['comments'] as $list ) {
			$plan['counts']['comments'] += is_array( $list ) ? count( $list ) : 0;
		}

		$team = array();

		foreach ( $read['team'] as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) ) {
				$team[ (string) $row['id'] ] = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
			}
		}

		foreach ( $read['institutions'] as $row ) {
			self::plan_school( $plan, $row, $team, $read['comments'] );
		}

		self::plan_outreach( $plan, $read['outreach'], $read['comments'] );

		$tutors = array(
			'tutors'     => array(),
			'unresolved' => array(),
		);

		WPCPM_Institution_Seed::plan_tutors( $tutors, $read['tutors'] );

		$plan['tutors']     = $tutors['tutors'];
		$plan['unresolved'] = array_merge( $plan['unresolved'], $tutors['unresolved'] );

		return $plan;
	}

	/**
	 * Write a plan, asking each part again as it is written, so nothing is written twice and nothing the site
	 * holds is replaced. No email, no Airtable write.
	 *
	 * @param array $plan What `plan()` returned.
	 * @return array|WP_Error What was written (`notes`, `fields`, `assigned_area`, `assigned_original`, `leads`,
	 *                        `joined`, `tutors`, `failed` sentences), or `ready()`'s refusal, before anything.
	 */
	public static function apply( array $plan ) {
		$ready = self::ready( isset( $plan['read'] ) ? (int) $plan['read'] : 0, ! empty( $plan['saved'] ) );

		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$done = array(
			'notes'             => 0,
			'fields'            => 0,
			'assigned_area'     => 0,
			'assigned_original' => 0,
			'leads'             => 0,
			'joined'            => 0,
			'tutors'            => 0,
			'failed'            => array(),
		);
		$now  = (string) current_time( 'mysql', true );

		foreach ( isset( $plan['schools'] ) ? $plan['schools'] : array() as $record_id => $school ) {
			self::apply_school( $done, (string) $record_id, $school, $now );
		}

		foreach ( isset( $plan['outreach'] ) ? $plan['outreach'] : array() as $entry ) {
			self::apply_outreach( $done, $entry, $now );
		}

		foreach ( isset( $plan['tutors'] ) ? $plan['tutors'] : array() as $record_id => $list ) {
			foreach ( $list as $tutor ) {
				$result = WPCPM_Institution_Tutors::add( $record_id, $tutor['name'], $tutor['email'], 0, WPCPM_Institution_Audit::GROUND_SYSTEM );

				if ( true === $result ) {
					++$done['tutors'];
				} elseif ( is_wp_error( $result ) ) {
					$done['failed'][] = sprintf(
						/* translators: 1: an institution's record ID, 2: why it was refused. */
						__( 'A tutor of the institution %1$s was not listed: %2$s', 'wpcredits-program-manager' ),
						$record_id,
						$result->get_error_message()
					);
				}
			}
		}

		return $done;
	}

	/**
	 * The plan as the short sentences an operator reads before saying yes, one fact each.
	 *
	 * @param array $plan What `plan()` returned.
	 * @return string[]
	 */
	public static function summary( array $plan ) {
		$lines  = array();
		$counts = $plan['counts'];

		if ( ! empty( $plan['rehearsal'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: a date and time in UTC. */
				__( 'A rehearsal: this site is a local copy, so the saved read of %s UTC is used whatever the last sync\'s time.', 'wpcredits-program-manager' ),
				gmdate( 'Y-m-d H:i', (int) $plan['read'] )
			);
		}

		$lines[] = sprintf(
			/* translators: 1: a date and time in UTC, 2: how many institutions, 3: how many Outreach Tracker rows, 4: how many Tutors rows. */
			__( 'Read on %1$s UTC: %2$s, %3$s and %4$s.', 'wpcredits-program-manager' ),
			gmdate( 'Y-m-d H:i', (int) $plan['read'] ),
			/* translators: %d: how many institutions. */
			sprintf( _n( '%d institution', '%d institutions', $counts['institutions'], 'wpcredits-program-manager' ), $counts['institutions'] ),
			/* translators: %d: how many rows. */
			sprintf( _n( '%d row of the Outreach Tracker', '%d rows of the Outreach Tracker', $counts['outreach'], 'wpcredits-program-manager' ), $counts['outreach'] ),
			/* translators: %d: how many rows. */
			sprintf( _n( '%d row of the Tutors table', '%d rows of the Tutors table', $counts['tutors'], 'wpcredits-program-manager' ), $counts['tutors'] )
		);

		if ( $plan['comments_read'] ) {
			/* translators: %d: how many record comments. */
			$lines[] = sprintf( _n( 'The record comments were read: %d comment.', 'The record comments were read: %d comments.', $counts['comments'], 'wpcredits-program-manager' ), $counts['comments'] );
		} else {
			$lines[] = __( 'The record comments were not read (--skip-comments). A later run without it brings them over, and nothing else twice.', 'wpcredits-program-manager' );
		}

		foreach ( $plan['comments_gone'] as $record_id ) {
			/* translators: %s: an Airtable record ID. */
			$lines[] = sprintf( __( 'The comments of %s could not be read, as Airtable no longer has the record, so none of them comes over.', 'wpcredits-program-manager' ), $record_id );
		}

		foreach ( $plan['unindexed'] as $record_id ) {
			/* translators: %s: an Airtable record ID. */
			$lines[] = sprintf( __( 'Not in the site\'s list of institutions, so nothing of it comes over: %s.', 'wpcredits-program-manager' ), $record_id );
		}

		$by_stage = array();

		foreach ( $plan['stages'] as $stage => $count ) {
			$by_stage[] = ( '' === $stage ? __( 'no stage', 'wpcredits-program-manager' ) : $stage ) . ' ' . (int) $count;
		}

		/* translators: %s: each stage and how many schools are at it, separated by commas. */
		$lines[] = sprintf( __( 'Schools by stage: %s.', 'wpcredits-program-manager' ), array() !== $by_stage ? implode( ', ', $by_stage ) : __( 'none', 'wpcredits-program-manager' ) );

		$notes    = array(
			'columns'  => 0,
			'comments' => 0,
			'outreach' => 0,
		);
		$fields   = array_fill_keys( self::FILLED, 0 );
		$answers  = 0;
		$assigned = array(
			'area'     => 0,
			'original' => 0,
		);

		foreach ( $plan['schools'] as $school ) {
			foreach ( $school['notes'] as $note ) {
				++$notes[ self::is_comment( $note ) ? 'comments' : 'columns' ];
			}

			foreach ( array_keys( $school['fill'] ) as $key ) {
				++$fields[ $key ];
			}

			$answers += array() !== $school['application'] ? 1 : 0;

			if ( null !== $school['assign'] ) {
				++$assigned[ $school['assign'][1] ];
			}
		}

		foreach ( $plan['outreach'] as $entry ) {
			foreach ( $entry['notes'] as $note ) {
				++$notes[ self::is_comment( $note ) ? 'comments' : 'outreach' ];
			}

			if ( 'new' !== $entry['target'] ) {
				foreach ( array_keys( $entry['fill'] ) as $key ) {
					++$fields[ $key ];
				}
			}
		}

		$lines[] = sprintf(
			/* translators: 1: notes from the Notes and Comments columns, 2: record comments, 3: notes from the Outreach Tracker; each a phrase with its number. */
			__( 'Notes that come over: %1$s, %2$s and %3$s.', 'wpcredits-program-manager' ),
			/* translators: %d: how many notes. */
			sprintf( _n( '%d from the Notes, Comments and FreeScout columns', '%d from the Notes, Comments and FreeScout columns', $notes['columns'], 'wpcredits-program-manager' ), $notes['columns'] ),
			/* translators: %d: how many notes. */
			sprintf( _n( '%d record comment', '%d record comments', $notes['comments'], 'wpcredits-program-manager' ), $notes['comments'] ),
			/* translators: %d: how many notes. */
			sprintf( _n( '%d from the Outreach Tracker', '%d from the Outreach Tracker', $notes['outreach'], 'wpcredits-program-manager' ), $notes['outreach'] )
		);

		if ( $plan['already'] > 0 ) {
			/* translators: %d: how many notes. */
			$lines[] = sprintf( _n( '%d note is on the site already and is not brought again.', '%d notes are on the site already and are not brought again.', $plan['already'], 'wpcredits-program-manager' ), $plan['already'] );
		}

		/* translators: %d: how many follow-up dates. */
		$lines[] = sprintf( _n( 'Follow-ups: %d date comes over.', 'Follow-ups: %d dates come over.', $fields['follow_up'], 'wpcredits-program-manager' ), $fields['follow_up'] );

		$filled = array();

		foreach ( $fields as $key => $count ) {
			if ( 'follow_up' !== $key && $count > 0 ) {
				$filled[] = self::field_label( $key ) . ' ' . $count;
			}
		}

		if ( $answers > 0 ) {
			$filled[] = __( 'the application\'s answers', 'wpcredits-program-manager' ) . ' ' . $answers;
		}

		if ( array() !== $filled ) {
			/* translators: %s: each field and how many schools it is filled for, separated by commas. */
			$lines[] = sprintf( __( 'Filled where the site has none: %s.', 'wpcredits-program-manager' ), implode( ', ', $filled ) );
		}

		if ( $assigned['area'] + $assigned['original'] > 0 ) {
			$lines[] = sprintf(
				/* translators: 1: institutions assigned by area, 2: institutions assigned to the person who first handled them; each a phrase with its number. */
				__( 'Institutions assigned: %1$s, %2$s.', 'wpcredits-program-manager' ),
				/* translators: %d: how many institutions. */
				sprintf( _n( '%d by its country\'s area', '%d by their countries\' areas', $assigned['area'], 'wpcredits-program-manager' ), $assigned['area'] ),
				/* translators: %d: how many institutions. */
				sprintf( _n( '%d by the person who first handled it', '%d by the people who first handled them', $assigned['original'], 'wpcredits-program-manager' ), $assigned['original'] )
			);
		} else {
			$lines[] = __( 'No institution is assigned.', 'wpcredits-program-manager' );
		}

		foreach ( $plan['unassigned'] as $name ) {
			/* translators: %s: an institution's name. */
			$lines[] = sprintf( __( 'Not assigned: %s: no Administrator covers its country and nobody here is the person who first handled it.', 'wpcredits-program-manager' ), $name );
		}

		$leads  = array();
		$joined = array();
		$found  = array();
		$astray = array();

		foreach ( $plan['outreach'] as $entry ) {
			if ( 'new' === $entry['target'] ) {
				$leads[] = sprintf(
					/* translators: 1: a lead's name, 2: how it is assigned. */
					__( 'Lead: %1$s, %2$s.', 'wpcredits-program-manager' ),
					$entry['lead']['name'],
					self::assigned_text( $entry )
				);

				if ( $entry['promoted'] && ! $entry['several'] ) {
					$astray[] = $entry['name'];
				}

				continue;
			}

			if ( 'source' === $entry['how'] ) {
				continue;
			}

			if ( $entry['promoted'] && WPCPM_Airtable::is_record_id( $entry['target'] ) ) {
				/* translators: 1: the Outreach Tracker row's name, 2: the institution's name, 3: its record ID. */
				$found[] = sprintf( __( 'Promoted and matched: %1$s is %2$s (%3$s).', 'wpcredits-program-manager' ), $entry['name'], $entry['target_name'], $entry['target'] );

				continue;
			}

			/* translators: 1: the Outreach Tracker row's name, 2: the school's name, 3: its key. */
			$joined[] = sprintf( __( 'Joined to an existing school: %1$s, to %2$s (%3$s).', 'wpcredits-program-manager' ), $entry['name'], $entry['target_name'], 'planned' === $entry['how'] ? __( 'a lead of this run', 'wpcredits-program-manager' ) : $entry['target'] );
		}

		/* translators: %d: how many leads. */
		$lines[] = sprintf( _n( 'Leads: %d comes over.', 'Leads: %d come over.', count( $leads ), 'wpcredits-program-manager' ), count( $leads ) );
		$lines   = array_merge( $lines, $leads, $found );

		foreach ( $astray as $name ) {
			/* translators: %s: a lead's name. */
			$lines[] = sprintf( __( 'Promoted but matched to no institution, so it comes over as a lead to merge by hand: %s.', 'wpcredits-program-manager' ), $name );
		}

		/* translators: %d: how many Outreach Tracker rows. */
		$lines[] = sprintf( _n( 'Joined to an existing school: %d row of the Outreach Tracker.', 'Joined to an existing school: %d rows of the Outreach Tracker.', count( $joined ), 'wpcredits-program-manager' ), count( $joined ) );
		$lines   = array_merge( $lines, $joined );

		$tutors = 0;

		foreach ( $plan['tutors'] as $list ) {
			$tutors += count( $list );
		}

		/* translators: %d: how many tutors. */
		$lines[] = sprintf( _n( 'Tutors: %d is added to a school\'s list.', 'Tutors: %d are added to their schools\' lists.', $tutors, 'wpcredits-program-manager' ), $tutors );

		if ( $plan['empty_rows'] > 0 ) {
			/* translators: %d: how many rows. */
			$lines[] = sprintf( _n( '%d row of the Outreach Tracker holds nothing, so it is left out.', '%d rows of the Outreach Tracker hold nothing, so they are left out.', $plan['empty_rows'], 'wpcredits-program-manager' ), $plan['empty_rows'] );
		}

		if ( $plan['repaired'] > 0 ) {
			/* translators: %d: how many texts. */
			$lines[] = sprintf( _n( '%d text held characters that are not valid UTF-8, and comes over repaired.', '%d texts held characters that are not valid UTF-8, and come over repaired.', $plan['repaired'], 'wpcredits-program-manager' ), $plan['repaired'] );
		}

		return array_merge( $lines, $plan['unresolved'] );
	}

	/**
	 * What `apply()` wrote, as one sentence.
	 *
	 * @param array $done What `apply()` returned.
	 * @return string
	 */
	public static function written( array $done ) {
		$assigned = $done['assigned_area'] + $done['assigned_original'];

		return sprintf(
			/* translators: each a phrase with its number: 1: notes, 2: fields, 3: institutions assigned, 4: leads, 5: rows joined, 6: tutors. */
			__( 'Written: %1$s; %2$s; %3$s; %4$s; %5$s; %6$s.', 'wpcredits-program-manager' ),
			/* translators: %d: how many notes. */
			sprintf( _n( '%d note', '%d notes', $done['notes'], 'wpcredits-program-manager' ), $done['notes'] ),
			/* translators: %d: how many fields. */
			sprintf( _n( '%d field filled', '%d fields filled', $done['fields'], 'wpcredits-program-manager' ), $done['fields'] ),
			/* translators: %d: how many institutions. */
			sprintf( _n( '%d institution assigned', '%d institutions assigned', $assigned, 'wpcredits-program-manager' ), $assigned ),
			/* translators: %d: how many leads. */
			sprintf( _n( '%d lead', '%d leads', $done['leads'], 'wpcredits-program-manager' ), $done['leads'] ),
			/* translators: %d: how many rows. */
			sprintf( _n( '%d row joined to an existing school', '%d rows joined to an existing school', $done['joined'], 'wpcredits-program-manager' ), $done['joined'] ),
			/* translators: %d: how many tutors. */
			sprintf( _n( '%d tutor', '%d tutors', $done['tutors'], 'wpcredits-program-manager' ), $done['tutors'] )
		);
	}

	/**
	 * A text cut into notes the notes' limit takes, in order, each cut at a paragraph's or a sentence's end
	 * where there is one in the second half of the piece. Nothing is dropped but the space at a cut.
	 *
	 * @param string $text Well-formed UTF-8.
	 * @return string[] One piece for a text within the limit; none for an empty one.
	 */
	public static function pieces( $text ) {
		$rest   = trim( (string) $text );
		$limit  = WPCPM_Institution_Talk_Notes::MAX_LENGTH;
		$pieces = array();

		while ( mb_strlen( $rest, 'UTF-8' ) > $limit ) {
			$window = mb_substr( $rest, 0, $limit, 'UTF-8' );
			$cut    = 0;

			// A paragraph's end, a sentence's (the full-width marks of Chinese and Japanese among them, which no space
			// follows), a line's, a word's.
			foreach ( array( '/\n\s*\n/u', '/[.!?]["\')\]]?\s|[\x{3002}\x{FF01}\x{FF1F}][\x{300D}\x{300F}\x{FF09}]?/u', '/\n/u', '/\s/u' ) as $boundary ) {
				if ( preg_match_all( $boundary, $window, $found, PREG_OFFSET_CAPTURE ) > 0 ) {
					$last = end( $found[0] );
					$at   = mb_strlen( substr( $window, 0, $last[1] + strlen( $last[0] ) ), 'UTF-8' );

					if ( $at > $limit / 2 ) {
						$cut = $at;

						break;
					}
				}
			}

			$cut      = $cut > 0 ? $cut : self::cluster_cut( $rest, $limit );
			$pieces[] = trim( mb_substr( $rest, 0, $cut, 'UTF-8' ) );
			$rest     = trim( mb_substr( $rest, $cut, null, 'UTF-8' ) );
		}

		if ( '' !== $rest ) {
			$pieces[] = $rest;
		}

		return $pieces;
	}

	/**
	 * Where a text with no place to cut is cut: at the limit, or before it where the limit falls inside one grapheme
	 * cluster (a letter and its marks), so no piece starts with a mark that belongs to the one before.
	 *
	 * @param string $text  The text, longer than the limit.
	 * @param int    $limit The limit, in characters.
	 * @return int The cut, in characters.
	 */
	private static function cluster_cut( $text, $limit ) {
		if ( false === preg_match_all( '/\X/u', mb_substr( $text, 0, $limit + 32, 'UTF-8' ), $clusters ) ) {
			return $limit;
		}

		$at = 0;

		foreach ( $clusters[0] as $cluster ) {
			$length = mb_strlen( $cluster, 'UTF-8' );

			if ( $at + $length > $limit ) {
				break;
			}

			$at += $length;
		}

		return $at > 0 ? $at : $limit;
	}

	/**
	 * Fill the plan for one Institutions row.
	 *
	 * @param array $plan     The plan, changed in place.
	 * @param mixed $row      The row as read.
	 * @param array $team     Team Members record ID => fields.
	 * @param array $comments Record ID => comments.
	 */
	private static function plan_school( array &$plan, $row, array $team, array $comments ) {
		$record_id = is_array( $row ) && isset( $row['id'] ) ? trim( (string) $row['id'] ) : '';

		if ( ! WPCPM_Airtable::is_record_id( $record_id ) ) {
			return;
		}

		$index = WPCPM_Institutions_Index::row( $record_id );

		if ( ! is_array( $index ) ) {
			$plan['unindexed'][] = $record_id;

			return;
		}

		$cells   = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
		$name    = isset( $index['name'] ) && '' !== trim( (string) $index['name'] ) ? trim( (string) $index['name'] ) : $record_id;
		$held    = WPCPM_Institution_Record::get( $record_id );
		$school  = array(
			'name'        => $name,
			'fill'        => array(),
			'application' => array(),
			'notes'       => array(),
			'present'     => 0,
			'logged'      => false,
			'assign'      => null,
		);
		$columns = self::INSTITUTION_COLUMNS;

		foreach ( array( 'next_step', 'follow_up', 'last_contact', 'estimated_students' ) as $key ) {
			$value = self::text( $cells, $columns[ $key ] );

			if ( '' === $value ) {
				continue;
			}

			if ( in_array( $key, array( 'follow_up', 'last_contact' ), true ) && ! self::is_date( $value ) ) {
				$plan['unresolved'][] = self::not_a_day( $columns[ $key ], $name, $value );

				continue;
			}

			if ( 'estimated_students' === $key ) {
				if ( 1 !== preg_match( '/^[0-9]{1,6}(\.0+)?$/D', $value ) || (int) $value > self::MAX_STUDENTS ) {
					$plan['unresolved'][] = sprintf(
						/* translators: 1: an institution's name, 2: the value Airtable holds. */
						__( 'Left out: the estimated students of %1$s, "%2$s", is not a whole number of students.', 'wpcredits-program-manager' ),
						$name,
						$value
					);

					continue;
				}

				$value = (string) (int) $value;
			}

			if ( self::is_blank( isset( $held[ $key ] ) ? $held[ $key ] : null ) ) {
				$school['fill'][ $key ] = $value;
			}
		}

		$desk = self::help_desk( self::text( $cells, $columns['help_desk'] ) );

		if ( '' !== $desk['link'] && self::is_blank( isset( $held['help_desk'] ) ? $held['help_desk'] : null ) ) {
			$school['fill']['help_desk'] = $desk['link'];
		}

		$option   = WPCPM_Institution_Agreement::option( $record_id );
		$document = is_array( $option ) && isset( $option['drive_url'] ) ? trim( (string) $option['drive_url'] ) : '';

		if ( '' !== $document && '' === WPCPM_Institution_Agreement::record_document( $record_id ) ) {
			$school['fill'][ WPCPM_Institution_Agreement::RECORD_DOCUMENT ] = $document;
		}

		$answers = isset( $held[ WPCPM_Institution_Talks::RECORD_APPLICATION ] ) && is_array( $held[ WPCPM_Institution_Talks::RECORD_APPLICATION ] ) ? $held[ WPCPM_Institution_Talks::RECORD_APPLICATION ] : array();

		foreach ( $columns as $key => $column ) {
			if ( 0 !== strpos( $key, 'application_answers.' ) ) {
				continue;
			}

			$answer = substr( $key, strlen( 'application_answers.' ) );
			$value  = self::text( $cells, $column );

			if ( '' !== $value && self::is_blank( isset( $answers[ $answer ] ) ? $answers[ $answer ] : null ) ) {
				$school['application'][ $answer ] = $value;
			}
		}

		self::plan_notes( $plan, $school, self::text( $cells, $columns['notes'] ), self::SOURCE_NOTES . $record_id, __( 'Imported from Airtable', 'wpcredits-program-manager' ), $columns['notes'] );
		self::plan_notes( $plan, $school, self::text( $cells, $columns['comments'] ), self::SOURCE_COMMENTS . $record_id, __( 'Imported from Airtable (Comments column)', 'wpcredits-program-manager' ), $columns['comments'] );
		self::plan_notes( $plan, $school, $desk['rest'], self::SOURCE_HELP_DESK . $record_id, __( 'Imported from Airtable (FreeScout column)', 'wpcredits-program-manager' ), $columns['help_desk'] );
		self::plan_comments( $plan, $school, isset( $comments[ $record_id ] ) ? $comments[ $record_id ] : array() );

		// A school something was brought to before, whose row of it a stopped run did not write, is given it now.
		$school['logged'] = self::logged( $record_id, self::FROM_AIRTABLE );

		// An assignee, or Nobody chosen on the site, is kept; otherwise the country's area, else the person who first handled the school.
		if ( 0 === WPCPM_Institution_Assignment::stored_of( $record_id ) && ! WPCPM_Institution_Assignment::has_choice( $record_id ) ) {
			$country = isset( $index['country'] ) ? trim( (string) $index['country'] ) : '';
			$admin   = '' !== $country ? (int) WPCPM_Institution_Areas::administrator_for( $country ) : 0;

			if ( $admin > 0 ) {
				$school['assign'] = array( $admin, 'area' );
			} else {
				foreach ( WPCPM_Airtable::link_ids( isset( $cells[ $columns['original'] ] ) ? $cells[ $columns['original'] ] : null ) as $team_id ) {
					$by = isset( $team[ $team_id ] ) ? WPCPM_Institution_Seed::administrator_of( $team[ $team_id ] ) : array( 0, '' );

					if ( $by[0] > 0 ) {
						$school['assign'] = array( (int) $by[0], 'original' );

						break;
					}
				}

				if ( null === $school['assign'] ) {
					$plan['unassigned'][] = $name;
				}
			}
		}

		$plan['schools'][ $record_id ] = $school;
	}

	/**
	 * Fill the plan for the Outreach Tracker's rows, in the order read.
	 *
	 * A row is matched, in this order, to the lead an earlier run made of it (its source), to the institution or lead
	 * of its name, to the lead an earlier row of this run makes; else it is a lead of its own. A promoted row whose
	 * name is several institutions' is matched to none and comes over as a lead, named. A row with no name but
	 * something else in it is a lead named `Unnamed lead <rec>`; a row with nothing in it is counted and left out.
	 *
	 * @param array $plan     The plan, changed in place.
	 * @param array $rows     The rows as read.
	 * @param array $comments Record ID => comments.
	 */
	private static function plan_outreach( array &$plan, array $rows, array $comments ) {
		$columns   = self::OUTREACH_COLUMNS;
		$schools   = array();
		$leads     = array();
		$planned   = array();
		$countries = array();

		foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
			$fold = is_array( $row ) && isset( $row['name'] ) ? self::fold( $row['name'] ) : '';

			if ( '' !== $fold ) {
				$schools[ $fold ][] = array( (string) $record_id, trim( (string) $row['name'] ) );
			}
		}

		foreach ( WPCPM_Institution_Leads::all() as $key => $lead ) {
			$fold = self::fold( $lead['name'] );

			if ( '' !== $fold && ! isset( $leads[ $fold ] ) ) {
				$leads[ $fold ] = array( (string) $key, trim( (string) $lead['name'] ) );
			}
		}

		foreach ( WPCPM_Countries::all() as $country_id => $country ) {
			$fold = is_array( $country ) && isset( $country['name'] ) ? self::fold( $country['name'] ) : '';

			if ( '' !== $fold ) {
				$countries[ $fold ] = (string) $country_id;
			}
		}

		foreach ( $rows as $row ) {
			$record_id = is_array( $row ) && isset( $row['id'] ) ? trim( (string) $row['id'] ) : '';

			if ( ! WPCPM_Airtable::is_record_id( $record_id ) ) {
				continue;
			}

			$cells   = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
			$name    = self::line( self::text( $cells, $columns['name'] ) );
			$city    = self::line( self::text( $cells, $columns['city'] ) );
			$country = self::line( self::text( $cells, $columns['country'] ) );
			$owner   = self::line( self::text( $cells, $columns['lead_owner'] ) );
			$last    = self::text( $cells, $columns['last_contact'] );
			$notes   = self::text( $cells, $columns['notes'] );
			$link    = self::text( $cells, $columns['help_desk'] );
			$said    = isset( $comments[ $record_id ] ) && array() !== $comments[ $record_id ];

			if ( '' === $name && '' === $city && '' === $country && '' === $owner && '' === $last && '' === $notes && '' === $link && ! $said ) {
				++$plan['empty_rows'];

				continue;
			}

			$unnamed = '' === $name;
			/* translators: %s: an Airtable record ID. */
			$name  = $unnamed ? sprintf( __( 'Unnamed lead %s', 'wpcredits-program-manager' ), $record_id ) : $name;
			$fold  = self::fold( $name );
			$desk  = self::help_desk( $link );
			$entry = array(
				'record'      => $record_id,
				'name'        => $name,
				'unnamed'     => $unnamed,
				'promoted'    => ! empty( $cells[ $columns['promoted'] ] ),
				'several'     => false,
				'target'      => 'new',
				'target_name' => '',
				'how'         => '',
				'lead'        => array(),
				'assigned_by' => '',
				'fill'        => array(),
				'notes'       => array(),
				'present'     => 0,
				'logged'      => false,
			);
			$long  = array();

			foreach ( array(
				'name'       => $name,
				'city'       => $city,
				'country'    => $country,
				'lead_owner' => $owner,
			) as $field => $value ) {
				if ( mb_strlen( $value, 'UTF-8' ) > self::LEAD_LIMIT ) {
					$long[ $field ] = $value;
				}
			}

			if ( '' !== $last && ! self::is_date( $last ) ) {
				$plan['unresolved'][] = self::not_a_day( $columns['last_contact'], $name, $last );
				$last                 = '';
			}

			// A Lead Owner over the limit is kept whole in its note alone.
			$fill = array_filter(
				array(
					'last_contact' => $last,
					'help_desk'    => $desk['link'],
					'lead_owner'   => isset( $long['lead_owner'] ) ? '' : $owner,
				),
				static function ( $value ) {
					return '' !== (string) $value;
				}
			);

			$found = WPCPM_Institution_Leads::by_source( self::SOURCE_LEAD . $record_id );
			$named = isset( $schools[ $fold ] ) ? $schools[ $fold ] : array();

			if ( '' !== $found ) {
				$entry['target'] = $found;
				$entry['how']    = 'source';
			} elseif ( $unnamed ) {
				$entry['target'] = 'new';
			} elseif ( $entry['promoted'] && count( $named ) > 1 ) {
				$entry['several'] = true;
			} elseif ( array() !== $named ) {
				$entry['target']      = $named[0][0];
				$entry['target_name'] = $named[0][1];
				$entry['how']         = 'name';
			} elseif ( isset( $leads[ $fold ] ) ) {
				$entry['target']      = $leads[ $fold ][0];
				$entry['target_name'] = $leads[ $fold ][1];
				$entry['how']         = 'name';
			} elseif ( isset( $planned[ $fold ] ) ) {
				$entry['target']      = $planned[ $fold ][0];
				$entry['target_name'] = $planned[ $fold ][1];
				$entry['how']         = 'planned';
			}

			if ( 'new' === $entry['target'] ) {
				$entry['lead'] = self::lead_fields( $plan, $entry, $city, $country, $countries, $fill );

				if ( ! $unnamed && ! $entry['several'] ) {
					$planned[ $fold ] = array( $record_id, $entry['lead']['name'] );
				}
			} elseif ( 'planned' === $entry['how'] ) {
				// The lead it joins is made by an earlier row of this run: what is empty on it is asked as it is written.
				$entry['fill'] = $fill;
			} elseif ( 'source' !== $entry['how'] ) {
				$entry['fill'] = self::empty_of( $entry['target'], $fill );
			}

			self::plan_notes( $plan, $entry, $notes, self::SOURCE_OUTREACH_NOTES . $record_id, __( 'Imported from the Outreach Tracker', 'wpcredits-program-manager' ), $columns['notes'] );
			self::plan_notes( $plan, $entry, $desk['rest'], self::SOURCE_OUTREACH_DESK . $record_id, __( 'Imported from the Outreach Tracker (HS link column)', 'wpcredits-program-manager' ), $columns['help_desk'] );

			// A value over a lead's limit is cut on the lead and kept whole in a note; a Lead Owner's goes to a joined school too.
			$whole = array(
				'name'       => array( self::SOURCE_OUTREACH_NAME, __( 'Imported from the Outreach Tracker (the whole name)', 'wpcredits-program-manager' ), $columns['name'] ),
				'city'       => array( self::SOURCE_OUTREACH_CITY, __( 'Imported from the Outreach Tracker (the whole city)', 'wpcredits-program-manager' ), $columns['city'] ),
				'country'    => array( self::SOURCE_OUTREACH_COUNTRY, __( 'Imported from the Outreach Tracker (the whole country)', 'wpcredits-program-manager' ), $columns['country'] ),
				'lead_owner' => array( self::SOURCE_OUTREACH_OWNER, __( 'Imported from the Outreach Tracker (the whole Lead Owner)', 'wpcredits-program-manager' ), $columns['lead_owner'] ),
			);

			// The row's own lead, new or found by its source on a rerun, keeps every whole value; a school it only joins,
			// the Lead Owner's. `has_source()` keeps each from being written twice.
			$own = 'new' === $entry['target'] || 'source' === $entry['how'];

			foreach ( $long as $field => $value ) {
				if ( 'lead_owner' !== $field && ! $own ) {
					continue;
				}

				self::plan_notes( $plan, $entry, $value, $whole[ $field ][0] . $record_id, $whole[ $field ][1], $whole[ $field ][2] );

				if ( 'new' !== $entry['target'] ) {
					continue;
				}

				if ( 'lead_owner' === $field ) {
					$plan['unresolved'][] = sprintf(
						/* translators: 1: a lead's name, 2: the most characters a lead's field holds. */
						__( 'Cut: the Lead Owner of %1$s is longer than %2$d characters, so the lead has no lead owner and a note keeps it whole.', 'wpcredits-program-manager' ),
						$entry['lead']['name'],
						self::LEAD_LIMIT
					);

					continue;
				}

				$plan['unresolved'][] = sprintf(
					/* translators: 1: an Outreach Tracker column, 2: a lead's name, 3: the most characters a lead's field holds. */
					__( 'Cut: the %1$s of %2$s is longer than %3$d characters, so the lead keeps the first %3$d and a note keeps it whole.', 'wpcredits-program-manager' ),
					$whole[ $field ][2],
					$entry['lead']['name'],
					self::LEAD_LIMIT
				);
			}

			self::plan_comments( $plan, $entry, isset( $comments[ $record_id ] ) ? $comments[ $record_id ] : array() );

			if ( 'new' !== $entry['target'] && 'planned' !== $entry['how'] ) {
				$entry['logged'] = self::logged( $entry['target'], self::FROM_OUTREACH . $record_id );
			}

			// A row brought over before, or joined to a school that has everything it gives, has nothing left to write,
			// unless a stopped run left its history row unwritten.
			if ( 'new' !== $entry['target'] && array() === $entry['fill'] && array() === $entry['notes'] && ( 0 === $entry['present'] || $entry['logged'] ) ) {
				continue;
			}

			$plan['outreach'][] = $entry;
		}
	}

	/**
	 * A new lead's fields, its assignment resolved and named. A value over a lead's limit is cut to it (the caller keeps
	 * it whole in a note); a Lead Owner over it is left out of the lead.
	 *
	 * @param array  $plan      The plan, changed in place.
	 * @param array  $entry     The row's entry, `assigned_by` set in place.
	 * @param string $city      The row's city, on one line.
	 * @param string $country   The row's country, on one line.
	 * @param array  $countries Folded country name => Countries record ID.
	 * @param array  $fill      The row's last contact, help desk link and lead owner, the empty ones left out.
	 * @return array
	 */
	private static function lead_fields( array &$plan, array &$entry, $city, $country, array $countries, array $fill ) {
		$name = $entry['name'];

		if ( $entry['several'] ) {
			// The name is several institutions', so it cannot be a lead's: the row's own ID keeps it apart.
			$tail = ' (Outreach Tracker ' . $entry['record'] . ')';
			$name = self::cut( $name, self::LEAD_LIMIT - mb_strlen( $tail, 'UTF-8' ) ) . $tail;

			$plan['unresolved'][] = sprintf(
				/* translators: 1: the Outreach Tracker row's name, 2: the lead it comes over as. */
				__( 'Promoted and matched to several institutions, so it comes over as a lead to merge by hand: %1$s, as %2$s.', 'wpcredits-program-manager' ),
				$entry['name'],
				$name
			);
		}

		$fields = array_merge(
			array(
				'name'   => self::cut( $name, self::LEAD_LIMIT ),
				'city'   => self::cut( $city, self::LEAD_LIMIT ),
				'source' => self::SOURCE_LEAD . $entry['record'],
			),
			$fill
		);

		if ( '' !== $country && isset( $countries[ self::fold( $country ) ] ) ) {
			$fields['country'] = $countries[ self::fold( $country ) ];
		} elseif ( '' !== $country ) {
			$fields['country_text'] = self::cut( $country, self::LEAD_LIMIT );
			$plan['unresolved'][]   = sprintf(
				/* translators: 1: a country's name as the Outreach Tracker holds it, 2: a lead's name. */
				__( 'Not resolved: the country "%1$s" of %2$s is not one the site knows, so it is kept as text.', 'wpcredits-program-manager' ),
				$fields['country_text'],
				$fields['name']
			);
		}

		if ( $entry['unnamed'] ) {
			$plan['unresolved'][] = sprintf(
				/* translators: 1: an Airtable record ID, 2: the name the lead is given. */
				__( 'Unnamed: the Outreach Tracker row %1$s has no name, so it comes over as "%2$s" for an Administrator to rename or close.', 'wpcredits-program-manager' ),
				$entry['record'],
				$fields['name']
			);
		}

		$admin = isset( $fields['country'] ) ? (int) WPCPM_Institution_Areas::administrator_for( $fields['country'] ) : 0;
		$by    = $admin > 0 ? 'area' : '';
		$owner = isset( $fill['lead_owner'] ) ? $fill['lead_owner'] : '';

		if ( 0 === $admin && '' !== $owner ) {
			$cell  = is_email( $owner ) ? array( WPCPM_Institution_Seed::TEAM_EMAIL => $owner ) : array( WPCPM_Institution_Seed::TEAM_NAME => $owner );
			$admin = (int) WPCPM_Institution_Seed::administrator_of( $cell )[0];
			$by    = $admin > 0 ? 'owner' : '';

			if ( 0 === $admin ) {
				$plan['unresolved'][] = sprintf(
					/* translators: 1: the Lead Owner as the Outreach Tracker holds it, 2: a lead's name. */
					__( 'Not resolved: the Lead Owner "%1$s" of %2$s is no Administrator here, so the lead is not assigned and the name is kept as its lead owner.', 'wpcredits-program-manager' ),
					$owner,
					$fields['name']
				);
			}
		}

		$fields['assigned']   = $admin;
		$entry['assigned_by'] = $by;

		return $fields;
	}

	/**
	 * A text cut to a length in characters, the space at the cut trimmed.
	 *
	 * @param string $text  The text.
	 * @param int    $limit The most characters.
	 * @return string
	 */
	private static function cut( $text, $limit ) {
		return mb_strlen( $text, 'UTF-8' ) > $limit ? rtrim( mb_substr( $text, 0, max( 1, (int) $limit ), 'UTF-8' ) ) : $text;
	}

	/**
	 * The fields of a school, a lead or an institution, that are empty now, of the ones given.
	 *
	 * @param string $key    A record ID or a lead key.
	 * @param array  $fields Field => value.
	 * @return array
	 */
	private static function empty_of( $key, array $fields ) {
		if ( WPCPM_Institution_Leads::is_key( $key ) ) {
			$held = WPCPM_Institution_Leads::get( $key );
		} else {
			$held = WPCPM_Institution_Record::get( $key );
		}

		$held = is_array( $held ) ? $held : array();
		$out  = array();

		foreach ( $fields as $field => $value ) {
			if ( self::is_blank( isset( $held[ $field ] ) ? $held[ $field ] : null ) ) {
				$out[ $field ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Add a text's notes to a school's or a row's part, the ones the site holds already counted and left out.
	 *
	 * A cell brought once is never brought again in another shape: when its base source or its first part is on the
	 * site, only the parts a stopped run did not write are added, and only while the parts brought still read as the
	 * cell does now; a cell changed in Airtable since is left whole, and named.
	 *
	 * @param array  $plan   The plan, changed in place (`already`, `unresolved`).
	 * @param array  $part   The school's or the row's part, changed in place (`notes`, `present`).
	 * @param string $text   The text.
	 * @param string $source Its source.
	 * @param string $author The name the notes are signed with.
	 * @param string $column The column it came from, as a sentence names it.
	 * @param string $date   `Y-m-d H:i:s` UTC, or '' for the day of the import.
	 */
	private static function plan_notes( array &$plan, array &$part, $text, $source, $author, $column, $date = '' ) {
		$pieces = self::pieces( $text );

		if ( array() === $pieces ) {
			return;
		}

		$brought = array();
		$whole   = WPCPM_Institution_Talk_Notes::text_of_source( $source );

		if ( null !== $whole ) {
			$brought = array( $whole );
		} else {
			for ( $at = 1; $at <= 1000; $at++ ) {
				$kept = WPCPM_Institution_Talk_Notes::text_of_source( $source . ':' . $at );

				if ( null === $kept ) {
					break;
				}

				$brought[] = $kept;
			}
		}

		if ( array() !== $brought ) {
			$same = ( null === $whole || 1 === count( $pieces ) ) && count( $brought ) <= count( $pieces );

			foreach ( $brought as $at => $kept ) {
				$same = $same && self::squeezed( $kept ) === self::squeezed( $pieces[ $at ] );
			}

			$part['present'] += count( $brought );
			$plan['already'] += count( $brought );

			if ( ! $same ) {
				$plan['unresolved'][] = sprintf(
					/* translators: 1: an Airtable column, 2: a school's name. */
					__( 'Changed in Airtable since it was brought, and not brought again: %1$s of %2$s.', 'wpcredits-program-manager' ),
					$column,
					$part['name']
				);

				return;
			}
		}

		$many = count( $pieces ) > 1;

		foreach ( $pieces as $at => $piece ) {
			if ( $at < count( $brought ) ) {
				continue;
			}

			$from = $many ? $source . ':' . ( $at + 1 ) : $source;

			if ( WPCPM_Institution_Talk_Notes::has_source( $from ) ) {
				++$part['present'];
				++$plan['already'];

				continue;
			}

			$part['notes'][] = array(
				'source' => $from,
				'text'   => $piece,
				'author' => $author,
				'date'   => $date,
			);
		}
	}

	/**
	 * Add a record's comments to a part's notes, each signed with its author's name and dated as it was written.
	 *
	 * @param array $plan     The plan, changed in place.
	 * @param array $part     The school's or the row's part, changed in place.
	 * @param mixed $comments The comments as read.
	 */
	private static function plan_comments( array &$plan, array &$part, $comments ) {
		foreach ( is_array( $comments ) ? $comments : array() as $comment ) {
			$id = is_array( $comment ) && isset( $comment['id'] ) ? trim( (string) $comment['id'] ) : '';

			if ( '' === $id ) {
				continue;
			}

			// Signed with the author's name, never their address: a comment with no name gets the generic signature.
			$author  = isset( $comment['author_name'] ) ? trim( (string) $comment['author_name'] ) : '';
			$created = isset( $comment['created'] ) ? strtotime( (string) $comment['created'] ) : false;

			self::plan_notes(
				$plan,
				$part,
				isset( $comment['text'] ) ? (string) $comment['text'] : '',
				self::SOURCE_COMMENT . $id,
				'' !== $author ? $author : __( 'Imported from Airtable (a record comment)', 'wpcredits-program-manager' ),
				__( 'a record comment', 'wpcredits-program-manager' ),
				false !== $created && $created > 0 ? gmdate( 'Y-m-d H:i:s', $created ) : ''
			);
		}
	}

	/**
	 * A text with its space taken out, as two parts are compared: a cut trims the space at it.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	private static function squeezed( $text ) {
		return (string) preg_replace( '/\s+/u', '', (string) $text );
	}

	/**
	 * Whether a school has the history row of this import's part already.
	 *
	 * @param string $key  A record ID or a lead key.
	 * @param string $from `FROM_AIRTABLE`, or `FROM_OUTREACH` and the row's record ID.
	 * @return bool
	 */
	private static function logged( $key, $from ) {
		$entries = WPCPM_Institution_Leads::is_key( $key ) ? WPCPM_Institution_Audit::entries_for_lead( $key, 0 ) : WPCPM_Institution_Audit::entries_for( $key, 0 );

		foreach ( $entries as $entry ) {
			if ( self::LOG_IMPORTED === $entry['kind'] && isset( $entry['data']['from'] ) && $from === $entry['data']['from'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Write one institution's part.
	 *
	 * @param array  $done      What was written, changed in place.
	 * @param string $record_id The record ID.
	 * @param array  $school    Its part of the plan.
	 * @param string $now       The import's moment, `Y-m-d H:i:s` UTC.
	 */
	private static function apply_school( array &$done, $record_id, array $school, $now ) {
		$brought = array();
		$lock    = WPCPM_Institution_Request::school_lock_name( $record_id );
		$token   = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

		if ( '' === $token ) {
			$done['failed'][] = sprintf(
				/* translators: %s: an institution's record ID. */
				__( 'The institution %s was left for the next run: somebody else was changing its record. Run the command again.', 'wpcredits-program-manager' ),
				$record_id
			);

			return;
		}

		try {
			$brought = self::fill_record( $done, $record_id, $school['fill'] );

			if ( array() !== $school['application'] ) {
				$held    = WPCPM_Institution_Record::get( $record_id );
				$answers = isset( $held[ WPCPM_Institution_Talks::RECORD_APPLICATION ] ) && is_array( $held[ WPCPM_Institution_Talks::RECORD_APPLICATION ] ) ? $held[ WPCPM_Institution_Talks::RECORD_APPLICATION ] : array();
				$merged  = $answers;

				foreach ( $school['application'] as $answer => $value ) {
					if ( self::is_blank( isset( $merged[ $answer ] ) ? $merged[ $answer ] : null ) ) {
						$merged[ $answer ] = $value;
					}
				}

				if ( $merged !== $answers && WPCPM_Institution_Record::set( $record_id, array( WPCPM_Institution_Talks::RECORD_APPLICATION => $merged ) ) ) {
					$brought[] = 'application';
					++$done['fields'];
				}
			}

			if ( null !== $school['assign'] && 0 === WPCPM_Institution_Assignment::stored_of( $record_id ) && ! WPCPM_Institution_Assignment::has_choice( $record_id ) ) {
				$result = WPCPM_Institution_Assignment::assign( $record_id, (int) $school['assign'][0], 0, false );

				if ( true === $result ) {
					++$done[ 'area' === $school['assign'][1] ? 'assigned_area' : 'assigned_original' ];
				} elseif ( is_wp_error( $result ) ) {
					$done['failed'][] = sprintf(
						/* translators: 1: an institution's record ID, 2: why it was refused. */
						__( 'The institution %1$s was not assigned: %2$s', 'wpcredits-program-manager' ),
						$record_id,
						$result->get_error_message()
					);
				}
			}
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		$notes = self::add_notes( $done, $record_id, $school['notes'], $now );

		self::log( $record_id, __( 'Airtable', 'wpcredits-program-manager' ), self::FROM_AIRTABLE, $notes, $brought, false, $school['present'] );
	}

	/**
	 * Write one Outreach Tracker row's part: a lead, or what it joins to an existing school.
	 *
	 * @param array  $done  What was written, changed in place.
	 * @param array  $entry Its part of the plan.
	 * @param string $now   The import's moment.
	 */
	private static function apply_outreach( array &$done, array $entry, $now ) {
		$key     = 'new' === $entry['target'] || 'planned' === $entry['how'] ? '' : (string) $entry['target'];
		$fill    = $entry['fill'];
		$created = false;

		// Asked again: a run that stopped after this row's lead was added finds it by its row.
		$found = WPCPM_Institution_Leads::by_source( self::SOURCE_LEAD . $entry['record'] );

		if ( '' !== $found ) {
			$key = $found;
		} elseif ( 'planned' === $entry['how'] ) {
			$key = WPCPM_Institution_Leads::by_source( self::SOURCE_LEAD . $entry['target'] );
		}

		if ( '' === $key && 'new' === $entry['target'] ) {
			$key = WPCPM_Institution_Leads::add( $entry['lead'], 0, false );

			if ( is_wp_error( $key ) && 'wpcpm_lead_taken' === $key->get_error_code() ) {
				// The name was taken since the plan was made: the row joins the school that took it.
				$key  = WPCPM_Institution_Leads::name_taken( $entry['name'] );
				$fill = array_intersect_key( $entry['lead'], array_flip( array( 'last_contact', 'help_desk', 'lead_owner' ) ) );
			} elseif ( ! is_wp_error( $key ) ) {
				$created = true;
				++$done['leads'];
			}

			if ( is_wp_error( $key ) ) {
				$done['failed'][] = sprintf(
					/* translators: 1: a lead's name, 2: why it was refused. */
					__( 'The lead %1$s was not added: %2$s', 'wpcredits-program-manager' ),
					$entry['name'],
					$key->get_error_message()
				);

				return;
			}
		}

		if ( '' === $key ) {
			$done['failed'][] = sprintf(
				/* translators: %s: a lead's name. */
				__( 'The Outreach Tracker row %s was left for the next run: the lead it joins was not added.', 'wpcredits-program-manager' ),
				$entry['name']
			);

			return;
		}

		$brought = array();

		if ( ! $created && array() !== $fill ) {
			if ( WPCPM_Institution_Leads::is_key( $key ) ) {
				$changes = self::empty_of( $key, $fill );

				$updated = array() !== $changes ? WPCPM_Institution_Leads::update( $key, $changes, 0 ) : false;

				if ( true === $updated ) {
					$brought         = array_keys( $changes );
					$done['fields'] += count( $changes );
				} elseif ( is_wp_error( $updated ) ) {
					$done['failed'][] = sprintf(
						/* translators: 1: the Outreach Tracker row's name, 2: the lead's name, 3: why it was refused. */
						__( 'The Outreach Tracker row %1$s was not joined to %2$s: %3$s', 'wpcredits-program-manager' ),
						$entry['name'],
						'' !== $entry['target_name'] ? $entry['target_name'] : $key,
						$updated->get_error_message()
					);

					// The school it joins is not what it was: its notes wait for the next run.
					return;
				}
			} else {
				$lock  = WPCPM_Institution_Request::school_lock_name( $key );
				$token = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

				if ( '' === $token ) {
					$done['failed'][] = sprintf(
						/* translators: %s: an institution's record ID. */
						__( 'The institution %s was left for the next run: somebody else was changing its record. Run the command again.', 'wpcredits-program-manager' ),
						$key
					);
				} else {
					try {
						$brought = self::fill_record( $done, $key, $fill );
					} finally {
						WPCPM_Lock::release( $lock, $token );
					}
				}
			}
		}

		$notes = self::add_notes( $done, $key, $entry['notes'], $now );

		if ( ! $created && '' === $found && ( $notes > 0 || array() !== $brought ) ) {
			++$done['joined'];
		}

		self::log( $key, __( 'the Outreach Tracker', 'wpcredits-program-manager' ), self::FROM_OUTREACH . $entry['record'], $notes, $brought, $created, $entry['present'] );
	}

	/**
	 * Fill a record's empty keys, the school's lock held by the caller.
	 *
	 * @param array  $done      What was written, changed in place.
	 * @param string $record_id The record ID.
	 * @param array  $fill      Key => value.
	 * @return string[] The keys filled now.
	 */
	private static function fill_record( array &$done, $record_id, array $fill ) {
		if ( array() === $fill ) {
			return array();
		}

		WPCPM_Institution_Record::forget_cached( $record_id );

		$before = WPCPM_Institution_Record::get( $record_id );

		if ( ! WPCPM_Institution_Record::fill_empty( $record_id, $fill ) ) {
			return array();
		}

		WPCPM_Institution_Record::forget_cached( $record_id );

		$after  = WPCPM_Institution_Record::get( $record_id );
		$filled = array();

		foreach ( array_keys( $fill ) as $key ) {
			if ( self::is_blank( isset( $before[ $key ] ) ? $before[ $key ] : null ) && ! self::is_blank( isset( $after[ $key ] ) ? $after[ $key ] : null ) ) {
				$filled[] = $key;
			}
		}

		$done['fields'] += count( $filled );

		return $filled;
	}

	/**
	 * Add the notes the site does not hold yet.
	 *
	 * @param array  $done  What was written, changed in place.
	 * @param string $key   A record ID or a lead key.
	 * @param array  $notes The planned notes.
	 * @param string $now   The import's moment, the date of a note that has none of its own.
	 * @return int How many were added.
	 */
	private static function add_notes( array &$done, $key, array $notes, $now ) {
		$added = 0;

		foreach ( $notes as $note ) {
			if ( WPCPM_Institution_Talk_Notes::has_source( $note['source'] ) ) {
				continue;
			}

			$id = WPCPM_Institution_Talk_Notes::add(
				$key,
				$note['text'],
				0,
				array(
					'author_name' => $note['author'],
					'date'        => '' !== $note['date'] ? $note['date'] : $now,
					'source'      => $note['source'],
				)
			);

			if ( is_wp_error( $id ) ) {
				$done['failed'][] = sprintf(
					/* translators: 1: where the note came from, 2: why it was refused. */
					__( 'The note %1$s was not added: %2$s', 'wpcredits-program-manager' ),
					$note['source'],
					$id->get_error_message()
				);

				continue;
			}

			++$added;
		}

		$done['notes'] += $added;

		return $added;
	}

	/**
	 * Say in a school's history what was brought to it: when this run brought something, or when an earlier run did
	 * (the notes of its sources are on the site) and stopped before it wrote the row.
	 *
	 * @param string   $key     A record ID or a lead key.
	 * @param string   $from    Where from, as a sentence names it ("Airtable", "the Outreach Tracker").
	 * @param string   $tag     The part, as the row's data keeps it (`FROM_AIRTABLE`, `FROM_OUTREACH` and a record ID).
	 * @param int      $notes   How many notes were added.
	 * @param string[] $brought The fields filled (record keys, and `application`).
	 * @param bool     $created Whether the lead was made now.
	 * @param int      $present How many of the part's notes an earlier run brought.
	 */
	private static function log( $key, $from, $tag, $notes, array $brought, $created, $present ) {
		if ( 0 === $notes && array() === $brought && ! $created ) {
			if ( $present < 1 || self::logged( $key, $tag ) ) {
				return;
			}

			$notes = (int) $present;
		}

		$parts = array();

		if ( $notes > 0 ) {
			/* translators: %d: how many notes. */
			$parts[] = sprintf( _n( '%d note', '%d notes', $notes, 'wpcredits-program-manager' ), $notes );
		}

		foreach ( self::FILLED as $field ) {
			if ( in_array( $field, $brought, true ) ) {
				$parts[] = self::field_phrase( $field );
			}
		}

		if ( in_array( 'application', $brought, true ) ) {
			$parts[] = __( 'the application\'s answers', 'wpcredits-program-manager' );
		}

		$message = array() === $parts
			/* translators: %s: where from, as "the Outreach Tracker". */
			? sprintf( __( 'Brought over from %s.', 'wpcredits-program-manager' ), $from )
			/* translators: 1: where from, as "Airtable", 2: what came over, as "4 notes, the next step and the follow-up". */
			: sprintf( __( 'Brought over from %1$s: %2$s.', 'wpcredits-program-manager' ), $from, self::joined( $parts ) );

		$data = array(
			'from'   => $tag,
			'notes'  => (int) $notes,
			'fields' => array_values( $brought ),
		);

		if ( WPCPM_Institution_Leads::is_key( $key ) ) {
			WPCPM_Institution_Audit::record_lead(
				array(
					'kind'     => self::LOG_IMPORTED,
					'lead'     => $key,
					'subject'  => $key,
					'actor'    => 0,
					'ground'   => WPCPM_Institution_Audit::GROUND_SYSTEM,
					'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'  => $message,
					'data'     => $data,
				)
			);

			return;
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_IMPORTED,
				'institution' => $key,
				'subject'     => $key,
				'actor'       => 0,
				'ground'      => WPCPM_Institution_Audit::GROUND_SYSTEM,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => $message,
				'data'        => $data,
			)
		);
	}

	/**
	 * The schools of the index by stage: the stages the site knows in their order, then any other A to Z,
	 * then no stage.
	 *
	 * @return array<string, int>
	 */
	private static function stages() {
		$count = array();

		foreach ( WPCPM_Institutions_Index::rows() as $row ) {
			$stage = is_array( $row ) && isset( $row['stage'] ) ? trim( (string) $row['stage'] ) : '';

			$count[ $stage ] = isset( $count[ $stage ] ) ? $count[ $stage ] + 1 : 1;
		}

		$out = array();

		foreach ( WPCPM_Institution_Talks::stages() as $stage ) {
			if ( isset( $count[ $stage ] ) ) {
				$out[ $stage ] = $count[ $stage ];
				unset( $count[ $stage ] );
			}
		}

		$none = isset( $count[''] ) ? $count[''] : 0;
		unset( $count[''] );
		ksort( $count, SORT_STRING );

		$out = $out + $count;

		if ( $none > 0 ) {
			$out[''] = $none;
		}

		return $out;
	}

	/**
	 * How a new lead is assigned, as the end of a sentence.
	 *
	 * @param array $entry The row's entry.
	 * @return string
	 */
	private static function assigned_text( array $entry ) {
		$user = isset( $entry['lead']['assigned'] ) ? (int) $entry['lead']['assigned'] : 0;

		if ( 0 === $user ) {
			return __( 'not assigned', 'wpcredits-program-manager' );
		}

		return sprintf(
			'area' === $entry['assigned_by']
				/* translators: %s: an Administrator's name. */
				? __( 'assigned to %s by its country\'s area', 'wpcredits-program-manager' )
				/* translators: %s: an Administrator's name. */
				: __( 'assigned to %s by its Lead Owner', 'wpcredits-program-manager' ),
			WPCPM_Institution_Assignment::label( $user )
		);
	}

	/**
	 * Whether a planned note is a record comment.
	 *
	 * @param array $note A planned note.
	 * @return bool
	 */
	private static function is_comment( array $note ) {
		return 0 === strpos( $note['source'], self::SOURCE_COMMENT );
	}

	/**
	 * A field as a count's label names it.
	 *
	 * @param string $field A key of `FILLED`.
	 * @return string
	 */
	private static function field_label( $field ) {
		$labels = array(
			'next_step'          => __( 'next step', 'wpcredits-program-manager' ),
			'follow_up'          => __( 'follow-up', 'wpcredits-program-manager' ),
			'last_contact'       => __( 'last contact', 'wpcredits-program-manager' ),
			'estimated_students' => __( 'estimated students', 'wpcredits-program-manager' ),
			'help_desk'          => __( 'help desk link', 'wpcredits-program-manager' ),
			'lead_owner'         => __( 'lead owner', 'wpcredits-program-manager' ),
			'agreement_document' => __( 'agreement\'s Drive link', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
	}

	/**
	 * A field as the history's sentence names it: "the next step".
	 *
	 * @param string $field A key of `FILLED`.
	 * @return string
	 */
	private static function field_phrase( $field ) {
		$phrases = array(
			'next_step'          => __( 'the next step', 'wpcredits-program-manager' ),
			'follow_up'          => __( 'the follow-up', 'wpcredits-program-manager' ),
			'last_contact'       => __( 'the last contact date', 'wpcredits-program-manager' ),
			'estimated_students' => __( 'the estimated students', 'wpcredits-program-manager' ),
			'help_desk'          => __( 'the help desk link', 'wpcredits-program-manager' ),
			'lead_owner'         => __( 'the lead owner', 'wpcredits-program-manager' ),
			'agreement_document' => __( 'the agreement\'s Drive link', 'wpcredits-program-manager' ),
		);

		return isset( $phrases[ $field ] ) ? $phrases[ $field ] : $field;
	}

	/**
	 * A date column's value that is not a real day, as the summary names it.
	 *
	 * @param string $column The column.
	 * @param string $name   The school's name.
	 * @param string $value  The value.
	 * @return string
	 */
	private static function not_a_day( $column, $name, $value ) {
		return sprintf(
			/* translators: 1: an Airtable column, 2: a school's name, 3: the value Airtable holds. */
			__( 'Left out: the %1$s of %2$s, "%3$s", is not a real day.', 'wpcredits-program-manager' ),
			$column,
			$name,
			$value
		);
	}

	/**
	 * The help desk link a cell holds, and what else it holds, which comes over as a note.
	 *
	 * @param string $text The cell, as text.
	 * @return array{link: string, rest: string} `link` the first https address, '' for none; `rest` the whole
	 *                                           text when it is more than that address, else ''.
	 */
	private static function help_desk( $text ) {
		$text = trim( (string) $text );
		$link = '';

		if ( 1 === preg_match( '#https://[^\s<>()\[\]"\']+#u', $text, $found ) ) {
			$candidate = rtrim( $found[0], '.,;:!?' );
			$link      = false !== filter_var( $candidate, FILTER_VALIDATE_URL ) ? $candidate : '';
		}

		return array(
			'link' => $link,
			'rest' => '' !== $text && $text !== $link ? $text : '',
		);
	}

	/**
	 * A cell as text: a list joined by commas, trimmed.
	 *
	 * @param array  $cells  The row's cells.
	 * @param string $column The column.
	 * @return string
	 */
	private static function text( array $cells, $column ) {
		if ( ! isset( $cells[ $column ] ) || is_bool( $cells[ $column ] ) ) {
			return '';
		}

		return trim( WPCPM_Airtable::flatten( $cells[ $column ] ) );
	}

	/**
	 * A text as one line: runs of space, tabs and line ends as one space.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	private static function line( $text ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );
	}

	/**
	 * A name as two are matched: trimmed, lowercase, runs of spaces as one.
	 *
	 * @param mixed $name A name.
	 * @return string
	 */
	private static function fold( $name ) {
		$name = self::line( is_scalar( $name ) ? (string) $name : '' );

		// A composed and a decomposed letter are one letter, where the intl extension can say so.
		if ( '' !== $name && function_exists( 'normalizer_normalize' ) ) {
			$normal = normalizer_normalize( $name, Normalizer::FORM_C );
			$name   = is_string( $normal ) ? $normal : $name;
		}

		return mb_strtolower( $name, 'UTF-8' );
	}

	/**
	 * Whether a value says nothing, as `WPCPM_Institution_Record` counts it.
	 *
	 * @param mixed $value A value.
	 * @return bool
	 */
	private static function is_blank( $value ) {
		return null === $value || '' === $value || array() === $value || ( is_string( $value ) && '' === trim( $value ) );
	}

	/**
	 * Whether a value is a real day as `Y-m-d`.
	 *
	 * @param string $date Candidate.
	 * @return bool
	 */
	private static function is_date( $date ) {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/D', (string) $date ) ) {
			return false;
		}

		$parsed = DateTime::createFromFormat( '!Y-m-d', (string) $date, new DateTimeZone( 'UTC' ) );

		return $parsed instanceof DateTime && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Every string of a read, repaired where it is not valid UTF-8: each broken sequence becomes U+FFFD.
	 *
	 * @param mixed $value A read, or a part of one.
	 * @param int   $count How many strings were repaired, added to.
	 * @return mixed
	 */
	private static function repaired( $value, &$count ) {
		if ( is_array( $value ) ) {
			$out = array();

			foreach ( $value as $key => $item ) {
				$out[ self::repaired( $key, $count ) ] = self::repaired( $item, $count );
			}

			return $out;
		}

		if ( ! is_string( $value ) || mb_check_encoding( $value, 'UTF-8' ) ) {
			return $value;
		}

		++$count;

		$was = mb_substitute_character();
		mb_substitute_character( 0xFFFD );
		$fixed = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
		mb_substitute_character( $was );

		return $fixed;
	}

	/**
	 * Whether a value is a read in the shape `read()` returns.
	 *
	 * @param mixed $read Candidate.
	 * @return bool
	 */
	private static function is_read( $read ) {
		if ( ! is_array( $read ) || ! isset( $read['v'] ) || self::VERSION !== (int) $read['v'] || ! isset( $read['read'] ) || ! is_numeric( $read['read'] ) ) {
			return false;
		}

		foreach ( array( 'institutions', 'team', 'outreach', 'tutors', 'comments' ) as $part ) {
			if ( ! isset( $read[ $part ] ) || ! is_array( $read[ $part ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A path as the operator typed it, a leading `~/` read as their home folder (a shell leaves it as it is after `=`).
	 *
	 * @param mixed $path Candidate.
	 * @return string
	 */
	private static function expanded( $path ) {
		$path = is_string( $path ) ? trim( $path ) : '';
		$home = (string) getenv( 'HOME' );

		return 0 === strpos( $path, '~/' ) && '' !== $home ? rtrim( $home, '/' ) . substr( $path, 1 ) : $path;
	}

	/**
	 * Whether a folder is inside the plugin, inside WordPress or inside a git work tree.
	 *
	 * @param string $folder A real path.
	 * @return bool
	 */
	private static function in_repository_or_site( $folder ) {
		$folder = rtrim( $folder, '/' ) . '/';

		foreach ( array( WPCPM_PLUGIN_DIR, ABSPATH ) as $root ) {
			$real = realpath( $root );

			if ( false !== $real && 0 === strpos( $folder, rtrim( $real, '/' ) . '/' ) ) {
				return true;
			}
		}

		for ( $at = rtrim( $folder, '/' ); '' !== $at; $at = dirname( $at ) ) {
			if ( file_exists( $at . '/.git' ) ) {
				return true;
			}

			if ( dirname( $at ) === $at ) {
				break;
			}
		}

		return false;
	}

	/**
	 * The HTTP status a client's error carries, 0 for none.
	 *
	 * @param WP_Error $error The error.
	 * @return int
	 */
	private static function status_of( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
	}

	/**
	 * The refusal of the record comments, said so the operator can act on it.
	 *
	 * @param WP_Error $error The read's error.
	 * @return WP_Error
	 */
	private static function comments_refused( WP_Error $error ) {
		if ( in_array( self::status_of( $error ), array( 401, 403 ), true ) ) {
			return new WP_Error( 'wpcpm_import_comments', __( 'Airtable refused the record comments, so nothing was brought over. Give the token the scope data.recordComments:read, or run with --skip-comments and bring the comments over later.', 'wpcredits-program-manager' ) );
		}

		return $error;
	}

	/**
	 * Names as a sentence part: "A", "A and B", "A, B and C".
	 *
	 * @param string[] $parts The parts.
	 * @return string
	 */
	private static function joined( array $parts ) {
		$last = array_pop( $parts );

		if ( array() === $parts ) {
			return (string) $last;
		}

		/* translators: 1: parts separated by commas, 2: the last part. */
		return sprintf( __( '%1$s and %2$s', 'wpcredits-program-manager' ), implode( ', ', $parts ), $last );
	}
}
