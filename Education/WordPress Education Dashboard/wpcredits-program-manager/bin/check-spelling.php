<?php
/**
 * US English in everything a person reads: interface strings, the blocks, the guides and the
 * readme.
 *
 * The WordPress writing style guide (make.wordpress.org/marketing/handbook) asks for US
 * spelling per Merriam-Webster. Only text people read is checked: the arguments of the
 * translation functions, the title, description and keywords of each block, the guide sections
 * and the readme. A status key, an RFC 5545 value ("STATUS:CANCELLED"), a CSV column alias or a
 * page title the site once used are data, not prose, and are left exactly as the outside world
 * spells them. Comments and identifiers are the developer's.
 *
 * The block pass exists because block.json is where the inserter reads a block's title and
 * description from, and WordPress passes both through translation: a block called "Sponsor
 * Application Programme" reached the inserter with nothing here to stop it (deep check finding
 * FSUIT-12).
 *
 * The last pass reads bin/, which never ships in the zip but IS published on the public GitHub
 * mirror. Not the whole of it: the suites quote the outside world's own spellings, an RFC 5545
 * "CANCELLED" and a form field named "Programme" among them, and rewriting those would be
 * wrong. What is read is the prose that explains the fixtures - the comment each fixture
 * carries, and the three scripts that write, refresh and verify them - because that prose is
 * this repository's own, and one of the three writes its text into committed data. Fifteen
 * British spellings, one of them in that committed comment, survived until this pass existed.
 *
 * Run from the plugin root: php bin/check-spelling.php
 *
 * An alternative root may be given as the one argument, which is how bin/test-tooling.php
 * checks this check against a scratch tree rather than by committing a misspelling to prove
 * it. A root that holds no readme.txt is refused rather than read as an empty tree: a gate
 * that goes green over nothing is not a gate.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root  = ( isset( $argv[1] ) && '' !== $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__ );

// Every plugin root has a readme.txt, so a root without one is a mistyped path or an argument
// meant as a flag. Both used to read as a tree holding no files at all, and this check
// announced US English throughout without opening a single one.
if ( ! is_dir( $root ) || ! is_file( $root . '/readme.txt' ) ) {
	fwrite( STDERR, 'Not a plugin tree: ' . $root . " holds no readme.txt.\nGive the plugin root, or no argument at all.\n" );
	exit( 1 );
}

$words = array(
	'programme', 'programmes', 'colour', 'colours', 'coloured', 'behaviour', 'behaviours', 'enrolment', 'enrolments',
	'enrol', 'enrols', 'cancelled', 'cancelling', 'afterwards', 'organise', 'organised', 'organising', 'organisation',
	'organisations', 'recognise', 'recognised', 'recognising', 'licence', 'licences', 'centre', 'centres', 'favourite',
	'favourites', 'catalogue', 'labelled', 'labelling', 'whilst', 'amongst', 'analyse', 'analysed', 'analysing',
	'customise', 'customised', 'summarise', 'summarised', 'authorise', 'authorised', 'prioritise', 'optimise',
	'finalise', 'realise', 'realised', 'practise', 'practised', 'learnt', 'honour', 'neighbour', 'judgement', 'fulfil',
	'modelled', 'travelled', 'grey',
);
$i18n  = array( '__', '_e', '_x', '_ex', '_n', '_nx', 'esc_html__', 'esc_html_e', 'esc_html_x', 'esc_attr__', 'esc_attr_e', 'esc_attr_x', '_n_noop', '_nx_noop' );
$re    = '/\b(' . implode( '|', $words ) . ')\b/i';
$hits  = array();
$files = array_merge( glob( $root . '/includes/*.php' ), glob( $root . '/includes/*/*.php' ), array( $root . '/wpcredits-program-manager.php' ) );
$files = array_values( array_filter( $files, 'is_file' ) );

/**
 * The line a word sits on, so a hit in a JSON file reads like every other hit.
 *
 * @param string $raw    The file as it is on disk.
 * @param string $needle The word found in one of its values.
 * @return int 1-based line number, 1 when the word cannot be placed.
 */
function wpcpm_line_of( $raw, $needle ) {
	$at = stripos( $raw, $needle );

	return false === $at ? 1 : substr_count( substr( $raw, 0, $at ), "\n" ) + 1;
}

