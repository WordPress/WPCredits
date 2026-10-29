<?php
/**
 * The answers (Phase A's four, 1.123.30, and Phase B's lookups of people): what `WPCPM_Ability_Answers`
 * builds from the readers the plugin already has.
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
 * - **A student's tracks are found by address, username, profile address, record ID or part of a name,**
 *   one entry per person (the rows sharing an address key), every track in start order, a `Duplicated` or
 *   `SPAM` row never (Review Focus 1); at most ten people, an eleventh says `more`, and two characters are
 *   refused by the input schema (Review Focus 2); an institution narrows to its roster.
 * - **A mentor's students are found by the Mentors record ID or by the lookup `wp wpcredits mentor` makes**
 *   (a login, an address, a WordPress.org profile address), and only a mentor: an account the Engagement
 *   tab does not list is nobody (Review Focus 3). Found, a mentor with no students is an empty list and a
 *   mentor account with no Mentors record ID has `record_id` null with its students listed. The students
 *   are the Mentor Report Card's own list, the current before the past, each by the Students record ID
 *   `wpcpm/find-student` gives for its track (the one rule for which track holds a report row is shared by
 *   the two), with their status, the hours the card prints and start, and no latest report's month, as
 *   the site keeps no date for a report; the
 *   mentor's last sign-in and visit are dates; the Engagement view is one of the tab's.
 * - **Mentor applications are listed by their reference, never by a name.** One state at a time (`open`: new,
 *   held, more information asked for and a second opinion; `selected`; `lapsed`), newest received first, at
 *   most the limit asked (25 by default) with `more` when cut. A row holds the day received, the state, the
 *   days in it (counted from the history's row for the move into the state), the country, the
 *   languages (only those on the form's fixed list of them), the hours a week, the contribution areas, the teacher answer (a yes or no, never text),
 *   whether the address is confirmed and the duplicate and held flags. `spam`, `declined` and `active` rows
 *   are never listed, whatever the query returns. The totals are the feed's (open, selected) and the lapsed
 *   count, which the build takes from the feed's `today()` and not from its `build()`, so a lookup does not walk
 *   every application's history. The answers the form keeps of the applicant (name, address, profile, the
 *   free-text answer), the history's notes, the guard's signals and the consent evidence never leave: the
 *   build is given the answers and the signals whole and takes five answers and one signal by key. The suite
 *   says which post meta is read (state, answers, reference, confirmed, signals and the history's times), on
 *   the real class, and where an Administrator finds the person behind a record ID or a reference.
 * - **No person anywhere.** For every answer the JSON holds none of the names, addresses or logins the
 *   fixtures carry on every row the readers return. The people walk (`people_walk()`, Review Focus 5) is
 *   the one check every lookup of people runs: the fixtures put every field that holds a person on every
 *   roster row and every mentor card, and no answer holds any of them, in any case, whatever was asked.
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
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
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

/**
 * An answer's build, from a clean slate of fixtures unless told otherwise. The account meta a
 * `wpcpm/find-student` build reads is kept, key by key, in `$GLOBALS['students_meta_read']`, and a
 * `wpcpm/mentor-students` build's in `$GLOBALS['mentor_meta_read']`; the post meta a
 * `wpcpm/mentor-applications` build reads is kept in `$GLOBALS['applications_meta_read']`, and the meta keys
 * its queries select by in `$GLOBALS['applications_queried']`.
 */
function build( $method, $input = null ) {
	$from  = count( $GLOBALS['reader_calls'] );
	$built = call_user_func( array( 'WPCPM_Ability_Answers', $method ), $input );

	if ( 'students' === $method || 'mentor' === $method ) {
		foreach ( array_slice( $GLOBALS['reader_calls'], $from ) as $call ) {
			if ( 'user_meta' === $call[0] ) { $GLOBALS[ $method . '_meta_read' ][] = $call[2]; }
		}
	}

	if ( 'applications' === $method ) {
		foreach ( array_slice( $GLOBALS['reader_calls'], $from ) as $call ) {
			if ( 'post_meta' === $call[0] ) { $GLOBALS['applications_meta_read'][] = $call[2]; }
			if ( 'post_query' === $call[0] ) { $GLOBALS['applications_queried'][] = $call[1]; }
		}
	}

	return $built;
}
$GLOBALS['reader_calls']       = array();
$GLOBALS['students_meta_read'] = array();
$GLOBALS['mentor_meta_read']   = array();
$GLOBALS['applications_meta_read'] = array();
$GLOBALS['applications_queried']   = array();

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

/*
 * ---- The people walk (Phase B, Review Focus 5) ------------------------------------------------------
 *
 * One walk for every answer that looks people up: `wpcpm/find-student` here, and the mentor's students and
 * the mentor applications after it. A fixture person is the fields below, and the walk fails when the JSON
 * of an answer holds any of them, in any case.
 */

/**
 * The fields that hold a person, as the people walk asks a fixture person for them: everything a roster
 * row, a student's account or a mentor card holds about a person. The suite checks that every key of the
 * real roster row and mentor card is one an answer may carry or one of these.
 */
const PEOPLE_FIELDS = array( 'name', 'display_name', 'login', 'nicename', 'email', 'email_key', 'username', 'profile', 'slack', 'github', 'avatar', 'jobline', 'location', 'website', 'website_label', 'tutor', 'mentor_name', 'field_of_study', 'import_key', 'accessibility' );

/**
 * What the people walk finds in an answer: every field of the people given that the answer's JSON holds,
 * in any case, read as json_encode() writes it and again with slashes and letters unescaped, so a profile
 * address or an accented name is found either way; "@" when the JSON holds an address sign at all, as no
 * answer carries an address; and a line for every person given without one of the ten fields, so a suite
 * cannot walk with half a person. A field that is '' (a person with no address, a mentor with no Slack
 * handle) is not looked for, so a suite whose people lack a field passes '' for it.
 *
 * @param mixed   $answer An answer, or a whole build.
 * @param array[] $people The fixture people, each with every key of PEOPLE_FIELDS.
 * @return string[] What was found, empty when the answer names nobody.
 */
function people_found( $answer, array $people ) {
	$texts = array( (string) json_encode( $answer ), (string) json_encode( $answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	$found = array();

	foreach ( $people as $key => $person ) {
		foreach ( PEOPLE_FIELDS as $field ) {
			if ( ! is_array( $person ) || ! array_key_exists( $field, $person ) ) {
				$found[] = "the person $key was given without a $field";
				continue;
			}

			$value = (string) $person[ $field ];

			if ( '' === $value ) {
				continue;
			}

			foreach ( $texts as $text ) {
				if ( false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $text, $value, 0, 'UTF-8' ) : stripos( $text, $value ) ) ) {
					$found[] = $value;
					break;
				}
			}
		}
	}

	if ( false !== strpos( $texts[1], '@' ) ) {
		$found[] = '@';
	}

	return array_values( array_unique( $found ) );
}

/**
 * The people walk as one check: it fails when the JSON of an answer holds any field of PEOPLE_FIELDS of the
 * people given (a name, display name, login or nicename, an address or its key, a WordPress.org username or
 * profile address, a Slack or GitHub handle, an avatar, jobline, location, website or its label, a tutor's
 * or mentor's name, a field of study, an import key or an accessibility answer), in any case, or any
 * address sign. Walk every input form an ability takes.
 *
 * @param string  $label  What was asked, for the check's label.
 * @param mixed   $answer The answer, or the whole build.
 * @param array[] $people The fixture people, each with every key of PEOPLE_FIELDS.
 * @return string[] What was found.
 */
function people_walk( $label, $answer, array $people ) {
	$found = people_found( $answer, $people );

	ck( "$label: the JSON names none of the people, in any case, and holds no address", $found, array() );

	return $found;
}

/** Each person of a `wpcpm/find-student` build as the record IDs of their tracks, in the answer's order. */
function tracks_of( $built ) {
	return array_map(
		static function ( $person ) {
			return array_column( $person['tracks'], 'record_id' );
		},
		plain( $built['answer'] )['people']
	);
}

/** One track of a `wpcpm/find-student` build by its record ID, as plain arrays, or null. */
function track_of( $built, $record_id ) {
	foreach ( plain( $built['answer'] )['people'] as $person ) {
		foreach ( $person['tracks'] as $track ) {
			if ( $record_id === $track['record_id'] ) { return $track; }
		}
	}

	return null;
}

/**
 * $count more people on the second roster, each one track, whose names hold "Many Pupil": the higher the
 * number, the later the start. Each carries every field of a person, its own, and account 600 + its number.
 *
 * @param int $count How many.
 * @return array[] The people, for the walk.
 */
