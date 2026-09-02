<?php
/**
 * Does the agreement gate open only when both sources say so?
 *
 * `is_settled()` is what stands between an institution account and every student on its
 * roster, so each assertion here pins one way it must stay closed: no option, a malformed
 * one, one at another version, one whose flag contradicts its own sides, a grid row that says
 * `Accepted` with no accepted post, an accepted post under a grid row that says `Revoked`.
 * And the one way it opens without an upload: a manager types `On file` and a Drive link
 * into the grid, the rebuild materialises a legacy post, and the two sides agree.
 *
 * The other classes this one leans on are stood in for here: `WPCPM_Mentors_Sync` for the
 * record-ID check, `$wpdb` for the option listing. Nothing else is loaded.
 *
 * Run from the plugin root:  php bin/test-institution-agreement.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']       = array();
$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['post_types'] = array();
$GLOBALS['clock']      = 1756700000;
$GLOBALS['next_id']    = 500;

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_author = 0, $post_title = '', $post_date = '';
}

/**
 * The slice of `$wpdb` the class touches: one LIKE over option names.
 */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	private $args = array();
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { $this->args = $args; return $sql; }
	public function get_col( $sql ) {
		$like   = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), (string) ( $this->args[0] ?? '' ) );
		$prefix = rtrim( $like, '%' );
		$out    = array();
		foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
			if ( 0 === strpos( $name, $prefix ) ) { $out[] = $name; }
		}
		return $out;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $u, $p = null ) { return (string) $u; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function register_post_type( $t, $a = array() ) { $GLOBALS['post_types'][ $t ] = $a; return (object) $a; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v; return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

/**
 * Post meta that behaves like WordPress's: repeated rows, and `true` returns the first.
 *
 * The event log is a repeating key and the route detection reads it, so the harness must
 * keep rows apart rather than one value per key.
 */
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }

function wp_insert_post( $a, $error = false ) {
	$post              = new WP_Post();
	$post->ID          = $GLOBALS['next_id']++;
	$post->post_type   = $a['post_type'] ?? 'post';
	$post->post_status = $a['post_status'] ?? 'publish';
	$post->post_author = (int) ( $a['post_author'] ?? 0 );
	$post->post_title  = $a['post_title'] ?? '';
	$post->post_date   = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$GLOBALS['clock'] += 60;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }

/**
 * `get_posts()` as the class uses it: type, status, one meta clause, newest first, ids or posts.
 */
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $a['post_type'] ) && $post->post_type !== $a['post_type'] ) { continue; }
		if ( isset( $a['post_status'] ) && 'any' !== $a['post_status'] && $post->post_status !== $a['post_status'] ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			foreach ( $a['meta_query'] as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
				if ( get_post_meta( $post->ID, $clause['key'], true ) !== $clause['value'] ) { continue 2; }
			}
		}
		$out[] = $post;
	}
	usort( $out, function ( $x, $y ) {
		$by_date = strcmp( $y->post_date, $x->post_date );
		return 0 !== $by_date ? $by_date : $y->ID - $x->ID;
	} );
	if ( isset( $a['numberposts'] ) && (int) $a['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $a['numberposts'] );
	}
	if ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) {
		return array_map( function ( $p ) { return $p->ID; }, $out );
	}
	return $out;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';

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
 * Forget every option, lock and post.
 */
function reset_world() {
	$GLOBALS['opts']  = array();
	$GLOBALS['posts'] = array();
	$GLOBALS['pmeta'] = array();
}

/**
 * Stand up an agreement post the way a later phase's handler would.
 *
 * @param string $record Institutions record ID.
 * @param string $state  A STATE_* value.
 * @param string $kind   A KIND_* value.
 * @param array  $meta   Extra meta rows.
 * @return int Post ID.
 */
