<?php
/**
 * Custom user roles for the program modules.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Student, Mentor, Institution and Sponsor roles and the
 * capabilities that drive content gating.
 *
 * Role slugs are prefixed (`wpcpm_mentor`, not `mentor`) because bare `student`
 * and `teacher` slugs are commonly claimed by LMS plugins — Sensei among them —
 * and silently sharing a role slug means sharing its capability set.
 */
class WPCPM_Roles {

	const ROLE_STUDENT     = 'wpcpm_student';
	const ROLE_MENTOR      = 'wpcpm_mentor';
	const ROLE_INSTITUTION = 'wpcpm_institution';
	const ROLE_SPONSOR     = 'wpcpm_sponsor';
	const ROLE_ADMIN       = 'administrator';

	/** Marker capabilities used to gate access-level content. */
	const CAP_VIEW_STUDENT     = 'wpcpm_view_student_content';
	const CAP_VIEW_MENTOR      = 'wpcpm_view_mentor_content';
	const CAP_VIEW_INSTITUTION = 'wpcpm_view_institution_content';
	const CAP_VIEW_SPONSOR     = 'wpcpm_view_sponsor_content';
	const CAP_MANAGE           = 'wpcpm_manage_program';

	/** Option holding the role schema version, so upgrades can re-apply caps. */
	const OPT_VERSION = 'wpcpm_roles_version';

	/**
	 * Bump this when the capability sets below change.
	 */
	const SCHEMA_VERSION = 2;

	/**
	 * The custom roles, keyed by slug.
	 *
	 * @return array<string, array{label: string, cap: string}>
	 */
	public static function custom_roles() {
		return array(
			self::ROLE_STUDENT     => array(
				'label' => __( 'Student', 'wpcredits-program-manager' ),
				'cap'   => self::CAP_VIEW_STUDENT,
			),
			self::ROLE_MENTOR      => array(
				'label' => __( 'Mentor', 'wpcredits-program-manager' ),
				'cap'   => self::CAP_VIEW_MENTOR,
			),
			self::ROLE_INSTITUTION => array(
				'label' => __( 'Institution', 'wpcredits-program-manager' ),
				'cap'   => self::CAP_VIEW_INSTITUTION,
			),
			self::ROLE_SPONSOR     => array(
				'label' => __( 'Sponsor', 'wpcredits-program-manager' ),
				'cap'   => self::CAP_VIEW_SPONSOR,
			),
		);
	}

	/**
	 * Create (or refresh) the custom roles, based on Subscriber.
	 *
	 * Each role gets Subscriber's capability set plus exactly one marker cap, so a
	 * Mentor can reach Mentor-level content and nothing else. Administrator picks
	 * up every marker cap plus the management cap.
	 */
	public static function register() {
		$base_caps = self::subscriber_caps();

		foreach ( self::custom_roles() as $slug => $role ) {
			$caps                 = $base_caps;
			$caps[ $role['cap'] ] = true;

			// remove_role() would drop caps an admin had deliberately added, so an
			// existing role is updated in place instead of recreated.
			$existing = get_role( $slug );
			if ( $existing instanceof WP_Role ) {
				foreach ( $caps as $cap => $grant ) {
					$existing->add_cap( $cap, $grant );
				}
			} else {
				add_role( $slug, $role['label'], $caps );
			}
		}

		$administrator = get_role( self::ROLE_ADMIN );
		if ( $administrator instanceof WP_Role ) {
			foreach ( self::administrator_caps() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
	}

	/**
	 * Every capability the Administrator role is granted by this plugin.
	 *
	 * Derived from the roles rather than listed by hand: adding a module used to
	 * mean remembering to add its marker cap in two places — grant on activation
	 * and remove on uninstall — and missing the second leaves the cap behind on a
	 * site that has removed the plugin.
	 *
	 * @return string[]
	 */
	public static function administrator_caps() {
		$caps = array();

		foreach ( self::custom_roles() as $role ) {
			$caps[] = $role['cap'];
		}

		$caps[] = self::CAP_MANAGE;

		return $caps;
	}

	/**
	 * Re-apply roles when the schema version moves, covering sites updated by
	 * dropping in new files rather than by re-activating.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_VERSION ) !== self::SCHEMA_VERSION ) {
			self::register();
		}
	}

	/**
	 * Remove the custom roles. Only called on uninstall.
	 *
	 * Users holding a removed role keep their account but lose its capabilities,
	 * so accounts are reassigned to Subscriber first.
	 */
	public static function unregister() {
		foreach ( array_keys( self::custom_roles() ) as $slug ) {
			$users = get_users(
				array(
					'role'   => $slug,
					'fields' => 'ID',
					'number' => -1,
				)
			);

			foreach ( $users as $user_id ) {
				$user = new WP_User( $user_id );
				$user->remove_role( $slug );
				if ( empty( $user->roles ) ) {
					$user->set_role( 'subscriber' );
				}
			}

			remove_role( $slug );
		}

		$administrator = get_role( self::ROLE_ADMIN );
		if ( $administrator instanceof WP_Role ) {
			foreach ( self::administrator_caps() as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}

		delete_option( self::OPT_VERSION );
	}

	/**
	 * Subscriber's capabilities, used as the base for every custom role.
	 *
	 * Falls back to a literal `read` grant on the rare site where the Subscriber
	 * role has been removed outright.
	 *
	 * @return array<string, bool>
	 */
	private static function subscriber_caps() {
		$subscriber = get_role( 'subscriber' );

		if ( $subscriber instanceof WP_Role && ! empty( $subscriber->capabilities ) ) {
			return $subscriber->capabilities;
		}

		return array( 'read' => true );
	}

	/**
	 * Whether a user holds one of the program roles.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @param string           $role Role slug.
	 * @return bool
	 */
	public static function user_has_role( $user, $role ) {
		$user = self::resolve_user( $user );

		return $user instanceof WP_User && in_array( $role, (array) $user->roles, true );
	}

	/**
	 * A user ID out of whatever `get_users()` handed back.
	 *
	 * **The `fields` argument cannot be trusted on every stack.** Asked for `'ID'`, WordPress is
	 * documented to return a flat list of IDs — and on this program's site it returns `stdClass`
	 * rows anyway, because something in the stack filters the query. Casting the entry straight to
	 * `int` then raises "Object of class stdClass could not be converted to int" on every render,
	 * which is exactly the warning this was introduced to stop.
	 *
	 * So the shape is not assumed. An int, a numeric string, a `stdClass` row and a `WP_User` all
	 * resolve; anything else is 0.
	 *
	 * @param mixed $entry One element of a `get_users()` result.
	 * @return int User ID, or 0.
	 */
	public static function id_of( $entry ) {
		if ( is_object( $entry ) ) {
			return isset( $entry->ID ) ? (int) $entry->ID : 0;
		}

		return is_numeric( $entry ) ? (int) $entry : 0;
	}

	/**
	 * Normalize a user argument to a WP_User.
	 *
	 * @param int|WP_User|null $user User ID, object, or null for the current user.
	 * @return WP_User|null
	 */
	public static function resolve_user( $user = null ) {
		if ( $user instanceof WP_User ) {
			return $user;
		}

		if ( is_numeric( $user ) && $user > 0 ) {
			$resolved = get_user_by( 'id', (int) $user );
			return $resolved ? $resolved : null;
		}

		$current = wp_get_current_user();

		return ( $current instanceof WP_User && $current->exists() ) ? $current : null;
	}
}
