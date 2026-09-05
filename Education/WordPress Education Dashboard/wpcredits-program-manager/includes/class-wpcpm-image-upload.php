<?php
/**
 * The plugin's one image handler.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accepts an image by its bytes, re-saves it through WordPress's editor, stores it.
 *
 * Design spec of 4 September 2026, section 8.1, pulled forward to Phase S1 because the
 * sponsors sync copies each Approved sponsor's logo out of Airtable into the Media Library:
 * an Airtable attachment URL expires within hours, and a dashboard that hotlinked one would
 * show a broken image by the afternoon.
 *
 * The rules, each a refusal on its own: the file exists; it is within `logo_max_kb`; the
 * MIME from `finfo` and the type from `getimagesize()` agree and are PNG, JPEG or WebP; the
 * name it came with, when one did, names the same type; the width is at least `MIN_WIDTH`
 * and no side passes `MAX_SIDE`; and the bytes stored are the ones `wp_get_image_editor()`
 * wrote, so metadata is stripped and nothing the uploader put after the image data survives.
 * SVG is refused: it is a document that can carry script, and the two sponsors who hold one
 * convert it. Nothing here calls the core function that moves an upload on the strength of
 * its claimed type; every byte is re-checked instead.
 */
final class WPCPM_Image_Upload {

	/** Narrower than this and the logo box on the dashboard is mostly gap. */
	const MIN_WIDTH = 200;

	/** Longer than this and the editor re-saving it is a memory bill, not a logo. */
	const MAX_SIDE = 4000;

	/** The types accepted, by the MIME `finfo` reports, and the extension each is stored under. */
	const TYPES = array(
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
	);

	/** Seconds a download may take before it is a refusal. */
	const DOWNLOAD_TIMEOUT = 20;

