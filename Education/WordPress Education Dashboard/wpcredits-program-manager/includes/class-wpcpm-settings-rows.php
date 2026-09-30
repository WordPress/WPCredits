<?php
/**
 * The rows a settings form is drawn with.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One setting's row of a settings form, in core's `form-table` markup: a text input, a number, a
 * select, a checkbox list, a list of reviewers or a landing page's switch, each with its one
 * sentence of help and, under it, the Details fold that holds the rest, which a screen reader hears
 * named for its row. A row a form draws by hand in the same markup ends through `close_row()` when
 * it has a fold.
 *
 * Shared by every form that saves settings, the tabs of the Settings screen
 * (`WPCPM_Settings_Screen`) and the Settings section a tool draws on its own screen
 * (`WPCPM_Tool::render_settings()`), so a setting is drawn the same way wherever its row is. Each
 * row posts under its setting's key, which is what `WPCPM_Settings::save()` reads.
 */
final class WPCPM_Settings_Rows {

	/**
	 * One number input row on a settings form.
	 *
	 * @param string          $name        Field name.
	 * @param string          $label       Field label.
	 * @param int             $value       Current value.
	 * @param int             $min         Minimum accepted value.
	 * @param int             $max         Maximum accepted value.
	 * @param string          $description Optional help text, one sentence; may contain <code> tags.
	 * @param string|string[] $details     Optional text for the row's Details fold (`details_after()`).
	 */
	public static function number_row( $name, $label, $value, $min, $max, $description = '', $details = '' ) {
		printf(
			'<tr><th scope="row"><label for="wpcpm-%1$s">%2$s</label></th><td><input type="number" id="wpcpm-%1$s" name="%1$s" value="%3$d" min="%4$d" max="%5$d" step="1" class="small-text" />',
			esc_attr( $name ),
			esc_html( $label ),
			(int) $value,
			(int) $min,
			(int) $max
		);

		if ( $description ) {
			printf( '<p class="description">%s</p>', wp_kses( $description, array( 'code' => array() ) ) );
		}

		self::close_row( $label, $details );
	}

	/**
	 * One text input row on a settings form.
	 *
	 * @param string          $name        Field name.
	 * @param string          $label       Field label.
	 * @param string          $value       Current value.
	 * @param string          $description Optional help text, one sentence; may contain <code> tags.
	 * @param string          $type        Input type.
	 * @param string          $fallback    Optional sentence saying why the field is typed rather than
	 *                                     chosen from a list (`select_row()`); may contain <code> tags.
	 * @param string          $placeholder Optional example of what to type.
	 * @param string|string[] $details     Optional text for the row's Details fold (`details_after()`).
	 */
	public static function text_row( $name, $label, $value, $description = '', $type = 'text', $fallback = '', $placeholder = '', $details = '' ) {
		printf(
			'<tr><th scope="row"><label for="wpcpm-%1$s">%2$s</label></th><td><input type="%3$s" id="wpcpm-%1$s" name="%1$s" value="%4$s" class="regular-text"%5$s autocomplete="off" />',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			'' === (string) $placeholder ? '' : ' placeholder="' . esc_attr( $placeholder ) . '"'
		);

		self::row_notes( $description, $fallback );
		self::close_row( $label, $details );
	}

