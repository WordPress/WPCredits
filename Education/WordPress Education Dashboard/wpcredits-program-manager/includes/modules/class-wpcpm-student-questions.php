<?php
/**
 * A student's question to their mentor, sent from Need help? (AI Mentor R2, 1.123.103).
 *
 * Behind one switch, **Send to mentors** on the Need help? screen, which ships off. This holds the switch's
 * reader, the private post type a question is kept in, and the one decision every later caller asks first:
 * whether the signed-in account may send, and to whom (`mentor_of()`).
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Send to mentors switch, the question post type, and who may send.
 */
class WPCPM_Student_Questions {

	/**
	 * The post type a question is kept in: private, one post a question.
	 */
	const POST_TYPE = 'wpcpm_question';

	/**
	 * The refusals `mentor_of()` answers with, in the order it checks them.
	 */
	const ERROR_OFF         = 'wpcpm_send_off';
	const ERROR_NOT_STUDENT = 'wpcpm_not_student';
	const ERROR_NO_RECORD   = 'wpcpm_no_record';
	const ERROR_NO_MENTOR   = 'wpcpm_no_mentor';
	const ERROR_MENTOR_GONE = 'wpcpm_mentor_gone';
	const ERROR_PAST        = 'wpcpm_past';

	/** Most questions one student's account may send a UTC day. */
	const PER_DAY = 3;

	/** Longest line a student may add to a question: longer words are cut, never refused for length. */
	const NOTE_MAX = 600;

	/** Shortest question kept, in characters, once it is cleaned. */
	const QUESTION_MIN = 3;

	/** Most links one question carries. */
	const LINKS_MAX = 5;

	/** How long, in days, an answered question stays listed. */
	const ANSWERED_DAYS = 30;

	const ERROR_SHORT = 'wpcpm_question_short';

	/**
	 * What a question post holds, as meta: the student's Students record ID, the mentor's user ID and
	 * Mentors record ID, the line the student added, the links, the state, and when and by whom it was
	 * marked answered.
	 */
	const META_STUDENT       = '_wpcpm_question_student';
	const META_MENTOR        = '_wpcpm_question_mentor';
	const META_MENTOR_RECORD = '_wpcpm_question_mentor_record';
	const META_NOTE          = '_wpcpm_question_note';
	const META_LINKS         = '_wpcpm_question_links';
	const META_STATE         = '_wpcpm_question_state';
	const META_ANSWERED_AT   = '_wpcpm_question_answered_at';
	const META_ANSWERED_BY   = '_wpcpm_question_answered_by';

	/** Mark as answered: the `admin-post.php` action, and the channel of the line shown after it. */
	const ACTION_ANSWERED = 'wpcpm_question_answered';
	const FLASH_CHANNEL   = 'question';

	const STATE_WAITING  = 'waiting';
	const STATE_ANSWERED = 'answered';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		// Logged in only: there is no `nopriv` twin, so a visitor never reaches it.
		add_action( 'admin_post_' . self::ACTION_ANSWERED, array( __CLASS__, 'handle_answered' ) );
		// The student's own words are personal data: WordPress's Export and Erase Personal Data tools reach them.
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/** The privacy tools' ID for both, and how many posts one page of either handles. */
	const PRIVACY_ID   = 'wpcpm-student-questions';
	const PRIVACY_PAGE = 50;

