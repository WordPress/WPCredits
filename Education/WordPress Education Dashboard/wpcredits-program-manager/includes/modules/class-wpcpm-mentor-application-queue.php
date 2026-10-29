<?php
/**
 * Mentors module - the Waiting for review tab and its decisions (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where an Administrator reads a mentor application and decides it (design of 16 October 2026,
 * sections 5 to 7): the Mentors screen's Waiting for review tab, in six views, each row whole, and the
 * decisions under it; then, under the views, the rows recently decided as spam or declined, which
 * no view lists, so a genuine application the checks filed as spam, or a declined applicant who
 * appeals, can still be reached (the sponsor queue's "Recently decided", its S5 review's lesson).
 *
 * **The decisions** are the Status choices the program used in Airtable, under their own names:
 *
 * - Select: to `selected`, the selection email goes, with the mentors' course invitation link;
 *   offered on a lapsed row and on a declined one too, for the applicant who appeals (section 5).
 * - Ask for more information: to `info`, the request goes; the row stays in the queue, dated.
 * - Second opinion: to `second`, no email; the note is for a colleague, and needed.
 * - Decline: to `declined`, the decline email goes; the note stays on the site.
 * - Mark as spam: to `spam`, nothing is sent.
 * - Withdraw, on a selected row whose applicant wrote that they are not continuing: to `lapsed`,
 *   nothing is sent; the note says why, and is needed.
 * - Put back in the queue, on a spam row: to `new`; a row that was never acknowledged (one the
 *   form's checks filed as spam, or one past the day's limit on acknowledgements) is sent the
 *   acknowledgement then, once, with the link that confirms the address, and one acknowledged
 *   before, marked as spam by mistake, is sent nothing.
 * - The course is completed, on a selected row while Make mentors from applications is on
 *   (1.122.33): the applicant is made a mentor through the step the daily check takes
 *   (`WPCPM_Mentor_Activation::activate()`), by who pressed and with the note, and the Mentor Status
 *   Checker's Slack message names them; the row moves to `active`, listed under Activated.
 * - Write the Airtable row again, on an Activated row whose Mentors row is still to be written
 *   (1.122.33): the row is written as the activation writes it (`retry_airtable()`).
 * - Send the acknowledgement, on an open row whose acknowledgement never went (1.122.33): it goes
 *   now, once, with the link that confirms the address, and the row stays where it is.
 *
 * A decision that emails the applicant refuses an address nobody has confirmed through the
 * acknowledgement's link, as the institution approval does, and Select refuses while no course
 * invitation link is set. The email goes before the state moves: a send that fails leaves the row as
 * it was. Every decision is a press by an Administrator, the capability asked first and then the
 * nonce keyed to the decision and the row, and is written to the row's history with who and when.
 *
 * **Activated** lists the mentors made from applications, each with its account, what became of the
 * invitation since the activation (`invitation_line()`), read from the email queue, the mail log and
 * the account's stamp and never from before the activation, and what became of its Mentors row in
 * Airtable. The activation's own sentence for an outcome that made no mentor, and the retry's, are
 * carried beside the outcome and printed as its notice (`notices()`).
 */
final class WPCPM_Mentor_Application_Queue {

	/** The ten decisions, one action apiece, so a nonce from one form cannot press another. */
	const ACTION_SELECT      = 'wpcpm_mapp_select';
	const ACTION_INFO        = 'wpcpm_mapp_info';
	const ACTION_SECOND      = 'wpcpm_mapp_second';
	const ACTION_DECLINE     = 'wpcpm_mapp_decline';
	const ACTION_SPAM        = 'wpcpm_mapp_spam';
	const ACTION_WITHDRAW    = 'wpcpm_mapp_withdraw';
	const ACTION_REOPEN      = 'wpcpm_mapp_reopen';
	const ACTION_COMPLETE    = 'wpcpm_mapp_complete';
	const ACTION_AIRTABLE    = 'wpcpm_mapp_airtable';
	const ACTION_ACKNOWLEDGE = 'wpcpm_mapp_acknowledge';

	/** The field naming the application a decision is posted for. */
	const FIELD_APPLICATION = 'wpcpm_mapp_app';

	/** The field a decision's note is posted in. */
	const FIELD_NOTE = 'wpcpm_mapp_note';

	/** The form name a refused note's typing is kept under, with the row's ID (`WPCPM_Typed_Text::box_form()`). */
	const TYPED = 'mapp-note';

	/** A needed note: long enough to be a sentence, short enough to read. */
	const MIN_NOTE = 10;
	const MAX_NOTE = 2000;

	/** The three emails the decisions send, named for the mail log. */
	const MAIL_SELECTED = 'mentor-selected';
	const MAIL_INFO     = 'mentor-more-information';
	const MAIL_DECLINED = 'mentor-declined';

	/** The query argument the tab's views travel in. */
	const VIEW_ARG = 'mapp_view';

	/** The query argument the Find by reference field is sent in. */
	const FIND_ARG = 'mapp_ref';

	/** The ID of the region that answers the field, which the field's form lands on. */
	const FOUND_ANCHOR = 'wpcpm-mapp-found';

	/**
	 * Most rows a view draws, and Recently decided under it: the first in the list's own order, the
	 * oldest first in the views of applications still open, whose turn it is, and in Activated and
	 * Recently decided the order each gives (`render()`, `render_decided()`).
	 */
	const QUEUE_MAX = 50;

	/** The flash channel the Mentors screen reads its outcomes from. */
	const FLASH = 'mentors_admin';

	/**
	 * The flash channel a press's own sentence for its notice travels on, tagged with the outcome it
	 * was worded for (1.122.33): the activation's for an outcome that made no mentor, and the retry's.
	 */
	const FLASH_MESSAGE = 'mentors_admin_mapp';

	/** The mentor invitation's id, which the mail log keeps every one under. */
	const MAIL_INVITE = 'invite-mentor';

