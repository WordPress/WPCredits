<?php
/**
 * Rewrite bin/ so that nothing under it names a real record, a real organization, a real
 * person or a real mail domain.
 *
 * `bin/` never ships in the installable zip, but it IS published on the public GitHub mirror,
 * and the deep check of 7 September 2026 found what that published: the institutions seed
 * fixture carried 300 live Airtable record IDs and 104 real organization names beside the
 * stage the program had filed each of them under - a judgment made in private - and three of
 * those "organizations" were people who had typed their own name into the public application
 * form (FSUIT-5). Four suites carried invented local parts at a partner university's own
 * deliverable mail domain (FSUIT-13). The fixture is the pipeline index the suites are built on, so it
 * cannot simply be deleted; it is made synthetic instead, here, by a script that is kept in
 * the repository so the fixture can be regenerated the same way after every refresh.
 *
 * What it rewrites, in one deterministic mapping across the whole of bin/:
 *
 *   - every real Airtable record ID becomes `recSEED0` and a nine-digit index, in order of
 *     first appearance (the seed fixture is read first, so its rows number 1 upwards). The
 *     separating zero is there because an Airtable record ID is `rec` and fourteen more
 *     characters: `WPCPM_Mentors_Sync::is_record_id()` refuses anything else, and the index
 *     module drops a row whose ID it refuses, so a sixteen-character placeholder would empty
 *     the fixture rather than anonymize it.
 *   - every institution name becomes "Institution <index>", every city "City <index>" and
 *     every website "https://institution-<index>.example/". Indexes are per column and follow
 *     first appearance, so two rows in one city still share a city and a name that appears
 *     twice is still one name. Leading and trailing whitespace is kept: ten names in the base
 *     ended in a space once, and "a trailing space survives the round trip" is a check.
 *   - a name that starts with TEST keeps that prefix ("TEST - Institution <index>"): the
 *     agreement suite finds the program's own TEST record by its label rather than by its ID,
 *     on purpose, so that a seed refreshed from a base without one fails loudly.
 *   - every address whose domain is not a reserved name is retired to `institution.example`,
 *     keeping the local part: `Anna@` and `anna@` must stay one address in two cases for the
 *     import suite's "kept as typed, lowercased for comparing" pair to mean anything. The rule
 *     is "reserved or retired" rather than a list of the domains to avoid, because a list of
 *     real mail domains is itself a thing not to publish on a mirror.
 *   - any value that reads as a phone number is emptied.
 *   - every sample display name, profile address, personal site and real organization the
 *     refused list names becomes an obviously invented one. That list is written by hand,
 *     because no rule can tell an invented name from a real one; it is the record of which
 *     names were judged real, and it lives in bin/refused-strings.php as hashes rather than
 *     as the strings, so that the list itself is not the publication it exists to prevent.
 *
 * The name column is anonymized the same way for every row, rather than marking the three
 * rows that are people as "Person <index>": telling the reader of a public mirror which rows
 * are individuals, beside the stage they were filed under, is the exposure being closed.
 *
 * A hand-written placeholder is left alone. Every ID this repository invents is upper case
 * (`recSTU00000000001`, `recTEAM0000000001`, `recAAAAAAAAAAAAAA`), and the three mixed-case
 * ones read as words plus a run of digits, so the test below asks for a lower-case letter and
 * no run of four identical characters or five digits. Anything found in the seed itself is
 * treated as real whatever its shape, because the seed is a dump of the live base.
 *
 * What it does NOT do: it never guesses at a person's name in a suite fixture - it replaces
 * only what the refused list holds, which somebody read and judged - and it does not sweep city
 * names through the suites (one city in the base is called "Test", and the word is everywhere).
 * Cities are reported instead, at the end of the run, for a person to judge.
 *
 * Run from the plugin root, after every refresh of the seed and before committing it:
 *
 *   ssh wpcredits-dashboard 'wp eval-file -' < bin/dump-institutions-seed.php \
 *     > bin/fixtures/institutions-index-seed.json   (the raw dump, from the site)
 *   php bin/anonymize-fixtures.php                  (rewrites it, and every suite quoting it)
 *   php bin/anonymize-fixtures.php --dry-run        (says what would change, writes nothing)
 *   php bin/anonymize-fixtures.php --hash 'A Name'  (prints one hash for the refused list, so
 *                                                    a name is never typed into a tracked file)
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

// The refused list, and the tokenizer that finds its entries in a body of text. Shared with
// bin/test-fixtures.php so that the sweep and the walk that checks the sweep cut a file into
// candidates exactly the same way; a second copy of that rule would let the two drift apart and
// the walk would then be green on a file the sweep cannot reach.
require_once __DIR__ . '/refused-strings.php';

$wpcpm_root = dirname( __DIR__ );
$wpcpm_dry  = in_array( '--dry-run', (array) $argv, true );
$wpcpm_seed = $wpcpm_root . '/bin/fixtures/institutions-index-seed.json';

// `--hash 'A Name'` prints the one line to paste into bin/refused-strings.php. It exists so
// that adding a name to the list never means typing that name into a tracked file: the string
// lives in the shell for one command and the repository only ever sees its hash.
$wpcpm_hash_at = array_search( '--hash', (array) $argv, true );

if ( false !== $wpcpm_hash_at ) {
	if ( ! isset( $argv[ $wpcpm_hash_at + 1 ] ) || '' === $argv[ $wpcpm_hash_at + 1 ] ) {
		fwrite( STDERR, "--hash needs the string to hash: php bin/anonymize-fixtures.php --hash 'A Name'\n" );
		exit( 1 );
	}

	printf( "\t'%s' => 'A Placeholder',\n", sha1( $argv[ $wpcpm_hash_at + 1 ] ) );
	exit( 0 );
}

/**
 * The address domains left exactly as they are, lower-cased, and why.
 *
 * The reserved names RFC 2606 sets aside for documentation (`.example`, `.test`, `.invalid`
 * and the four example.* domains) are what the suites already use for everything invented;
 * `a8c.com` is the program's own address, which the plan allows anywhere; `li.org` is GNU
 * gettext's own Language-Team placeholder, which bin/make-pot.sh writes into the POT header
 * and no tool would recognize under another name. Every other domain found after an `@` is
 * retired.
 */
