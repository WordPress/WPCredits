<?php
/**
 * The sponsor application form: its guards, its eight questions, its two logos and its two mails.
 *
 * Design spec of 4 September 2026, section 9.2. What each block pins, and why:
 *
 * - **The base's spelling is the fixture's.** Every one of the six columns that reach Airtable,
 *   and the three choices of `Sponsorship options`, are compared byte for byte against
 *   `bin/fixtures/sponsors-table-fields.json`; the apostrophe in "Anything else you'd like to
 *   share." is ASCII, unlike the institution table's U+2019, and the full stop is part of the
 *   name.
 * - **The seven guards run in the institution form's order**, through `WPCPM_Form_Guard`, and
 *   each one is exercised at its boundary with real submissions rather than by calling the guard.
 * - **Consent is a precondition and nothing else will do.** Absent, `"0"`, `"yes"` and `""` each
 *   refuse the whole submission and store nothing; a refusal hands back every answer and never
 *   the tick.
 * - **The two logos go through the image handler, after the ceiling and before storage.** An SVG
 *   named `.png` is a problem on the logo question, nothing is stored, and the answers come back;
 *   a spam row stores no attachment; an accepted file lands in the Media Library with author 0
 *   and the title the logo card uses.
 * - **Duplicates are two flags and no hold.** Another open application with the same address or
 *   name, and a sponsor already in the index with the same name or website host. Both rows stay
 *   new, both flags are read back by the queue.
 * - **Two mails, gated differently.** The applicant hears for every row that is not spam; the
 *   managers only for a `new` one, through the sponsors module's own setting.
 *
 * `WPCPM_Form_Guard`, `WPCPM_Ceiling`, `WPCPM_Request`, `WPCPM_Field_Value`, `WPCPM_Image_Upload`,
 * `WPCPM_Sponsors_Index` and `WPCPM_Roles` are the real files. Real GD images and a real editor
 * stand-in, as bin/test-image-upload.php uses.
 *
 * Run from the plugin root:  php bin/test-sponsor-application.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']        = array();
$GLOBALS['autoload']    = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['transients']  = array();
$GLOBALS['mail']        = array();
$GLOBALS['managermail'] = array();
$GLOBALS['settings']    = array();
$GLOBALS['policy']      = 'https://example.test/privacy/';
$GLOBALS['caps']        = false;
$GLOBALS['uid']         = 0;
$GLOBALS['next_id']     = 500;
$GLOBALS['clock']       = 1788322400;
$GLOBALS['disallowed']  = false;
$GLOBALS['password']    = 0;
$GLOBALS['enqueued']    = array();
$GLOBALS['nocache']     = 0;
$GLOBALS['on_page']     = 0;
$GLOBALS['attachments'] = array();
$GLOBALS['temp_files']  = array();
$GLOBALS['store_fails'] = false;

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_author = 0, $post_title = '', $post_date = '', $post_modified_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) $n; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses( $html, $allowed = array() ) { return strip_tags( (string) $html, '<a>' ); }
function esc_url( $u ) { return (string) $u; }
function esc_url_raw( $u, $protocols = null ) { return preg_match( '#^https?://[^\s<>"]+$#i', (string) $u ) ? (string) $u : ''; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( wp_strip_all_tags( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) preg_match( '/^[^@\s]+@[^@\s.]+\.[^@\s]+$/', (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9_.-]/', '-', (string) $s ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function absint( $v ) { return abs( (int) $v ); }
function checked( $a, $b = true, $echo = true ) { return $a === $b ? ' checked="checked"' : ''; }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['hooks'][] = $h; }
function add_filter( $h, $c, $p = 10, $a = 1 ) {}
function add_shortcode( $t, $c ) {}
function register_post_type( $t, $a = array() ) { $GLOBALS['post_types'][ $t ] = $a; return (object) $a; }
function apply_filters( $hook, $value ) { return $value; }
function wp_check_comment_disallowed_list( $author, $email, $url, $comment, $ip, $agent ) { return (bool) $GLOBALS['disallowed']; }
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style( $h, $s, $d = array(), $v = false ) {}
function wp_register_script( $h, $s, $d = array(), $v = false, $f = false ) {}
function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
require_once __DIR__ . '/stubs/caps.php';
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function get_privacy_policy_url() { return (string) $GLOBALS['policy']; }
function nocache_headers() { ++$GLOBALS['nocache']; }
function is_page( $id = 0 ) { return (int) $id === (int) ( $GLOBALS['on_page'] ?? 0 ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function get_temp_dir() { return sys_get_temp_dir() . '/'; }

function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = is_array( $args[0] ) ? $args[1] : $args[2];
	foreach ( $pairs as $key => $value ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( (string) $value );
	}
	return $url;
}
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die: ' . $m ); }

function wp_hash( $data, $scheme = 'auth' ) { return md5( 'test-salt|' . (string) $data ); }
function wp_create_nonce( $action = -1 ) { return 'nonce-' . $action; }
function wp_verify_nonce( $nonce, $action = -1 ) { return 'nonce-' . $action === (string) $nonce ? 1 : false; }
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	return substr( str_repeat( 'AbCdEf0123456789', 4 ), 0, (int) $length - 4 ) . sprintf( '%04d', ++$GLOBALS['password'] );
}

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $a; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }

function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }

function wp_insert_post( $a, $error = false ) {
	if ( ! empty( $GLOBALS['post_fails'] ) ) {
		return $error ? new WP_Error( 'wpcpm_test_insert', 'refused' ) : 0;
	}
	$post                          = new WP_Post();
	$post->ID                      = $GLOBALS['next_id']++;
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	// WordPress runs a title through kses on the way in for anybody without `unfiltered_html`,
	// which every applicant is, and kses normalizes a bare `&` to `&amp;`. Modeled here since
	// the S5 fix wave, because the duplicate and in-base comparisons used to read this field
	// and so missed every company with an ampersand in its name.
	$post->post_title              = str_replace( '&', '&amp;', (string) ( $a['post_title'] ?? '' ) );
	$post->post_date               = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$post->post_modified_gmt       = $post->post_date;
	$GLOBALS['clock']             += 60;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; }
function get_post_time( $f = 'U', $gmt = false, $post = null ) { return $post instanceof WP_Post ? (int) strtotime( $post->post_date . ' UTC' ) : 0; }
function get_permalink( $id ) { return 'https://example.test/sponsor-application/'; }
function get_page_by_path( $slug ) { foreach ( $GLOBALS['posts'] as $p ) { if ( 'page' === $p->post_type && $slug === ( $GLOBALS['slugs'][ $p->ID ] ?? '' ) ) { return $p; } } return null; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }

/** `get_posts()` as this class uses it: one type, one status, one IN clause, oldest first by default, or newest-by-meta for `decided_posts()` (1.98.1). */
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $a['post_type'] ) && $post->post_type !== $a['post_type'] ) { continue; }
		if ( isset( $a['post_status'] ) && 'any' !== $a['post_status'] && $post->post_status !== $a['post_status'] ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			foreach ( $a['meta_query'] as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
				$have = get_post_meta( $post->ID, $clause['key'], true );
				if ( isset( $clause['compare'] ) && 'IN' === $clause['compare'] ) {
					if ( ! in_array( $have, (array) $clause['value'], true ) ) { continue 2; }
				} elseif ( $have !== $clause['value'] ) {
					continue 2;
				}
			}
		}
		$out[] = $post;
	}
	// decided_posts() (1.98.1) orders by a meta value instead of the post date; this is the
	// one other shape this class asks for, so it is the one other shape this stub answers.
	if ( isset( $a['orderby'] ) && 'meta_value_num' === $a['orderby'] && isset( $a['meta_key'] ) ) {
		$meta_key = $a['meta_key'];
		$desc     = isset( $a['order'] ) && 'DESC' === $a['order'];
		usort( $out, function ( $x, $y ) use ( $meta_key, $desc ) {
			$by_meta = (int) get_post_meta( $x->ID, $meta_key, true ) - (int) get_post_meta( $y->ID, $meta_key, true );
			return $desc ? -$by_meta : $by_meta;
		} );
	} else {
		usort( $out, function ( $x, $y ) {
			$by_date = strcmp( $x->post_date, $y->post_date );
			return 0 !== $by_date ? $by_date : $x->ID - $y->ID;
		} );
	}
	if ( isset( $a['numberposts'] ) && (int) $a['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $a['numberposts'] );
	}
	if ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) {
		return array_map( function ( $p ) { return $p->ID; }, $out );
	}
	return $out;
}

// The Media Library, in miniature: the image handler's store() writes a real file into a
// temporary uploads directory and records the attachment here.
function wp_upload_dir() { $dir = sys_get_temp_dir() . '/wpcpm-sapp-uploads-' . getmypid(); if ( ! is_dir( $dir ) ) { mkdir( $dir ); } return array( 'path' => $dir, 'url' => 'https://example.test/uploads', 'error' => false ); }
function wp_unique_filename( $dir, $name ) { $i = 0; $try = $name; while ( file_exists( $dir . '/' . $try ) ) { $try = preg_replace( '/(\.[a-z]+)$/', '-' . ( ++$i ) . '$1', $name ); } return $try; }
function wp_insert_attachment( array $a, $file, $parent = 0, $wp_error = false ) {
	if ( ! empty( $GLOBALS['store_fails'] ) ) { return new WP_Error( 'wpcpm_test_attach', 'refused' ); }
	$id = 200 + count( $GLOBALS['attachments'] );
	$GLOBALS['attachments'][ $id ] = array_merge( $a, array( 'file' => $file ) );
	return $id;
}
function wp_generate_attachment_metadata( $id, $file ) { return array( 'file' => basename( $file ) ); }
function wp_update_attachment_metadata( $id, $data ) { return true; }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function wp_get_attachment_url( $id ) { return isset( $GLOBALS['attachments'][ $id ] ) ? 'https://example.test/uploads/' . basename( $GLOBALS['attachments'][ $id ]['file'] ) : false; }
class WPCPM_Test_Editor {
	private $path;
	public function __construct( $path ) { $this->path = $path; }
	public function save( $dest, $mime = null ) { copy( $this->path, $dest ); $size = getimagesize( $dest ); return array( 'path' => $dest, 'file' => basename( $dest ), 'width' => $size[0], 'height' => $size[1], 'mime-type' => $size['mime'] ); }
}
function wp_get_image_editor( $path ) { return new WPCPM_Test_Editor( $path ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

// Two checks below count the image handler's temporary copies left in the system temp
// directory, so a copy another suite's crashed run left behind must not be counted here.
foreach ( glob( sys_get_temp_dir() . '/wpcpm-image-*' ) as $leftover ) {
	if ( is_file( $leftover ) ) { unlink( $leftover ); }
}

/* ---- the other pieces, stubbed to their contracts ----------------------- */

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get() { return $GLOBALS['settings']; }
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}
class WPCPM_Mail {
	public static function send_to( $email, $context, $build, $locale = '' ) {
		if ( ! is_email( $email ) || ! is_callable( $build ) ) { return false; }
		if ( ! empty( $GLOBALS['mail_fails'] ) ) { return false; }
		$mail              = (array) call_user_func( $build, $email );
		$mail['to']        = $email;
		$mail['context']   = $context;
		$GLOBALS['mail'][] = $mail;
		return true;
	}
	public static function site_name() { return 'WordPress Education'; }
	// The manager's Reply-To, at its contract, for the question part 2 sends.
	public static function reply_to( $person ) { return is_object( $person ) && '' !== (string) $person->user_email ? array( 'Reply-To: ' . $person->user_email ) : array(); }
}
class WPCPM_Institutions {
	const FLASH = 'institutions';
	public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' ) {
		if ( ! is_callable( $build ) ) { return 0; }
		$mail                     = (array) call_user_func( $build, null );
		$mail['context']          = $context;
		$mail['setting']          = $setting_key;
		$GLOBALS['managermail'][] = $mail;
		return 1;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-image-upload.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php';

$fail = 0; $checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * Forget every option, post, transient, attachment and message, and put the site back in a
 * state where the form renders: applications on, a privacy policy, a published page, an index.
 */
function reset_world() {
	$GLOBALS['opts']        = array();
	$GLOBALS['autoload']    = array();
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['transients']  = array();
	$GLOBALS['mail']        = array();
	$GLOBALS['managermail'] = array();
	$GLOBALS['attachments'] = array();
	$GLOBALS['caps']        = false;
	$GLOBALS['uid']         = 0;
	$GLOBALS['policy']      = 'https://example.test/privacy/';
	$GLOBALS['disallowed']  = false;
	$GLOBALS['post_fails']  = false;
	$GLOBALS['store_fails'] = false;
	$GLOBALS['enqueued']    = array();
	$GLOBALS['nocache']     = 0;
	$GLOBALS['on_page']     = 0;
	$GLOBALS['slugs']       = array();
	$GLOBALS['settings']    = array(
		'sponsor_applications_enabled' => true,
		'application_trusted_proxy'    => '',
		'logo_max_kb'                  => 1024,
	);

	$_POST                      = array();
	$_GET                       = array();
	$_FILES                     = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

	$page              = new WP_Post();
	$page->ID          = 42;
	$page->post_type   = 'page';
	$page->post_status = 'publish';
	$page->post_title  = 'Sponsor the WordPress Credits Program';
	$page->post_date   = '2026-09-01 09:00:00';

	$GLOBALS['posts'][42] = $page;
	$GLOBALS['slugs'][42] = 'sponsor-application';
	$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_PAGE ] = 42;

	$policy                    = new WP_Post();
	$policy->ID                = 43;
	$policy->post_type         = 'page';
	$policy->post_status       = 'publish';
	$policy->post_title        = 'Privacy';
	$policy->post_date         = '2026-08-01 09:00:00';
	$policy->post_modified_gmt = '2026-08-20 11:30:00';

	$GLOBALS['posts'][43]                          = $policy;
	$GLOBALS['opts']['wp_page_for_privacy_policy'] = 43;

	// An index of two sponsors, written through the real index class: one whose name is stored
	// with the trailing space the base keeps, one whose website carries www and a path.
	WPCPM_Sponsors_Index::write(
		array(
			'recSPN00000000001' => array( 'name' => 'Widgetry Ltd ', 'website' => 'https://www.widgetry.example/about', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
			'recSPN00000000002' => array( 'name' => 'Old Sponsor', 'website' => 'old-sponsor.example', 'status' => 'Paused', 'contact_email' => 'maciej@a8c.com' ),
		),
		1757000000
	);
}

/** A dwell token of a given age, signed the way the guard signs one for this form, or for another form when given its scope. */
function dwell_token( $age = 30, $scope = WPCPM_Sponsor_Application::DWELL_SCOPE ) {
	$issued = time() - (int) $age;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( $scope . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Sponsor_Application::ACTION_SUBMIT ) ), 0, 32 );
}

