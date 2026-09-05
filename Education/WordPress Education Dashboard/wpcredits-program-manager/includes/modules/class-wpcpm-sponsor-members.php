<?php
/**
 * Sponsor memberships: which accounts act for which sponsor.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stamp that lets a person act for a sponsor, and the two ways it changes.
 *
 * `WPCPM_Institution_Members`'s shape (design spec of 4 September 2026, decision 2): several
 * accounts per sponsor from day one, all with equal power, no owner. A manager attaches and
 * removes accounts; sponsors do not invite each other in this release. The stamp is read by
 * `WPCPM_Sponsor_Policy::decide()` and by nothing else that decides.
 *
 * | Meta                         | Holds                                       |
 * | ---------------------------- | ------------------------------------------- |
 * | `wpcpm_sponsor_record_id`    | the sponsor's Airtable record ID            |
 * | `wpcpm_sponsor_active`       | 1 while exercisable, 0 after detach         |
 * | `wpcpm_sponsor_record_id_was`| history, no power                           |
 * | `wpcpm_sponsor_membership`   | `since`, `by`, `how`                        |
 * | `wpcpm_sponsor_invited`      | when the welcome was sent (the mail layer)  |
 * | `wpcpm_sponsor_profile`      | name, website, product type, status stamped at attach |
 */
final class WPCPM_Sponsor_Members {

	const META_RECORD_ID     = 'wpcpm_sponsor_record_id';
	const META_ACTIVE        = 'wpcpm_sponsor_active';
	const META_RECORD_ID_WAS = 'wpcpm_sponsor_record_id_was';
	const META_MEMBERSHIP    = 'wpcpm_sponsor_membership';
	const META_INVITED       = 'wpcpm_sponsor_invited';
	const META_PROFILE       = 'wpcpm_sponsor_profile';

	const HOW_PROVISIONED = 'provisioned';
	const HOW_MANAGER     = 'manager';
	const HOW_APPROVED    = 'approved';

	const REASON_REMOVED = 'removed';
	const REASON_REVOKED = 'revoked';

	const KIND_MEMBER_ADDED   = 'member_added';
	const KIND_MEMBER_REMOVED = 'member_removed';

	/**
	 * The ways a membership can come about. A server-held list, never a pass-through: the
	 * value is shown on the members card and in the log, and a typo there would be a fact
	 * nobody wrote.
	 *
	 * @return string[]
	 */
	public static function hows() {
		return array( self::HOW_PROVISIONED, self::HOW_MANAGER, self::HOW_APPROVED );
	}

	/**
	 * The reasons a membership can end for.
	 *
	 * @return string[]
	 */
	public static function reasons() {
		return array( self::REASON_REMOVED, self::REASON_REVOKED );
	}

	/**
	 * The sponsor an account acts for, or ''.
	 *
	 * All three or nothing: a well-formed stamp, the active flag, an account that exists. A
	 * detached account keeps `_was` and answers '' here, which is what "no power" means.
	 *
	 * @param int|WP_User|null $user The account; null for the current user.
	 * @return string Airtable record ID, or ''.
	 */
	public static function sponsor_of( $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return '';
		}

