<?php
/**
 * The Institution Dashboard - a student's welcome invitation, sent again from their card.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The press that sends a student's welcome invitation again from the Institution Dashboard.
 *
 * **Who.** Every member of the student's own institution, on the member ground, and an
 * Administrator who opened the institution's dashboard through the switcher, on the manager
 * ground: `decide( ACT_RESEND_INVITE )` on the student's account stamp, as reading the card is
 * decided. Everybody else gets the policy's one refusal, which says nothing about whether the
 * student exists. The decision has to name a well-formed institution record as well, because
 * the day's allowance is counted under it and the history row is filed under it: the manager
 * ground carries an account with no stamp, and a send that could be counted and recorded
 * nowhere is a send this class does not make.
 *
 * **For whom.** An account that holds the Student role and has no recorded sign-in
 * (`WPCPM_Sign_Ins::has_signed_in()`). A student who has signed in once has WordPress's own
 * password reset, so the card draws no control for them, and a press from a page drawn before
 * they signed in is refused.
 *
 * **The two limits.** `WPCPM_Mail::may_invite()`'s fifteen minutes per student, because each
 * invitation cancels the link in the one before it, and `PER_DAY` sends a day for each
 * institution, through `WPCPM_Ceiling`. The day's place is claimed only once every refusal
 * before it has passed, and given back when the send answers an error or the host refuses its
 * email, so a press that sends nothing takes none of the school's twenty. Two presses for one
 * student at the same moment both pass the fifteen minutes before either has stamped; the send
 * takes the student's lock (`WPCPM_Mail::send_invite_now()`), so the press that reaches it second
 * is refused there as the fifteen minutes refuse it, and gives its place back.
 *
 * **The send.** `WPCPM_Students_Sync::send_invite()`, the one Administrators press in wp-admin:
 * the site's own welcome invitation with a new link, its time stamped and its row in the email
 * log. Nobody else is emailed. An invitation the mail host refuses is no send, as the mail layer
 * hears it for every route (`WPCPM_Mail::send_invite_now()`: WordPress's `wp_mail_failed`, counted
 * only for a failure addressed to the student): it is not stamped, so the card's "Invitation last
 * sent" stays the last one that went and the school may press again at once, though that one's
 * link no longer works, since core made the new key before the email was refused, which the notice
 * says; the place is given back and no history row is written. Another email failing while the
 * invitation is on its way, one another plugin sends from a filter, leaves the invitation sent.
 *
 * **The record.** One history row per send (`WPCPM_Institution_Audit::record()`): the
 * institution, the student's account, who pressed and on which ground.
 *
 * **The line.** On the student's card, under the name, for a student with no sign-in on record:
 * the day the site began recording sign-ins, when the last invitation went, and one button. The
 * sign-in record is read for one question here, whether a sign-in is on record for the student,
 * and no date of a sign-in is shown.
 */
final class WPCPM_Institution_Resend {

	/** The one press. Nonce keyed to the student's account. */
	const ACTION = 'wpcpm_institution_resend_invite';

	/** The posted field naming the student's account. Nothing else in the form is read. */
	const FIELD_USER = 'user';

	/** Flash channel. Carries the account it is about, so one card prints it and no other. */
	const FLASH = 'institution_resend';

	/** The line's anchor, so a press lands back on the line it came from. */
	const ANCHOR = 'wpcpm-student-invite';

	/** Audit kind: a student's welcome invitation, sent from the Institution Dashboard. */
	const KIND = 'student-invite-resent';

	/** How many invitations one institution may send in a day. */
	const PER_DAY = 20;

	/** The day's allowance is counted under this prefix and the institution's record ID. */
	const CEILING = 'institution-resend:';

	/** Outcome: sent, replacing the link of an earlier invitation. */
	const OUT_RESENT = 'invite-resent';

	/** Outcome: sent, the first invitation the account was ever sent. */
	const OUT_SENT = 'invite-sent';

