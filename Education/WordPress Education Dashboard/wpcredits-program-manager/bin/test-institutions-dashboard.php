<?php
/**
 * The Institution Dashboard's shell: the page, the routing, and the locked branch.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The page is created once. `ensure_page()` adopts an existing `institution-dashboard`
 *   page before it inserts one, so a site restored from a backup does not end up with a
 *   second page at `institution-dashboard-2` that nothing links to.
 * - The title is stamped at TITLE_VERSION whatever the outcome, so a site that renamed the
 *   page by hand is not asked again on every request. A counter, not a flag: the student
 *   page found out the hard way that a boolean skips a site for ever.
 * - **The locked branch, which is the point of this piece.** A member whose Collaboration
 *   Agreement nobody has accepted gets the identity header and the agreement panel and
 *   nothing else: no roster, no People card, no agreement card at the foot. Asserted on the
 *   markup, because "renders nothing" is the sort of claim that quietly stops being true.
 * - A program manager on the same unsettled institution gets the whole dashboard under a
 *   banner naming the state. Decision 6: the gate limits what an institution may do for
 *   itself, and refusing the manager cannot make an agreement arrive sooner.
 * - Somebody who is neither a member nor a manager is told nothing about any institution.
 *   A page that named the school it would have shown would be a membership oracle. The
 *   sentence is `WPCPM_Dashboards`'s, asserted against what that helper returns, so the
 *   three dashboards go on shipping one wording rather than three.
 * - The banner names every agreement state the summary can hold, because a manager's next
 *   move depends on which one it is, and the Airtable half is drawn only when the base has
 *   something to say.
 * - The identity header prefers the pipeline index to the membership stamp. Nothing
 *   refreshes the stamp after `attach()` writes it, so a stale one must never win over a
 *   row the sync read last night - and with no row at all the stamp is what draws the
 *   header instead of nothing.
 * - The four cards are named as strings and guarded, so a checkout missing one of them
 *   leaves a gap rather than a fatal. Only the panel exists when the section below the
 *   fixture runs: a guard whose whole job is to survive an absent class cannot be
 *   exercised by a suite that declares every class up front.
 * - The switcher is drawn under CAP_MANAGE and for nobody else, and the cohort argument is
 *   read as text: `sanitize_key()` lowercases, and `2026-H1` is not `2026-h1`.
 * - Open question 12: an account that both mentors and acts for an institution lands on the
 *   Mentor Report Card, so `login_redirect` steps aside for it.
 *
 * Run from the plugin root:  php bin/test-institutions-dashboard.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']    = array();
$GLOBALS['umeta']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['users']   = array();
$GLOBALS['posts']   = array();
$GLOBALS['manage']  = array();
$GLOBALS['editors'] = array();
$GLOBALS['uid']     = 0;
$GLOBALS['calls']   = array();
$GLOBALS['styles']  = array();
$GLOBALS['queried'] = 0;
$GLOBALS['pagenow'] = 'index.php';
$GLOBALS['ajax']    = false;
$GLOBALS['mentors'] = array();
$GLOBALS['next_id'] = 100;

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_name = '', $post_content = '', $post_type = 'page', $post_status = 'publish';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h, $p ); }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_filter', $h, $p ); }
function add_shortcode( $tag, $c ) { $GLOBALS['calls'][] = array( 'add_shortcode', $tag ); }
function register_block_type( $dir, $args = array() ) { $GLOBALS['calls'][] = array( 'register_block_type', $dir ); return true; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_doing_ajax() { return (bool) $GLOBALS['ajax']; }
function is_multisite() { return false; }
function network_admin_url( $p = '' ) { return 'https://example.test/wp-admin/network/' . $p; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_option', $k, $a ); return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
// Like WordPress: the access level is registered with a default of 'public', which is what a
// page with no row at all answers. `metadata_exists()` is how the two are told apart.
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ( '_wpcpm_access_level' === $k ? 'public' : '' ); }
function metadata_exists( $type, $id, $k ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_post_meta', (int) $id, $k, $v ); return true; }

function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_status( $id ) { $p = get_post( $id ); return $p instanceof WP_Post ? $p->post_status : false; }
function get_permalink( $id ) { $p = get_post( $id ); return $p instanceof WP_Post ? 'https://example.test/' . $p->post_name . '/' : false; }
function get_page_by_path( $slug, $output = null, $type = 'page' ) {
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_name === $slug ) { return $post; }
	}
	return null;
}
function wp_insert_post( $args, $wp_error = false ) {
	$post               = new WP_Post();
	$post->ID           = ++$GLOBALS['next_id'];
	$post->post_title   = $args['post_title'] ?? '';
	$post->post_name    = $args['post_name'] ?? '';
	$post->post_content = $args['post_content'] ?? '';
	$post->post_status  = $args['post_status'] ?? 'publish';
	$GLOBALS['posts'][ $post->ID ] = $post;
	$GLOBALS['calls'][]            = array( 'wp_insert_post', $post->post_name, $post->post_title );
	return $post->ID;
}
function wp_update_post( $args, $wp_error = false ) {
	$post = get_post( $args['ID'] ?? 0 );
	if ( $post instanceof WP_Post && isset( $args['post_title'] ) ) { $post->post_title = $args['post_title']; }
	$GLOBALS['calls'][] = array( 'wp_update_post', (int) ( $args['ID'] ?? 0 ) );
	return (int) ( $args['ID'] ?? 0 );
}
function get_queried_object_id() { return (int) $GLOBALS['queried']; }

function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
	}
	return false;
}
function get_users( $args = array() ) { return array_values( $GLOBALS['users'] ); }
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;
	if ( 'edit_posts' === $c ) { return in_array( $id, $GLOBALS['editors'], true ); }
	return in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }

function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $to ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v = '', $u = '' ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

function wp_style_is( $handle, $list = 'enqueued' ) {
	return isset( $GLOBALS['styles'][ $handle ] ) && ( 'registered' === $list || ! empty( $GLOBALS['styles'][ $handle ]['on'] ) );
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'on' => false );
}
function wp_enqueue_style( $handle ) { if ( isset( $GLOBALS['styles'][ $handle ] ) ) { $GLOBALS['styles'][ $handle ]['on'] = true; } }
function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
// The real helper and not a stand-in: it is where the empty-page wording lives now, and a
// copy of the sentence here would let the page ship one wording while the suite pinned
// another. Only `nothing_to_show()` is ever called, and it reads nothing but the functions
// stubbed above.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return is_string( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/D', trim( $value ) );
		}
	}
}

if ( ! class_exists( 'WPCPM_Content_Access' ) ) {
	class WPCPM_Content_Access {
		const META_KEY = '_wpcpm_access_level';
	}
}

if ( ! class_exists( 'WPCPM_Two_Factor' ) ) {
	class WPCPM_Two_Factor {
		public static function prompt( $user ) { echo '<!-- 2fa -->'; }
	}
}

if ( ! class_exists( 'WPCPM_Mentors_Dashboard' ) ) {
	class WPCPM_Mentors_Dashboard {
		const STYLE = 'wpcpm-mentor-dashboard';
		public static function register_assets() {
			if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
				wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/dashboard.css', array( 'dashicons' ), WPCPM_VERSION );
			}
		}
		public static function is_mentor( $user = null ) {
			$user = WPCPM_Roles::resolve_user( $user );
			return $user instanceof WP_User && in_array( $user->ID, $GLOBALS['mentors'], true );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	class WPCPM_Institution_Members {
		const META_RECORD_ID = 'wpcpm_institution_record_id';
		const META_ACTIVE    = 'wpcpm_institution_active';
		const META_PROFILE   = 'wpcpm_institution_profile';
		public static function institution_of( $user = null ) {
			$user = WPCPM_Roles::resolve_user( $user );
			if ( ! $user instanceof WP_User || ! $user->exists() ) { return ''; }
			$stamp = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ) );
			$live  = '1' === (string) get_user_meta( $user->ID, self::META_ACTIVE, true );
			return ( $live && WPCPM_Mentors_Sync::is_record_id( $stamp ) ) ? $stamp : '';
		}
		public static function memberships_of( $user = null ) {
			$one = self::institution_of( $user );
			return '' === $one ? array() : array( $one );
		}
		public static function is_member( $user = null ) { return '' !== self::institution_of( $user ); }
		public static function members_of( $record_id ) {
			$out = array();
			foreach ( $GLOBALS['users'] as $user ) {
				if ( 0 === strcmp( self::institution_of( $user ), (string) $record_id ) && '' !== (string) $record_id ) { $out[] = $user; }
			}
			return $out;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		const OPTION  = 'wpcpm_institutions_index';
		const VERSION = 1;
		public static function read() {
			$o = get_option( self::OPTION );
			return ( is_array( $o ) && isset( $o['v'] ) && self::VERSION === $o['v'] ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function rows() { $r = self::read(); return $r['rows']; }
		public static function row( $id ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $id ) ) { return null; }
			$rows = self::rows();
			return isset( $rows[ $id ] ) ? $rows[ $id ] : null;
		}
		public static function has( $id ) { return null !== self::row( $id ); }
	}
}

if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	class WPCPM_Roster_Index {
		const OPTION_PREFIX = 'wpcpm_roster_';
		const VERSION       = 1;
		public static function option_name( $id ) { return self::OPTION_PREFIX . $id; }
		public static function read( $id ) {
			$o = get_option( self::option_name( $id ) );
			return is_array( $o ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function rows( $id ) { return self::read( $id )['rows']; }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	class WPCPM_Institution_Agreement {
		const SUMMARY_NONE      = 'none';
		const SUMMARY_GENERATED = 'generated';
		const SUMMARY_SUBMITTED = 'submitted';
		const SUMMARY_RETURNED  = 'returned';
		const SUMMARY_REVOKED   = 'revoked';
		const SUMMARY_ACCEPTED  = 'accepted';
		const SUMMARY_ON_FILE   = 'on_file';
		public static function is_settled( $record_id ) {
			$row = get_option( 'wpcpm_agreement_' . $record_id );
			return is_array( $row ) && ! empty( $row['settled'] );
		}
		public static function summary( $record_id ) {
			$row = get_option( 'wpcpm_agreement_' . $record_id );
			return array(
				'state'           => is_array( $row ) && isset( $row['site_state'] ) ? $row['site_state'] : self::SUMMARY_NONE,
				'kind'            => '',
				'accepted_at'     => '',
				'agreement_id'    => 0,
				'pending_id'      => 0,
				'generated_id'    => 0,
				'airtable_status' => is_array( $row ) && isset( $row['airtable_status'] ) ? $row['airtable_status'] : '',
				'route'           => '',
			);
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Policy' ) ) {
	/**
	 * The fence, stubbed to `decide()`'s contract for the one action this page asks about.
	 *
	 * ACT_AGREEMENT is the only ungated action, so a member passes it whether or not their
	 * agreement is settled. That is deliberate here: the shell asks this question precisely
	 * because it must recognise a locked member as a member.
	 */
	class WPCPM_Institution_Policy {
		const GROUND_MANAGER = 'manager';
		const GROUND_MEMBER  = 'member';
		const ACT_VIEW_ROSTER = 'view_roster';
		const ACT_AGREEMENT   = 'agreement';
		public static function subject_institution( $record_id ) {
			return array( 'type' => 'institution', 'id' => $record_id, 'institution_ids' => array( $record_id ), 'evidence' => 'index' );
		}
		public static function decide( $action, array $subject, $user = null ) {
			$refused = array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => '' );
			$user    = WPCPM_Roles::resolve_user( $user );
			if ( ! $user instanceof WP_User || ! $user->exists() ) { return $refused; }
			$ids = array_values( array_filter( (array) $subject['institution_ids'], array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );
			if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) ) {
				return array( 'allowed' => true, 'ground' => self::GROUND_MANAGER, 'institution' => $ids ? $ids[0] : '', 'fields' => null, 'why' => '' );
			}
			if ( empty( $ids ) ) { return $refused; }
			foreach ( WPCPM_Institution_Members::memberships_of( $user ) as $mine ) {
				if ( ! in_array( $mine, $ids, true ) ) { continue; }
				if ( self::ACT_AGREEMENT !== $action && ! WPCPM_Institution_Agreement::is_settled( $mine ) ) { continue; }
				return array( 'allowed' => true, 'ground' => self::GROUND_MEMBER, 'institution' => $mine, 'fields' => null, 'why' => '' );
			}
			return $refused;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/** Spec 5.5, stubbed exactly: the switcher under CAP_MANAGE, then the stamp, then the first institution with a member. */
	class WPCPM_Institution_Roster {
		const ARG_VIEW = 'wpcpm_institution_view';
		public static function resolve_institution( $viewer, $can_manage ) {
			if ( $can_manage ) {
				$asked = WPCPM_Request::text( self::ARG_VIEW );
				if ( WPCPM_Mentors_Sync::is_record_id( $asked ) && WPCPM_Institutions_Index::has( $asked ) ) {
					return $asked;
				}
			}
			$mine = WPCPM_Institution_Members::institution_of( $viewer );
			if ( '' !== $mine ) { return $mine; }
			if ( $can_manage ) {
				foreach ( array_keys( WPCPM_Institutions_Index::rows() ) as $record_id ) {
					if ( ! empty( WPCPM_Institution_Members::members_of( $record_id ) ) ) { return $record_id; }
				}
			}
			return '';
		}
		public static function switcher_options() {
			$out = array();
			foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
				$out[ $record_id ] = trim( (string) $row['name'] );
			}
			return $out;
		}
	}
}

