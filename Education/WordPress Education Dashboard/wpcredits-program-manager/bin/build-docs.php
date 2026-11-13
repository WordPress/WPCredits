<?php
/**
 * Assemble the five program guides from one set of sections.
 *
 * Five audiences, and each guide has to be complete on its own: the access levels do not
 * nest, so a mentor holding only `wpcpm_view_mentor_content` cannot open a Student-level
 * page. A mentor guide that linked to the student guide would be linking them somewhere
 * they get an access notice.
 *
 * So the shared parts are written once, in docs/sections, and each guide is composed from
 * them here. Change the student booking section and it changes in every guide that carries
 * it; the sponsor and institution guides carry only the signing-in section they share.
 *
 * Two outputs per guide:
 *   docs/<audience>.md          - for reading in the repository.
 *   docs/build/<audience>.html  - block markup, for publishing as a page.
 *
 * The image token resolves differently for each: a relative path in the Markdown, the
 * uploaded URL in the block markup. Run with --base to set that URL.
 *
 * Usage:
 *   php bin/build-docs.php
 *   php bin/build-docs.php --base=https://example.com/wp-content/uploads/2026/08
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root     = dirname( __DIR__ );
$sections = $root . '/docs/sections';
$out_md   = $root . '/docs';
$out_html = $root . '/docs/build';

$base = 'images';
foreach ( $argv as $arg ) {
	if ( 0 === strpos( $arg, '--base=' ) ) {
		$base = rtrim( substr( $arg, 7 ), '/' );
	}
}

/**
 * Attachment IDs for the published pages, keyed by image slug.
 *
 * An `<!-- wp:image -->` block without an `id` still renders, but the media library never
 * learns the picture is in use - so deleting it later looks safe when it is not.
 */
$ids = array(
	'student-report-card-profile'            => 550,
	'student-report-card-booking-a-call'     => 549,
	'mentor-report-card-triaged-list'        => 548,
	'mentor-report-card-student-and-notes'   => 547,
	'mentor-report-card-availability'        => 546,
	'admin-overview'                         => 556,
	'admin-settings'                         => 557,
	'track-builder-list'                     => 1187,
	'track-builder-track'                    => 1188,
	'track-builder-question'                 => 1189,
	'track-builder-preview'                  => 1190,
	'track-builder-publish'                  => 1191,
	'track-builder-history'                  => 1192,
	'admin-header-notices'                   => 555,
	'admin-access-level'                     => 554,
	'sponsor-dashboard-top'                  => 1781,
	'sponsor-dashboard-cards'                => 1782,
	'sponsor-sponsorship-card'               => 1783,
	'sponsor-offers-card'                    => 1784,
	'sponsor-usage-card'                     => 1785,
	'sponsor-company-card'                   => 1786,
	'sponsor-events-card'                    => 1787,
);

$guides = require __DIR__ . '/docs-guides.php';

/**
 * A stable, unique anchor for a heading.
 *
 * Reset per guide by `wpcpm_docs_anchor_reset()`, so the same heading in two guides gets the same
 * anchor and a repeated heading *within* one guide gets `-2`.
 *
 * @param string $text Heading text, as written in Markdown.
 * @return string
 */
function wpcpm_docs_anchor( $text ) {
	static $seen = array();

	if ( null === $text ) {
		$seen = array();

		return '';
	}

	// Inline markup goes before slugging: `**Notes**` and `Notes` are the same heading.
	$slug = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', wpcpm_docs_strip_inline( $text ) ), '-' ) );
	$slug = '' !== $slug ? $slug : 'section';

	if ( isset( $seen[ $slug ] ) ) {
		++$seen[ $slug ];

		return $slug . '-' . $seen[ $slug ];
	}

	$seen[ $slug ] = 1;

	return $slug;
}

/**
 * Start a new guide's anchor numbering.
 */
function wpcpm_docs_anchor_reset() {
	wpcpm_docs_anchor( null );
}

/**
 * Strip the Markdown inline markup a heading can carry.
 *
 * @param string $text Heading text.
 * @return string
 */
function wpcpm_docs_strip_inline( $text ) {
	$text = preg_replace( '/\[([^\]]+)\]\([^)]*\)/', '$1', (string) $text );

	return str_replace( array( '**', '*', '`' ), '', $text );
}

