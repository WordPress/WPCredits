<?php
/**
 * An institution's details (Institutions release 2): the eleven things the program holds about a
 * school that the school may correct, how a typed answer is cleaned, what it changes, how a change
 * reads in a sentence, and the one way a change is written.
 *
 * What is pinned here and why:
 *
 * - **The table of fields is the plan's.** Eleven keys in the order the card draws them, six that
 *   save at once (the contact person, their role, who signs and their title, the students'
 *   languages and the page about WordPress Credits) and five an Administrator checks first (the name,
 *   website, city, country and the contact's email). The labels are the words the read view, the
 *   emails and the Administrator's table all use, so a change to one is a change to the table.
 *   Six keys are a column of the Institutions table, named by the key of
 *   `WPCPM_Institutions_Sync::fields()` and never by a column's spelling here; the other five live on
 *   the institution's record on the site.
 * - **The application's rules, through the application's cleaners.** An answer is refused with the
 *   application's own sentence, a country only if the Countries map offers it, a website or page
 *   address only if it names http or https, and a required answer is required here as it is there.
 *   A key of the other group is not read: a form for one group cannot change the other's.
 * - **A change is a difference.** An unchanged value, a value that differs only by the space around
 *   it, an email that differs only by its case and a list of languages in another order are none.
 * - **One write, in an order that fails safe.** The Airtable PATCH first, holding exactly the changed
 *   columns (the country as a list of one record ID); the index row second; the record third; one audit
 *   row, with the names of the fields and none of their values, last. Airtable refusing the PATCH,
 *   with an error or with nothing written, leaves the index, the record and the history as they were.
 *   A change of the site's own keys alone makes no Airtable call, and a change of the Airtable columns
 *   alone makes no record.
 * - **Refused before the first write, or reported as refused.** A new value is cleaned again by the
 *   application's rules, so a stored request replayed by an approval meets the scheme rule and the 200
 *   characters of a web address as a typed one does; a correction of an Airtable detail for an
 *   institution the index does not hold is refused before the PATCH; and a correction of the site's own
 *   answers alone, which the site could not keep, is an error and no row of history, where one that
 *   follows an Airtable change stands (it cannot be undone with the base).
 *
 * The other pieces are the real files: the Institutions sync (for its column names), the index, the
 * Countries map, the audit log, the institution's record, the application and the field cleaners.
 * Stood in for at their contracts: the settings, the Airtable client (journaled, so the PATCH and its
 * order among the other writes can be read) and enough of WordPress for a private post type with
 * meta and an option.
 *
 * Run from the plugin root:  php bin/test-institution-details.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']     = array();
$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['settings'] = array();
$GLOBALS['journal']  = array();
$GLOBALS['patched']  = array();
$GLOBALS['airtable'] = true;
$GLOBALS['next_id']  = 100;
$GLOBALS['record_fails'] = ''; // 'post' for a record post WordPress refuses to make, 'meta' for answers it refuses to keep.

class WP_Error {
	public $code = '';
	public $message = '';
	public $data = null;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_name = '', $post_author = 0, $post_content = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) $n; }
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function register_post_type( $t, $a = array() ) {}
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? 1788322400 : (int) $t ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) preg_match( '/^[^@\s]+@[^@\s.]+\.[^@\s]+$/', (string) $s ); }
function esc_url_raw( $u, $protocols = null ) { return preg_match( '#^https?://[^\s<>"]+$#i', (string) $u ) ? (string) $u : ''; }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }

require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/specialchars.php';

define( 'MINUTE_IN_SECONDS', 60 );

// What this request's object cache is told to drop, as `group:key`, so a read under a lock can be seen to
// be the table's.
$GLOBALS['dropped'] = array();
function wp_cache_delete( $key, $group = '' ) { $GLOBALS['dropped'][] = $group . ':' . $key; return true; }
// The extra sync run a patch asks for while a sync holds its lock; no sync runs in this suite.
function wp_next_scheduled( $hook, $args = array() ) { return false; }
function wp_schedule_single_event( $at, $hook, $args = array(), $wp_error = false ) { return true; }

// Every option write is journaled under its name, so the index's patch can be seen in order.
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['journal'][]  = 'option:' . $k;
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );

	if ( 'post' === $GLOBALS['record_fails'] && 'wpcpm_inst_record' === ( isset( $postarr['post_type'] ) ? $postarr['post_type'] : '' ) ) {
		return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' );
	}

	$id      = $GLOBALS['next_id']++;
	$post    = new WP_Post();

	$post->ID           = $id;
	$post->post_type    = isset( $postarr['post_type'] ) ? $postarr['post_type'] : 'post';
	$post->post_status  = isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'draft';
	$post->post_title   = isset( $postarr['post_title'] ) ? $postarr['post_title'] : '';
	$post->post_content = isset( $postarr['post_content'] ) ? $postarr['post_content'] : '';
	$post->post_author  = isset( $postarr['post_author'] ) ? (int) $postarr['post_author'] : 0;

	$GLOBALS['posts'][ $id ] = $post;

	if ( 'wpcpm_audit_entry' === $post->post_type ) {
		$GLOBALS['journal'][] = 'audit';
	}

	return $id;
}
function wp_update_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );

	if ( isset( $GLOBALS['posts'][ (int) $postarr['ID'] ], $postarr['post_status'] ) ) {
		$GLOBALS['posts'][ (int) $postarr['ID'] ]->post_status = $postarr['post_status'];
	}

	return (int) $postarr['ID'];
}
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function get_post_meta( $id, $key = '', $single = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) {
	if ( '_wpcpm_inst_answers' === $key ) {
		if ( 'meta' === $GLOBALS['record_fails'] ) {
			return false;
		}

		$GLOBALS['journal'][] = 'record';
	}

	$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );

	return true;
}
function get_posts( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['post_status'] ) && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) { continue; }
		if ( isset( $args['meta_key'] ) && (string) get_post_meta( $post->ID, $args['meta_key'], true ) !== (string) $args['meta_value'] ) { continue; }
		$out[] = $post;
	}

	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) { $out = array_slice( $out, 0, (int) $args['numberposts'] ); }

	return 'ids' === ( isset( $args['fields'] ) ? $args['fields'] : '' ) ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
}

/* ---- the other pieces, at their contracts --------------------------------- */

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}