/** The answers a good application carries, keyed by form key. */
function answers( array $overrides = array() ) {
	$values = array(
		'Company Name'                      => 'Gadgetry Inc',
		'Website'                           => 'gadgetry.example',
		'Contact Person Full Name'          => 'Sam Sponsor',
		'Contact Email'                     => 'maciej@a8c.com',
		'Sponsorship options'               => 'Sponsor mentors + tools/services',
		"Anything else you'd like to share." => 'We make gadgets and would like to help.',
		'Privacy Policy Compliance'         => '1',
	);

	foreach ( $overrides as $column => $value ) {
		if ( null === $value ) {
			unset( $values[ $column ] );
			continue;
		}

		$values[ $column ] = $value;
	}

	$posted = array();

	foreach ( $values as $column => $value ) {
		$posted[ WPCPM_Sponsor_Application::form_key( $column ) ] = $value;
	}

	return $posted;
}

/** A real PNG on disk. */
function png( $w, $h ) {
	$p  = tempnam( sys_get_temp_dir(), 'wpcpm-sapp-' ) . '.png';
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 30, 30, 200 ) );
	imagepng( $im, $p );
	$GLOBALS['temp_files'][] = $p;

	return $p;
}

/** A file that claims to be a PNG and is an SVG. */
function fake_svg() {
	$p = tempnam( sys_get_temp_dir(), 'wpcpm-sapp-' ) . '.png';
	file_put_contents( $p, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><script>alert(1)</script></svg>' );
	$GLOBALS['temp_files'][] = $p;

	return $p;
}

/**
 * Put one or both logo files in `$_FILES`, the way a browser would.
 *
 * @param string $color A path, or '' for no color file.
 * @param string $white A path, or '' for no white file.
 */
function post_logos( $color, $white = '' ) {
	$_FILES = array();

	if ( '' !== $color ) {
		$_FILES[ WPCPM_Sponsor_Application::FIELD_LOGO_COLOR ] = array( 'name' => 'logo.png', 'type' => 'image/png', 'tmp_name' => $color, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize( $color ) );
	}

	if ( '' !== $white ) {
		$_FILES[ WPCPM_Sponsor_Application::FIELD_LOGO_WHITE ] = array( 'name' => 'logo-white.png', 'type' => 'image/png', 'tmp_name' => $white, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize( $white ) );
	}
}

/**
 * Read a redirect the way the page behind it would.
 *
 * @param string $url Where the handler redirected to.
 * @return array `outcome`, `stash`, `url`, `id`.
 */
function landed( $url ) {
	$id    = '';
	$parts = array();

	if ( preg_match( '/[?&]' . WPCPM_Sponsor_Application::QUERY_STASH . '=([^&]+)/', $url, $parts ) ) {
		$id = $parts[1];
	}

	$stash = '' === $id ? array() : ( $GLOBALS['transients'][ WPCPM_Sponsor_Application::TRANSIENT_PREFIX . $id ] ?? array() );

	if ( '' === $id && preg_match( '/[?&]' . WPCPM_Sponsor_Application::QUERY_OUTCOME . '=([^&]+)/', $url, $parts ) ) {
		$stash = array( 'outcome' => $parts[1] );
	}

	return array(
		'outcome' => isset( $stash['outcome'] ) ? $stash['outcome'] : '',
		'stash'   => $stash,
		'url'     => $url,
		'id'      => $id,
	);
}

/** Put a redirect's query string into `$_GET`, which is what the applicant's browser does next. */
function follow( $url ) {
	$_GET  = array();
	$query = (string) parse_url( $url, PHP_URL_QUERY );

	if ( '' !== $query ) {
		parse_str( $query, $_GET );
	}
}

/**
 * Post the form once and answer with what came back.
 *
 * `$_FILES` is left as the caller set it through `post_logos()`, and cleared afterwards so no
 * later submission inherits a file.
 *
 * @param array $answers What to post, from `answers()`.
 * @param array $extra   `token`, `honeypot`, `nonce`, `ip`.
 * @return array `outcome`, `stash`, `url`, `id`.
 */
function submit( array $answers, array $extra = array() ) {
	$_POST = array(
		'action'                                   => WPCPM_Sponsor_Application::ACTION_SUBMIT,
		'_wpnonce'                                 => array_key_exists( 'nonce', $extra ) ? $extra['nonce'] : wp_create_nonce( WPCPM_Sponsor_Application::ACTION_SUBMIT ),
		WPCPM_Sponsor_Application::FIELD_ANSWERS   => $answers,
		WPCPM_Sponsor_Application::TOKEN_FIELD     => array_key_exists( 'token', $extra ) ? $extra['token'] : dwell_token( 30 + ( $GLOBALS['password'] % 600 ) ),
		WPCPM_Sponsor_Application::HONEYPOT        => isset( $extra['honeypot'] ) ? $extra['honeypot'] : '',
	);

	if ( isset( $extra['ip'] ) ) {
		$_SERVER['REMOTE_ADDR'] = $extra['ip'];
	}

	$url = '';

	try {
		WPCPM_Sponsor_Application::handle_submit();
	} catch ( Exception $e ) {
		$url = str_replace( 'redirect: ', '', $e->getMessage() );
	}

	$_FILES = array();

	return landed( $url );
}

/** One field of one message, or an empty string when that message was never sent. */
function mail_said( $index, $key ) {
	$index = (int) $index < 0 ? count( $GLOBALS['mail'] ) + (int) $index : (int) $index;

	return isset( $GLOBALS['mail'][ $index ][ $key ] ) ? (string) $GLOBALS['mail'][ $index ][ $key ] : '';
}

/** Every application row, newest last, as the queue would read them. */
function stored() {
	return WPCPM_Sponsor_Application::applications( WPCPM_Sponsor_Application::states() );
}

/** The one application row this test just made. */
function only_row() {
	$rows = stored();

	return count( $rows ) === 1 ? $rows[0] : null;
}

/** One method's body, for the assertions that read the source. */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

$src     = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php' );
$fixture = json_decode( file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/sponsors-table-fields.json' ), true );

echo "\n-- the base's spelling, byte for byte --------------------------------\n";

$missing = array();

foreach ( array_keys( WPCPM_Sponsor_Application::fields() ) as $column ) {
	if ( ! in_array( $column, $fixture['fields'], true ) ) {
		$missing[] = $column;
	}
}

ck( 'every one of the eight columns is in the fixture', $missing, array() );
ck( 'and there are eight of them, in the Airtable form\'s order', array_keys( WPCPM_Sponsor_Application::fields() ), array( 'Company Name', 'Website', 'Contact Person Full Name', 'Contact Email', 'Sponsorship options', 'Logo', "Anything else you'd like to share.", 'Privacy Policy Compliance' ) );
ck( 'the three choices are the column\'s own, in order', WPCPM_Sponsor_Application::support_choices(), $fixture['choices']['Sponsorship options'] );
ck( 'the "anything else" column ends with a full stop and carries an ASCII apostrophe', bin2hex( WPCPM_Sponsor_Application::COL_ANYTHING ), bin2hex( "Anything else you'd like to share." ) );
ck( 'the radio question is labelled the way the Airtable form asks it', WPCPM_Sponsor_Application::fields()['Sponsorship options']['label'], 'How would you like to support WP Credits?' );

$keys = array();

foreach ( array_keys( WPCPM_Sponsor_Application::fields() ) as $column ) {
	$keys[] = WPCPM_Sponsor_Application::form_key( $column );
}

ck( 'the eight form keys are distinct', count( array_unique( $keys ) ), 8 );
ck( 'the server holds two columns of its own', WPCPM_Sponsor_Application::server_held(), array( 'Logo', 'Privacy Policy Compliance' ) );
ck( 'the post type is seventeen characters, inside the limit', array( WPCPM_Sponsor_Application::POST_TYPE, strlen( WPCPM_Sponsor_Application::POST_TYPE ) <= 20 ), array( 'wpcpm_sponsor_app', true ) );
ck( 'every meta key carries the stem', count( preg_grep( '/^_wpcpm_sapp_/', array( WPCPM_Sponsor_Application::META_FIELDS, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::META_REFERENCE, WPCPM_Sponsor_Application::META_CONSENT, WPCPM_Sponsor_Application::META_SIGNALS, WPCPM_Sponsor_Application::META_EMAIL, WPCPM_Sponsor_Application::META_LOGOS, WPCPM_Sponsor_Application::META_RECORD, WPCPM_Sponsor_Application::META_USER, WPCPM_Sponsor_Application::META_EVENT ) ) ), 10 );

echo "\n-- one answer at a time ----------------------------------------------\n";

reset_world();
$clean = WPCPM_Sponsor_Application::clean( 'Company Name', '  Gadgetry Inc  ' );
ck( 'a name is trimmed and kept', array( $clean['ok'], $clean['value'] ), array( true, 'Gadgetry Inc' ) );
ck( 'and cut to the column\'s length rather than refused', mb_strlen( WPCPM_Sponsor_Application::clean( 'Company Name', str_repeat( 'a', 300 ) )['value'] ), 200 );
ck( 'an address that is not one is refused by name', WPCPM_Sponsor_Application::clean( 'Contact Email', 'sam at gadgetry' )['problem'], 'bad_email' );
ck( 'a scheme-less website gets the scheme rather than a telling-off', WPCPM_Sponsor_Application::clean( 'Website', 'gadgetry.example' )['value'], 'https://gadgetry.example' );
ck( 'a choice is the column\'s own, byte for byte', WPCPM_Sponsor_Application::clean( 'Sponsorship options', 'Sponsor one or multiple mentors' )['value'], 'Sponsor one or multiple mentors' );
ck( 'a choice spelled any other way is refused', WPCPM_Sponsor_Application::clean( 'Sponsorship options', 'sponsor one or multiple mentors' )['problem'], 'bad_choice' );
ck( 'no choice is an empty answer, for requiredness to catch', WPCPM_Sponsor_Application::clean( 'Sponsorship options', '' )['value'], '' );
ck( 'consent: "1" and "true" are ticks, "yes" and "0" are not', array( WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', '1' )['value'], WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', 'true' )['value'], WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', 'yes' )['value'], WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', '0' )['value'] ), array( true, true, false, false ) );
ck( 'the logo is not an answer clean() takes', WPCPM_Sponsor_Application::clean( 'Logo', 'anything' )['problem'], 'unknown_field' );
ck( 'a column nobody asked about is refused rather than stored', WPCPM_Sponsor_Application::clean( 'Notes', 'anything' )['problem'], 'unknown_field' );

