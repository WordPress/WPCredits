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
	 * A query argument kept exactly as given: unslashed, valid UTF-8, control characters dropped,
	 * and never trimmed.
	 *
	 * For an Airtable column name naming the question being edited. A column name is the
	 * question's key, verbatim, and an Airtable name can end in a space (the design's 4.2):
	 * `text()` would trim it and the question would not be found. Safe because every caller
	 * matches the value against the columns the track holds and prints it escaped.
	 *
	 * @param string $name     Query argument name.
	 * @param string $fallback Value when the argument is absent.
	 * @return string
	 */
	public static function exact( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state; see the class docblock.
		if ( ! isset( $_GET[ $name ] ) || ! is_scalar( $_GET[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; cleaned below without touching the characters a name is made of.
		return self::clean( wp_unslash( $_GET[ $name ] ) );
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
	 * A ticked list sent by GET, as account IDs: whole positive numbers, each once, in the order sent.
	 *
	 * The checkboxes of a list form, which core's own lists send to their screen by GET, so they
	 * arrive in the query string, where `posted_list()` never looks. A value that is not a whole
	 * positive number as written (a word, a sign, a leading zero, a space, a number too long to hold)
	 * is dropped rather than read as some other account, and so is a field that is not a list.
	 *
	 * Same standing as the rest of this class: the handler has already checked the nonce and the
	 * capability, and every ID is still matched against the accounts the site holds.
	 *
	 * @param string $name Query argument name, without the brackets.
	 * @return int[]
	 */
	public static function ids( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_GET[ $name ] ) || ! is_array( $_GET[ $name ] ) ) {
			return array();
		}

		$ids = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; each value is kept only if it is a whole number as written.
		foreach ( wp_unslash( $_GET[ $name ] ) as $value ) {
			if ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $value ) && (string) (int) $value === $value ) {
				$ids[ (int) $value ] = true;
			}
		}

		return array_keys( $ids );
	}

	/**
	 * A ticked list sent by GET, each value kept only when the whole of it matches a pattern: the
	 * query-string twin of `posted_list()`.
	 *
	 * The checkboxes of a list form, which core's own lists send to their screen by GET, holding
	 * what `ids()` cannot read: record IDs rather than account IDs, such as the Airtable records a
	 * list shows for what has no account yet. `ids()` keeps whole numbers alone and `key()`
	 * lowercases, and an Airtable record ID is letters and digits whose case is part of it. Nothing
	 * is cleaned into something else, as in `posted_list()`: a value a sanitizer repaired could look
	 * up a different record from the one that was ticked, so a value that does not match is dropped,
	 * and so are a field that is not a list, a value that is not a string and a repeat.
	 *
	 * Same standing as the rest of this class: the handler has already checked the nonce and the
	 * capability, and what this returns is still matched against what the site holds.
	 *
	 * @param string $name    Query argument name, without the brackets.
	 * @param string $pattern A regular expression; a value is kept only when what it matches is the
	 *                        whole value, whatever its anchors.
	 * @return string[] The matching values, each once, in the order sent.
	 */
	public static function list( $name, $pattern ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_GET[ $name ] ) || ! is_array( $_GET[ $name ] ) ) {
			return array();
		}

		$values = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; each value is kept only if the pattern matches it whole.
		foreach ( wp_unslash( $_GET[ $name ] ) as $value ) {
			// The match has to be the whole value: a `$` without the `D` modifier matches before a
			// trailing newline too, as Airtable's record rule is written, and an unanchored pattern
			// matches inside a longer value.
			if ( is_string( $value ) && 1 === preg_match( $pattern, $value, $matched ) && $matched[0] === $value ) {
				$values[ $value ] = true;
			}
		}

		// array_keys() hands back a key of decimal digits alone as an integer, so each is cast back to the string that was sent.
		return array_map( 'strval', array_keys( $values ) );
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
	 * A posted value as it was typed: unslashed, and not cleaned, trimmed or folded at all.
	 *
	 * `posted_text()` and `posted_lines()` hand back what the cleaner kept, and whether the cleaner
	 * took words away can only be told from what was typed. So this is for three jobs and no other:
	 * `WPCPM_Typed_Text::cleaner_loses()`, which asks that of the typing; the typing a refused form
	 * keeps for the one time it is drawn again (`WPCPM_Typed_Text::keep()`); and a note's length
	 * against its limit, counted as its box counts (`WPCPM_Typed_Text::box_length()`), since the
	 * cleaner writes some characters longer than they were typed. What it returns is never stored
	 * as a field's value and never printed unescaped: a save stores what the cleaning readers
	 * return, and a kept typing is drawn back through `esc_textarea()` or `esc_attr()`.
	 *
	 * Same standing as the rest of this class: the handler has already checked the nonce and the
	 * capability.
	 *
	 * @param string $name     Key in the posted fields.
	 * @param string $fallback Returned when the key is absent or not a scalar.
	 * @return string
	 */
	public static function posted_raw( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; read for the loss check and the kept typing only, never stored as a value or printed unescaped.
		return (string) wp_unslash( $_POST[ $name ] );
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
		return trim( self::clean( wp_unslash( $_POST[ $name ] ) ) );
	}

	/**
	 * A posted value kept exactly as typed: the cleaning of posted_verbatim(), and no trim.
	 *
	 * For an Airtable column name. A question is keyed by its column name, verbatim, and an
	 * Airtable name can end in a space (the design's 4.2): the base's own `Company ` does. Every
	 * other reader here trims, and a column typed with its space would silently become a
	 * different column. Safe for the same reason as posted_verbatim(): the value is matched
	 * against what the site holds and escaped on output.
	 *
	 * @param string $name     Key in the posted fields.
	 * @param string $fallback Fallback when the key is absent.
	 * @return string
	 */
	public static function posted_exact( $name, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; cleaned below without touching the characters a name is made of.
		return self::clean( wp_unslash( $_POST[ $name ] ) );
	}

	/**
	 * Valid UTF-8 with control characters dropped, and nothing else touched.
	 *
	 * @param mixed $value An unslashed scalar.
	 * @return string
	 */
	private static function clean( $value ) {
		$value = wp_check_invalid_utf8( (string) $value );

		return (string) preg_replace( '/[^\P{C}\n\r\t]+/u', '', (string) $value );
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

	/**
	 * A posted list, each value kept only when the whole of it matches a pattern.
	 *
	 * The checkbox lists a form posts as `name[]`. Nothing is cleaned into something else: the
	 * values are keys and record IDs a handler looks up, and a value a sanitizer repaired could look
	 * up a different row from the one that was ticked, so a value that does not match is dropped.
	 * A field that is not a list, a value that is not a string and a repeat are dropped too.
	 *
	 * Same standing as the rest of this class: the handler has already checked the nonce and the
	 * capability, and what this returns is still matched against what the site holds.
	 *
	 * @param string $name    Field name, without the brackets.
	 * @param string $pattern A regular expression each value must match in full, anchored.
	 * @return string[] The matching values, each once, in the order posted.
	 */
	public static function posted_list( $name, $pattern ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller's handler verifies the nonce before reaching here.
		if ( ! isset( $_POST[ $name ] ) || ! is_array( $_POST[ $name ] ) ) {
			return array();
		}

		$values = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; each value is kept only if the pattern matches it whole.
		foreach ( wp_unslash( $_POST[ $name ] ) as $value ) {
			if ( is_string( $value ) && 1 === preg_match( $pattern, $value ) ) {
				$values[ $value ] = true;
			}
		}

		// array_keys() hands back a key of decimal digits alone as an integer, so each is cast back to the string that was posted.
		return array_map( 'strval', array_keys( $values ) );
	}
}
