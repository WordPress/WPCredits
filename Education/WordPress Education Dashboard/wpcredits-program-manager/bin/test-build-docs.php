<?php
/**
 * The guide builder (bin/build-docs.php): the guides' composition, the reference marker, clips.
 *
 * The builder runs as a script, so each check builds into a temporary copy of the tree (the builder,
 * the guides table, the sections and the clips table) and reads what came out. Nothing is written in
 * this checkout.
 *
 * Run from the plugin root:  php bin/test-build-docs.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

$plugin = dirname( __DIR__ );
$made   = array();

/**
 * A scratch tree with the builder, its table, the sections and a clips table, and the sections edited by $edit.
 *
 * @param string|null $clips JSON for docs/clips.json, or null to copy the real one.
 * @param array       $edit  Section slug => text to append.
 * @return string The root.
 */
function scratch_tree( $clips = null, array $edit = array() ) {
	global $plugin, $made;
	$root   = rtrim( sys_get_temp_dir(), '/' ) . '/wpcpm-build-docs-' . getmypid() . '-' . count( $made );
	$made[] = $root;
	mkdir( $root . '/bin', 0700, true );
	mkdir( $root . '/docs/sections', 0700, true );
	mkdir( $root . '/docs/build', 0700, true );
	copy( $plugin . '/bin/build-docs.php', $root . '/bin/build-docs.php' );
	if ( is_file( $plugin . '/bin/docs-guides.php' ) ) {
		copy( $plugin . '/bin/docs-guides.php', $root . '/bin/docs-guides.php' );
	}
	foreach ( (array) glob( $plugin . '/docs/sections/*.md' ) as $f ) {
		copy( $f, $root . '/docs/sections/' . basename( $f ) );
	}
	if ( null === $clips ) {
		if ( is_file( $plugin . '/docs/clips.json' ) ) {
			copy( $plugin . '/docs/clips.json', $root . '/docs/clips.json' );
		}
	} else {
		file_put_contents( $root . '/docs/clips.json', $clips );
	}
	foreach ( $edit as $slug => $text ) {
		file_put_contents( $root . '/docs/sections/' . $slug . '.md', "\n\n" . $text . "\n", FILE_APPEND );
	}
	return $root;
}

function build( $root ) {
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/bin/build-docs.php' ) . ' 2>&1', $out, $status );
	return array( $status, implode( "\n", $out ) );
}

function outputs( $root ) {
	$all = array();
	foreach ( array_merge( (array) glob( $root . '/docs/*.md' ), (array) glob( $root . '/docs/build/*.html' ) ) as $f ) {
		$all[ substr( $f, strlen( $root ) ) ] = md5_file( $f );
	}
	ksort( $all );
	return $all;
}

function cleanup( $root ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() );
	}
	@rmdir( $root );
}

// The guides' composition.
$guides = is_file( $plugin . '/bin/docs-guides.php' ) ? require $plugin . '/bin/docs-guides.php' : array();

ck( 'the guides table is bin/docs-guides.php, five guides', array_keys( $guides ), array( 'students', 'mentors', 'administrators', 'institutions', 'sponsors' ) );
$admin = $guides['administrators'] ?? array();
ck( 'the Administrator guide is the jobs first, then the reference, then the other guides',
	$admin['parts'] ?? null,
	array( '60-admin-start', '61-admin-steps-institutions', '62-admin-steps-students', '63-admin-steps-mentors', '64-admin-steps-sponsors', '65-admin-steps-site', '66-admin-where-it-stands', '30-admin-wpadmin', '31-admin-settings', '32-admin-tools', '33-admin-access', '34-admin-operations', '35-admin-feedback', '00-signing-in', '67-admin-other-guides' )
);
ck( 'its reference parts are the screens, settings, tools, access, operations, feedback and signing in',
	$admin['reference'] ?? null,
	array( '30-admin-wpadmin', '31-admin-settings', '32-admin-tools', '33-admin-access', '34-admin-operations', '35-admin-feedback', '00-signing-in' )
);
ck( 'it holds none of the mentor or student sections',
	array_values( array_filter( $admin['parts'] ?? array( 'x' ), static function ( $p ) { return 1 === preg_match( '/^(1\d|2\d)-/', $p ); } ) ),
	array()
);
ck( 'the other four guides keep their parts',
	array( $guides['students']['parts'] ?? null, $guides['institutions']['parts'] ?? null, $guides['sponsors']['parts'] ?? null, count( $guides['mentors']['parts'] ?? array() ), isset( $guides['students']['reference'], $guides['mentors']['reference'], $guides['institutions']['reference'], $guides['sponsors']['reference'] ) ),
	array(
		array( '00-signing-in', '10-student-card', '11-student-booking', '13-student-feedback', '12-student-help', '14-student-program' ),
		array( '00-signing-in', '50-institution-dashboard', '51-institution-students', '52-institution-details' ),
		array( '00-signing-in', '40-sponsor-dashboard', '41-sponsor-sponsorship', '42-sponsor-offers', '43-sponsor-posts', '44-sponsor-mentors', '45-sponsor-company', '46-sponsor-visibility' ),
		14,
		false,
	)
);

