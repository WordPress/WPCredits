<?php
/**
 * One way to say what is wrong with a refused form: a summary at the top, and a mark at each field.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The error summary and the field marking every refused form prints (1.123.58).
 *
 * A handler that refuses a form names each refused field with one sentence, `array( field =>
 * sentence )`, never one sentence listing every rule, and keeps what was typed (`WPCPM_Flash::keep()`
 * on a signed-in page, `WPCPM_Form_Stash` on a public form). The form is drawn again from the kept
 * values, and prints through here:
 *
 * - `summary()` at the top of the form: a heading saying how many answers need a change, and one
 *   link per problem to the field, the field's label as the link's words and the sentence after it.
 *   `role="alert"` for a screen reader with scripts off, and `tabindex="-1"` so assets/js/forms.js
 *   can move the focus to it when the page loads (WCAG 3.3.1, 3.3.3 and 4.1.3);
 * - `attrs()` on the control: `aria-invalid="true"` and `aria-describedby` naming the problem's
 *   paragraph before the hint's, so a screen reader reads why first;
 * - `problem()` beside the control: the sentence, in a paragraph whose id `attrs()` names.
 *
 * A field is known by the id of its control, which the summary links to. A problem with no field in
 * the map, a refusal of the whole form, is listed without a link.
 *
 * Every printed value is escaped here, so a caller hands sentences and labels as text.
 */
final class WPCPM_Form_Errors {

	/** What a field's id is followed by in its problem paragraph's id. */
	const SUFFIX = '-problem';

	/**
	 * The classes a summary takes in wp-admin, after `wpcpm-errors`: a notice, and `inline`, which
	 * WordPress's own common.js leaves where it is printed. Without it core moves every notice under
	 * the page's heading on DOM ready, and the summary, moved, loses the focus forms.js gave it
	 * (1.123.58, trial T8).
	 */
	const ADMIN_CLASS = 'notice notice-error inline';

	/**
	 * The id of a field's problem paragraph.
	 *
	 * @param string $field_id The control's id.
	 * @return string
	 */
	public static function problem_id( $field_id ) {
		return (string) $field_id . self::SUFFIX;
	}

	/**
	 * A count as the summary's heading says it: spelled out from one to nine, as the house style
	 * writes a number under ten in prose, and in figures from ten. Capitalized, since it opens the
	 * heading.
	 *
	 * @param int $count How many.
	 * @return string
	 */
	public static function count_words( $count ) {
		$count = (int) $count;
		$words = array(
			1 => __( 'One', 'wpcredits-program-manager' ),
			2 => __( 'Two', 'wpcredits-program-manager' ),
			3 => __( 'Three', 'wpcredits-program-manager' ),
			4 => __( 'Four', 'wpcredits-program-manager' ),
			5 => __( 'Five', 'wpcredits-program-manager' ),
			6 => __( 'Six', 'wpcredits-program-manager' ),
			7 => __( 'Seven', 'wpcredits-program-manager' ),
			8 => __( 'Eight', 'wpcredits-program-manager' ),
			9 => __( 'Nine', 'wpcredits-program-manager' ),
		);

		return isset( $words[ $count ] ) ? $words[ $count ] : number_format_i18n( $count );
	}

	/**
	 * The one sentence a file input says when its form comes back refused: a browser never hands a
	 * chosen file back to a page, so the file is the one answer nothing can keep.
	 *
	 * @return string
	 */
	public static function file_again() {
		return __( 'A chosen file cannot be kept when the form comes back. Choose the file again.', 'wpcredits-program-manager' );
	}

	/**
	 * The summary's heading: "One answer needs a change", "Two answers need a change".
	 *
	 * @param int $count How many problems.
	 * @return string
	 */
	public static function heading( $count ) {
		return sprintf(
			/* translators: %s: how many answers, spelled out under ten, such as Two. */
			_n( '%s answer needs a change', '%s answers need a change', (int) $count, 'wpcredits-program-manager' ),
			self::count_words( $count )
		);
	}

