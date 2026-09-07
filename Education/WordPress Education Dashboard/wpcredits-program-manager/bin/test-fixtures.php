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

ck( 'Status offers 15 choices', count( $status_choices ), 15 );
ck( 'the status is spelled "Not moving forward", lower-case m', in_array( 'Not moving forward', $status_choices, true ), true );
// The Designer Track joins the run on 1.98.2. The Students table carries the choice as well as the
// Students Reports table does, and `student_statuses` is checked against both lists together
// further down - naming it here is what makes the Students half of that pair say so on its own.
ck( 'including the two decision 21 tracks, the Developer Track and the Designer Track',
    not_offered( array( 'Paused', 'Pending graduation', 'Developer Track', 'Designer Track' ), $status_choices ), array() );
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

/* ---- the Students Reports table ------------------------------------------ */

echo "\n=== Students Reports ===\n";

// The table the report form writes to. Its columns are the form's keys, so a name spelled any
// other way is a 422 for the whole PATCH and a student's answer that never lands.
$reports      = fixture( 'reports-table-fields.json' );
$rep_fields   = isset( $reports['fields'] ) ? (array) $reports['fields'] : array();
$rep_types    = isset( $reports['types'] ) ? (array) $reports['types'] : array();
$rep_choices  = isset( $reports['choices'] ) ? (array) $reports['choices'] : array();

ck( 'the fixture loaded', isset( $reports['table'] ) && 'tbljYkkVGbeoaWEtY' === $reports['table'], true );
ck( 'and was read on a date', isset( $reports['read'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reports['read'] ), true );
ck( 'the table has 73 fields: the 52 it held and the 21 the Designer Track added', count( $rep_fields ), 73 );
ck( 'listed once each, in byte order', in_byte_order( $rep_fields ), true );
ck( 'keeps the trailing space on "Company "', in_array( 'Company ', $rep_fields, true ), true );

// The twenty-one columns of the design spec of 2026-09-07, section 1. The form has to name them
// byte for byte, so they are pinned here before Task 2 writes a single one of them.
$designer = array(
	'Practical: Duplicate & Explore WP Design Library - Reflection',
	'Practical: Duplicate & Explore WP Design Library - link',
	'Practical: Duplicate & Explore WP Design Library - image',
	'Practical: Local WordPress Environment for Design Testing - Tool used',
	'Practical: Local WordPress Environment for Design Testing - Screenshot',
	"Practical: Change Your Site\xE2\x80\x99s Global Styles - Notes",
	"Practical: Change Your Site\xE2\x80\x99s Global Styles - Before Screenshot",
	"Practical: Change Your Site\xE2\x80\x99s Global Styles - After Screenshot",
	'Practical: Style Book - Notes',
	'Practical: Style Book - Screenshot',
	'Practical: Landing Page with Layout Blocks - Note',
	'Practical: Landing Page with Layout Blocks - Screenshot',
	'Practical: Apply Custom CSS in the Site Editor - Notes',
	'Practical: Apply Custom CSS in the Site Editor - CSS',
	'Practical: Apply Custom CSS in the Site Editor - Screenshot',
	'Practical: Submit a Custom Block Pattern - Link',
	'Practical: Submit a Custom Block Pattern - Screenshot',
	'Practical: Test Your Site for Accessibility - Part 1 - Note',
	'Practical: Test Your Site for Accessibility - Part 1 - Screenshot',
	'Practical: Test Your Site for Accessibility - Part 2 - Note',
	'Practical: Test Your Site for Accessibility - Part 2 - Screenshot',
);

ck( 'the twenty-one Designer Track columns are twenty-one distinct names', count( array_unique( $designer ) ), 21 );
ck( 'and every one of them exists', not_offered( $designer, $rep_fields ), array() );

// The U+2019 is the base's. An ASCII apostrophe here would be a column Airtable does not have,
// and the failure would be silent: the API answers with the rows and without that field.
ck( 'the apostrophe in "Site\'s Global Styles" is U+2019, not an ASCII one',
    array(
        in_array( "Practical: Change Your Site\xE2\x80\x99s Global Styles - Notes", $rep_fields, true ),
        in_array( "Practical: Change Your Site's Global Styles - Notes", $rep_fields, true ),
    ),
    array( true, false ) );

// The two lower-case endings and the shortened lesson title, which look like slips and are not.
ck( 'the library lesson ends "- link" and "- image" in lower case',
    not_offered( array( 'Practical: Duplicate & Explore WP Design Library - link', 'Practical: Duplicate & Explore WP Design Library - image' ), $rep_fields ), array() );

// The types decide the control the form draws: five attachment columns become the image control,
// one single select becomes the select, and the rest are text.
ck( 'every typed field is a listed field', not_offered( array_keys( $rep_types ), $rep_fields ), array() );
ck( 'the twenty-one Designer Track columns are the typed ones', count( $rep_types ), 21 );
ck( 'ten of them are attachments, one per screenshot the form asks for', count( array_keys( $rep_types, 'multipleAttachments', true ) ), 10 );
ck( 'two are URLs', count( array_keys( $rep_types, 'url', true ) ), 2 );
ck( 'eight are long text', count( array_keys( $rep_types, 'multilineText', true ) ), 8 );
ck( 'and exactly one is a single select', array_keys( $rep_types, 'singleSelect', true ), array( 'Practical: Local WordPress Environment for Design Testing - Tool used' ) );

// The choice names are what a write has to send: no typecast goes with the PATCH, so 'MAMP'
// instead of the base's 'MAAMP' is a 422 for the whole record.
ck( 'the local-environment select offers the three tools, in the base\'s order and spelling',
    isset( $rep_choices['Practical: Local WordPress Environment for Design Testing - Tool used'] ) ? $rep_choices['Practical: Local WordPress Environment for Design Testing - Tool used'] : null,
    array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) );