echo "\n-- what is drawn, and when nothing is ---------------------------------\n";

reset_world();
$GLOBALS['policy'] = '';
ck( 'with no privacy policy the public are shown nothing at all', WPCPM_Sponsor_Application::render(), '' );
$GLOBALS['caps'] = true;
ck( 'and a manager is told why, naming the privacy policy', false !== strpos( WPCPM_Sponsor_Application::render(), 'privacy policy' ), true );

reset_world();
$GLOBALS['settings']['sponsor_applications_enabled'] = false;
ck( 'with the form switched off the public are shown nothing', WPCPM_Sponsor_Application::render(), '' );
$GLOBALS['caps'] = true;
ck( 'and a manager is told it is switched off, and where', false !== strpos( WPCPM_Sponsor_Application::render(), 'Applications from sponsors' ), true );

reset_world();
$form = WPCPM_Sponsor_Application::render();

ck( 'the form posts to admin-post.php, as multipart, guarded against a second press', array( false !== strpos( $form, 'action="https://example.test/wp-admin/admin-post.php"' ), false !== strpos( $form, 'enctype="multipart/form-data"' ), false !== strpos( $form, 'data-wpcpm-once' ) ), array( true, true, true ) );
ck( 'and names its action, a nonce, a dwell token and the honeypot', array( false !== strpos( $form, 'value="wpcpm_sponsor_apply"' ), false !== strpos( $form, 'name="_wpnonce" value="nonce-wpcpm_sponsor_apply"' ), false !== strpos( $form, 'name="wpcpm_sponsor_application_token"' ), false !== strpos( $form, 'name="wpcpm_confirm_site"' ), strpos( $form, 'type="hidden" id="wpcpm_confirm_site"' ) ), array( true, true, true, true, false ) );

$drawn = 0;

foreach ( array( 'Company Name', 'Website', 'Contact Person Full Name', 'Contact Email', 'Sponsorship options', "Anything else you'd like to share.", 'Privacy Policy Compliance' ) as $column ) {
	$drawn += substr_count( $form, 'wpcpm_sponsor_application[' . WPCPM_Sponsor_Application::form_key( $column ) . ']' ) > 0 ? 1 : 0;
}

ck( 'the seven answered questions are drawn, and the logo as two file inputs', array( $drawn, false !== strpos( $form, 'type="file" id="wpcpm-sponsor-application-logo-color" name="wpcpm_sapp_logo_color"' ), false !== strpos( $form, 'type="file" id="wpcpm-sponsor-application-logo-white" name="wpcpm_sapp_logo_white"' ) ), array( 7, true, true ) );
ck( 'the file inputs say what they take', substr_count( $form, 'accept="image/png,image/jpeg,image/webp"' ), 2 );
ck( 'the six groups are drawn as fieldsets', substr_count( $form, '<fieldset' ), 6 );
ck( 'the three radios carry the column\'s choices', array( substr_count( $form, 'type="radio"' ), false !== strpos( $form, 'value="Sponsor mentors + tools/services"' ) ), array( 3, true ) );
ck( 'and the two-hours-a-week note is under them', false !== strpos( $form, 'at least one mentor, at two hours a week' ), true );

echo "\n-- required fields say so ----------------------------------------------\n";

// Eight `required` attributes: the four text answers, each of the three radios (a radio group
// is required when one of its inputs is), and the consent box. Six marks: one beside each of
// the four labels, one beside the radio group's label, one at the end of the consent sentence.
ck( 'eight rendered `required` attributes and six visible marks', array( substr_count( $form, ' required="required"' ), substr_count( $form, 'wpcpm-field__required' ) ), array( 8, 6 ) );
$name_key = WPCPM_Sponsor_Application::form_key( 'Company Name' );
ck( 'a required text field\'s label carries the mark', false !== strpos( $form, '<label for="wpcpm-sponsor-application-' . $name_key . '">Company Name <span class="wpcpm-field__required">Required</span></label>' ), true );
ck( 'the radio group\'s label carries it once', false !== strpos( $form, '<span class="wpcpm-field__label">How would you like to support WP Credits? <span class="wpcpm-field__required">Required</span></span>' ), true );
ck( 'the consent label ends with the mark, after the whole sentence', false !== strpos( $form, 'under it. <span class="wpcpm-field__required">Required</span></label>' ), true );
ck( 'the logo question and the free text carry no mark', array( false !== strpos( $form, '<span class="wpcpm-field__label">Company logo</span>' ), false !== strpos( $form, "Anything else you&#039;d like to share.</label>" ) ), array( true, true ) );
ck( 'the policy line links a word inside the sentence', array( false !== strpos( $form, '>here</a>' ), false !== strpos( $form, 'href="' . get_privacy_policy_url() . '" rel="noopener">here</a>' ) ), array( true, true ) );
ck( 'the consent box is not ticked when nothing was posted', strpos( $form, 'checked="checked"' ), false );
ck( 'the stylesheet and the submit guard are enqueued', $GLOBALS['enqueued'], array( 'wpcpm-sponsor-application', 'wpcpm-forms' ) );

echo "\n-- one good application, end to end -----------------------------------\n";

reset_world();
$sent = submit( answers() );
$row  = only_row();

ck( 'the sender is thanked', $sent['outcome'], 'sent' );
ck( 'one row was stored, private, authored by nobody, titled with the company', array( $row instanceof WP_Post, $row->post_status, $row->post_author, $row->post_title ), array( true, 'private', 0, 'Gadgetry Inc' ) );
ck( 'and filed as new, with no signals against it', array( get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_STATE, true ), get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ) ), array( 'new', array() ) );

$fields = get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_FIELDS, true );

ck( 'six columns are stored: the eight less the logo and the consent', array_keys( $fields ), array( 'Company Name', 'Website', 'Contact Person Full Name', 'Contact Email', 'Sponsorship options', "Anything else you'd like to share." ) );
ck( 'the website was normalized on the way in, and the choice kept byte for byte', array( $fields['Website'], $fields['Sponsorship options'] ), array( 'https://gadgetry.example', 'Sponsor mentors + tools/services' ) );
ck( 'no logo was posted, so both halves are 0', WPCPM_Sponsor_Application::logos_of( $row->ID ), array( 'colour' => 0, 'white' => 0 ) );

$consent = get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_CONSENT, true );

ck( 'the consent evidence names the policy, its version, the sentence, the truncated address and the browser', array( $consent['url'], $consent['policy'], $consent['modified'], false !== strpos( $consent['sentence'], 'privacy policy' ), $consent['ip'], $consent['agent'] ), array( 'https://example.test/privacy/', 43, '2026-08-20 11:30:00', true, '203.0.113.0', 'Mozilla/5.0 (test)' ) );
ck( 'the address is stored only as a hash, for duplicate flagging', get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_EMAIL, true ), wp_hash( 'maciej@a8c.com' ) );
$events = get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_EVENT );
ck( 'one event row was written, saying what happened and by whom', array( count( $events ), $events[0]['event'], $events[0]['actor'] ), array( 1, 'submitted', 0 ) );
ck( 'the reference reads like one, and is stored on the row', array( WPCPM_Sponsor_Application::reference( $row->ID ), get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_REFERENCE, true ) ), array( 'SAPP-2026-0500', 'SAPP-2026-0500' ) );
ck( 'the redirect carries a random id and nothing else', preg_match( '#^https://example\.test/sponsor-application/\?wpcpm_sapp=[a-z0-9]+$#', $sent['url'] ), 1 );

$_GET[ WPCPM_Sponsor_Application::QUERY_STASH ] = $sent['id'];
$page = WPCPM_Sponsor_Application::render();
ck( 'the confirmation names the reference and draws no form', array( false !== strpos( $page, 'SAPP-2026-0500' ), strpos( $page, '<form' ) ), array( true, false ) );
ck( 'and is gone on the next page load, which shows the plain form again', array( strpos( WPCPM_Sponsor_Application::render(), 'SAPP-' ), false !== strpos( WPCPM_Sponsor_Application::render(), '<form' ) ), array( false, true ) );

echo "\n-- the two mails ------------------------------------------------------\n";

ck( 'one message to the applicant, through the single exit, with its own context', array( count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['context'] ), array( 1, 'maciej@a8c.com', 'sponsor-applied' ) );
ck( 'the subject carries the site and the reference', $GLOBALS['mail'][0]['subject'], '[WordPress Education] We have your application: SAPP-2026-0500' );
ck( 'the body says what happens next and names the compliance check, and carries no link to confirm anything', array( false !== strpos( $GLOBALS['mail'][0]['body'], 'What happens next' ), false !== strpos( $GLOBALS['mail'][0]['body'], 'licensing and trademark' ), strpos( $GLOBALS['mail'][0]['body'], 'admin-post.php' ) ), array( true, true, false ) );
ck( 'one message to the managers, through the sponsors module\'s own setting', array( count( $GLOBALS['managermail'] ), $GLOBALS['managermail'][0]['context'], $GLOBALS['managermail'][0]['setting'] ), array( 1, 'sponsor-application', 'sponsor_notify' ) );
ck( 'naming the company in the subject and linking the queue row', array( $GLOBALS['managermail'][0]['subject'], false !== strpos( $GLOBALS['managermail'][0]['body'], 'page=wpcpm-sponsors&wpcpm_sapp_id=' . $row->ID ) ), array( '[WordPress Education] New sponsor application from Gadgetry Inc', true ) );
ck( 'the managers\' mail does not carry the applicant\'s own writing', strpos( $GLOBALS['managermail'][0]['body'], 'We make gadgets' ), false );

echo "\n-- consent is a precondition ------------------------------------------\n";