class WPCPM_Settings {
	public static function get() { return $GLOBALS['settings']; }
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

/** The client, journaled: what it was asked to PATCH, and what it answers (the global `airtable`). */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
	public function update_records( $table, array $records ) {
		$GLOBALS['journal'][] = 'airtable';
		$GLOBALS['patched'][] = array( $table, $records );

		if ( true !== $GLOBALS['airtable'] ) { return $GLOBALS['airtable']; }

		return array( $records[0]['id'] => true );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
// The lock the index's patch holds, over the options table this suite keeps in `$GLOBALS['opts']`.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details.php';

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

const REC     = 'recINSTITUTION001';
const OTHER   = 'recINSTITUTION002';
const COSTA   = 'recCR000000000001';
const POLAND  = 'recPL000000000002';
const READ_AT = 1788000000;

/** The institution the suite is about: what Airtable and the index hold, and what the site's record holds. */
function reset_world() {
	$GLOBALS['opts']     = array();
	$GLOBALS['posts']    = array();
	$GLOBALS['pmeta']    = array();
	$GLOBALS['journal']  = array();
	$GLOBALS['patched']  = array();
	$GLOBALS['airtable'] = true;
	$GLOBALS['next_id']  = 100;
	$GLOBALS['record_fails'] = '';
	$GLOBALS['settings'] = array(
		'institutions_table'      => 'tblInstitutions1',
		'institutions_name_field' => 'Name',
	);

	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => READ_AT,
		'rows' => array(
			COSTA  => array( 'name' => 'Costa Rica', 'manager' => '', 'email' => '', 'calendly' => '' ),
			POLAND => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
		),
	);

	WPCPM_Institutions_Index::write(
		array(
			REC   => array(
				'record_id'      => REC,
				'name'           => 'Universidad Ejemplo ',
				'stage'          => 'Confirmed',
				'country'        => COSTA,
				'country_name'   => 'Costa Rica',
				'city'           => 'San Jose',
				'website'        => 'https://ejemplo.edu',
				'contact_person' => 'Ana Example',
				'contact_email'  => 'ana@example.test',
			),
			OTHER => array(
				'record_id'      => OTHER,
				'name'           => 'Another School',
				'stage'          => 'Student',
				'contact_person' => 'Other Person',
			),
		),
		READ_AT
	);

	// The journal starts empty: seeding the index is not what the checks are about.
	$GLOBALS['journal'] = array();

	WPCPM_Institution_Record::fill_empty(
		REC,
		array(
			'contact_role'      => 'Head of the international office',
			'signer_name'       => 'First Signer',
			'signer_title'      => 'Dean of Engineering',
			'student_languages' => array( 'Spanish', 'English' ),
			'credits_page'      => 'https://ejemplo.edu/credits',
		)
	);

	$GLOBALS['journal'] = array();
}

/** The audit rows written about an institution, oldest first. */
function audit_rows( $record = REC ) {
	$rows = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'wpcpm_audit_entry' === $post->post_type && $record === get_post_meta( $post->ID, '_wpcpm_log_institution', true ) ) {
			$rows[] = $post;
		}
	}

	return $rows;
}

/** Everything an audit row holds, as one string, for "no value is in it". */
function audit_text( WP_Post $post ) {
	return $post->post_title . "\n" . $post->post_content . "\n" . var_export( $GLOBALS['pmeta'][ $post->ID ], true );
}

function the_record_posts() {
	return array_values( array_filter( $GLOBALS['posts'], function ( $p ) { return 'wpcpm_inst_record' === $p->post_type; } ) );
}

echo "\n-- the table of fields ------------------------------------------------\n";

ck( 'two groups: what saves at once, and what an Administrator checks first', array( WPCPM_Institution_Details::GROUP_NOW, WPCPM_Institution_Details::GROUP_CHECKED ), array( 'now', 'checked' ) );

$fields = WPCPM_Institution_Details::fields();

ck(
	'eleven keys, in the order the card draws them',
	array_keys( $fields ),
	array( 'contact_person', 'contact_role', 'signer_name', 'signer_title', 'student_languages', 'credits_page', 'name', 'website', 'city', 'country', 'contact_email' )
);
ck(
	'each carries its group, label, source, column and type, and nothing else',
	array_unique( array_map( 'array_keys', array_values( $fields ) ), SORT_REGULAR ),
	array( array( 'group', 'label', 'source', 'column', 'type' ) )
);
ck(
	'six save at once and five are checked',
	array_map( function ( $spec ) { return $spec['group']; }, $fields ),
	array(
		'contact_person'    => 'now',
		'contact_role'      => 'now',
		'signer_name'       => 'now',
		'signer_title'      => 'now',
		'student_languages' => 'now',
		'credits_page'      => 'now',
		'name'              => 'checked',
		'website'           => 'checked',
		'city'              => 'checked',
		'country'           => 'checked',
		'contact_email'     => 'checked',
	)
);
ck(
	'the labels are the ones the read view, the emails and the Administrator\'s table use',
	array_values( array_map( function ( $spec ) { return $spec['label']; }, $fields ) ),
	array( 'Contact person', 'Their role', 'Signs the agreement', 'Their title', 'Students study in', 'Page about WordPress Credits', 'Name', 'Website', 'City', 'Country', 'Contact\'s email' )
);
ck(
	'the contact person and the five checked details are Airtable\'s, the other five the site\'s record',
	array_map( function ( $spec ) { return $spec['source']; }, $fields ),
	array(
		'contact_person'    => 'airtable',
		'contact_role'      => 'record',
		'signer_name'       => 'record',
		'signer_title'      => 'record',
		'student_languages' => 'record',
		'credits_page'      => 'record',
		'name'              => 'airtable',
		'website'           => 'airtable',
		'city'              => 'airtable',
		'country'           => 'airtable',
		'contact_email'     => 'airtable',
	)
);
ck(
	'an Airtable detail names its column by the key of the sync\'s map, and a record detail names none',
	array_map( function ( $spec ) { return $spec['column']; }, $fields ),
	array(
		'contact_person'    => 'contact_person',
		'contact_role'      => '',
		'signer_name'       => '',
		'signer_title'      => '',
		'student_languages' => '',
		'credits_page'      => '',
		'name'              => 'name',
		'website'           => 'website',
		'city'              => 'city',
		'country'           => 'country',
		'contact_email'     => 'contact_email',
	)
);
ck(
	'every such key is one the sync\'s map has',
	array_values( array_diff( array_filter( array_column( $fields, 'column' ) ), array_keys( WPCPM_Institutions_Sync::fields() ) ) ),
	array()
);
ck(
	'the types are the application\'s own for the same question',
	array_map( function ( $spec ) { return $spec['type']; }, $fields ),
	array(
		'contact_person'    => 'text',
		'contact_role'      => 'text',
		'signer_name'       => 'text',
		'signer_title'      => 'text',
		'student_languages' => 'choices',
		'credits_page'      => 'url',
		'name'              => 'text',
		'website'           => 'url',
		'city'              => 'text',
		'country'           => 'country',
		'contact_email'     => 'email',
	)
);

