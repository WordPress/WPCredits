<?php
/**
 * Tools - the Track Builder's question editor: its handlers.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a press on the question editor does: add, save, move, remove a question, or delete a track.
 *
 * Not a Tool, because a Tool is a menu entry and this is the Track Builder's own second screen.
 * Every handler runs the capability check first and the nonce check second (the design's
 * decision 3.9), then hands the decision to `WPCPM_Track_Questions` and the result to the store:
 * this class reads the request and writes the answer, and decides nothing about a question itself.
 * Every save goes through `WPCPM_Track_Store::check()`, the same call Publish makes, so the
 * editor and the publish screen can never disagree about a definition. Every save but
 * `handle_move()`'s: a reorder changes nothing `check()` judges, so it saves the new order
 * straight away (the whole-branch review).
 */
final class WPCPM_Track_Editor {

	/** Add a question to a group. */
	const ACTION_ADD = 'wpcpm_question_add';

	/** Save one question's properties. */
	const ACTION_SAVE = 'wpcpm_question_save';

	/** Move a question one place within its group. */
	const ACTION_MOVE = 'wpcpm_question_move';

	/** Take a question out of the track. */
	const ACTION_REMOVE = 'wpcpm_question_remove';

	/** Delete a track that was never published. */
	const ACTION_DELETE = 'wpcpm_track_delete';

	/** The field a move carries when the page asked in the background. */
	const FIELD_ASYNC = 'wpcpm_async';

	/**
	 * The Track Builder, for the screen's URL.
	 *
	 * @var WPCPM_Track_Builder
	 */
	private $builder;

	/**
	 * The editor belongs to the Track Builder's screen.
	 *
	 * @param WPCPM_Track_Builder $builder The tool whose screen this editor is part of.
	 */
	public function __construct( WPCPM_Track_Builder $builder ) {
		$this->builder = $builder;
	}