ck( 'and every field with choices is a typed field', not_offered( array_keys( $rep_choices ), array_keys( $rep_types ) ), array() );

/* ---- the settings defaults agree with the base -------------------------- */

echo "\n=== The settings defaults name what the base offers ===\n";

$defaults = WPCPM_Settings::defaults();
$stages   = isset( $inst_choices['Current Stage'] ) ? $inst_choices['Current Stage'] : array();

// A status the base does not offer is not an error in a formula, it is a student nobody
// fetches. So the lists are checked entry by entry, and the report names the stray.
//
// **Against both Status columns.** `student_statuses` becomes a formula over the *Students
// Reports* table (`WPCPM_Students_Sync` and `WPCPM_CLI` build it from `report_status`), not over
// the Students table this file used to check it against alone. The two tables carry the same
// vocabulary and the Students fixture is the fuller record of it; the reports fixture adds the
// choices a later read named on the column the formula actually filters. A typo is in neither
// list and still fails here.
$report_status_choices = isset( $reports['status_choices_confirmed'] ) ? (array) $reports['status_choices_confirmed'] : array();
$offered_statuses      = array_merge( $status_choices, $report_status_choices );

ck( 'every student_statuses entry is a Status choice one of the two fixtures names', not_offered( $defaults['student_statuses'], $offered_statuses ), array() );
ck( 'the Designer Track is one the Students Reports table itself offers', in_array( 'Designer Track', $report_status_choices, true ), true );
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

// One 'Status' choice in the base is a real institution's name, so bin/anonymize-fixtures.php
// replaced it with a placeholder here. The comment has to say so: it is the sentence whoever
// refreshes this file acts on, and a refresh that skips the script puts the name back.
$students_comment = (string) ( isset( $students['_comment'] ) ? $students['_comment'] : '' );

ck( 'the Students fixture says its institution-named choice is a placeholder, and names the script', array(
	false !== strpos( $students_comment, 'bin/anonymize-fixtures.php' ),
	false !== strpos( $students_comment, 'Institution 84' ),
	in_array( 'Institution 84', isset( $stud_choices['Status'] ) ? $stud_choices['Status'] : array(), true ),
), array( true, true, true ) );

/* ---- the institutions seed carries no real record ------------------------- */

echo "\n=== The institutions seed is synthetic ===\n";

