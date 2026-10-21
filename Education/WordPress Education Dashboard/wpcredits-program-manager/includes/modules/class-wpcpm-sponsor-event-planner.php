<?php
/**
 * The Sponsor Dashboard's event planner: a sponsor's co-hosted event, planned, changed and
 * canceled from the Events card.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the Events card draws under the events a tier includes, and the three presses behind it
 * (the design's section 18).
 *
 * The card keeps its list of what the tier includes; this class draws the rest, in order: the
 * sponsor's event still to take place, with its state, how many people joined, a form to change it
 * and a button to cancel it; the form that plans one when the allowance lets the sponsor plan,
 * under what the term allows while the program has granted it more events than the tier's one, and
 * under the note a program manager returned the last plan with, if it was returned; in the form's
 * place, one sentence while the allowance refuses for a reason the sponsor only has to wait out (an
 * event still to take place, the term's events held); and the events that took place, read only.
 *
 * The planner is a tier's benefit, held only while the sponsor is Approved and its tier includes the
 * co-hosted event (`offered()`). A sponsor that no longer holds it, paused or whose tier lost events,
 * gets neither the form nor a sentence, reads an approved event of its own as no longer offered,
 * since no list offers it, and keeps Cancel alone: a plan or a change is neither drawn nor taken,
 * since the planner it would be made with is not the sponsor's any more, and what either posts is
 * not even read.
 *
 * The store (`WPCPM_Sponsor_Event`) answers every question and makes every write: the allowance,
 * the fields, the states, the lock and the audit row. This class reads the request, draws the card
 * and tells the program contact. A day and a time are entered and read on the viewer's own clock,
 * named beside them, as the group sessions do; the store keeps the moment in UTC with the clock's
 * name.
 *
 * Nothing is drawn and no press acts while the launch switch is off. Each press checks the nonce
 * keyed to the sponsor first, then claims `ACT_PLAN_EVENT` through the roster (which meters a
 * refusal), then the switch and the sponsor's row. A cancel then counts itself against the
 * account's `PER_HOUR` presses (`spend_press()`). A plan and a change ask whether the sponsor holds
 * the planner, then whether the text cleaner would take words from the title or the description,
 * and only then count themselves, so a press refused for either costs the account nothing, as the
 * interests card's refusal of lost words spends none of its own count; then they hand the store
 * what was posted, whose call link the store looks up once a press; the store asks the allowance
 * again under the sponsor's lock. A change and a cancel act on an event only when the
 * sponsor policy lets the person plan for the event's own sponsor, so a member never reaches
 * another sponsor's event by naming it, and nobody reaches an event of the program's own, which
 * the Administrator Dashboard's card changes and cancels. Every outcome leaves through the
 * dashboard's `leave()`, on the Events card. A plan or a change whose title or description the text
 * cleaner would take words from is refused before the form is read for the store, the box named in
 * the words of the form's button, and the form comes back once with exactly what was typed, for the
 * person who pressed and in that form alone (`refuse_loss()`); a Change form's box posted back as it
 * was drawn is not asked, and the event keeps what it holds there. A plan or a change refused for
 * another reason the person puts right by pressing again keeps what was typed for that person, and
 * its form comes back with it once (`keep_typed()`, `kept_for()`), the title and the description
 * exactly as typed, a form in a fold drawing the fold open; every other outcome keeps nothing. The
 * Administrator Dashboard's card refuses and brings back the program's own plan and change the same
 * way, through the same methods (`WPCPM_Sponsor_Event_Queue`).
 *
 * The program contact is mailed on every plan and cancel, and on every change that alters
 * something, with the call link, which a program manager approves with the rest, and with how many
 * people joined, never who. The mail reads the event on the recipient's own clock, and says so when
 * a program manager acted on the sponsor's behalf through the switcher.
 *
 * A change or a cancel of an event the program approved reaches three sets of people, in this
 * order: the store tells the people on the event of a cancel, and of a change of the time, the
 * length, the title or the link, the first `WPCPM_Sponsor_Event::TELL_IN_REQUEST` in the press and
 * the rest by its drain, and answers how many of each; the sponsor's live members, the member who
 * pressed included, are sent the calendar file that updates or removes the event their approval
 * mail put in their calendars; then the program contact. A change of the description or the places
 * alone reaches the members and the contact, and one that alters nothing reaches nobody and says
 * "Nothing changed." An event still waiting was in nobody's calendar, and its change or cancel
 * tells the program contact alone. The outcome adds that everyone who joined has been told only
 * when the store told every one of them in the press, and that everyone who joined is being told
 * when its drain has the rest.
 */
final class WPCPM_Sponsor_Event_Planner {

	/** Plan an event. Nonce keyed to the sponsor. */
	const ACTION_PLAN = 'wpcpm_sevent_plan';

	/** Change the sponsor's event. Nonce keyed to the sponsor. */
	const ACTION_CHANGE = 'wpcpm_sevent_change';

	/** Cancel the sponsor's event. Nonce keyed to the sponsor. */
	const ACTION_CANCEL = 'wpcpm_sevent_cancel';

	/** The anchor and flash key of the card the planner is drawn in: the tier card's Events card. */
	const CARD = 'events';

	// The fields the forms post. The sponsor's field is the one every card on the page posts.
	const FIELD_SPONSOR     = 'wpcpm_sponsor';
	const FIELD_EVENT       = 'wpcpm_sevent_id';
	const FIELD_DATE        = 'wpcpm_sevent_date';
	const FIELD_TIME        = 'wpcpm_sevent_time';
	const FIELD_MINUTES     = 'wpcpm_sevent_minutes';
	const FIELD_PLACES      = 'wpcpm_sevent_places';
	const FIELD_TITLE       = 'wpcpm_sevent_title';
	const FIELD_DESCRIPTION = 'wpcpm_sevent_description';
	const FIELD_LINK        = 'wpcpm_sevent_link';

	/** The ceiling's key: one count per account across the three presses, as `WPCPM_Ceiling::key()` joins it. */
	const CEILING = 'sponsor-event-press';

	/**
	 * How many presses one account may make an hour, plans, changes and cancels together. A change
	 * of an approved event's time, length, title or link mails up to fifty people in the press and
	 * looks its link up, and each press is audited but not refused: ten an hour is more than planning
	 * an event takes, and bounds what one account can send by pressing on purpose.
	 */
	const PER_HOUR = 10;

	/** The length a new event's form suggests, in minutes. */
	const DEFAULT_MINUTES = 60;

	/** The step the length box moves by, in minutes: from 15, every five-minute mark is a length. */
	const MINUTES_STEP = 5;

	/** How much of a detail the dashboard's flash keeps, in characters (`WPCPM_Sponsors_Dashboard::leave()`). */
	const DETAIL_MAX = 300;

	/**
	 * The flash channel a refused plan or change keeps what was typed on, for the person who pressed
	 * (`keep_typed()`): the planner's two forms on the Sponsor Dashboard and the program's own two on
	 * the Administrator Dashboard share it, since what is kept names the form it came from.
	 */
	const TYPED = 'sponsor_event_typed';

	/**
	 * The longest a value of the day, the start time, the length or the places may be and still be
	 * kept, in characters: each box holds a few, a day ten and a time five, and nothing an honest
	 * person types into one comes near this.
	 */
	const TYPED_SHORT = 20;

	/**
	 * The outcomes a person puts right by pressing again whose form comes back with what was typed,
	 * as the request readers read it, on the planner's channel, with the title and the description
	 * exactly as typed kept beside it under the form's own name (`keep_typed()`): the form's own
	 * refusal, and the store's refusals of a start outside the term or one that has passed, of fewer
	 * places than the people on the event, of a lock held and of a write that failed.
	 *
	 * A refusal for words the text cleaner would take (`sevent-plan-title-loss` and the three like
	 * it) is not on the list, though its form comes back too: it keeps exactly what was typed under
	 * the form's own name before it leaves (`loss_check()`), and the form draws that instead.
	 *
	 * Every other outcome keeps nothing: a success; a wait, which the card says in the form's place;
	 * a sponsor that no longer holds the planner, or the switch, which draw no form to come back to;
	 * an event canceled or over, whose form is gone; a refusal of who is pressing; and the ceiling
	 * (`sevent-ceiling`), which no press within the hour puts right.
	 */
	const KEEPS_TYPED = array( 'sevent-fields', 'sevent-past-term', 'sevent-before-term', 'sevent-in-past', 'sevent-busy', 'sevent-shrink', 'sevent-failed' );

	/**
	 * The form's two boxes of typed text, in the form's order, each with the field it posts and how the
	 * text cleaner reads it (`WPCPM_Typed_Text::cleaner_loses()`): the title one line, the description
	 * a text area.
	 */
	const TYPED_BOXES = array(
		'title'       => array( self::FIELD_TITLE, 'line' ),
		'description' => array( self::FIELD_DESCRIPTION, 'lines' ),
	);

	// What the program contact is told happened.
	const MAIL_PLANNED  = 'planned';
	const MAIL_CHANGED  = 'changed';
	const MAIL_CANCELED = 'canceled';

