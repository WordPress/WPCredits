<?php
/**
 * Institutions module - the notes of the talks with a school (Institutions off Airtable, release 2).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per note an Administrator adds to a school's talks, or the import brings over: dated,
 * signed, never edited, only added.
 *
 * **Keyed by the school's key** (`META_FOR`): its Airtable record ID, or a lead's key
 * (`WPCPM_Institution_Talks::is_lead_key()`) until the lead is promoted, when `rekey()` moves its notes to
 * the record it became.
 *
 * **Signed by an account or by a name.** A note an Administrator adds names their account (`META_AUTHOR`),
 * read as the account's name when the note is read. A note the import brings over may have been written by
 * somebody with no account here, so it is signed with the name it came with (`META_AUTHOR_NAME`). The post
 * itself names nobody as its author: core deletes or hands over a deleted account's posts, and a note
 * outlives the account of whoever wrote it.
 *
 * **The words are kept as given.** The text is post meta (`META_TEXT`), not the post's content, so no content
 * filter of core's ever rewrites a note, and it reads back byte for byte in every script. Through
 * WordPress's own tables, written as every name the site holds today.
 *
 * **Dated in UTC.** A note's date is the post's, in UTC (`post_date_gmt`), with the site's own beside it as
 * core keeps it. The import gives the date a note was written; a note added on the site is dated now.
 *
 * **Where it came from.** An imported note keeps its source (`META_SOURCE`), so a second run of the import
 * asks `has_source()` and writes nothing twice.
 *
 * Private everywhere: no public URL, no admin list, no REST route, and a note names the people a school
 * talks with, so the privacy guard holds the type to `private` (`WPCPM_Privacy_Guard::private_post_types()`)
 * and uninstall deletes every note (`delete_all()`). Nothing is autoloaded: the class writes no option.
 *
 * All static, in the shape of `WPCPM_Institution_Record`.
 */
class WPCPM_Institution_Talk_Notes {

	/** One post per note. */
	const POST_TYPE = 'wpcpm_inst_note';

	/** Private, like every post type in this module. */
	const POST_STATUS = 'private';

	/** Post meta: the key of the school the note is for, a record ID or a lead key. */
	const META_FOR = '_wpcpm_inote_for';

	/** Post meta: the user ID of the Administrator who wrote the note, 0 for an imported one. */
	const META_AUTHOR = '_wpcpm_inote_author';

	/** Post meta: the name an imported note is signed with, when it came with one. */
	const META_AUTHOR_NAME = '_wpcpm_inote_author_name';

	/** Post meta: where an imported note came from, as the import names it. */
	const META_SOURCE = '_wpcpm_inote_source';

	/** Post meta: the note's text. */
	const META_TEXT = '_wpcpm_inote_text';

	/** The longest note, in characters. */
	const MAX_LENGTH = 5000;