	/**
	 * Hooks: the ten decisions, for a signed-in Administrator alone (`admin_post_`, never `nopriv`).
	 */
	public static function init() {
		foreach ( self::decisions() as $action => $method ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * Each decision's action and its handler.
	 *
	 * @return array<string, string>
	 */
	public static function decisions() {
		return array(
			self::ACTION_SELECT      => 'handle_select',
			self::ACTION_INFO        => 'handle_info',
			self::ACTION_SECOND      => 'handle_second',
			self::ACTION_DECLINE     => 'handle_decline',
			self::ACTION_SPAM        => 'handle_spam',
			self::ACTION_WITHDRAW    => 'handle_withdraw',
			self::ACTION_REOPEN      => 'handle_reopen',
			self::ACTION_COMPLETE    => 'handle_complete',
			self::ACTION_AIRTABLE    => 'handle_airtable',
			self::ACTION_ACKNOWLEDGE => 'handle_acknowledge',
		);
	}

	/**
	 * The states a row is decided in and listed under Recently decided for, until retention deletes
	 * it: filed as spam, and declined.
	 *
	 * @return string[]
	 */
	public static function decided_states() {
		return WPCPM_Mentor_Application::decided_states();
	}

	/**
	 * The tab's six views, slug => the states each lists and its label, in the bar's order: the
	 * queue itself, the two kinds of row in it a person may look for, then the three the queue has let
	 * go of, the selected ones waiting for the course, the ones made mentors (1.122.33) and the lapsed
	 * ones.
	 *
	 * @return array<string, array{states: string[], label: string}>
	 */
	public static function views() {
		return array(
			'waiting'  => array(
				'states' => WPCPM_Mentor_Application::open_states(),
				'label'  => __( 'Waiting', 'wpcredits-program-manager' ),
			),
			'info'     => array(
				'states' => array( WPCPM_Mentor_Application::STATE_INFO ),
				'label'  => __( 'Asked for more information', 'wpcredits-program-manager' ),
			),
			'second'   => array(
				'states' => array( WPCPM_Mentor_Application::STATE_SECOND ),
				'label'  => __( 'Second opinion', 'wpcredits-program-manager' ),
			),
			'selected' => array(
				'states' => array( WPCPM_Mentor_Application::STATE_SELECTED ),
				'label'  => __( 'Selected (course pending)', 'wpcredits-program-manager' ),
			),
			'active'   => array(
				'states' => array( WPCPM_Mentor_Application::STATE_ACTIVE ),
				'label'  => __( 'Activated', 'wpcredits-program-manager' ),
			),
			'lapsed'   => array(
				'states' => array( WPCPM_Mentor_Application::STATE_LAPSED ),
				'label'  => __( 'Lapsed', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * The view the request names, or the queue.
	 *
	 * @return string A key of `views()`.
	 */
	public static function current_view() {
		$view = WPCPM_Request::key( self::VIEW_ARG );

		return isset( self::views()[ $view ] ) ? $view : 'waiting';
	}

	/**
	 * The tab's address at one view.
	 *
	 * @param string $view A key of `views()`.
	 * @return string
	 */
	public static function view_url( $view = 'waiting' ) {
		$url = admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' );

		return 'waiting' === $view ? $url : add_query_arg( self::VIEW_ARG, $view, $url );
	}

	/**
	 * The states the Find by reference field draws an application from: every state the tab lists an
	 * application under, in any view, Activated and Recently decided included, and none but a state the
	 * application class knows, so a row with no state, or one the tab does not know, is never found.
	 * Not an application filed as spam: the field says nothing of it, so the sentence it gets is the one
	 * a reference nobody has gets, and its row stays where Recently decided lists it.
	 *
	 * @return string[]
	 */
	private static function findable_states() {
		return array_values( array_diff( WPCPM_Mentor_Application::states(), array( WPCPM_Mentor_Application::STATE_SPAM ) ) );
	}

	/**
	 * The application a reference names, when it is in a state the field draws (`findable_states()`).
	 *
	 * @param string $reference A reference such as `MAPP-2026-0007`.
	 * @return WP_Post|null
	 */
	private static function found( $reference ) {
		$post = WPCPM_Mentor_Application::by_reference( $reference );

		return $post instanceof WP_Post && in_array( WPCPM_Mentor_Application::state_of( $post ), self::findable_states(), true ) ? $post : null;
	}

	/**
	 * The rows of a list without the one the Find by reference field drew above the views, so the page
	 * holds its ID once.
	 *
	 * @param WP_Post[]    $rows  A list's rows.
	 * @param WP_Post|null $found The application the field drew, or null.
	 * @return WP_Post[]
	 */
	private static function without( array $rows, $found ) {
		if ( ! $found instanceof WP_Post ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( $post ) use ( $found ) {
					return (int) $post->ID !== (int) $found->ID;
				}
			)
		);
	}

	/*
	 * The decisions
	 * --------------------------------------------------------------------
	 */

	/**
	 * What every decision does first: the capability, then the nonce keyed to the decision and the
	 * row, then the row, which must be an application in a state the decision takes.
	 *
	 * @param string   $action The decision.
	 * @param string[] $states The states it takes a row from.
	 * @return WP_Post
	 */
	private static function begin( $action, array $states ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		check_admin_referer( $action . '_' . $post_id );

		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			self::leave( 'mapp-unknown' );
		}

		if ( ! in_array( WPCPM_Mentor_Application::state_of( $post ), $states, true ) ) {
			self::leave( 'mapp-state' );
		}

		return $post;
	}

	/**
	 * The note posted with a decision, cleaned, or a refusal that gives the typing back: one the
	 * cleaner would take words from, one longer than the box holds, and, where the note is needed,
	 * one shorter than a sentence.
	 *
	 * @param WP_Post $post   The row.
	 * @param string  $key    The decision's own box (`select`, `info`, `second`, `decline`,
	 *                        `withdraw`, `complete`).
	 * @param bool    $needed Whether the decision needs a note.
	 * @return string
	 */
	private static function note( WP_Post $post, $key, $needed ) {
		$form  = WPCPM_Typed_Text::box_form( self::TYPED, (int) $post->ID );
		$typed = WPCPM_Request::posted_raw( self::FIELD_NOTE );

		// What an earlier refusal of a box kept is stale once a form of this row is posted again.
		WPCPM_Typed_Text::forget( $form );

		$refused = '';

		if ( WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ) {
			$refused = 'mapp-note-loss';
		} elseif ( WPCPM_Typed_Text::box_length( $typed ) > self::MAX_NOTE ) {
			$refused = 'mapp-note-long';
		} elseif ( $needed && WPCPM_Typed_Text::box_length( $typed ) < self::MIN_NOTE ) {
			$refused = 'mapp-note-short';
		}

		if ( '' !== $refused ) {
			WPCPM_Typed_Text::keep_box( $form, $key, $typed, self::MAX_NOTE );
			self::leave( $refused );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- begin() checked the nonce before any handler reads this.
		return isset( $_POST[ self::FIELD_NOTE ] ) && is_scalar( $_POST[ self::FIELD_NOTE ] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST[ self::FIELD_NOTE ] ) ) ) : '';
	}

	/**
	 * The applicant's address for a decision that emails them: refused while nobody has confirmed it
	 * through the acknowledgement's link, or when the row holds none.
	 *
	 * @param WP_Post $post The row.
	 * @return string
	 */
	private static function confirmed_address( WP_Post $post ) {
		$email = WPCPM_Mentor_Application::email_of( $post );

		if ( '' === $email ) {
			self::leave( 'mapp-no-email' );
		}

		if ( ! WPCPM_Mentor_Application::is_confirmed( (int) $post->ID ) ) {
			self::leave( 'mapp-unconfirmed' );
		}

		return $email;
	}

	/**
	 * Email the applicant under one id, with their name, the reference and what the email adds, and
	 * the program's Reply-To; or leave, refused, when the email could not be handed off.
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $email Where it goes.
	 * @param string  $id    The email's id.
	 * @param array   $more  What this email adds to the name and the reference.
	 */
	private static function mail( WP_Post $post, $email, $id, array $more = array() ) {
		$values = array_merge(
			array(
				'site_name'      => WPCPM_Mail::site_name(),
				'applicant_name' => WPCPM_Mentor_Application::name_of( $post ),
				'reference'      => WPCPM_Mentor_Application::reference_of( $post ),
			),
			$more
		);

		$build = static function () use ( $id, $values ) {
			return WPCPM_Mail_Templates::mail( $id, $values, array( 'headers' => WPCPM_Mentor_Application_Form::reply_headers() ) );
		};

		if ( ! WPCPM_Mail::send_to( $email, $id, $build ) ) {
			self::leave( 'mapp-not-sent' );
		}
	}

	/**
	 * Select: the selection email with the mentors' course invitation link, and the row waits for the
	 * course. From the queue, from a lapsed row whose applicant turned up again, and from a declined
	 * row whose applicant appealed (design, section 5: "a note and Select on the same row").
	 */
	public static function handle_select() {
		$post  = self::begin( self::ACTION_SELECT, self::select_states() );
		$link  = trim( (string) WPCPM_Settings::get_value( 'mentor_course_link', '' ) );
		$title = trim( (string) WPCPM_Settings::get_value( 'checker_course_title', '' ) );

		if ( '' === $link ) {
			self::leave( 'mapp-no-course-link' );
		}

		$note  = self::note( $post, 'select', false );
		$email = self::confirmed_address( $post );

		self::mail(
			$post,
			$email,
			self::MAIL_SELECTED,
			array(
				// The template's course name is optional but shares its sentence with the required link, so a
				// title an Administrator blanked is the default title, as the template's example has it, never
				// a hole before the link.
				'course_name'     => '' !== $title ? $title : 'WordPress Credits Mentor\'s Course',
				'course_link'     => $link,
				'program_contact' => WPCPM_Mail_Templates::program_contact(),
			)
		);

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_SELECTED, WPCPM_Mentor_Application::EVENT_SELECTED, get_current_user_id(), $note );

		self::leave( 'mapp-selected' );
	}

	/**
	 * Ask for more information: the request goes, and the row stays in the queue, dated.
	 */
	public static function handle_info() {
		$post  = self::begin( self::ACTION_INFO, WPCPM_Mentor_Application::open_states() );
		$note  = self::note( $post, 'info', false );
		$email = self::confirmed_address( $post );

		self::mail( $post, $email, self::MAIL_INFO );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_INFO, WPCPM_Mentor_Application::EVENT_INFO, get_current_user_id(), $note );

		self::leave( 'mapp-info' );
	}

	/**
	 * Second opinion: the row is marked for a colleague and stays in the queue. Nothing is sent, so the
	 * address needs no confirming; the note is what the colleague reads, so it is needed.
	 */
	public static function handle_second() {
		$post = self::begin( self::ACTION_SECOND, WPCPM_Mentor_Application::open_states() );
		$note = self::note( $post, 'second', true );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_SECOND, WPCPM_Mentor_Application::EVENT_SECOND, get_current_user_id(), $note );

		self::leave( 'mapp-second' );
	}

	/**
	 * Decline: the decline email goes, and the row closes. The note stays on the site and is never sent.
	 */
	public static function handle_decline() {
		$post  = self::begin( self::ACTION_DECLINE, WPCPM_Mentor_Application::open_states() );
		$note  = self::note( $post, 'decline', false );
		$email = self::confirmed_address( $post );

		self::mail( $post, $email, self::MAIL_DECLINED );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_DECLINED, WPCPM_Mentor_Application::EVENT_DECLINED, get_current_user_id(), $note );