/**
 * Read one section.
 *
 * @param string $dir  Sections directory.
 * @param string $name Section slug.
 * @return string
 */
function wpcpm_docs_section( $dir, $name ) {
	$path = $dir . '/' . $name . '.md';

	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Missing section: {$name}\n" );
		exit( 1 );
	}

	return trim( (string) file_get_contents( $path ) );
}

/**
 * A section with every heading one level further down, `##` to `###` and so on, for a guide that
 * nests another guide's sections under a heading of its own. A line inside a fenced code block is
 * left alone, and so is a heading already at the sixth level.
 *
 * @param string $md The section's Markdown.
 * @return string
 */
function wpcpm_docs_demote( $md ) {
	$fenced = false;
	$lines  = explode( "\n", (string) $md );

	foreach ( $lines as $i => $line ) {
		if ( 0 === strpos( ltrim( $line ), '```' ) ) {
			$fenced = ! $fenced;
			continue;
		}

		if ( ! $fenced && 1 === preg_match( '/^#{1,5} /', $line ) ) {
			$lines[ $i ] = '#' . $line;
		}
	}

	return implode( "\n", $lines );
}

/**
 * Turn `{{image:slug|caption}}` into Markdown.
 *
 * @param string $text Section text.
 * @param string $base Directory or URL images live under.
 * @return string
 */
function wpcpm_docs_images_md( $text, $base ) {
	return preg_replace_callback(
		'/\{\{image:([a-z0-9-]+)\|([^}]*)\}\}/',
		static function ( $m ) use ( $base ) {
			return sprintf( "![%2\$s](%1\$s/%3\$s.png)\n\n*%2\$s*", $base, $m[2], $m[1] );
		},
		$text
	);
}

/**
 * Turn `{{image:slug|caption}}` into an image block.
 *
 * @param string $text Section text.
 * @param string $base URL images live under.
 * @param array  $ids  Attachment IDs, keyed by slug.
 * @return string
 */
function wpcpm_docs_images_blocks( $text, $base, $ids ) {
	return preg_replace_callback(
		'/\{\{image:([a-z0-9-]+)\|([^}]*)\}\}/',
		static function ( $m ) use ( $base, $ids ) {
			$slug    = $m[1];
			$caption = htmlspecialchars( $m[2], ENT_QUOTES );
			$id      = isset( $ids[ $slug ] ) ? (int) $ids[ $slug ] : 0;
			$attrs   = $id ? sprintf( '{"id":%d,"sizeSlug":"large","linkDestination":"none"}', $id ) : '{"sizeSlug":"large","linkDestination":"none"}';
			$class   = $id ? sprintf( ' class="wp-image-%d"', $id ) : '';

			return sprintf(
				"<!-- wp:image %s -->\n<figure class=\"wp-block-image size-large\"><img src=\"%s/%s.png\" alt=\"%s\"%s/><figcaption class=\"wp-element-caption\">%s</figcaption></figure>\n<!-- /wp:image -->",
				$attrs,
				$base,
				$slug,
				$caption,
				$class,
				$caption
			);
		},
		$text
	);
}

/**
 * The clips table, docs/clips.json: slug => attachment id, urls, size and caption. A missing file is an
 * empty table; a file that is not a JSON object stops the build.
 *
 * @param string $path Path to clips.json.
 * @return array
 */
function wpcpm_docs_clips( $path ) {
	if ( ! is_file( $path ) ) {
		return array();
	}

	$clips = json_decode( (string) file_get_contents( $path ), true );

	if ( ! is_array( $clips ) ) {
		fwrite( STDERR, "docs/clips.json is not a JSON object.\n" );
		exit( 1 );
	}

	return $clips;
}

/**
 * Stop the build when a clip token names a slug the table lacks, or an entry lacks a field.
 *
 * @param string $text  Section text.
 * @param array  $clips The clips table.
 */