	/**
	 * The questions' entry in WordPress's Export Personal Data tool.
	 *
	 * @param array $exporters WordPress's exporters.
	 * @return array The same, with ours.
	 */
	public static function register_exporter( $exporters ) {
		$exporters                     = is_array( $exporters ) ? $exporters : array();
		$exporters[ self::PRIVACY_ID ] = array(
			'exporter_friendly_name' => __( 'WPCredits questions to a mentor', 'wpcredits-program-manager' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * The questions' entry in WordPress's Erase Personal Data tool.
	 *
	 * @param array $erasers WordPress's erasers.
	 * @return array The same, with ours.
	 */
	public static function register_eraser( $erasers ) {
		$erasers                     = is_array( $erasers ) ? $erasers : array();
		$erasers[ self::PRIVACY_ID ] = array(
			'eraser_friendly_name' => __( 'WPCredits questions to a mentor', 'wpcredits-program-manager' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * The posts a student's account wrote: their questions, or their Need help? notes. A mentor's own note
	 * has the mentor as its author and no source, so it is never in either list.
	 *
	 * @param int    $user_id The student's account.
	 * @param string $kind    `questions` or `notes`.
	 * @param int    $offset  How many to skip.
	 * @return WP_Post[]
	 */
	private static function posts_of( $user_id, $kind, $offset ) {
		$args = array(
			'post_status'      => 'any',
			'author'           => (int) $user_id,
			'numberposts'      => self::PRIVACY_PAGE,
			'offset'           => (int) $offset,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		);

		if ( 'notes' === $kind ) {
			$args['post_type']  = WPCPM_Mentor_Notes::POST_TYPE;
			$args['meta_query'] = array(
				array(
					'key'   => WPCPM_Mentor_Notes::META_SOURCE,
					'value' => WPCPM_Mentor_Notes::SOURCE_NEED_HELP,
				),
			);
		} else {
			$args['post_type'] = self::POST_TYPE;
		}

		$posts = array();

		foreach ( get_posts( $args ) as $post ) {
			if ( $post instanceof WP_Post ) {
				$posts[] = $post;
			}
		}

		return $posts;
	}

	/**
	 * WordPress's Export Personal Data tool: a student's questions and Need help? notes, by account email.
	 *
	 * Each item lists the date, the words, and for a question the added line, the links and its state. No
	 * mentor's name or address is in it. The question and the note are stored through kses, which keeps `&`
	 * as `&amp;`, so the export decodes those entities and reads them as typed.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1; 50 questions and 50 notes at a time.
	 * @return array{data:array,done:bool}
	 */
	public static function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', trim( (string) $email ) );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$offset    = ( max( 1, (int) $page ) - 1 ) * self::PRIVACY_PAGE;
		$questions = self::posts_of( $user->ID, 'questions', $offset );
		$notes     = self::posts_of( $user->ID, 'notes', $offset );
		$data      = array();

		foreach ( $questions as $post ) {
			$rows = array(
				array(
					'name'  => __( 'Date', 'wpcredits-program-manager' ),
					'value' => $post->post_date_gmt . ' UTC',
				),
				array(
					'name'  => __( 'Question', 'wpcredits-program-manager' ),
					'value' => wp_specialchars_decode( $post->post_content, ENT_QUOTES ),
				),
			);

			$added = (string) get_post_meta( $post->ID, self::META_NOTE, true );
			if ( '' !== $added ) {
				$rows[] = array(
					'name'  => __( 'Added line', 'wpcredits-program-manager' ),
					'value' => $added,
				);
			}

			$links = get_post_meta( $post->ID, self::META_LINKS, true );
			if ( is_array( $links ) && array() !== $links ) {
				$rows[] = array(
					'name'  => __( 'Links', 'wpcredits-program-manager' ),
					'value' => implode( "\n", array_map( 'strval', $links ) ),
				);
			}

			$rows[] = array(
				'name'  => __( 'State', 'wpcredits-program-manager' ),
				'value' => self::STATE_ANSWERED === get_post_meta( $post->ID, self::META_STATE, true )
					? __( 'Answered', 'wpcredits-program-manager' )
					: __( 'Waiting', 'wpcredits-program-manager' ),
			);

			$data[] = array(
				'group_id'    => self::PRIVACY_ID,
				'group_label' => __( 'Questions sent from Need help?', 'wpcredits-program-manager' ),
				'item_id'     => 'wpcpm-student-question-' . (int) $post->ID,
				'data'        => $rows,
			);
		}

		foreach ( $notes as $post ) {
			$data[] = array(
				'group_id'    => 'wpcpm-student-need-help-notes',
				'group_label' => __( 'Need help? notes', 'wpcredits-program-manager' ),
				'item_id'     => 'wpcpm-student-need-help-note-' . (int) $post->ID,
				'data'        => array(
					array(
						'name'  => __( 'Date', 'wpcredits-program-manager' ),
						'value' => $post->post_date_gmt . ' UTC',
					),
					array(
						'name'  => __( 'Note', 'wpcredits-program-manager' ),
						'value' => wp_specialchars_decode( $post->post_content, ENT_QUOTES ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $questions ) < self::PRIVACY_PAGE && count( $notes ) < self::PRIVACY_PAGE,
		);
	}

	/**
	 * WordPress's Erase Personal Data tool: delete a student's questions and Need help? notes, by account email.
	 *
	 * Always reads the first 50 of each, as what it deletes is gone from the next read; done when a read
	 * comes back short. A mentor's own note is never reached.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1; not read, for the reason above.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );

		$removed  = 0;
		$retained = 0;
		$more     = false;
		$user     = get_user_by( 'email', trim( (string) $email ) );

		if ( $user instanceof WP_User && $user->exists() ) {
			foreach ( array( 'questions', 'notes' ) as $kind ) {
				$posts = self::posts_of( $user->ID, $kind, 0 );

				foreach ( $posts as $post ) {
					if ( wp_delete_post( $post->ID, true ) ) {
						++$removed;
					} else {
						++$retained;
					}
				}

				if ( count( $posts ) >= self::PRIVACY_PAGE ) {
					$more = true;
				}
			}
		}

		// A row WordPress would not delete is still stored, and the tool says so. A call that removed
		// nothing is done: the next one would read the same rows and never end.
		$messages = array();

		if ( $retained > 0 ) {
			$messages[] = sprintf(
				/* translators: %s: how many questions and Need help? notes could not be deleted. */
				_n( '%s question or Need help? note from a student could not be deleted and is still stored.', '%s questions or Need help? notes from a student could not be deleted and are still stored.', $retained, 'wpcredits-program-manager' ),
				number_format_i18n( $retained )
			);
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained > 0,
			'messages'       => $messages,
			'done'           => 0 === $removed || ! $more,
		);
	}

	/**
	 * Uninstall: delete every question, whatever its status, and its meta with it.
	 */
	public static function delete_all() {
		$questions = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $questions as $question_id ) {
			wp_delete_post( $question_id, true );
		}
	}

	/**
	 * Register the question post type.
	 *
	 * Invisible everywhere by design, as the mentor notes' type is: not public, not in REST, not in search,
	 * not exported, no admin screen. Rows are inserted `private`, never `publish`, so no author archive
	 * redirect ever names the student.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Student questions', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Student question', 'wpcredits-program-manager' ),
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
				'can_export'          => false,
				'supports'            => array( 'author' ),
				// A capability type nothing is granted, so no role reaches these through a generic post screen.
				'capability_type'     => array( 'wpcpm_question', 'wpcpm_questions' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Whether Send to mentors is switched on. The only reader of the setting.
	 *
	 * @return bool
	 */
	public static function switched_on() {
		return (bool) WPCPM_Settings::get_value( 'handbook_to_mentors', false );
	}

	/**
	 * Whether a student may send a question to their mentor, and who the mentor is.
	 *
	 * The first check to fail is the answer: the switch is off; the account does not hold the student role;
	 * it has no Students record; no mentor resolves; the mentor's list no longer holds the student (the
	 * mentors sync empties an inactive mentor's list, and no email goes to a mentor who has left); the
	 * student is past. The acting account is always the signed-in one: no caller passes another student.
	 *
	 * @param int $student_user_id The student's account.
	 * @return array|WP_Error `student` (WP_User), `mentor` (WP_User), `student_record`, `mentor_record`
	 *                        and `row` (the student's row in the mentor's list), or the refusal.
	 */
	public static function mentor_of( $student_user_id ) {
		if ( ! self::switched_on() ) {
			return new WP_Error( self::ERROR_OFF, __( 'Sending a question to a mentor is switched off.', 'wpcredits-program-manager' ) );
		}

		$student = get_user_by( 'id', (int) $student_user_id );

		if ( ! $student instanceof WP_User || ! WPCPM_Roles::user_has_role( $student, WPCPM_Roles::ROLE_STUDENT ) ) {
			return new WP_Error( self::ERROR_NOT_STUDENT, __( 'Only a student can send a question to a mentor.', 'wpcredits-program-manager' ) );
		}

		$student_record = WPCPM_Mentor_Calls::student_record( $student->ID );

		if ( '' === $student_record ) {
			return new WP_Error( self::ERROR_NO_RECORD, __( 'This account is not linked to a student record yet.', 'wpcredits-program-manager' ) );
		}

		$mentor = WPCPM_Mentor_Calls::mentor_for_student( $student->ID );

		if ( ! $mentor instanceof WP_User ) {
			return new WP_Error( self::ERROR_NO_MENTOR, __( 'This student has no mentor yet.', 'wpcredits-program-manager' ) );
		}

		$row = null;

		foreach ( WPCPM_Mentors_Dashboard::get_mentees( $mentor->ID ) as $mentee ) {
			if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === (string) $student_record ) {
				$row = $mentee;
				break;
			}
		}

		if ( null === $row ) {
			return new WP_Error( self::ERROR_MENTOR_GONE, __( 'The mentor\'s list no longer holds this student.', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( $row['is_past'] ) ) {
			return new WP_Error( self::ERROR_PAST, __( 'A student who has finished cannot send a question to a mentor.', 'wpcredits-program-manager' ) );
		}

		$mentor_record = get_user_meta( $mentor->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true );

		return array(
			'student'        => $student,
			'mentor'         => $mentor,
			'student_record' => (string) $student_record,
			'mentor_record'  => is_string( $mentor_record ) ? $mentor_record : '',
			'row'            => $row,
		);
	}

	/**
	 * Send a student's question to their mentor: keep it, then email it.
	 *
	 * Refused with `mentor_of()`'s error, unchanged, when the account may not send, and with
	 * `wpcpm_question_short` when the question is under three characters once cleaned. A question the
	 * student sent in the last 24 hours (the same words, as `WPCPM_Typed_Text::same_lines()` compares them)
	 * is a `duplicate`, judged before the daily limit so it spends no place of the day; a fourth question
	 * of a UTC day is a `limit`. Both are answers, not errors, and neither saves nor sends anything.
	 * Otherwise the question is saved first, private, and the email goes second: a failed email leaves it
	 * waiting, and the answer is `sent` with `emailed` false. A failed save gives the day's place back.
	 *
	 * @param int      $student_user_id The student's account: always the signed-in one.
	 * @param string   $question        The question as typed.
	 * @param string   $note            A line the student added, 0 to 600 characters.
	 * @param string[] $links           Addresses Need help? showed with its answer.
	 * @return array|WP_Error `question_id`, `student_record_id`, `mentor_record_id`, `state` (`sent`,
	 *                        `limit` or `duplicate`), `emailed` and `sent_at`, or the refusal.
	 */
	public static function send( $student_user_id, $question, $note = '', $links = array() ) {
		$who = self::mentor_of( (int) $student_user_id );

		if ( is_wp_error( $who ) ) {
			return $who;
		}

		$question = self::clean_question( $question );

		if ( mb_strlen( $question ) < self::QUESTION_MIN ) {
			return new WP_Error( self::ERROR_SHORT, __( 'Nothing was sent - the question was too short.', 'wpcredits-program-manager' ) );
		}

		$note    = trim( mb_substr( trim( sanitize_textarea_field( (string) $note ) ), 0, self::NOTE_MAX ) );
		$links   = self::allowed_links( (array) $links );
		$student = $who['student'];
		$mentor  = $who['mentor'];
		$answer  = array(
			'question_id'       => 0,
			'student_record_id' => (string) $who['student_record'],
			'mentor_record_id'  => (string) $who['mentor_record'],
			'state'             => 'sent',
			'emailed'           => false,
			'sent_at'           => 0,
		);

		$earlier = self::repeat_of( (int) $student->ID, $question );

		if ( $earlier instanceof WP_Post ) {
			$answer['question_id'] = (int) $earlier->ID;
			$answer['state']       = 'duplicate';
			$answer['sent_at']     = (int) get_post_time( 'U', true, $earlier );

			return $answer;
		}

		$key = WPCPM_Ceiling::key( 'question_day_' . (int) $student->ID );

		if ( ! WPCPM_Ceiling::claim( $key, self::PER_DAY, DAY_IN_SECONDS ) ) {
			$answer['state'] = 'limit';

			return $answer;
		}

		$now     = time();
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_author'  => (int) $student->ID,
					'post_content' => $question,
					'post_title'   => sprintf(
						/* translators: %s: date and time. */
						__( 'Question - %s', 'wpcredits-program-manager' ),
						wp_date( 'Y-m-d H:i' )
					),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			WPCPM_Ceiling::release( $key, DAY_IN_SECONDS );

			return $post_id;
		}

		update_post_meta( $post_id, self::META_STUDENT, (string) $who['student_record'] );
		update_post_meta( $post_id, self::META_MENTOR, (int) $mentor->ID );
		update_post_meta( $post_id, self::META_MENTOR_RECORD, (string) $who['mentor_record'] );
		update_post_meta( $post_id, self::META_NOTE, wp_slash( $note ) );
		update_post_meta( $post_id, self::META_LINKS, $links );
		update_post_meta( $post_id, self::META_STATE, self::STATE_WAITING );

		$answer['question_id'] = (int) $post_id;
		$answer['sent_at']     = $now;
		$answer['emailed']     = self::email_mentor( $who, $question, $note, $links );

		return $answer;
	}

	/**
	 * A question as `send()` keeps it: sanitized as a textarea, trimmed, and cut to Need help?'s longest
	 * question (`WPCPM_Handbook_Assistant::MAX_LENGTH`) in characters. The AI Mentor's route gives its
	 * card the same, so the card's preview holds exactly what the mentor gets.
	 *
	 * @param string $question The question as typed.
	 * @return string
	 */
	public static function clean_question( $question ) {
		return trim( mb_substr( trim( sanitize_textarea_field( (string) $question ) ), 0, (int) WPCPM_Handbook_Assistant::MAX_LENGTH ) );
	}

	/**
	 * A student's questions for their card: every waiting one, and the answered ones marked in the last
	 * 30 days, newest first. Private rows only.
	 *
	 * @param string $student_record The student's Students record ID.
	 * @return WP_Post[]
	 */
	public static function for_student( $student_record ) {
		$student_record = trim( (string) $student_record );

		if ( '' === $student_record ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'numberposts'      => 200,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_STUDENT,
						'value' => $student_record,
					),
				),
			)
		);

		$since = time() - self::ANSWERED_DAYS * DAY_IN_SECONDS;
		$out   = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'private' !== $post->post_status ) {
				continue;
			}

			if ( self::STATE_ANSWERED === get_post_meta( $post->ID, self::META_STATE, true ) && (int) get_post_meta( $post->ID, self::META_ANSWERED_AT, true ) < $since ) {
				continue;
			}

			$out[] = $post;
		}

