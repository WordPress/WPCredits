<?php
/**
 * A track's questions, as a list somebody edits.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every rule the question editor needs, and nothing that touches WordPress or Airtable.
 *
 * The editor's handlers (`WPCPM_Track_Editor`) do the reading and the writing; this class decides.
 * Keeping the two apart is what lets the rules below be held by a suite that needs no WordPress at
 * all, the way `WPCPM_Track_Columns` is held by one that needs no Airtable (the design's section 3).
 *
 * A question is keyed by its Airtable column name, verbatim and never trimmed: `key()` hashes the
 * name and an Airtable name may end in a space (the design's 4.2). Nothing here trims one.
 */
final class WPCPM_Track_Questions {

	/**
	 * The properties whose change gives a shared question a column of its own.
	 *
	 * A different type is a different column (the design's 5). `airtable_type` is derived from the
	 * control rather than typed, so it moves with `type`; it is compared all the same, because a
	 * question whose stored type disagrees with its control must not pass for unchanged.
	 *
	 * @var string[]
	 */
	const FORKING = array( 'type', 'airtable_type', 'options' );

	/**
	 * What separates a forked column from the one it came from.
	 *
	 * @var string
	 */
	const FORK_JOIN = ' - ';

	/**
	 * Add a question, after the last question of its group or after a named column.
	 *
	 * The form draws group by group, so a question added to a group lands after the last one of
	 * that group rather than at the end of the map (the design's section 5). A question added under
	 * a Learn lesson names the lesson's last question as `$after` and lands right behind it, so a
	 * lesson's questions stay together (decision 32); a column the map does not hold falls back to
	 * the group rule.
	 *
	 * @param array  $questions Column => spec, in order.
	 * @param string $column    The new question's column, verbatim.
	 * @param array  $question  The new question.
	 * @param string $after     The column to place it after, when there is one.
	 * @return array|null The map with the question in place, or null when the column is used.
	 */
	public static function add( array $questions, $column, array $question, $after = '' ) {
		$column = (string) $column;

		if ( array_key_exists( $column, $questions ) ) {
			return null;
		}

		$after = (string) $after;

		if ( '' === $after || ! array_key_exists( $after, $questions ) ) {
			$group = isset( $question['group'] ) ? (string) $question['group'] : '';
			$after = '';

			foreach ( $questions as $name => $spec ) {
				if ( is_array( $spec ) && isset( $spec['group'] ) && (string) $spec['group'] === $group ) {
					$after = (string) $name;
				}
			}
		}

		if ( '' === $after ) {
			$questions[ $column ] = $question;

			return $questions;
		}

		$placed = array();

		foreach ( $questions as $name => $spec ) {
			$placed[ $name ] = $spec;

			if ( (string) $name === $after ) {
				$placed[ $column ] = $question;
			}
		}

		return $placed;
	}

	/**
	 * The last question reporting on a Learn lesson: where a new question of that lesson goes, and
	 * whether the lesson has a question at all (its first question is the one that carries the
	 * lesson's title as `lead`; decision 32).
	 *
	 * @param array $questions Column => spec, in order.
	 * @param int   $lesson_id The lesson.
	 * @return string The column, or '' when no question reports on the lesson.
	 */
	public static function last_of_lesson( array $questions, $lesson_id ) {
		$lesson_id = (int) $lesson_id;
		$last      = '';

		if ( $lesson_id <= 0 ) {
			return '';
		}

		foreach ( $questions as $name => $spec ) {
			if ( is_array( $spec ) && isset( $spec['learn_lesson_id'] ) && (int) $spec['learn_lesson_id'] === $lesson_id ) {
				$last = (string) $name;
			}
		}

		return $last;
	}

