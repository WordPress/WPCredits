<?php
/**
 * Leads (Institutions off Airtable, release 2, Task 14): the schools the program has not talked to yet, kept on
 * the site under a key of their own until one is promoted into the talks.
 *
 * What is pinned here and why:
 *
 * - **The names later tasks write by:** the post type, its two meta keys, the lock's prefix, the Lead stage and
 *   the key's prefix (`WPCPM_Institution_Talks`'s own), and the key's shape.
 * - **`add()`** assigns by area unless told whom, and emails the assignee once with the lead's page, never the
 *   actor and never when asked not to; it refuses a lead with no name, a name the talks already hold (a lead's
 *   or an institution's, trimmed and case-insensitive) and each field that is not what it says.
 * - **A country is a Countries record ID, or text** (`country_text`) when the name could not be resolved, never
 *   both; the resolved name is read before the text.
 * - **Never counted, never shown, never offered, never written:** after leads are added, changed, closed and
 *   reopened the index option is as it was, the forms are offered no lead, the stage groups hold none, and no
 *   request reached Airtable or the copy.
 * - **`update()` and `close()`, `reopen()`** write rows under the lead's own key (`talks_saved`,
 *   `assignment_changed`) that no institution reader lists.
 * - **Closed (PROVISIONAL, owner question 3):** a closed lead keeps everything, is still read by `all_facts()`
 *   (marked closed), is refused promotion until it is reopened.
 * - **`promote()`** takes its key from Airtable's create; then the index row at the stage, the record, the notes,
 *   the rows and one `talks_stage` row are the new record's, and the lead is gone. A refused create leaves the
 *   lead a lead; a promotion stopped after the create resumes with the same record and never creates a second.
 *   Confirmed, the three closing stages, Lead and a stage the talks do not know are refused, and so is a lead
 *   whose country is only text.
 * - **`facts()` and `all_facts()`** read the leads; the four scripts read back byte for byte.
 * - **Wired in:** the loader and uninstall.php require the class; the module boots it and deletes the leads on
 *   uninstall; uninstall sweeps the locks; the post type is private, hidden and never exported.
 *
 * Run from the plugin root:  php bin/test-institution-leads.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['opts']     = array();
$GLOBALS['next']     = 5;
$GLOBALS['mails']    = array();
$GLOBALS['remote']   = array();
$GLOBALS['created']  = array();
$GLOBALS['sent']     = array();
$GLOBALS['types']    = array();
$GLOBALS['held']     = array();
$GLOBALS['answer']   = 'recNEW00000000001';
$GLOBALS['users']    = array();
$GLOBALS['managers'] = array( 7, 11, 12 );

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
	public $ID;
	public $post_type;
	public $post_status;
	public $post_author;
	public $post_content = '';
	public $post_title   = '';
	public $post_date_gmt = '';
	public $post_date     = '';
	public function __construct( array $a ) {
		foreach ( $a as $k => $v ) {
			$this->$k = $v;
		}
	}
}

class WP_User {
	public $ID;
	public $display_name;
	public function __construct( $id, $name ) {
		$this->ID           = $id;
		$this->display_name = $name;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

// The filters a class hooks, by name (the personal data tools, the owner's wave X3).
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['filters'][ $hook ][] = $callback;
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}

function register_post_type( $type, $args ) {
	$GLOBALS['types'][ $type ] = $args;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}

// This request's cache of posts and their meta while `$GLOBALS['post_cache']` is on (the outside review, O3): a post
// or meta row read once is read from the cache until `wp_cache_delete()` drops it, and this request's own writes land
// in it. Another request's write is one to `$GLOBALS['posts']` or `$GLOBALS['pmeta']` alone, which the cache never
// sees. Off, as every check before O3 runs, both read the table.
function wp_cache_delete( $key, $group = '' ) {
	$GLOBALS['cache_dropped'][] = array( $key, $group );
	if ( 'post_meta' === $group ) {
		unset( $GLOBALS['mcache'][ (int) $key ] );
	}
	if ( 'posts' === $group ) {
		unset( $GLOBALS['pcache'][ (int) $key ] );
	}
	return true;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
}

function sanitize_textarea_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function absint( $n ) {
	return abs( (int) $n );
}

function is_email( $email ) {
	return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? 1763546400 : $timestamp );
}

function current_time( $type, $gmt = false ) {
	return '2026-11-19 10:00:00';
}

function get_date_from_gmt( $date, $format = 'Y-m-d H:i:s' ) {
	return $date;
}

function get_post_time( $format, $gmt, $post, $translate = false ) {
	return 1763546400 + (int) $post->ID;
}

function update_meta_cache( $type, $ids ) {}

function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
}

function user_can( $user, $cap ) {
	return in_array( (int) $user, $GLOBALS['managers'], true );
}

// As core's: a slashed copy in, unslashed on the way to the table, so a backslash in a name is a word.
function wp_slash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_slash', $value );
	}
	return is_string( $value ) ? addslashes( $value ) : $value;
}

function stripslashes_deep( $value ) {
	return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}

/**
 * A write that is killed: each insert, meta write and update ticks one, and once more ticks than
 * `$GLOBALS['kill_after']` allows are asked for, the request dies before that write lands.
 */
class Killed extends Exception {}

function wpcpm_test_tick() {
	$GLOBALS['ticks'] = isset( $GLOBALS['ticks'] ) ? $GLOBALS['ticks'] + 1 : 1;

	if ( isset( $GLOBALS['kill_after'] ) && null !== $GLOBALS['kill_after'] && $GLOBALS['ticks'] > $GLOBALS['kill_after'] ) {
		$GLOBALS['kill_after'] = null;
		throw new Killed( 'killed' );
	}
}

function wp_update_post( $postarr, $wp_error = false ) {
	wpcpm_test_tick();
	$id = (int) $postarr['ID'];
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'no' ) : 0;
	}
	foreach ( stripslashes_deep( $postarr ) as $k => $v ) {
		$GLOBALS['posts'][ $id ]->$k = $v;
	}
	unset( $GLOBALS['pcache'][ $id ] );
	return $id;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	wpcpm_test_tick();
	$id                      = $GLOBALS['next']++;
	$postarr                 = stripslashes_deep( $postarr );
	$postarr['ID']           = $id;
	$GLOBALS['posts'][ $id ] = new WP_Post( $postarr );
	return $id;
}

function get_post( $id ) {
	$id = (int) $id;
	if ( ! empty( $GLOBALS['post_cache'] ) ) {
		if ( ! array_key_exists( $id, $GLOBALS['pcache'] ?? array() ) ) {
			$GLOBALS['pcache'][ $id ] = isset( $GLOBALS['posts'][ $id ] ) ? clone $GLOBALS['posts'][ $id ] : null;
		}
		return $GLOBALS['pcache'][ $id ];
	}
	return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null;
}

function get_post_meta( $id, $key = '', $single = false ) {
	if ( ! empty( $GLOBALS['post_cache'] ) ) {
		if ( ! isset( $GLOBALS['mcache'][ (int) $id ] ) ) {
			$GLOBALS['mcache'][ (int) $id ] = isset( $GLOBALS['pmeta'][ (int) $id ] ) ? $GLOBALS['pmeta'][ (int) $id ] : array();
		}
		return isset( $GLOBALS['mcache'][ (int) $id ][ $key ] ) ? $GLOBALS['mcache'][ (int) $id ][ $key ] : '';
	}
	return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : '';
}

function update_post_meta( $id, $key, $value ) {
	wpcpm_test_tick();
	// A write a check refuses, by the post (round 2, L3).
	if ( ( $GLOBALS['meta_fails_for'] ?? 0 ) === (int) $id ) {
		return false;
	}
	// A write a check refuses, by the meta key (the outside review, O4).
	if ( ( $GLOBALS['meta_fails_key'] ?? '' ) === $key ) {
		return false;
	}
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = stripslashes_deep( $value );
	if ( isset( $GLOBALS['mcache'][ (int) $id ] ) ) {
		$GLOBALS['mcache'][ (int) $id ][ $key ] = stripslashes_deep( $value );
	}
	return true;
}

function delete_post_meta( $id, $key ) {
	unset( $GLOBALS['pmeta'][ (int) $id ][ $key ], $GLOBALS['mcache'][ (int) $id ][ $key ] );
	return true;
}

function wp_delete_post( $id, $force = false ) {
	if ( ! isset( $GLOBALS['posts'][ (int) $id ] ) ) {
		return false;
	}
	$post = $GLOBALS['posts'][ (int) $id ];
	unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ], $GLOBALS['pcache'][ (int) $id ], $GLOBALS['mcache'][ (int) $id ] );
	return $post;
}

// Enough of get_posts() for the leads, the notes, the records and the log: the type, the statuses (a list, or
// `any` for all but the trash), one meta_key and meta_value, a meta_query of key and value or EXISTS clauses,
// newest first for the log (date, then ID) and by ID otherwise, numberposts and `ids`.
function get_posts( $args = array() ) {
	$ids = array_keys( $GLOBALS['posts'] );
	sort( $ids );

	if ( isset( $args['orderby']['date'] ) ) {
		$ids = array_reverse( $ids );
	}

	$out = array();

	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];

		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( isset( $args['post_status'] ) ) {
			if ( 'any' === $args['post_status'] ) {
				if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					continue;
				}
			} elseif ( ! in_array( $post->post_status, (array) $args['post_status'], true ) ) {
				continue;
			}
		}

		if ( isset( $args['meta_key'] ) ) {
			$have = get_post_meta( $id, $args['meta_key'], true );
			if ( '' === $have || (string) $have !== (string) ( isset( $args['meta_value'] ) ? $args['meta_value'] : '' ) ) {
				continue;
			}
		}

		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
				continue;
			}
			$have = isset( $GLOBALS['pmeta'][ $id ][ $clause['key'] ] );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) {
				$ok = $ok && $have;
				continue;
			}
			// The database's collation does not tell `lead-5` from `LEAD-5`, or `recABC` from `recabc`.
			$ok = $ok && $have && 0 === strcasecmp( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], (string) $clause['value'] );
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

// Any request that left the site would be one of these, and none may.
function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['remote'][] = $url;
	return new WP_Error( 'blocked', 'No request leaves a suite.' );
}

function wp_remote_get( $url, $args = array() ) {
	return wp_remote_request( $url, $args );
}

function wp_remote_post( $url, $args = array() ) {
	return wp_remote_request( $url, $args );
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) {
		return is_scalar( $v ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) );
	}
}

class WPCPM_Test_Institutions {
	public static function is_test( $name ) {
		return false;
	}
	public static function label( $name, $fallback ) {
		return $fallback;
	}
}

/** Poland routes to 11, Japan to nobody. */
class WPCPM_Institution_Areas {
	public static function administrator_for( $country_id ) {
		return 'recCTY0000000PL01' === (string) $country_id ? 11 : 0;
	}
}

