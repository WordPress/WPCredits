<?php
/**
 * The import (Institutions off Airtable, release 2, Task 18): `wp wpcredits institutions_import` brings over what
 * Airtable alone holds of the talks, once, before the switch.
 *
 * What is pinned here and why:
 *
 * - **Refusals before anything:** in site mode, and while the last institutions sync is older than 30 minutes
 *   beside the read; and when Airtable refuses the record comments, with the sentence that names the scope.
 * - **What comes over to an institution:** the talks keys, the help desk link, the application's answers and the
 *   agreement's Drive link, only where the record has none; its Notes and Comments cells and its record comments as
 *   notes, signed and dated as the design says; one `talks_imported` row saying what came.
 * - **Assignments** by area, else by "Original program manager", with no email.
 * - **The Outreach Tracker:** a lead per row, assigned by area, else by its Lead Owner, the owner kept; a row whose
 *   name a school already has (promoted or not) is joined to that school, never refused and never lost; a promoted
 *   row with no match is a lead, named.
 * - **Nothing is cut, nothing is lost:** a 12,000-character cell is three notes in order; a broken byte is repaired
 *   and counted.
 * - **No school loses access by the switch:** the import never touches an agreement option or the index, and the
 *   Drive link the site-mode sync reads for each settled school is the one Airtable held.
 * - **Review Focus 5, twice is once:** `apply()` run twice, or run again after a stop, adds no note, comment, lead
 *   or tutor twice, overwrites nothing on the site, and sends no email.
 * - **The dry run** is the summary and writes nothing; `--save` then `--from` gives the same plan; `--save` refuses a
 *   repository or the site and writes mode 0600; `--skip-comments` brings the rest and a later run the comments.
 * - **The client** reads exactly the columns pinned against the fixtures, and nothing leaves the suite.
 *
 * Every name and record here is invented. Run from the plugin root:  php bin/test-institutions-move.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once __DIR__ . '/stubs/temp-dir.php';

$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['opts']      = array();
$GLOBALS['next']      = 5;
$GLOBALS['mails']     = array();
$GLOBALS['remote']    = array();
$GLOBALS['held']      = array();
$GLOBALS['managers']  = array( 11, 12 );
$GLOBALS['on_site']   = false;
$GLOBALS['last_sync'] = 1763546400;
$GLOBALS['die_on']    = null;

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_data() {
		return $this->data;
	}
}

class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_author;
	public $post_content  = '';
	public $post_title    = '';
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
	public $user_email;
	public $user_login;
	public function __construct( $id, $name, $email ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = 'u' . $id;
	}
	public function exists() {
		return true;
	}
}

/** What a stop part way looks like: a write that never returns. */
class Stopped extends Exception {}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function __( $text, $domain = null ) {
	return $text;
}
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function register_post_type( $type, $args ) {}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['opts'][ $key ] );
	return true;
}
function get_transient( $key ) {
	$GLOBALS['transients_read'][] = $key;
	return false;
}
function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['transients'][ $key ] = $value;
	return true;
}
function wp_cache_delete( $key, $group = '' ) {
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
function sanitize_email( $email ) {
	return trim( (string) $email );
}
function absint( $n ) {
	return abs( (int) $n );
}
function is_email( $email ) {
	return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
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
	return strtotime( $post->post_date_gmt . ' UTC' );
}
function update_meta_cache( $type, $ids ) {}
function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}
function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && 0 === strcasecmp( $user->user_email, (string) $value ) ) {
			return $user;
		}
	}
	return false;
}
function user_can( $user, $cap ) {
	$id = is_object( $user ) ? (int) $user->ID : (int) $user;
	return in_array( $id, $GLOBALS['managers'], true );
}
function get_current_user_id() {
	return 0;
}
/** The site's environment, as a check sets it: a live-like site unless it says `local`. */
function wp_get_environment_type() {
	return isset( $GLOBALS['env'] ) ? $GLOBALS['env'] : 'production';
}
function wp_slash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_slash', $value );
	}
	return is_string( $value ) ? addslashes( $value ) : $value;
}
function stripslashes_deep( $value ) {
	return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}
function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr = stripslashes_deep( $postarr );

	// A stop part way: the write a check names never lands, and the run ends there.
	if ( null !== $GLOBALS['die_on'] && call_user_func( $GLOBALS['die_on'], $postarr ) ) {
		$GLOBALS['die_on'] = null;
		throw new Stopped( 'stopped' );
	}

	$id                      = $GLOBALS['next']++;
	$postarr['ID']           = $id;
	$GLOBALS['posts'][ $id ] = new WP_Post( $postarr + array( 'post_date_gmt' => '2026-11-19 10:00:00' ) );
	return $id;
}
function wp_update_post( $postarr, $wp_error = false ) {
	$id = (int) $postarr['ID'];
	foreach ( $postarr as $k => $v ) {
		$GLOBALS['posts'][ $id ]->$k = $v;
	}
	return $id;
}
function get_post( $id ) {
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}
function get_post_meta( $id, $key = '', $single = false ) {
	return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : '';
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = stripslashes_deep( $value );
	return true;
}
function delete_post_meta( $id, $key ) {
	unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
	return true;
}
// As the leads' suite has it: the type, the statuses, one meta key and value, a meta_query, newest first for the log.
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
/** Poland routes to 11; Romania and Japan to nobody. */
class WPCPM_Institution_Areas {
	public static function administrator_for( $country_id ) {
		return 'recCTY0000000PL01' === (string) $country_id ? 11 : 0;
	}
}
class WPCPM_Settings {
	public static function get_value( $key, $default = null ) {
		$values = array(
			'institutions_on_site' => $GLOBALS['on_site'],
			'institutions_table'   => 'tblInstitutions00',
			'tutors_table'         => 'tbllW9yWhbg1UCHVy',
		);
		return array_key_exists( $key, $values ) ? $values[ $key ] : $default;
	}
}
class WPCPM_Settings_Choices {
	public static function manager_accounts() {
		$out = array();
		foreach ( $GLOBALS['managers'] as $id ) {
			$out[ $id ] = $GLOBALS['users'][ $id ]->display_name;
		}
		return $out;
	}
}
class WPCPM_Countries {
	public static function all() {
		return array(
			'recCTY0000000PL01' => array( 'name' => 'Poland' ),
			'recCTY0000000RO01' => array( 'name' => 'Romania' ),
			'recCTY0000000JP01' => array( 'name' => 'Japan' ),
		);
	}
	public static function name_of( $record_id ) {
		$all = self::all();
		return isset( $all[ (string) $record_id ] ) ? $all[ (string) $record_id ]['name'] : '';
	}
}
/** The last sync's read time, as a check sets it. */
class WPCPM_Institutions_Sync {
	public static function last_read() {
		return (int) $GLOBALS['last_sync'];
	}
	public static function start( $first = false, $restart = false ) {
		$GLOBALS['sync_started'] = true;
		return true;
	}
}
/** The copy, recorded: the import never calls it. */
class WPCPM_Institution_Copy {
	public static function create( array $row ) {
		$GLOBALS['copied'][] = array( 'create', $row );
		return new WP_Error( 'no', 'no' );
	}
	public static function send( $record_id, array $keys ) {
		$GLOBALS['copied'][] = array( 'send', $record_id, $keys );
		return false;
	}
}
class WPCPM_Lock {
	public static function take( $name, $ttl ) {
		return empty( $GLOBALS['held'][ $name ] ) ? 'token' : '';
	}
	public static function release( $name, $token ) {
		return true;
	}
}
class WPCPM_Institution_Request {
	const LOCK_TTL    = 300;
	const LOCK_PREFIX = 'wpcpm_inst_lock_';
	public static function school_lock_name( $record_id ) {
		return self::LOCK_PREFIX . $record_id;
	}
}
/** The mail capture: it stays empty. */
class WPCPM_Mail {
	public static function site_name() {
		return 'Education Directory';
	}
	public static function send( $user_id, $id, $build ) {
		$GLOBALS['mails'][] = array( $user_id, $id );
		return true;
	}
}
class WPCPM_Mail_Templates {
	public static function mail( $id, array $args ) {
		return $args;
	}
}
class WPCPM_Manager_Booking {
	public static function url_for( $id ) {
		return '';
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
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tutors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-seed.php';

if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-move.php' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-move.php';
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

if ( ! class_exists( 'WPCPM_Institutions_Move' ) ) {
	ck( 'the import\'s class exists', false, true );
	printf( "\n%d FAILED (%d checks)\n", $fails, $total );
	exit( 1 );
}

/** The client, stood in: it answers each table and each record's comments from the read below, and records every ask. */
class Move_Client extends WPCPM_Airtable {
	public $asked = array();
	public $refuse_comments = false;
	public $gone            = array();
	public function __construct() {
		parent::__construct( array( 'api_token' => 'patTEST', 'base_id' => 'appTEST' ) );
	}
	public function fetch_all( $table, array $args = array() ) {
		$this->asked[] = array( 'all', $table, isset( $args['fields'] ) ? $args['fields'] : array() );
		$parts         = array(
			'tblInstitutions00'          => 'institutions',
			WPCPM_Institution_Seed::TEAM_TABLE => 'team',
			WPCPM_Institutions_Move::OUTREACH_TABLE => 'outreach',
			'tbllW9yWhbg1UCHVy'          => 'tutors',
		);
		return $GLOBALS['airtable'][ $parts[ $table ] ];
	}
	public function fetch_comments( $table, $record_id ) {
		$this->asked[] = array( 'comments', $table, $record_id );
		if ( in_array( $record_id, $this->gone, true ) ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 404): NOT_FOUND', array( 'status' => 404 ) );
		}
		if ( $this->refuse_comments ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 403): Invalid permissions', array( 'status' => 403 ) );
		}
		return isset( $GLOBALS['airtable']['comments'][ $record_id ] ) ? $GLOBALS['airtable']['comments'][ $record_id ] : array();
	}
}

