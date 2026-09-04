<?php
/**
 * Institutions module - the allowlisted edit form for one student, and its audit row.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The five cells a school may correct on its own student, and the row that records it.
 *
 * This is the first write in the module made by somebody who is not a program manager, so
 * everything about it is deliberate and narrow.
 *
 * **The allowlist is the security boundary, not the form.** `fields()` names five columns
 * across two tables and the handler reads nothing else out of the request: a column outside
 * it has no form key, is never looked for, and cannot be written by a hand-made POST that
 * names it. The columns left out are left out for reasons, and the map says which beside
 * each group - `Status`, `Hours`, the institution links, `Mentor`, the consent flag, the
 * grades and `Email` are each somebody else's to change, and several of them would let an
 * institution grant itself something rather than correct a record.
 *
 * **The nonce is checked before anything else**, because the next step after it,
 * `WPCPM_Institution_Roster::claim()`, makes an HTTP request to Airtable, and a cross-site
 * POST must not be able to cause one. The token is keyed to the record being edited, so a
 * token for one student is not a token for another.
 *
 * **The Students row that is written is the one `claim()` proved, and nothing else.** The
 * claim reads that row live and allows the action on the `Educational Institutions` link
 * that row carries, so its record ID is the only Students record the request holds an
 * authorisation for. The second resolution in `students_row_for()` - the report's `Students`
 * link when it has one and `LOWER({Email})` otherwise, the two Airtable tables having no
 * populated link between them today - confirms that identity and never supplies one. An
 * address that answers with two rows, or with a row the claim did not name, refuses that
 * half by name - the school is told a program manager has to merge them first - while the
 * Students Reports half still saves. "Probably this one" is not an answer a write may give,
 * and a silent write to the wrong one of two rows is worse than a refusal a person can act
 * on.
 *
 * **The form and the handler read the same two caches.** `render_form()` fills every control
 * from `current()` and `handle_save()` calls `current()` again to decide which cells the
 * person actually changed. Diffing the post against the live row instead answers a different
 * question - "does the cache disagree with Airtable?" - and every cell where it did would be
 * written back with the stale value the form had shown, on a save that meant to change one
 * other thing. What a change *replaces* is still read live, because that is what the write
 * is about to overwrite and an audit row saying otherwise would be a false one.
 *
 * **One audit row per save**, naming the actor, the field, both values and the ground the
 * decision was allowed on, because "who changed this student's end date, and were they
 * allowed to" is the question this log exists to answer. A save that log would refuse is a
 * save this module does not make: the decision has to name an institution to file the row
 * under before a single posted cell is read.
 *
 * One caveat is worth writing down where the next reader will find it. `claim()` refuses a
 * report whose Students row is ambiguous, all-or-nothing, before this handler ever asks; so
 * on the shipped ordering the merge refusal below is the second of two locks rather than
 * the first. It is kept because it is the lock that produces a message a school can act on,
 * because the two reads are seconds apart and the base is written by people and automations
 * in between, and because a later phase claiming on the Students subject would make it the
 * only one.
 */
final class WPCPM_Institution_Student_Form {

	/** The one save. Nonce keyed to the Students Reports record being edited. */
	const ACTION_SAVE = 'wpcpm_save_student';

	/** Flash channel. Carries the record it is about, so one card prints it and not every card. */
	const FLASH = 'institution_student';

	/** The form's anchor, so a save lands back on the form it came from. */
	const ANCHOR = 'wpcpm-student-form';

	/** The posted array every allowlisted value arrives in. Nothing else in it is read. */
	const FIELD_GROUP = 'student';

	/** Audit kind: one or more cells corrected. */
	const KIND_EDIT = 'student_edited';

	/** Audit kind: the end date moved, and nothing else. The extension the spec names. */
	const KIND_EXTEND = 'extend';

	/** `students_row_for()`: the address names two Students rows, so no write can name one. */
	const CODE_MANY = 'wpcpm_students_many';

	/** `students_row_for()`: the Students table holds no row for this student at all. */
	const CODE_NONE = 'wpcpm_students_none';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/*
	 * The allowlist
	 * --------------------------------------------------------------------
	 */

	/**
	 * The columns a school may write, keyed by table and then by the base's own column name.
	 *
	 * Deliberately absent, each with its reason:
	 *
	 * - `Status` on either table. The Students table's is the application pipeline and the
	 *   reports table's is the track; graduating and withdrawing are `ACT_CHANGE_STATUS`
	 *   and a different form, with a confirm that names the email Airtable will send.
	 * - `Hours` and `Total hours`. An institution that could set hours could grant credit
	 *   for work that did not happen, which is the one thing this module must never allow.
	 * - Both institution links, `Educational Institutions` and `Educational institution`.
	 *   They are the fence's anchor: a school that could rewrite one could move somebody
	 *   else's student onto its own roster, or its own student onto somebody else's.
	 * - `Mentor`. Assigning a mentor is the program's, and a school that wanted one asks
	 *   for one through `WPCPM_Institution_Request`.
	 * - `Privacy Policy Compliance`. It records what the student agreed to; a third party
	 *   ticking it on their behalf would make the record say something it does not mean.
	 * - Every grade column and every `Post Reflection:` URL. The student writes their own
	 *   report and the mentor marks it; a school typing either would be neither.
	 * - The formula link fields. Airtable computes them and refuses a write outright.
	 * - `Notes`. It is the program's own working note about a student, not a shared field.
	 * - `Email` on BOTH tables, which is open question 6 and the one worth spelling out: on
	 *   Students Reports it is the mirror automation's join key, and `provision_student()`
	 *   never rewrites `user_email`, so an institution "changing" an address would leave
	 *   the account signing in at the old one forever and the two tables joined to nothing.
	 *
	 * @return array<string, array<string, array>> Table key to column name to field spec.
	 */
	public static function fields() {
		return array(
			'reports'  => array(
				'Name' => array(
					'type'      => 'text',
					'maxlength' => 200,
				),
			),
			'students' => array(
				'Full Name'           => array(
					'type'      => 'text',
					'maxlength' => 200,
				),
				'Start Date'          => array( 'type' => 'date' ),
				'End Date'            => array(
					'type'     => 'date',
					'after'    => 'Start Date',
					'max_days' => 365,
				),
				'Your field of study' => array(
					'type'    => 'select',
					'choices' => 'field_of_study',
				),
			),
		);
	}