// The reference marker, in the block output only.
$root = scratch_tree();
list( $status, $log ) = build( $root );
$html = (string) @file_get_contents( $root . '/docs/build/administrators.html' );
$md   = (string) @file_get_contents( $root . '/docs/administrators.md' );
ck( 'the build of the real tree succeeds', $status, 0 );
ck( 'a reference part\'s ## heading carries wpcpm-guide-ref',
	false !== strpos( $html, '<!-- wp:heading {"level":2,"anchor":"the-plugin-in-wp-admin","className":"wpcpm-guide-ref"} -->' ) && false !== strpos( $html, '<h2 class="wp-block-heading wpcpm-guide-ref" id="the-plugin-in-wp-admin">' ),
	true
);
ck( 'a job part\'s ## heading does not',
	array( false !== strpos( $html, '<!-- wp:heading {"level":2,"anchor":"start-here"} -->' ), false !== strpos( $html, '<h2 class="wp-block-heading" id="start-here">' ) ),
	array( true, true )
);
ck( 'the Markdown carries no marker, and the other guides\' blocks none', array( substr_count( $md, 'wpcpm-guide-ref' ), substr_count( (string) @file_get_contents( $root . '/docs/build/students.html' ), 'wpcpm-guide-ref' ) ), array( 0, 0 ) );
$first = outputs( $root );
build( $root );
ck( 'a second build changes nothing', outputs( $root ), $first );
$same = true;
foreach ( array_keys( $first ) as $f ) {
	$same = $same && is_file( $plugin . $f ) && md5_file( $plugin . $f ) === md5_file( $root . $f );
}
ck( 'the committed guides are what the build makes now', $same, true );
cleanup( $root );

// Clips.
$entry = array( 'id' => 77, 'src' => 'https://example.test/u/a.mp4', 'poster' => 'https://example.test/u/a.jpg', 'captions' => 'https://example.test/u/a.vtt', 'width' => 1280, 'height' => 720, 'caption' => 'Approving a school & "its" agreement.' );
$root  = scratch_tree( json_encode( array( 'demo' => $entry ) ), array( '60-admin-start' => '{{clip:demo}}' ) );
list( $status ) = build( $root );
$html     = (string) @file_get_contents( $root . '/docs/build/administrators.html' );
$md       = (string) @file_get_contents( $root . '/docs/administrators.md' );
$expected = "<!-- wp:video {\"id\":77,\"tracks\":[{\"src\":\"https://example.test/u/a.vtt\",\"kind\":\"captions\",\"srcLang\":\"en\",\"label\":\"English\"}]} -->\n"
	. '<figure class="wp-block-video wpcpm-guide-clip"><video controls preload="none" poster="https://example.test/u/a.jpg" src="https://example.test/u/a.mp4" width="1280" height="720"><track src="https://example.test/u/a.vtt" kind="captions" srclang="en" label="English"/></video><figcaption class="wp-element-caption">Approving a school &amp; &quot;its&quot; agreement.</figcaption></figure>'
	. "\n<!-- /wp:video -->";
ck( 'a clip token builds the video block from its table entry, values escaped', array( $status, false !== strpos( $html, $expected ) ), array( 0, true ) );
ck( 'the clip never autoplays, and the token is gone', array( stripos( $html, 'autoplay' ), strpos( $html, '{{clip:' ) ), array( false, false ) );
ck( 'in Markdown a clip is its caption in italics', false !== strpos( $md, "*Clip: Approving a school & \"its\" agreement.*" ) && false === strpos( $md, '{{clip:' ), true );
cleanup( $root );

