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
 * - **The reviewer's block comes back where it was drawn.** On the Administrator Dashboard its
 *   Accept and Return carry that page's way back to the Collaboration Agreements card, as every
 *   decision posted from that page does, since the dashboard passes `WPCPM_Return::DASHBOARD`
 *   after `true`; a block given no way back carries none, and the handler returns where the
 *   press came from.
 * - **The review block decides on one page and is read on the other.** The Administrator
 *   Dashboard passes `true` and gets Accept and Return; the Institutions screen's queue passes
 *   `false` and gets the same block to read, with the way to the dashboard's own copy of it in
 *   the decisions' place, or nothing there while that page is missing, since the queue says so
 *   once above its list. Both callers are read off their source, since each one's suite stands
 *   this in.
 * - **One mechanism per form for a manager acting on behalf.** The generate and Regenerate
 *   forms put the switcher on the action URL, where `resolve_institution()` reads it, and
 *   carry no record field, because that class stopped reading one. The upload and on-file
 *   forms do the opposite, because their handlers read the posted record themselves. Both
 *   sides of each pair are read here, off the two sources, since a mechanism only one half of
 *   a pair implements is exactly the defect Phase 3 shipped.
 * - **The stylesheets parse the way their comments read.** A comment between two selectors is
 *   a descendant combinator that welds two rules into one; a physical side in a file whose
 *   convention is logical puts a bar on the wrong edge in an RTL locale; free text on paper
 *   has to wrap. Each was found by hand once, and each is read off the source here.
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

$GLOBALS['opts']       = array( 'date_format' => 'F j, Y', 'time_format' => 'g:i a' );
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
// Core's, which escapes every "&" again: a refused return note comes back in its box.
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
// Core's two cleaners and its decoder as 7.1.2 writes them, the ones the typed-text rules read.
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/parts-inventory.php';
require_once __DIR__ . '/stubs/cleaners.php';
// Core's esc_url_raw() and wp_check_invalid_utf8() as 7.1.2 writes them, which a Drive link is
// read and stored through: an identity stand-in would keep a "%0A" core takes out.
require_once __DIR__ . '/stubs/link-cleaners.php';
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wpautop( $s ) { return '<p>' . str_replace( "\n", "<br />\n", (string) $s ) . '</p>'; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? $GLOBALS['clock'] : (int) $t ); }
function date_i18n( $f, $t = false ) { return gmdate( $f, (int) $t ); }
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
// The nonce as the suite has always read it: the nonce keyed to its action, alone. With `$GLOBALS['faithful_nonces']`
// set, as WordPress prints it: the field with the id core gives it and the referer beside it, so a form drawn
// through `WPCPM_UI::nonce()` (the same name and value, no id) can be compared with today's field by field.
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) {
	if ( empty( $GLOBALS['faithful_nonces'] ) ) {
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( 'nonce-' . $a ) . '" />';
		return;
	}
	echo '<input type="hidden" id="_wpnonce" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $a ) ) . '" />';
	if ( $r ) {
		wp_referer_field();
	}
}
function wp_create_nonce( $a = '' ) { return 'nonce-' . $a; }
function wp_referer_field( $display = true ) {
	$field = '<input type="hidden" name="_wp_http_referer" value="/administrator-dashboard/" />';
	if ( $display ) {
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A constant.
	}
	return $field;
}
// What the shared parts (`WPCPM_UI`) ask of WordPress: which side draws, a refusal said to the developer, and the
// parts' sheet and script, registered once each before the script is asked for.
function is_admin() { return ! empty( $GLOBALS['is_admin'] ); }
function _doing_it_wrong( $function, $message, $version ) { $GLOBALS['doing_it_wrong'][] = $message; }
function wp_style_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['registered_styles'][ $h ] ); }
function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['registered_styles'][ $h ] = $src; }
function wp_script_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['registered_scripts'][ $h ] ); }
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['registered_scripts'][ $h ] = $src; }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['nonces'][] = $a; return true; }
require_once __DIR__ . '/stubs/caps.php';
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
	if ( 0 === strpos( (string) $k, 'wpcpm_agreement_' ) && 0 !== strpos( (string) $k, 'wpcpm_agreement_lock_' ) ) {
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
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
// Core's own, beside meta stand-ins that unslash what they are handed as core's do: the flash and
// every write of words hand them a slashed copy, and it reads back as it was given.
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
// As core's: what is written is unslashed first, at any depth.
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = stripslashes_deep( $value ); return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( stripslashes_deep( $value ) ); return true; }

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
// What the courtesy scan noticed, in words (`WPCPM_Pdf_Check::in_words()`).
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-pdf-check.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
// The shared parts the review block draws on the Administrator Dashboard while its switch is on (Task 11).
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
// The real way back, which prints nothing for a form drawn anywhere but the Administrator Dashboard.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

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

			// A tutor holds the four view actions and the agreement is not among them (the real rule is
			// `bin/test-institution-policy.php`'s).
			if ( ! empty( $GLOBALS['tutor'] ) && self::ACT_AGREEMENT === $action ) {
				return self::answer( false, '', '' );
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
	/** The one write path, journaled so its position among the three writes can be asserted. */
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
		/** The form a refused generate keeps its typing under (Task 21, fix round 1); pinned against the real file below. */
		const KEPT_GENERATE = 'agreement-generate:';
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

// The Administrator Dashboard's page, which the review block links to on the screen that only reads it,
// and the real class that prints that link for every screen and block that links there.
require_once __DIR__ . '/stubs/administrators-dashboard.php';
require_once __DIR__ . '/stubs/view-faults.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

// The Administrator an institution is assigned to (Institutions off Airtable, R1), at the one read the assignment
// makes of the institution's record and of the booking link.
if ( ! class_exists( 'WPCPM_Institution_Record' ) ) {
	class WPCPM_Institution_Record {
		public static function get( $record_id ) { return isset( $GLOBALS['kept'][ $record_id ] ) ? $GLOBALS['kept'][ $record_id ] : array(); }
	}
}
if ( ! class_exists( 'WPCPM_Manager_Booking' ) ) {
	class WPCPM_Manager_Booking {
		public static function url_for( $user_id ) { return ''; }
	}
}
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
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
 * Assign an institution to an Administrator, the way its record keeps it. The Countries table still names
 * poland@example.org for the country, which nothing reads any more.
 *
 * @param string $record  Institutions record ID.
 * @param int    $user_id The Administrator.
 */
function seed_assignee( $record, $user_id = 12 ) {
	$GLOBALS['users'][ $user_id ]  = new WP_User( $user_id, 'Ana Admin', 'ana.admin@example.org' );
	$GLOBALS['grants'][ $user_id ] = array( 'wpcpm_manage_program' );
	$GLOBALS['kept'][ $record ]    = array( 'assigned' => $user_id );
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
	$GLOBALS['kept']       = array();
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

ck( 'generated: and not the date as year-month-day', false !== strpos( $generated, 'agreement on 2025-09-01' ), false );
$ledes['generated'] = $generated;
// Read while the generated document is there: the sentence names its day and its template.
$generated_sentence = method_exists( 'WPCPM_Institution_Panel', 'state_sentence' ) ? WPCPM_Institution_Panel::state_sentence( 'generated', WPCPM_Institution_Agreement::summary( $rec_a ) ) : 'no state_sentence()';

reset_world();
seed_index( $rec_a, $country );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_OWN );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Awaiting review' ) );
$submitted = panel( $rec_a );

ck( 'submitted: and not the date as year-month-day', false !== strpos( $submitted, 'agreement on 2025-09-01' ), false );
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
ck( 'returned: and not as year-month-day', false !== strpos( $returned, '2026-08-30' ), false );
$ledes['returned'] = $returned;

// A note nobody dated falls back to the day the document was written, in the same format.
reset_world();
seed_index( $rec_a, $country );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_RETURNED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
	WPCPM_Institution_Agreement::META_NOTE       => 'An older note.',
	WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
) );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Returned' ) );
$undated = panel( $rec_a );
ck( 'and not as year-month-day', false !== strpos( $undated, '2025-09-01' ), false );

reset_world();
seed_index( $rec_a, $country );
$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] = array(
	'v'    => WPCPM_Countries::VERSION,
	'read' => 1756600000,
	'rows' => array( $country => array( 'name' => 'Poland', 'manager' => 'recTEAMAAAAAAAA1', 'email' => 'poland@example.org', 'calendly' => '' ) ),
);
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_REVOKED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
	WPCPM_Institution_Agreement::META_NOTE       => 'The partnership ended in July.',
	WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
	WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-31',
) );
seed_assignee( $rec_a );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Revoked' ) );
$revoked = panel( $rec_a );

ck( 'revoked: says the program revoked it and what is left', false !== strpos( $revoked, 'revoked this institution&#039;s agreement' ), true );
ck( 'revoked: the note verbatim', false !== strpos( $revoked, 'The partnership ended in July.' ), true );
ck( 'revoked: names the assigned Administrator\'s address, not the Countries table\'s', array(
	false !== strpos( $revoked, 'Your program contact for Poland is ana.admin@example.org.' ),
	false === strpos( $revoked, 'poland@example.org' ),
), array( true, true ) );
$ledes['revoked'] = $revoked;

// An assigned school whose country the map does not hold: the neutral sentence, never one with a blank country.
reset_world();
seed_index( $rec_a, 'recCOUNTRYMISSING1' );
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_REVOKED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
	WPCPM_Institution_Agreement::META_NOTE       => 'The partnership ended in July.',
	WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
	WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-31',
) );
seed_assignee( $rec_a );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Revoked' ) );
$no_country = panel( $rec_a );
ck( 'revoked, assigned, country not in the map: the neutral sentence, not "for  is"', array(
	false !== strpos( $no_country, 'Write to the Administrator who has been in touch with your institution.' ),
	false === strpos( $no_country, 'Your program contact for' ),
), array( true, true ) );

$first_lines = array();
foreach ( $ledes as $state => $html ) {
	preg_match( '/<p class="wpcpm-agreement-panel__lede">(.*?)<\/p>/s', $html, $lede );
	$first_lines[ $state ] = $lede[1] ?? '';
}

$read_line = '/The agreement&#039;s state was read from the program records on [A-Z][a-z]+ \d{1,2}, \d{4} \d{1,2}:\d{2} [ap]m \(2 hours ago\)\./';
ck( 'and none in the old words or as year-month-day and a clock', count( array_filter( $ledes, function ( $html ) { return false !== strpos( $html, 'Agreement state: read' ) || 1 === preg_match( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $html ); } ) ), 0 );

// The exact moment, and a site with another format: the read time is the option's own.
$updated = (int) WPCPM_Institution_Agreement::option( $rec_a )['updated'];
$GLOBALS['opts']['date_format'] = 'd/m/Y';
$GLOBALS['opts']['time_format'] = 'H:i';
ck( 'a site with another date and time format reads its own read time', false !== strpos( strip_tags( panel( $rec_a ) ), 'on ' . gmdate( 'd/m/Y H:i', $updated ) . ' (2 hours ago).' ), true );
$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';

// Nothing read yet: the undated sentence keeps its words.
$unread_option            = WPCPM_Institution_Agreement::option( $rec_a );
$unread_option['updated'] = 0;
$GLOBALS['opts'][ WPCPM_Institution_Agreement::option_name( $rec_a ) ] = $unread_option;
ck( 'with nothing read yet the panel says so, in the words it had', false !== strpos( panel( $rec_a ), 'The agreement state has not been read from the program&#039;s records yet.' ), true );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Revoked' ) );

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
ck( 'and not as year-month-day', false !== strpos( $grid_card, gmdate( 'Y-m-d', $GLOBALS['clock'] ) ), false );
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
$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] = array(
	'v'    => WPCPM_Countries::VERSION,
	'read' => 1756600000,
	'rows' => array( $country => array( 'name' => 'Poland', 'manager' => 'recTEAMAAAAAAAA1', 'email' => 'poland@example.org', 'calendly' => '' ) ),
);
seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_RETURNED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
	WPCPM_Institution_Agreement::META_NOTE       => 'Page 4 is not signed.',
	WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
	WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-30',
) );
seed_assignee( $rec_a );
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Returned' ) );

$stranger = panel( $rec_a, false, $rec_b );

ck( 'a member of another institution gets nothing from the panel', $stranger, '' );
ck( 'not the state this one\'s agreement is in', false !== strpos( $stranger, 'returned your agreement' ), false );
ck( 'not the program manager\'s note', false !== strpos( $stranger, 'Page 4 is not signed.' ), false );
ck( 'and not the assigned Administrator\'s address', false !== strpos( $stranger, 'ana.admin@example.org' ), false );
ck( 'a member of this one gets all three', array(
	false !== strpos( panel( $rec_a ), 'returned your agreement' ),
	false !== strpos( panel( $rec_a ), 'Page 4 is not signed.' ),
	false !== strpos( panel( $rec_a ), 'ana.admin@example.org' ),
), array( true, true, true ) );
ck( 'and so does a program manager, who is a member of nothing', false !== strpos( panel( $rec_a, true ), 'Page 4 is not signed.' ), true );
$GLOBALS['tutor'] = true;
ck( 'a tutor of this one is drawn no panel: no state, no note, no upload and no generate form', array( panel( $rec_a ), forms_in( panel( $rec_a ) ) ), array( '', array( 'generate' => 0, 'upload' => 0, 'withdraw' => 0, 'accept' => 0, 'return' => 0, 'download' => 0 ) ) );
$GLOBALS['tutor'] = false;

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
$GLOBALS['tutor'] = true;
ck( 'a tutor\'s card has no sentence offering an upload, since none is drawn', array( false !== strpos( card( $rec_a ), 'Replace it by uploading' ), false !== strpos( card( $rec_a ), 'type="file"' ) ), array( false, false ) );
$GLOBALS['tutor'] = false;
ck( 'a representative\'s card keeps the sentence, and the upload it offers', array( false !== strpos( card( $rec_a ), 'Replace it by uploading a newer signed copy.' ), false !== strpos( card( $rec_a ), 'type="file"' ) ), array( true, true ) );

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
ck( 'the card names the accepted date in the site\'s format', array( false !== strpos( strip_tags( $site_card ), 'September 1, 2025' ), false !== strpos( strip_tags( $site_card ), '1 Sep 2025' ) ), array( true, false ) );
ck( 'and not as year-month-day', false !== strpos( $site_card, '2025-09-01' ), false );
$GLOBALS['opts']['date_format'] = 'j M Y';
ck( 'a site with another date format reads its own, on the card', array( false !== strpos( strip_tags( card( $rec_a ) ), '1 Sep 2025' ), false !== strpos( strip_tags( card( $rec_a ) ), 'September 1, 2025' ) ), array( true, false ) );
$GLOBALS['opts']['date_format'] = 'F j, Y';
ck( 'and says a manager recorded it here from the program\'s copy', false !== strpos( $site_card, 'Recorded on this site by an Administrator' ), true );
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

// 1.123.58, IDB-09: the panel's outcome carries its tone, is heard, and can take the focus. One
// fresh manager a message, for the reason above.
$GLOBALS['uid']       = 60;
$GLOBALS['users'][60] = new WP_User( 60, 'Eli Manager', 'eli@example.test' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-not-pdf', 60 );
$not_pdf              = panel( $rec_b, true );
$GLOBALS['uid']       = 61;
$GLOBALS['users'][61] = new WP_User( 61, 'Fay Manager', 'fay@example.test' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-uploaded', 61 );
$uploaded             = panel( $rec_b, true );
$css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );
ck(
	'and the page colors the two tones on the leading border of the panel\'s, the import\'s and the report\'s messages',
	array(
		1 === preg_match( '/\.wpcpm-agreement-panel__message\.is-success,\s*\.wpcpm-import__message\.is-success,\s*\.wpcpm-report-card__message\.is-success\s*\{\s*border-inline-start-color: #1a7f37;/', $css ),
		1 === preg_match( '/\.wpcpm-agreement-panel__message\.is-error,\s*\.wpcpm-import__message\.is-error,\s*\.wpcpm-report-card__message\.is-error\s*\{\s*border-inline-start-color: #b32d2e;/', $css ),
	),
	array( true, true )
);

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

/* ---- the bulk form, retired ---------------------------------------------- */

echo "\n=== Every Confirmed institution recorded as signed in one press: retired in 1.123.26 ===\n";

// Until 1.123.26 the Institutions screen's Agreements tab carried a form that recorded every
// Confirmed institution with nothing recorded as signed, with one Drive link and no date guard. It
// was the day-one migration's tool; left live, it would record as signed an institution confirmed
// last week that has signed nothing. Each agreement is now recorded on its own institution's page,
// through the single route above, and nothing of the bulk route is left to reach.
$agr_src_now   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
$panel_src_now = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php' );

ck( 'the handler, its action, its option and its time budget are gone', array(
	method_exists( 'WPCPM_Institution_Agreement', 'handle_on_file_all' ),
	method_exists( 'WPCPM_Institution_Agreement', 'last_on_file_all' ),
	defined( 'WPCPM_Institution_Agreement::ACTION_ON_FILE_ALL' ),
	defined( 'WPCPM_Institution_Agreement::OPT_ON_FILE_ALL' ),
	defined( 'WPCPM_Institution_Agreement::ON_FILE_ALL_BUDGET' ),
), array( false, false, false, false, false ) );
ck( 'no hook names the bulk action, and nothing posts to it', array(
	substr_count( $agr_src_now, 'on_file_all' ),
	substr_count( $agr_src_now, 'ON_FILE_ALL' ),
), array( 0, 0 ) );
ck( 'the outcomes only the bulk route left are gone from the panel\'s words, and its summary with them', array(
	array_key_exists( 'agreement-on-file-all', WPCPM_Institution_Panel::messages() ),
	array_key_exists( 'agreement-all-none', WPCPM_Institution_Panel::messages() ),
	method_exists( 'WPCPM_Institution_Panel', 'on_file_all_summary' ),
	substr_count( $panel_src_now, 'on_file_all' ),
), array( false, false, false, 0 ) );
ck( 'and the single route stays, with its words', array(
	method_exists( 'WPCPM_Institution_Agreement', 'handle_on_file' ),
	array_key_exists( 'agreement-on-file', WPCPM_Institution_Panel::messages() ),
	false !== strpos( $panel_src_now, "'Record an agreement the program already holds'" ),
), array( true, true, true ) );

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
	// Every output form: `printf()`, `echo`, and the parts' block both halves draw into (Task 23).
	// An empty list would mean the method body was not found at all, and reads here as a
	// failing assertion rather than as a fatal inside min().
	$prints = array_filter( array( strpos( $render, 'printf(' ), strpos( $render, 'echo ' ), strpos( $render, 'parts_block(' ) ), 'is_int' );

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

// The route reads its form in one method and writes in another: the counts are per method, so a
// reader added to the wrong one is a failing assertion rather than a total that still happens to
// add up.
$single = method_body( $agr_src, 'handle_on_file' );
$write  = method_body( $agr_src, 'record_on_file' );

ck( 'the handler reads the form with the posted_* readers, never the query string, and the Drive link as typed',
    array( substr_count( $single, 'WPCPM_Request::posted_text(' ), substr_count( $single, 'WPCPM_Request::posted_verbatim( \'wpcpm_agreement_drive\' )' ), substr_count( $agr_src, 'WPCPM_Request::text(' ) ),
    array( 3, 1, 0 ) );
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

// Three refusals after the lock, plus the release before the rebuild. Counted rather than
// read, because the one that goes missing is the one nobody notices until an institution
// cannot be recorded for five minutes.
ck( 'every exit after the lock gives it back', substr_count( $write, 'self::unlock(' ), 4 );
ck( 'and the log row is written while the lock is still held', strpos( $write, 'WPCPM_Institution_Audit::record(' ) < strrpos( $write, 'self::unlock(' ), true );
ck( 'the write never redirects: the handler that called it decides where the reader lands', strpos( $write, 'bounce_on_file(' ), false );


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
	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1756600000,
		'rows' => array( 'recCOUNTRYAAAAAAA' => array( 'name' => 'Poland', 'manager' => 'recTEAMAAAAAAAA1', 'email' => 'poland@example.org', 'calendly' => '' ) ),
	);

	seed_assignee( $record );
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
// the roster rather than assumed here: `resolve_institution()` honors the switcher for a
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

// The manager's form is drawn in wp-admin, where only core's button classes dress a button; the
// same form on the Institution Dashboard keeps the dashboard's own class, which its stylesheet
// dresses.
ck( 'the manager\'s Upload is core\'s primary button, and the dashboard\'s keeps the dashboard\'s class', array(
	substr_count( $on_behalf, '<button type="submit" class="button button-primary">Upload the signed agreement</button>' ),
	substr_count( $on_behalf, 'class="wpcpm-button"' ),
	substr_count( $upload_only, '<button type="submit" class="wpcpm-button">Upload the signed agreement</button>' ),
	substr_count( $upload_only, 'button-primary' ),
), array( 1, 0, 1, 0 ) );

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

// The Withdraw drawn on the manager's view is wp-admin's too, so it is core's secondary button, as
// the Upload there is core's primary one; drawn as the Institution Dashboard draws it, it keeps the
// dashboard's own class, which its stylesheet dresses.
ob_start();
WPCPM_Institution_Panel::render_withdraw_form( $waiting );
$withdraw_elsewhere = (string) ob_get_clean();

ck( 'the manager\'s Withdraw is core\'s secondary button, and the one the dashboard draws keeps the dashboard\'s class', array(
	substr_count( $row_busy, '<button type="submit" class="button button-secondary" data-wpcpm-confirm=' ),
	substr_count( $row_busy, 'wpcpm-button' ),
	substr_count( $withdraw_elsewhere, '<button type="submit" class="wpcpm-button" data-wpcpm-confirm=' ),
	substr_count( $withdraw_elsewhere, 'button-secondary' ),
), array( 1, 0, 1, 0 ) );

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
// another organization's document, with nobody emailed about it. A control drawn on forty rows
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
 * Drawn to decide, it is called with the post alone, so every check of the two decisions is a check
 * that the defaults still draw them, with no way back; the Administrator Dashboard's own call, which
 * adds `true` and its way back, is drawn below. Drawn to be read, with the `false` the Institutions
 * screen's queue passes.
 *
 * @param int         $post_id    Agreement post ID.
 * @param bool        $can_manage Whether the viewer holds CAP_MANAGE.
 * @param string|null $member_of  The institution the viewer belongs to; null for none.
 * @param bool        $decide     Whether the block is drawn to decide.
 * @return string
 */
function review( $post_id, $can_manage = true, $member_of = null, $decide = true ) {
	$GLOBALS['caps']      = $can_manage;
	$GLOBALS['member_of'] = null === $member_of ? array() : array( $member_of );
	ob_start();
	if ( $decide ) {
		WPCPM_Institution_Panel::render_review( $post_id );
	} else {
		WPCPM_Institution_Panel::render_review( $post_id, false );
	}
	return (string) ob_get_clean();
}

reset_world();
seed_index( $rec_a, '' );
$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska', 'Bo Nowak', 'Cy Wisniewski' );
$review_id = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
$template_review = review( $review_id );

ck( 'the block names the institution it is about', false !== strpos( $template_review, 'Review the signed agreement from Universidad Example' ), true );
ck( 'and who uploaded it, when, in the site\'s date format, and how big it is', false !== strpos( $template_review, 'Uploaded by Wera Rektor on September 1, 2025, 234 KB.' ), true );
ck( 'and not the date as year-month-day', false !== strpos( $template_review, '2025-09-01' ), false );
$GLOBALS['opts']['date_format'] = 'j M Y';
ck( 'a site with another date format reads its own', false !== strpos( review( $review_id ), 'Uploaded by Wera Rektor on 1 Sep 2025, 234 KB.' ), true );
$GLOBALS['opts']['date_format'] = 'F j, Y';
ck( 'the checklist is read, not ticked: three sentences and no boxes', array( substr_count( $template_review, '<li class="wpcpm-ui-checklist__item">' ), substr_count( $template_review, 'type="checkbox"' ) ), array( 3, 0 ) );
ck( 'a template-kind document names the version the footer should carry', false !== strpos( $template_review, 'The footer on the signed copy names template version 2025-11-04.' ), true );
ck( 'and the two names, side by side', false !== strpos( $template_review, 'The name on the document is Universidad Example Sede Central. Airtable&#039;s Name for this institution is Universidad Example.' ), true );
ck( 'and asks whether the signature block is filled', false !== strpos( $template_review, 'signature block is filled in' ), true );
ck( 'the flags line says what the scan noticed in words, each with the PDF\'s own name for it', false !== strpos( $template_review, 'The scan noticed these in the file: JavaScript code (/JavaScript), an action that runs when the file is opened (/OpenAction).' ), true );
ck( 'and says in the same breath that it is not evidence', false !== strpos( $template_review, 'The scan is a courtesy and not evidence' ), true );
// A stored name the scanner does not record is drawn as stored, and as text: a name holding a tag, a
// quote and an "&" beside one it words.
$hostile_id     = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array_merge( $file_meta, array( WPCPM_Institution_Agreement::META_FLAGS => array( '<b>"x"&</b>', '/OpenAction' ) ) ) );
$hostile_review = review( $hostile_id );
ck( 'a stored name holding a tag is drawn as text in the review, escaped, beside one in words', array( false !== strpos( $hostile_review, 'The scan noticed these in the file: &lt;b&gt;&quot;x&quot;&amp;&lt;/b&gt;, an action that runs when the file is opened (/OpenAction).' ), false !== strpos( $hostile_review, '<b>"x"' ) ), array( true, false ) );
ck( 'and what does protect the reviewer', false !== strpos( $template_review, 'never opens the file in your browser' ), true );
ck( 'the copy itself is one nonced link away', false !== strpos( $template_review, 'action=wpcpm_agreement_download&post=' . $review_id . '&_wpnonce=nonce-wpcpm_agreement_download_' . $review_id ), true );
ck( 'and the file is still nowhere in the page', array( false !== strpos( $template_review, '0123456789abcdef' ), false !== strpos( $template_review, 'Umowa podpisana' ) ), array( false, false ) );

ck( 'the two ways out are Accept and Return, one each', array_intersect_key( forms_in( $template_review ), array( 'accept' => 0, 'return' => 0, 'upload' => 0, 'withdraw' => 0 ) ), array( 'upload' => 0, 'withdraw' => 0, 'accept' => 1, 'return' => 1 ) );
ck( 'Accept is keyed to the document', false !== strpos( $template_review, 'value="nonce-wpcpm_agreement_accept_' . $review_id . '"' ), true );
ck( 'Return is too', false !== strpos( $template_review, 'value="nonce-wpcpm_agreement_return_' . $review_id . '"' ), true );
ck( 'and both carry the post ID under the name the handlers read', substr_count( $template_review, 'name="wpcpm_agreement_post" value="' . $review_id . '"' ), 2 );

// Drawn as the Administrator Dashboard draws it, with `true` and its way back, the block's two
// decisions come back to that page's Collaboration Agreements card, as every decision posted from
// that page does; drawn with no way back, the forms carry none and are what they were.
$GLOBALS['caps']      = true;
$GLOBALS['member_of'] = array();
ob_start();
WPCPM_Institution_Panel::render_review( $review_id, true, WPCPM_Return::DASHBOARD );
$dashboard_review = (string) ob_get_clean();
$back_to_card     = '<input type="hidden" name="wpcpm_return" value="dashboard" /><input type="hidden" name="wpcpm_return_to" value="agreements" />';
ck( 'drawn on the Administrator Dashboard, Accept and Return each carry the way back to its Collaboration Agreements card, after the post they decide; given no way back, neither carries one', array(
	substr_count( $dashboard_review, 'name="wpcpm_agreement_post" value="' . $review_id . '" />' . $back_to_card ),
	str_replace( $back_to_card, '', $dashboard_review ) === $template_review,
	substr_count( $template_review, 'wpcpm_return' ),
), array( 2, true, 0 ) );