/*
 * The first of the four cards. Each says only that it ran, which is all this suite asks of
 * them - but they do not all land at once. The other three are declared further down, after
 * the section that renders without them, because the shell names every card as a string
 * precisely so a checkout can be missing one. A class declared at the top level of a file is
 * bound when the file is compiled, so a top-level declaration is a class that exists from
 * the first line: only a conditional one is absent until execution reaches it.
 */
if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) ) {
	/**
	 * Stands in for the shared Resources section.
	 *
	 * Records the audience it was asked for and hands back the audience-specific block, which
	 * is all this page contributes to it; what the section itself draws is its own suite's.
	 */
	class WPCPM_Handbook_Assistant {
		public static function render_resources( $audience = '', $extra = '' ) {
			$GLOBALS['resources'][] = (string) $audience;

			return '<section class="wpcpm-handbook__resources" data-audience="' . $audience . '">' . $extra . '</section>';
		}
	}
}

if ( ! class_exists( 'WPCPM_Countries' ) ) {
	/** Stands in for the country routing map. */
	class WPCPM_Countries {
		public static function routing( $record_id ) {
			return isset( $GLOBALS['routing'][ $record_id ] ) ? $GLOBALS['routing'][ $record_id ] : null;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Panel' ) ) {
	class WPCPM_Institution_Panel {
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][] = array( 'panel', $record_id, $context );
			echo '<div class="wpcpm-institution__card" data-card="panel"></div>';
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';

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
 * Render the dashboard as one viewer, with one set of query arguments.
 *
 * @param int   $uid  User to render as; 0 for a logged-out visitor.
 * @param array $get  Query arguments the render should see.
 * @param array $atts Shortcode attributes.
 * @return string
 */
function render_as( $uid, array $get = array(), array $atts = array() ) {
	$GLOBALS['uid']   = (int) $uid;
	$GLOBALS['cards'] = array();
	$_GET             = $get;

	return WPCPM_Institutions_Dashboard::render( $atts );
}

/** Which cards ran on the last render, in order. */
function cards_run() {
	return array_map( function ( $card ) { return $card[0]; }, $GLOBALS['cards'] );
}

/* ---- the fixture --------------------------------------------------------- */

$krakow = 'recKRAKOW12345678';
$patil  = 'recPATIL123456789';

// Checked before anything reads them. Every guard in this module refuses a malformed ID
// silently and correctly, so a fixture one character short passes as "not a member" and
// every assertion below turns green for the wrong reason.
ck(
	'the fixture\'s record IDs are the shape Airtable uses',
	array( WPCPM_Mentors_Sync::is_record_id( $krakow ), WPCPM_Mentors_Sync::is_record_id( $patil ) ),
	array( true, true )
);

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ] = array(
	'v'    => 1,
	'read' => 1756000000,
	'rows' => array(
		// The trailing space is the base's, on ten records. The header trims it; the
		// manager screen is where the stored value is reported.
		$krakow => array(
			'record_id'      => $krakow,
			'name'           => 'Politechnika Krakowska ',
			'stage'          => 'Confirmed',
			'city'           => 'Krakow',
			'country'        => 'recCOUNTRYPOLAND1',
			'country_name'   => 'Poland',
			'website'        => 'pk.edu.pl',
			'contact_person' => 'Anna Kowalska',
			'contact_email'  => 'anna@example.edu',
		),
		$patil  => array(
			'record_id'      => $patil,
			'name'           => 'D. Y. Patil',
			'stage'          => 'Confirmed',
			'city'           => 'Pune',
			'country'        => 'recCOUNTRYINDIA01',
			'country_name'   => 'India',
			'website'        => '',
			'contact_person' => '',
			'contact_email'  => 'contact@dypatil.example',
		),
	),
);

$GLOBALS['opts'][ 'wpcpm_roster_' . $krakow ] = array( 'v' => 1, 'read' => 1756100000, 'rows' => array() );

// Two members at Krakow, one manager, one mentor who is also a member, one bystander.
$GLOBALS['users'][2] = new WP_User( 2, 'Anna Kowalska', 'anna@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][3] = new WP_User( 3, 'Piotr Nowak', 'piotr@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][4] = new WP_User( 4, 'Program Manager', 'manager@example.test', array( 'administrator' ) );
$GLOBALS['users'][5] = new WP_User( 5, 'Someone Else', 'nobody@example.test', array( 'subscriber' ) );
$GLOBALS['users'][6] = new WP_User( 6, 'Mentor Member', 'both@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_MENTOR ) );

$GLOBALS['manage']  = array( 4 );
$GLOBALS['mentors'] = array( 6 );

foreach ( array( 2, 3, 6 ) as $member ) {
	$GLOBALS['umeta'][ $member ][ WPCPM_Institution_Members::META_RECORD_ID ] = $krakow;
	$GLOBALS['umeta'][ $member ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
}

// The profile stamp is a snapshot taken at attach(), and nothing refreshes it afterwards:
// this one still reads the stage the record was at that day, and predates the city being
// filled in. Both halves matter below - the fresh index wins where they disagree, and the
// stamp fills what the index has no answer for.
$GLOBALS['umeta'][2][ WPCPM_Institution_Members::META_PROFILE ] = array(
	'name'           => 'Politechnika Krakowska ',
	'city'           => '',
	'country_name'   => 'Poland',
	'stage'          => 'Agreement Sent',
	'website'        => 'https://pk.edu.pl/',
	'contact_person' => 'Anna Kowalska',
);

/* ---- a checkout where only the panel has landed -------------------------- */

// Phase 2's cards land as separate commits, so `card()` names each one as a string and
// returns early when the class is not there, and `context()` asks the roster view for the
// filter bar's arguments only if it can. Three of the four classes do not exist yet, which
// is the only state in which those guards do anything at all. Rendered as a manager,
// because the locked branch would call the panel and nothing else whatever was installed.
$out = render_as(
	4,
	array(
		'wpcpm_institution_view' => $krakow,
		'wpcpm_cohort'           => '2026-H1',
		'wpcpm_roster_status'    => 'waiting',
	)
);

ck( 'a card whose class has not landed leaves a gap', cards_run(), array( 'panel' ) );
ck( 'and the page around it is still drawn', false !== strpos( $out, 'wpcpm-institution__identity' ), true );
ck( 'switcher and all', false !== strpos( $out, 'id="wpcpm-institution-switcher"' ), true );
ck( 'with no markup from the three that are missing', substr_count( $out, 'wpcpm-institution__card' ), 1 );

// The filter bar belongs to the roster view, so with no roster view there is nobody to ask
// and the answer is "nothing was filtered" rather than a guess of the shell's own.
$context = $GLOBALS['cards'][0][2];
ck( 'with no roster view there is no cohort to read', $context['cohort'], '' );
ck( 'and no filters either, though the URL carries both', $context['filters'], array() );
ck( 'while the read time, which is not the roster view\'s to give, still arrives', $context['read'], 1756100000 );

/* ---- the other three cards, landing after it ----------------------------- */

if ( ! class_exists( 'WPCPM_Institution_Roster_View' ) ) {
	class WPCPM_Institution_Roster_View {
		// The filter bar's own argument names. They are this class's, not the shell's, which is
		// the whole point of the two readers below: the shell asks rather than guessing.
		const ARG_COHORT = 'wpcpm_cohort';
		const ARG_STATUS = 'wpcpm_roster_status';
		const ARG_SEARCH = 'wpcpm_roster_search';
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][] = array( 'roster', $record_id, $context );
			echo '<div class="wpcpm-institution__card" data-card="roster"></div>';
		}
		public static function cohort_from_request() {
			$asked = WPCPM_Request::text( self::ARG_COHORT );
			return WPCPM_Cohort::is_key( $asked ) ? $asked : '';
		}
		public static function filters_from_request() {
			$status = WPCPM_Request::key( self::ARG_STATUS );
			$groups = array( 'current', 'waiting', 'finished', 'not_started' );
			return array(
				'status' => in_array( $status, $groups, true ) ? $status : '',
				'search' => trim( WPCPM_Request::text( self::ARG_SEARCH ) ),
			);
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_People' ) ) {
	class WPCPM_Institution_People {
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][] = array( 'people', $record_id, $context );
			echo '<div class="wpcpm-institution__card" data-card="people"></div>';
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement_Card' ) ) {
	class WPCPM_Institution_Agreement_Card {
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][] = array( 'agreement-card', $record_id, $context );
			echo '<div class="wpcpm-institution__card" data-card="agreement-card"></div>';
		}
	}
}

// The same render, now that they have: the guards let the pieces land in any order, and
// this is the order they landed in.
render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'and once they land, every card is drawn', cards_run(), array( 'panel', 'roster', 'people', 'agreement-card' ) );

/* ---- the page ------------------------------------------------------------ */

$page_id = WPCPM_Institutions_Dashboard::ensure_page();
ck( 'ensure_page() creates the page', $page_id > 0, true );
ck( 'and records it in the option', (int) get_option( WPCPM_Institutions_Dashboard::OPT_PAGE ), $page_id );
ck( 'at the slug that is never renamed', get_post( $page_id )->post_name, 'institution-dashboard' );
ck( 'with the title open question 14 settled', get_post( $page_id )->post_title, 'Institution Dashboard' );
ck( 'holding the block, not the shortcode', get_post( $page_id )->post_content, '<!-- wp:wpcpm/institution-dashboard /-->' );
// The stub answers 'public' for a page with no row, as WordPress does through the registered
// default; a gate that asked the value instead of `metadata_exists()` would read that as
// "already set" and leave the page public. It did, on the live site, once.
ck( 'gated to the Institution role, and not left at the registered default', array( get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ), metadata_exists( 'post', $page_id, WPCPM_Content_Access::META_KEY ) ), array( WPCPM_Roles::ROLE_INSTITUTION, true ) );