foreach ( array( 'absent' => null, 'zero' => '0', 'yes' => 'yes', 'empty' => '' ) as $label => $value ) {
	reset_world();
	$sent = submit( answers( array( 'Privacy Policy Compliance' => $value ) ) );

	ck( "consent $label: the submission is refused, nothing is stored, nobody is mailed", array( $sent['outcome'], count( stored() ), count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( 'consent', 0, 0, 0 ) );
}

reset_world();
submit( answers( array( 'Privacy Policy Compliance' => 'true' ) ) );
ck( 'consent "true": the application is taken', count( stored() ), 1 );

reset_world();
$sent = submit( answers( array( 'Privacy Policy Compliance' => '0' ) ) );
ck( 'a refusal hands back what was typed, and never the tick', array( $sent['stash']['values']['Company Name'], array_key_exists( 'Privacy Policy Compliance', $sent['stash']['values'] ) ), array( 'Gadgetry Inc', false ) );
follow( $sent['url'] );
$back = WPCPM_Sponsor_Application::render();
ck( 'the form comes back with the answers in it, the radio on, the consent box unticked, and says what to do', array( false !== strpos( $back, 'value="Gadgetry Inc"' ), false !== strpos( $back, 'value="Sponsor mentors + tools/services" required="required" checked="checked"' ), strpos( $back, 'value="1" required="required" checked' ), false !== strpos( $back, 'tick the last box' ) ), array( true, true, false, true ) );

echo "\n-- the honeypot -------------------------------------------------------\n";

reset_world();
$sent = submit( answers(), array( 'honeypot' => 'https://example.com/' ) );
$row  = only_row();
ck( 'the sender is told exactly what a real applicant is told', $sent['outcome'], 'sent' );
ck( 'the row is kept, held as spam, saying why, and nothing is sent to anybody', array( $row instanceof WP_Post, get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_STATE, true ), in_array( 'honeypot', get_post_meta( $row->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), true ), count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( true, 'spam', true, 0, 0 ) );
reset_world();
submit( answers( array( 'Company Name' => '', 'Website' => '' ) ), array( 'honeypot' => 'x' ) );
ck( 'an incomplete spam row is still stored rather than argued with', count( stored() ), 1 );

echo "\n-- the dwell token ----------------------------------------------------\n";

reset_world();
$_POST['_wpnonce'] = wp_create_nonce( WPCPM_Sponsor_Application::ACTION_SUBMIT );
ck( 'a token minted this instant is too fast to be a person', WPCPM_Sponsor_Application::check_token( WPCPM_Sponsor_Application::token() ), 'spam' );
reset_world();
$_POST['_wpnonce'] = wp_create_nonce( WPCPM_Sponsor_Application::ACTION_SUBMIT );
$token = dwell_token( 45 );
ck( 'the first use of a token is accepted and the second is not', array( WPCPM_Sponsor_Application::check_token( $token ), WPCPM_Sponsor_Application::check_token( $token ) ), array( 'ok', 'spam' ) );
// Well shaped (three parts) and signed with this form's nonce, so the refusal is the scope's, not the shape's.
ck( 'a token minted for the institution form is a forgery here', WPCPM_Sponsor_Application::check_token( dwell_token( 40, 'wpcpm-application-dwell' ) ), 'spam' );
reset_world();
$harvested = dwell_token( 60 );
$first     = submit( answers(), array( 'token' => $harvested ) );
$second    = submit( answers( array( 'Company Name' => 'Second Co', 'Contact Email' => 'maciej@a8c.com' ) ), array( 'token' => $harvested ) );
$rows      = stored();
ck( 'a harvested token gets one application through and the replay is held as spam', array( get_post_meta( $rows[0]->ID, WPCPM_Sponsor_Application::META_STATE, true ), get_post_meta( $rows[1]->ID, WPCPM_Sponsor_Application::META_STATE, true ), in_array( 'dwell', get_post_meta( $rows[1]->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), true ) ), array( 'new', 'spam', true ) );
ck( 'the sender of the replay is told nothing about it, and only the first was mailed about', array( $first['outcome'], $second['outcome'], count( $GLOBALS['mail'] ) ), array( 'sent', 'sent', 1 ) );
reset_world();
$stale = submit( answers(), array( 'token' => dwell_token( 13 * HOUR_IN_SECONDS ) ) );
ck( 'an overnight tab is asked to send again, nothing is stored, the writing is handed back', array( $stale['outcome'], count( stored() ), $stale['stash']['values']["Anything else you'd like to share."] ), array( 'stale', 0, 'We make gadgets and would like to help.' ) );
reset_world();
$expired = submit( answers(), array( 'nonce' => 'nonce-from-last-week' ) );
ck( 'an expired nonce is a message and not a death screen, and the writing comes back without the tick', array( $expired['outcome'], count( stored() ), $expired['stash']['values']['Contact Person Full Name'], array_key_exists( 'Privacy Policy Compliance', $expired['stash']['values'] ) ), array( 'expired', 0, 'Sam Sponsor', false ) );

echo "\n-- the two ceilings, which do different things ------------------------\n";

reset_world();
for ( $i = 1; $i <= 5; $i++ ) {
	submit( answers( array( 'Company Name' => 'Company ' . $i ) ) );
}
ck( 'five in an hour from one source are taken', count( stored() ), 5 );
$sixth = submit( answers( array( 'Company Name' => 'Company 6' ) ) );
ck( 'the sixth is refused outright, stores nothing, and travels as itself', array( $sixth['outcome'], count( stored() ), false !== strpos( $sixth['url'], 'wpcpm_sapp_said=busy' ), count( $GLOBALS['transients'] ) ), array( 'busy', 5, true, 5 ) );
ck( 'and another source is untouched by it', submit( answers( array( 'Company Name' => 'Elsewhere' ) ), array( 'ip' => '198.51.100.9' ) )['outcome'], 'sent' );
ck( 'the sponsor form counts under its own keys, not the institution form\'s', array( false !== strpos( $src, "const ACTOR_PREFIX = 'sponsor-apply';" ), false !== strpos( $src, "const SITE_KEY = 'sponsor-apply-site';" ), false !== strpos( $src, "const MAIL_KEY = 'sponsor-apply-mail';" ) ), array( true, true, true ) );

reset_world();
$made = 0;
for ( $source = 1; $source <= 8; $source++ ) {
	for ( $i = 1; $i <= 5; $i++ ) {
		++$made;
		submit( answers( array( 'Company Name' => 'Company ' . $made ) ), array( 'ip' => '198.51.100.' . $source ) );
	}
}
ck( 'forty applications from eight sources are all taken', count( stored() ), 40 );
$mails_so_far = count( $GLOBALS['mail'] );
$genuine      = submit( answers( array( 'Company Name' => 'Real Company' ) ), array( 'ip' => '198.51.100.42' ) );
$rows         = stored();
$last         = end( $rows );
ck( 'the form is still open to the next source afterwards, and its application is kept and held', array( $genuine['outcome'], count( $rows ), get_post_meta( $last->ID, WPCPM_Sponsor_Application::META_STATE, true ), in_array( 'site-ceiling', get_post_meta( $last->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), true ) ), array( 'sent', 41, 'held', true ) );
ck( 'the applicant is written to all the same, and only the managers are spared', array( count( $GLOBALS['mail'] ), mail_said( -1, 'to' ), count( $GLOBALS['managermail'] ) ), array( $mails_so_far + 1, 'maciej@a8c.com', 40 ) );

echo "\n-- nothing unauthenticated writes before the ceiling ------------------\n";

reset_world();
$opts_before = count( $GLOBALS['opts'] );
$rubbish     = array();
for ( $i = 0; $i < 100; $i++ ) {
	$rubbish = submit( array(), array( 'nonce' => 'not-a-nonce' ) );
}
ck( 'a hundred posts with a wrong nonce write no transient and nothing but the one ceiling row', array( count( $GLOBALS['transients'] ), count( $GLOBALS['opts'] ) - $opts_before, count( stored() ), $rubbish['outcome'] ), array( 0, 1, 0, 'busy' ) );
reset_world();
$empty = submit( array(), array( 'nonce' => 'not-a-nonce' ) );
ck( 'the first one is told the form had been open too long, as a slug and not a stash', array( $empty['outcome'], $empty['id'], count( $GLOBALS['transients'] ) ), array( 'expired', '', 0 ) );
$_GET = array( WPCPM_Sponsor_Application::QUERY_OUTCOME => 'approved' );
ck( 'an outcome nobody defined says nothing and draws the plain form', array( substr_count( WPCPM_Sponsor_Application::render(), 'wpcpm-application__message' ), false !== strpos( WPCPM_Sponsor_Application::render(), '<form' ) ), array( 0, true ) );

echo "\n-- requiredness, once, after cleaning ---------------------------------\n";

reset_world();
$missing = submit( answers( array( 'Company Name' => '', 'Website' => '   ' ) ) );
ck( 'a missing answer is a message naming each field, storing nothing', array( $missing['outcome'], $missing['stash']['problems']['Company Name'], $missing['stash']['problems']['Website'], count( stored() ) ), array( 'again', 'required', 'required', 0 ) );
reset_world();
ck( 'a refused answer keeps its own reason rather than being called missing', submit( answers( array( 'Contact Email' => 'sam at gadgetry' ) ) )['stash']['problems']['Contact Email'], 'bad_email' );
reset_world();
ck( 'no radio ticked is required', submit( answers( array( 'Sponsorship options' => '' ) ) )['stash']['problems']['Sponsorship options'], 'required' );
reset_world();
submit( answers( array( "Anything else you'd like to share." => '' ) ) );
ck( 'and the free text is genuinely optional', count( stored() ), 1 );

echo "\n-- the two logos ------------------------------------------------------\n";

reset_world();
post_logos( png( 400, 120 ) );
$with = submit( answers() );
$row  = only_row();
$ids  = WPCPM_Sponsor_Application::logos_of( $row->ID );
ck( 'a color logo is accepted, stored and recorded on the row', array( $with['outcome'], $ids['colour'] > 0, $ids['white'] ), array( 'sent', true, 0 ) );
ck( 'the attachment carries author 0 and the title the logo card uses', array( $GLOBALS['attachments'][ $ids['colour'] ]['post_author'], $GLOBALS['attachments'][ $ids['colour'] ]['post_title'] ), array( 0, 'Gadgetry Inc logo (color)' ) );
ck( 'and the stored bytes are a real PNG at the uploads path', array( is_file( $GLOBALS['attachments'][ $ids['colour'] ]['file'] ), getimagesize( $GLOBALS['attachments'][ $ids['colour'] ]['file'] )['mime'] ), array( true, 'image/png' ) );
// The S5 review's Finding 3: files a stranger uploaded through a public form were ordinary
// `inherit` attachments, served from /wp-content/uploads/ and listed by the unauthenticated
// media endpoint under the applicant's own title, before anybody had looked at them. The
// status takes the record out of that listing; the generated name takes the file out of reach
// of anybody guessing it off the company's name. Approval publishes both halves.
ck( 'the attachment is private until somebody approves it', $GLOBALS['attachments'][ $ids['colour'] ]['post_status'], 'private' );
ck( 'and its file name is generated, never the company\'s own', array(
	1 === preg_match( '/^sapp-[A-Za-z0-9]{24}\.(png|jpg|webp)$/', basename( (string) $GLOBALS['attachments'][ $ids['colour'] ]['file'] ) ),
	false !== stripos( basename( (string) $GLOBALS['attachments'][ $ids['colour'] ]['file'] ), 'gadgetry' ),
), array( true, false ) );

reset_world();
post_logos( png( 400, 120 ), png( 400, 120 ) );
submit( answers() );
$ids = WPCPM_Sponsor_Application::logos_of( only_row()->ID );
ck( 'both halves land, the white one titled as such', array( $ids['colour'] > 0, $ids['white'] > 0, $GLOBALS['attachments'][ $ids['white'] ]['post_title'] ), array( true, true, 'Gadgetry Inc logo (white)' ) );

reset_world();
post_logos( fake_svg() );
$bad = submit( answers() );
ck( 'an SVG named .png is a problem on the logo question: nothing stored, the answers handed back', array( $bad['outcome'], $bad['stash']['problems']['Logo'], count( stored() ), $GLOBALS['attachments'], $bad['stash']['values']['Company Name'] ), array( 'again', 'image_type', 0, array(), 'Gadgetry Inc' ) );
follow( $bad['url'] );
ck( 'and the page says what was wrong with the file and that files have to be chosen again', array( false !== strpos( WPCPM_Sponsor_Application::render(), 'not a PNG, JPEG or WebP' ), false !== strpos( WPCPM_Sponsor_Application::render(), 'chosen again' ) ), array( true, true ) );

reset_world();
post_logos( png( 199, 80 ) );
ck( 'a logo narrower than 200 pixels is refused by dimensions', submit( answers() )['stash']['problems']['Logo'], 'image_dimensions' );

reset_world();
post_logos( png( 400, 120 ), fake_svg() );
$pair = submit( answers() );
ck( 'one bad file of two refuses the pair, and neither half was stored', array( $pair['outcome'], count( stored() ), $GLOBALS['attachments'] ), array( 'again', 0, array() ) );
ck( 'and the accepted half\'s temporary copy was deleted', count( glob( sys_get_temp_dir() . '/wpcpm-image-*' ) ), 0 );

reset_world();
post_logos( png( 400, 120 ) );
submit( answers(), array( 'honeypot' => 'x' ) );
ck( 'a spam row stores no attachment, whatever it sent', array( get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_STATE, true ), $GLOBALS['attachments'] ), array( 'spam', array() ) );

