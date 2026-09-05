<?php
/**
 * Who took what: a claim under a lock, recorded on the person; problems, voids, the counts.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sponsors design spec of 4 September 2026, sections 6.2 to 6.4 and 6.6.
 *
 * A claim is recorded twice: on the person, in `wpcpm_claims` (offer ID to the code's index and
 * the time), which is the record of what is theirs, and in the offer's ledger
 * (WPCPM_Sponsor_Codes), which is what the sponsor's counts and the manager's claimant list
 * read. Both are written under the pool's lock, taken here for the claim: the same
 * `add_option()` test-and-set per offer (WPCPM_Sponsor_Codes::lock()) that the report
 * generation and the syncs already use, with a stale takeover after LOCK_TIMEOUT.
 *
 * Whether a person may claim is WPCPM_Sponsor_Tools::may_claim(), decided on the claimant's own
 * account: a claimant is never a member of the sponsor, so the sponsor policy is not asked.
 * Nothing about a claim reaches Airtable, and nothing here sends a code by mail: the manager's
 * problem mail carries the last four characters, the low-stock mail carries a count.
 */
final class WPCPM_Sponsor_Claims {

	/** User meta: offer ID to `array( 'i' => index or -1, 'at' => time )`. */
	const META_CLAIMS = 'wpcpm_claims';

	const LOCK_PREFIX  = WPCPM_Sponsor_Codes::LOCK_PREFIX;
	const LOCK_TIMEOUT = WPCPM_Sponsor_Codes::LOCK_TIMEOUT;

	const PROBLEMS_PER_DAY = 3;
	const CEILING_PROBLEM  = 'claim-problem';

	const MAIL_LOW_STOCK = 'offer-low-stock';
	const MAIL_PROBLEM   = 'claim-problem';

	const LOG_PROBLEM = 'claim_problem';
	const LOG_VOIDED  = 'claim_voided';

	/** The usage series is this many months, the current one last. */
	const MONTHS = 12;

	/**
	 * What one person has claimed, across every offer.
	 *
	 * @param int $user_id The person.
	 * @return array Offer ID to `i`, `at`.
	 */
	public static function claims_of( $user_id ) {
		$claims = get_user_meta( (int) $user_id, self::META_CLAIMS, true );

		return is_array( $claims ) ? $claims : array();
	}

	/**
	 * Whether a person already holds a claim on an offer.
	 *
	 * @param int $user_id  The person.
	 * @param int $offer_id Offer post ID.
	 * @return bool
	 */
	public static function has_claimed( $user_id, $offer_id ) {
		return isset( self::claims_of( $user_id )[ (int) $offer_id ] );
	}

	/**
	 * How many tools a person holds, for the one muted line a manager sees on their card.
	 *
	 * @param int $user_id The person.
	 * @return int
	 */
	public static function count_for_user( $user_id ) {
		return count( self::claims_of( $user_id ) );
	}