function seed_post( $record, $state, $kind = 'template', array $meta = array() ) {
	$id = wp_insert_post( array(
		'post_type'   => WPCPM_Institution_Agreement::POST_TYPE,
		'post_status' => WPCPM_Institution_Agreement::POST_STATUS,
		'post_author' => 7,
		'post_title'  => 'seeded',
	) );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_INSTITUTION, $record );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_STATE, $state );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_KIND, $kind );
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
}

/**
 * The Airtable agreement block for a record, every field present.
 *
 * @param string $status   Agreement Status.
 * @param string $document Agreement Document.
 * @param array  $more     Other fields.
 * @return array
 */
function block( $status, $document = '', array $more = array() ) {
	return array_merge( array(
		'status'           => $status,
		'kind'             => '',
		'accepted_on'      => '',
		'signed_on'        => '',
		'accepted_by'      => '',
		'document'         => $document,
		'submitted_on'     => '',
		'template_version' => '',
	), $more );
}

/**
 * Every agreement post for a record, oldest first, as `state/kind` strings.
 *
 * @param string $record Institutions record ID.
 * @return string[]
 */
function shapes_of( $record ) {
	$out = array();
	foreach ( array_reverse( WPCPM_Institution_Agreement::posts_for( $record ) ) as $post ) {
		$out[] = get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_STATE, true ) . '/' . get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_KIND, true );
	}
	return $out;
}

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/institutions-table-fields.json' ), true );
$seed    = json_decode( file_get_contents( __DIR__ . '/fixtures/institutions-index-seed.json' ), true );
$drive   = 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz';
$rec_a   = 'recAAAAAAAAAAAAA1';
$rec_b   = 'recBBBBBBBBBBBBB2';
$rec_c   = 'recCCCCCCCCCCCCC3';

/* ---- the constants pin the base's spelling ------------------------------ */

echo "=== The constants pin the base's spelling ===\n";

// A wrong-length ID goes down the refusal path and passes half the file by accident.
ck( 'the IDs this file types are record-shaped', array( strlen( $rec_a ), strlen( $rec_b ), strlen( $rec_c ), WPCPM_Mentors_Sync::is_record_id( $rec_a ) ), array( 17, 17, 17, true ) );

$stages = array_merge( WPCPM_Institution_Agreement::STAGE_ORDER, WPCPM_Institution_Agreement::TERMINAL_STAGES );
sort( $stages, SORT_STRING );
$choices = $fixture['choices']['Current Stage'];
sort( $choices, SORT_STRING );

ck( 'STAGE_ORDER plus TERMINAL_STAGES is exactly the Current Stage choice list', $stages, $choices );
$order = WPCPM_Institution_Agreement::STAGE_ORDER;
ck( 'STAGE_ORDER ends in Student, which no write touches', end( $order ), 'Student' );
ck( 'every AIRTABLE_SETTLED value is an Agreement Status choice',
    array_values( array_diff( WPCPM_Institution_Agreement::AIRTABLE_SETTLED, $fixture['choices']['Agreement Status'] ) ), array() );
ck( 'AIRTABLE_ON_FILE is one of the settled values', in_array( WPCPM_Institution_Agreement::AIRTABLE_ON_FILE, WPCPM_Institution_Agreement::AIRTABLE_SETTLED, true ), true );
ck( 'Revoked and Awaiting review, which the assertions below type, are choices',
    array_values( array_diff( array( 'Revoked', 'Awaiting review' ), $fixture['choices']['Agreement Status'] ) ), array() );
ck( 'the option prefix and the lock prefix', array( WPCPM_Institution_Agreement::OPTION_PREFIX, WPCPM_Institution_Agreement::LOCK_PREFIX ), array( 'wpcpm_agreement_', 'wpcpm_agreement_lock_' ) );
ck( 'option_name() builds from the prefix', WPCPM_Institution_Agreement::option_name( $rec_a ), 'wpcpm_agreement_' . $rec_a );
ck( 'the post type is the one section 9 names', WPCPM_Institution_Agreement::POST_TYPE, 'wpcpm_agreement' );