reset_world();
$GLOBALS['store_fails'] = true;
post_logos( png( 400, 120 ) );
$stored_anyway = submit( answers() );
$GLOBALS['store_fails'] = false;
ck( 'a Media Library that refuses does not lose the application: the row stands with a signal', array( $stored_anyway['outcome'], count( stored() ), in_array( 'logo-failed', get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), true ), WPCPM_Sponsor_Application::logos_of( only_row()->ID ) ), array( 'sent', 1, true, array( 'colour' => 0, 'white' => 0 ) ) );

echo "\n-- duplicates are flagged and never merged ----------------------------\n";

reset_world();
submit( answers() );
submit( answers( array( 'Company Name' => 'A Different Name' ) ) );
$rows = stored();
ck( 'both rows are kept; the second is flagged on the address and is still new', array( count( $rows ), get_post_meta( $rows[0]->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), get_post_meta( $rows[1]->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), get_post_meta( $rows[1]->ID, WPCPM_Sponsor_Application::META_STATE, true ) ), array( 2, array(), array( 'duplicate' ), 'new' ) );
ck( 'the managers hear about both', count( $GLOBALS['managermail'] ), 2 );
reset_world();
submit( answers() );
submit( answers( array( 'Contact Email' => 'someone.else@gadgetry.example' ) ) );
ck( 'the same name under another address is flagged too', get_post_meta( stored()[1]->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), array( 'duplicate' ) );
reset_world();
submit( answers() );
update_post_meta( stored()[0]->ID, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::STATE_REJECTED );
submit( answers() );
ck( 'a settled application is not something a new one duplicates', get_post_meta( stored()[1]->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), array() );

// Minor 4 of the S5 review: both comparisons read `post_title`, which WordPress entity-filters
// on the way in, so no company with an ampersand in its name ever matched anything.
reset_world();
submit( answers( array( 'Company Name' => 'Smith & Jones Ltd' ) ) );
submit( answers( array( 'Company Name' => 'Smith & Jones Ltd', 'Contact Email' => 'someone.else@smithjones.example' ) ) );
ck( 'a company with an ampersand in its name still duplicates itself', array( stored()[0]->post_title, get_post_meta( stored()[1]->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ) ), array( 'Smith &amp; Jones Ltd', array( 'duplicate' ) ) );

reset_world();
submit( answers( array( 'Company Name' => 'widgetry ltd', 'Website' => 'gadgetry.example' ) ) );
ck( 'a company already in the index by name is flagged in-base, trailing space and case aside, and stays new', array( get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_STATE, true ) ), array( array( 'in-base' ), 'new' ) );
reset_world();
submit( answers( array( 'Website' => 'WIDGETRY.example/pricing' ) ) );
ck( 'and by website host, www and path aside', get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), array( 'in-base' ) );
ck( 'the matches the screen shows are the index rows themselves', array_keys( WPCPM_Sponsor_Application::index_matches( 'Widgetry Ltd', 'https://nothing.example' ) ), array( 'recSPN00000000001' ) );
ck( 'a host is read off any spelling of a website', array( WPCPM_Sponsor_Application::host_of( 'https://www.Widgetry.example/about' ), WPCPM_Sponsor_Application::host_of( 'widgetry.example' ), WPCPM_Sponsor_Application::host_of( '' ) ), array( 'widgetry.example', 'widgetry.example', '' ) );

echo "\n-- content scoring holds and never refuses ----------------------------\n";

reset_world();
$GLOBALS['disallowed'] = true;
submit( answers() );
ck( 'a submission on the disallowed list is stored and held, saying why, the applicant acknowledged and the managers spared', array( get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_STATE, true ), get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( 'held', array( 'disallowed' ), 1, 0 ) );
reset_world();
submit( answers( array( "Anything else you'd like to share." => 'Visit https://one.example https://two.example https://three.example for more.' ) ) );
ck( 'a submission full of links is held', in_array( 'links', get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), true ), true );
reset_world();
submit( answers( array( 'Contact Person Full Name' => 'Gadgetry Inc' ) ) );
ck( 'a company named after its own contact is held', get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), array( 'name-is-contact' ) );

echo "\n-- when storage itself fails -----------------------------------------\n";

reset_world();
$GLOBALS['post_fails'] = true;
post_logos( png( 400, 120 ) );
$lost = submit( answers() );
$GLOBALS['post_fails'] = false;
ck( 'the sender is told nothing was saved, nothing was, nobody is mailed, the writing comes back', array( $lost['outcome'], count( stored() ), count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ), $lost['stash']['values']['Company Name'] ), array( 'lost', 0, 0, 0, 'Gadgetry Inc' ) );
ck( 'and the accepted logo\'s temporary copy did not outlive the refusal', array( $GLOBALS['attachments'], count( glob( sys_get_temp_dir() . '/wpcpm-image-*' ) ) ), array( array(), 0 ) );

echo "\n-- the one page that is never cached ----------------------------------\n";

reset_world();
$GLOBALS['on_page'] = 99;
WPCPM_Sponsor_Application::no_cache();
ck( 'a page that is not the form is left alone', array( $GLOBALS['nocache'], defined( 'DONOTCACHEPAGE' ) ), array( 0, false ) );
$GLOBALS['on_page'] = 42;
WPCPM_Sponsor_Application::no_cache();
ck( 'the form\'s own page sends the no-cache headers and says so by the name every page cache reads', array( $GLOBALS['nocache'], defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ), array( 1, true ) );
$GLOBALS['on_page'] = 0;

echo "\n-- the page ------------------------------------------------------------\n";

reset_world();
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_PAGE ] );
ck( 'a lost option adopts the page by slug rather than making a second one', WPCPM_Sponsor_Application::ensure_page(), 42 );
$GLOBALS['posts'] = array();
$GLOBALS['slugs'] = array();
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_PAGE ] );
$page_id = WPCPM_Sponsor_Application::ensure_page();
ck( 'a missing page is created, published, with the slug, the title and the block', array( $page_id > 0, $GLOBALS['posts'][ $page_id ]->post_title, (int) get_option( WPCPM_Sponsor_Application::OPT_PAGE ) ), array( true, 'Sponsor the WordPress Credits Program', $page_id ) );
ck( 'and is never gated: no access level is written on it', isset( $GLOBALS['pmeta'][ $page_id ] ), false );
ck( 'no option this class writes is autoloaded', array_values( array_filter( array_keys( $GLOBALS['autoload'] ), static function ( $name ) { return 0 === strpos( $name, 'wpcpm_' ) && false !== $GLOBALS['autoload'][ $name ]; } ) ), array() );

echo "\n-- the queue's readers -------------------------------------------------\n";

reset_world();
submit( answers( array( 'Company Name' => 'One' ) ) );
submit( answers( array( 'Company Name' => 'Two' ) ), array( 'honeypot' => 'x' ) );
submit( answers( array( 'Company Name' => 'Three' ) ) );
$rows = stored();
update_post_meta( $rows[2]->ID, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::STATE_INFO );
ck( 'the pending count is what is waiting for somebody', WPCPM_Sponsor_Application::pending_count(), 2 );
ck( 'spam is not waiting for anybody', count( WPCPM_Sponsor_Application::applications( array( WPCPM_Sponsor_Application::STATE_SPAM ) ) ), 1 );
$queue = WPCPM_Sponsor_Application::applications( array( WPCPM_Sponsor_Application::STATE_NEW, WPCPM_Sponsor_Application::STATE_INFO ) );
ck( 'the queue is oldest first', array( $queue[0]->post_title, $queue[1]->post_title ), array( 'One', 'Three' ) );
ck( 'a caller that asks for no states gets no rows, and a state nobody has heard of is dropped', array( WPCPM_Sponsor_Application::applications( array() ), WPCPM_Sponsor_Application::applications( array( 'wide-open' ) ) ), array( array(), array() ) );
ck( 'the queue link names the Sponsors screen and the row', WPCPM_Sponsor_Application::queue_url( 7 ), 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&wpcpm_sapp_id=7' );

echo "\n-- read off the source ------------------------------------------------\n";

$submit  = method_body( $src, 'handle_submit' );
$offsets = array(
	'actor'      => strpos( $submit, 'WPCPM_Form_Guard::claim_actor(' ),
	'nonce'      => strpos( $submit, 'wp_verify_nonce(' ),
	'closed'     => strpos( $submit, 'self::is_open()' ),
	'honeypot'   => strpos( $submit, 'WPCPM_Form_Guard::honeypot_filled(' ),
	'dwell'      => strpos( $submit, 'self::check_token(' ),
	'consent'    => strpos( $submit, 'self::COL_CONSENT' ),
	'fields'     => strpos( $submit, '$cleaned  = self::clean_all(' ),
	'required'   => strpos( $submit, 'self::add_required(' ),
	'logos'      => strpos( $submit, 'self::accept_logos(' ),
	'scoring'    => strpos( $submit, 'self::score(' ),
	'site'       => strpos( $submit, 'WPCPM_Form_Guard::claim_site(' ),
	'duplicates' => strpos( $submit, 'self::has_open_duplicate(' ),
	'store'      => strpos( $submit, 'self::store(' ),
	'files'      => strpos( $submit, 'self::store_logos(' ),
	'mail'       => strpos( $submit, 'self::mail_applicant(' ),
);
$sorted  = $offsets;
asort( $sorted );
ck( 'the submit path reads in the order the design fixes', array_keys( $sorted ), array_keys( $offsets ) );
ck( 'and every one of the fifteen is there', in_array( false, $offsets, true ), false );

$code = array();
foreach ( explode( "\n", $src ) as $line ) {
	$trimmed = ltrim( $line );
	if ( '' === $trimmed || 0 === strpos( $trimmed, '*' ) || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '/*' ) ) { continue; }
	$code[] = $line;
}
$code = implode( "\n", $code );
ck( 'nothing on the public path calls check_admin_referer(), and the nonce is verified once', array( strpos( method_body( $code, 'handle_submit' ), 'check_admin_referer' ), substr_count( method_body( $code, 'handle_submit' ), 'wp_verify_nonce(' ) ), array( false, 1 ) );
ck( 'the submit handler reads no query string', substr_count( $submit, 'WPCPM_Request::text(' ) + substr_count( $submit, 'WPCPM_Request::key(' ) + substr_count( $submit, 'WPCPM_Request::id(' ), 0 );
// Three: the isset, the type test and the read, all inside uploaded(); the docblocks say
// "the files array" so that the count is the code's.
ck( '$_FILES is read in one helper and nowhere else, and never moved on trust', array( substr_count( $src, '$_FILES' ), preg_match( '/wp_handle_upload|move_uploaded_file/', $src ) ), array( 3, 0 ) );
ck( 'no file is stored before every check passes', strpos( $src, 'WPCPM_Image_Upload::accept' ) < strpos( $src, 'WPCPM_Image_Upload::store' ), true );
ck( 'every string a person reads says color', preg_match( '/\bcolour\b/', preg_replace( "/'colour'/", '', $src ) ), 0 );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );

$slugs = array();
preg_match_all( "/self::bounce\(\s*'([a-z-]+)'/", $src, $found );
foreach ( $found[1] as $slug ) {
	if ( 'sent' !== $slug && 'sent-quiet' !== $slug && ! isset( WPCPM_Sponsor_Application::outcomes()[ $slug ] ) ) {
		$slugs[] = $slug;
	}
}
ck( 'every outcome the handler stashes has a sentence, and "sent" is drawn as a panel instead', array( $slugs, isset( WPCPM_Sponsor_Application::outcomes()['sent'] ) ), array( array(), false ) );

echo "\n-- the cap on unauthenticated outbound mail -----------------------------\n";

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
	WPCPM_Ceiling::claim( WPCPM_Sponsor_Application::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY, DAY_IN_SECONDS );
}
$capped = submit( answers() );
ck( 'past the ceiling the application is still stored and new, the queue is told why, and the applicant is told plainly', array( count( stored() ), get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_STATE, true ), in_array( 'mail-ceiling', (array) get_post_meta( only_row()->ID, WPCPM_Sponsor_Application::META_SIGNALS, true ), true ), $capped['outcome'] ), array( 1, 'new', true, 'sent-quiet' ) );

echo "\n-- the editor's preview -----------------------------------------------\n";

