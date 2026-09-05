<?php
/**
 * Do the fixtures still describe the base, and do the settings defaults agree with them?
 *
 * Three fixtures pin the Institutions, Students and Feedback tables as the metadata API
 * reported them. They exist because a field name is just a string until Airtable sees it:
 * `create_records()` and `update_records()` send no `typecast`, so a choice or a column
 * spelled any other way is a 422 for the whole record, and nothing in the plugin can notice
 * before a program manager does. The eleven columns the Institutions module added on
 * 2026-09-02 are asserted byte for byte here, and so are the two strings that look like
 * typos and are not ('Tutor ' with its space, 'Site import key' beside it).
 *
 * The second half asks the settings to agree with the base. `student_statuses` and
 * `institution_active_stages` become Airtable formulas, and a status the base does not offer
 * is not an error there, it is a student nobody fetches. So every default is checked against
 * the choice list the fixture recorded, and every table ID against the fixture's.
 *
 * Run from the plugin root:  php bin/test-fixtures.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts'] = array();

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * A fixture, decoded, or an empty array when the file is missing or not JSON.
 *
 * An empty array rather than an exit, so a broken fixture fails every assertion that reads
 * it and the report says which file, instead of stopping at the first one.
 *
 * @param string $name Basename under bin/fixtures/.
 * @return array
 */
function fixture( $name ) {
	$path = __DIR__ . '/fixtures/' . $name;

	if ( ! is_file( $path ) ) {
		return array();
	}

	$data = json_decode( file_get_contents( $path ), true );

	return is_array( $data ) ? $data : array();
}

/**
 * Whether a list of names is unique and in byte order.
 *
 * The three fixtures sort their field lists with a plain byte comparison, so a refresh that
 * pastes the base's own order, or one that trims a trailing space and so creates a duplicate,
 * shows up as an ordering failure here rather than as a diff nobody reads.
 *
 * @param array $names The list as the fixture holds it.
 * @return bool
 */
function in_byte_order( $names ) {
	$sorted = array_values( array_unique( $names ) );
	sort( $sorted, SORT_STRING );

	return $sorted === array_values( $names );
}

/**
 * The entries of a list that a choice list does not offer.
 *
 * @param array $wanted  What the plugin names.
 * @param array $offered What the base offers.
 * @return array
 */
function not_offered( $wanted, $offered ) {
	return array_values( array_diff( (array) $wanted, (array) $offered ) );
}

$institutions = fixture( 'institutions-table-fields.json' );
$students     = fixture( 'students-table-fields.json' );
$feedback     = fixture( 'feedback-table-fields.json' );

$inst_fields  = isset( $institutions['fields'] ) ? $institutions['fields'] : array();
$inst_choices = isset( $institutions['choices'] ) ? $institutions['choices'] : array();
$stud_fields  = isset( $students['fields'] ) ? $students['fields'] : array();
$stud_choices = isset( $students['choices'] ) ? $students['choices'] : array();
$feed_fields  = isset( $feedback['fields'] ) ? $feedback['fields'] : array();
$feed_choices = isset( $feedback['choices'] ) ? $feedback['choices'] : array();

/* ---- the institutions table --------------------------------------------- */

echo "=== Institutions ===\n";