$GLOBALS['calls'] = array();
ck( 'a second call creates nothing', WPCPM_Institutions_Dashboard::ensure_page(), $page_id );
ck( 'and inserts no post', array_filter( $GLOBALS['calls'], function ( $c ) { return 'wp_insert_post' === $c[0]; } ), array() );

// A site that has deliberately gated the page to something else keeps its own answer.
update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_MENTOR );
WPCPM_Institutions_Dashboard::ensure_page();
ck( 'and never re-gates a page a site has set for itself', get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ), WPCPM_Roles::ROLE_MENTOR );
update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_INSTITUTION );

// The option lost, the page still there: adopted by slug rather than duplicated.
delete_option( WPCPM_Institutions_Dashboard::OPT_PAGE );
$GLOBALS['calls'] = array();
ck( 'a lost option finds the page by its slug', WPCPM_Institutions_Dashboard::ensure_page(), $page_id );
ck( 'and still inserts nothing', array_filter( $GLOBALS['calls'], function ( $c ) { return 'wp_insert_post' === $c[0]; } ), array() );

ck( 'page_url() is the permalink', WPCPM_Institutions_Dashboard::page_url(), 'https://example.test/institution-dashboard/' );

// A trashed page is not a destination: get_post_status() returns 'trash', which is truthy.
$GLOBALS['posts'][ $page_id ]->post_status = 'trash';
ck( 'a trashed page has no URL', WPCPM_Institutions_Dashboard::page_url(), '' );
$GLOBALS['posts'][ $page_id ]->post_status = 'publish';