/** The setting behind which the site keeps the record of institutions: on, unless a check turns it off. */
$GLOBALS['on_site'] = true;
class WPCPM_Settings {
	public static function get_value( $key, $default = null ) {
		return 'institutions_on_site' === $key ? $GLOBALS['on_site'] : $default;
	}
}

class WPCPM_Countries {
	public static function name_of( $record_id ) {
		$names = array(
			'recCTY0000000PL01' => 'Poland',
			'recCTY0000000JP01' => 'Japan',
		);
		return isset( $names[ (string) $record_id ] ) ? $names[ (string) $record_id ] : '';
	}
}

/** The copy, recorded: what the create was asked, and every send; the create answers as told. */
class WPCPM_Institution_Copy {
	public static function create( array $row ) {
		$GLOBALS['created'][] = $row;
		return is_wp_error( $GLOBALS['answer'] ) ? $GLOBALS['answer'] : (string) $GLOBALS['answer'];
	}
	public static function send( $record_id, array $keys ) {
		$GLOBALS['sent'][] = array( $record_id, $keys );
		return false;
	}
}

class WPCPM_Lock {
	public static function take( $name, $ttl ) {
		$GLOBALS['ttls'][ $name ] = $ttl;
		return empty( $GLOBALS['held'][ $name ] ) ? 'token' : '';
	}
	public static function release( $name, $token ) {
		return true;
	}
}

class WPCPM_Mail {
	public static function site_name() {
		return 'Education Directory';
	}
	public static function send( $user_id, $id, $build ) {
		$GLOBALS['mails'][] = array( $user_id, $id, $build() );
		return true;
	}
}

class WPCPM_Mail_Templates {
	public static function mail( $id, array $args ) {
		return $args;
	}
}

class WPCPM_Institutions_Dashboard {
	public static function card_url( string $card, string $record = '', string $item = '', array $args = array() ): string {
		return 'https://example.test/institution-dashboard/?wpcpm_institution_view=' . $record . '#wpcpm-' . $card;
	}
}

class WPCPM_Administrators_Dashboard {
	public static function view_url( $view = 'overview' ) {
		return 'https://example.test/administrator-dashboard/?wpcpm_view=' . $view;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talk-notes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php';

if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php';
}

$total = 0;
$fails = 0;

function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;

	if ( $actual === $expected ) {
		echo "  ok   {$label}\n";
		return;
	}

	++$fails;
	echo "  FAIL {$label}\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

if ( ! class_exists( 'WPCPM_Institution_Leads' ) ) {
	ck( 'the leads class exists', false, true );
	printf( "\n%d FAILED (%d checks)\n", $fails, $total );
	exit( 1 );
}

/** The code and the sentence of an answer, or the answer itself when it is not a refusal. */
function wp_sprintf_l( $pattern, $args ) { $args = array_values( (array) $args ); if ( count( $args ) < 2 ) { return implode( '', $args ); } $last = array_pop( $args ); return implode( ', ', $args ) . ( count( $args ) > 1 ? ', and ' : ' and ' ) . $last; }
function said( $answer ) {
	return is_wp_error( $answer ) ? array( $answer->get_error_code(), $answer->get_error_message() ) : $answer;
}

/** An index row, shaped as the index keeps one. */
function row( $id, $name, $stage, array $more = array() ) {
	return array_merge(
		WPCPM_Institutions_Index::empty_row(),
		array(
			'record_id' => $id,
			'name'      => $name,
			'stage'     => $stage,
		),
		$more
	);
}

foreach ( array( 7 => 'Ana Admin', 11 => 'Piotr Admin', 12 => 'Mei Admin', 30 => 'Not Staff' ) as $id => $name ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name );
}

$school = 'recEXA00000000001';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array(
	'v'    => WPCPM_Institutions_Index::VERSION,
	'read' => 1763546400,
	'rows' => array(
		$school => row( $school, 'Example University ', 'Info Sent', array( 'country' => 'recCTY0000000PL01', 'country_name' => 'Poland' ) ),
	),
);
$index_before = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];

$lead_rows = static function ( $key ) {
	return array_column( WPCPM_Institution_Audit::entries_for_lead( $key, 0 ), 'kind' );
};

echo "=== The names later tasks write by ===\n";

ck(
	'the post type, its meta keys, the lock, the stage and the key\'s prefix',
	array( WPCPM_Institution_Leads::POST_TYPE, WPCPM_Institution_Leads::META_FIELDS, WPCPM_Institution_Leads::META_SOURCE, WPCPM_Institution_Leads::LOCK_PREFIX, WPCPM_Institution_Leads::STAGE, WPCPM_Institution_Leads::KEY_PREFIX ),
	array( 'wpcpm_inst_lead', '_wpcpm_lead_fields', '_wpcpm_lead_source', 'wpcpm_lead_lock_', 'Lead', 'lead-' )
);
ck( 'the stage and the prefix are the talks\' own', array( WPCPM_Institution_Leads::STAGE === WPCPM_Institution_Talks::STAGE_LEAD, WPCPM_Institution_Leads::KEY_PREFIX === WPCPM_Institution_Talks::LEAD_PREFIX ), array( true, true ) );
ck(
	'a key is lead- and a post ID',
	array( WPCPM_Institution_Leads::key( 5 ), WPCPM_Institution_Leads::key( 0 ), WPCPM_Institution_Leads::is_key( 'lead-5' ), WPCPM_Institution_Leads::is_key( 'lead-05' ), WPCPM_Institution_Leads::is_key( $school ), WPCPM_Institution_Leads::is_key( 'lead-5 ' ) ),
	array( 'lead-5', '', true, false, false, false )
);
ck( 'the audit\'s key for a lead\'s rows', WPCPM_Institution_Audit::META_LEAD, '_wpcpm_log_lead' );

echo "\n=== add() ===\n";

$poland = WPCPM_Institution_Leads::add(
	array(
		'name'               => 'Example Polytechnic',
		'city'               => 'Example City',
		'country'            => 'recCTY0000000PL01',
		'contact_person'     => 'Example Contact, Second Contact',
		'contact_email'      => 'Contact@Polytechnic.example',
		'help_desk'          => 'https://helpdesk.example/conversation/1',
		'lead_owner'         => 'An Earlier Owner',
		'last_contact'       => '2026-10-01',
		'next_step'          => 'Send the deck',
		'follow_up'          => '2026-11-04',
		'estimated_students' => '40',
		'first_students'     => '2027-02-01',
		'source'             => 'outreach:recOUT00000000001',
	),
	7
);
ck( 'a lead in Poland is added as lead-5', $poland, 'lead-5' );
$lead = WPCPM_Institution_Leads::get( 'lead-5' );
ck( 'assigned by area to 11', $lead['assigned'] ?? null, 11 );
ck( 'and 11 is emailed once, with the lead\'s page', array( count( $GLOBALS['mails'] ), $GLOBALS['mails'][0][0] ?? null, $GLOBALS['mails'][0][1] ?? null, $GLOBALS['mails'][0][2]['institution_name'] ?? null, $GLOBALS['mails'][0][2]['institution_link'] ?? null ), array( 1, 11, 'institution-assigned', 'Example Polytechnic', 'https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=lead-5' ) );
ck( 'the country\'s name is in the email', $GLOBALS['mails'][0][2]['country_phrase'] ?? null, ', in Poland,' );
ck(
	'every field is kept, the address lowercased, the country\'s name resolved',
	array( $lead['name'], $lead['city'], $lead['country'], $lead['country_name'], $lead['country_text'], $lead['contact_person'], $lead['contact_email'], $lead['help_desk'], $lead['lead_owner'], $lead['last_contact'], $lead['next_step'], $lead['follow_up'], $lead['estimated_students'], $lead['first_students'], $lead['source'], $lead['closed'] ),
	array( 'Example Polytechnic', 'Example City', 'recCTY0000000PL01', 'Poland', '', 'Example Contact, Second Contact', 'contact@polytechnic.example', 'https://helpdesk.example/conversation/1', 'An Earlier Owner', '2026-10-01', 'Send the deck', '2026-11-04', '40', '2027-02-01', 'outreach:recOUT00000000001', '' )
);
ck( 'a private post', array( $GLOBALS['posts'][5]->post_type, $GLOBALS['posts'][5]->post_status ), array( 'wpcpm_inst_lead', 'private' ) );
ck( 'found by its source', array( WPCPM_Institution_Leads::by_source( 'outreach:recOUT00000000001' ), WPCPM_Institution_Leads::by_source( 'outreach:recOUT00000000099' ), WPCPM_Institution_Leads::by_source( '' ) ), array( 'lead-5', '', '' ) );

$GLOBALS['mails'] = array();
$by_owner = WPCPM_Institution_Leads::add( array( 'name' => 'Example Academy', 'country' => 'recCTY0000000PL01' ), 11 );
ck( 'the actor who is the assignee is not emailed', array( is_string( $by_owner ), WPCPM_Institution_Leads::get( $by_owner )['assigned'] ?? null, count( $GLOBALS['mails'] ) ), array( true, 11, 0 ) );
$quiet = WPCPM_Institution_Leads::add( array( 'name' => 'Example Lyceum', 'country' => 'recCTY0000000PL01' ), 7, false );
ck( 'and nobody is emailed when the caller asks for none', array( WPCPM_Institution_Leads::get( $quiet )['assigned'] ?? null, count( $GLOBALS['mails'] ) ), array( 11, 0 ) );
$japan = WPCPM_Institution_Leads::add( array( 'name' => 'Example Gakuin', 'country' => 'recCTY0000000JP01' ), 7 );
ck( 'a country no area holds is assigned to nobody, and nobody is emailed', array( WPCPM_Institution_Leads::get( $japan )['assigned'] ?? null, count( $GLOBALS['mails'] ) ), array( 0, 0 ) );
$chosen = WPCPM_Institution_Leads::add( array( 'name' => 'Example Conservatory', 'country' => 'recCTY0000000PL01', 'assigned' => 12 ), 7 );
ck( 'an assignee given is kept over the area, and emailed', array( WPCPM_Institution_Leads::get( $chosen )['assigned'] ?? null, count( $GLOBALS['mails'] ), $GLOBALS['mails'][0][0] ?? null ), array( 12, 1, 12 ) );
$GLOBALS['mails'] = array();