	/**
	 * The three presses.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_PLAN, array( __CLASS__, 'handle_plan' ) );
		add_action( 'admin_post_' . self::ACTION_CHANGE, array( __CLASS__, 'handle_change' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'handle_cancel' ) );
	}

	/**
	 * The planner's outcomes. `refused` and `tier-off` are the tier card's, word for word, so the
	 * dashboard's merge reads the same whichever card it takes them from, and a change that alters
	 * nothing says so in the profile card's words. A plan says it waits for the program's OK and
	 * promises no confirmation, which a program manager may give or not. A change and a cancel say
	 * what happened and claim no mail of their own: the program contact's may reach nobody, and the
	 * sentence about everyone who joined is added as the outcome's detail, only when the store told
	 * every one of them or has the rest in hand (`told()`). The waits are the card's own sentences in
	 * the form's place (`wait_status()`), and the store refuses in the same words: while a term runs
	 * the event still to take place is the term's, or, for a term the program granted more events
	 * than the tier's one, the next follows it while the term can hold another, and the term's events
	 * are planned already once it is the last the term holds; with no term the next event follows it;
	 * and once the term's event has taken place, it has, or, for a term granted more, they have. A
	 * start outside the term is said in the store's words too, which name the day on the other side
	 * of the edge and the site's clock (`WPCPM_Sponsor_Event::edge_of()`): the press hands the two to
	 * the dashboard's flash, which fills them in (`refused()`). A title or a description the text
	 * cleaner would take words from is refused in a sentence that names the box and says nothing was
	 * planned, or nothing saved, as the form's button reads: Plan the event, or Save the changes
	 * (`WPCPM_Typed_Text::loss_message()`).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'sevent-sent'                    => array( 'success', __( 'Your event is planned. It waits for the program\'s OK, and nobody else sees it until then.', 'wpcredits-program-manager' ) ),
			'sevent-changed'                 => array( 'success', __( 'Your event is changed.', 'wpcredits-program-manager' ) ),
			'sevent-unchanged'               => array( 'info', __( 'Nothing changed.', 'wpcredits-program-manager' ) ),
			'sevent-canceled'                => array( 'success', __( 'Your event is canceled.', 'wpcredits-program-manager' ) ),
			'sevent-fields'                  => array( 'error', __( 'Some of the event\'s details are missing or not valid.', 'wpcredits-program-manager' ) ),
			'sevent-no-tier'                 => array( 'error', __( 'Your sponsorship does not include a co-hosted event right now.', 'wpcredits-program-manager' ) ),
			'sevent-one-open'                => array( 'error', __( 'Your next event can be planned once this one has taken place.', 'wpcredits-program-manager' ) ),
			'sevent-one-open-term'           => array( 'error', __( 'Your event for this term is planned already.', 'wpcredits-program-manager' ) ),
			'sevent-one-open-more'           => array( 'error', __( 'Your events for this term are planned already.', 'wpcredits-program-manager' ) ),
			'sevent-term-used'               => array( 'error', __( 'Your event for this term has taken place already.', 'wpcredits-program-manager' ) ),
			'sevent-term-used-more'          => array( 'error', __( 'Your events for this term have taken place already.', 'wpcredits-program-manager' ) ),
			/* translators: 1: the last day of the sponsor's term, in the site's date format, 2: the site's time zone, e.g. Europe / Warsaw. */
			'sevent-past-term'               => array( 'error', __( 'Choose a start on or before %1$s, the last day of your sponsorship term on the site\'s clock (%2$s).', 'wpcredits-program-manager' ) ),
			/* translators: 1: the first day of the sponsor's current term, in the site's date format, 2: the site's time zone, e.g. Europe / Warsaw. */
			'sevent-before-term'             => array( 'error', __( 'Choose a start on or after %1$s, the first day of your current sponsorship term on the site\'s clock (%2$s).', 'wpcredits-program-manager' ) ),
			'sevent-in-past'                 => array( 'error', __( 'That day and time have passed. Choose a time still to come.', 'wpcredits-program-manager' ) ),
			'sevent-busy'                    => array( 'error', __( 'Another change to your company\'s events was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'sevent-was-canceled'            => array( 'error', __( 'That event was canceled already.', 'wpcredits-program-manager' ) ),
			'sevent-past'                    => array( 'error', __( 'That event has taken place, so it can no longer be changed or canceled.', 'wpcredits-program-manager' ) ),
			'sevent-shrink'                  => array( 'error', __( 'More people have joined than that many places would hold.', 'wpcredits-program-manager' ) ),
			'sevent-failed'                  => array( 'error', __( 'Your event could not be saved right now. Try again later.', 'wpcredits-program-manager' ) ),
			'sevent-ceiling'                 => array( 'error', __( 'Ten an hour is the limit. Try again in an hour.', 'wpcredits-program-manager' ) ),
			'sevent-plan-title-loss'         => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Title', 'wpcredits-program-manager' ), 'plan' ) ),
			'sevent-plan-description-loss'   => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Description', 'wpcredits-program-manager' ), 'plan' ) ),
			'sevent-change-title-loss'       => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Title', 'wpcredits-program-manager' ) ) ),
			'sevent-change-description-loss' => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Description', 'wpcredits-program-manager' ) ) ),
			'tier-off'                       => array( 'error', __( 'That is not available for your company right now.', 'wpcredits-program-manager' ) ),
			'refused'                        => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Whether a sponsor holds the planner today: the launch switch is on, its tier includes the
	 * co-hosted event, and the allowance does not refuse the sponsor itself (`no_tier`: not Approved,
	 * or a key the store does not hold). The one question the Events card asks before it lists the
	 * tier's events and says what the planner is for, which it reads as `holds()` of the one allowance
	 * it asks for that and for its action; the planner asks it of its own answer before it draws
	 * Change, and a plan or a change asks it before it is taken.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $tier   The effective tier.
	 * @return bool
	 */
	public static function offered( $record, array $row, $tier ) {
		return self::holds( self::allowance( $record, $row, $tier ) );
	}

	/**
	 * The allowance with no start yet (`may_plan()` with 0), asked only of a tier that includes the
	 * co-hosted event: one without it plans nothing, which is the store's own `no_tier`, and nothing
	 * is read to learn it. The planner draws its form when this is '', and the Events card offers
	 * Plan an event on its row by the same answer, so the row never offers a plan the form would not
	 * take. Whether the sponsor may plan now, not how many events a term holds: that count is the
	 * store's `WPCPM_Sponsor_Event::extra_events()`, which this answer weighs a term's events against.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $tier   The effective tier.
	 * @return string '' or one of `may_plan()`'s keys.
	 */
	public static function allowance( $record, array $row, $tier ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Event' ) || ! in_array( WPCPM_Sponsor_Tiers::EVENT_CO_HOSTED, WPCPM_Sponsor_Tiers::events( $tier ), true ) ) {
			return 'no_tier';
		}

		return WPCPM_Sponsor_Event::may_plan( $record, $row, 0 );
	}

	/**
	 * Whether an allowance's answer leaves the sponsor holding the planner: every answer but the two
	 * about the sponsor itself, the switch and the tier. An event still to take place, or the term's
	 * event held, stops a new plan and leaves the planner the sponsor's. The Events card reads it too
	 * (`WPCPM_Sponsor_Tier_Card`), of the one allowance it asks for both whether it lists the tier's
	 * events and whether it offers Plan an event, so it and the planner read one answer one way.
	 *
	 * @param string $refusal '' or one of `may_plan()`'s keys.
	 * @return bool
	 */
	public static function holds( $refusal ) {
		return ! in_array( $refusal, array( 'no_tier', 'switch_off' ), true );
	}

	/*
	 * --------------------------------------------------------------------
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The planner, inside the Events card. Nothing while the launch switch is off, or on a checkout
	 * without the event store.
	 *
	 * The allowance is asked of the sponsor alone, with no start yet, as `offered()` asks it: a tier
	 * without the co-hosted event plans nothing, which is the store's own `no_tier`.
	 *
	 * @param string  $record Sponsor key.
	 * @param array   $row    The sponsor's row, as the card read it.
	 * @param string  $tier   The effective tier the card read for today.
	 * @param WP_User $viewer Who is looking: every day and time is drawn and read on their clock.
	 */
	public static function render( $record, array $row, $tier, $viewer ) {
		if ( ! WPCPM_Sponsor_Tiers::live() || ! class_exists( 'WPCPM_Sponsor_Event' ) ) {
			return;
		}

		$person   = $viewer instanceof WP_User ? $viewer : wp_get_current_user();
		$zone     = WPCPM_Mentor_Availability::viewer_timezone( (int) $person->ID );
		$refusal  = self::allowance( $record, $row, $tier );
		$offered  = self::holds( $refusal );
		$upcoming = WPCPM_Sponsor_Event::upcoming_of( $record );
		$until    = self::until_of( $row );
		$extra    = WPCPM_Sponsor_Event::extra_events( $row, $until );

		if ( array() !== $upcoming ) {
			self::subheading( __( 'Your event', 'wpcredits-program-manager' ) );
		}

		foreach ( $upcoming as $event ) {
			self::render_own( $record, $event, $zone, $offered, $row );
		}

		// The two refusals a sponsor waits out get a sentence in the form's place, the flash's own
		// words for a plan pressed anyway. A sponsor that no longer holds the planner (`no_tier`) gets
		// none: the card around this then draws neither the tier's events nor the sentence about the
		// planner, and an event still to take place says it is no longer offered.
		$wait = self::wait_status( $refusal, $until, $extra, self::held_for_wait( $refusal, $record, $until, $extra ) );

		if ( '' === $refusal ) {
			self::render_plan( $record, $zone, $until, $extra );
		} elseif ( '' !== $wait ) {
			self::note( self::messages()[ $wait ][1] );
		}

		self::render_past( WPCPM_Sponsor_Event::past_of( $record ), $zone );
	}

	/**
	 * The sponsor's event still to take place: its title, when it runs on the viewer's clock with
	 * the clock named, its state and how many joined, then Change while the sponsor holds the
	 * planner, and Cancel.
	 *
	 * @param string       $record  Sponsor key.
	 * @param WP_Post      $event   The event.
	 * @param DateTimeZone $zone    The viewer's clock.
	 * @param bool         $offered Whether the sponsor holds the planner today: an approved event of a
	 *                              sponsor that does not is on nobody's list, and takes no change.
	 * @param array        $row     The sponsor's row, for the term its Change form's date box keeps to.
	 */
	private static function render_own( $record, WP_Post $event, DateTimeZone $zone, $offered, array $row ) {
		$facts = WPCPM_Sponsor_Event::facts( $event );

		echo '<div class="wpcpm-sevent__own">';
		printf( '<p class="wpcpm-sevent__title">%s</p>', esc_html( $facts['title'] ) );
		printf(
			'<p class="wpcpm-sevent__meta">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: the day and the start and end times, 2: how long it runs, e.g. 60 minutes, 3: the time zone's name. */
					__( '%1$s, %2$s, in your time zone (%3$s).', 'wpcredits-program-manager' ),
					WPCPM_Mentor_Calls::format_range( $facts['start'], $facts['end'], $zone ),
					WPCPM_Sponsor_Event::length_line( $facts['minutes'] ),
					WPCPM_Mentor_Availability::zone_label( $zone->getName() )
				)
			)
		);
		printf( '<p class="wpcpm-sevent__state">%s</p>', esc_html( self::state( $facts, $offered ) ) );

		if ( $offered ) {
			self::render_change( $record, $event, $facts, $zone, $row );
		}

		self::render_cancel( $record, $event );

