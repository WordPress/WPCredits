<?php
/**
 * The For Administrators section of an institution's page (1.123.29): what the application told the
 * program that the Airtable base has no column for, and the institution's history.
 *
 * What is pinned here and why:
 *
 * - **One section, two folded cards.** `render()` returns the heading "For Administrators", the
 *   line "Only Administrators see this section." and two cards in the shape the dashboard's other
 *   cards have: a `<section>` with the card class around a closed `<details>`. "From the
 *   application" comes first and "History" second.
 * - **It is for Administrators in the class itself,** and not only on the page that calls it: a
 *   caller without the manage capability, or with an ID that is no record, is handed nothing.
 * - **"From the application"** lists each kept answer under its form label, in the form's order
 *   and not the order they were stored in, the languages joined by ", ", and only the answers the
 *   form asks and the record holds: a key the form does not know never shows. It says "Nothing
 *   kept from the application." for an institution approved before the answers were kept.
 * - **"History"** lists the entries newest first. A row holds the moment in the site's formats, who
 *   did it ("The site" for the system, "A removed account" for a user who is gone) and a plain
 *   sentence. The latest 100 are drawn, and "Showing the latest 100." says there were more.
 *   Rows that belong to a sponsor, whatever kind they carry and whatever key they were written
 *   under, never appear in an institution's history.
 * - **Plain sentences.** For every institution kind the plugin writes, `sentence()` is a non-empty
 *   sentence with no record ID, no kind slug and no meta key in it, whether the stored message is
 *   a plain sentence (it is returned), empty (`student_linked` stores none), or built around a
 *   slug, an ID or a key (the words for the kind take its place). An agreement's revoke row reads
 *   its sentence and then "Note: " and the note it was revoked with.
 * - **Every string is escaped at output.**
 *
 * The stand-ins below model enough of WordPress for the audit log and the institution record, which
 * are the real classes: private posts with meta, found by a meta value, newest first. The form's
 * five site-only questions are stood in for, because the application class loads the whole mentor
 * form; the labels are checked against its source below, so the stand-in cannot drift.
 *
 * Run from the plugin root:  php bin/test-institution-history.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['users'] = array();
$GLOBALS['next']  = 100;
$GLOBALS['can']   = true;

class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class WP_Post {
	public $ID = 0;
	public $post_type = 'post';
	public $post_status = 'draft';
	public $post_title = '';
	public $post_author = 0;
	public $post_content = '';

	public function __construct( array $fields = array() ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_User {
	public $ID = 0;
	public $display_name = '';
	public $user_login = '';

	public function __construct( $id, $display_name, $user_login = '' ) {
		$this->ID           = $id;
		$this->display_name = $display_name;
		$this->user_login   = $user_login;
	}
}

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}\z/', trim( $value ) );
	}
}

/**
 * The form's five site-only questions, in the form's order, with the labels it asks them under.
 * Checked against the class's source below.
 */
class WPCPM_Institution_Application {
	public static function site_fields() {
		return array(
			'contact_role'      => array( 'label' => 'Their role at the institution', 'site' => true ),
			'student_languages' => array( 'label' => 'In which languages do your students study?', 'site' => true ),
			'signer_name'       => array( 'label' => 'Who will sign the agreement for the institution?', 'site' => true ),
			'signer_title'      => array( 'label' => 'Their title', 'site' => true ),
			'credits_page'      => array( 'label' => 'Your page about WordPress Credits', 'site' => true ),
		);
	}
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function esc_attr( $text ) {
	return esc_html( $text );
}

function number_format_i18n( $number, $decimals = 0 ) {
	return (string) $number;
}

function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_text_field( $text ) {
	return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) );
}

function sanitize_textarea_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function add_action() {}

function register_post_type() {}

function wp_date( $format, $time = null ) {
	return gmdate( $format, (int) $time );
}

function get_option( $key, $default = false ) {
	return $default;
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['can'] ) && WPCPM_Roles::CAP_MANAGE === $capability;
}

function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function wp_slash( $value ) {
	return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
}

function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}

// As core's insert: the fields unslashed, so words reach it as slashed copies.
function wp_insert_post( $postarr, $wp_error = false ) {
	$id                      = $GLOBALS['next']++;
	$postarr                 = wp_unslash( $postarr );
	$postarr['ID']           = $id;
	$GLOBALS['posts'][ $id ] = new WP_Post( $postarr );

	return $id;
}

// As core's: what is written is unslashed first, at any depth.
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );

	return true;
}

function get_post_meta( $id, $key = '', $single = false ) {
	return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : '';
}

// A minute apart, in the order the rows were written, so the newest row is the highest ID.
function get_post_time( $format, $gmt, $post, $translate = false ) {
	return 1757000000 + ( (int) $post->ID * 60 );
}

/*
 * Enough of get_posts() for both readers: the post type, the status (one, or a list), a meta_key
 * with its value or a meta_query of `key` + `value` clauses, `fields`, and `numberposts`; newest
 * ID first, which is how the audit reads orders its rows (date, then ID).
 */
