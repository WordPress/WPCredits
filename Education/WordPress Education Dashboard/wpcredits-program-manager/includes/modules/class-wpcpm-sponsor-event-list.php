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
		if ( ! $viewer instanceof WP_User || ! $viewer->exists() || get_current_user_id() !== (int) $viewer->ID ) {
			return;
		}

		$message = self::pending();

		if ( ! self::live() ) {
			return;
		}

		$events = WPCPM_Sponsor_Event::open_for( $viewer );

		if ( array() === $events && null === $message ) {
			return;
		}

		$zone    = WPCPM_Mentor_Availability::viewer_timezone( (int) $viewer->ID );
		$program = false;

		foreach ( $events as $event ) {
			$program = $program || WPCPM_Sponsor_Event::is_program( $event );
		}

		printf( '<section class="wpcpm-sevents" id="%s">', esc_attr( self::ANCHOR ) );
		self::render_heading( $back, $program );

		if ( array() !== $events ) {
			printf(
				'<p class="wpcpm-sevents__zone">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the time zone's name, e.g. America / New York. */
						__( 'Times are in your time zone, %s.', 'wpcredits-program-manager' ),
						WPCPM_Mentor_Availability::zone_label( $zone->getName() )
					)
				)
			);
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
		$text = $program ? __( 'Events from our sponsors and the program', 'wpcredits-program-manager' ) : __( 'Events from our sponsors', 'wpcredits-program-manager' );

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
	 * Dashboard's card draws this on each event under Coming up, and the call link itself.
	 *
	 * @param WP_Post      $event  The event, one a list or the program's card read as not over yet.
	 * @param WP_User|null $viewer The person looking, who presses as themselves; nothing is drawn
	 *                             for anything that is not an account.
	 * @param string       $back   Which page this is: one of the BACK_* keys.
	 */
	public static function render_actions( WP_Post $event, $viewer, $back ) {
		if ( ! self::live() || ! WPCPM_Sponsor_Event::is_event( $event ) || ! $viewer instanceof WP_User || ! $viewer->exists() ) {
			return;
		}

		$facts = WPCPM_Sponsor_Event::facts( $event );

		if ( WPCPM_Sponsor_Event::STATE_APPROVED !== $facts['state'] ) {
			return;
		}

		$joined = WPCPM_Sponsor_Event::joined( $event, $viewer );
		$full   = $facts['places'] > 0 && $facts['joined'] >= $facts['places'];

		// The store's own tests, so no Join is drawn that it would refuse; somebody on the event may
		// still leave it whatever became of its places or its sponsor.
		if ( ! $joined && ( $full || ! WPCPM_Sponsor_Event::offers_events( $event ) ) ) {
			return;
		}

		echo '<div class="wpcpm-sevent__actions">';

		if ( $joined ) {
			printf( '<p class="wpcpm-sevent__joined">%s</p>', esc_html__( 'You are on it.', 'wpcredits-program-manager' ) );
			self::render_press( self::ACTION_LEAVE, $event, $back );

			$link = WPCPM_Sponsor_Event::link_of( $event );

			// Named to a screen reader with its event, as Join and Leave are: a list of several
			// events otherwise reads "Join the call" once for each, with nothing to tell them apart.
			if ( self::BACK_ADMIN !== $back && '' !== $link ) {
				printf(
					'<a class="wpcpm-sevent__link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a>',
					esc_url( $link ),
					esc_html__( 'Join the call', 'wpcredits-program-manager' ),
					esc_html( $event->post_title )
				);
			}
		} else {
			self::render_press( self::ACTION_JOIN, $event, $back );
		}

		echo '</div>';
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
		$facts = WPCPM_Sponsor_Event::facts( $event );

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
					WPCPM_Mentor_Calls::format_range( $facts['start'], $facts['end'], $zone ),
					WPCPM_Sponsor_Event::length_line( $facts['minutes'] ),
					WPCPM_Sponsor_Event::offers_events( $event ) ? self::places( $facts['places'], $facts['joined'] ) : __( 'No longer offered', 'wpcredits-program-manager' )
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
		$text = (string) $text;

		if ( '' === trim( $text ) ) {
			return;
		}

		if ( mb_strlen( $text ) <= self::DESCRIPTION_SHOWN ) {
			printf( '<p class="wpcpm-sevent__description">%s</p>', esc_html( $text ) );

			return;
		}

		printf(
			'<details class="wpcpm-sevent__more"><summary>%1$s</summary><p class="wpcpm-sevent__description">%2$s</p></details>',
			esc_html__( 'Read the description', 'wpcredits-program-manager' ),
			esc_html( $text )
		);
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
		$join = self::ACTION_JOIN === $action;

		printf(
			'<form class="wpcpm-inline-form %1$s" method="post" action="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_attr( $join ? 'wpcpm-sevent__join' : 'wpcpm-sevent__leave' ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $join ? __( 'Joining', 'wpcredits-program-manager' ) : __( 'Leaving', 'wpcredits-program-manager' ) )
		);
		wp_nonce_field( $action . '_' . (int) $event->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_EVENT ), (int) $event->ID );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_BACK ), esc_attr( in_array( $back, self::backs(), true ) ? $back : '' ) );

		if ( $join ) {
			printf(
				'<button type="submit" class="wpcpm-button">%1$s<span class="screen-reader-text"> %2$s</span></button>',
				esc_html__( 'Join', 'wpcredits-program-manager' ),
				esc_html( $event->post_title )
			);
		} else {
			printf(
				'<button type="submit" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-confirm="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></button>',
				esc_attr__( 'Leave this event? Your place goes back.', 'wpcredits-program-manager' ),
				esc_html__( 'Leave', 'wpcredits-program-manager' ),
				esc_html( $event->post_title )
			);
		}

		echo '</form>';
	}

	/**
	 * The outcome waiting on the list's channel for the signed-in person, taken: its notice kind and
	 * its sentence, or null when there is none, or the status is none of the list's.
	 *
	 * @return array{0: string, 1: string}|null
	 */
	private static function pending() {
		$status   = WPCPM_Flash::take( self::FLASH );
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
			wp_die( esc_html__( 'Please log in to join an event.', 'wpcredits-program-manager' ), 403 );
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
			wp_die( esc_html__( 'Please log in to leave an event.', 'wpcredits-program-manager' ), 403 );
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
				$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? (string) WPCPM_Institutions_Dashboard::page_url() : '';
				break;
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