	/**
	 * Claim an offer for a person.
	 *
	 * The order: theirs already, then whether they may, then the lock, then (under it) the
	 * lookup again, the code, the record on the person, the release. The code before the
	 * person because a record with no code behind it would need undoing (plan ruling 4); the
	 * lookup before the lock because a second press must return the same code, not queue.
	 *
	 * @param int              $offer_id Offer post ID.
	 * @param int|WP_User|null $user     The claimant.
	 * @return array|WP_Error `code`, `index`, `new`.
	 */
	public static function claim( $offer_id, $user ) {
		$user  = WPCPM_Roles::resolve_user( $user );
		$offer = WPCPM_Sponsor_Offers::read( (int) $offer_id );

		if ( ! $user instanceof WP_User || ! $user->exists() || null === $offer ) {
			return self::refusal();
		}

		$claims = self::claims_of( $user->ID );

		if ( isset( $claims[ $offer['id'] ] ) ) {
			return array(
				'code'  => self::code_for( $user->ID, $offer ),
				'index' => (int) $claims[ $offer['id'] ]['i'],
				'new'   => false,
			);
		}

		// The reason, not the yes or no: a pool that emptied since the page was drawn is not a
		// refusal about this person, and saying so is the difference between "try again" and
		// "your account may not" (finding 7).
		$why = WPCPM_Sponsor_Tools::may_claim_reason( $user, $offer );

		if ( 'empty' === $why ) {
			return self::empty_pool();
		}

		if ( '' !== $why ) {
			return self::refusal();
		}

		if ( ! WPCPM_Sponsor_Codes::lock( $offer['id'] ) ) {
			return new WP_Error( 'wpcpm_claim_busy', __( 'Another claim was going through. Reload the page to see your code.', 'wpcredits-program-manager' ) );
		}

		// Under the lock, once more: two presses inside a second both passed the lookup above.
		$claims = self::claims_of( $user->ID );

		if ( isset( $claims[ $offer['id'] ] ) ) {
			WPCPM_Sponsor_Codes::unlock( $offer['id'] );

			return array(
				'code'  => self::code_for( $user->ID, $offer ),
				'index' => (int) $claims[ $offer['id'] ]['i'],
				'new'   => false,
			);
		}

		if ( WPCPM_Sponsor_Offers::KIND_SHARED === $offer['kind'] ) {
			WPCPM_Sponsor_Codes::record_shared( $offer['id'], $user->ID );
			$index = WPCPM_Sponsor_Codes::SHARED_INDEX;
		} else {
			$index = WPCPM_Sponsor_Codes::take( $offer['id'], $user->ID );

			if ( is_wp_error( $index ) ) {
				WPCPM_Sponsor_Codes::unlock( $offer['id'] );

				return self::empty_pool();
			}
		}

		self::remember( $user->ID, $offer['id'], $index );
		WPCPM_Sponsor_Codes::unlock( $offer['id'] );

		// After the release: the mail is the slow part, and nothing about it needs the lock.
		self::maybe_low_stock( WPCPM_Sponsor_Offers::read( $offer['id'] ) );

		return array(
			'code'  => WPCPM_Sponsor_Codes::SHARED_INDEX === $index ? WPCPM_Sponsor_Codes::shared( $offer['id'] ) : WPCPM_Sponsor_Codes::code_at( $offer['id'], $index ),
			'index' => (int) $index,
			'new'   => true,
		);
	}