foreach ( array(
	'an unknown slug'    => '{}',
	'an entry missing its poster' => json_encode( array( 'demo' => array_diff_key( $entry, array( 'poster' => 1 ) ) ) ),
) as $what => $clips ) {
	$root = scratch_tree( $clips, array( '60-admin-start' => '{{clip:demo}}' ) );
	list( $status, $log ) = build( $root );
	ck( $what . ' stops the build with exit 1, names the slug and writes no file', array( $status, false !== strpos( $log, 'demo' ), outputs( $root ) ), array( 1, true, array() ) );
	cleanup( $root );
}

// A value that could end the block comment is escaped the way WordPress writes block attributes.
$nasty = $entry;
$nasty['captions'] = 'https://example.test/u/a.vtt?x=1&y="2"<b>--></b>';
$root = scratch_tree( json_encode( array( 'demo' => $nasty ) ), array( '60-admin-start' => '{{clip:demo}}' ) );
list( $status ) = build( $root );
$html = (string) @file_get_contents( $root . '/docs/build/administrators.html' );
$at   = strpos( $html, '<!-- wp:video ' );
$end  = false === $at ? false : strpos( $html, '-->', $at );
$head = false === $at ? '' : substr( $html, $at, $end - $at + 3 );
ck( 'a captions URL holding -->, <, > & and a quote builds a block comment with the escapes, ending only at its own -->',
	array( $status, false !== strpos( $head, '\u002d\u002d\u003e' ), false !== strpos( $head, '\u0026y=\u00222\u0022\u003cb\u003e' ), 1 === substr_count( $head, '-->' ), false === strpos( substr( $head, 5 ), '<' ), '}' . ' -->' === substr( $head, -5 ) ),
	array( 0, true, true, true, true, true )
);
cleanup( $root );

foreach ( array(
	'a line starting {{clip: that is not a token'        => array( '{}', "{{clip:Bad_Slug}}", 'Bad_Slug' ),
	'a clip token with a trailing word'                   => array( '{}', "{{clip:demo}} and more", 'and more' ),
	'a clip field that is a list'                         => array( json_encode( array( 'demo' => array_merge( $entry, array( 'src' => array( 'x' ) ) ) ) ), '{{clip:demo}}', 'src' ),
	'a clip field that is true'                           => array( json_encode( array( 'demo' => array_merge( $entry, array( 'caption' => true ) ) ) ), '{{clip:demo}}', 'caption' ),
) as $what => $case ) {
	$root = scratch_tree( $case[0], array( '60-admin-start' => $case[1] ) );
	list( $status, $log ) = build( $root );
	ck( $what . ' stops the build with exit 1, names it and writes no file', array( $status, false !== strpos( $log, $case[2] ), outputs( $root ) ), array( 1, true, array() ) );
	cleanup( $root );
}

// Pointers: [[Heading text]] links to a heading of the same guide.
$committed = array();
foreach ( array( 'students', 'mentors', 'institutions', 'sponsors' ) as $g ) {
	$committed[ '/docs/' . $g . '.md' ]         = md5_file( $plugin . '/docs/' . $g . '.md' );
	$committed[ '/docs/build/' . $g . '.html' ] = md5_file( $plugin . '/docs/build/' . $g . '.html' );
}
ksort( $committed );
$ptr_src = "### What's next: a test, really?\n\nBack to [[What's next: a test, really?]] and on to [[Start here]] now, then [bracket] stays.\n\n- A list item with [[Start here]].\n\n| Job | See |\n|---|---|\n| One | [[What's next: a test, really?]] |\n";
$root = scratch_tree( null, array( '60-admin-start' => $ptr_src ) );
list( $status ) = build( $root );
$html = (string) @file_get_contents( $root . '/docs/build/administrators.html' );
$md   = (string) @file_get_contents( $root . '/docs/administrators.md' );
$a    = 'what-s-next-a-test-really';
ck( 'a pointer in a paragraph builds the link in the blocks, two on one line both resolve',
	array( $status, false !== strpos( $html, '<p>Back to <a href="#' . $a . '">What\'s next: a test, really?</a> and on to <a href="#start-here">Start here</a> now, then &#91;bracket] stays.</p>' ) ),
	array( 0, true )
);
ck( 'a pointer in a list item and in a table cell builds the link',
	array( false !== strpos( $html, '<li>A list item with <a href="#start-here">Start here</a>.</li>' ), false !== strpos( $html, '<td><a href="#' . $a . '">What\'s next: a test, really?</a></td>' ) ),
	array( true, true )
);
ck( 'the heading the pointer names has that anchor', false !== strpos( $html, 'id="' . $a . '"' ), true );
ck( 'in Markdown a pointer is a link to the anchor, in a paragraph, a list item and a table cell',
	array(
		false !== strpos( $md, "Back to [What's next: a test, really?](#" . $a . ') and on to [Start here](#start-here) now, then [bracket] stays.' ),
		false !== strpos( $md, '- A list item with [Start here](#start-here).' ),
		false !== strpos( $md, "| One | [What's next: a test, really?](#" . $a . ') |' ),
	),
	array( true, true, true )
);
ck( 'no pointer brackets are left, and the four other guides are unchanged',
	array( strpos( $html . $md, '[[' ), array_intersect_key( outputs( $root ), $committed ) ),
	array( false, $committed )
);
cleanup( $root );

