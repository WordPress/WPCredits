<?php
/**
 * Institutions module - the audit log.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per applied change or membership event against an institution, with the
 * ground it was allowed on.
 *
 * The Sponsors module writes its rows about a sponsor here too, under a key of its own
 * (`META_SPONSOR`), the rows about the program's own work, an event the program plans
 * itself, under a third (`META_PROGRAM`), and the Claude access rows, one per call to an ability
 * of the program's own, under a fourth (`META_MCP`), and the rows about a lead, a school the program
 * has not talked to yet, under a fifth (`META_LEAD`) until it is promoted, and the openings of a
 * student's AI Mentor transcript under a sixth (`META_TUTOR`): each module's readers ask
 * for their own key, so one log holds the six and none of them lists another's rows.
 *
 * A private post type in the shape of `WPCPM_Mentor_Notes`: applied changes only, one row
 * per save, no count cap, no IP addresses. Membership events (`member_added`,
 * `member_removed`) and agreement events are rows of the same type, so one list per
 * institution tells the whole story in order. Every row carries who did it, on which
 * ground (`manager`, `member`, or `system` for a sync), and what the evidence was when the
 * decision was made (`index`, `cache` or `live`), because the question a manager asks of a
 * log is never "what changed" alone but "who was allowed to, and on what basis".
 *
 * What this log cannot see: the Airtable automations. A Status, a Start Date, an End Date
 * or a Mentor written from the site can be overwritten by the base's own automations
 * minutes later, and nothing here records that. So "last write wins is detectable after
 * the fact" holds for the fields only the site writes; for those four it does not, and a
 * row saying the site wrote a value is not proof the value stood.
 */
class WPCPM_Institution_Audit {

	const POST_TYPE = 'wpcpm_audit_entry';

	/** Post meta: what happened, e.g. `member_added`. */
	const META_KIND = '_wpcpm_log_kind';

	/** Post meta: the Airtable institution record the row is about. The queryable key. */
	const META_INSTITUTION = '_wpcpm_log_institution';

	/**
	 * The sponsor a row is about, for the Sponsors module's rows; `META_INSTITUTION` is absent
	 * on those and this key is absent on an institution's, so each module's readers see only
	 * their own rows, and a row about the program's own work carries neither (`META_PROGRAM`).
	 * One log, three keys, the class name kept: renaming the log would touch every institution
	 * suite for no behavior (design spec of 4 September 2026, section 5.5).
	 */
	const META_SPONSOR = '_wpcpm_log_sponsor';

	/**
	 * The mark of a row about the program's own work, which is about no institution and no sponsor:
	 * it holds `program`, and neither `META_INSTITUTION` nor `META_SPONSOR` is on such a row, so the
	 * institution readers and the sponsor readers never list it.
	 */
	const META_PROGRAM = '_wpcpm_log_program';

	/**
	 * The mark of a row about one call to an ability (Claude access, 1.123.30), answered, refused or
	 * failed, or about one refusal by the MCP address's door or its password fence:
	 * it holds `mcp`, and none of the other three keys is on such a row, so no institution reader, no
	 * sponsor reader and no program reader lists it, and only `mcp_entries()` does. The facts of the
	 * call are in `META_DATA`. The rows are about no record of the program: they say who asked the
	 * site what, and when.
	 */
	const META_MCP = '_wpcpm_log_mcp';

	/**
	 * The lead a row is about (Institutions release 2): a school the program has not talked to yet, keyed
	 * `lead-` and its post's ID (`WPCPM_Institution_Leads`), which has no record ID until it is promoted.
	 * None of the other keys is on such a row, so no institution, sponsor, program or call reader lists it,
	 * and only `entries_for_lead()` does. When the lead is promoted, `move_lead_rows()` gives its rows to the
	 * record it became, under `META_INSTITUTION`, and they are that school's history from then on.
	 */
	const META_LEAD = '_wpcpm_log_lead';

	/**
	 * The student record a row is about when an Administrator opened that account's AI Mentor transcript (AI Mentor R3),
	 * keyed by the record ID. None of the other keys is on such a row, so no institution, sponsor, program, call or lead
	 * reader lists it, and only `tutor_reads()` does. The row holds the record ID and the reader, never a word of the
	 * conversation.
	 */
	const META_TUTOR = '_wpcpm_log_tutor';

