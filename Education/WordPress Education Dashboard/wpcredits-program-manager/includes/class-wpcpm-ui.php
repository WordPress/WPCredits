<?php
/**
 * The surface switch for the shared interface parts, and the parts' sheet and script.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Says which screens draw the shared parts (assets/css/parts.css) and which still draw their own markup.
 *
 * A screen names itself when its render begins, `enter()`, and leaves at the end, `leave()`; the parts'
 * branch of a renderer asks `on()`. `LIVE` lists the surfaces and the wp-admin tabs (`<page slug>/<tab>`)
 * that are on: the Administrator Dashboard since release 1, the Institution Dashboard since its frame's release;
 * every other one is off until a release adds its own,
 * so every other page renders as before.
 */
final class WPCPM_UI {

	/** The surfaces and wp-admin tabs that are on: the Administrator Dashboard (release 1), the Institution Dashboard. */
	const LIVE = array( 'administrator', 'institution' );

	/** The surfaces outside wp-admin; a wp-admin tab is named `<page slug>/<tab>`. */
	const SURFACES = array( 'administrator', 'sponsor', 'institution', 'report-cards' );

	/** The sheet's handle. */
	const STYLE = 'wpcpm-parts';

	/** The script's handle. */
	const SCRIPT = 'wpcpm-parts';

	/** The compact Viewing as control's label beside its box, and its box (Task 14): `WPCPM_Dashboards::render_combo()` prints them. */
	const VIEWING_LABEL = 'wpcpm-ui-viewing__label';
	const VIEWING_BOX   = 'wpcpm-ui-viewing__box';

	/**
	 * The surfaces named so far, the innermost last.
	 *
	 * @var string[]
	 */
	private static $stack = array();

	/**
	 * A list the suites put in place of LIVE, or null.
	 *
	 * @var string[]|null
	 */
	private static $live = null;

	/**
	 * Name the surface that is drawing.
	 *
	 * @param string $surface A surface, or a wp-admin tab as `<page slug>/<tab>`.
	 */
	public static function enter( string $surface ): void {
		self::$stack[] = $surface;
	}

	/**
	 * Return to the surface named before the last enter().
	 */
	public static function leave(): void {
		array_pop( self::$stack );
	}

	/**
	 * The surface that is drawing, '' when none was named.
	 *
	 * @return string
	 */
	public static function surface(): string {
		return empty( self::$stack ) ? '' : (string) end( self::$stack );
	}

	/**
	 * Whether the surface that is drawing is on.
	 *
	 * @return bool
	 */
	public static function on(): bool {
		$surface = self::surface();
		return '' !== $surface && in_array( $surface, self::live(), true );
	}

	/**
	 * The surfaces and tabs that are on.
	 *
	 * @return string[]
	 */
	public static function live(): array {
		return null === self::$live ? self::LIVE : self::$live;
	}

	/**
	 * Replace LIVE; for the suites only. Null puts the constant back.
	 *
	 * @param string[]|null $surfaces The surfaces to count as on.
	 */
	public static function set_live( ?array $surfaces ): void {
		self::$live = $surfaces;
	}

	/**
	 * The class a surface's root prints while its switch is on, with its leading space.
	 *
	 * @return string
	 */
	public static function root_class(): string {
		return self::on() ? ' wpcpm-ui-on' : '';
	}