$s1 = 'recEXA00000000001';
$s2 = 'recEXA00000000002';
$s3 = 'recEXA00000000003';
$s5 = 'recEXA00000000005';
$u9 = 'recEXA00000000099';
$o1 = 'recOUT00000000001';
$o2 = 'recOUT00000000002';
$o3 = 'recOUT00000000003';
$o4 = 'recOUT00000000004';
$o5 = 'recOUT00000000005';

$GLOBALS['users'] = array(
	11 => new WP_User( 11, 'Piotr Admin', 'piotr@example.test' ),
	12 => new WP_User( 12, 'Mei Admin', 'mei@example.test' ),
	30 => new WP_User( 30, 'Not Staff', 'staff@example.test' ),
);

// 12,000 characters in paragraphs of about 1,000, so the cuts fall at a paragraph's end.
$paragraph = str_repeat( 'Rozmowa o programie trwała długo. ', 29 ) . 'Koniec akapitu.';
$long      = implode( "\n\n", array_fill( 0, 11, $paragraph ) );
$long      = $long . "\n\n" . str_repeat( 'x', 12000 - 2 - mb_strlen( $long, 'UTF-8' ) );
$broken    = "Notatka z bajtem \xC3\x28 po drodze.";

$GLOBALS['airtable'] = array(
	'institutions' => array(
		array(
			'id'     => $s1,
			'fields' => array(
				'Next Step'                => 'Send the agreement',
				'Follow-Up Date'           => '2026-12-01',
				'Notes'                    => 'Spoke with the dean in October.',
				'Comments'                 => 'Prefers email to calls.',
				'Department'               => 'Informatyka',
				'Original program manager' => array( 'recTEM00000000001' ),
			),
		),
		array(
			'id'     => $s2,
			'fields' => array(
				'Last Contact Date'                                   => '2026-02-30',
				'Estimated number of students who may be interested' => 25,
				'FreeScout'                                           => 'https://help.example.test/conversation/22',
				'Original program manager'                            => array( 'recTEM00000000001' ),
			),
		),
		array(
			'id'     => $s3,
			'fields' => array(
				'Notes'     => $long,
				'Next Step' => 'Not the site\'s value',
			),
		),
		array(
			'id'     => $u9,
			'fields' => array( 'Notes' => 'A school the index does not hold.' ),
		),
	),
	'team'         => array(
		array( 'id' => 'recTEM00000000001', 'fields' => array( 'Name' => 'Mei Admin', 'Email' => 'mei@example.test' ) ),
	),
	'outreach'     => array(
		array(
			'id'     => $o1,
			'fields' => array(
				'Ac'                         => 'Szkoła Leśna Przykładowa',
				'Country'                    => 'Romania',
				'City'                       => 'Câmpia',
				'Lead Owner'                 => 'piotr@example.test',
				'Date of last contact'       => '2026-10-05',
				'Notes'                      => 'Met at the fair.',
				'HS link (where applicable)' => 'https://help.example.test/conversation/1',
			),
		),
		array(
			'id'     => $o2,
			'fields' => array(
				'Ac'         => '架空川女子大学',
				'Country'    => 'Japan',
				'Lead Owner' => 'Hanako Nobody',
				'Notes'      => 'メモ。',
			),
		),
		array(
			'id'     => $o3,
			'fields' => array(
				'Ac'                         => '  UNIVERSITATEA   Câmpiei Rîușițeni ',
				'Lead Owner'                 => 'Mei Admin',
				'Notes'                      => 'Promoted after the call.',
				'HS link (where applicable)' => 'See [the thread](https://help.example.test/conversation/3) and the call notes.',
				'Promoted to Institutions'   => true,
			),
		),
		array(
			'id'     => $o4,
			'fields' => array(
				'Ac'                       => 'Instytut Nieistniejący',
				'Country'                  => 'Atlantis',
				'Notes'                    => $broken,
				'Promoted to Institutions' => true,
			),
		),
		array(
			'id'     => $o5,
			'fields' => array(
				'Ac'    => 'szkoła leśna  przykładowa',
				'Notes' => 'A second row for the same school.',
			),
		),
	),
	'tutors'       => array(
		array( 'id' => 'recTUT00000000001', 'fields' => array( 'Name' => 'Tess Tutor', 'Email' => 'tess@example.test', 'Institutions Onboarding' => array( $s1 ) ) ),
		array( 'id' => 'recTUT00000000002', 'fields' => array( 'Name' => 'Kept Tutor', 'Email' => 'kept@example.test', 'Institutions Onboarding' => array( $s3 ) ) ),
	),
	'comments'     => array(
		$s1 => array(
			array( 'id' => 'comEXA00000000001', 'text' => 'First record comment.', 'created' => '2026-10-01T09:30:00.000Z', 'author_name' => 'Ana Comment', 'author_email' => 'ana@example.test' ),
			array( 'id' => 'comEXA00000000002', 'text' => 'Second record comment.', 'created' => '2026-10-02T10:00:00.000Z', 'author_name' => '', 'author_email' => 'bo@example.test' ),
		),
		$o1 => array(
			array( 'id' => 'comEXA00000000003', 'text' => 'A comment on the lead.', 'created' => '2026-10-06T08:00:00.000Z', 'author_name' => 'Ana Comment', 'author_email' => '' ),
		),
	),
);

