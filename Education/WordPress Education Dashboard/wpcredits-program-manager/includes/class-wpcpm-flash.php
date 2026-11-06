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
 * A string, an array of them at any depth, or any other scalar comes back exactly as it was
 * given. Core's metadata API unslashes every value it writes (`update_metadata()` passes it
 * through `wp_unslash()`), and the values handed in here are unslashed already, so every write
 * hands it a slashed copy (`wp_slash()`). Written as it stands, a value would lose a backslash
 * on every write, and one that waits while another channel is taken is written again: a path,
 * or what somebody typed into a form that was refused, would come back altered and still look
 * right. An object's strings are not covered: `wp_slash()` leaves an object as it is, while
 * core's unslash reaches the strings inside it.
 *
 * View state - which mentor a manager is inspecting, which student card is focused - stays
 * in the URL, where it belongs: that is a description of the page, and it *should* survive
 * a reload.
 */
class WPCPM_Flash {

	/** User meta holding the pending messages, keyed by channel. */
	const META = 'wpcpm_flash';

	/**
	 * The channel prefix a refused form's kept values wait under, one channel per form (1.123.58).
	 * Apart from `WPCPM_Typed_Text::KEPT_CHANNEL`, so the two stores never read each other's entries.
	 */
	const KEPT_CHANNEL = 'kept:';

	/**
	 * How long a refused form's kept values wait for the form to be drawn again, in seconds: a refusal
	 * lands on its page at once, so an entry older than this is from a page that never drew the form.
	 * The same ten minutes `WPCPM_Typed_Text::KEPT_FOR` gives its boxes.
	 */
	const KEPT_FOR = 600;

	/**
	 * The most characters one kept value holds: above the longest box any signed-in form draws (the
	 * enrollment list's paste box, 300 rows), so a crafted post keeps no more than a box could hold.
	 */
	const KEPT_MAX = 60000;

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