	/**
	 * Match every question that reports on a lesson against another course's lessons, by heading.
	 *
	 * A question's heading is its `lead`, or its `subgroup` when it has no lead (the Designer
	 * Track's form holds one lesson's title in a subgroup); it matches a lesson's title exactly once
	 * case, apostrophes and runs of spaces are folded, the way the design's 2.6 counted the matches.
	 * A match takes the new lesson's ID; the rest lose theirs and are listed; a question with no
	 * lesson is left alone, heading or not (decision 33).
	 *
	 * @param array   $questions Column => spec, in order.
	 * @param array[] $lessons   The new course's lessons, each `id` and `title`, every module together.
	 * @return array `questions` (the map, in the same order), `matched` and `cleared` (columns).
	 */
	public static function rematch( array $questions, array $lessons ) {
		$by_title = array();

		foreach ( $lessons as $lesson ) {
			if ( is_array( $lesson ) && isset( $lesson['id'], $lesson['title'] ) ) {
				$folded = self::fold( $lesson['title'] );

				if ( '' !== $folded && ! isset( $by_title[ $folded ] ) ) {
					$by_title[ $folded ] = (int) $lesson['id'];
				}
			}
		}

		$matched = array();
		$cleared = array();

		foreach ( $questions as $name => $spec ) {
			if ( ! is_array( $spec ) || ! isset( $spec['learn_lesson_id'] ) ) {
				continue;
			}

			$heading = isset( $spec['lead'] ) && '' !== (string) $spec['lead'] ? $spec['lead'] : ( isset( $spec['subgroup'] ) ? $spec['subgroup'] : '' );
			$folded  = self::fold( $heading );

			if ( '' !== $folded && isset( $by_title[ $folded ] ) ) {
				$questions[ $name ]['learn_lesson_id'] = $by_title[ $folded ];
				$matched[]                             = (string) $name;
			} else {
				unset( $questions[ $name ]['learn_lesson_id'] );
				$cleared[] = (string) $name;
			}
		}

		return array(
			'questions' => $questions,
			'matched'   => $matched,
			'cleared'   => $cleared,
		);
	}

	/**
	 * A heading or a title as the re-match compares it: lower case, apostrophes gone, one space
	 * between words.
	 *
	 * @param mixed $text The heading or title.
	 * @return string
	 */
	private static function fold( $text ) {
		$text = str_replace( array( "'", "\u{2019}", '`' ), '', (string) $text );

		return strtolower( trim( preg_replace( '/\s+/u', ' ', $text ) ) );
	}

	/**
	 * Move a question one place up or down among the questions of its own group.
	 *
	 * Swapping with whatever happens to sit beside it in the map would move it across a group
	 * boundary without changing its `group`, which reorders nothing a student sees and looks like
	 * a button that does not work.
	 *
	 * @param array  $questions The questions, column => spec, in order.
	 * @param string $column    The question to move.
	 * @param string $direction `up` or `down`.
	 * @return array The questions in their new order, unchanged when there is nowhere to go.
	 */
	public static function move( array $questions, $column, $direction ) {
		$column = (string) $column;

		if ( ! array_key_exists( $column, $questions ) ) {
			return $questions;
		}

		$spec  = $questions[ $column ];
		$group = is_array( $spec ) && isset( $spec['group'] ) ? (string) $spec['group'] : '';
		$peers = array();

		foreach ( $questions as $name => $other ) {
			if ( is_array( $other ) && isset( $other['group'] ) && (string) $other['group'] === $group ) {
				$peers[] = (string) $name;
			}
		}

		$at   = array_search( $column, $peers, true );
		$with = 'up' === $direction ? $at - 1 : $at + 1;

		if ( false === $at || ! isset( $peers[ $with ] ) ) {
			return $questions;
		}

		return self::swap( $questions, $column, $peers[ $with ] );
	}

	/**
	 * Two questions in each other's place, every other question where it was.
	 *
	 * @param array  $questions The questions.
	 * @param string $one       One column.
	 * @param string $other     The other.
	 * @return array
	 */
	private static function swap( array $questions, $one, $other ) {
		$swapped = array();

		foreach ( $questions as $name => $spec ) {
			if ( (string) $name === $one ) {
				$swapped[ $other ] = $questions[ $other ];
				continue;
			}

			if ( (string) $name === $other ) {
				$swapped[ $one ] = $questions[ $one ];
				continue;
			}

			$swapped[ $name ] = $spec;
		}

		return $swapped;
	}

	/**
	 * Take a question out.
	 *
	 * What it leaves in Airtable is not this class's business: the column and every answer in it
	 * stay, because nothing is deleted (the design's decision 9), and the screen says so.
	 *
	 * @param array  $questions The questions.
	 * @param string $column    The question to remove.
	 * @return array The questions without it.
	 */
	public static function remove( array $questions, $column ) {
		unset( $questions[ (string) $column ] );

		return $questions;
	}