	/**
	 * Check a file on disk and re-save it.
	 *
	 * @param string $path  A readable file.
	 * @param array  $rules `max_kb` (int, else the setting) and `name` (the name the file came with, or '').
	 * @return array|WP_Error `path` (the re-saved copy, the caller's to store or delete), `mime`, `ext`, `width`, `height`, `size`.
	 */
	public static function accept( $path, array $rules = array() ) {
		$path = (string) $path;

		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'wpcpm_image_missing', __( 'No file arrived.', 'wpcredits-program-manager' ) );
		}

		$size = (int) filesize( $path );

		if ( $size < 1 || $size > self::max_bytes( $rules ) ) {
			return new WP_Error( 'wpcpm_image_size', __( 'The image is larger than the site allows.', 'wpcredits-program-manager' ) );
		}

		$mime = self::type_of( $path );

		if ( '' === $mime || ! isset( self::TYPES[ $mime ] ) ) {
			return new WP_Error( 'wpcpm_image_type', __( 'The file is not a PNG, JPEG or WebP image.', 'wpcredits-program-manager' ) );
		}

		// Two readers of the bytes must agree: `finfo` on the header, `getimagesize()` on the
		// image structure. A file that fools one and not the other is not an image.
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a corrupt file is a refusal, not a warning in the log.

		if ( ! is_array( $info ) || empty( $info['mime'] ) || $info['mime'] !== $mime ) {
			return new WP_Error( 'wpcpm_image_type', __( 'The file is not a PNG, JPEG or WebP image.', 'wpcredits-program-manager' ) );
		}

		$name = isset( $rules['name'] ) ? (string) $rules['name'] : '';

		if ( '' !== $name && ! self::name_matches( $name, $mime ) ) {
			return new WP_Error( 'wpcpm_image_name', __( 'The file\'s name says one kind of image and its bytes another.', 'wpcredits-program-manager' ) );
		}

		$width  = (int) $info[0];
		$height = (int) $info[1];

		if ( $width < self::MIN_WIDTH || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $height < 1 ) {
			return new WP_Error(
				'wpcpm_image_dimensions',
				sprintf(
					/* translators: 1: the least width, 2: the longest side. */
					__( 'The image must be at least %1$d pixels wide and no side may pass %2$d pixels.', 'wpcredits-program-manager' ),
					self::MIN_WIDTH,
					self::MAX_SIDE
				)
			);
		}

		$editor = wp_get_image_editor( $path );

		if ( is_wp_error( $editor ) || ! is_object( $editor ) || ! method_exists( $editor, 'save' ) ) {
			return new WP_Error( 'wpcpm_image_editor', __( 'This site cannot process images right now.', 'wpcredits-program-manager' ) );
		}

		// tempnam() creates a zero-byte file to reserve the name; the editor saves under that
		// name plus an extension, so the placeholder itself would stay behind for ever. Its
		// uniqueness is spent once the name is in hand, and the file goes.
		$placeholder = tempnam( get_temp_dir(), 'wpcpm-image-' );
		$copy        = $placeholder . '.' . self::TYPES[ $mime ];
		wp_delete_file( $placeholder );
		$saved = $editor->save( $copy, $mime );

		if ( is_wp_error( $saved ) || ! is_array( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
			return new WP_Error( 'wpcpm_image_editor', __( 'The image could not be re-saved.', 'wpcredits-program-manager' ) );
		}

		return array(
			'path'   => (string) $saved['path'],
			'mime'   => $mime,
			'ext'    => self::TYPES[ $mime ],
			'width'  => $width,
			'height' => $height,
			'size'   => (int) filesize( $saved['path'] ),
		);
	}

	/**
	 * Put an accepted image into the Media Library.
	 *
	 * @param array  $accepted What `accept()` returned.
	 * @param string $name     The base name to store under, without extension.
	 * @param int    $author   The attachment's author; 0 for the sync.
	 * @param string $title    The attachment's title, e.g. "Weglot logo (colour)".
	 * @return int|WP_Error The attachment ID.
	 */
	public static function store( array $accepted, $name, $author, $title ) {
		$upload = wp_upload_dir();

		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['path'] ) ) {
			return new WP_Error( 'wpcpm_image_store', __( 'The uploads directory is not writable.', 'wpcredits-program-manager' ) );
		}

		$base = sanitize_file_name( trim( (string) $name ) );
		$base = '' === $base ? 'image' : $base;
		$file = wp_unique_filename( $upload['path'], $base . '.' . $accepted['ext'] );
		$dest = trailingslashit( $upload['path'] ) . $file;

		// A copy and a delete rather than a rename: the temporary directory and the uploads
		// directory are on different filesystems on the host, where rename() fails silently.
		if ( ! copy( $accepted['path'], $dest ) ) {
			// The re-saved copy was never moved anywhere the caller can find it, and the
			// caller only holds this path to store or delete: a refusal must not leave a
			// stray file behind for nothing to clean up afterwards.
			wp_delete_file( $accepted['path'] );

			return new WP_Error( 'wpcpm_image_store', __( 'The image could not be written to the uploads directory.', 'wpcredits-program-manager' ) );
		}

		// A temporary file that may already be gone; wp_delete_file() is a no-op either way.
		wp_delete_file( $accepted['path'] );

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $accepted['mime'],
				'post_title'     => sanitize_text_field( (string) $title ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => (int) $author,
			),
			$dest,
			0,
			true
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		wp_update_attachment_metadata( (int) $id, wp_generate_attachment_metadata( (int) $id, $dest ) );

		return (int) $id;
	}

	/**
	 * Fetch an image from a URL, accept it and store it.
	 *
	 * @param string $url    Where the image is; http or https only.
	 * @param string $name   The name it came with (Airtable's filename); its extension must match the bytes.
	 * @param int    $author The attachment's author; 0 for the sync.
	 * @param string $title  The attachment's title.
	 * @param array  $rules  As for `accept()`; `name` is taken from `$name`.
	 * @return int|WP_Error The attachment ID.
	 */
	public static function sideload( $url, $name, $author, $title, array $rules = array() ) {
		$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'wpcpm_image_url', __( 'That is not an address an image can be fetched from.', 'wpcredits-program-manager' ) );
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp = download_url( $url, self::DOWNLOAD_TIMEOUT );

		if ( is_wp_error( $temp ) ) {
			return new WP_Error( 'wpcpm_image_download', $temp->get_error_message() );
		}

		$rules['name'] = (string) $name;
		$accepted      = self::accept( $temp, $rules );

		// The download is read once and never kept.
		wp_delete_file( $temp );

		if ( is_wp_error( $accepted ) ) {
			return $accepted;
		}

		$base = pathinfo( (string) $name, PATHINFO_FILENAME );

		return self::store( $accepted, '' === $base ? 'logo' : $base, $author, $title );
	}

	/**
	 * The size ceiling in bytes: the rule's `max_kb`, else the `logo_max_kb` setting.
	 *
	 * @param array $rules The caller's rules.
	 * @return int
	 */
	public static function max_bytes( array $rules ) {
		$kb = isset( $rules['max_kb'] ) ? (int) $rules['max_kb'] : (int) WPCPM_Settings::get_value( 'logo_max_kb', 1024 );

		return max( 1, $kb ) * 1024;
	}

	/**
	 * The MIME type `finfo` reads off the bytes, or ''.
	 *
	 * @param string $path A readable file.
	 * @return string
	 */
	private static function type_of( $path ) {
		if ( ! class_exists( 'finfo' ) ) {
			return '';
		}

		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->file( $path );

		return is_string( $mime ) ? strtolower( $mime ) : '';
	}

	/**
	 * Whether a filename's extension names the type the bytes are.
	 *
	 * @param string $name The name the file came with.
	 * @param string $mime What the bytes are.
	 * @return bool
	 */
	private static function name_matches( $name, $mime ) {
		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( '' === $ext ) {
			return true;
		}

		$aliases = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
		);

		return isset( $aliases[ $ext ] ) && $aliases[ $ext ] === $mime;
	}
}
