<?php
/**
 * Plugin settings, stored as a single option.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the connector's configuration.
 */
class WCAC_Settings {

	const OPTION = 'wcac_settings';

	/**
	 * Setting keys refused by the most recent save() call in this request.
	 *
	 * A rejection is a silent no-op at the data layer -- the previously stored
	 * value is kept -- so without this the admin would redirect to "Settings
	 * saved." while the box re-renders the old value and the five syncs keep
	 * using it. last_rejected() lets the caller say so instead.
	 *
	 * @var array
	 */
	private static $rejected = array();

	/**
	 * Defaults, pre-filled with the base this connector was built against.
	 *
	 * central_app_password is a secret: save() never writes it -- set_secret()
	 * and forget_central() are its only two writers -- and it is never rendered
	 * back to a browser.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_key'              => '',
			'base_id'              => 'appoiPJkMFdnJEmfa',
			'tbl_wordcamps'        => 'tblLBVsO4rU2oj2Ki',
			'tbl_meetups'          => 'tblbbyWvUsxF9CHRj',
			'tbl_sessions'         => 'tblO5yMO8waGwqO1l',
			'tbl_speakers'         => 'tbliYsOf6pAZLOzfd',
			'tbl_sponsors'         => 'tblPkKmjbgGpazI6K',
			'tbl_campus_connect'   => 'tbld8niqsLWyNbcVF',
			'sync_wordcamps'       => 1,
			'sync_meetups'         => 1,
			'sync_children'        => 1,
			'sync_campus_connect'  => 0,
			'campus_extra_status'  => '',
			'lookback_hours'       => 25,
			'time_budget'          => 20,
			'source_root'          => 'https://central.wordcamp.org',
			'central_user'         => '',
			'central_app_password' => '',
		);
	}

	/**
	 * The full settings array, defaults merged in.
	 *
	 * Deliberately free of any wp-config overlay: save() starts from all() and
	 * writes the result back wholesale, so an overlay here would copy a
	 * wp-config secret into wp_options on the next unrelated form save. The
	 * constants are read in central_credential() instead.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Read one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Canonical stored form of an application password.
	 *
	 * Deliberately NOT sanitize_text_field(): that strips tags, drops "<" and
	 * removes percent-encoded octets, which silently mutates a pasted secret
	 * into one that can only ever 401. Nothing echoes this value into HTML.
	 * WordPress displays an application password as six space-separated groups
	 * and core's own verifier removes the spaces before comparing, so the
	 * space-free form is canonical.
	 *
	 * @param string $value Raw submitted value.
	 * @return string
	 */
	public static function normalise_secret( $value ) {
		return preg_replace( '/\s+/', '', (string) $value );
	}

	/**
	 * The Central credential, constants first.
	 *
	 * All-or-nothing per source: a constant username with an option password is
	 * NOT mixed, because that combination is almost always a half-finished
	 * migration and guessing at it would send the wrong pair to a third-party
	 * host. Nothing here logs -- it runs on every job.
	 *
	 * @return array array( 'user' => string, 'pass' => string, 'source' => 'constant'|'option'|'none' )
	 */
	public static function central_credential() {
		if ( defined( 'WCAC_CENTRAL_USER' ) && defined( 'WCAC_CENTRAL_APP_PASSWORD' ) ) {
			$user = trim( (string) WCAC_CENTRAL_USER );
			$pass = self::normalise_secret( WCAC_CENTRAL_APP_PASSWORD );

			if ( '' !== $user && '' !== $pass ) {
				return array(
					'user'   => $user,
					'pass'   => $pass,
					'source' => 'constant',
				);
			}
		}

		$all  = self::all();
		$user = trim( (string) $all['central_user'] );
		$pass = self::normalise_secret( $all['central_app_password'] );

		if ( '' !== $user && '' !== $pass ) {
			return array(
				'user'   => $user,
				'pass'   => $pass,
				'source' => 'option',
			);
		}

		return array(
			'user'   => '',
			'pass'   => '',
			'source' => 'none',
		);
	}

	/**
	 * Both halves of the Central credential are present.
	 *
	 * @return bool
	 */
	public static function central_ready() {
		$cred = self::central_credential();

		return 'none' !== $cred['source'];
	}

	/**
	 * Everything the Campus Connect job needs: a credential, the toggle, and a
	 * destination table.
	 *
	 * @return bool
	 */
	public static function campus_connect_ready() {
		return self::central_ready()
			&& ! empty( self::get( 'sync_campus_connect' ) )
			&& '' !== self::table_for( 'campus_connect' );
	}

