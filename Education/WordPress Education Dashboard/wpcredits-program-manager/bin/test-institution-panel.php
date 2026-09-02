<?php
/**
 * The locked agreement panel, the card at the foot of a settled dashboard, and the on-file route.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **Every locked state says its own words.** The panel is the only thing an institution
 *   account can see before its agreement is settled, so a branch that fell through to another
 *   state's wording would tell a partner that has already signed to start signing. The five
 *   states of the design spec's section 7.4 are rendered here and their ledes compared with
 *   each other, not only with themselves.
 * - **A settled institution gets a card and no panel; an unsettled one gets a panel and no
 *   card.** Two halves of one gate, and a page showing both would be a gate nobody believes.
 * - **The card names the date and the route.** "We never signed anything on this website" is a
 *   reasonable thing for a long-standing partner to think, and the answer is on the card.
 * - **`handle_on_file()` writes Airtable first and refuses on failure.** The order is asserted
 *   as an order, from a journal the stubs keep, because an institution opened on the site while
 *   the base still says `Not started` is the shape the whole design exists to prevent. An
 *   `example.com` link is refused by name before any of it, and a second recording over a
 *   standing accepted document is refused before any of it too.
 * - **The base's spelling is the fixture's.** `update_records()` sends no `typecast`, so a
 *   choice or a column spelled any other way is a 422 for the whole record; every cell name and
 *   both choice values are checked against `bin/fixtures/institutions-table-fields.json`.
 *
 * The other pieces are stood in for exactly at their contracts: the policy, the sync's field
 * map, the Airtable client, the manager screen's flash channel. Nothing else is loaded.
 *
 * Run from the plugin root:  php bin/test-institution-panel.php
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
$GLOBALS['umeta']      = array();
$GLOBALS['users']      = array();
$GLOBALS['post_types'] = array();
$GLOBALS['caps']       = true;
$GLOBALS['uid']        = 0;
$GLOBALS['clock']      = 1756700000;
$GLOBALS['next_id']    = 500;
$GLOBALS['nonces']     = array();
$GLOBALS['journal']    = array();
$GLOBALS['audit']      = array();
$GLOBALS['patched']    = array();
$GLOBALS['airtable']   = null;
$GLOBALS['allow']      = true;
$GLOBALS['member_of']  = array();
$GLOBALS['decisions']  = array();
$GLOBALS['referer']    = '';

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
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '', $email = '' ) { $this->ID = $id; $this->display_name = $name; $this->user_email = $email; }
	public function exists() { return $this->ID > 0; }
}

/** The slice of `$wpdb` the agreement class touches: one LIKE over option names. */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	private $args = array();
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { $this->args = $args; return $sql; }
	public function get_col( $sql ) {
		$prefix = rtrim( (string) ( $this->args[0] ?? '' ), '%' );
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
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) (int) $n; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $u ) { return (string) $u; }
function esc_url_raw( $u, $p = null ) { return (string) $u; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wpautop( $s ) { return '<p>' . str_replace( "\n", "<br />\n", (string) $s ) . '</p>'; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? $GLOBALS['clock'] : (int) $t ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function register_post_type( $t, $a = array() ) { $GLOBALS['post_types'][ $t ] = $a; return (object) $a; }
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( 'nonce-' . $a ) . '" />'; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['nonces'][] = $a; return true; }
function current_user_can( $c ) { return (bool) $GLOBALS['caps']; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function wp_get_referer() { return '' !== $GLOBALS['referer'] ? $GLOBALS['referer'] : false; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die: ' . $m ); }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	// The journal records only the agreement option, which is the third of the three writes
	// the on-file route makes; the lock is an add_option() and is not one of them.
	if ( 0 === strpos( (string) $k, 'wpcpm_agreement_' ) && 0 !== strpos( (string) $k, 'wpcpm_agreement_lock_' ) && 'wpcpm_agreement_on_file_all' !== (string) $k ) {
		$GLOBALS['journal'][] = 'option';
	}
	$GLOBALS['opts'][ $k ]          = $v;
	$GLOBALS['opts_autoload'][ $k ] = $a;
	return true;
}
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

function get_user_meta( $id, $k = '', $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ( $single ? '' : array() ); }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }

function wp_insert_post( $a, $error = false ) {
	if ( ! empty( $GLOBALS['post_fails'] ) ) {
		return $error ? new WP_Error( 'wpcpm_test_insert', 'refused' ) : 0;
	}
	$GLOBALS['journal'][]           = 'post';
	$post                           = new WP_Post();
	$post->ID                       = $GLOBALS['next_id']++;
	$post->post_type                = $a['post_type'] ?? 'post';
	$post->post_status              = $a['post_status'] ?? 'publish';
	$post->post_author              = (int) ( $a['post_author'] ?? 0 );
	$post->post_title               = $a['post_title'] ?? '';
	$post->post_date                = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$GLOBALS['clock']              += 60;
	$GLOBALS['posts'][ $post->ID ]  = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }

/** `get_posts()` as the agreement class uses it: type, status, one meta clause, newest first. */
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
	return $out;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';

if ( ! class_exists( 'WPCPM_Institution_Policy' ) ) {
	/**
	 * The fence, at its contract: the subject builder, the two shipped grounds, one refusal.
	 *
	 * It decides the way the real class does rather than answering a flag, because what this
	 * file has to be able to see is that both halves of the gate ask it and act on the
	 * answer: a stub that said yes to everyone would pass a panel that never asked at all,
	 * which is exactly the state this suite failed to catch once already. `$GLOBALS['allow']`
	 * stays as the blunt override the on-file block needs, where the question is what a
	 * handler does with a refusal rather than who earned one.
	 */
	class WPCPM_Institution_Policy {
		const ACT_VIEW_ROSTER = 'view_roster';
		const ACT_AGREEMENT   = 'agreement';
		const GROUND_MANAGER  = 'manager';
		const GROUND_MEMBER   = 'member';
		const REFUSAL_CODE    = 'wpcpm_inst_unknown';
		public static function ungated() {
			return array( self::ACT_AGREEMENT );
		}
		public static function subject_institution( $record_id ) {
			return array(
				'type'            => 'institution',
				'id'              => $record_id,
				'institution_ids' => array( $record_id ),
				'evidence'        => 'index',
			);
		}
		public static function decide( $action, array $subject, $user = null ) {
			$GLOBALS['decisions'][] = array( $action, $subject['id'] );

			if ( ! $GLOBALS['allow'] ) {
				return self::answer( false, '', '' );
			}

			// The manager ground first, as the real map orders it, and unconditional.
			if ( $GLOBALS['caps'] ) {
				return self::answer( true, self::GROUND_MANAGER, (string) ( $subject['institution_ids'][0] ?? '' ) );
			}

			// Then membership of an institution the subject names, gated on the agreement for
			// every action but the one the panel draws.
			foreach ( (array) $GLOBALS['member_of'] as $mine ) {
				if ( ! in_array( $mine, (array) $subject['institution_ids'], true ) ) {
					continue;
				}
				if ( ! in_array( $action, self::ungated(), true ) && ! WPCPM_Institution_Agreement::is_settled( $mine ) ) {
					continue;
				}
				return self::answer( true, self::GROUND_MEMBER, $mine );
			}

			return self::answer( false, '', '' );
		}
		public static function refusal() {
			return new WP_Error( self::REFUSAL_CODE, 'That record is not on your roster.' );
		}
		/** One decision, in the shape the real class returns. */
		private static function answer( $allowed, $ground, $institution ) {
			return array(
				'allowed'     => (bool) $allowed,
				'ground'      => $ground,
				'institution' => $institution,
				'fields'      => $allowed ? null : array(),
				'why'         => $allowed ? '' : 'no-ground',
			);
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Sync' ) ) {
	/** Only the field map the on-file write reads. Checked against the fixture below. */
	class WPCPM_Institutions_Sync {
		public static function fields() {
			return array(
				'name'             => 'Name',
				'stage'            => 'Current Stage',
				'country'          => 'Country',
				'city'             => 'City',
				'website'          => 'Website',
				'contact_person'   => 'Contact Person',
				'contact_email'    => 'Contact Email',
				'confirmed_on'     => 'Confirmed on',
				'consent'          => 'Privacy Policy Compliance',
				'agr_status'       => 'Agreement Status',
				'agr_kind'         => 'Agreement Kind',
				'agr_accepted_on'  => 'Agreement Accepted On',
				'agr_signed_on'    => 'Agreement Signed On',
				'agr_accepted_by'  => 'Agreement Accepted By',
				'agr_document'     => 'Agreement Document',
				'agr_submitted_on' => 'Agreement Submitted On',
				'agr_template'     => 'Agreement Template Version',
			);
		}
	}
}

if ( ! class_exists( 'WPCPM_Settings' ) ) {
	/** Enough of the settings for the one table the write names. */
	class WPCPM_Settings {
		public static function get() {
			return array( 'institutions_table' => 'tbl4V0FEbzRP7I2w2' );
		}
	}
}

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/** The one write path, journalled so its position among the three writes can be asserted. */
	class WPCPM_Airtable {
		public function __construct( $settings = null ) {}
		public function update_records( $table, array $records ) {
			$GLOBALS['journal'][] = 'airtable';
			$GLOBALS['patched'][] = array( $table, $records );
			if ( $GLOBALS['airtable'] instanceof WP_Error ) { return $GLOBALS['airtable']; }
			if ( is_array( $GLOBALS['airtable'] ) ) { return $GLOBALS['airtable']; }
			return array( $records[0]['id'] => true );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	/**
	 * The log, at its contract, refusals included.
	 *
	 * It validates what the real class validates, because the point of asserting that a row
	 * was written is that it is a row the real class would have kept: `record()` refuses a
	 * row with no kind, an institution it cannot list under, a ground it does not know or an
	 * evidence level it does not know, and a stub that accepted all four would let a patch
	 * pass while writing a row that is silently dropped on the live site.
	 */
	class WPCPM_Institution_Audit {
		const EVIDENCE_INDEX = 'index';
		const EVIDENCE_CACHE = 'cache';
		const EVIDENCE_LIVE  = 'live';
		const GROUND_MANAGER = 'manager';
		const GROUND_MEMBER  = 'member';
		const GROUND_SYSTEM  = 'system';
		public static function grounds() {
			return array( self::GROUND_MANAGER, self::GROUND_MEMBER, self::GROUND_SYSTEM );
		}
		public static function evidence_levels() {
			return array( self::EVIDENCE_INDEX, self::EVIDENCE_CACHE, self::EVIDENCE_LIVE );
		}
		public static function record( array $entry ) {
			if ( '' === sanitize_key( (string) ( $entry['kind'] ?? '' ) )
				|| ! WPCPM_Mentors_Sync::is_record_id( (string) ( $entry['institution'] ?? '' ) )
				|| ! in_array( (string) ( $entry['ground'] ?? '' ), self::grounds(), true )
				|| ! in_array( (string) ( $entry['evidence'] ?? '' ), self::evidence_levels(), true ) ) {
				return new WP_Error( 'wpcpm_audit', 'refused' );
			}
			$GLOBALS['journal'][] = 'audit';
			$GLOBALS['audit'][]   = $entry;
			return 900 + count( $GLOBALS['audit'] );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/** The manager screen, for its flash channel alone. Pinned against the real file below. */
	class WPCPM_Institutions {
		const FLASH = 'institutions';
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php';

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
 * Forget every option, post and pending message.
 *
 * The capability goes back to the manager's too. Every render below says who is looking
 * through `panel()` and `card()`, so a block that starts with a reset and then posts a form
 * would otherwise inherit whichever viewer the last render happened to leave behind, and
 * "no capability dies 403" would pass in a block that meant to test something else.
 */
function reset_world() {
	$GLOBALS['caps']       = true;
	$GLOBALS['opts']       = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['journal']    = array();
	$GLOBALS['audit']      = array();
	$GLOBALS['patched']    = array();
	$GLOBALS['nonces']     = array();
	$GLOBALS['decisions']  = array();
	$GLOBALS['airtable']   = null;
	$GLOBALS['allow']      = true;
	$GLOBALS['member_of']  = array();
	$GLOBALS['post_fails'] = false;
}

/**
 * Seed the pipeline index with one institution.
 *
 * @param string $record  Institutions record ID.
 * @param string $country Countries record ID, or an empty string.
 */
function seed_index( $record, $country = '' ) {
	$row               = WPCPM_Institutions_Index::empty_row();
	$row['record_id']  = $record;
	$row['name']       = 'Universidad Example';
	$row['stage']      = 'Confirmed';
	$row['country']    = $country;
	$row['contact_email'] = 'contact@example.edu';

	WPCPM_Institutions_Index::write( array( $record => $row ), 1756600000 );
}

/**
 * Stand up an agreement post the way a handler would.
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
 * @return array
 */
function block( $status, $document = '' ) {
	return array(
		'status'           => $status,
		'kind'             => '',
		'accepted_on'      => '',
		'signed_on'        => '',
		'accepted_by'      => '',
		'document'         => $document,
		'submitted_on'     => '',
		'template_version' => '',
	);
}

/**
 * Which institutions the acting viewer is a live member of while a half of the gate is drawn.
 *
 * A member is a member of the institution being drawn, which is the ordinary case and the
 * one every state block below is written for; a manager is a member of nothing, because
 * `attach()` refuses an administrator. The third argument names the case worth its own
 * block: a member of another school, who is what the fence in front of both renders exists
 * to stop.
 *
 * @param string      $record     Institutions record ID being drawn.
 * @param bool        $can_manage Whether the viewer holds CAP_MANAGE.
 * @param string|null $member_of  The institution the viewer belongs to; null for the default.
 * @return array
 */
function memberships( $record, $can_manage, $member_of ) {
	if ( null !== $member_of ) {
		return '' === $member_of ? array() : array( $member_of );
	}

	return $can_manage ? array() : array( $record );
}

/**
 * Render the panel and hand back what it printed.
 *
 * @param string      $record     Institutions record ID.
 * @param bool        $can_manage Whether the viewer holds CAP_MANAGE.
 * @param string|null $member_of  The institution the viewer belongs to; null for `$record`.
 * @return string
 */
function panel( $record, $can_manage = false, $member_of = null ) {
	$GLOBALS['caps']      = $can_manage;
	$GLOBALS['member_of'] = memberships( $record, $can_manage, $member_of );
	ob_start();
	WPCPM_Institution_Panel::render( $record, array( 'can_manage' => $can_manage ) );
	return (string) ob_get_clean();
}

/**
 * Render the card and hand back what it printed.
 *
 * @param string      $record     Institutions record ID.
 * @param bool        $can_manage Whether the viewer holds CAP_MANAGE.
 * @param string|null $member_of  The institution the viewer belongs to; null for `$record`.
 * @return string
 */
function card( $record, $can_manage = false, $member_of = null ) {
	$GLOBALS['caps']      = $can_manage;
	$GLOBALS['member_of'] = memberships( $record, $can_manage, $member_of );
	ob_start();
	WPCPM_Institution_Agreement_Card::render( $record, array( 'can_manage' => $can_manage ) );
	return (string) ob_get_clean();
}

/**
 * Post the on-file form and say where the handler went.
 *
 * @param string $record Institutions record ID posted.
 * @param string $drive  The Drive link posted.
 * @param string $signed The date signed posted.
 * @param string $where  The location note posted.
 * @return string The redirect or the wp_die message.
 */
function on_file( $record, $drive, $signed = '', $where = '' ) {
	$_POST = array(
		'wpcpm_agreement_record'    => $record,
		'wpcpm_agreement_drive'     => $drive,
		'wpcpm_agreement_signed_on' => $signed,
		'wpcpm_agreement_where'     => $where,
	);

	$GLOBALS['journal'] = array();
	$GLOBALS['audit']   = array();
	$GLOBALS['patched'] = array();
	$GLOBALS['nonces']  = array();

	try {
		WPCPM_Institution_Agreement::handle_on_file();
		return 'returned';
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
}

/**
 * Post the bulk on-file form and report how it ended, the way `on_file()` does.
 *
 * @param string $drive What was typed as the link.
 * @param string $where The optional location note.
 * @return string
 */
function on_file_all( $drive, $where = '' ) {
	$_POST = array(
		'wpcpm_agreement_drive' => $drive,
		'wpcpm_agreement_where' => $where,
	);

	$GLOBALS['journal'] = array();
	$GLOBALS['audit']   = array();
	$GLOBALS['patched'] = array();
	$GLOBALS['nonces']  = array();

	try {
		WPCPM_Institution_Agreement::handle_on_file_all();
		return 'returned';
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
}

/**
 * The outcome slug waiting for the acting user, read from the meta the flash writes.
 *
 * Read rather than taken: `WPCPM_Flash::take()` memoizes per request, which is right for a
 * page and wrong for a file that runs twenty of them.
 *
 * @return string
 */
function flashed() {
	$pending = $GLOBALS['umeta'][ (int) $GLOBALS['uid'] ][ WPCPM_Flash::META ] ?? array();

	return isset( $pending[ WPCPM_Institutions::FLASH ] ) ? (string) $pending[ WPCPM_Institutions::FLASH ] : '';
}

$fixture = json_decode( file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-table-fields.json' ), true );
$drive   = 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz';
$rec_a   = 'recAAAAAAAAAAAAA1';
$rec_b   = 'recBBBBBBBBBBBBB2';
$country = 'recCOUNTRYAAAAAAA';

$GLOBALS['users'][9] = new WP_User( 9, 'Ada Manager', 'ada@example.test' );

/* ---- the constants pin the base's spelling ------------------------------ */

echo "=== The constants pin the base's spelling ===\n";

ck( 'the IDs this file types are record-shaped', array( WPCPM_Mentors_Sync::is_record_id( $rec_a ), WPCPM_Mentors_Sync::is_record_id( $rec_b ), WPCPM_Mentors_Sync::is_record_id( $country ) ), array( true, true, true ) );
ck( 'the on-file action is the one the form posts', WPCPM_Institution_Agreement::ACTION_ON_FILE, 'wpcpm_agreement_on_file' );
ck( 'On file is an Agreement Status choice', in_array( WPCPM_Institution_Agreement::AIRTABLE_ON_FILE, $fixture['choices']['Agreement Status'], true ), true );
ck( 'Legacy is an Agreement Kind choice', in_array( WPCPM_Institution_Agreement::AIRTABLE_KIND_LEGACY, $fixture['choices']['Agreement Kind'], true ), true );
ck( 'the site kind and the base kind are not the same string, and both are needed', array( WPCPM_Institution_Agreement::KIND_LEGACY, WPCPM_Institution_Agreement::AIRTABLE_KIND_LEGACY ), array( 'legacy', 'Legacy' ) );

$map    = WPCPM_Institutions_Sync::fields();
$stub   = array( $map['agr_status'], $map['agr_kind'], $map['agr_document'], $map['agr_accepted_on'], $map['agr_accepted_by'], $map['agr_signed_on'] );
ck( 'every column the write names is a field of the table', array_values( array_diff( $stub, $fixture['fields'] ) ), array() );

$screen = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );
preg_match( "/const FLASH\s*=\s*'([^']+)'/", $screen, $m );
ck( 'the flash channel stubbed here is the manager screen\'s own', WPCPM_Institutions::FLASH, $m[1] ?? '' );

$sync = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php' );
foreach ( array( 'agr_status', 'agr_kind', 'agr_document', 'agr_accepted_on', 'agr_accepted_by', 'agr_signed_on' ) as $key ) {
	ck( sprintf( 'the sync really maps %s to the name the stub gives it', $key ), false !== strpos( $sync, "'" . $key . "'" . str_repeat( ' ', max( 1, 17 - strlen( $key ) ) ) . "=> '" . $map[ $key ] . "'," ), true );
}

/* ---- every locked state says its own words ------------------------------- */

echo "\n=== Every locked state says its own words ===\n";

$ledes = array();

reset_world();
seed_index( $rec_a, $country );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Not started' ) );
$none = panel( $rec_a );

ck( 'none: says nothing is recorded', false !== strpos( $none, 'no Collaboration Agreement recorded' ), true );
ck( 'none: three numbered steps', substr_count( $none, '<li>' ), 3 );
ck( 'none: names both routes to a document in step one', false !== strpos( $none, 'or upload an agreement of your own' ), true );
ck( 'none: says the two controls are not here yet', false !== strpos( $none, 'Generating and uploading arrive in the next release' ), true );
ck( 'none: says a signed agreement can be recorded instead', false !== strpos( $none, 'does not need to sign again' ), true );
ck( 'none: no on-file form for a member', false !== strpos( $none, 'wpcpm_agreement_drive' ), false );
$ledes['none'] = $none;

reset_world();
seed_index( $rec_a, $country );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_GENERATED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array( WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04' ) );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Template generated' ) );
$generated = panel( $rec_a );

ck( 'generated: names the date and the template version', false !== strpos( $generated, 'You generated the program&#039;s agreement on 2025-09-01 (template 2025-11-04)' ), true );
ck( 'generated: still shows the three steps', substr_count( $generated, '<li>' ), 3 );
$ledes['generated'] = $generated;

reset_world();
seed_index( $rec_a, $country );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_OWN );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Awaiting review' ) );
$submitted = panel( $rec_a );

ck( 'submitted: says it arrived and that an email is coming either way', false !== strpos( $submitted, 'We received your signed agreement on 2025-09-01' ), true );
ck( 'submitted: asks the reader to do nothing, so no steps', substr_count( $submitted, '<li>' ), 0 );
ck( 'submitted: says download and withdraw are not here yet', false !== strpos( $submitted, 'withdrawing it arrive in the next release' ), true );
$ledes['submitted'] = $submitted;

reset_world();
seed_index( $rec_a, $country );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_RETURNED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
	WPCPM_Institution_Agreement::META_NOTE       => "Page 4 is not signed.\nPlease send it again.",
	WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
	WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-30',
) );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Returned' ) );
$returned = panel( $rec_a );

ck( 'returned: says a manager returned it', false !== strpos( $returned, 'returned your agreement, with this note' ), true );
ck( 'returned: the note is printed verbatim', false !== strpos( $returned, 'Page 4 is not signed.' ), true );
ck( 'returned: and its second line too', false !== strpos( $returned, 'Please send it again.' ), true );
ck( 'returned: signed and dated', false !== strpos( $returned, 'Ada Manager, 2026-08-30' ), true );
$ledes['returned'] = $returned;

reset_world();
seed_index( $rec_a, $country );
$GLOBALS['opts'][ WPCPM_Countries::OPTION ] = array(
	'v'    => WPCPM_Countries::VERSION,
	'read' => 1756600000,
	'rows' => array( $country => array( 'name' => 'Poland', 'manager' => 'recTEAMAAAAAAAA1', 'email' => 'poland@example.org', 'calendly' => '' ) ),
);
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_REVOKED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
	WPCPM_Institution_Agreement::META_NOTE       => 'The partnership ended in July.',
	WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
	WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-31',
) );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Revoked' ) );
$revoked = panel( $rec_a );

ck( 'revoked: says the program revoked it and what is left', false !== strpos( $revoked, 'revoked this institution&#039;s agreement' ), true );
ck( 'revoked: the note verbatim', false !== strpos( $revoked, 'The partnership ended in July.' ), true );
ck( 'revoked: names the country contact\'s address', false !== strpos( $revoked, 'Your program contact for Poland is poland@example.org.' ), true );
$ledes['revoked'] = $revoked;

$first_lines = array();
foreach ( $ledes as $state => $html ) {
	preg_match( '/<p class="wpcpm-agreement-panel__lede">(.*?)<\/p>/s', $html, $lede );
	$first_lines[ $state ] = $lede[1] ?? '';
}

ck( 'all five states rendered a lede', count( array_filter( $first_lines ) ), 5 );
ck( 'and no two of them are the same words', count( array_unique( $first_lines ) ), 5 );
ck( 'every panel printed its read time', count( array_filter( $ledes, function ( $html ) { return false !== strpos( $html, 'Agreement state: read' ); } ) ), 5 );

/* ---- the sixth case: the two sources disagree ---------------------------- */

echo "\n=== An accepted document under a base that disagrees is not a blank page ===\n";

reset_world();
seed_index( $rec_a, $country );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_ACCEPTED, WPCPM_Institution_Agreement::KIND_OWN );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Revoked' ) );

ck( 'the gate is closed', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );
$stuck = panel( $rec_a );
ck( 'the panel is not empty', '' !== trim( $stuck ), true );
ck( 'and it says the program has to settle it', false !== strpos( $stuck, 'does not agree with it yet' ), true );
ck( 'the card says nothing at all', card( $rec_a ), '' );

/* ---- a settled institution gets the card, not the panel ------------------ */

echo "\n=== A settled institution gets the card, not the panel ===\n";

reset_world();
seed_index( $rec_a, $country );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );

ck( 'the grid route settled it', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'the panel prints nothing', panel( $rec_a ), '' );

$grid_card = card( $rec_a );
ck( 'the card names the acceptance date', false !== strpos( $grid_card, 'Accepted on ' . gmdate( 'Y-m-d', $GLOBALS['clock'] ) . '.' ), true );
ck( 'the card says it came from the program\'s own records', false !== strpos( $grid_card, 'Recorded from the program&#039;s own records' ), true );
ck( 'and not that it was signed here', false !== strpos( $grid_card, 'Signed and accepted through this site' ), false );
ck( 'a member is not sent to the program\'s Drive folder', false !== strpos( $grid_card, $drive ), false );
ck( 'a manager is', false !== strpos( card( $rec_a, true ), $drive ), true );

/* ---- neither half draws another institution's agreement ------------------ */

echo "\n=== Neither half draws another institution's agreement ===\n";

// Everything both halves print is one institution's: the state of its agreement, a program
// manager's note verbatim, its country contact's address, and on the card the acceptance
// date and the program's Drive folder. So each render decides first and acts on what came
// back (design spec 5.4), rather than trusting the one decision the dashboard shell makes
// before it calls them. The bug this block pins is a second caller - a shortcode, a theme
// template, a later phase's screen - that renders a half without the shell's own check.

reset_world();
seed_index( $rec_a, $country );
$GLOBALS['opts'][ WPCPM_Countries::OPTION ] = array(
	'v'    => WPCPM_Countries::VERSION,
	'read' => 1756600000,
	'rows' => array( $country => array( 'name' => 'Poland', 'manager' => 'recTEAMAAAAAAAA1', 'email' => 'poland@example.org', 'calendly' => '' ) ),
);
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_RETURNED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
	WPCPM_Institution_Agreement::META_NOTE       => 'Page 4 is not signed.',
	WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
	WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-30',
) );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Returned' ) );

