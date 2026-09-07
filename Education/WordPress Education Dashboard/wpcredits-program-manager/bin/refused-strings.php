<?php
/**
 * The strings no file under bin/ or docs/ may carry, held as hashes rather than as themselves.
 *
 * `bin/` and `docs/` never ship in the installable zip, but they ARE published on the public
 * GitHub mirror. The deep check of 7 September 2026 found real people used as sample display
 * names in the suites, three of them beside a WordPress.org profile address or a personal
 * domain, and real organizations named beside participation numbers (findings FSUIT-5 and
 * FSUIT-13). bin/anonymize-fixtures.php replaces them; bin/test-fixtures.php refuses them.
 *
 * **Why hashes.** The first version of this list wrote the strings out, because a sweep has to
 * know what to look for. That put eight real names, three profile addresses and one personal
 * domain into the one file whose whole purpose is that nothing under bin/ names a real person -
 * gathered, titled and cross-referenced, which is a worse publication than the scattered
 * fixtures it was closing. A hash is enough: the sweep cuts every file into candidate strings,
 * hashes each one and compares. A match hands back the candidate's own text, so the replacement
 * still works, and the walk can say which file is at fault without ever printing the value.
 *
 * **Adding one.** Never type the string into this file. Print its hash instead and paste that:
 *
 *   php bin/anonymize-fixtures.php --hash 'The Name'
 *
 * The values below - the placeholders - are invented and stay in plain text: that is what a
 * reader of the mirror is meant to see.
 *
 * **What a hash cannot do.** It cannot be read back, so nobody can audit this list by looking at
 * it, and a string that was judged real once cannot be un-judged without the original. That is
 * the trade, and it is the right way round: the list exists to keep values out of the mirror.
 *
 * "Maciej (Matt) Pilarski" is deliberately absent, and this is where that is recorded: it is the
 * maintainer's own name, like maciej@a8c.com, which the plan allows anywhere under bin/. The
 * suites carry it because the program contact block prints a real program manager. It is nobody
 * else's name to publish, so it stays.
 *
 * @package WPCreditsProgramManager
 */

/**
 * The sample display names, handles, profile addresses and personal sites the sweep refuses.
 *
 * Nothing can tell an invented name from a real one, so the sweep never guesses: this list is
 * written by hand and is the record of the decision. Every entry was used in a suite as a
 * display name that reads as a real person, or is the handle, WordPress.org profile address,
 * personal site or mail local part printed beside one - and a profile address identifies
 * somebody on its own, whatever the name next to it says.
 *
 * Length does not gate this list the way WPCPM_MIN_SWEEP gates the institution names. These are
 * exact strings somebody read one at a time, which is what lets a bare first name be swept while
 * an institution of the same length is not. The sweep runs longest first, so a full name goes
 * before the bare first name inside it and a profile address before the handle at the end of it.
 *
 * Keys are `sha1()` of the refused string; values are the placeholder that replaces it. Every
 * placeholder ends in, or reads as, "Example", which no reader can mistake for a person.
 */
const WPCPM_SAMPLE_NAMES = array(
	'e53d479e08dbd4c29c7c32ca9fbffe8b32b19b65' => 'https://profiles.wordpress.org/rio-example/',
	'1128dc95d0fe8181002e0d1b351a97e2657c4c39' => 'https://profiles.wordpress.org/ines-example/',
	'20061bc542533b10f42b5c9f878041c7b24c226c' => 'https://profiles.wordpress.org/sam-example/',
	'942759d779a0fa79bccfc3fb80d81cfc8f88c516' => 'https://rio-example.example',
	'4270eb3213bd460bfa00e3b4f89f815b39f2d3d8' => 'Ada Example',
	'373641fe82842b9cbb6d102ef37775fe6f9e0319' => 'Rey Example',
	'ad35930a44c1e05e392e7219fe44a4fb81ad2a19' => 'Lu Example',
	'e9d783c71dd33de6ceed1cce443bb14f8bcc3dc1' => 'Ines Example',
	'32cf22f4719aa51be7c0910f16ed2147a402ca1e' => 'Mira Example',
	'8bd9a186f48130446895c5b8bcbe1a45a4e125e9' => 'ada@example.test',
	'54080faf38dc44e496759d3a9f4d305dce337153' => 'a program participant',
	'215d3eaeef63cd32229d911251cd22594cd33784' => 'Sam Example',
	'5b5e2e85654b4788998bf1cb49ed38f7c997a8fa' => 'rio-example',
	'6521fc25e7624eef7c3a0d89b71de7e612d27d37' => 'ines-example',
	'c1a4d20e6b54246e0b859530f33385794b7f87a0' => 'lu',
	'94c816f71ea0451f2d20cbaa75e5da221cc24217' => 'Lu',
	'61632e2a55f7e115822cc70c24cf22542a4720b5' => 'Ines',
	'6017679831a845f1a61c411c913b84ddeef26d15' => 'Sam',
	'f772702fbc277bb993db5a28eb33d75eb1c217c3' => 'Ada',
);

