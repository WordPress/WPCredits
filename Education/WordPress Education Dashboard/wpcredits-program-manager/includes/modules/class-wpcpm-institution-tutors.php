<?php
/**
 * The tutors a school lists, kept on the site.
 *
 * Each school keeps its own list of tutors, a name and an address each, in its record
 * (`WPCPM_Institution_Record`). The student import's tutor picker offers these names and reads nothing
 * from Airtable: it used to read the base's Tutors table, one request for the whole program and a cache
 * of half a day. The list belongs to the school, so a representative keeps it themselves and an
 * Administrator may too.
 *
 * - **A tutor on the list is not an account.** Removing someone from the list leaves any account they
 *   hold, which is removed from the members of the People card like any member's. The list says whom a
 *   student's tutor can be; the account says who may see the students.
 * - **An address is listed once.** Addresses are compared trimmed and lowercase, so the same person in
 *   another case is not added twice. Two tutors may share a name, and the picker offers the name once.
 * - **A Tutor invitation that is accepted lists the account's address**, under its display name, when it
 *   is not listed already (`accepted()`); a Representative invitation lists nobody.
 * - **Each change is one row of history**, with the name and the address in its facts. A refusal, a
 *   repeat and a removal of nobody write nothing.
 * - **Who may change the list is the policy's**, asked for managing members: the two handlers ask it
 *   before they touch anything. This class decides nothing about who may.
 * - **Writes run under the school's lock**, the one the details and the requests use, because two
 *   writers at once would each overwrite the other's list.
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The school's tutor list, its two handlers and its section of the People card.
 */
class WPCPM_Institution_Tutors {

	/**
	 * The key in the institution's record: a list of `array( 'name' => string, 'email' => string )`.
	 */
	const RECORD_KEY = 'tutors';

	/**
	 * The admin-post action that adds a tutor, and its nonce's name before the record.
	 */
	const ACTION_ADD = 'wpcpm_institution_tutor_add';

	/**
	 * The admin-post action that removes a tutor, and its nonce's name before the record.
	 */
	const ACTION_REMOVE = 'wpcpm_institution_tutor_remove';

	/**
	 * The audit row's kind for a tutor added.
	 */
	const LOG_ADDED = 'tutor_added';

	/**
	 * The audit row's kind for a tutor removed.
	 */
	const LOG_REMOVED = 'tutor_removed';

	/**
	 * The channel the outcome of a press is flashed on.
	 */
	const FLASH = 'institution_tutors';

	/**
	 * The id the handlers land on, in the People card.
	 */
	const ANCHOR = 'wpcpm-tutors';

	/** Add a tutor's fold, where Add's refusal is drawn and its way back lands (Gate B round 2, F3). */
	const ADD_ID = 'wpcpm-tutors-add';

	/**
	 * The longest name kept, in characters.
	 */
	const NAME_MAX = 100;

	/**
	 * What a refused Add keeps of its form, one entry per school, as `WPCPM_Flash::keep()` names a form:
	 * `tutor-add:<institution record>` (Stage B, Task 20).
	 */
	const KEPT_ADD = 'tutor-add:';

	/**
	 * The tutors a school lists, by name.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return array[] Each `array( 'name' => string, 'email' => string )`, by name A to Z and then by
	 *                 address; an empty list for none, and for an ID that is not a record ID.
	 */
	public static function list_for( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$answers = WPCPM_Institution_Record::get( $record_id );
		$held    = isset( $answers[ self::RECORD_KEY ] ) && is_array( $answers[ self::RECORD_KEY ] ) ? $answers[ self::RECORD_KEY ] : array();
		$out     = array();

		foreach ( $held as $tutor ) {
			if ( ! is_array( $tutor ) || ! isset( $tutor['name'], $tutor['email'] ) || ! is_scalar( $tutor['name'] ) || ! is_scalar( $tutor['email'] ) ) {
				continue;
			}

			$name  = trim( (string) $tutor['name'] );
			$email = self::key( $tutor['email'] );

			if ( '' !== $name && '' !== $email ) {
				$out[] = array(
					'name'  => $name,
					'email' => $email,
				);
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$by = strnatcasecmp( $a['name'], $b['name'] );

				return 0 !== $by ? $by : strcmp( $a['email'], $b['email'] );
			}
		);

		return $out;
	}