const WPCPM_KEEP_DOMAINS = '/(^|\.)(example|test|invalid)$|^example\.(com|org|net|edu)$|^a8c\.com$|^li\.org$/';

/**
 * The shortest name or website the sweep will replace through the suites.
 *
 * Some names in the base are only a few characters long, and some of those are acronyms or
 * ordinary words: replacing a string that short everywhere under bin/ would rewrite text that
 * has nothing to do with an institution. Anything shorter than this is replaced in the fixture
 * and then listed at the end of the run, for a person to judge, rather than swept.
 *
 * The names themselves are deliberately not quoted here. This file is the one whose whole
 * purpose is that nothing under bin/ names a real organization, and a docblock is under bin/
 * like everything else.
 */
const WPCPM_MIN_SWEEP = 10;

/**
 * Whether a record ID is one this repository invented rather than one Airtable issued.
 *
 * @param string $id A `rec` and fourteen characters.
 * @return bool
 */
function wpcpm_is_placeholder_id( $id ) {
	$body = substr( $id, 3 );

	// This script's own output.
	if ( 1 === preg_match( '/^SEED0[0-9]{9}$/', $body ) ) {
		return true;
	}

	// Every placeholder written by hand in bin/ is upper case.
	if ( 1 !== preg_match( '/[a-z]/', $body ) ) {
		return true;
	}

	// And the few mixed-case ones read as a word plus a run: recStudent0000001, recMiXeDcAsE12345.
	return 1 === preg_match( '/(.)\1{3}/', $body ) || 1 === preg_match( '/[0-9]{5}/', $body );
}

/**
 * Whether a value reads as a phone number rather than as prose.
 *
 * The application form is public and its City field is free text, so it collects whatever the
 * applicant typed: one row of the base holds an eighteen-digit account number there.
 *
 * @param string $value The value as the base holds it.
 * @return bool
 */
function wpcpm_looks_like_phone( $value ) {
	$digits = preg_replace( '/[^0-9]/', '', (string) $value );

	return strlen( $digits ) >= 7 && 1 === preg_match( '/^[0-9 ()+.\/-]+$/', (string) $value );
}

/**
 * Every file under bin/, in path order, so the mapping is the same on every run.
 *
 * @param string $dir Directory to walk.
 * @return string[] Absolute paths.
 */