	/**
	 * Hook the five handlers.
	 */
	public function boot() {
		add_action( 'admin_post_' . self::ACTION_ADD, array( $this, 'handle_add' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_MOVE, array( $this, 'handle_move' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE, array( $this, 'handle_remove' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( $this, 'handle_delete' ) );
	}

	/**
	 * Add a question at the end of a group, then open it.
	 *
	 * The column name, the words a student reads, the control and the group: the four things a
	 * question cannot do without (4.2 makes `label` required, and never a copy of the column
	 * name), so the new question is valid the moment it exists and everything else is edited on
	 * its own screen.
	 */
	public function handle_add() {
		$this->verify( self::ACTION_ADD );

		$post_id = WPCPM_Request::posted_id( 'track' );
		$stored  = $this->stored( $post_id );
		$column  = WPCPM_Request::posted_exact( 'wpcpm_column' );
		$type    = WPCPM_Request::posted_key( 'wpcpm_type' );
		$group   = WPCPM_Request::posted_key( 'wpcpm_group' );
		$lesson  = WPCPM_Request::posted_id( 'wpcpm_lesson' );
		$typed   = array(
			'column' => $column,
			'label'  => WPCPM_Request::posted_text( 'wpcpm_label' ),
			'type'   => $type,
			'group'  => $group,
			'lesson' => $lesson,
		);

		$question = array(
			'type'  => $type,
			'label' => $typed['label'],
			'group' => $group,
		);

		$airtable_type = self::airtable_type( $type );

		if ( '' !== $airtable_type ) {
			$question['airtable_type'] = $airtable_type;
		}

		$after = '';

		// Under a lesson (decision 32): the question carries it, takes the lesson's title as its
		// heading when it is the lesson's first question, since the heading belongs to the first
		// question after it, and lands after the lesson's last.
		if ( $lesson > 0 ) {
			$question['learn_lesson_id'] = $lesson;
			$after                       = WPCPM_Track_Questions::last_of_lesson( self::questions( $stored ), $lesson );

			if ( '' === $after ) {
				$title = self::lesson_title( $stored, $lesson );

				if ( '' !== $title ) {
					$question['lead'] = $title;
				}
			}
		}

		$questions = WPCPM_Track_Questions::add( self::questions( $stored ), $column, $question, $after );

		if ( null === $questions ) {
			$this->refuse( $post_id, __( 'This track already has a question on that column. One column, one question.', 'wpcredits-program-manager' ), $typed );
		}

		$definition              = $stored;
		$definition['questions'] = $questions;

		$this->store( $post_id, $definition, $typed );

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => __( 'The question was added. Fill in what it asks, then save it.', 'wpcredits-program-manager' ),
			),
			array(
				'wpcpm_track'    => $post_id,
				'wpcpm_question' => $column,
			)
		);
	}

	/**
	 * Save one question: its properties, and its column when that may change.
	 *
	 * Three rules meet here, all decided by `WPCPM_Track_Questions` and only applied in this
	 * order. A published question keeps its column (decision 23): a different name is refused,
	 * and so is a change that would fork, with the way round named. A shared question whose
	 * control or options changed takes a column of its own (the design's 5). And a renamed
	 * column is a rename, the question keeping its place.
	 */
	public function handle_save() {
		$this->verify( self::ACTION_SAVE );

		$post_id   = WPCPM_Request::posted_id( 'track' );
		$stored    = $this->stored( $post_id );
		$current   = WPCPM_Request::posted_exact( 'wpcpm_question' );
		$column    = WPCPM_Request::posted_exact( 'wpcpm_column' );
		$questions = self::questions( $stored );

		if ( ! array_key_exists( $current, $questions ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => __( 'That question is no longer on this track.', 'wpcredits-program-manager' ),
				),
				array( 'wpcpm_track' => $post_id )
			);
		}

		$was       = (array) $questions[ $current ];
		$question  = self::posted_question( $was );
		$typed     = array_merge( $question, array( 'column' => $column ) );
		$published = WPCPM_Track_Store::published( $post_id );
		$locked    = WPCPM_Track_Questions::locked( $current, is_array( $published ) && isset( $published['questions'] ) ? (array) $published['questions'] : array() );
		$others    = WPCPM_Track_Store::others( $post_id );
		$forks     = WPCPM_Track_Questions::forks( $question, $was );

		if ( $locked && $column !== $current ) {
			$this->refuse( $post_id, __( 'This question has been published, so its column is fixed: the column holds what students have already written. To move the question to another column, remove it and add a new one.', 'wpcredits-program-manager' ), $typed, $current );
		}

		if ( $locked && $forks ) {
			$this->refuse( $post_id, __( 'This question has been published, so its control and its choices are fixed: the column in Airtable has that shape. To ask it differently, remove it and add a new question with a column of its own.', 'wpcredits-program-manager' ), $typed, $current );
		}

		$target = $column;

		// A shared column forks the moment the control or the choices change, before anything is
		// validated: the fork is what protects the other tracks, and the person sees it on the
		// screen that comes back. A renamed column is the person's own choice of name and is
		// left as typed.
		if ( $column === $current && $forks && array() !== WPCPM_Track_Questions::owners( $current, $others ) ) {
			$target = WPCPM_Track_Questions::fork_name( $current, isset( $stored['key'] ) ? (string) $stored['key'] : '' );
		}

		$questions[ $current ] = $question;

		if ( $target !== $current ) {
			$questions = WPCPM_Track_Questions::rename( $questions, $current, $target );

			if ( null === $questions ) {
				$this->refuse( $post_id, __( 'This track already has a question on that column. One column, one question.', 'wpcredits-program-manager' ), $typed, $current );
			}
		}

		$definition              = $stored;
		$definition['questions'] = $questions;

		$this->store( $post_id, $definition, $typed, $current );

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => $target === $current
					? __( 'The question was saved. Nothing reaches students until the track is published.', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %s: the column the question now writes. */
						__( 'The question was saved and now has a column of its own, %s, so the tracks that share the old column are not changed. Nothing reaches students until the track is published.', 'wpcredits-program-manager' ),
						$target
					),
			),
			array( 'wpcpm_track' => $post_id )
		);
	}

	/**
	 * Move a question one place up or down within its group.
	 *
	 * The page moves the row the moment the arrow is pressed and posts this in the background
	 * (`assets/js/track-editor.js`); the answer carries the order the store kept, and a refusal
	 * puts the row back. Without the script the form posts the ordinary way and comes back.
	 */
	public function handle_move() {
		$this->verify( self::ACTION_MOVE );

		$post_id   = WPCPM_Request::posted_id( 'track' );
		$stored    = $this->stored( $post_id );
		$column    = WPCPM_Request::posted_exact( 'wpcpm_question' );
		$direction = WPCPM_Request::posted_key( 'wpcpm_direction' );
		$questions = self::questions( $stored );
		$shifted   = false;

		if ( array_key_exists( $column, $questions ) && in_array( $direction, array( 'up', 'down' ), true ) ) {
			$moved = WPCPM_Track_Questions::move( $questions, $column, $direction );

			if ( $moved !== $questions ) {
				$definition              = $stored;
				$definition['questions'] = $moved;
				$saved                   = WPCPM_Track_Store::save( $post_id, $definition );

				if ( is_wp_error( $saved ) ) {
					$this->refuse( $post_id, $saved->get_error_message(), array() );
				}

				$questions = $moved;
				$shifted   = true;
			}
		}

		if ( '1' === WPCPM_Request::posted_key( self::FIELD_ASYNC ) ) {
			wp_send_json_success( array( 'order' => array_map( 'strval', array_keys( $questions ) ) ) );
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				// At the edge of a group `move()` gives the order back unchanged. The script puts
				// the row where it was and says nothing, but without it this line is all a person
				// reads, and "was moved" of a row that did not move is a lie (the whole-branch
				// review).
				'message' => $shifted
					? __( 'The question was moved.', 'wpcredits-program-manager' )
					: __( 'That question is already at the edge of its group, so nothing moved.', 'wpcredits-program-manager' ),
			),
			array( 'wpcpm_track' => $post_id )
		);
	}

	/**
	 * Take a question out of the track.
	 *
	 * The column and what students wrote in it stay in Airtable, because nothing is deleted there
	 * (decision 9); the screen's confirmation says so before this runs.
	 */
	public function handle_remove() {
		$this->verify( self::ACTION_REMOVE );

		$post_id   = WPCPM_Request::posted_id( 'track' );
		$stored    = $this->stored( $post_id );
		$column    = WPCPM_Request::posted_exact( 'wpcpm_question' );
		$questions = self::questions( $stored );

		if ( ! array_key_exists( $column, $questions ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => __( 'That question is no longer on this track.', 'wpcredits-program-manager' ),
				),
				array( 'wpcpm_track' => $post_id )
			);
		}

		$definition              = $stored;
		$definition['questions'] = WPCPM_Track_Questions::remove( $questions, $column );

		$this->store( $post_id, $definition, array() );

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => __( 'The question was removed from the track. Its column, and whatever students wrote in it, stay in Airtable.', 'wpcredits-program-manager' ),
			),
			array( 'wpcpm_track' => $post_id )
		);
	}

	/**
	 * Delete a track that was never published (decision 25). The store refuses every other one.
	 */
	public function handle_delete() {
		$this->verify( self::ACTION_DELETE );

		$post_id = WPCPM_Request::posted_id( 'track' );
		$stored  = WPCPM_Track_Store::get( $post_id );
		$label   = is_array( $stored ) && isset( $stored['label'] ) ? (string) $stored['label'] : '';
		$deleted = WPCPM_Track_Store::delete( $post_id );

		if ( is_wp_error( $deleted ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $deleted->get_error_message(),
				)
			);
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => sprintf(
					/* translators: %s: the track's name. */
					__( '%s was deleted. It was never published, so nothing in Airtable or on the live site referred to it.', 'wpcredits-program-manager' ),
					$label
				),
			)
		);
	}

	/**
	 * A question's properties as posted, on top of the ones the form does not show.
	 *
	 * Every property 4.2 lists, read for the control that owns it and left out otherwise, so
	 * `validate()` sees exactly what a person set and nothing a previous control left behind.
	 * `learn_lesson_id` is a box since T3c (decision 32) and read like any other property; `why`
	 * too, since the screen draws it, so it is read from the post like any other text and dropped
	 * when it comes back empty (the whole-branch review).
	 *
	 * @param array $was The question as it is stored.
	 * @return array
	 */
	public static function posted_question( array $was ) {
		$type     = WPCPM_Request::posted_key( 'wpcpm_type' );
		$question = array(
			'type'  => $type,
			'label' => WPCPM_Request::posted_text( 'wpcpm_label' ),
			'group' => WPCPM_Request::posted_key( 'wpcpm_group' ),
		);

		foreach ( array( 'help', 'lead', 'subgroup', 'note', 'why' ) as $property ) {
			$value = WPCPM_Request::posted_text( 'wpcpm_' . $property );

			if ( '' !== $value ) {
				$question[ $property ] = $value;
			}
		}

		$row = WPCPM_Request::posted_key( 'wpcpm_row' );

		if ( '' !== $row ) {
			$question['row'] = $row;
		}

		foreach ( array( 'stack', 'required', 'hide_from_institution' ) as $flag ) {
			if ( '1' === WPCPM_Request::posted_key( 'wpcpm_' . $flag ) ) {
				$question[ $flag ] = true;
			}
		}

		if ( 'number' === $type ) {
			foreach ( array( 'min', 'max', 'step' ) as $bound ) {
				$value = WPCPM_Request::posted_text( 'wpcpm_' . $bound );

				if ( '' !== $value && is_numeric( $value ) ) {
					$question[ $bound ] = false === strpos( $value, '.' ) ? (int) $value : (float) $value;
				} elseif ( '' !== $value ) {
					$question[ $bound ] = $value;
				}
			}
		}

		if ( 'text' === $type ) {
			$maxlength = WPCPM_Request::posted_text( 'wpcpm_maxlength' );

			if ( '' !== $maxlength ) {
				$question['maxlength'] = ctype_digit( $maxlength ) ? (int) $maxlength : $maxlength;
			}
		}

		if ( 'textarea' === $type && '1' === WPCPM_Request::posted_key( 'wpcpm_mono' ) ) {
			$question['mono'] = true;
		}

		if ( 'select' === $type ) {
			$lines               = WPCPM_Request::posted_verbatim_lines( 'wpcpm_options' );
			$question['options'] = '' === $lines ? array() : explode( "\n", $lines );
		}

		// The Airtable type follows the control for a column being created, and is whatever the
		// base says for one that already exists (the design's 5): so it moves with the control and
		// stays put otherwise, and a column that exists with another type is the preflight's to
		// refuse.
		if ( isset( $was['type'] ) && (string) $was['type'] === $type && isset( $was['airtable_type'] ) ) {
			$question['airtable_type'] = (string) $was['airtable_type'];
		} else {
			$airtable_type = self::airtable_type( $type );

			if ( '' !== $airtable_type ) {
				$question['airtable_type'] = $airtable_type;
			}
		}

		// The lesson the screen's box holds: chosen from the course's lessons, the stored id when
		// Learn could not be read, or carried hidden when the track has no course; None is none.
		$lesson = WPCPM_Request::posted_id( 'wpcpm_learn_lesson_id' );

		if ( $lesson > 0 ) {
			$question['learn_lesson_id'] = $lesson;
		}

		return $question;
	}

	/**
	 * The Airtable type a control's new column gets.
	 *
	 * @param string $type The control.
	 * @return string Empty for a control that never creates a column.
	 */
	private static function airtable_type( $type ) {
		if ( 'team' === $type ) {
			return WPCPM_Track_Columns::LINK;
		}

		return isset( WPCPM_Track_Columns::TYPES[ $type ] ) ? WPCPM_Track_Columns::TYPES[ $type ] : '';
	}

	/**
	 * A definition's questions, as a map.
	 *
	 * @param array $definition The definition.
	 * @return array
	 */
	private static function questions( array $definition ) {
		return isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? $definition['questions'] : array();
	}

	/**
	 * A lesson's title on the track's course, from what Learn keeps; '' when it cannot be read.
	 *
	 * @param array $definition The track's definition.
	 * @param int   $lesson_id  The lesson.
	 * @return string
	 */
	private static function lesson_title( array $definition, $lesson_id ) {
		$course_id = isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0;

		if ( $course_id <= 0 ) {
			return '';
		}

		$modules = WPCPM_Learn::structure( $course_id );

		return is_wp_error( $modules ) ? '' : WPCPM_Learn::lesson_title( $modules, $lesson_id );
	}

	/**
	 * The definition a handler works on, or the list with a refusal.
	 *
	 * @param int $post_id The track.
	 * @return array
	 */
	private function stored( $post_id ) {
		$stored = WPCPM_Track_Store::get( $post_id );

		if ( ! is_array( $stored ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => __( 'That track does not exist.', 'wpcredits-program-manager' ),
				)
			);
		}

		return $stored;
	}

	/**
	 * Check the whole definition and save it, or come back with the first refusal.
	 *
	 * @param int    $post_id    The track.
	 * @param array  $definition The definition with the change applied.
	 * @param array  $typed      What was posted, so nothing has to be retyped.
	 * @param string $question   The question screen to come back to, or empty for the add form.
	 */
	private function store( $post_id, array $definition, array $typed, $question = '' ) {
		$errors = WPCPM_Track_Store::check( $post_id, $definition );

		if ( array() !== $errors ) {
			// The whole definition is checked, so the first refusal may belong to another question
			// entirely; naming its column is the difference between a message a person can act on
			// and one that looks like a refusal of the question in front of them (the whole-branch
			// review). `where` is the key `WPCPM_Track_Definition::validate()` writes, and the only
			// one a refusal carries: this read `column` as well until the final review of T3b found
			// that nothing writes it, the publish preflight reads `where` too, and the fallback was
			// dead.
			$message = (string) $errors[0]['message'];
			$column  = isset( $errors[0]['where'] ) ? (string) $errors[0]['where'] : '';

			if ( '' !== $column ) {
				$message = sprintf(
					/* translators: 1: a column name, 2: why the definition was refused. */
					__( '%1$s: %2$s', 'wpcredits-program-manager' ),
					$column,
					$message
				);
			}

			$this->refuse( $post_id, $message, $typed, $question );
		}

		$saved = WPCPM_Track_Store::save( $post_id, $definition );

		if ( is_wp_error( $saved ) ) {
			$this->refuse( $post_id, $saved->get_error_message(), $typed, $question );
		}
	}

	/**
	 * Back to the screen the press came from, with the refusal and what was typed.
	 *
	 * Under `question_values`, never `values`: a refused Add lands on the track's own screen,
	 * where `values` is what the track's properties are drawn from, and a question's `label`
	 * would arrive in the track's Name box and be saved as the track's name by the next press
	 * (the whole-branch review).
	 *
	 * @param int    $post_id  The track.
	 * @param string $message  Why it was refused.
	 * @param array  $typed    What was posted.
	 * @param string $question The question being edited, or empty when a question was being added.
	 */
	private function refuse( $post_id, $message, array $typed, $question = '' ) {
		$args = array( 'wpcpm_track' => $post_id );

		if ( '' !== $question ) {
			$args['wpcpm_question'] = $question;
		}

		$this->redirect_back(
			array(
				'status'          => 'error',
				'message'         => $message,
				'question_values' => $typed,
			),
			$args
		);
	}

	/**
	 * The capability, then the nonce, before a handler does anything (decision 3.9).
	 *
	 * The Track Builder has the same two lines. They are not shared through a base class because
	 * the tool's are private to it and this editor is not a tool: making it one would give it a
	 * menu entry of its own.
	 *
	 * @param string $action The action being verified.
	 */
	private function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Back to the Track Builder, with what happened flashed for the person who pressed.
	 *
	 * @param array $outcome `status`, `message`, and `question_values` when a form has to come back.
	 * @param array $args    Query arguments naming the screen to come back to; none for the list.
	 */
	private function redirect_back( array $outcome, array $args = array() ) {
		$encoded = array();

		foreach ( $args as $key => $value ) {
			$encoded[ $key ] = rawurlencode( (string) $value );
		}

		// Core's add_query_arg() inserts a value exactly as it is handed and leaves encoding to
		// the caller, so a column name holding `&`, `+`, `%` or `#` would come back as a
		// different string (the Task 5 review); one call, not one per argument, so nothing
		// already in the query is touched twice. `add_query_arg( array(), $url )` is the URL
		// unchanged, so the no-argument case still works.
		$url = add_query_arg( $encoded, $this->builder->admin_url() );

		WPCPM_Flash::set( WPCPM_Track_Builder::FLASH, $outcome );
		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $url ) : $url );
		exit;
	}
}