$drive = 'https://drive.google.com/file/d/EXAMPLE0000001/view';

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

/** The site before the import: the index the last sync wrote, one settled agreement, one assigned school with a tutor. */
function fresh_site() {
	global $s1, $s2, $s3, $s5, $drive;

	$GLOBALS['posts']  = array();
	$GLOBALS['pmeta']  = array();
	$GLOBALS['opts']   = array();
	$GLOBALS['next']   = 5;
	$GLOBALS['mails']  = array();
	$GLOBALS['remote'] = array();
	$GLOBALS['copied'] = array();
	$GLOBALS['held']   = array();
	unset( $GLOBALS['sync_started'], $GLOBALS['transients'] );

	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array(
		'v'    => WPCPM_Institutions_Index::VERSION,
		'read' => 1763546400,
		'rows' => array(
			$s1 => row( $s1, 'Wyższa Szkoła Górnołąćka', 'Confirmed', array( 'country' => 'recCTY0000000PL01', 'country_name' => 'Poland' ) ),
			$s2 => row( $s2, 'Universitatea Câmpiei Rîușițeni', 'Agreement Sent', array( 'country' => 'recCTY0000000RO01', 'country_name' => 'Romania' ) ),
			$s3 => row( $s3, 'جامعة الواحة الخضراء المتخيلة', '' ),
			$s5 => row( $s5, '架空山学園大学', 'Rejected', array( 'country' => 'recCTY0000000JP01', 'country_name' => 'Japan' ) ),
		),
	);
	$GLOBALS['opts'][ WPCPM_Institution_Agreement::option_name( $s1 ) ] = array(
		'v'               => WPCPM_Institution_Agreement::VERSION,
		'settled'         => true,
		'site_state'      => 'accepted',
		'airtable_status' => 'Accepted',
		'drive_url'       => $drive,
	);

	WPCPM_Institutions_Index::forget_cached();
	WPCPM_Institution_Record::set( $s3, array( 'next_step' => 'The site\'s own next step', 'assigned' => 12, 'tutors' => array( array( 'name' => 'Kept Tutor', 'email' => 'kept@example.test' ) ) ) );
	WPCPM_Institution_Record::forget_cached( $s3 );
}

/** Everything the import may write, in a shape two runs can be compared by. */
function state() {
	global $s1, $s2, $s3, $s5;

	$notes = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( WPCPM_Institution_Talk_Notes::POST_TYPE === $post->post_type ) {
			$notes[] = array(
				get_post_meta( $id, WPCPM_Institution_Talk_Notes::META_FOR, true ),
				get_post_meta( $id, WPCPM_Institution_Talk_Notes::META_SOURCE, true ),
				get_post_meta( $id, WPCPM_Institution_Talk_Notes::META_AUTHOR_NAME, true ),
				$post->post_date_gmt,
				get_post_meta( $id, WPCPM_Institution_Talk_Notes::META_TEXT, true ),
			);
		}
	}
	sort( $notes );

	$leads = array();
	foreach ( WPCPM_Institution_Leads::all() as $lead ) {
		unset( $lead['key'] );
		$leads[ $lead['source'] ] = $lead;
	}
	ksort( $leads );

	$records = array();
	foreach ( array( $s1, $s2, $s3, $s5 ) as $id ) {
		WPCPM_Institution_Record::forget_cached( $id );
		$records[ $id ] = WPCPM_Institution_Record::get( $id );
	}

	$rows = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( WPCPM_Institution_Audit::POST_TYPE === $post->post_type ) {
			$key    = get_post_meta( $id, WPCPM_Institution_Audit::META_INSTITUTION, true );
			$lead   = get_post_meta( $id, WPCPM_Institution_Audit::META_LEAD, true );
			$rows[] = array( get_post_meta( $id, WPCPM_Institution_Audit::META_KIND, true ), '' !== $key ? $key : 'lead of ' . WPCPM_Institution_Leads::get( $lead )['source'] );
		}
	}
	sort( $rows );

	return array(
		'notes'   => $notes,
		'leads'   => $leads,
		'records' => $records,
		'index'   => $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ],
		'option'  => $GLOBALS['opts'][ WPCPM_Institution_Agreement::option_name( $s1 ) ],
		'rows'    => $rows,
	);
}

/** Each school's history rows, kind and message, as the readers list them. */
function rows_of( $key ) {
	$entries = WPCPM_Institution_Leads::is_key( $key ) ? WPCPM_Institution_Audit::entries_for_lead( $key, 0 ) : WPCPM_Institution_Audit::entries_for( $key, 0 );
	return array_map(
		static function ( $entry ) {
			return array( $entry['kind'], $entry['message'] );
		},
		$entries
	);
}

function notes_of( $key ) {
	return array_map(
		static function ( $note ) {
			return array( $note['author'], $note['date'], $note['text'] );
		},
		array_reverse( WPCPM_Institution_Talk_Notes::for_key( $key ) )
	);
}

function said( $answer ) {
	return is_wp_error( $answer ) ? array( $answer->get_error_code(), $answer->get_error_message() ) : $answer;
}

echo "=== The names, and the columns against the fixtures ===\n";

$outreach_fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/outreach-tracker-fields.json' ), true );
$inst_fixture     = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/institutions-table-fields.json' ), true );

ck( 'the Outreach Tracker table, the history kind', array( WPCPM_Institutions_Move::OUTREACH_TABLE, WPCPM_Institutions_Move::LOG_IMPORTED ), array( 'tblQssm68GvGEfAEH', 'talks_imported' ) );
ck( 'the Outreach columns are read by what each becomes, with no contact person', array_keys( WPCPM_Institutions_Move::OUTREACH_COLUMNS ), array( 'name', 'city', 'country', 'notes', 'last_contact', 'lead_owner', 'help_desk', 'promoted' ) );
ck( 'every Outreach column is the fixture\'s', array_values( array_diff( WPCPM_Institutions_Move::OUTREACH_COLUMNS, $outreach_fixture['fields'] ) ), array() );
ck( 'every Institutions column is the fixture\'s, and Agreement Document is not read', array( array_values( array_diff( WPCPM_Institutions_Move::INSTITUTION_COLUMNS, $inst_fixture['fields'] ) ), in_array( 'Agreement Document', WPCPM_Institutions_Move::INSTITUTION_COLUMNS, true ) ), array( array(), false ) );
ck( 'nothing the sync reads is read again: no name, stage, country, contact or agreement column', array_values( array_intersect( WPCPM_Institutions_Move::INSTITUTION_COLUMNS, array( 'Name', 'Current Stage', 'Country', 'City', 'Website', 'Contact Person', 'Contact Email', 'Confirmed on', 'Agreement Status', 'Agreement Document' ) ) ), array() );

echo "\n=== Refusals, before any read or write ===\n";

fresh_site();
$client             = new Move_Client();
$GLOBALS['on_site'] = true;
ck( 'in site mode', array( said( WPCPM_Institutions_Move::read( $client ) ), $client->asked ), array( array( 'wpcpm_import_on_site', 'The site already keeps the record. The import runs before the switch.' ), array() ) );
$GLOBALS['on_site'] = false;

$GLOBALS['last_sync'] = time() - 1801;
ck( 'with the last sync older than 30 minutes', array( said( WPCPM_Institutions_Move::read( $client ) ), $client->asked ), array( array( 'wpcpm_import_stale', 'Run the institutions sync first: its last read is older than 30 minutes.' ), array() ) );
$GLOBALS['last_sync'] = time() - 60;