function get_posts( array $args ) {
	$ids = array_keys( $GLOBALS['posts'] );
	rsort( $ids );
	$out = array();

	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];

		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( isset( $args['post_status'] ) && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) {
			continue;
		}

		if ( isset( $args['meta_key'] ) ) {
			$have = get_post_meta( $id, $args['meta_key'], true );

			if ( '' === $have || 0 !== strcasecmp( (string) $have, (string) $args['meta_value'] ) ) {
				continue;
			}
		}

		$ok = true;

		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			$have = get_post_meta( $id, $clause['key'], true );
			$ok   = $ok && '' !== $have && 0 === strcasecmp( (string) $have, (string) $clause['value'] );
		}

		if ( ! $ok ) {
			continue;
		}

		$out[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $id : $post;

		if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 && count( $out ) >= (int) $args['numberposts'] ) {
			break;
		}
	}

	return $out;
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-dates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-audit.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-record.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-history.php';

$fail = 0;

function ck( $label, $actual, $expected ) {
	global $fail;

	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}

	++$fail;
	echo "FAIL $label\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * Write one audit row about an institution, as the plugin's writers do.
 *
 * @param string $institution Record ID.
 * @param string $kind        The kind.
 * @param string $message     The stored message.
 * @param int    $actor       The user, 0 for the system.
 * @param array  $data        The row's facts.
 * @return int The row's post ID.
 */
function audit_row( $institution, $kind, $message, $actor = 0, array $data = array() ) {
	$row = WPCPM_Institution_Audit::record(
		array(
			'kind'        => $kind,
			'institution' => $institution,
			'subject'     => '5',
			'actor'       => $actor,
			'ground'      => WPCPM_Institution_Audit::GROUND_MANAGER,
			'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
			'message'     => $message,
			'data'        => $data,
		)
	);

	return is_int( $row ) ? $row : 0;
}

/** The rows of the History card, in page order. */
function history_rows( $html ) {
	preg_match_all( '/<li class="wpcpm-admin-history__row">(.*?)<\/li>/s', $html, $found );

	return $found[1];
}

/** The text of the page, tags gone and entities decoded. */
function text_of( $html ) {
	return html_entity_decode( trim( preg_replace( '/\s+/', ' ', strip_tags( $html ) ) ), ENT_QUOTES, 'UTF-8' );
}

$krakow    = 'recKRAKOW12345678';
$older     = 'recOLDER123456789';
$empty     = 'recEMPTY123456789';
$crowded   = 'recCROWDED1234567';
$full      = 'recFULLHUNDRED001';
$revoked   = 'recREVOKED1234567';
$escaping  = 'recESCAPING123456';
$sponsored = 'recSPONSOR1234567';
$stopped_id = 'recSTOPPED1234567';

ck(
	'the fixture\'s record IDs are the shape Airtable uses',
	array_map( array( 'WPCPM_Mentors_Sync', 'is_record_id' ), array( $krakow, $older, $empty, $crowded, $full, $revoked, $escaping, $sponsored, $stopped_id ) ),
	array( true, true, true, true, true, true, true, true, true )
);

$GLOBALS['users'][7] = new WP_User( 7, 'Test Member', 'testmember' );
$GLOBALS['users'][8] = new WP_User( 8, '', 'login-only' );
$GLOBALS['users'][9] = new WP_User( 9, '<script>alert(1)</script>Bold', 'bold' );

echo "=== The five questions, as the form asks them ===\n";

$application = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-application.php' );

foreach ( WPCPM_Institution_Application::site_fields() as $key => $spec ) {
	ck(
		'the stand-in\'s label for ' . $key . ' is the form\'s own',
		1 === preg_match( '/\'label\'\s*=>\s*__\(\s*\'' . preg_quote( $spec['label'], '/' ) . '\'/', $application ),
		true
	);
}

echo "\n=== The section ===\n";

WPCPM_Institution_Record::fill_empty(
	$krakow,
	array(
		'credits_page'      => 'https://example.test/credits',
		'signer_title'      => 'Dean of the Faculty of Engineering',
		'signer_name'       => 'Example Signer',
		'student_languages' => array( 'English', 'Polish' ),
		'contact_role'      => 'Internship coordinator',
		'not_a_question'    => 'Never shown',
	)
);
audit_row( $krakow, 'member_added', 'Test Member was added as a member (approved).', 7 );
audit_row( $krakow, 'student_linked', '', 0 );
audit_row( $krakow, 'invite_accepted', 'The invitation to invitee@example.test was accepted.', 99 );

$html = WPCPM_Institution_History::render( $krakow );

