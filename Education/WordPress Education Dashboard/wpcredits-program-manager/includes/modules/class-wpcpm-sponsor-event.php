<?php
/**
 * A sponsor's co-hosted event, and the program's own: the store, its allowance, its states and who
 * may join.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per event a sponsor plans with the program (the design's section 18), or the
 * program plans itself (section 20).
 *
 * **The program's own events.** A program manager plans an event of the program's own on the
 * Administrator Dashboard's card (`plan_program()`, `KIND_PROGRAM`): the partners session and the
 * launch communications are such events. It is hosted by the WordPress Credits Program and has no
 * sponsor (`sponsor_of()` answers nobody's key for it, whatever its rows hold, and no sponsor's
 * reader lists it), it is approved as it is planned, and none of a sponsor's rules holds it: no
 * tier, no term, no one event at a time, a start still to come the one condition. It is listed and
 * joined, changed, canceled and reminded as a sponsor's approved event is, offered while it is
 * approved (`offers_events()`), to the same four audiences, a sponsor's own members among them,
 * since it is nobody's own; its writes take one lock of their own (`PROGRAM_LOCK`), and its rows on
 * the audit trail are the program's (`WPCPM_Institution_Audit::record_program()`), naming no sponsor.
 * Everything below about a sponsor's event holds for it where it names no sponsor.
 *
 * **One kind, one at a time, and one a term unless the program grants more.** An Approved Champion
 * or Partner plans its co-hosted webinar or event here and nothing else. A sponsor has at most one
 * event still to take place, planned or approved; while its term runs, its approved events dated in
 * that term spend it once they are the tier's one and as many more as the Sponsors screen's Tier
 * box granted for that term (`extra_events()`), and no event may be dated after the term's last
 * day; a sponsor with no term waits only for its last event to take place. `may_plan()` answers
 * which of these stands in the way, as a key the planner puts in words.
 *
 * **A term as the row records it.** A term is the twelve months ending on its last day. A renewal
 * made before a term ends leaves that term running to its own last day, so a start before the
 * renewed term's first day as the row records it, the day after the end of the term before
 * (`term_from()`), is counted in the term before, and an event of the renewed term is never moved
 * back into it.
 *
 * **Planned, approved, cancelled.** An event waits for a program manager before anybody but the
 * sponsor sees it, because it carries the program's name. A manager approves it while its sponsor
 * still offers events (`offers_events()`), or returns it with a note, which cancels it; the sponsor's
 * members and the managers change it or cancel it. A change keeps the state, so the program approves
 * an event once. An event whose end has passed takes no move at all. Every move writes one row of its
 * own kind on the sponsors module's audit trail.
 *
 * **The lock and the version.** Every write happens under a lock per sponsor, and one for the
 * program's own events (`lock_of()`), in the group sessions' shape: `add_option()` as the claim,
 * the moment it was taken as its value, and a lock older than `LOCK_TIMEOUT` taken over as one a
 * dead request left behind; the held value is read from the options table itself, past a request's
 * own copy of the options, and like theirs it is not a strict mutex (see `lock()`). The event is
 * read again under the lock. A plan starts its calendar version at 0 and every write after it
 * raises the version, a join and a leave included, so each calendar file sent about the event
 * outranks the one before.
 *
 * **Who may join.** Everyone the program knows who is signed in: current students, Active mentors,
 * program managers and institution contacts, each read by the module that owns the question. A
 * sponsor's own members are no audience of their own event: they planned it, and it is on their
 * card. An event is offered only while its sponsor is in the program (Approved) and its tier
 * includes events: one the Sponsors screen sets to Paused, one whose term has lapsed and one the
 * store no longer holds offer nothing, and a sponsor that still has its card keeps the event there.
 * The people already on such an event keep it on their own lists, to leave it: nobody is left on an
 * event they cannot get off until a program manager cancels it.
 *
 * **Names and the link stay where they belong.** A joiner is one meta row holding a user ID, and
 * the call link a meta row of its own that `link_of()` alone reads: `facts()` carries neither, the
 * audit rows carry the start, the length and the counts, and `held_in()`, which the annual report
 * reads, carries titles and counts. `bin/test-sponsor-events.php` walks the audit rows and
 * `held_in()` for a joiner's name, the link and the description, and checks `facts()` for the link.
 * The Partners page reads `public_upcoming()` alone: a title, a host, when and for how long,
 * and the description with every http, https and mailto address taken out, since it is free text a
 * sponsor may paste the link into; never the link, nobody on the event, by name or by count, and no
 * places. The suite walks what it gives for each of them.
 *
 * **The people on it are told here, fifty in the press and the rest by cron.** A change and a
 * cancel are the store's moves, made from the Sponsor Dashboard and the Administrator Dashboard
 * alike, so the store tells each person on the event, with the calendar file that brings their
 * calendar in line: a person is told once whichever page the move was made on. A change tells them
 * only when it moves what their calendar holds the event for, its time, its length, its title or its
 * link, as the group sessions tell theirs only of a new time; the description and the places are
 * news for the sponsor's members and the program contact alone, and a change that alters nothing
 * writes nothing. An event open to everyone may hold hundreds of people, more than one request can
 * mail before a host cuts it off, so a move writes each of them down as a row still to tell
 * (`META_PENDING`), puts the drain on the clock and tells the first `TELL_IN_REQUEST` itself; the
 * drain, one cron event (`drain()`), tells as many again a run until no row is left. Each row comes
 * off the event before its mail is handed off, the invitation queue's order, so a request that dies
 * part way tells nobody twice and leaves whoever it had not reached to the next run. A drain that
 * runs once the event is over sends no change, a new time for something past being noise, and
 * still sends a cancel, which takes the entry off the person's calendar. The daily run and the
 * plugin's activation put a drain whose event the clock lost back on it (`resume_drain()`), so
 * rows wait a day at most. `change()` and `cancel()` answer how many they told in the press and
 * how many are left.
 *
 * **The day before, the daily run reminds them.** While the tiers are live, the daily run asks
 * `queue_reminders()` once a day, and every approved event of a sponsor that still offers events, or
 * of the program's own, that starts in the run's window, normally from `REMIND_FROM` to
 * `REMIND_UNTIL` hours after the run and reaching back to where the last complete window stopped
 * (`OPT_REMINDER_EDGE`) after a run that fired late, is written down the same way: a row of the
 * reminder's kind for each person on it and each of the sponsor's live members (the program's own
 * event has none), a person who is both once, told by the drain and never in the run itself. An
 * event whose lock another write holds is left to one retry of the run five minutes later
 * (`CRON_RETRY`). The event is marked with the version reminded
 * (`META_REMINDED`), and a marked event is never reminded again, whatever becomes of it: a change's
 * own mail gives its people the new time. The store sends nothing else: every other mail to the
 * sponsor's members, and the program contact's, is the pages' to send.
 *
 * This file stores and reads: it draws nothing and reads no request. Whether a person may plan,
 * change or decide is the caller's to ask, through the roster's claim or the management capability;
 * who may join is the one question answered here, because who may join is who the event is for.
 * `bin/test-sponsor-policy.php` scans this file, with every other sponsor file, for a sponsor ID
 * compared with `===`.
 */
final class WPCPM_Sponsor_Event {

	/**
	 * The post that holds one event.
	 *
	 * Nineteen characters, inside the twenty `register_post_type()` accepts: a longer name is refused
	 * with an error nothing reads, and the type silently does not exist. `bin/test-roles.php`
	 * measures every name this plugin declares.
	 *
	 * @var string
	 */
	const POST_TYPE = 'wpcpm_sponsor_event';

	/** The sponsor the event is for: its key, written once, and the key its events are found by. An event of the program's own has none. */
	const META_SPONSOR = '_wpcpm_sevent_sponsor';

	/** When the event starts, a UTC timestamp. */
	const META_START = '_wpcpm_sevent_start';

	/** When it ends, a UTC timestamp: the start and the minutes, kept so a query can ask what is not over. */
	const META_END = '_wpcpm_sevent_end';

	/** The planner's clock, by name: the one the day and the time were entered on. */
	const META_ZONE = '_wpcpm_sevent_zone';

	/** How long it runs, in minutes. */
	const META_MINUTES = '_wpcpm_sevent_minutes';

	/** How many people may join; 0 for no limit. */
	const META_PLACES = '_wpcpm_sevent_places';

	/** Which kind of event, written once: `KIND_CO_HOSTED`, the one kind a sponsor plans, or `KIND_PROGRAM`, the program's own. */
	const META_KIND = '_wpcpm_sevent_kind';

	/** The call link: a row of its own, read by `link_of()` alone, so nothing that reads the facts carries it by accident. */
	const META_LINK = '_wpcpm_sevent_link';

	/** `planned`, `approved` or `cancelled`. */
	const META_STATE = '_wpcpm_sevent_state';

	/** The program's decision, `array( 'at' => int, 'by' => int, 'note' => string )`: written by an approval and by a return. */
	const META_DECISION = '_wpcpm_sevent_decision';

	/** The event's calendar version: the SEQUENCE of every calendar file sent about it. */
	const META_REVISION = '_wpcpm_sevent_revision';

	/** One row per person on the event, holding their user ID: a join adds one, a leave removes it. */
	const META_PERSON = '_wpcpm_sevent_person';

	/**
	 * One row per person still to be told, `<user ID>:<version>:<kind>`: of the event's last change
	 * of the time, the length, the title or the link (`ROW_CHANGE`), of its cancel (`ROW_CANCEL`), or,
	 * the day before it, that it is coming up (`ROW_REMINDER`). The kind is the row's, what the person
	 * is told, and has nothing to do with the event's own kind (`META_KIND`). The version is the one
	 * the move raised, or for a reminder the event's as it was reminded, so a row a later move
	 * replaced is never taken for its own. A row written before rows carried a kind,
	 * `<user ID>:<version>`, is the change or the cancel the event's state says, as it always was
	 * (`row_kind()`). Written under the lock, by the move or by the daily run, and taken off by
	 * whoever tells the person, just before.
	 */
	const META_PENDING = '_wpcpm_sevent_pending';

	/**
	 * The version the event was at when its people were written down to be reminded of it, the day
	 * before it: written once, and an event that holds it, whatever version it names, is never
	 * reminded again.
	 */
	const META_REMINDED = '_wpcpm_sevent_reminded';

	/** Waiting for a program manager; nobody but the sponsor sees it. */
	const STATE_PLANNED = 'planned';

	/** Approved by a program manager: open to join until it is over. */
	const STATE_APPROVED = 'approved';

	/** Canceled by the sponsor or a manager, or returned with a note. A key, spelled as the design spells it. */
	const STATE_CANCELLED = 'cancelled';

	/** The co-hosted webinar or event, the one kind a sponsor plans. */
	const KIND_CO_HOSTED = 'co_hosted';

	/**
	 * An event the program plans itself, with no sponsor: hosted by the WordPress Credits Program,
	 * planned by a program manager and approved as it is planned (`plan_program()`).
	 */
	const KIND_PROGRAM = 'program';

	/** Shortest an event may run, in minutes. */
	const MIN_MINUTES = 15;

	/** Longest an event may run, in minutes. */
	const MAX_MINUTES = 480;

	/** Most places an event may have: a ceiling on a number typed by hand, not a judgment about size. */
	const MAX_PLACES = 500;

	/** Longest title, in characters. */
	const MAX_TITLE = 120;

	/** Longest description, in characters. */
	const MAX_DESCRIPTION = 2000;

	/** Longest call link, in characters. */
	const MAX_LINK = 500;

	/**
	 * Longest note a return or a cancel takes, in characters as its box counts them
	 * (`WPCPM_Typed_Text::box_length()` of what was typed).
	 */
	const MAX_NOTE = 2000;

	/**
	 * How many of the people on an event a change or a cancel tells in the press that made it, and
	 * the drain in a run: the group sessions' largest, the number one request is already trusted to
	 * mail, while an event's own ceiling is the program's whole audience.
	 */
	const TELL_IN_REQUEST = 50;

	/** The cron event that tells the people a move, or the daily run's reminders, left on its event: one single event, put on the clock again while rows remain. */
	const CRON_DRAIN = 'wpcpm_sevent_drain';

	/** How long after a press, or a run of the drain, the next run is due, in seconds: long enough for the press's own sends to finish first. */
	const DRAIN_GAP = 60;

	/** The cron event that runs the reminders again after a run found a sponsor's lock held: one single event, and one at a time. */
	const CRON_RETRY = 'wpcpm_sevent_remind_retry';

	/** How long after such a run its retry is due, in seconds: past the two minutes a lock is honored, so by then the lock is free or taken over. */
	const RETRY_GAP = 300;

	/**
	 * The nearest start the daily run reminds people of, in hours after the run, on the daily
	 * cadence: an event that starts sooner was the run before's to remind. A run that fires late
	 * starts its window sooner, where the run before's stopped (`OPT_REMINDER_EDGE`).
	 */
	const REMIND_FROM = 12;

	/**
	 * The furthest start, in hours after the run and not included: the next run, a day later, reminds
	 * people of what starts from then on, so runs a day apart share no event and miss none.
	 */
	const REMIND_UNTIL = 36;

	/**
	 * The option that keeps the reminders' edge, a timestamp: where the last window every event of
	 * which was written down stopped, which the next run's window starts from when that is sooner
	 * than `REMIND_FROM` hours ahead (`reminder_window()`). A run that left an event to its retry
	 * records where its own window began instead.
	 */
	const OPT_REMINDER_EDGE = 'wpcpm_sevent_reminder_edge';

	// What the people on an event are told became of it, or that it is coming up.
	const MAIL_CHANGED  = 'changed';
	const MAIL_CANCELED = 'canceled';
	const MAIL_REMINDED = 'reminded';

	// What a row still to tell is about (`META_PENDING`): the row's own kind, not the event's.
	const ROW_CHANGE   = 'change';
	const ROW_CANCEL   = 'cancel';
	const ROW_REMINDER = 'reminder';

	// What a change alters, as `what_changed()` answers it: what a calendar holds the event for, or
	// the description or the places alone.
	const CHANGED_CALENDAR = 'calendar';
	const CHANGED_DETAILS  = 'details';

	// The kinds of an event's rows on the sponsors module's audit trail.
	const LOG_PLANNED   = 'sponsor_event_planned';
	const LOG_APPROVED  = 'sponsor_event_approved';
	const LOG_RETURNED  = 'sponsor_event_returned';
	const LOG_CHANGED   = 'sponsor_event_changed';
	const LOG_CANCELLED = 'sponsor_event_cancelled';

	/** The option one sponsor's lock is held as: this prefix and the sponsor's key, or `PROGRAM_LOCK` for the program's own events. */
	const LOCK_PREFIX = 'wpcpm_sevent_lock_';

	/**
	 * The name the program's own events are locked under, after `LOCK_PREFIX`: the option
	 * `wpcpm_sevent_lock_program`, one lock for all of them, as they share one host. No sponsor's
	 * key can be it, a key being `rec` or `spo` and fourteen letters or digits.
	 */
	const PROGRAM_LOCK = 'program';

	/**
	 * How long a lock is honored, in seconds.
	 *
	 * Two minutes: a write here is a handful of rows, so a lock that old belonged to a request that
	 * died between taking it and giving it back, and left in place it would keep a sponsor's events,
	 * or the program's own, shut to every join and change.
	 */
	const LOCK_TIMEOUT = 120;

	// Who an event is for, as `audience_of()` answers.
	const AUDIENCE_STUDENTS     = 'students';
	const AUDIENCE_MENTORS      = 'mentors';
	const AUDIENCE_MANAGERS     = 'managers';
	const AUDIENCE_INSTITUTIONS = 'institutions';

	/**
	 * Stand-in for `time()`. Null means the real clock. See for_tests().
	 *
	 * @var callable|null
	 */
	private static $clock = null;

	/*
	 * --------------------------------------------------------------------
	 * Registration
	 * --------------------------------------------------------------------
	 */

