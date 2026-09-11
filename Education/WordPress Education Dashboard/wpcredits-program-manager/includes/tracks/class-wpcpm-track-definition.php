<?php
/**
 * A program track as data: what the Track Builder stores and what the live site runs.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One track's definition: its shape, its rules, and the two things compiled from it.
 *
 * **A question is exactly a `WPCPM_Student_Report_Form::fields()` entry, keyed by its
 * Airtable column,** plus three properties the form never sees (`AUTHORING`). That is the
 * whole design. The four hand-written forms were passed through `json_encode()` and back on
 * 10 September 2026 and came back identical, so a definition can hold what the PHP holds,
 * and everything downstream of `fields()` - the renderer, `WPCPM_Field_Value`,
 * `handle_save()`, the screenshot store - runs an authored track unchanged.
 *
 * The rules were set from what the four forms actually use, a probe of all 115 questions,
 * and `bin/test-report-form.php` holds them to it: every rule here accepts the four forms,
 * so no rule can make parity impossible.
 *
 * Nothing here reads WordPress state. What a rule needs from the site - the other tracks,
 * the statuses that mean something else, the columns the syncs own - arrives as `$context`,
 * which `WPCPM_Tracks::validation_context()` builds, so each rule is a function of its
 * arguments and the suite can ask every one of them.
 */
final class WPCPM_Track_Definition {

	/** The version of this shape; a stored definition says which one it was written in. */
	const SCHEMA_VERSION = 1;

	/** The ten controls `WPCPM_Student_Report_Form::render_field()` draws. */
	const TYPES = array( 'text', 'textarea', 'richtext', 'url', 'email', 'number', 'checkbox', 'select', 'image', 'team' );

	/**
	 * The form's groups, as `WPCPM_Student_Report_Form::groups()` keys them.
	 *
	 * Written out rather than read from that class, which would make these rules load the whole
	 * form; `bin/test-report-form.php` asserts that the two lists are the same.
	 */
	const GROUPS = array( 'hours', 'onboarding', 'project', 'wrapup' );

	/**
	 * Keys no new track may take: the four built-in tracks' and the other modifiers `badge()`
	 * already emits. A key is also a class name the stylesheets paint.
	 */
	const RESERVED_KEYS = array( '150h', '50h', 'dev', 'design', 'sensei', 'paused', 'pending' );

	/** Every property a track may have. */
	const TRACK_PROPERTIES = array( 'schema_version', 'status', 'key', 'label', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'questions' );

	/** Every property a question may have. */
	const QUESTION_PROPERTIES = array( 'type', 'label', 'group', 'help', 'lead', 'subgroup', 'note', 'row', 'stack', 'required', 'min', 'max', 'step', 'maxlength', 'mono', 'options', 'hide_from_institution', 'airtable_type', 'learn_lesson_id', 'why' );

	/** The properties the form never sees; compiling strips them. */
	const AUTHORING = array( 'airtable_type', 'learn_lesson_id', 'why' );

	/** Properties that are `true` or absent. */
	const FLAGS = array( 'stack', 'required', 'mono', 'hide_from_institution' );

	/** Properties holding words, other than the label. */
	const TEXTS = array( 'help', 'lead', 'subgroup', 'note', 'why' );

	/**
	 * The one column a `team` question may write.
	 *
	 * The team control is written for it: its choices are the rows of `Contribution areas`, and
	 * every link column in the base carries a reverse field in the table it points at, so a
	 * second link column would reach into a second table.
	 */
	const TEAM_COLUMN = 'Main Contribution Team';

	/**
	 * The Airtable types a question may be bound to.
	 *
	 * Computed types (formula, lookup, rollup, count and the rest) are missing because Airtable
	 * refuses every write to them; a link is allowed for the team question alone.
	 */
	const WRITABLE_TYPES = array( 'singleLineText', 'multilineText', 'richText', 'url', 'email', 'number', 'checkbox', 'singleSelect', 'multipleAttachments', 'multipleRecordLinks' );

