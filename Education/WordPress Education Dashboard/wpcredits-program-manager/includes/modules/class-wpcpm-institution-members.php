<?php
/**
 * Institutions module - who may act for which institution.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The membership stamp: which account acts for which Airtable institution.
 *
 * All static, and the only writer of the three stamp keys. Every attach and detach goes
 * through here, and `bin/test-institution-members.php` asserts that no other file under
 * `includes/` writes `wpcpm_institution_record_id`, because the fence the policy builds
 * on these keys is only as good as the one place that sets them.
 *
 * Several accounts may carry the same institution; one account carries at most one
 * institution today. The storage and `memberships_of()` could hold a second, and
 * `attach()` refuses it until somebody needs it and says who.
 *
 * | Key                                | Meaning                                                            |
 * | `wpcpm_institution_record_id`      | The membership. Present and well-formed means "acts for it"        |
 * | `wpcpm_institution_active`         | 1 while it may be exercised; 0 after a detach or the sync's revoke  |
 * | `wpcpm_institution_record_id_was`  | Where the stamp goes when membership ends: history without power   |
 * | `wpcpm_institution_membership`     | `since`, `by`, `how`, `invite`: facts for the card and the log      |
 * | `wpcpm_institution_invited`        | Stamped by the mail layer when the login invitation is sent        |
 * | `wpcpm_institution_profile`        | The index row's public facts, so the header needs no Airtable read |
 *
 * The policy reads `institution_of()` and nothing else here; the facts are for people.
 */
class WPCPM_Institution_Members {

	/** User meta: the Airtable institution record this account acts for. */
	const META_RECORD_ID = 'wpcpm_institution_record_id';

	/** User meta: 1 while the membership may be exercised, 0 once it has ended. */
	const META_ACTIVE = 'wpcpm_institution_active';

	/** User meta: the record ID a former membership named. */
	const META_RECORD_ID_WAS = 'wpcpm_institution_record_id_was';

	/** User meta: `since`, `by`, `how`, `invite`. Never read by the policy. */
	const META_MEMBERSHIP = 'wpcpm_institution_membership';

	/** User meta: when the login invitation went out. Written by the mail layer. */
	const META_INVITED = 'wpcpm_institution_invited';

	/** User meta: name, city, country, stage, website, contact person from the index row. */
	const META_PROFILE = 'wpcpm_institution_profile';

	/** How a membership came about. */
	const HOW_PROVISIONED = 'provisioned';
	const HOW_APPROVED    = 'approved';
	const HOW_MANAGER     = 'manager';
	const HOW_INVITED     = 'invited';
	const HOW_LEGACY      = 'legacy';

	/** Why a membership ended. */
	const REASON_REMOVED = 'removed';
	const REASON_LEFT    = 'left';
	const REASON_REVOKED = 'revoked';

	/** Mail context for the last-member notice, so it shows in the log by name. */
	const NOTIFY_LAST_MEMBER = 'member-last';

	/**
	 * The ways a membership may come about.
	 *
	 * @return string[]
	 */
	public static function hows() {
		return array( self::HOW_PROVISIONED, self::HOW_APPROVED, self::HOW_MANAGER, self::HOW_INVITED, self::HOW_LEGACY );
	}

	/**
	 * The reasons a membership may end.
	 *
	 * @return string[]
	 */
	public static function reasons() {
		return array( self::REASON_REMOVED, self::REASON_LEFT, self::REASON_REVOKED );
	}

	/**
	 * Whether two record IDs name the same institution.
	 *
	 * Housekeeping, never a fence: it decides whether a returning account is coming back to
	 * the institution it left, which governs one audit label and whether the `_was` stamp is
	 * cleared. Every question of the form "may this person act for that institution" is
	 * `WPCPM_Institution_Policy::decide()`'s, and this class asks it of nobody. Kept as one
	 * named helper so that `bin/test-institution-members.php` can go on asserting that no
	 * comparison of an institution ID is written inline anywhere in this file.
	 *
	 * @param string $one One record ID.
	 * @param string $two Another.
	 * @return bool
	 */
	private static function same_record( $one, $two ) {
		return (string) $one === (string) $two;
	}

