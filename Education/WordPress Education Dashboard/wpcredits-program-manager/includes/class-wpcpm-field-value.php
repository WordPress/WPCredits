<?php
/**
 * One posted value, cleaned into the shape Airtable takes, or refused with a reason.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place a posted answer becomes an Airtable cell.
 *
 * Hoisted out of the Student Report Card form and the feedback forms, which each grew their own
 * copy of this and then disagreed in small ways - a rule that one form applied and the other did
 * not is exactly the kind of thing a student cannot see and a manager cannot explain. The
 * application form and the student import build on the same rules, so there is one set.
 *
 * Every type is handled explicitly. Nothing reaches Airtable without passing through here - a
 * number field given "twelve" is a 422 for the whole request, so one bad answer would otherwise
 * lose the other twenty-one.
 *
 * A refusal names its reason with a short key rather than a sentence, because the caller decides
 * how to say it: a form lists the rejected fields on its flash, an import lists them per row.
 * The keys are `not_scalar`, `not_a_number`, `below_min`, `above_max`, `bad_email`,
 * `bad_choice` and `out_of_range`.
 */
final class WPCPM_Field_Value {

	/**
	 * How much free text a cell holds when the spec does not say.
	 *
	 * Airtable's long-text columns take far more, but the forms have never accepted more than
	 * this. A caller with its own ceiling passes it as `max_text`, which is how both forms keep
	 * the cap they had before the rules moved here.
	 *
	 * @var int
	 */
	const MAX_TEXT = 5000;

