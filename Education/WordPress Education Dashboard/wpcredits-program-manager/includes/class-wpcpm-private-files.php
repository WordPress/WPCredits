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
 * Atomic serves uploads through nginx, which never reads `.htaccess`, and the host will not
 * add a rule for this plugin. So the guard files are written for hosts that do read them, and
 * the two controls that actually hold here are ones the plugin can apply on its own:
 *
 * 1. **The directory name begins with a dot.** Probing this host on 2 September 2026: a file in
 *    `uploads/wpcpm-probe-plain/` answers 200 with its body, and the same file in
 *    `uploads/.wpcpm-probe-dot/` answers 403 with none. The rule that denies dot-prefixed path
 *    segments is nginx's own and needs nobody's cooperation. It is a host behaviour rather than
 *    a promise, which is why it is not relied on alone and why `probe()` re-checks it.
 * 2. **Every stored file is encrypted at rest**, AES-256-GCM, one random key per site held in a
 *    non-autoloaded option. If the host ever stops refusing dot paths, what it hands over is
 *    ciphertext. The key lives in the database and the bytes live on disk, so reaching the
 *    document means reaching both stores, and reaching either one alone yields nothing.
 *
 * On top of that the plugin never prints a file's URL anywhere, every download goes through a
 * handler that checks the capability and the institution first, and file names carry 128 bits
 * of entropy. `store()` and `read()` are the only way in and out; nothing else touches the bytes.
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
	const OPT_PROBE = 'wpcpm_private_probe';

	/**
	 * The directory's name under the uploads base.
	 *
	 * The leading dot is load-bearing, not decoration: this host answers 403 for any URL with a
	 * dot-prefixed segment and 200 for the same file without one. Renaming it without a dot
	 * turns a refusal into a download.
	 */
	const DIRECTORY = '.wpcpm-private';

	/** What the directory was called before 1.68.0, moved on the next `ensure()`. */
	const LEGACY_DIRECTORY = 'wpcpm-private';

	/** The per-site key that encrypts stored files. Not autoloaded, never printed. */
	const OPT_KEY = WPCPM_Secret::OPT_KEY;

	/** Authenticated encryption: a tampered file fails to decrypt rather than decrypting wrongly. */
	const CIPHER = WPCPM_Secret::CIPHER;

	/** One byte of format version at the head of every stored file, so the format can change. */
	const FORMAT = WPCPM_Secret::FORMAT;

	/**
	 * The extensions this store will write, by name.
	 *
	 * An allowlist rather than a shape test, because the directory lives under the document
	 * root: a name like `x.php` is a name the host could be asked to execute, and no rule about
	 * letters and digits refuses it. The signed agreement is a PDF; that is the whole list until
	 * something else genuinely needs storing.
	 */
	const EXTENSIONS = array( 'pdf' );

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
		self::migrate_legacy();

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
			'status'         => 0,
			'time'           => time(),
			'blocked'        => false,
			'error'          => '',
			'control_status' => 0,
			'encrypted'      => self::can_encrypt(),
		);

		$ensured = self::ensure();

		if ( is_wp_error( $ensured ) ) {
			$result['error'] = $ensured->get_error_message();

			update_option( self::OPT_PROBE, $result, false );

			return $result;
		}

		// Alphanumeric only: the name becomes part of a URL and of a path, and it is deleted a
		// moment later, so it needs to be unguessable for the length of one request and nothing more.
		$name = 'probe-' . wp_generate_password( 24, false, false ) . '.txt';
		$path = self::base() . $name;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A throwaway file in the plugin's own directory, deleted in this same request once the host has answered for it.
		if ( false === file_put_contents( $path, "probe\n" ) ) {
			$result['error'] = __( 'The probe file could not be written.', 'wpcredits-program-manager' );

			update_option( self::OPT_PROBE, $result, false );

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

		$result['control_status'] = self::probe_control( $name );

		update_option( self::OPT_PROBE, $result, false );

		return $result;
	}

	/**
	 * Ask for the same file from a directory whose name has no dot, and report the status.
	 *
	 * This is the control the main probe is measured against. Without it, a host that answers
	 * 403 for everything under uploads and a host that answers 403 only for dot paths look
	 * identical, and the storage card would credit the plugin's directory name for a refusal
	 * that had nothing to do with it. On this program's host the control answers 200 and the
	 * real path answers 403, which is what makes the leading dot worth having.
	 *
	 * Failure is not an error: the control is an explanation, not a control in the other sense,
	 * and a probe that cannot place it simply records 0.
	 *
	 * @param string $name The throwaway file's name.
	 * @return int The HTTP status, or 0 when the check could not be made.
	 */
	private static function probe_control( $name ) {
		$upload  = wp_upload_dir( null, false );
		$basedir = trailingslashit( isset( $upload['basedir'] ) ? (string) $upload['basedir'] : '' ) . self::LEGACY_DIRECTORY . '/';
		$baseurl = trailingslashit( isset( $upload['baseurl'] ) ? (string) $upload['baseurl'] : '' ) . self::LEGACY_DIRECTORY . '/';

		if ( '' === trim( $baseurl, '/' ) || ! wp_mkdir_p( $basedir ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A throwaway file with no content of its own, deleted in this same request.
		if ( false === file_put_contents( $basedir . $name, "control\n" ) ) {
			return 0;
		}

		$response = wp_remote_head(
			$baseurl . $name,
			array(
				'timeout'     => self::PROBE_TIMEOUT,
				'redirection' => 3,
			)
		);

		wp_delete_file( $basedir . $name );
		@rmdir( $basedir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removed when empty; left alone when a legacy file is still waiting for the next ensure() to move it.

		return is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * The last probe's result, or null when none has run or the record is not one.
	 *
	 * @return array{status: int, time: int, blocked: bool, error: string}|null
	 */
	public static function probe_result() {
		$stored = get_option( self::OPT_PROBE );

		if ( ! is_array( $stored ) || ! isset( $stored['status'], $stored['time'], $stored['blocked'] ) ) {
			return null;
		}

		return array(
			'status'         => (int) $stored['status'],
			'time'           => (int) $stored['time'],
			'blocked'        => (bool) $stored['blocked'],
			'error'          => isset( $stored['error'] ) ? (string) $stored['error'] : '',
			// Absent on a record written before the control probe existed, which reads as
			// "not measured" rather than as "the host refused it too".
			'control_status' => isset( $stored['control_status'] ) ? (int) $stored['control_status'] : 0,
			'encrypted'      => isset( $stored['encrypted'] ) ? (bool) $stored['encrypted'] : self::can_encrypt(),
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
	 * Write bytes into the store, encrypted, and return what the caller must remember.
	 *
	 * The only way a file enters this directory. The plaintext never touches the disk: it is
	 * encrypted in memory and the ciphertext is what `file_put_contents()` receives, so there
	 * is no window in which a readable copy exists under a directory the host might serve.
	 *
	 * The returned `path` is relative to `base()`, which is what a post stores; `sha256` is of
	 * the **plaintext**, so a later reader can prove it got back exactly what was uploaded, and
	 * `size` is the plaintext length for display. The name carries 128 bits of entropy and no
	 * hint of the institution, and the extension is fixed by the caller from a checked list
	 * rather than taken from the upload.
	 *
	 * @param string $bytes     The file's contents.
	 * @param string $extension Extension without the dot, letters and digits only.
	 * @return array{path: string, sha256: string, size: int}|WP_Error
	 */
	public static function store( $bytes, $extension = 'pdf' ) {
		$bytes = (string) $bytes;

		if ( '' === $bytes ) {
			return new WP_Error( 'wpcpm_private_empty', __( 'There was nothing to store.', 'wpcredits-program-manager' ) );
		}

		if ( ! in_array( (string) $extension, self::EXTENSIONS, true ) ) {
			return new WP_Error( 'wpcpm_private_extension', __( 'That is not a file extension this store accepts.', 'wpcredits-program-manager' ) );
		}

		$ensured = self::ensure();

		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		$sealed = self::seal( $bytes );

		if ( is_wp_error( $sealed ) ) {
			return $sealed;
		}

		// Year folders keep one directory from growing without limit, and the retention rules
		// in the design spec are expressed in years too.
		$relative = gmdate( 'Y' ) . '/' . bin2hex( random_bytes( 16 ) ) . '.' . $extension;
		$absolute = self::base() . $relative;

		if ( ! wp_mkdir_p( dirname( $absolute ) ) ) {
			return new WP_Error( 'wpcpm_private_dir', __( 'The year directory could not be created.', 'wpcredits-program-manager' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- The plugin's own store, under uploads; WP_Filesystem would ask for credentials on a request that has none, and the bytes are already ciphertext.
		if ( false === file_put_contents( $absolute, $sealed ) ) {
			return new WP_Error( 'wpcpm_private_write', __( 'The file could not be written.', 'wpcredits-program-manager' ) );
		}

		// Owner-only, for the hosts where that means something. It is not the control here.
		@chmod( $absolute, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- A best effort on hosts that honour it; failure changes nothing about the two controls that do the work.

		return array(
			'path'   => $relative,
			'sha256' => hash( 'sha256', $bytes ),
			'size'   => strlen( $bytes ),
		);
	}

	/**
	 * Write a plain-text note into the private directory, for a person to read later.
	 *
	 * Not encrypted, deliberately: the one thing written this way is the inventory of signed
	 * agreements an uninstall leaves behind, and a list nobody can open without the plugin
	 * that is being removed is no inventory. It is only written when the last probe recorded
	 * the directory as blocked from the web, which is the caller's check to make; the file
	 * name is the caller's, under the directory's own root, so nothing here takes a path.
	 *
	 * @param string $name Bare file name, letters, digits, dots, hyphens and underscores.
	 * @param string $text The note.
	 * @return string|WP_Error The relative path written.
	 */
	public static function write_note( $name, $text ) {
		$name = (string) preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name );

		if ( '' === $name || '.' === $name[0] ) {
			return new WP_Error( 'wpcpm_private_name', __( 'That is not a name this store accepts.', 'wpcredits-program-manager' ) );
		}

		$ensured = self::ensure();

		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		$absolute = self::base() . $name;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- The plugin's own store, under uploads, on the same terms as store().
		if ( false === file_put_contents( $absolute, (string) $text ) ) {
			return new WP_Error( 'wpcpm_private_write', __( 'The file could not be written.', 'wpcredits-program-manager' ) );
		}

		@chmod( $absolute, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- A best effort on hosts that honour it.

		return $name;
	}

	/**
	 * Read a stored file back, decrypted, or say why not.
	 *
	 * The counterpart of `store()`, and the only reader. A file whose tag does not verify is
	 * refused rather than returned: with GCM that means the bytes were changed after they were
	 * written, and handing a reviewer a document that is not the one that was signed is the
	 * failure this whole directory exists to prevent.
	 *
	 * @param string $relative Path relative to the base, as stored on the post.
	 * @return string|WP_Error The plaintext.
	 */
	public static function read( $relative ) {
		$absolute = self::path( $relative );

		if ( false === $absolute ) {
			return new WP_Error( 'wpcpm_private_missing', __( 'That file is not in the store.', 'wpcredits-program-manager' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own file by an absolute path `path()` has already resolved and bounded.
		$sealed = file_get_contents( $absolute );

		if ( false === $sealed ) {
			return new WP_Error( 'wpcpm_private_unreadable', __( 'That file could not be read.', 'wpcredits-program-manager' ) );
		}

		return self::unseal( $sealed );
	}

	/**
	 * Delete a stored file. Returns true when it is gone, including when it never existed.
	 *
	 * @param string $relative Path relative to the base.
	 * @return bool
	 */
	public static function forget( $relative ) {
		$absolute = self::path( $relative );

		if ( false === $absolute ) {
			return true;
		}

		wp_delete_file( $absolute );

		return ! file_exists( $absolute );
	}

	/**
	 * Whether this site can encrypt at all, for the storage card and for `store()`.
	 *
	 * @return bool
	 */
	public static function can_encrypt() {
		return WPCPM_Secret::can_encrypt();
	}

	/**
	 * Encrypt. The cipher and the format are WPCPM_Secret's since 1.94.0; this store keeps
	 * the name so the read and write paths above read as they always did.
	 *
	 * @param string $bytes Plaintext.
	 * @return string|WP_Error
	 */
	private static function seal( $bytes ) {
		return WPCPM_Secret::seal( $bytes );
	}

	/**
	 * Decrypt. The cipher and the format are WPCPM_Secret's since 1.94.0; this store keeps
	 * the name so the read and write paths above read as they always did.
	 *
	 * @param string $sealed What is on disk.
	 * @return string|WP_Error
	 */
	private static function unseal( $sealed ) {
		return WPCPM_Secret::unseal( $sealed );
	}

	/**
	 * Move anything the old, undotted directory holds into this one, then remove it.
	 *
	 * 1.67.0 made `uploads/wpcpm-private/`, which this host serves. Nothing had been stored in
	 * it by then, but a site that did have files there must not be left with them in a place
	 * the web can reach, so the move runs on every `ensure()` and is a no-op once done.
	 *
	 * @return void
	 */
	private static function migrate_legacy() {
		$upload = wp_upload_dir( null, false );
		$legacy = trailingslashit( isset( $upload['basedir'] ) ? (string) $upload['basedir'] : '' ) . self::LEGACY_DIRECTORY . '/';

		if ( self::LEGACY_DIRECTORY === self::DIRECTORY || ! is_dir( $legacy ) ) {
			return;
		}

		// `scandir()` and not `glob( '*' )`: glob skips dot-prefixed names, so the `.htaccess`
		// guard would be left behind and the directory could never be removed.
		$found = scandir( $legacy );

		foreach ( (array) $found as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}

			$item = $legacy . $name;

			// The guard files are made fresh in the new directory; nothing else is left behind.
			if ( 'index.php' === $name || '.htaccess' === $name ) {
				wp_delete_file( $item );
				continue;
			}

			$target = self::base() . $name;

			if ( is_dir( $item ) ) {
				wp_mkdir_p( $target );

				foreach ( (array) scandir( $item ) as $inner ) {
					if ( '.' === $inner || '..' === $inner ) {
						continue;
					}

					@rename( $item . '/' . $inner, $target . '/' . $inner ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rename -- A move inside the plugin's own store; a failure leaves the file where it was, which the next run retries.
				}

				@rmdir( $item ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty by the loop above, or left alone.
				continue;
			}

			@rename( $item, $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rename -- As above.
		}

		@rmdir( $legacy ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Only succeeds once it is empty.
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