// bin/ never ships in the zip, but it is published on the public GitHub mirror, and this
// fixture is a dump of the live base: it published 300 live record IDs and 104 real
// organization names beside the stage the program had filed each of them under, three of them
// people who had typed their own name into the public application form (deep check of
// 7 September 2026, findings FSUIT-5 and FSUIT-13). bin/anonymize-fixtures.php replaces all of
// it; this section is what makes a raw dump fail here instead of reaching the mirror. Only
// counts are printed, never the offending value, because a failure means the file holds the
// real thing.
$seed      = fixture( 'institutions-index-seed.json' );
$seed_rows = isset( $seed['institutions'] ) ? (array) $seed['institutions'] : array();
$seed_lands = isset( $seed['countries'] ) ? (array) $seed['countries'] : array();

$off_id   = 0;
$off_name = 0;
$off_city = 0;
$off_site = 0;

foreach ( $seed_rows as $row ) {
	if ( 1 !== preg_match( '/^recSEED0[0-9]{9}$/', (string) $row['id'] ) ) {
		++$off_id;
	}

	foreach ( (array) $row['country'] as $link ) {
		if ( 1 !== preg_match( '/^recSEED0[0-9]{9}$/', (string) $link ) ) {
			++$off_id;
		}
	}

	if ( 1 !== preg_match( '/^(TEST - )?Institution [0-9]+$/', trim( (string) $row['name'] ) ) ) {
		++$off_name;
	}

	if ( '' !== trim( (string) $row['city'] ) && 1 !== preg_match( '/^City [0-9]+$/', trim( (string) $row['city'] ) ) ) {
		++$off_city;
	}

	if ( '' !== trim( (string) $row['website'] ) && 1 !== preg_match( '#^https://institution-[0-9]+\.example/$#', trim( (string) $row['website'] ) ) ) {
		++$off_site;
	}
}

foreach ( $seed_lands as $land ) {
	if ( 1 !== preg_match( '/^recSEED0[0-9]{9}$/', (string) $land['id'] ) ) {
		++$off_id;
	}
}

ck( 'the seed loaded', array( count( $seed_rows ) > 0, count( $seed_lands ) > 0 ), array( true, true ) );
ck( 'every record ID is a seed number, not a record in the base (run bin/anonymize-fixtures.php)', $off_id, 0 );
ck( 'every institution is "Institution N", so no row names an organization or a person', $off_name, 0 );
ck( 'every city is "City N" or empty', $off_city, 0 );
ck( 'every website is a reserved .example address', $off_site, 0 );

// The TEST record is found by its label, so the label has to survive anonymizing.
$labeled = 0;
foreach ( $seed_rows as $row ) {
	if ( 0 === strpos( (string) $row['name'], 'TEST' ) ) {
		++$labeled;
	}
}
ck( 'exactly one row is still labeled TEST, which is how the agreement suite finds it', $labeled, 1 );

// And nothing else under bin/ names a record either: five suites had a live ID typed into them
// by hand. "Real-looking" is the test bin/anonymize-fixtures.php uses and states its reasons
// for: every placeholder this repository invents is upper case, and the three mixed-case ones
// read as a word plus a run. A hit here is fixed by running that script, not by editing the
// suite, so that the seed and the suites keep one mapping between them.
$real = array();

$id_files = array();

foreach ( array( __DIR__, dirname( __DIR__ ) . '/docs' ) as $tree ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tree, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( $file->isFile() ) {
			$id_files[] = $file->getPathname();
		}
	}
}

foreach ( $id_files as $path ) {
	$file = new SplFileInfo( $path );
	if ( ! preg_match_all( '/\brec[A-Za-z0-9]{14}\b/', (string) file_get_contents( $path ), $found ) ) {
		continue;
	}

	foreach ( $found[0] as $id ) {
		$body = substr( $id, 3 );

		if ( 1 === preg_match( '/^SEED0[0-9]{9}$/', $body ) || 1 !== preg_match( '/[a-z]/', $body ) ) {
			continue;
		}

		if ( 1 === preg_match( '/(.)\1{3}/', $body ) || 1 === preg_match( '/[0-9]{5}/', $body ) ) {
			continue;
		}

		$real[ substr( $path, strlen( dirname( __DIR__ ) ) + 1 ) ] = true;
	}
}