// Last, because a define cannot be taken back.
define( 'REST_REQUEST', true );
reset_world();
$preview = WPCPM_Sponsor_Application::render();
ck( 'the editor is shown no form and no token, the groups and how many questions each asks', array( strpos( $preview, '<form' ), strpos( $preview, WPCPM_Sponsor_Application::TOKEN_FIELD ), substr_count( $preview, '<li>' ), false !== strpos( $preview, '2 questions' ) ), array( false, false, 6, true ) );

/* ---- part 2: the six states and the six decisions (Task 3) --------------- */

// The functions and classes below are declared at the top level of the file, so PHP hoists
// them: they exist from the first line of the run even though they are written here.

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '', $email = '' ) { $this->ID = (int) $id; $this->display_name = $name; $this->user_email = $email; }
	public function exists() { return $this->ID > 0; }
}
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
// No account holds an applicant's address in this file, so `queue_facts()` never raises the
// account mark here: that mark is the Sponsors screen suite's business, and this one reads the
// facts for the half-done signal alone.
function get_user_by( $field, $value ) { return false; }
function esc_js( $s ) { return addslashes( (string) $s ); }
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { $GLOBALS['nonce_checked'][] = $action; return true; }
function wp_delete_attachment( $id, $force = false ) { $GLOBALS['deleted_attachments'][] = (int) $id; unset( $GLOBALS['attachments'][ (int) $id ] ); return true; }
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][ $channel ] = $value; }
	public static function take( $channel, $user_id = 0 ) { $v = isset( $GLOBALS['flash'][ $channel ] ) ? $GLOBALS['flash'][ $channel ] : ''; unset( $GLOBALS['flash'][ $channel ] ); return $v; }
}
class WPCPM_Sponsors {
	const FLASH = 'sponsors_admin';
}
if ( ! class_exists( 'WPCPM_Sponsor_Approval' ) ) {
	/** The approval, at its contract: journalled, answering what the test says. Task 5's real class is never loaded here. */
	class WPCPM_Sponsor_Approval {
		public static function approve( $application_id, $manager_id ) {
			$GLOBALS['approve_calls'][] = array( (int) $application_id, (int) $manager_id );
			return $GLOBALS['approve_answer'];
		}
		/**
		 * The real class's own test, restated rather than switched by a global: a record
		 * stamped and nothing decided since. Written out here so the refusals below are
		 * pinned against the condition itself and not against a flag this file sets.
		 * `bin/test-sponsor-approval.php` pins the real one against the real class.
		 */
		public static function is_half_done( $application_id ) {
			if ( '' === (string) get_post_meta( (int) $application_id, WPCPM_Sponsor_Application::META_RECORD, true ) ) {
				return false;
			}

			return in_array( (string) get_post_meta( (int) $application_id, WPCPM_Sponsor_Application::META_STATE, true ), WPCPM_Sponsor_Application::open_states(), true );
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

/**
 * Press one decision as the current user and answer with what came back.
 *
 * @param string $handler The handler's name.
 * @param int    $id      The application.
 * @param array  $fields  Other posted fields.
 * @return array `flash` (channel => status), `url`, `died`.
 */
function decide( $handler, $id, array $fields = array() ) {
	$_POST = array_merge( array( WPCPM_Sponsor_Application::FIELD_APPLICATION => $id ), $fields );
	$GLOBALS['flash'] = array();
	$url  = '';
	$died = '';

	try {
		call_user_func( array( 'WPCPM_Sponsor_Application', $handler ) );
	} catch ( Exception $e ) {
		if ( 0 === strpos( $e->getMessage(), 'wp_die: ' ) ) {
			$died = substr( $e->getMessage(), 8 );
		} else {
			$url = str_replace( 'redirect: ', '', $e->getMessage() );
		}
	}

	return array(
		'flash' => $GLOBALS['flash'],
		'url'   => $url,
		'died'  => $died,
	);
}

/** The status flashed on the Sponsors screen's channel by the last decision, or ''. */
function flashed( array $decided ) {
	return isset( $decided['flash']['sponsors_admin'] ) ? (string) $decided['flash']['sponsors_admin'] : '';
}

/** A fresh application through the real form, as a manager finds it. */
function seed_application( array $overrides = array(), $logo = false ) {
	if ( $logo ) {
		post_logos( png( 400, 120 ), png( 400, 120 ) );
	}

	submit( answers( $overrides ) );
	$rows = stored();

	return (int) end( $rows )->ID;
}

/** Become the manager, with an address the question can be replied to. */
function as_manager() {
	$GLOBALS['users'] = array( 3 => new WP_User( 3, 'Manager Three', 'maciej@a8c.com' ) );
	$GLOBALS['uid']   = 3;
	$GLOBALS['caps']  = true;
}

echo "\n-- the six states and the six decisions --------------------------------\n";

ck( 'the six states', WPCPM_Sponsor_Application::states(), array( 'new', 'held', 'spam', 'info', 'approved', 'rejected' ) );
ck( 'open, reopenable and purgeable, exactly as the institution queue has them', array( WPCPM_Sponsor_Application::open_states(), WPCPM_Sponsor_Application::reopen_states(), WPCPM_Sponsor_Application::purgeable_states() ), array( array( 'new', 'held', 'info' ), array( 'held', 'info', 'spam', 'rejected' ), array( 'spam', 'rejected', 'approved' ) ) );
ck( 'six actions, one apiece, under the stem', array( WPCPM_Sponsor_Application::ACTION_APPROVE, WPCPM_Sponsor_Application::ACTION_INFO, WPCPM_Sponsor_Application::ACTION_REJECT, WPCPM_Sponsor_Application::ACTION_SPAM, WPCPM_Sponsor_Application::ACTION_REOPEN, WPCPM_Sponsor_Application::ACTION_PURGE ), array( 'wpcpm_sapp_approve', 'wpcpm_sapp_info', 'wpcpm_sapp_reject', 'wpcpm_sapp_spam', 'wpcpm_sapp_reopen', 'wpcpm_sapp_purge' ) );
ck( 'every state has a label in the manager\'s words', count( array_filter( array_map( array( 'WPCPM_Sponsor_Application', 'state_label' ), WPCPM_Sponsor_Application::states() ), 'strlen' ) ), 6 );

$src    = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php' );
$leaves = array();
preg_match_all( "/self::leave\(\s*'([a-z-]+)'/", $src, $found );
foreach ( array_unique( $found[1] ) as $slug ) {
	if ( ! isset( WPCPM_Sponsor_Application::manager_messages()[ $slug ] ) ) {
		$leaves[] = $slug;
	}
}
$mapped = array();
foreach ( array( 'wpcpm_sapp_unknown', 'wpcpm_sapp_state', 'wpcpm_sapp_busy', 'wpcpm_sapp_fields', 'wpcpm_sapp_no_email', 'wpcpm_sapp_name', 'wpcpm_sapp_airtable', 'wpcpm_sapp_account', 'wpcpm_sapp_actor', 'anything-else' ) as $code ) {
	$mapped[] = isset( WPCPM_Sponsor_Application::manager_messages()[ WPCPM_Sponsor_Application::approval_outcome( $code ) ] );
}
ck( 'every status a decision leaves has a sentence, and every approval refusal maps to one', array( $leaves, in_array( false, $mapped, true ) ), array( array(), false ) );
// Seven `admin_post_` registrations (the submit and the six decisions) and one `nopriv`, the submit's.
ck( 'the six decisions register under admin_post_ beside the submit, and only the submit is nopriv', array( substr_count( method_body( $src, 'init' ), "'admin_post_' . self::ACTION_" ), substr_count( method_body( $src, 'init' ), 'admin_post_nopriv_' ) ), array( 7, 1 ) );

echo "\n-- who may decide -------------------------------------------------------\n";

reset_world();
$id = seed_application();
$GLOBALS['uid']  = 21;
$GLOBALS['caps'] = false;
$GLOBALS['nonce_checked'] = array();
$refused = decide( 'handle_reject', $id );
ck( 'a non-manager meets wp_die() before the nonce is even looked at', array( '' !== $refused['died'], $GLOBALS['nonce_checked'] ), array( true, array() ) );
as_manager();
decide( 'handle_reject', 9999 );
ck( 'the nonce is keyed to the action and the application together, and checked before the post is read', end( $GLOBALS['nonce_checked'] ), 'wpcpm_sapp_reject_9999' );
ck( 'an application nobody has is the one refusal', flashed( decide( 'handle_reject', 9999 ) ), 'sapp-unknown' );
ck( 'and so is a post of another type', flashed( decide( 'handle_reject', 42 ) ), 'sapp-unknown' );

echo "\n-- a question ----------------------------------------------------------\n";

reset_world();
as_manager();
$id = seed_application();
$GLOBALS['mail'] = array();
ck( 'a question shorter than ten characters is refused and nothing moves', array( flashed( decide( 'handle_info', $id, array( 'wpcpm_question' => 'Why?' ) ) ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), count( $GLOBALS['mail'] ) ), array( 'sapp-question', 'new', 0 ) );
$asked = decide( 'handle_info', $id, array( 'wpcpm_question' => "Which of your plans would students get?\n\nAnd for how long?" ) );
ck( 'a question is mailed to the applicant with the manager\'s address to reply to', array( flashed( $asked ), count( $GLOBALS['mail'] ), mail_said( 0, 'to' ), mail_said( 0, 'context' ), $GLOBALS['mail'][0]['headers'] ), array( 'sapp-info', 1, 'maciej@a8c.com', 'sponsor-information', array( 'Reply-To: maciej@a8c.com' ) ) );
ck( 'the subject carries the site and the reference, the body the question with its paragraphs and how to answer', array( mail_said( 0, 'subject' ), false !== strpos( mail_said( 0, 'body' ), "Which of your plans would students get?\n\nAnd for how long?" ), false !== strpos( mail_said( 0, 'body' ), 'Reply to this message' ) ), array( sprintf( '[WordPress Education] A question about your application (SAPP-2026-%04d)', $id ), true, true ) );
$events = get_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT );
ck( 'the state moves to info and the history keeps the question, with the manager as the actor', array( get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), end( $events )['event'], end( $events )['actor'], false !== strpos( end( $events )['note'], 'Which of your plans' ) ), array( 'info', 'information requested', 3, true ) );
ck( 'a question can be asked again while it waits', flashed( decide( 'handle_info', $id, array( 'wpcpm_question' => 'One more thing: which countries?' ) ) ), 'sapp-info' );
$GLOBALS['mail_fails'] = true;
$unsent = decide( 'handle_info', $id, array( 'wpcpm_question' => 'And one more question here.' ) );
$GLOBALS['mail_fails'] = false;
$events = get_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT );
ck( 'a question that could not be handed off moves nothing and says so', array( flashed( $unsent ), count( $events ) ), array( 'sapp-not-sent', 3 ) );

echo "\n-- a rejection and a spam mark ------------------------------------------\n";

reset_world();
as_manager();
$id = seed_application();
$GLOBALS['mail'] = array();
$rejected = decide( 'handle_reject', $id, array( 'wpcpm_reason' => 'Their product is not GPL.' ) );
ck( 'the applicant gets the neutral acknowledgement, through the single exit', array( flashed( $rejected ), count( $GLOBALS['mail'] ), mail_said( 0, 'context' ), mail_said( 0, 'subject' ) ), array( 'sapp-rejected', 1, 'sponsor-declined', '[WordPress Education] About your application to sponsor the WordPress Credits Program' ) );
ck( 'with no reason in it, anywhere', array( strpos( mail_said( 0, 'body' ), 'GPL' ), false !== strpos( mail_said( 0, 'body' ), 'not taking it forward' ) ), array( false, true ) );
$events = get_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT );
ck( 'the state is rejected and the reason stays on this site, for the next manager', array( get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), end( $events )['event'], end( $events )['note'] ), array( 'rejected', 'rejected', 'Their product is not GPL.' ) );
ck( 'a decided application cannot be rejected again', flashed( decide( 'handle_reject', $id ) ), 'sapp-state' );

