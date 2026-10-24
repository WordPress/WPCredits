<?php
/**
 * Institutions module - the institution's own record on the site: what an application told us
 * that the Airtable base has no column for.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per institution, keyed by its Airtable record ID, holding the answers the
 * application form keeps on this site (`WPCPM_Institution_Application::site_answers()`): the
 * contact's role, the languages the students study in, who will sign the agreement, their title
 * and the institution's page about the program.
 *
 * **Why a post of its own.** The answers live on the application until the retention purge
 * removes it, and an institution outlives its application by years. Approval copies them here
 * (`WPCPM_Institution_Approval::approve()`), under the record ID every other part of the plugin
 * already keys an institution by, so they are still on the site when the application is gone.
 * Kept the way the sponsors' rows are (`WPCPM_Sponsors_Index`): a private post, found by a meta
 * value, created the first time something is written and never before. An institution approved
 * before 1.123.29 has no such answers, so it has no record, and a reader asks `get()` and is
 * answered with an empty list.
 *
 * **The first answers stand.** `fill_empty()` is the only way in, and it only fills what is
 * empty. Approval joins an application to a record the program already has without writing
 * anything the applicant typed over the Airtable record, and the site's record follows the same
 * rule; and a second press of Approve, or the resume of one that stopped partway, asks again and
 * changes nothing. Release 2's corrections will have their own, deliberate way to change an
 * answer; this class has none.
 *
 * **Not autoloaded.** The answers are post meta, which WordPress never autoloads, and the class
 * writes no option. Private everywhere: no public URL, no admin list, no REST route, and a row
 * names an institution's contact and who signs for it, so the privacy guard holds the type to
 * `private` (`WPCPM_Privacy_Guard::private_post_types()`) and uninstall deletes every row
 * (`delete_all()`).
 *
 * All static, in the shape of `WPCPM_Institution_Request`.
 */
class WPCPM_Institution_Record {

	/** One post per institution. */
	const POST_TYPE = 'wpcpm_inst_record';

	/** Private, like every post type in this module. */
	const POST_STATUS = 'private';

	/**
	 * Post meta: the Airtable institution record ID the post is for. The queryable key; the
	 * post's title says the same, so a row reads as itself in a database tool.
	 */
	const META_RECORD = '_wpcpm_inst_record';

	/** Post meta: the answers kept, an array keyed by field key. */
	const META_ANSWERS = '_wpcpm_inst_answers';

	/**
	 * The shape of an Airtable record ID: `rec` and fourteen letters or digits.
	 *
	 * `\z`, not `$`: `$` lets a trailing newline through in PCRE. The ID is trimmed first, so
	 * nothing a caller passes with space around it is refused for that alone.
	 */
	const ID_PATTERN = '/^rec[A-Za-z0-9]{14}\z/';

	/**
	 * Every post status WordPress has, named rather than left to the shorthand for "any": the
	 * shorthand leaves out trash and auto-draft, and both `post_id_of()` and `delete_all()` need
	 * the two. A record's post another process trashed is still the institution's post, and an
	 * uninstall that promises every record gone has to find it.
	 */
	const POST_STATUSES = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the post type.
	 *
	 * Invisible everywhere, like the audit log and the request queue: not public, not queryable,
	 * not in REST, not in search, no admin UI. The only way to read or write a row is this class.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Institution records', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Institution record', 'wpcredits-program-manager' ),
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
				'supports'            => array( 'title' ),
				// A capability type nothing is granted, so no role can reach these
				// through any generic post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_inst_record', 'wpcpm_inst_records' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The answers kept for one institution.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return array The kept answers, keyed by field key; an empty list for an institution that
	 *               has none, one approved before 1.123.29 among them, and for an ID that is not
	 *               a record ID.
	 */
	public static function get( $record_id ) {
		$post_id = self::post_id_of( $record_id );

		return $post_id > 0 ? self::answers_of( $post_id ) : array();
	}

	/**
	 * Keep the answers an institution has not given yet, and change none it has.
	 *
	 * A key the record holds with a real answer stands, whatever this call carries for it; a key
	 * it lacks, or holds blank, takes the call's answer; a blank answer in the call is no answer
	 * and is never written. The record's post is made by the first call that has something to
	 * keep, so a call carrying only blank answers makes none.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $answers   Answers keyed by field key.
	 * @return bool Whether the record changed. False for an ID that is not a record ID, for a call
	 *              with nothing new to keep (the second press of Approve, or a record that already
	 *              held the answers), and when WordPress refused the write.
	 */
	public static function fill_empty( $record_id, array $answers ) {
		$record_id = self::clean_id( $record_id );

		if ( '' === $record_id ) {
			return false;
		}

		$post_id = self::post_id_of( $record_id );
		$held    = $post_id > 0 ? self::answers_of( $post_id ) : array();
		$kept    = $held;

		foreach ( $answers as $key => $value ) {
			if ( self::is_blank( $value ) ) {
				continue;
			}

			if ( array_key_exists( $key, $kept ) && ! self::is_blank( $kept[ $key ] ) ) {
				continue;
			}

			$kept[ $key ] = $value;
		}

		if ( $kept === $held ) {
			return false;
		}

		if ( 0 === $post_id ) {
			$post_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'   => self::POST_TYPE,
						'post_status' => self::POST_STATUS,
						'post_title'  => $record_id,
						'post_author' => 0,
					)
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return false;
			}

			// The key before the answers: a request that dies between the two leaves a post the
			// lookup finds and the next call fills, where the other order would leave a post
			// nothing can find and the next call would make a second one.
			update_post_meta( $post_id, self::META_RECORD, $record_id );
		}

		// Slashed because post meta is unslashed on its way in, and the answers are the
		// applicant's words: a backslash or a quote in them would otherwise be gone.
		update_post_meta( $post_id, self::META_ANSWERS, wp_slash( $kept ) );

		return true;
	}

	/**
	 * Delete every record, in any status, with its meta. Uninstall only.
	 *
	 * The records outlive the application's purge on purpose, so this is the one thing that
	 * removes them.
	 */
	public static function delete_all() {
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUSES,
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * The record ID a caller handed over, trimmed, or '' when it is not one.
	 *
	 * @param mixed $record_id Candidate.
	 * @return string
	 */
	private static function clean_id( $record_id ) {
		$record_id = is_string( $record_id ) ? trim( $record_id ) : '';

		return 1 === preg_match( self::ID_PATTERN, $record_id ) ? $record_id : '';
	}

	/**
	 * The post behind a record ID, or 0.
	 *
	 * @param string $record_id Candidate record ID.
	 * @return int
	 */
	private static function post_id_of( $record_id ) {
		$record_id = self::clean_id( $record_id );

		if ( '' === $record_id ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUSES,
				'meta_key'         => self::META_RECORD,
				'meta_value'       => $record_id,
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);

		return empty( $found ) ? 0 : (int) $found[0];
	}

	/**
	 * The answers one post holds.
	 *
	 * @param int $post_id The record's post.
	 * @return array An empty list when the meta is not a list.
	 */
	private static function answers_of( $post_id ) {
		$answers = get_post_meta( $post_id, self::META_ANSWERS, true );

		return is_array( $answers ) ? $answers : array();
	}

	/**
	 * Whether an answer says nothing: not given, only space, or a list of nothing.
	 *
	 * A zero is an answer, and so is false.
	 *
	 * @param mixed $value One answer.
	 * @return bool
	 */
	private static function is_blank( $value ) {
		if ( null === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! self::is_blank( $item ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}
}