/* ---- the title revision -------------------------------------------------- */

ck( 'the title revision is unstamped to begin with', get_option( WPCPM_Institutions_Dashboard::OPT_TITLE_FIXED ), false );
WPCPM_Institutions_Dashboard::maybe_rename_page();
ck(
	'maybe_rename_page() stamps TITLE_VERSION',
	(int) get_option( WPCPM_Institutions_Dashboard::OPT_TITLE_FIXED ),
	WPCPM_Institutions_Dashboard::TITLE_VERSION
);
ck( 'and leaves the title alone: nothing older has shipped', get_post( $page_id )->post_title, 'Institution Dashboard' );

// Renamed by hand, and asked again: the stamp is what stops it, not the title.
$GLOBALS['posts'][ $page_id ]->post_title = 'Our Students';
$GLOBALS['calls']                         = array();
WPCPM_Institutions_Dashboard::maybe_rename_page();
ck( 'a site that renamed the page is not asked twice', get_post( $page_id )->post_title, 'Our Students' );
ck( 'and nothing was written', array_filter( $GLOBALS['calls'], function ( $c ) { return 'wp_update_post' === $c[0]; } ), array() );
$GLOBALS['posts'][ $page_id ]->post_title = 'Institution Dashboard';

/* ---- the assets ---------------------------------------------------------- */

