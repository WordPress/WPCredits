<?php
/**
 * Institutions module - the People card and the three membership handlers.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who can act for an institution on this site, and the three ways that list changes.
 *
 * The card at the foot of an institution's dashboard names every account that can see this
 * school's students, how each one came by that access and since when, and carries a Remove
 * on every row, Leave on the viewer's own. Equal power is the design, not an oversight:
 * one member may remove any other, including the account the sync provisioned first. The
 * mitigation is that it cannot be done quietly. The confirm names the person, the school
 * and everyone who will be told; `detach()` writes an audit row and, when the last member
 * goes, tells the program managers; and a manager puts somebody back in one click from
 * `former_members_of()`.
 *
 * **The subject of a removal is placed by the subject's own stamp and never by the form.**
 * A member of B who posts a member of A's user ID is decided against A, is not a member of
 * A, and gets the one refusal. Reading the institution out of the request instead would
 * make the fence a suggestion, which is the shape of every fence bug this module has had.
 *
 * The program's contact, the address Airtable holds for the school, is marked on the row
 * whose account carries it and **nothing at all is attached to that mark**: it is
 * information about who the program writes to, not a rank. The mark used to take the row's
 * control away, on both surfaces, which meant a contact who left the school could not be
 * removed by their colleagues, by a program manager, or by themselves, and an institution
 * whose contact had gone could not be repaired from anywhere. The row now carries the same
 * control as every other, and the fact a manager relies on that address is said in the
 * confirm, where a warning belongs, rather than enforced by a control nobody has.
 *
 * Invitations ship in Phase 4. Until then the only way an account is created is the manager
 * backstop on the Institutions screen, which `render_manager()` draws and the two manager
 * handlers here serve: Add account, Re-add, Remove.
 */
class WPCPM_Institution_People {

	/** Remove a member, or leave. Nonce keyed to the subject account. */
	const ACTION_REMOVE_MEMBER = 'wpcpm_remove_member';

	/** The manager backstop: create or adopt an account for an institution. */
	const ACTION_ADD_ACCOUNT = 'wpcpm_add_institution_account';

	/** The manager backstop: put a former member back. */
	const ACTION_READD = 'wpcpm_readd_institution_member';

	/**
	 * Flash channel, read by both renderers.
	 *
	 * The value carries the institution it is about, so a screen drawing the backstop under
	 * every pipeline row prints it under one of them and not under all of them.
	 */
	const FLASH = 'institution_people';

	/** Where a handler returns to, as a posted flag and never as a posted URL. */
	const RETURN_ADMIN = 'admin';