$stranger = panel( $rec_a, false, $rec_b );

ck( 'a member of another institution gets nothing from the panel', $stranger, '' );
ck( 'not the state this one\'s agreement is in', false !== strpos( $stranger, 'returned your agreement' ), false );
ck( 'not the program manager\'s note', false !== strpos( $stranger, 'Page 4 is not signed.' ), false );
ck( 'and not the country contact\'s address', false !== strpos( $stranger, 'poland@example.org' ), false );
ck( 'a member of this one gets all three', array(
	false !== strpos( panel( $rec_a ), 'returned your agreement' ),
	false !== strpos( panel( $rec_a ), 'Page 4 is not signed.' ),
	false !== strpos( panel( $rec_a ), 'poland@example.org' ),
), array( true, true, true ) );
ck( 'and so does a program manager, who is a member of nothing', false !== strpos( panel( $rec_a, true ), 'Page 4 is not signed.' ), true );

// The panel asks for the one ungated action, and it has to: a locked member is exactly who
// it is drawn for, and any other action would refuse every one of them inside ground_member().
$GLOBALS['decisions'] = array();
panel( $rec_a, false, $rec_b );
ck( 'the panel asked the fence for the agreement, on this institution', $GLOBALS['decisions'], array( array( 'agreement', $rec_a ) ) );

