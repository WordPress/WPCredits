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
 * Nothing here renders or touches a post. `drift()` is the one thing that reaches outside
 * the plugin at all: on a button press it fetches the Doc's plain-text export and reports
 * what differs. It reports and stops there - its docblock says why that is the whole design
 * and not a first step.
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

	/** Where the last drift check is remembered, one row per language. */
	const OPT_DRIFT = 'wpcpm_agreement_drift';

	/** How long to wait for the Doc's plain-text export. */
	const DRIFT_TIMEOUT = 20;

	/** How much of that export to read. The Doc is editable by anyone holding its link. */
	const DRIFT_MAX_BYTES = 524288;

	/**
	 * How much of a difference report is worth keeping, in bytes.
	 *
	 * The report is stored and redrawn on every load of the manager screen, and the Doc is
	 * editable by anyone holding its link, so what is on the other side of this button is not
	 * necessarily an agreement. A real wording change is a few kilobytes of table markup; the
	 * only thing on the far side of this number is somebody's paste.
	 */
	const DRIFT_MAX_DIFF = 65536;

	/** The hosts a recorded Doc address may name. */
	const DRIFT_HOSTS = array( 'docs.google.com', 'drive.google.com' );

	/**
	 * The Doc's opening instruction, which the plugin's copy leaves out. See `normalise()`.
	 *
	 * Matched as a prefix, so the line is dropped whether or not somebody has added a word to
	 * the end of it.
	 */
	const DRIFT_INSTRUCTION = 'PLEASE MAKE A COPY';

	/**
	 * The markers a list item may begin with, as a regular expression fragment.
	 *
	 * Written as escapes rather than as the glyphs themselves so the pattern stays ASCII and
	 * matches byte-wise, which is what `normalise()` needs: a hyphen or an asterisk, then the
	 * bullet, the triangular and hyphen bullets, the black and white circles, the white
	 * bullet, the black small square and the middle dot. Which of them a word processor's
	 * export reaches for is a choice about lists, not about the agreement, so the whole family
	 * is accepted rather than the one glyph today's export happens to use.
	 */
	const DRIFT_BULLETS = '(?:[-*]|\xE2\x80\xA2|\xE2\x80\xA3|\xE2\x81\x83|\xE2\x97\x8F|\xE2\x97\x8B|\xE2\x97\xA6|\xE2\x96\xAA|\xC2\xB7)';

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
	 * Compare the plugin's copy with the Doc the program keeps the wording in.
	 *
	 * **A button only, never a schedule, and never a reason to refuse generation.** The Doc is
	 * editable by anyone holding its link, so what comes back is not authority: a difference
	 * may be the wording change the program made and told the developer about, or it may be a
	 * stranger's vandalism, and nothing here can tell the two apart. On a schedule that would
	 * be a nightly alarm about somebody else's typing; consulted by the generate path it would
	 * let anyone with the link stop an institution being onboarded. So it runs when a manager
	 * presses the button, and the only thing it produces is a report a manager reads.
	 *
	 * The address is a setting and not a constant for the same reason: a link in a public
	 * repository hands out write access to the wording institutions sign. An unset setting is
	 * not a failure and not something to guess at - the check is unavailable and says so.
	 *
	 * Both sides are normalised before they are compared, `normalise()` lists what it folds and
	 * why each one is safe, and the point of the whole list is that the unchanged Doc matches.
	 * A check that reported the same four differences every time - a word processor's paragraph
	 * spacing, its bullet glyph, the instruction at the top of the Doc, the address the plugin's
	 * copy prints where the Doc has a hyperlink - would be a report a manager learns to skip
	 * past, and a report nobody reads is worse than no report, because it is believed to be
	 * doing the job.
	 *
	 * `bin/fixtures/agreement-doc-en.txt` is the Doc's text as a human transcribed it, and
	 * `bin/test-agreement-template.php` compares the plugin's copy against that rather than
	 * against something `plain_text()` produced: a fixture built from the code under test can
	 * only ever agree with itself.
	 *
	 * @param string $language Language code, as in the file name.
	 * @return array{ok: bool, checked: int, differences: string, error: string, version: string, language: string}
	 *               Read `error` first: when it says anything, the check could not be made and
	 *               `ok` means nothing at all.
	 */
	public static function drift( $language = 'en' ) {
		$language = strtolower( trim( (string) $language ) );
		$template = self::load( $language );

		if ( is_wp_error( $template ) ) {
			return self::unavailable( $template->get_error_message(), $language, '' );
		}

		$version = self::version( $template );
		$doc     = trim( (string) WPCPM_Settings::get_value( 'agreement_doc_url', '' ) );

		if ( '' === $doc ) {
			return self::unavailable(
				__( 'The address of the program\'s agreement Doc is not recorded, so the plugin\'s copy cannot be checked against it. A program manager sets it in the plugin\'s settings.', 'wpcredits-program-manager' ),
				$language,
				$version
			);
		}

		$export = self::export_url( $doc );

		if ( '' === $export ) {
			return self::unavailable(
				__( 'The recorded address does not name a Google Doc, so there is nothing to export and compare.', 'wpcredits-program-manager' ),
				$language,
				$version
			);
		}

		$exported = self::fetch_doc( $export );

		if ( is_wp_error( $exported ) ) {
			return self::unavailable( $exported->get_error_message(), $language, $version );
		}

		$ours   = self::normalise( self::plain_text( $template ) );
		$theirs = self::normalise( $exported );

		$result = array(
			'ok'          => $ours === $theirs,
			'checked'     => time(),
			'differences' => '',
			'error'       => '',
			'version'     => $version,
			'language'    => $language,
		);

		if ( ! $result['ok'] ) {
			$result['differences'] = self::report( $ours, $theirs );
		}

		self::remember( $language, $result );

		return $result;
	}

	/**
	 * The last comparison that actually happened, when it is still about this template.
	 *
	 * Keyed to the version as well as to the language: once the wording owner changes the Doc
	 * and the developer bumps the version, yesterday's "it matches" is about a document the
	 * site no longer generates, and showing it would be worse than showing nothing.
	 *
	 * @param string $language Language code.
	 * @return array|null What `drift()` returned, or null when there is nothing worth showing.
	 */
	public static function cached( $language = 'en' ) {
		$language = strtolower( trim( (string) $language ) );
		$rows     = get_option( self::OPT_DRIFT, array() );

		if ( ! is_array( $rows ) || empty( $rows[ $language ] ) || ! is_array( $rows[ $language ] ) ) {
			return null;
		}

		$template = self::load( $language );

		if ( is_wp_error( $template ) ) {
			return null;
		}

		$row = $rows[ $language ];

		if ( ! isset( $row['version'] ) || self::version( $template ) !== (string) $row['version'] ) {
			return null;
		}

		return $row;
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
	 * What differs between two normalised texts, in something the card can hold and redraw.
	 *
	 * **Bounded, and bounded to a sentence rather than to a cut.** The report is stored in
	 * `OPT_DRIFT` and drawn again on every load of the screen that shows it, and the Doc is
	 * editable by anyone holding its link, so one press against a vandalised copy would
	 * otherwise write half a megabyte of table markup into an option and redraw it for ever
	 * after. Cutting the markup at a byte count would be worse than not keeping it: an unclosed
	 * table is a broken screen. And past the cap a longer report is not what a manager needs
	 * anyway - a document that no longer resembles the agreement is read beside it, not line by
	 * line.
	 *
	 * The bounded string is what `drift()` returns as well as what it remembers, so the card
	 * cannot show something the person who pressed the button did not see.
	 *
	 * @param string $ours   The plugin's copy, normalised.
	 * @param string $theirs The Doc's export, normalised.
	 * @return string HTML.
	 */
	private static function report( $ours, $theirs ) {
		$differences = (string) wp_text_diff(
			$ours,
			$theirs,
			array(
				'title_left'  => __( 'The plugin\'s copy', 'wpcredits-program-manager' ),
				'title_right' => __( 'The Doc', 'wpcredits-program-manager' ),
			)
		);

		// The verdict is the comparison, not the renderer. If the two ever disagree the texts
		// still differ, and saying so is better than handing back an empty report that a
		// manager would read as "nothing has changed".
		if ( '' === trim( $differences ) ) {
			return '<p>' . esc_html__( 'The two texts differ, but not in a way a line-by-line comparison can show. They need reading side by side.', 'wpcredits-program-manager' ) . '</p>';
		}

		if ( strlen( $differences ) > self::DRIFT_MAX_DIFF ) {
			return '<p>' . esc_html__( 'The Doc and the plugin\'s copy differ in more places than are worth listing here. Anyone holding the Doc\'s link can edit it, so a difference this size is usually somebody pasting into the Doc rather than the program rewriting the agreement. Open the Doc and read it.', 'wpcredits-program-manager' ) . '</p>';
		}

		return $differences;
	}

	/**
	 * The result of a check that could not be made, with any stale answer thrown away.
	 *
	 * Nothing is cached here, on purpose. A stored row saying `ok` false would be read off the
	 * card as "the Doc differs" when what actually happened is that nobody could read the Doc;
	 * and leaving the previous answer in place would show a verdict about a document this run
	 * never looked at. The cache holds comparisons that happened, and nothing else.
	 *
	 * @param string $message  Why the check could not be made, in words a manager reads.
	 * @param string $language The language the check was for.
	 * @param string $version  The plugin copy's version, or '' when even that could not be read.
	 * @return array The shape `drift()` returns.
	 */
	private static function unavailable( $message, $language, $version ) {
		self::forget_drift( $language );

		return array(
			'ok'          => false,
			'checked'     => time(),
			'differences' => '',
			'error'       => (string) $message,
			'version'     => (string) $version,
			'language'    => (string) $language,
		);
	}

	/**
	 * Remember one comparison.
	 *
	 * One row per language rather than one row full stop, because `languages()` reads the
	 * directory and a sibling file is the whole job of adding a language; a single row would
	 * make the second language quietly overwrite the first one's answer.
	 *
	 * @param string $language Language code.
	 * @param array  $result   What `drift()` is about to return.
	 */
	private static function remember( $language, array $result ) {
		$rows = get_option( self::OPT_DRIFT, array() );

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$rows[ $language ] = $result;

		// Not autoloaded: a diff nobody is reading has no business on every request.
		update_option( self::OPT_DRIFT, $rows, false );
	}

	/**
	 * Drop one language's remembered answer, and the option with it when nothing is left.
	 *
	 * @param string $language Language code.
	 */
	private static function forget_drift( $language ) {
		$rows = get_option( self::OPT_DRIFT, array() );

		if ( ! is_array( $rows ) || ! array_key_exists( $language, $rows ) ) {
			return;
		}

		unset( $rows[ $language ] );

		if ( empty( $rows ) ) {
			delete_option( self::OPT_DRIFT );

			return;
		}

		update_option( self::OPT_DRIFT, $rows, false );
	}

	/**
	 * The plain-text export address for a recorded Doc link.
	 *
	 * What a manager pastes is whatever Google's Share dialog gave them, so the document ID is
	 * dug out of it and the address is built here rather than assumed. Only the ID travels:
	 * nothing else from the pasted path or query reaches the request.
	 *
	 * The host is checked here rather than trusted from the settings screen, because this
	 * value becomes an outbound HTTP request and an option can be written by something other
	 * than the form that sanitises it.
	 *
	 * @param string $doc The recorded address.
	 * @return string The export address, or '' when the link names no Google Doc.
	 */
	private static function export_url( $doc ) {
		$parts = wp_parse_url( $doc );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return '';
		}

		if ( empty( $parts['host'] ) || ! in_array( strtolower( (string) $parts['host'] ), self::DRIFT_HOSTS, true ) ) {
			return '';
		}

		$id = '';

		if ( preg_match( '#/d/([A-Za-z0-9_-]{10,})#', $doc, $found ) ) {
			$id = $found[1];
		} elseif ( preg_match( '#[?&]id=([A-Za-z0-9_-]{10,})#', $doc, $found ) ) {
			$id = $found[1];
		}

		if ( '' === $id ) {
			return '';
		}

		return 'https://docs.google.com/document/d/' . $id . '/export?format=txt';
	}

	/**
	 * Ask Google for the Doc as plain text.
	 *
	 * A Doc that is not shared with everyone holding its link answers 200 with a sign-in page,
	 * and comparing that against the agreement would report the wording as having been
	 * rewritten from end to end. So the body has to look like text before it is compared: an
	 * answer that opens with a tag is a page about the document, not the document.
	 *
	 * A body that fills `DRIFT_MAX_BYTES` is refused for the same reason. The request stops
	 * reading at that many bytes, and what comes back then is the beginning of a document
	 * rather than a document: comparing it would report everything past the cut as deleted
	 * from the Doc. A truncated read is a failed read, and every failed read here has to say
	 * so rather than turn into a verdict.
	 *
	 * @param string $url The export address.
	 * @return string|WP_Error The exported text, or why it could not be read.
	 */
	private static function fetch_doc( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => self::DRIFT_TIMEOUT,
				'redirection'         => 5,
				// A world-editable document can be pasted full of anything, and this one is
				// read into memory to be compared line by line.
				'limit_response_size' => self::DRIFT_MAX_BYTES,
				'headers'             => array( 'Accept' => 'text/plain' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wpcpm_template_drift_http',
				sprintf(
					/* translators: %s: the reason the request failed, from the HTTP layer. */
					__( 'The Doc could not be read: %s', 'wpcredits-program-manager' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'wpcpm_template_drift_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The Doc could not be read (HTTP %d).', 'wpcredits-program-manager' ),
					$code
				)
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );

		// Measured before anything is stripped, because what is being asked is how many bytes
		// the request read and not how many are left after tidying. A document that ends exactly
		// on the cap is treated as cut too: from here the two are indistinguishable, and of the
		// two possible mistakes, refusing a whole document is the one that costs nothing.
		if ( strlen( $body ) >= self::DRIFT_MAX_BYTES ) {
			return new WP_Error(
				'wpcpm_template_drift_long',
				sprintf(
					/* translators: %d: how many kilobytes of the export this check reads. */
					__( 'The Doc is longer than the %d KB this check reads, so only its beginning arrived and the rest of it would have been reported as deleted. Nothing was compared. An agreement is not this long: a Doc that is has had something pasted into it.', 'wpcredits-program-manager' ),
					(int) round( self::DRIFT_MAX_BYTES / 1024 )
				)
			);
		}

		$body = ltrim( self::strip_bom( $body ) );

		if ( '' === $body ) {
			return new WP_Error( 'wpcpm_template_drift_empty', __( 'The Doc exported nothing, so there is nothing to compare.', 'wpcredits-program-manager' ) );
		}

		if ( '<' === substr( $body, 0, 1 ) ) {
			return new WP_Error( 'wpcpm_template_drift_html', __( 'Google answered with a web page instead of the document\'s text, which is what it does when the Doc is not shared with everyone holding its link.', 'wpcredits-program-manager' ) );
		}

		return $body;
	}

	/**
	 * The comparable form of a text: the words, in order, and nothing else.
	 *
	 * Both sides go through this, and every rule below is symmetric, so nothing here can make
	 * one side say something the other does not. What each rule takes out is a difference
	 * between a word processor's rendering of the agreement and this plugin's, and the test of
	 * whether the list is right is that the real, unchanged Doc matches.
	 *
	 * The rules, and why each one is safe to apply to a document somebody signs:
	 *
	 * 1. A byte order mark, CRLF line endings, tabs, runs of spaces, trailing spaces and blank
	 *    lines. Typing, not wording. A word processor puts a blank line under every paragraph
	 *    on export; `plain_text()` puts one between blocks.
	 * 2. A no-break space, a narrow no-break space and a zero-width space, each folded to a
	 *    space. All three are a word processor's idea of a space and none of them is a word.
	 * 3. `[Institution Name]`. Whether the placeholder is there, and how often, is `load()`'s
	 *    rule and the fixture's; it is refused in the one place where it would matter, and it
	 *    is not a wording question.
	 * 4. A list marker at the start of a line, whichever glyph it is and whether or not a space
	 *    follows it, folded to the `- ` that `plain_text()` writes. The marker is how a list is
	 *    drawn; the item after it is the text.
	 * 5. A parenthesised web address, and the space before it. In the Doc "Code of Conduct" is a
	 *    hyperlink and a plain-text export carries the words without the address; the plugin's
	 *    copy prints the address after the words because a signed paper copy cannot be clicked.
	 *    Dropping it from both sides compares the wording rather than the rendering of a link.
	 *    Nothing is lost by it: what pins that address is the fixture's load-bearing sentence
	 *    and the checksum, and the Doc's export cannot speak to it either way.
	 * 6. A run of underscores, and the space before it. That is the ruled line a signatory
	 *    writes on, and how long it is drawn is not something anybody wrote.
	 * 7. The `PLEASE MAKE A COPY` instruction the Doc opens with, which the plugin's copy leaves
	 *    out on purpose. It is an instruction to whoever opens the Doc rather than a clause
	 *    anybody signs, so its absence is not news, and news reported on every press is what
	 *    teaches a manager to stop reading the report.
	 *
	 * Byte-wise on purpose. The unicode modifier makes `preg_replace()` return null on invalid
	 * UTF-8, which would silently empty a line of a document somebody pasted badly into, so the
	 * bullet glyphs are matched as the bytes they are (`DRIFT_BULLETS`).
	 *
	 * The rules are numbered in the order they are applied below.
	 *
	 * @param string $text Either side.
	 * @return string One line per line of text, no blank lines, single spaces.
	 */
	private static function normalise( $text ) {
		$text = self::strip_bom( (string) $text );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = str_replace( self::PLACEHOLDER, '', $text );

		// A no-break space, a narrow no-break space and a zero-width space are all a word
		// processor's idea of a space, and none of them is a change to the agreement.
		$text = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x8B" ), ' ', $text );

		$lines = array();

		foreach ( explode( "\n", $text ) as $line ) {
			// Whitespace first, so every rule after it sees one space where a word processor
			// may have written a tab, a run of spaces or a no-break space.
			$line = trim( preg_replace( '/[ \t\x0B\f]+/', ' ', $line ) );
			$line = preg_replace( '/^' . self::DRIFT_BULLETS . ' ?/', '- ', $line );
			$line = trim( preg_replace( '#\s*\(https?://[^\s()]*\)#', '', $line ) );
			$line = trim( preg_replace( '/ ?_{2,}/', '', $line ) );

			if ( '' === $line || 0 === stripos( $line, self::DRIFT_INSTRUCTION ) ) {
				continue;
			}

			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Drop a UTF-8 byte order mark, which a Doc export begins with and no reader wants.
	 *
	 * @param string $text Any text.
	 * @return string
	 */
	private static function strip_bom( $text ) {
		return 0 === strpos( $text, "\xEF\xBB\xBF" ) ? substr( $text, 3 ) : $text;
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
