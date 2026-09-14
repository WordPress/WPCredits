<?php
/**
 * What changed between two copies of a track.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Two definitions in, a question-level account of the difference out.
 *
 * No WordPress and no Airtable in it: History (`WPCPM_Track_History_Screen`) reads the copies and
 * prints the answer, this class compares them, and its suite needs neither (the design's decision
 * 28, and the shape `WPCPM_Track_Questions` and `WPCPM_Track_Columns` set).
 *
 * A question is keyed by its column name, verbatim (the design's 4.2), so the keys of the two
 * `questions` maps are what is compared, never trimmed.
 */
final class WPCPM_Track_Diff {

	/**
	 * The track's own properties, compared one by one.
	 *
	 * `questions` is compared question by question below, and `schema_version` is the format's,
	 * not the track's.
	 *
	 * @var string[]
	 */
	const TRACK = array( 'status', 'key', 'label', 'course_url', 'learn_course_id', 'hours_target', 'hue' );

	/**
	 * Compare two definitions.
	 *
	 * @param array $before The older copy.
	 * @param array $after  The newer copy.
	 * @return array `track` (the track properties that changed), `added` and `removed` (columns),
	 *               `moved` (columns present in both whose order changed, the fewest that account
	 *               for it), `changed` (column => the properties that changed), and `same`, true
	 *               when nothing did.
	 */
	public static function between( array $before, array $after ) {
		$track = array();

		foreach ( self::TRACK as $property ) {
			if ( self::flat( isset( $before[ $property ] ) ? $before[ $property ] : null ) !== self::flat( isset( $after[ $property ] ) ? $after[ $property ] : null ) ) {
				$track[] = $property;
			}
		}

		$old = self::questions( $before );
		$new = self::questions( $after );

		$added   = array_values( array_diff( array_keys( $new ), array_keys( $old ) ) );
		$removed = array_values( array_diff( array_keys( $old ), array_keys( $new ) ) );
		$common  = array_values( array_intersect( array_keys( $new ), array_keys( $old ) ) );
		$changed = array();

		foreach ( $common as $column ) {
			$properties = self::properties( $old[ $column ], $new[ $column ] );

			if ( array() !== $properties ) {
				$changed[ $column ] = $properties;
			}
		}

		// The same columns in each copy's own order: `$common` runs in the newer copy's, and the
		// older copy's keys intersected with it run in the older copy's.
		$moved = self::moved( array_values( array_intersect( array_keys( $old ), $common ) ), $common );

		return array(
			'track'   => $track,
			'added'   => $added,
			'removed' => $removed,
			'moved'   => $moved,
			'changed' => $changed,
			'same'    => array() === $track && array() === $added && array() === $removed && array() === $moved && array() === $changed,
		);
	}

	/**
	 * The questions of a definition, as a map keyed by column name.
	 *
	 * @param array $definition The definition.
	 * @return array
	 */
	private static function questions( array $definition ) {
		$questions = isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? $definition['questions'] : array();
		$map       = array();

		foreach ( $questions as $column => $spec ) {
			$map[ (string) $column ] = is_array( $spec ) ? $spec : array();
		}

		return $map;
	}

	/**
	 * The properties of one question that differ between two copies of it.
	 *
	 * A property present on one side and absent on the other counts, so a help line removed is
	 * reported as `help` changed. Values are compared through `flat()`, so a stored 100 and a
	 * posted "100" are the same length limit.
	 *
	 * @param array $older The question before.
	 * @param array $newer The question after.
	 * @return string[] Property names, in the order the newer copy holds them, then the removed ones.
	 */
	private static function properties( array $older, array $newer ) {
		$changed = array();

		foreach ( array_unique( array_merge( array_keys( $newer ), array_keys( $older ) ) ) as $property ) {
			$was = array_key_exists( $property, $older ) ? self::flat( $older[ $property ] ) : null;
			$is  = array_key_exists( $property, $newer ) ? self::flat( $newer[ $property ] ) : null;

			if ( $was !== $is ) {
				$changed[] = (string) $property;
			}
		}

		return $changed;
	}

	/**
	 * The columns whose order changed: the common columns not in the longest common subsequence
	 * of the two orders, so a question dragged past ten others is reported once, not eleven times
	 * (the design's decision 28).
	 *
	 * @param string[] $older The common columns in the older copy's order.
	 * @param string[] $newer The same columns in the newer copy's order.
	 * @return string[] The movers, in the newer copy's order.
	 */
	private static function moved( array $older, array $newer ) {
		$n = count( $older );
		$m = count( $newer );

		if ( $n < 2 || $older === $newer ) {
			return array();
		}

		$length = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );

		for ( $i = $n - 1; $i >= 0; --$i ) {
			for ( $j = $m - 1; $j >= 0; --$j ) {
				$length[ $i ][ $j ] = $older[ $i ] === $newer[ $j ]
					? $length[ $i + 1 ][ $j + 1 ] + 1
					: max( $length[ $i + 1 ][ $j ], $length[ $i ][ $j + 1 ] );
			}
		}

		$kept = array();
		$i    = 0;
		$j    = 0;

		while ( $i < $n && $j < $m ) {
			if ( $older[ $i ] === $newer[ $j ] ) {
				$kept[] = $newer[ $j ];
				++$i;
				++$j;
			} elseif ( $length[ $i + 1 ][ $j ] >= $length[ $i ][ $j + 1 ] ) {
				++$i;
			} else {
				++$j;
			}
		}

		return array_values( array_diff( $newer, $kept ) );
	}

	/**
	 * A value in one shape for comparison: a flag as `1`, a number or a string as the string it
	 * prints as, a list as the list of its flattened items, and nothing as null.
	 *
	 * @param mixed $value Any stored value.
	 * @return mixed
	 */
	private static function flat( $value ) {
		if ( null === $value || false === $value ) {
			return null;
		}

		if ( true === $value ) {
			return '1';
		}

		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'flat' ), $value );
		}

		return (string) $value;
	}
}