function wpcpm_docs_clips_check( $text, $clips ) {
	// A line that starts like a clip and is not exactly one would print as text in the guide.
	foreach ( explode( "\n", (string) $text ) as $line ) {
		if ( 0 === strpos( $line, '{{clip:' ) && ! preg_match( '/^\{\{clip:[a-z0-9-]+\}\}$/', rtrim( $line ) ) ) {
			fwrite( STDERR, 'Not a clip token: ' . $line . "\n" );
			exit( 1 );
		}
	}

	if ( ! preg_match_all( '/^\{\{clip:([a-z0-9-]+)\}\}$/m', $text, $found ) ) {
		return;
	}

	foreach ( array_unique( $found[1] ) as $slug ) {
		if ( ! isset( $clips[ $slug ] ) || ! is_array( $clips[ $slug ] ) ) {
			fwrite( STDERR, "Clip \"{$slug}\" is not in docs/clips.json.\n" );
			exit( 1 );
		}

		foreach ( array( 'id', 'src', 'poster', 'captions', 'width', 'height', 'caption' ) as $field ) {
			$value = $clips[ $slug ][ $field ] ?? null;

			if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
				fwrite( STDERR, "Clip \"{$slug}\" in docs/clips.json has no \"{$field}\" as a string or a number.\n" );
				exit( 1 );
			}

			if ( '' === trim( (string) $value ) ) {
				fwrite( STDERR, "Clip \"{$slug}\" in docs/clips.json has an empty \"{$field}\".\n" );
				exit( 1 );
			}
		}
	}
}

/**
 * Turn a line that is exactly `{{clip:slug}}` into the caption in italics.
 *
 * @param string $text  Section text.
 * @param array  $clips The clips table.
 * @return string
 */
function wpcpm_docs_clips_md( $text, $clips ) {
	return preg_replace_callback(
		'/^\{\{clip:([a-z0-9-]+)\}\}$/m',
		static function ( $m ) use ( $clips ) {
			return '*Clip: ' . $clips[ $m[1] ]['caption'] . '*';
		},
		$text
	);
}

/**
 * Turn a line that is exactly `{{clip:slug}}` into a core video block: controls, nothing preloaded,
 * captions as a track, never autoplay.
 *
 * @param string $text  Section text.
 * @param array  $clips The clips table.
 * @return string
 */
function wpcpm_docs_clips_blocks( $text, $clips ) {
	$esc = static function ( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	};

	return preg_replace_callback(
		'/^\{\{clip:([a-z0-9-]+)\}\}$/m',
		static function ( $m ) use ( $clips, $esc ) {
			$c     = $clips[ $m[1] ];
			$attrs = wpcpm_docs_block_json( array(
				'id'     => (int) $c['id'],
				'tracks' => array( array( 'src' => (string) $c['captions'], 'kind' => 'captions', 'srcLang' => 'en', 'label' => 'English' ) ),
			) );

			return sprintf(
				"<!-- wp:video %s -->\n<figure class=\"wp-block-video wpcpm-guide-clip\"><video controls preload=\"none\" poster=\"%s\" src=\"%s\" width=\"%d\" height=\"%d\"><track src=\"%s\" kind=\"captions\" srclang=\"en\" label=\"English\"/></video><figcaption class=\"wp-element-caption\">%s</figcaption></figure>\n<!-- /wp:video -->",
				$attrs,
				$esc( $c['poster'] ),
				$esc( $c['src'] ),
				(int) $c['width'],
				(int) $c['height'],
				$esc( $c['captions'] ),
				$esc( $c['caption'] )
			);
		},
		$text
	);
}

/**
 * JSON for a block comment, escaped the way WordPress's serialize_block_attributes() does it: `--`, `<`,
 * `>`, `&` and `\"` as unicode escapes, so no value can end the comment or open a tag in it.
 *
 * @param array $data Attributes.
 * @return string
 */
function wpcpm_docs_block_json( $data ) {
	$json = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	return str_replace(
		array( '--', '<', '>', '&', '\\"' ),
		array( '\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022' ),
		$json
	);
}

/**
 * Convert the Markdown subset the sections use into block markup.
 *
 * Deliberately small: headings, paragraphs, lists, tables and the image blocks already
 * substituted above. A general Markdown parser would be a dependency, and these are
 * documents this repository writes for itself.
 *
 * @param string $md        Markdown.
 * @param bool   $reference Whether this is a reference part: its ## headings carry the class wpcpm-guide-ref.
 * @return string
 */