$client->refuse_comments = true;
$refused                 = WPCPM_Institutions_Move::read( $client );
ck(
	'a token without the comments scope stops the import, dry run included, before anything is written',
	array( said( $refused ), count( $GLOBALS['posts'] ) ),
	array( array( 'wpcpm_import_comments', 'Airtable refused the record comments, so nothing was brought over. Give the token the scope data.recordComments:read, or run with --skip-comments and bring the comments over later.' ), 1 )
);
$client->refuse_comments = false;

echo "\n=== The read ===\n";

$client = new Move_Client();
$read   = WPCPM_Institutions_Move::read( $client );
ck(
	'four tables, each once, by the columns pinned above',
	array_slice( $client->asked, 0, 4 ),
	array(
		array( 'all', 'tblInstitutions00', array_values( WPCPM_Institutions_Move::INSTITUTION_COLUMNS ) ),
		array( 'all', WPCPM_Institution_Seed::TEAM_TABLE, array( 'Name', 'Email' ) ),
		array( 'all', 'tblQssm68GvGEfAEH', array_values( WPCPM_Institutions_Move::OUTREACH_COLUMNS ) ),
		array( 'all', 'tbllW9yWhbg1UCHVy', array( 'Name', 'Email', 'Institutions Onboarding' ) ),
	)
);
ck( 'then the comments of every row of the two tables, each once', array_column( array_slice( $client->asked, 4 ), 2 ), array( $s1, $s2, $s3, $u9, $o1, $o2, $o3, $o4, $o5 ) );
ck( 'the read keeps what came, and the comments of the rows that have some', array( $read['v'], $read['comments_read'], array_keys( $read['comments'] ), count( $read['outreach'] ) ), array( 1, true, array( $s1, $o1 ), 5 ) );
ck( 'nothing of the read is kept in an option or a transient', array( array_keys( $GLOBALS['opts'] ), isset( $GLOBALS['transients'] ) ), array( array( WPCPM_Institutions_Index::OPT_NAME, WPCPM_Institution_Agreement::option_name( $s1 ) ), false ) );

echo "\n=== The plan, and the dry run's summary ===\n";

$before = state();
$plan   = WPCPM_Institutions_Move::plan( $read );
ck( 'plan() writes nothing', state(), $before );

$summary = WPCPM_Institutions_Move::summary( $plan );
echo "\n--- the summary as the dry run prints it ---\n" . implode( "\n", $summary ) . "\n---\n\n";

ck(
	'the summary, line by line',
	$summary,
	array(
		'Read on ' . gmdate( 'Y-m-d H:i', $read['read'] ) . ' UTC: 4 institutions, 5 rows of the Outreach Tracker and 2 rows of the Tutors table.',
		'The record comments were read: 3 comments.',
		'Not in the site\'s list of institutions, so nothing of it comes over: recEXA00000000099.',
		'Schools by stage: Agreement Sent 1, Confirmed 1, Rejected 1, no stage 1.',
		'Notes that come over: 5 from the Notes, Comments and FreeScout columns, 3 record comments and 6 from the Outreach Tracker.',
		'Follow-ups: 1 date comes over.',
		'Filled where the site has none: next step 1, estimated students 1, help desk link 2, lead owner 1, agreement\'s Drive link 1, the application\'s answers 1.',
		'Institutions assigned: 1 by its country\'s area, 1 by the person who first handled it.',
		'Leads: 3 come over.',
		'Lead: Szkoła Leśna Przykładowa, assigned to Piotr Admin by its Lead Owner.',
		'Lead: 架空川女子大学, not assigned.',
		'Lead: Instytut Nieistniejący, not assigned.',
		'Promoted and matched: UNIVERSITATEA Câmpiei Rîușițeni is Universitatea Câmpiei Rîușițeni (recEXA00000000002).',
		'Promoted but matched to no institution, so it comes over as a lead to merge by hand: Instytut Nieistniejący.',
		'Joined to an existing school: 1 row of the Outreach Tracker.',
		'Joined to an existing school: szkoła leśna przykładowa, to Szkoła Leśna Przykładowa (a lead of this run).',
		'Tutors: 1 is added to a school\'s list.',
		'1 text held characters that are not valid UTF-8, and comes over repaired.',
		'Left out: the Last Contact Date of Universitatea Câmpiei Rîușițeni, "2026-02-30", is not a real day.',
		'Not resolved: the Lead Owner "Hanako Nobody" of 架空川女子大学 is no Administrator here, so the lead is not assigned and the name is kept as its lead owner.',
		'Not resolved: the country "Atlantis" of Instytut Nieistniejący is not one the site knows, so it is kept as text.',
	)
);

echo "\n=== apply() ===\n";

$done = WPCPM_Institutions_Move::apply( $plan );
ck( 'what was written', $done, array( 'notes' => 14, 'fields' => 7, 'assigned_area' => 1, 'assigned_original' => 1, 'leads' => 3, 'joined' => 2, 'tutors' => 1, 'failed' => array() ) );
ck( 'and as the command says it', WPCPM_Institutions_Move::written( $done ), 'Written: 14 notes; 7 fields filled; 2 institutions assigned; 3 leads; 2 rows joined to an existing school; 1 tutor.' );

WPCPM_Institution_Record::forget_cached( $s1 );
$r1 = WPCPM_Institution_Record::get( $s1 );
ck(
	'the institution with everything: the talks keys, the answers and the Drive link where empty, assigned by its area',
	array( $r1['next_step'], $r1['follow_up'], $r1['application_answers'], $r1['agreement_document'], $r1['assigned'] ),
	array( 'Send the agreement', '2026-12-01', array( 'department' => 'Informatyka' ), $drive, 11 )
);
ck(
	'its four notes, oldest first: each record comment with its own date and author, the two columns dated the import day',
	notes_of( $s1 ),
	array(
		array( 'Ana Comment', '2026-10-01 09:30:00', 'First record comment.' ),
		array( 'Imported from Airtable (a record comment)', '2026-10-02 10:00:00', 'Second record comment.' ),
		array( 'Imported from Airtable', '2026-11-19 10:00:00', 'Spoke with the dean in October.' ),
		array( 'Imported from Airtable (Comments column)', '2026-11-19 10:00:00', 'Prefers email to calls.' ),
	)
);
ck(
	'one talks_imported row saying what came, beside the assignment\'s and the tutor\'s own',
	rows_of( $s1 ),
	array(
		array( 'tutor_added', 'Tess Tutor was added to the tutors.' ),
		array( 'talks_imported', 'Brought over from Airtable: 4 notes, the next step, the follow-up, the agreement\'s Drive link and the application\'s answers.' ),
		array( 'assignment_changed', 'Piotr Admin is now the assigned Administrator.' ),
	)
);

WPCPM_Institution_Record::forget_cached( $s2 );
$r2 = WPCPM_Institution_Record::get( $s2 );
ck(
	'the second: assigned by Original program manager, and the promoted row joined to it, its owner the lead owner, its link the first one Airtable gave',
	array( $r2['assigned'], $r2['estimated_students'], $r2['help_desk'], $r2['lead_owner'], isset( $r2['last_contact'] ) ),
	array( 12, '25', 'https://help.example.test/conversation/22', 'Mei Admin', false )
);
ck(
	'its notes: the promoted row\'s, and the HS link cell, which held more than a link, in full',
	notes_of( $s2 ),
	array(
		array( 'Imported from the Outreach Tracker', '2026-11-19 10:00:00', 'Promoted after the call.' ),
		array( 'Imported from the Outreach Tracker (HS link column)', '2026-11-19 10:00:00', 'See [the thread](https://help.example.test/conversation/3) and the call notes.' ),
	)
);
ck(
	'its rows: Airtable\'s part, then the Outreach Tracker\'s',
	rows_of( $s2 ),
	array(
		array( 'talks_imported', 'Brought over from the Outreach Tracker: 2 notes and the lead owner.' ),
		array( 'talks_imported', 'Brought over from Airtable: the estimated students and the help desk link.' ),
		array( 'assignment_changed', 'Mei Admin is now the assigned Administrator.' ),
	)
);

