<?php
/**
 * The Administrator Dashboard's cards: what each reads, what each draws.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every queue a program manager works, read once and drawn as cards.
 *
 * Each card reads through one static method on the class that owns the data and posts to
 * the handler that already exists (design of 4 September 2026, decision 2): nothing here
 * queries posts or options itself, so the page can never show a count the owner would
 * compute differently. `collect()` reads everything once, because the cards and `counts()` (the
 * waiting list on wp-admin's Overview and the abilities) read the same arrays.
 *
 * A card's head says what the card holds, read from those arrays, so a folded card still tells a
 * manager what is in it, and a card whose work has one home in wp-admin ends its body with the way to it,
 * the fold's foot (`render_screen_line()`): to the tab of the Institutions or the Sponsors screen that
 * holds the card's items, and to the card's own place on that tab where it has one, on every draw, an
 * empty card's too. Nothing is printed after the fold (task V1, ruling 2). The foot carries the card's
 * title after its words for a screen reader alone, so links that read alike on the page still say
 * which card each comes from. The Duplicated students card's home is the Student Duplicate Finder:
 * its foot leads there in the finder's own words.
 *
 * A list stops at self::LIMIT rows. Under five of them a line says how many more there are, from a
 * count read apart from the rows, and links nowhere (`render_more_line()`). On the Institution
 * applications card: under the open applications, how many more wait, and under the fold of
 * rejected and spam applications, which wait for no decision, how many more are kept, each from its
 * own total. On the Sponsor applications, Sponsor posts to review, Sponsor offers to review and
 * Sponsor Collaboration Agreements cards, under the list of what waits: how many more wait, from the
 * four sponsor queues' counts, each read as the Sponsors screen counts it (`sponsor_totals()`), the
 * applications in full, the posts, the offers and the agreements up to that screen's ceiling, past
 * which they print as its printer prints them (`WPCPM_Sponsors::queue_count()`). Each of those counts of
 * what waits is also the number beside its card's title and on its tile. The other lists this page
 * reads at self::LIMIT are windows by design and stop there without a word, and so does every
 * count read from them: the Collaboration Agreements card's returned and revoked lists, the Sponsor
 * Collaboration Agreements card's agreements out of force, the offers running low and the new
 * interests.
 *
 * The page draws the cards in three groups (`WPCPM_Administrators_Dashboard::groups()`, the design
 * the owner approved on 28 September 2026): a card waiting for a decision is drawn open while it
 * holds something, and a card to watch or a card of the program's numbers is drawn folded whatever
 * it holds, since those hold what a manager looks at rather than acts on, until a tile, a link or a
 * press on its title opens it.
 *
 * Sponsors' cards join in the Sponsor module's last phase; the ids here are the anchors
 * `WPCPM_Return::ANCHORS` names, so a decision posted from a card lands back on it.
 */
final class WPCPM_Administrators_Cards {
	/** Most rows a list draws before it says how many more there are. */
	const LIMIT = 50;
	/** Closed requests shown under the open ones. */
	const CLOSED_SHOWN = 20;
	/**
	 * How long a sync may go without a finished run, while the syncs run by themselves, before the
	 * Syncs and health card counts it among the syncs that need a look: two turns of the three-hour
	 * schedule every sync runs on, so one late or skipped run raises nothing. The row says six
	 * hours.
	 */
	const SYNC_WINDOW = 6 * HOUR_IN_SECONDS;
	/**
	 * What every card on this page asks of the dashboards' shell, `WPCPM_Dashboard_Card`, besides
	 * its own key, title, count, open state, phrase and action: the anchor `wpcpm-<key>` on the
	 * card's section, where the mails, the wp-admin screens and every decision's way back land, and
	 * the page's classes on the section and on the disclosure, which this page's stylesheets key
	 * on.
	 */
	const SHELL = array(
		'prefix'        => 'wpcpm-',
		'section_class' => 'wpcpm-administrator__card',
		'details_class' => 'wpcpm-administrator__disclosure',
		'id_on'         => 'section',
		// The heading's words carry an id, `title_id()`, which names a card's tables (1.122.35).
		'title_id'      => true,
	);