ck( 'no file under bin/ or docs/ holds a record ID that reads as Airtable\'s (run bin/anonymize-fixtures.php)', array_keys( $real ), array() );

// The record IDs were not the only thing published. Suites used real people as sample display
// names, three of them with a WordPress.org profile address or a personal domain beside the
// name, which identifies somebody on its own, and both the suites and the specs named real
// organizations (deep check of 7 September 2026, the Task 8 review, rulings (a) and (b)).
//
// bin/refused-strings.php holds the decision, as `sha1()` hashes and never as the strings, so
// that this walk and the sweep can be self-contained without the list itself becoming the
// publication it exists to prevent. No file is skipped now: the walk reads the whole of bin/
// and the whole of docs/, and it reads bin/refused-strings.php like any other file.
//
// The failure names the file and never the value, so a red suite is not a leak either.
require_once __DIR__ . '/refused-strings.php';

$refused = wpcpm_refused_all();
$shapes  = 0;

foreach ( array_keys( $refused ) as $hash ) {
	if ( 1 === preg_match( '/^[0-9a-f]{40}$/', $hash ) ) {
		++$shapes;
	}
}

ck( 'the refused list records what it refuses as a hash, never as the string', array( $shapes, count( $refused ) >= 20 ), array( count( $refused ), true ) );

// The walk is worth nothing if the tokenizer finds nothing, and a tokenizer that returned an
// empty array would make the assertion below pass on a tree full of names. So it is asked first
// for something it must find, against a list of one hash this check makes up: a two-word name
// inside a quoted array value, wrapped in exactly the punctuation a suite wraps one in.
$probe = wpcpm_refused_found(
	"\tarray( 'name' => 'Ada Example', 'id' => 1 ),\n",
	array( sha1( 'Ada Example' ) => 'found it' )
);
ck( 'and the walk really does cut a line into the candidates it hashes', $probe, array( 'Ada Example' => 'found it' ) );

$named = array();

// The shipped source and the readme too: a refused string in a comment ships in the zip, which
// is a wider publication than the mirror (the fix wave's re-review, 8 September 2026).
$refused_files = array( dirname( __DIR__ ) . '/readme.txt' );

foreach ( array( __DIR__, dirname( __DIR__ ) . '/docs', dirname( __DIR__ ) . '/includes' ) as $tree ) {
	if ( ! is_dir( $tree ) ) {
		continue;
	}

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tree, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( $file->isFile() ) {
			$refused_files[] = $file->getPathname();
		}
	}
}

foreach ( $refused_files as $path ) {
	if ( is_file( $path ) && wpcpm_refused_found( (string) file_get_contents( $path ) ) ) {
		$named[ substr( $path, strlen( dirname( __DIR__ ) ) + 1 ) ] = true;
	}
}

ck( 'no file under bin/, docs/ or includes/ and not the readme carries a refused name, handle, profile address or organization (run bin/anonymize-fixtures.php)', array_keys( $named ), array() );

// Every fixture, not the seed alone: the sweep rewrote a choice list in
// students-table-fields.json too, and a refresh of one file on its own must not put a real
// address back on the mirror with nothing to notice it. The refused-name walk above already
// reads bin/fixtures/ as part of bin/.
$with_address = array();

foreach ( glob( __DIR__ . '/fixtures/*' ) as $fixture_file ) {
	if ( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', (string) file_get_contents( $fixture_file ) ) ) {
		$with_address[] = basename( $fixture_file );
	}
}

ck( 'no fixture holds an address of any kind', $with_address, array() );
ck( 'and it says what it is, and how to make it again', array(
	false !== strpos( (string) $seed['_comment'], 'synthetic' ),
	false !== strpos( (string) $seed['_comment'], 'bin/anonymize-fixtures.php' ),
	false !== strpos( (string) $seed['_comment'], 'no real record' ),
), array( true, true, true ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