ck( 'render() returns a string', is_string( $html ), true );
ck( 'one section holds the whole of it', array( 0 === strpos( $html, '<section class="wpcpm-institution__admin"' ), substr( $html, -10 ), substr_count( $html, 'class="wpcpm-institution__admin"' ) ), array( true, '</section>', 1 ) );
ck( 'with the heading, in the level and classes a module title wears', substr_count( $html, '<h2 class="wpcpm-student__heading wpcpm-institution__module-title">For Administrators</h2>' ), 1 );
ck( 'and the line that says who it is for, under it', substr_count( $html, '<p class="wpcpm-student__note wpcpm-institution__module-lead">Only Administrators see this section.</p>' ), 1 );
ck( 'the line is under the heading', strpos( $html, 'For Administrators</h2>' ) < strpos( $html, 'Only Administrators see this section.' ), true );

ck(
	'two cards, each a section with the card class around a closed details',
	array(
		preg_match_all( '/<section class="wpcpm-institution__card [^"]*"[^>]*>\s*<details class="wpcpm-group wpcpm-group__disclosure [^"]*">\s*<summary class="wpcpm-group__summary">/', $html ),
		substr_count( $html, '<details' ),
		preg_match( '/<details[^>]*\sopen/', $html ),
	),
	array( 2, 2, 0 )
);

preg_match_all( '/<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">([^<]*)<\/h3><span class="wpcpm-mentee__toggle" aria-hidden="true"><\/span><\/summary>/', $html, $titles );
ck( 'their summaries are the dashboard\'s own: a title and the toggle, From the application first and History second', $titles[1], array( 'From the application', 'History' ) );
ck( 'each body is a group body', substr_count( $html, '<div class="wpcpm-group__body ' ), 2 );

echo "\n=== For Administrators alone ===\n";

$GLOBALS['can'] = false;
ck( 'without the capability the class hands back nothing', WPCPM_Institution_History::render( $krakow ), '' );
$GLOBALS['can'] = true;
ck( 'an ID that is no record is handed nothing either', array( WPCPM_Institution_History::render( 'not-a-record' ), WPCPM_Institution_History::render( '' ) ), array( '', '' ) );
ck( 'and the manager is shown the section again', 0 === strpos( WPCPM_Institution_History::render( $krakow ), '<section class="wpcpm-institution__admin"' ), true );

echo "\n=== From the application ===\n";

$text = text_of( $html );

ck(
	'every kept answer sits under its form label, in the form\'s order and not the order it was stored in',
	array(
		strpos( $html, 'Their role at the institution' ) < strpos( $html, 'In which languages do your students study?' ),
		strpos( $html, 'In which languages do your students study?' ) < strpos( $html, 'Who will sign the agreement for the institution?' ),
		strpos( $html, 'Who will sign the agreement for the institution?' ) < strpos( $html, 'Their title' ),
		strpos( $html, 'Their title' ) < strpos( $html, 'Your page about WordPress Credits' ),
	),
	array( true, true, true, true )
);
ck( 'as a list of terms and descriptions', array( substr_count( $html, '<dl class="wpcpm-admin-answers__list">' ), substr_count( $html, '<dt>' ), substr_count( $html, '<dd>' ) ), array( 1, 5, 5 ) );
ck( 'the answers are the words stored', array( false !== strpos( $html, '<dd>Internship coordinator</dd>' ), false !== strpos( $html, '<dd>Example Signer</dd>' ), false !== strpos( $html, '<dd>Dean of the Faculty of Engineering</dd>' ), false !== strpos( $html, '<dd>https://example.test/credits</dd>' ) ), array( true, true, true, true ) );
ck( 'the languages are joined by a comma and a space', false !== strpos( $html, '<dd>English, Polish</dd>' ), true );
ck( 'a key the form does not ask is never shown, nor its name nor its value', array( false !== strpos( $html, 'not_a_question' ), false !== strpos( $html, 'Never shown' ) ), array( false, false ) );

WPCPM_Institution_Record::fill_empty( $older, array( 'contact_role' => 'Head of department' ) );
$partial = WPCPM_Institution_History::render( $older );
ck( 'an institution that kept one answer lists that one', array( substr_count( $partial, '<dt>' ), false !== strpos( $partial, '<dt>Their role at the institution</dt>' ), false !== strpos( $partial, 'Head of department' ), false !== strpos( $partial, 'Their title' ) ), array( 1, true, true, false ) );

$none = WPCPM_Institution_History::render( $empty );
ck( 'an institution approved before the answers were kept says so', array( false !== strpos( $none, 'Nothing kept from the application.' ), false !== strpos( $none, '<dl' ) ), array( true, false ) );
ck( 'in the card\'s own words', false !== strpos( $none, '<p class="wpcpm-institution__empty">Nothing kept from the application.</p>' ), true );
ck( 'and shows no label for answers it does not hold', false !== strpos( $none, 'Their role at the institution' ), false );

WPCPM_Institution_Record::fill_empty( $escaping, array( 'contact_role' => '<script>alert(1)</script> & "quoted"' ) );
$escaped = WPCPM_Institution_History::render( $escaping );
ck( 'an answer is escaped at output', array( false !== strpos( $escaped, '<script>' ), false !== strpos( $escaped, '&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;quoted&quot;' ) ), array( false, true ) );