function wpcpm_files_under( $dir ) {
	$files = array();
	$walk  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $walk as $file ) {
		if ( $file->isFile() && '.DS_Store' !== $file->getBasename() ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files, SORT_STRING );

	return $files;
}

if ( ! is_file( $wpcpm_seed ) ) {
	fwrite( STDERR, "The seed fixture is missing: bin/fixtures/institutions-index-seed.json\n" );
	exit( 1 );
}

$wpcpm_data = json_decode( (string) file_get_contents( $wpcpm_seed ), true );

if ( ! is_array( $wpcpm_data ) || ! isset( $wpcpm_data['institutions'] ) ) {
	fwrite( STDERR, "The seed fixture is not the JSON this script knows how to read.\n" );
	exit( 1 );
}

$wpcpm_files = wpcpm_files_under( $wpcpm_root . '/bin' );
$wpcpm_self  = $wpcpm_root . '/bin/anonymize-fixtures.php';

/* ---- the record ID mapping, in order of first appearance ------------------ */

$wpcpm_ids  = array();
$wpcpm_seen = array();

// The seed first, whatever the path order, because its rows are the numbers a reader follows.
foreach ( array_merge( array( $wpcpm_seed ), $wpcpm_files ) as $wpcpm_file ) {
	if ( $wpcpm_self === $wpcpm_file || isset( $wpcpm_seen[ $wpcpm_file ] ) ) {
		continue;
	}

	$wpcpm_seen[ $wpcpm_file ] = true;
	$wpcpm_body                = (string) file_get_contents( $wpcpm_file );

	if ( ! preg_match_all( '/\brec[A-Za-z0-9]{14}\b/', $wpcpm_body, $wpcpm_found ) ) {
		continue;
	}

	// Everything in the seed came from the base, so its shape is not asked about.
	$wpcpm_from_seed = ( $wpcpm_seed === $wpcpm_file );

	foreach ( $wpcpm_found[0] as $wpcpm_id ) {
		if ( isset( $wpcpm_ids[ $wpcpm_id ] ) ) {
			continue;
		}
		if ( 1 === preg_match( '/^recSEED0[0-9]{9}$/', $wpcpm_id ) ) {
			continue;
		}
		if ( ! $wpcpm_from_seed && wpcpm_is_placeholder_id( $wpcpm_id ) ) {
			continue;
		}

		$wpcpm_ids[ $wpcpm_id ] = sprintf( 'recSEED0%09d', count( $wpcpm_ids ) + 1 );
	}
}

/* ---- the column mappings, from the seed's own rows ----------------------- */

$wpcpm_names  = array();
$wpcpm_cities = array();
$wpcpm_sites  = array();

// The index counts the placeholders issued, not the values seen, so that a value emptied for
// reading as a phone number does not consume a number: the next run over the rewritten file
// has to arrive at the same names, or every run would renumber the rows below the emptied one.
$wpcpm_next = array( 'name' => 0, 'city' => 0, 'site' => 0 );

foreach ( $wpcpm_data['institutions'] as $wpcpm_row ) {
	$wpcpm_name = trim( (string) ( isset( $wpcpm_row['name'] ) ? $wpcpm_row['name'] : '' ) );
	$wpcpm_city = trim( (string) ( isset( $wpcpm_row['city'] ) ? $wpcpm_row['city'] : '' ) );
	$wpcpm_site = trim( (string) ( isset( $wpcpm_row['website'] ) ? $wpcpm_row['website'] : '' ) );

	if ( '' !== $wpcpm_name && ! isset( $wpcpm_names[ $wpcpm_name ] ) ) {
		$wpcpm_names[ $wpcpm_name ] = ( 0 === strpos( $wpcpm_name, 'TEST' ) ? 'TEST - ' : '' ) . 'Institution ' . ++$wpcpm_next['name'];
	}
	if ( '' !== $wpcpm_city && ! isset( $wpcpm_cities[ $wpcpm_city ] ) ) {
		$wpcpm_cities[ $wpcpm_city ] = wpcpm_looks_like_phone( $wpcpm_city ) ? '' : 'City ' . ++$wpcpm_next['city'];
	}
	if ( '' !== $wpcpm_site && ! isset( $wpcpm_sites[ $wpcpm_site ] ) ) {
		$wpcpm_sites[ $wpcpm_site ] = 'https://institution-' . ++$wpcpm_next['site'] . '.example/';
	}
}