	/**
	 * Hooks: the post type and its meta, both on `init` at 5, the sponsors store's own priority,
	 * ahead of anything that could query an event on the same request. The meta after the type. And
	 * the drain and the reminders' retry, each on its cron event.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 5 );
		add_action( self::CRON_DRAIN, array( __CLASS__, 'drain' ) );
		add_action( self::CRON_RETRY, array( __CLASS__, 'queue_reminders' ) );
	}

	/**
	 * Register the event post type.
	 *
	 * The sponsor report's arguments: not public, not queryable, not in search, no screen, no menu,
	 * not in REST, and a capability type nothing is granted, so the one way to an event is this
	 * class. A title and an editor, the description being the post's content. Private from the
	 * first insert, never published: an event's author is a sponsor's member, and a published row
	 * would hand out their login through the author archive.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Sponsor events', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Sponsor event', 'wpcredits-program-manager' ),
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
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => array( self::POST_TYPE, self::POST_TYPE . 's' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register every key the store writes: typed, out of REST, and out of reach of the meta API,
	 * `auth_callback` said rather than inherited, so the one road to a value is this class. Single
	 * values but for the one row per person and the one per person still to tell.
	 */
	public static function register_meta() {
		foreach ( self::meta_types() as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => ! in_array( $key, array( self::META_PERSON, self::META_PENDING ), true ),
					'show_in_rest'  => false,
					'auth_callback' => '__return_false',
				)
			);
		}
	}

	/**
	 * Replace the clock. Test-only: bin/test-sponsor-events.php holds "now" where it needs it, so a
	 * term's last day written into the suite stays ahead of the clock however long the suite lives.
	 *
	 * @param callable|null $clock Answers the current Unix time; null puts the real clock back.
	 */
	public static function for_tests( $clock = null ) {
		self::$clock = is_callable( $clock ) ? $clock : null;
	}

	/*
	 * --------------------------------------------------------------------
	 * Reading the planner's form
	 * --------------------------------------------------------------------
	 */

	/**
	 * The six fields of the planner's form, cleaned, and why any of them is refused.
	 *
	 * The day and the start time are read on the planner's clock and answered as a UTC moment,
	 * through the helpers the group sessions read theirs with: `date_string()` and `time_string()`
	 * for the shape, then `plan_dates()` for the moment, which refuses an hour the clocks skip on
	 * that day rather than rolling it forward. Whether the moment has passed is not asked here: it
	 * is the allowance's question (`in_past`), asked again under the lock with the rest, so this
	 * stays a reader of what was posted.
	 *
	 * The places are blank for no limit. The link is kept as it was typed, never passed through the
	 * text cleaner, which strips percent-encoded characters and would break a meeting link's query;
	 * it has to be an http or https address core would make a request to, at most `MAX_LINK`
	 * characters.
	 *
	 * @param array        $posted The form's values, unslashed: `date` (`Y-m-d`), `time` (`H:i`),
	 *                             `minutes`, `places`, `title`, `description` and `link`.
	 * @param DateTimeZone $zone   The planner's clock.
	 * @return array `fields`: `start` (a UTC timestamp, 0 when the day or the time is refused),
	 *               `minutes`, `places` (0 for no limit), `title`, `description`, `link` (cleaned,
	 *               '' when refused) and `zone` (the clock's name), to be stored only when `errors`
	 *               is empty; `errors`: field => sentence, in the form's order.
	 */
	public static function fields_from( array $posted, DateTimeZone $zone ) {
		$errors = array();
		$date   = WPCPM_Mentor_Availability::date_string( self::posted_text( $posted, 'date' ) );
		$time   = WPCPM_Mentor_Availability::time_string( self::posted_text( $posted, 'time' ) );
		$start  = 0;

		if ( '' === $date ) {
			$errors['date'] = __( 'Choose the day your event takes place.', 'wpcredits-program-manager' );
		}

		if ( '' === $time ) {
			$errors['time'] = __( 'Choose the time your event starts.', 'wpcredits-program-manager' );
		}

		if ( '' !== $date && '' !== $time ) {
			// No moment a start has to be after: whether it has passed is the allowance's to say.
			$planned = WPCPM_Group_Sessions::plan_dates( array( $date ), $time, $zone, PHP_INT_MIN );

			if ( '' === $planned['refused'] && isset( $planned['starts'][0] ) ) {
				$start = (int) $planned['starts'][0];
			} else {
				$errors['time'] = __( 'That time does not exist on that day in your time zone, because the clocks change that night. Choose another time.', 'wpcredits-program-manager' );
			}
		}

		$values = array(
			'start'       => $start,
			'minutes'     => self::whole( self::posted_text( $posted, 'minutes' ), 0 ),
			'places'      => self::whole( self::posted_text( $posted, 'places' ), 0 ),
			'title'       => self::posted_text( $posted, 'title' ),
			'description' => self::posted_lines( $posted, 'description' ),
			'link'        => self::posted_link( $posted, 'link' ),
			'zone'        => $zone->getName(),
		);

		$errors         = array_merge( $errors, self::problems( $values ) );
		$values['link'] = self::clean_link( $values['link'] );

		return array(
			'fields' => $values,
			'errors' => $errors,
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The allowance
	 * --------------------------------------------------------------------
	 */

	/**
	 * Why a sponsor may not plan an event now, or '' when it may.
	 *
	 * In this order: `switch_off` while the tiers are not live; `no_tier` for a sponsor that is not
	 * Approved, asked before its tier is read, and for one whose effective tier does not include the
	 * co-hosted event (a Backer, a Partner whose term has lapsed, a key the store does not hold);
	 * `one_open` while an event of the sponsor's is planned or approved and not over, asked before any
	 * term is counted; `term_used` while a term runs and the approved events dated in the term the
	 * start falls in (`counted_term()`) are as many as that term holds: the one the tier includes and
	 * the extra ones the Sponsors screen's Tier box granted for that term (`extra_events()`). The term
	 * is the running term, or, for a start before its first day as the row records it, the term a
	 * renewal moved off, which runs to its own last day and counts the extra events the row still
	 * names it for; with no start, the running term. `past_term` for a start after the term's last day
	 * while a term runs; `in_past` for a start that has come. A sponsor with no term is bound by
	 * `one_open` alone.
	 *
	 * The status comes first because the planner is a tier's benefit, and only an Approved sponsor
	 * holds one: a sponsor the Sponsors screen sets to Paused, or one that left the program, keeps its
	 * tier and its term on its row, and would otherwise plan on them. Its events are not listed either
	 * (`open_for()`).
	 *
	 * A canceled event counts for nothing, returned or not, and so does a planned one whose day
	 * passed without an approval: it never took place under the program's name, and a sponsor would
	 * otherwise lose its term to a decision nobody made.
	 *
	 * Days are the site's: a term's last day, today and the day an event is dated on are read on the
	 * one clock `WPCPM_Sponsor_Tiers::today()` reads.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row, read for `status`, `tier`, `tier_until` and
	 *                       `event_allowance`, and for the term before as `term_from()` reads it:
	 *                       `report_owed`, `report_drafted_for` and `record_id`.
	 * @param int    $start  The planned start, a UTC timestamp; 0 asks the sponsor's questions alone,
	 *                       for a form not filled in yet.
	 * @param string $today  `Y-m-d`, or '' for today.
	 * @return string '' or one of the keys above.
	 */
	public static function may_plan( $record, array $row, $start, $today = '' ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return 'switch_off';
		}

		$record = trim( (string) $record );
		$day    = is_scalar( $today ) && '' !== (string) $today ? (string) $today : WPCPM_Sponsor_Tiers::today();

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) || ! self::is_approved( $row ) || ! self::tier_offers( $row, $day ) ) {
			return 'no_tier';
		}

		$now   = self::now();
		$start = is_numeric( $start ) ? (int) $start : 0;
		$until = self::running_until( $row, $day );
		$term  = self::counted_term( $row, $until, $start );
		$held  = 0;

		// Read fresh: under the lock this is the question that decides a write, and a state another
		// request changed since this one first read the event must not answer it.
		foreach ( self::events_of( $record, true ) as $event ) {
			$state = self::state_of( $event );

			if ( ! in_array( $state, array( self::STATE_PLANNED, self::STATE_APPROVED ), true ) ) {
				continue;
			}

			if ( self::end_of( $event ) > $now ) {
				return 'one_open';
			}

			if ( '' !== $term['from'] && self::STATE_APPROVED === $state && self::within( self::day_of( self::start_of( $event ) ), $term['from'], $term['until'] ) ) {
				++$held;
			}
		}

		// The extra events counted are the ones granted for the term the start falls in, named by its
		// last day: after a renewal made before a term ended, a number granted for the renewed term
		// must not open a second event in the days the term before still runs, nor one granted for
		// the term before in the renewed term.
		if ( $held >= 1 + self::extra_events( $row, $term['until'] ) ) {
			return 'term_used';
		}

		return $start > 0 ? self::start_refusal( $until, $start, $now ) : '';
	}

	/**
	 * How many co-hosted events a term holds beyond the one the sponsor's tier includes: the number
	 * the Sponsors screen's Tier box granted, while the row's allowance names that term's last day,
	 * and 0 for any other day, a value that is no day, and a row granted nothing.
	 *
	 * The Tier box keeps the number with the term's last day as its save leaves it, so a renewal,
	 * which moves the day on, leaves the number with the term it was granted for. That term goes on
	 * counting it for the days it still runs after a renewal made before it ended; the renewed term
	 * counts it for nothing until the box grants a number for it, which takes its place, since the
	 * row holds one number at a time. Not the planner's own `allowance()`, which is `may_plan()`'s
	 * answer for a sponsor with no start yet: this is the count that answer weighs a term's approved
	 * events against, and what the Events card and the planner's form say a term allows.
	 *
	 * @param array  $row   The sponsor's row, read for `event_allowance`.
	 * @param string $until The last day of the term asked about, `Y-m-d`.
	 * @return int
	 */
	public static function extra_events( array $row, $until ) {
		$until = is_scalar( $until ) ? (string) $until : '';
		$given = isset( $row['event_allowance'] ) && is_array( $row['event_allowance'] ) ? $row['event_allowance'] : array();
		$day   = isset( $given['until'] ) && is_scalar( $given['until'] ) ? trim( (string) $given['until'] ) : '';
		$count = isset( $given['extra'] ) ? $given['extra'] : 0;

		// A number, or its digits as a row written by hand holds them, as the store's shape reads it.
		if ( is_string( $count ) ) {
			$count = ctype_digit( trim( $count ) ) ? (int) trim( $count ) : 0;
		}

		return is_int( $count ) && $count > 0 && '' !== $day && $day === $until && WPCPM_Sponsor_Tiers::is_date( $until ) ? $count : 0;
	}

	/**
	 * The first day of a sponsor's running term as its row records it: the day after the end of the
	 * term before, or '' when nothing records that end.
	 *
	 * A renewal is a program manager moving the last day, often before the term ends, as the
	 * thirty-day mail asks, and the term it moved off still runs to its own last day: where the
	 * renewed term starts is known only from where the term before ended. Three records keep that
	 * end, each for the annual report: the term a renewal moved off, owed its report (`report_owed`,
	 * which the Edit save writes and a report for that day clears); the term the daily run last
	 * drafted a report for (`report_drafted_for`); and the last day of the sponsor's latest report,
	 * the one record left when a manager drafted the term's report by hand before renewing, since the
	 * save then owes nothing. The latest of them before the term's last day is the end. One on or
	 * after it is not: a day moved earlier to correct it leaves the later day owed until Not a
	 * renewal clears it. Nor is a day that does not exist.
	 *
	 * @param array $row The sponsor's row, read for `tier_until`, `report_owed`, `report_drafted_for`
	 *                   and `record_id`.
	 * @return string `Y-m-d`, or ''.
	 */
	public static function term_from( array $row ) {
		$end = self::end_before( $row );

		return '' === $end ? '' : self::day_after( $end );
	}

	/**
	 * The first day a change may move an event to, or '' when its term sets none: the running term's
	 * first day as the row records it (`term_from()`), for an event dated on or after that day on the
	 * site's clock. An event of the renewed term keeps to it, since moved back it would land in the
	 * term before, which a renewal leaves running and which may have held its own event already; an
	 * event of the term before is bound by nothing here, since that term is the one it was planned
	 * in. `change()` refuses a start before it (`before_term`). The planner's Change form starts its
	 * date box on it when it is later than the site's today, though never after the day the event is
	 * drawn on, so the form as drawn can always be saved. The box reads the viewer's clock and the
	 * store the site's: for a viewer west of the site, an event early on this day on the site's clock
	 * is drawn on the day before, which the box then offers, and a start on that day still before
	 * this one on the site's clock is refused, the refusal naming this day and the site's clock
	 * (`edge_of()`).
	 *
	 * @param WP_Post $event The event.
	 * @param array   $row   The sponsor's row.
	 * @return string `Y-m-d`, or ''.
	 */
	public static function change_floor( WP_Post $event, array $row ) {
		$from = self::term_from( $row );

		return '' !== $from && strcmp( self::day_of( self::start_of( $event ) ), $from ) >= 0 ? $from : '';
	}

	/**
	 * What a refusal of a start outside the term names beside its words: the day it is refused
	 * against, in the site's date format, and the site's clock by name. A term's days are the
	 * site's while a planner types the day and the time on their own clock, so a planner west of
	 * the site who picks the term's last day late in their evening is already past it on the site's
	 * clock, and the sentence says which clock decided. `past_term` names the last day of the term as
	 * recorded, `before_term` the running term's first day as the row records it (`term_from()`):
	 * the store answers neither without that day.
	 *
	 * @param string $key A refusal's key.
	 * @param array  $row The sponsor's row.
	 * @return string[] The day and the clock, in that order, for `past_term` and `before_term`; an
	 *                  empty list for any other key.
	 */
	public static function edge_of( $key, array $row ) {
		if ( 'past_term' === $key ) {
			$day = self::term_until( $row );
		} elseif ( 'before_term' === $key ) {
			$day = self::term_from( $row );
		} else {
			return array();
		}

		return array(
			'' === $day ? '' : WPCPM_Sponsor_Tiers::format_day( $day ),
			WPCPM_Mentor_Availability::zone_label( wp_timezone_string() ),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The states
	 * --------------------------------------------------------------------
	 */

	/**
	 * Plan an event for a sponsor: a planned post, written under the sponsor's lock once the
	 * allowance has been asked again there, and its audit row.
	 *
	 * Whether the person may plan for the sponsor is the caller's claim to make; the ground it was
	 * made on is what the row records. The post and its text meta are slashed, because WordPress
	 * unslashes both on the way in: a description with a backslash in it would otherwise lose it. The
	 * title and the description are handed over as `WPCPM_Typed_Text::insert_text()` writes them, so
	 * kses keeps every word of a member's; `plan_program()` and `change()` hand them over the same
	 * way.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $fields  As `fields_from()` answers them: `start`, `minutes`, `places`, `title`,
	 *                        `description`, `link`, `zone`.
	 * @param int    $user_id The person planning.
	 * @param string $ground  `member` or `manager`, from the claim.
	 * @return int|WP_Error The event's post ID, or the refusal: `not_allowed`, `fields`, `busy`,
	 *                      `not_saved`, or one of `may_plan()`'s keys.
	 */
	public static function plan( $record, array $fields, $user_id, $ground ) {
		$record  = trim( (string) $record );
		$user_id = is_numeric( $user_id ) ? (int) $user_id : 0;

		if ( $user_id <= 0 || ! in_array( $ground, array( WPCPM_Sponsor_Policy::GROUND_MANAGER, WPCPM_Sponsor_Policy::GROUND_MEMBER ), true ) ) {
			return self::refuse( 'not_allowed' );
		}

		$values = self::normalize( $fields );

		if ( null === $values ) {
			return self::refuse( 'fields' );
		}

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return self::refuse( 'no_tier' );
		}

		if ( ! self::lock( $record ) ) {
			return self::refuse( 'busy' );
		}

		$row     = WPCPM_Sponsors_Index::row( $record );
		$row     = is_array( $row ) ? $row : array();
		$refusal = self::may_plan( $record, $row, $values['start'] );

		if ( '' !== $refusal ) {
			self::unlock( $record );

			return self::refuse( $refusal, $row );
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_author'  => $user_id,
					'post_title'   => WPCPM_Typed_Text::insert_text( $values['title'] ),
					'post_content' => WPCPM_Typed_Text::insert_text( $values['description'] ),
					// Nothing finds an event by its slug; a neutral one keeps the title out of it.
					'post_name'    => sanitize_title( 'sevent-' . $record ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::unlock( $record );

			return self::refuse( 'not_saved' );
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_SPONSOR, $record );
		update_post_meta( $post_id, self::META_KIND, self::KIND_CO_HOSTED );
		self::write_fields( $post_id, $values );
		update_post_meta( $post_id, self::META_STATE, self::STATE_PLANNED );
		update_post_meta( $post_id, self::META_REVISION, 0 );

		// Released before the row is written: the row is the trail's, and nothing of the event waits on it.
		self::unlock( $record );

		self::audit( self::LOG_PLANNED, $post_id, $user_id, $ground );

		return $post_id;
	}

	/**
	 * Plan an event of the program's own: a post written by a program manager, hosted by the
	 * WordPress Credits Program with no sponsor, and approved as it is planned, the decision stamped
	 * with the moment and the manager; written under the program's lock (`PROGRAM_LOCK`), and its row
	 * on the audit trail through the audit's writer of the program's own rows, naming no sponsor.
	 *
	 * None of a sponsor's rules holds it: no tier, no term, and no one event at a time, so the program
	 * may plan as many as it holds. The switch and the start are asked under the lock, as a sponsor's
	 * plan asks its allowance there: nothing is planned while Sponsor tiers is off, every surface of
	 * the events waiting for it, and a start has to be still to come. Nobody is told: an approved
	 * event is told of by being listed, and a person who joins it is mailed then. Whether the page
	 * that pressed may plan for the program is the caller's to ask; the person is asked again here,
	 * since the row records the manager's ground and a program manager's alone is true of this plan.
	 * The post and its text meta are slashed, as `plan()` slashes them.
	 *
	 * @param array $fields  As `fields_from()` answers them: `start`, `minutes`, `places`, `title`,
	 *                       `description`, `link`, `zone`.
	 * @param int   $user_id The program manager planning.
	 * @return int|WP_Error The event's post ID, or the refusal: `not_allowed` (somebody who does not
	 *                      manage the program), `fields`, `busy`, `switch_off`, `in_past` or
	 *                      `not_saved`.
	 */
	public static function plan_program( array $fields, $user_id ) {
		$user_id = is_numeric( $user_id ) ? (int) $user_id : 0;

		if ( $user_id <= 0 || ! user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return self::refuse( 'not_allowed' );
		}

		$values = self::normalize( $fields );

		if ( null === $values ) {
			return self::refuse( 'fields' );
		}

		if ( ! self::lock( self::PROGRAM_LOCK ) ) {
			return self::refuse( 'busy' );
		}

		$refusal = WPCPM_Sponsor_Tiers::live() ? self::start_refusal( '', $values['start'], self::now() ) : 'switch_off';

		if ( '' !== $refusal ) {
			self::unlock( self::PROGRAM_LOCK );

			return self::refuse( $refusal );
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_author'  => $user_id,
					'post_title'   => WPCPM_Typed_Text::insert_text( $values['title'] ),
					'post_content' => WPCPM_Typed_Text::insert_text( $values['description'] ),
					// Nothing finds an event by its slug; a neutral one keeps the title out of it.
					'post_name'    => sanitize_title( 'sevent-' . self::KIND_PROGRAM ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::unlock( self::PROGRAM_LOCK );

			return self::refuse( 'not_saved' );
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_KIND, self::KIND_PROGRAM );
		self::write_fields( $post_id, $values );
		update_post_meta( $post_id, self::META_STATE, self::STATE_APPROVED );
		update_post_meta( $post_id, self::META_DECISION, self::decision( $user_id, '' ) );
		update_post_meta( $post_id, self::META_REVISION, 0 );

		// Released before the row is written, as a sponsor's plan releases its lock.
		self::unlock( self::PROGRAM_LOCK );
		self::refresh_public_page();

		self::audit( self::LOG_PLANNED, $post_id, $user_id, WPCPM_Institution_Audit::GROUND_MANAGER );

		return $post_id;
	}

	/**
	 * A program manager approves a planned event: it opens to its audiences, and the decision is
	 * stamped with the moment and the manager. Whether the person is a manager is the caller's to ask.
	 *
	 * Only while the sponsor still offers events (`offers_events()`), asked under the lock on the
	 * fresh read: an approval opens an event to its audiences, and a sponsor that is not Approved, is
	 * gone from the store or holds no tier with events today offers it to nobody, so its members would
	 * be told of an event nobody can join. Refused, nothing is written.
	 *
	 * @param WP_Post|int $post    The event.
	 * @param int         $user_id The manager.
	 * @return true|WP_Error The refusal: `not_found`, `not_allowed`, `busy`, `cancelled`,
	 *                       `not_planned` (approved already), `past` or `no_tier` (a sponsor that no
	 *                       longer offers events).
	 */
	public static function approve( $post, $user_id ) {
		$opened = self::open( $post, $user_id, array( self::STATE_PLANNED ) );

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		$record = self::sponsor_of( $opened );
		$lock   = self::lock_of( $opened );

		if ( ! self::offers_events( $record ) ) {
			self::unlock( $lock );

			return self::refuse( 'no_tier' );
		}

		update_post_meta( $opened->ID, self::META_STATE, self::STATE_APPROVED );
		update_post_meta( $opened->ID, self::META_DECISION, self::decision( $user_id, '' ) );
		self::next_revision( $opened->ID );
		self::unlock( $lock );
		self::refresh_public_page();

		self::audit( self::LOG_APPROVED, $opened->ID, $user_id, WPCPM_Institution_Audit::GROUND_MANAGER );

		return true;
	}

	/**
	 * A program manager returns an event with a note: it is canceled, and the note is kept in the
	 * decision for the mail that carries it. The audit row says a note went with it and never what it
	 * says.
	 *
	 * A planned event alone, not over, asked here under the lock on the fresh read rather than left
	 * to the page that pressed: a return is the program's answer to a plan that waits, so it tells
	 * nobody on the event, and the members' mail about it carries no calendar file. An approved event,
	 * which people may have joined and whose approval put it in the members' calendars, is called off
	 * with a cancel, which tells both; a return that meets one, approved since its page was drawn, is
	 * refused `not_planned` and writes nothing.
	 *
	 * @param WP_Post|int $post    The event.
	 * @param int         $user_id The manager.
	 * @param string      $note    Why, in the manager's words; at most `MAX_NOTE` characters as typed.
	 *                             Cleaned here, whether it comes as typed or as the cleaner left it.
	 * @param string|null $typed   What the manager typed for the note, unslashed and not cleaned,
	 *                             which the limit is counted on, as its box counts it
	 *                             (`WPCPM_Typed_Text::box_length()`): the Events card hands it beside
	 *                             the note it cleaned. Null counts the note as it was handed.
	 * @return true|WP_Error The refusal: `not_found`, `not_allowed`, `note` (none written), `note_long`
	 *                       (longer than `MAX_NOTE`, none kept), `busy`, `cancelled`, `not_planned`
	 *                       (approved) or `past`.
	 */
	public static function return_with_note( $post, $user_id, $note, $typed = null ) {
		$typed = null === $typed ? $note : $typed;
		$note  = is_scalar( $note ) ? trim( sanitize_textarea_field( (string) $note ) ) : '';

		if ( '' === $note && null !== self::event( $post ) ) {
			return self::refuse( 'note' );
		}

		// Counted on what was typed, as its box counted it: the cleaner writes a "<" that opens no tag,
		// and the quote marks and the ampersand after it, as entities, and a reference typed out after
		// it in core's own form, `&#9;` as `&#009;`, so a note that fits its box is stored longer than
		// it was typed. One longer than the box takes is refused whole, never cut, which would drop its
		// last words or end inside an entity.
		if ( WPCPM_Typed_Text::box_length( $typed ) > self::MAX_NOTE && null !== self::event( $post ) ) {
			return self::refuse( 'note_long' );
		}

		$opened = self::open( $post, $user_id, array( self::STATE_PLANNED ) );

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		update_post_meta( $opened->ID, self::META_STATE, self::STATE_CANCELLED );
		update_post_meta( $opened->ID, self::META_DECISION, wp_slash( self::decision( $user_id, $note ) ) );
		self::next_revision( $opened->ID );
		self::unlock( self::lock_of( $opened ) );

		self::audit( self::LOG_RETURNED, $opened->ID, $user_id, WPCPM_Institution_Audit::GROUND_MANAGER );

		return true;
	}

	/**
	 * Cancel an event, planned or approved, before it is over: by one of the sponsor's members or by
	 * a program manager, whose ground the row records. The people on it stay on the record, and each
	 * of them is told, with the calendar file that takes the event off their calendar: written down
	 * under the lock as the rows still to tell, then, once the row is written and the lock given
	 * back, the first `TELL_IN_REQUEST` of them in this request and the rest by the drain.
	 *
	 * @param WP_Post|int $post    The event.
	 * @param int         $user_id Who cancels it.
	 * @return array|WP_Error `told` (how many of the people on it this request told, the sends the
	 *                        mail layer handed off) and `pending` (how many it left to the drain); or
	 *                        the refusal: `not_found`, `not_allowed`, `busy`, `cancelled` or `past`.
	 */
	public static function cancel( $post, $user_id ) {
		$opened = self::open( $post, $user_id, array( self::STATE_PLANNED, self::STATE_APPROVED ) );

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		// An approved event is on the Partners page, and this takes it off; a waiting one
		// never was.
		$listed = self::STATE_APPROVED === self::state_of( $opened );

		update_post_meta( $opened->ID, self::META_STATE, self::STATE_CANCELLED );
		self::write_down( $opened->ID, self::next_revision( $opened->ID ), self::ROW_CANCEL );
		self::unlock( self::lock_of( $opened ) );

		if ( $listed ) {
			self::refresh_public_page();
		}

		self::audit( self::LOG_CANCELLED, $opened->ID, $user_id, self::ground_of( $user_id ) );

		return self::tell_in_request( $opened->ID );
	}

	/**
	 * Change an event's day and time, length, places, title, description or link, planned or
	 * approved, before it is over. The state stays: an approved event changed is still approved (the
	 * design's 18.2), and the version rises so the calendar files sent about the change replace the
	 * ones before. The new start meets the allowance's questions about a start. `past_term`: on or
	 * before the last day of the sponsor's term as recorded, whether the term still runs or has
	 * lapsed, so an event is never moved past the term it was planned in (a renewal moves the last day
	 * on, and the bound with it). `before_term`: for an event dated on or after the running term's
	 * first day as the row records it, not before that day either (`change_floor()`), so an event of a
	 * renewed term is never moved back into the term before, which still runs and may have held its
	 * own event. An event of the term before keeps that term's days, and may move on into the renewed
	 * term. And `in_past`. The places may not fall below the people on it. An event of the program's
	 * own has no term, so `in_past` and the places are the whole of it.
	 *
	 * What the change is, is asked of the event as read under the lock (`what_changed()`). One that
	 * alters nothing writes nothing, raises no version and tells nobody: a Save pressed on the form
	 * as it was drawn, from whatever clock, is no news. One that moves what a calendar holds the event
	 * for, its time, its length, its title or its link, tells each person on it what the event now
	 * is, with a calendar file at the new version that replaces the one they had: written down under
	 * the lock, then the first `TELL_IN_REQUEST` in this request and the rest by the drain, as a
	 * cancel does. One of the description or the places alone is written and raises the version,
	 * and tells nobody on it, as a group session's new topic tells nobody: the pages tell the
	 * sponsor's members and the program contact. The people a move before this one left to the
	 * drain stay on its list, since they have yet to hear of that one.
	 *
	 * A title or a description the form posted back as it drew it (`unedited`) is the text the event
	 * holds, read under the lock: it is no change, and it is written back as it is held, so a word
	 * the text cleaner would take from it, in a row written by other means, is kept. It is still
	 * handed to `WPCPM_Typed_Text::insert_text()`, since WordPress runs kses on the whole post again.
	 *
	 * @param WP_Post|int $post    The event.
	 * @param array       $fields  As `fields_from()` answers them, and `unedited`: the keys among
	 *                             `title` and `description` the form posted back as it drew them
	 *                             (`WPCPM_Sponsor_Event_Planner::handle_change()`), none by default.
	 * @param int         $user_id Who changes it: a member, or a manager on the sponsor's behalf, or a
	 *                             manager changing an event of the program's own.
	 * @return array|WP_Error `changed` (false when nothing was, and nothing is written), `told` (how
	 *                        many of the people on it this request told) and `pending` (how many it
	 *                        left to the drain); or the refusal: `not_found`, `not_allowed`, `fields`,
	 *                        `busy`, `cancelled`, `past`, `past_term`, `before_term`, `in_past`,
	 *                        `shrink` or `not_saved`.
	 */
	public static function change( $post, array $fields, $user_id ) {
		$values = null === self::event( $post ) ? array() : self::normalize( $fields );

		if ( null === $values ) {
			return self::refuse( 'fields' );
		}

		$opened = self::open( $post, $user_id, array( self::STATE_PLANNED, self::STATE_APPROVED ) );

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		$record = self::sponsor_of( $opened );
		$lock   = self::lock_of( $opened );
		$row    = WPCPM_Sponsors_Index::row( $record );
		$row    = is_array( $row ) ? $row : array();

		$unedited = isset( $fields['unedited'] ) && is_array( $fields['unedited'] ) ? $fields['unedited'] : array();

		if ( in_array( 'title', $unedited, true ) ) {
			$values['title'] = (string) $opened->post_title;
		}

		if ( in_array( 'description', $unedited, true ) ) {
			$values['description'] = (string) $opened->post_content;
		}

		// Asked of the event as read under the lock, by the one rule the Change form's date box reads.
		$refusal = self::start_refusal( self::term_until( $row ), $values['start'], self::now(), self::change_floor( $opened, $row ) );

		if ( '' === $refusal && $values['places'] > 0 && count( self::joiners( $opened ) ) > $values['places'] ) {
			$refusal = 'shrink';
		}

		$what = '' === $refusal ? self::what_changed( $opened, $values ) : '';

		if ( '' === $refusal && '' === $what ) {
			self::unlock( $lock );

			return array(
				'changed' => false,
				'told'    => 0,
				'pending' => 0,
			);
		}

		if ( '' === $refusal ) {
			$updated = wp_update_post(
				wp_slash(
					array(
						'ID'           => $opened->ID,
						'post_title'   => WPCPM_Typed_Text::insert_text( $values['title'] ),
						'post_content' => WPCPM_Typed_Text::insert_text( $values['description'] ),
					)
				),
				true
			);

			$refusal = is_wp_error( $updated ) || ! $updated ? 'not_saved' : '';
		}

		if ( '' !== $refusal ) {
			self::unlock( $lock );

			return self::refuse( $refusal, $row );
		}

		self::write_fields( $opened->ID, $values );
		$revision = self::next_revision( $opened->ID );

		if ( self::CHANGED_CALENDAR === $what ) {
			self::write_down( $opened->ID, $revision, self::ROW_CHANGE );
		}

		self::unlock( $lock );

		// A change keeps the state: an approved event is on the Partners page as it now reads.
		if ( self::STATE_APPROVED === self::state_of( $opened ) ) {
			self::refresh_public_page();
		}

		self::audit( self::LOG_CHANGED, $opened->ID, $user_id, self::ground_of( $user_id ) );

		if ( self::CHANGED_CALENDAR !== $what ) {
			return array(
				'changed' => true,
				'told'    => 0,
				'pending' => 0,
			);
		}

		return array_merge( array( 'changed' => true ), self::tell_in_request( $opened->ID ) );
	}

	/*
	 * --------------------------------------------------------------------
	 * Who may join
	 * --------------------------------------------------------------------
	 */

	/**
	 * Which audience a person joins events as, or '' for somebody no event is for.
	 *
	 * Managers first: a manager is staff whatever else the account holds, the Tools section's own
	 * rule. Then each audience through the module that owns the question: a current student as the
	 * Tools section counts one (`WPCPM_Sponsor_Tools::is_current_student()`: the student role and a
	 * status that is current and not past), an Active mentor as the mentors module reads one
	 * (`WPCPM_Mentors_Dashboard::is_active_mentor()`), and an institution contact as the Institution
	 * Dashboard resolves a signed-in contact's institution
	 * (`WPCPM_Institution_Roster::resolve_institution()`, asked without the manager's switcher, since
	 * managers were answered first). A sponsor's member is nobody here unless the account is one of
	 * these as well.
	 *
	 * @param int|WP_User|null $user The person; null for the current user, 0 for nobody.
	 * @return string One of the AUDIENCE_* constants, or ''.
	 */
	public static function audience_of( $user ) {
		$person = self::person( $user );

		if ( null === $person ) {
			return '';
		}

		if ( user_can( $person, WPCPM_Roles::CAP_MANAGE ) ) {
			return self::AUDIENCE_MANAGERS;
		}

		if ( WPCPM_Sponsor_Tools::is_current_student( $person ) ) {
			return self::AUDIENCE_STUDENTS;
		}

		if ( WPCPM_Mentors_Dashboard::is_active_mentor( $person ) ) {
			return self::AUDIENCE_MENTORS;
		}

		if ( '' !== WPCPM_Institution_Roster::resolve_institution( $person, false ) ) {
			return self::AUDIENCE_INSTITUTIONS;
		}

		return '';
	}

	/**
	 * Put a person on an approved event that is not over, under the sponsor's lock, or the program's
	 * for an event of its own, where the last place is counted. Joining is by audience, not by the
	 * sponsor policy: who may join is who the event is for, and a sponsor's own members are refused
	 * their own event, while the program's own is nobody's.
	 *
	 * @param WP_Post|int $post    The event.
	 * @param int         $user_id The person joining.
	 * @return true|WP_Error The refusal: `not_open` (no such event, not approved, or a sponsor that no
	 *                       longer offers events: not Approved, gone from the store, or its tier no
	 *                       longer one with events), `audience`, `own`, `busy`, `past`, `joined` or
	 *                       `full`.
	 */
	public static function join( $post, $user_id ) {
		$event  = self::event( $post );
		$person = null === $user_id ? null : self::person( $user_id );

		if ( null === $event ) {
			return self::refuse( 'not_open' );
		}

		if ( null === $person || '' === self::audience_of( $person ) ) {
			return self::refuse( 'audience' );
		}

		$record = self::sponsor_of( $event );
		$lock   = self::lock_of( $event );

		if ( self::is_own( $person, $record ) ) {
			return self::refuse( 'own' );
		}

		// A test sponsor's event is for the program team alone (1.123.18), so a Join drawn before
		// the flag was set is refused as the list now leaves the event out.
		if ( ! self::shown_to( self::audience_of( $person ), $record ) ) {
			return self::refuse( 'audience' );
		}

		if ( '' === $lock ) {
			return self::refuse( 'not_open' );
		}

		if ( ! self::lock( $lock ) ) {
			return self::refuse( 'busy' );
		}

		$event   = self::fresh( $event->ID );
		$refusal = '';

		if ( null === $event || self::STATE_APPROVED !== self::state_of( $event ) || ! self::offers_events( $event ) ) {
			$refusal = 'not_open';
		} elseif ( self::end_of( $event ) <= self::now() ) {
			$refusal = 'past';
		} elseif ( self::joined( $event, $person ) ) {
			$refusal = 'joined';
		} elseif ( self::is_full( $event ) ) {
			$refusal = 'full';
		}

		if ( '' !== $refusal ) {
			self::unlock( $lock );

			return self::refuse( $refusal );
		}

		add_post_meta( $event->ID, self::META_PERSON, (int) $person->ID, false );
		self::next_revision( $event->ID );
		self::unlock( $lock );

		return true;
	}

	/**
	 * Take a person off an event that is not over, under the sponsor's lock, or the program's for
	 * an event of its own. The place is free at once. A canceled event is refused: its cancel
	 * already took it out of every calendar and told each person on it, and they stay on its
	 * record, so a Leave pressed on a page drawn before the cancel changes nothing and mails
	 * nothing.
	 *
	 * @param WP_Post|int $post    The event.
	 * @param int         $user_id The person leaving.
	 * @return true|WP_Error The refusal: `not_joined` (no such event, or not on it), `busy`,
	 *                       `cancelled` or `past`.
	 */
	public static function leave( $post, $user_id ) {
		$event     = self::event( $post );
		$person_id = $user_id instanceof WP_User ? (int) $user_id->ID : ( is_numeric( $user_id ) ? (int) $user_id : 0 );
		$lock      = null === $event ? '' : self::lock_of( $event );

		if ( $person_id <= 0 || '' === $lock ) {
			return self::refuse( 'not_joined' );
		}

		if ( ! self::lock( $lock ) ) {
			return self::refuse( 'busy' );
		}

		$event   = self::fresh( $event->ID );
		$refusal = '';

		if ( null === $event ) {
			$refusal = 'not_joined';
		} elseif ( self::STATE_CANCELLED === self::state_of( $event ) ) {
			$refusal = 'cancelled';
		} elseif ( self::end_of( $event ) <= self::now() ) {
			$refusal = 'past';
		} elseif ( ! self::joined( $event, $person_id ) ) {
			$refusal = 'not_joined';
		}

		if ( '' !== $refusal ) {
			self::unlock( $lock );

			return self::refuse( $refusal );
		}

		delete_post_meta( $event->ID, self::META_PERSON, $person_id );
		self::next_revision( $event->ID );
		self::unlock( $lock );

		return true;
	}

	/**
	 * Whether a person is on an event.
	 *
	 * @param WP_Post|int $post The event.
	 * @param int|WP_User $user The person.
	 * @return bool
	 */
	public static function joined( $post, $user ) {
		$person_id = $user instanceof WP_User ? (int) $user->ID : ( is_numeric( $user ) ? (int) $user : 0 );

		return $person_id > 0 && in_array( $person_id, self::joiners( $post ), true );
	}

	/**
	 * The people on an event, as user IDs in the order they joined. Names are for program managers
	 * alone, and are theirs to read from these; nothing here holds one.
	 *
	 * @param WP_Post|int $post The event.
	 * @return int[]
	 */
	public static function joiners( $post ) {
		$event = self::event( $post );

		if ( null === $event ) {
			return array();
		}

		$people = array();

		foreach ( (array) get_post_meta( $event->ID, self::META_PERSON, false ) as $value ) {
			$person_id = is_numeric( $value ) ? (int) $value : 0;

			if ( $person_id > 0 && ! in_array( $person_id, $people, true ) ) {
				$people[] = $person_id;
			}
		}

		return $people;
	}

	/*
	 * --------------------------------------------------------------------
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether a value is an event: a private post of this type.
	 *
	 * @param mixed $post Anything.
	 * @return bool
	 */
	public static function is_event( $post ) {
		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type && 'private' === $post->post_status;
	}

	/**
	 * An event's facts: everything a list or a card draws, and neither the call link nor anybody's
	 * name, which are read on purpose or not at all.
	 *
	 * The host is the name a list gives beside the event's title: its sponsor's (`sponsor_name()`),
	 * or, for an event of the program's own, the WordPress Credits Program, in the language the
	 * facts are read in. A sponsor's name may reach everybody the event is offered to, and so may the
	 * program's.
	 *
	 * @param WP_Post $post The event.
	 * @return array `id`, `sponsor` ('' for the program's own), `host`, `title`, `description`,
	 *               `start`, `end`, `minutes`, `places` (0 for no limit), `zone`, `kind`, `state`,
	 *               `decision` (`at`, `by`, `note`), `revision` and `joined` (how many people are on
	 *               it).
	 */
	public static function facts( WP_Post $post ) {
		$post_id  = (int) $post->ID;
		$decision = get_post_meta( $post_id, self::META_DECISION, true );
		$decision = is_array( $decision ) ? $decision : array();
		$record   = self::sponsor_of( $post );

		return array(
			'id'          => $post_id,
			'sponsor'     => $record,
			'host'        => self::is_program( $post ) ? __( 'the WordPress Credits Program', 'wpcredits-program-manager' ) : self::sponsor_name( $record ),
			'title'       => (string) $post->post_title,
			'description' => (string) $post->post_content,
			'start'       => self::start_of( $post ),
			'end'         => self::end_of( $post ),
			'minutes'     => (int) get_post_meta( $post_id, self::META_MINUTES, true ),
			'places'      => max( 0, (int) get_post_meta( $post_id, self::META_PLACES, true ) ),
			'zone'        => (string) get_post_meta( $post_id, self::META_ZONE, true ),
			'kind'        => (string) get_post_meta( $post_id, self::META_KIND, true ),
			'state'       => self::state_of( $post ),
			'decision'    => array(
				'at'   => isset( $decision['at'] ) ? (int) $decision['at'] : 0,
				'by'   => isset( $decision['by'] ) ? (int) $decision['by'] : 0,
				'note' => isset( $decision['note'] ) ? (string) $decision['note'] : '',
			),
			'revision'    => (int) get_post_meta( $post_id, self::META_REVISION, true ),
			'joined'      => count( self::joiners( $post ) ),
		);
	}

	/**
	 * An event's call link. For the people on it, the sponsor's own members, who wrote it, and
	 * program managers; never for a list, a report or the audit trail.
	 *
	 * @param WP_Post $post The event.
	 * @return string
	 */
	public static function link_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_LINK, true );
	}

	/**
	 * The sponsor an event is for: nobody's key for an event of the program's own, whatever its rows
	 * hold, so a key written on one by hand makes it no sponsor's, to be listed, counted or reported.
	 *
	 * @param WP_Post $post The event.
	 * @return string Sponsor key, or ''.
	 */
	public static function sponsor_of( WP_Post $post ) {
		if ( self::is_program( $post ) ) {
			return '';
		}

		return trim( (string) get_post_meta( (int) $post->ID, self::META_SPONSOR, true ) );
	}

	/**
	 * Whether an event is one the program planned itself (`KIND_PROGRAM`), with no sponsor: its kind,
	 * written once as it is planned, says so.
	 *
	 * @param WP_Post $post The event.
	 * @return bool
	 */
	public static function is_program( WP_Post $post ) {
		return self::KIND_PROGRAM === (string) get_post_meta( (int) $post->ID, self::META_KIND, true );
	}

	/**
	 * A sponsor's name as the sponsors store holds it, or its key when the store has none: what the
	 * list of events and the mails to the people on one call the sponsor. The sponsor's name may reach
	 * everybody the event is offered to; nobody else's does.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	public static function sponsor_name( $record ) {
		$record = trim( (string) $record );
		$row    = WPCPM_Sponsors_Index::row( $record );
		$name   = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : $record;
	}

	/**
	 * The values every email about an event fills its template with (`includes/mail/templates/events.php`):
	 * the site's name, and the event's title and its sponsor's name as stored, which the registry reads
	 * back once, as a person reads them (`WPCPM_Mail_Templates::fill()`); when it runs, on one person's
	 * clock with the zone named, so that no two people read one event's time differently, the people on
	 * it, the sponsor's members, the program contact and the person who joins alike; how long it runs;
	 * how many may join, or "no limit"; and its call link.
	 *
	 * @param array        $facts   The event's facts (`facts()`), or any array with its `title`,
	 *                              `start`, `end`, `minutes` and `places`.
	 * @param string       $link    The event's call link (`link_of()`).
	 * @param DateTimeZone $zone    The person's clock.
	 * @param string       $sponsor The sponsor's name (`sponsor_name()`), '' for an event of the
	 *                              program's own.
	 * @return array<string,string>
	 */
	public static function mail_values( array $facts, $link, DateTimeZone $zone, $sponsor ) {
		return array(
			'site_name'    => WPCPM_Mail::site_name(),
			'event_title'  => (string) $facts['title'],
			'company_name' => (string) $sponsor,
			'when'         => WPCPM_Mentor_Calls::format_range( (int) $facts['start'], (int) $facts['end'], $zone ),
			'time_zone'    => WPCPM_Mentor_Availability::zone_label( $zone->getName() ),
			'length'       => self::length_line( (int) $facts['minutes'] ),
			'places'       => (int) $facts['places'] > 0 ? number_format_i18n( (int) $facts['places'] ) : __( 'no limit', 'wpcredits-program-manager' ),
			'call_link'    => (string) $link,
		);
	}

	/**
	 * How long an event runs, in words ("90 minutes"): what every line and list about an event says
	 * for its length, a mail's "Length:" line, the program's card and the lists alike.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function length_line( $minutes ) {
		return sprintf(
			/* translators: %s: a number of minutes. */
			_n( '%s minute', '%s minutes', (int) $minutes, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $minutes )
		);
	}

	/**
	 * An event's state.
	 *
	 * @param WP_Post $post The event.
	 * @return string One of the STATE_* constants, or '' for a value this class never writes.
	 */
	public static function state_of( WP_Post $post ) {
		$state = (string) get_post_meta( (int) $post->ID, self::META_STATE, true );

		return in_array( $state, array( self::STATE_PLANNED, self::STATE_APPROVED, self::STATE_CANCELLED ), true ) ? $state : '';
	}

	/**
	 * Whether a sponsor still offers its events to the program: the store holds it, it is Approved,
	 * and its tier includes events today, or on the day asked about. A sponsor that is no longer
	 * Approved, because it left the program or is paused, has nothing listed and nothing to join, as
	 * the public listing, the badge and the feed drop it; one whose term has lapsed holds a tier with
	 * no events. A sponsor that still has its card keeps the event there.
	 *
	 * Asked of an event, it is asked of the event's sponsor, and an event of the program's own is
	 * always offered: no sponsor's standing stands behind it, and the state and the time, which every
	 * caller asks beside this, are the whole of whether it can be joined.
	 *
	 * The one test `approve()`, `join()` and `open_for()` ask, and the Administrator Dashboard's card,
	 * which draws no Approve for an event whose sponsor it refuses, the Partners page
	 * (`public_host()`), which asks it on the day the listing reads every sponsor's tier on, and the
	 * daily run's reminders (`write_reminders()`), which ask it under the event's lock on the run's
	 * day, and the event lists (`WPCPM_Sponsor_Event_List`), which ask it for each event they draw, so none of
	 * them can disagree.
	 *
	 * @param string|WP_Post $record Sponsor key, or an event.
	 * @param string         $day    `Y-m-d`, the day the tier is read on, or '' for today.
	 * @return bool
	 */
	public static function offers_events( $record, $day = '' ) {
		if ( $record instanceof WP_Post ) {
			if ( self::is_program( $record ) ) {
				return true;
			}

			$record = self::sponsor_of( $record );
		}

		$row = WPCPM_Sponsors_Index::row( trim( (string) $record ) );
		$day = is_scalar( $day ) && '' !== (string) $day ? (string) $day : WPCPM_Sponsor_Tiers::today();

		return is_array( $row ) && self::is_approved( $row ) && self::tier_offers( $row, $day );
	}

	/**
	 * A sponsor's events still to take place, planned or approved, soonest first: what its card
	 * draws above the form. Never the program's own (`events_of()`).
	 *
	 * @param string $record Sponsor key.
	 * @return WP_Post[]
	 */
	public static function upcoming_of( $record ) {
		$now      = self::now();
		$upcoming = array();

		foreach ( self::events_of( trim( (string) $record ) ) as $event ) {
			if ( in_array( self::state_of( $event ), array( self::STATE_PLANNED, self::STATE_APPROVED ), true ) && self::end_of( $event ) > $now ) {
				$upcoming[] = $event;
			}
		}

		return self::soonest_first( $upcoming );
	}

	/**
	 * A sponsor's events that took place: approved and over, the latest first.
	 *
	 * @param string $record Sponsor key.
	 * @return WP_Post[]
	 */
	public static function past_of( $record ) {
		$now  = self::now();
		$past = array();

		foreach ( self::events_of( trim( (string) $record ) ) as $event ) {
			if ( self::STATE_APPROVED === self::state_of( $event ) && self::end_of( $event ) <= $now ) {
				$past[] = $event;
			}
		}

		return array_reverse( self::soonest_first( $past ) );
	}

	/**
	 * The note a program manager returned the sponsor's latest event with, or '' when its latest
	 * event was not returned: the planner shows it above its form, so the sponsor plans again knowing
	 * why. The latest is the event planned last, whatever its day, so a new plan puts the note away;
	 * a cancel carries no note of its own, so an event the sponsor canceled puts it away too.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	public static function returned_note( $record ) {
		$latest = null;

		foreach ( self::events_of( trim( (string) $record ) ) as $event ) {
			if ( null === $latest || (int) $event->ID > (int) $latest->ID ) {
				$latest = $event;
			}
		}

		if ( null === $latest || self::STATE_CANCELLED !== self::state_of( $latest ) ) {
			return '';
		}

		$decision = get_post_meta( (int) $latest->ID, self::META_DECISION, true );

		return is_array( $decision ) && isset( $decision['note'] ) ? trim( (string) $decision['note'] ) : '';
	}

	/**
	 * The events waiting for a program manager, every sponsor's, soonest first. One whose day has
	 * passed without an approval waits no longer: it can no longer take place.
	 *
	 * @return WP_Post[]
	 */
	public static function waiting() {
		return self::in_state( self::STATE_PLANNED );
	}

	/**
	 * The approved events not over yet, every sponsor's and the program's own, soonest first, whatever
	 * the sponsor's standing: the program's own list, which the Administrator Dashboard draws. A
	 * sponsor set to Paused, or one whose tier no longer has events, offers its event to nobody
	 * (`open_for()` leaves it out), and the people already on it still expect it; a program manager
	 * has to see it to call it off.
	 *
	 * @return WP_Post[]
	 */
	public static function approved_upcoming() {
		return self::in_state( self::STATE_APPROVED );
	}

	/**
	 * The approved events on a person's list, not over yet, soonest first: every event they may
	 * join, which is every sponsor's while its sponsor still offers events (Approved, and a tier
	 * that includes them) and every one of the program's own, never a sponsor's own to one of its
	 * members, nor a test sponsor's but to the program team (1.123.18), and nothing to somebody no
	 * event is for; and every event they are on, whatever
	 * became of its sponsor or of their own place in the program since they joined, so that they
	 * can leave it. `join()` refuses such an event all the same. The sponsor's own card reads
	 * `upcoming_of()`, and the program's `approved_upcoming()`, and both keep a sponsor's event
	 * whatever the sponsor's standing.
	 *
	 * @param int|WP_User|null $user The person; null for the current user.
	 * @return WP_Post[]
	 */
	public static function open_for( $user ) {
		$person = self::person( $user );

		if ( null === $person ) {
			return array();
		}

		$who      = self::audience_of( $person );
		$audience = '' !== $who;
		$open     = array();

		foreach ( self::in_state( self::STATE_APPROVED ) as $event ) {
			$record = self::sponsor_of( $event );

			if ( self::joined( $event, $person ) || ( $audience && self::offers_events( $event ) && ! self::is_own( $person, $record ) && self::shown_to( $who, $record ) ) ) {
				$open[] = $event;
			}
		}

		return $open;
	}

	/**
	 * Whether a sponsor's events are for an audience: a test sponsor's, never listed publicly, are
	 * for the program team alone (1.123.18), and every other sponsor's, and the program's own, for
	 * every audience.
	 *
	 * @param string $audience One of the AUDIENCE_* constants, or ''.
	 * @param string $record   The event's sponsor key, '' for the program's own.
	 * @return bool
	 */
	private static function shown_to( $audience, $record ) {
		return self::AUDIENCE_MANAGERS === $audience || '' === (string) $record || ! WPCPM_Sponsors_Index::is_test( (string) $record );
	}

	/**
	 * The events the Partners page lists: every approved event not over yet, soonest first, one
	 * already running among them, since the people it is for may join it until it ends; each of a
	 * sponsor that still offers events on the day and is no test sponsor (1.123.18), or of the
	 * program's own. Each is what a visitor may
	 * read of it and nothing more: its title, its host and the host's website, when it runs and for how
	 * long, and its description as `public_text()` leaves it. Never the call link, never anybody on the
	 * event, by name or by count, never its places, its clock, its ID or its sponsor's key: the page is
	 * public, a page cache keeps it, and the people an event is for join it on their own pages, signed
	 * in. A title changed after the approval is listed as it now reads, as the program's card shows it
	 * to the managers who may cancel the event.
	 *
	 * A sponsor offers the page its event on the terms `offers_events()` asks, read on the day given so
	 * that the listing reads a sponsor's tier on one day for its list and its events alike: the store
	 * holds the sponsor, it is Approved, and its tier on the day includes events. A sponsor with no name
	 * is no host either: the listing draws nothing for a row without one, and the page would otherwise
	 * show its key. Nothing at all while the launch switch is off, whoever asks: every event is a benefit
	 * of the tiers, and the public sees nothing of them before the launch.
	 *
	 * @param string $today `Y-m-d`, the day a sponsor's tier is read on, or '' for today.
	 * @return array[] Each `title`, `host` (the sponsor's name, or the WordPress Credits Program for the
	 *                 program's own), `host_url` (the sponsor's website as the sponsors store holds it,
	 *                 '' for the program's own or a sponsor with none), `start` and `end` (UTC
	 *                 timestamps), `minutes` and `description`.
	 */
	public static function public_upcoming( $today = '' ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return array();
		}

		$day    = '' !== (string) $today ? (string) $today : WPCPM_Sponsor_Tiers::today();
		$listed = array();

		foreach ( self::in_state( self::STATE_APPROVED ) as $event ) {
			$host = self::public_host( $event, $day );

			if ( null === $host ) {
				continue;
			}

			$listed[] = array(
				'title'       => (string) $event->post_title,
				'host'        => $host['name'],
				'host_url'    => $host['url'],
				'start'       => self::start_of( $event ),
				'end'         => self::end_of( $event ),
				'minutes'     => (int) get_post_meta( (int) $event->ID, self::META_MINUTES, true ),
				'description' => self::public_text( (string) $event->post_content ),
			);
		}

		return $listed;
	}

	/**
	 * An event's host as the public page names it, or null when the page lists nothing of the event:
	 * the WordPress Credits Program for an event of its own, with no website; else the sponsor's name
	 * and website as the sponsors store holds them, while the sponsor offers events on the day
	 * (`offers_events()`, asked on that day) and has a name.
	 *
	 * @param WP_Post $event The event.
	 * @param string  $day   `Y-m-d`, the day the sponsor's tier is read on.
	 * @return array|null `name` and `url`, or null.
	 */
	private static function public_host( WP_Post $event, $day ) {
		if ( self::is_program( $event ) ) {
			return array(
				'name' => __( 'the WordPress Credits Program', 'wpcredits-program-manager' ),
				'url'  => '',
			);
		}

		// The one test every reader of whether a sponsor offers its events asks, on the listing's day;
		// and a test sponsor is never listed publicly, so neither is its event (1.123.18).
		if ( ! self::offers_events( $event, $day ) || WPCPM_Sponsors_Index::is_test( self::sponsor_of( $event ) ) ) {
			return null;
		}

		$row  = WPCPM_Sponsors_Index::row( self::sponsor_of( $event ) );
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		if ( '' === $name ) {
			return null;
		}

		return array(
			'name' => $name,
			'url'  => isset( $row['website'] ) ? trim( (string) $row['website'] ) : '',
		);
	}

	/**
	 * An event's description as the public page shows it: every address written with an `http://`,
	 * `https://` or `mailto:` scheme taken out, from the scheme to the next space, whatever its case,
	 * so a call link pasted into the description with its scheme never reaches the page, nor a
	 * person's address written as a link; and the spaces and line breaks left run together into one,
	 * since what is left of the line an address stood on reads on as one sentence. The rule is the
	 * scheme: an address written with none, a bare `www.` one or a bare email address, is words to
	 * this and stays as the sponsor wrote it, which is why the planner's description box says the
	 * description is public.
	 *
	 * @param string $text The description as the planner stored it.
	 * @return string
	 */
	private static function public_text( $text ) {
		$text = (string) preg_replace( '#(?:https?://|mailto:)\S+#i', '', (string) $text );

		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Tell the host's page cache that the Partners page has changed: `clean_post_cache()` of
	 * the page (`WPCPM_Sponsors_Page::page_id()`), which the host's page cache purges the page's
	 * address on. The page lists the approved events not over yet (`public_upcoming()`), and a page
	 * cache keeps it for signed-out visitors, so without this an event approved or planned as the
	 * program's own would appear late, and one changed or canceled would go on showing what it was,
	 * its Sign in to join included, until the cache expired. Asked after a move that adds an event to
	 * the page, changes one there or takes one off, once the move is written; a sponsor's plan and a
	 * return touch nothing the page shows. A site with no page cache has nothing to purge, and the
	 * store loaded without the page's class has no page to name.
	 */
	private static function refresh_public_page() {
		if ( ! class_exists( 'WPCPM_Sponsors_Page' ) ) {
			return;
		}

		$page_id = WPCPM_Sponsors_Page::page_id();

		if ( $page_id > 0 ) {
			clean_post_cache( $page_id );
		}
	}

	/**
	 * The events a sponsor held in a period, for the annual report: approved and ended, their end on
	 * a day of the period on the site's clock, first day and last day included, soonest first. Each
	 * is its title, its start, its length and how many people were on it; a canceled event was not
	 * held, and a planned one never took place under the program's name. An event still to come was
	 * not held either, whatever its date: a report can be drafted before its term ends, and the
	 * people on an event are only known once it is over, since they may join and leave until then.
	 * An event of the program's own is never in a sponsor's report: it is no sponsor's (`events_of()`).
	 *
	 * @param string $record Sponsor key.
	 * @param string $from   The period's first day, `Y-m-d`.
	 * @param string $until  The period's last day, `Y-m-d`.
	 * @return array[] Each `title`, `start` (a UTC timestamp), `minutes` and `joined`; `array()` for a
	 *                 key or a day that is not one.
	 */
	public static function held_in( $record, $from, $until ) {
		$from  = is_scalar( $from ) ? (string) $from : '';
		$until = is_scalar( $until ) ? (string) $until : '';

		if ( ! WPCPM_Sponsor_Tiers::is_date( $from ) || ! WPCPM_Sponsor_Tiers::is_date( $until ) ) {
			return array();
		}

		$now  = self::now();
		$held = array();

		foreach ( self::soonest_first( self::events_of( trim( (string) $record ) ) ) as $event ) {
			if ( self::STATE_APPROVED !== self::state_of( $event ) || self::end_of( $event ) > $now || ! self::within( self::day_of( self::end_of( $event ) ), $from, $until ) ) {
				continue;
			}

			$held[] = array(
				'title'   => (string) $event->post_title,
				'start'   => self::start_of( $event ),
				'minutes' => (int) get_post_meta( (int) $event->ID, self::META_MINUTES, true ),
				'joined'  => count( self::joiners( $event ) ),
			);
		}

		return $held;
	}

	/*
	 * --------------------------------------------------------------------
	 * The calendar file
	 * --------------------------------------------------------------------
	 */

	/**
	 * The event's calendar file for one person, as a one-element attachment list, for the mails that
	 * carry it.
	 *
	 * Built with `WPCPM_ICS`, the group sessions' builder: the program's sender as the organizer, the
	 * address the mail the file arrives with comes from (`WPCPM_Mail::sender()`), never a person's
	 * and never an address a setting holds for another purpose; the title as the summary, the
	 * description and the link as the description, the start and the end in UTC, the event's version
	 * as the sequence, and a cancellation for a canceled event.
	 *
	 * The person it goes to is its attendee, by name and address, as the sessions' files name theirs,
	 * since RFC 5546 asks a request to name at least one. It names nobody else, so each person gets a
	 * file of their own, and their own name and address in their own file are theirs. Asked with no
	 * person, it names the program alone.
	 *
	 * A list, so a caller hands it to a mail's `attachments` and `cleanup` alike, and a temporary
	 * folder that cannot be written means no attachment rather than a failed send. Asked again for the
	 * same event, method, version and person while the file is still there, the same file comes back,
	 * as the calls' own builder does: a second one would be left on disk with nothing holding its path.
	 *
	 * The method follows the event's state unless the caller names one: a person who leaves an event
	 * the others still attend is sent the cancel of an approved event, which takes it off their
	 * calendar alone. The method is part of what a file is remembered by, so a request and a cancel
	 * at one version are two files.
	 *
	 * @param WP_Post      $post      The event.
	 * @param string       $method    `WPCPM_ICS::METHOD_REQUEST` or `WPCPM_ICS::METHOD_CANCEL`, or ''
	 *                                (and anything else) for the one the state calls for: a cancel
	 *                                for a canceled event, else a request.
	 * @param WP_User|null $recipient The person the file goes to, the account the mail layer hands a
	 *                                mail's builder; null for a file that names nobody but the program.
	 * @return string[] The file's path in a list, or an empty list.
	 */
	public static function calendar_file( WP_Post $post, $method = '', $recipient = null ) {
		static $built = array();

		if ( ! self::is_event( $post ) ) {
			return array();
		}

		$facts     = self::facts( $post );
		$recipient = $recipient instanceof WP_User ? $recipient : null;

		if ( ! in_array( $method, array( WPCPM_ICS::METHOD_REQUEST, WPCPM_ICS::METHOD_CANCEL ), true ) ) {
			$method = self::STATE_CANCELLED === $facts['state'] ? WPCPM_ICS::METHOD_CANCEL : WPCPM_ICS::METHOD_REQUEST;
		}

		// The calls' key shape, the person part of it: each person's file names them, so two people
		// are two files.
		$memo = $facts['id'] . '|' . $method . '|' . $facts['revision'] . '|' . ( null === $recipient ? 0 : (int) $recipient->ID );

		if ( isset( $built[ $memo ][0] ) && file_exists( $built[ $memo ][0] ) ) {
			return $built[ $memo ];
		}

		$link = self::link_of( $post );
		$body = $facts['description'];

		if ( '' !== $link ) {
			/* translators: %s: the event's call link. */
			$body = trim( $body . "\n\n" . sprintf( __( 'Join the call: %s', 'wpcredits-program-manager' ), $link ) );
		}

		$ics = WPCPM_ICS::build(
			array(
				'id'    => $facts['id'],
				'start' => $facts['start'],
				'end'   => $facts['end'],
			),
			$method,
			self::organizer(),
			$recipient,
			$facts['title'],
			$body,
			'',
			$facts['revision']
		);

		$path = WPCPM_ICS::tempfile( $ics, 'sponsor-event.ics' );

		$built[ $memo ] = '' === $path ? array() : array( $path );

		return $built[ $memo ];
	}

	/*
	 * --------------------------------------------------------------------
	 * The people on an event
	 * --------------------------------------------------------------------
	 */

	/**
	 * Tell the people moves and the daily run's reminders left on their events, a run at a time: the
	 * cron event's own hook.
	 *
	 * The rows of every event that holds any, the oldest event first, up to `TELL_IN_REQUEST` in all.
	 * The next run is put on the clock before the first send whenever this one found anybody to
	 * tell, so a run the host cuts off leaves its rows to that one, and the run that finds nobody
	 * puts nothing back. Each event is read again first, so a mail says what the event is now.
	 */
	public static function drain() {
		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => self::META_PENDING,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$batch  = array();
		$people = array();
		$left   = self::TELL_IN_REQUEST;

		foreach ( (array) $found as $event_id ) {
			$event = self::fresh( (int) $event_id );
			$rows  = null === $event ? array() : array_slice( self::pending_rows( $event ), 0, $left );

			if ( array() === $rows ) {
				continue;
			}

			$batch[] = array( $event, $rows );
			$people  = array_merge( $people, array_map( array( __CLASS__, 'row_person' ), $rows ) );
			$left   -= count( $rows );

			if ( $left <= 0 ) {
				break;
			}
		}

		if ( array() === $batch ) {
			return;
		}

		self::schedule_drain();
		cache_users( $people );

		foreach ( $batch as $part ) {
			self::tell( $part[0], $part[1] );
		}
	}

	/**
	 * Put the drain on the clock when rows wait for it, unless a run is due already
	 * (`schedule_drain()`): the net under a drain whose event the clock lost, taken off by a
	 * deactivation, fired while the plugin was off, or never put on by a press that died between
	 * writing its rows down and scheduling the drain. Otherwise only a move on an event with somebody
	 * on it, or the next reminders written, puts the drain back, and the people a lost drain left
	 * could wait for ever. One query, for one event that holds a row; the daily run and the plugin's
	 * activation ask it, so rows wait a day at most.
	 */
	public static function resume_drain() {
		$waiting = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::META_PENDING,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( array() !== (array) $waiting ) {
			self::schedule_drain();
		}
	}

	/**
	 * The day before an event: write down everyone to remind of it, for the drain to tell. Asked by
	 * the daily run, once a day, last, while the tiers are live, and by its retry (`CRON_RETRY`).
	 *
	 * The run fires once a day at the hour the first load after a deploy put it on, so "the day
	 * before" is a window it can keep whatever that hour is (`reminder_window()`): every approved
	 * event that starts before `REMIND_UNTIL` hours after the run, and from `REMIND_FROM` hours after
	 * it, or from where the last complete window stopped (`OPT_REMINDER_EDGE`) when that is sooner.
	 * On the daily cadence the two are one moment, so runs a day apart share no event and miss none;
	 * a run that fires late, as WP-Cron fires a due event on the first request after its time,
	 * reaches back to the band the window before stopped short of, and reminds those events closer
	 * to their start than a day. The window is counted in hours on the store's clock, not in the
	 * site's calendar days, so a run late in the site's evening reminds people of an event early on
	 * the day after next, and not of one just after midnight, which the run before reminded them of;
	 * and it never reaches before the run's own moment, an event that has started being past
	 * reminding. An event approved after a run is written down by a later run only if it starts
	 * inside that run's window, which on the daily cadence opens `REMIND_FROM` hours after it, so one
	 * approved less than `REMIND_UNTIL` hours before its start may be reminded by no run.
	 *
	 * Only while the event's sponsor still offers events, the rule `offers_events()` asks, on the
	 * day the run reads, handed in so that a run which crosses the site's midnight asks every
	 * question of one day: a sponsor the Sponsors screen set to Paused, or one whose term has
	 * lapsed, offers its event to nobody, and it is a program manager's to call off. An event of
	 * the program's own is reminded while it is approved, with no sponsor's standing behind it.
	 * Each event once: under its lock, the sponsor's or the program's, it is read again, and one
	 * already marked (`META_REMINDED`) is left alone, so a second run the same day writes nothing,
	 * and nor does a run after a change, whose own mail gave the new time.
	 *
	 * An event whose lock another write holds at that moment is left unmarked, and one retry
	 * of the run is put on the clock, `RETRY_GAP` from now, unless one is due already. The edge moves
	 * on to where the window stops only once a run has written down every event its window holds; a
	 * run that left one records where its window began instead, which on the daily cadence and after
	 * a late run is the edge it started from, so the retry covers the same window.
	 *
	 * One row of the reminder's kind at the event's version for each person on it, in the order they
	 * joined, then for each live member of its sponsor who is not on it, so a person who is both is
	 * written once, and for the program's own event the people on it alone; an account that is gone
	 * gets none. The rows a move left waiting stay beside them.
	 * Whether each is reminded as someone on the event or as a member is asked when they are told
	 * (`tell()`). The drain is put on the clock before an event's first row is written
	 * (`write_reminders()`) and tells the rows; nothing is mailed here, since the run has every
	 * sponsor's work to do in the one request.
	 *
	 * @param string $today `Y-m-d`, the day the run reads, on which a sponsor's standing is asked; ''
	 *                      for the site's today.
	 * @return int How many people were written down, over every event.
	 */
	public static function queue_reminders( $today = '' ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return 0;
		}

		$day     = is_scalar( $today ) && '' !== (string) $today ? (string) $today : WPCPM_Sponsor_Tiers::today();
		$window  = self::reminder_window( self::now() );
		$written = 0;
		$held    = false;

		foreach ( self::in_state( self::STATE_APPROVED ) as $event ) {
			if ( ! self::in_window( $event, $window ) || self::reminded( $event ) ) {
				continue;
			}

			$done = self::write_reminders( $event, $day, $window );

			if ( null === $done ) {
				$held = true;
			} else {
				$written += $done;
			}
		}

		// The edge moves on only once every event of the window is written down: a run that left one
		// to its retry records where its window began, so the retry covers the same window.
		update_option( self::OPT_REMINDER_EDGE, $held ? $window['from'] : $window['until'], false );

		if ( $held ) {
			self::schedule_retry();
		}

		return $written;
	}

	/**
	 * Write every person on an event down as still to be told of the move just written, one row each
	 * of the move's kind at the version it raised, under the lock the move holds. The rows a move
	 * before it left are replaced, not added to, a reminder still to go among them: a person is told
	 * what the event is now, and one mail says it, a change's giving the new time and a cancel's
	 * taking the event off their calendar, while the sponsor's members hear of either from the page
	 * it was made on. An account that is gone gets no row, since nobody can tell it. The accounts are
	 * read in one query here, for the rows and for the sends after them.
	 *
	 * @param int    $event_id The event.
	 * @param int    $revision The version the move raised.
	 * @param string $kind     `ROW_CHANGE` or `ROW_CANCEL`.
	 */
	private static function write_down( $event_id, $revision, $kind ) {
		delete_post_meta( (int) $event_id, self::META_PENDING );

		$people = self::joiners( (int) $event_id );

		if ( array() === $people ) {
			return;
		}

		cache_users( $people );

		foreach ( $people as $person_id ) {
			if ( null !== self::person( $person_id ) ) {
				add_post_meta( (int) $event_id, self::META_PENDING, self::row_of( $person_id, $revision, $kind ) );
			}
		}
	}

	/**
	 * Tell the first `TELL_IN_REQUEST` people a move wrote down, in the request that made it, the
	 * drain put on the clock before the first of them: a press the host cuts off leaves whoever it
	 * had not reached to the drain. The event is read again after the write, so the mail says what
	 * the event now is.
	 *
	 * @param int $event_id The event, changed or canceled.
	 * @return array `told`, the sends the mail layer handed off, and `pending`, the rows left.
	 */
	private static function tell_in_request( $event_id ) {
		$event = self::fresh( (int) $event_id );
		$rows  = null === $event ? array() : self::pending_rows( $event );

		if ( array() === $rows ) {
			return array(
				'told'    => 0,
				'pending' => 0,
			);
		}

		self::schedule_drain();

		$now = array_slice( $rows, 0, self::TELL_IN_REQUEST );

		return array(
			'told'    => self::tell( $event, $now ),
			'pending' => count( $rows ) - count( $now ),
		);
	}

	/**
	 * Tell people on an event, from the rows written down for them: what became of it, a change with
	 * what the event now is or a cancel, or, the day before it, that it is coming up. Each in their
	 * own language and on their own clock; a change and a cancel with the calendar file that brings
	 * their calendar in line, a request at the event's version or the cancel, and a reminder with
	 * none, their calendar holding the event already. Nobody is named to anybody else: each file
	 * names the program and the person it goes to, and the mail the event and its sponsor, or the
	 * WordPress Credits Program as the host of an event of its own, or, for a member, how many have
	 * joined.
	 *
	 * Each row is taken off the event before its mail is handed off, and a row this request could not
	 * take, because another request took it first or a later move replaced it, is left to whoever
	 * did: a request that dies part way tells nobody twice, and the one it can have missed is the
	 * person whose send it died in. A change's mail goes only to a person still on the event, and
	 * only while the event is neither over nor canceled: a drain that runs late takes those rows and
	 * sends nothing, since a new time for something past is noise, and a canceled event's own rows
	 * tell its people. A cancel's still goes, since its file takes the entry off the person's calendar
	 * and it is the only word they get. A reminder goes only while the event is approved and not
	 * over, to a person on it as such, with the call link, or to a live member of its sponsor who is
	 * not on it, with the count: who each is, is asked now, so a person who left since and a member
	 * the program detached since are told nothing.
	 *
	 * @param WP_Post $event The event, read after the move's write, or by the drain's run.
	 * @param array   $rows  Rows of `pending_rows()`.
	 * @return int How many were told: the sends the mail layer handed off.
	 */
	private static function tell( WP_Post $event, array $rows ) {
		$record  = self::sponsor_of( $event );
		$sponsor = self::sponsor_name( $record );
		$state   = self::state_of( $event );
		$on_it   = self::joiners( $event );
		$over    = self::end_of( $event ) <= self::now();
		$members = null;
		$told    = 0;

		foreach ( $rows as $row ) {
			$person_id = self::row_person( $row );
			$kind      = self::row_kind( $row, $state );
			$joined    = in_array( $person_id, $on_it, true );

			// Off the list before the send, and sent only by the request whose delete took the row.
			if ( ! delete_post_meta( (int) $event->ID, self::META_PENDING, $row ) ) {
				continue;
			}

			if ( self::ROW_REMINDER === $kind ) {
				// A reminder is for an approved event still to come, and for whoever is on it or acts
				// for its sponsor at the moment it is told; the members are read once, when a row asks.
				if ( $over || self::STATE_APPROVED !== $state ) {
					continue;
				}

				if ( ! $joined && null === $members ) {
					$members = self::member_ids( $record );
				}

				if ( ! $joined && ! in_array( $person_id, $members, true ) ) {
					continue;
				}

				$what = self::MAIL_REMINDED;
			} elseif ( self::ROW_CHANGE === $kind ) {
				// Somebody who left since the change was written down is not on it to be told, and
				// nobody is told of a change once the event is over or canceled.
				if ( $over || self::STATE_CANCELLED === $state || ! $joined ) {
					continue;
				}

				$what = self::MAIL_CHANGED;
			} else {
				$what = self::MAIL_CANCELED;
			}

			$id = self::tell_id( $event, $what, $joined );

			$told += WPCPM_Mail::send(
				$person_id,
				$id,
				static function ( WP_User $recipient ) use ( $event, $sponsor, $id, $what ) {
					return self::MAIL_REMINDED === $what ? self::reminder_mail( $event, $sponsor, $id, $recipient ) : self::joiner_mail( $event, $sponsor, $id, $recipient );
				}
			) ? 1 : 0;
		}

		return $told;
	}

	/**
	 * The template a mail of `tell()` is built from, by what happened, whose event it is and whether the
	 * person is on it: a reminder to one of the sponsor's members who is not on it is the sponsor's, and
	 * an event of the program's own says so in words of its own.
	 *
	 * @param WP_Post $event  The event.
	 * @param string  $what   MAIL_REMINDED, MAIL_CHANGED or MAIL_CANCELED.
	 * @param bool    $joined Whether the person is on the event.
	 * @return string The template's id in the catalog.
	 */
	private static function tell_id( WP_Post $event, $what, $joined ) {
		$program = self::is_program( $event ) ? '-program' : '';

		if ( self::MAIL_REMINDED === $what ) {
			return $joined ? 'event-reminder' . $program : 'event-reminder-sponsor';
		}

		return ( self::MAIL_CHANGED === $what ? 'event-changed' : 'event-canceled' ) . $program;
	}

	/**
	 * The rows an event holds of the people still to tell, in the order they were written: a move's in
	 * the order the people joined, the reminders' in the order `queue_reminders()` wrote them. A row
	 * this class does not write, a kind it does not know included, is left out, and left alone.
	 *
	 * @param WP_Post $event The event.
	 * @return string[] `<user ID>:<version>:<kind>` each, or `<user ID>:<version>` for a row written
	 *                  before rows carried a kind.
	 */
	private static function pending_rows( WP_Post $event ) {
		$rows    = array();
		$pattern = '/^[1-9][0-9]*:[0-9]+(?::(?:' . self::ROW_CHANGE . '|' . self::ROW_CANCEL . '|' . self::ROW_REMINDER . '))?$/';

		foreach ( (array) get_post_meta( (int) $event->ID, self::META_PENDING, false ) as $row ) {
			if ( is_string( $row ) && 1 === preg_match( $pattern, $row ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * The person a row still to tell is for.
	 *
	 * @param string $row A row of `pending_rows()`.
	 * @return int The user ID.
	 */
	private static function row_person( $row ) {
		return (int) explode( ':', (string) $row, 2 )[0];
	}

	/**
	 * What a row still to tell is about: its kind, or, for a row written before rows carried one, the
	 * change or the cancel the event's state says, the rule such a row was always told by.
	 *
	 * @param string $row   A row of `pending_rows()`.
	 * @param string $state The event's state.
	 * @return string `ROW_CHANGE`, `ROW_CANCEL` or `ROW_REMINDER`.
	 */
	private static function row_kind( $row, $state ) {
		$parts = explode( ':', (string) $row, 3 );

		if ( isset( $parts[2] ) ) {
			return $parts[2];
		}

		return self::STATE_CANCELLED === $state ? self::ROW_CANCEL : self::ROW_CHANGE;
	}

	/**
	 * A row still to tell, as the store writes it.
	 *
	 * @param int    $person_id The person.
	 * @param int    $revision  The version it is at.
	 * @param string $kind      `ROW_CHANGE`, `ROW_CANCEL` or `ROW_REMINDER`.
	 * @return string
	 */
	private static function row_of( $person_id, $revision, $kind ) {
		return (int) $person_id . ':' . (int) $revision . ':' . $kind;
	}

	/**
	 * Write the people to remind of one event down, under its lock, its sponsor's or the program's,
	 * and mark it. The event is read again there, and nothing is written unless it is still approved,
	 * still in the window and not marked, and still offered on the day, as `offers_events()` answers
	 * every reader: its sponsor's standing read then, and an event of the program's own offered
	 * while it is approved.
	 *
	 * @param WP_Post $event  The event, found in the window.
	 * @param string  $day    `Y-m-d`, the day its sponsor's standing is asked on.
	 * @param array   $window The run's window, `from` and `until` (`reminder_window()`).
	 * @return int|null How many people were written down, or null when another write held the
	 *                  event's lock, which leaves the event to the retry.
	 */
	private static function write_reminders( WP_Post $event, $day, array $window ) {
		$record = self::sponsor_of( $event );
		$lock   = self::lock_of( $event );

		if ( '' === $lock ) {
			return 0;
		}

		if ( ! self::lock( $lock ) ) {
			return null;
		}

		$event   = self::fresh( $event->ID );
		$due     = null !== $event && self::STATE_APPROVED === self::state_of( $event ) && self::in_window( $event, $window ) && ! self::reminded( $event );
		$offered = null !== $event && self::offers_events( $event, $day );

		if ( ! $due || ! $offered ) {
			self::unlock( $lock );

			return 0;
		}

		$revision = (int) get_post_meta( (int) $event->ID, self::META_REVISION, true );
		$people   = self::to_remind( $event, $record );

		// On the clock before the first row, as a move's own rows are: a run the host cuts off in the
		// walk leaves the rows it wrote, and their event's mark, to a drain minutes away rather than to
		// the next daily run, by which time most of those events have taken place.
		if ( array() !== $people ) {
			self::schedule_drain();
		}

		foreach ( $people as $person_id ) {
			add_post_meta( (int) $event->ID, self::META_PENDING, self::row_of( $person_id, $revision, self::ROW_REMINDER ) );
		}

		update_post_meta( (int) $event->ID, self::META_REMINDED, $revision );
		self::unlock( $lock );

		return count( $people );
	}

	/**
	 * Who is reminded of an event, as user IDs: the people on it, in the order they joined, then the
	 * sponsor's live members who are not on it, so a person who is both comes once; for an event of
	 * the program's own, whose sponsor is nobody's key and has no members, the people on it alone.
	 * An account that is gone is nobody, as for a move's rows, and the people on the event are read
	 * in one query.
	 *
	 * @param WP_Post $event  The event, read under the lock.
	 * @param string  $record Its sponsor's key, or '' for the program's own.
	 * @return int[]
	 */
	private static function to_remind( WP_Post $event, $record ) {
		$on_it  = self::joiners( $event );
		$people = array();

		if ( array() !== $on_it ) {
			cache_users( $on_it );
		}

		foreach ( $on_it as $person_id ) {
			if ( null !== self::person( $person_id ) ) {
				$people[] = $person_id;
			}
		}

		foreach ( self::member_ids( $record ) as $member_id ) {
			if ( ! in_array( $member_id, $people, true ) ) {
				$people[] = $member_id;
			}
		}

		return $people;
	}

	/**
	 * The user IDs of a sponsor's live members, as the memberships read them: the accounts that act
	 * for it now.
	 *
	 * @param string $record Sponsor key.
	 * @return int[]
	 */
	private static function member_ids( $record ) {
		$ids = array();

		foreach ( WPCPM_Sponsor_Members::members_of( $record ) as $member ) {
			$ids[] = (int) $member->ID;
		}

		return $ids;
	}

	/**
	 * The daily run's window, as the `from` moment, included, and the `until` moment, not.
	 *
	 * It ends `REMIND_UNTIL` hours after the run. It starts `REMIND_FROM` hours after the run, or at
	 * the edge on record (`OPT_REMINDER_EDGE`), where the last complete window stopped, when that is
	 * sooner: on the daily cadence the two are one moment, and after a late run the edge is the
	 * sooner, so the band the window before stopped short of is covered. Never before a second after
	 * the run's own moment, though: an event that has started is past reminding. With no edge on
	 * record, as on the first run ever, it starts `REMIND_FROM` hours after the run.
	 *
	 * @param int $now The run's moment.
	 * @return array{from: int, until: int}
	 */
	private static function reminder_window( $now ) {
		$edge = get_option( self::OPT_REMINDER_EDGE, '' );
		$from = $now + self::REMIND_FROM * HOUR_IN_SECONDS;

		if ( is_numeric( $edge ) && (int) $edge < $from ) {
			$from = max( (int) $edge, $now + 1 );
		}

		return array(
			'from'  => $from,
			'until' => $now + self::REMIND_UNTIL * HOUR_IN_SECONDS,
		);
	}

	/**
	 * Whether an event starts inside a run's window (`reminder_window()`): from its `from`, included,
	 * to its `until`, not.
	 *
	 * @param WP_Post $event  The event.
	 * @param array   $window `from` and `until`, timestamps.
	 * @return bool
	 */
	private static function in_window( WP_Post $event, array $window ) {
		$start = self::start_of( $event );

		return $start >= $window['from'] && $start < $window['until'];
	}

	/**
	 * Put the reminders' retry on the clock, `RETRY_GAP` from now, unless one is due already: one
	 * retry serves every event a run left to it.
	 */
	private static function schedule_retry() {
		if ( false === wp_next_scheduled( self::CRON_RETRY ) ) {
			wp_schedule_single_event( self::now() + self::RETRY_GAP, self::CRON_RETRY );
		}
	}

	/**
	 * Whether an event's people were reminded of it already: it holds the mark, whatever version the
	 * mark names, 0 included.
	 *
	 * @param WP_Post $event The event.
	 * @return bool
	 */
	private static function reminded( WP_Post $event ) {
		return '' !== (string) get_post_meta( (int) $event->ID, self::META_REMINDED, true );
	}

	/**
	 * Put the drain on the clock, `DRAIN_GAP` from now, unless a run is due already: one event serves
	 * every event's rows.
	 */
	private static function schedule_drain() {
		if ( false === wp_next_scheduled( self::CRON_DRAIN ) ) {
			wp_schedule_single_event( self::now() + self::DRAIN_GAP, self::CRON_DRAIN );
		}
	}

	/**
	 * The mail to one person on an event that changed or was canceled, from its template
	 * (`event-changed`, `event-canceled`, or their `-program` copies for an event of the program's
	 * own): what happened, which event, whose (its sponsor, or the WordPress Credits Program as its
	 * host, in the recipient's language), and when on their own clock with the zone named; a change
	 * adds how long it now runs and the call link, which a person on the event holds already. The
	 * calendar file goes with it, their own, naming them, and the sentence about the file only when
	 * there is one. Never the program's note to the sponsor, and never anybody else on the event or a
	 * member of the sponsor. The title is the one typed, read back by the registry.
	 *
	 * @param WP_Post $event     The event, read after the write.
	 * @param string  $sponsor   The sponsor's name.
	 * @param string  $id        The template's id (`tell_id()`).
	 * @param WP_User $recipient The person.
	 * @return array `subject`, `body`, `attachments` and `cleanup`.
	 */
	private static function joiner_mail( WP_Post $event, $sponsor, $id, WP_User $recipient ) {
		$facts   = self::facts( $event );
		$changed = 0 === strpos( $id, 'event-changed' );
		$files   = self::calendar_file( $event, $changed ? WPCPM_ICS::METHOD_REQUEST : WPCPM_ICS::METHOD_CANCEL, $recipient );
		$values  = self::mail_values( $facts, self::link_of( $event ), WPCPM_Mentor_Availability::viewer_timezone( (int) $recipient->ID ), $sponsor );

		$values['calendar_note'] = array() === $files ? '' : ( $changed
			? __( 'The calendar file attached replaces the one you had.', 'wpcredits-program-manager' )
			: __( 'The calendar file attached takes it off your calendar.', 'wpcredits-program-manager' ) );

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
	 * The reminder to one person the day before an event, in their language, from its template: which
	 * event, and when on their own clock with the zone named; to a person on it (`event-reminder`, or
	 * `event-reminder-program` for an event of the program's own), whose event it is, how long it runs
	 * and the call link, which they hold already; to one of the sponsor's members who is not on it
	 * (`event-reminder-sponsor`), how many people have joined so far, or that nobody has yet, never
	 * who, and no link: the members wrote it, and their approval mail and the event's Change form on
	 * the Sponsor Dashboard hold it. No calendar file: the person's calendar holds the event already,
	 * from the mail that put it there. Never the description, and never anybody else's name.
	 *
	 * @param WP_Post $event     The event, read by the drain's run.
	 * @param string  $sponsor   The sponsor's name.
	 * @param string  $id        The template's id (`tell_id()`).
	 * @param WP_User $recipient The person.
	 * @return array `subject`, `body`, `attachments` and `cleanup`.
	 */
	private static function reminder_mail( WP_Post $event, $sponsor, $id, WP_User $recipient ) {
		$facts  = self::facts( $event );
		$values = self::mail_values( $facts, self::link_of( $event ), WPCPM_Mentor_Availability::viewer_timezone( (int) $recipient->ID ), $sponsor );

		// Nobody is said in words, as the dashboards' cards say it, rather than as a count of none.
		$values['joined_so_far'] = (int) $facts['joined'] > 0
			? sprintf(
				/* translators: %s: how many people have joined the event so far. */
				_n( '%s person', '%s people', (int) $facts['joined'], 'wpcredits-program-manager' ),
				number_format_i18n( (int) $facts['joined'] )
			)
			: __( 'nobody yet', 'wpcredits-program-manager' );

		return WPCPM_Mail_Templates::mail(
			$id,
			$values,
			array(
				'attachments' => array(),
				'cleanup'     => array(),
			)
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * Uninstall
	 * --------------------------------------------------------------------
	 */

	/**
	 * Remove every event, every lock and the reminders' edge, and the places of the drain and of the
	 * reminders' retry on the clock, and nothing else.
	 *
	 * Every status the sponsors store walks, the trash included, rather than `any`, which leaves the
	 * trash out: an event somebody trashed by hand would otherwise outlive the plugin with its link in
	 * it. Each post goes with its meta, the people on it, the rows still to tell them (`META_PENDING`)
	 * and the mark of its reminders (`META_REMINDED`) included, the program's own events among them.
	 * The locks are found by their prefix, since a lock is named after a sponsor the store may already
	 * have forgotten, and the program's own lock (`PROGRAM_LOCK`) goes with them. The edge
	 * (`OPT_REMINDER_EDGE`) goes with the events it was kept for, and the drain's event (`CRON_DRAIN`)
	 * and the reminders' retry (`CRON_RETRY`) come off the clock, so nothing of the plugin's stays on
	 * the site's schedule.
	 */
	public static function delete_all() {
		global $wpdb;

		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => WPCPM_Sponsors_Index::POST_STATUSES,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( (array) $found as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rows are addressable only by exact name; this runs from uninstall.
		$locks = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LOCK_PREFIX ) . '%' ) );

		foreach ( (array) $locks as $name ) {
			delete_option( (string) $name );
		}

		delete_option( self::OPT_REMINDER_EDGE );
		wp_clear_scheduled_hook( self::CRON_DRAIN );
		wp_clear_scheduled_hook( self::CRON_RETRY );
	}

	/*
	 * --------------------------------------------------------------------
	 * Internals
	 * --------------------------------------------------------------------
	 */

	/**
	 * Every key the store writes, and its type.
	 *
	 * @return array<string, string>
	 */
	private static function meta_types() {
		return array(
			self::META_SPONSOR  => 'string',
			self::META_START    => 'integer',
			self::META_END      => 'integer',
			self::META_ZONE     => 'string',
			self::META_MINUTES  => 'integer',
			self::META_PLACES   => 'integer',
			self::META_KIND     => 'string',
			self::META_LINK     => 'string',
			self::META_STATE    => 'string',
			self::META_DECISION => 'array',
			self::META_REVISION => 'integer',
			self::META_PERSON   => 'integer',
			self::META_PENDING  => 'string',
			self::META_REMINDED => 'integer',
		);
	}

	/**
	 * The current Unix time: the real clock, or the suite's.
	 *
	 * @return int
	 */
	private static function now() {
		return null !== self::$clock ? (int) call_user_func( self::$clock ) : time();
	}

	/**
	 * Take the event for a move under its lock, its sponsor's or the program's (`lock_of()`), read
	 * again there, or refuse.
	 *
	 * The shape every move shares: an event, somebody acting, the lock, a fresh read, and a state the
	 * move is for, and not over. On success the lock is held and the caller releases it, the lock
	 * `lock_of()` names for the event.
	 *
	 * @param WP_Post|int $post    The event.
	 * @param int         $user_id Who is acting.
	 * @param string[]    $states  The states the move is for.
	 * @return WP_Post|WP_Error The event read under the lock, or the refusal: `not_found`,
	 *                          `not_allowed`, `busy`, `cancelled`, `not_planned` or `past`.
	 */
	private static function open( $post, $user_id, array $states ) {
		$event = self::event( $post );
		$lock  = null === $event ? '' : self::lock_of( $event );

		if ( null === $event || '' === $lock ) {
			return self::refuse( 'not_found' );
		}

		if ( ! is_numeric( $user_id ) || (int) $user_id <= 0 ) {
			return self::refuse( 'not_allowed' );
		}

		if ( ! self::lock( $lock ) ) {
			return self::refuse( 'busy' );
		}

		$event   = self::fresh( $event->ID );
		$refusal = '';

		if ( null === $event ) {
			$refusal = 'not_found';
		} elseif ( self::STATE_CANCELLED === self::state_of( $event ) ) {
			$refusal = 'cancelled';
		} elseif ( ! in_array( self::state_of( $event ), $states, true ) ) {
			$refusal = 'not_planned';
		} elseif ( self::end_of( $event ) <= self::now() ) {
			$refusal = 'past';
		}

		if ( '' !== $refusal ) {
			self::unlock( $lock );

			return self::refuse( $refusal );
		}

		return $event;
	}

	/**
	 * The name an event's writes are locked under, after `LOCK_PREFIX`: its sponsor's key, one lock
	 * per sponsor, or `PROGRAM_LOCK` for an event of the program's own; '' for an event that is
	 * neither, which takes no write.
	 *
	 * @param WP_Post $event The event.
	 * @return string
	 */
	private static function lock_of( WP_Post $event ) {
		if ( self::is_program( $event ) ) {
			return self::PROGRAM_LOCK;
		}

		$record = self::sponsor_of( $event );

		return WPCPM_Sponsors_Index::is_key( $record ) ? $record : '';
	}

	/**
	 * The event a value names, or null: a post, or a post ID, that is an event.
	 *
	 * @param WP_Post|int $post Anything.
	 * @return WP_Post|null
	 */
	private static function event( $post ) {
		if ( ! $post instanceof WP_Post ) {
			$post = is_numeric( $post ) && (int) $post > 0 ? get_post( (int) $post ) : null;
		}

		return self::is_event( $post ) ? $post : null;
	}

	/**
	 * An event read again from the table: this request's copy dropped first, so a state, a list of
	 * people or a start another request wrote since is the one read under the lock.
	 *
	 * @param int $post_id The event.
	 * @return WP_Post|null
	 */
	private static function fresh( $post_id ) {
		self::forget( $post_id );

		return self::event( (int) $post_id );
	}

	/**
	 * Drop this request's copy of an event and its meta.
	 *
	 * @param int $post_id The event.
	 */
	private static function forget( $post_id ) {
		wp_cache_delete( (int) $post_id, 'posts' );
		wp_cache_delete( (int) $post_id, 'post_meta' );
	}

	/**
	 * A sponsor's events, in every state, in no order. Never an event of the program's own, whatever
	 * key its rows hold: no sponsor's card, allowance or report counts one.
	 *
	 * @param string $record Sponsor key.
	 * @param bool   $fresh  Whether to drop this request's copy of each before it is read.
	 * @return WP_Post[] `array()` for a value that is not a key, which must never match every event
	 *                   whose own key is empty.
	 */
	private static function events_of( $record, $fresh = false ) {
		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_SPONSOR,
						'value' => $record,
					),
				),
			)
		);

		$events = array();

		foreach ( (array) $found as $post ) {
			if ( ! self::is_event( $post ) || self::is_program( $post ) ) {
				continue;
			}

			if ( $fresh ) {
				self::forget( $post->ID );
			}

			$events[] = $post;
		}

		return $events;
	}

	/**
	 * Every sponsor's events in one state that are not over, soonest first. The state and the end are
	 * asked of the query and again of each post, so a row the query let through by some other reading
	 * of a value is not listed.
	 *
	 * @param string $state One of the STATE_* constants.
	 * @return WP_Post[]
	 */
	private static function in_state( $state ) {
		$now   = self::now();
		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_STATE,
						'value' => $state,
					),
					array(
						'key'     => self::META_END,
						'value'   => $now,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$events = array();

		foreach ( (array) $found as $post ) {
			if ( self::is_event( $post ) && self::state_of( $post ) === $state && self::end_of( $post ) > $now ) {
				$events[] = $post;
			}
		}

		return self::soonest_first( $events );
	}

	/**
	 * Events by their start, the soonest first, the older post first on a tie.
	 *
	 * @param WP_Post[] $events Events.
	 * @return WP_Post[]
	 */
	private static function soonest_first( array $events ) {
		usort(
			$events,
			static function ( $a, $b ) {
				$order = self::start_of( $a ) - self::start_of( $b );

				return 0 !== $order ? $order : (int) $a->ID - (int) $b->ID;
			}
		);

		return array_values( $events );
	}

	/**
	 * An event's start.
	 *
	 * @param WP_Post $post The event.
	 * @return int A UTC timestamp.
	 */
	private static function start_of( WP_Post $post ) {
		return (int) get_post_meta( (int) $post->ID, self::META_START, true );
	}

	/**
	 * An event's end.
	 *
	 * @param WP_Post $post The event.
	 * @return int A UTC timestamp.
	 */
	private static function end_of( WP_Post $post ) {
		return (int) get_post_meta( (int) $post->ID, self::META_END, true );
	}

	/**
	 * The day a moment falls on, on the site's clock: the one a term's days are counted on.
	 *
	 * @param int $timestamp A UTC timestamp.
	 * @return string `Y-m-d`.
	 */
	private static function day_of( $timestamp ) {
		return (string) wp_date( 'Y-m-d', (int) $timestamp );
	}

	/**
	 * Whether a day falls inside a period, both ends included. Days compare as `Y-m-d` text.
	 *
	 * @param string $day   The day.
	 * @param string $from  The first day.
	 * @param string $until The last day.
	 * @return bool
	 */
	private static function within( $day, $from, $until ) {
		return strcmp( $day, $from ) >= 0 && strcmp( $day, $until ) <= 0;
	}

	/**
	 * The last day of the term running on a day, or '' when none runs: a sponsor with no valid
	 * `tier_until` has no term, and one whose last day is before that day has none any more.
	 *
	 * @param array  $row The sponsor's row.
	 * @param string $day `Y-m-d`.
	 * @return string `Y-m-d`, or ''.
	 */
	private static function running_until( array $row, $day ) {
		$until = self::term_until( $row );

		return '' !== $until && strcmp( $until, (string) $day ) >= 0 ? $until : '';
	}

	/**
	 * The last day of the sponsor's term as recorded, running or lapsed, or '' with no valid one.
	 *
	 * @param array $row The sponsor's row.
	 * @return string `Y-m-d`, or ''.
	 */
	private static function term_until( array $row ) {
		$until = isset( $row['tier_until'] ) ? (string) $row['tier_until'] : '';

		return WPCPM_Sponsor_Tiers::is_date( $until ) ? $until : '';
	}

	/**
	 * The last day of the term before a sponsor's term as recorded, as `term_from()` reads it, or ''.
	 *
	 * @param array $row The sponsor's row.
	 * @return string `Y-m-d`, or ''.
	 */
	private static function end_before( array $row ) {
		$until = self::term_until( $row );

		if ( '' === $until ) {
			return '';
		}

		$owed = WPCPM_Sponsor_Report::owed_of( $row );
		$ends = array(
			isset( $owed['until'] ) ? $owed['until'] : '',
			isset( $row['report_drafted_for'] ) && is_scalar( $row['report_drafted_for'] ) ? (string) $row['report_drafted_for'] : '',
		);

		// A row with no key finds no report: reports_of() answers nothing for a value that is not one.
		foreach ( WPCPM_Sponsor_Report::reports_of( isset( $row['record_id'] ) ? (string) $row['record_id'] : '' ) as $report ) {
			$ends[] = WPCPM_Sponsor_Report::until_of( $report );
		}

		$last = '';

		foreach ( $ends as $end ) {
			if ( WPCPM_Sponsor_Tiers::is_date( $end ) && strcmp( $end, $until ) < 0 && strcmp( $end, $last ) > 0 ) {
				$last = $end;
			}
		}

		return $last;
	}

	/**
	 * The term a start is counted in, as `from` and `until`, both '' while no term runs.
	 *
	 * The running term is the twelve months ending on its last day, the design's term, which the
	 * annual report reads through `WPCPM_Sponsor_Report::period()`. The first day `term_from()`
	 * records does not narrow those months: the design's term is the year ending on its last day,
	 * whatever the term before was, and a report drafted while a sponsor had no term, or for a day a
	 * program manager chose by hand, would otherwise pass for the end of a term and open a second
	 * event in the same twelve months. A start before that first day falls in the term before, which
	 * a renewal made before it ended leaves running to its own last day, and is counted in that
	 * term's twelve months. A question with no start is the running term's.
	 *
	 * @param array  $row   The sponsor's row.
	 * @param string $until The running term's last day, or '' for none.
	 * @param int    $start The start, a UTC timestamp, or 0 for none.
	 * @return array{from: string, until: string}
	 */
	private static function counted_term( array $row, $until, $start ) {
		if ( '' === $until ) {
			return array(
				'from'  => '',
				'until' => '',
			);
		}

		$end  = self::end_before( $row );
		$from = '' === $end ? '' : self::day_after( $end );

		if ( '' !== $from && $start > 0 && strcmp( self::day_of( $start ), $from ) < 0 ) {
			return WPCPM_Sponsor_Report::period( $end );
		}

		return WPCPM_Sponsor_Report::period( $until );
	}

	/**
	 * The day after a day, reckoned at noon in UTC as the report's period is, so that neither the
	 * timezone PHP runs in nor the site's can move it.
	 *
	 * @param string $day `Y-m-d`.
	 * @return string `Y-m-d`, or '' for a value that is not a day.
	 */
	private static function day_after( $day ) {
		$next = DateTime::createFromFormat( '!Y-m-d H:i:s', $day . ' 12:00:00', new DateTimeZone( 'UTC' ) );

		if ( false === $next ) {
			return '';
		}

		$next->modify( '+1 day' );

		return $next->format( 'Y-m-d' );
	}

	/**
	 * Whether a sponsor's tier includes the co-hosted event on a day: the tier the sponsor holds that
	 * day, so a term that has lapsed reads as a Friend's, which includes none. The one question
	 * the allowance and the lists both ask, so they cannot come to disagree about which tiers have
	 * events.
	 *
	 * @param array  $row The sponsor's row, read for `tier` and `tier_until`.
	 * @param string $day `Y-m-d`.
	 * @return bool
	 */
	private static function tier_offers( array $row, $day ) {
		return in_array( WPCPM_Sponsor_Tiers::EVENT_CO_HOSTED, WPCPM_Sponsor_Tiers::events( WPCPM_Sponsor_Tiers::effective( $row, $day ) ), true );
	}

	/**
	 * Whether a sponsor's row says it is in the program: Approved. The one status question the
	 * allowance and the lists both ask.
	 *
	 * @param array $row The sponsor's row, read for `status`.
	 * @return bool
	 */
	private static function is_approved( array $row ) {
		return isset( $row['status'] ) && WPCPM_Sponsors_Index::STATUS_APPROVED === (string) $row['status'];
	}

	/**
	 * The allowance's questions about a start: after the term's last day, before its first, or come.
	 * The two edges are two answers, since each is put to the sponsor as the day on the other side
	 * of it.
	 *
	 * @param string $until The term's last day, or '' for none.
	 * @param int    $start The start, a UTC timestamp.
	 * @param int    $now   The current moment.
	 * @param string $from  The first day a start may fall on, or '' for none: a change keeps an event
	 *                      of the running term in it (`change_floor()`), while a plan's start before
	 *                      that term is counted in the term before instead (`counted_term()`).
	 * @return string `past_term`, `before_term`, `in_past`, or ''.
	 */
	private static function start_refusal( $until, $start, $now, $from = '' ) {
		$day = self::day_of( $start );

		if ( '' !== $until && strcmp( $day, $until ) > 0 ) {
			return 'past_term';
		}

		if ( '' !== $from && strcmp( $day, $from ) < 0 ) {
			return 'before_term';
		}

		return $start <= $now ? 'in_past' : '';
	}

	/**
	 * Whether an event has no place left. No limit is never full.
	 *
	 * @param WP_Post $post The event.
	 * @return bool
	 */
	private static function is_full( WP_Post $post ) {
		$places = (int) get_post_meta( (int) $post->ID, self::META_PLACES, true );

		return $places > 0 && count( self::joiners( $post ) ) >= $places;
	}

	/**
	 * Whether a person is one of a sponsor's own members: the ground the sponsor policy grants them
	 * for planning its events is membership. Asked of the policy rather than of the roster's claim,
	 * which counts every refusal of a person who is not a member against a daily ceiling: asked
	 * about every student who joins, or on every list drawn, it would count refusals nobody made. A
	 * manager who is also a member is a manager here, as the policy has it. An event of the program's
	 * own, whose sponsor is nobody's key, is nobody's own: a sponsor's members join it as anybody in
	 * its audiences does.
	 *
	 * @param WP_User $person The person.
	 * @param string  $record Sponsor key, or '' for the program's own event.
	 * @return bool
	 */
	private static function is_own( WP_User $person, $record ) {
		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return false;
		}

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_PLAN_EVENT, WPCPM_Sponsor_Policy::subject_sponsor( $record ), $person );

		return ! empty( $decision['allowed'] ) && WPCPM_Sponsor_Policy::GROUND_MEMBER === $decision['ground'];
	}

	/**
	 * A person, or null: an account that exists, from an object, a positive ID, or null for the
	 * current user. A 0 is nobody, never the current user.
	 *
	 * @param int|WP_User|null $user The person.
	 * @return WP_User|null
	 */
	private static function person( $user ) {
		if ( null === $user ) {
			$user = wp_get_current_user();
		} elseif ( ! $user instanceof WP_User ) {
			$user = is_numeric( $user ) && (int) $user > 0 ? get_user_by( 'id', (int) $user ) : null;
		}

		return $user instanceof WP_User && $user->exists() ? $user : null;
	}

	/**
	 * The ground a person changes or cancels an event on: a manager's, else a member's, as the
	 * sponsor policy puts the manager first.
	 *
	 * @param int $user_id The person.
	 * @return string
	 */
	private static function ground_of( $user_id ) {
		return user_can( (int) $user_id, WPCPM_Roles::CAP_MANAGE ) ? WPCPM_Institution_Audit::GROUND_MANAGER : WPCPM_Institution_Audit::GROUND_MEMBER;
	}

	/**
	 * The program's decision on an event: the moment, the manager, and the note, if any.
	 *
	 * @param int    $user_id The manager.
	 * @param string $note    The note, or ''.
	 * @return array
	 */
	private static function decision( $user_id, $note ) {
		return array(
			'at'   => self::now(),
			'by'   => (int) $user_id,
			'note' => (string) $note,
		);
	}

	/**
	 * What a change alters of an event as read under the lock: `CHANGED_CALENDAR` when it moves what
	 * a calendar holds the event for, its start, its length, its title or its link;
	 * `CHANGED_DETAILS` when it alters the description or the places alone; '' when it alters
	 * nothing. The clock the form was filled in on is no change of its own: a member on another clock
	 * who saves the event as it stands posts the same moment.
	 *
	 * The title and the description are compared as they read. WordPress stores a bare ampersand
	 * typed by somebody without unfiltered HTML as an entity, and a named entity typed into a title,
	 * `&copy;` say, as typed, while the Change form hands the title back as the browser shows it, so
	 * an unedited save would otherwise read as a new title and tell everyone.
	 *
	 * @param WP_Post $event  The event, read under the lock.
	 * @param array   $values As `normalize()` answers them.
	 * @return string
	 */
	private static function what_changed( WP_Post $event, array $values ) {
		$facts = self::facts( $event );

		if ( $values['start'] !== $facts['start'] || $values['minutes'] !== $facts['minutes'] || ! WPCPM_Typed_Text::same_text( $values['title'], $facts['title'] ) || self::link_of( $event ) !== $values['link'] ) {
			return self::CHANGED_CALENDAR;
		}

		if ( $values['places'] !== $facts['places'] || ! WPCPM_Typed_Text::same_text( $values['description'], $facts['description'] ) ) {
			return self::CHANGED_DETAILS;
		}

		return '';
	}

	/**
	 * Raise an event's calendar version, under the lock, and answer it.
	 *
	 * @param int $post_id The event.
	 * @return int
	 */
	private static function next_revision( $post_id ) {
		$revision = (int) get_post_meta( (int) $post_id, self::META_REVISION, true ) + 1;

		update_post_meta( (int) $post_id, self::META_REVISION, $revision );

		return $revision;
	}

	/**
	 * Write the fields a plan and a change share.
	 *
	 * @param int   $post_id The event.
	 * @param array $values  As `normalize()` answers them.
	 */
	private static function write_fields( $post_id, array $values ) {
		update_post_meta( $post_id, self::META_START, $values['start'] );
		update_post_meta( $post_id, self::META_END, $values['start'] + $values['minutes'] * MINUTE_IN_SECONDS );
		update_post_meta( $post_id, self::META_ZONE, $values['zone'] );
		update_post_meta( $post_id, self::META_MINUTES, $values['minutes'] );
		update_post_meta( $post_id, self::META_PLACES, $values['places'] );
		update_post_meta( $post_id, self::META_LINK, wp_slash( $values['link'] ) );
	}

	/**
	 * One row on the sponsors module's audit trail about an event, read after the move: the day in
	 * words, and in its facts the start, the minutes, the places and how many people are on it.
	 * Never the link, the title, the description, the note or a name: the trail is about the act.
	 *
	 * An event of the program's own is no sponsor's, so its row is written through the audit's writer
	 * of the program's own rows (`WPCPM_Institution_Audit::record_program()`), of the same kind and
	 * the same facts, naming no sponsor; the sponsor's writer would refuse a row with no sponsor's
	 * key, and the act would go unrecorded. It is planned, changed and canceled, never approved or
	 * returned, so those are the words it has.
	 *
	 * @param string $kind    One of the LOG_* kinds.
	 * @param int    $post_id The event, the row's subject.
	 * @param int    $actor   Who acted.
	 * @param string $ground  `member` or `manager`.
	 */
	private static function audit( $kind, $post_id, $actor, $ground ) {
		$event = self::event( (int) $post_id );

		if ( null === $event ) {
			return;
		}

		$facts   = self::facts( $event );
		$day     = WPCPM_Sponsor_Tiers::format_day( self::day_of( $facts['start'] ) );
		$program = self::is_program( $event );

		switch ( $kind ) {
			case self::LOG_APPROVED:
				/* translators: %s: the day the sponsor event takes place, in the site's date format. */
				$message = sprintf( __( 'An Administrator approved the sponsor event on %s.', 'wpcredits-program-manager' ), $day );
				break;
			case self::LOG_RETURNED:
				/* translators: %s: the day the sponsor event was to take place, in the site's date format. */
				$message = sprintf( __( 'An Administrator returned the sponsor event on %s with a note.', 'wpcredits-program-manager' ), $day );
				break;
			case self::LOG_CHANGED:
				$message = $program
					/* translators: %s: the day the program's own event takes place, in the site's date format. */
					? sprintf( __( 'The program event was changed; it takes place on %s.', 'wpcredits-program-manager' ), $day )
					/* translators: %s: the day the sponsor event takes place, in the site's date format. */
					: sprintf( __( 'The sponsor event was changed; it takes place on %s.', 'wpcredits-program-manager' ), $day );
				break;
			case self::LOG_CANCELLED:
				$message = $program
					/* translators: %s: the day the program's own event was to take place, in the site's date format. */
					? sprintf( __( 'The program event on %s was canceled.', 'wpcredits-program-manager' ), $day )
					/* translators: %s: the day the sponsor event was to take place, in the site's date format. */
					: sprintf( __( 'The sponsor event on %s was canceled.', 'wpcredits-program-manager' ), $day );
				break;
			default:
				$message = $program
					/* translators: %s: the day the program's own event takes place, in the site's date format. */
					? sprintf( __( 'A program event was planned for %s.', 'wpcredits-program-manager' ), $day )
					/* translators: %s: the day the sponsor event takes place, in the site's date format. */
					: sprintf( __( 'A sponsor event was planned for %s.', 'wpcredits-program-manager' ), $day );
		}

		$data = array(
			'start'   => $facts['start'],
			'minutes' => $facts['minutes'],
			'places'  => $facts['places'],
			'joined'  => $facts['joined'],
		);

		if ( $program ) {
			WPCPM_Institution_Audit::record_program(
				array(
					'kind'     => $kind,
					'subject'  => (string) $facts['id'],
					'actor'    => max( 0, (int) $actor ),
					'ground'   => $ground,
					'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'  => $message,
					'data'     => $data,
				)
			);

			return;
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => $kind,
				'sponsor'  => $facts['sponsor'],
				'subject'  => (string) $facts['id'],
				'actor'    => max( 0, (int) $actor ),
				'ground'   => $ground,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $message,
				'data'     => $data,
			)
		);
	}

	/**
	 * Take an event's lock: a sponsor's, or the program's own (`lock_of()` names which).
	 *
	 * The held value is read from the options table itself, not through `get_option()`. On a site
	 * with a persistent object cache, as the live site has, the names a request found absent are kept
	 * in a list the cache shares (`notoptions`), and a request that read that list before another
	 * request took the lock still holds the name as absent: `get_option()` answers from the list
	 * before it asks anything else, and `add_option()` skips its own look for a name on it, writes the
	 * row with `INSERT ... ON DUPLICATE KEY UPDATE` and reports a win by the rows that write affected,
	 * two when it wrote over another request's lock. A lock read through them could be taken while
	 * another request holds it, the daily run's one request most of all, and the holder's release
	 * would then free it for a third. Read from the table, a lock that is held is refused; one older
	 * than `LOCK_TIMEOUT` belonged to a request that died holding it and is deleted; and
	 * `add_option()` then takes it, the moment it was taken as its value. The sponsor reports take
	 * their lock the same way (`WPCPM_Sponsor_Report::lock()`).
	 *
	 * `add_option()` as the test-and-set is the project's accepted primitive for this, the one the
	 * group sessions take their lock with, and it is still not a strict mutex (the deep check of
	 * 1.109.1, SESSIONS-8): two claims made in the moment between one request's read and its insert
	 * can both be told they won, each writing a different time; and two requests that both find a
	 * lock older than `LOCK_TIMEOUT` each delete it and claim it, the second deleting the first's
	 * fresh row.
	 *
	 * Both windows are a few milliseconds wide, and what is lost when one is hit is bounded: an event
	 * one person over its places, a sponsor with two events planned at once, or two writes that share
	 * one calendar version, the second file then outranking nothing a calendar already holds.
	 *
	 * @param string $record The lock's name after `LOCK_PREFIX`: a sponsor key, or `PROGRAM_LOCK`.
	 * @return bool Whether the lock was taken.
	 */
	private static function lock( $record ) {
		global $wpdb;

		$name = self::LOCK_PREFIX . $record;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The row itself, past this request's copy of the options: see above.
		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

		if ( null !== $held ) {
			if ( ( self::now() - (int) $held ) <= self::LOCK_TIMEOUT ) {
				return false;
			}

			delete_option( $name );
		}

		return (bool) add_option( $name, self::now(), '', false );
	}

	/**
	 * Give an event's lock back: a sponsor's, or the program's own.
	 *
	 * @param string $record The lock's name after `LOCK_PREFIX`: a sponsor key, or `PROGRAM_LOCK`.
	 */
	private static function unlock( $record ) {
		delete_option( self::LOCK_PREFIX . $record );
	}

	/**
	 * The program's sender as a calendar organizer, or null when it is no address: who the mail a
	 * calendar file arrives with comes from, as the mail layer reads it (`WPCPM_Mail::sender()`),
	 * never an address a setting holds for another purpose, which a manager may fill in with their
	 * own. The calendar builder names its organizer from an account's address and display name, and
	 * the sender is no account, so an empty one carries the two.
	 *
	 * @return WP_User|null
	 */
	private static function organizer() {
		$sender  = WPCPM_Mail::sender();
		$address = trim( $sender['email'] );

		if ( ! is_email( $address ) ) {
			return null;
		}

		$organizer               = new WP_User();
		$organizer->user_email   = $address;
		$organizer->display_name = trim( $sender['name'] );

		return $organizer;
	}

	/**
	 * The fields `plan()` and `change()` are handed, typed and checked against the limits again, or
	 * null when any is out of them: a caller that skipped `fields_from()` writes nothing the form
	 * would have refused.
	 *
	 * @param array $fields As `fields_from()` answers them.
	 * @return array|null
	 */
	private static function normalize( array $fields ) {
		$values = array(
			'start'       => isset( $fields['start'] ) && is_numeric( $fields['start'] ) ? (int) $fields['start'] : 0,
			'minutes'     => isset( $fields['minutes'] ) && is_numeric( $fields['minutes'] ) ? (int) $fields['minutes'] : 0,
			'places'      => isset( $fields['places'] ) && is_numeric( $fields['places'] ) ? (int) $fields['places'] : -1,
			'title'       => self::posted_text( $fields, 'title' ),
			'description' => self::posted_lines( $fields, 'description' ),
			'link'        => self::posted_link( $fields, 'link' ),
			'zone'        => WPCPM_Mentor_Availability::timezone( isset( $fields['zone'] ) && is_scalar( $fields['zone'] ) ? (string) $fields['zone'] : '' )->getName(),
		);

		if ( $values['start'] <= 0 || array() !== self::problems( $values ) ) {
			return null;
		}

		$values['link'] = self::clean_link( $values['link'] );

		return $values;
	}

	/**
	 * How long a value is as the person who typed it counted it, which is what its box counts:
	 * `WPCPM_Typed_Text::typed_length()`, the rule every box of typed text is measured by, this
	 * store's title and description among them (`MAX_TITLE`, `MAX_DESCRIPTION`); a note is counted
	 * by `WPCPM_Typed_Text::box_length()` of what was typed (`MAX_NOTE`).
	 * The store measures by that rule directly; this answers the callers that ask the store, as the
	 * suites that pin an event's limits do.
	 *
	 * @param string $value A title or a description, or another value a text cleaner read.
	 * @return int
	 */
	public static function typed_length( $value ) {
		return WPCPM_Typed_Text::typed_length( $value );
	}

	/**
	 * Why each field past the day and the time is refused: the limits, in the form's order.
	 *
	 * @param array $values `minutes`, `places`, `title`, `description` and `link` as read.
	 * @return array<string, string> Field => sentence.
	 */
	private static function problems( array $values ) {
		$problems = array();

		if ( $values['minutes'] < self::MIN_MINUTES || $values['minutes'] > self::MAX_MINUTES ) {
			$problems['minutes'] = sprintf(
				/* translators: 1: the shortest an event may run, in minutes, 2: the longest. */
				_n( 'The length has to be between %1$d and %2$d minute.', 'The length has to be between %1$d and %2$d minutes.', self::MAX_MINUTES, 'wpcredits-program-manager' ),
				self::MIN_MINUTES,
				self::MAX_MINUTES
			);
		}

		if ( $values['places'] < 0 || $values['places'] > self::MAX_PLACES ) {
			$problems['places'] = sprintf(
				/* translators: %d: the most places an event may have. */
				_n( 'Leave the places blank for no limit, or give from 1 to %d place.', 'Leave the places blank for no limit, or give from 1 to %d places.', self::MAX_PLACES, 'wpcredits-program-manager' ),
				self::MAX_PLACES
			);
		}

		if ( '' === $values['title'] ) {
			$problems['title'] = __( 'Give your event a title.', 'wpcredits-program-manager' );
		} elseif ( WPCPM_Typed_Text::typed_length( $values['title'] ) > self::MAX_TITLE ) {
			$problems['title'] = sprintf(
				/* translators: %d: the longest title, in characters. */
				_n( 'Keep the title to %d character.', 'Keep the title to %d characters.', self::MAX_TITLE, 'wpcredits-program-manager' ),
				self::MAX_TITLE
			);
		}

		if ( '' === $values['description'] ) {
			$problems['description'] = __( 'Say what your event is about.', 'wpcredits-program-manager' );
		} elseif ( WPCPM_Typed_Text::typed_length( $values['description'] ) > self::MAX_DESCRIPTION ) {
			$problems['description'] = sprintf(
				/* translators: %d: the longest description, in characters. */
				_n( 'Keep the description to %d character.', 'Keep the description to %d characters.', self::MAX_DESCRIPTION, 'wpcredits-program-manager' ),
				self::MAX_DESCRIPTION
			);
		}

		if ( '' === $values['link'] ) {
			$problems['link'] = __( 'Add the link people use to join the call.', 'wpcredits-program-manager' );
		} elseif ( mb_strlen( $values['link'] ) > self::MAX_LINK ) {
			$problems['link'] = sprintf(
				/* translators: %d: the longest call link, in characters. */
				_n( 'Keep the call link to %d character.', 'Keep the call link to %d characters.', self::MAX_LINK, 'wpcredits-program-manager' ),
				self::MAX_LINK
			);
		} elseif ( '' === self::clean_link( $values['link'] ) ) {
			$problems['link'] = __( 'The call link has to be a web address that starts with https:// or http://.', 'wpcredits-program-manager' );
		}

		return $problems;
	}

	/**
	 * A call link as stored, or '' for one that is not an http or https address core would make a
	 * request to (no account in it, a port core allows), or that is longer than `MAX_LINK`.
	 *
	 * @param string $raw The link as typed.
	 * @return string
	 */
	private static function clean_link( $raw ) {
		// Core's check resolves the link's host in DNS on the site, and a press asks it of the form's
		// link and again of the store's copy: the answer is kept for the request, under the link as
		// typed and as cleaned, so a press looks its host up once.
		static $known = array();

		$raw = trim( (string) $raw );

		if ( '' === $raw || mb_strlen( $raw ) > self::MAX_LINK ) {
			return '';
		}

		if ( isset( $known[ $raw ] ) ) {
			return $known[ $raw ];
		}

		$valid  = wp_http_validate_url( $raw );
		$scheme = is_string( $valid ) ? strtolower( (string) wp_parse_url( $valid, PHP_URL_SCHEME ) ) : '';
		$clean  = '';

		if ( in_array( $scheme, array( 'http', 'https' ), true ) ) {
			$clean = esc_url_raw( $valid, array( 'http', 'https' ) );
			$clean = mb_strlen( $clean ) <= self::MAX_LINK ? $clean : '';
		}

		$known[ $raw ] = $clean;

		if ( '' !== $clean ) {
			$known[ $clean ] = $clean;
		}

		return $clean;
	}

	/**
	 * A whole number from a form box: the fallback for a blank box, -1 for anything but digits, which
	 * every limit refuses.
	 *
	 * @param string $text  The box's text.
	 * @param int    $blank What a blank box means.
	 * @return int
	 */
	private static function whole( $text, $blank ) {
		if ( '' === $text ) {
			return (int) $blank;
		}

		return ctype_digit( $text ) ? (int) $text : -1;
	}

	/**
	 * One line of text from the form, cleaned; '' for a value that is no text, as a tampered form's
	 * list is.
	 *
	 * @param array  $posted The form's values.
	 * @param string $key    The field.
	 * @return string
	 */
	private static function posted_text( array $posted, $key ) {
		return isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? trim( sanitize_text_field( (string) $posted[ $key ] ) ) : '';
	}

	/**
	 * Text with its line breaks from the form, cleaned.
	 *
	 * @param array  $posted The form's values.
	 * @param string $key    The field.
	 * @return string
	 */
	private static function posted_lines( array $posted, $key ) {
		return isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? trim( sanitize_textarea_field( (string) $posted[ $key ] ) ) : '';
	}

	/**
	 * A link from the form, as typed: `clean_link()` is its cleaner.
	 *
	 * @param array  $posted The form's values.
	 * @param string $key    The field.
	 * @return string
	 */
	private static function posted_link( array $posted, $key ) {
		return isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? trim( (string) $posted[ $key ] ) : '';
	}

	/**
	 * A refusal, as the key and a sentence.
	 *
	 * The two refusals a sponsor waits out read as the planner says them, in the form's place and in
	 * its flash. While a term runs, the event still to take place is the term's, and it is the term
	 * a later plan waits for: "Your event for this term is planned already." For a running term the
	 * Sponsors screen's Tier box granted more events (`extra_events()`), the next event follows this
	 * one while the term can hold another after it, that is while the approved events the term has
	 * held (`held_in()` over its twelve months) are fewer than the ones granted, and once this one is
	 * the last the term holds, "Your events for this term are planned already." With no term, the
	 * next event follows this one. Once the term's event has taken place, it has, and for a term
	 * granted more, "Your events for this term have taken place already." None says how many events
	 * a term holds.
	 *
	 * A start outside the term is refused in words that name the day on the other side of the edge
	 * and the clock the term's days are read on (`edge_of()`), as the planner's flash says them: a
	 * sponsor who picked the term's last day on their own clock is told why that day is refused,
	 * rather than told to pick what they picked.
	 *
	 * @param string $key Why.
	 * @param array  $row The sponsor's row, read for its term: a refusal of a plan or of a change
	 *                    passes it.
	 * @return WP_Error
	 */
	private static function refuse( $key, array $row = array() ) {
		$edge = self::edge_of( $key, $row );

		if ( array() !== $edge ) {
			$sentence = 'past_term' === $key
				/* translators: 1: the last day of the sponsor's term, in the site's date format, 2: the site's time zone, e.g. Europe / Warsaw. */
				? __( 'Choose a start on or before %1$s, the last day of your sponsorship term on the site\'s clock (%2$s).', 'wpcredits-program-manager' )
				/* translators: 1: the first day of the sponsor's current term, in the site's date format, 2: the site's time zone, e.g. Europe / Warsaw. */
				: __( 'Choose a start on or after %1$s, the first day of your current sponsorship term on the site\'s clock (%2$s).', 'wpcredits-program-manager' );

			return new WP_Error( $key, sprintf( $sentence, $edge[0], $edge[1] ) );
		}

		$running  = self::running_until( $row, WPCPM_Sponsor_Tiers::today() );
		$extra    = '' === $running ? 0 : self::extra_events( $row, $running );
		$next     = __( 'Your next event can be planned once this one has taken place.', 'wpcredits-program-manager' );
		$one_open = $next;

		if ( '' !== $running && 0 === $extra ) {
			$one_open = __( 'Your event for this term is planned already.', 'wpcredits-program-manager' );
		} elseif ( '' !== $running && 'one_open' === $key ) {
			// The term holds the tier's one and the extra ones: once this event has taken place the
			// term will have held one more than now, so another can follow while fewer are held than
			// were granted. Counted only for this refusal, since it reads the sponsor's events.
			$period   = WPCPM_Sponsor_Report::period( $running );
			$held     = count( self::held_in( isset( $row['record_id'] ) ? (string) $row['record_id'] : '', $period['from'], $period['until'] ) );
			$one_open = $held < $extra ? $next : __( 'Your events for this term are planned already.', 'wpcredits-program-manager' );
		}

		$sentences = array(
			'switch_off'  => __( 'Sponsor events are not open yet.', 'wpcredits-program-manager' ),
			'no_tier'     => __( 'Your sponsorship does not include a co-hosted event.', 'wpcredits-program-manager' ),
			'one_open'    => $one_open,
			'term_used'   => $extra > 0 ? __( 'Your events for this term have taken place already.', 'wpcredits-program-manager' ) : __( 'Your event for this term has taken place already.', 'wpcredits-program-manager' ),
			'in_past'     => __( 'That day and time have passed. Choose a time still to come.', 'wpcredits-program-manager' ),
			'busy'        => __( 'Another change to these events was going through. Try again in a moment.', 'wpcredits-program-manager' ),
			'fields'      => __( 'Some of the event\'s details are missing or not valid.', 'wpcredits-program-manager' ),
			'not_allowed' => __( 'That is not something your account can do here.', 'wpcredits-program-manager' ),
			'not_found'   => __( 'That event could not be found.', 'wpcredits-program-manager' ),
			'not_planned' => __( 'That event is not waiting for approval.', 'wpcredits-program-manager' ),
			'cancelled'   => __( 'That event was canceled.', 'wpcredits-program-manager' ),
			'past'        => __( 'That event has taken place.', 'wpcredits-program-manager' ),
			'shrink'      => __( 'More people have joined than that many places would hold.', 'wpcredits-program-manager' ),
			'note'        => __( 'Write a note to go with the event you return.', 'wpcredits-program-manager' ),
			'note_long'   => sprintf(
				/* translators: %d: the longest note a return keeps, in characters. */
				_n( 'Keep the note to %d character.', 'Keep the note to %d characters.', self::MAX_NOTE, 'wpcredits-program-manager' ),
				self::MAX_NOTE
			),
			'not_saved'   => __( 'The event could not be saved. Try again.', 'wpcredits-program-manager' ),
			'not_open'    => __( 'That event is not open to join.', 'wpcredits-program-manager' ),
			'audience'    => __( 'These events are for the program\'s students, mentors, Administrators and institutions.', 'wpcredits-program-manager' ),
			'own'         => __( 'Your company\'s own event is on your Sponsor Dashboard.', 'wpcredits-program-manager' ),
			'joined'      => __( 'You are on that event already.', 'wpcredits-program-manager' ),
			'full'        => __( 'That event is full.', 'wpcredits-program-manager' ),
			'not_joined'  => __( 'You are not on that event.', 'wpcredits-program-manager' ),
		);

		return new WP_Error( $key, isset( $sentences[ $key ] ) ? $sentences[ $key ] : $sentences['not_found'] );
	}
}