	/**
	 * Give a question a different column, in the place it already holds.
	 *
	 * @param array  $questions The questions.
	 * @param string $from      The column it has.
	 * @param string $to        The column it should have, verbatim.
	 * @return array|null The questions, or null when there is nothing to rename or the new name
	 *                    belongs to another question.
	 */
	public static function rename( array $questions, $from, $to ) {
		$from = (string) $from;
		$to   = (string) $to;

		if ( ! array_key_exists( $from, $questions ) ) {
			return null;
		}

		if ( $from === $to ) {
			return $questions;
		}

		if ( array_key_exists( $to, $questions ) ) {
			return null;
		}

		$renamed = array();

		foreach ( $questions as $name => $spec ) {
			$renamed[ (string) $name === $from ? $to : $name ] = $spec;
		}

		return $renamed;
	}

	/**
	 * Which other tracks write this column.
	 *
	 * @param string $column The column.
	 * @param array  $others Each `label`, `published` and `columns`: every track but this one.
	 * @return array[] Those that hold it, in the order given, each `label` and `published`.
	 */
	public static function owners( $column, array $others ) {
		$column = (string) $column;
		$found  = array();

		foreach ( $others as $other ) {
			if ( ! is_array( $other ) || empty( $other['columns'] ) || ! is_array( $other['columns'] ) ) {
				continue;
			}

			if ( ! in_array( $column, array_map( 'strval', $other['columns'] ), true ) ) {
				continue;
			}

			$found[] = array(
				'label'     => isset( $other['label'] ) ? (string) $other['label'] : '',
				'published' => ! empty( $other['published'] ),
			);
		}

		return $found;
	}

	/**
	 * Whether this edit gives the question a column of its own.
	 *
	 * Rewording keeps the column and warns; changing the control, the Airtable type or a select's
	 * options is a different column (the design's 5).
	 *
	 * @param array $question The question as it would be saved.
	 * @param array $was      The question as it is stored.
	 * @return bool
	 */
	public static function forks( array $question, array $was ) {
		foreach ( self::FORKING as $property ) {
			$now = isset( $question[ $property ] ) ? $question[ $property ] : null;
			$old = isset( $was[ $property ] ) ? $was[ $property ] : null;

			if ( is_array( $now ) || is_array( $old ) ) {
				if ( array_map( 'strval', (array) $now ) !== array_map( 'strval', (array) $old ) ) {
					return true;
				}

				continue;
			}

			if ( (string) $now !== (string) $old ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The column a forked question takes.
	 *
	 * @param string $column The column it shared.
	 * @param string $key    The track's key.
	 * @return string
	 */
	public static function fork_name( $column, $key ) {
		return (string) $column . self::FORK_JOIN . (string) $key;
	}

	/**
	 * The column a forked question came from, when it reads as a fork of one another track holds.
	 *
	 * Worked out rather than recorded, so a person who renames a forked column is not left with a
	 * stored flag that says something the name no longer does.
	 *
	 * @param string $column The column this question holds.
	 * @param string $key    The track's key.
	 * @param array  $others As `owners()` takes them.
	 * @return string The column it forked from, or an empty string when it did not.
	 */
	public static function forked_from( $column, $key, array $others ) {
		$column = (string) $column;
		$tail   = self::FORK_JOIN . (string) $key;
		$cut    = strlen( $column ) - strlen( $tail );

		if ( '' === (string) $key || $cut < 1 || substr( $column, $cut ) !== $tail ) {
			return '';
		}

		$from = substr( $column, 0, $cut );

		return array() === self::owners( $from, $others ) ? '' : $from;
	}

	/**
	 * Whether a question's column is fixed because it has been published.
	 *
	 * Renaming it would leave the old column holding every student's answer while the form began
	 * writing to a new one, and nothing is deleted (the design's decisions 9 and 23).
	 *
	 * @param string $column    The column.
	 * @param array  $published The published copy's questions, column => spec.
	 * @return bool
	 */
	public static function locked( $column, array $published ) {
		return array_key_exists( (string) $column, $published );
	}
}