ck( 'an empty name is refused', said( WPCPM_Institution_Leads::add( array( 'name' => '  ' ), 7 ) ), array( 'wpcpm_lead_name', 'Give the school\'s name.' ) );
ck( 'a name over 200 characters is refused', said( WPCPM_Institution_Leads::add( array( 'name' => str_repeat( 'a', 201 ) ), 7 ) )[1] ?? null, 'The school\'s name can be at most 200 characters long.' );
ck( 'a name a lead already has, in other case and space, is refused', said( WPCPM_Institution_Leads::add( array( 'name' => '  example POLYTECHNIC ' ), 7 ) ), array( 'wpcpm_lead_taken', 'A school of that name is already in the talks.' ) );
ck( 'and so is an institution\'s', said( WPCPM_Institution_Leads::add( array( 'name' => 'example university' ), 7 ) ), array( 'wpcpm_lead_taken', 'A school of that name is already in the talks.' ) );
ck( 'name_taken() answers with the key that holds the name', array( WPCPM_Institution_Leads::name_taken( ' Example Polytechnic' ), WPCPM_Institution_Leads::name_taken( 'EXAMPLE UNIVERSITY' ), WPCPM_Institution_Leads::name_taken( 'Nowhere College' ), WPCPM_Institution_Leads::name_taken( '' ) ), array( 'lead-5', $school, '', '' ) );
ck( 'an assignee who is not an Administrator is refused', said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Seminary', 'assigned' => 30 ), 7 ) ), array( 'wpcpm_lead_assigned', 'Only an Administrator can be assigned a school.' ) );
ck( 'an address that is none is refused', said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Seminary', 'contact_email' => 'not an address' ), 7 ) ), array( 'wpcpm_lead_email', 'That contact email is not an email address.' ) );
ck( 'a help desk link that is not https is refused', said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Seminary', 'help_desk' => 'http://helpdesk.example/1' ), 7 ) ), array( 'wpcpm_lead_help_desk', 'The help desk link must be an address that starts with https://.' ) );
ck( 'a date that is none is refused', said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Seminary', 'follow_up' => '2026-02-30' ), 7 ) ), array( 'wpcpm_lead_date', 'Follow up on must be a date.' ) );
ck( 'a number of students out of range is refused', said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Seminary', 'estimated_students' => '100001' ), 7 ) ), array( 'wpcpm_lead_number', 'Estimated students must be a whole number from 0 to 100000.' ) );
ck( 'a country that is no record ID is refused', said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Seminary', 'country' => 'Poland' ), 7 ) ), array( 'wpcpm_lead_country', 'That country is not one of the program\'s.' ) );
ck( 'a next step over 200 characters is refused', said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Seminary', 'next_step' => str_repeat( 'b', 201 ) ), 7 ) )[1] ?? null, 'The next step can be at most 200 characters long.' );
ck( 'and no refusal made a post or sent an email', array( count( WPCPM_Institution_Leads::all() ), count( $GLOBALS['mails'] ) ), array( 5, 0 ) );

echo "\n=== A country is an ID, or the text it came as ===\n";

$text = WPCPM_Institution_Leads::add( array( 'name' => 'Example Hochschule', 'country_text' => 'Atlantis' ), 7 );
$read = WPCPM_Institution_Leads::get( $text );
ck( 'an unresolved country is kept as text, with no ID, and nobody is assigned', array( $read['country'], $read['country_name'], $read['country_text'], $read['assigned'] ), array( '', '', 'Atlantis', 0 ) );
ck( 'facts() print the text where no name is resolved', array( WPCPM_Institution_Talks::facts( $text )['country_name'] ?? null, WPCPM_Institution_Talks::facts( $text )['country_text'] ?? null ), array( 'Atlantis', 'Atlantis' ) );
ck( 'a country resolved later clears the text', array( WPCPM_Institution_Leads::update( $text, array( 'country' => 'recCTY0000000JP01' ), 7 ), WPCPM_Institution_Leads::get( $text )['country'], WPCPM_Institution_Leads::get( $text )['country_name'], WPCPM_Institution_Leads::get( $text )['country_text'] ), array( true, 'recCTY0000000JP01', 'Japan', '' ) );
$both = WPCPM_Institution_Leads::add( array( 'name' => 'Example Kolegium', 'country' => 'recCTY0000000PL01', 'country_text' => 'Polska' ), 7, false );
ck( 'an ID and a text given together keep the ID alone', array( WPCPM_Institution_Leads::get( $both )['country'], WPCPM_Institution_Leads::get( $both )['country_text'] ), array( 'recCTY0000000PL01', '' ) );

echo "\n=== update() writes the lead's own rows ===\n";

$GLOBALS['mails'] = array();
ck( 'a change of the next step and the follow-up is saved', WPCPM_Institution_Leads::update( 'lead-5', array( 'next_step' => 'Call the dean', 'follow_up' => '2026-12-01' ), 7 ), true );
$rows = WPCPM_Institution_Audit::entries_for_lead( 'lead-5', 0 );
ck( 'one talks_saved row under the lead, naming the fields', array( count( $rows ), $rows[0]['kind'] ?? null, $rows[0]['message'] ?? null, $rows[0]['data']['changed'] ?? null, $rows[0]['actor'] ?? null ), array( 1, 'talks_saved', 'The talks were updated: Next step and Follow up on.', array( 'next_step', 'follow_up' ), 7 ) );
ck( 'a reassignment is saved', WPCPM_Institution_Leads::update( 'lead-5', array( 'assigned' => 12 ), 7 ), true );
$rows = WPCPM_Institution_Audit::entries_for_lead( 'lead-5', 0 );
ck( 'with one assignment_changed row', array( $rows[0]['kind'] ?? null, $rows[0]['message'] ?? null, $rows[0]['data'] ?? null ), array( 'assignment_changed', 'Mei Admin is now the assigned Administrator.', array( 'from' => 11, 'to' => 12 ) ) );
ck( 'and the new assignee is emailed once', array( count( $GLOBALS['mails'] ), $GLOBALS['mails'][0][0] ?? null ), array( 1, 12 ) );
ck( 'an unchanged save changes nothing and writes no row', array( WPCPM_Institution_Leads::update( 'lead-5', array( 'next_step' => 'Call the dean', 'assigned' => 12 ), 7 ), count( WPCPM_Institution_Audit::entries_for_lead( 'lead-5', 0 ) ) ), array( false, 2 ) );
ck( 'a rename to a name another school has is refused', said( WPCPM_Institution_Leads::update( 'lead-5', array( 'name' => 'Example Academy' ), 7 ) ), array( 'wpcpm_lead_taken', 'A school of that name is already in the talks.' ) );
ck( 'and a rename to its own name in other case is a change', array( WPCPM_Institution_Leads::update( 'lead-5', array( 'name' => 'Example polytechnic' ), 7 ), WPCPM_Institution_Leads::get( 'lead-5' )['name'] ), array( true, 'Example polytechnic' ) );
WPCPM_Institution_Leads::update( 'lead-5', array( 'name' => 'Example Polytechnic' ), 7 );
ck( 'a refused change writes nothing', array( said( WPCPM_Institution_Leads::update( 'lead-5', array( 'next_step' => 'x', 'help_desk' => 'ftp://x' ), 7 ) )[0] ?? null, WPCPM_Institution_Leads::get( 'lead-5' )['next_step'] ), array( 'wpcpm_lead_help_desk', 'Call the dean' ) );
ck( 'a lead the site does not keep is refused', said( WPCPM_Institution_Leads::update( 'lead-999', array( 'next_step' => 'x' ), 7 ) ), array( 'wpcpm_lead_gone', 'That lead is not in the talks any more.' ) );
ck( 'no institution reader lists a lead\'s rows', array( WPCPM_Institution_Audit::entries_for( $school, 0 ), WPCPM_Institution_Audit::sponsor_entries( '', 0 ) ), array( array(), array() ) );

echo "\n=== Closed, not promoted (PROVISIONAL, owner question 3) ===\n";

ck( 'close() closes a lead', WPCPM_Institution_Leads::close( $japan, 7 ), true );
ck( 'and keeps everything, with the day it was closed', array( WPCPM_Institution_Leads::get( $japan )['name'], WPCPM_Institution_Leads::get( $japan )['country'], WPCPM_Institution_Leads::get( $japan )['closed'] ), array( 'Example Gakuin', 'recCTY0000000JP01', '2025-11-19' ) );
ck( 'a second close changes nothing', WPCPM_Institution_Leads::close( $japan, 7 ), false );
ck( 'its row says so', array( WPCPM_Institution_Audit::entries_for_lead( $japan, 0 )[0]['kind'] ?? null, WPCPM_Institution_Audit::entries_for_lead( $japan, 0 )[0]['message'] ?? null ), array( 'talks_saved', 'The lead was closed.' ) );
$facts = WPCPM_Institution_Talks::all_facts();
ck( 'all_facts() still reads it, marked closed, so Find finds it', array( isset( $facts[ $japan ] ), $facts[ $japan ]['closed'] ?? null, $facts['lead-5']['closed'] ?? null ), array( true, true, false ) );
ck( 'a closed lead is not promoted', said( WPCPM_Institution_Leads::promote( $japan, 'First Contact Made', 7 ) ), array( 'wpcpm_lead_closed', 'This lead is closed. Reopen it before you move it on.' ) );
ck( 'reopen() opens it again', array( WPCPM_Institution_Leads::reopen( $japan, 7 ), WPCPM_Institution_Leads::get( $japan )['closed'], WPCPM_Institution_Leads::reopen( $japan, 7 ) ), array( true, '', false ) );
ck( 'with its own row', WPCPM_Institution_Audit::entries_for_lead( $japan, 0 )[0]['message'] ?? null, 'The lead was reopened.' );
ck( 'and an unknown lead is neither closed nor reopened', array( said( WPCPM_Institution_Leads::close( 'lead-999', 7 ) )[0] ?? null, said( WPCPM_Institution_Leads::reopen( 'lead-999', 7 ) )[0] ?? null ), array( 'wpcpm_lead_gone', 'wpcpm_lead_gone' ) );
ck( 'and nothing of it was emailed', count( $GLOBALS['mails'] ), 1 );

echo "\n=== Never counted, never shown, never offered, never written ===\n";

ck( 'the index option is as it was', $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ], $index_before );
ck( 'the forms are offered no lead, at any stage', WPCPM_Institutions_Index::offered_to_forms( WPCPM_Institution_Talks::stages(), true ), array( $school => 'Example University' ) );
ck( 'the stage groups hold no lead', array_keys( WPCPM_Institutions_Index::by_stage() ), array( 'Info Sent' ) );
ck( 'no request reached Airtable, and nothing was asked of the copy', array( $GLOBALS['remote'], $GLOBALS['created'], $GLOBALS['sent'] ), array( array(), array(), array() ) );

echo "\n=== facts() and all_facts() read the leads ===\n";

$f = WPCPM_Institution_Talks::facts( 'lead-5' );
ck(
	'facts() of a lead, every key in the institutions\' shape',
	$f,
	array(
		'key'                 => 'lead-5',
		'lead'                => true,
		'closed'              => false,
		'name'                => 'Example Polytechnic',
		'stage'               => 'Lead',
		'country'             => 'recCTY0000000PL01',
		'country_name'        => 'Poland',
		'country_text'        => '',
		'city'                => 'Example City',
		'website'             => '',
		'contact_person'      => 'Example Contact, Second Contact',
		'contact_email'       => 'contact@polytechnic.example',
		'assigned'            => 12,
		'estimated_students'  => '40',
		'first_students'      => '2027-02-01',
		'next_step'           => 'Call the dean',
		'follow_up'           => '2026-12-01',
		'last_contact'        => '2026-10-01',
		'help_desk'           => 'https://helpdesk.example/conversation/1',
		'lead_owner'          => 'An Earlier Owner',
		'program_details'     => '',
		'application_answers' => array(),
	)
);
ck( 'and of an institution, the same keys', array_keys( WPCPM_Institution_Talks::facts( $school ) ), array_keys( $f ) );
ck( 'an institution is never closed and has no country text', array( WPCPM_Institution_Talks::facts( $school )['closed'], WPCPM_Institution_Talks::facts( $school )['country_text'] ), array( false, '' ) );
ck( 'a lead the site does not keep is null', array( WPCPM_Institution_Talks::facts( 'lead-999' ), WPCPM_Institution_Talks::facts( 'lead-0' ) ), array( null, null ) );
$all = WPCPM_Institution_Talks::all_facts();
ck( 'all_facts() holds the index\'s rows, then every lead', array_keys( $all ), array_merge( array( $school ), array_keys( WPCPM_Institution_Leads::all() ) ) );
ck( 'each lead the same as facts() says of it', $all['lead-5'] === $f, true );

$names = array( 'Wyższa Szkoła Górnołąćka', 'Universitatea Câmpiei Rîușițeni', 'جامعة الواحة الخضراء المتخيلة', '架空山学園大学' );
foreach ( $names as $name ) {
	$key  = WPCPM_Institution_Leads::add( array( 'name' => $name, 'city' => $name, 'contact_person' => $name . ' \\ "q"' ), 7, false );
	$back = WPCPM_Institution_Talks::facts( $key );
	ck( 'byte for byte: ' . bin2hex( substr( $name, 0, 6 ) ), array( bin2hex( (string) ( $back['name'] ?? '' ) ), bin2hex( (string) ( $back['city'] ?? '' ) ), bin2hex( (string) ( $back['contact_person'] ?? '' ) ) ), array( bin2hex( $name ), bin2hex( $name ), bin2hex( $name . ' \\ "q"' ) ) );
}

echo "\n=== promote() ===\n";

WPCPM_Institution_Talk_Notes::add( 'lead-5', 'Met at an event.', 7 );
WPCPM_Institution_Talk_Notes::add( 'lead-5', 'Sent the deck.', 7 );
$before_lead  = WPCPM_Institution_Leads::get( 'lead-5' );
$before_index = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];

ck( 'to Confirmed is refused', said( WPCPM_Institution_Leads::promote( 'lead-5', 'Confirmed', 7 ) ), array( 'wpcpm_lead_confirmed', 'Confirmed is set when the Collaboration Agreement is accepted or recorded as on file.' ) );
foreach ( array( 'Not Moving Forward', 'SPAM', 'Revisit Later' ) as $closing ) {
	ck( 'to ' . $closing . ' is refused: a lead that goes nowhere is closed', said( WPCPM_Institution_Leads::promote( 'lead-5', $closing, 7 ) ), array( 'wpcpm_lead_stage_closed', 'A lead that goes nowhere is closed, not moved to ' . $closing . '.' ) );
}
ck( 'to Lead, or to a stage the talks do not know, is refused', array( said( WPCPM_Institution_Leads::promote( 'lead-5', 'Lead', 7 ) )[0] ?? null, said( WPCPM_Institution_Leads::promote( 'lead-5', 'Signed', 7 ) )[0] ?? null ), array( 'wpcpm_lead_stage', 'wpcpm_lead_stage' ) );
ck( 'a lead whose country is only text is refused', said( WPCPM_Institution_Leads::promote( $text = WPCPM_Institution_Leads::add( array( 'name' => 'Example Escuela', 'country_text' => 'Atlantis' ), 7, false ), 'First Contact Made', 7 ) ), array( 'wpcpm_lead_country_text', 'The country of this lead, Atlantis, is not one of the program\'s yet. Choose it, then move the lead on.' ) );
ck( 'a lead another request holds is refused', ( function () {
	$GLOBALS['held']['wpcpm_lead_lock_5'] = true;
	$answer = said( WPCPM_Institution_Leads::promote( 'lead-5', 'First Contact Made', 7 ) );
	unset( $GLOBALS['held']['wpcpm_lead_lock_5'] );
	return $answer;
} )(), array( 'wpcpm_lead_busy', 'Somebody else is changing this lead. Try again in a while.' ) );
ck( 'promote_refuses_student: to Student, which comes after Confirmed, is refused as Confirmed is', said( WPCPM_Institution_Leads::promote( 'lead-5', 'Student', 7 ) ), array( 'wpcpm_lead_confirmed', 'Confirmed is set when the Collaboration Agreement is accepted or recorded as on file.' ) );
$GLOBALS['on_site'] = false;
ck( 'promote_refused_outside_site_mode: before any request', said( WPCPM_Institution_Leads::promote( 'lead-5', 'First Contact Made', 7 ) ), array( 'wpcpm_lead_not_on_site', 'A lead is moved into the talks only once the site keeps the record of institutions.' ) );
$GLOBALS['on_site'] = true;
// Another school took the name after the lead was added: an institution the index now holds.
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows']['recEXA00000000009'] = row( 'recEXA00000000009', ' EXAMPLE polytechnic', 'Info Sent' );
ck( 'promote_checks_the_name_again: a name an institution has taken since is refused under the lock', said( WPCPM_Institution_Leads::promote( 'lead-5', 'First Contact Made', 7 ) ), array( 'wpcpm_lead_taken', 'A school of that name is already in the talks.' ) );
unset( $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows']['recEXA00000000009'] );
ck( 'none of those asked Airtable for anything', $GLOBALS['created'], array() );

$GLOBALS['answer'] = new WP_Error( 'airtable_refused', 'INVALID_VALUE' );
ck( 'a refused create leaves a lead a lead', said( WPCPM_Institution_Leads::promote( 'lead-5', 'First Contact Made', 7 ) ), array( 'wpcpm_lead_create', 'Airtable could not create the institution\'s record, so it is still a lead. Try again in a while.' ) );
ck( 'untouched: the lead, the index, its notes and its rows', array( WPCPM_Institution_Leads::get( 'lead-5' ) === $before_lead, $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] === $before_index, count( WPCPM_Institution_Talk_Notes::for_key( 'lead-5' ) ), count( WPCPM_Institution_Audit::entries_for_lead( 'lead-5', 0 ) ) ), array( true, true, 2, 4 ) );

$GLOBALS['answer']  = 'recNEW00000000001';
$GLOBALS['created'] = array();
$new                = WPCPM_Institution_Leads::promote( 'lead-5', 'First Contact Made', 7 );
ck( 'promote() returns the record ID Airtable made', $new, 'recNEW00000000001' );
ck(
	'Airtable was asked once, with the lead\'s name, stage, country, city and contact',
	$GLOBALS['created'],
	array(
		array(
			'name'           => 'Example Polytechnic',
			'stage'          => 'First Contact Made',
			'country'        => 'recCTY0000000PL01',
			'city'           => 'Example City',
			'website'        => '',
			'contact_person' => 'Example Contact, Second Contact',
			'contact_email'  => 'contact@polytechnic.example',
		),
	)
);
$irow = WPCPM_Institutions_Index::row( $new );
ck(
	'the index holds the row at that stage, with the agreement not started',
	array( $irow['name'] ?? null, $irow['stage'] ?? null, $irow['country'] ?? null, $irow['country_name'] ?? null, $irow['city'] ?? null, $irow['contact_person'] ?? null, $irow['contact_email'] ?? null, $irow['created'] ?? null, $irow['agreement']['status'] ?? null ),
	array( 'Example Polytechnic', 'First Contact Made', 'recCTY0000000PL01', 'Poland', 'Example City', 'Example Contact, Second Contact', 'contact@polytechnic.example', '2025-11-19', 'Not started' )
);
ck( 'and the rest of the index as it was', $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $school ], $before_index['rows'][ $school ] );
$record = WPCPM_Institution_Record::get( $new );
ck(
	'the record holds the talks, the help desk link, the owner, the last contact, the assignment and the source',
	array( $record['next_step'] ?? null, $record['follow_up'] ?? null, $record['estimated_students'] ?? null, $record['first_students'] ?? null, $record['last_contact'] ?? null, $record['help_desk'] ?? null, $record['lead_owner'] ?? null, $record['assigned'] ?? null, $record['lead_source'] ?? null ),
	array( 'Call the dean', '2026-12-01', '40', '2027-02-01', '2026-10-01', 'https://helpdesk.example/conversation/1', 'An Earlier Owner', 12, 'outreach:recOUT00000000001' )
);
ck( 'the record keeps the lead\'s old key, and promoted_to() finds the school by it (Task 16 fix round 1, G1)', array( $record['lead_key'] ?? null, WPCPM_Institution_Leads::promoted_to( 'lead-5' ), WPCPM_Institution_Leads::promoted_to( 'lead-6' ), WPCPM_Institution_Leads::promoted_to( 'nope' ) ), array( 'lead-5', $new, '', '' ) );
ck( 'the notes are the new record\'s', array( count( WPCPM_Institution_Talk_Notes::for_key( $new ) ), count( WPCPM_Institution_Talk_Notes::for_key( 'lead-5' ) ) ), array( 2, 0 ) );
$moved = WPCPM_Institution_Audit::entries_for( $new, 0 );
ck( 'the history rows are the new record\'s, with one talks_stage row on top', array_column( $moved, 'kind' ), array( 'talks_stage', 'talks_saved', 'talks_saved', 'assignment_changed', 'talks_saved' ) );
ck( 'the stage row says from Lead, to the stage', array( $moved[0]['data'] ?? null, $moved[0]['message'] ?? null, $moved[0]['subject'] ?? null, $moved[0]['actor'] ?? null ), array( array( 'from' => 'Lead', 'to' => 'First Contact Made' ), 'The stage of the talks moved from Lead to First Contact Made.', $new, 7 ) );
ck( 'a moved row is about the record now', array( $moved[1]['institution'] ?? null, $moved[1]['subject'] ?? null ), array( $new, $new ) );
ck( 'and the lead has none', WPCPM_Institution_Audit::entries_for_lead( 'lead-5', 0 ), array() );
ck( 'the lead is gone', array( WPCPM_Institution_Leads::get( 'lead-5' ), isset( $GLOBALS['posts'][5] ), WPCPM_Institution_Leads::by_source( 'outreach:recOUT00000000001' ) ), array( null, false, $new ) );
ck( 'facts() of the record read it as an institution', array( WPCPM_Institution_Talks::facts( $new )['lead'] ?? null, WPCPM_Institution_Talks::facts( $new )['stage'] ?? null, WPCPM_Institution_Talks::facts( $new )['assigned'] ?? null, WPCPM_Institution_Talks::facts( $new )['next_step'] ?? null ), array( false, 'First Contact Made', 12, 'Call the dean' ) );
ck( 'the copy is asked for the talks columns the create does not carry, estimated students and the help desk link among them (the outside review, O9)', $GLOBALS['sent'], array( array( $new, array( 'next_step', 'follow_up', 'last_contact', 'estimated_students', 'help_desk' ) ) ) );
ck( 'no assignment email went with the promotion', count( $GLOBALS['mails'] ), 1 );
ck( 'the name is an institution\'s now', WPCPM_Institution_Leads::name_taken( 'Example Polytechnic' ), $new );

echo "\n=== A promotion stopped after Airtable's create resumes ===\n";

$GLOBALS['answer'] = 'recNEW00000000002';
$GLOBALS['created'] = array();
$half = WPCPM_Institution_Leads::add( array( 'name' => 'Example Akademie', 'country' => 'recCTY0000000PL01' ), 7, false );
$half_id = (int) substr( $half, 5 );
// As a request that died after the create would have left it: the record ID on the lead, nothing else done.
update_post_meta( $half_id, WPCPM_Institution_Leads::META_PROMOTED, 'recNEW00000000003' );
update_post_meta( $half_id, WPCPM_Institution_Leads::META_PROMOTED_STAGE, 'Info Sent' );
$resumed = WPCPM_Institution_Leads::promote( $half, 'Call Scheduled', 7 );
ck( 'promote_resumes_at_the_first_stage: the record already made, Airtable not asked again, at the first attempt\'s stage', array( $resumed, $GLOBALS['created'], WPCPM_Institutions_Index::row( 'recNEW00000000003' )['stage'] ?? null, WPCPM_Institution_Leads::get( $half ) ), array( 'recNEW00000000003', array(), 'Info Sent', null ) );
ck( 'with its one talks_stage row', array_column( WPCPM_Institution_Audit::entries_for( 'recNEW00000000003', 0 ), 'kind' ), array( 'talks_stage' ) );

// The index's lock held for good: the create is made, its record and stage kept, nothing deleted; the next
// try goes on from there with no second create.
$GLOBALS['answer']  = 'recNEW00000000004';
$GLOBALS['created'] = array();
$locked             = WPCPM_Institution_Leads::add( array( 'name' => 'Example Hochschule Zwei', 'country' => 'recCTY0000000PL01' ), 7, false );
$locked_id          = (int) substr( $locked, 5 );
$GLOBALS['held']['wpcpm_institutions_index_lock'] = true;
$first              = said( WPCPM_Institution_Leads::promote( $locked, 'Under Review', 7 ) );
unset( $GLOBALS['held']['wpcpm_institutions_index_lock'] );
ck( 'promote_keeps_the_lead_when_the_index_refuses: the error, the record and stage kept on the lead, nothing in the index', array( $first, get_post_meta( $locked_id, WPCPM_Institution_Leads::META_PROMOTED, true ), get_post_meta( $locked_id, WPCPM_Institution_Leads::META_PROMOTED_STAGE, true ), WPCPM_Institutions_Index::has( 'recNEW00000000004' ), is_array( WPCPM_Institution_Leads::get( $locked ) ), count( $GLOBALS['created'] ) ), array( array( 'wpcpm_lead_index', 'The school could not be added to the site\'s list of institutions, so it is still a lead. Try again in a while.' ), 'recNEW00000000004', 'Under Review', false, true, 1 ) );
$GLOBALS['answer'] = 'recNEW00000000099';
ck( 'and the next try resumes with the same record at the same stage, and no second create', array( WPCPM_Institution_Leads::promote( $locked, 'Call Scheduled', 7 ), count( $GLOBALS['created'] ), WPCPM_Institutions_Index::row( 'recNEW00000000004' )['stage'] ?? null, WPCPM_Institution_Leads::get( $locked ) ), array( 'recNEW00000000004', 1, 'Under Review', null ) );

// A resume that finds the row in the index already (a request that died after the insert): the row is left as
// it is now, and no second stage row is written.
$again    = WPCPM_Institution_Leads::add( array( 'name' => 'Example Hochschule Drei', 'country' => 'recCTY0000000PL01' ), 7, false );
$again_id = (int) substr( $again, 5 );
update_post_meta( $again_id, WPCPM_Institution_Leads::META_PROMOTED, 'recNEW00000000005' );
update_post_meta( $again_id, WPCPM_Institution_Leads::META_PROMOTED_STAGE, 'Info Sent' );
WPCPM_Institutions_Index::insert( row( 'recNEW00000000005', 'Example Hochschule Drei', 'Waiting on Reply', array( 'city' => 'Changed since' ) ) );
ck( 'promote_leaves_an_existing_row_alone: its later stage and city stay, and the stage row it died before is written, once', array( WPCPM_Institution_Leads::promote( $again, 'Info Sent', 7 ), WPCPM_Institutions_Index::row( 'recNEW00000000005' )['stage'] ?? null, WPCPM_Institutions_Index::row( 'recNEW00000000005' )['city'] ?? null, array_column( WPCPM_Institution_Audit::entries_for( 'recNEW00000000005', 0 ), 'kind' ), WPCPM_Institution_Audit::entries_for( 'recNEW00000000005', 0 )[0]['data'] ?? null, WPCPM_Institution_Leads::get( $again ) ), array( 'recNEW00000000005', 'Waiting on Reply', 'Changed since', array( 'talks_stage' ), array( 'from' => 'Lead', 'to' => 'Info Sent' ), null ) );

// Fix wave, E3: a resume after the stage row was written (it died before the lead was deleted) writes no second.
$after_row    = WPCPM_Institution_Leads::add( array( 'name' => 'Example Hochschule Sechs', 'country' => 'recCTY0000000PL01' ), 7, false );
$after_row_id = (int) substr( $after_row, 5 );
update_post_meta( $after_row_id, WPCPM_Institution_Leads::META_PROMOTED, 'recNEW00000000007' );
update_post_meta( $after_row_id, WPCPM_Institution_Leads::META_PROMOTED_STAGE, 'Info Sent' );
WPCPM_Institutions_Index::insert( row( 'recNEW00000000007', 'Example Hochschule Sechs', 'Info Sent' ) );
WPCPM_Institution_Audit::record( array( 'kind' => WPCPM_Institution_Talks::LOG_STAGE, 'institution' => 'recNEW00000000007', 'subject' => 'recNEW00000000007', 'actor' => 7, 'ground' => 'manager', 'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX, 'message' => 'The stage of the talks moved from Lead to Info Sent.', 'data' => array( 'from' => 'Lead', 'to' => 'Info Sent' ) ) );
ck( 'promote_stage_row_once: a resume that finds the Lead row written writes no second', array( WPCPM_Institution_Leads::promote( $after_row, 'Info Sent', 7 ), array_column( WPCPM_Institution_Audit::entries_for( 'recNEW00000000007', 0 ), 'kind' ) ), array( 'recNEW00000000007', array( 'talks_stage' ) ) );

// Fix wave, E1: a resume whose record the index holds already is not checked against the names again: another
// school of the same name listed before it does not stop the promotion halfway.
$named    = WPCPM_Institution_Leads::add( array( 'name' => 'Example Hochschule Vier', 'country' => 'recCTY0000000PL01' ), 7, false );
$named_id = (int) substr( $named, 5 );
update_post_meta( $named_id, WPCPM_Institution_Leads::META_PROMOTED, 'recNEW00000000006' );
update_post_meta( $named_id, WPCPM_Institution_Leads::META_PROMOTED_STAGE, 'Info Sent' );
WPCPM_Institutions_Index::insert( row( 'recEXA00000000066', 'Example Hochschule Vier', 'Info Sent' ) );
WPCPM_Institutions_Index::insert( row( 'recNEW00000000006', 'Example Hochschule Vier', 'Info Sent' ) );
ck( 'promote_resume_listed_skips_name: the resume goes on and the lead is gone', array( WPCPM_Institution_Leads::promote( $named, 'Info Sent', 7 ), WPCPM_Institution_Leads::get( $named ) ), array( 'recNEW00000000006', null ) );

echo "\n=== O4: a promotion deletes the lead only after everything moved ===\n";

// Each of the three steps that carry what the lead holds made to fail in turn: the record's answers, the notes' key,
// the history rows' key. The lead stays whole and unfinished, the promotion answers that the move did not finish under
// a code of its own and sends nothing (round 2, R2), and the next promotion finishes it with no second create and one
// send.
$o4_refusal = array( 'wpcpm_lead_move_unfinished', 'This lead\'s move into the talks as an institution did not finish. Choose the stage again and save to finish it.' );
$o4_n       = 0;
foreach ( array(
	'the record' => WPCPM_Institution_Record::META_ANSWERS,
	'the notes'  => WPCPM_Institution_Talk_Notes::META_FOR,
	'the rows'   => WPCPM_Institution_Audit::META_INSTITUTION,
) as $o4_what => $o4_key ) {
	++$o4_n;
	$GLOBALS['answer']  = 'recO4A0000000000' . $o4_n;
	$GLOBALS['created'] = array();
	$GLOBALS['sent']    = array();
	$o4_lead            = WPCPM_Institution_Leads::add( array( 'name' => 'Example O4 School ' . $o4_n, 'country' => 'recCTY0000000PL01', 'next_step' => 'Send the plan' ), 7, false );
	WPCPM_Institution_Leads::update( $o4_lead, array( 'follow_up' => '2026-12-12' ), 7 );
	WPCPM_Institution_Talk_Notes::add( $o4_lead, 'A note the lead carries.', 7 );
	$GLOBALS['meta_fails_key'] = $o4_key;
	$o4_first                  = said( WPCPM_Institution_Leads::promote( $o4_lead, 'Info Sent', 7 ) );
	$GLOBALS['meta_fails_key'] = '';
	$o4_kept                   = WPCPM_Institution_Leads::get( $o4_lead );
	ck(
		"o4_{$o4_n}: {$o4_what} not moved leaves the lead whole and unfinished, says the move did not finish, and sends nothing (R2)",
		array( $o4_first, is_array( $o4_kept ), WPCPM_Institution_Leads::unfinished( $o4_lead ), $o4_kept['next_step'] ?? null, $GLOBALS['sent'] ),
		array( $o4_refusal, true, true, 'Send the plan', array() )
	);
	$o4_second = WPCPM_Institution_Leads::promote( $o4_lead, 'Info Sent', 7 );
	ck(
		"o4_{$o4_n}: the next promotion finishes it with no second create and one send: the record, the note and the rows the school's, the lead gone",
		array( $o4_second, count( $GLOBALS['created'] ), count( $GLOBALS['sent'] ), WPCPM_Institution_Record::get( $GLOBALS['answer'] )['next_step'] ?? null, count( WPCPM_Institution_Talk_Notes::for_key( $GLOBALS['answer'] ) ), count( WPCPM_Institution_Talk_Notes::for_key( $o4_lead ) ), WPCPM_Institution_Audit::entries_for_lead( $o4_lead, 0 ), in_array( 'talks_saved', array_column( WPCPM_Institution_Audit::entries_for( $GLOBALS['answer'], 0 ), 'kind' ), true ), WPCPM_Institution_Leads::get( $o4_lead ) ),
		array( $GLOBALS['answer'], 1, 1, 'Send the plan', 1, 0, array(), true, null )
	);
}

$GLOBALS['answer']  = 'recO4A00000000009';
$GLOBALS['created'] = array();
$o4_whole           = WPCPM_Institution_Leads::add( array( 'name' => 'Example O4 Whole', 'country' => 'recCTY0000000PL01', 'next_step' => 'Send the plan' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $o4_whole, 'A note the lead carries.', 7 );
ck( 'o4_all_finish: all three finishing deletes the lead as today, for good', array( WPCPM_Institution_Leads::promote( $o4_whole, 'Info Sent', 7 ), isset( $GLOBALS['posts'][ (int) substr( $o4_whole, 5 ) ] ) ), array( 'recO4A00000000009', false ) );

$unlisted    = WPCPM_Institution_Leads::add( array( 'name' => 'Example Hochschule Fuenf', 'country' => 'recCTY0000000PL01' ), 7, false );
$unlisted_id = (int) substr( $unlisted, 5 );
update_post_meta( $unlisted_id, WPCPM_Institution_Leads::META_PROMOTED, 'recNEW00000000008' );
update_post_meta( $unlisted_id, WPCPM_Institution_Leads::META_PROMOTED_STAGE, 'Info Sent' );
WPCPM_Institutions_Index::insert( row( 'recEXA00000000067', 'Example Hochschule Fuenf', 'Info Sent' ) );
ck( 'and a resume whose record the index does not hold yet is still checked: a school of that name refuses it', array( said( WPCPM_Institution_Leads::promote( $unlisted, 'Info Sent', 7 ) )[0] ?? null, WPCPM_Institutions_Index::has( 'recNEW00000000008' ) ), array( 'wpcpm_lead_taken', false ) );

// Fix wave, E4: the stage is kept on the lead before the record ID, so a resume always has the stage it was made at.
$order_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php' );
ck( 'promote_stage_kept_first: META_PROMOTED_STAGE is written before META_PROMOTED', strpos( $order_src, 'update_post_meta( $post_id, self::META_PROMOTED_STAGE, $stage );' ) < strpos( $order_src, 'update_post_meta( $post_id, self::META_PROMOTED, $record_id );' ), true );

echo "\n=== A refused or timed-out create is logged by the lead's key ===\n";

$GLOBALS['answer'] = new WP_Error( 'http_request_failed', 'Operation timed out' );
$quietly           = WPCPM_Institution_Leads::add( array( 'name' => 'Example Timed Out School', 'contact_email' => 'someone@timedout.example' ), 7, false );
$log_file          = tempnam( sys_get_temp_dir(), 'wpcpm-lead-log-' );
$was_log           = ini_set( 'error_log', $log_file );
WPCPM_Institution_Leads::promote( $quietly, 'Info Sent', 7 );
ini_set( 'error_log', (string) $was_log );
$logged = (string) file_get_contents( $log_file );
unlink( $log_file );
ck( 'promote_logs_a_failed_create_by_key: the key is logged, never the name or the address', array( false !== strpos( $logged, $quietly ), false !== strpos( $logged, 'Example Timed Out School' ), false !== strpos( $logged, 'timedout.example' ) ), array( true, false, false ) );

echo "\n=== Two leads of one name, added at once ===\n";

$GLOBALS['held']['wpcpm_lead_lock_add'] = true;
ck( 'add_takes_its_lock: an add while another holds the adding lock is refused, and makes no post', array( said( WPCPM_Institution_Leads::add( array( 'name' => 'Example Held School' ), 7 ) ), WPCPM_Institution_Leads::name_taken( 'Example Held School' ) ), array( array( 'wpcpm_lead_busy', 'Another lead is being saved just now. Try again in a moment.' ), '' ) );
unset( $GLOBALS['held']['wpcpm_lead_lock_add'] );
ck( 'add_lock_lives_ten_seconds: the adding lock is taken for 10 seconds, a lead\'s own for 60', array( $GLOBALS['ttls']['wpcpm_lead_lock_add'] ?? null, $GLOBALS['ttls'][ WPCPM_Institution_Leads::LOCK_PREFIX . $locked_id ] ?? null ), array( 10, 60 ) );

// Fix wave, E5: a rename is checked and written under the adding lock, so it cannot race an add of that name.
$renamed = WPCPM_Institution_Leads::add( array( 'name' => 'Example Rename School' ), 7, false );
$GLOBALS['held']['wpcpm_lead_lock_add'] = true;
ck( 'rename_under_add_lock: a rename while another add holds the lock is refused in the busy sentence, and nothing is changed', array( said( WPCPM_Institution_Leads::update( $renamed, array( 'name' => 'Example Renamed School' ), 7 ) ), WPCPM_Institution_Leads::get( $renamed )['name'] ), array( array( 'wpcpm_lead_busy', 'Another lead is being saved just now. Try again in a moment.' ), 'Example Rename School' ) );
ck( 'the busy sentence is one string, written once', substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php' ), "'Another lead is being saved just now. Try again in a moment.'" ), 1 );
ck( 'and a change that renames nothing needs no lock', array( WPCPM_Institution_Leads::update( $renamed, array( 'next_step' => 'Write to the rector' ), 7 ), WPCPM_Institution_Leads::get( $renamed )['next_step'] ), array( true, 'Write to the rector' ) );
unset( $GLOBALS['held']['wpcpm_lead_lock_add'] );
ck( 'and with the lock free the rename is saved', array( WPCPM_Institution_Leads::update( $renamed, array( 'name' => 'Example Renamed School' ), 7 ), WPCPM_Institution_Leads::get( $renamed )['name'] ), array( true, 'Example Renamed School' ) );

echo "\n=== Names compared as the import compares them (Task 18 fix round 2) ===\n";

$spaced = WPCPM_Institution_Leads::add( array( 'name' => 'Szkoła  Dwóch   Spacji' ), 7, false );
ck( 'runs of spaces count as one: a double-spaced twin is refused', array( is_string( $spaced ), said( WPCPM_Institution_Leads::add( array( 'name' => ' szkoła dwóch spacji ' ), 7, false ) )[0], WPCPM_Institution_Leads::name_taken( 'Szkoła Dwóch Spacji' ) === $spaced ), array( true, 'wpcpm_lead_taken', true ) );
if ( function_exists( 'normalizer_normalize' ) ) {
	$composed = WPCPM_Institution_Leads::add( array( 'name' => 'Câmpia Școlii' ), 7, false );
	ck( 'and a decomposed twin of a composed name is refused', array( is_string( $composed ), said( WPCPM_Institution_Leads::add( array( 'name' => "Ca\u{0302}mpia S\u{0326}colii" ), 7, false ) )[0] ), array( true, 'wpcpm_lead_taken' ) );
}

echo "\n=== A lead is committed by its status (Task 18 fix round 1) ===\n";

// A lead the site adds: the insert, its fields, its source, who added it, then the one update to private.
$GLOBALS['ticks']      = 0;
$GLOBALS['kill_after'] = null;
$whole                 = WPCPM_Institution_Leads::add( array( 'name' => 'Szkoła Całości', 'source' => 'kill:whole' ), 7, false );
$whole_post            = get_post( (int) substr( (string) $whole, 5 ) );
ck( 'five writes, the last the move to private', array( $GLOBALS['ticks'], $whole_post->post_status ), array( 5, 'private' ) );

$killed = array();
for ( $at = 1; $at <= 4; $at++ ) {
	$name                  = 'Szkoła Przerwana ' . $at;
	$GLOBALS['ticks']      = 0;
	$GLOBALS['kill_after'] = $at;
	try {
		WPCPM_Institution_Leads::add( array( 'name' => $name, 'source' => 'kill:' . $at ), 7, false );
		$died = false;
	} catch ( Killed $e ) {
		$died = true;
	}
	$GLOBALS['kill_after'] = null;
	$listed                = array_values( array_filter( WPCPM_Institution_Leads::all(), static function ( $lead ) use ( $name ) { return $lead['name'] === $name; } ) );
	$seen                  = array( $died, $listed, WPCPM_Institution_Leads::by_source( 'kill:' . $at ), WPCPM_Institution_Leads::name_taken( $name ) );
	$again                 = WPCPM_Institution_Leads::add( array( 'name' => $name, 'source' => 'kill:' . $at ), 7, false );
	$killed[]              = array_merge( $seen, array( is_string( $again ) && WPCPM_Institution_Leads::is_key( $again ), WPCPM_Institution_Leads::by_source( 'kill:' . $at ) === $again, count( array_filter( WPCPM_Institution_Leads::all(), static function ( $lead ) use ( $name ) { return $lead['name'] === $name; } ) ) ) );
}
ck(
	'killed after the insert and after each meta: nothing is listed, the source and the name are not found, and the rerun adds one whole lead',
	$killed,
	array_fill( 0, 4, array( true, array(), '', '', true, true, 1 ) )
);
ck( 'an unfinished draft is no lead to get(), and uninstall still deletes it', array( WPCPM_Institution_Leads::get( 'lead-' . ( (int) substr( (string) $whole, 5 ) + 1 ) ), count( array_filter( $GLOBALS['posts'], static function ( $p ) { return 'wpcpm_inst_lead' === $p->post_type && 'draft' === $p->post_status; } ) ) ), array( null, 4 ) );

echo "\n=== Wired in ===\n";

$loader    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );
$uninstall = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'uninstall.php' );
$module    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );

ck( 'the loader and uninstall.php require the class', array( false !== strpos( $loader, "includes/modules/class-wpcpm-institution-leads.php'" ), false !== strpos( $uninstall, "includes/modules/class-wpcpm-institution-leads.php'" ) ), array( true, true ) );
ck(
	'the module boots the leads and its uninstall deletes them',
	array(
		1 === preg_match( '/public function boot\(\) \{.*?WPCPM_Institution_Leads::init\(\);/s', $module ),
		1 === preg_match( '/public function uninstall\(\) \{.*?WPCPM_Institution_Leads::delete_all\(\);/s', $module ),
	),
	array( true, true )
);
ck( 'uninstall.php sweeps the leads\' locks', 1 === preg_match( '/foreach \( array\([^;]*WPCPM_Institution_Leads::LOCK_PREFIX[^;]*\) as \$wpcpm_prefix \)/s', $uninstall ), true );

WPCPM_Institution_Leads::register_post_type();
$args = $GLOBALS['types']['wpcpm_inst_lead'] ?? array();
ck(
	'the post type is private, hidden, out of REST and search, never exported',
	array( $args['public'] ?? null, $args['publicly_queryable'] ?? null, $args['show_ui'] ?? null, $args['show_in_rest'] ?? null, $args['exclude_from_search'] ?? null, $args['can_export'] ?? null, $args['query_var'] ?? null, $args['rewrite'] ?? null ),
	array( false, false, false, false, true, false, false, false )
);
ck( 'no lead is inserted as publish', 1 === preg_match( "/'post_status'\s*=>\s*'publish'/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php' ) ), false );

echo "\n=== The leads an application names (the owner's wave, X2) ===\n";

$GLOBALS['on_site'] = true;
$GLOBALS['answer']  = 'recEXA0000000X201';
$x2_open   = WPCPM_Institution_Leads::add( array( 'name' => 'Wyższa  Szkoła Wnioskowa ', 'contact_email' => 'Rektor@Example.test' ), 7, false );
$x2_closed = WPCPM_Institution_Leads::add( array( 'name' => 'Universitatea Câmpiei Cererii', 'contact_email' => 'cerere@example.test' ), 7, false );
WPCPM_Institution_Leads::close( $x2_closed, 7 );
$x2_trash = WPCPM_Institution_Leads::add( array( 'name' => 'جامعة الطلب المتخيلة', 'contact_email' => 'talab@example.test' ), 7, false );
$GLOBALS['posts'][ (int) substr( $x2_trash, 5 ) ]->post_status = 'trash';
$x2_moved = WPCPM_Institution_Leads::add( array( 'name' => '架空申請学園', 'country' => 'recCTY0000000JP01', 'contact_email' => 'shinsei@example.test' ), 7, false );
WPCPM_Institution_Leads::promote( $x2_moved, 'First Contact Made', 7 );
ck( 'X2: a lead is found by its name folded as fold() folds it, the case and the spaces ignored', array_keys( WPCPM_Institution_Leads::matching( '  wyższa szkoła   WNIOSKOWA', '' ) ), array( $x2_open ) );
ck( 'X2: or by the application\'s address equal to its contact email, case ignored, on the address alone', array_keys( WPCPM_Institution_Leads::matching( 'Another Name Entirely', 'rektor@EXAMPLE.test' ) ), array( $x2_open ) );
ck( 'X2: a closed lead is found, and said to be closed', array( array_keys( WPCPM_Institution_Leads::matching( 'Universitatea Câmpiei Cererii', '' ) ), WPCPM_Institution_Leads::matching( 'Universitatea Câmpiei Cererii', '' )[ $x2_closed ]['closed'] !== '' ), array( array( $x2_closed ), true ) );
ck( 'X2: a lead in the Trash and a lead already moved into the talks are not found, by name or by address', array( WPCPM_Institution_Leads::matching( 'جامعة الطلب المتخيلة', 'talab@example.test' ), WPCPM_Institution_Leads::matching( '架空申請学園', 'shinsei@example.test' ) ), array( array(), array() ) );
ck( 'X2: both found when one matches by name and the other by address, oldest first', array_keys( WPCPM_Institution_Leads::matching( 'Wyższa Szkoła Wnioskowa', 'cerere@example.test' ) ), array( $x2_open, $x2_closed ) );
ck( 'X2: nothing for an empty name and an empty address', WPCPM_Institution_Leads::matching( '  ', '' ), array() );

echo "\n=== WordPress's personal data tools (the owner's wave, X3) ===\n";

$GLOBALS['filters'] = array();
WPCPM_Institution_Leads::init();
$x3_exporters = WPCPM_Institution_Leads::register_exporter( array( 'other' => array() ) );
$x3_erasers   = WPCPM_Institution_Leads::register_eraser( array() );
ck( 'X3: init() hooks both personal data tools, in either mode', array( $GLOBALS['filters']['wp_privacy_personal_data_exporters'] ?? null, $GLOBALS['filters']['wp_privacy_personal_data_erasers'] ?? null ), array( array( array( 'WPCPM_Institution_Leads', 'register_exporter' ) ), array( array( 'WPCPM_Institution_Leads', 'register_eraser' ) ) ) );
ck( 'X3: both are listed by WordPress\'s two filters as Institution leads, the others kept', array( array_keys( $x3_exporters ), $x3_exporters['wpcpm-institution-leads'] ?? null, $x3_erasers['wpcpm-institution-leads'] ?? null ), array( array( 'other', 'wpcpm-institution-leads' ), array( 'exporter_friendly_name' => 'Institution leads', 'callback' => array( 'WPCPM_Institution_Leads', 'export' ) ), array( 'eraser_friendly_name' => 'Institution leads', 'callback' => array( 'WPCPM_Institution_Leads', 'erase' ) ) ) );

$x3_open   = WPCPM_Institution_Leads::add( array( 'name' => 'Wyższa Szkoła Prywatności', 'city' => 'Gdańsk', 'contact_person' => 'Osoba Kontaktowa', 'contact_email' => 'osoba@example.test', 'next_step' => 'Call the dean', 'program_details' => 'Hours a week' ), 7, false );
$x3_closed = WPCPM_Institution_Leads::add( array( 'name' => 'Universitatea Câmpiei Datelor', 'contact_person' => 'Persoana <Doi>', 'contact_email' => 'OSOBA@Example.test' ), 7, false );
WPCPM_Institution_Leads::close( $x3_closed, 7 );
$x3_trash = WPCPM_Institution_Leads::add( array( 'name' => 'جامعة البيانات المتخيلة', 'contact_email' => 'osoba@example.test' ), 7, false );
$GLOBALS['posts'][ (int) substr( $x3_trash, 5 ) ]->post_status = 'trash';
$x3_other = WPCPM_Institution_Leads::add( array( 'name' => '架空個人情報大学', 'contact_person' => 'Tantō', 'contact_email' => 'tanto@example.test' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $x3_open, 'Osoba Kontaktowa asked for a call.', 7 );
WPCPM_Institution_Talk_Notes::add( $x3_other, 'A note about someone else.', 7 );

$x3_export = WPCPM_Institution_Leads::export( ' Osoba@EXAMPLE.test ' );
$x3_item   = $x3_export['data'][0] ?? array();
ck( 'X3: the exporter gives one item for each lead of that contact email, case ignored, a closed and a trashed one included', array( array_map( static function ( $item ) { return $item['item_id']; }, $x3_export['data'] ), $x3_export['done'] ), array( array( 'wpcpm-institution-leads-' . substr( $x3_open, 5 ), 'wpcpm-institution-leads-' . substr( $x3_closed, 5 ), 'wpcpm-institution-leads-' . substr( $x3_trash, 5 ) ), true ) );
ck( 'X3: in the group Institution leads, with the three rows Institution, Contact person and Contact\'s email, nothing of the notes, Program details or the talks', array( $x3_item['group_id'] ?? null, $x3_item['group_label'] ?? null, $x3_item['data'] ?? null, false !== strpos( (string) json_encode( $x3_export ), 'Call the dean' ) || false !== strpos( (string) json_encode( $x3_export ), 'Hours a week' ) || false !== strpos( (string) json_encode( $x3_export ), 'asked for a call' ) ), array( 'wpcpm-institution-leads', 'Institution leads', array( array( 'name' => 'Institution', 'value' => 'Wyższa Szkoła Prywatności' ), array( 'name' => 'Contact person', 'value' => 'Osoba Kontaktowa' ), array( 'name' => 'Contact\'s email', 'value' => 'osoba@example.test' ) ), false ) );
ck( 'X3: a value is escaped as the tool prints it', $x3_export['data'][1]['data'][1]['value'] ?? null, 'Persoana &lt;Doi&gt;' );
ck( 'X3: one lead for another address, none for an address no lead has', array( count( WPCPM_Institution_Leads::export( 'tanto@example.test' )['data'] ), WPCPM_Institution_Leads::export( 'nobody@example.test' ), WPCPM_Institution_Leads::export( '' ) ), array( 1, array( 'data' => array(), 'done' => true ), array( 'data' => array(), 'done' => true ) ) );

$x3_rows   = count( WPCPM_Institution_Audit::entries_for_lead( $x3_open, 0 ) ) + count( WPCPM_Institution_Audit::entries_for_lead( $x3_closed, 0 ) );
$x3_before = WPCPM_Institution_Leads::get( $x3_open );
$x3_erased = WPCPM_Institution_Leads::erase( 'osoba@example.test' );
$x3_after  = WPCPM_Institution_Leads::get( $x3_open );
$x3_trashed_fields = $GLOBALS['pmeta'][ (int) substr( $x3_trash, 5 ) ]['_wpcpm_lead_fields'] ?? array();
ck( 'X3: the eraser answers items_removed true, and says the one lead with notes keeps them', $x3_erased, array( 'items_removed' => true, 'items_retained' => true, 'messages' => array( 'The lead Wyższa Szkoła Prywatności keeps its notes, which may name this person. A note cannot be changed: to remove them, close and delete the lead.' ), 'done' => true ) );
ck( 'X3: it blanks the contact person and the contact\'s email, and nothing else', array( $x3_after['contact_person'], $x3_after['contact_email'], array_diff_key( $x3_after, array( 'contact_person' => 1, 'contact_email' => 1 ) ) === array_diff_key( $x3_before, array( 'contact_person' => 1, 'contact_email' => 1 ) ), WPCPM_Institution_Leads::get( $x3_closed )['contact_person'], $x3_trashed_fields['contact_email'] ?? null, $x3_trashed_fields['name'] ?? null ), array( '', '', true, '', '', 'جامعة البيانات المتخيلة' ) );
ck( 'X3: the leads, their talks and their notes stay; the closed lead stays closed; another address\'s lead is untouched', array( null !== WPCPM_Institution_Leads::get( $x3_open ), '' !== WPCPM_Institution_Leads::get( $x3_closed )['closed'], count( WPCPM_Institution_Talk_Notes::for_key( $x3_open ) ), WPCPM_Institution_Leads::get( $x3_other )['contact_email'] ), array( true, true, 1, 'tanto@example.test' ) );
ck( 'X3: the blanking writes no history row, so none carries the person\'s name or address', array( count( WPCPM_Institution_Audit::entries_for_lead( $x3_open, 0 ) ) + count( WPCPM_Institution_Audit::entries_for_lead( $x3_closed, 0 ) ), false !== stripos( (string) json_encode( $GLOBALS['opts'] ), 'osoba@example.test' ) ), array( $x3_rows, false ) );
ck( 'X3: a second run finds nothing', array( WPCPM_Institution_Leads::erase( 'osoba@example.test' ), WPCPM_Institution_Leads::export( 'osoba@example.test' )['data'] ), array( array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ), array() ) );

echo "\n=== Round 2 of the owner's wave: L1, L3, L2 ===\n";

$r2_markup = WPCPM_Institution_Leads::add( array( 'name' => 'Lab <b>North</b> & Co', 'contact_email' => 'markup@example.test' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $r2_markup, 'A note.', 7 );
ck( 'L1: the eraser\'s message holds a lead\'s name escaped, as WordPress\'s screen builds it as HTML', WPCPM_Institution_Leads::erase( 'markup@example.test' )['messages'], array( 'The lead Lab &lt;b&gt;North&lt;/b&gt; &amp; Co keeps its notes, which may name this person. A note cannot be changed: to remove them, close and delete the lead.' ) );

$r2_lead = WPCPM_Institution_Leads::add( array( 'name' => 'Wyższa Szkoła Zamkowa', 'contact_email' => 'zamek@example.test' ), 7, false );
$r2_lock = WPCPM_Institution_Leads::lock_name( $r2_lead );
$GLOBALS['held'][ $r2_lock ] = true;
$r2_meta = $GLOBALS['pmeta'][ (int) substr( $r2_lead, 5 ) ];
ck( 'L3: Close with the lead\'s lock held answers the lead\'s busy sentence and writes nothing', array( said( WPCPM_Institution_Leads::close( $r2_lead, 7 ) ), $GLOBALS['pmeta'][ (int) substr( $r2_lead, 5 ) ] === $r2_meta ), array( array( 'wpcpm_lead_busy', 'Somebody else is changing this lead. Try again in a while.' ), true ) );
ck( 'L3: the eraser leaves a lead whose lock is held as it is, counts it retained, says so, and is done', array( WPCPM_Institution_Leads::erase( 'zamek@example.test' ), WPCPM_Institution_Leads::get( $r2_lead )['contact_email'] ), array( array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( 'The lead Wyższa Szkoła Zamkowa was being saved and was left as it is. Run the erasure again.' ), 'done' => true ), 'zamek@example.test' ) );
$GLOBALS['held'] = array();
ck( 'L3: Close takes the lead\'s lock, and closes once it is free', array( WPCPM_Institution_Leads::close( $r2_lead, 7 ), array_key_exists( $r2_lock, $GLOBALS['ttls'] ) ), array( true, true ) );
$GLOBALS['held'][ $r2_lock ] = true;
ck( 'L3: Reopen alike', array( said( WPCPM_Institution_Leads::reopen( $r2_lead, 7 ) ), '' !== WPCPM_Institution_Leads::get( $r2_lead )['closed'] ), array( array( 'wpcpm_lead_busy', 'Somebody else is changing this lead. Try again in a while.' ), true ) );
$GLOBALS['held'] = array();
$GLOBALS['meta_fails_for'] = (int) substr( $r2_lead, 5 );
ck( 'L3: a write the site refuses blanks nothing either, and says the same', array( WPCPM_Institution_Leads::erase( 'zamek@example.test' )['messages'], WPCPM_Institution_Leads::get( $r2_lead )['contact_email'] ), array( array( 'The lead Wyższa Szkoła Zamkowa was being saved and was left as it is. Run the erasure again.' ), 'zamek@example.test' ) );
$GLOBALS['meta_fails_for'] = 0;
ck( 'L3: and the next run erases it', array( WPCPM_Institution_Leads::erase( 'zamek@example.test' )['items_removed'], WPCPM_Institution_Leads::get( $r2_lead )['contact_email'] ), array( true, '' ) );

// L2: the reviewer's sequence. A lead moved into the talks; an application typed otherwise (one space, the composed
// letters) is matched by the approval's search, WPCPM_Institutions_Index::matching(), as the leads' own search does.
$GLOBALS['on_site'] = true;
$GLOBALS['answer']  = 'recEXA0000000L201';
$r2_nfd   = "Universitatea Ca\u{0302}mpiei  Mutate";
$r2_moved = WPCPM_Institution_Leads::add( array( 'name' => $r2_nfd, 'country' => 'recCTY0000000JP01' ), 7, false );
ck( 'L2: the lead is moved into the talks', WPCPM_Institution_Leads::promote( $r2_moved, 'First Contact Made', 7 ), 'recEXA0000000L201' );
ck( 'L2: the application with one space and composed letters is matched by the approval\'s search to that institution', array_keys( WPCPM_Institutions_Index::matching( 'universitatea câmpiei mutate', '' ) ), array( 'recEXA0000000L201' ) );
ck( 'L2: one fold for both: the index\'s and the leads\'', array( WPCPM_Institutions_Index::fold( "  Foo \t University " ), WPCPM_Institutions_Index::fold( "Ca\u{0302}" ) ), array( 'foo university', 'câ' ) );

echo "\n=== O3: each critical section reads the lead from the table once its lock is held ===\n";

// Each time: this request reads the lead (the handler's first read fills the cache), another request then writes the
// table alone, and the method's read under the lead's lock must see that write.
$GLOBALS['on_site'] = true;
$GLOBALS['held']    = array();
$o3_fields          = static function ( $key ) {
	return $GLOBALS['pmeta'][ (int) substr( $key, 5 ) ][ WPCPM_Institution_Leads::META_FIELDS ];
};
$o3_elsewhere       = static function ( $key, array $change ) {
	$id = (int) substr( $key, 5 );
	$GLOBALS['pmeta'][ $id ][ WPCPM_Institution_Leads::META_FIELDS ] = array_merge( $GLOBALS['pmeta'][ $id ][ WPCPM_Institution_Leads::META_FIELDS ], $change );
};

$o3_close = WPCPM_Institution_Leads::add( array( 'name' => 'Example Closed Elsewhere' ), 7, false );
$GLOBALS['post_cache'] = true;
WPCPM_Institution_Leads::get( $o3_close );
$o3_elsewhere( $o3_close, array( 'closed' => '2026-11-18', 'next_step' => 'Closed by another request' ) );
ck( 'O3 close: a lead another request closed meanwhile is closed already, nothing written over it', array( WPCPM_Institution_Leads::close( $o3_close, 7 ), $o3_fields( $o3_close )['closed'], $o3_fields( $o3_close )['next_step'] ), array( false, '2026-11-18', 'Closed by another request' ) );
$GLOBALS['post_cache'] = false;

$o3_reopen = WPCPM_Institution_Leads::add( array( 'name' => 'Example Reopened Elsewhere' ), 7, false );
WPCPM_Institution_Leads::close( $o3_reopen, 7 );
$GLOBALS['post_cache'] = true;
WPCPM_Institution_Leads::get( $o3_reopen );
$o3_elsewhere( $o3_reopen, array( 'closed' => '', 'next_step' => 'Reopened by another request' ) );
ck( 'O3 reopen: a lead another request reopened meanwhile is open already, nothing written over it', array( WPCPM_Institution_Leads::reopen( $o3_reopen, 7 ), $o3_fields( $o3_reopen )['next_step'] ), array( false, 'Reopened by another request' ) );
$GLOBALS['post_cache'] = false;

$o3_promote = WPCPM_Institution_Leads::add( array( 'name' => 'Example Promoted Elsewhere', 'country' => 'recCTY0000000JP01' ), 7, false );
$GLOBALS['post_cache'] = true;
WPCPM_Institution_Leads::get( $o3_promote );
$o3_elsewhere( $o3_promote, array( 'closed' => '2026-11-18' ) );
$o3_created = count( $GLOBALS['created'] );
ck( 'O3 promote: a lead another request closed meanwhile is refused as closed, no create asked', array( said( WPCPM_Institution_Leads::promote( $o3_promote, 'First Contact Made', 7 ) )[0] ?? null, count( $GLOBALS['created'] ) - $o3_created ), array( 'wpcpm_lead_closed', 0 ) );
$GLOBALS['post_cache'] = false;

$o3_erase = WPCPM_Institution_Leads::add( array( 'name' => 'Example Erased Elsewhere', 'contact_email' => 'o3.erase@example.test', 'contact_person' => 'Example Person' ), 7, false );
$GLOBALS['post_cache'] = true;
WPCPM_Institution_Leads::get( $o3_erase );
$o3_elsewhere( $o3_erase, array( 'next_step' => 'Saved by another request' ) );
WPCPM_Institution_Leads::erase( 'o3.erase@example.test' );
ck( 'O3 erase: the blanking keeps what another request saved meanwhile', array( $o3_fields( $o3_erase )['next_step'], $o3_fields( $o3_erase )['contact_email'], $o3_fields( $o3_erase )['contact_person'] ), array( 'Saved by another request', '', '' ) );
$GLOBALS['post_cache'] = false;

echo "\n=== O7: a lead is shown only when all of it was written ===\n";

$o7_leads = static function () {
	return count( array_filter( $GLOBALS['posts'], static function ( $p ) { return 'wpcpm_inst_lead' === $p->post_type; } ) );
};
foreach ( array( WPCPM_Institution_Leads::META_FIELDS, WPCPM_Institution_Leads::META_SOURCE, WPCPM_Institution_Leads::META_ADDED_BY ) as $o7_key ) {
	$o7_before                 = $o7_leads();
	$GLOBALS['meta_fails_key'] = $o7_key;
	$o7_lead                   = WPCPM_Institution_Leads::add( array( 'name' => 'Example O7 Lead', 'source' => 'o7:' . $o7_key ), 7, false );
	$GLOBALS['meta_fails_key'] = '';
	ck( "o7_lead_{$o7_key}: a lead whose {$o7_key} write is refused is not kept: the refusal, no post left, the name free, the source not found", array( said( $o7_lead ), $o7_leads() - $o7_before, WPCPM_Institution_Leads::name_taken( 'Example O7 Lead' ), WPCPM_Institution_Leads::by_source( 'o7:' . $o7_key ) ), array( array( 'wpcpm_lead_write', 'The lead could not be kept. Try again in a while.' ), 0, '', '' ) );
}

$trashed = WPCPM_Institution_Leads::add( array( 'name' => 'Example Trashed School' ), 7, false );
$GLOBALS['posts'][ (int) substr( $trashed, 5 ) ]->post_status = 'trash';
$count = count( array_filter( $GLOBALS['posts'], static function ( $post ) { return 'wpcpm_inst_lead' === $post->post_type; } ) );
ck( 'delete_all() deletes every lead, a trashed one too, and says how many', array( WPCPM_Institution_Leads::delete_all(), count( array_filter( $GLOBALS['posts'], static function ( $post ) { return 'wpcpm_inst_lead' === $post->post_type; } ) ) ), array( $count, 0 ) );

echo "\n=== House rules ===\n";
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php' ) ), 0 );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