WPCPM_Institution_Record::forget_cached( $s3 );
$r3    = WPCPM_Institution_Record::get( $s3 );
$long3 = notes_of( $s3 );
ck( 'the third keeps the site\'s next step and its Administrator', array( $r3['next_step'], $r3['assigned'] ), array( 'The site\'s own next step', 12 ) );
ck(
	'its 12,000-character Notes cell is three notes in order, each within the limit, cut at a paragraph\'s end, nothing lost but the space at a cut',
	array(
		count( $long3 ),
		max( array_map( static function ( $n ) { return mb_strlen( $n[2], 'UTF-8' ); }, $long3 ) ) <= 5000,
		implode( '', array_map( static function ( $n ) { return preg_replace( '/\s+/u', '', $n[2] ); }, $long3 ) ) === preg_replace( '/\s+/u', '', $long ),
		'Koniec akapitu.' === mb_substr( $long3[0][2], -15, null, 'UTF-8' ),
	),
	array( 3, true, true, true )
);
ck(
	'their sources say which part each is',
	array_values( array_filter( array_column( state()['notes'], 1 ), static function ( $source ) { return 0 === strpos( $source, 'airtable-notes:recEXA00000000003' ); } ) ),
	array( 'airtable-notes:recEXA00000000003:1', 'airtable-notes:recEXA00000000003:2', 'airtable-notes:recEXA00000000003:3' )
);
ck( 'and its tutor stays listed once', WPCPM_Institution_Tutors::list_for( $s3 ), array( array( 'name' => 'Kept Tutor', 'email' => 'kept@example.test' ) ) );
ck( 'the new tutor joins its school\'s list', WPCPM_Institution_Tutors::list_for( $s1 ), array( array( 'name' => 'Tess Tutor', 'email' => 'tess@example.test' ) ) );
ck( 'the school the index does not hold has nothing', array( WPCPM_Institution_Talk_Notes::for_key( $u9 ), WPCPM_Institution_Record::get( $u9 ) ), array( array(), array() ) );

$leads = array();
foreach ( WPCPM_Institution_Leads::all() as $key => $lead ) {
	$leads[ $lead['source'] ] = $lead + array( 'notes' => notes_of( $key ), 'rows' => rows_of( $key ) );
}
ck( 'three leads, one per row that is no school yet', array_keys( $leads ), array( 'outreach:' . $o1, 'outreach:' . $o2, 'outreach:' . $o4 ) );
$l1 = $leads[ 'outreach:' . $o1 ];
ck(
	'the owned row: assigned to its owner\'s account, its country resolved, its last contact, link and owner kept',
	array( $l1['name'], $l1['assigned'], $l1['country'], $l1['country_text'], $l1['city'], $l1['last_contact'], $l1['help_desk'], $l1['lead_owner'] ),
	array( 'Szkoła Leśna Przykładowa', 11, 'recCTY0000000RO01', '', 'Câmpia', '2026-10-05', 'https://help.example.test/conversation/1', 'piotr@example.test' )
);
ck(
	'its notes: its own, its record comment, and the second row of the same school, joined to it',
	$l1['notes'],
	array(
		array( 'Ana Comment', '2026-10-06 08:00:00', 'A comment on the lead.' ),
		array( 'Imported from the Outreach Tracker', '2026-11-19 10:00:00', 'Met at the fair.' ),
		array( 'Imported from the Outreach Tracker', '2026-11-19 10:00:00', 'A second row for the same school.' ),
	)
);
ck(
	'and its rows say so',
	$l1['rows'],
	array(
		array( 'talks_imported', 'Brought over from the Outreach Tracker: 1 note.' ),
		array( 'talks_imported', 'Brought over from the Outreach Tracker: 2 notes.' ),
	)
);
$l2 = $leads[ 'outreach:' . $o2 ];
ck( 'the Japan row: unassigned, its owner kept as the lead owner, its note', array( $l2['assigned'], $l2['country'], $l2['lead_owner'], $l2['notes'] ), array( 0, 'recCTY0000000JP01', 'Hanako Nobody', array( array( 'Imported from the Outreach Tracker', '2026-11-19 10:00:00', 'メモ。' ) ) ) );
$l4 = $leads[ 'outreach:' . $o4 ];
ck(
	'the unmatched promoted row is a lead, its country kept as text, its broken byte repaired and never dropped',
	array( $l4['name'], $l4['country'], $l4['country_text'], $l4['notes'] ),
	array( 'Instytut Nieistniejący', '', 'Atlantis', array( array( 'Imported from the Outreach Tracker', '2026-11-19 10:00:00', "Notatka z bajtem \u{FFFD}( po drodze." ) ) )
);
$addresses = array();
foreach ( $GLOBALS['posts'] as $id => $post ) {
	if ( WPCPM_Institution_Talk_Notes::POST_TYPE === $post->post_type ) {
		foreach ( $GLOBALS['pmeta'][ $id ] as $meta => $value ) {
			if ( false !== strpos( (string) $value, '@' ) ) {
				$addresses[] = $meta;
			}
		}
	}
}
ck( 'no stored note\'s text or meta holds an address: a comment with no author\'s name is signed generically (M4)', $addresses, array() );
ck( 'no email, no request, no copy, no sync', array( $GLOBALS['mails'], $GLOBALS['remote'], $GLOBALS['copied'], isset( $GLOBALS['sync_started'] ) ), array( array(), array(), array(), false ) );

echo "\n=== No school loses access by the switch ===\n";

ck( 'the import changed no agreement option and no index row', array( state()['option'], state()['index'] ), array( $before['option'], $before['index'] ) );
ck(
	'every settled school\'s Drive link, as the site-mode sync reads it (the record\'s, else the option\'s), is the one Airtable held',
	array( WPCPM_Institution_Agreement::record_document( $s1 ), WPCPM_Institution_Agreement::option( $s1 )['drive_url'], WPCPM_Institution_Agreement::option( $s1 )['settled'] ),
	array( $drive, $drive, true )
);

echo "\n=== Review Focus 5: twice is once ===\n";

$once   = state();
$again  = WPCPM_Institutions_Move::plan( $read );
$second = WPCPM_Institutions_Move::apply( $again );
ck( 'a second run writes nothing', $second, array( 'notes' => 0, 'fields' => 0, 'assigned_area' => 0, 'assigned_original' => 0, 'leads' => 0, 'joined' => 0, 'tutors' => 0, 'failed' => array() ) );
ck( 'and the site is as one run left it, the mail capture empty', array( state(), $GLOBALS['mails'] ), array( $once, array() ) );
$again_summary = WPCPM_Institutions_Move::summary( $again );
ck(
	'its dry run says nothing comes over and why',
	array_values( array_intersect( $again_summary, array( 'Notes that come over: 0 from the Notes, Comments and FreeScout columns, 0 record comments and 0 from the Outreach Tracker.', '14 notes are on the site already and are not brought again.', 'Leads: 0 come over.', 'Joined to an existing school: 0 rows of the Outreach Tracker.', 'Tutors: 0 are added to their schools\' lists.', 'No institution is assigned.' ) ) ),
	array( 'Notes that come over: 0 from the Notes, Comments and FreeScout columns, 0 record comments and 0 from the Outreach Tracker.', '14 notes are on the site already and are not brought again.', 'No institution is assigned.', 'Leads: 0 come over.', 'Joined to an existing school: 0 rows of the Outreach Tracker.', 'Tutors: 0 are added to their schools\' lists.' )
);

