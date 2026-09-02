<?php
/**
 * Which accounts must present a second factor at login, and what they present.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The program's two-factor policy, laid over the Two Factor plugin.
 *
 * The plugin (`two-factor`, published by WordPress.org) owns the codes, the login step and the
 * rate limiting. It has no notion of who *must* use it: a person turns it on for themselves or
 * they do not. This class is the missing half, and it belongs here because this plugin is what
 * knows the difference between a student, a mentor, an institution and a program manager.
 *
 * **The mechanism, and why it is this one.** `Two_Factor_Core::wp_login()` asks
 * `is_user_using_two_factor()`, which reads the list of providers enabled for the account, and
 * that list runs through the `two_factor_enabled_providers_for_user` filter before it is
 * returned. So adding a provider here makes the second step appear at the account's very next
 * login, with nothing for the account holder to set up first.
 *
 * That is the whole design, and the alternative is worse in a way that is easy to miss: letting
 * people sign in with a password and then blocking the site until they enrol an authenticator
 * leaves a window in which somebody holding a stolen password signs in and enrols *their own*
 * authenticator, ending up better attached to the account than its owner. Turning on emailed
 * codes by role closes that window, because the second factor exists before anyone has
 * configured anything. An emailed code is only as good as the mailbox, which is why the account
 * is then nudged towards an authenticator app; it is not the destination, it is the floor.
 *
 * **Deliberately not enforced for students.** A student account holds that student's own work
 * and nothing else, there are hundreds of them, and there is no helpdesk to unlock the ones who
 * change phone and lose an inbox. They may turn it on for themselves, and the roles that can
 * see other people's data may not turn it off.
 *
 * Nothing here runs unless the Two Factor plugin is active, and the policy is a setting, so a
 * program manager can widen or narrow it without a release.
 */
class WPCPM_Two_Factor {

	/** The provider every enforced account gets for free, needing no setup. */
	const PROVIDER_EMAIL = 'Two_Factor_Email';

	/** The provider an account is nudged towards, once it has been set up. */
	const PROVIDER_TOTP = 'Two_Factor_Totp';

	/** Single-use codes, the way back in when the phone is gone. */
	const PROVIDER_BACKUP = 'Two_Factor_Backup_Codes';

	/**
	 * Register the policy, if there is a plugin to lay it over.
	 */
	public static function init() {
		if ( ! self::available() ) {
			return;
		}

		add_filter( 'two_factor_enabled_providers_for_user', array( __CLASS__, 'enabled_providers' ), 10, 2 );
		add_filter( 'two_factor_primary_provider_for_user', array( __CLASS__, 'primary_provider' ), 10, 2 );
	}

	/**
	 * Whether the Two Factor plugin is present and booted.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'Two_Factor_Core' );
	}

	/**
	 * The roles this site requires a second factor from.
	 *
	 * The stored setting, narrowed to roles that exist, because a role slug left behind by a
	 * renamed module must not silently mean "nobody". Filterable so a site can decide this in
	 * code; the filter runs last and is the final word.
	 *
	 * @return string[]
	 */
	public static function required_roles() {
		$stored = (array) WPCPM_Settings::get_value( 'two_factor_roles', array() );
		$roles  = array();

		foreach ( $stored as $role ) {
			$role = sanitize_key( $role );

			if ( '' !== $role && ( function_exists( 'get_role' ) ? get_role( $role ) instanceof WP_Role : true ) ) {
				$roles[] = $role;
			}
		}

		/**
		 * Filter the roles that must present a second factor.
		 *
		 * @param string[] $roles Role slugs.
		 */
		return array_values( array_unique( (array) apply_filters( 'wpcpm_two_factor_roles', $roles ) ) );
	}

	/**
	 * Whether this account must present a second factor.
	 *
	 * An account holding several roles is judged on all of them: a mentor who is also an
	 * administrator is an administrator, and the stronger requirement wins.
	 *
	 * @param int|WP_User $user The account.
	 * @return bool
	 */
	public static function is_required( $user ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		$required = self::required_roles();

		if ( empty( $required ) ) {
			return false;
		}

		return (bool) array_intersect( (array) $user->roles, $required );
	}

	/**
	 * The providers this account may use at login.
	 *
	 * Adds emailed codes to an enforced account that has enabled nothing, which is what makes
	 * the second step appear without the account holder having done anything first. An account
	 * that has already chosen its providers is left exactly as it is: the point is a floor, not
	 * a ceiling, and somebody who set up an authenticator has already cleared it.
	 *
	 * @param array $providers Provider class names the account has enabled.
	 * @param int   $user_id   The account.
	 * @return array
	 */
	public static function enabled_providers( $providers, $user_id ) {
		$providers = (array) $providers;

		if ( ! empty( $providers ) || ! self::is_required( $user_id ) ) {
			return $providers;
		}

		// Only what the plugin actually offers: a class name it does not know is dropped by the
		// plugin anyway, and would leave an enforced account with an empty list, which reads as
		// "no second factor" and would open the very door this class exists to close.
		if ( ! array_key_exists( self::PROVIDER_EMAIL, self::providers() ) ) {
			return $providers;
		}

		return array( self::PROVIDER_EMAIL );
	}

	/**
	 * Which provider the login screen offers first.
	 *
	 * An authenticator app once there is one, because it is the stronger factor and the one the
	 * account is being nudged towards; emailed codes otherwise. Only ever narrowed to something
	 * the account has actually enabled.
	 *
	 * @param string $provider The plugin's own choice.
	 * @param int    $user_id  The account.
	 * @return string
	 */
	public static function primary_provider( $provider, $user_id ) {
		$enabled = (array) Two_Factor_Core::get_enabled_providers_for_user( $user_id );

		if ( in_array( self::PROVIDER_TOTP, $enabled, true ) ) {
			return self::PROVIDER_TOTP;
		}

		return $provider;
	}