	/** Post meta: whom or what the row is about - a user ID or a record ID, as a string. */
	const META_SUBJECT = '_wpcpm_log_subject';

	/** Post meta: the user who did it; 0 for a sync or another background job. */
	const META_ACTOR = '_wpcpm_log_actor';

	/** Post meta: the ground the action was allowed on. */
	const META_GROUND = '_wpcpm_log_ground';

	/** Post meta: what the decision was made against. */
	const META_EVIDENCE = '_wpcpm_log_evidence';

	/** Post meta: structured facts about the change. Scalars and arrays of them, never prose. */
	const META_DATA = '_wpcpm_log_data';

	const KIND_MEMBER_ADDED   = 'member_added';
	const KIND_MEMBER_REMOVED = 'member_removed';

	/** The kind of every row under `META_MCP`: one call to an ability, answered, refused or failed, or one refusal by the door or the fence. */
	const KIND_MCP_CALL = 'mcp_call';

	/** The kind of every row under `META_TUTOR`: one opening of a student's AI Mentor transcript. */
	const KIND_TUTOR_READ = 'tutor_transcript_read';

	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const GROUND_TUTOR   = 'tutor';

	const EVIDENCE_INDEX = 'index';
	const EVIDENCE_CACHE = 'cache';
	const EVIDENCE_LIVE  = 'live';

	/** Longest message kept, so one row cannot swallow a pasted document. */
	const MAX_MESSAGE = 2000;

	/** How deep `data` may nest. Facts are flat; anything deeper is a structure, not a fact. */
	const MAX_DEPTH = 3;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the audit post type.
	 *
	 * Invisible everywhere by design, like the mentor notes: not public, not queryable, not
	 * in REST, not in search, no admin UI. The rows name people and the institutions they
	 * act for, and the only route to them is the Institutions screen, which checks the
	 * reader first.
	 *
	 * Rows are inserted and read as `private`, never `publish`. The author of a row is the
	 * manager who acted, and WordPress reads a published row of any type as published work
	 * by its author: `redirect_canonical()` then answers `?author=N` with a 301 to
	 * `/author/<login>/`. Every read names the status, because `get_posts()` defaults to
	 * `publish`. `WPCPM_Privacy_Guard` flips the rows written before this on upgrade.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Institution audit entries', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Institution audit entry', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor', 'author' ),
				// A capability type nothing is granted, so no role can reach these
				// through any generic post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_audit_entry', 'wpcpm_audit_entries' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The grounds a row may carry.
	 *
	 * @return string[]
	 */
	public static function grounds() {
		return array( self::GROUND_MANAGER, self::GROUND_MEMBER, self::GROUND_TUTOR, self::GROUND_SYSTEM );
	}

	/**
	 * The evidence levels a row may carry.
	 *
	 * @return string[]
	 */
	public static function evidence_levels() {
		return array( self::EVIDENCE_INDEX, self::EVIDENCE_CACHE, self::EVIDENCE_LIVE );
	}