WPCPM_Institutions_Dashboard::register_assets();
ck( 'the stylesheet is registered', isset( $GLOBALS['styles'][ WPCPM_Institutions_Dashboard::STYLE ] ), true );
ck(
	'from assets/css/institution.css',
	$GLOBALS['styles'][ WPCPM_Institutions_Dashboard::STYLE ]['src'],
	WPCPM_PLUGIN_URL . 'assets/css/institution.css'
);
ck(
	'depending on the mentor stylesheet, so the shell is defined once',
	$GLOBALS['styles'][ WPCPM_Institutions_Dashboard::STYLE ]['deps'],
	array( WPCPM_Mentors_Dashboard::STYLE )
);
ck( 'which register_assets() registered for it', isset( $GLOBALS['styles'][ WPCPM_Mentors_Dashboard::STYLE ] ), true );

/* ---- logged out ---------------------------------------------------------- */

$out = render_as( 0 );
ck( 'a logged-out visitor is asked to log in', false !== strpos( $out, 'Please log in' ), true );
ck( 'and is told about no institution at all', false !== strpos( $out, 'Krakowska' ), false );

/* ---- a locked member: the header, the panel, and nothing else ------------ */

delete_option( 'wpcpm_agreement_' . $krakow );
$out = render_as( 2 );

ck( 'a locked member gets the identity header', false !== strpos( $out, 'wpcpm-institution__identity' ), true );
ck( 'naming their institution, trimmed', false !== strpos( $out, '<p class="wpcpm-institution__name">Politechnika Krakowska</p>' ), true );
ck( 'with the city the index knows and the stamp does not', false !== strpos( $out, 'Krakow, Poland.' ), true );

// The two sources disagree about the stage, and the fresh one wins. The stamp is written
// once, by attach(), and no sync ever refreshes it: preferred, it would have this member
// reading the day their account was attached on a header with no read date to say so.
ck( 'and the index\'s stage, because the index is what gets re-read', false !== strpos( $out, 'Stage: Confirmed.' ), true );
ck( 'never the stamp\'s, which has been stale since attach() wrote it', false !== strpos( $out, 'Agreement Sent' ), false );

ck( 'and the panel', false !== strpos( $out, 'data-card="panel"' ), true );
ck( 'and nothing else at all', cards_run(), array( 'panel' ) );
ck( 'no roster markup', false !== strpos( $out, 'data-card="roster"' ), false );
ck( 'no People card', false !== strpos( $out, 'data-card="people"' ), false );
ck( 'no agreement card at the foot', false !== strpos( $out, 'data-card="agreement-card"' ), false );
ck( 'no banner: that is the manager\'s, not theirs', false !== strpos( $out, 'wpcpm-institution__banner' ), false );
ck( 'and no switcher', false !== strpos( $out, 'wpcpm-institution-switcher' ), false );
ck( 'the account-security prompt is chrome and still runs', false !== strpos( $out, '<!-- 2fa -->' ), true );

// The same account, appending the manager's switcher argument by hand.
$out = render_as( 2, array( 'wpcpm_institution_view' => $patil ) );
ck( 'a member cannot switch institutions with a query argument', false !== strpos( $out, 'Patil' ), false );
ck( 'and still sees their own', false !== strpos( $out, 'Krakowska' ), true );

// And what the stamp is for. `WPCPM_Institutions_Index::read()` discards a stored copy at a
// version it does not know, so between a shape change and the next sync no institution has
// a row at all - and a header that went blank at that moment would be a worse answer than a
// header a few weeks old.
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['v'] = 99;
$out = render_as( 2 );
ck( 'with no index row the stamp draws the header', false !== strpos( $out, '<p class="wpcpm-institution__name">Politechnika Krakowska</p>' ), true );
ck( 'stage and all', false !== strpos( $out, 'Stage: Agreement Sent.' ), true );
// Needled on the city *sentence*, because the institution's own name contains the city.
ck( 'and the city only the index ever knew is simply absent', false !== strpos( $out, 'Krakow, Poland' ), false );
ck( 'leaving the country the stamp does hold, with no stray separator', false !== strpos( $out, '>Poland.' ), true );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['v'] = 1;

