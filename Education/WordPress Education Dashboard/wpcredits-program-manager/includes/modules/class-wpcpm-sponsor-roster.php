<?php
/**
 * Which sponsor a request is about, and the metered claim.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place a record ID arriving in a request becomes an acting sponsor.
 *
 * Design spec of 4 September 2026, section 4: for a member the stamp wins and the argument
 * is ignored; for a manager the argument is checked against the index; every refusal of a
 * member is metered by `WPCPM_Refusal_Meter` in the `sponsor` scope, twenty a day, then the
 * account is locked until tomorrow and the lock is logged once on the sponsor's own log.
 * A manager is never metered: their refusals are the site's mistakes to fix.
 */
final class WPCPM_Sponsor_Roster {

	/** The query argument the manager switcher posts. Read with `text()`, never `key()`: record IDs are case-sensitive. */
	const ARG_VIEW = 'wpcpm_sponsor_view';

	/** The meter's scope: its keys are `sponsor-refused` and `sponsor-locked`. */
	const METER_SCOPE = 'sponsor';

	/** Audit kind of the once-a-day lock row. */
	const LOG_LOCKED = 'claim_locked';

	/**
	 * Turn a record ID from a request into an acting sponsor, or refuse.
	 *
	 * @param string           $record The record ID the request names; ignored for a member.
	 * @param string           $action A `WPCPM_Sponsor_Policy::ACT_*` constant.
	 * @param int|WP_User|null $user   The acting user; null for the current one.
	 * @return array|WP_Error `record`, `decision` (the policy's array) and `row` (the index row or null), or the one refusal.
	 */
	public static function claim( $record, $action, $user = null ) {
		$actor = WPCPM_Roles::resolve_user( $user );

		// An account that spent today's refusals is refused before anything is read, and
		// this refusal is not counted: a locked account costs nothing more to refuse.
		if ( WPCPM_Refusal_Meter::is_locked( self::METER_SCOPE, $actor ) ) {
			return WPCPM_Sponsor_Policy::refusal();
		}

		$own = WPCPM_Sponsor_Members::sponsor_of( $actor );

		if ( '' !== $own ) {
			// The stamp wins. A member cannot reach another sponsor by naming it, and is not
			// refused for trying: the argument is simply not read. sponsor_of() only checks
			// the stamp's shape and the active flag, never the index, so a member whose
			// sponsor has left the index still claims here, with row() null below; every
			// caller refuses on that rather than writing back to nothing.
			$record = $own;
		} else {
			$record = trim( (string) $record );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! WPCPM_Sponsors_Index::has( $record ) ) {
				self::meter( $actor );

				return WPCPM_Sponsor_Policy::refusal();
			}
		}

		$decision = WPCPM_Sponsor_Policy::decide( $action, WPCPM_Sponsor_Policy::subject_sponsor( $record ), $actor );

		if ( empty( $decision['allowed'] ) ) {
			self::meter( $actor );

			return WPCPM_Sponsor_Policy::refusal();
		}

