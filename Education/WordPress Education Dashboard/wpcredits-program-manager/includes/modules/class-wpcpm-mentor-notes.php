<?php
/**
 * Mentors module — call notes against a student.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets a mentor keep a running history of notes against each of their students —
 * one per call, typically.
 *
 * Stored as a private post type rather than in user meta. Two reasons decide it.
 * The sync rewrites the whole `wpcpm_mentees` meta value on every run, so a note
 * kept in there would be destroyed the next time anything synced; and a history
 * grows without limit, which wants rows that can be ordered, paged and attributed
 * rather than one ever-growing serialized array that two browser tabs can clobber.
 *
 * Notes belong to the *student*, not to the mentor–student pair, and each one
 * shows who wrote it. A student normally has one mentor, but where they have two,
 * continuity of history is worth more than siloing it.
 */
class WPCPM_Mentor_Notes {

	const POST_TYPE = 'wpcpm_mentor_note';

	/** Post meta: which Airtable student record the note is about. */
	const META_STUDENT = '_wpcpm_student_record';

	/** Post meta: the student's name when the note was written, for admin listings. */
	const META_STUDENT_NAME = '_wpcpm_student_name';

	const ACTION_ADD    = 'wpcpm_add_note';
	const ACTION_DELETE = 'wpcpm_delete_note';