	/**
	 * Write one row.
	 *
	 * Refuses rather than guesses: a row with no institution cannot be listed, and a row
	 * with an unknown ground would be the one row the policy did not stand behind. The
	 * actor goes on the post as its author for the admin's sake, but the meta is the
	 * record - WordPress substitutes the current user for an empty author, and a sync
	 * running under cron has none.
	 *
	 * @param array $entry `kind`, `institution` (record ID), `subject` (user ID or record ID
	 *                     as a string), `actor` (user ID, 0 for the system), `ground`,
	 *                     `evidence`, `message`, `data` (array, no prose).
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function record( array $entry ) {
		$institution = trim( isset( $entry['institution'] ) ? (string) $entry['institution'] : '' );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return new WP_Error( 'wpcpm_audit_institution', __( 'An audit row needs the institution record it is about.', 'wpcredits-program-manager' ) );
		}

		return self::write( $entry, self::META_INSTITUTION, $institution );
	}

	/**
	 * Write one row about a sponsor.
	 *
	 * The same row as `record()` writes, keyed by `META_SPONSOR` instead of `META_INSTITUTION`,
	 * so the institution readers never list it and `entries_for_sponsor()` never lists theirs.
	 *
	 * @param array $entry As for `record()`, with `sponsor` (sponsor key) in place of `institution`.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function record_sponsor( array $entry ) {
		$sponsor = trim( isset( $entry['sponsor'] ) ? (string) $entry['sponsor'] : '' );

		// class_exists() first: bin/test-audit.php and the institution suites that require this
		// file on their own - test-institution-graduate.php, -invite.php, -members.php,
		// -notes.php, -people.php, -request.php, -student-form.php and test-unlinked-link.php -
		// never load the sponsors store, so the fallback is a literal copy of
		// WPCPM_Sponsors_Index::KEY_PATTERN, `\z` anchor included.
		if ( ! ( class_exists( 'WPCPM_Sponsors_Index' ) ? WPCPM_Sponsors_Index::is_key( $sponsor ) : 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', (string) $sponsor ) ) ) {
			return new WP_Error( 'wpcpm_audit_sponsor', __( 'An audit row needs the sponsor record it is about.', 'wpcredits-program-manager' ) );
		}

		return self::write( $entry, self::META_SPONSOR, $sponsor );
	}

	/**
	 * Write one row about the program's own work, which no institution and no sponsor is the subject
	 * of: an event the program plans itself, for one.
	 *
	 * The same row as `record()` writes, keyed by `META_PROGRAM` instead, so the institution readers
	 * and `entries_for_sponsor()` and `sponsor_entries()` never list it. An `institution` or a
	 * `sponsor` in the entry is not written: the row is about the program. The kind, the ground and
	 * the evidence are checked as for every row.
	 *
	 * @param array $entry As for `record()`, with no institution.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function record_program( array $entry ) {
		return self::write( $entry, self::META_PROGRAM, 'program' );
	}

	/**
	 * Write one row about a call to an ability (Claude access).
	 *
	 * The same row as `record()` writes, keyed by `META_MCP` instead, so the institution readers and
	 * the sponsor readers never list it, and `mcp_entries()` lists nothing else. The kind is always
	 * `mcp_call`, whatever the entry says, since a row under this key is nothing but a call, and an
	 * `institution` or a `sponsor` in the entry is not written. The ground and the evidence are
	 * checked as for every row. The facts of the call are `data`: an ability name, an outcome, the
	 * input and the record ids that went out, never the answer itself and never prose.
	 *
	 * @param array $entry As for `record()`, with no institution and no kind.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function record_mcp( array $entry ) {
		$entry['kind'] = self::KIND_MCP_CALL;

		return self::write( $entry, self::META_MCP, 'mcp' );
	}

	/**
	 * Write one row about a lead.
	 *
	 * The same row as `record()` writes, keyed by `META_LEAD` instead of `META_INSTITUTION`, so no
	 * institution reader lists it until `move_lead_rows()` gives it to the record the lead became. An
	 * `institution` or a `sponsor` in the entry is not written. The kind, the ground and the evidence are
	 * checked as for every row.
	 *
	 * @param array $entry As for `record()`, with `lead` (a lead key) in place of `institution`.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function record_lead( array $entry ) {
		$lead = trim( isset( $entry['lead'] ) && is_scalar( $entry['lead'] ) ? (string) $entry['lead'] : '' );

		if ( ! self::is_lead_key( $lead ) ) {
			return new WP_Error( 'wpcpm_audit_lead', __( 'An audit row needs the lead it is about.', 'wpcredits-program-manager' ) );
		}

		return self::write( $entry, self::META_LEAD, $lead );
	}

	/**
	 * Every row about a lead, newest first.
	 *
	 * @param string $lead  A lead key.
	 * @param int    $limit How many at most; 0 or less for all of them.
	 * @return array[] As `entries_for()` returns them.
	 */
	public static function entries_for_lead( $lead, $limit = 50 ) {
		return self::entries_on( self::META_LEAD, $lead, $limit );
	}