function many_pupils( $count ) {
	$people = array();

	for ( $n = 1; $n <= $count; $n++ ) {
		$id     = sprintf( 'recMANYP%09d', $n );
		$person = array(
			'name'         => sprintf( 'Many Pupil %02d', $n ),
			'display_name' => sprintf( 'Many Display %02d', $n ),
			'login'        => sprintf( 'many_login_%02d', $n ),
			'email'        => sprintf( 'Many-Pupil-%02d@example.test', $n ),
			'email_key'    => sprintf( 'many-pupil-%02d@example.test', $n ),
			'username'     => sprintf( 'many-pupil-%02d-example', $n ),
			'profile'      => 'https://profiles.wordpress.org/' . sprintf( 'many-pupil-%02d-example', $n ) . '/',
			'tutor'        => sprintf( 'Tutor Many %02d', $n ),
			'mentor_name'  => sprintf( 'Mentor Many %02d', $n ),
			'website'      => sprintf( 'https://many-%02d.example.test/', $n ),
		);
		foreach ( array( 'nicename', 'slack', 'github', 'avatar', 'jobline', 'location', 'website_label', 'field_of_study', 'import_key', 'accessibility' ) as $field ) {
			$person[ $field ] = sprintf( 'many %s %02d', str_replace( '_', ' ', $field ), $n );
		}

		$GLOBALS['readers']['roster']['recINST0000000002'][ $id ] = array_merge(
			readers_track( 'alpha', $id, 'recINST0000000002', array( 'status' => 'In Sensei', 'start' => sprintf( '2025-01-%02d', $n ), 'user_id' => 600 + $n ) ),
			$person
		);
		$people[ "many $n" ] = $person;
	}

	return $people;
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
	'includes/modules/class-wpcpm-mentor-engagement.php'        => array( 'WPCPM_Mentor_Engagement', array( 'report', 'views', 'in_view' ) ),
	'includes/modules/class-wpcpm-mentors-dashboard.php'        => array( 'WPCPM_Mentors_Dashboard', array( 'find_mentor', 'get_mentees', 'logged_count' ) ),
	'includes/class-wpcpm-roles.php'                            => array( 'WPCPM_Roles', array( 'id_of' ) ),
	'includes/modules/class-wpcpm-institutions-index.php'       => array( 'WPCPM_Institutions_Index', array( 'read', 'rows', 'row', 'by_stage' ) ),
	'includes/class-wpcpm-roster-index.php'                     => array( 'WPCPM_Roster_Index', array( 'read', 'rows', 'unlinked', 'counts', 'groups' ) ),
	'includes/modules/class-wpcpm-institution-members.php'      => array( 'WPCPM_Institution_Members', array( 'members_of' ) ),
	'includes/modules/class-wpcpm-sponsors-index.php'           => array( 'WPCPM_Sponsors_Index', array( 'rows', 'is_test' ) ),
	'includes/modules/class-wpcpm-mentor-application-feed.php'  => array( 'WPCPM_Mentor_Application_Feed', array( 'build', 'today' ) ),
	'includes/modules/class-wpcpm-mentor-application.php'       => array( 'WPCPM_Mentor_Application', array( 'states', 'open_states', 'applications', 'count_in', 'state_of', 'fields_of', 'reference_of', 'is_confirmed', 'signals_of', 'events', 'last_at', 'decided_at' ) ),
	'includes/class-wpcpm-outcome-figures.php'                  => array( 'WPCPM_Outcome_Figures', array( 'published' ) ),
	'includes/class-wpcpm-cohort.php'                           => array( 'WPCPM_Cohort', array( 'is_key' ) ),
	'includes/modules/class-wpcpm-mentors-sync.php'             => array( 'WPCPM_Mentors_Sync', array( 'is_record_id', 'wporg_username', 'typed_username' ) ),
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

// What `wpcpm/find-student` reads off the roster and the accounts, on the real classes.
readers_reset();
$roster_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php' );
ck( 'the roster still never shows SPAM and Duplicated, as the stand-in says', array( 0 < preg_match( "/const NEVER_SHOWN = array\( 'SPAM', 'Duplicated' \);/", $roster_source ), WPCPM_Roster_Index::NEVER_SHOWN ), array( true, array( 'SPAM', 'Duplicated' ) ) );
preg_match( '/const KEYS = array\((.*?)\n\t\);/s', $roster_source, $roster_keys );
preg_match_all( "/^\t\t'([a-z_]+)',/m", isset( $roster_keys[1] ) ? $roster_keys[1] : '', $roster_keys );
$missing = array();
foreach ( array_merge( array_values( $GLOBALS['readers']['roster'] ), array( $GLOBALS['readers']['unlinked'] ) ) as $roster_rows ) {
	foreach ( $roster_rows as $record => $row ) {
		foreach ( array_diff( array_merge( $roster_keys[1], PEOPLE_FIELDS ), array_keys( $row ) ) as $key ) { $missing[] = "$record $key"; }
	}
}
ck( 'and every key a roster row holds, and every field of a person the walk looks for besides, is on every fixture row', $missing, array() );
// What a row may give an answer, or holds for the site alone (the account's ID, when the site last wrote it).
$row_allowed = array( 'record_id', 'status', 'institution', 'start', 'end', 'has_mentor', 'team', 'hours', 'reports', 'user_id', 'touched' );
ck( 'every key of a real roster row is one an answer may carry, one the site keeps for itself, or a field the people walk looks for', array_values( array_diff( $roster_keys[1], $row_allowed, PEOPLE_FIELDS ) ), array() );
ck( 'the roster\'s keys hold the person fields the fixtures carry', array_values( array_diff( array( 'name', 'email', 'email_key', 'username', 'tutor', 'mentor_name', 'website', 'has_mentor', 'team', 'hours', 'reports', 'user_id' ), $roster_keys[1] ) ), array() );
$students_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php' );
ck( 'the students sync still keeps the mentor card and the waiting course under the keys the stand-in names', array(
	0 < preg_match( "/const META_MENTOR\s*= 'wpcpm_student_mentor';/", $students_source ),
	0 < preg_match( "/const META_COURSE = 'wpcpm_student_course';/", $students_source ),
	WPCPM_Students_Sync::META_MENTOR,
	WPCPM_Students_Sync::META_COURSE,
), array( true, true, 'wpcpm_student_mentor', 'wpcpm_student_course' ) );
ck( 'and the mentor card it writes still carries the Mentors record ID first', 0 < preg_match( "/\\\$card = array\(\s*'record_id'\s*=> \\\$record_id,/", $students_source ), true );
preg_match( '/\$card = array\((.*?)\n\t\t\t\);/s', $students_source, $card_block );
preg_match_all( "/'([a-z_]+)'\s*=>/", isset( $card_block[1] ) ? $card_block[1] : '', $card_keys );
ck( 'every key of the real mentor card but its record ID and its teams is a field the people walk looks for', array( count( $card_keys[1] ) > 10, array_values( array_diff( $card_keys[1], array( 'record_id', 'teams' ), PEOPLE_FIELDS ) ) ), array( true, array() ) );
ck( 'and the fixture card carries every key the real one does', array_values( array_diff( $card_keys[1], array_keys( readers_mentor_card( 'recMENT0000000001' ) ) ) ), array() );
ck( 'the account follows one Students Reports record, written in the same pass as its mentor card, and only by the report row that governs the address', array(
	0 < preg_match( "/const META_RECORD_ID = 'wpcpm_student_record_id';/", $students_source ),
	WPCPM_Students_Sync::META_RECORD_ID,
	0 < preg_match( "/\\\$state\['students'\]\[\] = array\(\s*'record_id'\s*=> isset\( \\\$record\['id'\] \)/", $students_source ),
	0 < preg_match( "/update_user_meta\( \\\$user_id, self::META_RECORD_ID, \\\$student\['record_id'\] \);/", $students_source ),
	0 < preg_match( "/update_user_meta\( \\\$user_id, self::META_MENTOR, wp_slash\( \\\$mentor \) \);/", $students_source ),
	0 < preg_match( "/if \( ! self::governs\( \\\$student, \\\$state \) \) \{\s*return 0;/", $students_source ),
), array( true, 'wpcpm_student_record_id', true, true, true, true ) );
ck( 'waiting to graduate is still Pending graduation', array( 0 < preg_match( "/const STATUS_PENDING = 'Pending graduation';/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php' ) ), WPCPM_Program::STATUS_PENDING ), array( true, 'Pending graduation' ) );
$username_body = static function ( $file ) {
	preg_match( '/public static function wporg_username\( \$raw \) \{\n(.*?)\n\t\}\n/s', (string) file_get_contents( $file ), $body );
	return isset( $body[1] ) ? $body[1] : '';
};
$course_body = static function ( $file ) {
	preg_match( '/public static function course_status\( \$status, \$user_id = 0 \) \{\n(.*?)\n\t\}\n/s', (string) file_get_contents( $file ), $body );
	return isset( $body[1] ) ? $body[1] : '';
};
ck( 'the stand-in\'s course_status() is the real one, line for line, and the real class still has is_track()', array( '' !== $course_body( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php' ), $course_body( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php' ) === $course_body( __DIR__ . '/stubs/ability-readers.php' ), 0 < preg_match( '/^\tpublic static function is_track\(/m', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php' ) ) ), array( true, true, true ) );
ck( 'the stand-in\'s wporg_username() is the real one, line for line', array( '' !== $username_body( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' ), $username_body( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' ) === $username_body( __DIR__ . '/stubs/ability-readers.php' ) ), array( true, true ) );

// What `wpcpm/mentor-students` reads: the lookup of a mentor, the list of students, the Engagement tab's views
// and rule, and the keys the mentors sync keeps, each on the real class.
$method_body = static function ( $file, $method ) {
	preg_match( '/\n\t(?:public|private) static function ' . preg_quote( $method, '/' ) . '\([^)]*\) \{\n(.*?)\n\t\}\n/s', (string) file_get_contents( $file ), $body );
	return isset( $body[1] ) ? $body[1] : '';
};
$stub_file = __DIR__ . '/stubs/ability-readers.php';
foreach ( array(
	'find_mentor' => 'includes/modules/class-wpcpm-mentors-dashboard.php',
	'get_mentees' => 'includes/modules/class-wpcpm-mentors-dashboard.php',
	'logged_count' => 'includes/modules/class-wpcpm-mentors-dashboard.php',
	'countries'   => 'includes/modules/class-wpcpm-mentor-application-form.php',
	'teams'       => 'includes/modules/class-wpcpm-mentor-application-form.php',
	'typed_username' => 'includes/modules/class-wpcpm-mentors-sync.php',
	'user_for_record' => 'includes/modules/class-wpcpm-students-sync.php',
	'find_by_record_id' => 'includes/modules/class-wpcpm-students-sync.php',
	'views'       => 'includes/modules/class-wpcpm-mentor-engagement.php',
	'in_view'     => 'includes/modules/class-wpcpm-mentor-engagement.php',
) as $method => $file ) {
	ck( "the stand-in's $method() is the real one, line for line", array( '' !== $method_body( WPCPM_PLUGIN_DIR . $file, $method ), $method_body( $stub_file, $method ) === $method_body( WPCPM_PLUGIN_DIR . $file, $method ) ), array( true, true ) );
}
$sync_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' );
$sync_keys   = array();
foreach ( array( 'META_RECORD_ID', 'META_ACTIVE', 'META_MENTEES', 'META_UPDATED' ) as $constant ) {
	$sync_keys[ $constant ] = preg_match( '/const ' . $constant . '\s*=\s*\'([a-z_]+)\';/', $sync_source, $found_key ) ? $found_key[1] : null;
}
ck( 'the mentors sync still keeps the Mentors record ID, the active flag, the students and when they were written under the keys the stand-in names',
	array( $sync_keys, array( WPCPM_Mentors_Sync::META_RECORD_ID, WPCPM_Mentors_Sync::META_ACTIVE, WPCPM_Mentors_Sync::META_MENTEES, WPCPM_Mentors_Sync::META_UPDATED ) ),
	array( array( 'META_RECORD_ID' => 'wpcpm_mentor_record_id', 'META_ACTIVE' => 'wpcpm_mentor_active', 'META_MENTEES' => 'wpcpm_mentees', 'META_UPDATED' => 'wpcpm_mentees_updated' ), array( 'wpcpm_mentor_record_id', 'wpcpm_mentor_active', 'wpcpm_mentees', 'wpcpm_mentees_updated' ) ) );
$engagement_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php' );
preg_match_all( '/const (VIEW_[A-Z_]+)\s*=\s*\'([a-z-]+)\';/', $engagement_source, $views_found );
ck( 'the Engagement tab\'s eight views are the stand-in\'s, constant for constant, and views() lists them in the tab\'s order', array( array_combine( $views_found[1], $views_found[2] ), array_keys( WPCPM_Mentor_Engagement::views() ) ), array( array( 'VIEW_ALL' => 'all', 'VIEW_ACTIVE' => 'active', 'VIEW_STUDENTS' => 'students', 'VIEW_NEVER' => 'never', 'VIEW_QUIET' => 'quiet', 'VIEW_IDLE' => 'idle', 'VIEW_NO_HOURS' => 'no-hours', 'VIEW_STAFF' => 'staff' ), array( 'all', 'active', 'students', 'never', 'quiet', 'idle', 'no-hours', 'staff' ) ) );
ck( 'and the stand-in holds the same constants', array_filter( (new ReflectionClass( 'WPCPM_Mentor_Engagement' ))->getConstants(), static function ( $key ) { return 0 === strpos( $key, 'VIEW_' ); }, ARRAY_FILTER_USE_KEY ), array_combine( $views_found[1], $views_found[2] ) );

// A row of the Engagement tab's report, on the real class: the keys the stand-in's rows hold, and what each is for.
preg_match( '/private static function row\(.*?\$row = array\((.*?)\n\t\t\);/s', $engagement_source, $row_block );
preg_match_all( "/^\t\t\t'([a-z_0-9]+)'\s*=>/m", isset( $row_block[1] ) ? $row_block[1] : '', $row_keys );
// `acted` is set once the notes and calls are counted, after the row is made.
preg_match_all( "/^\t\t\\\$row\['([a-z_0-9]+)'\]\s*=[^=]/m", $engagement_source, $row_keys_after );
$row_keys[1] = array_values( array_unique( array_merge( $row_keys[1], array_intersect( $row_keys_after[1], array( 'acted' ) ) ) ) );
$row_read   = array( 'id', 'active', 'staff', 'students_now', 'last_sign_in', 'last_visit', 'last_seen', 'seen_30', 'acted', 'weekly_hours' );
$row_counts = array( 'students_past', 'seen_7', 'schedule_saved', 'meeting_link', 'notes', 'notes_30', 'last_note', 'calls_booked', 'calls_held', 'calls_upcoming', 'calls_noted', 'cancelled_by_mentor', 'sessions_created', 'sessions_held', 'sessions_upcoming', 'sessions_noted' );
ck( 'every key of a real Engagement row is one the mentor answer reads, a count of the mentor\'s work it never reads, or a field the people walk looks for', array( count( $row_keys[1] ) > 25, array_values( array_diff( $row_keys[1], $row_read, $row_counts, PEOPLE_FIELDS ) ) ), array( true, array() ) );
ck( 'and the stand-in\'s row holds every key the real one does, and no other', array( array_values( array_diff( $row_keys[1], array_keys( readers_engagement_row( 1, readers_mentors()['gamma'] ) ) ) ), array_values( array_diff( array_keys( readers_engagement_row( 1, readers_mentors()['gamma'] ) ), $row_keys[1] ) ) ), array( array(), array() ) );

// A student on a mentor's list, on the mentors sync: the keys of the row, and what each is for.
preg_match_all( '/\$row = array\((.*?)\n\t\t\t\);/s', $sync_source, $row_blocks );
$mentee_block = '';
foreach ( $row_blocks[1] as $block ) {
	if ( false !== strpos( $block, "'mentor_ids'" ) ) { $mentee_block = $block; }
}
preg_match_all( "/^\t\t\t\t'([a-z_]+)'\s*=>/m", $mentee_block, $mentee_keys );
$mentee_read  = array( 'record_id', 'status', 'is_past', 'start', 'hours' );
$mentee_kept  = array( 'end', 'institution', 'team', 'link', 'mentor_ids' );
ck( 'every key of a real row on a mentor\'s list is one the mentor answer reads, one the site keeps for itself, or a field the people walk looks for', array( count( $mentee_keys[1] ) > 15, array_values( array_diff( $mentee_keys[1], $mentee_read, $mentee_kept, PEOPLE_FIELDS ) ) ), array( true, array() ) );
ck( 'and the stand-in\'s student row holds every key the real one does, and every field of a person the walk looks for', array( array_values( array_diff( array_merge( $mentee_keys[1], PEOPLE_FIELDS ), array_keys( readers_mentee( 'alpha', 'recREPT0000000001' ) ) ) ) ), array( array() ) );
ck( 'the mentors sync still orders a list by end date, so the Mentor Report Card\'s current and past students are one list this answer splits', array( 0 < preg_match( '/usort\( \$rows, array\( __CLASS__, \'compare_mentees\' \) \);/', $sync_source ), 0 < preg_match( "/'is_past'\s*=> in_array\( \\\$status, \\\$statuses\['past'\], true \)/", $sync_source ) ), array( true, true ) );

// What `wpcpm/mentor-applications` reads: the states, the meta keys and the accessors of the real application
// class, the marks of the real form, and the questions the real form asks as closed answers.
$application_file   = WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
$application_source = (string) file_get_contents( $application_file );
$form_source        = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php' );
$constants_of       = static function ( $source, array $names ) {
	$found = array();
	foreach ( $names as $constant ) {
		$found[ $constant ] = preg_match( '/const ' . preg_quote( $constant, '/' ) . '\s*=\s*\'([^\']*)\';/', $source, $value ) ? $value[1] : null;
	}
	return $found;
};
$stand_in_constants = static function ( $class, $prefix ) {
	return array_filter( ( new ReflectionClass( $class ) )->getConstants(), static function ( $name ) use ( $prefix ) { return 0 === strpos( $name, $prefix ); }, ARRAY_FILTER_USE_KEY );
};
foreach ( array( 'STATE_', 'META_', 'EVENT_' ) as $prefix ) {
	$ours = $stand_in_constants( 'WPCPM_Mentor_Application', $prefix );
	ck( "the application class still has the stand-in's $prefix constants, value for value", $constants_of( $application_source, array_keys( $ours ) ), $ours );
}
ck( 'and the post type', $constants_of( $application_source, array( 'POST_TYPE' ) ), array( 'POST_TYPE' => WPCPM_Mentor_Application::POST_TYPE ) );
$ours = $stand_in_constants( 'WPCPM_Mentor_Application_Form', 'SIGNAL_' );
ck( 'the form still marks a possible duplicate, a known mentor, a honeypot, a quick fill and the mail that did not go, under the stand-in\'s words', $constants_of( $form_source, array_keys( $ours ) ), $ours );
ck( 'the states, in the real class, are the nine the stand-in lists, and the queue is the four open ones', array(
	preg_match( '/public static function states\(\) \{\s*return array\(\s*self::STATE_NEW,\s*self::STATE_HELD,\s*self::STATE_SPAM,\s*self::STATE_INFO,\s*self::STATE_SECOND,\s*self::STATE_SELECTED,\s*self::STATE_ACTIVE,\s*self::STATE_DECLINED,\s*self::STATE_LAPSED,\s*\);/', $application_source ),
	preg_match( '/public static function open_states\(\) \{\s*return array\( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO, self::STATE_SECOND \);/', $application_source ),
	WPCPM_Mentor_Application::open_states(),
), array( 1, 1, array( 'new', 'held', 'info', 'second' ) ) );

// The languages an application may hold are the form's fixed list, in the code: the stand-in's list is the real one.
preg_match( '/public static function languages\(\) \{\s*return array\((.*?)\n\t\t\);/s', $form_source, $real_languages );
preg_match_all( "/'([^']+)',/", isset( $real_languages[1] ) ? $real_languages[1] : '', $real_languages );
ck( 'the form still has the stand-in\'s 38 languages, name for name, in the same order, and they do not read an option', array( count( $real_languages[1] ), $real_languages[1] === WPCPM_Mentor_Application_Form::languages(), false !== strpos( $method_body( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php', 'languages' ), 'get_option' ) ), array( 38, true, false ) );

// Each accessor the answer calls reads the one post meta key the stand-in says it does, and the list query
// selects by the state alone, among the private posts of the application type, oldest first.
$meta_of = static function ( $method ) use ( $method_body, $application_file ) {
	preg_match_all( '/get_post_meta\(\s*[^,]+,\s*self::(META_[A-Z_]+)/', $method_body( $application_file, $method ), $found );
	return array_values( array_unique( $found[1] ) );
};
ck( 'the real accessors read, between them, the state, the answers, the reference, the confirmation, the signals and the history, one key each', array(
	'state_of'     => $meta_of( 'state_of' ),
	'fields_of'    => $meta_of( 'fields_of' ),
	'reference_of' => $meta_of( 'reference_of' ),
	'is_confirmed' => $meta_of( 'is_confirmed' ),
	'signals_of'   => $meta_of( 'signals_of' ),
	'events'       => $meta_of( 'events' ),
), array(
	'state_of'     => array( 'META_STATE' ),
	'fields_of'    => array( 'META_FIELDS' ),
	'reference_of' => array( 'META_REFERENCE' ),
	'is_confirmed' => array( 'META_VERIFIED' ),
	'signals_of'   => array( 'META_SIGNALS' ),
	'events'       => array( 'META_EVENT' ),
) );
ck( 'the history is read through events() by last_at() and decided_at(), the times only', array(
	false !== strpos( $method_body( $application_file, 'last_at' ), 'self::events(' ),
	false !== strpos( $method_body( $application_file, 'decided_at' ), 'self::events(' ),
	false !== strpos( $method_body( $application_file, 'last_at' ), "['note']" ),
	false !== strpos( $method_body( $application_file, 'decided_at' ), "['note']" ),
), array( true, true, false, false ) );
$query_body = $method_body( $application_file, 'query' );
ck( 'the real query selects the private posts of the application type by their state (META_STATE), oldest first', array(
	false !== strpos( $query_body, "'post_type'   => self::POST_TYPE" ),
	false !== strpos( $query_body, "'post_status' => 'private'" ),
	false !== strpos( $query_body, "'key'     => self::META_STATE" ),
	false !== strpos( $query_body, "'date' => 'ASC'" ),
	false !== strpos( $query_body, "'ID'   => 'ASC'" ),
), array( true, true, true, true, true ) );
ck( 'a reference is read from its meta first, as the queue prints it', 0 < preg_match( '/\$stored = \(string\) get_post_meta\( \(int\) \$post->ID, self::META_REFERENCE, true \);\s*return \'\' !== \$stored \? \$stored : self::reference\( \(int\) \$post->ID \);/', $application_source ), true );

// The five answers an application's row gives are closed answers in the real form: a yes or no, a country
// from the list, the languages and the contribution areas from their lists, and a number of hours.
$question_type = static function ( $key ) use ( $form_source ) {
	return preg_match( "/'" . $key . "'\s+=> array\(.*?'type'\s+=> '([a-z]+)'/s", $form_source, $found ) ? $found[1] : null;
};
ck( 'the form asks the teacher question as a yes or no, the country as a country, the languages and the contribution areas as picks from a list, and the hours as a number', array(
	'teacher' => $question_type( 'teacher' ), 'country' => $question_type( 'country' ), 'languages' => $question_type( 'languages' ), 'expertise' => $question_type( 'expertise' ), 'hours' => $question_type( 'hours' ),
), array( 'teacher' => 'yesno', 'country' => 'country', 'languages' => 'languages', 'expertise' => 'teams', 'hours' => 'number' ) );
ck( 'and keeps nothing else of them: a yes or no is Yes or No, a country one of the list, a pick the ticked choices of the list, hours a number from min to max', array(
	0 < preg_match( "/'yesno' === \\\$type.*?'choices' => array\( 'Yes', 'No' \)/s", $form_source ),
	0 < preg_match( "/'country' === \\\$type.*?in_array\( \\\$value, self::countries\(\), true \) \? self::accept\( \\\$value \) : self::refuse\( 'bad_choice' \)/s", $form_source ),
	0 < preg_match( "/in_array\( \\\$type, array\( 'languages', 'teams' \), true \).*?self::picked\( \\\$raw, 'languages' === \\\$type \? self::languages\(\) : self::teams\(\) \)/s", $form_source ),
	0 < preg_match( "/return array_values\( array_intersect\( \\\$choices, \\\$posted \) \);/", $form_source ),
	0 < preg_match( "/'number' === \\\$type.*?is_numeric\( \\\$typed \).*?'below_min'.*?'above_max'/s", $form_source ),
), array( true, true, true, true, true ) );

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

echo "\n=== A student's tracks (wpcpm/find-student) ===\n";
readers_reset();
$PUPILS = readers_pupils();
$ALPHA  = array( array( 'recPUPA0000000002', 'recPUPA0000000001' ) );
ck( 'the builder is there', method_exists( 'WPCPM_Ability_Answers', 'students' ), true );

$built = build( 'students', array( 'who' => 'PUPIL-ALPHA@example.TEST' ) );
$a     = plain( $built['answer'] );
ck( 'a build is the answer and the record IDs of the tracks it lists', array_keys( $built ), array( 'answer', 'records' ) );
ck( 'the answer\'s keys', array_keys( $a ), array( 'people', 'more', 'read_at' ) );
ck( 'an email address, in any case, finds the rows whose address key it is: one person, two tracks in start order, and the Duplicated row never (Review Focus 1)', tracks_of( $built ), $ALPHA );
ck( 'the records are every track\'s record ID, in the answer\'s order', $built['records'], array( 'recPUPA0000000002', 'recPUPA0000000001' ) );
ck( 'no more, and the roster\'s read time in UTC', array( $a['more'], $a['read_at'] ), array( false, '2026-10-24T03:00:00+00:00' ) );
$p0 = isset( $a['people'][0] ) ? $a['people'][0] : array();
$t0 = isset( $p0['tracks'][0] ) ? $p0['tracks'][0] : array();
$t1 = isset( $p0['tracks'][1] ) ? $p0['tracks'][1] : array();
ck( 'a person: whether they have an account, and their tracks', array_keys( $p0 ), array( 'has_account', 'tracks' ) );
ck( 'this one has an account', isset( $p0['has_account'] ) ? $p0['has_account'] : null, true );
ck( 'a track: the keys, in the schema\'s order', array_keys( $t0 ), array( 'record_id', 'status', 'institution', 'cohort', 'start', 'end', 'has_mentor', 'mentor_record_id', 'team', 'hours', 'reports', 'waiting_course' ) );
ck( 'the first track: on a track, with the institution whose roster lists it, its semester, dates, mentor, team, hours and reports', $t0, array(
	'record_id'        => 'recPUPA0000000002',
	'status'           => 'In Sensei',
	'institution'      => array( 'record_id' => 'recINST0000000002', 'name' => 'Northern UNIVERSITY of Samples' ),
	'cohort'           => '2025-H2',
	'start'            => '2025-09-01',
	'end'              => '2026-01-31',
	'has_mentor'       => true,
	'mentor_record_id' => 'recMENT0000000001',
	'team'             => 'Polyglots',
	'hours'            => 135.5,
	'reports'          => 2,
	'waiting_course'   => null,
) );
ck( 'the second: waiting to graduate, so the course the account keeps; no end, no hours, no mentor on the row, and not the account\'s mentor, who is the other track\'s', $t1, array(
	'record_id'        => 'recPUPA0000000001',
	'status'           => 'Pending graduation',
	'institution'      => array( 'record_id' => 'recINST0000000001', 'name' => 'University of Example' ),
	'cohort'           => '2026-H1',
	'start'            => '2026-03-02',
	'end'              => null,
	'has_mentor'       => false,
	'mentor_record_id' => null,
	'team'             => '',
	'hours'            => null,
	'reports'          => 0,
	'waiting_course'   => 'In Sensei',
) );
ck( 'the mentor is the Mentors record ID on the student\'s account, read from the account the row names', in_array( array( 'user_meta', 501, 'wpcpm_student_mentor' ), $GLOBALS['reader_calls'], true ), true );
ck( 'an address with white space around it is the same address', tracks_of( build( 'students', array( 'who' => "  pupil-alpha@example.test\n" ) ) ), $ALPHA );
ck( 'an address nobody holds finds nobody: an empty list, no more, no records', ( static function () { $b = build( 'students', array( 'who' => 'nobody@example.test' ) ); return array( json_encode( $b['answer']['people'] ), $b['answer']['more'], $b['records'] ); } )(), array( '[]', false, array() ) );
ck( 'the address of a person whose one row is SPAM finds nobody: a SPAM row is never a track', tracks_of( build( 'students', array( 'who' => 'pupil-delta@example.test' ) ) ), array() );
ck( 'an address is an address: a part of one finds nobody', tracks_of( build( 'students', array( 'who' => 'alpha@example.test' ) ) ), array() );

echo "\n--- By WordPress.org username or profile address ---\n";
foreach ( array( 'pupil-alpha-example', 'Pupil-Alpha-Example', '@pupil-alpha-example', 'https://profiles.wordpress.org/pupil-alpha-example/', 'profiles.wordpress.org/pupil-alpha-example', 'https://profiles.wordpress.org/pupil-alpha-example/profile/' ) as $who ) {
	ck( "\"$who\" finds the rows whose username it is", tracks_of( build( 'students', array( 'who' => $who ) ) ), $ALPHA );
}
ck( 'a username nobody holds finds nobody', tracks_of( build( 'students', array( 'who' => 'pupil-omega-example' ) ) ), array() );
ck( 'the username of the person whose one row is SPAM finds nobody', tracks_of( build( 'students', array( 'who' => 'pupil-delta-example' ) ) ), array() );
ck( 'a mentor\'s username finds no student', tracks_of( build( 'students', array( 'who' => 'mentor-gamma-example' ) ) ), array() );

// A name with a letter a username cannot hold is not tried as the username without it (1.123.48): "Éxample-example"
// is not "xample-example". The row below has the username xample-example and a name no one types.
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPX0000000001'] = readers_track( 'delta', 'recPUPX0000000001', 'recINST0000000002', array( 'status' => 'In Sensei', 'start' => '2026-01-05', 'name' => 'Student Name Quux', 'email' => 'student-quux@example.test', 'email_key' => 'student-quux@example.test', 'username' => 'xample-example' ) );
foreach ( array( 'xample-example', '@Xample-Example', 'https://profiles.wordpress.org/xample-example/' ) as $who ) {
	ck( "\"$who\" finds the row whose username it is", tracks_of( build( 'students', array( 'who' => $who ) ) ), array( array( 'recPUPX0000000001' ) ) );
}
foreach ( array( "\u{00C9}xample-example", "\u{00C9}XAMPLE-EXAMPLE", "https://profiles.wordpress.org/\u{00C9}xample-example/", "@\u{00C9}xample-example" ) as $who ) {
	ck( "\"$who\" is a name, not the username without its first letter: it finds nobody, and the answer does not repeat it", array( tracks_of( $b = build( 'students', array( 'who' => $who ) ) ), false !== strpos( json_of( $b ), 'xample-example' ) ), array( array(), false ) );
}
people_walk( 'a name with an accented first letter', build( 'students', array( 'who' => "\u{00C9}xample-example" ) ), $PUPILS );
readers_reset();

echo "\n--- By Students record ID ---\n";
ck( 'a record ID finds that row and every row of the same person', tracks_of( build( 'students', array( 'who' => 'recPUPA0000000002' ) ) ), $ALPHA );
ck( 'and so does the other one', tracks_of( build( 'students', array( 'who' => 'recPUPA0000000001' ) ) ), $ALPHA );
ck( 'with white space around it too', tracks_of( build( 'students', array( 'who' => " recPUPA0000000001\n" ) ) ), $ALPHA );
ck( 'the record ID of a Duplicated row finds nothing', tracks_of( build( 'students', array( 'who' => 'recPUPA0000000003' ) ) ), array() );
ck( 'nor that of a SPAM row', tracks_of( build( 'students', array( 'who' => 'recPUPD0000000001' ) ) ), array() );
ck( 'a row with no address is a person of its own: its record ID finds it alone', tracks_of( build( 'students', array( 'who' => 'recPUPC0000000001' ) ) ), array( array( 'recPUPC0000000001' ) ) );
ck( 'a record ID the roster does not hold finds nobody', tracks_of( build( 'students', array( 'who' => 'recPUPZ0000000001' ) ) ), array() );
ck( 'an institution\'s record ID is no student\'s', tracks_of( build( 'students', array( 'who' => 'recINST0000000001' ) ) ), array() );
ck( 'a row that names no institution is found as well', tracks_of( build( 'students', array( 'who' => 'recPUPB0000000001' ) ) ), array( array( 'recPUPB0000000001' ) ) );

echo "\n--- By part of a name ---\n";
$everyone = array( array( 'recPUPA0000000002', 'recPUPA0000000001' ), array( 'recPUPC0000000001' ), array( 'recPUPC0000000002' ), array( 'recPUPE0000000001' ), array( 'recPUPB0000000001' ) );
$built    = build( 'students', array( 'who' => 'pupil name' ) );
ck( 'three or more characters find every row whose name holds them, case-insensitive: one entry per person, the most recent start first, a person with no start last', tracks_of( $built ), $everyone );
ck( 'the SPAM row\'s person is not among them', in_array( array( 'recPUPD0000000001' ), tracks_of( $built ), true ), false );
ck( 'and the records are every listed track\'s, in order', $built['records'], array( 'recPUPA0000000002', 'recPUPA0000000001', 'recPUPC0000000001', 'recPUPC0000000002', 'recPUPE0000000001', 'recPUPB0000000001' ) );
ck( '"PUPIL NAME" finds the same', tracks_of( build( 'students', array( 'who' => 'PUPIL NAME' ) ) ), $everyone );
ck( 'two rows with one name and no address are two people, the earlier record ID first when they started the same day', tracks_of( build( 'students', array( 'who' => 'name gamma' ) ) ), array( array( 'recPUPC0000000001' ), array( 'recPUPC0000000002' ) ) );
ck( 'a name is found by its middle too', tracks_of( build( 'students', array( 'who' => 'il name eps' ) ) ), array( array( 'recPUPE0000000001' ) ) );
ck( 'a mentor\'s name, a tutor\'s or a display name finds no student: only the row\'s own name is searched', array( tracks_of( build( 'students', array( 'who' => 'Mentor Name Gamma' ) ) ), tracks_of( build( 'students', array( 'who' => 'Tutor Name Beta' ) ) ), tracks_of( build( 'students', array( 'who' => 'Pupil Display Alpha' ) ) ) ), array( array(), array(), array() ) );
ck( 'two characters are too few: nobody, even past the schema', tracks_of( build( 'students', array( 'who' => 'pu' ) ) ), array() );
ck( 'two characters with white space around them are still two', tracks_of( build( 'students', array( 'who' => "  pu\n  " ) ) ), array() );
ck( 'three are enough', count( tracks_of( build( 'students', array( 'who' => 'pup' ) ) ) ), 5 );
ck( 'a name with a newline and a zero-width space in it is found by the words a person would type', tracks_of( build( 'students', array( 'who' => "pupil\nname\u{200B} alpha" ) ) ), $ALPHA );

readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['name'] = 'Other Spelling Of Alpha';
ck( 'a person found by the name on one row is listed with every track, the one whose row spells the name otherwise too', tracks_of( build( 'students', array( 'who' => 'Pupil Name Alpha' ) ) ), $ALPHA );

readers_reset();
$GLOBALS['readers']['roster']['recINST0000000006']['recPUPE0000000001']['username'] = 'gamma';
ck( 'a word that is one person\'s username and part of others\' names finds both: the username first, then the names, whatever their starts', tracks_of( build( 'students', array( 'who' => 'Gamma' ) ) ), array( array( 'recPUPE0000000001' ), array( 'recPUPC0000000001' ), array( 'recPUPC0000000002' ) ) );
ck( 'words with a space are a name only, never a username', tracks_of( build( 'students', array( 'who' => 'gam ma' ) ) ), array() );

echo "\n--- What a track says when its row says little ---\n";
readers_reset();
$built = build( 'students', array( 'who' => 'pupil name' ) );
$a     = plain( $built['answer'] );
ck( 'a row that names no institution: no institution, no semester, no dates, no account, no mentor', array( isset( $a['people'][4] ) ? $a['people'][4]['has_account'] : null, track_of( $built, 'recPUPB0000000001' ) ), array( false, array(
	'record_id'        => 'recPUPB0000000001',
	'status'           => 'Interested',
	'institution'      => null,
	'cohort'           => null,
	'start'            => null,
	'end'              => null,
	'has_mentor'       => false,
	'mentor_record_id' => null,
	'team'             => '',
	'hours'            => null,
	'reports'          => 0,
	'waiting_course'   => null,
) ) );
ck( 'a roster whose institution the index does not hold: its record ID and an empty name; whole hours are a whole number; a mentor on the row and no card on the account is no record ID; a graduate waits for no course, whatever the account keeps', track_of( $built, 'recPUPE0000000001' ), array(
	'record_id'        => 'recPUPE0000000001',
	'status'           => 'Graduate',
	'institution'      => array( 'record_id' => 'recINST0000000006', 'name' => '' ),
	'cohort'           => '2024-H1',
	'start'            => '2024-02-01',
	'end'              => '2024-06-30',
	'has_mentor'       => true,
	'mentor_record_id' => null,
	'team'             => 'Training',
	'hours'            => 400,
	'reports'          => 1,
	'waiting_course'   => null,
) );
ck( 'a card on the account without a record ID is no record ID; waiting with no course kept is none; a team is cleaned to one line; hours keep their fraction', track_of( $built, 'recPUPC0000000001' ), array(
	'record_id'        => 'recPUPC0000000001',
	'status'           => 'Pending graduation',
	'institution'      => array( 'record_id' => 'recINST0000000002', 'name' => 'Northern UNIVERSITY of Samples' ),
	'cohort'           => '2025-H1',
	'start'            => '2025-03-01',
	'end'              => null,
	'has_mentor'       => false,
	'mentor_record_id' => null,
	'team'             => 'Core Team',
	'hours'            => 6.2,
	'reports'          => 0,
	'waiting_course'   => null,
) );
ck( 'a status is cleaned; an end that is no date is none; hours that are no number are none; no account', array( isset( $a['people'][2] ) ? $a['people'][2]['has_account'] : null, track_of( $built, 'recPUPC0000000002' ) ), array( false, array(
	'record_id'        => 'recPUPC0000000002',
	'status'           => 'Paused',
	'institution'      => array( 'record_id' => 'recINST0000000002', 'name' => 'Northern UNIVERSITY of Samples' ),
	'cohort'           => '2025-H1',
	'start'            => '2025-03-01',
	'end'              => null,
	'has_mentor'       => false,
	'mentor_record_id' => null,
	'team'             => '',
	'hours'            => null,
	'reports'          => 0,
	'waiting_course'   => null,
) ) );
ck( 'hours of 0 are 0 hours, not none', ( static function () { readers_reset(); $GLOBALS['readers']['roster']['recINST0000000006']['recPUPE0000000001']['hours'] = '0'; return track_of( build( 'students', array( 'who' => 'recPUPE0000000001' ) ), 'recPUPE0000000001' )['hours']; } )(), 0 );
ck( 'a start written as a time is no date, so no semester either', ( static function () { readers_reset(); $GLOBALS['readers']['roster']['recINST0000000006']['recPUPE0000000001']['start'] = '2024-02-01T00:00:00.000Z'; $t = track_of( build( 'students', array( 'who' => 'recPUPE0000000001' ) ), 'recPUPE0000000001' ); return array( $t['start'], $t['cohort'] ); } )(), array( null, null ) );
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPC0000000002']['status'] = 'Pending graduation';
ck( 'waiting to graduate with no account waits for no course anyone keeps', track_of( build( 'students', array( 'who' => 'recPUPC0000000002' ) ), 'recPUPC0000000002' )['waiting_course'], null );
readers_reset();
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_course'] = 'Retired Track';
ck( 'a waiting course the site no longer runs is none, as the screens name none', track_of( build( 'students', array( 'who' => 'recPUPA0000000001' ) ), 'recPUPA0000000001' )['waiting_course'], null );
$GLOBALS['readers']['tracks_run'][] = 'Retired Track';
ck( 'and the day a track holds it again, it is named', track_of( build( 'students', array( 'who' => 'recPUPA0000000001' ) ), 'recPUPA0000000001' )['waiting_course'], 'Retired Track' );
readers_reset();
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_course'] = "In\u{200B} Sensei\n";
ck( 'a kept course spelled otherwise than any track the site runs is none', track_of( build( 'students', array( 'who' => 'recPUPA0000000001' ) ), 'recPUPA0000000001' )['waiting_course'], null );
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['status'] = 'Paused';
ck( 'a paused track waits for no course, whatever the account keeps', track_of( build( 'students', array( 'who' => 'recPUPA0000000002' ) ), 'recPUPA0000000002' )['waiting_course'], null );
readers_reset();
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_mentor'] = 'recMENT0000000001';
ck( 'a mentor kept as anything but a card is no record ID', track_of( build( 'students', array( 'who' => 'recPUPA0000000002' ) ), 'recPUPA0000000002' )['mentor_record_id'], null );

echo "\n--- The mentor's record ID, on that mentor's track only ---\n";
/** The mentor record IDs of the tracks a who finds, by track. */
$mentors_of = static function ( $who ) {
	$out = array();
	foreach ( plain( build( 'students', array( 'who' => $who ) )['answer'] )['people'] as $person ) {
		foreach ( $person['tracks'] as $track ) { $out[ $track['record_id'] ] = $track['mentor_record_id']; }
	}
	return $out;
};
$old_track = static function ( array $over = array() ) {
	$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000004'] = readers_track( 'alpha', 'recPUPA0000000004', 'recINST0000000002', array_merge( array( 'status' => 'Graduate', 'start' => '2024-09-01', 'end' => '2025-01-31', 'has_mentor' => true, 'reports' => array( 'recREPT0000000004' ), 'user_id' => 501 ), $over ) );
};
readers_reset();
$old_track();
ck( 'the account follows the report on one track: that track has the mentor, an old finished track with a mentor of its own and a Pending track with none do not', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000004' => null, 'recPUPA0000000002' => 'recMENT0000000001', 'recPUPA0000000001' => null ) );
ck( 'and the account was asked which report it follows', in_array( array( 'user_meta', 501, 'wpcpm_student_record_id' ), $GLOBALS['reader_calls'], true ), true );
readers_reset();
$old_track( array( 'reports' => array( 'recREPT0000000002', 'recREPT0000000004' ) ) );
ck( 'the followed report on two tracks cannot tell them apart, and two tracks name a mentor: none has the record ID', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000004' => null, 'recPUPA0000000002' => null, 'recPUPA0000000001' => null ) );
readers_reset();
$old_track( array( 'reports' => array( 'recREPT0000000002', 'recREPT0000000004' ), 'has_mentor' => false ) );
ck( 'the same, with one track that names a mentor: that track has it', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000004' => null, 'recPUPA0000000002' => 'recMENT0000000001', 'recPUPA0000000001' => null ) );
readers_reset();
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_record_id'] = 'recREPT0000000077';
ck( 'an account following a report on none of the tracks: the one track that names a mentor has it', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000002' => 'recMENT0000000001', 'recPUPA0000000001' => null ) );
readers_reset();
unset( $GLOBALS['readers']['user_meta'][501]['wpcpm_student_record_id'] );
$old_track();
ck( 'an account that follows no report, and two tracks that name a mentor: none has it', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000004' => null, 'recPUPA0000000002' => null, 'recPUPA0000000001' => null ) );
readers_reset();
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_record_id'] = 'not a record';
ck( 'a followed report that is no record ID tells nothing: the one track that names a mentor has it', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000002' => 'recMENT0000000001', 'recPUPA0000000001' => null ) );
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['has_mentor'] = false;
ck( 'the followed report\'s track has the mentor even when its row names none: the account says whose mentor it is', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000002' => 'recMENT0000000001', 'recPUPA0000000001' => null ) );
readers_reset();
ck( 'an account whose card is empty has no mentor on any track, even on the report it follows', $mentors_of( 'recPUPE0000000001' ), array( 'recPUPE0000000001' => null ) );
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['user_id'] = 0;
ck( 'a track on no account has no mentor, and the one that is on the account but follows no report and names no mentor has none either', $mentors_of( 'recPUPA0000000001' ), array( 'recPUPA0000000002' => null, 'recPUPA0000000001' => null ) );

// Two tracks hold the followed report: the one of them that names a mentor has it, and a mentored track
// that does not hold the report never does.
$third_track = static function ( array $over = array() ) {
	$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000006'] = readers_track( 'alpha', 'recPUPA0000000006', 'recINST0000000002', array_merge( array( 'status' => 'Paused', 'start' => '2024-03-01', 'has_mentor' => true, 'reports' => array( 'recREPT0000000006' ), 'user_id' => 501 ), $over ) );
};
readers_reset();
$old_track( array( 'reports' => array( 'recREPT0000000002', 'recREPT0000000004' ), 'has_mentor' => false ) );
$third_track();
ck( 'two tracks hold the followed report and one of them names a mentor: that one has it, though another track that does not hold the report names a mentor too', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000006' => null, 'recPUPA0000000004' => null, 'recPUPA0000000002' => 'recMENT0000000001', 'recPUPA0000000001' => null ) );
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['has_mentor'] = false;
$old_track( array( 'reports' => array( 'recREPT0000000002', 'recREPT0000000004' ), 'has_mentor' => false ) );
$third_track();
ck( 'two tracks hold it and neither names a mentor: none has it, not even the one mentored track that does not hold the report', $mentors_of( 'recPUPA0000000002' ), array( 'recPUPA0000000006' => null, 'recPUPA0000000004' => null, 'recPUPA0000000002' => null, 'recPUPA0000000001' => null ) );

// The mentor's track is judged over every track of the person, those an institution hides too.
$visible_track = static function () {
	$GLOBALS['readers']['roster']['recINST0000000001']['recPUPA0000000005'] = readers_track( 'alpha', 'recPUPA0000000005', 'recINST0000000001', array( 'status' => 'Graduate', 'start' => '2024-05-01', 'has_mentor' => true, 'reports' => array( 'recREPT0000000005' ), 'user_id' => 501 ) );
};
readers_reset();
$visible_track();
$GLOBALS['reader_calls'] = array();
$narrowed = static function () {
	$out = array();
	foreach ( plain( build( 'students', array( 'who' => 'pupil-alpha@example.test', 'institution' => 'recINST0000000001' ) )['answer'] )['people'] as $person ) {
		foreach ( $person['tracks'] as $track ) { $out[ $track['record_id'] ] = $track['mentor_record_id']; }
	}
	return $out;
};
ck( 'an institution that hides the track the account\'s mentor belongs to: the visible track that names a mentor of its own does not carry that mentor', $narrowed(), array( 'recPUPA0000000005' => null, 'recPUPA0000000001' => null ) );
ck( 'and the hidden track is not listed', array_keys( $narrowed() ), array( 'recPUPA0000000005', 'recPUPA0000000001' ) );
ck( 'without the institution, the hidden track is the one that has it', $mentors_of( 'recPUPA0000000001' ), array( 'recPUPA0000000005' => null, 'recPUPA0000000002' => 'recMENT0000000001', 'recPUPA0000000001' => null ) );
readers_reset();
$visible_track();
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_record_id'] = 'recREPT0000000077';
ck( 'an account that follows a report on no track, with a mentored track hidden and one shown: two name a mentor, so the shown one does not carry it', $narrowed(), array( 'recPUPA0000000005' => null, 'recPUPA0000000001' => null ) );
readers_reset();
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_record_id'] = 'recREPT0000000077';
ck( 'the one mentored track, hidden, has it even when it is not listed: the shown track does not', $narrowed(), array( 'recPUPA0000000001' => null ) );
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPC0000000002']['user_id'] = 503;
ck( 'a person with an account on any of their rows has an account', plain( build( 'students', array( 'who' => 'recPUPC0000000002' ) )['answer'] )['people'][0]['has_account'], true );
readers_reset();
$GLOBALS['readers']['roster_read'] = 0;
ck( 'a roster never read says null for its time, and the key is there', ( static function () { $b = build( 'students', array( 'who' => 'pupil name' ) ); return array( array_key_exists( 'read_at', $b['answer'] ), $b['answer']['read_at'] ); } )(), array( true, null ) );

echo "\n--- Narrowed to one institution ---\n";
readers_reset();
$built = build( 'students', array( 'who' => 'pupil name', 'institution' => 'recINST0000000002' ) );
ck( 'an institution\'s record ID narrows to the rows of its roster: a person with tracks elsewhere is listed with this one only, and a row that names no institution is not among them', tracks_of( $built ), array( array( 'recPUPA0000000002' ), array( 'recPUPC0000000001' ), array( 'recPUPC0000000002' ) ) );
$rosters_read = static function () { return array_values( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return in_array( $call[0], array( 'roster', 'unlinked' ), true ); } ) ); };
ck( 'that roster was read first, and then, someone being found, every roster and the rows that name none, for the people\'s other tracks (to tell whose mentor is whose; none of them is listed)', $rosters_read(), array( array( 'roster', 'recINST0000000002' ), array( 'roster', 'recINST0000000001' ), array( 'roster', 'recINST0000000002' ), array( 'roster', 'recINST0000000006' ), array( 'unlinked' ) ) );
readers_reset();
build( 'students', array( 'who' => 'nobody at all', 'institution' => 'recINST0000000002' ) );
ck( 'with nobody found there, only that roster was read', $rosters_read(), array( array( 'roster', 'recINST0000000002' ) ) );
readers_reset();
build( 'students', array( 'who' => 'pupil name' ) );
ck( 'and with no institution, every roster and the rows that name none, once', $rosters_read(), array( array( 'roster', 'recINST0000000001' ), array( 'roster', 'recINST0000000002' ), array( 'roster', 'recINST0000000006' ), array( 'unlinked' ) ) );
readers_reset();
ck( 'part of its name narrows the same, case-insensitive', tracks_of( build( 'students', array( 'who' => 'pupil name', 'institution' => 'NORTHERN' ) ) ), array( array( 'recPUPA0000000002' ), array( 'recPUPC0000000001' ), array( 'recPUPC0000000002' ) ) );
ck( 'a part that names two institutions narrows to both, and a roster whose institution the index does not hold is not named by any', tracks_of( build( 'students', array( 'who' => 'pupil name', 'institution' => 'university' ) ) ), array( array( 'recPUPA0000000002', 'recPUPA0000000001' ), array( 'recPUPC0000000001' ), array( 'recPUPC0000000002' ) ) );
ck( 'that roster by its record ID', tracks_of( build( 'students', array( 'who' => 'pupil name', 'institution' => 'recINST0000000006' ) ) ), array( array( 'recPUPE0000000001' ) ) );
ck( 'an address narrowed to an institution: the person\'s tracks there only', tracks_of( build( 'students', array( 'who' => 'pupil-alpha@example.test', 'institution' => 'recINST0000000001' ) ) ), array( array( 'recPUPA0000000001' ) ) );
ck( 'a record ID narrowed to an institution whose roster does not list it: nobody, as the row is not among the rows looked at', tracks_of( build( 'students', array( 'who' => 'recPUPA0000000002', 'institution' => 'recINST0000000001' ) ) ), array() );
ck( 'an institution nothing matches: nobody', tracks_of( build( 'students', array( 'who' => 'pupil name', 'institution' => 'zzz' ) ) ), array() );
ck( 'an institution of two characters names none: nobody', tracks_of( build( 'students', array( 'who' => 'pupil name', 'institution' => 'un' ) ) ), array() );
ck( 'an institution\'s record ID with no roster: nobody', tracks_of( build( 'students', array( 'who' => 'pupil name', 'institution' => 'recINST0000000099' ) ) ), array() );
foreach ( array( 'a number' => 12, 'a list' => array( 'recINST0000000002' ), 'an empty string' => '', 'white space' => " \n " ) as $label => $institution ) {
	ck( "an institution that is $label is ignored as if absent", tracks_of( build( 'students', array( 'who' => 'pupil name', 'institution' => $institution ) ) ), $everyone );
}

echo "\n--- Fourteen people: ten listed, and more (Review Focus 2) ---\n";
readers_reset();
$MANY  = many_pupils( 14 );
$built = build( 'students', array( 'who' => 'many pupil' ) );
ck( 'a name fourteen people hold: ten people, more true, ten records', array( count( $built['answer']['people'] ), $built['answer']['more'], count( $built['records'] ) ), array( WPCPM_Abilities::PEOPLE_MAX, true, 10 ) );
ck( 'the ten are the most recent starts', $built['records'], array_map( static function ( $n ) { return sprintf( 'recMANYP%09d', $n ); }, range( 14, 5 ) ) );
ck( 'and only the ten listed had their accounts read: the four left out were never looked up', array_values( array_unique( array_map( static function ( $call ) { return $call[1]; }, array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return 'user_meta' === $call[0]; } ) ) ) ), range( 614, 605 ) );
people_walk( 'fourteen people', $built, $MANY );
readers_reset();
many_pupils( 10 );
$built = build( 'students', array( 'who' => 'many pupil' ) );
ck( 'ten people: all ten, and no more', array( count( $built['answer']['people'] ), $built['answer']['more'] ), array( 10, false ) );
readers_reset();
many_pupils( 11 );
ck( 'eleven: ten, and more', ( static function () { $b = build( 'students', array( 'who' => 'many pupil' ) ); return array( count( $b['answer']['people'] ), $b['answer']['more'] ); } )(), array( 10, true ) );

echo "\n--- What it cannot read is an empty answer ---\n";
readers_reset();
foreach ( array( 'no input' => null, 'text' => 'pupil name', 'an empty list' => array(), 'a number' => 12, 'no who' => array( 'institution' => 'recINST0000000002' ), 'a who that is not text' => array( 'who' => 12 ), 'a list for a who' => array( 'who' => array( 'pupil name' ) ), 'an empty who' => array( 'who' => '' ), 'a who of white space' => array( 'who' => "  \n\t " ) ) as $label => $input ) {
	$built = build( 'students', $input );
	ck( "$label: nobody, no more, no records, and the roster's read time", array( json_encode( $built['answer']['people'] ), $built['answer']['more'], $built['records'], $built['answer']['read_at'] ), array( '[]', false, array(), '2026-10-24T03:00:00+00:00' ) );
}
$built = build( 'students', array( 'who' => str_repeat( 'x', 500 ) ) );
ck( 'a who of 500 characters is cut like any typed text and finds nobody', array( count( $built['answer']['people'] ), $built['answer']['more'] ), array( 0, false ) );
ck( 'a key the schema does not name is ignored', tracks_of( build( 'students', array( 'who' => 'recPUPA0000000001', 'extra' => 'x' ) ) ), $ALPHA );

echo "\n--- The input schema: who is required, from three characters (Review Focus 2) ---\n";
$in = WPCPM_Ability_Answers::input_schema( 'students' );
ck( 'an object that requires who, takes institution besides and nothing else', array( isset( $in['type'] ) ? $in['type'] : null, isset( $in['required'] ) ? $in['required'] : null, isset( $in['properties'] ) ? array_keys( $in['properties'] ) : null, isset( $in['additionalProperties'] ) ? $in['additionalProperties'] : 'missing' ), array( 'object', array( 'who' ), array( 'who', 'institution' ), false ) );
ck( 'who is a string of 3 to 200 characters, room for a profile address', array( $in['properties']['who']['type'], $in['properties']['who']['minLength'], $in['properties']['who']['maxLength'] ), array( 'string', 3, 200 ) );
ck( 'so a who of two characters is refused by the schema, before the build', array( mb_strlen( 'ab' ) < $in['properties']['who']['minLength'], mb_strlen( 'abc' ) < $in['properties']['who']['minLength'] ), array( true, false ) );
ck( 'institution is a string of 3 to 120 characters', array( $in['properties']['institution']['type'], $in['properties']['institution']['minLength'], $in['properties']['institution']['maxLength'] ), array( 'string', 3, 120 ) );
ck( 'each says what it takes', array( '' !== (string) $in['properties']['who']['description'], '' !== (string) $in['properties']['institution']['description'] ), array( true, true ) );

echo "\n--- The people walk: no name, address or username, whatever was asked (Review Focus 5) ---\n";
readers_reset();
$forms = array(
	'an email address'                       => array( 'who' => 'pupil-alpha@example.test' ),
	'an email address in capitals'           => array( 'who' => 'PUPIL-ALPHA@EXAMPLE.TEST' ),
	'another person\'s address'              => array( 'who' => 'pupil-epsilon@example.test' ),
	'a username'                             => array( 'who' => 'pupil-alpha-example' ),
	'a username with an at sign'             => array( 'who' => '@pupil-alpha-example' ),
	'a profile address'                      => array( 'who' => 'https://profiles.wordpress.org/pupil-alpha-example/' ),
	'a record ID'                            => array( 'who' => 'recPUPA0000000001' ),
	'the record ID of a row with no address' => array( 'who' => 'recPUPC0000000001' ),
	'the record ID of an unlinked row'       => array( 'who' => 'recPUPB0000000001' ),
	'part of a name'                         => array( 'who' => 'pupil name' ),
	'three characters'                       => array( 'who' => 'pup' ),
	'a whole name'                           => array( 'who' => 'Pupil Name Alpha' ),
	'a name, narrowed by a record ID'        => array( 'who' => 'pupil name', 'institution' => 'recINST0000000002' ),
	'a name, narrowed by part of a name'     => array( 'who' => 'pupil name', 'institution' => 'university' ),
	'an address, narrowed'                   => array( 'who' => 'pupil-alpha@example.test', 'institution' => 'recINST0000000001' ),
	'the SPAM row\'s address'                => array( 'who' => 'pupil-delta@example.test' ),
	'a mentor\'s name'                       => array( 'who' => 'Mentor Name Gamma' ),
);
foreach ( $forms as $label => $input ) {
	people_walk( $label, build( 'students', $input ), $PUPILS );
}
ck( 'and the walk had people to find: the name forms listed five', count( build( 'students', array( 'who' => 'pupil name' ) )['answer']['people'] ), 5 );

// The walk must be able to fail, or the checks above prove nothing.
$planted = plain( build( 'students', array( 'who' => 'pupil name' ) )['answer'] );
$planted['people'][0]['tracks'][0]['team'] = 'Pupil Name ALPHA';
ck( 'the walk finds a name planted in a track, in another case', people_found( $planted, $PUPILS ), array( 'Pupil Name Alpha' ) );
$planted['people'][0]['tracks'][0]['team'] = 'https://profiles.wordpress.org/pupil-alpha-example/';
ck( 'and a profile address, whose slashes JSON escapes, and the username inside it', people_found( $planted, $PUPILS ), array( 'pupil-alpha-example', 'https://profiles.wordpress.org/pupil-alpha-example/' ) );
$planted['people'][0]['tracks'][0]['team'] = 'mentor_login_gamma';
ck( 'and a mentor\'s login', people_found( $planted, $PUPILS ), array( 'mentor_login_gamma' ) );
$planted['people'][0]['tracks'][0]['team'] = 'x@y';
ck( 'and any address sign', people_found( $planted, $PUPILS ), array( '@' ) );
$planted['people'][0]['tracks'][0]['team'] = 'PUPIL-ALPHA-SLACK';
ck( 'and a Slack handle, in another case', people_found( $planted, $PUPILS ), array( 'pupil-alpha-slack' ) );
$planted['people'][0]['tracks'][0]['team'] = 'Location Mentor Gamma';
ck( 'and the location on the mentor card', people_found( $planted, $PUPILS ), array( 'Location Mentor Gamma' ) );
foreach ( array( 'field_of_study', 'accessibility', 'github', 'avatar', 'jobline', 'website_label', 'import_key', 'nicename' ) as $field ) {
	$planted['people'][0]['tracks'][0]['team'] = $PUPILS['alpha'][ $field ];
	ck( "and the person's $field", people_found( $planted, $PUPILS ), array( $PUPILS['alpha'][ $field ] ) );
}
$planted['people'][0]['tracks'][0]['team'] = 'Polyglots';
ck( 'and the answer as it was, with every field looked for, holds none', people_found( $planted, $PUPILS ), array() );
$half = $PUPILS;
unset( $half['beta']['tutor'] );
ck( 'and a walk given half a person says so', people_found( build( 'students', array( 'who' => 'pupil name' ) ), $half ), array( 'the person beta was given without a tutor' ) );

echo "\n--- The account meta a lookup of a student reads ---\n";
$expected_meta = array( 'wpcpm_student_course', 'wpcpm_student_mentor', 'wpcpm_student_record_id' );
$meta_read     = array_values( array_unique( $GLOBALS['students_meta_read'] ) );
sort( $meta_read );
ck( 'over every find-student build above, the account meta read is the mentor card, the report the account follows and the course kept while waiting (inside course_status()), and nothing else', $meta_read, $expected_meta );
ck( 'and those builds did read accounts', count( $GLOBALS['students_meta_read'] ) > 50, true );

echo "\n=== A mentor's students (wpcpm/mentor-students) ===\n";
readers_reset();

$MENTORS       = readers_mentors();
$MENTOR_PEOPLE = readers_pupils();
foreach ( $MENTORS as $key => $mentor ) {
	$MENTOR_PEOPLE[ "mentor $key" ] = $mentor;
}
$NOBODY = array( 'found' => false, 'mentor' => null, 'students' => array(), 'more' => false, 'read_at' => null );

/** Change one mentor's row of the Engagement report: the account's ID and the keys that differ. */
function set_engagement_row( $id, array $facts ) {
	foreach ( $GLOBALS['readers']['engagement']['rows'] as $n => $row ) {
		if ( $id === $row['id'] ) { $GLOBALS['readers']['engagement']['rows'][ $n ] = array_merge( $row, $facts ); }
	}
}

/** The Engagement view a build of mentor 91 says, with the row changed first. */
function view_of_91( array $facts ) {
	readers_reset();
	set_engagement_row( 91, $facts );

	return plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) )['answer'] )['mentor']['view'];
}

$before = time();
$built  = build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) );
$after  = time();
$a      = plain( $built['answer'] );
$GOOD   = array(
	'found'     => true,
	'mentor'    => array( 'record_id' => 'recMENT0000000001', 'active' => true, 'view' => 'students', 'last_sign_in' => '2026-10-23', 'last_visit' => '2026-10-25' ),
	'students'  => array(
		array( 'record_id' => 'recPUPA0000000002', 'status' => 'In Sensei', 'hours' => 135.5, 'start' => '2026-03-02' ),
		array( 'record_id' => 'recPUPA0000000002', 'status' => 'Pending graduation', 'hours' => 0, 'start' => '2026-01-10' ),
		array( 'record_id' => null, 'status' => 'Paused', 'hours' => null, 'start' => null ),
		array( 'record_id' => 'recPUPE0000000001', 'status' => 'Graduate', 'hours' => 400, 'start' => '2024-02-01' ),
	),
	'more'      => false,
	'read_at'   => '2026-10-24T06:00:00+00:00',
);
ck( 'a build is the answer and the record IDs it was made of', array_keys( $built ), array( 'answer', 'records' ) );
ck( 'the answer\'s keys, in the order the schema lists them', array_keys( $a ), array( 'found', 'mentor', 'students', 'more', 'read_at' ) );
ck( 'found by the Mentors record ID: the mentor\'s own record ID, whether active, the Engagement view, and the dates of the last sign-in and the last visit in UTC (23:59:59 is still the day before)', $a['mentor'], $GOOD['mentor'] );
ck( 'the students are the Mentor Report Card\'s list: the current ones first, then the past ones, each in the order the mentors sync keeps them; each by the Students record ID of the track that holds its report row (two report rows of one track are two cards under one ID, and a report no track holds is null); a row with no record ID and a second row of one record are left out; the status is one clean line; hours keep a fraction and a whole number stays whole; a start that is no date, and hours that are no number, are null', $a['students'], $GOOD['students'] );
ck( 'and nothing else is in the answer, and the read time is when the mentors sync last wrote this mentor\'s list', array( $a['more'], $a['read_at'] ), array( false, '2026-10-24T06:00:00+00:00' ) );
ck( 'each student has exactly the four keys and no latest report\'s month: the site keeps no date for a report (it is read live from the base), so the answer leaves the month out', array_values( array_unique( array_map( 'array_keys', $a['students'] ), SORT_REGULAR ) ), array( array( 'record_id', 'status', 'hours', 'start' ) ) );
ck( '`records` lists the mentor\'s record ID and then the students\' Students record IDs, once each, in the answer\'s order, and no null', $built['records'], array( 'recMENT0000000001', 'recPUPA0000000002', 'recPUPE0000000001' ) );
ck( 'the Engagement report was read as of now, once', array( engagement_now() >= $before && engagement_now() <= $after, count( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return 'engagement' === $call[0]; } ) ) ), array( true, 1 ) );

echo "\n--- Found by a login, an address, a profile address, or the Mentors record ID in any shape ---\n";
foreach ( array(
	'a login'                         => 'mentor-gamma-example',
	'a login in capitals'             => 'MENTOR-GAMMA-EXAMPLE',
	'an email address'                => 'mentor-gamma@example.test',
	'an email address in capitals'    => 'MENTOR-GAMMA@EXAMPLE.TEST',
	'a WordPress.org profile address' => 'https://profiles.wordpress.org/mentor-gamma-example/',
	'a profile address without the scheme' => 'profiles.wordpress.org/mentor-gamma-example/profile',
	'an @handle'                      => '@Mentor-Gamma-Example',
	'the record ID with white space'  => "  recMENT0000000001\n",
) as $label => $who ) {
	readers_reset();
	ck( "$label finds the same mentor, the same students and the same records", plain( build( 'mentor', array( 'mentor' => $who ) )['answer'] ) === $GOOD && build( 'mentor', array( 'mentor' => $who ) )['records'] === $built['records'], true );
}
readers_reset();
build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) );
$asked = array_values( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return 'get_users' === $call[0] && 'wpcpm_mentor_record_id' === $call[1]; } ) );
ck( 'a record ID is looked up by the meta key the mentors sync keeps it under, once', $asked, array( array( 'get_users', 'wpcpm_mentor_record_id' ) ) );
readers_reset();
build( 'mentor', array( 'mentor' => 'mentor-gamma-example' ) );
ck( 'and a login is looked up by the command\'s own lookup, with no query for a Mentors record ID', array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return 'get_users' === $call[0] && 'wpcpm_mentor_record_id' === $call[1]; } ), array() );

echo "\n--- Nobody: an unknown mentor, and an account that is no mentor's (Review Focus 3) ---\n";
foreach ( array(
	'a login nobody has'                       => 'nobody-example',
	'an address nobody has'                    => 'nobody@example.test',
	'a profile address nobody has'             => 'https://profiles.wordpress.org/nobody-example/',
	'a record ID in the right shape nobody has' => 'recMENT0000000099',
	'a record ID in the wrong shape'           => 'recMENT',
	'a student\'s login: the account is no mentor\'s' => 'pupil_login_alpha',
	'a student\'s address'                     => 'pupil-alpha@example.test',
	'a student\'s Students Reports record ID'  => 'recREPT0000000001',
) as $label => $who ) {
	readers_reset();
	$nobody = build( 'mentor', array( 'mentor' => $who ) );
	ck( "$label: found false and nothing else filled, no records", array( plain( $nobody['answer'] ), $nobody['records'] ), array( $NOBODY, array() ) );
}
readers_reset();
build( 'mentor', array( 'mentor' => 'nobody-example' ) );
ck( 'an unknown mentor costs no Engagement report and reads no account\'s meta', array_values( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return in_array( $call[0], array( 'engagement', 'user_meta' ), true ); } ) ), array() );
readers_reset();
build( 'mentor', array( 'mentor' => 'pupil_login_alpha' ) );
ck( 'an account that is no mentor\'s is found out by the Engagement report, which lists every mentor, before any account\'s meta is read', array_values( array_unique( array_map( static function ( $call ) { return $call[0]; }, $GLOBALS['reader_calls'] ) ) ), array( 'engagement' ) );

