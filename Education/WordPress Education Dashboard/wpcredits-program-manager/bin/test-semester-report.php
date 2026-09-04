<?php
/**
 * The semester report: what a university is handed, and what it is never handed.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The snapshot carries nothing the institution could not print.** A walk over the whole
 *   stored array asserts no key is `email`, `email_key`, `status`, `accessibility`, `hours` or
 *   `grade`, and that no string value passes `is_email()`. The fixture deliberately hands the
 *   generator rows carrying every one of those, so the assertion is about what the generator
 *   leaves behind rather than about a fixture that never held it.
 * - **Consent is the student's, and it is read twice.** Once at generation, and again on every
 *   render through `consent_check()`, so a student who changes their answer to `No` leaves a
 *   document that was generated weeks ago without anybody regenerating it. Both halves are
 *   asserted, including that the stored snapshot is *not* rewritten by a render.
 * - **A candidate quote is not a quote.** A student who wrote something in the "proud of" box
 *   and never answered the permission question contributes nothing at all: the school does not
 *   get to see a quote in order to decide whether to ask for it.
 * - **The two consent questions are separate questions.** The fixture has a student who declines
 *   to be listed and releases a quote without her name. She is in the withheld count and in the
 *   quotes, which is what her two answers said.
 * - **The institution link decides which rows are this school's.** Empty keeps, "contains this
 *   institution" keeps, "names another" drops - on the Students Reports row and on the Feedback
 *   row alike. The fixture has a student whose Feedback row says "yes, with my name" on another
 *   school's record, and she is not listed here.
 * - **Several reports rows for one student are not merged.** Fields are never unioned; when no
 *   row's name matches, that student gets no links and one `ambiguous` in the withheld line.
 * - **Every read is by email, in chunks, through `formula_in( 'Email', ..., true )`.** Never by
 *   an institution-name formula: Airtable's `LOWER()` folds `Ł` and PHP's does not, so the name
 *   formula returns nothing for Uniwersytet Łódzki with every line looking correct. The fixture
 *   institution is called that on purpose, and no formula may contain its name.
 * - **A `WP_Error` from any read aborts the whole generation**, message verbatim, and writes no
 *   post: a report with Participation and no Student Projects looks finished.
 * - **The print document is a document, not a page.** No theme part, and every anchor shows its
 *   href as visible text, because the thing this produces is printed and a paper link that says
 *   "here" is a dead link.
 * - **Another institution's post ID and a post ID that does not exist read identically.** A
 *   refusal that only appears for a real post is a lookup service for which reports exist.
 * - **A manager may not answer a permission question for a student.** The one write this module
 *   must never make: nothing reaches Airtable and nothing is stamped.
 *
 * The handlers are reached the way a browser reaches them: `init()` is called, the callbacks it
 * registers are recorded, and the forms and links the screen draws are scraped for their field
 * names. Nothing here knows what the handler methods are called or what the hidden inputs are
 * named, which is the point - this suite was written against the contract and the spec while the
 * two classes were being written, and it is only useful if it does not quietly encode them.
 *
 * Run from the plugin root:  php bin/test-semester-report.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

// The two a site always has. `wp_date()` is handed whatever `get_option( 'date_format' )`
// returns, and a stub answering false would print every date as an empty string.
$GLOBALS['opts']       = array(
	'date_format' => 'Y-m-d',
	'time_format' => 'H:i',
);
$GLOBALS['transients'] = array();
$GLOBALS['umeta']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['posts']      = array();
$GLOBALS['next_id']    = 500;
$GLOBALS['modified']   = 1757000000;
$GLOBALS['flash']      = array();
$GLOBALS['hooks']      = array();
$GLOBALS['redirect']   = '';
$GLOBALS['died']       = '';
$GLOBALS['uid']        = 7;
$GLOBALS['manage']     = false;
$GLOBALS['acting']     = '';
$GLOBALS['index']      = array();
$GLOBALS['fetches']    = array();
$GLOBALS['updated']    = array();
$GLOBALS['created']    = array();
$GLOBALS['fail_table'] = '';
$GLOBALS['mail']       = array();
$GLOBALS['rows']       = array();
$GLOBALS['audit']      = array();
$GLOBALS['decisions']  = array();
$GLOBALS['referer']    = array();
$GLOBALS['post_types'] = array();
$GLOBALS['post_meta']  = array();

/* ---- WordPress, stubbed to the parts this code touches ------------------- */

class WP_Error {
	private $code;
	private $message;
	public function __construct( $c = '', $m = '' ) {
		$this->code    = $c;
		$this->message = $m;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '';
	public function __construct( $id = 0, $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = $name;
	}
	public function exists() { return $this->ID > 0; }
}

class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_title = '', $post_content = '';
	public $post_author = 0, $post_parent = 0, $post_modified_gmt = '', $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _x( $s, $c = '', $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
/*
 * Close enough to WordPress to be worth having: a scheme-less value gets `http://` in front of it
 * (which is how a typed email address becomes a URL with a userinfo part), and a scheme outside the
 * allowed list comes back empty. An identity stub here would let no URL ever fail, and two of the
 * assertions below exist because a URL has to be able to.
 */
function esc_url_raw( $u, $p = null ) {
	$u = trim( (string) $u );
	if ( '' === $u ) { return ''; }
	$allowed = null === $p ? array( 'http', 'https', 'mailto' ) : (array) $p;
	$scheme  = strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) );
	if ( '' === $scheme ) { return 'http://' . $u; }
	return in_array( $scheme, $allowed, true ) ? $u : '';
}
function esc_url( $u, $p = null, $c = 'display' ) { return esc_url_raw( $u, $p ); }
function wp_kses_post( $s ) { return (string) $s; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( str_replace( array( "\r\n", "\r" ), "\n", strip_tags( (string) $s ) ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return is_string( $e ) && (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_hash( $v, $scheme = 'auth' ) { return md5( (string) $v ); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function number_format_i18n( $n, $d = 0 ) { return (string) round( (float) $n, (int) $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_date( $f, $t = null, $z = null ) { return gmdate( (string) $f, null === $t ? time() : (int) $t ); }
function current_time( $type = 'timestamp', $gmt = 0 ) { return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time(); }
function apply_filters( $t, $v ) { return $v; }
function do_action() {}
function add_filter() {}
function plugins_url( $path = '', $plugin = '' ) { return WPCPM_PLUGIN_URL . ltrim( (string) $path, '/' ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_bloginfo( $what = '', $filter = '' ) { return 'charset' === $what ? 'UTF-8' : 'WordPress Credits'; }
function get_locale() { return 'en_US'; }
function is_rtl() { return false; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_add_inline_style() {}
function wp_localize_script() {}

/** Every hook the classes register, so a handler can be reached without knowing its name. */
function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) {
	if ( null !== $callback ) {
		$GLOBALS['hooks'][ (string) $hook ][] = $callback;
	}
}

/* Theme parts. The print document must contain none of these, so each leaves a mark. */
function get_header() { echo '<!--WPCPM-THEME-PART:header-->'; }
function get_footer() { echo '<!--WPCPM-THEME-PART:footer-->'; }
function wp_head() { echo '<!--WPCPM-THEME-PART:wp_head-->'; }
function wp_footer() { echo '<!--WPCPM-THEME-PART:wp_footer-->'; }
function get_template_part() { echo '<!--WPCPM-THEME-PART:template-->'; }
function body_class() { echo '<!--WPCPM-THEME-PART:body_class-->'; }
function language_attributes() { echo 'lang="en"'; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	if ( ! array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	unset( $GLOBALS['opts'][ $k ] );
	return true;
}

/**
 * Just enough `$wpdb` for the uninstall sweep's one query, with real LIKE semantics.
 *
 * The lock and the queue are options named after a report, so neither has a name uninstall
 * could carry a list of and the sweep has to go by prefix. `esc_like()` escapes `_`, which
 * matters here more than it looks: both prefixes end in one, and unescaped `wpcpm_report_ask_%`
 * also matches anything spelled with any character in those positions. The decoys in the
 * assertion exist to catch exactly that.
 */
class WPCPM_Fake_WPDB {
	public $options = 'wp_options';
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { return array( 'sql' => $sql, 'args' => $args ); }
	public function get_col( $query ) {
		$found = array();

		foreach ( (array) ( $query['args'] ?? array() ) as $pattern ) {
			$regex = '';

			for ( $i = 0, $n = strlen( (string) $pattern ); $i < $n; $i++ ) {
				$c = $pattern[ $i ];

				if ( '\\' === $c && $i + 1 < $n ) {
					$regex .= preg_quote( $pattern[ ++$i ], '/' );
				} elseif ( '%' === $c ) {
					$regex .= '.*';
				} elseif ( '_' === $c ) {
					$regex .= '.';
				} else {
					$regex .= preg_quote( $c, '/' );
				}
			}

			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( preg_match( '/^' . $regex . '$/', (string) $name ) ) { $found[ $name ] = true; }
			}
		}

		return array_keys( $found );
	}
}
$GLOBALS['wpdb'] = new WPCPM_Fake_WPDB();
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }

function get_user_meta( $id, $k = '', $single = false ) {
	if ( '' === $k ) { return isset( $GLOBALS['umeta'][ (int) $id ] ) ? $GLOBALS['umeta'][ (int) $id ] : array(); }
	return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ( $single ? '' : array() );
}
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function is_user_logged_in() { return (int) $GLOBALS['uid'] > 0; }
require_once __DIR__ . '/stubs/caps.php';
function wp_get_current_user() { return new WP_User( (int) $GLOBALS['uid'], 'Member One', 'member@example.test' ); }
function get_user_by( $by, $value ) {
	if ( 'id' === $by ) {
		$id = (int) $value;
		return $id > 0 ? new WP_User( $id, 'Person ' . $id, 'person' . $id . '@example.test' ) : false;
	}
	return false;
}
function get_users( $args = array() ) { return array(); }

function wp_insert_post( $args, $error = false ) {
	$id   = ++$GLOBALS['next_id'];
	$post = new WP_Post();

	foreach ( (array) $args as $key => $value ) {
		if ( property_exists( $post, $key ) ) { $post->$key = $value; }
	}

	$post->ID                = $id;
	$post->post_modified_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['modified']++ );
	$post->post_date_gmt     = $post->post_modified_gmt;
	$GLOBALS['posts'][ $id ] = $post;

	if ( isset( $args['meta_input'] ) && is_array( $args['meta_input'] ) ) {
		foreach ( $args['meta_input'] as $key => $value ) { update_post_meta( $id, $key, $value ); }
	}

	return $id;
}

function wp_update_post( $args, $error = false ) {
	$id = isset( $args['ID'] ) ? (int) $args['ID'] : 0;

	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; }

	foreach ( (array) $args as $key => $value ) {
		if ( 'ID' !== $key && property_exists( $GLOBALS['posts'][ $id ], $key ) ) {
			$GLOBALS['posts'][ $id ]->$key = $value;
		}
	}

	// Every save moves the clock forward by a second, which is what makes a second member's
	// copy of the form stale rather than merely equal.
	$GLOBALS['posts'][ $id ]->post_modified_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['modified']++ );

	return $id;
}

function get_post( $id = null ) {
	$id = $id instanceof WP_Post ? $id->ID : (int) $id;
	return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null;
}
function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; }
function get_post_modified_time( $format = 'U', $gmt = false, $post = null, $translate = false ) {
	$p = get_post( $post );

	if ( ! $p ) { return false; }

	$stamp = strtotime( $p->post_modified_gmt . ' UTC' );

	return 'U' === $format || 'G' === $format ? (int) $stamp : gmdate( (string) $format, (int) $stamp );
}
function get_post_time( $format = 'U', $gmt = false, $post = null, $translate = false ) {
	return get_post_modified_time( $format, $gmt, $post, $translate );
}
function get_post_field( $field, $post = null, $context = 'display' ) {
	$p = get_post( $post );
	return $p && property_exists( $p, $field ) ? $p->$field : '';
}
function get_the_title( $post = null ) { $p = get_post( $post ); return $p ? $p->post_title : ''; }
function mysql2date( $format, $date, $translate = true ) { return gmdate( (string) $format, (int) strtotime( (string) $date . ' UTC' ) ); }
function get_gmt_from_date( $date, $format = 'Y-m-d H:i:s' ) { return gmdate( $format, (int) strtotime( (string) $date . ' UTC' ) ); }
function date_i18n( $format, $stamp = false, $gmt = false ) { return gmdate( (string) $format, false === $stamp ? time() : (int) $stamp ); }
function wp_list_pluck( $list, $field ) { return array_column( (array) $list, $field ); }
function get_permalink( $id = 0 ) { return 'https://example.test/?p=' . (int) $id; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ] ); return true; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function add_post_meta( $id, $k, $v, $unique = false ) { return update_post_meta( $id, $k, $v ); }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function get_post_meta( $id, $k = '', $single = false ) {
	if ( '' === $k ) { return isset( $GLOBALS['pmeta'][ (int) $id ] ) ? $GLOBALS['pmeta'][ (int) $id ] : array(); }
	if ( ! isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ) { return $single ? '' : array(); }
	return $single ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : array( $GLOBALS['pmeta'][ (int) $id ][ $k ] );
}

/** Enough of `get_posts()` for a post type plus meta equality, which is all these queries do. */
function get_posts( $args = array() ) {
	$type   = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'post' );
	$fields = isset( $args['fields'] ) ? (string) $args['fields'] : '';
	$out    = array();

	// `any` means every status not excluded from search, and `trash` is excluded from search: the
	// same rule WordPress applies, so a query that says `any` misses trashed rows here too.
	$status = isset( $args['post_status'] ) ? $args['post_status'] : 'publish';
	$status = 'any' === $status ? array( 'publish', 'private', 'draft', 'pending', 'future', 'inherit' ) : (array) $status;

	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( ! in_array( $post->post_type, $type, true ) ) { continue; }
		if ( '' !== (string) $post->post_status && ! in_array( $post->post_status, $status, true ) ) { continue; }

		$ok = true;

		foreach ( isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			if ( get_post_meta( $id, $clause['key'], true ) !== $clause['value'] ) { $ok = false; break; }
		}

		if ( isset( $args['meta_key'] ) && isset( $args['meta_value'] )
			&& get_post_meta( $id, $args['meta_key'], true ) !== $args['meta_value'] ) {
			$ok = false;
		}

		if ( $ok ) { $out[] = 'ids' === $fields ? $id : $post; }
	}

	return $out;
}

function register_post_type( $type, $args = array() ) { $GLOBALS['post_types'][ $type ] = $args; return true; }
function register_post_meta( $type, $key, $args = array() ) { $GLOBALS['post_meta'][ $type ][ $key ] = $args; return true; }
function wp_get_post_revisions( $id, $args = array() ) { return array(); }
function wp_get_post_revision( $id ) { $p = get_post( $id ); return ( $p && 'revision' === $p->post_type ) ? $p : null; }
function wp_restore_post_revision( $id ) { $GLOBALS['restored'][] = (int) $id; $p = get_post( $id ); return $p ? (int) $p->post_parent : false; }

function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-' . esc_attr( $action ) . '" />';
	if ( $display ) { echo $html; }
	return $html;
}
function wp_create_nonce( $action = -1 ) { return 'nonce-' . $action; }
function wp_verify_nonce( $nonce, $action = -1 ) { return 1; }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { $GLOBALS['referer'][] = $action; return true; }
function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) { return 1; }
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	return add_query_arg( array( $name => 'nonce-' . $action ), $url );
}
function add_query_arg( $key, $value = null, $url = '' ) {
	if ( is_array( $key ) ) { $url = (string) $value; $pairs = $key; } else { $pairs = array( $key => $value ); }
	$join = false === strpos( $url, '?' ) ? '?' : '&';
	$bits = array();
	foreach ( $pairs as $k => $v ) { $bits[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v ); }
	return $url . $join . implode( '&', $bits );
}
function remove_query_arg( $key, $url = '' ) { return $url; }
function wp_safe_redirect( $url, $status = 302 ) { $GLOBALS['redirect'] = (string) $url; return true; }
function wp_redirect( $url, $status = 302 ) { return wp_safe_redirect( $url, $status ); }
function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['died'] = is_wp_error( $message ) ? $message->get_error_message() : (string) $message;
	echo $GLOBALS['died'];
	wpcpm_test_exit();
}
function status_header( $code ) {}
function nocache_headers() {}
function checked( $a, $b = true, $echo = true ) { $out = ( (string) $a === (string) $b ) ? ' checked' : ''; if ( $echo ) { echo $out; } return $out; }
function selected( $a, $b = true, $echo = true ) { $out = ( (string) $a === (string) $b ) ? ' selected' : ''; if ( $echo ) { echo $out; } return $out; }
function disabled( $a, $b = true, $echo = true ) { $out = ( (string) $a === (string) $b ) ? ' disabled' : ''; if ( $echo ) { echo $out; } return $out; }
function wp_next_scheduled( $hook, $args = array() ) { return false; }
function wp_schedule_single_event( $when, $hook, $args = array() ) { return true; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { return true; }

/** The handlers end in `exit`; here they end in an exception the runner can catch. */
class Left extends Exception {}
function wpcpm_test_exit() { throw new Left( (string) $GLOBALS['redirect'] ); }

/* ---- the collaborators, stubbed to their contracts ----------------------- */

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
	const ROLE_INSTITUTION = 'wpcpm_institution';
	public static function resolve_user( $user = null ) {
		if ( $user instanceof WP_User ) { return $user; }
		return wp_get_current_user();
	}
}

class WPCPM_Mentors_Sync {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $value ) {
		return is_scalar( $value ) && (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
	}
	public static function tracked_statuses( $settings = null ) {
		return array(
			'active' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ),
			'past'   => array( 'Graduate', 'Dropped out' ),
			'all'    => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Graduate', 'Dropped out' ),
		);
	}

	/** Record ID to name, as the sync's lookup phase writes it. */
	public static function lookups() {
		return array(
			'institutions' => array(
				'recINSTA000000001' => 'Uniwersytet Łódzki',
				'recINSTB000000002' => 'Universidad Beta',
				'recINSTC000000003' => 'Instituto Chunk',
				'recINSTD000000004' => 'Universidad Delta',
			),
			'teams'        => array(),
			'companies'    => array(),
		);
	}

	/** The real one's behaviour: record IDs become names, everything else is passed through. */
	public static function resolve( $value, $type ) {
		$value = trim( (string) $value );

		if ( '' === $value || false === strpos( $value, 'rec' ) ) { return $value; }

		$lookups = self::lookups();
		$map     = isset( $lookups[ $type ] ) ? $lookups[ $type ] : array();
		$out     = array();

		foreach ( explode( ',', $value ) as $part ) {
			$part = trim( $part );

			if ( '' === $part ) { continue; }

			if ( ! self::is_record_id( $part ) ) { $out[] = $part; continue; }

			if ( isset( $map[ $part ] ) ) { $out[] = $map[ $part ]; }
		}

		return implode( ', ', $out );
	}
}

