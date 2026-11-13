<?php
/**
 * A name in bold in the Administrator guide is a name in the code.
 *
 * Every **bold** span in a covered section has to be found, word for word, inside a string in the
 * plugin's code (includes/, blocks/, assets/ and the main file; PHP and JS), or be listed in
 * bin/fixtures/guide-labels-allow.json with the reason: a label WordPress or another plugin prints
 * ("wordpress"), a name in a service outside the site ("other-service"), a label the code builds
 * from a string with a placeholder ("placeholder", with that string as its pattern), or a run-in
 * heading of the guide's own fixed build ("heading").
 *
 * The reference (30 to 35) joined the covered list when it was rewritten into entries, where bold is
 * a name on the screen and nothing else. 00 (signing in) is shared with the other guides and is not covered.
 *
 * Run from the plugin root:  php bin/test-guide-labels.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

// The sections the check covers.
$covered = array(
	'60-admin-start',
	'61-admin-steps-institutions',
	'62-admin-steps-students',
	'63-admin-steps-mentors',
	'64-admin-steps-sponsors',
	'65-admin-steps-site',
	'66-admin-where-it-stands',
	'67-admin-other-guides',
	'30-admin-wpadmin',
	'31-admin-settings',
	'32-admin-tools',
	'33-admin-access',
	'34-admin-operations',
	'35-admin-feedback',
);

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

/**
 * The strings of a JS source, comments left out: '...' and "..." (one line each) and `...`.
 *
 * Read with a small scanner, so a quote inside a comment starts no string and a label that exists only
 * in a comment is not read. A regular expression literal holding a quote can confuse it; none of the
 * plugin's labels sit in one.
 *
 * @param string $src JS source.
 * @return string The strings, one per line break, with \' and \" undone.
 */
function js_strings( $src ) {
	$out = array();
	$n   = strlen( $src );
	$i   = 0;

	while ( $i < $n ) {
		$c = $src[ $i ];

		if ( '/' === $c && $i + 1 < $n && '/' === $src[ $i + 1 ] ) {
			$e = strpos( $src, "\n", $i );
			$i = false === $e ? $n : $e;
			continue;
		}
		if ( '/' === $c && $i + 1 < $n && '*' === $src[ $i + 1 ] ) {
			$e = strpos( $src, '*/', $i + 2 );
			$i = false === $e ? $n : $e + 2;
			continue;
		}
		if ( "'" === $c || '"' === $c || '`' === $c ) {
			$j   = $i + 1;
			$buf = '';
			while ( $j < $n && $src[ $j ] !== $c ) {
				if ( '\\' === $src[ $j ] && $j + 1 < $n ) {
					$buf .= $src[ $j + 1 ];
					$j   += 2;
					continue;
				}
				if ( "\n" === $src[ $j ] && '`' !== $c ) {
					break;
				}
				$buf .= $src[ $j++ ];
			}
			$out[] = $buf;
			$i     = $j + 1;
			continue;
		}
		++$i;
	}

	return implode( "\n", $out );
}

/**
 * Every string in the code, one per entry, with \' and \" undone and entities decoded.
 *
 * PHP is read with the tokenizer, so a comment's apostrophe starts no string. JS goes through js_strings().
 *
 * @param string $root Plugin root.
 * @return string[]
 */
function code_strings( $root ) {
	$files = array( $root . '/wpcredits-program-manager.php' );
	foreach ( array( 'includes', 'blocks', 'assets' ) as $dir ) {
		if ( ! is_dir( $root . '/' . $dir ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( preg_match( '/\.(php|js)$/', $f->getFilename() ) && ! preg_match( '/\.min\.js$/', $f->getFilename() ) ) {
				$files[] = $f->getPathname();
			}
		}
	}
	sort( $files );

	$strings = array();
	foreach ( $files as $file ) {
		$src = (string) file_get_contents( $file );
		if ( '.php' === substr( $file, -4 ) ) {
			foreach ( token_get_all( $src ) as $t ) {
				if ( is_array( $t ) && in_array( $t[0], array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML ), true ) ) {
					// A quoted string's token holds its quotes: without them a one-word name can equal the string.
					$strings[] = T_CONSTANT_ENCAPSED_STRING === $t[0] ? substr( $t[1], 1, -1 ) : $t[1];
				}
			}
		} else {
			$strings[] = js_strings( $src );
		}
	}

	return array_map(
		static function ( $str ) {
			return html_entity_decode( str_replace( array( "\\'", '\\"' ), array( "'", '"' ), $str ), ENT_QUOTES, 'UTF-8' );
		},
		$strings
	);
}

/**
 * Whether a bold name is in the code's strings, on whole words.
 *
 * A name of one word passes only as a whole string, a trailing colon or full stop ignored. A name of two
 * words or more passes inside a string, with no letter or digit touching it on either side.
 *
 * @param string          $words   The bold words.
 * @param string[]|string $strings The code's strings (a string is split on line breaks).
 * @return bool
 */
