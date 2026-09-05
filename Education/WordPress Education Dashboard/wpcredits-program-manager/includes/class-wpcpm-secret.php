<?php
/**
 * The site's one secret: a key made once, and the seal and unseal every store uses.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AES-256-GCM on a key the site makes once and keeps out of the autoloaded options.
 *
 * Extracted from WPCPM_Private_Files in 1.94.0 (sponsors design spec of 4 September 2026,
 * section 3, decision 1) so that sponsor codes can be sealed at rest with the same key the
 * stored agreements use: one key, one format, one place that can get either wrong. The format
 * and the option name are unchanged, so every file sealed before the extraction still opens.
 *
 * Two things are new here and were never in the store. `seal_for_option()` base64-encodes the
 * blob, because wpdb strips bytes that are not valid UTF-8 before writing a utf8mb4 column and
 * ciphertext is not text; a file on disk needs no such thing. `fingerprint()` is a keyed hash
 * for finding duplicates without unsealing, keyed because a bare SHA-256 of a six-character
 * coupon code is enumerated in seconds by anyone who can read the option.
 */
final class WPCPM_Secret {

	/** Option holding the key, hex-encoded. Deliberately not autoloaded. */
	const OPT_KEY = 'wpcpm_private_key';

	/** The cipher. GCM, so a changed byte is a refusal rather than garbage. */
	const CIPHER = 'aes-256-gcm';

	/** Format version byte that leads every sealed blob. */
	const FORMAT = 1;

	/**
	 * Whether this PHP can encrypt at all.
	 *
	 * @return bool
	 */
	public static function can_encrypt() {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& in_array( self::CIPHER, (array) openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypt, and lay the result out so `unseal()` can take it apart without guessing.
	 *
	 * Format: one version byte, then the 12-byte nonce, then the 16-byte tag, then the
	 * ciphertext. The version byte is what makes a future format change possible without a
	 * migration that has to guess which blobs are which.
	 *
	 * @param string $bytes Plaintext.
	 * @return string|WP_Error Binary.
	 */
	public static function seal( $bytes ) {
		if ( ! self::can_encrypt() ) {
			return new WP_Error(
				'wpcpm_private_cipher',
				__( 'This site cannot encrypt stored values: PHP has no OpenSSL support for AES-256-GCM. Nothing is stored in the clear instead.', 'wpcredits-program-manager' )
			);
		}

		$key = self::key();

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		$nonce  = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( (string) $bytes, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag );

		if ( false === $cipher ) {
			return new WP_Error( 'wpcpm_private_cipher', __( 'The value could not be encrypted.', 'wpcredits-program-manager' ) );
		}

		return chr( self::FORMAT ) . $nonce . $tag . $cipher;
	}

	/**
	 * Take a sealed blob apart and decrypt it.
	 *
	 * @param string $sealed What `seal()` returned.
	 * @return string|WP_Error
	 */
	public static function unseal( $sealed ) {
		if ( ! self::can_encrypt() ) {
			return new WP_Error( 'wpcpm_private_cipher', __( 'This site cannot decrypt stored values: PHP has no OpenSSL support for AES-256-GCM.', 'wpcredits-program-manager' ) );
		}

		$sealed = (string) $sealed;

		// One version byte, twelve of nonce, sixteen of tag, and the ciphertext, which is zero
		// bytes long when the sealed text itself was empty: `seal_for_option()` seals option
		// values, and an empty option value is not a malformed one.
		if ( strlen( $sealed ) < 29 || self::FORMAT !== ord( $sealed[0] ) ) {
			return new WP_Error( 'wpcpm_private_format', __( 'That value is not in a format this store wrote.', 'wpcredits-program-manager' ) );
		}

		$key = self::key();

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		$plain = openssl_decrypt( substr( $sealed, 29 ), self::CIPHER, $key, OPENSSL_RAW_DATA, substr( $sealed, 1, 12 ), substr( $sealed, 13, 16 ) );

		if ( false === $plain ) {
			return new WP_Error(
				'wpcpm_private_tampered',
				__( 'That value did not decrypt. Either it was changed after it was stored, or the site key has been replaced.', 'wpcredits-program-manager' )
			);
		}

		return $plain;
	}

	/**
	 * Seal a text for an option or a meta row.
	 *
	 * Base64, because wpdb strips bytes that are not valid UTF-8 before writing a utf8mb4
	 * column, and about a third of the bytes a cipher produces are exactly that; a blob written
	 * raw would come back unreadable with no error anywhere (plan ruling 1).
	 *
	 * @param string $text Plaintext.
	 * @return string|WP_Error ASCII.
	 */
	public static function seal_for_option( $text ) {
		$sealed = self::seal( (string) $text );

		return is_wp_error( $sealed ) ? $sealed : base64_encode( $sealed );
	}

	/**
	 * The reverse of `seal_for_option()`.
	 *
	 * @param string $stored What was stored.
	 * @return string|WP_Error
	 */
	public static function unseal_from_option( $stored ) {
		$raw = base64_decode( (string) $stored, true );

		if ( false === $raw ) {
			return new WP_Error( 'wpcpm_private_format', __( 'That value is not in a format this store wrote.', 'wpcredits-program-manager' ) );
		}

		return self::unseal( $raw );
	}

	/**
	 * A keyed fingerprint of a text: equal texts, equal fingerprints, and nothing about the
	 * text readable from one (plan ruling 2).
	 *
	 * @param string $text Plaintext.
	 * @return string|WP_Error 64 hex characters.
	 */
	public static function fingerprint( $text ) {
		$key = self::key();

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		return hash_hmac( 'sha256', (string) $text, $key );
	}

	/**
	 * The site's key, made once and kept out of the autoloaded options.
	 *
	 * In the database, while the agreement files are on disk, so that reaching a stored
	 * agreement means reaching both stores. Never printed, never sent, and deliberately not
	 * derived from the WordPress salts, because rotating those is a routine security step that
	 * would otherwise make every stored value unreadable.
	 *
	 * @return string|WP_Error 32 raw bytes.
	 */
	private static function key() {
		$stored = get_option( self::OPT_KEY );

		if ( is_string( $stored ) && '' !== $stored ) {
			$key = self::from_hex( $stored );

			if ( '' !== $key ) {
				return $key;
			}

			return new WP_Error(
				'wpcpm_private_key',
				__( 'The site key for stored values is not readable. Stored values cannot be opened until it is restored from a backup.', 'wpcredits-program-manager' )
			);
		}

		$key = random_bytes( 32 );

		// `add_option()` and not `update_option()`: two requests arriving together must not each
		// make a key and have the second overwrite the first, which would orphan whatever the
		// first one had already encrypted.
		if ( ! add_option( self::OPT_KEY, bin2hex( $key ), '', false ) ) {
			$stored = get_option( self::OPT_KEY );
			$key    = is_string( $stored ) ? self::from_hex( $stored ) : '';

			if ( '' === $key ) {
				return new WP_Error( 'wpcpm_private_key', __( 'The site key for stored values could not be made.', 'wpcredits-program-manager' ) );
			}
		}

		return $key;
	}

	/**
	 * A stored key back as raw bytes, or '' when it is not one.
	 *
	 * @param string $hex The option's value.
	 * @return string
	 */
	private static function from_hex( $hex ) {
		if ( ! preg_match( '/^[0-9a-f]{64}$/', (string) $hex ) ) {
			return '';
		}

		$key = hex2bin( $hex );

		return ( is_string( $key ) && 32 === strlen( $key ) ) ? $key : '';
	}
}
