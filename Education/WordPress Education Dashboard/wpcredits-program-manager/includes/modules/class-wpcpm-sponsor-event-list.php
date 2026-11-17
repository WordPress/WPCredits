<?php
/**
 * The events from the program's sponsors, and the program's own, as the people they are for see
 * them: the list on the Student Report Card, the Mentor Report Card and the Institution Dashboard,
 * the manager's Join and Leave on the Administrator Dashboard, and the two presses behind them.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who sees a sponsor's approved event, and how they join and leave it (the design's section 18).
 *
 * **The list.** "Events from our sponsors", or "Events from our sponsors and the program" while one
 * of the program's own is among them: every approved event not over yet that the person looking may
 * join, and every one they are on (`WPCPM_Sponsor_Event::open_for()`), soonest first, each with its
 * sponsor's name, or the WordPress Credits Program for an event of the program's own, its title,
 * when it runs on the person's own clock (the zone named once, above the list), how long, the
 * places left, its description (folded when it is long), and Join or Leave. An event they are on
 * whose sponsor no longer offers events stays, marked no longer offered in place of the places,
 * with Leave alone, so nobody is left on an event they cannot get off. The call link is drawn for a
 * person on the event alone: it is for the people who joined and the program, never for a list.
 * Nobody's name is drawn: who joined is the program managers' to read, on their own card. Nothing
 * at all while the launch switch is off, and nothing when there is no event and nothing to say.
 *
 * **Where it is drawn.** For the signed-in person on their own page alone, since a press acts as
 * whoever presses it: on the Student Report Card inside the call section, under the group sessions;
 * on the Mentor Report Card under the sessions panel; on the Institution Dashboard after the modules,
 * for a contact of the institution drawn. The Administrator Dashboard draws no second list: the
 * program's own card lists every approved event, and draws the manager's Join or Leave on each
 * through `render_actions()`. A program manager joins as themselves, never on anybody's behalf.
 * Its heading is the house's for the place it is drawn (`render_heading()`), so the theme's rules
 * for that place dress it.
 *
 * **The presses.** Join and Leave post to admin-post.php, and each asks in this order: whether the
 * person is signed in (a lapsed login is told to log in with a 403, before the nonce, as the group
 * sessions tell it; the nopriv twins of the two actions are how such a request reaches it at all);
 * the nonce keyed to the event, whose ID is the one value read before it; the launch switch, off
 * answered with a 403, since no card is drawn to say so while it is off; an event; and then the
 * store's `join()` or `leave()`, which decide the rest under the sponsor's lock: the audience, the
 * sponsor's own members, the places, the time, and for a Leave an event canceled since the page was
 * drawn. A join mails the person the event with its call link and the calendar file that adds it to
 * their calendar; a leave, the file that takes it out. Every other outcome goes back to the page the
 * form named, one of four keys this class maps to the four dashboards (a posted address is never
 * followed), its sentence on a flash channel of the list's own, which the list draws at its head and
 * the program's card under Coming up.
 */
final class WPCPM_Sponsor_Event_List {

	/** Join an event. Nonce keyed to the event. */
	const ACTION_JOIN = 'wpcpm_sevent_join';

	/** Leave an event. Nonce keyed to the event. */
	const ACTION_LEAVE = 'wpcpm_sevent_leave';

	/** The event a form names, as its post ID: the field every sponsor event form posts it in. */
	const FIELD_EVENT = 'wpcpm_sevent_id';

	/** The page a press goes back to, as one of the four keys below. */
	const FIELD_BACK = 'wpcpm_sevent_back';

	// The four pages a press goes back to, each mapped to a dashboard this plugin builds.
	const BACK_STUDENT     = 'student';
	const BACK_MENTOR      = 'mentor';
	const BACK_ADMIN       = 'admin';
	const BACK_INSTITUTION = 'institution';

	/** The list's anchor on the three pages that draw it. */
	const ANCHOR = 'wpcpm-sevents';

	/** The program's card's anchor on the Administrator Dashboard, one of `WPCPM_Return::ANCHORS`. */
	const ADMIN_ANCHOR = 'wpcpm-sponsor-events';

	/** The list's own flash channel: its presses set it, the list and the program's card draw it. */
	const FLASH = 'sevent-list';

	/** How long a description is shown whole, in characters; a longer one is folded. */
	const DESCRIPTION_SHOWN = 300;

	/**
	 * The two presses, each on both of core's arms. admin-post.php fires the nopriv action for a
	 * request whose login has lapsed, and gives a blank page for an action nothing hooked there, so
	 * the handlers' first question, which tells the person to log in, is reached through that arm.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_JOIN, array( __CLASS__, 'handle_join' ) );
		add_action( 'admin_post_' . self::ACTION_LEAVE, array( __CLASS__, 'handle_leave' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_JOIN, array( __CLASS__, 'handle_join' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_LEAVE, array( __CLASS__, 'handle_leave' ) );
	}

	/**
	 * The presses' outcomes, as the list and the program's card print them: status => notice kind
	 * and sentence. A join and a leave say what happened and claim no mail, which may reach nobody.
	 * The launch switch off has no sentence: the presses answer it with a 403, since no list or card
	 * is drawn to say it while the switch is off.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'joined'     => array( 'success', __( 'You joined the event.', 'wpcredits-program-manager' ) ),
			'left'       => array( 'success', __( 'You left the event, and your place is free for somebody else.', 'wpcredits-program-manager' ) ),
			'full'       => array( 'error', __( 'That event filled up before you could join it.', 'wpcredits-program-manager' ) ),
			'past'       => array( 'error', __( 'That event has taken place.', 'wpcredits-program-manager' ) ),
			'not-open'   => array( 'error', __( 'That event is no longer open to join.', 'wpcredits-program-manager' ) ),
			'audience'   => array( 'error', __( 'These events are for the program\'s students, mentors, Administrators and institutions.', 'wpcredits-program-manager' ) ),
			'own'        => array( 'error', __( 'Your company\'s event is on your Sponsor Dashboard.', 'wpcredits-program-manager' ) ),
			'busy'       => array( 'error', __( 'Another change to that event was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'already'    => array( 'error', __( 'You are on that event already.', 'wpcredits-program-manager' ) ),
			'not-joined' => array( 'error', __( 'You are not on that event.', 'wpcredits-program-manager' ) ),
			'canceled'   => array( 'error', __( 'That event is canceled.', 'wpcredits-program-manager' ) ),
			'gone'       => array( 'error', __( 'That event is not on this site anymore.', 'wpcredits-program-manager' ) ),
			'failed'     => array( 'error', __( 'That could not be done right now. Try again later.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The list
	 * --------------------------------------------------------------------
	 */

