<?php
/**
 * The sponsor tier ladder's daily run: dormant, wake and the thirty-day mail.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Once a day, each Approved sponsor's posts are brought in line with its effective tier, and its
 * dormant offers are brought back.
 *
 * `plan_for()` works out one sponsor's plan: the published posts its effective tier no longer
 * covers, the dormant posts it covers again, the dormant offers to wake, and whether the
 * thirty-day mail is due. No tier caps live offers since 1.123.18, so the run puts no offer to
 * dormant; an offer the run parked under the caps before then wakes on the next run with the
 * switch on. It is pure and ungated, so the Sponsors screen's grace preview (`preview()`) and the daily
 * run (`run()`) read one plan and cannot drift apart. `apply()` holds every gate: nothing goes
 * dormant unless the launch switch is on and the grace date has passed, nothing wakes unless the
 * switch is on, and the mail goes regardless, because it is a manager's and a term ends before
 * the launch as after it.
 *
 * Which offers wake first: the primary, the one the profile card points to, then the oldest.
 * Which posts keep their slots: the oldest published, so the newest go dormant first. The dormant
 * posts wake in the same order. A sponsor that is not Approved is left alone: its offers are shown to
 * nobody, and its posts are a manager's to handle.
 *
 * `WPCPM_Sponsors::schedule_cron()` puts `CRON_HOOK` on the clock daily, and the module's
 * deactivation and uninstall take it off, like the module's other two daily jobs. The hook's name
 * is written once, as the constant, and the plugin's other files name it through the constant.
 */
final class WPCPM_Sponsor_Tiers_Run {

	/**
	 * The daily event, and the audit kind of a run that moved something for a sponsor.
	 */
	const CRON_HOOK = 'wpcpm_sponsor_tiers_daily';
	const LOG_KIND  = 'sponsor_tier_run';

	/**
	 * The listener: the daily event runs `run()`.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Every Approved sponsor, planned and applied, one after another.
	 *
	 * The site's day is read once, here, and handed to every question the run asks, as `preview()`
	 * fixes its own: the run's hour falls wherever the first load after a deploy put it, so a run
	 * can cross the site's midnight, and a day read again at each gate would apply half of one day's
	 * plan under the other day's rules, and name a tier the sponsor no longer holds on the trail.
	 *
	 * Then it asks the sponsor events' store to put its drain back on the clock if rows wait for it
	 * and no run is due (`WPCPM_Sponsor_Event::resume_drain()`): a drain whose event the clock lost
	 * would otherwise leave the people on an event untold until a move on another event, so they
	 * wait a day at most. Last, while the tiers are live, it asks the store to write down the day
	 * before's reminders for the drain to send (`WPCPM_Sponsor_Event::queue_reminders()`), handed the
	 * day read here: the run fires at the same hour every day, so the store's window, twelve to
	 * thirty-six hours after the run and reaching back to where the last one stopped when the run
	 * fired late, gives each event its reminder once, normally the day before it. Both are asked only
	 * while the store is loaded, as the draft is.
	 *
	 * @param string $today `Y-m-d`; '' for the site's today.
	 * @return array<string, int> `offers_woken`, `posts_dormant`, `posts_woken`, `mailed` and
	 *                            `drafted`, summed over the sponsors.
	 */
	public static function run( $today = '' ) {
		$today  = '' !== (string) $today ? (string) $today : WPCPM_Sponsor_Tiers::today();
		$totals = self::nothing_yet();

		foreach ( WPCPM_Sponsors_Index::approved() as $record => $row ) {
			$record = (string) $record;

			foreach ( self::apply( $record, $row, self::plan_for( $record, $row, $today ), $today ) as $key => $count ) {
				$totals[ $key ] += $count;
			}
		}

		if ( class_exists( 'WPCPM_Sponsor_Event' ) ) {
			WPCPM_Sponsor_Event::resume_drain();

			// A reminder is mail about a benefit the switch gates, so it waits for the switch, and it
			// is handed the day read above, as every other question of the run is.
			if ( WPCPM_Sponsor_Tiers::live() ) {
				WPCPM_Sponsor_Event::queue_reminders( $today );
			}
		}

		return $totals;
	}