	/**
	 * One select row on a settings form: a setting whose value is one choice from a known list.
	 *
	 * The value saved is always offered, after the list and marked as the current one when the list
	 * lacks it, so a Save of a list that has changed, or of a value typed before there was a list,
	 * cannot drop it. Each option posts the very string the text input it replaced posted. With no
	 * list to offer, the row is that text input, with the same name and value, and the sentence
	 * saying why.
	 *
	 * @param string          $name        Field name.
	 * @param string          $label       Field label.
	 * @param string          $value       Current value.
	 * @param array           $choices     Value => label, or a group's label => array( value => label ),
	 *                                     drawn as an option group.
	 * @param string          $description Optional help text, one sentence; may contain <code> tags.
	 * @param string          $fallback    The sentence the text input says when there is no list; may
	 *                                     contain <code> tags.
	 * @param string|string[] $details     Optional text for the row's Details fold (`details_after()`).
	 */
	public static function select_row( $name, $label, $value, array $choices, $description = '', $fallback = '', $details = '' ) {
		$value = (string) $value;

		if ( array() === $choices ) {
			self::text_row( $name, $label, $value, $description, 'text', $fallback, '', $details );

			return;
		}

		printf(
			'<tr><th scope="row"><label for="wpcpm-%1$s">%2$s</label></th><td><select id="wpcpm-%1$s" name="%1$s">',
			esc_attr( $name ),
			esc_html( $label )
		);

		foreach ( $choices as $key => $choice ) {
			if ( ! is_array( $choice ) ) {
				self::select_option( (string) $key, (string) $choice, $value );

				continue;
			}

			if ( array() === $choice ) {
				continue;
			}

			printf( '<optgroup label="%s">', esc_attr( (string) $key ) );

			foreach ( $choice as $option => $option_label ) {
				self::select_option( (string) $option, (string) $option_label, $value );
			}

			echo '</optgroup>';
		}

		if ( ! in_array( $value, self::choice_values( $choices ), true ) ) {
			self::select_option( $value, self::current_label( $value ), $value );
		}

		echo '</select>';

		self::row_notes( $description );
		self::close_row( $label, $details );
	}

