<?php
/**
 * Institutions module - test institutions.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which institutions are test institutions, decided in one place.
 *
 * **The rule is the name.** An institution whose name starts with the word TEST in capitals is a
 * test institution, by the rule the site already applies to sponsors
 * (`WPCPM_Sponsors_Index::is_test_name()`: "TEST - Example" and "TEST" are tests, "Test College",
 * "Contest Labs" and "TESTAMENT Academy" are not). A test institution is listed wherever a real one
 * is, with a Test mark, and is left out of every number the site draws, mails or answers. This class
 * is the one helper every list and every count asks, so the two never disagree.
 *
 * **A record ID or a name.** The site's own records of an institution (its agreements, requests,
 * semester reports and memberships) carry only its Airtable record ID, so `is_test()` takes either.
 * While the institutions index holds a record ID, the name it holds there decides, read when it is
 * needed, so an institution renamed in Airtable changes what it is at the next institutions sync. A
 * name the index does not hold is judged as given.
 *
 * **The remembered list.** Testing ends when a person removes the test institution's row from
 * Airtable, and the next sync then drops it from the index; a check that read only the index would
 * find no name and count its agreements, requests and reports again. So the sync keeps a short list
 * of the record IDs it has found to be tests (the option `OPTION`, never autoloaded), and an ID on
 * the list that the index no longer holds stays a test. A name in the index always wins over the
 * list: an institution renamed away from TEST is real again.
 *
 * **Why the sync writes it.** `remember()` is called in one place, `WPCPM_Institutions_Sync::
 * phase_records()`, right before `WPCPM_Institutions_Index::write()`, with the index about to be
 * replaced and the rows about to be written. That write is the only way an institution leaves the
 * index, so a row removed from Airtable is remembered at the very write that drops it, one the
 * approval inserted since the last sync included. It runs in the sync's tick, so no public read, no
 * ability and no dashboard draw ever writes an option. Nothing else writes the list; the uninstaller
 * deletes it with the index.
 *
 * Reads go through `WPCPM_Institutions_Index::row()` and `rows()` only, so the class loads wherever
 * the index is stood in at that contract.
 */
final class WPCPM_Test_Institutions {

	/** The option holding the record IDs found to be tests, sorted: written by the institutions sync. */
	const OPTION = 'wpcpm_test_institutions';

	/** The classes of the mark in wp-admin: the pipeline's own small flag, with a hook of its own. */
	const MARK_ADMIN = 'wpcpm-inst-mark wpcpm-inst-mark--test';

	/** The classes of the mark on the dashboards, which never load the admin stylesheet. */
	const MARK_DASHBOARD = 'wpcpm-badge wpcpm-badge--test';

	/**
	 * Whether an institution is a test institution.
	 *
	 * @param mixed $institution An Airtable record ID or an institution's name. Anything that is not
	 *                           a string is never a test.
	 * @return bool
	 */
	public static function is_test( $institution ) {
		if ( ! is_string( $institution ) ) {
			return false;
		}

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return WPCPM_Sponsors_Index::is_test_name( $institution );
		}

		$row = WPCPM_Institutions_Index::row( $institution );

		if ( null !== $row ) {
			return self::row_is_test( $row );
		}