echo "\n=== History ===\n";

$rows = history_rows( $html );
ck( 'one row per entry', count( $rows ), 3 );
ck( 'newest first', array( false !== strpos( $rows[0], 'The invitation to invitee@example.test was accepted.' ), false !== strpos( $rows[1], 'A student was linked' ), false !== strpos( $rows[2], 'Test Member was added as a member (approved).' ) ), array( true, true, true ) );

$entries = WPCPM_Institution_Audit::entries_for( $krakow, 10 );
ck( 'each row holds the moment in the site\'s formats', array( false !== strpos( $rows[0], esc_html( WPCPM_Dates::moment( $entries[0]['time'] ) ) ), false !== strpos( $rows[2], esc_html( WPCPM_Dates::moment( $entries[2]['time'] ) ) ) ), array( true, true ) );
ck( 'and the moment is not empty', '' !== WPCPM_Dates::moment( $entries[0]['time'] ), true );
ck( 'in a time element carrying the instant', 1 === preg_match( '/<time datetime="' . preg_quote( gmdate( 'c', $entries[0]['time'] ), '/' ) . '">/', $rows[0] ), true );
ck( 'each row says who: the account, the site, a removed account', array( false !== strpos( $rows[2], 'Test Member</span>' ), false !== strpos( $rows[1], 'The site</span>' ), false !== strpos( $rows[0], 'A removed account</span>' ) ), array( true, true, true ) );
ck( 'and what happened, as a sentence of its own', array( false !== strpos( $rows[1], 'A student was linked to this institution.' ), substr_count( $html, 'wpcpm-admin-history__what' ) ), array( true, 3 ) );
ck( 'nothing in the card says how many there are when all of them are drawn', false !== strpos( $html, 'Showing the latest' ), false );

$quiet = WPCPM_Institution_History::render( $empty );
ck( 'an institution with no entries says so, and draws no list', array( false !== strpos( $quiet, '<p class="wpcpm-institution__empty">Nothing has been recorded for this institution yet.</p>' ), false !== strpos( $quiet, '<ol' ), false !== strpos( $quiet, 'Showing the latest' ) ), array( true, false, false ) );

// Core's own display name field is user-typed text.
audit_row( $escaping, 'member_added', 'Added.', 9 );
audit_row( $escaping, 'member_added', 'Added again.', 8 );
$names = history_rows( WPCPM_Institution_History::render( $escaping ) );
ck( 'a name is escaped, and a user with no display name is known by their login', array( false !== strpos( $names[1], '<script>' ), false !== strpos( $names[1], '&lt;script&gt;alert(1)&lt;/script&gt;Bold' ), false !== strpos( $names[0], 'login-only</span>' ) ), array( false, true, true ) );

echo "\n=== The latest 100 ===\n";

for ( $n = 1; $n <= 101; $n++ ) {
	audit_row( $crowded, 'member_added', sprintf( 'Entry %03d.', $n ), 7 );
}

$page = WPCPM_Institution_History::render( $crowded );
ck( 'a hundred and one entries draw a hundred rows', count( history_rows( $page ) ), 100 );
ck( 'the newest hundred: the last is drawn and the first is not', array( false !== strpos( $page, 'Entry 101.' ), false !== strpos( $page, 'Entry 002.' ), false !== strpos( $page, 'Entry 001.' ) ), array( true, true, false ) );
ck( 'and the line says there were more, once, after the list', array( substr_count( $page, 'Showing the latest 100.' ), strpos( $page, 'Showing the latest 100.' ) > strrpos( $page, 'wpcpm-admin-history__row' ) ), array( 1, true ) );
ck( 'in the card\'s own paragraph', false !== strpos( $page, '<p class="wpcpm-institution__empty wpcpm-admin-history__limit">Showing the latest 100.</p>' ), true );

for ( $n = 1; $n <= 100; $n++ ) {
	audit_row( $full, 'member_added', sprintf( 'Entry %03d.', $n ), 7 );
}

$page = WPCPM_Institution_History::render( $full );
ck( 'exactly a hundred entries draw a hundred rows and no line', array( count( history_rows( $page ) ), false !== strpos( $page, 'Showing the latest' ) ), array( 100, false ) );

echo "\n=== A sponsor's rows are not an institution's ===\n";

audit_row( $sponsored, 'member_added', 'Institution member row.', 7 );
foreach ( array( 'member_added', 'application_approved', 'provisioned' ) as $kind ) {
	WPCPM_Institution_Audit::record_sponsor(
		array(
			'kind'     => $kind,
			'sponsor'  => $sponsored,
			'subject'  => '5',
			'actor'    => 7,
			'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
			'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
			'message'  => 'SPONSOR ROW ' . $kind . '.',
		)
	);
}
WPCPM_Institution_Audit::record_program(
	array(
		'kind'     => 'member_added',
		'subject'  => '5',
		'actor'    => 7,
		'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
		'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
		'message'  => 'PROGRAM ROW.',
	)
);