echo "\n--- A name is not a username without its accented letters, and a record ID is the same in the same case ---\n";
readers_reset();
$GLOBALS['readers']['accounts'][95]         = new WP_User( 95, array( 'user_login' => 'xample-example', 'user_email' => 'xample-mentor@example.test', 'user_nicename' => 'xample-example', 'display_name' => 'Mentor Display Xample' ) );
$GLOBALS['readers']['engagement']['rows'][] = readers_engagement_row( 95, array_merge( readers_mentors()['xi'], array( 'username' => 'xample-example', 'email' => 'xample-mentor@example.test', 'display_name' => 'Mentor Display Xample' ) ) );
ck( 'a username, an @handle and a profile address whole find the mentor', array( plain( build( 'mentor', array( 'mentor' => 'xample-example' ) )['answer'] )['found'], plain( build( 'mentor', array( 'mentor' => '@Xample-Example' ) )['answer'] )['found'], plain( build( 'mentor', array( 'mentor' => 'https://profiles.wordpress.org/xample-example/' ) )['answer'] )['found'] ), array( true, true, true ) );
foreach ( array( "\u{00C9}xample-example", "\u{00C9}XAMPLE-EXAMPLE", "https://profiles.wordpress.org/\u{00C9}xample-example/" ) as $who ) {
	$nobody = build( 'mentor', array( 'mentor' => $who ) );
	ck( "\"$who\" is a name, not the username without its first letter: nobody, and nothing of what was asked in the answer", array( plain( $nobody['answer'] ), $nobody['records'], false !== strpos( json_of( $nobody ), 'xample-example' ) ), array( $NOBODY, array(), false ) );
}
readers_reset();
ck( 'a Mentors record ID in another letter case is not that mentor: the database does not tell recABC from recabc, so the account found is checked again', array( plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) )['answer'] )['found'], plain( build( 'mentor', array( 'mentor' => 'recment0000000001' ) )['answer'] )['found'], plain( build( 'mentor', array( 'mentor' => 'RECMENT0000000001' ) )['answer'] )['found'], plain( build( 'mentor', array( 'mentor' => " recMENT0000000001\n" ) )['answer'] )['found'] ), array( true, false, false, true ) );
readers_reset();