	/**
	 * The summary a refused form opens with, or '' when nothing was refused.
	 *
	 * Listed in the order of `$fields`, the form's own order, then any problem the map does not
	 * name, in the order given.
	 *
	 * @param array<string, string> $problems Field => the sentence saying why it was refused.
	 * @param array                 $fields   Field => `array( 'id' => the control's id, 'label' =>
	 *                                        its label as text )`, in the form's order.
	 * @param array                 $args     Optional: `level`, the heading's level, 2 to 4 (2);
	 *                                        `class`, classes after `wpcpm-errors` (''); `heading`,
	 *                                        words in place of `heading()` ('').
	 * @return string
	 */
	public static function summary( array $problems, array $fields, array $args = array() ) {
		$problems = array_filter(
			$problems,
			static function ( $sentence ) {
				return is_string( $sentence ) && '' !== trim( $sentence );
			}
		);

		if ( array() === $problems ) {
			return '';
		}

		$args  = array_merge(
			array(
				'level'   => 2,
				'class'   => '',
				'heading' => '',
			),
			$args
		);
		$level = max( 2, min( 4, (int) $args['level'] ) );
		$items = array();

		foreach ( $fields as $field => $about ) {
			if ( ! isset( $problems[ $field ] ) ) {
				continue;
			}

			$id    = is_array( $about ) && isset( $about['id'] ) ? (string) $about['id'] : '';
			$label = is_array( $about ) && isset( $about['label'] ) ? (string) $about['label'] : '';

			$items[] = '' !== $id && '' !== $label
				? sprintf(
					/* translators: 1: a link to the field, its label as the words, 2: the sentence saying why it was refused. */
					__( '%1$s: %2$s', 'wpcredits-program-manager' ),
					sprintf( '<a href="#%1$s">%2$s</a>', esc_attr( $id ), esc_html( $label ) ),
					esc_html( $problems[ $field ] )
				)
				: esc_html( $problems[ $field ] );

			unset( $problems[ $field ] );
		}

		foreach ( $problems as $sentence ) {
			$items[] = esc_html( $sentence );
		}

		$class = trim( 'wpcpm-errors ' . (string) $args['class'] );
		$title = '' !== (string) $args['heading'] ? (string) $args['heading'] : self::heading( count( $items ) );

		return sprintf(
			'<div class="%1$s" role="alert" tabindex="-1"><h%2$d class="wpcpm-errors__title">%3$s</h%2$d><ul class="wpcpm-errors__list"><li>%4$s</li></ul></div>',
			esc_attr( $class ),
			$level,
			esc_html( $title ),
			implode( '</li><li>', $items )
		);
	}

	/**
	 * The attributes a control carries: a refused one `aria-invalid="true"` and `aria-describedby`
	 * naming its problem, then its hint; any other its hint alone; '' for neither. With a space in
	 * front, to be printed straight after the control's other attributes.
	 *
	 * @param string $field_id The control's id.
	 * @param string $sentence The sentence it was refused with, or '' when it was not.
	 * @param string $hint_id  The id of the control's hint, or '' for none.
	 * @return string
	 */
	public static function attrs( $field_id, $sentence, $hint_id = '' ) {
		$described = array();

		if ( '' !== trim( (string) $sentence ) ) {
			$described[] = self::problem_id( $field_id );
		}

		if ( '' !== (string) $hint_id ) {
			$described[] = (string) $hint_id;
		}

		$attrs = '' !== trim( (string) $sentence ) ? ' aria-invalid="true"' : '';

		if ( array() !== $described ) {
			$attrs .= ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"';
		}

		return $attrs;
	}

	/**
	 * The sentence a control was refused with, in the paragraph `attrs()` names, or '' when it was
	 * not refused.
	 *
	 * @param string $field_id The control's id.
	 * @param string $sentence The sentence, or ''.
	 * @return string
	 */
	public static function problem( $field_id, $sentence ) {
		if ( '' === trim( (string) $sentence ) ) {
			return '';
		}

		return sprintf(
			'<p class="wpcpm-field__problem" id="%1$s">%2$s</p>',
			esc_attr( self::problem_id( $field_id ) ),
			esc_html( (string) $sentence )
		);
	}
}
