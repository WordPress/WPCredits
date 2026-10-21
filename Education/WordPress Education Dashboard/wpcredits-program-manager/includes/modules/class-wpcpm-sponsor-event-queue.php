<?php
/**
 * The Administrator Dashboard's sponsor events: the program approves, returns or cancels a
 * sponsor's co-hosted event, and plans, changes and cancels events of its own.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The program's side of a sponsor's co-hosted event (the design's section 18) and its own events
 * (section 20): the Events card on the Administrator Dashboard, the tile above it, and the six
 * presses made on it. The Sponsors screen's Waiting for review tab lists what waits from the card's
 * own read of it (`waiting_rows()`), each event linking to its item on the card, and the screen's
 * menu bubble counts it as the tile does (`count_waiting()`).
 *
 * The card has three parts. What waits for the program's OK: every sponsor's planned event not over
 * yet, the one planned first at the top, as a queue is worked, each with Approve and Return with a
 * note. What is coming up: every approved event not over yet, the soonest first, whatever its
 * sponsor's standing, each with how many joined, who, the manager's own Join or Leave (drawn by
 * the list the four dashboards share, `WPCPM_Sponsor_Event_List`, whose outcome is said under the
 * part's heading), and Cancel with a note. A sponsor that no longer offers events
 * (`WPCPM_Sponsor_Event::offers_events()`: not Approved, gone from the store, or without a tier
 * that has them today) offers its event to nobody, and the card says so: waiting, the event has
 * Return alone, since an approval would open it to nobody, and the store refuses one; approved,
 * the people already on it still expect it, so a program manager has to see it to call it off.
 * Each event reads on the viewing manager's own clock, with its call link, which the program
 * approves with the rest. The names of the people on an event are read here, for the program
 * managers the card is drawn for, and reach nothing the sponsor reads.
 *
 * The third part plans an event of the program's own (`WPCPM_Sponsor_Event::plan_program()`),
 * hosted by the WordPress Credits Program with no sponsor and approved as it is planned, in the
 * decide row the items' decisions sit in, with the planner's own form rows and field readers
 * (`WPCPM_Sponsor_Event_Planner::render_fields()` and `posted_fields()`), so the two forms ask for
 * the same six things; its day box has no last day, no term binding the program. Its events are
 * listed under Coming up with the rest, named for the program where a sponsor's name the sponsor,
 * and offered while they are approved; each carries the manager's own Join or Leave, then Change
 * the event, the same form filled from the event, and Cancel the event, which asks first and needs
 * no note: the program has no members to write to, and the store tells everyone on the event, as it
 * does for a sponsor's. Nobody else is mailed about the program's own events, a plan included. A
 * plan or a change refused for a reason the manager puts right by pressing again comes back with
 * what was typed, the title and the description exactly as typed, kept for that manager through the
 * planner's own keeper and read once by the form it came from
 * (`WPCPM_Sponsor_Event_Planner::keep_typed()` and `kept_for()`), its fold drawn open, as the
 * Sponsor Dashboard's forms come back.
 *
 * A title, a description or a note the text cleaner would take words from is refused before the
 * store is asked, as on the Sponsor Dashboard's planner: "Ages 8 <12 welcome, adults >18 pay" would
 * be stored as `Ages 8 18 pay`, and the members mailed the rest without a word said. Asked of what
 * was typed, so a refused press stores, logs, mails and sends nothing and looks no link up; the box
 * named in the words of the form's button (`messages()`); the form coming back once with exactly
 * what was typed, for the manager who pressed and in that form alone, its fold open: the program's
 * plan and change through the planner's own check (`WPCPM_Sponsor_Event_Planner::loss_check()`),
 * and a note through `refuse_note()`. A Change form's box posted back as it was drawn is not asked,
 * and the store keeps what the event holds there.
 *
 * Nothing is drawn, counted or read, and no press acts, while the launch switch is off: the
 * program's side of a tier benefit the public has not been told of is as dark as the sponsor's.
 * Each press checks the nonce keyed to the event first (the program's plan, which names no event,
 * its own action's nonce), then the management capability, then the switch and the event, and whose
 * the event is (a sponsor's three decisions take a sponsor's event alone, and the program's change
 * and cancel its own), and only then hands the store its move; the store takes the event's lock,
 * the sponsor's or the program's, reads the event again under it and writes the audit row. The
 * sponsor's live members are mailed every decision, each on their own clock: an approval with the
 * event's calendar file; a return with the note, and word that they can plan again only while the
 * sponsor still offers events; a cancel with the note, how many of the people on the event were
 * told when that is every one of them and, for an event the approval put in their calendars, the
 * file that takes it out. Each member's file names that member, as the file each person on the
 * event is sent names them. Each person on the event is told by the store's cancel itself, with
 * that file: the first fifty before the members are mailed, and the rest by the store's drain,
 * which the members' mail counts as being told. Every decision goes back to the card through
 * `WPCPM_Return`, its sentence on the Administrator Dashboard's channel; a stale nonce and a
 * missing capability stop on WordPress's own screens instead, and flash nothing.
 */
final class WPCPM_Sponsor_Event_Queue {

	/** Approve a waiting event. Nonce keyed to the event. */
	const ACTION_APPROVE = 'wpcpm_sevent_approve';

	/** Return a waiting event with a note, which cancels it. Nonce keyed to the event. */
	const ACTION_RETURN = 'wpcpm_sevent_return';

	/** Cancel an approved event with a note. Nonce keyed to the event. */
	const ACTION_CANCEL = 'wpcpm_sevent_manager_cancel';

	/** Plan an event of the program's own. Nonce of the action alone: the form names no event. */
	const ACTION_PROGRAM_PLAN = 'wpcpm_sevent_program_plan';

	/** Change an event of the program's own. Nonce keyed to the event. */
	const ACTION_PROGRAM_CHANGE = 'wpcpm_sevent_program_change';

	/** Cancel an event of the program's own, with no note. Nonce keyed to the event. */
	const ACTION_PROGRAM_CANCEL = 'wpcpm_sevent_program_cancel';

	/** The card's anchor on the Administrator Dashboard, one of `WPCPM_Return::ANCHORS`. */
	const CARD = 'sponsor-events';

	/** The event a form names, as its post ID: the field the planner's forms post it in. */
	const FIELD_EVENT = 'wpcpm_sevent_id';

	/** The note a return or a cancel carries. */
	const FIELD_NOTE = 'wpcpm_sevent_note';

	/** What a refused return keeps its note under, before the event it returns (`WPCPM_Typed_Text::box_form()`). */
	const TYPED_RETURN = 'sevent-return';

	/** What a refused cancel keeps its note under, before the event it cancels (`WPCPM_Typed_Text::box_form()`). */
	const TYPED_CANCEL = 'sevent-cancel';

	/** How long a description is shown whole, in characters; a longer one is folded. */
	const DESCRIPTION_SHOWN = 300;

	/**
	 * The classes of the program's own plan and change forms: the card's own form class, which the
	 * decide row's rules take, and the planner's two, whose rules lay out the planner's rows these
	 * forms are drawn with, on this page as on the Sponsor Dashboard.
	 */
	const PROGRAM_FORM = 'wpcpm-sponsor-post__form wpcpm-sponsor__form wpcpm-sevent__form';

	/**
	 * The outcomes of the program's own plan and change a manager puts right by pressing again, whose
	 * form comes back with what was typed, the title and the description exactly as typed
	 * (`WPCPM_Sponsor_Event_Planner::keep_typed()`): the form's own refusal, and the store's
	 * refusals of a start that has passed, of fewer places than the people on the event, of the
	 * program's lock held and of a write that failed.
	 *
	 * A refusal for words the text cleaner would take (`sevent-program-plan-title-loss` and the three
	 * like it) is not on the list, though its form comes back too: it keeps exactly what was typed
	 * under the form's own name before it leaves (`WPCPM_Sponsor_Event_Planner::loss_check()`).
	 *
	 * Every other outcome keeps nothing: a success; the switch off, which draws no card; an event
	 * gone, canceled or over, whose form is gone; and a sponsor's event named by a request made by
	 * hand (`sevent-program-not-its`).
	 */
	const KEEPS_TYPED = array( 'sevent-program-fields', 'sevent-program-in-past', 'sevent-program-shrink', 'sevent-program-busy', 'sevent-failed' );

	// What the sponsor's members are told the program decided.
	const MAIL_APPROVED = 'approved';
	const MAIL_RETURNED = 'returned';
	const MAIL_CANCELED = 'canceled';