// The card draws each form field from the application's question for the same answer: its label, its
// help, its limit, its choices. The table says which question a detail is, and `fields()` keeps its five
// keys (pinned above), so the question has a reader of its own.
ck(
	'each detail names the application question it is asked as, which gives the card its label, help and rules',
	array_map( function ( $key ) { return WPCPM_Institution_Details::question( $key ); }, array_keys( $fields ) ),
	array( 'Contact Person', 'contact_role', 'signer_name', 'signer_title', 'student_languages', 'credits_page', 'Name', 'Website', 'City', 'Country', 'Contact Email' )
);
ck(
	'every such question is one the application asks',
	array_values( array_diff( array_map( function ( $key ) { return WPCPM_Institution_Details::question( $key ); }, array_keys( $fields ) ), array_keys( WPCPM_Institution_Application::fields() ) ) ),
	array()
);
ck( 'and a key that is no detail has none', WPCPM_Institution_Details::question( 'stage' ), '' );

echo "\n-- what is on record now ----------------------------------------------\n";

reset_world();

ck(
	'current() reads the contact person and the five checked details from the index row and the rest from the record, trimmed, in the table\'s order',
	WPCPM_Institution_Details::current( REC ),
	array(
		'contact_person'    => 'Ana Example',
		'contact_role'      => 'Head of the international office',
		'signer_name'       => 'First Signer',
		'signer_title'      => 'Dean of Engineering',
		'student_languages' => array( 'Spanish', 'English' ),
		'credits_page'      => 'https://ejemplo.edu/credits',
		'name'              => 'Universidad Ejemplo',
		'website'           => 'https://ejemplo.edu',
		'city'              => 'San Jose',
		'country'           => COSTA,
		'contact_email'     => 'ana@example.test',
	)
);
ck(
	'an institution with an index row and no record has the record\'s five empty',
	array_slice( WPCPM_Institution_Details::current( OTHER ), 1, 5 ),
	array( 'contact_role' => '', 'signer_name' => '', 'signer_title' => '', 'student_languages' => array(), 'credits_page' => '' )
);
ck( 'and its own index values', array( WPCPM_Institution_Details::current( OTHER )['name'], WPCPM_Institution_Details::current( OTHER )['contact_person'], WPCPM_Institution_Details::current( OTHER )['country'] ), array( 'Another School', 'Other Person', '' ) );
ck(
	'an institution the site knows nothing about has all eleven empty',
	array_values( array_unique( array_map( function ( $v ) { return array() === $v ? 'list' : $v; }, WPCPM_Institution_Details::current( 'recNOSUCHONE00001' ) ) ) ),
	array( '', 'list' )
);
ck( 'in all eleven keys', array_keys( WPCPM_Institution_Details::current( 'recNOSUCHONE00001' ) ), array_keys( $fields ) );
ck( 'and so does an ID that is not a record ID', count( WPCPM_Institution_Details::current( 'nonsense' ) ), 11 );

echo "\n-- cleaning: the application's rules, through its cleaners ---------------\n";

$good_now = array(
	'contact_person'    => '  Ana Example  ',
	'contact_role'      => 'Registrar',
	'signer_name'       => 'New Signer',
	'signer_title'      => 'Rector',
	'student_languages' => array( 'English', 'Klingon', 'Spanish', 'English' ),
	'credits_page'      => 'ejemplo.edu/credits',
);
$cleaned  = WPCPM_Institution_Details::clean( 'now', $good_now );

ck(
	'the first group is cleaned as the application cleans each answer: trimmed, a language not on the list dropped, a scheme given to an address',
	$cleaned,
	array(
		'values'   => array(
			'contact_person'    => 'Ana Example',
			'contact_role'      => 'Registrar',
			'signer_name'       => 'New Signer',
			'signer_title'      => 'Rector',
			'student_languages' => array( 'English', 'Spanish' ),
			'credits_page'      => 'https://ejemplo.edu/credits',
		),
		'problems' => array(),
	)
);

$cleaned = WPCPM_Institution_Details::clean(
	'checked',
	array(
		'name'          => ' Universidad Ejemplo del Sur ',
		'website'       => 'uev.edu',
		'city'          => 'Heredia',
		'country'       => POLAND,
		'contact_email' => 'ana@example.test',
	)
);

ck(
	'the second group likewise, the country a record ID the Countries map offers',
	$cleaned,
	array(
		'values'   => array(
			'name'          => 'Universidad Ejemplo del Sur',
			'website'       => 'https://uev.edu',
			'city'          => 'Heredia',
			'country'       => POLAND,
			'contact_email' => 'ana@example.test',
		),
		'problems' => array(),
	)
);

$refused = WPCPM_Institution_Details::clean( 'checked', array( 'name' => 'Universidad', 'website' => 'uev.edu', 'city' => 'Heredia', 'country' => 'recZZ000000000009', 'contact_email' => 'ana@example.test' ) );

ck( 'a country the map does not offer is refused with the application\'s sentence', $refused['problems'], array( 'country' => 'Please choose one of the options offered.' ) );
ck( 'and is not among the values, which keep the rest', array_keys( $refused['values'] ), array( 'name', 'website', 'city', 'contact_email' ) );

$refused = WPCPM_Institution_Details::clean( 'checked', array( 'name' => 'Universidad', 'website' => 'uev.edu', 'city' => 'Heredia', 'country' => COSTA, 'contact_email' => 'ana at example dot test' ) );

ck( 'an email that is not one is refused with the application\'s sentence', $refused['problems'], array( 'contact_email' => 'That does not look like an email address.' ) );

$refused = WPCPM_Institution_Details::clean( 'checked', array( 'name' => '  ', 'website' => '', 'city' => '', 'country' => '', 'contact_email' => '' ) );

