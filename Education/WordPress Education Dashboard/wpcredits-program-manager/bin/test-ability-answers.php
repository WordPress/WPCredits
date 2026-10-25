<?php
/**
 * The four answers (1.123.30): what `WPCPM_Ability_Answers` builds from the readers the plugin already has.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The readers are still there.** The suite stands the readers in with fixtures
 *   (`bin/stubs/ability-readers.php`), so a reader renamed on the real classes would leave every check
 *   green and the ability broken. The first block reads the real files and asks for each method.
 * - **Program counts are the Programs and Sponsors cards' numbers,** track by track, with the institutions
 *   per stage, the test sponsors counted out of the approved, the mentor applications and the outcome
 *   figures that may be shown, each with when it was read or null when it never was.
 * - **Waiting is the attention strip,** tile for tile in the strip's order, with the number the strip
 *   prints and the address that opens the tile's card, and the syncs' times, never a sync's error text.
 * - **Engagement counts as of a day:** the end of that day in UTC, never a time that has not come, and a
 *   date that is no date counts as of now. The summary is the Engagement tab's, key for key.
 * - **An institution is found by its record ID or by part of its name,** at most five, a sixth says
 *   `more`. Its contacts and the person who accepted its agreement never leave, and a name with a newline
 *   and 500 characters arrives as one clean line (Review Focus 4).
 * - **No person anywhere.** For every answer the JSON holds none of the names, addresses or logins the
 *   fixtures carry on every row the readers return.
 * - **A builder never returns an error,** whatever it is given: nothing found is an answer with an empty
 *   list, and what cannot be read is ignored as if it were absent.
 *
 * Nothing touches the network or a database. Every record ID is invented, uppercase letters and digits
 * after `rec`, and every address is on `example.test`.
 *
 * Run from the plugin root:  php bin/test-ability-answers.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

// Translations, empty unless a check asks for some: a string that is data must not follow them.
$GLOBALS['translations'] = array();
function __( $text, $domain = null ) { return isset( $GLOBALS['translations'][ $text ] ) ? $GLOBALS['translations'][ $text ] : $text; }

/**
 * Core's remove_accents(), as far as the fixtures' names reach: the letters below lose their marks.
 *
 * @param string $text   A name.
 * @param string $locale Not read.
 * @return string
 */