	/**
	 * The list for the person named, on their own page: the whole section, or nothing.
	 *
	 * Drawn for the signed-in person alone: a press acts as whoever presses it, so a list drawn for
	 * somebody else would offer a Join that puts the reader on the event. The outcome waiting on the
	 * list's channel is taken first, whatever is drawn, so that it never waits past the page it was
	 * meant for to appear beside a later list: one still waiting when the launch switch goes off goes
	 * unsaid with everything else of the tiers, and with no event left to list the section holds the
	 * sentence alone, so that a refusal whose event has gone is still explained.
	 *
	 * @param WP_User|null $viewer The person looking, whose page this is; nothing is drawn for
	 *                             anything that is not a signed-in account.
	 * @param string       $back   Which page this is: one of the BACK_* keys, posted with every press
	 *                             and deciding the heading's markup.
	 */
	public static function render( $viewer, $back ) {
		$drawable = self::drawable( $viewer );

		if ( null === $drawable ) {
			return;
		}

		list( $message, $events ) = $drawable;

		$zone    = WPCPM_Mentor_Availability::viewer_timezone( (int) $viewer->ID );
		$program = false;

		foreach ( $events as $event ) {
			$program = $program || WPCPM_Sponsor_Event::is_program( $event );
		}

		if ( self::parts() ) {
			self::render_parts( $message, $events, $viewer, $zone, $back );

			return;
		}

		printf( '<section class="wpcpm-sevents" id="%s">', esc_attr( self::ANCHOR ) );
		self::render_heading( $back, $program );

		if ( array() !== $events ) {
			printf( '<p class="wpcpm-sevents__zone">%s</p>', esc_html( self::zone_sentence( $zone ) ) );
		}

		self::draw_message( $message );

		if ( array() !== $events ) {
			echo '<ul class="wpcpm-sevent__list">';

			foreach ( $events as $event ) {
				self::render_item( $event, $viewer, $zone, $back );
			}

			echo '</ul>';
		}

		echo '</section>';
	}

	/**
	 * Whether `render()` would print its section for the person named: they are the person signed
	 * in, the list is live, and there is an event open to them or an outcome waiting on the list's
	 * channel. The Institution Dashboard asks it to know whether its Events view is in the menu.
	 *
	 * The three questions are `render()`'s own, asked through the one helper both call, so the two
	 * cannot disagree. It peeks at the outcome waiting on the list's channel and takes nothing, so a
	 * page that asks and then draws no list leaves the outcome for the page that does.
	 *
	 * @param WP_User $viewer The person looking, whose page this is.
	 * @return bool
	 */
	public static function shows( WP_User $viewer ): bool {
		return null !== self::drawable( $viewer, false );
	}

	/**
	 * The three questions `render()` asks before it prints: what it draws, or null for nothing.
	 *
	 * For `render()` the outcome waiting on the list's channel is taken once the viewer is the person
	 * signed in, whatever is drawn after, as `render()` has always taken it; for `shows()` it is only
	 * peeked at, and stays.
	 *
	 * @param WP_User|null $viewer The person looking.
	 * @param bool         $take   Take the outcome (`render()`), or only peek at it (`shows()`).
	 * @return array{0: array|null, 1: array}|null The outcome to say and the events to list, or null.
	 */
	private static function drawable( $viewer, $take = true ) {
		if ( ! $viewer instanceof WP_User || ! $viewer->exists() || get_current_user_id() !== (int) $viewer->ID ) {
			return null;
		}

		$message = self::pending( $take );

		if ( ! self::live() ) {
			return null;
		}

		$events = WPCPM_Sponsor_Event::open_for( $viewer );

		if ( array() === $events && null === $message ) {
			return null;
		}

		return array( $message, $events );
	}

	/**
	 * The list's heading, in the house's markup for the place it is drawn, so the theme's existing
	 * rules dress it there and no rule of its own has to be kept in step with them: on the Student
	 * Report Card, the student's group sessions' own subheading, since the list sits under the
	 * sessions; on the Mentor Report Card, the calls card's heading, which the sessions panel above
	 * it wears, so the events read as a part of the card beside the sessions and not as a part of
	 * them; on the Institution Dashboard, a module title's level and class, so the outline keeps it
	 * beside the modules. A key naming no such page claims none of them. The heading names the program
	 * beside the sponsors only while an event of its own is listed, so a list of sponsors' events
	 * alone reads as it always has.
	 *
	 * @param string $back    Which page this is: one of the BACK_* keys.
	 * @param bool   $program Whether an event of the program's own is listed.
	 */
	private static function render_heading( $back, $program = false ) {
		$text = self::heading_text( $program );

		if ( self::BACK_STUDENT === $back ) {
			printf( '<h4 class="wpcpm-sevents__heading wpcpm-calls__subheading">%s</h4>', esc_html( $text ) );
		} elseif ( self::BACK_MENTOR === $back ) {
			printf( '<h3 class="wpcpm-sevents__heading wpcpm-calls__heading">%s</h3>', esc_html( $text ) );
		} elseif ( self::BACK_INSTITUTION === $back ) {
			printf( '<h2 class="wpcpm-sevents__heading wpcpm-student__heading">%s</h2>', esc_html( $text ) );
		} else {
			printf( '<h3 class="wpcpm-sevents__heading">%s</h3>', esc_html( $text ) );
		}
	}

	/**
	 * The outcome of the signed-in person's last Join or Leave, where the program's card draws it:
	 * under Coming up, the part the manager pressed in. Taken whether or not it is drawn, and drawn
	 * only while the launch switch is on.
	 */
	public static function render_message() {
		$message = self::pending();

		if ( self::live() ) {
			self::draw_message( $message );
		}
	}