ck(
	'a required answer left empty is asked for, as the application asks',
	$refused['problems'],
	array(
		'name'          => 'Please fill this in.',
		'website'       => 'Please fill this in.',
		'city'          => 'Please fill this in.',
		'country'       => 'Please fill this in.',
		'contact_email' => 'Please fill this in.',
	)
);
ck( 'and keeps no value for them', $refused['values'], array() );
ck(
	'a whole group left out is the same as every answer left empty',
	WPCPM_Institution_Details::clean( 'checked', array() )['problems'],
	$refused['problems']
);

$refused = WPCPM_Institution_Details::clean( 'now', array( 'contact_person' => 'Ana', 'contact_role' => '', 'student_languages' => array( 'Klingon' ) ) );

ck(
	'the role and the languages are required in the first group, the languages when none the list offers is ticked',
	$refused['problems'],
	array( 'contact_role' => 'Please fill this in.', 'student_languages' => 'Please fill this in.' )
);

$base = array( 'contact_person' => 'Ana', 'contact_role' => 'Registrar', 'student_languages' => array( 'English' ) );

ck( 'who signs, their title and the page are not required when all are left empty', WPCPM_Institution_Details::clean( 'now', $base )['problems'], array() );
ck(
	'a title with nobody named to sign asks for the name',
	WPCPM_Institution_Details::clean( 'now', $base + array( 'signer_title' => 'Dean' ) )['problems'],
	array( 'signer_name' => 'Please fill this in.' )
);
ck(
	'and a name with no title asks for the title',
	WPCPM_Institution_Details::clean( 'now', $base + array( 'signer_name' => 'A Signer' ) )['problems'],
	array( 'signer_title' => 'Please fill this in.' )
);

$refused = WPCPM_Institution_Details::clean( 'checked', array( 'name' => 'Universidad', 'website' => 'javascript:alert(1)', 'city' => 'Heredia', 'country' => COSTA, 'contact_email' => 'ana@example.test' ) );

ck( 'a web address naming a scheme of its own is refused', array_keys( $refused['problems'] ), array( 'website' ) );
ck( 'and says the answer could not be read', $refused['problems']['website'], 'That answer could not be read. Please try it again.' );

$long = 'https://ejemplo.edu/' . str_repeat( 'a', 200 );

ck( 'a website over 200 characters is refused, with the length it may have', WPCPM_Institution_Details::clean( 'checked', array( 'name' => 'U', 'website' => $long, 'city' => 'H', 'country' => COSTA, 'contact_email' => 'ana@example.test' ) )['problems'], array( 'website' => 'Please keep this to 200 characters or fewer.' ) );
ck( 'and so is the page about WordPress Credits', WPCPM_Institution_Details::clean( 'now', $base + array( 'credits_page' => $long ) )['problems'], array( 'credits_page' => 'Please keep this to 200 characters or fewer.' ) );

$only_checked = WPCPM_Institution_Details::clean( 'now', array( 'name' => 'Not mine to change', 'website' => 'x.edu', 'country' => POLAND ) + $base );

ck( 'an answer of the other group is not read: a form for one cannot change the other', array_keys( $only_checked['values'] ), array( 'contact_person', 'contact_role', 'signer_name', 'signer_title', 'student_languages', 'credits_page' ) );
ck( 'and an answer of the group left out is an empty one, as the form that posts every field means it', array( $only_checked['values']['signer_name'], $only_checked['values']['signer_title'], $only_checked['values']['credits_page'] ), array( '', '', '' ) );
ck( 'and a group nobody has gives nothing', WPCPM_Institution_Details::clean( 'nonsense', $good_now ), array( 'values' => array(), 'problems' => array() ) );

echo "\n-- changes: only what differs ------------------------------------------\n";

reset_world();

$now = WPCPM_Institution_Details::current( REC );

ck( 'nothing differs when every value is what is on record', WPCPM_Institution_Details::changes( REC, $now ), array() );
ck(
	'a value that differs only by the space around it is no change',
	WPCPM_Institution_Details::changes( REC, array( 'name' => '  Universidad Ejemplo  ', 'city' => "San Jose\n", 'contact_role' => ' Head of the international office ' ) ),
	array()
);
ck( 'an email that differs only by its case is none either, the index holding it lowercased', WPCPM_Institution_Details::changes( REC, array( 'contact_email' => 'Ana@Example.test' ) ), array() );
ck( 'nor the same languages in another order', WPCPM_Institution_Details::changes( REC, array( 'student_languages' => array( 'English', 'Spanish' ) ) ), array() );
ck(
	'a changed value is its old and its new',
	WPCPM_Institution_Details::changes( REC, array( 'website' => 'https://uev.edu' ) ),
	array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'https://uev.edu' ) )
);
ck(
	'and comes in the table\'s order, whatever the order given, with the new value trimmed',
	WPCPM_Institution_Details::changes( REC, array( 'contact_email' => 'new@example.test', 'country' => POLAND, 'signer_name' => ' New Signer ', 'contact_person' => 'Ana Example' ) ),
	array(
		'signer_name'   => array( 'from' => 'First Signer', 'to' => 'New Signer' ),
		'country'       => array( 'from' => COSTA, 'to' => POLAND ),
		'contact_email' => array( 'from' => 'ana@example.test', 'to' => 'new@example.test' ),
	)
);
ck(
	'a list that gained a language is a change, in both lists as they are',
	WPCPM_Institution_Details::changes( REC, array( 'student_languages' => array( 'English', 'Spanish', 'French' ) ) ),
	array( 'student_languages' => array( 'from' => array( 'Spanish', 'English' ), 'to' => array( 'English', 'Spanish', 'French' ) ) )
);
ck(
	'emptying a value is a change to nothing',
	WPCPM_Institution_Details::changes( REC, array( 'signer_title' => '' ) ),
	array( 'signer_title' => array( 'from' => 'Dean of Engineering', 'to' => '' ) )
);
ck( 'a key the table does not have is not a change', WPCPM_Institution_Details::changes( REC, array( 'stage' => 'Student', 'record_id' => OTHER ) ), array() );
ck(
	'a value for an institution with nothing on record is a change from nothing',
	WPCPM_Institution_Details::changes( OTHER, array( 'contact_role' => 'Registrar' ) ),
	array( 'contact_role' => array( 'from' => '', 'to' => 'Registrar' ) )
);

