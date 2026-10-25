<?php
/**
 * The Sponsor Dashboard's tier cards: Sponsorship, and the events a tier includes.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a sponsor's tier includes, what the next one adds, one press to say so, and the events.
 *
 * One class draws both cards from one `render()` call, the way `WPCPM_Sponsor_Mentors` draws its
 * two, so the sponsorship group names it once, first. Sponsorship reads the effective tier
 * (`WPCPM_Sponsor_Tiers::effective()`), its term and its funded line, lists what the tier
 * includes and, below Partner, what the next tier adds, with a button that tells the program
 * contact. The button acts on the effective tier too. The row's recorded `tier` decides three
 * things only: whether a date on the row is a term at all (a tier above Friend), which
 * term the sentence says has ended, and, once one has, that Talk asks to renew it rather than to
 * move to the tier above the Friend the row now reads as.
 *
 * Events, from Champion up, lists the events the tier includes, with a line under the co-hosted
 * event while the program has granted the running term more of them on the Sponsors screen's Tier
 * box, says what the planner under the list is for, and hands the rest to
 * `WPCPM_Sponsor_Event_Planner` (1.117.0): the co-hosted webinar or event is planned, changed and
 * canceled there, while the program plans the partners session and the launch communications with
 * the sponsor. The list and the sentence are drawn only while the sponsor holds the planner
 * (Approved, with the co-hosted event in its tier). A sponsor that does not, paused or whose tier
 * lost events, keeps the card while an event of its own is still to take place, since the card is
 * the one place that says the event is no longer offered and the one place to cancel it; with
 * nothing to take place it has no Events card.
 *
 * Each card opens by what it holds, as the design the owner approved on 27 September 2026 has it:
 * Sponsorship always, since it is how the sponsorship stands, and Events while an event of the
 * sponsor's is still to take place; a flash opens the card it names as before. Each row says its
 * state in one phrase: the tier with the term's last day and the days left in it, and the next
 * event with its day and how many joined, or that it waits for the program while the program has
 * not approved it, or that none is planned. Events offers Plan an event after its fold while it is
 * folded and the planner would take a plan; Sponsorship offers nothing there, since its one press,
 * Talk, is inside it. The term and the event's day are read here for the row (`term_days_left()`,
 * `event_day()`), and the At a glance strip's tier tile, which opens the Sponsorship card, reads the
 * same term, so the two never say different things; whether the Events card is drawn at all is one
 * public test, `draws_events()`, which the Your sponsorship group's line asks too.
 *
 * Nothing is drawn and the button does not act while the launch switch is off: until the program
 * announces the tiers, nothing a sponsor sees names one. The button files an expression of
 * interest through `WPCPM_Sponsor_Interests::file()`, so it claims `ACT_EXPRESS_INTEREST`, is held
 * to the interests' ceiling of five a day per account, and writes, mails and logs as every interest
 * is written, mailed and logged; since Offer more left the page (1.123.22) it is the one press on
 * the page that files one. The card links nowhere for the program contact: the page's Resources
 * section already names them, with the booking link.
 */
final class WPCPM_Sponsor_Tier_Card {

	/** The Sponsorship card's anchor and flash key on the Sponsor Dashboard. */
	const CARD = 'sponsorship';

	/** The Events card's anchor and flash key. */
	const CARD_EVENTS = 'events';

	/** Talk to your program contact. Nonce keyed to the sponsor. */
	const ACTION_TALK = 'wpcpm_tier_talk';