	/**
	 * Register the parts' sheet and script, once each.
	 */
	public static function register_assets(): void {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/parts.css', array(), WPCPM_VERSION );
		}

		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script( self::SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/parts.js', array(), WPCPM_VERSION, true );
		}
	}

	/**
	 * Enqueue the parts' script, registering it first.
	 */
	public static function enqueue_script(): void {
		self::register_assets();
		wp_enqueue_script( self::SCRIPT );
	}

	/**
	 * Which side a part is drawn for: wp-admin or the front end.
	 *
	 * @param array $args The part's arguments; `side` is 'front' or 'admin'.
	 * @return string 'admin' or 'front'.
	 */
	private static function side( array $args ): string {
		if ( isset( $args['side'] ) && in_array( $args['side'], array( 'front', 'admin' ), true ) ) {
			return $args['side'];
		}
		return is_admin() ? 'admin' : 'front';
	}

	/**
	 * A button or a link drawn as one (design 2.1).
	 *
	 * The front end prints the frame's `wpcpm-btn` classes; wp-admin prints WordPress's own `button` and adds
	 * only the danger and quiet modifiers.
	 *
	 * @param array $args label, kind (primary, secondary, danger, quiet, quiet-danger), type (submit or button),
	 *                    href, name, value, form, formaction (the address a submit posts its form to, in place of the
	 *                    form's own; a link and a type="button" print none), confirm, controls, expanded, context, aria_label, hidden, id, side,
	 *                    data (the plugin's own hooks: `array( 'wpcpm-cancel' => true, 'wpcpm-for' => 'done' )`
	 *                    prints `data-wpcpm-cancel data-wpcpm-for="done"`; true prints the bare attribute).
	 *                    A link also takes rel (its words, as `nofollow` or `noopener noreferrer`) and target (`_blank`
	 *                    alone; anything else prints none), printed after its address: a file's download, a Drive folder
	 *                    opened in a tab of its own, as today's links open them (Institution Dashboard, Task 21). Without
	 *                    them, and on a button, the markup is as before.
	 * @return string Escaped markup.
	 */
	public static function button( array $args ): string {
		$kind  = isset( $args['kind'] ) ? (string) $args['kind'] : 'primary';
		$admin = 'admin' === self::side( $args );
		$all   = array(
			'secondary'    => array( 'wpcpm-btn wpcpm-btn--secondary', 'button' ),
			'danger'       => array( 'wpcpm-btn wpcpm-btn--danger', 'button wpcpm-btn--danger' ),
			'quiet'        => array( 'wpcpm-btn wpcpm-btn--quiet', 'button wpcpm-btn--quiet' ),
			'quiet-danger' => array( 'wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger', 'button wpcpm-btn--quiet wpcpm-btn--danger' ),
		);
		$pair  = $all[ $kind ] ?? array( 'wpcpm-btn', 'button button-primary' );
		$class = $admin ? $pair[1] : $pair[0];

		$href = isset( $args['href'] ) ? (string) $args['href'] : '';
		$type = '';
		$out  = '';
		if ( '' !== $href ) {
			$out .= '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '"';
			// How the link opens, as the link it replaces opened (Task 21).
			if ( isset( $args['rel'] ) && 1 === preg_match( '/^[a-z]+( [a-z]+)*\z/', (string) $args['rel'] ) ) {
				$out .= ' rel="' . esc_attr( (string) $args['rel'] ) . '"';
			}
			if ( isset( $args['target'] ) && '_blank' === $args['target'] ) {
				$out .= ' target="_blank"';
			}
		} else {
			$type = isset( $args['type'] ) && 'button' === $args['type'] ? 'button' : 'submit';
			$out .= '<button type="' . $type . '" class="' . esc_attr( $class ) . '"';
		}
		// A name, a value and a form belong to a form control; a link prints its id only.
		foreach ( array(
			'id'    => 'id',
			'name'  => 'name',
			'value' => 'value',
			'form'  => 'form',
		) as $key => $attr ) {
			if ( '' !== $href && 'id' !== $key ) {
				continue;
			}
			if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
				$out .= ' ' . $attr . '="' . esc_attr( (string) $args[ $key ] ) . '"';
			}
		}
		// The address a submit posts its form to: a link and a button that submits nothing have none to change.
		if ( 'submit' === $type && isset( $args['formaction'] ) && '' !== (string) $args['formaction'] ) {
			$out .= ' formaction="' . esc_url( (string) $args['formaction'] ) . '"';
		}
		if ( isset( $args['confirm'] ) && '' !== (string) $args['confirm'] ) {
			$out .= ' data-wpcpm-confirm="' . esc_attr( (string) $args['confirm'] ) . '"';
		}
		if ( isset( $args['controls'] ) && '' !== (string) $args['controls'] ) {
			$out .= ' aria-controls="' . esc_attr( (string) $args['controls'] ) . '" aria-expanded="' . ( ! empty( $args['expanded'] ) ? 'true' : 'false' ) . '"';
		}
		if ( isset( $args['aria_label'] ) && '' !== (string) $args['aria_label'] ) {
			$out .= ' aria-label="' . esc_attr( (string) $args['aria_label'] ) . '"';
		}
		if ( ! empty( $args['hidden'] ) ) {
			$out .= ' hidden';
		}
		$out .= self::data_attrs( $args['data'] ?? array() );
		$out .= '>' . esc_html( (string) ( $args['label'] ?? '' ) );
		if ( isset( $args['context'] ) && '' !== (string) $args['context'] ) {
			$out .= '<span class="screen-reader-text"> ' . esc_html( (string) $args['context'] ) . '</span>';
		}
		return $out . ( '' !== $href ? '</a>' : '</button>' );
	}

	/**
	 * A status tag (design 2.2): red, amber or green; any other tone is neutral.
	 *
	 * @param string $words The words.
	 * @param string $tone  red, amber, green or ''.
	 * @param string $title The tag's tooltip, '' for none (the tag then prints as before): today's words a tag kept from the
	 *                      mark it replaces, such as the People card's "The program writes to this address about the
	 *                      institution." (Institution Dashboard, Task 20).
	 * @return string Escaped markup.
	 */
	public static function tag( string $words, string $tone = '', string $title = '' ): string {
		$class = in_array( $tone, array( 'red', 'amber', 'green' ), true ) ? 'wpcpm-status wpcpm-status--' . $tone : 'wpcpm-status';
		return '<span class="' . $class . '"' . ( '' !== $title ? ' title="' . esc_attr( $title ) . '"' : '' ) . '>' . esc_html( $words ) . '</span>';
	}

	/**
	 * A topic chip (design 2.2).
	 *
	 * @param string $words The words.
	 * @return string Escaped markup.
	 */
	public static function chip( string $words ): string {
		return '<span class="wpcpm-ui-chip">' . esc_html( $words ) . '</span>';
	}

	/**
	 * An in-card heading (design 2.11).
	 *
	 * @param string $text  The words.
	 * @param string $level h2 to h6; anything else is h4.
	 * @param string $id    An id, or ''.
	 * @return string Escaped markup.
	 */
	public static function heading( string $text, string $level = 'h4', string $id = '' ): string {
		$level = in_array( $level, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $level : 'h4';
		$id    = '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '';
		return '<' . $level . ' class="wpcpm-ui-h"' . $id . '>' . esc_html( $text ) . '</' . $level . '>';
	}

	/**
	 * The line above a form that says what it does (design 2.5); muted, the note a card prints under a list it cut
	 * ("N more are waiting").
	 *
	 * @param string $text The words, escaped here.
	 * @param string $tone 'muted' or '' (any other value is '').
	 * @param string $id   An id, or '' (the line then prints as it did without one).
	 * @param string $html The line as markup the caller already escaped (a sentence with its link after its words),
	 *                     printed as given in place of the text; '' for none, and the text is printed as before.
	 * @param string $names A class of the caller's after the part's own (a line its module's print sheet hides; the
	 *                      Institution Dashboard, Task 18), '' for none, and the line is printed as before.
	 * @return string Escaped markup.
	 */
	public static function intro( string $text, string $tone = '', string $id = '', string $html = '', string $names = '' ): string {
		return '<p class="' . esc_attr( 'wpcpm-ui-intro' . ( 'muted' === $tone ? ' wpcpm-ui-intro--muted' : '' ) . ( '' !== trim( $names ) ? ' ' . trim( $names ) : '' ) ) . '"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>' . ( '' !== $html ? $html : esc_html( $text ) ) . '</p>';
	}

	/**
	 * A list of sentences to read, not to tick (an agreement's review checklist): a plain list, each sentence a line of
	 * its own class, so the sheet keys the room between two lines on the line and never on an element.
	 *
	 * @param array $lines   Plain sentences, escaped here; or a line as `array( 'text' => sentence, 'body' => markup )`,
	 *                       its body markup the caller built from the parts and escaped (as actions() takes buttons),
	 *                       printed after the sentence inside its item: a step and the form that carries it out (the
	 *                       Collaboration Agreement's steps; Institution Dashboard, Task 21, fix round 1). A line with no
	 *                       body, or an empty one, prints as a plain line does.
	 * @param bool  $ordered True: the sentences are steps taken in their order, an ordered list numbered by the
	 *                       browser (the Collaboration Agreement's three steps; Institution Dashboard, Task 21); false,
	 *                       or none, prints the list as before.
	 * @return string Escaped markup, or '' for no lines.
	 */
	public static function checklist( array $lines, bool $ordered = false ): string {
		$items = '';
		foreach ( $lines as $line ) {
			$body = '';
			if ( is_array( $line ) ) {
				$body = (string) ( $line['body'] ?? '' );
				$line = (string) ( $line['text'] ?? '' );
			}
			$items .= '<li class="wpcpm-ui-checklist__item">' . esc_html( (string) $line ) . $body . '</li>';
		}
		$list = $ordered ? 'ol' : 'ul';
		return '' === $items ? '' : '<' . $list . ' class="wpcpm-ui-checklist">' . $items . '</' . $list . '>';
	}

	/**
	 * An action row (design 2.5): the buttons in the order given, then the buttons' own consequence.
	 *
	 * @param string[] $buttons     Markup from button(), already escaped.
	 * @param string   $consequence What the button does to others, or ''.
	 * @return string Escaped markup.
	 */
	public static function actions( array $buttons, string $consequence = '' ): string {
		$out = '<div class="wpcpm-ui-actions">' . implode( '', $buttons );
		if ( '' !== $consequence ) {
			$out .= '<p class="wpcpm-ui-consequence">' . esc_html( $consequence ) . '</p>';
		}
		return $out . '</div>';
	}

	/**
	 * A form field (design 2.4): the label over the box, Required or Optional in words, the hint under the
	 * box and tied to it, and the plugin's own refusal markup when the field was refused.
	 *
	 * @param array $args id, name, label (required); type (text, email, url, search, date, time, number,
	 *                    textarea, select; anything else is text; a time is drawn as a date is, 200px), value,
	 *                    options (value to label, for a select), long (a select up to 640px), need (required,
	 *                    optional or ''), hint, rows (a textarea), attrs (minlength, maxlength, min, max, step,
	 *                    placeholder, autocomplete; pattern on a text, email, url or search box only), problem
	 *                    (the refusal sentence, '' for none), required_attr (false: `need` required prints the word
	 *                    alone and no `required` on the box, for a box the handler refuses empty although today's form
	 *                    leaves that to the server, so what the browser refuses stays as it is; the box then carries
	 *                    `aria-required="true"`, so a screen reader hears what the word says; true by default), disabled
	 *                    (true prints `disabled` on the box; false or none prints the box as before).
	 *                    `side` is accepted and not used: the field's markup is the same on both sides.
	 *                    `attrs` may also hold `inputmode` (text, url, email, numeric, decimal, tel, search or none; any
	 *                    other value prints none), printed after the others: a box of text that brings the address keyboard,
	 *                    as today's forms draw a website (Institution Dashboard, Task 19). Without it the box is as before.
	 *                    type `combo`: the one combobox (`WPCPM_Dashboards::render_combo()`, the field the application form
	 *                    draws for a long list) in a field's wrapper, its label and its boxes in the field's classes, the need
	 *                    word after the label, the hint and the refusal under it and tied to both boxes; it also takes
	 *                    options (value to label, in the order to draw them), find, none and count (the field's words);
	 *                    `required_attr` false prints no `required` on its select, as for any box. Task 19.
	 *                    `after`: one link, `array( 'href' => ..., 'label' => ..., 'outside' => bool )`, drawn as the field's
	 *                    last line: a text link in the hint's paragraph, so its first letter is on the box's edge and it
	 *                    stands at a hint's distance (1.123.104: Open the help desk link). An outside link opens in a tab of
	 *                    its own, as the dashboards' other outside links. No address, no line; without it the field is as
	 *                    before.
	 * @return string Escaped markup.
	 */
	public static function field( array $args ): string {
		$id      = (string) ( $args['id'] ?? '' );
		$name    = (string) ( $args['name'] ?? '' );
		$type    = isset( $args['type'] ) ? (string) $args['type'] : 'text';
		$need    = (string) ( $args['need'] ?? '' );
		$hint    = (string) ( $args['hint'] ?? '' );
		$problem = (string) ( $args['problem'] ?? '' );
		$hint_id = '' !== $hint ? $id . '-hint' : '';

		if ( 'combo' === $type && class_exists( 'WPCPM_Dashboards' ) ) {
			return self::combo( $args, $id, $name, $need, $hint, $problem, $hint_id );
		}

		$type  = in_array( $type, array( 'text', 'email', 'url', 'search', 'date', 'time', 'number', 'textarea', 'select' ), true ) ? $type : 'text';
		$value = (string) ( $args['value'] ?? '' );

		$class = 'wpcpm-ui-field__control';
		if ( in_array( $type, array( 'date', 'number', 'textarea', 'select' ), true ) ) {
			$class .= ' wpcpm-ui-field__control--' . $type;
		} elseif ( 'time' === $type ) {
			// A time is as wide as a date (design 2.4), by the date's own class.
			$class .= ' wpcpm-ui-field__control--date';
		}
		if ( 'select' === $type && ! empty( $args['long'] ) ) {
			$class .= ' wpcpm-ui-field__control--long';
		}

		$common = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"';
		if ( 'required' === $need && false !== ( $args['required_attr'] ?? true ) ) {
			$common .= ' required';
		} elseif ( 'required' === $need ) {
			// The word says Required where the browser is not asked to refuse the box empty; a screen reader is told
			// so too (Task 18c).
			$common .= ' aria-required="true"';
		}
		if ( isset( $args['attrs'] ) && is_array( $args['attrs'] ) ) {
			foreach ( array( 'minlength', 'maxlength', 'min', 'max', 'step', 'placeholder', 'autocomplete' ) as $attr ) {
				if ( isset( $args['attrs'][ $attr ] ) && '' !== (string) $args['attrs'][ $attr ] ) {
					$common .= ' ' . $attr . '="' . esc_attr( (string) $args['attrs'][ $attr ] ) . '"';
				}
			}
			// The browser reads a pattern on a box of one line of text alone.
			if ( in_array( $type, array( 'text', 'email', 'url', 'search' ), true ) && isset( $args['attrs']['pattern'] ) && '' !== (string) $args['attrs']['pattern'] ) {
				$common .= ' pattern="' . esc_attr( (string) $args['attrs']['pattern'] ) . '"';
			}
			// The keyboard a phone brings for the box (Task 19); only the browser's own words.
			if ( isset( $args['attrs']['inputmode'] ) && in_array( (string) $args['attrs']['inputmode'], array( 'text', 'url', 'email', 'numeric', 'decimal', 'tel', 'search', 'none' ), true ) ) {
				$common .= ' inputmode="' . (string) $args['attrs']['inputmode'] . '"';
			}
		}
		// A box the form may not use, as today's form draws it (the correction form's Students half while the site cannot tell
		// which entry a change belongs to; Institution Dashboard, Task 16).
		if ( ! empty( $args['disabled'] ) ) {
			$common .= ' disabled';
		}
		$common .= WPCPM_Form_Errors::attrs( $id, $problem, $hint_id );

		if ( 'textarea' === $type ) {
			$rows = isset( $args['rows'] ) ? (int) $args['rows'] : 0;
			// esc_textarea(), not esc_html(): esc_html() leaves an entity as it is, so typing kept with a typed-out
			// "&lt;" in it would come back as "<".
			$control = '<textarea class="' . $class . '"' . $common . ( $rows > 0 ? ' rows="' . $rows . '"' : '' ) . '>' . "\n" . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'select' === $type ) {
			$control = '<select class="' . $class . '"' . $common . '>';
			foreach ( ( isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array() ) as $option => $words ) {
				$control .= '<option value="' . esc_attr( (string) $option ) . '"' . ( (string) $option === $value ? ' selected' : '' ) . '>' . esc_html( (string) $words ) . '</option>';
			}
			$control .= '</select>';
		} else {
			$control = '<input type="' . $type . '" class="' . $class . '"' . $common . ' value="' . esc_attr( $value ) . '">';
		}

		$word = self::need_word( $need );

		$out = '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $args['label'] ?? '' ) ) . self::need_gap( $word ) . '</label>' . $control;
		if ( '' !== $hint ) {
			$out .= '<p class="wpcpm-ui-field__hint" id="' . esc_attr( $hint_id ) . '">' . esc_html( $hint ) . '</p>';
		}
		$after = isset( $args['after'] ) && is_array( $args['after'] ) ? $args['after'] : array();
		if ( '' !== (string) ( $after['href'] ?? '' ) ) {
			$out .= '<p class="wpcpm-ui-field__hint"><a href="' . esc_url( (string) $after['href'] ) . '"'
				. ( ! empty( $after['outside'] ) ? ' rel="noopener noreferrer" target="_blank"' : '' )
				. '>' . esc_html( (string) ( $after['label'] ?? '' ) ) . '</a></p>';
		}
		return $out . WPCPM_Form_Errors::problem( $id, $problem ) . '</div>';
	}

	/**
	 * The need word of a field, a choice group or a file box (design 2.4): "Required" or "Optional" in words, in its
	 * span, or '' for any other need. need_gap() puts it after the label's text.
	 *
	 * @param string $need required, optional or ''.
	 * @return string Escaped markup, or ''.
	 */
	private static function need_word( string $need ): string {
		if ( 'required' === $need ) {
			return '<span class="wpcpm-ui-field__need">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>';
		}
		if ( 'optional' === $need ) {
			return '<span class="wpcpm-ui-field__need">' . esc_html__( 'Optional', 'wpcredits-program-manager' ) . '</span>';
		}
		return '';
	}

	/**
	 * The need word as it follows a label's text: one ordinary space, then the word's span, so a screen reader reads
	 * "Reason Required" and never "ReasonRequired"; the span's margin is the design's 6px less that space (parts.css).
	 * Nothing when there is no word.
	 *
	 * @param string $word The need word's markup, or ''.
	 * @return string
	 */
	private static function need_gap( string $word ): string {
		return '' === $word ? '' : ' ' . $word;
	}

	/**
	 * The one combobox as a field (field()'s type `combo`, Task 19): `WPCPM_Dashboards::render_combo()`, called and not
	 * copied, so its select, its hidden field, its list and its script are the ones every other form draws; given the
	 * field's label class and the select's box classes, the need word after the label's words, and the problem's and the
	 * hint's ids for both boxes, as field() ties a box's. Without the script the select is the field and works alone.
	 *
	 * @param array  $args    field()'s arguments.
	 * @param string $id      The select's id.
	 * @param string $name    The name it posts.
	 * @param string $need    required, optional or ''.
	 * @param string $hint    The hint, or ''.
	 * @param string $problem The refusal sentence, or ''.
	 * @param string $hint_id The hint's id, or ''.
	 * @return string Escaped markup.
	 */
	private static function combo( array $args, string $id, string $name, string $need, string $hint, string $problem, string $hint_id ): string {
		$described = array();
		if ( '' !== trim( $problem ) ) {
			$described[] = WPCPM_Form_Errors::problem_id( $id );
		}
		if ( '' !== $hint_id ) {
			$described[] = $hint_id;
		}

		ob_start();
		WPCPM_Dashboards::render_combo(
			array(
				'id'            => $id,
				'name'          => $name,
				'options'       => isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array(),
				'current'       => $args['value'] ?? '',
				'label'         => (string) ( $args['label'] ?? '' ),
				'find'          => (string) ( $args['find'] ?? '' ),
				'none'          => (string) ( $args['none'] ?? '' ),
				'count'         => (string) ( $args['count'] ?? '' ),
				'need_html'     => self::need_gap( self::need_word( $need ) ),
				'describedby'   => implode( ' ', $described ),
				'invalid'       => '' !== trim( $problem ),
				'required'      => 'required' === $need && false !== ( $args['required_attr'] ?? true ),
				// Required in words with no `required` on the select: a screen reader is told so too, as field() tells it.
				'aria_required' => 'required' === $need && false === ( $args['required_attr'] ?? true ),
				'label_class'   => 'wpcpm-ui-field__label',
				'box_class'     => 'wpcpm-ui-field__control wpcpm-ui-field__control--select',
			)
		);
		$out = '<div class="wpcpm-ui-field wpcpm-ui-field--combo">' . (string) ob_get_clean();
		if ( '' !== $hint ) {
			$out .= '<p class="wpcpm-ui-field__hint" id="' . esc_attr( $hint_id ) . '">' . esc_html( $hint ) . '</p>';
		}
		return $out . WPCPM_Form_Errors::problem( $id, $problem ) . '</div>';
	}

	/**
	 * A choice group, or one checkbox that is its own question (design 2.4, "Choice group"; 3.3).
	 *
	 * A group with a legend is a `fieldset` with no border whose legend is the field's label, Required or Optional in
	 * words after it when `need` says so. One checkbox with no legend is its own field: no `fieldset`, its label the
	 * question. Each option is a `label` holding its input and then its words, the option's own hint under its words
	 * and tied to its input with `aria-describedby`; the input is named by its words alone (`aria-labelledby`) when a
	 * hint follows them, so the hint is said once, as a description.
	 *
	 * @param array $args id (the input's id for one checkbox alone; for a group, each input's id is it and the
	 *                    option's value, `{id}-{value}`), legend, name, type (radio or checkbox; anything else is
	 *                    checkbox), options (value to label, or value to `array( 'label' => ..., 'hint' => ... )`),
	 *                    checked (a value or a list of values), need (required, optional or ''). `required` is printed
	 *                    on a radio group's inputs and on a checkbox alone; a group of checkboxes says it in words only.
	 *                    problem (the refusal sentence of a checkbox alone, tied to it as field() ties a box's: `aria-invalid`,
	 *                    the sentence named before the option's hint, printed under the label, the wrapper then a field's
	 *                    too; '' or none, or a group, prints as before; Institution Dashboard, Task 17).
	 *                    A group also takes hint (the question's help, muted under the legend), group_problem (its refusal
	 *                    sentence, under its options; the fieldset is then a field's wrapper too, for the refusal's look),
	 *                    both named by the fieldset's `aria-describedby` (the sentence first), and columns (true: the options
	 *                    in a grid of columns, a long list such as the application's languages). With none of them (or each
	 *                    '' or false), and for a checkbox alone, it prints as before (Institution Dashboard, Task 19).
	 * @return string Escaped markup.
	 */
	public static function choices( array $args ): string {
		$base    = (string) ( $args['id'] ?? '' );
		$name    = (string) ( $args['name'] ?? '' );
		$type    = isset( $args['type'] ) && 'radio' === $args['type'] ? 'radio' : 'checkbox';
		$legend  = (string) ( $args['legend'] ?? '' );
		$need    = (string) ( $args['need'] ?? '' );
		$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		$checked = array_map(
			static function ( $value ) {
				return (string) $value;
			},
			is_array( $args['checked'] ?? null ) ? $args['checked'] : ( isset( $args['checked'] ) ? array( $args['checked'] ) : array() )
		);
		$alone   = '' === $legend && 'checkbox' === $type && 1 === count( $options );
		$problem = $alone ? (string) ( $args['problem'] ?? '' ) : '';

		$word = self::need_word( $need );

		$items = '';
		foreach ( $options as $value => $option ) {
			$value = (string) $value;
			$words = is_array( $option ) ? (string) ( $option['label'] ?? '' ) : (string) $option;
			$hint  = is_array( $option ) ? (string) ( $option['hint'] ?? '' ) : '';
			$id    = $alone ? $base : self::choice_id( $base, $value );
			$attrs = ' type="' . $type . '" class="wpcpm-ui-choice__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';
			if ( in_array( $value, $checked, true ) ) {
				$attrs .= ' checked';
			}
			if ( 'required' === $need && ( 'radio' === $type || $alone ) ) {
				$attrs .= ' required';
			}
			if ( '' !== $hint ) {
				$attrs .= ' aria-labelledby="' . esc_attr( $id . '-words' ) . '"';
			}
			if ( '' !== trim( $problem ) ) {
				// A checkbox alone, refused: said as a box is, its sentence first, then its hint.
				$attrs .= WPCPM_Form_Errors::attrs( $id, $problem, '' !== $hint ? $id . '-hint' : '' );
			} elseif ( '' !== $hint ) {
				$attrs .= ' aria-describedby="' . esc_attr( $id . '-hint' ) . '"';
			}
			$items .= '<label class="wpcpm-ui-choice" for="' . esc_attr( $id ) . '"><input' . $attrs . '><span class="wpcpm-ui-choice__text">'
				. '<span class="wpcpm-ui-choice__words" id="' . esc_attr( $id . '-words' ) . '">' . esc_html( $words ) . ( $alone ? self::need_gap( $word ) : '' ) . '</span>'
				. ( '' !== $hint ? '<span class="wpcpm-ui-choice__hint" id="' . esc_attr( $id . '-hint' ) . '">' . esc_html( $hint ) . '</span>' : '' )
				. '</span></label>';
		}

		if ( $alone ) {
			// Refused, it is a field's wrapper too, so its sentence takes the field's refusal look (parts.css).
			return '<div class="wpcpm-ui-choices wpcpm-ui-choices--alone' . ( '' !== trim( $problem ) ? ' wpcpm-ui-field' : '' ) . '">' . $items . WPCPM_Form_Errors::problem( $base, $problem ) . '</div>';
		}

		// A group's help and refusal (Task 19), the refusal said first, as a box's are.
		$hint      = (string) ( $args['hint'] ?? '' );
		$refused   = (string) ( $args['group_problem'] ?? '' );
		$columns   = ! empty( $args['columns'] );
		$described = array();
		if ( '' !== trim( $refused ) ) {
			$described[] = WPCPM_Form_Errors::problem_id( $base );
		}
		if ( '' !== $hint ) {
			$described[] = $base . '-hint';
		}

		return '<fieldset class="wpcpm-ui-choices' . ( $columns ? ' wpcpm-ui-choices--columns' : '' ) . ( '' !== trim( $refused ) ? ' wpcpm-ui-field' : '' ) . '"'
			. ( array() !== $described ? ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"' : '' ) . '>'
			. ( '' !== $legend ? '<legend class="wpcpm-ui-choices__legend">' . esc_html( $legend ) . self::need_gap( $word ) . '</legend>' : '' )
			. ( '' !== $hint ? '<p class="wpcpm-ui-field__hint" id="' . esc_attr( $base . '-hint' ) . '">' . esc_html( $hint ) . '</p>' : '' )
			. ( $columns ? '<div class="wpcpm-ui-choices__grid">' . $items . '</div>' : $items )
			. WPCPM_Form_Errors::problem( $base, $refused ) . '</fieldset>';
	}

	/**
	 * The id choices() gives one option of a group: the group's id, a hyphen, and the option's value with anything but
	 * letters, digits, hyphens and underscores left out. Public, so an outcome that leads to a group's first option names
	 * the id the group draws (Institution Dashboard, Task 20).
	 *
	 * @param string $base  The group's id.
	 * @param string $value The option's value.
	 * @return string
	 */
	public static function choice_id( string $base, string $value ): string {
		return $base . '-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
	}

	/**
	 * The file box (design 2.4, "File box"; 3.2, job 3): the field's label, Required or Optional in words after it,
	 * the browser's own file control, then a row holding the secondary button "Choose a file" and the chosen file's
	 * name beside it ("No file chosen" until one is), the hint and the refusal under them as field() prints them.
	 *
	 * The button and the name are printed `hidden`, so with no script the browser's own control shows and works as
	 * today. The parts' script reveals them, clips the control (never `display: none`, so its label, `required`, the
	 * browser's refusal and the upload are the control's as before), opens the control from the button and writes
	 * the chosen name, reading "No file chosen" back from this markup when the choice is cleared. One file: no
	 * `multiple`.
	 *
	 * @param array $args id, name, label (required); accept (the control's, '' for none), need (required, optional
	 *                    or ''; required prints `required` on the control alone), hint, problem (the refusal
	 *                    sentence, '' for none), attrs (data-* attributes for the control; anything else is not
	 *                    printed).
	 * @return string Escaped markup.
	 */
	public static function file( array $args ): string {
		$id      = (string) ( $args['id'] ?? '' );
		$need    = (string) ( $args['need'] ?? '' );
		$accept  = (string) ( $args['accept'] ?? '' );
		$hint    = (string) ( $args['hint'] ?? '' );
		$problem = (string) ( $args['problem'] ?? '' );
		$hint_id = '' !== $hint ? $id . '-hint' : '';

		$control = '<input class="wpcpm-ui-file__input" type="file" id="' . esc_attr( $id ) . '" name="' . esc_attr( (string) ( $args['name'] ?? '' ) ) . '"';
		if ( '' !== $accept ) {
			$control .= ' accept="' . esc_attr( $accept ) . '"';
		}
		if ( 'required' === $need ) {
			$control .= ' required';
		}
		if ( isset( $args['attrs'] ) && is_array( $args['attrs'] ) ) {
			foreach ( $args['attrs'] as $attr => $value ) {
				if ( 1 === preg_match( '/^data-[a-z0-9-]+$/', (string) $attr ) ) {
					$control .= ' ' . $attr . '="' . esc_attr( (string) $value ) . '"';
				}
			}
		}
		$control .= WPCPM_Form_Errors::attrs( $id, $problem, $hint_id ) . '>';

		$word = self::need_word( $need );

		self::enqueue_script();

		$out = '<div class="wpcpm-ui-field wpcpm-ui-file"><label class="wpcpm-ui-field__label" for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $args['label'] ?? '' ) ) . self::need_gap( $word ) . '</label>'
			. $control
			. '<span class="wpcpm-ui-file__row"><button type="button" class="wpcpm-btn wpcpm-btn--secondary wpcpm-ui-file__choose" hidden aria-controls="' . esc_attr( $id ) . '">' . esc_html__( 'Choose a file', 'wpcredits-program-manager' ) . '</button>'
			. '<span class="wpcpm-ui-file__name" aria-live="polite" hidden>' . esc_html__( 'No file chosen', 'wpcredits-program-manager' ) . '</span></span>';
		if ( '' !== $hint ) {
			$out .= '<p class="wpcpm-ui-field__hint" id="' . esc_attr( $hint_id ) . '">' . esc_html( $hint ) . '</p>';
		}
		return $out . WPCPM_Form_Errors::problem( $id, $problem ) . '</div>';
	}

	/**
	 * The Decide area (design 2.6): a heading, one action row holding every decision, and for each decision that
	 * takes a note one panel under the row.
	 *
	 * The server prints everything a decision needs, so the page works with no script: a decision with no note
	 * is its whole form in the row; a decision with a note is an opener in the row, printed `hidden`, and its
	 * panel after the row, printed open, with its form, its fields, the confirming button and a Cancel printed
	 * `hidden`. The parts' script reveals the openers and Cancels and folds the panels, except one marked
	 * `data-wpcpm-open`. Every id the helper prints starts with the area's id; a field's id is the caller's and
	 * must be unique per item (`{field name}-{item id}`); a field's `name` is printed as the caller gave it.
	 *
	 * Refused, and said through `_doing_it_wrong()`: an area whose id is empty or holds anything but letters,
	 * digits, hyphens and underscores (nothing is printed); a decision whose key, after `sanitize_key()`, is
	 * empty or already used in the area (that decision is left out). The row is printed in one order whatever the
	 * caller's: primary, then secondary, then danger (any other kind last), each kind in the order given; the panels
	 * follow their decisions. Only one panel is open: the first decision in that order with `open` keeps it.
	 *
	 * @param array $args id (the area's id, unique per item, as `wpcpm-decide-app-123`), title (default
	 *                    "Decide"), level (the heading's, h4 by default), side, decisions: a list, each with key
	 *                    (unique in the area, normalised by sanitize_key()), label (the opener or the button),
	 *                    kind (primary, secondary, danger), form_attrs (the form's class, method, action, enctype
	 *                    and data-* attributes, printed as given; true prints a bare attribute; no id: the form
	 *                    needs none), hidden (a callable that prints the form's hidden inputs; nonce() gives a
	 *                    nonce field with no id), fields (markup from field(), '' for no note), confirm_label (the
	 *                    act in full on the panel's button, '' keeps label), confirm (the browser question on the
	 *                    submit), submit_name, submit_value, open (the panel holds kept typing or a refusal),
	 *                    panel (the key of another decision with a note whose panel and form this one shares;
	 *                    its own form_attrs, hidden and fields are then not used). In a shared panel each
	 *                    opener and each submit carries `data-wpcpm-for` its decision's key; the script shows
	 *                    only the pressed opener's submit and posts the shown one whatever the browser picks,
	 *                    so a shared panel may hold any field, a one-line box included. Each decision may also give
	 *                    cancel_label (the words of its panel's dismissing button, '' keeps "Cancel"; on a shared panel
	 *                    the owner's alone are used). The area may give context (plain text naming the item, such as an
	 *                    event's title, '' for none): printed as screen-reader text on every opener and on every submit
	 *                    that acts at once, never on a panel's confirming button or its dismissing button.
	 * @return string Escaped markup, or '' when the area's id is refused.
	 */
	public static function decide( array $args ): string {
		$base = (string) ( $args['id'] ?? '' );
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+\z/', $base ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'A Decide area needs an id of letters, digits, hyphens and underscores, unique per item.', 'wpcredits-program-manager' ), '1.123.92' );
			return '';
		}
		$title   = isset( $args['title'] ) ? (string) $args['title'] : __( 'Decide', 'wpcredits-program-manager' );
		$side    = self::side( $args );
		$context = (string) ( $args['context'] ?? '' );

		// The decisions, each key normalised; one with no key or a key already used is left out.
		$decisions = array();
		$used      = array();
		foreach ( ( isset( $args['decisions'] ) && is_array( $args['decisions'] ) ? $args['decisions'] : array() ) as $decision ) {
			if ( ! is_array( $decision ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $decision['key'] ?? '' ) );
			if ( '' === $key || isset( $used[ $key ] ) ) {
				_doing_it_wrong( __METHOD__, esc_html__( 'Each decision in a Decide area needs a key of its own.', 'wpcredits-program-manager' ), '1.123.92' );
				continue;
			}
			$used[ $key ]      = true;
			$decision['key']   = $key;
			$decision['panel'] = sanitize_key( (string) ( $decision['panel'] ?? '' ) );
			$decisions[]       = $decision;
		}

		// One order for every row (design 2.5, 2.6): the primary first, then the secondary, then the red ones, each kind
		// in the order the caller gave it. The panels follow their decisions, so they take the same order.
		$rank = array(
			'primary'   => 0,
			'secondary' => 1,
			'danger'    => 2,
		);
		foreach ( $decisions as $i => $decision ) {
			$decisions[ $i ]['_at'] = array( $rank[ (string) ( $decision['kind'] ?? 'primary' ) ] ?? 3, $i );
		}
		usort(
			$decisions,
			static function ( $a, $b ) {
				return $a['_at'] <=> $b['_at'];
			}
		);

		// Who owns a panel: a decision with a note that shares no other's, kept by its index. A decision naming
		// an owner shares that owner's panel and form; one naming anything else keeps its own (a panel if it has
		// a note, else the row). The panel's form is always its owner's, whatever the order of the list.
		$owner_at = array();
		foreach ( $decisions as $i => $decision ) {
			$key = $decision['key'];
			if ( '' !== (string) ( $decision['fields'] ?? '' ) && ( '' === $decision['panel'] || $decision['panel'] === $key ) ) {
				$owner_at[ $key ] = $i;
			}
		}
		$panel_of = array();
		foreach ( $decisions as $i => $decision ) {
			$key = $decision['key'];
			if ( isset( $owner_at[ $key ] ) ) {
				$panel_of[ $i ] = $key;
			} elseif ( '' !== $decision['panel'] && isset( $owner_at[ $decision['panel'] ] ) ) {
				$panel_of[ $i ] = $decision['panel'];
			} elseif ( '' !== (string) ( $decision['fields'] ?? '' ) ) {
				$owner_at[ $key ] = $i;
				$panel_of[ $i ]   = $key;
			}
		}
		// One panel open at most: the first listed decision with a panel and `open` keeps it.
		$opened = false;
		foreach ( $decisions as $i => $decision ) {
			$decisions[ $i ]['open'] = ! $opened && isset( $panel_of[ $i ] ) && ! empty( $decision['open'] );
			$opened                  = $opened || $decisions[ $i ]['open'];
		}
		$owners = array_fill_keys( array_keys( $owner_at ), array() );
		foreach ( $panel_of as $i => $owner ) {
			$owners[ $owner ][] = $i;
		}

		$submit = function ( array $decision, bool $in_panel, bool $shared ) use ( $side, $context ): string {
			$label = (string) ( $decision['label'] ?? '' );
			if ( $in_panel && '' !== (string) ( $decision['confirm_label'] ?? '' ) ) {
				$label = (string) $decision['confirm_label'];
			}
			return self::button(
				array(
					'label'   => $label,
					'kind'    => (string) ( $decision['kind'] ?? 'primary' ),
					'name'    => (string) ( $decision['submit_name'] ?? '' ),
					'value'   => (string) ( $decision['submit_value'] ?? '' ),
					'confirm' => (string) ( $decision['confirm'] ?? '' ),
					'data'    => $shared ? array( 'wpcpm-for' => (string) ( $decision['key'] ?? '' ) ) : array(),
					// The item is named on a submit in the row alone: a panel's buttons sit under its opener.
					'context' => $in_panel ? '' : $context,
					'side'    => $side,
				)
			);
		};

		$row = '';
		foreach ( $decisions as $i => $decision ) {
			if ( ! isset( $panel_of[ $i ] ) ) {
				$row .= self::form( $decision, $submit( $decision, false, false ) );
				continue;
			}
			$row .= self::button(
				array(
					'label'    => (string) ( $decision['label'] ?? '' ),
					'kind'     => (string) ( $decision['kind'] ?? 'primary' ),
					'type'     => 'button',
					'controls' => $base . '-' . $panel_of[ $i ] . '-panel',
					'expanded' => ! empty( $decision['open'] ),
					'hidden'   => true,
					// An opener of a shared panel names its decision, as its submit does, so the script pairs them.
					'data'     => count( $owners[ $panel_of[ $i ] ] ) > 1 ? array( 'wpcpm-for' => (string) $decision['key'] ) : array(),
					'context'  => $context,
					'side'     => $side,
				)
			);
		}

		$panels = '';
		foreach ( $owners as $key => $members ) {
			$owner   = $decisions[ $owner_at[ $key ] ];
			$shared  = count( $members ) > 1;
			$open    = false;
			$buttons = array();
			foreach ( $members as $i ) {
				$open      = $open || ! empty( $decisions[ $i ]['open'] );
				$buttons[] = $submit( $decisions[ $i ], true, $shared );
			}
			$buttons[] = self::button(
				array(
					'label'  => '' !== (string) ( $owner['cancel_label'] ?? '' ) ? (string) $owner['cancel_label'] : __( 'Cancel', 'wpcredits-program-manager' ),
					'kind'   => 'quiet',
					'type'   => 'button',
					'hidden' => true,
					'data'   => array( 'wpcpm-cancel' => true ),
					'side'   => $side,
				)
			);

			$body    = (string) $owner['fields'] . self::actions( $buttons );
			$panels .= '<div class="wpcpm-ui-panel" id="' . esc_attr( $base . '-' . $key . '-panel' ) . '" data-wpcpm-panel' . ( $open ? ' data-wpcpm-open' : '' ) . '>'
				. self::form( $owner, $body ) . '</div>';
		}

		if ( ! empty( $owners ) ) {
			self::enqueue_script();
		}

		return '<div class="wpcpm-ui-decide" id="' . esc_attr( $base ) . '">' . self::heading( $title, (string) ( $args['level'] ?? 'h4' ) )
			. '<div class="wpcpm-ui-actions">' . $row . '</div>' . $panels . '</div>';
	}

	/**
	 * One decision's form: its attributes as given, its hidden inputs, then the markup after them.
	 *
	 * @param array  $decision A decision as decide() takes it.
	 * @param string $inner    Escaped markup: the fields and the buttons.
	 * @return string Markup; the hidden inputs are the caller's callable's, printed as it prints them.
	 */
	private static function form( array $decision, string $inner ): string {
		$attrs = '';
		foreach ( ( isset( $decision['form_attrs'] ) && is_array( $decision['form_attrs'] ) ? $decision['form_attrs'] : array() ) as $name => $value ) {
			$name = (string) $name;
			// No id: the area's ids all start with the area's own, and a form needs none.
			if ( 1 !== preg_match( '/^(class|method|action|enctype|data-[a-z0-9-]+)\z/', $name ) || false === $value || null === $value ) {
				continue;
			}
			if ( true === $value ) {
				$attrs .= ' ' . $name;
			} else {
				$attrs .= ' ' . $name . '="' . ( 'action' === $name ? esc_url( (string) $value ) : esc_attr( (string) $value ) ) . '"';
			}
		}

		$hidden = '';
		if ( isset( $decision['hidden'] ) && is_callable( $decision['hidden'] ) ) {
			ob_start();
			try {
				call_user_func( $decision['hidden'] );
			} finally {
				// A callable that throws still gives the buffer back; the exception travels on.
				$hidden = (string) ob_get_clean();
			}
		}
		return '<form' . $attrs . '>' . $hidden . $inner . '</form>';
	}

	/**
	 * A form's nonce field and, when asked, its referer field, with no id attribute.
	 *
	 * WordPress's own `wp_nonce_field()` prints `id` equal to the field's name, so two forms in one Decide area
	 * (a row form and a panel form) would repeat that id on the page. This prints the same name and value
	 * without it; `wp_referer_field()` prints no id.
	 *
	 * @param string $action  The nonce's action.
	 * @param string $name    The field's name.
	 * @param bool   $referer Whether to add the referer field.
	 * @return string Escaped markup.
	 */
	public static function nonce( string $action, string $name = '_wpnonce', bool $referer = true ): string {
		$out = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
		return $out . ( $referer ? (string) wp_referer_field( false ) : '' );
	}

	/**
	 * The plugin's own data attributes on a control.
	 *
	 * @param mixed $data Name (without `data-`) to value; true prints a bare attribute, false or null none.
	 * @return string Escaped attributes, each with its leading space.
	 */
	private static function data_attrs( $data ): string {
		$out = '';
		foreach ( ( is_array( $data ) ? $data : array() ) as $name => $value ) {
			$name = (string) $name;
			if ( 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $name ) || false === $value || null === $value ) {
				continue;
			}
			$out .= ' data-' . $name . ( true === $value ? '' : '="' . esc_attr( (string) $value ) . '"' );
		}
		return $out;
	}

	/**
	 * A tag from an array of `words` and `tone`, and its `title` when it has one (Task 20), or '' when there are no words.
	 *
	 * @param mixed $tag An array with words and tone, or anything else for none.
	 * @return string Escaped markup.
	 */
	private static function tag_from( $tag ): string {
		if ( ! is_array( $tag ) || '' === (string) ( $tag['words'] ?? '' ) ) {
			return '';
		}
		return self::tag( (string) $tag['words'], (string) ( $tag['tone'] ?? '' ), (string) ( $tag['title'] ?? '' ) );
	}

	/**
	 * The facts of an item or a row: each a plain string (escaped) or `array( 'html' => ... )` (printed as given).
	 *
	 * @param mixed $facts The list.
	 * @return string Escaped markup, one span per fact.
	 */
	private static function facts_html( $facts ): string {
		$out = '';
		foreach ( ( is_array( $facts ) ? $facts : array() ) as $fact ) {
			$html = is_array( $fact ) ? (string) ( $fact['html'] ?? '' ) : esc_html( (string) $fact );
			if ( '' !== $html ) {
				$out .= '<span class="wpcpm-ui-item__fact">' . $html . '</span>';
			}
		}
		return $out;
	}

	/**
	 * The inner markup of a fold's head (design 2.3), for both sizes.
	 *
	 * A card head: the title and the phrase, the end group (tag, cue, help slot) and one chevron, or the
	 * external-link icon when the row leaves. An inner head: the title with an optional second piece, and the
	 * chevron.
	 *
	 * @param array $args size (card or inner), title, title_id, phrase, count (printed only when there is no
	 *                    phrase), tag (words, tone), cue, help_slot (bool), leaves (bool), second.
	 * @return string Escaped markup.
	 */
	public static function fold_head( array $args ): string {
		$title = esc_html( (string) ( $args['title'] ?? '' ) );
		$icon  = ! empty( $args['leaves'] )
			? '<span class="wpcpm-ui-fold__ext" aria-hidden="true">' . WPCPM_Icons::svg( 'external', 20 ) . '</span>'
			: '<span class="wpcpm-ui-fold__chev" aria-hidden="true">' . WPCPM_Icons::ui( 'chevron', 20 ) . '</span>';

		if ( isset( $args['size'] ) && 'inner' === $args['size'] ) {
			return self::inner_head( $title, esc_html( (string) ( $args['second'] ?? '' ) ), $icon );
		}

		$title_id = (string) ( $args['title_id'] ?? '' );
		$phrase   = (string) ( $args['phrase'] ?? '' );
		// The heading is a direct child of the summary, and the phrase follows it as a sibling.
		$lead = '<h3 class="wpcpm-ui-fold__title"' . ( '' !== $title_id ? ' id="' . esc_attr( $title_id ) . '"' : '' ) . '>' . $title . '</h3>';
		if ( '' !== $phrase ) {
			$lead .= '<span class="wpcpm-ui-fold__phrase">' . esc_html( $phrase ) . '</span>';
		} elseif ( isset( $args['count'] ) && '' !== (string) $args['count'] ) {
			$lead .= '<span class="wpcpm-ui-count">' . esc_html( (string) $args['count'] ) . '</span>';
		}

		$end = self::tag_from( $args['tag'] ?? null );
		if ( isset( $args['cue'] ) && '' !== (string) $args['cue'] ) {
			$end .= '<span class="wpcpm-ui-fold__cue" aria-hidden="true">' . esc_html( (string) $args['cue'] ) . '</span>';
		}
		if ( ! empty( $args['help_slot'] ) ) {
			$end .= '<span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span>';
		}
		return $lead . '<span class="wpcpm-ui-fold__end">' . $end . '</span>' . $icon;
	}

	/**
	 * An inner head's markup (design 2.3), the one builder of it: the portrait's box when there is one, the text column (the
	 * title, and the second piece when there is one), the tag at the end when there is one, then the icon. The sheet
	 * places the second piece: in the one-row form of a head with no portrait on the title's line, 12px after it, so the
	 * head is 44px, and under it where the line is too short; in the stacked form, and beside a portrait, under the title,
	 * 2px apart (Gate B round 2, F1). Every argument is printed as given: the callers escape it.
	 *
	 * @param string $title    The title, escaped.
	 * @param string $second   The second piece, escaped, or ''.
	 * @param string $icon     The chevron's (or the leaving icon's) markup.
	 * @param string $portrait The portrait's markup, escaped, or ''.
	 * @param string $tag      tag()'s markup, or ''.
	 * @return string Markup.
	 */
	private static function inner_head( string $title, string $second, string $icon, string $portrait = '', string $tag = '' ): string {
		return ( '' !== $portrait ? '<span class="wpcpm-ui-fold__portrait">' . $portrait . '</span>' : '' )
			. '<span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">' . $title . '</span>'
			. ( '' !== $second ? '<span class="wpcpm-ui-fold__second">' . $second . '</span>' : '' )
			. '</span>' . ( '' !== $tag ? '<span class="wpcpm-ui-fold__end">' . $tag . '</span>' : '' ) . $icon;
	}

	/**
	 * The initials a portrait's box shows for a person with no photo: the first character of the name as a reader sees
	 * it (a grapheme cluster: a letter and its combining marks together), or '' for no name.
	 *
	 * @param string $name The name.
	 * @return string Plain text, not escaped.
	 */
	public static function initials( string $name ): string {
		// A name that starts with a combining mark or a zero-width joiner (a typing slip, a pasted name) would give a lone
		// mark as its first character: they are skipped, with any space after them (Institution Dashboard, Task 16).
		$name = trim( (string) preg_replace( '/^[\p{M}\x{200D}]+/u', '', trim( $name ) ) );
		if ( '' === $name ) {
			return '';
		}
		if ( function_exists( 'grapheme_substr' ) ) {
			$first = grapheme_substr( $name, 0, 1 );
			if ( is_string( $first ) && '' !== $first ) {
				return $first;
			}
		}
		if ( 1 === preg_match( '/^\X/u', $name, $m ) ) {
			return $m[0];
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
	}

	/**
	 * Open an inner fold (design 2.3); inner_close() closes it.
	 *
	 * @param array $args Plain text, escaped here: title, second, initials (drawn in the portrait's box when there is no
	 *                    portrait; WPCPM_UI::initials() gives them from a name). Printed as given, so the caller must
	 *                    escape them: portrait (a 44px photo's markup, before the text column), title_html and
	 *                    second_html (in place of title and second: a name as a link through breakable(), dates whole),
	 *                    tag (tag()'s markup, at the head's end). Also open (bool), id and data (the plugin's own data
	 *                    attributes on the disclosure, as button() takes them: the Student Report Card's fetch hook,
	 *                    Task 16). With none of portrait, initials, title_html, second_html, tag and data (or each
	 *                    empty), the fold prints exactly what it printed before (Institution Dashboard, Task 15).
	 * @return string Escaped markup, given the caller escaped the markup it passed.
	 */
	public static function inner_open( array $args ): string {
		$id       = (string) ( $args['id'] ?? '' );
		$portrait = (string) ( $args['portrait'] ?? '' );
		$initials = (string) ( $args['initials'] ?? '' );
		if ( '' === $portrait && '' !== $initials ) {
			$portrait = '<span class="wpcpm-ui-fold__initials" aria-hidden="true">' . esc_html( $initials ) . '</span>';
		}
		$title_html  = (string) ( $args['title_html'] ?? '' );
		$second_html = (string) ( $args['second_html'] ?? '' );

		return '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner' . ( '' !== $portrait ? ' wpcpm-ui-fold--portrait' : '' ) . '"' . ( ! empty( $args['open'] ) ? ' open' : '' ) . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . self::data_attrs( $args['data'] ?? array() ) . '>'
			. '<summary class="wpcpm-ui-fold__head">' . self::inner_head(
				'' !== $title_html ? $title_html : esc_html( (string) ( $args['title'] ?? '' ) ),
				'' !== $second_html ? $second_html : esc_html( (string) ( $args['second'] ?? '' ) ),
				'<span class="wpcpm-ui-fold__chev" aria-hidden="true">' . WPCPM_Icons::ui( 'chevron', 20 ) . '</span>',
				$portrait,
				(string) ( $args['tag'] ?? '' )
			) . '</summary><div class="wpcpm-ui-fold__body">';
	}

	/**
	 * A card fold's foot (design 2.3): the line a card's body ends with, the way to the card's home, after a hairline.
	 *
	 * @param string $href    The address.
	 * @param string $label   The link's words.
	 * @param string $context Words that follow the label inside the link for a screen reader alone, '' for none; the
	 *                        space before them sits inside the hidden span, so the line reads the same to everyone who
	 *                        sees it.
	 * @return string Escaped markup.
	 */
	public static function fold_foot( string $href, string $label, string $context = '' ): string {
		$context = trim( $context );
		return '<p class="wpcpm-ui-fold__foot"><a href="' . esc_url( $href ) . '">' . esc_html( $label )
			. ( '' === $context ? '' : '<span class="screen-reader-text"> ' . esc_html( $context ) . '</span>' ) . '</a></p>';
	}

	/**
	 * A card's help link ("How to do this", `WPCPM_Guide_Jobs::link()`'s markup) as a fold prints it: the part's own
	 * class added after the link's, so the part's look decides by weight wherever the old sheet loads (task V1,
	 * ruling 3). Anything that does not start as the guide's link is given back as it came.
	 *
	 * @param string $link The link's markup, escaped.
	 * @return string Escaped markup.
	 */
	public static function help_link( string $link ): string {
		return (string) preg_replace( '/^<a class="wpcpm-job-link"/', '<a class="wpcpm-job-link wpcpm-ui-fold__help-link"', $link, 1 );
	}

	/**
	 * Close an inner fold.
	 *
	 * @return string Markup.
	 */
	public static function inner_close(): string {
		return '</div></details>';
	}

	/**
	 * Open a run of inner folds as a list (Task 28, W6): a list whose items are the folds, so a screen reader hears how
	 * many there are and moves from one to the next, as a list of people on the live page is heard. Each fold sits in an
	 * item, `inner_item()` before it and `inner_item_close()` after; `inner_list_close()` closes the list. `role="list"`:
	 * the list is drawn with no bullets, and Safari with VoiceOver reads a list styled so as no list at all.
	 *
	 * The sheet holds what the one list drawn today needs, a list that is the last thing in its block, after a paragraph or
	 * a heading: its folds' hairlines shared, no gap between them, the block's 16px above it. Four rules of a bare run have
	 * no twin for the list yet and are owed when a list is first drawn beside one of them: a fold after a table and after
	 * an item, and a run followed by a Decide row or by a fold's foot (`parts.css`).
	 *
	 * @return string Markup.
	 */
	public static function inner_list(): string {
		return '<ul class="wpcpm-ui-folds" role="list">';
	}

	/**
	 * Open one item of a run of inner folds as a list.
	 *
	 * @return string Markup.
	 */
	public static function inner_item(): string {
		return '<li class="wpcpm-ui-folds__item">';
	}

	/**
	 * Close one item of a run of inner folds as a list.
	 *
	 * @return string Markup.
	 */
	public static function inner_item_close(): string {
		return '</li>';
	}

	/**
	 * Close a run of inner folds as a list.
	 *
	 * @return string Markup.
	 */
	public static function inner_list_close(): string {
		return '</ul>';
	}

	/**
	 * An item header (design 2.10): the name, its reference, then the tag and the facts.
	 *
	 * @param array $args name, href, mark (already escaped), ref, tag (words, tone), facts (strings, or
	 *                    `array( 'html' => ... )` for a link already escaped), level (h2 to h6; h4 by default),
	 *                    name_html (a name the caller built and escaped, such as a sentence with a linked name
	 *                    in it, printed as given in place of name, href and mark; '' for none), id (the title's
	 *                    id, '' for none), media (a photo's markup the caller built and escaped, drawn in a 44px box
	 *                    before the name and the facts; '' for none), initials (plain text, escaped here, drawn in that
	 *                    box when there is no media; WPCPM_UI::initials() gives them from a name). With neither media nor
	 *                    initials (or each ''), the header prints exactly what it printed before (Institution Dashboard,
	 *                    Task 16).
	 * @return string Escaped markup.
	 */
	public static function item_header( array $args ): string {
		$level = isset( $args['level'] ) && in_array( $args['level'], array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $args['level'] : 'h4';
		$name  = esc_html( (string) ( $args['name'] ?? '' ) );
		$href  = (string) ( $args['href'] ?? '' );
		$ref   = (string) ( $args['ref'] ?? '' );
		$built = (string) ( $args['name_html'] ?? '' );
		$id    = (string) ( $args['id'] ?? '' );
		$out   = '<' . $level . ' class="wpcpm-ui-item__title"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>'
			. ( '' !== $built ? $built : ( '' !== $href ? '<a href="' . esc_url( $href ) . '">' . $name . '</a>' : $name ) . (string) ( $args['mark'] ?? '' ) )
			. ( '' !== $ref ? '<code class="wpcpm-ui-item__ref">' . esc_html( $ref ) . '</code>' : '' )
			. '</' . $level . '>';
		// The facts sit in one run that the sheet shifts and clips, so no middot starts a line (design 2.10).
		$facts = self::facts_html( $args['facts'] ?? array() );
		$line  = self::tag_from( $args['tag'] ?? null ) . ( '' !== $facts ? '<span class="wpcpm-ui-item__run">' . $facts . '</span>' : '' );
		$out  .= '' !== $line ? '<p class="wpcpm-ui-item__facts">' . $line . '</p>' : '';

		// A person's photo, or the first letter of their name when there is none, in a box before the name and its facts
		// (Institution Dashboard, Task 16: a student's card); with neither, the header is what it was.
		$media    = (string) ( $args['media'] ?? '' );
		$initials = (string) ( $args['initials'] ?? '' );
		if ( '' === $media && '' !== $initials ) {
			$media = '<span class="wpcpm-ui-item__initials" aria-hidden="true">' . esc_html( $initials ) . '</span>';
		}
		if ( '' === $media ) {
			return $out;
		}
		return '<div class="wpcpm-ui-item__head"><span class="wpcpm-ui-item__media">' . $media . '</span><div class="wpcpm-ui-item__lead">' . $out . '</div></div>';
	}

	/**
	 * An item's line of facts on its own, for a block that is no item (the Institution Dashboard's head, its release's
	 * Task 6): the line item_header() prints under a title, with no tag, and a class of the caller's beside the part's.
	 * Each fact draws its own dot, so a missing one leaves no separator and no dot starts a line (design 2.10), at
	 * every width.
	 *
	 * @param array  $facts As item_header()'s: plain strings (escaped) or `array( 'html' => ... )` (printed as given).
	 * @param string $names The caller's own class for the line, or ''.
	 * @return string Escaped markup, or '' for no fact.
	 */
	public static function facts_line( array $facts, string $names = '' ): string {
		$html = self::facts_html( $facts );
		return '' !== $html ? '<p class="' . esc_attr( trim( 'wpcpm-ui-item__facts ' . $names ) ) . '"><span class="wpcpm-ui-item__run">' . $html . '</span></p>' : '';
	}

	/**
	 * An item's article on the parts (design 2.10): the names it is known by, kept for its anchors and scripts, then
	 * the part's own class, which draws the line and the room between two items of one card.
	 *
	 * @param string $names The article's own classes (its type class, as `wpcpm-request wpcpm-request--details`), in
	 *                      their order.
	 * @param string $id    Its id, or '' for none.
	 * @return string Escaped markup, the article's opening tag; the caller closes it.
	 */
	public static function item_open( string $names, string $id = '' ): string {
		return '<article class="' . esc_attr( trim( $names . ' wpcpm-ui-item' ) ) . '"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
	}

	/**
	 * A note a person typed (an application's notes, a request's note from the school, an event's description),
	 * kept with its line breaks: one paragraph, the text escaped.
	 *
	 * @param string $text The note, as typed.
	 * @param string $tag  'blockquote' for words quoted from a person (printed as a quotation, the note's look); 'p', or
	 *                     anything else, prints the paragraph as before.
	 * @return string Escaped markup.
	 */
	public static function note( string $text, string $tag = 'p' ): string {
		// A person's words quoted (a student's released answer; the Institution Dashboard, Task 18) are a quotation, in
		// the note's look; anything else is the paragraph it always was.
		$tag = 'blockquote' === $tag ? 'blockquote' : 'p';
		return '<' . $tag . ' class="wpcpm-ui-note">' . esc_html( $text ) . '</' . $tag . '>';
	}

	/**
	 * Open a table (design 2.7): the colgroup when a column has a width, and the header (a key and value table
	 * has none: its label column is made of row headers the caller prints).
	 *
	 * @param array[] $columns Each: key, label ('' for an action column), kind (text, num, date, short,
	 *                         action), width ('' for auto).
	 * @param array   $args    keyvalue (bool), caption, labelledby, end (bool: the table ends its card although it is not
	 *                         the body's last child, as a table inside the card's one form is; its last row then draws no
	 *                         hairline of its own, as a table that is the body's last child draws none), wide (bool: a
	 *                         table of seven columns or more, which the sheet stacks as on a phone while the dashboard's
	 *                         main area is too narrow for it, under 880px; or 936: such a table that needs about 936px of
	 *                         that area, which stacks under 936px too, Programs running; Task 18d), fixed (bool: a table of a family, design 2.7, whose columns
	 *                         are laid out by the widths its columns give and not by what the cells hold, so the columns
	 *                         of the family's tables start at the same places; task V1, ruling 14). A table that is not a
	 *                         key and value table also says how many pairs a stacked row holds, its row header, its
	 *                         action and a joining cell aside (`wpcpm-ui-table--pairs-N`, at most 9), for the sheet's
	 *                         balanced lines (Task 18f). A column may say `joins` => true: when the table is stacked
	 *                         its cell joins the row's header line, at its end, without its label (a status tag that
	 *                         speaks for itself; the cohort month table's Outlook).
	 * @return string Escaped markup, up to the opening of the body; table_close() ends it.
	 */
	public static function table_open( array $columns, array $args = array() ): string {
		// How many label and value pairs a stacked row holds, so the sheet can set them in balanced lines: its row header,
		// its action (a line of its own) and a cell that joins the header line aside (Task 18f, fix rounds 2 and 3); a key
		// and value table stacks label over value and says nothing.
		$pairs = 0;
		foreach ( $columns as $column ) {
			$pairs += empty( $column['header'] ) && 'action' !== ( $column['kind'] ?? '' ) && empty( $column['joins'] ) ? 1 : 0;
		}
		$pairs = empty( $args['keyvalue'] ) && $pairs > 0 ? ' wpcpm-ui-table--pairs-' . min( 9, $pairs ) : '';
		$out   = '<table class="wpcpm-ui-table' . ( ! empty( $args['keyvalue'] ) ? ' wpcpm-ui-table--keyvalue' : '' ) . ( ! empty( $args['end'] ) ? ' wpcpm-ui-table--end' : '' ) . ( ! empty( $args['wide'] ) ? ' wpcpm-ui-table--wide' : '' ) . ( 936 === ( $args['wide'] ?? false ) ? ' wpcpm-ui-table--wide-936' : '' ) . ( ! empty( $args['fixed'] ) ? ' wpcpm-ui-table--fixed' : '' ) . $pairs . '" role="table"';
		if ( isset( $args['labelledby'] ) && '' !== (string) $args['labelledby'] ) {
			$out .= ' aria-labelledby="' . esc_attr( (string) $args['labelledby'] ) . '"';
		}
		$out .= '>';
		if ( isset( $args['caption'] ) && '' !== (string) $args['caption'] ) {
			$out .= '<caption class="screen-reader-text">' . esc_html( (string) $args['caption'] ) . '</caption>';
		}

		$widths = '';
		$any    = false;
		foreach ( $columns as $column ) {
			$width   = preg_replace( '/[^0-9a-z.%]/i', '', (string) ( $column['width'] ?? '' ) );
			$any     = $any || '' !== $width;
			$widths .= '' !== $width ? '<col style="width:' . esc_attr( $width ) . '">' : '<col>';
		}
		if ( $any ) {
			$out .= '<colgroup>' . $widths . '</colgroup>';
		}

		if ( ! empty( $args['keyvalue'] ) ) {
			return $out . '<tbody role="rowgroup">';
		}

		$out .= '<thead role="rowgroup"><tr role="row">';
		foreach ( $columns as $column ) {
			$label = (string) ( $column['label'] ?? '' );
			$out  .= '<th scope="col" role="columnheader" class="wpcpm-ui-cell--' . esc_attr( (string) ( $column['kind'] ?? 'text' ) ) . '">'
				. ( '' !== $label ? esc_html( $label ) : '<span class="screen-reader-text">' . esc_html__( 'Action', 'wpcredits-program-manager' ) . '</span>' ) . '</th>';
		}
		return $out . '</tr></thead><tbody role="rowgroup">';
	}

	/**
	 * One table cell (design 2.7), with its column's name for the phone layout.
	 *
	 * @param array  $column A column as given to table_open(); `header` => true prints a row header (`th`)
	 *                       for the label cell of a key and value table, with the header's look; `header` => 'row'
	 *                       prints a wide table's row header, the cell that names its row (a sponsor, a student, a
	 *                       month), with the class `wpcpm-ui-cell--rowhead`, which reads as a body cell.
	 * @param string $html   The cell's content, already escaped.
	 * @param string $kind   '' or 'empty' (an empty value kept in words).
	 * @return string Escaped markup. A body cell holding nothing, or white space alone, is marked blank
	 *                (`wpcpm-ui-cell--blank`), so a stacked table draws neither it nor its column's name (Task 18e).
	 */
	public static function cell( array $column, string $html, string $kind = '' ): string {
		$label  = (string) ( $column['label'] ?? '' );
		$header = $column['header'] ?? false;
		$class  = 'wpcpm-ui-cell--' . esc_attr( (string) ( $column['kind'] ?? 'text' ) ) . ( 'row' === $header ? ' wpcpm-ui-cell--rowhead' : '' ) . ( 'empty' === $kind ? ' wpcpm-ui-cell--empty' : '' ) . ( ! empty( $column['joins'] ) ? ' wpcpm-ui-cell--joins' : '' );
		if ( ! empty( $header ) ) {
			return '<th scope="row" role="rowheader" class="' . $class . '">' . $html . '</th>';
		}
		if ( '' === trim( $html ) ) {
			$class .= ' wpcpm-ui-cell--blank';
		}
		return '<td class="' . $class . '" role="cell"' . ( '' !== $label ? ' data-label="' . esc_attr( $label ) . '"' : '' ) . '>' . $html . '</td>';
	}

	/**
	 * A cell's second line (design 2.7): words under the cell's value, on a line of their own, muted, wrapping even in
	 * a number cell. Put it in the cell's html after the value. One ordinary space comes before it, so a screen reader
	 * hears the value and the line apart; the line is a block, so the space moves nothing on screen.
	 *
	 * @param string $text The words.
	 * @param string $html The line as markup the caller already escaped (a name with its link and its mark), printed as
	 *                     given in place of the text, as `intro()` takes one; '' for none, and the text is printed as
	 *                     before (1.123.93).
	 * @return string Escaped markup.
	 */
	public static function cell_sub( string $text, string $html = '' ): string {
		return ' <span class="wpcpm-ui-cell__sub">' . ( '' !== $html ? $html : esc_html( $text ) ) . '</span>';
	}

	/**
	 * Words a person typed, escaped, with a break opportunity (`<wbr>`) after every 10 characters inside any run of 21
	 * characters or more that holds no white space and no hyphen (1.123.93): a long unbroken run in a name may then
	 * break, at those points only, rather than widen its table past the card, and every shorter word keeps its line as
	 * it did. Characters are counted as a reader sees them (grapheme clusters), so a letter and its combining marks are
	 * never parted, and an entity the text already holds (`&amp;`) counts as one. Text with no such run is exactly what
	 * `esc_html()` gives. Put it in a cell's html, inside a link too.
	 *
	 * @param string $text The words, escaped here.
	 * @return string Escaped markup.
	 */
	public static function breakable( string $text ): string {
		$unit  = '(?:&(?:#[0-9]+|#x[0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);|(?![\s\-])\X)';
		$parts = preg_split( '/(' . $unit . '{21,})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) || 1 === count( $parts ) ) {
			return esc_html( $text );
		}

		$out = '';

		foreach ( $parts as $n => $part ) {
			if ( 0 === $n % 2 ) {
				$out .= esc_html( $part );
				continue;
			}

			preg_match_all( '/' . $unit . '/u', $part, $units );
			$out .= implode(
				'<wbr>',
				array_map(
					static function ( array $chunk ): string {
						return esc_html( implode( '', $chunk ) );
					},
					array_chunk( $units[0], 10 )
				)
			);
		}

		return $out;
	}

	/**
	 * A run of words that never breaks inside itself (Task 18d): a date in a range that may break between its dates, at
	 * the range's own words. Put it in a cell's html.
	 *
	 * @param string $text The words.
	 * @return string Escaped markup.
	 */
	public static function whole( string $text ): string {
		return '<span class="wpcpm-ui-whole">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Open a table row, with the role that keeps it a row when the phone layout sets display: block.
	 *
	 * @param array $args id, class (both optional).
	 * @return string Escaped markup; row_close() ends it.
	 */
	public static function row_open( array $args = array() ): string {
		$id    = (string) ( $args['id'] ?? '' );
		$class = (string) ( $args['class'] ?? '' );
		return '<tr role="row"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
	}

	/**
	 * Close a table row.
	 *
	 * @return string Markup.
	 */
	public static function row_close(): string {
		return '</tr>';
	}

	/**
	 * Close a table.
	 *
	 * @return string Markup.
	 */
	public static function table_close(): string {
		return '</tbody></table>';
	}

	/**
	 * Open a list of rows (design 2.8).
	 *
	 * @param array $args facts ('under': the design's people rows, for a list whose rows carry no tag: each row's name on
	 *                    line 1, its facts on line 2 under it in the muted ink, the action at the row's end across both
	 *                    lines, the note under as ever; prints the list's modifier `wpcpm-ui-list--under`; anything else,
	 *                    or none, keeps the four columns and prints the list as before; Task 18c).
	 * @return string Markup.
	 */
	public static function list_open( array $args = array() ): string {
		return '<ul class="wpcpm-ui-list' . ( 'under' === ( $args['facts'] ?? '' ) ? ' wpcpm-ui-list--under' : '' ) . '">';
	}

	/**
	 * Close a list of rows.
	 *
	 * @return string Markup.
	 */
	public static function list_close(): string {
		return '</ul>';
	}

	/**
	 * The one muted line a list shows when a filter or a search leaves no row (design 2.8).
	 *
	 * It prints a paragraph, not a list item, so a caller prints it outside the list: after list_close(), or in
	 * place of the list.
	 *
	 * @param string $text The words.
	 * @return string Escaped markup.
	 */
	public static function list_empty( string $text ): string {
		return '<p class="wpcpm-ui-list__empty">' . esc_html( $text ) . '</p>';
	}

	/**
	 * One list row (design 2.8): the name, the state, the facts and the action, and the row's form under it.
	 *
	 * @param array $args name, href (the name as a link, as item_header()'s), mark (already escaped, after the
	 *                    name), tag (words, tone), facts (as item_header()), actions (button() markup, or a form
	 *                    holding one: the cell is a div; a row with none prints no cell, so on a phone no empty cell
	 *                    takes a line of the grid), note (plain text on a line of its own under the row's line, '' for
	 *                    none), form (markup), form_id, media (markup built and escaped by the caller, as a fact's
	 *                    `html` is: an image or initials, drawn in a 40px box at the row's start, before the name;
	 *                    '' for none, and the row prints as it did without it). A list passes media for every row or
	 *                    for none, so its names line up; level (h4 or h5: the name printed inside that heading,
	 *                    with the same look, so a screen reader moves from row to row by heading; anything else, or
	 *                    none, keeps the name a span, as it was); id (the row's own id, an anchor a link lands on; ''
	 *                    or none prints none, as before); name_html (the name as markup the caller escaped, printed
	 *                    as given in place of name, inside the link when there is an href: a long name through
	 *                    breakable(); '' or none prints the name as before, Institution Dashboard Task 15); more (markup
	 *                    the caller built from the parts and escaped, as `form` is, printed under the note and over the
	 *                    form, at the row's full width, the row marked `wpcpm-ui-list__row--more` (on a phone it reads
	 *                    before the row's action, as a note does): an event's description, a note() or a folded one,
	 *                    Institution Dashboard Task 22; '' or none prints the row as before).
	 * @return string Escaped markup, given the caller escaped the markup it passed.
	 */
	public static function row( array $args ): string {
		$form  = (string) ( $args['form'] ?? '' );
		$more  = (string) ( $args['more'] ?? '' );
		$note  = (string) ( $args['note'] ?? '' );
		$media = (string) ( $args['media'] ?? '' );
		$name  = '' !== (string) ( $args['name_html'] ?? '' ) ? (string) $args['name_html'] : esc_html( (string) ( $args['name'] ?? '' ) );
		$href  = (string) ( $args['href'] ?? '' );
		$tag   = isset( $args['level'] ) && in_array( $args['level'], array( 'h4', 'h5' ), true ) ? (string) $args['level'] : 'span';
		$act   = implode( '', ( isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array() ) );
		$id    = (string) ( $args['id'] ?? '' );
		$out   = '<li class="wpcpm-ui-list__row' . ( '' !== $note ? ' wpcpm-ui-list__row--note' : '' ) . ( '' !== $more ? ' wpcpm-ui-list__row--more' : '' ) . ( '' !== $media ? ' wpcpm-ui-list__row--media' : '' ) . '"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>'
			. ( '' !== $media ? '<span class="wpcpm-ui-list__media">' . $media . '</span>' : '' )
			. '<div class="wpcpm-ui-list__line"><' . $tag . ' class="wpcpm-ui-list__name">'
			. ( '' !== $href ? '<a href="' . esc_url( $href ) . '">' . $name . '</a>' : $name ) . (string) ( $args['mark'] ?? '' ) . '</' . $tag . '>'
			. self::tag_from( $args['tag'] ?? null )
			. '<span class="wpcpm-ui-list__facts">' . self::facts_html( $args['facts'] ?? array() ) . '</span>'
			. ( '' !== $act ? '<div class="wpcpm-ui-list__act">' . $act . '</div>' : '' ) . '</div>';
		if ( '' !== $note ) {
			$out .= '<p class="wpcpm-ui-list__note">' . esc_html( $note ) . '</p>';
		}
		if ( '' !== $more ) {
			$out .= '<div class="wpcpm-ui-list__more">' . $more . '</div>';
		}
		if ( '' !== $form ) {
			$out .= '<div class="wpcpm-ui-list__form" id="' . esc_attr( (string) ( $args['form_id'] ?? '' ) ) . '">' . $form . '</div>';
		}
		return $out . '</li>';
	}
	/**
	 * A head's first row (Task 14): the page's H1 and, at its end, a small control such as Viewing as institution.
	 *
	 * @param string $html The row's markup, escaped by the caller.
	 * @return string Markup.
	 */
	public static function head_row( string $html ): string {
		return '<div class="wpcpm-ui-head-row">' . $html . '</div>';
	}

	/**
	 * The opening of the compact Viewing as control (Task 14, design 7): a GET form marked `data-wpcpm-pick-submits`,
	 * which the parts' script sends when a name is picked in its field (assets/js/parts.js).
	 *
	 * @return string Markup.
	 */
	public static function viewing_open(): string {
		return '<form class="wpcpm-ui-viewing" method="get" data-wpcpm-pick-submits>';
	}

	/**
	 * The compact Viewing as control's Show: a secondary button inside the wrapper the parts' script hides once a pick
	 * sends the form, so without the script the select and Show work as today's switcher's.
	 *
	 * @return string Markup.
	 */
	public static function viewing_show(): string {
		return '<span class="wpcpm-ui-viewing__show" data-wpcpm-noscript>' . self::button(
			array(
				'kind'  => 'secondary',
				'label' => __( 'Show', 'wpcredits-program-manager' ),
				'side'  => 'front',
			)
		) . '</span>';
	}
}