class WPCPM_Students_Sync {
	const META_PROGRAM     = 'wpcpm_student_program';
	const META_MENTOR      = 'wpcpm_student_mentor';
	const META_INSTITUTION = 'wpcpm_student_institution';
	public static function get_program( $user_id ) {
		$stored = get_user_meta( (int) $user_id, self::META_PROGRAM, true );
		return is_array( $stored ) ? $stored : array();
	}
	public static function get_mentor( $user_id ) { return array(); }
}

class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-dashboard/'; }
}

class WPCPM_Settings {
	public static function get() {
		return array_merge(
			array(
				'students_table'            => 'tblStudents',
				'reports_table'             => 'tblReports',
				'feedback_table'            => 'tblFeedback',
				'institution_active_stages' => array( 'Confirmed', 'Student' ),
				'past_statuses'             => array( 'Graduate', 'Dropped out' ),
			),
			isset( $GLOBALS['settings_extra'] ) && is_array( $GLOBALS['settings_extra'] ) ? $GLOBALS['settings_extra'] : array()
		);
	}
	public static function get_value( $key, $fallback = false ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
	}
	public static function is_connected() { return true; }
}

/**
 * Airtable, recording every read so the formulas can be inspected.
 *
 * `formula_in()` is the real one's behaviour rather than a marker, because two of the
 * assertions in this suite are about what the formula says: that it compares `LOWER({Email})`
 * against a lowercased address, and that it never contains the institution's name.
 */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}

	public function formula_in( $field, array $values, $lower = false ) {
		$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );

		if ( empty( $values ) ) { return ''; }

		$tests = array();

		foreach ( $values as $value ) {
			$tests[] = $lower
				? sprintf( "LOWER({%s}) = '%s'", $field, strtolower( $value ) )
				: sprintf( "{%s} = '%s'", $field, $value );
		}

		return 1 === count( $tests ) ? $tests[0] : 'OR(' . implode( ',', $tests ) . ')';
	}

	public function formula_contains( $field, array $values, $lower = true ) {
		return empty( $values ) ? '' : 'FIND()';
	}

	public function fetch_all( $table, array $args = array() ) {
		$formula = isset( $args['formula'] ) ? (string) $args['formula'] : '';

		$GLOBALS['fetches'][] = array(
			'table'   => (string) $table,
			'formula' => $formula,
		);

		if ( (string) $table === $GLOBALS['fail_table'] ) {
			return new WP_Error( 'wpcpm_airtable_http', 'Airtable said 503 and the read was abandoned.' );
		}

		$wanted = array();

		if ( preg_match_all( "/'([^']*)'/", $formula, $found ) ) {
			foreach ( $found[1] as $value ) { $wanted[ strtolower( $value ) ] = true; }
		}

		$out = array();

		foreach ( isset( $GLOBALS['rows'][ (string) $table ] ) ? $GLOBALS['rows'][ (string) $table ] : array() as $record ) {
			$email = strtolower( trim( (string) ( isset( $record['fields']['Email'] ) ? $record['fields']['Email'] : '' ) ) );

			if ( isset( $wanted[ $email ] ) ) { $out[] = $record; }
		}

		return $out;
	}

	public function fetch_page( $table, array $args = array() ) {
		$records = $this->fetch_all( $table, $args );
		return is_wp_error( $records ) ? $records : array( 'records' => $records, 'offset' => null );
	}

	public function get_record( $table, $record_id ) {
		foreach ( isset( $GLOBALS['rows'][ (string) $table ] ) ? $GLOBALS['rows'][ (string) $table ] : array() as $record ) {
			if ( (string) $record['id'] === (string) $record_id ) { return $record; }
		}
		return new WP_Error( 'wpcpm_airtable_missing', 'No such record.' );
	}

	public function update_records( $table, array $records ) {
		foreach ( $records as $record ) { $GLOBALS['updated'][] = $record; }
		return array_column( $records, 'id' );
	}

	public function create_records( $table, array $records ) {
		$ids = array();
		foreach ( $records as $record ) {
			$GLOBALS['created'][] = $record['fields'];
			$ids[]                = sprintf( 'recNEW%011d', count( $GLOBALS['created'] ) );
		}
		return $ids;
	}

	/** The real one, byte for byte: three shapes come back from the API and all three flatten. */
	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) { return (string) $value['name']; }

			$parts = array();

			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) { $parts[] = (string) $item; } elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) { $parts[] = (string) $item['name']; }
			}

			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Also the real one: a link cell is an array, and anything else holds no link at all. */
	public static function link_ids( $value ) {
		if ( ! is_array( $value ) ) { return array(); }

		$ids = array();

		foreach ( $value as $item ) {
			if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) {
				$ids[] = $item;
			} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$ids[] = (string) $item['id'];
			}
		}

		return $ids;
	}
}

/** The roster index, stubbed to its contract: an envelope with a read time and rows. */
class WPCPM_Roster_Index {
	const NEVER_SHOWN = array( 'SPAM', 'Duplicated' );

	public static function read( $record_id ) {
		$store = isset( $GLOBALS['index'][ (string) $record_id ] )
			? $GLOBALS['index'][ (string) $record_id ]
			: array( 'read' => 0, 'rows' => array() );

		return array( 'v' => 4, 'read' => (int) $store['read'], 'rows' => $store['rows'] );
	}

	public static function rows( $record_id ) { return self::read( $record_id )['rows']; }

	public static function groups( $record_id, $cohort = '' ) {
		$groups  = array( 'current' => array(), 'waiting' => array(), 'finished' => array(), 'not_started' => array() );
		$tracked = WPCPM_Mentors_Sync::tracked_statuses();
		$narrow  = WPCPM_Cohort::is_key( $cohort );

		foreach ( self::rows( $record_id ) as $key => $row ) {
			$status = trim( (string) ( isset( $row['status'] ) ? $row['status'] : '' ) );
			if ( in_array( $status, self::NEVER_SHOWN, true ) ) { continue; }
			if ( $narrow && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) !== $cohort ) { continue; }
			if ( in_array( $status, $tracked['active'], true ) ) {
				$groups[ empty( $row['reports'] ) ? 'waiting' : 'current' ][ $key ] = $row;
				continue;
			}
			if ( in_array( $status, $tracked['past'], true ) ) { $groups['finished'][ $key ] = $row; continue; }
			$groups['not_started'][ $key ] = $row;
		}

		return $groups;
	}
}

class WPCPM_Institutions_Index {
	public static function row( $record_id ) {
		$names = array(
			'recINSTA000000001' => 'Uniwersytet Łódzki',
			'recINSTB000000002' => 'Universidad Beta',
			'recINSTC000000003' => 'Instituto Chunk',
			'recINSTD000000004' => 'Universidad Delta',
		);

		// $GLOBALS['inst_rows'] is read first, because it is what a test just set up for this
		// run; the map above is only the shared default the older fixtures rely on and never
		// disagrees with it where the two overlap (A, B, C). The job's own fixtures (a dozen
		// "Job University" rows, made up per run) exist only in $GLOBALS['inst_rows'].
		$live = isset( $GLOBALS['inst_rows'][ (string) $record_id ]['name'] ) ? trim( (string) $GLOBALS['inst_rows'][ (string) $record_id ]['name'] ) : '';
		$name = '' !== $live ? $live : ( isset( $names[ (string) $record_id ] ) ? $names[ (string) $record_id ] : '' );

		return array(
			'record_id' => (string) $record_id,
			'name'      => $name,
			'stage'     => 'Confirmed',
			'country'   => 'PL',
		);
	}
	public static function rows() { return isset( $GLOBALS['inst_rows'] ) && is_array( $GLOBALS['inst_rows'] ) ? $GLOBALS['inst_rows'] : array(); }
	public static function has( $record_id ) { return '' !== self::row( $record_id )['name']; }
}

class WPCPM_Institution_Agreement {
	public static function is_settled( $record_id ) { return true; }
}

class WPCPM_Institution_Members {
	const META_RECORD  = 'wpcpm_institution_record_id';
	const META_PROFILE = 'wpcpm_institution_profile';
	public static function institution_of( $user = null ) { return (string) $GLOBALS['acting']; }
	public static function memberships_of( $user = null ) {
		return '' === (string) $GLOBALS['acting'] ? array() : array( (string) $GLOBALS['acting'] );
	}
	public static function is_member( $user = null ) { return '' !== (string) $GLOBALS['acting']; }
	public static function members_of( $record_id ) {
		return isset( $GLOBALS['members'][ (string) $record_id ] ) ? $GLOBALS['members'][ (string) $record_id ] : array();
	}
}

class WPCPM_Institution_Roster {
	const TYPE_REPORT = 'report';
	// The switcher argument. Copied, and checked against the real class below: a manager's
	// redirect after asking carries it, and without it they land on whichever institution is
	// their own fallback and read another school's report as the answer.
	const ARG_VIEW = 'wpcpm_institution_view';
	public static function resolve_institution( $viewer = null, $can_manage = false ) { return (string) $GLOBALS['acting']; }
	public static function switcher_options() { return array(); }
	public static function claim( $record, $action, $type = 'student', $user = null ) {
		return WPCPM_Institution_Policy::refusal();
	}
}

class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}

class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_CACHE = 'cache';
	const EVIDENCE_LIVE  = 'live';
	public static function record( array $entry ) { $GLOBALS['audit'][] = $entry; return 1; }
}

class WPCPM_Flash {
	const META = 'wpcpm_flash';
	public static function set( $channel, $value, $user = 0 ) { $GLOBALS['flash'][ $channel ] = $value; }
	public static function take( $channel, $user = 0 ) {
		$value = isset( $GLOBALS['flash'][ $channel ] ) ? $GLOBALS['flash'][ $channel ] : null;
		unset( $GLOBALS['flash'][ $channel ] );
		return $value;
	}
	public static function peek( $channel, $user = 0 ) {
		return isset( $GLOBALS['flash'][ $channel ] ) ? $GLOBALS['flash'][ $channel ] : null;
	}
}

class WPCPM_Mail {
	public static function send( $recipient, $context, $build ) {
		$user = $recipient instanceof WP_User ? $recipient : get_user_by( 'id', (int) $recipient );

		if ( ! $user instanceof WP_User || ! $user->exists() ) { return false; }

		$GLOBALS['mail'][] = array( 'to' => $user->ID, 'context' => (string) $context );

		return true;
	}
	public static function send_to( $email, $context, $build, $locale = '' ) {
		$GLOBALS['mail'][] = array( 'to' => (string) $email, 'context' => (string) $context );
		return true;
	}
}

class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$GLOBALS['ceiling'][ $key ] = ( isset( $GLOBALS['ceiling'][ $key ] ) ? $GLOBALS['ceiling'][ $key ] : 0 ) + max( 1, (int) $amount );
		return $GLOBALS['ceiling'][ $key ] <= (int) $limit;
	}
}

/**
 * The module, reduced to the one thing the job asks of it: telling the managers. What is
 * recorded is the context and which setting named the recipients, which is the whole of the
 * contract the report has with it.
 */
class WPCPM_Institutions {
	public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' ) {
		$message = is_callable( $build ) ? call_user_func( $build, new WP_User( 99, 'Manager', 'maciej@a8c.com' ) ) : array();
		$GLOBALS['mail'][] = array( 'to' => 'managers:' . $setting_key, 'context' => (string) $context, 'subject' => isset( $message['subject'] ) ? $message['subject'] : '', 'body' => isset( $message['body'] ) ? $message['body'] : '' );
		return 1;
	}
}

class WPCPM_Contribution_Teams {
	public static function names() { return array( 'Documentation', 'Training', 'Polyglots', 'Marketing', 'Design' ); }
	public static function all() { return self::names(); }
}

/**
 * The fence, as a faithful miniature.
 *
 * Allowed for a manager, and for a member whose own institution is among the subject's. That
 * is enough for this suite because what is being tested is which institution the subject names,
 * not how the real policy decides: `subject_post()` reads the institution off the post's own
 * meta and never off the form, which is what makes another school's post ID a refusal.
 */
class WPCPM_Institution_Policy {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';

	const ACT_VIEW_ROSTER          = 'view_roster';
	const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
	const ACT_EDIT_SEMESTER_REPORT = 'edit_semester_report';
	const ACT_EXPORT               = 'export';

	const REFUSAL_CODE = 'wpcpm_inst_unknown';

	public static function subject_institution( $record_id ) {
		return array(
			'type'            => 'institution',
			'id'              => (string) $record_id,
			'institution_ids' => array( (string) $record_id ),
			'evidence'        => 'index',
		);
	}

	public static function subject_post( WP_Post $post, $meta_key ) {
		$record = trim( (string) get_post_meta( $post->ID, $meta_key, true ) );

		return array(
			'type'            => 'semester_report',
			'id'              => (int) $post->ID,
			'institution_ids' => '' === $record ? array() : array( $record ),
			'evidence'        => 'cache',
		);
	}

	public static function decide( $action, array $subject, $user = null ) {
		$ids = array_values( array_filter( (array) $subject['institution_ids'], array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );

		$GLOBALS['decisions'][] = array( 'action' => (string) $action, 'ids' => $ids );

		if ( $GLOBALS['manage'] ) {
			return array(
				'allowed'     => true,
				'ground'      => self::GROUND_MANAGER,
				'institution' => isset( $ids[0] ) ? $ids[0] : '',
				'fields'      => null,
				'why'         => '',
			);
		}

		// The real map's row for this action holds only GROUND_MANAGER: the approval design
		// of 4 September 2026, decision 1, gives the write to the program and not to a member.
		if ( self::ACT_EDIT_SEMESTER_REPORT === $action ) {
			return array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => 'no-ground' );
		}

		if ( '' !== (string) $GLOBALS['acting'] && in_array( (string) $GLOBALS['acting'], $ids, true ) ) {
			return array(
				'allowed'     => true,
				'ground'      => self::GROUND_MEMBER,
				'institution' => (string) $GLOBALS['acting'],
				'fields'      => null,
				'why'         => '',
			);
		}

		return array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => 'no-ground' );
	}

	public static function refusal() {
		return new WP_Error( self::REFUSAL_CODE, 'That record is not on your roster.' );
	}

	public static function scope( array $decision, array $keyed ) {
		if ( empty( $decision['allowed'] ) || ! array_key_exists( 'fields', $decision ) ) { return array(); }
		if ( null === $decision['fields'] ) { return $keyed; }
		$permitted = array();
		foreach ( (array) $decision['fields'] as $key ) { $permitted[ (string) $key ] = true; }
		return array_intersect_key( $keyed, $permitted );
	}
}

/* ---- the real pieces ----------------------------------------------------- */

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
// WPCPM_Ceiling is stubbed above, so a test can pre-fill $GLOBALS['ceiling'] to make a claim
// land full; the real option-backed class is not loaded here, and nothing this suite exercises
// (class-wpcpm-student-report-form.php's fields() included) calls the real one.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked, as a sentence.
 * @param mixed  $got   What the code returned.
 * @param mixed  $want  What it should have returned.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }

/**
 * Run whatever was registered on one hook.
 *
 * Nothing here is WordPress, so a post type registered on `init` is registered nowhere until
 * something calls the callback. The alternative is asserting that `init()` registers a hook and
 * never looking at what the hook does, which is how a post type ships public.
 *
 * @param string $hook Hook name.
 */
function fire( $hook ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $callback ) {
		call_user_func( $callback );
	}
}

/*
 * The classes under test, plus the feedback form the consent is written on.
 *
 * Loaded through a rewrite rather than `require`, because every one of them ends a handler in
 * `exit` and the runner has to survive them. ABSPATH is defined above, so the guard at the top
 * of each file is dead code and rewriting the `exit;` inside it costs nothing.
 */
$missing = array();

foreach ( array(
	'includes/modules/class-wpcpm-student-feedback.php',
	'includes/modules/class-wpcpm-semester-report.php',
	'includes/modules/class-wpcpm-semester-report-screen.php',
) as $relative ) {
	$path = WPCPM_PLUGIN_DIR . $relative;

	if ( ! is_readable( $path ) ) {
		$missing[] = $relative;
		continue;
	}

	$source = (string) file_get_contents( $path );
	$source = preg_replace( '/^<\?php/', '', $source, 1 );
	$source = str_replace( "defined( 'ABSPATH' ) || exit;", '', $source );
	$source = preg_replace( '/^\s*exit;\s*$/m', "\t\twpcpm_test_exit();", $source );

	eval( $source );
}

if ( ! empty( $missing ) ) {
	printf( "MISSING  %s\n", implode( "\n         ", $missing ) );
	echo "\nThe semester report classes are not on disk yet, so nothing below can run.\n";
	exit( 1 );
}

/* ---- the fixture --------------------------------------------------------- */

// Named with a Polish diacritic on purpose: the reads are by email precisely because a formula
// built from this name would fetch nothing, and one assertion below is that no formula holds it.
$A = 'recINSTA000000001';
$B = 'recINSTB000000002';
$C = 'recINSTC000000003';
$D = 'recINSTD000000004';

$COHORT = '2026-H1';
$PREV   = '2025-H2';

$LIST_FIELD  = 'F3 - Report: my institution may list me in its semester report';
$QUOTE_FIELD = 'F3 - Report: my institution may quote my feedback in its semester report';
$QUOTE_TEXT  = 'F3 - One example of a contribution you are proud of';

/**
 * One roster index row, in the shape the students sync writes.
 *
 * @param string $record Students record ID.
 * @param string $name   Full name.
 * @param string $status Airtable status.
 * @param string $start  Start date.
 * @param array  $extra  Overrides.
 * @return array
 */
function row( $record, $name, $status, $start, array $extra = array() ) {
	$email = strtolower( str_replace( ' ', '.', $name ) ) . '@example.test';

	return $extra + array(
		'record_id'      => $record,
		'name'           => $name,
		'email'          => $email,
		'email_key'      => $email,
		'status'         => $status,
		'institution'    => 'recINSTA000000001',
		'start'          => $start,
		'end'            => '',
		'has_mentor'     => true,
		'username'       => '',
		'field_of_study' => '',
		'tutor'          => '',
		'mentor_name'    => 'Marta Mentor',
		'team'           => '',
		'website'        => '',
		// On the row and never in the document: a school is told how many students it sent and
		// how they are getting on, not how many hours each of them has logged.
		'hours'          => '87.5',
		// The disclosure the index's own cleaner drops. Here so the walk below is asserting
		// what the generator leaves out rather than what the fixture never held.
		'accessibility'  => 'Uses a screen reader',
		'import_key'     => '',
		'reports'        => array(),
		'user_id'        => 0,
	);
}

