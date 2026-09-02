<?php
/**
 * The Collaboration Agreement template: loading, merging and pinning.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the agreement block list, fills in the institution's name and produces the plain
 * text a checksum can pin.
 *
 * The agreement lives in `includes/templates/collaboration-agreement-<language>.php` as a
 * list of typed blocks copied from the program's Google Doc. This class is the only thing
 * that opens those files, so the rules about them are enforced in one place: the file must
 * have the expected shape, the placeholder must appear exactly `OCCURRENCES` times, and
 * nothing bracketed may survive a merge.
 *
 * **Why the bracket rule is strict.** The document a merge produces is what a rector signs.
 * If a wording change ever adds `[Signatory Title]` beside `[Institution Name]`, the merge
 * would happily print the new token verbatim and nobody would notice until a signed copy
 * came back with it. `load()` counts the placeholder before anything else happens, and
 * `merge()` refuses any `[` or `]` in the merged text, so the failure is a refusal on the
 * generate form rather than a bracket on paper.
 *
 * **Why plain text.** `plain_text()` is the one deterministic rendering of a template, so
 * the fixture's sha256 pins the wording independently of how a later phase draws it. An
 * HTML checksum would change with every stylesheet edit; this one changes only when a word
 * does, which is exactly when the version must be bumped.
 *
 * Nothing here renders, fetches the Doc or touches a post. Rendering and the drift check
 * against the Doc are later phases; this is the part every one of them depends on.
 */
class WPCPM_Agreement_Template {

	/** The token the institution's name replaces. */
	const PLACEHOLDER = '[Institution Name]';

	/** How many times the placeholder appears in a well-formed template. */
	const OCCURRENCES = 2;

	/** Block types a template file may contain. */
	const TYPES = array( 'h1', 'h2', 'h3', 'p', 'label', 'ul', 'signatures' );

	/** Block types whose content is a single `text` string. */
	const TEXT_TYPES = array( 'h1', 'h2', 'h3', 'p', 'label' );

	/** The blank a signatory fills in on paper. */
	const BLANK = '____';