	/** The card's anchor, so a removal lands back on the list it changed. */
	const ANCHOR = 'wpcpm-people';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_REMOVE_MEMBER, array( __CLASS__, 'handle_remove_member' ) );
		add_action( 'admin_post_' . self::ACTION_ADD_ACCOUNT, array( __CLASS__, 'handle_add_account' ) );
		add_action( 'admin_post_' . self::ACTION_READD, array( __CLASS__, 'handle_readd' ) );
	}

	/*
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The People card at the foot of an institution's dashboard.
	 *
	 * Two decisions, both asked of the policy and neither re-implemented here: reading the
	 * card is `ACT_VIEW_ROSTER`, which the agreement gate closes for a member whose agreement
	 * is not settled, and the controls are `ACT_MANAGE_MEMBERS`. A refused viewer is drawn
	 * nothing at all rather than an empty card, because an empty card is itself an answer.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @param array  $context   The dashboard's context. Only `can_manage` is read here; the
	 *                          comment below says why the roster's read time is not this card's.
	 */
	public static function render( $record_id, array $context ) {
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
		$can_manage = ! empty( $context['can_manage'] );
		// The context's `read` is the roster index's, which is the read time of the student
		// rows the other cards draw. This card draws no roster row: its one stale fact is the
		// institution's own name and contact address, so it prints when the pipeline index was
		// read and lets the roster's footer speak for the roster.
		$read    = self::index_read();
		$members = WPCPM_Institution_Members::members_of( $record_id );
		$name    = self::institution_name( $record_id );
		$contact = self::contact_email( $record_id );
		$viewer  = get_current_user_id();

		// The person Airtable names, when no account here holds their address. Forty of the
		// forty-two confirmed institutions have a contact and no account at all, so without
		// this row the card said "Institution representatives 0" directly under a header
		// naming that very person - a card that contradicts the page it sits on.
		$contact_name  = self::contact_person( $record_id );
		$contact_shown = ( '' !== $contact || '' !== $contact_name )
			&& ! self::holds_address( $members, $contact );

		// The card's box, heading, read line and empty line are the shell's shared classes, so
		// four cards written in four commits cannot end up four slightly different boxes; the
		// `wpcpm-people` hooks style only what is this card's own.
		printf( '<section class="wpcpm-institution__card wpcpm-people" id="%s">', esc_attr( self::ANCHOR ) );

		// The same disclosure the roster's groups and the enrolment form are drawn in, open by
		// default: a short list of colleagues is not something to hide, but a page on which
		// every other panel folds and this one does not hands the reader a chevron that works
		// on one row and is missing on the next. Same classes on purpose, so the theme's one
		// set of rules for the fold, the chevron and the inset applies here without a copy.
		printf(
			'<details class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><span class="wpcpm-group__title">%1$s <span class="wpcpm-group__count wpcpm-people__count">%2$s</span></span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body">',
			esc_html__( 'Institution representatives', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $members ) + ( $contact_shown ? 1 : 0 ) ) )
		);

		printf(
			'<p class="wpcpm-people__intro">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: institution name. */
					__( 'Everyone here with an account can see %s\'s students on this site, and can remove anyone, including themselves. A removal keeps the account and tells the people who are left.', 'wpcredits-program-manager' ),
					$name
				)
			)
		);

		self::render_message( $record_id );

		if ( $members || $contact_shown ) {
			echo '<ul class="wpcpm-people__list">';

			foreach ( $members as $member ) {
				self::render_member( $member, $members, $name, $contact, $viewer, $can_change, '' );
			}

			// After the accounts, because the accounts are the rows that can be acted on.
			if ( $contact_shown ) {
				self::render_contact_row( $contact_name, self::contact_email_raw( $record_id ) );
			}

			echo '</ul>';
		}

		// Still said whenever no account exists, contact row or not: the row names who the
		// program writes to, and this line answers the different question of who can open
		// this page. An institution can have the first and not the second, and today most do.
		if ( empty( $members ) ) {
			printf(
				'<p class="wpcpm-institution__empty wpcpm-people__empty">%s</p>',
				esc_html__( 'Nobody can act for this institution on this site right now.', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<p class="wpcpm-people__note">%s</p>',
			esc_html__( 'Inviting a colleague from this page ships with a later release. Until then a program manager adds an account for them.', 'wpcredits-program-manager' )
		);

		if ( $can_manage ) {
			printf(
				'<p class="wpcpm-people__note"><a href="%1$s">%2$s</a></p>',
				esc_url( self::manager_url() ),
				esc_html__( 'Add or re-add an account on the Institutions screen', 'wpcredits-program-manager' )
			);
		}

		self::render_read_line( $read );

		echo '</div></details>';
		echo '</section>';
	}

	/**
	 * One member's row.
	 *
	 * The program's contact is marked and the mark takes nothing away: the row carries the
	 * same control as every other one, because a contact who has left the school has to be
	 * removable by their colleagues and by a program manager alike. What the mark does is
	 * put a sentence in that row's confirm saying Airtable still names the address, so the
	 * person pressing Remove knows the school is about to have no contact on record.
	 *
	 * @param WP_User   $member     The account being drawn.
	 * @param WP_User[] $members    Every live member, for the count the confirm names.
	 * @param string    $name       Institution name, as printed.
	 * @param string    $contact    The program's contact address, lowercased, or ''.
	 * @param int       $viewer     Who is looking, so their own row offers Leave.
	 * @param bool      $can_change Whether the viewer may remove anybody at all.
	 * @param string    $origin     `RETURN_ADMIN` from the manager screen, '' from the dashboard.
	 */
	private static function render_member( WP_User $member, array $members, $name, $contact, $viewer, $can_change, $origin ) {
		$is_contact = ( '' !== $contact && strtolower( trim( (string) $member->user_email ) ) === $contact );
		$is_self    = ( (int) $viewer === (int) $member->ID );

		echo '<li class="wpcpm-people__member">';

		printf(
			'<span class="wpcpm-people__name">%s</span>',
			esc_html( '' !== trim( (string) $member->display_name ) ? $member->display_name : $member->user_login )
		);

		printf( ' <span class="wpcpm-people__email">%s</span>', esc_html( $member->user_email ) );

		if ( $is_contact ) {
			printf(
				' <span class="wpcpm-people__mark" title="%1$s">%2$s</span>',
				esc_attr__( 'This is the address Airtable holds for the institution. It is shown so everybody knows who the program writes to.', 'wpcredits-program-manager' ),
				esc_html__( 'the program\'s contact', 'wpcredits-program-manager' )
			);
		}

		printf( ' <span class="wpcpm-people__facts">%s</span>', esc_html( self::facts_line( $member ) ) );

		if ( $can_change ) {
			self::render_remove_form( $member, $members, $name, $is_self, $is_contact, $origin );
		}

		echo '</li>';
	}

	/**
	 * The contact Airtable names, who holds no account here.
	 *
	 * **A representative of the institution, not of this site.** The card answers "who speaks
	 * for this school", and until now it answered it with the accounts alone - so a school
	 * whose contact has never signed in read as having nobody, three lines under a header
	 * naming that person. Two of the forty-two confirmed institutions have an account; every
	 * other page said zero.
	 *
	 * The row carries no control, because there is nothing here to remove: it is a fact about
	 * the program's records, and it goes away by itself the moment somebody adds the account,
	 * since the address then matches a member and `holds_address()` suppresses this row.
	 *
	 * The status is stated rather than implied. A row that named the person and stopped would
	 * read as access, which is the one thing it is not; and where the address is missing from
	 * the base and only a name is known, that name may in fact belong to one of the accounts
	 * above under a different address. Forty-one of the forty-two carry both, so the pairing
	 * is reliable in practice and the wording does not overclaim where it is not.
	 *
	 * @param string $name  The contact's name as Airtable holds it, or ''.
	 * @param string $email The contact's address as Airtable holds it, or ''.
	 */
	private static function render_contact_row( $name, $email ) {
		$name  = trim( (string) $name );
		$email = trim( (string) $email );

		echo '<li class="wpcpm-people__member wpcpm-people__member--contact">';

		printf(
			'<span class="wpcpm-people__name">%s</span>',
			esc_html( '' !== $name ? $name : __( 'Name not recorded', 'wpcredits-program-manager' ) )
		);

		if ( '' !== $email ) {
			printf( ' <span class="wpcpm-people__email">%s</span>', esc_html( $email ) );
		}

		printf(
			' <span class="wpcpm-people__mark" title="%1$s">%2$s</span>',
			esc_attr__( 'This is the person Airtable holds for the institution. It is shown so everybody knows who the program writes to.', 'wpcredits-program-manager' ),
			esc_html__( 'the program\'s contact', 'wpcredits-program-manager' )
		);

		printf(
			' <span class="wpcpm-people__facts wpcpm-people__status">%s</span>',
			esc_html__( 'Named in the program records, with no account on this site yet. A program manager can add one so they can see this page.', 'wpcredits-program-manager' )
		);

		echo '</li>';
	}

	/**
	 * The Remove control, or Leave on the viewer's own row.
	 *
	 * The nonce is keyed to the subject account and to nothing else: one member's Remove
	 * button is not a token for removing another. The institution is not in the form at all,
	 * because the handler reads it from the subject's own stamp.
	 *
	 * @param WP_User   $member     The account the control acts on.
	 * @param WP_User[] $members    Every live member, for the count the confirm names.
	 * @param string    $name       Institution name, as printed.
	 * @param bool      $is_self    Whether the viewer is removing themselves.
	 * @param bool      $is_contact Whether this row holds the address Airtable names.
	 * @param string    $origin     `RETURN_ADMIN` from the manager screen, '' from the dashboard.
	 */
	private static function render_remove_form( WP_User $member, array $members, $name, $is_self, $is_contact, $origin ) {
		$others = max( 0, count( $members ) - 1 );

		echo '<form class="wpcpm-people__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_REMOVE_MEMBER . '_' . (int) $member->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_REMOVE_MEMBER ) );
		printf( '<input type="hidden" name="member" value="%d" />', (int) $member->ID );

		if ( self::RETURN_ADMIN === $origin ) {
			printf( '<input type="hidden" name="wpcpm_from" value="%s" />', esc_attr( self::RETURN_ADMIN ) );
		}

		printf(
			'<button type="submit" class="wpcpm-people__remove" onclick="return confirm(%1$s)">%2$s</button>',
			esc_attr( wp_json_encode( self::confirm_text( $member, $name, $is_self, $is_contact, $others ) ) ),
			esc_html(
				$is_self
					? __( 'Leave', 'wpcredits-program-manager' )
					: __( 'Remove', 'wpcredits-program-manager' )
			)
		);

		echo '</form>';
	}

	/**
	 * What the confirm says.
	 *
	 * The wording the design spec fixes, in four cases: removing somebody while others
	 * remain, removing the last member, leaving while others remain, and leaving as the last
	 * member, which is allowed and warned about rather than refused. It names the person, the
	 * institution, who else is told and that the account is kept, because a removal that read
	 * as a deletion is the one somebody would undo by creating a second account.
	 *
	 * A fifth sentence is added on the row Airtable names as the contact. That is the whole
	 * of what the contact mark does now: the control it used to take away left an institution
	 * whose contact had left unrepairable from either surface, and the address matters to a
	 * program manager, not to the fence. So it is said, not enforced.
	 *
	 * The mails to the members themselves ship with the agreement phase; the text is the one
	 * the spec settles, so it does not have to change under the people who learned it.
	 *
	 * @param WP_User $member     The account the control acts on.
	 * @param string  $name       Institution name, as printed.
	 * @param bool    $is_self    Whether the viewer is removing themselves.
	 * @param bool    $is_contact Whether this row holds the address Airtable names.
	 * @param int     $others     How many members would be left.
	 * @return string
	 */
	private static function confirm_text( WP_User $member, $name, $is_self, $is_contact, $others ) {
		$who  = '' !== trim( (string) $member->display_name ) ? $member->display_name : $member->user_login;
		$note = $is_contact
			? ' ' . __( 'Airtable still names this address as the institution\'s contact: a program manager changes that there.', 'wpcredits-program-manager' )
			: '';

		if ( $is_self && 0 === (int) $others ) {
			return sprintf(
				/* translators: %s: institution name. */
				__( 'You are the last member of %s. Leave anyway? Nobody will be left who can act for it on this site, the program managers will be told, and your account is kept.', 'wpcredits-program-manager' ),
				$name
			) . $note;
		}

		if ( $is_self ) {
			return sprintf(
				/* translators: 1: institution name, 2: number of other members. */
				_n(
					'Leave %1$s on this site? You will lose access to its students, the other %2$s member will be emailed, and your account is kept.',
					'Leave %1$s on this site? You will lose access to its students, the other %2$s members will be emailed, and your account is kept.',
					(int) $others,
					'wpcredits-program-manager'
				),
				$name,
				number_format_i18n( (int) $others )
			) . $note;
		}

		if ( 0 === (int) $others ) {
			return sprintf(
				/* translators: 1: member name, 2: institution name. */
				__( 'Remove %1$s\'s access to %2$s\'s students on this site? They will be emailed that their access was removed, nobody will be left who can act for the institution, the program managers will be told, and the account is kept.', 'wpcredits-program-manager' ),
				$who,
				$name
			) . $note;
		}

		return sprintf(
			/* translators: 1: member name, 2: institution name, 3: number of other members. */
			_n(
				'Remove %1$s\'s access to %2$s\'s students on this site? They will be emailed that their access was removed, and so will the other %3$s member. The account is kept.',
				'Remove %1$s\'s access to %2$s\'s students on this site? They will be emailed that their access was removed, and so will the other %3$s members. The account is kept.',
				(int) $others,
				'wpcredits-program-manager'
			),
			$who,
			$name,
			number_format_i18n( (int) $others )
		) . $note;
	}

	/**
	 * How a member came by their access, and since when.
	 *
	 * Facts for people, never a fence: the policy reads the stamp and the flag and nothing
	 * here. A membership written before the facts were recorded says so rather than guessing
	 * at a date it does not have.
	 *
	 * @param WP_User $member The account.
	 * @return string
	 */
	private static function facts_line( WP_User $member ) {
		$facts = get_user_meta( $member->ID, WPCPM_Institution_Members::META_MEMBERSHIP, true );
		$how   = is_array( $facts ) && isset( $facts['how'] ) ? (string) $facts['how'] : '';
		$since = is_array( $facts ) && isset( $facts['since'] ) ? (int) $facts['since'] : 0;
		$label = self::how_label( $how );

		if ( ! $since ) {
			return sprintf(
				/* translators: %s: how the membership came about. */
				__( '%s; the date was not recorded.', 'wpcredits-program-manager' ),
				$label
			);
		}

		return sprintf(
			/* translators: 1: how the membership came about, 2: date. */
			__( '%1$s, %2$s.', 'wpcredits-program-manager' ),
			$label,
			wp_date( get_option( 'date_format' ), $since )
		);
	}

	/**
	 * The way a membership came about, in words.
	 *
	 * A server-held list, matched against the ways the members module knows. A value it does
	 * not know is named as unrecorded rather than printed raw, because the stamp is shown to
	 * the school and a slug is not an answer.
	 *
	 * @param string $how One of `WPCPM_Institution_Members::hows()`.
	 * @return string
	 */
	private static function how_label( $how ) {
		$labels = array(
			WPCPM_Institution_Members::HOW_PROVISIONED => __( 'Added by the institutions sync', 'wpcredits-program-manager' ),
			WPCPM_Institution_Members::HOW_APPROVED    => __( 'Added when the application was approved', 'wpcredits-program-manager' ),
			WPCPM_Institution_Members::HOW_MANAGER     => __( 'Added by a program manager', 'wpcredits-program-manager' ),
			WPCPM_Institution_Members::HOW_INVITED     => __( 'Joined by invitation', 'wpcredits-program-manager' ),
			WPCPM_Institution_Members::HOW_LEGACY      => __( 'Added before memberships were recorded', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $how ] ) ? $labels[ $how ] : __( 'How this access was given was not recorded', 'wpcredits-program-manager' );
	}

	/*
	 * The manager backstop
	 * --------------------------------------------------------------------
	 */

	/**
	 * The backstop for one institution on the Institutions screen.
	 *
	 * Live members with the same facts the school sees, former members with a Re-add, whether
	 * the address Airtable holds belongs to a member, and the Add account form.
	 *
	 * Two questions, and they are not the same one. The capability says which screen this is:
	 * the backstop is a manager's block on the Institutions screen and a member who reached it
	 * is drawn nothing, because the Add account form it carries is a manager's control and not
	 * a school's. The policy then says whether this record may be acted on at all, and on what
	 * ground, which is the answer the handlers below record in the log. Asking the capability
	 * alone was how the three manager paths came to be the only ones in the module that never
	 * reached `decide()`.
	 *
	 * @param string $record_id Airtable institution record ID.
	 */
	public static function render_manager( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) || ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		$members = WPCPM_Institution_Members::members_of( $record_id );
		$former  = WPCPM_Institution_Members::former_members_of( $record_id );
		$name    = self::institution_name( $record_id );
		$contact = self::contact_email( $record_id );
		$viewer  = get_current_user_id();

		// The same row the dashboard card draws, so one heading means one thing on both
		// screens. The gap line further down is what this screen adds to it: there it is
		// followed by the form that closes the gap.
		$contact_name  = self::contact_person( $record_id );
		$contact_shown = ( '' !== $contact || '' !== $contact_name )
			&& ! self::holds_address( $members, $contact );

		echo '<div class="wpcpm-people wpcpm-people--admin">';

		printf(
			'<h3 class="wpcpm-people__title">%1$s <span class="wpcpm-people__count">%2$s</span></h3>',
			esc_html__( 'Institution representatives', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $members ) + ( $contact_shown ? 1 : 0 ) ) )
		);

		self::render_message( $record_id );

		if ( $members || $contact_shown ) {
			echo '<ul class="wpcpm-people__list">';

			foreach ( $members as $member ) {
				// Every row carries its control, the contact's included: the decision above is
				// what allows them, a refusal having returned before this loop. This block is
				// the backstop for exactly the case a suppressed control created, an institution
				// whose contact has left and whose row nobody could act on.
				self::render_member( $member, $members, $name, $contact, $viewer, true, self::RETURN_ADMIN );
			}

			if ( $contact_shown ) {
				self::render_contact_row( $contact_name, self::contact_email_raw( $record_id ) );
			}

			echo '</ul>';
		}

		if ( empty( $members ) ) {
			printf(
				'<p class="wpcpm-people__empty wpcpm-warning">%s</p>',
				esc_html__( 'Nobody can act for this institution on this site. Nobody at the school can see their own students, answer for the agreement, or be written to here.', 'wpcredits-program-manager' )
			);
		}

		if ( '' !== $contact && ! self::holds_address( $members, $contact ) ) {
			printf(
				'<p class="wpcpm-people__contact-gap">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the contact email Airtable holds. */
						__( 'Airtable names %s as the contact, and no member holds that address. Add it below if that person should see the school\'s students here.', 'wpcredits-program-manager' ),
						self::contact_email_raw( $record_id )
					)
				)
			);
		}

		self::render_former( $former, $record_id );
		self::render_add_form( $record_id );

		printf(
			'<p class="wpcpm-people__note">%s</p>',
			esc_html__( 'Pending invitations appear here once invitations ship. There are none to count yet, which is not the same as none being overdue.', 'wpcredits-program-manager' )
		);

		self::render_read_line( self::index_read() );

		echo '</div>';
	}

	/**
	 * Former members, each with a Re-add.
	 *
	 * The list is the `_was` stamp the members module keeps, which is why a removal is one
	 * click to undo and why nothing here asks the form who used to be a member.
	 *
	 * @param WP_User[] $former    Former members, from `former_members_of()`.
	 * @param string    $record_id Airtable institution record ID.
	 */
	private static function render_former( array $former, $record_id ) {
		if ( empty( $former ) ) {
			return;
		}

		printf( '<h4 class="wpcpm-people__subtitle">%s</h4>', esc_html__( 'Former members', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-people__list wpcpm-people__list--former">';

		foreach ( $former as $member ) {
			echo '<li class="wpcpm-people__member">';
			printf(
				'<span class="wpcpm-people__name">%1$s</span> <span class="wpcpm-people__email">%2$s</span>',
				esc_html( '' !== trim( (string) $member->display_name ) ? $member->display_name : $member->user_login ),
				esc_html( $member->user_email )
			);

			echo '<form class="wpcpm-people__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_READD . '_' . (int) $member->ID );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_READD ) );
			printf( '<input type="hidden" name="member" value="%d" />', (int) $member->ID );
			printf( '<input type="hidden" name="record" value="%s" />', esc_attr( $record_id ) );
			printf(
				'<button type="submit" class="button button-secondary">%s</button>',
				esc_html__( 'Re-add', 'wpcredits-program-manager' )
			);
			echo '</form>';
			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * The Add account form.
	 *
	 * Name and email, and a sentence saying what it refuses: an address that already has an
	 * account is adopted only when that account was a member of this institution or is a
	 * mentor. Anything else is somebody's account to explain, and adopting it would hand a
	 * school a person who never agreed to act for them.
	 *
	 * @param string $record_id Airtable institution record ID.
	 */
	private static function render_add_form( $record_id ) {
		printf( '<h4 class="wpcpm-people__subtitle">%s</h4>', esc_html__( 'Add an account', 'wpcredits-program-manager' ) );

		echo '<form class="wpcpm-people__form wpcpm-people__form--add" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_ADD_ACCOUNT );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_ADD_ACCOUNT ) );
		printf( '<input type="hidden" name="record" value="%s" />', esc_attr( $record_id ) );

		printf(
			'<label class="screen-reader-text" for="wpcpm-people-name-%1$s">%2$s</label>',
			esc_attr( $record_id ),
			esc_html__( 'Name', 'wpcredits-program-manager' )
		);
		printf(
			'<input type="text" id="wpcpm-people-name-%1$s" name="name" value="" placeholder="%2$s" />',
			esc_attr( $record_id ),
			esc_attr__( 'Name', 'wpcredits-program-manager' )
		);

		printf(
			'<label class="screen-reader-text" for="wpcpm-people-email-%1$s">%2$s</label>',
			esc_attr( $record_id ),
			esc_html__( 'Email address', 'wpcredits-program-manager' )
		);
		printf(
			'<input type="email" id="wpcpm-people-email-%1$s" name="email" value="" placeholder="%2$s" required />',
			esc_attr( $record_id ),
			esc_attr__( 'Email address', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="button button-primary">%s</button>',
			esc_html__( 'Add account', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'A new account is created with a random password and sent an invitation. An address that already has an account is only adopted when that account was a member of this institution or is a mentor.', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Remove a member, or leave.
	 *
	 * The nonce is keyed to the subject account, so a token for removing one person is not a
	 * token for removing another; the subject is read before it because the key names it. The
	 * institution comes from the subject's own stamp and never from the form, so a member of
	 * B posting a member of A's user ID is decided against A and refused with the one message.
	 * Removing the last member is allowed: the warning is in the confirm, and `detach()` tells
	 * the program managers.
	 *
	 * The outcome is queued for the institution it happened to, so the card that draws it is
	 * the one whose list changed. On the self path it is queued only when the person leaving
	 * will still be shown a card to read it on, which a member no longer is: see `finish()`.
	 */
	public static function handle_remove_member() {
		$member_id = WPCPM_Request::posted_id( 'member' );

		check_admin_referer( self::ACTION_REMOVE_MEMBER . '_' . $member_id );

		// The subject says whose institution this is. Reading it from the request would be the
		// same check with a stranger's answer.
		$record = WPCPM_Institution_Members::institution_of( $member_id );

		// A membership that has already ended has no live stamp, so the subject is the
		// institution it LEFT, from the `_was` stamp the members class keeps for exactly this
		// kind of afterwards. The fence is asked about that institution all the same: whether
		// somebody's access has ended is a fact about that institution's People card, and a
		// stranger posting user IDs must not learn it.
		$was     = '' === $record ? trim( (string) get_user_meta( $member_id, WPCPM_Institution_Members::META_RECORD_ID_WAS, true ) ) : '';
		$subject = '' !== $record ? $record : $was;

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS,
			WPCPM_Institution_Policy::subject_institution( $subject )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		if ( '' === $record ) {
			// Nothing to detach, and nothing to be quiet about: two people pressing Remove on
			// the same row is ordinary, and the second deserves an answer where they pressed.
			self::finish( 'ended', '', $was );
		}

		$actor   = get_current_user_id();
		$is_self = ( (int) $actor === (int) $member_id );
		$reason  = $is_self ? WPCPM_Institution_Members::REASON_LEFT : WPCPM_Institution_Members::REASON_REMOVED;
		$result  = WPCPM_Institution_Members::detach( $member_id, $reason, $actor );

		if ( is_wp_error( $result ) ) {
			self::finish( 'error', $result->get_error_message(), $record );
		}

		if ( ! $is_self ) {
			self::finish( 'removed', '', $record );
		}

		// Leaving ends the viewer's own membership, so the card refuses to draw for them on the
		// next request and a queued "You have left this institution" would sit in user meta
		// until some later membership greeted them with it. Asked of the policy rather than
		// assumed, because a program manager who is also a member still reads the card and
		// still deserves the sentence. Whoever cannot read it is told by the page they land
		// on, which says their account is not linked to an institution.
		$still = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_VIEW_ROSTER,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		self::finish( empty( $still['allowed'] ) ? '' : 'left', '', $record );
	}

	/**
	 * The manager backstop: create an account for an institution, or adopt one.
	 *
	 * Capability, then nonce, then the policy, in that order (spec 5.4): the capability says
	 * this is the manager's backstop, the nonce says the request was meant, and `decide()`
	 * says whether the record may be acted on at all, so a refusal here is the policy's one
	 * refusal and not a bespoke one. (`attach()` derives the audit row's ground from the actor
	 * itself; the decision is asked for the refusal path, not to feed the log.)
	 *
	 * An address that already has an account is refused unless that account was a member of
	 * this institution or is a mentor: found by email with no membership is a conflict and not
	 * a match, and adopting a student's or an administrator's account would mail a password
	 * link to somebody who never asked for one. `attach()` makes the rest of the identity
	 * rules; this handler does not repeat them.
	 */
	public static function handle_add_account() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( self::ACTION_ADD_ACCOUNT );

		$record = WPCPM_Request::posted_text( 'record' );

		// Shape, then the policy, then the state of the record. The hidden field is written by
		// the block the button sits in, so a value that is not a record ID at all is a forged
		// or a broken form and is answered with the one refusal, as every other bad subject in
		// this module is. Deciding before the index is checked keeps the decision about a
		// well-formed institution, which is what the log reads.
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

		// An institution the site has never read cannot acquire members. `attach()` refuses it
		// too; saying it here names the sync as the fix rather than leaving a generic error.
		if ( ! WPCPM_Institutions_Index::has( $record ) ) {
			self::finish( 'unknown-record', '', $record );
		}

		$email = sanitize_email( WPCPM_Request::posted_text( 'email' ) );
		$name  = WPCPM_Request::posted_text( 'name' );

		if ( ! is_email( $email ) ) {
			self::finish( 'bad-email', '', $record );
		}

		$existing = get_user_by( 'email', $email );
		$user_id  = 0;

		if ( $existing instanceof WP_User && $existing->exists() ) {
			// Named before the conflict, because it is not one: a manager who adds the address
			// that is already on the list above deserves to be told that, not told their
			// colleague's account is a stranger's.
			if ( self::is_member( $existing, $record ) ) {
				self::finish( 'already', '', $record );
			}

			$adoptable = self::was_member( $existing, $record )
				|| WPCPM_Roles::user_has_role( $existing, WPCPM_Roles::ROLE_MENTOR );

			if ( ! $adoptable ) {
				self::finish( 'conflict', $existing->user_login, $record );
			}

			$user_id = (int) $existing->ID;
		} else {
			$login   = self::unique_login( $email, $name );
			$created = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24, true, true ),
					'display_name' => '' !== $name ? $name : $login,
					'nickname'     => '' !== $name ? $name : $login,
					'role'         => WPCPM_Roles::ROLE_INSTITUTION,
				)
			);

			if ( is_wp_error( $created ) ) {
				self::finish( 'error', $created->get_error_message(), $record );
			}

			$user_id = (int) $created;
		}

		$attached = WPCPM_Institution_Members::attach( $user_id, $record, WPCPM_Institution_Members::HOW_MANAGER, get_current_user_id() );

		if ( is_wp_error( $attached ) ) {
			self::finish( 'error', $attached->get_error_message(), $record );
		}

		// Queued rather than sent, like every other invitation: the queue is what the mail log
		// and the stop control are built on. What comes back is how many were actually queued,
		// and it is 0 for an adopted mentor or a re-added former member, because `queue_invites()`
		// drops anybody already carrying an invited stamp and `attach()` never clears one. The
		// message says which of the two happened rather than promising mail that will not be
		// sent; see `render_message()` for why the stamp is not cleared to force one.
		$invited = (int) WPCPM_Mail::queue_invites( array( $user_id ) );

		self::finish( $invited ? 'added' : 'added-known', $email, $record );
	}

	/**
	 * The manager backstop: put a former member back.
	 *
	 * Capability, then nonce, then the policy, for the reason `handle_add_account()` gives.
	 *
	 * Which institution they were a member of is read from the members module's own list of
	 * former members, not from the form: a re-add can only return somebody to where they were.
	 * `attach()` clears the `_was` stamp when it is this institution, so the account stops
	 * counting as a former member in the same breath.
	 */
	public static function handle_readd() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$member_id = WPCPM_Request::posted_id( 'member' );

		check_admin_referer( self::ACTION_READD . '_' . $member_id );

		$record = WPCPM_Request::posted_text( 'record' );

		// Shape, policy, state, in the order `handle_add_account()` explains.
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

		if ( ! WPCPM_Institutions_Index::has( $record ) ) {
			self::finish( 'unknown-record', '', $record );
		}

		$member = get_user_by( 'id', $member_id );

		if ( ! $member instanceof WP_User || ! $member->exists() || ! self::was_member( $member, $record ) ) {
			self::finish( 'not-former', '', $record );
		}

		$attached = WPCPM_Institution_Members::attach( $member_id, $record, WPCPM_Institution_Members::HOW_MANAGER, get_current_user_id() );

		if ( is_wp_error( $attached ) ) {
			self::finish( 'error', $attached->get_error_message(), $record );
		}

		// 0 for anybody who has been invited before, which a former member almost always has:
		// their password still works and nothing is sent. The message says so.
		$invited = (int) WPCPM_Mail::queue_invites( array( $member_id ) );

		self::finish( $invited ? 'readded' : 'readded-known', $member->user_email, $record );
	}

	/*
	 * Shared parts
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether an account's membership of this institution has ended.
	 *
	 * Asked of the members module, which owns the `_was` stamp, and answered by user ID: no
	 * institution ID is compared here, because comparisons of those belong to the policy and
	 * to nowhere else.
	 *
	 * @param WP_User $user      The account.
	 * @param string  $record_id Airtable institution record ID.
	 * @return bool
	 */
	private static function was_member( WP_User $user, $record_id ) {
		foreach ( WPCPM_Institution_Members::former_members_of( $record_id ) as $former ) {
			if ( $former instanceof WP_User && (int) $former->ID === (int) $user->ID ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an account is a live member of this institution.
	 *
	 * Asked of the members module for the same reason `was_member()` is, and answered by user
	 * ID: the institution IDs are compared where every other one in this module is, which is
	 * inside the policy.
	 *
	 * @param WP_User $user      The account.
	 * @param string  $record_id Airtable institution record ID.
	 * @return bool
	 */
	private static function is_member( WP_User $user, $record_id ) {
		foreach ( WPCPM_Institution_Members::members_of( $record_id ) as $member ) {
			if ( $member instanceof WP_User && (int) $member->ID === (int) $user->ID ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether any live member holds an address.
	 *
	 * Compared lowercased and trimmed on both sides: addresses are not case-sensitive, and the
	 * Airtable value regularly arrives with a space on the end.
	 *
	 * @param WP_User[] $members Live members.
	 * @param string    $address Lowercased, trimmed address.
	 * @return bool
	 */
	private static function holds_address( array $members, $address ) {
		foreach ( $members as $member ) {
			if ( $member instanceof WP_User && strtolower( trim( (string) $member->user_email ) ) === $address ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A free username for a new institution account.
	 *
	 * The local part of the address, or the name when that leaves nothing usable. An
	 * institution contact has no WordPress.org handle to borrow, which is the one thing the
	 * students sync can fall back on, so the address is the only stable source here.
	 *
	 * @param string $email The address the account is created with.
	 * @param string $name  The contact's name, as typed.
	 * @return string
	 */
	public static function unique_login( $email, $name ) {
		$base = strtolower( (string) strstr( (string) $email, '@', true ) );
		$base = preg_replace( '/[^a-z0-9._\-]/', '', $base );

		if ( '' === $base ) {
			$base = sanitize_user( (string) $name, true );
		}

		$base = sanitize_user( $base, true );

		if ( '' === $base ) {
			$base = 'institution';
		}

		$login = $base;
		$n     = 1;

		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}

		return $login;
	}

	/**
	 * The institution's name as printed, or its record ID.
	 *
	 * The index keeps the name as Airtable stores it, trailing space and all; every renderer
	 * trims. A record the index has not read yet is named by its ID rather than by nothing,
	 * so a confirm never says "Remove Anna's access to 's students".
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
	 * The contact's name as Airtable holds it, or ''.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @return string
	 */
	private static function contact_person( $record_id ) {
		$row = WPCPM_Institutions_Index::row( $record_id );

		return is_array( $row ) && isset( $row['contact_person'] ) ? trim( (string) $row['contact_person'] ) : '';
	}

	/**
	 * The address Airtable holds for the institution, lowercased for comparison.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @return string
	 */
	private static function contact_email( $record_id ) {
		return strtolower( self::contact_email_raw( $record_id ) );
	}

	/**
	 * When the pipeline index was last read.
	 *
	 * @return int Unix time, or 0 when no sync has written the index yet.
	 */
	private static function index_read() {
		$index = WPCPM_Institutions_Index::read();

		return isset( $index['read'] ) ? (int) $index['read'] : 0;
	}

	/**
	 * The address Airtable holds for the institution, as it is printed.
	 *
	 * @param string $record_id Airtable institution record ID.
	 * @return string
	 */
	private static function contact_email_raw( $record_id ) {
		$row = WPCPM_Institutions_Index::row( $record_id );

		return is_array( $row ) && isset( $row['contact_email'] ) ? trim( (string) $row['contact_email'] ) : '';
	}

	/**
	 * Where this card's two halves came from.
	 *
	 * Two sources in one card: the institution's name and the contact address are as the last
	 * sync read them, the memberships are as they are right now. Saying only one of the two
	 * would let the stale half look as fresh as the live one.
	 *
	 * @param int $read Unix time the pipeline index was read, or 0 for never.
	 */
	private static function render_read_line( $read ) {
		$read = (int) $read;

		if ( ! $read ) {
			printf(
				'<p class="wpcpm-institution__read wpcpm-people__read">%s</p>',
				esc_html__( 'The institution\'s details have not been read from Airtable yet. The people above are as they are right now.', 'wpcredits-program-manager' )
			);

			return;
		}

		printf(
			'<p class="wpcpm-institution__read wpcpm-people__read">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: date and time, 2: human-readable time difference. */
					__( 'The institution\'s details were read %1$s (%2$s ago). The people above are as they are right now.', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d H:i', $read ),
					human_time_diff( $read, time() )
				)
			)
		);
	}

	/**
	 * The one-shot outcome of the last thing somebody pressed, on the card it happened to.
	 *
	 * A flash rather than a query argument, so a reload does not say "That person no longer
	 * has access" a second time about a removal that happened once.
	 *
	 * The flash names the institution it is about, and a block for another institution draws
	 * nothing. `WPCPM_Flash::take()` memoizes per request, so a screen drawing the backstop
	 * under every pipeline row used to print one manager's outcome under all 106 of them,
	 * every one of which read as a fact about that school. The record is what makes it one
	 * message, on one card, and every path in this file names one. A flash carrying no record
	 * is one this plugin wrote before it did: it is taken, so it cannot sit in user meta, and
	 * it is not printed, because there is no card it is known to be about.
	 *
	 * Two of the outcomes say no invitation was sent. `WPCPM_Mail::queue_invites()` drops
	 * anybody already carrying an invited stamp and `attach()` never clears one, so an
	 * adopted mentor and a re-added former member are not mailed. Clearing the stamp to force
	 * one was the other way out and is not taken: the stamp is the record that an invitation
	 * was sent, `never_invited()` and the bulk invite are built on it, and the mail itself is
	 * `wp_new_user_notification()`, a set-your-password message that an account with a working
	 * password does not need. So the message tells the truth instead.
	 *
	 * @param string $record_id The institution whose card is being drawn.
	 */
	private static function render_message( $record_id ) {
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

		$messages = array(
			'removed'        => array( 'success', __( 'That person no longer has access to this institution. Their account is kept.', 'wpcredits-program-manager' ) ),
			'left'           => array( 'success', __( 'You have left this institution. You no longer see its students, and your account is kept.', 'wpcredits-program-manager' ) ),
			'ended'          => array( 'info', __( 'Nothing to remove: that person\'s access had already ended.', 'wpcredits-program-manager' ) ),
			'added'          => array( 'success', __( 'The account was added and an invitation is queued.', 'wpcredits-program-manager' ) ),
			'added-known'    => array( 'success', __( 'The account was added. No new invitation was queued: it has either been invited before or is already waiting in the queue.', 'wpcredits-program-manager' ) ),
			'readded'        => array( 'success', __( 'That former member has access again, and an invitation is queued.', 'wpcredits-program-manager' ) ),
			'readded-known'  => array( 'success', __( 'That former member has access again. No new invitation was queued: their account has either been invited before or is already waiting in the queue.', 'wpcredits-program-manager' ) ),
			'already'        => array( 'error', __( 'That account is already a member of this institution. It is on the list above.', 'wpcredits-program-manager' ) ),
			'conflict'       => array( 'error', __( 'That address already has an account here that is neither a former member of this institution nor a mentor, so it was not adopted. A hand-made account is somebody\'s to explain.', 'wpcredits-program-manager' ) ),
			'bad-email'      => array( 'error', __( 'That is not an address WordPress can create an account with.', 'wpcredits-program-manager' ) ),
			'unknown-record' => array( 'error', __( 'That institution is not in the pipeline index. Run the institutions sync, then try again.', 'wpcredits-program-manager' ) ),
			'not-former'     => array( 'error', __( 'That account is not a former member of this institution, so there is nothing to put back.', 'wpcredits-program-manager' ) ),
			'error'          => array( 'error', __( 'That could not be done.', 'wpcredits-program-manager' ) ),
		);

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
	 * Record the outcome and return to the page the control was on.
	 *
	 * The destination is rebuilt here from a flag rather than taken from the request, so no
	 * form can bounce a manager somewhere else. `admin` is the Institutions screen; anything
	 * else is the institution dashboard, reached as an array callable so this file loads and
	 * its tests run whether or not the dashboard shell has landed yet.
	 *
	 * **This does not return.** Every call to it ends the request, which is why a refusal in a
	 * handler above reads as one line and not as an early return with a branch around it.
	 *
	 * The record travels with the outcome so the card that prints it is the one whose list
	 * changed, and every caller has one: a record that is not even well-formed is refused
	 * before it reaches here. An empty status queues nothing at all: a message nobody will be
	 * shown is a message that stays in user meta until it surprises somebody, and the one
	 * path that ends without a reader (leaving) says so by passing ''.
	 *
	 * @param string $status Outcome slug, one of the keys `render_message()` knows, or ''
	 *                       to say nothing at all.
	 * @param string $detail What the message adds: an address, a username, an error.
	 * @param string $record The institution the outcome is about.
	 */
	private static function finish( $status, $detail, $record ) {
		if ( '' !== (string) $status ) {
			WPCPM_Flash::set(
				self::FLASH,
				array(
					'status' => $status,
					'detail' => $detail,
					'record' => trim( (string) $record ),
				)
			);
		}

		$where = WPCPM_Request::posted_key( 'wpcpm_from' );

		if ( self::RETURN_ADMIN === $where ) {
			wp_safe_redirect( self::manager_url() . '#' . self::ANCHOR );
			exit;
		}

		wp_safe_redirect( self::dashboard_url() . '#' . self::ANCHOR );
		exit;
	}

	/**
	 * The Institutions screen.
	 *
	 * The slug is written out rather than asked of the module: `admin_url()` is an instance
	 * method on `WPCPM_Module` and the registry owns the instance, so building one here to
	 * read a constant string would be the longer way round.
	 *
	 * @return string
	 */
	private static function manager_url() {
		return admin_url( 'admin.php?page=wpcpm-institutions' );
	}

	/**
	 * The institution dashboard, or the front page while it does not exist.
	 *
	 * @return string
	 */
	private static function dashboard_url() {
		if ( class_exists( 'WPCPM_Institutions_Dashboard' ) && method_exists( 'WPCPM_Institutions_Dashboard', 'page_url' ) ) {
			$url = (string) call_user_func( array( 'WPCPM_Institutions_Dashboard', 'page_url' ) );

			if ( '' !== $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}
}