$shown = WPCPM_Institution_History::render( $sponsored );
ck(
	'a sponsor row with the same kind, under the very same key, is not in the institution\'s history; nor is the program\'s',
	array( count( history_rows( $shown ) ), false !== strpos( $shown, 'Institution member row.' ), false !== strpos( $shown, 'SPONSOR ROW' ), false !== strpos( $shown, 'PROGRAM ROW' ) ),
	array( 1, true, false, false )
);

echo "\n=== Plain sentences ===\n";

// The thirty kinds the plugin writes about an institution, and where each is declared. The
// source is read below, so a kind renamed there fails here rather than reaching a row unworded.
$kinds = array(
	'member_added',
	'member_removed',
	'application_approved',
	'agreement_generated',
	'agreement_on_file',
	'agreement_upload',
	'agreement_accept',
	'agreement_return',
	'agreement_withdraw',
	'agreement_revoke',
	'agreement_reinstate',
	'claim_refused',
	'claim_locked',
	'import_created',
	'import_stopped',
	'invite_sent',
	'invite_resent',
	'invite_accepted',
	'invite_cancelled',
	'invite_expired',
	'note_added',
	'note_deleted',
	'request_raised',
	'request_resolved',
	'student_edited',
	'extend',
	'student_graduated',
	'student_withdrawn',
	'student_linked',
	'student-invite-resent',
);

ck( 'thirty kinds, each once', array( count( $kinds ), count( array_unique( $kinds ) ) ), array( 30, 30 ) );

$declared = '';
foreach ( array_merge( glob( __DIR__ . '/../includes/modules/class-wpcpm-institution-*.php' ), array( __DIR__ . '/../includes/modules/class-wpcpm-institutions.php', __DIR__ . '/../includes/modules/class-wpcpm-agreement-generate.php' ) ) as $file ) {
	$declared .= (string) file_get_contents( $file );
}

$undeclared = array();
foreach ( $kinds as $kind ) {
	if ( 1 !== preg_match( '/const\s+\w+\s*=\s*\'' . preg_quote( $kind, '/' ) . '\'\s*;/', $declared ) ) {
		$undeclared[] = $kind;
	}
}
ck( 'every kind is a constant the plugin declares', $undeclared, array() );

// What each writer stores, as it stores it (translated at write time, so in English here).
$stored = array(
	'member_added'          => 'Test Member was added as a member (approved).',
	'member_removed'        => 'Test Member is no longer a member (removed).',
	'application_approved'  => 'An application was approved, and the institution record and its first account were created.',
	'agreement_generated'   => 'The program\'s Collaboration Agreement template was generated for signature.',
	'agreement_on_file'     => 'A Collaboration Agreement the program already held was recorded as on file, and the account opened.',
	'agreement_upload'      => 'A signed Collaboration Agreement was uploaded and is waiting for an Administrator to read it.',
	'agreement_accept'      => 'A signed Collaboration Agreement was accepted, and the institution\'s account opened.',
	'agreement_return'      => 'A signed Collaboration Agreement was returned to the institution with a note.',
	'agreement_withdraw'    => 'A submitted Collaboration Agreement was withdrawn and its file deleted.',
	'agreement_revoke'      => 'An accepted Collaboration Agreement was revoked, and the institution\'s account closed.',
	'agreement_reinstate'   => 'A revoked Collaboration Agreement was reinstated, and the institution\'s account opened again.',
	'claim_refused'         => 'A live read placed this record with another institution than the site held for it.',
	'claim_locked'          => 'This account was refused 5 claims today, on the shape of the request or on what the site holds, and cannot change the roster again until tomorrow.',
	'import_created'        => 'Import finished: 12 created, 1 blocked, 0 failed.',
	// As `WPCPM_Institution_Create::park()` stores it: the reason is one of agreement, no-actor and not-allowed,
	// through sanitize_key(), and the count says "students" for one as well.
	'import_stopped'        => 'The import stopped: not-allowed. 3 students had been created.',
	'invite_sent'           => 'Test Member invited invitee@example.test to act for this institution.',
	'invite_resent'         => 'Test Member sent the invitation to invitee@example.test again, with a new link.',
	'invite_accepted'       => 'The invitation to invitee@example.test was accepted.',
	'invite_cancelled'      => 'The invitation was canceled: the member who sent it is no longer one.',
	'invite_expired'        => 'The invitation to invitee@example.test expired after 14 days without being accepted.',
	'note_added'            => 'An institution note was added about this student. The note stays on the student card and is not copied here.',
	'note_deleted'          => 'An institution note about this student was deleted. The text of the note is not kept here.',
	'request_raised'        => 'A mentor is wanted. Raised by the institution and waiting for an Administrator.',
	'request_resolved'      => "An Administrator closed this request as handled.\nThe mentor was found.",
	'student_edited'        => 'Full name: Example Student to Example Learner. Field of study: empty to Design & Creative Media.',
	'extend'                => 'End date: 2026-01-31 to 2026-06-30.',
	'student_graduated'     => 'Status: In Sensei to Graduate.',
	'student_withdrawn'     => 'Status: In Sensei to Withdrawn.',
	'student_linked'        => '',
	'student-invite-resent' => 'Welcome invitation sent from the Institution Dashboard.',
);

