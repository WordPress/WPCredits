<?php
/**
 * Is this a PDF, and what does a bounded scan find inside it?
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's one PDF scanner, shared by the two agreement classes.
 *
 * Grown inside `WPCPM_Institution_Agreement` and lifted out here for Phase S4 of the Sponsors
 * module (design spec of 4 September 2026, section 3 decision 1, section 8.2). Two modules
 * accept a signed PDF from somebody outside the program, and a second copy of this code would
 * be a second set of bounds to keep in step.
 *
 * Three questions, deliberately kept apart because they have different answers:
 *
 * - `named_pdf()` asks WordPress's own name-and-type map about the extension and the declared
 *   type. It is about the name.
 * - `has_magic()` and `mime_of()` ask the bytes. A renamed executable passes the first
 *   question and fails these, which is the whole reason all three are called.
 * - `inspect()` is a courtesy scan and is documented as one: two names refuse the file
 *   outright, five are recorded for the reviewer, and a bounded inflate is as far as it goes.
 *   What protects a reviewer is that neither module ever opens the file in a browser.
 *
 * Nothing here reads a superglobal, moves an upload or trusts a claimed type. The caller
 * holds the bytes; this class only answers about them.
 */
final class WPCPM_Pdf_Check {

	/** The five bytes every PDF begins with. */
	const MAGIC = '%PDF-';

	/** The one type either module accepts. */
	const MIME = 'application/pdf';

	/**
	 * Names whose presence refuses the file outright, and the outcome each one flashes.
	 *
	 * Two, and only two. `/Encrypt` because a document nobody can open is not a document the
	 * program can keep, and `/Launch` because it is an action whose only purpose is to run
	 * something on the reader's machine. Everything else the scan finds is a flag. The two
	 * slugs are flash keys in both modules' message maps, so the scanner names the outcome
	 * once and neither caller translates.
	 */
	const SCAN_REFUSALS = array(
		'/Encrypt' => 'agreement-encrypted',
		'/Launch'  => 'agreement-launch',
	);

	/** Names recorded for the reviewer and never a refusal. */
	const SCAN_FLAGS = array( '/JavaScript', '/JS', '/OpenAction', '/AA', '/EmbeddedFile' );

	/**
	 * How much of a file the courtesy scan will inflate.
	 *
	 * A few hundred bytes of zlib can expand into gigabytes, and a scan that helps an
	 * attacker exhaust the site's memory would be worse than no scan at all. Per stream and
	 * in total, both, because either alone is a way round the other.
	 */
	const SCAN_MAX_STREAMS = 200;
	const SCAN_MAX_STREAM  = 2097152;
	const SCAN_MAX_TOTAL   = 8388608;

	/**
	 * Whether the extension and the declared type together say PDF, by WordPress's own map.
	 *
	 * The question about the name. `wp_check_filetype_and_ext()` also re-reads the bytes on
	 * hosts that can, which is why it is asked first and the byte tests are asked anyway.
	 *
	 * A path with nothing behind it answers false. WordPress's map falls back to the name
	 * alone when it cannot read the file, so a missing file would otherwise be called a PDF on
	 * the strength of its extension, and the guard also keeps `finfo` from being asked about a
	 * file that is gone.
	 *
	 * @param string $path A readable temporary file.
	 * @param string $name The name the file came with.
	 * @return bool
	 */
	public static function named_pdf( $path, $name ) {
		$path = (string) $path;

		if ( '' === $path || ! is_readable( $path ) ) {
			return false;
		}

		$checked = wp_check_filetype_and_ext( $path, (string) $name, array( 'pdf' => self::MIME ) );

		$ext  = isset( $checked['ext'] ) ? $checked['ext'] : '';
		$type = isset( $checked['type'] ) ? $checked['type'] : '';

		return 'pdf' === $ext && self::MIME === $type;
	}

