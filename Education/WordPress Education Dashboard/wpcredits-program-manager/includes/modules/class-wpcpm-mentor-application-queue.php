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
 * section 5): the Mentors screen's Waiting for review tab, in five views, each row whole, and the
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
 *
 * A decision that emails the applicant refuses an address nobody has confirmed through the
 * acknowledgement's link, as the institution approval does, and Select refuses while no course
 * invitation link is set. The email goes before the state moves: a send that fails leaves the row as
 * it was. Every decision is a press by an Administrator, the capability asked first and then the
 * nonce keyed to the decision and the row, and is written to the row's history with who and when.
 */
final class WPCPM_Mentor_Application_Queue {

	/** The seven decisions, one action apiece, so a nonce from one form cannot press another. */
	const ACTION_SELECT   = 'wpcpm_mapp_select';
	const ACTION_INFO     = 'wpcpm_mapp_info';
	const ACTION_SECOND   = 'wpcpm_mapp_second';
	const ACTION_DECLINE  = 'wpcpm_mapp_decline';
	const ACTION_SPAM     = 'wpcpm_mapp_spam';
	const ACTION_WITHDRAW = 'wpcpm_mapp_withdraw';
	const ACTION_REOPEN   = 'wpcpm_mapp_reopen';

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

	/** Most rows a view draws: the oldest ones, whose turn it is. */
	const QUEUE_MAX = 50;

	/** The flash channel the Mentors screen reads its outcomes from. */
	const FLASH = 'mentors_admin';

	/**
	 * Hooks: the seven decisions, for a signed-in Administrator alone (`admin_post_`, never `nopriv`).
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
			self::ACTION_SELECT   => 'handle_select',
			self::ACTION_INFO     => 'handle_info',
			self::ACTION_SECOND   => 'handle_second',
			self::ACTION_DECLINE  => 'handle_decline',
			self::ACTION_SPAM     => 'handle_spam',
			self::ACTION_WITHDRAW => 'handle_withdraw',
			self::ACTION_REOPEN   => 'handle_reopen',
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
	 * The tab's five views, slug => the states each lists and its label, in the bar's order: the
	 * queue itself, the two kinds of row in it a person may look for, then the two the queue has let
	 * go of, the selected ones waiting for the course and the lapsed ones.
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
	 * @param string  $key    The decision's own box (`select`, `info`, `second`, `decline`, `withdraw`).
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
	 */
	public static function handle_withdraw() {
		$post = self::begin( self::ACTION_WITHDRAW, array( WPCPM_Mentor_Application::STATE_SELECTED ) );
		$note = self::note( $post, 'withdraw', true );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_LAPSED, WPCPM_Mentor_Application::EVENT_WITHDRAWN, get_current_user_id(), $note );

		self::leave( 'mapp-withdrawn' );
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
	 * or one whose hand-off the mail layer refused, until Put back in the queue sends it.
	 *
	 * @param WP_Post $post The row.
	 * @return bool
	 */
	private static function acknowledged( WP_Post $post ) {
		return array() === self::withheld( $post ) || 0 < WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED );
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

		return $because . ' ' . __( 'The applicant has no link to confirm the address with, so Select, Ask for more information and Decline wait. Put back in the queue sends it to an application filed as spam, once.', 'wpcredits-program-manager' );
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
	 * Back to the tab with a one-shot outcome, at the view the press was made on.
	 *
	 * @param string $status A key of `messages()`.
	 */
	private static function leave( $status ) {
		$view = WPCPM_Request::posted_key( self::VIEW_ARG, 'waiting' );

		WPCPM_Flash::set( self::FLASH, $status );
		wp_safe_redirect( self::view_url( isset( self::views()[ $view ] ) ? $view : 'waiting' ) );
		exit;
	}

	/**
	 * What each press can leave, in the Administrator's words.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	public static function messages() {
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
	 */
	public static function render() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$view  = self::current_view();
		$views = self::views();
		$total = WPCPM_Mentor_Application::count_in( $views[ $view ]['states'] );
		$rows  = WPCPM_Mentor_Application::applications( $views[ $view ]['states'], self::QUEUE_MAX );

		echo '<div class="wpcpm-card" id="wpcpm-mentor-applications">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Mentor applications', 'wpcredits-program-manager' ), esc_html( number_format_i18n( WPCPM_Mentor_Application::waiting_count() ) ) );
		echo '<p class="description">' . esc_html__( 'People who applied to mentor through the form on the site, oldest first. Select sends the mentors\' course invitation link; Ask for more information and Decline send their emails; Second opinion, Mark as spam and Withdraw send nothing. A decision that emails the applicant waits until they have confirmed the address.', 'wpcredits-program-manager' ) . '</p>';

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
			self::render_decided( $view );
			echo '</div>';

			return;
		}

		if ( count( $rows ) < $total ) {
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

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $post ) {
			self::render_row( $post, $view );
		}

		echo '</ol>';

		self::render_decided( $view );

		echo '</div>';
	}

	/**
	 * Recently decided: the rows filed as spam or declined, newest decision first, the fifty newest,
	 * each whole with the decision its state allows. Read in one query, ordered by the decision time the
	 * row carries and bounded at one row past the fifty drawn, so the list can say when it was cut, as
	 * the sponsor screen's does. Retention takes them away in time.
	 *
	 * @param string $view The view the tab is drawn in, which a press here comes back to.
	 */
	private static function render_decided( $view ) {
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
		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $post ) {
			self::render_row( $post, $view );
		}

		echo '</ol></section>';
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
		self::render_answers( $post );
		self::render_history( $post );
		self::render_decisions( $post, $state, $view );

		echo '</li>';
	}

	/**
	 * When the row arrived, or for a selected, lapsed, declined or spam row, when that happened: the
	 * decision's own day (`WPCPM_Mentor_Application::decided_at()`), never the day of the newest thing
	 * that happened to the row, which an applicant confirming the address would move.
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
		$events = WPCPM_Mentor_Application::events( (int) $post->ID );
		$words  = self::event_words();
		$format = (string) get_option( 'date_format', 'F j, Y' );

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
	 * @return array<string, string>
	 */
	private static function event_words() {
		return array(
			WPCPM_Mentor_Application::EVENT_SUBMITTED => __( 'applied', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_CONFIRMED => __( 'confirmed the address', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SELECTED  => __( 'selected', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_INFO      => __( 'asked for more information', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SECOND    => __( 'asked for a second opinion', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_DECLINED  => __( 'declined', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SPAM      => __( 'marked as spam', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_WITHDRAWN => __( 'withdrawn', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_REOPENED  => __( 'put back in the queue', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED => __( 'put back in the queue, acknowledgement sent', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_LAPSED    => __( 'lapsed', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_ACTIVATED => __( 'made a mentor', 'wpcredits-program-manager' ),
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