echo "\n-- lines: a change in a sentence ----------------------------------------\n";

$website = array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'https://uev.edu' ) );

ck( 'a saved change reads label, from, now, to', WPCPM_Institution_Details::lines( $website ), 'Website: https://ejemplo.edu, now https://uev.edu' );
ck( 'and an asked one reads asked to be', WPCPM_Institution_Details::lines( $website, true ), 'Website: https://ejemplo.edu, asked to be https://uev.edu' );
ck( 'the form is the saved one unless asked otherwise', WPCPM_Institution_Details::lines( $website, false ), WPCPM_Institution_Details::lines( $website ) );
ck(
	'an empty value reads not given, on either side',
	WPCPM_Institution_Details::lines( array( 'signer_name' => array( 'from' => '', 'to' => 'New Signer' ), 'signer_title' => array( 'from' => 'Dean', 'to' => '' ) ) ),
	"Signs the agreement: not given, now New Signer\r\nTheir title: Dean, now not given"
);
ck(
	'a country reads by name, a list by its names, and one the map no longer holds by what was given',
	WPCPM_Institution_Details::lines(
		array(
			'student_languages' => array( 'from' => array( 'Spanish' ), 'to' => array( 'English', 'Spanish' ) ),
			'country'           => array( 'from' => COSTA, 'to' => POLAND ),
		)
	),
	"Students study in: Spanish, now English, Spanish\r\nCountry: Costa Rica, now Poland"
);
ck( 'a country the map does not hold reads as given', WPCPM_Institution_Details::lines( array( 'country' => array( 'from' => 'recZZ000000000009', 'to' => POLAND ) ) ), 'Country: recZZ000000000009, now Poland' );
ck(
	'one line to a change, in the table\'s order, and a key the table does not have gets none',
	WPCPM_Institution_Details::lines(
		array(
			'city'     => array( 'from' => 'San Jose', 'to' => 'Heredia' ),
			'stage'    => array( 'from' => 'A', 'to' => 'B' ),
			'name'     => array( 'from' => 'Universidad Ejemplo', 'to' => 'Universidad Ejemplo del Sur' ),
			'contact_person' => array( 'from' => 'Ana Example', 'to' => 'Eva Example' ),
		),
		true
	),
	"Contact person: Ana Example, asked to be Eva Example\r\nName: Universidad Ejemplo, asked to be Universidad Ejemplo del Sur\r\nCity: San Jose, asked to be Heredia"
);
ck( 'no change is no line', WPCPM_Institution_Details::lines( array() ), '' );

echo "\n-- show(): one value as a table says it ---------------------------------\n";

// The Administrator's table prints what the program has and what was asked for one detail at a time,
// in the words the emails use for a value: a country by its name, a list by its values, nothing as
// "not given".
ck(
	'a text reads as it is, a country by its name, a list by its values',
	array(
		WPCPM_Institution_Details::show( 'website', 'https://uev.edu' ),
		WPCPM_Institution_Details::show( 'country', POLAND ),
		WPCPM_Institution_Details::show( 'student_languages', array( 'English', 'Spanish' ) ),
	),
	array( 'https://uev.edu', 'Poland', 'English, Spanish' )
);
ck(
	'nothing, space and an empty list read as not given, and a country the map no longer holds as given',
	array(
		WPCPM_Institution_Details::show( 'city', '' ),
		WPCPM_Institution_Details::show( 'name', '   ' ),
		WPCPM_Institution_Details::show( 'student_languages', array() ),
		WPCPM_Institution_Details::show( 'country', 'recZZ000000000009' ),
	),
	array( 'not given', 'not given', 'not given', 'recZZ000000000009' )
);
ck( 'a key the table does not have reads as nothing at all', WPCPM_Institution_Details::show( 'stage', 'Confirmed' ), '' );

// A country row added in Airtable after the last read of the Countries table, or a map that is empty: the
// index still keeps the name beside the ID, as the sync wrote it, so the card, the Administrator's table and
// the emails read the name and not the record ID.
reset_world();
unset( $GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]['rows'][ COSTA ] );
ck(
	'a country the map does not hold reads by the name an index row keeps beside it',
	array(
		WPCPM_Countries::name_of( COSTA ),
		WPCPM_Institution_Details::show( 'country', COSTA ),
		WPCPM_Institution_Details::lines( array( 'country' => array( 'from' => COSTA, 'to' => POLAND ) ) ),
		WPCPM_Institution_Details::lines( array( 'country' => array( 'from' => POLAND, 'to' => COSTA ) ), true ),
	),
	array( '', 'Costa Rica', 'Country: Costa Rica, now Poland', 'Country: Poland, asked to be Costa Rica' )
);
unset( $GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] );
ck( 'with no map at all too, and a country neither holds still reads as its record ID', array( WPCPM_Institution_Details::show( 'country', COSTA ), WPCPM_Institution_Details::show( 'country', 'recZZ000000000009' ), WPCPM_Institution_Details::show( 'country', POLAND ) ), array( 'Costa Rica', 'recZZ000000000009', POLAND ) );
reset_world();

echo "\n-- forget_cached(): a read under a lock is the tables' --------------------\n";

// The details card reads what is on record again under the school's lock before it saves, so the copies this
// request read before the lock are dropped first: the index's option and the institution's answers.
reset_world();
$record_post = 0;
foreach ( $GLOBALS['posts'] as $post ) {
	if ( 'wpcpm_inst_record' === $post->post_type ) { $record_post = $post->ID; }
}
$GLOBALS['dropped'] = array();
if ( method_exists( 'WPCPM_Institution_Details', 'forget_cached' ) ) { WPCPM_Institution_Details::forget_cached( REC ); }
$dropped_rec        = $GLOBALS['dropped'];
$GLOBALS['dropped'] = array();
if ( method_exists( 'WPCPM_Institution_Details', 'forget_cached' ) ) { WPCPM_Institution_Details::forget_cached( OTHER ); }
ck( 'the index\'s copy and the institution\'s answers are dropped, and an institution with no record has only the index to drop', array( $record_post > 0, $dropped_rec, $GLOBALS['dropped'] ), array( true, array( 'options:wpcpm_institutions_index', 'post_meta:' . $record_post ), array( 'options:wpcpm_institutions_index' ) ) );
reset_world();

echo "\n-- write(): the one way a change is made --------------------------------\n";

reset_world();