/* ---- a manager on the same unsettled institution ------------------------- */

$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array(
	'v'               => 1,
	'settled'         => false,
	'site_state'      => WPCPM_Institution_Agreement::SUMMARY_SUBMITTED,
	'airtable_status' => 'Awaiting review',
);

$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );

ck( 'a manager sees the banner', false !== strpos( $out, 'wpcpm-institution__banner' ), true );
ck( 'naming the state in words', false !== strpos( $out, 'not settled: a signed copy is waiting for review' ), true );
ck( 'and what the base says, which is the other half of the predicate', false !== strpos( $out, 'Agreement Status as Awaiting review' ), true );
ck( 'and gets the whole dashboard under it', cards_run(), array( 'panel', 'roster', 'people', 'agreement-card' ) );
ck( 'with the switcher', false !== strpos( $out, 'id="wpcpm-institution-switcher"' ), true );
ck( 'posting the field the resolver actually reads', false !== strpos( $out, 'name="' . WPCPM_Institution_Roster::ARG_VIEW . '"' ), true );
ck( 'listing both institutions', substr_count( $out, '<option value="rec' ), 2 );
ck( 'with the one being viewed selected', false !== strpos( $out, 'value="' . $krakow . '" selected' ), true );
ck( 'and the header falls back to the index, since a manager holds no stamp', false !== strpos( $out, 'Stage: Confirmed.' ), true );
ck( 'and a bare host from the base is given a scheme', false !== strpos( $out, 'href="https://pk.edu.pl"' ), true );
ck( 'and printed without one', false !== strpos( $out, '>pk.edu.pl</a>' ), true );

// Without pretty permalinks the page is addressed by query string, which a GET form drops.
$GLOBALS['queried'] = $page_id;
$out                = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'the switcher carries the page ID when there are no pretty permalinks', false !== strpos( $out, '<input type="hidden" name="page_id" value="' . $page_id . '" />' ), true );
$GLOBALS['opts']['permalink_structure'] = '/%postname%/';
$out                                    = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'and does not when there are', false !== strpos( $out, 'name="page_id"' ), false );
unset( $GLOBALS['opts']['permalink_structure'] );
$GLOBALS['queried'] = 0;

// The switcher, honoured.
$out = render_as( 4, array( 'wpcpm_institution_view' => $patil ) );
ck( 'the switcher moves a manager to another institution', false !== strpos( $out, 'D. Y. Patil' ), true );
ck( 'and the roster it is handed is that institution\'s', $GLOBALS['cards'][1][1], $patil );

// No argument at all: the first institution in the index that has a live member.
$out = render_as( 4 );
ck( 'with no argument a manager lands on the first institution with a member', $GLOBALS['cards'][0][1], $krakow );

/* ---- every state the banner can name ------------------------------------- */

/**
 * The banner a manager sees over the Krakow record, in one agreement state.
 *
 * Through a render because the labelling is private, which is the right way round: what is
 * worth pinning is the sentence a manager reads, not the branch that produced it.
 *
 * @param string $state           A summary state.
 * @param string $airtable_status What the base records, or '' when it records nothing.
 * @return string The rendered page.
 */
function banner_for( $state, $airtable_status = 'Awaiting review' ) {
	global $krakow;

	$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array(
		'v'               => 1,
		'settled'         => false,
		'site_state'      => $state,
		'airtable_status' => $airtable_status,
	);

	return render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
}

// Every state the summary can hold, because the manager's next move depends on which one it
// is: a submitted copy is theirs to review, a `none` is the institution's to act on, and an
// `accepted` or `on_file` reaching this banner at all means the two sources disagree - which
// is neither party's move until somebody looks. A branch nobody renders is a sentence nobody
// has read.
$states = array(
	WPCPM_Institution_Agreement::SUMMARY_GENERATED => 'the template has been generated and no signed copy uploaded',
	WPCPM_Institution_Agreement::SUMMARY_SUBMITTED => 'a signed copy is waiting for review',
	WPCPM_Institution_Agreement::SUMMARY_RETURNED  => 'the last copy was returned',
	WPCPM_Institution_Agreement::SUMMARY_REVOKED   => 'revoked',
	WPCPM_Institution_Agreement::SUMMARY_ACCEPTED  => 'accepted on this site, which Airtable has not confirmed',
	WPCPM_Institution_Agreement::SUMMARY_ON_FILE   => 'on file according to this site, which Airtable has not confirmed',
	WPCPM_Institution_Agreement::SUMMARY_NONE      => 'not started',
);

foreach ( $states as $state => $words ) {
	ck(
		'the banner puts the ' . $state . ' state in words',
		false !== strpos( banner_for( $state ), 'not settled: ' . $words . '.' ),
		true
	);
}