	/**
	 * A non-reversible fingerprint of the stored credential.
	 *
	 * Used only to notice that the credential CHANGED (to clear the failure
	 * latch) and to show the operator that autofill has not silently replaced
	 * it. Twelve hex characters of a salted SHA-256; never log it, never place
	 * it in a WP_Error message.
	 *
	 * A wp-config credential wins here exactly as it does in
	 * central_credential(), so a constant swap is detected and a stored pair
	 * that the constants override cannot masquerade as a change.
	 *
	 * @param array|null $settings Settings array, or null for the stored one.
	 * @return string Empty string when nothing is stored.
	 */
	public static function credential_fingerprint( $settings = null ) {
		$cred = self::central_credential();

		if ( null === $settings || 'constant' === $cred['source'] ) {
			$user = $cred['user'];
			$pass = $cred['pass'];
		} else {
			$user = isset( $settings['central_user'] ) ? trim( (string) $settings['central_user'] ) : '';
			$pass = isset( $settings['central_app_password'] ) ? self::normalise_secret( $settings['central_app_password'] ) : '';
		}

		if ( '' === $user || '' === $pass ) {
			return '';
		}

		return substr( hash( 'sha256', $user . '|' . $pass . '|' . wp_salt( 'auth' ) ), 0, 12 );
	}

	/**
	 * Write one settings key without touching anything else.
	 *
	 * save()'s checkbox loop is UNCONDITIONAL, so save( array( one key ) ) would
	 * silently set every sync toggle to 0. The CLI and the credential form write
	 * through here instead. The explicit false is the autoload flag: this option
	 * must never be autoloaded, because it holds two secrets.
	 *
	 * wcac_central_credential_changed fires only when the effective credential's
	 * fingerprint actually moves, exactly as save() does it -- see the contract
	 * in wordcamp-airtable-connector.php.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set_secret( $key, $value ) {
		$all = get_option( self::OPTION, array() );

		if ( ! is_array( $all ) ) {
			$all = array();
		}

		$credential = ( 'central_user' === $key || 'central_app_password' === $key );
		$before     = $credential ? self::credential_fingerprint() : '';

		$all[ $key ] = $value;

		update_option( self::OPTION, $all, false );

		// Re-saving an unchanged value must not unlatch a job that is failing to
		// authenticate: the credential form's username box always posts its
		// stored value, so pressing Save credential merely to read the
		// fingerprint would otherwise put a 401ing sync straight back into
		// rotation.
		if ( $credential && self::credential_fingerprint() !== $before ) {
			do_action( 'wcac_central_credential_changed' );
		}
	}

	/**
	 * Remove the stored Central credential.
	 *
	 * save()'s preserve-on-empty rule means the form can never clear it, so a
	 * revoked password would otherwise sit in wp_options indefinitely and travel
	 * in every database export.
	 *
	 * The database copy is removable even while wp-config constants override it
	 * -- that is the whole point of the admin's Remove button under a locked
	 * credential -- but removing an overridden copy changes nothing that goes
	 * on the wire, so it must not fire wcac_central_credential_changed. The
	 * test is the same one set_secret() uses.
	 *
	 * @return void
	 */
	public static function forget_central() {
		$all = get_option( self::OPTION, array() );

		if ( ! is_array( $all ) ) {
			$all = array();
		}

		$before = self::credential_fingerprint();

		$all['central_user']         = '';
		$all['central_app_password'] = '';

		update_option( self::OPTION, $all, false );

		// Measured on the EFFECTIVE credential, not the option row. With the
		// constants set, the row is inert: firing here would clear the Campus
		// Connect block and reset the five-strike counter for a job that is
		// 401ing against a wp-config credential this call cannot touch, which
		// is the exact hazard the changed-fingerprint contract exists for.
		if ( self::credential_fingerprint() !== $before ) {
			do_action( 'wcac_central_credential_changed' );
		}
	}