$changes = WPCPM_Institution_Details::changes(
	REC,
	array(
		'contact_person' => 'Eva Example',
		'contact_role'   => 'Registrar',
		'website'        => 'https://uev.edu',
		'country'        => POLAND,
		'contact_email'  => 'Eva@Example.test',
	)
);

$before = WPCPM_Institutions_Index::rows();

ck( 'a change is written and says true', WPCPM_Institution_Details::write( REC, $changes, 7, 'member' ), true );

ck(
	'one PATCH went to the Institutions table, for the record, holding exactly the changed Airtable columns and the country as a list of one record ID',
	$GLOBALS['patched'],
	array(
		array(
			'tblInstitutions1',
			array(
				array(
					'id'     => REC,
					'fields' => array(
						'Contact Person' => 'Eva Example',
						'Website'        => 'https://uev.edu',
						'Country'        => array( POLAND ),
						'Contact Email'  => 'Eva@Example.test',
					),
				),
			),
		),
	)
);
ck(
	'then the index, then the record, then one row of history, in that order',
	$GLOBALS['journal'],
	array( 'airtable', 'option:wpcpm_institutions_index', 'record', 'audit' )
);

$row = WPCPM_Institutions_Index::row( REC );

ck(
	'the index row follows: the new values, the country by name, the email as the sync keeps it',
	array( $row['contact_person'], $row['website'], $row['country'], $row['country_name'], $row['contact_email'] ),
	array( 'Eva Example', 'https://uev.edu', POLAND, 'Poland', 'eva@example.test' )
);
ck(
	'and every other key of the row, and every other row, and the read time, are as they were',
	array( array_diff_key( $row, array_flip( array( 'contact_person', 'website', 'country', 'country_name', 'contact_email' ) ) ), WPCPM_Institutions_Index::rows()[ OTHER ], WPCPM_Institutions_Index::read()['read'] ),
	array( array_diff_key( $before[ REC ], array_flip( array( 'contact_person', 'website', 'country', 'country_name', 'contact_email' ) ) ), $before[ OTHER ], READ_AT )
);
ck(
	'the record holds the new role and the answers it had besides',
	WPCPM_Institution_Record::get( REC ),
	array(
		'contact_role'      => 'Registrar',
		'signer_name'       => 'First Signer',
		'signer_title'      => 'Dean of Engineering',
		'student_languages' => array( 'Spanish', 'English' ),
		'credits_page'      => 'https://ejemplo.edu/credits',
	)
);

$audit = audit_rows();

ck( 'one row of history was written', count( $audit ), 1 );

$entry = $audit[0];

ck(
	'of the kind details_saved, about the institution, by the actor, on the ground given, against the index',
	array(
		get_post_meta( $entry->ID, '_wpcpm_log_kind', true ),
		get_post_meta( $entry->ID, '_wpcpm_log_institution', true ),
		(int) get_post_meta( $entry->ID, '_wpcpm_log_actor', true ),
		get_post_meta( $entry->ID, '_wpcpm_log_ground', true ),
		get_post_meta( $entry->ID, '_wpcpm_log_evidence', true ),
	),
	array( 'details_saved', REC, 7, 'member', 'index' )
);
ck(
	'it names the fields that changed and nothing else',
	get_post_meta( $entry->ID, '_wpcpm_log_data', true ),
	array( 'fields' => array( 'contact_person', 'contact_role', 'website', 'country', 'contact_email' ) )
);
ck( 'the message names them by their labels', $entry->post_content, 'Details were corrected: Contact person, Their role, Website, Country, Contact\'s email.' );

$said = audit_text( $entry );

ck(
	'and no value, old or new, is in the row',
	array_filter( array( 'Eva', 'Ana', 'Registrar', 'ejemplo', 'uev.edu', 'example.test', 'Head of the', POLAND, COSTA ), function ( $value ) use ( $said ) { return false !== stripos( $said, $value ); } ),
	array()
);

echo "\n-- write(): the name column is the one the sync reads ---------------------\n";

reset_world();
$GLOBALS['settings']['institutions_name_field'] = 'Institution Name';

WPCPM_Institution_Details::write( REC, WPCPM_Institution_Details::changes( REC, array( 'name' => 'Universidad Ejemplo del Sur', 'city' => 'Heredia' ) ), 1, 'manager' );

ck( 'a setting that names the name column is written under, as the sync reads under', $GLOBALS['patched'][0][1][0]['fields'], array( 'Institution Name' => 'Universidad Ejemplo del Sur', 'City' => 'Heredia' ) );
ck( 'and the index row holds the name', WPCPM_Institutions_Index::row( REC )['name'], 'Universidad Ejemplo del Sur' );
ck( 'the ground an Administrator acts on is kept on the row', get_post_meta( audit_rows()[0]->ID, '_wpcpm_log_ground', true ), 'manager' );

reset_world();
unset( $GLOBALS['settings']['institutions_name_field'] );

WPCPM_Institution_Details::write( REC, WPCPM_Institution_Details::changes( REC, array( 'name' => 'Universidad Nueva' ) ), 1, 'manager' );

ck( 'with the setting empty the column is the sync\'s own', $GLOBALS['patched'][0][1][0]['fields'], array( 'Name' => 'Universidad Nueva' ) );

echo "\n-- write(): Airtable refuses, and nothing is written on the site ----------\n";

foreach (
	array(
		'an error'            => new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): invalid value' ),
		'an empty answer'     => array(),
	) as $why => $answer
) {
	reset_world();

	$GLOBALS['airtable'] = $answer;

	$index_before  = $GLOBALS['opts']['wpcpm_institutions_index'];
	$posts_before  = $GLOBALS['posts'];
	$pmeta_before  = $GLOBALS['pmeta'];
	$changes       = WPCPM_Institution_Details::changes( REC, array( 'contact_person' => 'Eva Example', 'contact_role' => 'Registrar', 'city' => 'Heredia' ) );
	$result        = WPCPM_Institution_Details::write( REC, $changes, 7, 'member' );

	ck( sprintf( 'with %s from Airtable the write is refused', $why ), is_wp_error( $result ), true );
	ck( sprintf( 'with %s the one PATCH was tried and no other write was made', $why ), $GLOBALS['journal'], array( 'airtable' ) );
	ck( sprintf( 'with %s the index is as it was', $why ), $GLOBALS['opts']['wpcpm_institutions_index'], $index_before );
	ck( sprintf( 'with %s the record is as it was, and no post was made or changed', $why ), array( $GLOBALS['posts'] == $posts_before, $GLOBALS['pmeta'] == $pmeta_before ), array( true, true ) );
	ck( sprintf( 'with %s no row of history was written', $why ), audit_rows(), array() );
}