	/**
	 * The one handler. The Events card's presses are the planner's own (`WPCPM_Sponsor_Event_Planner`).
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_TALK, array( __CLASS__, 'handle_talk' ) );
	}

	/**
	 * The Sponsorship card's outcomes. `tier-off` names no tier, because a press can reach it while the
	 * launch switch is off; `refused` is the sentence every card on the page shares. The Events
	 * card's are the planner's own.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'tier-sent'    => array( 'success', __( 'Sent. Your program contact will be in touch.', 'wpcredits-program-manager' ) ),
			'tier-unsent'  => array( 'warning', __( 'Your message is on record. No program contact could be mailed just now; the program will see it on the Sponsors screen.', 'wpcredits-program-manager' ) ),
			'tier-ceiling' => array( 'error', __( 'You have sent five messages today. Tomorrow, then.', 'wpcredits-program-manager' ) ),
			'tier-failed'  => array( 'error', __( 'That could not be recorded right now. Try again later.', 'wpcredits-program-manager' ) ),
			'tier-off'     => array( 'error', __( 'That is not available for your company right now.', 'wpcredits-program-manager' ) ),
			'refused'      => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The two cards: Sponsorship, then Events from Champion up.
	 *
	 * Nothing at all while the launch switch is off. Today is read once, so the tier, the term
	 * and the note all speak of the same day. Sponsorship is drawn open, whatever a flash says.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return;
		}

		$row    = WPCPM_Sponsors_Index::row( $record );
		$row    = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
		$today  = WPCPM_Sponsor_Tiers::today();
		$tier   = WPCPM_Sponsor_Tiers::effective( $row, $today );
		$open   = isset( $context['open'] ) ? (string) $context['open'] : '';
		$viewer = isset( $context['viewer'] ) && $context['viewer'] instanceof WP_User ? $context['viewer'] : wp_get_current_user();

		self::render_sponsorship( $record, $row, $tier, $today );

		// What the Events card is drawn from, read once, since the card reads it again for whether it
		// is open, for its row and for its action. An event is offered only while its sponsor holds the
		// planner, so a sponsor that no longer does still gets the card while an event of its own is to
		// take place: the card is where it reads that the event is no longer offered, and where it
		// cancels it.
		$events = self::events_facts( $record, $row, $tier );

		if ( self::drawn( $events ) ) {
			self::render_events( $record, $row, $tier, $viewer, $events, self::CARD_EVENTS === $open );
		}
	}

	/**
	 * Whether the Events card is drawn for a sponsor: while it holds the planner, or while an event of
	 * its own is still to take place. The one test for it: the Your sponsorship group's line asks it
	 * here, and `render()` asks the same rule of the same facts (`drawn()`, `events_facts()`), so the
	 * line never names a card the page did not draw. It does not read the launch switch: `render()`
	 * draws nothing while the switch is off, before it asks, so a caller asks it only with the switch
	 * on. The At a glance strip's Event returned tile asks the planner's allowance instead, since the
	 * card prints a returned event's note only inside the planner's plan form.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $tier   The effective tier.
	 * @return bool
	 */
	public static function draws_events( $record, array $row, $tier ) {
		return self::drawn( self::events_facts( $record, $row, $tier ) );
	}

	/**
	 * The Events card's draw rule, of the facts `events_facts()` read: the planner held, or an event
	 * still to take place.
	 *
	 * @param array $events As `events_facts()` answers.
	 * @return bool
	 */
	private static function drawn( array $events ) {
		return $events['offered'] || array() !== $events['upcoming'];
	}

	/**
	 * What the Events card is drawn from, the planner asked once: its allowance with no start yet
	 * (`WPCPM_Sponsor_Event_Planner::allowance()`), which says both whether the sponsor holds the
	 * planner today, as the planner reads it (`holds()`), and whether the planner would take a plan,
	 * when Plan an event follows the fold; and the sponsor's events still to take place, planned or
	 * approved, the soonest first. On a checkout without the planner or the event store the card lists
	 * the tier's events as it did before there was a planner to ask, offers no plan, and has no event
	 * to read.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $tier   The effective tier.
	 * @return array `offered` and `plans`, bools, and `upcoming`, the events still to take place.
	 */
	private static function events_facts( $record, array $row, $tier ) {
		$planner   = class_exists( 'WPCPM_Sponsor_Event_Planner' ) && class_exists( 'WPCPM_Sponsor_Event' );
		$allowance = $planner ? WPCPM_Sponsor_Event_Planner::allowance( $record, $row, $tier ) : null;
		$upcoming  = class_exists( 'WPCPM_Sponsor_Event' ) ? WPCPM_Sponsor_Event::upcoming_of( $record ) : array();

		return array(
			'offered'  => $planner ? WPCPM_Sponsor_Event_Planner::holds( $allowance ) : WPCPM_Sponsor_Tiers::includes( $tier, 'events' ),
			'plans'    => '' === $allowance,
			'upcoming' => $upcoming,
		);
	}

