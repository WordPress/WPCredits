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
 * - **`render_manager_upload()` decides everything for itself.** Its call site is one row in
 *   a table of every institution, so the only thing it takes from the caller is the record ID:
 *   the capability, the fence, and the state that would make the handler refuse are all read
 *   here. A row drawn for B names B in the field and in the nonce and does not name A
 *   anywhere; a row whose institution already has a copy in review is given the sentence and
 *   the way out rather than a form that would be refused; a settled one still takes T10's
 *   replacement.
 * - **One mechanism per form for a manager acting on behalf.** The generate and Regenerate
 *   forms put the switcher on the action URL, where `resolve_institution()` reads it, and
 *   carry no record field, because that class stopped reading one. The upload and on-file
 *   forms do the opposite, because their handlers read the posted record themselves. Both
 *   sides of each pair are read here, off the two sources, since a mechanism only one half of
 *   a pair implements is exactly the defect Phase 3 shipped.
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
$GLOBALS['languages']  = array( 'en' );
$GLOBALS['members']    = array();

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
function add_query_arg( $args, $url = '' ) {
	$sep = false === strpos( (string) $url, '?' ) ? '?' : '&';
	return (string) $url . $sep . http_build_query( (array) $args );
}
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	$sep = false === strpos( (string) $url, '?' ) ? '?' : '&';
	return (string) $url . $sep . $name . '=nonce-' . $action;
}
function size_format( $bytes, $decimals = 0 ) {
	$bytes = (int) $bytes;
	if ( $bytes >= 1048576 ) { return number_format( $bytes / 1048576, max( 1, $decimals ) ) . ' MB'; }
	if ( $bytes >= 1024 ) { return number_format( $bytes / 1024, $decimals ) . ' KB'; }
	return $bytes . ' B';
}
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
		/**
		 * One document's subject, from the post's own stamp and never from the screen.
		 *
		 * The download link and the withdraw form act on a post ID rather than on a
		 * record, and they are drawn from three screens. Reading the institution off the
		 * post here is what makes "a member of B withdrawing A's post gets the one
		 * refusal" true whichever screen drew the control.
		 */
		public static function subject_post( WP_Post $post, $meta_key ) {
			$record = (string) get_post_meta( $post->ID, $meta_key, true );

			return array(
				'type'            => 'post',
				'id'              => (string) $post->ID,
				'institution_ids' => '' !== $record ? array( $record ) : array(),
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
	/**
	 * Enough of the settings for the one table the write names and the one number the
	 * upload form and its refusal print. Both are pinned against the real defaults below.
	 */
	class WPCPM_Settings {
		public static function get() {
			return array(
				'institutions_table' => 'tbl4V0FEbzRP7I2w2',
				'agreement_max_mb'   => 10,
			);
		}
		public static function get_value( $key, $fallback = null ) {
			$settings = self::get();
			return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
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

if ( ! class_exists( 'WPCPM_Agreement_Generate' ) ) {
	/**
	 * The generate route, for the one thing the panel needs of it: the action name.
	 *
	 * A stub rather than the real class, which reaches the template files, the ceiling, the
	 * mail queue and the Airtable client to do its job and would drag all four into a suite
	 * about markup. The string is pinned against the real file below, byte for byte, because
	 * a form posting to an action nobody registered is a page that silently does nothing.
	 */
	class WPCPM_Agreement_Generate {
		const ACTION_GENERATE = 'wpcpm_agreement_generate';
	}
}

if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/**
	 * The roster, for the one thing the forms need of it: the switcher's argument name.
	 *
	 * The real class reads the Students table, the membership store and the policy to do its
	 * job, none of which a suite about markup wants. What the forms borrow is the name a
	 * manager's institution arrives under, and it is pinned against the real file below: a
	 * form posting an argument `resolve_institution()` does not read is a manager acting on
	 * whichever institution happens to be their fallback.
	 */
	class WPCPM_Institution_Roster {
		const ARG_VIEW = 'wpcpm_institution_view';
	}
}

if ( ! class_exists( 'WPCPM_Agreement_Template' ) ) {
	/** The template store, for the one question the generate form asks it. */
	class WPCPM_Agreement_Template {
		public static function languages() {
			return (array) $GLOBALS['languages'];
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/**
	 * The membership store, at the one method the review block and `review_facts()` read.
	 *
	 * The count matters and the accounts do not: what the Accept confirm has to say is how
	 * many people an acceptance emails, which is the whole reason the design puts a number
	 * in a dialog rather than "the institution".
	 */
	class WPCPM_Institution_Members {
		public static function members_of( $record_id ) {
			$rows = $GLOBALS['members'][ (string) $record_id ] ?? array();
			$out  = array();
			foreach ( (array) $rows as $i => $name ) {
				$out[] = new WP_User( 700 + $i, $name, 'member' . $i . '@example.edu' );
			}
			return $out;
		}
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
	$GLOBALS['members']    = array();
	// `$GLOBALS['languages']` is deliberately not reset here. It configures the template
	// stub rather than describing the world, the way `$GLOBALS['users']` does, and a block
	// that sets it and then seeds an institution would otherwise lose it before rendering.
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

$GLOBALS['users'][7] = new WP_User( 7, 'Wera Rektor', 'wera@example.edu' );
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
ck( 'none: offers both controls rather than promising them', array( false !== strpos( $none, 'value="wpcpm_agreement_generate"' ), false !== strpos( $none, 'value="wpcpm_agreement_upload"' ) ), array( true, true ) );
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
ck( 'submitted: offers the copy and the way to take it back', array( false !== strpos( $submitted, 'action=wpcpm_agreement_download' ), false !== strpos( $submitted, 'value="wpcpm_agreement_withdraw"' ) ), array( true, true ) );
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

ck( 'a recording that lost the race to another write says nothing was saved', false !== strpos( $busy, 'Nothing was saved. Either another write to this institution' ), true );
ck( 'and names the two writers it could have lost to', false !== strpos( $busy, 'from a sync or from somebody else pressing the same button' ), true );
ck( 'and the other producer of the same slug, the daily ceiling', false !== strpos( $busy, 'used up the documents one day allows it to generate and upload' ), true );
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


/* ---- Phase 3: the forms each locked state offers -------------------------- */

echo "\n=== Each locked state offers its own controls and no other state's ===\n";

/**
 * How many of each control one render printed.
 *
 * Counted by the value of the hidden `action` field, which is the thing `admin-post.php`
 * dispatches on, rather than by a class name: a form whose class was renamed still posts,
 * and a form whose action was renamed posts into nothing at all.
 *
 * @param string $html What a render printed.
 * @return array<string, int>
 */
function forms_in( $html ) {
	return array(
		'generate' => substr_count( $html, 'value="' . WPCPM_Agreement_Generate::ACTION_GENERATE . '"' ),
		'upload'   => substr_count( $html, 'value="' . WPCPM_Institution_Agreement::ACTION_UPLOAD . '"' ),
		'withdraw' => substr_count( $html, 'value="' . WPCPM_Institution_Agreement::ACTION_WITHDRAW . '"' ),
		'accept'   => substr_count( $html, 'value="' . WPCPM_Institution_Agreement::ACTION_ACCEPT . '"' ),
		'return'   => substr_count( $html, 'value="' . WPCPM_Institution_Agreement::ACTION_RETURN . '"' ),
		'download' => substr_count( $html, 'action=' . WPCPM_Institution_Agreement::ACTION_DOWNLOAD ),
	);
}

/**
 * Stand one institution up in a named summary state and render its panel for a member.
 *
 * @param string $record Institutions record ID.
 * @param string $state  A `STATE_*` value, or an empty string for nothing recorded at all.
 * @param string $status The Airtable `Agreement Status` the rebuild reads.
 * @param array  $meta   Extra meta rows on the seeded document.
 * @return array `array( html, post id )`.
 */
function state_panel( $record, $state, $status, array $meta = array() ) {
	reset_world();
	seed_index( $record, 'recCOUNTRYAAAAAAA' );
	$GLOBALS['opts'][ WPCPM_Countries::OPTION ] = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1756600000,
		'rows' => array( 'recCOUNTRYAAAAAAA' => array( 'name' => 'Poland', 'manager' => 'recTEAMAAAAAAAA1', 'email' => 'poland@example.org', 'calendly' => '' ) ),
	);

	$post_id = '' === $state ? 0 : seed_post( $record, $state, WPCPM_Institution_Agreement::KIND_TEMPLATE, $meta );
	WPCPM_Institution_Agreement::rebuild( $record, block( $status ) );

	return array( panel( $record ), $post_id );
}

$file_meta = array(
	WPCPM_Institution_Agreement::META_FILE              => array(
		'path'   => '2026/0123456789abcdef0123456789abcd.pdf',
		'size'   => 239616,
		'sha256' => str_repeat( 'a', 64 ),
	),
	WPCPM_Institution_Agreement::META_ORIGINAL_NAME     => 'Umowa podpisana.pdf',
	WPCPM_Institution_Agreement::META_TEMPLATE_VERSION  => '2025-11-04',
	WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT  => 'Universidad Example Sede Central',
	WPCPM_Institution_Agreement::META_FLAGS             => array( '/JavaScript', '/OpenAction' ),
);

list( $p_none )      = state_panel( $rec_a, '', 'Not started' );
list( $p_generated ) = state_panel( $rec_a, WPCPM_Institution_Agreement::STATE_GENERATED, 'Template generated', array( WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04' ) );
list( $p_submitted, $submitted_id ) = state_panel( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, 'Awaiting review', $file_meta );
list( $p_returned )  = state_panel( $rec_a, WPCPM_Institution_Agreement::STATE_RETURNED, 'Returned', array( WPCPM_Institution_Agreement::META_NOTE => 'Page 4 is not signed.', WPCPM_Institution_Agreement::META_DECIDED_BY => 9, WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-30' ) );
list( $p_revoked )   = state_panel( $rec_a, WPCPM_Institution_Agreement::STATE_REVOKED, 'Revoked', array( WPCPM_Institution_Agreement::META_NOTE => 'The partnership ended in July.', WPCPM_Institution_Agreement::META_DECIDED_BY => 9, WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-31' ) );

// The spec's section 7.4 table, read as a matrix. `generated` carries two generate forms
// on purpose: the full one, and the Regenerate control that reproduces the document the
// institution already has. Everything else is one form or none.
ck( 'none: generate and upload, nothing else', forms_in( $p_none ), array( 'generate' => 1, 'upload' => 1, 'withdraw' => 0, 'accept' => 0, 'return' => 0, 'download' => 0 ) );
ck( 'generated: the same two, plus Regenerate', forms_in( $p_generated ), array( 'generate' => 2, 'upload' => 1, 'withdraw' => 0, 'accept' => 0, 'return' => 0, 'download' => 0 ) );
ck( 'submitted: download and withdraw, and nothing to upload over it', forms_in( $p_submitted ), array( 'generate' => 0, 'upload' => 0, 'withdraw' => 1, 'accept' => 0, 'return' => 0, 'download' => 1 ) );
ck( 'returned: the upload form and not the generate form', forms_in( $p_returned ), array( 'generate' => 0, 'upload' => 1, 'withdraw' => 0, 'accept' => 0, 'return' => 0, 'download' => 0 ) );
ck( 'revoked: the same', forms_in( $p_revoked ), array( 'generate' => 0, 'upload' => 1, 'withdraw' => 0, 'accept' => 0, 'return' => 0, 'download' => 0 ) );

ck( 'generated: says when it was generated and from which template', false !== strpos( $p_generated, 'You generated the program&#039;s agreement on 2025-09-01 (template 2025-11-04)' ), true );
ck( 'and offers that document again rather than only a fresh one', false !== strpos( $p_generated, 'Open that document again' ), true );
ck( 'revoked: still names the country contact after the upload form', strpos( $p_revoked, 'value="wpcpm_agreement_upload"' ) < strpos( $p_revoked, 'Your program contact for Poland' ), true );

/* ---- every nonce action string, byte for byte ---------------------------- */

echo "\n=== Every nonce is keyed the way its handler reads it ===\n";

// The strings the handlers check against, taken from the classes that own them rather than
// typed here, and then typed here as well: a constant renamed on both sides at once is a
// rename, a constant renamed on one side is a form that 403s and a test that still passes.
ck( 'the six action names are the ones the contract fixes',
	array(
		WPCPM_Agreement_Generate::ACTION_GENERATE,
		WPCPM_Institution_Agreement::ACTION_UPLOAD,
		WPCPM_Institution_Agreement::ACTION_DOWNLOAD,
		WPCPM_Institution_Agreement::ACTION_ACCEPT,
		WPCPM_Institution_Agreement::ACTION_RETURN,
		WPCPM_Institution_Agreement::ACTION_WITHDRAW,
	),
	array( 'wpcpm_agreement_generate', 'wpcpm_agreement_upload', 'wpcpm_agreement_download', 'wpcpm_agreement_accept', 'wpcpm_agreement_return', 'wpcpm_agreement_withdraw' ) );

$generate_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-agreement-generate.php' );
preg_match( "/const ACTION_GENERATE\s*=\s*'([^']+)'/", $generate_src, $g );
ck( 'and the generate action stubbed here is the real class\'s own', WPCPM_Agreement_Generate::ACTION_GENERATE, $g[1] ?? '' );

ck( 'generate is keyed to the institution', false !== strpos( $p_none, 'value="nonce-wpcpm_agreement_generate_' . $rec_a . '"' ), true );
ck( 'upload is keyed to the institution', false !== strpos( $p_none, 'value="nonce-wpcpm_agreement_upload_' . $rec_a . '"' ), true );
ck( 'regenerate is keyed to the institution too, not to the document', false !== strpos( $p_generated, 'value="nonce-wpcpm_agreement_generate_' . $rec_a . '"' ), true );
ck( 'withdraw is keyed to the document', false !== strpos( $p_submitted, 'value="nonce-wpcpm_agreement_withdraw_' . $submitted_id . '"' ), true );
ck( 'and no form was keyed to the wrong half', array(
	false !== strpos( $p_none, 'nonce-wpcpm_agreement_upload_' . $submitted_id ),
	false !== strpos( $p_submitted, 'nonce-wpcpm_agreement_withdraw_' . $rec_a ),
), array( false, false ) );

ck( 'every form posts to admin-post.php', substr_count( $p_none, 'action="https://example.test/wp-admin/admin-post.php"' ), 2 );
ck( 'and every form carries the attribute the submit guard reads', substr_count( $p_none, 'data-wpcpm-once' ), 2 );

/* ---- a manager's post lands on the institution they were looking at ------- */

echo "\n=== A manager's generate carries the switcher; a member's carries nothing ===\n";

// `generate()` resolves the institution from the request and from nothing else, and a POST
// to admin-post.php carries the form's own fields and none of the query string of the page
// the button was on. So a manager pressing Generate on an institution's dashboard resolved
// to whichever institution is their fallback, met a nonce keyed to a different record, and
// got "Are you sure you want to do this?" every time: a program manager could not generate
// at all. The switcher argument on the form's own action is what carries the answer back.

/**
 * One form out of a render, by the action it posts to.
 *
 * The panel prints several forms into one string, and every question below is about one of
 * them: which fields it carries, and where it posts. Sliced on the hidden `action` field
 * rather than on a class, for the reason `forms_in()` gives - the action is the thing
 * `admin-post.php` dispatches on.
 *
 * @param string $html   What a render printed.
 * @param string $action The `admin_post_` action name.
 * @return string
 */
function form_with( $html, $action ) {
	$at = strpos( $html, 'value="' . $action . '"' );

	if ( false === $at ) {
		return '';
	}

	$open = (int) strrpos( substr( $html, 0, $at ), '<form ' );
	$end  = (int) strpos( $html, '</form>', $at );

	return substr( $html, $open, $end - $open + 7 );
}

$roster_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php' );
preg_match( "/const ARG_VIEW\s*=\s*'([^']+)'/", $roster_src, $v );
ck( 'the switcher argument stubbed here is the roster\'s own', WPCPM_Institution_Roster::ARG_VIEW, $v[1] ?? '' );

// The two facts on the other side that an argument on a form's action depends on, read off
// the roster rather than assumed here: `resolve_institution()` honours the switcher for a
// manager, and `requested_view()` looks in the query string as well as in the posted
// fields. Take either away and this form posts an argument nobody reads.
ck( 'the resolver honours the switcher, and looks where a form action puts it', array(
	false !== strpos( method_body( $roster_src, 'resolve_institution' ), 'self::requested_view()' ),
	false !== strpos( method_body( $roster_src, 'requested_view' ), 'WPCPM_Request::text( self::ARG_VIEW )' ),
), array( true, true ) );

list( $member_none ) = state_panel( $rec_a, '', 'Not started' );
$manager_none        = panel( $rec_a, true );

list( $member_generated ) = state_panel( $rec_a, WPCPM_Institution_Agreement::STATE_GENERATED, 'Template generated', array( WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04' ) );
$manager_generated        = panel( $rec_a, true );

$plain    = 'action="https://example.test/wp-admin/admin-post.php"';
$switched = 'action="https://example.test/wp-admin/admin-post.php?' . WPCPM_Institution_Roster::ARG_VIEW . '=' . $rec_a . '"';

ck( 'a member posts to admin-post.php and nothing else', array( substr_count( $member_none, $plain ), false !== strpos( $member_none, WPCPM_Institution_Roster::ARG_VIEW ) ), array( 2, false ) );
ck( 'a manager\'s generate form names the institution being looked at', false !== strpos( $manager_none, $switched ), true );
ck( 'exactly once: it is the only form on that state whose handler resolves from the request', substr_count( $manager_none, $switched ), 1 );
ck( 'and Regenerate carries it too, because it is keyed to the same institution', substr_count( $manager_generated, $switched ), 2 );
ck( 'the upload form does not, because its own handler honours the record it posts', false !== strpos( form_with( $manager_none, WPCPM_Institution_Agreement::ACTION_UPLOAD ), WPCPM_Institution_Roster::ARG_VIEW ), false );

// The field the generate form used to carry: it named the right institution while the
// handler was busy resolving another one, which is the worst kind of hidden input - one
// that reads as the answer and is never asked.
ck( 'the generate form carries no record field', false !== strpos( form_with( $member_none, WPCPM_Agreement_Generate::ACTION_GENERATE ), 'name="wpcpm_agreement_record"' ), false );
ck( 'nor does Regenerate', false !== strpos( form_with( $member_generated, WPCPM_Agreement_Generate::ACTION_GENERATE ), 'name="wpcpm_agreement_record"' ), false );
// The route reads that field only when it is there, and a request without it falls through
// to `resolve_institution()`, which is what reads the switcher off the action URL. That
// fallback is the whole reason this form can drop the field, so it is pinned rather than
// assumed.
ck( 'and the generate route falls through to the resolver, which is what reads the action URL', false !== strpos( $generate_src, 'WPCPM_Institution_Roster::resolve_institution(' ), true );
ck( 'the upload form still carries it, because its handler honours it for a manager', false !== strpos( form_with( $member_none, WPCPM_Institution_Agreement::ACTION_UPLOAD ), 'name="wpcpm_agreement_record" value="' . $rec_a . '"' ), true );
ck( 'and its handler really reads it', false !== strpos( $agr_src, "WPCPM_Request::posted_text( 'wpcpm_agreement_record' )" ), true );

/* ---- the download is a nonce URL and never a file URL -------------------- */

echo "\n=== The download link is a nonced route, never the file ===\n";

$expected_download = 'https://example.test/wp-admin/admin-post.php?action=wpcpm_agreement_download&post=' . $submitted_id . '&_wpnonce=nonce-wpcpm_agreement_download_' . $submitted_id;

ck( 'the link is admin-post.php with the action, the post and the nonce', false !== strpos( $p_submitted, $expected_download ), true );
ck( 'and the nonce is keyed to the post ID, which is what the handler checks', false !== strpos( $p_submitted, '_wpnonce=nonce-wpcpm_agreement_download_' . $submitted_id ), true );

// Design spec 7.4: the plugin never prints a private file's URL anywhere. The stored path,
// the private directory's name and the extension are each looked for on their own, because
// the way this leaks is one careless `esc_url( $file['path'] )` in a later phase.
ck( 'the stored path is nowhere in the page', false !== strpos( $p_submitted, '0123456789abcdef0123456789abcd' ), false );
ck( 'neither is the private directory', false !== strpos( $p_submitted, 'wpcpm-private' ), false );
ck( 'nor the original filename the institution sent', false !== strpos( $p_submitted, 'Umowa podpisana' ), false );
ck( 'nor a .pdf address of any kind', false !== strpos( $p_submitted, '.pdf' ), false );

/* ---- one free-text field, and it is the return note ---------------------- */

echo "\n=== No form carries a free-text field except the return note ===\n";

// Design spec 7.4, on the upload form: "No free-text field: a textarea on an upload form is
// a second place for personal data to land." The panel is where an institution's own people
// type, so the rule is checked over all five of its states at once rather than form by form.
$all_states = $p_none . $p_generated . $p_submitted . $p_returned . $p_revoked;

ck( 'no state of the panel prints a textarea', substr_count( $all_states, '<textarea' ), 0 );

ob_start();
WPCPM_Institution_Panel::render_upload_form( $rec_a, '' );
$upload_only = (string) ob_get_clean();

ck( 'the upload form has no textarea', substr_count( $upload_only, '<textarea' ), 0 );
ck( 'and no free-text input either: a file, two radios, a checkbox and its companion', substr_count( $upload_only, 'type="text"' ), 0 );
ck( 'the one text box on the panel is the name the agreement will print', substr_count( $p_none, 'type="text"' ), 1 );
ck( 'and it is the generate form\'s', false !== strpos( $p_none, 'name="wpcpm_agreement_name"' ), true );

/* ---- the upload form, field by field ------------------------------------- */

echo "\n=== The upload form is the shape the handler reads ===\n";

ck( 'it is multipart, or the bytes never arrive', false !== strpos( $upload_only, 'enctype="multipart/form-data"' ), true );
ck( 'the file field is the name the handler reads, and asks for a PDF', false !== strpos( $upload_only, 'name="wpcpm_agreement_file" accept="application/pdf,.pdf"' ), true );
ck( 'the kind is two radios on the server-held allowlist', array(
	false !== strpos( $upload_only, 'name="wpcpm_agreement_kind" value="template"' ),
	false !== strpos( $upload_only, 'name="wpcpm_agreement_kind" value="own"' ),
), array( true, true ) );
ck( 'the institution travels with it, for a manager uploading on behalf', false !== strpos( $upload_only, 'name="wpcpm_agreement_record" value="' . $rec_a . '"' ), true );

// The companion has to come first: a later field of the same name wins, so an unticked box
// leaves `0` behind and a ticked one overwrites it. In the other order the declaration would
// always post `0` and no upload could ever succeed.
$companion = strpos( $upload_only, 'name="wpcpm_agreement_signed" value="0"' );
$checkbox  = strpos( $upload_only, 'name="wpcpm_agreement_signed" value="1"' );

ck( 'both halves of the declaration are printed', array( is_int( $companion ), is_int( $checkbox ) ), array( true, true ) );
ck( 'and the hidden 0 comes before the checkbox', $companion < $checkbox, true );
ck( 'the checkbox is a checkbox and the companion is hidden', array(
	false !== strpos( $upload_only, 'type="checkbox" id="wpcpm-upload-' . $rec_a . '-signed" name="wpcpm_agreement_signed" value="1"' ),
	false !== strpos( $upload_only, '<input type="hidden" name="wpcpm_agreement_signed" value="0" />' ),
), array( true, true ) );

/* ---- the generate form, and the language select that is not always there -- */

echo "\n=== The generate form pre-fills the name and offers a language only when there is one to pick ===\n";

ck( 'the name is pre-filled from the pipeline index', false !== strpos( $p_none, 'name="wpcpm_agreement_name" value="Universidad Example"' ), true );
ck( 'with one template the reader is not asked which', substr_count( $p_none, 'name="wpcpm_agreement_language"' ), 0 );
ck( 'and no select is printed at all', substr_count( $p_none, '<select' ), 0 );
ck( 'the Regenerate control carries the language the document was made with, whatever the count', substr_count( $p_generated, 'name="wpcpm_agreement_language"' ), 1 );

$GLOBALS['languages'] = array( 'en', 'es' );
list( $p_two ) = state_panel( $rec_a, '', 'Not started' );
$GLOBALS['languages'] = array( 'en' );

ck( 'with two templates the reader is asked which', substr_count( $p_two, '<select' ), 1 );
ck( 'and both are offered', array( false !== strpos( $p_two, '<option value="en">' ), false !== strpos( $p_two, '<option value="es">' ) ), array( true, true ) );

/* ---- the manager's upload on behalf -------------------------------------- */

echo "\n=== A manager uploads on the institution's behalf with the same form ===\n";

reset_world();
seed_index( $rec_a, '' );
$GLOBALS['caps'] = true;
ob_start();
WPCPM_Institution_Panel::render_manager_upload( $rec_a );
$on_behalf = (string) ob_get_clean();

ck( 'a manager gets the form', false !== strpos( $on_behalf, 'value="wpcpm_agreement_upload"' ), true );
ck( 'keyed to the institution being looked at', false !== strpos( $on_behalf, 'value="nonce-wpcpm_agreement_upload_' . $rec_a . '"' ), true );
ck( 'and told what it does to the people at that institution', false !== strpos( $on_behalf, 'everybody at the institution is emailed that it arrived' ), true );
ck( 'the record travels in the field its handler honours', false !== strpos( $on_behalf, 'name="wpcpm_agreement_record" value="' . $rec_a . '"' ), true );

$GLOBALS['caps']      = false;
$GLOBALS['member_of'] = array( $rec_a );
ob_start();
WPCPM_Institution_Panel::render_manager_upload( $rec_a );
ck( 'a member of the institution gets nothing from the manager\'s form', (string) ob_get_clean(), '' );

// This method is called from an institution's row on a screen it knows nothing about, so it
// decides for itself and it decides before it prints: a heading reading "Upload a signed
// agreement on the institution's behalf" with nothing under it is what a caller gets when
// the capability is asked here and the fence is asked one call further down.
$GLOBALS['caps']      = true;
$GLOBALS['member_of'] = array();
$GLOBALS['allow']     = false;
ob_start();
WPCPM_Institution_Panel::render_manager_upload( $rec_a );
$refused_behalf   = (string) ob_get_clean();
$GLOBALS['allow'] = true;

ck( 'a manager the fence refuses gets nothing, not a heading over no form', $refused_behalf, '' );
ck( 'and a record that is not a record draws nothing either', ( function () {
	ob_start();
	WPCPM_Institution_Panel::render_manager_upload( 'not-a-record' );
	return (string) ob_get_clean();
} )(), '' );
ck( 'the heading and the form arrive together', array(
	substr_count( $on_behalf, 'Upload a signed agreement on the institution' ),
	substr_count( $on_behalf, 'value="wpcpm_agreement_upload"' ),
), array( 1, 1 ) );

// The call site is a row in a table of every institution, so the one thing this method takes
// from its caller is the record, and everything drawn has to be that record's. A manager
// belongs to no institution, so there is nothing of their own for a stale value to fall back
// to; what a mixed-up row would produce is the *other* institution's paperwork under this
// one's name, which is why the second record is seeded and then looked for by name.
reset_world();
seed_index( $rec_a, '' );
seed_index( $rec_b, '' );
$GLOBALS['caps'] = true;
ob_start();
WPCPM_Institution_Panel::render_manager_upload( $rec_b );
$row_b = (string) ob_get_clean();

ck( 'a manager on B\'s row uploads for B', array(
	false !== strpos( $row_b, 'name="wpcpm_agreement_record" value="' . $rec_b . '"' ),
	false !== strpos( $row_b, 'value="nonce-wpcpm_agreement_upload_' . $rec_b . '"' ),
), array( true, true ) );
ck( 'and A is named nowhere on it', false !== strpos( $row_b, $rec_a ), false );
ck( 'the switcher argument is not on this form\'s action, because its handler reads the field',
    false !== strpos( $row_b, WPCPM_Institution_Roster::ARG_VIEW ), false );

// And the field really is what the handler on the other side reads for a manager: the posted
// record first, then `resolve_institution()`. Two files, one mechanism per form, asserted
// across the pair rather than assumed on either side of it - which is the check Phase 3 did
// not have, and the reason the generate route shipped with two.
$behalf_resolver = method_body( $agr_src, 'record_for_request' );

ck( 'the upload handler honours the posted record for a manager and resolves otherwise', array(
	false !== strpos( $behalf_resolver, "WPCPM_Request::posted_text( 'wpcpm_agreement_record' )" ),
	false !== strpos( $behalf_resolver, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ),
	false !== strpos( $behalf_resolver, 'WPCPM_Institution_Roster::resolve_institution(' ),
), array( true, true, true ) );

// One document in review at a time is `handle_upload()`'s rule, so a row for an institution
// that already has one gets the sentence rather than a form that would be refused. The way
// out of the state is drawn instead: the copy, and the control that takes it back.
reset_world();
seed_index( $rec_a, '' );
$waiting = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Awaiting review' ) );
$GLOBALS['caps'] = true;
ob_start();
WPCPM_Institution_Panel::render_manager_upload( $rec_a );
$row_busy = (string) ob_get_clean();

ck( 'a row whose institution has a copy in review is offered no second upload', forms_in( $row_busy )['upload'], 0 );
ck( 'and is told why, in the words the handler would have refused with', false !== strpos( $row_busy, 'only one can be in review at a time' ), true );
ck( 'with the way out of it: the copy, and the control that withdraws it', array(
	forms_in( $row_busy )['download'],
	forms_in( $row_busy )['withdraw'],
	false !== strpos( $row_busy, 'value="nonce-wpcpm_agreement_withdraw_' . $waiting . '"' ),
), array( 1, 1, true ) );
ck( 'and the file is still named nowhere', false !== strpos( $row_busy, '0123456789abcdef' ), false );

// An accepted agreement is not that case: a re-signed copy reaching the program by email is
// T10, and the standing one stays in force until somebody accepts the new one. A row that
// refused this would send a manager holding a renewal to ask the institution to upload it.
reset_world();
seed_index( $rec_a, '' );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_ACCEPTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );
$GLOBALS['caps'] = true;
ob_start();
WPCPM_Institution_Panel::render_manager_upload( $rec_a );
$row_accepted = (string) ob_get_clean();

ck( 'a settled institution\'s row still takes a replacement', forms_in( $row_accepted )['upload'], 1 );

/* ---- withdrawing is two people's control, and they are asked differently -- */

echo "\n=== Withdraw asks whoever is pressing it, in their own words ===\n";

// `handle_withdraw()` takes a member or a manager on their behalf, so the control belongs on
// both surfaces. What must not travel between them is the wording: the same dialog that reads
// right to somebody taking back their own upload reads, on a manager's row, as though the
// institution were pressing it - and what the manager is actually about to do is delete
// another organisation's document, with nobody emailed about it. A control drawn on forty rows
// has to name which one it belongs to as well.
reset_world();
seed_index( $rec_a, '' );
$mine = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );

$GLOBALS['caps']      = false;
$GLOBALS['member_of'] = array( $rec_a );
ob_start();
WPCPM_Institution_Panel::render_withdraw_form( $mine );
$member_withdraw = (string) ob_get_clean();

$GLOBALS['caps']      = true;
$GLOBALS['member_of'] = array();
ob_start();
WPCPM_Institution_Panel::render_withdraw_form( $mine );
$manager_withdraw = (string) ob_get_clean();

ck( 'a member is asked about the document they uploaded', array(
	false !== strpos( $member_withdraw, 'Withdraw the signed agreement waiting for review?' ),
	false !== strpos( $member_withdraw, 'You can upload another whenever you are ready.' ),
), array( true, true ) );
ck( 'and is not handed a manager\'s sentence about somebody else\'s institution', array(
	false !== strpos( $member_withdraw, 'Universidad Example' ),
	false !== strpos( $member_withdraw, 'Nobody at the institution is emailed' ),
), array( false, false ) );

ck( 'a manager is asked about the institution whose row it is',
    false !== strpos( $manager_withdraw, 'Withdraw the signed agreement from Universidad Example?' ), true );
ck( 'told the thing only they need to know: nobody is emailed, so telling them is now their job',
    false !== strpos( $manager_withdraw, 'Nobody at the institution is emailed, so tell them yourself' ), true );
ck( 'and what the institution is left with', false !== strpos( $manager_withdraw, 'nothing for anyone to review' ), true );
ck( 'the button says whose document it is', false !== strpos( $manager_withdraw, 'Withdraw it on the institution&#039;s behalf' ), true );
ck( 'and the member\'s dialogue is not what they get',
    false !== strpos( $manager_withdraw, 'You can upload another whenever you are ready.' ), false );

// Same handler, same nonce, same field: only the words differ. A branch that changed the form
// as well as the sentence would be a second route into a handler that knows about one.
ck( 'both post the same action, keyed to the same document', array(
	substr_count( $member_withdraw, 'value="nonce-wpcpm_agreement_withdraw_' . $mine . '"' ),
	substr_count( $manager_withdraw, 'value="nonce-wpcpm_agreement_withdraw_' . $mine . '"' ),
	substr_count( $member_withdraw, 'name="wpcpm_agreement_post" value="' . $mine . '"' ),
	substr_count( $manager_withdraw, 'name="wpcpm_agreement_post" value="' . $mine . '"' ),
), array( 1, 1, 1, 1 ) );

// The manager's row is where the wording was wrong, so it is asserted where it is drawn and
// not only through the method that draws it.
ck( 'the manager\'s institution row asks the manager\'s question',
    false !== strpos( $row_busy, 'Withdraw the signed agreement from Universidad Example?' ), true );

// An institution the last sync has not read yet has no name to print. The record ID is a poor
// name and a true one; a dialog naming the wrong institution would be worse than either.
reset_world();
$unnamed = seed_post( $rec_b, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
$GLOBALS['caps'] = true;
ob_start();
WPCPM_Institution_Panel::render_withdraw_form( $unnamed );
$unnamed_withdraw = (string) ob_get_clean();

ck( 'an institution the index has not read yet is named by its record',
    false !== strpos( $unnamed_withdraw, 'Withdraw the signed agreement from ' . $rec_b . '?' ), true );

/* ---- the reviewer's block ------------------------------------------------ */

echo "\n=== The review block: the checklist read, the flags as a courtesy, two ways out ===\n";

/**
 * Render the review block for one document and hand back what it printed.
 *
 * @param int         $post_id    Agreement post ID.
 * @param bool        $can_manage Whether the viewer holds CAP_MANAGE.
 * @param string|null $member_of  The institution the viewer belongs to; null for none.
 * @return string
 */
function review( $post_id, $can_manage = true, $member_of = null ) {
	$GLOBALS['caps']      = $can_manage;
	$GLOBALS['member_of'] = null === $member_of ? array() : array( $member_of );
	ob_start();
	WPCPM_Institution_Panel::render_review( $post_id );
	return (string) ob_get_clean();
}

reset_world();
seed_index( $rec_a, '' );
$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska', 'Bo Nowak', 'Cy Wisniewski' );
$review_id = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
$template_review = review( $review_id );

ck( 'the block names the institution it is about', false !== strpos( $template_review, 'Review the signed agreement from Universidad Example' ), true );
ck( 'and who uploaded it, when, and how big it is', false !== strpos( $template_review, 'Uploaded by Wera Rektor on 2025-09-01, 234 KB.' ), true );
ck( 'the checklist is read, not ticked: three sentences and no boxes', array( substr_count( $template_review, '<li>' ), substr_count( $template_review, 'type="checkbox"' ) ), array( 3, 0 ) );
ck( 'a template-kind document names the version the footer should carry', false !== strpos( $template_review, 'The footer on the signed copy names template version 2025-11-04.' ), true );
ck( 'and the two names, side by side', false !== strpos( $template_review, 'The name on the document is Universidad Example Sede Central. Airtable&#039;s Name for this institution is Universidad Example.' ), true );
ck( 'and asks whether the signature block is filled', false !== strpos( $template_review, 'signature block is filled in' ), true );
ck( 'the flags line names what the scan noticed', false !== strpos( $template_review, 'The scan noticed these in the file: /JavaScript, /OpenAction.' ), true );
ck( 'and says in the same breath that it is not evidence', false !== strpos( $template_review, 'The scan is a courtesy and not evidence' ), true );
ck( 'and what does protect the reviewer', false !== strpos( $template_review, 'never opens the file in your browser' ), true );
ck( 'the copy itself is one nonced link away', false !== strpos( $template_review, 'action=wpcpm_agreement_download&post=' . $review_id . '&_wpnonce=nonce-wpcpm_agreement_download_' . $review_id ), true );
ck( 'and the file is still nowhere in the page', array( false !== strpos( $template_review, '0123456789abcdef' ), false !== strpos( $template_review, 'Umowa podpisana' ) ), array( false, false ) );

ck( 'the two ways out are Accept and Return, one each', array_intersect_key( forms_in( $template_review ), array( 'accept' => 0, 'return' => 0, 'upload' => 0, 'withdraw' => 0 ) ), array( 'upload' => 0, 'withdraw' => 0, 'accept' => 1, 'return' => 1 ) );
ck( 'Accept is keyed to the document', false !== strpos( $template_review, 'value="nonce-wpcpm_agreement_accept_' . $review_id . '"' ), true );
ck( 'Return is too', false !== strpos( $template_review, 'value="nonce-wpcpm_agreement_return_' . $review_id . '"' ), true );
ck( 'and both carry the post ID under the name the handlers read', substr_count( $template_review, 'name="wpcpm_agreement_post" value="' . $review_id . '"' ), 2 );

// The confirm names everything that leaves the building: the institution, the Airtable stage
// and the number of inboxes. The count comes from members_of(), so a fourth member added
// this morning is a four in the dialog this afternoon.
ck( 'the Accept confirm names the institution', false !== strpos( $template_review, 'Accept the signed agreement from Universidad Example?' ), true );
ck( 'names Confirmed', false !== strpos( $template_review, 'sets Current Stage to Confirmed in Airtable' ), true );
ck( 'names how many people it emails', false !== strpos( $template_review, 'emails the 3 people at the institution' ), true );
ck( 'and says it can be undone', false !== strpos( $template_review, 'You can revoke it from here later' ), true );

$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska' );
ck( 'one member is one person, not one people', false !== strpos( review( $review_id ), 'emails the 1 person at the institution' ), true );
$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska', 'Bo Nowak', 'Cy Wisniewski' );

ck( 'the return note is the one free-text field in the block', substr_count( $template_review, '<textarea' ), 1 );
ck( 'named the way the handler reads it, and bounded the way it refuses', false !== strpos( $template_review, 'name="wpcpm_agreement_note" rows="4" minlength="20" maxlength="2000" required' ), true );
ck( 'and said to be mailed verbatim', false !== strpos( $template_review, 'exactly as you write it' ), true );

/* ---- an own-kind document is a different read ----------------------------- */

reset_world();
seed_index( $rec_a, '' );
$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska' );
$own_id = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_OWN, array( WPCPM_Institution_Agreement::META_FILE => array( 'path' => '2026/ffffffffffffffffffffffffffffffff.pdf', 'size' => 51200, 'sha256' => str_repeat( 'b', 64 ) ) ) );
$own_review = review( $own_id );

ck( 'an own-kind document says to read the whole thing', false !== strpos( $own_review, 'Read the whole document' ), true );
ck( 'names the two parties it has to name', false !== strpos( $own_review, 'It names the WordPress Foundation and the institution' ), true );
ck( 'and warns about what it must not add', false !== strpos( $own_review, 'does not commit the program to anything the program&#039;s own template does not' ), true );
ck( 'it does not compare a template version, because there is none to compare', false !== strpos( $own_review, 'The footer on the signed copy names template version' ), false );
ck( 'the flags line is there whichever kind it is', false !== strpos( $own_review, 'The scan is a courtesy and not evidence' ), true );
ck( 'and says so plainly when the scan noticed nothing', false !== strpos( $own_review, 'The scan noticed none of the features it looks for' ), true );

/* ---- who may read a review block ----------------------------------------- */

echo "\n=== The review block belongs to a program manager and to nobody else ===\n";

ck( 'a member of another institution gets nothing', review( $own_id, false, $rec_b ), '' );
ck( 'a member of this very institution gets nothing either: reviewing is not theirs', review( $own_id, false, $rec_a ), '' );
ck( 'a manager the fence refuses gets nothing', ( function () use ( $own_id ) {
	$GLOBALS['allow'] = false;
	$out              = review( $own_id );
	$GLOBALS['allow'] = true;
	return $out;
} )(), '' );
ck( 'a program manager gets it', '' !== trim( review( $own_id ) ), true );

// Accept and Return are transitions out of `submitted` and out of nothing else, so a block
// over any other state would be two buttons that refuse.
update_post_meta( $own_id, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_WITHDRAWN );
ck( 'a withdrawn document has nothing left to review', review( $own_id ), '' );
update_post_meta( $own_id, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_SUBMITTED );

ck( 'a post ID that is not an agreement at all draws nothing', review( 999999 ), '' );

// The block reads one source, so it has to survive that source answering with nothing:
// `review_facts()` returns an empty array for a post it does not recognise, and a heading
// reading "Review the signed agreement from" over two live buttons is worse than silence.
$orphan = wp_insert_post( array( 'post_type' => WPCPM_Institution_Agreement::POST_TYPE, 'post_status' => WPCPM_Institution_Agreement::POST_STATUS ) );
update_post_meta( $orphan, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_SUBMITTED );
update_post_meta( $orphan, WPCPM_Institution_Agreement::META_INSTITUTION, $rec_b );
ck( 'and a document whose institution the index has not read yet is named by its record', false !== strpos( review( $orphan ), 'Review the signed agreement from ' . $rec_b ), true );

/* ---- the card at the foot of a settled dashboard -------------------------- */

echo "\n=== The settled card carries the copy and the way to replace it ===\n";

reset_world();
seed_index( $rec_a, '' );
$accepted_id = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_ACCEPTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );

ck( 'the institution is settled', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
$accepted_card = card( $rec_a );

ck( 'the card offers the accepted copy', false !== strpos( $accepted_card, 'action=wpcpm_agreement_download&post=' . $accepted_id ), true );
ck( 'and the upload form that replaces it', forms_in( $accepted_card )['upload'], 1 );
ck( 'saying plainly that the current one stays in force', false !== strpos( $accepted_card, 'stays in force until a program manager accepts the new one' ), true );
ck( 'and the file is not named anywhere on it', false !== strpos( $accepted_card, '0123456789abcdef' ), false );

// A replacement already waiting: the upload would refuse ("one in review at a time"), so the
// card says where it got to and offers the way back out instead of a form that cannot work.
$replacement_id = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );
$busy_card = card( $rec_a );

ck( 'with a replacement in review the card says so', false !== strpos( $busy_card, 'A newer signed agreement is waiting for review' ), true );
ck( 'offers no second upload', forms_in( $busy_card )['upload'], 0 );
ck( 'and offers the way to take the replacement back', array( forms_in( $busy_card )['withdraw'], false !== strpos( $busy_card, 'value="nonce-wpcpm_agreement_withdraw_' . $replacement_id . '"' ) ), array( 1, true ) );

// A legacy row is settled and has no file at all. Offering a download would 404; the route
// line and the manager's Drive link are what that institution has.
reset_world();
seed_index( $rec_b, '' );
WPCPM_Institution_Agreement::rebuild( $rec_b, block( 'On file', $drive ) );
$legacy_card = card( $rec_b );

ck( 'an on-file institution is settled', WPCPM_Institution_Agreement::is_settled( $rec_b ), true );
ck( 'and is offered no download, because this site holds no file', false !== strpos( $legacy_card, 'action=wpcpm_agreement_download' ), false );
ck( 'but may still replace it by signing the current template', forms_in( $legacy_card )['upload'], 1 );

/* ---- a document of another institution's is nobody else's ------------------ */

echo "\n=== A control keyed to a document asks about that document's institution ===\n";

reset_world();
seed_index( $rec_a, '' );
$theirs = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );

$GLOBALS['caps']      = false;
$GLOBALS['member_of'] = array( $rec_b );
$GLOBALS['decisions'] = array();
ob_start();
WPCPM_Institution_Panel::render_download_link( $theirs, 'Download' );
WPCPM_Institution_Panel::render_withdraw_form( $theirs );
$stranger_controls = (string) ob_get_clean();

ck( 'a member of another institution gets neither control', $stranger_controls, '' );
ck( 'and the fence was asked about the document, not about the screen', $GLOBALS['decisions'], array( array( 'agreement', (string) $theirs ), array( 'agreement', (string) $theirs ) ) );

$GLOBALS['member_of'] = array( $rec_a );
ob_start();
WPCPM_Institution_Panel::render_download_link( $theirs, 'Download' );
WPCPM_Institution_Panel::render_withdraw_form( $theirs );
$own_controls = (string) ob_get_clean();

ck( 'a member of the institution that uploaded it gets both', array(
	false !== strpos( $own_controls, 'action=wpcpm_agreement_download&post=' . $theirs ),
	false !== strpos( $own_controls, 'value="wpcpm_agreement_withdraw"' ),
), array( true, true ) );

/* ---- every class either half prints is dressed ---------------------------- */

echo "\n=== Every class these two halves print has a rule in the right stylesheet ===\n";

// Markup with no stylesheet is the failure nothing above would catch: every assertion in
// this file passes on a page that renders as a stack of unstyled paragraphs, and the review
// block shipped exactly that way - eleven classes and not one rule anywhere. The two halves
// are asked of different files because they are drawn on different surfaces: the panel and
// the card on the institution dashboard, which loads institution.css on top of the mentor
// stylesheet, and the review block in wp-admin, which loads admin.css and neither of the
// other two.

/**
 * Every class this file's markup carries, read from the source rather than from a render.
 *
 * A render only shows the states this file happened to stand up; the source shows all of
 * them. The form classes come out of the `form_start()` calls, because that is where the
 * panel names them, and the rest out of the `class="..."` attributes. A name built from a
 * placeholder is skipped: `--%1$s` is a modifier assembled at runtime, and the base class
 * printed beside it is the one a stylesheet can name.
 *
 * @param string $src The panel source.
 * @return string[]
 */
function classes_in( $src ) {
	$names = array();

	preg_match_all( '/class="([^"]*)"/', $src, $attrs );
	preg_match_all( "/form_start\(\s*'([^']+)'/", $src, $forms );

	foreach ( array_merge( $attrs[1], $forms[1] ) as $list ) {
		foreach ( preg_split( '/\s+/', trim( $list ) ) as $name ) {
			if ( '' !== $name && 0 === strpos( $name, 'wpcpm-' ) && false === strpos( $name, '%' ) ) {
				$names[ $name ] = true;
			}
		}
	}

	return array_keys( $names );
}

/**
 * Whether a stylesheet has a rule for one class.
 *
 * A modifier is dressed by its base: `class="a a--b"` takes its box from `.a`, and a
 * modifier exists so that a theme has somewhere to hang a difference, not so that every one
 * of them has to carry a declaration of its own.
 *
 * @param string $css  The stylesheet.
 * @param string $name The class name.
 * @return bool
 */
function dressed( $css, $name ) {
	$at   = strpos( $name, '--' );
	$base = false === $at ? $name : substr( $name, 0, $at );

	return (bool) preg_match( '/\.' . preg_quote( $base, '/' ) . '(?![\w-])/', $css );
}

$front_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' )
	. (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/dashboard.css' );
$admin_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' );

$undressed = array();
$review    = 0;

foreach ( classes_in( $panel_src ) as $name ) {
	$in_admin = 0 === strpos( $name, 'wpcpm-review' );
	$review  += $in_admin ? 1 : 0;

	if ( ! dressed( $in_admin ? $admin_css : $front_css, $name ) ) {
		$undressed[] = $name;
	}
}

sort( $undressed );

ck( 'the review block is eleven classes, and the admin stylesheet dresses all of them', array( $review, in_array( 'wpcpm-review', $undressed, true ) ), array( 11, false ) );

// `.wpcpm-link-button` is the mentor dashboard's own button class - the group-sessions cards
// print it too - so its rule belongs in dashboard.css and not in either file this page owns.
// Named here rather than skipped quietly, so that the day dashboard.css grows it, this line
// fails and the exception goes with it.
ck( 'every class either half prints is dressed, but for the one another stylesheet owes', $undressed, array( 'wpcpm-link-button' ) );

// The review block draws two pieces of the panel's own markup - the download link, and the
// note's textarea, which carries the panel's input class - on a screen that loads neither of
// the front-end stylesheets. Both are dressed by context in admin.css rather than by a
// second copy of institution.css's selectors: one class dressed in two files is two files
// that have to be kept in step.
ck( 'the panel markup inside the review block is dressed on the screen it is drawn on', array(
	false !== strpos( $admin_css, '.wpcpm-review .wpcpm-agreement-panel__download' ),
	false !== strpos( $admin_css, '.wpcpm-review__note textarea' ),
), array( true, true ) );

// Declarations only: both files talk about `!important` in their comments, and saying why
// a rule does not use one is the opposite of using one.
$declarations = function ( $css ) {
	return (string) preg_replace( '#/\*.*?\*/#s', '', (string) $css );
};

ck( 'and neither stylesheet reaches for !important', array( substr_count( $declarations( $front_css ), '!important' ), substr_count( $declarations( $admin_css ), '!important' ) ), array( 0, 0 ) );
ck( 'nor writes an em dash or an en dash, the way the two PHP files do not', array(
	preg_match( '/[\x{2013}\x{2014}]/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' ) ),
	preg_match( '/[\x{2013}\x{2014}]/u', $admin_css ),
), array( 0, 0 ) );

/* ---- the submit guard is an attribute, and the guard is a script ---------- */

echo "\n=== The once attribute is printed, and the docblock says what reads it ===\n";

// `data-wpcpm-once` is inert on both of these screens: what reads it is assets/js/forms.js,
// registered as `wpcpm-forms`, and neither the institution dashboard nor the Institutions
// screen enqueues it. That is a fact about the site rather than about this file, and the
// only thing this file can do about it is not pretend otherwise - a reader who believes the
// guard is running does not go looking for the enqueue that is missing.
$form_doc = substr( $panel_src, 0, (int) strpos( $panel_src, 'private static function form_start(' ) );
$form_doc = substr( $form_doc, (int) strrpos( $form_doc, '/**' ) );

ck( 'the docblock names the script the attribute needs', false !== strpos( $form_doc, 'wpcpm-forms' ), true );
ck( 'and says the screens do not enqueue it', false !== strpos( $form_doc, 'enqueue' ), true );
ck( 'and the attribute is still the one that script reads', false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/forms.js' ), 'form[data-wpcpm-once]' ), true );

// The tripwire on the sentence above: when a screen does enqueue the guard, this fails, and
// the docblock that says nothing enqueues it is one line away from the failure.
$enqueues = 0;

foreach ( array( 'includes/class-wpcpm-admin.php', 'includes/modules/class-wpcpm-institutions.php', 'includes/modules/class-wpcpm-institutions-dashboard.php' ) as $file ) {
	$enqueues += substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ), "wp_enqueue_script( 'wpcpm-forms' )" );
}

ck( 'no screen these forms are drawn on enqueues it yet, which is what the docblock says', $enqueues, 0 );

/* ---- every outcome the handlers flash has words --------------------------- */

echo "\n=== Every outcome the handlers flash has words on this page ===\n";

$named = array_keys( WPCPM_Institution_Panel::messages() );

ck( 'messages() names every slug the contract lists',
	array_values( array_diff( array(
		'agreement-uploaded',
		'agreement-too-big',
		'agreement-not-pdf',
		'agreement-encrypted',
		'agreement-launch',
		'agreement-in-review',
		'agreement-declare',
		'agreement-kind',
		'agreement-accepted',
		'agreement-returned',
		'agreement-withdrawn',
		'agreement-note',
		'agreement-stage',
		'agreement-generated-later',
		'agreement-name',
	), $named ) ),
	array() );

// And every slug the two handler files actually flash, read off their source. This is the
// assertion that pays for itself: `render_flash()` prints nothing at all for a slug it does
// not know, so a handler that refuses with a name nobody wrote words for is a button that
// looks like it did nothing. Three of these were found exactly this way.
$flashed_slugs = array();
foreach ( array( $agr_src, $generate_src ) as $handler_src ) {
	preg_match_all( "/(?:self::bounce(?:_on_file)?\(\s*|WPCPM_Flash::set\(\s*WPCPM_Institutions::FLASH\s*,\s*|return\s+|'reason'\s*=>\s*)'(agreement-[a-z-]+)'/", $handler_src, $found );
	$flashed_slugs = array_merge( $flashed_slugs, $found[1] );
}
$flashed_slugs = array_values( array_unique( $flashed_slugs ) );

ck( 'the handlers really do flash a dozen or more outcomes', count( $flashed_slugs ) >= 12, true );
ck( 'and every one of them has words here', array_values( array_diff( $flashed_slugs, $named ) ), array() );

ck( 'the size the upload form offers is the size the refusal names', array(
	false !== strpos( $upload_only, 'as a PDF of up to 10 MB' ),
	false !== strpos( WPCPM_Institution_Panel::messages()['agreement-too-big'][1], 'larger than the 10 MB this site accepts' ),
), array( true, true ) );

$settings_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php' );
ck( 'and ten is what the real defaults say', false !== strpos( $settings_src, "'agreement_max_mb'              => 10," ), true );

ck( 'every message is a known notice type', array_values( array_unique( array_map( function ( $row ) { return $row[0]; }, WPCPM_Institution_Panel::messages() ) ) ), array( 'success', 'error', 'info' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