	/**
	 * The six presses: the program's three decisions on a sponsor's event, and the plan, the change
	 * and the cancel of an event of its own.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_APPROVE, array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_' . self::ACTION_RETURN, array( __CLASS__, 'handle_return' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_PROGRAM_PLAN, array( __CLASS__, 'handle_program_plan' ) );
		add_action( 'admin_post_' . self::ACTION_PROGRAM_CHANGE, array( __CLASS__, 'handle_program_change' ) );
		add_action( 'admin_post_' . self::ACTION_PROGRAM_CANCEL, array( __CLASS__, 'handle_program_cancel' ) );
	}

	/**
	 * The card's outcomes, merged into the words the Administrator Dashboard prints from its channel.
	 * A decision says what happened to the event and claims no mail: a sponsor may have no member to
	 * mail, and an event nobody on. The program's own presses say theirs in the same voice, and name
	 * the program where a sponsor's refusal names the sponsor; a cancel of the program's own event
	 * says what a manager's cancel of a sponsor's says, and the outcomes they share with the three
	 * decisions (the switch, an event gone, canceled already, over, a failure) are the same keys. A
	 * title, a description or a note the text cleaner would take words from is refused in a sentence
	 * that names the box and says nothing was planned, saved, returned or canceled, as the form's
	 * button reads: Plan the event, Save the changes, Return it with this note, Cancel with this note
	 * (`WPCPM_Typed_Text::loss_message()`).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'sevent-approved'                        => array( 'success', __( 'The event is approved.', 'wpcredits-program-manager' ) ),
			'sevent-returned'                        => array( 'success', __( 'The event went back to the sponsor, canceled, with your note.', 'wpcredits-program-manager' ) ),
			'sevent-manager-canceled'                => array( 'success', __( 'The event is canceled.', 'wpcredits-program-manager' ) ),
			'sevent-note-empty'                      => array( 'error', __( 'Nothing was changed. Write a note for the sponsor: it is the whole of what the sponsor is told.', 'wpcredits-program-manager' ) ),
			'sevent-note-long'                       => array(
				'error',
				sprintf(
					/* translators: %d: the longest note the program keeps, in characters. */
					_n( 'Nothing was changed. Keep the note to %d character.', 'Nothing was changed. Keep the note to %d characters.', WPCPM_Sponsor_Event::MAX_NOTE, 'wpcredits-program-manager' ),
					WPCPM_Sponsor_Event::MAX_NOTE
				),
			),
			'sevent-not-waiting'                     => array( 'error', __( 'Nothing was changed. That event was approved already; to call it off, cancel it under Coming up.', 'wpcredits-program-manager' ) ),
			'sevent-no-tier'                         => array( 'error', __( 'Nothing was changed. That sponsor no longer offers events, so its event cannot be approved. Return it with a note.', 'wpcredits-program-manager' ) ),
			'sevent-was-canceled'                    => array( 'error', __( 'Nothing was changed. That event was canceled already.', 'wpcredits-program-manager' ) ),
			'sevent-past'                            => array( 'error', __( 'Nothing was changed. That event\'s time has passed.', 'wpcredits-program-manager' ) ),
			'sevent-busy'                            => array( 'error', __( 'Nothing was changed. Another change to that sponsor\'s events was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'sevent-gone'                            => array( 'error', __( 'Nothing was changed. That event is not on this site anymore.', 'wpcredits-program-manager' ) ),
			'sevent-off'                             => array( 'error', __( 'Nothing was changed. Events wait until Sponsor tiers is switched on in Settings.', 'wpcredits-program-manager' ) ),
			'sevent-failed'                          => array( 'error', __( 'Nothing was changed. The event could not be saved right now. Try again later.', 'wpcredits-program-manager' ) ),
			'sevent-program-planned'                 => array( 'success', __( 'The event is planned, and open to join now: the program\'s own events need no approval.', 'wpcredits-program-manager' ) ),
			'sevent-program-changed'                 => array( 'success', __( 'The event is changed.', 'wpcredits-program-manager' ) ),
			'sevent-program-unchanged'               => array( 'success', __( 'Nothing changed.', 'wpcredits-program-manager' ) ),
			'sevent-program-fields'                  => array( 'error', __( 'Nothing was changed. Some of the event\'s details are missing or not valid.', 'wpcredits-program-manager' ) ),
			'sevent-program-in-past'                 => array( 'error', __( 'Nothing was changed. That day and time have passed. Choose a time still to come.', 'wpcredits-program-manager' ) ),
			'sevent-program-shrink'                  => array( 'error', __( 'Nothing was changed. More people have joined than that many places would hold.', 'wpcredits-program-manager' ) ),
			'sevent-program-busy'                    => array( 'error', __( 'Nothing was changed. Another change to the program\'s events was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'sevent-program-not-its'                 => array( 'error', __( 'Nothing was changed. That event is a sponsor\'s, not one of the program\'s own.', 'wpcredits-program-manager' ) ),
			'sevent-program-own'                     => array( 'error', __( 'Nothing was changed. That event is the program\'s own: change or cancel it under Coming up.', 'wpcredits-program-manager' ) ),
			'sevent-program-plan-title-loss'         => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Title', 'wpcredits-program-manager' ), 'plan' ) ),
			'sevent-program-plan-description-loss'   => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Description', 'wpcredits-program-manager' ), 'plan' ) ),
			'sevent-program-change-title-loss'       => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Title', 'wpcredits-program-manager' ) ) ),
			'sevent-program-change-description-loss' => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Description', 'wpcredits-program-manager' ) ) ),
			'sevent-return-note-loss'                => array( 'error', WPCPM_Typed_Text::loss_message( self::note_label(), 'return' ) ),
			'sevent-cancel-note-loss'                => array( 'error', WPCPM_Typed_Text::loss_message( self::note_label(), 'cancel' ) ),
		);
	}

	/**
	 * Whether the card, its tile and its presses are in use: the launch switch is on, and the event
	 * store is loaded.
	 *
	 * @return bool
	 */
	public static function shown() {
		return class_exists( 'WPCPM_Sponsor_Event' ) && WPCPM_Sponsor_Tiers::live();
	}

	/*
	 * --------------------------------------------------------------------
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * What the card draws, read once for the page: the events waiting for the program's OK
	 * (`waiting_rows()`) and the approved events still to take place. Nothing while the card is not
	 * in use, and nothing for somebody who does not manage the program.
	 *
	 * @return array{waiting: array[], upcoming: array[]} Each row `id`, `sponsor` (the key, '' for an
	 *               event of the program's own), `sponsor_name` (the sponsor's name, or the WordPress
	 *               Credits Program for the program's own), `title`, `description`, `start`, `end`,
	 *               `minutes`, `places` (0 for no limit), `joined`, `link` (the call link), `people`
	 *               (the names of the people on it, sorted), for program managers alone, `offered`
	 *               (whether its sponsor still offers events, the store's own answer, and always for
	 *               the program's own), `program` (whether it is the program's own),
	 *               `planned_author` (the ID of the account that planned it, as the post holds it,
	 *               whose account is not read here) and `planned_at` (when it was planned, a UTC
	 *               timestamp).
	 */
	public static function data() {
		$data = array(
			'waiting'  => array(),
			'upcoming' => array(),
		);

		// The names of the people on an event and its call link are the program's alone. The page
		// that draws the card asks the capability before it reads, and this asks again, so no other
		// caller is handed a student's name.
		if ( ! self::shown() || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return $data;
		}

		$data['waiting'] = self::waiting_rows();

		foreach ( WPCPM_Sponsor_Event::approved_upcoming() as $event ) {
			$data['upcoming'][] = self::row( $event );
		}

		return $data;
	}

	/**
	 * The events waiting for the program's OK, every sponsor's, the one planned first at the top, as
	 * a queue is worked: the card's first part, and the list the Sponsors screen's Waiting for review
	 * tab draws of it (`WPCPM_Sponsors::render_queue_events()`). One read for both, so the tab lists
	 * no event the card does not draw, in the card's order, and reads nothing of the approved events
	 * the card draws below. Nothing while the card is not in use, and nothing for somebody who does
	 * not manage the program: each row holds the event's call link.
	 *
	 * @return array[] Rows as `data()` documents them.
	 */
	public static function waiting_rows() {
		if ( ! self::shown() || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return array();
		}

		$waiting = WPCPM_Sponsor_Event::waiting();

		// The one planned first, whose post came first, at the top, as a queue is worked. The store
		// answers the soonest first, which would keep a request for a later day waiting behind every
		// request made after it for a sooner one.
		usort(
			$waiting,
			static function ( $a, $b ) {
				return (int) $a->ID - (int) $b->ID;
			}
		);

		$rows = array();

		foreach ( $waiting as $event ) {
			$rows[] = self::row( $event );
		}

		return $rows;
	}

	/**
	 * How many events wait for the program's OK, for the Sponsors screen's menu bubble
	 * (`WPCPM_Sponsors::attention_count()`), which counts what the tile counts (`count_for_tile()`):
	 * the store's read the card's first part is drawn from, counted, with no row built. It asks no
	 * capability, since the bubble is one number, kept for a minute for every admin page, and names
	 * nobody. Nothing while the card is not in use.
	 *
	 * @return int
	 */
	public static function count_waiting() {
		return self::shown() ? count( (array) WPCPM_Sponsor_Event::waiting() ) : 0;
	}

	/**
	 * How many events wait for the program's OK: the tile's number and the card's count, so the two
	 * cannot disagree.
	 *
	 * @param array $data What `data()` answered.
	 * @return int
	 */
	public static function count_for_tile( array $data ) {
		return isset( $data['waiting'] ) && is_array( $data['waiting'] ) ? count( $data['waiting'] ) : 0;
	}

	/**
	 * One event as the card draws it, and as the Sponsors screen's queue tab lists one waiting: who
	 * planned it is the post's author, by the ID the post holds, and when is the post's own date,
	 * which a change to the event leaves as it was. No planner's account is read here: the
	 * Administrator Dashboard and the Overview draw these rows and print no planner, so the queue tab,
	 * which prints the planners' names, reads their accounts itself
	 * (`WPCPM_Sponsors::render_queue_events()`).
	 *
	 * @param WP_Post $event The event.
	 * @return array The row `data()` documents.
	 */
	private static function row( WP_Post $event ) {
		$facts   = WPCPM_Sponsor_Event::facts( $event );
		$program = WPCPM_Sponsor_Event::is_program( $event );

		return array(
			'id'             => (int) $facts['id'],
			'sponsor'        => (string) $facts['sponsor'],
			// The program's own event is named for its host, as the lists name it.
			'sponsor_name'   => $program ? (string) $facts['host'] : WPCPM_Sponsor_Event::sponsor_name( (string) $facts['sponsor'] ),
			'title'          => (string) $facts['title'],
			'description'    => (string) $facts['description'],
			'start'          => (int) $facts['start'],
			'end'            => (int) $facts['end'],
			'minutes'        => (int) $facts['minutes'],
			'places'         => (int) $facts['places'],
			'joined'         => (int) $facts['joined'],
			'link'           => WPCPM_Sponsor_Event::link_of( $event ),
			'people'         => self::people( $event ),
			'offered'        => WPCPM_Sponsor_Event::offers_events( $event ),
			'program'        => $program,
			'planned_author' => (int) $event->post_author,
			'planned_at'     => (int) get_post_time( 'U', true, $event ),
		);
	}

	/**
	 * The names of the people on an event, sorted as a person reads a list, letter case aside. Read
	 * here, for the program managers the card is drawn for, the one audience entitled to them: the
	 * event keeps user IDs, and nothing the sponsor reads holds a name. An account that is gone has
	 * no name to show. The accounts are read in one query before the loop, rather than one each:
	 * an event open to everyone may have hundreds of people on it.
	 *
	 * @param WP_Post $event The event.
	 * @return string[]
	 */
	private static function people( WP_Post $event ) {
		$names  = array();
		$people = WPCPM_Sponsor_Event::joiners( $event );

		if ( array() !== $people ) {
			cache_users( $people );
		}

		foreach ( $people as $person_id ) {
			$person = get_userdata( (int) $person_id );

			if ( $person instanceof WP_User ) {
				$names[] = (string) $person->display_name;
			}
		}

		usort( $names, 'strnatcasecmp' );

		return $names;
	}

	/*
	 * --------------------------------------------------------------------
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The card's three parts, inside the frame the Administrator Dashboard's cards draw: the first
	 * two keep their headings, with one sentence under each when it is empty, so the card has one
	 * shape whatever the day holds, and the third, the program's own plan, is always drawn. Nothing
	 * while the card is not in use.
	 *
	 * @param array $data What `data()` answered.
	 */
	public static function render( array $data ) {
		if ( ! self::shown() ) {
			return;
		}

		$waiting  = isset( $data['waiting'] ) ? (array) $data['waiting'] : array();
		$upcoming = isset( $data['upcoming'] ) ? (array) $data['upcoming'] : array();
		$zone     = WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() );

		self::subtitle( __( 'Waiting for the program\'s OK', 'wpcredits-program-manager' ) );

		if ( array() === $waiting ) {
			self::empty_line( __( 'Nothing is waiting for the program\'s OK.', 'wpcredits-program-manager' ) );
		}

		foreach ( $waiting as $row ) {
			self::render_item( (array) $row, $zone, false );
		}

		self::subtitle( __( 'Coming up', 'wpcredits-program-manager' ) );

		// The outcome of the manager's own Join or Leave, pressed on an event under this heading.
		if ( class_exists( 'WPCPM_Sponsor_Event_List' ) ) {
			WPCPM_Sponsor_Event_List::render_message();
		}

		if ( array() === $upcoming ) {
			self::empty_line( __( 'No event is coming up.', 'wpcredits-program-manager' ) );
		}

		foreach ( $upcoming as $row ) {
			self::render_item( (array) $row, $zone, true );
		}

		self::render_program_plan( $zone );
	}

	/**
	 * One event, under its own id: its title and its sponsor, or the program for an event of its own;
	 * when it runs on the manager's clock with the zone named, and its length (`time_fact()`); its
	 * places, approved, how many joined, and, for a sponsor that no longer offers events, that it is
	 * no longer offered; its description, folded when it is long; its call link; approved, who
	 * joined; then its decisions, where such a sponsor's waiting event has no Approve and an approved
	 * one carries the manager's own Join or Leave before its Cancel. An event of the program's own
	 * carries the Join or Leave, then Change the event and Cancel the event in place of Cancel with a
	 * note: there is no sponsor to write to.
	 *
	 * @param array        $row      A row of `data()`.
	 * @param DateTimeZone $zone     The viewing manager's clock.
	 * @param bool         $approved Whether the event is approved, under Coming up.
	 */
	private static function render_item( array $row, DateTimeZone $zone, $approved ) {
		$id      = (int) $row['id'];
		$title   = (string) $row['title'];
		$link    = (string) $row['link'];
		$offered = (bool) $row['offered'];

		// The id the Sponsors screen's queue tab links to, as the sponsor posts' items carry theirs.
		printf( '<article class="wpcpm-administrator__item wpcpm-sponsor-event" id="wpcpm-sponsor-event-%d">', (int) $id );
		printf(
			'<h4 class="wpcpm-administrator__item-title">%1$s <span class="wpcpm-administrator__kind">%2$s</span></h4>',
			esc_html( $title ),
			esc_html( (string) $row['sponsor_name'] )
		);

		echo '<p class="wpcpm-administrator__facts">';
		self::fact( self::time_fact( $row, $zone ) );
		self::fact( self::places( (int) $row['places'] ) );

		if ( $approved ) {
			self::fact( self::joined( (int) $row['joined'] ) );
		}

		// Said on the card, so a manager knows why a waiting event has no Approve, and that nobody can
		// join an approved one now.
		if ( ! $offered ) {
			self::fact( __( 'No longer offered', 'wpcredits-program-manager' ) );
		}

		echo '</p>';

		self::render_description( (string) $row['description'] );

		// The link is the program's to vet before it approves, and a manager may join the call.
		if ( '' !== $link ) {
			printf(
				'<p class="wpcpm-administrator__facts"><span class="wpcpm-administrator__fact">%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></span></p>',
				esc_html__( 'Call link:', 'wpcredits-program-manager' ),
				esc_url( $link ),
				esc_html( $link )
			);
		}

		if ( $approved ) {
			self::render_people( (array) $row['people'] );
		}

		echo '<div class="wpcpm-request__decide wpcpm-sponsor-post__decide">';

		if ( $approved && ! empty( $row['program'] ) ) {
			self::render_join( $id );
			self::render_program_change( $row, $zone );
			self::render_program_cancel( $id, $title );
		} elseif ( $approved ) {
			self::render_join( $id );
			self::render_cancel( $id, $title );
		} else {
			// The store refuses the approval of an event its sponsor no longer offers, so none is drawn.
			if ( $offered ) {
				self::render_approve( $id, $title );
			}

			self::render_return( $id, $title, $offered );
		}

		echo '</div></article>';
	}

	/**
	 * When an event runs, with its length and the zone named: the fact each item on the card opens
	 * with, and what the Sponsors screen's queue tab says of each event it lists
	 * (`WPCPM_Sponsors::render_queue_events()`), so the two read one event's time alike. On the clock
	 * given, else on the viewing manager's own.
	 *
	 * @param array             $row  A row of `data()`, or any array with its `start`, `end` and
	 *                                `minutes`.
	 * @param DateTimeZone|null $zone The clock to read it on; null for the viewing manager's.
	 * @return string
	 */
	public static function time_fact( array $row, $zone = null ) {
		$zone = $zone instanceof DateTimeZone ? $zone : WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() );

		return sprintf(
			/* translators: 1: the day and the start and end times, 2: how long it runs, e.g. 60 minutes, 3: the time zone's name. */
			__( '%1$s, %2$s, in your time zone (%3$s)', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Calls::format_range( (int) $row['start'], (int) $row['end'], $zone ),
			WPCPM_Sponsor_Event::length_line( (int) $row['minutes'] ),
			WPCPM_Mentor_Availability::zone_label( $zone->getName() )
		);
	}

	/**
	 * An event's description as it was written, escaped: whole when it is short, folded under a
	 * disclosure when it is long, so one long description does not push the rest of the queue away.
	 *
	 * @param string $text The description.
	 */
	private static function render_description( $text ) {
		if ( '' === trim( $text ) ) {
			return;
		}

		if ( mb_strlen( $text ) <= self::DESCRIPTION_SHOWN ) {
			printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $text ) );

			return;
		}

		printf(
			'<details class="wpcpm-administrator__details"><summary>%1$s</summary><p class="wpcpm-administrator__note">%2$s</p></details>',
			esc_html__( 'Read the description', 'wpcredits-program-manager' ),
			esc_html( $text )
		);
	}

	/**
	 * Who joined an approved event, by name, folded: a list that may run to hundreds of names.
	 * Nothing when nobody has.
	 *
	 * @param string[] $names The names, in the order to draw them.
	 */
	private static function render_people( array $names ) {
		if ( array() === $names ) {
			return;
		}

		printf( '<details class="wpcpm-administrator__details"><summary>%s</summary><ul class="wpcpm-administrator__list">', esc_html__( 'Who joined', 'wpcredits-program-manager' ) );

		foreach ( $names as $name ) {
			printf( '<li>%s</li>', esc_html( (string) $name ) );
		}

		echo '</ul></details>';
	}

	/**
	 * Approve: one button, named for its event to a screen reader.
	 *
	 * @param int    $id    The event.
	 * @param string $title The event's title.
	 */
	private static function render_approve( $id, $title ) {
		self::open_form( self::ACTION_APPROVE, $id, __( 'Approving', 'wpcredits-program-manager' ) );
		printf(
			'<button type="submit" class="button button-primary">%1$s<span class="screen-reader-text"> %2$s</span></button>',
			esc_html__( 'Approve', 'wpcredits-program-manager' ),
			esc_html( $title )
		);
		echo '</form>';
	}

	/**
	 * The manager's own Join or Leave on an approved event, and the mark that they are on it, as the
	 * list the other three dashboards draw has them: a program manager joins an event as themselves,
	 * never on somebody's behalf, and comes back to this card. The call link is on the card already.
	 *
	 * @param int $id The event.
	 */
	private static function render_join( $id ) {
		$event = get_post( (int) $id );

		if ( class_exists( 'WPCPM_Sponsor_Event_List' ) && WPCPM_Sponsor_Event::is_event( $event ) ) {
			WPCPM_Sponsor_Event_List::render_actions( $event, wp_get_current_user(), WPCPM_Sponsor_Event_List::BACK_ADMIN );
		}
	}

	/**
	 * Return with a note: folded until chosen, as the sponsor posts' is, and a note required, and
	 * sent with the posts' own button, "Return it with this note", so the same decision reads the
	 * same on every card that sends a sponsor's own work back, a sponsor's Collaboration Agreement
	 * among them, which goes back in the words the institutions' agreements do. Its sentence says the
	 * sponsor can plan again only while it still offers events, as the mail the press sends says it.
	 * A refused return comes back with the note as it was typed, its fold open
	 * (`WPCPM_Typed_Text::kept_box()`).
	 *
	 * @param int    $id      The event.
	 * @param string $title   The event's title.
	 * @param bool   $offered Whether the sponsor still offers events.
	 */
	private static function render_return( $id, $title, $offered ) {
		$kept = WPCPM_Typed_Text::kept_box( WPCPM_Typed_Text::box_form( self::TYPED_RETURN, $id ), 'note' );

		self::open_fold(
			__( 'Return with a note', 'wpcredits-program-manager' ),
			$title,
			$offered
				? __( 'The event is canceled and goes back to the sponsor, and your note is emailed to the sponsor\'s members, who can plan again.', 'wpcredits-program-manager' )
				: __( 'The event is canceled, and your note is emailed to the sponsor\'s members. The sponsor no longer holds the tier that includes events.', 'wpcredits-program-manager' ),
			null !== $kept
		);
		self::open_form( self::ACTION_RETURN, $id, __( 'Returning', 'wpcredits-program-manager' ) );
		self::note_box( 'return', $id, __( 'What should change before it can be approved', 'wpcredits-program-manager' ), $kept );
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Return it with this note', 'wpcredits-program-manager' ) );
		echo '</form></details>';
	}

	/**
	 * Cancel with a note: folded until chosen, a note required, and a question before it posts, since
	 * a cancel cannot be taken back and everyone on the event is told. A refused cancel comes back
	 * with the note as it was typed, its fold open (`WPCPM_Typed_Text::kept_box()`).
	 *
	 * @param int    $id    The event.
	 * @param string $title The event's title.
	 */
	private static function render_cancel( $id, $title ) {
		$kept = WPCPM_Typed_Text::kept_box( WPCPM_Typed_Text::box_form( self::TYPED_CANCEL, $id ), 'note' );

		self::open_fold( __( 'Cancel with a note', 'wpcredits-program-manager' ), $title, __( 'The event is canceled for everyone: your note is emailed to the sponsor\'s members, and everyone who joined is told.', 'wpcredits-program-manager' ), null !== $kept );
		self::open_form( self::ACTION_CANCEL, $id, __( 'Canceling', 'wpcredits-program-manager' ) );
		self::note_box( 'cancel', $id, __( 'Why the event is canceled', 'wpcredits-program-manager' ), $kept );
		printf(
			'<button type="submit" class="button" data-wpcpm-confirm="%1$s">%2$s</button>',
			esc_attr__( 'Cancel this event for everyone? This cannot be undone.', 'wpcredits-program-manager' ),
			esc_html__( 'Cancel with this note', 'wpcredits-program-manager' )
		);
		echo '</form></details>';
	}

	/**
	 * Plan an event of the program's own: the card's third part, its heading, and the planner's form
	 * folded under Plan an event with a sentence saying what such an event is. The form's rows are the
	 * planner's (`WPCPM_Sponsor_Event_Planner::render_fields()`), on the viewing manager's clock: the
	 * day from the site's today, as a sponsor's plan starts it, and no last day, since no term binds
	 * the program. Its boxes are empty but for the suggested length, or hold what the manager typed
	 * when a refusal of their plan brought the page back, the title and the description exactly as
	 * typed (`WPCPM_Sponsor_Event_Planner::kept_for()`), the fold then drawn open. Nothing when the
	 * planner is not loaded, whose rows these are.
	 *
	 * The fold sits in the decide row every item's decisions sit in, and the form carries the
	 * planner's form classes beside the card's, as the Change form does: the rules that dress a
	 * decision's fold, its buttons and its box, and the planner's own rules for its rows, reach the
	 * plan part with nothing drawn for it alone.
	 *
	 * @param DateTimeZone $zone The viewing manager's clock.
	 */
	private static function render_program_plan( DateTimeZone $zone ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Event_Planner' ) ) {
			return;
		}

		$kept = WPCPM_Sponsor_Event_Planner::kept_for( '', 0 );

		self::subtitle( __( 'Plan an event of the program\'s', 'wpcredits-program-manager' ) );
		echo '<div class="wpcpm-request__decide wpcpm-sponsor-post__decide">';
		self::open_fold( __( 'Plan an event', 'wpcredits-program-manager' ), '', __( 'An event of the WordPress Credits Program\'s own, with no sponsor: it is approved as you plan it, and the program\'s students, mentors, Administrators and institution contacts can join it at once, as they join a sponsor\'s.', 'wpcredits-program-manager' ), array() !== $kept );
		self::open_form( self::ACTION_PROGRAM_PLAN, 0, __( 'Planning', 'wpcredits-program-manager' ), self::PROGRAM_FORM );
		// A kept typing is drawn over the empty form's values, which give its boxes their room.
		WPCPM_Sponsor_Event_Planner::render_fields(
			'wpcpm-sevent-program-new',
			array(
				'date'        => '',
				'time'        => '',
				'minutes'     => (string) WPCPM_Sponsor_Event_Planner::DEFAULT_MINUTES,
				'places'      => '',
				'title'       => '',
				'description' => '',
				'link'        => '',
			),
			$zone,
			1,
			WPCPM_Sponsor_Tiers::today(),
			'',
			$kept
		);
		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html__( 'Plan the event', 'wpcredits-program-manager' ) );
		echo '</form></details></div>';
	}

	/**
	 * Change the event, for an event of the program's own: the planner's form, folded, every row
	 * filled from the event on the viewing manager's clock, the one the handler reads it back on, or
	 * with what the manager typed when a refusal of this change brought the page back, the title and
	 * the description exactly as typed (`WPCPM_Sponsor_Event_Planner::kept_for()`), the fold then
	 * drawn open. The places cannot be set below the people on it, so the store's refusal is the
	 * browser's first. The day box starts on the site's today, or on the day the event is drawn on
	 * when that is earlier, so the form as drawn can always be saved, and has no last day. The store
	 * tells everyone on the event of a new time, length, title or call link.
	 *
	 * @param array        $row  A row of `data()`.
	 * @param DateTimeZone $zone The viewing manager's clock.
	 */
	private static function render_program_change( array $row, DateTimeZone $zone ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Event_Planner' ) ) {
			return;
		}

		$id    = (int) $row['id'];
		$start = ( new DateTimeImmutable( '@' . (int) $row['start'] ) )->setTimezone( $zone );
		$drawn = $start->format( 'Y-m-d' );
		$today = WPCPM_Sponsor_Tiers::today();
		$kept  = WPCPM_Sponsor_Event_Planner::kept_for( '', $id );

		self::open_fold( __( 'Change the event', 'wpcredits-program-manager' ), (string) $row['title'], __( 'Everyone who joined is told of a new time, length, title or call link, with the calendar file that brings theirs up to date.', 'wpcredits-program-manager' ), array() !== $kept );
		self::open_form( self::ACTION_PROGRAM_CHANGE, $id, __( 'Saving', 'wpcredits-program-manager' ), self::PROGRAM_FORM );
		// A kept typing is drawn over the event's own values, which give its boxes their room.
		WPCPM_Sponsor_Event_Planner::render_fields(
			'wpcpm-sevent-program-' . $id,
			array(
				'date'        => $drawn,
				'time'        => $start->format( 'H:i' ),
				'minutes'     => (string) (int) $row['minutes'],
				'places'      => (int) $row['places'] > 0 ? (string) (int) $row['places'] : '',
				'title'       => (string) $row['title'],
				'description' => (string) $row['description'],
				'link'        => (string) $row['link'],
			),
			$zone,
			max( 1, (int) $row['joined'] ),
			strcmp( $drawn, $today ) < 0 ? $drawn : $today,
			'',
			$kept
		);
		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html__( 'Save the changes', 'wpcredits-program-manager' ) );
		echo '</form></details>';
	}

	/**
	 * Cancel the event, for an event of the program's own: folded until chosen, with no note, since
	 * there is no sponsor to write one to, and a question before it posts, since a cancel cannot be
	 * taken back and everyone on the event is told.
	 *
	 * @param int    $id    The event.
	 * @param string $title The event's title.
	 */
	private static function render_program_cancel( $id, $title ) {
		self::open_fold( __( 'Cancel the event', 'wpcredits-program-manager' ), $title, __( 'The event is canceled for everyone, and everyone who joined is told, with the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ) );
		self::open_form( self::ACTION_PROGRAM_CANCEL, $id, __( 'Canceling', 'wpcredits-program-manager' ) );
		printf(
			'<button type="submit" class="button" data-wpcpm-confirm="%1$s">%2$s</button>',
			esc_attr__( 'Cancel this event for everyone? This cannot be undone.', 'wpcredits-program-manager' ),
			esc_html__( 'Cancel the event', 'wpcredits-program-manager' )
		);
		echo '</form></details>';
	}

	/**
	 * A decision folded until chosen: its summary a button named for its event to a screen reader,
	 * when it is about one, and a sentence saying what the press does. Drawn open when the form in
	 * it came back with what the manager typed, so the typing is in view.
	 *
	 * @param string $label     The summary's words.
	 * @param string $title     The event's title, or '' for a fold about no event, as the program's
	 *                          plan is.
	 * @param string $explained What the press does.
	 * @param bool   $open      Whether the form in it holds what a refusal kept.
	 */
	private static function open_fold( $label, $title, $explained, $open = false ) {
		printf( '<details class="wpcpm-sponsor-post__return"%1$s><summary class="button">%2$s', $open ? ' open' : '', esc_html( $label ) );

		if ( '' !== (string) $title ) {
			printf( '<span class="screen-reader-text"> %s</span>', esc_html( $title ) );
		}

		printf( '</summary><p class="wpcpm-administrator__note">%s</p>', esc_html( $explained ) );
	}

	/**
	 * A form's opening, the same for every press: posted to admin-post.php, guarded against a second
	 * press, the nonce keyed to the event, the event it names, and the way back to this card. The
	 * program's plan names no event, so its nonce is its action's alone and it posts no event.
	 *
	 * @param string $action  One of the ACTION_* presses.
	 * @param int    $id      The event, or 0 for the program's plan.
	 * @param string $busy    What the pressed button says while the request is in flight.
	 * @param string $classes The form's classes: the card's own, or `PROGRAM_FORM` for a form of the
	 *                        planner's rows.
	 */
	private static function open_form( $action, $id, $busy, $classes = 'wpcpm-sponsor-post__form' ) {
		printf(
			'<form class="%1$s" method="post" action="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_attr( $classes ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $busy )
		);
		wp_nonce_field( (int) $id > 0 ? $action . '_' . (int) $id : $action );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );

		if ( (int) $id > 0 ) {
			printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_EVENT ), (int) $id );
		}

		WPCPM_Return::field( WPCPM_Return::DASHBOARD, self::CARD );
	}

	/**
	 * The note's box, required, as long as the store keeps a note: drawn empty, or with what a
	 * refusal of its form kept, exactly as typed.
	 *
	 * @param string      $kind        `return` or `cancel`, for the box's id.
	 * @param int         $id          The event.
	 * @param string      $placeholder What the note is for.
	 * @param string|null $kept        What a refusal of this form kept, or null.
	 */
	private static function note_box( $kind, $id, $placeholder, $kept = null ) {
		printf(
			'<label class="screen-reader-text" for="wpcpm-sevent-%1$s-%2$d">%3$s</label><textarea id="wpcpm-sevent-%1$s-%2$d" name="%4$s" rows="2" maxlength="%5$d" required placeholder="%6$s">%7$s</textarea>',
			esc_attr( $kind ),
			(int) $id,
			esc_html( self::note_label() ),
			esc_attr( self::FIELD_NOTE ),
			(int) WPCPM_Sponsor_Event::MAX_NOTE,
			esc_attr( $placeholder ),
			// The parser drops one line feed right after the opening tag, so a kept note that
			// begins with a line break keeps it.
			null === $kept ? '' : "\n" . esc_textarea( $kept )
		);
	}

	/**
	 * The note box's name, as a screen reader hears it and a refusal of its note names it.
	 *
	 * @return string
	 */
	private static function note_label() {
		return __( 'A note for the sponsor', 'wpcredits-program-manager' );
	}

	/**
	 * A part's heading, as the other cards' parts have.
	 *
	 * @param string $text The heading.
	 */
	private static function subtitle( $text ) {
		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html( $text ) );
	}

	/**
	 * The sentence an empty part says, so it does not read as something that failed to load.
	 *
	 * @param string $text The sentence.
	 */
	private static function empty_line( $text ) {
		printf( '<p class="wpcpm-administrator__empty">%s</p>', esc_html( $text ) );
	}

	/**
	 * One of an event's facts.
	 *
	 * @param string $text The fact.
	 */
	private static function fact( $text ) {
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( $text ) );
	}

	/**
	 * An event's places, in words: open to everyone for no limit.
	 *
	 * @param int $places The places; 0 for no limit.
	 * @return string
	 */
	private static function places( $places ) {
		if ( $places <= 0 ) {
			return __( 'Open to everyone', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: how many people may join. */
			_n( '%s place', '%s places', $places, 'wpcredits-program-manager' ),
			number_format_i18n( $places )
		);
	}

	/**
	 * How many people joined an event, in words.
	 *
	 * @param int $joined How many.
	 * @return string
	 */
	private static function joined( $joined ) {
		if ( $joined <= 0 ) {
			return __( 'Nobody has joined yet', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: how many people have joined the event. */
			_n( '%s person has joined', '%s people have joined', $joined, 'wpcredits-program-manager' ),
			number_format_i18n( $joined )
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The presses
	 * --------------------------------------------------------------------
	 */

	/**
	 * Approve: the store opens the event to its audiences and stamps the decision; the sponsor's
	 * members are told, each with the event's calendar file. The store refuses a sponsor that no
	 * longer offers events (`sevent-no-tier`): the card draws no Approve for one, and a page drawn
	 * before the sponsor was paused, or its term ended, meets the refusal.
	 */
	public static function handle_approve() {
		$event    = self::begin_sponsor( self::ACTION_APPROVE );
		$approved = WPCPM_Sponsor_Event::approve( $event, get_current_user_id() );

		if ( is_wp_error( $approved ) ) {
			self::leave( self::status_of( $approved ) );
		}

		self::tell_sponsor( (int) $event->ID, self::MAIL_APPROVED, '', array() );
		self::leave( 'sevent-approved' );
	}

	/**
	 * Return with a note: the store cancels the event and keeps the note, which the Sponsor Dashboard
	 * shows above the planner's form while the sponsor holds the planner; the sponsor's members are
	 * mailed it, and told they can plan again only while the sponsor still offers events. For an event
	 * still waiting, which the store asks itself under its lock: one another manager approved since
	 * this page was drawn may have people on it, is refused as approved already (`not_planned`), and
	 * is called off with Cancel, which tells them. A note the text cleaner would take words from is
	 * refused before the store is asked, and so is one the store would not keep; either comes back
	 * as it was typed (`refuse_note()`).
	 */
	public static function handle_return() {
		$event = self::begin_sponsor( self::ACTION_RETURN );
		$note  = self::refuse_note( self::TYPED_RETURN, (int) $event->ID, 'sevent-return-note-loss' );

		// The store counts the note's length on what was typed too, so the two never part on it.
		$returned = WPCPM_Sponsor_Event::return_with_note( $event, get_current_user_id(), $note, WPCPM_Request::posted_raw( self::FIELD_NOTE ) );

		if ( is_wp_error( $returned ) ) {
			self::leave( self::status_of( $returned ) );
		}

		self::tell_sponsor( (int) $event->ID, self::MAIL_RETURNED, $note, array() );
		self::leave( 'sevent-returned' );
	}

	/**
	 * Cancel with a note: the store cancels the event, keeps the people on it on the record and tells
	 * them, the first `WPCPM_Sponsor_Event::TELL_IN_REQUEST` in this request and the rest by its
	 * drain, answering how many of each; then the sponsor's members are mailed the note and that
	 * number. A note the text cleaner would take words from is refused before the store is asked, as
	 * a return's is (`refuse_note()`).
	 */
	public static function handle_cancel() {
		$event = self::begin_sponsor( self::ACTION_CANCEL );
		$note  = self::refuse_note( self::TYPED_CANCEL, (int) $event->ID, 'sevent-cancel-note-loss' );

		$canceled = WPCPM_Sponsor_Event::cancel( $event, get_current_user_id() );

		if ( is_wp_error( $canceled ) ) {
			self::leave( self::status_of( $canceled ) );
		}

		self::tell_sponsor( (int) $event->ID, self::MAIL_CANCELED, $note, $canceled );
		self::leave( 'sevent-manager-canceled' );
	}

	/**
	 * Plan an event of the program's own: the nonce of the action first, the form naming no event;
	 * then the management capability and the switch; then the planner's fields, read on the manager's
	 * clock through the planner's own readers; then the store's plan, which asks the switch and the
	 * start again under the program's lock and approves the event as it writes it. Nobody is mailed:
	 * the event is listed for its audiences at once, and each person who joins it is mailed then.
	 * Once the form is read, every outcome leaves through `leave_typed()`, so a refusal the manager
	 * puts right by pressing again brings the form back with what was typed. A title or a
	 * description the text cleaner would take words from is refused before the fields are read for
	 * the store, so nothing is looked up or written, and the form comes back exactly as typed
	 * (`WPCPM_Sponsor_Event_Planner::loss_check()`).
	 */
	public static function handle_program_plan() {
		check_admin_referer( self::ACTION_PROGRAM_PLAN );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		if ( ! self::shown() ) {
			self::leave( 'sevent-off' );
		}

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Sponsor_Event_Planner::forget_typed( '', 0 );

		$posted = WPCPM_Sponsor_Event_Planner::posted_fields();
		$asked  = WPCPM_Sponsor_Event_Planner::loss_check( '', 0, $posted );

		if ( '' !== $asked['lost'] ) {
			self::leave_typed( 'sevent-program-plan-' . $asked['lost'] . '-loss', 0, $posted );
		}

		$read = WPCPM_Sponsor_Event::fields_from( $posted, WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() ) );

		if ( array() !== $read['errors'] ) {
			self::leave_typed( 'sevent-program-fields', 0, $posted );
		}

		$planned = WPCPM_Sponsor_Event::plan_program( $read['fields'], get_current_user_id() );

		if ( is_wp_error( $planned ) ) {
			self::leave_typed( self::program_status_of( $planned ), 0, $posted );
		}

		self::leave_typed( 'sevent-program-planned', 0, $posted );
	}

	/**
	 * Change an event of the program's own: the event the form names, its fields read on the
	 * manager's clock through the planner's readers, then the store's change, which keeps it approved
	 * and tells everyone on it of a new time, length, title or call link. There are no members to
	 * mail and no program contact to tell: the manager who pressed is the program. A change that
	 * alters nothing writes nothing and says so. Once the form is read, every outcome leaves through
	 * `leave_typed()`, as the plan's does. A title or a description the text cleaner would take words
	 * from is refused first, as the plan's is, and a box posted back as it was drawn is not asked:
	 * the store keeps the text the event holds there (`unedited`).
	 */
	public static function handle_program_change() {
		$event = self::begin_program( self::ACTION_PROGRAM_CHANGE );

		// What an earlier refusal of this event's form kept is stale once the form is posted again.
		WPCPM_Sponsor_Event_Planner::forget_typed( '', (int) $event->ID );

		$posted = WPCPM_Sponsor_Event_Planner::posted_fields();
		$asked  = WPCPM_Sponsor_Event_Planner::loss_check( '', (int) $event->ID, $posted );

		if ( '' !== $asked['lost'] ) {
			self::leave_typed( 'sevent-program-change-' . $asked['lost'] . '-loss', (int) $event->ID, $posted );
		}

		$read = WPCPM_Sponsor_Event::fields_from( $posted, WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() ) );

		if ( array() !== $read['errors'] ) {
			self::leave_typed( 'sevent-program-fields', (int) $event->ID, $posted );
		}

		$changed = WPCPM_Sponsor_Event::change( $event, array_merge( $read['fields'], array( 'unedited' => $asked['unedited'] ) ), get_current_user_id() );

		if ( is_wp_error( $changed ) ) {
			self::leave_typed( self::program_status_of( $changed ), (int) $event->ID, $posted );
		}

		self::leave_typed( empty( $changed['changed'] ) ? 'sevent-program-unchanged' : 'sevent-program-changed', (int) $event->ID, $posted );
	}

	/**
	 * Cancel an event of the program's own, with no note: the store cancels it and tells everyone on
	 * it, the first `WPCPM_Sponsor_Event::TELL_IN_REQUEST` in this request and the rest by its drain.
	 * Nobody else is mailed, as for a change.
	 */
	public static function handle_program_cancel() {
		$event    = self::begin_program( self::ACTION_PROGRAM_CANCEL );
		$canceled = WPCPM_Sponsor_Event::cancel( $event, get_current_user_id() );

		if ( is_wp_error( $canceled ) ) {
			self::leave( self::program_status_of( $canceled ) );
		}

		self::leave( 'sevent-manager-canceled' );
	}

	/**
	 * What every press does first, in this order: the nonce keyed to the event the form names, so a
	 * request without it learns nothing about which post IDs are events; the management capability;
	 * the launch switch; an event.
	 *
	 * @param string $action The press's action, which keys the nonce.
	 * @return WP_Post The event; leaves on any refusal.
	 */
	private static function begin( $action ) {
		$event_id = WPCPM_Request::posted_id( self::FIELD_EVENT );

		check_admin_referer( $action . '_' . $event_id );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		// While the switch is off nothing here is drawn, so a press comes from a page drawn before it
		// went off, or from a request made by hand; either way nothing is decided, and no sponsor is
		// mailed about a benefit the program has not announced.
		if ( ! self::shown() ) {
			self::leave( 'sevent-off' );
		}

		$event = $event_id > 0 ? get_post( $event_id ) : null;

		if ( ! WPCPM_Sponsor_Event::is_event( $event ) ) {
			self::leave( 'sevent-gone' );
		}

		return $event;
	}

	/**
	 * What the three decisions on a sponsor's event ask first, as every press does (`begin()`), and
	 * then that the event is a sponsor's: the card draws Approve, Return with a note and Cancel with a
	 * note on a sponsor's events alone, and an event of the program's own is changed and canceled by
	 * its own presses (`begin_program()`). A request made by hand for one would otherwise meet the
	 * store's answers for a sponsor's event, an approval refused as done already, which points at a
	 * control the program's own event does not carry, and a cancel that takes a note nobody reads.
	 *
	 * @param string $action The press's action, which keys the nonce.
	 * @return WP_Post The event; leaves on any refusal.
	 */
	private static function begin_sponsor( $action ) {
		$event = self::begin( $action );

		if ( WPCPM_Sponsor_Event::is_program( $event ) ) {
			self::leave( 'sevent-program-own' );
		}

		return $event;
	}

	/**
	 * What the program's Change and Cancel ask first, as every press does (`begin()`), and then that
	 * the event is one of the program's own: the card draws the two on those alone, and a sponsor's
	 * event is decided with a note, and changed on the Sponsor Dashboard, where its sponsor's members
	 * and its program contact are told.
	 *
	 * @param string $action The press's action, which keys the nonce.
	 * @return WP_Post The event; leaves on any refusal.
	 */
	private static function begin_program( $action ) {
		$event = self::begin( $action );

		if ( ! WPCPM_Sponsor_Event::is_program( $event ) ) {
			self::leave( 'sevent-program-not-its' );
		}

		return $event;
	}

	/**
	 * The note a return or a cancel carries: the lines typed, cleaned. Never cut: one longer than the
	 * store keeps is refused (`note_refusal()`), so the mail says the whole of what the manager wrote.
	 *
	 * @return string '' when none was written.
	 */
	private static function note() {
		return WPCPM_Request::posted_lines( self::FIELD_NOTE );
	}

	/**
	 * Why a return or a cancel cannot carry its note, or '' when it can: none was written, or it is
	 * longer than the store keeps, counted on what was typed, as its box counted it
	 * (`WPCPM_Typed_Text::box_length()`) and as the store counts it
	 * (`WPCPM_Sponsor_Event::return_with_note()`). The cleaner writes a "<" that opens no tag, and the
	 * quote marks and the ampersand after it, as entities, and a reference typed out after it in
	 * core's own form, `&#9;` as `&#009;`, so a note that fits its box is stored longer than it was
	 * typed, and is kept whole; one longer than the box takes is refused rather than cut, which would
	 * drop its last words or end inside an entity.
	 *
	 * @param string $note  The note, as `note()` reads it.
	 * @param string $typed What was posted for it, unslashed and not cleaned
	 *                      (`WPCPM_Request::posted_raw()`).
	 * @return string A key of `messages()`, or ''.
	 */
	private static function note_refusal( $note, $typed ) {
		if ( '' === $note ) {
			return 'sevent-note-empty';
		}

		return WPCPM_Typed_Text::box_length( $typed ) > WPCPM_Sponsor_Event::MAX_NOTE ? 'sevent-note-long' : '';
	}

	/**
	 * The note a return or a cancel carries, once it passes the two refusals a note meets before the
	 * store is asked, in this order: the text cleaner would take words from it, which a mail would
	 * then give the sponsor's members without a word said ("Kids <12 free, adults >18 pay" stored as
	 * `Kids 18 pay`); then none was written, or one longer than the store keeps (`note_refusal()`).
	 * Asked of what was typed (`WPCPM_Request::posted_raw()`), so a refused press writes, mails and
	 * sends nothing. A note refused for its words or its length comes back in its box as it was
	 * typed, for the manager who pressed and on this event's form alone
	 * (`WPCPM_Typed_Text::keep_box()`); what an earlier refusal of the form kept is dropped first.
	 *
	 * @param string $form `TYPED_RETURN` or `TYPED_CANCEL`.
	 * @param int    $id   The event.
	 * @param string $loss The status a note the cleaner would take words from leaves with.
	 * @return string The note, as `note()` reads it; leaves on any refusal.
	 */
	private static function refuse_note( $form, $id, $loss ) {
		$form = WPCPM_Typed_Text::box_form( $form, $id );

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		$typed = WPCPM_Request::posted_raw( self::FIELD_NOTE );

		if ( WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, WPCPM_Sponsor_Event::MAX_NOTE );
			self::leave( $loss );
		}

		$note    = self::note();
		$refusal = self::note_refusal( $note, $typed );

		if ( 'sevent-note-long' === $refusal ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, WPCPM_Sponsor_Event::MAX_NOTE );
		}

		if ( '' !== $refusal ) {
			self::leave( $refusal );
		}

		return $note;
	}

	/**
	 * The outcome a refusal of the store leaves with. A refusal no form could have caused, or a write
	 * that failed, is said as a failure to try again.
	 *
	 * @param WP_Error $refusal The store's refusal.
	 * @return string A key of `messages()`.
	 */
	private static function status_of( WP_Error $refusal ) {
		$statuses = array(
			'not_found'   => 'sevent-gone',
			'busy'        => 'sevent-busy',
			'cancelled'   => 'sevent-was-canceled',
			'not_planned' => 'sevent-not-waiting',
			'no_tier'     => 'sevent-no-tier',
			'past'        => 'sevent-past',
			'note'        => 'sevent-note-empty',
			'note_long'   => 'sevent-note-long',
		);
		$code     = (string) $refusal->get_error_code();

		return isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'sevent-failed';
	}

	/**
	 * The outcome a refusal of the store leaves the program's own presses with, in the words that
	 * name the program where a sponsor's name the sponsor. A refusal no form could have caused, or a
	 * write that failed, is said as a failure to try again.
	 *
	 * @param WP_Error $refusal The store's refusal.
	 * @return string A key of `messages()`.
	 */
	private static function program_status_of( WP_Error $refusal ) {
		$statuses = array(
			'switch_off' => 'sevent-off',
			'not_found'  => 'sevent-gone',
			'busy'       => 'sevent-program-busy',
			'cancelled'  => 'sevent-was-canceled',
			'past'       => 'sevent-past',
			'in_past'    => 'sevent-program-in-past',
			'shrink'     => 'sevent-program-shrink',
			'fields'     => 'sevent-program-fields',
		);
		$code     = (string) $refusal->get_error_code();

		return isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'sevent-failed';
	}

	/**
	 * Flash on the Administrator Dashboard's channel and go back to the card, the way every decision
	 * there does: the place posted is a name `WPCPM_Return` maps, never an address followed.
	 *
	 * @param string $status A key of `messages()`.
	 */
	private static function leave( $status ) {
		WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );
		wp_safe_redirect( WPCPM_Return::url( home_url( '/' ) ) );
		exit;
	}

	/**
	 * Leave from the program's own plan or change once its form was read: what it posted is kept for
	 * the manager who pressed when the outcome is one they put right by pressing again
	 * (`KEEPS_TYPED`), with no sponsor, the program's own, and the event a change was for; nothing is
	 * kept for any other outcome, a success among them. Through the planner's own keeper, on its one
	 * channel, so the form comes back with it once (`WPCPM_Sponsor_Event_Planner::keep_typed()`). A
	 * press stopped before its form is read keeps nothing.
	 *
	 * @param string $status   A key of `messages()`.
	 * @param int    $event_id The event a change was for, or 0 for a plan.
	 * @param array  $posted   What the form posted (`WPCPM_Sponsor_Event_Planner::posted_fields()`).
	 */
	private static function leave_typed( $status, $event_id, array $posted ) {
		WPCPM_Sponsor_Event_Planner::keep_typed( $status, self::KEEPS_TYPED, $posted, '', $event_id );
		self::leave( $status );
	}

	/*
	 * --------------------------------------------------------------------
	 * The mails
	 * --------------------------------------------------------------------
	 */

	/**
	 * Tell the event's sponsor what the program decided: every live member, each in their own
	 * language and on their own clock. The event is read again after the store's write, so the mail
	 * says what it now is, and whether the sponsor still offers events is asked once, of the store's
	 * own test, for every member's mail alike. Nobody on the event is named, a count at most.
	 *
	 * @param int    $event_id The event.
	 * @param string $what     MAIL_APPROVED, MAIL_RETURNED or MAIL_CANCELED.
	 * @param string $note     The manager's note, or ''.
	 * @param array  $told     The store's answer to a cancel: `told`, how many people on the event it
	 *                         told in this request, and `pending`, how many it left to its drain.
	 * @return int How many were mailed.
	 */
	private static function tell_sponsor( $event_id, $what, $note, array $told ) {
		$event = get_post( (int) $event_id );

		if ( ! WPCPM_Sponsor_Event::is_event( $event ) ) {
			return 0;
		}

		$record = WPCPM_Sponsor_Event::sponsor_of( $event );
		$said   = array(
			'what'    => $what,
			'note'    => $note,
			'told'    => isset( $told['told'] ) ? (int) $told['told'] : 0,
			'pending' => isset( $told['pending'] ) ? (int) $told['pending'] : 0,
			// Every person the event's rows hold, a gone account included, read once for every
			// member's mail: what the store's count of sends is measured against.
			'people'  => count( WPCPM_Sponsor_Event::joiners( $event ) ),
			'manager' => wp_get_current_user(),
			'page'    => class_exists( 'WPCPM_Sponsors_Dashboard' ) ? (string) WPCPM_Sponsors_Dashboard::page_url() : '',
			'offered' => WPCPM_Sponsor_Event::offers_events( $record ),
		);
		$id     = self::member_id( $what, $said['offered'] );
		$sent   = 0;

		foreach ( WPCPM_Sponsor_Members::members_of( $record ) as $member ) {
			$sent += WPCPM_Mail::send(
				$member,
				$id,
				static function ( WP_User $recipient ) use ( $event, $said, $id ) {
					return self::member_mail( $event, $said, $id, $recipient );
				}
			) ? 1 : 0;
		}

		return $sent;
	}

	/**
	 * The template of the mail that tells a sponsor what the program decided: the approval, the return,
	 * which says the company can plan again while the sponsor still offers events and that its tier no
	 * longer includes them when it does not, or the cancel with a note.
	 *
	 * @param string $what    MAIL_APPROVED, MAIL_RETURNED or MAIL_CANCELED.
	 * @param bool   $offered Whether the sponsor still offers events.
	 * @return string The template's id in the catalog.
	 */
	private static function member_id( $what, $offered ) {
		if ( self::MAIL_APPROVED === $what ) {
			return 'event-approved';
		}

		if ( self::MAIL_RETURNED === $what ) {
			return $offered ? 'event-returned' : 'event-returned-no-events';
		}

		return 'event-canceled-with-note';
	}

	/**
	 * One mail to one of the sponsor's members about the program's decision, built in their language
	 * from its template (`member_id()`): what happened, the note when there is one, the event on their
	 * own clock with the zone named, and what comes next: after a return, planning again, while the
	 * sponsor still offers events, else that the company no longer holds the tier that includes them,
	 * with no way to a card it has not got. A note comes with the deciding Administrator to reply to.
	 * An approval carries the event's calendar file; a cancel carries the file that takes it out again,
	 * for an event the program approved, whose approval stamp a cancel leaves in place. Each file is the
	 * member's own, naming them. The title and the note are the ones typed, read back by the registry.
	 *
	 * @param WP_Post $event     The event, read after the store's write.
	 * @param array   $said      `what` (MAIL_APPROVED, MAIL_RETURNED or MAIL_CANCELED), `note` (the
	 *                           Administrator's, or ''), `told` (how many people on the event the store
	 *                           told of a cancel in the press), `pending` (how many it left to its
	 *                           drain), `people` (how many people the event holds), `manager` (who
	 *                           decided), `page` (the Sponsor Dashboard's address, or '' when it has
	 *                           none) and `offered` (whether the sponsor still offers events).
	 * @param string  $id        The template's id.
	 * @param WP_User $recipient The member.
	 * @return array `subject`, `body`, `headers`, `attachments` and `cleanup`.
	 */
	private static function member_mail( WP_Post $event, array $said, $id, WP_User $recipient ) {
		$what   = $said['what'];
		$facts  = WPCPM_Sponsor_Event::facts( $event );
		$values = WPCPM_Sponsor_Event::mail_values( $facts, WPCPM_Sponsor_Event::link_of( $event ), WPCPM_Mentor_Availability::viewer_timezone( (int) $recipient->ID ), '' );
		$files  = array();

		$values['note']        = (string) $said['note'];
		$values['events_card'] = '' === $said['page'] ? '' : $said['page'] . '#wpcpm-sponsor-events';

		if ( self::MAIL_APPROVED === $what ) {
			$files = WPCPM_Sponsor_Event::calendar_file( $event, '', $recipient );

			$values['calendar_note'] = array() === $files ? '' : __( 'The calendar file attached adds it to your calendar.', 'wpcredits-program-manager' );
		} elseif ( self::MAIL_CANCELED === $what ) {
			$files = (int) $facts['decision']['at'] > 0 ? WPCPM_Sponsor_Event::calendar_file( $event, '', $recipient ) : array();

			$values['calendar_note'] = array() === $files ? '' : __( 'The calendar file attached takes it off your calendar.', 'wpcredits-program-manager' );
			$values['told_note']     = self::told_note( $said );
		}

		return WPCPM_Mail_Templates::mail(
			$id,
			$values,
			array(
				// A note is the program's word, and the sponsor answers it to the Administrator who wrote it.
				'headers'     => self::MAIL_APPROVED === $what ? array() : WPCPM_Mail::reply_to( $said['manager'] ),
				'attachments' => $files,
				'cleanup'     => $files,
			)
		);
	}

	/**
	 * How many of the people who joined a canceled event are told, as the cancel's mail to the sponsor
	 * says it: only when every person on the event was told, or is in the drain's hands, and as a
	 * number, since who is for the program alone. The store counts the sends the mail layer handed
	 * off, so a send that failed, or an account that is gone and can be told nothing, would make the
	 * sentence untrue of the people who joined; then nothing is said, as the planner's outcome says
	 * nothing, and the mail log keeps the sends that failed. The people the drain still has are counted
	 * as told, the drain being on the clock, and the sentence says they are being told.
	 *
	 * @param array $said `told`, `pending` and `people`, as `tell_sponsor()` read them.
	 * @return string The sentence, or '' to say nothing.
	 */
	private static function told_note( array $said ) {
		$told    = (int) $said['told'];
		$pending = (int) $said['pending'];
		$reached = $told + $pending;

		if ( $reached > 0 && $reached === (int) $said['people'] && $pending > 0 ) {
			return sprintf(
				/* translators: %s: how many people joined the event, all of them being told it is canceled. */
				_n( 'The %s person who joined is being told.', 'The %s people who joined are being told.', $reached, 'wpcredits-program-manager' ),
				number_format_i18n( $reached )
			);
		}

		if ( $reached > 0 && $reached === (int) $said['people'] ) {
			return sprintf(
				/* translators: %s: how many of the people on the event were told it is canceled. */
				_n( 'The %s person who joined it has been told.', 'The %s people who joined it have been told.', $told, 'wpcredits-program-manager' ),
				number_format_i18n( $told )
			);
		}

		return '';
	}
}