reset_world();

$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): invalid value' );

$result = WPCPM_Institution_Details::write( REC, WPCPM_Institution_Details::changes( REC, array( 'city' => 'Heredia' ) ), 7, 'member' );

ck( 'the refusal is Airtable\'s own, so the sentence can say what it said', array( $result->get_error_code(), $result->get_error_message() ), array( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): invalid value' ) );

$GLOBALS['airtable'] = array();
$result              = WPCPM_Institution_Details::write( REC, WPCPM_Institution_Details::changes( REC, array( 'city' => 'Heredia' ) ), 7, 'member' );

ck( 'an empty answer is a refusal of the plugin\'s own, since nothing was updated', $result instanceof WP_Error ? $result->get_error_code() : $result, 'wpcpm_details_airtable' );

echo "\n-- write(): the site's own keys alone, and Airtable's alone ----------------\n";

reset_world();

$result = WPCPM_Institution_Details::write( REC, WPCPM_Institution_Details::changes( REC, array( 'contact_role' => 'Registrar', 'student_languages' => array( 'French' ) ) ), 3, 'member' );

ck( 'a change of the record\'s keys alone is written', $result, true );
ck( 'with no Airtable call and no write to the index, the record and the history in order', array( $GLOBALS['patched'], $GLOBALS['journal'] ), array( array(), array( 'record', 'audit' ) ) );
ck( 'the record holds them', array( WPCPM_Institution_Record::get( REC )['contact_role'], WPCPM_Institution_Record::get( REC )['student_languages'] ), array( 'Registrar', array( 'French' ) ) );
ck( 'and the history names them', get_post_meta( audit_rows()[0]->ID, '_wpcpm_log_data', true ), array( 'fields' => array( 'contact_role', 'student_languages' ) ) );

reset_world();

$result = WPCPM_Institution_Details::write( OTHER, WPCPM_Institution_Details::changes( OTHER, array( 'city' => 'Heredia' ) ), 3, 'member' );

ck( 'a change of Airtable\'s columns alone is written', $result, true );
ck( 'with the PATCH, the index and the history in order', $GLOBALS['journal'], array( 'airtable', 'option:wpcpm_institutions_index', 'audit' ) );
ck( 'and no record made for the institution, which has none: the one record is the other\'s', array_map( function ( $post ) { return $post->post_title; }, the_record_posts() ), array( REC ) );
ck( 'its index row holds the city, and the other\'s is as it was', array( WPCPM_Institutions_Index::row( OTHER )['city'], WPCPM_Institutions_Index::row( REC )['city'] ), array( 'Heredia', 'San Jose' ) );

echo "\n-- write(): what is refused before anything is written -----------------------\n";

foreach (
	array(
		'a record ID that is not one'               => array( 'nonsense', array( 'city' => array( 'from' => 'a', 'to' => 'b' ) ), 7, 'member' ),
		'a key the table does not have'             => array( REC, array( 'stage' => array( 'from' => 'Student', 'to' => 'Confirmed' ) ), 7, 'member' ),
		'a change that has no new value'            => array( REC, array( 'city' => array( 'from' => 'a' ) ), 7, 'member' ),
		'a country the Countries map does not offer' => array( REC, array( 'country' => array( 'from' => COSTA, 'to' => 'recZZ000000000009' ) ), 7, 'member' ),
		'a web address naming a scheme of its own'   => array( REC, array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'javascript:alert(1)' ) ), 7, 'member' ),
		'a web address over 200 characters'         => array( REC, array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'https://ejemplo.edu/' . str_repeat( 'a', 200 ) ) ), 7, 'member' ),
		'a page address over 200 characters'        => array( REC, array( 'credits_page' => array( 'from' => '', 'to' => 'https://ejemplo.edu/' . str_repeat( 'a', 200 ) ) ), 7, 'member' ),
		'an email that is not one'                  => array( REC, array( 'contact_email' => array( 'from' => 'ana@example.test', 'to' => 'ana at example dot test' ) ), 7, 'member' ),
		'a ground the history does not know'        => array( REC, array( 'city' => array( 'from' => 'San Jose', 'to' => 'Heredia' ) ), 7, 'neighbor' ),
		'a value that is not text'                  => array( REC, array( 'city' => array( 'from' => 'San Jose', 'to' => array( 'Heredia' ) ) ), 7, 'member' ),
	) as $why => $arguments
) {
	reset_world();

	$posts_before = $GLOBALS['posts'];
	$index_before = $GLOBALS['opts']['wpcpm_institutions_index'];
	$result       = call_user_func_array( array( 'WPCPM_Institution_Details', 'write' ), $arguments );

	ck( sprintf( 'for %s the write is refused', $why ), is_wp_error( $result ), true );
	ck( 'and nothing was sent or written', array( $GLOBALS['journal'], $GLOBALS['patched'], $GLOBALS['posts'] == $posts_before, $GLOBALS['opts']['wpcpm_institutions_index'] === $index_before ), array( array(), array(), true, true ) );
}

reset_world();

$result = WPCPM_Institution_Details::write( REC, array(), 7, 'member' );

ck( 'no change at all is true, and nothing at all is done', array( $result, $GLOBALS['journal'], $GLOBALS['patched'], audit_rows() ), array( true, array(), array(), array() ) );

reset_world();
WPCPM_Institution_Details::write( REC, array( 'country' => array( 'from' => COSTA, 'to' => '' ) ), 7, 'member' );

ck( 'a country emptied clears the link with an empty list', $GLOBALS['patched'][0][1][0]['fields'], array( 'Country' => array() ) );
ck( 'and the index row holds no country', array( WPCPM_Institutions_Index::row( REC )['country'], WPCPM_Institutions_Index::row( REC )['country_name'] ), array( '', '' ) );

echo "\n-- write(): a stored change is cleaned again, as a typed one is ---------------\n";