		return $out;
	}

	/**
	 * How many questions of a student wait: the tag "Question waiting" on the student's row shows while
	 * this is above zero.
	 *
	 * @param string $student_record The student's Students record ID.
	 * @return int
	 */
	public static function waiting_count( $student_record ) {
		$count = 0;

		foreach ( self::for_student( $student_record ) as $post ) {
			if ( self::STATE_WAITING === get_post_meta( $post->ID, self::META_STATE, true ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * The list of questions for the open student on the Mentor Report Card, newest first, each with its date,
	 * the question, the line the student added, the links, and a small Mark as answered while it waits.
	 *
	 * Only for an account `WPCPM_Mentor_Notes::user_can_access()` lets read the student. The markup keeps
	 * clear of the notes' classes `wpcpm-note` and `wpcpm-note__meta`, which the card's script counts and
	 * reads (Ruling 10).
	 *
	 * @param string $student_record The student's Students record ID.
	 * @param int    $mentor_id      The mentor whose page is drawn; an Administrator's press returns to it.
	 * @return string Escaped markup, or '' when the viewer may not read the student or nothing is listed.
	 */
	public static function render_for( $student_record, $mentor_id = 0 ) {
		if ( ! WPCPM_Mentor_Notes::user_can_access( $student_record ) ) {
			return '';
		}

		// A line left by Mark as answered shows on the reopened card, once.
		$flash = ( WPCPM_Mentor_Notes::focused_student() === (string) $student_record ) ? sanitize_key( (string) WPCPM_Flash::take( self::FLASH_CHANNEL ) ) : '';
		$posts = self::for_student( $student_record );

		if ( empty( $posts ) ) {
			return '';
		}

		$out = '<section class="wpcpm-questions-block">';

		$out .= '<h4 class="wpcpm-questions__title">' . esc_html__( 'Questions sent from Need help?', 'wpcredits-program-manager' ) . '</h4>';

		if ( 'answered' === $flash ) {
			$out .= '<p class="wpcpm-questions__message is-success" role="status">' . esc_html__( 'Marked as answered.', 'wpcredits-program-manager' ) . '</p>';
		}

		$out .= '<ol class="wpcpm-questions">';

		foreach ( $posts as $post ) {
			$out .= self::render_question( $post, (int) $mentor_id );
		}

		return $out . '</ol></section>';
	}

	/**
	 * One question of the list.
	 *
	 * @param WP_Post $post      The question.
	 * @param int     $mentor_id The mentor whose page is drawn.
	 * @return string Escaped markup.
	 */
	private static function render_question( WP_Post $post, $mentor_id ) {
		$answered = ( self::STATE_ANSWERED === get_post_meta( $post->ID, self::META_STATE, true ) );
		$note     = trim( (string) get_post_meta( $post->ID, self::META_NOTE, true ) );
		$links    = get_post_meta( $post->ID, self::META_LINKS, true );
		$links    = is_array( $links ) ? $links : array();

		$out = '<li class="wpcpm-question' . ( $answered ? ' wpcpm-question--answered' : '' ) . '">';

		$out .= sprintf(
			'<p class="wpcpm-question__date"><time datetime="%1$s">%2$s</time></p>',
			esc_attr( get_post_time( 'c', true, $post ) ),
			esc_html( get_post_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $post ) )
		);

		$out .= '<p class="wpcpm-question__text">' . esc_html( $post->post_content ) . '</p>';

		if ( '' !== $note ) {
			$out .= '<p class="wpcpm-question__added">' . esc_html( $note ) . '</p>';
		}

		if ( ! empty( $links ) ) {
			$out .= '<ul class="wpcpm-question__links">';

			foreach ( $links as $link ) {
				$out .= sprintf(
					'<li><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></li>',
					esc_url( (string) $link ),
					esc_html( (string) $link )
				);
			}

			$out .= '</ul>';
		}

		if ( $answered ) {
			$out .= '<p class="wpcpm-question__state">' . esc_html(
				sprintf(
					/* translators: %s: the date a question was marked as answered. */
					__( 'Answered %s', 'wpcredits-program-manager' ),
					date_i18n( get_option( 'date_format' ), (int) get_post_meta( $post->ID, self::META_ANSWERED_AT, true ) )
				)
			) . '</p>';
		} else {
			$out .= self::answered_form( $post->ID, $mentor_id );
		}

		return $out . '</li>';
	}

	/**
	 * Mark as answered: one small secondary button in a form that posts once.
	 *
	 * @param int $question_id The question.
	 * @param int $mentor_id   The mentor whose page is drawn; sent for an Administrator, to return to it.
	 * @return string Escaped markup.
	 */
	private static function answered_form( $question_id, $mentor_id ) {
		$question_id = (int) $question_id;
		$mentor      = ( $mentor_id && current_user_can( WPCPM_Roles::CAP_MANAGE ) ) ? '<input type="hidden" name="mentor" value="' . (int) $mentor_id . '" />' : '';

		return sprintf(
			'<form class="wpcpm-question__answer" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s"><input type="hidden" name="_wpnonce" value="%3$s" />%4$s<input type="hidden" name="action" value="%5$s" /><input type="hidden" name="question" value="%6$d" />%7$s<button type="submit" class="wpcpm-button wpcpm-button--secondary">%8$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' ),
			esc_attr( wp_create_nonce( self::ACTION_ANSWERED . '_' . $question_id ) ),
			wp_referer_field( false ),
			esc_attr( self::ACTION_ANSWERED ),
			$question_id,
			$mentor,
			esc_html__( 'Mark as answered', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Mark as answered: the state, when and by whom, and back to the student's card, reopened.
	 *
	 * Allowed to anybody `WPCPM_Mentor_Notes::user_can_access()` lets read the question's student (their
	 * mentor, and Administrators); everybody else, the student included, stops on a 403. A question already
	 * answered is left as it is, and the redirect still happens, so a second press does not rewrite who
	 * answered it.
	 */
	public static function handle_answered() {
		$question_id = WPCPM_Request::posted_id( 'question' );

		// The press has to be signed in (`admin_post_` has no `nopriv` twin), and the nonce is the
		// question's own, so it is read from the post before anything is changed.
		check_admin_referer( self::ACTION_ANSWERED . '_' . $question_id );

		$post = $question_id ? get_post( $question_id ) : null;

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'You cannot mark that question as answered.', 'wpcredits-program-manager' ), 403 );
		}

		$student = (string) get_post_meta( $post->ID, self::META_STUDENT, true );

		if ( '' === $student || ! WPCPM_Mentor_Notes::user_can_access( $student ) ) {
			wp_die( esc_html__( 'You cannot mark that question as answered.', 'wpcredits-program-manager' ), 403 );
		}

		if ( self::STATE_ANSWERED !== get_post_meta( $post->ID, self::META_STATE, true ) ) {
			update_post_meta( $post->ID, self::META_STATE, self::STATE_ANSWERED );
			update_post_meta( $post->ID, self::META_ANSWERED_AT, time() );
			update_post_meta( $post->ID, self::META_ANSWERED_BY, get_current_user_id() );
		}

		WPCPM_Flash::set( self::FLASH_CHANNEL, 'answered' );

		$page = WPCPM_Mentors_Dashboard::page_url();
		$args = array( 'wpcpm_student' => $student );

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$mentor = WPCPM_Request::posted_id( 'mentor' );

			if ( $mentor ) {
				$args['wpcpm_mentor'] = $mentor;
			}
		}

		// The way back is rebuilt here, never taken from the request.
		wp_safe_redirect( add_query_arg( $args, '' === $page ? home_url( '/' ) : $page ) . '#' . WPCPM_Mentor_Notes::anchor( $student ) );
		exit;
	}

	/**
	 * The links a question may carry: at most five, in order, each cleaned and kept only when it is a
	 * page of this site or of the WordPress Credits handbook. Any other is dropped without a word.
	 *
	 * @param array $links Addresses as given.
	 * @return string[]
	 */
	private static function allowed_links( array $links ) {
		$roots = array(
			home_url( '/' ),
			'https://make.wordpress.org/community/handbook/education/credits/',
		);
		$out   = array();

		foreach ( $links as $link ) {
			if ( ! is_string( $link ) ) {
				continue;
			}

			$url = esc_url_raw( trim( $link ) );

			if ( '' === $url ) {
				continue;
			}

			foreach ( $roots as $root ) {
				if ( 0 === strpos( $url, $root ) ) {
					$out[] = $url;
					break;
				}
			}

			if ( count( $out ) >= self::LINKS_MAX ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * A question the student sent in the last 24 hours with the same words, if there is one.
	 *
	 * Judged on the post's own date against the real clock, as the Need help? notes' repeat is.
	 *
	 * @param int    $student_user_id The student's account.
	 * @param string $question        The question, as it would be saved.
	 * @return WP_Post|null
	 */
	private static function repeat_of( $student_user_id, $question ) {
		$since = time() - DAY_IN_SECONDS;
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'author'           => (int) $student_user_id,
				'numberposts'      => 50,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'date_query'       => array(
					array(
						'column'    => 'post_date_gmt',
						'after'     => gmdate( 'Y-m-d H:i:s', $since ),
						'inclusive' => true,
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post || (int) $post->post_author !== (int) $student_user_id || (int) get_post_time( 'U', true, $post ) < $since ) {
				continue;
			}

			if ( WPCPM_Typed_Text::same_lines( $question, (string) $post->post_content ) ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Email the question to the mentor, who answers by replying to the student.
	 *
	 * @param array    $who      What `mentor_of()` answered.
	 * @param string   $question The question.
	 * @param string   $note     The student's added line, possibly empty.
	 * @param string[] $links    The links kept.
	 * @return bool Whether the mail layer handed the message off.
	 */
	private static function email_mentor( array $who, $question, $note, array $links ) {
		$student = $who['student'];
		$card    = (string) WPCPM_Mentors_Dashboard::page_url();
		$record  = (string) $who['student_record'];
		$url     = '' !== $card ? add_query_arg( 'wpcpm_student', $record, $card ) . '#' . WPCPM_Mentor_Notes::anchor( $record ) : home_url( '/' );

		return (bool) WPCPM_Mail::send(
			$who['mentor'],
			'student-question-mentor',
			static function ( $mentor ) use ( $student, $question, $note, $links, $url ) {
				return WPCPM_Mail_Templates::mail(
					'student-question-mentor',
					array(
						'student_first_name' => self::first_name( $student ),
						'mentor_first_name'  => self::first_name( $mentor ),
						'question'           => $question,
						'student_note'       => $note,
						'links'              => implode( "\n", $links ),
						'student_card_url'   => $url,
					),
					array( 'headers' => WPCPM_Mail::reply_to( $student ) )
				);
			}
		);
	}

	/**
	 * An account's first name, or the name shown for it when it has none.
	 *
	 * @param WP_User $user The account.
	 * @return string
	 */
	private static function first_name( $user ) {
		$first = trim( (string) $user->first_name );

		return '' !== $first ? $first : trim( (string) $user->display_name );
	}
}