reset_world();
as_manager();
$id = seed_application();
$GLOBALS['mail'] = array();
$spam = decide( 'handle_spam', $id );
ck( 'spam sends nothing to anybody and moves the state', array( flashed( $spam ), count( $GLOBALS['mail'] ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ) ), array( 'sapp-spam', 0, 'spam' ) );
ck( 'and can be put back in the queue', array( flashed( decide( 'handle_reopen', $id ) ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ) ), array( 'sapp-reopened', 'new' ) );
update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::STATE_APPROVED );
ck( 'an approved application is never reopened: approval made a record and an account', flashed( decide( 'handle_reopen', $id ) ), 'sapp-state' );

echo "\n-- a half-done approval is marked, and three decisions refuse it (S5 fix wave) --\n";

// The review's Finding 2: after an account or a category refusal the application stays open
// with the record stamped, so the row looked like any other and Reject took it - the applicant
// got the neutral decline while a record with Status Approved, an index row and possibly an
// account with a membership stood for them.
reset_world();
as_manager();
$id = seed_application();
$GLOBALS['mail'] = array();
update_post_meta( $id, WPCPM_Sponsor_Application::META_RECORD, 'recSPN00000000009' );
$before = get_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT );

ck( 'the queue facts carry the signal and the flag', array(
	in_array( WPCPM_Sponsor_Application::SIGNAL_HALF_DONE, WPCPM_Sponsor_Application::queue_facts()[0]['signals'], true ),
	WPCPM_Sponsor_Application::queue_facts()[0]['half_done'],
), array( true, true ) );

$refused_reject = decide( 'handle_reject', $id, array( 'wpcpm_reason' => 'Their product is not GPL.' ) );
ck( 'Reject is refused, nothing is sent and nothing moves', array( flashed( $refused_reject ), count( $GLOBALS['mail'] ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), count( get_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT ) ) ), array( 'sapp-half-done', 0, 'new', count( $before ) ) );
ck( 'Reject as spam is refused too, and moves nothing', array( flashed( decide( 'handle_spam', $id ) ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ) ), array( 'sapp-half-done', 'new' ) );
update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::STATE_INFO );
ck( 'and so is Put back in the queue, on the one open state that offers it', array( flashed( decide( 'handle_reopen', $id ) ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ) ), array( 'sapp-half-done', 'info' ) );
ck( 'the refusal has a sentence naming the record and the way out', array(
	false !== strpos( WPCPM_Sponsor_Application::manager_messages()['sapp-half-done'][1], 'an Airtable record already exists for it' ),
	false !== strpos( WPCPM_Sponsor_Application::manager_messages()['sapp-half-done'][1], 'Press Approve again to finish' ),
), array( true, true ) );

// Delete for good needs no guard of its own: the two lists cannot describe one application.
ck( 'and a half-done row can never be a purgeable one, so Delete for good needs no guard', array_intersect( WPCPM_Sponsor_Application::open_states(), WPCPM_Sponsor_Application::purgeable_states() ), array() );

// Approve is the one decision that still goes through: pressing it again is the repair.
$GLOBALS['approve_answer'] = array( 'record' => 'recSPN00000000009', 'user_id' => 40, 'offer_id' => 501, 'term_id' => 77 );
ck( 'Approve still goes through, because pressing it again is the repair', flashed( decide( 'handle_approve', $id ) ), 'sapp-approved' );

echo "\n-- deleting for good ----------------------------------------------------\n";

reset_world();
as_manager();
$GLOBALS['deleted_attachments'] = array();
$id   = seed_application( array(), true );
$ids  = WPCPM_Sponsor_Application::logos_of( $id );
ck( 'an open application cannot be deleted by hand', array( flashed( decide( 'handle_purge', $id ) ), null !== get_post( $id ) ), array( 'sapp-state', true ) );
decide( 'handle_reject', $id );
$purged = decide( 'handle_purge', $id );
ck( 'a rejected one goes for good, with both logo files', array( flashed( $purged ), get_post( $id ), $GLOBALS['deleted_attachments'] ), array( 'sapp-purged', null, array( $ids['colour'], $ids['white'] ) ) );
$log = WPCPM_Sponsor_Application::application_log();
ck( 'and one log row says what happened: the reference, the state, by hand, and who', array( count( $log ), $log[0]['reference'], $log[0]['state'], $log[0]['days'], $log[0]['actor'], isset( $log[0]['email'] ) ), array( 1, sprintf( 'SAPP-2026-%04d', $id ), 'rejected', 0, 3, false ) );

$GLOBALS['deleted_attachments'] = array();
$id = seed_application( array( 'Company Name' => 'Approved Co' ), true );
update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::STATE_APPROVED );
$kept = decide( 'handle_purge', $id );
ck( 'an approved application goes, and its attachments stay: they are the sponsor\'s logo now', array( get_post( $id ), $GLOBALS['deleted_attachments'] ), array( null, array() ) );
// Minor 3 of the S5 review: one sentence said the files went whatever the state was.
ck( 'and the sentence says the files stayed, where the other says they went', array( flashed( $kept ), false !== strpos( WPCPM_Sponsor_Application::manager_messages()['sapp-purged-kept'][1], 'stay in the Media Library as the sponsor\'s logo' ), false !== strpos( WPCPM_Sponsor_Application::manager_messages()['sapp-purged'][1], 'its logo files went with it' ) ), array( 'sapp-purged-kept', true, true ) );

$still_there = get_post( seed_application( array( 'Company Name' => 'Confirm Co' ) ) );
ob_start();
WPCPM_Sponsor_Application::render_actions( $still_there, 'rejected' );
$confirm_gone = (string) ob_get_clean();
ob_start();
WPCPM_Sponsor_Application::render_actions( $still_there, 'approved' );
$confirm_kept = (string) ob_get_clean();
$amp = get_post( seed_application( array( 'Company Name' => 'Smith & Jones Ltd' ) ) );
ob_start();
WPCPM_Sponsor_Application::render_actions( $amp, 'new' );
$amp_html = (string) ob_get_clean();
// The stub esc_js() is addslashes(), so the typed name comes out raw and the kses-filtered title comes out as its entity: the two spellings differ, and only the typed one may appear.
$amp_fixed = 'Smith ' . esc_js( '&' ) . ' Jones Ltd';
$amp_buggy = 'Smith ' . esc_js( '&amp;' ) . ' Jones Ltd';
ck( 'a company name with an ampersand is printed from the stored fields as typed, never from the kses-filtered title (S5 review)', array( false !== strpos( $amp_html, $amp_fixed ), $amp_fixed === $amp_buggy || false === strpos( $amp_html, $amp_buggy ), $amp_fixed !== $amp_buggy ), array( true, true, true ) );
ob_start();
WPCPM_Sponsor_Application::render_details( $amp );
$details_html = (string) ob_get_clean();
ck( 'render_details() is the open view without its heading and decisions: the answers table, the logo figures, the base matches', array( false !== strpos( $details_html, '<table class="widefat striped wpcpm-list wpcpm-app-answers">' ), false !== strpos( $details_html, 'wpcpm-sapp-logos' ) || false !== strpos( $details_html, 'No logo' ), strpos( $details_html, 'wpcpm-app-action' ), strpos( $details_html, 'Back to the queue' ) ), array( true, true, false, false ) );
ck( 'the Delete for good confirm says the same two things, chosen by the state', array(
	false !== strpos( $confirm_gone, 'its logo files go with it' ),
	strpos( $confirm_gone, 'stay in the Media Library' ),
	false !== strpos( $confirm_kept, 'stay in the Media Library as the sponsor' ),
	strpos( $confirm_kept, 'go with it' ),
), array( true, false, true, false ) );

echo "\n-- approving, through the approval class -------------------------------\n";

reset_world();
as_manager();
$id = seed_application();
$GLOBALS['approve_calls']  = array();
$GLOBALS['approve_answer'] = array( 'record' => 'recSPN00000000009', 'user_id' => 40, 'offer_id' => 0, 'term_id' => 0 );
ck( 'Approve hands the application and the manager to the approval and says so', array( flashed( decide( 'handle_approve', $id ) ), $GLOBALS['approve_calls'] ), array( 'sapp-approved', array( array( $id, 3 ) ) ) );
$GLOBALS['approve_calls']  = array();
$GLOBALS['approve_answer'] = new WP_Error( 'wpcpm_sapp_airtable', 'Airtable said no.' );
ck( 'an approval refused by the base is the base\'s sentence', flashed( decide( 'handle_approve', $id ) ), 'sapp-airtable' );
$GLOBALS['approve_answer'] = new WP_Error( 'wpcpm_sapp_account', 'That address belongs to a student.' );
ck( 'and one refused at the account is the account\'s', flashed( decide( 'handle_approve', $id ) ), 'sapp-account' );
$GLOBALS['approve_answer'] = new WP_Error( 'wpcpm_sapp_busy', 'Locked.' );
ck( 'and a lock is a lock', flashed( decide( 'handle_approve', $id ) ), 'sapp-busy' );

// Minor 2 of the S5 review: the detail was read inside `manager_messages()`, so every caller
// of that map consumed it - `WPCPM_Sponsor_Approval::status_sentence()` builds the map in the
// middle of the very POST that sets the detail, and took it before the manager ever saw it.
$GLOBALS['flash'] = array();
WPCPM_Flash::set( WPCPM_Sponsor_Application::FLASH_DETAIL, array( 'status' => 'sapp-account', 'detail' => 'That address belongs to a student.' ) );
WPCPM_Sponsor_Application::manager_messages();
WPCPM_Sponsor_Application::manager_messages();
$plain = WPCPM_Sponsor_Application::manager_messages()['sapp-account'][1];
ck( 'building the map does not take the carried detail, however often it is built', WPCPM_Sponsor_Application::sentence_for( 'sapp-account', $plain ), 'The Airtable record was created, but the account could not be made. The site said: That address belongs to a student. Press Approve again once that is fixed.' );
ck( 'and printing it takes it once: the next notice gets the plain sentence', WPCPM_Sponsor_Application::sentence_for( 'sapp-account', $plain ), $plain );
WPCPM_Flash::set( WPCPM_Sponsor_Application::FLASH_DETAIL, array( 'status' => 'sapp-account', 'detail' => 'That address belongs to a student.' ) );
ck( 'a detail tagged to another status is never printed behind it', WPCPM_Sponsor_Application::sentence_for( 'sapp-spam', 'Marked as spam.' ), 'Marked as spam.' );
$GLOBALS['flash'] = array();
update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, WPCPM_Sponsor_Application::STATE_REJECTED );
$GLOBALS['approve_calls'] = array();
ck( 'a decided application is refused before the approval is reached', array( flashed( decide( 'handle_approve', $id ) ), $GLOBALS['approve_calls'] ), array( 'sapp-state', array() ) );

echo "\n-- a decision taken on the Administrator Dashboard comes back there -----\n";

reset_world();
as_manager();
$id   = seed_application();
$back = decide( 'handle_spam', $id, array( WPCPM_Return::FIELD => WPCPM_Return::DASHBOARD ) );
ck( 'the flash goes on the dashboard\'s channel and the redirect leaves the Sponsors screen', array( isset( $back['flash']['institutions'] ) ? $back['flash']['institutions'] : '', isset( $back['flash']['sponsors_admin'] ), strpos( $back['url'], 'page=wpcpm-sponsors' ) ), array( 'sapp-spam', false, false ) );
$id   = seed_application( array( 'Company Name' => 'Screen Co' ) );
$here = decide( 'handle_spam', $id );
ck( 'and one taken on the screen comes back to the screen', array( flashed( $here ), false !== strpos( $here['url'], 'page=wpcpm-sponsors' ) ), array( 'sapp-spam', true ) );

echo "\n-- the decision forms ---------------------------------------------------\n";