		update_user_meta( $user_id, self::META, wp_slash( $pending ) );
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
			update_user_meta( $user_id, self::META, wp_slash( $pending ) );
		}

		$taken[ $memo ] = $value;

		return $value;
	}

	/**
	 * Clear, unread, every channel a test picks.
	 *
	 * For a value worth something only for a while, whose page may never come to take it: left
	 * alone, it would wait in user meta for good. What is left is written in one go, as the slashed
	 * copy `set()` and `take()` hand core, so it comes back as it was given; when nothing is picked,
	 * nothing is written.
	 *
	 * @param callable $stale   Called with each channel's name and value; true clears that channel.
	 * @param int      $user_id Optional user; defaults to the current user.
	 */
	public static function sweep( callable $stale, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$pending = self::pending( $user_id );
		$left    = array();

		foreach ( $pending as $channel => $value ) {
			if ( ! call_user_func( $stale, (string) $channel, $value ) ) {
				$left[ $channel ] = $value;
			}
		}

		if ( count( $left ) === count( $pending ) ) {
			return;
		}

		if ( empty( $left ) ) {
			delete_user_meta( $user_id, self::META );
		} else {
			update_user_meta( $user_id, self::META, wp_slash( $left ) );
		}
	}

	/**
	 * Keep what a person posted to a form that was refused, and why each field was refused, for the
	 * one time the form is drawn again (1.123.58).
	 *
	 * For the forms of a signed-in person: the dashboards' and wp-admin's. A public form keeps its
	 * values behind `WPCPM_Form_Stash`, since its sender has no account, and a form of one box keeps
	 * through `WPCPM_Typed_Text::keep_box()`, which cuts to the box's own room. A refused form is drawn
	 * again from what `kept()` hands back, never from the stored record.
	 *
	 * A value is a string, with a line break read as one LF, or a list of strings (the boxes of a
	 * checklist). A string over `KEPT_MAX` characters is not kept at all, never kept cut, and neither
	 * is an item of a list over it, so a form drawn again never shows a box holding part of what was
	 * typed as if it were the whole; the rest of the form is kept. A string that is not valid UTF-8,
	 * and anything that is neither, is not kept. A problem is a field and one sentence. When nothing
	 * is left to keep, nothing is stored. Every keep first clears the person's kept entries older than `KEPT_FOR`, for
	 * whatever form, and any value on such a channel that is not a kept entry (`sweep()`), so a form
	 * that is never drawn again does not leave what was typed in user meta for good.
	 *
	 * @param string $form     The form, by what it does and the record it edits, such as
	 *                         `sponsor-edit:42`.
	 * @param array  $values   Each field and what was posted for it, unslashed and not cleaned.
	 * @param array  $problems Each refused field and the one sentence that says why.
	 */
	public static function keep( $form, array $values, array $problems = array() ) {
		self::sweep(
			static function ( $channel, $entry ) {
				if ( 0 !== strpos( $channel, self::KEPT_CHANNEL ) ) {
					return false;
				}

				return ! is_array( $entry ) || ! isset( $entry['at'] ) || time() - (int) $entry['at'] > self::KEPT_FOR;
			}
		);

		$kept = array();

		foreach ( $values as $field => $value ) {
			if ( is_array( $value ) ) {
				$list = array();

				foreach ( $value as $item ) {
					if ( is_string( $item ) && mb_check_encoding( $item, 'UTF-8' ) && mb_strlen( $item, 'UTF-8' ) <= self::KEPT_MAX ) {
						$list[] = $item;
					}
				}

				$kept[ (string) $field ] = $list;

				continue;
			}

			if ( ! is_scalar( $value ) || is_bool( $value ) || ! mb_check_encoding( (string) $value, 'UTF-8' ) ) {
				continue;
			}

			$value = str_replace( "\r\n", "\n", (string) $value );

			if ( mb_strlen( $value, 'UTF-8' ) > self::KEPT_MAX ) {
				continue;
			}

			$kept[ (string) $field ] = $value;
		}

		$why = array();

		foreach ( $problems as $field => $sentence ) {
			if ( is_string( $sentence ) && '' !== trim( $sentence ) ) {
				$why[ (string) $field ] = $sentence;
			}
		}

		if ( array() === $kept && array() === $why ) {
			return;
		}

		self::set(
			self::KEPT_CHANNEL . $form,
			array(
				'at'       => time(),
				'values'   => $kept,
				'problems' => $why,
			)
		);
	}

	/**
	 * What a refused form kept for its redraw, handed back once and cleared (1.123.58).
	 *
	 * Both keys are always there, so a form reads `$kept['values']` and `$kept['problems']` without a
	 * test. An entry older than `KEPT_FOR` hands back nothing and is cleared all the same: one still
	 * waiting is from a page that never drew the form, and it must not turn up on a later visit.
	 *
	 * @param string $form The form, named as `keep()` was given it.
	 * @return array{values: array, problems: array<string, string>}
	 */
	public static function kept( $form ) {
		$none  = array(
			'values'   => array(),
			'problems' => array(),
		);
		$entry = self::take( self::KEPT_CHANNEL . $form );

		if ( ! is_array( $entry ) || ! isset( $entry['at'] ) || time() - (int) $entry['at'] > self::KEPT_FOR ) {
			return $none;
		}

		return array(
			'values'   => isset( $entry['values'] ) && is_array( $entry['values'] ) ? $entry['values'] : array(),
			'problems' => isset( $entry['problems'] ) && is_array( $entry['problems'] ) ? $entry['problems'] : array(),
		);
	}

	/**
	 * Drop what an earlier refusal of a form kept, because the form has been posted again: every
	 * handler that keeps calls this first, so a refusal whose page never drew the form cannot fill
	 * the boxes after a save that went through (1.123.58).
	 *
	 * @param string $form The form, named as `keep()` was given it.
	 */
	public static function forget( $form ) {
		self::take( self::KEPT_CHANNEL . $form );
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