	/**
	 * Join or Leave for one approved event, as the person looking may press it: Leave with the mark
	 * that they are on it, and on the three pages that draw the list the call link, theirs once they
	 * are on it; else Join, while there is a place and the sponsor still offers events. Nothing for an
	 * event that is not approved, for nobody signed in, or with nothing to press. The Administrator
	 * Dashboard's card draws this on each event under Coming up, and the call link itself; while that
	 * dashboard's switch is on, nothing is printed here: the card puts Join or Leave in the event's own
	 * Decide row (`decision_parts()`, Task 18d). On the Institution Dashboard, while its surface is on,
	 * the row's presses and call link are the shared parts' (Task 22); the Student and Mentor Report
	 * Cards' lists draw today's markup.
	 *
	 * @param WP_Post      $event  The event, one a list or the program's card read as not over yet.
	 * @param WP_User|null $viewer The person looking, who presses as themselves; nothing is drawn
	 *                             for anything that is not an account.
	 * @param string       $back   Which page this is: one of the BACK_* keys.
	 */
	public static function render_actions( WP_Post $event, $viewer, $back ) {
		$press = self::press_of( $event, $viewer );

		if ( null === $press ) {
			return;
		}

		// On the Administrator Dashboard's card while its switch is on, Join or Leave is a decision of the event's own
		// Decide row, which the card draws (`decision_parts()`). The Institution Dashboard on its parts draws the row's
		// presses below; the Report Cards' lists draw today's markup, the call link with it.
		if ( self::BACK_ADMIN === $back && class_exists( 'WPCPM_UI', false ) && WPCPM_UI::on() ) {
			return;
		}

		// On the Institution Dashboard's parts (Task 22): the row's presses and its call link, the parts' buttons; the
		// mark that the reader is on the event is the row's note, which the row draws.
		if ( self::parts() ) {
			echo implode( '', self::actions_parts( $event, $press, $back ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::button() and WPCPM_UI::nonce().

			return;
		}

		$joined = $press;

		echo '<div class="wpcpm-sevent__actions">';

		if ( $joined ) {
			printf( '<p class="wpcpm-sevent__joined">%s</p>', esc_html( self::joined_words() ) );
			self::render_press( self::ACTION_LEAVE, $event, $back );

			$link = self::call_link( $event, $back );

			// Named to a screen reader with its event, as Join and Leave are: a list of several
			// events otherwise reads "Join the call" once for each, with nothing to tell them apart.
			if ( '' !== $link ) {
				printf(
					'<a class="wpcpm-sevent__link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a>',
					esc_url( $link ),
					esc_html( self::call_words() ),
					esc_html( $event->post_title )
				);
			}
		} else {
			self::render_press( self::ACTION_JOIN, $event, $back );
		}

		echo '</div>';
	}

	/**
	 * Whether the person looking has something to press on an event, and which: true for Leave (they are on it), false
	 * for Join, null for nothing. Nothing while the list is not in use, for an event that is not approved, for nobody
	 * signed in, and for Join on an event that is full or whose sponsor no longer offers events: the store's own tests,
	 * so no Join is drawn that it would refuse; somebody on the event may still leave it whatever became of its places
	 * or its sponsor.
	 *
	 * @param WP_Post      $event  The event.
	 * @param WP_User|null $viewer The person looking.
	 * @return bool|null
	 */
	private static function press_of( WP_Post $event, $viewer ) {
		if ( ! self::live() || ! WPCPM_Sponsor_Event::is_event( $event ) || ! $viewer instanceof WP_User || ! $viewer->exists() ) {
			return null;
		}

		$facts = WPCPM_Sponsor_Event::facts( $event );

		if ( WPCPM_Sponsor_Event::STATE_APPROVED !== $facts['state'] ) {
			return null;
		}

		$joined = WPCPM_Sponsor_Event::joined( $event, $viewer );
		$full   = $facts['places'] > 0 && $facts['joined'] >= $facts['places'];

		if ( ! $joined && ( $full || ! WPCPM_Sponsor_Event::offers_events( $event ) ) ) {
			return null;
		}

		return $joined;
	}

	/**
	 * Join or Leave on the shared parts (design 2.1, 2.5, 2.6), for the Administrator Dashboard's Events card, which puts
	 * it in the event's own Decide row (Task 18d): a decision of `WPCPM_UI::decide()` that acts at once, Join the
	 * secondary (the card's one primary is the row's Approve; task V1, ruling 9), Leave red (it leaves), asking today's
	 * question, each in today's words. Its form posts what today's posts, the way back to that card among it, the nonce
	 * printed with no id (`WPCPM_UI::nonce()`), since a card holds one per event; the row names the event to a screen
	 * reader. No call link: the card prints the event's own. `joined` says whether the person is on the event, for the
	 * card's facts to say so in today's words ("You are on it."); `decision` is null when there is nothing to press, as
	 * `render_actions()` draws nothing then.
	 *
	 * @param WP_Post      $event  The event.
	 * @param WP_User|null $viewer The person looking.
	 * @return array{joined: bool, decision: array|null}
	 */
	public static function decision_parts( WP_Post $event, $viewer ) {
		$press = self::press_of( $event, $viewer );

		if ( null === $press ) {
			return array(
				'joined'   => false,
				'decision' => null,
			);
		}

		$action = $press ? self::ACTION_LEAVE : self::ACTION_JOIN;
		$id     = (int) $event->ID;

		return array(
			'joined'   => $press,
			'decision' => array(
				'key'        => $press ? 'leave' : 'join',
				'label'      => $press ? __( 'Leave', 'wpcredits-program-manager' ) : __( 'Join', 'wpcredits-program-manager' ),
				'kind'       => $press ? 'danger' : 'secondary',
				'form_attrs' => array(
					'method'          => 'post',
					'action'          => admin_url( 'admin-post.php' ),
					'data-wpcpm-once' => true,
					'data-wpcpm-busy' => $press ? __( 'Leaving', 'wpcredits-program-manager' ) : __( 'Joining', 'wpcredits-program-manager' ),
				),
				'hidden'     => static function () use ( $action, $id ) {
					echo WPCPM_UI::nonce( $action . '_' . $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::nonce().
					printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
					printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_EVENT ), (int) $id );
					printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_BACK ), esc_attr( self::BACK_ADMIN ) );
				},
				'fields'     => '',
				'confirm'    => $press ? __( 'Leave this event? Your place goes back.', 'wpcredits-program-manager' ) : '',
			),
		);
	}

	/**
	 * One event in the list: its title and its sponsor, or the WordPress Credits Program as the
	 * host of an event of its own, when it runs on the person's clock, how long and the places
	 * left, its description, and what they may press. For an event whose sponsor no longer offers
	 * events, which is on the list only because the person is on it, the places give way to the
	 * mark that it is no longer offered: nobody can take a place on it.
	 *
	 * @param WP_Post      $event  The event.
	 * @param WP_User      $viewer The person looking.
	 * @param DateTimeZone $zone   Their clock.
	 * @param string       $back   Which page this is.
	 */
	private static function render_item( WP_Post $event, WP_User $viewer, DateTimeZone $zone, $back ) {
		$facts = self::event_facts( $event, $zone );

		echo '<li class="wpcpm-sevent__item">';
		printf(
			'<p class="wpcpm-sevent__title">%1$s <span class="wpcpm-sevent__sponsor">%2$s</span></p>',
			esc_html( $facts['title'] ),
			esc_html( $facts['host'] )
		);
		printf(
			'<p class="wpcpm-sevent__meta">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: the day and the start and end times, 2: how long it runs, e.g. 60 minutes, 3: the places left, e.g. 12 places left, Full, or No longer offered. */
					__( '%1$s. %2$s. %3$s.', 'wpcredits-program-manager' ),
					$facts['range'],
					$facts['length'],
					$facts['places']
				)
			)
		);
		self::render_description( $facts['description'] );
		self::render_actions( $event, $viewer, $back );
		echo '</li>';
	}

	/**
	 * An event's description as it was written, escaped: whole when it is short, folded under a
	 * disclosure when it is long, so one long description does not push the rest of the list away.
	 *
	 * @param string $text The description.
	 */
	private static function render_description( $text ) {
		$text  = (string) $text;
		$shown = self::description_shown( $text );

		if ( '' === $shown ) {
			return;
		}

		if ( 'whole' === $shown ) {
			printf( '<p class="wpcpm-sevent__description">%s</p>', esc_html( $text ) );

			return;
		}

		printf(
			'<details class="wpcpm-sevent__more"><summary>%1$s</summary><p class="wpcpm-sevent__description">%2$s</p></details>',
			esc_html( self::more_words() ),
			esc_html( $text )
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The list on the Institution Dashboard's parts (Task 22), and the rules both drawings ask
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether the list is drawn on the shared parts: the Institution Dashboard's surface named and on. Every other page
	 * that draws the list (the Student and Mentor Report Cards' calendars) and the program's card on the Administrator
	 * Dashboard keep today's markup, whatever else is on.
	 *
	 * @return bool
	 */
	private static function parts() {
		return class_exists( 'WPCPM_UI', false ) && class_exists( 'WPCPM_Dashboard_Card', false ) && WPCPM_UI::on() && 'institution' === WPCPM_UI::surface();
	}

	/**
	 * The list on the parts (design 6.6): the view's content, one headless block holding the list's anchor (the view's
	 * head titles it "Events"); no heading, since today's "Events from our sponsors" says what the view's lede says one
	 * line above and each row names its host (Task 22, fix round 1); the zone named once, a fact only it holds; the outcome, the shell's message in its tone; then one list of rows, the facts
	 * under the name: the host, the day and time on the reader's clock with each date whole, how long and the places left
	 * in today's words; "You are on it." the row's note; the description under it, folded when long; Join (quiet),
	 * Leave (quiet red) and Join the call (a quiet link) in the row's action cell.
	 *
	 * @param array|null   $message The outcome to say, or null.
	 * @param WP_Post[]    $events  The events to list.
	 * @param WP_User      $viewer  The person looking.
	 * @param DateTimeZone $zone    Their clock.
	 * @param string       $back    Which page this is.
	 */
	private static function render_parts( $message, array $events, WP_User $viewer, DateTimeZone $zone, $back ) {
		printf( '<section class="wpcpm-ui-block" id="%s">', esc_attr( self::ANCHOR ) );

		if ( array() !== $events ) {
			echo WPCPM_UI::intro( self::zone_sentence( $zone ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
		}

		self::draw_message( $message );

		if ( array() !== $events ) {
			echo WPCPM_UI::list_open( array( 'facts' => 'under' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup of its own.

			foreach ( $events as $event ) {
				echo self::row_parts( $event, $viewer, $zone, $back ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.
			}

			echo WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup of its own.
		}

		echo '</section>';
	}

	/**
	 * One event as a row of the parts' list: its title and its host as typed (the list breaks a word that would
	 * overflow; `breakable()` is for a data table's cell), when it runs with each date whole, how long and the places
	 * left (or that it is no longer offered); "You are on it." the note of a row the reader is on; its description under
	 * the row, whole or folded; and what the reader may press.
	 *
	 * @param WP_Post      $event  The event.
	 * @param WP_User      $viewer The person looking.
	 * @param DateTimeZone $zone   Their clock.
	 * @param string       $back   Which page this is.
	 * @return string Escaped markup.
	 */
	private static function row_parts( WP_Post $event, WP_User $viewer, DateTimeZone $zone, $back ) {
		$facts = self::event_facts( $event, $zone );
		$press = self::press_of( $event, $viewer );

		return WPCPM_UI::row(
			array(
				'name'    => $facts['title'],
				'facts'   => array(
					$facts['host'],
					array( 'html' => self::range_html( $facts['range'], $facts['start'], $facts['end'], $zone ) ),
					$facts['length'],
					$facts['places'],
				),
				'note'    => true === $press ? self::joined_words() : '',
				'more'    => self::description_parts( $facts['description'] ),
				'actions' => null === $press ? array() : self::actions_parts( $event, $press, $back ),
			)
		);
	}

	/**
	 * When an event runs, as `WPCPM_Mentor_Calls::format_range()` words it on the reader's clock, with each of its
	 * moments whole (`WPCPM_UI::whole()`), so a day, its month and its year never part and the range may break at its
	 * own words between them. The words are the helper's, and the two moments are its own (`range_moments()`, the one
	 * place their formats are read): marked where the words start and end with them; otherwise printed as they are.
	 *
	 * @param string       $range The helper's words.
	 * @param int          $start The start, a Unix time.
	 * @param int          $end   The end, a Unix time.
	 * @param DateTimeZone $zone  The reader's clock.
	 * @return string Escaped markup.
	 */
	private static function range_html( $range, $start, $end, DateTimeZone $zone ) {
		$moments = WPCPM_Mentor_Calls::range_moments( $start, $end, $zone );
		$from    = $moments['from'];
		$to      = $moments['to'];
		$between = strlen( $range ) - strlen( $from ) - strlen( $to );

		if ( '' !== $from && '' !== $to && $between > 0 && 0 === strpos( $range, $from ) && substr( $range, -strlen( $to ) ) === $to ) {
			return WPCPM_UI::whole( $from ) . esc_html( substr( $range, strlen( $from ), $between ) ) . WPCPM_UI::whole( $to );
		}

		return esc_html( $range );
	}

	/**
	 * An event's description on the parts: whole as a person's note when it is short, folded under today's words as an
	 * inner fold when it is long, as the program's card draws it; '' for none.
	 *
	 * @param string $text The description.
	 * @return string Escaped markup.
	 */
	private static function description_parts( $text ) {
		$text  = (string) $text;
		$shown = self::description_shown( $text );

		if ( '' === $shown ) {
			return '';
		}

		if ( 'whole' === $shown ) {
			return WPCPM_UI::note( $text );
		}

		return WPCPM_UI::inner_open( array( 'title' => self::more_words() ) ) . WPCPM_UI::note( $text ) . WPCPM_UI::inner_close();
	}

	/**
	 * What the reader may press on an event on the parts, in the order today's markup has it: Leave (quiet red, today's
	 * question) and, on the three pages that list the events, Join the call (a quiet link that opens apart, as today's);
	 * or Join (quiet). Each press is its own form posting what today's posts (`press_fields()`), its nonce printed with
	 * no id; each is named for its event to a screen reader.
	 *
	 * @param WP_Post $event  The event.
	 * @param bool    $joined Whether the reader is on it (`press_of()`).
	 * @param string  $back   Which page this is.
	 * @return string[] Escaped markup, a press or a link each.
	 */
	private static function actions_parts( WP_Post $event, $joined, $back ) {
		$out  = array( self::press_parts( $joined ? self::ACTION_LEAVE : self::ACTION_JOIN, $event, $back ) );
		$link = $joined ? self::call_link( $event, $back ) : '';

		if ( '' !== $link ) {
			$out[] = WPCPM_UI::button(
				array(
					'label'   => self::call_words(),
					'kind'    => 'quiet',
					'href'    => $link,
					'rel'     => 'noopener noreferrer',
					'target'  => '_blank',
					'context' => (string) $event->post_title,
					'side'    => 'front',
				)
			);
		}

		return $out;
	}

	/**
	 * One press on the parts, Join or Leave: today's form (the method, the address, the guard and its busy words), its
	 * nonce with no id and the referer, today's hidden fields, and the button part, quiet or quiet red.
	 *
	 * @param string  $action ACTION_JOIN or ACTION_LEAVE.
	 * @param WP_Post $event  The event.
	 * @param string  $back   Which page this is.
	 * @return string Escaped markup.
	 */
	private static function press_parts( $action, WP_Post $event, $back ) {
		$join   = self::ACTION_JOIN === $action;
		$words  = self::press_words( $join );
		$hidden = '';

		foreach ( self::press_fields( $action, $event, $back ) as $name => $value ) {
			$hidden .= sprintf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $value ) );
		}

		return sprintf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $words['busy'] )
		)
			. WPCPM_UI::nonce( $action . '_' . (int) $event->ID )
			. $hidden
			. WPCPM_UI::button(
				array(
					'label'   => $words['label'],
					'kind'    => $join ? 'quiet' : 'quiet-danger',
					'confirm' => $words['confirm'],
					'context' => (string) $event->post_title,
					'side'    => 'front',
				)
			)
			. '</form>';
	}

	/**
	 * What a Join or a Leave posts besides its nonce, in today's order: the action, the event, the page to come back to
	 * (one of the four keys, '' for any other). Both drawings print these.
	 *
	 * @param string  $action ACTION_JOIN or ACTION_LEAVE.
	 * @param WP_Post $event  The event.
	 * @param string  $back   Which page this is.
	 * @return array<string, string> Name => value.
	 */
	private static function press_fields( $action, WP_Post $event, $back ) {
		return array(
			'action'          => (string) $action,
			self::FIELD_EVENT => (string) (int) $event->ID,
			self::FIELD_BACK  => in_array( $back, self::backs(), true ) ? (string) $back : '',
		);
	}

	/**
	 * A press's words, today's: its label, the busy words its guard shows while it posts, and the browser's question
	 * (Leave asks; Join does not).
	 *
	 * @param bool $join Whether the press is Join.
	 * @return array{label: string, busy: string, confirm: string}
	 */
	private static function press_words( $join ) {
		return array(
			'label'   => $join ? __( 'Join', 'wpcredits-program-manager' ) : __( 'Leave', 'wpcredits-program-manager' ),
			'busy'    => $join ? __( 'Joining', 'wpcredits-program-manager' ) : __( 'Leaving', 'wpcredits-program-manager' ),
			'confirm' => $join ? '' : __( 'Leave this event? Your place goes back.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The call link a person on an event is shown, on the three pages that list the events: the event's own, or '' (on
	 * the Administrator Dashboard, whose card prints the link itself, and for an event with none).
	 *
	 * @param WP_Post $event The event.
	 * @param string  $back  Which page this is.
	 * @return string The address, not escaped.
	 */
	private static function call_link( WP_Post $event, $back ) {
		return self::BACK_ADMIN === $back ? '' : (string) WPCPM_Sponsor_Event::link_of( $event );
	}

	/**
	 * An event's facts as the list says them: its title and its host, when it runs on the reader's clock (the words and
	 * the two moments), how long, the places left or that it is no longer offered, and its description.
	 *
	 * @param WP_Post      $event The event.
	 * @param DateTimeZone $zone  The reader's clock.
	 * @return array{title: string, host: string, range: string, start: int, end: int, length: string, places: string, description: string}
	 */
	private static function event_facts( WP_Post $event, DateTimeZone $zone ) {
		$facts = WPCPM_Sponsor_Event::facts( $event );

		return array(
			'title'       => (string) $facts['title'],
			'host'        => (string) $facts['host'],
			'range'       => WPCPM_Mentor_Calls::format_range( $facts['start'], $facts['end'], $zone ),
			'start'       => (int) $facts['start'],
			'end'         => (int) $facts['end'],
			'length'      => WPCPM_Sponsor_Event::length_line( $facts['minutes'] ),
			'places'      => WPCPM_Sponsor_Event::offers_events( $event ) ? self::places( $facts['places'], $facts['joined'] ) : __( 'No longer offered', 'wpcredits-program-manager' ),
			'description' => (string) $facts['description'],
		);
	}

	/**
	 * How a description is shown: 'whole' when it is short, 'folded' when it is longer than `DESCRIPTION_SHOWN`
	 * characters, '' when there is none.
	 *
	 * @param string $text The description.
	 * @return string
	 */
	private static function description_shown( $text ) {
		if ( '' === trim( (string) $text ) ) {
			return '';
		}

		return mb_strlen( (string) $text ) <= self::DESCRIPTION_SHOWN ? 'whole' : 'folded';
	}

	/**
	 * The sentence that names the reader's clock, once, above the list.
	 *
	 * @param DateTimeZone $zone The reader's clock.
	 * @return string
	 */
	private static function zone_sentence( DateTimeZone $zone ) {
		return sprintf(
			/* translators: %s: the time zone's name, e.g. America / New York. */
			__( 'Times are in your time zone, %s.', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Availability::zone_label( $zone->getName() )
		);
	}

	/**
	 * The list's heading: the program named beside the sponsors only while an event of its own is listed.
	 *
	 * @param bool $program Whether an event of the program's own is listed.
	 * @return string
	 */
	private static function heading_text( $program ) {
		return $program ? __( 'Events from our sponsors and the program', 'wpcredits-program-manager' ) : __( 'Events from our sponsors', 'wpcredits-program-manager' );
	}

	/**
	 * The mark that the reader is on an event.
	 *
	 * @return string
	 */
	private static function joined_words() {
		return __( 'You are on it.', 'wpcredits-program-manager' );
	}

	/**
	 * The call link's words.
	 *
	 * @return string
	 */
	private static function call_words() {
		return __( 'Join the call', 'wpcredits-program-manager' );
	}

	/**
	 * The words a long description is folded under.
	 *
	 * @return string
	 */
	private static function more_words() {
		return __( 'Read the description', 'wpcredits-program-manager' );
	}

	/**
	 * One press, Join or Leave: posted to admin-post.php, guarded against a second press, the nonce
	 * keyed to the event, the event it names, the page to come back to, and a button that names the
	 * event to a screen reader. Leave asks before it posts.
	 *
	 * @param string  $action ACTION_JOIN or ACTION_LEAVE.
	 * @param WP_Post $event  The event.
	 * @param string  $back   Which page this is.
	 */
	private static function render_press( $action, WP_Post $event, $back ) {
		$join  = self::ACTION_JOIN === $action;
		$words = self::press_words( $join );

		printf(
			'<form class="wpcpm-inline-form %1$s" method="post" action="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_attr( $join ? 'wpcpm-sevent__join' : 'wpcpm-sevent__leave' ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $words['busy'] )
		);
		wp_nonce_field( $action . '_' . (int) $event->ID );

		foreach ( self::press_fields( $action, $event, $back ) as $name => $value ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $value ) );
		}

		if ( $join ) {
			printf(
				'<button type="submit" class="wpcpm-button">%1$s<span class="screen-reader-text"> %2$s</span></button>',
				esc_html( $words['label'] ),
				esc_html( $event->post_title )
			);
		} else {
			printf(
				'<button type="submit" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-confirm="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></button>',
				esc_attr( $words['confirm'] ),
				esc_html( $words['label'] ),
				esc_html( $event->post_title )
			);
		}

		echo '</form>';
	}

	/**
	 * The outcome waiting on the list's channel for the signed-in person, taken, or only peeked at and
	 * left: its notice kind and its sentence, or null when there is none, or the status is none of the
	 * list's.
	 *
	 * @param bool $take Take the outcome, or peek at it and leave it.
	 * @return array{0: string, 1: string}|null
	 */
	private static function pending( $take = true ) {
		$status   = $take ? WPCPM_Flash::take( self::FLASH ) : WPCPM_Flash::peek( self::FLASH );
		$messages = self::messages();

		return is_string( $status ) && isset( $messages[ $status ] ) ? $messages[ $status ] : null;
	}

	/**
	 * An outcome, announced to a screen reader as it is drawn.
	 *
	 * @param array|null $message Its notice kind and its sentence, or null for nothing.
	 */
	private static function draw_message( $message ) {
		if ( ! is_array( $message ) ) {
			return;
		}

		// On the Institution Dashboard's parts, the shell's outcome in the list's tone: a refusal alerts and takes the
		// focus, a success is polite (Task 22).
		if ( self::parts() ) {
			echo WPCPM_Dashboard_Card::message( $message[0], $message[1], 'wpcpm-sevent__message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Dashboard_Card::message() escapes what it prints.

			return;
		}

		printf(
			'<p class="wpcpm-sevent__message is-%1$s" role="status">%2$s</p>',
			esc_attr( $message[0] ),
			esc_html( $message[1] )
		);
	}

	/**
	 * The places left on an event, in words: open to everyone for no limit, full when none is left.
	 *
	 * @param int $places The places; 0 for no limit.
	 * @param int $joined How many people are on it.
	 * @return string
	 */
	private static function places( $places, $joined ) {
		if ( (int) $places <= 0 ) {
			return __( 'Open to everyone', 'wpcredits-program-manager' );
		}

		$left = max( 0, (int) $places - (int) $joined );

		if ( 0 === $left ) {
			return __( 'Full', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: how many places are left on the event. */
			_n( '%s place left', '%s places left', $left, 'wpcredits-program-manager' ),
			number_format_i18n( $left )
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The presses
	 * --------------------------------------------------------------------
	 */

	/**
	 * Join: the person pressing goes on the event the form names, and is mailed the event with its
	 * call link and the calendar file that adds it to their calendar.
	 */
	public static function handle_join() {
		// Before the nonce: somebody whose login lapsed while the page sat open is told to log in,
		// not that the link has expired, as the group sessions tell them.
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in to join an event.', 'wpcredits-program-manager' ), 403 );
		}

		$event  = self::begin( self::ACTION_JOIN );
		$joined = WPCPM_Sponsor_Event::join( $event, get_current_user_id() );

		if ( is_wp_error( $joined ) ) {
			self::go_back( self::status_of( $joined ) );
		}

		self::mail_person( (int) $event->ID, get_current_user_id(), true );
		self::go_back( 'joined' );
	}

	/**
	 * Leave: the person pressing comes off the event the form names, their place free at once, and
	 * is mailed the calendar file that takes it off their calendar, while the event goes on for the
	 * rest. An event canceled since the page was drawn is refused by the store: the cancel told them
	 * already, and took it out of their calendar.
	 */
	public static function handle_leave() {
		// Before the nonce, as a join asks it.
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in to leave an event.', 'wpcredits-program-manager' ), 403 );
		}

		$event = self::begin( self::ACTION_LEAVE );
		$left  = WPCPM_Sponsor_Event::leave( $event, get_current_user_id() );

		if ( is_wp_error( $left ) ) {
			self::go_back( self::status_of( $left ) );
		}

		self::mail_person( (int) $event->ID, get_current_user_id(), false );
		self::go_back( 'left' );
	}

	/**
	 * What both presses ask once the person is known to be signed in, in this order: the nonce keyed
	 * to the event the form names, the event's ID the one value read before it, so a request without
	 * the nonce learns nothing of which post IDs are events; the launch switch; an event.
	 *
	 * @param string $action The press's action, which keys the nonce.
	 * @return WP_Post The event; dies with a 403 while the switch is off, goes back on any other
	 *                 refusal.
	 */
	private static function begin( $action ) {
		$event_id = WPCPM_Request::posted_id( self::FIELD_EVENT );

		check_admin_referer( $action . '_' . $event_id );

		// While the switch is off nothing here is drawn, so a press comes from a page drawn before it
		// went off, or from a request made by hand: nobody joins or leaves a benefit the program has
		// not announced, and nobody is mailed about one. Answered on the spot, as a lapsed login is:
		// no list or card is drawn to say it while the switch is off, and a flash would wait to be
		// said beside a later list.
		if ( ! self::live() ) {
			wp_die( esc_html__( 'This is not available.', 'wpcredits-program-manager' ), 403 );
		}

		$event = $event_id > 0 ? get_post( $event_id ) : null;

		if ( ! WPCPM_Sponsor_Event::is_event( $event ) ) {
			self::go_back( 'gone' );
		}

		return $event;
	}

	/**
	 * The outcome a refusal of the store goes back with. A refusal no form could have caused is said
	 * as a failure to try again.
	 *
	 * @param WP_Error $refusal The store's refusal.
	 * @return string A key of `messages()`.
	 */
	private static function status_of( WP_Error $refusal ) {
		$statuses = array(
			'not_open'   => 'not-open',
			'audience'   => 'audience',
			'own'        => 'own',
			'busy'       => 'busy',
			'past'       => 'past',
			'joined'     => 'already',
			'full'       => 'full',
			'not_joined' => 'not-joined',
			'cancelled'  => 'canceled',
		);
		$code     = (string) $refusal->get_error_code();

		return isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'failed';
	}

	/**
	 * Flash on the list's channel and go back to the page the form named, then end the request.
	 *
	 * @param string $status A key of `messages()`.
	 */
	private static function go_back( $status ) {
		WPCPM_Flash::set( self::FLASH, (string) $status );
		wp_safe_redirect( self::back_to( WPCPM_Request::posted_key( self::FIELD_BACK ), get_current_user_id() ) );
		exit;
	}

	/**
	 * Where a press goes back to: the page the form named, as one of the four keys, each mapped to a
	 * dashboard this plugin builds, at the list's place on it; a posted address is never followed. A
	 * key that is none of the four, or a page that is not published, goes to the page the person's
	 * audience implies instead (a manager's is the Administrator Dashboard, a student's the Student
	 * Report Card, a mentor's the Mentor Report Card, an institution contact's the Institution
	 * Dashboard), and with none of those to the front page.
	 *
	 * @param string           $key  What the form posted.
	 * @param int|WP_User|null $user The person who pressed.
	 * @return string
	 */
	public static function back_to( $key, $user ) {
		$url = self::page_of( (string) $key );

		if ( '' === $url && class_exists( 'WPCPM_Sponsor_Event' ) ) {
			$url = self::page_for( WPCPM_Sponsor_Event::audience_of( $user ) );
		}

		return '' === $url ? home_url( '/' ) : $url;
	}

	/**
	 * The page an audience joins the events on, at the list's place there (the program's card's on
	 * the Administrator Dashboard): a current student's Student Report Card, an Active mentor's
	 * Mentor Report Card, an institution contact's Institution Dashboard, a program manager's
	 * Administrator Dashboard. Where a press with no page of its own goes back to (`back_to()`), and
	 * where the Partners page sends a signed-in viewer to join (`WPCPM_Sponsors_Listing`), so the two
	 * cannot name different pages for one person.
	 *
	 * @param string $audience One of the store's AUDIENCE_* values, as `audience_of()` answers, or ''.
	 * @return string '' for somebody no event is for, or for a page that is not published.
	 */
	public static function page_for( $audience ) {
		return self::page_of( self::key_for( (string) $audience ) );
	}

	/**
	 * The four keys a form may post.
	 *
	 * @return string[]
	 */
	private static function backs() {
		return array( self::BACK_STUDENT, self::BACK_MENTOR, self::BACK_ADMIN, self::BACK_INSTITUTION );
	}

	/**
	 * The key of the page an audience's own list, or card, is drawn on.
	 *
	 * @param string $audience One of the store's AUDIENCE_* values, or ''.
	 * @return string One of the BACK_* keys, or ''.
	 */
	private static function key_for( $audience ) {
		$keys = array(
			WPCPM_Sponsor_Event::AUDIENCE_MANAGERS     => self::BACK_ADMIN,
			WPCPM_Sponsor_Event::AUDIENCE_STUDENTS     => self::BACK_STUDENT,
			WPCPM_Sponsor_Event::AUDIENCE_MENTORS      => self::BACK_MENTOR,
			WPCPM_Sponsor_Event::AUDIENCE_INSTITUTIONS => self::BACK_INSTITUTION,
		);

		return isset( $keys[ $audience ] ) ? $keys[ $audience ] : '';
	}

	/**
	 * The page one of the four keys names, at the list's anchor on it (the program's card's on the
	 * Administrator Dashboard), or '' for a key that is none of them or a page that is not published.
	 *
	 * @param string $key A BACK_* key, or anything.
	 * @return string
	 */
	private static function page_of( $key ) {
		$page   = '';
		$anchor = self::ANCHOR;

		switch ( $key ) {
			case self::BACK_STUDENT:
				$page = class_exists( 'WPCPM_Students_Dashboard' ) ? (string) WPCPM_Students_Dashboard::page_url() : '';
				break;
			case self::BACK_MENTOR:
				$page = class_exists( 'WPCPM_Mentors_Dashboard' ) ? (string) WPCPM_Mentors_Dashboard::page_url() : '';
				break;
			case self::BACK_INSTITUTION:
				// The Institution Dashboard draws one view at a time: the Events view, at the list (`card_url()`).
				return class_exists( 'WPCPM_Institutions_Dashboard' ) ? WPCPM_Institutions_Dashboard::card_url( 'sevents' ) : '';
			case self::BACK_ADMIN:
				$page   = class_exists( 'WPCPM_Administrators_Dashboard' ) ? (string) WPCPM_Administrators_Dashboard::page_url() : '';
				$anchor = self::ADMIN_ANCHOR;
				break;
		}

		return '' === $page ? '' : $page . '#' . $anchor;
	}

	/*
	 * --------------------------------------------------------------------
	 * The mails
	 * --------------------------------------------------------------------
	 */

	/**
	 * Mail the person who joined or left, in their language and on their own clock, the event read
	 * after the store's write so its calendar file carries the version the press raised.
	 *
	 * @param int  $event_id The event.
	 * @param int  $user_id  The person.
	 * @param bool $joined   Whether they joined; false for a leave.
	 * @return bool Whether the mail layer handed the mail off.
	 */
	private static function mail_person( $event_id, $user_id, $joined ) {
		$event = get_post( (int) $event_id );

		if ( ! WPCPM_Sponsor_Event::is_event( $event ) ) {
			return false;
		}

		$sponsor = WPCPM_Sponsor_Event::sponsor_name( WPCPM_Sponsor_Event::sponsor_of( $event ) );
		$id      = ( $joined ? 'event-joined' : 'event-left' ) . ( WPCPM_Sponsor_Event::is_program( $event ) ? '-program' : '' );

		return WPCPM_Mail::send(
			(int) $user_id,
			$id,
			static function ( WP_User $recipient ) use ( $event, $sponsor, $joined, $id ) {
				return $joined ? self::joined_mail( $event, $sponsor, $id, $recipient ) : self::left_mail( $event, $sponsor, $id, $recipient );
			}
		);
	}

	/**
	 * The mail to a person who joined, from its template (`event-joined`, or `event-joined-program` for
	 * an event of the program's own, which names the WordPress Credits Program as its host): whose
	 * event, which, when on their clock with the zone named, how long, the call link, and the calendar
	 * file that adds it to their calendar, their own, naming them, with a sentence about the file only
	 * when there is one. Nobody else on the event is named. The title is the one typed, read back by
	 * the registry.
	 *
	 * @param WP_Post $event     The event.
	 * @param string  $sponsor   The sponsor's name.
	 * @param string  $id        The template's id.
	 * @param WP_User $recipient The person.
	 * @return array `subject`, `body`, `attachments` and `cleanup`.
	 */
	private static function joined_mail( WP_Post $event, $sponsor, $id, WP_User $recipient ) {
		$facts  = WPCPM_Sponsor_Event::facts( $event );
		$files  = WPCPM_Sponsor_Event::calendar_file( $event, WPCPM_ICS::METHOD_REQUEST, $recipient );
		$values = WPCPM_Sponsor_Event::mail_values( $facts, WPCPM_Sponsor_Event::link_of( $event ), WPCPM_Mentor_Availability::viewer_timezone( (int) $recipient->ID ), $sponsor );

		$values['calendar_note'] = array() === $files ? '' : __( 'The calendar file attached adds it to your calendar.', 'wpcredits-program-manager' );

		return WPCPM_Mail_Templates::mail(
			$id,
			$values,
			array(
				'attachments' => $files,
				'cleanup'     => $files,
			)
		);
	}

	/**
	 * The mail to a person who left, from its template (`event-left`, or `event-left-program`): whose
	 * event, its sponsor's or the WordPress Credits Program's, and the calendar file that takes it off
	 * their calendar, a cancel sent to them alone, naming them, while the event goes on for everybody
	 * else. The subject names the event by the title typed, read back by the registry.
	 *
	 * @param WP_Post $event     The event.
	 * @param string  $sponsor   The sponsor's name.
	 * @param string  $id        The template's id.
	 * @param WP_User $recipient The person.
	 * @return array `subject`, `body`, `attachments` and `cleanup`.
	 */
	private static function left_mail( WP_Post $event, $sponsor, $id, WP_User $recipient ) {
		$files = WPCPM_Sponsor_Event::calendar_file( $event, WPCPM_ICS::METHOD_CANCEL, $recipient );

		return WPCPM_Mail_Templates::mail(
			$id,
			array(
				'site_name'     => WPCPM_Mail::site_name(),
				'event_title'   => (string) $event->post_title,
				'company_name'  => $sponsor,
				'calendar_note' => array() === $files ? '' : __( 'The calendar file attached takes it off your calendar.', 'wpcredits-program-manager' ),
			),
			array(
				'attachments' => $files,
				'cleanup'     => $files,
			)
		);
	}

	/**
	 * Whether the list and its presses are in use: the launch switch is on, and the event store is
	 * loaded.
	 *
	 * @return bool
	 */
	private static function live() {
		return class_exists( 'WPCPM_Sponsor_Event' ) && WPCPM_Sponsor_Tiers::live();
	}
}