	/**
	 * Write one row about an Administrator opening a student's AI Mentor transcript (AI Mentor R3).
	 *
	 * Keyed by `META_TUTOR`, so no other reader lists it. The kind is always `tutor_transcript_read`, the ground the
	 * manager's and the evidence live; the subject is the record ID, and there is no data and no text: who read what is
	 * the whole fact. The existing `GROUND_TUTOR` is an institution's tutor and has nothing to do with this.
	 *
	 * @param string $record The student's record ID.
	 * @param int    $reader The Administrator who opened the transcript.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function record_tutor_read( $record, $reader ) {
		$record = trim( (string) $record );

		if ( ! self::is_student_record( $record ) ) {
			return new WP_Error( 'wpcpm_audit_tutor', __( 'An audit row needs the student record it is about.', 'wpcredits-program-manager' ) );
		}

		return self::write(
			array(
				'kind'     => self::KIND_TUTOR_READ,
				'subject'  => $record,
				'actor'    => (int) $reader,
				'ground'   => self::GROUND_MANAGER,
				'evidence' => self::EVIDENCE_LIVE,
			),
			self::META_TUTOR,
			$record
		);
	}

	/**
	 * The openings of one student's AI Mentor transcript, newest first.
	 *
	 * @param string $record A student's record ID.
	 * @param int    $limit  How many at most; 0 or less for all of them.
	 * @return array[] As `entries_for()` returns them.
	 */
	public static function tutor_reads( $record, $limit = 5 ) {
		return self::entries_on( self::META_TUTOR, $record, $limit );
	}

	/**
	 * Whether a value is a student's record ID, the shape the Students table gives.
	 *
	 * @param string $value The value.
	 * @return bool
	 */
	private static function is_student_record( $value ) {
		// class_exists() first: bin/test-audit.php and the suites that load this file on its own do not all load the sync.
		return class_exists( 'WPCPM_Mentors_Sync' ) ? WPCPM_Mentors_Sync::is_record_id( $value ) : 1 === preg_match( '/^rec[A-Za-z0-9]{14}\z/', (string) $value );
	}