	/**
	 * One sponsor's plan, before any gate: what its effective tier would put to dormant, what it
	 * would wake, and whether the thirty-day mail is due. Reads, never writes.
	 *
	 * Dormant is the one state the run put an offer in, so it is the one state it wakes an offer
	 * from: a paused, ended, waiting or draft offer is never the run's to bring back, and neither is
	 * a dormant one past its last day, which would be shown to nobody.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $today  `Y-m-d`; '' for the site's today.
	 * @return array `tier` (the effective tier), the ID lists `offers_wake`, `posts_dormant` and
	 *               `posts_wake`, in the order they are to be applied, and `mail`.
	 */
	public static function plan_for( $record, array $row, $today = '' ) {
		$tier = WPCPM_Sponsor_Tiers::effective( $row, $today );
		$plan = array(
			'tier'          => $tier,
			'offers_wake'   => array(),
			'posts_dormant' => array(),
			'posts_wake'    => array(),
			'mail'          => false,
		);

		// Offers: every dormant one wakes, the primary first, then the oldest, while it would be live
		// once woken. It is asked as if it were live, so the one date rule the offers card reads
		// decides it, not a second copy of that rule here.
		$dormant = array();

		foreach ( WPCPM_Sponsor_Offers::offers_of( $record ) as $offer ) {
			if ( WPCPM_Sponsor_Offers::STATE_DORMANT === $offer['state'] && WPCPM_Sponsor_Offers::is_live( array_merge( $offer, array( 'state' => WPCPM_Sponsor_Offers::STATE_LIVE ) ), $today ) ) {
				$dormant[] = $offer;
			}
		}

		usort(
			$dormant,
			static function ( array $a, array $b ) {
				if ( $a['primary'] !== $b['primary'] ) {
					return $a['primary'] ? -1 : 1;
				}

				return (int) $a['id'] - (int) $b['id'];
			}
		);

		$plan['offers_wake'] = array_map( 'intval', array_column( $dormant, 'id' ) );

		// Posts: the oldest published hold the slots, so the newest go dormant first; among the
		// dormant, the oldest wake first.
		$published = WPCPM_Sponsor_Posts::published_of( $record );
		$asleep    = WPCPM_Sponsor_Posts::dormant_of( $record );
		$by_date   = static function ( $a, $b ) {
			$by_day = strcmp( (string) $a->post_date, (string) $b->post_date );

			// Two posts that went live in the same second fall back on their IDs, as the offers do:
			// usort() is not stable before PHP 8, so a tie could otherwise land either way.
			return 0 !== $by_day ? $by_day : (int) $a->ID - (int) $b->ID;
		};

		usort( $published, $by_date );
		usort( $asleep, $by_date );

		$cap = WPCPM_Sponsor_Tiers::post_cap( $tier );

		if ( null !== $cap && count( $published ) > $cap ) {
			$plan['posts_dormant'] = array_map(
				static function ( $p ) {
					return (int) $p->ID;
				},
				array_slice( $published, $cap )
			);
		} elseif ( ! empty( $asleep ) ) {
			$room               = null === $cap ? count( $asleep ) : max( 0, $cap - count( $published ) );
			$plan['posts_wake'] = array_map(
				static function ( $p ) {
					return (int) $p->ID;
				},
				array_slice( $asleep, 0, $room )
			);
		}

		// The thirty-day mail, once per term: the stamp is the term it went out for. A term already
		// over is still due, so a day the run did not run does not lose the mail.
		$left         = WPCPM_Sponsor_Tiers::days_left( $row, $today );
		$plan['mail'] = null !== $left && $left <= WPCPM_Sponsor_Tiers::NOTICE_DAYS && (string) $row['tier_mailed_for'] !== (string) $row['tier_until'];

		return $plan;
	}