// A stop as the third institution's first note is written: then the whole run again.
fresh_site();
$plan             = WPCPM_Institutions_Move::plan( $read );
$GLOBALS['die_on'] = static function ( $post ) use ( $s3 ) {
	return WPCPM_Institution_Talk_Notes::POST_TYPE === $post['post_type'] && $s3 === $post['post_title'];
};
try {
	WPCPM_Institutions_Move::apply( $plan );
	$stopped = false;
} catch ( Stopped $e ) {
	$stopped = true;
}
$half = state();
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
ck( 'a run stopped after the second institution, then run again, ends as one whole run', array( $stopped, count( $half['notes'] ) < count( $once['notes'] ), state() ), array( true, true, $once ) );
ck( 'with no email', $GLOBALS['mails'], array() );

// A stop in the middle of the Outreach Tracker: after a lead is added, before its notes.
fresh_site();
$plan              = WPCPM_Institutions_Move::plan( $read );
$GLOBALS['die_on'] = static function ( $post ) {
	return WPCPM_Institution_Talk_Notes::POST_TYPE === $post['post_type'] && 0 === strpos( $post['post_title'], 'lead-' );
};
try {
	WPCPM_Institutions_Move::apply( $plan );
	$stopped = false;
} catch ( Stopped $e ) {
	$stopped = true;
}
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
$resumed = state();
ck(
	'a run stopped after a lead is added and before its notes, run again, has every note, lead and field once',
	array( $stopped, $resumed['notes'], $resumed['leads'], $resumed['records'] ),
	array( true, $once['notes'], $once['leads'], $once['records'] )
);

echo "\n=== Nothing on the site is overwritten ===\n";

fresh_site();
WPCPM_Institution_Record::set( $s1, array( 'follow_up' => '2027-01-15', 'assigned' => 0 ) );
WPCPM_Institution_Record::forget_cached( $s1 );
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
WPCPM_Institution_Record::forget_cached( $s1 );
$r1 = WPCPM_Institution_Record::get( $s1 );
ck( 'a follow-up the site holds, and Nobody chosen on the site, stay', array( $r1['follow_up'], $r1['assigned'], $r1['next_step'] ), array( '2027-01-15', 0, 'Send the agreement' ) );

echo "\n=== --skip-comments, then a run with comments ===\n";

fresh_site();
$client = new Move_Client();
$bare   = WPCPM_Institutions_Move::read( $client, array( 'comments' => false ) );
ck( 'no comment is asked for', count( array_filter( $client->asked, static function ( $ask ) { return 'comments' === $ask[0]; } ) ), 0 );
$bare_plan = WPCPM_Institutions_Move::plan( $bare );
ck( 'the summary says the comments were not read', WPCPM_Institutions_Move::summary( $bare_plan )[1], 'The record comments were not read (--skip-comments). A later run without it brings them over, and nothing else twice.' );
WPCPM_Institutions_Move::apply( $bare_plan );
$without = count( state()['notes'] );
$later   = WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
ck( 'everything else came, and the later run adds only the three comments', array( $without, $later['notes'], $later['leads'], $later['fields'], $later['tutors'], count( state()['notes'] ) ), array( 11, 3, 0, 0, 0, 14 ) );
ck( 'the later run\'s row says what it brought', rows_of( $s1 )[0], array( 'talks_imported', 'Brought over from Airtable: 2 notes.' ) );

echo "\n=== --save and --from ===\n";

fresh_site();
$dir  = wpcpm_test_temp_dir();
$file = $dir . 'read.json';
ck( 'a saved read is written', WPCPM_Institutions_Move::save( $read, $file ), true );
ck( 'for its owner alone', substr( sprintf( '%o', fileperms( $file ) ), -4 ), '0600' );
$from = WPCPM_Institutions_Move::read_file( $file );
$from_plan = WPCPM_Institutions_Move::plan( $from );
$read_plan = WPCPM_Institutions_Move::plan( $read );
ck( 'read back, it is marked saved, whatever the file says', array( $from_plan['saved'], $read_plan['saved'] ), array( true, false ) );
unset( $from_plan['saved'], $read_plan['saved'] );
ck( 'and gives the same plan as the read itself', $from_plan, $read_plan );

$sentence = 'That file would sit in a repository or on the site. Save it under ~/Projects/wpcredits-airtable-exit/ instead.';
ck( 'refused inside the plugin\'s folder, and nothing written', array( said( WPCPM_Institutions_Move::save( $read, __DIR__ . '/fixtures/read.json' ) ), file_exists( __DIR__ . '/fixtures/read.json' ) ), array( array( 'wpcpm_import_save_place', $sentence ), false ) );
mkdir( $dir . 'repo/.git', 0700, true );
mkdir( $dir . 'repo/exports', 0700, true );
ck( 'refused inside any git work tree', said( WPCPM_Institutions_Move::save( $read, $dir . 'repo/exports/read.json' ) ), array( 'wpcpm_import_save_place', $sentence ) );
ck( 'refused in a folder that does not exist', said( WPCPM_Institutions_Move::save( $read, $dir . 'nowhere/read.json' ) )[0], 'wpcpm_import_save_folder' );
file_put_contents( $dir . 'other.json', '{"v":1}' );
ck( 'a file that is no saved read is refused', said( WPCPM_Institutions_Move::read_file( $dir . 'other.json' ) ), array( 'wpcpm_import_file', 'That file is not a read the import saved.' ) );

$GLOBALS['last_sync'] = $read['read'] - 1801;
ck( 'a saved read 30 minutes newer than the site\'s last sync is refused', said( WPCPM_Institutions_Move::plan( $from ) ), array( 'wpcpm_import_saved_stale', 'That saved read is not from this site\'s last sync. Read Airtable again.' ) );
$GLOBALS['on_site'] = true;
ck( 'and apply() asks again: in site mode it writes nothing', array( said( WPCPM_Institutions_Move::apply( $plan ) )[0], count( state()['notes'] ) ), array( 'wpcpm_import_on_site', 0 ) );
$GLOBALS['on_site']   = false;
$GLOBALS['last_sync'] = time() - 60;

echo "\n=== The gate holds both ways (M2) ===\n";

fresh_site();
$GLOBALS['env']       = 'production';
$GLOBALS['last_sync'] = $read['read'];
$refusal              = array( 'wpcpm_import_saved_stale', 'That saved read is not from this site\'s last sync. Read Airtable again.' );
$week_old             = array_merge( $read, array( 'read' => $read['read'] - 7 * 86400 ) );
WPCPM_Institutions_Move::save( $week_old, $dir . 'old.json' );
$zero = array_merge( $read, array( 'read' => 0 ) );
WPCPM_Institutions_Move::save( $zero, $dir . 'zero.json' );
ck( 'on a live-like site, last week\'s saved read is refused after a newer sync', said( WPCPM_Institutions_Move::plan( WPCPM_Institutions_Move::read_file( $dir . 'old.json' ) ) ), $refusal );
ck( 'a saved read with no time is refused', said( WPCPM_Institutions_Move::plan( WPCPM_Institutions_Move::read_file( $dir . 'zero.json' ) ) ), $refusal );
$GLOBALS['last_sync'] = $read['read'] + 1000;
ck( 'a saved read a little older than the site\'s last sync passes', is_array( WPCPM_Institutions_Move::plan( WPCPM_Institutions_Move::read_file( $file ) ) ), true );
$GLOBALS['last_sync'] = $read['read'] + 1801;
ck( 'one more than 30 minutes older does not', said( WPCPM_Institutions_Move::plan( WPCPM_Institutions_Move::read_file( $file ) ) ), $refusal );
$stale_plan = $read_plan + array( 'saved' => true );
ck( 'and apply() asks again with the plan\'s own time', said( WPCPM_Institutions_Move::apply( $stale_plan ) ), $refusal );

