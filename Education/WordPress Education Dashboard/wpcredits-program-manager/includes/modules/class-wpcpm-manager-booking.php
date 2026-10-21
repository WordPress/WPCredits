<?php
/**
 * The booking link on a program manager's own profile (1.111.0).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The address a sponsor presses to book a call with its program manager, kept on that manager's
 * own user profile.
 *
 * A sponsor's contact block used to take this link from the Team Members table, through the
 * option the sponsors sync wrote. Since 1.111.0 a sponsor's assigned manager is a site user
 * (design of 23 September 2026, decision 5), so the link is a field on that user's profile,
 * `WPCPM_Sponsors_Index::manager_of()` reads it through `url_for()`, and the migration seeds it
 * once from the held team by address. The field is drawn and saved only on a program manager's
 * profile, because the design assigns a sponsor to a program manager and to nobody else.
 *
 * Only an https address is kept, because the link is printed on the Sponsor Dashboard as
 * something a sponsor presses: the page escapes it on the way out as well, and this rule keeps
 * anything but a secure web address from being stored at all. `url_for()` applies the same rule
 * to whatever it reads, so a value that reached the meta some other way (by hand, or through a
 * tool that skips `set()`) is never printed as a link.
 */
final class WPCPM_Manager_Booking {

	/** The user meta that holds the link; the profile form's field carries the same name. */
	const META = 'wpcpm_booking_url';

	/**
	 * Draw the field on both profile screens (a manager's own, and another user's opened by
	 * somebody who may edit it) and save it from both updates.
	 */
	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'render_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_field' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_field' ) );
	}

	/**
	 * A user's booking link.
	 *
	 * @param int $user_id User ID.
	 * @return string An https address, or '' when the user has none.
	 */
	public static function url_for( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id < 1 ) {
			return '';
		}

		return self::clean( get_user_meta( $user_id, self::META, true ) );
	}

	/**
	 * Set or clear a user's booking link.
	 *
	 * @param int    $user_id User ID.
	 * @param string $url     An https address to keep, or '' to clear the link.
	 * @return bool Whether the user now holds exactly what was asked for; false, with the link
	 *              already there left alone, for anything but an https address or '', and for an
	 *              account that does not exist.
	 */
	public static function set( $user_id, $url ) {
		$user_id = (int) $user_id;

		// Checked, because user meta takes any ID and would keep a row for nobody.
		if ( $user_id < 1 || false === get_userdata( $user_id ) ) {
			return false;
		}

		$url = trim( (string) $url );

		if ( '' === $url ) {
			delete_user_meta( $user_id, self::META );

			return '' === self::url_for( $user_id );
		}

		$clean = self::clean( $url );

		if ( '' === $clean ) {
			return false;
		}

		update_user_meta( $user_id, self::META, $clean );

		// update_user_meta() answers false for a value that did not change as well as for a
		// failed write, so the stored link itself says whether the user holds the one asked for.
		return self::url_for( $user_id ) === $clean;
	}

	/**
	 * The profile row, on a program manager's profile only, for the manager or for somebody who
	 * may edit them.
	 *
	 * WordPress shows another user's profile only to somebody who may edit that user; the check
	 * is repeated here so the row never depends on which screen fired the action.
	 *
	 * @param WP_User $user The profile being drawn.
	 */
	public static function render_field( $user ) {
		if ( ! $user instanceof WP_User || ! self::may_edit( $user->ID ) || ! user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		// `pattern` lets the browser say "https only" before the form is sent: set() refuses
		// anything else, and a refusal on its own would leave the old link in place with nothing
		// on the screen to say so.
		printf(
			'<table class="form-table" role="presentation"><tr><th><label for="%1$s">%2$s</label></th><td>'
			. '<input type="url" name="%1$s" id="%1$s" value="%3$s" class="regular-text code" pattern="https://.+" aria-describedby="%1$s-description" />'
			. '<p class="description" id="%1$s-description">%4$s</p></td></tr></table>',
			esc_attr( self::META ),
			esc_html__( 'Booking link', 'wpcredits-program-manager' ),
			esc_attr( self::url_for( $user->ID ) ),
			esc_html__( 'The link a sponsor presses to book a call with you. An https address.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Save the field from the profile form.
	 *
	 * WordPress verifies the form's own nonce (`update-user_<ID>`) and the right to edit the
	 * user before it fires either update action. Both are checked again here, because another
	 * screen can fire the same actions, and the row is saved only on a program manager's profile,
	 * the one the field is drawn on. A form without the field leaves the link alone.
	 *
	 * @param int $user_id The profile being saved.
	 * @return bool Whether the link was set or cleared.
	 */
	public static function save_field( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
			return false;
		}

		if ( ! self::may_edit( $user_id ) || ! user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return false;
		}

		// null, not the '' that clears the link: a form without the field, or with anything but a
		// single value in it, leaves the link as it is.
		$posted = WPCPM_Request::posted_verbatim( self::META, null );

		if ( null === $posted ) {
			return false;
		}

		return self::set( $user_id, $posted );
	}

	/**
	 * Whether the signed-in user may change this user's profile: the user themself, or somebody
	 * WordPress lets edit them.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function may_edit( $user_id ) {
		$user_id = (int) $user_id;

		return $user_id > 0 && ( get_current_user_id() === $user_id || current_user_can( 'edit_user', $user_id ) );
	}

	/**
	 * A link as it may be kept: an https address with a host, or ''.
	 *
	 * The host is checked here because `esc_url_raw()` alone would keep `https://` with nothing
	 * after it.
	 *
	 * @param mixed $url A candidate link.
	 * @return string
	 */
	private static function clean( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';

		if ( 1 !== preg_match( '#^https://[^/\s?\#]+#i', $url ) ) {
			return '';
		}

		return (string) esc_url_raw( $url, array( 'https' ) );
	}
}