	/** The longest status: one line of the "Currently mentoring" box, and a chip that still reads. */
	const MAX_STATUS = 100;

	/** The longest column name Airtable accepts. */
	const MAX_COLUMN = 255;

	/**
	 * The definition in its one canonical shape, ready to validate and store.
	 *
	 * Trims the status and the name (the plugin trims a status everywhere it reads one), never
	 * a column name: `key()` hashes the name, and an Airtable name can end in a space. An empty
	 * hours target is removed rather than stored as 0, because no target and an explicit 0
	 * already mean the same thing (decision 1.9 of the design). Every `email` question is kept
	 * off the institution's view, and a flag that is `false` or `null` is dropped, so two definitions
	 * that mean the same thing are the same bytes.
	 *
	 * @param array $definition Track definition.
	 * @return array
	 */
	public static function normalize( array $definition ) {
		if ( ! isset( $definition['schema_version'] ) ) {
			$definition['schema_version'] = self::SCHEMA_VERSION;
		}

		foreach ( array( 'status', 'label' ) as $property ) {
			if ( isset( $definition[ $property ] ) && is_string( $definition[ $property ] ) ) {
				$definition[ $property ] = trim( $definition[ $property ] );
			}
		}

		if ( array_key_exists( 'hours_target', $definition ) && ( null === $definition['hours_target'] || '' === $definition['hours_target'] ) ) {
			unset( $definition['hours_target'] );
		}

		if ( isset( $definition['questions'] ) && is_array( $definition['questions'] ) ) {
			foreach ( $definition['questions'] as $column => $spec ) {
				if ( ! is_array( $spec ) ) {
					continue;
				}

				if ( isset( $spec['type'] ) && 'email' === $spec['type'] ) {
					$spec['hide_from_institution'] = true;
				}

				foreach ( self::FLAGS as $flag ) {
					if ( array_key_exists( $flag, $spec ) && ( false === $spec[ $flag ] || null === $spec[ $flag ] ) ) {
						unset( $spec[ $flag ] );
					}
				}

				$definition['questions'][ $column ] = $spec;
			}
		}

		return $definition;
	}