function name_in_code( $words, $strings ) {
	static $cache_key = null, $whole = array(), $blob = '';

	$strings = is_array( $strings ) ? $strings : explode( "\n", (string) $strings );
	$key     = md5( serialize( array( count( $strings ), strlen( implode( '', $strings ) ) ) ) );
	if ( $key !== $cache_key ) {
		$cache_key = $key;
		$whole     = array();
		foreach ( $strings as $str ) {
			foreach ( explode( "\n", $str ) as $line ) {
				$whole[ rtrim( trim( $line ), ':.' ) ] = true;
			}
			$whole[ rtrim( trim( $str ), ':.' ) ] = true;
		}
		$blob = implode( "\n", $strings );
	}

	if ( isset( $whole[ rtrim( trim( $words ), ':.' ) ] ) ) {
		return true;
	}
	if ( count( preg_split( '/\s+/', trim( $words ) ) ) < 2 ) {
		return false;
	}

	return 1 === preg_match( '/(?<![\pL\pN])' . preg_quote( $words, '/' ) . '(?![\pL\pN])/u', $blob );
}

/**
 * Every bold span in a section's Markdown: array( line, words ).
 *
 * @param string $md Markdown.
 * @return array
 */
function bold_spans( $md ) {
	$spans = array();
	foreach ( explode( "\n", $md ) as $i => $line ) {
		if ( preg_match_all( '/\*\*(.+?)\*\*/', $line, $m ) ) {
			foreach ( $m[1] as $words ) {
				$spans[] = array( $i + 1, $words );
			}
		}
	}
	return $spans;
}

/**
 * The problems with a set of sections: one line each.
 *
 * @param array  $sections Slug => Markdown.
 * @param array  $allow    The allow list.
 * @param string[]|string $code The code's strings.
 * @return string[]
 */
function label_problems( array $sections, array $allow, $code ) {
	$problems = array();
	$used     = array();

	foreach ( $sections as $slug => $md ) {
		foreach ( bold_spans( $md ) as $span ) {
			list( $line, $words ) = $span;
			if ( isset( $allow[ $words ] ) ) {
				$used[ $words ] = true;
				$entry          = $allow[ $words ];
				if ( 'placeholder' === ( $entry['why'] ?? '' ) ) {
					$pattern = (string) ( $entry['pattern'] ?? '' );
					// A placeholder that ends the label after a space is a count the screen adds ("Invited (3)"): the
					// guide may name the label without it.
					$quoted  = preg_replace( '/ %(?:\d+\\\$)?s$/', '(?: .+)?', preg_quote( $pattern, '/' ) );
					$regex   = '/^' . preg_replace( array( '/%(?:\d+\\\$)?s/', '/%(?:\d+\\\$)?d/' ), array( '.+', '\d+' ), $quoted ) . '$/u';
					if ( ! preg_match( $regex, $words ) ) {
						$problems[] = "{$slug}:{$line}: **{$words}** does not match its pattern \"{$pattern}\"";
					}
				}
				continue;
			}
			if ( ! name_in_code( $words, $code ) ) {
				$problems[] = "{$slug}:{$line}: **{$words}** is not in the code and not allowed";
			}
		}
	}

	foreach ( $allow as $words => $entry ) {
		$why = $entry['why'] ?? '';
		if ( ! in_array( $why, array( 'wordpress', 'other-service', 'placeholder', 'heading' ), true ) ) {
			$problems[] = "allow entry **{$words}**: unknown reason \"{$why}\"";
		}
		if ( 'placeholder' === $why && ( '' === (string) ( $entry['pattern'] ?? '' ) || false === strpos( is_array( $code ) ? implode( "\n", $code ) : $code, (string) $entry['pattern'] ) ) ) {
			$problems[] = "allow entry **{$words}**: its pattern is not in the code";
		}
		// A heading entry belongs to the guide's fixed build: only the two run-in headings it prints may use that reason.
		if ( 'heading' === $why && ! in_array( $words, array( 'What happens next', 'Good to know' ), true ) ) {
			$problems[] = "allow entry **{$words}**: only \"What happens next\" and \"Good to know\" may be headings";
		}
		// A heading entry stays valid whether or not a section uses it today.
		if ( 'heading' !== $why && ! isset( $used[ $words ] ) ) {
			$problems[] = "allow entry **{$words}**: no covered section uses it (stale)";
		}
	}

	return $problems;
}