$GLOBALS['index'][ $A ] = array(
	'read' => 1756900000,
	'rows' => array(
		'recSTUA0000000001' => row( 'recSTUA0000000001', 'Ana Fidelitas', 'In Sensei', '2026-02-10', array( 'user_id' => 11 ) ),
		'recSTUA0000000002' => row( 'recSTUA0000000002', 'Bruno Kowalski', 'In Sensei', '2026-02-11', array( 'user_id' => 12 ) ),
		'recSTUA0000000003' => row( 'recSTUA0000000003', 'Carla Nowak', 'Graduate', '2026-03-01', array( 'user_id' => 13 ) ),
		'recSTUA0000000004' => row( 'recSTUA0000000004', 'Dana Ortiz', 'In Sensei', '2026-02-12', array( 'user_id' => 14 ) ),
		'recSTUA0000000005' => row( 'recSTUA0000000005', 'Eva Zielinska', 'In Sensei', '2026-02-13', array( 'user_id' => 15 ) ),
		'recSTUA0000000006' => row( 'recSTUA0000000006', 'Felipe Silva', 'Developer Track', '2026-04-01', array( 'user_id' => 16 ) ),
		'recSTUA0000000007' => row( 'recSTUA0000000007', 'Gustavo Lima', 'Not moving forward', '2026-05-01' ),
		'recSTUA0000000008' => row( 'recSTUA0000000008', 'Hanna Weiss', 'Dropped out', '2026-02-14' ),
		'recSTUA0000000009' => row( 'recSTUA0000000009', 'Iker Ambiguo', 'In Sensei', '2026-02-15', array( 'user_id' => 19 ) ),
		'recSTUA0000000010' => row( 'recSTUA0000000010', 'Jonas Pending', 'Pending graduation', '2026-06-01' ),
		'recSTUA0000000011' => row( 'recSTUA0000000011', 'Spam Bot', 'SPAM', '2026-02-10' ),
		'recSTUA0000000012' => row( 'recSTUA0000000012', 'Twice Over', 'Duplicated', '2026-02-10' ),
		// The semester before, for the two-number comparison and nothing else.
		'recSTUA0000000013' => row( 'recSTUA0000000013', 'Jana Reyes', 'Graduate', '2025-09-01' ),
		'recSTUA0000000014' => row( 'recSTUA0000000014', 'Karol Bak', 'Dropped out', '2025-10-01' ),
	),
);

$GLOBALS['index'][ $B ] = array(
	'read' => 1756900001,
	'rows' => array(
		'recSTUB0000000001' => array( 'record_id' => 'recSTUB0000000001', 'name' => 'Beta Student', 'email' => 'beta@example.test', 'email_key' => 'beta@example.test', 'status' => 'In Sensei', 'start' => '2026-02-01', 'institution' => 'recINSTB000000002', 'reports' => array(), 'user_id' => 0 ),
	),
);

$GLOBALS['index'][ $D ] = array(
	'read' => 1756900002,
	'rows' => array(
		'recSTUD0000000001' => array( 'record_id' => 'recSTUD0000000001', 'name' => 'Delta Student', 'email' => 'delta@example.test', 'email_key' => 'delta@example.test', 'status' => 'In Sensei', 'start' => '2026-02-01', 'institution' => 'recINSTD000000004', 'reports' => array(), 'user_id' => 0 ),
	),
);

// A hundred and twenty students in one cohort, for the chunk arithmetic and nothing else.
$chunk_rows = array();

for ( $n = 1; $n <= 120; $n++ ) {
	$id                = sprintf( 'recSTUC%010d', $n );
	$chunk_rows[ $id ] = array(
		'record_id'   => $id,
		'name'        => 'Chunk Student ' . $n,
		'email'       => 'chunk' . $n . '@example.test',
		'email_key'   => 'chunk' . $n . '@example.test',
		'status'      => 'In Sensei',
		'start'       => '2026-02-01',
		'institution' => $C,
		'reports'     => array(),
		'user_id'     => 0,
	);
}

$GLOBALS['index'][ $C ] = array( 'read' => 1756900003, 'rows' => $chunk_rows );

/**
 * One Students Reports row.
 *
 * @param string $id     Airtable record ID.
 * @param string $email  Email address.
 * @param string $name   Name as the reports row spells it.
 * @param array  $links  Institution link value.
 * @param array  $fields The rest of the cells.
 * @return array
 */
function report_row( $id, $email, $name, array $links, array $fields = array() ) {
	return array(
		'id'     => $id,
		'fields' => $fields + array(
			'Email'                   => $email,
			'Name'                    => $name,
			'Educational institution' => $links,
			// Never generated, each for its own reason. Present so the assertions below are
			// about the generator's restraint rather than about an empty fixture.
			'Hours'                   => '112',
			'Contribution Project Summary' => 'A project summary the school is not sent.',
			'Mentor'                  => array( 'recMENTOR000000001' ),
		),
	);
}

$GLOBALS['rows']['tblReports'] = array(
	report_row(
		'recREPA0000000001',
		'ana.fidelitas@example.test',
		'Ana Fidelitas',
		array( $A ),
		array(
			'Main Contribution Team'                          => 'Documentation',
			'Personal Website URL'                            => 'https://ana.example.test/',
			'Post Reflection: Building Your Personal Website' => 'https://ana.example.test/hello-website/',
			'Closing post URL'                                => 'https://ana.example.test/closing/',
			'WP event participation URL'                      => 'https://europe.wordcamp.org/2026/',
		)
	),
	// No institution link at all, which is the common case in the base and is kept.
	report_row(
		'recREPA0000000002',
		'bruno.kowalski@example.test',
		'Bruno Kowalski',
		array(),
		array(
			'Main Contribution Team'            => 'Documentation',
			'Personal Website URL'              => 'https://www.bruno.example.test/blog',
			'Post Reflection: Halfway Check-In' => 'https://www.bruno.example.test/blog/halfway/',
			'WP event participation URL'        => 'https://europe.wordcamp.org/2026/',
		)
	),
	report_row( 'recREPA0000000003', 'carla.nowak@example.test', 'Carla Nowak', array( $A ), array( 'Main Contribution Team' => 'Training' ) ),
	// Named to another institution: dropped whole, so Dana has no links, no team and no event.
	report_row(
		'recREPA0000000004',
		'dana.ortiz@example.test',
		'Dana Ortiz',
		array( $B ),
		array(
			'Main Contribution Team'     => 'Design',
			'Personal Website URL'       => 'https://dana.example.test/',
			'WP event participation URL' => 'https://example.test/dana-event/',
		)
	),
	// Consented by her blog address and has no blog address: withheld rather than named.
	report_row( 'recREPA0000000005', 'eva.zielinska@example.test', 'Eva Zielinska', array( $A ), array( 'Main Contribution Team' => 'Training' ) ),
	// Two links, one of which is this institution: kept.
	report_row(
		'recREPA0000000006',
		'felipe.silva@example.test',
		'Felipe Silva',
		array( $B, $A ),
		array(
			'Main Contribution Team'     => 'Polyglots',
			'Personal Website URL'       => 'https://felipe.example.test/',
			'Closing post URL'           => 'https://felipe.example.test/closing/',
			'WP event participation URL' => 'https://wordcamp.org/central-america-2026/',
		)
	),
	// In the cohort and on this school's roster, but she released nothing: her team counts and
	// her event does not, because an event is printed per consenting student.
	report_row(
		'recREPA0000000007',
		'hanna.weiss@example.test',
		'Hanna Weiss',
		array( $A ),
		array(
			'Main Contribution Team'     => 'Documentation',
			'WP event participation URL' => 'https://example.test/hanna-event/',
		)
	),
	// Two rows for one student, neither spelling her name as the roster does.
	report_row( 'recREPA0000000008', 'iker.ambiguo@example.test', 'Iker A. Ambiguo', array( $A ), array( 'Main Contribution Team' => 'Marketing', 'Closing post URL' => 'https://iker.example.test/one/' ) ),
	report_row( 'recREPA0000000009', 'iker.ambiguo@example.test', 'I. Ambiguo', array( $A ), array( 'Main Contribution Team' => 'Marketing', 'Closing post URL' => 'https://iker.example.test/two/' ) ),
);

/**
 * One Feedback row.
 *
 * @param string $id      Airtable record ID.
 * @param string $email   Email address.
 * @param string $name    Name.
 * @param array  $links   Institution link value.
 * @param string $listing The listing permission answer.
 * @param string $quoting The quoting permission answer.
 * @param string $text    The quote itself.
 * @return array
 */
function feedback_row( $id, $email, $name, array $links, $listing, $quoting, $text ) {
	return array(
		'id'     => $id,
		'fields' => array(
			'Email'                                                => $email,
			'Name'                                                 => $name,
			'Institution'                                          => $links,
			'F3 - Report: my institution may list me in its semester report' => $listing,
			'F3 - Report: my institution may quote my feedback in its semester report' => $quoting,
			'F3 - One example of a contribution you are proud of' => $text,
			// A rating, and an aggregate over ten people is a disclosure about ten people.
			'F3 - Overall experience so far'                       => '5',
		),
	);
}

$GLOBALS['rows']['tblFeedback'] = array(
	feedback_row( 'recFDBA0000000001', 'ana.fidelitas@example.test', 'Ana Fidelitas', array( $A ), 'Yes, with my name', 'Yes, with my name', 'Contributing to Documentation changed how I read code.' ),
	feedback_row( 'recFDBA0000000002', 'bruno.kowalski@example.test', 'Bruno Kowalski', array( $A ), 'Yes, by my blog address only', 'Yes, without my name', 'The mentor calls were the best part of the term.' ),
	// Declines to be listed and releases a quote without her name: two questions, two answers.
	feedback_row( 'recFDBA0000000003', 'carla.nowak@example.test', 'Carla Nowak', array( $A ), 'No', 'Yes, without my name', 'I would tell any student on my course to try it.' ),
	// Wrote something in the box and answered neither question: contributes nothing at all.
	feedback_row( 'recFDBA0000000004', 'dana.ortiz@example.test', 'Dana Ortiz', array( $A ), '', '', 'A quote nobody has released.' ),
	// Gave permission and wrote nothing: a permission with nothing to quote is not a quote.
	feedback_row( 'recFDBA0000000005', 'eva.zielinska@example.test', 'Eva Zielinska', array( $A ), 'Yes, by my blog address only', 'Yes, with my name', '' ),
	feedback_row( 'recFDBA0000000006', 'felipe.silva@example.test', 'Felipe Silva', array( $A ), 'Yes, with my name', 'Yes, without my name', 'I shipped my first patch in week three.' ),
	feedback_row( 'recFDBA0000000007', 'iker.ambiguo@example.test', 'Iker Ambiguo', array( $A ), 'Yes, with my name', '', '' ),
	// Her consent is recorded against another institution's record, so this one never sees it.
	feedback_row( 'recFDBA0000000008', 'hanna.weiss@example.test', 'Hanna Weiss', array( $B ), 'Yes, with my name', 'Yes, with my name', 'A quote released to a different school.' ),
);

/** The snapshot id of one address: stable, and not the address. */
function report_id_of( $email_key ) { return substr( wp_hash( strtolower( trim( (string) $email_key ) ) ), 0, 12 ); }

/** One entry of a snapshot list, by its id. */
function entry_by_id( array $list, $id ) {
	foreach ( $list as $entry ) {
		if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $id ) { return $entry; }
	}
	return null;
}

/** A student's links as a label => url map, so the order of the list is not pinned here. */
function links_map( $entry ) {
	$out = array();

	foreach ( isset( $entry['links'] ) ? (array) $entry['links'] : array() as $link ) {
		$out[ (string) $link['label'] ] = (string) $link['url'];
	}

	return $out;
}

/** Every string in a nested array, with the key it sat under. */
function walk_pairs( $value, $key = '', array &$out = array() ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $child_key => $child ) { walk_pairs( $child, $child_key, $out ); }
		return $out;
	}

	$out[] = array( 'key' => (string) $key, 'value' => $value );

	return $out;
}

echo "\n=== The contract: the post type, the meta and the eight sections ===\n";

ck( 'the post type is the spec\'s', WPCPM_Semester_Report::POST_TYPE, 'wpcpm_inst_report' );
ck( 'the institution meta key is the policy\'s input', WPCPM_Semester_Report::META_INSTITUTION, '_wpcpm_report_institution' );
ck( 'the cohort key', WPCPM_Semester_Report::META_COHORT, '_wpcpm_report_cohort' );
ck( 'the snapshot key', WPCPM_Semester_Report::META_DATA, '_wpcpm_report_data' );
ck( 'the sections key', WPCPM_Semester_Report::META_SECTIONS, '_wpcpm_report_sections' );
ck( 'the choices key', WPCPM_Semester_Report::META_CHOICES, '_wpcpm_report_choices' );
ck( 'the state key', WPCPM_Semester_Report::META_STATE, '_wpcpm_report_state' );

// Fifty is the number of addresses one Airtable formula is asked to hold, and it is asserted
// separately below by counting reads. Named here so the two cannot drift apart silently.
ck( 'addresses are read fifty at a time', WPCPM_Semester_Report::CHUNK, 50 );
ck( 'each read is cached five minutes', WPCPM_Semester_Report::CACHE_TTL, 300 );
ck( 'a narrative is capped at five thousand characters', WPCPM_Semester_Report::MAX_TEXT, 5000 );

$sections = WPCPM_Semester_Report::sections();

ck(
	'the eight sections are the spec\'s, in its order',
	array_keys( $sections ),
	array( 'overview', 'participation', 'teams', 'projects', 'recognition', 'continuing', 'feedback', 'ahead' )
);

$generated_sections = array();
$defaulted_sections = array();

foreach ( $sections as $key => $spec ) {
	if ( ! empty( $spec['generated'] ) ) { $generated_sections[] = $key; }
	if ( '' !== trim( (string) ( isset( $spec['default'] ) ? $spec['default'] : '' ) ) ) { $defaulted_sections[] = $key; }
}

ck( 'five sections carry generated content', $generated_sections, array( 'participation', 'teams', 'projects', 'recognition', 'feedback' ) );
ck( 'four arrive with a narrative already written', $defaulted_sections, array( 'overview', 'projects', 'feedback', 'ahead' ) );

WPCPM_Semester_Report::init();
fire( 'init' );

$type_args = isset( $GLOBALS['post_types'][ WPCPM_Semester_Report::POST_TYPE ] ) ? $GLOBALS['post_types'][ WPCPM_Semester_Report::POST_TYPE ] : array();

// Section 9: every post type this module keeps is private, has no wp-admin screen and is not in
// REST. A report holds a school's students by name; the one place it is read is the dashboard.
ck( 'the post type is private', isset( $type_args['public'] ) ? (bool) $type_args['public'] : true, false );
ck( 'it has no wp-admin screen', isset( $type_args['show_ui'] ) ? (bool) $type_args['show_ui'] : true, false );
ck( 'and it is not in REST', isset( $type_args['show_in_rest'] ) ? (bool) $type_args['show_in_rest'] : true, false );
ck( 'it supports revisions, which is how a restore brings a narrative back', in_array( 'revisions', isset( $type_args['supports'] ) ? (array) $type_args['supports'] : array(), true ), true );

$meta_args = isset( $GLOBALS['post_meta'][ WPCPM_Semester_Report::POST_TYPE ] ) ? $GLOBALS['post_meta'][ WPCPM_Semester_Report::POST_TYPE ] : array();

// Without this a restore brings back the title and leaves the narrative on the newer version,
// which is the half-restored state nobody can see and everybody has to unpick by hand.
ck(
	'the sections travel with a revision',
	! empty( $meta_args[ WPCPM_Semester_Report::META_SECTIONS ]['revisions_enabled'] ),
	true
);
ck(
	'and so do the quote choices',
	! empty( $meta_args[ WPCPM_Semester_Report::META_CHOICES ]['revisions_enabled'] ),
	true
);

echo "\n=== Generating: the numbers come from the index ===\n";

ck( 'nothing is found before anything is generated', WPCPM_Semester_Report::find( $A, $COHORT ), null );

$post_id = WPCPM_Semester_Report::generate( $A, $COHORT );

ck( 'generating returns a post ID', is_int( $post_id ) && $post_id > 0, true );

// The five-minute cache holds what the report may use, not Airtable's raw rows: a quote nobody
// has released is held nowhere on this site, while the released ones are cached as read.
$cache_json = wp_json_encode( $GLOBALS['transients'] );
ck( 'the read cache holds no unreleased words', has( $cache_json, 'A quote nobody has released' ), false );
ck( 'but does hold a released quote, as read', has( $cache_json, 'changed how I read code' ), true );
ck( 'and no raw Airtable record shape', has( $cache_json, '"fields"' ), false );
ck( 'while the student who wrote and did not answer is still somebody to ask', in_array( 14, (array) WPCPM_Semester_Report::consent_candidates( get_post( $post_id ) ), true ), true );

$report = get_post( $post_id );
$snap   = WPCPM_Semester_Report::snapshot( $report );

ck( 'the snapshot is at version one', isset( $snap['v'] ) ? (int) $snap['v'] : 0, 1 );
ck( 'it names its cohort', isset( $snap['cohort'] ) ? $snap['cohort'] : '', $COHORT );
ck( 'and it records when it was generated', isset( $snap['generated'] ) && (int) $snap['generated'] > 0, true );

// The read time is the index's, not "now". A school reading a report has to be able to tell how
// old the roster behind it is, and a generation timestamp answers a different question.
ck( 'it carries the index read time', isset( $snap['read'] ) ? (int) $snap['read'] : 0, 1756900000 );
ck( 'which is the roster index\'s own', (int) $snap['read'], (int) WPCPM_Roster_Index::read( $A )['read'] );

ck(
	'participation is the fixture\'s numbers',
	$snap['participation'],
	array(
		'signed_up'   => 10,
		'graduated'   => 1,
		'pending'     => 1,
		'active'      => 6,
		'withdrawn'   => 1,
		'not_started' => 1,
		'other'       => 0,
	)
);

// The same function the roster screen counts with, so a report and a screen never disagree
// about how many students a school sent. SPAM and Duplicated are outside both.
ck(
	'and they are the cohort helper\'s, not a second count',
	$snap['participation'],
	WPCPM_Cohort::participation( WPCPM_Roster_Index::rows( $A ), $COHORT )
);

ck(
	'the previous semester is two numbers and a flag',
	$snap['previous'],
	array(
		'key'       => $PREV,
		'signed_up' => 2,
		'graduated' => 1,
		'has_rows'  => true,
	)
);

ck( 'the previous key is the cohort helper\'s', $snap['previous']['key'], WPCPM_Cohort::previous( $COHORT ) );

echo "\n=== Contribution teams: counted across the cohort's own rows ===\n";

// Counted per student and not per row: Iker has two rows and is one person, and the row that
// would have decided which of them to read is the one the join could not pick.
ck(
	'teams are counted, most first and then by name',
	$snap['teams'],
	array(
		array( 'team' => 'Documentation', 'count' => 3 ),
		array( 'team' => 'Training', 'count' => 2 ),
		array( 'team' => 'Polyglots', 'count' => 1 ),
	)
);