	/**
	 * Load the template for one language.
	 *
	 * @param string $language Language code, as in the file name.
	 * @return array|WP_Error The template, or why it cannot be used.
	 */
	public static function load( $language = 'en' ) {
		$language = strtolower( trim( (string) $language ) );

		// The code becomes part of a path, so only the shape the file names use is allowed.
		if ( ! preg_match( '/^[a-z]{2}(?:-[a-z]{2})?$/', $language ) ) {
			return new WP_Error( 'wpcpm_template_language', __( 'There is no agreement template in that language.', 'wpcredits-program-manager' ) );
		}

		$path = self::path( $language );

		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'wpcpm_template_language', __( 'There is no agreement template in that language.', 'wpcredits-program-manager' ) );
		}

		$template = include $path;

		$shape = self::check_shape( $template, $language );

		if ( is_wp_error( $shape ) ) {
			return $shape;
		}

		// Every bracketed token must be the placeholder, and there must be exactly the
		// expected number of them. A third copy, or a token with another name, is a template
		// edit that was not thought through, and it is caught here rather than on paper.
		preg_match_all( '/\[[^\]]*\]/', self::plain_text( $template ), $found );

		$tokens = array_unique( $found[0] );

		if ( self::OCCURRENCES !== count( $found[0] ) || array( self::PLACEHOLDER ) !== array_values( $tokens ) ) {
			return new WP_Error(
				'wpcpm_template_placeholder',
				sprintf(
					/* translators: 1: the placeholder token, 2: how many times it must appear, 3: the language code */
					__( 'The agreement template must contain %1$s exactly %2$d times and no other bracketed token; the %3$s template does not.', 'wpcredits-program-manager' ),
					self::PLACEHOLDER,
					self::OCCURRENCES,
					$language
				)
			);
		}

		return $template;
	}

	/**
	 * The language codes a template file exists for.
	 *
	 * Read from the directory rather than from a list, so adding a sibling file is the whole
	 * job of adding a language and nothing has to be kept in step with it.
	 *
	 * @return string[] Sorted language codes.
	 */
	public static function languages() {
		$codes = array();
		$files = glob( self::directory() . 'collaboration-agreement-*.php' );

		foreach ( (array) $files as $file ) {
			if ( preg_match( '/^collaboration-agreement-([a-z]{2}(?:-[a-z]{2})?)\.php$/', basename( (string) $file ), $m ) ) {
				$codes[] = $m[1];
			}
		}

		$codes = array_values( array_unique( $codes ) );
		sort( $codes );

		return $codes;
	}

	/**
	 * Put the institution's name into every block.
	 *
	 * @param array  $template A template from `load()`.
	 * @param string $name     The name as it should print.
	 * @return array|WP_Error The merged template, or why it cannot be produced.
	 */
	public static function merge( $template, $name ) {
		// What `load()` returned, error included, so a caller can chain the two and check once.
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		if ( ! is_array( $template ) ) {
			return new WP_Error( 'wpcpm_template_shape', __( 'The agreement template could not be read.', 'wpcredits-program-manager' ) );
		}

		$name = trim( (string) $name );

		if ( '' === $name ) {
			return new WP_Error( 'wpcpm_template_name', __( 'The institution name to print on the agreement is missing.', 'wpcredits-program-manager' ) );
		}

		// Checked before the merge so the message can say where the bracket came from. After
		// the merge a bracket from the name and one from the template look the same.
		if ( preg_match( '/[\[\]]/', $name ) ) {
			return new WP_Error( 'wpcpm_template_placeholder', __( 'The institution name cannot contain square brackets.', 'wpcredits-program-manager' ) );
		}

		$merged = self::map_text(
			$template,
			function ( $text ) use ( $name ) {
				return str_replace( self::PLACEHOLDER, $name, $text );
			}
		);

		// Any bracket left is a token the merge did not know about. Refusing is the last
		// line: the document must never print one, whatever the template file says.
		if ( preg_match( '/[\[\]]/', self::plain_text( $merged ) ) ) {
			return new WP_Error( 'wpcpm_template_placeholder', __( 'The agreement template needs attention: it contains a placeholder the generator does not fill in.', 'wpcredits-program-manager' ) );
		}

		return $merged;
	}

	/**
	 * The template as deterministic plain text.
	 *
	 * Headings, paragraphs and labels on their own line, bullets as `- item`, each signature
	 * party as its line followed by the three blanks, blocks separated by one blank line, no
	 * trailing whitespace, `\n` line endings. The fixture's sha256 is of this string.
	 *
	 * @param array $template A template, merged or not.
	 * @return string
	 */
	public static function plain_text( array $template ) {
		$chunks = array();
		$blocks = isset( $template['blocks'] ) && is_array( $template['blocks'] ) ? $template['blocks'] : array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) ) {
				continue;
			}

			$lines = array();

			if ( in_array( $block['type'], self::TEXT_TYPES, true ) ) {
				$lines[] = isset( $block['text'] ) ? (string) $block['text'] : '';
			} elseif ( 'ul' === $block['type'] ) {
				foreach ( (array) ( isset( $block['items'] ) ? $block['items'] : array() ) as $item ) {
					$lines[] = '- ' . (string) $item;
				}
			} elseif ( 'signatures' === $block['type'] ) {
				$parties = array();

				foreach ( (array) ( isset( $block['parties'] ) ? $block['parties'] : array() ) as $party ) {
					$party_lines = array( isset( $party['party'] ) ? (string) $party['party'] : '' );

					foreach ( (array) ( isset( $party['lines'] ) ? $party['lines'] : array() ) as $line ) {
						$party_lines[] = (string) $line . ': ' . self::BLANK;
					}

					$parties[] = implode( "\n", $party_lines );
				}

				// Each party is its own signature box on paper, so they are set apart the
				// way blocks are.
				$lines[] = implode( "\n\n", $parties );
			}

			$chunks[] = implode( "\n", $lines );
		}

		$text  = implode( "\n\n", $chunks );
		$text  = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$lines = array_map( 'rtrim', explode( "\n", $text ) );

		return rtrim( implode( "\n", $lines ) );
	}

	/**
	 * The template's version, the Doc's modified date when the copy was taken.
	 *
	 * @param array $template A template.
	 * @return string
	 */
	public static function version( array $template ) {
		return isset( $template['version'] ) ? (string) $template['version'] : '';
	}

	/**
	 * The sha256 of the plain text, the value the fixture pins.
	 *
	 * @param array $template A template.
	 * @return string Lowercase hex.
	 */
	public static function checksum( array $template ) {
		return hash( 'sha256', self::plain_text( $template ) );
	}

	/**
	 * Where the template files live.
	 *
	 * @return string Directory path with a trailing slash.
	 */
	private static function directory() {
		return __DIR__ . '/templates/';
	}

	/**
	 * The file a language code maps to.
	 *
	 * @param string $language A code that has already passed the shape check.
	 * @return string
	 */
	private static function path( $language ) {
		return self::directory() . 'collaboration-agreement-' . $language . '.php';
	}

	/**
	 * Whether what a template file returned is a template.
	 *
	 * Strict on purpose: a file that returns something else is a developer's mistake, and a
	 * loose reader would turn it into a half-empty document instead of a refusal.
	 *
	 * @param mixed  $template Whatever the file returned.
	 * @param string $language The code the file was opened for.
	 * @return true|WP_Error
	 */
	private static function check_shape( $template, $language ) {
		$bad = new WP_Error(
			'wpcpm_template_shape',
			sprintf(
				/* translators: %s: the language code */
				__( 'The %s agreement template file does not have the expected shape.', 'wpcredits-program-manager' ),
				$language
			)
		);

		if ( ! is_array( $template ) ) {
			return $bad;
		}

		foreach ( array( 'language', 'version', 'read', 'source' ) as $key ) {
			if ( empty( $template[ $key ] ) || ! is_string( $template[ $key ] ) ) {
				return $bad;
			}
		}

		// The file's own language must match its name; a copy that was renamed but not
		// edited would otherwise be served under the wrong code.
		if ( $language !== $template['language'] ) {
			return $bad;
		}

		// The version is the Doc's modified date, so it has a date's shape.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $template['version'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $template['read'] ) ) {
			return $bad;
		}

		if ( empty( $template['blocks'] ) || ! is_array( $template['blocks'] ) ) {
			return $bad;
		}

		foreach ( $template['blocks'] as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) || ! in_array( $block['type'], self::TYPES, true ) ) {
				return $bad;
			}

			if ( in_array( $block['type'], self::TEXT_TYPES, true ) ) {
				if ( empty( $block['text'] ) || ! is_string( $block['text'] ) ) {
					return $bad;
				}
			} elseif ( 'ul' === $block['type'] ) {
				if ( empty( $block['items'] ) || ! is_array( $block['items'] ) ) {
					return $bad;
				}

				foreach ( $block['items'] as $item ) {
					if ( ! is_string( $item ) || '' === $item ) {
						return $bad;
					}
				}
			} elseif ( empty( $block['parties'] ) || ! is_array( $block['parties'] ) ) {
				return $bad;
			} else {
				foreach ( $block['parties'] as $party ) {
					if ( ! is_array( $party ) || empty( $party['party'] ) || ! is_string( $party['party'] )
						|| empty( $party['lines'] ) || ! is_array( $party['lines'] ) ) {
						return $bad;
					}

					foreach ( $party['lines'] as $line ) {
						if ( ! is_string( $line ) || '' === $line ) {
							return $bad;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * Apply a callback to every string a document is made of.
	 *
	 * The metadata (`language`, `version`, `read`, `source`) is left alone: it names the
	 * template, it is not part of the document.
	 *
	 * @param array    $template A template.
	 * @param callable $callback Takes a string, returns a string.
	 * @return array The template with every text replaced.
	 */
	private static function map_text( array $template, $callback ) {
		$blocks = isset( $template['blocks'] ) && is_array( $template['blocks'] ) ? $template['blocks'] : array();

		foreach ( $blocks as $i => $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) ) {
				continue;
			}

			if ( isset( $block['text'] ) ) {
				$blocks[ $i ]['text'] = call_user_func( $callback, (string) $block['text'] );
			}

			if ( isset( $block['items'] ) && is_array( $block['items'] ) ) {
				foreach ( $block['items'] as $j => $item ) {
					$blocks[ $i ]['items'][ $j ] = call_user_func( $callback, (string) $item );
				}
			}

			if ( isset( $block['parties'] ) && is_array( $block['parties'] ) ) {
				foreach ( $block['parties'] as $j => $party ) {
					if ( isset( $party['party'] ) ) {
						$blocks[ $i ]['parties'][ $j ]['party'] = call_user_func( $callback, (string) $party['party'] );
					}

					if ( isset( $party['lines'] ) && is_array( $party['lines'] ) ) {
						foreach ( $party['lines'] as $k => $line ) {
							$blocks[ $i ]['parties'][ $j ]['lines'][ $k ] = call_user_func( $callback, (string) $line );
						}
					}
				}
			}
		}

		$template['blocks'] = $blocks;

		return $template;
	}
}