$GLOBALS['env'] = 'local';
$rehearsal      = WPCPM_Institutions_Move::plan( WPCPM_Institutions_Move::read_file( $dir . 'old.json' ) );
ck(
	'on a local copy a saved read of any age is taken, and the dry run\'s first line says so',
	array( is_array( $rehearsal ), is_array( $rehearsal ) ? WPCPM_Institutions_Move::summary( $rehearsal )[0] : $rehearsal ),
	array( true, 'A rehearsal: this site is a local copy, so the saved read of ' . gmdate( 'Y-m-d H:i', $week_old['read'] ) . ' UTC is used whatever the last sync\'s time.' )
);
ck( 'but never one with no time', said( WPCPM_Institutions_Move::plan( WPCPM_Institutions_Move::read_file( $dir . 'zero.json' ) ) ), $refusal );
ck( 'and a read of Airtable on a local copy still asks for a fresh sync', said( WPCPM_Institutions_Move::plan( $read ) )[0], 'wpcpm_import_stale' );
$GLOBALS['env']       = 'production';
$GLOBALS['last_sync'] = time() - 60;

echo "\n=== No row is dropped with its notes (M3) ===\n";

fresh_site();
$long_name  = trim( str_repeat( 'Długa nazwa szkoły ', 15 ) );
$long_city  = str_repeat( 'Miasto', 42 );
$long_owner = str_repeat( 'Właściciel ', 23 );
$rows3      = array_merge(
	$read,
	array(
		'institutions' => array(),
		'tutors'       => array(),
		'comments'     => array(),
		'outreach'     => array(
			array( 'id' => 'recOUT00000000011', 'fields' => array( 'Ac' => $long_name, 'Notes' => 'Long name, short note.' ) ),
			array( 'id' => 'recOUT00000000012', 'fields' => array( 'Ac' => 'Szkoła z długim miastem', 'City' => $long_city, 'Lead Owner' => $long_owner ) ),
			array( 'id' => 'recOUT00000000013', 'fields' => array( 'Notes' => 'Only a note, and no name.' ) ),
			array( 'id' => 'recOUT00000000014', 'fields' => array( 'Promoted to Institutions' => false ) ),
		),
	)
);
$plan3    = WPCPM_Institutions_Move::plan( $rows3 );
$summary3 = WPCPM_Institutions_Move::summary( $plan3 );
WPCPM_Institutions_Move::apply( $plan3 );
$by3 = array();
foreach ( WPCPM_Institution_Leads::all() as $key => $lead ) {
	$by3[ $lead['source'] ] = array( $lead['name'], $lead['city'], $lead['lead_owner'], array_column( notes_of( $key ), 2 ) );
}
ck(
	'a name over 200 characters: the lead keeps the first 200, a note keeps it whole',
	array( mb_strlen( $by3['outreach:recOUT00000000011'][0], 'UTF-8' ) <= 200, 0 === strpos( $long_name, $by3['outreach:recOUT00000000011'][0] ), $by3['outreach:recOUT00000000011'][3] ),
	array( true, true, array( 'Long name, short note.', $long_name ) )
);
ck(
	'a city over 200: cut on the lead, whole in a note; a Lead Owner over 200: not the lead owner at all, whole in a note',
	array( mb_strlen( $by3['outreach:recOUT00000000012'][1], 'UTF-8' ), $by3['outreach:recOUT00000000012'][2], $by3['outreach:recOUT00000000012'][3] ),
	array( 200, '', array( $long_city, trim( $long_owner ) ) )
);
ck( 'a row with no name but a note is a lead named for its row, with its note', $by3['outreach:recOUT00000000013'], array( 'Unnamed lead recOUT00000000013', '', '', array( 'Only a note, and no name.' ) ) );
ck( 'a row with nothing in it is left out', isset( $by3['outreach:recOUT00000000014'] ), false );
ck(
	'and the summary names each, and counts the empty row',
	array_values( array_filter( $summary3, static function ( $line ) { return 0 === strpos( $line, 'Cut:' ) || 0 === strpos( $line, 'Unnamed:' ) || false !== strpos( $line, 'holds nothing' ); } ) ),
	array(
		'1 row of the Outreach Tracker holds nothing, so it is left out.',
		'Cut: the Ac of ' . $by3['outreach:recOUT00000000011'][0] . ' is longer than 200 characters, so the lead keeps the first 200 and a note keeps it whole.',
		'Cut: the City of Szkoła z długim miastem is longer than 200 characters, so the lead keeps the first 200 and a note keeps it whole.',
		'Cut: the Lead Owner of Szkoła z długim miastem is longer than 200 characters, so the lead has no lead owner and a note keeps it whole.',
		'Unnamed: the Outreach Tracker row recOUT00000000013 has no name, so it comes over as "Unnamed lead recOUT00000000013" for an Administrator to rename or close.',
	)
);
// A stop after the lead is made and before its first note: the rerun finds the lead by its source and still writes the whole values.
fresh_site();
$GLOBALS['die_on'] = static function ( $post ) {
	return WPCPM_Institution_Talk_Notes::POST_TYPE === $post['post_type'] && 0 === strpos( $post['post_title'], 'lead-' );
};
try {
	WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $rows3 ) );
} catch ( Stopped $e ) {
	unset( $e );
}
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $rows3 ) );
$whole_notes = array();
foreach ( WPCPM_Institution_Leads::all() as $key => $lead ) {
	foreach ( array_column( notes_of( $key ), 2 ) as $text ) {
		$whole_notes[] = $text;
	}
}
ck( 'a stop at a lead\'s first note: the rerun writes each whole value once (N1)', array( count( array_keys( $whole_notes, $long_name, true ) ), count( array_keys( $whole_notes, $long_city, true ) ), count( array_keys( $whole_notes, trim( $long_owner ), true ) ) ), array( 1, 1, 1 ) );

fresh_site();
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $rows3 ) );
ck( 'twice is once here too', WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $rows3 ) )['notes'] + WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $rows3 ) )['leads'], 0 );

echo "\n=== A join the lead refuses is named (m1) ===\n";

fresh_site();
$kept_lead = WPCPM_Institution_Leads::add( array( 'name' => 'Szkoła Istniejąca' ), 0, false );
$rows4     = array_merge( $rows3, array( 'outreach' => array( array( 'id' => 'recOUT00000000021', 'fields' => array( 'Ac' => 'szkoła istniejąca', 'HS link (where applicable)' => 'https://help.example.test/conversation/21', 'Notes' => 'Joined note.' ) ) ) ) );
$plan4     = WPCPM_Institutions_Move::plan( $rows4 );
unset( $GLOBALS['posts'][ (int) substr( $kept_lead, 5 ) ] );
$done4 = WPCPM_Institutions_Move::apply( $plan4 );
ck(
	'the refusal is a failure naming the row, and its notes wait for the next run',
	array( $done4['failed'], $done4['notes'], $done4['joined'] ),
	array( array( 'The Outreach Tracker row szkoła istniejąca was not joined to Szkoła Istniejąca: That lead is not in the talks any more.' ), 0, 0 )
);

