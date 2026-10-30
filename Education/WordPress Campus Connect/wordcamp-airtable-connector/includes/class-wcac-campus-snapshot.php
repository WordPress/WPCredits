<?php
/**
 * The Campus Connect snapshot: what this plugin hands the dashboard in place
 * of a round trip through Airtable.
 *
 * Both halves of the Campus Connect reporting spine exist upstream. The event
 * rows come from wordcamp-reports/v1/campus-connect-details, which needs the
 * campus_connect_viewer subrole, and the coordinates come from wp/v2/wordcamps,
 * which needs nothing at all. Airtable was in the middle only because this
 * plugin had no way to hand the rows to a reader on the same site. That is what
 * this class is.
 *
 * Nothing here talks to Airtable, and nothing here holds a credential. The
 * reader asks through a filter, so a dashboard plugin need not know this class
 * exists, need not load in any particular order, and keeps working unchanged
 * if this plugin is deactivated - it simply finds no provider and says so.
 *
 * @package WordCamp_Airtable_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Deliberately not exit(): the refusal rules below are pure and are
	// exercised by the bare-PHP test suite, which has no WordPress.
	if ( ! defined( 'WCAC_SNAPSHOT_TESTING' ) ) {
		define( 'WCAC_SNAPSHOT_TESTING', true );
	}
}

class WCAC_Campus_Snapshot {

	/**
	 * Option holding the latest accepted snapshot.
	 *
	 * Not autoloaded: it carries every Campus Connect event and the whole
	 * coordinate map, which is far too much to put on every page load of a
	 * site that reads it once a day.
	 */
	const OPTION = 'wcac_campus_snapshot';

	/**
	 * Filter a reader applies to ask for the snapshot.
	 *
	 * The contract, which the dashboard depends on and which must not change
	 * without changing it there too:
	 *
	 *   apply_filters( 'wcac_campus_connect_export', null )
	 *
	 * returns either null, meaning no provider answered, or an array of
	 * array( 'read_at' => ISO-8601 string, 'rows' => list, 'coords' => map ).
	 */
	const FILTER = 'wcac_campus_connect_export';

	/**
	 * Fewest rows that can be a real reading of the report.
	 *
	 * The report carries 177 rows today and has never been below 142. A
	 * handful of rows is a broken query, a maintenance page or a changed
	 * report, and storing it would quietly blank the dashboard.
	 */
	const MIN_ROWS = 50;

	/**
	 * How far a count may fall against the stored snapshot before the new one
	 * is refused.
	 *
	 * A halving cannot be legitimate: events are not unpublished in bulk. A
	 * smaller dip can be, and refusing one of those would serve staler data to
	 * avoid a smaller inaccuracy, which is the worse trade.
	 */
	const COLLAPSE_RATIO = 0.5;

	/**
	 * Register the provider.
	 *
	 * Called from the plugin bootstrap, never at file scope, so that requiring
	 * this file for its rules alone does not need WordPress.
	 */
	public static function boot() {
		add_filter( self::FILTER, array( __CLASS__, 'provide' ) );
	}

	/**
	 * Answer a reader's request for the snapshot.
	 *
	 * @param mixed $value Whatever an earlier provider returned.
	 * @return mixed
	 */
	public static function provide( $value ) {
		$stored = self::get();

		return empty( $stored['rows'] ) ? $value : $stored;
	}

	/**
	 * The stored snapshot, or an empty array when there is none.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Store a snapshot, if it is plausible.
	 *
	 * A refused snapshot leaves the stored one in place: the dashboard then
	 * shows data that is a day old and says so, rather than showing nothing.
	 *
	 * @param array $snapshot From WCAC_Mapper::campus_snapshot().
	 * @return true|WP_Error
	 */
	public static function store( array $snapshot ) {
		$reason = self::refuse_reason( $snapshot, self::get() );

		if ( '' !== $reason ) {
			return new WP_Error( 'wcac_snapshot_refused', $reason );
		}

		update_option( self::OPTION, $snapshot, false );

		return true;
	}

	/**
	 * Why this snapshot must not replace the stored one, or '' to accept it.
	 *
	 * Pure, so the rules that decide whether the dashboard gets new figures
	 * are testable without WordPress, Central or Airtable.
	 *
	 * @param array $next     Candidate snapshot.
	 * @param array $previous Currently stored snapshot, if any.
	 * @return string Empty string to accept.
	 */
	public static function refuse_reason( array $next, array $previous ) {
		$rows   = isset( $next['rows'] ) && is_array( $next['rows'] ) ? $next['rows'] : null;
		$coords = isset( $next['coords'] ) && is_array( $next['coords'] ) ? $next['coords'] : null;

		if ( null === $rows || null === $coords ) {
			return 'The snapshot is malformed: it must carry a rows list and a coords map.';
		}

		if ( count( $rows ) < self::MIN_ROWS ) {
			return sprintf(
				'The snapshot carries only %d event row(s), against a floor of %d. Treating this as a broken read of the report rather than a shrunken programme.',
				count( $rows ),
				self::MIN_ROWS
			);
		}

		if ( empty( $coords ) ) {
			return 'The snapshot carries no coordinates. The public WordCamps route answered, but nothing in it matched a Campus Connect event, which points at the search or the name match rather than at the events.';
		}

		$prev_rows   = isset( $previous['rows'] ) && is_array( $previous['rows'] ) ? count( $previous['rows'] ) : 0;
		$prev_coords = isset( $previous['coords'] ) && is_array( $previous['coords'] ) ? count( $previous['coords'] ) : 0;

		if ( $prev_rows >= self::MIN_ROWS && count( $rows ) < $prev_rows * self::COLLAPSE_RATIO ) {
			return sprintf(
				'The snapshot carries %d event row(s) where the stored one has %d. A fall that size is not something the programme does, so the stored snapshot is kept.',
				count( $rows ),
				$prev_rows
			);
		}

		if ( $prev_coords > 0 && count( $coords ) < $prev_coords * self::COLLAPSE_RATIO ) {
			return sprintf(
				'The snapshot carries %d coordinate(s) where the stored one has %d. That would empty half the map, so the stored snapshot is kept.',
				count( $coords ),
				$prev_coords
			);
		}

		return '';
	}
}