	/**
	 * Whether these bytes begin the way a PDF begins.
	 *
	 * @param string $bytes The file's contents.
	 * @return bool
	 */
	public static function has_magic( $bytes ) {
		return self::MAGIC === substr( (string) $bytes, 0, strlen( self::MAGIC ) );
	}

	/**
	 * What the fileinfo extension says a file is, or '' when the host has none.
	 *
	 * @param string $path Path to inspect.
	 * @return string A MIME type, or ''.
	 */
	public static function mime_of( $path ) {
		$path = (string) $path;

		if ( '' === $path || ! is_readable( $path ) || ! function_exists( 'finfo_open' ) ) {
			return '';
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );

		if ( ! $finfo ) {
			return '';
		}

		$type = finfo_file( $finfo, $path );

		// No `finfo_close()`: it has been a no-op since PHP 8.0, where the resource became an
		// object that is freed when this method returns, and the standard flags the call as
		// deprecated. Letting `$finfo` go out of scope is the supported way to close it.
		unset( $finfo );

		return is_string( $type ) ? $type : '';
	}

	/**
	 * Whether a type read off the bytes is one to go on with.
	 *
	 * An empty type is a host with no fileinfo extension, and it agrees: the magic bytes and
	 * the name map still run, so the refusal that matters still happens, and such a host is
	 * not told it may never accept an agreement.
	 *
	 * @param string $mime What `mime_of()` answered.
	 * @return bool
	 */
	public static function mime_agrees( $mime ) {
		$mime = (string) $mime;

		return '' === $mime || self::MIME === $mime;
	}

	/**
	 * What the courtesy scan finds.
	 *
	 * What it is not is evidence. A token can be hidden in ways a bounded scan will not find -
	 * an object stream inside an object stream, a filter chain this does not implement, a name
	 * split across an indirect reference - and the reviewer's actual protection is
	 * download-never-inline plus their own viewer. Every screen that shows the result says so,
	 * in those words, because a "scanned: clean" badge would move somebody from "I will open
	 * this carefully" to "the site checked it". Both agreement classes run this one scan, so
	 * there is a single set of bounds to keep in step.
	 *
	 * @param string $bytes The file's contents.
	 * @return array{ok: bool, reason: string, flags: string[]}
	 */
	public static function inspect( $bytes ) {
		$bytes    = (string) $bytes;
		$haystack = self::decode_names( $bytes );

		foreach ( self::inflated_streams( $bytes ) as $stream ) {
			$haystack .= "\n" . self::decode_names( $stream );
		}

		foreach ( self::SCAN_REFUSALS as $name => $reason ) {
			if ( self::names_contain( $haystack, $name ) ) {
				return array(
					'ok'     => false,
					'reason' => $reason,
					'flags'  => array(),
				);
			}
		}

		$flags = array();

		foreach ( self::SCAN_FLAGS as $name ) {
			if ( self::names_contain( $haystack, $name ) ) {
				$flags[] = $name;
			}
		}

		return array(
			'ok'     => true,
			'reason' => '',
			'flags'  => $flags,
		);
	}

	/**
	 * Undo PDF's `#xx` name escapes.
	 *
	 * A name in a PDF may be written with any of its characters as `#` and two hex digits, so
	 * `/Launch` is also `/L#61unch` and `/#4C#61#75#6E#63#68`. A scan that searched the raw
	 * bytes would pass every one of those, which is why this runs first and over both the
	 * raw file and each inflated stream.
	 *
	 * @param string $bytes Anything to search.
	 * @return string
	 */
	private static function decode_names( $bytes ) {
		return (string) preg_replace_callback(
			'/#([0-9A-Fa-f]{2})/',
			static function ( $escape ) {
				return chr( hexdec( $escape[1] ) );
			},
			(string) $bytes
		);
	}