	/**
	 * One option of a select, chosen when it is the value saved.
	 *
	 * @param string $option The value it posts.
	 * @param string $label  What it says.
	 * @param string $value  The value saved.
	 */
	private static function select_option( $option, $label, $value ) {
		printf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $option ),
			selected( $value, $option, false ),
			esc_html( $label )
		);
	}

	/**
	 * One checkbox list row on a settings form: a setting that holds any number of choices from a
	 * known list, such as the statuses a list of students is made of.
	 *
	 * An empty value is always posted first, so a list with every box unticked is saved as the empty
	 * list it is, not read as a form that never drew it. A value saved that the list lacks is drawn
	 * as a ticked box of its own, after the list and marked as the current one, so a Save cannot drop
	 * it. A locked box is one whose change the save would refuse: it is drawn switched off, its note
	 * in its label, and when it is ticked its value is carried in a hidden field, since a
	 * switched-off box posts nothing. A group whose boxes are locked for one reason can say it once,
	 * in a note under its name, in place of a note beside each box. With no list, the row is the
	 * textarea it replaced, one value a line, with the same name, and the sentence saying why.
	 *
	 * @param string          $name        Field name; the boxes post as `<name>[]`.
	 * @param string          $label       Field label.
	 * @param array           $ticked      The values saved, each as the list stores it.
	 * @param array           $choices     Value => label, or a group's label => array( value => label ),
	 *                                     each group drawn under its name.
	 * @param string          $description Optional help text, one sentence; may contain <code> tags.
	 * @param array           $locked      Value => the note saying why its box cannot be changed, or ''
	 *                                     for a box whose group's note says it (`$notes`). A '' only
	 *                                     for a box in a group that has a note: anywhere else the box
	 *                                     is switched off with no reason given, so it has its own.
	 * @param string          $fallback    The sentence the textarea says when there is no list; may
	 *                                     contain <code> tags.
	 * @param array           $other       Optional line after the boxes for the values no box holds,
	 *                                     posting under the same name: its `label`, `value` and
	 *                                     `placeholder`. It posts whatever is ticked, so no empty value
	 *                                     goes before the boxes.
	 * @param string|string[] $details     Optional text for the row's Details fold (`details_after()`).
	 * @param array           $notes       Optional note for a group of boxes: the group's label => the
	 *                                     note, drawn once under the group's name, for the locked boxes
	 *                                     in it that have no note of their own.
	 */
	public static function checklist_row( $name, $label, array $ticked, array $choices, $description = '', array $locked = array(), $fallback = '', array $other = array(), $details = '', array $notes = array() ) {
		$ticked = array_values( array_map( 'strval', $ticked ) );

		if ( array() === $choices ) {
			self::textarea_row( $name, $label, $ticked, $description, $fallback, $details );

			return;
		}

		printf( '<tr><th scope="row">%1$s</th><td><fieldset><legend class="screen-reader-text">%1$s</legend>', esc_html( $label ) );

		if ( array() === $other ) {
			printf( '<input type="hidden" name="%s[]" value="" />', esc_attr( $name ) );
		}

		self::checklist_boxes( $name, $ticked, $choices, $locked, $notes );

		if ( array() !== $other ) {
			printf(
				'<p><label for="wpcpm-%1$s-other">%2$s</label><br /><input type="text" id="wpcpm-%1$s-other" name="%1$s[]" value="%3$s" class="regular-text" placeholder="%4$s" autocomplete="off" /></p>',
				esc_attr( $name ),
				esc_html( isset( $other['label'] ) ? (string) $other['label'] : '' ),
				esc_attr( isset( $other['value'] ) ? (string) $other['value'] : '' ),
				esc_attr( isset( $other['placeholder'] ) ? (string) $other['placeholder'] : '' )
			);
		}

		echo '</fieldset>';

		self::row_notes( $description );
		self::close_row( $label, $details );
	}

	/**
	 * One reviewer list on a settings form: the program managers to write to, under Administrators
	 * (`WPCPM_Settings_Choices::managers()`), and a line for any other address, both posting as
	 * `<name>[]`, which the save joins into the comma list the text input saved
	 * (`WPCPM_Settings::save()`).
	 *
	 * A saved address that is a listed program manager's is that manager's ticked box, whatever case
	 * either spells it in, and every other saved address is on the Other addresses line. With nobody to
	 * list, since no account holding the capability has an address, the row is the text input it
	 * replaced, with the same name and value, and the sentence saying why.
	 *
	 * @param string          $name        Field name.
	 * @param string          $label       Field label.
	 * @param string          $value       The addresses saved, comma-separated.
	 * @param string          $description Optional help text, one sentence; may contain <code> tags.
	 * @param string|string[] $details     Optional text for the row's Details fold (`details_after()`).
	 */
	public static function reviewers_row( $name, $label, $value, $description = '', $details = '' ) {
		$value       = (string) $value;
		$managers    = WPCPM_Settings_Choices::managers();
		$placeholder = __( 'one@example.org, two@example.org', 'wpcredits-program-manager' );

		if ( array() === $managers ) {
			self::text_row( $name, $label, $value, $description, 'text', self::typed( 'addresses', WPCPM_Settings_Choices::why_empty( 'managers' ) ), $placeholder, $details );

			return;
		}

		$choices = array();
		$known   = array();

		foreach ( $managers as $group => $people ) {
			foreach ( $people as $email => $person ) {
				$email = (string) $email;

				/* translators: 1: a name, 2: the ID or the address it goes with. */
				$choices[ $group ][ $email ]   = sprintf( __( '%1$s (%2$s)', 'wpcredits-program-manager' ), $person, $email );
				$known[ strtolower( $email ) ] = $email;
			}
		}

		$ticked = array();
		$others = array();

		foreach ( preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY ) as $address ) {
			if ( isset( $known[ strtolower( $address ) ] ) ) {
				$ticked[] = $known[ strtolower( $address ) ];
			} else {
				$others[] = $address;
			}
		}

		self::checklist_row(
			$name,
			$label,
			$ticked,
			$choices,
			$description,
			array(),
			'',
			array(
				'label'       => __( 'Other addresses', 'wpcredits-program-manager' ),
				'value'       => implode( ', ', $others ),
				'placeholder' => $placeholder,
			),
			$details
		);
	}

	/**
	 * One landing page's row: the switch that sends an audience to its page when it logs in, the one
	 * sentence saying what that does, the page's address or the sentence saying it is missing
	 * (`page_line()`), and the row's Details fold. The four landing pages share it, so they read alike
	 * because they are drawn alike.
	 *
	 * @param string          $name        The switch's setting.
	 * @param string          $label       Field label.
	 * @param bool            $on          Whether the switch is on.
	 * @param string          $box         What the switch's box says.
	 * @param string          $description One sentence of help.
	 * @param string          $page_url    The page's address, or '' when the page is missing.
	 * @param string|string[] $details     Optional text for the row's Details fold (`details_after()`).
	 */
	public static function landing_row( $name, $label, $on, $box, $description, $page_url, $details = '' ) {
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="%2$s" value="1"%3$s> %4$s</label><p class="description">%5$s</p>',
			esc_html( $label ),
			esc_attr( $name ),
			checked( (bool) $on, true, false ),
			esc_html( $box ),
			esc_html( $description )
		);

		self::page_line( $page_url );
		self::close_row( $label, $details );
	}

	/**
	 * The address of a page a row switches on, under the row's help, or, when the page is missing, the
	 * sentence saying so and what puts it back: one line for every row that prints a page, the landing
	 * pages' and the application forms', so each says it in the same words.
	 *
	 * @param string $url The page's address, or '' when the page is missing.
	 */
	public static function page_line( $url ) {
		if ( '' !== (string) $url ) {
			printf( '<p class="description"><a href="%1$s">%1$s</a></p>', esc_url( (string) $url ) );

			return;
		}

		printf( '<p class="description wpcpm-warning">%s</p>', esc_html__( 'The page is missing: re-activate the plugin to recreate it.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The boxes of a checkbox list: each group under its name, with its note when it has one, then
	 * any value saved that the list lacks (`checklist_row()`).
	 *
	 * @param string $name    Field name; the boxes post as `<name>[]`.
	 * @param array  $ticked  The values saved.
	 * @param array  $choices Value => label, or a group's label => array( value => label ).
	 * @param array  $locked  Value => the note saying why its box cannot be changed, or '' for a box
	 *                        its group's note speaks for.
	 * @param array  $notes   A group's label => the note drawn under its name.
	 */
	private static function checklist_boxes( $name, array $ticked, array $choices, array $locked = array(), array $notes = array() ) {
		$ticked = array_values( array_map( 'strval', $ticked ) );
		$group  = 0;

		foreach ( $choices as $key => $choice ) {
			if ( ! is_array( $choice ) ) {
				self::checkbox( $name, (string) $key, (string) $choice, $ticked, $locked );

				continue;
			}

			if ( array() === $choice ) {
				continue;
			}

			++$group;

			printf( '<fieldset class="wpcpm-settings__group"><legend>%s</legend>', esc_html( (string) $key ) );

			// Under the group's name, where it is read before the boxes it speaks for, and named by
			// each of them for assistive technology, which does not reach a switched-off box by its
			// focus.
			$note_id = '';

			if ( isset( $notes[ $key ] ) && '' !== (string) $notes[ $key ] ) {
				$note_id = sprintf( 'wpcpm-%1$s-note-%2$d', $name, $group );

				printf( '<p class="description" id="%1$s">%2$s</p>', esc_attr( $note_id ), esc_html( (string) $notes[ $key ] ) );
			}

			foreach ( $choice as $option => $option_label ) {
				self::checkbox( $name, (string) $option, (string) $option_label, $ticked, $locked, $note_id );
			}

			echo '</fieldset>';
		}

		$known = self::choice_values( $choices );

		foreach ( $ticked as $value ) {
			if ( '' !== $value && ! in_array( $value, $known, true ) ) {
				self::checkbox( $name, $value, self::current_label( $value ), $ticked );
			}
		}
	}

	/**
	 * One box of a checkbox list, and, for a locked one, its note, inside the label so assistive
	 * technology reads it with the box, or, for a locked one its group's note speaks for, that note
	 * named as what describes it; and the hidden field that carries a locked box's value when it is
	 * ticked.
	 *
	 * @param string $name         Field name; the box posts as `<name>[]`.
	 * @param string $value        The value it posts.
	 * @param string $label        What it says.
	 * @param array  $ticked       The values saved.
	 * @param array  $locked       Value => the note saying why its box cannot be changed, or '' for
	 *                             a box its group's note speaks for; a '' box outside such a group is
	 *                             drawn switched off with no reason, so a caller gives it a note.
	 * @param string $described_by The ID of the group's note, when the group has one.
	 */
	private static function checkbox( $name, $value, $label, array $ticked, array $locked = array(), $described_by = '' ) {
		$is_ticked = in_array( $value, $ticked, true );
		$lock      = isset( $locked[ $value ] ) ? (string) $locked[ $value ] : null;
		$own_note  = null !== $lock && '' !== $lock;

		printf(
			'<label><input type="checkbox" name="%1$s[]" value="%2$s"%3$s%4$s%5$s /> %6$s%7$s</label>',
			esc_attr( $name ),
			esc_attr( $value ),
			checked( $is_ticked, true, false ),
			null === $lock ? '' : ' disabled="disabled"',
			null !== $lock && ! $own_note && '' !== (string) $described_by ? ' aria-describedby="' . esc_attr( $described_by ) . '"' : '',
			esc_html( $label ),
			$own_note ? ' <span class="description">' . esc_html( $lock ) . '</span>' : ''
		);

		if ( null !== $lock && $is_ticked ) {
			printf( '<input type="hidden" name="%1$s[]" value="%2$s" />', esc_attr( $name ), esc_attr( $value ) );
		}

		echo '<br />';
	}

	/**
	 * One textarea row on a settings form, one value a line: the row a checkbox list stands in for
	 * when there is no list (`checklist_row()`).
	 *
	 * @param string          $name        Field name.
	 * @param string          $label       Field label.
	 * @param string[]        $lines       The values saved.
	 * @param string          $description Optional help text, one sentence; may contain <code> tags.
	 * @param string          $fallback    Optional sentence saying why the list is typed; may contain
	 *                                     <code> tags.
	 * @param string|string[] $details     Optional text for the row's Details fold (`details_after()`).
	 */
	private static function textarea_row( $name, $label, array $lines, $description = '', $fallback = '', $details = '' ) {
		printf(
			'<tr><th scope="row"><label for="wpcpm-%1$s">%2$s</label></th><td><textarea id="wpcpm-%1$s" name="%1$s" rows="%3$d" class="regular-text">%4$s</textarea>',
			esc_attr( $name ),
			esc_html( $label ),
			(int) max( 3, min( 10, count( $lines ) ) ),
			esc_textarea( implode( "\n", $lines ) )
		);

		self::row_notes( $description, $fallback );
		self::close_row( $label, $details );
	}

	/**
	 * A row's help, and the sentence saying why a field is typed rather than chosen, when it is.
	 *
	 * @param string $description Help text; may contain <code> tags.
	 * @param string $fallback    The sentence; may contain <code> tags.
	 */
	private static function row_notes( $description, $fallback = '' ) {
		if ( $description ) {
			printf( '<p class="description">%s</p>', wp_kses( $description, array( 'code' => array() ) ) );
		}

		if ( $fallback ) {
			printf( '<p class="description wpcpm-settings__fallback">%s</p>', wp_kses( $fallback, array( 'code' => array() ) ) );
		}
	}

	/**
	 * End a row: its Details fold, when it has one, named for the row, then the end of the row's cell.
	 * Every row with a fold ends here, a helper's and one a form draws by hand in this markup, so no
	 * row's fold goes without the row's name.
	 *
	 * @param string          $label   The row's label, which the fold is named for.
	 * @param string|string[] $details Optional text for the row's Details fold (`details_after()`).
	 */
	public static function close_row( $label, $details = '' ) {
		self::details_after( $details, $label );

		echo '</td></tr>';
	}

	/**
	 * The word a Details fold's summary shows, which a sentence that sends somebody to the fold names
	 * it by, so a translation cannot call the fold one thing and show it as another.
	 *
	 * @return string
	 */
	public static function details_word() {
		return __( 'Details', 'wpcredits-program-manager' );
	}

	/**
	 * What a screen reader adds to a guide link's or a Details fold's own words: the section or the
	 * row it belongs to, which the eye takes from where it sits. Hidden from sight, so the link still
	 * says Program manager guide and the summary Details, while a tab's list of links, or of its
	 * folds, no longer reads the same words once a section or once a row.
	 *
	 * @param string $name  The heading of the section, or the label of the row, it belongs to.
	 * @param string $after Optional words after the name, such as that the link opens a new tab.
	 * @return string The hidden words, escaped, led by the space that parts them from the words shown.
	 */
	public static function screen_reader_about( $name, $after = '' ) {
		/* translators: %s: the heading of a section, or the label of a setting, that a guide link or a Details fold belongs to, read out after the link's or the fold's own words. */
		$words = sprintf( __( 'about %s', 'wpcredits-program-manager' ), (string) $name );

		return sprintf( '<span class="screen-reader-text"> %s</span>', esc_html( '' === (string) $after ? $words : $words . ' ' . $after ) );
	}

	/**
	 * Open a Details fold: the browser's own disclosure, closed, its summary one word, and, for a
	 * screen reader, what it belongs to. The caller that fills it with markup of its own closes it
	 * with `close_details()`; `details_after()` opens and closes its own.
	 *
	 * @param string $about The row's label, or the section's heading, the fold belongs to.
	 */
	public static function open_details( $about = '' ) {
		printf(
			'<details class="wpcpm-settings__details"><summary>%1$s%2$s</summary>',
			esc_html( self::details_word() ),
			'' === (string) $about ? '' : self::screen_reader_about( $about ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in screen_reader_about().
		);
	}

	/**
	 * Close a Details fold opened by `open_details()`.
	 */
	public static function close_details() {
		echo '</details>';
	}

	/**
	 * A Details fold, closed until somebody opens it: what a row's one sentence of help leaves out, the
	 * reasons and the consequences, as the last thing under the row's control; or what the one
	 * sentence introducing a tool's Settings section leaves out, under that sentence.
	 *
	 * @param string|string[] $details The fold's text, a paragraph a string; may contain <code> tags.
	 *                                 Nothing is drawn for none.
	 * @param string          $about   The row's label, or the section's heading, the fold belongs to,
	 *                                 which a screen reader reads after the summary's word.
	 */
	public static function details_after( $details, $about = '' ) {
		$paragraphs = array_values( array_filter( array_map( 'strval', (array) $details ), 'strlen' ) );

		if ( array() === $paragraphs ) {
			return;
		}

		self::open_details( $about );

		foreach ( $paragraphs as $paragraph ) {
			printf( '<p>%s</p>', wp_kses( $paragraph, array( 'code' => array() ) ) );
		}

		self::close_details();
	}

	/**
	 * Every value a list of choices holds, its groups' included.
	 *
	 * @param array $choices Value => label, or a group's label => array( value => label ).
	 * @return string[]
	 */
	private static function choice_values( array $choices ) {
		$values = array();

		foreach ( $choices as $key => $choice ) {
			if ( is_array( $choice ) ) {
				foreach ( array_keys( $choice ) as $option ) {
					$values[] = (string) $option;
				}
			} else {
				$values[] = (string) $key;
			}
		}

		return $values;
	}

	/**
	 * What a value saved says when the list it is chosen from lacks it.
	 *
	 * @param string $value The value saved.
	 * @return string
	 */
	private static function current_label( $value ) {
		if ( '' === $value ) {
			return __( 'None (current)', 'wpcredits-program-manager' );
		}

		/* translators: %s: a setting's saved value, which the list it is chosen from does not hold. */
		return sprintf( __( '%s (current)', 'wpcredits-program-manager' ), $value );
	}

	/**
	 * What a field drawn as its text input says about its missing list: what to type, then why the
	 * list is not there (`WPCPM_Settings_Choices::why_empty()`).
	 *
	 * @param string $what What is typed: `id`, `column`, `status`, `statuses`, `stage`, `stages` or
	 *                     `addresses`.
	 * @param string $why  Why the list is not there, one sentence; may contain <code> tags.
	 * @return string May contain <code> tags.
	 */
	public static function typed( $what, $why ) {
		$how = array(
			'id'        => __( 'Type the ID.', 'wpcredits-program-manager' ),
			'column'    => __( 'Type the column\'s name.', 'wpcredits-program-manager' ),
			'status'    => __( 'Type the status.', 'wpcredits-program-manager' ),
			'statuses'  => __( 'Type one status per line.', 'wpcredits-program-manager' ),
			'stage'     => __( 'Type the stage.', 'wpcredits-program-manager' ),
			'stages'    => __( 'Type one stage per line.', 'wpcredits-program-manager' ),
			'addresses' => __( 'Type the addresses, separated by commas.', 'wpcredits-program-manager' ),
		);

		return trim( ( isset( $how[ $what ] ) ? $how[ $what ] : '' ) . ' ' . (string) $why );
	}
}