/**
 * The real organizations, and their domains, the sweep refuses.
 *
 * Separate from the names above because the judgment is a different one. An organization's name
 * is a public fact and a neutral fixture that quotes one gives nothing away on its own; what the
 * deep check found is that the same names sit in the specs beside how many students each sent
 * and how those students finished, which is the program's private record of a partner (the Task
 * 8 review, ruling (b)). Rather than decide case by case which quotation is neutral, every
 * organization the program has a relationship with - the partner institutions, and the sponsors,
 * whose fixtures carried the pipeline stage each had been filed under - is refused under bin/ and
 * docs/ and replaced with an invented one. WordPress, the Foundation, Airtable, Google and the
 * program's own site are not in this list and are not meant to be: they are the ecosystem and the
 * tooling, named the way any file names them, and none of them is a record in the base.
 *
 * Several entries are a single word of a longer name - a partner's distinctive word used as a
 * fixture surname, the adjective in a university's name, the short form a suite made a variable
 * and a domain label out of - because that is the form the suites carry them in, and the walk
 * cuts a word at its punctuation so a word inside an address or a host is found. Two more are
 * mail or web hosts, whole.
 *
 * `Uniwersytet Łexample` keeps an uppercase non-ASCII letter on purpose: two suites turn on the
 * fact that PHP's `strtolower()` leaves `Ł` alone while Airtable's `LOWER()` does not, so a
 * placeholder spelled in ASCII would quietly retire the check with it.
 *
 * Keys are `sha1()` of the refused string; values are the placeholder that replaces it.
 */
const WPCPM_SAMPLE_ORGS = array(
	'ce1c6c1e0b43bba3019a92c9b2202853895dcf8e' => 'Example University of Economics',
	'bfa1d6dc68c51db32ae265d1521de9b4263d1f96' => 'Politechnika Example',
	'c9eb71b7c17e1d58cf76dd4d8d652e3a89a87a24' => 'Universidad Example',
	'a7653268a338bb378ae33ea3bc811031780b28e1' => 'Green Example University',
	'e1d848eb42f0f3d67960798de7572e0d58f77524' => 'Uniwersytet Łexample',
	'b340eb1947aefc04fc36829f1de8ee958f5fcfe6' => 'Academy Example',
	'beee63844a1df755bcbba112e86aa4ccb778c539' => 'institute.example',
	'e8dc31bd286829567c593801aee01f055b607554' => 'Institute Example',
	'5594a3d732f7bd900acd3fb314e7b13a7e60da42' => 'UIN Example',
	'38966fe5a86c40c9bdb6c93650df46342ee59bae' => 'Institute Example',
	'f60e2d204724f59cb291a5ebb3ca4f650d2141ab' => 'institute',
	'f41f04957264a0c902b417410c89e428c815d7c8' => 'institute',
	'7136a3be451ed0022bfa1313f13bfb209780e6bc' => 'politechnika.example',
	'619d36adf00ca50ddc68d60d095d4254aa94f313' => 'Łexample',
	'7012b61da750a37e20437a5c407c24b73aed138b' => 'Example',
	'ac4086137476562f00c412f7aa9d9c8e5b3c65a0' => 'example',
	'bd72abd463fa1f3fa65e8701395c263c0a6446ae' => 'plugins.mango-example.test',
	'fad524ef8aa06f36a22a191865e534cc1830758c' => 'Mango Example',
	'33b787deb64137c2c02078174c0dfaf1dda0975d' => 'mango-example',
	'af2f43d17920089bea071cc13a9b9744b24ab9a1' => 'Cirrus Example',
	'e796475bab0b814d1e1350521a13faf451ba0a3d' => 'cirrus-example',
	'c68c4fc3cd4f181be4c93114b3b52d23065be5b0' => 'Wexample',
	'537ea61b0f98b711a7d7a7623727ae3fc7c3a6e8' => 'wexample',
	'9e2c584462f274c61db474e808832113815ca929' => 'Ember Example',
	'ee0c018e735a984b890fc3d8f77922d973c9f1b5' => 'ember-example',
	// Added 8 September 2026: an institution's mail domain and its own-language name, found by the fix wave's re-review.
	'7110bf464e2779a5346083bd11fe04a5216023a2' => 'institution.example',
	'9aa1d355857c15bdb2a59fb11b41965315e39795' => 'Uniwersytet Przykładowy',
);