// Her row names another institution, so it is not this school's row to read at all.
ck( 'a row linked to another institution contributes no team', has( wp_json_encode( $snap ), 'Design' ), false );
ck( 'and the ambiguous student contributes none either', has( wp_json_encode( $snap['teams'] ), 'Marketing' ), false );

echo "\n=== Student Projects: exactly the students who said yes ===\n";

$ana    = report_id_of( 'ana.fidelitas@example.test' );
$bruno  = report_id_of( 'bruno.kowalski@example.test' );
$carla  = report_id_of( 'carla.nowak@example.test' );
$felipe = report_id_of( 'felipe.silva@example.test' );
$iker   = report_id_of( 'iker.ambiguo@example.test' );

$displays = array_column( $snap['students'], 'display' );
sort( $displays );

ck(
	'four students are listed, and they are the four who released their names',
	$displays,
	array( 'Ana Fidelitas', 'Felipe Silva', 'Iker Ambiguo', 'bruno.example.test' )
);

$ana_entry   = entry_by_id( $snap['students'], $ana );
$bruno_entry = entry_by_id( $snap['students'], $bruno );
$iker_entry  = entry_by_id( $snap['students'], $iker );

ck( 'a student is keyed by a hash of their address and never by the address', $ana_entry['id'], $ana );
ck( 'the name goes in only for "Yes, with my name"', $ana_entry['display'], 'Ana Fidelitas' );
ck( 'and that answer is what marks them as named', $ana_entry['named'], true );

// The label the student's own answer chose. Not the name, not a truncation of the URL: the host,
// which is the thing a reader can type back in.
ck( '"Yes, by my blog address only" prints the blog host', $bruno_entry['display'], 'bruno.example.test' );
ck( 'with the leading www dropped', has( $bruno_entry['display'], 'www.' ), false );
ck( 'and that student is not named', $bruno_entry['named'], false );

ck( 'the personal website is carried whole', $ana_entry['website'], 'https://ana.example.test/' );
ck( 'and for the blog-only student it is still their own URL', $bruno_entry['website'], 'https://www.bruno.example.test/blog' );

$labels = WPCPM_Student_Report_Form::fields( '150h' );

ck(
	'each link is labelled from the report form, so a rename there renames it here',
	links_map( $ana_entry ),
	array(
		$labels['Post Reflection: Building Your Personal Website']['label'] => 'https://ana.example.test/hello-website/',
		$labels['Closing post URL']['label'] => 'https://ana.example.test/closing/',
	)
);

ck(
	'and an empty column is not a link with no address',
	links_map( $bruno_entry ),
	array( $labels['Post Reflection: Halfway Check-In']['label'] => 'https://www.bruno.example.test/blog/halfway/' )
);

ck( 'a student whose Feedback row names another school is not listed here', null, entry_by_id( $snap['students'], report_id_of( 'hanna.weiss@example.test' ) ) );
ck( 'nor is a student who declined', null, entry_by_id( $snap['students'], $carla ) );
ck( 'nor one who never answered', null, entry_by_id( $snap['students'], report_id_of( 'dana.ortiz@example.test' ) ) );
ck( 'nor one whose blog-only answer has no blog', null, entry_by_id( $snap['students'], report_id_of( 'eva.zielinska@example.test' ) ) );

echo "\n=== Several rows for one student are not merged ===\n";

// Two rows, neither spelling her name as the roster does, so no row wins. Both hold a closing
// post and neither is printed: a document that guessed would be printing the wrong student's work.
ck( 'the ambiguous student is still listed, because her consent was not ambiguous', $iker_entry['display'], 'Iker Ambiguo' );
ck( 'but she gets no links at all', links_map( $iker_entry ), array() );
ck( 'and nothing is unioned across her two rows', has( wp_json_encode( $snap ), 'iker.example.test' ), false );
ck( 'one ambiguity is counted', $snap['withheld']['ambiguous'], 1 );

echo "\n=== Recognition: events, grouped, and only for consenting students ===\n";

$events = array();

foreach ( $snap['events'] as $event ) { $events[ (string) $event['url'] ] = (int) $event['count']; }

ck(
	'identical URLs are one line with a count',
	$events,
	array(
		'https://europe.wordcamp.org/2026/'           => 2,
		'https://wordcamp.org/central-america-2026/'  => 1,
	)
);

ck( 'a student who released nothing contributes no event', has( wp_json_encode( $snap ), 'hanna-event' ), false );
ck( 'and neither does a row linked to another school', has( wp_json_encode( $snap ), 'dana-event' ), false );

echo "\n=== Quotes: the student's own words, and only once released ===\n";

$quotes = $snap['quotes'];

ck( 'four quotes were released', count( $quotes ), 4 );

$ana_quote   = entry_by_id( $quotes, $ana );
$bruno_quote = entry_by_id( $quotes, $bruno );
$carla_quote = entry_by_id( $quotes, $carla );

// The same id function, so a quote can be matched back to the student it belongs to without
// the document holding an address to match on.
ck( 'a quote carries the same id as its student', $ana_quote['id'], $ana_entry['id'] );
ck( 'the text is the student\'s own', $ana_quote['text'], 'Contributing to Documentation changed how I read code.' );
ck( '"Yes, with my name" names them', $ana_quote['named'], true );
ck( 'and the name is on the quote', $ana_quote['name'], 'Ana Fidelitas' );

ck( '"Yes, without my name" is still a quote', is_array( $bruno_quote ), true );
ck( 'without the name', $bruno_quote['named'], false );
ck( 'and with nothing in its name field', $bruno_quote['name'], '' );

// The two questions are separate questions. She declined to be listed and released a quote.
ck( 'a student who declined to be listed can still release a quote', is_array( $carla_quote ), true );
ck( 'and she is counted as declined all the same', $snap['withheld']['declined'], 1 );

// The rule the whole design turns on: a school never sees a candidate quote in order to decide
// whether to ask for it.
ck( 'a quote with no permission answer is in the snapshot nowhere', has( wp_json_encode( $snap ), 'A quote nobody has released' ), false );
ck( 'a permission with nothing written is not an empty quote', null, entry_by_id( $quotes, report_id_of( 'eva.zielinska@example.test' ) ) );
ck( 'and a quote released to another school does not arrive here', has( wp_json_encode( $snap ), 'released to a different school' ), false );

/*
 * Five, exactly, and each for a different reason: Dana answered neither question; Eva said
 * "by my blog address" and has no blog; Hanna's only Feedback row belongs to another school,
 * so this report may not read it; and Gustavo and Jonas never filled the form in at all. The contract
 * does not say whether the last three belong here, and they do, on the reading that protects
 * the student: nobody has said yes for them, and a school shown four names out of ten with no
 * number beside them would read the term as having had four students in it.
 */
ck( 'the students who are not listed are counted, and every reason counts', $snap['withheld']['no_answer'], 5 );

echo "\n=== What is never generated ===\n";

ck( 'no project summary reaches the document', has( wp_json_encode( $snap ), 'A project summary the school is not sent' ), false );
ck( 'no mentor is named', has( wp_json_encode( $snap ), 'recMENTOR000000001' ), false );
ck( 'and no feedback rating, not even one', has( wp_json_encode( $snap ), 'Overall experience' ), false );

echo "\n=== The walk: nothing the institution could not print ===\n";

$bad_keys  = array();
$addresses = array();

foreach ( walk_pairs( $snap ) as $pair ) {
	if ( in_array( strtolower( $pair['key'] ), array( 'email', 'email_key', 'status', 'accessibility', 'hours', 'grade' ), true ) ) {
		$bad_keys[] = $pair['key'];
	}

	if ( is_string( $pair['value'] ) && is_email( $pair['value'] ) ) { $addresses[] = $pair['value']; }
}

ck( 'no key in the snapshot is one of the six', $bad_keys, array() );
ck( 'and no string in it is an email address', $addresses, array() );

// Belt and braces on the two the fixture is loudest about: the disclosure is on every index row
// this generation read, and the hours are on every reports row.
ck( 'the accessibility disclosure is nowhere in the snapshot', has( wp_json_encode( $snap ), 'screen reader' ), false );
ck( 'and neither is anybody\'s hours figure', has( wp_json_encode( $snap ), '87.5' ), false );

echo "\n=== The reads: by email, in chunks, never by name ===\n";

$formulas = array_column( $GLOBALS['fetches'], 'formula' );

$names_in_formula = 0;

foreach ( $formulas as $formula ) {
	// The reason the reads are by email at all. A formula built from this name matches nothing,
	// because Airtable's LOWER() folds the diacritic and PHP's strtolower() does not.
	if ( has( $formula, 'Łódzki' ) || has( $formula, 'Uniwersytet' ) || has( $formula, 'Educational institution' ) ) {
		++$names_in_formula;
	}
}

ck( 'no read is filtered by the institution\'s name', $names_in_formula, 0 );
ck( 'every read compares LOWER({Email})', count( array_filter( $formulas, function ( $f ) { return has( $f, 'LOWER({Email})' ); } ) ), count( $formulas ) );
ck( 'and the address is lowercased on this side too', has( $formulas[0], 'ana.fidelitas@example.test' ), true );

$before = count( $GLOBALS['fetches'] );
$again  = WPCPM_Semester_Report::generate( $A, $COHORT );

ck( 'regenerating writes the same post rather than a second one', $again, $post_id );
ck( 'and reads nothing again inside the cache window', count( $GLOBALS['fetches'] ), $before );

// One post per institution and cohort, so a school never has two reports of one semester with
// different narratives in them.
ck( 'the report is found by its institution and cohort', WPCPM_Semester_Report::find( $A, $COHORT )->ID, $post_id );
ck( 'and another institution\'s report is not found under this one', WPCPM_Semester_Report::find( $B, $COHORT ), null );

$GLOBALS['fetches'] = array();

WPCPM_Semester_Report::generate( $C, $COHORT );

$reports_reads  = 0;
$feedback_reads = 0;

foreach ( $GLOBALS['fetches'] as $fetch ) {
	if ( 'tblReports' === $fetch['table'] ) { ++$reports_reads; }
	if ( 'tblFeedback' === $fetch['table'] ) { ++$feedback_reads; }
}

// A hundred and twenty addresses in one formula is a URL no server will accept; fifty is the
// number the rest of this plugin uses and the number the contract fixes.
ck( 'a hundred and twenty students are read in three requests per table', $reports_reads, 3 );
ck( 'the Feedback table the same way', $feedback_reads, 3 );

echo "\n=== A refused read is a refused report ===\n";

$GLOBALS['fail_table'] = 'tblReports';
$GLOBALS['fetches']    = array();

$refused = WPCPM_Semester_Report::generate( $D, $COHORT );

ck( 'a WP_Error from the reports read aborts the generation', is_wp_error( $refused ), true );
ck( 'and its message is passed through unchanged', is_wp_error( $refused ) ? $refused->get_error_message() : '', 'Airtable said 503 and the read was abandoned.' );
ck( 'nothing was written for that institution', WPCPM_Semester_Report::find( $D, $COHORT ), null );

$GLOBALS['fail_table'] = 'tblFeedback';

$refused = WPCPM_Semester_Report::generate( $D, $COHORT );

// A report with Participation and no Student Projects reads as a report about a semester where
// nobody did anything, which is worse than no report at all.
ck( 'a WP_Error from the feedback read aborts it too', is_wp_error( $refused ), true );
ck( 'and still writes nothing', WPCPM_Semester_Report::find( $D, $COHORT ), null );

$GLOBALS['fail_table'] = '';

echo "\n=== The handlers the screen registers ===\n";

WPCPM_Semester_Report_Screen::init();

$actions = array(
	'generate'        => WPCPM_Semester_Report_Screen::ACTION_GENERATE,
	'save'            => WPCPM_Semester_Report_Screen::ACTION_SAVE,
	'refresh consent' => WPCPM_Semester_Report_Screen::ACTION_REFRESH_CONSENT,
	'draft'           => WPCPM_Semester_Report_Screen::ACTION_DRAFT,
	'approve'         => WPCPM_Semester_Report_Screen::ACTION_APPROVE,
	'reopen'          => WPCPM_Semester_Report_Screen::ACTION_REOPEN,
	'restore'         => WPCPM_Semester_Report_Screen::ACTION_RESTORE,
	'ask'             => WPCPM_Semester_Report_Screen::ACTION_ASK,
	'print'           => WPCPM_Semester_Report_Screen::ACTION_PRINT,
);

$unregistered = array();
$public_hooks = array();

foreach ( $actions as $what => $action ) {
	if ( empty( $GLOBALS['hooks'][ 'admin_post_' . $action ] ) ) { $unregistered[] = $what; }
	// Every one of these needs an account. A `nopriv` twin would be a logged-out route into a
	// document naming a school's students.
	if ( ! empty( $GLOBALS['hooks'][ 'admin_post_nopriv_' . $action ] ) ) { $public_hooks[] = $what; }
}

ck( 'every action has a handler', $unregistered, array() );
ck( 'and none of them is reachable logged out', $public_hooks, array() );

ck( 'the cohort argument is a cohort key', WPCPM_Semester_Report_Screen::ARG, 'wpcpm_report' );
ck( 'and the key the fixture uses is one', WPCPM_Cohort::is_key( $COHORT ), true );

/**
 * Call a registered handler and return whatever it echoed before it left.
 *
 * @param string $action The `admin_post_` action name.
 * @return string
 */
function run_handler( $action ) {
	$GLOBALS['redirect'] = '';
	$GLOBALS['died']     = '';

	ob_start();

	try {
		call_user_func( $GLOBALS['hooks'][ 'admin_post_' . $action ][0] );
	} catch ( Left $left ) {
		// The handler ended where it meant to.
	}

	return (string) ob_get_clean();
}

/* ---- the screen, and the forms it draws ---------------------------------- */

/**
 * The editing screen, drawn the way the dashboard draws a card.
 *
 * @param string $record Institutions record ID.
 * @param string $cohort Cohort key for the `?wpcpm_report=` argument.
 * @return string
 */
function screen_html( $record, $cohort ) {
	$_GET  = array( WPCPM_Semester_Report_Screen::ARG => $cohort );
	$_POST = array();

	ob_start();

	WPCPM_Semester_Report_Screen::render(
		$record,
		array(
			'can_manage' => (bool) $GLOBALS['manage'],
			'cohort'     => '',
			'filters'    => array(),
			'read'       => (int) WPCPM_Roster_Index::read( $record )['read'],
		)
	);

	return (string) ob_get_clean();
}

/** The chunk of markup holding the form that posts one action. */
function form_for( $html, $action ) {
	foreach ( explode( '<form', (string) $html ) as $chunk ) {
		if ( has( $chunk, 'value="' . $action . '"' ) ) { return $chunk; }
	}

	return '';
}

/** Turn `sections[overview][text]` into the nested value a browser would post. */
function set_posted( array &$fields, $name, $value ) {
	if ( ! preg_match( '/^([^\[]+)((?:\[[^\]]*\])*)$/', (string) $name, $parts ) ) { return; }

	$path = array( $parts[1] );

	if ( '' !== $parts[2] && preg_match_all( '/\[([^\]]*)\]/', $parts[2], $keys ) ) {
		foreach ( $keys[1] as $key ) { $path[] = $key; }
	}

	$cursor = &$fields;

	foreach ( $path as $step ) {
		if ( '' === $step ) { $cursor[] = array(); $step = array_key_last( $cursor ); }
		if ( ! isset( $cursor[ $step ] ) || ! is_array( $cursor[ $step ] ) ) { $cursor[ $step ] = array(); }
		$cursor = &$cursor[ $step ];
	}

	$cursor = $value;
}

/**
 * Every field a browser would send from one form, and the textareas separately.
 *
 * Scraped rather than named, because this suite is not supposed to know what the hidden inputs
 * are called: a form that stops carrying `post_modified_gmt` should fail the stale-save test,
 * not quietly pass it because the test posted the field the handler wanted.
 *
 * @param string $html   The form's markup.
 * @return array{fields: array, textareas: array}
 */
function form_fields( $html ) {
	$fields    = array();
	$textareas = array();

	if ( preg_match_all( '/<input\b[^>]*>/i', $html, $inputs ) ) {
		foreach ( $inputs[0] as $input ) {
			if ( ! preg_match( '/name=["\']([^"\']+)["\']/i', $input, $name ) ) { continue; }

			$type = preg_match( '/type=["\']([^"\']+)["\']/i', $input, $found ) ? strtolower( $found[1] ) : 'text';

			if ( in_array( $type, array( 'submit', 'button', 'file' ), true ) ) { continue; }
			if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! preg_match( '/\schecked\b/i', $input ) ) { continue; }

			$value = preg_match( '/value=["\']([^"\']*)["\']/i', $input, $found ) ? html_entity_decode( $found[1], ENT_QUOTES ) : '1';

			set_posted( $fields, $name[1], $value );
		}
	}

	if ( preg_match_all( '/<textarea\b[^>]*name=["\']([^"\']+)["\'][^>]*>(.*?)<\/textarea>/is', $html, $areas, PREG_SET_ORDER ) ) {
		foreach ( $areas as $area ) {
			$value = html_entity_decode( $area[2], ENT_QUOTES );
			set_posted( $fields, $area[1], $value );
			$textareas[ $area[1] ] = $value;
		}
	}

	if ( preg_match_all( '/<select\b[^>]*name=["\']([^"\']+)["\'][^>]*>(.*?)<\/select>/is', $html, $selects, PREG_SET_ORDER ) ) {
		foreach ( $selects as $select ) {
			$value = preg_match( '/<option[^>]*value=["\']([^"\']*)["\'][^>]*\sselected/i', $select[2], $found ) ? $found[1] : '';
			set_posted( $fields, $select[1], $value );
		}
	}

	return array( 'fields' => $fields, 'textareas' => $textareas );
}

echo "\n=== The screen a program manager sees ===\n";

// The approval design of 4 September 2026, decision 1, made the editor a manager's; the
// member's own card is asserted in the block Task 5 appended ("What the institution sees").
$GLOBALS['acting'] = $A;
$prev_manage       = $GLOBALS['manage'];
$GLOBALS['manage'] = true;
$GLOBALS['uid']    = 7;

$screen = screen_html( $A, $COHORT );

ck( 'the screen names the institution\'s students who consented', has( $screen, 'Ana Fidelitas' ), true );
ck( 'and the blog host for the student who chose that', has( $screen, 'bruno.example.test' ), true );
ck( 'a student who declined is not on it', has( $screen, 'Carla Nowak' ), false );
ck( 'nor is the disclosure from her index row', has( $screen, 'screen reader' ), false );
ck( 'and no address is printed anywhere on it', has( $screen, 'ana.fidelitas@example.test' ), false );
ck( 'the read date of the roster behind it is shown', has( $screen, wp_date( 'Y-m-d', 1756900000 ) ), true );

$save_form = form_for( $screen, WPCPM_Semester_Report_Screen::ACTION_SAVE );

ck( 'the screen offers one save form', '' !== $save_form, true );

$scraped = form_fields( $save_form );