	/**
	 * One sponsor's plan applied, behind the gates `plan_for()` leaves out: the dormant lists only
	 * while the launch switch is on and the grace date has passed, the wake lists while the switch
	 * is on, the mail always, and the draft always too, whatever the switch says.
	 *
	 * Every move is asked of the class that owns the offer or the post, which answers whether it
	 * took, and only a move that took is counted: a plan read a moment ago can be stale by the
	 * time it is applied. When anything moved, one audit row names the tier and the counts; a run
	 * that moved nothing for a sponsor leaves no row.
	 *
	 * The mail is stamped with the term it went out for, and only once somebody was mailed: a day
	 * with nobody reachable is tried again the next day, and a term moved to a new day is a new
	 * term, mailed again.
	 *
	 * The draft comes last, asked of WPCPM_Sponsor_Report once the mail has gone: a term that ended
	 * is a fact of the sponsor's record, not a benefit the switch gates, so the draft is attempted
	 * whether or not the switch is on, while the report itself stays a manager's draft, seen only on
	 * the manager surfaces the reports module already restricts it to. The class is optional, so the
	 * call sits behind class_exists(): a build without the reports module still runs the rest of the
	 * ladder. maybe_draft() writes its own audit row when it drafts one, so this method's own row,
	 * above, keeps naming only what it moved; the same fact in two rows would help nobody reading
	 * the trail.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param array  $plan   From `plan_for()`.
	 * @param string $today  `Y-m-d`; '' for the site's today.
	 * @return array<string, int> The counts `run()` answers, for this sponsor alone, `drafted`
	 *                            included.
	 */
	public static function apply( $record, array $row, array $plan, $today = '' ) {
		$counts = self::nothing_yet();
		$moved  = array(
			'offers_woken'  => array(),
			'posts_dormant' => array(),
			'posts_woken'   => array(),
		);

		if ( WPCPM_Sponsor_Tiers::live() ) {
			// Nothing goes dormant on or before the grace date, even with the switch on: managers
			// set the tiers one by one until then. Waking needs the switch alone.
			if ( ! WPCPM_Sponsor_Tiers::in_grace( $today ) ) {
				foreach ( $plan['posts_dormant'] as $post_id ) {
					if ( WPCPM_Sponsor_Posts::set_dormant( $post_id, $today ) ) {
						$moved['posts_dormant'][] = (int) $post_id;
					}
				}
			}

			foreach ( $plan['offers_wake'] as $offer_id ) {
				if ( WPCPM_Sponsor_Offers::wake( $offer_id ) ) {
					$moved['offers_woken'][] = (int) $offer_id;
				}
			}

			foreach ( $plan['posts_wake'] as $post_id ) {
				if ( WPCPM_Sponsor_Posts::wake( $post_id ) ) {
					$moved['posts_woken'][] = (int) $post_id;
				}
			}
		}

		foreach ( $moved as $key => $ids ) {
			$counts[ $key ] = count( $ids );
		}

		if ( array_sum( $counts ) > 0 ) {
			// Each count in its own words, so a count of one reads as one: the same pairs the Sponsors
			// screen's preview prints, so the translation template holds them once.
			/* translators: %d: how many posts the daily run set dormant. */
			$posts_dormant = sprintf( _n( '%d post', '%d posts', $counts['posts_dormant'], 'wpcredits-program-manager' ), $counts['posts_dormant'] );
			/* translators: %d: how many offers the daily run woke. */
			$offers_woken = sprintf( _n( '%d offer', '%d offers', $counts['offers_woken'], 'wpcredits-program-manager' ), $counts['offers_woken'] );
			/* translators: %d: how many posts the daily run woke. */
			$posts_woken = sprintf( _n( '%d post', '%d posts', $counts['posts_woken'], 'wpcredits-program-manager' ), $counts['posts_woken'] );

			WPCPM_Institution_Audit::record_sponsor(
				array(
					'kind'     => self::LOG_KIND,
					'sponsor'  => $record,
					'subject'  => $record,
					'actor'    => 0,
					'ground'   => WPCPM_Institution_Audit::GROUND_SYSTEM,
					'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'  => sprintf(
						/* translators: 1: the sponsor's effective tier, 2: the posts set dormant, a phrase like 1 post, 3: the offers woken, a phrase like 2 offers, 4: the posts woken, a phrase like 1 post. */
						__( 'The daily run applied the %1$s tier: %2$s set dormant, %3$s and %4$s woken.', 'wpcredits-program-manager' ),
						WPCPM_Sponsor_Tiers::label( $plan['tier'] ),
						$posts_dormant,
						$offers_woken,
						$posts_woken
					),
					'data'     => array(
						'counts' => array_intersect_key( $counts, $moved ),
						'ids'    => $moved,
					),
				)
			);
		}

		if ( ! empty( $plan['mail'] ) && self::mail_term_end( $record, $row, $today ) > 0 ) {
			WPCPM_Sponsors_Index::patch( $record, array( 'tier_mailed_for' => (string) $row['tier_until'] ) );
			$counts['mailed'] = 1;
		}

		// A term that ended is a fact of the record, not a benefit the switch gates, so the draft is
		// attempted whatever the switch says. maybe_draft() writes its own audit row when it drafts
		// one, so the row above, which already names what this run moved, is left as it is: the same
		// fact in two rows would help nobody reading the trail.
		if ( class_exists( 'WPCPM_Sponsor_Report' ) && WPCPM_Sponsor_Report::maybe_draft( $record, $row, $today ) ) {
			$counts['drafted'] = 1;
		}

		return $counts;
	}

