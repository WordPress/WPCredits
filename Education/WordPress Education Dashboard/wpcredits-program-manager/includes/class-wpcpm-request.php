<?php
/**
 * Reading view state out of the current request.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitized reads of the arguments that describe *which page* this is.
 *
 * Which mentor a manager is inspecting, which month a calendar is showing, which student
 * card is open: all of it read-only, all of it safe to take from the query string, and all
 * of it previously written out longhand at eleven separate sites in three slightly
 * different shapes.
 *
 * Collecting it here buys two things. The `unslash → sanitize → default` sequence is
 * written once instead of eleven times, so it cannot be got subtly wrong in the twelfth
 * place; and the `phpcs:ignore` that says "this is view state, not form data" lives beside
 * the three reads it describes rather than being copied next to every caller - where, as it
 * turned out, several had drifted onto the wrong line and stopped applying at all.
 *
 * **This is not a substitute for verifying a nonce.** Nothing here proves the request was
 * intended. Anything that *changes* state must still check a nonce and a capability in its
 * own handler; these methods are for deciding what to render.
 */
class WPCPM_Request {

	/**
	 * A slug-shaped argument, such as an outcome flag.
	 *
	 * @param string $name     Query argument name.
	 * @param string $fallback Value when the argument is absent.
	 * @return string
	 */
	public static function key( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state; see the class docblock.
		if ( ! isset( $_GET[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		return sanitize_key( wp_unslash( $_GET[ $name ] ) );
	}

	/**
	 * A free-text argument, such as a date or an Airtable record ID.
	 *
	 * The caller still validates the shape - `sanitize_text_field()` only guarantees this
	 * is a harmless string, never that it is a date or a record that exists.
	 *
	 * @param string $name     Query argument name.
	 * @param string $fallback Value when the argument is absent.
	 * @return string
	 */
	public static function text( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state; see the class docblock.
		if ( ! isset( $_GET[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		return sanitize_text_field( wp_unslash( $_GET[ $name ] ) );
	}

	/**
	 * A positive integer argument, such as a user ID being inspected.
	 *
	 * Returns 0 when absent or not a number, which every caller already treats as "no
	 * selection" - so there is no separate absent-versus-zero case to handle. An array is
	 * not a number either: `absint()` casts a non-empty array to 1, so without the scalar
	 * check `?wpcpm_export_student_id[]=x` named user 1 while this docblock said 0.
	 *
	 * @param string $name Query argument name.
	 * @return int
	 */
	public static function id( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state; see the class docblock.
		if ( ! isset( $_GET[ $name ] ) || ! is_scalar( $_GET[ $name ] ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		return absint( wp_unslash( $_GET[ $name ] ) );
	}

	/**
	 * Whether a login actually asked to be sent somewhere in particular.
	 *
	 * `login_redirect` hands over a `$requested_redirect_to`, and the obvious reading - "if
	 * it is not empty, the visitor asked for somewhere, so honour it" - is wrong. Core's
	 * login form carries a hidden `redirect_to` field, and when the visitor did not ask for
	 * anywhere its value is `admin_url()`. So *every* ordinary login arrives looking like an
	 * explicit request for the admin dashboard, and a filter that steps aside for a non-empty
	 * value steps aside always.
	 *
	 * The admin root therefore counts as "nothing was asked for". Anything else - a gated
	 * page somebody was bounced off, a specific admin screen - is a real request and is
	 * honoured, which is what the guard was for in the first place.
	 *
	 * @param string $requested The `$requested_redirect_to` passed to `login_redirect`.
	 * @return bool
	 */
	public static function is_explicit_redirect( $requested ) {
		$requested = trim( (string) $requested );

		if ( '' === $requested ) {
			return false;
		}

		// Compared without a trailing slash, and against the network's admin too: on
		// multisite the form's default can be either.
		$roots = array( untrailingslashit( admin_url() ) );

		if ( is_multisite() ) {
			$roots[] = untrailingslashit( network_admin_url() );
		}

		return ! in_array( untrailingslashit( $requested ), $roots, true );
	}

	/**
	 * A slug-shaped argument from a posted form.
	 *
	 * The counterpart to `key()`, and needed for the same reason `posted_id()` is: a form
	 * that posts to `admin-post.php` puts its fields in `$_POST`, and reading `$_GET` for one
	 * of them does not fail - it silently returns the fallback, so the feature works and
	 * quietly does the wrong thing. That is exactly how the sample-invitation control came to
	 * send the student template whichever button was pressed.
	 *
	 * Only for reading view state inside a handler that has already verified its nonce and
	 * capability. It does not verify anything itself.
	 *
	 * @param string $name     Field name.
	 * @param string $fallback Value when the field is absent.
	 * @return string
	 */
	public static function posted_key( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		return sanitize_key( wp_unslash( $_POST[ $name ] ) );
	}

	/**
	 * A positive integer from a posted form.
	 *
	 * Only for reading *view state* back off a form - which student a manager was looking
	 * at when they saved - inside a handler that has already verified its nonce and
	 * capability. It does not verify anything itself. Like `id()`, an array is 0, not the 1
	 * that `absint()` would make of it.
	 *
	 * @param string $name Field name.
	 * @return int
	 */
	public static function posted_id( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		return absint( wp_unslash( $_POST[ $name ] ) );
	}

	/**
	 * Free text from a posted form.
	 *
	 * The counterpart to `text()`, for a filter that has to survive the round trip through
	 * `admin-post.php` - which sees the form's fields and not the query string of the screen the
	 * form was on. `sanitize_key()` would not do: an institution's name has spaces, capitals and
	 * punctuation in it.
	 *
	 * Same standing as the rest of this class: it says what was posted, and proves nothing. The
	 * handler has already checked the nonce and the capability, and whatever this returns is
	 * matched against values the site itself holds rather than trusted.
	 *
	 * @param string $name     Field name.
	 * @param string $fallback Returned when the field is absent.
	 * @return string
	 */
	public static function posted_text( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		return trim( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) );
	}

	/**
	 * A posted value that is allowed to have lines in it.
	 *
	 * **`posted_text()` strips newlines, and for a pasted list that is the whole value.**
	 * `sanitize_text_field()` collapses every run of whitespace, so a CSV pasted into a
	 * textarea arrives as one line: the header becomes the first cell and the first student's
	 * name joins it, which the import then refuses for having no email column. It cost a live
	 * import to find, because every test fed the parser a string directly and never came
	 * through the request at all.
	 *
	 * `sanitize_textarea_field()` is the same cleaning with the line breaks left alone.
	 *
	 * @param string $name     Key in `$_POST`.
	 * @param string $fallback Returned when the key is absent or not a scalar.
	 * @return string
	 */
	public static function posted_lines( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		return trim( sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) ) );
	}

	/**
	 * A posted value kept as typed: unslashed, valid UTF-8, control characters dropped, trimmed.
	 *
	 * No tag stripping and no percent-decoding or stripping, because this is for a value that
	 * is a code, not text: a coupon code or a checkout link with `%20` in its query. Core's
	 * sanitize_text_field() removes every percent-encoded character, which silently corrupts
	 * such a value (final review of Phase S2, finding 1). Safe because every caller escapes the
	 * value on output and never prints it raw.
	 *
	 * @param string $name     Key in the posted fields.
	 * @param string $fallback Fallback when the key is absent.
	 * @return string
	 */
	public static function posted_verbatim( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; cleaned below without touching the characters a code is made of.
		$value = wp_check_invalid_utf8( wp_unslash( $_POST[ $name ] ) );

		return trim( (string) preg_replace( '/[^\P{C}\n\r\t]+/u', '', (string) $value ) );
	}

	/**
	 * The lines variant of posted_verbatim(): line breaks kept.
	 *
	 * Each line is trimmed, empty lines are dropped and the rest are joined back with "\n" -
	 * the contract posted_lines() has, so a caller that parses a pasted list can swap one for
	 * the other and only the character-level cleaning changes.
	 *
	 * @param string $name     Key in the posted fields.
	 * @param string $fallback Fallback when the key is absent.
	 * @return string
	 */
	public static function posted_verbatim_lines( $name, $fallback = '' ) {
		$value = self::posted_verbatim( $name, $fallback );

		if ( $value === $fallback ) {
			return $fallback;
		}

		$lines = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $value ) as $line ) {
			$line = trim( $line );

			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}
}