// Decision 13 puts several equal people on one institution, so the form has to carry the
// version the reader opened or two of them will overwrite each other in silence. Asserted on
// the value and not on the field's name: what the input is called is the screen's business.
ck( 'the form carries the version the reader opened', has( $save_form, esc_attr( get_post( $post_id )->post_modified_gmt ) ), true );
ck( 'and a narrative box to type into', count( $scraped['textareas'] ) > 0, true );

echo "\n=== Two people, one institution, one form ===\n";

// The approval design of 4 September 2026, decision 1, made the editor a manager's; the
// member's own card is asserted in the block Task 5 appended ("What the institution sees").
$area_names = array_keys( $scraped['textareas'] );
$first_area = $area_names[0];

$member_one = $scraped['fields'];
set_posted( $member_one, $first_area, 'The narrative the first member wrote.' );

$_POST = $member_one;
$_GET  = array();

run_handler( WPCPM_Semester_Report_Screen::ACTION_SAVE );

$stored = wp_json_encode( get_post_meta( $post_id, WPCPM_Semester_Report::META_SECTIONS, true ) );

ck( 'the first member\'s narrative is saved', has( $stored, 'The narrative the first member wrote.' ), true );

// The second member opened the page before the first one saved, so their copy of the form
// carries the older version.
$member_two = $scraped['fields'];
set_posted( $member_two, $first_area, 'The narrative the second member wrote.' );

$_POST = $member_two;
$_GET  = array();

run_handler( WPCPM_Semester_Report_Screen::ACTION_SAVE );

$stored = wp_json_encode( get_post_meta( $post_id, WPCPM_Semester_Report::META_SECTIONS, true ) );

ck( 'a stale save does not land', has( $stored, 'The narrative the second member wrote.' ), false );
ck( 'and the first member\'s words are still there', has( $stored, 'The narrative the first member wrote.' ), true );

// Refusing a save and dropping what somebody typed are two different things, and the second one
// is how a person loses an afternoon.
$stash = wp_json_encode( array( $GLOBALS['flash'], $GLOBALS['umeta'], $GLOBALS['opts'], $GLOBALS['transients'] ) );

ck( 'the text they typed is stashed rather than lost', has( $stash, 'The narrative the second member wrote.' ), true );

$screen_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php' );

ck( 'and they are told what happened in words', has( $screen_source, 'saved this report after you opened it' ), true );

echo "\n=== A withdrawal reaches a stored document ===\n";

// The approval design of 4 September 2026, decision 1, made the editor a manager's; the
// member's own card is asserted in the block Task 5 appended ("What the institution sees").
$GLOBALS['flash'] = array();

// The student opens her own Finishing-up form and changes her mind. Nothing regenerates.
$GLOBALS['rows']['tblFeedback'][0]['fields'][ $LIST_FIELD ]  = 'No';
$GLOBALS['rows']['tblFeedback'][0]['fields'][ $QUOTE_FIELD ] = 'No';

// The live read is cached for five minutes, and the student's own save is what clears it. Here
// the cache is emptied by hand, because what is being tested is the re-check and not the save.
$GLOBALS['transients'] = array();

$live = WPCPM_Semester_Report::consent_check( get_post( $post_id ) );

ck( 'the live view no longer holds her', null, entry_by_id( $live['students'], $ana ) );
ck( 'nor her quote', null, entry_by_id( $live['quotes'], $ana ) );
ck( 'and the three students who did not change their minds are still there', count( $live['students'] ), 3 );
ck( 'one withdrawal is counted', $live['dropped'] >= 1, true );

$screen = screen_html( $A, $COHORT );

ck( 'she is gone from the next render, with nothing regenerated', has( $screen, 'Ana Fidelitas' ), false );
ck( 'and so is what she wrote', has( $screen, 'changed how I read code' ), false );
ck( 'the page says a withdrawal happened', has( $screen, 'since this draft was generated' ), true );

// The stored snapshot is the document that was generated. A render must not rewrite it, or the
// next reader cannot tell what the report said when it was made.
$still = WPCPM_Semester_Report::snapshot( get_post( $post_id ) );

ck( 'the stored snapshot is untouched by a render', count( $still['students'] ), 4 );
ck( 'and still holds the quote the render dropped', count( $still['quotes'] ), 4 );

/*
 * Section 5 is "per consenting student" in the same sense section 4 is. Ana and Bruno both put
 * down the same WordCamp, so the generated document counts it twice; once she has withdrawn it
 * is Bruno's alone. A grouped count cannot have one person taken out of it, so the section has
 * to be rebuilt from whoever is left rather than read off the snapshot - and a report that
 * dropped her name from Student Projects while still counting her here would be a withdrawal
 * that had not been honoured.
 */
$drawn = array();

if ( preg_match_all( '#<li class="wpcpm-report-doc__event">(.*?)</li>#s', $screen, $rows_drawn ) ) {
	foreach ( $rows_drawn[1] as $row_html ) {
		if ( ! preg_match( '#<a href="([^"]+)"#', $row_html, $found ) ) { continue; }

		$drawn[ html_entity_decode( $found[1], ENT_QUOTES ) ] = preg_match( '#__count">(\d+)#', $row_html, $count ) ? (int) $count[1] : 1;
	}
}

ck( 'the events section is regrouped from who consents now', $drawn['https://europe.wordcamp.org/2026/'] ?? 0, 1 );
ck( 'and the stored snapshot still says what it said when it was generated', $events['https://europe.wordcamp.org/2026/'], 2 );

echo "\n=== The print document ===\n";

// The approval design of 4 September 2026, decision 1, made the editor a manager's; the
// member's own card is asserted in the block Task 5 appended ("What the institution sees").
$print_link = '';

if ( preg_match_all( '/href=["\']([^"\']+)["\']/i', $screen, $hrefs ) ) {
	foreach ( $hrefs[1] as $href ) {
		if ( has( $href, WPCPM_Semester_Report_Screen::ACTION_PRINT ) ) { $print_link = html_entity_decode( $href, ENT_QUOTES ); break; }
	}
}

ck( 'the screen offers a print link', '' !== $print_link, true );

/** The query string of the print link, as `$_GET`. */
function print_query( $url ) {
	$query = (string) parse_url( $url, PHP_URL_QUERY );
	$out   = array();

	parse_str( $query, $out );

	return $out;
}

$print_get = print_query( $print_link );

$_GET  = $print_get;
$_POST = array();

$document = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );

ck( 'it is a whole document', has( strtolower( $document ), '<html' ), true );
ck( 'with the report\'s own title', has( $document, esc_html( get_post( $post_id )->post_title ) ), true );

// No theme part at all. A print stylesheet fighting a theme's is how a document comes out of a
// printer with a navigation menu across the top of page one.
$parts = array();

foreach ( array( 'header', 'footer', 'wp_head', 'wp_footer', 'template', 'body_class' ) as $part ) {
	if ( has( $document, '<!--WPCPM-THEME-PART:' . $part . '-->' ) ) { $parts[] = $part; }
}

ck( 'and no theme part in it', $parts, array() );

ck( 'the print stylesheet is inlined rather than linked', has( $document, '<style' ), true );
ck( 'no stylesheet is fetched over the network', preg_match( '/<link[^>]+stylesheet/i', $document ), 0 );
ck( 'the page margin is set', has( $document, '@page' ) && has( $document, '18mm' ), true );
ck( 'a quote and a student row are kept off a page boundary', has( $document, 'page-break-inside' ), true );
ck( 'and Student Feedback starts on its own page', has( $document, 'page-break-before' ), true );
ck( 'the document asks the browser to print itself', has( $document, 'report-print.js' ) || has( $document, 'window.print' ), true );

// The rule the reference report follows, and the reason this is a printed document rather than a
// page: a link on paper that says "here" is a link nobody can follow.
$silent = array();

if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $document, $anchors, PREG_SET_ORDER ) ) {
	foreach ( $anchors as $anchor ) {
		$href = html_entity_decode( $anchor[1], ENT_QUOTES );
		$text = html_entity_decode( strip_tags( $anchor[2] ), ENT_QUOTES );

		if ( ! has( $text, $href ) ) { $silent[] = $href; }
	}
}

ck( 'every anchor in the document shows its href as text', $silent, array() );
ck( 'and there is at least one to have checked', count( isset( $anchors ) ? $anchors : array() ) > 0, true );

// The withdrawal reaches the exported document too, and by the same route.
ck( 'the student who withdrew is not in the printed copy either', has( $document, 'Ana Fidelitas' ), false );

// Restores the viewer this suite had before "The screen a program manager sees" switched it:
// the sections from here on test a member's own boundaries again, not a manager's editor.
$GLOBALS['manage'] = $prev_manage;

echo "\n=== Another institution's report is not a report ===\n";

// The approval design of 4 September 2026, decision 1, made the editor a manager's; the
// member's own card is asserted in the block Task 5 appended ("What the institution sees").

// A second institution with a report of its own, so there is a real post ID to point at.
$GLOBALS['acting'] = $B;
$b_post            = WPCPM_Semester_Report::generate( $B, $COHORT );
$GLOBALS['acting'] = $A;

ck( 'the other institution has a report', is_int( $b_post ) && $b_post > 0, true );

/** The print link with whichever argument carries the post ID swapped for another. */
function print_query_for( array $query, $was, $now ) {
	foreach ( $query as $key => $value ) {
		if ( (string) $value === (string) $was ) { $query[ $key ] = (string) $now; }
	}

	return $query;
}

$_GET                  = print_query_for( $print_get, $post_id, $b_post );
$GLOBALS['decisions']  = array();
$stranger              = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );

$asked_about = array();

foreach ( $GLOBALS['decisions'] as $decision ) {
	foreach ( $decision['ids'] as $id ) { $asked_about[ $id ] = true; }
}

// The subject's institution comes off the post's own meta and never off the request, so a
// member of A posting B's post ID is decided against B, is not a member of B, and is refused.
ck( 'the decision was made about the institution the post names', isset( $asked_about[ $B ] ), true );
ck( 'and never about the one the reader belongs to', isset( $asked_about[ $A ] ), false );

$_GET  = print_query_for( $print_get, $post_id, 999999 );
$ghost = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );

ck( 'another institution\'s report is not printed', has( $stranger, 'Universidad Beta' ), false );
ck( 'and no student of theirs is named', has( $stranger, 'Beta Student' ), false );

// The whole of the rule. A refusal that only appears for a post that exists is a way to find out
// which reports exist, one ID at a time.
ck( 'a post belonging to another school reads exactly like a post that does not exist', $stranger, $ghost );

echo "\n=== A snapshot from a version this code does not know ===\n";

update_post_meta( $b_post, WPCPM_Semester_Report::META_DATA, array( 'v' => 99, 'students' => array( array( 'display' => 'From the future' ) ) ) );

// Better an empty report than a report drawn from a shape this code is guessing at.
ck( 'is read as nothing at all', WPCPM_Semester_Report::snapshot( get_post( $b_post ) ), array() );

echo "\n=== A manager may not answer for a student ===\n";

$forms = WPCPM_Student_Feedback::forms();
$f3    = isset( $forms['f3']['fields'] ) ? $forms['f3']['fields'] : array();

ck( 'the listing question is on the Finishing up form', isset( $f3[ $LIST_FIELD ] ), true );
ck( 'and the quoting question with it', isset( $f3[ $QUOTE_FIELD ] ), true );
ck( 'both are in the permissions box', isset( $f3[ $LIST_FIELD ]['group'] ) ? $f3[ $LIST_FIELD ]['group'] : '', 'permissions' );
ck( 'and so is the quoting one', isset( $f3[ $QUOTE_FIELD ]['group'] ) ? $f3[ $QUOTE_FIELD ]['group'] : '', 'permissions' );

// Pinned byte for byte: `update_records()` sends no typecast, so a choice spelled any other way
// is a 422 for the whole PATCH and the student's answer never lands.
ck(
	'the listing choices are the base\'s, byte for byte',
	isset( $f3[ $LIST_FIELD ]['choices'] ) ? $f3[ $LIST_FIELD ]['choices'] : array(),
	array( 'Yes, with my name', 'Yes, by my blog address only', 'No' )
);
ck(
	'and so are the quoting choices',
	isset( $f3[ $QUOTE_FIELD ]['choices'] ) ? $f3[ $QUOTE_FIELD ]['choices'] : array(),
	array( 'Yes, with my name', 'Yes, without my name', 'No' )
);

$student_id = 11;

$GLOBALS['umeta'][ $student_id ] = array(
	WPCPM_Students_Sync::META_PROGRAM => array(
		'name'    => 'Ana Fidelitas',
		'email'   => 'ana.fidelitas@example.test',
		'program' => 'In Sensei',
	),
	WPCPM_Student_Feedback::META_RECORD => 'recFDBA0000000001',
);

/**
 * Post the Finishing-up permissions as somebody.
 *
 * @param int $actor The signed-in account.
 * @param bool $manager Whether they hold CAP_MANAGE.
 * @return void
 */
function post_permissions( $actor, $manager ) {
	global $LIST_FIELD, $QUOTE_FIELD, $student_id;

	$GLOBALS['uid']     = (int) $actor;
	$GLOBALS['manage']  = (bool) $manager;
	$GLOBALS['updated'] = array();

	$_POST = array(
		'student'  => $student_id,
		'form'     => 'f3',
		'feedback' => array(
			WPCPM_Student_Feedback::key( $LIST_FIELD )  => 'Yes, with my name',
			WPCPM_Student_Feedback::key( $QUOTE_FIELD ) => 'Yes, with my name',
		),
	);

	try {
		WPCPM_Student_Feedback::handle_save();
	} catch ( Left $left ) {
		// The handler redirected, which is how it ends.
	}
}

// A program manager, acting with every capability the site has, on a student's behalf.
delete_user_meta( $student_id, 'wpcpm_report_permissions' );
post_permissions( 99, true );

$written = wp_json_encode( $GLOBALS['updated'] );

ck( 'a manager\'s answer never reaches Airtable', has( $written, 'F3 - Report:' ), false );
ck( 'and nothing is stamped on the student', get_user_meta( $student_id, 'wpcpm_report_permissions', true ), '' );

// The student themselves, which is the only path that writes either cell.
post_permissions( $student_id, false );

$written = wp_json_encode( $GLOBALS['updated'] );

ck( 'the student\'s own answer is written', has( $written, $LIST_FIELD ), true );
ck( 'both of them', has( $written, $QUOTE_FIELD ), true );

$stamp = get_user_meta( $student_id, 'wpcpm_report_permissions', true );

ck( 'and their own save is stamped', is_array( $stamp ) && ! empty( $stamp ), true );

$GLOBALS['uid']    = 7;
$GLOBALS['manage'] = false;

echo "\n=== Asking the students who have not answered ===\n";

// The approval design of 4 September 2026, decision 1, made the editor a manager's; the
// member's own card is asserted in the block Task 5 appended ("What the institution sees").

/*
 * Open question 2, decided: **program managers only**. The institution is the party that
 * gains from a yes, so it is the wrong party to send the request; the addresses belong to the
 * program rather than to the school. Two halves are asserted here and both matter:
 *
 * - the institution's own card never draws the control at all; for a draft, it says only
 *   that the report is being prepared by the program team. The reassurance that a program
 *   manager can send the request is drawn on the manager's editor, so nobody on the
 *   institution's side presses a button that will refuse them;
 * - a member posting the action by hand sends nothing **and stamps nothing**. The stamp is
 *   the thirty-day clock: a refusal that still stamped would spend a student's one message a
 *   month on a request that never left.
 */
$GLOBALS['acting'] = $A;
$GLOBALS['mail']   = array();

$member_screen = screen_html( $A, $COHORT );

ck( 'the institution is not offered the ask control', form_for( $member_screen, WPCPM_Semester_Report_Screen::ACTION_ASK ), '' );

// The reassurance that a program manager can send the request is drawn on the manager's own
// editor now (design of 4 September 2026, decision 1): a member reading a draft's cohort never
// reaches the report at all, so the sentence has nowhere left to stand on their card.
$prev_manage       = $GLOBALS['manage'];
$GLOBALS['manage'] = true;
$manager_screen    = screen_html( $A, $COHORT );
$GLOBALS['manage'] = $prev_manage;

ck( 'and is told a program manager can send it', has( strtolower( $manager_screen ), 'program manager' ), true );

// The form as the manager screen draws it, scraped rather than named, so this suite still does
// not know what the handler's inputs are called.
ob_start();
WPCPM_Semester_Report_Screen::render_ask_form( $post_id );
$ask_form = (string) ob_get_clean();

ck( 'the manager screen has a form for it', has( $ask_form, 'value="' . WPCPM_Semester_Report_Screen::ACTION_ASK . '"' ), true );