	/**
	 * Give a lead's rows to the record it became: each row is keyed by the record (`META_INSTITUTION`) and
	 * no longer by the lead, and a row whose subject was the lead is about the record. The query asks for
	 * `any` status, which core reads as every status but the trash and an auto-draft: a row is written
	 * private, so every row a reader lists moves, and a row somebody trashed stays under the lead's key,
	 * where no reader lists it.
	 *
	 * Safe to repeat: a row moved is no longer under the lead's key, so a promotion that stops after it and goes on
	 * moves only the rows left, once each (the outside review of release 2, O4). A write counts when the row reads the
	 * wanted value after it, since `update_post_meta()` answers false for a value it already holds; a row whose record
	 * key could not be written keeps its lead key, to be moved by the next call.
	 *
	 * @param string $lead_key  The lead key the rows are about now.
	 * @param string $record_id The Airtable record ID the lead was promoted into.
	 * @return int How many rows could NOT be moved (0 when every one moved); 0 too when either key is of the wrong
	 *             shape, where there is nothing to move.
	 */
	public static function move_lead_rows( $lead_key, $record_id ) {
		$lead_key  = trim( is_scalar( $lead_key ) ? (string) $lead_key : '' );
		$record_id = trim( is_scalar( $record_id ) ? (string) $record_id : '' );

		if ( ! self::is_lead_key( $lead_key ) || ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_LEAD,
						'value' => $lead_key,
					),
				),
			)
		);

		$left = 0;

		foreach ( (array) $ids as $id ) {
			$id = (int) $id;

			// The collation's match, checked again: a lead key is lowercase, and only this one's rows move.
			if ( 0 !== strcmp( (string) get_post_meta( $id, self::META_LEAD, true ), $lead_key ) ) {
				continue;
			}

			// The record's key first, so a request that dies here leaves the row listed by the school.
			if ( false === update_post_meta( $id, self::META_INSTITUTION, $record_id ) && (string) get_post_meta( $id, self::META_INSTITUTION, true ) !== $record_id ) {
				++$left;

				continue;
			}

			if ( (string) get_post_meta( $id, self::META_SUBJECT, true ) === $lead_key && false === update_post_meta( $id, self::META_SUBJECT, $record_id ) ) {
				++$left;

				continue;
			}

			if ( false === delete_post_meta( $id, self::META_LEAD ) && '' !== (string) get_post_meta( $id, self::META_LEAD, true ) ) {
				++$left;
			}
		}

		return $left;
	}

	/**
	 * Whether a value is a lead key, as `WPCPM_Institution_Talks::is_lead_key()` says.
	 *
	 * The talks are asked first only when they are loaded: bin/test-audit.php and the suites that require this
	 * file on their own never load them, so the fallback is a literal copy of that pattern.
	 *
	 * @param string $key Candidate.
	 * @return bool
	 */
	private static function is_lead_key( $key ) {
		return class_exists( 'WPCPM_Institution_Talks' ) ? WPCPM_Institution_Talks::is_lead_key( $key ) : 1 === preg_match( '/^lead-[1-9][0-9]*$/D', (string) $key );
	}

	/**
	 * The one writer behind `record()`, `record_sponsor()`, `record_program()`, `record_mcp()`, `record_lead()` and `record_tutor_read()`.
	 *
	 * Refuses rather than guesses: a row with an unknown ground would be the one row the policy
	 * did not stand behind. The actor goes on the post as its author for the admin's sake, but
	 * the meta is the record: WordPress substitutes the current user for an empty author, and a
	 * sync running under cron has none.
	 *
	 * The post and the data are written as slashed copies (`wp_slash()`): core unslashes what it
	 * is handed, and a message or data built around a typed note or a name is unslashed.
	 *
	 * @param array  $entry    The entry, its record already validated by the caller.
	 * @param string $meta_key `META_INSTITUTION`, `META_SPONSOR`, `META_PROGRAM`, `META_MCP`, `META_LEAD` or `META_TUTOR`.
	 * @param string $record   The record ID the row is about, `program` for the program's own,
	 *                         `mcp` for a call to an ability, a lead key, or a student's record ID.
	 * @return int|WP_Error
	 */
	private static function write( array $entry, $meta_key, $record ) {
		$kind = sanitize_key( isset( $entry['kind'] ) ? (string) $entry['kind'] : '' );

		if ( '' === $kind ) {
			return new WP_Error( 'wpcpm_audit_kind', __( 'An audit row needs a kind.', 'wpcredits-program-manager' ) );
		}

		$ground = sanitize_key( isset( $entry['ground'] ) ? (string) $entry['ground'] : '' );

		if ( ! in_array( $ground, self::grounds(), true ) ) {
			return new WP_Error( 'wpcpm_audit_ground', __( 'An audit row needs the ground the action was allowed on.', 'wpcredits-program-manager' ) );
		}

		$evidence = sanitize_key( isset( $entry['evidence'] ) ? (string) $entry['evidence'] : '' );

		if ( ! in_array( $evidence, self::evidence_levels(), true ) ) {
			return new WP_Error( 'wpcpm_audit_evidence', __( 'An audit row needs to say what the decision was made against.', 'wpcredits-program-manager' ) );
		}

		$subject = sanitize_text_field( isset( $entry['subject'] ) ? (string) $entry['subject'] : '' );
		$actor   = isset( $entry['actor'] ) ? absint( $entry['actor'] ) : 0;
		$message = sanitize_textarea_field( isset( $entry['message'] ) ? (string) $entry['message'] : '' );
		$message = trim( mb_substr( $message, 0, self::MAX_MESSAGE ) );
		$data    = self::clean_data( isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : array(), 1 );

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_author'  => $actor,
					'post_content' => $message,
					'post_title'   => sprintf(
						/* translators: 1: event kind, 2: the record ID the row is about, 3: date and time. */
						__( '%1$s on %2$s - %3$s', 'wpcredits-program-manager' ),
						$kind,
						$record,
						wp_date( 'Y-m-d H:i' )
					),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_KIND, $kind );
		update_post_meta( $post_id, self::META_SUBJECT, $subject );
		update_post_meta( $post_id, self::META_ACTOR, $actor );
		update_post_meta( $post_id, self::META_GROUND, $ground );
		update_post_meta( $post_id, self::META_EVIDENCE, $evidence );
		update_post_meta( $post_id, self::META_DATA, wp_slash( $data ) );

		// The key that lists the row last (Task 18 fix round 2): every reader finds a row by this key alone, so a
		// request killed before it leaves a row nobody lists, never one listed without its data.
		update_post_meta( $post_id, $meta_key, $record );

		return (int) $post_id;
	}

	/**
	 * Every row about an institution, newest first.
	 *
	 * @param string $institution Airtable record ID.
	 * @param int    $limit       How many at most; 0 or less for all of them.
	 * @return array[] Entries: `id`, `kind`, `institution`, `subject`, `actor`, `ground`,
	 *                 `evidence`, `message`, `data`, `time` (unix, UTC).
	 */
	public static function entries_for( $institution, $limit = 50 ) {
		return self::entries_on( self::META_INSTITUTION, $institution, $limit );
	}

	/**
	 * Every row about a sponsor, newest first.
	 *
	 * @param string $sponsor Sponsor key.
	 * @param int    $limit   How many at most; 0 or less for all of them.
	 * @return array[] As `entries_for()` returns them.
	 */
	public static function entries_for_sponsor( $sponsor, $limit = 50 ) {
		return self::entries_on( self::META_SPONSOR, $sponsor, $limit );
	}

	/**
	 * The sponsor rows across every sponsor, newest first: the wp-admin screen's interests log.
	 *
	 * @param string $kind  One kind, or '' for every kind.
	 * @param int    $limit How many at most; 0 or less for all of them.
	 * @return array[]
	 */
	public static function sponsor_entries( $kind = '', $limit = 50 ) {
		return self::entries_with_key( self::META_SPONSOR, $kind, $limit );
	}

	/**
	 * The rows about calls to an ability across every account, newest first: the Security tab's latest
	 * calls and the command that lists more.
	 *
	 * @param int $limit How many at most; 0 or less for all of them.
	 * @return array[] As `entries_for()` returns them.
	 */
	public static function mcp_entries( $limit = 100 ) {
		return self::entries_with_key( self::META_MCP, '', $limit );
	}

	/**
	 * Delete the rows about calls that are older than a time, and no other row.
	 *
	 * The oldest first, at most `$limit` of them, so a run that has a great many to delete deletes them
	 * over a few runs and none of them is long. A row is old when its GMT date is before the time, so
	 * a row written at that very second stays. The query asks for the fourth key, so a row under another
	 * key, however old, is never in it.
	 *
	 * @param int $before Unix time: rows written before it are deleted.
	 * @param int $limit  How many at most; none for zero or less.
	 * @return int How many were deleted.
	 */
	public static function delete_mcp_before( $before, $limit = 500 ) {
		$before = (int) $before;
		$limit  = (int) $limit;

		if ( $before < 1 || $limit < 1 ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => $limit,
				'fields'           => 'ids',
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'suppress_filters' => false,
				'date_query'       => array(
					array(
						'column'    => 'post_date_gmt',
						'before'    => gmdate( 'Y-m-d H:i:s', $before ),
						'inclusive' => false,
					),
				),
				'meta_query'       => array(
					array(
						'key'     => self::META_MCP,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * The rows that carry a key, whatever it holds, across every record, newest first.
	 *
	 * @param string $meta_key `META_SPONSOR` or `META_MCP`.
	 * @param string $kind     One kind, or '' for every kind.
	 * @param int    $limit    How many at most; 0 or less for all of them.
	 * @return array[]
	 */
	private static function entries_with_key( $meta_key, $kind, $limit ) {
		$kind  = sanitize_key( (string) $kind );
		$limit = (int) $limit;
		$query = array(
			array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			),
		);

		if ( '' !== $kind ) {
			$query[] = array(
				'key'   => self::META_KIND,
				'value' => $kind,
			);
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'numberposts'      => $limit > 0 ? $limit : -1,
				'orderby'          => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => $query,
			)
		);

		$entries = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$entries[] = self::entry_from( $post );
			}
		}

		return $entries;
	}

	/**
	 * The rows keyed by one of the record keys, with the case re-check `entries_for()` always made.
	 *
	 * @param string $meta_key `META_INSTITUTION`, `META_SPONSOR`, `META_LEAD` or `META_TUTOR`.
	 * @param string $record   An institution's Airtable record ID, a sponsor's key, a lead key, or a student's record ID.
	 * @param int    $limit    How many at most; 0 or less for all of them.
	 * @return array[]
	 */
	private static function entries_on( $meta_key, $record, $limit ) {
		$record = trim( (string) $record );

		if ( self::META_LEAD === $meta_key ) {
			if ( ! self::is_lead_key( $record ) ) {
				return array();
			}
		} elseif ( self::META_TUTOR === $meta_key ) {
			if ( ! self::is_student_record( $record ) ) {
				return array();
			}
		} elseif ( ! ( class_exists( 'WPCPM_Sponsors_Index' ) ? WPCPM_Sponsors_Index::is_key( $record ) : 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', (string) $record ) ) ) {
			// class_exists() first: bin/test-audit.php and the institution suites that require this
			// file on their own - test-institution-graduate.php, -invite.php, -members.php,
			// -notes.php, -people.php, -request.php, -student-form.php and test-unlinked-link.php -
			// never load the sponsors store, so the fallback is a literal copy of
			// WPCPM_Sponsors_Index::KEY_PATTERN, `\z` anchor included.
			return array();
		}

		$limit = (int) $limit;

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'numberposts'      => $limit > 0 ? $limit : -1,
				// ID breaks the tie: attach and detach in one request, or the revoke loop's rows,
				// share a second, and date alone leaves their order to the database.
				'orderby'          => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => $meta_key,
						'value' => $record,
					),
				),
			)
		);

		$entries = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			// The query matched under the database collation, which does not tell `recABC`
			// from `recabc`; record IDs do. Keep only the rows that name this record.
			if ( 0 !== strcmp( (string) get_post_meta( $post->ID, $meta_key, true ), $record ) ) {
				continue;
			}

			$entries[] = self::entry_from( $post );
		}

		return $entries;
	}

	/**
	 * One row as an array, read from the post and its meta.
	 *
	 * @param WP_Post $post The row.
	 * @return array
	 */
	private static function entry_from( WP_Post $post ) {
		$data = get_post_meta( $post->ID, self::META_DATA, true );

		return array(
			'id'          => (int) $post->ID,
			'kind'        => (string) get_post_meta( $post->ID, self::META_KIND, true ),
			'institution' => (string) get_post_meta( $post->ID, self::META_INSTITUTION, true ),
			'sponsor'     => (string) get_post_meta( $post->ID, self::META_SPONSOR, true ),
			'subject'     => (string) get_post_meta( $post->ID, self::META_SUBJECT, true ),
			'actor'       => (int) get_post_meta( $post->ID, self::META_ACTOR, true ),
			'ground'      => (string) get_post_meta( $post->ID, self::META_GROUND, true ),
			'evidence'    => (string) get_post_meta( $post->ID, self::META_EVIDENCE, true ),
			'message'     => (string) $post->post_content,
			'data'        => is_array( $data ) ? $data : array(),
			'time'        => (int) get_post_time( 'U', true, $post ),
		);
	}

	/**
	 * Reduce `data` to facts: scalars, and arrays of scalars, a few levels deep.
	 *
	 * Strings are sanitized as single-line text, so a paragraph arrives here folded to one line:
	 * an agreement's revoke carries the note it was revoked with under `note` that way, for the
	 * institution's history to show, and the document keeps the lines. The message field is where
	 * prose belongs, and it is capped. Objects and resources are dropped rather than serialized,
	 * since nothing that renders the log knows what to do with them.
	 *
	 * @param array $data  What the caller handed in.
	 * @param int   $depth Current nesting level, from 1.
	 * @return array
	 */
	private static function clean_data( array $data, $depth ) {
		$clean = array();

		foreach ( $data as $key => $value ) {
			$key = is_int( $key ) ? $key : sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				if ( $depth >= self::MAX_DEPTH ) {
					continue;
				}

				$clean[ $key ] = self::clean_data( $value, $depth + 1 );
			} elseif ( is_string( $value ) ) {
				$clean[ $key ] = sanitize_text_field( $value );
			} elseif ( is_int( $value ) || is_float( $value ) || is_bool( $value ) || null === $value ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Delete every row. Called on uninstall.
	 *
	 * Post meta goes with the posts, so no `delete_metadata()` line is needed for it.
	 */
	public static function delete_all() {
		$entries = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $entries as $entry_id ) {
			wp_delete_post( $entry_id, true );
		}
	}
}