	/**
	 * The Airtable institution this account may act for right now, or ''.
	 *
	 * All three conditions, every time: a well-formed stamp, the active flag at 1, on an
	 * account that exists. Revocation moves the stamp and zeroes the flag, so either alone
	 * would do; both are checked because they are written by different code paths and the
	 * day they disagree is the day one of them is wrong. An empty stamp must never be
	 * treated as "matches every student whose institution is also empty", which is the
	 * shape of every fence bug in this module's history.
	 *
	 * @param int|WP_User|null $user User ID, object, or null for the current user.
	 * @return string Record ID, or ''.
	 */
	public static function institution_of( $user = null ) {
		$user = self::resolve( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return '';
		}

		$record = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ) );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return '';
		}

		if ( 1 !== (int) get_user_meta( $user->ID, self::META_ACTIVE, true ) ) {
			return '';
		}

		return $record;
	}

	/**
	 * Every institution this account acts for.
	 *
	 * One element or none today; a list because the policy iterates it, so a second
	 * membership per account would land in storage and here without touching a caller.
	 *
	 * @param int|WP_User|null $user User ID, object, or null for the current user.
	 * @return string[] Record IDs.
	 */
	public static function memberships_of( $user = null ) {
		$record = self::institution_of( $user );

		return '' === $record ? array() : array( $record );
	}

	/**
	 * Whether this account acts for any institution right now.
	 *
	 * What `WPCPM_Notices::applies_to()` delegates to: the role alone would count an
	 * account whose membership ended, and an administrator who was never attached.
	 *
	 * @param int|WP_User|null $user User ID, object, or null for the current user.
	 * @return bool
	 */
	public static function is_member( $user = null ) {
		return '' !== self::institution_of( $user );
	}

	/**
	 * Every account that acts for an institution right now.
	 *
	 * Guarded here: a malformed ID returns nothing and issues no query, because a query
	 * for an empty stamp would return every account that has none. The query matches
	 * under the database collation, which does not tell `recABC` from `recabc`; record
	 * IDs do, so every hit is checked again in PHP through `institution_of()`, which also
	 * applies the flag and the existence test in the one place they are defined.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return WP_User[]
	 */
	public static function members_of( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$members = array();

		foreach ( self::accounts_stamped( self::META_RECORD_ID, $record_id ) as $user ) {
			if ( self::same( self::institution_of( $user ), $record_id ) ) {
				$members[] = $user;
			}
		}

		return $members;
	}

	/**
	 * Every account whose membership of an institution has ended.
	 *
	 * The `_was` key, so a manager can re-add in one click. An account that has since
	 * joined another institution still appears: it was a member of this one.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return WP_User[]
	 */
	public static function former_members_of( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$former = array();

		foreach ( self::accounts_stamped( self::META_RECORD_ID_WAS, $record_id ) as $user ) {
			if ( self::same( get_user_meta( $user->ID, self::META_RECORD_ID_WAS, true ), $record_id ) ) {
				$former[] = $user;
			}
		}

		return $former;
	}

	/**
	 * Give an account a membership.
	 *
	 * The record must be well-formed and in the pipeline index: an institution the site
	 * has never read cannot acquire members, and the approval handler inserts the row
	 * before it attaches. The account must exist. Then the identity rules, each a named
	 * refusal: an administrator is refused, because a manager passes by the `manager`
	 * ground and stamping one would make the log's ground ambiguous; an account carrying a
	 * student record is refused, because the school sees the student and not the other
	 * way round; a mentor is allowed and keeps the Mentor role; a live member of this
	 * institution is already one; a live member of another is refused, one membership per
	 * account today; a former member is re-added and `_was` cleared.
	 *
	 * @param int    $user_id   The account.
	 * @param string $record_id Airtable record ID of the institution.
	 * @param string $how       One of `hows()`.
	 * @param int    $actor_id  Who is doing it; 0 for a sync.
	 * @param int    $invite_id The invitation post this answers, when there is one.
	 * @return true|WP_Error
	 */
	public static function attach( $user_id, $record_id, $how, $actor_id, $invite_id = 0 ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return new WP_Error( 'wpcpm_member_bad_record', __( 'That is not an Airtable record ID.', 'wpcredits-program-manager' ) );
		}

		if ( ! WPCPM_Institutions_Index::has( $record_id ) ) {
			return new WP_Error( 'wpcpm_member_not_indexed', __( 'That institution is not in the pipeline index yet. Run the institutions sync, then try again.', 'wpcredits-program-manager' ) );
		}

		$user = self::account( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'wpcpm_member_no_account', __( 'There is no account with that ID.', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_ADMIN ) ) {
			return new WP_Error( 'wpcpm_member_is_admin', __( 'An administrator already manages every institution and is not attached to one.', 'wpcredits-program-manager' ) );
		}

		if ( '' !== trim( (string) get_user_meta( $user->ID, WPCPM_Students_Sync::META_RECORD_ID, true ) ) ) {
			return new WP_Error( 'wpcpm_member_is_student', __( 'That account belongs to a student. A student cannot act for their own institution.', 'wpcredits-program-manager' ) );
		}

		$live = self::institution_of( $user );

		if ( '' !== $live ) {
			if ( self::same( $live, $record_id ) ) {
				return new WP_Error( 'wpcpm_member_already', __( 'That account is already a member of this institution.', 'wpcredits-program-manager' ) );
			}

			return new WP_Error( 'wpcpm_member_elsewhere', __( 'That account already acts for another institution. Remove it there first.', 'wpcredits-program-manager' ) );
		}

		$how = sanitize_key( (string) $how );

		// A server-held list, never a pass-through: the value is shown on the members card
		// and in the log, and a typo there would be a fact nobody wrote.
		if ( ! in_array( $how, self::hows(), true ) ) {
			return new WP_Error( 'wpcpm_member_bad_how', __( 'That is not a way a membership can come about.', 'wpcredits-program-manager' ) );
		}

		$actor_id  = absint( $actor_id );
		$invite_id = absint( $invite_id );
		// Re-added only when the account is coming back to THIS institution. A `_was` naming
		// another one is history that must survive: `former_members_of()` promises it, and the
		// sync's "no live member and no _was naming it" gate would otherwise let a removed
		// contact's account be provisioned again every night.
		$was     = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID_WAS, true ) );
		$readded = self::same_record( $was, $record_id );

		update_user_meta( $user->ID, self::META_RECORD_ID, $record_id );
		update_user_meta( $user->ID, self::META_ACTIVE, 1 );
		update_user_meta(
			$user->ID,
			self::META_MEMBERSHIP,
			array(
				'since'  => time(),
				'by'     => $actor_id,
				'how'    => $how,
				'invite' => $invite_id,
			)
		);
		update_user_meta( $user->ID, self::META_PROFILE, self::profile_of( $record_id ) );

		if ( $readded ) {
			delete_user_meta( $user->ID, self::META_RECORD_ID_WAS );
		}

		// add_role() rather than set_role(): the account may be a mentor, an author or an
		// editor as well, and joining an institution should not demote anyone.
		if ( ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_INSTITUTION ) ) {
			$user->add_role( WPCPM_Roles::ROLE_INSTITUTION );
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => WPCPM_Institution_Audit::KIND_MEMBER_ADDED,
				'institution' => $record_id,
				'subject'     => (string) $user->ID,
				'actor'       => $actor_id,
				'ground'      => self::ground_of( $actor_id ),
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => sprintf(
					/* translators: 1: display name, 2: how the membership came about. */
					__( '%1$s was added as a member (%2$s).', 'wpcredits-program-manager' ),
					$user->display_name,
					$how
				),
				'data'        => array(
					'user'    => (int) $user->ID,
					'how'     => $how,
					'invite'  => $invite_id,
					'readded' => $readded,
				),
			)
		);

		return true;
	}

	/**
	 * End an account's membership.
	 *
	 * The stamp moves to `_was` (deleted, never blanked, so an empty stamp never exists to
	 * be matched); the flag goes to 0; the Institution role comes off unless the account
	 * is an administrator, and an account left with no role at all becomes a Subscriber;
	 * the account itself is kept, it is a person. One audit row with the reason. When no
	 * live member remains, the last-member rule: the institution's pending invitations are
	 * cancelled and the managers are told, because an institution nobody can act for is
	 * the one that pages a manager.
	 *
	 * The sync's revoke phase calls `detach( $id, 'revoked', 0 )` per member rather than
	 * carrying its own copy of these steps.
	 *
	 * @param int    $user_id  The account.
	 * @param string $reason   One of `reasons()`.
	 * @param int    $actor_id Who is doing it; 0 for a sync.
	 * @return true|WP_Error
	 */
	public static function detach( $user_id, $reason, $actor_id ) {
		$user = self::account( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'wpcpm_member_no_account', __( 'There is no account with that ID.', 'wpcredits-program-manager' ) );
		}

		$record_id = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ) );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return new WP_Error( 'wpcpm_member_none', __( 'That account is not a member of any institution.', 'wpcredits-program-manager' ) );
		}

		$reason = sanitize_key( (string) $reason );

		if ( ! in_array( $reason, self::reasons(), true ) ) {
			return new WP_Error( 'wpcpm_member_bad_reason', __( 'That is not a reason a membership can end for.', 'wpcredits-program-manager' ) );
		}

		$actor_id = absint( $actor_id );

		update_user_meta( $user->ID, self::META_RECORD_ID_WAS, $record_id );
		delete_user_meta( $user->ID, self::META_RECORD_ID );
		update_user_meta( $user->ID, self::META_ACTIVE, 0 );

		// Never touch an administrator's roles, and never delete an account.
		if ( ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_ADMIN )
			&& WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_INSTITUTION ) ) {
			$user->remove_role( WPCPM_Roles::ROLE_INSTITUTION );

			if ( empty( $user->roles ) ) {
				$user->set_role( 'subscriber' );
			}
		}

		self::cancel_invitations_by( $user->ID );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => WPCPM_Institution_Audit::KIND_MEMBER_REMOVED,
				'institution' => $record_id,
				'subject'     => (string) $user->ID,
				'actor'       => $actor_id,
				'ground'      => self::ground_of( $actor_id ),
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => sprintf(
					/* translators: 1: display name, 2: why the membership ended. */
					__( '%1$s is no longer a member (%2$s).', 'wpcredits-program-manager' ),
					$user->display_name,
					$reason
				),
				'data'        => array(
					'user'   => (int) $user->ID,
					'reason' => $reason,
				),
			)
		);

		if ( empty( self::members_of( $record_id ) ) ) {
			self::cancel_invitations_for( $record_id );
			self::notify_last_member( $record_id, $user, $reason );
		}

		return true;
	}

	/**
	 * Cancel every pending invitation this account issued.
	 *
	 * A deliberate no-op until the invitation post type (`wpcpm_institution_invite`) ships
	 * in Phase 4. It is called now so `detach()` already has the step in the right order,
	 * and the day the type exists this is the one place to fill in.
	 *
	 * @param int $user_id The account whose invitations end with its membership.
	 */
	private static function cancel_invitations_by( $user_id ) {
		unset( $user_id );
	}

	/**
	 * Cancel every pending invitation for an institution.
	 *
	 * The last-member rule's first half; a no-op until Phase 4 for the same reason as
	 * `cancel_invitations_by()`.
	 *
	 * @param string $record_id Airtable record ID.
	 */
	private static function cancel_invitations_for( $record_id ) {
		unset( $record_id );
	}

	/**
	 * Tell the managers that an institution has no member left.
	 *
	 * Through `WPCPM_Institutions::notify_managers()`, reached as an array callable so this
	 * file loads and its tests run whether or not the module carries the method yet; the
	 * shell ships in the same phase. A missing method means nobody is told, which the
	 * audit row written just before still records.
	 *
	 * @param string  $record_id Airtable record ID.
	 * @param WP_User $user      The member who just left.
	 * @param string  $reason    Why.
	 * @return int How many managers were mailed.
	 */
	private static function notify_last_member( $record_id, WP_User $user, $reason ) {
		if ( ! class_exists( 'WPCPM_Institutions' ) || ! method_exists( 'WPCPM_Institutions', 'notify_managers' ) ) {
			return 0;
		}

		$profile = get_user_meta( $user->ID, self::META_PROFILE, true );
		$name    = is_array( $profile ) && ! empty( $profile['name'] ) ? (string) $profile['name'] : $record_id;
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$who     = $user->display_name;
		$when    = wp_date( get_option( 'date_format' ) );

		$build = function () use ( $site, $name, $record_id, $who, $reason, $when ) {
			$lines = array(
				sprintf(
					/* translators: 1: person, 2: institution name, 3: record ID, 4: reason, 5: date. */
					__( '%1$s is no longer a member of %2$s (%3$s) as of %5$s (%4$s). No account can act for this institution on the site now.', 'wpcredits-program-manager' ),
					$who,
					$name,
					$record_id,
					$reason,
					$when
				),
				__( 'The Airtable record, the agreement, the students and the history are untouched. A former member can be re-added in one click from the Institutions screen, or a new account created there.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: institution name. */
					__( '[%1$s] %2$s has no members left', 'wpcredits-program-manager' ),
					$site,
					$name
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return (int) call_user_func( array( 'WPCPM_Institutions', 'notify_managers' ), self::NOTIFY_LAST_MEMBER, $build );
	}

	/**
	 * The ground an actor acts on, for the log.
	 *
	 * 0 is a sync or another background job. A holder of `CAP_MANAGE` acts as a manager
	 * whatever else they are; anyone else reaching a write here did so as a member.
	 *
	 * @param int $actor_id User ID, or 0.
	 * @return string
	 */
	private static function ground_of( $actor_id ) {
		$actor_id = absint( $actor_id );

		if ( 0 === $actor_id ) {
			return WPCPM_Institution_Audit::GROUND_SYSTEM;
		}

		if ( user_can( $actor_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return WPCPM_Institution_Audit::GROUND_MANAGER;
		}

		return WPCPM_Institution_Audit::GROUND_MEMBER;
	}

	/**
	 * The public facts about an institution, from the pipeline index.
	 *
	 * Written on the account so the dashboard header renders from user meta alone. The
	 * index keeps the name as Airtable stores it, trailing space and all; it is trimmed
	 * here because this copy is for display.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return array
	 */
	private static function profile_of( $record_id ) {
		$row = WPCPM_Institutions_Index::row( $record_id );

		if ( ! is_array( $row ) ) {
			return array();
		}

		return array(
			'name'           => trim( isset( $row['name'] ) ? (string) $row['name'] : '' ),
			'city'           => isset( $row['city'] ) ? (string) $row['city'] : '',
			'country_name'   => isset( $row['country_name'] ) ? (string) $row['country_name'] : '',
			'stage'          => isset( $row['stage'] ) ? (string) $row['stage'] : '',
			'website'        => isset( $row['website'] ) ? (string) $row['website'] : '',
			'contact_person' => isset( $row['contact_person'] ) ? (string) $row['contact_person'] : '',
		);
	}

	/**
	 * Whether two values are the same record ID.
	 *
	 * Both must be well-formed before they are compared: two empty strings are equal and
	 * are not the same institution, and that is the whole reason the policy suite greps
	 * for a bare `===` between institution IDs.
	 *
	 * @param string $a One value.
	 * @param string $b The other.
	 * @return bool
	 */
	private static function same( $a, $b ) {
		$a = trim( (string) $a );
		$b = trim( (string) $b );

		return WPCPM_Mentors_Sync::is_record_id( $a )
			&& WPCPM_Mentors_Sync::is_record_id( $b )
			&& 0 === strcmp( $a, $b );
	}

	/**
	 * The accounts whose meta key holds a record ID, before the case check.
	 *
	 * @param string $key       Meta key.
	 * @param string $record_id Airtable record ID, already checked as well-formed.
	 * @return WP_User[]
	 */
	private static function accounts_stamped( $key, $record_id ) {
		$users = get_users(
			array(
				'number'     => -1,
				'meta_key'   => $key,
				'meta_value' => $record_id,
			)
		);

		$out = array();

		foreach ( (array) $users as $user ) {
			if ( $user instanceof WP_User && $user->exists() ) {
				$out[] = $user;
			}
		}

		return $out;
	}

	/**
	 * An account by ID, or null.
	 *
	 * Not `WPCPM_Roles::resolve_user()`: that falls back to the current user, and a
	 * membership written to whoever happens to be logged in because a caller passed 0 is
	 * exactly the accident this must not have.
	 *
	 * @param int $user_id The account.
	 * @return WP_User|null
	 */
	private static function account( $user_id ) {
		$user_id = absint( $user_id );

		if ( 0 === $user_id ) {
			return null;
		}

		$user = get_user_by( 'id', $user_id );

		return ( $user instanceof WP_User && $user->exists() ) ? $user : null;
	}

	/**
	 * Normalise a user argument for the readers.
	 *
	 * Null means the current user, as everywhere in the plugin. A number that is not a
	 * real ID is nobody, not the current user: 0 is what an unset actor or a failed lookup
	 * yields, and resolving it to whoever is logged in would answer a background job's
	 * question with a stranger's membership.
	 *
	 * @param int|WP_User|null $user User ID, object, or null.
	 * @return WP_User|null
	 */
	private static function resolve( $user ) {
		if ( null === $user ) {
			return WPCPM_Roles::resolve_user( null );
		}

		if ( $user instanceof WP_User ) {
			return $user;
		}

		return is_numeric( $user ) ? self::account( (int) $user ) : null;
	}
}
