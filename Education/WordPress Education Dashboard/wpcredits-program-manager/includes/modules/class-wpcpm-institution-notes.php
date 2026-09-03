<?php
/**
 * Institutions module - the notes a school keeps on its own students.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Notes block on a student's card: the school's own record of a student it sent.
 *
 * A school wants somewhere to write "spoke to her tutor, extension agreed" against a
 * student it sent, and it wants that somewhere not to be the mentor's notebook. The mentor
 * is writing about the same student at the same time, about calls the school was not on,
 * and neither of them agreed to be read by the other. So the two sets of notes are the same
 * private post type with the same key and one meta between them: `META_AUDIENCE`, which
 * `WPCPM_Mentor_Notes::audience_of()` reads and nothing else does. Absent means the
 * mentor's, so every note written before this release keeps exactly the meaning it had.
 *
 * **A second class rather than a second mode of the first one**, because the storage is all
 * the two share. The mentor's gate is their synced mentee list; this one's is the policy,
 * on the student's own account stamp, with the agreement gate inside it. The mentor's page
 * is the mentors dashboard, this one's card is the institution dashboard's, and the two
 * handlers redirect to different pages with different flashes. A single class carrying both
 * would be one `if` away from drawing a school's notes on a mentor's page.
 *
 * **The notebook is the decision's institution and never the request's.** `decide()` names
 * the institution the reader was allowed on - their own, for a member; the student's own
 * stamp, for a program manager - and every note is stamped with it and read back by it. A
 * student who transfers takes their card to the new school and leaves the old school's
 * notes behind, which is what a private notebook means. That comparison is routing inside
 * one school's own notes, not a fence: whether this reader may see this student at all is
 * `decide()`'s answer and only its answer.
 *
 * **So the card's promise says "while this student is on your roster", because that is the
 * part of it that is true.** A transfer moves the account's stamp, the old school loses the
 * card along with the student, and a manager opening the card is decided against the new
 * school: the old notes are then read by nobody. Given the choice between making the promise
 * true and making it accurate, this takes the second. Making it true would mean either a
 * school reading a card the program has stopped showing them, or one card mixing two
 * schools' notebooks, and neither is a private notebook. What is left behind is bounded: the
 * rows go with every other note when `WPCPM_Mentor_Notes::delete_all()` runs on uninstall.
 *
 * Nothing here reaches Airtable, so the fence is `decide()` on the cached subject rather
 * than `claim()`: a live read is what an action that discloses or writes a live record
 * deserves, and this one writes a note into the site's own database.
 *
 * There is no `delete_all()` here, and there should not be: these are rows of
 * `WPCPM_Mentor_Notes::POST_TYPE`, and that class's own `delete_all()` takes every row of
 * the type on uninstall. A second sweep over the same posts would be a second thing to keep
 * in step with the first.
 */
class WPCPM_Institution_Notes {

	/** Add a note about one student. Nonce keyed to the subject account. */
	const ACTION_ADD = 'wpcpm_add_institution_note';

	/** Delete one note. Nonce keyed to the note. */
	const ACTION_DELETE = 'wpcpm_delete_institution_note';

	/**
	 * Post meta: which institution's notebook this note is in.
	 *
	 * Written from the decision and never from the form, and read back the same way.
	 */
	const META_INSTITUTION = '_wpcpm_note_institution';

	/** Audit kind: a note was added. */
	const KIND_NOTE_ADDED = 'note_added';

	/** Audit kind: a note was deleted. */
	const KIND_NOTE_DELETED = 'note_deleted';

	/** Flash channel, read by the card. */
	const FLASH = 'institution_note';

