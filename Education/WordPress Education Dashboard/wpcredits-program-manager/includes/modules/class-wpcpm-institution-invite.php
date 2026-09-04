<?php
/**
 * Institutions module - a member inviting a colleague.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One invitation to act for an institution, and the four things that can happen to it.
 *
 * Membership is the permission (decision 13), so an institution adds a colleague itself
 * rather than waiting for a program manager to make an account. An invitation is a
 * `wpcpm_inst_invite` post: the institution it is for, the address it went to, when
 * it was sent, who sent it, and a hash of the secret in its link. It is `pending` until it
 * is accepted, cancelled, or lapses.
 *
 * **The token is the security of this class, and it is never stored.** What the row holds
 * is `wp_hash()` of it, so somebody reading the database, a backup or an export cannot
 * accept an invitation with what they find there; the clear token exists in the mail and
 * nowhere else. That has one consequence worth saying out loud: Resend cannot re-send the
 * same link, because this site cannot read it back. Resend mints a new secret and the old
 * link stops working, which is the safer half of the trade anyway.
 *
 * **Every failure on the accept path gives the same message.** "This invitation expired",
 * "that invitation was cancelled" and "no such invitation" are three different sentences
 * that together tell a stranger walking tokens which addresses this program has invited,
 * and a signed-in visitor whose address is not the invited one is refused with that same
 * message rather than with a helpful one. The four states are still recorded, in the row
 * and in the audit log, where the people entitled to them can read them.
 *
 * **Accepting is two requests, and only the second changes anything.** The link in the mail
 * is a GET, and a GET is followed by mail gateways, security scanners and link prefetchers
 * long before any human opens the message; a state change on the end of one grants a
 * membership, and creates an account, for a robot. So the link lands on a page that says who
 * invited whom and asks, and the acceptance is a POST that page's own button makes, carrying
 * a nonce this site minted for that one token. It is the rule `render_control()` states for
 * Cancel and Resend, held to on the one path with no member behind it.
 *
 * Three ceilings, all of them nuisance controls rather than entitlements: ten invitations
 * pending per institution, so a member cannot fill a colleague's inbox by pressing the
 * button, five sends a day per member through `WPCPM_Ceiling`, and fourteen days of
 * life, after which `expire()` closes the row whether or not anybody looked at it.
 *
 * **The daily ceiling counts per member and not per institution.** That is the figure spec
 * 14.13 records the product owner answering, and the figure the drafted privacy notice
 * states in plain words; a number this site publishes and a number this site enforces have
 * to be the same number, which is the class of defect this module exists around. It is the
 * better rule anyway: per institution, one member who has spent their five stops a colleague
 * who has sent none from inviting anybody until tomorrow.
 *
 * The fence is the policy's, as everywhere else in this module: `ACT_MANAGE_MEMBERS` on the
 * institution, and on cancel and resend the institution is **the one the invitation names**,
 * read from the post's own meta, so a member of B cannot cancel A's invitation by posting
 * its ID. The one thing that is not decided by the policy is acceptance itself, which is
 * allowed by the token: the person following the link has no membership yet, and often no
 * account at all.
 */
class WPCPM_Institution_Invite {

	const POST_TYPE = 'wpcpm_inst_invite';

	/**
	 * The status every invitation is stored under.
	 *
	 * The type is invisible everywhere, so the status carries no visibility of its own; it is
	 * pinned so that every query and every insert in this class agree on it.
	 */
	const POST_STATUS = 'private';

	/** A member invites a colleague. Nonce keyed to the institution. */
	const ACTION_INVITE = 'wpcpm_invite_member';

	/** The link in the mail. The one action a signed-out request may reach. */
	const ACTION_ACCEPT = 'wpcpm_accept_invite';

	/** Take a pending invitation back. Nonce keyed to the invitation. */
	const ACTION_CANCEL = 'wpcpm_cancel_invite';

	/** Send a pending invitation again, with a new secret. Nonce keyed to the invitation. */
	const ACTION_RESEND = 'wpcpm_resend_invite';

	/** The daily job that closes the invitations nobody answered. */
	const CRON_EXPIRE = 'wpcpm_invite_expire';

	/** How many invitations one institution may have waiting at once. */
	const PENDING_MAX = 10;

	/** How long an invitation is good for. */
	const LIFETIME_DAYS = 14;

	/** How many invitations one member may send in a day, counted by `WPCPM_Ceiling`. */
	const SENDS_PER_DAY = 5;

	/** How long the secret in the link is. Alphanumeric, so it survives a mail client. */
	const TOKEN_LENGTH = 32;

	/** The query argument the secret travels in. */
	const TOKEN_ARG = 't';

	/** Post meta: the Institutions record the invitation is for. The queryable key. */
	const META_INSTITUTION = '_wpcpm_inv_institution';

	/** Post meta: the address it was sent to, lowercased. */
	const META_EMAIL = '_wpcpm_inv_email';

	/** Post meta: `wp_hash()` of the secret, and never the secret itself. */
	const META_TOKEN = '_wpcpm_inv_token';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_inv_state';

	/** Post meta: the member who sent it. */
	const META_ACTOR = '_wpcpm_inv_actor';

	/** Post meta: when it was last sent, unix. The fourteen days run from here. */
	const META_SENT = '_wpcpm_inv_sent';

	/** Post meta: when it stopped being pending, unix. The retention window runs from here. */
	const META_SETTLED = '_wpcpm_inv_settled';

	/** Post meta: when it was accepted, unix. */
	const META_ACCEPTED = '_wpcpm_inv_accepted';

	/** Post meta: the account that accepted it. */
	const META_USER = '_wpcpm_inv_user';

	const STATE_PENDING   = 'pending';
	const STATE_ACCEPTED  = 'accepted';
	const STATE_CANCELLED = 'cancelled';
	const STATE_EXPIRED   = 'expired';

	/** Audit kinds, in the shape the agreement's are. */
	const LOG_SENT      = 'invite_sent';
	const LOG_RESENT    = 'invite_resent';
	const LOG_ACCEPTED  = 'invite_accepted';
	const LOG_CANCELLED = 'invite_cancelled';
	const LOG_EXPIRED   = 'invite_expired';

	/** Mail context, so the send shows in the log by name. */
	const MAIL_INVITE = 'institution-invite';

	/** Flash channel. The value carries the institution, the way the People card's does. */
	const FLASH = 'institution_invite';

	/**
	 * Hooks.
	 *
	 * Accept is the one action with a `nopriv` arm, and it has to be: the person following
	 * the link is a colleague who has never signed in here, and often has no account for the
	 * site to recognise. Both halves of accepting go through that one action, the page the
	 * link draws and the button on it; `handle_accept()` says why. Every other action needs a
	 * member, and a logged-out request would learn something from a handler that answered it
	 * at all.
	 *
	 * The cron is hooked here and not scheduled here: scheduling belongs to the module's
	 * activation, where every other event of this plugin's is put on the calendar.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_INVITE, array( __CLASS__, 'handle_invite' ) );
		add_action( 'admin_post_' . self::ACTION_ACCEPT, array( __CLASS__, 'handle_accept' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_ACCEPT, array( __CLASS__, 'handle_accept' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_RESEND, array( __CLASS__, 'handle_resend' ) );
		add_action( self::CRON_EXPIRE, array( __CLASS__, 'expire' ) );
	}

	/**
	 * Register the invitation post type.
	 *
	 * Invisible everywhere by design, like every other type this module keeps: not public,
	 * not queryable, not in REST, not in search, no admin UI. These rows name the people an
	 * institution is trying to add, and the only routes to them are the People card and the
	 * manager's backstop, both of which ask the policy first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Institution invitations', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Institution invitation', 'wpcredits-program-manager' ),
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
				'supports'            => array( 'title', 'author' ),
				// A capability type nothing is granted, so no role can reach these through any
				// generic post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_inst_invite', 'wpcpm_inst_invites' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/*
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * The invitations one institution has waiting right now.
	 *
	 * Pending **and** inside their fourteen days: a row the cron has not swept yet is not a
	 * place somebody's colleague is waiting in, so it does not hold one of the ten either.
	 * That makes this the one definition of "waiting" the limit, the card and the resend
	 * control all read.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @return int[] Post IDs, newest first.
	 */
	public static function pending_for( $record_id ) {
		$pending = array();

		foreach ( self::posts_for( $record_id ) as $post ) {
			$post_id = (int) $post->ID;

			if ( self::STATE_PENDING === self::state_of( $post_id ) && ! self::lapsed( $post_id ) ) {
				$pending[] = $post_id;
			}
		}

		return $pending;
	}