		$record = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ) );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || 1 !== (int) get_user_meta( $user->ID, self::META_ACTIVE, true ) ) {
			return '';
		}

		return $record;
	}

	/**
	 * The sponsors an account acts for, as a list: one today, shaped for more.
	 *
	 * @param int|WP_User|null $user The account.
	 * @return string[]
	 */
	public static function memberships_of( $user ) {
		$record = self::sponsor_of( $user );

		return '' === $record ? array() : array( $record );
	}

	/**
	 * Whether an account acts for a sponsor, or for one sponsor in particular.
	 *
	 * @param int|WP_User|null $user   The account.
	 * @param string           $record A record ID, or '' for any sponsor.
	 * @return bool
	 */
	public static function is_member( $user = null, $record = '' ) {
		$own = self::sponsor_of( $user );

		if ( '' === $own ) {
			return false;
		}

		return '' === (string) $record || self::same_record( $own, (string) $record );
	}

	/**
	 * The live accounts of a sponsor.
	 *
	 * @param string $record Airtable record ID.
	 * @return WP_User[]
	 */
	public static function members_of( $record ) {
		return self::holders( self::META_RECORD_ID, $record, true );
	}

	/**
	 * The accounts that used to act for a sponsor.
	 *
	 * @param string $record Airtable record ID.
	 * @return WP_User[]
	 */
	public static function former_members_of( $record ) {
		return self::holders( self::META_RECORD_ID_WAS, $record, false );
	}

	/**
	 * Every account currently active for some sponsor, in one query.
	 *
	 * `WPCPM_Sponsor_Roster::locked_today()` and `first_with_member()` used to run this same
	 * `get_users()` query themselves, once each; a screen render that needs every sponsor's
	 * accounts (`WPCPM_Sponsors::accounts_by_sponsor()`) groups this one list by
	 * `sponsor_of()` instead of asking `members_of()` once per row, its own query each time.
	 *
	 * @return WP_User[]
	 */
	public static function live_accounts() {
		return (array) get_users(
			array(
				'number'     => -1,
				'meta_key'   => self::META_ACTIVE,
				'meta_value' => 1,
			)
		);
	}

	/**
	 * Let an account act for a sponsor.
	 *
	 * The refusals, in the spec's order (section 5.1): a malformed record ID; a record not in
	 * the index; no such account; an administrator (a manager already reaches every sponsor);
	 * a student (a student cannot be a sponsor's representative while in the program); an
	 * institution's representative (one person acting for a school and for a company that
	 * sells to its students is a conflict the site does not arbitrate); already a member here;
	 * a member of another sponsor. A mentor may be attached: sponsored mentors are often the
	 * sponsor's own staff, and the account keeps its first role beside the new one.
	 *
	 * @param int    $user_id   The account.
	 * @param string $record_id The sponsor.
	 * @param string $how       One of `hows()`.
	 * @param int    $actor_id  Who did it; 0 for the system.
	 * @return true|WP_Error
	 */
	public static function attach( $user_id, $record_id, $how, $actor_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return new WP_Error( 'wpcpm_sponsor_bad_record', __( 'That is not an Airtable record ID.', 'wpcredits-program-manager' ) );
		}

		if ( ! WPCPM_Sponsors_Index::has( $record_id ) ) {
			return new WP_Error( 'wpcpm_sponsor_not_indexed', __( 'That sponsor is not in the index yet. Run the sponsors sync, then try again.', 'wpcredits-program-manager' ) );
		}

		$user = self::account( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'wpcpm_sponsor_no_account', __( 'There is no account with that ID.', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_ADMIN ) ) {
			return new WP_Error( 'wpcpm_sponsor_is_admin', __( 'An administrator already manages every sponsor and is not attached to one.', 'wpcredits-program-manager' ) );
		}

		if ( '' !== trim( (string) get_user_meta( $user->ID, WPCPM_Students_Sync::META_RECORD_ID, true ) ) ) {
			return new WP_Error( 'wpcpm_sponsor_is_student', __( 'That account belongs to a student. A student cannot represent a sponsor while in the program.', 'wpcredits-program-manager' ) );
		}

		if ( '' !== WPCPM_Institution_Members::institution_of( $user ) ) {
			return new WP_Error( 'wpcpm_sponsor_is_institution', __( 'That account acts for an institution. One person cannot act for a school and for a company that sells to its students.', 'wpcredits-program-manager' ) );
		}

		$live = self::sponsor_of( $user );

		if ( '' !== $live ) {
			if ( self::same_record( $live, $record_id ) ) {
				return new WP_Error( 'wpcpm_sponsor_member_already', __( 'That account is already attached to this sponsor.', 'wpcredits-program-manager' ) );
			}

			return new WP_Error( 'wpcpm_sponsor_member_elsewhere', __( 'That account already acts for another sponsor. Remove it there first.', 'wpcredits-program-manager' ) );
		}

		$how = sanitize_key( (string) $how );

		if ( ! in_array( $how, self::hows(), true ) ) {
			return new WP_Error( 'wpcpm_sponsor_bad_how', __( 'That is not a way a membership can come about.', 'wpcredits-program-manager' ) );
		}

		$actor_id = absint( $actor_id );
		// Re-added only when the account is coming back to THIS sponsor. A `_was` naming
		// another one is history that must survive: `former_members_of()` promises it.
		$was     = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID_WAS, true ) );
		$readded = self::same_record( $was, $record_id );

		update_user_meta( $user->ID, self::META_RECORD_ID, $record_id );
		update_user_meta( $user->ID, self::META_ACTIVE, 1 );
		update_user_meta(
			$user->ID,
			self::META_MEMBERSHIP,
			array(
				'since' => time(),
				'by'    => $actor_id,
				'how'   => $how,
			)
		);
		update_user_meta( $user->ID, self::META_PROFILE, self::profile_of( $record_id ) );

		if ( $readded ) {
			delete_user_meta( $user->ID, self::META_RECORD_ID_WAS );
		}

		// add_role() rather than set_role(): the account may be a mentor as well, and acting
		// for a sponsor should not demote anyone.
		if ( ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_SPONSOR ) ) {
			$user->add_role( WPCPM_Roles::ROLE_SPONSOR );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::KIND_MEMBER_ADDED,
				'sponsor'  => $record_id,
				'subject'  => (string) $user->ID,
				'actor'    => $actor_id,
				'ground'   => self::ground_of( $actor_id ),
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => sprintf(
					/* translators: 1: display name, 2: how the membership came about. */
					__( '%1$s was attached to the sponsor (%2$s).', 'wpcredits-program-manager' ),
					$user->display_name,
					$how
				),
				'data'     => array(
					'user'    => (int) $user->ID,
					'how'     => $how,
					'readded' => $readded,
				),
			)
		);

		return true;
	}

	/**
	 * End an account's membership. The stamp becomes history; the account is never deleted.
	 *
	 * @param int    $user_id  The account.
	 * @param string $reason   One of `reasons()`.
	 * @param int    $actor_id Who did it; 0 for the system.
	 * @return true|WP_Error
	 */
	public static function detach( $user_id, $reason, $actor_id ) {
		$user = self::account( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'wpcpm_sponsor_no_account', __( 'There is no account with that ID.', 'wpcredits-program-manager' ) );
		}

		$reason = sanitize_key( (string) $reason );

		if ( ! in_array( $reason, self::reasons(), true ) ) {
			return new WP_Error( 'wpcpm_sponsor_bad_reason', __( 'That is not a reason a membership can end for.', 'wpcredits-program-manager' ) );
		}

		$record_id = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ) );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return new WP_Error( 'wpcpm_sponsor_member_none', __( 'That account is not attached to any sponsor.', 'wpcredits-program-manager' ) );
		}

		$actor_id = absint( $actor_id );

		update_user_meta( $user->ID, self::META_RECORD_ID_WAS, $record_id );
		delete_user_meta( $user->ID, self::META_RECORD_ID );
		update_user_meta( $user->ID, self::META_ACTIVE, 0 );

		// Never touch an administrator's roles, and never delete an account.
		if ( ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_ADMIN )
			&& WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_SPONSOR ) ) {
			$user->remove_role( WPCPM_Roles::ROLE_SPONSOR );

			if ( empty( $user->roles ) ) {
				$user->set_role( 'subscriber' );
			}
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::KIND_MEMBER_REMOVED,
				'sponsor'  => $record_id,
				'subject'  => (string) $user->ID,
				'actor'    => $actor_id,
				'ground'   => self::ground_of( $actor_id ),
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => sprintf(
					/* translators: 1: display name, 2: why the membership ended. */
					__( '%1$s no longer acts for the sponsor (%2$s).', 'wpcredits-program-manager' ),
					$user->display_name,
					$reason
				),
				'data'     => array(
					'user'   => (int) $user->ID,
					'reason' => $reason,
				),
			)
		);

		return true;
	}

	/**
	 * The account behind an ID, or null.
	 *
	 * @param int $user_id The account.
	 * @return WP_User|null
	 */
	private static function account( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );

		return ( $user instanceof WP_User && $user->exists() ) ? $user : null;
	}

	/**
	 * The accounts holding a record ID under one meta key.
	 *
	 * The comparison is left to `same_record()`, never `===` on the raw stamp: the fence
	 * rule is that the policy alone compares sponsor IDs, and this reader is a lookup, not a
	 * decision, so it goes through the one helper the policy's suite allows.
	 *
	 * @param string $meta_key `META_RECORD_ID` or `META_RECORD_ID_WAS`.
	 * @param string $record   Airtable record ID.
	 * @param bool   $live     Whether the active flag must be set too.
	 * @return WP_User[]
	 */
	private static function holders( $meta_key, $record, $live ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		$args = array(
			'number'     => -1,
			'meta_key'   => $meta_key,
			'meta_value' => $record,
		);

		$found = array();

		foreach ( (array) get_users( $args ) as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			// The query matched under the database collation; record IDs are case-sensitive.
			if ( ! self::same_record( (string) get_user_meta( $user->ID, $meta_key, true ), $record ) ) {
				continue;
			}

			if ( $live && 1 !== (int) get_user_meta( $user->ID, self::META_ACTIVE, true ) ) {
				continue;
			}

			$found[] = $user;
		}

		return $found;
	}

	/**
	 * Whether two record IDs name the same record: byte for byte, trimmed.
	 *
	 * @param string $a One.
	 * @param string $b The other.
	 * @return bool
	 */
	private static function same_record( $a, $b ) {
		return 0 === strcmp( trim( (string) $a ), trim( (string) $b ) );
	}

	/**
	 * The header facts stamped on an account at attach time, trimmed.
	 *
	 * @param string $record Airtable record ID.
	 * @return array
	 */
	private static function profile_of( $record ) {
		$row = WPCPM_Sponsors_Index::row( $record );
		$out = array();

		foreach ( array( 'name', 'website', 'product_type', 'status' ) as $key ) {
			$out[ $key ] = ( is_array( $row ) && isset( $row[ $key ] ) ) ? trim( (string) $row[ $key ] ) : '';
		}

		return $out;
	}

	/**
	 * The ground a change is logged on: a manager pressed, or the system did.
	 *
	 * @param int $actor_id Who did it; 0 for the system.
	 * @return string
	 */
	private static function ground_of( $actor_id ) {
		if ( $actor_id > 0 && user_can( $actor_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return WPCPM_Institution_Audit::GROUND_MANAGER;
		}

		return $actor_id > 0 ? WPCPM_Institution_Audit::GROUND_MEMBER : WPCPM_Institution_Audit::GROUND_SYSTEM;
	}
}