	/**
	 * Persist a settings array, sanitising every field.
	 *
	 * The API key is left untouched when the submitted value is empty, so that
	 * saving the form without re-typing it does not wipe it. It is not in the
	 * generic loop below: an isset() guard would blank the stored token on every
	 * form save.
	 *
	 * The Central application password is deliberately NOT accepted here at all.
	 * The settings form has no such field, and honouring one would let a
	 * hand-crafted POST to admin-post.php?action=wcac_save create a database
	 * copy that handle_central()'s wp-config check exists to prevent. The
	 * credential has exactly two writers, set_secret() and forget_central().
	 *
	 * @param array $input Raw submitted values.
	 * @return array The stored settings.
	 */
	public static function save( array $input ) {
		self::$rejected = array();

		$current = self::all();
		$clean   = $current;

		if ( isset( $input['api_key'] ) && '' !== trim( $input['api_key'] ) ) {
			$clean['api_key'] = trim( sanitize_text_field( $input['api_key'] ) );
		}

		foreach ( array( 'base_id', 'tbl_wordcamps', 'tbl_meetups', 'tbl_sessions', 'tbl_speakers', 'tbl_sponsors', 'tbl_campus_connect' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = trim( sanitize_text_field( $input[ $key ] ) );
			}
		}

		if ( isset( $input['source_root'] ) ) {
			$raw  = trim( (string) $input['source_root'] );
			$root = self::strip_userinfo( untrailingslashit( esc_url_raw( $raw ) ) );

			if ( '' === $raw && '' !== trim( (string) $current['source_root'] ) ) {
				// An empty box is not a request to clear this field. The admin
				// renders source_root(), which is '' for any stored root that
				// cannot be parsed -- exactly the row maybe_upgrade() declines
				// to rewrite so that an operator can repair it by hand -- so an
				// empty submission is almost always an unrelated save (a table
				// ID, the lookback window) on a screen that could not display
				// the value. Writing '' there would blank the root for all six
				// syncs and fail every job with http_request_failed, under a
				// green "Settings saved.". Keep the stored bytes and say so.
				$root             = $current['source_root'];
				self::$rejected[] = 'source_root';
				WCAC_Logger::log( 'warn', 'Source site rejected: the field was submitted empty while a value is stored. The previous value was kept.' );
			} elseif ( '' !== $raw && ( '' === $root || ! self::root_scheme_allowed( $root ) ) ) {
				$root             = $current['source_root'];
				self::$rejected[] = 'source_root';
				WCAC_Logger::log( 'warn', 'Source site rejected: it must be an https URL unless WCAC_ALLOW_INSECURE_CENTRAL is defined. The previous value was kept.' );
			}

			$clean['source_root'] = $root;
		}

		if ( isset( $input['central_user'] ) ) {
			$user = sanitize_user( trim( (string) $input['central_user'] ), false );

			if ( false !== strpos( $user, ':' ) ) {
				self::$rejected[] = 'central_user';
				WCAC_Logger::log( 'warn', 'Central username rejected: a colon cannot be used in HTTP Basic auth.' );
			} else {
				$clean['central_user'] = $user;
			}
		}

		foreach ( array( 'sync_wordcamps', 'sync_meetups', 'sync_children', 'sync_campus_connect' ) as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		if ( isset( $input['campus_extra_status'] ) ) {
			// Only a label the mapper can actually emit is worth storing: anything
			// else would imply a capability that does not exist.
			$known = defined( 'WCAC_Mapper::CAMPUS_STATUS' ) ? array_values( WCAC_Mapper::CAMPUS_STATUS ) : array();
			$lines = preg_split( '/[\r\n]+/', (string) $input['campus_extra_status'] );
			$keep  = array();

			foreach ( $lines as $line ) {
				$line = trim( sanitize_text_field( $line ) );

				if ( '' !== $line && in_array( $line, $known, true ) ) {
					$keep[] = $line;
				}
			}

			$clean['campus_extra_status'] = implode( "\n", array_values( array_unique( $keep ) ) );
		}

		if ( isset( $input['lookback_hours'] ) ) {
			$clean['lookback_hours'] = max( 1, min( 24 * 365, (int) $input['lookback_hours'] ) );
		}

		if ( isset( $input['time_budget'] ) ) {
			$clean['time_budget'] = max( 5, min( 120, (int) $input['time_budget'] ) );
		}

		update_option( self::OPTION, $clean, false );

		if ( self::credential_fingerprint( $current ) !== self::credential_fingerprint( $clean ) ) {
			do_action( 'wcac_central_credential_changed' );
		}

		return $clean;
	}

	/**
	 * Setting keys refused during the most recent save() call.
	 *
	 * Request-scoped: valid only to the caller that just invoked save(), which
	 * reads it to choose between the "saved" and "save_rejected" notices. An
	 * empty array means every submitted value was accepted.
	 *
	 * @return array List of setting keys.
	 */
	public static function last_rejected() {
		return self::$rejected;
	}

	/**
	 * The configured source root, safe to transmit, print and render.
	 *
	 * The only sanctioned read of source_root anywhere in the plugin.
	 * strip_userinfo() stays private so that a legacy 1.0.x row carrying a
	 * user:pass@ authority cannot reach an outbound request, a WP_Error message,
	 * a log line, WP-CLI stdout or an HTML value attribute through a plain
	 * get( 'source_root' ).
	 *
	 * @return string Absolute scheme/host/port/path root with no userinfo, or an
	 *                empty string when there is no usable host.
	 */
	public static function source_root() {
		return self::strip_userinfo( untrailingslashit( (string) self::get( 'source_root' ) ) );
	}

	/**
	 * One-time repair of a stored source_root that carries userinfo.
	 *
	 * strip_userinfo() runs only over submitted input, and baseline 1.0.x saved
	 * the field through esc_url_raw() alone, which preserves user:pass@. Without
	 * this the dirty row survives every upgrade untouched -- hidden from readers
	 * by source_root(), but still cleartext in wp_options, and so still in every
	 * backup, staging clone and support dump.
	 *
	 * A root that cannot be parsed at all is left alone rather than blanked:
	 * source_root() already returns '' for it, so nothing leaks, and rewriting
	 * would discard a value the operator may still be able to repair by hand.
	 *
	 * The trigger is userinfo in the stored authority, NOT strip_userinfo()
	 * disagreeing with the stored string. strip_userinfo() also drops a query
	 * string, a fragment and a trailing slash, all of which baseline 1.0.x
	 * saved happily, so a string comparison would silently change the URL all
	 * six syncs fetch on admin_init and log a rotate-your-credential alarm at a
	 * site whose root never held one. Read-side stripping (source_root()) is
	 * unconditional, so leaving those rows alone costs nothing.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) || ! isset( $saved['source_root'] ) ) {
			return;
		}

		$stored = (string) $saved['source_root'];
		$parts  = wp_parse_url( $stored );

		if ( ! is_array( $parts ) || ( ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ) ) {
			return;
		}

		$safe = self::strip_userinfo( untrailingslashit( $stored ) );

		if ( '' === $safe || $safe === $stored ) {
			return;
		}

		$saved['source_root'] = $safe;

		update_option( self::OPTION, $saved, false );

		if ( isset( $parts['pass'] ) ) {
			WCAC_Logger::log( 'warn', 'Stored source site rewritten: a user:pass@ authority was removed from it. Any credential it contained should be treated as exposed and rotated.' );
		} else {
			WCAC_Logger::log( 'warn', 'Stored source site rewritten: a username was removed from its authority. No password was stored alongside it.' );
		}
	}

	/**
	 * Remove any user:pass@ authority from a URL.
	 *
	 * esc_url_raw() preserves userinfo, WP_Http honours it as Basic auth, and
	 * WCAC_Source::get() bakes the full URL into its WP_Error messages, which
	 * are logged verbatim and printed on the admin screen. A credential in the
	 * authority would land in wp_options in cleartext.
	 *
	 * @param string $url Absolute URL.
	 * @return string Empty string when there is no usable scheme and host.
	 */
	private static function strip_userinfo( $url ) {
		$url = (string) $url;

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = isset( $parts['path'] ) ? $parts['path'] : '';

		return untrailingslashit( $parts['scheme'] . '://' . $parts['host'] . $port . $path );
	}

	/**
	 * Whether a source root may be used.
	 *
	 * https only, unless WCAC_ALLOW_INSECURE_CENTRAL is defined -- the Campus
	 * Connect sync attaches an Authorization header to this host.
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	private static function root_scheme_allowed( $url ) {
		$scheme = wp_parse_url( (string) $url, PHP_URL_SCHEME );

		if ( 'https' === $scheme ) {
			return true;
		}

		if ( 'http' === $scheme && defined( 'WCAC_ALLOW_INSECURE_CENTRAL' ) && WCAC_ALLOW_INSECURE_CENTRAL ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the connector has everything it needs to talk to Airtable.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$all = self::all();

		return '' !== $all['api_key'] && '' !== $all['base_id'];
	}

	/**
	 * Map an entity slug to its configured Airtable table ID.
	 *
	 * @param string $entity One of wordcamps|meetups|sessions|speakers|sponsors|campus_connect.
	 * @return string Empty string when the entity is unknown.
	 */
	public static function table_for( $entity ) {
		return (string) self::get( 'tbl_' . $entity, '' );
	}
}