function wpcpm_docs_to_blocks( $md, $reference = false ) {
	$out    = array();
	$lines  = explode( "\n", $md );
	$count  = count( $lines );
	$buffer = array();

	// Paragraph text is wrapped in the source for readability; a blank line ends it.
	$flush = static function () use ( &$buffer, &$out ) {
		if ( empty( $buffer ) ) {
			return;
		}
		$text = trim( implode( ' ', $buffer ) );
		$buffer = array();
		if ( '' === $text ) {
			return;
		}
		$out[] = "<!-- wp:paragraph -->\n<p>" . wpcpm_docs_inline( $text ) . "</p>\n<!-- /wp:paragraph -->";
	};

	for ( $i = 0; $i < $count; $i++ ) {
		$line = rtrim( $lines[ $i ] );

		// Blocks already built by the image substitution pass through untouched.
		if ( 0 === strpos( $line, '<!-- wp:' ) ) {
			$flush();
			$block = array( $line );
			while ( ++$i < $count ) {
				$block[] = rtrim( $lines[ $i ] );
				if ( 0 === strpos( rtrim( $lines[ $i ] ), '<!-- /wp:' ) ) {
					break;
				}
			}
			$out[] = implode( "\n", $block );
			continue;
		}

		if ( '' === trim( $line ) ) {
			$flush();
			continue;
		}

		if ( preg_match( '/^(#{2,4}) (.+)$/', $line, $m ) ) {
			$flush();
			$level = strlen( $m[1] );

			// **Every heading carries an anchor.** The published guides are long enough to need a
			// table of contents, and a contents entry has to have somewhere to jump to. Written in
			// at build time rather than added by the theme at render time, because an anchor is
			// part of the document - it is what a link somebody shares points at, and it should not
			// change because a stylesheet did.
			//
			// Uniquified, because the guides repeat sections on purpose: the mentor guide carries
			// the student guide in full, so "Resources" and "If something looks wrong" each
			// appear twice and would otherwise share an anchor with the wrong one.
			$anchor = wpcpm_docs_anchor( $m[2] );

			// A reference part's `##` heading carries a class, so the guide view can fold what is under it.
			$marked = $reference && 2 === $level;

			$out[] = sprintf(
				"<!-- wp:heading {\"level\":%d,\"anchor\":\"%s\"%s} -->\n<h%d class=\"wp-block-heading%s\" id=\"%s\">%s</h%d>\n<!-- /wp:heading -->",
				$level,
				$anchor,
				$marked ? ',"className":"wpcpm-guide-ref"' : '',
				$level,
				$marked ? ' wpcpm-guide-ref' : '',
				$anchor,
				wpcpm_docs_inline( $m[2] ),
				$level
			);
			continue;
		}

		// Tables: a header row, a divider, then rows.
		if ( 0 === strpos( $line, '|' ) ) {
			$flush();
			$rows = array();
			while ( $i < $count && 0 === strpos( rtrim( $lines[ $i ] ), '|' ) ) {
				$rows[] = rtrim( $lines[ $i ] );
				$i++;
			}
			$i--;
			$out[] = wpcpm_docs_table( $rows );
			continue;
		}

		// Lists: bullets or numbers, each item possibly wrapped over several lines.
		if ( preg_match( '/^(\s*)([-*]|\d+\.) (.+)$/', $line, $m ) ) {
			$flush();
			$ordered = ! in_array( $m[2], array( '-', '*' ), true );
			$items   = array();
			$current = $m[3];

			while ( ++$i < $count ) {
				$next = rtrim( $lines[ $i ] );
				if ( preg_match( '/^(\s*)([-*]|\d+\.) (.+)$/', $next, $n ) ) {
					$items[] = $current;
					$current = $n[3];
					continue;
				}
				if ( '' === trim( $next ) ) {
					break;
				}
				// A continuation line of the item above.
				$current .= ' ' . trim( $next );
			}
			$items[] = $current;

			$tag  = $ordered ? 'ol' : 'ul';
			$attr = $ordered ? ' {"ordered":true}' : '';
			$li   = '';
			foreach ( $items as $item ) {
				$li .= "<!-- wp:list-item -->\n<li>" . wpcpm_docs_inline( trim( $item ) ) . "</li>\n<!-- /wp:list-item -->\n";
			}
			$out[] = sprintf( "<!-- wp:list%s -->\n<%s class=\"wp-block-list\">%s</%s>\n<!-- /wp:list -->", $attr, $tag, "\n" . $li, $tag );
			continue;
		}

		$buffer[] = trim( $line );
	}

	$flush();

	return implode( "\n\n", wpcpm_docs_notes( $out ) );
}