// The stub above stands in for the roster's switcher argument, so it has to be the real one.
ck(
	'and the switcher argument this suite stubs is the roster\'s own',
	has(
		(string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php' ),
		"const ARG_VIEW = '" . WPCPM_Institution_Roster::ARG_VIEW . "';"
	),
	true
);

$dana = 14;

delete_user_meta( $dana, WPCPM_Semester_Report_Screen::META_ASKED );

$_POST = form_fields( $ask_form )['fields'];
$_GET  = array();

run_handler( WPCPM_Semester_Report_Screen::ACTION_ASK );

ck( 'a member pressing it sends nothing', $GLOBALS['mail'], array() );
ck( 'and stamps nobody, so their one message a month is still theirs', get_user_meta( $dana, WPCPM_Semester_Report_Screen::META_ASKED, true ), '' );

// The same press, by somebody holding the management capability.
$GLOBALS['manage'] = true;
$GLOBALS['mail']   = array();

$_POST = form_fields( $ask_form )['fields'];
$_GET  = array();

run_handler( WPCPM_Semester_Report_Screen::ACTION_ASK );

$asked = count( $GLOBALS['mail'] );

ck( 'a manager pressing it does send', $asked > 0, true );
ck( 'and the message is the report-consent one', isset( $GLOBALS['mail'][0]['context'] ) ? $GLOBALS['mail'][0]['context'] : '', 'report-consent' );

/*
 * Who it went to. The spec's set is "a candidate answer and no permission answer", so it is
 * the one student who wrote something nobody has released: Dana Ortiz, whose Feedback row
 * carries a quote and neither permission. Not Iker, who answered the listing question; not
 * Eva, who answered both; and not the students who wrote nothing at all, who have nothing to
 * release and would be receiving mail about a blank.
 */
$written = array();

foreach ( $GLOBALS['mail'] as $sent ) { $written[] = (int) $sent['to']; }

ck( 'to the student with a quote nobody has released, and to nobody else', $written, array( $dana ) );
ck( 'and they are stamped with the date', get_user_meta( $dana, WPCPM_Semester_Report_Screen::META_ASKED, true ) > 0, true );

$GLOBALS['mail'] = array();

$_POST = form_fields( $ask_form )['fields'];
$_GET  = array();

run_handler( WPCPM_Semester_Report_Screen::ACTION_ASK );

// Thirty days. A manager pressing a button twice must not send a student the same request twice.
ck( 'and nobody is asked twice in the same month', $GLOBALS['mail'], array() );

$GLOBALS['manage'] = false;

echo "\n=== What uninstall has to take with it ===\n";

/*
 * The generate lock and the ask queue are options named after a report, so there is no name
 * `uninstall.php` could carry a list of and the sweep goes by prefix. Two decoys stand beside
 * them: an option belonging to the data half, which is not this class's to delete, and one
 * spelled with a hyphen where the prefix has an underscore, which an unescaped LIKE would take
 * with it because `_` is a one-character wildcard in SQL.
 */
$GLOBALS['opts'][ WPCPM_Semester_Report_Screen::LOCK_PREFIX . 'recINSTA000000001_2026-H1' ] = time();
$GLOBALS['opts'][ WPCPM_Semester_Report_Screen::QUEUE_PREFIX . '4242' ]                     = array( 'actor' => 1, 'ids' => array( 5 ) );
$GLOBALS['opts']['wpcpm_report_epoch']                                                      = array();
$GLOBALS['opts']['wpcpm-report-ask-4242']                                                   = 'a neighbour';

$swept = WPCPM_Semester_Report_Screen::delete_all();

ck( 'the sweep takes both of its own', $swept, 2 );
ck( 'the lock is gone', isset( $GLOBALS['opts'][ WPCPM_Semester_Report_Screen::LOCK_PREFIX . 'recINSTA000000001_2026-H1' ] ), false );
ck( 'and the queue with it', isset( $GLOBALS['opts'][ WPCPM_Semester_Report_Screen::QUEUE_PREFIX . '4242' ] ), false );
ck( 'the data half\'s own option is left for the data half', isset( $GLOBALS['opts']['wpcpm_report_epoch'] ), true );
ck( 'and the underscore is escaped, so a neighbour survives', isset( $GLOBALS['opts']['wpcpm-report-ask-4242'] ), true );


echo "\n=== The findings the reviewers proved, held in place ===\n";

// The approval design of 4 September 2026, decision 1, made the editor a manager's; the
// member's own card is asserted in the block Task 5 appended ("What the institution sees").

/*
 * Fourteen mutation-proved findings came out of Phase 6's review and the suite caught none of them.
 * Every assertion in this section fails against the code as it was before the fix it names. They
 * run against an institution of their own, seeded here, so nothing above can move them and they
 * move nothing above.
 */
$E = 'recINSTE000000005';

$GLOBALS['index'][ $E ] = array(
	'read' => 1756900005,
	'rows' => array(
		'recSTUE0000000001' => row( 'recSTUE0000000001', 'Ewa Named', 'In Sensei', '2026-02-01', array( 'institution' => $E, 'user_id' => 51 ) ),
		// The website box holding an email address, which the student form stores as a URL.
		'recSTUE0000000002' => row( 'recSTUE0000000002', 'Filip Blog', 'In Sensei', '2026-02-02', array( 'institution' => $E, 'user_id' => 52, 'website' => 'https://filip.blog@example.test' ) ),
		'recSTUE0000000003' => row( 'recSTUE0000000003', 'Greta Script', 'In Sensei', '2026-02-03', array( 'institution' => $E, 'user_id' => 53, 'website' => 'javascript:alert(1)' ) ),
		// No Students Reports row at all: nothing to attach, and nothing unmatched.
		'recSTUE0000000004' => row( 'recSTUE0000000004', 'Hugo Norow', 'In Sensei', '2026-02-04', array( 'institution' => $E, 'user_id' => 54 ) ),
		// Two Students Reports rows, neither name matching: the one case that is ambiguous.
		'recSTUE0000000005' => row( 'recSTUE0000000005', 'Ivo Tworows', 'In Sensei', '2026-02-05', array( 'institution' => $E, 'user_id' => 55 ) ),
		// A lead who never enrolled, with every consent given: participation() does not count her.
		'recSTUE0000000006' => row( 'recSTUE0000000006', 'Lena Lead', 'Interested', '2026-02-06', array( 'institution' => $E, 'user_id' => 56 ) ),
		// Wrote into the column whose label mentions quoting, which is empty on every real row.
		'recSTUE0000000007' => row( 'recSTUE0000000007', 'Mila Old', 'In Sensei', '2026-02-07', array( 'institution' => $E, 'user_id' => 57 ) ),
		// The semester before holds one spam row and nothing else.
		'recSTUE0000000008' => row( 'recSTUE0000000008', 'Spam Prev', 'SPAM', '2025-09-01', array( 'institution' => $E ) ),
	),
);

$GLOBALS['rows']['tblReports'][] = report_row( 'recREPE0000000001', 'ewa.named@example.test', 'Ewa Named', array( $E ), array( 'WP event participation URL' => 'https://Europe.WordCamp.org/2026/' ) );
$GLOBALS['rows']['tblReports'][] = report_row( 'recREPE0000000002', 'filip.blog@example.test', 'Filip Blog', array( $E ) );
$GLOBALS['rows']['tblReports'][] = report_row( 'recREPE0000000003', 'greta.script@example.test', 'Greta Script', array( $E ), array( 'WP event participation URL' => 'https://europe.wordcamp.org/2026/' ) );
$GLOBALS['rows']['tblReports'][] = report_row( 'recREPE0000000005', 'ivo.tworows@example.test', 'Someone Else', array( $E ) );
$GLOBALS['rows']['tblReports'][] = report_row( 'recREPE0000000006', 'ivo.tworows@example.test', 'Another Person', array( $E ) );
$GLOBALS['rows']['tblReports'][] = report_row( 'recREPE0000000007', 'lena.lead@example.test', 'Lena Lead', array( $E ) );

$GLOBALS['rows']['tblFeedback'][] = feedback_row( 'recFDBE0000000001', 'ewa.named@example.test', 'Ewa Named', array( $E ), 'Yes, with my name', 'Yes, with my name', 'Ewa on what she built.' );
$GLOBALS['rows']['tblFeedback'][] = feedback_row( 'recFDBE0000000002', 'filip.blog@example.test', 'Filip Blog', array( $E ), 'Yes, by my blog address only', 'No', '' );
$GLOBALS['rows']['tblFeedback'][] = feedback_row( 'recFDBE0000000003', 'greta.script@example.test', 'Greta Script', array( $E ), 'Yes, with my name', 'No', '' );
$GLOBALS['rows']['tblFeedback'][] = feedback_row( 'recFDBE0000000004', 'hugo.norow@example.test', 'Hugo Norow', array( $E ), 'Yes, with my name', 'No', '' );
$GLOBALS['rows']['tblFeedback'][] = feedback_row( 'recFDBE0000000005', 'ivo.tworows@example.test', 'Ivo Tworows', array( $E ), 'Yes, with my name', 'No', '' );
$GLOBALS['rows']['tblFeedback'][] = feedback_row( 'recFDBE0000000006', 'lena.lead@example.test', 'Lena Lead', array( $E ), 'Yes, with my name', 'Yes, with my name', 'A lead with a quote.' );
$GLOBALS['rows']['tblFeedback'][] = array(
	'id'     => 'recFDBE0000000007',
	'fields' => array(
		'Email'       => 'mila.old@example.test',
		'Name'        => 'Mila Old',
		'Institution' => array( $E ),
		$LIST_FIELD   => 'Yes, with my name',
		$QUOTE_FIELD  => 'Yes, with my name',
		'F3 - May we share a quote about your experience publicly? If so, please share your thoughts below' => 'Words in the column nobody fills in.',
	),
);

$GLOBALS['acting']     = $E;
$GLOBALS['manage']     = false;
$GLOBALS['uid']        = 7;
$GLOBALS['transients'] = array();

$e_post = WPCPM_Semester_Report::generate( $E, $COHORT );
ck( 'the fixture institution generates', is_int( $e_post ) && $e_post > 0, true );
$e_snap = WPCPM_Semester_Report::snapshot( get_post( $e_post ) );
$e_json = wp_json_encode( $e_snap );

// The quote column (the contract had the wrong one).
$data_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report.php' );
ck( 'the quotes come from the "proud of" answer, which the reference report quoted', WPCPM_Semester_Report::FIELD_QUOTE, 'F3 - One example of a contribution you are proud of' );
ck( 'and the column whose label mentions quoting, empty on all 834 rows, is never read', has( $data_source, 'May we share a quote' ), false );
ck( 'so words written only there are not a quote, whatever the permission says', has( $e_json, 'Words in the column nobody fills in' ), false );
ck( 'while Ewa\'s answer in the right column is', has( $e_json, 'Ewa on what she built.' ), true );

// A URL with a userinfo part carries an address the no-PII walk cannot see.
ck( 'an email address typed into the website box does not reach the snapshot as a URL', has( $e_json, 'filip.blog@' ), false );
ck( 'so a student who chose "blog address only" and has no usable address is withheld', null, entry_by_id( $e_snap['students'], report_id_of( 'filip.blog@example.test' ) ) );
$greta = entry_by_id( $e_snap['students'], report_id_of( 'greta.script@example.test' ) );
ck( 'a javascript: website is dropped', is_array( $greta ) ? $greta['website'] : 'missing', '' );
ck( 'and the student is still listed by name, as she asked', is_array( $greta ) ? $greta['display'] : '', 'Greta Script' );

// Only several rows is ambiguous.
ck( 'a student with no Students Reports row is listed', is_array( entry_by_id( $e_snap['students'], report_id_of( 'hugo.norow@example.test' ) ) ), true );
ck( 'and is not counted as unmatched: one student, two rows, is the only ambiguity here', $e_snap['withheld']['ambiguous'], 1 );
ck( 'the withheld numbers add up to the students not listed, plus the one listed without links',
	$e_snap['withheld']['no_answer'] + $e_snap['withheld']['declined'], 1 );

// The report and participation() count the same people.
ck( 'a lead who never enrolled is not counted: six enrolled, one lead, one spam row last year', $e_snap['participation']['signed_up'], 6 );
ck( 'and is not listed, however many consents she gave', has( $e_json, 'Lena Lead' ), false );
ck( 'nor quoted', has( $e_json, 'A lead with a quote' ), false );
ck( 'a previous semester holding only a spam row is a semester that did not happen', $e_snap['previous']['has_rows'], false );

// Identical events are grouped whatever the case of their host.
$grouped = WPCPM_Semester_Report::group_events( $e_snap['students'] );
ck( 'two students at one WordCamp are one event with a count of two, however the host is capitalised', $grouped, array( array( 'url' => 'https://Europe.WordCamp.org/2026/', 'count' => 2 ) ) );
ck( 'the first spelling seen is the one printed', $grouped[0]['url'], 'https://Europe.WordCamp.org/2026/' );
ck( 'a trailing slash still tells two URLs apart, as the spec\'s "identical" says',
	count( WPCPM_Semester_Report::group_events( array( array( 'events' => array( 'https://x.test/a' ) ), array( 'events' => array( 'https://x.test/a/' ) ) ) ) ), 2 );

// The sentence about unmatched records is its own, and the old clause is gone.
$screen_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php' );
ck( 'the unmatched count is a sentence of its own', has( $screen_source, 'could not be matched to a single row, so no project links are shown' ), true );
ck( 'and no longer an item in "not listed above"', has( $screen_source, 'could not be matched to a single record' ), false );
// Ivo's records, and the sentence about them, are drawn in the editor; a draft is a manager's
// to read in full (design of 4 September 2026, decision 1).
$prev_manage       = $GLOBALS['manage'];
$GLOBALS['manage'] = true;
$e_screen          = screen_html( $E, $COHORT );
$GLOBALS['manage'] = $prev_manage;
ck( 'on the page, Ivo is named', has( $e_screen, 'Ivo Tworows' ), true );
ck( 'and the sentence about his records does not say he is not listed', has( $e_screen, 'Students not listed above: 1 could not' ), false );

// A save that could not see the quotes must not un-tick them.
$ewa_quote = report_id_of( 'ewa.named@example.test' );
WPCPM_Semester_Report::save( get_post( $e_post ), WPCPM_Semester_Report::narratives( get_post( $e_post ) ), array(
	$ewa_quote => array( 'include' => true, 'translation' => 'Ewa o tym, co zbudowala.', 'show_name' => true ),
) );

// The form the school would see on a bad read is a manager's editor too (design of 4
// September 2026, decision 1): drawn with $GLOBALS['manage'] false, the card has no <form>
// at all and the three checks below would pass on zero fields scraped from nothing.
$prev = $GLOBALS['manage'];
$GLOBALS['manage'] = true;
$GLOBALS['fail_table'] = 'tblFeedback';
$GLOBALS['transients'] = array();
$blind = form_fields( form_for( screen_html( $E, $COHORT ), WPCPM_Semester_Report_Screen::ACTION_SAVE ) );
$GLOBALS['fail_table'] = '';

ck( 'the blind form is a form, not an empty string', count( $blind['fields'] ) > 0, true );
ck( 'a form drawn while the answers could not be read carries no quote controls', has( wp_json_encode( $blind['fields'] ), 'quote_include_' ), false );

$_POST = $blind['fields'];
$_GET  = array();
run_handler( WPCPM_Semester_Report_Screen::ACTION_SAVE );
$GLOBALS['manage'] = $prev;
$kept = WPCPM_Semester_Report::choices( get_post( $e_post ) );

ck( 'saving it keeps the quote included', ! empty( $kept[ $ewa_quote ]['include'] ), true );
ck( 'and keeps the translation the school typed', isset( $kept[ $ewa_quote ]['translation'] ) ? $kept[ $ewa_quote ]['translation'] : '', 'Ewa o tym, co zbudowala.' );

// The same form drawn with the quotes in view, and the box unticked on purpose. Drawing the
// quote picker and saving against it are both a manager's editor now (design of 4 September
// 2026, decision 1).
$GLOBALS['transients'] = array();
$prev_manage           = $GLOBALS['manage'];
$GLOBALS['manage']     = true;
$seen_form = form_for( screen_html( $E, $COHORT ), WPCPM_Semester_Report_Screen::ACTION_SAVE );
$seen      = form_fields( $seen_form );
ck( 'a form that drew the quote says so', has( $seen_form, 'quote_offered_' ), true );
unset( $seen['fields'][ 'quote_include_' . sanitize_key( $ewa_quote ) ] );
$_POST = $seen['fields'];
$_GET  = array();
run_handler( WPCPM_Semester_Report_Screen::ACTION_SAVE );
$GLOBALS['manage'] = $prev_manage;
$kept = WPCPM_Semester_Report::choices( get_post( $e_post ) );
ck( 'and an unticked box on a form that drew the quote does exclude it', empty( $kept[ $ewa_quote ]['include'] ), true );

// One answer for "not yours" and "not here", on every POST handler, not only print.
function flash_status_after( $action, array $post ) {
	$GLOBALS['flash'] = array();
	$_POST            = $post;
	$_GET             = array();
	run_handler( $action );
	$got = isset( $GLOBALS['flash'][ WPCPM_Semester_Report_Screen::FLASH ] ) ? $GLOBALS['flash'][ WPCPM_Semester_Report_Screen::FLASH ] : '';
	return is_array( $got ) && isset( $got['status'] ) ? (string) $got['status'] : wp_json_encode( $got );
}

$plain_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Not a report' ) );
$GLOBALS['acting'] = $E;
$GLOBALS['manage'] = false;

foreach ( array(
	'save'    => WPCPM_Semester_Report_Screen::ACTION_SAVE,
	'approve' => WPCPM_Semester_Report_Screen::ACTION_APPROVE,
	'reopen'  => WPCPM_Semester_Report_Screen::ACTION_REOPEN,
	'refresh' => WPCPM_Semester_Report_Screen::ACTION_REFRESH_CONSENT,
	'ask'     => WPCPM_Semester_Report_Screen::ACTION_ASK,
) as $what => $action ) {
	$theirs = flash_status_after( $action, array( 'report' => $post_id ) );
	$ghost  = flash_status_after( $action, array( 'report' => 999999 ) );
	$other  = flash_status_after( $action, array( 'report' => $plain_post ) );
	ck( $what . ': another school\'s report, a ghost and a post of another type read the same', $theirs === $ghost && $ghost === $other, true );
	ck( $what . ': and none of them is a refusal that names a report', in_array( $theirs, array( 'refused', 'ask-refused' ), true ), true );
}

// The restore handler, executed: the parent is the revision's, never the form's.
$rev_own = wp_insert_post( array( 'post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $e_post ) );
$rev_a   = wp_insert_post( array( 'post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $post_id ) );
$GLOBALS['restored'] = array();

// Restoring a revision is an edit, and item 6's policy row gives that ground to a manager
// only: a member is refused this school's own report exactly as it is refused another's.
ck( 'restoring a revision, even of this school\'s own report, is refused to a member', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_RESTORE, array( 'revision' => $rev_own ) ), 'refused' );
ck( 'and nothing was restored', $GLOBALS['restored'], array() );
$GLOBALS['restored'] = array();
ck( 'a revision of another school\'s report is refused', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_RESTORE, array( 'revision' => $rev_a ) ), 'refused' );
ck( 'posting this school\'s report ID beside it changes nothing: the parent comes off the revision', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_RESTORE, array( 'revision' => $rev_a, 'report' => $e_post ) ), 'refused' );
ck( 'a revision that does not exist reads the same as one that is not yours', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_RESTORE, array( 'revision' => 999999 ) ), 'refused' );
ck( 'and nothing was restored by any of the three', $GLOBALS['restored'], array() );

// A read that was never made is not a confirmed consent.
$e_rows = $GLOBALS['index'][ $E ]['rows'];
$GLOBALS['index'][ $E ]['rows'] = array();
$GLOBALS['transients']          = array();
$unread = WPCPM_Semester_Report::consent_check( get_post( $e_post ) );
ck( 'an empty index under a snapshot that names people is an error, not a withdrawal', is_wp_error( $unread ) ? $unread->get_error_code() : 'no error', 'wpcpm_report_no_index' );
// The failed-read message is on the document itself, which only a manager's editor draws for
// a draft (design of 4 September 2026, decision 1); the blanking is unconditional either way.
$prev_manage       = $GLOBALS['manage'];
$GLOBALS['manage'] = true;
$e_screen          = screen_html( $E, $COHORT );
$GLOBALS['manage'] = $prev_manage;
ck( 'and the page says the answers could not be read', has( $e_screen, 'could not be read' ), true );
ck( 'rather than that anybody withdrew', has( $e_screen, 'since this draft was generated' ), false );
ck( 'and prints no student', has( $e_screen, 'Ewa Named' ), false );
$GLOBALS['index'][ $E ]['rows'] = $e_rows;

// Ctrl-P on the dashboard hides the withdrawal line the print handler leaves off the paper.
$print_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/report-print.css' );
$media     = strstr( $print_css, '@media print' );
ck( 'the print sheet has a print block', false !== $media, true );
ck( 'and it hides the withdrawal line', preg_match( '/\.wpcpm-report-doc__withdrawn\s*\{[^}]*display:\s*none/s', preg_replace( '#/\*.*?\*/#s', '', (string) $media ) ) === 1
	|| preg_match( '/\.wpcpm-report-doc__withdrawn[^{]*\{[^}]*display:\s*none/s', preg_replace( '#/\*.*?\*/#s', '', (string) $media ) ) === 1, true );

// Uninstall takes a trashed report with it.
$trashed = wp_insert_post( array( 'post_type' => WPCPM_Semester_Report::POST_TYPE, 'post_status' => 'trash', 'post_title' => 'Binned report' ) );
ck( 'a query for "any" status does not see a trashed post here either', in_array( $trashed, get_posts( array( 'post_type' => WPCPM_Semester_Report::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids' ) ), true ), false );
WPCPM_Semester_Report::delete_all();
ck( 'yet uninstall removes it, with the students\' names and words in it', get_post( $trashed ), null );
ck( 'along with the live ones', get_post( $e_post ), null );

echo "\n=== Airtable's own words reach a manager, not a member ===\n";

// The edit action became a manager's ground in item 6, so a member cannot reach the read at
// all: ACTION_GENERATE refuses before Airtable is asked anything, and the only viewer who can
// see Airtable's own words in the client's error is the one who can act on them.
$GLOBALS['acting']     = $E;
$GLOBALS['manage']     = false;
$GLOBALS['uid']        = 7;
$GLOBALS['transients'] = array();
$GLOBALS['fail_table'] = 'tblReports';
$GLOBALS['flash']      = array();
$_POST = array( 'cohort' => $COHORT, 'institution' => $E, 'report' => 0 );
$_GET  = array();
run_handler( WPCPM_Semester_Report_Screen::ACTION_GENERATE );
$member_flash = wp_json_encode( $GLOBALS['flash'] );
ck( 'a member is refused before any read is attempted', has( $member_flash, 'refused' ), true );
ck( 'carrying nothing of the read that never happened', has( $member_flash, 'generate-failed' ) || has( $member_flash, 'HTTP' ) || has( $member_flash, 'token' ), false );

$GLOBALS['manage'] = true;
$GLOBALS['flash']  = array();
run_handler( WPCPM_Semester_Report_Screen::ACTION_GENERATE );
$manager_flash = wp_json_encode( $GLOBALS['flash'] );
ck( 'a manager is told the read failed', has( $manager_flash, 'generate-failed' ), true );
ck( 'and is shown the client\'s message, because a manager can act on it', has( $manager_flash, 'the read was abandoned' ), true );
$GLOBALS['fail_table'] = '';
$GLOBALS['manage']     = false;

echo "\n=== Two states: draft and approved ===\n";

// Everything the earlier blocks generated was removed by delete_all() above, so this block
// stands its own reports up. A manager, acting for nobody in particular.
$GLOBALS['manage']     = true;
$GLOBALS['acting']     = '';
$GLOBALS['uid']        = 3;
$GLOBALS['transients'] = array();
$GLOBALS['fail_table'] = '';
$GLOBALS['flash']      = array();
$GLOBALS['mail']       = array();

$s_post_id = WPCPM_Semester_Report::generate( $A, $COHORT );
ck( 'a fresh report is a draft', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'draft' );
ck( 'and its origin is a manager, the default', WPCPM_Semester_Report::origin_of( get_post( $s_post_id ) ), 'manager' );
ck( 'the vocabulary is draft and approved', array( WPCPM_Semester_Report::STATE_DRAFT, WPCPM_Semester_Report::STATE_APPROVED ), array( 'draft', 'approved' ) );
ck( 'final is not a state any more', WPCPM_Semester_Report::set_state( get_post( $s_post_id ), 'final' ), false );
ck( 'nothing on the class still names it', defined( 'WPCPM_Semester_Report::STATE_FINAL' ), false );

ck( 'approve writes the state and the stamp', WPCPM_Semester_Report::approve( get_post( $s_post_id ), 3 ), true );
$stamp = WPCPM_Semester_Report::approved_at( get_post( $s_post_id ) );
ck( 'the stamp names the manager', isset( $stamp['by'] ) ? $stamp['by'] : null, 3 );
ck( 'and a time', ! empty( $stamp['at'] ), true );
ck( 'the state reads approved', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'approved' );

$again = WPCPM_Semester_Report::generate( $A, $COHORT );
ck( 'generating an approved report is refused', is_wp_error( $again ) ? $again->get_error_code() : 'no error', 'wpcpm_report_approved' );

ck( 'reopen makes it a draft', WPCPM_Semester_Report::reopen( get_post( $s_post_id ) ), true );
ck( 'and the state says so', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'draft' );
ck( 'and the stamp is gone', WPCPM_Semester_Report::approved_at( get_post( $s_post_id ) ), array() );

$auto_id = WPCPM_Semester_Report::generate( $B, $COHORT, WPCPM_Semester_Report::ORIGIN_AUTO );
ck( 'the job\'s origin is recorded', WPCPM_Semester_Report::origin_of( get_post( $auto_id ) ), 'auto' );
WPCPM_Semester_Report::generate( $B, $COHORT, WPCPM_Semester_Report::ORIGIN_MANAGER );
ck( 'and a regeneration by hand does not rewrite it: the origin is how the report came to exist', WPCPM_Semester_Report::origin_of( get_post( $auto_id ) ), 'auto' );

echo "\n=== The upgrade: final becomes approved, once ===\n";

$legacy = wp_insert_post( array( 'post_type' => WPCPM_Semester_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Legacy final' ) );
update_post_meta( $legacy, WPCPM_Semester_Report::META_INSTITUTION, $C );
update_post_meta( $legacy, WPCPM_Semester_Report::META_COHORT, '2025-H2' );
update_post_meta( $legacy, WPCPM_Semester_Report::META_STATE, 'final' );
unset( $GLOBALS['opts'][ WPCPM_Semester_Report::OPT_STATE_VERSION ], $GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] );

WPCPM_Semester_Report::maybe_upgrade();
ck( 'a final report reads approved after the upgrade', WPCPM_Semester_Report::state( get_post( $legacy ) ), 'approved' );
$legacy_stamp = WPCPM_Semester_Report::approved_at( get_post( $legacy ) );
ck( 'approved by nobody, because approval did not exist when it was marked', isset( $legacy_stamp['by'] ) ? $legacy_stamp['by'] : null, 0 );

// The header prints "Approved on <date>." with no " by " clause when by is 0: rendered here
// as a manager, because a member never sees a draft's editor and this stamp is drawn there.
$prev_manage       = $GLOBALS['manage'];
$GLOBALS['manage'] = true;
$legacy_screen     = screen_html( $C, '2025-H2' );
$GLOBALS['manage'] = $prev_manage;
ck( 'an upgraded report says when it was approved and names nobody', has( $legacy_screen, 'Approved on' ) && ! preg_match( '/Approved on [^<]* by /', $legacy_screen ), true );

ck( 'the vocabulary version is stamped', (int) get_option( WPCPM_Semester_Report::OPT_STATE_VERSION ), 2 );
ck( 'and the since-date is today', get_option( WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ), gmdate( 'Y-m-d' ) );
ck( 'the draft was left alone', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'draft' );

update_post_meta( $legacy, WPCPM_Semester_Report::META_STATE, 'final' );
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2020-01-01';
WPCPM_Semester_Report::maybe_upgrade();
ck( 'a second run does nothing', WPCPM_Semester_Report::state( get_post( $legacy ) ), 'final' );
ck( 'and never moves the since-date', get_option( WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ), '2020-01-01' );
update_post_meta( $legacy, WPCPM_Semester_Report::META_STATE, 'approved' );

echo "\n=== The log ===\n";

$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_LOG ] = array();
WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFTED, $A, $COHORT, 0, array( 'in_progress' => 2, 'email' => 'anna@example.test' ) );
WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_APPROVED, $A, $COHORT, 3 );
$entries = WPCPM_Semester_Report::log_entries();
ck( 'newest first', array( $entries[0]['event'], $entries[1]['event'] ), array( 'approved', 'drafted' ) );
ck( 'the job is actor 0', $entries[1]['actor'], 0 );
ck( 'the in-progress count travels', $entries[1]['in_progress'], 2 );
ck( 'and nothing else does: an address handed to the log is not kept', array_key_exists( 'email', $entries[1] ), false );
for ( $i = 0; $i < WPCPM_Semester_Report::LOG_MAX + 5; $i++ ) {
	WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_REOPENED, $A, $COHORT, 3 );
}
ck( 'the log is capped', count( WPCPM_Semester_Report::log_entries() ), WPCPM_Semester_Report::LOG_MAX );
WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFT_FAILED, $A, $COHORT, 0, array( 'why' => str_repeat( 'x', 500 ) ) );
ck( 'a reason is cut short', strlen( WPCPM_Semester_Report::log_entries()[0]['why'] ), 200 );

WPCPM_Semester_Report::delete_all();
ck( 'uninstall takes the log, the version and the since-date', array(
	get_option( WPCPM_Semester_Report::OPT_LOG, 'gone' ),
	get_option( WPCPM_Semester_Report::OPT_STATE_VERSION, 'gone' ),
	get_option( WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE, 'gone' ),
), array( 'gone', 'gone', 'gone' ) );

echo "\n=== When a cohort is due ===\n";

// The five conditions of design section 5.1, each flipped on its own.
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$today             = '2026-09-04';
// The fixture's own rosters come back at the end of the job block: the approval block below
// needs an institution whose snapshot names released students, which only the fixture has.
$saved_index       = $GLOBALS['index'];
// `email_key` alongside `email`: `cohort_rows()` reads the roster index by `email_key`
// (never `email`), and Task 4's job tests need these rows to carry a real address there so
// `generate()` makes its live Students Reports and Feedback reads instead of finding no
// email and returning early - which is the only way a $GLOBALS['fail_table'] can be proven
// to stop a draft.
$finished          = array(
	array( 'record_id' => 'recS0000000000001', 'email' => 'a@example.test', 'email_key' => 'a@example.test', 'status' => 'Graduate', 'start' => '2026-02-10', 'end' => '2026-06-20' ),
	array( 'record_id' => 'recS0000000000002', 'email' => 'b@example.test', 'email_key' => 'b@example.test', 'status' => 'In Sensei', 'start' => '2026-03-01', 'end' => '2026-06-30' ),
);
$GLOBALS['inst_rows'] = array(
	$A => array( 'record_id' => $A, 'name' => 'Uniwersytet Łódzki', 'stage' => 'Confirmed' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Beta', 'stage' => 'Confirmed' ),
	$C => array( 'record_id' => $C, 'name' => 'Instituto Chunk', 'stage' => 'Interested' ),
);
$GLOBALS['index'] = array(
	$A => array( 'read' => 1756000000, 'rows' => $finished ),
	$B => array( 'read' => 1756000000, 'rows' => $finished ),
	$C => array( 'read' => 1756000000, 'rows' => $finished ),
);
WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';

$due = WPCPM_Semester_Report::due( $today );
ck( 'two active institutions with a finished January-to-June cohort are due', array_map( static function ( $d ) { return $d['institution'] . ' ' . $d['cohort']; }, $due ), array( $A . ' 2026-H1', $B . ' 2026-H1' ) );
ck( 'with nobody in progress and the window end named', array( $due[0]['in_progress'], $due[0]['window_end'] ), array( 0, '2026-06-30' ) );
ck( 'an institution outside the active stages is not due', in_array( $C, array_column( $due, 'institution' ), true ), false );

$GLOBALS['index'][ $A ]['rows'] = array();
ck( 'an empty roster is not due', in_array( $A, array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), true ), false );
$GLOBALS['index'][ $A ]['rows'] = array( array( 'record_id' => 'recS0000000000003', 'email' => 'c@example.test', 'status' => 'In Sensei', 'start' => '', 'end' => '' ) );
ck( 'rows with no start date are not a cohort', in_array( $A, array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), true ), false );
$GLOBALS['index'][ $A ]['rows'] = array( array( 'record_id' => 'recS0000000000004', 'email' => 'd@example.test', 'status' => 'Graduate', 'start' => '2026-08-01', 'end' => '2026-09-01' ) );
ck( 'a window that has not closed is not due, however finished its rows', in_array( $A, array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), true ), false );
$GLOBALS['index'][ $A ]['rows'] = $finished;