// Read from the agreement module's own source rather than from the stub above, so the table
// cannot quietly fall behind it: a state added there with no sentence here reaches a manager
// as "not started", which is a wrong answer rather than a missing one.
preg_match_all(
	'/const SUMMARY_[A-Z_]+\s*=\s*\'([a-z_]+)\'/',
	(string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' ),
	$declared
);
ck( 'and there is no state it can hold that the banner has no words for', array_values( array_diff( $declared[1], array_keys( $states ) ) ), array() );

// The fall-through. A state this page has no branch for reads as the one the summary means
// by "nothing has happened yet", rather than leaving the sentence with a hole in it.
ck(
	'and anything it has no branch for reads as not started',
	false !== strpos( banner_for( 'astonished' ), 'not settled: not started.' ),
	true
);

// The other half of the predicate: the gate is closed when either source says so, and a
// manager reading "a signed copy is waiting" needs to know whether the base is what is
// holding it. Drawn only when the base has an answer - a line reading "records the Agreement
// Status as" with nothing after it would look like the bug it is not.
$out = banner_for( WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, 'Sent to institution' );
ck( 'and names what the base records', false !== strpos( $out, 'Agreement Status as Sent to institution.' ), true );

$out = banner_for( WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, '' );
ck( 'and says nothing about the base when the base says nothing', false !== strpos( $out, 'Agreement Status' ), false );
ck( 'though the banner itself is still drawn', false !== strpos( $out, 'wpcpm-institution__banner' ), true );
ck( 'with the state in it', false !== strpos( $out, 'not settled: a signed copy is waiting for review.' ), true );

/* ---- a settled member ---------------------------------------------------- */

$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array(
	'v'               => 1,
	'settled'         => true,
	'site_state'      => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED,
	'airtable_status' => 'Accepted',
);

$out = render_as( 2 );
ck( 'a settled member gets the whole dashboard', cards_run(), array( 'panel', 'roster', 'people', 'agreement-card' ) );
ck( 'and no banner, because there is nothing outstanding', false !== strpos( $out, 'wpcpm-institution__banner' ), false );
ck( 'and no switcher: it is drawn under CAP_MANAGE and nowhere else', false !== strpos( $out, 'wpcpm-institution-switcher' ), false );

/* ---- somebody who is neither --------------------------------------------- */

// Asserted against what the shared helper returns rather than against a sentence written
// out here: the three dashboards answer this question with one wording, and a page holding
// a private copy of it - which this one did - is a second wording that ships.
$out = render_as( 5 );
ck( 'a stranger is told this page is not theirs', false !== strpos( $out, WPCPM_Dashboards::nothing_to_show( 'institutions', false ) ), true );
ck( 'in the shared words and no others', false !== strpos( $out, 'not linked to one' ), false );
ck( 'and no institution is named', array( false !== strpos( $out, 'Krakowska' ), false !== strpos( $out, 'Patil' ) ), array( false, false ) );
ck( 'and no card ran', cards_run(), array() );

// The same, with another institution's record asked for by hand.
$out = render_as( 5, array( 'wpcpm_institution_view' => $patil ) );
ck( 'and asking for one by record ID changes nothing', false !== strpos( $out, 'Patil' ), false );

/* ---- a manager with nothing to show -------------------------------------- */

// The manager half of the same helper, which no other suite reaches through this page. The
// resolver falls back to the first institution *with a live member*, so an index full of
// institutions can still resolve to nothing, and the sentence points at the screen that
// provisions rather than telling anybody to run a sync.
$rows = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['rows'];
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['rows'] = array();
$out = render_as( 4 );
ck( 'a manager with no institution to show gets the shared wording too', false !== strpos( $out, WPCPM_Dashboards::nothing_to_show( 'institutions', true ) ), true );
ck( 'which is a link to the Institutions screen', false !== strpos( $out, 'admin.php?page=wpcpm-institutions' ), true );
ck( 'and never says "add one from"', false !== strpos( $out, 'Add one from' ), false );
ck( 'and no card ran for them either', cards_run(), array() );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['rows'] = $rows;

/* ---- what the cards are handed ------------------------------------------- */

render_as( 2, array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_status' => 'waiting', 'wpcpm_roster_search' => 'Nowak' ) );
$context = $GLOBALS['cards'][1][2];

ck( 'the cohort survives its capital H, because it is read as text', $context['cohort'], '2026-H1' );
ck( 'the filters are read once and handed over', $context['filters'], array( 'status' => 'waiting', 'search' => 'Nowak' ) );
ck( 'a member is not told they can manage', $context['can_manage'], false );
ck( 'and the read time is the roster index\'s, not now', $context['read'], 1756100000 );

// The filter bar is the roster view's, so its argument names are the ones that count. The
// shell reading names of its own would look like it worked and filter nothing.
render_as( 2, array( 'wpcpm_status' => 'waiting', 'wpcpm_search' => 'Nowak' ) );
ck( 'and only under the roster view\'s own argument names', $GLOBALS['cards'][1][2]['filters'], array( 'status' => '', 'search' => '' ) );

render_as( 2, array( 'wpcpm_cohort' => 'sometime' ) );
ck( 'junk in the cohort argument reads as no cohort', $GLOBALS['cards'][1][2]['cohort'], '' );

render_as( 2, array( 'wpcpm_cohort' => WPCPM_Cohort::NONE ) );
ck( 'and the empty bucket is a cohort like any other', $GLOBALS['cards'][1][2]['cohort'], WPCPM_Cohort::NONE );

// A group the roster view does not have is not a group. The shell hands the answer straight
// to the roster, so a status it never validated would narrow the list to nothing at all.
render_as( 2, array( 'wpcpm_roster_status' => 'expelled' ) );
ck( 'an unknown group reads as no filter', $GLOBALS['cards'][1][2]['filters']['status'], '' );

render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'a manager is told so', $GLOBALS['cards'][0][2]['can_manage'], true );

// An institution with no roster option yet: a read time of 0, not a warning.
render_as( 4, array( 'wpcpm_institution_view' => $patil ) );
ck( 'an institution with no index read yet reads 0', $GLOBALS['cards'][0][2]['read'], 0 );

/* ---- the optional heading ------------------------------------------------ */

$out = render_as( 2, array(), array( 'title' => 'Our students' ) );
ck( 'the block\'s heading is printed when it is set', false !== strpos( $out, '<h2 class="wpcpm-dashboard__title">Our students</h2>' ), true );
$out = render_as( 2 );
ck( 'and nothing is printed when it is not', false !== strpos( $out, 'wpcpm-dashboard__title' ), false );

/* ---- routing ------------------------------------------------------------- */

$GLOBALS['uid'] = 2;
ck(
	'a member landing on wp-login goes to their dashboard',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
	'https://example.test/institution-dashboard/'
);
ck(
	'a member who asked for somewhere in particular is taken there',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/guides/getting-started/', $GLOBALS['users'][2] ),
	'https://example.test/wp-admin/'
);
ck(
	'a program manager is left in wp-admin',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][4] ),
	'https://example.test/wp-admin/'
);
ck(
	'a stranger is left alone',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ),
	'https://example.test/wp-admin/'
);
// Open question 12: mentoring is the time-critical half, so the mentor page wins.
ck(
	'an account that mentors and is a member lands on the Mentor Report Card',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][6] ),
	'https://example.test/wp-admin/'
);
// The setting is what turns the routing off, and it turns off both halves.
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = array( 'institution_home' => false );
ck(
	'and nobody is routed when institution_home is off',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
	'https://example.test/wp-admin/'
);
unset( $GLOBALS['opts'][ WPCPM_Settings::OPTION ] );