	/**
	 * A submitted value, in the shape Airtable takes, or a refusal.
	 *
	 * @param mixed $raw  Posted value.
	 * @param array $spec Field spec: `type` (team, checkbox, email, number, url, text, textarea,
	 *                    richtext, rating, select; a missing type is text) and, per type, `min`,
	 *                    `max`, `step`, `maxlength`, `max_text` and `choices`.
	 * @return array{ok:bool,value:mixed,problem:string} `problem` is '' when `ok`, and `value`
	 *                                                   is null when it is not.
	 */
	public static function clean( $raw, array $spec ) {
		$type = isset( $spec['type'] ) ? (string) $spec['type'] : 'text';

		// Before the scalar guard: teams post as an array of checkbox values, and reading them
		// through `is_scalar()` would turn the array into an empty string - the field would stop
		// saving the moment the control became a checkbox list, and silently.
		if ( 'team' === $type ) {
			$known = WPCPM_Contribution_Teams::options();
			$ids   = array();

			foreach ( (array) $raw as $id ) {
				if ( ! is_scalar( $id ) ) {
					continue;
				}

				$id = trim( (string) $id );

				// Duplicates collapse, because Airtable will happily store the same link twice.
				if ( '' !== $id && isset( $known[ $id ] ) && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}

			// A linked-record field takes an array of record IDs, however many. An empty array is
			// how every link is cleared; a bare empty string would be rejected. An unknown ID is
			// dropped rather than refusing the answer, because the list the student ticked from
			// is the plugin's own and a stale one is the plugin's fault, not theirs.
			return self::accept( $ids );
		}

		if ( ! is_scalar( $raw ) ) {
			return self::refuse( 'not_scalar' );
		}

		$raw = trim( (string) $raw );

		// A tick posts `1` from either form. What an unticked box posts differs: the report form
		// carries a hidden `0` so the answer is always present, and the feedback form posts
		// nothing, with its handler passing '' in its place. Both read as false under the report
		// form's strict rule, which is the one kept: a box is ticked only when the browser posted
		// the value the control carries, and anything else scalar is an unticked box. The
		// feedback form's `! empty()` would have taken any word as a tick, and read a non-empty
		// array as one too; an array is now refused above like every other field. Only a
		// hand-made request can post either, so no student's answer changes. Airtable takes a
		// real boolean and clears a checkbox with `false`, not with an empty string.
		if ( 'checkbox' === $type ) {
			return self::accept( '1' === $raw || 'true' === strtolower( $raw ) );
		}

		if ( 'email' === $type ) {
			// Emptying the box clears the column, the same bargain the number fields strike.
			if ( '' === $raw ) {
				return self::accept( '' );
			}

			$email = sanitize_email( $raw );

			return is_email( $email ) ? self::accept( $email ) : self::refuse( 'bad_email' );
		}

		if ( 'number' === $type ) {
			// Emptying the box means emptying the column, and Airtable does that with `null`. An
			// empty string in a number field is a 422 for the whole request, which would lose the
			// other twenty-one answers along with this one.
			if ( '' === $raw ) {
				return self::accept( null );
			}

			// A comma decimal is what half of Europe types, and Airtable will not take it.
			$raw = str_replace( ',', '.', $raw );

			if ( ! is_numeric( $raw ) ) {
				return self::refuse( 'not_a_number' );
			}

			$number = ( isset( $spec['step'] ) && '1' === $spec['step'] ) ? (int) round( (float) $raw ) : round( (float) $raw, 2 );

			if ( isset( $spec['min'] ) && $number < $spec['min'] ) {
				return self::refuse( 'below_min' );
			}

			if ( isset( $spec['max'] ) && $number > $spec['max'] ) {
				return self::refuse( 'above_max' );
			}

			return self::accept( $number );
		}

		if ( 'url' === $type ) {
			return self::accept( self::clean_url( $raw ) );
		}

		if ( 'rating' === $type ) {
			if ( '' === $raw ) {
				// Cleared, which is a legitimate answer to a question nobody has to answer.
				return self::accept( null );
			}

			$max    = isset( $spec['max'] ) ? (int) $spec['max'] : 5;
			$number = (int) $raw;

			return ( $number >= 1 && $number <= $max ) ? self::accept( $number ) : self::refuse( 'out_of_range' );
		}

		if ( 'select' === $type ) {
			if ( '' === $raw ) {
				return self::accept( null );
			}

			// **Matched against the choices the column actually has.** Airtable would otherwise
			// refuse the whole record - or, with typecast on, quietly invent a new option - and a
			// hand-edited form must be able to do neither. A spec with no choices at all has
			// nothing to match, so it takes nothing.
			$choices = isset( $spec['choices'] ) ? (array) $spec['choices'] : array();

			return in_array( $raw, $choices, true ) ? self::accept( $raw ) : self::refuse( 'bad_choice' );
		}

		if ( 'text' === $type ) {
			$max = isset( $spec['maxlength'] ) ? (int) $spec['maxlength'] : self::max_text( $spec );

			return self::accept( sanitize_text_field( mb_substr( $raw, 0, $max ) ) );
		}

		// `textarea` and `richtext` both arrive as text. Rich text is Markdown in Airtable, so it
		// is stored as typed rather than being converted - a student writing a bullet list gets a
		// bullet list, and one writing prose gets prose. A type nobody has heard of lands here as
		// well, because refusing it would lose an answer over a typo in a spec.
		return self::accept( mb_substr( sanitize_textarea_field( $raw ), 0, self::max_text( $spec ) ) );
	}

	/**
	 * A URL the way the rest of the plugin normalizes them.
	 *
	 * Airtable's url columns are full of scheme-less values, and a student retyping one the same
	 * way should not be told off for it.
	 *
	 * @param string $raw Posted value.
	 * @return string
	 */
	public static function clean_url( $raw ) {
		if ( '' === $raw ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $raw ) ) {
			$raw = 'https://' . ltrim( $raw, '/' );
		}

		$url = esc_url_raw( $raw, array( 'http', 'https' ) );

		return $url ? $url : '';
	}

	/**
	 * The free-text ceiling a caller asked for, or the class's own.
	 *
	 * @param array $spec Field spec.
	 * @return int
	 */
	private static function max_text( array $spec ) {
		return isset( $spec['max_text'] ) ? (int) $spec['max_text'] : self::MAX_TEXT;
	}

	/**
	 * A value that will be written.
	 *
	 * @param mixed $value What Airtable gets.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function accept( $value ) {
		return array(
			'ok'      => true,
			'value'   => $value,
			'problem' => '',
		);
	}

	/**
	 * A value that will not be written, and why.
	 *
	 * @param string $problem One of the keys the class docblock lists.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function refuse( $problem ) {
		return array(
			'ok'      => false,
			'value'   => null,
			'problem' => $problem,
		);
	}
}