	/**
	 * What the daily run would put to dormant on the day after the grace date, for the Sponsors
	 * screen: every Approved sponsor planned for that day, and nothing applied.
	 * The plan is read on that day rather than today, because the paragraph says what happens
	 * then: a sponsor whose term ends before it already counts as a Friend. Only posts go dormant,
	 * since no tier caps live offers. The daily run itself plans with today; nothing here reads
	 * today at all, so this takes no today of its own.
	 *
	 * @return array `day`, the day after the grace date as `Y-m-d`; `live`, the launch switch;
	 *               `sponsors`, key to `name`, `tier` (the effective tier's label) and `posts`, for
	 *               the sponsors with something to go dormant alone; and the total `posts`.
	 */
	public static function preview() {
		// A pure sum on a calendar day: the grace date read at midnight UTC and written back in UTC,
		// so no timezone moves it. That is why gmdate() is right here, and wrong wherever the
		// question is what today is.
		$day     = gmdate( 'Y-m-d', strtotime( WPCPM_Sponsor_Tiers::grace_until() . ' 00:00:00 UTC' ) + DAY_IN_SECONDS );
		$preview = array(
			'day'      => $day,
			'live'     => WPCPM_Sponsor_Tiers::live(),
			'sponsors' => array(),
			'posts'    => 0,
		);

		foreach ( WPCPM_Sponsors_Index::approved() as $record => $row ) {
			$record = (string) $record;
			$plan   = self::plan_for( $record, $row, $day );

			if ( empty( $plan['posts_dormant'] ) ) {
				continue;
			}

			$preview['sponsors'][ $record ] = array(
				'name'  => self::name_of( $record, $row ),
				'tier'  => WPCPM_Sponsor_Tiers::label( $plan['tier'] ),
				'posts' => count( $plan['posts_dormant'] ),
			);

			$preview['posts'] += count( $plan['posts_dormant'] );
		}

		return $preview;
	}

	/**
	 * The thirty-day mail, down the road every mail to a sponsor's manager takes,
	 * `WPCPM_Sponsor_Interests::mail_manager()`: the assigned manager, else the Interest mail
	 * addresses, else every manager.
	 *
	 * It names the recorded tier, the term that ends, never the plan's effective one, which is
	 * already a Friend once the term is over. It goes out whether or not the tiers are
	 * switched on, and the run puts nothing to dormant while they are off (`apply()`), so it says
	 * the dormant step waits for the switch as well as for the grace date. A term already over
	 * when the mail goes, as a run that was off for a while can find one, is said to have ended
	 * rather than to end on a day gone by: two templates, `sponsor-term-ending` and
	 * `sponsor-term-ended`, in the Sponsors area of the registry, whose subject names the site, as
	 * every Sponsors email's does (`WPCPM_Mail::site_name()`). Its link opens the sponsor's Edit
	 * form on the Sponsors screen, where a renewal moves the day (`WPCPM_Sponsors::edit_url()`). The
	 * tier's label and the day are read inside the builder, because `WPCPM_Mail` switches to the
	 * recipient's language around it.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row, holding the term that ends.
	 * @param string $today  `Y-m-d`; '' for the site's today.
	 * @return int How many were mailed.
	 */
	private static function mail_term_end( $record, array $row, $today = '' ) {
		$name  = self::name_of( $record, $row );
		$tier  = (string) $row['tier'];
		$until = (string) $row['tier_until'];
		$left  = WPCPM_Sponsor_Tiers::days_left( $row, $today );
		$over  = null !== $left && $left < 0;
		$url   = WPCPM_Sponsors::edit_url( $record );
		$id    = $over ? 'sponsor-term-ended' : 'sponsor-term-ending';
		$build = static function () use ( $id, $name, $tier, $until, $url ) {
			return WPCPM_Mail_Templates::mail(
				$id,
				array(
					'site_name'         => WPCPM_Mail::site_name(),
					'company_name'      => $name,
					'tier'              => WPCPM_Sponsor_Tiers::label( $tier ),
					'term_end'          => WPCPM_Sponsor_Tiers::format_day( $until ),
					'sponsor_edit_link' => $url,
				)
			);
		};

		return (int) WPCPM_Sponsor_Interests::mail_manager( $record, $build, $id );
	}

	/**
	 * The counts `run()` and `apply()` answer, before anything has moved.
	 *
	 * @return array<string, int>
	 */
	private static function nothing_yet() {
		return array(
			'offers_woken'  => 0,
			'posts_dormant' => 0,
			'posts_woken'   => 0,
			'mailed'        => 0,
			'drafted'       => 0,
		);
	}

	/**
	 * A sponsor's name as the mail and the preview print it: the row's, trimmed, or the key when
	 * the row has none, as the Sponsors screen's own index does.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @return string
	 */
	private static function name_of( $record, array $row ) {
		$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' === $name ? (string) $record : $name;
	}
}