	/**
	 * Every post status a note's post can have, named for the reason `WPCPM_Institution_Record::POST_STATUSES`
	 * gives: `delete_all()` has to find a note another process trashed, and an unfinished draft.
	 */
	const POST_STATUSES = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );

	/**
	 * The status a note is written in until every meta of it is (Task 18 fix round 1): a note is committed by its
	 * status, the one update that moves it to `POST_STATUS` coming last, so a request killed half way leaves a draft
	 * no reader lists, `has_source()` does not find and `rekey()` does not move, and the next add writes a whole one.
	 */
	const UNFINISHED_STATUS = 'draft';

	/** The statuses a note is found in by `has_source()` and `rekey()`: every one but an unfinished draft and an auto-draft. */
	const FOUND_STATUSES = array( 'publish', 'private', 'pending', 'future', 'trash' );

	/** The statuses a note is read in: every one but the trash, an unfinished draft and an auto-draft. */
	const READ_STATUSES = array( 'publish', 'private', 'pending', 'future' );

	/**
	 * Whether mbstring measures and tests the text: null asks the extension, a bool says it.
	 *
	 * The length and the UTF-8 test read this one guard, so the two never disagree.
	 *
	 * @var bool|null
	 */
	private static $multibyte = null;

	/**
	 * Hooks. Called from `WPCPM_Institutions::boot()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the post type: invisible everywhere, like the institutions' records. The only way to read or
	 * write a note is this class.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Talks notes', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Talks note', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				// Out of WordPress's export file, as the leads are (the whole-branch wave of release 2, W6).
				'can_export'          => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'delete_with_user'    => false,
				'supports'            => array( 'title' ),
				// A capability type nothing is granted, so no role can reach these through any generic
				// post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_inst_note', 'wpcpm_inst_notes' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Add a note.
	 *
	 * @param string $key       The school's record ID or a lead key.
	 * @param string $text      The note, at most `MAX_LENGTH` characters; the space around it is trimmed.
	 * @param int    $author_id The Administrator who wrote it, or 0 for a note signed by `author_name`.
	 * @param array  $extra     `author_name` (a name for a note with no account), `date` (`Y-m-d H:i:s`,
	 *                          UTC; now when not given) and `source` (where an imported note came from).
	 * @return int|WP_Error The note's post ID, or why it was refused.
	 */
	public static function add( $key, $text, $author_id, array $extra = array() ) {
		$key = self::clean_key( $key );

		if ( '' === $key ) {
			return new WP_Error( 'wpcpm_note_key', __( 'The note is for no school the site knows.', 'wpcredits-program-manager' ) );
		}

		if ( ! is_string( $text ) || ! self::is_utf8( $text ) || '' === trim( $text ) ) {
			return new WP_Error( 'wpcpm_note_empty', __( 'Write the note first.', 'wpcredits-program-manager' ) );
		}

		$text = trim( $text );

		if ( self::length( $text ) > self::MAX_LENGTH ) {
			/* translators: %d: the longest note, in characters. */
			return new WP_Error( 'wpcpm_note_long', sprintf( __( 'A note can be at most %d characters long.', 'wpcredits-program-manager' ), self::MAX_LENGTH ) );
		}

		$author_id   = is_numeric( $author_id ) ? (int) $author_id : 0;
		$author_name = isset( $extra['author_name'] ) && is_string( $extra['author_name'] ) && self::is_utf8( $extra['author_name'] ) ? trim( $extra['author_name'] ) : '';

		if ( $author_id > 0 && ! get_userdata( $author_id ) ) {
			$author_id = 0;
		}

		if ( $author_id <= 0 && '' === $author_name ) {
			return new WP_Error( 'wpcpm_note_author', __( 'A note needs the name of whoever wrote it.', 'wpcredits-program-manager' ) );
		}

		$date = isset( $extra['date'] ) ? $extra['date'] : (string) current_time( 'mysql', true );

		if ( ! self::is_date( $date ) ) {
			return new WP_Error( 'wpcpm_note_date', __( 'The note\'s date is not a date.', 'wpcredits-program-manager' ) );
		}

		$source = isset( $extra['source'] ) && is_scalar( $extra['source'] ) ? trim( (string) $extra['source'] ) : '';

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'     => self::POST_TYPE,
					'post_status'   => self::UNFINISHED_STATUS,
					'post_title'    => $key,
					'post_author'   => 0,
					'post_date_gmt' => $date,
					'post_date'     => get_date_from_gmt( $date ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new WP_Error( 'wpcpm_note_write', __( 'The note could not be kept. Try again in a while.', 'wpcredits-program-manager' ) );
		}

		$post_id = (int) $post_id;

		// Slashed because core unslashes post meta on its way in, and a backslash in a note is a word. Every write is
		// checked before the commit (the outside review, O7): a refused one removes the unfinished draft and keeps
		// nothing, so no note is shown without its text, and the source marker, written last, after the text, is never
		// kept for a note whose text is not, so a later import brings it again.
		$metas = array(
			self::META_FOR    => $key,
			self::META_TEXT   => wp_slash( $text ),
			self::META_AUTHOR => max( 0, $author_id ),
		);

		if ( '' !== $author_name ) {
			$metas[ self::META_AUTHOR_NAME ] = wp_slash( $author_name );
		}

		if ( '' !== $source ) {
			$metas[ self::META_SOURCE ] = wp_slash( $source );
		}

		foreach ( $metas as $meta_key => $value ) {
			if ( false === update_post_meta( $post_id, $meta_key, $value ) ) {
				wp_delete_post( $post_id, true );

				return new WP_Error( 'wpcpm_note_write', __( 'The note could not be kept. Try again in a while.', 'wpcredits-program-manager' ) );
			}
		}

		// The commit: every meta is written, so the note may now be read. Its date is given again, as core leaves a
		// draft's date alone only while it is given.
		$committed = wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => self::POST_STATUS,
				'post_date_gmt' => $date,
				'post_date'     => get_date_from_gmt( $date ),
			),
			true
		);

		if ( is_wp_error( $committed ) || ! $committed ) {
			return new WP_Error( 'wpcpm_note_write', __( 'The note could not be kept. Try again in a while.', 'wpcredits-program-manager' ) );
		}

		return $post_id;
	}

	/**
	 * A school's notes, newest first; of two written at the same second, the later added first.
	 *
	 * @param string $key A record ID or a lead key.
	 * @return array[] Each `array( 'id' => int, 'text' => string, 'date' => 'Y-m-d H:i:s' UTC, 'author' => string )`,
	 *                 the author being the name the note was signed with, else its account's name, else ''.
	 */
	public static function for_key( $key ) {
		$key = self::clean_key( $key );

		if ( '' === $key ) {
			return array();
		}

		$ids = self::ids_of( $key, self::READ_STATUSES );

		if ( empty( $ids ) ) {
			return array();
		}

		update_meta_cache( 'post', $ids );

		$notes = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$notes[] = array(
				'id'     => (int) $post->ID,
				'text'   => (string) get_post_meta( $post->ID, self::META_TEXT, true ),
				'date'   => (string) $post->post_date_gmt,
				'author' => self::author_of( (int) $post->ID ),
			);
		}

		usort(
			$notes,
			static function ( $a, $b ) {
				$by_date = strcmp( $b['date'], $a['date'] );

				return 0 !== $by_date ? $by_date : $b['id'] - $a['id'];
			}
		);

		return $notes;
	}

	/**
	 * Whether a note with this source is kept, in any status: the import's check before it writes one.
	 *
	 * @param string $source A source as `add()` was given it.
	 * @return bool False for an empty source.
	 */
	public static function has_source( $source ) {
		$source = is_scalar( $source ) ? trim( (string) $source ) : '';

		if ( '' === $source ) {
			return false;
		}

		$found = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::FOUND_STATUSES,
				'meta_key'         => self::META_SOURCE,
				'meta_value'       => $source,
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
				'cache_results'    => false,
			)
		);

		return ! empty( $found );
	}

	/**
	 * The text of the note with this source, as it was kept: the import's check that a cell brought once has not
	 * changed shape since (Task 18 fix round 1).
	 *
	 * @param string $source A source as `add()` was given it.
	 * @return string|null The text, or null when no finished note has this source.
	 */
	public static function text_of_source( $source ) {
		$source = is_scalar( $source ) ? trim( (string) $source ) : '';

		if ( '' === $source ) {
			return null;
		}

		$found = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::FOUND_STATUSES,
				'meta_key'         => self::META_SOURCE,
				'meta_value'       => $source,
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
				'cache_results'    => false,
			)
		);

		return empty( $found ) ? null : (string) get_post_meta( (int) reset( $found ), self::META_TEXT, true );
	}

	/**
	 * Move every note of a key to another, in any status: a lead's notes to the record it became.
	 *
	 * Safe to repeat: it moves what is still under `$from`, so a promotion that stops after it and goes on moves only
	 * what is left (the outside review of release 2, O4). A note counts as moved when its key reads `$to` after the
	 * write, since `update_post_meta()` answers false for a value it already holds as well as for a write refused.
	 *
	 * @param string $from The key the notes are for now.
	 * @param string $to   The key they are for from now on.
	 * @return int How many notes could NOT be moved (0 when every one moved); 0 too when either key is of neither shape
	 *             or the two are one, where there is nothing to move.
	 */
	public static function rekey( $from, $to ) {
		$from = self::clean_key( $from );
		$to   = self::clean_key( $to );

		if ( '' === $from || '' === $to || $from === $to ) {
			return 0;
		}

		$left = 0;

		foreach ( self::ids_of( $from, self::FOUND_STATUSES ) as $id ) {
			if ( false === update_post_meta( $id, self::META_FOR, $to ) && (string) get_post_meta( $id, self::META_FOR, true ) !== $to ) {
				++$left;
			}
		}

		return $left;
	}

	/**
	 * How many notes a key has, the Trash's among them (the owner's wave of release 2, X3: a lead's contact erased keeps
	 * its notes, and the eraser says so).
	 *
	 * @param string $key A record ID or a lead key.
	 * @return int
	 */
	public static function count_for( $key ) {
		return count( self::ids_of( (string) $key, self::FOUND_STATUSES ) );
	}

	/**
	 * Move every note of a key to WordPress's Trash, which keeps each one's status to restore: a lead deleted takes its
	 * notes with it (the owner's wave of release 2, X4). In the Trash a note is read by no one, and `has_source()` still
	 * finds it, so an import does not bring it back.
	 *
	 * @param string $key A lead key.
	 * @return int How many notes went to the Trash.
	 */
	public static function trash_for( $key ) {
		$moved = 0;

		foreach ( self::ids_of( (string) $key, self::READ_STATUSES ) as $id ) {
			if ( wp_trash_post( $id ) ) {
				++$moved;
			}
		}

		return $moved;
	}

	/**
	 * Bring every note of a key back from WordPress's Trash, each to the status it had (the leads' `untrash_status()`
	 * answers it): a lead brought back from the Trash brings its notes (the outside review of release 2, O10).
	 *
	 * @param string $key A lead key.
	 * @return int How many notes came back.
	 */
	public static function untrash_for( $key ) {
		$back = 0;

		foreach ( self::ids_of( (string) $key, array( 'trash' ) ) as $id ) {
			if ( wp_untrash_post( $id ) ) {
				++$back;
			}
		}

		return $back;
	}

	/**
	 * Delete every note, in any status, with its meta. Uninstall only.
	 *
	 * @return int How many notes were deleted.
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

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * The posts of a key's notes, in the statuses given.
	 *
	 * @param string   $key      A key already cleaned.
	 * @param string[] $statuses Post statuses.
	 * @return int[]
	 */
	private static function ids_of( $key, array $statuses ) {
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => $statuses,
				'meta_key'         => self::META_FOR,
				'meta_value'       => $key,
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Who signed a note: the name it came with, else its account's name, else ''.
	 *
	 * @param int $post_id The note.
	 * @return string
	 */
	private static function author_of( $post_id ) {
		$name = (string) get_post_meta( $post_id, self::META_AUTHOR_NAME, true );

		if ( '' !== $name ) {
			return $name;
		}

		$user = get_userdata( (int) get_post_meta( $post_id, self::META_AUTHOR, true ) );

		return $user && isset( $user->display_name ) ? (string) $user->display_name : '';
	}

	/**
	 * A key as a caller handed it, trimmed, or '' when it is neither a record ID nor a lead key.
	 *
	 * @param mixed $key Candidate.
	 * @return string
	 */
	private static function clean_key( $key ) {
		if ( ! is_scalar( $key ) ) {
			return '';
		}

		$key = trim( (string) $key );

		return WPCPM_Airtable::is_record_id( $key ) || WPCPM_Institution_Talks::is_lead_key( $key ) ? $key : '';
	}

	/**
	 * Whether a value is a date and time as a note keeps one: `Y-m-d H:i:s`, and a real one.
	 *
	 * @param mixed $date Candidate.
	 * @return bool
	 */
	private static function is_date( $date ) {
		if ( ! is_string( $date ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $date ) ) {
			return false;
		}

		$parsed = DateTime::createFromFormat( '!Y-m-d H:i:s', $date, new DateTimeZone( 'UTC' ) );

		return $parsed instanceof DateTime && $parsed->format( 'Y-m-d H:i:s' ) === $date;
	}

	/**
	 * Whether a string is well-formed UTF-8.
	 *
	 * @param string $text Candidate.
	 * @return bool
	 */
	private static function is_utf8( $text ) {
		return self::multibyte() ? mb_check_encoding( $text, 'UTF-8' ) : 1 === preg_match( '//u', $text );
	}

	/**
	 * The length of well-formed UTF-8, in characters.
	 *
	 * @param string $text Candidate, already found well formed.
	 * @return int
	 */
	private static function length( $text ) {
		return self::multibyte() ? mb_strlen( $text, 'UTF-8' ) : (int) preg_match_all( '/./su', $text );
	}

	/**
	 * Whether mbstring is the way: the one guard the length and the UTF-8 test share.
	 *
	 * @return bool
	 */
	private static function multibyte() {
		if ( null === self::$multibyte ) {
			self::$multibyte = function_exists( 'mb_check_encoding' ) && function_exists( 'mb_strlen' );
		}

		return self::$multibyte;
	}
}