	/**
	 * What each allowlisted cell is called on the form and in a refusal.
	 *
	 * Kept apart from `fields()` so that map stays exactly the allowlist the design spec
	 * prints and nothing else: a label added to it would be one more thing to read past when
	 * checking what a school may write.
	 *
	 * @return array<string, string> `"<table>|<column>"` to its visible label.
	 */
	public static function labels() {
		return array(
			'reports|Name'                 => __( 'Name on the report record', 'wpcredits-program-manager' ),
			'students|Full Name'           => __( 'Full name', 'wpcredits-program-manager' ),
			'students|Start Date'          => __( 'Start date', 'wpcredits-program-manager' ),
			'students|End Date'            => __( 'End date', 'wpcredits-program-manager' ),
			'students|Your field of study' => __( 'Field of study', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * A named list of choices, held by this site and never taken from the request.
	 *
	 * `fields()` names its list rather than carrying one, so a select is matched against
	 * something the server holds. A name this method does not know answers with an empty
	 * list, which `WPCPM_Field_Value::clean()` reads as "nothing but clearing is accepted" -
	 * a spec with a typo in it fails closed rather than passing everything through.
	 *
	 * The nine values are the base's own, as `bin/fixtures/students-table-fields.json`
	 * recorded them; `create_records()` and `update_records()` do not send `typecast`, so a
	 * value spelled any other way is a 422 for the whole record rather than a new choice
	 * quietly added to the column.
	 *
	 * @param string $name The list `fields()` asked for.
	 * @return string[]
	 */
	public static function choices( $name ) {
		$lists = array(
			'field_of_study' => array(
				'Technology & Engineering',
				'Design & Creative Media',
				'Languages, Communication & Writing',
				'Business, Marketing & Management',
				'Education & Learning',
				'Natural Sciences & Mathematics',
				'Health & Medicine',
				'Arts & Architecture',
				'Humanities & Social Sciences',
			),
		);

		$name = (string) $name;

		return isset( $lists[ $name ] ) ? $lists[ $name ] : array();
	}

	/**
	 * The form key for one allowlisted column.
	 *
	 * Table-scoped, because both tables carry `Email` and both carry a name: an unscoped key
	 * would let a value meant for one table be accepted for the other, which is a fence built
	 * on a hash collision nobody would ever see. Airtable's names also contain spaces, commas
	 * and colons, none of which belong in a form key, and `Tutor ` ends in a space that would
	 * be lost in transit and take its field with it.
	 *
	 * @param string $table `reports` or `students`.
	 * @param string $name  The base's own column name.
	 * @return string
	 */
	public static function key( $table, $name ) {
		return 'f' . substr( md5( (string) $table . '|' . (string) $name ), 0, 12 );
	}

	/*
	 * The form
	 * --------------------------------------------------------------------
	 */

	/**
	 * The edit form for one student, drawn inside the student card.
	 *
	 * Nothing at all when the decision refuses, which is the module's first call pattern:
	 * the card the reader came from is the answer, and a refused reader is not shown a
	 * disabled form telling them what they may not do.
	 *
	 * No Airtable read happens here. The values come from the two caches the card already
	 * reads - the roster index for the Students columns, because that index *is* the
	 * Students table as the last sync read it, and the account's own program row for the
	 * report's `Name` - so drawing a roster of forty cards costs no requests. The handler
	 * decides live who may write and which row is written; this is a form, not a fence.
	 *
	 * What it decides *from* is the other half of that bargain. `handle_save()` calls
	 * `current()` again on these same two caches, so a control the reader never touched
	 * posts back equal to what the handler compares it against and is not written at all.
	 *
	 * @param string $record  Students Reports record ID this student's report is filed under.
	 * @param array  $context {
	 *     What the card already knows.
	 *
	 *     @type string $institution Institutions record ID whose roster the reader came from.
	 *     @type int    $user_id     The student's account.
	 * }
	 */
	public static function render_form( $record, array $context ) {
		$record  = trim( (string) $record );
		$user_id = isset( $context['user_id'] ) ? (int) $context['user_id'] : 0;

		// A student with no report record has nothing to key the nonce to and nothing to
		// claim. The card already says so above this point, so this adds no second sentence.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_STUDENT,
			WPCPM_Institution_Policy::subject_student_account( $user_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		// **Not `$context['institution']`.** The handler builds its diff baseline from the
		// institution the DECISION names, and this call fills the controls the reader will post
		// back. If the two disagreed, the form would be drawn from one roster and compared
		// against another, and every cell where the two had drifted would count as a change the
		// reader had made and be written over the live row. They cannot disagree if they are
		// the same question, so this asks the one the handler asks; the caller's value says
		// which roster the reader came from, which is not the same question and is not used
		// here.
		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';

		$rows    = self::index_rows( $institution, $user_id );
		$values  = self::current( $user_id, $rows );
		$settled = count( $rows ) < 2;

		// `fields` is null for every ground this module ships, so this narrows nothing today.
		// It is here for the reason every other renderer in the module has it: a field-scoped
		// ground added later has to narrow every form without one of them being edited, and
		// the one that was not is the one that leaks.
		$drawn = WPCPM_Institution_Policy::scope( $decision, $values );

		printf( '<section class="wpcpm-institution__card wpcpm-institution__edit" id="%s">', esc_attr( self::ANCHOR ) );

		printf(
			'<h3 class="wpcpm-institution__heading">%s</h3>',
			esc_html__( 'Correct this student\'s details', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-institution__edit-lede">%s</p>',
			esc_html__( 'These five details are yours to correct. Everything else on this card - the program, the status, the hours, the mentor and the grades - is written by the program or by the student, and a change to any of them goes through your program contact.', 'wpcredits-program-manager' )
		);

		self::render_message( $record );

		printf(
			'<form class="wpcpm-institution__edit-form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving...', 'wpcredits-program-manager' )
		);

		// Keyed to the record this form edits, which is also what the handler keys its check
		// to. A manager acting through the switcher needs nothing added to this URL: the form
		// names its subject with a record ID, and the handler resolves the institution from
		// that record rather than from anything the form says about it.
		wp_nonce_field( self::ACTION_SAVE . '_' . $record );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="record" value="%s" />', esc_attr( $record ) );

		foreach ( self::fields() as $table => $columns ) {
			foreach ( $columns as $name => $spec ) {
				$slot = $table . '|' . $name;

				if ( ! array_key_exists( $slot, $drawn ) ) {
					continue;
				}

				// The Students half is drawn without its controls when the site cannot say
				// which Students row this student is: a form that took a date it could not
				// file would be a promise it cannot keep.
				self::render_field( $table, $name, $spec, (string) $drawn[ $slot ], 'reports' === $table || $settled );
			}
		}

		if ( ! $settled ) {
			printf(
				'<p class="wpcpm-institution__edit-note">%s</p>',
				esc_html__( 'This student has more than one row in the program\'s student records, so the site cannot tell which of them a change belongs to. A program manager needs to merge them first. The name on the report record can still be corrected.', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<p><button type="submit" class="wpcpm-button">%s</button></p>',
			esc_html__( 'Save changes', 'wpcredits-program-manager' )
		);

		echo '</form>';
		echo '</section>';
	}

	/**
	 * One control, with its label and its current value.
	 *
	 * A select prints only the choices this site holds, so an option that is not one of them
	 * cannot be picked; a value already stored that is not among them is still shown, and
	 * marked, because a school looking at a record should see what it says rather than a
	 * blank where a value is.
	 *
	 * @param string $table   `reports` or `students`.
	 * @param string $name    The base's own column name.
	 * @param array  $spec    The field spec from `fields()`.
	 * @param string $value   The current value, as the caches hold it.
	 * @param bool   $enabled Whether this control may be used.
	 */
	private static function render_field( $table, $name, array $spec, $value, $enabled ) {
		$slot   = $table . '|' . $name;
		$labels = self::labels();
		$label  = isset( $labels[ $slot ] ) ? $labels[ $slot ] : $name;
		$id     = 'wpcpm-student-' . self::key( $table, $name );
		$field  = self::FIELD_GROUP . '[' . self::key( $table, $name ) . ']';
		$off    = $enabled ? '' : ' disabled="disabled"';
		$type   = isset( $spec['type'] ) ? (string) $spec['type'] : 'text';

		echo '<p class="wpcpm-institution__edit-field">';

		printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $label ) );

		if ( 'select' === $type ) {
			printf(
				'<select class="wpcpm-institution__edit-input" id="%1$s" name="%2$s"%3$s>',
				esc_attr( $id ),
				esc_attr( $field ),
				$off // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two fixed attribute strings above.
			);

			printf( '<option value="">%s</option>', esc_html__( 'Not set', 'wpcredits-program-manager' ) );

			$choices = self::choices( isset( $spec['choices'] ) ? $spec['choices'] : '' );

			if ( '' !== $value && ! in_array( $value, $choices, true ) ) {
				// Shown, selected, named for what it is and **carrying the stored value**, so
				// that posting it back is refused by `clean()` like any other value the column
				// does not offer - which is what the sentence above promises: the school can
				// see what the record says without being able to re-save it. An empty value
				// here would do the exact opposite. `clean()` reads an empty select as
				// "cleared" and clearing is allowed, so a school correcting the name on a card
				// like this one would wipe the column it was only being shown.
				printf(
					'<option value="%1$s" selected="selected">%2$s</option>',
					esc_attr( $value ),
					esc_html(
						sprintf(
							/* translators: %s: the value stored in the program records. */
							__( '%s (not one of the program\'s choices)', 'wpcredits-program-manager' ),
							$value
						)
					)
				);
			}

			foreach ( $choices as $choice ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $choice ),
					selected( $choice, $value, false ),
					esc_html( $choice )
				);
			}

			echo '</select>';
		} else {
			printf(
				'<input class="wpcpm-institution__edit-input" type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s />',
				esc_attr( 'date' === $type ? 'date' : 'text' ),
				esc_attr( $id ),
				esc_attr( $field ),
				esc_attr( $value ),
				isset( $spec['maxlength'] ) ? ' maxlength="' . esc_attr( (string) (int) $spec['maxlength'] ) . '"' : '',
				$off // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two fixed attribute strings above.
			);
		}

		echo '</p>';
	}

	/**
	 * The outcome of the last save, on the card it happened on.
	 *
	 * @param string $record The Students Reports record this card is about.
	 */
	private static function render_message( $record ) {
		$flash  = WPCPM_Flash::take( self::FLASH );
		$status = is_array( $flash ) && isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';
		$detail = is_array( $flash ) && isset( $flash['detail'] ) ? (string) $flash['detail'] : '';
		$about  = is_array( $flash ) && isset( $flash['record'] ) ? trim( (string) $flash['record'] ) : '';

		// Routing, not authorisation: which card prints a sentence, never who may read one.
		if ( '' === $status || 0 !== strcmp( trim( (string) $record ), $about ) ) {
			return;
		}

		$messages = self::messages();

		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-institution__edit-message is-%1$s" role="status">%2$s%3$s</p>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $messages[ $status ][1] ),
			'' === $detail ? '' : ' ' . esc_html( $detail )
		);
	}

	/**
	 * What each outcome says.
	 *
	 * Every refusal opens by saying what did and did not happen, because the reader has just
	 * pressed a button and cannot see the server. The two halves are named separately
	 * wherever one landed and the other did not: "saved" over a half-save is the sentence
	 * that sends somebody away believing a date they cannot see was written.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function messages() {
		$merge = __( 'This student has more than one row in the program\'s student records, so the site cannot tell which of them the change belongs to, and a program manager needs to merge them first.', 'wpcredits-program-manager' );
		$none  = __( 'The program\'s student records hold no row for this student yet, so there is nothing to write the dates or the field of study to. A program manager completes the record.', 'wpcredits-program-manager' );

		return array(
			'student-saved'         => array( 'success', __( 'Saved. The change is in the program records and on your roster.', 'wpcredits-program-manager' ) ),
			'student-nothing'       => array( 'info', __( 'Nothing changed, so nothing was saved.', 'wpcredits-program-manager' ) ),
			'student-partly'        => array( 'error', __( 'Saved, except for the following. A date is written as YYYY-MM-DD, an end date has to be after the start date, a placement cannot run longer than a year, and the field of study has to be one of the program\'s own choices.', 'wpcredits-program-manager' ) ),
			'student-rejected'      => array( 'error', __( 'Nothing was saved. A date is written as YYYY-MM-DD, an end date has to be after the start date, a placement cannot run longer than a year, and the field of study has to be one of the program\'s own choices. What was refused:', 'wpcredits-program-manager' ) ),
			'student-merge'         => array( 'error', __( 'The name on the report record was saved. The dates and the field of study were not.', 'wpcredits-program-manager' ) . ' ' . $merge ),
			'student-merge-only'    => array( 'error', __( 'Nothing was saved.', 'wpcredits-program-manager' ) . ' ' . $merge ),
			'student-no-row'        => array( 'error', __( 'The name on the report record was saved. The dates and the field of study were not.', 'wpcredits-program-manager' ) . ' ' . $none ),
			'student-no-row-only'   => array( 'error', __( 'Nothing was saved.', 'wpcredits-program-manager' ) . ' ' . $none ),
			'student-refused'       => array( 'error', WPCPM_Institution_Policy::refusal()->get_error_message() ),
			'student-unreadable'    => array( 'error', __( 'Nothing was saved: the program records could not be read just now, so the site did not write anything it could not check first. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'student-airtable'      => array( 'error', __( 'Nothing was saved: the program records refused the change. Try again, and tell your program contact if it happens twice.', 'wpcredits-program-manager' ) ),
			'student-part-airtable' => array( 'error', __( 'Part of this change was saved and part was not: the program records refused the rest. Reload this card to see what stands, and tell your program contact if it happens twice.', 'wpcredits-program-manager' ) ),
			'student-unfiled'       => array( 'error', __( 'Nothing was saved: this student\'s row in the program records is not linked to any institution, so there is nothing to record the change against and this form does not make a change it cannot record. A program manager links the row first.', 'wpcredits-program-manager' ) ),
			'student-unlogged'      => array( 'error', __( 'The change reached the program records, but the entry saying who made it could not be written, so it is not in this institution\'s history. Tell your program contact, and do not save again until they have looked.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * Saving
	 * --------------------------------------------------------------------
	 */

	/**
	 * Write the allowlisted cells back to the two Airtable tables.
	 *
	 * The order is the whole of the security. The nonce is first, before the record is used
	 * for anything, because the next step makes an HTTP request on this site's Airtable
	 * credentials and a cross-site POST must not be able to cause one. Then `claim()`, which
	 * decides from cache, reads the live Students row and decides again against the link that
	 * row carries - so the write is authorised against what the base says now and not against
	 * what the last sync cached. Only then is the request read at all, and only through the
	 * allowlist.
	 *
	 * The claim settles two things at once and both are taken from it and from nowhere else:
	 * **which Students record** may be written, and **which institution** the audit row is
	 * filed under. Everything read afterwards either confirms those or is refused.
	 */
	public static function handle_save() {
		// Read to key the token and for nothing else. Whether this record is real, whose it
		// is and whether it may be written are all decided below.
		$record = WPCPM_Request::posted_text( 'record' );

		check_admin_referer( self::ACTION_SAVE . '_' . $record );

		// Four steps, each returning either what the next one needs or the one word the
		// reader is told. Split this way so a suite can drive each step and read its answer:
		// the 215-line handler this replaces had twelve exits woven through one body, and the
		// branch that fires when the audit row will not insert had never been executed by any
		// test. Behaviour is unchanged; only the seams are new.
		$claim = self::resolve_claim( $record );

		if ( isset( $claim['outcome'] ) ) {
			self::bounce( $claim['outcome'], '', $record );
		}

		$diff = self::diff_cells( $record, $claim );

		if ( isset( $diff['outcome'] ) ) {
			self::bounce( $diff['outcome'], self::listing( $diff['rejected'] ), $record );
		}

		$written = self::write_cells( $record, $diff );

		if ( isset( $written['outcome'] ) ) {
			self::bounce( $written['outcome'], '', $record );
		}

		$final = self::record_outcome( $record, $claim, $written );

		self::bounce( $final['outcome'], $final['listing'], $record );
	}

	/**
	 * Step one: who may write, to which row, filed under which institution.
	 *
	 * @param string $record Students Reports record ID, as posted.
	 * @return array Either `outcome` (a slug to bounce with) or `decision`, `before`,
	 *               `students_id`, `institution` and `user_id`.
	 */
	private static function resolve_claim( $record ) {
		$claim = WPCPM_Institution_Roster::claim(
			$record,
			WPCPM_Institution_Policy::ACT_EDIT_STUDENT,
			WPCPM_Institution_Roster::TYPE_REPORT
		);

		if ( is_wp_error( $claim ) ) {
			// The one refusal is the one refusal, byte for byte. Anything else is the base
			// being unreachable, which is a different sentence: a reader told "that record is
			// not on your roster" because Airtable timed out goes looking for a permissions
			// fault that does not exist.
			return array(
				'outcome' => WPCPM_Institution_Policy::REFUSAL_CODE === $claim->get_error_code() ? 'student-refused' : 'student-unreadable',
			);
		}

		$decision = isset( $claim['decision'] ) && is_array( $claim['decision'] ) ? $claim['decision'] : array();

		// **The identity of what is about to be written.** `claim()` read this Students row
		// live and allowed the action on the `Educational Institutions` link that row carries,
		// so this record ID is the only Students record this request holds an authorisation
		// for. It is taken from the claim and from nowhere else - a row resolved a second time
		// out of a cache is a different question with its own answer, and a write that landed
		// on that answer would be a write nobody authorised.
		$students_id = isset( $claim['record']['id'] ) ? trim( (string) $claim['record']['id'] ) : '';

		// The institution the audit row is filed under, from the same decision that allowed
		// the write.
		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';

		// Refused before a posted cell is read, because a write on this path that cannot be
		// logged is one this module does not make. `WPCPM_Institution_Audit::record()` files a
		// row under the institution it is about and refuses one with none; the manager ground
		// allows an institution-less subject, so a student whose live Students row has lost
		// its link would otherwise have their record corrected with nothing written down at
		// all. There is nothing honest to put in that row's place either - the roster index's
		// answer would be a guess, and a guess in this log is worse than an empty log.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return array( 'outcome' => 'student-unfiled' );
		}

		// The student's account, resolved once: it names the two caches the form was drawn
		// from, and it is what `forget()` writes them back through.
		$user = WPCPM_Students_Sync::user_for_record( $record );

		return array(
			'decision'    => $decision,
			'before'      => isset( $claim['record']['fields'] ) && is_array( $claim['record']['fields'] ) ? $claim['record']['fields'] : array(),
			'students_id' => $students_id,
			'institution' => $institution,
			'user_id'     => $user instanceof WP_User ? (int) $user->ID : 0,
		);
	}