		echo '</div>';
	}

	/**
	 * Where an event stands, in words: waiting, or approved with how many joined. Who joined is for
	 * program managers alone; the sponsor reads the count.
	 *
	 * @param array $facts   The event's facts.
	 * @param bool  $offered Whether the sponsor offers events today.
	 * @return string
	 */
	private static function state( array $facts, $offered ) {
		if ( WPCPM_Sponsor_Event::STATE_APPROVED !== $facts['state'] ) {
			return __( 'Waiting for the program\'s OK.', 'wpcredits-program-manager' );
		}

		$state = $offered
			? __( 'Approved.', 'wpcredits-program-manager' )
			: __( 'Approved, but no longer offered: your company\'s sponsorship does not include events at the moment.', 'wpcredits-program-manager' );

		if ( 0 === (int) $facts['joined'] ) {
			return $state . ' ' . __( 'Nobody has joined yet.', 'wpcredits-program-manager' );
		}

		return $state . ' ' . sprintf(
			/* translators: %s: how many people have joined the event. */
			_n( '%s person has joined.', '%s people have joined.', (int) $facts['joined'], 'wpcredits-program-manager' ),
			number_format_i18n( (int) $facts['joined'] )
		);
	}

	/**
	 * Change: the event's form, folded, every box filled from the event on the viewer's clock, the
	 * clock the handler reads them back on, or with what the person typed when a refusal of this
	 * change brought the page back (`kept_rows()`: the rows `typed()` reads, and the title and the
	 * description exactly as typed while that typing waits, the event's own after), the fold then
	 * drawn open. The places cannot be set below the people on it, so the store's refusal is the
	 * browser's first.
	 *
	 * The date box starts on the site's today, as a plan's does, or, for an event of a renewed term,
	 * on that term's first day when it is later, since the store keeps such an event in its term
	 * (`WPCPM_Sponsor_Event::change_floor()`); and never after the day the event is drawn on. It ends
	 * on the term's last day, and never before that day: the form as drawn can always be saved. The
	 * days are the site's while the box reads the viewer's clock, so past the site's midnight the
	 * site's today is already tomorrow for a viewer west of the site, whose event may still be later
	 * today on their own clock; and on the term's last evening an event is already the next day for
	 * a viewer east of the site.
	 *
	 * @param string       $record Sponsor key.
	 * @param WP_Post      $event  The event.
	 * @param array        $facts  The event's facts.
	 * @param DateTimeZone $zone   The viewer's clock.
	 * @param array        $row    The sponsor's row, read for its term.
	 */
	private static function render_change( $record, WP_Post $event, array $facts, DateTimeZone $zone, array $row ) {
		$start = ( new DateTimeImmutable( '@' . (int) $facts['start'] ) )->setTimezone( $zone );
		$drawn = $start->format( 'Y-m-d' );
		$until = self::until_of( $row );
		$floor = WPCPM_Sponsor_Event::change_floor( $event, $row );
		$today = WPCPM_Sponsor_Tiers::today();
		$from  = strcmp( $floor, $today ) > 0 ? $floor : $today;
		$first = strcmp( $drawn, $from ) < 0 ? $drawn : $from;
		$last  = '' !== $until && strcmp( $drawn, $until ) > 0 ? $drawn : $until;
		$kept  = self::kept_for( $record, (int) $event->ID );

		echo array() !== $kept ? '<details class="wpcpm-sevent__change" open>' : '<details class="wpcpm-sevent__change">';
		printf( '<summary class="wpcpm-sevent__change-toggle">%s</summary>', esc_html__( 'Change the event', 'wpcredits-program-manager' ) );

		self::open_form( self::ACTION_CHANGE, $record, 'wpcpm-sponsor__form wpcpm-sevent__form', __( 'Saving', 'wpcredits-program-manager' ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_EVENT ), (int) $event->ID );

		// A kept typing is drawn over the event's own values, which give its boxes their room.
		self::render_fields(
			'wpcpm-sevent-' . (int) $event->ID,
			array(
				'date'        => $drawn,
				'time'        => $start->format( 'H:i' ),
				'minutes'     => (string) (int) $facts['minutes'],
				'places'      => $facts['places'] > 0 ? (string) (int) $facts['places'] : '',
				'title'       => $facts['title'],
				'description' => $facts['description'],
				'link'        => WPCPM_Sponsor_Event::link_of( $event ),
			),
			$zone,
			max( 1, (int) $facts['joined'] ),
			$first,
			$last,
			$kept
		);

		self::submit( __( 'Save the changes', 'wpcredits-program-manager' ) );
		echo '</form></details>';
	}

	/**
	 * Cancel: one button, asking before it posts, since a cancel cannot be taken back.
	 *
	 * @param string  $record Sponsor key.
	 * @param WP_Post $event  The event.
	 */
	private static function render_cancel( $record, WP_Post $event ) {
		self::open_form( self::ACTION_CANCEL, $record, 'wpcpm-sponsor__form wpcpm-inline-form wpcpm-sevent__cancel', __( 'Canceling', 'wpcredits-program-manager' ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_EVENT ), (int) $event->ID );
		printf(
			'<button type="submit" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-confirm="%1$s">%2$s</button>',
			esc_attr__( 'Cancel this event? This cannot be undone.', 'wpcredits-program-manager' ),
			esc_html__( 'Cancel the event', 'wpcredits-program-manager' )
		);
		echo '</form>';
	}

	/**
	 * The form that plans an event, under what the term allows while the program has granted it more
	 * events than the tier's one, and under the note the last plan was returned with, if it was. A
	 * term granted none says nothing of how many it holds, as the planner never has. Its boxes are
	 * empty but for the suggested length, or hold what the person typed when a refusal of their plan
	 * brought the page back (`kept_rows()`: the rows `typed()` reads, and the title and the
	 * description exactly as typed while that typing waits, empty after). It sits in no fold.
	 *
	 * @param string       $record Sponsor key.
	 * @param DateTimeZone $zone   The viewer's clock.
	 * @param string       $until  The running term's last day, `Y-m-d`, or '' with no term.
	 * @param int          $extra  The events the running term was granted beyond the tier's one
	 *                             (`WPCPM_Sponsor_Event::extra_events()`), 0 with no term.
	 */
	private static function render_plan( $record, DateTimeZone $zone, $until, $extra ) {
		self::subheading( __( 'Plan your co-hosted event', 'wpcredits-program-manager' ) );

		if ( $extra > 0 ) {
			$allowed = 1 + (int) $extra;

			self::note(
				sprintf(
					/* translators: %s: how many co-hosted events the sponsor's term allows. */
					_n( 'Your term allows %s event.', 'Your term allows %s events.', $allowed, 'wpcredits-program-manager' ),
					number_format_i18n( $allowed )
				)
			);
		}

		$note = WPCPM_Sponsor_Event::returned_note( $record );

		if ( '' !== $note ) {
			printf(
				'<p class="wpcpm-sevent__returned">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the note an Administrator returned the sponsor's last plan with. */
						__( 'Your last plan was returned: %s', 'wpcredits-program-manager' ),
						$note
					)
				)
			);
		}

		$kept = self::kept_for( $record, 0 );

		self::open_form( self::ACTION_PLAN, $record, 'wpcpm-sponsor__form wpcpm-sevent__form', __( 'Sending', 'wpcredits-program-manager' ) );
		self::render_fields(
			'wpcpm-sevent-new',
			array(
				'date'        => '',
				'time'        => '',
				'minutes'     => (string) self::DEFAULT_MINUTES,
				'places'      => '',
				'title'       => '',
				'description' => '',
				'link'        => '',
			),
			$zone,
			1,
			WPCPM_Sponsor_Tiers::today(),
			$until,
			$kept
		);
		self::submit( __( 'Plan the event', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * The form's rows, the plan's and the change's alike: the day, the start time with the clock
	 * named beside it, the length, the places, the title, the description and the call link.
	 *
	 * Each box says in the browser most of what the store refuses, so a press is refused as seldom as
	 * it can be: the day from the site's today, the day the store reads a start on, to the running
	 * term's last day; a change's box from a renewed term's first day instead when that is the later,
	 * for an event the store keeps in that term (`WPCPM_Sponsor_Event::change_floor()`), and
	 * stretched to the event's own day where the viewer's clock draws it outside them
	 * (`render_change()`); and the link an http or https address. The store keeps the last word where
	 * the browser cannot follow it: a start earlier today, an hour the clocks skip, and the edges of
	 * the term's days, which the store reads on the site's clock while the box reads the viewer's. A
	 * post refused for a reason the person puts right by pressing again comes back with what was
	 * typed (`keep_typed()`), which the rows hold in place of what they hold otherwise
	 * (`typed()`), every value escaped here as a stored one is, the call link as typed and the
	 * title and the description read back to the text typed (`WPCPM_Typed_Text::typed_text()`).
	 * A title the event holds with a line break is drawn with a space in its place
	 * (`WPCPM_Typed_Text::one_line()`, a CR LF one break), since a text input drops the break and
	 * would post the title back joined, and posted back so it is read as unedited (`unedited()`).
	 *
	 * The forms come back with exactly what was typed (`$kept`, `kept_for()`):
	 * every row of a post refused because the text cleaner would take words from its title or its
	 * description, and the title and the description of one refused for an outcome of `KEEPS_TYPED`,
	 * beside its other rows from the planner's channel. Each row the kept typing holds is drawn with
	 * it in place of `$values`', the title through `WPCPM_Typed_Text::kept_attr()` and the
	 * description after a line feed of its own, as the parser drops the one right after the opening
	 * tag, each in the room `$values`' text gives its box (`WPCPM_Typed_Text::kept_room()`), the room
	 * it was kept in.
	 *
	 * The description box says where its words go: once the event is approved, the public sponsors
	 * page lists it with its description (`WPCPM_Sponsor_Event::public_upcoming()`), every http,
	 * https and mailto address taken out of it there and every other word kept, so the call link
	 * belongs in its own box.
	 *
	 * The Administrator Dashboard's card draws the program's own plan and change forms with these
	 * rows too (`WPCPM_Sponsor_Event_Queue`), with no last day, no term binding the program, so the
	 * two forms can never come to ask for different fields, and brings them back with what was typed
	 * the same way.
	 *
	 * @param string       $prefix The ids' prefix, one per form on the page.
	 * @param array        $values `date`, `time`, `minutes`, `places`, `title`, `description`, `link`:
	 *                             as stored, or as typed (`typed()`).
	 * @param DateTimeZone $zone   The viewer's clock.
	 * @param int          $floor  The fewest places the box takes.
	 * @param string       $first  The first day the date box offers, `Y-m-d`.
	 * @param string       $last   The last day it offers, `Y-m-d`, or '' for no last day.
	 * @param array        $kept   What a refusal of this form kept as typed, by row, or none.
	 */
	public static function render_fields( $prefix, array $values, DateTimeZone $zone, $floor, $first, $last, array $kept = array() ) {
		foreach ( array_keys( self::typed_limits() ) as $key ) {
			if ( isset( $kept[ $key ] ) && is_string( $kept[ $key ] ) && ! isset( self::TYPED_BOXES[ $key ] ) ) {
				$values[ $key ] = $kept[ $key ];
			}
		}

		$title       = isset( $kept['title'] ) && is_string( $kept['title'] ) ? $kept['title'] : null;
		$description = isset( $kept['description'] ) && is_string( $kept['description'] ) ? $kept['description'] : null;

		printf(
			'<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="%1$s-date">%2$s</label><input type="date" id="%1$s-date" name="%3$s" value="%4$s" min="%5$s"',
			esc_attr( $prefix ),
			esc_html__( 'Date', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_DATE ),
			esc_attr( $values['date'] ),
			esc_attr( $first )
		);

		if ( '' !== $last ) {
			printf( ' max="%s"', esc_attr( $last ) );
		}

		echo ' required /></p>';
		printf(
			'<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="%1$s-time">%2$s</label><input type="time" id="%1$s-time" name="%3$s" value="%4$s" required aria-describedby="%1$s-zone" /><span class="wpcpm-student__note" id="%1$s-zone">%5$s</span></p>',
			esc_attr( $prefix ),
			esc_html__( 'Start time', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_TIME ),
			esc_attr( $values['time'] ),
			esc_html(
				sprintf(
					/* translators: %s: the time zone's name, e.g. America / New York. */
					__( 'In your time zone, %s. Everyone who joins sees it in theirs.', 'wpcredits-program-manager' ),
					WPCPM_Mentor_Availability::zone_label( $zone->getName() )
				)
			)
		);
		printf(
			'<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="%1$s-minutes">%2$s</label><input type="number" id="%1$s-minutes" name="%3$s" value="%4$s" min="%5$d" max="%6$d" step="%7$d" required /></p>',
			esc_attr( $prefix ),
			esc_html__( 'Length in minutes', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_MINUTES ),
			esc_attr( $values['minutes'] ),
			(int) WPCPM_Sponsor_Event::MIN_MINUTES,
			(int) WPCPM_Sponsor_Event::MAX_MINUTES,
			(int) self::MINUTES_STEP
		);
		printf(
			'<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="%1$s-places">%2$s</label><input type="number" id="%1$s-places" name="%3$s" value="%4$s" min="%5$d" max="%6$d" step="1" aria-describedby="%1$s-places-note" /><span class="wpcpm-student__note" id="%1$s-places-note">%7$s</span></p>',
			esc_attr( $prefix ),
			esc_html__( 'Places', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_PLACES ),
			esc_attr( $values['places'] ),
			(int) $floor,
			(int) WPCPM_Sponsor_Event::MAX_PLACES,
			esc_html__( 'Leave it blank for no limit.', 'wpcredits-program-manager' )
		);
		printf(
			'<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="%1$s-title">%2$s</label><input type="text" id="%1$s-title" name="%3$s" value="%4$s" maxlength="%5$d" required /></p>',
			esc_attr( $prefix ),
			esc_html__( 'Title', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_TITLE ),
			// esc_attr() keeps an entity it is handed, which the box would show as its character:
			// attr_text() writes each "&" of the text read back out first, and kept_attr() each "&" of
			// a kept typing, so the box shows that text as it is.
			esc_attr( null !== $title ? WPCPM_Typed_Text::kept_attr( $title ) : WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::attr_text( $values['title'] ) ) ),
			(int) ( null !== $title ? WPCPM_Typed_Text::kept_room( $values['title'], WPCPM_Sponsor_Event::MAX_TITLE ) : WPCPM_Typed_Text::drawn_limit( $values['title'], WPCPM_Sponsor_Event::MAX_TITLE ) )
		);
		printf(
			'<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="%1$s-description">%2$s</label><textarea id="%1$s-description" name="%3$s" rows="4" maxlength="%4$d" required aria-describedby="%1$s-description-note">%5$s</textarea><span class="wpcpm-student__note" id="%1$s-description-note">%6$s</span></p>',
			esc_attr( $prefix ),
			esc_html__( 'Description', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_DESCRIPTION ),
			(int) ( null !== $description ? WPCPM_Typed_Text::kept_room( $values['description'], WPCPM_Sponsor_Event::MAX_DESCRIPTION ) : WPCPM_Typed_Text::drawn_limit( $values['description'], WPCPM_Sponsor_Event::MAX_DESCRIPTION ) ),
			// The parser drops one line feed right after the opening tag, so a kept typing, which can
			// begin with a line break, is given one of its own first.
			null !== $description ? "\n" . esc_textarea( $description ) : esc_textarea( WPCPM_Typed_Text::typed_text( $values['description'] ) ),
			esc_html__( 'Shown on the Partners page once the event is approved. Put the call link in its own box.', 'wpcredits-program-manager' )
		);
		printf(
			'<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="%1$s-link">%2$s</label><input type="url" id="%1$s-link" name="%3$s" value="%4$s" maxlength="%5$d" pattern="https?://.+" required aria-describedby="%1$s-link-note" /><span class="wpcpm-student__note" id="%1$s-link-note">%6$s</span></p>',
			esc_attr( $prefix ),
			esc_html__( 'Call link', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_LINK ),
			esc_attr( $values['link'] ),
			(int) WPCPM_Sponsor_Event::MAX_LINK,
			esc_html__( 'Only the people who join, and the program, see this link.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The events that took place, read only: each title, its day on the viewer's clock and how many
	 * joined. Nothing when there are none.
	 *
	 * @param WP_Post[]    $events The events, as `past_of()` lists them.
	 * @param DateTimeZone $zone   The viewer's clock.
	 */
	private static function render_past( array $events, DateTimeZone $zone ) {
		if ( array() === $events ) {
			return;
		}

		self::subheading( __( 'Past events', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-sevent__past">';

		foreach ( $events as $event ) {
			$facts  = WPCPM_Sponsor_Event::facts( $event );
			$day    = wp_date( (string) get_option( 'date_format', 'F j, Y' ), $facts['start'], $zone );
			$joined = (int) $facts['joined'];

			if ( 0 === $joined ) {
				/* translators: %s: the day the event took place. */
				$held = sprintf( __( '%s. Nobody joined.', 'wpcredits-program-manager' ), $day );
			} else {
				$held = sprintf(
					/* translators: 1: the day the event took place, 2: how many people joined it. */
					_n( '%1$s. %2$s person joined.', '%1$s. %2$s people joined.', $joined, 'wpcredits-program-manager' ),
					$day,
					number_format_i18n( $joined )
				);
			}

			printf( '<li><span class="wpcpm-sevent__title">%1$s</span> <span class="wpcpm-sevent__meta">%2$s</span></li>', esc_html( $facts['title'] ), esc_html( $held ) );
		}

		echo '</ul>';
	}

	/**
	 * A form's opening, the same for all three: posted to admin-post.php, guarded against a second
	 * press, with the nonce keyed to the sponsor and the two hidden fields every press is read by.
	 *
	 * @param string $action  ACTION_PLAN, ACTION_CHANGE or ACTION_CANCEL.
	 * @param string $record  Sponsor key.
	 * @param string $classes The form's classes.
	 * @param string $busy    What the pressed button says while the request is in flight.
	 */
	private static function open_form( $action, $record, $classes, $busy ) {
		printf(
			'<form method="post" action="%1$s" class="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $classes ),
			esc_attr( $busy )
		);
		wp_nonce_field( $action . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_SPONSOR ), esc_attr( $record ) );
	}

	/**
	 * A form's button, on its own row.
	 *
	 * @param string $label The button's words.
	 */
	private static function submit( $label ) {
		printf( '<p class="wpcpm-sevent__submit"><button type="submit" class="wpcpm-button">%s</button></p>', esc_html( $label ) );
	}

	/**
	 * A sub-heading inside the card, as the card's own list has.
	 *
	 * @param string $text The heading.
	 */
	private static function subheading( $text ) {
		printf( '<h4 class="wpcpm-sponsor__subheading">%s</h4>', esc_html( $text ) );
	}

	/**
	 * A sentence in the card's note voice.
	 *
	 * @param string $text The sentence.
	 */
	private static function note( $text ) {
		printf( '<p class="wpcpm-student__note">%s</p>', esc_html( $text ) );
	}

	/**
	 * The running term's last day on a sponsor's row, `Y-m-d`, or '' with no term. Both forms are
	 * drawn, and a plan is taken, only while the sponsor holds the planner, and a tier that holds it
	 * has no term or one still running, so a day on the row is the running term's last day.
	 *
	 * @param array $row The sponsor's row.
	 * @return string
	 */
	private static function until_of( array $row ) {
		return isset( $row['tier_until'] ) && WPCPM_Sponsor_Tiers::is_date( (string) $row['tier_until'] ) ? (string) $row['tier_until'] : '';
	}

	/**
	 * The outcome a wait is said with, in the form's place on the card and as the flash of a plan
	 * pressed anyway, so the two read the same. While an event is still to take place: with no term,
	 * the next event follows it; while a term runs, the event is the term's; and for a term the
	 * program granted more events than the tier's one, the next event follows it while the term can
	 * hold another after it, and the term's events are planned already once it is the last the term
	 * holds. The term holds the tier's one and `$extra` more, and once the event still to take place
	 * has taken place the term will have held one more than `$held`, so another can follow while
	 * `$held` is below `$extra`. Once the term's event has taken place, it has, and for a term granted
	 * more, every one of them has.
	 *
	 * @param string $refusal '' or one of `may_plan()`'s keys.
	 * @param string $until   The running term's last day, `Y-m-d`, or '' with no term.
	 * @param int    $extra   The events that term was granted beyond the tier's one.
	 * @param int    $held    The approved events the term has held so far (`held_for_wait()`).
	 * @return string A key of `messages()`, or '' for a refusal that is no wait.
	 */
	private static function wait_status( $refusal, $until, $extra = 0, $held = 0 ) {
		if ( 'term_used' === $refusal ) {
			return $extra > 0 ? 'sevent-term-used-more' : 'sevent-term-used';
		}

		if ( 'one_open' !== $refusal ) {
			return '';
		}

		if ( '' === $until ) {
			return 'sevent-one-open';
		}

		if ( $extra <= 0 ) {
			return 'sevent-one-open-term';
		}

		return $held < $extra ? 'sevent-one-open' : 'sevent-one-open-more';
	}

	/**
	 * How many approved events the running term has held, for the one wait that turns on it: an event
	 * still to take place in a term granted more than the tier's one. The events store's own count of
	 * a period (`WPCPM_Sponsor_Event::held_in()`) over the term's twelve months
	 * (`WPCPM_Sponsor_Report::period()`), the reader the annual report takes the term's events from;
	 * 0, and nothing read, for every other answer.
	 *
	 * @param string $refusal '' or one of `may_plan()`'s keys.
	 * @param string $record  Sponsor key.
	 * @param string $until   The running term's last day, `Y-m-d`, or '' with no term.
	 * @param int    $extra   The events that term was granted beyond the tier's one.
	 * @return int
	 */
	private static function held_for_wait( $refusal, $record, $until, $extra ) {
		if ( 'one_open' !== $refusal || '' === $until || $extra <= 0 ) {
			return 0;
		}

		$period = WPCPM_Sponsor_Report::period( $until );

		return count( WPCPM_Sponsor_Event::held_in( $record, $period['from'], $period['until'] ) );
	}

	/*
	 * --------------------------------------------------------------------
	 * The presses
	 * --------------------------------------------------------------------
	 */

	/**
	 * Plan: while the sponsor holds the planner, the title and the description asked whether the text
	 * cleaner would take words from them (`refuse_loss()`), then the press counted (`spend_press()`),
	 * then the form's fields read on the planner's clock, then the store's plan, which asks the
	 * allowance again under the sponsor's lock; the program contact is told. Once the form is read,
	 * every outcome but the ceiling's leaves through `leave_typed()`, so a refusal the person puts
	 * right by pressing again brings the form back with what was typed.
	 */
	public static function handle_plan() {
		$asked = WPCPM_Request::posted_text( self::FIELD_SPONSOR );

		check_admin_referer( self::ACTION_PLAN . '_' . $asked );

		$claim  = self::claim( $asked );
		$record = $claim['record'];

		// What an earlier refusal of this form kept is stale once the form is posted again.
		self::forget_typed( $record, 0 );

		// Asked before the form is read, as a change asks it: a nonce printed while the sponsor held
		// the planner outlives its holding it, and a sponsor that no longer does has nothing it
		// posts read, its call link's host looked up nowhere.
		if ( ! self::offered( $record, $claim['row'], WPCPM_Sponsor_Tiers::effective( $claim['row'] ) ) ) {
			self::leave( 'sevent-no-tier', $record );
		}

		$posted = self::posted_fields();

		self::refuse_loss( $record, 0, $posted );
		self::spend_press( $record );

		$read = WPCPM_Sponsor_Event::fields_from( $posted, self::zone() );

		if ( array() !== $read['errors'] ) {
			self::leave_typed( 'sevent-fields', $record, 0, $posted, self::detail( $read['errors'] ) );
		}

		$planned = WPCPM_Sponsor_Event::plan( $record, $read['fields'], get_current_user_id(), $claim['decision']['ground'] );

		if ( is_wp_error( $planned ) ) {
			self::refused( $planned, $record, $claim['row'], 0, $posted );
		}

		self::mail_contact( $record, $planned, self::MAIL_PLANNED, $claim['decision']['ground'] );
		self::leave_typed( 'sevent-sent', $record, 0, $posted );
	}

	/**
	 * Change: the event the form names, if the person plans for its sponsor and the sponsor still
	 * holds the planner, its title and description asked whether the text cleaner would take words
	 * from them, each but a box posted back as it was drawn (`refuse_loss()`), the press counted
	 * (`spend_press()`), and its fields read on the planner's clock; the store keeps its state, keeps
	 * the text it holds in a box posted back as drawn (`unedited`), and tells the people on the event
	 * of a new time, length, title or link, the sponsor's members are sent the new calendar file when
	 * the program had approved it, and the program contact is told. When the change alters nothing,
	 * the store writes nothing and says so, and nobody is told. Once the form is read, every outcome
	 * but the ceiling's leaves through `leave_typed()`, as a plan's does.
	 */
	public static function handle_change() {
		$asked = WPCPM_Request::posted_text( self::FIELD_SPONSOR );

		check_admin_referer( self::ACTION_CHANGE . '_' . $asked );

		$claim  = self::claim( $asked );
		$event  = self::own_event();
		$record = WPCPM_Sponsor_Event::sponsor_of( $event );
		$row    = WPCPM_Sponsors_Index::row( $record );
		$row    = is_array( $row ) ? $row : array();

		// What an earlier refusal of this event's form kept is stale once the form is posted again.
		self::forget_typed( $record, (int) $event->ID );

		// A sponsor that no longer holds the planner, paused or whose tier lost events, keeps Cancel
		// alone: nothing it posts is read, and nothing is written. The switch is on here (the claim
		// asked), so a refusal is the sponsor's own `no_tier`.
		if ( ! self::offered( $record, $row, WPCPM_Sponsor_Tiers::effective( $row ) ) ) {
			self::leave( 'sevent-no-tier', $record );
		}

		$posted   = self::posted_fields();
		$unedited = self::refuse_loss( $record, (int) $event->ID, $posted );

		self::spend_press( $record );

		$read = WPCPM_Sponsor_Event::fields_from( $posted, self::zone() );

		if ( array() !== $read['errors'] ) {
			self::leave_typed( 'sevent-fields', $record, (int) $event->ID, $posted, self::detail( $read['errors'] ) );
		}

		$changed = WPCPM_Sponsor_Event::change( $event, array_merge( $read['fields'], array( 'unedited' => $unedited ) ), get_current_user_id() );

		if ( is_wp_error( $changed ) ) {
			self::refused( $changed, $record, $row, (int) $event->ID, $posted );
		}

		if ( empty( $changed['changed'] ) ) {
			self::leave_typed( 'sevent-unchanged', $record, (int) $event->ID, $posted );
		}

		self::mail_members( $record, $event->ID, self::MAIL_CHANGED, $claim['decision']['ground'] );
		self::mail_contact( $record, $event->ID, self::MAIL_CHANGED, $claim['decision']['ground'] );
		self::leave_typed( 'sevent-changed', $record, (int) $event->ID, $posted, self::told( $changed, $event ) );
	}

	/**
	 * Cancel: the press counted (`spend_press()`), then the event the form names, if the person plans
	 * for its sponsor; the store cancels it and tells the people on it, the sponsor's members are sent
	 * the file that takes it out of their calendars when the program had approved it, and the program
	 * contact is told.
	 */
	public static function handle_cancel() {
		$asked = WPCPM_Request::posted_text( self::FIELD_SPONSOR );

		check_admin_referer( self::ACTION_CANCEL . '_' . $asked );

		$claim = self::claim( $asked );

		self::spend_press( $claim['record'] );

		$event    = self::own_event();
		$record   = WPCPM_Sponsor_Event::sponsor_of( $event );
		$canceled = WPCPM_Sponsor_Event::cancel( $event, get_current_user_id() );

		if ( is_wp_error( $canceled ) ) {
			self::leave( self::status_of( $canceled ), $record );
		}

		self::mail_members( $record, $event->ID, self::MAIL_CANCELED, $claim['decision']['ground'] );
		self::mail_contact( $record, $event->ID, self::MAIL_CANCELED, $claim['decision']['ground'] );
		self::leave( 'sevent-canceled', $record, self::told( $canceled, $event ) );
	}

	/**
	 * The detail a change or a cancel adds to its outcome, once the store answered how many of the
	 * people on the event it told in this request and how many it left to the drain: a sentence only
	 * when the two together are every one of them, and there is somebody. That everyone who joined
	 * has been told, when the press told them all; that everyone who joined is being told, when the
	 * drain has the rest. Every one is every person the event's rows hold (`joiners()`, one each), an
	 * account that is gone included: the sentence claims everybody, and a person no mail can reach,
	 * or whose mail was refused, makes it untrue. The mail log keeps the sends that failed.
	 *
	 * @param array   $answer The store's answer: `told` and `pending`.
	 * @param WP_Post $event  The event, whose people a change and a cancel both leave on its record.
	 * @return string
	 */
	private static function told( array $answer, WP_Post $event ) {
		$people  = count( WPCPM_Sponsor_Event::joiners( $event ) );
		$told    = isset( $answer['told'] ) ? (int) $answer['told'] : 0;
		$pending = isset( $answer['pending'] ) ? (int) $answer['pending'] : 0;

		if ( $people <= 0 || $told + $pending !== $people ) {
			return '';
		}

		return $pending > 0 ? __( 'Everyone who joined is being told.', 'wpcredits-program-manager' ) : __( 'Everyone who joined has been told.', 'wpcredits-program-manager' );
	}

	/**
	 * What every press checks once its handler has checked the nonce keyed to the sponsor the form
	 * names, in this order: the roster's claim (which decides `ACT_PLAN_EVENT` and meters a
	 * refusal), the launch switch and a row the site holds. A member's claim acts on the member's own
	 * sponsor whatever the form names. The press is counted after it (`spend_press()`).
	 *
	 * @param string $asked The sponsor the form names.
	 * @return array The claim: `record`, `decision`, and `row`, which is an array here.
	 */
	private static function claim( $asked ) {
		$claim = WPCPM_Sponsor_Roster::claim( $asked, WPCPM_Sponsor_Policy::ACT_PLAN_EVENT );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		// While the switch is off nothing here is drawn, so a press comes from a page drawn before it
		// went off, or from a request made by hand; a sponsor the site no longer holds has no tier.
		// Either way nothing is written, and the sentence names no tier.
		if ( ! WPCPM_Sponsor_Tiers::live() || ! is_array( $claim['row'] ) ) {
			self::leave( 'tier-off', $claim['record'] );
		}

		return $claim;
	}

	/**
	 * Count the press against the account's ceiling of `PER_HOUR` presses, as the interests card
	 * counts its own, and refuse it past the ceiling (`sevent-ceiling`).
	 *
	 * Counted once the press is the account's to make, so a stranger's or a stale page's press costs
	 * the account nothing. A plan or a change is counted once the sponsor is found to hold the planner
	 * and its title and description lose no words (`refuse_loss()`), so a press refused for either,
	 * which reads no link and writes, mails and schedules nothing, costs nothing; a press the store
	 * is then asked about counts, refused by the form's own check or by the store as well.
	 *
	 * @param string $record Sponsor key, for where the refusal lands.
	 */
	private static function spend_press( $record ) {
		if ( ! WPCPM_Ceiling::claim( WPCPM_Ceiling::key( self::CEILING, (string) get_current_user_id() ), self::PER_HOUR, HOUR_IN_SECONDS ) ) {
			self::leave( 'sevent-ceiling', $record );
		}
	}

	/**
	 * The event a change or a cancel names, when the person may plan for its sponsor: asked of the
	 * sponsor policy, the one place a sponsor is matched to a person, so a member of another sponsor,
	 * or a value that is no event, meets the one refusal. An event of the program's own is no
	 * sponsor's and meets it too, a program manager's press through the switcher included: the
	 * program changes and cancels its own events on the Administrator Dashboard's card, where no
	 * sponsor's members or program contact are told of them as if a sponsor had acted.
	 *
	 * @return WP_Post
	 */
	private static function own_event() {
		$event_id = WPCPM_Request::posted_id( self::FIELD_EVENT );
		$event    = $event_id > 0 ? get_post( $event_id ) : null;

		if ( ! WPCPM_Sponsor_Event::is_event( $event ) || WPCPM_Sponsor_Event::is_program( $event ) ) {
			self::leave( 'refused', '' );
		}

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_PLAN_EVENT, WPCPM_Sponsor_Policy::subject_sponsor( WPCPM_Sponsor_Event::sponsor_of( $event ) ) );

		if ( empty( $decision['allowed'] ) ) {
			self::leave( 'refused', '' );
		}

		return $event;
	}

	/**
	 * The form's fields, through the request readers, for `fields_from()` to clean and check. The
	 * link is read as typed: the text reader strips percent-encoded characters, which a meeting
	 * link's query often holds, and the store's own cleaner is the link's. The Administrator
	 * Dashboard's card reads the program's own plan and change forms, which draw these rows
	 * (`render_fields()`), through this too, once its handler has checked the nonce. What it answers
	 * is what a refused press keeps for its form to come back with (`keep_typed()`); the title and
	 * the description are the cleaner's, so the forms bring those two back as they were typed
	 * instead, kept beside them under the form's own name (`keep_typed()`, `kept_for()`).
	 *
	 * @return array `date`, `time`, `minutes`, `places`, `title`, `description` and `link`, unslashed.
	 */
	public static function posted_fields() {
		return array(
			'date'        => WPCPM_Request::posted_text( self::FIELD_DATE ),
			'time'        => WPCPM_Request::posted_text( self::FIELD_TIME ),
			'minutes'     => WPCPM_Request::posted_text( self::FIELD_MINUTES ),
			'places'      => WPCPM_Request::posted_text( self::FIELD_PLACES ),
			'title'       => WPCPM_Request::posted_text( self::FIELD_TITLE ),
			'description' => WPCPM_Request::posted_lines( self::FIELD_DESCRIPTION ),
			'link'        => WPCPM_Request::posted_verbatim( self::FIELD_LINK ),
		);
	}

	/**
	 * Keep what a refused plan or change posted, for the person who pressed, with the form it came
	 * from: the sponsor it plans for, '' for the program's own, and the event a change was for, 0 for
	 * a plan; kept when the outcome the press leaves with is one of the calling class's own that keep
	 * it, and otherwise nothing is kept and whatever an earlier refusal kept is dropped, so a press
	 * that ends any other way leaves nothing behind. The form reads it once, the next time a page
	 * draws it (`typed()`). The Administrator Dashboard's card keeps the program's own plan and change
	 * through this too (`WPCPM_Sponsor_Event_Queue`), with its own list of outcomes.
	 *
	 * Each value is kept only as long as its box lets a person type it (`typed_limits()`), so a post
	 * made by hand cannot park a value of any length in the person's meta, while the longest honest
	 * typing, which is exactly what a refusal of the fields keeps, is kept whole. The title and the
	 * description are measured as the box counted them, as the store measures them
	 * (`WPCPM_Typed_Text::typed_length()`): an entity the text cleaner wrote counts as the one
	 * character it stands for, and a line break the description posts as two bytes, as a browser
	 * sends a text area's, counts once. A Change form's title and description boxes let a person type
	 * up to their drawn limit, three more than the store's for each ">" the box held as `&gt;`
	 * (`WPCPM_Typed_Text::drawn_limit()`), so each is kept up to the drawn limit of the event's
	 * stored text: an edit that writes a held `&gt;` out and fills the box is refused by the store,
	 * and comes back as typed. A plan's boxes are drawn empty, at the store's limits. That bound is
	 * the store's, not the box's in bytes: entity text the store accepts, up to six times the limit
	 * in bytes, is kept as it is. The call link is read as typed and counts as the store counts it,
	 * and the four short boxes, which can hold no "<", count what was posted, a line break once. A
	 * longer value is kept as an empty string, and its box comes back empty.
	 *
	 * The title and the description are kept beside this exactly as typed, read from the request
	 * (`WPCPM_Request::posted_raw()`), under the form's own name (`keep_loss()`), and the form draws
	 * those two, not the cleaner's text this holds (`kept_for()`): drawn from the cleaner's text, a
	 * box the event holds "10%cashback" in, left as drawn, would post "10shback" back as an edit, and
	 * the next press would store it. As every kept typing is, each is cut to the room its box was
	 * drawn with.
	 *
	 * @param string   $status   The outcome the press leaves with.
	 * @param string[] $keeps    The calling class's outcomes that keep what was typed (its `KEEPS_TYPED`).
	 * @param array    $posted   What the form posted, as `posted_fields()` read it.
	 * @param string   $sponsor  The sponsor the form plans for, or '' for the program's own forms.
	 * @param int      $event_id The event a change was for, or 0 for a plan.
	 */
	public static function keep_typed( $status, array $keeps, array $posted, $sponsor, $event_id ) {
		if ( ! in_array( (string) $status, $keeps, true ) ) {
			// Taken to be dropped: the flash clears a channel by reading it.
			WPCPM_Flash::take( self::TYPED );

			return;
		}

		$kept  = array();
		$drawn = self::drawn_from( $event_id );

		foreach ( self::typed_limits() as $key => $limit ) {
			$value = isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? (string) $posted[ $key ] : '';

			if ( 'title' === $key || 'description' === $key ) {
				$length = WPCPM_Typed_Text::typed_length( $value );
				$limit  = WPCPM_Typed_Text::drawn_limit( $drawn[ $key ], $limit );
			} elseif ( 'link' === $key ) {
				$length = mb_strlen( $value );
			} else {
				$length = mb_strlen( str_replace( "\r\n", "\n", $value ) );
			}

			$kept[ $key ] = $length <= $limit ? $value : '';
		}

		WPCPM_Flash::set(
			self::TYPED,
			array(
				'sponsor' => (string) $sponsor,
				'event'   => (int) $event_id,
				'values'  => $kept,
			)
		);

		// The title and the description exactly as typed, beside them under the form's own name,
		// which the form draws in place of the cleaner's two (`kept_for()`).
		$typed = array();

		foreach ( self::TYPED_BOXES as $key => $box ) {
			$typed[ $key ] = WPCPM_Request::posted_raw( $box[0] );
		}

		self::keep_loss( (string) $sponsor, (int) $event_id, $typed, $drawn );
	}

	/**
	 * The title and the description a Change form's boxes were drawn from, the event's as stored, for
	 * `keep_typed()` to bound what it keeps by the boxes' drawn limits; '' for each when the press was
	 * a plan, whose boxes are drawn empty, or names no event.
	 *
	 * @param int $event_id The event a change was for, or 0 for a plan.
	 * @return array{title: string, description: string}
	 */
	private static function drawn_from( $event_id ) {
		$post = (int) $event_id > 0 ? get_post( (int) $event_id ) : null;

		if ( ! WPCPM_Sponsor_Event::is_event( $post ) ) {
			return array(
				'title'       => '',
				'description' => '',
			);
		}

		return array(
			'title'       => (string) $post->post_title,
			'description' => (string) $post->post_content,
		);
	}

	/**
	 * Refuse a plan or a change whose title or description the text cleaner would take words from
	 * (`loss_check()`), before the form is read for the store, so a refused press looks nothing up
	 * and writes, mails and sends nothing. The first such box is named in the words of the form's
	 * button, Plan the event or Save the changes (`messages()`), and what was typed comes back for
	 * the person who pressed, in this form alone.
	 *
	 * @param string $record   Sponsor key.
	 * @param int    $event_id The event a change is for, or 0 for a plan.
	 * @param array  $posted   What the form posted (`posted_fields()`).
	 * @return string[] The boxes posted back as drawn, for the store to keep as they are.
	 */
	private static function refuse_loss( $record, $event_id, array $posted ) {
		$asked = self::loss_check( $record, $event_id, $posted );

		if ( '' !== $asked['lost'] ) {
			self::leave_typed( ( $event_id > 0 ? 'sevent-change-' : 'sevent-plan-' ) . $asked['lost'] . '-loss', $record, $event_id, $posted );
		}

		return $asked['unedited'];
	}

	/**
	 * Whether the text cleaner would take words from the title or the description of a plan or a
	 * change: "Ages 8 <12 welcome, adults >18 pay" would be stored as `Ages 8 18 pay`. Asked of what
	 * was typed (`WPCPM_Request::posted_raw()`), in the form's order; the first such box is the
	 * answer, and every row of what was typed is then kept for the person who pressed, for this form
	 * alone (`keep_loss()`). The planner's own presses ask it (`refuse_loss()`), and so do the
	 * program's own plan and change on the Administrator Dashboard's card
	 * (`WPCPM_Sponsor_Event_Queue`), with no sponsor, before either reads the form for the store.
	 *
	 * A Change form's box posted back as it was drawn (`unedited()`) is not asked: the event can hold
	 * words the cleaner would take, written by other means, and only what a person changed is theirs
	 * to be told about. The store keeps such a box's text as it holds it (`unedited`,
	 * `WPCPM_Sponsor_Event::change()`).
	 *
	 * @param string $sponsor  The sponsor the form plans for, or '' for the program's own forms.
	 * @param int    $event_id The event a change is for, or 0 for a plan.
	 * @param array  $posted   What the form posted (`posted_fields()`).
	 * @return array{unedited: string[], lost: string} The boxes posted back as drawn, and the first
	 *                                                 box the cleaner would take words from, or ''.
	 */
	public static function loss_check( $sponsor, $event_id, array $posted ) {
		$stored   = (int) $event_id > 0 ? self::drawn_from( (int) $event_id ) : array();
		$typed    = array();
		$unedited = array();

		foreach ( self::TYPED_BOXES as $key => $box ) {
			$typed[ $key ] = WPCPM_Request::posted_raw( $box[0] );

			if ( isset( $stored[ $key ] ) && self::unedited( $box[1], $typed[ $key ], $stored[ $key ] ) ) {
				$unedited[] = $key;
			}
		}

		foreach ( self::TYPED_BOXES as $key => $box ) {
			if ( ! in_array( $key, $unedited, true ) && WPCPM_Typed_Text::cleaner_loses( $typed[ $key ], $box[1] ) ) {
				self::keep_loss( (string) $sponsor, (int) $event_id, array_merge( $posted, $typed ), $stored );

				return array(
					'unedited' => $unedited,
					'lost'     => $key,
				);
			}
		}

		return array(
			'unedited' => $unedited,
			'lost'     => '',
		);
	}

	/**
	 * Whether a box was posted back as the Change form drew it from the event's text, asked of what
	 * was typed: both read trimmed, as the cleaner trims, and the same once every entity is read
	 * (`WPCPM_Typed_Text::same_text()`), each line break one LF for the description
	 * (`WPCPM_Typed_Text::same_lines()`). A title the event holds with a line break is drawn with a
	 * space in its place (`WPCPM_Typed_Text::one_line()`), so it reads as drawn either way.
	 *
	 * @param string $kind   'line' for the title, 'lines' for the description.
	 * @param string $typed  What was posted, as typed.
	 * @param string $stored What the event holds.
	 * @return bool
	 */
	private static function unedited( $kind, $typed, $stored ) {
		$typed  = trim( (string) $typed );
		$stored = trim( (string) $stored );

		if ( 'lines' === $kind ) {
			return WPCPM_Typed_Text::same_lines( $typed, $stored );
		}

		return WPCPM_Typed_Text::same_text( $typed, $stored ) || WPCPM_Typed_Text::same_text( $typed, trim( WPCPM_Typed_Text::one_line( $stored ) ) );
	}

	/**
	 * Keep what was typed into a refused press, for the one time its form is drawn again
	 * (`WPCPM_Typed_Text::keep()`), each row it is given: the title and the description exactly as
	 * typed, each in the room its box was drawn with (`WPCPM_Typed_Text::kept_room()` of the event's
	 * text, or of an empty box for a plan), and any of the other five as the request readers read
	 * them, up to `typed_limits()`. A press refused because the cleaner would take words from it
	 * gives every row of the form (`loss_check()`); one refused for an outcome of `KEEPS_TYPED`
	 * gives the title and the description alone, the other rows waiting on the planner's channel
	 * (`keep_typed()`).
	 *
	 * @param string $record   Sponsor key, or '' for the program's own forms.
	 * @param int    $event_id The event a change was for, or 0 for a plan.
	 * @param array  $typed    Each row as it is to come back.
	 * @param array  $stored   The event's title and description as stored, or none for a plan.
	 */
	private static function keep_loss( $record, $event_id, array $typed, array $stored ) {
		$room = array();

		foreach ( self::typed_limits() as $key => $limit ) {
			$room[ $key ] = isset( self::TYPED_BOXES[ $key ] ) ? WPCPM_Typed_Text::kept_room( isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '', $limit ) : $limit;
		}

		WPCPM_Typed_Text::keep( self::typed_form( $record, $event_id ), array_intersect_key( $typed, $room ), $room );
	}

	/**
	 * The name a press refused because the cleaner would take words keeps its typing under
	 * (`WPCPM_Typed_Text::keep()`): a sponsor's Plan form, or its Change form of one event, named by
	 * the event's post ID, so the typing comes back in the form it was typed in and in no other. The
	 * program's own forms on the Administrator Dashboard's card are named with no sponsor.
	 *
	 * @param string $record   Sponsor key, or '' for the program's own forms.
	 * @param int    $event_id The event a Change form changes, or 0 for the Plan form.
	 * @return string
	 */
	private static function typed_form( $record, $event_id ) {
		return 'sevent:' . $record . ':' . ( (int) $event_id > 0 ? (string) (int) $event_id : 'new' );
	}

	/**
	 * The longest each of the form's values may be and still be kept, in characters: the title's,
	 * the description's and the call link's as the rows' boxes and the store define them
	 * (`WPCPM_Sponsor_Event::MAX_TITLE`, `MAX_DESCRIPTION`, `MAX_LINK`), and for the day, the start
	 * time, the length and the places, whose boxes hold a few characters each, `TYPED_SHORT`. The
	 * keys are the form's, in its order, and `typed()` answers them in it.
	 *
	 * @return array<string, int>
	 */
	private static function typed_limits() {
		return array(
			'date'        => self::TYPED_SHORT,
			'time'        => self::TYPED_SHORT,
			'minutes'     => self::TYPED_SHORT,
			'places'      => self::TYPED_SHORT,
			'title'       => WPCPM_Sponsor_Event::MAX_TITLE,
			'description' => WPCPM_Sponsor_Event::MAX_DESCRIPTION,
			'link'        => WPCPM_Sponsor_Event::MAX_LINK,
		);
	}

	/**
	 * What the person typed into one of the planner's forms, when a refusal of that very form brought
	 * the page back (`keep_typed()`): the form draws it in place of the values it holds otherwise, and
	 * a form in a fold draws the fold open, so the typing is in view. What was kept is the viewing
	 * person's own, and is read once: by the first form a request draws, and every other form that
	 * request draws reads the same, since the flash remembers what a request took. It is answered
	 * only to the form it came from, the same sponsor's, '' for the program's own, and the same
	 * event's, 0 for a plan; any other form drops it unread, and a reload draws the form as it is
	 * drawn with nothing typed.
	 *
	 * @param string $sponsor  The sponsor the form plans for, or '' for the program's own forms.
	 * @param int    $event_id The event a Change form changes, or 0 for a Plan form.
	 * @return array `date`, `time`, `minutes`, `places`, `title`, `description` and `link` as typed,
	 *               or an empty array when nothing was kept for this form.
	 */
	public static function typed( $sponsor, $event_id ) {
		$kept = WPCPM_Flash::take( self::TYPED );

		// Routing, not authorization: which form a person's own typing comes back to.
		if ( ! is_array( $kept ) || ! isset( $kept['sponsor'], $kept['event'], $kept['values'] ) || ! is_array( $kept['values'] ) || 0 !== strcmp( (string) $sponsor, (string) $kept['sponsor'] ) || (int) $event_id !== (int) $kept['event'] ) {
			return array();
		}

		$typed = array();

		foreach ( array_keys( self::typed_limits() ) as $key ) {
			$typed[ $key ] = isset( $kept['values'][ $key ] ) && is_scalar( $kept['values'][ $key ] ) ? (string) $kept['values'][ $key ] : '';
		}

		return $typed;
	}

	/**
	 * What one of the planner's forms comes back with after a refusal of that very form, for
	 * `render_fields()`'s `$kept`: the rows the planner's channel kept (`typed()`), with the title
	 * and the description exactly as typed while that typing waits under the form's own name
	 * (`kept_rows()`); none when neither kept anything for the form. Read once, as both are. The
	 * planner's two forms draw it, and so do the program's own plan and change forms on the
	 * Administrator Dashboard's card (`WPCPM_Sponsor_Event_Queue`), with no sponsor, so the forms
	 * come back alike.
	 *
	 * @param string $sponsor  The sponsor the form plans for, or '' for the program's own forms.
	 * @param int    $event_id The event a Change form changes, or 0 for a Plan form.
	 * @return array By row, what the form draws in place of what it holds otherwise.
	 */
	public static function kept_for( $sponsor, $event_id ) {
		$typed = self::typed( $sponsor, $event_id );

		return self::kept_rows( WPCPM_Typed_Text::kept( self::typed_form( (string) $sponsor, (int) $event_id ) ), $typed );
	}

	/**
	 * Drop what an earlier refusal of one of the planner's forms kept under the form's own name,
	 * because the form has been posted again (`WPCPM_Typed_Text::forget()`): every plan and change
	 * calls it first, the program's own on the Administrator Dashboard's card with no sponsor.
	 *
	 * @param string $sponsor  The sponsor the form plans for, or '' for the program's own forms.
	 * @param int    $event_id The event a Change form changes, or 0 for a Plan form.
	 */
	public static function forget_typed( $sponsor, $event_id ) {
		WPCPM_Typed_Text::forget( self::typed_form( (string) $sponsor, (int) $event_id ) );
	}

	/**
	 * What one of the sponsor's forms comes back with exactly as typed (`render_fields()`'s `$kept`):
	 * what was kept under the form's own name (`WPCPM_Typed_Text::kept()`), and with it, when that is
	 * the title and the description a refusal of `KEEPS_TYPED` kept (`keep_typed()`), the form's
	 * other rows from the planner's channel (`typed()`), whose title and description the cleaner has
	 * read and are left out.
	 *
	 * The typing under the form's name waits `WPCPM_Typed_Text::KEPT_FOR`, and the channel until the
	 * next draw, so a refusal looked at later finds the channel's rows alone: those are drawn without
	 * the title and the description, which then come from the event, as it holds them, or empty for a
	 * plan, and never as the cleaner's text the channel holds for them, which a box left as drawn
	 * would post back as an edit. Nothing when neither kept anything for this form.
	 *
	 * The title and the description alone, with nothing for this form on the channel, are the half of
	 * such a typing whose other rows were dropped unread, by a look at another sponsor's planner, say:
	 * they are dropped too, so the form never comes back half typed.
	 *
	 * @param array $kept  What was kept under the form's name, by row, or none.
	 * @param array $typed What the planner's channel kept for the same form (`typed()`), or none.
	 * @return array
	 */
	private static function kept_rows( array $kept, array $typed ) {
		if ( array() === $kept ) {
			return array_diff_key( $typed, self::TYPED_BOXES );
		}

		if ( array() === $typed && array() === array_diff_key( $kept, self::TYPED_BOXES ) ) {
			return array();
		}

		return array_merge( array_diff_key( $typed, self::TYPED_BOXES ), $kept );
	}

	/**
	 * The clock the form was filled in on: the pressing person's own, the one the form named.
	 *
	 * @return DateTimeZone
	 */
	private static function zone() {
		return WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() );
	}

	/**
	 * The fields' sentences as one detail for the flash, in the form's order: as many whole
	 * sentences as the flash keeps, so none is cut in the middle.
	 *
	 * @param string[] $errors Field => sentence.
	 * @return string
	 */
	private static function detail( array $errors ) {
		$detail = '';

		foreach ( $errors as $sentence ) {
			$longer = trim( $detail . ' ' . $sentence );

			if ( mb_strlen( $longer ) > self::DETAIL_MAX ) {
				break;
			}

			$detail = $longer;
		}

		return $detail;
	}

	/**
	 * Leave with a refusal of the store: its outcome (`status_of()`), and, for a start outside the
	 * term, the day and the clock the store's own words name (`WPCPM_Sponsor_Event::edge_of()`), for
	 * the flash to put into the planner's copy of those words; through `leave_typed()`, so the form
	 * comes back with what was typed when the outcome is one the person puts right by pressing again.
	 *
	 * @param WP_Error $refusal  The store's refusal.
	 * @param string   $record   Sponsor key.
	 * @param array    $row      The sponsor's row, read for its term and the events granted for it.
	 * @param int      $event_id The event a change was for, or 0 for a plan.
	 * @param array    $posted   What the form posted (`posted_fields()`).
	 */
	private static function refused( WP_Error $refusal, $record, array $row, $event_id, array $posted ) {
		$until = self::until_of( $row );
		$extra = WPCPM_Sponsor_Event::extra_events( $row, $until );
		$code  = (string) $refusal->get_error_code();

		self::leave_typed( self::status_of( $refusal, $until, $extra, self::held_for_wait( $code, $record, $until, $extra ) ), $record, $event_id, $posted, '', WPCPM_Sponsor_Event::edge_of( $code, $row ) );
	}

	/**
	 * The outcome a refusal of the store leaves with. A wait is said as the card says it in the
	 * form's place, by the term the sponsor's row holds, the events granted for it and the events it
	 * has held (`wait_status()`). A refusal no form could have caused, or a write that failed, is said
	 * as a failure to try again.
	 *
	 * @param WP_Error $refusal The store's refusal.
	 * @param string   $until   The running term's last day, `Y-m-d`, or '' with no term.
	 * @param int      $extra   The events that term was granted beyond the tier's one.
	 * @param int      $held    The approved events the term has held so far (`held_for_wait()`).
	 * @return string A key of `messages()`.
	 */
	private static function status_of( WP_Error $refusal, $until = '', $extra = 0, $held = 0 ) {
		$wait = self::wait_status( (string) $refusal->get_error_code(), $until, $extra, $held );

		if ( '' !== $wait ) {
			return $wait;
		}

		$statuses = array(
			'switch_off'  => 'tier-off',
			'no_tier'     => 'sevent-no-tier',
			'past_term'   => 'sevent-past-term',
			'before_term' => 'sevent-before-term',
			'in_past'     => 'sevent-in-past',
			'busy'        => 'sevent-busy',
			'fields'      => 'sevent-fields',
			'not_allowed' => 'refused',
			'cancelled'   => 'sevent-was-canceled',
			'past'        => 'sevent-past',
			'shrink'      => 'sevent-shrink',
		);
		$code     = (string) $refusal->get_error_code();

		return isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'sevent-failed';
	}

	/*
	 * --------------------------------------------------------------------
	 * The sponsor's members' mail
	 * --------------------------------------------------------------------
	 */

	/**
	 * Tell the sponsor's live members that their approved event changed or was canceled, each in
	 * their own language and on their own clock, with the calendar file that brings the entry their
	 * approval mail made up to date: a request at the new version for a change, the cancel for a
	 * cancel. The member who pressed is told too, since the file is the point. An event the program
	 * never approved was put in nobody's calendar, and its change or cancel tells the program contact
	 * alone. Sent after the store has told the people on the event. Nobody on the event is named.
	 *
	 * @param string $record   Sponsor key.
	 * @param int    $event_id The event, read again after the store's write.
	 * @param string $what     MAIL_CHANGED or MAIL_CANCELED.
	 * @param string $ground   The ground the claim was made on: a program manager's through the
	 *                         switcher is said as done on the sponsor's behalf.
	 * @return int How many were mailed.
	 */
	private static function mail_members( $record, $event_id, $what, $ground ) {
		$event = get_post( (int) $event_id );

		if ( ! WPCPM_Sponsor_Event::is_event( $event ) ) {
			return 0;
		}

		$facts = WPCPM_Sponsor_Event::facts( $event );

		// The approval's stamp, which a change and a cancel leave in place: only an approved event
		// was ever sent to the members' calendars.
		if ( (int) $facts['decision']['at'] <= 0 ) {
			return 0;
		}

		$sponsor = WPCPM_Sponsor_Event::sponsor_name( $record );
		$manager = WPCPM_Sponsor_Policy::GROUND_MANAGER === $ground;
		$id      = ( self::MAIL_CANCELED === $what ? 'event-canceled-sponsor' : 'event-changed-sponsor' ) . ( $manager ? '-for' : '' );
		$sent    = 0;

		foreach ( WPCPM_Sponsor_Members::members_of( $record ) as $member ) {
			$sent += WPCPM_Mail::send(
				$member,
				$id,
				static function ( WP_User $recipient ) use ( $event, $id, $sponsor ) {
					return self::member_mail( $event, $id, $sponsor, $recipient );
				}
			) ? 1 : 0;
		}

		return $sent;
	}

	/**
	 * One mail to one of the sponsor's members about its approved event, built in their language from
	 * its template (`event-changed-sponsor` or `event-canceled-sponsor`, each with a `-for` copy that
	 * says an Administrator acted on the sponsor's behalf): what happened, the calendar file when there
	 * is one, their own, naming them, and the event on their own clock with the zone named. A change
	 * gives what the event now is: its length, its places and its call link; a cancel names the event
	 * and when it was to be. The title is the one typed, read back by the registry.
	 *
	 * @param WP_Post $event     The event, read after the store's write.
	 * @param string  $id        The template's id.
	 * @param string  $sponsor   The sponsor's name.
	 * @param WP_User $recipient The member.
	 * @return array `subject`, `body`, `attachments` and `cleanup`.
	 */
	private static function member_mail( WP_Post $event, $id, $sponsor, WP_User $recipient ) {
		$facts    = WPCPM_Sponsor_Event::facts( $event );
		$canceled = 0 === strpos( $id, 'event-canceled' );
		$files    = WPCPM_Sponsor_Event::calendar_file( $event, $canceled ? WPCPM_ICS::METHOD_CANCEL : WPCPM_ICS::METHOD_REQUEST, $recipient );
		$values   = WPCPM_Sponsor_Event::mail_values( $facts, WPCPM_Sponsor_Event::link_of( $event ), WPCPM_Mentor_Availability::viewer_timezone( (int) $recipient->ID ), $sponsor );

		$values['calendar_note'] = array() === $files ? '' : ( $canceled
			? __( 'The calendar file attached takes it off your calendar.', 'wpcredits-program-manager' )
			: __( 'The calendar file attached replaces the one you had.', 'wpcredits-program-manager' ) );

		return WPCPM_Mail_Templates::mail(
			$id,
			$values,
			array(
				'attachments' => $files,
				'cleanup'     => $files,
			)
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The program contact's mail
	 * --------------------------------------------------------------------
	 */

	/**
	 * Tell the program contact what happened to the sponsor's event, through the road every mail to
	 * a sponsor's program manager takes: the assigned manager, else the Interest mail addresses, else
	 * every manager. The event is read again after the store's write, so the mail says what it now is.
	 *
	 * @param string $record   Sponsor key.
	 * @param int    $event_id The event.
	 * @param string $what     MAIL_PLANNED, MAIL_CHANGED or MAIL_CANCELED.
	 * @param string $ground   The ground the claim was made on: a program manager's through the
	 *                         switcher is said as done on the sponsor's behalf.
	 * @return int How many were mailed.
	 */
	private static function mail_contact( $record, $event_id, $what, $ground ) {
		$event = get_post( (int) $event_id );

		if ( ! WPCPM_Sponsor_Event::is_event( $event ) ) {
			return 0;
		}

		$sponsor = WPCPM_Sponsor_Event::sponsor_name( $record );
		$facts   = WPCPM_Sponsor_Event::facts( $event );
		$link    = WPCPM_Sponsor_Event::link_of( $event );
		$page    = class_exists( 'WPCPM_Administrators_Dashboard' ) ? (string) WPCPM_Administrators_Dashboard::page_url() : '';
		$id      = self::contact_id( $what, WPCPM_Sponsor_Event::STATE_PLANNED === $facts['state'], WPCPM_Sponsor_Policy::GROUND_MANAGER === $ground );

		// The mail layer hands the builder its recipient: an account for the assigned Administrator or
		// an Administrator, nothing for a bare address the Interest mail setting holds.
		return (int) WPCPM_Sponsor_Interests::mail_manager(
			$record,
			static function ( $recipient = null ) use ( $id, $sponsor, $facts, $link, $page ) {
				return self::contact_mail( $id, $sponsor, $facts, $link, $page, $recipient );
			},
			$id
		);
	}

	/**
	 * The template of the program contact's mail about one event: what happened (planned, changed or
	 * canceled), whether a changed event still waits for approval or stays approved, and whether an
	 * Administrator acted through the switcher, on the sponsor's behalf (`-for`).
	 *
	 * @param string $what    MAIL_PLANNED, MAIL_CHANGED or MAIL_CANCELED.
	 * @param bool   $waiting Whether the event waits for the program's approval.
	 * @param bool   $manager Whether an Administrator acted, through the switcher.
	 * @return string The template's id in the catalog.
	 */
	private static function contact_id( $what, $waiting, $manager ) {
		if ( self::MAIL_CANCELED === $what ) {
			return $manager ? 'event-canceled-for' : 'event-canceled-by-sponsor';
		}

		if ( self::MAIL_CHANGED === $what ) {
			$id = $waiting ? 'event-changed-waiting' : 'event-changed-approved';
		} else {
			$id = 'event-planned';
		}

		return $id . ( $manager ? '-for' : '' );
	}

	/**
	 * The program contact's mail about one event, built in the recipient's language from its template
	 * (`contact_id()`): what happened and who did it, the sponsor or an Administrator on its behalf; the
	 * event on the recipient's own clock (the site's for a bare address) with the zone named, its
	 * length, its places, how many joined once anybody has, the call link and the description; and,
	 * while it waits or stays approved, where the program manages it. Nobody who joined is named. The
	 * title and the description are the ones typed, read back by the registry; the subject names the
	 * sponsor and carries neither.
	 *
	 * @param string       $id        The template's id.
	 * @param string       $sponsor   The sponsor's name.
	 * @param array        $facts     The event's facts, read after the store's write.
	 * @param string       $link      The call link.
	 * @param string       $page      The Administrator Dashboard's address, or '' when it has none.
	 * @param WP_User|null $recipient The account the mail goes to, or null for a bare address.
	 * @return array `subject` and `body`.
	 */
	private static function contact_mail( $id, $sponsor, array $facts, $link, $page, $recipient ) {
		$zone   = $recipient instanceof WP_User ? WPCPM_Mentor_Availability::viewer_timezone( (int) $recipient->ID ) : wp_timezone();
		$values = WPCPM_Sponsor_Event::mail_values( $facts, $link, $zone, $sponsor );

		$values['joined']       = (int) $facts['joined'] > 0 ? number_format_i18n( (int) $facts['joined'] ) : '';
		$values['description']  = (string) $facts['description'];
		$values['events_queue'] = '' === $page ? '' : $page . '#wpcpm-sponsor-events';

		return WPCPM_Mail_Templates::mail( $id, $values );
	}

	/**
	 * Flash and go back to the dashboard, at the Events card. Through the dashboard's own method, by
	 * array callable, as every card does.
	 *
	 * @param string   $status A key of `messages()`.
	 * @param string   $record The sponsor, for the manager switcher; '' to land on the page as is.
	 * @param string   $detail A sentence after the status's own, or ''.
	 * @param string[] $args   The values the status's sentence names, in its order, or none.
	 */
	private static function leave( $status, $record, $detail = '', array $args = array() ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record, $detail, $args );
		exit;
	}

	/**
	 * Leave from a plan or a change whose form was read. What it posted is kept on the planner's
	 * channel for the person who pressed when the outcome is one they put right by pressing again
	 * (`KEEPS_TYPED`), so the form comes back with it once, and the title and the description are
	 * kept beside it exactly as typed, under the form's own name (`keep_typed()`), which the form
	 * draws in place of the channel's two. The channel holds them as the request readers read them,
	 * the cleaner's text, which drops a percent octet such as the "%ca" of "10%cashback" from a box
	 * the event holds it in and that the person left as it was drawn: drawn from it, the box would
	 * post the shorter text back as an edit, and the next press would store it.
	 * For every other outcome, a success among them, that channel keeps nothing, and what an earlier
	 * refusal left on it is dropped. A refusal for words the cleaner would take is one of those
	 * others: it has already kept exactly what was typed under the form's own name (`loss_check()`),
	 * every row of it, which the form draws instead. A press stopped before its form is read keeps
	 * nothing: on the nonce; by the claim, which refuses a stranger, the switch off and a row the site
	 * no longer holds; by `own_event()`'s refusal of a change; or by a sponsor that no longer holds the
	 * planner. Nor does a press past the ceiling, which leaves through `leave()` once the form is read
	 * (`spend_press()`).
	 *
	 * @param string   $status   A key of `messages()`.
	 * @param string   $record   The sponsor.
	 * @param int      $event_id The event a change was for, or 0 for a plan.
	 * @param array    $posted   What the form posted (`posted_fields()`).
	 * @param string   $detail   A sentence after the status's own, or ''.
	 * @param string[] $args     The values the status's sentence names, in its order, or none.
	 */
	private static function leave_typed( $status, $record, $event_id, array $posted, $detail = '', array $args = array() ) {
		self::keep_typed( $status, self::KEEPS_TYPED, $posted, $record, $event_id );
		self::leave( $status, $record, $detail, $args );
	}
}
