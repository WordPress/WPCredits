<?php
/**
 * The private directory under uploads, and the probe that says whether it is private.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the signed agreements will live, and whether the host serves them to anyone who asks.
 *
 * The directory is `wp-content/uploads/wpcpm-private/`, made with an `index.php` and an
 * `.htaccess` that denies everything under both Apache syntaxes. That is the most a plugin can
 * do from PHP, and on this program's host it is not enough: Atomic serves uploads through
 * nginx, which never reads `.htaccess`, and a request for a file that exists there is answered
 * with the file (open question 10 of the design spec, answered by probing the host). So the
 * plugin never prints a file's URL anywhere, every download goes through a handler that checks
 * the capability and the institution first, and the file names carry 128 bits of entropy.
 *
 * `probe()` is how the site finds out what its own host does, rather than assuming. It writes a
 * throwaway file, asks for it over HTTP the way a stranger would, records the answer in
 * `wpcpm_private_probe` and deletes the file. The storage card on the Institutions screen reads
 * that record and says either "the host blocks direct requests" or, as a warning, "ask the host
 * to block this path". Run at activation and by a button; never on a schedule, because the
 * answer only changes when the host does.
 *
 * `path()` is the one way a stored relative path becomes an absolute one. It resolves through
 * `realpath()` and refuses anything that lands outside the base, so a stored value that was
 * tampered with, or a `..` that reached the database somehow, cannot read `wp-config.php`.
 */
class WPCPM_Private_Files {

	/** Option holding the last probe's result: `status`, `time`, `blocked`, `error`. */
	const OPTION_PROBE = 'wpcpm_private_probe';

	/** The directory's name under the uploads base. */
	const DIRECTORY = 'wpcpm-private';

	/** How long the probe waits for its own site to answer, in seconds. */
	const PROBE_TIMEOUT = 10;

	/**
	 * Statuses that mean the host refused to hand the file over.
	 *
	 * 404 is in the list on purpose: the file exists while the request is made, so "not found"
	 * from the host means the location is hidden, which is the outcome wanted. The one way this
	 * misreads is an uploads base URL pointing at a mirror that has not copied the file yet,
	 * which this program's host does not do.
	 */
	const BLOCKED_STATUSES = array( 401, 403, 404, 405, 410 );

	/**
	 * The directory, with a trailing slash.
	 *
	 * Read from `wp_upload_dir()` on every call rather than cached: the uploads base can be
	 * filtered per request, and a cached path from one request is wrong on the next.
	 *
	 * @return string Absolute filesystem path ending in a slash.
	 */
	public static function base() {
		$upload  = wp_upload_dir( null, false );
		$basedir = isset( $upload['basedir'] ) ? (string) $upload['basedir'] : '';

		return trailingslashit( $basedir ) . self::DIRECTORY . '/';
	}