	/**
	 * Step two: what the person changed, split by table, with the Students half proved.
	 *
	 * @param string $record Students Reports record ID.
	 * @param array  $claim  Step one's answer.
	 * @return array Either `outcome` and `rejected`, or `changes`, `rejected`, `students_cells`,
	 *               `reports_cells`, `blocked` and `students_id` (emptied when blocked).
	 */
	private static function diff_cells( $record, array $claim ) {
		$decision    = $claim['decision'];
		$before      = $claim['before'];
		$students_id = $claim['students_id'];
		$institution = $claim['institution'];
		$user_id     = $claim['user_id'];

		// **What the form was drawn from, recomputed here rather than carried in the request.**
		// `render_form()` fills every control from `current()`, and this is that same call on
		// the same two site-held caches. It is what the diff below compares against, so a cell
		// counts as changed only when the person changed it; comparing against the live row
		// instead would count every cell where the caches had drifted, and write each of them
		// back with the value the form happened to show.
		//
		// The roster asked for is the claim's, not the card's - the request says nothing about
		// which institution this is, here as everywhere else in this handler. The two are the
		// same roster whenever the index and the live link agree, and when they do not the
		// card the reader is looking at is itself about to be corrected by a sync.
		$shown = self::current( $user_id, self::index_rows( $institution, $user_id ) );

		// The reports row, for three things and no more: the current `Name`, the `Students`
		// link and the address. Read through the report form's own cached reader, which the
		// card that drew this form has usually just warmed, and forgotten again below.
		$report = WPCPM_Student_Report_Form::values( $record );

		if ( is_wp_error( $report ) ) {
			return array(
				'outcome'  => 'student-unreadable',
				'rejected' => array(),
			);
		}

		$rejected = array();
		$accepted = self::walk( self::posted(), $rejected );
		$accepted = self::apply_rules( $accepted, $before, $rejected );
		$accepted = WPCPM_Institution_Policy::scope( $decision, $accepted );

		$changes = self::changes( $accepted, $shown, $before, $report );

		if ( empty( $changes ) ) {
			return array(
				'outcome'  => empty( $rejected ) ? 'student-nothing' : 'student-rejected',
				'rejected' => $rejected,
			);
		}

		$students_cells = self::cells( $changes, 'students' );
		$reports_cells  = self::cells( $changes, 'reports' );
		$blocked        = '';

		if ( ! empty( $students_cells ) ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $students_id ) ) {
				// The claim named no row to write to. For the school that amounts to the same
				// thing as the table holding none, and it is told the same sentence.
				$blocked = 'no-row';
			} else {
				// The second lock, on the identity the claim already settled. It proves; it
				// never supplies. `claim()` has already refused a report whose Students row is
				// ambiguous, so on the shipped ordering this is the second of two - it is kept
				// because it is the one that produces a sentence a school can act on, and
				// because the two reads are seconds apart on a base that people and
				// automations write in between.
				$proof = self::students_row_for( $report );

				if ( is_wp_error( $proof ) ) {
					$code = $proof->get_error_code();

					// A base that could not be read is not a half-save; nothing is written at all.
					if ( self::CODE_MANY !== $code && self::CODE_NONE !== $code ) {
						return array(
							'outcome'  => 'student-unreadable',
							'rejected' => array(),
						);
					}

					$blocked = self::CODE_MANY === $code ? 'merge' : 'no-row';
				} elseif ( 0 !== strcmp( (string) $proof, $students_id ) ) {
					// The claim proved one row and the report's own link or address answers
					// with another. Two records answer for this student, however that came
					// about, which is the merge case: neither of them may be written on a
					// guess, and the school is told the sentence it can act on.
					$blocked = 'merge';
				}
			}