$root = scratch_tree( null, array( '60-admin-start' => 'See [[No such job]].' ) );
list( $status, $log ) = build( $root );
ck( 'a pointer to an unknown heading stops the build with exit 1, names the guide, section and pointer, and writes nothing',
	array( $status, false !== strpos( $log, 'administrators' ), false !== strpos( $log, '60-admin-start' ), false !== strpos( $log, '[[No such job]]' ), outputs( $root ) ),
	array( 1, true, true, true, array() )
);
cleanup( $root );

$root = scratch_tree( null, array( '60-admin-start' => "### Twice\n\n### Twice\n\nSee [[Twice]]." ) );
list( $status, $log ) = build( $root );
ck( 'a pointer to a heading that appears twice in the guide stops the build, and writes nothing',
	array( $status, false !== strpos( $log, '[[Twice]]' ), outputs( $root ) ),
	array( 1, true, array() )
);
cleanup( $root );

$root = scratch_tree( null, array( '60-admin-start' => "### Twice\n\n### Twice\n\nPlain text." ) );
list( $status ) = build( $root );
ck( 'a repeated heading nobody points at is not an error', $status, 0 );
cleanup( $root );

// A page of this site may be linked by its path; nothing else without a scheme may.
$root = scratch_tree( null, array( '60-admin-start' => 'Read the [Student guide](/student-guide/), not [this](//evil.example/x), [that](/a b) or [those](javascript:alert).' ) );
list( $status, $out ) = build( $root );
$html = (string) @file_get_contents( $root . '/docs/build/administrators.html' );
ck( 'a link to a page of this site by its path is built, and a protocol-relative address, a path with a space and a scripted address are left as text',
	array( $status, false !== strpos( $html, '<a href="/student-guide/">Student guide</a>' ), substr_count( $html, 'href="//' ) + substr_count( $html, 'href="/a' ) + substr_count( $html, 'href="javascript' ) ),
	array( 0, true, 0 ) );
cleanup( $root );


// A shared section resolves against each guide's own headings.
$root = scratch_tree( null, array( '00-signing-in' => 'Back to [[Signing in]].' ) );
list( $status ) = build( $root );
$s = (string) @file_get_contents( $root . '/docs/build/students.html' );
$i = (string) @file_get_contents( $root . '/docs/build/institutions.html' );
ck( 'a shared section\'s pointer resolves in every guide that carries it',
	array( $status, false !== strpos( $s, 'Back to <a href="#signing-in">Signing in</a>.' ), false !== strpos( $i, 'Back to <a href="#signing-in">Signing in</a>.' ) ),
	array( 0, true, true )
);
cleanup( $root );

$root = scratch_tree( null, array( '00-signing-in' => 'See [[Start here]].' ) );
list( $status, $log ) = build( $root );
ck( 'a shared section\'s pointer to a heading another guide lacks stops the build, naming the guide that lacks it',
	array( $status, false !== strpos( $log, 'students' ), outputs( $root ) ),
	array( 1, true, array() )
);
cleanup( $root );