	/** Longest note accepted, to keep one paste from filling the table. */
	const MAX_LENGTH = 5000;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_ADD, array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Register the note post type.
	 *
	 * Invisible everywhere by design: not public, not queryable, not in REST, not
	 * in search, no admin UI. These are private records about named students, and
	 * the only route to them is the mentor page, which checks the reader against
	 * that student's assignment first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Mentor notes', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Mentor note', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'editor', 'author' ),
				// A capability type nothing is granted, so no role can reach these
				// through any generic post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_mentor_note', 'wpcpm_mentor_notes' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Whether a user may read and write notes about a student.
	 *
	 * This is the whole access control for notes: a mentor may only touch students
	 * their own synced list contains. Program managers may touch any.
	 *
	 * @param string           $student_record Airtable record ID of the student.
	 * @param int|WP_User|null $user           Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function user_can_access( $student_record, $user = null ) {
		$student_record = trim( (string) $student_record );

		if ( '' === $student_record || ! WPCPM_Mentors_Sync::is_record_id( $student_record ) ) {
			return false;
		}

		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) ) {
			return true;
		}

		if ( ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR ) ) {
			return false;
		}

		foreach ( WPCPM_Mentors_Dashboard::get_mentees( $user->ID ) as $mentee ) {
			if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === $student_record ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The stored row for a student, if the viewer can see it.
	 *
	 * @param string $student_record Airtable record ID.
	 * @param int    $mentor_id      Whose list to look in. Defaults to the current user's.
	 * @return array|null
	 */
	private static function student_row( $student_record, $mentor_id = 0 ) {
		$mentor_id = $mentor_id ? (int) $mentor_id : get_current_user_id();

		foreach ( WPCPM_Mentors_Dashboard::get_mentees( $mentor_id ) as $mentee ) {
			if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === (string) $student_record ) {
				return $mentee;
			}
		}

		return null;
	}

	/**
	 * Whether a *new* note may be added for a student.
	 *
	 * Reading and deleting stay available for finished students — the history is
	 * worth keeping — but a graduated student is not going to be called again, so
	 * nothing new gets written against them. Enforced here and not only by hiding
	 * the form, since a hidden form is not a restriction.
	 *
	 * @param string $student_record Airtable record ID.
	 * @param int    $mentor_id      Whose list to look in.
	 * @return bool
	 */
	public static function can_add_note( $student_record, $mentor_id = 0 ) {
		if ( ! self::user_can_access( $student_record ) ) {
			return false;
		}

		$row = self::student_row( $student_record, $mentor_id );

		// No row in the list being viewed means a program manager inspecting
		// without a mentor context; leave that to their judgment.
		if ( null === $row ) {
			return true;
		}

		return empty( $row['is_past'] );
	}

	/**
	 * Every note about a student, newest first.
	 *
	 * @param string $student_record Airtable record ID of the student.
	 * @return WP_Post[]
	 */
	public static function get_notes( $student_record ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $student_record ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 200,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_meta_query -- Indexed by meta value; the set is per student.
					array(
						'key'   => self::META_STUDENT,
						'value' => $student_record,
					),
				),
			)
		);
	}

	/**
	 * How many notes exist for a student.
	 *
	 * @param string $student_record Airtable record ID of the student.
	 * @return int
	 */
	public static function count_notes( $student_record ) {
		return count( self::get_notes( $student_record ) );
	}

	/**
	 * Save a new note.
	 */
	public static function handle_add() {
		$student = isset( $_POST['student'] ) ? sanitize_text_field( wp_unslash( $_POST['student'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.

		check_admin_referer( self::ACTION_ADD . '_' . $student );

		$mentor = isset( $_POST['mentor'] ) ? absint( wp_unslash( $_POST['mentor'] ) ) : 0;

		if ( ! self::user_can_access( $student ) ) {
			wp_die( esc_html__( 'You cannot add notes for that student.', 'wpcredits-program-manager' ), 403 );
		}

		if ( ! self::can_add_note( $student, $mentor ) ) {
			wp_die( esc_html__( 'Mentoring has finished for that student, so no new notes can be added.', 'wpcredits-program-manager' ), 403 );
		}

		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$note = trim( mb_substr( $note, 0, self::MAX_LENGTH ) );

		if ( '' === $note ) {
			self::redirect_back( $student, 'empty' );
		}

		$name = isset( $_POST['student_name'] ) ? sanitize_text_field( wp_unslash( $_POST['student_name'] ) ) : '';

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_content' => $note,
				'post_title'   => sprintf(
					/* translators: 1: student name, 2: date and time. */
					__( 'Note on %1$s — %2$s', 'wpcredits-program-manager' ),
					'' !== $name ? $name : $student,
					wp_date( 'Y-m-d H:i' )
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::redirect_back( $student, 'error' );
		}

		update_post_meta( $post_id, self::META_STUDENT, $student );

		if ( '' !== $name ) {
			update_post_meta( $post_id, self::META_STUDENT_NAME, $name );
		}

		self::redirect_back( $student, 'saved' );
	}

	/**
	 * Delete a note.
	 *
	 * Only its author, or a program manager, may remove one — a mentor should not
	 * be able to erase a colleague's record of a call.
	 */
	public static function handle_delete() {
		$note_id = isset( $_POST['note_id'] ) ? absint( wp_unslash( $_POST['note_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.

		check_admin_referer( self::ACTION_DELETE . '_' . $note_id );

		$note = $note_id ? get_post( $note_id ) : null;

		if ( ! $note instanceof WP_Post || self::POST_TYPE !== $note->post_type ) {
			wp_die( esc_html__( 'That note does not exist.', 'wpcredits-program-manager' ), 404 );
		}

		$student = (string) get_post_meta( $note->ID, self::META_STUDENT, true );

		if ( ! self::user_can_access( $student ) ) {
			wp_die( esc_html__( 'You cannot change notes for that student.', 'wpcredits-program-manager' ), 403 );
		}

		$is_author = ( get_current_user_id() === (int) $note->post_author );

		if ( ! $is_author && ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You can only delete your own notes.', 'wpcredits-program-manager' ), 403 );
		}

		wp_delete_post( $note->ID, true );

		self::redirect_back( $student, 'deleted' );
	}

	/**
	 * Return to the mentor page, focused on the student that was just edited.
	 *
	 * The destination is rebuilt here rather than taken from the request, so the
	 * form cannot be used to bounce anyone somewhere else.
	 *
	 * @param string $student Airtable record ID of the student.
	 * @param string $status  Outcome flag.
	 */
	private static function redirect_back( $student, $status ) {
		$page = WPCPM_Mentors_Dashboard::page_url();

		if ( '' === $page ) {
			$page = home_url( '/' );
		}

		WPCPM_Flash::set( 'note', $status );

		// `wpcpm_student` stays in the URL: it is view state, not a message. It says which
		// card to open, and that *should* survive a reload.
		$args = array( 'wpcpm_student' => $student );

		// Keep a program manager on the mentor they were inspecting. The nonce for this
		// request was verified by the handler that called us.
		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$mentor = WPCPM_Request::posted_id( 'mentor' );

			if ( $mentor ) {
				$args['wpcpm_mentor'] = $mentor;
			}
		}

		wp_safe_redirect( add_query_arg( $args, $page ) . '#' . self::anchor( $student ) );
		exit;
	}

	/**
	 * The page anchor for a student, so a save returns to the right card.
	 *
	 * @param string $student Airtable record ID of the student.
	 * @return string
	 */
	public static function anchor( $student ) {
		return 'wpcpm-student-' . sanitize_html_class( $student );
	}

	/**
	 * Which student the request is focused on, if any.
	 *
	 * A fragment never reaches the server, so the record ID travels as a query
	 * argument too — that is what lets the right card be rendered already open
	 * after a note is saved, with no JavaScript involved.
	 *
	 * @return string Airtable record ID, or an empty string.
	 */
	public static function focused_student() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$student = WPCPM_Request::text( 'wpcpm_student' );

		return WPCPM_Mentors_Sync::is_record_id( $student ) ? $student : '';
	}

	/**
	 * Render the notes history and the add-note form for one student.
	 *
	 * @param string $student_record Airtable record ID.
	 * @param string $student_name   Student's name, stored with any new note.
	 * @param int    $mentor_id      Mentor whose page is being viewed.
	 */
	public static function render( $student_record, $student_name, $mentor_id = 0 ) {
		if ( ! self::user_can_access( $student_record ) ) {
			return;
		}

		$notes   = self::get_notes( $student_record );
		$focused = ( self::focused_student() === $student_record );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag.
		$status = $focused ? sanitize_key( (string) WPCPM_Flash::take( 'note' ) ) : '';

		echo '<section class="wpcpm-notes">';

		printf(
			'<h4 class="wpcpm-notes__title">%1$s <span class="wpcpm-notes__count">%2$s</span></h4>',
			esc_html__( 'Notes', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $notes ) ) )
		);

		$messages = array(
			'saved'   => array( 'success', __( 'Note saved.', 'wpcredits-program-manager' ) ),
			'deleted' => array( 'success', __( 'Note deleted.', 'wpcredits-program-manager' ) ),
			'empty'   => array( 'error', __( 'Nothing was saved — the note was empty.', 'wpcredits-program-manager' ) ),
			'error'   => array( 'error', __( 'That note could not be saved.', 'wpcredits-program-manager' ) ),
		);

		if ( isset( $messages[ $status ] ) ) {
			printf(
				'<p class="wpcpm-notes__message is-%1$s" role="status">%2$s</p>',
				esc_attr( $messages[ $status ][0] ),
				esc_html( $messages[ $status ][1] )
			);
		}

		$can_add = self::can_add_note( $student_record, $mentor_id );

		if ( empty( $notes ) ) {
			echo '<p class="wpcpm-notes__empty">';
			echo esc_html(
				$can_add
					? __( 'No notes yet. Add one after your next call.', 'wpcredits-program-manager' )
					: __( 'No notes were recorded for this student.', 'wpcredits-program-manager' )
			);
			echo '</p>';
		} else {
			echo '<ol class="wpcpm-notes__list">';
			foreach ( $notes as $note ) {
				self::render_note( $note, $mentor_id );
			}
			echo '</ol>';
		}

		if ( $can_add ) {
			self::render_form( $student_record, $student_name, $mentor_id );
		} elseif ( ! empty( $notes ) ) {
			// Say why the form is absent rather than just leaving a gap.
			printf(
				'<p class="wpcpm-notes__closed">%s</p>',
				esc_html__( 'Mentoring has finished, so no new notes can be added. The history above is kept for reference.', 'wpcredits-program-manager' )
			);
		}

		echo '</section>';
	}

	/**
	 * Render one note.
	 *
	 * @param WP_Post $note      Note post.
	 * @param int     $mentor_id Mentor whose page is being viewed.
	 */
	private static function render_note( WP_Post $note, $mentor_id ) {
		$author    = get_userdata( (int) $note->post_author );
		$is_author = ( get_current_user_id() === (int) $note->post_author );
		$can_purge = ( $is_author || current_user_can( WPCPM_Roles::CAP_MANAGE ) );

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

		// Stored as plain text, so escaping then adding paragraphs is enough — no
		// markup is ever accepted from the form.
		echo '<div class="wpcpm-note__body">' . wp_kses_post( wpautop( esc_html( $note->post_content ) ) ) . '</div>';

		if ( $can_purge ) {
			echo '<form class="wpcpm-note__delete" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_DELETE . '_' . $note->ID );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE ) . '" />';
			printf( '<input type="hidden" name="note_id" value="%d" />', (int) $note->ID );
			printf( '<input type="hidden" name="mentor" value="%d" />', (int) $mentor_id );
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
	 * Render the add-note form.
	 *
	 * @param string $student_record Airtable record ID.
	 * @param string $student_name   Student's name.
	 * @param int    $mentor_id      Mentor whose page is being viewed.
	 */
	private static function render_form( $student_record, $student_name, $mentor_id ) {
		$field_id = 'wpcpm-note-' . sanitize_html_class( $student_record );

		echo '<form class="wpcpm-notes__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_ADD . '_' . $student_record );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_ADD ) . '" />';
		printf( '<input type="hidden" name="student" value="%s" />', esc_attr( $student_record ) );
		printf( '<input type="hidden" name="student_name" value="%s" />', esc_attr( $student_name ) );
		printf( '<input type="hidden" name="mentor" value="%d" />', (int) $mentor_id );

		printf(
			'<label class="wpcpm-notes__label" for="%1$s">%2$s</label>',
			esc_attr( $field_id ),
			esc_html__( 'Add a note', 'wpcredits-program-manager' )
		);

		printf(
			'<textarea class="wpcpm-notes__input" id="%1$s" name="note" rows="3" maxlength="%2$d" placeholder="%3$s" required></textarea>',
			esc_attr( $field_id ),
			(int) self::MAX_LENGTH,
			esc_attr__( 'What did you discuss on this call?', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Save note', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * Delete every note. Called on uninstall.
	 */
	public static function delete_all() {
		$notes = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $notes as $note_id ) {
			wp_delete_post( $note_id, true );
		}
	}
}