foreach ( $files as $file ) {
	$tokens = token_get_all( (string) file_get_contents( $file ) );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], $i18n, true ) ) {
			continue;
		}

		// The call's arguments, to the parenthesis that closes it.
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			++$j;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		$depth = 0;
		for ( ; $j < $count; $j++ ) {
			$t = $tokens[ $j ];
			if ( '(' === $t ) {
				++$depth;
			} elseif ( ')' === $t ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			} elseif ( is_array( $t ) && in_array( $t[0], array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) && preg_match_all( $re, $t[1], $m ) ) {
				foreach ( $m[1] as $word ) {
					$hits[] = sprintf( '%s:%d  %s', substr( $file, strlen( $root ) + 1 ), $t[2], $word );
				}
			}
		}
	}
}

// The three strings block.json shows a person: the inserter's title and description, and the
// keywords it searches. Read from the decoded JSON rather than by line, so a value spread over
// several lines is still one string.
$blocks = glob( $root . '/blocks/*/block.json' );

foreach ( $blocks as $file ) {
	$raw   = (string) file_get_contents( $file );
	$block = json_decode( $raw, true );

	if ( ! is_array( $block ) ) {
		$hits[] = sprintf( '%s:1  not valid JSON', substr( $file, strlen( $root ) + 1 ) );
		continue;
	}

	$reads = array();

	foreach ( array( 'title', 'description' ) as $key ) {
		if ( isset( $block[ $key ] ) ) {
			$reads[] = (string) $block[ $key ];
		}
	}

	foreach ( isset( $block['keywords'] ) ? (array) $block['keywords'] : array() as $keyword ) {
		$reads[] = (string) $keyword;
	}

	foreach ( $reads as $text ) {
		if ( preg_match_all( $re, $text, $m ) ) {
			foreach ( $m[1] as $word ) {
				$hits[] = sprintf( '%s:%d  %s', substr( $file, strlen( $root ) + 1 ), wpcpm_line_of( $raw, $word ), $word );
			}
		}
	}
}

foreach ( array_filter( array_merge( glob( $root . '/docs/sections/*.md' ), array( $root . '/readme.txt' ) ), 'is_file' ) as $file ) {
	foreach ( file( $file ) as $n => $line ) {
		// A word in double quotes is being talked about ("Programme" is "Program"), not used.
		$line = preg_replace( '/"[^"]*"/', '""', $line );

		if ( preg_match_all( $re, $line, $m ) ) {
			foreach ( $m[1] as $word ) {
				$hits[] = sprintf( '%s:%d  %s', substr( $file, strlen( $root ) + 1 ), $n + 1, $word );
			}
		}
	}
}

// The prose bin/ publishes about the fixtures. A JSON fixture is read through its `_comment`
// alone, because the rest of the file is Airtable's own field names and choices; the scripts
// are read line by line, comments and assertion labels together, because all of it is ours.
// bin/refused-strings.php is in the list for the same reason: it is nothing but a hand-written
// list and the prose explaining it.
$prose = array_values(
	array_filter(
		array_merge(
			array(
				$root . '/bin/anonymize-fixtures.php',
				$root . '/bin/dump-institutions-seed.php',
				$root . '/bin/refused-strings.php',
				$root . '/bin/test-fixtures.php',
			),
			(array) glob( $root . '/bin/fixtures/*.json' )
		),
		'is_file'
	)
);

foreach ( $prose as $file ) {
	$raw = (string) file_get_contents( $file );

	if ( 'json' === pathinfo( $file, PATHINFO_EXTENSION ) ) {
		$decoded = json_decode( $raw, true );
		$comment = is_array( $decoded ) && isset( $decoded['_comment'] ) ? (string) $decoded['_comment'] : '';

		if ( '' !== $comment && preg_match_all( $re, $comment, $m ) ) {
			foreach ( $m[1] as $word ) {
				$hits[] = sprintf( '%s:%d  %s', substr( $file, strlen( $root ) + 1 ), wpcpm_line_of( $raw, $word ), $word );
			}
		}

		continue;
	}

	foreach ( file( $file ) as $n => $line ) {
		if ( preg_match_all( $re, $line, $m ) ) {
			foreach ( $m[1] as $word ) {
				$hits[] = sprintf( '%s:%d  %s', substr( $file, strlen( $root ) + 1 ), $n + 1, $word );
			}
		}
	}
}

if ( $hits ) {
	echo "British spellings in text people read (the WordPress style guide is US English):\n  " . implode( "\n  ", $hits ) . "\n";
	exit( 1 );
}

printf( "%d PHP files, %d blocks, %d fixture and tooling files, the guides and the readme: US English throughout.\n", count( $files ), count( $blocks ), count( $prose ) );
exit( 0 );