ck( 'the stored messages above cover the same thirty kinds', array_keys( $stored ), $kinds );

// What may never show: a record ID, a kind slug, or a meta key. The slug is matched as a word, so
// "extend" does not count against "extended".
$slug_pattern = '/(?<![A-Za-z0-9_-])(?:' . implode( '|', array_map( static function ( $kind ) { return preg_quote( $kind, '/' ); }, $kinds ) ) . ')(?![A-Za-z0-9_-])/i';

function leaks( $sentence, $slug_pattern ) {
	$found = array();

	if ( '' === trim( (string) $sentence ) ) {
		$found[] = 'empty';
	}
	if ( 1 === preg_match( '/rec[A-Za-z0-9]{14}/', (string) $sentence ) ) {
		$found[] = 'record ID';
	}
	if ( 1 === preg_match( $slug_pattern, (string) $sentence ) ) {
		$found[] = 'kind slug';
	}
	if ( 1 === preg_match( '/_wpcpm|wpcpm_|\bstudents\||\|/i', (string) $sentence ) ) {
		$found[] = 'meta key';
	}
	if ( 1 === preg_match( '/[A-Za-z0-9]_[A-Za-z0-9]/', preg_replace( '/\S+@\S+/', '', (string) $sentence ) ) ) {
		$found[] = 'snake_case word';
	}

	return $found;
}

$problems = array();
$worded   = array();
$returned = array();
// The facts a writer stores beside its message, for the kind whose sentence is built from them.
$stored_data = array(
	'import_stopped' => array( 'batch' => 7, 'reason' => 'not-allowed', 'created' => 3 ),
);

foreach ( $kinds as $kind ) {
	$entry = array(
		'kind'    => $kind,
		'message' => $stored[ $kind ],
		'data'    => isset( $stored_data[ $kind ] ) ? $stored_data[ $kind ] : array(),
		'actor'   => 0,
	);

	$sentence          = WPCPM_Institution_History::sentence( $entry );
	$returned[ $kind ] = $sentence;

	foreach ( leaks( $sentence, $slug_pattern ) as $leak ) {
		$problems[] = $kind . ' as stored: ' . $leak;
	}

	// The same kind with an empty message, and with one built round a record ID, a kind slug and
	// a meta key: the words for the kind take over, and they hold none of those.
	foreach ( array( 'empty' => '', 'ids' => 'Record recABCDEFGHIJKLMN of kind ' . $kind . ' changed _wpcpm_log_kind and students|End Date.', 'key' => '_wpcpm_inst_record', 'slug' => $kind ) as $name => $message ) {
		$words = WPCPM_Institution_History::sentence( array_merge( $entry, array( 'message' => $message ) ) );

		foreach ( leaks( $words, $slug_pattern ) as $leak ) {
			$problems[] = $kind . ' with ' . $name . ' message: ' . $leak;
		}

		if ( 'empty' === $name ) {
			$worded[ $kind ] = $words;
		}
	}
}
ck( 'for all thirty kinds, a stored message, an empty one or a leaking one gives a sentence with no ID, slug or key', $problems, array() );
ck( 'each kind has words of its own', array( count( $worded ), count( array_unique( $worded ) ) ), array( 30, 30 ) );
ck( 'and a sentence ends with a full stop', array_values( array_filter( $worded, static function ( $sentence ) { return '.' !== substr( $sentence, -1 ); } ) ), array() );

$plain = array_diff( $kinds, array( 'import_stopped', 'student_linked', 'request_resolved', 'agreement_revoke' ) );
$kept  = array();
foreach ( $plain as $kind ) {
	if ( $returned[ $kind ] !== $stored[ $kind ] ) {
		$kept[] = $kind;
	}
}
ck( 'a stored message that is a plain sentence is the one returned', $kept, array() );
ck( 'the stopped import never reads its stored message, which names a slug and says "1 students"', array( $returned['import_stopped'] === $stored['import_stopped'], false !== strpos( $returned['import_stopped'], 'not-allowed' ) ), array( false, false ) );
ck( 'the linked student, whose message is empty, gets words', $returned['student_linked'], $worded['student_linked'] );
ck( 'a request closed with a note keeps its lines run into one', $returned['request_resolved'], 'An Administrator closed this request as handled. The mentor was found.' );

ck( 'a kind the map does not know, with a plain message, reads as that message', WPCPM_Institution_History::sentence( array( 'kind' => 'some_future_kind', 'message' => 'Something happened to this institution.' ) ), 'Something happened to this institution.' );
$unknown = WPCPM_Institution_History::sentence( array( 'kind' => 'some_future_kind', 'message' => '' ) );
ck( 'and with none, reads as a sentence that names nothing', array( '' !== $unknown, leaks( $unknown, $slug_pattern ), false !== strpos( $unknown, 'some_future_kind' ) ), array( true, array(), false ) );
ck( 'an entry with nothing in it still reads', '' !== WPCPM_Institution_History::sentence( array() ), true );