	/**
	 * Whether this account has set up something better than an emailed code.
	 *
	 * @param int|WP_User $user The account.
	 * @return bool
	 */
	public static function has_app( $user ) {
		if ( ! self::available() ) {
			return false;
		}

		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		return in_array( self::PROVIDER_TOTP, (array) Two_Factor_Core::get_enabled_providers_for_user( $user->ID ), true );
	}

	/**
	 * The providers the plugin offers on this site.
	 *
	 * @return array Class name to label.
	 */
	public static function providers() {
		return self::available() ? (array) Two_Factor_Core::get_providers() : array();
	}

	/**
	 * The setup screen's address, which is the one place the Two Factor plugin puts its controls.
	 *
	 * The plugin renders its options on the wp-admin profile screen and nowhere else. Mentors and
	 * students on this site are routed to a front-end Report Card and have no reason to open
	 * wp-admin at all, so without a link from where they actually are, "optional" would mean
	 * "impossible in practice". `replace_admin_dashboard()` redirects only `index.php`, so the
	 * profile screen itself is reachable by everyone who has an account.
	 *
	 * @return string
	 */
	public static function setup_url() {
		return admin_url( 'profile.php#two-factor-options' );
	}

	/**
	 * A card on a Report Card, telling this person where their account stands.
	 *
	 * Three states, because the honest message is different in each: an account that is required
	 * to use a second factor and has only the emailed one is told it is covered and offered
	 * something better; an account that has an authenticator app is told so once, quietly, with
	 * backup codes named because that is the part people skip; and an account nobody requires it
	 * of is invited rather than nagged. Nothing is printed when the plugin is absent.
	 *
	 * @param int|WP_User $user The person reading.
	 */
	public static function prompt( $user ) {
		if ( ! self::available() ) {
			return;
		}

		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		$using    = Two_Factor_Core::is_user_using_two_factor( $user->ID );
		$app      = self::has_app( $user );
		$required = self::is_required( $user );

		if ( $app ) {
			return;
		}

		if ( $required ) {
			$heading = __( 'Your account asks for a code when you sign in', 'wpcredits-program-manager' );
			$body    = __( 'Because your account can see other people\'s details, signing in needs a second step as well as your password. Right now that code is emailed to you. An authenticator app on your phone is quicker and does not depend on your email, and it takes about a minute to set up.', 'wpcredits-program-manager' );
			$action  = __( 'Set up an authenticator app', 'wpcredits-program-manager' );
		} elseif ( $using ) {
			return;
		} else {
			$heading = __( 'Add a second step to your sign-in', 'wpcredits-program-manager' );
			$body    = __( 'A password on its own can be guessed or reused. Adding a code from an authenticator app means that knowing your password is not enough to get into your account. It is optional, and it takes about a minute.', 'wpcredits-program-manager' );
			$action  = __( 'Turn on two-factor authentication', 'wpcredits-program-manager' );
		}

		printf(
			'<aside class="wpcpm-2fa%1$s"><h2 class="wpcpm-2fa__title">%2$s</h2><p class="wpcpm-2fa__body">%3$s</p><p class="wpcpm-2fa__actions"><a class="wpcpm-2fa__action" href="%4$s">%5$s</a></p><p class="wpcpm-2fa__note">%6$s</p></aside>',
			$required ? ' wpcpm-2fa--required' : '',
			esc_html( $heading ),
			esc_html( $body ),
			esc_url( self::setup_url() ),
			esc_html( $action ),
			esc_html__( 'While you are there, save the backup codes it offers. They are how you get back in if you lose the phone.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * How the policy stands right now, for the settings screen.
	 *
	 * Counted live rather than stored: the number is small, it is read on one admin screen, and
	 * a stored count that drifts from the truth on a security screen is worse than no count.
	 *
	 * @return array{roles: array<string, array{label: string, total: int, covered: int, app: int}>, available: bool, enforced: int, uncovered: int}
	 */
	public static function status() {
		$out = array(
			'available' => self::available(),
			'roles'     => array(),
			'enforced'  => 0,
			'uncovered' => 0,
		);

		if ( ! $out['available'] ) {
			return $out;
		}

		// Accounts, not memberships: somebody who is both a mentor and an administrator is one
		// person to protect, and counting them twice would make the screen overstate the work.
		$seen = array();

		foreach ( self::required_roles() as $role ) {
			$object = get_role( $role );
			$names  = wp_roles()->get_names();

			$row = array(
				'label'   => isset( $names[ $role ] ) ? $names[ $role ] : $role,
				'total'   => 0,
				'covered' => 0,
				'app'     => 0,
			);

			if ( ! $object instanceof WP_Role ) {
				$out['roles'][ $role ] = $row;
				continue;
			}

			$users = get_users(
				array(
					'role'   => $role,
					'fields' => 'ID',
					'number' => -1,
				)
			);

			foreach ( $users as $user_id ) {
				$user_id = (int) $user_id;
				$using   = Two_Factor_Core::is_user_using_two_factor( $user_id );

				++$row['total'];

				if ( $using ) {
					++$row['covered'];
				}

				if ( self::has_app( $user_id ) ) {
					++$row['app'];
				}

				if ( isset( $seen[ $user_id ] ) ) {
					continue;
				}

				$seen[ $user_id ] = true;
				++$out['enforced'];

				if ( ! $using ) {
					++$out['uncovered'];
				}
			}

			$out['roles'][ $role ] = $row;
		}

		return $out;
	}
}