// The confirm names everything that leaves the building: the institution, the Airtable stage
// and the number of inboxes. The count comes from members_of(), so a fourth member added
// this morning is a four in the dialog this afternoon.
ck( 'the Accept confirm names the institution', false !== strpos( $template_review, 'Accept the signed agreement from Universidad Example?' ), true );
ck( 'names Confirmed', false !== strpos( $template_review, 'sets Current Stage to Confirmed in Airtable' ), true );
ck( 'names how many people it emails', false !== strpos( $template_review, 'emails the 3 people at the institution' ), true );
ck( 'and says it can be undone, and where: the Agreements tab of the Institutions screen, which is true wherever the dialog is drawn', array(
	false !== strpos( $template_review, 'and emails the 3 people at the institution. You can revoke it later on the Institutions screen, in the Agreements tab.' ),
	false !== strpos( $template_review, 'from here' ),
), array( true, false ) );

$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska' );
$one_member_review            = review( $review_id );
ck( 'one member is one person, not one people', false !== strpos( $one_member_review, 'emails the 1 person at the institution' ), true );
ck( 'and the singular dialog ends the same way, with no "from here" in it', array(
	false !== strpos( $one_member_review, 'emails the 1 person at the institution. You can revoke it later on the Institutions screen, in the Agreements tab.' ),
	false !== strpos( $one_member_review, 'from here' ),
), array( true, false ) );
$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska', 'Bo Nowak', 'Cy Wisniewski' );

ck( 'the return note is the one free-text field in the block', substr_count( $template_review, '<textarea' ), 1 );
ck( 'named the way the handler reads it, and bounded the way it refuses', 1 === preg_match( '#<textarea [^>]*name="wpcpm_agreement_note" required minlength="20" maxlength="2000"[^>]* rows="4">#', $template_review ), true );
ck( 'and said to be mailed verbatim', false !== strpos( $template_review, 'exactly as you write it' ), true );

// A return refused for its own words lands back on this block, and the box gets back the note as it
// was typed: in this document's box, for the person who typed it, once. The text area prints a line
// feed right after its opening tag, which the parser drops, so a note that begins with a line break
// keeps it. The refusal names the box by the first words of its label.
$returned_note = "\nClause 4 says kids <12 free, adults >18 pay & \"more\".\r\nPlease change it.";
$returned_kept = str_replace( "\r\n", "\n", $returned_note );
$GLOBALS['uid'] = 21;
WPCPM_Typed_Text::keep_box( WPCPM_Typed_Text::box_form( WPCPM_Institution_Agreement::TYPED_RETURN, $review_id ), 'note', $returned_note, WPCPM_Institution_Agreement::MAX_NOTE );
$GLOBALS['uid'] = 22;
$someone_else   = review( $review_id );
$GLOBALS['uid'] = 21;
$kept_review    = review( $review_id );
$GLOBALS['umeta'][23][ WPCPM_Flash::META ] = $GLOBALS['umeta'][21][ WPCPM_Flash::META ] ?? array();
$GLOBALS['uid'] = 23;
$next_review    = review( $review_id );
$GLOBALS['uid'] = 0;
// What the note's box holds as the parts draw it (`WPCPM_UI::field()`), the line feed the parser drops after the
// opening tag included; null with no such box (Task 18b: the old form is gone).
$note_box       = static function ( $html ) {
	return preg_match( '#<textarea [^>]*name="wpcpm_agreement_note" required minlength="20" maxlength="2000"[^>]* rows="4">(.*?)</textarea>#s', (string) $html, $m ) ? $m[1] : null;
};
ck( 'a refused return\'s note comes back in its box exactly as typed, once, the box bounded as before', array(
	$note_box( $kept_review ) === "\n" . esc_textarea( $returned_kept ),
	html_entity_decode( esc_textarea( $returned_kept ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
	substr_count( $kept_review, '<textarea' ),
), array( true, $returned_kept, 1 ) );
ck( 'and it is taken: the page after that draws the box empty, as does another person\'s', array( $note_box( $next_review ), $note_box( $someone_else ), $someone_else === $template_review ), array( "\n", "\n", true ) );
ck( 'the refusal names the box by its label and says to return it again', WPCPM_Institution_Panel::messages()['agreement-return-loss'] ?? null, array( 'error', 'Nothing was returned, because WordPress would remove part of what you typed in "What has to change, in your own words". To keep every word, put a space after each "<" (or write "less than") and after each "%", then return it again.' ) );
ck( 'the revoke refusals: a note the cleaner would take words from names its box by its label and says to revoke it again, and the Institutions screen\'s Agreements tab prints both', array(
	WPCPM_Institution_Panel::messages()['agreement-revoke-loss'] ?? null,
	WPCPM_Institution_Panel::messages()['agreement-revoke-note'] ?? null,
), array(
	array( 'error', 'Nothing was revoked, because WordPress would remove part of what you typed in "Why it is revoked, in your own words". To keep every word, put a space after each "<" (or write "less than") and after each "%", then revoke it again.' ),
	array( 'error', 'Nothing was revoked. A note is required, between 20 and 2000 characters: it is emailed to the institution as the reason, so it has to say something.' ),
) );
unset( $GLOBALS['umeta'][21], $GLOBALS['umeta'][22], $GLOBALS['umeta'][23] );
ck( 'drawn to decide, the block does not send the reviewer anywhere else', strpos( $template_review, 'wpcpm-review__open' ), false );

/* ---- the same block, read on the Institutions screen ------------------------ */

echo "\n=== Read on the Institutions screen, the block links to its decisions on the Administrator Dashboard ===\n";

// The queue in wp-admin draws the block to be read: everything a reviewer reads is the same, and
// the two decisions are the Administrator Dashboard's, so they give way to one line that links to
// this block there, by the anchor both pages print.
$read_review = review( $review_id, true, null, false );

ck( 'read, the block keeps everything a reviewer reads', array(
	false !== strpos( $read_review, '<section class="wpcpm-review" id="wpcpm-review-' . $review_id . '">' ),
	false !== strpos( $read_review, 'Review the signed agreement from Universidad Example' ),
	false !== strpos( $read_review, 'Uploaded by Wera Rektor on September 1, 2025, 234 KB.' ),
	false !== strpos( $read_review, 'The institution says this is the program&#039;s template, signed.' ),
	substr_count( $read_review, '<li>' ),
	false !== strpos( $read_review, 'The scan noticed these in the file: JavaScript code (/JavaScript), an action that runs when the file is opened (/OpenAction).' ),
	false !== strpos( $read_review, 'The scan is a courtesy and not evidence' ),
	false !== strpos( $read_review, 'action=wpcpm_agreement_download&post=' . $review_id . '&_wpnonce=nonce-wpcpm_agreement_download_' . $review_id ),
), array( true, true, true, true, 3, true, true, true ) );
ck( 'and draws neither decision: no form, no note, no post field', array(
	forms_in( $read_review )['accept'],
	forms_in( $read_review )['return'],
	substr_count( $read_review, '<form' ),
	substr_count( $read_review, '<textarea' ),
	substr_count( $read_review, 'wpcpm_agreement_post' ),
), array( 0, 0, 0, 0, 0 ) );
ck( 'in their place, last in the block, one line linking to this block on the Administrator Dashboard', array(
	substr_count( $read_review, 'wpcpm-review__open' ),
	false !== strpos( $read_review, '<p class="wpcpm-review__open"><a href="https://example.test/administrator-dashboard/#wpcpm-review-' . $review_id . '">Open on the Administrator Dashboard</a></p></section>' ),
), array( 1, true ) );

WPCPM_Administrators_Dashboard::$url = '';
$read_no_page                        = review( $review_id, true, null, false );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';

// The queue, the one page that reads the block, says the page is missing once above its whole
// list; a block that said it again would say it once more for every document waiting.
ck( 'while that page is missing the block ends with the download link: no line, no sentence, no form', array(
	1 === preg_match( '#<p class="wpcpm-agreement-panel__download"><a [^>]*>Download the signed agreement</a></p></section>$#', $read_no_page ),
	substr_count( $read_no_page, 'wpcpm-review__open' ),
	strpos( $read_no_page, 'The dashboard class says its page is missing.' ),
	forms_in( $read_no_page )['accept'] + forms_in( $read_no_page )['return'],
), array( true, 0, false, 0 ) );
ck( 'and whoever may not review it is drawn nothing either way', array( review( $review_id, false, $rec_a, false ), review( $review_id, false, $rec_b, false ) ), array( '', '' ) );
ck( 'passing true is the default, the Administrator Dashboard\'s', ( function () use ( $review_id, $template_review ) {
	$GLOBALS['caps']      = true;
	$GLOBALS['member_of'] = array();
	ob_start();
	WPCPM_Institution_Panel::render_review( $review_id, true );
	return (string) ob_get_clean() === $template_review;
} )(), true );

// Which page passes which is said where the next reader of the method will look for it.
$review_doc = substr( $panel_src, 0, (int) strpos( $panel_src, 'public static function render_review(' ) );
$review_doc = substr( $review_doc, (int) strrpos( $review_doc, '/**' ) );
ck( 'the docblock says which page decides and which reads, and where a decision comes back to', array(
	1 === preg_match( '/@param\s+bool\s+\$decide\s/', $review_doc ),
	1 === preg_match( '/@param\s+string\s+\$where\s/', $review_doc ),
	false !== strpos( $review_doc, 'Administrator Dashboard' ),
	false !== strpos( $review_doc, 'Institutions screen' ),
), array( true, true, true, true ) );

// And each page passes what the docblock says, read off the two callers: the suite of either page
// stands this class in, so neither could see the other argument passed.
$callers = array(
	'cards' => (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php' ),
	'queue' => (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' ),
);
// The cards call it once, from the card on the shared parts (Task 11; today's card left in Task 18b), to decide and
// bound for the card.
ck( 'the Administrator Dashboard passes true and its way back, once, and the Institutions screen passes false, once', array(
	substr_count( $callers['cards'], 'WPCPM_Institution_Panel::render_review(' ),
	substr_count( $callers['cards'], "WPCPM_Institution_Panel::render_review( (int) \$row['id'], true, WPCPM_Return::DASHBOARD );" ),
	substr_count( $callers['queue'], 'WPCPM_Institution_Panel::render_review(' ),
	substr_count( $callers['queue'], "WPCPM_Institution_Panel::render_review( (int) \$row['id'], false );" ),
), array( 1, 1, 1, 1 ) );

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
// `review_facts()` returns an empty array for a post it does not recognize, and a heading
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
ck( 'saying plainly that the current one stays in force', false !== strpos( $accepted_card, 'stays in force until an Administrator accepts the new one' ), true );
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
	// A line printed by the shared printer names its paragraph's class as the third argument.
	preg_match_all( "/render_dashboard_link\(\s*'[^']*',\s*[^,]+,\s*'([^']+)'\s*\)/", $src, $printed );

	foreach ( array_merge( $attrs[1], $forms[1], $printed[1] ) as $list ) {
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
// The parts' own classes are the parts' sheet's, which every dashboard loads first (dashboard.css depends on it): the
// review block on the Administrator Dashboard's parts prints its checklist with one (Task 17b fix round).
$parts_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/parts.css' );
$admin_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' );

$undressed = array();
$review    = 0;

foreach ( classes_in( $panel_src ) as $name ) {
	$in_admin = 0 === strpos( $name, 'wpcpm-review' );
	$review  += $in_admin ? 1 : 0;

	if ( ! dressed( 0 === strpos( $name, 'wpcpm-ui-' ) ? $parts_css : ( $in_admin ? $admin_css : $front_css ), $name ) ) {
		$undressed[] = $name;
	}
}

sort( $undressed );

// Eight since the deciding block's old forms left (Task 18b): their two form classes and the note's paragraph went.
ck( 'the review block is eight classes, and the admin stylesheet dresses all of them', array( $review, in_array( 'wpcpm-review', $undressed, true ), in_array( 'wpcpm-review__open', $undressed, true ) ), array( 8, false, false ) );

// `.wpcpm-link-button` is the mentor dashboard's own button class - the group-sessions cards
// print it too - so its rule belongs in dashboard.css and not in either file this page owns.
// Named here rather than skipped quietly, so that the day dashboard.css grows it, this line
// fails and the exception goes with it.
ck( 'every class either half prints is dressed (the old card\'s link button, the one another stylesheet owed, went with it in Task 23)', $undressed, array() );

// And the other way round: three classes no PHP of the plugin's or the theme's prints, a promise
// of a later card and a muted line nothing drew, are out of the stylesheet's selector list.
ck( 'and institution.css dresses none of the three classes nothing prints', array(
	dressed( $front_css, 'wpcpm-agreement-panel__soon' ),
	dressed( $front_css, 'wpcpm-agreement-card__soon' ),
	dressed( $front_css, 'wpcpm-people__muted' ),
), array( false, false, false ) );

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

/* ---- the stylesheets parse the way their comments read ------------------ */

echo "\n=== Every disclosure title on the institution page is a heading ===\n";

// A screen reader stepping through headings found the page title and the report legends and
// nothing else: the six folded sections wrapped their titles in a span inside the summary,
// while the mentor page prints the same component's title as an h3. Read off the source, file
// by file, so a section added later either uses the heading or fails here. The Institution
// Dashboard's own groups and folds are the parts' since Task 23 (`WPCPM_UI::fold_head()`, an h3);
// the count is what the other surfaces still draw: the mentor page's one (the semester report's went with its old
// branch, Task 23, fix round 1).
$title_files = array(
	'includes/modules/class-wpcpm-institution-panel.php',
	'includes/modules/class-wpcpm-institution-people.php',
	'includes/modules/class-wpcpm-institution-roster-view.php',
	'includes/modules/class-wpcpm-institution-import-form.php',
	'includes/modules/class-wpcpm-semester-report-screen.php',
	'includes/modules/class-wpcpm-mentors-dashboard.php',
);
$span_titles = array();
$h3_titles   = 0;
foreach ( $title_files as $file ) {
	$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . $file );
	if ( false !== strpos( $src, '<summary class="wpcpm-group__summary"><span class="wpcpm-group__title">' ) ) {
		$span_titles[] = basename( $file );
	}
	$h3_titles += substr_count( $src, '<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">' );
}
ck( 'no summary wraps its title in a span', $span_titles, array() );
ck( 'and the one disclosure prints an h3, which heading navigation reaches', $h3_titles, 1 );

echo "\n=== The stylesheets parse the way their comments read ===\n";

// A comment is whitespace to the parser, and whitespace between two selectors is a descendant
// combinator. So a comment written between two selectors that were meant to open two rules
// welds them into one rule carrying the second's declarations, and the first is gone without
// a trace: the file still reads as two rules. institution.css shipped exactly that for the
// file input, which left four of the five upload forms undressed. Every plugin stylesheet is
// walked here, because the slip is one anybody tidying a comment can make.

/**
 * Selector lists with a comment between two selectors, as `file:line` strings.
 *
 * A comment before a selector list, or after a comma inside one, is ordinary in these files;
 * the one that is not is a comment between two selectors with no comma between them, which
 * the parser reads as a descendant combinator. Comments are blanked line for line first, so
 * a brace inside one is not a brace and the line numbers still point into the file.
 *
 * @param string $css  The stylesheet.
 * @param string $name Its basename, for the report.
 * @return string[]
 */
function welded_in( $css, $name ) {
	$marked = preg_replace_callback(
		'#/\*.*?\*/#s',
		function ( $m ) {
			return "\x01" . str_repeat( "\n", substr_count( $m[0], "\n" ) );
		},
		$css
	);

	preg_match_all( '/([^{}]*)\{/', $marked, $preludes, PREG_OFFSET_CAPTURE );

	$welded = array();

	foreach ( $preludes[1] as $prelude ) {
		$text = preg_replace( '/^[\s\x01]+/', '', $prelude[0] );

		if ( '' === $text || '@' === $text[0] ) {
			continue;
		}

		if ( preg_match( '/[^,\s\x01]\s*\x01/', $text ) ) {
			$welded[] = $name . ':' . ( substr_count( $marked, "\n", 0, $prelude[1] ) + 1 );
		}
	}

	return $welded;
}

/**
 * A stylesheet's rules, comment-stripped, as selector => declarations.
 *
 * One entry per selector: a list opening one rule is split on its commas, so a rule can be
 * asked about by any name it carries, and a selector opening two rules gets both bodies.
 * Whitespace is collapsed on both sides, so `display: block` is how a declaration reads.
 *
 * @param string $css The stylesheet.
 * @return string[]
 */
function rules_in( $css ) {
	$bare = preg_replace( '#/\*.*?\*/#s', '', $css );

	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $bare, $found, PREG_SET_ORDER );

	$rules = array();

	foreach ( $found as $rule ) {
		$body = trim( preg_replace( '/\s+/', ' ', $rule[2] ) );

		foreach ( explode( ',', $rule[1] ) as $selector ) {
			$selector = trim( preg_replace( '/\s+/', ' ', $selector ) );

			if ( '' !== $selector && '@' !== $selector[0] ) {
				$rules[ $selector ] = isset( $rules[ $selector ] ) ? $rules[ $selector ] . ' ' . $body : $body;
			}
		}
	}

	return $rules;
}

$welded = array();

foreach ( glob( WPCPM_PLUGIN_DIR . 'assets/css/*.css' ) as $sheet ) {
	$welded = array_merge( $welded, welded_in( (string) file_get_contents( $sheet ), basename( $sheet ) ) );
}

ck( 'no plugin stylesheet has a comment between two selectors', $welded, array() );

// A box that scrolls holds what is drawn inside it only if it is positioned. An absolutely
// positioned descendant, such as the `.screen-reader-text` span a button's longer name is hidden
// in, takes its place from the nearest positioned ancestor, so in a scroll box that is not
// positioned it is laid out past the box's edge without being clipped by it, and it widens the
// whole page: on a phone the page would scroll sideways and zoom out, and a tap could land on the
// wrong control. A box counts as scrolling when any value of its `overflow`, `overflow-x` or
// `overflow-y` is `auto`, `scroll` or `overlay`, in any case.
$scroll_boxes = array();
$unheld       = array();

foreach ( glob( WPCPM_PLUGIN_DIR . 'assets/css/*.css' ) as $sheet ) {
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $sheet ) ), $found, PREG_SET_ORDER );

	foreach ( $found as $rule ) {
		if ( ! preg_match( '/(^|;)\s*overflow(-x|-y)?\s*:[^;]*\b(auto|scroll|overlay)\b/i', $rule[2] ) ) {
			continue;
		}

		$box            = basename( $sheet ) . ': ' . trim( preg_replace( '/\s+/', ' ', $rule[1] ) );
		$scroll_boxes[] = $box;

		if ( ! preg_match( '/(^|;)\s*position\s*:\s*(relative|absolute|fixed|sticky)\b/i', $rule[2] ) ) {
			$unheld[] = $box;
		}
	}
}

// The Administrator Dashboard's box left its sheet with its old tables (Task 18c); the Usage card's box stands for it.
ck( 'the check finds the scroll boxes the stylesheets draw: the Usage card\'s box on a phone, the Student Duplicate Finder\'s tables, the Need help? panel\'s answers and the Viewing as list', array( in_array( 'sponsor.css: .wpcpm-usage__scroll', $scroll_boxes, true ), in_array( 'duplicate-finder.css: .wpcpm-duplicates__scroll', $scroll_boxes, true ), in_array( 'handbook.css: .wpcpm-hb-panel__body', $scroll_boxes, true ), in_array( 'dashboard.css: .wpcpm-dashboard__switcher-list', $scroll_boxes, true ) ), array( true, true, true, true ) );
ck( 'and each of them is positioned, so what is drawn inside it stays inside it', $unheld, array() );

// A box that scrolls sideways around a table is out of a keyboard's reach in Safari when it holds
// no link or button, unless it takes focus, and a screen reader does not announce it unless it is
// named. So wherever the plugin prints the class of a box a stylesheet lets scroll sideways, it
// prints it as a tab stop with a role and a name, as the Administrator Dashboard's tables were made
// in 1.122.35, the Student Duplicate Finder's and the Emails tool's in 1.122.36 and, on the sponsor
// tiers line, the Sponsor Dashboard's Usage table in 1.123.17. The role is a region, or what the
// Student Duplicate Finder's helper fills in, a group for a student on its list and a region
// elsewhere, which bin/test-duplicate-finder.php reads off the screens.
// The sideways boxes are pinned by name, so a new one is decided here rather than shipped unseen;
// the two boxes that scroll up and down are a combobox's list, which its field drives with the
// arrow keys, and the Need help? panel's answers.
$sideways = array();

foreach ( glob( WPCPM_PLUGIN_DIR . 'assets/css/*.css' ) as $sheet ) {
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $sheet ) ), $found, PREG_SET_ORDER );

	foreach ( $found as $rule ) {
		if ( preg_match( '/(^|;)\s*overflow(-x)?\s*:[^;]*\b(auto|scroll|overlay)\b/i', $rule[2] ) ) {
			$sideways[] = trim( preg_replace( '/\s+/', ' ', $rule[1] ) );
		}
	}
}

$printed = array();
$unnamed = array();

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) ) as $source_file ) {
	if ( 'php' !== $source_file->getExtension() ) {
		continue;
	}

	$source = (string) file_get_contents( $source_file->getPathname() );

	foreach ( $sideways as $box ) {
		if ( 1 !== preg_match( '/^\.([a-z0-9_-]+)$/', $box, $class ) ) {
			continue;
		}

		$at = preg_match_all( '/class="' . preg_quote( $class[1], '/' ) . '"([^>]*)>/', $source, $tags );

		if ( $at ) {
			$printed[ basename( $source_file->getPathname() ) . ': ' . $box ] = $at;
		}

		foreach ( $tags[1] as $rest ) {
			if ( 1 !== preg_match( '/^ tabindex="0" role="(region|%1\$s)" aria-labelledby="/', $rest ) ) {
				$unnamed[] = basename( $source_file->getPathname() ) . ': ' . $box . $rest;
			}
		}
	}
}

ksort( $printed );
// The Settings screen's Security tab joined them in 1.123.30: the Latest calls table, in the box that
// admin.css lets scroll (the stylesheets are read in file order, and admin.css comes first). The cohort
// timeline's chart and its month table joined them in 1.123.68: twelve months do not fit a phone.
// The Administrator Dashboard's box left administrator.css in Task 18c, nothing printing it since Task 18b.
ck( 'the boxes the stylesheets let scroll sideways are the six that hold the wide tables and the wide chart: the Settings screen\'s Latest calls, the cohort timeline\'s chart and month table, the Student Duplicate Finder\'s, the Emails tool\'s and the Sponsor Dashboard\'s Usage card\'s', $sideways, array( '.wpcpm-settings__scroll', '.wpcpm-timeline__scroll', '.wpcpm-timeline-table', '.wpcpm-duplicates__scroll', '.wpcpm-emails__scroll', '.wpcpm-usage__scroll' ) );
// The Administrator Dashboard's box and the cohort timeline's month table box left with their old branches (Task 18b):
// their tables are the table part's now. Their rules stay in the sheets until the stylesheet's own clean-up.
ck( 'and each is printed in one place, as a tab stop with a role and a name', array( $printed, $unnamed ), array( array( 'class-wpcpm-cohort-timeline-view.php: .wpcpm-timeline__scroll' => 1, 'class-wpcpm-duplicate-finder-screen.php: .wpcpm-duplicates__scroll' => 1, 'class-wpcpm-emails.php: .wpcpm-emails__scroll' => 1, 'class-wpcpm-settings-screen.php: .wpcpm-settings__scroll' => 1, 'class-wpcpm-sponsor-usage.php: .wpcpm-usage__scroll' => 1 ), array() ) );