		return array(
			'record'   => $record,
			'decision' => $decision,
			'row'      => WPCPM_Sponsors_Index::row( $record ),
		);
	}

	/**
	 * The sponsor a page is about: the manager's choice, else the viewer's own, else the
	 * first sponsor with a live account (managers only), else ''.
	 *
	 * The caller's flag is re-checked rather than trusted, as the institution roster does: a
	 * stale `true` handed one level too far is exactly how a switcher ends up in a member's page.
	 *
	 * @param int|WP_User|null $viewer     The person looking; null for the current user.
	 * @param bool             $can_manage Whether the caller has already established `CAP_MANAGE`.
	 * @return string Airtable record ID, or ''.
	 */
	public static function resolve_sponsor( $viewer, $can_manage ) {
		$viewer = WPCPM_Roles::resolve_user( $viewer );

		if ( ! $viewer instanceof WP_User || ! $viewer->exists() ) {
			return '';
		}

		$can_manage = $can_manage && user_can( $viewer, WPCPM_Roles::CAP_MANAGE );

		if ( $can_manage ) {
			$asked = self::requested_view();

			if ( WPCPM_Mentors_Sync::is_record_id( $asked ) && WPCPM_Sponsors_Index::has( $asked ) ) {
				return $asked;
			}
		}

		$own = WPCPM_Sponsor_Members::sponsor_of( $viewer );

		if ( '' !== $own ) {
			return $own;
		}

		if ( ! $can_manage ) {
			return '';
		}

		return self::first_with_member();
	}

	/**
	 * The record ID the request names, from the query string or the posted form.
	 *
	 * @return string
	 */
	public static function requested_view() {
		$asked = WPCPM_Request::text( self::ARG_VIEW );

		if ( '' === $asked ) {
			$asked = WPCPM_Request::posted_text( self::ARG_VIEW );
		}

		return trim( (string) $asked );
	}

	/**
	 * The sponsors the manager switcher offers, in index order: every row, one entry each.
	 *
	 * Provisioning a sponsor is done by looking at it first, so a sponsor with no account yet
	 * is precisely the one a manager needs to open. A nameless record is listed by its ID.
	 *
	 * @return array<string, string> Record ID to label.
	 */
	public static function switcher_options() {
		$options = array();

		foreach ( WPCPM_Sponsors_Index::rows() as $record_id => $row ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$name = trim( isset( $row['name'] ) ? (string) $row['name'] : '' );

			$options[ (string) $record_id ] = '' === $name ? (string) $record_id : $name;
		}

		return $options;
	}

	/**
	 * The sponsor accounts whose write path is locked for the rest of today.
	 *
	 * @return WP_User[]
	 */
	public static function locked_today() {
		return WPCPM_Refusal_Meter::locked_among( self::METER_SCOPE, WPCPM_Sponsor_Members::live_accounts() );
	}

	/**
	 * Count one refusal, and log the lock the one time it happens.
	 *
	 * @param WP_User|null $actor The acting account.
	 */
	private static function meter( $actor ) {
		if ( WPCPM_Refusal_Meter::refuse( self::METER_SCOPE, $actor ) ) {
			self::log_lock( $actor );
		}
	}

	/**
	 * Record that an account's write path is locked for the rest of the day, on the log of the
	 * sponsor it acts for. An actor with no membership has no log to write on.
	 *
	 * @param WP_User $actor The acting account.
	 */
	private static function log_lock( WP_User $actor ) {
		$sponsor = WPCPM_Sponsor_Members::sponsor_of( $actor );

		if ( '' === $sponsor ) {
			return;
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_LOCKED,
				'sponsor'  => $sponsor,
				'subject'  => (string) $actor->ID,
				'actor'    => $actor->ID,
				'ground'   => WPCPM_Institution_Audit::GROUND_MEMBER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'  => sprintf(
					/* translators: %d: the number of refused claims that fills a day's ceiling. */
					__( 'This account was refused %d requests today, on the shape of the request or on what the site holds, and cannot change anything again until tomorrow.', 'wpcredits-program-manager' ),
					WPCPM_Refusal_Meter::PER_DAY
				),
				'data'     => array(
					'refusals' => WPCPM_Refusal_Meter::PER_DAY,
					'window'   => DAY_IN_SECONDS,
				),
			)
		);
	}

	/**
	 * The first sponsor in index order that has a live account, or ''.
	 *
	 * @return string
	 */
	private static function first_with_member() {
		$live = array();

		foreach ( WPCPM_Sponsor_Members::live_accounts() as $holder ) {
			$record = WPCPM_Sponsor_Members::sponsor_of( $holder );

			if ( '' !== $record ) {
				$live[ $record ] = true;
			}
		}

		if ( empty( $live ) ) {
			return '';
		}

		foreach ( array_keys( WPCPM_Sponsors_Index::rows() ) as $record_id ) {
			if ( isset( $live[ $record_id ] ) ) {
				return (string) $record_id;
			}
		}

		return '';
	}
}
