<?php
/**
 * A track's questions as Airtable columns.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * What a question becomes in the base, and what a column already there means for it.
 *
 * One job, and no request: publishing (`WPCPM_Track_Publish`) asks this class what to create and
 * what to make of what it finds, then does the talking. Keeping the two apart is what lets every
 * rule below be held by a suite that needs no Airtable at all (the design's section 3).
 */
final class WPCPM_Track_Columns {

	/**
	 * The control a question uses, and the Airtable type a new column for it gets.
	 *
	 * The design's 4.2 fixes this map. `team` is absent on purpose: it is written for
	 * `Main Contribution Team`, the one column all four tracks share, and a second link column
	 * would carry a reverse field into a second table, so a team question never creates one.
	 *
	 * @var array<string,string>
	 */
	const TYPES = array(
		'text'     => 'singleLineText',
		'textarea' => 'multilineText',
		'richtext' => 'richText',
		'url'      => 'url',
		'email'    => 'email',
		'number'   => 'number',
		'checkbox' => 'checkbox',
		'select'   => 'singleSelect',
		'image'    => 'multipleAttachments',
	);

	/**
	 * The Airtable types nothing may be written to.
	 *
	 * A student's answer sent to one of these is refused by Airtable, and a question bound to one
	 * would look saved on the site and be missing in the base (the design's 4.2).
	 *
	 * @var string[]
	 */
	const COMPUTED = array( 'formula', 'rollup', 'count', 'multipleLookupValues' );

	/**
	 * Airtable's own type for a link to another table.
	 *
	 * @var string
	 */
	const LINK = 'multipleRecordLinks';

	/**
	 * The one link column a question may use, because all four tracks already share it.
	 *
	 * @var string
	 */
	const TEAM_COLUMN = 'Main Contribution Team';

	/**
	 * The column a question asks Airtable to create.
	 *
	 * @param string $column   The column name, verbatim: `key()` hashes it and an Airtable name may
	 *                         end in a space, so nothing here trims it (the design's 4.2).
	 * @param array  $question The question, as the definition holds it.
	 * @return array|null The create-field body, or null when this control never creates a column.
	 */
	public static function field( $column, array $question ) {
		$type = isset( $question['type'] ) ? (string) $question['type'] : '';

		if ( ! isset( self::TYPES[ $type ] ) ) {
			return null;
		}

		$field = array(
			'name' => (string) $column,
			'type' => self::TYPES[ $type ],
		);

		if ( 'number' === $type ) {
			// Airtable asks how many decimal places to keep, and the step the form already uses
			// says it: the thirteen whole counts step by 1, the forty grades by 0.01.
			$field['options'] = array( 'precision' => self::precision( $question ) );
		}

		if ( 'checkbox' === $type ) {
			// The base's own checkbox, so a new one does not stand out beside it (4.2).
			$field['options'] = array(
				'icon'  => 'check',
				'color' => 'greenBright',
			);
		}

		if ( 'select' === $type ) {
			$choices = array();

			foreach ( isset( $question['options'] ) ? (array) $question['options'] : array() as $choice ) {
				$choices[] = array( 'name' => (string) $choice );
			}

			$field['options'] = array( 'choices' => $choices );
		}

		return $field;
	}

	/**
	 * What a column already in the base means for the question that names it.
	 *
	 * @param string $column   The column name.
	 * @param array  $question The question.
	 * @param array  $columns  The table's columns, keyed by name, each with a `type`, as
	 *                         `WPCPM_Airtable::fetch_schema()` reports them.
	 * @return string `create` when it is not there, `ok` when it is ready to be written to,
	 *                `type_mismatch`, `computed`, or `foreign_link`.
	 */
	public static function judge( $column, array $question, array $columns ) {
		$column = (string) $column;

		if ( ! isset( $columns[ $column ]['type'] ) ) {
			return 'create';
		}

		$type = (string) $columns[ $column ]['type'];

		if ( in_array( $type, self::COMPUTED, true ) ) {
			return 'computed';
		}

		$wanted = isset( $question['type'] ) ? (string) $question['type'] : '';

		if ( self::LINK === $type ) {
			// The reverse field a link carries is why only the shared one passes (4.2).
			// Only a team question may use Main Contribution Team; anything else reaches another table.
			if ( self::TEAM_COLUMN === $column ) {
				return 'team' === $wanted ? 'ok' : 'foreign_link';
			}
			return 'foreign_link';
		}

		if ( ! isset( self::TYPES[ $wanted ] ) ) {
			return 'ok';
		}

		return self::TYPES[ $wanted ] === $type ? 'ok' : 'type_mismatch';
	}

	/**
	 * How many decimal places a number question keeps.
	 *
	 * @param array $question The question.
	 * @return int
	 */
	private static function precision( array $question ) {
		$step = isset( $question['step'] ) ? (string) $question['step'] : '';
		$dot  = strpos( $step, '.' );

		if ( false === $dot ) {
			return 0;
		}

		return strlen( rtrim( substr( $step, $dot + 1 ), '0' ) );
	}
}
