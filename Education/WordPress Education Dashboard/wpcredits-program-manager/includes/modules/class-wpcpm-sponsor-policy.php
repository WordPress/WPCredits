<?php
/**
 * The one authorisation fence of the Sponsors module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides every sponsor action, and is the only code that compares sponsor IDs.
 *
 * `WPCPM_Institution_Policy`'s mirror (design spec of 4 September 2026, section 4): an
 * explicit map of grounds per action, so an action missing from it fails closed and a suite
 * fails until the map is updated; one refusal for every no, byte for byte, so the fence
 * cannot be walked as a membership oracle; and no gate, because the sponsor agreement is
 * optional (decision 10), so `ungated()` is every action.
 *
 * `bin/test-sponsor-policy.php` asserts the map row by row and scans every other sponsor
 * file for a `===` on a sponsor ID.
 */
final class WPCPM_Sponsor_Policy {

	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';

	const ACT_VIEW_DASHBOARD   = 'view_dashboard';
	const ACT_EDIT_PROFILE     = 'edit_profile';
	const ACT_MANAGE_OFFERS    = 'manage_offers';
	const ACT_VIEW_STATS       = 'view_stats';
	const ACT_VIEW_CLAIMANTS   = 'view_claimants';
	const ACT_WRITE_POSTS      = 'write_posts';
	const ACT_PUBLISH_POST     = 'publish_post';
	const ACT_UPLOAD_LOGO      = 'upload_logo';
	const ACT_AGREEMENT        = 'agreement';
	const ACT_REVIEW_AGREEMENT = 'review_agreement';
	const ACT_EXPRESS_INTEREST = 'express_interest';
	const ACT_MANAGE_MEMBERS   = 'manage_members';
	const ACT_PROVISION        = 'provision';

	/** The one refusal's code. */
	const REFUSAL_CODE = 'wpcpm_sponsor_unknown';

	/** The post meta a sponsor's post carries (Phase S3); read by `subject_post()` from day one. */
	const META_POST_SPONSOR = '_wpcpm_sponsor';

	/**
	 * Which grounds allow each action, manager first on every row so a manager who is also a
	 * member is logged as a manager. The whole table, literally, and never a default plus
	 * exceptions: a row that is not here is an action nobody may take.
	 *
	 * `ACT_WRITE_POSTS` gains the per-sponsor posting flag in Phase S3 (spec section 7.1);
	 * until then a member's ground is the membership alone.
	 *
	 * @return array<string, string[]>
	 */
	public static function grounds() {
		return array(
			self::ACT_VIEW_DASHBOARD   => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_EDIT_PROFILE     => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_MANAGE_OFFERS    => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_VIEW_STATS       => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_VIEW_CLAIMANTS   => array( self::GROUND_MANAGER ),
			self::ACT_WRITE_POSTS      => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_PUBLISH_POST     => array( self::GROUND_MANAGER ),
			self::ACT_UPLOAD_LOGO      => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_AGREEMENT        => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_REVIEW_AGREEMENT => array( self::GROUND_MANAGER ),
			self::ACT_EXPRESS_INTEREST => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_MANAGE_MEMBERS   => array( self::GROUND_MANAGER ),
			self::ACT_PROVISION        => array( self::GROUND_MANAGER ),
		);
	}

	/**
	 * The actions that need no settled agreement: all of them (decision 10).
	 *
	 * Kept as a method rather than dropped, so a later phase that gates something has one
	 * place to say so, and so the member ground reads the same as the institution's.
	 *
	 * @return string[]
	 */
	public static function ungated() {
		return array_keys( self::grounds() );
	}

	/**
	 * A subject that is a sponsor, from a record ID.
	 *
	 * @param string $record Airtable record ID.
	 * @return array
	 */
	public static function subject_sponsor( $record ) {
		return array(
			'type'        => 'sponsor',
			'sponsor_ids' => array( trim( (string) $record ) ),
		);
	}

	/**
	 * A subject that is a post a sponsor wrote, from the post's sponsor meta.
	 *
	 * @param WP_Post $post The post.
	 * @return array
	 */
	public static function subject_post( WP_Post $post ) {
		$sponsor = trim( (string) get_post_meta( $post->ID, self::META_POST_SPONSOR, true ) );

		return array(
			'type'        => 'post',
			'sponsor_ids' => '' === $sponsor ? array() : array( $sponsor ),
			'post_id'     => (int) $post->ID,
		);
	}

	/**
	 * Decide an action on a subject for a user.
	 *
	 * @param string           $action  An `ACT_*` constant.
	 * @param array            $subject From a `subject_*()` builder.
	 * @param int|WP_User|null $user    The acting user; null for the current one.
	 * @return array `allowed` (bool), `ground`, `sponsor` (the record the decision is about, or ''), `why` (log-only).
	 */
	public static function decide( $action, array $subject, $user = null ) {
		$refused = array(
			'allowed' => false,
			'ground'  => '',
			'sponsor' => '',
			'why'     => '',
		);

		$grounds = self::grounds();

		if ( ! is_string( $action ) || ! isset( $grounds[ $action ] ) ) {
			return array_merge( $refused, array( 'why' => 'unknown-action' ) );
		}

		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return array_merge( $refused, array( 'why' => 'no-user' ) );
		}

		$given = isset( $subject['sponsor_ids'] ) ? (array) $subject['sponsor_ids'] : array();
		$ids   = array_values( array_filter( array_map( 'strval', $given ), array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );

		foreach ( $grounds[ $action ] as $ground ) {
			$decision = call_user_func( array( __CLASS__, 'ground_' . $ground ), $action, $ids, $user );

			if ( is_array( $decision ) ) {
				return $decision;
			}
		}

		return array_merge( $refused, array( 'why' => 'no-ground' ) );
	}

	/**
	 * The one refusal, for every reason.
	 *
	 * @return WP_Error
	 */
	public static function refusal() {
		return new WP_Error( self::REFUSAL_CODE, __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The management capability covers every row of the map.
	 *
	 * @param string  $action Unused: the capability covers every row.
	 * @param array   $ids    The subject's sponsor IDs, well-formed ones only.
	 * @param WP_User $user   The acting user.
	 * @return array|null
	 */
	private static function ground_manager( $action, array $ids, WP_User $user ) {
		if ( ! user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
			return null;
		}

		return array(
			'allowed' => true,
			'ground'  => self::GROUND_MANAGER,
			'sponsor' => isset( $ids[0] ) ? $ids[0] : '',
			'why'     => '',
		);
	}

	/**
	 * Membership of a sponsor the subject names. No gate: `ungated()` is every action.
	 *
	 * @param string  $action The action, for the gate that a later phase may add.
	 * @param array   $ids    The subject's sponsor IDs, well-formed ones only.
	 * @param WP_User $user   The acting user.
	 * @return array|null
	 */
	private static function ground_member( $action, array $ids, WP_User $user ) {
		if ( empty( $ids ) ) {
			return null;
		}

		foreach ( WPCPM_Sponsor_Members::memberships_of( $user ) as $mine ) {
			if ( ! in_array( $mine, $ids, true ) ) {
				continue;
			}

			if ( ! in_array( $action, self::ungated(), true ) ) {
				continue;
			}

			return array(
				'allowed' => true,
				'ground'  => self::GROUND_MEMBER,
				'sponsor' => $mine,
				'why'     => '',
			);
		}

		return null;
	}
}