echo "\n--- A mentor with no students, and a mentor account with no record ID (Review Focus 3) ---\n";
readers_reset();
$none = build( 'mentor', array( 'mentor' => 'mentor-xi-example' ) );
ck( 'a mentor with no students: found true, the record ID, not active, the Engagement view all (they are in none of the follow-ups), no dates, an empty list and no more; the list was last written when the sync says', plain( $none['answer'] ), array(
	'found'    => true,
	'mentor'   => array( 'record_id' => 'recMENT0000000002', 'active' => false, 'view' => 'all', 'last_sign_in' => null, 'last_visit' => null ),
	'students' => array(),
	'more'     => false,
	'read_at'  => '2026-10-24T06:00:00+00:00',
) );
ck( 'and the students are an empty list in the JSON, never an object', json_encode( $none['answer']['students'] ), '[]' );
ck( 'its records are the mentor\'s alone', $none['records'], array( 'recMENT0000000002' ) );
readers_reset();
// The student of this mentor has a track that holds the report row on their list.
$GLOBALS['readers']['roster']['recINST0000000006']['recPUPN0000000001'] = readers_track( 'beta', 'recPUPN0000000001', 'recINST0000000006', array( 'status' => 'In Sensei 50h', 'start' => '2026-05-05', 'reports' => array( 'recREPT0000000005' ) ) );
$bare = build( 'mentor', array( 'mentor' => 'mentor-nu-example' ) );
ck( 'a mentor account with no Mentors record ID: found, record_id null, and the students listed', array( plain( $bare['answer'] )['found'], plain( $bare['answer'] )['mentor']['record_id'], array_column( plain( $bare['answer'] )['students'], 'record_id' ) ), array( true, null, array( 'recPUPN0000000001' ) ) );
ck( 'and its records are the student\'s alone: there is no mentor ID to log', $bare['records'], array( 'recPUPN0000000001' ) );
ck( 'a record ID is a way to find a mentor that has one, and this one is found by its login and by its address instead', array( plain( build( 'mentor', array( 'mentor' => 'mentor-nu@example.test' ) )['answer'] )['found'], plain( build( 'mentor', array( 'mentor' => 'recMENT0000000009' ) )['answer'] )['found'] ), array( true, false ) );
readers_reset();
$GLOBALS['readers']['user_meta'][92]['wpcpm_mentor_record_id'] = 'not a record id';
ck( 'a Mentors record ID that is no record ID is not given', plain( build( 'mentor', array( 'mentor' => 'mentor-nu-example' ) )['answer'] )['mentor']['record_id'], null );
readers_reset();
unset( $GLOBALS['readers']['user_meta'][92]['wpcpm_mentees_updated'] );
ck( 'a list the sync never wrote says null for its time, and the key is there', ( static function () { $b = build( 'mentor', array( 'mentor' => 'mentor-nu-example' ) ); return array( array_key_exists( 'read_at', $b['answer'] ), $b['answer']['read_at'] ); } )(), array( true, null ) );