	/** The block's anchor, so a saved note lands back on the notes it changed. */
	const ANCHOR = 'wpcpm-institution-notes';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_ADD, array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'handle_delete' ) );
	}

	/*
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether a user may read one institution note.
	 *
	 * The per-note gate `WPCPM_Mentor_Notes::user_can_read_note()` hands an institution
	 * note to. Two questions, both of them asked of stored state: may this reader see the
	 * student the note is about, and is the note in the notebook that reader was allowed
	 * on. A mentor of this very student fails the first, because a mentee list is not a
	 * membership and the policy has never heard of one; a member of another school fails
	 * it too; a program manager passes it, as they pass every fence in this plugin.
	 *
	 * An account that has been deleted since the note was written fails closed: the subject
	 * the policy decides against is the account's own stamp, and there is no account to ask.
	 *
	 * @param int|WP_Post      $note The note, or its ID.
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function user_can_read( $note, $user = null ) {
		$note = $note instanceof WP_Post ? $note : get_post( $note );

		if ( ! $note instanceof WP_Post || WPCPM_Mentor_Notes::POST_TYPE !== $note->post_type ) {
			return false;
		}

		if ( WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION !== WPCPM_Mentor_Notes::audience_of( $note ) ) {
			return false;
		}

		$record  = (string) get_post_meta( $note->ID, WPCPM_Mentor_Notes::META_STUDENT, true );
		$student = WPCPM_Students_Sync::user_for_record( $record );

		if ( ! $student instanceof WP_User ) {
			return false;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_VIEW_STUDENT,
			self::subject( $student->ID ),
			$user
		);

		if ( empty( $decision['allowed'] ) ) {
			return false;
		}

		return self::belongs( $note, isset( $decision['institution'] ) ? $decision['institution'] : '' );
	}

	/**
	 * One institution's notes about one student, newest first.
	 *
	 * The audience narrows the post type to the school's half; the stamp narrows that to
	 * this school's own. Both are needed: the first keeps the mentor's notes off this card,
	 * the second keeps a previous school's notes off it after a transfer.
	 *
	 * @param string $student_record Airtable record ID of the student.
	 * @param string $institution    Airtable record ID of the institution whose notes these are.
	 * @return WP_Post[]
	 */
	public static function notes_for( $student_record, $institution ) {
		$institution = trim( (string) $institution );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return array();
		}

		$notes = array();

		foreach ( WPCPM_Mentor_Notes::get_notes( $student_record, WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION ) as $note ) {
			if ( self::belongs( $note, $institution ) ) {
				$notes[] = $note;
			}
		}

		return $notes;
	}

	/**
	 * Whether a note is in this institution's notebook.
	 *
	 * `strcmp()` and not the database's idea of equality: record IDs are case-sensitive and
	 * a collation is not, and this is the same care `WPCPM_Institution_Audit::entries_for()`
	 * takes with the rows it reads back. A note with no stamp belongs to no notebook and is
	 * drawn on none, which is where a hand-made row ends up.
	 *
	 * @param WP_Post $note        The note.
	 * @param string  $institution Airtable record ID of the institution.
	 * @return bool
	 */
	private static function belongs( WP_Post $note, $institution ) {
		$stored = trim( (string) get_post_meta( $note->ID, self::META_INSTITUTION, true ) );

		return '' !== $stored && 0 === strcmp( $stored, trim( (string) $institution ) );
	}

	/**
	 * The policy subject for a student's card: the account's own institution stamp.
	 *
	 * One builder, used by the renderer, both handlers and the per-note gate, so all four
	 * decide against the same thing. The evidence the subject carries is what the audit row
	 * records, which is why the handlers keep the subject rather than rebuilding it.
	 *
	 * @param int $user_id The student's account.
	 * @return array
	 */
	private static function subject( $user_id ) {
		return WPCPM_Institution_Policy::subject_student_account( (int) $user_id );
	}

	/**
	 * The student's program record, from the account's own stamp.
	 *
	 * The key both cards share: a note is filed against the Students Reports record, which
	 * is what the mentor's notes are filed against too, so one student has one row of notes
	 * per audience rather than one per surface that found them.
	 *
	 * @param int $user_id The student's account.
	 * @return string Airtable record ID, or an empty string.
	 */
	private static function student_record( $user_id ) {
		return WPCPM_Mentor_Calls::student_record( (int) $user_id );
	}

	/*
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The notes block, drawn inside one student's card on the institution dashboard.
	 *
	 * Takes the account and nothing else. The institution is the decision's, not the roster
	 * the reader came from and not an argument a caller could pass: a card drawn for a
	 * manager through the switcher and a card drawn for a member both show the notebook the
	 * policy named, and there is no third answer for the two of them to disagree about.
	 *
	 * Reading is `ACT_VIEW_STUDENT` and writing is `ACT_EDIT_STUDENT`, asked separately.
	 * Today no ground splits them, so a reader is a writer; a field-scoped ground added
	 * later narrows this block without it being edited, which is the point of asking twice.
	 * When writing is refused the history draws with no form and no explanation: why one
	 * reader may write and another may not is the policy's business, and a page that
	 * explained it would be answering questions about somebody else's membership.
	 *
	 * @param int $user_id The student's account.
	 */
	public static function render( $user_id ) {
		$user_id = (int) $user_id;
		$subject = self::subject( $user_id );
		$view    = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_VIEW_STUDENT, $subject );

		if ( empty( $view['allowed'] ) ) {
			return;
		}

		$institution = isset( $view['institution'] ) ? trim( (string) $view['institution'] ) : '';
		$record      = self::student_record( $user_id );

		// Read before the block below decides whether there is anything to draw, and read
		// whether or not it is printed: an outcome nobody is shown is one that sits in user
		// meta until it appears on a card weeks later, about something else.
		$message = self::message_for( $user_id );

		// Both halves of "a school's note about a student it sent" have to be true before
		// there is a notebook: a student with no program record has nothing to file against,
		// and a card a program manager opened for a student linked to no institution belongs
		// to no school's notes. Neither is an error, so neither says anything - to anybody
		// but the person who has just pressed Save, who is owed the reason nothing was saved.
		// `unlinked` is refused on exactly this condition, so this is the only place that can
		// print it, and without it that outcome is written and never read.
		if ( '' === $record || ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			if ( null !== $message ) {
				printf( '<section class="wpcpm-notes wpcpm-notes--institution" id="%s">', esc_attr( self::ANCHOR ) );
				self::render_message( $message );
				echo '</section>';
			}

			return;
		}

		$notes = self::notes_for( $record, $institution );
		$write = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EDIT_STUDENT, $subject );

		// The mentor card's own classes, from the stylesheet institution.css already depends
		// on, plus one modifier for anything this block wants of its own. Two notes lists
		// that look like two different features would be a worse answer than one that reads
		// the same in both places.
		printf( '<section class="wpcpm-notes wpcpm-notes--institution" id="%s">', esc_attr( self::ANCHOR ) );

		printf(
			'<h4 class="wpcpm-notes__title">%1$s <span class="wpcpm-notes__count">%2$s</span></h4>',
			esc_html__( 'Your notes', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $notes ) ) )
		);

		// Said on the card and not only in the code, because somebody typing into a box about
		// a named student is entitled to know who reads it - and for how long, which is the
		// half a promise usually leaves out. The class docblock says why the sentence is
		// worded to the roster rather than to the institution.
		printf(
			'<p class="wpcpm-notes__intro">%s</p>',
			esc_html__( 'These notes stay with your institution and with the program managers for as long as this student is on your roster. The mentor does not see them, and the mentor\'s own notes are not shown here. A student who transfers takes their card to their new institution and leaves these notes behind, where nobody reads them again.', 'wpcredits-program-manager' )
		);

		self::render_message( $message );

		if ( empty( $notes ) ) {
			printf(
				'<p class="wpcpm-notes__empty">%s</p>',
				esc_html__( 'No notes yet.', 'wpcredits-program-manager' )
			);
		} else {
			echo '<ol class="wpcpm-notes__list">';

			foreach ( $notes as $note ) {
				self::render_note( $note, $user_id );
			}

			echo '</ol>';
		}

		if ( ! empty( $write['allowed'] ) ) {
			self::render_form( $user_id );
		}

		echo '</section>';
	}

	/**
	 * Render one note.
	 *
	 * Author and time on every row: several people at one school share this notebook, and a
	 * note nobody signed is a note nobody can ask about.
	 *
	 * @param WP_Post $note    The note.
	 * @param int     $user_id The student's account, so a deletion returns to this card.
	 */
	private static function render_note( WP_Post $note, $user_id ) {
		$author = get_userdata( (int) $note->post_author );

		echo '<li class="wpcpm-note">';

		echo '<div class="wpcpm-note__meta">';
		printf(
			'<time datetime="%1$s">%2$s</time>',
			esc_attr( get_post_time( 'c', true, $note ) ),
			esc_html( get_post_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $note ) )
		);
		printf(
			' <span class="wpcpm-note__author">%s</span>',
			esc_html(
				$author instanceof WP_User
					? $author->display_name
					: __( 'Unknown author', 'wpcredits-program-manager' )
			)
		);
		echo '</div>';

		// Stored as plain text, so escaping then adding paragraphs is enough: no markup is
		// ever accepted from the form.
		echo '<div class="wpcpm-note__body">' . wp_kses_post( wpautop( esc_html( $note->post_content ) ) ) . '</div>';

		if ( self::can_delete( $note ) ) {
			echo '<form class="wpcpm-note__delete" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_DELETE . '_' . $note->ID );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE ) . '" />';
			printf( '<input type="hidden" name="note_id" value="%d" />', (int) $note->ID );
			printf( '<input type="hidden" name="student" value="%d" />', (int) $user_id );
			printf(
				'<button type="submit" class="wpcpm-note__delete-button" onclick="return confirm(%1$s)">%2$s</button>',
				esc_attr( wp_json_encode( __( 'Delete this note? This cannot be undone.', 'wpcredits-program-manager' ) ) ),
				esc_html__( 'Delete', 'wpcredits-program-manager' )
			);
			echo '</form>';
		}

		echo '</li>';
	}

	/**
	 * Whether the current user may delete one note.
	 *
	 * Its author, or a program manager, and the same rule as the mentor's notes for the same
	 * reason: colleagues at one school are equals here, and one of them erasing another's
	 * record of a conversation is not something an equal should be able to do quietly. A
	 * note somebody else needs gone is asked for, or removed by a manager.
	 *
	 * @param WP_Post $note The note.
	 * @return bool
	 */
	private static function can_delete( WP_Post $note ) {
		return get_current_user_id() === (int) $note->post_author || current_user_can( WPCPM_Roles::CAP_MANAGE );
	}

	/**
	 * The add-note form.
	 *
	 * The only thing it posts about the subject is the account it is on, which is what the
	 * nonce is keyed to; the institution, the student's record and their name are all read
	 * from stored state by the handler.
	 *
	 * @param int $user_id The student's account.
	 */
	private static function render_form( $user_id ) {
		$field_id = 'wpcpm-institution-note-' . (int) $user_id;

		echo '<form class="wpcpm-notes__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_ADD . '_' . (int) $user_id );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_ADD ) . '" />';
		printf( '<input type="hidden" name="student" value="%d" />', (int) $user_id );

		printf(
			'<label class="wpcpm-notes__label" for="%1$s">%2$s</label>',
			esc_attr( $field_id ),
			esc_html__( 'Add a note', 'wpcredits-program-manager' )
		);

		printf(
			'<textarea class="wpcpm-notes__input" id="%1$s" name="note" rows="3" maxlength="%2$d" placeholder="%3$s" required></textarea>',
			esc_attr( $field_id ),
			(int) WPCPM_Mentor_Notes::MAX_LENGTH,
			esc_attr__( 'What does your institution need to remember about this placement?', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Save note', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * The one-shot outcome of the last thing somebody pressed on this card, if it is theirs.
	 *
	 * Keyed to the student it happened to, so a card opened for somebody else is answered
	 * with nothing: `WPCPM_Flash::take()` clears the message wherever it is read, and a
	 * message read on the wrong card is a message the right one never shows.
	 *
	 * Reading and printing are two methods because the caller has to read the outcome before
	 * it knows whether there is a notebook to print it in, and reading it is what takes it
	 * out of user meta.
	 *
	 * @param int $user_id The student's account, whose card is being drawn.
	 * @return array{0: string, 1: string}|null Type and message, or null.
	 */
	private static function message_for( $user_id ) {
		$flash  = WPCPM_Flash::take( self::FLASH );
		$status = is_array( $flash ) && isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';
		$about  = is_array( $flash ) && isset( $flash['student'] ) ? (int) $flash['student'] : 0;

		if ( $about !== (int) $user_id ) {
			return null;
		}

		$messages = array(
			'saved'    => array( 'success', __( 'Note saved.', 'wpcredits-program-manager' ) ),
			'deleted'  => array( 'success', __( 'Note deleted.', 'wpcredits-program-manager' ) ),
			'empty'    => array( 'error', __( 'Nothing was saved: the note was empty.', 'wpcredits-program-manager' ) ),
			'unlinked' => array( 'error', __( 'Nothing was saved: this student is not linked to an institution in the program records, so there is no notebook to file a note in.', 'wpcredits-program-manager' ) ),
			'error'    => array( 'error', __( 'That note could not be saved.', 'wpcredits-program-manager' ) ),
		);

		return isset( $messages[ $status ] ) ? $messages[ $status ] : null;
	}

	/**
	 * Print one outcome.
	 *
	 * @param array|null $message What `message_for()` returned.
	 */
	private static function render_message( $message ) {
		if ( ! is_array( $message ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-notes__message is-%1$s" role="status">%2$s</p>',
			esc_attr( $message[0] ),
			esc_html( $message[1] )
		);
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Save a note about one student.
	 *
	 * The nonce first and keyed to the subject account, so a token for writing about one
	 * student is not a token for writing about another; the account is read before it
	 * because the key names it, and it is the only thing this handler takes from the form
	 * besides the text. The institution, the student's program record and their name all
	 * come from stored state: a form that named its own institution would make the stamp on
	 * the note a claim rather than a fact.
	 *
	 * `decide()` and not `claim()`: nothing here reaches Airtable, and the account's own
	 * stamp is the evidence this action deserves. The audit row carries that evidence, the
	 * ground the decision was allowed on, and no part of the note itself - the text is the
	 * school's, and a log a program manager reads is not where it goes.
	 */
	public static function handle_add() {
		$student_id = WPCPM_Request::posted_id( 'student' );

		check_admin_referer( self::ACTION_ADD . '_' . $student_id );

		$subject  = self::subject( $student_id );
		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EDIT_STUDENT, $subject );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';
		$record      = self::student_record( $student_id );

		// Reachable although the form is only drawn where both of these hold: a sync between
		// the draw and the press can take either away. The card the redirect lands on draws
		// no notebook on exactly this condition and prints this outcome in its place, which
		// is the only reason it can be seen at all.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) || '' === $record ) {
			self::finish( 'unlinked', $student_id, $institution );
		}

		// Read here rather than through `WPCPM_Request::posted_text()`, which sanitises as
		// single-line text: a note is prose, and a school typing three paragraphs about a
		// placement would get them back as one. The nonce above is what lets this read $_POST.
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$note = trim( mb_substr( $note, 0, WPCPM_Mentor_Notes::MAX_LENGTH ) );

		if ( '' === $note ) {
			self::finish( 'empty', $student_id, $institution );
		}

		$student = get_user_by( 'id', $student_id );
		$name    = $student instanceof WP_User ? (string) $student->display_name : '';

		$post_id = wp_insert_post(
			array(
				'post_type'    => WPCPM_Mentor_Notes::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_content' => $note,
				'post_title'   => sprintf(
					/* translators: 1: student name, 2: date and time. */
					__( 'Institution note on %1$s - %2$s', 'wpcredits-program-manager' ),
					'' !== $name ? $name : $record,
					wp_date( 'Y-m-d H:i' )
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::finish( 'error', $student_id, $institution );
		}

		update_post_meta( $post_id, WPCPM_Mentor_Notes::META_STUDENT, $record );
		update_post_meta( $post_id, WPCPM_Mentor_Notes::META_AUDIENCE, WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION );
		update_post_meta( $post_id, self::META_INSTITUTION, $institution );

		if ( '' !== $name ) {
			update_post_meta( $post_id, WPCPM_Mentor_Notes::META_STUDENT_NAME, $name );
		}

		self::log( self::KIND_NOTE_ADDED, $decision, $subject, $record, (int) $post_id, $student_id );

		self::finish( 'saved', $student_id, $institution );
	}

	/**
	 * Delete one note.
	 *
	 * The note is the subject, so the nonce is keyed to it and everything else is read out
	 * of it: which student it is about, which account that student has, and which notebook
	 * it is in. The form's `student` field is a destination and nothing more, and the
	 * redirect ignores it in favour of the account the note itself names.
	 *
	 * Three gates, in order: the note is one of ours and an institution's, the policy allows
	 * writing about that student, and the person pressing Delete wrote it or manages the
	 * program. The first two are answered with the policy's one refusal, because "no such
	 * note" and "not your school's note" must read the same way from outside.
	 */
	public static function handle_delete() {
		$note_id = WPCPM_Request::posted_id( 'note_id' );

		check_admin_referer( self::ACTION_DELETE . '_' . $note_id );

		$note = $note_id ? get_post( $note_id ) : null;

		if (
			! $note instanceof WP_Post
			|| WPCPM_Mentor_Notes::POST_TYPE !== $note->post_type
			|| WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION !== WPCPM_Mentor_Notes::audience_of( $note )
		) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$record  = (string) get_post_meta( $note->ID, WPCPM_Mentor_Notes::META_STUDENT, true );
		$student = WPCPM_Students_Sync::user_for_record( $record );

		if ( ! $student instanceof WP_User ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$subject  = self::subject( $student->ID );
		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_EDIT_STUDENT, $subject );
		$allowed  = isset( $decision['institution'] ) ? $decision['institution'] : '';

		if ( empty( $decision['allowed'] ) || ! self::belongs( $note, $allowed ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		if ( ! self::can_delete( $note ) ) {
			wp_die( esc_html__( 'You can only delete your own notes.', 'wpcredits-program-manager' ), 403 );
		}

		self::log( self::KIND_NOTE_DELETED, $decision, $subject, $record, (int) $note->ID, (int) $student->ID );

		wp_delete_post( $note->ID, true );

		self::finish( 'deleted', (int) $student->ID, (string) $allowed );
	}

	/**
	 * Write the audit row for a note.
	 *
	 * One row per applied change, carrying the ground the decision was allowed on and the
	 * evidence the subject was built from, as every row in this module does. **The note's
	 * text is not in it.** The log is a program manager's view of what an institution did;
	 * the note is the institution's own record of a student, and copying it into a second
	 * place that a different audience reads would undo the whole point of the audience.
	 *
	 * @param string $kind      One of this class's KIND_* constants.
	 * @param array  $decision  What decide() returned.
	 * @param array  $subject   The subject it decided on, for its evidence level.
	 * @param string $record    Airtable record ID of the student.
	 * @param int    $note_id   The note.
	 * @param int    $user_id   The student's account.
	 * @return int|WP_Error What the audit log returned.
	 */
	private static function log( $kind, array $decision, array $subject, $record, $note_id, $user_id ) {
		return WPCPM_Institution_Audit::record(
			array(
				'kind'        => $kind,
				'institution' => isset( $decision['institution'] ) ? $decision['institution'] : '',
				'subject'     => $record,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? $decision['ground'] : '',
				'evidence'    => isset( $subject['evidence'] ) ? $subject['evidence'] : WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => self::KIND_NOTE_DELETED === $kind
					? __( 'An institution note about this student was deleted. The text of the note is not kept here.', 'wpcredits-program-manager' )
					: __( 'An institution note was added about this student. The note stays on the student card and is not copied here.', 'wpcredits-program-manager' ),
				'data'        => array(
					'note'    => (int) $note_id,
					'account' => (int) $user_id,
				),
			)
		);
	}

	/**
	 * Record the outcome and return to the student's card.
	 *
	 * The destination is rebuilt here rather than taken from the request, so no form can
	 * bounce anybody elsewhere. A program manager keeps the institution they were looking
	 * at, and it is the decision's institution that travels, not the switcher's argument
	 * from the request they arrived on.
	 *
	 * **This does not return.** Every call to it ends the request, which is why the refusals
	 * above read as one line each.
	 *
	 * @param string $status      Outcome slug, one of the keys `message_for()` knows.
	 * @param int    $user_id     The student's account, whose card is the destination.
	 * @param string $institution The institution the outcome is about.
	 */
	private static function finish( $status, $user_id, $institution ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'status'  => (string) $status,
				'student' => (int) $user_id,
			)
		);

		$page = WPCPM_Institutions_Dashboard::page_url();

		if ( '' === $page ) {
			$page = home_url( '/' );
		}

		$args = array( WPCPM_Institution_Student_View::ARG => (int) $user_id );

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) && WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			$args[ WPCPM_Institution_Roster::ARG_VIEW ] = $institution;
		}

		wp_safe_redirect( add_query_arg( $args, $page ) . '#' . self::ANCHOR );
		exit;
	}
}