/**
 * The longest run of whitespace-separated words a refused string is allowed to be.
 *
 * Five, because the longest entry above is four words long and one more leaves room for the
 * next. Every window from one word to this many is hashed, so the cost of raising it is linear
 * in the number of words under bin/ and docs/: at five it is about nine million hashes and under
 * two seconds for both trees together.
 */
const WPCPM_REFUSED_WORDS = 5;

/**
 * The characters trimmed off the ends of a candidate before it is hashed.
 *
 * A name in a suite is wrapped in whatever the syntax around it needs - `'Sam Example',` in an
 * array, `(Sam Example)` in a sentence - and the wrapping is not part of the name. Trimming from
 * the ends of a substring leaves a substring, so a hit can still be replaced where it was found.
 *
 * A forward slash, a hyphen, an at sign and an underscore are NOT in this list: a profile
 * address ends in a slash, a handle can end in a hyphen, and both are strings this list refuses.
 */
const WPCPM_REFUSED_EDGE = " \t\r\n'\"(),;:=[]{}<>*.!?`|\\";

/**
 * The shortest candidate worth hashing, in bytes.
 *
 * One character is never a name and hashing every single character of both trees doubles the
 * work for nothing.
 */
const WPCPM_REFUSED_MIN = 2;

/**
 * The longest candidate worth hashing, in bytes.
 *
 * Longer than any name, handle or organization anybody would write, and short enough that a
 * minified fixture line does not turn into a hash of itself five times over.
 */
const WPCPM_REFUSED_MAX = 120;

/**
 * Every refused string, people and organizations together.
 *
 * @return array<string,string> `sha1()` of the refused string => its placeholder.
 */
function wpcpm_refused_all() {
	return WPCPM_SAMPLE_NAMES + WPCPM_SAMPLE_ORGS;
}

/**
 * The refused strings a body of text actually carries, with what each one becomes.
 *
 * The body is cut into candidates - every run of one to WPCPM_REFUSED_WORDS whitespace-separated
 * words, each also with its wrapping punctuation trimmed off - and each candidate is hashed and
 * looked up. Nothing here ever holds a refused string: what comes back is the text this body
 * carries, which is what makes it replaceable and what lets the walk report a file without
 * printing a value.
 *
 * A name split across two lines is not found, because the candidate would carry the line break
 * and the indent between its halves. That is the known limit of this shape, and it is the reason
 * the sweep is run over the whole of bin/ after every fixture refresh rather than relied on to
 * catch a name typed by hand into a wrapped comment.
 *
 * @param string                     $body    The file's text.
 * @param array<string,string>|null $refused The list to look for, for a check that has to prove
 *                                           this function finds anything at all; the real one by
 *                                           default.
 * @return array<string,string> The text as this body holds it => its placeholder.
 */
function wpcpm_refused_found( $body, $refused = null ) {
	$body    = (string) $body;
	$refused = null === $refused ? wpcpm_refused_all() : (array) $refused;
	$found   = array();

	if ( ! preg_match_all( '/\S+/', $body, $matches, PREG_OFFSET_CAPTURE ) ) {
		return $found;
	}

	$words = $matches[0];
	$count = count( $words );

	for ( $i = 0; $i < $count; $i++ ) {
		$start = $words[ $i ][1];

		for ( $k = 0; $k < WPCPM_REFUSED_WORDS && $i + $k < $count; $k++ ) {
			$end  = $words[ $i + $k ][1] + strlen( $words[ $i + $k ][0] );
			$span = substr( $body, $start, $end - $start );

			$candidates = array( $span, trim( $span, WPCPM_REFUSED_EDGE ) );

			// One word is also cut at every punctuation mark inside it, so that a name used as
			// the local part of an address, as a domain label or as a segment of a path is
			// found: a first name in front of an `@` and a short form of an institution in
			// front of a dot are both shapes the suites carried, and a whole-word compare
			// walks straight past either. Only single words, because the pieces of a run of
			// words are the pieces of each word in it.
			if ( 0 === $k ) {
				foreach ( preg_split( '/[^A-Za-z0-9\x80-\xFF]+/', $span ) as $piece ) {
					$candidates[] = $piece;
				}
			}

			foreach ( $candidates as $candidate ) {
				$length = strlen( $candidate );

				if ( WPCPM_REFUSED_MIN > $length || WPCPM_REFUSED_MAX < $length ) {
					continue;
				}

				$hash = sha1( $candidate );

				if ( isset( $refused[ $hash ] ) ) {
					$found[ $candidate ] = $refused[ $hash ];
				}
			}
		}
	}

	return $found;
}