/* ---- the fixture, rewritten by column ------------------------------------ */

$wpcpm_cells = array( 'name' => 0, 'city' => 0, 'website' => 0 );

$wpcpm_data['_comment'] = 'A synthetic fixture. The Institutions table (' . $wpcpm_data['institutions_table'] . ') and the Countries table (' . $wpcpm_data['countries_table'] . ') as read on ' . $wpcpm_data['read'] . ', with every personal field left out at the dump (bin/dump-institutions-seed.php) and every remaining real value replaced by bin/anonymize-fixtures.php: record IDs are recSEED0 and an index, institutions are "Institution N", cities "City N" and websites institution-N.example. It holds no real record: no organization, person, address or site here names anybody, and the pipeline stages, dates and counts are what the suites measure. Country names are the world\'s own. Refresh with bin/dump-institutions-seed.php and run bin/anonymize-fixtures.php before committing, then update the counts and the values bin/test-institutions-index.php pins.';

foreach ( $wpcpm_data['institutions'] as $wpcpm_i => $wpcpm_row ) {
	foreach ( array( 'name' => $wpcpm_names, 'city' => $wpcpm_cities, 'website' => $wpcpm_sites ) as $wpcpm_key => $wpcpm_map ) {
		$wpcpm_value = (string) ( isset( $wpcpm_row[ $wpcpm_key ] ) ? $wpcpm_row[ $wpcpm_key ] : '' );
		$wpcpm_core  = trim( $wpcpm_value );

		if ( '' === $wpcpm_core || ! isset( $wpcpm_map[ $wpcpm_core ] ) ) {
			continue;
		}

		// The space a program manager left on the end of a name is data: a check counts them.
		$wpcpm_lead  = substr( $wpcpm_value, 0, strpos( $wpcpm_value, $wpcpm_core ) );
		$wpcpm_trail = substr( $wpcpm_value, strpos( $wpcpm_value, $wpcpm_core ) + strlen( $wpcpm_core ) );

		$wpcpm_data['institutions'][ $wpcpm_i ][ $wpcpm_key ] = '' === $wpcpm_map[ $wpcpm_core ] ? '' : $wpcpm_lead . $wpcpm_map[ $wpcpm_core ] . $wpcpm_trail;

		if ( $wpcpm_data['institutions'][ $wpcpm_i ][ $wpcpm_key ] !== $wpcpm_value ) {
			++$wpcpm_cells[ $wpcpm_key ];
		}
	}
}