echo "\n--- The Engagement view (the tab's, one of views()) ---\n";
$views = array_keys( WPCPM_Mentor_Engagement::views() );
$quiet_week = array( 'last_sign_in' => 1792000000, 'last_visit' => 1792000000, 'last_seen' => 1792000000, 'seen_7' => false, 'seen_30' => false );
$view_cases = array(
	'a mentor seen, acted, with students and weekly hours: the students view'                              => array( array(), 'students' ),
	'with no students now: the active view'                                                                  => array( array( 'students_now' => 0 ), 'active' ),
	'who stopped mentoring: all, the one view that holds them'                                               => array( array( 'active' => false ), 'all' ),
	'an Administrator who mentors: the staff view'                                                           => array( array( 'staff' => true ), 'staff' ),
	'who never signed in or was seen: never, before every other follow-up'                                   => array( array( 'last_sign_in' => 0, 'last_visit' => 0, 'last_seen' => 0, 'seen_7' => false, 'seen_30' => false, 'acted' => false, 'weekly_hours' => false ), 'never' ),
	'not seen in 30 days: quiet, before idle and no hours'                                                   => array( $quiet_week + array( 'acted' => false, 'weekly_hours' => false ), 'quiet' ),
	'seen but no program action: idle, before no hours'                                                      => array( array( 'acted' => false, 'weekly_hours' => false ), 'idle' ),
	'acted, with students and no weekly hours: no-hours'                                                     => array( array( 'weekly_hours' => false ), 'no-hours' ),
);
$seen_views = array();
foreach ( $view_cases as $label => $case ) {
	$seen_views[] = view_of_91( $case[0] );
	ck( $label, end( $seen_views ), $case[1] );
}
ck( 'and every view an answer says is one of the tab\'s, and the eight cases reached all eight', array( array_diff( $seen_views, $views ), count( array_unique( $seen_views ) ) ), array( array(), count( $views ) ) );
ck( 'the mentor answer says whether they are active as the tab\'s row does', array( plain( build( 'mentor', array( 'mentor' => 'mentor-xi-example' ) )['answer'] )['mentor']['active'], plain( build( 'mentor', array( 'mentor' => 'mentor-omicron-example' ) )['answer'] )['mentor']['active'] ), array( false, true ) );
readers_reset();
set_engagement_row( 91, array( 'last_sign_in' => 0, 'last_visit' => 1792800000 ) );
ck( 'a mentor who has visited and never signed in has a visit date and no sign-in date', array_intersect_key( plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) )['answer'] )['mentor'], array( 'last_sign_in' => 0, 'last_visit' => 0 ) ), array( 'last_sign_in' => null, 'last_visit' => '2026-10-24' ) );
readers_reset();
set_engagement_row( 91, array( 'last_sign_in' => '1792800000', 'last_visit' => 'yesterday' ) );
ck( 'a time held as text is read, and one that is no time is none', array_intersect_key( plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) )['answer'] )['mentor'], array( 'last_sign_in' => 0, 'last_visit' => 0 ) ), array( 'last_sign_in' => '2026-10-24', 'last_visit' => null ) );

echo "\n--- Twenty-five students and more ---\n";
$list_of = static function ( $current, $past ) {
	readers_reset();
	$rows = array();
	// A track for each report row, so that each student has a Students record ID of their own.
	$hold = static function ( $n, $report ) {
		$id = sprintf( 'recMANYT%09d', $n );
		$GLOBALS['readers']['roster']['recINST0000000002'][ $id ] = readers_track( 'alpha', $id, 'recINST0000000002', array( 'status' => 'In Sensei', 'reports' => array( $report ) ) );
	};
	for ( $n = 1; $n <= $past; $n++ ) { $rows[] = readers_mentee( 'epsilon', sprintf( 'recMANYS%09d', 100 + $n ), array( 'status' => 'Graduate', 'is_past' => true ) ); $hold( 100 + $n, sprintf( 'recMANYS%09d', 100 + $n ) ); }
	for ( $n = 1; $n <= $current; $n++ ) { $rows[] = readers_mentee( 'alpha', sprintf( 'recMANYS%09d', $n ), array( 'status' => 'In Sensei' ) ); $hold( $n, sprintf( 'recMANYS%09d', $n ) ); }
	$GLOBALS['readers']['user_meta'][91]['wpcpm_mentees'] = $rows;
	$built = build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) );
	return array( count( $built['answer']['students'] ), $built['answer']['more'], count( $built['records'] ), end( $built['answer']['students'] )['record_id'] );
};
ck( 'twenty-five students: all of them, and no more', $list_of( 25, 0 ), array( 25, false, 26, 'recMANYT000000025' ) );
ck( 'twenty-six: twenty-five, and more; the mentor and the twenty-five are the records', $list_of( 26, 0 ), array( 25, true, 26, 'recMANYT000000025' ) );
ck( 'twenty-four current and three past: the current first, so the cut falls among the past, and says more', $list_of( 24, 3 ), array( 25, true, 26, 'recMANYT000000101' ) );
ck( 'a mentor with only past students lists them', $list_of( 0, 2 ), array( 2, false, 3, 'recMANYT000000102' ) );

// The hours are worked out for the rows that are kept, and for no other (1.123.48). `logged_count()` looks the
// student's account up by the Students Reports record ID, once for each row it is asked about, and that lookup
// is the only `get_users` call keyed by it: counting those calls counts the hours worked out.
$hours_looked_up = static function ( $current ) {
	readers_reset();
	$rows = array();
	for ( $n = 1; $n <= $current; $n++ ) { $rows[] = readers_mentee( 'alpha', sprintf( 'recMANYS%09d', $n ), array( 'status' => 'In Sensei', 'hours' => (string) ( 10 + $n ) ) ); }
	$GLOBALS['readers']['user_meta'][91]['wpcpm_mentees'] = $rows;
	$GLOBALS['reader_calls'] = array();
	$built = build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) );
	$looked = count( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return 'get_users' === $call[0] && isset( $call[1] ) && 'wpcpm_student_record_id' === $call[1]; } ) );
	return array( $looked, count( $built['answer']['students'] ), $built['answer']['more'], array_column( $built['answer']['students'], 'hours' ) === range( 11, 10 + min( $current, 25 ) ) );
};
ck( 'with thirty students the hours are worked out for the twenty-five listed, and for the five cut they are not', $hours_looked_up( 30 ), array( 25, 25, true, true ) );
ck( 'with twenty-five, for each; with three, for each', array( $hours_looked_up( 25 )[0], $hours_looked_up( 3 )[0] ), array( 25, 3 ) );

echo "\n--- The Students record ID, as wpcpm/find-student gives it ---\n";

/** The students a build of mentor 91 lists, as [Students record ID or null, hours] pairs, in the answer's order. */
function listed_by_91() {
	return array_map( static function ( $student ) { return array( $student['record_id'], $student['hours'] ); }, plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) )['answer'] )['students'] );
}
/** A track of another person that holds the Students Reports record ID rec...01, which the mentor's list also holds. */
function other_track_holding_91_first( array $facts = array() ) {
	$GLOBALS['readers']['roster']['recINST0000000006']['recPUPB0000000002'] = readers_track( 'beta', 'recPUPB0000000002', 'recINST0000000006', array_merge( array( 'status' => 'In Sensei', 'start' => '2025-01-01', 'reports' => array( 'recREPT0000000001' ) ), $facts ) );
}
$track_named = static function ( $has_mentor ) { return array( 'has_mentor' => $has_mentor ); };

readers_reset();
// The same fixture person, found by find-student and listed by their mentor: the mentor the account's card names
// is on the track that holds the report the account follows, and that track is the Students record ID listed.
$GLOBALS['readers']['user_meta'][502]['wpcpm_student_mentor'] = readers_mentor_card( 'recMENT0000000001' );
$found_by_find_student = array();
foreach ( array( 'pupil-alpha@example.test', 'pupil-epsilon@example.test' ) as $address ) {
	foreach ( plain( build( 'students', array( 'who' => $address ) )['answer'] )['people'] as $person ) {
		foreach ( $person['tracks'] as $track ) {
			if ( 'recMENT0000000001' === $track['mentor_record_id'] ) { $found_by_find_student[] = $track['record_id']; }
		}
	}
}
sort( $found_by_find_student );
$listed_ids = array_values( array_unique( array_filter( array_column( plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) )['answer'] )['students'], 'record_id' ) ) ) );
sort( $listed_ids );
ck( 'a fixture person whose track find-student gives mentor M\'s record ID is listed by M under the same Students record ID, and the tracks M\'s other students are listed under are the same ones', array( $found_by_find_student, $listed_ids ), array( array( 'recPUPA0000000002', 'recPUPE0000000001' ), array( 'recPUPA0000000002', 'recPUPE0000000001' ) ) );

readers_reset();
ck( 'a report row that no track holds is null, and a report row of a track held twice by the list is that track twice', array_column( listed_by_91(), 0 ), array( 'recPUPA0000000002', 'recPUPA0000000002', null, 'recPUPE0000000001' ) );

echo "\n--- Two tracks hold the report row ---\n";
readers_reset();
other_track_holding_91_first();
ck( 'two tracks hold it and the first names a mentor: the one that names one', array_column( listed_by_91(), 0 )[0], 'recPUPA0000000002' );
readers_reset();
other_track_holding_91_first( array( 'has_mentor' => true ) );
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['has_mentor'] = false;
ck( 'and the other way round: the one that names one is the other', array_column( listed_by_91(), 0 )[0], 'recPUPB0000000002' );
readers_reset();
other_track_holding_91_first( array( 'has_mentor' => true ) );
ck( 'both name a mentor: null, the roster cannot tell', array_column( listed_by_91(), 0 )[0], null );
readers_reset();
other_track_holding_91_first();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['has_mentor'] = false;
ck( 'neither names one: null', array_column( listed_by_91(), 0 )[0], null );
readers_reset();
other_track_holding_91_first();
$GLOBALS['readers']['roster']['recINST0000000006']['recPUPB0000000003'] = readers_track( 'beta', 'recPUPB0000000003', 'recINST0000000006', array( 'status' => 'In Sensei', 'has_mentor' => true, 'reports' => array( 'recREPT0000000001' ) ) );
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['has_mentor'] = false;
ck( 'three hold it and exactly one names a mentor: that one', array_column( listed_by_91(), 0 )[0], 'recPUPB0000000003' );

echo "\n--- A Duplicated or SPAM row is never chosen ---\n";
foreach ( array( 'Duplicated', 'SPAM' ) as $hidden ) {
	readers_reset();
	other_track_holding_91_first( array( 'status' => $hidden, 'has_mentor' => true ) );
	$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['has_mentor'] = false;
	ck( "a $hidden row that names a mentor and holds the report beside a live one that names none: the live one, as the hidden row is no track", array_column( listed_by_91(), 0 )[0], 'recPUPA0000000002' );
	readers_reset();
	other_track_holding_91_first( array( 'status' => $hidden, 'has_mentor' => true ) );
	$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['reports'] = array( 'recREPT0000000002' );
	ck( "and a $hidden row alone holding it: null", array_column( listed_by_91(), 0 )[0], null );
}

echo "\n--- Where the roster is read, and when it cannot tell ---\n";
readers_reset();
build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) );
$rosters_read = array_values( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return in_array( $call[0], array( 'roster', 'unlinked' ), true ); } ) );
ck( 'every roster and the rows that name no institution are read once, as find-student reads them', $rosters_read, array( array( 'roster', 'recINST0000000001' ), array( 'roster', 'recINST0000000002' ), array( 'roster', 'recINST0000000006' ), array( 'unlinked' ) ) );
readers_reset();
build( 'mentor', array( 'mentor' => 'recMENT0000000002' ) );
build( 'mentor', array( 'mentor' => 'nobody-example' ) );
ck( 'a mentor with no students, and nobody, read no roster', array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return in_array( $call[0], array( 'roster', 'unlinked' ), true ); } ), array() );
readers_reset();
$GLOBALS['readers']['roster']   = array();
$GLOBALS['readers']['unlinked'] = array();
$built = build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) );
ck( 'a roster that was never read cannot tell: every student is listed, under a null record ID, with the hours and the start as before, and the records are the mentor\'s alone', array( array_column( plain( $built['answer'] )['students'], 'record_id' ), array_column( plain( $built['answer'] )['students'], 'hours' ), $built['records'] ), array( array( null, null, null, null ), array( 135.5, 0, null, 400 ), array( 'recMENT0000000001' ) ) );
readers_reset();
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['reports'] = array( 'recREPT0000000001', 'recREPT0000000002' );
$GLOBALS['readers']['roster']['recINST0000000002']['recPUPA0000000002']['record_id'] = 'not a record id';
ck( 'a roster row whose own record ID is no record ID is no track, so the report rows it held are held by none', array_column( listed_by_91(), 0 ), array( null, null, null, 'recPUPE0000000001' ) );

echo "\n--- One rule for both: the track a report row belongs to ---\n";
$answers_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ability-answers.php' );
$mentor_tracks_body = preg_match( '/\n\tprivate static function mentor_tracks\(.*?\n\t\}\n/s', $answers_source, $found_body ) ? $found_body[0] : '';
$mentor_body_source = preg_match( '/\n\tpublic static function mentor\(.*?\n\t\}\n/s', $answers_source, $found_body ) ? $found_body[0] : '';
ck( 'find-student\'s rule for the mentor of a track and the mentor builder\'s rule for the track of a report row are one private helper each of them calls, written once: the test for a report row in a track\'s reports is in the source once, and so is the choice among the tracks that hold it',
	array(
		substr_count( $answers_source, "in_array( \$report, \$row['reports'], true )" ),
		false !== strpos( $mentor_tracks_body, 'self::tracks_holding(' ) && false !== strpos( $mentor_tracks_body, 'self::track_of_report(' ),
		false !== strpos( $mentor_body_source, 'self::tracks_holding(' ) && false !== strpos( $mentor_body_source, 'self::track_of_report(' ),
		substr_count( $answers_source, "! empty( \$rows[ \$record ]['has_mentor'] )" ),
	),
	array( 1, true, true, 1 ) );

echo "\n--- The hours the Mentor Report Card shows ---\n";
readers_reset();
$GLOBALS['readers']['user_meta'][502]['wpcpm_student_program']['hours'] = '410';
$GLOBALS['readers']['user_meta'][501]['wpcpm_student_program']['hours'] = '12.5';
ck( 'a student whose account holds a count has that count, as the card prints it, over the list\'s own copy (400 and 0)', array_column( listed_by_91(), 1 ), array( 135.5, 12.5, null, 410 ) );
$GLOBALS['readers']['user_meta'][502]['wpcpm_student_program']['hours'] = '0';
ck( 'a 0 there is a count and wins over the list\'s 400', array_column( listed_by_91(), 1 )[3], 0 );
$GLOBALS['readers']['user_meta'][502]['wpcpm_student_program']['hours'] = '';
ck( 'an empty one falls through to the list\'s', array_column( listed_by_91(), 1 )[3], 400 );
$GLOBALS['readers']['user_meta'][502]['wpcpm_student_program']['hours'] = 'n/a';
ck( 'and one that is no number is no number, as the card prints it', array_column( listed_by_91(), 1 )[3], null );
readers_reset();
$GLOBALS['readers']['user_meta'][502]['wpcpm_student_program']['hours'] = '410';
people_walk( 'a program row\'s hours, with every field of its person beside them', build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) ), $MENTOR_PEOPLE );

