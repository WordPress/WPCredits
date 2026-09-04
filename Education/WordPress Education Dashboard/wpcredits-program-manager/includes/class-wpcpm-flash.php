<?php
/**
 * One-shot messages that survive a redirect and then go away.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries "Saved", "Canceled" and the rest across the redirect that follows a form post.
 *
 * These used to travel as a query argument - `?wpcpm_call=cancelled`. That works exactly
 * once: the argument is still in the address bar afterwards, so **reloading the page shows
 * the message again**, and so does going back to it, and so does anyone the URL is sent to.
 * "That call is canceled" then sits there permanently, describing something that happened
 * once, which reads as the page being broken.
 *
 * A flash is read *and deleted* in the same breath, so the message appears on the page the
 * redirect lands on and nowhere else. Stored per user in user meta rather than in a
 * transient keyed by session, because these flows all require a logged-in user and user
 * meta cannot be read by the wrong person.
 *
 * View state - which mentor a manager is inspecting, which student card is focused - stays
 * in the URL, where it belongs: that is a description of the page, and it *should* survive
 * a reload.
 */
class WPCPM_Flash {

	/** User meta holding the pending messages, keyed by channel. */
	const META = 'wpcpm_flash';

	/**
	 * Queue a message for the next page this user loads.
	 *
	 * @param string $channel Message channel, e.g. `call` or `availability`.
	 * @param mixed  $value   Anything serializable. Usually an outcome slug.
	 * @param int    $user_id Optional user; defaults to the current user.
	 */
	public static function set( $channel, $value, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$pending = self::pending( $user_id );

		$pending[ (string) $channel ] = $value;

		update_user_meta( $user_id, self::META, $pending );
	}

	/**
	 * Read a message and clear it.
	 *
	 * Memoized per request: a renderer may reasonably ask twice - once to decide whether
	 * to draw a message area at all, once for the text - and the second ask must not come
	 * back empty because the first consumed it.
	 *
	 * @param string $channel Message channel.
	 * @param int    $user_id Optional user; defaults to the current user.
	 * @return mixed The value, or an empty string when there is nothing pending.
	 */
	public static function take( $channel, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$channel = (string) $channel;

		if ( ! $user_id ) {
			return '';
		}

		static $taken = array();

		$memo = $user_id . '|' . $channel;

		if ( array_key_exists( $memo, $taken ) ) {
			return $taken[ $memo ];
		}

		$pending = self::pending( $user_id );

		if ( ! array_key_exists( $channel, $pending ) ) {
			$taken[ $memo ] = '';

			return '';
		}

		$value = $pending[ $channel ];

		unset( $pending[ $channel ] );

		// Cleared before the value is returned, so a fatal further down the render cannot
		// leave the message queued to reappear on the next page too.
		if ( empty( $pending ) ) {
			delete_user_meta( $user_id, self::META );
		} else {
			update_user_meta( $user_id, self::META, $pending );
		}

		$taken[ $memo ] = $value;

		return $value;
	}

	/**
	 * Everything currently queued for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private static function pending( $user_id ) {
		$pending = get_user_meta( (int) $user_id, self::META, true );

		return is_array( $pending ) ? $pending : array();
	}
}