	/**
	 * Every invitation an institution has, newest first, as facts.
	 *
	 * Facts only, in the shape the cards print them: nothing here decides anything and
	 * nothing here is a token. `waiting` is the state after the fourteen days are applied,
	 * so a card never shows a lapsed row as pending while it waits for the sweep.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param int    $limit     Most rows to read.
	 * @return array[] `id`, `email`, `state`, `waiting`, `sent`, `expires`, `actor`,
	 *                 `actor_name`, `user`.
	 */
	public static function invites_for( $record_id, $limit = 50 ) {
		$limit = (int) $limit > 0 ? (int) $limit : 50;
		$facts = array();

		foreach ( self::posts_for( $record_id, $limit ) as $post ) {
			$facts[] = self::facts_of( $post );
		}

		return $facts;
	}

	/**
	 * What one invitation is, as facts.
	 *
	 * An unknown post answers with an empty array rather than a half-filled one, so a caller
	 * that forgot to check gets nothing to print.
	 *
	 * @param int $post_id Invitation post ID.
	 * @return array
	 */
	public static function facts( $post_id ) {
		$post = self::invite_post( $post_id );

		return $post instanceof WP_Post ? self::facts_of( $post ) : array();
	}

	/*
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The invite form, drawn inside the People card.
	 *
	 * Nothing at all for a viewer the policy refuses, rather than an empty form: a form that
	 * appears and then answers "that record is not on your roster" is the same disclosure the
	 * one refusal exists to avoid. The decision is `ACT_MANAGE_MEMBERS`, the same one the
	 * handler makes, so what is drawn and what is allowed cannot drift apart.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param string $origin    `WPCPM_Institution_People::RETURN_ADMIN` from the manager
	 *                          screen, '' from the institution's own dashboard.
	 */
	public static function render_form( $record_id, $origin = '' ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		printf( '<h4 class="wpcpm-people__subtitle">%s</h4>', esc_html__( 'Invite a colleague', 'wpcredits-program-manager' ) );

		echo '<form class="wpcpm-people__form wpcpm-people__form--invite" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_INVITE . '_' . $record_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_INVITE ) );
		printf( '<input type="hidden" name="record" value="%s" />', esc_attr( $record_id ) );
		self::render_origin( $origin );

		printf(
			'<label class="screen-reader-text" for="wpcpm-invite-email-%1$s">%2$s</label>',
			esc_attr( $record_id ),
			esc_html__( 'Their email address', 'wpcredits-program-manager' )
		);
		printf(
			'<input type="email" id="wpcpm-invite-email-%1$s" name="email" value="" placeholder="%2$s" required />',
			esc_attr( $record_id ),
			esc_attr__( 'Their email address', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="button button-primary">%s</button>',
			esc_html__( 'Send invitation', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: how many days an invitation is good for, 2: how many invitations may be waiting at once. */
					__( 'They get one email with a link that is good for %1$s days. Whoever accepts it sees the same students you do and can remove anybody, including you. Up to %2$s invitations can be waiting at a time.', 'wpcredits-program-manager' ),
					number_format_i18n( self::LIFETIME_DAYS ),
					number_format_i18n( self::PENDING_MAX )
				)
			)
		);

		echo '</form>';
	}

	/**
	 * The invitations this institution is waiting on, with Cancel and Resend on each.
	 *
	 * Drawn only for somebody who may manage members, for the reason `render_form()` gives,
	 * and drawn as nothing at all when there are none: an empty list under a heading reads as
	 * a fault rather than as an absence.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param string $origin    `WPCPM_Institution_People::RETURN_ADMIN` from the manager
	 *                          screen, '' from the institution's own dashboard.
	 */
	public static function render_pending( $record_id, $origin = '' ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		$pending = self::pending_for( $record_id );

		if ( empty( $pending ) ) {
			return;
		}

		printf( '<h4 class="wpcpm-people__subtitle">%s</h4>', esc_html__( 'Invitations waiting', 'wpcredits-program-manager' ) );

		echo '<ul class="wpcpm-people__invites">';

		foreach ( $pending as $post_id ) {
			self::render_invite( self::facts( $post_id ), $origin );
		}

		echo '</ul>';
	}

	/**
	 * One waiting invitation's row.
	 *
	 * @param array  $facts  What `facts()` returned.
	 * @param string $origin Where the two controls return to.
	 */
	private static function render_invite( array $facts, $origin ) {
		if ( empty( $facts['id'] ) ) {
			return;
		}

		echo '<li class="wpcpm-people__invite">';

		printf( '<span class="wpcpm-people__invite-email">%s</span> ', esc_html( $facts['email'] ) );

		printf(
			'<span class="wpcpm-people__invite-facts">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: who sent it, 2: the date it was sent, 3: the date it stops working. */
					__( 'Invited by %1$s on %2$s, good until %3$s.', 'wpcredits-program-manager' ),
					'' !== $facts['actor_name'] ? $facts['actor_name'] : __( 'a colleague', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d', $facts['sent'] ),
					wp_date( 'Y-m-d', $facts['expires'] )
				)
			)
		);

		self::render_control( self::ACTION_RESEND, (int) $facts['id'], __( 'Resend', 'wpcredits-program-manager' ), '', $origin );
		self::render_control(
			self::ACTION_CANCEL,
			(int) $facts['id'],
			__( 'Cancel', 'wpcredits-program-manager' ),
			sprintf(
				/* translators: %s: the invited address. */
				__( 'Cancel the invitation to %s? The link in their email stops working straight away.', 'wpcredits-program-manager' ),
				$facts['email']
			),
			$origin
		);