reset_world();
seed_index( $rec_a, $country );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );

$stranger_card = card( $rec_a, false, $rec_b );

ck( 'a member of another institution gets nothing from the card', $stranger_card, '' );
ck( 'not the acceptance date', false !== strpos( $stranger_card, 'Accepted on' ), false );
ck( 'a member of this one gets it', false !== strpos( card( $rec_a ), 'Accepted on' ), true );
ck( 'and a program manager gets it with the Drive link', array(
	false !== strpos( card( $rec_a, true ), 'Accepted on' ),
	false !== strpos( card( $rec_a, true ), $drive ),
), array( true, true ) );

// The card is the foot of the dashboard rather than the agreement panel, so it asks the
// action the roster and the People card beside it ask: a viewer who may not see this
// institution's students may not see which document opened its account either.
$GLOBALS['decisions'] = array();
card( $rec_a, false, $rec_b );
ck( 'the card asked the fence for the dashboard it sits on', $GLOBALS['decisions'], array( array( 'view_roster', $rec_a ) ) );

/* ---- the on-file route: refusals write nothing --------------------------- */

echo "\n=== The on-file route refuses before it writes ===\n";

reset_world();
seed_index( $rec_a, $country );
$GLOBALS['uid'] = 9;

ck( 'an example.com link is refused', on_file( $rec_a, 'https://example.com/agreement.pdf' ), 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions' );
ck( 'and named: the message asks for the Drive link', flashed(), 'agreement-link' );
ck( 'the words say what is wanted', WPCPM_Institution_Panel::messages()['agreement-link'][1], 'Nothing was recorded. Paste the Drive link to the folder or the file: it has to be an https address on drive.google.com or docs.google.com.' );
ck( 'nothing was written anywhere', array( $GLOBALS['journal'], $GLOBALS['posts'], WPCPM_Institution_Agreement::is_settled( $rec_a ) ), array( array(), array(), false ) );
ck( 'the nonce was keyed to the institution', $GLOBALS['nonces'], array( 'wpcpm_agreement_on_file_' . $rec_a ) );
ck( 'and the policy was asked before any of it', $GLOBALS['decisions'], array( array( 'agreement', $rec_a ) ) );

ck( 'a plain http Drive link is refused too', on_file( $rec_a, 'http://drive.google.com/file/d/1AbC' ), 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions' );
ck( 'nothing written', array( $GLOBALS['journal'], flashed() ), array( array(), 'agreement-link' ) );

ck( 'a record the index does not hold is refused', on_file( $rec_b, $drive ), 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions' );
ck( 'and named', array( flashed(), $GLOBALS['journal'] ), array( 'agreement-unknown', array() ) );

$GLOBALS['allow'] = false;
ck( 'a decision that refuses dies with the one refusal', on_file( $rec_a, $drive ), 'wp_die: That record is not on your roster.' );
ck( 'and wrote nothing', $GLOBALS['journal'], array() );
$GLOBALS['allow'] = true;

$GLOBALS['caps'] = false;
ck( 'no capability dies 403 before the nonce is read', on_file( $rec_a, $drive ), 'wp_die: You do not have permission to manage the program.' );
ck( 'and no nonce was read', $GLOBALS['nonces'], array() );
$GLOBALS['caps'] = true;

/* ---- the on-file route: Airtable first ----------------------------------- */

echo "\n=== Airtable is written first, and a failure writes nothing here ===\n";

reset_world();
seed_index( $rec_a, $country );
$GLOBALS['uid']      = 9;
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said 503.' );

ck( 'a failed PATCH bounces', on_file( $rec_a, $drive ), 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions' );
ck( 'and is named as the base refusing, not as a site error', flashed(), 'agreement-airtable' );
ck( 'the base was asked, and nothing followed it', $GLOBALS['journal'], array( 'airtable' ) );
ck( 'no post, no option, no open account', array( count( $GLOBALS['posts'] ), WPCPM_Institution_Agreement::option( $rec_a ), WPCPM_Institution_Agreement::is_settled( $rec_a ) ), array( 0, null, false ) );

$GLOBALS['airtable'] = array();
ck( 'a PATCH that updated nothing is a failure too', on_file( $rec_a, $drive ), 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions' );
ck( 'named the same way, and nothing written', array( flashed(), count( $GLOBALS['posts'] ) ), array( 'agreement-airtable', 0 ) );

/* ---- the on-file route: the happy path ----------------------------------- */

echo "\n=== A Drive link opens the account, in the one order that is safe ===\n";

reset_world();
seed_index( $rec_a, $country );
$GLOBALS['uid']     = 9;
$GLOBALS['referer'] = 'https://example.test/institution-dashboard/';

ck( 'the manager is returned to where the form was', on_file( $rec_a, $drive, '2023-10-02', 'second folder, the 2025 copy' ), 'redirect: https://example.test/institution-dashboard/' );
ck( 'Airtable first, then the post, then the log row, then the option', $GLOBALS['journal'], array( 'airtable', 'post', 'audit', 'option' ) );
ck( 'and the lock it took is released, so the rebuild that follows is not skipped', isset( $GLOBALS['opts'][ WPCPM_Institution_Agreement::lock_name( $rec_a ) ] ), false );
ck( 'and the outcome says the account is open', flashed(), 'agreement-on-file' );
ck( 'the institution is settled', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

$patch = $GLOBALS['patched'][0];
ck( 'the PATCH named the institutions table and the one record', array( $patch[0], count( $patch[1] ), $patch[1][0]['id'] ), array( 'tbl4V0FEbzRP7I2w2', 1, $rec_a ) );
ck( 'and carried exactly T7\'s columns',
    array_keys( $patch[1][0]['fields'] ),
    array( 'Agreement Status', 'Agreement Kind', 'Agreement Document', 'Agreement Accepted On', 'Agreement Accepted By', 'Agreement Signed On' ) );
ck( 'with the base\'s own spellings and today\'s date',
    array( $patch[1][0]['fields']['Agreement Status'], $patch[1][0]['fields']['Agreement Kind'], $patch[1][0]['fields']['Agreement Accepted On'], $patch[1][0]['fields']['Agreement Signed On'], $patch[1][0]['fields']['Agreement Accepted By'] ),
    array( 'On file', 'Legacy', '2025-09-01', '2023-10-02', 'Ada Manager' ) );

$post_id = array_key_first( $GLOBALS['posts'] );
ck( 'the post is a private accepted legacy row for this institution',
    array(
        $GLOBALS['posts'][ $post_id ]->post_type,
        $GLOBALS['posts'][ $post_id ]->post_status,
        get_post_meta( $post_id, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
        get_post_meta( $post_id, WPCPM_Institution_Agreement::META_STATE, true ),
        get_post_meta( $post_id, WPCPM_Institution_Agreement::META_KIND, true ),
    ),
    array( 'wpcpm_agreement', 'private', $rec_a, 'accepted', 'legacy' ) );
ck( 'it holds the link, the signed date and who recorded it',
    array(
        get_post_meta( $post_id, WPCPM_Institution_Agreement::META_DRIVE_URL, true ),
        get_post_meta( $post_id, WPCPM_Institution_Agreement::META_SIGNED_ON, true ),
        get_post_meta( $post_id, WPCPM_Institution_Agreement::META_DECIDED_BY, true ),
        get_post_meta( $post_id, WPCPM_Institution_Agreement::META_DECIDED_AT, true ),
    ),
    array( $drive, '2023-10-02', 9, '2025-09-01' ) );
ck( 'the location note is kept where a document\'s note lives', get_post_meta( $post_id, WPCPM_Institution_Agreement::META_NOTE, true ), 'second folder, the 2025 copy' );

// Design spec 5.6: membership and agreement events are rows of the same type, and this was
// the one write on the institution side that left none. An account that opens with nothing
// in the log saying who opened it, or on what ground, is what this block prevents.
ck( 'exactly one log row was written', count( $GLOBALS['audit'] ), 1 );
ck( 'it is this institution\'s, about the document, by the manager, on the ground the fence gave',
    array(
        $GLOBALS['audit'][0]['kind'],
        $GLOBALS['audit'][0]['institution'],
        $GLOBALS['audit'][0]['subject'],
        $GLOBALS['audit'][0]['actor'],
        $GLOBALS['audit'][0]['ground'],
        $GLOBALS['audit'][0]['evidence'],
    ),
    array( WPCPM_Institution_Agreement::LOG_ON_FILE, $rec_a, (string) $post_id, 9, 'manager', 'index' ) );
ck( 'and carries the facts of the recording, with no prose among them',
    $GLOBALS['audit'][0]['data'],
    array( 'kind' => 'legacy', 'drive' => $drive, 'signed_on' => '2023-10-02' ) );
ck( 'the prose is in the message, where the log caps it', false !== strpos( $GLOBALS['audit'][0]['message'], 'recorded as on file' ), true );
ck( 'the event says a person did it here, not that the base was read', get_post_meta( $post_id, WPCPM_Institution_Agreement::META_EVENT, false )[0]['event'], 'recorded on file' );
ck( 'so the summary calls the route the site', WPCPM_Institution_Agreement::summary( $rec_a )['route'], 'site' );
ck( 'and the option holds the link with no name on it',
    array( WPCPM_Institution_Agreement::option( $rec_a )['drive_url'], false !== strpos( serialize( $GLOBALS['opts'] ), 'Ada Manager' ) ),
    array( $drive, false ) );

$site_card = card( $rec_a );
ck( 'the card at the foot names the date', false !== strpos( $site_card, 'Accepted on 2025-09-01.' ), true );
ck( 'and says a manager recorded it here from the program\'s copy', false !== strpos( $site_card, 'Recorded on this site by a program manager' ), true );
ck( 'the panel has nothing left to say', panel( $rec_a ), '' );

/* ---- one accepted document at a time ------------------------------------- */

echo "\n=== A second recording over a standing agreement is refused ===\n";

// The panel above left the viewer without the capability; the manager is back.
$GLOBALS['caps']    = true;
$GLOBALS['journal'] = array();
ck( 'the second attempt bounces', on_file( $rec_a, 'https://docs.google.com/document/d/1XyZ' ), 'redirect: https://example.test/institution-dashboard/' );
ck( 'and is named', flashed(), 'agreement-standing' );
ck( 'the base was never asked', $GLOBALS['journal'], array() );
ck( 'the standing document is still the only one', count( $GLOBALS['posts'] ), 1 );
ck( 'and still points at the first link', WPCPM_Institution_Agreement::option( $rec_a )['drive_url'], $drive );

/* ---- the outcome is printed wherever the manager lands ------------------- */

echo "\n=== The outcome is printed by whichever half is drawn ===\n";

$GLOBALS['uid'] = 11;
$GLOBALS['users'][11] = new WP_User( 11, 'Bo Manager', 'bo@example.test' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-on-file', 11 );
ck( 'a settled dashboard prints it on the card', false !== strpos( card( $rec_a, true ), 'The agreement is recorded as on file.' ), true );

reset_world();
seed_index( $rec_b, '' );
$GLOBALS['uid']       = 12;
$GLOBALS['users'][12] = new WP_User( 12, 'Cy Manager', 'cy@example.test' );
WPCPM_Institution_Agreement::rebuild( $rec_b, block( 'Not started' ) );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-link', 12 );
$refused = panel( $rec_b, true );
ck( 'a locked dashboard prints it on the panel', false !== strpos( $refused, 'Paste the Drive link' ), true );
ck( 'and the manager gets the form to try again', false !== strpos( $refused, 'name="wpcpm_agreement_drive"' ), true );
ck( 'the form posts to the on-file action', false !== strpos( $refused, 'value="wpcpm_agreement_on_file"' ), true );
ck( 'keyed to this institution', false !== strpos( $refused, 'value="nonce-wpcpm_agreement_on_file_' . $rec_b . '"' ), true );
ck( 'and the manager is not told to write to themselves', false !== strpos( $refused, 'Your program contact' ), false );

// A fresh viewer for each flash: `WPCPM_Flash::take()` memoizes per user and channel, so
// the same manager cannot be shown two outcomes in one process.
$GLOBALS['uid']       = 13;
$GLOBALS['users'][13] = new WP_User( 13, 'Di Manager', 'di@example.test' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-busy', 13 );
$busy = panel( $rec_b, true );

ck( 'a recording that lost the race to another write says nothing was recorded', false !== strpos( $busy, 'Nothing was recorded. Another write to this institution' ), true );
ck( 'and names the two writers it could have lost to', false !== strpos( $busy, 'from a sync or from somebody else pressing the same button' ), true );
ck( 'as an error rather than as news', false !== strpos( $busy, 'wpcpm-agreement-panel__message--error' ), true );
ck( 'and the form is still there to try again with', false !== strpos( $busy, 'name="wpcpm_agreement_drive"' ), true );

/* ---- two recordings in flight cannot both open the account --------------- */

echo "\n=== Two recordings in flight cannot both open the account ===\n";

// Without the lock, two managers pressing Record it in the same second both read "no
// accepted post", both PATCH the base and both insert an accepted legacy row: the duplicate
// that makes "an accepted one stands" mean two different things to T3, T6 and T10. The held
// row stands in for the other request, because `add_option()` is the test-and-set.
reset_world();
seed_index( $rec_a, $country );
$GLOBALS['uid']     = 9;
$GLOBALS['referer'] = 'https://example.test/institution-dashboard/';

// Seeded with real time, not the fake clock: `lock()` clears a lock older than LOCK_TIMEOUT,
// and the fake clock is a year behind, so a row written from it would read as abandoned.
$GLOBALS['opts'][ WPCPM_Institution_Agreement::lock_name( $rec_a ) ] = time();

ck( 'a recording that arrives while another write holds the lock bounces', on_file( $rec_a, $drive ), 'redirect: https://example.test/institution-dashboard/' );
ck( 'and is named as a race, not as a failure of the base', flashed(), 'agreement-busy' );
ck( 'nothing was written anywhere: no PATCH, no post, no log row', array( $GLOBALS['journal'], count( $GLOBALS['posts'] ), $GLOBALS['audit'] ), array( array(), 0, array() ) );
ck( 'and the lock it did not take is still the other request\'s', isset( $GLOBALS['opts'][ WPCPM_Institution_Agreement::lock_name( $rec_a ) ] ), true );

delete_option( WPCPM_Institution_Agreement::lock_name( $rec_a ) );

ck( 'once that write finishes, the same press is recorded', on_file( $rec_a, $drive ), 'redirect: https://example.test/institution-dashboard/' );
ck( 'and the account opens', array( flashed(), WPCPM_Institution_Agreement::is_settled( $rec_a ), count( $GLOBALS['posts'] ) ), array( 'agreement-on-file', true, 1 ) );

// Every refusal after the lock is taken has to give it back, or one bad press would shut an
// institution out of the on-file route until LOCK_TIMEOUT expired.
$lock_b = WPCPM_Institution_Agreement::lock_name( $rec_b );

reset_world();
seed_index( $rec_b, '' );
$GLOBALS['uid'] = 9;

ck( 'the first recording opens the account', array( on_file( $rec_b, $drive ), flashed() ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-on-file' ) );
ck( 'a second press over it is refused as standing, with the lock given back', array( on_file( $rec_b, $drive ), flashed(), isset( $GLOBALS['opts'][ $lock_b ] ) ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-standing', false ) );

reset_world();
seed_index( $rec_b, '' );
$GLOBALS['uid']      = 9;
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said 503.' );

ck( 'a failed PATCH gives it back too', array( on_file( $rec_b, $drive ), flashed(), isset( $GLOBALS['opts'][ $lock_b ] ) ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-airtable', false ) );

reset_world();
seed_index( $rec_b, '' );
$GLOBALS['uid']        = 9;
$GLOBALS['post_fails'] = true;

ck( 'and so does a post that will not save', array( on_file( $rec_b, $drive ), flashed(), isset( $GLOBALS['opts'][ $lock_b ] ) ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-not-saved', false ) );

$GLOBALS['post_fails'] = false;

/* ---- the source itself ---------------------------------------------------- */

echo "\n=== The source itself ===\n";

/* ---- the bulk form of the same route ------------------------------------- */

echo "\n=== Recording every Confirmed institution as signed, with one link ===\n";

// Three Confirmed institutions in the index: two with nothing recorded, one already settled
// through the single route. The bulk form must record the two, leave the one, and write for
// each of the two exactly what the single route writes.
$bulk_a = 'recBULK0000000001';
$bulk_b = 'recBULK0000000002';
$bulk_c = 'recBULK0000000003';

reset_world();
$rows = array();
foreach ( array( $bulk_a => 'Universidad Alpha', $bulk_b => 'Universidad Beta', $bulk_c => 'Universidad Gamma' ) as $id => $name ) {
	$row                  = WPCPM_Institutions_Index::empty_row();
	$row['record_id']     = $id;
	$row['name']          = $name;
	$row['stage']         = 'Confirmed';
	$row['contact_email'] = 'contact@example.edu';
	$rows[ $id ]          = $row;
}
WPCPM_Institutions_Index::write( $rows, 1756600000 );
$GLOBALS['uid'] = 9;

ck( 'the third is settled first, through the single route', array( on_file( $bulk_c, $drive ), flashed() ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-on-file' ) );
ck( 'so two of the three have nothing recorded', array( WPCPM_Institution_Agreement::is_settled( $bulk_a ), WPCPM_Institution_Agreement::is_settled( $bulk_b ), WPCPM_Institution_Agreement::is_settled( $bulk_c ) ), array( false, false, true ) );

$GLOBALS['caps'] = false;
ck( 'without the capability the bulk form is refused before anything is read', substr( on_file_all( $drive ), 0, 7 ), 'wp_die:' );
$GLOBALS['caps'] = true;

ck( 'a link that is not a Drive link is refused by name, and nothing is patched', array( on_file_all( 'https://example.com/agreements' ), flashed(), count( $GLOBALS['patched'] ) ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-link', 0 ) );

$ended = on_file_all( $drive, 'Signed PDFs in the program Drive' );
ck( 'with a Drive link the run completes and says so', array( $ended, flashed() ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-on-file-all' ) );
ck( 'it patched the base once per institution with nothing recorded, and not for the settled one',
	array_map( function ( $call ) { return $call[1][0]['id']; }, $GLOBALS['patched'] ),
	array( $bulk_a, $bulk_b ) );
ck( 'each patch carries the on-file status, the legacy kind and the link',
	array_unique( array_map( function ( $call ) use ( $drive ) {
		$cells = $call[1][0]['fields'];
		return in_array( WPCPM_Institution_Agreement::AIRTABLE_ON_FILE, $cells, true ) && in_array( WPCPM_Institution_Agreement::AIRTABLE_KIND_LEGACY, $cells, true ) && in_array( $drive, $cells, true );
	}, $GLOBALS['patched'] ) ),
	array( true ) );
ck( 'and wrote one audit row per institution, on the manager ground, of the on-file kind',
	array_map( function ( $row ) { return array( $row['kind'], $row['institution'], $row['ground'] ); }, $GLOBALS['audit'] ),
	array( array( 'agreement_on_file', $bulk_a, 'manager' ), array( 'agreement_on_file', $bulk_b, 'manager' ) ) );
ck( 'both are settled afterwards, and the third is as it was', array( WPCPM_Institution_Agreement::is_settled( $bulk_a ), WPCPM_Institution_Agreement::is_settled( $bulk_b ), WPCPM_Institution_Agreement::is_settled( $bulk_c ) ), array( true, true, true ) );
$tally = WPCPM_Institution_Agreement::last_on_file_all();
ck( 'the tally says two recorded, none skipped, none failed, none left', array( $tally['recorded'], $tally['skipped'], $tally['failed'], $tally['left'], $tally['actor'] ), array( 2, 0, array(), 0, 9 ) );
ck( 'and the tally is stored without autoload', $GLOBALS['opts_autoload'][ WPCPM_Institution_Agreement::OPTION_ON_FILE_ALL ] ?? 'unset', false );
ck( 'the outcome message reads the tally back', false !== strpos( WPCPM_Institution_Panel::on_file_all_summary(), '2 institutions recorded as signed' ), true );

ck( 'pressed again with nothing left to record, it says so and patches nothing', array( on_file_all( $drive ), flashed(), count( $GLOBALS['patched'] ) ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-all-none', 0 ) );

$panel_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php' );
$agr_src   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );

ck( 'the file declares both halves of the gate',
    array( preg_match( '/^class WPCPM_Institution_Panel\b/m', $panel_src ), preg_match( '/^class WPCPM_Institution_Agreement_Card\b/m', $panel_src ) ),
    array( 1, 1 ) );
ck( 'the panel writes no option of its own', preg_match( '/update_option\(/', $panel_src ), 0 );

// Both halves decide before they draw, read off the source as well as run above. A render
// that asked the fence after its first line of output would still pass the runtime block
// (the refused viewer is handed a stray heading and nothing else, which is easy to miss),
// and the action each names is the other half of the same decision: the panel's is the one
// the gate does not apply to, the card's is the one the dashboard around it uses.
foreach ( array(
	'WPCPM_Institution_Panel'          => 'ACT_AGREEMENT',
	'WPCPM_Institution_Agreement_Card' => 'ACT_VIEW_ROSTER',
) as $half => $action ) {
	$render = substr( $panel_src, (int) strpos( $panel_src, 'class ' . $half ) );
	$render = substr( $render, (int) strpos( $render, 'public static function render(' ) );
	$render = substr( $render, 0, (int) strpos( $render, "\n\t}\n" ) );
	$decide = strpos( $render, 'WPCPM_Institution_Policy::decide(' );
	// Both output forms, because the panel opens with `printf()` and the card with `echo`.
	// An empty list would mean the method body was not found at all, and reads here as a
	// failing assertion rather than as a fatal inside min().
	$prints = array_filter( array( strpos( $render, 'printf(' ), strpos( $render, 'echo ' ) ), 'is_int' );

	ck( sprintf( '%s::render() asks the fence before it prints anything', $half ), is_int( $decide ) && $prints && $decide < min( $prints ), true );
	ck( sprintf( '%s::render() names the action for what it draws', $half ), false !== strpos( $render, 'WPCPM_Institution_Policy::' . $action ), true );
	ck( sprintf( '%s::render() prints no refusal text of its own', $half ), false !== strpos( $render, 'refusal(' ), false );
}
ck( 'every option write in the agreement class is still non-autoloaded', preg_match_all( '/update_option\(/', $agr_src ), preg_match_all( '/update_option\([^;]*?,\s*false\s*\);/s', $agr_src ) );
ck( 'neither file names accessibility', array( strpos( $panel_src, 'accessibility' ), strpos( $agr_src, 'accessibility' ) ), array( false, false ) );
ck( 'no em dash or en dash in either file', array( preg_match( '/[\x{2013}\x{2014}]/u', $panel_src ), preg_match( '/[\x{2013}\x{2014}]/u', $agr_src ) ), array( 0, 0 ) );
/**
 * One method's body out of a source file, from its signature to its closing brace.
 *
 * @param string $src  The file.
 * @param string $name The method name.
 * @return string
 */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

// The single route reads its form in one method and writes in another, and the bulk route
// reads two fields of its own: the counts are per method, so a reader added to the wrong one
// is a failing assertion rather than a total that still happens to add up.
$single = method_body( $agr_src, 'handle_on_file' );
$bulk   = method_body( $agr_src, 'handle_on_file_all' );
$write  = method_body( $agr_src, 'record_on_file' );

ck( 'the handlers read the form with the posted_* readers, never the query string',
    array( substr_count( $single, 'WPCPM_Request::posted_text(' ), substr_count( $bulk, 'WPCPM_Request::posted_text(' ), substr_count( $agr_src, 'WPCPM_Request::text(' ) ),
    array( 4, 2, 0 ) );
ck( 'the write itself reads no form at all', substr_count( $write, 'WPCPM_Request::' ), 0 );

// The order the design spec fixes, read off the source as well as run above: a reader
// changing either method has the sequence in front of them either way. The handler ends by
// calling the write, so the two bodies in that order are the one sequence.
$body    = $single . $write;
$offsets = array(
	'capability' => strpos( $body, 'current_user_can(' ),
	'nonce'      => strpos( $body, 'check_admin_referer(' ),
	'decide'     => strpos( $body, 'WPCPM_Institution_Policy::decide(' ),
	'link'       => strpos( $body, 'self::is_drive_link(' ),
	'lock'       => strpos( $body, 'self::lock(' ),
	'airtable'   => strpos( $body, 'update_records(' ),
	'post'       => strpos( $body, 'wp_insert_post(' ),
	'option'     => strpos( $body, 'self::rebuild(' ),
);
$sorted = $offsets;
asort( $sorted );

ck( 'the route reads in the order the design fixes', array_keys( $sorted ), array_keys( $offsets ) );
ck( 'and the bulk route checks the link before it loops', strpos( $bulk, 'self::is_drive_link(' ) < strpos( $bulk, 'foreach' ), true );

// Three refusals after the lock, plus the release before the rebuild. Counted rather than
// read, because the one that goes missing is the one nobody notices until an institution
// cannot be recorded for five minutes.
ck( 'every exit after the lock gives it back', substr_count( $write, 'self::unlock(' ), 4 );
ck( 'and the log row is written while the lock is still held', strpos( $write, 'WPCPM_Institution_Audit::record(' ) < strrpos( $write, 'self::unlock(' ), true );
ck( 'the write never redirects, which is what lets the bulk route loop it', strpos( $write, 'bounce_on_file(' ), false );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