ck( 'the fixture loaded', isset( $institutions['institutions_table'] ), true );
ck( 'and was read on a date', isset( $institutions['read'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $institutions['read'] ), true );
ck( 'the table has 45 fields', count( $inst_fields ), 45 );
ck( 'listed once each, in byte order', in_byte_order( $inst_fields ), true );

// The eight columns section 10 of the design spec added. Their names are the plugin's to
// write, so a rename in the grid must fail here before it fails a PATCH.
$agreement = array(
	'Agreement Status',
	'Agreement Kind',
	'Agreement Accepted On',
	'Agreement Signed On',
	'Agreement Accepted By',
	'Agreement Document',
	'Agreement Submitted On',
	'Agreement Template Version',
);

ck( 'the eight Agreement columns exist', not_offered( $agreement, $inst_fields ), array() );

ck( 'Agreement Status offers the seven document states, in order',
    isset( $inst_choices['Agreement Status'] ) ? $inst_choices['Agreement Status'] : null,
    array( 'Not started', 'Template generated', 'Awaiting review', 'Accepted', 'Returned', 'On file', 'Revoked' ) );
ck( 'Agreement Kind offers the three kinds, in order',
    isset( $inst_choices['Agreement Kind'] ) ? $inst_choices['Agreement Kind'] : null,
    array( 'Program template', 'Institution-specific', 'Legacy' ) );

// The two strings the base spells in a way that looks wrong. Pinned so a refresh that
// "fixes" them is a failure, not a cleanup.
ck( 'the stage is spelled "Not Moving Forward", capital M', in_array( 'Not Moving Forward', isset( $inst_choices['Current Stage'] ) ? $inst_choices['Current Stage'] : array(), true ), true );
ck( 'the U+2019 in "Anything else you’d like us to know?" survives', in_array( "Anything else you\xE2\x80\x99d like us to know?", $inst_fields, true ), true );
ck( 'and the leading space on the first internship choice',
    isset( $inst_choices['How do your internships or practices typically work?'][0] ) ? $inst_choices['How do your internships or practices typically work?'][0] : null,
    ' Based on required hours (e.g. 150 hours)' );

ck( 'the Countries table has 8 fields', isset( $institutions['countries_fields'] ) ? count( $institutions['countries_fields'] ) : 0, 8 );
ck( 'including the two lookups the acknowledgement needs',
    not_offered( array( 'Email (from Person of contact (Team))', 'Calendly link (from Person of contact (Team))' ), isset( $institutions['countries_fields'] ) ? $institutions['countries_fields'] : array() ),
    array() );

/* ---- the students table ------------------------------------------------- */

echo "\n=== Students ===\n";

ck( 'the fixture loaded', isset( $students['students_table'] ), true );
ck( 'and was read on a date', isset( $students['read'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $students['read'] ), true );
ck( 'the table has 29 fields', count( $stud_fields ), 29 );
ck( 'listed once each, in byte order', in_byte_order( $stud_fields ), true );

// Trailing spaces are real. Trimming the fixture would hide exactly the class of bug it
// exists to catch, so the trimmed spelling is asserted absent as well as the real one present.
ck( 'keeps the trailing space on "Tutor "', in_array( 'Tutor ', $stud_fields, true ), true );
ck( 'and does not also offer "Tutor" without it', in_array( 'Tutor', $stud_fields, true ), false );
ck( 'has the "Site import key" column the import writes', in_array( 'Site import key', $stud_fields, true ), true );

// The thirteen columns section 8.1 of the design spec has the sync request by name.
$sync_reads = array(
	'Full Name',
	'Email',
	'Status',
	'Educational Institutions',
	'Start Date',
	'End Date',
	'Mentor',
	'WP Profile',
	'Tutor ',
	'Tutors official',
	'Your field of study',
	'Accessibility needs',
	'Site import key',
);

ck( 'every column the Students-table pass reads exists', not_offered( $sync_reads, $stud_fields ), array() );

// And the sync's own map, not a copy of it: the fixture exists to catch the two drifting apart.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
$sync_student = array();
foreach ( WPCPM_Mentors_Sync::fields() as $key => $name ) {
	if ( 0 === strpos( $key, 'student_' ) ) {
		$sync_student[] = $name;
	}
}
ck( 'WPCPM_Mentors_Sync::fields() names Students columns', count( $sync_student ) >= 5, true );
ck( 'and every one of them exists in the Students table', not_offered( $sync_student, $stud_fields ), array() );

$status_choices = isset( $stud_choices['Status'] ) ? $stud_choices['Status'] : array();
$study_choices  = isset( $stud_choices['Your field of study'] ) ? $stud_choices['Your field of study'] : array();

ck( 'Status offers 14 choices', count( $status_choices ), 14 );
ck( 'the status is spelled "Not moving forward", lower-case m', in_array( 'Not moving forward', $status_choices, true ), true );
ck( 'including the two decision 21 tracks and the Developer Track',
    not_offered( array( 'Paused', 'Pending graduation', 'Developer Track' ), $status_choices ), array() );
ck( 'Your field of study offers 9 choices', count( $study_choices ), 9 );

// The row facts the design spec measured. They are quoted, not re-read, so a refresh of
// the field list must not touch them and a re-read of the rows must update all four.
$coverage = isset( $students['coverage'] ) ? $students['coverage'] : array();

ck( 'coverage: 800 rows', isset( $coverage['record_count'] ) ? $coverage['record_count'] : null, 800 );
ck( 'coverage: 797 linked to exactly one institution', isset( $coverage['linked_to_one_institution'] ) ? $coverage['linked_to_one_institution'] : null, 797 );
ck( 'coverage: none linked to several', isset( $coverage['linked_to_several_institutions'] ) ? $coverage['linked_to_several_institutions'] : null, 0 );
ck( 'coverage: Start Date on 793', isset( $coverage['with_start_date'] ) ? $coverage['with_start_date'] : null, 793 );
ck( 'coverage: the Students Reports link is empty on every row', isset( $coverage['linked_to_students_reports'] ) ? $coverage['linked_to_students_reports'] : null, 0 );

/* ---- the feedback table ------------------------------------------------- */

echo "\n=== Feedback ===\n";

ck( 'the fixture loaded', isset( $feedback['feedback_table'] ), true );
ck( 'and was read on a date', isset( $feedback['read'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $feedback['read'] ), true );
ck( 'the table has 64 fields', count( $feed_fields ), 64 );
ck( 'listed once each, in byte order', in_byte_order( $feed_fields ), true );

$list_field  = 'F3 - Report: my institution may list me in its semester report';
$quote_field = 'F3 - Report: my institution may quote my feedback in its semester report';

ck( 'the two report-permission columns exist', not_offered( array( $list_field, $quote_field ), $feed_fields ), array() );
ck( 'listing offers name, blog address or no, in order',
    isset( $feed_choices[ $list_field ] ) ? $feed_choices[ $list_field ] : null,
    array( 'Yes, with my name', 'Yes, by my blog address only', 'No' ) );
ck( 'quoting offers named, unnamed or no, in order',
    isset( $feed_choices[ $quote_field ] ) ? $feed_choices[ $quote_field ] : null,
    array( 'Yes, with my name', 'Yes, without my name', 'No' ) );

// The column the semester report quotes from, and the two the base spells oddly.
ck( 'the proud-of column the report quotes exists', in_array( 'F3 - One example of a contribution you are proud of', $feed_fields, true ), true );
ck( 'keeps the trailing space on the F50H follow-up', in_array( 'F50H- If yes or maybe: Which would interest you most? ', $feed_fields, true ), true );
ck( 'and the double space in the mentor-support question', in_array( 'Mentor support - What was most helpful  and what could be improved', $feed_fields, true ), true );

ck( 'Course names the three programs', isset( $feed_choices['Course'] ) ? $feed_choices['Course'] : null, array( 'In Sensei', 'In Sensei 50h', 'Developer Track' ) );

/* ---- the settings defaults agree with the base -------------------------- */

echo "\n=== The settings defaults name what the base offers ===\n";

$defaults = WPCPM_Settings::defaults();
$stages   = isset( $inst_choices['Current Stage'] ) ? $inst_choices['Current Stage'] : array();

// A status the base does not offer is not an error in a formula, it is a student nobody
// fetches. So the lists are checked entry by entry, and the report names the stray.
ck( 'every student_statuses entry is a Students Status choice', not_offered( $defaults['student_statuses'], $status_choices ), array() );
ck( 'every past_statuses entry is a Students Status choice', not_offered( $defaults['past_statuses'], $status_choices ), array() );
ck( 'the two lists do not overlap', array_values( array_intersect( $defaults['student_statuses'], $defaults['past_statuses'] ) ), array() );
ck( 'institution_new_stage is a Current Stage choice', in_array( $defaults['institution_new_stage'], $stages, true ), true );
ck( 'every institution_active_stages entry is a Current Stage choice', not_offered( $defaults['institution_active_stages'], $stages ), array() );
ck( 'and the new stage is one of the active ones', in_array( $defaults['institution_new_stage'], $defaults['institution_active_stages'], true ), true );

// The table IDs. The fixtures were read from these tables, so a default pointing anywhere
// else would be checked against the wrong schema.
ck( 'countries_table is the fixture\'s', $defaults['countries_table'], isset( $institutions['countries_table'] ) ? $institutions['countries_table'] : null );
ck( 'institutions_table is the fixture\'s', $defaults['institutions_table'], isset( $institutions['institutions_table'] ) ? $institutions['institutions_table'] : null );
ck( 'students_table is the fixture\'s', $defaults['students_table'], isset( $students['students_table'] ) ? $students['students_table'] : null );
ck( 'feedback_table is the fixture\'s', $defaults['feedback_table'], isset( $feedback['feedback_table'] ) ? $feedback['feedback_table'] : null );

/* ---- the sponsors table -------------------------------------------------- */

echo "\n=== Sponsors table ===\n";

$sponsors     = fixture( 'sponsors-table-fields.json' );
$spon_fields  = isset( $sponsors['fields'] ) ? (array) $sponsors['fields'] : array();
$spon_choices = isset( $sponsors['choices'] ) ? (array) $sponsors['choices'] : array();

ck( 'the fixture loaded', isset( $sponsors['sponsors_table'] ) && 'tbluji8wknOZr55fa' === $sponsors['sponsors_table'], true );
ck( 'and names the Team Members table', isset( $sponsors['team_members_table'] ) ? $sponsors['team_members_table'] : '', 'tblUYWUSEcRLJ5BaR' );
ck( 'the table has 31 fields: the 26 the base held and the five the site creates', count( $spon_fields ), 31 );
ck( 'listed once each, in byte order', in_byte_order( $spon_fields ), true );
ck( 'the five site-created columns are listed as such, in byte order',
	isset( $sponsors['created_by_site'] ) ? $sponsors['created_by_site'] : array(),
	array( 'Agreement Accepted On', 'Agreement Document', 'Agreement Status', 'Dashboard account', 'Sponsorship interests' ) );
ck( 'and every one of them is a field', not_offered( isset( $sponsors['created_by_site'] ) ? $sponsors['created_by_site'] : array(), $spon_fields ), array() );
ck( 'every typed field is a listed field', not_offered( array_keys( isset( $sponsors['types'] ) ? (array) $sponsors['types'] : array() ), $spon_fields ), array() );
ck( 'Status offers the five statuses the base holds, in Airtable\'s order',
	isset( $spon_choices['Status'] ) ? $spon_choices['Status'] : array(),
	array( 'Approved', 'Rejected', 'In review', 'Paused', 'Not Moving Forward' ) );
ck( 'Type of product offers three', isset( $spon_choices['Type of product'] ) ? $spon_choices['Type of product'] : array(), array( 'Hosting', 'Plugin', 'Service' ) );
ck( 'the six ways to support, in order', isset( $spon_choices['How would you like to support WP Credits?'] ) ? count( $spon_choices['How would you like to support WP Credits?'] ) : 0, 6 );
ck( 'Agreement Status offers the six states the site writes, in order',
	isset( $spon_choices['Agreement Status'] ) ? $spon_choices['Agreement Status'] : array(),
	array( 'Not started', 'Awaiting review', 'Accepted', 'Returned', 'On file', 'Revoked' ) );
ck( 'the free-text field ends with a full stop and an ASCII apostrophe', in_array( "Anything else you'd like to share.", $spon_fields, true ), true );
ck( 'the Team Members table has 8 fields, in byte order', isset( $sponsors['team_members_fields'] ) && 8 === count( $sponsors['team_members_fields'] ) && in_byte_order( $sponsors['team_members_fields'] ), true );
ck( 'including the three the sync reads', not_offered( array( 'Name', 'Email', 'Calendly link' ), isset( $sponsors['team_members_fields'] ) ? $sponsors['team_members_fields'] : array() ), array() );

/* ---- the mentors table ---------------------------------------------------- */

echo "\n=== Mentors table ===\n";

$mentors      = fixture( 'mentors-table-fields.json' );
$ment_fields  = isset( $mentors['fields'] ) ? (array) $mentors['fields'] : array();
$ment_choices = isset( $mentors['choices'] ) ? (array) $mentors['choices'] : array();

ck( 'the fixture loaded', isset( $mentors['mentors_table'] ) && 'tblJmEYgBWYxVuzUw' === $mentors['mentors_table'], true );
ck( 'the table has 32 fields', count( $ment_fields ), 32 );
ck( 'listed once each, in byte order', in_byte_order( $ment_fields ), true );
ck( 'the three sponsorship columns and the expertise column exist',
	not_offered( array( 'Sponsored', 'Wants to be in the looking for sponsors list', 'Sponsor Company Name', 'Contribution Area - Expertise' ), $ment_fields ), array() );
ck( 'Sponsored and the wants-a-sponsor flag are Yes or No',
	array( isset( $ment_choices['Sponsored'] ) ? $ment_choices['Sponsored'] : array(), isset( $ment_choices['Wants to be in the looking for sponsors list'] ) ? $ment_choices['Wants to be in the looking for sponsors list'] : array() ),
	array( array( 'Yes', 'No' ), array( 'Yes', 'No' ) ) );
ck( 'Active is a Status the base offers', in_array( 'Active', isset( $ment_choices['Status'] ) ? $ment_choices['Status'] : array(), true ), true );
ck( 'the U+2019 in the free-text question survives', in_array( "Anything else you\xE2\x80\x99d like us to know?", $ment_fields, true ), true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
