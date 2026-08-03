<?php
/**
 * WordPress user <-> FreeScout agent mapping.
 *
 * FreeScout requires a `user` (integer agent ID) on every `message` or `note`
 * thread created through the API. Without a real mapping, every reply sent from
 * WordPress would be attributed to whoever owns the API key — so this class
 * resolves a genuine agent ID or refuses to let the reply happen at all.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Agent mapping.
 */
class JPCRM_FS_Agents {

	/**
	 * User meta key holding the resolved FreeScout agent ID.
	 */
	const META_KEY = 'jpcrm_fs_user_id';

	/**
	 * User meta key marking a lookup that ran and found nothing.
	 *
	 * Stops every page load re-querying the API for an unmapped user.
	 */
	const META_MISS = 'jpcrm_fs_user_lookup_failed';

	/**
	 * API client.
	 *
	 * @var JPCRM_FS_API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param JPCRM_FS_API $api API client.
	 */
	public function __construct( $api ) {
		$this->api = $api;
	}

	/**
	 * Capability required to send a reply from within WordPress.
	 *
	 * Defaults to `manage_options`, i.e. WordPress administrators.
	 *
	 * @return string
	 */
	public function reply_capability() {
		return (string) apply_filters( 'jpcrm_fs_reply_capability', 'manage_options' );
	}

	/**
	 * Does this user hold the capability to reply?
	 *
	 * This is only the WordPress-side check — a mapped agent ID is also
	 * required. Use can_reply() for the combined answer.
	 *
	 * @param int|null $wp_user_id WP user ID, or null for current user.
	 * @return bool
	 */
	public function has_reply_capability( $wp_user_id = null ) {

		$wp_user_id = $wp_user_id ? absint( $wp_user_id ) : get_current_user_id();

		if ( ! $wp_user_id ) {
			return false;
		}

		return user_can( $wp_user_id, $this->reply_capability() );
	}

	/**
	 * Resolve the FreeScout agent ID for a WordPress user.
	 *
	 * Resolution order:
	 *   1. Manual override set in plugin settings.
	 *   2. Previously resolved ID cached in user meta.
	 *   3. Live lookup against `GET /api/users?email=`, matching the primary
	 *      email first and then `alternateEmails`.
	 *
	 * @param int|null $wp_user_id WP user ID, or null for current user.
	 * @return int|WP_Error Agent ID, or error explaining why there isn't one.
	 */
	public function get_agent_id( $wp_user_id = null ) {

		$wp_user_id = $wp_user_id ? absint( $wp_user_id ) : get_current_user_id();

		if ( ! $wp_user_id ) {
			return new WP_Error( 'jpcrm_fs_no_user', __( 'No WordPress user to map.', 'jpcrm-freescout' ) );
		}

		// 1. Manual override.
		$override = $this->get_override( $wp_user_id );

		if ( $override > 0 ) {
			return $override;
		}

		// 2. Cached resolution.
		$cached = (int) get_user_meta( $wp_user_id, self::META_KEY, true );

		if ( $cached > 0 ) {
			return $cached;
		}

		// A previous lookup already came back empty — don't hammer the API.
		if ( get_user_meta( $wp_user_id, self::META_MISS, true ) ) {
			return $this->unmapped_error( $wp_user_id );
		}

		// 3. Live lookup by email.
		$user = get_userdata( $wp_user_id );

		if ( ! $user || empty( $user->user_email ) ) {
			return $this->unmapped_error( $wp_user_id );
		}

		$agent_id = $this->look_up_by_email( $user->user_email );

		if ( is_wp_error( $agent_id ) ) {
			// A transport/auth failure is not the same as "no such agent" —
			// don't cache it as a miss, so it retries once things are fixed.
			return $agent_id;
		}

		if ( $agent_id > 0 ) {
			update_user_meta( $wp_user_id, self::META_KEY, $agent_id );
			delete_user_meta( $wp_user_id, self::META_MISS );
			return $agent_id;
		}

		update_user_meta( $wp_user_id, self::META_MISS, 1 );

		return $this->unmapped_error( $wp_user_id );
	}

