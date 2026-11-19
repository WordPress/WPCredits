<?php
/**
 * The test set of AI Mentor R1 (1.123.101): the questions Need help? must be able to answer, each with the
 * page and the section its answer has to come from, written before the engine was built.
 *
 * What it pins, and why it is worth pinning:
 *
 * - **The right passage reaches the model.** For each question, ranked as the asker's role may read with the
 *   search words the model is expected to give, the expected section is among the five passages the model is
 *   handed, and the first passage is on a page the answer may come from. A section the model is never shown
 *   cannot be cited, however good the model is; this is the part of an answer the site decides.
 * - **Other languages through English words.** Most students write in Spanish or Italian and the guides are in
 *   English. The Spanish and Italian questions here carry the English search words the model is asked for
 *   (design, section 6, step 2), so what is pinned is the site's half: that those words find the section.
 * - **The fallback.** For an English question with plain words, the section is also found from the question's
 *   own words alone, which is what the ranking reads when the model cannot be reached (section 6, step 6).
 * - **Nothing above the asker's level.** A student is never given a Mentor guide passage, an institution's
 *   member never a Sponsor guide passage, a mentor never an Administrator guide passage; an Administrator's
 *   question about the program's own steps is answered from the Administrator guide.
 *
 * The corpus is what the site indexes: the five built guides as committed (`docs/build/*.html`, the markup each
 * release publishes to its page), the FAQ page (`bin/fixtures/guides/faq.json`, its REST answer of 19 November
 * 2026) and the handbook's pages under `credits/` but for `program-manager-guide/` (`bin/fixtures/guides/
 * handbook.json`, the route's answer of the same day). Both fixtures have every email address replaced by the
 * words "the program's address" (the public repository's privacy guard refuses any address in a data file) and
 * every name the repository refuses replaced by its stand-in. The classes are the real
 * ones, over the suites' SQLite stand-in of the database. Nothing touches the network.
 *
 * A question that fails prints the five passages it was given. Its expected section is not changed to make it
 * pass (the plan's Ruling 2): the text pipeline or the ranking is fixed, or the expectation is shown wrong with
 * the passage that proves it.
 *
 * Run from the plugin root:  php bin/test-guide-questions.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_VERSION', '1.123.101' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts'] = array();

/* ---- WordPress, as far as the index reaches ---------------------------------- */

function __( $text, $domain = null ) { return $text; }
function apply_filters( $tag, $value ) { return $value; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $dep = '', $a = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function current_time( $type, $gmt = 0 ) { return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time(); }
// Core's contract, as far as it matters here: an address on a protocol WordPress does not allow comes back empty.
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );
	return preg_match( '#^https?://#i', $url ) ? $url : '';
}

require __DIR__ . '/stubs/sqlite-wpdb.php';

$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['wpdb']->create_guide_tables();

require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-guide-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-guide-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-guide-search.php';

/* ---- runner ------------------------------------------------------------------ */

$fail = 0;

function ck( $label, $actual, $expected ) {
	global $fail;
	if ( $actual === $expected ) {
		echo "ok    $label\n";
		return true;
	}
	$fail++;
	echo "FAIL  $label\n      got:  " . var_export( $actual, true ) . "\n      want: " . var_export( $expected, true ) . "\n";
	return false;
}

/* ---- the corpus, written as the site writes it ------------------------------- */

$site = 'https://example.test/';

// Source key, kind, audience, title, the page's address, and the markup it publishes.
$pages = array(
	array( 'page:558', 'guide', 'student', 'Student guide', $site . 'student-guide/', 'docs/build/students.html' ),
	array( 'page:559', 'guide', 'mentor', 'Mentor guide', $site . 'mentor-guide/', 'docs/build/mentors.html' ),
	array( 'page:1715', 'guide', 'institution', 'Institution guide', $site . 'institution-guide/', 'docs/build/institutions.html' ),
	array( 'page:1770', 'guide', 'sponsor', 'Sponsor guide', $site . 'sponsor-guide/', 'docs/build/sponsors.html' ),
	array( 'page:560', 'guide', 'administrator', 'Administrator guide', $site . 'administrator-guide/', 'docs/build/administrators.html' ),
);

foreach ( $pages as $page ) {
	list( $source, $kind, $audience, $title, $url, $file ) = $page;

	WPCPM_Guide_Index::write(
		$source,
		array(
			'kind'     => $kind,
			'audience' => $audience,
			'post_id'  => (int) substr( $source, 5 ),
			'title'    => $title,
			'url'      => $url,
			'modified' => '2026-11-19 00:00:00',
		),
		WPCPM_Guide_Text::passages( (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ) )
	);
}

$faq = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/guides/faq.json' ), true );

WPCPM_Guide_Index::write(
	'page:2746',
	array(
		'kind'     => 'faq',
		'audience' => 'public',
		'post_id'  => 2746,
		'title'    => 'Frequently asked questions',
		'url'      => $site . 'faq/',
		'modified' => (string) $faq['modified_gmt'],
	),
	WPCPM_Guide_Text::passages( (string) $faq['content']['rendered'] )
);

// The handbook's pages the sync keeps: under credits/, but for the program manager's guide (design, section 3).
$handbook = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/guides/handbook.json' ), true );
$kept     = 0;