$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-07-15';
ck( 'a window that closed before the since-date is history, not a draft', WPCPM_Semester_Report::due( $today ), array() );
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';

WPCPM_Semester_Report::generate( $B, '2026-H1' );
ck( 'a cohort with a report is not due again', array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), array( $A ) );

$late = $finished;
$late[] = array( 'record_id' => 'recS0000000000005', 'email' => 'e@example.test', 'status' => 'In Sensei', 'start' => '2026-03-15', 'end' => '' );
$GLOBALS['index'][ $A ]['rows'] = $late;
ck( 'a row still in progress holds the cohort back inside the grace', WPCPM_Semester_Report::due( '2026-07-20' ), array() );
$grace_due = WPCPM_Semester_Report::due( '2026-08-20' );
ck( 'and the cohort is drafted anyway once the grace has run out', array_column( $grace_due, 'institution' ), array( $A ) );
ck( 'saying how many rows were still in progress', $grace_due[0]['in_progress'], 1 );
$GLOBALS['settings_extra'] = array( 'report_autodraft_grace_days' => 10 );
ck( 'the grace is the setting', array_column( WPCPM_Semester_Report::due( '2026-07-20' ), 'institution' ), array( $A ) );
$GLOBALS['settings_extra'] = array();
$GLOBALS['index'][ $A ]['rows'] = $finished;

$spam = $finished;
$spam[] = array( 'record_id' => 'recS0000000000006', 'email' => 'f@example.test', 'status' => 'SPAM', 'start' => '2026-03-15', 'end' => '' );
$GLOBALS['index'][ $A ]['rows'] = $spam;
ck( 'a SPAM row is nobody in progress', WPCPM_Semester_Report::due( '2026-07-20' )[0]['in_progress'], 0 );
$GLOBALS['index'][ $A ]['rows'] = $finished;

$timed = $finished;
$timed[] = array( 'record_id' => 'recS0000000000008', 'email' => 'h@example.test', 'status' => 'In Sensei', 'start' => '2026-03-15', 'end' => '2026-08-20 10:00:00' );
$GLOBALS['index'][ $A ]['rows'] = $timed;
ck( 'an end date carrying a time is finished on its own day', WPCPM_Semester_Report::due( '2026-08-20' )[0]['in_progress'], 0 );
$timed[2]['end'] = 'not a date';
$GLOBALS['index'][ $A ]['rows'] = $timed;
ck( 'and an end that is not a date is unknown, which is in progress', WPCPM_Semester_Report::due( '2026-08-20' )[0]['in_progress'], 1 );
$GLOBALS['index'][ $A ]['rows'] = $finished;

ck( 'a malformed today is nothing due', WPCPM_Semester_Report::due( 'yesterday' ), array() );
ck( 'and so is an impossible one', WPCPM_Semester_Report::due( '2026-13-45' ), array() );

echo "\n=== The queue and the approved list ===\n";

$q_draft = WPCPM_Semester_Report::generate( $A, '2026-H1', WPCPM_Semester_Report::ORIGIN_AUTO );
update_post_meta( $q_draft, WPCPM_Semester_Report::META_IN_PROGRESS, 1 );
$queue = WPCPM_Semester_Report::queue();
ck( 'the queue lists the drafts, oldest first', array_column( $queue, 'post_id' ), array( WPCPM_Semester_Report::find( $B, '2026-H1' )->ID, $q_draft ) );
ck( 'with institution, cohort, origin and the in-progress count', array( $queue[1]['institution'], $queue[1]['cohort'], $queue[1]['origin'], $queue[1]['in_progress'] ), array( $A, '2026-H1', 'auto', 1 ) );
ck( 'and no approval stamp on a draft', array( $queue[1]['approved_at'], $queue[1]['approved_by'] ), array( 0, 0 ) );
ck( 'nothing approved yet', WPCPM_Semester_Report::approved_since( 0 ), array() );
WPCPM_Semester_Report::approve( get_post( $q_draft ), 3 );
ck( 'the queue shrinks', array_column( WPCPM_Semester_Report::queue(), 'post_id' ), array( WPCPM_Semester_Report::find( $B, '2026-H1' )->ID ) );
$approved = WPCPM_Semester_Report::approved_since( time() - 60 );
ck( 'and the approved list has it, with the stamp', array( count( $approved ), $approved[0]['approved_by'] ), array( 1, 3 ) );
ck( 'a later cut-off leaves it out', WPCPM_Semester_Report::approved_since( time() + 60 ), array() );
$walk = wp_json_encode( array_merge( $queue, $approved, $due, WPCPM_Semester_Report::log_entries() ) );
ck( 'none of the three, nor the log, carries an address', preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $walk ), 0 );

echo "\n=== Draft now ===\n";

$GLOBALS['manage']  = true;
$GLOBALS['acting']  = '';
$GLOBALS['uid']     = 3;
$GLOBALS['ceiling'] = array();
$GLOBALS['flash']   = array();
$GLOBALS['mail']    = array();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_LOG ] = array();
WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
$GLOBALS['index'][ $B ]['rows'] = $finished;

ck( 'drafting writes the report and says so', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $B, 'cohort' => '2026-H1' ) ), 'drafted' );
$b_post = WPCPM_Semester_Report::find( $B, '2026-H1' );
ck( 'as a manager\'s draft', $b_post instanceof WP_Post ? WPCPM_Semester_Report::origin_of( $b_post ) : 'no post', 'manager' );
ck( 'the manager lands on it, as that institution', has( $GLOBALS['redirect'], 'wpcpm_report=2026-H1' ) && has( $GLOBALS['redirect'], WPCPM_Institution_Roster::ARG_VIEW . '=' . $B ), true );
ck( 'and it is logged to the account that pressed', array( WPCPM_Semester_Report::log_entries()[0]['event'], WPCPM_Semester_Report::log_entries()[0]['actor'] ), array( 'drafted', 3 ) );
ck( 'nobody is mailed for a press', $GLOBALS['mail'], array() );
ck( 'a second press finds the report', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $B, 'cohort' => '2026-H1' ) ), 'draft-exists' );
ck( 'no start date is not a semester', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $B, 'cohort' => 'none' ) ), 'bad-cohort' );
ck( 'a malformed record is refused', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => 'not-a-record', 'cohort' => '2025-H2' ) ), 'refused' );

$GLOBALS['ceiling'] = array( 'report-draft:3' => WPCPM_Semester_Report_Screen::DRAFTS_PER_DAY );
ck( 'the twenty-first press in a day is refused', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $A, 'cohort' => '2026-H1' ) ), 'draft-refused' );
ck( 'and writes nothing', WPCPM_Semester_Report::find( $A, '2026-H1' ), null );
$GLOBALS['ceiling'] = array();

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $A;
$GLOBALS['decisions'] = array();
ck( 'a member is refused before the policy is asked', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $A, 'cohort' => '2026-H1' ) ), 'refused' );
ck( 'the capability is the first gate, so the policy never saw the press', $GLOBALS['decisions'], array() );
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';

