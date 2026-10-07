<?php
/**
 * Rolling in-database log.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the most recent sync events so the admin screen can explain itself.
 */
class WCAC_Logger {

	const OPTION = 'wcac_log';
	const LIMIT  = 200;

	/**
	 * Redaction bounds. A log context is an explanation, not a payload: a run
	 * that needs more than this to explain itself needs a message, not a bigger
	 * array, and every extra byte is one more place a secret can hide.
	 */
	const REDACTED         = '[redacted]';
	const CONTEXT_ENTRIES  = 20;
	const CONTEXT_CHARS    = 200;
	const CONTEXT_DEPTH    = 4;
	const CONTEXT_KEY_MASK = '/pass|secret|token|auth|key|cred/i';

	/**
	 * Append an entry, trimming the log to LIMIT rows.
	 *
	 * @param string $level   One of info|warn|error.
	 * @param string $message Human-readable message.
	 * @param array  $context Optional extra data, redacted before it is stored.
	 * @return void
	 */
	public static function log( $level, $message, array $context = array() ) {
		$log = get_option( self::OPTION, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'level'   => in_array( $level, array( 'info', 'warn', 'error' ), true ) ? $level : 'info',
				'message' => (string) $message,
				'context' => self::redact( $context ),
			)
		);

		update_option( self::OPTION, array_slice( $log, 0, self::LIMIT ), false );
	}

	/**
	 * Strip anything credential-shaped out of a context before it is stored.
	 *
	 * readme.txt promises this, and this method is where the promise is kept.
	 * The trap it exists for is a context that carries a WP_Http $args array:
	 * the Campus Connect request puts a live Authorization header in there, and
	 * the row it would land in is read by wp option get and by every database
	 * dump, neither of which goes anywhere near render_log().
	 *
	 * @param array $context Raw context.
	 * @param int   $depth   Nesting depth of this call, counted from zero.
	 * @return array Context safe to write to wp_options.
	 */
	private static function redact( array $context, $depth = 0 ) {
		$out  = array();
		$kept = 0;

		foreach ( $context as $key => $value ) {
			if ( $kept >= self::CONTEXT_ENTRIES ) {
				$out['(truncated)'] = sprintf( '%d further item(s) dropped.', count( $context ) - $kept );

				break;
			}

			++$kept;

			if ( is_string( $key ) && preg_match( self::CONTEXT_KEY_MASK, $key ) ) {
				$out[ $key ] = self::REDACTED;
				continue;
			}

			if ( is_array( $value ) ) {
				$out[ $key ] = $depth >= self::CONTEXT_DEPTH ? '(nested)' : self::redact( $value, $depth + 1 );
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = self::scrub( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = $value;
			} else {
				// An object can hold a credential in a property this walk cannot
				// reach, and neither an object nor a resource survives the option
				// round-trip in a form the log table could print. Name the type.
				$out[ $key ] = is_object( $value ) ? '(' . get_class( $value ) . ')' : '(' . gettype( $value ) . ')';
			}
		}

		return $out;
	}

	/**
	 * Scrub one string value: credential shapes out, length capped.
	 *
	 * The second pattern is the WordPress application password as the profile
	 * screen shows it, six groups of four. The third is the same secret after
	 * WCAC_Settings::normalise_secret() has taken the spaces out, which is the
	 * form this plugin stores and therefore the form it could pass on.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function scrub( $value ) {
		$text = (string) $value;

		if ( preg_match( '/^(?:Basic|Bearer)\s+\S+/i', trim( $text ) ) ) {
			return self::REDACTED;
		}

		if ( preg_match( '/(?:^|\s)[A-Za-z0-9]{4}(?:[ -][A-Za-z0-9]{4}){5}(?:\s|$)/', $text ) ) {
			return self::REDACTED;
		}

		if ( preg_match( '/^[A-Za-z0-9]{24}$/', trim( $text ) ) ) {
			return self::REDACTED;
		}

		return mb_strlen( $text ) > self::CONTEXT_CHARS
			? mb_substr( $text, 0, self::CONTEXT_CHARS - 3 ) . '...'
			: $text;
	}

	/**
	 * Most recent entries, newest first.
	 *
	 * @param int $limit How many to return.
	 * @return array
	 */
	public static function recent( $limit = 50 ) {
		$log = get_option( self::OPTION, array() );

		return is_array( $log ) ? array_slice( $log, 0, $limit ) : array();
	}

	/**
	 * Empty the log.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
