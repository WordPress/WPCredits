<?php
/**
 * Student Duplicate Finder: the copies of deleted rows, and the log.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per row the finder deletes: a sealed copy of the row for thirty days, then the
 * log entry that says it went.
 *
 * **No copy, no delete** (spec decision 3.7). The copy is written before the delete is asked for,
 * in the state `pending`, and becomes `deleted` only for a row Airtable confirms; a copy still
 * pending the next day is settled by reading its record, so a lost response never leaves a deleted
 * row without its copy, nor a kept row with a log entry saying it went.
 *
 * The cells are sealed with `WPCPM_Secret` (AES-256-GCM), because they are a student's name,
 * address, grades and answers. After thirty days the daily job erases them and keeps the post:
 * the table, the record ID, the created date, the status, who deleted it and when, and never a
 * name or an address, which is the permanent log the owner asked for (spec section 8).
 *
 * The post type follows the plugin's other private records (`wpcpm_mentor_note`): not public, no
 * screen, not in REST, and a capability type nothing is granted, so only this class's own reads
 * and writes reach it.
 */
final class WPCPM_Duplicate_Vault {

	/** Fourteen characters: WordPress silently refuses a post type name longer than twenty. */
	const POST_TYPE = 'wpcpm_dup_copy';

	/** The daily job that erases old copies and settles unconfirmed ones. */
	const CRON_PURGE = 'wpcpm_duplicates_purge';

	/** How long a copy's cells are kept (spec 13: a constant until the owner asks for a setting). */
	const KEEP_DAYS = 30;

	/**
	 * How old a pending copy must be before the daily job settles it.
	 *
	 * A delete in progress holds its copies pending for the seconds its batches take, and a job
	 * that settled one mid-run would read a row about to go as a row that stayed.
	 */
	const SETTLE_AFTER = 3600;

	const META_TABLE   = '_wpcpm_dup_table';
	const META_RECORD  = '_wpcpm_dup_record';
	const META_CREATED = '_wpcpm_dup_created';
	const META_STATUS  = '_wpcpm_dup_status';
	const META_READ    = '_wpcpm_dup_read';
	const META_STATE   = '_wpcpm_dup_state';
	const META_EXPIRES = '_wpcpm_dup_expires';

	const STATE_PENDING = 'pending';
	const STATE_DELETED = 'deleted';
	const STATE_ERASED  = 'erased';