/* ---- the post type is invisible ----------------------------------------- */

echo "\n=== The post type is invisible ===\n";

WPCPM_Institution_Agreement::register_post_type();
$args = $GLOBALS['post_types']['wpcpm_agreement'] ?? array();

ck( 'registered', empty( $args ), false );
ck( 'not public, no UI, not in REST, not searchable',
    array( $args['public'] ?? null, $args['show_ui'] ?? null, $args['show_in_rest'] ?? null, $args['exclude_from_search'] ?? null ),
    array( false, false, false, true ) );
ck( 'a capability type nobody holds, with map_meta_cap', array( $args['capability_type'] ?? null, $args['map_meta_cap'] ?? null ), array( array( 'wpcpm_agreement', 'wpcpm_agreements' ), true ) );

/* ---- the predicate fails closed ----------------------------------------- */

echo "\n=== The predicate fails closed ===\n";

reset_world();

ck( 'no option: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );
ck( 'no option: option() is null', WPCPM_Institution_Agreement::option( $rec_a ), null );
ck( 'not a record ID: locked', WPCPM_Institution_Agreement::is_settled( 'Universidad Example' ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = 'yes';
ck( 'a string where the array should be: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array( 'settled' => true );
ck( 'an array missing the version and the sides: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$good = array(
	'v'               => 1,
	'settled'         => true,
	'site_state'      => 'accepted',
	'airtable_status' => 'Accepted',
	'kind'            => 'template',
	'agreement_id'    => 12,
	'pending_id'      => 0,
	'generated_id'    => 0,
	'accepted_at'     => '2026-08-30',
	'drive_url'       => '',
	'updated'         => 1756700000,
);

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'v' => 2 ) );
ck( 'the wrong version: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'settled' => false, 'site_state' => 'none' ) );
ck( 'settled false: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'settled' => 1 ) );
ck( 'settled as an int rather than a bool: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'airtable_status' => 'Revoked' ) );
ck( 'a flag that says settled over a Revoked grid status: locked, the row is corrupt', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'site_state' => 'submitted' ) );
ck( 'a flag that says settled over a submitted site state: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = $good;
ck( 'both sides settled and the flag agreeing: open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'option() hands the row back typed', WPCPM_Institution_Agreement::option( $rec_a ), $good );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'site_state' => 'on_file', 'airtable_status' => 'On file', 'kind' => 'legacy' ) );
ck( 'on file on both sides: open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'site_state' => 'on_file', 'airtable_status' => 'Accepted', 'kind' => 'legacy' ) );
ck( 'on file here and Accepted there still agree on settled: open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

/* ---- rebuild: the two sources ------------------------------------------- */

echo "\n=== rebuild(): the two sources ===\n";

reset_world();
$post_a = seed_post( $rec_a, 'accepted', 'template', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-30' ) );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted', '', array( 'kind' => 'Program template' ) ) );

ck( 'Accepted with an accepted template post: settled', $option['settled'], true );
ck( 'site_state accepted, kind template, the post named', array( $option['site_state'], $option['kind'], $option['agreement_id'] ), array( 'accepted', 'template', $post_a ) );
ck( 'accepted_at is the decision date', $option['accepted_at'], '2026-08-30' );
ck( 'the option is what was stored', $GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ], $option );
ck( 'the predicate now opens', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );
ck( 'the version is stamped', $option['v'], 1 );
ck( 'route is site', WPCPM_Institution_Agreement::summary( $rec_a )['route'], 'site' );
ck( 'nothing to reconcile', WPCPM_Institution_Agreement::discrepancies(), array() );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );

ck( 'Accepted with no post: locked', $option['settled'], false );
ck( 'site_state none', $option['site_state'], 'none' );
ck( 'listed as a discrepancy naming both sides', WPCPM_Institution_Agreement::discrepancies(), array( $rec_a => array( 'site_state' => 'none', 'airtable_status' => 'Accepted' ) ) );
ck( 'no post was invented for a bare Accepted', shapes_of( $rec_a ), array() );

reset_world();
$post_b = seed_post( $rec_a, 'submitted', 'own' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Awaiting review' ) );

ck( 'Awaiting review with a submitted post: not settled', $option['settled'], false );
ck( 'site_state submitted, pending_id the post', array( $option['site_state'], $option['pending_id'], $option['agreement_id'] ), array( 'submitted', $post_b, 0 ) );
ck( 'in review is a queue, not a discrepancy', WPCPM_Institution_Agreement::discrepancies(), array() );

reset_world();
$post_c = seed_post( $rec_a, 'accepted', 'template' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Revoked' ) );

ck( 'Revoked in the grid over an accepted post: locked', $option['settled'], false );
ck( 'the post is untouched, the grid word is recorded', array( $option['site_state'], $option['airtable_status'] ), array( 'accepted', 'Revoked' ) );
ck( 'listed, both sides named', WPCPM_Institution_Agreement::discrepancies(), array( $rec_a => array( 'site_state' => 'accepted', 'airtable_status' => 'Revoked' ) ) );

reset_world();
seed_post( $rec_a, 'superseded', 'template' );
seed_post( $rec_a, 'revoked', 'template', array( WPCPM_Institution_Agreement::META_NOTE => 'ended' ) );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );

ck( 'a site-side revoke is not undone by a stale Accepted in the grid', $option['settled'], false );
ck( 'site_state revoked', $option['site_state'], 'revoked' );

reset_world();
seed_post( $rec_a, 'returned', 'own' );
seed_post( $rec_a, 'withdrawn', 'own' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Returned' ) );

ck( 'a withdrawn re-upload after a return leaves the return standing', $option['site_state'], 'returned' );

reset_world();
seed_post( $rec_a, 'generated', 'template' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Template generated' ) );

ck( 'a generated post reads generated and names itself', array( $option['site_state'], $option['generated_id'] > 0 ), array( 'generated', true ) );

reset_world();
$older = seed_post( $rec_a, 'accepted', 'template', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-01-10' ) );
$newer = seed_post( $rec_a, 'submitted', 'template' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );

ck( 'a replacement in review sits beside the accepted one without unseating it',
    array( $option['settled'], $option['site_state'], $option['agreement_id'], $option['pending_id'] ), array( true, 'accepted', $older, $newer ) );

reset_world();
ck( 'a bad record ID writes nothing', WPCPM_Institution_Agreement::rebuild( 'nope', block( 'Accepted' ) ), array() );
ck( 'and stores nothing', $GLOBALS['opts'], array() );

/* ---- the grid route ----------------------------------------------------- */

echo "\n=== The grid route: On file and a Drive link ===\n";

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2024-03-15', 'accepted_on' => '2026-09-01' ) ) );

ck( 'settled', $option['settled'], true );
ck( 'site_state on_file, kind legacy', array( $option['site_state'], $option['kind'] ), array( 'on_file', 'legacy' ) );
ck( 'one legacy post materialised', shapes_of( $rec_a ), array( 'accepted/legacy' ) );

$legacy = get_post( $option['agreement_id'] );

ck( 'author 0', $legacy->post_author, 0 );
ck( 'private, of our type', array( $legacy->post_status, $legacy->post_type ), array( 'private', 'wpcpm_agreement' ) );
ck( 'the Drive link and the signed date from the block', array( get_post_meta( $legacy->ID, '_wpcpm_agr_drive_url', true ), get_post_meta( $legacy->ID, '_wpcpm_agr_signed_on', true ) ), array( $drive, '2024-03-15' ) );
ck( 'decided by nobody, on the date the base holds', array( get_post_meta( $legacy->ID, '_wpcpm_agr_decided_by', true ), get_post_meta( $legacy->ID, '_wpcpm_agr_decided_at', true ) ), array( 0, '2026-09-01' ) );
ck( 'one event, recorded in Airtable', array_map( function ( $e ) { return $e['event']; }, get_post_meta( $legacy->ID, '_wpcpm_agr_event' ) ), array( 'recorded in Airtable' ) );
ck( 'no file on a legacy row', get_post_meta( $legacy->ID, '_wpcpm_agr_file', true ), '' );
ck( 'the option carries the Drive link', $option['drive_url'], $drive );
ck( 'accepted_at is the base date', $option['accepted_at'], '2026-09-01' );

$summary = WPCPM_Institution_Agreement::summary( $rec_a );
ck( 'summary: on_file, legacy, route grid, the grid status', array( $summary['state'], $summary['kind'], $summary['route'], $summary['airtable_status'], $summary['agreement_id'] ), array( 'on_file', 'legacy', 'grid', 'On file', $legacy->ID ) );
ck( 'the predicate opens', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

$again = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2024-03-15', 'accepted_on' => '2026-09-01' ) ) );
ck( 'a second rebuild with the same inputs creates no second post', shapes_of( $rec_a ), array( 'accepted/legacy' ) );
ck( 'and names the same post', $again['agreement_id'], $legacy->ID );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', '', array( 'kind' => 'Legacy' ) ) );
ck( 'On file with no link: locked', $option['settled'], false );
ck( 'no post created', shapes_of( $rec_a ), array() );
ck( 'listed: On file in the grid, nothing here', WPCPM_Institution_Agreement::discrepancies(), array( $rec_a => array( 'site_state' => 'none', 'airtable_status' => 'On file' ) ) );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', 'https://www.dropbox.com/s/abc/agreement.pdf' ) );
ck( 'On file with a link that is not Drive: locked, no post', array( $option['settled'], shapes_of( $rec_a ) ), array( false, array() ) );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', 'http://drive.google.com/file/d/abc' ) );
ck( 'On file with a plain http Drive link: locked, no post', array( $option['settled'], shapes_of( $rec_a ) ), array( false, array() ) );
ck( 'and the option does not carry it', $option['drive_url'], '' );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', 'https://docs.google.com/document/d/abc/edit' ) );
ck( 'docs.google.com is Drive too', array( $option['settled'], shapes_of( $rec_a ) ), array( true, array( 'accepted/legacy' ) ) );
ck( 'a legacy row with no dates is accepted today', get_post_meta( $option['agreement_id'], '_wpcpm_agr_decided_at', true ), gmdate( 'Y-m-d' ) );
ck( 'and carries an empty signed date rather than a wrong one', get_post_meta( $option['agreement_id'], '_wpcpm_agr_signed_on', true ), '' );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'signed_on' => '15/03/2024', 'accepted_on' => '2026-02-30' ) ) );
ck( 'dates the base could not have written are dropped, not stored', array( get_post_meta( $option['agreement_id'], '_wpcpm_agr_signed_on', true ), get_post_meta( $option['agreement_id'], '_wpcpm_agr_decided_at', true ) ), array( '', gmdate( 'Y-m-d' ) ) );

reset_world();
$uploaded = seed_post( $rec_a, 'accepted', 'own' );
$option   = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );
ck( 'On file over an accepted upload: settled on the upload, no legacy post beside it', array( $option['settled'], $option['agreement_id'], shapes_of( $rec_a ) ), array( true, $uploaded, array( 'accepted/own' ) ) );
ck( 'route is site: a person accepted it here', WPCPM_Institution_Agreement::summary( $rec_a )['route'], 'site' );

ck( 'is_drive_link() accepts the two hosts over https only',
    array(
    	WPCPM_Institution_Agreement::is_drive_link( $drive ),
    	WPCPM_Institution_Agreement::is_drive_link( 'https://docs.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'https://www.drive.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'http://drive.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'drive.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( '' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'https://drive.google.com.evil.example/x' ),
    ),
    array( true, true, false, false, false, false, false ) );

/* ---- the TEST record, as Phase 1 demonstrates it ------------------------ */

echo "\n=== The TEST record's grid route ===\n";

// Found by its label rather than by its ID, so that a seed refreshed from a base where the
// TEST record has been replaced fails here rather than silently walking a record that is not
// in the fixture at all.
$test_id = '';
foreach ( $seed['institutions'] as $row ) {
	if ( isset( $row['name'] ) && 0 === strpos( (string) $row['name'], 'TEST' ) ) {
		$test_id = (string) $row['id'];
	}
}

ck( 'the seed holds exactly one record labelled TEST, and it is the one the module is built against', $test_id, 'recDdomg5W6h410JT' );
ck( 'and it is a Confirmed-stage-free record nobody will mistake for a partner', count( array_filter( $seed['institutions'], function ( $row ) { return 0 === strpos( (string) $row['name'], 'TEST' ); } ) ), 1 );

reset_world();
$first = WPCPM_Institution_Agreement::rebuild( $test_id, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2026-09-02' ) ) );
ck( 'typing On file and a Drive link and pressing Refresh settles it', WPCPM_Institution_Agreement::is_settled( $test_id ), true );
ck( 'by the grid route', WPCPM_Institution_Agreement::summary( $test_id )['route'], 'grid' );

$second = WPCPM_Institution_Agreement::rebuild( $test_id, block( 'Revoked', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2026-09-02' ) ) );
ck( 'typing Revoked locks it on the next rebuild', WPCPM_Institution_Agreement::is_settled( $test_id ), false );
ck( 'the legacy post still stands, so the screen can say what disagrees', WPCPM_Institution_Agreement::discrepancies(), array( $test_id => array( 'site_state' => 'on_file', 'airtable_status' => 'Revoked' ) ) );
ck( 'no second post was made while locked', shapes_of( $test_id ), array( 'accepted/legacy' ) );
ck( 'the same post is named across both rebuilds', $first['agreement_id'] === $second['agreement_id'] && $second['agreement_id'] > 0, true );

$third = WPCPM_Institution_Agreement::rebuild( $test_id, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2026-09-02' ) ) );
ck( 'putting On file back reopens it without a new post', array( $third['settled'], shapes_of( $test_id ) ), array( true, array( 'accepted/legacy' ) ) );

/* ---- the lock ----------------------------------------------------------- */

echo "\n=== The lock ===\n";

reset_world();
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] = time();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );

ck( 'a held lock skips: nothing written', $option, array() );
ck( 'no option stored', array_key_exists( 'wpcpm_agreement_' . $rec_a, $GLOBALS['opts'] ), false );
ck( 'no post materialised', shapes_of( $rec_a ), array() );
ck( 'the lock is left to its holder', $GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] > 0, true );
ck( 'the institution stays locked meanwhile', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

delete_option( 'wpcpm_agreement_lock_' . $rec_a );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );
ck( 'once released, the same call writes', $option['settled'], true );

reset_world();
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] = time() - WPCPM_Institution_Agreement::LOCK_TIMEOUT - 1;
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );
ck( 'a lock older than the timeout belonged to a dead request and is cleared', $option['site_state'], 'none' );
ck( 'and released again after', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );

reset_world();
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] = time();
ck( 'a lock row is not listed as an institution', WPCPM_Institution_Agreement::discrepancies(), array() );

/* ---- rebuild_all, summary, delete_all ----------------------------------- */

echo "\n=== rebuild_all(), summary() and delete_all() ===\n";

reset_world();
$GLOBALS['opts']['wpcpm_agreement_drift'] = array( 'read' => 1 );
seed_post( $rec_b, 'accepted', 'template' );
$written = WPCPM_Institution_Agreement::rebuild_all( array(
	$rec_a => block( 'On file', $drive ),
	$rec_b => block( 'Accepted' ),
	$rec_c => block( 'Not started' ),
	'junk' => block( 'Accepted' ),
	$rec_a . 'x' => 'not a block',
) );

ck( 'three options written, the junk keys skipped', $written, 3 );
ck( 'each row reads as its inputs say',
    array( WPCPM_Institution_Agreement::is_settled( $rec_a ), WPCPM_Institution_Agreement::is_settled( $rec_b ), WPCPM_Institution_Agreement::is_settled( $rec_c ) ),
    array( true, true, false ) );
ck( 'Not started with nothing here is a queue row, not a discrepancy', WPCPM_Institution_Agreement::discrepancies(), array() );
ck( 'a sibling option under the prefix is neither listed nor touched', $GLOBALS['opts']['wpcpm_agreement_drift'], array( 'read' => 1 ) );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_c ] = array( 'v' => 99, 'site_state' => 'accepted', 'airtable_status' => 'Accepted' );
ck( 'an unreadable row is listed with whatever it holds, because the predicate locks it', WPCPM_Institution_Agreement::discrepancies(), array( $rec_c => array( 'site_state' => 'accepted', 'airtable_status' => 'Accepted' ) ) );
ck( 'and it is locked', WPCPM_Institution_Agreement::is_settled( $rec_c ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_c ] = 'garbage';
ck( 'a row that is not an array is listed with empty sides', WPCPM_Institution_Agreement::discrepancies(), array( $rec_c => array( 'site_state' => '', 'airtable_status' => '' ) ) );

reset_world();
$empty = WPCPM_Institution_Agreement::summary( $rec_a );
ck( 'summary with nothing at all', $empty, array(
	'state'           => 'none',
	'kind'            => '',
	'accepted_at'     => '',
	'agreement_id'    => 0,
	'pending_id'      => 0,
	'generated_id'    => 0,
	'airtable_status' => '',
	'route'           => '',
) );

$revoked = seed_post( $rec_a, 'revoked', 'template' );
ck( 'summary after a site-side revoke deleted the option still says revoked', array( WPCPM_Institution_Agreement::summary( $rec_a )['state'], WPCPM_Institution_Agreement::is_settled( $rec_a ) ), array( 'revoked', false ) );

ck( 'posts_for() is newest first', array_map( function ( $p ) { return $p->ID; }, WPCPM_Institution_Agreement::posts_for( $rec_a ) ), array( $revoked ) );
ck( 'posts_for() refuses a bad ID', WPCPM_Institution_Agreement::posts_for( 'x' ), array() );

reset_world();
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );
WPCPM_Institution_Agreement::rebuild( $rec_b, block( 'Accepted' ) );
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_c ] = time();
$GLOBALS['opts']['wpcpm_settings'] = array( 'keep' => true );
WPCPM_Institution_Agreement::delete_all();

ck( 'delete_all() removes every option and lock under the prefix and every post', array( array_keys( $GLOBALS['opts'] ), $GLOBALS['posts'] ), array( array( 'wpcpm_settings' ), array() ) );

/* ---- nothing in the option is prose ------------------------------------- */

echo "\n=== The option holds no prose ===\n";

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'accepted_by' => 'A Person', 'kind' => 'Legacy' ) ) );
ck( 'the option has exactly the contract keys', array_keys( $option ), array( 'v', 'settled', 'site_state', 'airtable_status', 'kind', 'agreement_id', 'pending_id', 'generated_id', 'accepted_at', 'drive_url', 'updated' ) );
ck( 'the name typed into Accepted By reaches neither the option nor the post',
    array( strpos( serialize( $option ), 'A Person' ), strpos( serialize( $GLOBALS['pmeta'] ), 'A Person' ) ), array( false, false ) );

$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
ck( 'every option write is non-autoloaded', preg_match_all( '/update_option\(/', $source ), preg_match_all( '/update_option\([^;]*?,\s*false\s*\);/s', $source ) );
ck( 'no institution ID is compared with === in the class', preg_match( '/===\s*\$record_id|\$record_id\s*===/', $source ), 0 );
ck( 'no em dash or en dash anywhere in the class', preg_match( '/[\x{2013}\x{2014}]/u', $source ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