	/**
	 * Whether a haystack names a PDF name, and not merely a longer one beginning with it.
	 *
	 * `/JS` must not match inside `/JSomething`, and `/AA` must not match `/AArgh`. A PDF
	 * name ends at a delimiter, so the test is the name followed by anything that is not a
	 * name character.
	 *
	 * @param string $haystack Decoded bytes.
	 * @param string $name     The name, leading slash included.
	 * @return bool
	 */
	private static function names_contain( $haystack, $name ) {
		return 1 === preg_match( '/' . preg_quote( (string) $name, '/' ) . '(?![0-9A-Za-z])/', (string) $haystack );
	}

	/**
	 * Every `FlateDecode` stream in a file, inflated, within a budget.
	 *
	 * The interesting half of the scan. A hostile PDF does not write `/Launch` where a text
	 * search would find it; it writes it inside a compressed object stream. Each `stream`
	 * keyword is found (never `endstream`, which the lookbehind excludes), the dictionary in
	 * front of it is checked for `/FlateDecode`, and what follows is inflated.
	 *
	 * The budget is the other half. A few hundred bytes of zlib can expand into gigabytes, so
	 * a scan meant to protect the reviewer must not become the way to exhaust the site's
	 * memory: at most `SCAN_MAX_STREAMS` streams, at most `SCAN_MAX_STREAM` bytes out of each
	 * and `SCAN_MAX_TOTAL` in all. A stream that would exceed its own bound is skipped rather
	 * than partly read, and this is one of the bounds `inspect()` means when it says the scan
	 * is a courtesy and not evidence.
	 *
	 * @param string $bytes The file's contents.
	 * @return string[] The inflated streams, in file order.
	 */
	private static function inflated_streams( $bytes ) {
		$out   = array();
		$total = 0;

		if ( ! preg_match_all( '/(?<![A-Za-z])stream(\r\n|\r|\n)?/', $bytes, $hits, PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}

		foreach ( $hits[0] as $index => $hit ) {
			if ( $index >= self::SCAN_MAX_STREAMS || $total >= self::SCAN_MAX_TOTAL ) {
				break;
			}

			$at   = (int) $hit[1];
			$back = min( 2048, $at );

			// A window rather than a parser. A filter named further back than this is one
			// this scan does not claim to find.
			if ( ! self::names_contain( self::decode_names( substr( $bytes, $at - $back, $back ) ), '/FlateDecode' ) ) {
				continue;
			}

			$from  = $at + strlen( $hit[0] );
			$end   = strpos( $bytes, 'endstream', $from );
			$body  = false === $end ? substr( $bytes, $from ) : substr( $bytes, $from, $end - $from );
			$plain = self::inflate( $body );

			if ( '' === $plain ) {
				continue;
			}

			$out[]  = $plain;
			$total += strlen( $plain );
		}

		return $out;
	}

	/**
	 * Inflate one stream body, bounded, whichever of the two shapes it is in.
	 *
	 * `gzuncompress()` reads the zlib wrapper nearly every producer writes; `gzinflate()`
	 * reads the raw deflate a few write instead. Both answer false for data they cannot read,
	 * which for a PDF full of images is the normal case rather than an error, so both are
	 * silenced. Both are given the output bound as well, but PHP grows its buffer in rounds
	 * and looks at the total between them, so a stream a little past the bound still comes
	 * back whole: what came back over the budget is dropped at the end rather than searched,
	 * which is what makes the budget a budget.
	 *
	 * @param string $body The compressed bytes.
	 * @return string The inflated bytes, or '' when there are none to be had.
	 */
	private static function inflate( $body ) {
		if ( '' === $body || ! function_exists( 'gzuncompress' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A stream this scan cannot read is a normal part of a normal PDF, not a condition worth a warning in the host's log on every upload.
		$plain = @gzuncompress( $body, self::SCAN_MAX_STREAM );

		if ( ! is_string( $plain ) || '' === $plain ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- As above, for the producers that write a raw deflate stream with no zlib header.
			$plain = @gzinflate( $body, self::SCAN_MAX_STREAM );
		}

		if ( ! is_string( $plain ) || strlen( $plain ) > self::SCAN_MAX_STREAM ) {
			return '';
		}

		return $plain;
	}
}