	/**
	 * The URL path a host would be asked to block, e.g. `/wp-content/uploads/wpcpm-private/`.
	 *
	 * This is the only URL-shaped value this class exposes, and it names the directory, never
	 * a file. The storage card prints it in the warning that asks the host to block it.
	 *
	 * @return string
	 */
	public static function url_path() {
		$path = wp_parse_url( self::base_url(), PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? $path : '/wp-content/uploads/' . self::DIRECTORY . '/';
	}

	/**
	 * Make sure the directory exists and carries its two guard files.
	 *
	 * The guard files are written only when missing, so a host or an administrator who edits
	 * them keeps the edit. Both Apache syntaxes are in the `.htaccess`, because a 2.2 host reads
	 * `Deny from all` and a 2.4 host reads `Require all denied`, and the wrong one alone is a
	 * 500 on the former and nothing on the latter.
	 *
	 * @return true|WP_Error
	 */
	public static function ensure() {
		$base = self::base();

		if ( ! wp_mkdir_p( $base ) ) {
			return new WP_Error(
				'wpcpm_private_dir',
				sprintf(
					/* translators: %s: directory path */
					__( 'The private directory %s could not be created.', 'wpcredits-program-manager' ),
					$base
				)
			);
		}

		if ( ! wp_is_writable( $base ) ) {
			return new WP_Error(
				'wpcpm_private_dir',
				sprintf(
					/* translators: %s: directory path */
					__( 'The private directory %s is not writable.', 'wpcredits-program-manager' ),
					$base
				)
			);
		}

		$guards = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => self::htaccess(),
		);

		foreach ( $guards as $name => $content ) {
			if ( file_exists( $base . $name ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Two guard files inside a directory this class owns under uploads; WP_Filesystem would ask for credentials on a request that has none to give, and a directory without its guards is worse than a direct write.
			if ( false === file_put_contents( $base . $name, $content ) ) {
				return new WP_Error(
					'wpcpm_private_dir',
					sprintf(
						/* translators: %s: file name */
						__( 'The private directory guard file %s could not be written.', 'wpcredits-program-manager' ),
						$name
					)
				);
			}
		}

		return true;
	}

	/**
	 * Ask the host what it does with a direct request to the private directory.
	 *
	 * Always records something, even when the request could not be made, so the storage card
	 * never shows a stale "blocked" from a previous host after a migration. A request error
	 * leaves `status` at 0 and `blocked` false with the error in words; the card reads that as
	 * "the probe could not tell", not as either verdict.
	 *
	 * @return array{status: int, time: int, blocked: bool, error: string}
	 */
	public static function probe() {
		$result = array(
			'status'  => 0,
			'time'    => time(),
			'blocked' => false,
			'error'   => '',
		);

		$ensured = self::ensure();

		if ( is_wp_error( $ensured ) ) {
			$result['error'] = $ensured->get_error_message();

			update_option( self::OPTION_PROBE, $result, false );

			return $result;
		}

		// Alphanumeric only: the name becomes part of a URL and of a path, and it is deleted a
		// moment later, so it needs to be unguessable for the length of one request and nothing more.
		$name = 'probe-' . wp_generate_password( 24, false, false ) . '.txt';
		$path = self::base() . $name;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A throwaway file in the plugin's own directory, deleted in this same request once the host has answered for it.
		if ( false === file_put_contents( $path, "probe\n" ) ) {
			$result['error'] = __( 'The probe file could not be written.', 'wpcredits-program-manager' );

			update_option( self::OPTION_PROBE, $result, false );

			return $result;
		}

		// Redirects are followed: a canonical-host redirect that ends at the file is the file
		// being served, and a redirect that ends somewhere else answers with that page's code,
		// which errs towards the warning rather than towards silence.
		$response = wp_remote_head(
			self::base_url() . $name,
			array(
				'timeout'     => self::PROBE_TIMEOUT,
				'redirection' => 3,
			)
		);

		wp_delete_file( $path );

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
		} else {
			$result['status']  = (int) wp_remote_retrieve_response_code( $response );
			$result['blocked'] = in_array( $result['status'], self::BLOCKED_STATUSES, true );
		}

		update_option( self::OPTION_PROBE, $result, false );

		return $result;
	}

	/**
	 * The last probe's result, or null when none has run or the record is not one.
	 *
	 * @return array{status: int, time: int, blocked: bool, error: string}|null
	 */
	public static function probe_result() {
		$stored = get_option( self::OPTION_PROBE );

		if ( ! is_array( $stored ) || ! isset( $stored['status'], $stored['time'], $stored['blocked'] ) ) {
			return null;
		}

		return array(
			'status'  => (int) $stored['status'],
			'time'    => (int) $stored['time'],
			'blocked' => (bool) $stored['blocked'],
			'error'   => isset( $stored['error'] ) ? (string) $stored['error'] : '',
		);
	}

	/**
	 * What a probe result means, in one word the storage card can branch on.
	 *
	 * `blocked` is the host refusing; `served` is a 2xx, the host handing the file over;
	 * `unknown` is everything else: a request that failed, a redirect that was not followed to
	 * an answer, a 5xx. Three states rather than a boolean because "we could not tell" must
	 * never be printed as either of the other two.
	 *
	 * @param array $result A probe result.
	 * @return string `blocked`, `served` or `unknown`.
	 */
	public static function verdict( array $result ) {
		$status = isset( $result['status'] ) ? (int) $result['status'] : 0;

		if ( ! empty( $result['blocked'] ) ) {
			return 'blocked';
		}

		if ( $status >= 200 && $status < 300 ) {
			return 'served';
		}

		return 'unknown';
	}

	/**
	 * The absolute path a stored relative path points at, or false.
	 *
	 * `realpath()` on both ends, so symlinks and `..` are resolved before the comparison, and
	 * the base itself and its sub-directories are refused along with anything outside: a
	 * caller asking for a file must get a file. A path that does not exist yet is false too,
	 * which is right for reading and is not what a writer wants; a writer builds its own path
	 * under `base()` and never comes through here.
	 *
	 * @param string $relative Path relative to the base, as stored on a post.
	 * @return string|false
	 */
	public static function path( $relative ) {
		$relative = (string) $relative;

		if ( '' === $relative || false !== strpos( $relative, "\0" ) ) {
			return false;
		}

		$base = realpath( self::base() );

		if ( false === $base ) {
			return false;
		}

		$base = rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR;
		$full = realpath( self::base() . ltrim( $relative, '/\\' ) );

		if ( false === $full || ! is_file( $full ) ) {
			return false;
		}

		if ( 0 !== strpos( $full, $base ) ) {
			return false;
		}

		return $full;
	}

	/**
	 * The directory's public URL, with a trailing slash.
	 *
	 * Private on purpose. The probe is the only thing that ever requests a file in this
	 * directory by URL; everything else reaches the bytes through `path()` and a handler.
	 *
	 * @return string
	 */
	private static function base_url() {
		$upload  = wp_upload_dir( null, false );
		$baseurl = isset( $upload['baseurl'] ) ? (string) $upload['baseurl'] : '';

		return trailingslashit( $baseurl ) . self::DIRECTORY . '/';
	}

	/**
	 * The `.htaccess` that denies everything, in both Apache syntaxes.
	 *
	 * @return string
	 */
	private static function htaccess() {
		return "# WPCredits Program Manager: signed agreements are served only through the plugin.\n"
			. "<IfModule mod_authz_core.c>\n"
			. "\tRequire all denied\n"
			. "</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n"
			. "\tOrder deny,allow\n"
			. "\tDeny from all\n"
			. "</IfModule>\n";
	}
}