/* ---- the admin dashboard ------------------------------------------------- */

/**
 * What `replace_admin_dashboard()` did, as a string.
 *
 * @param int    $uid     Who is asking.
 * @param string $pagenow The admin screen they are on.
 * @return string The redirect target, or ''.
 */
function admin_hop( $uid, $pagenow ) {
	$GLOBALS['uid']     = (int) $uid;
	$GLOBALS['pagenow'] = $pagenow;
	try {
		WPCPM_Institutions_Dashboard::replace_admin_dashboard();
	} catch ( Exception $e ) {
		return str_replace( 'redirect: ', '', $e->getMessage() );
	}
	return '';
}

ck( 'a member on the wp-admin Dashboard is moved to their page', admin_hop( 2, 'index.php' ), 'https://example.test/institution-dashboard/' );
ck( 'and is left alone on their own profile screen', admin_hop( 2, 'profile.php' ), '' );
ck( 'a program manager keeps wp-admin', admin_hop( 4, 'index.php' ), '' );
$GLOBALS['ajax'] = true;
ck( 'and an ajax request is never redirected', admin_hop( 2, 'index.php' ), '' );
$GLOBALS['ajax'] = false;

/* ---- init and register --------------------------------------------------- */

$GLOBALS['calls'] = array();
WPCPM_Institutions_Dashboard::init();
$hooks = array_map( function ( $c ) { return $c[0] . ':' . $c[1]; }, $GLOBALS['calls'] );
ck(
	'init() hooks the register, the rename, the editor style and both routes',
	array(
		in_array( 'add_action:init', $hooks, true ),
		in_array( 'add_action:enqueue_block_editor_assets', $hooks, true ),
		in_array( 'add_filter:login_redirect', $hooks, true ),
		in_array( 'add_action:admin_init', $hooks, true ),
	),
	array( true, true, true, true )
);

$GLOBALS['calls'] = array();
WPCPM_Institutions_Dashboard::register();
$names = array_map( function ( $c ) { return $c[0] . ':' . $c[1]; }, $GLOBALS['calls'] );
ck( 'register() adds the shortcode', in_array( 'add_shortcode:' . WPCPM_Institutions_Dashboard::SHORTCODE, $names, true ), true );
ck(
	'and registers the block from its own directory',
	in_array( 'register_block_type:' . WPCPM_PLUGIN_DIR . 'blocks/institution-dashboard', $names, true ),
	true
);

/* ---- the block's own files ----------------------------------------------- */

$block = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/institution-dashboard/block.json' ), true );
ck( 'block.json parses', is_array( $block ), true );
ck( 'and names the block the class does', $block['name'], WPCPM_Institutions_Dashboard::BLOCK );
ck( 'with this plugin\'s text domain', $block['textdomain'], 'wpcredits-program-manager' );
ck( 'and points at the hand-written editor script', $block['editorScript'], 'file:./editor.js' );

$asset = require WPCPM_PLUGIN_DIR . 'blocks/institution-dashboard/editor.asset.php';
ck( 'editor.asset.php declares the wp.* globals editor.js reads', $asset['dependencies'], array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ) );
ck( 'at the same version block.json carries', $asset['version'], $block['version'] );

/* ---- the Resources section, and the person in it ------------------------- */

echo "\n=== Where to go with a question ===\n";

// The section is the one the two Report Cards end on, asked for this audience. What this page
// adds to it is the person: every institution is routed to a program manager by country, and
// until now that name appeared only in a revoked agreement's panel, which is the worst moment
// to meet it for the first time.
$GLOBALS['routing'] = array(
	'recCOUNTRYPOLAND1' => array(
		'name'     => 'Poland',
		'manager'  => 'Ola Nowak',
		'email'    => 'ola@example.org',
		'calendly' => 'https://calendly.com/example/intro',
	),
);

$GLOBALS['resources'] = array();
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );

ck( 'the section is drawn, for the institution audience', $GLOBALS['resources'], array( 'institution' ) );
ck( 'and it names the person, their address and the way to book them', array(
	false !== strpos( $out, 'Your contact at the program' ),
	false !== strpos( $out, 'Ola Nowak' ),
	false !== strpos( $out, 'mailto:ola@example.org' ),
	false !== strpos( $out, 'https://calendly.com/example/intro' ),
), array( true, true, true, true ) );

// A country the program has not routed prints nothing at all. A heading with a blank under it
// would read as "you have nobody", which is not what an unrouted country means.
$GLOBALS['resources'] = array();
$out = render_as( 4, array( 'wpcpm_institution_view' => $patil ) );

ck( 'an unrouted country still gets the section', $GLOBALS['resources'], array( 'institution' ) );
ck( 'but no contact block, and no empty heading', array(
	false !== strpos( $out, 'wpcpm-resources__contact' ),
	false !== strpos( $out, 'Your contact at the program' ),
), array( false, false ) );

// A record ID in the manager field means the link was never resolved to a person. It is a
// database key, and printing one at a school is worse than printing nothing.
$GLOBALS['routing']['recCOUNTRYINDIA01'] = array(
	'name'     => 'India',
	'manager'  => 'recUNRESOLVED0001',
	'email'    => 'india@example.org',
	'calendly' => '',
);
$out = render_as( 4, array( 'wpcpm_institution_view' => $patil ) );

ck( 'an unresolved manager link is not shown as a name', false !== strpos( $out, 'recUNRESOLVED0001' ), false );
ck( 'though the address behind it still is', false !== strpos( $out, 'mailto:india@example.org' ), true );

// The locked account is the one most likely to need somebody to ask.
$GLOBALS['resources'] = array();
$locked = render_as( 2 );

ck( 'a locked account gets the section too', $GLOBALS['resources'], array( 'institution' ) );
ck( 'with its own contact in it', false !== strpos( $locked, 'Ola Nowak' ), true );

$GLOBALS['routing'] = array();


echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