/**
 * A job's two notes, each as one block: the line "What happens next" or "Good to know" in bold, and the
 * list under it, in a group that carries the note's kind, so the guide's page can draw the two as the
 * notices they are (assets/css/administrator-guide.css). Only these two lines, only directly over a
 * bulleted list: any other paragraph in bold stays a paragraph. The Markdown output is not touched.
 *
 * The group's layout is named as the default (flow) one: a group with no layout takes the constrained
 * one when WordPress renders it, which caps and centers what is inside.
 *
 * @param string[] $blocks The section's blocks, in order.
 * @return string[]
 */
function wpcpm_docs_notes( $blocks ) {
	$kinds = array(
		'What happens next' => 'next',
		'Good to know'      => 'know',
	);
	$out   = array();
	$count = count( $blocks );

	for ( $i = 0; $i < $count; $i++ ) {
		$kind = '';
		$name = '';

		foreach ( $kinds as $title => $slug ) {
			if ( "<!-- wp:paragraph -->\n<p><strong>" . $title . "</strong></p>\n<!-- /wp:paragraph -->" === $blocks[ $i ] ) {
				$kind = $slug;
				$name = $title;
				break;
			}
		}

		if ( '' === $kind || ! isset( $blocks[ $i + 1 ] ) || 0 !== strpos( $blocks[ $i + 1 ], "<!-- wp:list -->\n" ) ) {
			$out[] = $blocks[ $i ];
			continue;
		}

		$class = 'wpcpm-guide-note wpcpm-guide-note--' . $kind;
		$out[] = '<!-- wp:group {"className":"' . $class . '","layout":{"type":"default"}} -->' . "\n"
			. '<div class="wp-block-group ' . $class . '"><!-- wp:paragraph {"className":"wpcpm-guide-note__title"} -->' . "\n"
			. '<p class="wpcpm-guide-note__title"><strong>' . $name . "</strong></p>\n<!-- /wp:paragraph -->\n\n"
			. $blocks[ $i + 1 ] . "</div>\n<!-- /wp:group -->";
		++$i;
	}

	return $out;
}

/**
 * A Markdown table as a table block.
 *
 * @param string[] $rows Raw pipe rows, including the divider.
 * @return string
 */
function wpcpm_docs_table( $rows ) {
	$cells = static function ( $row ) {
		$row = trim( $row, "| \t" );
		return array_map( 'trim', explode( '|', $row ) );
	};

	$head = $cells( array_shift( $rows ) );
	array_shift( $rows ); // The divider.

	$html = '<thead><tr>';
	foreach ( $head as $cell ) {
		$html .= '<th>' . wpcpm_docs_inline( $cell ) . '</th>';
	}
	$html .= '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$html .= '<tr>';
		foreach ( $cells( $row ) as $cell ) {
			$html .= '<td>' . wpcpm_docs_inline( $cell ) . '</td>';
		}
		$html .= '</tr>';
	}

	$html .= '</tbody>';

	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table>" . $html . "</table></figure>\n<!-- /wp:table -->";
}

/**
 * The pointers a section holds: `[[Heading text]]`, the text free of `[` and `]`.
 */
const WPCPM_DOCS_POINTER = '/\[\[([^\[\]]+)\]\]/';

/**
 * The anchors of one guide's headings, by heading text, in the order the block output numbers them.
 *
 * Walks the texts the way wpcpm_docs_to_blocks() does (headings of the second to fourth level), with
 * the same anchor function, so the anchor listed here is the one the heading is given. Leaves the
 * anchor numbering reset.
 *
 * @param string[] $texts The guide's Markdown texts in reading order: the intro, then each part.
 * @return array Stripped heading text => list of anchors.
 */
function wpcpm_docs_heading_map( $texts ) {
	$map = array();

	wpcpm_docs_anchor_reset();

	foreach ( $texts as $text ) {
		foreach ( explode( "\n", (string) $text ) as $line ) {
			if ( preg_match( '/^(#{2,4}) (.+)$/', rtrim( $line ), $m ) ) {
				$map[ wpcpm_docs_strip_inline( $m[2] ) ][] = wpcpm_docs_anchor( $m[2] );
			}
		}
	}

	wpcpm_docs_anchor_reset();

	return $map;
}