	/** Outcome: the last invitation went less than fifteen minutes ago, or another is being sent, so nothing was sent. */
	const OUT_TOO_SOON = 'invite-too-soon';

	/** Outcome: the institution has sent `PER_DAY` today, so nothing was sent. */
	const OUT_CEILING = 'invite-ceiling';

	/** Outcome: the student signed in after the card was drawn, so nothing was sent. */
	const OUT_SIGNED_IN = 'invite-signed-in';

	/** Outcome: the account cannot be sent an invitation, or the send answered an error. */
	const OUT_ERROR = 'invite-error';

	/** Outcome: the mail host refused the invitation, so nothing was sent and the earlier link is gone. */
	const OUT_MAIL_REFUSED = 'invite-mail-refused';

	/** Outcome: the policy refused the press. */
	const OUT_REFUSED = 'invite-refused';

	/** Outcome: sent, and the history row would not insert. */
	const OUT_UNLOGGED = 'invite-unlogged';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_resend' ) );
	}

	/*
	 * The line on the card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The sign-in line for one student's card, and the outcome of the last press on it.
	 *
	 * Nothing at all for a reader the policy refuses, never a disabled control with a reason
	 * beside it. The flash is taken first, so a sentence meant for this card is spent by the
	 * render that could have printed it rather than waiting in user meta for a later one. A
	 * student with a sign-in on record, an account that no longer holds the Student role, and an
	 * account whose address is no address (the press refuses it) get no line and no button; the
	 * outcome of a press on their card is still printed. No student gets the line while the site
	 * keeps no sign-in record (`WPCPM_Sign_Ins::since()` is 0 until the record begins), because
	 * the line names the day it began.
	 *
	 * @param WP_User $student The student whose card this is.
	 */
	public static function render( WP_User $student ) {
		$user_id  = (int) $student->ID;
		$flash    = self::flash_for( $user_id );
		$decision = self::decision( $user_id );

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		$line = WPCPM_Sign_Ins::since() > 0
			&& WPCPM_Roles::user_has_role( $student, WPCPM_Roles::ROLE_STUDENT )
			&& is_email( (string) $student->user_email )
			&& ! WPCPM_Sign_Ins::has_signed_in( $user_id );

		if ( ! $line && empty( $flash ) ) {
			return;
		}

		printf( '<div class="wpcpm-institution__invite" id="%s">', esc_attr( self::ANCHOR ) );

		if ( ! empty( $flash ) ) {
			printf(
				'<p class="wpcpm-institution__invite-message is-%1$s" role="status">%2$s</p>',
				esc_attr( $flash['tone'] ),
				esc_html( $flash['text'] )
			);
		}

		if ( $line ) {
			self::render_line( $user_id );
		}

		echo '</div>';
	}

	/**
	 * The sentence and the button.
	 *
	 * Two dates, both in the site's date format: the day the site began recording sign-ins
	 * (`WPCPM_Sign_Ins::since()`), since the site can speak of no sign-in before it, and the last
	 * invitation by any route (`WPCPM_Mail::last_invited()`). The form is locked at its first press
	 * and says it is sending, as the plugin's other one-press forms do, and its nonce is keyed to
	 * the student's account, so a form drawn for one student is no use for another.
	 *
	 * @param int $user_id The student's account.
	 */
	private static function render_line( $user_id ) {
		$user_id = (int) $user_id;
		$last    = WPCPM_Mail::last_invited( $user_id );
		$format  = (string) get_option( 'date_format' );
		$since   = wp_date( $format, WPCPM_Sign_Ins::since() );

		if ( $last > 0 ) {
			$note = sprintf(
				/* translators: 1: the day the site began recording sign-ins, 2: the date the last invitation was sent, both in the site's date format. */
				__( 'No sign-in on record since %1$s. Invitation last sent %2$s.', 'wpcredits-program-manager' ),
				$since,
				wp_date( $format, $last )
			);
		} else {
			$note = sprintf(
				/* translators: %s: the day the site began recording sign-ins, in the site's date format. */
				__( 'No sign-in on record since %s. No invitation sent yet.', 'wpcredits-program-manager' ),
				$since
			);
		}

		printf( '<p class="wpcpm-institution__invite-note">%s</p>', esc_html( $note ) );

		printf(
			'<form class="wpcpm-institution__invite-form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending...', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION . '_' . $user_id );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_USER ), (int) $user_id );

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html( $last > 0 ? __( 'Resend invitation', 'wpcredits-program-manager' ) : __( 'Send invitation', 'wpcredits-program-manager' ) )
		);

		echo '</form>';
	}

	/**
	 * The last press's outcome when it is about this student, as its tone and its sentence.
	 *
	 * @param int $user_id The student whose card is being drawn.
	 * @return array{tone?: string, text?: string} Empty when there is nothing to print here.
	 */
	private static function flash_for( $user_id ) {
		$flash   = WPCPM_Flash::take( self::FLASH );
		$outcome = is_array( $flash ) && isset( $flash['outcome'] ) ? sanitize_key( (string) $flash['outcome'] ) : '';
		$about   = is_array( $flash ) && isset( $flash['user'] ) ? (int) $flash['user'] : 0;
		$detail  = is_array( $flash ) && isset( $flash['detail'] ) && is_scalar( $flash['detail'] ) ? (string) $flash['detail'] : '';

		// Routing, not authorization: which card prints a sentence, never who may read one.
		if ( '' === $outcome || $about !== (int) $user_id ) {
			return array();
		}

		$messages = self::messages( $detail );

		if ( ! isset( $messages[ $outcome ] ) ) {
			return array();
		}

		return array(
			'tone' => $messages[ $outcome ][0],
			'text' => $messages[ $outcome ][1],
		);
	}

	/*
	 * What the card says
	 * --------------------------------------------------------------------
	 */

	/**
	 * What each outcome says, with the student's masked address where a sentence names it.
	 *
	 * Every refusal says that nothing was sent, because the reader has just pressed a button and
	 * cannot see the server. The fifteen minutes are `WPCPM_Mail::too_soon_message()`, the
	 * sentence every route's refusal gives; a failed send is `WPCPM_Mail::invite_failed_message()`,
	 * the sentence the Resend invite in wp-admin prints; an email the mail host refused is
	 * `WPCPM_Mail::invite_refused_message()`, worded for an institution's members.
	 *
	 * @param string $address The student's address, masked by `WPCPM_Mail::mask_address()`.
	 * @return array<string, array{0: string, 1: string}> Outcome to its tone and sentence.
	 */
	public static function messages( $address = '' ) {
		$address = (string) $address;

		return array(
			self::OUT_RESENT       => array(
				'success',
				sprintf(
					/* translators: %s: the student's email address, masked, such as a***@example.org. */
					__( 'The invitation is on its way to %s. The new link replaces the one in the earlier email.', 'wpcredits-program-manager' ),
					$address
				),
			),
			self::OUT_SENT         => array(
				'success',
				sprintf(
					/* translators: %s: the student's email address, masked, such as a***@example.org. */
					__( 'The invitation is on its way to %s.', 'wpcredits-program-manager' ),
					$address
				),
			),
			self::OUT_TOO_SOON     => array( 'warning', WPCPM_Mail::too_soon_message() ),
			self::OUT_CEILING      => array(
				'error',
				sprintf(
					/* translators: %d: how many invitations an institution may send in a day. */
					_n(
						'Your institution has sent %d invitation today, the most a day allows, so nothing was sent. Try again tomorrow, or write to the program team.',
						'Your institution has sent %d invitations today, the most a day allows, so nothing was sent. Try again tomorrow, or write to the program team.',
						self::PER_DAY,
						'wpcredits-program-manager'
					),
					self::PER_DAY
				),
			),
			self::OUT_SIGNED_IN    => array( 'info', __( 'This student has signed in since the page was drawn, so nothing was sent.', 'wpcredits-program-manager' ) ),
			self::OUT_ERROR        => array( 'error', WPCPM_Mail::invite_failed_message() ),
			self::OUT_MAIL_REFUSED => array( 'error', WPCPM_Mail::invite_refused_message( 'member' ) ),
			self::OUT_REFUSED      => array( 'error', WPCPM_Institution_Policy::refusal()->get_error_message() ),
			self::OUT_UNLOGGED     => array(
				'error',
				sprintf(
					/* translators: %s: the student's email address, masked, such as a***@example.org. */
					__( 'The invitation is on its way to %s, but the entry saying who sent it could not be written, so it is not in this institution\'s history. Tell your program contact.', 'wpcredits-program-manager' ),
					$address
				),
			),
		);
	}

	/*
	 * The press
	 * --------------------------------------------------------------------
	 */

	/**
	 * Send one student's welcome invitation again, and say what came of it on their card.
	 *
	 * The order is the rule. The nonce first, keyed to the account the form names. Then the
	 * policy. Then the student, asked again here rather than taken from the page: an account
	 * holding the Student role, with no recorded sign-in and an address to send to. Then the
	 * fifteen minutes, then the day's place, claimed only now, so no refusal before it spends
	 * one. Then the send, which gives the place back when it answers an error, its email refused
	 * among them, and the history row. Every step ends in `bounce()`, which does not return.
	 */
	public static function handle_resend() {
		// Read to key the token and for nothing else: whose student this is, and whether the
		// invitation may go, are decided below.
		$user_id = WPCPM_Request::posted_id( self::FIELD_USER );

		check_admin_referer( self::ACTION . '_' . $user_id );

		$decision = self::decision( $user_id );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( self::OUT_REFUSED, $user_id );
		}

		$student = self::student( $user_id );

		if ( ! $student instanceof WP_User ) {
			self::bounce( self::OUT_ERROR, $user_id );
		}

		if ( WPCPM_Sign_Ins::has_signed_in( $user_id ) ) {
			self::bounce( self::OUT_SIGNED_IN, $user_id );
		}

		// An account with no address it could be sent to is refused before the day's place is
		// claimed, rather than told an invitation is on its way to nobody.
		if ( ! is_email( (string) $student->user_email ) ) {
			self::bounce( self::OUT_ERROR, $user_id );
		}

		if ( is_wp_error( WPCPM_Mail::may_invite( $user_id ) ) ) {
			self::bounce( self::OUT_TOO_SOON, $user_id );
		}

		$key = self::ceiling_key( $decision['institution'] );

		if ( ! WPCPM_Ceiling::claim( $key, self::PER_DAY, DAY_IN_SECONDS ) ) {
			self::bounce( self::OUT_CEILING, $user_id );
		}

		// Read before the send stamps the new one: it decides whether the notice speaks of an
		// earlier email whose link the new one replaces.
		$earlier = WPCPM_Mail::last_invited( $user_id ) > 0;

		// A refused email comes back as an error too: the mail layer listens for it around the send.
		$result = WPCPM_Students_Sync::send_invite( $user_id );

		if ( is_wp_error( $result ) ) {
			WPCPM_Ceiling::release( $key, DAY_IN_SECONDS );

			// The send's answer read as wp-admin's Resend reads it: the fifteen minutes, the email
			// the mail host refused, or the shared error.
			$outcomes = array(
				'invite-too-soon'     => self::OUT_TOO_SOON,
				'invite-mail-refused' => self::OUT_MAIL_REFUSED,
			);
			$outcome  = WPCPM_Mail::invite_outcome( $result );

			self::bounce( isset( $outcomes[ $outcome ] ) ? $outcomes[ $outcome ] : self::OUT_ERROR, $user_id );
		}

		$address = WPCPM_Mail::mask_address( (string) $student->user_email );

		// The invitation has gone: a history row that would not insert is said out loud rather
		// than swallowed, since the row is how an Administrator learns who sent it.
		if ( is_wp_error( self::log( $user_id, $decision, $earlier ) ) ) {
			self::bounce( self::OUT_UNLOGGED, $user_id, $address );
		}

		self::bounce( $earlier ? self::OUT_RESENT : self::OUT_SENT, $user_id, $address );
	}

	/**
	 * The key the day's allowance is counted under: one per institution.
	 *
	 * Built here rather than through `WPCPM_Ceiling::key()`, which lowercases what it is given: a
	 * record ID is case-sensitive, and two institutions whose IDs differ only in case are two
	 * allowances. Nothing readable reaches the options table either way, because the ceiling
	 * names its row after an md5 of the key and the window.
	 *
	 * @param string $institution Institutions record ID.
	 * @return string
	 */
	public static function ceiling_key( $institution ) {
		return self::CEILING . trim( (string) $institution );
	}

	/**
	 * The decision for this student's account and this reader, and the institution it names.
	 *
	 * One question for the line and the press, so the card never draws a button the press
	 * refuses at its policy step. Allowed only when the policy allows the action and names a
	 * well-formed institution record.
	 *
	 * @param int $user_id The student's account.
	 * @return array{allowed: bool, ground: string, institution: string}
	 */
	private static function decision( $user_id ) {
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_RESEND_INVITE,
			WPCPM_Institution_Policy::subject_student_account( (int) $user_id )
		);

		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';

		return array(
			'allowed'     => ! empty( $decision['allowed'] ) && WPCPM_Mentors_Sync::is_record_id( $institution ),
			'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
			'institution' => $institution,
		);
	}

	/**
	 * The account behind the ID when it is a student's: it exists and holds the Student role,
	 * the one `WPCPM_Students_Sync::send_invite()` sends to.
	 *
	 * @param int $user_id The account.
	 * @return WP_User|null
	 */
	private static function student( $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return null;
		}

		return WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT ) ? $user : null;
	}

	/**
	 * One history row for one send.
	 *
	 * The evidence is `cache`: the decision was made on the account's institution stamp, which
	 * the students sync writes.
	 *
	 * @param int   $user_id  The student's account.
	 * @param array $decision What `decision()` answered.
	 * @param bool  $earlier  Whether the account had been sent an invitation before.
	 * @return int|WP_Error The row's post ID, or why there is none.
	 */
	private static function log( $user_id, array $decision, $earlier ) {
		return WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::KIND,
				'institution' => $decision['institution'],
				'subject'     => (string) (int) $user_id,
				'actor'       => get_current_user_id(),
				'ground'      => $decision['ground'],
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => __( 'Welcome invitation sent from the Institution Dashboard.', 'wpcredits-program-manager' ),
				'data'        => array(
					'student' => (int) $user_id,
					'first'   => ! $earlier,
				),
			)
		);
	}

	/**
	 * Record the outcome and go back to the card the press came from, at the line's anchor.
	 *
	 * **This does not return.** The account travels with the outcome, so the sentence is
	 * printed on that student's card and on no other.
	 *
	 * @param string $outcome One of the OUT_* constants.
	 * @param int    $user_id The student's account the press named.
	 * @param string $detail  The masked address a sentence names, or ''.
	 */
	private static function bounce( $outcome, $user_id, $detail = '' ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'outcome' => (string) $outcome,
				'user'    => (int) $user_id,
				'detail'  => (string) $detail,
			)
		);

		wp_safe_redirect( WPCPM_Request::back( WPCPM_Institutions_Dashboard::page_url(), home_url( '/' ) ) . '#' . self::ANCHOR );
		exit;
	}
}