	/**
	 * Register the post type. Hooked on `init` by the tool.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Deleted duplicate rows', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Deleted duplicate row', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'capability_type'     => self::POST_TYPE,
				'map_meta_cap'        => false,
				'supports'            => array( 'author' ),
			)
		);
	}

	/**
	 * Keep a sealed copy of one row, before it is deleted.
	 *
	 * @param string $table   `students`, `reports` or `feedback`.
	 * @param array  $record  The live record, as Airtable returned it just now.
	 * @param int    $actor   The manager deleting it.
	 * @param int    $read_at When the scan the selection came from read the base.
	 * @return int|WP_Error The copy's post ID.
	 */
	public static function keep( $table, array $record, $actor, $read_at = 0 ) {
		if ( ! WPCPM_Secret::can_encrypt() ) {
			return new WP_Error( 'wpcpm_duplicates_no_seal', __( 'This site cannot encrypt, so it cannot keep the copy a delete needs. Nothing was deleted.', 'wpcredits-program-manager' ) );
		}

		$id = isset( $record['id'] ) ? trim( (string) $record['id'] ) : '';

		if ( ! in_array( $table, WPCPM_Duplicate_Rules::TABLES, true ) || ! WPCPM_Airtable::is_record_id( $id ) ) {
			return new WP_Error( 'wpcpm_duplicates_bad_copy', __( 'That row cannot be copied: its table or its record ID is not one this site knows.', 'wpcredits-program-manager' ) );
		}

		$created = isset( $record['createdTime'] ) ? (string) $record['createdTime'] : '';
		$sealed  = WPCPM_Secret::seal_for_option(
			(string) wp_json_encode(
				array(
					'id'          => $id,
					'createdTime' => $created,
					'fields'      => isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array(),
				)
			)
		);

		if ( is_wp_error( $sealed ) ) {
			return $sealed;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => $table . ' ' . $id,
				'post_content' => $sealed,
				'post_author'  => (int) $actor,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$reduced = WPCPM_Duplicate_Rules::reduce( $table, $record, array() );

		update_post_meta( $post_id, self::META_TABLE, $table );
		update_post_meta( $post_id, self::META_RECORD, $id );
		update_post_meta( $post_id, self::META_CREATED, substr( $created, 0, 10 ) );
		update_post_meta( $post_id, self::META_STATUS, $reduced['status'] );
		update_post_meta( $post_id, self::META_READ, (int) $read_at );
		update_post_meta( $post_id, self::META_STATE, self::STATE_PENDING );
		update_post_meta( $post_id, self::META_EXPIRES, time() + self::KEEP_DAYS * DAY_IN_SECONDS );

		return (int) $post_id;
	}

	/**
	 * Mark copies whose rows Airtable confirmed deleted. From here each is a log entry.
	 *
	 * @param int[] $copy_ids Post IDs.
	 */
	public static function confirm( array $copy_ids ) {
		foreach ( array_map( 'intval', $copy_ids ) as $copy_id ) {
			if ( self::is_copy( $copy_id ) ) {
				update_post_meta( $copy_id, self::META_STATE, self::STATE_DELETED );
			}
		}
	}

	/**
	 * Remove a copy whose row was not deleted after all: refused, or still in Airtable.
	 *
	 * @param int $copy_id Post ID.
	 */
	public static function discard( $copy_id ) {
		if ( self::is_copy( $copy_id ) ) {
			wp_delete_post( (int) $copy_id, true );
		}
	}

	/**
	 * The log, newest first: every copy that still has its cells (`pending` or `deleted`), plus at
	 * most `$limit` copies whose cells are already `erased`.
	 *
	 * While a copy has its cells, View copy must reach it for the whole of its thirty days (spec
	 * 8.3), however many rows have been deleted since; only the `erased` entries, which View copy
	 * can no longer open, are capped.
	 *
	 * @param int $limit How many `erased` copies to list.
	 * @return array[] Each `copy`, `table`, `record`, `created`, `status`, `state`, `when` (unix),
	 *                 `by` (user ID) and `expires` (unix).
	 */
	public static function entries( $limit = 50 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);

		$limit   = max( 1, (int) $limit );
		$erased  = 0;
		$entries = array();

		foreach ( (array) $posts as $post ) {
			$state = (string) get_post_meta( $post->ID, self::META_STATE, true );

			if ( self::STATE_ERASED === $state ) {
				if ( $erased >= $limit ) {
					continue;
				}

				++$erased;
			}

			$entries[] = array(
				'copy'    => (int) $post->ID,
				'table'   => (string) get_post_meta( $post->ID, self::META_TABLE, true ),
				'record'  => (string) get_post_meta( $post->ID, self::META_RECORD, true ),
				'created' => (string) get_post_meta( $post->ID, self::META_CREATED, true ),
				'status'  => (string) get_post_meta( $post->ID, self::META_STATUS, true ),
				'state'   => $state,
				'when'    => (int) strtotime( (string) $post->post_date_gmt . ' UTC' ),
				'by'      => (int) $post->post_author,
				'expires' => (int) get_post_meta( $post->ID, self::META_EXPIRES, true ),
			);
		}

		return $entries;
	}