foreach (
	array(
		'a web address naming a scheme of its own' => array( array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'javascript:alert(1)' ) ), 'Website: That answer could not be read. Please try it again. Nothing was changed.' ),
		'a web address over 200 characters'        => array( array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'https://ejemplo.edu/' . str_repeat( 'a', 200 ) ) ), 'Website: Please keep this to 200 characters or fewer. Nothing was changed.' ),
		'a page address over 200 characters'       => array( array( 'credits_page' => array( 'from' => '', 'to' => 'https://ejemplo.edu/' . str_repeat( 'a', 200 ) ) ), 'Page about WordPress Credits: Please keep this to 200 characters or fewer. Nothing was changed.' ),
		'an email that is not one'                 => array( array( 'contact_email' => array( 'from' => 'ana@example.test', 'to' => 'ana at example dot test' ) ), 'Contact\'s email: That does not look like an email address. Nothing was changed.' ),
		'a country the map does not offer'         => array( array( 'country' => array( 'from' => COSTA, 'to' => 'recZZ000000000009' ) ), 'Country: Please choose one of the options offered. Nothing was changed.' ),
	) as $why => $case
) {
	reset_world();

	$result = WPCPM_Institution_Details::write( REC, $case[0], 7, 'manager' );

	ck( sprintf( 'a stored change holding %s is refused with the form\'s sentence', $why ), array( is_wp_error( $result ) ? $result->get_error_code() : $result, is_wp_error( $result ) ? $result->get_error_message() : '' ), array( 'wpcpm_details_value', $case[1] ) );
	ck( sprintf( 'and nothing for %s was sent or written', $why ), array( $GLOBALS['journal'], $GLOBALS['patched'], audit_rows() ), array( array(), array(), array() ) );
}

reset_world();

ck( 'a change that is clean already is written as it is', WPCPM_Institution_Details::write( REC, array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'https://uev.edu' ) ), 7, 'member' ), true );
ck( 'and one that is not quite, the scheme missing and the space around it, is written as the form would have it', true === WPCPM_Institution_Details::write( REC, array( 'website' => array( 'from' => 'https://uev.edu', 'to' => '  ejemplo.edu/sur ' ), 'student_languages' => array( 'from' => array(), 'to' => array( 'English', 'Klingon', 'English' ) ) ), 7, 'member' ) && 'https://ejemplo.edu/sur' === $GLOBALS['patched'][1][1][0]['fields']['Website'] && array( 'English' ) === WPCPM_Institution_Record::get( REC )['student_languages'], true );

echo "\n-- write(): an institution the index does not hold ----------------------------\n";

reset_world();

$unknown = 'recNOSUCHONE00001';
$result  = WPCPM_Institution_Details::write( $unknown, array( 'city' => array( 'from' => '', 'to' => 'Heredia' ), 'contact_role' => array( 'from' => '', 'to' => 'Registrar' ) ), 7, 'manager' );

ck( 'a correction of an Airtable detail is refused before the PATCH', array( is_wp_error( $result ) ? $result->get_error_code() : $result, is_wp_error( $result ) ? $result->get_error_message() : '' ), array( 'wpcpm_details_index', 'This institution is not in the program records yet, so nothing was saved.' ) );
ck( 'and nothing was called: no PATCH, no index write, no record, no history', array( $GLOBALS['journal'], $GLOBALS['patched'], WPCPM_Institution_Record::get( $unknown ), audit_rows( $unknown ), WPCPM_Institutions_Index::has( $unknown ) ), array( array(), array(), array(), array(), false ) );

$result = WPCPM_Institution_Details::write( $unknown, array( 'contact_role' => array( 'from' => '', 'to' => 'Registrar' ) ), 7, 'manager' );

ck( 'a correction of the site\'s own answers alone needs no row, and is kept', array( $result, WPCPM_Institution_Record::get( $unknown ), $GLOBALS['patched'] ), array( true, array( 'contact_role' => 'Registrar' ), array() ) );

echo "\n-- write(): the site could not keep the record's answers -----------------------\n";

foreach ( array( 'post' => 'WordPress would not make the record\'s post', 'meta' => 'WordPress refused the answers' ) as $fails => $why ) {
	reset_world();

	$GLOBALS['record_fails'] = $fails;

	$result = WPCPM_Institution_Details::write( OTHER, array( 'contact_role' => array( 'from' => '', 'to' => 'Registrar' ) ), 7, 'member' );

	ck( sprintf( 'when %s, a correction of the record\'s answers alone is an error with the plugin\'s sentence', $why ), array( is_wp_error( $result ) ? $result->get_error_code() : $result, is_wp_error( $result ) ? $result->get_error_message() : '' ), array( 'wpcpm_details_record', 'The details could not be kept on the site, so nothing was saved. Please try again in a while.' ) );
	ck( sprintf( 'and when %s, it wrote no row of history and called Airtable and the index not at all', $why ), array( audit_rows( OTHER ), $GLOBALS['journal'], $GLOBALS['patched'] ), array( array(), array(), array() ) );
}

reset_world();

$GLOBALS['record_fails'] = 'meta';

$result = WPCPM_Institution_Details::write( REC, WPCPM_Institution_Details::changes( REC, array( 'city' => 'Heredia', 'contact_role' => 'Registrar' ) ), 7, 'member' );

ck( 'after an Airtable change the base took, a record the site could not keep leaves the change standing, as it cannot be undone with the base', array( $result, WPCPM_Institutions_Index::row( REC )['city'], $GLOBALS['journal'], count( audit_rows() ) ), array( true, 'Heredia', array( 'airtable', 'option:wpcpm_institutions_index', 'audit' ), 1 ) );

echo "\n-- the source ------------------------------------------------------------\n";

$src  = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details.php' );
$mine = file_get_contents( __FILE__ );

ck( 'no em dash or en dash in the class', preg_match( '/[\x{2013}\x{2014}]/u', $src ), 0 );
ck( 'nor in this suite', preg_match( '/[\x{2013}\x{2014}]/u', $mine ), 0 );
ck( 'the class prints nothing and is no form handler', array( strpos( $src, 'echo ' ), strpos( $src, 'wp_verify_nonce' ), strpos( $src, 'check_admin_referer' ) ), array( false, false, false ) );
ck( 'the one Airtable write is through update_records(), and the column names are the sync\'s', array( substr_count( $src, '->update_records(' ), false !== strpos( $src, 'WPCPM_Institutions_Sync::fields()' ) ), array( 1, true ) );
ck( 'the record ID is checked by the Airtable client', false !== strpos( $src, 'WPCPM_Airtable::is_record_id(' ), true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