reset_world();
as_manager();
$id   = seed_application();
$post = get_post( $id );
ob_start();
WPCPM_Sponsor_Application::render_actions( $post, 'new' );
$forms = (string) ob_get_clean();
ck( 'an open application offers four decisions, each keyed to itself', array( substr_count( $forms, '<form' ), false !== strpos( $forms, 'nonce-wpcpm_sapp_approve_' . $id ), false !== strpos( $forms, 'nonce-wpcpm_sapp_info_' . $id ), false !== strpos( $forms, 'nonce-wpcpm_sapp_reject_' . $id ), false !== strpos( $forms, 'nonce-wpcpm_sapp_spam_' . $id ) ), array( 4, true, true, true, true ) );
ck( 'every form names the application, is guarded against a second press, and stays on the screen', array( substr_count( $forms, 'name="wpcpm_sapp" value="' . $id . '"' ), substr_count( $forms, 'data-wpcpm-once' ), strpos( $forms, 'wpcpm_return' ) ), array( 4, 4, false ) );
ck( 'the question is required and says so in the label\'s voice; the reason is not', array( 1 === preg_match( '/name="wpcpm_question"[^>]*required/', $forms ), false !== strpos( $forms, 'wpcpm-field__required' ), preg_match( '/name="wpcpm_reason"[^>]*required/', $forms ) ), array( true, true, 0 ) );
ck( 'Approve, Reject, Reject as spam carry a confirm naming the company and the address', array( substr_count( $forms, 'onsubmit="return confirm(' ), false !== strpos( $forms, 'Gadgetry Inc' ), false !== strpos( $forms, 'maciej@a8c.com' ) ), array( 3, true, true ) );
ob_start();
WPCPM_Sponsor_Application::render_actions( $post, 'new', WPCPM_Return::DASHBOARD );
$dashboard_forms = (string) ob_get_clean();
ck( 'drawn for the dashboard, every form carries the return and the anchor', array( substr_count( $dashboard_forms, 'name="wpcpm_return" value="dashboard"' ), substr_count( $dashboard_forms, 'name="wpcpm_return_to" value="sponsor-applications"' ) ), array( 4, 4 ) );
update_post_meta( $id, WPCPM_Sponsor_Application::META_SIGNALS, array( 'in-base' ) );
ob_start();
WPCPM_Sponsor_Application::render_actions( get_post( $id ), 'new' );
$flagged = (string) ob_get_clean();
ck( 'a company already in the base is warned about on Approve', false !== strpos( $flagged, 'already holds a sponsor with this name or website' ), true );
ob_start();
WPCPM_Sponsor_Application::render_actions( $post, 'rejected' );
$closed_forms = (string) ob_get_clean();
ck( 'a rejected one offers the way back and the deletion, and nothing else', array( substr_count( $closed_forms, '<form' ), false !== strpos( $closed_forms, 'value="wpcpm_sapp_reopen"' ), false !== strpos( $closed_forms, 'value="wpcpm_sapp_purge"' ) ), array( 2, true, true ) );
ob_start();
WPCPM_Sponsor_Application::render_actions( $post, 'approved' );
$done_forms = (string) ob_get_clean();
ck( 'an approved one offers the deletion alone', array( substr_count( $done_forms, '<form' ), false !== strpos( $done_forms, 'value="wpcpm_sapp_purge"' ) ), array( 1, true ) );

/* ---- part 3: the retention run (Task 6) --------------------------------- */

/** Forget every ceiling row, so a block that seeds more than five rows from one address may. */
function clear_ceilings() {
	foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
		if ( 0 === strpos( $name, WPCPM_Ceiling::PREFIX ) ) {
			unset( $GLOBALS['opts'][ $name ] );
		}
	}
}

/** Rewrite one application's history so its last decision was `$days_ago` days ago. */
function decided_days_ago( $id, $event, $days_ago ) {
	$GLOBALS['pmeta'][ $id ][ WPCPM_Sponsor_Application::META_EVENT ] = array(
		array( 'event' => 'submitted', 'at' => time() - ( $days_ago + 1 ) * DAY_IN_SECONDS, 'actor' => 0, 'note' => '' ),
		array( 'event' => $event, 'at' => time() - $days_ago * DAY_IN_SECONDS, 'actor' => 3, 'note' => '' ),
	);
}

echo "\n-- the retention run ----------------------------------------------------\n";

reset_world();
as_manager();
$GLOBALS['settings']['application_spam_days']     = 30;
$GLOBALS['settings']['application_rejected_days'] = 365;
$GLOBALS['settings']['application_approved_days'] = 0;
$GLOBALS['deleted_attachments']                   = array();

$old_spam     = seed_application( array( 'Company Name' => 'Old Spam' ), true );
$old_rejected = seed_application( array( 'Company Name' => 'Old Rejected' ), true );
$new_rejected = seed_application( array( 'Company Name' => 'New Rejected' ), true );
$old_approved = seed_application( array( 'Company Name' => 'Old Approved' ), true );
$open         = seed_application( array( 'Company Name' => 'Still Open' ), true );

update_post_meta( $old_spam, WPCPM_Sponsor_Application::META_STATE, 'spam' );
decided_days_ago( $old_spam, 'marked as spam', 31 );
update_post_meta( $old_rejected, WPCPM_Sponsor_Application::META_STATE, 'rejected' );
decided_days_ago( $old_rejected, 'rejected', 366 );
update_post_meta( $new_rejected, WPCPM_Sponsor_Application::META_STATE, 'rejected' );
decided_days_ago( $new_rejected, 'rejected', 10 );
update_post_meta( $old_approved, WPCPM_Sponsor_Application::META_STATE, 'approved' );
decided_days_ago( $old_approved, 'approved', 900 );
decided_days_ago( $open, 'submitted', 400 );

$spam_logos     = WPCPM_Sponsor_Application::logos_of( $old_spam );
$rejected_logos = WPCPM_Sponsor_Application::logos_of( $old_rejected );
$purged         = WPCPM_Sponsor_Application::purge();

ck( 'two rows were past their retention', $purged, 2 );
ck( 'the old spam and the old rejection are gone, with their four logo files', array( get_post( $old_spam ), get_post( $old_rejected ), $GLOBALS['deleted_attachments'] ), array( null, null, array( $spam_logos['colour'], $spam_logos['white'], $rejected_logos['colour'], $rejected_logos['white'] ) ) );
ck( 'a rejection inside its retention stays', get_post( $new_rejected ) instanceof WP_Post, true );
ck( 'an approved application is kept forever at the default, files and all', array( get_post( $old_approved ) instanceof WP_Post, count( $GLOBALS['attachments'] ) ), array( true, 6 ) );
ck( 'an open row is never retention\'s business, however old', get_post( $open ) instanceof WP_Post, true );
$log = WPCPM_Sponsor_Application::application_log();
ck( 'each deletion wrote one log row naming the rule and the run', array( count( $log ), $log[0]['state'], $log[0]['days'], $log[0]['actor'], $log[1]['state'], $log[1]['days'] ), array( 2, 'spam', 30, 0, 'rejected', 365 ) );

$GLOBALS['settings']['application_approved_days'] = 30;
ck( 'set to a number, approved applications go too, and their files stay: they are the sponsor\'s logo', array( WPCPM_Sponsor_Application::purge(), get_post( $old_approved ), count( $GLOBALS['attachments'] ) ), array( 1, null, 6 ) );

$GLOBALS['settings']['application_spam_days'] = 0;
clear_ceilings();
seed_application( array( 'Company Name' => 'Spam Forever' ), false );
$rows = stored();
update_post_meta( end( $rows )->ID, WPCPM_Sponsor_Application::META_STATE, 'spam' );
decided_days_ago( end( $rows )->ID, 'marked as spam', 1000 );
ck( 'zero means never, for spam too', WPCPM_Sponsor_Application::purge(), 0 );

ck( 'the run is a daily hook of its own, wired in init()', array( WPCPM_Sponsor_Application::CRON_PURGE, false !== strpos( method_body( $src, 'init' ), "add_action( self::CRON_PURGE, array( __CLASS__, 'purge' ) )" ) ), array( 'wpcpm_purge_sponsor_applications', true ) );

// Two hundred and ten old rejections, more than the log keeps; each seed clears the ceilings
// first, because they all come from one address and the form takes five an hour from it.
for ( $i = 0; $i < 210; $i++ ) {
	clear_ceilings();
	$id = seed_application( array( 'Company Name' => 'Bulk ' . $i ), false );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, 'rejected' );
	decided_days_ago( $id, 'rejected', 400 );
}
ck( 'every one of them goes', WPCPM_Sponsor_Application::purge(), 210 );
ck( 'and the log keeps its last two hundred rows and no more', count( WPCPM_Sponsor_Application::application_log() ), WPCPM_Sponsor_Application::LOG_MAX );

echo "\n=== The decision meta behind Recently decided (1.98.1) ===\n";
reset_world();
as_manager();
$d1 = seed_application( array( 'Company Name' => 'Decided One' ), true );
$d2 = seed_application( array( 'Company Name' => 'Decided Two' ), true );
$d3 = seed_application( array( 'Company Name' => 'Still Open' ), true );
update_post_meta( $d1, WPCPM_Sponsor_Application::META_STATE, 'rejected' );
WPCPM_Sponsor_Application::add_event( $d1, WPCPM_Sponsor_Application::EVENT_REJECTED, 3 );
update_post_meta( $d2, WPCPM_Sponsor_Application::META_STATE, 'approved' );
WPCPM_Sponsor_Application::add_event( $d2, WPCPM_Sponsor_Application::EVENT_APPROVED, 3 );
// time() has one-second resolution and both decisions above land in the same test tick, so the
// order under test is pinned by hand rather than left to whichever second the run happens to
// land on (the suite has no time() shim to offset instead).
update_post_meta( $d2, WPCPM_Sponsor_Application::META_DECIDED, time() + 10 );
ck( 'a terminal event stamps the decision time; an open row carries none', array( (int) get_post_meta( $d1, WPCPM_Sponsor_Application::META_DECIDED, true ) > 0, (int) get_post_meta( $d2, WPCPM_Sponsor_Application::META_DECIDED, true ) > (int) get_post_meta( $d1, WPCPM_Sponsor_Application::META_DECIDED, true ), get_post_meta( $d3, WPCPM_Sponsor_Application::META_DECIDED, true ) ), array( true, true, '' ) );
ck( 'decided_posts() answers newest decision first, bounded, and never an open row', array_map( static function ( $p ) { return (int) $p->ID; }, WPCPM_Sponsor_Application::decided_posts( 10 ) ), array( $d2, $d1 ) );
ck( 'and the bound holds', count( WPCPM_Sponsor_Application::decided_posts( 1 ) ), 1 );
$tie = (int) get_post_meta( $d2, WPCPM_Sponsor_Application::META_DECIDED, true );
update_post_meta( $d1, WPCPM_Sponsor_Application::META_DECIDED, $tie );
ck( 'two decisions in one second are ordered by ID, newest first (Task 4 review)', array_map( static function ( $p ) { return (int) $p->ID; }, WPCPM_Sponsor_Application::decided_posts( 10 ) ), array( max( $d1, $d2 ), min( $d1, $d2 ) ) );
update_post_meta( $d1, WPCPM_Sponsor_Application::META_DECIDED, $tie - 10 );
WPCPM_Sponsor_Application::add_event( $d1, WPCPM_Sponsor_Application::EVENT_REOPENED, 3 );
update_post_meta( $d1, WPCPM_Sponsor_Application::META_STATE, 'new' );
ck( 'a reopened row loses its decision time and leaves the list', array( get_post_meta( $d1, WPCPM_Sponsor_Application::META_DECIDED, true ), array_map( static function ( $p ) { return (int) $p->ID; }, WPCPM_Sponsor_Application::decided_posts( 10 ) ) ), array( '', array( $d2 ) ) );
delete_post_meta( $d2, WPCPM_Sponsor_Application::META_DECIDED );
delete_option( WPCPM_Sponsor_Application::OPT_BACKFILL );
WPCPM_Sponsor_Application::maybe_backfill_decided();
ck( 'the one-time backfill stamps a decided row that predates the meta from its history, and marks itself done', array( (int) get_post_meta( $d2, WPCPM_Sponsor_Application::META_DECIDED, true ) > 0, get_option( WPCPM_Sponsor_Application::OPT_BACKFILL ) ), array( true, 1 ) );

foreach ( $GLOBALS['temp_files'] as $temp ) {
	if ( is_file( $temp ) ) { unlink( $temp ); }
}
foreach ( glob( sys_get_temp_dir() . '/wpcpm-sapp-uploads-' . getmypid() . '/*' ) as $stored_file ) {
	unlink( $stored_file );
}

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