// A job's two notes: the bold line and the list under it become one group, which the guide's page draws as
// a notice. Only those two lines, only over a bulleted list.
$note_src = "### A job for the notes test\n\n1. Press it.\n\n**What happens next**\n\n- The first thing.\n- The second, with **a name**.\n\n**Good to know**\n\n- One caution.\n\n**Some other line in bold**\n\n- Its list.\n\n**Good to know**\n\nA paragraph, not a list.\n\n**What happens next**\n\n1. A numbered list.\n";
$root = scratch_tree( null, array( '60-admin-start' => $note_src ) );
list( $status ) = build( $root );
$html = (string) @file_get_contents( $root . '/docs/build/administrators.html' );
$md   = (string) @file_get_contents( $root . '/docs/administrators.md' );
$next = '<!-- wp:group {"className":"wpcpm-guide-note wpcpm-guide-note--next","layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group wpcpm-guide-note wpcpm-guide-note--next"><!-- wp:paragraph {"className":"wpcpm-guide-note__title"} -->' . "\n"
	. '<p class="wpcpm-guide-note__title"><strong>What happens next</strong></p>' . "\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n<!-- wp:list-item -->\n<li>The first thing.</li>\n<!-- /wp:list-item -->\n<!-- wp:list-item -->\n<li>The second, with <strong>a name</strong>.</li>\n<!-- /wp:list-item -->\n</ul>\n<!-- /wp:list --></div>\n<!-- /wp:group -->";
$know = '<div class="wp-block-group wpcpm-guide-note wpcpm-guide-note--know"><!-- wp:paragraph {"className":"wpcpm-guide-note__title"} -->' . "\n"
	. '<p class="wpcpm-guide-note__title"><strong>Good to know</strong></p>' . "\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n<!-- wp:list-item -->\n<li>One caution.</li>\n<!-- /wp:list-item -->\n</ul>\n<!-- /wp:list --></div>\n<!-- /wp:group -->";
ck( 'What happens next and the list under it are one group, with the flow layout named and the note\'s kind as its class',
	array( $status, false !== strpos( $html, $next ) ), array( 0, true ) );
ck( 'Good to know and its list are one group of the other kind', false !== strpos( $html, $know ), true );
ck( 'any other line in bold stays a paragraph over its list',
	false !== strpos( $html, "<!-- wp:paragraph -->\n<p><strong>Some other line in bold</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->" ), true );
ck( 'and so does a note\'s line over a paragraph or a numbered list',
	array(
		false !== strpos( $html, "<!-- wp:paragraph -->\n<p><strong>Good to know</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>A paragraph, not a list.</p>" ),
		false !== strpos( $html, "<!-- wp:paragraph -->\n<p><strong>What happens next</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list {\"ordered\":true} -->" ),
	),
	array( true, true ) );
ck( 'every group that opens closes, and none is inside another',
	array( substr_count( $html, '<!-- wp:group ' ) === substr_count( $html, '<!-- /wp:group -->' ), preg_match( '#<!-- wp:group [^>]*-->(?:(?!<!-- /wp:group -->).)*<!-- wp:group #s', $html ) ),
	array( true, 0 ) );
ck( 'the Markdown keeps the bold line and its list as they were written, and the four other guides are unchanged',
	array( false !== strpos( $md, "**What happens next**\n\n- The first thing.\n- The second, with **a name**." ), strpos( $md, 'wpcpm-guide-note' ), array_intersect_key( outputs( $root ), $committed ) ),
	array( true, false, $committed ) );
cleanup( $root );

// The real guide: every note of every job is drawn as one, and no bold-only line named as a note is left bare.
$real = (string) @file_get_contents( $plugin . '/docs/build/administrators.html' );
ck( 'in the built Administrator guide no What happens next or Good to know is left as a bare paragraph, and the other guides hold no note',
	array(
		substr_count( $real, "<!-- wp:paragraph -->\n<p><strong>What happens next</strong></p>" ) + substr_count( $real, "<!-- wp:paragraph -->\n<p><strong>Good to know</strong></p>" ),
		substr_count( $real, '<div class="wp-block-group wpcpm-guide-note wpcpm-guide-note--next">' ) > 50,
		substr_count( $real, '<div class="wp-block-group wpcpm-guide-note wpcpm-guide-note--know">' ) > 50,
		substr_count( (string) @file_get_contents( $plugin . '/docs/build/students.html' ) . (string) @file_get_contents( $plugin . '/docs/build/mentors.html' ) . (string) @file_get_contents( $plugin . '/docs/build/institutions.html' ) . (string) @file_get_contents( $plugin . '/docs/build/sponsors.html' ), 'wpcpm-guide-note' ),
	),
	array( 0, true, true, 0 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
