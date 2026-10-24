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
 * compute differently. `collect()` reads everything once, because the attention strip and
 * the cards draw the same arrays.
 *
 * A card's row says what the card holds, read from those arrays, so a folded card still tells a
 * manager what is in it, and a card whose work has one home in wp-admin links to it after its fold:
 * to the tab of the Institutions or the Sponsors screen that holds the card's items, and to the
 * card's own place on that tab where it has one. That link shows only while the card is folded, so
 * the card's body ends with the same link as a line, on every draw, an empty card's too, and the way
 * to its tab is in the same place however the card was drawn or opened. Both links carry the card's
 * title after their words for a screen reader alone, so links that read alike on the page still
 * say which card each comes from. The Duplicated students
 * card's home is the Student Duplicate Finder: its row links there in the finder's own words, and
 * its body ends with its own button to the finder instead of that line.
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
		// The heading's words carry an id, `title_id()`, which names a table's scroll box (1.122.35).
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

		$awaiting = array();
		$overdue  = 0;

		foreach ( WPCPM_Institution_Agreement::awaiting_review( 200 ) as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$at   = (int) get_post_time( 'U', true, $post );
			$late = ( $now - $at ) > $review;

			if ( $late ) {
				++$overdue;
			}

			$awaiting[] = array(
				'id'      => (int) $post->ID,
				'record'  => (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
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

			if ( ! empty( $facts['overdue'] ) ) {
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
				'open_total'   => count( WPCPM_Institution_Application::application_ids( $open_states ) ),
				'closed'       => $closed,
				'closed_total' => count( WPCPM_Institution_Application::application_ids( $closed_states ) ),
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
			'health'               => self::health(),
		);
	}

	/**
	 * The attention strip's tiles, from the arrays the cards draw: one for each count that needs
	 * a program manager today, in the order of the cards they link to, so the strip reads the
	 * page from the top. Every card waiting for a decision has a tile, and three of the cards to
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
		$review   = count( $data['agreements']['awaiting'] );
		$overdue  = (int) $data['agreements']['overdue'];
		$drafts   = count( $data['reports']['queue'] );
		$due      = count( $data['reports']['due'] );
		$requests = count( $data['requests']['open'] );
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
				'n'     => isset( $data['applications']['open_total'] ) ? (int) $data['applications']['open_total'] : count( $data['applications']['open'] ),
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
				'n'     => count( $data['locked'] ),
				'card'  => 'health',
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

		foreach ( $review as $row ) {
			self::render_sponsor_agreement_item( $row, __( 'Waiting for review', 'wpcredits-program-manager' ) );
		}

		self::render_sponsor_more_line( $total, count( $review ) );

		if ( ! empty( $revoked ) ) {
			printf( '<h4 class="wpcpm-administrator__subheading">%s</h4>', esc_html__( 'Out of force', 'wpcredits-program-manager' ) );

			foreach ( $revoked as $row ) {
				self::render_sponsor_agreement_item( $row, __( 'Revoked', 'wpcredits-program-manager' ) );
			}
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * One agreement row: the company, the kind, the facts and the decision.
	 *
	 * @param array  $row  A `review_facts()` row.
	 * @param string $kind The state word shown beside the company.
	 */
	private static function render_sponsor_agreement_item( array $row, $kind ) {
		printf( '<article class="wpcpm-administrator__item wpcpm-sponsor-agreement" id="wpcpm-sponsor-agreement-%d">', (int) $row['post_id'] );
		printf(
			'<h4 class="wpcpm-administrator__item-title">%1$s <span class="wpcpm-administrator__kind">%2$s</span></h4>',
			esc_html( (string) $row['sponsor_name'] ),
			esc_html( $kind )
		);
		echo '<p class="wpcpm-administrator__facts">';

		if ( '' !== (string) $row['uploaded_by'] ) {
			/* translators: 1: the account's name, 2: a date. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'Uploaded by %1$s on %2$s', 'wpcredits-program-manager' ), (string) $row['uploaded_by'], (string) $row['uploaded_at'] ) ) );
		} elseif ( '' !== (string) $row['uploaded_at'] ) {
			/* translators: %s: a date. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'Recorded on %s', 'wpcredits-program-manager' ), (string) $row['uploaded_at'] ) ) );
		}

		if ( (int) $row['size'] > 0 ) {
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( size_format( (int) $row['size'] ) ) );
		}

		if ( ! empty( $row['flags'] ) ) {
			/* translators: %s: what the courtesy scan noticed, in words, each with the PDF's own name for it in brackets. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'The scan noticed: %s', 'wpcredits-program-manager' ), WPCPM_Pdf_Check::in_words( array_map( 'strval', (array) $row['flags'] ) ) ) ) );
		} elseif ( 'own' === (string) $row['kind'] ) {
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html__( 'The scan noticed none of the features it looks for', 'wpcredits-program-manager' ) );
		}

		/* translators: %d: how many accounts the company has on the site. */
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( _n( '%d account', '%d accounts', (int) $row['members'], 'wpcredits-program-manager' ), (int) $row['members'] ) ) );
		echo '</p>';

		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			WPCPM_Sponsor_Agreement::render_decision( (int) $row['post_id'], WPCPM_Return::DASHBOARD );
		}

		echo '</article>';
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
			printf( '<article class="wpcpm-administrator__item wpcpm-sponsor-post" id="wpcpm-sponsor-post-%d">', (int) $row['id'] );
			printf(
				'<h4 class="wpcpm-administrator__item-title"><a href="%1$s">%2$s</a> <span class="wpcpm-administrator__kind">%3$s</span></h4>',
				esc_url( (string) $row['preview'] ),
				esc_html( (string) $row['title'] ),
				esc_html( (string) $row['company'] )
			);
			echo '<p class="wpcpm-administrator__facts">';
			/* translators: %s: how long ago. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'Submitted %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['at'], time() ) ) ) );

			if ( '' !== (string) $row['author'] ) {
				/* translators: %s: the account's name. */
				printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'By %s', 'wpcredits-program-manager' ), (string) $row['author'] ) ) );
			}

			echo '</p>';

			if ( class_exists( 'WPCPM_Sponsor_Posts' ) ) {
				WPCPM_Sponsor_Posts::render_decision( (int) $row['id'], WPCPM_Return::DASHBOARD );
			}

			echo '</article>';
		}

		self::render_sponsor_more_line( $total, count( $rows ) );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
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
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);
		WPCPM_Sponsor_Event_Queue::render( $data );
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Sponsor applications: one compact item per open application, with the six decisions.
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
			printf( '<article class="wpcpm-administrator__item wpcpm-sponsor-application" id="wpcpm-sponsor-application-%d">', (int) $row['id'] );
			printf(
				'<h4 class="wpcpm-administrator__item-title"><a href="%1$s">%2$s</a> <span class="wpcpm-administrator__kind">%3$s</span></h4>',
				esc_url( admin_url( 'admin.php?page=wpcpm-sponsors&' . WPCPM_Sponsor_Application::QUERY_QUEUE . '=' . (int) $row['id'] ) ),
				esc_html( (string) $row['company'] ),
				esc_html( (string) $row['reference'] )
			);
			echo '<p class="wpcpm-administrator__facts">';
			/* translators: %s: how long ago. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'Applied %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['at'], time() ) ) ) );

			if ( '' !== (string) $row['website'] ) {
				printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( (string) $row['website'] ) );
			}

			/* translators: 1: contact person, 2: contact email address. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ), (string) $row['person'], (string) $row['email'] ) ) );
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( (string) $row['state_label'] ) );

			// A manager's card, so the answer is shown whatever the switch says. Branched on the
			// key, never on the label a translation may reword: not sure yet is an answer and no
			// tier, so it reads as a sentence of its own. Facts shaped before the question existed
			// carry no key and show nothing; facts that carry one were built through the tier
			// ladder, which is loaded wherever they are.
			$requested = isset( $row['tier_requested'] ) ? (string) $row['tier_requested'] : '';

			if ( '' !== $requested ) {
				if ( WPCPM_Sponsor_Tiers::UNSURE === $requested ) {
					printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html__( 'Tier: not sure yet', 'wpcredits-program-manager' ) );
				} elseif ( ! empty( $row['tier_requested_label'] ) ) {
					/* translators: %s: the tier the applicant is considering. */
					printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'Considering: %s', 'wpcredits-program-manager' ), (string) $row['tier_requested_label'] ) ) );
				}
			}

			echo '</p>';

			if ( ! empty( $row['held'] ) ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'Held by the anti-spam checks. Open it on the Sponsors screen to read which.', 'wpcredits-program-manager' ) );
			}

			if ( ! empty( $row['duplicate'] ) ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'Another open application names this company or this address. Nothing is ever merged.', 'wpcredits-program-manager' ) );
			}

			if ( ! empty( $row['in_base'] ) ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'The site already holds a sponsor with this name or website. Approving creates a second record.', 'wpcredits-program-manager' ) );
			}

			// The one note that says a decision below it will be refused, so it is worth the
			// line on a card that is otherwise only facts.
			if ( ! empty( $row['half_done'] ) ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'The approval of this application is half done: a sponsor record already exists for it. Press Approve again to finish. Reject, Reject as spam and Put back in the queue are refused until it is.', 'wpcredits-program-manager' ) );
			}

			// The answers, the logo files and the base matches, folded (S6): the card stays a
			// queue, and a manager who wants the whole application has it without leaving. One
			// guard rather than two, the fold and the decision are the same availability.
			if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
				$post = get_post( (int) $row['id'] );

				if ( $post instanceof WP_Post ) {
					printf( '<details class="wpcpm-administrator__details"><summary>%s</summary>', esc_html__( 'Read the application', 'wpcredits-program-manager' ) );
					WPCPM_Sponsor_Application::render_details( $post );
					echo '</details>';
				}

				WPCPM_Sponsor_Application::render_decision( (int) $row['id'], WPCPM_Return::DASHBOARD );
			}

			echo '</article>';
		}

		if ( $total > count( $rows ) ) {
			self::render_more_line( $total - count( $rows ) );
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
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

		self::scroll_open( array( self::title_id( 'offers-low' ) ) );
		echo '<table class="wpcpm-admin-table wpcpm-offers-low"><thead><tr>';

		foreach ( array( __( 'Offer', 'wpcredits-program-manager' ), __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Codes left', 'wpcredits-program-manager' ), __( 'Warns at', 'wpcredits-program-manager' ) ) as $head ) {
			printf( '<th scope="col">%s</th>', esc_html( $head ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$url = (string) $row['url'];

			printf(
				'<tr><th scope="row">%1$s</th><td>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				// No Sponsor Dashboard page, no link: the title alone, not an anchor to nowhere.
				'' !== $url
					? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( (string) $row['title'] ) )
					: esc_html( (string) $row['title'] ),
				esc_html( (string) $row['sponsor_name'] ),
				esc_html( number_format_i18n( (int) $row['available'] ) ),
				esc_html( number_format_i18n( (int) $row['low'] ) )
			);
		}

		echo '</tbody></table></div>';
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
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
					'count'  => $count,
					'open'   => false,
					'state'  => self::duplicates_phrase( $count, (int) $facts['read'] ),
					'action' => $action,
				)
			)
		);

		if ( empty( $facts['read'] ) ) {
			self::empty_line( __( 'The Student Duplicate Finder has not scanned Airtable yet.', 'wpcredits-program-manager' ) );
		} elseif ( 0 === (int) $facts['addresses'] ) {
			/* translators: %s: date and time of the scan. */
			self::empty_line( sprintf( __( 'No duplicated students in Airtable, as of %s.', 'wpcredits-program-manager' ), self::when( (int) $facts['read'] ) ) );
		} else {
			printf(
				'<p class="wpcpm-administrator__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: duplicated students, 2: how many of them are ready to delete, 3: date and time of the scan. */
						_n( '%1$s duplicated student in Airtable, %2$s ready to delete, as of %3$s.', '%1$s duplicated students in Airtable, %2$s ready to delete, as of %3$s.', (int) $facts['addresses'], 'wpcredits-program-manager' ),
						number_format_i18n( (int) $facts['addresses'] ),
						number_format_i18n( (int) $facts['ready'] ),
						self::when( (int) $facts['read'] )
					)
				)
			);
		}

		if ( '' !== (string) $facts['url'] ) {
			printf(
				'<div class="wpcpm-administrator__actions"><a class="wpcpm-button wpcpm-button--secondary" href="%1$s">%2$s</a></div>',
				esc_url( (string) $facts['url'] ),
				esc_html__( 'Open the Student Duplicate Finder', 'wpcredits-program-manager' )
			);
		}

		WPCPM_Dashboard_Card::close();
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

		foreach ( $rows as $row ) {
			$url = (string) $row['url'];

			echo '<article class="wpcpm-administrator__item wpcpm-interest">';
			printf(
				'<h4 class="wpcpm-administrator__item-title">%1$s <span class="wpcpm-administrator__kind">%2$s</span></h4>',
				// No Sponsor Dashboard page, no link: the sponsor's name alone, not an anchor to
				// nowhere.
				'' !== $url
					? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( (string) $row['sponsor_name'] ) )
					: esc_html( (string) $row['sponsor_name'] ),
				/* translators: %s: how long ago. */
				esc_html( sprintf( __( '%s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['time'], time() ) ) )
			);
			// A sponsor's own words, on a manager's page: escaped, never linked, never trimmed.
			printf( '<p class="wpcpm-administrator__facts"><span class="wpcpm-administrator__fact">%s</span></p>', esc_html( (string) $row['message'] ) );
			echo '</article>';
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Sponsors card: four figures on the tiles the programs card draws its tracks with, and,
	 * once the tier ladder has loaded, the Approved sponsors by their effective tier underneath;
	 * then the way to the Sponsors screen's Sponsors tab.
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
			printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'Approved sponsors by tier, as the tiers stand today.', 'wpcredits-program-manager' ) );
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

		self::scroll_open( array( self::title_id( 'tiers-ending' ) ) );
		echo '<table class="wpcpm-admin-table wpcpm-tiers-ending"><thead><tr>';

		foreach ( array( __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Tier', 'wpcredits-program-manager' ), __( 'Ends on', 'wpcredits-program-manager' ), __( 'Administrator', 'wpcredits-program-manager' ) ) as $head ) {
			printf( '<th scope="col">%s</th>', esc_html( $head ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			printf(
				'<tr><th scope="row"><a href="%1$s">%2$s</a></th><td>%3$s</td><td>%4$s <span class="wpcpm-administrator__fact">%5$s</span></td><td>%6$s</td></tr>',
				esc_url( (string) $row['url'] ),
				esc_html( (string) $row['name'] ),
				esc_html( (string) $row['tier'] ),
				esc_html( WPCPM_Sponsor_Tiers::format_day( (string) $row['until'] ) ),
				esc_html( self::term_ends_in( (int) $row['days'] ) ),
				esc_html( '' !== (string) $row['manager'] ? (string) $row['manager'] : __( 'Nobody yet', 'wpcredits-program-manager' ) )
			);
		}

		echo '</tbody></table></div>';
		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
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
		// Each table's box is named by the card's heading and the subheading above the table, as the
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

		printf( '<h4 class="wpcpm-administrator__subtitle" id="%1$s">%2$s</h4>', esc_attr( $drafts_title ), esc_html__( 'Drafts to finish', 'wpcredits-program-manager' ) );

		if ( empty( $queue ) ) {
			self::empty_line( __( 'No draft to finish.', 'wpcredits-program-manager' ) );
		} else {
			self::scroll_open( array( self::title_id( 'sponsor-reports' ), $drafts_title ) );
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Sponsor', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Report', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $queue as $row ) {
				printf( '<tr><th scope="row">%1$s</th><td>%2$s</td><td>', esc_html( (string) $row['name'] ), esc_html( (string) $row['title'] ) );
				self::render_sponsor_report_open( $row );
				echo '</td></tr>';
			}

			echo '</tbody></table></div>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle" id="%1$s">%2$s</h4>', esc_attr( $ended_title ), esc_html__( 'Terms ended with no report', 'wpcredits-program-manager' ) );

		if ( empty( $due ) ) {
			self::empty_line( __( 'No term is waiting for its report.', 'wpcredits-program-manager' ) );
		} else {
			self::scroll_open( array( self::title_id( 'sponsor-reports' ), $ended_title ) );
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Sponsor', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Term ended on', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $due as $row ) {
				printf(
					'<tr><th scope="row">%1$s</th><td>%2$s</td><td>',
					esc_html( (string) $row['name'] ),
					esc_html( WPCPM_Sponsor_Tiers::format_day( (string) $row['until'] ) )
				);
				WPCPM_Sponsor_Report_Screen::render_draft_form( (string) $row['sponsor'], (string) $row['until'], WPCPM_Return::DASHBOARD );
				echo '</td></tr>';
			}

			echo '</tbody></table></div>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle" id="%1$s">%2$s</h4>', esc_attr( $final_title ), esc_html__( 'Final this semester', 'wpcredits-program-manager' ) );

		if ( empty( $final ) ) {
			self::empty_line( __( 'None marked final this semester yet.', 'wpcredits-program-manager' ) );
		} else {
			self::scroll_open( array( self::title_id( 'sponsor-reports' ), $final_title ) );
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Sponsor', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Report', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Marked final', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $final as $row ) {
				printf(
					'<tr><th scope="row">%1$s</th><td>%2$s</td><td>%3$s</td><td>',
					esc_html( (string) $row['name'] ),
					esc_html( (string) $row['title'] ),
					esc_html( self::marked_final( (int) $row['final_at'], (int) $row['final_by'] ) )
				);
				self::render_sponsor_report_open( $row );
				echo '</td></tr>';
			}

			echo '</tbody></table></div>';
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Open, to a report's editor on the Sponsors screen, for a draft and a final report alike. Every
	 * row's button says Open, so each carries its report's title for a screen reader.
	 *
	 * @param array $row A `queue` or `final` row of sponsor_reports().
	 */
	private static function render_sponsor_report_open( array $row ) {
		printf(
			'<a class="wpcpm-button wpcpm-button--secondary" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
			esc_url( (string) $row['url'] ),
			esc_html__( 'Open', 'wpcredits-program-manager' ),
			esc_html( (string) $row['title'] )
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

		// No page, no link: add_query_arg() on '' would build a query on the current page, and a
		// link that reloads the Administrator Dashboard says nothing.
		$page = (string) WPCPM_Sponsors_Dashboard::page_url();

		if ( '' === $page ) {
			return '';
		}

		return add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $record, $page ) . '#wpcpm-sponsor-' . $card;
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

		foreach ( array_keys( self::tracks() ) as $track ) {
			$tracks[ $track ] = array(
				'in_progress' => 0,
				'signed_up'   => 0,
			);
		}

		foreach ( WPCPM_Institutions_Index::rows() as $record => $institution ) {
			$record   = (string) $record;
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

			if ( $this_read > 0 && ( 0 === $read || $this_read < $read ) ) {
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

				if ( isset( $tracks[ $track ] ) && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) === $semester ) {
					++$tracks[ $track ]['signed_up'];
				}

				if ( in_array( $status, $active, true ) ) {
					++$in_progress;

					$label               = WPCPM_Program::label( $status );
					$by_status[ $label ] = ( isset( $by_status[ $label ] ) ? $by_status[ $label ] : 0 ) + 1;

					if ( isset( $tracks[ $track ] ) ) {
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
				} elseif ( 'Graduate' === $status && '' !== $end && $end >= $window['from'] && $end <= $window['to'] ) {
					// Graduate only, not every `past_statuses` entry: WPCPM_Cohort::participation()'s
					// private bucket() buckets 'Graduate' as graduated and 'Dropped out' as withdrawn,
					// and this count has to agree with that rule rather than inventing its own - a
					// student who left is not "finished", because nothing about the program was.
					++$finished;
				}
			}

			if ( 0 === $in_progress ) {
				++$quiet;
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
	 * The attention strip: a tile for each entry of `counts()`, each a link to its card, which
	 * the dashboards' opener opens, or, for the one tile with no card here (`url`, the mentor
	 * applications'), to the screen it is decided on. `counts()` gives no tile at zero, so none is
	 * drawn muted.
	 * With no tile at all, one sentence says that nothing is waiting, in the same section, so
	 * the page keeps the strip's anchor and a screen reader the region's name. A tile prints the
	 * words it carries for its number (`shown`, a sponsor posts or agreements count as
	 * `WPCPM_Sponsors::queue_count()` prints it), or its number as it stands.
	 *
	 * @param array $counts What `counts()` returned.
	 */
	public static function render_strip( array $counts ) {
		printf( '<section class="wpcpm-administrator__card wpcpm-attention" id="wpcpm-attention" aria-label="%s">', esc_attr__( 'Needs attention', 'wpcredits-program-manager' ) );

		if ( array() === $counts ) {
			printf( '<p class="wpcpm-attention__none">%s</p></section>', esc_html__( 'Nothing is waiting for you today.', 'wpcredits-program-manager' ) );

			return;
		}

		echo '<ul class="wpcpm-attention__tiles">';

		foreach ( $counts as $tile ) {
			printf(
				'<li class="wpcpm-attention__tile"><a class="wpcpm-attention__link" href="%1$s"><span class="wpcpm-attention__n">%2$s</span><span class="wpcpm-attention__l">%3$s</span></a></li>',
				isset( $tile['url'] ) ? esc_url( (string) $tile['url'] ) : '#wpcpm-' . esc_attr( $tile['card'] ),
				esc_html( isset( $tile['shown'] ) ? (string) $tile['shown'] : number_format_i18n( (int) $tile['n'] ) ),
				esc_html( $tile['label'] )
			);
		}

		echo '</ul></section>';
	}

	/**
	 * Institution applications: the open ones with the six decisions, the closed ones folded, and
	 * last the way to the Institutions screen's queue tab, which lists the same open applications.
	 *
	 * @param array $data `open`, `open_total`, `closed`, `closed_total`.
	 */
	public static function render_applications( array $data ) {
		$open         = isset( $data['open'] ) ? (array) $data['open'] : array();
		$open_total   = isset( $data['open_total'] ) ? (int) $data['open_total'] : count( $open );
		$closed       = isset( $data['closed'] ) ? (array) $data['closed'] : array();
		$closed_total = isset( $data['closed_total'] ) ? (int) $data['closed_total'] : count( $closed );
		$module       = class_exists( 'WPCPM_Modules' ) ? WPCPM_Modules::get( 'institutions' ) : null;
		$screen       = self::institutions_screen( 'queue', __( 'Institution applications', 'wpcredits-program-manager' ) );

		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'applications',
					'title'  => __( 'Institution applications', 'wpcredits-program-manager' ),
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
		if ( $open_total > count( $open ) ) {
			self::render_more_line( $open_total - count( $open ) );
		}

		if ( ! empty( $closed ) ) {
			printf(
				'<details class="wpcpm-administrator__closed"><summary>%s</summary>',
				esc_html(
					sprintf(
						/* translators: %s: a number of applications. */
						_n( '%s rejected or spam application', '%s rejected or spam applications', count( $closed ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $closed ) )
					)
				)
			);

			foreach ( $closed as $post ) {
				if ( $post instanceof WP_Post ) {
					self::render_application( $post, $module );
				}
			}

			if ( $closed_total > count( $closed ) ) {
				self::render_more_line( $closed_total - count( $closed ), true );
			}

			echo '</details>';
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

		printf(
			'<p class="wpcpm-administrator__more">%s</p>',
			esc_html( sprintf( $line, '' !== (string) $shown ? (string) $shown : number_format_i18n( $n ) ) )
		);
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
	 * One application: its title, linking to it in the Institutions screen's queue as a sponsor
	 * application's links to its own, its facts with the state in the Institutions screen's words,
	 * a note when the anti-spam checks held it, its answers behind a disclosure, and the module's
	 * decisions.
	 *
	 * @param WP_Post     $post   The application.
	 * @param object|null $module The Institutions module, which owns the answers and the forms.
	 */
	private static function render_application( WP_Post $post, $module ) {
		$state   = WPCPM_Institutions::application_state( $post );
		$country = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY, true );
		$name    = trim( (string) $post->post_title );
		$at      = (int) get_post_time( 'U', true, $post );
		$manager = WPCPM_Countries::contact_of( $country );

		printf( '<article class="wpcpm-administrator__item wpcpm-application" id="wpcpm-application-%d">', (int) $post->ID );
		printf(
			'<h4 class="wpcpm-administrator__item-title"><a href="%1$s">%2$s</a> <code class="wpcpm-administrator__ref">%3$s</code></h4>',
			esc_url( WPCPM_Institution_Application::queue_url( (int) $post->ID ) ),
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( WPCPM_Institutions::application_reference( $post ) )
		);
		echo '<p class="wpcpm-administrator__facts">';
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( WPCPM_Institutions::application_state_label( $state ) ) );
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( WPCPM_Institutions::country_name( $country, (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ) ) ) );

		if ( '' !== $manager ) {
			printf(
				'<span class="wpcpm-administrator__fact">%s</span>',
				esc_html(
					sprintf(
						/* translators: %s: an Administrator's name or address. */
						__( 'Routed to %s', 'wpcredits-program-manager' ),
						$manager
					)
				)
			);
		}

		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( self::received( $at ) ) );
		echo '</p>';

		if ( WPCPM_Institution_Application::STATE_HELD === $state ) {
			printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'Held by the anti-spam checks. Open it on the Institutions screen to read which.', 'wpcredits-program-manager' ) );
		}

		printf( '<details class="wpcpm-administrator__answers"><summary>%s</summary>', esc_html__( 'Read the application', 'wpcredits-program-manager' ) );

		if ( is_object( $module ) && method_exists( $module, 'render_application_answers' ) ) {
			$module->render_application_answers( $post );
		}

		echo '</details>';

		if ( is_object( $module ) && method_exists( $module, 'render_application_actions' ) ) {
			echo '<div class="wpcpm-administrator__actions">';
			$module->render_application_actions( $post, $state, WPCPM_Return::DASHBOARD );
			echo '</div>';
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

		$count = count( $awaiting );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'agreements',
					'title'  => __( 'Collaboration Agreements', 'wpcredits-program-manager' ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);
		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Awaiting review', 'wpcredits-program-manager' ) );

		if ( empty( $awaiting ) ) {
			self::empty_line( __( 'No signed agreement is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $awaiting as $row ) {
			printf( '<article class="wpcpm-administrator__item wpcpm-agreement%s">', ! empty( $row['overdue'] ) ? ' wpcpm-administrator__item--overdue' : '' );
			printf( '<h4 class="wpcpm-administrator__item-title">%s</h4>', self::institution_link( $row['record'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
			echo '<p class="wpcpm-administrator__facts">';
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( self::uploaded( (int) $row['at'] ) ) );

			if ( ! empty( $row['overdue'] ) ) {
				printf( '<span class="wpcpm-administrator__fact wpcpm-administrator__fact--overdue">%s</span>', esc_html__( 'Overdue', 'wpcredits-program-manager' ) );
			}

			echo '</p>';
			WPCPM_Institution_Panel::render_review( (int) $row['id'], true, WPCPM_Return::DASHBOARD );
			echo '</article>';
		}

		self::render_agreement_list(
			$returned,
			/* translators: %s: a number of institutions. */
			__( '%s returned, waiting for a new upload', 'wpcredits-program-manager' ),
			/* translators: %s: a number of institutions. */
			__( '%s returned, waiting for new uploads', 'wpcredits-program-manager' ),
			false
		);
		self::render_agreement_list(
			$revoked,
			/* translators: %s: a number of institutions. */
			__( '%s revoked agreement', 'wpcredits-program-manager' ),
			/* translators: %s: a number of institutions. */
			__( '%s revoked agreements', 'wpcredits-program-manager' ),
			true
		);

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * A folded list of returned or revoked agreements; the revoked ones offer Reinstate.
	 *
	 * @param array  $rows      `id`, `record`, `at`, `note`.
	 * @param string $singular  The summary, singular, with %s for the count.
	 * @param string $plural    The summary, plural.
	 * @param bool   $reinstate Whether to draw the Reinstate form.
	 */
	private static function render_agreement_list( array $rows, $singular, $plural, $reinstate ) {
		if ( empty( $rows ) ) {
			return;
		}

		// The sentences are translated by the caller, so _n() would translate them twice;
		// the count picks the form here.
		printf( '<details class="wpcpm-administrator__closed"><summary>%s</summary>', esc_html( sprintf( 1 === count( $rows ) ? $singular : $plural, number_format_i18n( count( $rows ) ) ) ) );

		foreach ( $rows as $row ) {
			echo '<article class="wpcpm-administrator__item wpcpm-agreement">';
			printf( '<h4 class="wpcpm-administrator__item-title">%s</h4>', self::institution_link( $row['record'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
			printf( '<p class="wpcpm-administrator__facts"><span class="wpcpm-administrator__fact">%s</span></p>', esc_html( self::when( (int) $row['at'] ) ) );

			if ( '' !== $row['note'] ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $row['note'] ) );
			}

			if ( $reinstate ) {
				self::render_reinstate_form( (int) $row['id'] );
			}

			echo '</article>';
		}

		echo '</details>';
	}

	/**
	 * The Reinstate form, drawn here because nothing else draws one: the handler and its nonce
	 * exist in the agreement class, and the queue never offered the button. Drawn on this page
	 * alone, so it always carries the way back to this card.
	 *
	 * @param int $post_id The revoked document.
	 */
	private static function render_reinstate_form( $post_id ) {
		printf(
			'<form class="wpcpm-administrator__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Reinstating', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Institution_Agreement::ACTION_REINSTATE . '_' . (int) $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Agreement::ACTION_REINSTATE ) );
		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );
		WPCPM_Return::field( WPCPM_Return::DASHBOARD, 'agreements' );
		printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Reinstate', 'wpcredits-program-manager' ) );
		echo '</form>';
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

		$count = count( $queue ) + count( $due );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'reports',
					'title'  => __( 'Semester reports', 'wpcredits-program-manager' ),
					'count'  => $count,
					'open'   => $count > 0,
					'state'  => self::waiting( $count ),
					'action' => $screen,
				)
			)
		);

		printf( '<h4 class="wpcpm-administrator__subtitle" id="%1$s">%2$s</h4>', esc_attr( $review_title ), esc_html__( 'To review', 'wpcredits-program-manager' ) );

		if ( empty( $queue ) ) {
			self::empty_line( __( 'No draft is waiting for review.', 'wpcredits-program-manager' ) );
		} else {
			self::scroll_open( array( self::title_id( 'reports' ), $review_title ) );
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Semester', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Drafted', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Still in progress', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Age', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $queue as $row ) {
				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td class="wpcpm-admin-table__n">%4$s</td><td>%5$s</td><td><a class="wpcpm-button wpcpm-button--secondary" href="%6$s">%7$s</a></td></tr>',
					self::institution_link( $row['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( WPCPM_Cohort::label( $row['cohort'] ) ),
					esc_html( self::drafted( (int) $row['generated'], (string) $row['origin'] ) ),
					esc_html( number_format_i18n( (int) $row['in_progress'] ) ),
					esc_html( sprintf( /* translators: %s: a number of days. */ _n( '%s day', '%s days', (int) $row['age_days'], 'wpcredits-program-manager' ), number_format_i18n( (int) $row['age_days'] ) ) ),
					esc_url( self::report_link( $row['institution'], $row['cohort'] ) ),
					esc_html__( 'Review', 'wpcredits-program-manager' )
				);
			}

			echo '</tbody></table></div>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle" id="%1$s">%2$s</h4>', esc_attr( $due_title ), esc_html__( 'Due for drafting', 'wpcredits-program-manager' ) );

		if ( empty( $due ) ) {
			self::empty_line( __( 'Nothing is waiting: every finished semester has a report, or is not finished yet.', 'wpcredits-program-manager' ) );
		} else {
			self::scroll_open( array( self::title_id( 'reports' ), $due_title ) );
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Semester', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Window ended', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Still in progress', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $due as $pair ) {
				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td class="wpcpm-admin-table__n">%4$s</td><td>',
					self::institution_link( $pair['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( WPCPM_Cohort::label( $pair['cohort'] ) ),
					esc_html( $pair['window_end'] ),
					esc_html( number_format_i18n( (int) $pair['in_progress'] ) )
				);
				// The dashboard return: a refusal (the semester already has a report, or the
				// ceiling for today is spent) comes back here rather than to the wp-admin
				// default; a successful draft still opens the new draft in the editor either
				// way (final review, Important 2).
				WPCPM_Semester_Report_Screen::render_draft_form( $pair['institution'], $pair['cohort'], '', WPCPM_Return::DASHBOARD );
				echo '</td></tr>';
			}

			echo '</tbody></table></div>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Approved this semester', 'wpcredits-program-manager' ) );

		if ( empty( $approved ) ) {
			self::empty_line( __( 'No report has been approved this semester yet.', 'wpcredits-program-manager' ) );
		} else {
			echo '<ul class="wpcpm-administrator__list">';

			foreach ( $approved as $row ) {
				$by = ! empty( $row['approved_by'] ) ? get_user_by( 'id', (int) $row['approved_by'] ) : false;

				printf(
					'<li>%1$s, %2$s: %3$s <a href="%4$s">%5$s</a></li>',
					self::institution_link( $row['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( WPCPM_Cohort::label( $row['cohort'] ) ),
					esc_html(
						$by instanceof WP_User
							? sprintf( /* translators: 1: a date, 2: an Administrator's name. */ __( 'approved on %1$s by %2$s', 'wpcredits-program-manager' ), self::when( (int) $row['approved_at'] ), $by->display_name )
							: sprintf( /* translators: %s: a date. */ __( 'approved on %s', 'wpcredits-program-manager' ), self::when( (int) $row['approved_at'] ) )
					),
					esc_url( self::report_link( $row['institution'], $row['cohort'] ) ),
					esc_html__( 'View', 'wpcredits-program-manager' )
				);
			}

			echo '</ul>';
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Requests from institutions: the open ones with the request class's two decisions, the closed
	 * folded, and last the way to the Institutions screen's queue tab.
	 *
	 * @param array $data `open`, `closed` (facts rows), `overdue`.
	 */
	public static function render_requests( array $data ) {
		$open   = isset( $data['open'] ) ? (array) $data['open'] : array();
		$closed = isset( $data['closed'] ) ? (array) $data['closed'] : array();
		$screen = self::institutions_screen( 'queue', __( 'Requests from institutions', 'wpcredits-program-manager' ) );

		$count = count( $open );
		WPCPM_Dashboard_Card::open(
			array_merge(
				self::SHELL,
				array(
					'key'    => 'requests',
					'title'  => __( 'Requests from institutions', 'wpcredits-program-manager' ),
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
			printf( '<article class="wpcpm-administrator__item wpcpm-request%s">', ! empty( $facts['overdue'] ) ? ' wpcpm-administrator__item--overdue' : '' );
			printf( '<h4 class="wpcpm-administrator__item-title">%1$s <span class="wpcpm-administrator__kind">%2$s</span></h4>', self::institution_link( $facts['institution'] ), esc_html( $facts['kind_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
			echo '<p class="wpcpm-administrator__facts">';
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( /* translators: %s: how long ago. */ __( 'Opened %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $facts['at'], time() ) ) ) );

			if ( ! empty( $facts['overdue'] ) ) {
				printf( '<span class="wpcpm-administrator__fact wpcpm-administrator__fact--overdue">%s</span>', esc_html__( 'Overdue', 'wpcredits-program-manager' ) );
			}

			echo '</p>';

			if ( '' !== (string) $facts['note'] ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $facts['note'] ) );
			}

			WPCPM_Institution_Request::render_decisions( (int) $facts['id'], WPCPM_Return::DASHBOARD );
			echo '</article>';
		}

		if ( ! empty( $closed ) ) {
			printf( '<details class="wpcpm-administrator__closed"><summary>%s</summary><ul class="wpcpm-administrator__list">', esc_html__( 'Recently closed', 'wpcredits-program-manager' ) );

			foreach ( $closed as $facts ) {
				// Which date this is, said in the sentence. The card is "Recently closed" and
				// the list is in closing order, so a bare date read as the closing one while it
				// was the opening one, months earlier (deep check FADMN-3). A row closed before
				// 1.99.0 carries no closing stamp, and says so rather than guessing.
				$closed_at = isset( $facts['closed_at'] ) ? (int) $facts['closed_at'] : 0;
				$dated     = $closed_at > 0
					/* translators: %s: a date and time. */
					? sprintf( __( 'closed on %s', 'wpcredits-program-manager' ), self::when( $closed_at ) )
					/* translators: %s: a date and time. */
					: sprintf( __( 'opened on %s', 'wpcredits-program-manager' ), self::when( (int) $facts['at'] ) );

				printf(
					'<li>%1$s, %2$s: %3$s, %4$s</li>',
					self::institution_link( $facts['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( $facts['kind_label'] ),
					esc_html( WPCPM_Institution_Request::STATE_DONE === $facts['state'] ? __( 'Handled', 'wpcredits-program-manager' ) : __( 'Declined', 'wpcredits-program-manager' ) ),
					esc_html( $dated )
				);
			}

			echo '</ul></details>';
		}

		self::render_screen_line( $screen );
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The programs running: a tile per track and one for the finished, then the institutions.
	 *
	 * @param array $programs What `programs()` returned.
	 */
	public static function render_programs( array $programs ) {
		$rows   = isset( $programs['rows'] ) ? (array) $programs['rows'] : array();
		$tracks = isset( $programs['tracks'] ) ? (array) $programs['tracks'] : array();

		// A program is running while somebody is in progress on it: the badge and the phrase count
		// those, and the institutions with a student in progress, the rows the card lists.
		$institutions = count( $rows );
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

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No institution has a student in progress.', 'wpcredits-program-manager' ) );
		} else {
			self::scroll_open( array( self::title_id( 'programs' ) ) );
			echo '<table class="wpcpm-admin-table wpcpm-programs__table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'In progress', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Mentors', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Waiting for a mentor', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'End dates', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Agreement', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Report', 'wpcredits-program-manager' ) );
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$breakdown = array();

				foreach ( $row['by_status'] as $label => $n ) {
					$breakdown[] = number_format_i18n( (int) $n ) . ' ' . $label;
				}

				printf(
					'<tr><td>%1$s</td><td class="wpcpm-admin-table__n">%2$s<span class="wpcpm-admin-table__breakdown">%3$s</span></td><td class="wpcpm-admin-table__n">%4$s</td><td class="wpcpm-admin-table__n">%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td></tr>',
					self::institution_link( $row['record'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( number_format_i18n( (int) $row['in_progress'] ) ),
					esc_html( implode( ', ', $breakdown ) ),
					esc_html( number_format_i18n( (int) $row['mentors'] ) ),
					esc_html( number_format_i18n( (int) $row['waiting'] ) ),
					esc_html(
						'' === $row['earliest']
							? __( 'no end date', 'wpcredits-program-manager' )
							: (
								$row['earliest'] === $row['latest']
									? $row['earliest']
									: sprintf( /* translators: 1: earliest end date, 2: latest end date. */ __( '%1$s to %2$s', 'wpcredits-program-manager' ), $row['earliest'], $row['latest'] )
							)
					),
					esc_html( WPCPM_Institution_Agreement::summary_label( (string) $row['agreement'] ) ),
					esc_html( WPCPM_Semester_Report::state_label( (string) $row['report'] ) )
				);
			}

			echo '</tbody></table></div>';
		}

		if ( ! empty( $programs['quiet'] ) ) {
			printf(
				'<p class="wpcpm-administrator__quiet">%s</p>',
				esc_html( sprintf( /* translators: %s: a number of institutions. */ _n( '%s more institution with no student in progress.', '%s more institutions with no student in progress.', (int) $programs['quiet'], 'wpcredits-program-manager' ), number_format_i18n( (int) $programs['quiet'] ) ) )
			);
		}

		printf(
			'<p class="wpcpm-administrator__read">%s</p>',
			esc_html(
				empty( $programs['read'] )
					? __( 'These students have not been read from the program records yet.', 'wpcredits-program-manager' )
					: sprintf( /* translators: 1: date and time, 2: how long ago. */ __( 'Read from the program records on %1$s (%2$s ago).', 'wpcredits-program-manager' ), self::when( (int) $programs['read'] ), human_time_diff( (int) $programs['read'], time() ) )
			)
		);

		WPCPM_Dashboard_Card::close();
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
	 * tab, so it needs no hidden words after it.
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

		printf( '<p class="wpcpm-administrator__quiet">%s</p>', esc_html( WPCPM_Mentor_Engagement::apart( $summary ) ) );
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
					'count' => $row['count'],
					'open'  => false,
					'state' => $row['state'],
				)
			)
		);

		self::scroll_open( array( self::title_id( 'health' ) ) );
		echo '<table class="wpcpm-admin-table"><thead><tr>';
		printf( '<th scope="col">%s</th>', esc_html__( 'Sync', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'State', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Last run', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Next run', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Last error', 'wpcredits-program-manager' ) );
		echo '<th scope="col"></th></tr></thead><tbody>';

		foreach ( $syncs as $sync ) {
			$progress = isset( $sync['progress'] ) && is_array( $sync['progress'] ) ? $sync['progress'] : array();
			$state    = ! empty( $progress['running'] )
				? sprintf( /* translators: %s: what the sync is doing. */ __( 'Running: %s', 'wpcredits-program-manager' ), isset( $progress['label'] ) ? (string) $progress['label'] : '' )
				: __( 'Idle', 'wpcredits-program-manager' );

			printf(
				'<tr class="wpcpm-health__sync"><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td><a href="%6$s">%7$s</a></td></tr>',
				esc_html( $sync['label'] ),
				esc_html( $state ),
				esc_html( empty( $sync['last'] ) ? /* translators: A table cell on its own, not a word inside a sentence. */ __( 'Never', 'wpcredits-program-manager' ) : self::when( (int) $sync['last'] ) ),
				esc_html( empty( $sync['next'] ) ? /* translators: A table cell on its own, not words inside a sentence. */ __( 'Not scheduled', 'wpcredits-program-manager' ) : self::when( (int) $sync['next'] ) ),
				esc_html( isset( $progress['error'] ) ? (string) $progress['error'] : '' ),
				esc_url( $sync['screen'] ),
				esc_html__( 'Open', 'wpcredits-program-manager' )
			);
		}

		echo '</tbody></table></div>';

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Locked accounts', 'wpcredits-program-manager' ) );

		if ( empty( $locked ) ) {
			self::empty_line( __( 'No account is locked today.', 'wpcredits-program-manager' ) );
		} else {
			$names = array();

			foreach ( $locked as $user ) {
				if ( $user instanceof WP_User ) {
					$names[] = $user->display_name . ' (' . $user->user_login . ')';
				}
			}

			printf(
				'<p class="wpcpm-administrator__note">%1$s %2$s</p>',
				esc_html( implode( ', ', $names ) ),
				esc_html__( 'Each was refused more claims than the daily ceiling allows; the lock lifts by itself tomorrow.', 'wpcredits-program-manager' )
			);
		}

		$probe   = isset( $health['probe'] ) ? (array) $health['probe'] : array(
			'verdict' => 'unknown',
			'time'    => 0,
		);
		$mail    = isset( $health['mail'] ) ? (array) $health['mail'] : array();
		$invites = isset( $health['invites'] ) ? (array) $health['invites'] : array(
			'run'    => array(),
			'queued' => 0,
		);

		echo '<ul class="wpcpm-administrator__list">';
		printf(
			'<li>%s</li>',
			esc_html( sprintf( /* translators: 1: the verdict, 2: a date. */ __( 'Private storage: %1$s (probed %2$s).', 'wpcredits-program-manager' ), $probe['verdict'], empty( $probe['time'] ) ? __( 'never', 'wpcredits-program-manager' ) : self::when( (int) $probe['time'] ) ) )
		);
		// The latest email by name, and the last day's failures, each linked to the Emails tool's Log.
		// The address is masked: this is a page of the site, and the full one is the Log's, in wp-admin.
		$latest = isset( $mail['latest'] ) ? (array) $mail['latest'] : array();
		$failed = isset( $mail['failed'] ) ? (int) $mail['failed'] : 0;
		$ready  = ! isset( $mail['ready'] ) || $mail['ready'];
		$tool   = WPCPM_Tools::get( 'emails' );
		$log    = add_query_arg( 'tab', 'log', $tool ? $tool->admin_url() : admin_url( 'admin.php?page=wpcpm-tool-emails' ) );

		printf(
			'<li>%1$s <a href="%2$s">%3$s</a></li>',
			esc_html(
				empty( $latest )
					? ( $ready ? __( 'No email has been sent yet.', 'wpcredits-program-manager' ) : __( 'The email log is not ready yet.', 'wpcredits-program-manager' ) )
					: sprintf(
						/* translators: 1: an email's name, 2: a masked address, 3: a date, 4: the email's status. */
						__( 'Last email: %1$s to %2$s on %3$s. Status: %4$s.', 'wpcredits-program-manager' ),
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
			printf(
				'<li>%1$s <a href="%2$s">%3$s</a></li>',
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

			printf(
				'<li>%s</li>',
				esc_html( sprintf( /* translators: 1: messages sent, 2: messages in the run. */ __( 'Invitations: %1$s of %2$s sent.', 'wpcredits-program-manager' ), number_format_i18n( $sent ), number_format_i18n( $total ) ) )
			);
		}

		echo '</ul>';

		WPCPM_Dashboard_Card::close();
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
	 * Open the box a table with column headers scrolls in on a phone (1.122.35); the table's own
	 * closing tag is followed by the box's.
	 *
	 * Below 782px administrator.css lets this box, not the table, scroll sideways, since a table
	 * cannot be a region without losing what makes it a table. The box is a tab stop, so a keyboard
	 * reaches it and its arrow keys scroll it (Safari reaches no scroll box that holds no link or
	 * button), and a region named by the headings above the table, so a screen reader says what
	 * scrolls, as the Education Hub's table was made in theme 1.24.15. A tab stop at every width,
	 * as that one is: an attribute cannot follow a media query without a script, and the dashboards
	 * work without one, so on a wide screen it is one stop more, around a table that fits.
	 *
	 * @param string[] $labels The ids of the headings that name the table, the card's first.
	 */
	private static function scroll_open( array $labels ) {
		printf( '<div class="wpcpm-administrator__scroll" tabindex="0" role="region" aria-labelledby="%s">', esc_attr( implode( ' ', $labels ) ) );
	}

	/**
	 * An empty half under a heading reads as something that failed to load, so it says so.
	 *
	 * @param string $text The sentence.
	 */
	private static function empty_line( $text ) {
		printf( '<p class="wpcpm-administrator__empty">%s</p>', esc_html( $text ) );
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
			self::site_day( (int) $read )
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
	 * reads. The Duplicated students card, whose home is the Student Duplicate Finder, ends with its
	 * own button to the finder instead. It takes the shape of the line under a list this page
	 * cut (`render_more_line()`), which says how many more there are and links nowhere, and comes
	 * after that line on a card that prints both. The words that name the card by its title, where the
	 * action carries them, follow the link's words inside the link for a screen reader alone, as they
	 * do in the row action, with the space before them inside the hidden span, so the line reads the
	 * same as before to everyone who sees it; Mentor engagement's link names its tab and carries none.
	 *
	 * @param array $action The card's row action: `label`, `href` and, optionally, `context`, the
	 *                      words that name the card by its title.
	 */
	private static function render_screen_line( array $action ) {
		$context = isset( $action['context'] ) ? trim( (string) $action['context'] ) : '';

		printf(
			'<p class="wpcpm-administrator__more"><a href="%1$s">%2$s%3$s</a></p>',
			esc_url( (string) $action['href'] ),
			esc_html( (string) $action['label'] ),
			'' === $context ? '' : '<span class="screen-reader-text"> ' . esc_html( $context ) . '</span>'
		);
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
	 * @param array $syncs `health()`'s `syncs`, each with `last` and `progress`.
	 * @param bool  $auto  Whether Automatic sync is on.
	 * @return array{count: int, state: string} The badge's number and the row's phrase.
	 */
	private static function syncs_row( array $syncs, $auto ) {
		$since   = time() - self::SYNC_WINDOW;
		$look    = 0;
		$figures = '';

		foreach ( $syncs as $key => $sync ) {
			if ( 'outcome_figures' === $key ) {
				$figures = self::figures_look();

				continue;
			}

			$last  = isset( $sync['last'] ) ? (int) $sync['last'] : 0;
			$error = isset( $sync['progress']['error'] ) ? trim( (string) $sync['progress']['error'] ) : '';

			if ( '' !== $error || ( $auto && $last < $since ) ) {
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

			$rows[] = array(
				'id'     => (int) $post->ID,
				'record' => (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
				'at'     => (int) get_post_modified_time( 'U', true, $post ),
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
	 * Escaped here: every caller prints it into markup as is.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function institution_link( $record ) {
		$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? (string) WPCPM_Institutions_Dashboard::page_url() : '';
		$name = esc_html( self::institution_name( $record ) );

		if ( '' === $page || '' === (string) $record ) {
			return $name;
		}

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $page ) ), $name );
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
	 * When a sponsor report was marked final and by whom: "<when> by <name>", the moment alone
	 * for an account that is no longer on the site, and "Not recorded" for a report with no stamp.
	 *
	 * @param int $timestamp Unix time, or 0.
	 * @param int $user_id   The program manager who marked it, or 0.
	 * @return string
	 */
	private static function marked_final( $timestamp, $user_id ) {
		if ( $timestamp <= 0 ) {
			return __( 'Not recorded', 'wpcredits-program-manager' );
		}

		$by = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( ! $by instanceof WP_User ) {
			return self::when( $timestamp );
		}

		/* translators: 1: a date and time, 2: an Administrator's name. */
		return sprintf( __( '%1$s by %2$s', 'wpcredits-program-manager' ), self::when( $timestamp ), $by->display_name );
	}

	/**
	 * "<when>, by the site" or "<when>, by a manager".
	 *
	 * @param int    $timestamp Unix time.
	 * @param string $origin    `auto` or `manager`.
	 * @return string
	 */
	private static function drafted( $timestamp, $origin ) {
		return self::when( $timestamp ) . ', ' . ( 'auto' === $origin ? __( 'by the site', 'wpcredits-program-manager' ) : __( 'by an Administrator', 'wpcredits-program-manager' ) );
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