foreach ( $handbook as $hb ) {
	$link = (string) $hb['link'];

	if ( false === strpos( $link, '/community/handbook/education/credits/' ) || false !== strpos( $link, '/credits/program-manager-guide/' ) ) {
		continue;
	}

	$kept++;

	WPCPM_Guide_Index::write(
		'handbook:' . (int) $hb['id'],
		array(
			'kind'     => 'handbook',
			'audience' => 'public',
			'post_id'  => 0,
			'title'    => html_entity_decode( (string) $hb['title']['rendered'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'url'      => $link,
			'modified' => (string) $hb['modified_gmt'],
		),
		WPCPM_Guide_Text::passages( (string) $hb['content']['rendered'] )
	);
}

ck( 'the twelve handbook pages of 19 November are indexed', $kept, 12 );
ck( 'the index holds every source: the five guides, the FAQ and the twelve handbook pages', count( WPCPM_Guide_Index::sources() ), count( $pages ) + 1 + 12 );

/* ---- who reads what (the plan's Ruling 5) ------------------------------------ */

$reads = array(
	'student'       => array( 'public', 'student' ),
	'mentor'        => array( 'public', 'mentor' ),
	'institution'   => array( 'public', 'institution' ),
	'sponsor'       => array( 'public', 'sponsor' ),
	'administrator' => array( 'public', 'student', 'mentor', 'institution', 'sponsor', 'administrator' ),
);

$hb_base = 'https://make.wordpress.org/community/handbook/education/credits/';

/**
 * Where a passage is, as the test set names it: a site page's path and its anchor, or a handbook page's path.
 *
 * @param array $passage One of `rank()`'s.
 * @return string Such as `student-guide/#report-form`, `faq/#does-it-cost` or `handbook:how-to-participate/call-for-mentors/`.
 */
function where( array $passage ) {
	global $site, $hb_base;

	$url = (string) $passage['url'];

	if ( 0 === strpos( $url, $hb_base ) ) {
		return 'handbook:' . (string) preg_replace( '/#.*$/', '', substr( $url, strlen( $hb_base ) ) );
	}

	return 0 === strpos( $url, $site ) ? substr( $url, strlen( $site ) ) : $url;
}

/**
 * The page part of `where()`: everything before the anchor.
 *
 * @param string $where A place.
 * @return string
 */
function page_of( $where ) {
	return (string) preg_replace( '/#.*$/', '', $where );
}

/* ---- the questions ----------------------------------------------------------- */

$questions = require __DIR__ . '/fixtures/guides/questions.php';

ck( 'the test set holds at least fifteen questions', count( $questions ) >= 15, true );

$languages = 0;

foreach ( $questions as $n => $q ) {
	list( $role, $question, $words, $sections, $first_pages, $fallback ) = $q;

	$num = $n + 1;

	if ( preg_match( '/[¿¡]|\b(Come|Devo|Quale|Puedo)\b/u', $question ) ) {
		$languages++;
	}

	$asked = array( $words, $question );
	$runs  = array( 'search words' => $asked );

	if ( $fallback ) {
		$runs['own words'] = array( $question );
	}

	foreach ( $runs as $how => $given ) {
		$found = WPCPM_Guide_Search::rank( $given, $reads[ $role ], $role, 5 );
		$at    = array_map( 'where', $found );
		$first = isset( $at[0] ) ? $at[0] : '';

		$hit = array() !== array_intersect( $sections, $at );
		$top = false;

		foreach ( $first_pages as $accepted ) {
			if ( '' !== $first && 0 === strpos( $first, $accepted ) ) {
				$top = true;
			}
		}

		$ok = ck( sprintf( 'Q%d (%s, %s): an expected section is among the five passages', $num, $role, $how ), $hit, true )
			& ck( sprintf( 'Q%d (%s, %s): the first passage is on an accepted page', $num, $role, $how ), $top, true );

		if ( ! $ok ) {
			echo "      asked: $question\n";
			foreach ( $found as $i => $passage ) {
				printf( "      %d. %s  %.4f  %s\n", $i + 1, where( $passage ), $passage['score'], $passage['heading'] );
			}
		}
	}
}

ck( 'some questions are in Spanish and Italian', $languages >= 8, true );

/* ---- nothing above the asker's level ----------------------------------------- */

$levels = array(
	array( 'student', 'How do I set my availability for calls?', 'set availability calls hours', 'mentor' ),
	array( 'institution', 'How do I make an offer with codes?', 'make offer codes', 'sponsor' ),
	array( 'mentor', 'How do I approve an institution application?', 'approve institution application', 'administrator' ),
	array( 'sponsor', 'How do I enroll students?', 'enroll students', 'institution' ),
);

foreach ( $levels as $level ) {
	list( $role, $question, $words, $refused ) = $level;

	$audiences = array_unique( array_map( static function ( $p ) { return (string) $p['audience']; }, WPCPM_Guide_Search::rank( array( $words, $question ), $reads[ $role ], $role, 5 ) ) );

	ck( sprintf( 'a %s asking "%s" is given no %s passage', $role, $question, $refused ), in_array( $refused, $audiences, true ), false );
}

// Every passage of the first answer to a student is one a student may read.
$student = WPCPM_Guide_Search::rank( array( 'mentor checklist weekly rhythm' ), $reads['student'], 'student', 5 );

ck(
	'a student asking about the mentor checklist reads only public and student passages',
	array_values( array_diff( array_unique( array_map( static function ( $p ) { return (string) $p['audience']; }, $student ) ), array( 'public', 'student' ) ) ),
	array()
);

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