	/*
	 * --------------------------------------------------------------------
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * Every dataset the page draws, read once.
	 *
	 * @return array
	 */
	public static function collect() {
		$now    = time();
		$review = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) ) * DAY_IN_SECONDS;

		// Read once: a test institution's items are listed and left out of every number, the overdue
		// counts included, and the index is one option unserialized on each read.
		$tests    = array_fill_keys( WPCPM_Test_Institutions::test_ids(), true );
		$awaiting = array();
		$overdue  = 0;

		foreach ( WPCPM_Institution_Agreement::awaiting_review( 200 ) as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$at     = (int) get_post_time( 'U', true, $post );
			$late   = ( $now - $at ) > $review;
			$record = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true );

			if ( $late && ! isset( $tests[ trim( $record ) ] ) ) {
				++$overdue;
			}

			$awaiting[] = array(
				'id'      => (int) $post->ID,
				'record'  => $record,
				'at'      => $at,
				'overdue' => $late,
			);
		}

		$open_requests   = array();
		$overdue_request = 0;

		foreach ( WPCPM_Institution_Request::open_requests( 200 ) as $request_id ) {
			$facts = WPCPM_Institution_Request::facts( (int) $request_id );

			if ( empty( $facts ) ) {
				continue;
			}

			if ( ! empty( $facts['overdue'] ) && ! isset( $tests[ trim( (string) $facts['institution'] ) ] ) ) {
				++$overdue_request;
			}

			$open_requests[] = $facts;
		}

		$closed_requests = array();

		foreach ( WPCPM_Institution_Request::closed_requests( self::CLOSED_SHOWN ) as $request_id ) {
			$facts = WPCPM_Institution_Request::facts( (int) $request_id );

			if ( ! empty( $facts ) ) {
				$closed_requests[] = $facts;
			}
		}

		// "This semester" is the current half-year, the same window the semester report, the
		// roster strip and the Sponsors tile use, so none of them can disagree about what the
		// phrase means: one helper answers it for the whole page.
		$from = self::semester_start()['from'];

		// Two questions, asked apart: the rows to draw, bounded at self::LIMIT, and the totals,
		// which are IDs. Asking for the rows to count them loaded every rejected application of
		// the year and every spam row of the month as a WP_Post - priming the meta cache with
		// each applicant's whole submitted form - to throw all but fifty away (deep check
		// FADMN-2). The sponsor half of this method has asked for its rows bounded since S5.
		$open_states   = WPCPM_Institutions::open_states();
		$closed_states = array( WPCPM_Institution_Application::STATE_REJECTED, WPCPM_Institution_Application::STATE_SPAM );
		$open          = WPCPM_Institution_Application::applications( $open_states, self::LIMIT );
		$closed        = WPCPM_Institution_Application::applications( $closed_states, self::LIMIT );

		// The three sponsor queues: the rows each card draws, the oldest self::LIMIT, read as they
		// always were, and how many wait in each, read apart from them (`sponsor_totals()`).
		$sponsor_posts        = class_exists( 'WPCPM_Sponsor_Posts' ) ? WPCPM_Sponsor_Posts::pending_all( self::LIMIT ) : array();
		$sponsor_offers       = class_exists( 'WPCPM_Sponsor_Offer_Review' ) ? WPCPM_Sponsor_Offer_Review::waiting_all( self::LIMIT ) : array();
		$sponsor_agreements   = self::sponsor_agreements();
		$sponsor_applications = class_exists( 'WPCPM_Sponsor_Application' ) ? WPCPM_Sponsor_Application::queue_facts( self::LIMIT ) : array();

		return array(
			// Only self::LIMIT of each list is drawn: the wp-admin queue caps its own list at
			// WPCPM_Institutions::QUEUE_MAX, which is the same number, and a page reading the
			// same rows must never draw a "complete" list the queue would call partial. The
			// totals beside them say how many there are, so the card can say how many more each
			// list holds.
			'applications'         => array(
				'open'         => $open,
				'open_total'   => count( WPCPM_Test_Institutions::real_application_ids( WPCPM_Institution_Application::application_ids( $open_states ) ) ),
				'closed'       => $closed,
				'closed_total' => count( WPCPM_Test_Institutions::real_application_ids( WPCPM_Institution_Application::application_ids( $closed_states ) ) ),
			),
			'agreements'           => array(
				'awaiting' => $awaiting,
				'returned' => self::agreement_rows( WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_RETURNED, self::LIMIT ) ),
				'revoked'  => self::agreement_rows( WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_REVOKED, self::LIMIT ) ),
				'overdue'  => $overdue,
			),
			'reports'              => array(
				'queue'    => WPCPM_Semester_Report::queue(),
				'due'      => WPCPM_Semester_Report::due( wp_date( 'Y-m-d' ) ),
				'approved' => WPCPM_Semester_Report::approved_since( $from ),
			),
			'requests'             => array(
				'open'    => $open_requests,
				'closed'  => $closed_requests,
				'overdue' => $overdue_request,
			),
			'locked'               => WPCPM_Institution_Roster::locked_today(),
			// The students waiting to graduate (1.123.93): the roster rows at Pending graduation, oldest first, each with
			// when the students sync first saw it so (`graduation()`).
			'graduation'           => self::graduation(),
			// Sponsor posts waiting for review (Sponsors module, S3): the facts, never the posts.
			'sponsor_posts'        => $sponsor_posts,
			// Sponsor offers, and changes to live ones, waiting for review (1.123.18): the facts the
			// review class reads, never the offers.
			'sponsor_offers'       => $sponsor_offers,
			// The Events card (1.117.0): what waits for the program's OK and the approved events coming
			// up, read through the queue class: the facts, the call link and the names of the people on
			// each, for the managers this page is drawn for, and nothing while Sponsor tiers is off.
			'sponsor_events'       => class_exists( 'WPCPM_Sponsor_Event_Queue' ) ? WPCPM_Sponsor_Event_Queue::data() : array(),
			// Sponsor Collaboration Agreements waiting for review, and those out of force (S4, 1.96.1).
			'sponsor_agreements'   => $sponsor_agreements,
			// Sponsor applications waiting for a decision (Sponsors module, S5): the facts, never
			// the posts. The full card with the answers and the base matches is S6; this phase
			// draws the decisions, which spec 9.2 puts on this page.
			'sponsor_applications' => $sponsor_applications,
			// How many wait in each of the three, which its tile and its card print.
			'sponsor_totals'       => self::sponsor_totals( $sponsor_posts, $sponsor_agreements['review'], $sponsor_applications, $sponsor_offers ),
			// How many mentor applications wait for a decision (1.122.31): the number only, read as
			// IDs through the record class. They are decided on the Mentors screen's Waiting for
			// review tab, which their tile opens, and on no card here (design of 16 October 2026,
			// decision 9).
			'mentor_applications'  => class_exists( 'WPCPM_Mentor_Application' ) ? WPCPM_Mentor_Application::waiting_count() : 0,
			// Pools under their own threshold, what sponsors said lately, and the sponsors'
			// figures (Sponsors module, S6): read through the owning classes, never their rows.
			'offers_low'           => self::offers_low(),
			// The Student Duplicate Finder's last scan (1.102.0): its counts, never its rows.
			'duplicates'           => self::duplicates(),
			'interests'            => self::interests(),
			'sponsors'             => self::sponsors(),
			// Approved sponsors whose term ends within the next sixty days (Sponsors module,
			// S8): the facts, never the rows. A card to watch: a term about to end is a sponsor to
			// talk to before it does, and nothing on the card waits for a press.
			'tiers_ending'         => self::tiers_ending(),
			// The sponsors' annual reports (1.115.0): the drafts to finish, the terms that ended
			// with no report, and the reports marked final this semester, through the report's
			// store: the facts, never the posts. A card waiting for a decision, since a draft and a
			// term with no report each wait for a manager's press.
			'sponsor_reports'      => self::sponsor_reports(),
			'programs'             => self::programs(),
			// How the mentors use the site (1.122.26): the figures only, never the rows, read through
			// the class the Engagement tab and the command read too.
			'mentor_engagement'    => class_exists( 'WPCPM_Mentor_Engagement' ) ? WPCPM_Mentor_Engagement::report()['summary'] : array(),
			'cohort_timeline'      => class_exists( 'WPCPM_Cohort_Timeline' ) ? WPCPM_Cohort_Timeline::report() : array(),
			'health'               => self::health(),
			// The students gone quiet (student automation): the daily job's stored snapshot, one option
			// read, never worked out here. Empty while the switch is off.
			'quiet_students'       => class_exists( 'WPCPM_Student_Flags' ) ? WPCPM_Student_Flags::snapshot() : array(),
		);
	}

	/**
	 * The counts that need an Administrator today, one tile each, from the arrays the cards draw, in
	 * the order of the cards they link to, so a list of them reads the page from the top. The
	 * Administrator Dashboard draws no strip of them since Task 18b: wp-admin's Overview lists them
	 * (`WPCPM_Overview::render_waiting()`) and the abilities answer with them. What follows still
	 * calls them the strip's tiles, the order and the words they keep. Every card waiting for a decision has a tile, and three of the cards to
	 * watch: Offers running low, Duplicated students and Syncs and health, which has two, the syncs
	 * that need a look, counted as its row counts them, and the locked accounts it lists. New
	 * interests, Sponsor terms ending and the program's numbers have none. A count at zero draws no
	 * tile, so the strip says nothing is waiting only on a day the Syncs and health card says no
	 * sync needs a look. A tile's words begin with the title of the card it links to, the name
	 * wp-admin gives the queue, so a tile read out of the strip says what it counts; the two tiles of
	 * Syncs and health begin with what each counts instead, a part of what that card holds.
	 *
	 * Two cards count a part of their own queue, and that part folds into the card's tile rather
	 * than repeating the queue on a tile of its own: the requests from institutions open and how
	 * many of them are overdue, the Collaboration Agreements to review and how many of those are
	 * overdue.
	 * The Semester reports card's two counts, the reports to review and the semesters due for
	 * drafting, add up to its number and share one tile the same way. Such a tile is drawn while
	 * either of its counts is above zero; its number is its first count and its label names what it
	 * counts and says the rest, each count through a plural form of its own, so the tile reads
	 * "2 Requests from institutions open, 1 overdue". The semester reports' tile is led by the reports to
	 * review while there is one, and by the semesters due for drafting when there is none, so it
	 * never leads with a zero: "1 Semester reports due for drafting".
	 *
	 * `n` is a number on every tile, which a reader may compare with zero or with another. The three
	 * sponsor queues' tiles say how many wait, as `collect()` read them apart from their cards' rows.
	 * The posts' and the agreements' tiles carry beside it `shown`, the number as
	 * `WPCPM_Sponsors::queue_count()` prints it: past the Sponsors screen's ceiling, the ceiling and
	 * a plus sign. Every other tile prints its number as it stands, the applications' at any size.
	 *
	 * Past that ceiling a posts or agreements tile's `n` is what the count's read returned, one more
	 * than the ceiling: a lower bound, not the count. A sum of tiles that holds one is a lower bound
	 * too, and is not a number to print as a total.
	 *
	 * The mentor applications' tile is the one with no card on this page: they are decided on the
	 * Mentors screen, so the tile carries `url`, the address of its Waiting for review tab, in place
	 * of `card`. It stands between the institutions' tiles and the sponsors', beside the other two
	 * kinds of application, and like every tile it is drawn only while its count is above zero.
	 *
	 * @param array $data What `collect()` returned.
	 * @return array[] `label`, `n` and `card` per tile (`url` in place of `card` on the mentor
	 *                 applications' tile), and `shown` on the sponsor posts' and agreements' tiles,
	 *                 keyed by tile in the order the strip draws them; empty when nothing needs a
	 *                 manager.
	 */
	public static function counts( array $data ) {
		$review   = WPCPM_Test_Institutions::count_real_rows( $data['agreements']['awaiting'], 'record' );
		$overdue  = (int) $data['agreements']['overdue'];
		$drafts   = WPCPM_Test_Institutions::count_real_rows( $data['reports']['queue'], 'institution' );
		$due      = WPCPM_Test_Institutions::count_real_rows( $data['reports']['due'], 'institution' );
		$requests = WPCPM_Test_Institutions::count_real_rows( $data['requests']['open'], 'institution' );
		$late     = (int) $data['requests']['overdue'];
		$look     = self::health_row( isset( $data['health'] ) ? (array) $data['health'] : array() )['count'];
		$totals   = array_merge(
			array(
				'posts'        => 0,
				'agreements'   => 0,
				'applications' => 0,
			),
			isset( $data['sponsor_totals'] ) ? (array) $data['sponsor_totals'] : array()
		);

		// The cards waiting for a decision first, in the order the page draws them. A tile that
		// says two counts carries their sum as `sum`, which decides whether the tile is drawn
		// and is taken off it once it has.
		$tiles = array(
			'applications'         => array(
				'label' => __( 'Institution applications waiting', 'wpcredits-program-manager' ),
				// The total, not count() of the (possibly capped) list that is actually drawn:
				// the strip's number is a fact about the queue, and must not shrink just because
				// the card beneath it stopped listing every row.
				'n'     => isset( $data['applications']['open_total'] ) ? (int) $data['applications']['open_total'] : WPCPM_Test_Institutions::count_real_rows( (array) $data['applications']['open'], 'post_title' ),
				'card'  => 'applications',
			),
			'agreements'           => self::paired_tile(
				/* translators: %s: how many of the Collaboration Agreements waiting for review are overdue, as "1 overdue". The tile prints how many wait for review above these words, so the two read as one line, "2 Collaboration Agreements to review, 1 overdue". */
				_n( 'Collaboration Agreements to review, %s', 'Collaboration Agreements to review, %s', $review, 'wpcredits-program-manager' ),
				/* translators: %s: how many of the Collaboration Agreements waiting for review are overdue. */
				_nx( '%s overdue', '%s overdue', $overdue, 'of the Collaboration Agreements to review', 'wpcredits-program-manager' ),
				$review,
				$overdue,
				'agreements'
			),
			// Led by the reports to review while there is one; with none, by the semesters due for
			// drafting, so the tile's number is never a zero over words about something else.
			'reports'              => $drafts > 0
				? self::paired_tile(
					/* translators: %s: how many semesters are due for drafting, as "1 due for drafting". The tile prints how many reports wait for review above these words, so the two read as one line, "2 Semester reports to review, 1 due for drafting". */
					_n( 'Semester reports to review, %s', 'Semester reports to review, %s', $drafts, 'wpcredits-program-manager' ),
					/* translators: %s: how many semesters of an institution have ended with no report drafted yet. */
					_n( '%s due for drafting', '%s due for drafting', $due, 'wpcredits-program-manager' ),
					$drafts,
					$due,
					'reports'
				)
				: array(
					/* translators: the tile prints how many semesters of an institution have ended with no report drafted yet above these words, so the two read as one line, "2 Semester reports due for drafting". */
					'label' => _n( 'Semester reports due for drafting', 'Semester reports due for drafting', $due, 'wpcredits-program-manager' ),
					'n'     => $due,
					'card'  => 'reports',
				),
			'requests'             => self::paired_tile(
				/* translators: %s: how many of the open requests from institutions are overdue, as "1 overdue". The tile prints how many are open above these words, so the two read as one line, "2 Requests from institutions open, 1 overdue". */
				_n( 'Requests from institutions open, %s', 'Requests from institutions open, %s', $requests, 'wpcredits-program-manager' ),
				/* translators: %s: how many of the open requests from institutions are overdue. */
				_nx( '%s overdue', '%s overdue', $late, 'of the open requests from institutions', 'wpcredits-program-manager' ),
				$requests,
				$late,
				'requests'
			),
			// The students waiting to graduate (1.123.93), after the institutions' requests and before the mentor
			// applications, as their card stands: real ones only, a test institution's student left out.
			'graduation'           => array(
				'label' => __( 'Students waiting to graduate', 'wpcredits-program-manager' ),
				'n'     => self::graduation_real( isset( $data['graduation'] ) ? (array) $data['graduation'] : array() ),
				'card'  => 'graduation',
			),
			// Beside the other two kinds of application (1.122.31), between the institutions' tiles
			// and the sponsors', opening the queue they are decided in, as no card here lists them.
			'mentor_applications'  => array(
				'label' => __( 'Mentor applications waiting', 'wpcredits-program-manager' ),
				'n'     => isset( $data['mentor_applications'] ) ? (int) $data['mentor_applications'] : 0,
				'url'   => admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' ),
			),
			'sponsor_applications' => array(
				'label' => __( 'Sponsor applications waiting', 'wpcredits-program-manager' ),
				'n'     => (int) $totals['applications'],
				'card'  => 'sponsor-applications',
			),
			'sponsor_posts'        => array(
				'label' => __( 'Sponsor posts to review', 'wpcredits-program-manager' ),
				'n'     => (int) $totals['posts'],
				'shown' => WPCPM_Sponsors::queue_count( $totals['posts'] ),
				'card'  => 'sponsor-posts',
			),
			// The offers and changes waiting for review (1.123.18), right after the posts, as their
			// card follows the posts' card. Not a tier's, so drawn whatever the tiers switch says.
			'sponsor_offers'       => array(
				'label' => __( 'Sponsor offers to review', 'wpcredits-program-manager' ),
				'n'     => isset( $totals['offers'] ) ? (int) $totals['offers'] : 0,
				'shown' => WPCPM_Sponsors::queue_count( isset( $totals['offers'] ) ? (int) $totals['offers'] : 0 ),
				'card'  => 'sponsor-offers-review',
			),
		);

		// The sponsor events waiting for the program's OK (1.117.0), right after the posts, as
		// their card follows the posts' card. Only while its card is drawn: the tiers are not
		// announced, and a tile would count what no card shows.
		if ( class_exists( 'WPCPM_Sponsor_Event_Queue' ) && WPCPM_Sponsor_Event_Queue::shown() ) {
			$tiles['sponsor_events'] = array(
				'label' => __( 'Events waiting', 'wpcredits-program-manager' ),
				'n'     => WPCPM_Sponsor_Event_Queue::count_for_tile( isset( $data['sponsor_events'] ) ? (array) $data['sponsor_events'] : array() ),
				'card'  => 'sponsor-events',
			);
		}

		$tiles += array(
			'sponsor_agreements' => array(
				'label' => __( 'Sponsor Collaboration Agreements to review', 'wpcredits-program-manager' ),
				'n'     => (int) $totals['agreements'],
				'shown' => WPCPM_Sponsors::queue_count( $totals['agreements'] ),
				'card'  => 'sponsor-agreements',
			),
			// The two parts of the Sponsor reports card that ask for a press: the drafts to finish
			// and the terms that ended with no report. The reports marked final are a record, not a
			// queue, so they are not counted (1.115.0).
			'sponsor_reports'    => array(
				'label' => __( 'Sponsor reports to finish', 'wpcredits-program-manager' ),
				'n'     => self::sponsor_reports_open( isset( $data['sponsor_reports'] ) ? (array) $data['sponsor_reports'] : array() ),
				'card'  => 'sponsor-reports',
			),
			// Then the cards to watch that have a tile, in their order.
			'offers_low'         => array(
				'label' => __( 'Offers running low', 'wpcredits-program-manager' ),
				'n'     => isset( $data['offers_low'] ) ? count( (array) $data['offers_low'] ) : 0,
				'card'  => 'offers-low',
			),
			// The students the Student Duplicate Finder's last scan listed, Ready or not: the
			// number its own screen opens with (1.102.0).
			'duplicates'         => array(
				'label' => __( 'Duplicated students', 'wpcredits-program-manager' ),
				'n'     => isset( $data['duplicates']['addresses'] ) ? (int) $data['duplicates']['addresses'] : 0,
				'card'  => 'duplicates',
			),
			// The syncs that need a look, the count the Syncs and health card's row gives, so the
			// strip's sentence that nothing is waiting never sits over a card that says one does.
			'syncs'              => array(
				/* translators: the tile prints how many syncs need a look above these words, the count the Syncs and health card's row gives, so the two read as one line, "2 Syncs need a look". */
				'label' => _n( 'Sync needs a look', 'Syncs need a look', $look, 'wpcredits-program-manager' ),
				'n'     => $look,
				'card'  => 'health',
			),
			// The institution accounts locked for the rest of today, which the Syncs and health
			// card lists under its syncs.
			'locked'             => array(
				'label' => __( 'Locked accounts', 'wpcredits-program-manager' ),
				'n'     => WPCPM_Test_Institutions::count_real_accounts( (array) $data['locked'] ),
				'card'  => 'health',
			),
			// The students quiet for the set number of days, real ones only: a test institution's
			// student is listed on the card and left out of its number.
			'quiet_students'     => array(
				'label' => __( 'Students gone quiet', 'wpcredits-program-manager' ),
				'n'     => self::quiet_real( isset( $data['quiet_students'] ) ? (array) $data['quiet_students'] : array() ),
				'card'  => 'quiet-students',
			),
		);

		$drawn = array();

		foreach ( $tiles as $key => $tile ) {
			$sum = isset( $tile['sum'] ) ? (int) $tile['sum'] : (int) $tile['n'];

			unset( $tile['sum'] );

			if ( $sum > 0 ) {
				$drawn[ $key ] = $tile;
			}
		}

		return $drawn;
	}

	/**
	 * A tile that says two counts: its number the first, its label the words that name what it
	 * counts with the second count's own phrase in them, and `sum` their sum, which `counts()`
	 * draws the tile by. Each form comes translated for its own count, the plural forms keyed at the
	 * call, where the translators' notes sit beside them.
	 *
	 * @param string $label  The label for the first count, with %s where the second's phrase goes.
	 * @param string $phrase The second count's phrase, with %s where its number goes.
	 * @param int    $first  The tile's number.
	 * @param int    $second The second count.
	 * @param string $card   The key of the card the tile links to.
	 * @return array `label`, `n`, `card` and `sum`.
	 */
	private static function paired_tile( $label, $phrase, $first, $second, $card ) {
		return array(
			'label' => sprintf( $label, sprintf( $phrase, number_format_i18n( (int) $second ) ) ),
			'n'     => (int) $first,
			'card'  => $card,
			'sum'   => (int) $first + (int) $second,
		);
	}

	/**
	 * The sponsor Collaboration Agreements a manager can act on: the documents waiting for
	 * review and the agreements out of force, each as the facts the agreement class publishes.
	 *
	 * @return array{review: array[], revoked: array[]}
	 */
	private static function sponsor_agreements() {
		$out = array(
			'review'  => array(),
			'revoked' => array(),
		);

		if ( ! class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			return $out;
		}

		foreach ( WPCPM_Sponsor_Agreement::awaiting_review( self::LIMIT ) as $id ) {
			$facts = WPCPM_Sponsor_Agreement::review_facts( (int) $id );

			if ( ! empty( $facts ) ) {
				$out['review'][] = $facts;
			}
		}

		foreach ( WPCPM_Sponsor_Agreement::revoked_all( self::LIMIT ) as $id ) {
			$facts = WPCPM_Sponsor_Agreement::review_facts( (int) $id );

			if ( ! empty( $facts ) ) {
				$out['revoked'][] = $facts;
			}
		}

		return $out;
	}

	/**
	 * How many wait in each sponsor queue, the number its tile and its card print.
	 *
	 * Read apart from the rows the cards draw, each as the Sponsors screen counts the same queue: the
	 * applications in full, as its applications card counts them, every open one as an ID
	 * (`pending_count()` with no limit); the posts and the signed agreements as its cards for them
	 * count them, one more than `WPCPM_Sponsors::COUNT_MAX`, so either past that ceiling prints the
	 * ceiling and a plus sign. A class that is not there costs a zero. The rows and the count are two
	 * queries, and a row decided between them must not leave a card listing more than it counts, so a
	 * count is never fewer than its card's rows.
	 *
	 * @param array $posts        The rows the sponsor posts card draws.
	 * @param array $agreements   The documents the sponsor agreements card lists as waiting.
	 * @param array $applications The rows the sponsor applications card draws.
	 * @param array $offers       The rows the sponsor offers card draws.
	 * @return array{posts: int, agreements: int, applications: int, offers: int}
	 */
	private static function sponsor_totals( array $posts, array $agreements, array $applications, array $offers = array() ) {
		$read = WPCPM_Sponsors::COUNT_MAX + 1;

		return array(
			'posts'        => max( count( $posts ), class_exists( 'WPCPM_Sponsor_Posts' ) ? count( (array) WPCPM_Sponsor_Posts::pending_all( $read ) ) : 0 ),
			'offers'       => max( count( $offers ), class_exists( 'WPCPM_Sponsor_Offer_Review' ) ? count( (array) WPCPM_Sponsor_Offer_Review::waiting_all( $read ) ) : 0 ),
			'agreements'   => max( count( $agreements ), class_exists( 'WPCPM_Sponsor_Agreement' ) ? count( (array) WPCPM_Sponsor_Agreement::awaiting_review( $read ) ) : 0 ),
			'applications' => max( count( $applications ), class_exists( 'WPCPM_Sponsor_Application' ) ? (int) WPCPM_Sponsor_Application::pending_count() : 0 ),
		);
	}

	/**
	 * Sponsor Collaboration Agreements: each document waiting for review with who uploaded it,
	 * when, its size, what the scan noticed and how many accounts the company has, then the
	 * decision drawn by the agreement class; below, the agreements out of force with the way
	 * back, and last the way to the documents' own card on the Sponsors screen's queue tab. A
	 * waiting document is accepted or returned here and nowhere else, and an agreement out of force
	 * is reinstated here while this list holds it; the wp-admin Sponsors screen lists the documents
	 * to be read, and reinstates one past this list under the same action and nonce (spec 8.2;
	 * pulled forward from S6 at the owner's request, 1.96.1).
	 *
	 * The waiting documents listed are the oldest `LIMIT`. The card's count is every one waiting, as
	 * that screen's Signed agreements card counts them, and while more wait than the list holds, the
	 * line under it says how many more (`render_sponsor_more_line()`), above the agreements out of force.
	 *
	 * @param array    $data  `review` and `revoked`, each a list of `review_facts()` rows.
	 * @param int|null $total How many documents wait, as `collect()` counted them; null counts `review`.
	 */
	public static function render_sponsor_agreements( array $data, $total = null ) {
		$review  = isset( $data['review'] ) ? (array) $data['review'] : array();
		$revoked = isset( $data['revoked'] ) ? (array) $data['revoked'] : array();
		$screen  = self::sponsors_screen( 'queue', 'wpcpm-sponsor-agreements', __( 'Sponsor Collaboration Agreements', 'wpcredits-program-manager' ) );

		$total = null === $total ? count( $review ) : (int) $total;
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'sponsor-agreements',
					'title'  => __( 'Sponsor Collaboration Agreements', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'sponsor-agreement', __( 'Sponsor Collaboration Agreements', 'wpcredits-program-manager' ) ),
					'count'  => $total,
					'shown'  => WPCPM_Sponsors::queue_count( $total ),
					'open'   => $total > 0,
					'state'  => self::waiting( WPCPM_Sponsors::queue_number( $total ), WPCPM_Sponsors::queue_count( $total ) ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $review ) ) {
			self::empty_line( __( 'No sponsor agreement is waiting for review.', 'wpcredits-program-manager' ) );
		}

		self::render_sponsor_agreements_parts( $review, $revoked, $total );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Sponsor Collaboration Agreements card's body on the shared parts (design 2.10, 2.11, 4.1): each document waiting for review, then the line that says how many more
	 * wait, then today's heading "Out of force" over the agreements out of force, a list of rows, each drawn by
	 * `sponsor_agreement_row_parts()` with Reinstate at its end. The empty sentence before them and the way to the
	 * Sponsors screen after them are the caller's, as today.
	 *
	 * @param array[] $review  The documents waiting for review, `review_facts()` rows.
	 * @param array[] $revoked The agreements out of force, the same.
	 * @param int     $total   How many documents wait.
	 */
	private static function render_sponsor_agreements_parts( array $review, array $revoked, $total ) {
		foreach ( $review as $row ) {
			self::render_sponsor_agreement_parts( $row, __( 'Waiting for review', 'wpcredits-program-manager' ) );
		}

		self::render_sponsor_more_line( $total, count( $review ) );

		if ( empty( $revoked ) ) {
			return;
		}

		echo WPCPM_UI::heading( __( 'Out of force', 'wpcredits-program-manager' ), 'h4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		// A list of rows, each with Reinstate at its end (task V1, ruling 14), its facts under its name and tag at the
		// row's full width (Task 18d), so they are not squeezed into a column between the tag and Reinstate.
		$list = WPCPM_UI::list_open( array( 'facts' => 'under' ) );

		foreach ( $revoked as $row ) {
			$list .= self::sponsor_agreement_row_parts( $row );
		}

		echo $list . WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row() and reinstate_form().
	}

	/**
	 * One sponsor agreement on the shared parts: the item header (the company, not a link, as today; its state as the
	 * tag in today's words; today's facts; and for an uploaded copy the link "Download" as the last fact, since it
	 * decides nothing, design 2.6), then the decision the agreement class draws: one Decide area for a document
	 * waiting for review (an agreement out of force is a list row, `sponsor_agreement_row_parts()`). The item keeps its article and its anchor, which the
	 * Sponsors screen's links point at. The tag is neutral: nothing on this card is overdue today.
	 *
	 * @param array  $row  A `review_facts()` row.
	 * @param string $kind The state word, the item's tag.
	 */
	private static function render_sponsor_agreement_parts( array $row, $kind ) {
		$header = WPCPM_UI::item_header(
			array(
				'name'  => (string) $row['sponsor_name'],
				'tag'   => array(
					'words' => $kind,
					'tone'  => '',
				),
				'facts' => self::sponsor_agreement_facts( $row ),
			)
		);

		echo WPCPM_UI::item_open( 'wpcpm-sponsor-agreement', 'wpcpm-sponsor-agreement-' . (int) $row['post_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
		echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header(); the one html fact escapes its href and words.

		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			WPCPM_Sponsor_Agreement::render_decision( (int) $row['post_id'], WPCPM_Return::DASHBOARD );
		}

		echo '</article>';
	}

	/**
	 * One sponsor agreement out of force on the shared parts (design 2.8; task V1, ruling 14): a list row of a list with
	 * its facts under the name (Task 18d), the company its name, "Revoked" its neutral tag, the item's facts as the row's
	 * facts, and Reinstate, the quiet button, at the end of the row's first line like every row action. The row carries the item's anchor, which the Sponsors screen's links point at.
	 *
	 * @param array $row A `review_facts()` row of an agreement out of force.
	 * @return string Escaped markup.
	 */
	private static function sponsor_agreement_row_parts( array $row ) {
		$form = class_exists( 'WPCPM_Sponsor_Agreement' ) ? (string) WPCPM_Sponsor_Agreement::reinstate_form( (int) $row['post_id'], WPCPM_Return::DASHBOARD ) : '';

		return WPCPM_UI::row(
			array(
				'id'      => 'wpcpm-sponsor-agreement-' . (int) $row['post_id'],
				'name'    => (string) $row['sponsor_name'],
				'tag'     => array(
					'words' => __( 'Revoked', 'wpcredits-program-manager' ),
					'tone'  => '',
				),
				// A list row's date is kept whole (Task 18f); the waiting item's header keeps today's.
				'facts'   => self::sponsor_agreement_facts( $row, true ),
				'actions' => '' !== $form ? array( $form ) : array(),
			)
		);
	}

	/**
	 * A sponsor agreement's facts, in today's words: who uploaded it and when (the day in the site's date format, as the
	 * rest of the dashboard prints one; Task 18d), its size, what the scan noticed, how many accounts the company has,
	 * and for an uploaded copy the link "Download", naming its company to a screen reader.
	 *
	 * @param array $row   A `review_facts()` row.
	 * @param bool  $whole Whether the date is kept whole (a list row's facts; Task 18f).
	 * @return array The facts, as `WPCPM_UI::item_header()` and `row()` read them.
	 */
	private static function sponsor_agreement_facts( array $row, $whole = false ) {
		$facts = array();
		$day   = self::site_date( (string) $row['uploaded_at'] );
		$day   = $whole ? self::whole_text( $day ) : $day;

		if ( '' !== (string) $row['uploaded_by'] ) {
			/* translators: 1: the account's name, 2: a date. */
			$facts[] = sprintf( __( 'Uploaded by %1$s on %2$s', 'wpcredits-program-manager' ), (string) $row['uploaded_by'], $day );
		} elseif ( '' !== (string) $row['uploaded_at'] ) {
			/* translators: %s: a date. */
			$facts[] = sprintf( __( 'Recorded on %s', 'wpcredits-program-manager' ), $day );
		}

		if ( (int) $row['size'] > 0 ) {
			$facts[] = size_format( (int) $row['size'] );
		}

		if ( ! empty( $row['flags'] ) ) {
			$flags = array_map(
				static function ( $flag ) {
					return (string) $flag;
				},
				(array) $row['flags']
			);
			/* translators: %s: what the courtesy scan noticed, in words, each with the PDF's own name for it in brackets. */
			$facts[] = sprintf( __( 'The scan noticed: %s', 'wpcredits-program-manager' ), WPCPM_Pdf_Check::in_words( $flags ) );
		} elseif ( 'own' === (string) $row['kind'] ) {
			$facts[] = __( 'The scan noticed none of the features it looks for', 'wpcredits-program-manager' );
		}

		/* translators: %d: how many accounts the company has on the site. */
		$facts[] = sprintf( _n( '%d account', '%d accounts', (int) $row['members'], 'wpcredits-program-manager' ), (int) $row['members'] );

		$download = class_exists( 'WPCPM_Sponsor_Agreement' ) ? (string) WPCPM_Sponsor_Agreement::download_url( (int) $row['post_id'] ) : '';

		// A link that decides nothing joins the facts (design 2.6), in today's words, naming its company to a screen
		// reader, so two agreements' links on one card do not read alike.
		if ( '' !== $download ) {
			$facts[] = array(
				'html' => sprintf(
					'<a href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
					esc_url( $download ),
					esc_html__( 'Download', 'wpcredits-program-manager' ),
					esc_html( (string) $row['sponsor_name'] )
				),
			);
		}

		return $facts;
	}

	/**
	 * Sponsor posts waiting for review: each with its company and author, Preview, Publish and
	 * the folded Return with a note, the decision drawn by the posts class (spec 7.4), and last the
	 * way to the posts' own card on the Sponsors screen's queue tab.
	 *
	 * The posts listed are the oldest `LIMIT`. The card's count is every post waiting, as the
	 * Sponsors screen's card counts them, and while more wait than the list holds, the line under it
	 * says how many more (`render_sponsor_more_line()`).
	 *
	 * @param array[]  $rows  What `WPCPM_Sponsor_Posts::pending_all()` returned.
	 * @param int|null $total How many posts wait, as `collect()` counted them; null counts the rows.
	 */
	public static function render_sponsor_posts( array $rows, $total = null ) {
		$total  = null === $total ? count( $rows ) : (int) $total;
		$screen = self::sponsors_screen( 'queue', 'wpcpm-sponsor-posts', __( 'Sponsor posts to review', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'sponsor-posts',
					'title'  => __( 'Sponsor posts to review', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'sponsor-post', __( 'Sponsor posts to review', 'wpcredits-program-manager' ) ),
					'count'  => $total,
					'shown'  => WPCPM_Sponsors::queue_count( $total ),
					'open'   => $total > 0,
					'state'  => self::waiting( WPCPM_Sponsors::queue_number( $total ), WPCPM_Sponsors::queue_count( $total ) ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No sponsor post is waiting for review.', 'wpcredits-program-manager' ) );
		}

		foreach ( $rows as $row ) {
			self::render_sponsor_post_parts( $row );
		}

		self::render_sponsor_more_line( $total, count( $rows ) );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * One sponsor post on the shared parts (design 2.10, 2.6, 4.1): the item header (the title linked to its preview, as today; then the company, "Submitted ...", "By ..."
	 * and the link "Preview the post" as muted facts, Preview having left the decision's row), then the decision the
	 * posts class draws, one Decide area. The item keeps its article and its anchor, as today.
	 *
	 * @param array $row A `WPCPM_Sponsor_Posts::pending_all()` row.
	 */
	private static function render_sponsor_post_parts( array $row ) {
		$facts = array(
			(string) $row['company'],
			/* translators: %s: how long ago. */
			sprintf( __( 'Submitted %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['at'], time() ) ),
		);

		if ( '' !== (string) $row['author'] ) {
			/* translators: %s: the account's name. */
			$facts[] = sprintf( __( 'By %s', 'wpcredits-program-manager' ), (string) $row['author'] );
		}

		// A link that decides nothing joins the facts (design 2.6).
		$facts[] = array(
			'html' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) $row['preview'] ), esc_html__( 'Preview the post', 'wpcredits-program-manager' ) ),
		);

		$header = WPCPM_UI::item_header(
			array(
				'name'  => (string) $row['title'],
				'href'  => (string) $row['preview'],
				'facts' => $facts,
			)
		);

		echo WPCPM_UI::item_open( 'wpcpm-sponsor-post', 'wpcpm-sponsor-post-' . (int) $row['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
		echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header(); the one html fact above escapes its href and words.

		if ( class_exists( 'WPCPM_Sponsor_Posts' ) ) {
			WPCPM_Sponsor_Posts::render_decision( (int) $row['id'], WPCPM_Return::DASHBOARD );
		}

		echo '</article>';
	}

	/**
	 * Sponsor offers waiting for review, and changes to live ones (1.123.18): each drawn by the review
	 * class, with its company, whether it is new or changes, who sent it, what students would read,
	 * Approve and the folded Return with a note, and last the way to the offers' own card on the
	 * Sponsors screen's queue tab. As the posts' card: the oldest `LIMIT` listed, the count every one
	 * waiting, as the Sponsors screen's card counts them, and while more wait than the list holds, the
	 * line under it says how many more.
	 *
	 * @param array[]  $rows  What `WPCPM_Sponsor_Offer_Review::waiting_all()` returned.
	 * @param int|null $total How many wait, as `collect()` counted them; null counts the rows.
	 */
	public static function render_sponsor_offers( array $rows, $total = null ) {
		$total  = null === $total ? count( $rows ) : (int) $total;
		$screen = self::sponsors_screen( 'queue', 'wpcpm-sponsor-offers-review', __( 'Sponsor offers to review', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'sponsor-offers-review',
					'title'  => __( 'Sponsor offers to review', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'sponsor-offer', __( 'Sponsor offers to review', 'wpcredits-program-manager' ) ),
					'count'  => $total,
					'shown'  => WPCPM_Sponsors::queue_count( $total ),
					'open'   => $total > 0,
					'state'  => self::waiting( WPCPM_Sponsors::queue_number( $total ), WPCPM_Sponsors::queue_count( $total ) ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No sponsor offer is waiting for review.', 'wpcredits-program-manager' ) );
		}

		if ( class_exists( 'WPCPM_Sponsor_Offer_Review' ) ) {
			foreach ( $rows as $row ) {
				WPCPM_Sponsor_Offer_Review::render_item( $row, WPCPM_Return::DASHBOARD );
			}
		}

		self::render_sponsor_more_line( $total, count( $rows ) );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Events card (1.117.0): the events waiting for the program's OK, each with Approve and Return
	 * with a note, and the approved events coming up, each with who joined and Cancel with a note, or,
	 * for an event of the program's own, Change and Cancel; then the program's own plan, drawn by the
	 * queue class inside this card's frame; and last the way to the events' own card on the Sponsors
	 * screen's queue tab, which lists them read-only. The count is the tile's, what waits, which an
	 * event of the program's own never does. Nothing at all while Sponsor tiers is off: the tiers are
	 * not announced, and the events are one of their benefits.
	 *
	 * @param array $data `waiting` and `upcoming`, as `WPCPM_Sponsor_Event_Queue::data()` answers them.
	 */
	public static function render_sponsor_events( array $data ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Event_Queue' ) || ! WPCPM_Sponsor_Event_Queue::shown() ) {
			return;
		}

		$count  = WPCPM_Sponsor_Event_Queue::count_for_tile( $data );
		$screen = self::sponsors_screen( 'queue', 'wpcpm-sponsor-events', __( 'Events', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'sponsor-events',
					'title'  => __( 'Events', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'sponsor-event', __( 'Events', 'wpcredits-program-manager' ) ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => $count > 0 ? self::waiting( $count ) : self::events_coming_up( isset( $data['upcoming'] ) ? count( (array) $data['upcoming'] ) : 0 ),
					'action' => $screen,
				)
			)
		);
		WPCPM_Sponsor_Event_Queue::render( $data );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Sponsor applications: one compact item per open application, with every decision its state allows: Approve,
	 * Ask a question, Reject and Reject as spam on each, and Put back in the queue on one held or waiting on the
	 * applicant. Delete for good, the sixth decision, is never on this card: it lists open applications only.
	 *
	 * The company, the reference, when it arrived, the website, the contact, the state, the tier
	 * the company is considering when it answered that question, and the marks; then the
	 * application in full, folded under "Read the application" (S6): its answers with the consent
	 * evidence, its logo files and what the site already has, drawn by
	 * `WPCPM_Sponsor_Application::render_details()`. The title links to the application on the
	 * wp-admin Sponsors screen. The decisions are the application class's own, bound for this
	 * page (spec 9.2). Last, the way to the applications' own card on the Sponsors screen's queue
	 * tab.
	 *
	 * The applications listed are the oldest `LIMIT`. The card's count is every open application, as
	 * the Sponsors screen's applications card counts them, printed as it stands at any size, and while
	 * more wait than the list holds, the line under it says exactly how many more, as the Institution
	 * applications card's line does (`render_more_line()`).
	 *
	 * @param array    $rows  What `WPCPM_Sponsor_Application::queue_facts()` returned.
	 * @param int|null $total How many applications wait, as `collect()` counted them; null counts the rows.
	 */
	public static function render_sponsor_applications( array $rows, $total = null ) {
		$total  = null === $total ? count( $rows ) : (int) $total;
		$screen = self::sponsors_screen( 'queue', 'wpcpm-sponsor-applications', __( 'Sponsor applications', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'sponsor-applications',
					'title'  => __( 'Sponsor applications', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'sponsor-application', __( 'Sponsor applications', 'wpcredits-program-manager' ) ),
					'count'  => $total,
					'open'   => $total > 0,
					'state'  => self::waiting( $total ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No sponsor application is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $rows as $row ) {
			self::render_sponsor_application_parts( $row );
		}

		if ( $total > count( $rows ) ) {
			self::render_more_line( $total - count( $rows ) );
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * One sponsor application on the shared parts (design 2.10, 2.3, 2.6, 4.1): the item header (the company linked to the application on the Sponsors screen, as today; its
	 * reference; the state as a neutral tag in today's words; then when it was applied, the website, the contact and
	 * the tier answer as muted facts), the notes as today, "Read the application" as an inner fold around the
	 * application class's details, and the class's one Decide area. No item is overdue: the card has no rule for it
	 * today, so no tag turns red. The item keeps its article and its anchor, which the opened application on the
	 * Sponsors screen and a decision's way back link to.
	 *
	 * @param array $row A `WPCPM_Sponsor_Application::queue_facts()` row.
	 */
	private static function render_sponsor_application_parts( array $row ) {
		/* translators: %s: how long ago. */
		$facts = array( sprintf( __( 'Applied %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['at'], time() ) ) );

		if ( '' !== (string) $row['website'] ) {
			$facts[] = (string) $row['website'];
		}

		/* translators: 1: contact person, 2: contact email address. */
		$facts[] = sprintf( __( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ), (string) $row['person'], (string) $row['email'] );

		// The tier answer as today: branched on the key, never on the label a translation may reword.
		$requested = isset( $row['tier_requested'] ) ? (string) $row['tier_requested'] : '';

		if ( '' !== $requested ) {
			if ( WPCPM_Sponsor_Tiers::UNSURE === $requested ) {
				$facts[] = __( 'Tier: not sure yet', 'wpcredits-program-manager' );
			} elseif ( ! empty( $row['tier_requested_label'] ) ) {
				/* translators: %s: the tier the applicant is considering. */
				$facts[] = sprintf( __( 'Considering: %s', 'wpcredits-program-manager' ), (string) $row['tier_requested_label'] );
			}
		}

		$header = WPCPM_UI::item_header(
			array(
				'name'  => (string) $row['company'],
				'href'  => admin_url( 'admin.php?page=wpcpm-sponsors&' . WPCPM_Sponsor_Application::QUERY_QUEUE . '=' . (int) $row['id'] ),
				'ref'   => (string) $row['reference'],
				'tag'   => array(
					'words' => (string) $row['state_label'],
					'tone'  => '',
				),
				'facts' => $facts,
			)
		);

		echo WPCPM_UI::item_open( 'wpcpm-sponsor-application', 'wpcpm-sponsor-application-' . (int) $row['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
		echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header().

		$notes = array(
			'held'      => __( 'Held by the anti-spam checks. Open it on the Sponsors screen to read which.', 'wpcredits-program-manager' ),
			'duplicate' => __( 'Another open application names this company or this address. Nothing is ever merged.', 'wpcredits-program-manager' ),
			'in_base'   => __( 'The site already holds a sponsor with this name or website. Approving creates a second record.', 'wpcredits-program-manager' ),
			'half_done' => __( 'The approval of this application is half done: a sponsor record already exists for it. Press Approve again to finish. Reject, Reject as spam and Put back in the queue are refused until it is.', 'wpcredits-program-manager' ),
		);

		foreach ( $notes as $flag => $note ) {
			if ( ! empty( $row[ $flag ] ) ) {
				echo WPCPM_UI::note( $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::note().
			}
		}

		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			$post = get_post( (int) $row['id'] );

			if ( $post instanceof WP_Post ) {
				echo WPCPM_UI::inner_open( array( 'title' => __( 'Read the application', 'wpcredits-program-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open().
				WPCPM_Sponsor_Application::render_details( $post );
				echo WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
			}

			WPCPM_Sponsor_Application::render_decision( (int) $row['id'], WPCPM_Return::DASHBOARD );
		}

		echo '</article>';
	}

	/**
	 * Offers running low: every live pool under its sponsor's own threshold, emptiest first, then
	 * the way to the Sponsors screen's Offers and codes tab.
	 *
	 * @param array $rows offers_low()'s rows.
	 */
	public static function render_offers_low( array $rows ) {
		$count  = count( $rows );
		$screen = self::sponsors_screen( 'offers', '', __( 'Offers running low', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'offers-low',
					'title'  => __( 'Offers running low', 'wpcredits-program-manager' ),
					'count'  => $count,
					'open'   => false,
					'state'  => self::offers_low_phrase( $count ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No offer is running low.', 'wpcredits-program-manager' ) );
			self::render_screen_line( $screen );
			WPCPM_Dashboard_Card::close();
			return;
		}

		self::render_offers_low_parts( $rows );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Offers running low's table on the shared parts (design 2.7, 4.1): the table part, named by the card's title as today's scroll box was (the table part replaces the box). The
	 * offer is each row's header, linked to its sponsor's Offers and codes card as today, or its title alone where
	 * there is no Sponsor Dashboard page; the codes left and the number it warns at are numbers. The link is the
	 * row's name, as today, so the row has no action column. Nothing on the row is a state in colour today, so it
	 * prints no tag.
	 *
	 * @param array[] $rows offers_low()'s rows, at least one.
	 */
	private static function render_offers_low_parts( array $rows ) {
		$offer   = array(
			'key'    => 'offer',
			'label'  => __( 'Offer', 'wpcredits-program-manager' ),
			'kind'   => 'text',
			'header' => 'row',
		);
		$sponsor = array(
			'key'   => 'sponsor',
			'label' => __( 'Sponsor', 'wpcredits-program-manager' ),
			'kind'  => 'text',
		);
		$left    = array(
			'key'   => 'left',
			'label' => __( 'Codes left', 'wpcredits-program-manager' ),
			'kind'  => 'num',
		);
		$warns   = array(
			'key'   => 'warns',
			'label' => __( 'Warns at', 'wpcredits-program-manager' ),
			'kind'  => 'num',
		);

		$table = WPCPM_UI::table_open( array( $offer, $sponsor, $left, $warns ), array( 'labelledby' => self::title_id( 'offers-low' ) ) );

		foreach ( $rows as $row ) {
			$url = (string) $row['url'];

			$table .= WPCPM_UI::row_open()
				// No Sponsor Dashboard page, no link: the title alone, not an anchor to nowhere.
				. WPCPM_UI::cell( $offer, '' !== $url ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( (string) $row['title'] ) ) : esc_html( (string) $row['title'] ) )
				. WPCPM_UI::cell( $sponsor, esc_html( (string) $row['sponsor_name'] ) )
				. WPCPM_UI::cell( $left, esc_html( number_format_i18n( (int) $row['available'] ) ) )
				. WPCPM_UI::cell( $warns, esc_html( number_format_i18n( (int) $row['low'] ) ) )
				. WPCPM_UI::row_close();
		}

		echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() or esc_url() above.
	}

	/**
	 * Duplicated students: how many the Student Duplicate Finder lists, how many of them are
	 * Ready, as of when, and the way to the finder, where the names are (1.102.0).
	 *
	 * @param array $facts duplicates()'s answer.
	 */
	public static function render_duplicates( array $facts ) {
		$facts = array_merge(
			array(
				'addresses' => 0,
				'ready'     => 0,
				'read'      => 0,
				'url'       => '',
			),
			$facts
		);

		$count  = (int) $facts['addresses'];
		$action = null;

		// The finder is where the names are and where the rows are deleted; with no address for it
		// the row offers no link, as the card's body offers none.
		if ( '' !== (string) $facts['url'] ) {
			$action = array(
				'label' => __( 'Open the Student Duplicate Finder', 'wpcredits-program-manager' ),
				'href'  => (string) $facts['url'],
			);
		}

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'duplicates',
					'title'  => __( 'Duplicated students', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'duplicates', __( 'Duplicated students', 'wpcredits-program-manager' ) ),
					'count'  => $count,
					'open'   => false,
					'state'  => self::duplicates_phrase( $count, (int) $facts['read'] ),
					'action' => $action,
				)
			)
		);

		self::render_duplicates_parts( $facts, $action );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Duplicated students' body on the shared parts (design 2.5, 2.3, 4.1): before the first scan and after a clean one, today's sentence as the parts' muted line; otherwise today's
	 * sentence as the parts' intro line. The way to the finder ends the body as every Watch card's way to its home
	 * does, the fold's foot (`render_screen_line()`), in today's words and to today's address, in place of today's
	 * bordered button; nothing is printed after the fold (task V1, ruling 2). With no address for the finder there is
	 * no foot.
	 *
	 * @param array      $facts  duplicates()'s answer, merged with its defaults.
	 * @param array|null $action The card's row action, null with no address for the finder.
	 */
	private static function render_duplicates_parts( array $facts, $action ) {
		if ( empty( $facts['read'] ) ) {
			self::empty_line( __( 'The Student Duplicate Finder has not scanned Airtable yet.', 'wpcredits-program-manager' ) );
		} elseif ( 0 === (int) $facts['addresses'] ) {
			/* translators: %s: date and time of the scan. */
			self::empty_line( sprintf( __( 'No duplicated students in Airtable, as of %s.', 'wpcredits-program-manager' ), self::when( (int) $facts['read'] ) ) );
		} else {
			$words = sprintf(
				/* translators: 1: duplicated students, 2: how many of them are ready to delete, 3: date and time of the scan. */
				_n( '%1$s duplicated student in Airtable, %2$s ready to delete, as of %3$s.', '%1$s duplicated students in Airtable, %2$s ready to delete, as of %3$s.', (int) $facts['addresses'], 'wpcredits-program-manager' ),
				number_format_i18n( (int) $facts['addresses'] ),
				number_format_i18n( (int) $facts['ready'] ),
				self::when( (int) $facts['read'] )
			);

			echo WPCPM_UI::intro( $words ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
		}

		if ( is_array( $action ) ) {
			self::render_screen_line( $action );
		}
	}

	/**
	 * New interests: what sponsors said on their dashboards in the last thirty days, then the way to
	 * the Sponsors screen's Interests tab.
	 *
	 * @param array $rows interests()'s rows.
	 */
	public static function render_interests( array $rows ) {
		$count  = count( $rows );
		$screen = self::sponsors_screen( 'interests', '', __( 'New interests', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'interests',
					'title'  => __( 'New interests', 'wpcredits-program-manager' ),
					'count'  => $count,
					'open'   => false,
					'state'  => self::interests_phrase( $count ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No sponsor said anything new in the last thirty days.', 'wpcredits-program-manager' ) );
		}

		self::render_interests_parts( $rows );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * New interests on the shared parts (design 2.8, 4.1): one list
	 * row per line a sponsor wrote, the sponsor's name linked to its Sponsorship card as today (its name alone where
	 * there is no Sponsor Dashboard page), how long ago as the row's fact, and the sponsor's own words as the row's
	 * note, escaped, never linked, never trimmed. The card lists and links, and decides nothing, so a row has no
	 * action. The empty sentence before it and the way to the Sponsors screen after it are the caller's, as today.
	 *
	 * @param array[] $rows interests()'s rows.
	 */
	private static function render_interests_parts( array $rows ) {
		if ( empty( $rows ) ) {
			return;
		}

		// No row carries a tag, so each row's facts sit under its name (Task 18c).
		$list = WPCPM_UI::list_open( array( 'facts' => 'under' ) );

		foreach ( $rows as $row ) {
			$list .= WPCPM_UI::row(
				array(
					'name'  => (string) $row['sponsor_name'],
					// No Sponsor Dashboard page, no link: the sponsor's name alone, not an anchor to nowhere.
					'href'  => (string) $row['url'],
					/* translators: %s: how long ago. */
					'facts' => array( sprintf( __( '%s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['time'], time() ) ) ),
					// A sponsor's own words, on an Administrator's page: escaped by the row, its line breaks kept.
					'note'  => (string) $row['message'],
				)
			);
		}

		echo $list . WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row().
	}

	/**
	 * The Sponsors card: four figures on the tiles the programs card draws its tracks with, and,
	 * once the tier ladder has loaded, the Approved sponsors by their effective tier underneath;
	 * then the way to the Sponsors screen's Sponsors tab. On the shared parts the line over the
	 * tier tiles is the parts' intro line and the foot the fold's; the tiles stay the card's own,
	 * since no part draws a figure with its label.
	 *
	 * @param array $facts sponsors()'s answer.
	 */
	public static function render_sponsors_strip( array $facts ) {
		$facts = array_merge(
			array(
				'approved'        => 0,
				'with_accounts'   => 0,
				'live_offers'     => 0,
				'claims_semester' => 0,
				'since'           => '',
				'since_display'   => '',
			),
			$facts
		);

		// The day as the site writes it, falling back to the stored `Y-m-d` for a caller that
		// has not been told about the second field.
		$since = '' !== (string) $facts['since_display'] ? (string) $facts['since_display'] : (string) $facts['since'];

		$count  = (int) $facts['approved'];
		$screen = self::sponsors_screen( 'sponsors', '', __( 'Sponsors', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'sponsors',
					'title'  => __( 'Sponsors', 'wpcredits-program-manager' ),
					'count'  => $count,
					'open'   => false,
					'state'  => self::sponsors_phrase( $count, (int) $facts['live_offers'] ),
					'action' => $screen,
				)
			)
		);

		// A tile is a name, a number and a qualifier, as the programs card's tiles are: the name
		// says what is counted, the line under the number says where or since when (the same
		// words twice read as a stutter).
		$tiles = array(
			array( __( 'Approved sponsors', 'wpcredits-program-manager' ), (int) $facts['approved'], __( 'in the program records', 'wpcredits-program-manager' ) ),
			array( __( 'With an account', 'wpcredits-program-manager' ), (int) $facts['with_accounts'], __( 'of the Approved sponsors', 'wpcredits-program-manager' ) ),
			array( __( 'Live offers', 'wpcredits-program-manager' ), (int) $facts['live_offers'], __( 'shown on the site today', 'wpcredits-program-manager' ) ),
			/* translators: %s: the first day of the semester, in the site's date format. */
			array( __( 'Claims this semester', 'wpcredits-program-manager' ), (int) $facts['claims_semester'], sprintf( __( 'since %s, on every offer', 'wpcredits-program-manager' ), $since ) ),
		);

		echo '<ul class="wpcpm-programs__tiles">';

		foreach ( $tiles as $tile ) {
			printf(
				'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
				esc_html( $tile[0] ),
				esc_html( number_format_i18n( $tile[1] ) ),
				esc_html( $tile[2] )
			);
		}

		echo '</ul>';

		// The tier ladder's own tiles, under the four, drawn only once the ladder has loaded: a
		// zero is drawn like every other tile, so a ladder nobody has climbed yet still reads as
		// five rungs rather than a blank space where a number should be (S8).
		if ( isset( $facts['tiers'] ) ) {
			// The line is the parts' intro line; the tiles stay the card's own, which no part draws.
			echo WPCPM_UI::intro( __( 'Approved sponsors by tier, as the tiers stand today.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
			echo '<ul class="wpcpm-programs__tiles">';

			foreach ( WPCPM_Sponsor_Tiers::KEYS as $key ) {
				printf(
					'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
					esc_html( WPCPM_Sponsor_Tiers::label( $key ) ),
					esc_html( number_format_i18n( (int) $facts['tiers'][ $key ] ) ),
					esc_html__( 'of the Approved sponsors', 'wpcredits-program-manager' )
				);
			}

			echo '</ul>';
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Sponsor terms ending: Approved sponsors whose term ends within the next sixty days, soonest
	 * first, each linked to its Edit form on the Sponsors screen's Sponsors tab with its manager to
	 * tell (S8), then the way to that tab, where every sponsor's term is set.
	 *
	 * @param array $rows tiers_ending()'s rows.
	 */
	public static function render_tiers_ending( array $rows ) {
		$count  = count( $rows );
		$screen = self::sponsors_screen( 'sponsors', '', __( 'Sponsor terms ending', 'wpcredits-program-manager' ) );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'tiers-ending',
					'title'  => __( 'Sponsor terms ending', 'wpcredits-program-manager' ),
					'count'  => $count,
					'open'   => false,
					'state'  => self::tiers_ending_phrase( $count ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No sponsor\'s term ends in the next sixty days.', 'wpcredits-program-manager' ) );
			self::render_screen_line( $screen );
			WPCPM_Dashboard_Card::close();
			return;
		}

		self::render_tiers_ending_parts( $rows );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Sponsor terms ending's table on the shared parts (design 2.7, 2.2, 4.1): the table part, named by the card's title as today's scroll box was (the table part replaces the
	 * box). The sponsor is each row's header, linked to its Edit form as today; the link is the row's name, so the row
	 * has no action column. The day the term ends is a date, followed by the days left in today's words ("today",
	 * "tomorrow", "in N days") as plain words in the cell's own ink, a space between them as today: every row has
	 * them, so they are no state and take no tag. A sponsor with no Administrator keeps today's words, "Nobody yet",
	 * muted as an empty value.
	 *
	 * @param array[] $rows tiers_ending()'s rows, at least one.
	 */
	private static function render_tiers_ending_parts( array $rows ) {
		$sponsor = array(
			'key'    => 'sponsor',
			'label'  => __( 'Sponsor', 'wpcredits-program-manager' ),
			'kind'   => 'text',
			'header' => 'row',
		);
		$tier    = array(
			'key'   => 'tier',
			'label' => __( 'Tier', 'wpcredits-program-manager' ),
			'kind'  => 'text',
		);
		$ends    = array(
			'key'   => 'ends',
			'label' => __( 'Ends on', 'wpcredits-program-manager' ),
			'kind'  => 'date',
		);
		$manager = array(
			'key'   => 'manager',
			'label' => __( 'Administrator', 'wpcredits-program-manager' ),
			'kind'  => 'text',
		);

		$table = WPCPM_UI::table_open( array( $sponsor, $tier, $ends, $manager ), array( 'labelledby' => self::title_id( 'tiers-ending' ) ) );

		foreach ( $rows as $row ) {
			$named = '' !== (string) $row['manager'];

			$table .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $sponsor, sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) $row['url'] ), esc_html( (string) $row['name'] ) ) )
				. WPCPM_UI::cell( $tier, esc_html( (string) $row['tier'] ) )
				// The days left after the day, a space between them as today, in the cell's own ink: every row has them,
				// so they are no state to tag.
				. WPCPM_UI::cell( $ends, esc_html( self::whole_text( WPCPM_Sponsor_Tiers::format_day( (string) $row['until'] ) ) ) . ' ' . esc_html( self::term_ends_in( (int) $row['days'] ) ) )
				// An empty value keeps its words, muted (design 2.7).
				. WPCPM_UI::cell( $manager, esc_html( $named ? (string) $row['manager'] : __( 'Nobody yet', 'wpcredits-program-manager' ) ), $named ? '' : 'empty' )
				. WPCPM_UI::row_close();
		}

		echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() or esc_url() above.
	}

	/**
	 * Sponsor reports (1.115.0): the drafts a manager has to finish, the terms that ended with no
	 * report yet, and the reports marked final this semester, in one card of three parts, then the
	 * way to the Sponsors screen's Reports tab.
	 *
	 * Nothing is decided here. A report opens in its editor on the Sponsors screen, where it is
	 * written, attached to and marked final, and a report marked final opens there too, as a record
	 * of what is done. A term with no report has Draft now, drawn by the Sponsors screen's own
	 * renderer of its draft form (`WPCPM_Sponsor_Report_Screen::render_draft_form()`) for that
	 * sponsor and that day and bound for this card: a refusal comes back here and says why, and a
	 * draft that is made opens in the editor. The count is the strip tile's, the two parts that ask
	 * for a press. Each part keeps its heading, with one short sentence under it when it is empty,
	 * as the Semester reports card does, so the card has one shape whatever the day holds and a
	 * manager learns where to look; a card with nothing in any part says so once, since three
	 * headings each over nothing would say it three times.
	 *
	 * @param array $data sponsor_reports()'s answer: `queue`, `due`, `final`.
	 */
	public static function render_sponsor_reports( array $data ) {
		$queue = isset( $data['queue'] ) ? (array) $data['queue'] : array();
		$due   = isset( $data['due'] ) ? (array) $data['due'] : array();
		$final = isset( $data['final'] ) ? (array) $data['final'] : array();

		$count  = self::sponsor_reports_open( $data );
		$screen = self::sponsors_screen( 'reports', '', __( 'Sponsor reports', 'wpcredits-program-manager' ) );
		// Each table is named by the card's heading and the subheading above the table, as the
		// Semester reports card's two are (1.122.35).
		$drafts_title = 'wpcpm-sponsor-reports-drafts-title';
		$ended_title  = 'wpcpm-sponsor-reports-ended-title';
		$final_title  = 'wpcpm-sponsor-reports-final-title';

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'sponsor-reports',
					'title'  => __( 'Sponsor reports', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'sponsor-report', __( 'Sponsor reports', 'wpcredits-program-manager' ) ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $queue ) && empty( $due ) && empty( $final ) ) {
			self::empty_line( __( 'Nothing to finish.', 'wpcredits-program-manager' ) );
			self::render_screen_line( $screen );
			WPCPM_Dashboard_Card::close();
			return;
		}

		self::render_sponsor_reports_parts( $queue, $due, $final, array( $drafts_title, $ended_title, $final_title ) );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Sponsor reports card's three parts on the shared parts (design 2.7, 2.11, 4.1): each part's heading through the heading part with its id kept, then its table through
	 * the table part, named as today by the card's title and that heading (the table part replaces today's scroll
	 * box), or today's empty sentence. The sponsor is each row's header, as today; the day a term ended is a date;
	 * a report with no stamp keeps its words, "Not recorded", muted as an empty value. The action column holds the
	 * quiet row action: Open, the link to the report's editor as today, and Draft now, the Sponsors screen's own form
	 * (`WPCPM_Sponsor_Report_Screen::render_draft_form()`), which draws its button on the parts for this page.
	 *
	 * @param array[]  $queue    The drafts to finish.
	 * @param array[]  $due      The terms that ended with no report.
	 * @param array[]  $finished The reports marked final this semester.
	 * @param string[] $titles   The three headings' ids: drafts, ended, final.
	 */
	private static function render_sponsor_reports_parts( array $queue, array $due, array $finished, array $titles ) {
		$card    = self::title_id( 'sponsor-reports' );
		$sponsor = array(
			'key'    => 'sponsor',
			'label'  => __( 'Sponsor', 'wpcredits-program-manager' ),
			'kind'   => 'text',
			'header' => 'row',
		);
		$report  = array(
			'key'   => 'report',
			'label' => __( 'Report', 'wpcredits-program-manager' ),
			'kind'  => 'text',
		);
		$action  = array(
			'key'   => 'action',
			'label' => '',
			'kind'  => 'action',
		);
		$ended   = array(
			'key'   => 'ended',
			'label' => __( 'Term ended on', 'wpcredits-program-manager' ),
			'kind'  => 'date',
		);
		$marked  = array(
			'key'   => 'marked',
			'label' => __( 'Marked final', 'wpcredits-program-manager' ),
			'kind'  => 'text',
		);

		// The three tables are one family (design 2.7; task V1, ruling 14): the sponsor, the second column and the action
		// start at the same places in all three.
		// The action column's 12% must hold "Draft now" (about 78px with its cell): the family needs about 650px, which a
		// main area of 800px holds; under 800px every table stacks (Task 18f, fix round 2).
		$family                   = array( 'fixed' => true );
		$sponsor['width']         = '30%';
		$action['width']          = '12%';
		$report_in_three          = $report;
		$report_in_three['width'] = '30%';

		echo WPCPM_UI::heading( __( 'Drafts to finish', 'wpcredits-program-manager' ), 'h4', $titles[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		if ( empty( $queue ) ) {
			self::empty_line( __( 'No draft to finish.', 'wpcredits-program-manager' ) );
		} else {
			$table = WPCPM_UI::table_open( array( $sponsor, $report, $action ), $family + array( 'labelledby' => $card . ' ' . $titles[0] ) );

			foreach ( $queue as $row ) {
				$table .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $sponsor, esc_html( (string) $row['name'] ) )
					. WPCPM_UI::cell( $report, esc_html( self::months_whole( (string) $row['title'] ) ) )
					. WPCPM_UI::cell( $action, self::sponsor_report_open_parts( $row ) )
					. WPCPM_UI::row_close();
			}

			echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() above, the action through WPCPM_UI::button().
		}

		echo WPCPM_UI::heading( __( 'Terms ended with no report', 'wpcredits-program-manager' ), 'h4', $titles[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		if ( empty( $due ) ) {
			self::empty_line( __( 'No term is waiting for its report.', 'wpcredits-program-manager' ) );
		} else {
			$table = WPCPM_UI::table_open( array( $sponsor, $ended, $action ), $family + array( 'labelledby' => $card . ' ' . $titles[1] ) );

			foreach ( $due as $row ) {
				ob_start();
				WPCPM_Sponsor_Report_Screen::render_draft_form( (string) $row['sponsor'], (string) $row['until'], WPCPM_Return::DASHBOARD );
				$form = (string) ob_get_clean();

				$table .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $sponsor, esc_html( (string) $row['name'] ) )
					. WPCPM_UI::cell( $ended, esc_html( self::whole_text( WPCPM_Sponsor_Tiers::format_day( (string) $row['until'] ) ) ) )
					. WPCPM_UI::cell( $action, $form )
					. WPCPM_UI::row_close();
			}

			echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() above, the form by its renderer.
		}

		echo WPCPM_UI::heading( __( 'Final this semester', 'wpcredits-program-manager' ), 'h4', $titles[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		if ( empty( $finished ) ) {
			self::empty_line( __( 'None marked final this semester yet.', 'wpcredits-program-manager' ) );

			return;
		}

		$table = WPCPM_UI::table_open( array( $sponsor, $report_in_three, $marked, $action ), $family + array( 'labelledby' => $card . ' ' . $titles[2] ) );

		foreach ( $finished as $row ) {
			$table .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $sponsor, esc_html( (string) $row['name'] ) )
				. WPCPM_UI::cell( $report, esc_html( self::months_whole( (string) $row['title'] ) ) )
				// An empty value keeps its words, muted (design 2.7).
				. WPCPM_UI::cell( $marked, esc_html( self::marked_final( (int) $row['final_at'], (int) $row['final_by'] ) ), (int) $row['final_at'] > 0 ? '' : 'empty' )
				. WPCPM_UI::cell( $action, self::sponsor_report_open_parts( $row ) )
				. WPCPM_UI::row_close();
		}

		echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() above, the action through WPCPM_UI::button().
	}

	/**
	 * Open on the shared parts: the quiet row action, a link to the report's editor on the Sponsors screen as today,
	 * carrying the report's title for a screen reader.
	 *
	 * @param array $row A `queue` or `final` row of sponsor_reports().
	 * @return string Escaped markup.
	 */
	private static function sponsor_report_open_parts( array $row ) {
		return WPCPM_UI::button(
			array(
				'label'   => __( 'Open', 'wpcredits-program-manager' ),
				'kind'    => 'quiet',
				'href'    => (string) $row['url'],
				'context' => (string) $row['title'],
				// Drawn on the Administrator Dashboard alone, which is never wp-admin.
				'side'    => 'front',
			)
		);
	}

	/**
	 * Every live pool below the number its sponsor asked to be warned at, the emptiest first.
	 *
	 * A pool is an offer of kind codes in state live whose last day has not passed; a shared
	 * offer has no pool and is never low. The threshold is the offer's own (`low`), which is
	 * the setting's default until the sponsor changes it. The sponsor's name comes from the
	 * index, and the link lands on its Offers and codes card through the switcher, because
	 * that is where a manager acts on it.
	 *
	 * Capped at LIMIT, and the tile counts the capped list: below fifty pools the two are the
	 * same number, and a strip of fifty low pools is not a number anybody acts on.
	 *
	 * @return array[] `id`, `title`, `sponsor`, `sponsor_name`, `available`, `low`, `url`.
	 */
	public static function offers_low() {
		if ( ! class_exists( 'WPCPM_Sponsor_Offers' ) || ! class_exists( 'WPCPM_Sponsor_Codes' ) ) {
			return array();
		}

		$rows = array();

		foreach ( WPCPM_Sponsor_Offers::live() as $offer ) {
			if ( WPCPM_Sponsor_Offers::KIND_CODES !== $offer['kind'] || ! WPCPM_Sponsor_Offers::is_live( $offer ) ) {
				continue;
			}

			$counts = WPCPM_Sponsor_Codes::counts( (int) $offer['id'] );

			if ( (int) $counts['available'] >= (int) $offer['low'] ) {
				continue;
			}

			$rows[] = array(
				'id'           => (int) $offer['id'],
				'title'        => (string) $offer['title'],
				'sponsor'      => (string) $offer['sponsor'],
				'sponsor_name' => self::sponsor_name( (string) $offer['sponsor'] ),
				'available'    => (int) $counts['available'],
				'low'          => (int) $offer['low'],
				'url'          => self::sponsor_url( (string) $offer['sponsor'], 'offers' ),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['available'] === $b['available'] ? strcmp( $a['title'], $b['title'] ) : $a['available'] <=> $b['available'];
			}
		);

		return array_slice( $rows, 0, self::LIMIT );
	}

	/**
	 * What sponsors said on their dashboards in the last thirty days: the audit rows of kind
	 * sponsor_interest, newest first, each with its sponsor's name and the line as written.
	 * The line is a sponsor's own words and is escaped where it is printed.
	 *
	 * @return array[] `id`, `sponsor`, `sponsor_name`, `message`, `time`, `url`.
	 */
	public static function interests() {
		if ( ! class_exists( 'WPCPM_Institution_Audit' ) || ! class_exists( 'WPCPM_Sponsor_Interests' ) ) {
			return array();
		}

		$since = time() - ( 30 * DAY_IN_SECONDS );
		$rows  = array();

		foreach ( WPCPM_Institution_Audit::sponsor_entries( WPCPM_Sponsor_Interests::LOG_KIND, self::LIMIT ) as $entry ) {
			if ( ! is_array( $entry ) || (int) $entry['time'] < $since ) {
				continue;
			}

			$rows[] = array(
				'id'           => (int) $entry['id'],
				'sponsor'      => (string) $entry['sponsor'],
				'sponsor_name' => self::sponsor_name( (string) $entry['sponsor'] ),
				'message'      => (string) $entry['message'],
				'time'         => (int) $entry['time'],
				// The sponsor's page at its Sponsorship card, whose Talk to your program contact files every
				// new line of this kind since Offer more left the Sponsor Dashboard (1.123.22).
				'url'          => self::sponsor_url( (string) $entry['sponsor'], 'sponsorship' ),
			);
		}

		return $rows;
	}

	/**
	 * The first moment of this semester, where the site is: one definition for the whole page.
	 *
	 * Midnight where the site is, rather than at Greenwich. The cohort's first day is a
	 * calendar day, and a calendar day belongs to the site's own zone: measured from midnight
	 * UTC, a claim made at half past eleven on the last evening of last semester was counted
	 * into this one on every site west of Greenwich, and the tile printed a figure nobody
	 * could reproduce by counting (the clean-up release parked this as P2).
	 *
	 * Answered here rather than twice, because the Sponsors tile and the Reports card both ask
	 * it and print the answer as the same words: one of them counting from Greenwich and the
	 * other from Los Angeles is a page saying "this semester" about two different days.
	 *
	 * A reader handed a day of its own asks for that day's semester, so it reads one today for
	 * every list it draws.
	 *
	 * @param string $today `Y-m-d`, the day whose semester is meant; '' for today, and so is a
	 *                      value that is not a day.
	 * @return array{from: int, since: string} The moment, and its day as `Y-m-d`; 0 and an
	 *               empty string when the cohort has no range.
	 */
	private static function semester_start( $today = '' ) {
		$key = WPCPM_Cohort::key( $today );

		// A value that is not a day is today, rather than no semester at all: a window from 0 would
		// count every report ever marked final as this semester's.
		if ( WPCPM_Cohort::NONE === $key ) {
			$key = WPCPM_Cohort::current();
		}

		$since = (string) WPCPM_Cohort::range( $key )['from'];

		return array(
			'from'  => '' !== $since ? (int) ( new DateTimeImmutable( $since . ' 00:00:00', wp_timezone() ) )->format( 'U' ) : 0,
			'since' => $since,
		);
	}

	/**
	 * The sponsors' figures for the Sponsors card: Approved sponsors, how many of them hold an
	 * account, the offers live today, and the claims that stand since this semester began.
	 * A voided claim is not a claim; a claim before the cohort's first day is
	 * last semester's. Approved sponsors by their effective tier join once the tier ladder has
	 * loaded (S8): every one of WPCPM_Sponsor_Tiers::KEYS is a key, zero included, so the
	 * ladder's tiles always have the same five rungs to draw.
	 *
	 * @return array `approved`, `with_accounts`, `live_offers`, `claims_semester`, `since`
	 *               (Y-m-d), `since_display` (the site's date format) and, once the tier ladder
	 *               class has loaded, `tiers` (tier key => count).
	 */
	public static function sponsors() {
		$start = self::semester_start();
		$since = $start['since'];
		$from  = $start['from'];
		$out   = array(
			'approved'        => 0,
			'with_accounts'   => 0,
			'live_offers'     => 0,
			'claims_semester' => 0,
			'since'           => $since,
			// Twice, on purpose: `since` stays `Y-m-d`, which is what every reader of this
			// array compares against, and `since_display` is the same day written the way this
			// site writes days, which is what the tile's sentence says out loud.
			'since_display'   => $from > 0 ? (string) wp_date( (string) get_option( 'date_format', 'F j, Y' ), $from ) : $since,
		);

		// The ladder's own tiles read this key, when it is there: absent rather than five zeros
		// while the tier ladder class has not loaded, so render_sponsors_strip() can tell "the
		// ladder counted nobody" from "the ladder was never asked" (S8).
		if ( class_exists( 'WPCPM_Sponsor_Tiers' ) ) {
			$out['tiers'] = array_fill_keys( WPCPM_Sponsor_Tiers::KEYS, 0 );
		}

		if ( class_exists( 'WPCPM_Sponsors_Index' ) ) {
			foreach ( WPCPM_Sponsors_Index::rows() as $row ) {
				if ( ! is_array( $row ) || ! isset( $row['status'] ) || WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] ) {
					continue;
				}

				++$out['approved'];

				if ( ! empty( $row['dashboard_account'] ) ) {
					++$out['with_accounts'];
				}

				if ( class_exists( 'WPCPM_Sponsor_Tiers' ) ) {
					++$out['tiers'][ WPCPM_Sponsor_Tiers::effective( $row ) ];
				}
			}
		}

		if ( class_exists( 'WPCPM_Sponsor_Offers' ) && class_exists( 'WPCPM_Sponsor_Codes' ) ) {
			foreach ( WPCPM_Sponsor_Offers::all() as $offer ) {
				if ( WPCPM_Sponsor_Offers::is_live( $offer ) ) {
					++$out['live_offers'];
				}

				// A claim made this semester counts whatever became of its offer since: an offer
				// that ended last week still gave out codes this semester.
				foreach ( WPCPM_Sponsor_Codes::claims( (int) $offer['id'] ) as $claim ) {
					if ( is_array( $claim ) && empty( $claim['v'] ) && isset( $claim['at'] ) && (int) $claim['at'] >= $from ) {
						++$out['claims_semester'];
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Approved sponsors whose term ends within the next sixty days, today included, soonest
	 * first: the day, the days left, and the manager to tell (S8). A term already over is not
	 * "ending" - `effective()` has already dropped that sponsor back to Friend - and is
	 * left to the Sponsors screen's Tier column, not repeated here.
	 *
	 * @param string $today A `Y-m-d` date, or '' for WPCPM_Sponsor_Tiers::today().
	 * @return array[] `record`, `name`, `tier` (the label), `until` (Y-m-d), `days`, `manager`
	 *                 (as `WPCPM_Sponsors_Index::manager_label()` names them: marked when they are
	 *                 no program manager now, '' when none is assigned) and `url` (the Edit form, a
	 *                 view of the Sponsors screen's Sponsors tab).
	 */
	public static function tiers_ending( $today = '' ) {
		$out = array();

		if ( ! class_exists( 'WPCPM_Sponsors_Index' ) || ! class_exists( 'WPCPM_Sponsor_Tiers' ) ) {
			return $out;
		}

		foreach ( WPCPM_Sponsors_Index::rows() as $record => $row ) {
			if ( ! is_array( $row ) || WPCPM_Sponsors_Index::STATUS_APPROVED !== ( isset( $row['status'] ) ? $row['status'] : '' ) ) {
				continue;
			}

			$left = WPCPM_Sponsor_Tiers::days_left( $row, $today );

			if ( null === $left || $left < 0 || $left > 60 ) {
				continue;
			}

			$out[] = array(
				'record'  => (string) $record,
				'name'    => self::sponsor_name( $record ),
				'tier'    => WPCPM_Sponsor_Tiers::label( isset( $row['tier'] ) ? $row['tier'] : '' ),
				'until'   => (string) $row['tier_until'],
				'days'    => (int) $left,
				'manager' => WPCPM_Sponsors_Index::manager_label( $row ),
				// Through the Sponsors screen's one builder of the Edit form's address, so the row
				// opens the form where every other link to it does. The plugin loads WPCPM_Sponsors
				// with the index and the tiers checked above, so the builder is always there.
				'url'     => WPCPM_Sponsors::edit_url( $record ),
			);
		}

		usort(
			$out,
			static function ( array $a, array $b ) {
				// Not a short ternary: the house rule is the full form, and "until" ties (a
				// day two sponsors share) still need the name to break them.
				return $a['until'] === $b['until'] ? strcmp( $a['name'], $b['name'] ) : strcmp( $a['until'], $b['until'] );
			}
		);

		return $out;
	}

	/**
	 * The sponsors' annual reports a manager has to finish, and those finished this semester
	 * (1.115.0): every draft, the oldest first; every term that ended on a tier carrying a report
	 * and has none yet, by the store's own rule of a term owed its report, the one the daily run
	 * drafts by; and every report marked final since that day's semester began, at the first moment
	 * the rest of the page counts a semester from.
	 *
	 * Through the report's store, which alone decides what is a draft, what is due and what is
	 * final, and never the posts themselves: each row is what the card prints. A report is named by
	 * its title, the sponsor's name and two months, and nothing of what it holds.
	 *
	 * @param string $today `Y-m-d`, the day a term must have ended before and whose semester the
	 *                      final reports are counted in; '' for today.
	 * @return array{queue: array[], due: array[], final: array[]} `queue` and `final` rows are
	 *               `post_id`, `sponsor` (the key), `name`, `title`, `until` (the term's last day),
	 *               `final_at` and `final_by` (the stamp, both 0 for a draft) and `url` (the report's
	 *               editor on the Sponsors screen); `due` rows are `sponsor`, `name` and `until`.
	 */
	public static function sponsor_reports( $today = '' ) {
		$out = array(
			'queue' => array(),
			'due'   => array(),
			'final' => array(),
		);

		// Both, or neither: the card's links and its form are the Sponsors screen's, and a store
		// without its screen would list drafts nothing could open.
		if ( ! class_exists( 'WPCPM_Sponsor_Report' ) || ! class_exists( 'WPCPM_Sponsor_Report_Screen' ) ) {
			return $out;
		}

		foreach ( WPCPM_Sponsor_Report::queue() as $post ) {
			$out['queue'][] = self::sponsor_report_row( $post );
		}

		foreach ( WPCPM_Sponsor_Report::due( $today ) as $term ) {
			$out['due'][] = array(
				'sponsor' => (string) $term['sponsor'],
				'name'    => (string) $term['name'],
				'until'   => (string) $term['until'],
			);
		}

		foreach ( WPCPM_Sponsor_Report::final_since( self::semester_start( $today )['from'] ) as $post ) {
			$out['final'][] = self::sponsor_report_row( $post );
		}

		return $out;
	}

	/**
	 * How many of the sponsors' reports ask for a press: the drafts to finish and the terms with no
	 * report. The strip's tile and the card's count, so the two cannot disagree.
	 *
	 * @param array $data sponsor_reports()'s answer.
	 * @return int
	 */
	private static function sponsor_reports_open( array $data ) {
		return ( isset( $data['queue'] ) ? count( (array) $data['queue'] ) : 0 ) + ( isset( $data['due'] ) ? count( (array) $data['due'] ) : 0 );
	}

	/**
	 * One report as the Sponsor reports card prints it.
	 *
	 * @param WP_Post $post A report, as the store answered it.
	 * @return array The row sponsor_reports() documents.
	 */
	private static function sponsor_report_row( WP_Post $post ) {
		$record = WPCPM_Sponsor_Report::sponsor_of( $post );
		$stamp  = WPCPM_Sponsor_Report::final_at( $post );

		return array(
			'post_id'  => (int) $post->ID,
			'sponsor'  => $record,
			'name'     => self::sponsor_name( $record ),
			'title'    => (string) $post->post_title,
			'until'    => WPCPM_Sponsor_Report::until_of( $post ),
			'final_at' => isset( $stamp['at'] ) ? (int) $stamp['at'] : 0,
			'final_by' => isset( $stamp['by'] ) ? (int) $stamp['by'] : 0,
			'url'      => WPCPM_Sponsor_Report_Screen::editor_url( (int) $post->ID ),
		);
	}

	/**
	 * A sponsor's name from the index, or its record ID when the index has no row for it.
	 *
	 * @param string $record Airtable record ID.
	 * @return string
	 */
	private static function sponsor_name( $record ) {
		$row = class_exists( 'WPCPM_Sponsors_Index' ) ? WPCPM_Sponsors_Index::row( $record ) : null;

		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : (string) $record;
	}

	/**
	 * A manager's way to one card of a sponsor's dashboard: the page, the switcher, the anchor.
	 *
	 * @param string $record Airtable record ID.
	 * @param string $card   The card's key, as its `wpcpm-sponsor-<card>` id spells it.
	 * @return string
	 */
	private static function sponsor_url( $record, $card ) {
		if ( ! class_exists( 'WPCPM_Sponsors_Dashboard' ) || ! class_exists( 'WPCPM_Sponsor_Roster' ) ) {
			return '';
		}

		// No page, no link ('' from card_url()): a link that reloads the Administrator Dashboard says nothing. At
		// the view that draws the card since the Sponsor Dashboard's views (1.123.78).
		return (string) WPCPM_Sponsors_Dashboard::card_url( (string) $card, (string) $record );
	}

	/**
	 * The Student Duplicate Finder's last scan, as the tile and its card read it (1.102.0).
	 *
	 * Through the scan's own `report()`, as every card reads through the class that owns its data,
	 * and the counts only: the names and addresses in the report stay on the finder's screen.
	 *
	 * @return array{addresses: int, ready: int, read: int, url: string}
	 */
	public static function duplicates() {
		$report = class_exists( 'WPCPM_Duplicates_Scan' ) ? WPCPM_Duplicates_Scan::report() : array();

		return array(
			'addresses' => isset( $report['counts']['addresses'] ) ? (int) $report['counts']['addresses'] : 0,
			'ready'     => isset( $report['counts']['ready'] ) ? (int) $report['counts']['ready'] : 0,
			'read'      => isset( $report['read'] ) ? (int) $report['read'] : 0,
			'url'       => admin_url( 'admin.php?page=wpcpm-tool-duplicate-finder' ),
		);
	}

	/**
	 * The tracks the Programs running card draws a tile for: track key => name, in the order
	 * `WPCPM_Program::labels()` lists them.
	 *
	 * Derived from the program map since 1.100.0, when the Track Builder made a track something
	 * the site can gain without a release. Until then this was a constant beside the map, and a
	 * track missing from it was one whose students were counted into a tile never drawn - and, on
	 * PHP 8, a warning on every load of the page. The order is still newest last: the map lists
	 * the tracks in the order they were made, the four original tracks, seeded before any other,
	 * first, so the tiles do not move under a reader who has learned where to look.
	 *
	 * @return array<string, string>
	 */
	public static function tracks() {
		$tracks = array();

		foreach ( WPCPM_Program::labels() as $status => $label ) {
			$key = WPCPM_Program::track( $status );

			if ( '' !== $key && ! isset( $tracks[ $key ] ) ) {
				$tracks[ $key ] = (string) $label;
			}
		}

		return $tracks;
	}

	/**
	 * The programs running: totals per track, and one row per institution with somebody in progress.
	 *
	 * Reads only `status`, `start`, `end`, `reports` and `mentor_name` off roster rows, and
	 * never Airtable. "Signed up this semester" is a start date in the current half-year, on a
	 * track status, and it stays that definition even for a row that has since paused,
	 * graduated or left: only a track status says which track a signed-up row was on.
	 * "finished this semester" is an end date inside it on the exact status `Graduate`, the
	 * same test `WPCPM_Cohort::participation()` uses to bucket a row as graduated rather than
	 * withdrawn - so a student who dropped out is not "finished", because nothing was; that
	 * student is in neither number, the way `participation()` also leaves them out of both
	 * `graduated` and `signed_up`'s finished-sounding buckets. One number rather than one per
	 * track because a graduate's status no longer says which track they were on (the spec's
	 * per-track finished count has no source in a row). Mentors are counted by distinct name:
	 * a roster row carries no mentor record ID.
	 *
	 * @return array `tracks`, `finished`, `rows`, `quiet`, `read`, `semester`.
	 */
	public static function programs() {
		$tracked  = WPCPM_Mentors_Sync::tracked_statuses();
		$active   = isset( $tracked['active'] ) ? (array) $tracked['active'] : array();
		$semester = WPCPM_Cohort::current();
		$window   = WPCPM_Cohort::range( $semester );
		$tracks   = array();
		$finished = 0;
		$rows     = array();
		$quiet    = 0;
		$read     = 0;
		// Read once: a test institution's row is listed in place and its figures stay out of every total.
		$tests = array_fill_keys( WPCPM_Test_Institutions::test_ids(), true );

		foreach ( array_keys( self::tracks() ) as $track ) {
			$tracks[ $track ] = array(
				'in_progress' => 0,
				'signed_up'   => 0,
			);
		}

		foreach ( WPCPM_Institutions_Index::rows() as $record => $institution ) {
			$record   = (string) $record;
			$counted  = ! isset( $tests[ $record ] );
			$envelope = WPCPM_Roster_Index::read( $record );
			$roster   = isset( $envelope['rows'] ) && is_array( $envelope['rows'] ) ? $envelope['rows'] : array();

			if ( empty( $roster ) ) {
				continue;
			}

			// The oldest non-zero read, not the newest: "Read from the program records on..." is
			// supposed to answer how stale the numbers on this page might be, and the newest
			// roster hides exactly the institution that answer is about - the one nobody has
			// synced in a while.
			$this_read = isset( $envelope['read'] ) ? (int) $envelope['read'] : 0;

			if ( $counted && $this_read > 0 && ( 0 === $read || $this_read < $read ) ) {
				$read = $this_read;
			}

			$in_progress = 0;
			$by_status   = array();
			$mentors     = array();
			$waiting     = 0;
			$earliest    = '';
			$latest      = '';

			foreach ( $roster as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

				if ( in_array( $status, WPCPM_Roster_Index::NEVER_SHOWN, true ) ) {
					continue;
				}

				$track = WPCPM_Program::track( $status );
				$end   = self::day( isset( $row['end'] ) ? $row['end'] : '' );

				if ( $counted && isset( $tracks[ $track ] ) && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) === $semester ) {
					++$tracks[ $track ]['signed_up'];
				}

				if ( in_array( $status, $active, true ) ) {
					++$in_progress;

					$label               = WPCPM_Program::label( $status );
					$by_status[ $label ] = ( isset( $by_status[ $label ] ) ? $by_status[ $label ] : 0 ) + 1;

					if ( $counted && isset( $tracks[ $track ] ) ) {
						++$tracks[ $track ]['in_progress'];
					}

					// The roster's own predicate, so this count and `groups()` cannot disagree.
					if ( WPCPM_Roster_Index::is_waiting( $row ) ) {
						++$waiting;
					}

					$mentor = isset( $row['mentor_name'] ) ? trim( (string) $row['mentor_name'] ) : '';

					if ( '' !== $mentor ) {
						$mentors[ $mentor ] = true;
					} elseif ( ! empty( $row['has_mentor'] ) ) {
						// A mentor on the Students row whose name never arrived: the name rides on
						// the reports join, and the join missed (WPCredits#222). The index holds no
						// mentor ID, so the row counts as one mentor of its own. "1 in progress, 0
						// mentors, 0 waiting" is a contradiction; two such rows sharing a mentor is
						// the rarer error, and the address fix removes it.
						$mentors[ 'row:' . ( isset( $row['record_id'] ) ? (string) $row['record_id'] : (string) count( $mentors ) ) ] = true;
					}

					if ( '' !== $end ) {
						if ( '' === $earliest || $end < $earliest ) {
							$earliest = $end;
						}

						if ( $end > $latest ) {
							$latest = $end;
						}
					}
				} elseif ( $counted && 'Graduate' === $status && '' !== $end && $end >= $window['from'] && $end <= $window['to'] ) {
					// Graduate only, not every `past_statuses` entry: WPCPM_Cohort::participation()'s
					// private bucket() buckets 'Graduate' as graduated and 'Dropped out' as withdrawn,
					// and this count has to agree with that rule rather than inventing its own - a
					// student who left is not "finished", because nothing about the program was.
					++$finished;
				}
			}

			if ( 0 === $in_progress ) {
				if ( $counted ) {
					++$quiet;
				}

				continue;
			}

			$reports = WPCPM_Semester_Report::reports_of( $record );
			$report  = 'none';

			if ( ! empty( $reports ) ) {
				$first  = reset( $reports );
				$report = $first instanceof WP_Post ? WPCPM_Semester_Report::state( $first ) : 'none';
			}

			$summary = WPCPM_Institution_Agreement::summary( $record );

			$rows[] = array(
				'record'      => $record,
				'name'        => self::institution_name( $record ),
				'in_progress' => $in_progress,
				'by_status'   => $by_status,
				'waiting'     => $waiting,
				'mentors'     => count( $mentors ),
				'earliest'    => $earliest,
				'latest'      => $latest,
				'agreement'   => isset( $summary['state'] ) ? (string) $summary['state'] : 'none',
				'report'      => $report,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return array(
			'tracks'   => $tracks,
			'finished' => $finished,
			'rows'     => $rows,
			'quiet'    => $quiet,
			'read'     => $read,
			'semester' => $semester,
		);
	}

	/**
	 * The students waiting to graduate (1.123.93): every roster row whose status is Pending graduation, the Students
	 * table's status, which the roster index carries and the program's records set together with their email about a
	 * student pending graduation, so a row of an account whose program row still says otherwise is listed by the
	 * roster's word. Every roster the site holds: those of the institutions the pipeline index lists, then those the
	 * roster counts name for an institution the index does not (a record gone from the Institutions table, or an index
	 * not read yet), whose name the site does not know (`known` false), then the rows that name no institution.
	 *
	 * Each row says when the students sync first saw it pending, from the account's stamp
	 * (`WPCPM_Students_Sync::META_PENDING`): `at`, 0 for a row with no account or no stamp, and `before`, a student
	 * already pending on the first run that kept the stamps. A test institution's student is listed, marked `test`,
	 * and left out of every count. Oldest stamp first, a row with none last, then the earlier end of the internship (a
	 * row with none last), then the name.
	 *
	 * The accounts are read in one go, and their meta with them (`cache_users()`), so sixty rows cost one read.
	 *
	 * Since 1.123.95 a student who finished Feedback form 3 on this site while the base did not take the status
	 * (`WPCPM_Student_Graduation::META_OWED`) is listed as well, whatever their roster row says, `owed` set and `at` the
	 * time the form was sent (`graduation_owed()`); counted like the others.
	 *
	 * @return array[] `record_id`, `name`, `institution` (an Institutions record ID, or ''), `known` (whether the pipeline
	 *                 index names it), `test` (for the mark only; the count asks the test helper), `user_id` (0 with no
	 *                 account), `end` (`Y-m-d` or ''), `hours`, `mentor_name`, `has_mentor`, `at`, `before`, `owed`.
	 */
	public static function graduation() {
		$tests   = array_fill_keys( WPCPM_Test_Institutions::test_ids(), true );
		$sources = array();
		$known   = array();

		foreach ( WPCPM_Institutions_Index::rows() as $record => $institution ) {
			$sources[ (string) $record ] = WPCPM_Roster_Index::rows( (string) $record );
			$known[ (string) $record ]   = true;
		}

		// A roster the students sync wrote for an institution the pipeline index does not hold: the sync files a Students
		// row under whatever record its link names, and the counts name every roster it wrote.
		$counts = WPCPM_Roster_Index::counts();

		foreach ( array_keys( isset( $counts['institutions'] ) ? (array) $counts['institutions'] : array() ) as $record ) {
			if ( ! isset( $sources[ (string) $record ] ) ) {
				$sources[ (string) $record ] = WPCPM_Roster_Index::rows( (string) $record );
			}
		}

		$sources[''] = WPCPM_Roster_Index::unlinked();
		$found       = array();
		$seen        = array();

		foreach ( $sources as $record => $roster ) {
			foreach ( (array) $roster as $row ) {
				if ( ! is_array( $row ) || WPCPM_Program::STATUS_PENDING !== trim( isset( $row['status'] ) ? (string) $row['status'] : '' ) ) {
					continue;
				}

				// One line a Students row, whatever the lists hold (1.123.95): a row a run moved onto a roster and a copy
				// still in the unlinked list are the same student, and the roster's, read first, is the one listed.
				$students_record = isset( $row['record_id'] ) ? trim( (string) $row['record_id'] ) : '';

				if ( '' !== $students_record && isset( $seen[ $students_record ] ) ) {
					continue;
				}

				$seen[ $students_record ] = true;

				$found[] = array(
					'record_id'   => isset( $row['record_id'] ) ? (string) $row['record_id'] : '',
					'name'        => isset( $row['name'] ) ? trim( (string) $row['name'] ) : '',
					'institution' => (string) $record,
					'known'       => isset( $known[ (string) $record ] ),
					'test'        => '' !== (string) $record && isset( $tests[ (string) $record ] ),
					'user_id'     => isset( $row['user_id'] ) ? max( 0, (int) $row['user_id'] ) : 0,
					'end'         => self::day( isset( $row['end'] ) ? $row['end'] : '' ),
					'hours'       => isset( $row['hours'] ) ? (string) $row['hours'] : '',
					'mentor_name' => isset( $row['mentor_name'] ) ? trim( (string) $row['mentor_name'] ) : '',
					'has_mentor'  => ! empty( $row['has_mentor'] ),
					'at'          => 0,
					'before'      => false,
					'owed'        => false,
				);
			}
		}

		// A student who finished Feedback form 3 on this site while the base did not take the status (1.123.95): listed
		// all the same, marked `owed`, waiting since the form was sent, whatever their roster row says.
		$found = array_merge( $found, self::graduation_owed( $found, $sources, $known, $tests ) );

		$ids = array_values( array_unique( array_filter( array_column( $found, 'user_id' ) ) ) );

		if ( ! empty( $ids ) && function_exists( 'cache_users' ) ) {
			cache_users( $ids );
		}

		foreach ( $found as $n => $row ) {
			if ( $row['user_id'] < 1 || ! get_userdata( $row['user_id'] ) ) {
				$found[ $n ]['user_id'] = 0;
				continue;
			}

			if ( $row['owed'] ) {
				continue;
			}

			$stamp = get_user_meta( $row['user_id'], WPCPM_Students_Sync::META_PENDING, true );

			if ( is_array( $stamp ) && ! empty( $stamp['at'] ) ) {
				$found[ $n ]['at']     = (int) $stamp['at'];
				$found[ $n ]['before'] = ! empty( $stamp['before'] );
			}
		}

		usort(
			$found,
			static function ( array $a, array $b ) {
				$order = array(
					array( 0 === $a['at'] ? 1 : 0, $a['at'], '' === $a['end'] ? 1 : 0, $a['end'] ),
					array( 0 === $b['at'] ? 1 : 0, $b['at'], '' === $b['end'] ? 1 : 0, $b['end'] ),
				);

				if ( $order[0] !== $order[1] ) {
					return $order[0] < $order[1] ? -1 : 1;
				}

				$name = strcasecmp( $a['name'], $b['name'] );

				return 0 !== $name ? $name : strcmp( $a['record_id'], $b['record_id'] );
			}
		);

		return $found;
	}

	/**
	 * The students who owe a Pending graduation and are not on the list by their roster row (1.123.95): each from the
	 * roster row the account is joined to, whatever its status, or from the account itself when the site holds no
	 * Students row for it (its name, its institution, and its program row's end and hours, its mentor card's name).
	 * `at` is when the form was sent. `owed` is the debt's kind, which picks the line: `retry` (the students sync writes
	 * it when the records take it), `no-row` (no Students row matched, so an Administrator checks the row) or
	 * `other-email` (the row holds another address, so an Administrator corrects it). `course` is the track the student
	 * is on today, from the roster row's status or else the program row's, for the Track and Hours cells. An account
	 * whose program status is no longer a track is not listed: that debt can no longer be paid.
	 *
	 * @param array[] $found   The rows already listed.
	 * @param array   $sources Institutions record ID, or '' for the rows that name none => that roster's rows.
	 * @param array   $known   Institutions record ID => true, for the ones the pipeline index names.
	 * @param array   $tests   Institutions record ID => true, for the test ones.
	 * @return array[] Rows in `graduation()`'s shape, `owed` the debt's kind, and `course`.
	 */
	private static function graduation_owed( array $found, array $sources, array $known, array $tests ) {
		if ( ! class_exists( 'WPCPM_Student_Graduation' ) ) {
			return array();
		}

		$listed = array_fill_keys( array_filter( array_column( $found, 'user_id' ) ), true );
		$out    = array();

		foreach ( WPCPM_Student_Graduation::owing() as $user_id => $owed ) {
			$user = get_userdata( $user_id );

			// A debt that can no longer be paid, the status no longer a track, is not a student waiting (the students
			// sync clears it at its next run).
			if ( isset( $listed[ $user_id ] ) || ! $user || ! WPCPM_Student_Graduation::on_track( $user_id ) ) {
				continue;
			}

			$row    = array();
			$record = '';

			foreach ( $sources as $institution => $roster ) {
				foreach ( (array) $roster as $candidate ) {
					if ( is_array( $candidate ) && (int) ( isset( $candidate['user_id'] ) ? $candidate['user_id'] : 0 ) === (int) $user_id
						&& ! in_array( trim( isset( $candidate['status'] ) ? (string) $candidate['status'] : '' ), WPCPM_Roster_Index::NEVER_SHOWN, true ) ) {
						$row    = $candidate;
						$record = (string) $institution;
						break 2;
					}
				}
			}

			if ( empty( $row ) ) {
				$program = get_user_meta( $user_id, WPCPM_Students_Sync::META_PROGRAM, true );
				$program = is_array( $program ) ? $program : array();
				$mentor  = get_user_meta( $user_id, WPCPM_Students_Sync::META_MENTOR, true );
				$mentor  = is_array( $mentor ) ? $mentor : array();
				$placed  = trim( (string) get_user_meta( $user_id, WPCPM_Students_Sync::META_INSTITUTION, true ) );
				$record  = WPCPM_Mentors_Sync::is_record_id( $placed ) ? $placed : '';
				$row     = array(
					'name'        => ! empty( $program['name'] ) ? (string) $program['name'] : (string) $user->display_name,
					'end'         => isset( $program['end'] ) ? $program['end'] : '',
					'hours'       => isset( $program['hours'] ) ? $program['hours'] : '',
					'mentor_name' => isset( $mentor['name'] ) ? $mentor['name'] : '',
					'has_mentor'  => ! empty( $mentor['record_id'] ),
				);
			}

			$out[] = array(
				'record_id'   => isset( $row['record_id'] ) ? (string) $row['record_id'] : '',
				'name'        => isset( $row['name'] ) ? trim( (string) $row['name'] ) : '',
				'institution' => $record,
				'known'       => isset( $known[ $record ] ),
				'test'        => '' !== $record && isset( $tests[ $record ] ),
				'user_id'     => (int) $user_id,
				'end'         => self::day( isset( $row['end'] ) ? $row['end'] : '' ),
				'hours'       => isset( $row['hours'] ) && is_scalar( $row['hours'] ) ? (string) $row['hours'] : '',
				'mentor_name' => isset( $row['mentor_name'] ) && is_scalar( $row['mentor_name'] ) ? trim( (string) $row['mentor_name'] ) : '',
				'has_mentor'  => ! empty( $row['has_mentor'] ),
				'at'          => (int) $owed['at'],
				'before'      => false,
				'owed'        => isset( $row['record_id'] ) ? $owed['kind'] : WPCPM_Student_Graduation::KIND_NO_ROW,
				// The track they are on today, as a recorded student's course is drawn: no course is kept for them yet.
				'course'      => self::graduation_track( isset( $row['status'] ) ? $row['status'] : '', $user_id ),
			);
		}

		return $out;
	}

	/**
	 * The track an owed student is on today: their roster row's status when it is a track, else their program row's.
	 *
	 * @param string $status  The roster row's status, or '' with no row.
	 * @param int    $user_id The account.
	 * @return string A track's status, or ''.
	 */
	private static function graduation_track( $status, $user_id ) {
		$status = trim( (string) $status );

		if ( WPCPM_Program::is_track( $status ) ) {
			return $status;
		}

		$program = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_PROGRAM, true );
		$held    = is_array( $program ) && isset( $program['program'] ) && is_scalar( $program['program'] ) ? trim( (string) $program['program'] ) : '';

		return WPCPM_Program::is_track( $held ) ? $held : '';
	}

	/**
	 * The students waiting to graduate that count: real ones, a test institution's left out.
	 *
	 * @param array[] $rows What `graduation()` returned.
	 * @return int
	 */
	private static function graduation_real( array $rows ) {
		return WPCPM_Test_Institutions::count_real_rows( $rows, 'institution' );
	}

	/**
	 * The syncs, the outcome figures' weekly read among them, whether Automatic sync is on, the private
	 * storage probe, the latest email and the last day's failures from the email log (and whether the
	 * log's table is there yet), and the invitation run.
	 *
	 * @return array
	 */
	public static function health() {
		$syncs = array(
			'students'     => array(
				'label'    => __( 'Students', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Students_Sync::progress(),
				'last'     => (int) get_option( WPCPM_Students_Sync::OPT_LAST, 0 ),
				'next'     => (int) wp_next_scheduled( WPCPM_Students_Sync::CRON_AUTO ),
				// The Students screen's Sync tab, where the run is started and followed, rather than
				// the Accounts tab the screen opens on.
				'screen'   => admin_url( 'admin.php?page=wpcpm-students&tab=sync' ),
			),
			'mentors'      => array(
				'label'    => __( 'Mentors', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Mentors_Sync::progress(),
				'last'     => (int) get_option( WPCPM_Mentors_Sync::OPT_LAST, 0 ),
				'next'     => (int) wp_next_scheduled( WPCPM_Mentors_Sync::CRON_DAILY ),
				// The Mentors screen's Sync tab, as the Students screen's above.
				'screen'   => admin_url( 'admin.php?page=wpcpm-mentors&tab=sync' ),
			),
			'institutions' => array(
				'label'    => __( 'Institutions', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Institutions_Sync::progress(),
				'last'     => (int) WPCPM_Institutions_Sync::last_read(),
				'next'     => (int) wp_next_scheduled( WPCPM_Institutions_Sync::CRON_DAILY ),
				// The Institutions screen's Sync and storage tab, as the Students screen's above.
				'screen'   => admin_url( 'admin.php?page=wpcpm-institutions&tab=sync' ),
			),
		);

		// The outcome figures' weekly read (1.122.24), last, behind the same guard, in the shape every
		// row has: its name opens the Connection tab of Settings, where a failure is fixed, and its note
		// says what the last attempt did when that was not to store (`WPCPM_Outcome_Figures::sync_row()`).
		if ( class_exists( 'WPCPM_Outcome_Figures' ) ) {
			$syncs['outcome_figures'] = WPCPM_Outcome_Figures::sync_row();
		}

		$probe  = WPCPM_Private_Files::probe_result();
		$latest = WPCPM_Mail_Log::latest();

		return array(
			'syncs'     => $syncs,
			// Read once, for the Syncs and health card's row: whether the syncs run by themselves
			// decides what an old run means there. On by default, as the setting is.
			'auto_sync' => (bool) WPCPM_Settings::get_value( 'auto_sync', true ),
			'probe'     => array(
				'verdict' => is_array( $probe ) ? WPCPM_Private_Files::verdict( $probe ) : 'unknown',
				'time'    => is_array( $probe ) && isset( $probe['time'] ) ? (int) $probe['time'] : 0,
			),
			'mail'      => array(
				// Whether the log's table is there: with none, "nothing sent" would be a guess.
				'ready'  => (bool) WPCPM_Mail_Log::exists(),
				'latest' => is_array( $latest ) ? $latest : array(),
				'failed' => (int) WPCPM_Mail_Log::failures_since( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ),
			),
			'invites'   => array(
				'run'    => WPCPM_Mail::run(),
				'queued' => (int) WPCPM_Mail::queued(),
			),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * Drawing
	 * --------------------------------------------------------------------
	 */

	/**
	 * Institution applications: the open ones with the six decisions, the closed ones folded, and
	 * last the way to the Institutions screen's queue tab, which lists the same open applications.
	 *
	 * @param array $data `open`, `open_total`, `closed`, `closed_total`.
	 */
	public static function render_applications( array $data ) {
		$open         = isset( $data['open'] ) ? (array) $data['open'] : array();
		$open_total   = isset( $data['open_total'] ) ? (int) $data['open_total'] : WPCPM_Test_Institutions::count_real_rows( $open, 'post_title' );
		$closed       = isset( $data['closed'] ) ? (array) $data['closed'] : array();
		$closed_total = isset( $data['closed_total'] ) ? (int) $data['closed_total'] : WPCPM_Test_Institutions::count_real_rows( $closed, 'post_title' );
		$module       = class_exists( 'WPCPM_Modules' ) ? WPCPM_Modules::get( 'institutions' ) : null;
		$screen       = self::institutions_screen( 'queue', __( 'Institution applications', 'wpcredits-program-manager' ) );

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'applications',
					'title'  => __( 'Institution applications', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'institution-application', __( 'Institution applications', 'wpcredits-program-manager' ) ),
					'count'  => $open_total,
					'open'   => $open_total > 0,
					'state'  => self::waiting( $open_total ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $open ) ) {
			self::empty_line( __( 'No application is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $open as $post ) {
			if ( $post instanceof WP_Post ) {
				self::render_application( $post, $module );
			}
		}

		// The line saying how many more are waiting counts the open list, so it sits right under it,
		// before the rejected and spam ones, where it reads as that list's. It links nowhere: the
		// card's way to the queue tab is its last line, below.
		// Both sides real: the total leaves a test institution's applications out, so the rows drawn
		// do too, and a listed test application is no row of the count.
		$open_real = WPCPM_Test_Institutions::count_real_rows( $open, 'post_title' );

		if ( $open_total > $open_real ) {
			self::render_more_line( $open_total - $open_real );
		}

		if ( ! empty( $closed ) ) {
			$closed_real = WPCPM_Test_Institutions::count_real_rows( $closed, 'post_title' );
			$summary     = sprintf(
				/* translators: %s: a number of applications. */
				_n( '%s rejected or spam application', '%s rejected or spam applications', $closed_real, 'wpcredits-program-manager' ),
				number_format_i18n( $closed_real )
			);
			// The inner fold, the summary its title, closed (Task 17b fix round).
			echo WPCPM_UI::inner_open( array( 'title' => $summary ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open().

			foreach ( $closed as $post ) {
				if ( $post instanceof WP_Post ) {
					self::render_application( $post, $module );
				}
			}

			if ( $closed_total > $closed_real ) {
				self::render_more_line( $closed_total - $closed_real, true );
			}

			echo WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The line under a list this page cut at self::LIMIT, so the page never claims to be showing
	 * more than it draws; it links nowhere. Under the open list: "N more are waiting; they are
	 * listed here as these are decided", since the Institutions screen's queue lists the same window
	 * of the oldest, so the rest reach a list as the ones above them are decided. Under the closed
	 * fold, whose rejected and spam applications are decided already and which the screen does not
	 * list: "N more are kept; they are listed here as these are deleted or put back in the queue".
	 * The sponsor cards print the open list's line under theirs: the applications card as it stands,
	 * the posts and agreements cards through `render_sponsor_more_line()`.
	 *
	 * @param int    $n      How many were left off the list, which chooses the singular or the plural.
	 *                       With `$shown`, the number those words stand for: past a count's ceiling,
	 *                       the least that is known, which the words print with a plus sign.
	 * @param bool   $closed True for the closed fold (rejected and spam applications).
	 * @param string $shown  The words for `$n` when they are not the number as it stands: past a
	 *                       count's ceiling, the least it vouches for with a plus sign.
	 */
	private static function render_more_line( $n, $closed = false, $shown = '' ) {
		if ( $closed ) {
			/* translators: %s: a number of applications. */
			$line = _n( '%s more is kept; it is listed here as these are deleted or put back in the queue.', '%s more are kept; they are listed here as these are deleted or put back in the queue.', $n, 'wpcredits-program-manager' );
		} else {
			/* translators: %s: a number of applications, sponsor posts or sponsor agreements, or past the count's ceiling the least of them with a plus sign, such as 150+. */
			$line = _n( '%s more is waiting; it is listed here as these are decided.', '%s more are waiting; they are listed here as these are decided.', $n, 'wpcredits-program-manager' );
		}

		$words = sprintf( $line, '' !== (string) $shown ? (string) $shown : number_format_i18n( $n ) );

		// The parts' muted note in today's words, with no hairline.
		echo WPCPM_UI::intro( $words, 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
	}

	/**
	 * Under the sponsor posts or the sponsor agreements card's list, while more wait than it holds, the
	 * line `render_more_line()` prints under the institution applications' open list.
	 *
	 * How many wait is the count's read, which stops one past `WPCPM_Sponsors::COUNT_MAX`, so past
	 * that ceiling the line says no more than the read vouches for: the ceiling less the rows drawn,
	 * with the plus sign `WPCPM_Sponsors::queue_count()` prints. The singular or the plural follows
	 * the number the line prints: both come from `WPCPM_Sponsors::queue_number()`, so they cannot
	 * part.
	 *
	 * @param int $total How many wait, as `collect()` counted them.
	 * @param int $drawn How many rows the card's list holds.
	 */
	private static function render_sponsor_more_line( $total, $drawn ) {
		if ( (int) $total <= (int) $drawn ) {
			return;
		}

		self::render_more_line( WPCPM_Sponsors::queue_number( $total, $drawn ), false, WPCPM_Sponsors::queue_count( $total, $drawn ) );
	}

	/**
	 * One application, on the shared parts (`render_application_parts()`): its title, linking to it in
	 * the Institutions screen's queue as a sponsor application's links to its own, its facts with the
	 * state in the Institutions screen's words, a note when the anti-spam checks held it, its answers
	 * behind a fold, and the module's decisions.
	 *
	 * @param WP_Post     $post   The application.
	 * @param object|null $module The Institutions module, which owns the answers and the forms.
	 */
	private static function render_application( WP_Post $post, $module ) {
		$state   = WPCPM_Institutions::application_state( $post );
		$country = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY, true );
		$name    = trim( (string) $post->post_title );
		$at      = (int) get_post_time( 'U', true, $post );
		$manager = WPCPM_Institution_Assignment::application_label( (int) $post->ID );

		self::render_application_parts( $post, $module, $state, $country, $name, $at, $manager );
	}

	/**
	 * One application on the shared parts (design 2.10, 2.3, 4.1): the item header (the name linked to the queue, the test mark, the reference, the state as a
	 * neutral tag, then the country, the Administrator and the received date as facts), the held note as
	 * today, "Read the application" as an inner fold, and the module's one Decide area.
	 *
	 * The item keeps its article and its anchor, which a mail, the queue and a decision's way back link to.
	 * The Decide area follows the fold straight inside it, with no wrapper of its own: the card's body gives
	 * the room around it.
	 *
	 * @param WP_Post     $post    The application.
	 * @param object|null $module  The Institutions module.
	 * @param string      $state   Its state.
	 * @param string      $country Its country's record ID.
	 * @param string      $name    Its name as filed.
	 * @param int         $at      When it was received.
	 * @param string      $manager The Administrator it is assigned to, or ''.
	 */
	private static function render_application_parts( WP_Post $post, $module, $state, $country, $name, $at, $manager ) {
		$facts = array( WPCPM_Institutions::country_name( $country, (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ) ) );

		if ( '' !== $manager ) {
			/* translators: %s: an Administrator's name or address. */
			$facts[] = sprintf( __( 'Assigned to %s', 'wpcredits-program-manager' ), $manager );
		}

		$facts[] = self::received( $at );

		$header = WPCPM_UI::item_header(
			array(
				'name'  => '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ),
				'href'  => WPCPM_Institution_Application::queue_url( (int) $post->ID ),
				'mark'  => WPCPM_Test_Institutions::mark( $name, WPCPM_Test_Institutions::MARK_DASHBOARD ),
				'ref'   => WPCPM_Institutions::application_reference( $post ),
				'tag'   => array(
					'words' => WPCPM_Institutions::application_state_label( $state ),
					'tone'  => '',
				),
				'facts' => $facts,
			)
		);

		echo WPCPM_UI::item_open( 'wpcpm-application', 'wpcpm-application-' . (int) $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
		echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header(); mark() escapes its classes, title and word.

		if ( WPCPM_Institution_Application::STATE_HELD === $state ) {
			echo WPCPM_UI::note( __( 'Held by the anti-spam checks. Open it on the Institutions screen to read which.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::note().
		}

		echo WPCPM_UI::inner_open( array( 'title' => __( 'Read the application', 'wpcredits-program-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open().

		if ( is_object( $module ) && method_exists( $module, 'render_application_answers' ) ) {
			$module->render_application_answers( $post );
		}

		echo WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.

		if ( is_object( $module ) && method_exists( $module, 'render_application_actions' ) ) {
			$module->render_application_actions( $post, $state, WPCPM_Return::DASHBOARD );
		}

		echo '</article>';
	}

	/**
	 * Collaboration Agreements: awaiting review with the panel's review block, then the
	 * returned and the revoked, folded, and last the way to the Institutions screen's queue tab,
	 * which lists the agreements waiting for review. Every decision made here comes back to this
	 * card.
	 *
	 * @param array $data `awaiting`, `returned`, `revoked`, `overdue`.
	 */
	public static function render_agreements( array $data ) {
		$awaiting = isset( $data['awaiting'] ) ? (array) $data['awaiting'] : array();
		$returned = isset( $data['returned'] ) ? (array) $data['returned'] : array();
		$revoked  = isset( $data['revoked'] ) ? (array) $data['revoked'] : array();
		$screen   = self::institutions_screen( 'queue', __( 'Collaboration Agreements', 'wpcredits-program-manager' ) );

		// A test institution's agreement is listed below and left out of the count.
		$count = WPCPM_Test_Institutions::count_real_rows( $awaiting, 'record' );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'agreements',
					'title'  => __( 'Collaboration Agreements', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'institution-agreement', __( 'Collaboration Agreements', 'wpcredits-program-manager' ) ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);

		self::render_agreements_parts( $awaiting, $returned, $revoked );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Collaboration Agreements card's body on the shared parts (design 2.10, 2.8, 4.1): each agreement awaiting review is an item header (the name linked
	 * to the institution, its test mark, the tag "Awaiting review", red while overdue, then the upload and Overdue as facts) with
	 * no heading above the items, then the panel's review block, whose Decide area the panel draws; the
	 * returned and the revoked are folded under today's summaries as list rows.
	 *
	 * Each item keeps its article, and the overdue one its mark, as today.
	 *
	 * @param array $awaiting `id`, `record`, `at`, `overdue`.
	 * @param array $returned `id`, `record`, `at`, `note`.
	 * @param array $revoked  The same.
	 */
	private static function render_agreements_parts( array $awaiting, array $returned, array $revoked ) {
		if ( empty( $awaiting ) ) {
			self::empty_line( __( 'No signed agreement is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $awaiting as $row ) {
			$name  = self::institution_name( $row['record'] );
			$facts = array( self::uploaded( (int) $row['at'] ) );

			if ( ! empty( $row['overdue'] ) ) {
				$facts[] = __( 'Overdue', 'wpcredits-program-manager' );
			}

			$header = WPCPM_UI::item_header(
				array(
					'name'  => $name,
					'href'  => self::institution_href( $row['record'] ),
					'mark'  => WPCPM_Test_Institutions::mark( $name, WPCPM_Test_Institutions::MARK_DASHBOARD ),
					// Red while it is overdue, which the facts also say in words.
					'tag'   => array(
						'words' => __( 'Awaiting review', 'wpcredits-program-manager' ),
						'tone'  => ! empty( $row['overdue'] ) ? 'red' : '',
					),
					'facts' => $facts,
				)
			);

			// An overdue item says so by its red tag and its word; the parts draw no side stripe (Task 17b, ruling 7).
			echo WPCPM_UI::item_open( 'wpcpm-agreement' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
			echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header(); mark() escapes its classes, title and word.
			WPCPM_Institution_Panel::render_review( (int) $row['id'], true, WPCPM_Return::DASHBOARD );
			echo '</article>';
		}

		self::render_agreement_rows(
			$returned,
			/* translators: %s: a number of institutions. */
			__( '%s returned, waiting for a new upload', 'wpcredits-program-manager' ),
			/* translators: %s: a number of institutions. */
			__( '%s returned, waiting for new uploads', 'wpcredits-program-manager' ),
			false
		);
		self::render_agreement_rows(
			$revoked,
			/* translators: %s: a number of institutions. */
			__( '%s revoked agreement', 'wpcredits-program-manager' ),
			/* translators: %s: a number of institutions. */
			__( '%s revoked agreements', 'wpcredits-program-manager' ),
			true
		);
	}

	/**
	 * A folded list of returned or revoked agreements on the shared parts, under today's summary: one list row
	 * each, the name linked to the institution, the date as a fact, the note on its own line, and on a revoked one Reinstate
	 * as the quiet row action, a form that posts at once what today's form posts.
	 *
	 * @param array  $rows      `id`, `record`, `at`, `note`.
	 * @param string $singular  The summary, singular, with %s for the count.
	 * @param string $plural    The summary, plural.
	 * @param bool   $reinstate Whether to offer Reinstate.
	 */
	private static function render_agreement_rows( array $rows, $singular, $plural, $reinstate ) {
		if ( empty( $rows ) ) {
			return;
		}

		// The sentences are translated by the caller, so _n() would translate them twice; the count picks the form here.
		$real = WPCPM_Test_Institutions::count_real_rows( $rows, 'record' );

		// The inner fold, today's summary its title, closed as today (Task 17b fix round).
		echo WPCPM_UI::inner_open( array( 'title' => sprintf( 1 === $real ? $singular : $plural, number_format_i18n( $real ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open().
		// No row carries a tag, so each row's date sits under its name (Task 18c).
		echo WPCPM_UI::list_open( array( 'facts' => 'under' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.

		foreach ( $rows as $row ) {
			$name = self::institution_name( $row['record'] );
			$line = WPCPM_UI::row(
				array(
					'name'    => $name,
					'href'    => self::institution_href( $row['record'] ),
					'mark'    => WPCPM_Test_Institutions::mark( $name, WPCPM_Test_Institutions::MARK_DASHBOARD ),
					'facts'   => array( self::when_whole( (int) $row['at'] ) ),
					// The Administrator's note on a line of its own, its line breaks kept.
					'note'    => (string) $row['note'],
					'actions' => $reinstate ? array( self::reinstate_form( (int) $row['id'] ) ) : array(),
				)
			);

			echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row(); mark() escapes its classes, title and word; the form in reinstate_form().
		}

		echo WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
		echo WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
	}

	/**
	 * The Reinstate form on the shared parts, drawn here because nothing else draws one: the handler and its nonce
	 * exist in the agreement class. It posts to admin-post.php under the once guard, the `ACTION_REINSTATE` nonce
	 * keyed to the document printed with no id (`WPCPM_UI::nonce()`), since a card may hold more than one, the
	 * document and the way back to this card; its one button is the quiet row action. It asks nothing before it posts.
	 *
	 * @param int $post_id The revoked document.
	 * @return string Escaped markup.
	 */
	private static function reinstate_form( $post_id ) {
		ob_start();
		WPCPM_Return::field( WPCPM_Return::DASHBOARD, 'agreements' );
		$back = (string) ob_get_clean();

		return sprintf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Reinstating', 'wpcredits-program-manager' )
		)
			. WPCPM_UI::nonce( WPCPM_Institution_Agreement::ACTION_REINSTATE . '_' . (int) $post_id )
			. sprintf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Agreement::ACTION_REINSTATE ) )
			. sprintf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id )
			. $back
			. WPCPM_UI::button(
				array(
					'label' => __( 'Reinstate', 'wpcredits-program-manager' ),
					'kind'  => 'quiet',
					// Drawn on the Administrator Dashboard alone, which is never wp-admin.
					'side'  => 'front',
				)
			)
			. '</form>';
	}

	/**
	 * Semester reports: drafts to review, cohorts due for drafting, approved this semester, and
	 * last the way to the Institutions screen's Semester reports tab.
	 *
	 * No approve button here: approval belongs in the editor, where the manager has read the
	 * document. Every link in the three lists opens the Institution Dashboard as that institution,
	 * so the card's own way to wp-admin is the line at its foot.
	 *
	 * @param array $data `queue`, `due`, `approved`.
	 */
	public static function render_reports( array $data ) {
		$queue    = isset( $data['queue'] ) ? (array) $data['queue'] : array();
		$due      = isset( $data['due'] ) ? (array) $data['due'] : array();
		$approved = isset( $data['approved'] ) ? (array) $data['approved'] : array();
		$screen   = self::institutions_screen( 'reports', __( 'Semester reports', 'wpcredits-program-manager' ) );

		// Each table's box is named by the card's heading and the subheading above the table.
		$review_title = 'wpcpm-reports-review-title';
		$due_title    = 'wpcpm-reports-due-title';

		$count = WPCPM_Test_Institutions::count_real_rows( $queue, 'institution' ) + WPCPM_Test_Institutions::count_real_rows( $due, 'institution' );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'reports',
					'title'  => __( 'Semester reports', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'semester-report', __( 'Semester reports', 'wpcredits-program-manager' ) ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);

		self::render_reports_parts( $queue, $due, $approved, array( $review_title, $due_title, 'wpcpm-reports-approved-title' ) );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Semester reports card's three parts on the shared parts (design 2.7, 2.11, 4.1): each part's heading through the heading part, the two ids kept and one given to
	 * "Approved this semester" so its table is named as the other two are, then its table through the table part,
	 * named by the card's title and that heading (the table part replaces today's scroll box), or today's empty
	 * sentence as the parts' muted line. The counts and the age are numbers; the day a window ended is a date; when a
	 * report was approved and by whom is `marked_final()`'s words. The action column holds the quiet row action:
	 * Review and View, the links to the report's editor as today, each naming its institution and semester for a
	 * screen reader, and Draft now, the report screen's own form, coming back here as today.
	 *
	 * @param array[]  $queue    The drafts to review.
	 * @param array[]  $due      The semesters due for drafting.
	 * @param array[]  $approved The reports approved this semester.
	 * @param string[] $titles   The three headings' ids: to review, due, approved.
	 */
	private static function render_reports_parts( array $queue, array $due, array $approved, array $titles ) {
		$card     = self::title_id( 'reports' );
		$column   = static function ( $key, $label, $kind ) {
			return array(
				'key'   => $key,
				'label' => $label,
				'kind'  => $kind,
			);
		};
		$semester = $column( 'semester', __( 'Semester', 'wpcredits-program-manager' ), 'text' );
		$drafted  = $column( 'drafted', __( 'Drafted', 'wpcredits-program-manager' ), 'text' );
		$progress = $column( 'progress', __( 'Still in progress', 'wpcredits-program-manager' ), 'num' );
		$age      = $column( 'age', __( 'Age', 'wpcredits-program-manager' ), 'num' );
		$ended    = $column( 'ended', __( 'Window ended', 'wpcredits-program-manager' ), 'date' );
		$when     = $column( 'approved', __( 'Approved', 'wpcredits-program-manager' ), 'text' );
		$action   = $column( 'action', '', 'action' );

		// The institution is each row's header, as on every other wide table (Task 17b, ruling 4).
		$school           = $column( 'institution', __( 'Institution', 'wpcredits-program-manager' ), 'text' );
		$school['header'] = 'row';

		// The three tables are one family (design 2.7; task V1, ruling 14): each column of a kind starts at the same
		// place in all three, the institution wide enough for a school's name on one line where the card has room.
		$sized = static function ( array $col, $width ) {
			$col['width'] = $width;
			return $col;
		};
		// A semester's name is whole (about 150px with its cell): at 20% the family needs about 870px, so it stacks while
		// the main area is under 936px; the semester takes 2% from the drafted and ended column (Task 18f, fix round 2).
		$family = array(
			'fixed' => true,
			'wide'  => 936,
		);
		// Shares that hold each column's longest unbroken content at 902px of table, the narrowest it is drawn as one: the
		// whole semester (about 174px), a whole date (about 149px), "Still in progress" (about 124px), "441 days" (about
		// 79px), Review (about 62px); the school takes the rest (Task 18f, fix round 3).
		$school   = $sized( $school, '23%' );
		$semester = $sized( $semester, '20%' );
		$drafted  = $sized( $drafted, '18%' );
		$ended    = $sized( $ended, '18%' );
		$progress = $sized( $progress, '15%' );
		$age      = $sized( $age, '10%' );

		// The row action: a quiet link to the report's editor as that institution, as today, naming the institution
		// and the semester for a screen reader, since every row's link says the same word.
		// With no address for the report (the Institution Dashboard's page is missing), the cell holds nothing: a quiet
		// button with no link would print as a submit outside any form.
		$open = static function ( $label, array $row ) {
			$href = self::report_link( $row['institution'], $row['cohort'] );

			if ( '' === $href ) {
				return '';
			}

			return WPCPM_UI::button(
				array(
					'label'   => $label,
					'kind'    => 'quiet',
					'href'    => $href,
					'context' => self::institution_name( $row['institution'] ) . ', ' . WPCPM_Cohort::label( $row['cohort'] ),
					// Drawn on the Administrator Dashboard alone, which is never wp-admin.
					'side'    => 'front',
				)
			);
		};

		echo WPCPM_UI::heading( __( 'To review', 'wpcredits-program-manager' ), 'h4', $titles[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		if ( empty( $queue ) ) {
			echo WPCPM_UI::list_empty( __( 'No draft is waiting for review.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().
		} else {
			$table = WPCPM_UI::table_open( array( $school, $semester, $drafted, $progress, $age, $sized( $action, '14%' ) ), $family + array( 'labelledby' => $card . ' ' . $titles[0] ) );

			foreach ( $queue as $row ) {
				$table .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $school, self::institution_link( $row['institution'] ) )
					. WPCPM_UI::cell( $semester, esc_html( self::whole_text( WPCPM_Cohort::label( $row['cohort'] ) ) ) )
					. WPCPM_UI::cell( $drafted, esc_html( self::drafted( (int) $row['generated'], (string) $row['origin'] ) ) )
					. WPCPM_UI::cell( $progress, esc_html( number_format_i18n( (int) $row['in_progress'] ) ) )
					. WPCPM_UI::cell( $age, esc_html( sprintf( /* translators: %s: a number of days. */ _n( '%s day', '%s days', (int) $row['age_days'], 'wpcredits-program-manager' ), number_format_i18n( (int) $row['age_days'] ) ) ) )
					. WPCPM_UI::cell( $action, $open( __( 'Review', 'wpcredits-program-manager' ), $row ) )
					. WPCPM_UI::row_close();
			}

			echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; institution_link() escapes the name and the href, each value through esc_html() above, the action through WPCPM_UI::button().
		}

		echo WPCPM_UI::heading( __( 'Due for drafting', 'wpcredits-program-manager' ), 'h4', $titles[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		if ( empty( $due ) ) {
			echo WPCPM_UI::list_empty( __( 'Nothing is waiting: every finished semester has a report, or is not finished yet.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().
		} else {
			$table = WPCPM_UI::table_open( array( $school, $semester, $ended, $progress, $action ), $family + array( 'labelledby' => $card . ' ' . $titles[1] ) );

			foreach ( $due as $pair ) {
				// The dashboard return, as today: a refusal comes back to this card.
				ob_start();
				WPCPM_Semester_Report_Screen::render_draft_form( $pair['institution'], $pair['cohort'], '', WPCPM_Return::DASHBOARD );
				$form = (string) ob_get_clean();

				$table .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $school, self::institution_link( $pair['institution'] ) )
					. WPCPM_UI::cell( $semester, esc_html( self::whole_text( WPCPM_Cohort::label( $pair['cohort'] ) ) ) )
					. WPCPM_UI::cell( $ended, esc_html( $pair['window_end'] ) )
					. WPCPM_UI::cell( $progress, esc_html( number_format_i18n( (int) $pair['in_progress'] ) ) )
					. WPCPM_UI::cell( $action, $form )
					. WPCPM_UI::row_close();
			}

			echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; institution_link() escapes the name and the href, each value through esc_html() above, the form by its renderer.
		}

		echo WPCPM_UI::heading( __( 'Approved this semester', 'wpcredits-program-manager' ), 'h4', $titles[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		if ( empty( $approved ) ) {
			echo WPCPM_UI::list_empty( __( 'No report has been approved this semester yet.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().

			return;
		}

		$table = WPCPM_UI::table_open( array( $school, $semester, $when, $sized( $action, '14%' ) ), $family + array( 'labelledby' => $card . ' ' . $titles[2] ) );

		foreach ( $approved as $row ) {
			$table .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $school, self::institution_link( $row['institution'] ) )
				. WPCPM_UI::cell( $semester, esc_html( self::whole_text( WPCPM_Cohort::label( $row['cohort'] ) ) ) )
				. WPCPM_UI::cell( $when, esc_html( self::marked_final( (int) $row['approved_at'], (int) $row['approved_by'] ) ), (int) $row['approved_at'] > 0 ? '' : 'empty' )
				. WPCPM_UI::cell( $action, $open( __( 'View', 'wpcredits-program-manager' ), $row ) )
				. WPCPM_UI::row_close();
		}

		echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; institution_link() escapes the name and the href, each value through esc_html() above, the action through WPCPM_UI::button().
	}

	/**
	 * Requests from institutions: the open ones with the request class's two decisions, the closed
	 * folded, and last the way to the Institutions screen's queue tab.
	 *
	 * A school's request to correct its details is the one kind with a decision of its own
	 * (`render_details_request()`), drawn as the chosen mockup A; the others keep the request class's
	 * "Mark as handled" and "Decline".
	 *
	 * @param array $data `open`, `closed` (facts rows), `overdue`.
	 */
	public static function render_requests( array $data ) {
		$open   = isset( $data['open'] ) ? (array) $data['open'] : array();
		$closed = isset( $data['closed'] ) ? (array) $data['closed'] : array();
		$screen = self::institutions_screen( 'queue', __( 'Requests from institutions', 'wpcredits-program-manager' ) );

		$count = WPCPM_Test_Institutions::count_real_rows( $open, 'institution' );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'requests',
					'title'  => __( 'Requests from institutions', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'institution-requests', __( 'Requests from institutions', 'wpcredits-program-manager' ) ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $open ) ) {
			self::empty_line( __( 'No request is open.', 'wpcredits-program-manager' ) );
		}

		foreach ( $open as $facts ) {
			if ( WPCPM_Institution_Request::KIND_DETAILS === (string) $facts['kind'] ) {
				self::render_details_request( $facts );

				continue;
			}

			self::render_request_parts( $facts );
		}

		if ( ! empty( $closed ) ) {
			self::render_closed_requests_parts( $closed );
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * One open request on the shared parts (design 2.10, 4.1):
	 * the item header (the institution linked, its test mark, the red tag "Overdue" while it is overdue and no tag
	 * otherwise, then the request's kind and how long ago it was opened as facts), the school's note as today,
	 * then the request class's decisions, whose Decide area that class draws.
	 *
	 * The item keeps its article, and the overdue one its mark, as today. A school's details request is not drawn
	 * here: it keeps `render_details_request()`.
	 *
	 * @param array $facts One open request's facts.
	 */
	private static function render_request_parts( array $facts ) {
		$name   = self::institution_name( $facts['institution'] );
		$header = WPCPM_UI::item_header(
			array(
				'name'  => $name,
				'href'  => self::institution_href( $facts['institution'] ),
				'mark'  => WPCPM_Test_Institutions::mark( $name, WPCPM_Test_Institutions::MARK_DASHBOARD ),
				// Today's word for a request past its days, now the item's tag; a request in time has none.
				'tag'   => ! empty( $facts['overdue'] ) ? array(
					'words' => __( 'Overdue', 'wpcredits-program-manager' ),
					'tone'  => 'red',
				) : null,
				'facts' => array(
					(string) $facts['kind_label'],
					/* translators: %s: how long ago. */
					sprintf( __( 'Opened %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $facts['at'], time() ) ),
				),
			)
		);

		// An overdue item says so by its red tag and its word; the parts draw no side stripe (Task 17b, ruling 7).
		echo WPCPM_UI::item_open( 'wpcpm-request' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
		echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header(); mark() escapes its classes, title and word.

		if ( '' !== (string) $facts['note'] ) {
			echo WPCPM_UI::note( (string) $facts['note'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::note().
		}

		WPCPM_Institution_Request::render_decisions( (int) $facts['id'], WPCPM_Return::DASHBOARD );
		echo '</article>';
	}

	/**
	 * Recently closed on the shared parts (design 2.8; Task 17b, ruling 2; task V1, ruling 6): under today's fold and
	 * summary, one list row per request, the institution its name, linked and marked as today; what became of it, in
	 * today's word, as a neutral tag in the row's tag column; its kind and which date this is as facts, in today's words;
	 * and the note it was closed with (`WPCPM_Institution_Request::settle()` writes it to the request's note), when there
	 * is one, as the row's note under it. Who closed it is not drawn: it is not in a request's facts, and this release
	 * adds no read for it (the controller's ruling on what task V1 left open).
	 *
	 * @param array[] $closed The closed requests' facts, in closing order.
	 */
	private static function render_closed_requests_parts( array $closed ) {
		// The inner fold, today's summary its title, closed as today (Task 17b fix round).
		echo WPCPM_UI::inner_open( array( 'title' => __( 'Recently closed', 'wpcredits-program-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open().
		echo WPCPM_UI::list_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.

		foreach ( $closed as $facts ) {
			// Which date this is, said in the words, as render_requests() says it.
			$closed_at = isset( $facts['closed_at'] ) ? (int) $facts['closed_at'] : 0;
			$dated     = $closed_at > 0
				/* translators: %s: a date and time. */
				? sprintf( __( 'closed on %s', 'wpcredits-program-manager' ), self::when_whole( $closed_at ) )
				/* translators: %s: a date and time. */
				: sprintf( __( 'opened on %s', 'wpcredits-program-manager' ), self::when_whole( (int) $facts['at'] ) );
			$name = self::institution_name( $facts['institution'] );
			$line = WPCPM_UI::row(
				array(
					'name'  => $name,
					'href'  => self::institution_href( $facts['institution'] ),
					'mark'  => WPCPM_Test_Institutions::mark( $name, WPCPM_Test_Institutions::MARK_DASHBOARD ),
					'tag'   => array(
						'words' => self::request_outcome( $facts ),
						'tone'  => '',
					),
					'facts' => array( (string) $facts['kind_label'], $dated ),
					'note'  => isset( $facts['note'] ) ? (string) $facts['note'] : '',
				)
			);

			echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row(); mark() escapes its classes, title and word.
		}

		echo WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
		echo WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
	}

	/**
	 * What became of a closed request, in a word: a school's details request that was approved,
	 * Approved, any other that was closed as handled, Handled; Declined; and Withdrawn, which only a
	 * school's own request to correct its details can be.
	 *
	 * @param array $facts A closed request's facts.
	 * @return string
	 */
	private static function request_outcome( array $facts ) {
		switch ( (string) $facts['state'] ) {
			case WPCPM_Institution_Request::STATE_WITHDRAWN:
				return __( 'Withdrawn', 'wpcredits-program-manager' );

			case WPCPM_Institution_Request::STATE_DECLINED:
				return __( 'Declined', 'wpcredits-program-manager' );
		}

		return WPCPM_Institution_Request::KIND_DETAILS === (string) $facts['kind'] ? __( 'Approved', 'wpcredits-program-manager' ) : __( 'Handled', 'wpcredits-program-manager' );
	}

	/**
	 * One open request to correct a school's details, drawn as the chosen mockup A: one decision for the
	 * whole request (Institutions release 2).
	 *
	 * The school's name linked as every other name here is, who asked and on which day, how long it has
	 * waited as the other requests say it, the details it asks to change as a table of Detail, On record
	 * now and Asked for, then the request class's Decide area (`render_details_request_parts()`). **"On record now" is what the request's
	 * facts hold, which they read from the index when they were built (`WPCPM_Institution_Request::facts()`),
	 * so a detail the program team changed in Airtable since the school asked shows as it is.** Approve
	 * writes the asked values to the program's records and tells the school; Decline needs the note, which
	 * the school reads. The handler behind both is `WPCPM_Institution_Request::handle_details_decide()`,
	 * and the form posts the names it reads.
	 *
	 * The decisions are drawn for an account that manages the program and for nobody else, as the other
	 * requests' are.
	 *
	 * @param array $facts One open details request's facts, with its `changes` rows.
	 */
	private static function render_details_request( array $facts ) {
		$heading = 'wpcpm-request-' . (int) $facts['id'] . '-title';
		$asker   = '' !== (string) $facts['actor_name'] ? (string) $facts['actor_name'] : __( 'a member of the institution', 'wpcredits-program-manager' );
		$changes = isset( $facts['changes'] ) ? (array) $facts['changes'] : array();

		self::render_details_request_parts( $facts, $heading, $asker, $changes );
	}

	/**
	 * One open details request on the shared parts (design 2.6, 2.7, 2.10): the item header holding today's sentence with the school's name linked (and its test mark), its
	 * id kept for the table's label; the red tag "Overdue" while it is overdue; who asked and when, and how long it
	 * has waited, as facts; the changes in the parts' table, Detail as the row header and what is asked kept strong,
	 * labelled by the card's title and the request's, with no scroll box (the table stacks on a phone); then the
	 * request class's own Decide area, Approve at once or Decline with the note the school reads
	 * (`WPCPM_Institution_Request::render_details_decide()`), for an account that manages the program alone.
	 *
	 * @param array  $facts   One open details request's facts.
	 * @param string $heading The title's id, which the table is labelled by.
	 * @param string $asker   Who asked, in words.
	 * @param array  $changes The changes asked for.
	 */
	private static function render_details_request_parts( array $facts, $heading, $asker, array $changes ) {
		$id    = (int) $facts['id'];
		$title = sprintf(
			/* translators: %s: the institution's name, as a link to its page. */
			esc_html__( '%s asks to correct its details', 'wpcredits-program-manager' ),
			self::institution_link( $facts['institution'] )
		);

		$header = WPCPM_UI::item_header(
			array(
				// The sentence and the linked name are escaped above.
				'name_html' => $title,
				'id'        => $heading,
				'tag'       => ! empty( $facts['overdue'] ) ? array(
					'words' => __( 'Overdue', 'wpcredits-program-manager' ),
					'tone'  => 'red',
				) : null,
				'facts'     => array(
					sprintf(
						/* translators: 1: the name of the person who asked, or "a member of the institution" when their account is gone, 2: the day they asked. */
						__( 'Asked by %1$s on %2$s', 'wpcredits-program-manager' ),
						$asker,
						WPCPM_Dates::day( (int) $facts['at'] )
					),
					/* translators: %s: how long ago. */
					sprintf( __( 'Opened %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $facts['at'], time() ) ),
				),
			)
		);

		// An overdue item says so by its red tag and its word; the parts draw no side stripe (Task 17b, ruling 7).
		echo WPCPM_UI::item_open( 'wpcpm-request wpcpm-request--details' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
		echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header(); the sentence and institution_link() are escaped above.

		if ( empty( $changes ) ) {
			echo WPCPM_UI::list_empty( __( 'This request holds no change to approve.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().
		} else {
			// A detail's value may be a website or an email address: its two columns may break anywhere (Task 18f).
			$columns = array(
				array(
					'key'    => 'detail',
					'label'  => __( 'Detail', 'wpcredits-program-manager' ),
					'kind'   => 'text',
					// A detail's name is its row's name: the row header, not a key and value table's label.
					'header' => 'row',
				),
				array(
					'key'   => 'now',
					'label' => __( 'On record now', 'wpcredits-program-manager' ),
					'kind'  => 'token',
				),
				array(
					'key'   => 'asked',
					'label' => __( 'Asked for', 'wpcredits-program-manager' ),
					'kind'  => 'token',
				),
			);
			$table   = WPCPM_UI::table_open( $columns, array( 'labelledby' => self::title_id( 'requests' ) . ' ' . $heading ) );

			foreach ( $changes as $change ) {
				$table .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $columns[0], esc_html( (string) $change['label'] ) )
					. WPCPM_UI::cell( $columns[1], esc_html( (string) $change['now'] ) )
					. WPCPM_UI::cell( $columns[2], '<strong>' . esc_html( (string) $change['asked'] ) . '</strong>' )
					. WPCPM_UI::row_close();
			}

			echo $table . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() above.
		}

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			WPCPM_Institution_Request::render_details_decide( $id, WPCPM_Return::DASHBOARD );
		}

		echo '</article>';
	}

	/**
	 * The programs running: a tile per track and one for the finished, then the institutions.
	 *
	 * On the shared parts (`render_programs_parts()`) the tiles stay the card's own, which no part
	 * draws; the rest is on the parts.
	 *
	 * @param array $programs What `programs()` returned.
	 */
	public static function render_programs( array $programs ) {
		$rows   = isset( $programs['rows'] ) ? (array) $programs['rows'] : array();
		$tracks = isset( $programs['tracks'] ) ? (array) $programs['tracks'] : array();

		// A program is running while somebody is in progress on it: the badge and the phrase count
		// those, and the institutions with a student in progress, the rows the card lists.
		$institutions = WPCPM_Test_Institutions::count_real_rows( $rows, 'record' );
		$running      = 0;

		foreach ( $tracks as $track ) {
			if ( is_array( $track ) && ! empty( $track['in_progress'] ) ) {
				++$running;
			}
		}

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'   => 'programs',
					'title' => __( 'Programs running', 'wpcredits-program-manager' ),
					'count' => $running,
					'open'  => false,
					'state' => self::programs_phrase( $running, $institutions ),
				)
			)
		);

		echo '<ul class="wpcpm-programs__tiles">';

		foreach ( self::tracks() as $track => $name ) {
			$tile = isset( $programs['tracks'][ $track ] ) ? $programs['tracks'][ $track ] : array(
				'in_progress' => 0,
				'signed_up'   => 0,
			);

			printf(
				'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
				esc_html( $name ),
				esc_html( number_format_i18n( (int) $tile['in_progress'] ) ),
				esc_html( sprintf( /* translators: %s: a number of students. */ __( 'in progress, %s signed up this semester', 'wpcredits-program-manager' ), number_format_i18n( (int) $tile['signed_up'] ) ) )
			);
		}

		printf(
			'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
			esc_html__( 'Finished this semester', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( isset( $programs['finished'] ) ? (int) $programs['finished'] : 0 ) ),
			esc_html__( 'across every track', 'wpcredits-program-manager' )
		);
		echo '</ul>';

		self::render_programs_parts( $programs, $rows );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Programs running on the shared parts (design 2.7, 4.1), after today's tiles: the institutions as one table through
	 * the table part, named by the card's title as today's scroll box was (the part replaces the box), or today's empty
	 * sentence through `empty_line()`. The institution is each row's header, linked as today; "In progress" is a count,
	 * left-aligned as today's, with the students by status, today's words, as the cell's second line; the mentors and the students waiting
	 * are numbers; "no end date" keeps its words, muted as an empty value; the agreement and the report in their owners'
	 * words. Under it the quiet and the read lines, today's words, as the parts' muted line (both are muted today). No
	 * form, no action.
	 *
	 * @param array $programs What `programs()` returned.
	 * @param array $rows     Its rows.
	 */
	private static function render_programs_parts( array $programs, array $rows ) {
		if ( empty( $rows ) ) {
			self::empty_line( __( 'No institution has a student in progress.', 'wpcredits-program-manager' ) );
		} else {
			$column = static function ( $key, $label, $kind, $header = false ) {
				return array(
					'key'    => $key,
					'label'  => $label,
					'kind'   => $kind,
					'header' => $header,
				);
			};
			$name   = $column( 'institution', __( 'Institution', 'wpcredits-program-manager' ), 'text', 'row' );
			// Left-aligned as today's cell was: the number in tabular figures, the students by status under it (task V1,
			// ruling 15); the other counts are right-aligned numbers.
			$doing  = $column( 'in_progress', __( 'In progress', 'wpcredits-program-manager' ), 'count' );
			$guides = $column( 'mentors', __( 'Mentors', 'wpcredits-program-manager' ), 'num' );
			$wait   = $column( 'waiting', __( 'Waiting for a mentor', 'wpcredits-program-manager' ), 'num' );
			// A range may break at its words, never inside a date: the dates are whole runs in a cell that wraps; the agreement
			// and the report are short words that never wrap (Task 18d).
			$ends   = $column( 'ends', __( 'End dates', 'wpcredits-program-manager' ), 'text' );
			$agreed = $column( 'agreement', __( 'Agreement', 'wpcredits-program-manager' ), 'short' );
			$report = $column( 'report', __( 'Report', 'wpcredits-program-manager' ), 'short' );

			// Where the card has room, the school's name keeps one line and the breakdown under In progress its width (task
			// V1, ruling 14): a wide table's columns are laid out by what they hold, and these two would give way first.
			$name['width']  = '18%';
			$doing['width'] = '22%';
			// Seven columns: the sheet stacks it while the dashboard's main area is too narrow for it, which for this one is
			// under 936px (Task 18d).
			$out = WPCPM_UI::table_open(
				array( $name, $doing, $guides, $wait, $ends, $agreed, $report ),
				array(
					'labelledby' => self::title_id( 'programs' ),
					'wide'       => 936,
				)
			);

			foreach ( $rows as $row ) {
				$breakdown = array();

				foreach ( $row['by_status'] as $label => $n ) {
					$breakdown[] = number_format_i18n( (int) $n ) . ' ' . $label;
				}

				$none  = '' === $row['earliest'];
				$dates = $none
					? esc_html__( 'no end date', 'wpcredits-program-manager' )
					: (
						$row['earliest'] === $row['latest']
							? WPCPM_UI::whole( (string) $row['earliest'] )
							: sprintf( /* translators: 1: earliest end date, 2: latest end date. */ esc_html__( '%1$s to %2$s', 'wpcredits-program-manager' ), WPCPM_UI::whole( (string) $row['earliest'] ), WPCPM_UI::whole( (string) $row['latest'] ) )
					);

				$out .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $name, self::institution_link( $row['record'], (string) $row['name'] ) )
					. WPCPM_UI::cell( $doing, esc_html( number_format_i18n( (int) $row['in_progress'] ) ) . ( empty( $breakdown ) ? '' : WPCPM_UI::cell_sub( implode( ', ', $breakdown ) ) ) )
					. WPCPM_UI::cell( $guides, esc_html( number_format_i18n( (int) $row['mentors'] ) ) )
					. WPCPM_UI::cell( $wait, esc_html( number_format_i18n( (int) $row['waiting'] ) ) )
					. WPCPM_UI::cell( $ends, $dates, $none ? 'empty' : '' )
					. WPCPM_UI::cell( $agreed, esc_html( WPCPM_Institution_Agreement::summary_label( (string) $row['agreement'] ) ) )
					. WPCPM_UI::cell( $report, esc_html( WPCPM_Semester_Report::state_label( (string) $row['report'] ) ) )
					. WPCPM_UI::row_close();
			}

			echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts; institution_link() escapes the name and the href; each value through esc_html() above.
		}

		if ( ! empty( $programs['quiet'] ) ) {
			echo WPCPM_UI::intro( sprintf( /* translators: %s: a number of institutions. */ _n( '%s more institution with no student in progress.', '%s more institutions with no student in progress.', (int) $programs['quiet'], 'wpcredits-program-manager' ), number_format_i18n( (int) $programs['quiet'] ) ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
		}

		$read = empty( $programs['read'] )
			? __( 'These students have not been read from the program records yet.', 'wpcredits-program-manager' )
			: sprintf( /* translators: 1: date and time, 2: how long ago. */ __( 'Read from the program records on %1$s (%2$s ago).', 'wpcredits-program-manager' ), self::when( (int) $programs['read'] ), human_time_diff( (int) $programs['read'], time() ) );

		echo WPCPM_UI::intro( $read, 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
	}

	/**
	 * The students waiting to graduate (1.123.93), a queue of the Overview's Waiting for you, after Requests from
	 * institutions: what an Administrator does about them in one muted line, then one table on the shared parts, oldest
	 * first (`graduation()`). The student is each row's header, linked to their Student Report Card when the account
	 * and the page exist; the institution linked and marked as on every card; the course they wait on, linked to its
	 * Learn course; the mentor, or "No mentor"; the hours logged, against the track's target when it has one; the end
	 * of the internship; and when the students sync first saw them pending, or "Before" the date of the first run that
	 * kept the stamps for a student already pending then. An empty value keeps its words, muted. No row has a button:
	 * nothing about a graduation is changed on this site (the status is the program's records', the quiz Learn's). The
	 * card ends with the way to the Students screen, filtered by the status.
	 *
	 * A test institution's student is listed, marked, and left out of the card's number and the tile's.
	 *
	 * @param array[] $rows What `graduation()` returned.
	 */
	public static function render_graduation( array $rows ) {
		$count  = self::graduation_real( $rows );
		$screen = array(
			'label'   => __( 'Open on the Students screen', 'wpcredits-program-manager' ),
			'href'    => admin_url( 'admin.php?page=wpcpm-students&wpcpm_student_status=' . rawurlencode( WPCPM_Program::STATUS_PENDING ) ),
			'context' => sprintf(
				/* translators: %s: the card's title. */
				__( 'for the %s card', 'wpcredits-program-manager' ),
				__( 'Students waiting to graduate', 'wpcredits-program-manager' )
			),
		);

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'graduation',
					'title'  => __( 'Students waiting to graduate', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'graduation', __( 'Students waiting to graduate', 'wpcredits-program-manager' ) ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No student is waiting to graduate.', 'wpcredits-program-manager' ) );
		} else {
			self::render_graduation_parts( $rows );
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The students waiting to graduate as one table on the shared parts (design 2.7, 2.11): five columns, the student the
	 * row's header with their institution under the name as the cell's second line (plain muted text with its Test mark:
	 * the row's one link is the student's), then Track, Mentor, Hours and Waiting since. The end of the internship
	 * still orders the rows (`graduation()`) and is on the Student Report Card; it has no column (fix round 3). As
	 * measured with five columns, the table's least width is about 560px with sixty rows (about 660px with a twenty-
	 * character name), against 782px of card at 880px of main area: it stacks under 880px, as the other wide tables do.
	 * Every name a person typed goes through `WPCPM_UI::breakable()`, so one long unbroken run breaks at its break
	 * points rather than widen the table past the card (fix round 2 of 1.123.93). "Before" may wrap before its date;
	 * every date stays whole.
	 *
	 * @param array[] $rows What `graduation()` returned, in its order.
	 */
	private static function render_graduation_parts( array $rows ) {
		$column  = static function ( $key, $label, $kind, $header = false ) {
			return array(
				'key'    => $key,
				'label'  => $label,
				'kind'   => $kind,
				'header' => $header,
			);
		};
		$student = $column( 'student', __( 'Student', 'wpcredits-program-manager' ), 'text', 'row' );
		$track   = $column( 'track', __( 'Track', 'wpcredits-program-manager' ), 'text' );
		$mentor  = $column( 'mentor', __( 'Mentor', 'wpcredits-program-manager' ), 'text' );
		$hours   = $column( 'hours', __( 'Hours', 'wpcredits-program-manager' ), 'short' );
		$since   = $column( 'since', __( 'Waiting since', 'wpcredits-program-manager' ), 'date' );
		$page    = (string) WPCPM_Students_Dashboard::page_url();
		$none    = __( 'Not recorded', 'wpcredits-program-manager' );
		$out     = WPCPM_UI::intro( __( 'These students have finished and wait for an Administrator: read their Student Report Card, ask the mentor if something is unclear, and approve the quiz on Learn so the certificate goes out. Then set the graduation date in Airtable: the student leaves this list at the first students sync after that date.', 'wpcredits-program-manager' ), 'muted' )
			. WPCPM_UI::table_open(
				array( $student, $track, $mentor, $hours, $since ),
				array(
					'labelledby' => self::title_id( 'graduation' ),
					'wide'       => true,
				)
			);

		foreach ( $rows as $row ) {
			$id     = (int) $row['user_id'];
			$name   = '' !== (string) $row['name'] ? (string) $row['name'] : (string) $row['record_id'];
			$link   = ( $id > 0 && '' !== $page ) ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( 'wpcpm_student_view', $id, $page ) ), WPCPM_UI::breakable( $name ) ) : WPCPM_UI::breakable( $name );
			$record = (string) $row['institution'];
			// A school the pipeline index does not name, or none, keeps the empty value's words, not its record ID; one the
			// test list remembers keeps its Test mark too, as it is left out of the count (fix round 4).
			if ( '' !== $record && ( ! isset( $row['known'] ) || ! empty( $row['known'] ) ) ) {
				$school = WPCPM_UI::cell_sub( '', self::graduation_school( $record ) );
			} elseif ( ! empty( $row['test'] ) ) {
				$school = WPCPM_UI::cell_sub( '', esc_html( $none ) . WPCPM_Test_Institutions::badge( WPCPM_Test_Institutions::MARK_DASHBOARD ) );
			} else {
				$school = WPCPM_UI::cell_sub( $none );
			}
			// A status the site owes the program's records (1.123.95): the student is listed, and the line says why it is not
			// recorded yet and who acts on it.
			if ( 'no-row' === $row['owed'] ) {
				$school .= WPCPM_UI::cell_sub( __( 'Finished Feedback form 3 on this site. The site finds no Students row for this student in the program\'s records, so the status cannot be set: check the student\'s row there, and the next students sync sets it.', 'wpcredits-program-manager' ) );
			} elseif ( 'other-email' === $row['owed'] ) {
				$school .= WPCPM_UI::cell_sub( __( 'Finished Feedback form 3 on this site. The Students row on their track holds an email address other than this student\'s, so the status was not set: correct the row in the program\'s records, and the next students sync sets it.', 'wpcredits-program-manager' ) );
			} elseif ( ! empty( $row['owed'] ) ) {
				$school .= WPCPM_UI::cell_sub( __( 'Finished Feedback form 3 on this site. The program\'s records do not say Pending graduation yet: each students sync tries again.', 'wpcredits-program-manager' ) );
			}
			// An owed student is still on their track: their Track and Hours are that track's, as a recorded student's are
			// the kept course's.
			$course = ! empty( $row['course'] ) ? (string) $row['course'] : WPCPM_Program::course_status( WPCPM_Program::STATUS_PENDING, $id );
			$url    = '' !== $course ? WPCPM_Program::course_url( $course ) : '';
			$named  = '' !== $course ? WPCPM_Program::label( $course ) : '';
			$logged = WPCPM_Program::hours_text( (string) $row['hours'], '' !== $course ? $course : WPCPM_Program::STATUS_PENDING );
			$who    = (string) $row['mentor_name'];
			$at     = (int) $row['at'];
			$day    = $at > 0 ? self::whole_text( self::site_day( $at ) ) : '';
			// "Before" may go to its own line; the date stays whole.
			/* translators: %s: the date of the first students sync that saw the student pending; they were pending before it. */
			$waited = '' === $day ? '' : ( ! empty( $row['before'] ) ? sprintf( __( 'Before %s', 'wpcredits-program-manager' ), $day ) : $day );

			$out .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $student, $link . $school )
				. ( '' !== $named
					? WPCPM_UI::cell( $track, '' !== $url ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), WPCPM_UI::breakable( $named ) ) : WPCPM_UI::breakable( $named ) )
					: WPCPM_UI::cell( $track, esc_html( $none ), 'empty' ) )
				// A mentor on the Students row whose name has not arrived is not "No mentor": the name is not recorded.
				. ( '' !== $who
					? WPCPM_UI::cell( $mentor, WPCPM_UI::breakable( $who ) )
					: WPCPM_UI::cell( $mentor, esc_html( ! empty( $row['has_mentor'] ) ? $none : __( 'No mentor', 'wpcredits-program-manager' ) ), 'empty' ) )
				. ( '' !== $logged
					? WPCPM_UI::cell( $hours, esc_html( $logged ) )
					: WPCPM_UI::cell( $hours, esc_html( $none ), 'empty' ) )
				. WPCPM_UI::cell( $since, esc_html( $waited ) )
				. WPCPM_UI::row_close();
		}

		echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts; each value through esc_html(), esc_url() or WPCPM_UI::breakable() above, the school in graduation_school().
	}

	/**
	 * A student's institution under their name on the Students waiting to graduate table: its name through
	 * `WPCPM_UI::breakable()`, as plain text (the row's one link is the student's), then its Test mark.
	 *
	 * @param string $record Institutions record ID.
	 * @return string Escaped markup.
	 */
	private static function graduation_school( $record ) {
		$plain = self::institution_name( $record );

		return WPCPM_UI::breakable( $plain ) . WPCPM_Test_Institutions::mark( $plain, WPCPM_Test_Institutions::MARK_DASHBOARD );
	}

	/**
	 * The students who have gone quiet, from the daily snapshot: the viewer's own students first, then
	 * each other program manager's by name, then the students with no program manager. Each row says
	 * how long, what was last seen and when, the mentor, whether a reminder went (or would go, in
	 * Preview) or why none will (a test institution, or in On a stretch already past the threshold
	 * when the switch went On), and the button "We've already talked", which takes the row off at once.
	 *
	 * Nothing is drawn while the switch is off. A test institution's student is listed with the test
	 * mark and left out of the card's number and the tile's.
	 *
	 * The card is one form, posting every press back to this card, and each row one button naming its
	 * student, that student's own nonce in the address the button posts to (Release B, the trial's F5):
	 * a form a row, each with its nonce field, its referer and its way back, made a card of 490 rows
	 * weigh nearly half a megabyte and repeated core's `id="_wpnonce"` on every row. After a press the
	 * card is drawn open, its body starting with the line that says it was noted.
	 *
	 * @param array $snapshot What `WPCPM_Student_Flags::snapshot()` returned: `at`, `rows`.
	 */
	public static function render_quiet_students( array $snapshot ) {
		if ( ! class_exists( 'WPCPM_Student_Flags' ) || WPCPM_Student_Flags::MODE_OFF === WPCPM_Student_Flags::mode() ) {
			return;
		}

		$rows   = isset( $snapshot['rows'] ) ? (array) $snapshot['rows'] : array();
		$at     = isset( $snapshot['at'] ) ? (int) $snapshot['at'] : 0;
		$real   = self::quiet_real( $snapshot );
		$days   = WPCPM_Student_Flags::threshold();
		$viewer = (int) get_current_user_id();
		$on     = WPCPM_Student_Flags::MODE_ON === WPCPM_Student_Flags::mode();
		$said   = WPCPM_Student_Flags::said();

		// The press this person just made here: the card opens, its body starting with the line.
		if ( null !== $said ) {
			WPCPM_Dashboard_Card::hold( 'wpcpm-quiet-students', 'success', WPCPM_Student_Flags::said_sentence( $said['name'], false ) );
		}

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'quiet-students',
					'title'  => __( 'Students gone quiet', 'wpcredits-program-manager' ),
					'help'   => WPCPM_Guide_Jobs::link( 'quiet-students', __( 'Students gone quiet', 'wpcredits-program-manager' ) ),
					'count'  => $real,
					'open'   => false,
					'state'  => self::quiet_phrase( $real, $days, $at ),
					'action' => null,
				)
			)
		);

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No student has been quiet that long.', 'wpcredits-program-manager' ) );
			WPCPM_Dashboard_Card::close();
			return;
		}

		self::render_quiet_students_parts( self::quiet_groups( $rows, $viewer ), $on );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Students gone quiet on the shared parts (design 2.7, 2.11, 2.1, 4.1): today's sentence as the parts' muted line, then today's one form around every press (its action and its
	 * way back to this card, `talked_card_form()`), and in it each group, in today's order, as a heading through the
	 * heading part with an id of its own (today's group row in the table) over its table through the table part,
	 * named by the card's title and that heading (the table part replaces today's scroll box). The student is each
	 * row's header, linked to the Student Report Card as today and a test one marked as today; the days quiet are a
	 * number; a student with no mentor keeps today's words, "No mentor", muted as an empty value. "We've already
	 * talked" is the quiet row action: a press of the one form posting the student as its value to the address that
	 * carries that student's nonce, as today (`WPCPM_Student_Flags::talked_url()`), naming the student for a screen
	 * reader as today. It records that somebody has been in touch and ends nothing, so it is not red.
	 *
	 * @param array[] $groups What `quiet_groups()` returned: `title` and `rows`, in their order.
	 * @param bool    $on     Whether the reminders' switch is On.
	 */
	private static function render_quiet_students_parts( array $groups, $on ) {
		$column  = static function ( $key, $label, $kind, $header = false ) {
			return array(
				'key'    => $key,
				'label'  => $label,
				'kind'   => $kind,
				'header' => $header,
			);
		};
		$student = $column( 'student', __( 'Student', 'wpcredits-program-manager' ), 'text', 'row' );
		$days    = $column( 'days', __( 'Days quiet', 'wpcredits-program-manager' ), 'num' );
		$seen    = $column( 'seen', __( 'Last seen', 'wpcredits-program-manager' ), 'text' );
		$mentor  = $column( 'mentor', __( 'Mentor', 'wpcredits-program-manager' ), 'text' );
		$remind  = $column( 'reminder', __( 'Reminder', 'wpcredits-program-manager' ), 'text' );
		$action  = $column( 'action', '', 'action' );
		$card    = self::title_id( 'quiet-students' );
		$page    = (string) WPCPM_Students_Dashboard::page_url();
		$out     = WPCPM_UI::intro( __( 'Students on a track who have not signed in, visited, had a call or group session, or saved or edited their report. Pressing We\'ve already talked records that somebody has been in touch.', 'wpcredits-program-manager' ), 'muted' );
		$n       = 0;

		// One form around every group, as today, so every press posts the card's action and its way back.
		$out .= WPCPM_Student_Flags::talked_card_form( 'quiet-students' );

		foreach ( $groups as $group ) {
			// Today's group row had no id; the heading takes the card's anchor, `-group-` and its place.
			$heading = self::SHELL['prefix'] . 'quiet-students-group-' . ( ++$n );
			$out    .= WPCPM_UI::heading( (string) $group['title'], 'h4', $heading )
				. WPCPM_UI::table_open(
					array( $student, $days, $seen, $mentor, $remind, $action ),
					array(
						'labelledby' => $card . ' ' . $heading,
						// The last group's table ends the card from inside the form, so it says so for the sheet.
						'end'        => count( $groups ) === $n,
						// About 755px as a table by the rules, more with a long name: it stacks under 880px (Task 18f,
						// fix round 3).
						'wide'       => true,
					)
				);

			foreach ( $group['rows'] as $row ) {
				$id    = (int) $row['user_id'];
				$name  = (string) $row['name'];
				$link  = '' === $page ? esc_html( $name ) : sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( 'wpcpm_student_view', $id, $page ) ), esc_html( $name ) );
				$user  = ! empty( $row['mentor_id'] ) ? get_userdata( (int) $row['mentor_id'] ) : false;
				$press = WPCPM_UI::button(
					array(
						'label'      => __( 'We\'ve already talked', 'wpcredits-program-manager' ),
						'kind'       => 'quiet',
						'name'       => 'student',
						'value'      => (string) $id,
						'formaction' => WPCPM_Student_Flags::talked_url( $id ),
						/* translators: %s: a student's name. */
						'context'    => '' !== trim( $name ) ? sprintf( __( 'for %s', 'wpcredits-program-manager' ), trim( $name ) ) : '',
						// Drawn on the Administrator Dashboard alone, which is never wp-admin.
						'side'       => 'front',
					)
				);

				$out .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $student, $link . ( ! empty( $row['test'] ) ? WPCPM_Test_Institutions::badge( WPCPM_Test_Institutions::MARK_DASHBOARD ) : '' ) )
					. WPCPM_UI::cell( $days, esc_html( (string) (int) $row['days'] ) )
					. WPCPM_UI::cell( $seen, esc_html( self::quiet_last( $row ) ) )
					// An empty value keeps its words, muted (design 2.7).
					. WPCPM_UI::cell( $mentor, esc_html( $user ? (string) $user->display_name : __( 'No mentor', 'wpcredits-program-manager' ) ), $user ? '' : 'empty' )
					. WPCPM_UI::cell( $remind, esc_html( self::quiet_reminder( $row, $on ) ) )
					. WPCPM_UI::cell( $action, $press )
					. WPCPM_UI::row_close();
			}

			$out .= WPCPM_UI::table_close();
		}

		echo $out . '</form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in talked_card_form(); each value through esc_html() or esc_url() above, the mark in badge().
	}

	/**
	 * The Students gone quiet card's groups: the viewer's own students, then each other Administrator's by name, then
	 * nobody's, each in the snapshot's order.
	 *
	 * @param array[] $rows   The snapshot's rows.
	 * @param int     $viewer The viewer's account.
	 * @return array[] Each `title` and `rows`, in their order.
	 */
	private static function quiet_groups( array $rows, $viewer ) {
		// Own students, then the other program managers by name, then nobody's.
		$groups = array();

		foreach ( $rows as $row ) {
			$manager = isset( $row['manager_id'] ) ? (int) $row['manager_id'] : 0;

			if ( $manager > 0 && $manager === (int) $viewer ) {
				$key   = '0';
				$title = __( 'Your students', 'wpcredits-program-manager' );
			} elseif ( $manager > 0 ) {
				$user  = get_userdata( $manager );
				$title = $user ? (string) $user->display_name : '#' . $manager;
				$key   = '1' . strtolower( $title ) . '|' . $manager;
			} else {
				$key   = '2';
				$title = __( 'No Administrator assigned', 'wpcredits-program-manager' );
			}

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'title' => $title,
					'rows'  => array(),
				);
			}

			$groups[ $key ]['rows'][] = $row;
		}

		ksort( $groups, SORT_STRING );

		return $groups;
	}

	/**
	 * A Students gone quiet row's Reminder cell. A row that will never be mailed says why, rather than leaving the
	 * cell blank. Preview does not read the switch's time, so only On says "quiet before the switch went On".
	 *
	 * @param array $row A snapshot row.
	 * @param bool  $on  Whether the reminders' switch is On.
	 * @return string
	 */
	private static function quiet_reminder( array $row, $on ) {
		if ( ! empty( $row['reminded_at'] ) ) {
			return sprintf( /* translators: %s: a date. */ __( 'Reminded %s, no reply', 'wpcredits-program-manager' ), self::whole_text( wp_date( get_option( 'date_format' ), (int) $row['reminded_at'] ) ) );
		}

		if ( ! empty( $row['test'] ) ) {
			return __( 'Not mailed: test institution', 'wpcredits-program-manager' );
		}

		if ( ! $on && ! empty( $row['would_remind'] ) ) {
			// A state on one line, as Programs running's (Task 18f, fix round 2).
			return self::whole_text( __( 'Would remind', 'wpcredits-program-manager' ) );
		}

		if ( $on && ! WPCPM_Student_Flags::reached_since_on( (int) $row['last_at'] ) ) {
			return __( 'Not mailed: quiet before the switch went On', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * The quiet students that count: real ones, a test institution's left out.
	 *
	 * @param array $snapshot The snapshot: `rows`.
	 * @return int
	 */
	private static function quiet_real( array $snapshot ) {
		$n = 0;

		foreach ( isset( $snapshot['rows'] ) ? (array) $snapshot['rows'] : array() as $row ) {
			if ( empty( $row['test'] ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * The phrase on the Students gone quiet card's row: how many, since how long, and when the list
	 * was made. Its first number is the badge's.
	 *
	 * @param int $n    Real quiet students.
	 * @param int $days The threshold.
	 * @param int $at   Unix time of the snapshot; 0 when none has been made.
	 * @return string
	 */
	private static function quiet_phrase( $n, $days, $at ) {
		if ( $at <= 0 ) {
			return __( 'Not checked yet, the first check runs overnight', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: 1: a number of students, 2: the days, 3: a date. */
			_n( '%1$s student quiet for %2$s days or more, as of %3$s', '%1$s students quiet for %2$s days or more, as of %3$s', $n, 'wpcredits-program-manager' ),
			number_format_i18n( $n ),
			number_format_i18n( $days ),
			// The card head's phrase: its date whole (Task 18f, fix round 2).
			self::whole_text( wp_date( get_option( 'date_format' ), $at ) )
		);
	}

	/**
	 * A row's Last seen cell: what was last seen and when. A student never seen counts from the track's
	 * start or from the account's, and the cell says which, "Track started" or "Account made" (Release
	 * B, the trial's F6: "Joined the program" named the account's date for a student whose track began
	 * before it). A row stored before the list said which reads "Counting from" the date.
	 *
	 * @param array $row A snapshot row: `last_kind`, `last_at`, and `start_from` for kind `start`.
	 * @return string
	 */
	private static function quiet_last( array $row ) {
		$kind = isset( $row['last_kind'] ) ? (string) $row['last_kind'] : '';
		// A table cell's date, kept whole (Task 18f).
		$date = self::whole_text( wp_date( get_option( 'date_format' ), isset( $row['last_at'] ) ? (int) $row['last_at'] : 0 ) );
		$from = isset( $row['start_from'] ) ? (string) $row['start_from'] : '';

		if ( 'start' === $kind && 'track' !== $from && 'account' !== $from ) {
			/* translators: %s: a date. */
			return sprintf( __( 'Counting from %s', 'wpcredits-program-manager' ), $date );
		}

		/* translators: 1: what was last seen, such as "Signed in", 2: the date. */
		return sprintf( __( '%1$s, %2$s', 'wpcredits-program-manager' ), self::quiet_kind( 'start' === $kind ? $from : $kind ), $date );
	}

	/**
	 * What a last-activity kind of `WPCPM_Student_Activity::last()` reads as, a kind `start` by where
	 * its clock started (`WPCPM_Student_Activity::clock_start_from()`).
	 *
	 * @param string $kind sign_in, visit, call, report, edited or talked; track or account for a start.
	 * @return string
	 */
	private static function quiet_kind( $kind ) {
		$words = array(
			'sign_in' => __( 'Signed in', 'wpcredits-program-manager' ),
			'visit'   => __( 'Visited the site', 'wpcredits-program-manager' ),
			'call'    => __( 'Mentor call', 'wpcredits-program-manager' ),
			'report'  => __( 'Saved the report', 'wpcredits-program-manager' ),
			'edited'  => __( 'Edited the report', 'wpcredits-program-manager' ),
			'talked'  => __( 'Talked with the program', 'wpcredits-program-manager' ),
			'track'   => __( 'Track started', 'wpcredits-program-manager' ),
			'account' => __( 'Account made', 'wpcredits-program-manager' ),
		);

		return isset( $words[ $kind ] ) ? $words[ $kind ] : __( 'Unknown', 'wpcredits-program-manager' );
	}

	/**
	 * How the mentors use the site: six tiles in the Programs running card's markup, so the two look
	 * alike with no rule of their own, the Administrators who mentor counted apart, and the way to
	 * the Mentors screen's Engagement tab, where every mentor is listed. Read-only; nothing when the
	 * summary is empty.
	 *
	 * It sits in the Reference group right after Programs running and is drawn folded, as that card
	 * is: the count beside its title is the active mentors, and the phrase after it says how many of
	 * them were seen in the last 30 days. Its home in wp-admin is the Engagement tab, so it links
	 * there as every card with a home in wp-admin links its tab: its row action while it is folded,
	 * and the line its body ends with, in the same words to the same address. The label names the
	 * tab, so it needs no hidden words after it. On the shared parts the line that counts the
	 * Administrators apart is the parts' muted line; the tiles stay the card's own.
	 *
	 * @param array $summary What `WPCPM_Mentor_Engagement::report()` returned as `summary`.
	 */
	public static function render_mentor_engagement( array $summary ) {
		if ( empty( $summary ) ) {
			return;
		}

		$active = (int) $summary['active'];
		$tab    = array(
			'label' => __( 'Open the Mentors screen\'s Engagement tab', 'wpcredits-program-manager' ),
			'href'  => admin_url( 'admin.php?page=wpcpm-mentors&tab=engagement' ),
		);

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'mentor-engagement',
					'title'  => __( 'Mentor engagement', 'wpcredits-program-manager' ),
					'count'  => $active,
					'open'   => false,
					'state'  => self::mentor_engagement_phrase( $active, (int) $summary['seen_30'] ),
					'action' => $tab,
				)
			)
		);

		echo '<ul class="wpcpm-programs__tiles">';

		foreach ( WPCPM_Mentor_Engagement::tiles( $summary ) as $tile ) {
			printf(
				'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span>%3$s</li>',
				esc_html( $tile['name'] ),
				esc_html( number_format_i18n( (int) $tile['n'] ) ),
				'' !== $tile['line'] ? '<span class="wpcpm-programs__l">' . esc_html( $tile['line'] ) . '</span>' : ''
			);
		}

		echo '</ul>';

		// The quiet line is the parts' muted line; the tiles stay the card's own, which no part draws.
		echo WPCPM_UI::intro( WPCPM_Mentor_Engagement::apart( $summary ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
		self::render_screen_line( $tab );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Syncs and health: read-only, every row linking out to the screen that owns it. The badge and
	 * the row count the syncs that need a look (`syncs_row()`), as the strip's syncs tile does; the
	 * accounts locked today, which the strip's Locked accounts tile counts and leads here, are
	 * listed in the body. No row action: each sync's own row links to its screen, and no one
	 * screen holds the three.
	 *
	 * @param array     $health What `health()` returned: `syncs`, `auto_sync` and the rest.
	 * @param WP_User[] $locked Accounts the roster's refusal ceiling locked today.
	 */
	public static function render_health( array $health, array $locked ) {
		$syncs = isset( $health['syncs'] ) ? (array) $health['syncs'] : array();
		$row   = self::health_row( $health );

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'   => 'health',
					'title' => __( 'Syncs and health', 'wpcredits-program-manager' ),
					'help'  => WPCPM_Guide_Jobs::link( 'syncs', __( 'Syncs and health', 'wpcredits-program-manager' ) ),
					'count' => $row['count'],
					'open'  => false,
					'state' => $row['state'],
				)
			)
		);

		self::render_health_parts( $syncs, $locked, $health );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Syncs and health on the shared parts (design 2.7, 2.2, 2.8, 2.11, 4.1): the syncs as one table through the table part, named by the card's title as today's scroll box was (the
	 * table part replaces the box), then the card's own facts (`render_health_lines()`: the storage, the email, the
	 * invitations), which print whether or not an account is locked, then "Locked accounts" through the heading part,
	 * as today with no id (the strip's tile leads to the card's own anchor), over today's sentence as the parts' muted
	 * intro and the accounts as a list of rows, one a row (Task 18d), or today's empty sentence as the parts' muted line. The sync is each row's header; its state is today's words, plain in the
	 * cell's own ink and no tag, since a tag is a short word that never wraps and "Running: <a step's name>" is not one
	 * (today's markup marks no state either; the error is in its own column, the card's phrase counts the rest); a run
	 * never made or none booked keeps today's words, muted as empty values; Open is the quiet row action to the sync's
	 * screen, naming its sync for a screen reader as the row prints it, nothing where it has none. With no
	 * sync at all no table is drawn, where today drew one with no row. Nothing on the card is a form.
	 *
	 * @param array[]   $syncs  `health()`'s `syncs`.
	 * @param WP_User[] $locked Accounts the roster's refusal ceiling locked today.
	 * @param array     $health What `health()` returned, for the card's own lines.
	 */
	private static function render_health_parts( array $syncs, array $locked, array $health ) {
		$column = static function ( $key, $label, $kind, $header = false ) {
			return array(
				'key'    => $key,
				'label'  => $label,
				'kind'   => $kind,
				'header' => $header,
			);
		};
		$sync   = $column( 'sync', __( 'Sync', 'wpcredits-program-manager' ), 'text', 'row' );
		$state  = $column( 'state', __( 'State', 'wpcredits-program-manager' ), 'text' );
		$last   = $column( 'last', __( 'Last run', 'wpcredits-program-manager' ), 'date' );
		$next   = $column( 'next', __( 'Next run', 'wpcredits-program-manager' ), 'date' );
		// A remote service's message may hold an address or a token: it may break anywhere (Task 18f).
		$error  = $column( 'error', __( 'Last error', 'wpcredits-program-manager' ), 'token' );
		$action = $column( 'action', '', 'action' );
		$out    = '';

		if ( ! empty( $syncs ) ) {
			// By the rules about 814px as a table (two dates with their times at about 214px each, a token at least 164px,
			// the sync, the state and Open): more than a main area of 800px holds, so it stacks under 880px (Task 18f,
			// fix round 3).
			$out = WPCPM_UI::table_open(
				array( $sync, $state, $last, $next, $error, $action ),
				array(
					'labelledby' => self::title_id( 'health' ),
					'wide'       => true,
				)
			);

			foreach ( $syncs as $row ) {
				$progress = isset( $row['progress'] ) && is_array( $row['progress'] ) ? $row['progress'] : array();
				$words    = ! empty( $progress['running'] )
					? sprintf( /* translators: %s: what the sync is doing. */ __( 'Running: %s', 'wpcredits-program-manager' ), isset( $progress['label'] ) ? (string) $progress['label'] : '' )
					: __( 'Idle', 'wpcredits-program-manager' );
				$screen   = isset( $row['screen'] ) ? (string) $row['screen'] : '';

				$out .= WPCPM_UI::row_open()
					. WPCPM_UI::cell( $sync, esc_html( $row['label'] ) )
					. WPCPM_UI::cell( $state, esc_html( $words ) )
					// A run never made, or none booked, keeps today's words, muted as an empty value (design 2.7).
					. WPCPM_UI::cell( $last, esc_html( empty( $row['last'] ) ? /* translators: A table cell on its own, not a word inside a sentence. */ __( 'Never', 'wpcredits-program-manager' ) : self::when_whole( (int) $row['last'] ) ), empty( $row['last'] ) ? 'empty' : '' )
					. WPCPM_UI::cell( $next, esc_html( empty( $row['next'] ) ? /* translators: A table cell on its own, not words inside a sentence. */ __( 'Not scheduled', 'wpcredits-program-manager' ) : self::when_whole( (int) $row['next'] ) ), empty( $row['next'] ) ? 'empty' : '' )
					. WPCPM_UI::cell( $error, esc_html( isset( $progress['error'] ) ? (string) $progress['error'] : '' ) )
					// No screen, no link: a row action with no address prints nothing.
					. WPCPM_UI::cell(
						$action,
						'' === $screen ? '' : WPCPM_UI::button(
							array(
								'label'   => __( 'Open', 'wpcredits-program-manager' ),
								'kind'    => 'quiet',
								'href'    => $screen,
								// Three rows say "Open": each names its sync, as its header prints it.
								'context' => (string) $row['label'],
								// Drawn on the Administrator Dashboard alone, which is never wp-admin.
								'side'    => 'front',
							)
						)
					)
					. WPCPM_UI::row_close();
			}

			$out .= WPCPM_UI::table_close();
		}

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts; each value through esc_html() above.

		// The card's own facts come right after the syncs, before the locked accounts' heading (Task 18d).
		self::render_health_lines( $health );

		echo WPCPM_UI::heading( __( 'Locked accounts', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		if ( empty( $locked ) ) {
			self::empty_line( __( 'No account is locked today.', 'wpcredits-program-manager' ) );

			return;
		}

		// Today's sentence leads as the muted intro; the accounts follow, one a row, each name as today wrote it.
		$list = WPCPM_UI::list_open( array( 'facts' => 'under' ) );

		foreach ( $locked as $user ) {
			if ( $user instanceof WP_User ) {
				$list .= WPCPM_UI::row( array( 'name' => WPCPM_Test_Institutions::label( $user->display_name . ' (' . $user->user_login . ')', WPCPM_Institution_Members::institution_of( (int) $user->ID ) ) ) );
			}
		}

		echo WPCPM_UI::intro( __( 'Each was refused more claims than the daily ceiling allows; the lock lifts by itself tomorrow.', 'wpcredits-program-manager' ), 'muted' ) . $list . WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro() and WPCPM_UI::row().
	}

	/**
	 * The Syncs and health card's last lines (`health_lines()`): one paragraph per sentence in the intro line's look,
	 * each sentence's link after its words.
	 *
	 * @param array $health What `health()` returned.
	 */
	private static function render_health_lines( array $health ) {
		$lines = self::health_lines( $health );

		// Each line is a sentence of its own (Task 17b, ruling 2): the intro line's look, with the line's link after its
		// words as today; no bullets.
		foreach ( $lines as $line ) {
			echo WPCPM_UI::intro( '', '', '', $line ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each line is escaped in health_lines(); printed as given by WPCPM_UI::intro().
		}
	}

	/**
	 * The Syncs and health card's last lines, each escaped markup: the private storage's probe, the latest email and
	 * the last day's failures, each with its link to the Emails tool's Log, and a run of invitations still going.
	 *
	 * @param array $health What `health()` returned.
	 * @return string[]
	 */
	private static function health_lines( array $health ) {
		$probe   = isset( $health['probe'] ) ? (array) $health['probe'] : array(
			'verdict' => 'unknown',
			'time'    => 0,
		);
		$mail    = isset( $health['mail'] ) ? (array) $health['mail'] : array();
		$invites = isset( $health['invites'] ) ? (array) $health['invites'] : array(
			'run'    => array(),
			'queued' => 0,
		);

		$lines   = array();
		$lines[] = sprintf(
			'%s',
			esc_html( sprintf( /* translators: 1: the verdict, 2: a date. */ __( 'Private storage: %1$s (probed %2$s).', 'wpcredits-program-manager' ), $probe['verdict'], empty( $probe['time'] ) ? __( 'never', 'wpcredits-program-manager' ) : self::when( (int) $probe['time'] ) ) )
		);
		// The latest email by name, and the last day's failures, each linked to the Emails tool's Log.
		// The address is masked: this is a page of the site, and the full one is the Log's, in wp-admin.
		// The sentence says "sent to" because some names hold a "to" of their own ("..., to Administrators"),
		// and a bare "to" after one read as "to Administrators to m***@...".
		$latest = isset( $mail['latest'] ) ? (array) $mail['latest'] : array();
		$failed = isset( $mail['failed'] ) ? (int) $mail['failed'] : 0;
		$ready  = ! isset( $mail['ready'] ) || $mail['ready'];
		$tool   = WPCPM_Tools::get( 'emails' );
		$log    = add_query_arg( 'tab', 'log', $tool ? $tool->admin_url() : admin_url( 'admin.php?page=wpcpm-tool-emails' ) );

		$lines[] = sprintf(
			'%1$s <a href="%2$s">%3$s</a>',
			esc_html(
				empty( $latest )
					? ( $ready ? __( 'No email has been sent yet.', 'wpcredits-program-manager' ) : __( 'The email log is not ready yet.', 'wpcredits-program-manager' ) )
					: sprintf(
						/* translators: 1: an email's name, 2: a masked address, 3: a date, 4: the email's status. */
						__( 'Last email: %1$s, sent to %2$s on %3$s. Status: %4$s.', 'wpcredits-program-manager' ),
						WPCPM_Mail_Catalog::label( isset( $latest['template'] ) ? $latest['template'] : '' ),
						WPCPM_Mail::mask_address( isset( $latest['to_email'] ) ? $latest['to_email'] : '' ),
						self::when( isset( $latest['sent_at'] ) ? (int) strtotime( $latest['sent_at'] . ' UTC' ) : 0 ),
						WPCPM_Mail_Log::status_label( isset( $latest['status'] ) ? $latest['status'] : '' )
					)
			),
			esc_url( $log ),
			esc_html__( 'Open the email log', 'wpcredits-program-manager' )
		);

		if ( $failed ) {
			$lines[] = sprintf(
				'%1$s <a href="%2$s">%3$s</a>',
				esc_html(
					sprintf(
						/* translators: %s: number of failures. */
						_n( '%s email failed in the last day.', '%s emails failed in the last day.', $failed, 'wpcredits-program-manager' ),
						number_format_i18n( $failed )
					)
				),
				esc_url( add_query_arg( 'status', WPCPM_Mail_Log::STATUS_FAILED, $log ) ),
				esc_html__( 'Show the failed emails', 'wpcredits-program-manager' )
			);
		}

		// Only while the run is still going: run() also answers the last run to finish, and its
		// 'finished' is a non-zero timestamp then - printing "N of M sent" for a run that is
		// over reads as a live count of something that stopped moving days ago.
		if ( ! empty( $invites['run'] ) && isset( $invites['run']['total'] ) && empty( $invites['run']['finished'] ) ) {
			$total = (int) $invites['run']['total'];
			$sent  = max( 0, $total - (int) $invites['queued'] );

			$lines[] = sprintf(
				'%s',
				esc_html( sprintf( /* translators: 1: messages sent, 2: messages in the run. */ __( 'Invitations: %1$s of %2$s sent.', 'wpcredits-program-manager' ), number_format_i18n( $sent ), number_format_i18n( $total ) ) )
			);
		}

		return $lines;
	}

	/*
	 * --------------------------------------------------------------------
	 * The small pieces
	 * --------------------------------------------------------------------
	 */

	/**
	 * The id a card's heading words carry, as the card shell prints them for every card on this
	 * page (the shell's `title_id`, which `SHELL` turns on, read by
	 * `WPCPM_Dashboard_Card::title_id()`): the anchor and `-title`, the id main's own cards give
	 * them. The words and not the whole heading, which holds the count too: named by the heading,
	 * the health card's table would read as "Syncs and health 0".
	 *
	 * @param string $id The card's anchor, one of WPCPM_Return::ANCHORS.
	 * @return string
	 */
	private static function title_id( $id ) {
		return WPCPM_Dashboard_Card::title_id( self::SHELL['prefix'] . $id );
	}

	/**
	 * An empty half under a heading reads as something that failed to load, so it says so: the parts' muted line, the
	 * one every converted card's empty part prints.
	 *
	 * @param string $text The sentence.
	 */
	private static function empty_line( $text ) {
		echo WPCPM_UI::list_empty( (string) $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().
	}

	/**
	 * The phrase on the row of a card waiting for a decision: how many things wait on it, the count
	 * beside its title, zero included.
	 *
	 * @param int    $n     The card's count, which chooses the singular or the plural. With `$shown`,
	 *                      the number those words stand for: past a count's ceiling, the ceiling
	 *                      (`WPCPM_Sponsors::queue_number()`).
	 * @param string $shown The words for `$n` when they are not the number as it stands: a sponsor
	 *                      posts or agreements count as `WPCPM_Sponsors::queue_count()` prints it.
	 * @return string
	 */
	private static function waiting( $n, $shown = '' ) {
		return sprintf(
			/* translators: %s: how many things on the card wait for an Administrator's decision, or past a sponsor queue's count's ceiling that ceiling with a plus sign, such as 200+. */
			_n( '%s waiting', '%s waiting', (int) $n, 'wpcredits-program-manager' ),
			'' !== (string) $shown ? (string) $shown : number_format_i18n( (int) $n )
		);
	}

	/**
	 * The phrase on the Events card's row when no event waits (1.123.93): that nothing waits, and how many events are
	 * coming up, or that none is. The card stays on the Overview then, for the events coming up and the plan. Short
	 * enough to stay one line under its title at 358px of main area, as the other heads' phrases do.
	 *
	 * @param int $upcoming How many approved events are still to take place.
	 * @return string
	 */
	private static function events_coming_up( $upcoming ) {
		$upcoming = max( 0, (int) $upcoming );

		if ( 0 === $upcoming ) {
			return __( 'Nothing waiting, none coming up', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: how many approved events are still to take place. */
			_n( 'Nothing waiting, %s coming up', 'Nothing waiting, %s coming up', $upcoming, 'wpcredits-program-manager' ),
			number_format_i18n( $upcoming )
		);
	}

	/**
	 * The phrase on Offers running low's row: how many live offers hold fewer codes than their
	 * sponsor asked to be warned at, the count beside its title.
	 *
	 * @param int $n The card's count.
	 * @return string
	 */
	private static function offers_low_phrase( $n ) {
		return sprintf(
			/* translators: %s: how many live offers hold fewer codes than their sponsor asked to be warned at. */
			_n( '%s offer under its threshold', '%s offers under their threshold', (int) $n, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $n )
		);
	}

	/**
	 * The phrase on Duplicated students' row: how many students the Student Duplicate Finder's last
	 * scan listed, as of the day of that scan. Before the first scan there is no day to count as of,
	 * and the row says nothing rather than a phrase with a hole in it.
	 *
	 * @param int $n    The card's count.
	 * @param int $read When the last scan was read, or 0 before the first.
	 * @return string
	 */
	private static function duplicates_phrase( $n, $read ) {
		if ( empty( $read ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: how many duplicated students the Student Duplicate Finder's last scan listed, 2: the day of that scan. */
			_n( '%1$s duplicated student as of %2$s', '%1$s duplicated students as of %2$s', (int) $n, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $n ),
			// The card head's phrase: its date whole (Task 18f, fix round 2).
			self::whole_text( self::site_day( (int) $read ) )
		);
	}

	/**
	 * The phrase on New interests' row: how many lines sponsors wrote on their dashboards in the
	 * last thirty days.
	 *
	 * @param int $n The card's count.
	 * @return string
	 */
	private static function interests_phrase( $n ) {
		return sprintf(
			/* translators: %s: how many lines sponsors wrote on their dashboards in the last 30 days. */
			_n( '%s new interest in the last 30 days', '%s new interests in the last 30 days', (int) $n, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $n )
		);
	}

	/**
	 * The phrase on Sponsor terms ending's row: how many Approved sponsors' terms end within the
	 * next sixty days.
	 *
	 * @param int $n The card's count.
	 * @return string
	 */
	private static function tiers_ending_phrase( $n ) {
		return sprintf(
			/* translators: %s: how many Approved sponsors' terms end within the next 60 days. */
			_n( '%s term ending within 60 days', '%s terms ending within 60 days', (int) $n, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $n )
		);
	}

	/**
	 * The phrase on the Sponsors card's row: how many sponsors are Approved, the count beside its
	 * title, and how many offers are live today.
	 *
	 * @param int $approved The Approved sponsors.
	 * @param int $live     The offers live today.
	 * @return string
	 */
	private static function sponsors_phrase( $approved, $live ) {
		return sprintf(
			/* translators: 1: how many sponsors are Approved in the program records, 2: how many offers are live today, as "3 live offers". */
			_n( '%1$s Approved sponsor, %2$s', '%1$s Approved sponsors, %2$s', (int) $approved, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $approved ),
			sprintf(
				/* translators: %s: how many offers are live today. */
				_n( '%s live offer', '%s live offers', (int) $live, 'wpcredits-program-manager' ),
				number_format_i18n( (int) $live )
			)
		);
	}

	/**
	 * The phrase on Programs running's row: how many programs have a student in progress, the
	 * count beside its title, and at how many institutions.
	 *
	 * @param int $running      The programs with a student in progress.
	 * @param int $institutions The institutions with a student in progress.
	 * @return string
	 */
	private static function programs_phrase( $running, $institutions ) {
		return sprintf(
			/* translators: 1: how many programs have a student in progress, 2: at how many institutions, as "3 institutions". */
			_n( '%1$s program running, %2$s', '%1$s programs running, %2$s', (int) $running, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $running ),
			sprintf(
				/* translators: %s: how many institutions have a student in progress. */
				_n( '%s institution', '%s institutions', (int) $institutions, 'wpcredits-program-manager' ),
				number_format_i18n( (int) $institutions )
			)
		);
	}

	/**
	 * The phrase on Mentor engagement's row: how many mentors are active, the count beside its title,
	 * and how many of them were seen on the site in the last 30 days, the card's second tile.
	 *
	 * @param int $active The active mentors, Administrators who also mentor left out.
	 * @param int $seen   Of those, the mentors seen in the last 30 days.
	 * @return string
	 */
	private static function mentor_engagement_phrase( $active, $seen ) {
		return sprintf(
			/* translators: 1: how many mentors are active, Administrators who also mentor left out, 2: how many of them were seen on the site in the last 30 days. */
			_n( '%1$s active mentor, %2$s seen in the last 30 days', '%1$s active mentors, %2$s seen in the last 30 days', (int) $active, 'wpcredits-program-manager' ),
			number_format_i18n( (int) $active ),
			number_format_i18n( (int) $seen )
		);
	}

	/**
	 * A card's way to the Institutions screen, at the tab that holds the card's items: the queue
	 * tab for the institutions' applications, their signed Collaboration Agreements waiting for
	 * review and their requests, the Semester reports tab for their reports. The card's row
	 * action, and the line its body ends with (`render_screen_line()`).
	 *
	 * Every institution card's link reads "Open on the Institutions screen", whichever tab it opens,
	 * so each carries its card's title after those words, as "for the Institution applications
	 * card", for a screen reader alone (`context`), and a link read out of its row still says which
	 * card it comes from.
	 *
	 * @param string $tab   The Institutions screen's tab that holds the card's items.
	 * @param string $title The card's title, as its heading prints it.
	 * @return array The row action, in the shape the shell takes a card's action in.
	 */
	private static function institutions_screen( $tab, $title ) {
		return array(
			'label'   => __( 'Open on the Institutions screen', 'wpcredits-program-manager' ),
			'href'    => admin_url( 'admin.php?page=wpcpm-institutions&tab=' . $tab ),
			'context' => sprintf(
				/* translators: %s: the card's title. */
				__( 'for the %s card', 'wpcredits-program-manager' ),
				(string) $title
			),
		);
	}

	/**
	 * A card's way to the Sponsors screen, at the tab that holds the card's items and at the card
	 * on that tab that lists them, where the tab has one for them: the queue tab, a card each, for
	 * the sponsor applications, posts, events and Collaboration Agreements waiting there; the
	 * Sponsors tab for the sponsors with their tiers and terms; a tab each for their offers and
	 * codes, their interests and their reports. The card's row action, and the line its body ends
	 * with (`render_screen_line()`).
	 *
	 * Every sponsor card's link reads "Open on the Sponsors screen", whichever tab or card it opens,
	 * so each carries its card's title after those words, as "for the Sponsor applications card",
	 * for a screen reader alone (`context`), and a link read out of its row still says which card it
	 * comes from.
	 *
	 * @param string $tab    The Sponsors screen's tab that holds the card's items.
	 * @param string $anchor The id of the card on that tab that lists them, or '' for the tab's top.
	 * @param string $title  The card's title, as its heading prints it.
	 * @return array The row action, in the shape the shell takes a card's action in.
	 */
	private static function sponsors_screen( $tab, $anchor, $title ) {
		return array(
			'label'   => __( 'Open on the Sponsors screen', 'wpcredits-program-manager' ),
			'href'    => admin_url( 'admin.php?page=wpcpm-sponsors&tab=' . $tab ) . ( '' !== (string) $anchor ? '#' . $anchor : '' ),
			'context' => sprintf(
				/* translators: %s: the card's title. */
				__( 'for the %s card', 'wpcredits-program-manager' ),
				(string) $title
			),
		);
	}

	/**
	 * The line that ends a card's body with the way to the card's home in wp-admin, in its row
	 * action's words and to its address. Printed on every draw of every card whose home there is a
	 * tab of the Institutions, the Sponsors or the Mentors screen, an empty card's too: the row action
	 * shows only while the card is folded, and once the card is open its body is what an Administrator
	 * reads. The Duplicated students card, whose home is the Student Duplicate Finder, ends with this
	 * line to the finder. It comes after the line under a list this page cut (`render_more_line()`),
	 * which says how many more there are and links nowhere, on a card that prints both. The words that
	 * name the card by its title, where the action carries them, follow the link's words inside the
	 * link for a screen reader alone, as they do in the row action, with the space before them inside
	 * the hidden span; Mentor engagement's link names its tab and carries none.
	 *
	 * @param array $action The card's row action: `label`, `href` and, optionally, `context`, the
	 *                      words that name the card by its title.
	 */
	private static function render_screen_line( array $action ) {
		$context = isset( $action['context'] ) ? trim( (string) $action['context'] ) : '';

		// The line is the card fold's foot, after a hairline (parts.css), drawn by the fold part, as the cohort timeline's
		// card draws its own; its link and words are today's.
		echo WPCPM_UI::fold_foot( (string) $action['href'], (string) $action['label'], $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::fold_foot().
	}

	/**
	 * The Syncs and health card's row, as `health()` read the syncs and the setting: the badge's
	 * number and the phrase, which the strip's syncs tile counts too. Automatic sync is on when
	 * `health()` did not say, as the setting is by default.
	 *
	 * @param array $health What `health()` returned.
	 * @return array{count: int, state: string} As `syncs_row()` answers.
	 */
	private static function health_row( array $health ) {
		return self::syncs_row(
			isset( $health['syncs'] ) ? (array) $health['syncs'] : array(),
			! isset( $health['auto_sync'] ) || ! empty( $health['auto_sync'] )
		);
	}

	/**
	 * The Syncs and health card's badge and phrase, from one count, so the two cannot say two
	 * numbers: the syncs that need a look. While Automatic sync is on, an audience's sync needs a
	 * look when its last run left an error or no run of it finished within `SYNC_WINDOW`, never run
	 * included, and the row says every audience's sync ran in the last six hours when nothing needs a
	 * look. While it is off, the runs are an Administrator's own and an old one is the program's
	 * choice, so only an error needs a look, under the words that say the switch is off. Nothing, and
	 * none, with no sync to speak of, where "every sync" would be about none.
	 *
	 * The outcome figures' row (`outcome_figures`, 1.122.24) is a read made once a week, on Mondays,
	 * so the six hours are no measure of it, and its line under Last error is often a note and not an
	 * error (waiting for the dashboard's build, not connected, a figure over too few answers). It is
	 * judged by `figures_look()`, the switch on or off, and with nothing else to look at the row says
	 * why it needs a look: a held read as held for a look, never as an error. Beside another sync that
	 * needs a look it is one more in the count the row says.
	 *
	 * The rule for an audience's sync is `sync_needs_look()`, which Claude access (1.123.30) asks for each
	 * sync too.
	 *
	 * @param array $syncs `health()`'s `syncs`, each with `last` and `progress`.
	 * @param bool  $auto  Whether Automatic sync is on.
	 * @return array{count: int, state: string} The badge's number and the row's phrase.
	 */
	private static function syncs_row( array $syncs, $auto ) {
		$look    = 0;
		$figures = '';

		foreach ( $syncs as $key => $sync ) {
			if ( 'outcome_figures' === $key ) {
				$figures = self::figures_look();

				continue;
			}

			if ( self::sync_needs_look( is_array( $sync ) ? $sync : array(), (bool) $auto ) ) {
				++$look;
			}
		}

		$count = $look + ( '' === $figures ? 0 : 1 );

		if ( empty( $syncs ) ) {
			$state = '';
		} elseif ( 0 === $look && '' !== $figures ) {
			$state = self::figures_phrase( $figures, $auto );
		} elseif ( ! $auto ) {
			if ( 0 === $count ) {
				$state = __( 'Automatic sync is off', 'wpcredits-program-manager' );
			} elseif ( '' === $figures ) {
				$state = sprintf(
					/* translators: %s: how many syncs left an error on their last run. */
					_n( 'Automatic sync is off; %s sync reports an error', 'Automatic sync is off; %s syncs report an error', $count, 'wpcredits-program-manager' ),
					number_format_i18n( $count )
				);
			} else {
				$state = sprintf(
					/* translators: %s: how many syncs need a look while Automatic sync is off: an audience's sync whose last run left an error, and the outcome figures' weekly read when it was held or failed or has stopped. */
					_n( 'Automatic sync is off; %s sync needs a look', 'Automatic sync is off; %s syncs need a look', $count, 'wpcredits-program-manager' ),
					number_format_i18n( $count )
				);
			}
		} elseif ( 0 === $count ) {
			$state = __( 'Every audience\'s sync ran in the last six hours', 'wpcredits-program-manager' );
		} else {
			$state = sprintf(
				/* translators: %s: how many syncs need a look: an audience's sync that left an error or finished no run in the last six hours, and the outcome figures' weekly read when it was held or failed or has stopped. */
				_n( '%s sync needs a look', '%s syncs need a look', $count, 'wpcredits-program-manager' ),
				number_format_i18n( $count )
			);
		}

		return array(
			'count' => $count,
			'state' => $state,
		);
	}

	/**
	 * Whether one audience's sync needs a look: its last run left an error, or, while Automatic sync is on,
	 * no run of it finished within `SYNC_WINDOW`, a sync that never ran included.
	 *
	 * The rule of the Syncs and health card, for the card's count (`syncs_row()`) and for any other reader
	 * of `health()`'s syncs, such as the answer of Claude access to what waits for an Administrator, so a
	 * sync is counted as one that needs a look in exactly the same case wherever it is asked. While
	 * Automatic sync is off, the runs are an Administrator's own and an old one is the program's choice,
	 * so only an error needs a look. A sync that is running now is judged by its last finished run and its
	 * last error, as any other. It does not apply to the outcome figures' weekly read, whose rule is
	 * `figures_need_look()`.
	 *
	 * @param array $sync `health()`'s entry for one sync: `last` (the Unix time of its last finished run,
	 *                    0 for never) and `progress`, whose `error` is the words of its last error.
	 * @param bool  $auto Whether Automatic sync is on.
	 * @return bool
	 */
	public static function sync_needs_look( array $sync, bool $auto ): bool {
		$last  = isset( $sync['last'] ) ? (int) $sync['last'] : 0;
		$error = isset( $sync['progress']['error'] ) ? trim( (string) $sync['progress']['error'] ) : '';

		return '' !== $error || ( $auto && $last < time() - self::SYNC_WINDOW );
	}

	/**
	 * Whether the outcome figures' weekly read needs a look: `figures_look()` has a reason to give.
	 *
	 * The rule of that one row, the switch on or off, for the count of the Syncs and health card and for
	 * any other reader that says it per sync.
	 *
	 * @return bool
	 */
	public static function figures_need_look(): bool {
		return '' !== self::figures_look();
	}

	/**
	 * Whether the outcome figures' weekly read needs a look, and why: its last read was held, as the
	 * first read always is until a person stores it, or failed (`WPCPM_Outcome_Figures::status()`);
	 * or the read has stopped, the set stored older than the figures are shown for
	 * (`WPCPM_Outcome_Figures::max_age_days()`), so the homepage shows none, as when the weekly event
	 * is lost. A read that waits for the dashboard's build, one that stored, a site that is not
	 * connected and a figure over too few answers need none.
	 *
	 * @return string `held`, `failed` or `stopped`; '' when the read needs no look.
	 */
	private static function figures_look() {
		if ( ! class_exists( 'WPCPM_Outcome_Figures' ) ) {
			return '';
		}

		$run = WPCPM_Outcome_Figures::status();

		if ( isset( $run['result'] ) && in_array( $run['result'], array( 'held', 'failed' ), true ) ) {
			return (string) $run['result'];
		}

		$set = WPCPM_Outcome_Figures::stored();

		if ( array() !== $set && time() - (int) $set['read'] > WPCPM_Outcome_Figures::max_age_days() * DAY_IN_SECONDS ) {
			return 'stopped';
		}

		return '';
	}

	/**
	 * The row's phrase when the outcome figures are all that needs a look, in words of their own.
	 *
	 * @param string $why  What `figures_look()` answered.
	 * @param bool   $auto Whether Automatic sync is on.
	 * @return string
	 */
	private static function figures_phrase( $why, $auto ) {
		if ( 'held' === $why ) {
			return $auto
				? __( 'The outcome figures are held for a look', 'wpcredits-program-manager' )
				: __( 'Automatic sync is off; the outcome figures are held for a look', 'wpcredits-program-manager' );
		}

		if ( 'failed' === $why ) {
			return $auto
				? __( 'The outcome figures\' last read failed', 'wpcredits-program-manager' )
				: __( 'Automatic sync is off; the outcome figures\' last read failed', 'wpcredits-program-manager' );
		}

		return $auto
			? __( 'The outcome figures are no longer shown on the homepage', 'wpcredits-program-manager' )
			: __( 'Automatic sync is off; the outcome figures are no longer shown on the homepage', 'wpcredits-program-manager' );
	}

	/**
	 * A day in the site's date format, on the site's clock.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function site_day( $timestamp ) {
		return (string) wp_date( (string) get_option( 'date_format' ), (int) $timestamp );
	}

	/**
	 * A day a store keeps as `Y-m-d` on the site's clock (a post's date), in the site's date format; anything else as it
	 * came. Read at noon on that day, so no zone's offset moves it to the day before or after.
	 *
	 * @param string $day The day, as `Y-m-d`.
	 * @return string
	 */
	private static function site_date( $day ) {
		$day = (string) $day;
		$at  = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}\z/', $day ) ? date_create_immutable( $day . ' 12:00:00', wp_timezone() ) : false;

		return false === $at ? $day : self::site_day( $at->getTimestamp() );
	}

	/**
	 * A date kept whole on its line (Task 18f): its spaces made no-break spaces, so its day, month and year never part.
	 * For a date printed as text in a table cell or a list row's facts on the shared parts, and a semester's name in a
	 * table cell ("January to June 2026", fix round 1).
	 *
	 * @param string $date The date as words.
	 * @return string The same words, unescaped.
	 */
	private static function whole_text( $date ) {
		return str_replace( ' ', "\u{00A0}", (string) $date );
	}

	/**
	 * A date and time in the site's format, the date kept whole and the time free to go to the next line (Task 18f).
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function when_whole( $timestamp ) {
		return self::whole_text( self::site_day( (int) $timestamp ) ) . ' ' . (string) wp_date( (string) get_option( 'time_format' ), (int) $timestamp );
	}

	/**
	 * Words with every month and its year kept together ("June 2026", "September 2025"), for a report's period in a table
	 * cell, which may still break at its own "to" (Task 18f). A semester's name is whole instead (`whole_text()`).
	 *
	 * @param string $text The words.
	 * @return string
	 */
	private static function months_whole( $text ) {
		return (string) preg_replace( '/(\p{L}+) (\d{4})(?!\d)/u', "\$1\u{00A0}\$2", (string) $text );
	}

	/**
	 * Rows for a folded agreement list.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array[] `id`, `record`, `at`, `note`.
	 */
	private static function agreement_rows( array $ids ) {
		$rows = array();

		foreach ( $ids as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			// A revoked document's day is the day it was revoked, which the agreement class reads
			// from the decision's own record: revoking edits no post, so the last edit is the
			// upload's day (the trial of 1.123.89). Any other document keeps its last edit.
			$revoked = WPCPM_Institution_Agreement::revoked_facts( (int) $post->ID );

			$rows[] = array(
				'id'     => (int) $post->ID,
				'record' => (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
				'at'     => $revoked['at'] > 0 ? (int) $revoked['at'] : (int) get_post_modified_time( 'U', true, $post ),
				'note'   => trim( (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_NOTE, true ) ),
			);
		}

		return $rows;
	}

	/**
	 * The institution's name from the index, trimmed; the record ID when the index lost it.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function institution_name( $record ) {
		$row  = WPCPM_Institutions_Index::row( $record );
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : (string) $record;
	}

	/**
	 * The institution's name, linked to its dashboard through the switcher argument.
	 *
	 * Escaped here: every caller prints it into markup as is. A test institution's name carries its
	 * Test mark after the link (`WPCPM_Test_Institutions::mark()`, which escapes inside).
	 *
	 * @param string $record Institutions record ID.
	 * @param string $name   The name the caller already holds, which decides the mark without a read of
	 *                       the index; '' to judge by the record ID (one read), for a single item.
	 * @return string
	 */
	private static function institution_link( $record, $name = '' ) {
		$href = self::institution_href( $record );
		// The name a caller holds is used as given and decides the mark with no read; without one, the
		// index's name does (the record ID where the index lacks the row). Escaped for output only.
		$plain = '' !== (string) $name ? (string) $name : self::institution_name( $record );
		$name  = esc_html( $plain );
		$mark  = WPCPM_Test_Institutions::mark( $plain, WPCPM_Test_Institutions::MARK_DASHBOARD );

		if ( '' === $href ) {
			return $name . $mark;
		}

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $href ), $name ) . $mark;
	}

	/**
	 * Where an institution's name links: its dashboard through the switcher argument, or '' while the
	 * Institution Dashboard's page is missing or the record is empty. Not escaped: for `institution_link()`
	 * and for the parts' item header and list row, which escape it.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function institution_href( $record ) {
		$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? (string) WPCPM_Institutions_Dashboard::page_url() : '';

		if ( '' === $page || '' === (string) $record ) {
			return '';
		}

		return (string) add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $page );
	}

	/**
	 * The report editor on the Institution Dashboard, as that institution.
	 *
	 * @param string $record Institutions record ID.
	 * @param string $cohort Cohort key.
	 * @return string
	 */
	private static function report_link( $record, $cohort ) {
		$url = (string) WPCPM_Semester_Report_Screen::report_url( $cohort );

		return '' === $url ? '' : add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $url );
	}

	/**
	 * A date-only string's leading `Y-m-d`, or '' for anything else.
	 *
	 * @param mixed $value A roster row's date.
	 * @return string
	 */
	private static function day( $value ) {
		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', trim( (string) $value ), $found ) ? $found[1] : '';
	}

	/**
	 * A date and time in the site's format.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function when( $timestamp ) {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
	}

	/**
	 * "Received <when> (<ago>)".
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function received( $timestamp ) {
		/* translators: 1: date and time, 2: how long ago. */
		return sprintf( __( 'Received %1$s (%2$s ago)', 'wpcredits-program-manager' ), self::when( $timestamp ), human_time_diff( (int) $timestamp, time() ) );
	}

	/**
	 * "Uploaded <when> (<ago>)".
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function uploaded( $timestamp ) {
		/* translators: 1: date and time, 2: how long ago. */
		return sprintf( __( 'Uploaded %1$s (%2$s ago)', 'wpcredits-program-manager' ), self::when( $timestamp ), human_time_diff( (int) $timestamp, time() ) );
	}

	/**
	 * When a sponsor report was marked final, or a semester report approved, and by whom: "<when> by
	 * <name>", the moment alone for an account that is no longer on the site, and "Not recorded" for a
	 * report with no stamp.
	 *
	 * @param int $timestamp Unix time, or 0.
	 * @param int $user_id   The Administrator who marked or approved it, or 0.
	 * @return string
	 */
	private static function marked_final( $timestamp, $user_id ) {
		if ( $timestamp <= 0 ) {
			return __( 'Not recorded', 'wpcredits-program-manager' );
		}

		$by = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		// Printed in a table cell alone: the date kept whole (Task 18f).
		if ( ! $by instanceof WP_User ) {
			return self::when_whole( $timestamp );
		}

		/* translators: 1: a date and time, 2: an Administrator's name. */
		return sprintf( __( '%1$s by %2$s', 'wpcredits-program-manager' ), self::when_whole( $timestamp ), $by->display_name );
	}

	/**
	 * "<when>, by the site" or "<when>, by a manager".
	 *
	 * @param int    $timestamp Unix time.
	 * @param string $origin    `auto` or `manager`.
	 * @return string
	 */
	private static function drafted( $timestamp, $origin ) {
		return self::when_whole( $timestamp ) . ', ' . ( 'auto' === $origin ? __( 'by the site', 'wpcredits-program-manager' ) : __( 'by an Administrator', 'wpcredits-program-manager' ) );
	}

	/**
	 * "today", "tomorrow" or "in N days": the words beside the date itself in the Sponsor terms
	 * ending card's Ends on column, read faster than the date alone.
	 *
	 * @param int $days How many days are left on the term. render_tiers_ending() only ever
	 *                   passes what tiers_ending() already bounded to 0 through 60.
	 * @return string
	 */
	private static function term_ends_in( $days ) {
		if ( 0 === $days ) {
			return __( 'today', 'wpcredits-program-manager' );
		}

		if ( 1 === $days ) {
			return __( 'tomorrow', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %d: how many days are left on the sponsor's term. */
			_n( 'in %d day', 'in %d days', $days, 'wpcredits-program-manager' ),
			$days
		);
	}
}