	/**
	 * The addresses ever taken off a school's list, as its history records them (1.123.84).
	 *
	 * For the one-off seed, which lists the Tutors table's rows: a tutor somebody removed on the site is not
	 * brought back by a second run.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return string[] Addresses, trimmed and lowercase, each once; an empty list for an ID that is not a record ID.
	 */
	public static function removed( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$out = array();

		foreach ( WPCPM_Institution_Audit::entries_for( trim( (string) $record_id ), 0 ) as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['kind'] ) || self::LOG_REMOVED !== $entry['kind'] ) {
				continue;
			}

			$email = isset( $entry['data']['email'] ) && is_scalar( $entry['data']['email'] ) ? self::key( $entry['data']['email'] ) : '';

			if ( '' !== $email && ! in_array( $email, $out, true ) ) {
				$out[] = $email;
			}
		}

		return $out;
	}

	/**
	 * The names the import's tutor picker offers.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return string[] The names, sorted, each once.
	 */
	public static function names( $record_id ) {
		$names = array();

		foreach ( self::list_for( $record_id ) as $tutor ) {
			if ( ! in_array( $tutor['name'], $names, true ) ) {
				$names[] = $tutor['name'];
			}
		}

		return $names;
	}

	/**
	 * List a tutor, and say so in the school's history.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param string $name      The tutor's name, up to 100 characters.
	 * @param string $email     The tutor's address.
	 * @param int    $actor     The user who made the change.
	 * @param string $ground    The ground the policy allowed it on, for the history.
	 * @return bool|WP_Error True when listed; false when the address is already listed (compared trimmed and
	 *                       lowercase); an error for a name that is empty or too long (`wpcpm_tutor_name`),
	 *                       an address that is not one (`wpcpm_tutor_email`), a school whose lock another
	 *                       request holds (`wpcpm_tutor_busy`) and a record the site could not keep
	 *                       (`wpcpm_tutor_record`).
	 */
	public static function add( $record_id, $name, $email, $actor, $ground ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return new WP_Error( 'wpcpm_tutor_record', __( 'That institution is not known to the site.', 'wpcredits-program-manager' ) );
		}

		$name = trim( sanitize_text_field( is_scalar( $name ) ? (string) $name : '' ) );

		if ( '' === $name || mb_strlen( $name ) > self::NAME_MAX ) {
			return new WP_Error( 'wpcpm_tutor_name', __( 'Give the tutor\'s name, up to 100 characters.', 'wpcredits-program-manager' ) );
		}

		$address = self::key( is_scalar( $email ) ? sanitize_email( trim( (string) $email ) ) : '' );

		if ( '' === $address || ! is_email( $address ) ) {
			return new WP_Error( 'wpcpm_tutor_email', __( 'Give an email address for the tutor.', 'wpcredits-program-manager' ) );
		}

		$lock  = WPCPM_Institution_Request::school_lock_name( $record_id );
		$token = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

		if ( '' === $token ) {
			return new WP_Error( 'wpcpm_tutor_busy', __( 'The list is being changed by somebody else. Try again in a moment.', 'wpcredits-program-manager' ) );
		}

		try {
			// The record is read under the lock, not from this request's copy from before it.
			WPCPM_Institution_Record::forget_cached( $record_id );

			$list = self::list_for( $record_id );

			foreach ( $list as $tutor ) {
				if ( $tutor['email'] === $address ) {
					return false;
				}
			}

			$list[] = array(
				'name'  => $name,
				'email' => $address,
			);

			if ( ! WPCPM_Institution_Record::set( $record_id, array( self::RECORD_KEY => $list ) ) ) {
				return new WP_Error( 'wpcpm_tutor_record', __( 'The tutor could not be kept on the site, so nothing was saved. Try again in a while.', 'wpcredits-program-manager' ) );
			}

			WPCPM_Institution_Audit::record(
				array(
					'kind'        => self::LOG_ADDED,
					'institution' => $record_id,
					'subject'     => $record_id,
					'actor'       => (int) $actor,
					'ground'      => $ground,
					'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'     => sprintf(
						/* translators: %s: the tutor's name. */
						__( '%s was added to the tutors.', 'wpcredits-program-manager' ),
						$name
					),
					'data'        => array(
						'name'  => $name,
						'email' => $address,
					),
				)
			);
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		return true;
	}

	/**
	 * Take a tutor off the list, and say so in the school's history. An account they hold is left as it is.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param string $email     The tutor's address, as listed (compared trimmed and lowercase).
	 * @param int    $actor     The user who made the change.
	 * @param string $ground    The ground the policy allowed it on, for the history.
	 * @return bool True when the tutor was listed and is not now; false when nobody was listed under that
	 *              address, when the record could not be kept, and when another request holds the school's lock.
	 */
	public static function remove( $record_id, $email, $actor, $ground ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return false;
		}

		$address = self::key( is_scalar( $email ) ? $email : '' );

		if ( '' === $address ) {
			return false;
		}

		$lock  = WPCPM_Institution_Request::school_lock_name( $record_id );
		$token = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

		if ( '' === $token ) {
			return false;
		}

		try {
			WPCPM_Institution_Record::forget_cached( $record_id );

			$kept    = array();
			$removed = null;

			foreach ( self::list_for( $record_id ) as $tutor ) {
				if ( $tutor['email'] === $address ) {
					$removed = $tutor;

					continue;
				}

				$kept[] = $tutor;
			}

			if ( null === $removed ) {
				return false;
			}

			// An empty list is a blank answer, which the record drops: the key goes and the school has none.
			if ( ! WPCPM_Institution_Record::set( $record_id, array( self::RECORD_KEY => $kept ) ) ) {
				return false;
			}

			WPCPM_Institution_Audit::record(
				array(
					'kind'        => self::LOG_REMOVED,
					'institution' => $record_id,
					'subject'     => $record_id,
					'actor'       => (int) $actor,
					'ground'      => $ground,
					'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'     => sprintf(
						/* translators: %s: the tutor's name. */
						__( '%s was removed from the tutors.', 'wpcredits-program-manager' ),
						$removed['name']
					),
					'data'        => array(
						'name'  => $removed['name'],
						'email' => $address,
					),
				)
			);
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		return true;
	}

	/**
	 * List the account of a Tutor invitation that was just accepted, when it is not listed.
	 *
	 * The address is the account's own, under its display name (its login when it has none). The row of
	 * history is on the system ground, as the invitation's own acceptance is: the invitation allowed it, and
	 * the account is the actor. A school's lock another request holds at that moment leaves the tutor unlisted
	 * (`add()` is refused, and the acceptance goes on): a representative or an Administrator adds them by hand.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param int    $user_id   The account that accepted.
	 * @return bool Whether the account was listed by this call.
	 */
	public static function accepted( $record_id, $user_id ) {
		$user = (int) $user_id > 0 ? get_user_by( 'id', (int) $user_id ) : false;

		if ( ! $user instanceof WP_User || '' === trim( (string) $user->user_email ) ) {
			return false;
		}

		$name = '' !== trim( (string) $user->display_name ) ? (string) $user->display_name : (string) $user->user_login;

		return true === self::add( $record_id, $name, (string) $user->user_email, (int) $user->ID, WPCPM_Institution_Audit::GROUND_SYSTEM );
	}

	/**
	 * An address as it is compared and kept: trimmed and lowercase.
	 *
	 * @param mixed $email An address.
	 * @return string
	 */
	private static function key( $email ) {
		return strtolower( trim( (string) $email ) );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_ADD, array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE, array( __CLASS__, 'handle_remove' ) );
	}

	/**
	 * The Tutors section of the People card.
	 *
	 * Everybody who reads the card reads the list, each tutor with a name and an address, and nothing at all is
	 * drawn for somebody the policy does not let read it. Whoever may manage members (a representative, an
	 * Administrator) is also drawn a Remove on each tutor and the form that adds one; a tutor is drawn neither.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $context   The dashboard's context; not read, the policy decides.
	 */
	public static function render( $record_id, array $context = array() ) {
		unset( $context );

		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$subject = WPCPM_Institution_Policy::subject_institution( $record_id );
		$view    = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_VIEW_ROSTER, $subject );

		if ( empty( $view['allowed'] ) ) {
			return;
		}

		$manage     = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS, $subject );
		$can_change = ! empty( $manage['allowed'] );
		$tutors     = self::list_for( $record_id );

		// The Institution Dashboard on the shared parts (Stage B, Task 20): the same reader, the same list, read above.
		self::render_parts( $record_id, $tutors, $can_change );
	}

	/**
	 * The outcome of the last press, on the card of the institution it was about.
	 *
	 * @param string $record_id The institution being drawn.
	 * @param bool   $in_form   Whether Add a tutor is drawn, so Add's own refusals are said there.
	 */
	private static function render_message( $record_id, $in_form = false ) {
		$said = self::message_for( $record_id );

		if ( null === $said ) {
			return;
		}

		// On the shared parts the shell's outcome (Stage B, Task 20), under Tutors over the rows it is about; Add's own
		// refusals are said in Add a tutor, before its form (Gate B, B7), unless that form is not drawn for this reader.
		if ( self::refuses_add( $said['status'] ) && $in_form ) {
			return;
		}

		echo self::outcome_parts( $record_id, $said ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Dashboard_Card::message() escapes what it prints.
	}

	/**
	 * The list's outcome as the shell's message on the parts: a refusal of the name or the address is a link to the box
	 * it refused.
	 *
	 * @param string $record_id The institution's record ID.
	 * @param array  $said      What `message_for()` returned.
	 * @return string Escaped markup.
	 */
	private static function outcome_parts( $record_id, array $said ) {
		$field = self::refused_field( $said['status'] );

		return WPCPM_Dashboard_Card::message( $said['tone'], $said['text'], 'wpcpm-people__message', '' !== $field ? self::field_id( $record_id, $field ) : '' );
	}

	/*
	 * The rules both drawings ask (Stage B, Task 20: one helper per rule, the old branch and the parts' both calling it)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The list's outcome, taken from the flash once and kept for the request: its status, its tone and its sentence. Null
	 * when there is none for this institution (routing, not authorization: which card prints a sentence, never who may
	 * read one).
	 *
	 * @param string $record_id The institution being drawn.
	 * @return array{status: string, tone: string, text: string}|null
	 */
	private static function message_for( $record_id ) {
		$flash  = WPCPM_Flash::take( self::FLASH );
		$status = is_array( $flash ) && isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';
		$about  = is_array( $flash ) && isset( $flash['record'] ) ? trim( (string) $flash['record'] ) : '';

		// Routing, not authorization: which card prints a sentence, never who may read one.
		if ( $record_id !== $about ) {
			return null;
		}

		$messages = array(
			'added'   => array( 'success', __( 'The tutor was added to the list.', 'wpcredits-program-manager' ) ),
			'removed' => array( 'success', __( 'The tutor was removed from the list. Their account, if they have one, is kept.', 'wpcredits-program-manager' ) ),
			'already' => array( 'info', __( 'That address is already on the list.', 'wpcredits-program-manager' ) ),
			'gone'    => array( 'info', __( 'Nothing to remove: that tutor is not on the list.', 'wpcredits-program-manager' ) ),
			'name'    => array( 'error', __( 'Give the tutor\'s name, up to 100 characters.', 'wpcredits-program-manager' ) ),
			'email'   => array( 'error', __( 'Give an email address for the tutor.', 'wpcredits-program-manager' ) ),
			'busy'    => array( 'error', __( 'The list is being changed by somebody else. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'error'   => array( 'error', __( 'That could not be done.', 'wpcredits-program-manager' ) ),
		);

		if ( ! isset( $messages[ $status ] ) ) {
			return null;
		}

		return array(
			'status' => $status,
			'tone'   => $messages[ $status ][0],
			'text'   => $messages[ $status ][1],
		);
	}

	/**
	 * The words of an empty list.
	 *
	 * @return string
	 */
	private static function empty_words() {
		return __( 'No tutors are listed yet.', 'wpcredits-program-manager' );
	}

	/**
	 * The hidden fields a tutor's Remove posts after its nonce: the action, the institution and the address.
	 *
	 * @param string $record_id The institution's record ID.
	 * @param array  $tutor     The tutor as listed: `name`, `email`.
	 * @return array<string, string> Name to value, in the order posted.
	 */
	private static function remove_fields( $record_id, array $tutor ) {
		return array(
			'action' => self::ACTION_REMOVE,
			'record' => (string) $record_id,
			'email'  => (string) $tutor['email'],
		);
	}

	/**
	 * What the browser asks before a tutor's Remove.
	 *
	 * @param array $tutor The tutor as listed: `name`, `email`.
	 * @return string
	 */
	private static function remove_question( array $tutor ) {
		return sprintf(
			/* translators: %s: the tutor's name. */
			__( 'Remove %s from the tutors? Their account, if they have one, stays until you remove it above.', 'wpcredits-program-manager' ),
			$tutor['name']
		);
	}

	/**
	 * The hidden fields Add posts after its nonce: the action and the institution.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return array<string, string> Name to value, in the order posted.
	 */
	private static function add_fields( $record_id ) {
		return array(
			'action' => self::ACTION_ADD,
			'record' => (string) $record_id,
		);
	}

	/**
	 * The box an outcome refused, on the parts: the name or the address Add was refused for, none for every other outcome.
	 *
	 * @param string $status The outcome.
	 * @return string `name`, `email` or ''.
	 */
	private static function refused_field( $status ) {
		return in_array( $status, array( 'name', 'email' ), true ) ? $status : '';
	}

	/**
	 * Whether an outcome is Add's own refusal, so the form is drawn open on the parts: a name or an address refused, or a
	 * tutor the site could not keep. "Busy" is either form's, so it opens nothing.
	 *
	 * @param string $status The outcome.
	 * @return bool
	 */
	private static function refuses_add( $status ) {
		return in_array( $status, array( 'name', 'email', 'error' ), true );
	}

	/**
	 * The id of Add's box on the parts, today's id: the name's or the address's, by the institution.
	 *
	 * @param string $record_id The institution's record ID.
	 * @param string $field     `name` or `email`.
	 * @return string
	 */
	private static function field_id( $record_id, $field ) {
		return 'wpcpm-tutor-' . $field . '-' . $record_id;
	}

	/*
	 * The list on the shared parts (Stage B, Task 20)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Tutors section of the card on the parts: an in-card heading on today's anchor, its outcome under it, a row per
	 * tutor (the name, the address under it) with Remove (quiet red, today's question) for a reader
	 * who may change the list, or today's empty line; then Add a tutor, the parts' inner fold, closed, and open on a
	 * refusal of Add with the problem under the box it refused.
	 *
	 * @param string  $record_id  The institution's record ID.
	 * @param array[] $tutors     The list, as `list_for()` gives it.
	 * @param bool    $can_change Whether the reader may change the list.
	 */
	private static function render_parts( $record_id, array $tutors, $can_change ) {
		echo WPCPM_UI::heading( __( 'Tutors', 'wpcredits-program-manager' ), 'h4', self::ANCHOR ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.

		self::render_message( $record_id, $can_change );

		if ( empty( $tutors ) ) {
			echo WPCPM_UI::list_empty( self::empty_words() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the part.
		} else {
			$list = WPCPM_UI::list_open( array( 'facts' => 'under' ) );

			foreach ( $tutors as $tutor ) {
				$list .= WPCPM_UI::row(
					array(
						'name'    => (string) $tutor['name'],
						'facts'   => array( (string) $tutor['email'] ),
						'actions' => $can_change ? array( self::remove_form_parts( $record_id, $tutor ) ) : array(),
					)
				);
			}

			echo $list . WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row() and remove_form_parts().
		}

		if ( $can_change ) {
			self::render_add_form_parts( $record_id );
		}
	}

	/**
	 * A tutor's Remove on the parts: the quiet red button with today's question, posting what today's form posts (its
	 * nonce without an id, its referer), the tutor named for a screen reader.
	 *
	 * @param string $record_id The institution's record ID.
	 * @param array  $tutor     The tutor as listed: `name`, `email`.
	 * @return string Escaped markup.
	 */
	private static function remove_form_parts( $record_id, array $tutor ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. WPCPM_UI::nonce( self::ACTION_REMOVE . '_' . $record_id )
			. WPCPM_Institution_People::hidden_html( self::remove_fields( $record_id, $tutor ) )
			. WPCPM_UI::button(
				array(
					'label'   => __( 'Remove', 'wpcredits-program-manager' ),
					'kind'    => 'quiet-danger',
					'confirm' => self::remove_question( $tutor ),
					'context' => (string) $tutor['name'],
					'side'    => 'front',
				)
			)
			. '</form>';
	}

	/**
	 * Add a tutor on the parts: the inner fold, Name (Required, at most 100 characters) and Email (Required), Add the one
	 * primary. A refused form is drawn open with what was typed (`keep_add()`, read once here) and the problem under the box
	 * it refused.
	 *
	 * @param string $record_id The institution's record ID.
	 */
	private static function render_add_form_parts( $record_id ) {
		$said    = self::message_for( $record_id );
		$status  = null !== $said ? $said['status'] : '';
		$refused = self::refused_field( $status );
		$kept    = WPCPM_Flash::kept( self::KEPT_ADD . $record_id );
		$typed   = static function ( $field ) use ( $kept ) {
			return isset( $kept['values'][ $field ] ) && is_scalar( $kept['values'][ $field ] ) ? (string) $kept['values'][ $field ] : '';
		};

		$out = WPCPM_UI::inner_open(
			array(
				'title' => __( 'Add a tutor', 'wpcredits-program-manager' ),
				'open'  => self::refuses_add( $status ),
				'id'    => self::ADD_ID,
			)
		);

		$out .= null !== $said && self::refuses_add( $status ) ? self::outcome_parts( $record_id, $said ) : '';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= WPCPM_UI::nonce( self::ACTION_ADD . '_' . $record_id ) . WPCPM_Institution_People::hidden_html( self::add_fields( $record_id ) );

		$out .= WPCPM_UI::field(
			array(
				'type'    => 'text',
				'id'      => self::field_id( $record_id, 'name' ),
				'name'    => 'name',
				'label'   => __( 'Name', 'wpcredits-program-manager' ),
				'need'    => 'required',
				'value'   => $typed( 'name' ),
				'attrs'   => array( 'maxlength' => (string) (int) self::NAME_MAX ),
				'problem' => 'name' === $refused ? $said['text'] : '',
			)
		);

		$out .= WPCPM_UI::field(
			array(
				'type'    => 'email',
				'id'      => self::field_id( $record_id, 'email' ),
				'name'    => 'email',
				'label'   => __( 'Email', 'wpcredits-program-manager' ),
				'value'   => $typed( 'email' ),
				'need'    => 'required',
				'problem' => 'email' === $refused ? $said['text'] : '',
			)
		);

		$out .= WPCPM_UI::actions(
			array(
				WPCPM_UI::button(
					array(
						'label' => __( 'Add', 'wpcredits-program-manager' ),
						'side'  => 'front',
					)
				),
			)
		) . '</form>' . WPCPM_UI::inner_close();

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in hidden_html(); the address escaped here.
	}

	/**
	 * Add a tutor, from the form the card draws.
	 *
	 * The nonce keyed to the school the form names, then the school's shape, then the policy against that school
	 * for managing members, so a tutor, a member of another school, a stranger and a post with no school are
	 * refused with the one refusal before anything is written or any lock is taken. The ground the policy
	 * gave goes to the history. Ends the request with the outcome flashed on the card.
	 */
	public static function handle_add() {
		$record = WPCPM_Request::posted_text( 'record' );

		check_admin_referer( self::ACTION_ADD . '_' . $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS, WPCPM_Institution_Policy::subject_institution( $record ) );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		// What an earlier refusal kept is stale once the form is posted again, as every handler that keeps does first.
		WPCPM_Flash::forget( self::KEPT_ADD . $record );

		$added = self::add( $record, WPCPM_Request::posted_text( 'name' ), WPCPM_Request::posted_text( 'email' ), get_current_user_id(), $decision['ground'] );

		if ( is_wp_error( $added ) ) {
			$codes  = array(
				'wpcpm_tutor_name'  => 'name',
				'wpcpm_tutor_email' => 'email',
				'wpcpm_tutor_busy'  => 'busy',
			);
			$code   = $added->get_error_code();
			$status = isset( $codes[ $code ] ) ? $codes[ $code ] : 'error';

			// The typing comes back on the form a refusal opens (Stage B, Task 20): not on "busy", which opens nothing.
			if ( self::refuses_add( $status ) ) {
				self::keep_add( $record );
			}

			self::finish( $status, $record );
		}

		self::finish( $added ? 'added' : 'already', $record );
	}

	/**
	 * Remove a tutor, from the Remove the card draws on each. Their account, if they hold one, is left.
	 *
	 * The same order and the same refusal as `handle_add()`.
	 */
	public static function handle_remove() {
		$record = WPCPM_Request::posted_text( 'record' );

		check_admin_referer( self::ACTION_REMOVE . '_' . $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS, WPCPM_Institution_Policy::subject_institution( $record ) );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$email   = WPCPM_Request::posted_text( 'email' );
		$removed = self::remove( $record, $email, get_current_user_id(), $decision['ground'] );

		if ( $removed ) {
			self::finish( 'removed', $record );
		}

		// `remove()` answers false for a lock another request holds as for nobody listed: a tutor still on the
		// list was not removed for that reason, or because the record could not be kept, and is told the list
		// is busy rather than that there was nothing to remove.
		WPCPM_Institution_Record::forget_cached( $record );

		$address = self::key( is_scalar( $email ) ? $email : '' );
		$listed  = '' !== $address && in_array( $address, array_column( self::list_for( $record ), 'email' ), true );

		self::finish( $listed ? 'busy' : 'gone', $record );
	}

	/**
	 * Keep what a refused Add was typed with, for the one drawing of the form that follows: the name cut where `add()`
	 * bounds it (`NAME_MAX`), the address whole, as posted.
	 *
	 * @param string $record The institution the form is for.
	 */
	private static function keep_add( $record ) {
		WPCPM_Flash::keep(
			self::KEPT_ADD . $record,
			array(
				'name'  => mb_substr( WPCPM_Request::posted_raw( 'name' ), 0, self::NAME_MAX ),
				'email' => WPCPM_Request::posted_raw( 'email' ),
			)
		);
	}

	/**
	 * Flash the outcome and send the person back to the card.
	 *
	 * @param string $status The outcome, a key of `render_message()`.
	 * @param string $record The institution it was about.
	 */
	private static function finish( $status, $record ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'status' => $status,
				'record' => trim( (string) $record ),
			)
		);

		// Add's own refusal lands on its fold, where it is drawn, so the page opens at the form with no script (Gate B
		// round 2, F3); every other outcome on Tutors.
		wp_safe_redirect( WPCPM_Institution_People::card_url( $record ) . '#' . ( self::refuses_add( (string) $status ) ? self::ADD_ID : self::ANCHOR ) );
		exit;
	}
}