	/**
	 * How many days are left in the sponsor's term, as this card counts a term: only on a recorded
	 * tier above Friend, since the Sponsors screen never leaves a date on a Friend's
	 * row and a date left under anything else is no term to speak for. Such a term runs through its
	 * last day and is over from the day after. The card's tier line, its row and the At a glance
	 * tile all read the term here, so they never disagree about whether one is running.
	 *
	 * @param array  $row   The sponsor's row.
	 * @param string $today `Y-m-d`.
	 * @return int|null Null with no term; 0 on its last day; negative once it has ended.
	 */
	public static function term_days_left( array $row, $today ) {
		return WPCPM_Sponsor_Tiers::rank( (string) $row['tier'] ) > 0 ? WPCPM_Sponsor_Tiers::days_left( $row, $today ) : null;
	}

	/**
	 * The day an event starts on, on the viewer's clock, the one the planner draws every day and time
	 * on, so the Events card's row and the planner under it name the same day: in the site's date
	 * format. On the site's clock where the viewer's cannot be read.
	 *
	 * @param array        $facts  The event's facts, `WPCPM_Sponsor_Event::facts()`.
	 * @param WP_User|null $viewer Who is looking; the signed-in account when none is given.
	 * @return string
	 */
	public static function event_day( array $facts, $viewer = null ) {
		$person = $viewer instanceof WP_User ? $viewer : wp_get_current_user();
		$zone   = class_exists( 'WPCPM_Mentor_Availability' ) ? WPCPM_Mentor_Availability::viewer_timezone( (int) $person->ID ) : wp_timezone();

		return (string) wp_date( (string) get_option( 'date_format', 'F j, Y' ), (int) $facts['start'], $zone );
	}

	/**
	 * Talk to your program contact: one line, filed as an interest, whose note asks to renew a
	 * term that has ended or else names the tier above.
	 */
	public static function handle_talk() {
		$asked = WPCPM_Request::posted_text( 'wpcpm_sponsor' );

		check_admin_referer( self::ACTION_TALK . '_' . $asked );

		$claim = self::claim_press( $asked );
		$row   = $claim['row'];
		$tier  = WPCPM_Sponsor_Tiers::effective( $row );

		// Decided as the card decides it (renews()), from the row the claim read.
		if ( self::renews( $row, $tier ) ) {
			$note = sprintf(
				/* translators: %s: the name of the tier whose term ended. */
				__( 'wants to renew as %s', 'wpcredits-program-manager' ),
				WPCPM_Sponsor_Tiers::label( $row['tier'] )
			);
		} else {
			$next = WPCPM_Sponsor_Tiers::next( $tier );

			// Partner is the top of the ladder and gets no button, so a press here comes from a
			// page drawn before the tier changed, or from a request made by hand: nothing is filed.
			if ( '' === $next ) {
				self::leave( 'tier-off', $claim['record'] );
			}

			$note = sprintf(
				/* translators: %s: the name of the tier above the sponsor's own. */
				__( 'wants to move to %s', 'wpcredits-program-manager' ),
				WPCPM_Sponsor_Tiers::label( $next )
			);
		}

		// A request, not an event: the note, so the line reads "note: ..." and the audit row
		// counts no event.
		self::file_press( $claim, $note );
	}

	/**
	 * Whether Talk asks to renew a term rather than to move up: the tier recorded ranks above the
	 * effective one, which happens only once a term has ended. The card and the press both ask
	 * this, so the note under the button and the line the press files agree.
	 *
	 * @param array  $row  The sponsor's row.
	 * @param string $tier The effective tier.
	 * @return bool
	 */
	private static function renews( array $row, $tier ) {
		return WPCPM_Sponsor_Tiers::rank( $row['tier'] ) > WPCPM_Sponsor_Tiers::rank( $tier );
	}

	/**
	 * What the press checks once its handler has checked the nonce keyed to the sponsor the form
	 * names, in this order: the roster's claim (which decides `ACT_EXPRESS_INTEREST` and meters a
	 * refusal), the launch switch, and a row the site holds.
	 *
	 * @param string $asked The sponsor the form names.
	 * @return array The claim: `record`, `decision`, and `row`, which is an array here.
	 */
	private static function claim_press( $asked ) {
		$claim = WPCPM_Sponsor_Roster::claim( $asked, WPCPM_Sponsor_Policy::ACT_EXPRESS_INTEREST );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		// While the switch is off the button is not drawn, so a press comes from a page drawn
		// before it went off, or from a request made by hand: nothing is filed, and the sentence
		// it leaves with names no tier.
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			self::leave( 'tier-off', $claim['record'] );
		}