echo "\n=== A cell brought once is not brought again in another shape (m2) ===\n";

fresh_site();
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
$changed                                        = $read;
$changed['institutions'][0]['fields']['Notes'] = 'Spoke with the dean in October, and again in November.';
$plan5                                          = WPCPM_Institutions_Move::plan( $changed );
ck(
	'a Notes cell changed in Airtable since is named, and not planned',
	array( in_array( 'Changed in Airtable since it was brought, and not brought again: Notes of Wyższa Szkoła Górnołąćka.', $plan5['unresolved'], true ), $plan5['schools'][ $s1 ]['notes'] ),
	array( true, array() )
);
ck( 'and nothing is written', WPCPM_Institutions_Move::apply( $plan5 )['notes'], 0 );

// A stop between the parts of a long cell: the rerun adds the parts left, and the cell is not called changed.
fresh_site();
$parts             = 0;
$GLOBALS['die_on'] = static function ( $post ) use ( $s3, &$parts ) {
	return WPCPM_Institution_Talk_Notes::POST_TYPE === $post['post_type'] && $s3 === $post['post_title'] && 2 === ++$parts;
};
try {
	WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
} catch ( Stopped $e ) {
	unset( $e );
}
$resume = WPCPM_Institutions_Move::plan( $read );
ck( 'a stop after the first of three parts: the rerun plans the other two, and names nothing as changed', array( count( $resume['schools'][ $s3 ]['notes'] ), count( preg_grep( '/^Changed in Airtable/', $resume['unresolved'] ) ) ), array( 2, 0 ) );
WPCPM_Institutions_Move::apply( $resume );
ck( 'and ends as one whole run', state()['notes'], $once['notes'] );

echo "\n=== A promoted row of a name several institutions have is joined to none (m3) ===\n";

fresh_site();
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows']['recEXA00000000006'] = row( 'recEXA00000000006', 'Universitatea Câmpiei Rîușițeni', 'Info Sent' );
WPCPM_Institutions_Index::forget_cached();
$plan6 = WPCPM_Institutions_Move::plan( $read );
WPCPM_Institutions_Move::apply( $plan6 );
WPCPM_Institution_Record::forget_cached( $s2 );
$several = array_values( array_filter( WPCPM_Institution_Leads::all(), static function ( $lead ) { return 'outreach:recOUT00000000003' === $lead['source']; } ) );
ck(
	'it comes over as a lead its own record ID keeps apart, named in the summary, and the two schools are left alone',
	array( isset( $several[0] ) ? $several[0]['name'] : null, in_array( 'Promoted and matched to several institutions, so it comes over as a lead to merge by hand: UNIVERSITATEA Câmpiei Rîușițeni, as UNIVERSITATEA Câmpiei Rîușițeni (Outreach Tracker recOUT00000000003).', $plan6['unresolved'], true ), isset( WPCPM_Institution_Record::get( $s2 )['lead_owner'] ) ),
	array( 'UNIVERSITATEA Câmpiei Rîușițeni (Outreach Tracker recOUT00000000003)', true, false )
);

echo "\n=== Names compared after Unicode normalization (m4) ===\n";

if ( function_exists( 'normalizer_normalize' ) ) {
	fresh_site();
	$decomposed = array_merge( $rows3, array( 'outreach' => array( array( 'id' => 'recOUT00000000031', 'fields' => array( 'Ac' => "Universitatea Ca\u{0302}mpiei Ri\u{0302}us\u{0326}it\u{0326}eni", 'Notes' => 'Decomposed letters.' ) ) ) ) );
	$plan7      = WPCPM_Institutions_Move::plan( $decomposed );
	ck( 'a decomposed name matches the composed one', array( $plan7['outreach'][0]['target'], $plan7['outreach'][0]['how'] ), array( $s2, 'name' ) );
} else {
	echo "  skip the intl extension is not here\n";
}

echo "\n=== A link is no place to save (m5), and a record gone during the read (m6) ===\n";

symlink( $dir . 'elsewhere.json', $dir . 'link.json' );
ck( 'save() refuses a target that is a symbolic link', array( said( WPCPM_Institutions_Move::save( $read, $dir . 'link.json' ) ), file_exists( $dir . 'elsewhere.json' ) ), array( array( 'wpcpm_import_save_place', $sentence ), false ) );

fresh_site();
$client       = new Move_Client();
$client->gone = array( $o1 );
$read_gone    = WPCPM_Institutions_Move::read( $client );
$gone_summary = WPCPM_Institutions_Move::summary( WPCPM_Institutions_Move::plan( $read_gone ) );
ck(
	'a 404 on one record\'s comments skips them, names the record, and reads the rest',
	array( is_array( $read_gone ), $read_gone['comments_gone'], array_keys( $read_gone['comments'] ), in_array( 'The comments of recOUT00000000001 could not be read, as Airtable no longer has the record, so none of them comes over.', $gone_summary, true ) ),
	array( true, array( $o1 ), array( $s1 ), true )
);

echo "\n=== The history row a stopped run did not write (m7) ===\n";

fresh_site();
$GLOBALS['die_on'] = static function ( $post ) use ( $s1 ) {
	return WPCPM_Institution_Audit::POST_TYPE === $post['post_type'] && 4 === count( WPCPM_Institution_Talk_Notes::for_key( $s1 ) ) && ! in_array( 'talks_imported', array_column( rows_of( $s1 ), 0 ), true );
};
try {
	WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
} catch ( Stopped $e ) {
	unset( $e );
}
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
WPCPM_Institutions_Move::apply( WPCPM_Institutions_Move::plan( $read ) );
ck(
	'a run stopped after a school\'s notes and before its row: the rerun writes the row once, a third run none',
	array_values( array_filter( rows_of( $s1 ), static function ( $row ) { return 'talks_imported' === $row[0]; } ) ),
	array( array( 'talks_imported', 'Brought over from Airtable: 4 notes.' ) )
);

echo "\n=== pieces() ===\n";

ck( 'a text within the limit is one piece, an empty one none', array( WPCPM_Institutions_Move::pieces( '  Short.  ' ), WPCPM_Institutions_Move::pieces( '   ' ) ), array( array( 'Short.' ), array() ) );
$unbroken = str_repeat( 'ą', 10001 );
$japanese = str_repeat( '日本語の長い文章です。', 700 );
ck( 'a sentence end of Japanese, which no space follows, is a place to cut (m8)', array_map( static function ( $p ) { return mb_substr( $p, -1, null, 'UTF-8' ); }, WPCPM_Institutions_Move::pieces( $japanese ) ), array( '。', '。' ) );
$marks = WPCPM_Institutions_Move::pieces( 'a' . str_repeat( "e\u{0301}", 5000 ) );
ck( 'and a cut never falls inside a letter and its mark', array( mb_strlen( $marks[0], 'UTF-8' ), mb_substr( $marks[1], 0, 1, 'UTF-8' ), implode( '', $marks ) === 'a' . str_repeat( "e\u{0301}", 5000 ) ), array( 4999, 'e', true ) );
ck( 'a text with no place to cut is cut at the limit, in characters', array_map( static function ( $p ) { return mb_strlen( $p, 'UTF-8' ); }, WPCPM_Institutions_Move::pieces( $unbroken ) ), array( 5000, 5000, 1 ) );

printf( "\n%s (%d checks)\n", $fails ? $fails . ' FAILED' : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