// The two rules the weld hid, each on its own. The file input's room is not scoped to the
// steps list, because the same form is drawn on five surfaces and only one of them is a step;
// the tighter margin is, because the step is what it is about - and it is the step's alone,
// so the plain form keeps the 1em the comment above it promises rather than a second rule
// quietly taking it back to 0.5em for every form on the page.
$sheets = array(
	'institution.css'  => (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' ),
	'report-print.css' => (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/report-print.css' ),
);

$inst_rules = rules_in( $sheets['institution.css'] );

ck( 'the file input is dressed wherever the upload form is drawn', array(
	isset( $inst_rules['.wpcpm-agreement-panel__field input[type="file"]'] ),
	false !== strpos( $inst_rules['.wpcpm-agreement-panel__field input[type="file"]'] ?? '', 'display: block' ),
), array( true, true ) );
ck( 'and the plain form keeps the room its comment promises', array(
	false !== strpos( $inst_rules['.wpcpm-agreement-panel__form'] ?? '', 'margin: 1em 0 0' ),
	false === strpos( $inst_rules['.wpcpm-agreement-panel__form'] ?? '', 'margin-top: 0.5em' ),
), array( true, true ) );

// Leading-edge markers are logical in both of these files: the banner and the flash say
// `border-inline-start-width`, so a school reading in an RTL locale gets the bar on the side
// the text starts from. One strip said `border-left-width` and put its bar on the far side,
// and five quote-like rules paired a logical border with a four-value `padding` shorthand,
// which put the quoted words flush against their bar. A physical side is refused outright:
// the convention here is logical, so one physical property is a slip and not a choice.
$physical  = array();
$shorthand = array();
$unpadded  = array();
$quoted    = array(
	// The old panel's note went with its markup (Task 23); the agreement's note is the parts' `WPCPM_UI::note()`.
	'institution.css'  => array(),
	// The semester report card's stash and quote rules went with its old branch (Task 23, fix round 1); the document's two stay.
	'report-print.css' => array( '.wpcpm-report-doc__unreadable', '.wpcpm-report-doc__quote' ),
);

foreach ( $sheets as $name => $css ) {
	preg_match_all( '/\b(?:border|padding|margin)-(?:left|right)\b[^;]*/', $declarations( $css ), $found );

	foreach ( $found[0] as $hit ) {
		$physical[] = $name . ': ' . $hit;
	}

	$rules = rules_in( $css );

	// A four-value shorthand whose second and fourth values differ: the bar is on the inline
	// start, so unequal left and right padding is the padding sitting on the wrong side of it
	// in one of the two directions.
	foreach ( $rules as $selector => $body ) {
		if ( false !== strpos( $body, 'border-inline-start' ) && preg_match( '/(?<![\w-])padding: ([^;\s]+) ([^;\s]+) ([^;\s]+) ([^;\s]+);/', $body, $sides ) && $sides[2] !== $sides[4] ) {
			$shorthand[] = $name . ' ' . $selector;
		}
	}

	foreach ( $quoted[ $name ] as $selector ) {
		if ( false === strpos( $rules[ $selector ] ?? '', 'padding-inline: 1em 0' ) ) {
			$unpadded[] = $name . ' ' . $selector;
		}
	}
}

ck( 'neither stylesheet draws on a physical side', $physical, array() );
ck( 'no rule with a leading-edge bar pads its two sides unequally', $shorthand, array() );
ck( 'and the two quote-like rules pad on the inline axis', $unpadded, array() );


// Free text on the printed report wraps anywhere, as the link already did: the narrative the
// school typed and a student's quoted words can carry a pasted address, and the column is
// 174mm of paper with no scrollbar. The report is the institution dashboard's own document,
// and this is the suite that reads its stylesheets, so its sheet is read here too.
$print_rules = rules_in( $sheets['report-print.css'] );
$clipped     = array();

foreach ( array( '.wpcpm-report-doc__p', '.wpcpm-report-doc__quote-text', '.wpcpm-report-doc__translation', '.wpcpm-report-doc__student-name', '.wpcpm-report-doc__link' ) as $selector ) {
	if ( false === strpos( $print_rules[ $selector ] ?? '', 'overflow-wrap: anywhere' ) ) {
		$clipped[] = $selector;
	}
}

ck( 'every free-text block on the printed report wraps an unbroken run', $clipped, array() );

/* ---- the submit guard is an attribute, and the guard is a script ---------- */

echo "\n=== The once attribute is printed, and the docblock says what reads it ===\n";

// `data-wpcpm-once` is an attribute, and what reads it is assets/js/forms.js, registered as
// `wpcpm-forms`: the Institution Dashboard enqueues it, and in wp-admin the admin class loads
// it on every plugin screen, the Institutions screen among them. That is a fact about the site
// rather than about this file, and the only thing this file can do about it is hold the
// docblock to it - a reader who believes the guard is running does not go looking for the
// enqueue that is missing. The docblock is read as prose, its lines joined, so a sentence
// that wraps differently still reads the same.
$form_doc  = substr( $panel_src, 0, (int) strpos( $panel_src, 'private static function form_start(' ) );
$form_doc  = substr( $form_doc, (int) strrpos( $form_doc, '/**' ) );
$form_text = (string) preg_replace( '/\s*\n\s*\*\s*/', ' ', $form_doc );

ck( 'the docblock names the script the attribute needs', false !== strpos( $form_text, 'wpcpm-forms' ), true );
ck( 'and says the dashboard enqueues it', false !== strpos( $form_text, 'dashboard enqueues' ), true );
ck( 'and that in wp-admin the admin class loads it on every plugin screen, the Institutions screen among them',
	array( false !== strpos( $form_text, 'in wp-admin the admin class loads it on every plugin screen' ), false !== strpos( $form_text, 'the Institutions screen among them' ) ),
	array( true, true ) );
ck( 'and no longer says the Institutions screen goes without it', false === strpos( $form_text, 'still does not' ), true );
ck( 'and the attribute is still the one that script reads', false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/forms.js' ), "getAttribute( 'data-wpcpm-once' )" ), true );

// The tripwire on the sentences above: the Institution Dashboard enqueues the guard, the admin
// class enqueues it for every plugin screen, and the Institutions screen leaves it to the admin
// class rather than enqueueing it a second time. This counts the call as it is written in each
// file, comments included, so an enqueue added or dropped on any side fails here, one line away
// from the docblock that names the screens; that the admin class's call is live code, not a
// comment, is pinned where its method is read without its comments, in test-submit-guard.php.
$enqueues = array();

foreach ( array( 'includes/class-wpcpm-admin.php', 'includes/modules/class-wpcpm-institutions.php', 'includes/modules/class-wpcpm-institutions-dashboard.php' ) as $file ) {
	$enqueues[ $file ] = substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ), "wp_enqueue_script( 'wpcpm-forms' )" );
}

ck( 'the dashboard enqueues it and so does the admin class, once each, which is what the docblock says; the Institutions screen has no enqueue of its own',
	$enqueues,
	array(
		'includes/class-wpcpm-admin.php'                          => 1,
		'includes/modules/class-wpcpm-institutions.php'           => 0,
		'includes/modules/class-wpcpm-institutions-dashboard.php' => 1,
	) );

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

// The two outcomes that send a manager to the Institutions screen name what it has: the
// institutions sync, run from its Sync and storage tab. It has no Refresh control, and never had.
$panel_messages = WPCPM_Institution_Panel::messages();
ck( 'a record the site could not write after Airtable took it sends the manager to the institutions sync, on the tab that runs it', $panel_messages['agreement-not-saved'][1], 'Airtable was updated but the record on this site could not be written. Run the institutions sync on the Institutions screen\'s Sync and storage tab: the next reconcile completes it.' );
ck( 'and a state that was being rebuilt says the account opens after the next institutions sync', $panel_messages['agreement-later'][1], 'Airtable and the site record were both written, but this institution\'s state was being rebuilt at that moment. The account opens after the next institutions sync.' );
// Task 25, fix round 1, B1: the sentence says what a school with no settled agreement shows its people now.
ck( 'the revocation\'s outcome says what the institution\'s people see from their next page load, the representatives the Overview and the Agreement and the tutors no page, and that everybody there has been emailed the note', $panel_messages['agreement-revoked'], array( 'success', 'The agreement is revoked. From their next page load the institution\'s representatives see only the Overview and the Agreement, its tutors see no page, and everybody there has been emailed your note.' ) );
ck( 'and no outcome calls that card a panel', array_values( array_filter( array_keys( $panel_messages ), function ( $slug ) use ( $panel_messages ) { return false !== stripos( (string) $panel_messages[ $slug ][1], 'agreement panel' ); } ) ), array() );
ck( 'and no outcome names a Refresh control', array_values( array_filter( array_keys( $panel_messages ), function ( $slug ) use ( $panel_messages ) { return false !== stripos( (string) $panel_messages[ $slug ][1], 'Refresh' ); } ) ), array() );

echo "\n=== The location note is kept as it was typed ===\n";

// Post meta unslashes what it is handed, as core's does, and the form's note is read unslashed:
// it keeps a backslash only when it is written as a slashed copy.
reset_world();
seed_index( $rec_a, $country );
$GLOBALS['uid']     = 9;
$GLOBALS['referer'] = 'https://example.test/institution-dashboard/';
$where_typed        = 'Shelf C:\drafts\2025, two \\\\ in a row, the "blue" folder';

ck( 'the recording goes through', on_file( $rec_a, $drive, '', $where_typed ), 'redirect: https://example.test/institution-dashboard/' );
ck( 'and the note is kept exactly as typed', get_post_meta( array_key_first( $GLOBALS['posts'] ), WPCPM_Institution_Agreement::META_NOTE, true ), $where_typed );

echo "\n=== A Drive link keeps every percent octet ===\n";

// A link is a code, not prose: the one-line cleaner removes every "%" followed by two hexadecimal
// digits, which takes "%20" out of a query and "%C3%B3" out of a name in another alphabet. The
// route reads the link as typed, so the base, the post and the log hold the link as it was given.
$octet_links = array(
	'%20'    => 'https://drive.google.com/drive/folders/1AbC?usp=sharing&q=Collaboration%20Agreement%202026',
	'%C3%B3' => 'https://drive.google.com/drive/folders/1AbC?q=Colaboraci%C3%B3n',
);

foreach ( $octet_links as $octet => $link ) {
	reset_world();
	seed_index( $rec_a, $country );
	$GLOBALS['uid']     = 9;
	$GLOBALS['referer'] = 'https://example.test/institution-dashboard/';

	$recorded = on_file( $rec_a, $link );
	$post_id  = array_key_first( $GLOBALS['posts'] );
	ck( 'a Drive link with ' . $octet . ' recorded as on file is written with it, in the base, on the post and in the log',
		array(
			$recorded,
			flashed(),
			$GLOBALS['patched'][0][1][0]['fields']['Agreement Document'] ?? null,
			null === $post_id ? null : get_post_meta( $post_id, WPCPM_Institution_Agreement::META_DRIVE_URL, true ),
			$GLOBALS['audit'][0]['data']['drive'] ?? null,
		),
		array( 'redirect: https://example.test/institution-dashboard/', 'agreement-on-file', $link, $link, $link ) );
}

// The link is stored through core's esc_url_raw(), stood in for here as 7.1.2 writes it
// (bin/stubs/link-cleaners.php): it keeps every octet above, and takes out a "%0A" or a "%0D", a
// line break written as a code, wherever it stands. A link that is nothing but one becomes
// "http://", which is no Drive link.
ck( 'the stand-in is core\'s esc_url_raw(): "%20" and "%C3%B3" kept, "%0A" taken out, and a link of nothing else made "http://"',
	array( esc_url_raw( $octet_links['%20'] ), esc_url_raw( $octet_links['%C3%B3'] ), esc_url_raw( 'https://drive.google.com/drive/folders/1AbC?q=Line%0Atwo' ), esc_url_raw( '%0A' ) ),
	array( $octet_links['%20'], $octet_links['%C3%B3'], 'https://drive.google.com/drive/folders/1AbC?q=Linetwo', 'http://' ) );

reset_world();
seed_index( $rec_a, $country );
$GLOBALS['uid']     = 9;
$GLOBALS['referer'] = 'https://example.test/institution-dashboard/';
$recorded           = on_file( $rec_a, 'https://drive.google.com/drive/folders/1AbC?q=Line%0Atwo' );
$post_id            = array_key_first( $GLOBALS['posts'] );
ck( 'so a Drive link with "%0A" in it is recorded without it, in the base and on the post',
	array(
		$recorded,
		flashed(),
		$GLOBALS['patched'][0][1][0]['fields']['Agreement Document'] ?? null,
		null === $post_id ? null : get_post_meta( $post_id, WPCPM_Institution_Agreement::META_DRIVE_URL, true ),
	),
	array( 'redirect: https://example.test/institution-dashboard/', 'agreement-on-file', 'https://drive.google.com/drive/folders/1AbC?q=Linetwo', 'https://drive.google.com/drive/folders/1AbC?q=Linetwo' ) );

// The link is read through core's wp_check_invalid_utf8() too, as 7.1.2 writes it: a text that is
// not valid UTF-8, which only a crafted post sends, is read as nothing, and nothing is no link.
reset_world();
seed_index( $rec_a, $country );
$GLOBALS['uid']     = 9;
$GLOBALS['referer'] = 'https://example.test/institution-dashboard/';
ck( 'the stand-in is core\'s wp_check_invalid_utf8(): a valid text kept, one that is not emptied', array( wp_check_invalid_utf8( "Colaboraci\u{F3}n" ), wp_check_invalid_utf8( "Colaboraci\xC3n" ) ), array( "Colaboraci\u{F3}n", '' ) );
ck( 'so a link posted with a byte that is not UTF-8 is refused by name, and nothing is patched', array( on_file( $rec_a, "https://drive.google.com/drive/folders/1AbC?q=Colaboraci\xC3n" ), flashed(), count( $GLOBALS['patched'] ) ), array( 'redirect: https://example.test/institution-dashboard/', 'agreement-link', 0 ) );

/* ---- the review block on the shared parts, behind the switch ---------------- */

echo "\n=== The review block on the Administrator Dashboard's parts: Accept it or Return with a note (UI shared parts, Task 11) ===\n";

// The Administrator Dashboard suite draws this block through a stand-in, so the real block is drawn here, under
// `WPCPM_UI::enter( 'administrator' )` with the dashboard's surface on, as the dashboard draws it: the review's
// facts as today, then one Decide area. Off the surface, and on the wp-admin Institutions screen, whose tab is
// off for this whole release, the block is today's, byte for byte.

/**
 * Every form a render holds, by the admin-post action it posts: how it posts, its guard, its hidden fields as
 * `name=value` in the order a browser posts them, its boxes with their bounds, its submits and the question it
 * asks (on the form, or on its submit).
 *
 * @param string $html Markup.
 * @return array<string, array[]>
 */
function review_posted( $html ) {
	$x   = review_doc( $html );
	$out = array();
	foreach ( $x->query( '//form' ) as $form ) {
		$hidden = array();
		$action = '';
		foreach ( $x->query( './/input[@type="hidden"]', $form ) as $input ) {
			$hidden[] = $input->getAttribute( 'name' ) . '=' . $input->getAttribute( 'value' );
			if ( 'action' === $input->getAttribute( 'name' ) ) {
				$action = $input->getAttribute( 'value' );
			}
		}
		$boxes = array();
		foreach ( $x->query( './/textarea', $form ) as $box ) {
			$boxes[] = $box->getAttribute( 'name' ) . ' minlength=' . $box->getAttribute( 'minlength' ) . ' maxlength=' . $box->getAttribute( 'maxlength' ) . ( $box->hasAttribute( 'required' ) ? ' required' : '' );
		}
		$submits = array();
		$asks    = $form->getAttribute( 'data-wpcpm-confirm' );
		foreach ( $x->query( './/button[not(@type="button")]', $form ) as $button ) {
			$submits[] = $button->getAttribute( 'name' ) . '=' . $button->getAttribute( 'value' );
			$asks      = '' !== $button->getAttribute( 'data-wpcpm-confirm' ) ? $button->getAttribute( 'data-wpcpm-confirm' ) : $asks;
		}
		$out[ $action ][] = array(
			'posts'   => $form->getAttribute( 'method' ) . ' ' . $form->getAttribute( 'action' ) . ' ' . $form->getAttribute( 'enctype' ),
			'guard'   => ( $form->hasAttribute( 'data-wpcpm-once' ) ? 'once ' : '' ) . $form->getAttribute( 'data-wpcpm-busy' ),
			'hidden'  => $hidden,
			'boxes'   => $boxes,
			'submits' => $submits,
			'asks'    => $asks,
		);
	}
	ksort( $out );
	return $out;
}

/**
 * A render read as a document.
 *
 * @param string $html Markup.
 * @return DOMXPath
 */
function review_doc( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . (string) $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $doc );
}

/**
 * The kinds and words of the buttons a path holds, in order: `kind: words`.
 *
 * @param DOMXPath $x    The document.
 * @param string   $path Where to look.
 * @return string[]
 */
function review_buttons( DOMXPath $x, $path ) {
	$out = array();
	foreach ( $x->query( $path ) as $button ) {
		$class = ' ' . $button->getAttribute( 'class' ) . ' ';
		$kind  = false !== strpos( $class, ' wpcpm-btn--danger ' ) ? 'danger' : ( false !== strpos( $class, ' wpcpm-btn--secondary ' ) ? 'secondary' : ( false !== strpos( $class, ' wpcpm-btn--quiet ' ) ? 'quiet' : ( false !== strpos( $class, ' wpcpm-btn ' ) ? 'primary' : 'old' ) ) );
		$out[] = $kind . ': ' . trim( $button->textContent );
	}
	return $out;
}

/**
 * The review block for one document, with a surface named (or none) and the Administrator Dashboard's switch on.
 *
 * @param int    $post_id Agreement post ID.
 * @param string $surface The surface drawing: 'administrator', a wp-admin tab, or '' for none.
 * @param bool   $decide  True as the Administrator Dashboard draws it, with its way back; false as the
 *                        Institutions screen's queue draws it.
 * @return string
 */
function review_drawn( $post_id, $surface, $decide = true ) {
	$GLOBALS['caps']      = true;
	$GLOBALS['member_of'] = array();
	WPCPM_UI::set_live( array( 'administrator' ) );
	if ( '' !== $surface ) {
		WPCPM_UI::enter( $surface );
	}
	ob_start();
	try {
		if ( $decide ) {
			WPCPM_Institution_Panel::render_review( $post_id, true, WPCPM_Return::DASHBOARD );
		} else {
			WPCPM_Institution_Panel::render_review( $post_id, false );
		}
	} finally {
		$html = (string) ob_get_clean();
		if ( 'administrator' === $surface && $decide ) {
			parts_inventory_add( $html );
		}
		if ( '' !== $surface ) {
			WPCPM_UI::leave();
		}
		WPCPM_UI::set_live( null );
	}
	return $html;
}

reset_world();
$GLOBALS['next_id']   = 5101;
$GLOBALS['uid']       = 0;
$GLOBALS['enqueued']  = array();
seed_index( $rec_a, '' );
seed_index( $rec_b, '' );
$GLOBALS['members'][ $rec_a ] = array( 'Anna Kowalska', 'Bo Nowak', 'Cy Wisniewski' );
$GLOBALS['members'][ $rec_b ] = array( 'Dee Lopez' );
$agr_one = seed_post( $rec_a, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file_meta );
$agr_two = seed_post( $rec_b, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_OWN, $file_meta );

// Check 4: on the wp-admin Institutions screen, today's markup. The digest is of the block as it was drawn before
// Task 11 (the same fixture, the same ids), so a byte changed anywhere in the read block shows. The block drawn to
// decide is the parts' alone since its old branch left (Task 18b), and its off digests with it.
$read_admin   = review_drawn( $agr_one, 'wpcpm-institutions/queue', false );
$read_none    = review_drawn( $agr_one, '', false );
ck( 'on the Institutions screen\'s queue (its tab is off in this release) the block drawn to be read is today\'s, byte for byte: the same as with no surface named, and as before Task 11', array(
	$read_admin === $read_none,
	md5( $read_admin ),
	strpos( $read_admin, 'wpcpm-ui-' ),
), array( true, '5704ddbda9b61f7ffdf5dcda2657a203', false ) );

// Fix round 1, ruling 7: the parts' branch draws only to decide. The one caller that draws the block to be read is
// the Institutions screen's queue (`class-wpcpm-institutions.php`, `render_review( (int) $row['id'], false )`), whose
// tab is off for this whole release, so a read block is today's even on a surface that is on.
ck( 'drawn to be read on a surface that is on, the block is still today\'s: the parts draw only the block that decides', review_drawn( $agr_one, 'administrator', false ), $read_none );

$GLOBALS['faithful_nonces'] = true;
$on_one  = review_drawn( $agr_one, 'administrator' );
$x_one   = review_doc( $on_one );
$area    = '//div[@class="wpcpm-ui-decide"][@id="wpcpm-decide-agr-' . $agr_one . '"]';

// Fix round 1, ruling 3: the loss sentence names the box as its label reads, on the parts and off them.
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$loss_on = WPCPM_Institution_Panel::messages()['agreement-return-loss'][1];
WPCPM_UI::leave();
$loss_named = WPCPM_Institution_Panel::messages()['agreement-return-loss'][1];
WPCPM_UI::set_live( null );
ck( 'the loss sentence names the box "What has to change" where the parts draw it, and by today\'s words everywhere else', array(
	false !== strpos( $loss_on, 'what you typed in "What has to change". To keep every word' ),
	false !== strpos( $loss_named, 'what you typed in "What has to change, in your own words". To keep every word' ),
	trim( (string) $x_one->evaluate( 'string(//label[@for="wpcpm-return-' . $agr_one . '"]/text())' ) ),
), array( true, true, 'What has to change' ) );

// Check 1: posted fields. The old forms left with their branch (Task 18b), so what the two forms post is pinned to
// what the old forms posted, read at 76b5ecff, where this check compared the two: the address and encoding, the guard
// and busy words, the hidden fields in order with the nonce keyed to the document and its referer, the way back to
// the card, the box under its name and bounds, the question.
$agr_posted = static function ( $action, $busy, array $boxes, $asks ) use ( $agr_one ) {
	return array(
		array(
			'posts'   => 'post https://example.test/wp-admin/admin-post.php application/x-www-form-urlencoded',
			'guard'   => 'once ' . $busy,
			'hidden'  => array( '_wpnonce=nonce-' . $action . '_' . $agr_one, '_wp_http_referer=/administrator-dashboard/', 'action=' . $action, 'wpcpm_agreement_post=' . $agr_one, 'wpcpm_return=dashboard', 'wpcpm_return_to=agreements' ),
			'boxes'   => $boxes,
			'submits' => array( '=' ),
			'asks'    => $asks,
		),
	);
};
ck( 'on, Accept and Return post exactly what the handlers read: the same address and encoding, the same guard and busy words, the same hidden fields in order with the nonce keyed to the document and its referer, the same box and bounds, the same question (posted fields)', review_posted( $on_one ), array(
	WPCPM_Institution_Agreement::ACTION_ACCEPT => $agr_posted( 'wpcpm_agreement_accept', 'Accepting...', array(), 'Accept the signed agreement from ' . $rec_a . '? This opens their account on the site, sets Current Stage to Confirmed in Airtable, and emails the 3 people at the institution. You can revoke it later on the Institutions screen, in the Agreements tab.' ),
	WPCPM_Institution_Agreement::ACTION_RETURN => $agr_posted( 'wpcpm_agreement_return', 'Returning...', array( 'wpcpm_agreement_note minlength=20 maxlength=2000 required' ), '' ),
) );

// Check 5: the words, one primary, nothing red.
ck( 'on, the review keeps its anchor and its heading, today\'s words and name, as the parts\' h5 the Decide heading is too (fix round 1), then today\'s facts, checklist, scan line and download link, and then one Decide area, id wpcpm-decide-agr-{ID}, headed "Decide"', array(
	// Task 17b fix round, ruling 5: the section keeps its anchor and prints no class of the old block; its sentences are
	// the parts' intro lines, its checklist today's list with the parts' spacing class; since Task 18a the helper's,
	// each line with the line's own class.
	false !== strpos( $on_one, '<section class="wpcpm-ui-item__part" id="wpcpm-review-' . $agr_one . '"><h5 class="wpcpm-ui-h">Review the signed agreement from ' . $rec_a . '</h5><p class="wpcpm-ui-intro">' ),
	false !== strpos( $on_one, '<p class="wpcpm-ui-intro">Uploaded by Wera Rektor on September 1, 2025, 234 KB.</p><p class="wpcpm-ui-intro">The institution says this is the program&#039;s template, signed.</p><ul class="wpcpm-ui-checklist"><li class="wpcpm-ui-checklist__item">' )
		&& 1 === preg_match( '#</ul><p class="wpcpm-ui-intro">The scan noticed these in the file: [^<]*</p><p class="wpcpm-ui-intro">The scan is a courtesy and not evidence: [^<]*</p><p><a href="#', $on_one )
		&& 0 === preg_match( '#class="[^"]*wpcpm-review#', $on_one ),
	substr_count( $on_one, '<li class="wpcpm-ui-checklist__item">' ),
	false !== strpos( $on_one, 'The scan is a courtesy and not evidence' ),
	false !== strpos( $on_one, 'action=wpcpm_agreement_download&post=' . $agr_one ),
	$x_one->query( '//div[@class="wpcpm-ui-decide"]' )->length,
	$x_one->query( $area )->length,
	trim( (string) $x_one->evaluate( 'string(' . $area . '/h5[@class="wpcpm-ui-h"])' ) ),
	strrpos( $on_one, '</section>' ) > strpos( $on_one, 'id="wpcpm-decide-agr-' . $agr_one . '"' ),
), array( true, true, 3, true, true, 1, 1, 'Decide', true ) );
ck( 'its row: Accept it, the one primary, acting at once with its question; Return with a note, a secondary opener (design 5)', array(
	review_buttons( $x_one, $area . '/div[@class="wpcpm-ui-actions"]//button' ),
	$x_one->query( $area . '/div[@class="wpcpm-ui-actions"]/form/button[@type="submit"][@data-wpcpm-confirm]' )->length,
	$x_one->query( $area . '/div[@class="wpcpm-ui-actions"]/button[@type="button"][@aria-controls="wpcpm-decide-agr-' . $agr_one . '-return-panel"][@aria-expanded="false"]' )->length,
), array( array( 'primary: Accept it', 'secondary: Return with a note' ), 1, 1 ) );
ck( 'the panel: label "What has to change" with Required, the hint saying who receives the note, the button that keeps "Return it with this note", and Cancel', array(
	trim( (string) $x_one->evaluate( 'string(//div[@id="wpcpm-decide-agr-' . $agr_one . '-return-panel"]//label[@for="wpcpm-return-' . $agr_one . '"])' ) ),
	trim( (string) $x_one->evaluate( 'string(//label[@for="wpcpm-return-' . $agr_one . '"]/span[@class="wpcpm-ui-field__need"])' ) ),
	trim( (string) $x_one->evaluate( 'string(//p[@id="wpcpm-return-' . $agr_one . '-hint"])' ) ),
	review_buttons( $x_one, '//div[@id="wpcpm-decide-agr-' . $agr_one . '-return-panel"]//button' ),
), array(
	'What has to change Required',
	'Required',
	'In your own words. It is emailed to everybody at the institution exactly as you write it, with your address to reply to.',
	array( 'secondary: Return it with this note', 'quiet: Cancel' ),
) );
ck( 'one primary in the whole area, nothing red (returning removes nothing), and no button of the old forms', array(
	count( array_filter( review_buttons( $x_one, $area . '//button' ), static function ( $b ) { return 0 === strpos( $b, 'primary: ' ); } ) ),
	count( array_filter( review_buttons( $x_one, '//button' ), static function ( $b ) { return 0 === strpos( $b, 'danger: ' ) || 0 === strpos( $b, 'old: ' ); } ) ),
	strpos( $on_one, 'wpcpm-button' ),
), array( 1, 0, false ) );
ck( 'the box keeps its id and name, wpcpm-return-{ID} and wpcpm_agreement_note, its four rows and its bounds, tied to its hint, empty', array(
	array_map( static function ( $box ) { return $box->getAttribute( 'id' ) . ' ' . $box->getAttribute( 'name' ) . ' ' . $box->getAttribute( 'rows' ) . ' ' . $box->getAttribute( 'minlength' ) . ' ' . $box->getAttribute( 'maxlength' ) . ' ' . $box->getAttribute( 'aria-describedby' ) . ' ' . json_encode( $box->textContent ); }, iterator_to_array( $x_one->query( '//textarea' ) ) ),
), array( array( 'wpcpm-return-' . $agr_one . ' wpcpm_agreement_note 4 20 2000 wpcpm-return-' . $agr_one . '-hint "\n"' ) ) );
ck( 'the parts\' script is asked for, where the panel is drawn', in_array( WPCPM_UI::SCRIPT, $GLOBALS['enqueued'], true ), true );