echo "\n=== The stopped import ===\n";

// `WPCPM_Institution_Create::park()` stores "The import stopped: <reason>. N students had been created."
// with a reason of agreement, no-actor or not-allowed and the facts batch, reason and created. The
// sentence is built from the facts, so no reason slug shows and one student is not "1 students".
$stopped = static function ( $reason, $created, $message = null ) {
	return WPCPM_Institution_History::sentence(
		array(
			'kind'    => 'import_stopped',
			'message' => null === $message ? sprintf( 'The import stopped: %s. %s students had been created.', $reason, $created ) : $message,
			'data'    => array( 'batch' => 7, 'reason' => $reason, 'created' => $created ),
		)
	);
};
$stems   = array(
	'agreement'     => 'The import stopped because the institution\'s agreement is no longer in force.',
	'no-actor'      => 'The import stopped because the batch no longer names the account that confirmed it.',
	'not-allowed'   => 'The import stopped because the account that confirmed it may no longer add students.',
	'anything-else' => 'The import stopped.',
);
foreach ( $stems as $reason => $stem ) {
	ck( 'reason ' . $reason . ': three created', $stopped( $reason, 3 ), $stem . ' 3 students had been created.' );
	ck( 'reason ' . $reason . ': one created reads in the singular', $stopped( $reason, 1 ), $stem . ' 1 student had been created.' );
	ck( 'reason ' . $reason . ': none created reads in the plural', $stopped( $reason, 0 ), $stem . ' 0 students had been created.' );
}
ck( 'a missing reason reads as the plain stem', WPCPM_Institution_History::sentence( array( 'kind' => 'import_stopped', 'message' => 'The import stopped: . 2 students had been created.', 'data' => array( 'created' => 2 ) ) ), 'The import stopped. 2 students had been created.' );
ck( 'a missing count reads 0, in the plural', WPCPM_Institution_History::sentence( array( 'kind' => 'import_stopped', 'message' => '', 'data' => array( 'reason' => 'agreement' ) ) ), $stems['agreement'] . ' 0 students had been created.' );
ck( 'a row with no facts at all reads as the plain stem', WPCPM_Institution_History::sentence( array( 'kind' => 'import_stopped', 'message' => 'The import stopped: not-allowed. 3 students had been created.' ) ), 'The import stopped. 0 students had been created.' );
ck( 'a count that is no number reads 0', WPCPM_Institution_History::sentence( array( 'kind' => 'import_stopped', 'data' => array( 'reason' => 'no-actor', 'created' => 'many' ) ) ), $stems['no-actor'] . ' 0 students had been created.' );
$shown = implode( ' ', array_map( static function ( $reason ) use ( $stopped ) { return $stopped( $reason, 3 ); }, array_keys( $stems ) ) );
ck( 'no reason slug and no stored colon shows, for any reason', array( false !== strpos( $shown, 'no-actor' ), false !== strpos( $shown, 'not-allowed' ), false !== strpos( $shown, 'stopped:' ) ), array( false, false, false ) );

audit_row( $stopped_id, 'import_stopped', 'The import stopped: no-actor. 1 students had been created.', 0, array( 'batch' => 7, 'reason' => 'no-actor', 'created' => 1 ) );
$stopped_rows = history_rows( WPCPM_Institution_History::render( $stopped_id ) );
ck( 'the History card draws it as the sentence, not as stored', array( count( $stopped_rows ), false !== strpos( $stopped_rows[0], 'The import stopped because the batch no longer names the account that confirmed it. 1 student had been created.' ), false !== strpos( $stopped_rows[0], 'no-actor' ) ), array( 1, true, false ) );

echo "\n=== The revoke row ===\n";

$revoke = array(
	'kind'    => 'agreement_revoke',
	'message' => $stored['agreement_revoke'],
	'data'    => array( 'note' => 'The partnership has ended, so the agreement ends with it.' ),
);
ck( 'reads its sentence, then "Note: " and the note', WPCPM_Institution_History::sentence( $revoke ), 'An accepted Collaboration Agreement was revoked, and the institution\'s account closed. Note: The partnership has ended, so the agreement ends with it.' );
ck( 'the note follows the words for the kind when the message is empty', WPCPM_Institution_History::sentence( array_merge( $revoke, array( 'message' => '' ) ) ), $worded['agreement_revoke'] . ' Note: The partnership has ended, so the agreement ends with it.' );
ck( 'a revoke row from before the note was kept has no note line', WPCPM_Institution_History::sentence( array_merge( $revoke, array( 'data' => array() ) ) ), $stored['agreement_revoke'] );
ck( 'nor does one whose note is only space', WPCPM_Institution_History::sentence( array_merge( $revoke, array( 'data' => array( 'note' => '   ' ) ) ) ), $stored['agreement_revoke'] );
ck( 'no other kind reads a note', WPCPM_Institution_History::sentence( array( 'kind' => 'agreement_return', 'message' => $stored['agreement_return'], 'data' => array( 'note' => 'Not for the history.' ) ) ), $stored['agreement_return'] );