	/**
	 * Combined check: may this user send a reply right now?
	 *
	 * @param int|null $wp_user_id WP user ID, or null for current user.
	 * @return true|WP_Error
	 */
	public function can_reply( $wp_user_id = null ) {

		$wp_user_id = $wp_user_id ? absint( $wp_user_id ) : get_current_user_id();

		if ( ! $this->has_reply_capability( $wp_user_id ) ) {
			return new WP_Error(
				'jpcrm_fs_cannot_reply',
				__( 'Replying to tickets from WordPress is limited to administrators.', 'jpcrm-freescout' )
			);
		}

		$agent_id = $this->get_agent_id( $wp_user_id );

		if ( is_wp_error( $agent_id ) ) {
			return $agent_id;
		}

		return true;
	}

	/**
	 * Find a FreeScout agent by email address.
	 *
	 * @param string $email Email address.
	 * @return int|WP_Error Agent ID, 0 when no match, or error on API failure.
	 */
	public function look_up_by_email( $email ) {

		$email = sanitize_email( $email );

		if ( $email === '' ) {
			return 0;
		}

		$users = $this->api->get_users( array( 'email' => $email ) );

		if ( is_wp_error( $users ) ) {
			return $users;
		}

		$needle = strtolower( $email );

		// Prefer an exact primary-email match.
		foreach ( $users as $user ) {
			if ( isset( $user['email'] ) && strtolower( $user['email'] ) === $needle ) {
				return isset( $user['id'] ) ? absint( $user['id'] ) : 0;
			}
		}

		// Then fall back to alternate emails.
		foreach ( $users as $user ) {

			if ( empty( $user['alternateEmails'] ) || ! is_string( $user['alternateEmails'] ) ) {
				continue;
			}

			$alternates = array_map( 'trim', explode( ',', strtolower( $user['alternateEmails'] ) ) );

			if ( in_array( $needle, $alternates, true ) ) {
				return isset( $user['id'] ) ? absint( $user['id'] ) : 0;
			}
		}

		return 0;
	}

	/**
	 * Manual override for a given WP user, from settings.
	 *
	 * @param int $wp_user_id WP user ID.
	 * @return int Agent ID, or 0.
	 */
	private function get_override( $wp_user_id ) {

		$map = jpcrm_fs()->get_setting( 'agent_map', array() );

		if ( ! is_array( $map ) || ! isset( $map[ $wp_user_id ] ) ) {
			return 0;
		}

		return absint( $map[ $wp_user_id ] );
	}

	/**
	 * Error explaining that a user has no FreeScout agent.
	 *
	 * @param int $wp_user_id WP user ID.
	 * @return WP_Error
	 */
	private function unmapped_error( $wp_user_id ) {

		$user  = get_userdata( $wp_user_id );
		$email = $user ? $user->user_email : '';

		return new WP_Error(
			'jpcrm_fs_unmapped_agent',
			sprintf(
				/* translators: %s: email address. */
				__( 'No FreeScout agent matches %s, so replies cannot be attributed to you. Add a FreeScout account with that email address, or set the agent ID manually in the CRM FreeScout settings.', 'jpcrm-freescout' ),
				$email
			)
		);
	}

	/**
	 * Forget a cached mapping so the next call re-resolves it.
	 *
	 * @param int $wp_user_id WP user ID.
	 */
	public function clear_cache( $wp_user_id ) {

		$wp_user_id = absint( $wp_user_id );

		delete_user_meta( $wp_user_id, self::META_KEY );
		delete_user_meta( $wp_user_id, self::META_MISS );
	}

	/**
	 * Every WP user who could potentially reply — used by the settings screen.
	 *
	 * @return WP_User[]
	 */
	public function get_candidate_users() {

		return get_users(
			array(
				'capability' => $this->reply_capability(),
				'orderby'    => 'display_name',
				'number'     => 100,
			)
		);
	}
}