// Check 2: two agreements on one card.
$two   = review_drawn( $agr_one, 'administrator' ) . review_drawn( $agr_two, 'administrator' );
$x_two = review_doc( $two );
$ids   = array_map( static function ( $node ) { return $node->getAttribute( 'id' ); }, iterator_to_array( $x_two->query( '//*[@id]' ) ) );
$loose = array();
foreach ( array( 'aria-controls', 'for' ) as $attr ) {
	foreach ( $x_two->query( '//*[@' . $attr . ']' ) as $node ) {
		if ( ! in_array( $node->getAttribute( $attr ), $ids, true ) ) {
			$loose[] = $attr . '=' . $node->getAttribute( $attr );
		}
	}
}
foreach ( $x_two->query( '//*[@aria-describedby]' ) as $node ) {
	foreach ( explode( ' ', $node->getAttribute( 'aria-describedby' ) ) as $described ) {
		if ( ! in_array( $described, $ids, true ) ) {
			$loose[] = 'aria-describedby=' . $described;
		}
	}
}
$return_posts = static function ( $id ) use ( $x_two ) {
	$form   = $x_two->query( '//div[@id="wpcpm-decide-agr-' . $id . '-return-panel"]/form' )->item( 0 );
	$fields = array();
	if ( $form ) {
		foreach ( $x_two->query( './/input[@type="hidden"]', $form ) as $input ) {
			if ( in_array( $input->getAttribute( 'name' ), array( '_wpnonce', 'wpcpm_agreement_post' ), true ) ) {
				$fields[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
			}
		}
	}
	return $fields;
};
ck( 'two agreements on one card: their areas, panels and boxes have ids of their own, none repeats, and every aria-controls, for and aria-describedby names an element on the page', array(
	count( $ids ) === count( array_unique( $ids ) ),
	$x_two->query( '//div[@class="wpcpm-ui-decide"]' )->length,
	$x_two->query( '//div[@data-wpcpm-panel]' )->length,
	$loose,
	array_map( static function ( $node ) { return $node->getAttribute( 'aria-controls' ); }, iterator_to_array( $x_two->query( '//button[@aria-controls]' ) ) ),
), array( true, 2, 2, array(), array( 'wpcpm-decide-agr-' . $agr_one . '-return-panel', 'wpcpm-decide-agr-' . $agr_two . '-return-panel' ) ) );
ck( 'and each Return panel posts its own agreement\'s ID, under a nonce keyed to it; with nothing refused or kept, no panel is open', array(
	$return_posts( $agr_one ),
	$return_posts( $agr_two ),
	$x_two->query( '//*[@data-wpcpm-open]' )->length,
	$x_two->query( '//button[@aria-expanded="true"]' )->length,
), array(
	array( '_wpnonce' => 'nonce-wpcpm_agreement_return_' . $agr_one, 'wpcpm_agreement_post' => (string) $agr_one ),
	array( '_wpnonce' => 'nonce-wpcpm_agreement_return_' . $agr_two, 'wpcpm_agreement_post' => (string) $agr_two ),
	0,
	0,
) );

// Check 3: a refused note comes back. The press is the real handler's, from one person; the page after it is
// drawn for the same person, a fresh reader of the flash (`WPCPM_Flash::take()` memoizes per request).
/**
 * Post a return of one document with one note, as the Administrator `$from`, and hand what it left to `$to`.
 *
 * @param int    $post_id The document.
 * @param string $note    The note as typed.
 * @param int    $from    Who pressed.
 * @param int    $to      The same person on the next page.
 * @return string Where the handler sent the press.
 */
function return_refused( $post_id, $note, $from, $to ) {
	$GLOBALS['caps']      = true;
	$GLOBALS['member_of'] = array();
	$GLOBALS['uid']       = $from;
	$GLOBALS['referer']   = 'https://example.test/administrator-dashboard/';
	$_POST                = array(
		'wpcpm_agreement_post' => (string) $post_id,
		'wpcpm_agreement_note' => $note,
		'wpcpm_return'         => 'dashboard',
		'wpcpm_return_to'      => 'agreements',
	);
	$went = '';
	try {
		WPCPM_Institution_Agreement::handle_return();
	} catch ( Exception $e ) {
		$went = $e->getMessage();
	}
	$_POST = array();
	$GLOBALS['umeta'][ $to ][ WPCPM_Flash::META ] = $GLOBALS['umeta'][ $from ][ WPCPM_Flash::META ] ?? array();
	unset( $GLOBALS['umeta'][ $from ] );
	$GLOBALS['uid'] = $to;
	return $went;
}

$short_went = return_refused( $agr_two, "Page 4.\nSign it.", 31, 32 );
$short_left = $GLOBALS['umeta'][32][ WPCPM_Flash::META ] ?? array();
$back       = review_drawn( $agr_one, 'administrator' ) . review_drawn( $agr_two, 'administrator' );
$x_back     = review_doc( $back );
$box        = $x_back->query( '//textarea[@id="wpcpm-return-' . $agr_two . '"]' )->item( 0 );
$problem    = WPCPM_Form_Errors::problem_id( 'wpcpm-return-' . $agr_two );
ck( 'a note too short is refused by the handler, which keeps the typing for that document alone', array(
	0 === strpos( $short_went, 'redirect: ' ),
	$short_left[ WPCPM_Institutions::FLASH ] ?? '',
	isset( $short_left[ WPCPM_Typed_Text::KEPT_CHANNEL . WPCPM_Typed_Text::box_form( WPCPM_Institution_Agreement::TYPED_RETURN, $agr_two ) ] ),
), array( true, 'agreement-note', true ) );
$GLOBALS['uid'] = 32;

ck( 'back on the card, that agreement\'s Return panel is printed open and its opener reads expanded; the other agreement\'s is not (Review Focus 1)', array(
	array_map( static function ( $node ) { return $node->getAttribute( 'id' ); }, iterator_to_array( $x_back->query( '//*[@data-wpcpm-open]' ) ) ),
	array_map( static function ( $node ) { return $node->getAttribute( 'aria-controls' ); }, iterator_to_array( $x_back->query( '//button[@aria-expanded="true"]' ) ) ),
), array( array( 'wpcpm-decide-agr-' . $agr_two . '-return-panel' ), array( 'wpcpm-decide-agr-' . $agr_two . '-return-panel' ) ) );
ck( 'its box holds the typing, is marked invalid and is tied to its refusal sentence, the handler\'s, before its hint', array(
	$box ? $box->textContent : null,
	$box ? $box->getAttribute( 'aria-invalid' ) : null,
	$box ? $box->getAttribute( 'aria-describedby' ) : null,
	trim( (string) $x_back->evaluate( 'string(//*[@id="' . $problem . '"])' ) ),
	$x_back->query( '//div[@id="wpcpm-decide-agr-' . $agr_two . '-return-panel"]//*[@id="' . $problem . '"]' )->length,
), array( "\nPage 4.\nSign it.", 'true', $problem . ' wpcpm-return-' . $agr_two . '-hint', WPCPM_Institution_Panel::messages()['agreement-note'][1], 1 ) );
ck( 'and the other agreement\'s box is neither refused nor filled', array(
	$x_back->query( '//div[@id="wpcpm-decide-agr-' . $agr_one . '"]//*[@aria-invalid]' )->length,
	array_map( static function ( $b ) { return $b->textContent; }, iterator_to_array( $x_back->query( '//div[@id="wpcpm-decide-agr-' . $agr_one . '"]//textarea' ) ) ),
), array( 0, array( "\n" ) ) );

$lossy      = "Clause 4 says kids <12 free, adults >18 pay.\nPlease change it.";
$lossy_went = return_refused( $agr_one, $lossy, 33, 34 );
$x_lossy    = review_doc( review_drawn( $agr_one, 'administrator' ) );
ck( 'a note the cleaner would take words from comes back the same way: open, the typing kept as typed, tied to the sentence that names the box by its label on the parts (fix round 1)', array(
	0 === strpos( $lossy_went, 'redirect: ' ),
	$x_lossy->query( '//div[@id="wpcpm-decide-agr-' . $agr_one . '-return-panel"][@data-wpcpm-open]' )->length,
	$x_lossy->evaluate( 'string(//textarea[@id="wpcpm-return-' . $agr_one . '"])' ),
	trim( (string) $x_lossy->evaluate( 'string(//*[@id="' . WPCPM_Form_Errors::problem_id( 'wpcpm-return-' . $agr_one ) . '"])' ) ),
), array( true, 1, "\n" . $lossy, $loss_on ) );

// A note of nothing but white space is refused as too short, and keeps nothing (`WPCPM_Typed_Text::keep_box()`):
// the outcome names no document, so no box is said to be the refused one and no panel is opened; the card says
// the refusal at its top, as today.
return_refused( $agr_one, '   ', 35, 36 );
$blank_left = $GLOBALS['umeta'][36][ WPCPM_Flash::META ] ?? array();
$x_blank    = review_doc( review_drawn( $agr_one, 'administrator' ) . review_drawn( $agr_two, 'administrator' ) );
ck( 'a blank note is refused as too short and keeps nothing (the outcome and the card to open, no typing), so no panel is opened and no box is said to be refused: nothing tells which document it was for', array(
	$blank_left[ WPCPM_Institutions::FLASH ] ?? '',
	array_keys( $blank_left ),
	$x_blank->query( '//*[@data-wpcpm-open]' )->length,
	$x_blank->query( '//*[@aria-invalid]' )->length,
), array( 'agreement-note', array( WPCPM_Institutions::FLASH, 'card-target' ), 0, 0 ) );

// A note kept with no refusal of this box on the channel (the outcome is another's) still comes back, in its
// open panel, with nothing said beside it.
$GLOBALS['uid'] = 37;
WPCPM_Typed_Text::keep_box( WPCPM_Typed_Text::box_form( WPCPM_Institution_Agreement::TYPED_RETURN, $agr_two ), 'note', 'Kept, and not refused.', WPCPM_Institution_Agreement::MAX_NOTE );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-accepted' );
$x_kept = review_doc( review_drawn( $agr_two, 'administrator' ) );
ck( 'a kept return note opens its panel with the typing, untied to any refusal', array(
	$x_kept->query( '//div[@id="wpcpm-decide-agr-' . $agr_two . '-return-panel"][@data-wpcpm-open]' )->length,
	$x_kept->evaluate( 'string(//textarea[@id="wpcpm-return-' . $agr_two . '"])' ),
	$x_kept->query( '//*[@aria-invalid]' )->length,
), array( 1, "\nKept, and not refused.", 0 ) );

// The card's message leads to the refused box. The card shell is the real one, holding the outcome for the
// Collaboration Agreements card as the dashboard holds it, around the two agreements' review blocks.
/**
 * The Collaboration Agreements card as the dashboard draws it, holding one outcome, around two review blocks.
 *
 * @param string $text The outcome's sentence, an error.
 * @param bool   $on   Whether the dashboard's surface is on.
 * @return string
 */
function agreements_card( $text, $on ) {
	global $agr_one, $agr_two;
	WPCPM_Dashboard_Card::forget();
	WPCPM_Dashboard_Card::hold( 'wpcpm-agreements', 'error', $text );
	$GLOBALS['caps']      = true;
	$GLOBALS['member_of'] = array();
	WPCPM_UI::set_live( array( 'administrator' ) );
	if ( $on ) {
		WPCPM_UI::enter( 'administrator' );
	}
	ob_start();
	WPCPM_Dashboard_Card::open(
		array(
			'key'           => 'agreements',
			'title'         => 'Collaboration Agreements',
			'prefix'        => 'wpcpm-',
			'id_on'         => 'section',
			'section_class' => 'wpcpm-administrator__card',
		)
	);
	WPCPM_Institution_Panel::render_review( $agr_one, true, WPCPM_Return::DASHBOARD );
	WPCPM_Institution_Panel::render_review( $agr_two, true, WPCPM_Return::DASHBOARD );
	WPCPM_Dashboard_Card::close();
	$html = (string) ob_get_clean();
	if ( $on ) {
		WPCPM_UI::leave();
	}
	WPCPM_UI::set_live( null );
	WPCPM_Dashboard_Card::forget();
	return $html;
}

$note_refused = (string) WPCPM_Institution_Panel::messages()['agreement-note'][1];
return_refused( $agr_two, 'Too short.', 38, 39 );
$led_card  = agreements_card( $note_refused, true );
$x_led     = review_doc( $led_card );
$led_links = iterator_to_array( $x_led->query( '//p[@data-wpcpm-card-message]//a' ) );
ck( 'on, the refused note\'s message is the refusal sentence as one link to the refused box, said once, before both agreements, the box inside the panel printed open', array(
	count( $led_links ),
	isset( $led_links[0] ) ? $led_links[0]->getAttribute( 'href' ) : null,
	isset( $led_links[0] ) ? $led_links[0]->textContent : null,
	substr_count( $led_card, 'data-wpcpm-card-message' ),
	strpos( $led_card, 'data-wpcpm-card-message' ) < strpos( $led_card, 'id="wpcpm-decide-agr-' . $agr_one . '"' ),
	$x_led->query( '//div[@data-wpcpm-open]//textarea[@id="wpcpm-return-' . $agr_two . '"]' )->length,
), array( 1, '#wpcpm-return-' . $agr_two, $note_refused, 1, true, 1 ) );
return_refused( $agr_one, '   ', 42, 43 );
ck( 'a blank note\'s message leads nowhere: no box was named', review_doc( agreements_card( $note_refused, true ) )->query( '//p[@data-wpcpm-card-message]//a' )->length, 0 );

// A refusal is tied only to the box it was for (Task 13x). The outcome names no document, and the flash hands every
// box on the page the same one, so a document whose kept typing no page drew (its card closed, or past the list)
// would read a later refusal of another as its own. The store remembers which form it kept last, and only that
// box is tied: here a document holding older kept typing beside one refused just now, drawn first and then last.
/**
 * What a card says about each box on it: which panels are printed open, each box's state ('invalid ' when marked,
 * then what describes it, then the sentence beside it or '', then its typing), and where the card's message leads.
 *
 * @param string $html Markup.
 * @return array
 */
function panel_ties( $html ) {
	$x     = review_doc( $html );
	$boxes = array();
	foreach ( $x->query( '//textarea' ) as $box ) {
		$id           = $box->getAttribute( 'id' );
		$said         = $x->query( '//*[@id="' . WPCPM_Form_Errors::problem_id( $id ) . '"]' )->item( 0 );
		$boxes[ $id ] = ( $box->hasAttribute( 'aria-invalid' ) ? 'invalid ' : '' ) . $box->getAttribute( 'aria-describedby' ) . ' | ' . ( $said ? trim( $said->textContent ) : '' ) . ' | ' . $box->textContent;
	}
	return array(
		'open'  => array_map( static function ( $node ) { return $node->getAttribute( 'id' ); }, iterator_to_array( $x->query( '//*[@data-wpcpm-open]' ) ) ),
		'boxes' => $boxes,
		'leads' => array_map( static function ( $node ) { return $node->getAttribute( 'href' ) . ' ' . $node->textContent; }, iterator_to_array( $x->query( '//p[@data-wpcpm-card-message]//a' ) ) ),
	);
}
/**
 * A box as `panel_ties()` reads it: refused, marked invalid and described by its sentence then its hint; otherwise
 * described by its hint alone.
 *
 * @param int         $post_id The document.
 * @param string|null $said    The refusal sentence, or null when it is not the refused box.
 * @param string      $typing  What it holds.
 * @return string
 */
function panel_tie( $post_id, $said, $typing ) {
	$id = 'wpcpm-return-' . (int) $post_id;
	return null === $said ? $id . '-hint |  | ' . "\n" . $typing : 'invalid ' . WPCPM_Form_Errors::problem_id( $id ) . ' ' . $id . '-hint | ' . $said . ' | ' . "\n" . $typing;
}
$tie_users = 130;
foreach ( array( 'first' => array( $agr_one, $agr_two ), 'last' => array( $agr_two, $agr_one ) ) as $tie_where => $tie_pair ) {
	list( $tie_kept, $tie_refused ) = $tie_pair;
	$GLOBALS['uid'] = ++$tie_users;
	WPCPM_Typed_Text::keep_box( WPCPM_Typed_Text::box_form( WPCPM_Institution_Agreement::TYPED_RETURN, $tie_kept ), 'note', 'Kept, and not refused.', WPCPM_Institution_Agreement::MAX_NOTE );
	return_refused( $tie_refused, 'Too short.', $tie_users, ++$tie_users );
	ck( 'Collaboration Agreements, the kept-only document drawn ' . $tie_where . ': only the refused document\'s box is invalid and tied to the sentence, the card\'s message is one link to it, and the kept-only document\'s panel is open with its typing, its box tied to its hint alone (Task 13x)', panel_ties( agreements_card( $note_refused, true ) ), array(
		'open'  => array( 'wpcpm-decide-agr-' . $agr_one . '-return-panel', 'wpcpm-decide-agr-' . $agr_two . '-return-panel' ),
		'boxes' => array(
			'wpcpm-return-' . $agr_one => panel_tie( $agr_one, $agr_one === $tie_refused ? $note_refused : null, $agr_one === $tie_refused ? 'Too short.' : 'Kept, and not refused.' ),
			'wpcpm-return-' . $agr_two => panel_tie( $agr_two, $agr_two === $tie_refused ? $note_refused : null, $agr_two === $tie_refused ? 'Too short.' : 'Kept, and not refused.' ),
		),
		'leads' => array( '#wpcpm-return-' . $tie_refused . ' ' . $note_refused ),
	) );
}
$GLOBALS['uid'] = ++$tie_users;
WPCPM_Typed_Text::keep_box( WPCPM_Typed_Text::box_form( WPCPM_Institution_Agreement::TYPED_RETURN, $agr_one ), 'note', 'Kept, and not refused.', WPCPM_Institution_Agreement::MAX_NOTE );
return_refused( $agr_two, " \n ", $tie_users, ++$tie_users );
ck( 'Collaboration Agreements: a blank note, refused and keeping nothing, beside a document holding older kept typing: no box is marked invalid, the message leads nowhere, and the older typing comes back untied (Task 13x)', panel_ties( agreements_card( $note_refused, true ) ), array(
	'open'  => array( 'wpcpm-decide-agr-' . $agr_one . '-return-panel' ),
	'boxes' => array(
		'wpcpm-return-' . $agr_one => panel_tie( $agr_one, null, 'Kept, and not refused.' ),
		'wpcpm-return-' . $agr_two => 'wpcpm-return-' . $agr_two . '-hint |  | ' . "\n",
	),
	'leads' => array(),
) );

unset( $GLOBALS['faithful_nonces'], $GLOBALS['umeta'] );
$GLOBALS['uid']     = 0;
$GLOBALS['referer'] = '';

// Task 17b, ruling 5: on the parts the review block that decides prints its download line with no class of its own
// (its rules live in sheets the Administrator Dashboard never loads); the block drawn to be read keeps it.
ck( 'ruling 5: the deciding review on the parts prints the download line bare; the read-only block keeps its class', array(
	1 === preg_match( '#<p><a href="[^"]*action=wpcpm_agreement_download&post=' . $agr_one . '&[^"]*" rel="nofollow">Download the signed agreement</a></p>#', $on_one ),
	strpos( $on_one, 'wpcpm-agreement-panel__download' ),
	substr_count( review_drawn( $agr_one, 'administrator', false ), '<p class="wpcpm-agreement-panel__download">' ),
), array( true, false, 1 ) );
// Task 18c (the review of Task 18b, minor 4): Accept and Return hand their decision to the area `render_decide_area()`
// is collecting, and to nothing else. Called outside it, each hands nothing on and prints nothing: the collector stays
// null, never an array a later area would read as its own.
$panel_into = new ReflectionProperty( 'WPCPM_Institution_Panel', 'decide_into' );
// Reflection reaches a private member without setAccessible() since PHP 8.1; PHP 8.5 deprecates the call.
if ( PHP_VERSION_ID < 80100 ) {
	$panel_into->setAccessible( true );
}
$panel_outside = array();
foreach ( array( 'render_accept_form' => array( $agr_one, 'Uni A', 3, WPCPM_Return::DASHBOARD ), 'render_return_form' => array( $agr_one, WPCPM_Return::DASHBOARD ) ) as $panel_method => $panel_args ) {
	$panel_call = new ReflectionMethod( 'WPCPM_Institution_Panel', $panel_method );
	if ( PHP_VERSION_ID < 80100 ) {
		$panel_call->setAccessible( true );
	}
	ob_start();
	$panel_call->invokeArgs( null, $panel_args );
	$panel_outside[ $panel_method ] = array( ob_get_clean(), $panel_into->getValue() );
}
ck( 'outside the Decide area\'s collecting, Accept and Return hand nothing on and print nothing: the collector stays null', $panel_outside, array(
	'render_accept_form' => array( '', null ),
	'render_return_form' => array( '', null ),
) );
// The inventory (UI shared parts, Task 17b; bin/stubs/parts-inventory.php): every old class this suite's drawings on
// the Administrator Dashboard's parts print inside the switch's root is one the inventory names, and no other.
ck( 'the inventory: the old classes printed inside the parts are exactly the ones named in bin/stubs/parts-inventory.php', parts_inventory_found(), parts_inventory_pinned( 'institution-panel' ) );
// Institution Dashboard frame, Task 4: the sentence each locked state says is `state_sentence()`, which
// `render_state()` prints and the Overview's waiting row reads as its why. The panel prints the same bytes: each
// state's whole panel is pinned by an md5 digest of today's output (the code at c0dfdf0a), the read time masked
// since `rebuild()` stamps the clock.
echo "\n=== The state's sentence, one method for the panel and the Overview ===\n";
$state_sentence = static function ( $state, array $row = array() ) {
	return method_exists( 'WPCPM_Institution_Panel', 'state_sentence' ) ? WPCPM_Institution_Panel::state_sentence( $state, $row ) : 'no state_sentence()';
};
// Task 25, fix round 2: the sentence says what a school with no settled agreement shows its people now.
ck( 'state_sentence(): none is the lede the panel prints', $state_sentence( 'none' ), 'The program has no Collaboration Agreement recorded for your institution. Until it does, its representatives reach only the Overview and the Agreement, and its tutors no page.' );
ck( 'state_sentence(): generated names the day and the template, from the summary handed in', $generated_sentence, 'You generated the program\'s agreement on September 1, 2025 (template 2025-11-04). It is not signed and uploaded yet.' );
ck( 'state_sentence(): returned is the upload form\'s sentence', $state_sentence( 'returned' ), 'Upload the corrected agreement, signed, as a PDF.' );
ck( 'state_sentence(): revoked is the upload form\'s sentence', $state_sentence( 'revoked' ), 'Upload a new signed agreement as a PDF. An Administrator reviews it the same way as the first one.' );
ck( 'state_sentence(): every other state is empty', array( $state_sentence( 'submitted' ), $state_sentence( 'accepted' ), $state_sentence( 'on_file' ), $state_sentence( 'nonsense' ) ), array( '', '', '', '' ) );
ck( 'and each sentence is in its panel, escaped as the panel escapes it', array(
	false !== strpos( $ledes['none'], esc_html( $state_sentence( 'none' ) ) ),
	false !== strpos( (string) preg_replace( '#<span class="wpcpm-ui-whole">([^<]*)</span>#', '$1', $ledes['generated'] ), esc_html( $generated_sentence ) ),
	false !== strpos( $ledes['returned'], esc_html( $state_sentence( 'returned' ) ) ),
	false !== strpos( $ledes['revoked'], esc_html( $state_sentence( 'revoked' ) ) ),
), array( true, true, true, true ) );
$panel_digest = static function ( $html ) use ( $read_line ) {
	return md5( (string) preg_replace( $read_line, '{read}', $html ) );
};

/* ---- Institution Dashboard, Task 21: the Collaboration Agreement on the parts ---- */

echo "\n=== The Collaboration Agreement on the parts (Institution Dashboard, Task 21) ===\n";

$t21_rec     = $rec_a;
$t21_long    = 'Exampleinstituteforappliedwebengineerings';
$t21_mail    = 'averyveryveryverylongmailboxname1234@example.org';
$t21_hostile = 'Uni <script>alert(1)</script> "Q" & Co';
$t21_print   = '' !== (string) getenv( 'T21_PRINT' );

if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/**
	 * The Institution Dashboard, at the one method the Agreement view's block reads (Task 21, fix round 1): the banner's
	 * sentences, the state in words. The real sentences are bin/test-institution-overview.php's.
	 */
	class WPCPM_Institutions_Dashboard {
		public static function banner_sentences( string $record ): array {
			// Task 25, fix round 1, B1: the sentence says what a school with no settled agreement shows its people now.
			return array( 'This institution\'s agreement is not settled: ' . WPCPM_Institution_Agreement::summary( $record )['state'] . '. Its representatives see only the Overview and the Agreement, and its tutors no page.' );
		}
	}
}
preg_match( "/const KEPT_GENERATE\s*=\s*'([^']+)'/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-agreement-generate.php' ), $t21_m );
ck( 'the generate route\'s kept form stubbed here is the real file\'s', WPCPM_Agreement_Generate::KEPT_GENERATE, $t21_m[1] ?? '' );

/**
 * Stand the school up in one of the view's states, every clock and id fixed so a drawing is the same bytes each run.
 *
 * @param string $state   none, generated, submitted, returned, revoked, disagree, accepted, waiting (accepted with a
 *                        newer copy waiting), on file.
 * @param string $variant '' for the plain fixture, long (a 40-character word in the name, the note and the
 *                        Administrator's name, a 48-character address), hostile (a tag, a quote and an ampersand in
 *                        each), two languages.
 */
function t21_world( $state, $variant = '' ) {
	global $t21_rec, $t21_long, $t21_mail, $t21_hostile, $drive;
	reset_world();
	$GLOBALS['clock']     = 1756700000;
	$GLOBALS['next_id']   = 7001;
	$GLOBALS['tutor']     = false;
	$GLOBALS['languages'] = 'two languages' === $variant ? array( 'en', 'es' ) : array( 'en' );

	$name  = 'long' === $variant ? $t21_long . ' University' : ( 'hostile' === $variant ? $t21_hostile : 'Universidad Example' );
	$note  = 'long' === $variant ? 'Page 4 needs ' . $t21_long . ' signed.' : ( 'hostile' === $variant ? $t21_hostile . "\nSecond line." : "Page 4 is not signed.\nPlease send it again." );
	$admin = 'long' === $variant ? 'Ada ' . $t21_long : ( 'hostile' === $variant ? $t21_hostile : 'Ada Manager' );

	$row              = WPCPM_Institutions_Index::empty_row();
	$row['record_id'] = $t21_rec;
	$row['name']      = $name;
	$row['stage']     = 'Confirmed';
	$row['country']   = 'recCOUNTRYAAAAAAA';
	WPCPM_Institutions_Index::write( array( $t21_rec => $row ), 1756600000 );
	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1756600000,
		'rows' => array( 'recCOUNTRYAAAAAAA' => array( 'name' => 'Poland', 'manager' => 'recTEAMAAAAAAAA1', 'email' => 'poland@example.org', 'calendly' => '' ) ),
	);
	$GLOBALS['users'][12]  = new WP_User( 12, 'Ana Admin', 'long' === $variant ? $t21_mail : 'ana.admin@example.org' );
	$GLOBALS['grants'][12] = array( 'wpcpm_manage_program' );
	$GLOBALS['kept'][ $t21_rec ] = array( 'assigned' => 12 );
	$GLOBALS['users'][9]   = new WP_User( 9, $admin, 'ada@example.test' );

	$file = array(
		WPCPM_Institution_Agreement::META_FILE          => array( 'path' => '2026/0123456789abcdef0123456789abcd.pdf', 'size' => 239616, 'sha256' => str_repeat( 'a', 64 ) ),
		WPCPM_Institution_Agreement::META_ORIGINAL_NAME => 'Umowa podpisana.pdf',
	);
	$decided = array(
		WPCPM_Institution_Agreement::META_NOTE       => $note,
		WPCPM_Institution_Agreement::META_DECIDED_BY => 9,
		WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-30',
	);

	$status = 'Not started';
	switch ( $state ) {
		case 'generated':
			seed_post( $t21_rec, WPCPM_Institution_Agreement::STATE_GENERATED, WPCPM_Institution_Agreement::KIND_TEMPLATE, array(
				WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04',
				WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT => $name,
				WPCPM_Institution_Agreement::META_LANGUAGE         => 'en',
			) );
			$status = 'Template generated';
			break;
		case 'submitted':
			seed_post( $t21_rec, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_OWN, $file );
			$status = 'Awaiting review';
			break;
		case 'returned':
			seed_post( $t21_rec, WPCPM_Institution_Agreement::STATE_RETURNED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $decided );
			$status = 'Returned';
			break;
		case 'revoked':
			seed_post( $t21_rec, WPCPM_Institution_Agreement::STATE_REVOKED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $decided );
			$status = 'Revoked';
			break;
		case 'disagree':
			seed_post( $t21_rec, WPCPM_Institution_Agreement::STATE_ACCEPTED, WPCPM_Institution_Agreement::KIND_OWN );
			$status = 'Revoked';
			break;
		case 'accepted':
		case 'waiting':
			$t21_accepted_post = seed_post( $t21_rec, WPCPM_Institution_Agreement::STATE_ACCEPTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file );
			// Fix round 2: an acceptance with no day recorded, neither decided nor dated.
			if ( 'undated' === $variant ) {
				$GLOBALS['posts'][ $t21_accepted_post ]->post_date = '0000-00-00 00:00:00';
			}
			if ( 'waiting' === $state ) {
				seed_post( $t21_rec, WPCPM_Institution_Agreement::STATE_SUBMITTED, WPCPM_Institution_Agreement::KIND_TEMPLATE, $file );
			}
			$status = 'Accepted';
			break;
	}

	// The Drive link an Administrator recorded: hostile, or of a scheme the site never links (fix round 1, point 7).
	$links = array(
		'drive hostile'    => 'https://drive.google.com/drive/folders/1Ab?x="<script>alert(1)</script>&y=1',
		'drive tag'        => 'https://drive.google.com/drive/folders/1Ab?x=<script>alert(1)</script>&y=1',
		'drive javascript' => 'javascript:alert(1)',
		'drive data'       => 'data:text/html,<script>alert(1)</script>',
		'drive http'       => 'http://drive.google.com/drive/folders/1Ab',
	);
	$link  = isset( $links[ $variant ] ) ? $links[ $variant ] : $drive;
	WPCPM_Institution_Agreement::rebuild( $t21_rec, 'on file' === $state ? block( 'On file', $link ) : block( $status ) );

	// The read time is the option's own stamp, which `rebuild()` takes from the real clock: fixed here.
	$option            = WPCPM_Institution_Agreement::option( $t21_rec );
	$option['updated'] = 1756600000;
	if ( 'with drive' === $variant || isset( $links[ $variant ] ) ) {
		$option['drive_url'] = isset( $links[ $variant ] ) ? $links[ $variant ] : $drive;
	}
	$GLOBALS['opts'][ WPCPM_Institution_Agreement::option_name( $t21_rec ) ] = $option;
}