echo "\n=== The daily job ===\n";

WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
$GLOBALS['mail']      = array();
$GLOBALS['inst_rows'] = array();
$GLOBALS['index']     = array();
for ( $i = 1; $i <= 12; $i++ ) {
	$rec = sprintf( 'recJOB%011d', $i );
	$GLOBALS['inst_rows'][ $rec ] = array( 'record_id' => $rec, 'name' => 'Job University ' . $i, 'stage' => 'Confirmed' );
	$GLOBALS['index'][ $rec ]     = array( 'read' => 1756000000, 'rows' => $finished );
}
$GLOBALS['transients'] = array();

// The first pair is being generated by somebody this minute.
$first_pair = WPCPM_Semester_Report::due( gmdate( 'Y-m-d' ) )[0];
// The job skips the locked first pair, so the first mail sent belongs to the second: capture
// it now, before the lock changes what due() would return.
$second_pair = WPCPM_Semester_Report::due( gmdate( 'Y-m-d' ) )[1];
$GLOBALS['opts'][ 'wpcpm_report_gen_' . md5( $first_pair['institution'] . '|' . $first_pair['cohort'] ) ] = time();

ck( 'one run drafts the cap, skipping the locked pair', WPCPM_Semester_Report_Screen::autodraft_tick(), WPCPM_Semester_Report_Screen::AUTODRAFT_PER_RUN );
ck( 'and mails the managers once per draft, through the report setting', count( array_filter( $GLOBALS['mail'], static function ( $m ) { return 'report-drafted' === $m['context'] && 'managers:report_notify' === $m['to']; } ) ), WPCPM_Semester_Report_Screen::AUTODRAFT_PER_RUN );
ck( 'the mail names the institution, the semester and the review link', has( $GLOBALS['mail'][0]['body'], 'Job University' ) && has( $GLOBALS['mail'][0]['body'], 'January to June 2026' ) && has( $GLOBALS['mail'][0]['body'], 'wpcpm_report=2026-H1' ), true );
ck( 'and opens it as the institution it was drafted for, through the switcher', has( $GLOBALS['mail'][0]['body'], WPCPM_Institution_Roster::ARG_VIEW . '=' . $second_pair['institution'] ), true );
ck( 'and names that institution in the subject', has( $GLOBALS['mail'][0]['subject'], 'Job University ' . (int) substr( $second_pair['institution'], -3 ) ), true );
ck( 'each draft is the job\'s', WPCPM_Semester_Report::queue()[0]['origin'], 'auto' );
ck( 'and logged to actor 0', WPCPM_Semester_Report::log_entries()[0]['actor'], 0 );
unset( $GLOBALS['opts'][ 'wpcpm_report_gen_' . md5( $first_pair['institution'] . '|' . $first_pair['cohort'] ) ] );
ck( 'the next run drafts the rest', WPCPM_Semester_Report_Screen::autodraft_tick(), 2 );
ck( 'and then there is nothing due', WPCPM_Semester_Report_Screen::autodraft_tick(), 0 );

WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
$GLOBALS['fail_table'] = 'tblReports';
$GLOBALS['transients'] = array();
$GLOBALS['mail']       = array();
ck( 'a read that fails drafts nothing and stops nothing', WPCPM_Semester_Report_Screen::autodraft_tick(), 0 );
ck( 'each failure is a log line with the reason', array( WPCPM_Semester_Report::log_entries()[0]['event'], has( WPCPM_Semester_Report::log_entries()[0]['why'], 'tblReports' ) || '' !== WPCPM_Semester_Report::log_entries()[0]['why'] ), array( 'draft_failed', true ) );
// Twelve institutions were due, the cap attempted ten, and every one of the ten failed: this
// counts the whole log rather than entry 0 alone, which only a loop that kept going after the
// first failure could produce.
ck( 'and every attempted pair failed on its own, not the loop', count( array_filter( WPCPM_Semester_Report::log_entries(), static function ( $e ) { return 'draft_failed' === $e['event']; } ) ), WPCPM_Semester_Report_Screen::AUTODRAFT_PER_RUN );
ck( 'and no mail', $GLOBALS['mail'], array() );
$GLOBALS['fail_table'] = '';

$GLOBALS['settings_extra'] = array( 'report_autodraft' => false );
ck( 'switched off, the job does nothing', WPCPM_Semester_Report_Screen::autodraft_tick(), 0 );
$GLOBALS['settings_extra'] = array();
$GLOBALS['inst_rows']      = array( $A => array( 'record_id' => $A, 'name' => 'Uniwersytet Łódzki', 'stage' => 'Confirmed' ), $B => array( 'record_id' => $B, 'name' => 'Universidad Beta', 'stage' => 'Confirmed' ) );
$GLOBALS['index']          = $saved_index;

echo "\n=== Approve and reopen ===\n";

WPCPM_Semester_Report::delete_all();
$GLOBALS['transients'] = array();
$GLOBALS['mail']       = array();
$GLOBALS['members']    = array( $E => array( new WP_User( 21, 'Rep One', 'maciej@a8c.com' ), new WP_User( 22, 'Rep Two', 'maciej@a8c.com' ) ) );
// E's fixture snapshot names released students, so approval has to spend a consent read
// and a Feedback read that fails is a refusal; an institution with nobody released would
// pass without reading, which is the early return consent_check() makes on purpose.
$ap_post = WPCPM_Semester_Report::generate( $E, $COHORT );

$GLOBALS['fail_table'] = 'tblFeedback';
$GLOBALS['transients'] = array();
ck( 'approval is refused while the students\' answers cannot be read', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ), 'approve-failed' );
ck( 'and the report stays a draft', WPCPM_Semester_Report::state( get_post( $ap_post ) ), 'draft' );
ck( 'with nobody told', $GLOBALS['mail'], array() );
$GLOBALS['fail_table'] = '';
$GLOBALS['transients'] = array();

ck( 'approval approves', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ), 'approved' );
ck( 'stamped with the manager', WPCPM_Semester_Report::approved_at( get_post( $ap_post ) )['by'], 3 );
ck( 'the institution\'s two accounts are told', array_map( static function ( $m ) { return $m['to'] . ':' . $m['context']; }, $GLOBALS['mail'] ), array( '21:report-approved', '22:report-approved' ) );
ck( 'and the flash carries the count', $GLOBALS['flash'][ WPCPM_Semester_Report_Screen::FLASH ]['detail']['notified'], 2 );
ck( 'logged', WPCPM_Semester_Report::log_entries()[0]['event'], 'approved' );
ck( 'approving again is a refusal that names the state', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ), 'is-approved' );

$GLOBALS['mail']    = array();
$GLOBALS['members'] = array();
ck( 'reopen reopens', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_REOPEN, array( 'report' => $ap_post ) ), 'reopened' );
ck( 'the stamp is gone', WPCPM_Semester_Report::approved_at( get_post( $ap_post ) ), array() );
ck( 'and nobody is mailed for a reopen', $GLOBALS['mail'], array() );
ck( 'approving with no institution account says so in the count', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ) . ':' . $GLOBALS['flash'][ WPCPM_Semester_Report_Screen::FLASH ]['detail']['notified'], 'approved:0' );
$ap_screen = screen_html( $E, $COHORT );
ck( 'and the page tells the manager to send the PDF by hand', has( $ap_screen, 'by hand' ), true );

echo "\n=== What the institution sees ===\n";

// B with a roster of its own: one finished semester to approve, one older one left a draft.
WPCPM_Semester_Report::delete_all();
$GLOBALS['transients'] = array();
$GLOBALS['flash']      = array();
$GLOBALS['index'][ $B ]['rows'] = array_merge( $finished, array(
	array( 'record_id' => 'recS0000000000007', 'email' => 'g@example.test', 'status' => 'Graduate', 'start' => '2025-09-10', 'end' => '2025-12-20' ),
) );
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$m_approved = WPCPM_Semester_Report::generate( $B, '2026-H1' );
$m_draft    = WPCPM_Semester_Report::generate( $B, '2025-H2' );
WPCPM_Semester_Report::approve( get_post( $m_approved ), 3 );

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $B;
$GLOBALS['uid']    = 21;
$member_card = screen_html( $B, '' );
ck( 'the approved semester is listed', has( $member_card, 'January to June 2026' ), true );
ck( 'with a View link and a PDF link', has( $member_card, '>View<' ) && has( $member_card, '>Download PDF<' ), true );
ck( 'the draft is a sentence, not a report', has( $member_card, 'July to December 2025 is being prepared' ), true );
ck( 'no form at all for a member', has( $member_card, '<form' ), false );
foreach ( array( 'ACTION_GENERATE', 'ACTION_SAVE', 'ACTION_APPROVE', 'ACTION_REOPEN', 'ACTION_DRAFT', 'ACTION_REFRESH_CONSENT' ) as $constant ) {
	ck( 'and no ' . $constant . ' field', has( $member_card, constant( 'WPCPM_Semester_Report_Screen::' . $constant ) ), false );
}
ck( 'no textarea either', has( $member_card, '<textarea' ), false );

$member_reading = screen_html( $B, '2026-H1' );
ck( 'the approved report opens read-only', has( $member_reading, 'Back to the other semesters' ) && has( $member_reading, 'Participation' ), true );
ck( 'with the PDF button and no editor', has( $member_reading, '>Download PDF<' ) && ! has( $member_reading, '<textarea' ), true );
$member_asks_draft = screen_html( $B, '2025-H2' );
ck( 'asking for the draft by address lands on the list', has( $member_asks_draft, 'is being prepared' ) && ! has( $member_asks_draft, 'Participation' ), true );

$GLOBALS['acting'] = $A;
$other_card = screen_html( $A, '' );
ck( 'another institution sees its own empty card', has( $other_card, 'No semester report has been published' ), true );
ck( 'and nothing of B', has( $other_card, 'January to June 2026' ), false );

echo "\n=== What the manager sees ===\n";

$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$GLOBALS['uid']    = 3;
$draft_editor = screen_html( $B, '2025-H2' );
ck( 'a draft offers Approve and not Reopen', has( $draft_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_APPROVE . '"' ) && ! has( $draft_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_REOPEN . '"' ), true );
ck( 'and the editing form', has( $draft_editor, '<textarea' ), true );
ck( 'and says who drafted it', has( $draft_editor, 'Drafted by a program manager' ), true );
$approved_editor = screen_html( $B, '2026-H1' );
ck( 'an approved report offers Reopen and not Approve', has( $approved_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_REOPEN . '"' ) && ! has( $approved_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_APPROVE . '"' ), true );
ck( 'and no editing form', has( $approved_editor, '<textarea' ), false );
ck( 'and says who approved it', has( $approved_editor, 'Approved on' ) && has( $approved_editor, 'Person 3' ), true );
ck( 'the state word is Approved', has( $approved_editor, '>Approved<' ), true );
ob_start();
WPCPM_Semester_Report_Screen::render_draft_form( $B, '2024-H2' );
$draft_form = ob_get_clean();
ck( 'the Draft now form posts the action with the two IDs', has( $draft_form, 'value="' . WPCPM_Semester_Report_Screen::ACTION_DRAFT . '"' ) && has( $draft_form, 'name="institution" value="' . $B . '"' ) && has( $draft_form, 'name="cohort" value="2024-H2"' ), true );
ck( 'guarded against a double press', has( $draft_form, 'data-wpcpm-once' ), true );

echo "\n=== Printing ===\n";

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $B;
$_POST = array();
$_GET  = array( 'report' => $m_draft );
$draft_print = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );
$_GET  = array( 'report' => 999999 );
$ghost_print = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );
ck( 'a member printing a draft reads exactly as a ghost', $draft_print === $ghost_print && '' !== $draft_print, true );
$_GET = array( 'report' => $m_approved );
$approved_print = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );
ck( 'and prints the approved one', has( $approved_print, '<!DOCTYPE html>' ) || has( $approved_print, '<html' ), true );
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$_GET = array( 'report' => $m_draft );
ck( 'a manager prints a draft', has( run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT ), '<html' ), true );
$_GET = array();

echo "\n=== The Draft now picker ===\n";

$GLOBALS['manage']    = true;
$GLOBALS['acting']    = '';
$GLOBALS['inst_rows'] = array(
	$A => array( 'record_id' => $A, 'name' => 'Uniwersytet Łódzki', 'stage' => 'Confirmed' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Beta', 'stage' => 'Confirmed' ),
	$C => array( 'record_id' => $C, 'name' => 'Instituto Chunk', 'stage' => 'Interested' ),
);
$GLOBALS['index'][ $A ]['rows'] = $finished;
$GLOBALS['index'][ $B ]['rows'] = $finished;
$GLOBALS['index'][ $C ]['rows'] = $finished;

ob_start();
WPCPM_Semester_Report_Screen::render_draft_picker();
$picker = ob_get_clean();

ck( 'the picker posts Draft now', has( $picker, 'value="' . WPCPM_Semester_Report_Screen::ACTION_DRAFT . '"' ), true );
ck( 'the institution options list the two active institutions with a roster', has( $picker, 'value="' . $A . '"' ) && has( $picker, 'value="' . $B . '"' ), true );
ck( 'and not the one outside the active stages', has( $picker, 'value="' . $C . '"' ), false );
ck( 'the cohort options include the finished semester and the current one', has( $picker, '2026-H1' ) && has( $picker, WPCPM_Cohort::current() ), true );

$GLOBALS['index'][ $B ]['rows'] = array();
ob_start();
WPCPM_Semester_Report_Screen::render_draft_picker();
$picker_empty_b = ob_get_clean();
ck( 'an institution with no roster rows drops out of the picker', has( $picker_empty_b, 'value="' . $B . '"' ), false );
$GLOBALS['index'][ $B ]['rows'] = $finished;

ck( 'a semester with none of the institution\'s rows is refused with no-rows', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $A, 'cohort' => '2024-H2' ) ), 'no-rows' );
ck( 'and creates no post', WPCPM_Semester_Report::find( $A, '2024-H2' ), null );

$GLOBALS['decisions'] = array();
ck( 'a well-formed record the index never held is refused', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => 'recZZZZZZZZZZZZZZ', 'cohort' => '2026-H1' ) ), 'refused' );
ck( 'and the policy was never asked about it', $GLOBALS['decisions'], array() );

$GLOBALS['index'] = $saved_index;

echo "\n=== handle_generate() drafts, logs and meters like Draft now ===\n";

// Decision 1's second drafting route: the dashboard button a manager already had, now held to
// the same account as Draft now (final review, Important 3).
WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_LOG ]             = array();
$GLOBALS['transients']          = array();
$GLOBALS['manage']              = true;
$GLOBALS['acting']              = $A;
$GLOBALS['uid']                 = 3;
$GLOBALS['ceiling']             = array();
$GLOBALS['index'][ $A ]['rows'] = $finished;

ck( 'the dashboard button generates', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_GENERATE, array( 'cohort' => '2026-H1' ) ), 'generated' );
ck( 'and is logged as a draft, to the manager who pressed it', array( WPCPM_Semester_Report::log_entries()[0]['event'], WPCPM_Semester_Report::log_entries()[0]['actor'] ), array( 'drafted', 3 ) );

$GLOBALS['ceiling'] = array( 'report-draft:3' => WPCPM_Semester_Report_Screen::DRAFTS_PER_DAY );
ck( 'and the same daily ceiling as Draft now refuses it too', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_GENERATE, array( 'cohort' => '2025-H2' ) ), 'draft-refused' );
ck( 'writing nothing', WPCPM_Semester_Report::find( $A, '2025-H2' ), null );
$GLOBALS['ceiling'] = array();

echo "\n=== A report outlives its roster rows ===\n";

// A sync can re-date or remove a roster row long after a report was drafted and mailed to
// the institution: the report is a document, not a live view of the roster, and neither the
// manager's index nor the institution's own card may lose it because the row that produced
// it moved (final review, item 4).
WPCPM_Semester_Report::delete_all();
$GLOBALS['transients'] = array();
$GLOBALS['flash']      = array();
$GLOBALS['manage']     = true;
$GLOBALS['acting']     = '';
$GLOBALS['index'][ $B ]['rows'] = $finished;
$outlives_post = WPCPM_Semester_Report::generate( $B, '2026-H1' );
WPCPM_Semester_Report::approve( get_post( $outlives_post ), 3 );

// The roster now knows only a semester the report was never about.
$GLOBALS['index'][ $B ]['rows'] = array(
	array( 'record_id' => 'recS0000000000007', 'email' => 'g@example.test', 'status' => 'Graduate', 'start' => '2025-09-10', 'end' => '2025-12-20' ),
);

ck( 'reports_of() still has the cohort a re-dated roster no longer offers', array_key_exists( '2026-H1', WPCPM_Semester_Report::reports_of( $B ) ), true );

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $B;
$member_outlives   = screen_html( $B, '' );
ck( 'a member still sees the semester the roster no longer lists', has( $member_outlives, 'January to June 2026' ), true );
ck( 'with its Download PDF link', has( $member_outlives, '>Download PDF<' ), true );

$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$manager_outlives  = screen_html( $B, '' );
ck( 'a manager still sees it too', has( $manager_outlives, 'January to June 2026' ), true );
ck( 'with an Open link', has( $manager_outlives, '>Open<' ), true );

ck( 'a malformed institution has no reports to lose', WPCPM_Semester_Report::reports_of( 'not-a-record' ), array() );

// The same re-dated roster still has a 2025-H2 row, which doubles as the fixture item 7
// needs: a draft in a semester a member can reach by address.
$draft_post = WPCPM_Semester_Report::generate( $B, '2025-H2' );

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $B;
$asked_card        = screen_html( $B, '2025-H2' );
ck( 'a member asking for a draft by address gets an open card', has( $asked_card, 'wpcpm-report-card__disclosure" open' ), true );
$unasked_card = screen_html( $B, '' );
ck( 'and the card stays folded when the address asks for nothing', has( $unasked_card, 'wpcpm-report-card__disclosure" open' ), false );

$GLOBALS['index'] = $saved_index;

echo "\n=== House rules ===\n";

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-semester-report.php',
	'includes/modules/class-wpcpm-semester-report-screen.php',
	'bin/test-semester-report.php',
) as $relative ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $relative ) ) ) {
		$dashes[] = $relative;
	}
}

ck( 'no dash but the plain hyphen in any of the three files', $dashes, array() );

// The promise in the students' handbook is that an institution never sees the feedback forms.
// These two questions are the exception to it, and a promise with an unnamed exception is not a
// promise. Changed in the same release, which is what this asserts.
$handbook = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/13-student-feedback.md' );

ck( 'the students\' handbook names the semester report exception', has( strtolower( $handbook ), 'semester report' ), true );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
