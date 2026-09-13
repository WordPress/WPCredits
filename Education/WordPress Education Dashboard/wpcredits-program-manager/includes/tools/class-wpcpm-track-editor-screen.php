<?php
/**
 * Tools - the Track Builder's question editor: what it draws.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The question list under a track's properties, and the screen one question is edited on.
 *
 * Markup only: every fact it prints arrives in `$args`, gathered by `WPCPM_Track_Builder`, so this
 * class can be held to what it draws with the collaborators stood in (the pattern of
 * `WPCPM_Track_Builder_Screen`). The list is one table a group, in the order the Student Report
 * Card draws them, and each row carries its column name verbatim in a data attribute, which is
 * what `assets/js/track-editor.js` moves rows by.
 */
class WPCPM_Track_Editor_Screen {

	/**
	 * The four groups, in the order the Student Report Card draws them.
	 *
	 * The same four labels as `WPCPM_Student_Report_Form::groups()`, repeated here rather than
	 * read from it so the editor's suite needs no report form: the keys are the definition's
	 * `GROUPS`, and a fifth group would have to be added to both, which `validate()` would say.
	 *
	 * @return array<string,string>
	 */
	public static function groups() {
		return array(
			'hours'      => __( 'Total hours', 'wpcredits-program-manager' ),
			'onboarding' => __( 'Onboarding', 'wpcredits-program-manager' ),
			'project'    => __( 'Project', 'wpcredits-program-manager' ),
			'wrapup'     => __( 'Wrap-up', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Every question property, in the words the question screen uses for it.
	 *
	 * One map, so History's diff lines read as the rows they point at (the design's decision 28),
	 * and a label changed here changes there. The last two are authoring properties the screen
	 * carries without a row of their own.
	 *
	 * @return string[] Property => what its row is called.
	 */
	public static function property_labels() {
		return array(
			'type'                  => __( 'Control', 'wpcredits-program-manager' ),
			'label'                 => __( 'What the student reads', 'wpcredits-program-manager' ),
			'group'                 => __( 'Group', 'wpcredits-program-manager' ),
			'help'                  => __( 'Help under the box', 'wpcredits-program-manager' ),
			'lead'                  => __( 'Heading before it', 'wpcredits-program-manager' ),
			'subgroup'              => __( 'Subheading before it', 'wpcredits-program-manager' ),
			'note'                  => __( 'Note after the run', 'wpcredits-program-manager' ),
			'row'                   => __( 'Row', 'wpcredits-program-manager' ),
			'stack'                 => __( 'Shares one column of its row', 'wpcredits-program-manager' ),
			'required'              => __( 'Marked required', 'wpcredits-program-manager' ),
			'hide_from_institution' => __( 'Kept off everything an institution reads', 'wpcredits-program-manager' ),
			'min'                   => __( 'Lowest value', 'wpcredits-program-manager' ),
			'max'                   => __( 'Highest value', 'wpcredits-program-manager' ),
			'step'                  => __( 'Step', 'wpcredits-program-manager' ),
			'maxlength'             => __( 'Length limit', 'wpcredits-program-manager' ),
			'mono'                  => __( 'Monospace, for code', 'wpcredits-program-manager' ),
			'options'               => __( 'Choices, one a line', 'wpcredits-program-manager' ),
			'why'                   => __( 'Developer note', 'wpcredits-program-manager' ),
			'learn_lesson_id'       => __( 'Learn lesson', 'wpcredits-program-manager' ),
			'airtable_type'         => __( 'Airtable column type', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The ten controls, named for the person choosing one.
	 *
	 * @return array<string,string>
	 */
	public static function controls() {
		return array(
			'text'     => __( 'Text, one line', 'wpcredits-program-manager' ),
			'textarea' => __( 'Text, many lines', 'wpcredits-program-manager' ),
			'richtext' => __( 'Rich text', 'wpcredits-program-manager' ),
			'url'      => __( 'Web address', 'wpcredits-program-manager' ),
			'email'    => __( 'Email address', 'wpcredits-program-manager' ),
			'number'   => __( 'Number', 'wpcredits-program-manager' ),
			'checkbox' => __( 'Checkbox', 'wpcredits-program-manager' ),
			'select'   => __( 'One choice of several', 'wpcredits-program-manager' ),
			'image'    => __( 'Screenshot', 'wpcredits-program-manager' ),
			'team'     => __( 'Contribution team', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * One question on a page of its own (the design's decision 22).
	 *
	 * The column and the control at the top with the fork and sharing notices beside them, then
	 * the properties that apply to the control: every question has its words, group, help, lead,
	 * subgroup, note, row, stack, required, hide from institution and why; a number also its
	 * bounds, text its length limit, a text area mono, a select its choices. Which control's boxes
	 * are drawn follows what was last typed when a refusal brought the page back, so changing the
	 * control to one that needs more is a two-step: choose it, save, fill in what the refusal
	 * names, save. A published question's column and control are drawn as text with the same
	 * values posted hidden, so the lock is visible before a save would refuse it (decision 23).
	 *
	 * @param array $args `form` from `WPCPM_Track_Builder::question_form()`, the screen's `url`,
	 *                    and the `flash` the last press left, whose `question_values` are what was
	 *                    typed (the track's own `values` are the properties form's, not this one's).
	 */
	public static function render_question( array $args ) {
		$form     = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
		$url      = isset( $args['url'] ) ? (string) $args['url'] : '';
		$flash    = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
		$typed    = isset( $flash['question_values'] ) && is_array( $flash['question_values'] ) ? $flash['question_values'] : array();
		$track    = isset( $form['track'] ) ? (int) $form['track'] : 0;
		$column   = isset( $form['column'] ) ? (string) $form['column'] : '';
		$question = isset( $form['question'] ) && is_array( $form['question'] ) ? $form['question'] : array();
		$locked   = ! empty( $form['locked'] );
		$controls = self::controls();

		// A locked question raced by a typed control that differs from the stored one could not
		// have carried that control's own properties: its boxes belonged to another control, so
		// they read blank unless corrected here. These six read the stored question instead, while
		// every other property still keeps what was typed (decision 29, the Task 9 review).
		$stored_type = isset( $question['type'] ) ? (string) $question['type'] : '';

		if ( $locked && array() !== $typed && isset( $typed['type'] ) && (string) $typed['type'] !== $stored_type ) {
			foreach ( array( 'min', 'max', 'step', 'maxlength', 'mono', 'options' ) as $own ) {
				unset( $typed[ $own ] );

				if ( array_key_exists( $own, $question ) ) {
					$typed[ $own ] = $question[ $own ];
				}
			}
		}

		// What was typed wins over what is stored. A refusal's values are the whole press, since
		// posted_question() writes a property only when it was given and a flag only when it was
		// ticked, so a property the flash does not carry was cleared or unticked and must draw
		// empty; only with no refusal at all does the stored question speak (the Task 7 review).
		$value = function ( $property, $fallback = '' ) use ( $typed, $question ) {
			$from = array() !== $typed ? $typed : $question;

			return array_key_exists( $property, $from ) ? $from[ $property ] : $fallback;
		};

		$type   = (string) $value( 'type' );
		$labels = self::property_labels();

		// A locked question's rows follow its stored control, not a typed one: a lock can be taken
		// while this screen is open, and the typed control is the very change the handler refuses,
		// so rows drawn for it would be the wrong rows (the design's decision 29). The identity
		// block below posts the stored control back for the same reason.
		if ( $locked ) {
			$type = isset( $question['type'] ) ? (string) $question['type'] : '';
		}

		self::render_flash( $flash );

		$back = sprintf(
			/* translators: %s: the track's name. */
			__( 'Back to %s', 'wpcredits-program-manager' ),
			isset( $form['label'] ) ? (string) $form['label'] : ''
		);

		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( add_query_arg( 'wpcpm_track', $track, $url ) ), esc_html( $back ) );

		if ( ! empty( $form['read_only'] ) ) {
			echo '<p class="wpcpm-tracks__readonly">' . esc_html__( 'This track runs from its hand-written form, so its questions cannot be edited here. Duplicate the track to start one of your own.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpcpm-question-form">';
		wp_nonce_field( WPCPM_Track_Editor::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Editor::ACTION_SAVE ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
		printf( '<input type="hidden" name="wpcpm_question" value="%s" />', esc_attr( $column ) );

		echo '<div class="wpcpm-question__identity">';

		if ( $locked ) {
			// The stored control, not the typed one: a lock can be taken while this screen is
			// open (publish the track in another tab), and the typed control shown and posted back
			// would be the very change the handler refuses (the Task 7 review).
			$fixed = isset( $question['type'] ) ? (string) $question['type'] : '';

			printf( '<input type="hidden" name="wpcpm_column" value="%s" />', esc_attr( $column ) );
			printf( '<input type="hidden" name="wpcpm_type" value="%s" />', esc_attr( $fixed ) );
			printf(
				'<p><strong>%1$s</strong> <code>%2$s</code><br /><strong>%3$s</strong> %4$s</p>',
				esc_html__( 'Airtable column', 'wpcredits-program-manager' ),
				esc_html( $column ),
				esc_html( $labels['type'] ),
				esc_html( isset( $controls[ $fixed ] ) ? $controls[ $fixed ] : $fixed )
			);
			echo '<p class="wpcpm-question__locked">' . esc_html__( 'This question has been published, so its column, its control and its choices are fixed: the column in Airtable holds what students have written, in that shape. To ask it differently, remove it and add a new question with a column of its own.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			printf(
				'<p><label for="wpcpm_column">%1$s</label><br /><input type="text" class="regular-text" id="wpcpm_column" name="wpcpm_column" value="%2$s" /></p>',
				esc_html__( 'Airtable column', 'wpcredits-program-manager' ),
				esc_attr( (string) $value( 'column', $column ) )
			);
			printf( '<p><label for="wpcpm_type">%s</label><br /><select id="wpcpm_type" name="wpcpm_type">', esc_html( $labels['type'] ) );

			foreach ( $controls as $control => $name ) {
				printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $control ), esc_html( $name ), $control === $type ? ' selected="selected"' : '' );
			}

			echo '</select></p>';
		}

		self::render_identity_notices( $form );
		echo '</div>';

		echo '<table class="form-table" role="presentation"><tbody>';

		self::render_text_row( 'label', $labels['label'], (string) $value( 'label' ) );

		printf( '<tr><th scope="row"><label for="wpcpm_group">%s</label></th><td><select id="wpcpm_group" name="wpcpm_group">', esc_html( $labels['group'] ) );

		foreach ( self::groups() as $group => $heading ) {
			printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $group ), esc_html( $heading ), $group === (string) $value( 'group' ) ? ' selected="selected"' : '' );
		}

		echo '</select></td></tr>';

		self::render_text_row( 'help', $labels['help'], (string) $value( 'help' ) );
		self::render_text_row( 'lead', $labels['lead'], (string) $value( 'lead' ) );
		self::render_text_row( 'subgroup', $labels['subgroup'], (string) $value( 'subgroup' ) );
		self::render_text_row( 'note', $labels['note'], (string) $value( 'note' ) );
		self::render_text_row( 'row', $labels['row'], (string) $value( 'row' ), __( 'Questions with the same row name sit side by side: lowercase letters, digits and hyphens.', 'wpcredits-program-manager' ) );
		self::render_flag_row( 'stack', $labels['stack'], ! empty( $value( 'stack' ) ) );
		self::render_flag_row( 'required', $labels['required'], ! empty( $value( 'required' ) ) );
		self::render_flag_row( 'hide_from_institution', $labels['hide_from_institution'], ! empty( $value( 'hide_from_institution' ) ) || 'email' === $type );

		if ( 'number' === $type ) {
			self::render_text_row( 'min', $labels['min'], (string) $value( 'min' ) );
			self::render_text_row( 'max', $labels['max'], (string) $value( 'max' ) );
			self::render_text_row( 'step', $labels['step'], (string) $value( 'step' ), __( 'The step also sets how many decimal places a new Airtable column keeps: 1 for whole numbers, 0.01 for a grade.', 'wpcredits-program-manager' ) );
		}

		if ( 'text' === $type ) {
			self::render_text_row( 'maxlength', $labels['maxlength'], (string) $value( 'maxlength' ), __( 'Optional. A single-line box alone takes one; a text area already has its own.', 'wpcredits-program-manager' ) );
		}

		if ( 'textarea' === $type ) {
			self::render_flag_row( 'mono', $labels['mono'], ! empty( $value( 'mono' ) ) );
		}

		if ( 'select' === $type ) {
			$options = $value( 'options', array() );
			// A published question's choices are fixed too, so the box is read-only rather than
			// absent: the handler reads `wpcpm_options` for a select, and a box that posted
			// nothing would be refused for having no choices at all, which is not what happened
			// (the whole-branch review, against decision 23).
			$choices = $locked
				? __( 'The choices are fixed: this question has been published.', 'wpcredits-program-manager' )
				: __( 'A column that already exists in Airtable must offer every one of these; a new column is created with them.', 'wpcredits-program-manager' );

			printf(
				'<tr><th scope="row"><label for="wpcpm_options">%1$s</label></th><td><textarea id="wpcpm_options" name="wpcpm_options" rows="6" class="large-text code"%4$s>%2$s</textarea><p class="description">%3$s</p></td></tr>',
				esc_html( $labels['options'] ),
				esc_textarea( is_array( $options ) ? implode( "\n", array_map( 'strval', $options ) ) : (string) $options ),
				esc_html( $choices ),
				$locked ? ' readonly="readonly"' : ''
			);
		}

		self::render_text_row( 'why', $labels['why'], (string) $value( 'why' ), __( 'Why a column name looks like a slip. No student sees it.', 'wpcredits-program-manager' ) );

		echo '</tbody></table>';

		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Save the question', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * What the top of the question's screen says about its column: shared, or forked.
	 *
	 * A published question's notice stops at who shares the column. The fork it would otherwise
	 * offer is the one thing `handle_save()` refuses of a locked question (decision 23), and a
	 * screen that promised it would be sending people into that refusal (the whole-branch review).
	 *
	 * @param array $form The question as `question_form()` gives it.
	 */
	private static function render_identity_notices( array $form ) {
		$owners = isset( $form['owners'] ) && is_array( $form['owners'] ) ? $form['owners'] : array();
		$locked = ! empty( $form['locked'] );

		if ( array() !== $owners ) {
			$names = array();

			foreach ( $owners as $owner ) {
				$names[] = ! empty( $owner['published'] )
					? (string) $owner['label']
					/* translators: %s: a track's name. */
					: sprintf( __( '%s (a draft)', 'wpcredits-program-manager' ), (string) $owner['label'] );
			}

			if ( $locked ) {
				$notice = sprintf(
					/* translators: %s: the names of the other tracks writing this column. */
					__( 'This column is shared with %s.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				);
			} else {
				$notice = sprintf(
					/* translators: %s: the names of the other tracks writing this column. */
					__( 'This column is shared with %s. Changing the words keeps it shared. Changing the control or the choices gives this track a column of its own, named after it.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				);
			}

			printf( '<p class="wpcpm-question__notice wpcpm-question__notice--shared">%s</p>', esc_html( $notice ) );
		}

		if ( ! empty( $form['forked_from'] ) ) {
			// Once the track is published the column is fixed, so the notice stops promising a
			// rename it cannot keep (decision 29).
			if ( $locked ) {
				$notice = sprintf(
					/* translators: %s: the column this question forked from. */
					__( 'A column of this track\'s own, forked from %s.', 'wpcredits-program-manager' ),
					(string) $form['forked_from']
				);
			} else {
				$notice = sprintf(
					/* translators: %s: the column this question forked from. */
					__( 'A column of this track\'s own, forked from %s. Its name can still be changed until the track is published.', 'wpcredits-program-manager' ),
					(string) $form['forked_from']
				);
			}

			printf( '<p class="wpcpm-question__notice wpcpm-question__notice--forked">%s</p>', esc_html( $notice ) );
		}
	}

	/**
	 * One text box in the properties table.
	 *
	 * @param string $property    The property, which names the field.
	 * @param string $heading     The row's label.
	 * @param string $value       What the box holds.
	 * @param string $description A line under the box, or empty.
	 */
	private static function render_text_row( $property, $heading, $value, $description = '' ) {
		printf(
			'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" />%4$s</td></tr>',
			esc_attr( $property ),
			esc_html( $heading ),
			esc_attr( $value ),
			'' === $description ? '' : '<p class="description">' . esc_html( $description ) . '</p>'
		);
	}

	/**
	 * One checkbox in the properties table.
	 *
	 * @param string $property The flag, which names the field.
	 * @param string $heading  The row's label.
	 * @param bool   $on       Whether it is ticked.
	 */
	private static function render_flag_row( $property, $heading, $on ) {
		printf(
			'<tr><th scope="row">%2$s</th><td><label for="wpcpm_%1$s"><input type="checkbox" id="wpcpm_%1$s" name="wpcpm_%1$s" value="1"%3$s /> %2$s</label></td></tr>',
			esc_attr( $property ),
			esc_html( $heading ),
			$on ? ' checked="checked"' : ''
		);
	}

	/**
	 * The outcome of the last press, in core's notice shape.
	 *
	 * @param array $flash `status` and `message`, or empty.
	 */
	private static function render_flash( array $flash ) {
		if ( empty( $flash['message'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === ( isset( $flash['status'] ) ? $flash['status'] : '' ) ? 'error' : 'success',
			esc_html( (string) $flash['message'] )
		);
	}

	/**
	 * The questions of a track, by group, with what can be done to each.
	 *
	 * @param array $args `track` (the post ID), `key`, `questions` (column => spec, in order),
	 *                    `others` (every other track as `WPCPM_Track_Store::others()` gives them),
	 *                    `schema` (`create`, the columns publishing would make, and `age`, how
	 *                    old the reading is in seconds; empty when the base could not be read),
	 *                    `locked` (the columns of the published copy), `typed` (what a refused Add
	 *                    carried), the screen's `url`, and `read_only` for a built-in track its PHP
	 *                    still runs.
	 */
	public static function render_questions( array $args ) {
		$track     = isset( $args['track'] ) ? (int) $args['track'] : 0;
		$key       = isset( $args['key'] ) ? (string) $args['key'] : '';
		$questions = isset( $args['questions'] ) && is_array( $args['questions'] ) ? $args['questions'] : array();
		$others    = isset( $args['others'] ) && is_array( $args['others'] ) ? $args['others'] : array();
		$schema    = isset( $args['schema'] ) && is_array( $args['schema'] ) ? $args['schema'] : array();
		$create    = isset( $schema['create'] ) && is_array( $schema['create'] ) ? array_map( 'strval', $schema['create'] ) : array();
		$locked    = isset( $args['locked'] ) && is_array( $args['locked'] ) ? array_map( 'strval', $args['locked'] ) : array();
		$typed     = isset( $args['typed'] ) && is_array( $args['typed'] ) ? $args['typed'] : array();
		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';
		$read_only = ! empty( $args['read_only'] );

		echo '<div class="wpcpm-questions">';
		echo '<h2 class="wpcpm-questions__heading">' . esc_html__( 'Questions', 'wpcredits-program-manager' ) . '</h2>';

		self::render_schema_line( $schema );

		foreach ( self::groups() as $group => $heading ) {
			printf( '<section class="wpcpm-questions__group" id="wpcpm-questions-%s">', esc_attr( $group ) );
			printf( '<h3>%s</h3>', esc_html( $heading ) );

			$rows = array();

			foreach ( $questions as $column => $spec ) {
				if ( is_array( $spec ) && isset( $spec['group'] ) && (string) $spec['group'] === $group ) {
					$rows[ (string) $column ] = $spec;
				}
			}

			if ( array() === $rows ) {
				echo '<p class="wpcpm-questions__empty">' . esc_html__( 'No questions in this group.', 'wpcredits-program-manager' ) . '</p>';
			} else {
				echo '<table class="widefat striped wpcpm-questions__table"><thead><tr>';

				foreach ( array(
					__( 'Question', 'wpcredits-program-manager' ),
					__( 'Control', 'wpcredits-program-manager' ),
					__( 'Actions', 'wpcredits-program-manager' ),
				) as $column_heading ) {
					echo '<th scope="col">' . esc_html( $column_heading ) . '</th>';
				}

				echo '</tr></thead><tbody>';

				foreach ( $rows as $column => $spec ) {
					self::render_row( $track, $key, $column, $spec, $others, $url, $read_only, in_array( (string) $column, $create, true ), in_array( (string) $column, $locked, true ) );
				}

				echo '</tbody></table>';
			}

			if ( ! $read_only ) {
				self::render_add( $track, $group, $typed );
			}

			echo '</section>';
		}

		echo '</div>';
	}

	/**
	 * One question's row.
	 *
	 * @param int    $track     The post ID.
	 * @param string $key       The track's key.
	 * @param string $column    The column, verbatim.
	 * @param array  $spec      The question.
	 * @param array  $others    Every other track.
	 * @param string $url       The screen's URL.
	 * @param bool   $read_only Whether the row offers nothing to press.
	 * @param bool   $to_create Whether publishing would create this column in the base.
	 * @param bool   $locked    Whether the published copy holds this column, so it cannot fork.
	 */
	private static function render_row( $track, $key, $column, array $spec, array $others, $url, $read_only, $to_create = false, $locked = false ) {
		$controls = self::controls();
		$type     = isset( $spec['type'] ) ? (string) $spec['type'] : '';
		$group    = isset( $spec['group'] ) ? (string) $spec['group'] : '';

		printf(
			'<tr class="wpcpm-question" id="wpcpm-question-%1$s" data-wpcpm-column="%2$s" data-wpcpm-group="%3$s">',
			esc_attr( md5( $column ) ),
			esc_attr( $column ),
			esc_attr( $group )
		);

		printf( '<td><strong>%s</strong>', esc_html( isset( $spec['label'] ) ? (string) $spec['label'] : '' ) );
		printf( '<code class="wpcpm-question__column">%s</code>', esc_html( $column ) );
		self::render_notice( $column, $key, $others, $locked );

		if ( $to_create ) {
			echo '<span class="wpcpm-question__notice wpcpm-question__notice--new">' . esc_html__( 'Publishing will create this column in Airtable.', 'wpcredits-program-manager' ) . '</span>';
		}

		echo '</td>';

		printf( '<td>%s</td>', esc_html( isset( $controls[ $type ] ) ? $controls[ $type ] : $type ) );

		echo '<td class="wpcpm-list__actions">';

		if ( ! $read_only ) {
			// The column is encoded by the caller: add_query_arg() inserts a value exactly as it
			// is handed and leaves encoding to us, and a column can hold `&` (the Task 5 review).
			printf(
				'<a href="%1$s">%2$s</a> ',
				esc_url( add_query_arg( 'wpcpm_question', rawurlencode( $column ), add_query_arg( 'wpcpm_track', $track, $url ) ) ),
				esc_html__( 'Edit', 'wpcredits-program-manager' )
			);
			self::render_mover( $track, $column );
			self::render_remover( $track, $column );
		}

		echo '</td></tr>';
	}

	/**
	 * The line above the list: what publishing would create, off the cached reading (decision 24).
	 *
	 * Left off when the base could not be read: guessing would make every column look like one
	 * to create, which is the preflight's own reason for refusing rather than guessing.
	 *
	 * @param array $schema `create` and `age`, or empty.
	 */
	private static function render_schema_line( array $schema ) {
		if ( ! isset( $schema['create'], $schema['age'] ) || ! is_array( $schema['create'] ) ) {
			return;
		}

		$count = count( $schema['create'] );
		$age   = (int) $schema['age'];

		if ( 0 === $count ) {
			$line = __( 'This track needs no new Airtable columns.', 'wpcredits-program-manager' );
		} else {
			$line = sprintf(
				/* translators: %d: how many columns publishing would create. */
				_n( 'Publishing will create %d column in Airtable.', 'Publishing will create %d columns in Airtable.', $count, 'wpcredits-program-manager' ),
				$count
			);
		}

		if ( $age < 60 ) {
			$when = __( 'Read from Airtable just now.', 'wpcredits-program-manager' );
		} else {
			$minutes = (int) floor( $age / 60 );
			$when    = sprintf(
				/* translators: %d: a number of minutes. */
				_n( 'Read from Airtable %d minute ago; the publish screen reads it afresh.', 'Read from Airtable %d minutes ago; the publish screen reads it afresh.', $minutes, 'wpcredits-program-manager' ),
				$minutes
			);
		}

		printf(
			'<p class="wpcpm-questions__schema">%1$s <span class="wpcpm-questions__age">%2$s</span></p>',
			esc_html( $line ),
			esc_html( $when )
		);
	}

	/**
	 * What a row says under its words: who else writes the column, or what it forked from.
	 *
	 * A published question's row promises no fork, for the reason `render_identity_notices()`
	 * gives: `handle_save()` refuses one (decision 23, and the whole-branch review).
	 *
	 * @param string $column The column.
	 * @param string $key    The track's key.
	 * @param array  $others Every other track.
	 * @param bool   $locked Whether the published copy holds this column.
	 */
	private static function render_notice( $column, $key, array $others, $locked = false ) {
		$owners = WPCPM_Track_Questions::owners( $column, $others );

		if ( array() !== $owners ) {
			$names = array();

			foreach ( $owners as $owner ) {
				$names[] = $owner['published']
					? $owner['label']
					/* translators: %s: a track's name. */
					: sprintf( __( '%s (a draft)', 'wpcredits-program-manager' ), $owner['label'] );
			}

			if ( $locked ) {
				$notice = sprintf(
					/* translators: %s: the names of the other tracks writing this column. */
					__( 'Shared with %s.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				);
			} else {
				$notice = sprintf(
					/* translators: %s: the names of the other tracks writing this column. */
					__( 'Shared with %s. Rewording keeps the column; a different control or different choices gives this track a column of its own.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				);
			}

			printf( '<span class="wpcpm-question__notice wpcpm-question__notice--shared">%s</span>', esc_html( $notice ) );

			return;
		}

		$from = WPCPM_Track_Questions::forked_from( $column, $key, $others );

		if ( '' !== $from ) {
			$notice = sprintf(
				/* translators: %s: the column this question forked from. */
				__( 'A column of this track\'s own, forked from %s.', 'wpcredits-program-manager' ),
				$from
			);

			printf( '<span class="wpcpm-question__notice wpcpm-question__notice--forked">%s</span>', esc_html( $notice ) );
		}
	}

	/**
	 * The two arrows: a form the page posts in the background, or the ordinary way without the script.
	 *
	 * @param int    $track  The post ID.
	 * @param string $column The column.
	 */
	private static function render_mover( $track, $column ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpcpm-question__mover" data-wpcpm-refused="' . esc_attr__( 'The move was not saved. The question is back where it was.', 'wpcredits-program-manager' ) . '">';
		wp_nonce_field( WPCPM_Track_Editor::ACTION_MOVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Editor::ACTION_MOVE ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
		printf( '<input type="hidden" name="wpcpm_question" value="%s" />', esc_attr( $column ) );
		printf(
			'<button type="submit" class="button button-small wpcpm-question__move wpcpm-question__move--up" name="wpcpm_direction" value="up" aria-label="%1$s" data-wpcpm-moved="%2$s">&uarr;</button> ',
			esc_attr__( 'Move up', 'wpcredits-program-manager' ),
			esc_attr__( 'Moved up.', 'wpcredits-program-manager' )
		);
		printf(
			'<button type="submit" class="button button-small wpcpm-question__move wpcpm-question__move--down" name="wpcpm_direction" value="down" aria-label="%1$s" data-wpcpm-moved="%2$s">&darr;</button>',
			esc_attr__( 'Move down', 'wpcredits-program-manager' ),
			esc_attr__( 'Moved down.', 'wpcredits-program-manager' )
		);
		echo '</form> ';
	}

	/**
	 * Remove, with the confirmation saying what stays in Airtable (decision 9).
	 *
	 * @param int    $track  The post ID.
	 * @param string $column The column.
	 */
	private static function render_remover( $track, $column ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-question__remover" onsubmit="return confirm(\'%2$s\');">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_js( __( 'Remove this question from the track? Its column, and whatever students wrote in it, stay in Airtable.', 'wpcredits-program-manager' ) )
		);
		wp_nonce_field( WPCPM_Track_Editor::ACTION_REMOVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Editor::ACTION_REMOVE ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
		printf( '<input type="hidden" name="wpcpm_question" value="%s" />', esc_attr( $column ) );
		printf( '<button type="submit" class="button button-small button-link-delete">%s</button>', esc_html__( 'Remove', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Add a question to this group: the column, the words, and the control.
	 *
	 * A refused Add comes back here, so the three boxes are filled again with what was typed
	 * rather than cleared; only in the group the press came from, since the other groups' forms
	 * were never filled in (the whole-branch review).
	 *
	 * @param int    $track The post ID.
	 * @param string $group The group.
	 * @param array  $typed What a refused Add carried, or empty.
	 */
	private static function render_add( $track, $group, array $typed = array() ) {
		$mine    = isset( $typed['group'] ) && (string) $typed['group'] === (string) $group;
		$column  = $mine && isset( $typed['column'] ) ? (string) $typed['column'] : '';
		$words   = $mine && isset( $typed['label'] ) ? (string) $typed['label'] : '';
		$control = $mine && isset( $typed['type'] ) ? (string) $typed['type'] : '';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpcpm-questions__add">';
		wp_nonce_field( WPCPM_Track_Editor::ACTION_ADD );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Editor::ACTION_ADD ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
		printf( '<input type="hidden" name="wpcpm_group" value="%s" />', esc_attr( $group ) );

		printf(
			'<label for="wpcpm_add_column_%1$s">%2$s</label> <input type="text" class="regular-text" id="wpcpm_add_column_%1$s" name="wpcpm_column" value="%3$s" /> ',
			esc_attr( $group ),
			esc_html__( 'Airtable column', 'wpcredits-program-manager' ),
			esc_attr( $column )
		);
		printf(
			'<label for="wpcpm_add_label_%1$s">%2$s</label> <input type="text" class="regular-text" id="wpcpm_add_label_%1$s" name="wpcpm_label" value="%3$s" /> ',
			esc_attr( $group ),
			esc_html( self::property_labels()['label'] ),
			esc_attr( $words )
		);
		printf( '<label for="wpcpm_add_type_%1$s">%2$s</label> <select id="wpcpm_add_type_%1$s" name="wpcpm_type">', esc_attr( $group ), esc_html( self::property_labels()['type'] ) );

		foreach ( self::controls() as $type => $name ) {
			printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $type ), esc_html( $name ), $type === $control ? ' selected="selected"' : '' );
		}

		echo '</select> ';
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Add a question', 'wpcredits-program-manager' ) );
		echo '</form>';
	}
}