$wpcpm_json = json_encode( $wpcpm_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";

/* ---- the sweep, over the fixture and every suite -------------------------- */

$wpcpm_sweep = $wpcpm_ids;
$wpcpm_short = array();

foreach ( $wpcpm_names as $wpcpm_from => $wpcpm_to ) {
	if ( $wpcpm_from === $wpcpm_to ) {
		continue;
	}
	if ( strlen( $wpcpm_from ) >= WPCPM_MIN_SWEEP ) {
		$wpcpm_sweep[ $wpcpm_from ] = $wpcpm_to;
	} else {
		$wpcpm_short[] = $wpcpm_from;
	}
}

// A website is swept only when the base holds it as a full URL. Half of them are bare domains,
// and a bare domain is also what sits after the @ of an address: sweeping a university's bare
// domain through the suites rewrote the middle of ten invented addresses before this rule
// existed, which is why the loop below retires an address before it sweeps a website.
foreach ( $wpcpm_sites as $wpcpm_from => $wpcpm_to ) {
	if ( $wpcpm_from === $wpcpm_to ) {
		continue;
	}
	if ( strlen( $wpcpm_from ) >= WPCPM_MIN_SWEEP && false !== strpos( $wpcpm_from, '://' ) ) {
		$wpcpm_sweep[ $wpcpm_from ] = $wpcpm_to;
	} else {
		$wpcpm_short[] = $wpcpm_from;
	}
}

// The hand-written list is NOT merged here. Its entries are held as hashes, so what it refuses
// cannot be listed in advance: each file is cut into candidates, the candidates are hashed, and
// what comes back is the text that file carries. The merge happens per file, below.

// Longest first, so a value that contains another is not half-replaced.
$wpcpm_longest_first = static function ( $a, $b ) {
	return strlen( $b ) - strlen( $a );
};

uksort( $wpcpm_sweep, $wpcpm_longest_first );

$wpcpm_counts  = array( 'ids' => 0, 'names' => 0, 'sites' => 0, 'domains' => 0, 'people' => 0 );
$wpcpm_domains = array();
$wpcpm_bodies  = array();
$wpcpm_changed = array();
$wpcpm_kinds   = array();

foreach ( $wpcpm_names as $wpcpm_from => $wpcpm_to ) {
	$wpcpm_kinds[ $wpcpm_from ] = 'names';
}
foreach ( $wpcpm_sites as $wpcpm_from => $wpcpm_to ) {
	$wpcpm_kinds[ $wpcpm_from ] = 'sites';
}
foreach ( $wpcpm_ids as $wpcpm_from => $wpcpm_to ) {
	$wpcpm_kinds[ $wpcpm_from ] = 'ids';
}

foreach ( $wpcpm_files as $wpcpm_file ) {
	if ( $wpcpm_self === $wpcpm_file ) {
		continue;
	}

	$wpcpm_before = (string) file_get_contents( $wpcpm_file );
	$wpcpm_after  = ( $wpcpm_seed === $wpcpm_file ) ? $wpcpm_json : $wpcpm_before;

	// The fixture's own columns were rewritten above; only its record IDs are counted here.
	$wpcpm_is_seed = ( $wpcpm_seed === $wpcpm_file );

	// Addresses first: a domain that is also a website value would otherwise be swept below as
	// a website and leave "anna@https://institution-9.example/" behind, which it did once. The
	// local part may be empty, because a suite builds an address by concatenation and leaves
	// the domain sitting on its own after the @.
	$wpcpm_after = preg_replace_callback(
		'/([A-Za-z0-9._%+-]*)@([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,})/',
		static function ( $wpcpm_match ) use ( &$wpcpm_domains, &$wpcpm_counts ) {
			$wpcpm_domain = strtolower( $wpcpm_match[2] );

			if ( 1 === preg_match( WPCPM_KEEP_DOMAINS, $wpcpm_domain ) ) {
				return $wpcpm_match[0];
			}

			if ( ! isset( $wpcpm_domains[ $wpcpm_domain ] ) ) {
				$wpcpm_domains[ $wpcpm_domain ] = $wpcpm_domains ? 'institution-' . ( count( $wpcpm_domains ) + 1 ) . '.example' : 'institution.example';
			}

			++$wpcpm_counts['domains'];

			return $wpcpm_match[1] . '@' . $wpcpm_domains[ $wpcpm_domain ];
		},
		$wpcpm_after
	);

	// What the refused list holds, as this file spells it. The list is hashes, so this is the
	// only way to learn which of its entries are here - and what comes back is the file's own
	// text, which is what makes it replaceable. Merged into a copy of the sweep so that the
	// longest-first order covers the refused strings and the mapped ones together: a full name
	// has to go before the bare first name inside it, whichever list each came from.
	$wpcpm_file_sweep = $wpcpm_sweep;

	foreach ( wpcpm_refused_found( $wpcpm_after ) as $wpcpm_from => $wpcpm_to ) {
		$wpcpm_file_sweep[ $wpcpm_from ] = $wpcpm_to;
		$wpcpm_kinds[ $wpcpm_from ]      = 'people';
	}

	uksort( $wpcpm_file_sweep, $wpcpm_longest_first );

	foreach ( $wpcpm_file_sweep as $wpcpm_from => $wpcpm_to ) {
		$wpcpm_hits  = 0;
		$wpcpm_after = str_replace( (string) $wpcpm_from, $wpcpm_to, $wpcpm_after, $wpcpm_hits );

		if ( ! $wpcpm_is_seed || 'ids' === $wpcpm_kinds[ $wpcpm_from ] ) {
			$wpcpm_counts[ $wpcpm_kinds[ $wpcpm_from ] ] += $wpcpm_hits;
		}
	}

	// Kept so the two reports below read the rewritten text, in a dry run as well.
	$wpcpm_bodies[ $wpcpm_file ] = $wpcpm_after;

	if ( $wpcpm_after === $wpcpm_before ) {
		continue;
	}

	$wpcpm_changed[] = substr( $wpcpm_file, strlen( $wpcpm_root ) + 1 );

	if ( ! $wpcpm_dry ) {
		file_put_contents( $wpcpm_file, $wpcpm_after );
	}
}

/* ---- what changed, and what a person still has to look at ----------------- */

printf(
	"%s\n  %d record IDs mapped, replaced %d times\n  %d institution names mapped, %d cells in the fixture and %d replacements elsewhere\n  %d cities mapped, %d cells in the fixture\n  %d websites mapped, %d cells in the fixture and %d replacements elsewhere\n  %d mail domains retired, %d replacements\n  %d refused names and organizations, %d replacements\n  %d files rewritten\n",
	$wpcpm_dry ? 'Would rewrite (--dry-run):' : 'Rewritten:',
	count( $wpcpm_ids ),
	$wpcpm_counts['ids'],
	count( $wpcpm_names ),
	$wpcpm_cells['name'],
	$wpcpm_counts['names'],
	count( $wpcpm_cities ),
	$wpcpm_cells['city'],
	count( $wpcpm_sites ),
	$wpcpm_cells['website'],
	$wpcpm_counts['sites'],
	count( $wpcpm_domains ),
	$wpcpm_counts['domains'],
	count( wpcpm_refused_all() ),
	$wpcpm_counts['people'],
	count( $wpcpm_changed )
);

foreach ( $wpcpm_changed as $wpcpm_file ) {
	echo '    ' . $wpcpm_file . "\n";
}

// Cities are not swept: one of them is called "Test". Any suite that quotes one is named here
// so the assertion can be moved to the new value by hand.
$wpcpm_quoted = array();

foreach ( $wpcpm_files as $wpcpm_file ) {
	if ( $wpcpm_self === $wpcpm_file || $wpcpm_seed === $wpcpm_file || 'php' !== pathinfo( $wpcpm_file, PATHINFO_EXTENSION ) ) {
		continue;
	}

	$wpcpm_body = $wpcpm_bodies[ $wpcpm_file ];

	foreach ( array_keys( $wpcpm_cities ) as $wpcpm_city ) {
		if ( false !== strpos( $wpcpm_body, "'" . $wpcpm_city . "'" ) || false !== strpos( $wpcpm_body, '"' . $wpcpm_city . '"' ) ) {
			$wpcpm_quoted[] = substr( $wpcpm_file, strlen( $wpcpm_root ) + 1 ) . '  ' . $wpcpm_city;
		}
	}
}

if ( $wpcpm_quoted ) {
	echo "\nA suite quotes a city the seed carries. Cities are never swept (one is called \"Test\"),\nso check each one and move the assertion by hand if it means the seed's row:\n  " . implode( "\n  ", $wpcpm_quoted ) . "\n";
}

if ( $wpcpm_short ) {
	echo "\nToo short to sweep safely (under " . WPCPM_MIN_SWEEP . " characters), replaced in the fixture only:\n  " . implode( ', ', $wpcpm_short ) . "\n";
}

// The safety net: any address the rewrite above left behind, which would mean the pattern that
// finds an address and the pattern that keeps a domain disagree.
$wpcpm_addresses = array();

foreach ( $wpcpm_files as $wpcpm_file ) {
	if ( $wpcpm_self === $wpcpm_file ) {
		continue;
	}

	if ( preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $wpcpm_bodies[ $wpcpm_file ], $wpcpm_found ) ) {
		foreach ( $wpcpm_found[0] as $wpcpm_address ) {
			$wpcpm_domain = strtolower( substr( $wpcpm_address, strpos( $wpcpm_address, '@' ) + 1 ) );

			if ( 1 === preg_match( WPCPM_KEEP_DOMAINS, $wpcpm_domain ) ) {
				continue;
			}

			$wpcpm_addresses[ $wpcpm_address ] = true;
		}
	}
}

if ( $wpcpm_addresses ) {
	echo "\nAddresses left under bin/ outside the reserved names and the program's own:\n  " . implode( "\n  ", array_keys( $wpcpm_addresses ) ) . "\n";
}

exit( 0 );
