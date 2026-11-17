<?php
/**
 * The screen a school enrolls students through, and the two handlers behind it.
 *
 * Kept apart from `WPCPM_Institution_Import`, which reads and decides and never learns who is
 * asking. This file is the half that touches the request: it reads `$_POST` and `$_FILES`,
 * resolves who the reader is, and hands the bytes to that module. The split is asserted from
 * both sides - a test on the other file fails if a superglobal appears in it - because the
 * rules about what a file may contain are worth reading without a form wrapped around them.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Draw the import form, and turn a submitted list into a staged batch.
 *
 * **Nothing here creates a student.** The check handler stages a batch and stops; a person
 * reads the preview and confirms, and the creating is a separate handler in a later release.
 * That is the point of the two steps: the base is written to only after somebody has seen what
 * the file was understood to say.
 */
final class WPCPM_Institution_Import_Form {

	/** The `admin_post_` action the form posts to. */
	const ACTION_CHECK = WPCPM_Institution_Import::ACTION_CHECK;

	/** The `admin_post_` action the preview's Cancel posts to. */
	const ACTION_CANCEL = WPCPM_Institution_Import::ACTION_CANCEL;

	/** The `admin_post_` action the preview's Confirm posts to. */
	const ACTION_CONFIRM = WPCPM_Institution_Import::ACTION_CONFIRM;

	/** The `admin_post_` action the progress screen's Continue posts to. */
	const ACTION_CONTINUE = WPCPM_Institution_Import::ACTION_CONTINUE;

	/** The batch the preview is showing, in the address. */
	const ARG_BATCH = 'wpcpm_batch';

	/** Where a message about an import is left for the reader. */
	const FLASH = 'institution_import';

	/**
	 * What a refused check keeps of the form, one entry per institution, as `WPCPM_Flash::keep()`
	 * names a form: `import-batch:<institution record>` (1.123.58).
	 */
	const KEPT_FORM = 'import-batch:';

	/** The controls a refused check marks, by their ids, which the summary links to. */
	const FIELD_PROGRAM  = 'wpcpm-import-program';
	const FIELD_START    = 'wpcpm-import-start';
	const FIELD_END      = 'wpcpm-import-end';
	const FIELD_NOTIFIED = 'wpcpm-import-notified';
	const FIELD_FILE     = 'wpcpm-import-file';
	const FIELD_PASTE    = 'wpcpm-import-paste';