echo "\n--- What it cannot read is nobody ---\n";
readers_reset();
foreach ( array( 'no input' => null, 'text' => 'recMENT0000000001', 'an empty list' => array(), 'a number' => 12, 'no mentor' => array( 'extra' => 'recMENT0000000001' ), 'a mentor that is not text' => array( 'mentor' => 12 ), 'a list for a mentor' => array( 'mentor' => array( 'recMENT0000000001' ) ), 'an empty mentor' => array( 'mentor' => '' ), 'a mentor of white space' => array( 'mentor' => " \n " ), 'a mentor of 500 characters' => array( 'mentor' => str_repeat( 'x', 500 ) ) ) as $label => $input ) {
	$built = build( 'mentor', $input );
	ck( "$label: nobody, no records", array( plain( $built['answer'] ), $built['records'] ), array( $NOBODY, array() ) );
}
ck( 'a key the schema does not name is ignored', plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001', 'extra' => 'x' ) )['answer'] ), $GOOD );

echo "\n--- The input schema: mentor is required, from three characters ---\n";
$in = WPCPM_Ability_Answers::input_schema( 'mentor' );
ck( 'an object that requires mentor and takes nothing else', array( isset( $in['type'] ) ? $in['type'] : null, isset( $in['required'] ) ? $in['required'] : null, isset( $in['properties'] ) ? array_keys( $in['properties'] ) : null, isset( $in['additionalProperties'] ) ? $in['additionalProperties'] : 'missing' ), array( 'object', array( 'mentor' ), array( 'mentor' ), false ) );
ck( 'mentor is a string of 3 to 200 characters, room for a profile address', array( $in['properties']['mentor']['type'], $in['properties']['mentor']['minLength'], $in['properties']['mentor']['maxLength'] ), array( 'string', 3, 200 ) );
ck( 'it says what it takes: a Mentors record ID, a username, an email address or a WordPress.org profile address', array( '' !== (string) $in['properties']['mentor']['description'], false !== strpos( $in['properties']['mentor']['description'], 'Mentors record ID' ), false !== strpos( $in['properties']['mentor']['description'], 'email address' ), false !== strpos( $in['properties']['mentor']['description'], 'profile address' ) ), array( true, true, true, true ) );

echo "\n--- The output schema ---\n";
$out = WPCPM_Ability_Answers::output_schema( 'mentor' );
$mentor_schema = $out['properties']['mentor'];
$student_schema = $out['properties']['students']['items'];
ck( 'a closed object of the five keys, all required', array( $out['type'], array_keys( $out['properties'] ), $out['required'], $out['additionalProperties'] ), array( 'object', array( 'found', 'mentor', 'students', 'more', 'read_at' ), array( 'found', 'mentor', 'students', 'more', 'read_at' ), false ) );
ck( 'the mentor is an object or null, closed, with the five keys the answer holds', array( $mentor_schema['type'], array_keys( $mentor_schema['properties'] ), $mentor_schema['required'], $mentor_schema['additionalProperties'] ), array( array( 'object', 'null' ), array( 'record_id', 'active', 'view', 'last_sign_in', 'last_visit' ), array( 'record_id', 'active', 'view', 'last_sign_in', 'last_visit' ), false ) );
ck( 'the mentor\'s record ID, the two dates, the student\'s Students record ID and start may be null; the view is a string and active a boolean', array( $mentor_schema['properties']['record_id']['type'], $mentor_schema['properties']['last_sign_in']['type'], $mentor_schema['properties']['last_visit']['type'], $mentor_schema['properties']['view']['type'], $mentor_schema['properties']['active']['type'], $student_schema['properties']['record_id']['type'], $student_schema['properties']['start']['type'] ), array( array( 'string', 'null' ), array( 'string', 'null' ), array( 'string', 'null' ), 'string', 'boolean', array( 'string', 'null' ), array( 'string', 'null' ) ) );
ck( 'and the dates, the student\'s start among them, are Y-m-d, which core checks by pattern', array( preg_match( '/' . $mentor_schema['properties']['last_sign_in']['pattern'] . '/', '2026-10-23' ), preg_match( '/' . $mentor_schema['properties']['last_visit']['pattern'] . '/', '2026-10-23T00:00:00+00:00' ), preg_match( '/' . $student_schema['properties']['start']['pattern'] . '/', '2026-03-02' ), preg_match( '/' . $student_schema['properties']['start']['pattern'] . '/', '2026-03-02T00:00:00+00:00' ) ), array( 1, 0, 1, 0 ) );
ck( 'the view is one of the tab\'s eight, spelled out in the schema, in the order the tab prints them: the enum is the keys of views()', isset( $mentor_schema['properties']['view']['enum'] ) ? $mentor_schema['properties']['view']['enum'] : null, array_keys( WPCPM_Mentor_Engagement::views() ) );
ck( 'a student is a closed object of the four keys', array( array_keys( $student_schema['properties'] ), $student_schema['required'], $student_schema['additionalProperties'], $out['properties']['students']['type'] ), array( array( 'record_id', 'status', 'hours', 'start' ), array( 'record_id', 'status', 'hours', 'start' ), false, 'array' ) );
ck( 'the view\'s words name every view of the tab, so a view the tab grows is one the words are changed for', array_values( array_filter( $views, static function ( $view ) use ( $mentor_schema ) { return false === strpos( $mentor_schema['properties']['view']['description'], "`$view`" ); } ) ), array() );
ck( 'every key says what it is', array_values( array_filter( array_merge( $out['properties'], $mentor_schema['properties'], $student_schema['properties'] ), static function ( $schema ) { return '' === (string) ( isset( $schema['description'] ) ? $schema['description'] : '' ); } ) ), array() );
ck( 'the students\' record ID says whose it is: the Students record ID wpcpm/find-student gives for the track, null when the roster cannot tell, and what the Mentor Report Card lists it by', array_map( static function ( $words ) use ( $student_schema ) { return false !== strpos( $student_schema['properties']['record_id']['description'], $words ); }, array( 'Students record ID', 'wpcpm/find-student', 'Null when the roster cannot tell', 'Students Reports' ) ), array( true, true, true, true ) );
ck( 'and the read time says the Students record IDs are the roster\'s, read at its own time', false !== strpos( $out['properties']['read_at']['description'], 'roster' ), true );
ck( 'the schema has no latest report\'s month: the site keeps no date for a report, so no answer could fill it', array_key_exists( 'latest_report', $student_schema['properties'] ), false );

echo "\n--- The people walk: no name, address or username, whatever was asked (Review Focus 5) ---\n";
readers_reset();
$forms = array(
	'the Mentors record ID'                  => 'recMENT0000000001',
	'a login'                                => 'mentor-gamma-example',
	'an email address'                       => 'mentor-gamma@example.test',
	'an email address in capitals'           => 'MENTOR-GAMMA@EXAMPLE.TEST',
	'a profile address'                      => 'https://profiles.wordpress.org/mentor-gamma-example/',
	'an @handle'                             => '@mentor-gamma-example',
	'the login in capitals'                  => 'MENTOR-GAMMA-EXAMPLE',
	'the login the walk looks for besides, which no account holds' => 'mentor_login_gamma',
	'a mentor with no record ID, by login'   => 'mentor-nu-example',
	'a mentor with no record ID, by address' => 'mentor-nu@example.test',
	'a mentor with no students'              => 'recMENT0000000002',
	'an Administrator who mentors'           => 'mentor-omicron-example',
	'a student\'s login'                     => 'pupil_login_alpha',
	'a student\'s address'                   => 'pupil-alpha@example.test',
	'a student\'s profile address'           => 'https://profiles.wordpress.org/pupil-alpha-example/',
	'an unknown login'                       => 'nobody-example',
	'an unknown address'                     => 'nobody@example.test',
	'an unknown record ID'                   => 'recMENT0000000099',
	'a name'                                 => 'Mentor Name Gamma',
);
foreach ( $forms as $label => $who ) {
	people_walk( $label, build( 'mentor', array( 'mentor' => $who ) ), $MENTOR_PEOPLE );
}
ck( 'and the walk had people to find: the five pupils, the mentor on their cards and the three other mentors, each with every field', count( $MENTOR_PEOPLE ), 10 );

// The walk must be able to fail, or the checks above prove nothing.
$planted = plain( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) )['answer'] );
$planted['students'][0]['status'] = 'Pupil Name ALPHA';
ck( 'the walk finds a student\'s name planted in a status, in another case', people_found( $planted, $MENTOR_PEOPLE ), array( 'Pupil Name Alpha' ) );
$planted['students'][0]['status'] = 'tutor name beta';
ck( 'and a tutor\'s', people_found( $planted, $MENTOR_PEOPLE ), array( 'Tutor Name Beta' ) );
$planted['students'][0]['status'] = 'mentor_login_gamma';
ck( 'and the mentor\'s login', people_found( $planted, $MENTOR_PEOPLE ), array( 'mentor_login_gamma' ) );
$planted['students'][0]['status'] = 'Mentor Display Gamma';
ck( 'and the mentor\'s display name', people_found( $planted, $MENTOR_PEOPLE ), array( 'Mentor Display Gamma' ) );
$planted['students'][0]['status'] = 'https://profiles.wordpress.org/mentor-gamma-example/';
ck( 'and the mentor\'s profile address, and the username inside it', people_found( $planted, $MENTOR_PEOPLE ), array( 'mentor-gamma-example', 'https://profiles.wordpress.org/mentor-gamma-example/' ) );
$planted['students'][0]['status'] = 'In Sensei';
ck( 'and the answer as it was holds none', people_found( $planted, $MENTOR_PEOPLE ), array() );
$half = $MENTOR_PEOPLE;
unset( $half['mentor omicron']['accessibility'] );
ck( 'and a walk given half a person says so', people_found( build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) ), $half ), array( 'the person mentor omicron was given without a accessibility' ) );

echo "\n--- The account meta a lookup of a mentor reads ---\n";
$expected_meta = array( 'wpcpm_mentees', 'wpcpm_mentees_updated', 'wpcpm_mentor_record_id', 'wpcpm_student_program' );
$meta_read     = array_values( array_unique( $GLOBALS['mentor_meta_read'] ) );
sort( $meta_read );
ck( 'over every mentor-students build above, the account meta read is the Mentors record ID, the students\' list and when it was written, and the student\'s program row, which only WPCPM_Mentors_Dashboard::logged_count() reads and of which only the hours leave: no profile, no address, no card, no course', $meta_read, $expected_meta );
ck( 'and those builds did read accounts', count( $GLOBALS['mentor_meta_read'] ) > 30, true );
preg_match_all( '/get_user_meta\( \$id, ([A-Za-z_]+::[A-Z_]+), true \)/', $engagement_source, $report_reads );
$report_reads = array_values( array_unique( $report_reads[1] ) );
sort( $report_reads );
ck( 'the Engagement report that the active flag, the dates and the view come from reads, of every mentor, the active flag, the two counts of students, the sign-in and visit record and the schedule, and nothing of a person: none of it goes out but the flag, the two dates and the view', $report_reads, array( 'WPCPM_Mentor_Availability::META', 'WPCPM_Mentors_Sync::META_ACTIVE', 'WPCPM_Mentors_Sync::META_COUNT', 'WPCPM_Mentors_Sync::META_PAST_COUNT', 'WPCPM_Sign_Ins::META_SIGN_IN', 'WPCPM_Sign_Ins::META_VISIT' ) );

echo "\n=== Mentor applications (wpcpm/mentor-applications) ===\n";
readers_reset();

$APPLICANTS = readers_applicants();
$day_of     = static function ( $days ) { return gmdate( 'Y-m-d', $GLOBALS['reader_now'] - $days * 86400 - 43200 ); };
$ref_of     = static function ( $id ) { return sprintf( 'MAPP-2026-%04d', $id ); };

/** An application's row of the answer, as the fixtures say it must read. */
function app_row( $id, $arrived_days_ago, $state, $in_state, $country, array $languages, $hours, array $areas, $teacher, $confirmed, array $flags ) {
	return array(
		'reference'      => sprintf( 'MAPP-2026-%04d', $id ),
		'received'       => gmdate( 'Y-m-d', $GLOBALS['reader_now'] - $arrived_days_ago * 86400 - 43200 ),
		'state'          => $state,
		'days_in_state'  => $in_state,
		'country'        => $country,
		'languages'      => $languages,
		'hours_per_week' => $hours,
		'areas'          => $areas,
		'teacher'        => $teacher,
		'confirmed'      => $confirmed,
		'flags'          => $flags,
	);
}

/** The seven rows the fixtures hold in the three states, by ID. */
function app_rows() {
	return array(
		701 => app_row( 701, 2, 'new', 1, 'Poland', array( 'Polish', 'English' ), 3, array( 'Training' ), false, true, array( 'duplicate' ) ),
		702 => app_row( 702, 9, 'held', 9, 'Kenya', array( 'Swahili', 'English' ), 2.5, array( 'Polyglots', 'Community' ), true, false, array( 'held' ) ),
		703 => app_row( 703, 15, 'info', 4, 'Brazil', array( 'Portuguese' ), 10, array(), false, true, array() ),
		704 => app_row( 704, 20, 'second', 3, 'Japan', array( 'Japanese' ), 4, array( 'Core' ), true, false, array( 'duplicate' ) ),
		705 => app_row( 705, 70, 'selected', 40, 'India', array( 'Hindi', 'English', 'Marathi' ), 5, array( 'Support' ), false, true, array() ),
		706 => app_row( 706, 30, 'selected', 10, 'Nigeria', array( 'English', 'Nigerian Pidgin', 'Hausa' ), 2, array( 'Design' ), false, false, array() ),
		707 => app_row( 707, 120, 'lapsed', 5, 'Spain', array( 'Spanish', 'Catalan' ), 1.5, array( 'Accessibility' ), true, true, array() ),
	);
}

/** The IDs, in the answer's order, of the applications an answer lists. */
function listed( $built ) {
	return array_map( static function ( $row ) { return (int) substr( $row['reference'], -4 ); }, plain( $built['answer'] )['applications'] );
}

/** $count more applications in one state, each from a day older than the one before, all of the first applicant. */
function many_applications( $count, $state = 'new' ) {
	for ( $n = 1; $n <= $count; $n++ ) {
		$id = 800 + $n;
		$GLOBALS['readers']['applications'][ $id ] = readers_application( $id, 'alpha', $state, 100 + $n, array( 'fields' => array( 'country' => 'Poland', 'teacher' => 'No', 'languages' => array( 'English' ), 'hours' => '3' ) ) );
	}
}

$before = $GLOBALS['reader_now'];
$built  = build( 'applications' );
$a      = plain( $built['answer'] );
$ROWS   = app_rows();
ck( 'a build is the answer and the references of the rows it was made of', array_keys( $built ), array( 'answer', 'records' ) );
ck( 'the answer\'s keys, in the order the schema lists them', array_keys( $a ), array( 'applications', 'totals', 'more' ) );
ck( 'with nothing asked, the open ones are listed (new, held, more information asked for, a second opinion), the newest received first, each by its reference', array_column( $a['applications'], 'reference' ), array( 'MAPP-2026-0701', 'MAPP-2026-0702', 'MAPP-2026-0703', 'MAPP-2026-0704' ) );
ck( 'each row has exactly the eleven keys, in the order the brief lists them', array_values( array_unique( array_map( 'array_keys', $a['applications'] ), SORT_REGULAR ) ), array( array( 'reference', 'received', 'state', 'days_in_state', 'country', 'languages', 'hours_per_week', 'areas', 'teacher', 'confirmed', 'flags' ) ) );
ck( 'and every row is what the fixtures say it is: the day received in UTC, the state, the days since it entered that state, the country, the languages and the contribution areas as ticked, the hours a week as a number (a fraction stays one), the teacher answer as a yes or no, whether the address is confirmed and the flags', $a['applications'], array( $ROWS[701], $ROWS[702], $ROWS[703], $ROWS[704] ) );
ck( 'the totals are the feed\'s open and selected and the lapsed count, and nothing is cut', array( $a['totals'], $a['more'] ), array( array( 'open' => 4, 'selected' => 2, 'lapsed' => 1 ), false ) );
ck( '`records` lists the references of the rows that went out, in the answer\'s order', $built['records'], array( 'MAPP-2026-0701', 'MAPP-2026-0702', 'MAPP-2026-0703', 'MAPP-2026-0704' ) );
ck( 'no input, an empty list and the state open are one question', array( plain( build( 'applications', array() ) ), plain( build( 'applications', array( 'state' => 'open' ) ) ), plain( build( 'applications', array( 'state' => 'open', 'limit' => 25 ) ) ) ), array( plain( $built ), plain( $built ), plain( $built ) ) );

echo "\n--- Each state's filter, newest received first ---\n";
$by_state = array( 'open' => array( 701, 702, 703, 704 ), 'selected' => array( 706, 705 ), 'lapsed' => array( 707 ) );
foreach ( $by_state as $state => $ids ) {
	$one = build( 'applications', array( 'state' => $state ) );
	ck( "$state: lists " . implode( ', ', $ids ) . ' and no other, each as the fixtures say', array( listed( $one ), plain( $one['answer'] )['applications'] ), array( $ids, array_values( array_map( static function ( $id ) use ( $ROWS ) { return $ROWS[ $id ]; }, $ids ) ) ) );
	ck( "$state: the totals are the same whichever state is listed", plain( $one['answer'] )['totals'], array( 'open' => 4, 'selected' => 2, 'lapsed' => 1 ) );
	ck( "$state: records are the references of the rows listed", $one['records'], array_map( $ref_of, $ids ) );
}
ck( 'a row filed as spam, declined or active is in no state\'s list, and its reference is in no answer', array_values( array_filter( array( 'open', 'selected', 'lapsed' ), static function ( $state ) { return array() !== array_intersect( array( 708, 709, 710 ), listed( build( 'applications', array( 'state' => $state ) ) ) ); } ) ), array() );
ck( 'the state of a row is one of the six', array_values( array_unique( array_merge( array_column( plain( build( 'applications' )['answer'] )['applications'], 'state' ), array_column( plain( build( 'applications', array( 'state' => 'selected' ) )['answer'] )['applications'], 'state' ), array_column( plain( build( 'applications', array( 'state' => 'lapsed' ) )['answer'] )['applications'], 'state' ) ) ) ), array( 'new', 'held', 'info', 'second', 'selected', 'lapsed' ) );

echo "\n--- A state the query lets through by mistake is never listed (spam, declined and active) ---\n";
$GLOBALS['readers']['applications_leak'] = true;
foreach ( array( 'open' => array( 701, 702, 703, 704 ), 'selected' => array( 706, 705 ), 'lapsed' => array( 707 ) ) as $state => $ids ) {
	$one = build( 'applications', array( 'state' => $state ) );
	ck( "$state, with every post handed to the build whatever its state: only the rows in that state are listed, and none of the three never listed", array( listed( $one ), $one['records'] ), array( $ids, array_map( $ref_of, $ids ) ) );
}
$GLOBALS['readers']['applications_leak'] = false;

echo "\n--- The state is the open states, selected or lapsed; anything else is ignored as if it were not asked ---\n";
foreach ( array( 'spam' => 'spam', 'declined' => 'declined', 'active' => 'active', 'a single open state' => 'held', 'an empty one' => '', 'a number' => 12, 'a list' => array( 'selected' ), 'in capitals' => 'SELECTED', 'padded' => ' selected ' ) as $label => $state ) {
	ck( "$label: the open ones, as ever, and none of the three never listed", array( listed( build( 'applications', array( 'state' => $state ) ) ), $built['records'] === build( 'applications', array( 'state' => $state ) )['records'] ), array( array( 701, 702, 703, 704 ), true ) );
}

echo "\n--- The limit: 1 to 25, 25 by default; what is not a limit is ignored ---\n";
foreach ( array( 1 => array( array( 701 ), true ), 2 => array( array( 701, 702 ), true ), 3 => array( array( 701, 702, 703 ), true ), 4 => array( array( 701, 702, 703, 704 ), false ), 5 => array( array( 701, 702, 703, 704 ), false ), 25 => array( array( 701, 702, 703, 704 ), false ) ) as $limit => $want ) {
	$one = build( 'applications', array( 'limit' => $limit ) );
	ck( "limit $limit: " . count( $want[0] ) . ' listed, the newest first, and more is ' . var_export( $want[1], true ), array( listed( $one ), plain( $one['answer'] )['more'], $one['records'] ), array( $want[0], $want[1], array_map( $ref_of, $want[0] ) ) );
}
ck( 'the limit and the state go together: the one newest selected application', array( listed( build( 'applications', array( 'state' => 'selected', 'limit' => 1 ) ) ), plain( build( 'applications', array( 'state' => 'selected', 'limit' => 1 ) )['answer'] )['more'] ), array( array( 706 ), true ) );
ck( 'and a limit at the count lists all of them and says nothing more', array( listed( build( 'applications', array( 'state' => 'selected', 'limit' => 2 ) ) ), plain( build( 'applications', array( 'state' => 'selected', 'limit' => 2 ) )['answer'] )['more'] ), array( array( 706, 705 ), false ) );
foreach ( array( 'zero' => 0, 'a negative number' => -3, '26' => 26, '500' => 500, 'text' => 'many', 'a fraction' => 2.5, 'a list' => array( 2 ), 'null' => null, 'a boolean' => true, 'an empty string' => '' ) as $label => $limit ) {
	ck( "a limit of $label is ignored: the default of 25", listed( build( 'applications', array( 'limit' => $limit ) ) ), array( 701, 702, 703, 704 ) );
}
ck( 'a limit that arrives as digits, or as a whole number with a point, is read as the number', array( listed( build( 'applications', array( 'limit' => '2' ) ) ), listed( build( 'applications', array( 'limit' => 2.0 ) ) ) ), array( array( 701, 702 ), array( 701, 702 ) ) );

echo "\n--- Thirty open applications: twenty-five listed, and more ---\n";
readers_reset();
many_applications( 30 );
$GLOBALS['readers']['feed_today']['open'] = 34;
$many = build( 'applications' );
$m    = plain( $many['answer'] );
ck( 'twenty-five are listed, the four of the fixtures first (they are the newest) and then the newest of the thirty', array( count( $m['applications'] ), listed( $many ) ), array( 25, array_merge( array( 701, 702, 703, 704 ), range( 801, 821 ) ) ) );
ck( 'more is true, the totals are the feed\'s 34 and `records` are the 25 references', array( $m['more'], $m['totals']['open'], $many['records'] === array_map( $ref_of, listed( $many ) ) ), array( true, 34, true ) );
$few = plain( build( 'applications', array( 'limit' => 10 ) )['answer'] );
ck( 'a limit of 10 lists ten, more is true', array( count( $few['applications'] ), $few['more'] ), array( 10, true ) );
foreach ( array( '26' => 26, '30' => 30, '500' => 500, 'digits above 25' => '30' ) as $label => $limit ) {
	$over = plain( build( 'applications', array( 'limit' => $limit ) )['answer'] );
	ck( "a limit above 25 ($label) is no limit at all: twenty-five are listed, more is true", array( count( $over['applications'] ), $over['more'] ), array( 25, true ) );
}
readers_reset();
many_applications( 21 );
$GLOBALS['readers']['feed_today']['open'] = 25;
ck( 'exactly 25 open applications are all listed and more is false', array( count( plain( build( 'applications' )['answer'] )['applications'] ), plain( build( 'applications' )['answer'] )['more'] ), array( 25, false ) );
many_applications( 22 );
ck( 'and a 26th makes more true', plain( build( 'applications' )['answer'] )['more'], true );
readers_reset();

echo "\n--- The totals are the feed's ---\n";
readers_reset();
build( 'applications' );
$feed_calls = array_count_values( array_column( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return 0 === strpos( $call[0], 'feed_' ); } ), 0 ) );
ck( 'the build asks the feed for its two numbers of today, once, and never for the whole feed, which walks every application\'s history', $feed_calls, array( 'feed_today' => 1 ) );
readers_reset();
build( 'program_counts' );
ck( 'while program-counts still asks for the whole feed, and the whole feed takes its today from today()', array_count_values( array_column( array_filter( $GLOBALS['reader_calls'], static function ( $call ) { return 0 === strpos( $call[0], 'feed_' ); } ), 0 ) ), array( 'feed_build' => 1, 'feed_today' => 1 ) );
$GLOBALS['readers']['feed_today'] = array( 'open' => 11, 'selected' => 7 );
$totals = plain( build( 'applications' )['answer'] )['totals'];
ck( 'open and selected are what the feed says today, wherever the rows are, and the lapsed count is the lapsed rows\'', $totals, array( 'open' => 11, 'selected' => 7, 'lapsed' => 1 ) );
readers_reset();
$GLOBALS['readers']['applications'][707]['state'] = 'declined';
ck( 'with no lapsed application, the lapsed total is 0, as a number', plain( build( 'applications' )['answer'] )['totals']['lapsed'], 0 );
readers_reset();
ck( 'and the totals are the same as program-counts gives for the same feed', array( plain( build( 'applications' )['answer'] )['totals']['open'], plain( build( 'applications' )['answer'] )['totals']['selected'] ), array( plain( build( 'program_counts' )['answer'] )['mentor_applications']['open'], plain( build( 'program_counts' )['answer'] )['mentor_applications']['selected_waiting'] ) );