	/**
	 * The one refusal a claimant sees for every "no" that is about them.
	 *
	 * @return WP_Error
	 */
	private static function refusal() {
		return new WP_Error( 'wpcpm_claim_refused', __( 'That offer is not open to your account.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The answer for a pool with nothing left, which is not a refusal about the person.
	 *
	 * @return WP_Error
	 */
	private static function empty_pool() {
		return new WP_Error( 'wpcpm_claim_empty', __( 'This offer has run out of codes. The sponsor has been told.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The person's code for an offer, unsealed; '' when they hold none.
	 *
	 * @param int   $user_id The person.
	 * @param array $offer   The offer.
	 * @return string
	 */
	public static function code_for( $user_id, array $offer ) {
		$claims = self::claims_of( $user_id );

		if ( ! isset( $claims[ $offer['id'] ] ) ) {
			return '';
		}

		$index = (int) $claims[ $offer['id'] ]['i'];

		return WPCPM_Sponsor_Codes::SHARED_INDEX === $index ? WPCPM_Sponsor_Codes::shared( $offer['id'] ) : WPCPM_Sponsor_Codes::code_at( $offer['id'], $index );
	}

	/**
	 * Record a claim on the person's own copy, beside the offer's ledger in WPCPM_Sponsor_Codes.
	 *
	 * @param int $user_id  The person.
	 * @param int $offer_id Offer post ID.
	 * @param int $index    The code's index, or SHARED_INDEX.
	 */
	private static function remember( $user_id, $offer_id, $index ) {
		$claims                    = self::claims_of( $user_id );
		$claims[ (int) $offer_id ] = array(
			'i'  => (int) $index,
			'at' => time(),
		);
		update_user_meta( (int) $user_id, self::META_CLAIMS, $claims );
	}

	/**
	 * Remove a claim from the person's own copy, so they may claim again.
	 *
	 * @param int $user_id  The person.
	 * @param int $offer_id Offer post ID.
	 */
	private static function forget( $user_id, $offer_id ) {
		$claims = self::claims_of( $user_id );
		unset( $claims[ (int) $offer_id ] );
		update_user_meta( (int) $user_id, self::META_CLAIMS, $claims );
	}

	/**
	 * A claimant says their code did not work: the sponsor's manager is mailed the name, the
	 * offer and the code's last four characters, and the report is logged. Three a day per
	 * person, because the sheet's notes column was exactly this conversation and it needs a
	 * ceiling before it needs anything else.
	 *
	 * @param int              $offer_id Offer post ID.
	 * @param int|WP_User|null $user     The claimant.
	 * @return array|WP_Error `mailed` (how many).
	 */
	public static function report_problem( $offer_id, $user ) {
		$user  = WPCPM_Roles::resolve_user( $user );
		$offer = WPCPM_Sponsor_Offers::read( (int) $offer_id );

		if ( ! $user instanceof WP_User || ! $user->exists() || null === $offer || ! self::has_claimed( $user->ID, $offer['id'] ) ) {
			return new WP_Error( 'wpcpm_problem_refused', __( 'You have not claimed that offer.', 'wpcredits-program-manager' ) );
		}

		if ( ! WPCPM_Ceiling::claim( WPCPM_Ceiling::key( self::CEILING_PROBLEM, (string) $user->ID ), self::PROBLEMS_PER_DAY, DAY_IN_SECONDS ) ) {
			return new WP_Error( 'wpcpm_problem_limit', __( 'You have reported three problems today. Try again tomorrow, or write to your program contact.', 'wpcredits-program-manager' ) );
		}

		$last  = mb_substr( self::code_for( $user->ID, $offer ), -4 );
		$build = static function ( $recipient ) use ( $user, $offer, $last ) {
			return array(
				/* translators: 1: site name, 2: offer title. */
				'subject' => sprintf( __( '[%1$s] A code from %2$s did not work', 'wpcredits-program-manager' ), WPCPM_Mail::site_name(), $offer['title'] ),
				'body'    => sprintf(
					/* translators: 1: the claimant's name, 2: offer title, 3: the code's last four characters, 4: the Sponsors screen's address. */
					__( "%1\$s reports that their code for \"%2\$s\" (ending %3\$s) did not work.\n\nThe sponsor's offers and the people who claimed from them are on the Sponsors screen: %4\$s\n\nReply to this person from your own mailbox. The site never sends a code by mail.", 'wpcredits-program-manager' ),
					$user->display_name,
					$offer['title'],
					$last,
					admin_url( 'admin.php?page=wpcpm-sponsors' )
				),
				'headers' => WPCPM_Mail::reply_to( $user ),
			);
		};

		$mailed = (int) WPCPM_Sponsor_Interests::mail_manager( $offer['sponsor'], $build, self::MAIL_PROBLEM );

		// Ground `system`: the log knows manager, member and system, and a claimant is neither
		// of the first two (plan ruling 11). The claimant is the subject, so the row says who.
		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_PROBLEM,
				'sponsor'  => $offer['sponsor'],
				'subject'  => (string) $user->ID,
				'actor'    => $user->ID,
				'ground'   => WPCPM_Institution_Audit::GROUND_SYSTEM,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => sprintf(
					/* translators: 1: offer title, 2: the code's last four characters, 3: mails sent. */
					__( 'A claimant reported a problem with their code for "%1$s" (ending %2$s); %3$d mail sent.', 'wpcredits-program-manager' ),
					$offer['title'],
					$last,
					$mailed
				),
				'data'     => array(
					'offer'  => $offer['id'],
					'mailed' => $mailed,
				),
			)
		);

		return array( 'mailed' => $mailed );
	}

	/**
	 * A manager voids a person's claim: the code stays void for the count, the person may
	 * claim again.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $user_id  The person.
	 * @param int $actor_id The manager.
	 * @return bool|WP_Error Whether there was a claim to void.
	 */
	public static function void_claim( $offer_id, $user_id, $actor_id ) {
		$offer  = WPCPM_Sponsor_Offers::read( (int) $offer_id );
		$claims = self::claims_of( $user_id );

		if ( null === $offer || ! isset( $claims[ $offer['id'] ] ) ) {
			return false;
		}

		$index = (int) $claims[ $offer['id'] ]['i'];

		if ( WPCPM_Sponsor_Codes::SHARED_INDEX === $index ) {
			$voided = WPCPM_Sponsor_Codes::void_shared_claim( $offer['id'], $user_id );
		} else {
			$voided = WPCPM_Sponsor_Codes::void_index( $offer['id'], $index );
		}

		if ( is_wp_error( $voided ) ) {
			return $voided;
		}

		self::forget( $user_id, $offer['id'] );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_VOIDED,
				'sponsor'  => $offer['sponsor'],
				'subject'  => (string) (int) $user_id,
				'actor'    => (int) $actor_id,
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				/* translators: %s: offer title. */
				'message'  => sprintf( __( 'A claimed code for "%s" was voided; the person may claim again.', 'wpcredits-program-manager' ), $offer['title'] ),
				'data'     => array(
					'offer' => $offer['id'],
					'index' => $index,
				),
			)
		);

		return true;
	}

	/**
	 * Who claimed from an offer, for a manager (decision 7: managers read names). Claims that
	 * were voided are not listed; the count keeps them.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return array Rows: `user_id`, `name`, `email`, `at`, `last4`, `index`.
	 */
	public static function claimants( $offer_id ) {
		$offer = WPCPM_Sponsor_Offers::read( (int) $offer_id );

		if ( null === $offer ) {
			return array();
		}

		$rows = array();

		foreach ( WPCPM_Sponsor_Codes::claims( $offer['id'] ) as $claim ) {
			if ( ! empty( $claim['v'] ) ) {
				continue;
			}

			$user  = get_user_by( 'id', (int) $claim['u'] );
			$index = (int) $claim['i'];
			$code  = WPCPM_Sponsor_Codes::SHARED_INDEX === $index ? WPCPM_Sponsor_Codes::shared( $offer['id'] ) : WPCPM_Sponsor_Codes::code_at( $offer['id'], $index );

			$rows[] = array(
				'user_id' => (int) $claim['u'],
				'name'    => $user instanceof WP_User ? $user->display_name : '',
				'email'   => $user instanceof WP_User ? $user->user_email : '',
				'at'      => (int) $claim['at'],
				'last4'   => mb_substr( $code, -4 ),
				'index'   => $index,
			);
		}

		return $rows;
	}

	/**
	 * The twelve `Y-m` keys ending with the current month.
	 *
	 * @param int $now A timestamp; 0 for now.
	 * @return string[]
	 */
	public static function month_keys( $now = 0 ) {
		$now   = $now > 0 ? (int) $now : time();
		$year  = (int) wp_date( 'Y', $now );
		$month = (int) wp_date( 'n', $now );
		$keys  = array();

		for ( $i = self::MONTHS - 1; $i >= 0; $i-- ) {
			$m = $month - $i;
			$y = $year;

			while ( $m < 1 ) {
				$m += 12;
				--$y;
			}

			$keys[] = sprintf( '%04d-%02d', $y, $m );
		}

		return $keys;
	}

	/**
	 * Counts over time and offer, and nothing else (decision 7). No institution, country,
	 * track or cohort; no list of anything; no name and no address, which
	 * bin/test-sponsor-offers.php walks the result to prove.
	 *
	 * @param string $record Sponsor record ID.
	 * @return array `months`, `offers` (ID to title, kind, state, total, month, series, available, claimed, void), `totals`.
	 */
	public static function stats( $record ) {
		$keys    = self::month_keys();
		$current = end( $keys );
		$out     = array(
			'months' => $keys,
			'offers' => array(),
			'totals' => array(
				'total'     => 0,
				'month'     => 0,
				'available' => 0,
				'claimed'   => 0,
				'void'      => 0,
			),
		);

		foreach ( WPCPM_Sponsor_Offers::offers_of( $record ) as $offer ) {
			$series = array_fill_keys( $keys, 0 );
			$total  = 0;
			$month  = 0;

			foreach ( WPCPM_Sponsor_Codes::claims( $offer['id'] ) as $claim ) {
				// A voided claim is counted under the pool's `void`, not as a claim that stands.
				if ( ! empty( $claim['v'] ) ) {
					continue;
				}

				++$total;
				$key = wp_date( 'Y-m', (int) $claim['at'] );

				if ( isset( $series[ $key ] ) ) {
					++$series[ $key ];
				}

				if ( $key === $current ) {
					++$month;
				}
			}

			$counts = WPCPM_Sponsor_Codes::counts( $offer['id'] );

			$out['offers'][ $offer['id'] ] = array(
				'title'     => $offer['title'],
				'kind'      => $offer['kind'],
				'state'     => $offer['state'],
				'total'     => $total,
				'month'     => $month,
				'series'    => $series,
				'available' => $counts['available'],
				'claimed'   => $counts['claimed'],
				'void'      => $counts['void'],
			);

			$out['totals']['total']     += $total;
			$out['totals']['month']     += $month;
			$out['totals']['available'] += $counts['available'];
			$out['totals']['claimed']   += $counts['claimed'];
			$out['totals']['void']      += $counts['void'];
		}

		return $out;
	}

	/**
	 * The same numbers as a CSV, through the neutralised writer the institution exports use.
	 *
	 * @param array $stats stats()'s answer.
	 * @return string
	 */
	public static function csv( array $stats ) {
		$head = array_merge(
			array(
				__( 'Offer', 'wpcredits-program-manager' ),
				__( 'State', 'wpcredits-program-manager' ),
				__( 'Claims, total', 'wpcredits-program-manager' ),
				__( 'Claims, this month', 'wpcredits-program-manager' ),
				__( 'Codes available', 'wpcredits-program-manager' ),
				__( 'Codes claimed', 'wpcredits-program-manager' ),
				__( 'Codes void', 'wpcredits-program-manager' ),
			),
			$stats['months']
		);

		$matrix = array( $head );

		foreach ( $stats['offers'] as $offer ) {
			$matrix[] = array_merge(
				array( $offer['title'], $offer['state'], $offer['total'], $offer['month'], $offer['available'], $offer['claimed'], $offer['void'] ),
				array_values( $offer['series'] )
			);
		}

		$t        = $stats['totals'];
		$matrix[] = array_merge(
			array( __( 'All offers', 'wpcredits-program-manager' ), '', $t['total'], $t['month'], $t['available'], $t['claimed'], $t['void'] ),
			array_fill( 0, count( $stats['months'] ), '' )
		);

		return WPCPM_Institution_Export::csv( $matrix );
	}

	/**
	 * After a claim from a pool: below the threshold, one mail to the sponsor's accounts and
	 * one to the assigned manager, once per crossing (spec 6.6). Adding codes re-arms it.
	 *
	 * @param array|null $offer The offer, read after the claim.
	 * @return int|false How many mails, or false when there was nothing to say.
	 */
	public static function maybe_low_stock( $offer ) {
		if ( ! is_array( $offer ) || WPCPM_Sponsor_Offers::KIND_CODES !== $offer['kind'] ) {
			return false;
		}

		$counts = WPCPM_Sponsor_Codes::counts( $offer['id'] );

		if ( $counts['available'] >= (int) $offer['low'] || (int) $offer['low_sent'] > 0 ) {
			return false;
		}

		$available = (int) $counts['available'];
		// The same address for everyone: a member's page ignores the switcher argument, and
		// a manager's needs it to land on this sponsor.
		$link  = add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $offer['sponsor'], WPCPM_Sponsors_Dashboard::page_url() ) . '#wpcpm-sponsor-offers';
		$build = static function ( $recipient ) use ( $offer, $available, $link ) {
			return array(
				/* translators: 1: site name, 2: offer title, 3: codes left. */
				'subject' => sprintf( __( '[%1$s] %2$s: %3$d codes left', 'wpcredits-program-manager' ), WPCPM_Mail::site_name(), $offer['title'], $available ),
				'body'    => sprintf(
					/* translators: 1: offer title, 2: codes left, 3: the threshold, 4: the Offers card's address. */
					__( "The offer \"%1\$s\" has %2\$d codes left; it warns below %3\$d.\n\nAdd more codes, or pause the offer, from the Offers card: %4\$s\n\nThis is sent once. Adding codes arms it again.", 'wpcredits-program-manager' ),
					$offer['title'],
					$available,
					(int) $offer['low'],
					$link
				),
			);
		};

		$sent = 0;

		foreach ( WPCPM_Sponsor_Members::members_of( $offer['sponsor'] ) as $member ) {
			if ( $member instanceof WP_User && WPCPM_Mail::send( $member, self::MAIL_LOW_STOCK, $build ) ) {
				++$sent;
			}
		}

		$sent += (int) WPCPM_Sponsor_Interests::mail_manager( $offer['sponsor'], $build, self::MAIL_LOW_STOCK );

		update_post_meta( $offer['id'], WPCPM_Sponsor_Offers::META_LOW_SENT, time() );

		return $sent;
	}

	/**
	 * Uninstall: the claims on every account. The offers, their pools and the pools' locks are
	 * WPCPM_Sponsor_Offers::delete_all()'s, which runs first and leaves no offer to iterate;
	 * the accounts are not the plugin's.
	 */
	public static function delete_all() {
		delete_metadata( 'user', 0, self::META_CLAIMS, '', true );
	}
}
