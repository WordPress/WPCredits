<?php
/**
 * Assemble the three program guides from one set of sections.
 *
 * Three audiences, and each guide has to be complete on its own: the access levels do not
 * nest, so a mentor holding only `wpcpm_view_mentor_content` cannot open a Student-level
 * page. A mentor guide that linked to the student guide would be linking them somewhere
 * they get an access notice.
 *
 * So the shared parts are written once, in docs/sections, and each guide is composed from
 * them here. Change the student booking section and it changes in all three.
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
	'admin-header-notices'                   => 555,
	'admin-access-level'                     => 554,
);

// Each guide: its title, its lead-in, and the sections it is made of. Order matters - this
// is the reading order.
$guides = array(
	'students'       => array(
		'title' => 'Student guide',
		'lede'  => 'Everything on your Student Report Card, and how to book a call with your mentor.',
		'intro' => "Welcome to the WordPress Education Dashboard. This is where your place on the WordPress Credits Program lives while you are on it: your program details, who your mentor is, and the calendar you book your calls from.\n\nThis guide is written for students. Your mentor and the program managers have their own.",
		'parts' => array( '00-signing-in', '10-student-card', '11-student-booking', '13-student-feedback', '12-student-help' ),
	),
	'mentors'        => array(
		'title' => 'Mentor guide',
		'lede'  => 'Your Mentor Report Card, setting the hours students can book, and what your students see on their own page.',
		'intro' => "Thank you for mentoring on the WordPress Credits Program. This guide covers your own Report Card and, at the end, what your students see on theirs - you cannot open a student's page yourself, so the whole of it is repeated here.\n\nIf you read one section today, make it *Setting your availability*: until you publish some hours, nobody can book a call with you.",
		'parts' => array(
			'00-signing-in',
			'20-mentor-card',
			'21-mentor-availability',
			'22-mentor-help',
			'23-mentor-student-view',
			'10-student-card',
			'11-student-booking',
			'13-student-feedback',
			'12-student-help',
		),
	),
	'administrators' => array(
		'title' => 'Program manager guide',
		'lede'  => 'The plugin in wp-admin - settings, modules, access levels and the sync - plus everything mentors and students are told.',
		'intro' => "This guide covers running the program: the plugin's own screens in wp-admin, what every setting does, who can read what, and what to check when something looks wrong.\n\nIt also contains the mentor and student guides in full. Program managers are the people other people ask, and the access levels mean you cannot open either of those pages yourself.",
		'parts' => array(
			'30-admin-wpadmin',
			'31-admin-settings',
			'32-admin-tools',
			'33-admin-access',
			'34-admin-operations',
			'35-admin-feedback',
			'20-mentor-card',
			'21-mentor-availability',
			'22-mentor-help',
			'23-mentor-student-view',
			'10-student-card',
			'11-student-booking',
			'13-student-feedback',
			'12-student-help',
		),
	),
);

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
 * Convert the Markdown subset the sections use into block markup.
 *
 * Deliberately small: headings, paragraphs, lists, tables and the image blocks already
 * substituted above. A general Markdown parser would be a dependency, and these are
 * documents this repository writes for itself.
 *
 * @param string $md Markdown.
 * @return string
 */
function wpcpm_docs_to_blocks( $md ) {
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
			// the student guide in full, so "What is on your Report Card" and "Resources" each
			// appear twice and would otherwise share an anchor with the wrong one.
			$anchor = wpcpm_docs_anchor( $m[2] );

			$out[] = sprintf(
				"<!-- wp:heading {\"level\":%d,\"anchor\":\"%s\"} -->\n<h%d class=\"wp-block-heading\" id=\"%s\">%s</h%d>\n<!-- /wp:heading -->",
				$level,
				$anchor,
				$level,
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

	return implode( "\n\n", $out );
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
 * Inline Markdown: bold, italic and code.
 *
 * @param string $text Text.
 * @return string
 */
function wpcpm_docs_inline( $text ) {
	$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
	$text = preg_replace( '/(?<![*\w])\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text );
	$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

	return $text;
}

$made = array();

foreach ( $guides as $slug => $guide ) {
	// Anchors are numbered per guide, so the second "Resources" in the mentor guide is `resources-2`
	// there and the only one in the student guide stays `resources`.
	wpcpm_docs_anchor_reset();

	$body = array();

	foreach ( $guide['parts'] as $part ) {
		$body[] = wpcpm_docs_section( $sections, $part );
	}

	$body = implode( "\n\n", $body );

	// Markdown, for reading in the repository.
	$md  = '# ' . $guide['title'] . "\n\n";
	$md .= '*' . $guide['lede'] . "*\n\n";
	$md .= $guide['intro'] . "\n\n";
	$md .= wpcpm_docs_images_md( $body, 'images' ) . "\n";

	file_put_contents( $out_md . '/' . $slug . '.md', $md );

	// Block markup, for the published page.
	$blocks  = wpcpm_docs_to_blocks( wpcpm_docs_images_blocks( $guide['intro'], $base, $ids ) ) . "\n\n";
	$blocks .= wpcpm_docs_to_blocks( wpcpm_docs_images_blocks( $body, $base, $ids ) ) . "\n";

	file_put_contents( $out_html . '/' . $slug . '.html', $blocks );

	$made[ $slug ] = array( strlen( $md ), substr_count( $blocks, '<!-- wp:' ) );
}

foreach ( $made as $slug => $info ) {
	printf( "%-16s %6d bytes of Markdown, %3d blocks\n", $slug, $info[0], $info[1] );
}

echo "Done.\n";