function remove_accents( $text, $locale = '' ) {
	return strtr( (string) $text, array( 'É' => 'E', 'é' => 'e', 'Ł' => 'L', 'ł' => 'l', 'ó' => 'o', 'ź' => 'z' ) );
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-abilities.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ability-answers.php';
require_once __DIR__ . '/stubs/ability-readers.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

/** An answer's build, from a clean slate of fixtures unless told otherwise. */
function build( $method, $input = null ) {
	return call_user_func( array( 'WPCPM_Ability_Answers', $method ), $input );
}

/** The answer part of a build as plain arrays, the way its JSON reads back. */
function plain( $value ) {
	return json_decode( (string) json_encode( $value ), true );
}

/** The answer part of a build, as JSON text. */
function json_of( $build ) {
	return (string) json_encode( $build );
}

/** The time the last Engagement report was asked to count as of, or null. */
function engagement_now() {
	foreach ( array_reverse( $GLOBALS['reader_calls'] ) as $call ) {
		if ( 'engagement' === $call[0] ) { return $call[1]; }
	}

	return null;
}

/** The syncs the card's rule was asked of since the last reset, each as its label and the switch it was asked with. */
function needs_look_asked() {
	$asked = array();

	foreach ( $GLOBALS['reader_calls'] as $call ) {
		if ( 'needs_look' === $call[0] ) { $asked[] = array( $call[1], $call[2] ); }
	}

	return $asked;
}

/** The arguments of the last roster `groups()` call, or null. */
function groups_asked() {
	foreach ( array_reverse( $GLOBALS['reader_calls'] ) as $call ) {
		if ( 'groups' === $call[0] ) { return array( $call[1], $call[2] ); }
	}

	return null;
}

/** Institutions whose names hold "Univ", named so that the first in the index is the last by name. */
function many_universities( $count, array $template ) {
	$rows = array();

	for ( $n = 1; $n <= $count; $n++ ) {
		$id               = sprintf( 'recMANY%010d', $n );
		$row              = $template;
		$row['record_id'] = $id;
		$row['name']      = sprintf( 'Univ Number %02d', $count + 1 - $n );
		$rows[ $id ]      = $row;
	}

	return $rows;
}

$ISO = static function ( $unix ) { return gmdate( 'c', $unix ); };

// The child that runs on a PHP without mb_stripos(): the lookup matches with stripos() instead.
if ( isset( $argv[1] ) && 'child-no-mb-stripos' === $argv[1] ) {
	readers_reset();
	echo json_encode( array( function_exists( 'mb_stripos' ), array_column( build( 'institutions', array( 'institution' => 'UNIV' ) )['answer']['matches'], 'name' ) ) );
	exit( 0 );
}

echo "=== The readers the answers lean on are still on the real classes ===\n";
$readers = array(
	'includes/modules/class-wpcpm-administrators-cards.php'     => array( 'WPCPM_Administrators_Cards', array( 'tracks', 'programs', 'sponsors', 'collect', 'counts', 'health', 'sync_needs_look', 'figures_need_look' ) ),
	'includes/modules/class-wpcpm-administrators-dashboard.php' => array( 'WPCPM_Administrators_Dashboard', array( 'page_url' ) ),
	'includes/modules/class-wpcpm-mentor-engagement.php'        => array( 'WPCPM_Mentor_Engagement', array( 'report' ) ),
	'includes/modules/class-wpcpm-institutions-index.php'       => array( 'WPCPM_Institutions_Index', array( 'read', 'rows', 'row', 'by_stage' ) ),
	'includes/class-wpcpm-roster-index.php'                     => array( 'WPCPM_Roster_Index', array( 'groups', 'counts' ) ),
	'includes/modules/class-wpcpm-institution-members.php'      => array( 'WPCPM_Institution_Members', array( 'members_of' ) ),
	'includes/modules/class-wpcpm-sponsors-index.php'           => array( 'WPCPM_Sponsors_Index', array( 'rows', 'is_test' ) ),
	'includes/modules/class-wpcpm-mentor-application-feed.php'  => array( 'WPCPM_Mentor_Application_Feed', array( 'build' ) ),
	'includes/class-wpcpm-outcome-figures.php'                  => array( 'WPCPM_Outcome_Figures', array( 'published' ) ),
	'includes/class-wpcpm-cohort.php'                           => array( 'WPCPM_Cohort', array( 'is_key' ) ),
	'includes/modules/class-wpcpm-mentors-sync.php'             => array( 'WPCPM_Mentors_Sync', array( 'is_record_id' ) ),
);
foreach ( $readers as $file => $pair ) {
	$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . $file );
	$found  = array();
	foreach ( $pair[1] as $method ) {
		if ( preg_match( '/^\tpublic static function ' . preg_quote( $method, '/' ) . '\(/m', $source ) ) { $found[] = $method; }
	}
	ck( "{$pair[0]}: " . implode( ', ', $pair[1] ), array( 0 < preg_match( '/^(?:final )?class ' . preg_quote( $pair[0], '/' ) . '\b/m', $source ), $found ), array( true, $pair[1] ) );
}
ck( 'WPCPM_Sponsors_Index::STATUS_APPROVED is still the word the base spells', 0 < preg_match( "/const STATUS_APPROVED = 'Approved';/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php' ) ), true );
ck( 'the outcome figures are still graduation, recommend and keep', 0 < preg_match( "/const FIGURES = array\( 'graduation', 'recommend', 'keep' \);/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-outcome-figures.php' ) ), true );
ck( 'the answers class\'s own list of them agrees', WPCPM_Ability_Answers::FIGURES, array( 'graduation', 'recommend', 'keep' ) );
preg_match( "/array\( ('active', [^)]*'staff') \)/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php' ), $keys );
ck( 'the Engagement summary\'s keys are the answers class\'s nineteen, in the same order', isset( $keys[1] ) ? array_map( static function ( $key ) { return trim( $key, "'" ); }, explode( ', ', $keys[1] ) ) : array(), WPCPM_Ability_Answers::ENGAGEMENT_KEYS );

echo "\n=== Program counts ===\n";
readers_reset();
ck( 'the engagement fixture lists the same nineteen keys, in the same order', array_keys( $GLOBALS['readers']['engagement']['summary'] ), WPCPM_Ability_Answers::ENGAGEMENT_KEYS );

$before = time();
$built  = build( 'program_counts' );
$after  = time();
$a      = plain( $built['answer'] );
ck( 'a build is the answer and the record IDs it was made of', array_keys( $built ), array( 'answer', 'records' ) );
ck( 'it names no record, so its log row counts none', $built['records'], array() );
ck( 'the answer\'s keys, in the order the schema lists them', array_keys( $a ), array( 'semester', 'students', 'mentors', 'institutions', 'sponsors', 'mentor_applications', 'outcome_figures' ) );
ck( 'the semester is the Programs card\'s', $a['semester'], '2026-H2' );
ck( 'students: the keys', array_keys( $a['students'] ), array( 'tracks', 'finished_this_semester', 'institutions_with_students', 'read_at' ) );
ck( 'students: per track, by the track\'s label, in progress and signed up, as the card counts them', $a['students']['tracks'], array(
	'Alpha Track' => array( 'in_progress' => 12, 'signed_up' => 5 ),
	'Beta Track'  => array( 'in_progress' => 3, 'signed_up' => 0 ),
) );
ck( 'students: finished this semester is the card\'s finished', $a['students']['finished_this_semester'], 7 );
ck( 'students: the institutions with students are the card\'s rows', $a['students']['institutions_with_students'], 2 );
ck( 'students: read at the roster\'s oldest read, in UTC', $a['students']['read_at'], '2026-10-24T00:00:00+00:00' );
ck( 'mentors: the Engagement summary\'s active and staff', $a['mentors'], array( 'active' => 40, 'staff' => 3 ) );
ck( 'the mentors were counted as of now', engagement_now() >= $before && engagement_now() <= $after, true );
ck( 'institutions: each stage\'s rows, no stage by that name, a stage cleaned to one line', $a['institutions']['by_stage'], array( 'Confirmed' => 2, 'Under Review' => 1, 'Info Sent' => 1, 'No stage' => 1 ) );
ck( 'institutions: read at the index\'s read time', $a['institutions']['read_at'], '2026-10-24T01:00:00+00:00' );
ck( 'sponsors: the keys', array_keys( $a['sponsors'] ), array( 'approved', 'test_approved', 'with_accounts', 'live_offers', 'claims_this_semester', 'tiers' ) );
ck( 'sponsors: the card\'s numbers, and the approved that are test sponsors', array( $a['sponsors']['approved'], $a['sponsors']['test_approved'], $a['sponsors']['with_accounts'], $a['sponsors']['live_offers'], $a['sponsors']['claims_this_semester'] ), array( 5, 2, 3, 4, 9 ) );
ck( 'sponsors: a test sponsor that is not approved is not counted', $a['sponsors']['test_approved'], 2 );
ck( 'sponsors: per tier, the card\'s five rungs', $a['sponsors']['tiers'], array( 'mentor' => 1, 'supporter' => 2, 'backer' => 1, 'champion' => 0, 'partner' => 1 ) );
ck( 'mentor applications: open, and selected and waiting for the course', $a['mentor_applications'], array( 'open' => 4, 'selected_waiting' => 2 ) );
ck( 'outcome figures: when they were read, and the three figures shown', $a['outcome_figures'], array(
	'read_at'    => '2026-10-24T02:00:00+00:00',
	'graduation' => array( 'share' => 78, 'count' => 156, 'n' => 200 ),
	'recommend'  => array( 'share' => 91, 'count' => 91, 'n' => 100 ),
	'keep'       => array( 'share' => 64, 'count' => 64, 'n' => 100 ),
) );
ck( 'every number is an integer', array_filter( array( $a['students']['tracks']['Alpha Track']['in_progress'], $a['students']['finished_this_semester'], $a['mentors']['active'], $a['sponsors']['tiers']['partner'], $a['institutions']['by_stage']['Confirmed'], $a['outcome_figures']['graduation']['share'] ), 'is_int' ) === array( 12, 7, 40, 1, 2, 78 ), true );

echo "\n--- Program counts: what is not there is said so ---\n";
readers_reset();
$GLOBALS['readers']['published'] = array();
$a = plain( build( 'program_counts' )['answer'] );
ck( 'no figure may be shown: the outcome figures hold a null read time and no figure', $a['outcome_figures'], array( 'read_at' => null ) );
readers_reset();
$GLOBALS['readers']['published'] = array( 'read' => READER_T_OUTCOMES, 'figures' => array( 'keep' => array( 'share' => 64, 'count' => 64, 'n' => 100 ) ) );
$a = plain( build( 'program_counts' )['answer'] );
ck( 'one figure shown: only that one is named', $a['outcome_figures'], array( 'read_at' => '2026-10-24T02:00:00+00:00', 'keep' => array( 'share' => 64, 'count' => 64, 'n' => 100 ) ) );
readers_reset();
$GLOBALS['readers']['programs']['read']            = 0;
$GLOBALS['readers']['institutions']['read']        = 0;
$a = plain( build( 'program_counts' )['answer'] );
ck( 'a source never read has a null read time, not 1970', array( $a['students']['read_at'], $a['institutions']['read_at'] ), array( null, null ) );
readers_reset();
unset( $GLOBALS['readers']['sponsors']['tiers'] );
$GLOBALS['readers']['institutions']['rows'] = array();
$GLOBALS['readers']['programs']['tracks']   = array();
$built = build( 'program_counts' );
ck( 'no ladder, no institutions and no tracks: each map is an empty object in the JSON, never an empty list', array(
	json_encode( $built['answer']['sponsors']['tiers'] ),
	json_encode( $built['answer']['institutions']['by_stage'] ),
	json_encode( $built['answer']['students']['tracks'] ),
), array( '{}', '{}', '{}' ) );
ck( 'and the answer is an answer all the same', array( $built['answer']['sponsors']['approved'], $built['answer']['students']['institutions_with_students'] ), array( 5, 2 ) );

readers_reset();
$GLOBALS['readers']['tracks']['alpha'] = "Alpha\nTrack\u{200B}" . str_repeat( 'z', 200 );
$a = plain( build( 'program_counts' )['answer'] );
ck( 'a track label is cleaned and cut at 120 like any typed text', array_keys( $a['students']['tracks'] )[0], mb_substr( 'Alpha Track' . str_repeat( 'z', 200 ), 0, 120 ) );
readers_reset();
$GLOBALS['readers']['institutions']['rows']['recINST0000000004']['stage'] = 'No stage';
$a = plain( build( 'program_counts' )['answer'] );
ck( 'a stage written "No stage" and a record with none are one key, their rows added', array( $a['institutions']['by_stage']['No stage'], array_sum( $a['institutions']['by_stage'] ) ), array( 2, 5 ) );
readers_reset();
$GLOBALS['readers']['tracks']['beta'] = 'Alpha Track';
$a = plain( build( 'program_counts' )['answer'] );
ck( 'two tracks with one label are one key, their numbers added', $a['students']['tracks'], array( 'Alpha Track' => array( 'in_progress' => 15, 'signed_up' => 5 ) ) );

echo "\n=== What waits for an Administrator ===\n";
readers_reset();
$built = build( 'waiting' );
$a     = plain( $built['answer'] );
ck( 'a build is the answer and no records', array( array_keys( $built ), $built['records'] ), array( array( 'answer', 'records' ), array() ) );
ck( 'the answer\'s keys', array_keys( $a ), array( 'tiles', 'nothing_waiting', 'syncs' ) );
ck( 'the tiles are the strip\'s, in the strip\'s order', array_column( $a['tiles'], 'key' ), array( 'applications', 'agreements', 'mentor_applications', 'sponsor_posts', 'syncs' ) );
ck( 'each tile: key, label, count, shown, open', array_keys( $a['tiles'][0] ), array( 'key', 'label', 'count', 'shown', 'open' ) );
ck( 'a tile with a card: its number, printed as it stands, opens that card on the page', $a['tiles'][0], array( 'key' => 'applications', 'label' => 'Institution applications waiting', 'count' => 3, 'shown' => '3', 'open' => 'https://example.test/administrator-dashboard/#wpcpm-applications' ) );
ck( 'a tile with an address opens that address', $a['tiles'][2]['open'], 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' );
ck( 'a tile that prints other words for its number says them in shown, and the count stays the number', array( $a['tiles'][3]['count'], $a['tiles'][3]['shown'], $a['tiles'][3]['open'] ), array( 26, '25+', 'https://example.test/administrator-dashboard/#wpcpm-sponsor-posts' ) );
ck( 'counts() was handed what collect() read, the call the Overview makes', in_array( array( 'counts', array( 'collected' => true ) ), $GLOBALS['reader_calls'], true ), true );
ck( 'something waits', $a['nothing_waiting'], false );
ck( 'the syncs, in the health card\'s order, with their times in UTC and whether each needs a look', $a['syncs'], array(
	array( 'key' => 'students', 'label' => 'Students', 'last_run' => '2026-10-24T04:00:00+00:00', 'next_run' => '2026-10-24T05:00:00+00:00', 'needs_look' => true ),
	array( 'key' => 'mentors', 'label' => 'Mentors', 'last_run' => '2026-10-24T04:00:00+00:00', 'next_run' => '2026-10-24T05:00:00+00:00', 'needs_look' => false ),
	array( 'key' => 'institutions', 'label' => 'Institutions', 'last_run' => null, 'next_run' => null, 'needs_look' => true ),
	array( 'key' => 'outcome_figures', 'label' => 'Outcome figures', 'last_run' => '2026-10-24T02:00:00+00:00', 'next_run' => '2026-10-24T05:00:00+00:00', 'needs_look' => false ),
) );
ck( 'each sync: key, label, last run, next run, needs a look', array_keys( $a['syncs'][0] ), array( 'key', 'label', 'last_run', 'next_run', 'needs_look' ) );
ck( 'needs_look is a boolean, never a code and never the error\'s words', array_unique( array_map( 'gettype', array_column( $a['syncs'], 'needs_look' ) ) ), array( 'boolean' ) );
ck( 'the card\'s rule was asked of each audience\'s sync, with the switch health() read: Automatic sync on', needs_look_asked(), array( array( 'Students', true ), array( 'Mentors', true ), array( 'Institutions', true ) ) );
ck( 'the outcome figures\' weekly read is judged by its own rule, not the six hours of the others', array( in_array( array( 'figures_look' ), $GLOBALS['reader_calls'], true ), in_array( 'Outcome figures', array_column( needs_look_asked(), 0 ), true ) ), array( true, false ) );
$json = json_of( $built );
ck( 'a sync\'s error text is in no part of the answer: not the progress, not the note, not the screen', array( false !== strpos( $json, 'Token rejected' ), false !== strpos( $json, 'progress' ), false !== strpos( $json, 'note' ), false !== strpos( $json, 'screen' ), false !== strpos( $json, 'wpcpm-students' ) ), array( false, false, false, false, false ) );

// Automatic sync off: the switch health() read is the one the rule is asked with, and an old run is the program's choice.
readers_reset();
$GLOBALS['readers']['health']['auto_sync'] = false;
$a = plain( build( 'waiting' )['answer'] );
ck( 'with Automatic sync off the rule is asked with the switch off, and only the sync that left an error needs a look', array( needs_look_asked(), array_column( $a['syncs'], 'needs_look', 'key' ) ), array(
	array( array( 'Students', false ), array( 'Mentors', false ), array( 'Institutions', false ) ),
	array( 'students' => true, 'mentors' => false, 'institutions' => false, 'outcome_figures' => false ),
) );
readers_reset();
unset( $GLOBALS['readers']['health']['auto_sync'] );
$a = plain( build( 'waiting' )['answer'] );
ck( 'a health() that does not say is read as the card reads it: Automatic sync on', array( needs_look_asked(), array_column( $a['syncs'], 'needs_look', 'key' ) ), array(
	array( array( 'Students', true ), array( 'Mentors', true ), array( 'Institutions', true ) ),
	array( 'students' => true, 'mentors' => false, 'institutions' => true, 'outcome_figures' => false ),
) );
readers_reset();
$GLOBALS['readers']['health']['auto_sync'] = 0;
build( 'waiting' );
ck( 'a switch held as 0 is off', array_unique( array_column( needs_look_asked(), 1 ) ), array( false ) );

// The weekly read of the outcome figures says it needs a look for its own reasons, the switch on or off.
foreach ( array( 'held', 'failed', 'stopped' ) as $why ) {
	foreach ( array( true, false ) as $auto ) {
		readers_reset();
		$GLOBALS['readers']['figures_look']        = $why;
		$GLOBALS['readers']['health']['auto_sync'] = $auto;
		$a = plain( build( 'waiting' )['answer'] );
		ck( "a $why read of the outcome figures needs a look, Automatic sync " . ( $auto ? 'on' : 'off' ), array_column( $a['syncs'], 'needs_look', 'key' )['outcome_figures'], true );
	}
}
readers_reset();
$GLOBALS['readers']['figures_look'] = 'held';
$a = plain( build( 'waiting' )['answer'] );
ck( 'and it is the only one that changes: the audiences\' syncs are as they were', array_column( $a['syncs'], 'needs_look', 'key' ), array( 'students' => true, 'mentors' => false, 'institutions' => true, 'outcome_figures' => true ) );
readers_reset();
$GLOBALS['readers']['health']['syncs'] = array();
$built = build( 'waiting' );
ck( 'no sync to speak of: an empty list, and the rule asked of none', array( json_encode( $built['answer']['syncs'] ), needs_look_asked() ), array( '[]', array() ) );
readers_reset();
$GLOBALS['readers']['health']['syncs']['students'] = 'not a sync';
$built = build( 'waiting' );
ck( 'something that is no sync is left out, and the others are still said', array_column( $built['answer']['syncs'], 'key' ), array( 'mentors', 'institutions', 'outcome_figures' ) );

readers_reset();
$GLOBALS['readers']['tiles'] = array();
$built = build( 'waiting' );
ck( 'no tile: nothing waits, the list is an empty list', array( $built['answer']['nothing_waiting'], json_encode( $built['answer']['tiles'] ) ), array( true, '[]' ) );
ck( 'and the syncs are still said', count( $built['answer']['syncs'] ), 4 );

readers_reset();
$GLOBALS['readers']['page_url'] = '';
$a = plain( build( 'waiting' )['answer'] );
ck( 'no published dashboard page: a tile with a card has no address to give, and a tile with an address keeps it', array( $a['tiles'][0]['open'], $a['tiles'][2]['open'] ), array( '', 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' ) );

readers_reset();
$GLOBALS['readers']['tiles']['applications']['label'] = "Institution\napplications" . str_repeat( 'w', 300 );
$GLOBALS['readers']['health']['syncs']['students']['label'] = "Stu\u{200B}dents\n";
$a = plain( build( 'waiting' )['answer'] );
ck( 'a label is one clean line of at most 120 characters', array( strlen( $a['tiles'][0]['label'] ), 0 === strpos( $a['tiles'][0]['label'], 'Institution applications' ), $a['syncs'][0]['label'] ), array( 120, true, 'Students' ) );

echo "\n=== Mentor engagement ===\n";
readers_reset();
$GLOBALS['reader_calls'] = array();
$before = time();
$built  = build( 'engagement' );
$after  = time();
$used   = engagement_now();
$a      = plain( $built['answer'] );
ck( 'a build is the answer and no records', array( array_keys( $built ), $built['records'] ), array( array( 'answer', 'records' ), array() ) );
ck( 'the answer\'s keys', array_keys( $a ), array( 'as_of', 'summary' ) );
ck( 'with no input it counts as of now', $used >= $before && $used <= $after, true );
ck( 'and says the time it used, in UTC', $a['as_of'], $ISO( $used ) );
ck( 'the summary is the Engagement tab\'s, unchanged', $a['summary'], $GLOBALS['readers']['engagement']['summary'] );
ck( 'its keys are the nineteen, in the tab\'s order', array_keys( $a['summary'] ), WPCPM_Ability_Answers::ENGAGEMENT_KEYS );
ck( 'and each is an integer', count( array_filter( $a['summary'], 'is_int' ) ), 19 );
$json = json_of( $built );
ck( 'the rows of the report are not in the answer', array( false !== strpos( $json, 'Mentor Name Phi' ), false !== strpos( $json, 'mentor-phi' ), false !== strpos( $json, 'mentor_phi' ), false !== strpos( $json, 'rows' ) ), array( false, false, false, false ) );

$GLOBALS['reader_calls'] = array();
$a = plain( build( 'engagement', array( 'as_of' => '2026-03-15' ) )['answer'] );
ck( 'a past day is counted as of the end of it, 23:59:59 UTC', array( engagement_now(), $a['as_of'] ), array( 1773619199, '2026-03-15T23:59:59+00:00' ) );
ck( '1773619199 is 15 March 2026, 23:59:59 UTC', gmdate( 'Y-m-d H:i:s', 1773619199 ), '2026-03-15 23:59:59' );
$GLOBALS['reader_calls'] = array();
build( 'engagement', array( 'as_of' => '2026-12-31', 'extra' => 'ignored' ) );
ck( 'a key the schema does not name is ignored', engagement_now() > 0, true );

foreach ( array(
	'today'                       => gmdate( 'Y-m-d' ),
	'tomorrow'                    => gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ),
	'a day far in the future'     => '2999-12-31',
) as $label => $day ) {
	$GLOBALS['reader_calls'] = array();
	$before = time();
	$a      = plain( build( 'engagement', array( 'as_of' => $day ) )['answer'] );
	$after  = time();
	ck( "$label: counted as of now, never a time that has not come", array( engagement_now() >= $before && engagement_now() <= $after, $a['as_of'] === $ISO( engagement_now() ) ), array( true, true ) );
}

foreach ( array(
	'text that is no date'                 => 'yesterday',
	'a day that does not exist'            => '2026-02-30',
	'a month that does not exist'          => '2026-13-01',
	'a date with a time'                   => '2026-03-15 10:00:00',
	'a date with a trailing space'         => '2026-03-15 ',
	'a date with a trailing newline'       => "2026-03-15\n",
	'an empty string'                      => '',
	'a day before the Unix epoch'          => '1960-01-01',
	'a number'                             => 20260315,
	'a list'                               => array( '2026-03-15' ),
	'null'                                 => null,
) as $label => $value ) {
	$GLOBALS['reader_calls'] = array();
	$before = time();
	$built  = build( 'engagement', array( 'as_of' => $value ) );
	$after  = time();
	ck( "$label: ignored as if absent, counted as of now, still an answer", array( is_array( $built['answer'] ), engagement_now() >= $before && engagement_now() <= $after ), array( true, true ) );
}
foreach ( array( 'no array' => 'text', 'a number' => 12, 'an empty list' => array() ) as $label => $input ) {
	$GLOBALS['reader_calls'] = array();
	$before = time();
	build( 'engagement', $input );
	ck( "an input that is $label: as of now", engagement_now() >= $before && engagement_now() <= time(), true );
}

readers_reset();
$GLOBALS['readers']['engagement']['summary']['an_unnamed_key'] = 99;
$GLOBALS['readers']['engagement']['summary']['staff']          = '3';
unset( $GLOBALS['readers']['engagement']['summary']['idle'] );
$a = plain( build( 'engagement' )['answer'] );
ck( 'a key the schema does not name is left out, a number held as text is a number, a missing one is zero', array( isset( $a['summary']['an_unnamed_key'] ), $a['summary']['staff'], $a['summary']['idle'], count( $a['summary'] ) ), array( false, 3, 0, 19 ) );

echo "\n=== One institution ===\n";
readers_reset();
$built = build( 'institutions', array( 'institution' => 'recINST0000000001' ) );
$a     = plain( $built['answer'] );
ck( 'a build is the answer and the record IDs of the matches, and nothing else', array( array_keys( $built ), $built['records'] ), array( array( 'answer', 'records' ), array( 'recINST0000000001' ) ) );
ck( 'the answer\'s keys', array_keys( $a ), array( 'matches', 'more', 'read_at', 'institutions_read_at' ) );
ck( 'a record ID finds that institution alone', array( count( $a['matches'] ), $a['more'] ), array( 1, false ) );
ck( 'the roster\'s read time, in UTC', $a['read_at'], '2026-10-24T03:00:00+00:00' );
ck( 'and the institutions\' own, the index the names, stages and agreements come from, beside it', $a['institutions_read_at'], '2026-10-24T01:00:00+00:00' );
ck( 'a match: the keys', array_keys( $a['matches'][0] ), array( 'record_id', 'name', 'stage', 'country', 'city', 'agreement', 'members', 'students' ) );
ck( 'a match: name, stage, country\'s name and city', array( $a['matches'][0]['record_id'], $a['matches'][0]['name'], $a['matches'][0]['stage'], $a['matches'][0]['country'], $a['matches'][0]['city'] ), array( 'recINST0000000001', 'University of Example', 'Confirmed', 'Exampleland', 'Sample City' ) );
ck( 'its agreement: status, kind, three dates and whether a document is on file, and no one who accepted it', $a['matches'][0]['agreement'], array(
	'status' => 'Accepted', 'kind' => 'Standard', 'submitted_on' => '2026-02-20', 'accepted_on' => '2026-03-02', 'signed_on' => '2026-03-01', 'has_document' => true,
) );
ck( 'members: how many accounts act for it', $a['matches'][0]['members'], 3 );
ck( 'students per group, for every semester when none is asked', $a['matches'][0]['students'], array( 'current' => 7, 'waiting' => 2, 'finished' => 4, 'not_started' => 1, 'cohort' => null ) );
ck( 'the roster was asked for every semester', groups_asked(), array( 'recINST0000000001', '' ) );

$a = plain( build( 'institutions', array( 'institution' => "  recINST0000000001\n" ) )['answer'] );
ck( 'a record ID with white space around it is still a record ID', array_column( $a['matches'], 'record_id' ), array( 'recINST0000000001' ) );
$built = build( 'institutions', array( 'institution' => 'recINST0000000099' ) );
ck( 'a record ID the index does not hold finds nothing, and says so', array( $built['answer']['matches'], $built['answer']['more'], $built['records'] ), array( array(), false, array() ) );

$built = build( 'institutions', array( 'institution' => 'univ' ) );
$a     = plain( $built['answer'] );
ck( '"univ" finds every name holding it, case-insensitive, by name A to Z', array_column( $a['matches'], 'name' ), array( 'Northern UNIVERSITY of Samples', 'University of Example' ) );
exec( escapeshellarg( PHP_BINARY ) . ' -d disable_functions=mb_stripos ' . escapeshellarg( __FILE__ ) . ' child-no-mb-stripos 2>&1', $no_mb_out, $no_mb_status );
ck( 'and on a PHP without mb_stripos() the same, through stripos()', array( $no_mb_status, json_decode( implode( '', $no_mb_out ), true ) ), array( 0, array( false, array( 'Northern UNIVERSITY of Samples', 'University of Example' ) ) ) );
ck( 'and the records of those two, in that order', $built['records'], array( 'recINST0000000002', 'recINST0000000001' ) );
ck( 'two matches say no more', $a['more'], false );
ck( '"UNIV" finds the same two', array_column( plain( build( 'institutions', array( 'institution' => 'UNIV' ) )['answer'] )['matches'], 'record_id' ), array( 'recINST0000000002', 'recINST0000000001' ) );
ck( 'a name is found in the middle too', array_column( plain( build( 'institutions', array( 'institution' => 'nstitute of tech' ) )['answer'] )['matches'], 'record_id' ), array( 'recINST0000000003' ) );
$built = build( 'institutions', array( 'institution' => 'zzz' ) );
ck( '"zzz" finds nothing: matches empty (an empty list), more false, no records', array( json_encode( $built['answer']['matches'] ), $built['answer']['more'], $built['records'] ), array( '[]', false, array() ) );
ck( 'and still says when the roster was read, and the institutions', array( $built['answer']['read_at'], $built['answer']['institutions_read_at'] ), array( '2026-10-24T03:00:00+00:00', '2026-10-24T01:00:00+00:00' ) );
readers_reset();
$GLOBALS['readers']['institutions']['read'] = 0;
$built = build( 'institutions', array( 'institution' => 'recINST0000000001' ) );
ck( 'an institutions index never read says null for its time (the key is there, not left out), and the roster\'s time is still the roster\'s', array( array_key_exists( 'institutions_read_at', $built['answer'] ), $built['answer']['institutions_read_at'], $built['answer']['read_at'], count( $built['answer']['matches'] ) ), array( true, null, '2026-10-24T03:00:00+00:00', 1 ) );
readers_reset();
$GLOBALS['readers']['roster_read'] = 0;
$built = build( 'institutions', array( 'institution' => 'recINST0000000001' ) );
ck( 'a roster never read says null for its time (the key is there, not left out), and the institutions\' time is still theirs', array( array_key_exists( 'read_at', $built['answer'] ), $built['answer']['read_at'], $built['answer']['institutions_read_at'] ), array( true, null, '2026-10-24T01:00:00+00:00' ) );
readers_reset();
ck( 'two characters are too few: nothing', build( 'institutions', array( 'institution' => 'un' ) )['answer']['matches'], array() );
ck( 'three characters are enough', count( build( 'institutions', array( 'institution' => 'uni' ) )['answer']['matches'] ), 2 );
ck( 'two characters and white space are still two', build( 'institutions', array( 'institution' => "  u n  " ) )['answer']['matches'], array() );

readers_reset();
$template = $GLOBALS['readers']['institutions']['rows']['recINST0000000002'];
$GLOBALS['readers']['institutions']['rows'] = many_universities( 6, $template );
$built = build( 'institutions', array( 'institution' => 'univ' ) );
ck( 'six names hold it: five matches, and more', array( count( $built['answer']['matches'] ), $built['answer']['more'], count( $built['records'] ) ), array( 5, true, 5 ) );
ck( 'the five are the first five by name', array_column( $built['answer']['matches'], 'name' ), array( 'Univ Number 01', 'Univ Number 02', 'Univ Number 03', 'Univ Number 04', 'Univ Number 05' ) );
$GLOBALS['readers']['institutions']['rows'] = many_universities( 5, $template );
$built = build( 'institutions', array( 'institution' => 'univ' ) );
ck( 'five names hold it: five matches, and no more', array( count( $built['answer']['matches'] ), $built['answer']['more'] ), array( 5, false ) );

// The order is the dashboards' own (`WPCPM_Dashboards::compare_names()`): an accent does not send a name
// after Z, as a comparison byte by byte would.
$GLOBALS['readers']['institutions']['rows'] = array(
	'recINST0000000071' => array_merge( $template, array( 'name' => 'Zeta Example College' ) ),
	'recINST0000000072' => array_merge( $template, array( 'name' => 'Łódź Example Academy' ) ),
	'recINST0000000073' => array_merge( $template, array( 'name' => 'Émile Example School' ) ),
);
$built = build( 'institutions', array( 'institution' => 'example' ) );
ck( 'accented names sort among their letters, as the dashboards sort them: E, L, Z', array_column( $built['answer']['matches'], 'name' ), array( 'Émile Example School', 'Łódź Example Academy', 'Zeta Example College' ) );

readers_reset();
$built = build( 'institutions', array( 'institution' => 'recINST0000000001', 'cohort' => '2026-H2' ) );
ck( 'a semester narrows the students\' groups, and the answer says which', array( $built['answer']['matches'][0]['students'], groups_asked() ), array( array( 'current' => 5, 'waiting' => 1, 'finished' => 0, 'not_started' => 0, 'cohort' => '2026-H2' ), array( 'recINST0000000001', '2026-H2' ) ) );
foreach ( array( 'text that is no semester' => 'last year', 'a half that does not exist' => '2026-H3', 'the "none" bucket' => 'none', 'a number' => 2026, 'a list' => array( '2026-H2' ), 'an empty string' => '', 'a semester with white space' => '2026-H2 ', 'a semester with a trailing newline, which the schema\'s pattern lets through' => "2026-H2\n" ) as $label => $cohort ) {
	$GLOBALS['reader_calls'] = array();
	$built = build( 'institutions', array( 'institution' => 'recINST0000000001', 'cohort' => $cohort ) );
	ck( "$label as the semester: ignored, every semester counted", array( groups_asked(), $built['answer']['matches'][0]['students']['cohort'], $built['answer']['matches'][0]['students']['current'] ), array( array( 'recINST0000000001', '' ), null, 7 ) );
}
$built = build( 'institutions', array( 'institution' => 'recINST0000000003' ) );
ck( 'an institution with no students and no members has zeros', array( $built['answer']['matches'][0]['students'], $built['answer']['matches'][0]['members'] ), array( array( 'current' => 0, 'waiting' => 0, 'finished' => 0, 'not_started' => 0, 'cohort' => null ), 0 ) );
ck( 'no country name falls back to the country the record holds, and an empty city is empty', array( $built['answer']['matches'][0]['country'], build( 'institutions', array( 'institution' => 'recINST0000000002' ) )['answer']['matches'][0]['city'] ), array( 'XF', '' ) );
ck( 'an agreement not yet accepted: empty dates, no document', plain( $built['answer'] )['matches'][0]['agreement'], array( 'status' => 'Submitted', 'kind' => 'Standard', 'submitted_on' => '2026-09-30', 'accepted_on' => '', 'signed_on' => '', 'has_document' => false ) );
ck( 'a record with no stage is in the stage "No stage", as the stages are counted', build( 'institutions', array( 'institution' => 'recINST0000000005' ) )['answer']['matches'][0]['stage'], 'No stage' );

// "No stage" is a key of the answer's data, not words for a person: it reads the same whatever language
// the account that asks reads the site in.
$GLOBALS['translations'] = array( 'No stage' => 'Sin etapa' );
ck( 'with "No stage" translated, a match with no stage still says "No stage"', build( 'institutions', array( 'institution' => 'recINST0000000005' ) )['answer']['matches'][0]['stage'], 'No stage' );
ck( 'and so does the key it is counted under', array_key_exists( 'No stage', (array) build( 'program_counts' )['answer']['institutions']['by_stage'] ), true );
$GLOBALS['translations'] = array();

echo "\n--- One institution: nothing of a person, and typed text on one line (Review Focus 4) ---\n";
$built = build( 'institutions', array( 'institution' => 'recINST0000000001' ) );
$json  = json_of( $built );
ck( 'neither the contact person, nor the contact\'s address, nor who accepted the agreement is in the JSON', array( false !== strpos( $json, 'Contact Person Zeta' ), false !== strpos( $json, 'contact-zeta' ), false !== strpos( $json, 'Acceptor Name Omega' ) ), array( false, false, false ) );
ck( 'nor are the fields that hold them, nor any address', array( false !== strpos( $json, 'contact' ), false !== strpos( $json, 'accepted_by' ), false !== strpos( $json, '@' ) ), array( false, false, false ) );
ck( 'nor a member: no login, no name, no address, only the count', array( false !== strpos( $json, 'member_login' ), false !== strpos( $json, 'Member Name' ), false !== strpos( $json, 'member-one' ) ), array( false, false, false ) );
ck( 'nor a student, nor a mentor', array( false !== strpos( $json, 'Student Name' ), false !== strpos( $json, 'student_sigma' ), false !== strpos( $json, 'Mentor Name Tau' ) ), array( false, false, false ) );
ck( 'nor the website, the consent or the template version', array( false !== strpos( $json, 'website' ), false !== strpos( $json, 'consent' ), false !== strpos( $json, 'template' ), false !== strpos( $json, 'example.test' ) ), array( false, false, false, false ) );

$built = build( 'institutions', array( 'institution' => 'recINST0000000004' ) );
$m     = $built['answer']['matches'][0];
ck( 'a name with a newline and 500 characters is one line, cut at 120', array( $m['name'] === WPCPM_Abilities::text( $GLOBALS['readers']['institutions']['rows']['recINST0000000004']['name'] ), mb_strlen( $m['name'] ), false !== strpos( $m['name'], "\n" ), 0 === strpos( $m['name'], 'Long Name xxx' ) ), array( true, 120, false, true ) );
ck( 'a stage with a zero-width character arrives clean, and a city with a tab', array( $m['stage'], $m['city'] ), array( 'Info Sent', 'Odd City' ) );
ck( 'the JSON holds no zero-width space and no escaped newline', array( false !== strpos( json_encode( $built ), 'u200b' ), false !== strpos( json_encode( $built ), '\\n' ), false !== strpos( json_encode( $built ), '\\t' ) ), array( false, false, false ) );
ck( 'the name is found by the words a person would type, newline and all', array_column( build( 'institutions', array( 'institution' => "long\nname" ) )['answer']['matches'], 'record_id' ), array( 'recINST0000000004' ) );
$built = build( 'institutions', array( 'institution' => str_repeat( 'x', 500 ) ) );
ck( 'a search of 500 characters is cut like any typed text, and finds nothing, in order', array( count( $built['answer']['matches'] ), $built['answer']['more'] ), array( 0, false ) );
$json = json_of( build( 'institutions', array( 'institution' => 'recINST0000000001' ) ) );
ck( 'no raw control character in the JSON of an answer', 0, preg_match( '/[\x00-\x1f]/', $json ) );

echo "\n--- One institution: what it cannot read is an empty answer ---\n";
foreach ( array( 'no input' => null, 'text' => 'univ', 'an empty list' => array(), 'a number' => 12, 'no institution' => array( 'cohort' => '2026-H2' ), 'an institution that is not text' => array( 'institution' => 12 ), 'a list for an institution' => array( 'institution' => array( 'univ' ) ), 'an empty institution' => array( 'institution' => '' ) ) as $label => $input ) {
	$built = build( 'institutions', $input );
	ck( "$label: matches empty, more false, no records, the roster's read time and the institutions'", array( $built['answer']['matches'], $built['answer']['more'], $built['records'], $built['answer']['read_at'], $built['answer']['institutions_read_at'] ), array( array(), false, array(), '2026-10-24T03:00:00+00:00', '2026-10-24T01:00:00+00:00' ) );
}

echo "\n=== No person anywhere ===\n";
readers_reset();
$builds = array(
	'program-counts'                     => build( 'program_counts' ),
	'waiting-for-decision'               => build( 'waiting' ),
	'mentor-engagement-summary'          => build( 'engagement' ),
	'mentor-engagement-summary, a day'   => build( 'engagement', array( 'as_of' => '2026-03-15' ) ),
	'institution-summary, by record ID'  => build( 'institutions', array( 'institution' => 'recINST0000000001' ) ),
	'institution-summary, by name'       => build( 'institutions', array( 'institution' => 'univ', 'cohort' => '2026-H2' ) ),
	'institution-summary, three matches' => build( 'institutions', array( 'institution' => 'ample' ) ),
);
foreach ( $builds as $label => $built ) {
	$json  = json_of( $built );
	$found = array();
	foreach ( readers_people() as $person ) {
		if ( false !== strpos( $json, $person ) ) { $found[] = $person; }
	}
	ck( "$label: none of the fixtures' names, addresses or logins is in the JSON, and no address at all", array( $found, false !== strpos( $json, '@' ) ), array( array(), false ) );
}
ck( 'the search for "ample" found three names, by name', array_column( $builds['institution-summary, three matches']['answer']['matches'], 'name' ), array( 'Example Institute of Technology', 'Northern UNIVERSITY of Samples', 'University of Example' ) );

echo "\n=== A builder never returns an error ===\n";
foreach ( array( 'program_counts', 'waiting', 'engagement', 'institutions' ) as $method ) {
	foreach ( array( 'no input' => null, 'an empty list' => array(), 'text' => 'x', 'a number' => 7 ) as $label => $input ) {
		$built = build( $method, $input );
		ck( "$method, $label: an array holding an array answer and an array of records", array( is_array( $built ), is_array( $built['answer'] ), is_array( $built['records'] ) ), array( true, true, true ) );
	}
}

echo "\n=== House rules ===\n";
$path   = WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ability-answers.php';
$source = (string) file_get_contents( $path );
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', $source ), 0 );
ck( 'the class calls no outside address and no Airtable', preg_match( '/wp_remote_|WPCPM_Airtable|file_get_contents\s*\(\s*[\'"]https?:|curl_/i', $source ), 0 );
ck( 'and writes no option, setting, post or meta', preg_match( '/update_option|add_option|delete_option|WPCPM_Settings::|wp_insert_post|update_post_meta|update_user_meta|delete_/', $source ), 0 );
$strings = array();
foreach ( token_get_all( $source ) as $token ) {
	if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) { $strings[] = trim( $token[1], "'\"" ); }
}
$never = array( 'contact_person', 'contact_email', 'accepted_by', 'progress', 'note', 'screen', 'user_email', 'user_login', 'display_name', 'email', 'username', 'mentor_name' );
ck( 'the code never so much as names a field that holds a person (the comments may)', array_values( array_intersect( $strings, $never ) ), array() );
ck( 'every short text an answer carries goes through text()', substr_count( $source, 'WPCPM_Abilities::text(' ) >= 8, true );

printf( "\n%s\n", $fail ? "$fail FAILED" : 'ALL PASS' );
exit( $fail ? 1 : 0 );