	/**
	 * Everything wrong with a definition, as a list a screen can print.
	 *
	 * @param array $definition Track definition, after `normalize()`.
	 * @param array $context    `tracks` (every other track, status => key), `refused_statuses`
	 *                          (statuses that mean something else), `reserved_columns` (the
	 *                          columns the syncs own) and `locked` (the status and key a
	 *                          published track keeps, or null).
	 * @return array[] Each with a `code`, a `where` (a column, or empty for the track) and a
	 *                 `message`. Empty when the definition may be stored.
	 */
	public static function validate( array $definition, array $context = array() ) {
		$context = array_merge(
			array(
				'tracks'           => array(),
				'refused_statuses' => array(),
				'reserved_columns' => array(),
				'locked'           => null,
			),
			$context
		);

		$errors = array();

		foreach ( array_keys( $definition ) as $property ) {
			if ( ! in_array( $property, self::TRACK_PROPERTIES, true ) ) {
				$errors[] = self::error( 'unknown_property', '', sprintf( /* translators: %s: a property name. */ __( 'The track has a property this version does not know: %s.', 'wpcredits-program-manager' ), $property ) );
			}
		}

		if ( ! isset( $definition['schema_version'] ) || self::SCHEMA_VERSION !== $definition['schema_version'] ) {
			$errors[] = self::error( 'schema_version', '', __( 'The track was written in a version of this format the site does not read.', 'wpcredits-program-manager' ) );
		}

		$errors = array_merge( $errors, self::validate_status( $definition, $context ), self::validate_key( $definition, $context ) );

		if ( ! isset( $definition['label'] ) || ! is_string( $definition['label'] ) || '' === trim( $definition['label'] ) ) {
			$errors[] = self::error( 'label_empty', '', __( 'The track needs a name.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $definition['course_url'] ) && '' !== $definition['course_url'] && ( ! is_string( $definition['course_url'] ) || 1 !== preg_match( '#^https://learn\.wordpress\.org/course/[a-z0-9-]+/?$#', $definition['course_url'] ) ) ) {
			$errors[] = self::error( 'course_url', '', __( 'The course link must be the address of a Learn WordPress course: https://learn.wordpress.org/course/ and its name.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $definition['learn_course_id'] ) && ( ! is_int( $definition['learn_course_id'] ) || $definition['learn_course_id'] < 0 ) ) {
			$errors[] = self::error( 'course_id', '', __( 'The Learn course ID must be a whole number.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $definition['hours_target'] ) && ( ! is_int( $definition['hours_target'] ) || $definition['hours_target'] < 0 ) ) {
			$errors[] = self::error( 'hours_target', '', __( 'The hours target must be a whole number of hours, or empty for none.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $definition['hue'] ) || ! WPCPM_Track_Palette::is_hue( $definition['hue'] ) ) {
			$errors[] = self::error( 'hue', '', __( 'Choose one of the chip colors.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $definition['questions'] ) || ! is_array( $definition['questions'] ) ) {
			$errors[] = self::error( 'questions_shape', '', __( 'The track\'s questions could not be read.', 'wpcredits-program-manager' ) );

			return $errors;
		}

		$teams = 0;

		foreach ( $definition['questions'] as $column => $spec ) {
			$errors = array_merge( $errors, self::validate_question( (string) $column, $spec, $context ) );

			if ( is_array( $spec ) && isset( $spec['type'] ) && 'team' === $spec['type'] ) {
				++$teams;
			}
		}

		if ( $teams > 1 ) {
			$errors[] = self::error( 'team_twice', '', __( 'A track asks for the contribution team once.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The status rules: one line, not taken, not a status that means something else, and kept
	 * once the track is published.
	 *
	 * @param array $definition Track definition.
	 * @param array $context    See `validate()`.
	 * @return array[]
	 */
	private static function validate_status( array $definition, array $context ) {
		$status = isset( $definition['status'] ) && is_string( $definition['status'] ) ? $definition['status'] : '';

		if ( '' === $status ) {
			return array( self::error( 'status_empty', '', __( 'The track needs its Airtable status.', 'wpcredits-program-manager' ) ) );
		}

		$errors = array();

		if ( strlen( $status ) > self::MAX_STATUS || 1 === preg_match( '/[\r\n]/', $status ) ) {
			$errors[] = self::error( 'status_shape', '', sprintf( /* translators: %d: a number of characters. */ __( 'The status must be one line of at most %d characters.', 'wpcredits-program-manager' ), self::MAX_STATUS ) );
		}

		$folded = self::fold( $status );

		foreach ( array_keys( (array) $context['tracks'] ) as $other ) {
			if ( self::fold( $other ) === $folded ) {
				$errors[] = self::error( 'status_taken', '', __( 'Another track already has this status.', 'wpcredits-program-manager' ) );
				break;
			}
		}

		foreach ( (array) $context['refused_statuses'] as $refused ) {
			if ( self::fold( $refused ) === $folded ) {
				$errors[] = self::error( 'status_refused', '', __( 'This status already means something else to the program, such as a student who has finished or paused.', 'wpcredits-program-manager' ) );
				break;
			}
		}

		if ( is_array( $context['locked'] ) && isset( $context['locked']['status'] ) && $status !== $context['locked']['status'] ) {
			$errors[] = self::error( 'status_locked', '', __( 'A published track keeps its status: every student on it is found by that exact value.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The key rules: its shape, not a key the stylesheets already paint, not taken, and kept
	 * once the track is published.
	 *
	 * A reserved key passes for the track that already holds it, which is how a built-in track's
	 * own definition keeps `design` (the migration of phase T2 creates it locked to its key).
	 *
	 * @param array $definition Track definition.
	 * @param array $context    See `validate()`.
	 * @return array[]
	 */
	private static function validate_key( array $definition, array $context ) {
		$key = isset( $definition['key'] ) && is_string( $definition['key'] ) ? $definition['key'] : '';

		if ( 1 !== preg_match( '/^[a-z0-9-]{2,20}$/', $key ) ) {
			return array( self::error( 'key_shape', '', __( 'The key is 2 to 20 lowercase letters, digits or hyphens.', 'wpcredits-program-manager' ) ) );
		}

		$errors = array();
		$own    = is_array( $context['locked'] ) && isset( $context['locked']['key'] ) ? (string) $context['locked']['key'] : null;

		if ( $key !== $own && in_array( $key, self::RESERVED_KEYS, true ) ) {
			$errors[] = self::error( 'key_reserved', '', __( 'This key belongs to a built-in track or to a chip the site already paints.', 'wpcredits-program-manager' ) );
		} elseif ( in_array( $key, array_map( 'strval', array_values( (array) $context['tracks'] ) ), true ) ) {
			$errors[] = self::error( 'key_taken', '', __( 'Another track already has this key.', 'wpcredits-program-manager' ) );
		}

		if ( null !== $own && $key !== $own ) {
			$errors[] = self::error( 'key_locked', '', __( 'A published track keeps its key.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The rules of one question.
	 *
	 * @param string $column  Airtable column name.
	 * @param mixed  $spec    The question.
	 * @param array  $context See `validate()`.
	 * @return array[]
	 */
	private static function validate_question( $column, $spec, array $context ) {
		$errors = array();

		if ( '' === trim( $column ) || strlen( $column ) > self::MAX_COLUMN ) {
			$errors[] = self::error( 'column_shape', $column, sprintf( /* translators: %d: a number of characters. */ __( 'A column name must hold more than spaces, and at most %d characters.', 'wpcredits-program-manager' ), self::MAX_COLUMN ) );
		} elseif ( ctype_digit( $column ) ) {
			$errors[] = self::error( 'column_numeric', $column, __( 'A column name cannot be a number alone: the form keys each question by its column name, and a number there changes meaning.', 'wpcredits-program-manager' ) );
		}

		if ( in_array( $column, (array) $context['reserved_columns'], true ) ) {
			$errors[] = self::error( 'column_reserved', $column, __( 'This column belongs to the syncs. A question writing it would let a student change it.', 'wpcredits-program-manager' ) );
		}

		if ( ! is_array( $spec ) ) {
			$errors[] = self::error( 'question_shape', $column, __( 'This question could not be read.', 'wpcredits-program-manager' ) );

			return $errors;
		}

		foreach ( array_keys( $spec ) as $property ) {
			if ( ! in_array( $property, self::QUESTION_PROPERTIES, true ) ) {
				$errors[] = self::error( 'unknown_property', $column, sprintf( /* translators: %s: a property name. */ __( 'The question has a property this version does not know: %s.', 'wpcredits-program-manager' ), $property ) );
			}
		}

		$type = isset( $spec['type'] ) ? $spec['type'] : '';

		if ( ! in_array( $type, self::TYPES, true ) ) {
			$errors[] = self::error( 'type_unknown', $column, __( 'The question needs one of the ten controls.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $spec['label'] ) || ! is_string( $spec['label'] ) || '' === trim( $spec['label'] ) ) {
			$errors[] = self::error( 'label_empty', $column, __( 'The question needs the words a student reads.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $spec['group'] ) || ! in_array( $spec['group'], self::GROUPS, true ) ) {
			$errors[] = self::error( 'group_unknown', $column, __( 'The question needs a group: Total hours, Onboarding, Project or Wrap-up.', 'wpcredits-program-manager' ) );
		}

		foreach ( self::TEXTS as $property ) {
			if ( isset( $spec[ $property ] ) && ! is_string( $spec[ $property ] ) ) {
				$errors[] = self::error( 'text_shape', $column, sprintf( /* translators: %s: a property name. */ __( '%s must be text.', 'wpcredits-program-manager' ), $property ) );
			}
		}

		foreach ( self::FLAGS as $flag ) {
			if ( array_key_exists( $flag, $spec ) && true !== $spec[ $flag ] ) {
				$errors[] = self::error( 'flag_shape', $column, sprintf( /* translators: %s: a property name. */ __( '%s is either on or absent.', 'wpcredits-program-manager' ), $flag ) );
			}
		}

		if ( isset( $spec['row'] ) && ( ! is_string( $spec['row'] ) || 1 !== preg_match( '/^[a-z0-9-]+$/', $spec['row'] ) ) ) {
			$errors[] = self::error( 'row_shape', $column, __( 'A row is named with lowercase letters, digits or hyphens.', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( $spec['stack'] ) && empty( $spec['row'] ) ) {
			$errors[] = self::error( 'stack_without_row', $column, __( 'Only a question in a row can share a column of it.', 'wpcredits-program-manager' ) );
		}

		$errors = array_merge( $errors, self::validate_control( $column, $type, $spec ) );

		if ( isset( $spec['airtable_type'] ) ) {
			if ( ! in_array( $spec['airtable_type'], self::WRITABLE_TYPES, true ) ) {
				$errors[] = self::error( 'airtable_type', $column, __( 'A form cannot write this kind of Airtable column.', 'wpcredits-program-manager' ) );
			} elseif ( ( 'multipleRecordLinks' === $spec['airtable_type'] ) !== ( 'team' === $type ) ) {
				$errors[] = self::error( 'airtable_link', $column, __( 'The contribution team question writes a linked-record column, and no other question does.', 'wpcredits-program-manager' ) );
			}
		}

		if ( isset( $spec['learn_lesson_id'] ) && ( ! is_int( $spec['learn_lesson_id'] ) || $spec['learn_lesson_id'] < 0 ) ) {
			$errors[] = self::error( 'lesson_shape', $column, __( 'The Learn lesson ID must be a whole number.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The rules that belong to one control: numbers have bounds, selects have choices, and a
	 * property that means something to one control appears on that control only.
	 *
	 * @param string $column Airtable column name.
	 * @param mixed  $type   The question's control.
	 * @param array  $spec   The question.
	 * @return array[]
	 */
	private static function validate_control( $column, $type, array $spec ) {
		$errors = array();

		if ( 'number' === $type ) {
			$bounded = isset( $spec['min'], $spec['max'], $spec['step'] ) && is_numeric( $spec['min'] ) && is_numeric( $spec['max'] ) && is_numeric( $spec['step'] );

			if ( ! $bounded || (float) $spec['min'] > (float) $spec['max'] || (float) $spec['step'] <= 0 ) {
				$errors[] = self::error( 'number_bounds', $column, __( 'A number needs a lowest value, a highest value at least as high, and a step above zero.', 'wpcredits-program-manager' ) );
			}
		} elseif ( isset( $spec['min'] ) || isset( $spec['max'] ) || isset( $spec['step'] ) ) {
			$errors[] = self::error( 'number_only', $column, __( 'Only a number has a lowest value, a highest value and a step.', 'wpcredits-program-manager' ) );
		}

		if ( 'select' === $type ) {
			if ( ! self::is_choice_list( isset( $spec['options'] ) ? $spec['options'] : null ) ) {
				$errors[] = self::error( 'options_shape', $column, __( 'A select needs its choices, each written once and none of them empty.', 'wpcredits-program-manager' ) );
			}
		} elseif ( isset( $spec['options'] ) ) {
			$errors[] = self::error( 'options_only', $column, __( 'Only a select has choices.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $spec['maxlength'] ) && ( ! in_array( $type, array( 'text', 'textarea' ), true ) || ! is_int( $spec['maxlength'] ) || $spec['maxlength'] < 1 ) ) {
			$errors[] = self::error( 'maxlength_shape', $column, __( 'A length limit is a whole number above zero, on a text box.', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( $spec['mono'] ) && 'textarea' !== $type ) {
			$errors[] = self::error( 'mono_only', $column, __( 'Only a text area can be set in monospace.', 'wpcredits-program-manager' ) );
		}

		if ( 'team' === $type && self::TEAM_COLUMN !== $column ) {
			$errors[] = self::error( 'team_column', $column, sprintf( /* translators: %s: the contribution team column's name. */ __( 'The contribution team question writes %s and no other column.', 'wpcredits-program-manager' ), self::TEAM_COLUMN ) );
		}

		return $errors;
	}

	/**
	 * Whether a select's choices are a list of words, each written once.
	 *
	 * @param mixed $options The choices.
	 * @return bool
	 */
	private static function is_choice_list( $options ) {
		if ( ! is_array( $options ) || array() === $options || array_values( $options ) !== $options ) {
			return false;
		}

		foreach ( $options as $option ) {
			if ( ! is_string( $option ) || '' === trim( $option ) ) {
				return false;
			}
		}

		return count( array_unique( $options ) ) === count( $options );
	}

	/**
	 * The stored form of a definition.
	 *
	 * Unicode and slashes unescaped, so the typographic apostrophe of `Site’s` is stored as the
	 * character it is and a course address reads as one. The caller slashes the result before
	 * `update_post_meta()`, which unslashes what it is given.
	 *
	 * @param array $definition Track definition.
	 * @return string JSON.
	 */
	public static function encode( array $definition ) {
		return (string) wp_json_encode( $definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * A stored definition, read back.
	 *
	 * @param mixed $json What was stored.
	 * @return array|null The definition, or null for anything that is not one.
	 */
	public static function decode( $json ) {
		if ( ! is_string( $json ) || '' === $json ) {
			return null;
		}

		$definition = json_decode( $json, true );

		return is_array( $definition ) ? $definition : null;
	}

	/**
	 * The form the live site draws: the questions, with the properties it never sees removed.
	 *
	 * @param array $definition Track definition.
	 * @return array Column => the spec `WPCPM_Student_Report_Form::fields()` returns for it.
	 */
	public static function compile_fields( array $definition ) {
		$questions = isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? $definition['questions'] : array();
		$fields    = array();

		foreach ( $questions as $column => $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}

			foreach ( self::AUTHORING as $property ) {
				unset( $spec[ $property ] );
			}

			$fields[ $column ] = $spec;
		}

		return $fields;
	}

	/**
	 * The track's row in the compiled index the program map reads.
	 *
	 * @param array  $definition Track definition.
	 * @param int    $post_id    The definition's post.
	 * @param string $source     `definition`, or `builtin` for a migrated track its PHP still runs.
	 * @param bool   $automation Whether somebody has ticked the reports automation item.
	 * @return array
	 */
	public static function row( array $definition, $post_id, $source = 'definition', $automation = false ) {
		return array(
			'key'        => isset( $definition['key'] ) ? (string) $definition['key'] : '',
			'label'      => isset( $definition['label'] ) ? (string) $definition['label'] : '',
			'course_url' => isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '',
			'course_id'  => isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0,
			'hours'      => isset( $definition['hours_target'] ) ? (int) $definition['hours_target'] : null,
			'hue'        => isset( $definition['hue'] ) ? (string) $definition['hue'] : '',
			'source'     => 'builtin' === $source ? 'builtin' : 'definition',
			'automation' => (bool) $automation,
			'post'       => (int) $post_id,
		);
	}

	/**
	 * A status as the comparisons see it: trimmed, one space for any run of them, the
	 * typographic apostrophe read as the plain one, and lower case.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function fold( $status ) {
		$status = str_replace( array( "\u{2019}", "\u{2018}" ), "'", (string) $status );

		return strtolower( trim( (string) preg_replace( '/\s+/', ' ', $status ) ) );
	}

	/**
	 * One entry of `validate()`'s list.
	 *
	 * @param string $code    What was wrong, for code to act on.
	 * @param string $where   The column, or empty for the track.
	 * @param string $message What was wrong, for a person.
	 * @return array
	 */
	private static function error( $code, $where, $message ) {
		return array(
			'code'    => $code,
			'where'   => (string) $where,
			'message' => $message,
		);
	}
}