/**
 * Draw the Agreement view's two halves as the view draws them (the panel, then the card), for one reader.
 *
 * @param string     $state   As t21_world().
 * @param string     $reader  representative, administrator, tutor or stranger (a member of another school).
 * @param string     $flash   An outcome slug the handler flashed, or ''.
 * @param string     $variant As t21_world().
 * @param string     $surface The surface drawing, '' for none.
 * @param array|null $live    The surfaces on (`WPCPM_UI::set_live()`).
 * @return string
 */
function t21_draw( $state, $reader, $flash = '', $variant = '', $surface = '', $live = null ) {
	global $t21_rec, $rec_b;
	t21_world( $state, $variant );
	$manager              = 'administrator' === $reader;
	$GLOBALS['caps']      = $manager;
	$GLOBALS['tutor']     = 'tutor' === $reader;
	$GLOBALS['member_of'] = $manager ? array() : array( 'stranger' === $reader ? $rec_b : $t21_rec );
	$GLOBALS['uid']       = $manager ? 9 : 7;
	$GLOBALS['users'][7]  = new WP_User( 7, 'Wera Rektor', 'wera@example.edu' );
	t21_forget_flash();
	if ( '' !== $flash ) {
		WPCPM_Flash::set( WPCPM_Institutions::FLASH, $flash, $GLOBALS['uid'] );
	}
	// What a refused form kept, as its handler keeps it (fix round 1, point 9).
	foreach ( (array) ( $GLOBALS['t21_keep'] ?? array() ) as $form => $values ) {
		WPCPM_Flash::keep( $form . $t21_rec, $values );
	}
	WPCPM_UI::set_live( $live );
	if ( '' !== $surface ) {
		WPCPM_UI::enter( $surface );
	}
	ob_start();
	try {
		WPCPM_Institution_Panel::render( $t21_rec, array( 'can_manage' => $manager ) );
		WPCPM_Institution_Agreement_Card::render( $t21_rec, array( 'can_manage' => $manager ) );
	} finally {
		$html = (string) ob_get_clean();
		if ( '' !== $surface ) {
			WPCPM_UI::leave();
		}
		WPCPM_UI::set_live( null );
		$GLOBALS['tutor'] = false;
	}
	return $html;
}

/** The flash's per-request memo alone, emptied: a new request, the meta kept. */
function t21_forget_flash_memo() {
	$taken = new ReflectionProperty( 'WPCPM_Flash', 'taken' );
	if ( PHP_VERSION_ID < 80100 ) {
		$taken->setAccessible( true );
	}
	$taken->setValue( null, array() );
}

/** The flash's per-request memo, emptied between two drawings of one process (the real `take()` keeps it for a request). */
function t21_forget_flash() {
	$taken = new ReflectionProperty( 'WPCPM_Flash', 'taken' );
	if ( PHP_VERSION_ID < 80100 ) {
		$taken->setAccessible( true );
	}
	$taken->setValue( null, array() );
	unset( $GLOBALS['umeta'][ (int) $GLOBALS['uid'] ][ WPCPM_Flash::META ] );
}

/**
 * The same, on the Institution Dashboard's parts.
 *
 * @param string $state   As t21_world().
 * @param string $reader  As t21_draw().
 * @param string $flash   As t21_draw().
 * @param string $variant As t21_world().
 * @return string
 */
function t21_parts( $state, $reader, $flash = '', $variant = '' ) {
	return t21_draw( $state, $reader, $flash, $variant, 'institution', array( 'administrator', 'institution' ) );
}

// Every state the view has, for each reader, and every outcome the handlers flash.
$t21_states  = array( 'none', 'generated', 'submitted', 'returned', 'revoked', 'disagree', 'accepted', 'waiting', 'on file' );
$t21_readers = array( 'representative', 'administrator', 'tutor', 'stranger' );
$t21_cases   = array();
foreach ( $t21_states as $t21_state ) {
	foreach ( $t21_readers as $t21_reader ) {
		$t21_cases[ $t21_state . ', ' . $t21_reader ] = array( $t21_state, $t21_reader, '', '' );
	}
}
foreach ( array( 'long', 'hostile' ) as $t21_variant ) {
	foreach ( array( 'none', 'generated', 'submitted', 'returned', 'revoked', 'disagree', 'accepted', 'waiting', 'on file' ) as $t21_state ) {
		$t21_cases[ $t21_state . ', representative, ' . $t21_variant ] = array( $t21_state, 'representative', '', $t21_variant );
		$t21_cases[ $t21_state . ', administrator, ' . $t21_variant ]  = array( $t21_state, 'administrator', '', $t21_variant );
	}
}
$t21_cases['generated, representative, two languages'] = array( 'generated', 'representative', '', 'two languages' );
// Fix round 1, point 7: the Drive link an Administrator recorded, hostile or of a scheme never linked; point 6: an
// accepted copy with its file and a Drive link, both in one row.
foreach ( array( 'drive hostile', 'drive tag', 'drive javascript', 'drive data', 'drive http' ) as $t21_variant ) {
	$t21_cases[ 'on file, representative, ' . $t21_variant ] = array( 'on file', 'representative', '', $t21_variant );
	$t21_cases[ 'on file, administrator, ' . $t21_variant ]  = array( 'on file', 'administrator', '', $t21_variant );
}
$t21_cases['accepted, administrator, with drive'] = array( 'accepted', 'administrator', '', 'with drive' );
$t21_cases['accepted, representative, undated']   = array( 'accepted', 'representative', '', 'undated' );
foreach ( array_keys( WPCPM_Institution_Panel::messages() ) as $t21_slug ) {
	$t21_cases[ 'message ' . $t21_slug . ', none, administrator' ] = array( 'none', 'administrator', $t21_slug, '' );
}
foreach ( array( 'agreement-not-pdf', 'agreement-declare', 'agreement-kind', 'agreement-name', 'agreement-name-brackets', 'agreement-too-big', 'agreement-uploaded' ) as $t21_slug ) {
	$t21_cases[ 'message ' . $t21_slug . ', none, representative' ] = array( 'none', 'representative', $t21_slug, '' );
	$t21_cases[ 'message ' . $t21_slug . ', accepted, representative' ] = array( 'accepted', 'representative', $t21_slug, '' );
	$t21_cases[ 'message ' . $t21_slug . ', returned, representative' ] = array( 'returned', 'representative', $t21_slug, '' );
}
$t21_cases['message agreement-on-file, accepted, administrator'] = array( 'accepted', 'administrator', 'agreement-on-file', '' );