// The checker itself, on text made for it.
$fake_code  = "Save changes\nRun check and promote to %s\nSend invitation";
$fake_allow = array(
	'Dashboard'                       => array( 'why' => 'wordpress', 'note' => 'WordPress menu' ),
	'Run check and promote to Active' => array( 'why' => 'placeholder', 'note' => 'status', 'pattern' => 'Run check and promote to %s' ),
	'Good to know'                    => array( 'why' => 'heading', 'note' => 'fixed build' ),
);
ck( 'a name in the code, an allowed name and a filled placeholder pass', label_problems( array( 'x' => "Press **Save changes**, then **Dashboard** and **Run check and promote to Active**.\n**Good to know**" ), $fake_allow, $fake_code ), array() );
ck( 'a bold name found nowhere is named with its line, and unused allow entries are stale', label_problems( array( 'x' => "a\nPress **Send invite**." ), $fake_allow, $fake_code ), array(
	'x:2: **Send invite** is not in the code and not allowed',
	'allow entry **Dashboard**: no covered section uses it (stale)',
	'allow entry **Run check and promote to Active**: no covered section uses it (stale)',
) );
ck( 'a placeholder whose words do not fit, and a pattern not in the code, are named', label_problems(
	array( 'x' => 'Press **Run check and demote to Active**.' ),
	array(
		'Run check and demote to Active' => array( 'why' => 'placeholder', 'note' => 'n', 'pattern' => 'Run check and promote to %s' ),
		'Other %s'                       => array( 'why' => 'placeholder', 'note' => 'n', 'pattern' => 'Not there %s' ),
	),
	$fake_code
), array(
	'x:1: **Run check and demote to Active** does not match its pattern "Run check and promote to %s"',
	'allow entry **Other %s**: its pattern is not in the code',
	'allow entry **Other %s**: no covered section uses it (stale)',
) );


// Word boundaries: a one-word name passes only as a whole string; a longer phrase may sit inside a string.
$wb_code = "Unsaved changes\nSave changes\nSave:\nOpen the Tools screen now";
$wb      = static function ( $words ) use ( $wb_code ) { return label_problems( array( 'x' => "**{$words}**" ), array(), $wb_code ); };
ck( 'a one-word name passes only as a whole string, a trailing colon ignored', array( $wb( 'Save' ), $wb( 'Unsaved' ), $wb( 'Changes' ) ), array( array(), array( 'x:1: **Unsaved** is not in the code and not allowed' ), array( 'x:1: **Changes** is not in the code and not allowed' ) ) );
ck( 'a phrase of two words or more passes inside a string, on whole words', array( $wb( 'Tools screen' ), $wb( 'ools screen' ), $wb( 'Save change' ) ), array( array(), array( 'x:1: **ools screen** is not in the code and not allowed' ), array( 'x:1: **Save change** is not in the code and not allowed' ) ) );

// Only the two seeded phrases may be allowed as headings.
ck( 'a heading entry other than the two seeded phrases fails', label_problems( array( 'x' => 'none' ), array( 'Save' => array( 'why' => 'heading', 'note' => 'n' ), 'Good to know' => array( 'why' => 'heading', 'note' => 'n' ) ), '' ), array( 'allow entry **Save**: only "What happens next" and "Good to know" may be headings' ) );

// A label that exists only in a JS comment does not pass.
$js = "// don't \"Js Comment Label\" isn't\n/* \"Block Comment Label\" */\nvar a = 'Real Label'; var u = 'https://example.test/x';\n";
$read = js_strings( $js );
ck( 'JS comments are not code strings, and a URL in a string survives', array( false !== strpos( $read, 'Js Comment Label' ), false !== strpos( $read, 'Block Comment Label' ), false !== strpos( $read, 'Real Label' ), false !== strpos( $read, 'https://example.test/x' ) ), array( false, false, true, true ) );

// The real sections.
$guides = is_file( __DIR__ . '/docs-guides.php' ) ? require __DIR__ . '/docs-guides.php' : array();
$parts  = $guides['administrators']['parts'] ?? array();
$bad    = array();
$text   = array();
foreach ( $covered as $slug ) {
	$path = $plugin . '/docs/sections/' . $slug . '.md';
	if ( ! is_file( $path ) ) {
		$bad[] = "{$slug}: no such section";
		continue;
	}
	if ( ! in_array( $slug, $parts, true ) ) {
		$bad[] = "{$slug}: not a part of the Administrator guide";
	}
	$text[ $slug ] = (string) file_get_contents( $path );
}
ck( 'every covered section exists and is a part of the Administrator guide', $bad, array() );

$allow_file = __DIR__ . '/fixtures/guide-labels-allow.json';
$allow      = is_file( $allow_file ) ? json_decode( (string) file_get_contents( $allow_file ), true ) : null;
ck( 'the allow file is a JSON object', is_array( $allow ), true );

$problems = label_problems( $text, is_array( $allow ) ? $allow : array(), code_strings( $plugin ) );
ck( 'every bold name in the covered sections is a name in the code or is allowed, and no allowed name is stale', $problems, array() );
foreach ( $problems as $p ) {
	echo "       $p\n";
}

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