echo "\n--- The days in the state, as the queue reads the state's day ---\n";
$days = static function ( $id ) {
	return array_column( plain( build( 'applications', array( 'state' => 'open' ) )['answer'] )['applications'], 'days_in_state', 'reference' )[ sprintf( 'MAPP-2026-%04d', $id ) ];
};
readers_reset();
ck( 'a new application put back in the queue counts from the day it was put back (a day and a half ago: 1), not from the day it arrived (two and a half: 2)', $days( 701 ), 1 );
$GLOBALS['readers']['applications'][701]['events'][] = array( 'event' => WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED, 'at' => $GLOBALS['reader_now'] - 43200, 'actor' => 7, 'note' => '' );
ck( 'and put back with the acknowledgement sent counts the same: from the later of the two, half a day ago: 0', $days( 701 ), 0 );
readers_reset();
ck( 'a held one counts from the day it arrived: 9', $days( 702 ), 9 );
ck( 'one asked for more information counts from the day it was asked: 4, not from the 15 days since it arrived', $days( 703 ), 4 );
ck( 'one asked for a second opinion, from the day it was asked: 3, not 20', $days( 704 ), 3 );
$GLOBALS['readers']['applications'][703]['events'] = array_slice( $GLOBALS['readers']['applications'][703]['events'], 0, 1 );
$GLOBALS['readers']['applications'][704]['events'] = array_slice( $GLOBALS['readers']['applications'][704]['events'], 0, 1 );
ck( 'an application with no row in its history for the move, as an older one may have, counts from the day it arrived: 15 and 20', array( $days( 703 ), $days( 704 ) ), array( 15, 20 ) );
$GLOBALS['readers']['applications'][703]['events'][] = array( 'event' => WPCPM_Mentor_Application::EVENT_INFO, 'at' => $GLOBALS['reader_now'] - 5 * 86400 - 43200, 'actor' => 7, 'note' => '' );
$GLOBALS['readers']['applications'][703]['events'][] = array( 'event' => WPCPM_Mentor_Application::EVENT_INFO, 'at' => $GLOBALS['reader_now'] - 2 * 86400 - 43200, 'actor' => 7, 'note' => '' );
ck( 'asked twice, from the day it was last asked: 2', $days( 703 ), 2 );
readers_reset();
$selected = array_column( plain( build( 'applications', array( 'state' => 'selected' ) )['answer'] )['applications'], 'days_in_state', 'reference' );
$lapsed   = array_column( plain( build( 'applications', array( 'state' => 'lapsed' ) )['answer'] )['applications'], 'days_in_state', 'reference' );
ck( 'a selected application counts from the day it was selected (40 and 10), a lapsed one from the day it lapsed (5), as the queue says them', array( $selected, $lapsed ), array( array( 'MAPP-2026-0706' => 10, 'MAPP-2026-0705' => 40 ), array( 'MAPP-2026-0707' => 5 ) ) );
$GLOBALS['readers']['applications'][707]['events'][] = array( 'event' => WPCPM_Mentor_Application::EVENT_WITHDRAWN, 'at' => $GLOBALS['reader_now'] - 2 * 86400 - 43200, 'actor' => 7, 'note' => '' );
ck( 'one an Administrator withdrew counts from the withdrawal: 2', array_column( plain( build( 'applications', array( 'state' => 'lapsed' ) )['answer'] )['applications'], 'days_in_state' ), array( 2 ) );
readers_reset();
$GLOBALS['readers']['applications'][702]['arrived'] = $GLOBALS['reader_now'] + 86400;
ck( 'a day that has not come is 0 days, never a negative number', $days( 702 ), 0 );
$GLOBALS['readers']['applications'][702]['arrived'] = 0;
$unknown = array_column( plain( build( 'applications' )['answer'] )['applications'], null, 'reference' )['MAPP-2026-0702'];
ck( 'and one the site holds no time for is null, with no day received', array( $unknown['days_in_state'], $unknown['received'] ), array( null, null ) );
readers_reset();

echo "\n--- The day received is the day in UTC ---\n";
foreach ( array( '2026-03-02T23:59:59+00:00' => '2026-03-02', '2026-03-03T00:00:00+00:00' => '2026-03-03', '2027-01-01T00:30:00+00:00' => '2027-01-01' ) as $at => $want ) {
	$GLOBALS['readers']['applications'][701]['arrived'] = strtotime( $at );
	$got = array_column( plain( build( 'applications' )['answer'] )['applications'], 'received', 'reference' )['MAPP-2026-0701'];
	ck( "arrived $at: received is $want", $got, $want );
}
readers_reset();

echo "\n--- The flags: a possible duplicate, and held ---\n";
$flags = static function () {
	return array_column( plain( build( 'applications' )['answer'] )['applications'], 'flags', 'reference' );
};
ck( 'a possible duplicate (701, 704) and a held application (702): the rest none', $flags(), array( 'MAPP-2026-0701' => array( 'duplicate' ), 'MAPP-2026-0702' => array( 'held' ), 'MAPP-2026-0703' => array(), 'MAPP-2026-0704' => array( 'duplicate' ) ) );
$GLOBALS['readers']['applications'][702]['signals'][] = WPCPM_Mentor_Application_Form::SIGNAL_DUPLICATE;
ck( 'a held application that is also a possible duplicate has both, the duplicate first', $flags()['MAPP-2026-0702'], array( 'duplicate', 'held' ) );
$GLOBALS['readers']['applications'][703]['signals'] = array( WPCPM_Mentor_Application_Form::SIGNAL_MENTOR, WPCPM_Mentor_Application_Form::SIGNAL_HONEYPOT, WPCPM_Mentor_Application_Form::SIGNAL_DWELL, WPCPM_Mentor_Application_Form::SIGNAL_MAIL_FAILED, WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING );
ck( 'and every other mark the checks raised (a known mentor, a honeypot, a quick fill, the mail that did not go) is no flag', $flags()['MAPP-2026-0703'], array() );
$GLOBALS['readers']['applications'][703]['signals'] = array( 'Duplicate', ' duplicate' );
ck( 'a signal in another case is not the duplicate mark', $flags()['MAPP-2026-0703'], array() );
readers_reset();

echo "\n--- Whether the address is confirmed ---\n";
ck( 'confirmed is the fixtures\' confirmation, a boolean on every row', array_column( plain( build( 'applications' )['answer'] )['applications'], 'confirmed', 'reference' ), array( 'MAPP-2026-0701' => true, 'MAPP-2026-0702' => false, 'MAPP-2026-0703' => true, 'MAPP-2026-0704' => false ) );

echo "\n--- The answers: closed ones, whatever the meta holds ---\n";
$field = static function ( $id, array $fields, $state = 'open' ) {
	$GLOBALS['readers']['applications'][ $id ]['fields'] = array_merge( $GLOBALS['readers']['applications'][ $id ]['fields'], $fields );
	$listed = array_column( plain( build( 'applications', array( 'state' => $state ) )['answer'] )['applications'], null, 'reference' );
	return $listed[ sprintf( 'MAPP-2026-%04d', $id ) ];
};
readers_reset();
ck( 'the teacher answer is true for Yes, false for No', array( $field( 701, array( 'teacher' => 'Yes' ) )['teacher'], $field( 701, array( 'teacher' => 'No' ) )['teacher'] ), array( true, false ) );
ck( 'in any case and with spaces round it', array( $field( 701, array( 'teacher' => ' YES ' ) )['teacher'], $field( 701, array( 'teacher' => 'no' ) )['teacher'] ), array( true, false ) );
foreach ( array( 'unanswered' => '', 'a maybe' => 'maybe', 'a name' => 'Applicant Name Alpha', 'a sentence' => 'Yes, at Applicant Name Alpha University', 'a number' => 1, 'a boolean' => true, 'a list' => array( 'Yes' ), 'null' => null ) as $label => $teacher ) {
	ck( "a teacher answer that is $label is null, and what it said goes nowhere", array( $field( 701, array( 'teacher' => $teacher ) )['teacher'], false !== strpos( json_of( build( 'applications' ) ), 'Applicant' ) ), array( null, false ) );
}
readers_reset();
ck( 'the hours a week are a number: a whole number stays whole, a fraction stays one', array( $field( 701, array( 'hours' => '3' ) )['hours_per_week'], $field( 701, array( 'hours' => '2.5' ) )['hours_per_week'], $field( 701, array( 'hours' => 4 ) )['hours_per_week'] ), array( 3, 2.5, 4 ) );
foreach ( array( 'empty' => '', 'text' => 'n/a', 'a name' => 'Applicant Name Alpha', 'a list' => array( 3 ), 'null' => null ) as $label => $hours ) {
	ck( "hours that are $label are null", $field( 701, array( 'hours' => $hours ) )['hours_per_week'], null );
}
readers_reset();
$planted_country = array( 'Applicant Name Alpha', 'applicant-alpha@example.test', 'https://profiles.wordpress.org/applicant-alpha-example/', 'applicant-alpha-example', 'Alpha Applicant, Poland', 'poland', "Pol\nand", 'Atlantis' );
ck( 'a country is the form\'s own: one of the names on its list comes out as it is', array( $field( 701, array( 'country' => 'Poland' ) )['country'], $field( 701, array( 'country' => 'Kenya' ) )['country'], $field( 701, array( 'country' => ' Brazil ' ) )['country'] ), array( 'Poland', 'Kenya', 'Brazil' ) );
foreach ( $planted_country as $edited ) {
	ck( 'a country hand-edited to ' . json_encode( $edited ) . ' is null, and goes nowhere', array( $field( 701, array( 'country' => $edited ) )['country'], false !== stripos( json_of( build( 'applications' ) ), 'applicant' ) ), array( null, false ) );
}
ck( 'a country that is not text, and none chosen, are null', array( $field( 701, array( 'country' => 12 ) )['country'], $field( 701, array( 'country' => array( 'Poland' ) ) )['country'], $field( 701, array( 'country' => '' ) )['country'] ), array( null, null, null ) );
$GLOBALS['readers']['form_countries'] = array();
ck( 'with the countries never synced, the list is empty and the country is null, never what the row holds', array( WPCPM_Mentor_Application_Form::countries(), $field( 701, array( 'country' => 'Poland' ) )['country'], $field( 702, array( 'country' => 'Applicant Name Beta' ) )['country'] ), array( array(), null, null ) );
readers_reset();
ck( 'languages and areas are the choices ticked, as clean lines, in the order ticked, each once', array( $field( 701, array( 'languages' => array( 'Polish', 'English', 'Polish', 'Polish ' ), 'expertise' => array( 'Training', "Photo\nDirectory", 'Training' ) ) )['languages'], $field( 701, array( 'languages' => array( 'Polish', 'English', 'Polish', 'Polish ' ), 'expertise' => array( 'Training', "Photo\nDirectory", 'Training' ) ) )['areas'] ), array( array( 'Polish', 'English' ), array( 'Training', 'Photo Directory' ) ) );
ck( 'a language is kept only when it is on the form\'s fixed list: a language the form does not offer, a name, an address, a profile address and a language in another case are dropped (a hand-edited answer cannot carry text out)', $field( 701, array( 'languages' => array( 'Polish', 'Klingon', 'Applicant Name Alpha', 'applicant-alpha@example.test', 'https://profiles.wordpress.org/applicant-alpha-example/', 'polish', "En\nglish", 'English' ) ) )['languages'], array( 'Polish', 'English' ) );
ck( 'an area is kept only when it is on the form\'s list of teams: a team the form does not offer, a name, an address, a profile address and a team in another case are dropped, and the ones on the list stay in the order ticked', $field( 701, array( 'expertise' => array( 'Training', 'Applicant Name Alpha', 'applicant-alpha@example.test', 'https://profiles.wordpress.org/applicant-alpha-example/', 'Underwater Basket Weaving', 'design', 'Design', 'Applicant Name Alpha, Core' ) ) )['areas'], array( 'Training', 'Design' ) );
ck( 'and none of it is in the answer', false !== stripos( json_of( build( 'applications' ) ), 'applicant' ), false );
ck( 'the whole list, in the order ticked, comes out whole: thirty-eight languages', $field( 701, array( 'languages' => array_reverse( WPCPM_Mentor_Application_Form::languages() ) ) )['languages'], array_reverse( WPCPM_Mentor_Application_Form::languages() ) );
ck( 'anything in a list that is not text is left out', $field( 701, array( 'languages' => array( 'Polish', 12, null, array( 'x' ), '', "\n" ) ) )['languages'], array( 'Polish' ) );
ck( 'a list that is not a list is an empty list', array( $field( 701, array( 'languages' => 'Polish', 'expertise' => 'Training' ) )['languages'], $field( 701, array( 'languages' => 'Polish', 'expertise' => 'Training' ) )['areas'] ), array( array(), array() ) );
$GLOBALS['readers']['form_teams'] = array();
ck( 'with the teams never synced, the list is empty and the areas are an empty list, never what the row holds', array( WPCPM_Mentor_Application_Form::teams(), $field( 701, array( 'expertise' => array( 'Training', 'Applicant Name Alpha' ) ) )['areas'] ), array( array(), array() ) );
$GLOBALS['readers']['form_teams'] = array_combine( array_map( static function ( $n ) { return sprintf( 'recTEAM%010d', $n ); }, range( 1, 80 ) ), array_map( static function ( $n ) { return "Area $n"; }, range( 1, 80 ) ) );
ck( 'a list of areas is cut at fifty choices', count( $field( 701, array( 'expertise' => array_map( static function ( $n ) { return "Area $n"; }, range( 1, 80 ) ) ) )['areas'] ), 50 );
readers_reset();
$GLOBALS['readers']['applications'][701]['fields'] = array();
ck( 'an application whose answers are gone: every answer unanswered, never an error (no country, no languages, no areas, no hours, no teacher answer)', array_intersect_key( plain( build( 'applications' )['answer'] )['applications'][0], array_flip( array( 'country', 'languages', 'hours_per_week', 'areas', 'teacher' ) ) ), array( 'country' => null, 'languages' => array(), 'hours_per_week' => null, 'areas' => array(), 'teacher' => null ) );
readers_reset();

echo "\n--- What is not read: the name, address, profile, free-text answer, notes, signals and consent evidence ---\n";
$every = array(
	'open'     => build( 'applications' ),
	'selected' => build( 'applications', array( 'state' => 'selected' ) ),
	'lapsed'   => build( 'applications', array( 'state' => 'lapsed' ) ),
	'limit 1'  => build( 'applications', array( 'limit' => 1 ) ),
);
$GLOBALS['readers']['applications_leak'] = true;
$every['everything handed over'] = build( 'applications' );
$GLOBALS['readers']['applications_leak'] = false;
foreach ( $every as $label => $one ) {
	$json = json_of( $one );
	ck( "$label: no free-text answer, note, consent evidence or hash, and no mark of the checks but the duplicate flag", array_values( array_filter( array( 'FREE-TEXT-SENTINEL', 'NOTE-SENTINEL', 'CONSENT-SENTINEL', 'CONSENT-IP-SENTINEL', 'honeypot', 'dwell', 'mail-failed', 'mail-ceiling', '"mentor"' ), static function ( $word ) use ( $json ) { return false !== strpos( $json, $word ); } ) ), array() );
	ck( "$label: no applicant's name, address, profile or any other field of them, in any case, and no address sign", people_found( $one, $APPLICANTS ), array() );
}

echo "\n--- The post meta an application build reads ---\n";
$expected_meta = array( '_wpcpm_mapp_event', '_wpcpm_mapp_fields', '_wpcpm_mapp_reference', '_wpcpm_mapp_signals', '_wpcpm_mapp_state', '_wpcpm_mapp_verified' );
$meta_read     = array_values( array_unique( $GLOBALS['applications_meta_read'] ) );
sort( $meta_read );
ck( 'over every application build above, the post meta read is the state, the answers, the reference, whether the address is confirmed, the checks\' signals and the history, and nothing else: not the consent evidence, not the address\'s hash, not the account, the Airtable row or the activation', $meta_read, $expected_meta );
ck( 'and those builds did read posts', count( $GLOBALS['applications_meta_read'] ) > 100, true );
ck( 'the posts are selected by their state alone', array_values( array_unique( $GLOBALS['applications_queried'] ) ), array( '_wpcpm_mapp_state' ) );
ck( 'the answers are read whole by the one accessor, and what the builder takes of them is five, named in its code below', in_array( '_wpcpm_mapp_fields', $meta_read, true ), true );

echo "\n--- Nothing it can be given or read makes an error ---\n";
readers_reset();
foreach ( array( 'no input' => null, 'text' => 'selected', 'an empty list' => array(), 'a number' => 12, 'a key the schema does not name' => array( 'who' => 'Applicant Name Alpha' ), 'the name of an applicant for a state' => array( 'state' => 'Applicant Name Alpha' ), 'the name of an applicant for a limit' => array( 'limit' => 'Applicant Name Alpha' ) ) as $label => $input ) {
	$one = build( 'applications', $input );
	ck( "$label: the open ones, as ever; nothing of what was asked is in the answer", array( listed( $one ), false !== strpos( json_of( $one ), 'Applicant Name' ) ), array( array( 701, 702, 703, 704 ), false ) );
}
$GLOBALS['readers']['applications'] = array();
$GLOBALS['readers']['feed_today']   = array( 'open' => 0, 'selected' => 0 );
$none = build( 'applications' );
ck( 'with no application at all: an empty list, zero totals, nothing more, no record', array( plain( $none['answer'] ), $none['records'] ), array( array( 'applications' => array(), 'totals' => array( 'open' => 0, 'selected' => 0, 'lapsed' => 0 ), 'more' => false ), array() ) );
ck( 'and the empty list is a list, not an object', substr( json_of( $none ), 0, 18 ), '{"answer":{"applic' );
ck( 'its JSON has the list as []', false !== strpos( json_of( $none ), '"applications":[]' ), true );
readers_reset();

echo "\n--- The input schema: state and limit, both optional ---\n";
$in = WPCPM_Ability_Answers::input_schema( 'applications' );
ck( 'an object that requires nothing and takes the two keys and nothing else, whose default is an empty list', array( isset( $in['type'] ) ? $in['type'] : null, isset( $in['required'] ) ? $in['required'] : array(), isset( $in['properties'] ) ? array_keys( $in['properties'] ) : null, isset( $in['additionalProperties'] ) ? $in['additionalProperties'] : 'missing', array_key_exists( 'default', $in ) ? $in['default'] : 'missing' ), array( 'object', array(), array( 'state', 'limit' ), false, array() ) );
ck( 'the state is a string that is open, selected or lapsed, open by default', array( $in['properties']['state']['type'], $in['properties']['state']['enum'], $in['properties']['state']['default'] ), array( 'string', array( 'open', 'selected', 'lapsed' ), 'open' ) );
ck( 'the limit is a whole number from 1 to 25, 25 by default', array( $in['properties']['limit']['type'], $in['properties']['limit']['minimum'], $in['properties']['limit']['maximum'], $in['properties']['limit']['default'] ), array( 'integer', 1, 25, 25 ) );
ck( 'the 25 is the list limit', $in['properties']['limit']['maximum'], WPCPM_Abilities::LIST_MAX );
ck( 'each says what it takes, and the state names the states each of the three holds', array( '' !== (string) $in['properties']['limit']['description'], false !== strpos( $in['properties']['state']['description'], 'new, held' ), false !== strpos( $in['properties']['state']['description'], 'more information' ), false !== strpos( $in['properties']['state']['description'], 'second opinion' ), false !== strpos( $in['properties']['state']['description'], 'selected' ), false !== strpos( $in['properties']['state']['description'], 'lapsed' ) ), array( true, true, true, true, true, true ) );