		return in_array( trim( $institution ), self::remembered(), true );
	}

	/**
	 * The record IDs the sync has remembered as tests: the option's values that are record IDs,
	 * trimmed, once each, sorted. Empty for an option that is absent or not a list.
	 *
	 * @return string[]
	 */
	public static function remembered() {
		$stored = get_option( self::OPTION, array() );
		$ids    = array();

		if ( ! is_array( $stored ) ) {
			return $ids;
		}

		foreach ( $stored as $value ) {
			if ( is_string( $value ) && WPCPM_Mentors_Sync::is_record_id( $value ) ) {
				$ids[] = trim( $value );
			}
		}

		$ids = array_values( array_unique( $ids ) );

		sort( $ids, SORT_STRING );

		return $ids;
	}

	/**
	 * The record IDs that are tests now, sorted: the index's rows whose name is a test's, and the
	 * remembered IDs the index does not hold.
	 *
	 * @return string[]
	 */
	public static function test_ids() {
		$rows = WPCPM_Institutions_Index::rows();
		$ids  = array();

		foreach ( $rows as $record_id => $row ) {
			if ( is_array( $row ) && self::row_is_test( $row ) ) {
				$ids[] = (string) $record_id;
			}
		}

		foreach ( self::remembered() as $record_id ) {
			if ( ! isset( $rows[ $record_id ] ) || ! is_array( $rows[ $record_id ] ) ) {
				$ids[] = $record_id;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		sort( $ids, SORT_STRING );

		return $ids;
	}

	/**
	 * Bring the remembered list up to the index about to be written, and answer it.
	 *
	 * For each record ID in either array, the name it is about to be written with, or else the name
	 * it was held with, decides whether it is on the list; an ID in neither keeps its place. So a row
	 * dropped from the index is remembered by the name it had, a row renamed away from TEST leaves the
	 * list, and a row renamed to TEST joins it, each at the write that makes it so in the index. The
	 * option is written only when the list changes.
	 *
	 * @param array $before The index about to be replaced, rows keyed by record ID.
	 * @param array $after  The rows about to be written, keyed by record ID.
	 * @return string[] The list, sorted.
	 */
	public static function remember( array $before, array $after ) {
		$current = self::remembered();
		$list    = array_flip( $current );

		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $key ) {
			$record_id = trim( (string) $key );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			if ( isset( $after[ $key ] ) && is_array( $after[ $key ] ) ) {
				$row = $after[ $key ];
			} elseif ( isset( $before[ $key ] ) && is_array( $before[ $key ] ) ) {
				$row = $before[ $key ];
			} else {
				continue;
			}

			if ( self::row_is_test( $row ) ) {
				$list[ $record_id ] = true;
			} else {
				unset( $list[ $record_id ] );
			}
		}

		$list = array_map( 'strval', array_keys( $list ) );

		sort( $list, SORT_STRING );

		if ( $list !== $current ) {
			update_option( self::OPTION, $list, false );
		}

		return $list;
	}

	/**
	 * A map keyed by institution record ID, less the keys that are tests; keys and order kept.
	 *
	 * @param array $by_record Anything keyed by an institution's record ID.
	 * @return array
	 */
	public static function real_keys( array $by_record ) {
		$real  = array();
		$tests = self::test_set( $by_record );

		foreach ( $by_record as $key => $value ) {
			if ( ! self::judged( (string) $key, $tests ) ) {
				$real[ $key ] = $value;
			}
		}

		return $real;
	}

	/**
	 * How many of some rows are not a test institution's.
	 *
	 * @param array  $rows  Rows, each an array or an object (a `WP_Post`, say).
	 * @param string $field The row's field holding an institution's record ID or name. A row without
	 *                      it is real.
	 * @return int
	 */
	public static function count_real_rows( array $rows, $field ) {
		$count = 0;
		$tests = self::test_set( $rows );

		foreach ( $rows as $row ) {
			$value = null;

			if ( is_array( $row ) && isset( $row[ $field ] ) ) {
				$value = $row[ $field ];
			} elseif ( is_object( $row ) && isset( $row->$field ) ) {
				$value = $row->$field;
			}

			if ( ! self::judged( $value, $tests ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * The posts whose institution is not a test one, in their order.
	 *
	 * @param int[]  $post_ids Post IDs.
	 * @param string $meta_key The meta key holding the institution's record ID.
	 * @return int[]
	 */
	public static function real_post_ids( array $post_ids, $meta_key ) {
		$post_ids = array_map( 'intval', $post_ids );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$tests = self::test_set( $post_ids );

		// No test institution: nothing to leave out, and no meta to read.
		if ( empty( $tests ) ) {
			return $post_ids;
		}

		// One read for all the meta, where core offers it, rather than one query per post.
		if ( function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $post_ids );
		}

		$real = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! self::judged( get_post_meta( $post_id, $meta_key, true ), $tests ) ) {
				$real[] = $post_id;
			}
		}

		return $real;
	}

	/**
	 * The applications whose name is not a test's, in their order.
	 *
	 * An application carries the institution's name as given on the form, in its title, and no record
	 * ID yet. The titles are read in one query that touches no meta: the Administrator Dashboard's
	 * totals must not load every applicant's answers.
	 *
	 * @param int[] $post_ids Application post IDs.
	 * @return int[]
	 */
	public static function real_application_ids( array $post_ids ) {
		global $wpdb;

		$post_ids = array_values( array_filter( array_map( 'intval', $post_ids ) ) );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$holders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		// The statement's only variables are the posts table's name, `{$wpdb->posts}`, and a run of %d
		// placeholders, prepared with the IDs and the post type.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND ID IN ( $holders )", array_merge( array( WPCPM_Institution_Application::POST_TYPE ), $post_ids ) ) );
		$titles = array();

		foreach ( $found as $post ) {
			$titles[ (int) $post->ID ] = (string) $post->post_title;
		}

		$real = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! isset( $titles[ $post_id ] ) || ! WPCPM_Sponsors_Index::is_test_name( $titles[ $post_id ] ) ) {
				$real[] = $post_id;
			}
		}

		return $real;
	}

	/**
	 * How many institution accounts do not act for a test institution.
	 *
	 * @param array $users Institution accounts (`WP_User` objects or IDs).
	 * @return int
	 */
	public static function count_real_accounts( array $users ) {
		$count = 0;
		$tests = self::test_set( $users );

		foreach ( $users as $user ) {
			if ( ! self::judged( WPCPM_Institution_Members::institution_of( $user ), $tests ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * The mark beside a test institution's name, with its leading space, escaped; empty for a real one.
	 *
	 * For markup. Where none can go (an option, a combobox entry, a mail line) use `label()`.
	 *
	 * @param mixed  $institution An Airtable record ID or an institution's name.
	 * @param string $classes     `MARK_ADMIN` in wp-admin, `MARK_DASHBOARD` on the dashboards.
	 * @return string
	 */
	public static function mark( $institution, $classes = self::MARK_ADMIN ) {
		return self::is_test( $institution ) ? self::badge( $classes ) : '';
	}

	/**
	 * The mark itself, for a caller that has already judged the institution (a row that carries its
	 * own `test` flag): with its leading space, escaped.
	 *
	 * @param string $classes `MARK_ADMIN` in wp-admin, `MARK_DASHBOARD` on the dashboards.
	 * @return string
	 */
	public static function badge( $classes = self::MARK_ADMIN ) {
		return sprintf(
			' <span class="%1$s" title="%2$s">%3$s</span>',
			esc_attr( $classes ),
			esc_attr( __( 'A test institution, whose name starts with TEST: listed here and left out of every count.', 'wpcredits-program-manager' ) ),
			esc_html( _x( 'Test', 'the mark beside a test institution', 'wpcredits-program-manager' ) )
		);
	}

	/**
	 * An institution's name for a place that takes text only: a test institution's reads "(Test)".
	 *
	 * @param string $name        The name to show.
	 * @param mixed  $institution An Airtable record ID or an institution's name.
	 * @return string The name as given for a real institution.
	 */
	public static function label( $name, $institution ) {
		if ( ! self::is_test( $institution ) ) {
			return $name;
		}

		return self::labeled( $name );
	}

	/**
	 * A name in its "(Test)" form, whatever the name: the counterpart of `badge()`, for a caller that
	 * has already judged the item a test one (a row's own `test` flag, say). `label()` judges first.
	 *
	 * @param string $name The name to show.
	 * @return string
	 */
	public static function labeled( $name ) {
		/* translators: %s: an institution's name. */
		return sprintf( __( '%s (Test)', 'wpcredits-program-manager' ), $name );
	}

	/**
	 * The test record IDs as a set, read once for a bulk filter: the index and the list are read one
	 * time, not once per item (the index is one option, unserialized on each read).
	 *
	 * @param array $items What the filter is about to judge; none means nothing is read.
	 * @return array Record ID to true; empty when there are no items.
	 */
	private static function test_set( array $items ) {
		return empty( $items ) ? array() : array_fill_keys( self::test_ids(), true );
	}

	/**
	 * `is_test()` against a set read once: a record ID by the set, a name by the rule.
	 *
	 * @param mixed $value A record ID or a name; anything that is not a string is never a test.
	 * @param array $tests The set from `test_set()`.
	 * @return bool
	 */
	private static function judged( $value, array $tests ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		if ( WPCPM_Mentors_Sync::is_record_id( $value ) ) {
			return isset( $tests[ trim( $value ) ] );
		}

		return WPCPM_Sponsors_Index::is_test_name( $value );
	}

	/**
	 * Whether an index row's name is a test's.
	 *
	 * @param array $row A row as the index holds it.
	 * @return bool
	 */
	private static function row_is_test( array $row ) {
		return isset( $row['name'] ) && is_string( $row['name'] ) && WPCPM_Sponsors_Index::is_test_name( $row['name'] );
	}
}