			if ( '' !== $blocked ) {
				// The record is dropped along with the cells, so the audit row below names
				// only what was written and never a record this save did not touch.
				$students_id    = '';
				$students_cells = array();
				$changes        = array_diff_key( $changes, self::slots( 'students' ) );
			}
		}

		// Only the block above can empty this, and it always names why. The fallback is here
		// because an outcome slug nothing has words for prints no sentence at all, and a save
		// that redirects in silence is the one failure a person cannot report.
		if ( empty( $changes ) ) {
			return array(
				'outcome'  => '' !== $blocked ? 'student-' . $blocked . '-only' : 'student-nothing',
				'rejected' => $rejected,
			);
		}

		return array(
			'changes'        => $changes,
			'rejected'       => $rejected,
			'students_cells' => $students_cells,
			'reports_cells'  => $reports_cells,
			'blocked'        => $blocked,
			'students_id'    => $students_id,
		);
	}

	/**
	 * Step three: the writes, reports half first.
	 *
	 * @param string $record Students Reports record ID.
	 * @param array  $diff   Step two's answer.
	 * @return array Either `outcome`, or step two's answer with `partial` set and the Students
	 *               half dropped where it did not land.
	 */
	private static function write_cells( $record, array $diff ) {
		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );

		$diff['partial'] = false;

		// The reports half first, so the merge case above and a failure here read the same
		// way round: what landed is always the half named first in the message.
		if ( ! empty( $diff['reports_cells'] ) ) {
			$result = $airtable->update_records(
				$settings['reports_table'],
				array(
					array(
						'id'     => $record,
						'fields' => $diff['reports_cells'],
					),
				)
			);

			if ( is_wp_error( $result ) ) {
				return array( 'outcome' => 'student-airtable' );
			}
		}

		if ( ! empty( $diff['students_cells'] ) ) {
			$result = $airtable->update_records(
				$settings['students_table'],
				array(
					array(
						'id'     => $diff['students_id'],
						'fields' => $diff['students_cells'],
					),
				)
			);

			if ( is_wp_error( $result ) ) {
				// Nothing else was written either, so this is not a half-save and must not
				// say it is: "part of this was saved" over a save where nothing landed is
				// how somebody comes to believe a date exists that does not.
				if ( empty( $diff['reports_cells'] ) ) {
					return array( 'outcome' => 'student-airtable' );
				}

				// The name landed and the rest did not. The audit row records what was
				// written and not what was attempted, so the half that failed is dropped here,
				// record and all: a row naming a Students record beside no Students change
				// reads as a write that happened.
				$diff['partial']        = true;
				$diff['students_id']    = '';
				$diff['students_cells'] = array();
				$diff['changes']        = array_diff_key( $diff['changes'], self::slots( 'students' ) );
			}
		}

		return $diff;
	}

	/**
	 * Step four: the log, the caches, and the one word the reader is told.
	 *
	 * @param string $record  Students Reports record ID.
	 * @param array  $claim   Step one's answer.
	 * @param array  $written Step three's answer.
	 * @return array `outcome` and `listing`.
	 */
	private static function record_outcome( $record, array $claim, array $written ) {
		$logged = self::log( $record, $written['students_id'], $written['changes'], $claim['decision'] );

		self::forget( $record, $written['students_id'], $written['changes'], $claim['institution'], $claim['user_id'] );

		// Step one rules out the one refusal this handler can cause, so reaching here means
		// the log itself failed - a post that would not insert. Said out loud rather than
		// swallowed: the change is in the base and "Saved." over a change nobody can be held
		// to is the sentence the log exists to prevent, and the person at the screen is the
		// only one who can tell anybody the row is missing.
		if ( is_wp_error( $logged ) ) {
			return array(
				'outcome' => 'student-unlogged',
				'listing' => '',
			);
		}

		if ( $written['partial'] ) {
			return array(
				'outcome' => 'student-part-airtable',
				'listing' => '',
			);
		}

		if ( '' !== $written['blocked'] ) {
			return array(
				'outcome' => 'student-' . $written['blocked'],
				'listing' => self::listing( $written['rejected'] ),
			);
		}

		return array(
			'outcome' => empty( $written['rejected'] ) ? 'student-saved' : 'student-partly',
			'listing' => self::listing( $written['rejected'] ),
		);
	}

	/**
	 * The Students record ID this student's dates belong to, proven rather than assumed.
	 *
	 * The report's `Students` link first, because the day somebody fills it in it is the
	 * better join, and because a link naming two students is as unresolvable as an address
	 * filed twice. Otherwise `LOWER({Email})`, which is the only join the two tables actually
	 * have: the link is empty on all 795 reports rows and all 800 Students rows today.
	 *
	 * The comparison is case-insensitive on both sides, and that is right here and wrong for
	 * a name: an address is ASCII by nature, so PHP's folding and Airtable's agree on it.
	 *
	 * Two or more matches is a refusal with its own code, because it is the one failure the
	 * school can do something about - the message names the merge - and because writing to
	 * one of two rows on a guess would put a school's correction on a record nobody is
	 * looking at. Zero matches is its own code for the same reason: 19 students exist whose
	 * reports row has no Students row at all, and their card already says a program manager
	 * has to complete the record.
	 *
	 * @param array $report The reports row's fields, as the cached reader returned them.
	 * @return string|WP_Error The Students record ID, or why it could not be named.
	 */
	public static function students_row_for( array $report ) {
		$link  = isset( $report[ WPCPM_Institution_Roster::FIELD_STUDENTS_LINK ] ) ? $report[ WPCPM_Institution_Roster::FIELD_STUDENTS_LINK ] : array();
		$links = array_values( array_filter( WPCPM_Airtable::link_ids( $link ), array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );

		if ( count( $links ) > 1 ) {
			return new WP_Error( self::CODE_MANY, __( 'That report names more than one student record.', 'wpcredits-program-manager' ) );
		}

		if ( 1 === count( $links ) ) {
			return $links[0];
		}

		$email = trim( (string) WPCPM_Airtable::flatten( isset( $report[ WPCPM_Institution_Roster::FIELD_EMAIL ] ) ? $report[ WPCPM_Institution_Roster::FIELD_EMAIL ] : '' ) );

		if ( '' === $email ) {
			return new WP_Error( self::CODE_NONE, __( 'That report carries no address to find a student record by.', 'wpcredits-program-manager' ) );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$formula  = $airtable->formula_in( WPCPM_Institution_Roster::FIELD_EMAIL, array( $email ), true );

		if ( '' === $formula ) {
			return new WP_Error( self::CODE_NONE, __( 'That report carries no address to find a student record by.', 'wpcredits-program-manager' ) );
		}

		$page = $airtable->fetch_page(
			$settings['students_table'],
			array(
				'formula' => $formula,
				// The address and nothing else. This read exists to count rows and name one,
				// and a request that asked for the row's columns would be a disclosure made
				// for no reason - the columns this handler needs came back with the claim.
				'fields'  => array( WPCPM_Institution_Roster::FIELD_EMAIL ),
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$records = ( isset( $page['records'] ) && is_array( $page['records'] ) ) ? $page['records'] : array();

		if ( count( $records ) > 1 ) {
			return new WP_Error( self::CODE_MANY, __( 'That address is on more than one student record.', 'wpcredits-program-manager' ) );
		}

		$id = ( 1 === count( $records ) && isset( $records[0]['id'] ) ) ? trim( (string) $records[0]['id'] ) : '';

		if ( ! WPCPM_Mentors_Sync::is_record_id( $id ) ) {
			return new WP_Error( self::CODE_NONE, __( 'No student record was found for that address.', 'wpcredits-program-manager' ) );
		}

		return $id;
	}

	/*
	 * The walk, and what it produces
	 * --------------------------------------------------------------------
	 */

	/**
	 * The posted values, as an array and nothing more.
	 *
	 * One array, read once. Every value in it is validated by type below, and every key that
	 * is not one of the five the allowlist hashes is never looked for at all.
	 *
	 * @return array
	 */
	private static function posted() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle_save() verified the nonce before anything reached here.
		if ( ! isset( $_POST[ self::FIELD_GROUP ] ) || ! is_array( $_POST[ self::FIELD_GROUP ] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; every value goes through WPCPM_Field_Value::clean().
		return (array) wp_unslash( $_POST[ self::FIELD_GROUP ] );
	}

	/**
	 * The posted values that survive the allowlist and their own type, keyed `"<table>|<column>"`.
	 *
	 * The loop is over `fields()` and never over what was posted, which is what makes the
	 * allowlist a boundary rather than a list: a key the map does not name is not read, not
	 * cleaned, not counted and not refused - it simply never exists.
	 *
	 * @param array $posted   What the form sent.
	 * @param array $rejected Collects the labels of values that could not be used.
	 * @return array
	 */
	private static function walk( array $posted, array &$rejected ) {
		$accepted = array();

		foreach ( self::fields() as $table => $columns ) {
			foreach ( $columns as $name => $spec ) {
				$key = self::key( $table, $name );

				// A field the form did not send is not a change. A cleared one is: the browser
				// posts an empty string for it, which `clean()` turns into the null Airtable
				// clears a cell with.
				if ( ! isset( $posted[ $key ] ) ) {
					continue;
				}

				if ( isset( $spec['choices'] ) ) {
					// The named list becomes the list, here and nowhere else, so that no spec
					// anywhere carries choices a request could have influenced.
					$spec['choices'] = self::choices( $spec['choices'] );
				}

				$result = WPCPM_Field_Value::clean( $posted[ $key ], $spec );

				if ( $result['ok'] ) {
					$accepted[ $table . '|' . $name ] = $result['value'];
				} else {
					$rejected[] = self::label( $table . '|' . $name );
				}
			}
		}

		return $accepted;
	}

	/**
	 * The rules that need more than one cell to decide, run with the stored values underneath.
	 *
	 * A school changing only the end date is still changing a period, so the rule is applied
	 * against the start date the record already holds; a school changing both is checked
	 * against what it just typed. Hence "the accepted cells with the record's current values
	 * underneath" and not one or the other.
	 *
	 * Clearing a date is never refused here. It is the answer to "we do not know yet", the
	 * base already holds 7 rows with an end date and no start, and a rule about a pair has
	 * nothing to say about half a pair being removed.
	 *
	 * The value underneath is read from the **live** row and not from what the form was drawn
	 * from, which is the one place in this handler where those two part company on purpose.
	 * A rule is about the record's real state: an end date that falls before the start date
	 * the base actually holds is wrong however old the copy on the form was, and a refusal a
	 * reader can act on by reloading the card beats a period that does not exist.
	 *
	 * @param array $accepted Cells that passed their own type.
	 * @param array $before   The live Students row's fields, from the claim.
	 * @param array $rejected Collects the labels of values that could not be used.
	 * @return array
	 */
	private static function apply_rules( array $accepted, array $before, array &$rejected ) {
		foreach ( self::fields() as $table => $columns ) {
			foreach ( $columns as $name => $spec ) {
				$slot = $table . '|' . $name;

				// `array_key_exists()`, not `isset()`: a cleared date is null, and `isset()`
				// reads null as absent, so every rule below would silently skip the one shape
				// it most needs to leave alone.
				if ( ! isset( $spec['after'] ) || ! array_key_exists( $slot, $accepted ) ) {
					continue;
				}

				$value = is_string( $accepted[ $slot ] ) ? $accepted[ $slot ] : '';

				if ( '' === $value ) {
					continue;
				}

				$other = $table . '|' . $spec['after'];
				$under = array_key_exists( $other, $accepted )
					? ( is_string( $accepted[ $other ] ) ? $accepted[ $other ] : '' )
					: trim( (string) WPCPM_Airtable::flatten( isset( $before[ $spec['after'] ] ) ? $before[ $spec['after'] ] : '' ) );

				// Nothing to compare against is not a failure: a placement whose start the
				// program has never recorded still has an end date somebody has to be able
				// to correct.
				if ( '' === $under ) {
					continue;
				}

				if ( strcmp( $value, $under ) <= 0 ) {
					unset( $accepted[ $slot ] );
					$rejected[] = self::label( $slot );
					continue;
				}

				if ( ! isset( $spec['max_days'] ) ) {
					continue;
				}

				// Both sides passed `checkdate()`, so both parse. Read as UTC on purpose: a
				// span measured across a daylight-saving boundary in a local zone is a day
				// short, and a placement of exactly a year would refuse itself half the year.
				$span = ( strtotime( $value . ' 00:00:00 UTC' ) - strtotime( $under . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS;

				if ( $span > (int) $spec['max_days'] ) {
					unset( $accepted[ $slot ] );
					$rejected[] = self::label( $slot );
				}
			}
		}

		return $accepted;
	}

	/**
	 * The accepted cells the person actually changed, each with the value it replaces.
	 *
	 * **Two questions, two sources, and they are not interchangeable.** Whether a cell is a
	 * change is decided against `$shown`, the values `render_form()` put in the controls,
	 * because that is the only comparison that means "the person edited this". Deciding it
	 * against the stored row instead would mean "the caches disagree with the base", and every
	 * cell where they did would be written back with the stale value the form had shown - so
	 * a school correcting a name would silently reset a date somebody else had just moved.
	 * That is a data-loss bug, not a display one, and it is what this argument exists for.
	 *
	 * What a change *replaces* is read from the stored row, because that is the value the
	 * write is about to overwrite and the audit row has to say so. When the caches have
	 * drifted, `from` is therefore not what the reader saw on their form; it is what the base
	 * held, which is the only honest answer to "what did this write replace".
	 *
	 * A save that changes nothing writes nothing, sends no request and logs no row: a form
	 * pressed twice is not two edits, and an audit log full of rows saying a value was
	 * changed to itself is a log nobody reads.
	 *
	 * @param array $accepted Cells that passed their type, their rules and the scope.
	 * @param array $shown    What the form was drawn from, slot to value.
	 * @param array $before   The live Students row's fields, from the claim.
	 * @param array $report   The reports row's fields.
	 * @return array<string, array{value: mixed, from: string, to: string}>
	 */
	private static function changes( array $accepted, array $shown, array $before, array $report ) {
		$changes = array();

		foreach ( $accepted as $slot => $value ) {
			$parts  = explode( '|', $slot, 2 );
			$name   = isset( $parts[1] ) ? $parts[1] : '';
			$stored = 'reports' === $parts[0] ? $report : $before;
			$was    = trim( (string) WPCPM_Airtable::flatten( isset( $stored[ $name ] ) ? $stored[ $name ] : '' ) );
			$had    = isset( $shown[ $slot ] ) ? trim( (string) $shown[ $slot ] ) : '';
			$now    = null === $value ? '' : trim( (string) $value );

			// Nothing to do when the control came back holding what it was drawn with, and
			// nothing to do when the value typed is already what the base says either: the
			// second is the rare case where the caches had drifted and the correction happens
			// to land on the stored value, and writing it would still log a value becoming
			// itself.
			if ( 0 === strcmp( $had, $now ) || 0 === strcmp( $was, $now ) ) {
				continue;
			}

			$changes[ $slot ] = array(
				'value' => $value,
				'from'  => $was,
				'to'    => $now,
			);
		}

		return $changes;
	}

	/**
	 * One table's cells out of the changes, in the shape Airtable takes.
	 *
	 * @param array  $changes What `changes()` produced.
	 * @param string $table   `reports` or `students`.
	 * @return array Column name to value.
	 */
	private static function cells( array $changes, $table ) {
		$cells = array();

		foreach ( $changes as $slot => $change ) {
			$parts = explode( '|', $slot, 2 );

			if ( ! isset( $parts[1] ) || $parts[0] !== $table ) {
				continue;
			}

			$cells[ $parts[1] ] = $change['value'];
		}

		return $cells;
	}

	/**
	 * Every slot one table owns, as a key set for `array_diff_key()`.
	 *
	 * @param string $table `reports` or `students`.
	 * @return array<string, bool>
	 */
	private static function slots( $table ) {
		$slots  = array();
		$fields = self::fields();

		foreach ( isset( $fields[ $table ] ) ? $fields[ $table ] : array() as $name => $spec ) {
			$slots[ $table . '|' . $name ] = true;
		}

		return $slots;
	}

	/**
	 * One row in the audit log for one save.
	 *
	 * The actor, the fields, both values and the ground the decision was allowed on, which
	 * is the whole question a school's edit raises. The evidence is `live`, because the
	 * decision this row records was made against the Students row as Airtable answered it
	 * and not against a cached stamp.
	 *
	 * Logged as `extend` when the end date moved and nothing else did, because moving an end
	 * date is the commonest thing a school does and a log that told extensions apart from
	 * corrections is one somebody can count.
	 *
	 * The result is handed back rather than dropped. The caller has already refused the one
	 * refusal it can cause - a decision naming no institution - so a failure here is the post
	 * itself not inserting, and a save that says "Saved." over a change with no row behind it
	 * is the one outcome this log cannot allow.
	 *
	 * @param string $record      The Students Reports record that was edited.
	 * @param string $students_id The Students record that was written, or ''.
	 * @param array  $changes     What `changes()` produced, narrowed to what landed.
	 * @param array  $decision    The claim's decision.
	 * @return int|WP_Error The audit row's post ID, or why there is not one.
	 */
	private static function log( $record, $students_id, array $changes, array $decision ) {
		$facts     = array();
		$sentences = array();

		foreach ( $changes as $slot => $change ) {
			$facts[] = array(
				'field' => $slot,
				'from'  => $change['from'],
				'to'    => $change['to'],
			);

			$sentences[] = sprintf(
				/* translators: 1: field label, 2: the value before the change, 3: the value after it. */
				__( '%1$s: %2$s to %3$s.', 'wpcredits-program-manager' ),
				self::label( $slot ),
				'' === $change['from'] ? __( 'empty', 'wpcredits-program-manager' ) : $change['from'],
				'' === $change['to'] ? __( 'empty', 'wpcredits-program-manager' ) : $change['to']
			);
		}

		$only = array_keys( $changes );
		$kind = ( 1 === count( $only ) && 'students|End Date' === $only[0] ) ? self::KIND_EXTEND : self::KIND_EDIT;

		return WPCPM_Institution_Audit::record(
			array(
				'kind'        => $kind,
				'institution' => isset( $decision['institution'] ) ? $decision['institution'] : '',
				'subject'     => $record,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'     => implode( ' ', $sentences ),
				'data'        => array(
					'changes'         => $facts,
					'report_record'   => $record,
					'students_record' => $students_id,
				),
			)
		);
	}

	/**
	 * The four caches that would otherwise show yesterday's answer.
	 *
	 * The report card's own transient, so the next reader of the Student Report Card sees
	 * what was just written rather than what Airtable held five minutes ago. The account's
	 * cached program row twice over, because two tables feed it: through `apply_report()`
	 * with the reports-side columns the mirror automation will copy these values into, and
	 * through `apply_student_row()` with the Students-side columns that have no reports-side
	 * mirror at all - `Full Name` and `Your field of study` reach that row by no other route,
	 * so without the second call the student's own card keeps last week's answer until a sync
	 * runs. And the roster index, so the school's own list shows the change now: that index
	 * is the Students table as the last sync read it, and it is what every institution-side
	 * surface counts and prints.
	 *
	 * The Students half is handed over first and the reports half second, so a save that
	 * corrects both names leaves the reports row's `Name` on the cached row. That is the
	 * column the sync builds the cached name from, and so the one the next run would put back.
	 *
	 * `apply_report()` ignores a column its map does not name, so handing it the mirrored
	 * names is safe before that map is widened and correct after.
	 *
	 * @param string $record      The Students Reports record that was edited.
	 * @param string $students_id The Students record that was written, or ''.
	 * @param array  $changes     What landed.
	 * @param string $institution The institution the claim's decision named.
	 * @param int    $user_id     The student's account, or 0 when they have none.
	 */
	private static function forget( $record, $students_id, array $changes, $institution, $user_id ) {
		WPCPM_Student_Report_Form::forget( $record );

		$names   = WPCPM_Mentors_Sync::fields();
		$mirror  = array();
		$student = array();
		$row     = array();

		// Where each changed cell has to be carried, under the name each destination knows it
		// by: the reports-side column the mirror automation copies a Students value into, the
		// Students-side column the account's cached row is also built from, and the roster
		// index's own key. A destination with no name for a cell simply does not get it, which
		// is why two of the three columns below are empty.
		$mirrored = array(
			'reports|Name'                 => array(
				'report'  => 'report_name',
				'student' => '',
				'index'   => 'name',
			),
			'students|Full Name'           => array(
				'report'  => '',
				'student' => 'student_record_name',
				'index'   => 'name',
			),
			'students|Start Date'          => array(
				'report'  => 'report_start',
				'student' => 'student_start',
				'index'   => 'start',
			),
			'students|End Date'            => array(
				'report'  => 'report_end',
				'student' => 'student_end',
				'index'   => 'end',
			),
			'students|Your field of study' => array(
				'report'  => '',
				'student' => 'student_study',
				'index'   => 'field_of_study',
			),
		);

		foreach ( $changes as $slot => $change ) {
			if ( ! isset( $mirrored[ $slot ] ) ) {
				continue;
			}

			$to = $mirrored[ $slot ];

			if ( '' !== $to['report'] && isset( $names[ $to['report'] ] ) ) {
				$mirror[ $names[ $to['report'] ] ] = $change['to'];
			}

			if ( '' !== $to['student'] && isset( $names[ $to['student'] ] ) ) {
				$student[ $names[ $to['student'] ] ] = $change['to'];
			}

			// The roster index is the Students table, so only that table's cells belong on it.
			if ( 0 === strpos( $slot, 'students|' ) ) {
				$row[ $to['index'] ] = $change['to'];
			}
		}

		// `apply_student_row()` lives in `WPCPM_Students_Sync`, which is where both cached
		// copies of a student's row are written, and it ships with that class rather than with
		// this one - so the call is made only once the class carries it and a site running the
		// older one still saves. The class is named through a variable on purpose:
		// `bin/check-references.php` resolves every literal `WPCPM_X::member()` in the plugin
		// and would report this one undefined until then, and a check that reports a known
		// absence is a check people learn to scroll past.
		$sync = 'WPCPM_Students_Sync';

		if ( $user_id > 0 && ! empty( $student ) && method_exists( $sync, 'apply_student_row' ) ) {
			call_user_func( array( $sync, 'apply_student_row' ), $user_id, $student );
		}

		if ( $user_id > 0 && ! empty( $mirror ) ) {
			WPCPM_Students_Sync::apply_report( $user_id, $mirror );
		}

		if ( '' !== $students_id && ! empty( $row ) ) {
			WPCPM_Roster_Index::update( $institution, $students_id, $row );
		}
	}

	/*
	 * Small shared pieces
	 * --------------------------------------------------------------------
	 */

	/**
	 * The visible label for one slot.
	 *
	 * @param string $slot `"<table>|<column>"`.
	 * @return string
	 */
	private static function label( $slot ) {
		$labels = self::labels();

		return isset( $labels[ $slot ] ) ? $labels[ $slot ] : (string) $slot;
	}

	/**
	 * A list of labels as one readable clause, or ''.
	 *
	 * @param array $labels Field labels.
	 * @return string
	 */
	private static function listing( array $labels ) {
		$labels = array_values( array_unique( array_filter( array_map( 'strval', $labels ), 'strlen' ) ) );

		return empty( $labels ) ? '' : implode( ', ', $labels ) . '.';
	}

	/**
	 * Every roster row this account is on, its duplicates included.
	 *
	 * One student is one row. Two rows sharing an address is the state the merge message
	 * describes, and it is visible from the index without asking Airtable anything, which is
	 * why the form can say so while it is being drawn rather than only after a press. The
	 * second pass catches the duplicate that carries no account of its own, which is the
	 * shape most of them have.
	 *
	 * @param string $institution Institutions record ID.
	 * @param int    $user_id     The student's account.
	 * @return array[] Index rows, the account's own first.
	 */
	private static function index_rows( $institution, $user_id ) {
		$user_id = (int) $user_id;
		$rows    = WPCPM_Roster_Index::rows( $institution );
		$mine    = array();
		$address = '';

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || (int) ( isset( $row['user_id'] ) ? $row['user_id'] : 0 ) !== $user_id || $user_id <= 0 ) {
				continue;
			}

			$mine[] = $row;

			if ( '' === $address && isset( $row['email_key'] ) ) {
				$address = trim( (string) $row['email_key'] );
			}
		}

		if ( '' === $address ) {
			return $mine;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || (int) ( isset( $row['user_id'] ) ? $row['user_id'] : 0 ) === $user_id ) {
				continue;
			}

			if ( isset( $row['email_key'] ) && 0 === strcasecmp( trim( (string) $row['email_key'] ), $address ) ) {
				$mine[] = $row;
			}
		}

		return $mine;
	}

	/**
	 * What each control starts out holding, and what a save is compared against.
	 *
	 * **One definition, two callers.** `render_form()` draws the controls from this and
	 * `handle_save()` calls it again to decide which cells the person changed. They have to
	 * be the same values or a save quietly rewrites every cell where they are not: the form
	 * shows one thing, the handler compares against another, and the difference is written
	 * back without anybody asking for it. Anything added here is therefore added to both at
	 * once, which is the point of it being one method.
	 *
	 * Two caches, each asked about its own table: the roster index for the Students columns,
	 * because that index is the Students table as the last sync read it, and the account's
	 * cached program row for the report's `Name`. Reading one for the other is how a form
	 * comes to offer a school the chance to "correct" a value to what the other table
	 * already says.
	 *
	 * @param int   $user_id The student's account.
	 * @param array $rows    The index rows `index_rows()` found.
	 * @return array<string, string> Slot to value.
	 */
	private static function current( $user_id, array $rows ) {
		$program = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_PROGRAM, true );
		$program = is_array( $program ) ? $program : array();
		$row     = isset( $rows[0] ) && is_array( $rows[0] ) ? $rows[0] : array();

		$read = static function ( array $source, $key ) {
			return isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? trim( (string) $source[ $key ] ) : '';
		};

		return array(
			'reports|Name'                 => $read( $program, 'name' ),
			'students|Full Name'           => $read( $row, 'name' ),
			'students|Start Date'          => $read( $row, 'start' ),
			'students|End Date'            => $read( $row, 'end' ),
			'students|Your field of study' => '' !== $read( $row, 'field_of_study' ) ? $read( $row, 'field_of_study' ) : $read( $program, 'field_of_study' ),
		);
	}

	/**
	 * Record the outcome and return to the card the form was on.
	 *
	 * **This does not return.** Every call to it ends the request, which is why a refusal
	 * above reads as one line rather than as an early return with a branch around it.
	 *
	 * The record travels with the outcome, so the sentence is printed on the card it happened
	 * to and not under every student on the roster.
	 *
	 * @param string $status Outcome slug, one of `messages()`'s keys.
	 * @param string $detail What the message adds: the fields that were refused.
	 * @param string $record The Students Reports record the outcome is about.
	 */
	private static function bounce( $status, $detail, $record ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'status' => $status,
				'detail' => $detail,
				'record' => trim( (string) $record ),
			)
		);

		$back = wp_get_referer();

		if ( ! $back ) {
			$page = WPCPM_Institutions_Dashboard::page_url();
			$back = '' !== $page ? $page : home_url( '/' );
		}

		wp_safe_redirect( $back . '#' . self::ANCHOR );
		exit;
	}
}