echo "\n--- The output schema ---\n";
$out    = WPCPM_Ability_Answers::output_schema( 'applications' );
$item   = $out['properties']['applications']['items'];
$totals = $out['properties']['totals'];
ck( 'a closed object of the three keys, all required', array( $out['type'], array_keys( $out['properties'] ), $out['required'], $out['additionalProperties'] ), array( 'object', array( 'applications', 'totals', 'more' ), array( 'applications', 'totals', 'more' ), false ) );
ck( 'applications is a list of closed objects with the eleven keys, all required', array( $out['properties']['applications']['type'], array_keys( $item['properties'] ), $item['required'], $item['additionalProperties'] ), array( 'array', array( 'reference', 'received', 'state', 'days_in_state', 'country', 'languages', 'hours_per_week', 'areas', 'teacher', 'confirmed', 'flags' ), array( 'reference', 'received', 'state', 'days_in_state', 'country', 'languages', 'hours_per_week', 'areas', 'teacher', 'confirmed', 'flags' ), false ) );
ck( 'the totals are three integers, closed and required', array( $totals['type'], array_keys( $totals['properties'] ), $totals['required'], $totals['additionalProperties'], array_unique( array_column( $totals['properties'], 'type' ) ) ), array( 'object', array( 'open', 'selected', 'lapsed' ), array( 'open', 'selected', 'lapsed' ), false, array( 'integer' ) ) );
ck( 'more is a boolean', $out['properties']['more']['type'], 'boolean' );
ck( 'the types: the reference text, the country text or null, the day received and the days in the state may be null, the languages and areas lists of text, the hours a number or null, the teacher answer a boolean or null, confirmed a boolean, flags a list', array( $item['properties']['reference']['type'], $item['properties']['received']['type'], $item['properties']['days_in_state']['type'], $item['properties']['country']['type'], $item['properties']['languages']['type'], $item['properties']['languages']['items']['type'], $item['properties']['hours_per_week']['type'], $item['properties']['areas']['type'], $item['properties']['areas']['items']['type'], $item['properties']['teacher']['type'], $item['properties']['confirmed']['type'], $item['properties']['flags']['type'] ), array( 'string', array( 'string', 'null' ), array( 'integer', 'null' ), array( 'string', 'null' ), 'array', 'string', array( 'number', 'null' ), 'array', 'string', array( 'boolean', 'null' ), 'boolean', 'array' ) );
ck( 'the state is one of the six, and a flag one of two', array( $item['properties']['state']['enum'], $item['properties']['flags']['items']['enum'] ), array( array( 'new', 'held', 'info', 'second', 'selected', 'lapsed' ), array( 'duplicate', 'held' ) ) );
ck( 'the day received is Y-m-d, which core checks by pattern', array( preg_match( '/' . $item['properties']['received']['pattern'] . '/', '2026-03-02' ), preg_match( '/' . $item['properties']['received']['pattern'] . '/', '2026-03-02T00:00:00+00:00' ) ), array( 1, 0 ) );
ck( 'the days in the state are not below 0, the lists are cut at fifty', array( $item['properties']['days_in_state']['minimum'], $item['properties']['languages']['maxItems'], $item['properties']['areas']['maxItems'] ), array( 0, 50, 50 ) );
ck( 'the lists are cut at the limit', $out['properties']['applications']['maxItems'], WPCPM_Abilities::LIST_MAX );
ck( 'every key says what it is', array_values( array_filter( array_merge( $out['properties'], $item['properties'], $totals['properties'] ), static function ( $schema ) { return '' === (string) ( isset( $schema['description'] ) ? $schema['description'] : '' ); } ) ), array() );
ck( 'the reference says it is the one the queue prints and how to look it up; the teacher answer says it is a yes or no; the flags name what they mean', array( false !== strpos( $item['properties']['reference']['description'], 'Mentors screen' ), false !== strpos( $item['properties']['teacher']['description'], 'Yes' ), false !== strpos( $item['properties']['flags']['description'], 'possible duplicate' ), false !== strpos( $item['properties']['flags']['description'], 'held' ) ), array( true, true, true, true ) );
ck( 'the totals say they are the feed\'s: the open ones and the selected ones waiting for the course, as the Mentors screen counts them', array( false !== strpos( $totals['properties']['open']['description'], 'new, held' ), false !== strpos( $totals['properties']['selected']['description'], 'course' ), false !== strpos( $totals['properties']['lapsed']['description'], 'lapsed' ) ), array( true, true, true ) );
ck( 'and the days in the state say what they count from: the history\'s row for the move into the state, and not that they are as the queue counts them', array( false !== strpos( $item['properties']['days_in_state']['description'], 'counted from the history\'s row for the move into the state' ), false !== strpos( $item['properties']['days_in_state']['description'], 'as the queue' ) ), array( true, false ) );
ck( 'the reference says where to find the application: the Find by reference field on the Waiting for review tab', array( false !== strpos( $item['properties']['reference']['description'], 'Find by reference' ), false !== strpos( $item['properties']['reference']['description'], 'Waiting for review' ), false !== strpos( $item['properties']['reference']['description'], 'Look the' ) ), array( true, true, false ) );

echo "\n--- Where an Administrator finds the person behind an ID ---\n";
$track_schema    = WPCPM_Ability_Answers::output_schema( 'students' )['properties']['people']['items']['properties']['tracks']['items'];
$mentor_out      = WPCPM_Ability_Answers::output_schema( 'mentor' );
$mentors_student = $mentor_out['properties']['students']['items'];
$track_where = $track_schema['properties']['record_id']['description'];
ck( 'a track\'s Students record ID is searched in the Student accounts list for a student with an account, and in the Institution Dashboard roster of its institution for any student', array( false !== strpos( $track_where, 'Student accounts list' ), false !== strpos( $track_where, 'Institution Dashboard' ), false !== strpos( $track_where, 'roster' ), false !== strpos( $track_where, 'with an account or not' ), false !== strpos( $track_where, 'Look the' ) ), array( true, true, true, true, false ) );
ck( 'and says a track with no institution is on no roster, rather than that a student with no account has no row to be found', array( false !== strpos( $track_where, 'no institution' ), false !== strpos( $track_where, 'has no row there' ) ), array( true, false ) );
$people_where = WPCPM_Ability_Answers::output_schema( 'students' )['properties']['people']['description'];
ck( 'the people list says where the name is put to an ID: the same two places', array( false !== strpos( $people_where, 'Student accounts list' ), false !== strpos( $people_where, 'Institution Dashboard roster' ), false !== strpos( $people_where, 'look the record IDs up' ) ), array( true, true, false ) );
ck( 'a mentor\'s Mentors record ID in the Mentor accounts list or on the Engagement tab', array( false !== strpos( $mentor_out['properties']['mentor']['properties']['record_id']['description'], 'Mentor accounts list' ), false !== strpos( $mentor_out['properties']['mentor']['properties']['record_id']['description'], 'Engagement tab' ), false !== strpos( $mentor_out['properties']['mentor']['properties']['record_id']['description'], 'Look the' ) ), array( true, true, false ) );
ck( 'and a mentor\'s student\'s Students record ID in the Student accounts list and in the Institution Dashboard roster, as a track\'s', array( false !== strpos( $mentors_student['properties']['record_id']['description'], 'Student accounts list' ), false !== strpos( $mentors_student['properties']['record_id']['description'], 'Institution Dashboard' ), false !== strpos( $mentors_student['properties']['record_id']['description'], 'Look the' ) ), array( true, true, false ) );

echo "\n--- The people walk: no name, address or username, whatever was asked (Review Focus 5) ---\n";
readers_reset();
$forms = array(
	'no input'                         => null,
	'an empty list'                    => array(),
	'the open ones'                    => array( 'state' => 'open' ),
	'the selected ones'                => array( 'state' => 'selected' ),
	'the lapsed ones'                  => array( 'state' => 'lapsed' ),
	'a state that is never listed'     => array( 'state' => 'spam' ),
	'a state that is an applicant\'s name' => array( 'state' => 'Applicant Name Alpha' ),
	'a limit of 1'                     => array( 'limit' => 1 ),
	'a limit of 3'                     => array( 'limit' => 3 ),
	'a limit of 25'                    => array( 'limit' => 25 ),
	'a limit that is an address'       => array( 'limit' => 'applicant-alpha@example.test' ),
	'selected, a limit of 1'           => array( 'state' => 'selected', 'limit' => 1 ),
	'lapsed, a limit of 1'             => array( 'state' => 'lapsed', 'limit' => 1 ),
	'a name for a key'                 => array( 'Applicant Name Alpha' => 'applicant-alpha@example.test' ),
);
foreach ( $forms as $label => $input ) {
	people_walk( $label, build( 'applications', $input ), $APPLICANTS );
	$GLOBALS['readers']['applications_leak'] = true;
	people_walk( "$label, with every post handed to the build", build( 'applications', $input ), $APPLICANTS );
	$GLOBALS['readers']['applications_leak'] = false;
}
many_applications( 30 );
people_walk( 'thirty more open applications', build( 'applications' ), $APPLICANTS );
people_walk( 'thirty more open applications, a limit of 7', build( 'applications', array( 'limit' => 7 ) ), $APPLICANTS );
readers_reset();
$GLOBALS['readers']['applications'][701]['fields']['languages'] = array_merge( array( 'Polish' ), array_values( $APPLICANTS['alpha'] ) );
$GLOBALS['readers']['applications'][702]['fields']['languages'] = array_values( $APPLICANTS['beta'] );
people_walk( 'a hand-edited list of languages holding every field of an applicant', build( 'applications' ), $APPLICANTS );
ck( 'and the languages of those two are the one that is on the list, and none', array_column( plain( build( 'applications' )['answer'] )['applications'], 'languages', 'reference' ), array( 'MAPP-2026-0701' => array( 'Polish' ), 'MAPP-2026-0702' => array(), 'MAPP-2026-0703' => array( 'Portuguese' ), 'MAPP-2026-0704' => array( 'Japanese' ) ) );
readers_reset();
ck( 'and the walk had people to find: ten applicants, each with every field', array( count( $APPLICANTS ), array_values( array_unique( array_map( 'count', $APPLICANTS ) ) ) ), array( 10, array( 20 ) ) );
ck( 'and each applicant\'s free-text answer holds all twenty fields of them, so a leak of any shows', count( array_filter( $GLOBALS['readers']['applications'], static function ( $row ) use ( $APPLICANTS ) { foreach ( $APPLICANTS as $person ) { if ( $row['fields']['name'] === $person['name'] ) { return array() === array_filter( $person, static function ( $value ) use ( $row ) { return false === strpos( $row['fields']['anything'], $value ); } ); } } return false; } ) ), 10 );

// The walk must be able to fail, or the checks above prove nothing.
$planted = plain( build( 'applications' )['answer'] );
$planted['applications'][0]['country'] = 'APPLICANT NAME alpha';
ck( 'the walk finds an applicant\'s name planted in the country, in another case', people_found( $planted, $APPLICANTS ), array( 'Applicant Name Alpha' ) );
$planted['applications'][0]['country'] = 'Poland';
$planted['applications'][1]['areas'][] = 'applicant-beta@example.test';
ck( 'and an address planted in an area, as the person spells it and as its key, with the address sign', people_found( $planted, $APPLICANTS ), array( 'Applicant-Beta@example.test', 'applicant-beta@example.test', '@' ) );
$planted['applications'][1]['areas'] = array();
$planted['applications'][2]['languages'][] = 'https://profiles.wordpress.org/applicant-gamma-example/';
ck( 'and a profile address in a language, and the username inside it', people_found( $planted, $APPLICANTS ), array( 'applicant-gamma-example', 'https://profiles.wordpress.org/applicant-gamma-example/' ) );
$planted['applications'][2]['languages'] = array( 'Portuguese' );
ck( 'and the answer as it was holds none', people_found( $planted, $APPLICANTS ), array() );
$half = $APPLICANTS;
unset( $half['kappa']['accessibility'] );
ck( 'and a walk given half a person says so', people_found( build( 'applications' ), $half ), array( 'the person kappa was given without a accessibility' ) );

echo "\n--- The code: what the builder names and calls ---\n";
$source_now = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ability-answers.php' );
$apps_body  = preg_match( '/\n\tpublic static function applications\(.*?\n\t\}\n/s', $source_now, $found_apps ) ? $found_apps[0] : '';
// With the one helper that counts from the day a row entered its state, which asks the history for times alone.
$apps_body .= preg_match( '/\n\tprivate static function application_since\(.*?\n\t\}\n/s', $source_now, $found_since ) ? $found_since[0] : '';
$app_strings = array();
foreach ( token_get_all( '<?php ' . $apps_body ) as $token ) {
	if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) { $app_strings[] = trim( $token[1], "'\"" ); }
}
preg_match_all( '/WPCPM_Mentor_Application::([a-z_]+)\(/', $apps_body, $called );
preg_match_all( '/WPCPM_Mentor_Application::([A-Z_]+)\b(?!\()/', $apps_body, $constants_used );
ck( 'the applications builder is there', '' !== $apps_body, true );
ck( 'of the answers an application keeps it names the country, the teacher question, the languages, the contribution areas and the hours, and no other key (not the name, the address, the profile, the free-text answer or the boxes)', array_values( array_intersect( $app_strings, array_merge( PEOPLE_FIELDS, array( 'anything', 'consent', 'gpl', 'wpcpm_student_program' ) ) ) ), array() );
ck( 'its strings are the keys of the answer, the five answers it takes and the words it gives back', array_values( array_diff( $app_strings, array( 'state', 'limit', 'open', 'selected', 'lapsed', 'country', 'teacher', 'languages', 'expertise', 'hours', 'duplicate', 'held', 'yes', 'no', 'applications', 'totals', 'more', 'answer', 'records', 'reference', 'received', 'days_in_state', 'hours_per_week', 'areas', 'confirmed', 'flags', 'U', 'strlen', 'today', '', '/^\\d{1,3}$/' ) ) ), array() );
$calls = array_values( array_unique( $called[1] ) );
sort( $calls );
ck( 'it calls only these accessors of the application class: the list, the count, the state, the answers, the reference, the confirmation, the signals and the history\'s times', $calls, array( 'applications', 'count_in', 'decided_at', 'fields_of', 'is_confirmed', 'last_at', 'open_states', 'reference_of', 'signals_of', 'state_of' ) );
ck( 'it never reads the name, address, handle, profile, account, Airtable row, problem, check or history rows themselves, or the consent evidence', array_values( array_intersect( array_unique( $called[1] ), array( 'name_of', 'email_of', 'handle_of', 'profile_url', 'user_of', 'airtable_of', 'problem_of', 'linked_by_of', 'last_check', 'events', 'application', 'by_reference' ) ) ), array() );
ck( 'and names none of the meta keys itself, the consent evidence and the address\'s hash among them', array( false !== strpos( $apps_body, 'META_' ), false !== strpos( $apps_body, 'get_post_meta' ), false !== strpos( $apps_body, 'get_posts' ), false !== strpos( $apps_body, 'consent' ) ), array( false, false, false, false ) );
ck( 'the totals come from the feed\'s today() and not from its build(), and from the count of the lapsed', array( false !== strpos( $apps_body, 'WPCPM_Mentor_Application_Feed::today()' ), false !== strpos( $apps_body, 'WPCPM_Mentor_Application_Feed::build' ), false !== strpos( $apps_body, 'count_in( array( WPCPM_Mentor_Application::STATE_LAPSED ) )' ) ), array( true, false, true ) );
ck( 'the languages are kept only when they are on the form\'s fixed list', false !== strpos( $apps_body, 'array_intersect( self::picked(' ) && false !== strpos( $apps_body, 'WPCPM_Mentor_Application_Form::languages()' ), true );
ck( 'the country and the areas are kept only when they are on the form\'s own lists, the ones its validators take', array( false !== strpos( $apps_body, 'WPCPM_Mentor_Application_Form::countries()' ), false !== strpos( $apps_body, 'WPCPM_Mentor_Application_Form::teams()' ), false !== strpos( $apps_body, 'in_array( $country, $countries, true )' ), false !== strpos( $apps_body, 'array_intersect( self::picked( isset( $fields[\'expertise\'] )' ) ), array( true, true, true, true ) );

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
	'mentor-students, by record ID'      => build( 'mentor', array( 'mentor' => 'recMENT0000000001' ) ),
	'mentor-students, a mentor with no record ID' => build( 'mentor', array( 'mentor' => 'mentor-nu-example' ) ),
	'mentor-students, nobody'            => build( 'mentor', array( 'mentor' => 'nobody-example' ) ),
	'mentor-applications, the open ones' => build( 'applications' ),
	'mentor-applications, selected'      => build( 'applications', array( 'state' => 'selected' ) ),
	'mentor-applications, lapsed'        => build( 'applications', array( 'state' => 'lapsed' ) ),
);
foreach ( $builds as $label => $built ) {
	$json  = json_of( $built );
	$found = array();
	foreach ( readers_people() as $person ) {
		if ( false !== strpos( $json, $person ) ) { $found[] = $person; }
	}
	ck( "$label: none of the fixtures' names, addresses or logins is in the JSON, and no address at all", array( $found, false !== strpos( $json, '@' ) ), array( array(), false ) );
	people_walk( "$label, and the roster's people", $built, readers_pupils() );
	people_walk( "$label, and the mentors' people", $built, $MENTOR_PEOPLE );
	people_walk( "$label, and the applicants' people", $built, $APPLICANTS );
}
ck( 'the search for "ample" found three names, by name', array_column( $builds['institution-summary, three matches']['answer']['matches'], 'name' ), array( 'Example Institute of Technology', 'Northern UNIVERSITY of Samples', 'University of Example' ) );

echo "\n=== A builder never returns an error ===\n";
foreach ( array( 'program_counts', 'waiting', 'engagement', 'institutions', 'students', 'mentor', 'applications' ) as $method ) {
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
$strings_of = static function ( $code ) {
	$strings = array();
	foreach ( token_get_all( $code ) as $token ) {
		if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) { $strings[] = trim( $token[1], "'\"" ); }
	}
	return $strings;
};
// The one method that compares a roster row with what was asked, and the source without it.
$matcher_pattern = '/\n\tprivate static function people_matching\(.*?\n\t\}\n/s';
$matcher         = preg_match( $matcher_pattern, $source, $found_matcher ) ? $found_matcher[0] : '';
$elsewhere       = (string) preg_replace( $matcher_pattern, "\n", $source, 1 );
$never           = array( 'contact_person', 'contact_email', 'accepted_by', 'progress', 'note', 'screen', 'user_email', 'user_login', 'display_name', 'email', 'username', 'mentor_name', 'email_key', 'tutor', 'website', 'profile', 'login', 'slack', 'field_of_study', 'import_key', 'nicename', 'github', 'avatar', 'jobline', 'location', 'website_label', 'accessibility', 'wpcpm_student_program' );
ck( 'the one method that compares a roster row with what was asked is there', '' !== $matcher, true );
ck( 'the code never so much as names a field that holds a person (the comments may), but in that method', array_values( array_intersect( $strings_of( $elsewhere ), $never ) ), array() );
ck( 'and that method names two of them, the address key and the username, to compare them with what was asked', array_values( array_unique( array_intersect( $strings_of( '<?php ' . $matcher ), $never ) ) ), array( 'email_key', 'username' ) );
ck( 'no answer reads the program row an account keeps, where the accessibility answer is', array( false !== strpos( $source, 'META_PROGRAM' ), false !== strpos( $source, 'wpcpm_student_program' ) ), array( false, false ) );
ck( 'a waiting course is read through the screens\' own reader, WPCPM_Program::course_status(), and never off the account directly', array( false !== strpos( $source, 'WPCPM_Program::course_status(' ), false !== strpos( $source, 'META_COURSE' ) ), array( true, false ) );
ck( 'every short text an answer carries goes through text()', substr_count( $source, 'WPCPM_Abilities::text(' ) >= 8, true );
// The mentor builders: what they read of a mentor's list and of the Engagement report.
$mentor_body = preg_match( '/\n\tpublic static function mentor\(.*?\n\t\}\n/s', $source, $found_mentor ) ? $found_mentor[0] : '';
ck( 'the mentor builder is there, and its code names none of the fields that hold a person: of a student on a list it names the record ID, status, hours, start and whether past, of the report the flag and the two times', array( '' !== $mentor_body, array_values( array_intersect( $strings_of( '<?php ' . $mentor_body ), $never ) ) ), array( true, array() ) );
ck( 'no mentor builder reads the program row an account keeps, or the profile, or a student\'s account', array( false !== strpos( $mentor_body, 'META_PROGRAM' ), false !== strpos( $mentor_body, 'META_PROFILE' ), false !== strpos( $mentor_body, 'user_for_record' ), false !== strpos( $mentor_body, 'WPCPM_Students_Sync' ) ), array( false, false, false, false ) );
ck( 'the Mentors record ID is looked up by its meta, through id_of(), the way every other lookup of an account by a record does', array( false !== strpos( $source, "'meta_key'   => WPCPM_Mentors_Sync::META_RECORD_ID" ), false !== strpos( $source, 'WPCPM_Roles::id_of(' ) ), array( true, true ) );

printf( "\n%s\n", $fail ? "$fail FAILED" : 'ALL PASS' );
exit( $fail ? 1 : 0 );