		// An index the site has not read cannot be written back to, as on the profile card: a member
		// whose sponsor left the index still claims.
		if ( ! is_array( $claim['row'] ) ) {
			self::leave( 'tier-failed', $claim['record'] );
		}

		return $claim;
	}

	/**
	 * The end of the press: the interests' ceiling (five a day per account, the one Offer more's form
	 * shared until it left the page in 1.123.22, since both filed the same kind of line), then the one
	 * road an interest takes, then the flash.
	 *
	 * @param array  $claim The claim `claim_press()` answered.
	 * @param string $note  What the sponsor would like, as the line's note: a request, not an event.
	 */
	private static function file_press( array $claim, $note ) {
		$viewer = wp_get_current_user();

		if ( ! WPCPM_Ceiling::claim( WPCPM_Ceiling::key( WPCPM_Sponsor_Interests::CEILING, (string) $viewer->ID ), WPCPM_Sponsor_Interests::PER_DAY, DAY_IN_SECONDS ) ) {
			self::leave( 'tier-ceiling', $claim['record'] );
		}

		$filed = WPCPM_Sponsor_Interests::file( $claim['record'], $claim['row'], array(), array(), $note, $viewer->display_name, $claim['decision']['ground'] );

		if ( ! $filed['ok'] ) {
			self::leave( 'tier-failed', $claim['record'] );
		}

		// Nobody may be reachable (no manager assigned, an empty sponsor_notify setting, no
		// account holding the manage capability): the line is on record either way, and the
		// sentence says which happened.
		self::leave( $filed['mailed'] > 0 ? 'tier-sent' : 'tier-unsent', $claim['record'] );
	}

	/**
	 * Sponsorship: the tier and its term, the note in a term's last thirty days, the funded line,
	 * what the tier includes, and what the next tier adds, with the button. Drawn open whatever a
	 * flash says, since it is how the sponsorship stands, and with no action after the fold, since
	 * Talk is inside it.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $tier   The effective tier.
	 * @param string $today  `Y-m-d`.
	 */
	private static function render_sponsorship( $record, array $row, $tier, $today ) {
		WPCPM_Dashboard_Card::open(
			array(
				'key'   => self::CARD,
				'title' => __( 'Sponsorship', 'wpcredits-program-manager' ),
				'open'  => true,
				'state' => self::sponsorship_state( $row, $tier, $today ),
			)
		);

		self::render_term( $row, $tier, $today );

		$funded = trim( (string) $row['funded_line'] );

		if ( WPCPM_Sponsor_Tiers::includes( $tier, 'funded_line' ) && '' !== $funded ) {
			printf(
				'<p class="wpcpm-sponsorship__funded">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: what the sponsorship funds, as the program wrote it. */
						__( 'Your sponsorship funds: %s', 'wpcredits-program-manager' ),
						$funded
					)
				)
			);
		}

		// The partner page's lead, "Everything in <the level below>, plus:", is a line of its own over
		// the checklist, as the page sets it, never one of the things checked (1.123.18).
		$includes = WPCPM_Sponsor_Tiers::describe( $tier );

		echo '<h4 class="wpcpm-sponsor__subheading">' . esc_html__( 'Your tier includes', 'wpcredits-program-manager' ) . '</h4>';

		if ( '' !== $includes['lead'] ) {
			echo '<p class="wpcpm-sponsorship__lead">' . esc_html( $includes['lead'] ) . '</p>';
		}

		echo '<ul class="wpcpm-sponsorship__includes">';

		foreach ( $includes['items'] as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}

		echo '</ul>';

		// A term that has ended asks for its renewal, not a downgrade: the
		// sentence above already names the tier, so the next tier's line is not drawn.
		// Otherwise, below Partner, the card says what the next tier adds; Partner is the top of
		// the ladder, with no next tier and so no button.
		$renew = self::renews( $row, $tier );

		if ( $renew || '' !== WPCPM_Sponsor_Tiers::next( $tier ) ) {
			if ( ! $renew ) {
				echo '<p class="wpcpm-student__note">' . esc_html( WPCPM_Sponsor_Tiers::next_line( $tier ) ) . '</p>';
			}

			$after = $renew
				? __( 'One press tells your program contact you would like to renew your term.', 'wpcredits-program-manager' )
				: __( 'One press tells your program contact you would like to talk about the next tier.', 'wpcredits-program-manager' );

			self::open_form( $record );
			printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Talk to your program contact', 'wpcredits-program-manager' ) );
			echo '</form>';
			echo '<p class="wpcpm-student__note">' . esc_html( $after ) . '</p>';
		}

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Sponsorship card's row: the tier to the term's last day with the days left in it while a
	 * term runs, and on its last day that it ends today, as the thirty-day note says it; the tier the
	 * sponsor reads as since a term ended, with that term and its last day; otherwise the tier's name,
	 * as the tier line gives it, the checklist under it saying what the tier includes.
	 *
	 * @param array  $row   The sponsor's row.
	 * @param string $tier  The effective tier.
	 * @param string $today `Y-m-d`.
	 * @return string
	 */
	private static function sponsorship_state( array $row, $tier, $today ) {
		$left = self::term_days_left( $row, $today );

		if ( null === $left ) {
			return WPCPM_Sponsor_Tiers::label( $tier );
		}

		$day = WPCPM_Sponsor_Tiers::format_day( (string) $row['tier_until'] );

		if ( $left < 0 ) {
			return sprintf(
				/* translators: 1: the tier the sponsor reads as now, Friend, 2: the tier the term was for, 3: the term's last day. */
				__( '%1$s since the %2$s term ended on %3$s', 'wpcredits-program-manager' ),
				WPCPM_Sponsor_Tiers::label( $tier ),
				WPCPM_Sponsor_Tiers::label( (string) $row['tier'] ),
				$day
			);
		}

		if ( 0 === $left ) {
			/* translators: 1: the tier's name, 2: the last day of the term, which is today. */
			return sprintf( __( '%1$s to %2$s, ending today', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::label( $tier ), $day );
		}

		return sprintf(
			/* translators: 1: the tier's name, 2: the last day of the term, 3: how many days are left in it. */
			_n( '%1$s to %2$s, %3$s day left', '%1$s to %2$s, %3$s days left', $left, 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Tiers::label( $tier ),
			$day,
			number_format_i18n( $left )
		);
	}

	/**
	 * The tier line, and the note in the last thirty days of a term.
	 *
	 * A term is read by `term_days_left()`: only on a recorded tier above Friend, open
	 * through its last day (the recorded tier is then the effective one) and over from the day
	 * after, when the sponsor reads as a Friend.
	 *
	 * The line links to the Partners page once the launch switch has published it, at every level
	 * (1.123.22): the page lists every level in one alphabetical list since 1.123.18, where the link
	 * was a Friend's alone, left from the listing by level. It follows the level's name, or the term's
	 * last day while a term runs, its last thirty days among them. A term that has ended reads its
	 * renewal sentence alone: the sentence is about the term and who renews it, and a link after it
	 * would read as the way to renew. Behind class_exists() so the card still draws on a checkout
	 * without the page class.
	 *
	 * @param array  $row   The sponsor's row.
	 * @param string $tier  The effective tier.
	 * @param string $today `Y-m-d`.
	 */
	private static function render_term( array $row, $tier, $today ) {
		$recorded = (string) $row['tier'];
		$left     = self::term_days_left( $row, $today );
		$day      = null === $left ? '' : WPCPM_Sponsor_Tiers::format_day( (string) $row['tier_until'] );
		$partners = class_exists( 'WPCPM_Sponsors_Page' ) ? WPCPM_Sponsors_Page::page_url() : '';
		$link     = '' === $partners ? '' : sprintf( ' <a href="%1$s">%2$s</a>', esc_url( $partners ), esc_html__( 'See the program\'s partners.', 'wpcredits-program-manager' ) );

		if ( null !== $left && $left >= 0 ) {
			// The tier's name is set in bold, so it is printed outside the translated words.
			printf(
				'<p class="wpcpm-sponsorship__tier"><strong>%1$s</strong> %2$s%3$s</p>',
				esc_html( WPCPM_Sponsor_Tiers::label( $tier ) ),
				esc_html(
					sprintf(
						/* translators: %s: the last day of the term. */
						__( 'until %s.', 'wpcredits-program-manager' ),
						$day
					)
				),
				$link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above with esc_url() and esc_html__().
			);
		} elseif ( null !== $left ) {
			printf(
				'<p class="wpcpm-sponsorship__tier">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: the tier the term was for, 2: the term's last day. */
						__( 'Your %1$s term ended on %2$s. Your company reads as a Friend now; your program contact can renew it.', 'wpcredits-program-manager' ),
						WPCPM_Sponsor_Tiers::label( $recorded ),
						$day
					)
				)
			);
		} else {
			// The line names the tier and the link, no more: the checklist below says what the tier
			// includes, the company on the partner page among it, and saying it here too put the same
			// sentence on the card twice.
			printf(
				'<p class="wpcpm-sponsorship__tier"><strong>%1$s</strong>%2$s</p>',
				esc_html( WPCPM_Sponsor_Tiers::label( $tier ) ),
				$link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above with esc_url() and esc_html__().
			);
		}

		if ( null === $left || $left < 0 || $left > WPCPM_Sponsor_Tiers::NOTICE_DAYS ) {
			return;
		}

		// Today and tomorrow are words, not counts, so each is a sentence of its own; the day
		// keeps the placeholder the counted sentence gives it.
		if ( 0 === $left ) {
			/* translators: 1: the last day of the term. */
			$note = sprintf( __( 'Your term ends on %1$s, today. Your program contact can renew it.', 'wpcredits-program-manager' ), $day );
		} elseif ( 1 === $left ) {
			/* translators: 1: the last day of the term. */
			$note = sprintf( __( 'Your term ends on %1$s, tomorrow. Your program contact can renew it.', 'wpcredits-program-manager' ), $day );
		} else {
			// Both forms carry the count: in another language the first form can cover 21 or 31.
			$note = sprintf(
				/* translators: 1: the last day of the term, 2: how many days are left. */
				_n( 'Your term ends on %1$s, in %2$d day. Your program contact can renew it.', 'Your term ends on %1$s, in %2$d days. Your program contact can renew it.', $left, 'wpcredits-program-manager' ),
				$day,
				$left
			);
		}

		echo '<p class="wpcpm-student__note">' . esc_html( $note ) . '</p>';
	}

	/**
	 * Events: the events the tier includes, what the planner under them is for, and the planner.
	 *
	 * The list and the sentence are drawn only while the sponsor holds the planner: for a sponsor
	 * that does not, they would promise a planner that is not there, above an event that says it is
	 * no longer offered. Such a sponsor gets the planner alone, which then holds its event still to
	 * take place. Under the co-hosted event, the list says how many more of it the program granted
	 * the running term on the Sponsors screen's Tier box (`WPCPM_Sponsor_Event::extra_events()`),
	 * while it granted any.
	 *
	 * Open while an event is still to take place, or when a flash names the card. The row names the
	 * soonest of those events, the one the planner lists first, or says none is planned; while the
	 * card is folded, Plan an event follows the fold whenever the planner would draw its form, which
	 * is when its allowance refuses nothing, and links to the card, where the form is. All three read
	 * the facts `render()` read once, the planner's one answer among them.
	 *
	 * @param string  $record Sponsor key.
	 * @param array   $row    The sponsor's row.
	 * @param string  $tier   The effective tier.
	 * @param WP_User $viewer Who is looking, for the planner's clock.
	 * @param array   $facts  From `events_facts()`: whether the sponsor holds the planner today,
	 *                        whether the planner would take a plan, and the sponsor's events still to
	 *                        take place, the soonest first.
	 * @param bool    $open   Whether a flash names this card.
	 */
	private static function render_events( $record, array $row, $tier, $viewer, array $facts, $open ) {
		WPCPM_Dashboard_Card::open(
			array(
				'key'    => self::CARD_EVENTS,
				'title'  => __( 'Events', 'wpcredits-program-manager' ),
				'open'   => $open || array() !== $facts['upcoming'],
				'state'  => self::events_state( $facts['upcoming'], $viewer ),
				'action' => $facts['plans'] ? array(
					'label'   => __( 'Plan an event', 'wpcredits-program-manager' ),
					/* translators: read after "Plan an event" by a screen reader alone. */
					'context' => __( 'with the program', 'wpcredits-program-manager' ),
				) : null,
			)
		);

		$events = $facts['offered'] ? WPCPM_Sponsor_Tiers::events( $tier ) : array();

		if ( array() !== $events ) {
			// The running term's last day is the row's while the list is drawn: a sponsor holding the
			// planner has a term still running or none, and a term that lapsed draws no list.
			$extra = class_exists( 'WPCPM_Sponsor_Event' ) ? WPCPM_Sponsor_Event::extra_events( $row, isset( $row['tier_until'] ) ? (string) $row['tier_until'] : '' ) : 0;

			echo '<h4 class="wpcpm-sponsor__subheading">' . esc_html__( 'Events your tier includes', 'wpcredits-program-manager' ) . '</h4>';
			echo '<ul class="wpcpm-sponsorship__events">';

			foreach ( $events as $event ) {
				echo '<li>' . esc_html( WPCPM_Sponsor_Tiers::event_label( $event ) ) . '</li>';

				// The events the program granted this term beyond the tier's one follow the co-hosted
				// event they add to, since they are more of it.
				if ( WPCPM_Sponsor_Tiers::EVENT_CO_HOSTED === $event && $extra > 0 ) {
					echo '<li>' . esc_html(
						sprintf(
							/* translators: %s: how many co-hosted events the program granted the sponsor for its term beyond the one its tier includes. */
							_n( 'and %s more this term, granted by the program', 'and %s more this term, granted by the program', $extra, 'wpcredits-program-manager' ),
							number_format_i18n( $extra )
						)
					) . '</li>';
				}
			}

			echo '</ul>';

			// The planner is for the co-hosted event alone: the partners session is the program's own
			// invitation, and the launch communications have no date. The launch communications are
			// named only for a tier that includes them, so the sentence never promises a Champion one.
			$planner_note = WPCPM_Sponsor_Tiers::includes( $tier, 'launch_communications' )
				? __( 'The planner below is for your co-hosted webinar or event; the program plans the partners session and the launch communications with you.', 'wpcredits-program-manager' )
				: __( 'The planner below is for your co-hosted webinar or event; the program plans the partners session with you.', 'wpcredits-program-manager' );

			echo '<p class="wpcpm-student__note">' . esc_html( $planner_note ) . '</p>';
		}

		// Behind class_exists() so the card still draws on a checkout without the planner.
		if ( class_exists( 'WPCPM_Sponsor_Event_Planner' ) ) {
			WPCPM_Sponsor_Event_Planner::render( $record, $row, $tier, $viewer );
		}

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The Events card's row: the soonest event still to take place, with its day and how many joined,
	 * or that no event is planned. While the program has not approved the event nobody can join it,
	 * so the row says it waits for the program, as the planner under it says it waits for the
	 * program's OK, rather than counting nobody. Its title is printed as it is stored and escaped
	 * where the row is printed.
	 *
	 * @param WP_Post[] $upcoming The sponsor's events still to take place, the soonest first.
	 * @param WP_User   $viewer   Who is looking, whose clock the day is read on.
	 * @return string
	 */
	private static function events_state( array $upcoming, $viewer ) {
		if ( array() === $upcoming ) {
			return __( 'No event planned', 'wpcredits-program-manager' );
		}

		$facts = WPCPM_Sponsor_Event::facts( $upcoming[0] );

		if ( WPCPM_Sponsor_Event::STATE_PLANNED === $facts['state'] ) {
			return sprintf(
				/* translators: 1: the event's title, 2: the day it starts on. */
				__( '%1$s on %2$s, waiting for the program', 'wpcredits-program-manager' ),
				$facts['title'],
				self::event_day( $facts, $viewer )
			);
		}

		return sprintf(
			/* translators: 1: the event's title, 2: the day it starts on, 3: how many people have joined it. */
			_n( '%1$s on %2$s, %3$s joined', '%1$s on %2$s, %3$s joined', (int) $facts['joined'], 'wpcredits-program-manager' ),
			$facts['title'],
			self::event_day( $facts, $viewer ),
			number_format_i18n( (int) $facts['joined'] )
		);
	}

	/**
	 * The button's form opening: posted to admin-post.php, guarded against a second press, with
	 * the nonce keyed to the sponsor and the two hidden fields the press is read by.
	 *
	 * @param string $record Sponsor key.
	 */
	private static function open_form( $record ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_TALK . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_TALK ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
	}

	/**
	 * Flash and go back to the dashboard, at the Sponsorship card, where the button is. Through the
	 * dashboard's own method, by array callable, as every card does (see the profile card).
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, for the manager switcher; '' to land on the page as is.
	 */
	private static function leave( $status, $record ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record );
		exit;
	}
}