		self::leave( 'mapp-declined' );
	}

	/**
	 * Mark as spam: the row closes and nothing is sent, since the address on it is forged or somebody
	 * else's.
	 */
	public static function handle_spam() {
		$post = self::begin( self::ACTION_SPAM, WPCPM_Mentor_Application::open_states() );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_SPAM, WPCPM_Mentor_Application::EVENT_SPAM, get_current_user_id() );

		self::leave( 'mapp-spam' );
	}

	/**
	 * Withdraw: a selected applicant wrote that they are not continuing, so the row lapses now, with
	 * the note saying so, and nothing is sent, as Airtable's Dropped out sent nothing.
	 *
	 * Under the application's lock (`locked()`), as the activation and the lapse clock work: a row
	 * that an activation filed active meanwhile stays active, since one filed Lapsed under a mentor
	 * would never have its Airtable row written again, and a row another request holds is left.
	 */
	public static function handle_withdraw() {
		$post = self::begin( self::ACTION_WITHDRAW, array( WPCPM_Mentor_Application::STATE_SELECTED ) );
		$note = self::note( $post, 'withdraw', true );

		self::locked(
			$post,
			static function ( WP_Post $row ) use ( $note ) {
				if ( WPCPM_Mentor_Application::STATE_SELECTED !== WPCPM_Mentor_Application::state_of( $row ) ) {
					return 'mapp-state';
				}

				WPCPM_Mentor_Application::decide( (int) $row->ID, WPCPM_Mentor_Application::STATE_LAPSED, WPCPM_Mentor_Application::EVENT_WITHDRAWN, get_current_user_id(), $note );

				return 'mapp-withdrawn';
			}
		);
	}

	/**
	 * Do a press's work under the application's lock (`WPCPM_Mentor_Application::lock_name()`,
	 * through `WPCPM_Lock`), the one the activation, its retry, settling and the lapse clock take, so
	 * one of them at a time works on the row (1.122.33). Once the lock is held, the row's meta is
	 * dropped from this request's copy, so the work reads the row as it stands; the work answers the
	 * outcome. Another request holding the lock is `mapp-busy`, with that sentence carried and
	 * nothing done. The outcome is left with once the lock is let go, never inside the `try`: the
	 * redirect ends the request, and `exit` runs no `finally`.
	 *
	 * @param WP_Post  $post The row.
	 * @param callable $work Given the row, answers a key of `messages()`.
	 */
	private static function locked( WP_Post $post, callable $work ) {
		$lock  = WPCPM_Mentor_Application::lock_name( (int) $post->ID );
		$token = WPCPM_Lock::take( $lock, WPCPM_Mentor_Application::LOCK_TTL );

		if ( '' === $token ) {
			self::leave( 'mapp-busy', __( 'Another request is working on this application at this moment, so nothing was done.', 'wpcredits-program-manager' ) );
		}

		try {
			WPCPM_Mentor_Application::forget_cached( (int) $post->ID );

			$status = (string) call_user_func( $work, $post );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}

		self::leave( $status );
	}

	/**
	 * Put back in the queue: a row filed as spam is `new` again, for a person to decide. One that was
	 * never acknowledged, which the form's checks filed or which came past the day's limit on
	 * acknowledgements, is sent the acknowledgement now, through the form's own sender, so its
	 * applicant has the link that confirms the address; it goes once, since the history then says it
	 * went. One acknowledged before, marked as spam by mistake, is sent nothing, and so is one with no
	 * address. A send that fails leaves the row as it was.
	 */
	public static function handle_reopen() {
		$post = self::begin( self::ACTION_REOPEN, array( WPCPM_Mentor_Application::STATE_SPAM ) );
		$send = ! self::acknowledged( $post ) && '' !== WPCPM_Mentor_Application::email_of( $post );

		if ( $send && ! WPCPM_Mentor_Application_Form::mail_applicant( (int) $post->ID ) ) {
			self::leave( 'mapp-not-sent' );
		}

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_NEW, $send ? WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED : WPCPM_Mentor_Application::EVENT_REOPENED, get_current_user_id() );

		self::leave( $send ? 'mapp-reopened-sent' : 'mapp-reopened' );
	}

	/**
	 * The course is completed: an Administrator who knows the applicant finished the mentors' course
	 * makes them a mentor now, through the step the daily check takes when the course shows on the
	 * profile (design, section 6), by who pressed and with the note. The step refuses while Make
	 * mentors from applications is off; the press is not offered then. The Mentor Status Checker's
	 * Slack message, which the step told, is sent at once. For an outcome that made no mentor, the
	 * step's own sentence is carried beside the outcome for the notice.
	 */
	public static function handle_complete() {
		$post   = self::begin( self::ACTION_COMPLETE, array( WPCPM_Mentor_Application::STATE_SELECTED ) );
		$note   = self::note( $post, 'complete', false );
		$answer = WPCPM_Mentor_Activation::activate( (int) $post->ID, get_current_user_id(), $note );
		$answer = is_array( $answer ) ? $answer : array();

		$outcome = isset( $answer['outcome'] ) ? (string) $answer['outcome'] : '';

		if ( 'activated' === $outcome ) {
			WPCPM_Mentor_Checker_Slack::flush();

			self::leave( self::activated_status( $answer ) );
		}

		$statuses = array(
			'already'        => 'mapp-already',
			'already-mentor' => 'mapp-already-mentor',
			'off'            => 'mapp-off',
			'busy'           => 'mapp-busy',
			'no-account'     => 'mapp-no-account',
			'not-selected'   => 'mapp-state',
		);

		self::leave( isset( $statuses[ $outcome ] ) ? $statuses[ $outcome ] : 'mapp-unknown', isset( $answer['message'] ) ? (string) $answer['message'] : '' );
	}

	/**
	 * The notice an activation that made a mentor leaves, by what became of the invitation and of the
	 * Airtable row: sent, to an account made or to one that was there and was linked, held because
	 * the account was linked by its WordPress.org username alone (ruling R5), held because another
	 * account also answered for the applicant (ruling P9b), or not sent, as an Administrator's account
	 * is never sent one; and the row written or refused.
	 *
	 * @param array $answer What `WPCPM_Mentor_Activation::activate()` answered.
	 * @return string A key of `messages()`.
	 */
	private static function activated_status( array $answer ) {
		$held    = isset( $answer['held'] ) ? (string) $answer['held'] : '';
		$refused = isset( $answer['airtable'] ) && 'refused' === $answer['airtable'];

		if ( in_array( $held, array( 'login', 'record' ), true ) ) {
			$status = 'mapp-activated-' . $held;
		} elseif ( isset( $answer['invited'] ) && false === $answer['invited'] ) {
			$status = 'mapp-activated-admin';
		} elseif ( ! empty( $answer['linked'] ) ) {
			$status = 'mapp-activated-linked';
		} else {
			$status = 'mapp-activated';
		}

		return $refused ? $status . '-refused' : $status;
	}

	/**
	 * Write the Airtable row again: for an activated application whose Mentors row Airtable refused,
	 * or whose write a request that died never finished, by who pressed (design, section 7). No note
	 * and no question: the write searches the table by the address before it creates anything, so a
	 * second press makes no second row. The step's own sentence is carried beside the outcome for the
	 * notice, since a row with nothing more to write may be one whose account is gone.
	 */
	public static function handle_airtable() {
		$post   = self::begin( self::ACTION_AIRTABLE, array( WPCPM_Mentor_Application::STATE_ACTIVE ) );
		$answer = WPCPM_Mentor_Activation::retry_airtable( (int) $post->ID, get_current_user_id() );
		$answer = is_array( $answer ) ? $answer : array();

		$statuses = array(
			'written'    => 'mapp-airtable-written',
			'refused'    => 'mapp-airtable-refused',
			'not-needed' => 'mapp-airtable-not-needed',
			'gone'       => 'mapp-airtable-gone',
			'busy'       => 'mapp-busy',
			'not-active' => 'mapp-state',
		);
		$outcome  = isset( $answer['outcome'] ) ? (string) $answer['outcome'] : '';

		self::leave( isset( $statuses[ $outcome ] ) ? $statuses[ $outcome ] : 'mapp-unknown', isset( $answer['message'] ) ? (string) $answer['message'] : '' );
	}

	/**
	 * Send the acknowledgement: an open row whose acknowledgement never went, past the day's limit on
	 * acknowledgements or after a hand-off the mail layer refused, is sent it now, through the form's
	 * own sender, with the link that confirms the address, and stays where it is. It goes once, since
	 * the history then says it went, and it does not count against the form's daily limit, as Put back
	 * in the queue's does not. A row acknowledged before, or with no address, is sent nothing; a send
	 * that fails leaves the row as it was.
	 *
	 * The send is made under the application's lock (`locked()`), with the row read again there, so
	 * two presses at one moment send it once: the second finds the lock held, or, once it is let go,
	 * the acknowledgement in the history.
	 */
	public static function handle_acknowledge() {
		$post = self::begin( self::ACTION_ACKNOWLEDGE, WPCPM_Mentor_Application::open_states() );

		if ( self::acknowledged( $post ) ) {
			self::leave( 'mapp-state' );
		}

		if ( '' === WPCPM_Mentor_Application::email_of( $post ) ) {
			self::leave( 'mapp-no-email' );
		}

		self::locked(
			$post,
			static function ( WP_Post $row ) {
				if ( ! in_array( WPCPM_Mentor_Application::state_of( $row ), WPCPM_Mentor_Application::open_states(), true ) || self::acknowledged( $row ) ) {
					return 'mapp-state';
				}

				if ( ! WPCPM_Mentor_Application_Form::mail_applicant( (int) $row->ID ) ) {
					return 'mapp-not-sent';
				}

				WPCPM_Mentor_Application::add_event( (int) $row->ID, WPCPM_Mentor_Application::EVENT_ACKNOWLEDGED, get_current_user_id() );

				return 'mapp-acknowledged';
			}
		);
	}

	/**
	 * What the signals say kept the acknowledgement from going, most telling first: the mail layer
	 * refusing it, the day's limit on acknowledgements, and the form's checks filing the row as spam
	 * (the honeypot, the dwell token).
	 *
	 * @param WP_Post $post The row.
	 * @return string[] Signals, none when nothing kept it.
	 */
	private static function withheld( WP_Post $post ) {
		return array_values(
			array_intersect(
				array(
					WPCPM_Mentor_Application_Form::SIGNAL_MAIL_FAILED,
					WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING,
					WPCPM_Mentor_Application_Form::SIGNAL_HONEYPOT,
					WPCPM_Mentor_Application_Form::SIGNAL_DWELL,
				),
				WPCPM_Mentor_Application::signals_of( $post )
			)
		);
	}

	/**
	 * Whether the applicant was sent the acknowledgement, with the link that confirms the address:
	 * every row but one the form's checks filed as spam, one past the day's limit on acknowledgements
	 * or one whose hand-off the mail layer refused, until Put back in the queue or Send the
	 * acknowledgement sends it.
	 *
	 * @param WP_Post $post The row.
	 * @return bool
	 */
	private static function acknowledged( WP_Post $post ) {
		if ( array() === self::withheld( $post ) ) {
			return true;
		}

		return 0 < WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED )
			|| 0 < WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_ACKNOWLEDGED );
	}

	/**
	 * What the mark says of a row whose acknowledgement never went: the cause the signals give, then
	 * what it means for the applicant and for the decisions.
	 *
	 * @param WP_Post $post The row.
	 * @return string
	 */
	private static function unacknowledged_sentence( WP_Post $post ) {
		$withheld = self::withheld( $post );
		$cause    = isset( $withheld[0] ) ? $withheld[0] : '';

		if ( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_FAILED === $cause ) {
			$because = __( 'The acknowledgement was not sent, because the email could not be handed off.', 'wpcredits-program-manager' );
		} elseif ( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING === $cause ) {
			$because = __( 'The acknowledgement was not sent, because the day\'s limit on acknowledgements had been reached.', 'wpcredits-program-manager' );
		} else {
			$because = __( 'The acknowledgement was not sent, because the form\'s checks filed the application as spam.', 'wpcredits-program-manager' );
		}

		return $because . ' ' . __( 'The applicant has no link to confirm the address with, so Select, Ask for more information and Decline wait. Send the acknowledgement sends it now, once; for an application filed as spam, Put back in the queue does.', 'wpcredits-program-manager' );
	}

	/**
	 * The states Select takes a row from: the four open ones, lapsed and declined.
	 *
	 * @return string[]
	 */
	private static function select_states() {
		return array_merge( WPCPM_Mentor_Application::open_states(), array( WPCPM_Mentor_Application::STATE_LAPSED, WPCPM_Mentor_Application::STATE_DECLINED ) );
	}

	/**
	 * Back to the tab with a one-shot outcome, at the view the press was made on, and, where the step
	 * pressed worded its own sentence for it, that sentence beside it, tagged with the outcome
	 * (`notices()`). Every press writes the second channel, empty when it carries nothing, so a
	 * sentence a redirect nobody followed left behind is never printed under a later press.
	 *
	 * @param string $status  A key of `messages()`.
	 * @param string $message The step's own sentence for it, or ''.
	 */
	private static function leave( $status, $message = '' ) {
		$view    = WPCPM_Request::posted_key( self::VIEW_ARG, 'waiting' );
		$message = trim( (string) $message );

		WPCPM_Flash::set( self::FLASH, $status );
		WPCPM_Flash::set(
			self::FLASH_MESSAGE,
			'' === $message ? '' : array(
				'status'  => (string) $status,
				'message' => $message,
			)
		);
		wp_safe_redirect( self::view_url( isset( self::views()[ $view ] ) ? $view : 'waiting' ) );
		exit;
	}

	/**
	 * The notices the tab prints: `messages()`, with the sentence the last press carried for its
	 * outcome in place of that outcome's own, which the activation and the retry word themselves.
	 *
	 * Called where the notice is printed and nowhere else, since what was carried is one-shot: the
	 * Mentors screen's Waiting for review tab, which takes the outcome itself. It is taken whatever
	 * the outcome printed, and only the outcome it was carried for reads it, so it never surfaces
	 * under a later press. `messages()` stays a plain map anything may read (the S5 review's lesson).
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	public static function notices() {
		$messages = self::messages();
		$carried  = WPCPM_Flash::take( self::FLASH_MESSAGE );

		if ( is_array( $carried ) && isset( $carried['status'], $carried['message'], $messages[ (string) $carried['status'] ] ) && '' !== trim( (string) $carried['message'] ) ) {
			$messages[ (string) $carried['status'] ][1] = (string) $carried['message'];
		}

		return $messages;
	}

	/**
	 * What each press can leave, in the Administrator's words.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	public static function messages() {
		return array_merge( self::decision_messages(), self::activation_messages() );
	}

	/**
	 * What The course is completed, Write the Airtable row again and Send the acknowledgement can
	 * leave (1.122.33). An activation that made a mentor says whether the account was made or linked
	 * and what became of the invitation, sent, held for a person to confirm the account (rulings R5
	 * and P9b) or not sent to an Administrator, then of the Airtable row; an outcome that made none prints the activation's own sentence where
	 * the press carried it (`notices()`), and these where it did not.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	private static function activation_messages() {
		$written = __( 'The Airtable row is written.', 'wpcredits-program-manager' );
		$refused = __( 'Airtable refused the row, so Write the Airtable row again waits on it under Activated.', 'wpcredits-program-manager' );
		$login   = __( 'The applicant is a mentor now, but the invitation with the welcome is held: the account was linked by WordPress.org username alone. Confirm it is the same person, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		$record  = __( 'The applicant is a mentor now, but the invitation with the welcome is held: another account also answered for the applicant. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		$admin   = __( 'The applicant is a mentor now. No invitation was sent: the account is an Administrator\'s, which signs in already.', 'wpcredits-program-manager' );

		return array(
			'mapp-activated'                => array( 'success', __( 'The applicant is a mentor now: their account is made, the Airtable row written, and the invitation with the welcome is on its way.', 'wpcredits-program-manager' ) ),
			'mapp-activated-refused'        => array( 'warning', __( 'The applicant is a mentor now: their account is made and the invitation with the welcome is on its way.', 'wpcredits-program-manager' ) . ' ' . $refused ),
			'mapp-activated-linked'         => array( 'success', __( 'The applicant is a mentor now: their existing account is linked, the Airtable row written, and the invitation with the welcome is on its way.', 'wpcredits-program-manager' ) ),
			'mapp-activated-linked-refused' => array( 'warning', __( 'The applicant is a mentor now: their existing account is linked and the invitation with the welcome is on its way.', 'wpcredits-program-manager' ) . ' ' . $refused ),
			'mapp-activated-login'          => array( 'warning', $login . ' ' . $written ),
			'mapp-activated-login-refused'  => array( 'warning', $login . ' ' . $refused ),
			'mapp-activated-record'         => array( 'warning', $record . ' ' . $written ),
			'mapp-activated-record-refused' => array( 'warning', $record . ' ' . $refused ),
			'mapp-activated-admin'          => array( 'success', $admin . ' ' . $written ),
			'mapp-activated-admin-refused'  => array( 'warning', $admin . ' ' . $refused ),
			'mapp-already'                  => array( 'warning', __( 'That application was made a mentor already. Nothing was changed.', 'wpcredits-program-manager' ) ),
			'mapp-already-mentor'           => array( 'success', __( 'That applicant is a mentor on this site already: the application is filed under Activated with a note, and nothing was sent or written to Airtable.', 'wpcredits-program-manager' ) ),
			'mapp-off'                      => array( 'error', __( 'Nothing was done: Make mentors from applications is off, under Mentor applications on the Students and mentors tab of Settings. It is switched on at the launch.', 'wpcredits-program-manager' ) ),
			'mapp-busy'                     => array( 'warning', __( 'Another request is making that applicant a mentor at this moment. Reload the page in a minute to see where it got to.', 'wpcredits-program-manager' ) ),
			'mapp-no-account'               => array( 'error', __( 'The account could not be made, so the application waits under Selected (course pending), with the reason on it.', 'wpcredits-program-manager' ) ),
			'mapp-airtable-written'         => array( 'success', __( 'The Airtable row is written, and the mentor\'s account holds its record.', 'wpcredits-program-manager' ) ),
			'mapp-airtable-refused'         => array( 'error', __( 'Airtable refused the row again. The reason is on the application; nothing else changed.', 'wpcredits-program-manager' ) ),
			'mapp-airtable-not-needed'      => array( 'success', __( 'Nothing was written: the mentor\'s account holds its Airtable record already.', 'wpcredits-program-manager' ) ),
			'mapp-airtable-gone'            => array( 'warning', __( 'The account made for this mentor no longer exists; nothing more is written for it.', 'wpcredits-program-manager' ) ),
			'mapp-acknowledged'             => array( 'success', __( 'The acknowledgement is sent, with the link that confirms the address. The application stays where it was.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * What the seven decisions of the queue itself can leave (1.122.31), and the refusals every press
	 * shares.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	private static function decision_messages() {
		return array(
			'mapp-selected'       => array( 'success', __( 'The applicant is selected, and the selection email with the mentors\' course invitation link is sent. The application waits for the course under Selected (course pending).', 'wpcredits-program-manager' ) ),
			'mapp-info'           => array( 'success', __( 'The request for more information is sent. The application stays in the queue, under Asked for more information; record what comes back in the note of the decision you take next.', 'wpcredits-program-manager' ) ),
			'mapp-second'         => array( 'success', __( 'The application is marked for a second opinion, with your note for a colleague. Nothing was sent to the applicant.', 'wpcredits-program-manager' ) ),
			'mapp-declined'       => array( 'success', __( 'The application is declined, and the decline email is sent. Your note stays on this site.', 'wpcredits-program-manager' ) ),
			'mapp-spam'           => array( 'success', __( 'The application is marked as spam. Nothing was sent to the address on it.', 'wpcredits-program-manager' ) ),
			'mapp-withdrawn'      => array( 'success', __( 'The application is withdrawn and listed under Lapsed, with your note. Nothing was sent to the applicant, and Select is offered there if they come back.', 'wpcredits-program-manager' ) ),
			'mapp-reopened'       => array( 'success', __( 'The application is back in the queue, under Waiting. Nothing was sent to the address on it.', 'wpcredits-program-manager' ) ),
			'mapp-reopened-sent'  => array( 'success', __( 'The application is back in the queue, under Waiting, and the acknowledgement it never had is sent, with the link that confirms the address.', 'wpcredits-program-manager' ) ),
			'mapp-unknown'        => array( 'error', __( 'There is no mentor application with that ID.', 'wpcredits-program-manager' ) ),
			'mapp-state'          => array( 'error', __( 'That application is not in a state this decision applies to. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
			'mapp-unconfirmed'    => array( 'error', __( 'Nothing was sent. The applicant has not confirmed the address with the link in the acknowledgement, and a decision that emails them waits until they have. If they never do, Mark as spam closes the application without writing to anyone.', 'wpcredits-program-manager' ) ),
			'mapp-no-email'       => array( 'error', __( 'Nothing was sent. That application holds no address an email can go to.', 'wpcredits-program-manager' ) ),
			'mapp-no-course-link' => array( 'error', __( 'Nothing was sent. The selection email gives the mentors\' course invitation link, and none is set: fill in Mentors\' course invitation link on the Students and mentors tab of WPCredits Program > Settings.', 'wpcredits-program-manager' ) ),
			'mapp-not-sent'       => array( 'error', __( 'The email could not be handed off, so the application was left as it was. The Log tab of Tools > Emails says what happened to it.', 'wpcredits-program-manager' ) ),
			/* translators: %s: the least length of a note, in characters. */
			'mapp-note-short'     => array( 'error', sprintf( __( 'Nothing was done. This decision needs a note of at least %s characters, for whoever reads the application next.', 'wpcredits-program-manager' ), number_format_i18n( self::MIN_NOTE ) ) ),
			/* translators: %s: the longest note allowed, in characters. */
			'mapp-note-long'      => array( 'error', sprintf( __( 'Nothing was done. The note is longer than %s characters: shorten it and press again.', 'wpcredits-program-manager' ), number_format_i18n( self::MAX_NOTE ) ) ),
			'mapp-note-loss'      => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Note', 'wpcredits-program-manager' ), 'save' ) ),
		);
	}

	/*
	 * The tab
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Waiting for review tab: the views with their counts, then the view's rows, oldest first,
	 * each whole with the decisions its state allows. The capability is asked here as well as by the
	 * screen: every answer a stranger typed is printed below.
	 *
	 * Activated draws the activation's own list (`WPCPM_Mentor_Activation::activated_posts()`), every
	 * row still waiting for its Mentors row first, then the most recently made mentors, read one past
	 * the rows it draws so it can say when it was cut. The list may settle a row as it reads it, a
	 * row whose account was linked meanwhile or is gone, under that row's lock and only for such a
	 * row (ruling R1).
	 */
	public static function render() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$view  = self::current_view();
		$views = self::views();
		$total = WPCPM_Mentor_Application::count_in( $views[ $view ]['states'] );

		if ( 'active' === $view ) {
			$rows = WPCPM_Mentor_Activation::activated_posts( self::QUEUE_MAX + 1 );
			$cut  = count( $rows ) > self::QUEUE_MAX;
			$rows = array_slice( $rows, 0, self::QUEUE_MAX );
		} else {
			$rows = WPCPM_Mentor_Application::applications( $views[ $view ]['states'], self::QUEUE_MAX );
			$cut  = count( $rows ) < $total;
		}

		echo '<div class="wpcpm-card" id="wpcpm-mentor-applications">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Mentor applications', 'wpcredits-program-manager' ), esc_html( number_format_i18n( WPCPM_Mentor_Application::waiting_count() ) ) );
		echo '<p class="description">' . esc_html__( 'People who applied to mentor through the form on the site, oldest first. Select sends the mentors\' course invitation link; Ask for more information and Decline send their emails; Second opinion, Mark as spam and Withdraw send nothing. A decision that emails the applicant waits until they have confirmed the address. The course is completed, or the daily check finding the course on the profile, makes a selected applicant a mentor once Make mentors from applications is on.', 'wpcredits-program-manager' ) . '</p>';

		$found = self::render_find( $view );

		echo '<ul class="subsubsub">';

		$links = array();

		foreach ( $views as $slug => $about ) {
			$links[] = sprintf(
				'<li class="%1$s"><a href="%2$s"%3$s>%4$s <span class="count">(%5$s)</span></a>',
				esc_attr( $slug ),
				esc_url( self::view_url( $slug ) ),
				$slug === $view ? ' class="current" aria-current="page"' : '',
				esc_html( $about['label'] ),
				esc_html( number_format_i18n( WPCPM_Mentor_Application::count_in( $about['states'] ) ) )
			);
		}

		echo implode( ' |</li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is built escaped above.
		echo '</ul><div class="clear"></div>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing here. New applications appear in Waiting.', 'wpcredits-program-manager' ) . '</p>';
			self::render_decided( $view, $found );
			echo '</div>';

			return;
		}

		if ( $cut && 'active' === $view ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: how many rows are drawn, 2: how many there are. */
						__( 'Showing %1$s of %2$s: those still waiting for their Airtable row first, then the most recently made mentors.', 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) ),
						number_format_i18n( $total )
					)
				)
			);
		} elseif ( $cut ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: how many rows are drawn, 2: how many there are. */
						__( 'Showing the oldest %1$s of %2$s. As these are decided, the next take their place.', 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) ),
						number_format_i18n( $total )
					)
				)
			);
		}

		// The row the field found is drawn once, above the views, where the page lands on it, and the list is
		// drawn only when a row remains.
		$listed = self::without( $rows, $found );

		if ( array() !== $listed ) {
			echo '<ol class="wpcpm-queue">';

			foreach ( $listed as $post ) {
				self::render_row( $post, $view );
			}

			echo '</ol>';
		}

		self::render_decided( $view, $found );

		echo '</div>';
	}

	/**
	 * The Find by reference field and, under it, what it found.
	 *
	 * A GET form that changes nothing, so it carries no nonce: the reference typed in it is cleaned
	 * and looked up (`WPCPM_Mentor_Application::by_reference()`), and the application is drawn here
	 * whole, with its decisions, whatever its age or the view it is listed under, when the tab lists it
	 * in any view (`findable_states()`: every state but spam); the form lands on this region, which
	 * holds the row and its anchor, and the lists below skip the row so the page holds its ID once.
	 * Otherwise one neutral sentence says no application was found for the reference, the same for a
	 * reference nobody has and for one filed as spam, and nothing of an application is drawn. Only
	 * `render()`'s capability check lets it be drawn at all.
	 *
	 * @param string $view The view the tab is drawn in, which the form keeps and a decision comes back to.
	 * @return WP_Post|null The application drawn, or null.
	 */
	private static function render_find( $view ) {
		$reference = trim( WPCPM_Request::text( self::FIND_ARG ) );

		printf( '<form method="get" action="#%s" class="wpcpm-mapp-find">', esc_attr( self::FOUND_ANCHOR ) );
		echo '<input type="hidden" name="page" value="wpcpm-mentors" /><input type="hidden" name="tab" value="queue" />';

		// The Waiting view is the default, as `view_url()` has it, and needs no field.
		if ( 'waiting' !== $view ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::VIEW_ARG ), esc_attr( $view ) );
		}

		printf(
			'<label for="wpcpm-mapp-ref">%1$s</label> <input type="search" id="wpcpm-mapp-ref" name="%2$s" value="%3$s" /> <button type="submit" class="button">%4$s</button>',
			esc_html__( 'Find by reference', 'wpcredits-program-manager' ),
			esc_attr( self::FIND_ARG ),
			esc_attr( $reference ),
			esc_html__( 'Find', 'wpcredits-program-manager' )
		);
		echo '</form>';

		if ( '' === $reference ) {
			return null;
		}

		$post = self::found( $reference );

		printf( '<div id="%s">', esc_attr( self::FOUND_ANCHOR ) );

		if ( $post instanceof WP_Post ) {
			echo '<ol class="wpcpm-queue">';
			self::render_row( $post, $view );
			echo '</ol>';
		} else {
			printf( '<p class="description">%s</p>', esc_html__( 'No application was found for that reference.', 'wpcredits-program-manager' ) );
		}

		echo '</div>';

		return $post;
	}

	/**
	 * Recently decided: the rows filed as spam or declined, newest decision first, the fifty newest,
	 * each whole with the decision its state allows. Read in one query, ordered by the decision time the
	 * row carries and bounded at one row past the fifty drawn, so the list can say when it was cut, as
	 * the sponsor screen's does. Retention takes them away in time. The row the Find by reference field drew
	 * above the views is skipped here, so the page holds its ID once.
	 *
	 * @param string       $view  The view the tab is drawn in, which a press here comes back to.
	 * @param WP_Post|null $found The application the field drew, or null.
	 */
	private static function render_decided( $view, $found = null ) {
		$rows = WPCPM_Mentor_Application::decided_posts( self::QUEUE_MAX + 1 );
		$more = count( $rows ) > self::QUEUE_MAX;
		$rows = array_slice( $rows, 0, self::QUEUE_MAX );

		echo '<section class="wpcpm-mapp-decided">';
		printf( '<h3>%s</h3>', esc_html__( 'Recently decided', 'wpcredits-program-manager' ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No application has been filed as spam or declined.', 'wpcredits-program-manager' ) . '</p>';
			echo '</section>';

			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html(
				$more
					? sprintf(
						/* translators: %s: how many rows are listed. */
						__( 'The %s most recent applications filed as spam or declined, newest first. Older ones are listed again as these are put back in the queue or deleted. Put back in the queue returns one filed as spam to Waiting, and sends the acknowledgement to one that never had it; Select is offered on a declined one, for an applicant who appeals.', 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) )
					)
					: __( 'The applications filed as spam and the declined ones, newest first, until they are deleted. Put back in the queue returns one filed as spam to Waiting, and sends the acknowledgement to one that never had it; Select is offered on a declined one, for an applicant who appeals.', 'wpcredits-program-manager' )
			)
		);
		$listed = self::without( $rows, $found );

		if ( array() !== $listed ) {
			echo '<ol class="wpcpm-queue">';

			foreach ( $listed as $post ) {
				self::render_row( $post, $view );
			}

			echo '</ol>';
		}

		echo '</section>';
	}

	/**
	 * One application, whole: who and when, its marks, its answers, its history and its decisions.
	 *
	 * @param WP_Post $post The row.
	 * @param string  $view The view it is drawn in.
	 */
	private static function render_row( WP_Post $post, $view ) {
		$state  = WPCPM_Mentor_Application::state_of( $post );
		$name   = WPCPM_Mentor_Application::name_of( $post );
		$anchor = 'wpcpm-mentor-application-' . (int) $post->ID;

		printf( '<li class="wpcpm-queue-item" id="%s">', esc_attr( $anchor ) );
		printf(
			'<h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">%1$s</span> <span class="wpcpm-inst-muted">%2$s</span></h3>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( WPCPM_Mentor_Application::reference_of( $post ) )
		);
		printf( '<p class="wpcpm-queue-age">%s</p>', esc_html( self::age_line( $post, $state ) ) );

		self::render_marks( $post, $state );

		if ( WPCPM_Mentor_Application::STATE_SELECTED === $state ) {
			self::render_course( $post );
		} elseif ( WPCPM_Mentor_Application::STATE_ACTIVE === $state ) {
			self::render_activation( $post );
		}

		self::render_answers( $post );
		self::render_history( $post );
		self::render_decisions( $post, $state, $view );

		echo '</li>';
	}

	/**
	 * When the row arrived, or for a selected, lapsed, declined or spam row, when that happened: the
	 * decision's own day (`WPCPM_Mentor_Application::decided_at()`), never the day of the newest thing
	 * that happened to the row, which an applicant confirming the address would move. An activated
	 * row says when it was made a mentor, or filed as already a mentor (1.122.33).
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $state Its state.
	 * @return string
	 */
	private static function age_line( WP_Post $post, $state ) {
		$format = (string) get_option( 'date_format', 'F j, Y' );

		if ( WPCPM_Mentor_Application::STATE_SELECTED === $state ) {
			$selected = WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_SELECTED );
			$days     = max( WPCPM_Mentor_Application::LAPSE_FLOOR, (int) WPCPM_Settings::get_value( 'mentor_lapse_days', 90 ) );

			return sprintf(
				/* translators: 1: the day the applicant was selected, 2: the day the application lapses. */
				__( 'Selected on %1$s. It lapses from %2$s if the course has not shown by then.', 'wpcredits-program-manager' ),
				wp_date( $format, $selected ),
				wp_date( $format, $selected + $days * DAY_IN_SECONDS )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_ACTIVE === $state ) {
			$airtable = WPCPM_Mentor_Application::airtable_of( $post );

			if ( isset( $airtable['state'] ) && 'already' === $airtable['state'] ) {
				return sprintf(
					/* translators: %s: the day the application was filed as already a mentor. */
					__( 'Filed as already a mentor on %s.', 'wpcredits-program-manager' ),
					wp_date( $format, self::activated_time( $post ) )
				);
			}

			return sprintf(
				/* translators: %s: the day the applicant was made a mentor. */
				__( 'Made a mentor on %s.', 'wpcredits-program-manager' ),
				wp_date( $format, self::activated_time( $post ) )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_LAPSED === $state ) {
			return sprintf(
				/* translators: %s: the day the application lapsed. */
				__( 'Lapsed on %s.', 'wpcredits-program-manager' ),
				wp_date( $format, WPCPM_Mentor_Application::decided_at( $post ) )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_DECLINED === $state ) {
			return sprintf(
				/* translators: %s: the day the application was declined. */
				__( 'Declined on %s.', 'wpcredits-program-manager' ),
				wp_date( $format, WPCPM_Mentor_Application::decided_at( $post ) )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_SPAM === $state ) {
			return sprintf(
				/* translators: %s: the day the application was filed as spam. */
				__( 'Filed as spam on %s.', 'wpcredits-program-manager' ),
				wp_date( $format, WPCPM_Mentor_Application::decided_at( $post ) )
			);
		}

		$arrived = (int) get_post_time( 'U', true, $post );

		return sprintf(
			/* translators: 1: how long the application has waited, 2: the day it arrived. */
			__( 'Waiting %1$s, since %2$s.', 'wpcredits-program-manager' ),
			human_time_diff( $arrived, time() ),
			wp_date( $format, $arrived )
		);
	}

	/**
	 * The marks a row carries: held and why, a possible duplicate, a mentor already, the address
	 * unconfirmed, and when more information was asked for. A row whose acknowledgement never went
	 * carries one mark about it, and no unconfirmed mark, whose sentence (that the applicant has not
	 * followed the link in the acknowledgement) would contradict it; a held one keeps its held mark,
	 * worded without the claim that the applicant was acknowledged.
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $state Its state.
	 */
	private static function render_marks( WP_Post $post, $state ) {
		$signals = WPCPM_Mentor_Application::signals_of( $post );
		$sent    = self::acknowledged( $post );
		$marks   = array();

		if ( WPCPM_Mentor_Application::STATE_HELD === $state ) {
			$marks[] = array(
				'held',
				__( 'held', 'wpcredits-program-manager' ),
				$sent
					? __( 'The form\'s checks held it for a person to read first. Nobody was refused, and the applicant was acknowledged.', 'wpcredits-program-manager' )
					: __( 'The form\'s checks held it for a person to read first. Nobody was refused.', 'wpcredits-program-manager' ),
			);
		}

		if ( in_array( WPCPM_Mentor_Application_Form::SIGNAL_DUPLICATE, $signals, true ) ) {
			$marks[] = array( 'duplicate', __( 'possible duplicate', 'wpcredits-program-manager' ), __( 'Another application in the queue or waiting for the course has the same address or WordPress.org username. Nothing is ever merged.', 'wpcredits-program-manager' ) );
		}

		if ( in_array( WPCPM_Mentor_Application_Form::SIGNAL_MENTOR, $signals, true ) ) {
			$marks[] = array( 'account', __( 'already a mentor', 'wpcredits-program-manager' ), __( 'A mentor the site knows, current or former, has this address or WordPress.org username.', 'wpcredits-program-manager' ) );
		}

		if ( $sent && ! WPCPM_Mentor_Application::is_confirmed( (int) $post->ID ) ) {
			$marks[] = array( 'unconfirmed', __( 'address not confirmed', 'wpcredits-program-manager' ), __( 'The applicant has not followed the link in the acknowledgement yet, so Select, Ask for more information and Decline wait.', 'wpcredits-program-manager' ) );
		}

		// The form sends no acknowledgement for a row its checks filed as spam, nor past the day's
		// limit on acknowledgements, nor when the mail layer refuses it, so that applicant has no link
		// to confirm the address with until Put back in the queue sends it. The mark says why, once.
		if ( ! $sent ) {
			$marks[] = array( 'unacknowledged', __( 'acknowledgement not sent', 'wpcredits-program-manager' ), self::unacknowledged_sentence( $post ) );
		}

		foreach ( $marks as $mark ) {
			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--%1$s">%2$s</span> %3$s</p>',
				esc_attr( $mark[0] ),
				esc_html( $mark[1] ),
				esc_html( $mark[2] )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_INFO === $state ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the day more information was asked for. */
						__( 'Asked for more information on %s.', 'wpcredits-program-manager' ),
						wp_date( (string) get_option( 'date_format', 'F j, Y' ), WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_INFO ) )
					)
				)
			);
		}
	}

	/**
	 * A selected row's course step (1.122.33): what the Mentor Status Checker last found on the
	 * applicant's profile, or that it has not looked yet; while Make mentors from applications is off,
	 * that the applicant waits for it; and, when an activation could not make the account, when and
	 * why.
	 *
	 * @param WP_Post $post The row.
	 */
	private static function render_course( WP_Post $post ) {
		$format  = (string) get_option( 'date_format', 'F j, Y' );
		$check   = WPCPM_Mentor_Application::last_check( $post );
		$problem = WPCPM_Mentor_Application::problem_of( $post );

		if ( ! WPCPM_Mentor_Activation::enabled() ) {
			printf( '<p class="wpcpm-mapp-waits">%s</p>', esc_html__( 'Making mentors from applications is off until the launch: this applicant waits.', 'wpcredits-program-manager' ) );
		}

		if ( array() === $check ) {
			$line = __( 'Not checked yet: the daily check reads the profile next.', 'wpcredits-program-manager' );
		} elseif ( 'completed' === $check['state'] ) {
			/* translators: %s: the day the profile was last checked. */
			$line = sprintf( __( 'Checked on %s: the course shows on the profile.', 'wpcredits-program-manager' ), wp_date( $format, $check['at'] ) );
		} elseif ( 'not_completed' === $check['state'] ) {
			/* translators: %s: the day the profile was last checked. */
			$line = sprintf( __( 'Checked on %s: the course does not show on the profile yet.', 'wpcredits-program-manager' ), wp_date( $format, $check['at'] ) );
		} else {
			$line = sprintf(
				/* translators: 1: the day the profile was last checked, 2: why it could not be read, as the check said. */
				__( 'Checked on %1$s: the profile could not be read (%2$s).', 'wpcredits-program-manager' ),
				wp_date( $format, $check['at'] ),
				'' !== $check['message'] ? $check['message'] : $check['state']
			);
		}

		printf( '<p class="wpcpm-mapp-check">%s</p>', esc_html( $line ) );

		if ( array() !== $problem ) {
			printf(
				'<p class="wpcpm-mapp-problem">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: the day the account could not be made, 2: why, in the words the account step gave. */
						__( 'The account could not be made on %1$s: %2$s', 'wpcredits-program-manager' ),
						wp_date( $format, $problem['at'] ),
						$problem['message']
					)
				)
			);
		}
	}

	/**
	 * An activated row's lines (1.122.33): the account it made a mentor, the invitation's fate since,
	 * and the Mentors row's in Airtable.
	 *
	 * @param WP_Post $post The row.
	 */
	private static function render_activation( WP_Post $post ) {
		$user = get_userdata( WPCPM_Mentor_Application::user_of( $post ) );

		if ( $user instanceof WP_User ) {
			$link  = (string) get_edit_user_link( (int) $user->ID );
			$named = '' !== $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $user->user_login ) . '</a>' : esc_html( $user->user_login );

			/* translators: %s: the username of the mentor's account, a link to its profile screen. */
			$account = sprintf( esc_html__( 'Account: %s', 'wpcredits-program-manager' ), $named );

			printf( '<p class="wpcpm-mapp-account">%s</p>', $account ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The words are escaped, and the username and its link are built escaped above.
		} else {
			printf( '<p class="wpcpm-mapp-account">%s</p>', esc_html__( 'Account: the account made for this mentor no longer exists.', 'wpcredits-program-manager' ) );
		}

		$invitation = self::invitation_line( $post );

		if ( '' !== $invitation ) {
			printf( '<p class="wpcpm-mapp-invitation">%s</p>', esc_html( $invitation ) );
		}

		$airtable = self::airtable_line( $post );

		if ( '' !== $airtable ) {
			printf( '<p class="wpcpm-mapp-airtable">%s</p>', esc_html( $airtable ) );
		}
	}

	/**
	 * What became of an activated applicant's invitation, which carries the welcome, since the
	 * activation (design, section 6; Review Focus 5). Only what happened at or after the moment the
	 * application was made a mentor counts: a refused or skipped invitation writes no stamp, so the
	 * account may hold an older one, and the mail log may hold an older invitation to the address.
	 *
	 * In order: none for an application filed as already a mentor; the account gone, or an
	 * Administrator's, which is sent none; waiting in the email queue; the newest mentor invitation of
	 * the mail log to the account's address since the activation, sent, refused by the mail host or
	 * not confirmed; past the log's thirty days, the account's stamp since the activation; and
	 * otherwise nothing since, with why when the activation held it for a person to confirm the
	 * account (rulings R5 and P9b), a send the fifteen minutes or another request's lock passed over,
	 * or one refused before the log's thirty days.
	 *
	 * @param WP_Post $post An activated application.
	 * @return string '' when there is nothing to say.
	 */
	public static function invitation_line( WP_Post $post ) {
		$airtable = WPCPM_Mentor_Application::airtable_of( $post );

		if ( isset( $airtable['state'] ) && 'already' === $airtable['state'] ) {
			return '';
		}

		$user = get_userdata( WPCPM_Mentor_Application::user_of( $post ) );

		if ( ! $user instanceof WP_User ) {
			return __( 'The account is gone, so no invitation can reach it.', 'wpcredits-program-manager' );
		}

		if ( in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true ) ) {
			return __( 'No invitation: the account is an Administrator\'s, which signs in already.', 'wpcredits-program-manager' );
		}

		if ( in_array( (int) $user->ID, WPCPM_Mail::queue(), true ) ) {
			return __( 'The invitation with the welcome waits in the email queue.', 'wpcredits-program-manager' );
		}

		$since  = self::activated_time( $post );
		$format = (string) get_option( 'date_format', 'F j, Y' );
		$email  = trim( (string) $user->user_email );
		// An account with no address is asked nothing: the log reads an empty address as no filter.
		$found = '' === $email ? array() : WPCPM_Mail_Log::find(
			array(
				'exact_email' => $email,
				'template'    => self::MAIL_INVITE,
			),
			1,
			1
		);
		$row   = isset( $found['rows'][0] ) && is_array( $found['rows'][0] ) ? $found['rows'][0] : null;
		$at    = null !== $row && isset( $row['sent_at'] ) ? (int) strtotime( (string) $row['sent_at'] . ' UTC' ) : 0;

		if ( null !== $row && $at >= $since ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';

			if ( WPCPM_Mail_Log::STATUS_FAILED === $status ) {
				return sprintf(
					/* translators: %s: the day the invitation was handed to the mail host. */
					__( 'The invitation with the welcome went to the mail host on %s.', 'wpcredits-program-manager' ),
					wp_date( $format, $at )
				) . ' ' . WPCPM_Mail::invite_refused_message( 'administrator' ) . ' ' . __( 'Send invite or Resend invite is on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
			}

			if ( WPCPM_Mail_Log::STATUS_UNCONFIRMED === $status ) {
				return sprintf(
					/* translators: %s: the day the invitation was handed over. */
					__( 'The invitation with the welcome was handed over on %s, and the mail log does not say whether it went.', 'wpcredits-program-manager' ),
					wp_date( $format, $at )
				);
			}

			if ( WPCPM_Mail_Log::STATUS_SENT === $status ) {
				return self::invitation_sent( $at );
			}
		}

		// No row since the activation, as the log keeps thirty days: the account's stamp, which only an
		// invitation that went writes.
		$stamp = (int) WPCPM_Mail::last_invited( (int) $user->ID );

		if ( $stamp > 0 && $stamp >= $since ) {
			return self::invitation_sent( $stamp );
		}

		$linked_by = WPCPM_Mentor_Application::linked_by_of( $post );

		if ( 'login' === $linked_by ) {
			return __( 'No invitation has gone since this applicant was made a mentor: the account was linked by WordPress.org username alone. Confirm it is the same person, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		}

		if ( 'record' === $linked_by ) {
			return __( 'No invitation has gone since this applicant was made a mentor: another account also answered for the applicant. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
		}

		return __( 'No invitation has gone since this applicant was made a mentor. Send invite or Resend invite is on its row in the Mentors screen\'s Accounts tab.', 'wpcredits-program-manager' );
	}

	/**
	 * The invitation line for one that went.
	 *
	 * @param int $at When, a Unix time.
	 * @return string
	 */
	private static function invitation_sent( $at ) {
		return sprintf(
			/* translators: %s: the day the invitation was sent. */
			__( 'The invitation with the welcome was sent on %s.', 'wpcredits-program-manager' ),
			wp_date( (string) get_option( 'date_format', 'F j, Y' ), (int) $at )
		);
	}

	/**
	 * What became of an activated applicant's Mentors row in Airtable, then what was left out of it
	 * when the base did not hold a value or a column.
	 *
	 * @param WP_Post $post An activated application.
	 * @return string '' when the activation kept nothing of it.
	 */
	private static function airtable_line( WP_Post $post ) {
		$row = WPCPM_Mentor_Application::airtable_of( $post );

		if ( array() === $row ) {
			return '';
		}

		$day = wp_date( (string) get_option( 'date_format', 'F j, Y' ), $row['at'] );

		switch ( $row['state'] ) {
			case 'created':
				/* translators: %s: the day the Mentors row was written. */
				$line = sprintf( __( 'Airtable row written on %s.', 'wpcredits-program-manager' ), $day );
				break;

			case 'reactivated':
				/* translators: %s: the day the mentor's own Mentors row was set back to Active. */
				$line = sprintf( __( 'The mentor\'s own Airtable row was set back to Active on %s.', 'wpcredits-program-manager' ), $day );
				break;

			case 'adopted':
				/* translators: %s: the day the Mentors row that held the address was set back to Active and linked. */
				$line = sprintf( __( 'The Mentors row that held this address was set back to Active and linked on %s.', 'wpcredits-program-manager' ), $day );
				break;

			case 'found':
				$line = __( 'The account holds its Airtable record, linked by the mentors sync or by hand; nothing was written from here.', 'wpcredits-program-manager' );
				break;

			case 'gone':
				$line = __( 'The account made for this mentor no longer exists, so no Airtable row is written for it.', 'wpcredits-program-manager' );
				break;

			case 'already':
				$line = __( 'A mentor already: nothing was written.', 'wpcredits-program-manager' );
				break;

			case 'refused':
				/* translators: 1: the day Airtable refused the Mentors row, 2: Airtable's words, or the site's, for why. */
				$line = sprintf( __( 'Airtable refused the row on %1$s: %2$s', 'wpcredits-program-manager' ), $day, $row['error'] );
				break;

			default:
				$line = __( 'The Airtable row has not been written yet.', 'wpcredits-program-manager' );
		}

		$left_out = WPCPM_Mentor_Airtable_Row::left_out_sentence( $row['left_out'] );

		return '' !== $left_out ? $line . ' ' . $left_out : $line;
	}

	/**
	 * When an application was made a mentor: the moment the activation kept, or, for one that keeps
	 * none, its newest activation in the history.
	 *
	 * @param WP_Post $post An activated application.
	 * @return int A Unix time, or 0.
	 */
	private static function activated_time( WP_Post $post ) {
		$at = (int) get_post_meta( (int) $post->ID, WPCPM_Mentor_Application::META_ACTIVATED_AT, true );

		if ( $at > 0 ) {
			return $at;
		}

		return max( WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_ACTIVATED ), WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_ALREADY_MENTOR ) );
	}

	/**
	 * Whether an activated row is offered Write the Airtable row again: its Mentors row still to be
	 * written, refused or never finished, and its account still there and still waiting for it. One
	 * the mentors sync linked meanwhile waits for nothing, and one whose account is gone has nothing
	 * to write for.
	 *
	 * @param WP_Post $post An activated application.
	 * @return bool
	 */
	private static function retry_offered( WP_Post $post ) {
		if ( ! WPCPM_Mentor_Application::row_unwritten( $post ) ) {
			return false;
		}

		$user_id = WPCPM_Mentor_Application::user_of( $post );

		return get_userdata( $user_id ) instanceof WP_User && '' !== (string) get_user_meta( $user_id, WPCPM_Mentor_Provision::USER_ROW_PENDING, true );
	}

	/**
	 * Every answer, in the order the form asked, each printed escaped: a stranger's words on an
	 * Administrator's screen. The profile is a link, with the username beside it.
	 *
	 * @param WP_Post $post The row.
	 */
	private static function render_answers( WP_Post $post ) {
		$fields = WPCPM_Mentor_Application::fields_of( $post );

		echo '<table class="widefat striped wpcpm-list wpcpm-app-answers"><tbody>';

		foreach ( WPCPM_Mentor_Application_Form::fields() as $key => $spec ) {
			if ( 'consent' === $spec['type'] ) {
				continue;
			}

			$answer = isset( $fields[ $key ] ) ? $fields[ $key ] : '';
			$label  = $spec['label'];

			if ( 'profile' === $spec['type'] && '' !== (string) $answer ) {
				$cell = sprintf( '<a href="%1$s" rel="noopener">%2$s</a>', esc_url( WPCPM_Mentor_Application::profile_url( (string) $answer ) ), esc_html( (string) $answer ) );
			} elseif ( 'email' === $spec['type'] && '' !== (string) $answer ) {
				$cell = esc_html(
					sprintf(
						WPCPM_Mentor_Application::is_confirmed( (int) $post->ID )
							/* translators: %s: an address. */
							? __( '%s (confirmed)', 'wpcredits-program-manager' )
							/* translators: %s: an address. */
							: __( '%s (not confirmed yet)', 'wpcredits-program-manager' ),
						(string) $answer
					)
				);
			} elseif ( 'statement' === $spec['type'] ) {
				$cell = esc_html( true === $answer ? __( 'Agreed', 'wpcredits-program-manager' ) : __( 'Not agreed', 'wpcredits-program-manager' ) );
			} else {
				$text = is_array( $answer ) ? implode( ', ', array_map( 'strval', $answer ) ) : (string) $answer;
				$cell = '' !== trim( $text ) ? nl2br( esc_html( $text ) ) : '<span class="wpcpm-inst-muted">' . esc_html__( 'no answer', 'wpcredits-program-manager' ) . '</span>';
			}

			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), $cell ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each cell is built escaped above.
		}

		echo '</tbody></table>';
	}

	/**
	 * The row's history: each decision with its day, who took it and the note they wrote.
	 *
	 * @param WP_Post $post The row.
	 */
	private static function render_history( WP_Post $post ) {
		$events   = WPCPM_Mentor_Application::events( (int) $post->ID );
		$airtable = WPCPM_Mentor_Application::airtable_of( $post );
		$words    = self::event_words( isset( $airtable['state'] ) && 'gone' === $airtable['state'] ? 'gone' : 'found' );
		$format   = (string) get_option( 'date_format', 'F j, Y' );

		printf( '<h4>%s</h4>', esc_html__( 'History', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-app-history">';

		foreach ( $events as $row ) {
			$who = $row['actor'] > 0 ? get_userdata( $row['actor'] ) : null;

			printf(
				'<li>%1$s%2$s</li>',
				esc_html(
					sprintf(
						/* translators: 1: a day, 2: what happened, 3: who did it. */
						__( '%1$s: %2$s, %3$s.', 'wpcredits-program-manager' ),
						wp_date( $format, $row['at'] ),
						isset( $words[ $row['event'] ] ) ? $words[ $row['event'] ] : $row['event'],
						$who instanceof WP_User ? $who->display_name : ( $row['actor'] > 0 ? __( 'an Administrator', 'wpcredits-program-manager' ) : __( 'by the site', 'wpcredits-program-manager' ) )
					)
				),
				'' !== $row['note'] ? '<br />' . nl2br( esc_html( $row['note'] ) ) : ''
			);
		}

		echo '</ul>';
	}

	/**
	 * What the history calls each event, in the Administrator's words.
	 *
	 * The Mentors row settled with nothing written from here (ruling R2) is worded by how it settled,
	 * which the row's kept Airtable state says: an application settles once, since a settled row is no
	 * longer one to write.
	 *
	 * @param string $settled How the application's Mentors row settled: `found` or `gone`.
	 * @return array<string, string>
	 */
	private static function event_words( $settled = 'found' ) {
		return array(
			WPCPM_Mentor_Application::EVENT_SUBMITTED      => __( 'applied', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_CONFIRMED      => __( 'confirmed the address', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SELECTED       => __( 'selected', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_INFO           => __( 'asked for more information', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SECOND         => __( 'asked for a second opinion', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_DECLINED       => __( 'declined', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SPAM           => __( 'marked as spam', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_WITHDRAWN      => __( 'withdrawn', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_REOPENED       => __( 'put back in the queue', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED => __( 'put back in the queue, acknowledgement sent', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_ACKNOWLEDGED   => __( 'sent the acknowledgement', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_LAPSED         => __( 'lapsed', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_ACTIVATED      => __( 'made a mentor', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_ALREADY_MENTOR => __( 'filed as already a mentor', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_AIRTABLE_WRITTEN => __( 'wrote the Airtable row', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_AIRTABLE_REFUSED => __( 'Airtable refused the row', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_AIRTABLE_SETTLED => 'gone' === $settled
				? __( 'the account is gone, so nothing more is written for it', 'wpcredits-program-manager' )
				: __( 'the row was linked by the mentors sync or by hand', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The decisions the row's state allows, each its own form.
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $state Its state.
	 * @param string  $view  The view it is drawn in, which a press comes back to.
	 */
	private static function render_decisions( WP_Post $post, $state, $view ) {
		$name = WPCPM_Mentor_Application::name_of( $post );
		$name = '' !== $name ? $name : WPCPM_Mentor_Application::reference_of( $post );
		$open = in_array( $state, WPCPM_Mentor_Application::open_states(), true );
		$kept = WPCPM_Typed_Text::box_form( self::TYPED, (int) $post->ID );

		// First, since Select, Ask for more information and Decline wait on the link it carries.
		if ( $open && ! self::acknowledged( $post ) && '' !== WPCPM_Mentor_Application::email_of( $post ) ) {
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_ACKNOWLEDGE,
					'label'   => __( 'Send the acknowledgement', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Send %s the acknowledgement, with the link that confirms the address?', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( in_array( $state, self::select_states(), true ) ) {
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_SELECT,
					'label'   => __( 'Select', 'wpcredits-program-manager' ),
					'class'   => 'button button-primary',
					'note'    => __( 'A note for the next Administrator, optional. It is never sent.', 'wpcredits-program-manager' ),
					'kept'    => WPCPM_Typed_Text::kept_box( $kept, 'select' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Select %s and send the selection email with the mentors\' course invitation link?', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( $open ) {
			self::render_form(
				$post,
				$view,
				array(
					'action' => self::ACTION_INFO,
					'label'  => __( 'Ask for more information', 'wpcredits-program-manager' ),
					'note'   => __( 'What you are asking about, for the next Administrator, optional. The email is the template\'s; this is never sent.', 'wpcredits-program-manager' ),
					'kept'   => WPCPM_Typed_Text::kept_box( $kept, 'info' ),
				)
			);
			self::render_form(
				$post,
				$view,
				array(
					'action' => self::ACTION_SECOND,
					'label'  => __( 'Second opinion', 'wpcredits-program-manager' ),
					'note'   => __( 'What a colleague should look at. Needed; nothing is sent to the applicant.', 'wpcredits-program-manager' ),
					'needed' => true,
					'kept'   => WPCPM_Typed_Text::kept_box( $kept, 'second' ),
				)
			);
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_DECLINE,
					'label'   => __( 'Decline', 'wpcredits-program-manager' ),
					'note'    => __( 'Why, for the next Administrator, optional. It is never sent.', 'wpcredits-program-manager' ),
					'kept'    => WPCPM_Typed_Text::kept_box( $kept, 'decline' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Decline the application from %s and send the decline email?', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_SPAM,
					'label'   => __( 'Mark as spam', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Mark the application from %s as spam? Nothing is sent to the address on it.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( WPCPM_Mentor_Application::STATE_SPAM === $state ) {
			self::render_form(
				$post,
				$view,
				array(
					'action' => self::ACTION_REOPEN,
					'label'  => __( 'Put back in the queue', 'wpcredits-program-manager' ),
				)
			);
		}

		// While Make mentors from applications is off the step refuses, so the press is not offered and
		// the row says the applicant waits (`render_course()`).
		if ( WPCPM_Mentor_Application::STATE_SELECTED === $state && WPCPM_Mentor_Activation::enabled() ) {
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_COMPLETE,
					'label'   => __( 'The course is completed', 'wpcredits-program-manager' ),
					'class'   => 'button button-primary',
					'note'    => __( 'How you know, for the next Administrator, optional. It is never sent.', 'wpcredits-program-manager' ),
					'kept'    => WPCPM_Typed_Text::kept_box( $kept, 'complete' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Make %s a mentor now? It makes or links their account, writes or reactivates their Airtable row, and queues the invitation with the welcome unless the account must be confirmed first or is an Administrator\'s. If Airtable refuses the row, they are a mentor all the same. An applicant who is a mentor already is only filed as one.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( WPCPM_Mentor_Application::STATE_ACTIVE === $state && self::retry_offered( $post ) ) {
			self::render_form(
				$post,
				$view,
				array(
					'action' => self::ACTION_AIRTABLE,
					'label'  => __( 'Write the Airtable row again', 'wpcredits-program-manager' ),
				)
			);
		}

		if ( WPCPM_Mentor_Application::STATE_SELECTED === $state ) {
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_WITHDRAW,
					'label'   => __( 'Withdraw', 'wpcredits-program-manager' ),
					'note'    => __( 'Why: what the applicant wrote. Needed; nothing is sent to them.', 'wpcredits-program-manager' ),
					'needed'  => true,
					'kept'    => WPCPM_Typed_Text::kept_box( $kept, 'withdraw' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Withdraw %s? The application moves to Lapsed and nothing is sent.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}
	}

	/**
	 * One decision's form: the nonce keyed to the decision and the row, the view to come back to, a
	 * note box where the decision takes one, and the button, which asks first where `confirm` says.
	 *
	 * @param WP_Post $post The row.
	 * @param string  $view The view to come back to.
	 * @param array   $args `action`, `label`, `class`, `note` (the box's label, or '' for no box),
	 *                      `needed`, `kept` (a refused press's typing, or null) and `confirm`.
	 */
	private static function render_form( WP_Post $post, $view, array $args ) {
		$args = array_merge(
			array(
				'action'  => '',
				'label'   => '',
				'class'   => 'button',
				'note'    => '',
				'needed'  => false,
				'kept'    => null,
				'confirm' => '',
			),
			$args
		);
		$box  = $args['action'] . '-' . (int) $post->ID;

		printf(
			'<form class="wpcpm-app-action" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s"%3$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Working', 'wpcredits-program-manager' ),
			'' !== $args['confirm'] ? ' data-wpcpm-confirm="' . esc_attr( $args['confirm'] ) . '"' : ''
		);
		wp_nonce_field( $args['action'] . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_APPLICATION ), (int) $post->ID );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::VIEW_ARG ), esc_attr( $view ) );

		if ( '' !== $args['note'] ) {
			printf(
				'<p class="wpcpm-app-action__note"><label for="%1$s">%2$s</label><br /><textarea id="%1$s" name="%3$s" rows="2" maxlength="%4$d"%5$s>%6$s</textarea></p>',
				esc_attr( $box ),
				esc_html( $args['note'] ),
				esc_attr( self::FIELD_NOTE ),
				(int) WPCPM_Typed_Text::drawn_limit( '', self::MAX_NOTE ),
				$args['needed'] ? ' minlength="' . (int) self::MIN_NOTE . '" required="required"' : '',
				// The parser drops one line feed right after the opening tag, so kept typing that
				// begins with a line break keeps it.
				is_string( $args['kept'] ) ? "\n" . esc_textarea( $args['kept'] ) : ''
			);
		}

		printf( '<button type="submit" class="%1$s">%2$s</button>', esc_attr( $args['class'] ), esc_html( $args['label'] ) );
		echo '</form>';
	}
}