	/**
	 * The most bytes accepted from the paste box.
	 *
	 * The same ceiling the file route uses, because the two routes produce the same rows and a
	 * limit that applied to one of them would be a limit somebody could step around by
	 * selecting all and pressing paste.
	 *
	 * @var int
	 */
	const MAX_BYTES = WPCPM_Institution_Import::MAX_BYTES;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_CHECK, array( __CLASS__, 'handle_check' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_CONFIRM, array( __CLASS__, 'handle_confirm' ) );
		add_action( 'admin_post_' . self::ACTION_CONTINUE, array( __CLASS__, 'handle_continue' ) );
	}

	/**
	 * Whether this site takes imports at all.
	 *
	 * Off by default, like the application form. A school that has never been told about this
	 * seeing an upload box on its dashboard would be an invitation to guess at what the program
	 * wants, and the answer to that is a conversation rather than a form.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) WPCPM_Settings::get_value( 'import_enabled' );
	}

	/**
	 * Draw the import section: either the preview of a staged batch, or the form.
	 *
	 * `$context` is taken and not read. Every card on this page is called the same way by
	 * `WPCPM_Institutions_Dashboard::card()`, and a section that took a different shape would
	 * be a special case in that loop for no gain: this one needs the cohort filter and the
	 * manager flag that context carries about as much as the agreement panel does, which is
	 * not at all.
	 *
	 * @param string $record  Airtable Institutions record ID, already resolved by the page.
	 * @param array  $context The dashboard's context. Unused, and part of the shared shape.
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 * phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	 */
	public static function render( $record, array $context = array() ) {
		if ( ! self::enabled() || ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		// The same question the handlers ask, asked again here so the form is not drawn to a
		// reader who would be refused the moment they pressed it. A control that leads to a
		// refusal teaches somebody to distrust the screen.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_ADD_STUDENT,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		// Staged or part way through being created: both are a list this school has in hand,
		// and the preview is what shows either of them.
		$staged = WPCPM_Institution_Import::active_for( $record );

		// **Read once, here, and carried down.** The disclosure has to know whether there is a
		// message before the message is printed, and `WPCPM_Flash::take()` empties the channel.
		// It memoizes per request, so asking twice does work in production; relying on that
		// made the behavior depend on a subtlety two files away, and a test harness that
		// simulates several requests in one process could not honestly reproduce it. One read
		// and one argument is less to know.
		$flash = WPCPM_Flash::take( self::FLASH );
		$flash = is_array( $flash ) ? $flash : array();
		$said  = ! empty( $flash['status'] ) ? (string) $flash['status'] : '';

		// What a refused check kept of the form, taken here whichever branch is drawn, so a list
		// that is waiting does not leave the typing in the account's meta to fill a later form.
		$kept = WPCPM_Flash::kept( self::KEPT_FORM . $record );

		$finished = ( 0 === $staged && ! empty( $flash['detail']['batch'] ) ) ? (int) $flash['detail']['batch'] : 0;

		// **The card is the Enroll students view's content**, drawn with the shared parts (Institution Dashboard, Task 17): the view's
		// head already says "Enroll students", so the card prints no head of its own and no fold, and its block starts at
		// the head's edge, with the anchor every link and way back lands on.
		echo '<section class="wpcpm-ui-block" id="wpcpm-import">';

		// A refusal of the form is said at the form, after its intro and directly over it (Gate B round 2, F2); every
		// other outcome, and a refusal while a list is in hand, at the block's top.
		$at_form = 0 === $staged && 0 === $finished && '' !== $said && 'error' === self::tone( $said );

		if ( ! $at_form ) {
			self::render_message( $flash );
		}

		self::render_state( $staged, $finished, $record, $kept, $at_form ? $flash : array() );

		echo '</section>';
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

	/**
	 * The card's one state: a list in hand (checked, or being created), the outcome of one just
	 * finished, or the form.
	 *
	 * @param int    $staged   The batch in hand, or 0.
	 * @param int    $finished The batch the last press finished, or 0.
	 * @param string $record   Institutions record ID.
	 * @param array  $kept     What a refused check kept of the form.
	 * @param array  $refusal  The flash of a refusal the form says over itself, or none.
	 */
	private static function render_state( $staged, $finished, $record, array $kept, array $refusal = array() ) {
		if ( $staged > 0 ) {
			self::render_preview( $staged, $record );
		} elseif ( $finished > 0 ) {
			self::render_outcome( $finished, $record );
		} else {
			self::render_form( $record, $kept, $refusal );
		}
	}

	/**
	 * A form's hidden fields: the nonce and the page it was drawn on, with no id (`WPCPM_UI::nonce()`), since two of
	 * these forms sit side by side in one row; the handler's action and, for a list, its batch.
	 *
	 * @param string   $nonce  The nonce's action.
	 * @param string   $action The `admin_post_` action.
	 * @param int|null $batch  The batch the form acts on, or null for none.
	 */
	private static function render_hidden( $nonce, $action, $batch = null ) {
		echo WPCPM_UI::nonce( (string) $nonce ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::nonce().

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );

		if ( null !== $batch ) {
			printf( '<input type="hidden" name="batch" value="%d" />', (int) $batch );
		}
	}

	/**
	 * Markup a callable prints, as a string.
	 *
	 * @param callable $draw Prints markup.
	 * @return string
	 */
	private static function captured( callable $draw ) {
		ob_start();
		$draw();
		return (string) ob_get_clean();
	}

	/**
	 * The address every form on this card posts to.
	 *
	 * For an administrator it names the institution the page is showing, because a press that
	 * acts on nothing, such as Cancel on a list another tab has already thrown away, has no
	 * batch to read its institution from, and a post to `admin-post.php` carries the form's
	 * fields and none of the query string of the page it was on. Without it that press lands
	 * on the first institution with a member. A member's form posts to the bare address: their
	 * own membership places them. A press that acts on something still takes its institution
	 * from the batch and never from this address, unless what it acted on names no institution.
	 *
	 * @param string $record Institutions record ID the page is showing.
	 * @return string
	 */
	private static function post_url( $record ) {
		$url    = admin_url( 'admin-post.php' );
		$record = trim( (string) $record );

		if ( '' !== $record && current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$url = add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, $record, $url );
		}

		return $url;
	}

	/**
	 * The form itself.
	 *
	 * The batch-wide answers come first, because they are properties of the whole import
	 * rather than of a student: the program somebody is joining and the term they start are
	 * chosen once, and a file carrying its own copy of either may only agree.
	 *
	 * The switcher travels on the action address for an administrator, and only for one: the
	 * check works out which institution it is acting for from the request, and a post to
	 * `admin-post.php` carries the form's fields and none of the query string of the page it
	 * was on. Without it an administrator viewing any institution but their fallback meets a
	 * nonce keyed to a different record. A member's own membership places them, and the
	 * address stays what it was. See `post_url()`.
	 *
	 * A refused check comes back with every answer as it was posted (1.123.58): the program, both
	 * dates, the tick, the one-student fields and the pasted list, and each refused answer marked at
	 * its own control under a summary over the form. A chosen file is the one answer a browser never
	 * hands back, so its input asks for it again; a pasted list too long to keep asks for the paste
	 * again.
	 *
	 * @param string $record Institutions record ID.
	 * @param array  $kept    What `WPCPM_Flash::kept()` handed back for this form.
	 * @param array  $refusal The flash of a refusal to say over the form, or none.
	 */
	private static function render_form( $record, array $kept = array(), array $refusal = array() ) {
		$typed    = isset( $kept['values'] ) && is_array( $kept['values'] ) ? $kept['values'] : array();
		$problems = isset( $kept['problems'] ) && is_array( $kept['problems'] ) ? $kept['problems'] : array();
		$value    = static function ( $field ) use ( $typed ) {
			return isset( $typed[ $field ] ) && is_scalar( $typed[ $field ] ) ? (string) $typed[ $field ] : '';
		};
		$said     = static function ( $id ) use ( $problems ) {
			return isset( $problems[ $id ] ) && is_string( $problems[ $id ] ) ? $problems[ $id ] : '';
		};
		// The programs a school may choose, asked once for either branch: the server's gated map (decision 10).
		$programs = WPCPM_Institutions::offered_programs();

		self::render_form_parts( $record, $problems, $programs, $value, $said, $refusal );
	}

	/**
	 * The form on the shared parts (design 6.2): the lede's fact, then one form posting what today's posts, under the same
	 * ids. Program a select (at most 320px), Start date and End date 200px date boxes (End Optional, the rest of today's
	 * label its hint), the notified tick its own question, then two field sets: One student, and Or a list (the file
	 * box, the sample list's link, the paste box); Check the list. A refused answer keeps its typing and its sentence
	 * under its box, as today.
	 *
	 * @param string   $record   Institutions record ID.
	 * @param array    $problems The refused controls' sentences, by id.
	 * @param array    $programs The programs offered, status to label.
	 * @param callable $value    A kept answer by field name.
	 * @param callable $said     A refusal by control id.
	 * @param array    $refusal  The flash of a refusal to say over the form, after its intro, or none.
	 */
	private static function render_form_parts( $record, array $problems, array $programs, callable $value, callable $said, array $refusal = array() ) {
		// The lede's fact alone: its first half ("Add one student, or a list of them.") repeats the view's head, which says
		// what the view is; that nothing is created before the list is seen is said nowhere else (the controller's ruling).
		echo WPCPM_UI::intro( __( 'Nothing is created until you have seen what the list was understood to say.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

		self::render_message( $refusal );

		printf(
			'<form method="post" enctype="multipart/form-data" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( self::post_url( $record ) ),
			esc_attr__( 'Checking the list', 'wpcredits-program-manager' )
		);

		self::render_hidden( self::ACTION_CHECK . '_' . $record, self::ACTION_CHECK );

		echo WPCPM_Form_Errors::summary( $problems, self::field_map(), array( 'level' => 3 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Form_Errors::summary() escapes every value it prints.

		$out = WPCPM_UI::field(
			array(
				'id'      => self::FIELD_PROGRAM,
				'name'    => 'program',
				'label'   => __( 'Program', 'wpcredits-program-manager' ),
				'type'    => 'select',
				'options' => $programs,
				'value'   => $value( 'program' ),
				'problem' => $said( self::FIELD_PROGRAM ),
			)
		);

		$out .= WPCPM_UI::field(
			array(
				'id'      => self::FIELD_START,
				'name'    => 'start',
				'label'   => __( 'Start date', 'wpcredits-program-manager' ),
				'type'    => 'date',
				'need'    => 'required',
				'hint'    => __( 'The term these students begin. A date more than a year either side of today is refused as a typo.', 'wpcredits-program-manager' ),
				'value'   => $value( 'start' ),
				'problem' => $said( self::FIELD_START ),
			)
		);

		// Today's label "End date, if you know it" is split (parts design 2.4): its first clause the label, Optional in words,
		// and the rest the hint, before today's hint.
		$out .= WPCPM_UI::field(
			array(
				'id'      => self::FIELD_END,
				'name'    => 'end',
				'label'   => __( 'End date', 'wpcredits-program-manager' ),
				'type'    => 'date',
				'need'    => 'optional',
				'hint'    => __( 'If you know it.', 'wpcredits-program-manager' ) . ' ' . __( 'After the start date, and at most a year after it.', 'wpcredits-program-manager' ),
				'value'   => $value( 'end' ),
				'problem' => $said( self::FIELD_END ),
			)
		);

		$out .= WPCPM_UI::choices(
			array(
				'id'      => self::FIELD_NOTIFIED,
				'name'    => 'notified',
				'options' => array( '1' => self::notified_label() ),
				'checked' => $value( 'notified' ),
				'need'    => 'required',
				'problem' => $said( self::FIELD_NOTIFIED ),
			)
		);

		$out .= self::set_open( __( 'One student', 'wpcredits-program-manager' ) );

		foreach ( self::single_fields() as $name => $label ) {
			$options = self::options_for( $name, $record );
			$field   = array(
				'id'    => 'wpcpm-import-' . $name,
				'name'  => $name,
				'label' => $label,
				'value' => $value( $name ),
			);

			if ( null !== $options ) {
				// Blank first, as today: neither is required, and a picker that opens on a real answer files a cohort under it.
				$choices = array( '' => __( 'Not recorded', 'wpcredits-program-manager' ) );

				foreach ( $options as $option ) {
					$choices[ (string) $option ] = (string) $option;
				}

				$field['type']    = 'select';
				$field['options'] = $choices;
				$field['hint']    = self::tutor_hint( $name, $options );
			}

			$out .= WPCPM_UI::field( $field );
		}

		$out .= '</fieldset>' . self::set_open( __( 'Or a list', 'wpcredits-program-manager' ) );

		$out .= WPCPM_UI::file(
			array(
				'id'      => self::FIELD_FILE,
				'name'    => 'list',
				'label'   => __( 'A CSV file', 'wpcredits-program-manager' ),
				'accept'  => '.csv,text/csv',
				'hint'    => self::file_hint(),
				'problem' => $said( self::FIELD_FILE ),
			)
		);

		// The sample list's link, the export class's own (see render_form()), on its line under the file box.
		if ( class_exists( 'WPCPM_Institution_Export' ) ) {
			$out .= WPCPM_UI::intro( '', '', '', '<a href="' . esc_url( WPCPM_Institution_Export::sample_url( $record ) ) . '">' . esc_html__( 'Download a sample list', 'wpcredits-program-manager' ) . '</a>' );
		}

		$out .= WPCPM_UI::field(
			array(
				'id'      => self::FIELD_PASTE,
				'name'    => 'paste',
				'label'   => __( 'Or paste the same thing here', 'wpcredits-program-manager' ),
				'type'    => 'textarea',
				'rows'    => 6,
				'hint'    => __( 'Useful when the list is in a spreadsheet you would rather not download.', 'wpcredits-program-manager' ),
				'value'   => $value( 'paste' ),
				'problem' => $said( self::FIELD_PASTE ),
			)
		);

		$out .= '</fieldset>' . WPCPM_UI::actions(
			array(
				WPCPM_UI::button(
					array(
						'label' => __( 'Check the list', 'wpcredits-program-manager' ),
						'side'  => 'front',
					)
				),
			)
		);

		echo $out . '</form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts, each value through esc_html() or esc_attr() there.
	}

	/**
	 * The opening of a set of fields on the parts: the choice group's fieldset (no border), its legend an in-card
	 * heading. No part prints a set of fields; the caller closes it.
	 *
	 * @param string $legend The set's name.
	 * @return string
	 */
	private static function set_open( $legend ) {
		return '<fieldset class="wpcpm-ui-choices wpcpm-ui-choices--set"><legend class="wpcpm-ui-h">' . esc_html( $legend ) . '</legend>';
	}

	/**
	 * The file box's hint: the limit, said where the file is chosen (1.123.58, IDB-16), then the format.
	 *
	 * @return string
	 */
	private static function file_hint() {
		return sprintf(
			/* translators: %s: the largest file the import reads, such as 256 KB. */
			__( 'A CSV file of %s at most.', 'wpcredits-program-manager' ),
			size_format( self::MAX_BYTES )
		)
		. ' '
		. sprintf(
			/* translators: %s: the largest number of students one import may carry. */
			__( 'A header row, then one student per line. Name and email are needed; field of study and tutor are read when they are there. Up to %s students. The file is read and never stored.', 'wpcredits-program-manager' ),
			number_format_i18n( WPCPM_Institution_Import::MAX_ROWS )
		);
	}

	/**
	 * The one-student fields, in the order they are asked for.
	 *
	 * @return array<string, string> Field name to label.
	 */
	private static function single_fields() {
		// **No WordPress.org profile.** Getting one is the student's own first step of
		// onboarding, after they are enrolled, so at the moment a school fills this in nobody
		// has one to give: a box for it collects blanks at best and somebody's guess at worst.
		// The CSV route still reads a `profile` column for the school that does happen to hold
		// them, because taking a value somebody has is different from asking for one they do
		// not.
		return array(
			'name'           => __( 'Full name', 'wpcredits-program-manager' ),
			'email'          => __( 'Email address', 'wpcredits-program-manager' ),
			'field_of_study' => __( 'Field of study', 'wpcredits-program-manager' ),
			'tutor'          => __( 'Tutor', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The words of the tick, which is its own label.
	 *
	 * @return string
	 */
	private static function notified_label() {
		return __( 'These students have been notified that their name and email address are shared with the WordPress Foundation for the WordPress Credits Program.', 'wpcredits-program-manager' );
	}

	/**
	 * The controls a refused check can mark, in the form's order, each with its id and its label,
	 * for `WPCPM_Form_Errors::summary()` (1.123.58).
	 *
	 * The end date's label is "End date", its rest the hint (Institution Dashboard, Task 17), so the summary names it by
	 * that label.
	 *
	 * @return array<string, array{id: string, label: string}>
	 */
	private static function field_map() {
		$labels = array(
			self::FIELD_PROGRAM  => __( 'Program', 'wpcredits-program-manager' ),
			self::FIELD_START    => __( 'Start date', 'wpcredits-program-manager' ),
			self::FIELD_END      => __( 'End date', 'wpcredits-program-manager' ),
			self::FIELD_NOTIFIED => self::notified_label(),
			self::FIELD_FILE     => __( 'A CSV file', 'wpcredits-program-manager' ),
			self::FIELD_PASTE    => __( 'Or paste the same thing here', 'wpcredits-program-manager' ),
		);
		$map    = array();

		foreach ( $labels as $id => $label ) {
			$map[ $id ] = array(
				'id'    => $id,
				'label' => $label,
			);
		}

		return $map;
	}

	/**
	 * The sentence under the tutor picker while the school lists no tutor, saying where to add them, or '' for any other
	 * field or when tutors are listed. It names the Tutors section of Your institution, since no card there is called
	 * People (Stage B, Task 20).
	 *
	 * @param string        $name    The field.
	 * @param string[]|null $options What the field offers (`options_for()`).
	 * @return string
	 */
	private static function tutor_hint( $name, $options ) {
		if ( 'tutor' === $name && empty( $options ) ) {
			return __( 'No tutors are listed for your institution yet. Add them under Tutors on Your institution, and they will appear here.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * The answers a field offers, or null when it is free text.
	 *
	 * **Both lists are the base's own.** `Your field of study` is a single-select in Airtable
	 * and `create_records()` sends no typecast, so a value spelled any other way is a 422 for
	 * the whole record: a text box here is a box in which a school can only get it wrong.
	 * Tutors are the institution's own list, kept on the site (`WPCPM_Institution_Tutors`), and
	 * the list offered is that and never another school's.
	 *
	 * The file route is deliberately not held to these. A school adding a tutor the base has
	 * not heard of yet should not have their whole term refused for it, so a CSV keeps free
	 * text and the cleaner's own rules; what the picker buys is that nobody types a name into
	 * the one-student form that matches nothing.
	 *
	 * @param string $name   The field.
	 * @param string $record Institutions record ID.
	 * @return string[]|null
	 */
	private static function options_for( $name, $record ) {
		if ( 'field_of_study' === $name ) {
			return class_exists( 'WPCPM_Institution_Student_Form' )
				? (array) WPCPM_Institution_Student_Form::choices( 'field_of_study' )
				: null;
		}

		if ( 'tutor' === $name ) {
			// **Always a picker, even when the answer is empty.** Tutors are the school's own list and
			// a free-text box invites a name that matches nobody on it, which is how the column filled
			// up with spellings in the first place. A school with none listed gets a picker holding
			// only the blank answer and the sentence beside it saying where to add them, which is a
			// truer thing to show than a box implying the site will take whatever they type.
			return WPCPM_Institution_Import::tutors( $record );
		}

		return null;
	}

	/**
	 * The sentence the card prints above a checked list not yet created (how many of its rows can be created) or
	 * above a creation in progress (how far it is), which the Institution Dashboard's Overview reads as its
	 * waiting row's why. Not escaped: a caller escapes it where it prints it.
	 *
	 * @param int    $batch_id The batch, as `WPCPM_Institution_Import::active_for()` finds it.
	 * @param string $record   The Institutions record ID the sentence is asked for: another school's batch says nothing.
	 * @param array  $batch    The batch, when the caller has already read it (`WPCPM_Institution_Import::batch()`),
	 *                         so it is not read again; read here when empty or of another number.
	 * @return string '' for a number that is no batch, a batch of another school, or a batch neither checked nor
	 *                being created.
	 */
	public static function waiting_sentence( int $batch_id, string $record, array $batch = array() ): string {
		if ( ! isset( $batch['id'] ) || (int) $batch['id'] !== $batch_id ) {
			$batch = WPCPM_Institution_Import::batch( $batch_id );
		}

		if ( ! is_array( $batch ) || '' === $record || $batch['institution'] !== $record || ! in_array( $batch['state'], array( WPCPM_Institution_Import::STATE_STAGED, WPCPM_Institution_Import::STATE_CREATING ), true ) ) {
			return '';
		}

		return self::batch_sentence( $batch );
	}

	/**
	 * The sentence over a batch already read, as the card prints it: how far a creation in progress is, and for
	 * a batch in any other state how many of its rows can be created. `waiting_sentence()` asks it for a waiting
	 * batch, and the card that holds the batch prints it without reading the batch again.
	 *
	 * @param array $batch The batch.
	 * @return string
	 */
	private static function batch_sentence( array $batch ) {
		$rows = $batch['rows'];

		if ( WPCPM_Institution_Import::STATE_CREATING === $batch['state'] ) {
			$tally = WPCPM_Institution_Create::tally( $rows );

			return sprintf(
				/* translators: 1: students created so far, 2: how many are still to come. */
				__( '%1$s created so far. %2$s still to create.', 'wpcredits-program-manager' ),
				number_format_i18n( $tally['created'] ),
				number_format_i18n( WPCPM_Institution_Create::remaining( $rows ) )
			);
		}

		$counts = self::counts( $rows );

		return sprintf(
			/* translators: 1: how many students would be created, 2: how many rows the list held. */
			__( '%1$s of %2$s rows can be created.', 'wpcredits-program-manager' ),
			number_format_i18n( $counts['ok'] ),
			number_format_i18n( count( $rows ) )
		);
	}

	/**
	 * The preview of a staged batch.
	 *
	 * **Every row is shown, including the ones that will not be created.** A school that sent
	 * thirty students and is about to get twenty-seven has to be told which three and why, on
	 * the same screen and before anything happens, rather than reading a total afterwards and
	 * guessing.
	 *
	 * @param int    $batch_id The staged batch.
	 * @param string $record   Institutions record ID, for the nonce and for the forms' address.
	 */
	private static function render_preview( $batch_id, $record ) {
		$batch = WPCPM_Institution_Import::batch( $batch_id );

		if ( ! is_array( $batch ) || $batch['institution'] !== $record ) {
			return;
		}

		if ( WPCPM_Institution_Import::STATE_CREATING === $batch['state'] ) {
			self::render_progress( $batch, $record );

			return;
		}

		$counts   = self::counts( $batch['rows'] );
		$question = self::confirm_question( $batch, $counts['ok'] );

		self::render_preview_parts( (int) $batch_id, $batch, $question, $record );
	}

	/**
	 * The check on the shared parts (design 6.2): today's count sentence, the ignored columns line, the check table, then
	 * today's question and one row holding Create these student records (primary, asking it first) and Throw this list
	 * away (danger), each the form it is today.
	 *
	 * @param int    $batch_id The staged batch.
	 * @param array  $batch    The batch.
	 * @param string $question The question Create asks, or '' when no row can be created.
	 * @param string $record   Institutions record ID the page is showing.
	 */
	private static function render_preview_parts( $batch_id, array $batch, $question, $record ) {
		$out = WPCPM_UI::intro( self::batch_sentence( $batch ) );

		if ( '' !== self::unknown_sentence( $batch ) ) {
			$out .= WPCPM_UI::intro( self::unknown_sentence( $batch ), 'muted' );
		}

		$out  .= self::rows_table( $batch['rows'] );
		$forms = array();

		if ( '' !== $question ) {
			$out .= WPCPM_UI::intro( '', '', '', self::question_html( $batch, $question ) );

			$forms[] = sprintf(
				'<form method="post" action="%1$s" data-wpcpm-confirm="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
				esc_url( self::post_url( $record ) ),
				esc_attr( $question ),
				esc_attr__( 'Creating the records', 'wpcredits-program-manager' )
			) . self::captured(
				static function () use ( $batch_id ) {
					self::render_hidden( WPCPM_Institution_Create::confirm_action( $batch_id ), self::ACTION_CONFIRM, $batch_id );
				}
			) . WPCPM_UI::button(
				array(
					'label' => __( 'Create these student records', 'wpcredits-program-manager' ),
					'side'  => 'front',
				)
			) . '</form>';
		}

		$forms[] = sprintf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( self::post_url( $record ) ),
			esc_attr__( 'Canceling', 'wpcredits-program-manager' )
		) . self::captured(
			static function () use ( $batch_id ) {
				self::render_hidden( self::ACTION_CANCEL . '_' . $batch_id, self::ACTION_CANCEL, $batch_id );
			}
		) . WPCPM_UI::button(
			array(
				'label' => __( 'Throw this list away', 'wpcredits-program-manager' ),
				'kind'  => 'danger',
				'side'  => 'front',
			)
		) . '</form>';

		echo $out . WPCPM_UI::actions( $forms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and above.
	}

	/**
	 * The line naming the columns a list carried that the import does not read, or '' when it carried none.
	 *
	 * @param array $batch The batch.
	 * @return string
	 */
	private static function unknown_sentence( array $batch ) {
		if ( empty( $batch['unknown'] ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: the column headings the import did not read. */
			__( 'Columns this import does not read, and ignored: %s', 'wpcredits-program-manager' ),
			implode(
				', ',
				array_map(
					static function ( $heading ) {
						return sanitize_text_field( $heading );
					},
					$batch['unknown']
				)
			)
		);
	}

	/**
	 * The question Create asks first, and the sentence it makes somebody read, or '' when nothing can be created.
	 *
	 * **Everything that is about to happen, and everything that is not.** The count, the
	 * institution's own program and start date, that no mail goes out now, when the student
	 * will hear from anybody, and the one instruction that prevents the duplicate this plugin
	 * cannot close: a student who is imported and then sent to the public registration form
	 * creates a second record under their own hand, and no ladder here can tell that from a
	 * new enrollment.
	 *
	 * Nothing to create is not a button. A school whose whole list was blocked reads the rows and
	 * throws the list away; a Confirm here would do nothing and would teach them that the button
	 * sometimes does nothing. Both branches ask this, so the rule is written once.
	 *
	 * @param array $batch    The batch.
	 * @param int   $creating How many rows would be created.
	 * @return string
	 */
	private static function confirm_question( array $batch, $creating ) {
		if ( $creating < 1 ) {
			return '';
		}

		$values = isset( $batch['values'] ) && is_array( $batch['values'] ) ? $batch['values'] : array();

		return sprintf(
			/* translators: 1: how many student records, 2: the program's name, 3: the start date. */
			__( 'Create %1$s student records on the %2$s program, starting %3$s. No email is sent now. The program emails each student when their place is set up, and they receive their login when their account is created. Do not send these students to the registration form; it would create a second record.', 'wpcredits-program-manager' ),
			number_format_i18n( $creating ),
			WPCPM_Program::label( isset( $values['status'] ) ? (string) $values['status'] : '' ),
			isset( $values['start'] ) ? (string) $values['start'] : ''
		);
	}

	/**
	 * The question as the parts print it: escaped, its start date one run that never breaks (`WPCPM_UI::whole()`).
	 *
	 * @param array  $batch    The batch.
	 * @param string $question The question.
	 * @return string Escaped markup.
	 */
	private static function question_html( array $batch, $question ) {
		$html  = esc_html( $question );
		$start = isset( $batch['values']['start'] ) ? esc_html( (string) $batch['values']['start'] ) : '';
		$at    = '' !== $start ? strrpos( $html, $start ) : false;

		// The date is the sentence's last placeholder, so its last occurrence is the date.
		return false === $at ? $html : substr( $html, 0, $at ) . WPCPM_UI::whole( (string) $batch['values']['start'] ) . substr( $html, $at + strlen( $start ) );
	}

	/**
	 * A batch part way through, and the control that carries it on.
	 *
	 * Continue is offered as well as the scheduled continuation, not instead of it. Cron on a
	 * quiet site runs when somebody visits, which for an institution's dashboard may be
	 * tomorrow; a person watching a half-finished import should not have to wait for a visitor.
	 *
	 * On the parts: today's two sentences, the rows not created as the check table, Continue (secondary).
	 *
	 * @param array  $batch  The batch being created.
	 * @param string $record Institutions record ID the page is showing.
	 */
	private static function render_progress( array $batch, $record ) {
		echo WPCPM_UI::intro( self::batch_sentence( $batch ) ) . WPCPM_UI::intro( self::progress_note(), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

		self::render_exceptions( $batch['rows'] );

		// Continue creates the next records, so it says the words Confirm says while it works.
		printf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( self::post_url( $record ) ),
			esc_attr__( 'Creating the records', 'wpcredits-program-manager' )
		);
		self::render_hidden( WPCPM_Institution_Create::continue_action( $batch['id'] ), self::ACTION_CONTINUE, (int) $batch['id'] );

		$continue = WPCPM_UI::button(
			array(
				'label' => __( 'Continue', 'wpcredits-program-manager' ),
				'kind'  => 'secondary',
				'side'  => 'front',
			)
		);

		echo WPCPM_UI::actions( array( $continue ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.

		echo '</form>';
	}

	/**
	 * Said rather than left to be worked out from a button that is not there: a batch with records behind it cannot be
	 * thrown away, because deleting the post would leave those students with a `Site import key` pointing at a batch that
	 * no longer exists.
	 *
	 * @return string
	 */
	private static function progress_note() {
		return __( 'This import has already created records, so it cannot be thrown away. It carries on by itself, and Continue picks it up now.', 'wpcredits-program-manager' );
	}

	/**
	 * What one row shows: its line, its name (today's words for none), its address and what happens to it.
	 *
	 * @param array $row A checked row.
	 * @return array{line: int, name: string, named: bool, email: string, says: string}
	 */
	private static function row_cells( array $row ) {
		$named = isset( $row['name'] ) && '' !== $row['name'];

		return array(
			'line'  => isset( $row['line'] ) ? (int) $row['line'] : 0,
			'name'  => $named ? (string) $row['name'] : __( '(no name)', 'wpcredits-program-manager' ),
			'named' => $named,
			'email' => isset( $row['email'] ) ? (string) $row['email'] : '',
			'says'  => self::verdict_sentence( $row ),
		);
	}

	/**
	 * The check table's columns (design 6): Line, Name, Email address (a token: it may break anywhere), What happens.
	 *
	 * @return array[]
	 */
	private static function check_columns() {
		return array(
			array(
				'key'   => 'line',
				'label' => __( 'Line', 'wpcredits-program-manager' ),
				'kind'  => 'num',
			),
			array(
				'key'   => 'name',
				'label' => __( 'Name', 'wpcredits-program-manager' ),
				'kind'  => 'text',
			),
			array(
				'key'   => 'email',
				'label' => __( 'Email address', 'wpcredits-program-manager' ),
				'kind'  => 'token',
			),
			array(
				'key'   => 'says',
				'label' => __( 'What happens', 'wpcredits-program-manager' ),
				'kind'  => 'text',
			),
		);
	}

	/**
	 * Rows as the check table on the parts: each row's line, its name through `WPCPM_UI::breakable()` (today's words in
	 * the empty kind when it has none), its address and what happens to it, in words. It stacks under 800px of main area.
	 *
	 * @param array $rows Checked rows.
	 * @return string Escaped markup.
	 */
	private static function rows_table( array $rows ) {
		$columns = self::check_columns();
		$out     = WPCPM_UI::table_open( $columns );

		foreach ( $rows as $row ) {
			$cells = self::row_cells( $row );
			$out  .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $columns[0], esc_html( number_format_i18n( $cells['line'] ) ) )
				. WPCPM_UI::cell( $columns[1], WPCPM_UI::breakable( $cells['name'] ), $cells['named'] ? '' : 'empty' )
				. WPCPM_UI::cell( $columns[2], esc_html( $cells['email'] ) )
				. WPCPM_UI::cell( $columns[3], esc_html( $cells['says'] ) )
				. WPCPM_UI::row_close();
		}

		return $out . WPCPM_UI::table_close();
	}

	/**
	 * What a finished import did, once, on the way back from it.
	 *
	 * Shown from the flash rather than from a query for a finished batch, because a batch that
	 * has stopped must not keep a school from importing again: what they need after a stopped
	 * import is to send the rest of the list, and the students already created are caught by
	 * the duplicate ladder because each was put on this roster the moment it was made.
	 *
	 * @param int    $batch_id The batch the flash named.
	 * @param string $record   Institutions record ID, to check the batch is this school's.
	 */
	private static function render_outcome( $batch_id, $record ) {
		$batch = WPCPM_Institution_Import::batch( $batch_id );

		if ( ! is_array( $batch ) || $batch['institution'] !== $record ) {
			return;
		}

		$tally = WPCPM_Institution_Create::tally( $batch['rows'] );
		$said  = sprintf(
			/* translators: 1: students created, 2: rows not created, 3: rows the program records refused. */
			__( '%1$s created, %2$s blocked, %3$s failed.', 'wpcredits-program-manager' ),
			number_format_i18n( $tally['created'] ),
			number_format_i18n( $tally['blocked'] ),
			number_format_i18n( $tally['failed'] )
		);

		echo WPCPM_UI::intro( $said ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

		self::render_exceptions( $batch['rows'] );
	}

	/**
	 * The rows of a batch that did not simply get created.
	 *
	 * Only those: a list of two hundred students who were created exactly as asked is a list
	 * nobody reads, and burying the three that were not in it is how a school comes back a
	 * week later asking where somebody went. On the parts they are the check table.
	 *
	 * @param array $rows The batch's rows.
	 */
	private static function render_exceptions( array $rows ) {
		$shown = array();

		foreach ( $rows as $row ) {
			$state = WPCPM_Institution_Create::state_of( $row );

			if ( in_array( $state, array( WPCPM_Institution_Create::ROW_FAILED, WPCPM_Institution_Create::ROW_BLOCKED ), true ) ) {
				$shown[] = $row;
			}
		}

		if ( empty( $shown ) ) {
			return;
		}

		echo self::rows_table( $shown ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in rows_table().
	}

	/**
	 * What one row says to the school, before creating and after it.
	 *
	 * A row the base refused says so in the base's own words: that message is the only account
	 * of what went wrong with that one student, and a paraphrase would cost a program manager
	 * the clue. A row whose answer changed between the preview somebody read and the moment it
	 * was about to be created is prefaced with that fact, because otherwise a school reads a
	 * refusal on a line the screen told them ten seconds earlier was ready.
	 *
	 * @param array $row A checked row.
	 * @return string
	 */
	private static function verdict_sentence( array $row ) {
		if ( WPCPM_Institution_Create::ROW_FAILED === WPCPM_Institution_Create::state_of( $row ) ) {
			return sprintf(
				/* translators: %s: the message the program records answered with, word for word. */
				__( 'The program records refused this row: %s', 'wpcredits-program-manager' ),
				isset( $row['error'] ) ? (string) $row['error'] : ''
			);
		}

		if ( empty( $row['changed'] ) ) {
			return self::sentence_for( $row );
		}

		return sprintf(
			/* translators: %s: the sentence describing what the second check found. */
			__( 'Checked again just before creating, and the answer had changed. %s', 'wpcredits-program-manager' ),
			self::sentence_for( $row )
		);
	}

	/**
	 * What one row's verdict says to the school.
	 *
	 * **The blocked sentence is one sentence and is written once, here.** It is the same
	 * whether the address belongs to a student at another university, to a row nobody has
	 * linked, or to an account on this site: answering those separately would let anybody who
	 * can paste three hundred addresses read three hundred answers about who is in the
	 * program. The reason is on the batch for a program manager, and never on this screen.
	 *
	 * @param array $row A checked row.
	 * @return string
	 */
	private static function sentence_for( array $row ) {
		$verdict = isset( $row['verdict'] ) ? (string) $row['verdict'] : WPCPM_Institution_Import::OK;
		$detail  = isset( $row['detail'] ) && is_array( $row['detail'] ) ? $row['detail'] : array();

		if ( WPCPM_Institution_Import::OK === $verdict ) {
			return __( 'Ready to create.', 'wpcredits-program-manager' );
		}

		if ( WPCPM_Institution_Import::BLOCKED === $verdict ) {
			return __( 'This student cannot be imported from here. Ask an Administrator.', 'wpcredits-program-manager' );
		}

		if ( WPCPM_Institution_Import::DUPLICATE_FILE === $verdict ) {
			return sprintf(
				/* translators: %s: another line number in the same file. */
				__( 'The same person as line %s in this list. Neither is created.', 'wpcredits-program-manager' ),
				number_format_i18n( isset( $row['duplicate_of'] ) ? (int) $row['duplicate_of'] : 0 )
			);
		}

		if ( WPCPM_Institution_Import::EXISTS_HERE === $verdict ) {
			if ( ! empty( $detail['reports_only'] ) ) {
				return __( 'This student has a program record but no enrollment record. An Administrator needs to complete it.', 'wpcredits-program-manager' );
			}

			return sprintf(
				/* translators: 1: the student's name as the program holds it, 2: their status. */
				__( 'Already on your roster: %1$s, %2$s.', 'wpcredits-program-manager' ),
				isset( $detail['name'] ) ? (string) $detail['name'] : '',
				isset( $detail['status'] ) ? (string) $detail['status'] : ''
			);
		}

		if ( WPCPM_Institution_Import::NEAR_NAME === $verdict ) {
			return sprintf(
				/* translators: %s: a name already on this institution's roster. */
				__( 'Similar name on your roster: %s. This one will still be created.', 'wpcredits-program-manager' ),
				isset( $detail['near'] ) ? (string) $detail['near'] : ''
			);
		}

		return self::problem_sentence( $row );
	}

	/**
	 * Why a row failed cleaning, in words.
	 *
	 * Named rather than counted: a school that pasted thirty students and got twenty-eight has
	 * to be able to fix the two, and "two rows were invalid" is not something anybody can act
	 * on.
	 *
	 * @param array $row A checked row.
	 * @return string
	 */
	private static function problem_sentence( array $row ) {
		$problems = isset( $row['problems'] ) && is_array( $row['problems'] ) ? $row['problems'] : array();
		$words    = array(
			'name_missing'  => __( 'No name.', 'wpcredits-program-manager' ),
			'name_formula'  => __( 'The name starts with a character a spreadsheet reads as a formula.', 'wpcredits-program-manager' ),
			'name_control'  => __( 'The name carries a tab or a line break.', 'wpcredits-program-manager' ),
			'name_length'   => __( 'The name is too short or too long.', 'wpcredits-program-manager' ),
			'email_missing' => __( 'No email address.', 'wpcredits-program-manager' ),
			'email_invalid' => __( 'That is not an email address.', 'wpcredits-program-manager' ),
			'email_sample'  => __( 'This is the sample list\'s example student. Replace it with one of your students, or delete the row.', 'wpcredits-program-manager' ),
		);

		$said = array();

		foreach ( $problems as $problem ) {
			if ( isset( $words[ $problem ] ) ) {
				$said[] = $words[ $problem ];
			}
		}

		return empty( $said ) ? __( 'This row cannot be read.', 'wpcredits-program-manager' ) : implode( ' ', $said );
	}

	/**
	 * How many rows fall under each verdict.
	 *
	 * @param array $rows Checked rows.
	 * @return array<string, int>
	 */
	public static function counts( array $rows ) {
		$counts = array(
			'ok'      => 0,
			'blocked' => 0,
			'other'   => 0,
		);

		foreach ( $rows as $row ) {
			$verdict = isset( $row['verdict'] ) ? (string) $row['verdict'] : WPCPM_Institution_Import::OK;

			if ( WPCPM_Institution_Import::OK === $verdict || WPCPM_Institution_Import::NEAR_NAME === $verdict ) {
				++$counts['ok'];
			} elseif ( WPCPM_Institution_Import::BLOCKED === $verdict ) {
				++$counts['blocked'];
			} else {
				++$counts['other'];
			}
		}

		return $counts;
	}

	/**
	 * Check a submitted list and stage it.
	 *
	 * **The order is the module's rule and not a preference.** The institution is resolved from
	 * the reader's own stamp or from a manager's switcher and never from the form; the cheap
	 * decision refuses before anything else happens; the nonce is checked before a single
	 * request reaches Airtable; and the ceilings are claimed before a byte of the file is
	 * parsed. A cross-site post therefore costs this site nothing at all.
	 */
	public static function handle_check() {
		if ( ! self::enabled() ) {
			self::bounce( 'off' );
		}

		$institution = WPCPM_Institution_Roster::resolve_institution(
			wp_get_current_user(),
			current_user_can( WPCPM_Roles::CAP_MANAGE )
		);

		if ( '' === $institution ) {
			self::bounce( 'refused' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_ADD_STUDENT,
			WPCPM_Institution_Policy::subject_institution( $institution )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( self::ACTION_CHECK . '_' . $institution );

		// What an earlier refusal kept is stale once the form is posted again, whatever this press
		// comes to (1.123.58).
		WPCPM_Flash::forget( self::KEPT_FORM . $institution );

		// From here every way out comes back to `$institution`: the one the page resolved and the
		// nonce above is keyed to, which is what this press acted on whatever else was posted.
		// Every refusal from here keeps what was posted (`refuse()`), so the form comes back as it
		// was sent, the refused answer marked at its control.
		// Staged or being created: either is a list this school already has in hand, and a
		// second one would put two imports on one roster behind one lock.
		if ( WPCPM_Institution_Import::active_for( $institution ) > 0 ) {
			self::refuse( 'already-staged', array(), $institution );
		}

		// The batch-wide answers first: a press refused for a date, the program or the tick costs
		// none of the checks an hour allows, which only a list that reaches the parse and the base
		// spends.
		$batch = self::batch_values();

		if ( '' !== $batch['problem'] ) {
			self::refuse( $batch['problem'], array(), $institution, $batch['field'] );
		}

		$allowed = WPCPM_Institution_Import::may_check( $institution );

		if ( empty( $allowed['ok'] ) ) {
			self::refuse( $allowed['problem'], array(), $institution );
		}

		$sent = self::submitted_text();

		if ( is_wp_error( $sent ) ) {
			// A chosen file is the file's answer, too big or unreadable: never a fall through to
			// the paste box, which may hold a list kept from an earlier refusal, nor "nothing was
			// sent" over a file that was chosen.
			self::refuse( (string) $sent->get_error_code(), array(), $institution, self::FIELD_FILE );
		}

		if ( '' === $sent['text'] ) {
			self::refuse( 'nothing-sent', array(), $institution );
		}

		$parsed = WPCPM_Institution_Import::parse( $sent['text'], $batch['values'] );

		if ( empty( $parsed['ok'] ) ) {
			self::refuse( 'parse-' . $parsed['problem'], $parsed['detail'], $institution, $sent['field'] );
		}

		$claimed = WPCPM_Institution_Import::claim_rows( $institution, count( $parsed['rows'] ) );

		if ( empty( $claimed['ok'] ) ) {
			self::refuse( $claimed['problem'], array(), $institution );
		}

		$rows = WPCPM_Institution_Import::clean_rows( $parsed['rows'] );

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$checked  = WPCPM_Institution_Import::check_against_base( $rows, $institution, $airtable, $settings );

		if ( is_wp_error( $checked ) ) {
			// The base being unreachable is not a refusal, and saying so keeps a school from
			// looking for a permissions fault that does not exist.
			self::refuse( 'unreadable', array(), $institution );
		}

		$staged = WPCPM_Institution_Import::stage( $institution, get_current_user_id(), $batch['values'], $checked, $parsed['unknown'] );

		if ( ! $staged ) {
			self::refuse( 'not-staged', array(), $institution );
		}

		self::leave( 'checked', array( 'batch' => $staged ), $institution );
	}

	/**
	 * Throw a staged batch away.
	 */
	public static function handle_cancel() {
		$batch_id = (int) WPCPM_Request::posted_text( 'batch' );

		check_admin_referer( self::ACTION_CANCEL . '_' . $batch_id );

		$batch = WPCPM_Institution_Import::batch( $batch_id );

		if ( ! is_array( $batch ) ) {
			self::bounce( 'no-batch' );
		}

		// **The institution comes off the batch, never off the form.** A member of one school
		// posting another school's batch ID is decided against the school that batch belongs
		// to, and gets the one refusal.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_ADD_STUDENT,
			WPCPM_Institution_Policy::subject_institution( $batch['institution'] )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		self::bounce( WPCPM_Institution_Import::cancel( $batch_id ) ? 'cancelled' : 'not-cancelled', array(), (string) $batch['institution'] );
	}

	/**
	 * Create the clean rows of one staged batch.
	 *
	 * **The order is the module's rule.** The batch is read first because everything else is
	 * derived from it: the institution comes off `_wpcpm_batch_institution` and never off the
	 * form, so a member of one school posting another's batch ID is decided against the school
	 * that batch belongs to; the decision is cheap and is made before the nonce; the nonce is
	 * keyed to the batch and to what the batch says; and only then does anything reach the
	 * network. A cross-site post costs this site one post read.
	 *
	 * The account that pressed this is written onto the batch before the first record is
	 * created, because every later slice re-asks the policy about that account and the ones
	 * running under cron have no other way to know who to ask about.
	 */
	public static function handle_confirm() {
		if ( ! self::enabled() ) {
			self::bounce( 'off' );
		}

		$batch_id = (int) WPCPM_Request::posted_text( 'batch' );
		$batch    = WPCPM_Institution_Import::batch( $batch_id );

		if ( ! is_array( $batch ) ) {
			self::bounce( 'no-batch' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_ADD_STUDENT,
			WPCPM_Institution_Policy::subject_institution( $batch['institution'] )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( WPCPM_Institution_Create::confirm_action( $batch_id ) );

		// The batch's institution is where every way out from here goes back to, as it is the
		// one the decision above was made about.
		$record = (string) $batch['institution'];

		// A second Confirm of a batch that has been created, or that stopped, creates nothing.
		// This is the browser's back button, and it has to be free.
		if ( WPCPM_Institution_Import::STATE_STAGED !== $batch['state'] ) {
			self::bounce( 'not-staged-now', array(), $record );
		}

		// A program may have been unticked between staging and confirming, which this phase's
		// gating made reachable. Every row would fail with the generic refusal if we let
		// create_slice proceed, while the staging step named the program clearly. Check here
		// and give that reason instead (decision 10, and 2.4 for the automation itself).
		$status = isset( $batch['values'], $batch['values']['status'] ) ? (string) $batch['values']['status'] : '';
		if ( '' !== $status && ! isset( WPCPM_Institutions::offered_programs()[ $status ] ) ) {
			self::bounce( 'program-ungated', array(), $record );
		}

		WPCPM_Institution_Create::claim( $batch_id, get_current_user_id(), $decision['ground'] );

		self::report( WPCPM_Institution_Create::create_slice( $batch_id ), $batch_id, $record );
	}

	/**
	 * Carry on with a batch already being created.
	 *
	 * The same order, and the nonce keyed to the batch alone: a batch part way through changes
	 * on every slice, so a fingerprint of its rows would invalidate this button as soon as the
	 * first student existed.
	 *
	 * **The batch is not re-claimed here.** The account the guard holds to the rule on every
	 * slice is the one that confirmed, and letting a second member take that over by pressing
	 * Continue would let a revoked confirmer's batch be carried on under somebody else's name.
	 * This reader is checked in their own right, above, and that is all pressing this buys.
	 */
	public static function handle_continue() {
		if ( ! self::enabled() ) {
			self::bounce( 'off' );
		}

		$batch_id = (int) WPCPM_Request::posted_text( 'batch' );
		$batch    = WPCPM_Institution_Import::batch( $batch_id );

		if ( ! is_array( $batch ) ) {
			self::bounce( 'no-batch' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_ADD_STUDENT,
			WPCPM_Institution_Policy::subject_institution( $batch['institution'] )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( WPCPM_Institution_Create::continue_action( $batch_id ) );

		$record = (string) $batch['institution'];

		if ( WPCPM_Institution_Import::STATE_CREATING !== $batch['state'] ) {
			self::bounce( 'not-creating-now', array(), $record );
		}

		self::report( WPCPM_Institution_Create::create_slice( $batch_id ), $batch_id, $record );
	}

	/**
	 * Say what a slice did, and go back to the dashboard.
	 *
	 * The batch ID travels with the message so the page can show which rows did not simply get
	 * created; the counts travel with it so the sentence can name them rather than saying that
	 * something happened.
	 *
	 * @param array  $outcome  From `WPCPM_Institution_Create::create_slice()`.
	 * @param int    $batch_id The batch.
	 * @param string $record   Institutions record ID the batch belongs to.
	 */
	private static function report( array $outcome, $batch_id, $record ) {
		self::leave(
			(string) $outcome['problem'],
			array(
				'detail' => array(
					'batch'     => (int) $batch_id,
					'created'   => (int) $outcome['created'],
					'blocked'   => (int) $outcome['blocked'],
					'failed'    => (int) $outcome['failed'],
					'remaining' => (int) $outcome['remaining'],
				),
			),
			$record
		);
	}

	/**
	 * The batch-wide answers, cleaned, or a problem key and the control it is about.
	 *
	 * @return array{values:array,problem:string,field:string}
	 */
	private static function batch_values() {
		$status = WPCPM_Request::posted_text( 'program' );

		// The map is the server's. A posted value outside it is not a program this site offers,
		// whatever the form said, and typecast is off in the base so it would be a 422 anyway.
		// It is the gated map, so a track whose automation item is not ticked is refused here
		// even if the select was drawn before somebody unticked it (decision 10).
		if ( ! isset( WPCPM_Institutions::offered_programs()[ $status ] ) ) {
			return array(
				'values'  => array(),
				'problem' => 'bad-program',
				'field'   => self::FIELD_PROGRAM,
			);
		}

		$start = self::date( WPCPM_Request::posted_text( 'start' ) );

		if ( '' === $start ) {
			return array(
				'values'  => array(),
				'problem' => 'bad-start',
				'field'   => self::FIELD_START,
			);
		}

		// A year either side. A term starting next autumn is ordinary; one starting in 2036 is
		// a finger on the wrong digit, and a whole cohort filed under it is a mess somebody has
		// to unpick a record at a time.
		$today = (int) strtotime( gmdate( 'Y-m-d' ) );
		$away  = abs( (int) strtotime( $start ) - $today );

		if ( $away > 365 * DAY_IN_SECONDS ) {
			return array(
				'values'  => array(),
				'problem' => 'start-far',
				'field'   => self::FIELD_START,
			);
		}

		$end = self::date( WPCPM_Request::posted_text( 'end' ) );

		if ( '' !== $end ) {
			$span = (int) strtotime( $end ) - (int) strtotime( $start );

			if ( $span <= 0 || $span > 365 * DAY_IN_SECONDS ) {
				return array(
					'values'  => array(),
					'problem' => 'bad-end',
					'field'   => self::FIELD_END,
				);
			}
		}

		if ( '1' !== WPCPM_Request::posted_text( 'notified' ) ) {
			return array(
				'values'  => array(),
				'problem' => 'not-notified',
				'field'   => self::FIELD_NOTIFIED,
			);
		}

		return array(
			'values'  => array(
				'status'   => $status,
				'start'    => $start,
				'end'      => $end,
				'notified' => true,
			),
			'problem' => '',
			'field'   => '',
		);
	}

	/**
	 * A posted date, or ''.
	 *
	 * Pattern and `checkdate()` both, so 2026-02-31 is refused rather than becoming March.
	 *
	 * @param string $raw As posted.
	 * @return string `Y-m-d`, or ''.
	 */
	private static function date( $raw ) {
		$raw = trim( (string) $raw );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts ) ) {
			return '';
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $raw : '';
	}

	/**
	 * The rows the reader sent, as CSV text, from whichever of the three routes was used, and the
	 * control that route is: the file input, the paste box, or '' for the one-student fields.
	 *
	 * **A chosen file is the file's answer.** Too big for the import, or refused by PHP for its size,
	 * it is `parse-too_large` at the file input (1.123.58, INS-05); chosen and not readable for any
	 * other reason, a partial upload, no temporary directory, a failed write, an extension, or nothing
	 * read from it, it is `file-unreadable` there. The paste box and the one-student fields are read
	 * only when no file was chosen, so a paste kept from an earlier refusal is never checked in place
	 * of the file the reader chose.
	 *
	 * @return array{text:string,field:string}|WP_Error The text and its control, or
	 *                                                  `parse-too_large` or `file-unreadable`.
	 */
	private static function submitted_text() {
		// The upload as PHP described it. The nonce is checked in `handle_check()`, several steps
		// before this private method is reached, and it has no other caller; `uploaded_file()`
		// reads the two pieces it needs from it and cleans the one that is a string.
		//
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$file = isset( $_FILES['list'] ) && is_array( $_FILES['list'] ) ? $_FILES['list'] : array();
		$read = self::uploaded_file( $file );

		if ( is_wp_error( $read ) ) {
			return $read;
		}

		if ( '' !== trim( $read ) ) {
			return array(
				'text'  => $read,
				'field' => self::FIELD_FILE,
			);
		}

		if ( self::file_chosen() ) {
			return new WP_Error( 'file-unreadable' );
		}

		// Lines, not text: the text reader strips them and a CSV becomes one line.
		$paste = WPCPM_Request::posted_lines( 'paste' );

		if ( '' !== trim( $paste ) ) {
			return array(
				'text'  => $paste,
				'field' => self::FIELD_PASTE,
			);
		}

		return array(
			'text'  => self::single_row_text(),
			'field' => '',
		);
	}

	/**
	 * The text of an uploaded list, '' when there is none to read, or `parse-too_large`.
	 *
	 * **The file is read and never stored.** `wp_handle_upload()` would move a list of names and
	 * addresses into the uploads directory, which this site serves over the web; this reads the
	 * temporary file and lets PHP discard it. The size is checked before the read, so a large file
	 * costs the memory of nothing, and a file PHP itself refused for its size (`UPLOAD_ERR_INI_SIZE`,
	 * `UPLOAD_ERR_FORM_SIZE`), which arrives with no temporary file at all, is too large as well.
	 *
	 * Any other upload error is `file-unreadable`.
	 *
	 * @param array $file The upload's entry in `$_FILES`.
	 * @return string|WP_Error
	 */
	private static function uploaded_file( array $file ) {
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
			return new WP_Error( 'parse-too_large' );
		}

		if ( UPLOAD_ERR_NO_FILE !== $error && UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'file-unreadable' );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		// Before the read, and before anything else is asked of the path.
		if ( $size > self::MAX_BYTES ) {
			return new WP_Error( 'parse-too_large' );
		}

		$path = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? sanitize_text_field( $file['tmp_name'] ) : '';

		// **`is_uploaded_file()` is the check that matters, and it is PHP's own.** It answers yes
		// only for a path this request's upload handler created, so a posted `tmp_name` naming
		// `/etc/passwd` reads nothing: the sanitizing above satisfies a linter, this line is the
		// control.
		if ( '' === $path || ! is_uploaded_file( $path ) ) {
			return '';
		}

		// A PHP upload's temporary file, read once and never moved. WP_Filesystem is for writing
		// into the site's own directories, which is the thing this deliberately does not do.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$read = file_get_contents( $path );

		return is_string( $read ) ? $read : '';
	}

	/**
	 * Whether the post carried a chosen file, read or not: the one answer a form drawn again cannot
	 * hold, so its input asks for it again.
	 *
	 * @return bool
	 */
	private static function file_chosen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle_check() verified the nonce before any refusal reaches here.
		$error = isset( $_FILES['list']['error'] ) ? (int) $_FILES['list']['error'] : UPLOAD_ERR_NO_FILE;

		return UPLOAD_ERR_NO_FILE !== $error;
	}

	/**
	 * The one-student fields as a two-line CSV.
	 *
	 * **One shape reaches the checking, not two.** The single-student route could have been a
	 * second path with its own cleaning and its own duplicate checks, and it would have drifted
	 * from the file route within a release. Turning the five boxes into the file they describe
	 * means every rule is written once and every test covers both.
	 *
	 * @return string
	 */
	private static function single_row_text() {
		$values = array();

		foreach ( array_keys( self::single_fields() ) as $field ) {
			$values[ $field ] = WPCPM_Request::posted_text( $field );
		}

		if ( '' === trim( $values['name'] ) && '' === trim( $values['email'] ) ) {
			return '';
		}

		$rows = array( array_keys( $values ), array_values( $values ) );
		$out  = '';

		foreach ( $rows as $row ) {
			$cells = array();

			foreach ( $row as $cell ) {
				$cells[] = '"' . str_replace( '"', '""', (string) $cell ) . '"';
			}

			$out .= implode( ',', $cells ) . "\n";
		}

		return $out;
	}

	/**
	 * Leave a message and go back to the dashboard.
	 *
	 * `$record` is the institution the press acted on: the one the page resolved, or the one the
	 * batch belongs to, and never the switcher the form happened to carry. It is added to the
	 * address for a viewer who holds `CAP_MANAGE` and for nobody else, because
	 * `resolve_institution()` reads the argument on that branch alone: an administrator comes
	 * back to the institution they were viewing instead of to the first one with a member, and
	 * a member's address names no school. A press that acted on nothing, such as one on a
	 * batch that is gone, passes none, and `return_record()` says where an administrator goes.
	 *
	 * @param string $status What happened.
	 * @param array  $extra  Anything the message needs, such as the batch to open.
	 * @param string $record Institutions record ID the press acted on, or ''.
	 */
	private static function leave( $status, array $extra = array(), $record = '' ) {
		WPCPM_Flash::set(
			self::FLASH,
			array_merge( array( 'status' => (string) $status ), $extra )
		);

		// The Enroll students view, at the form (`card_url()`), with the batch to open and, for an Administrator, the
		// school: the page draws one view at a time, and the form that prints the message is on this one.
		$view = current_user_can( WPCPM_Roles::CAP_MANAGE ) ? self::return_record( $record ) : '';
		$args = ! empty( $extra['batch'] ) ? array( self::ARG_BATCH => (string) (int) $extra['batch'] ) : array();
		$url  = WPCPM_Institutions_Dashboard::card_url( 'import', $view, '', $args );

		wp_safe_redirect( '' !== $url ? $url : '#wpcpm-import' );
		exit;
	}

	/**
	 * The institution an administrator's way back names, or ''.
	 *
	 * What the press acted on, when the handler named it, and then only if it is a record ID: a
	 * press that acted on something goes back to the thing it acted on, and the switcher the
	 * form carried is not asked. A press that acted on nothing, such as one on a batch that is
	 * gone or one refused before anything was read, has no institution of its own, so its way
	 * back is navigation and takes the institution the request says the administrator was
	 * viewing, read where `resolve_institution()` reads it. It is used only if it is a record ID
	 * the pipeline index holds, and the next page load checks it again, so a value that is
	 * stale or made up lands nobody anywhere they could not already open.
	 *
	 * @param string $record Institutions record ID the press acted on, or ''.
	 * @return string
	 */
	private static function return_record( $record ) {
		$record = trim( (string) $record );

		if ( '' !== $record ) {
			return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
		}

		$asked = trim( WPCPM_Request::text( WPCPM_Institution_Roster::ARG_VIEW ) );

		if ( '' === $asked ) {
			$asked = trim( WPCPM_Request::posted_text( WPCPM_Institution_Roster::ARG_VIEW ) );
		}

		return ( WPCPM_Mentors_Sync::is_record_id( $asked ) && WPCPM_Institutions_Index::has( $asked ) ) ? $asked : '';
	}

	/**
	 * Refuse a check that got past the nonce: keep what was posted, then leave the refusal.
	 *
	 * @param string $status Why.
	 * @param array  $detail Anything the sentence needs.
	 * @param string $record Institutions record ID the press acted on.
	 * @param string $field  The control the refusal is about, or '' for the form as a whole.
	 */
	private static function refuse( $status, array $detail, $record, $field = '' ) {
		self::keep_posted( $status, $record, $field );
		self::bounce( $status, $detail, $record );
	}

	/**
	 * Keep every answer the check was posted with, and why each refused control was refused, for the
	 * one time the form is drawn again (1.123.58, INS-02).
	 *
	 * One reason a control: the refusal's own sentence at its control; a pasted list over
	 * `WPCPM_Flash::KEPT_MAX` characters is not kept at all, and its box asks for the paste again; a
	 * chosen file, which no form can hand back, asks to be chosen again unless the file itself was
	 * the refusal.
	 *
	 * @param string $status The refusal.
	 * @param string $record Institutions record ID.
	 * @param string $field  The control the refusal is about, or ''.
	 */
	private static function keep_posted( $status, $record, $field ) {
		$values = array(
			'program'  => WPCPM_Request::posted_text( 'program' ),
			'start'    => WPCPM_Request::posted_text( 'start' ),
			'end'      => WPCPM_Request::posted_text( 'end' ),
			'notified' => WPCPM_Request::posted_text( 'notified' ),
			'paste'    => WPCPM_Request::posted_lines( 'paste' ),
		);

		foreach ( array_keys( self::single_fields() ) as $name ) {
			$values[ $name ] = WPCPM_Request::posted_text( $name );
		}

		$said     = self::messages();
		$problems = array();

		if ( '' !== $field ) {
			$problems[ $field ] = isset( $said[ $status ] ) ? $said[ $status ] : $said['unknown'];
		}

		if ( mb_strlen( $values['paste'], 'UTF-8' ) > WPCPM_Flash::KEPT_MAX ) {
			unset( $values['paste'] );
			$problems[ self::FIELD_PASTE ] = $said['paste-too-long'];
		}

		if ( ! isset( $problems[ self::FIELD_FILE ] ) && self::file_chosen() ) {
			$problems[ self::FIELD_FILE ] = WPCPM_Form_Errors::file_again();
		}

		WPCPM_Flash::keep( self::KEPT_FORM . $record, $values, $problems );
	}

	/**
	 * Leave a refusal and go back.
	 *
	 * @param string $status Why.
	 * @param array  $detail Anything the sentence needs.
	 * @param string $record Institutions record ID the press acted on, or ''; see `leave()`.
	 */
	private static function bounce( $status, array $detail = array(), $record = '' ) {
		self::leave( $status, empty( $detail ) ? array() : array( 'detail' => $detail ), $record );
	}

	/**
	 * Print whatever the last import left to say.
	 *
	 * @param array $flash The flash `render()` read, or an empty array for nothing to say.
	 */
	private static function render_message( array $flash ) {
		$key = ! empty( $flash['status'] ) ? (string) $flash['status'] : '';

		if ( '' === $key ) {
			return;
		}

		$text = self::outcome_sentence( $key, isset( $flash['detail'] ) && is_array( $flash['detail'] ) ? $flash['detail'] : array() );

		// Toned, heard and focused as every card's outcome is (1.123.58, IDB-09).
		echo WPCPM_Dashboard_Card::message( self::tone( $key ), $text, 'wpcpm-import__message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Dashboard_Card::message() escapes what it prints.
	}

	/**
	 * The tone an outcome is printed in: done is a success, under way or already so is news, and
	 * everything else is a refusal.
	 *
	 * @param string $key The status.
	 * @return string `success`, `info` or `error`.
	 */
	private static function tone( $key ) {
		if ( in_array( $key, array( 'checked', 'created', 'cancelled' ), true ) ) {
			return 'success';
		}

		if ( in_array( $key, array( 'progress', 'locked', 'create-unreadable', 'not-staged-now', 'not-creating-now' ), true ) ) {
			return 'info';
		}

		return 'error';
	}

	/**
	 * What an outcome says: its sentence, or the one that carries its numbers.
	 *
	 * @param string $key    The status.
	 * @param array  $detail What the handler counted.
	 * @return string
	 */
	public static function outcome_sentence( $key, array $detail = array() ) {
		$said = self::messages();
		$sums = self::summary_sentence( (string) $key, $detail );

		if ( '' !== $sums ) {
			return $sums;
		}

		return isset( $said[ $key ] ) ? $said[ $key ] : $said['unknown'];
	}

	/**
	 * The one sentence that has to carry numbers, or ''.
	 *
	 * **Created, blocked and failed, named separately, because they are three different jobs
	 * for the reader.** Created is nothing to do; blocked is a row to ask a program manager
	 * about; failed is a row to send again. A single total would hide the two that need doing
	 * behind the one that does not.
	 *
	 * @param string $key    The status.
	 * @param array  $detail What the handler counted.
	 * @return string
	 */
	private static function summary_sentence( $key, array $detail ) {
		$count = function ( $name ) use ( $detail ) {
			return number_format_i18n( isset( $detail[ $name ] ) ? (int) $detail[ $name ] : 0 );
		};

		if ( 'created' === $key ) {
			return sprintf(
				/* translators: 1: students created, 2: rows not created, 3: rows the program records refused. */
				__( 'Done: %1$s students created, %2$s blocked, %3$s failed. Nobody has been emailed.', 'wpcredits-program-manager' ),
				$count( 'created' ),
				$count( 'blocked' ),
				$count( 'failed' )
			);
		}

		// The creating stage's own hiccup, apart from the check's `unreadable`: records were
		// created, and the rest carry on by themselves (1.123.58, INS-09).
		if ( 'create-unreadable' === $key ) {
			$created = isset( $detail['created'] ) ? (int) $detail['created'] : 0;

			return sprintf(
				/* translators: %s: how many student records were created before the program records stopped answering. */
				_n(
					'The program records stopped answering partway. %s student record was created, and the rest are created by themselves as soon as the records answer again. Nothing needs to be sent again.',
					'The program records stopped answering partway. %s student records were created, and the rest are created by themselves as soon as the records answer again. Nothing needs to be sent again.',
					$created,
					'wpcredits-program-manager'
				),
				number_format_i18n( $created )
			);
		}

		if ( 'progress' === $key ) {
			return sprintf(
				/* translators: 1: students created so far, 2: how many are still to come. */
				__( '%1$s students created so far, %2$s still to go. This carries on by itself, and Continue picks it up now.', 'wpcredits-program-manager' ),
				$count( 'created' ),
				$count( 'remaining' )
			);
		}

		return '';
	}

	/**
	 * Every sentence this screen can say about what just happened.
	 *
	 * In one map so that a refusal cannot be written twice in two wordings, and so that the
	 * ones an outsider can trigger can be read together and checked for saying too much.
	 *
	 * @return array<string, string>
	 */
	public static function messages() {
		return array(
			'checked'              => __( 'The list was checked. Nothing has been created yet.', 'wpcredits-program-manager' ),
			// Both of these are replaced by `summary_sentence()`, which has the counts. They
			// are here so that a status can never reach the screen without a sentence behind
			// it, which is what the "unknown" fallback would otherwise have to say.
			'created'              => __( 'The import finished.', 'wpcredits-program-manager' ),
			'progress'             => __( 'The import is running.', 'wpcredits-program-manager' ),
			'not-staged-now'       => __( 'That list has already been created, or it stopped. Nothing was created again.', 'wpcredits-program-manager' ),
			'not-creating-now'     => __( 'That import is not running, so there was nothing to carry on with.', 'wpcredits-program-manager' ),
			'locked'               => __( 'This import is already running. Give it a moment and look again.', 'wpcredits-program-manager' ),
			'wrong-state'          => __( 'That list is not one that can be created.', 'wpcredits-program-manager' ),
			// The three ways a slice can be stopped by the guard that runs before every one of
			// them. Named separately because a school can act on the first and only a program
			// manager can act on the other two.
			'agreement'            => __( 'The import stopped: this institution\'s agreement is no longer settled. Students created before it stopped are on your roster.', 'wpcredits-program-manager' ),
			'not-allowed'          => __( 'The import stopped: the account that started it can no longer add students here. Ask an Administrator.', 'wpcredits-program-manager' ),
			'no-actor'             => __( 'The import stopped: it has no record of who started it. Ask an Administrator.', 'wpcredits-program-manager' ),
			'no-institution'       => __( 'That list is not attached to an institution, so nothing was created.', 'wpcredits-program-manager' ),
			'cancelled'            => __( 'That list was thrown away.', 'wpcredits-program-manager' ),
			'not-cancelled'        => __( 'That list could not be thrown away. It may already have been.', 'wpcredits-program-manager' ),
			'off'                  => __( 'This site is not taking imports right now.', 'wpcredits-program-manager' ),
			'refused'              => __( 'That is not something you can do here.', 'wpcredits-program-manager' ),
			'no-batch'             => __( 'That list is no longer here.', 'wpcredits-program-manager' ),
			'already-staged'       => __( 'There is already a list waiting. Look at it or throw it away before sending another.', 'wpcredits-program-manager' ),
			'too_often'            => __( 'That is several checks in a short time. Try again in an hour.', 'wpcredits-program-manager' ),
			'rows_today'           => __( 'That is more students than this site checks for one institution in a day. Try again tomorrow, or send fewer.', 'wpcredits-program-manager' ),
			'bad-program'          => __( 'Choose the program these students are joining.', 'wpcredits-program-manager' ),
			'program-ungated'      => __( 'The program you chose is no longer being offered, because the Reports and Feedback automation for it is not active. Ask an Administrator to tick it and try again.', 'wpcredits-program-manager' ),
			'bad-start'            => __( 'Give the date these students start, as a date.', 'wpcredits-program-manager' ),
			'start-far'            => __( 'That start date is more than a year away. Check the year.', 'wpcredits-program-manager' ),
			'bad-end'              => __( 'The end date has to be after the start date, and within a year of it.', 'wpcredits-program-manager' ),
			'not-notified'         => __( 'Confirm that these students have been notified of what is shared, before sending the list.', 'wpcredits-program-manager' ),
			'nothing-sent'         => __( 'Nothing was sent. Fill in one student, choose a file, or paste a list.', 'wpcredits-program-manager' ),
			'unreadable'           => __( 'The program records could not be reached just now, so the list was not checked. Nothing was created. Try again shortly.', 'wpcredits-program-manager' ),
			'not-staged'           => __( 'The list was read but could not be kept. Nothing was created.', 'wpcredits-program-manager' ),
			// Said at the paste box, never as an outcome: a list too long to keep is pasted again.
			'paste-too-long'       => __( 'The pasted list was too long to keep here: paste it again.', 'wpcredits-program-manager' ),
			'parse-empty'          => __( 'That list is empty.', 'wpcredits-program-manager' ),
			'parse-too_large'      => __( 'That file is too big. Send it in two halves.', 'wpcredits-program-manager' ),
			'file-unreadable'      => __( 'That file could not be read. Choose it again.', 'wpcredits-program-manager' ),
			'parse-not_utf8'       => __( 'That file is not UTF-8, and converting it would spell somebody\'s name wrong. Export it again as UTF-8 and send it back.', 'wpcredits-program-manager' ),
			'parse-no_header'      => __( 'That list has no header row.', 'wpcredits-program-manager' ),
			'parse-no_columns'     => __( 'That list needs a name column and an email column.', 'wpcredits-program-manager' ),
			'parse-no_rows'        => __( 'That list has a header and no students under it.', 'wpcredits-program-manager' ),
			'parse-too_many_rows'  => __( 'That list is longer than one import may carry. Send it in halves.', 'wpcredits-program-manager' ),
			'parse-batch_mismatch' => __( 'That list carries a start date or a program of its own that disagrees with the ones chosen above. Make them agree, or take those columns out.', 'wpcredits-program-manager' ),
			'unknown'              => __( 'Something about that list could not be read.', 'wpcredits-program-manager' ),
		);
	}
}