	/**
	 * A copy's cells, unsealed, for View copy.
	 *
	 * @param int $copy_id Post ID.
	 * @return array|WP_Error The record: `id`, `createdTime` and `fields`.
	 */
	public static function view( $copy_id ) {
		if ( ! self::is_copy( $copy_id ) ) {
			return new WP_Error( 'wpcpm_duplicates_no_copy', __( 'There is no such copy.', 'wpcredits-program-manager' ) );
		}

		$post = get_post( (int) $copy_id );

		if ( self::STATE_ERASED === get_post_meta( (int) $copy_id, self::META_STATE, true ) || '' === (string) $post->post_content ) {
			return new WP_Error( 'wpcpm_duplicates_erased', __( 'This copy has been erased: copies are kept for 30 days.', 'wpcredits-program-manager' ) );
		}

		$plain = WPCPM_Secret::unseal_from_option( (string) $post->post_content );

		if ( is_wp_error( $plain ) ) {
			return $plain;
		}

		$record = json_decode( (string) $plain, true );

		return is_array( $record ) ? $record : new WP_Error( 'wpcpm_duplicates_unreadable', __( 'This copy could not be read.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The daily job: erase what is past its thirty days, and settle what is still pending.
	 *
	 * @param callable|null $exists `function( $table, $record ) : bool|null` - whether the row is
	 *                              still in Airtable, or null when that could not be learned. The
	 *                              default asks Airtable; the suites hand in their own.
	 * @param int|null      $now    Unix time, for the suites.
	 * @return array Counts: `erased`, `settled_deleted`, `settled_kept`, `unsettled`.
	 */
	public static function purge( $exists = null, $now = null ) {
		$now    = null === $now ? time() : (int) $now;
		$exists = is_callable( $exists ) ? $exists : array( __CLASS__, 'still_in_airtable' );
		$counts = array(
			'erased'          => 0,
			'settled_deleted' => 0,
			'settled_kept'    => 0,
			'unsettled'       => 0,
		);

		foreach ( self::all_ids() as $copy_id ) {
			$state = (string) get_post_meta( $copy_id, self::META_STATE, true );

			if ( self::STATE_PENDING === $state ) {
				$post = get_post( $copy_id );

				if ( ! $post || ( $now - (int) strtotime( (string) $post->post_date_gmt . ' UTC' ) ) < self::SETTLE_AFTER ) {
					continue;
				}

				$there = call_user_func( $exists, (string) get_post_meta( $copy_id, self::META_TABLE, true ), (string) get_post_meta( $copy_id, self::META_RECORD, true ) );

				if ( true === $there ) {
					self::discard( $copy_id );
					++$counts['settled_kept'];
					continue;
				}

				if ( false === $there ) {
					update_post_meta( $copy_id, self::META_STATE, self::STATE_DELETED );
					$state = self::STATE_DELETED;
					++$counts['settled_deleted'];
				} else {
					++$counts['unsettled'];
					continue;
				}
			}

			if ( self::STATE_DELETED === $state && (int) get_post_meta( $copy_id, self::META_EXPIRES, true ) <= $now ) {
				wp_update_post(
					array(
						'ID'           => $copy_id,
						'post_content' => '',
					)
				);
				update_post_meta( $copy_id, self::META_STATE, self::STATE_ERASED );
				++$counts['erased'];
			}
		}

		return $counts;
	}

	/**
	 * Put the daily job on the clock whenever it is missing, as the other retention runs do.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_PURGE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_PURGE );
		}
	}

	/**
	 * Deactivation: the job off the clock. The copies stay, sealed.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_PURGE );
	}

	/**
	 * Uninstall: every copy, sealed cells and log together, and the job.
	 */
	public static function delete_all() {
		foreach ( self::all_ids() as $copy_id ) {
			wp_delete_post( $copy_id, true );
		}

		wp_clear_scheduled_hook( self::CRON_PURGE );
	}

	/**
	 * Whether a row is still in Airtable: true, false for a 404, null when Airtable would not say.
	 *
	 * @param string $table  `students`, `reports` or `feedback`.
	 * @param string $record Record ID.
	 * @return bool|null
	 */
	public static function still_in_airtable( $table, $record ) {
		$settings = WPCPM_Settings::get();
		$key      = isset( WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] ) ? WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] : '';
		$source   = ( '' !== $key && isset( $settings[ $key ] ) ) ? (string) $settings[ $key ] : '';

		if ( '' === $source ) {
			return null;
		}

		$answer = ( new WPCPM_Airtable( $settings ) )->get_record( $source, (string) $record );

		if ( ! is_wp_error( $answer ) ) {
			return true;
		}

		$data = (array) $answer->get_error_data();

		return ( isset( $data['status'] ) && 404 === (int) $data['status'] ) ? false : null;
	}

	/**
	 * Whether a post ID is one of this class's copies.
	 *
	 * @param int $copy_id Post ID.
	 * @return bool
	 */
	private static function is_copy( $copy_id ) {
		$post = get_post( (int) $copy_id );

		return $post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * Every copy's post ID.
	 *
	 * @return int[]
	 */
	private static function all_ids() {
		return array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'        => self::POST_TYPE,
					'post_status'      => 'any',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			)
		);
	}
}
