<?php
/**
 * The screen a school enrols students through, and the two handlers behind it.
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

	/** The batch the preview is showing, in the address. */
	const ARG_BATCH = 'wpcpm_batch';

	/** Where a message about an import is left for the reader. */
	const FLASH = 'institution_import';

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

		$staged = WPCPM_Institution_Import::staged_for( $record );

		// **Read once, here, and carried down.** The disclosure has to know whether there is a
		// message before the message is printed, and `WPCPM_Flash::take()` empties the channel.
		// It memoizes per request, so asking twice does work in production; relying on that
		// made the behaviour depend on a subtlety two files away, and a test harness that
		// simulates several requests in one process could not honestly reproduce it. One read
		// and one argument is less to know.
		$flash = WPCPM_Flash::take( self::FLASH );
		$said  = ( is_array( $flash ) && ! empty( $flash['status'] ) ) ? (string) $flash['status'] : '';

		echo '<section class="wpcpm-institution__card wpcpm-import" id="wpcpm-import">';

		// **Folded by default, and open when there is something to answer.** Enrolling is an
		// occasional act and the form is the longest thing on this page; left open it pushes
		// the people and the agreement off the screen for the ninety-nine visits that came to
		// read the roster. It opens by itself when a list is waiting to be looked at or when
		// the last attempt left something to say, because both of those are the page asking
		// the reader for something rather than offering.
		printf(
			'<details class="wpcpm-group wpcpm-group__disclosure wpcpm-import__disclosure"%s>',
			( $staged > 0 || '' !== $said ) ? ' open' : ''
		);

		printf(
			'<summary class="wpcpm-group__summary"><span class="wpcpm-group__title">%1$s</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Enrol students', 'wpcredits-program-manager' )
		);

		echo '<div class="wpcpm-group__body wpcpm-import__body">';

		self::render_message( $said );

		if ( $staged > 0 ) {
			self::render_preview( $staged, $record );
		} else {
			self::render_form( $record );
		}

		echo '</div>';
		echo '</details>';
		echo '</section>';
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

	/**
	 * The form itself.
	 *
	 * The batch-wide answers come first, because they are properties of the whole import
	 * rather than of a student: the program somebody is joining and the term they start are
	 * chosen once, and a file carrying its own copy of either may only agree.
	 *
	 * @param string $record Institutions record ID.
	 */
	private static function render_form( $record ) {
		printf(
			'<p class="wpcpm-import__lede">%s</p>',
			esc_html__( 'Add one student, or a list of them. Nothing is created until you have seen what the list was understood to say.', 'wpcredits-program-manager' )
		);

		printf(
			'<form class="wpcpm-import__form" method="post" enctype="multipart/form-data" action="%s">',
			esc_url( admin_url( 'admin-post.php' ) )
		);

		wp_nonce_field( self::ACTION_CHECK . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CHECK ) );

		echo '<div class="wpcpm-import__batch">';

		printf( '<p class="wpcpm-field"><label for="wpcpm-import-program">%s</label>', esc_html__( 'Program', 'wpcredits-program-manager' ) );
		echo '<select id="wpcpm-import-program" name="program">';

		// The value is the status the base holds and the label is what a person calls it. The
		// map is the server's, so a posted value that is not one of these is not a program.
		foreach ( WPCPM_Program::labels() as $status => $label ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $status ), esc_html( $label ) );
		}

		echo '</select></p>';

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-import-start">%1$s</label><input type="date" id="wpcpm-import-start" name="start" required /><span class="wpcpm-field__hint">%2$s</span></p>',
			esc_html__( 'Start date', 'wpcredits-program-manager' ),
			esc_html__( 'The term these students begin. A date more than a year either side of today is refused as a typo.', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-import-end">%1$s</label><input type="date" id="wpcpm-import-end" name="end" /><span class="wpcpm-field__hint">%2$s</span></p>',
			esc_html__( 'End date, if you know it', 'wpcredits-program-manager' ),
			esc_html__( 'After the start date, and at most a year after it.', 'wpcredits-program-manager' )
		);

		// **Recorded on the batch and never written to a student's record.** The consent that
		// matters is the student's own, given on their own form; this is the school saying it
		// has notified them, which is a different statement by a different person and belongs
		// in a different place.
		//
		// It names the name and the address and nothing else. A WordPress.org profile is not
		// shared at this point because there is none to share: getting one is the student's own
		// first step after enrolment, so a school confirming it had been notified about would
		// be confirming something that has not happened.
		printf(
			'<p class="wpcpm-field wpcpm-field--check"><label><input type="checkbox" name="notified" value="1" required /> %s</label></p>',
			esc_html__( 'These students have been notified that their name and email address are shared with the WordPress Foundation for the WordPress Credits Program.', 'wpcredits-program-manager' )
		);

		echo '</div>';

		echo '<div class="wpcpm-import__rows">';

		printf( '<h3 class="wpcpm-import__subtitle">%s</h3>', esc_html__( 'One student', 'wpcredits-program-manager' ) );

		foreach ( self::single_fields() as $name => $label ) {
			printf(
				'<p class="wpcpm-field"><label for="wpcpm-import-%1$s">%2$s</label>',
				esc_attr( $name ),
				esc_html( $label )
			);

			$options = self::options_for( $name, $record );

			if ( null === $options ) {
				printf( '<input type="text" id="wpcpm-import-%1$s" name="%1$s" /></p>', esc_attr( $name ) );
				continue;
			}

			printf( '<select id="wpcpm-import-%1$s" name="%1$s">', esc_attr( $name ) );
			// Blank first and selected, because neither of these is required and a picker that
			// opens on the first real answer is a picker that files half a cohort under it.
			printf( '<option value="">%s</option>', esc_html__( 'Not recorded', 'wpcredits-program-manager' ) );

			foreach ( $options as $option ) {
				printf( '<option value="%1$s">%1$s</option>', esc_attr( $option ) );
			}

			echo '</select>';

			if ( 'tutor' === $name && empty( $options ) ) {
				printf(
					'<span class="wpcpm-field__hint">%s</span>',
					esc_html__( 'The program has no tutors recorded for your institution yet. Ask a program manager to add them, and they will appear here.', 'wpcredits-program-manager' )
				);
			}

			echo '</p>';
		}

		printf( '<h3 class="wpcpm-import__subtitle">%s</h3>', esc_html__( 'Or a list', 'wpcredits-program-manager' ) );

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-import-file">%1$s</label><input type="file" id="wpcpm-import-file" name="list" accept=".csv,text/csv" /><span class="wpcpm-field__hint">%2$s</span></p>',
			esc_html__( 'A CSV file', 'wpcredits-program-manager' ),
			esc_html(
				sprintf(
					/* translators: %s: the largest number of students one import may carry. */
					__( 'A header row, then one student per line. Name and email are needed; field of study and tutor are read when they are there. Up to %s students. The file is read and never stored.', 'wpcredits-program-manager' ),
					number_format_i18n( WPCPM_Institution_Import::MAX_ROWS )
				)
			)
		);

		printf(
			'<p class="wpcpm-field"><label for="wpcpm-import-paste">%1$s</label><textarea id="wpcpm-import-paste" name="paste" rows="6"></textarea><span class="wpcpm-field__hint">%2$s</span></p>',
			esc_html__( 'Or paste the same thing here', 'wpcredits-program-manager' ),
			esc_html__( 'Useful when the list is in a spreadsheet you would rather not download.', 'wpcredits-program-manager' )
		);

		echo '</div>';

		printf(
			'<p class="wpcpm-import__actions"><button type="submit" class="wpcpm-button">%s</button></p>',
			esc_html__( 'Check the list', 'wpcredits-program-manager' )
		);

		echo '</form>';
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
	 * The answers a field offers, or null when it is free text.
	 *
	 * **Both lists are the base's own.** `Your field of study` is a single-select in Airtable
	 * and `create_records()` sends no typecast, so a value spelled any other way is a 422 for
	 * the whole record: a text box here is a box in which a school can only get it wrong.
	 * Tutors are a table, and the list offered is this institution's own rows from it and never
	 * the program's fourteen.
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
			// **Always a picker, even when the answer is empty.** Tutors are a table in the
			// base and a free-text box invites a name that matches nothing in it, which is
			// how the column filled up with spellings in the first place. A school with none
			// recorded gets a picker holding only the blank answer and the sentence beside it
			// saying who to ask, which is a truer thing to show than a box implying the site
			// will take whatever they type.
			return WPCPM_Institution_Import::tutors( $record );
		}

		return null;
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
	 * @param string $record   Institutions record ID, for the nonce.
	 */
	private static function render_preview( $batch_id, $record ) {
		$batch = WPCPM_Institution_Import::batch( $batch_id );

		if ( ! is_array( $batch ) || $batch['institution'] !== $record ) {
			return;
		}

		$counts = self::counts( $batch['rows'] );

		printf(
			'<p class="wpcpm-import__summary">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: how many students would be created, 2: how many rows the list held. */
					__( '%1$s of %2$s rows can be created.', 'wpcredits-program-manager' ),
					number_format_i18n( $counts['ok'] ),
					number_format_i18n( count( $batch['rows'] ) )
				)
			)
		);

		if ( ! empty( $batch['unknown'] ) ) {
			printf(
				'<p class="wpcpm-import__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the column headings the import did not read. */
						__( 'Columns this import does not read, and ignored: %s', 'wpcredits-program-manager' ),
						implode( ', ', array_map( 'sanitize_text_field', $batch['unknown'] ) )
					)
				)
			);
		}

		echo '<ul class="wpcpm-import__rows-list">';

		foreach ( $batch['rows'] as $row ) {
			self::render_row( $row );
		}

		echo '</ul>';

		printf(
			'<form class="wpcpm-import__cancel" method="post" action="%s">',
			esc_url( admin_url( 'admin-post.php' ) )
		);
		wp_nonce_field( self::ACTION_CANCEL . '_' . (int) $batch_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CANCEL ) );
		printf( '<input type="hidden" name="batch" value="%d" />', (int) $batch_id );
		printf(
			'<button type="submit" class="wpcpm-button wpcpm-button--quiet">%s</button>',
			esc_html__( 'Throw this list away', 'wpcredits-program-manager' )
		);
		echo '</form>';

		// Said plainly rather than left to be inferred from the absence of a button. Creating
		// is a separate release, and a school staring at a checked list with nothing to press
		// deserves to know that rather than to hunt for the control.
		printf(
			'<p class="wpcpm-import__note">%s</p>',
			esc_html__( 'Creating these records from here ships with the next release. Until then a program manager creates them.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * One row of the preview.
	 *
	 * @param array $row A checked row.
	 */
	private static function render_row( array $row ) {
		$name  = isset( $row['name'] ) && '' !== $row['name'] ? $row['name'] : __( '(no name)', 'wpcredits-program-manager' );
		$email = isset( $row['email'] ) ? (string) $row['email'] : '';

		printf(
			'<li class="wpcpm-import__row wpcpm-import__row--%1$s"><span class="wpcpm-import__line">%2$s</span> <span class="wpcpm-import__name">%3$s</span> <span class="wpcpm-import__email">%4$s</span> <span class="wpcpm-import__verdict">%5$s</span></li>',
			esc_attr( isset( $row['verdict'] ) ? $row['verdict'] : 'ok' ),
			esc_html( sprintf( /* translators: %s: a line number in the uploaded file. */ __( 'Line %s', 'wpcredits-program-manager' ), number_format_i18n( isset( $row['line'] ) ? (int) $row['line'] : 0 ) ) ),
			esc_html( $name ),
			esc_html( $email ),
			esc_html( self::verdict_sentence( $row ) )
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
	private static function verdict_sentence( array $row ) {
		$verdict = isset( $row['verdict'] ) ? (string) $row['verdict'] : WPCPM_Institution_Import::OK;
		$detail  = isset( $row['detail'] ) && is_array( $row['detail'] ) ? $row['detail'] : array();

		if ( WPCPM_Institution_Import::OK === $verdict ) {
			return __( 'Ready to create.', 'wpcredits-program-manager' );
		}

		if ( WPCPM_Institution_Import::BLOCKED === $verdict ) {
			return __( 'This student cannot be imported from here. Ask a program manager.', 'wpcredits-program-manager' );
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
				return __( 'This student has a program record but no enrolment record. A program manager needs to complete it.', 'wpcredits-program-manager' );
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

		if ( WPCPM_Institution_Import::staged_for( $institution ) > 0 ) {
			self::bounce( 'already-staged' );
		}

		$allowed = WPCPM_Institution_Import::may_check( $institution );

		if ( empty( $allowed['ok'] ) ) {
			self::bounce( $allowed['problem'] );
		}

		$batch = self::batch_values();

		if ( '' !== $batch['problem'] ) {
			self::bounce( $batch['problem'] );
		}

		$text = self::submitted_text();

		if ( '' === $text ) {
			self::bounce( 'nothing-sent' );
		}

		$parsed = WPCPM_Institution_Import::parse( $text, $batch['values'] );

		if ( empty( $parsed['ok'] ) ) {
			self::bounce( 'parse-' . $parsed['problem'], $parsed['detail'] );
		}

		$claimed = WPCPM_Institution_Import::claim_rows( $institution, count( $parsed['rows'] ) );

		if ( empty( $claimed['ok'] ) ) {
			self::bounce( $claimed['problem'] );
		}

		$rows = WPCPM_Institution_Import::clean_rows( $parsed['rows'] );

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$checked  = WPCPM_Institution_Import::check_against_base( $rows, $institution, $airtable, $settings );

		if ( is_wp_error( $checked ) ) {
			// The base being unreachable is not a refusal, and saying so keeps a school from
			// looking for a permissions fault that does not exist.
			self::bounce( 'unreadable' );
		}

		$staged = WPCPM_Institution_Import::stage( $institution, get_current_user_id(), $batch['values'], $checked, $parsed['unknown'] );

		if ( ! $staged ) {
			self::bounce( 'not-staged' );
		}

		$counts = self::counts( $checked );
		WPCPM_Institution_Import::log_check( $institution, get_current_user_id(), count( $checked ), $counts['blocked'] );

		self::leave( 'checked', array( 'batch' => $staged ) );
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

		self::bounce( WPCPM_Institution_Import::cancel( $batch_id ) ? 'cancelled' : 'not-cancelled' );
	}

	/**
	 * The batch-wide answers, cleaned, or a problem key.
	 *
	 * @return array{values:array,problem:string}
	 */
	private static function batch_values() {
		$status = WPCPM_Request::posted_text( 'program' );

		// The map is the server's. A posted value outside it is not a program this site offers,
		// whatever the form said, and typecast is off in the base so it would be a 422 anyway.
		if ( ! isset( WPCPM_Program::labels()[ $status ] ) ) {
			return array(
				'values'  => array(),
				'problem' => 'bad-program',
			);
		}

		$start = self::date( WPCPM_Request::posted_text( 'start' ) );

		if ( '' === $start ) {
			return array(
				'values'  => array(),
				'problem' => 'bad-start',
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
			);
		}

		$end = self::date( WPCPM_Request::posted_text( 'end' ) );

		if ( '' !== $end ) {
			$span = (int) strtotime( $end ) - (int) strtotime( $start );

			if ( $span <= 0 || $span > 365 * DAY_IN_SECONDS ) {
				return array(
					'values'  => array(),
					'problem' => 'bad-end',
				);
			}
		}

		if ( '1' !== WPCPM_Request::posted_text( 'notified' ) ) {
			return array(
				'values'  => array(),
				'problem' => 'not-notified',
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
	 * The rows the reader sent, as CSV text, from whichever of the three routes was used.
	 *
	 * **The file is read and never stored.** `wp_handle_upload()` would move a list of names
	 * and addresses into the uploads directory, which this site serves over the web; this reads
	 * the temporary file and lets PHP discard it. The size is checked before the read, so a
	 * large file costs the memory of nothing.
	 *
	 * @return string CSV text, or ''.
	 */
	private static function submitted_text() {
		// The two pieces of the upload this reads, taken one at a time rather than the array
		// whole: a path and a byte count are the only things wanted, and lifting the rest would
		// be carrying a browser-supplied filename around for no reason.
		//
		// The nonce is checked in `handle_check()`, several steps before this private method is
		// reached, and it has no other caller. The sniff cannot see across methods, so the
		// ignore says where to look rather than claiming the check does not matter.
		//
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$path = isset( $_FILES['list']['tmp_name'] ) ? sanitize_text_field( (string) $_FILES['list']['tmp_name'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$size = isset( $_FILES['list']['size'] ) ? (int) $_FILES['list']['size'] : 0;

		// **`is_uploaded_file()` is the check that matters, and it is PHP's own.** It answers
		// yes only for a path this request's upload handler created, so a posted `tmp_name`
		// naming `/etc/passwd` reads nothing: the sanitising above satisfies a linter, this
		// line is the control.
		if ( '' !== $path && is_uploaded_file( $path ) ) {
			// Before the read, so a large file costs the memory of nothing.
			if ( $size > self::MAX_BYTES ) {
				return '';
			}

			// A PHP upload's temporary file, read once and never moved. WP_Filesystem is for
			// writing into the site's own directories, which is the thing this deliberately
			// does not do.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$read = file_get_contents( $path );

			if ( is_string( $read ) && '' !== trim( $read ) ) {
				return $read;
			}
		}

		$paste = WPCPM_Request::posted_text( 'paste' );

		if ( '' !== trim( $paste ) ) {
			return $paste;
		}

		return self::single_row_text();
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
	 * @param string $status What happened.
	 * @param array  $extra  Anything the message needs, such as the batch to open.
	 */
	private static function leave( $status, array $extra = array() ) {
		WPCPM_Flash::set(
			self::FLASH,
			array_merge( array( 'status' => (string) $status ), $extra )
		);

		$url = WPCPM_Institutions_Dashboard::page_url();

		if ( ! empty( $extra['batch'] ) ) {
			$url = add_query_arg( self::ARG_BATCH, (int) $extra['batch'], $url );
		}

		wp_safe_redirect( $url . '#wpcpm-import' );
		exit;
	}

	/**
	 * Leave a refusal and go back.
	 *
	 * @param string $status Why.
	 * @param array  $detail Anything the sentence needs.
	 */
	private static function bounce( $status, array $detail = array() ) {
		self::leave( $status, empty( $detail ) ? array() : array( 'detail' => $detail ) );
	}

	/**
	 * Print whatever the last import left to say.
	 *
	 * @param string $key The status `render()` read, or '' for nothing to say.
	 */
	private static function render_message( $key ) {
		$key = (string) $key;

		if ( '' === $key ) {
			return;
		}

		$said = self::messages();
		$text = isset( $said[ $key ] ) ? $said[ $key ] : $said['unknown'];

		printf(
			'<p class="wpcpm-import__message wpcpm-import__message--%1$s">%2$s</p>',
			esc_attr( sanitize_html_class( $key ) ),
			esc_html( $text )
		);
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
			'cancelled'            => __( 'That list was thrown away.', 'wpcredits-program-manager' ),
			'not-cancelled'        => __( 'That list could not be thrown away. It may already have been.', 'wpcredits-program-manager' ),
			'off'                  => __( 'This site is not taking imports right now.', 'wpcredits-program-manager' ),
			'refused'              => __( 'That is not something you can do here.', 'wpcredits-program-manager' ),
			'no-batch'             => __( 'That list is no longer here.', 'wpcredits-program-manager' ),
			'already-staged'       => __( 'There is already a list waiting. Look at it or throw it away before sending another.', 'wpcredits-program-manager' ),
			'too_often'            => __( 'That is several checks in a short time. Try again in an hour.', 'wpcredits-program-manager' ),
			'rows_today'           => __( 'That is more students than this site checks for one institution in a day. Try again tomorrow, or send fewer.', 'wpcredits-program-manager' ),
			'bad-program'          => __( 'Choose the program these students are joining.', 'wpcredits-program-manager' ),
			'bad-start'            => __( 'Give the date these students start, as a date.', 'wpcredits-program-manager' ),
			'start-far'            => __( 'That start date is more than a year away. Check the year.', 'wpcredits-program-manager' ),
			'bad-end'              => __( 'The end date has to be after the start date, and within a year of it.', 'wpcredits-program-manager' ),
			'not-notified'         => __( 'Confirm that these students have been notified of what is shared, before sending the list.', 'wpcredits-program-manager' ),
			'nothing-sent'         => __( 'Nothing was sent. Fill in one student, choose a file, or paste a list.', 'wpcredits-program-manager' ),
			'unreadable'           => __( 'The program records could not be reached just now, so the list was not checked. Nothing was created. Try again shortly.', 'wpcredits-program-manager' ),
			'not-staged'           => __( 'The list was read but could not be kept. Nothing was created.', 'wpcredits-program-manager' ),
			'parse-empty'          => __( 'That list is empty.', 'wpcredits-program-manager' ),
			'parse-too_large'      => __( 'That file is too big. Send it in two halves.', 'wpcredits-program-manager' ),
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