		echo '</li>';
	}

	/**
	 * One of the two controls on a waiting invitation.
	 *
	 * Both are posts and neither is a link: they change state, and a link that changes state
	 * is followed by every prefetcher and every mail scanner that meets it. `handle_accept()`
	 * keeps the same rule on the one path that starts as a link, by making the link ask.
	 *
	 * @param string $action  `ACTION_CANCEL` or `ACTION_RESEND`.
	 * @param int    $post_id The invitation.
	 * @param string $label   The button.
	 * @param string $confirm What the browser asks first, or '' to ask nothing.
	 * @param string $origin  Where the handler returns to.
	 */
	private static function render_control( $action, $post_id, $label, $confirm, $origin ) {
		echo '<form class="wpcpm-people__form wpcpm-people__form--inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $action . '_' . $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="invite" value="%d" />', (int) $post_id );
		self::render_origin( $origin );

		// The confirm is built the way the People card's is, and only where there is something
		// to warn about: Resend sends the same person another mail, Cancel breaks a link
		// somebody may be about to follow.
		if ( '' === $confirm ) {
			printf( '<button type="submit" class="button button-link">%s</button>', esc_html( $label ) );
		} else {
			printf(
				'<button type="submit" class="button button-link" onclick="return confirm(%1$s)">%2$s</button>',
				esc_attr( wp_json_encode( $confirm ) ),
				esc_html( $label )
			);
		}

		echo '</form>';
	}

	/**
	 * The flag that says which screen a control was pressed on.
	 *
	 * A flag and never a URL: `finish()` rebuilds the destination from it, so no form can
	 * bounce a member somewhere else.
	 *
	 * @param string $origin `WPCPM_Institution_People::RETURN_ADMIN` or ''.
	 */
	private static function render_origin( $origin ) {
		if ( WPCPM_Institution_People::RETURN_ADMIN !== $origin ) {
			return;
		}

		printf(
			'<input type="hidden" name="wpcpm_from" value="%s" />',
			esc_attr( WPCPM_Institution_People::RETURN_ADMIN )
		);
	}

	/**
	 * The one-shot outcome of the last thing somebody pressed, on the card it happened to.
	 *
	 * The same shape as the People card's, and for the same reasons: a flash rather than a
	 * query argument so a reload does not repeat it, and the institution travels with it so a
	 * screen drawing this card under every pipeline row prints it under one of them.
	 *
	 * @param string $record_id The institution whose card is being drawn.
	 */
	public static function render_message( $record_id ) {
		$flash  = WPCPM_Flash::take( self::FLASH );
		$status = is_array( $flash ) && isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : sanitize_key( (string) $flash );
		$detail = is_array( $flash ) && isset( $flash['detail'] ) ? (string) $flash['detail'] : '';
		$about  = is_array( $flash ) && isset( $flash['record'] ) ? trim( (string) $flash['record'] ) : '';

		// Routing, not authorisation: which card prints a sentence, never who may read one.
		// Every fence in this module is the policy's, and this comparison decides nothing
		// about access.
		if ( trim( (string) $record_id ) !== $about ) {
			return;
		}

		$messages = self::messages();

		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-people__message is-%1$s" role="status">%2$s%3$s</p>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $messages[ $status ][1] ),
			'' === $detail ? '' : ' ' . esc_html( $detail )
		);
	}

	/**
	 * The words for each outcome, in one place because two screens print them.
	 *
	 * Three of them name a number, and every one of those numbers is the constant the
	 * handler enforces: a limit a person is told about in different words from the one that
	 * refused them is a support ticket.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to tone and sentence.
	 */
	public static function messages() {
		return array(
			'invite-sent'      => array(
				'success',
				sprintf(
					/* translators: %s: how many days an invitation is good for. */
					__( 'The invitation is on its way. It is good for %s days, and you can cancel or resend it from the list above.', 'wpcredits-program-manager' ),
					number_format_i18n( self::LIFETIME_DAYS )
				),
			),
			'invite-unsent'    => array( 'error', __( 'The invitation was recorded and this site could not hand the message to its mail server, which is this site\'s fault and not yours. Press Resend in a few minutes, and tell your program contact if it happens again.', 'wpcredits-program-manager' ) ),
			'invite-resent'    => array( 'success', __( 'The invitation was sent again, with a new link. The link in the earlier message has stopped working.', 'wpcredits-program-manager' ) ),
			'invite-cancelled' => array( 'success', __( 'That invitation is cancelled and its link no longer works.', 'wpcredits-program-manager' ) ),
			'invite-member'    => array( 'error', __( 'Nothing was sent. That address already has access to this institution and is on the list above.', 'wpcredits-program-manager' ) ),
			'invite-waiting'   => array( 'error', __( 'Nothing was sent. That address already has an invitation waiting. Resend it if it has not arrived.', 'wpcredits-program-manager' ) ),
			'invite-full'      => array(
				'error',
				sprintf(
					/* translators: %s: how many invitations may be waiting at once. */
					__( 'Nothing was sent. This institution already has %s invitations waiting. Cancel one, or wait for it to expire, before sending another.', 'wpcredits-program-manager' ),
					number_format_i18n( self::PENDING_MAX )
				),
			),
			'invite-ceiling'   => array(
				'error',
				sprintf(
					/* translators: %s: how many invitations one member may send in a day. */
					__( 'Nothing was sent. You have sent the %s invitations one day allows you. Try again tomorrow, or ask a colleague to send it: their own allowance is untouched.', 'wpcredits-program-manager' ),
					number_format_i18n( self::SENDS_PER_DAY )
				),
			),
			'invite-bad-email' => array( 'error', __( 'Nothing was sent. That is not an address an invitation can go to.', 'wpcredits-program-manager' ) ),
			'invite-unknown'   => array( 'error', __( 'Nothing was sent. This institution is not in the program\'s index on this site yet, so nobody can be added to it. Tell your program contact.', 'wpcredits-program-manager' ) ),
			'invite-gone'      => array( 'info', __( 'Nothing happened. That invitation is no longer waiting: somebody has accepted or cancelled it since this page was drawn, or it has expired.', 'wpcredits-program-manager' ) ),
			'invite-joined'    => array( 'success', __( 'You are now a member of this institution and can see its students.', 'wpcredits-program-manager' ) ),
			'invite-error'     => array( 'error', __( 'That could not be done.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Send one invitation.
	 *
	 * The order is the design. Signed in, then the nonce keyed to the institution the form
	 * names, then the policy, then the state of the record, then the address, then the two
	 * limits, and the ceiling last of all: a refusal that sends nothing must not use up one
	 * of the five sends a day, or a member could be locked out of inviting anybody by five
	 * mistyped addresses.
	 *
	 * The form's `record` is a claim and is treated as one. The nonce key narrows a token to
	 * one institution, and `decide()` is what says whether this person may act for it; a
	 * member of B posting A's record ID is decided against A and gets the one refusal.
	 */
	public static function handle_invite() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to invite a colleague.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Request::posted_text( 'record' );

		check_admin_referer( self::ACTION_INVITE . '_' . $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		// An institution the site has never read cannot acquire members: `attach()` would
		// refuse the acceptance in a fortnight, and the person to tell is the one pressing the
		// button now.
		if ( ! WPCPM_Institutions_Index::has( $record ) ) {
			self::finish( 'invite-unknown', '', $record );
		}

		$email = strtolower( sanitize_email( WPCPM_Request::posted_text( 'email' ) ) );

		if ( ! is_email( $email ) ) {
			self::finish( 'invite-bad-email', '', $record );
		}

		// Named before anything is sent, because neither of these is an error the sender made
		// twice: the address is on the list they are looking at, or a colleague invited it an
		// hour ago.
		if ( self::holds_address( WPCPM_Institution_Members::members_of( $record ), $email ) ) {
			self::finish( 'invite-member', $email, $record );
		}

		if ( self::pending_to( $record, $email ) ) {
			self::finish( 'invite-waiting', $email, $record );
		}

		if ( count( self::pending_for( $record ) ) >= self::PENDING_MAX ) {
			self::finish( 'invite-full', '', $record );
		}

		$actor = get_current_user_id();

		if ( ! WPCPM_Ceiling::claim( self::ceiling_key( $record, $actor ), self::SENDS_PER_DAY, DAY_IN_SECONDS ) ) {
			self::finish( 'invite-ceiling', '', $record );
		}

		$post_id = self::create( $record, $email, $actor );

		if ( ! $post_id ) {
			self::finish( 'invite-error', '', $record );
		}

		$sent = self::mail_invite( $post_id, self::issue( $post_id ) );

		self::log(
			self::LOG_SENT,
			$post_id,
			$decision,
			WPCPM_Institution_Audit::EVIDENCE_INDEX,
			sprintf(
				/* translators: 1: who sent it, 2: the address it went to. */
				__( '%1$s invited %2$s to act for this institution.', 'wpcredits-program-manager' ),
				self::actor_name( $actor ),
				$email
			)
		);

		self::finish( $sent ? 'invite-sent' : 'invite-unsent', $sent ? $email : '', $record );
	}

	/**
	 * The link in the mail, and the button on the page it lands on.
	 *
	 * Both halves arrive here, because both are the one action a signed-out request may reach
	 * and `admin-post.php` routes by action rather than by method. The GET only draws a page.
	 * The POST is the acceptance.
	 *
	 * **Nothing the GET arm touches writes anything at all**, and that is the whole point of
	 * the split: a mail gateway, a security scanner and a link prefetcher all follow a link,
	 * and a state change on the end of one creates an account and grants a membership before
	 * the person the invitation was meant for has opened the message.
	 *
	 * Two lines, for the reason `WPCPM_Agreement_Generate::handle_generate()` is two lines: a
	 * method that ends in `exit` cannot be watched by a test, and everything above the `exit`
	 * is worth watching.
	 */
	public static function handle_accept() {
		if ( self::is_post() ) {
			self::accept_invitation();
		}

		self::send( self::accept_page() );
	}

	/**
	 * Everything the link in the mail does, except put the page on the wire.
	 *
	 * Returns the document to print. It does not return any other way: every refusal in here
	 * dies with the one message, so a caller has a page or has already stopped. The token is
	 * checked exactly as far as it can be without writing anything, so a link that will not
	 * work says so here rather than under the button.
	 *
	 * @return string A complete HTML document.
	 */
	public static function accept_page() {
		$token = WPCPM_Request::text( self::TOKEN_ARG );

		return self::confirm_document( self::invitation_for( $token ), $token );
	}

	/**
	 * Accept an invitation: the POST the person makes on the page the link landed on.
	 *
	 * The token is the credential here, and the only one: the person pressing the button has
	 * no membership to decide against and often no account at all, which is exactly what the
	 * invitation is for. The nonce is not a second credential. It was minted for this token,
	 * on the page this site drew a moment ago, and all it says is that a person on that page
	 * pressed the button - which is the thing a prefetcher cannot do and the reason this
	 * handler has two halves.
	 *
	 * It is checked before the token is looked up, so a POST carrying a well-formed token
	 * nobody was ever sent opens no query at all.
	 *
	 * **This does not return.**
	 */
	private static function accept_invitation() {
		$token = WPCPM_Request::posted_text( self::TOKEN_ARG );

		if ( ! self::nonce_ok( $token ) ) {
			self::refuse_accept();
		}

		self::complete_accept( self::invitation_for( $token ) );
	}

	/**
	 * Do what accepting does, once there is nothing left to check.
	 *
	 * The account is created or adopted, `attach()` stamps `invited` with the inviter as the
	 * actor, the row is settled and its token forgotten, and the visitor goes to the
	 * dashboard. A brand new account is sent the site's own set-your-password invitation on
	 * the way, because it was created with a password nobody knows.
	 *
	 * Split out from `accept_invitation()` so that the one thing that has to be true here -
	 * that none of this is reachable from a GET - is a property of the call graph rather than
	 * of a reader's attention.
	 *
	 * **This does not return.**
	 *
	 * @param array $invite What `invitation_for()` returned: `id`, `record`, `email`.
	 */
	private static function complete_accept( array $invite ) {
		$post_id = (int) $invite['id'];
		$record  = (string) $invite['record'];
		$email   = (string) $invite['email'];

		$user_id = self::account_for( $email );

		if ( ! $user_id ) {
			self::refuse_accept();
		}

		$actor    = (int) get_post_meta( $post_id, self::META_ACTOR, true );
		$attached = WPCPM_Institution_Members::attach( $user_id, $record, WPCPM_Institution_Members::HOW_INVITED, $actor, $post_id );

		// Already a member of this institution is not a failure: two people can follow the
		// same link, and the second deserves to land on the dashboard rather than on a
		// refusal. Every other reason `attach()` gives - an administrator, a student's
		// account, a live membership of somewhere else - is a no, and gets the one message.
		if ( is_wp_error( $attached ) && 'wpcpm_member_already' !== $attached->get_error_code() ) {
			self::refuse_accept();
		}

		self::settle( $post_id, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_ACCEPTED, time() );
		update_post_meta( $post_id, self::META_USER, (int) $user_id );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_ACCEPTED,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => (int) $user_id,
				// The token is what allowed this, and the policy has no ground for a person
				// who was not a member a moment ago. `system` is the honest answer: the site's
				// own rule let it through, and the row above says which invitation it was.
				'ground'      => WPCPM_Institution_Audit::GROUND_SYSTEM,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => sprintf(
					/* translators: %s: the invited address. */
					__( 'The invitation to %s was accepted.', 'wpcredits-program-manager' ),
					$email
				),
				'data'        => array(
					'invite' => $post_id,
					'user'   => (int) $user_id,
				),
			)
		);

		// A no-op for somebody who is not signed in, which is most of them: the flash is user
		// meta, and the account that was just created has nobody sitting behind it yet.
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'status' => 'invite-joined',
				'detail' => '',
				'record' => $record,
			)
		);

		wp_safe_redirect( self::dashboard_url() );
		exit;
	}

	/**
	 * The invitation a secret names, checked as far as it can be without writing anything.
	 *
	 * Both halves of accepting ask this, and they have to ask exactly the same question: a
	 * link that draws a page and then refuses under the button has told somebody they were
	 * invited and then called them a stranger. Whether there is such a row, whether it is
	 * still pending, whether it is inside its fourteen days, whether its own facts are usable,
	 * whether the institution is still one this site knows, and whether the visitor is either
	 * signed out or signed in as the invited address.
	 *
	 * Every no is answered with the same sentence, by `refuse_accept()`.
	 *
	 * **It does not return when it refuses.**
	 *
	 * @param string $token The secret, from the link or from the form.
	 * @return array `id`, `record`, `email`.
	 */
	private static function invitation_for( $token ) {
		$post = self::by_token( $token );

		if ( ! $post instanceof WP_Post ) {
			self::refuse_accept();
		}

		$post_id = (int) $post->ID;

		if ( self::STATE_PENDING !== self::state_of( $post_id ) || self::lapsed( $post_id ) ) {
			self::refuse_accept();
		}

		$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
		$email  = strtolower( trim( (string) get_post_meta( $post_id, self::META_EMAIL, true ) ) );

		// A row whose own facts are unusable is answered like an unknown one: it cannot be
		// accepted, and why not is the log's business rather than a stranger's.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! is_email( $email ) ) {
			self::refuse_accept();
		}

		// Asked before an account is made rather than left to `attach()`: an institution the
		// index has since lost cannot acquire members, and creating the account first would
		// leave somebody holding a new account with a password mail on its way and no
		// institution to see.
		if ( ! WPCPM_Institutions_Index::has( $record ) ) {
			self::refuse_accept();
		}

		// **And the gate, which is the reason an invitation is not a standing right.** An
		// institution whose agreement was revoked has had every member's access closed; a
		// pending invitation issued before that is a way back in for somebody the program has
		// never met, and the one action a locked account keeps is the agreement panel, which
		// is the last place a stranger should arrive. Cancelling on revoke is not enough on
		// its own: a link already in a mailbox is not recalled by a database write, so the
		// state is asked again here, at the moment it matters.
		if ( ! WPCPM_Institution_Agreement::is_settled( $record ) ) {
			self::refuse_accept();
		}

		// **Why a signed-in visitor with another address is refused.** Invitations get
		// forwarded, and the account somebody happens to be signed into is not evidence about
		// the mailbox the link was sent to. Attaching it would hand a colleague's institution
		// to whoever the mail reached, and telling them apart in the refusal would say which
		// address had been invited.
		if ( ! self::visitor_may_accept( $email ) ) {
			self::refuse_accept();
		}

		return array(
			'id'     => $post_id,
			'record' => $record,
			'email'  => $email,
		);
	}

	/**
	 * Whether the visitor may accept an invitation to this address.
	 *
	 * Signed out is a yes: most people following one of these links have no account here, and
	 * making one is part of what accepting does. Signed in is a yes only for the invited
	 * address. Written once because both halves of accepting ask it, and an answer that
	 * differed between them would be a page that asks and a button that refuses.
	 *
	 * @param string $email The invited address, lowercased.
	 * @return bool
	 */
	private static function visitor_may_accept( $email ) {
		$user = wp_get_current_user();

		if ( ! is_user_logged_in() || ! $user instanceof WP_User || ! $user->exists() ) {
			return true;
		}

		return strtolower( trim( (string) $user->user_email ) ) === (string) $email;
	}

	/**
	 * Whether this request is the button and not the link.
	 *
	 * The method and nothing else: `admin-post.php` routes by action, so both halves of
	 * accepting reach one handler and this is what tells them apart. A request with no method
	 * to read reads as "not a POST", which is the half that writes nothing.
	 *
	 * @return bool
	 */
	private static function is_post() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		return 'POST' === strtoupper( $method );
	}

	/**
	 * Whether the POST carries the nonce this site minted for this token.
	 *
	 * `wp_verify_nonce()` and never `check_admin_referer()`, for the reason the application
	 * form's handler gives: `check_admin_referer()` dies with core's own 403 screen, and every
	 * failure on this path owes its reader the same sentence as every other failure on it.
	 *
	 * Keyed to the token, so a nonce that came with one invitation's page is not a nonce for
	 * accepting another. Only the nonce is printed and never the action, so keying it to the
	 * secret puts the secret nowhere it was not already.
	 *
	 * @param string $token The secret posted with it.
	 * @return bool
	 */
	private static function nonce_ok( $token ) {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, self::ACTION_ACCEPT . '_' . (string) $token );
	}

	/**
	 * The page the link lands on: what accepting would do, and the button that does it.
	 *
	 * A standalone document, the way the agreement's printable one is, and for the same two
	 * reasons: this runs on `admin-post.php`, where there is no theme and no `wp_head()`, and
	 * the institution dashboard it would otherwise be a part of is gated to members - which
	 * the reader is not yet, and is here to become.
	 *
	 * **It says nothing the mail did not already say.** The institution, who invited them and
	 * the day the link stops working were all in the message this link came out of. The
	 * address it was sent to is not printed: a forwarded mail should not tell its new reader
	 * which mailbox this program wrote to, and the refusal is careful about the same thing.
	 *
	 * @param array  $invite What `invitation_for()` returned.
	 * @param string $token  The secret, so the form can post it back.
	 * @return string A complete HTML document.
	 */
	private static function confirm_document( array $invite, $token ) {
		$post_id = (int) $invite['id'];
		$school  = self::institution_name( (string) $invite['record'] );
		$site    = WPCPM_Mail::site_name();
		$who     = self::actor_name( (int) get_post_meta( $post_id, self::META_ACTOR, true ) );
		$until   = wp_date( 'Y-m-d', self::expires_at( (int) get_post_meta( $post_id, self::META_SENT, true ) ) );
		$title   = __( 'Accept this invitation?', 'wpcredits-program-manager' );

		if ( '' === $who ) {
			$who = __( 'A colleague', 'wpcredits-program-manager' );
		}

		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="utf-8" />' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";

		// The URL of this page carries the secret. `noindex` keeps it out of a search index,
		// and `no-referrer` keeps it out of the Referer header of anything the reader opens
		// next, which is the way a token in a query string usually escapes.
		$html .= '<meta name="robots" content="noindex, nofollow" />' . "\n";
		$html .= '<meta name="referrer" content="no-referrer" />' . "\n";
		$html .= '<title>' . esc_html( $title ) . '</title>' . "\n";
		$html .= '<style>' . "\n" . self::stylesheet() . "\n" . '</style>' . "\n";
		$html .= '</head>' . "\n";
		$html .= '<body class="wpcpm-accept">' . "\n";
		$html .= '<main class="wpcpm-accept__card">' . "\n";
		$html .= '<h1 class="wpcpm-accept__title">' . esc_html( $title ) . '</h1>' . "\n";

		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: 1: the member who invited them, 2: the institution's name, 3: the site's name. */
				__( '%1$s has invited you to see %2$s\'s students in the WordPress Credits Program on %3$s.', 'wpcredits-program-manager' ),
				$who,
				$school,
				$site
			)
		) . '</p>' . "\n";

		$html .= '<p>' . esc_html__( 'Accepting adds you as a member: you will see the same students they do, and you can remove anybody, including them. If you do not have an account here yet, one is made for you and you get an email with a link to set your password.', 'wpcredits-program-manager' ) . '</p>' . "\n";

		$html .= '<form class="wpcpm-accept__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . "\n";
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_ACCEPT ) . '" />' . "\n";
		$html .= '<input type="hidden" name="' . esc_attr( self::TOKEN_ARG ) . '" value="' . esc_attr( $token ) . '" />' . "\n";

		// Written out rather than printed by `wp_nonce_field()` because this document is built
		// as a string and echoed in one piece; there is nothing here that echoes as it goes.
		$html .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( self::ACTION_ACCEPT . '_' . (string) $token ) ) . '" />' . "\n";
		$html .= '<button type="submit" class="wpcpm-accept__button">' . esc_html__( 'Accept invitation', 'wpcredits-program-manager' ) . '</button>' . "\n";
		$html .= '</form>' . "\n";

		$html .= '<p class="wpcpm-accept__note">' . esc_html(
			sprintf(
				/* translators: %s: the date the invitation stops working. */
				__( 'Nothing has happened yet: this page only asks. The invitation works until %s, and if you were not expecting it you can close this page and let it stop working on its own.', 'wpcredits-program-manager' ),
				$until
			)
		) . '</p>' . "\n";

		$html .= '</main>' . "\n";
		$html .= '</body>' . "\n";
		$html .= '</html>' . "\n";

		return $html;
	}

	/**
	 * The confirmation page's own styles.
	 *
	 * Inlined because there is no `wp_head()` on `admin-post.php` to print a handle with, and
	 * because the reader has no account and so no admin stylesheet either. Deliberately plain:
	 * a page asking somebody whether they meant to join something should look like the site
	 * telling them where they are, not like an advertisement for saying yes.
	 *
	 * @return string
	 */
	private static function stylesheet() {
		return 'body.wpcpm-accept{margin:0;padding:1.5rem;background:#f0f0f1;color:#1d2327;'
			. 'font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
			. "\n" . '.wpcpm-accept__card{max-width:36rem;margin:2rem auto;padding:2rem;background:#fff;'
			. 'border:1px solid #c3c4c7;border-radius:4px}'
			. "\n" . '.wpcpm-accept__title{margin:0 0 1rem;font-size:1.5rem;line-height:1.3}'
			. "\n" . '.wpcpm-accept p{margin:0 0 1rem}'
			. "\n" . '.wpcpm-accept__form{margin:1.5rem 0}'
			. "\n" . '.wpcpm-accept__button{padding:.6rem 1.4rem;border:1px solid #2271b1;border-radius:3px;'
			. 'background:#2271b1;color:#fff;font-size:1rem;cursor:pointer}'
			. "\n" . '.wpcpm-accept__button:hover{background:#135e96;border-color:#135e96}'
			. "\n" . '.wpcpm-accept__note{margin:0;color:#50575e;font-size:.875rem}';
	}

	/**
	 * Put the confirmation page on the wire and stop.
	 *
	 * The three headers the agreement's printable document goes out under, for the same
	 * reasons: nothing about a page that names one institution and carries one invitation's
	 * secret belongs in a cache or an index, and a browser left to sniff a content type is a
	 * browser that can be talked into treating a document as something else.
	 *
	 * **This does not return.**
	 *
	 * @param string $document A complete HTML document.
	 */
	private static function send( $document ) {
		nocache_headers();

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		echo $document; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A whole HTML document built by confirm_document(), which escapes every value it interpolates; escaping it again would print its own markup.
		exit;
	}

	/**
	 * Cancel a waiting invitation.
	 *
	 * The nonce is keyed to the invitation, so a token for cancelling one is not a token for
	 * cancelling another, and the institution is **the one the invitation names**, read from
	 * the post's own meta. A member of B posting A's invitation ID is decided against A, is
	 * not a member of A, and gets the one refusal.
	 */
	public static function handle_cancel() {
		$post_id  = WPCPM_Request::posted_id( 'invite' );
		$decision = self::decide_on_invite( self::ACTION_CANCEL, $post_id );
		$record   = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		if ( self::STATE_PENDING !== self::state_of( $post_id ) || self::lapsed( $post_id ) ) {
			self::finish( 'invite-gone', '', $record );
		}

		$email = (string) get_post_meta( $post_id, self::META_EMAIL, true );

		self::settle( $post_id, self::STATE_CANCELLED );

		self::log(
			self::LOG_CANCELLED,
			$post_id,
			$decision,
			WPCPM_Institution_Audit::EVIDENCE_CACHE,
			sprintf(
				/* translators: 1: who cancelled it, 2: the address it had gone to. */
				__( '%1$s cancelled the invitation to %2$s.', 'wpcredits-program-manager' ),
				self::actor_name( get_current_user_id() ),
				$email
			)
		);

		self::finish( 'invite-cancelled', $email, $record );
	}

	/**
	 * Send a waiting invitation again.
	 *
	 * With a **new** secret, because this site cannot read the old one back: the row holds a
	 * hash of it and nothing else, which is the whole point of storing it that way. So the
	 * earlier link stops working, the fourteen days start again from now, and the message
	 * says so rather than letting somebody believe both links are live.
	 *
	 * A resend is a send, so it claims the same daily ceiling as a first one, against the
	 * member who pressed the button rather than against whoever sent the first message.
	 */
	public static function handle_resend() {
		$post_id  = WPCPM_Request::posted_id( 'invite' );
		$decision = self::decide_on_invite( self::ACTION_RESEND, $post_id );
		$record   = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		if ( self::STATE_PENDING !== self::state_of( $post_id ) || self::lapsed( $post_id ) ) {
			self::finish( 'invite-gone', '', $record );
		}

		if ( ! WPCPM_Ceiling::claim( self::ceiling_key( $record, get_current_user_id() ), self::SENDS_PER_DAY, DAY_IN_SECONDS ) ) {
			self::finish( 'invite-ceiling', '', $record );
		}

		$email = (string) get_post_meta( $post_id, self::META_EMAIL, true );
		$sent  = self::mail_invite( $post_id, self::issue( $post_id ) );

		self::log(
			self::LOG_RESENT,
			$post_id,
			$decision,
			WPCPM_Institution_Audit::EVIDENCE_CACHE,
			sprintf(
				/* translators: 1: who sent it again, 2: the address it went to. */
				__( '%1$s sent the invitation to %2$s again, with a new link.', 'wpcredits-program-manager' ),
				self::actor_name( get_current_user_id() ),
				$email
			)
		);

		self::finish( $sent ? 'invite-resent' : 'invite-unsent', $sent ? $email : '', $record );
	}

	/**
	 * The three checks the two invitation controls share.
	 *
	 * Signed in, the nonce keyed to the invitation, then the policy on the institution the
	 * **invitation** names. Written once because a second copy is a second chance to read the
	 * institution off the form by mistake, which is the shape of every fence bug this module
	 * has had. It does not return when it refuses.
	 *
	 * @param string $action  The action whose nonce is expected.
	 * @param int    $post_id The invitation.
	 * @return array The decision, for the audit row's ground.
	 */
	private static function decide_on_invite( $action, $post_id ) {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to change an invitation.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action . '_' . $post_id );

		$post = self::invite_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		return $decision;
	}

	/*
	 * The jobs nobody presses
	 * --------------------------------------------------------------------
	 */

	/**
	 * Close the invitations nobody answered, and forget the ones nobody needs. The cron body.
	 *
	 * Two pieces of housekeeping, one job. An invitation past its fourteen days is moved to
	 * `expired` and its token forgotten, which is what makes the link in a fortnight-old mail
	 * stop working even if somebody re-opens it; and a row that has been settled for longer
	 * than `invite_retention_days` is deleted, because an invitation is an address this site
	 * was asked to write to once and the audit log already records that it did. The states
	 * are recorded before the row goes, so the history survives the address.
	 *
	 * @return int How many invitations were expired.
	 */
	public static function expire() {
		$expired = 0;

		foreach ( self::posts_in_state( self::STATE_PENDING ) as $post ) {
			$post_id = (int) $post->ID;

			if ( ! self::lapsed( $post_id ) ) {
				continue;
			}

			$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
			$email  = (string) get_post_meta( $post_id, self::META_EMAIL, true );

			self::settle( $post_id, self::STATE_EXPIRED );

			if ( WPCPM_Mentors_Sync::is_record_id( $record ) ) {
				WPCPM_Institution_Audit::record(
					array(
						'kind'        => self::LOG_EXPIRED,
						'institution' => $record,
						'subject'     => (string) $post_id,
						'actor'       => 0,
						'ground'      => WPCPM_Institution_Audit::GROUND_SYSTEM,
						'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
						'message'     => sprintf(
							/* translators: 1: the invited address, 2: how many days an invitation is good for. */
							__( 'The invitation to %1$s expired after %2$s days without being accepted.', 'wpcredits-program-manager' ),
							$email,
							number_format_i18n( self::LIFETIME_DAYS )
						),
						'data'        => array( 'invite' => $post_id ),
					)
				);
			}

			++$expired;
		}

		self::forget_settled();

		return $expired;
	}

	/**
	 * Cancel every invitation an institution has waiting.
	 *
	 * The last-member rule's first half (spec 5.1): when nobody is left to act for an
	 * institution, nobody is left to vouch for the people it was adding, so the links stop
	 * working rather than admitting a stranger to a school with no members. Called by
	 * `WPCPM_Institution_Members::detach()`.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @return int How many were cancelled.
	 */
	public static function cancel_for_institution( $record_id ) {
		$cancelled = 0;

		foreach ( self::pending_for( $record_id ) as $post_id ) {
			self::settle( $post_id, self::STATE_CANCELLED );
			self::log_system_cancel( $post_id, __( 'The invitation was cancelled: no member is left to act for this institution.', 'wpcredits-program-manager' ) );
			++$cancelled;
		}

		return $cancelled;
	}

	/**
	 * Cancel every invitation one account issued and nobody has answered.
	 *
	 * A membership that has ended is not a recommendation that outlives it: the person who
	 * vouched for the invitee is no longer someone this institution's members chose. Called
	 * by `WPCPM_Institution_Members::detach()`.
	 *
	 * @param int $user_id The account whose invitations end with its membership.
	 * @return int How many were cancelled.
	 */
	public static function cancel_by_actor( $user_id ) {
		$user_id   = absint( $user_id );
		$cancelled = 0;

		if ( ! $user_id ) {
			return 0;
		}

		foreach ( self::posts_in_state( self::STATE_PENDING ) as $post ) {
			$post_id = (int) $post->ID;

			if ( (int) get_post_meta( $post_id, self::META_ACTOR, true ) !== $user_id || self::lapsed( $post_id ) ) {
				continue;
			}

			self::settle( $post_id, self::STATE_CANCELLED );
			self::log_system_cancel( $post_id, __( 'The invitation was cancelled: the member who sent it is no longer one.', 'wpcredits-program-manager' ) );
			++$cancelled;
		}

		return $cancelled;
	}

	/**
	 * Delete every invitation. Called on uninstall.
	 *
	 * Post meta goes with the posts, so no `delete_metadata()` line is needed for it.
	 */
	public static function delete_all() {
		$invites = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $invites as $invite_id ) {
			wp_delete_post( $invite_id, true );
		}
	}

	/*
	 * The row
	 * --------------------------------------------------------------------
	 */

	/**
	 * Write one invitation, without its token.
	 *
	 * The title carries the record and the date and never the address: the address is meta,
	 * where the rest of the row's facts are, and a title is the one field of a post that
	 * every generic admin list would print if one were ever exposed.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param string $email     The invited address, lowercased.
	 * @param int    $actor_id  Who is inviting.
	 * @return int The post ID, or 0.
	 */
	private static function create( $record_id, $email, $actor_id ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => absint( $actor_id ),
				'post_title'  => sprintf(
					/* translators: 1: institution record ID, 2: date and time. */
					__( 'Invitation to %1$s - %2$s', 'wpcredits-program-manager' ),
					$record_id,
					wp_date( 'Y-m-d H:i' )
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_INSTITUTION, (string) $record_id );
		update_post_meta( $post_id, self::META_EMAIL, (string) $email );
		update_post_meta( $post_id, self::META_STATE, self::STATE_PENDING );
		update_post_meta( $post_id, self::META_ACTOR, absint( $actor_id ) );
		update_post_meta( $post_id, self::META_SENT, time() );

		return $post_id;
	}

	/**
	 * Mint a secret for an invitation, store the hash of it, and hand the secret back.
	 *
	 * The only place a token is ever made, and the only place one is ever written down. What
	 * is stored is `wp_hash()` of it: a database read cannot accept an invitation, and this
	 * site cannot re-send a link it has already sent. The clock restarts here too, because a
	 * resent invitation is the one whose fourteen days the recipient is counting.
	 *
	 * @param int $post_id The invitation.
	 * @return string The secret, for the mail and for nothing else.
	 */
	private static function issue( $post_id ) {
		$token = wp_generate_password( self::TOKEN_LENGTH, false );

		update_post_meta( (int) $post_id, self::META_TOKEN, self::token_hash( $token ) );
		update_post_meta( (int) $post_id, self::META_SENT, time() );

		return $token;
	}

	/**
	 * Move an invitation out of `pending`, forget its token, and stamp the moment.
	 *
	 * The token goes with the state change rather than being left to the state check: a
	 * settled row cannot be found by a token at all afterwards, so a leaked link is dead even
	 * if a later reader of this class gets the state machine wrong. Everything else about the
	 * row is kept, because the card and the log both read it.
	 *
	 * The moment is stamped here because this is the only way out of `pending`, and it is
	 * what `forget_settled()` measures the retention window from. The post date will not do:
	 * that is when the invitation was *sent*, so a row cancelled an hour after a month-old
	 * send would be deleted the same night.
	 *
	 * @param int    $post_id The invitation.
	 * @param string $state   One of the `STATE_*` values.
	 */
	private static function settle( $post_id, $state ) {
		update_post_meta( (int) $post_id, self::META_STATE, (string) $state );
		update_post_meta( (int) $post_id, self::META_TOKEN, '' );
		update_post_meta( (int) $post_id, self::META_SETTLED, time() );
	}

	/**
	 * The hash an invitation is found by.
	 *
	 * Salted with the site's own keys through `wp_hash()`, and prefixed so a token from this
	 * class can never collide with a hash written by another one.
	 *
	 * @param string $token The secret from the link.
	 * @return string
	 */
	private static function token_hash( $token ) {
		return wp_hash( 'wpcpm-institution-invite|' . (string) $token );
	}

	/**
	 * The invitation a secret names, or null.
	 *
	 * The shape is checked before anything is hashed or queried, so a pasted paragraph opens
	 * no query at all. The hash the database matched is compared again in PHP with
	 * `hash_equals()`: the query matched under the database collation, which is a looser
	 * comparison than the one a credential deserves.
	 *
	 * @param string $token The secret from the link.
	 * @return WP_Post|null
	 */
	private static function by_token( $token ) {
		$token = trim( (string) $token );

		if ( ! preg_match( '/^[A-Za-z0-9]{' . (int) self::TOKEN_LENGTH . '}$/', $token ) ) {
			return null;
		}

		$hash  = self::token_hash( $token );
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => 5,
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_TOKEN,
						'value' => $hash,
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && hash_equals( (string) get_post_meta( (int) $post->ID, self::META_TOKEN, true ), $hash ) ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * One invitation post, or null for anything that is not one.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	private static function invite_post( $post_id ) {
		$post = get_post( absint( $post_id ) );

		return ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) ? $post : null;
	}

	/**
	 * An invitation's state.
	 *
	 * @param int $post_id The invitation.
	 * @return string One of the `STATE_*` values, or ''.
	 */
	private static function state_of( $post_id ) {
		return (string) get_post_meta( absint( $post_id ), self::META_STATE, true );
	}

	/**
	 * Whether an invitation's fourteen days have run out.
	 *
	 * Read from the row rather than from the state, so an invitation that lapsed before the
	 * nightly job ran is already dead: the cron tidies the record, it does not decide the
	 * question. A row with no send time is lapsed, because a row this class did not finish
	 * writing must not be acceptable for ever.
	 *
	 * @param int $post_id The invitation.
	 * @return bool
	 */
	private static function lapsed( $post_id ) {
		$sent = (int) get_post_meta( absint( $post_id ), self::META_SENT, true );

		if ( $sent < 1 ) {
			return true;
		}

		return time() > ( $sent + ( self::LIFETIME_DAYS * DAY_IN_SECONDS ) );
	}

	/**
	 * When an invitation stops working.
	 *
	 * @param int $sent When it was sent, unix.
	 * @return int Unix.
	 */
	private static function expires_at( $sent ) {
		return (int) $sent + ( self::LIFETIME_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * One invitation as facts.
	 *
	 * @param WP_Post $post The invitation.
	 * @return array
	 */
	private static function facts_of( WP_Post $post ) {
		$post_id = (int) $post->ID;
		$sent    = (int) get_post_meta( $post_id, self::META_SENT, true );
		$actor   = (int) get_post_meta( $post_id, self::META_ACTOR, true );
		$state   = self::state_of( $post_id );

		return array(
			'id'         => $post_id,
			'email'      => (string) get_post_meta( $post_id, self::META_EMAIL, true ),
			'state'      => $state,
			'waiting'    => ( self::STATE_PENDING === $state && ! self::lapsed( $post_id ) ),
			'sent'       => $sent,
			'expires'    => self::expires_at( $sent ),
			'actor'      => $actor,
			'actor_name' => self::actor_name( $actor ),
			'user'       => (int) get_post_meta( $post_id, self::META_USER, true ),
		);
	}

	/**
	 * Every invitation for one institution, newest first.
	 *
	 * Guarded here: a malformed ID returns nothing and issues no query, because a query for an
	 * empty stamp would return every row that has none. The rows the database matched are
	 * checked again with `strcmp()`, which tells `recABC` from `recabc` where the collation
	 * does not.
	 *
	 * The default reads two hundred, which is more than a month of the daily ceiling at its
	 * limit, and the rows come back newest first: the waiting ones are always the newest,
	 * because nothing waits longer than fourteen days.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param int    $limit     Most rows to read.
	 * @return WP_Post[]
	 */
	private static function posts_for( $record_id, $limit = 200 ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => (int) $limit > 0 ? (int) $limit : 200,
				'orderby'          => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_INSTITUTION,
						'value' => $record_id,
					),
				),
			)
		);

		$mine = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && 0 === strcmp( (string) get_post_meta( (int) $post->ID, self::META_INSTITUTION, true ), $record_id ) ) {
				$mine[] = $post;
			}
		}

		return $mine;
	}

	/**
	 * Every invitation in one state, across every institution. The jobs' query.
	 *
	 * @param string $state One of the `STATE_*` values.
	 * @return WP_Post[]
	 */
	private static function posts_in_state( $state ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => 500,
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_STATE,
						'value' => (string) $state,
					),
				),
			)
		);

		$rows = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && self::state_of( (int) $post->ID ) === (string) $state ) {
				$rows[] = $post;
			}
		}

		return $rows;
	}

	/**
	 * Whether an institution already has an invitation waiting for an address.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param string $email     Lowercased address.
	 * @return bool
	 */
	private static function pending_to( $record_id, $email ) {
		foreach ( self::pending_for( $record_id ) as $post_id ) {
			if ( strtolower( trim( (string) get_post_meta( $post_id, self::META_EMAIL, true ) ) ) === (string) $email ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete the invitations that have been settled for longer than the site keeps them.
	 *
	 * `invite_retention_days`, 30 by default (spec 8.3, and the retention figures the privacy
	 * notice states). What is deleted is the address; what is kept is the audit row, which
	 * says an invitation was sent, to whom, and what became of it.
	 *
	 * **Measured from when the row settled and not from when it was written.** Those are the
	 * same day for an invitation that runs its fourteen days out, and a month apart for one
	 * cancelled long after it was sent; measuring from the post date deletes the second the
	 * night after it is cancelled, which is not thirty days of anything. The stamp `settle()`
	 * writes is the one clock this reads.
	 *
	 * @return int How many rows were deleted.
	 */
	private static function forget_settled() {
		$days = max( 7, (int) WPCPM_Settings::get_value( 'invite_retention_days', 30 ) );
		$now  = time();
		$cut  = $now - ( $days * DAY_IN_SECONDS );
		$gone = 0;

		foreach ( array( self::STATE_ACCEPTED, self::STATE_CANCELLED, self::STATE_EXPIRED ) as $state ) {
			foreach ( self::posts_in_state( $state ) as $post ) {
				$post_id = (int) $post->ID;
				$settled = (int) get_post_meta( $post_id, self::META_SETTLED, true );

				// A row settled by a version that did not stamp the moment. Guessing one from
				// the post date is the very thing this reads a stamp to avoid, so the window
				// starts at the first sweep that sees the row: the site keeps it a little
				// longer than it promised, which is the harmless half of the mistake.
				if ( $settled < 1 ) {
					update_post_meta( $post_id, self::META_SETTLED, $now );
					continue;
				}

				if ( $settled > $cut ) {
					continue;
				}

				wp_delete_post( $post_id, true );
				++$gone;
			}
		}

		return $gone;
	}

	/*
	 * The people, the mail and the answers
	 * --------------------------------------------------------------------
	 */

	/**
	 * The account an acceptance is for: the signed-in one, an existing one, or a new one.
	 *
	 * A signed-in visitor whose address is not the invited one does not get an account back;
	 * they get 0, and the caller answers with the one message. `invitation_for()` has already
	 * refused them by then, and this asks `visitor_may_accept()` all the same: it is the last
	 * thing between a mismatched session and an `attach()`, and the two must never be able to
	 * disagree about who is signed in.
	 *
	 * Everybody else is either the account that already holds the address or a new account
	 * made for it.
	 *
	 * @param string $email The invited address, lowercased.
	 * @return int User ID, or 0.
	 */
	private static function account_for( $email ) {
		$user = wp_get_current_user();

		if ( is_user_logged_in() && $user instanceof WP_User && $user->exists() ) {
			return self::visitor_may_accept( $email ) ? (int) $user->ID : 0;
		}

		$existing = get_user_by( 'email', $email );

		if ( $existing instanceof WP_User && $existing->exists() ) {
			return (int) $existing->ID;
		}

		return self::create_account( $email );
	}

	/**
	 * Make an account for somebody who accepted an invitation and had none.
	 *
	 * The same shape as the manager backstop's: a free login from the address, a random
	 * password nobody knows, and the Institution role. The set-your-password message goes
	 * through `WPCPM_Mail::queue_invites()`, the one route every account of this plugin's is
	 * invited by, so the send is logged and rate-limited like the rest. The display name is
	 * the login until they change it: this class asks for an address and not for a name, and
	 * inventing one from the local part of an address is how people end up greeted as `j.doe`
	 * for ever with nobody to blame.
	 *
	 * @param string $email The invited address.
	 * @return int User ID, or 0.
	 */
	private static function create_account( $email ) {
		$login   = WPCPM_Institution_People::unique_login( $email, '' );
		$created = WPCPM_Roles::insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $login,
				'nickname'     => $login,
				'role'         => WPCPM_Roles::ROLE_INSTITUTION,
			)
		);

		if ( is_wp_error( $created ) ) {
			return 0;
		}

		WPCPM_Mail::queue_invites( array( (int) $created ) );

		return (int) $created;
	}

	/**
	 * Whether any live member holds an address.
	 *
	 * Compared lowercased and trimmed on both sides: addresses are not case-sensitive, and
	 * the values this module meets regularly arrive with a space on the end.
	 *
	 * @param WP_User[] $members Live members.
	 * @param string    $address Lowercased, trimmed address.
	 * @return bool
	 */
	private static function holds_address( array $members, $address ) {
		foreach ( $members as $member ) {
			if ( $member instanceof WP_User && strtolower( trim( (string) $member->user_email ) ) === (string) $address ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The link in the mail.
	 *
	 * @param string $token The secret.
	 * @return string
	 */
	public static function accept_url( $token ) {
		return add_query_arg(
			array(
				'action'        => self::ACTION_ACCEPT,
				self::TOKEN_ARG => (string) $token,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Send one invitation to an address that usually has no account here.
	 *
	 * Through `WPCPM_Mail::send_to()`, the single exit for mail to somebody with no account:
	 * a message sent straight to `wp_mail()` would go past the filter, the log and the subject
	 * sanitising, which would make the one message this module sends to a stranger the one
	 * message nobody could trace. Reply-to is the member who invited them, because the person
	 * who can answer "why am I getting this" is the colleague who pressed the button.
	 *
	 * @param int    $post_id The invitation.
	 * @param string $token   The secret it was issued with.
	 * @return bool Whether the message was handed off.
	 */
	private static function mail_invite( $post_id, $token ) {
		$post_id = (int) $post_id;
		$email   = (string) get_post_meta( $post_id, self::META_EMAIL, true );
		$record  = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
		$actor   = (int) get_post_meta( $post_id, self::META_ACTOR, true );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$site    = WPCPM_Mail::site_name();
		$school  = self::institution_name( $record );
		$who     = self::actor_name( $actor );
		$link    = self::accept_url( $token );
		$until   = wp_date( 'Y-m-d', self::expires_at( time() ) );
		$sender  = get_user_by( 'id', $actor );
		$headers = WPCPM_Mail::reply_to( $sender instanceof WP_User ? $sender : null );

		$build = function () use ( $site, $school, $who, $link, $until, $headers ) {
			$lines = array(
				sprintf(
					/* translators: 1: the member who invited them, 2: the institution's name, 3: the site's name. */
					__( '%1$s has invited you to see %2$s\'s students in the WordPress Credits Program on %3$s.', 'wpcredits-program-manager' ),
					$who,
					$school,
					$site
				),
				__( 'Open this link, and press Accept on the page it opens:', 'wpcredits-program-manager' ),
				$link,
				sprintf(
					/* translators: %s: the date the invitation stops working. */
					__( 'The link works until %s and can only be used once. If you do not have an account here yet, one is made for you and you get a second email with a link to set your password.', 'wpcredits-program-manager' ),
					$until
				),
				__( 'If you were not expecting this, ignore this message: opening the link only shows you the invitation, nothing happens until you press Accept, and it stops working on its own. Replying to this message reaches the person who sent it.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: the institution's name. */
					__( '[%1$s] An invitation to %2$s', 'wpcredits-program-manager' ),
					$site,
					$school
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => $headers,
			);
		};

		return (bool) WPCPM_Mail::send_to( $email, self::MAIL_INVITE, $build );
	}

	/**
	 * The one answer every failed acceptance gets.
	 *
	 * Expired, cancelled, already accepted, never existed, or followed while signed in as
	 * somebody else: one sentence, byte for byte. Telling them apart would let a stranger
	 * walking tokens learn which addresses this program has invited, and would tell whoever a
	 * forwarded mail reached that the address it was sent to is real.
	 *
	 * **This does not return.**
	 */
	private static function refuse_accept() {
		wp_die(
			esc_html__( 'That invitation cannot be used. It may have been accepted already, cancelled, or expired, or it may have been sent to a different address than the one you are signed in with. Ask whoever invited you to send a new one.', 'wpcredits-program-manager' ),
			403
		);
	}

	/**
	 * Write one audit row about an invitation, carrying the decision's ground.
	 *
	 * @param string $kind     One of the `LOG_*` values.
	 * @param int    $post_id  The invitation.
	 * @param array  $decision What `decide()` returned.
	 * @param string $evidence What the decision was made against.
	 * @param string $message  What happened, in one sentence.
	 */
	private static function log( $kind, $post_id, array $decision, $evidence, $message ) {
		WPCPM_Institution_Audit::record(
			array(
				'kind'        => $kind,
				'institution' => (string) get_post_meta( (int) $post_id, self::META_INSTITUTION, true ),
				'subject'     => (string) (int) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => (string) $evidence,
				'message'     => (string) $message,
				'data'        => array(
					'invite' => (int) $post_id,
					'email'  => (string) get_post_meta( (int) $post_id, self::META_EMAIL, true ),
				),
			)
		);
	}

	/**
	 * Write the audit row for a cancellation nobody pressed.
	 *
	 * The ground is `system` and the actor is 0 for both of these: a rule cancelled them, not
	 * a person, and the row that records the person is the `member_removed` one `detach()`
	 * wrote a moment before.
	 *
	 * @param int    $post_id The invitation.
	 * @param string $message Why.
	 */
	private static function log_system_cancel( $post_id, $message ) {
		$record = (string) get_post_meta( (int) $post_id, self::META_INSTITUTION, true );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_CANCELLED,
				'institution' => $record,
				'subject'     => (string) (int) $post_id,
				'actor'       => 0,
				'ground'      => WPCPM_Institution_Audit::GROUND_SYSTEM,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => (string) $message,
				'data'        => array( 'invite' => (int) $post_id ),
			)
		);
	}

	/**
	 * The ceiling key for one member's sends.
	 *
	 * Shaped like the agreement upload's, `prefix:record`, with the member on the end because
	 * the five a day are theirs and not the institution's: see the class docblock for why
	 * that is the figure. The record stays in the key even though an account holds one
	 * membership today, so the day a second one ships the two are counted apart rather than
	 * silently sharing an allowance.
	 *
	 * Built here rather than through `WPCPM_Ceiling::key()`, which lowercases what it is
	 * given: a record ID is case-sensitive, and `recABC` and `recabc` are different
	 * institutions. Nothing readable ends up in the options table either way, because
	 * `WPCPM_Ceiling` names its row after an md5 of the key and the bucket.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param int    $user_id   The member whose day it counts against.
	 * @return string
	 */
	private static function ceiling_key( $record_id, $user_id ) {
		return 'invite:' . (string) $record_id . ':' . absint( $user_id );
	}

	/**
	 * A person's name for a message, or ''.
	 *
	 * @param int $user_id The account.
	 * @return string
	 */
	private static function actor_name( $user_id ) {
		$user = $user_id ? get_user_by( 'id', (int) $user_id ) : null;

		return $user instanceof WP_User ? (string) $user->display_name : '';
	}

	/**
	 * The institution's name as printed, or its record ID.
	 *
	 * The index keeps the name as Airtable stores it, trailing space and all; every renderer
	 * trims. A record the index has not read yet is named by its ID rather than by nothing,
	 * so an invitation never says "an invitation to  's students".
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @return string
	 */
	private static function institution_name( $record_id ) {
		$row  = WPCPM_Institutions_Index::row( $record_id );
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : (string) $record_id;
	}

	/**
	 * Record the outcome and return to the page the control was on.
	 *
	 * The same shape as the People card's `finish()`, deliberately: the two sets of controls
	 * sit in one card, and an outcome from one that landed somewhere else would read as the
	 * page being broken. The destination is rebuilt from a flag rather than taken from the
	 * request, so no form can bounce a member somewhere else.
	 *
	 * **This does not return.**
	 *
	 * @param string $status Outcome slug, one of the keys `messages()` knows.
	 * @param string $detail What the message adds: usually the address.
	 * @param string $record The institution the outcome is about.
	 */
	private static function finish( $status, $detail, $record ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'status' => (string) $status,
				'detail' => (string) $detail,
				'record' => trim( (string) $record ),
			)
		);

		$anchor = '#' . WPCPM_Institution_People::ANCHOR;

		if ( WPCPM_Institution_People::RETURN_ADMIN === WPCPM_Request::posted_key( 'wpcpm_from' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpcpm-institutions' ) . $anchor );
			exit;
		}

		wp_safe_redirect( self::dashboard_url() . $anchor );
		exit;
	}

	/**
	 * The institution dashboard, or the front page while it does not exist.
	 *
	 * @return string
	 */
	private static function dashboard_url() {
		$url = (string) WPCPM_Institutions_Dashboard::page_url();

		return '' !== $url ? $url : home_url( '/' );
	}
}
