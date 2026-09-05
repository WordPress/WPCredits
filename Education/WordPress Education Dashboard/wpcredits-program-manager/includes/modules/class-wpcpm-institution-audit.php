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
	 * their own rows. One log, two keys, the class name kept: renaming the log would touch
	 * every institution suite for no behaviour (design spec of 4 September 2026, section 5.5).
	 */
	const META_SPONSOR = '_wpcpm_log_sponsor';

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

	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';

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
		return array( self::GROUND_MANAGER, self::GROUND_MEMBER, self::GROUND_SYSTEM );
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
	 * @param array $entry As for `record()`, with `sponsor` (record ID) in place of `institution`.
	 * @return int|WP_Error The post ID, or why not.
	 */
	public static function record_sponsor( array $entry ) {
		$sponsor = trim( isset( $entry['sponsor'] ) ? (string) $entry['sponsor'] : '' );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $sponsor ) ) {
			return new WP_Error( 'wpcpm_audit_sponsor', __( 'An audit row needs the sponsor record it is about.', 'wpcredits-program-manager' ) );
		}

		return self::write( $entry, self::META_SPONSOR, $sponsor );
	}

	/**
	 * The one writer behind `record()` and `record_sponsor()`.
	 *
	 * Refuses rather than guesses: a row with an unknown ground would be the one row the policy
	 * did not stand behind. The actor goes on the post as its author for the admin's sake, but
	 * the meta is the record: WordPress substitutes the current user for an empty author, and a
	 * sync running under cron has none.
	 *
	 * @param array  $entry    The entry, its record already validated by the caller.
	 * @param string $meta_key `META_INSTITUTION` or `META_SPONSOR`.
	 * @param string $record   The record ID the row is about.
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
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_KIND, $kind );
		update_post_meta( $post_id, $meta_key, $record );
		update_post_meta( $post_id, self::META_SUBJECT, $subject );
		update_post_meta( $post_id, self::META_ACTOR, $actor );
		update_post_meta( $post_id, self::META_GROUND, $ground );
		update_post_meta( $post_id, self::META_EVIDENCE, $evidence );
		update_post_meta( $post_id, self::META_DATA, $data );

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
	 * @param string $sponsor Airtable record ID.
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
		$kind  = sanitize_key( (string) $kind );
		$limit = (int) $limit;
		$query = array(
			array(
				'key'     => self::META_SPONSOR,
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
	 * The rows keyed by one of the two record keys, with the case re-check `entries_for()` always made.
	 *
	 * @param string $meta_key `META_INSTITUTION` or `META_SPONSOR`.
	 * @param string $record   Airtable record ID.
	 * @param int    $limit    How many at most; 0 or less for all of them.
	 * @return array[]
	 */
	private static function entries_on( $meta_key, $record, $limit ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		$limit = (int) $limit;
		$field = self::META_SPONSOR === $meta_key ? 'sponsor' : 'institution';

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

			$entry = self::entry_from( $post );

			// The query matched under the database collation, which does not tell `recABC`
			// from `recabc`; record IDs do. Keep only the rows that name this record.
			if ( 0 !== strcmp( $entry[ $field ], $record ) ) {
				continue;
			}

			$entries[] = $entry;
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
	 * Strings are sanitised as single-line text so a paragraph cannot arrive here under a
	 * key called `note`; the message field is where prose belongs, and it is capped. Objects
	 * and resources are dropped rather than serialised, since nothing that renders the log
	 * knows what to do with them.
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