/**
 * The pointers in a text that name no heading of the guide, or a heading it holds more than once.
 *
 * @param string $text Markdown.
 * @param array  $map  The guide's heading map.
 * @return string[] The offending pointers as written, with the reason.
 */
function wpcpm_docs_pointer_problems( $text, $map ) {
	$problems = array();

	if ( preg_match_all( WPCPM_DOCS_POINTER, (string) $text, $found ) ) {
		foreach ( array_unique( $found[1] ) as $name ) {
			$n = isset( $map[ $name ] ) ? count( $map[ $name ] ) : 0;

			if ( 1 !== $n ) {
				$problems[] = '[[' . $name . ']] ' . ( 0 === $n ? 'names no heading in this guide' : 'names a heading that appears ' . $n . ' times in this guide' );
			}
		}
	}

	return $problems;
}

/**
 * The heading map pointers resolve against, set for each guide before its text is built.
 *
 * @param array|null $set A heading map to keep, or null to read it.
 * @return array
 */
function wpcpm_docs_pointer_map( $set = null ) {
	static $map = array();

	if ( null !== $set ) {
		$map = $set;
	}

	return $map;
}

/**
 * Pointers as Markdown links, for the repository copy.
 *
 * @param string $text Markdown.
 * @return string
 */
function wpcpm_docs_pointers_md( $text ) {
	$map = wpcpm_docs_pointer_map();

	return preg_replace_callback(
		WPCPM_DOCS_POINTER,
		static function ( $m ) use ( $map ) {
			return isset( $map[ $m[1] ][0] ) ? '[' . $m[1] . '](#' . $map[ $m[1] ][0] . ')' : $m[0];
		},
		(string) $text
	);
}

/**
 * Inline Markdown: bold, italic and code.
 *
 * @param string $text Text.
 * @return string
 */