audit_row( $revoked, 'agreement_revoke', $stored['agreement_revoke'], 7, array( 'kind' => 'own', 'members' => 2, 'note' => 'It ended <b>early</b> & for good.' ) );
$revoked_rows = history_rows( WPCPM_Institution_History::render( $revoked ) );
ck( 'the row draws the note, escaped at output', array( count( $revoked_rows ), false !== strpos( $revoked_rows[0], 'account closed. Note: It ended early &amp; for good.' ) ), array( 1, true ) );

echo "\n=== Who ===\n";

ck( 'an account is its display name', WPCPM_Institution_History::who( array( 'actor' => 7 ) ), 'Test Member' );
ck( 'the system is the site', array( WPCPM_Institution_History::who( array( 'actor' => 0 ) ), WPCPM_Institution_History::who( array() ) ), array( 'The site', 'The site' ) );
ck( 'an account that is gone is a removed account', WPCPM_Institution_History::who( array( 'actor' => 4040 ) ), 'A removed account' );
ck( 'an account with no display name is its login', WPCPM_Institution_History::who( array( 'actor' => 8 ) ), 'login-only' );
ck( 'who() returns text, not markup', WPCPM_Institution_History::who( array( 'actor' => 9 ) ), '<script>alert(1)</script>Bold' );

echo "\n=== The section keeps the modules' rhythm on the WPCredits theme ===\n";

// On `.wpc-institution-page` the theme gives every `.wpcpm-module` a hairline over its title, 20px of
// top padding and 22px from the module above, and cards inside a module margin 0 and 10px apart
// (assets/css/dashboard.css of the theme, `.wpc-institution-page .wpcpm-module` and the three rules
// after it). The section is not a module (the script that arranges the modules would collect it), so
// the plugin's stylesheet carries the same values for it, with the theme's own soft line, #ededed, as
// the fallback. Parsed rather than matched as text, so the check reads values and not spacing.
$stylesheet = (string) file_get_contents( __DIR__ . '/../assets/css/institution.css' );
$rules      = array();
foreach ( explode( '}', preg_replace( '#/\*.*?\*/#s', '', $stylesheet ) ) as $chunk ) {
	if ( false === strpos( $chunk, '{' ) ) {
		continue;
	}

	list( $selectors, $body ) = explode( '{', $chunk, 2 );

	foreach ( explode( ',', $selectors ) as $selector ) {
		$selector = trim( preg_replace( '/\s+/', ' ', $selector ) );

		foreach ( array_filter( array_map( 'trim', explode( ';', $body ) ) ) as $declaration ) {
			list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );

			$rules[ $selector ][ $property ] = trim( preg_replace( '/\s+/', ' ', $value ) );
		}
	}
}

$page    = '.wpc-institution-page .wpcpm-institution__admin';
$section = isset( $rules[ $page ] ) ? $rules[ $page ] : array();
$cards   = isset( $rules[ $page . ' > .wpcpm-institution__card' ] ) ? $rules[ $page . ' > .wpcpm-institution__card' ] : array();
$gap     = isset( $rules[ $page . ' > .wpcpm-institution__card + .wpcpm-institution__card' ] ) ? $rules[ $page . ' > .wpcpm-institution__card + .wpcpm-institution__card' ] : array();

ck( 'the section has the hairline a module has over its title, in the theme\'s soft line', isset( $section['border-top'] ) ? $section['border-top'] : null, '1px solid var( --wpc-line-soft, #ededed )' );
ck( 'the 20px of top padding a module has', isset( $section['padding-top'] ) ? $section['padding-top'] : null, '20px' );
ck( 'and the 22px from the module above', isset( $section['margin-top'] ) ? $section['margin-top'] : null, '22px' );
ck( 'its cards have the margin 0 a module\'s cards have, above and below', array( isset( $cards['margin-top'] ) ? $cards['margin-top'] : null, isset( $cards['margin-bottom'] ) ? $cards['margin-bottom'] : null ), array( '0', '0' ) );
ck( 'and stand 10px apart', isset( $gap['margin-top'] ) ? $gap['margin-top'] : null, '10px' );
ck( 'the plain-plugin spacing is still there for a site without the theme', isset( $rules['.wpcpm-institution__admin']['margin'] ) ? $rules['.wpcpm-institution__admin']['margin'] : null, '1.25em 0 0' );

echo "\n=== House rules ===\n";

$source = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-history.php' );
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', $source ), 0 );
ck( 'no em or en dash in this suite', preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __FILE__ ) ), 0 );
ck( 'the class writes nothing: no option, no post, no meta, no hook', preg_match( '/update_option|add_option|wp_insert_post|update_post_meta|add_action|add_filter|register_rest_route|wp_send_json/', $source ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