// What the old branch drew in every case, read from the classes at db850661 (the last commit that drew it) before it was
// deleted (Task 23): whether it drew nothing, a digest of its words (`t21_words()`), the words it said that the parts do
// not and the parts say that it did not (so the old words are rebuilt from the parts' and checked against the digest),
// and whether it said "Accepted on".
// Task 25, fix round 1, B1: in the Administrator's cases the banner's sentence and the revoke outcome say what a school with no
// settled agreement shows its people now; only those words changed in the two lists.
// Task 25, fix round 2: the panel's sentences for none recorded and revoked say what the school's people reach now. In each case
// that draws one, the old words are the old branch's with that sentence alone replaced (the digest retaken), and only those
// words changed in the two lists.
$t21_old_pins = array(
	'none, representative' => array( false, '8c91853d37a0e1c8cdbe6433559d59d9', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'none, administrator' => array( false, 'bb3b91b8aff9b342f1234010a8b3c4f2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'none, tutor' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'none, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'generated, representative' => array( false, '3f9ac7ce422a42cb67fbe0da33c2add0', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'generated, administrator' => array( false, 'c2a0c65c398445176758ee89d4cc6c74', array( 'Collaboration', 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Overview', 'Required', 'chosen', 'its', 'no', 'only', 'representatives', 'see', 'settled', 'tutors' ), false ),
	'generated, tutor' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'generated, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'submitted, representative' => array( false, '544ce261781099296fa65e1dde86edc8', array( 'Agreement', 'Collaboration' ), array( 'Waiting', 'for' ), false ),
	'submitted, administrator' => array( false, '7f09c3d437e7b6532e97ee346c0b0caf', array( 'Collaboration', 'optional', 'required' ), array( 'Its', 'Optional', 'Overview', 'Required', 'This', 'Waiting', 'for', 'its', 'no', 'not', 'only', 'representatives', 'see', 'settled', 'submitted', 'tutors' ), false ),
	'submitted, tutor' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'submitted, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'returned, representative' => array( false, '66e6dfa93278dfd1c365d054078ea1b6', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'returned, administrator' => array( false, '37ae4ec7425b3f3fc7865882129cc895', array( 'Collaboration', 'optional', 'required' ), array( 'Choose', 'Its', 'No', 'Optional', 'Overview', 'Required', 'Returned', 'and', 'chosen', 'institution\'s', 'its', 'no', 'only', 'representatives', 'see', 'settled', 'tutors' ), false ),
	'returned, tutor' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'returned, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'revoked, representative' => array( false, '0e6e8191392ceba38d3b32b57f5fdd39', array( 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Revoked', 'chosen', 'file' ), false ),
	'revoked, administrator' => array( false, 'e9a5e3adefaf999e849c36f1f7e1f4bc', array( 'Collaboration', 'optional', 'required' ), array( 'Choose', 'Its', 'No', 'Optional', 'Required', 'Revoked', 'chosen', 'see', 'settled' ), false ),
	'revoked, tutor' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'revoked, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'disagree, representative' => array( false, '36fc09ed932c27fc72229d3f0c88b91b', array( 'Agreement', 'Collaboration' ), array(), false ),
	'disagree, administrator' => array( false, '4389ee021fba8114dac0f974b4465f80', array( 'Collaboration', 'optional', 'required' ), array( 'Its', 'Optional', 'Overview', 'Required', 'institution\'s', 'its', 'no', 'only', 'representatives', 'settled', 'tutors' ), false ),
	'disagree, tutor' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'disagree, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'accepted, representative' => array( false, '5d042fe2f89e70ff23a1ba8972731fca', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'accepted, administrator' => array( false, '5d042fe2f89e70ff23a1ba8972731fca', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'accepted, tutor' => array( false, '373330515452c40ae49ac23911c6d3ed', array( 'Agreement', 'Collaboration', 'on' ), array(), true ),
	'accepted, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'waiting, representative' => array( false, '66819744157382e1aad528c046d21dbe', array( 'Agreement', 'Collaboration', 'on' ), array(), true ),
	'waiting, administrator' => array( false, '19c8fe993f7d06958671d9e85037dc19', array( 'Agreement', 'Collaboration' ), array(), true ),
	'waiting, tutor' => array( false, '4e9e701669c2497b739abada680c0f58', array( 'Agreement', 'Collaboration', 'on' ), array(), true ),
	'waiting, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'on file, representative' => array( false, '65babb1b209a6a3fc723815a403c23e1', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, administrator' => array( false, '4c215f63135f5f2bfa68c4d43c379f61', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, tutor' => array( false, '5b339f1b89d42a580c08e3a5459cd372', array( 'Agreement', 'Collaboration', 'on' ), array(), true ),
	'on file, stranger' => array( true, '40cd750bba9870f18aada2478b24840a', array(), array(), false ),
	'none, representative, long' => array( false, '21d95207a6d9c1d113efe7847b85b169', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'none, administrator, long' => array( false, 'bb3b91b8aff9b342f1234010a8b3c4f2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'generated, representative, long' => array( false, '115125ab62f84ddc9162773f4a87f47e', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'generated, administrator, long' => array( false, 'c2a0c65c398445176758ee89d4cc6c74', array( 'Collaboration', 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Overview', 'Required', 'chosen', 'its', 'no', 'only', 'representatives', 'see', 'settled', 'tutors' ), false ),
	'submitted, representative, long' => array( false, '544ce261781099296fa65e1dde86edc8', array( 'Agreement', 'Collaboration' ), array( 'Waiting', 'for' ), false ),
	'submitted, administrator, long' => array( false, '7f09c3d437e7b6532e97ee346c0b0caf', array( 'Collaboration', 'optional', 'required' ), array( 'Its', 'Optional', 'Overview', 'Required', 'This', 'Waiting', 'for', 'its', 'no', 'not', 'only', 'representatives', 'see', 'settled', 'submitted', 'tutors' ), false ),
	'returned, representative, long' => array( false, 'e6bc97b6d15a50e6f10b9b017fb9ae2b', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'returned, administrator, long' => array( false, '703e28ed06d16b573eef2f00f0881838', array( 'Collaboration', 'optional', 'required' ), array( 'Choose', 'Its', 'No', 'Optional', 'Overview', 'Required', 'Returned', 'and', 'chosen', 'institution\'s', 'its', 'no', 'not', 'only', 'representatives', 'see', 'settled', 'tutors' ), false ),
	'revoked, representative, long' => array( false, 'd219ed560ff8939933081b6ec9510aa3', array( 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Revoked', 'chosen', 'file' ), false ),
	'revoked, administrator, long' => array( false, '9cfa3eadc59924a88d2157fbde34a75d', array( 'Collaboration', 'optional', 'required' ), array( 'Choose', 'Its', 'No', 'Optional', 'Required', 'Revoked', 'chosen', 'not', 'see', 'settled' ), false ),
	'disagree, representative, long' => array( false, '9529816985db251507e82372538b9a6c', array( 'Agreement', 'Collaboration' ), array(), false ),
	'disagree, administrator, long' => array( false, '4389ee021fba8114dac0f974b4465f80', array( 'Collaboration', 'optional', 'required' ), array( 'Its', 'Optional', 'Overview', 'Required', 'institution\'s', 'its', 'no', 'only', 'representatives', 'settled', 'tutors' ), false ),
	'accepted, representative, long' => array( false, '5d042fe2f89e70ff23a1ba8972731fca', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'accepted, administrator, long' => array( false, '5d042fe2f89e70ff23a1ba8972731fca', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'waiting, representative, long' => array( false, '66819744157382e1aad528c046d21dbe', array( 'Agreement', 'Collaboration', 'on' ), array(), true ),
	'waiting, administrator, long' => array( false, '19c8fe993f7d06958671d9e85037dc19', array( 'Agreement', 'Collaboration' ), array(), true ),
	'on file, representative, long' => array( false, '65babb1b209a6a3fc723815a403c23e1', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, administrator, long' => array( false, '4c215f63135f5f2bfa68c4d43c379f61', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'none, representative, hostile' => array( false, '8c91853d37a0e1c8cdbe6433559d59d9', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'none, administrator, hostile' => array( false, 'bb3b91b8aff9b342f1234010a8b3c4f2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'generated, representative, hostile' => array( false, '3f9ac7ce422a42cb67fbe0da33c2add0', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'generated, administrator, hostile' => array( false, 'c2a0c65c398445176758ee89d4cc6c74', array( 'Collaboration', 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Overview', 'Required', 'chosen', 'its', 'no', 'only', 'representatives', 'see', 'settled', 'tutors' ), false ),
	'submitted, representative, hostile' => array( false, '544ce261781099296fa65e1dde86edc8', array( 'Agreement', 'Collaboration' ), array( 'Waiting', 'for' ), false ),
	'submitted, administrator, hostile' => array( false, '7f09c3d437e7b6532e97ee346c0b0caf', array( 'Collaboration', 'optional', 'required' ), array( 'Its', 'Optional', 'Overview', 'Required', 'This', 'Waiting', 'for', 'its', 'no', 'not', 'only', 'representatives', 'see', 'settled', 'submitted', 'tutors' ), false ),
	'returned, representative, hostile' => array( false, '3d49f1c771f9b66757884d8bc207f759', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'returned, administrator, hostile' => array( false, '095a1732c04dd30e39fab128691fd05b', array( 'Collaboration', 'optional', 'required' ), array( 'Choose', 'Its', 'No', 'Optional', 'Overview', 'Required', 'Returned', 'and', 'chosen', 'institution\'s', 'its', 'no', 'not', 'only', 'representatives', 'see', 'settled', 'tutors' ), false ),
	'revoked, representative, hostile' => array( false, '2b58fdb7495064c6e422edb88ed2558d', array( 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Revoked', 'chosen', 'file' ), false ),
	'revoked, administrator, hostile' => array( false, '6887e147acf102a0bdd1a65bb53ae737', array( 'Collaboration', 'optional', 'required' ), array( 'Choose', 'Its', 'No', 'Optional', 'Required', 'Revoked', 'chosen', 'not', 'see', 'settled' ), false ),
	'disagree, representative, hostile' => array( false, '36fc09ed932c27fc72229d3f0c88b91b', array( 'Agreement', 'Collaboration' ), array(), false ),
	'disagree, administrator, hostile' => array( false, '4389ee021fba8114dac0f974b4465f80', array( 'Collaboration', 'optional', 'required' ), array( 'Its', 'Optional', 'Overview', 'Required', 'institution\'s', 'its', 'no', 'only', 'representatives', 'settled', 'tutors' ), false ),
	'accepted, representative, hostile' => array( false, '5d042fe2f89e70ff23a1ba8972731fca', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'accepted, administrator, hostile' => array( false, '5d042fe2f89e70ff23a1ba8972731fca', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'waiting, representative, hostile' => array( false, '66819744157382e1aad528c046d21dbe', array( 'Agreement', 'Collaboration', 'on' ), array(), true ),
	'waiting, administrator, hostile' => array( false, '19c8fe993f7d06958671d9e85037dc19', array( 'Agreement', 'Collaboration' ), array(), true ),
	'on file, representative, hostile' => array( false, '65babb1b209a6a3fc723815a403c23e1', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, administrator, hostile' => array( false, '4c215f63135f5f2bfa68c4d43c379f61', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'generated, representative, two languages' => array( false, 'a618c44b142427faef837263b6b1a091', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'on file, representative, drive hostile' => array( false, '65babb1b209a6a3fc723815a403c23e1', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, administrator, drive hostile' => array( false, 'f691e20337cd1cddef0c4d7451070a8a', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, representative, drive tag' => array( false, '65babb1b209a6a3fc723815a403c23e1', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, administrator, drive tag' => array( false, '4c215f63135f5f2bfa68c4d43c379f61', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'on file, representative, drive javascript' => array( false, '8c91853d37a0e1c8cdbe6433559d59d9', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'on file, administrator, drive javascript' => array( false, 'bb3b91b8aff9b342f1234010a8b3c4f2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'on file, representative, drive data' => array( false, '8c91853d37a0e1c8cdbe6433559d59d9', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'on file, administrator, drive data' => array( false, 'bb3b91b8aff9b342f1234010a8b3c4f2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'on file, representative, drive http' => array( false, '8c91853d37a0e1c8cdbe6433559d59d9', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'on file, administrator, drive http' => array( false, 'bb3b91b8aff9b342f1234010a8b3c4f2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'accepted, administrator, with drive' => array( false, '045e2e1d1a1c3a57e5c6dde15f1a1d4f', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'accepted, representative, undated' => array( false, '6d62631cf0ee2a17f6affed75d5aaf26', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), false ),
	'message agreement-on-file, none, administrator' => array( false, 'f40407814a122efc94630b02f2bb1cc7', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-link, none, administrator' => array( false, 'e0837c70f507352e37e393c6c734e922', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-standing, none, administrator' => array( false, '556d9679f325953c883c2b277145e8af', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-unknown, none, administrator' => array( false, '14b10f01b13fdf3cae1b63a65e4ecc09', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-busy, none, administrator' => array( false, '7172380099400c282c00f86ac84f0246', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	// Task 25, fix round 1, B1: the old branch's words with the revoke outcome's new sentence in place of its old one ("card" and "shows" out; "Overview", "representatives", "see", "their" and "tutors" in), so the digest is the old words' with that sentence alone replaced.
	'message agreement-revoked, none, administrator' => array( false, 'ac4f6e7763334e5e70b06b995a82a9b0', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'settled' ), false ),
	'message agreement-revoke-note, none, administrator' => array( false, 'ec161cdb4ac28e3bc585013a98fcc86b', array( 'exactly', 'optional' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-revoke-loss, none, administrator' => array( false, 'e46faf6819b84c18d853134b575c9b06', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-not-accepted, none, administrator' => array( false, '9b65e6081c48fab0b1d94c107d6c023e', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-not-revoked, none, administrator' => array( false, 'fcd1444a22aa86cd0cd48b0a21f8afe0', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-reinstate-standing, none, administrator' => array( false, 'af2b0f592277112701be2d96aeb41b51', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-reinstated, none, administrator' => array( false, '4143aa5755bb13cc3ee3f1213487cdf7', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-airtable, none, administrator' => array( false, '1f257923f0c6114d0340405447cdfbc6', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-not-saved, none, administrator' => array( false, 'a1155cf4b169475d9f1252c0250dc85d', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-later, none, administrator' => array( false, '5e5e0b38f1e00a0e624b08f5b37f36c3', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-uploaded, none, administrator' => array( false, '6b51810a155f2ad7b147f24e8af5ec12', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-too-big, none, administrator' => array( false, '144968e4092d1bee0311cbc6e867f2ad', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-not-pdf, none, administrator' => array( false, '9016df66b6f22e35094ce9b1335e8cad', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-no-file, none, administrator' => array( false, '67faea12ca4361b2740358727d40fb22', array( 'exactly', 'optional', 'required' ), array( 'Exactly', 'Its', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-file, none, administrator' => array( false, '97f93c01cd0f25680b882ad79d3dbbd3', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-gone, none, administrator' => array( false, 'ea66ce12cb4aa55e4d0e327b46a9803a', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'settled' ), false ),
	'message agreement-encrypted, none, administrator' => array( false, '561fd9fdadc1d284d57c57133d75e1d5', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-launch, none, administrator' => array( false, '2c051adc848a5cd981fc9e0f6e07c8d1', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-in-review, none, administrator' => array( false, 'e3ee225c8490b952c78b2605975c03ef', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-declare, none, administrator' => array( false, 'e99b632b077d13464bfb89c7bc524282', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-kind, none, administrator' => array( false, 'a1483fb28e465962adac5346c971e882', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-accepted, none, administrator' => array( false, '8d065ee821bda16f737c12357c44606a', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-returned, none, administrator' => array( false, 'aa11def9290b0af2c7a6e7f264de2d5d', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-withdrawn, none, administrator' => array( false, '1647a1acfd27c564b8b05eb6ea77b077', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-note, none, administrator' => array( false, '0bfc5fbe4362da2ac9d923ef64e3932e', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-return-loss, none, administrator' => array( false, '9b35381e558785bc23652286938378df', array( 'exactly', 'optional', 'required', 'words' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-stage, none, administrator' => array( false, 'c68c2709ff3323076c1a022c4db41d71', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-generated-later, none, administrator' => array( false, 'd97b05dc33f2577916812772fbe39119', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-name, none, administrator' => array( false, 'b8ca5bf520b3d1d016312155cbf6cbd9', array( 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-name-brackets, none, administrator' => array( false, '1ab3763d284c55ed30c70d4d8bd04037', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-generate-in-review, none, administrator' => array( false, 'd8233ef856e7a6556f50ea56e0b5f1a2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-generate-standing, none, administrator' => array( false, '4fe604b9b6ec064283ebb360c2a7d166', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'not', 'see', 'settled' ), false ),
	'message agreement-generate-not-saved, none, administrator' => array( false, '20a18a3d4dd6cbe4a35537ce325c03d2', array( 'exactly', 'optional', 'required' ), array( 'Choose', 'Exactly', 'Its', 'No', 'Optional', 'Required', 'chosen', 'none', 'see', 'settled' ), false ),
	'message agreement-not-pdf, none, representative' => array( false, '47ca46f8c1e41cfba0d627a2405e1673', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen' ), false ),
	'message agreement-not-pdf, accepted, representative' => array( false, 'fe3b05d6fdd1ce5bc4bb00f85eca8b0b', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen' ), true ),
	'message agreement-not-pdf, returned, representative' => array( false, '9243cbaf7d6067d678ee57a9c3caea89', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen' ), false ),
	'message agreement-declare, none, representative' => array( false, '7e3ac58a353dad91f4424d61dca6d24c', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'message agreement-declare, accepted, representative' => array( false, 'f9e4bdc565a8b2742981bf490da8373a', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'message agreement-declare, returned, representative' => array( false, '732f3ae2ab01c5f85c23c638f49f66c7', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'message agreement-kind, none, representative' => array( false, '42a810bbc2acbb75a25a9358384b2583', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'message agreement-kind, accepted, representative' => array( false, '35a9bf025ebba1f0fa506588d71089d3', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'message agreement-kind, returned, representative' => array( false, 'b32ef65fe6e1c23a0c9f9d159ff3ee71', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'message agreement-name, none, representative' => array( false, '12a702939a460050eafb2a9d98ba2ced', array(), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'message agreement-name, accepted, representative' => array( false, '52252cd10b9210ad09911c370efd843c', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'message agreement-name, returned, representative' => array( false, '62c3b15d67ba5afaff8c28d1e651bd4e', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'message agreement-name-brackets, none, representative' => array( false, 'def7d3e3145b80da496625cdcf40690c', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'message agreement-name-brackets, accepted, representative' => array( false, '094188ae6c435aae61a23fd673541206', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'message agreement-name-brackets, returned, representative' => array( false, 'a63971af0e7372e1559b8ed489552443', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'message agreement-too-big, none, representative' => array( false, '2260001274be61149d7605a2b3ae8207', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen' ), false ),
	'message agreement-too-big, accepted, representative' => array( false, 'cfeb5883038c526cac153fc4b9c810ff', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen' ), true ),
	'message agreement-too-big, returned, representative' => array( false, '67427d93f377faf0cf844e46d9052831', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen' ), false ),
	'message agreement-uploaded, none, representative' => array( false, 'db11d0d39633a6cebe33a9f94fe9d3ca', array( 'exactly' ), array( 'Choose', 'Exactly', 'No', 'Required', 'chosen', 'file' ), false ),
	'message agreement-uploaded, accepted, representative' => array( false, '45d0b093004efd194cfba900b5a35fb3', array( 'Agreement', 'Collaboration', 'on' ), array( 'Choose', 'No', 'Required', 'chosen', 'file' ), true ),
	'message agreement-uploaded, returned, representative' => array( false, '4d69b1e98c801d6fdc9f743c7f6f8b67', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'Returned', 'chosen', 'file' ), false ),
	'message agreement-on-file, accepted, administrator' => array( false, '20dec9ecbea9da003f4a439161f27a83', array( 'Agreement', 'Collaboration' ), array( 'Choose', 'No', 'Required', 'chosen' ), true ),
);
/**
 * The old branch's words in one case, rebuilt from the parts' drawing and the case's two lists, and checked against the
 * old words' digest: a list that does not match reads as one word that never matches.
 *
 * @param string $name The case.
 * @param string $html The parts' drawing of it.
 * @return string[]
 */
function t21_old_words( $name, $html ) {
	$pin   = $GLOBALS['t21_old_pins'][ $name ];
	$words = array_values( array_unique( array_merge( array_diff( t21_words( $html ), $pin[3] ), $pin[2] ) ) );
	sort( $words );
	return md5( serialize( $words ) ) === $pin[1] ? $words : array( '(not the old words)' );
}
$GLOBALS['t21_old_pins'] = $t21_old_pins;

/**
 * The manager's upload on an institution's Manage members view, drawn as wp-admin draws it.
 *
 * @param string     $state   As t21_world().
 * @param string     $variant As t21_world().
 * @param string     $surface The surface drawing, '' for none.
 * @param array|null $live    The surfaces on.
 * @return string
 */
function t21_manager( $state, $variant, $surface, $live ) {
	global $t21_rec;
	t21_world( $state, $variant );
	$GLOBALS['caps']      = true;
	$GLOBALS['member_of'] = array();
	$GLOBALS['uid']       = 9;
	$GLOBALS['is_admin']  = true;
	WPCPM_UI::set_live( $live );
	if ( '' !== $surface ) {
		WPCPM_UI::enter( $surface );
	}
	ob_start();
	try {
		WPCPM_Institution_Panel::render_manager_upload( $t21_rec );
	} finally {
		$html = (string) ob_get_clean();
		if ( '' !== $surface ) {
			WPCPM_UI::leave();
		}
		WPCPM_UI::set_live( null );
		$GLOBALS['is_admin'] = false;
	}
	return $html;
}
$t21_manager = array();
foreach ( array( 'none', 'submitted', 'returned', 'accepted', 'waiting' ) as $t21_state ) {
	foreach ( array( '', 'long', 'hostile' ) as $t21_variant ) {
		$t21_key = $t21_state . ( '' !== $t21_variant ? ', ' . $t21_variant : '' );
		$t21_manager[ $t21_key ] = array(
			md5( t21_manager( $t21_state, $t21_variant, '', null ) ),
			md5( t21_manager( $t21_state, $t21_variant, 'wpcpm-institutions/accounts', null ) ),
			md5( t21_manager( $t21_state, $t21_variant, 'wpcpm-institutions/accounts', array( 'administrator', 'institution' ) ) ),
		);
		if ( $t21_print ) {
			echo "\t'" . $t21_key . "' => '" . $t21_manager[ $t21_key ][0] . "',\n";
		}
	}
}
$t21_manager_base = array(
	'none' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'none, long' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'none, hostile' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'submitted' => 'fc70765205b792da50fef9c7ea59e462',
	'submitted, long' => '2b4b10f7daaaaf5a8b48c686ac10eeb3',
	'submitted, hostile' => 'db6d1e40483fc8a87ecde2925c51e247',
	'returned' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'returned, long' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'returned, hostile' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'accepted' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'accepted, long' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'accepted, hostile' => '0969a3937b5a354ff21def8c3d4cfc9d',
	'waiting' => '1b02e45a9db6b96fce57ce0a7e09a7ec',
	'waiting, long' => '42949d991f3796ebab6504cf14365cc1',
	'waiting, hostile' => '8fc4414b9d85e54a406d5913d6366a80',
);
ck( 'Task 21: the manager\'s upload in wp-admin prints the base\'s bytes with no surface, under Manage members, and under Manage members with the Institution Dashboard live (md5 at 1c7468a1)', $t21_manager, array_map( static function ( $digest ) {
	return array( $digest, $digest, $digest );
}, $t21_manager_base ) );

// The parts this task extends print the base's bytes without the new argument: real Administrator Dashboard calls.
$t21_parts_base = array(
	'button, quiet link'          => WPCPM_UI::button( array( 'label' => 'Open', 'kind' => 'quiet', 'href' => 'https://example.test/report/?a=1&b=2', 'context' => 'Spring <report>', 'side' => 'front' ) ),
	'button, secondary submit'    => WPCPM_UI::button( array( 'label' => 'Reinstate', 'kind' => 'secondary', 'confirm' => 'Put it "back"?', 'side' => 'admin' ) ),
	'button, primary link'        => WPCPM_UI::button( array( 'label' => 'Open the queue', 'href' => 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors', 'side' => 'front' ) ),
	'checklist'                   => WPCPM_UI::checklist( array( 'Read it <all>', 'Check the "name" & the date' ) ),
	'checklist, none'             => WPCPM_UI::checklist( array() ),
);
if ( $t21_print ) {
	foreach ( $t21_parts_base as $t21_key => $t21_html ) {
		echo "\t'" . $t21_key . "' => '" . md5( $t21_html ) . "',\n";
	}
}
$t21_md5 = static function ( $html ) {
	return md5( (string) $html );
};
ck( 'Task 21: button() and checklist() without the new arguments print the base\'s bytes (md5 at 1c7468a1)', array_map( $t21_md5, $t21_parts_base ), array(
	'button, quiet link'       => '7cbd6b49d711cc1fc6f81beafe492a3f',
	'button, secondary submit' => '05a4625beb01562407e4adbfb84df7c3',
	'button, primary link'     => 'a6e1479940980a0ade306a720690e2a2',
	'checklist'                => '1a7dcbaed0a9e47253448728c86eb158',
	'checklist, none'          => 'd41d8cd98f00b204e9800998ecf8427e',
) );

/* -- the parts' branch -- */

$t21_new = array();
foreach ( $t21_cases as $t21_name => $t21_case ) {
	$t21_new[ $t21_name ] = t21_parts( $t21_case[0], $t21_case[1], $t21_case[2], $t21_case[3] );
}

// The whole view on the real cards (Task 23, fix round 1): the panel and the agreement card as the Collaboration
// Agreement view draws them, every state, reader, variant and message, under the view's own head
// (`bin/stubs/view-faults.php`).
ck( 'the whole Collaboration Agreement view on the real panel and card, every case: one H1, the headings in order, no id twice, the lede once, no title again', array( wpcpm_view_faults_of( 'Collaboration Agreement', $t21_new ), count( array_filter( array_map( 'trim', $t21_new ) ) ) >= 80 ), array( array(), true ) );

// One name per page, one anchor: the view's block carries `wpcpm-agreement` in every state, settled or not; no head, no
// fold, no id twice. A reader the old branch drew nothing for is drawn nothing.
$t21_shape = array();
$t21_shape_expected = array();
foreach ( $t21_new as $t21_name => $t21_html ) {
	if ( $t21_old_pins[ $t21_name ][0] ) {
		$t21_shape[ $t21_name ]          = $t21_html;
		$t21_shape_expected[ $t21_name ] = '';
		continue;
	}
	$t21_x   = review_doc( $t21_html );
	$t21_ids = array();
	foreach ( $t21_x->query( '//*[@id]' ) as $t21_node ) {
		$t21_ids[] = $t21_node->getAttribute( 'id' );
	}
	$t21_shape[ $t21_name ]          = array(
		'anchor'  => $t21_x->query( '/html/body/div/section[@id="wpcpm-agreement"][@class="wpcpm-ui-block"]' )->length . ' block, ' . substr_count( $t21_html, 'id="wpcpm-agreement"' ) . ' id',
		// The block's own head: none (an in-view heading such as the on-file form's h3 sits inside its form).
		'heads'   => $t21_x->query( '/html/body/div/section/*[self::h1 or self::h2 or self::h3]' )->length,
		'folds'   => $t21_x->query( '//details' )->length,
		'repeats' => count( $t21_ids ) - count( array_unique( $t21_ids ) ),
		'blocks'  => $t21_x->query( '/html/body/div/*' )->length,
	);
	$t21_shape_expected[ $t21_name ] = array( 'anchor' => '1 block, 1 id', 'heads' => 0, 'folds' => 0, 'repeats' => 0, 'blocks' => 1 );
}
ck( 'Task 21: on the parts one block carries the anchor in every state, settled or not; no head, no fold, no id twice; nothing for a reader drawn nothing today', $t21_shape, $t21_shape_expected );

/**
 * The first line of the view's block: its tag (words and classes) and whether words follow it, or 'no tag'.
 *
 * @param string $html Markup.
 * @return string
 */
function t21_lead( $html ) {
	$x     = review_doc( $html );
	$first = $x->query( '//section/p[contains(concat(" ", @class, " "), " wpcpm-ui-intro ")]' )->item( 0 );
	if ( ! $first ) {
		return 'no line';
	}
	$tag = $first->firstChild;
	if ( ! $tag instanceof DOMElement || false === strpos( $tag->getAttribute( 'class' ), 'wpcpm-status' ) ) {
		return 'no tag';
	}
	return trim( $tag->textContent ) . ' | ' . $tag->getAttribute( 'class' ) . ' | ' . ( '' !== trim( substr( $first->textContent, strlen( $tag->textContent ) ) ) ? 'said in words' : 'tag alone' );
}
$t21_tags_expected = array(
	'none'      => 'no tag',
	'generated' => 'no tag',
	'submitted' => 'Waiting for review | wpcpm-status wpcpm-status--amber | said in words',
	'returned'  => 'Returned | wpcpm-status wpcpm-status--red | said in words',
	'revoked'   => 'Revoked | wpcpm-status wpcpm-status--red | said in words',
	'disagree'  => 'no tag',
	'accepted'  => 'Accepted | wpcpm-status wpcpm-status--green | said in words',
	'waiting'   => 'Accepted | wpcpm-status wpcpm-status--green | said in words',
	'on file'   => 'Accepted | wpcpm-status wpcpm-status--green | said in words',
);
$t21_tags = array();
$t21_tags_want = array();
foreach ( $t21_states as $t21_state ) {
	foreach ( array( 'representative', 'administrator' ) as $t21_reader ) {
		$t21_tags[ $t21_state . ', ' . $t21_reader ]      = t21_lead( $t21_new[ $t21_state . ', ' . $t21_reader ] );
		$t21_tags_want[ $t21_state . ', ' . $t21_reader ] = $t21_tags_expected[ $t21_state ];
	}
}
foreach ( array( 'accepted', 'waiting', 'on file' ) as $t21_state ) {
	$t21_tags[ $t21_state . ', tutor' ]      = t21_lead( $t21_new[ $t21_state . ', tutor' ] );
	$t21_tags_want[ $t21_state . ', tutor' ] = $t21_tags_expected[ $t21_state ];
}
ck( 'Task 21: a status tag leads, in words and in its tone: Accepted green, Waiting for review amber, Returned and Revoked red, none for none, generated and a base that disagrees; the state said in words beside it', $t21_tags, $t21_tags_want );

// The sentence of each state the school acts on is state_sentence(), as the Overview reads it.
$t21_said = array();
foreach ( array( 'none', 'generated', 'returned', 'revoked' ) as $t21_state ) {
	foreach ( array( 'representative', 'administrator' ) as $t21_reader ) {
		t21_world( $t21_state );
		$t21_sentence = WPCPM_Institution_Panel::state_sentence( $t21_state, WPCPM_Institution_Agreement::summary( $t21_rec ) );
		$t21_lines    = array();
		foreach ( review_doc( $t21_new[ $t21_state . ', ' . $t21_reader ] )->query( '//p' ) as $t21_p ) {
			$t21_lines[] = trim( (string) preg_replace( '/\s+/', ' ', $t21_p->textContent ) );
		}
		$t21_said[ $t21_state . ', ' . $t21_reader ] = '' !== $t21_sentence && in_array( $t21_sentence, $t21_lines, true );
	}
}
ck( 'Task 21: each state\'s sentence is a line of its own on the parts, equal to state_sentence()', $t21_said, array_fill_keys( array_keys( $t21_said ), true ) );

/**
 * What each form posts and how, read from the drawn markup, with no words but the question a browser asks.
 *
 * @param string $html Markup.
 * @return array[]
 */
function t21_forms( $html ) {
	$x   = review_doc( $html );
	$out = array();
	foreach ( $x->query( '//form' ) as $form ) {
		$pairs  = array();
		$checks = array();
		$signed = array();
		foreach ( $x->query( './/input|.//select|.//textarea', $form ) as $in ) {
			$name = $in->getAttribute( 'name' );
			if ( '' === $name ) {
				continue;
			}
			$tag  = strtolower( $in->nodeName );
			$type = 'input' === $tag ? strtolower( '' !== $in->getAttribute( 'type' ) ? $in->getAttribute( 'type' ) : 'text' ) : $tag;
			if ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
				$pair = $name . '=' . $in->getAttribute( 'value' ) . ( $in->hasAttribute( 'checked' ) ? ' (ticked)' : '' );
			} elseif ( 'select' === $type ) {
				$options = array();
				foreach ( $x->query( './/option', $in ) as $option ) {
					$options[] = $option->getAttribute( 'value' ) . ( $option->hasAttribute( 'selected' ) ? '*' : '' );
				}
				$pair = $name . '=[' . implode( ',', $options ) . ']';
			} elseif ( 'file' === $type ) {
				$pair = $name . '=(a file)';
			} else {
				$pair = $name . '=' . ( 'textarea' === $type ? trim( $in->textContent ) : $in->getAttribute( 'value' ) );
			}
			$pairs[] = $pair;
			if ( 'hidden' !== $type ) {
				$checks[] = $name . ' ' . $type
					. ( $in->hasAttribute( 'required' ) ? ' required' : '' )
					. ( $in->hasAttribute( 'maxlength' ) ? ' maxlength=' . $in->getAttribute( 'maxlength' ) : '' )
					. ( $in->hasAttribute( 'accept' ) ? ' accept=' . $in->getAttribute( 'accept' ) : '' )
					. ( $in->hasAttribute( 'placeholder' ) ? ' placeholder=' . $in->getAttribute( 'placeholder' ) : '' )
					. ( $in->hasAttribute( 'multiple' ) ? ' multiple' : '' );
			}
			if ( 'wpcpm_agreement_signed' === $name ) {
				$signed[] = $type;
			}
		}
		$submits = array();
		foreach ( $x->query( './/button|.//input[@type="submit"]', $form ) as $button ) {
			if ( 'button' === $button->getAttribute( 'type' ) ) {
				continue;
			}
			$submits[] = $button->getAttribute( 'name' ) . '=' . $button->getAttribute( 'value' ) . ' asks: ' . $button->getAttribute( 'data-wpcpm-confirm' );
		}
		sort( $pairs );
		sort( $checks );
		$action = $x->query( './/input[@name="action"]', $form )->item( 0 );
		$out[]  = array(
			'what'    => $action ? $action->getAttribute( 'value' ) : '?',
			'method'  => $form->getAttribute( 'method' ),
			'to'      => $form->getAttribute( 'action' ),
			'enctype' => $form->getAttribute( 'enctype' ),
			'once'    => $form->hasAttribute( 'data-wpcpm-once' ),
			'busy'    => $form->getAttribute( 'data-wpcpm-busy' ),
			'asks'    => $form->getAttribute( 'data-wpcpm-confirm' ),
			'posts'   => $pairs,
			'checks'  => $checks,
			'signed'  => $signed,
			'referer' => $x->query( './/input[@name="_wp_http_referer"]', $form )->length,
			'submits' => $submits,
		);
	}
	return $out;
}

/**
 * Every link, as where it goes and how it opens, in order.
 *
 * @param string $html Markup.
 * @return string[]
 */
function t21_links( $html ) {
	$out = array();
	foreach ( review_doc( $html )->query( '//a' ) as $a ) {
		if ( 0 === strpos( $a->getAttribute( 'href' ), '#' ) ) {
			continue;
		}
		$out[] = $a->getAttribute( 'href' ) . ' rel=' . $a->getAttribute( 'rel' ) . ' target=' . $a->getAttribute( 'target' );
	}
	// In no order: where a link stands is the layout's, where it goes and how it opens are the behavior.
	sort( $out );
	return $out;
}

// What the old branch posted, linked to and asked in every case, read from the classes at db850661 (the last commit
// that drew it) before it was deleted (Task 23, fix round 1): per case, md5( serialize( t21_forms() ) ) of the old
// drawing with `faithful_nonces` on, md5( serialize( t21_links() ) ), and md5( serialize( array( first, sorted ) ) )
// of the policy log on the old branch (`set_live( array( 'administrator' ) )`, `enter( 'institution' )`). To
// reproduce: this suite with its normalisation, run against `git archive db850661`, each value computed as named.
$t21_old_posts = array(
	'none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'none, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'none, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'generated, representative' => array( '757d5c5a41d9ad144b3b97852fdf1435', '40cd750bba9870f18aada2478b24840a', '76f70d4724cec7143af5a1f8fd5ea3fe' ),
	'generated, administrator' => array( 'eb22a7c267fb70f28a7e3622a9c0293e', '40cd750bba9870f18aada2478b24840a', '76f70d4724cec7143af5a1f8fd5ea3fe' ),
	'generated, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'generated, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'submitted, representative' => array( '606cb3ca7848b1c896a7a46758219752', '6bb26b2e71937f11843a9aadaf234139', '7be0a9c078b62b2c5a29426017e63737' ),
	'submitted, administrator' => array( 'd01faf6b1ae58199340876bb753d5dbb', '6bb26b2e71937f11843a9aadaf234139', '7be0a9c078b62b2c5a29426017e63737' ),
	'submitted, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'submitted, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'returned, administrator' => array( 'e0c0b2dc70a515b6d4c54c26c8b388bd', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'returned, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'returned, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'revoked, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'revoked, administrator' => array( 'e0c0b2dc70a515b6d4c54c26c8b388bd', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'revoked, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'revoked, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'disagree, representative' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'disagree, administrator' => array( '6ec48fcf9172593d0a9f5d0e710ed7d9', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'disagree, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'disagree, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'accepted, administrator' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'accepted, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', '11ffef8a79f3b027a5f5660a495caf88' ),
	'accepted, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'waiting, representative' => array( 'cb2ae2c8fb1b633c6846fb481ce4f2fb', '4b5d31054bfd4a5a127ae00384f4b4d1', '323f6decd95c9475c2a3883a8aa289d5' ),
	'waiting, administrator' => array( '8bce58da5bfcc2890746513d90de3e89', '4b5d31054bfd4a5a127ae00384f4b4d1', '323f6decd95c9475c2a3883a8aa289d5' ),
	'waiting, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', '323f6decd95c9475c2a3883a8aa289d5' ),
	'waiting, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'on file, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator' => array( '753023273a18b755cdbd678a642018be', '4df05e5169a72e387626ab53324b26a4', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, tutor' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'on file, stranger' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'none, representative, long' => array( 'f0941359750906204a1ad6394b8f882d', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'none, administrator, long' => array( 'e25fee6130b269f518d29e82761a5767', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'generated, representative, long' => array( '110aa02a3a17c9cebef25d4364444f89', '40cd750bba9870f18aada2478b24840a', '76f70d4724cec7143af5a1f8fd5ea3fe' ),
	'generated, administrator, long' => array( '4347a3f6a58c32d02ce801b30da677eb', '40cd750bba9870f18aada2478b24840a', '76f70d4724cec7143af5a1f8fd5ea3fe' ),
	'submitted, representative, long' => array( '606cb3ca7848b1c896a7a46758219752', '6bb26b2e71937f11843a9aadaf234139', '7be0a9c078b62b2c5a29426017e63737' ),
	'submitted, administrator, long' => array( '88d05b1cd5839ff652f1ca8e5439004d', '6bb26b2e71937f11843a9aadaf234139', '7be0a9c078b62b2c5a29426017e63737' ),
	'returned, representative, long' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'returned, administrator, long' => array( '1d7dce88f2c84c2b4c1abc25fd8ceb0e', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'revoked, representative, long' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'revoked, administrator, long' => array( '1d7dce88f2c84c2b4c1abc25fd8ceb0e', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'disagree, representative, long' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'disagree, administrator, long' => array( '12c26bfd79df6e377d05160bdaf0d788', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'accepted, representative, long' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'accepted, administrator, long' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'waiting, representative, long' => array( 'cb2ae2c8fb1b633c6846fb481ce4f2fb', '4b5d31054bfd4a5a127ae00384f4b4d1', '323f6decd95c9475c2a3883a8aa289d5' ),
	'waiting, administrator, long' => array( '86c82c08b9a7b6fedc610bc6808b3d15', '4b5d31054bfd4a5a127ae00384f4b4d1', '323f6decd95c9475c2a3883a8aa289d5' ),
	'on file, representative, long' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator, long' => array( '753023273a18b755cdbd678a642018be', '4df05e5169a72e387626ab53324b26a4', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'none, representative, hostile' => array( '9bef7f34cbf93c9ffae84d86522e7f73', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'none, administrator, hostile' => array( '88a97544337f1bbf3c6fe9591bc9f667', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'generated, representative, hostile' => array( 'aeabcf783b42f08c92c45ab882f394dd', '40cd750bba9870f18aada2478b24840a', '76f70d4724cec7143af5a1f8fd5ea3fe' ),
	'generated, administrator, hostile' => array( 'f21ee7955b4fe591898b4db9372b40bc', '40cd750bba9870f18aada2478b24840a', '76f70d4724cec7143af5a1f8fd5ea3fe' ),
	'submitted, representative, hostile' => array( '606cb3ca7848b1c896a7a46758219752', '6bb26b2e71937f11843a9aadaf234139', '7be0a9c078b62b2c5a29426017e63737' ),
	'submitted, administrator, hostile' => array( '1eea93827bedbb4d92a58196dd73c3f0', '6bb26b2e71937f11843a9aadaf234139', '7be0a9c078b62b2c5a29426017e63737' ),
	'returned, representative, hostile' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'returned, administrator, hostile' => array( '39e1fe01cd533827d92cdda6f5fbc7e7', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'revoked, representative, hostile' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'revoked, administrator, hostile' => array( '39e1fe01cd533827d92cdda6f5fbc7e7', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'disagree, representative, hostile' => array( '40cd750bba9870f18aada2478b24840a', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'disagree, administrator, hostile' => array( '5a3bddbbdd7dad983758a8d704dd43ca', '40cd750bba9870f18aada2478b24840a', 'fb59c11fabc977d3be81bed7c101fb9a' ),
	'accepted, representative, hostile' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'accepted, administrator, hostile' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'waiting, representative, hostile' => array( 'cb2ae2c8fb1b633c6846fb481ce4f2fb', '4b5d31054bfd4a5a127ae00384f4b4d1', '323f6decd95c9475c2a3883a8aa289d5' ),
	'waiting, administrator, hostile' => array( '6f85e383ffc5f517b7ff654af6f48631', '4b5d31054bfd4a5a127ae00384f4b4d1', '323f6decd95c9475c2a3883a8aa289d5' ),
	'on file, representative, hostile' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator, hostile' => array( '753023273a18b755cdbd678a642018be', '4df05e5169a72e387626ab53324b26a4', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'generated, representative, two languages' => array( 'd1da26712274a395e492327ff471e353', '40cd750bba9870f18aada2478b24840a', '76f70d4724cec7143af5a1f8fd5ea3fe' ),
	'on file, representative, drive hostile' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator, drive hostile' => array( '753023273a18b755cdbd678a642018be', '945479aa00e8b29aec3ecb3b920fc9f5', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, representative, drive tag' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator, drive tag' => array( '753023273a18b755cdbd678a642018be', 'c9088ed23a0d67658ea31d49e0f9ee50', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, representative, drive javascript' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator, drive javascript' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, representative, drive data' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator, drive data' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, representative, drive http' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'on file, administrator, drive http' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'accepted, administrator, with drive' => array( '753023273a18b755cdbd678a642018be', '329dc0c77d7760ffff153ea0716c57a3', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'accepted, representative, undated' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-on-file, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-link, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-standing, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-unknown, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-busy, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-revoked, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-revoke-note, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-revoke-loss, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-not-accepted, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-not-revoked, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-reinstate-standing, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-reinstated, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-airtable, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-not-saved, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-later, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-uploaded, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-too-big, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-not-pdf, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-no-file, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-file, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-gone, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-encrypted, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-launch, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-in-review, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-declare, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-kind, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-accepted, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-returned, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-withdrawn, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-note, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-return-loss, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-stage, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-generated-later, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-name, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-name-brackets, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-generate-in-review, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-generate-standing, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-generate-not-saved, none, administrator' => array( 'cd940da686b38311872e82dd1a8ee1f6', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-not-pdf, none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-not-pdf, accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-not-pdf, returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'message agreement-declare, none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-declare, accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-declare, returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'message agreement-kind, none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-kind, accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-kind, returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'message agreement-name, none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-name, accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-name, returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'message agreement-name-brackets, none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-name-brackets, accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-name-brackets, returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'message agreement-too-big, none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-too-big, accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-too-big, returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'message agreement-uploaded, none, representative' => array( '0bcf6080aedf0874ce3a9be74cdb2abc', '40cd750bba9870f18aada2478b24840a', '689cfa6ca0a0c622f04c050de9d1079e' ),
	'message agreement-uploaded, accepted, representative' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
	'message agreement-uploaded, returned, representative' => array( '753023273a18b755cdbd678a642018be', '40cd750bba9870f18aada2478b24840a', 'd82b2c61e9ab2d25ea473d813d02e809' ),
	'message agreement-on-file, accepted, administrator' => array( '753023273a18b755cdbd678a642018be', '6bb26b2e71937f11843a9aadaf234139', '3f14ad4cbe69e2182c7cf357d9efae07' ),
);

// Rule 2: every form posts what the old branch posted (method, action, enctype, the once guard and its busy words,
// every name and value, the nonce and the referer as WordPress prints them, the submit's name and value, the browser's
// question); rule 3: the same links, opening as they did. The parts held to the old branch's pins, in every case.
$GLOBALS['faithful_nonces'] = true;
$t21_posts_differ = array();
$t21_links_differ = array();
$t21_actions      = array();
foreach ( $t21_cases as $t21_name => $t21_case ) {
	$t21_now = t21_parts( $t21_case[0], $t21_case[1], $t21_case[2], $t21_case[3] );
	if ( ! isset( $t21_old_posts[ $t21_name ] ) || md5( serialize( t21_forms( $t21_now ) ) ) !== $t21_old_posts[ $t21_name ][0] ) {
		$t21_posts_differ[ $t21_name ] = t21_forms( $t21_now );
	}
	if ( ! isset( $t21_old_posts[ $t21_name ] ) || md5( serialize( t21_links( $t21_now ) ) ) !== $t21_old_posts[ $t21_name ][1] ) {
		$t21_links_differ[ $t21_name ] = t21_links( $t21_now );
	}
	if ( '' === $t21_case[2] && '' === $t21_case[3] ) {
		$t21_actions[ $t21_name ] = implode( ', ', array_merge( array_map( static function ( $form ) {
			return $form['what'];
		}, t21_forms( $t21_now ) ), array_map( static function ( $link ) {
			return false !== strpos( $link, 'drive.google.com' ) ? 'Drive' : 'download';
		}, t21_links( $t21_now ) ) ) );
	}
}
$t21_referers = array();
foreach ( $t21_cases as $t21_name => $t21_case ) {
	foreach ( t21_forms( t21_parts( $t21_case[0], $t21_case[1], $t21_case[2], $t21_case[3] ) ) as $t21_form ) {
		$t21_referers[] = $t21_form['referer'];
	}
}
$GLOBALS['faithful_nonces'] = false;
ck( 'Task 21: every form posts on the parts exactly what the old branch posted, in every state, for every reader and every outcome', array( $t21_posts_differ, count( $t21_old_posts ), count( $t21_cases ) ), array( array(), 145, 145 ) );
ck( 'Task 21: and every link goes where the old branch\'s went and opens as it did', $t21_links_differ, array() );
ck( 'Task 21: each parts form carries the referer once', array( count( $t21_referers ) > 0, array_unique( $t21_referers ) ), array( true, array( 1 ) ) );
ck( 'Task 21: the parts\' nonce carries no id', substr_count( implode( '', $t21_new ), 'id="_wpnonce"' ), 0 );
if ( $t21_print ) {
	var_export( $t21_actions );
}
// Who may do what, per reader (rule 3, ruling 7), as the parts draw it, which the two checks above hold equal to today.
ck( 'Task 21: what each reader is offered on the parts, state by state', $t21_actions, array(
	'none, representative' => 'wpcpm_agreement_generate, wpcpm_agreement_upload',
	'none, administrator' => 'wpcpm_agreement_generate, wpcpm_agreement_upload, wpcpm_agreement_on_file',
	'none, tutor' => '',
	'none, stranger' => '',
	'generated, representative' => 'wpcpm_agreement_generate, wpcpm_agreement_generate, wpcpm_agreement_upload',
	'generated, administrator' => 'wpcpm_agreement_generate, wpcpm_agreement_generate, wpcpm_agreement_upload, wpcpm_agreement_on_file',
	'generated, tutor' => '',
	'generated, stranger' => '',
	'submitted, representative' => 'wpcpm_agreement_withdraw, download',
	'submitted, administrator' => 'wpcpm_agreement_withdraw, wpcpm_agreement_on_file, download',
	'submitted, tutor' => '',
	'submitted, stranger' => '',
	'returned, representative' => 'wpcpm_agreement_upload',
	'returned, administrator' => 'wpcpm_agreement_upload, wpcpm_agreement_on_file',
	'returned, tutor' => '',
	'returned, stranger' => '',
	'revoked, representative' => 'wpcpm_agreement_upload',
	'revoked, administrator' => 'wpcpm_agreement_upload, wpcpm_agreement_on_file',
	'revoked, tutor' => '',
	'revoked, stranger' => '',
	'disagree, representative' => '',
	'disagree, administrator' => 'wpcpm_agreement_on_file',
	'disagree, tutor' => '',
	'disagree, stranger' => '',
	'accepted, representative' => 'wpcpm_agreement_upload, download',
	'accepted, administrator' => 'wpcpm_agreement_upload, download',
	'accepted, tutor' => '',
	'accepted, stranger' => '',
	'waiting, representative' => 'wpcpm_agreement_withdraw, download, download',
	'waiting, administrator' => 'wpcpm_agreement_withdraw, download, download',
	'waiting, tutor' => '',
	'waiting, stranger' => '',
	'on file, representative' => 'wpcpm_agreement_upload',
	'on file, administrator' => 'wpcpm_agreement_upload, Drive',
	'on file, tutor' => '',
	'on file, stranger' => '',
) );

/**
 * The words a drawing shows, each once, in no order; screen-reader text left out (it repeats a row's name).
 *
 * @param string $html Markup.
 * @return string[]
 */
function t21_words( $html ) {
	// A break opportunity in a long word is no word of its own.
	$x = review_doc( str_replace( '<wbr>', '', (string) $html ) );
	foreach ( iterator_to_array( $x->query( '//*[contains(concat(" ", @class, " "), " screen-reader-text ")]' ) ) as $node ) {
		$node->parentNode->removeChild( $node );
	}
	$text = array();
	foreach ( $x->query( '//text()' ) as $node ) {
		$text[] = $node->nodeValue;
	}
	preg_match_all( '/[\p{L}\p{N}]+(?:\'[\p{L}]+)?/u', implode( ' ', $text ), $m );
	$words = array_values( array_unique( $m[0] ) );
	sort( $words );
	return $words;
}
$t21_word_diff = array();
foreach ( $t21_cases as $t21_name => $t21_case ) {
	$t21_a = t21_old_words( $t21_name, $t21_new[ $t21_name ] );
	$t21_b = t21_words( $t21_new[ $t21_name ] );
	$t21_d = array( 'gone' => array_values( array_diff( $t21_a, $t21_b ) ), 'new' => array_values( array_diff( $t21_b, $t21_a ) ) );
	if ( array() !== $t21_d['gone'] || array() !== $t21_d['new'] ) {
		$t21_word_diff[ $t21_name ] = implode( ' ', $t21_d['gone'] ) . ' | ' . implode( ' ', $t21_d['new'] );
	}
}
if ( $t21_print ) {
	var_export( $t21_word_diff );
}
// The same facts on both branches: a word the old branch shows and the parts do not is only the old head's (the view's
// head says "Collaboration Agreement"), the generate box's label and the on-file boxes' "(optional)" and "(required)"
// (their need is said in words); a word only the parts show is a need word, the file box's own ("Choose a file", "No
// file chosen"), the generate box's hint, and a tag's words. The return note's refusal reads "What has to change" on
// the parts (`return_label()`, the base's own behavior with a parts switch on).
// Fix round 1: "on" from "Accepted on", which the parts say as the tag and the day; the banner's note is the view's on
// both branches (the old branch prints it above the block), so it is left out of the parts' words here.
$t21_may_go  = array( 'Agreement', 'Collaboration', 'exactly', 'on', 'optional', 'required' );
$t21_may_add = array( 'Choose', 'Exactly', 'No', 'Optional', 'Required', 'Returned', 'Revoked', 'Waiting', 'chosen', 'file', 'for' );
$t21_beyond  = array();
foreach ( $t21_cases as $t21_name => $t21_case ) {
	$t21_a = t21_old_words( $t21_name, $t21_new[ $t21_name ] );
	$t21_b = t21_words( (string) preg_replace( '#<p class="wpcpm-ui-note">This institution&\#039;s agreement is not settled.*?</p>#s', '', $t21_new[ $t21_name ] ) );
	$t21_g = array_values( array_diff( array_diff( $t21_a, $t21_b ), $t21_may_go ) );
	$t21_n = array_values( array_diff( array_diff( $t21_b, $t21_a ), $t21_may_add ) );
	if ( array() !== $t21_g || array() !== $t21_n ) {
		$t21_beyond[ $t21_name ] = implode( ' ', $t21_g ) . ' | ' . implode( ' ', $t21_n );
	}
}
ck( 'Task 21: the same facts on both branches: the words differ only where a label is reworded, a need is said in words, the file box speaks and a tag leads', $t21_beyond, array(
	'message agreement-return-loss, none, administrator' => 'words | ',
) );
ck( 'Task 21: and in the plainest states exactly so', array(
	$t21_word_diff['none, representative'] ?? '',
	$t21_word_diff['submitted, representative'] ?? '',
	$t21_word_diff['returned, representative'] ?? '',
	$t21_word_diff['accepted, tutor'] ?? '',
	$t21_word_diff['on file, representative'] ?? '',
), array(
	'exactly | Choose Exactly No Required chosen file',
	'Agreement Collaboration | Waiting for',
	'Agreement Collaboration | Choose No Required Returned chosen file',
	'Agreement Collaboration on | ',
	'Agreement Collaboration on | Choose No Required chosen file',
) );

// A refused upload is tied to the box it refused: the box described by the sentence, the card's message a link to it.
/**
 * How a refusal is tied, in one drawing: where the message leads, the box it names (marked invalid, described by
 * the sentence), the sentence under it, and which kind of agreement is ticked.
 *
 * @param string $html Markup.
 * @return array
 */
function t21_tie( $html ) {
	$x    = review_doc( $html );
	$lead = $x->query( '//p[@data-wpcpm-card-message]//a' )->item( 0 );
	$to   = $lead ? substr( $lead->getAttribute( 'href' ), 1 ) : '';
	$box  = '' !== $to ? $x->query( '//*[@id="' . $to . '"]' ) : null;
	$node = $box && 1 === $box->length ? $box->item( 0 ) : null;
	// A radio's refusal is its group's: the fieldset is described by the sentence, which sits under the options.
	$owner = $node;
	$named = WPCPM_Form_Errors::problem_id( $to );
	if ( $node && 'radio' === $node->getAttribute( 'type' ) ) {
		$owner = $x->query( 'ancestor::fieldset', $node )->item( 0 );
		$named = $owner ? WPCPM_Form_Errors::problem_id( substr( $to, 0, (int) strrpos( $to, '-' ) ) ) : '';
	}
	$said = $node ? $x->query( '//*[@id="' . $named . '"]' )->item( 0 ) : null;
	$kind = $x->query( '//input[@name="wpcpm_agreement_kind"][@checked]' )->item( 0 );
	return array(
		'leads to' => $to,
		'boxes'    => $box ? $box->length : 0,
		'invalid'  => $node ? $node->getAttribute( 'aria-invalid' ) : '',
		'named by' => $owner && false !== strpos( ' ' . $owner->getAttribute( 'aria-describedby' ) . ' ', ' ' . $named . ' ' ),
		'sentence' => $said ? trim( $said->textContent ) : '',
		'ticked'   => $kind ? $kind->getAttribute( 'value' ) : '',
	);
}
$t21_msgs = WPCPM_Institution_Panel::messages();
$t21_up   = 'wpcpm-upload-' . $t21_rec;
$t21_ties = array();
foreach ( array(
	'message agreement-not-pdf, none, representative'     => $t21_up . '-file',
	'message agreement-too-big, none, representative'     => $t21_up . '-file',
	'message agreement-declare, none, representative'     => $t21_up . '-signed',
	'message agreement-kind, none, representative'        => $t21_up . '-kind-template',
	'message agreement-not-pdf, accepted, representative' => $t21_up . '-file',
	'message agreement-not-pdf, returned, representative' => $t21_up . '-file',
	'message agreement-kind, returned, representative'    => $t21_up . '-kind-template',
	'message agreement-name, none, representative'        => 'wpcpm-generate-' . $t21_rec . '-name',
	'message agreement-name-brackets, none, representative' => 'wpcpm-generate-' . $t21_rec . '-name',
	'message agreement-link, none, administrator'         => 'wpcpm-on-file-' . $t21_rec . '-drive',
	'message agreement-no-file, none, administrator'      => $t21_up . '-file',
) as $t21_name => $t21_to ) {
	$t21_slug = (string) preg_replace( '/^message (\S+),.*$/', '$1', $t21_name );
	$t21_ties[ $t21_name ] = array( t21_tie( $t21_new[ $t21_name ] ), array(
		'leads to' => $t21_to,
		'boxes'    => 1,
		// A group carries no aria-invalid; its fieldset is described by the sentence.
		'invalid'  => false !== strpos( $t21_to, '-kind-' ) ? '' : 'true',
		'named by' => true,
		'sentence' => $t21_msgs[ $t21_slug ][1],
		'ticked'   => 'template',
	) );
}
ck( 'Task 21: a refusal leads to the box it refused, the box is marked invalid and described by the sentence printed under it, and the kind stays ticked', array_map( static function ( $pair ) {
	return $pair[0];
}, $t21_ties ), array_map( static function ( $pair ) {
	return $pair[1];
}, $t21_ties ) );
// Every message that leads anywhere leads to one element on its page; an outcome with no box of its own leads nowhere.
$t21_dead = array();
foreach ( $t21_new as $t21_name => $t21_html ) {
	$t21_x = review_doc( $t21_html );
	foreach ( $t21_x->query( '//p[@data-wpcpm-card-message]//a' ) as $t21_a ) {
		if ( 1 !== $t21_x->query( '//*[@id="' . substr( $t21_a->getAttribute( 'href' ), 1 ) . '"]' )->length ) {
			$t21_dead[] = $t21_name;
		}
	}
}
ck( 'Task 21: no message leads to a box its page does not hold', $t21_dead, array() );
ck( 'Task 21: an outcome that names no box (busy, in review, uploaded) is the sentence alone', array(
	review_doc( $t21_new['message agreement-busy, none, administrator'] )->query( '//p[@data-wpcpm-card-message]//a' )->length,
	review_doc( $t21_new['message agreement-in-review, none, administrator'] )->query( '//p[@data-wpcpm-card-message]//a' )->length,
	review_doc( $t21_new['message agreement-uploaded, none, representative'] )->query( '//p[@data-wpcpm-card-message]//a' )->length,
), array( 0, 0, 0 ) );

// Rule 6: every outcome through the shell's message, the module's own class, the block's first line; one per page. The
// words are messages() as the parts read it (the return note's box is named as the parts name it).
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
WPCPM_UI::enter( 'institution' );
$t21_msgs_on = WPCPM_Institution_Panel::messages();
WPCPM_UI::leave();
WPCPM_UI::set_live( null );
$t21_messages = array();
foreach ( $t21_new as $t21_name => $t21_html ) {
	if ( 0 !== strpos( $t21_name, 'message ' ) ) {
		continue;
	}
	$t21_x    = review_doc( $t21_html );
	$t21_said = $t21_x->query( '//p[@data-wpcpm-card-message]' );
	$t21_one  = $t21_said->item( 0 );
	$t21_slug = (string) preg_replace( '/^message (\S+),.*$/', '$1', $t21_name );
	// Gate B round 2, F2: a refusal of a form the block draws is said in that form, directly over its first field (the
	// next element a field or a choice group); every other outcome is the block's first line.
	$t21_next = $t21_one ? $t21_one->nextSibling : null;
	while ( $t21_next && XML_ELEMENT_NODE !== $t21_next->nodeType ) {
		$t21_next = $t21_next->nextSibling;
	}
	$t21_messages[ $t21_name ] = $t21_one ? array(
		$t21_said->length,
		$t21_one->getAttribute( 'class' ),
		'form' === $t21_one->parentNode->nodeName ? 'form' : $t21_one->parentNode->getAttribute( 'id' ),
		'form' === $t21_one->parentNode->nodeName
			? ( $t21_next && 1 === preg_match( '/\bwpcpm-ui-(field|choices)\b/', $t21_next->getAttribute( 'class' ) ) )
			: $t21_one === $t21_x->query( '//section/*' )->item( 0 ),
		trim( $t21_one->textContent ) === $t21_msgs_on[ $t21_slug ][1],
	) : 'no message';
}
$t21_at_form = array( 'agreement-too-big', 'agreement-not-pdf', 'agreement-no-file', 'agreement-file', 'agreement-encrypted', 'agreement-launch', 'agreement-in-review', 'agreement-declare', 'agreement-kind', 'agreement-name', 'agreement-name-brackets', 'agreement-generate-in-review', 'agreement-generate-standing', 'agreement-generate-not-saved', 'agreement-link', 'agreement-standing', 'agreement-unknown' );
// The generate form is drawn only while no agreement stands or waits: elsewhere its refusal opens the block.
ck( 'Task 21: every outcome is the shell\'s message, in its tone, in today\'s words: the block\'s first line, or a refusal of a form the block draws directly over that form\'s first field', $t21_messages, array_map( static function ( $name ) use ( $t21_msgs, $t21_at_form, $t21_new ) {
	$slug  = (string) preg_replace( '/^message (\S+),.*$/', '$1', $name );
	$tone  = $t21_msgs[ $slug ][0];
	$drawn = 0 !== strpos( $slug, 'agreement-name' ) || false !== strpos( $t21_new[ $name ], 'name="wpcpm_agreement_name"' );
	return array( 1, 'wpcpm-agreement-panel__message wpcpm-agreement-panel__message--' . $tone . ' is-' . $tone, in_array( $slug, $t21_at_form, true ) && $drawn ? 'form' : 'wpcpm-agreement', true, true );
}, array_combine( array_keys( $t21_messages ), array_keys( $t21_messages ) ) ) );

// Ruling 5: the file box is WPCPM_UI::file(), its control named once, by its label alone.
$t21_x    = review_doc( $t21_new['none, representative'] );
$t21_file = $t21_x->query( '//input[@type="file"]' )->item( 0 );
ck( 'Task 21: the file box is the parts\' own, its control named once: one label for it, no aria-label, no aria-labelledby; the button only points at it', $t21_file ? array(
	$t21_x->query( 'ancestor::div[1]', $t21_file )->item( 0 )->getAttribute( 'class' ),
	$t21_file->getAttribute( 'name' ),
	$t21_file->getAttribute( 'accept' ),
	$t21_file->hasAttribute( 'required' ),
	$t21_x->query( '//label[@for="' . $t21_file->getAttribute( 'id' ) . '"]' )->length,
	$t21_x->query( 'ancestor::label', $t21_file )->length,
	$t21_file->hasAttribute( 'aria-label' ) || $t21_file->hasAttribute( 'aria-labelledby' ),
	trim( $t21_x->query( '//label[@for="' . $t21_file->getAttribute( 'id' ) . '"]' )->item( 0 )->textContent ),
	$t21_x->query( '//button[@aria-controls="' . $t21_file->getAttribute( 'id' ) . '"][not(@aria-label)]' )->length,
) : 'no file box', array( 'wpcpm-ui-field wpcpm-ui-file', 'wpcpm_agreement_file', 'application/pdf,.pdf', true, 1, 0, false, 'The signed agreement, as a PDF of up to 10 MB Required', 1 ) );

// Ruling 8: dates whole, a person's quoted words as a quotation, names and addresses breakable.
$t21_wholes = array();
foreach ( array( 'generated, representative', 'submitted, representative', 'returned, representative', 'revoked, administrator', 'accepted, representative', 'on file, administrator' ) as $t21_name ) {
	$t21_wholes[ $t21_name ] = array_map( static function ( $node ) {
		return $node->textContent;
	}, iterator_to_array( review_doc( $t21_new[ $t21_name ] )->query( '//span[@class="wpcpm-ui-whole"]' ) ) );
}
t21_world( 'on file' );
$t21_on_file_day = WPCPM_Dates::day( (string) WPCPM_Institution_Agreement::summary( $t21_rec )['accepted_at'] );
t21_world( 'accepted' );
$t21_accepted_day = WPCPM_Dates::day( (string) WPCPM_Institution_Agreement::summary( $t21_rec )['accepted_at'] );
ck( 'Task 21: every day is whole: the template\'s, the copy\'s arrival, the note\'s, the acceptance, the read time', $t21_wholes, array(
	'generated, representative' => array( 'September 1, 2025', 'August 31, 2025 12:26 am' ),
	'submitted, representative' => array( 'September 1, 2025', 'August 31, 2025 12:26 am' ),
	'returned, representative'  => array( 'August 30, 2026', 'August 31, 2025 12:26 am' ),
	'revoked, administrator'    => array( 'August 30, 2026', 'August 31, 2025 12:26 am' ),
	'accepted, representative'  => array( $t21_accepted_day ),
	'on file, administrator'    => array( $t21_on_file_day ),
) );
$t21_x = review_doc( $t21_new['returned, representative, long'] );
ck( 'Task 21: the note an Administrator left is a quotation (WPCPM_UI::note()), verbatim, and who left it and when is its own line, the name plain words the sheet breaks (no `<wbr>`, Task 22b)', array(
	$t21_x->query( '//blockquote[@class="wpcpm-ui-note"]' )->length,
	$t21_x->query( '//blockquote[@class="wpcpm-ui-note"]' )->item( 0 ) ? $t21_x->query( '//blockquote[@class="wpcpm-ui-note"]' )->item( 0 )->textContent : '',
	substr_count( implode( '', $t21_new ), '<wbr>' ),
	substr_count( $t21_new['returned, representative, long'], 'Ada Exampleinstituteforappliedwebengineerings' ),
), array( 1, 'Page 4 needs ' . $t21_long . ' signed.', 0, 1 ) );

// Rule 10: hostile words escaped once on both branches, no raw tag, no broken attribute.
$t21_hostile_found = array();
foreach ( $t21_cases as $t21_name => $t21_case ) {
	if ( 'hostile' !== $t21_case[3] ) {
		continue;
	}
	foreach ( array( 'new' => $t21_new[ $t21_name ] ) as $t21_branch => $t21_html ) {
		$t21_hostile_found[ $t21_name . ', ' . $t21_branch ] = array(
			false !== stripos( $t21_html, '<script' ),
			false !== strpos( $t21_html, '&amp;amp;' ) || false !== strpos( $t21_html, '&amp;lt;' ) || false !== strpos( $t21_html, '&amp;quot;' ),
		);
	}
}
ck( 'Task 21: a tag, a quote and an ampersand in the name, the note and the Administrator\'s name are escaped once on both branches', $t21_hostile_found, array_fill_keys( array_keys( $t21_hostile_found ), array( false, false ) ) );

// Rule 3: the policy is asked the questions the old branch asked, in every case (the order of two forms' questions
// aside), and before anything is printed: the panel and the card each decide first. The old branch's log is the pin
// read at db850661 (`$t21_old_posts`, its third value).
$t21_asked = array();
foreach ( $t21_cases as $t21_name => $t21_case ) {
	$t21_logs = array();
	foreach ( array( 'new' => array( 'administrator', 'institution' ) ) as $t21_branch => $t21_on ) {
		t21_world( $t21_case[0], $t21_case[3] );
		$GLOBALS['decisions'] = array();
		$GLOBALS['caps']      = 'administrator' === $t21_case[1];
		$GLOBALS['tutor']     = 'tutor' === $t21_case[1];
		$GLOBALS['member_of'] = 'administrator' === $t21_case[1] ? array() : array( 'stranger' === $t21_case[1] ? $rec_b : $t21_rec );
		WPCPM_UI::set_live( $t21_on );
		WPCPM_UI::enter( 'institution' );
		ob_start();
		WPCPM_Institution_Panel::render( $t21_rec, array( 'can_manage' => 'administrator' === $t21_case[1] ) );
		WPCPM_Institution_Agreement_Card::render( $t21_rec, array( 'can_manage' => 'administrator' === $t21_case[1] ) );
		ob_end_clean();
		WPCPM_UI::leave();
		WPCPM_UI::set_live( null );
		$GLOBALS['tutor'] = false;
		$t21_log          = array_map( static function ( $call ) {
			return $call[0] . ' ' . $call[1];
		}, $GLOBALS['decisions'] );
		$t21_first        = isset( $t21_log[0] ) ? $t21_log[0] : '';
		sort( $t21_log );
		$t21_logs[ $t21_branch ] = array( $t21_first, $t21_log );
	}
	if ( md5( serialize( $t21_logs['new'] ) ) !== $t21_old_posts[ $t21_name ][2] ) {
		$t21_asked[ $t21_name ] = $t21_logs['new'];
	}
}
ck( 'Task 21: the policy is asked the questions the old branch asked, the panel\'s and the card\'s first, in every case', $t21_asked, array() );

// Rule 4: a rule both branches need lives in one helper each calls (read from the source): who may record, download,
// withdraw and see the Drive link; which fields the forms post; the sentences.
$t21_src  = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php' );
// A name with `card:` before it is the card's method; any other, the panel's.
$t21_body = static function ( $name ) use ( $t21_src ) {
	$at   = (int) strpos( $t21_src, 'class WPCPM_Institution_Agreement_Card' );
	$from = 0 === strpos( $name, 'card:' ) ? substr( $t21_src, $at ) : substr( $t21_src, 0, $at );
	$name = (string) preg_replace( '/^card:/', '', $name );
	if ( ! preg_match( '/function ' . preg_quote( $name, '/' ) . '\s*\(.*?\n\t}\n/s', $from, $m ) ) {
		return '';
	}
	return $m[0];
};
$t21_calls = array();
foreach ( array(
	'may_record'        => array( 'render_on_file_form', 'on_file_form_parts' ),
	'may_download'      => array( 'render_download_link', 'download_button' ),
	'post_decision'     => array( 'render_withdraw_form', 'withdraw_form_parts' ),
	'on_manager_ground' => array( 'render_withdraw_form', 'withdraw_form_parts' ),
	'withdraw_label'    => array( 'render_withdraw_form', 'withdraw_form_parts' ),
	'withdraw_confirm'  => array( 'render_withdraw_form', 'withdraw_form_parts' ),
	'form_url'          => array( 'form_start', 'form_open_parts' ),
	'generate_name'     => array( 'render_generate_form', 'generate_form_parts' ),
	'language_choices'  => array( 'render_generate_form', 'generate_form_parts' ),
	'regenerate_fields' => array( 'render_regenerate_form', 'regenerate_form_parts' ),
	'upload_base'       => array( 'render_upload_form', 'upload_form_parts' ),
	'file_label'        => array( 'render_upload_form', 'upload_form_parts' ),
	'signed_words'      => array( 'render_upload_form', 'upload_form_parts' ),
	'contact_facts'     => array( 'render_contact', 'school_lines' ),
	'note_facts'        => array( 'render_note', 'note_parts' ),
	'read_at'           => array( 'render_read_line', 'read_html' ),
	'steps'             => array( 'render_steps', 'steps_parts' ),
	'received_format'   => array( 'render_state', 'render_state_parts' ),
	'lede_text'         => array( 'render_state', 'render_state_parts' ),
	'generated_format'  => array( 'state_sentence', 'render_state_parts' ),
	'take_status'       => array( 'render_flash', 'parts_block' ),
	'print_flash'       => array( 'render_flash', 'parts_block' ),
	'accepted_day'      => array( 'card:render' ),
	'accepted_file'     => array( 'card:render_download', 'card:render_parts' ),
	'pending'           => array( 'card:render_replace', 'card:render_parts' ),
	'route_sentence'    => array( 'card:render_route', 'card:render_parts' ),
	'drive_url'         => array( 'card:render_drive_link', 'card:render_parts' ),
	'replace_sentence'  => array( 'card:render_replace', 'card:render_parts' ),
) as $t21_helper => $t21_callers ) {
	// The old renderers the Institution Dashboard alone drew are gone (Task 23): the helper is held to the callers left.
	$t21_callers              = array_values( array_filter( $t21_callers, static function ( $caller ) use ( $t21_body ) {
		return '' !== $t21_body( $caller );
	} ) );
	$t21_calls[ $t21_helper ] = array_map( static function ( $caller ) use ( $t21_body, $t21_helper ) {
		return 1 === preg_match( '/(self|WPCPM_Institution_Panel)::' . preg_quote( $t21_helper, '/' ) . '\(/', $t21_body( $caller ) );
	}, $t21_callers );
}
ck( 'Task 21: each rule and sentence the agreement needs is one helper, called by the parts\' renderer and by any old one another surface still draws', $t21_calls, array_map( static function ( $callers ) {
	return array_fill( 0, count( $callers ), true );
}, $t21_calls ) );
ck( 'Task 21: the card asks on_manager_ground() for the Drive link, once, before it is drawn', array(
	substr_count( $t21_body( 'card:render' ), 'WPCPM_Institution_Panel::on_manager_ground( $decision )' ),
	1 === preg_match( '/\$as_manager = WPCPM_Institution_Panel::on_manager_ground\( \$decision \);.*WPCPM_Institution_Panel::parts_block\(/s', $t21_src ),
), array( 1, true ) );

// The parts this task extends, with the new arguments.
ck( 'Task 21: a link takes rel and target (_blank alone); a button takes neither; the steps are an ordered list', array(
	WPCPM_UI::button( array( 'label' => 'Open the signed copy in Drive', 'kind' => 'quiet', 'href' => 'https://drive.google.com/x', 'rel' => 'noopener noreferrer', 'target' => '_blank', 'side' => 'front' ) ),
	WPCPM_UI::button( array( 'label' => 'Download', 'kind' => 'secondary', 'href' => 'https://example.test/d', 'rel' => 'nofollow', 'target' => 'top', 'side' => 'front' ) ),
	WPCPM_UI::button( array( 'label' => 'Go', 'rel' => 'nofollow', 'target' => '_blank', 'side' => 'front' ) ),
	WPCPM_UI::button( array( 'label' => 'Go', 'href' => 'https://example.test/', 'rel' => 'x" onclick="y', 'side' => 'front' ) ),
	WPCPM_UI::checklist( array( 'Get it.', 'Sign <it>.' ), true ),
), array(
	'<a class="wpcpm-btn wpcpm-btn--quiet" href="https://drive.google.com/x" rel="noopener noreferrer" target="_blank">Open the signed copy in Drive</a>',
	'<a class="wpcpm-btn wpcpm-btn--secondary" href="https://example.test/d" rel="nofollow">Download</a>',
	'<button type="submit" class="wpcpm-btn">Go</button>',
	'<a class="wpcpm-btn" href="https://example.test/">Go</a>',
	'<ol class="wpcpm-ui-checklist"><li class="wpcpm-ui-checklist__item">Get it.</li><li class="wpcpm-ui-checklist__item">Sign &lt;it&gt;.</li></ol>',
) );

// One real drawing of each kind of block, as the report quotes them.
if ( $t21_print ) {
	foreach ( array( 'submitted, representative', 'on file, administrator', 'returned, representative' ) as $t21_name ) {
		echo "\n--- " . $t21_name . "\n" . $t21_new[ $t21_name ] . "\n";
	}
}

/* -- Task 21, fix round 1 -- */

echo "\n=== Task 21, fix round 1 ===\n";

// Point 1: the settled line is the tag, then the day as a fact, then how it was signed or recorded; the old branch keeps
// "Accepted on".
$t21_settled = array();
foreach ( array( 'accepted, representative', 'accepted, administrator, with drive', 'waiting, tutor', 'on file, administrator' ) as $t21_name ) {
	$t21_x      = review_doc( $t21_new[ $t21_name ] );
	$t21_first  = $t21_x->query( '//section/p[contains(concat(" ", @class, " "), " wpcpm-ui-intro ")]' )->item( 0 );
	$t21_second = $t21_x->query( '//section/p[contains(concat(" ", @class, " "), " wpcpm-ui-intro ")]' )->item( 1 );
	$t21_settled[ $t21_name ] = array(
		$t21_first ? trim( (string) preg_replace( '/\s+/', ' ', $t21_first->textContent ) ) : '',
		$t21_first ? $t21_x->query( './span[@class="wpcpm-ui-whole"]', $t21_first )->length : 0,
		false !== strpos( $t21_new[ $t21_name ], 'Accepted on' ),
		$t21_old_pins[ $t21_name ][4],
		$t21_second ? 0 === strpos( trim( $t21_second->textContent ), 'Recorded' ) || 0 === strpos( trim( $t21_second->textContent ), 'Signed' ) : false,
	);
}
ck( 'fix 1: the settled line is the tag Accepted, then the day whole, then how it was signed or recorded; "Accepted on" stays on the old branch alone', $t21_settled, array(
	'accepted, representative'            => array( 'Accepted ' . $t21_accepted_day, 1, false, true, true ),
	'accepted, administrator, with drive' => array( 'Accepted ' . $t21_accepted_day, 1, false, true, true ),
	'waiting, tutor'                      => array( 'Accepted ' . $t21_accepted_day, 1, false, true, true ),
	'on file, administrator'              => array( 'Accepted ' . $t21_on_file_day, 1, false, true, true ),
) );

// Fix round 2, point 1: with no day recorded the tag is followed by "The date was not recorded." alone; the old
// branch keeps "Accepted. The date was not recorded." whole.
$t21_x     = review_doc( $t21_new['accepted, representative, undated'] );
$t21_first = $t21_x->query( '//section/p[contains(concat(" ", @class, " "), " wpcpm-ui-intro ")]' );
ck( 'fix 2.1: undated, the parts say the tag, then "The date was not recorded.", then the route; the old branch today\'s sentence', array(
	$t21_first->item( 0 ) ? trim( (string) preg_replace( '/\s+/', ' ', $t21_first->item( 0 )->textContent ) ) : '',
	$t21_first->item( 1 ) ? trim( $t21_first->item( 1 )->textContent ) : '',
	substr_count( $t21_new['accepted, representative, undated'], 'Accepted. The date' ),
), array( 'Accepted The date was not recorded.', 'Signed and accepted through this site.', 0 ) );

// Point 2: the on-file form's heading is an h3, under the view's h2.
$t21_x = review_doc( $t21_new['none, administrator'] );
ck( 'fix 2: the on-file form\'s heading is an h3, inside its form', array(
	$t21_x->query( '//form//h3' )->length,
	$t21_x->query( '//h4' )->length,
	trim( (string) ( $t21_x->query( '//form//h3' )->item( 0 ) ? $t21_x->query( '//form//h3' )->item( 0 )->textContent : '' ) ),
), array( 1, 0, 'Record an agreement the program already holds' ) );

/**
 * What a step list holds: per step, its sentence and the actions of the forms inside it.
 *
 * @param string $html Markup.
 * @return string[]
 */
function t21_steps( $html ) {
	$x   = review_doc( $html );
	$out = array();
	foreach ( $x->query( '//ol[@class="wpcpm-ui-checklist"]/li' ) as $li ) {
		$forms = array();
		foreach ( $x->query( './/form//input[@name="action"]', $li ) as $in ) {
			$forms[] = $in->getAttribute( 'value' ) . ( $x->query( 'ancestor::form[1]//input[@name="wpcpm_agreement_post"]', $in )->length ? ' (again)' : '' );
		}
		$out[] = substr( trim( (string) $li->firstChild->textContent ), 0, 20 ) . ' | ' . implode( ', ', $forms );
	}
	return $out;
}
// Point 4: each form sits inside the step it carries out.
ck( 'fix 4: step 1 holds the generate form (and Open that document again once generated), step 2 is its sentence, step 3 holds the upload form', array(
	'none'      => t21_steps( $t21_new['none, representative'] ),
	'generated' => t21_steps( $t21_new['generated, administrator'] ),
	'outside'   => review_doc( $t21_new['generated, representative'] )->query( '//form[not(ancestor::ol)]//input[@name="action"][@value="wpcpm_agreement_generate" or @value="wpcpm_agreement_upload"]' )->length,
), array(
	'none'      => array( 'Get the agreement: g | wpcpm_agreement_generate', 'Sign it. It needs so | ', 'Upload the signed PD | wpcpm_agreement_upload' ),
	'generated' => array( 'Get the agreement: g | wpcpm_agreement_generate (again), wpcpm_agreement_generate', 'Sign it. It needs so | ', 'Upload the signed PD | wpcpm_agreement_upload' ),
	'outside'   => 0,
) );

// Point 3: "It opens as a page..." follows Generate the agreement, the form's last line.
$t21_x    = review_doc( $t21_new['none, representative'] );
$t21_gen  = $t21_x->query( '//form[.//input[@name="action"][@value="wpcpm_agreement_generate"]]' )->item( 0 );
$t21_last = $t21_gen ? $t21_x->query( './*[last()]', $t21_gen )->item( 0 ) : null;
$t21_prev = $t21_last ? $t21_last->previousSibling : null;
ck( 'fix 3: the generate form ends with its button, then "It opens as a page ..."', array(
	$t21_last ? trim( $t21_last->textContent ) : '',
	$t21_prev instanceof DOMElement ? $t21_prev->getAttribute( 'class' ) : '',
), array( 'It opens as a page your browser can save as a PDF or print. Nothing is sent to the program until you upload the signed copy.', 'wpcpm-ui-actions' ) );

// Point 5: for an Administrator the banner's sentences are the block's first lines, after an outcome, as a note set
// apart from the school's sentence; once; from banner_sentences(). Nobody else is shown them.
$t21_banner_of = static function ( $html ) {
	$x     = review_doc( $html );
	$first = $x->query( '//section/*[not(@data-wpcpm-card-message)]' )->item( 0 );
	return array(
		$first ? $first->nodeName . '.' . $first->getAttribute( 'class' ) : '',
		$first ? trim( $first->textContent ) : '',
		substr_count( $html, 'agreement is not settled' ),
	);
};
t21_world( 'none' );
$t21_banner_none = implode( ' ', WPCPM_Institutions_Dashboard::banner_sentences( $t21_rec ) );
t21_world( 'submitted' );
$t21_banner_sub = implode( ' ', WPCPM_Institutions_Dashboard::banner_sentences( $t21_rec ) );
ck( 'fix 5: an Administrator\'s block opens with the banner\'s sentences as a note, after the outcome, printed once; the school\'s own people are not shown them', array(
	'none, administrator'                     => $t21_banner_of( $t21_new['none, administrator'] ),
	'submitted, administrator'                => $t21_banner_of( $t21_new['submitted, administrator'] ),
	// The link refused is said in the on-file form now (Gate B round 2, F2): a busy record still opens the block.
	'message agreement-busy, none, administrator' => array_merge( $t21_banner_of( $t21_new['message agreement-busy, none, administrator'] ), array( review_doc( $t21_new['message agreement-busy, none, administrator'] )->query( '//section/*[1][@data-wpcpm-card-message]' )->length ) ),
	'none, representative'                    => substr_count( $t21_new['none, representative'], 'agreement is not settled' ),
	'accepted, administrator'                 => substr_count( $t21_new['accepted, administrator'], 'agreement is not settled' ),
), array(
	'none, administrator'                     => array( 'p.wpcpm-ui-note', $t21_banner_none, 1 ),
	'submitted, administrator'                => array( 'p.wpcpm-ui-note', $t21_banner_sub, 1 ),
	'message agreement-busy, none, administrator' => array( 'p.wpcpm-ui-note', $t21_banner_none, 1, 1 ),
	'none, representative'                    => 0,
	'accepted, administrator'                 => 0,
) );

ck( 'fix 5: the note is built from banner_sentences(), the method the Overview\'s row reads, and only the panel prints it on the parts', array(
	false !== strpos( $t21_body( 'banner_parts' ), 'WPCPM_Institutions_Dashboard::banner_sentences( (string) $record_id )' ),
	false !== strpos( $t21_body( 'banner_parts' ), 'WPCPM_UI::note( implode(' ),
), array( true, true ) );

// Point 6: the Drive link is in the accepted copy's row, beside its download.
$t21_rows = array();
foreach ( array( 'accepted, administrator, with drive', 'on file, administrator', 'accepted, representative, with drive' ) as $t21_name ) {
	$t21_html = $t21_new[ $t21_name ] ?? t21_parts( 'accepted', 'representative', '', 'with drive' );
	$t21_x    = review_doc( $t21_html );
	$t21_row  = $t21_x->query( '//section/div[@class="wpcpm-ui-actions"]' )->item( 0 );
	$t21_rows[ $t21_name ] = array(
		$t21_row ? array_map( static function ( $a ) {
			return trim( $a->textContent );
		}, iterator_to_array( $t21_x->query( './a', $t21_row ) ) ) : array(),
		$t21_row && $t21_row->previousSibling ? 0 === strpos( trim( $t21_row->previousSibling->textContent ), 'Recorded' ) || 0 === strpos( trim( $t21_row->previousSibling->textContent ), 'Signed' ) : false,
	);
}
ck( 'fix 6: the Drive link stands in the accepted copy\'s row, under the route sentence, beside Download the accepted agreement; a school\'s people get the download alone', $t21_rows, array(
	'accepted, administrator, with drive'  => array( array( 'Download the accepted agreement', 'Open the signed copy in Drive' ), true ),
	'on file, administrator'               => array( array( 'Open the signed copy in Drive' ), true ),
	'accepted, representative, with drive' => array( array( 'Download the accepted agreement' ), true ),
) );

// Point 7: the Drive link an Administrator typed, hostile. The suite's esc_url() is the identity, so what core's esc_url()
// strips from an address (a quote, a tag) is not stripped here: the check is that both branches draw the link through
// esc_url() and print the same address, and that nothing outside the address carries a raw tag or anything escaped
// twice. A javascript:, data: or http: link is drawn on neither branch.
// The address the old branch drew for each, read from the class at db850661 (Task 23).
$t21_old_drive = array(
	'drive hostile'    => 'href="https://drive.google.com/drive/folders/1Ab?x="<script>alert(1)</script>&y=1" rel="noopener noreferrer" target="_blank"',
	'drive tag'        => 'href="https://drive.google.com/drive/folders/1Ab?x=<script>alert(1)</script>&y=1" rel="noopener noreferrer" target="_blank"',
	'drive javascript' => '',
	'drive data'       => '',
	'drive http'       => '',
);
$t21_drive = array();
foreach ( array( 'drive hostile', 'drive tag', 'drive javascript', 'drive data', 'drive http' ) as $t21_variant ) {
	$t21_hrefs = array();
	foreach ( array( 'new' => $t21_new ) as $t21_branch => $t21_set ) {
		$t21_html = $t21_set[ 'on file, administrator, ' . $t21_variant ];
		$t21_at   = strpos( $t21_html, 'href="https://drive.google.com' );
		$t21_hrefs[ $t21_branch ] = false === $t21_at ? '' : substr( $t21_html, $t21_at, (int) strpos( $t21_html, '>Open the signed copy in Drive', $t21_at ) - $t21_at );
		$t21_bare = (string) preg_replace( '/href="https:\/\/drive\.google\.com[^>]*>/', '>', $t21_html );
		$t21_drive[ $t21_variant . ', ' . $t21_branch ] = array(
			false !== stripos( $t21_bare, '<script' ),
			false !== strpos( $t21_html, '&amp;amp;' ) || false !== strpos( $t21_html, '&amp;quot;' ),
			false !== strpos( $t21_html, 'Open the signed copy in Drive' ),
			false !== stripos( $t21_html, 'javascript:' ) || false !== stripos( $t21_html, 'data:text' ) || false !== strpos( $t21_html, 'http://drive' ),
		);
	}
	$t21_drive[ $t21_variant . ', the same address on both' ] = $t21_old_drive[ $t21_variant ] === $t21_hrefs['new'];
}
ck( 'fix 7: a hostile https Drive link goes through esc_url() on both branches, the same address, nothing raw outside it; javascript:, data: and http: links are drawn on neither', $t21_drive, array(
	'drive hostile, new'                     => array( false, false, true, false ),
	'drive hostile, the same address on both' => true,
	'drive tag, new'                         => array( false, false, true, false ),
	'drive tag, the same address on both'    => true,
	'drive javascript, new'                  => array( false, false, false, false ),
	'drive javascript, the same address on both' => true,
	'drive data, new'                        => array( false, false, false, false ),
	'drive data, the same address on both'   => true,
	'drive http, new'                        => array( false, false, false, false ),
	'drive http, the same address on both'   => true,
) );
ck( 'fix 7: the Drive link passes through esc_url() (core strips a quote and a tag from an address)', array(
	false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php' ), "'\" href=\"' . esc_url( \$href ) . '\"'" ),
), array( true ) );

// Point 4: checklist() keeps its bytes for plain lines (the Administrator Dashboard's review checklist, drawn for real
// above, md5 at 1c7468a1), and a line may carry a body of parts.
preg_match( '#<ul class="wpcpm-ui-checklist">.*?</ul>#s', $on_one, $t21_review_list );
ck( 'fix 4: the review\'s checklist on the Administrator Dashboard prints the base\'s bytes (md5 at 1c7468a1)', md5( $t21_review_list[0] ?? '' ), '0ad046c671caae8520f3cc7f430d5a8a' );
ck( 'fix 4: checklist(): a line may carry a body of parts after its words; a line with an empty body, or a plain line, prints as before', array(
	WPCPM_UI::checklist( array( array( 'text' => 'Get <it>.', 'body' => '<form method="post"></form>' ), 'Sign it.', array( 'text' => 'Upload it.', 'body' => '' ) ), true ),
	WPCPM_UI::checklist( array( array( 'text' => 'A' ), 'B' ) ) === WPCPM_UI::checklist( array( 'A', 'B' ) ),
), array(
	'<ol class="wpcpm-ui-checklist"><li class="wpcpm-ui-checklist__item">Get &lt;it&gt;.<form method="post"></form></li><li class="wpcpm-ui-checklist__item">Sign it.</li><li class="wpcpm-ui-checklist__item">Upload it.</li></ol>',
	true,
) );

// Point 9: what a refused form kept comes back on the parts: the kind chosen, the name typed, the Drive link, the date
// and the location typed; the signer's tick is not kept. The old branch and the manager's upload read none of it.
$t21_up_form = WPCPM_Institution_Agreement::KEPT_UPLOAD;
$t21_gen_form = WPCPM_Agreement_Generate::KEPT_GENERATE;
$t21_of_form  = WPCPM_Institution_Agreement::KEPT_ON_FILE;
$t21_long_name = $t21_long . ' [Sede Norte]';
$t21_kept_cases = array(
	'kept: upload refused, own kept, none, representative'      => array( 'none', 'representative', 'agreement-not-pdf', '', array( $t21_up_form => array( 'wpcpm_agreement_kind' => 'own' ) ), 'message agreement-not-pdf, none, representative' ),
	'kept: upload refused, own kept, accepted, representative'  => array( 'accepted', 'representative', 'agreement-not-pdf', '', array( $t21_up_form => array( 'wpcpm_agreement_kind' => 'own' ) ), 'message agreement-not-pdf, accepted, representative' ),
	'kept: upload refused, own kept, returned, representative'  => array( 'returned', 'representative', 'agreement-declare', '', array( $t21_up_form => array( 'wpcpm_agreement_kind' => 'own' ) ), 'message agreement-declare, returned, representative' ),
	'kept: generate refused, long name kept, none, representative' => array( 'none', 'representative', 'agreement-name-brackets', '', array( $t21_gen_form => array( 'wpcpm_agreement_name' => $t21_long_name ) ), 'message agreement-name-brackets, none, representative' ),
	'kept: on-file refused, three kept, none, administrator'    => array( 'none', 'administrator', 'agreement-link', '', array( $t21_of_form => array( 'wpcpm_agreement_drive' => 'https://example.com/our-copy', 'wpcpm_agreement_signed_on' => '2024-05-06', 'wpcpm_agreement_where' => 'second folder, the 2019 copy' ) ), 'message agreement-link, none, administrator' ),
	'kept: hostile, none, administrator'                         => array( 'none', 'administrator', 'agreement-link', '', array(
		$t21_up_form  => array( 'wpcpm_agreement_kind' => 'legacy" onclick="x' ),
		$t21_gen_form => array( 'wpcpm_agreement_name' => $t21_hostile ),
		$t21_of_form  => array( 'wpcpm_agreement_drive' => 'https://drive.google.com/x?a="<script>&b', 'wpcpm_agreement_signed_on' => '<script>', 'wpcpm_agreement_where' => $t21_hostile ),
	), 'message agreement-link, none, administrator' ),
);
$t21_kept_new = array();
foreach ( $t21_kept_cases as $t21_name => $t21_case ) {
	$GLOBALS['t21_keep']       = $t21_case[4];
	$t21_kept_new[ $t21_name ] = t21_parts( $t21_case[0], $t21_case[1], $t21_case[2], $t21_case[3] );
	$GLOBALS['t21_keep']       = array();
}
ck( 'and the same with each refused form\'s typing kept', wpcpm_view_faults_of( 'Collaboration Agreement', $t21_kept_new ), array() );
$GLOBALS['t21_keep'] = array( $t21_up_form => array( 'wpcpm_agreement_kind' => 'own' ) );
$t21_mgr_kept        = md5( t21_manager( 'none', '', 'wpcpm-institutions/accounts', array( 'administrator', 'institution' ) ) );
$GLOBALS['t21_keep'] = array();
ck( 'fix 9: and so does the manager\'s upload in wp-admin (it keeps today\'s ticked template)', $t21_mgr_kept, $t21_manager_base['none'] );

/**
 * What a drawing's forms hold of a refused form's typing.
 *
 * @param string $html Markup.
 * @return array
 */
function t21_kept_in( $html ) {
	$x    = review_doc( $html );
	$val  = static function ( $name ) use ( $x ) {
		$box = $x->query( '//input[@name="' . $name . '"][not(@type="hidden")]' )->item( 0 );
		return $box ? $box->getAttribute( 'value' ) : null;
	};
	$kind = $x->query( '//input[@name="wpcpm_agreement_kind"][@checked]' );
	return array(
		'kind'   => 1 === $kind->length ? $kind->item( 0 )->getAttribute( 'value' ) : $kind->length . ' ticked',
		'signed' => $x->query( '//input[@name="wpcpm_agreement_signed"][@type="checkbox"][@checked]' )->length,
		'name'   => $val( 'wpcpm_agreement_name' ),
		'drive'  => $val( 'wpcpm_agreement_drive' ),
		'date'   => $val( 'wpcpm_agreement_signed_on' ),
		'where'  => $val( 'wpcpm_agreement_where' ),
	);
}
ck( 'fix 9: on the parts each refused form comes back holding what was typed, the signer\'s tick not kept; a kind nobody knows is today\'s default', array_map( 't21_kept_in', $t21_kept_new ), array(
	'kept: upload refused, own kept, none, representative'         => array( 'kind' => 'own', 'signed' => 0, 'name' => 'Universidad Example', 'drive' => null, 'date' => null, 'where' => null ),
	'kept: upload refused, own kept, accepted, representative'     => array( 'kind' => 'own', 'signed' => 0, 'name' => null, 'drive' => null, 'date' => null, 'where' => null ),
	'kept: upload refused, own kept, returned, representative'     => array( 'kind' => 'own', 'signed' => 0, 'name' => null, 'drive' => null, 'date' => null, 'where' => null ),
	'kept: generate refused, long name kept, none, representative' => array( 'kind' => 'template', 'signed' => 0, 'name' => $t21_long_name, 'drive' => null, 'date' => null, 'where' => null ),
	'kept: on-file refused, three kept, none, administrator'       => array( 'kind' => 'template', 'signed' => 0, 'name' => 'Universidad Example', 'drive' => 'https://example.com/our-copy', 'date' => '2024-05-06', 'where' => 'second folder, the 2019 copy' ),
	'kept: hostile, none, administrator'                           => array( 'kind' => 'template', 'signed' => 0, 'name' => $t21_hostile, 'drive' => 'https://drive.google.com/x?a="<script>&b', 'date' => '<script>', 'where' => $t21_hostile ),
) );
ck( 'fix 9: every kept value is escaped once: no raw tag, no broken attribute, nothing escaped twice', array(
	false !== stripos( $t21_kept_new['kept: hostile, none, administrator'], '<script' ),
	false !== strpos( $t21_kept_new['kept: hostile, none, administrator'], '&amp;amp;' ) || false !== strpos( $t21_kept_new['kept: hostile, none, administrator'], '&amp;quot;' ),
	false !== strpos( $t21_kept_new['kept: hostile, none, administrator'], 'onclick' ),
	substr_count( $t21_kept_new['kept: hostile, none, administrator'], 'value="Uni &lt;script&gt;alert(1)&lt;/script&gt; &quot;Q&quot; &amp; Co"' ),
), array( false, false, false, 2 ) );
// The on-file handler keeps the three boxes as typed on a refusal, and nothing once it records.
$t21_of_kept = static function () use ( $t21_rec ) {
	$pending = get_user_meta( $GLOBALS['uid'], WPCPM_Flash::META, true );
	$key     = 'kept:' . WPCPM_Institution_Agreement::KEPT_ON_FILE . $t21_rec;
	return is_array( $pending ) && isset( $pending[ $key ] ) ? $pending[ $key ]['values'] : null;
};
$t21_of = array();
reset_world();
seed_index( $t21_rec, '' );
$GLOBALS['uid']      = 9;
$GLOBALS['caps']     = true;
$GLOBALS['referer']  = '';
t21_forget_flash();
$t21_of['a link that is not Drive'] = array( on_file( $t21_rec, 'https://example.com/our "copy" & <b>', '2024-05-06', 'second folder, <the> "2019" & copy' ), flashed(), $t21_of_kept() );
reset_world();
$GLOBALS['uid']  = 9;
$GLOBALS['caps'] = true;
t21_forget_flash();
$t21_of['a record the index does not hold'] = array( on_file( $t21_rec, $drive, '', 'shelf' ), flashed(), $t21_of_kept() );
reset_world();
seed_index( $t21_rec, '' );
$GLOBALS['uid']  = 9;
$GLOBALS['caps'] = true;
t21_forget_flash();
WPCPM_Flash::keep( WPCPM_Institution_Agreement::KEPT_ON_FILE . $t21_rec, array( 'wpcpm_agreement_drive' => 'an earlier try' ) );
t21_forget_flash_memo();
$t21_of['recorded, an earlier refusal kept'] = array( on_file( $t21_rec, $drive, '2024-05-06', 'shelf' ), flashed(), $t21_of_kept() );
reset_world();
seed_index( $t21_rec, '' );
$GLOBALS['uid']  = 9;
$GLOBALS['caps'] = true;
t21_forget_flash();
$t21_long_link   = 'https://example.com/' . str_repeat( 'a', 500 );
$t21_of['over-long'] = array( on_file( $t21_rec, $t21_long_link, '2024-05-06 and more', str_repeat( 'b', 450 ) ), flashed(), $t21_of_kept() );
// Fix round 2, point 2: each kept value is cut where its handler cuts it: the location at MAX_LOCATION, the date at
// the ten characters date_or_empty() reads; the Drive link is read whole by its handler, and kept whole.
$t21_of['over-long'][2] = array_map( static function ( $value ) {
	return strlen( (string) $value );
}, (array) $t21_of['over-long'][2] );
ck( 'fix 9: a refused on-file record keeps the Drive link, the date and the location as typed, its status and way back unchanged; one that records keeps nothing', $t21_of, array(
	'a link that is not Drive'          => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions', 'agreement-link', array( 'wpcpm_agreement_drive' => 'https://example.com/our "copy" & <b>', 'wpcpm_agreement_signed_on' => '2024-05-06', 'wpcpm_agreement_where' => 'second folder, <the> "2019" & copy' ) ),
	'a record the index does not hold'  => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions', 'agreement-unknown', array( 'wpcpm_agreement_drive' => $drive, 'wpcpm_agreement_signed_on' => '', 'wpcpm_agreement_where' => 'shelf' ) ),
	'recorded, an earlier refusal kept' => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions', 'agreement-on-file', null ),
	'over-long'                         => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions', 'agreement-link', array( 'wpcpm_agreement_drive' => strlen( $t21_long_link ), 'wpcpm_agreement_signed_on' => WPCPM_Institution_Agreement::DATE_LENGTH, 'wpcpm_agreement_where' => WPCPM_Institution_Agreement::MAX_LOCATION ) ),
) );
// The kept typing is taken once: drawn, it is gone, and a second drawing shows today's defaults.
$GLOBALS['t21_keep'] = array( $t21_up_form => array( 'wpcpm_agreement_kind' => 'own' ) );
t21_parts( 'none', 'representative', 'agreement-not-pdf' );
$GLOBALS['t21_keep'] = array();
$t21_left = get_user_meta( $GLOBALS['uid'], WPCPM_Flash::META, true );
$t21_left = is_array( $t21_left ) ? array_keys( $t21_left ) : array();
ck( 'fix 9: drawn once, what was kept is cleared', $t21_left, array() );

/* -- the layout harness -- */

if ( function_exists( 'wpcpm_layout_keep' ) ) {
	foreach ( $t21_kept_new as $t21_name => $t21_html ) {
		if ( false === strpos( $t21_name, 'hostile' ) ) {
			wpcpm_layout_keep( 'Collaboration Agreement: ' . $t21_name, $t21_html );
		}
	}
	foreach ( $t21_new as $t21_name => $t21_html ) {
		if ( '' === $t21_html || false !== strpos( $t21_name, 'hostile' ) ) {
			continue;
		}
		wpcpm_layout_keep( 'Collaboration Agreement: ' . $t21_name, $t21_html );
	}
}

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