function wpcpm_docs_inline( $text ) {
	// A pointer, `[[Heading text]]`, is held aside while the rest is escaped and put back as a link at the
	// end, so the bracket escape below never touches it. The map is the guide's own; a pointer the build
	// has not checked is left to the escape like any other bracket.
	$pointers = array();
	$map      = wpcpm_docs_pointer_map();
	$text     = preg_replace_callback(
		WPCPM_DOCS_POINTER,
		static function ( $m ) use ( &$pointers, $map ) {
			if ( ! isset( $map[ $m[1] ][0] ) ) {
				return $m[0];
			}

			$pointers[] = '<a href="#' . $map[ $m[1] ][0] . '">' . htmlspecialchars( $m[1], ENT_NOQUOTES, 'UTF-8', false ) . '</a>';

			return "\x01" . ( count( $pointers ) - 1 ) . "\x02";
		},
		(string) $text
	);

	// The text is prose written as Markdown, and only its own four marks are markup: an
	// ampersand or an angle bracket in it is a character to read, so it is escaped before the
	// marks are turned into tags (clean-up 1.98.1; the S6 review found `<record>` swallowed).
	// Quotes stay: the sections use them as prose and only a link's address lands in an
	// attribute, which escapes its own.
	$text = htmlspecialchars( $text, ENT_NOQUOTES, 'UTF-8', false );

	// **Links, added 1.102.2.** A section that wrote one got the Markdown printed at the
	// reader verbatim: `35-admin-feedback` has carried a raw `[#123](…)` since it was written.
	// Deliberately narrow - http and https only, so a `javascript:` address cannot be written
	// into a guide, and no whitespace, angle bracket or quote in the address, so it cannot end
	// the attribute early. Anything that does not match is left as the text it already was.
	// A page of this site may be named by its path alone (`/student-guide/`): one slash, then
	// letters, digits, hyphens and slashes, so it holds no scheme and no second slash to make one.
	$text = preg_replace_callback(
		'/\[([^\]]+)\]\((https?:\/\/[^\s()<>"\']+|\/[a-z0-9][a-z0-9\/-]*)\)/',
		static function ( $m ) {
			return '<a href="' . $m[2] . '">' . $m[1] . '</a>';
		},
		$text
	);

	// **A bracket left now is one to read (1.123.70).** The guides name shortcodes, `[wpcpm_mentor_application]`
	// and `[wpcpm_student_interest]`, and WordPress ran every name it found on the published page: the
	// Administrator guide drew a working Mentor Application form and a Student Interest Form in the middle
	// of its text. As `&#91;` it reads the same and runs nothing.
	$text = str_replace( '[', '&#91;', $text );

	$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
	$text = preg_replace( '/(?<![*\w])\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text );
	$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

	return preg_replace_callback(
		'/\x01(\d+)\x02/',
		static function ( $m ) use ( $pointers ) {
			return $pointers[ (int) $m[1] ];
		},
		$text
	);
}

$clips = wpcpm_docs_clips( $root . '/docs/clips.json' );

// Everything is built in memory first and written after, so a build that stops writes no file.
$made   = array();
$result = array();

foreach ( $guides as $slug => $guide ) {
	// Anchors are numbered per guide, so the second "Resources" in the mentor guide is `resources-2`
	// there and the only one in the student guide stays `resources`.
	wpcpm_docs_anchor_reset();

	$body      = array();
	$block_out = array();
	$texts     = array( $guide['intro'] );

	foreach ( $guide['parts'] as $part ) {
		$text    = wpcpm_docs_section( $sections, $part );
		$texts[] = isset( $guide['demote'] ) && in_array( $part, $guide['demote'], true ) ? wpcpm_docs_demote( $text ) : $text;
	}

	// A pointer, `[[Heading text]]`, is checked against this guide's own headings before anything is built:
	// a pointer to a heading that is gone, renamed or written twice would be a dead link in the guide.
	$map = wpcpm_docs_heading_map( $texts );
	wpcpm_docs_pointer_map( $map );

	foreach ( $texts as $n => $text ) {
		foreach ( wpcpm_docs_pointer_problems( $text, $map ) as $problem ) {
			fwrite( STDERR, 'Guide "' . $slug . '", ' . ( 0 === $n ? 'its intro' : 'section ' . $guide['parts'][ $n - 1 ] ) . ': ' . $problem . ".\n" );
			$failed = true;
		}
	}

	if ( ! empty( $failed ) ) {
		exit( 1 );
	}

	foreach ( $guide['parts'] as $part ) {
		$section = wpcpm_docs_section( $sections, $part );
		$section = isset( $guide['demote'] ) && in_array( $part, $guide['demote'], true ) ? wpcpm_docs_demote( $section ) : $section;

		wpcpm_docs_clips_check( $section, $clips );

		$body[] = $section;
	}

	wpcpm_docs_clips_check( $guide['intro'], $clips );

	// Block markup is built part by part, in order, so a reference part can mark its headings. The parts
	// were joined by a blank line before and are still, and every part ends its own paragraph and list.
	$blocks = wpcpm_docs_to_blocks( wpcpm_docs_images_blocks( wpcpm_docs_clips_blocks( $guide['intro'], $clips ), $base, $ids ) ) . "\n\n";
	$pieces = array();

	foreach ( $guide['parts'] as $n => $part ) {
		$is_ref   = isset( $guide['reference'] ) && in_array( $part, $guide['reference'], true );
		$pieces[] = wpcpm_docs_to_blocks( wpcpm_docs_images_blocks( wpcpm_docs_clips_blocks( $body[ $n ], $clips ), $base, $ids ), $is_ref );
	}

	$blocks .= implode( "\n\n", $pieces ) . "\n";

	$body = implode( "\n\n", $body );

	// Markdown, for reading in the repository.
	$md  = '# ' . $guide['title'] . "\n\n";
	$md .= '*' . $guide['lede'] . "*\n\n";
	$md .= wpcpm_docs_pointers_md( wpcpm_docs_clips_md( $guide['intro'], $clips ) ) . "\n\n";
	$md .= wpcpm_docs_pointers_md( wpcpm_docs_images_md( wpcpm_docs_clips_md( $body, $clips ), 'images' ) ) . "\n";

	$result[ $slug ] = array( $md, $blocks );
	$made[ $slug ]   = array( strlen( $md ), substr_count( $blocks, '<!-- wp:' ) );
}

foreach ( $result as $slug => $texts ) {
	file_put_contents( $out_md . '/' . $slug . '.md', $texts[0] );
	file_put_contents( $out_html . '/' . $slug . '.html', $texts[1] );
}

foreach ( $made as $slug => $info ) {
	printf( "%-16s %6d bytes of Markdown, %3d blocks\n", $slug, $info[0], $info[1] );
}

echo "Done.\n";
