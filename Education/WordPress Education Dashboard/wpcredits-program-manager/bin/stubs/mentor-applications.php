<?php
/**
 * The small WordPress the mentor application suites run in (1.122.31).
 *
 * bin/test-mentor-application.php, bin/test-mentor-application-form.php,
 * bin/test-mentor-application-queue.php and bin/test-mentor-application-feed.php share it: posts and
 * their meta, options and transients, accounts and their meta, the hooks a class adds, the cron a
 * class books, nonces, redirects and `wp_die()` as exceptions, and the site's clock. What each
 * suite needs beyond it (the mail layer, the settings, a screen) the suite stands in itself, so a
 * check can read what was sent and to whom.
 *
 * **The clock.** `$GLOBALS['now']` is the moment in UTC, and `$GLOBALS['site_offset']` how far the
 * site's clock is ahead of UTC: `wp_date()` formats on the site's clock, as core's does, and a post
 * is dated on it (`post_date`) and in UTC (`post_date_gmt`). The plugin's own code reads `time()`,
 * PHP's real clock, where it stamps an event; a check that needs an event in the past writes it
 * there itself.
 *
 * Every address is example.test's, and every name an invented one.
 *
 * Loaded with `require_once __DIR__ . '/stubs/mentor-applications.php';` from a suite's header.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']        = array();
$GLOBALS['transients']  = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['umeta']       = array();
$GLOBALS['hooks']       = array();
$GLOBALS['cron']        = array();
$GLOBALS['post_types']  = array();
$GLOBALS['slugs']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['next_id']     = 500;
$GLOBALS['now']         = gmmktime( 10, 0, 0, 10, 16, 2026 );
$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;
$GLOBALS['insert_fails'] = false;

class WP_Error {
	public $code    = '';
	public $message = '';
	public function __construct( $code = '', $message = '', $data = null ) {
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
	public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_author = 0, $post_title = '', $post_name = '', $post_date = '', $post_date_gmt = '', $post_content = '', $post_modified_gmt = '';
}

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', array $roles = array() ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( preg_replace( '/[^A-Za-z]/', '', $name ) );
		$this->roles        = $roles;
	}
	public function exists() {
		return $this->ID > 0;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/** What a redirect, `wp_die()` or a refused nonce ends a request with, caught by `run()`. */
class WPCPM_Test_Stop extends Exception {}

require_once __DIR__ . '/caps.php';
require_once __DIR__ . '/cleaners.php';
require_once __DIR__ . '/specialchars.php';

/* ---- words and escaping -------------------------------------------------------------------- */

function __( $text, $domain = null ) {
	return $text;
}
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_attr__( $text, $domain = null ) {
	return esc_attr( $text );
}
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_url( $url ) {
	return str_replace( '&', '&#038;', (string) $url );
}
function esc_url_raw( $url, $protocols = null ) {
	return preg_match( '#^https?://[^\s<>"]+$#i', (string) $url ) ? (string) $url : '';
}
function wp_kses( $html, $allowed = array() ) {
	return strip_tags( (string) $html, '<a>' );
}
function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_email( $email ) {
	return trim( (string) $email );
}
function is_email( $email ) {
	return (bool) preg_match( '/^[^@\s]+@[^@\s.]+\.[^@\s]+$/', (string) $email );
}
function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}
function wp_slash( $value ) {
	return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
}
function stripslashes_deep( $value ) {
	return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}
function absint( $value ) {
	return abs( (int) $value );
}
function checked( $one, $two = true, $echo = true ) {
	return (string) $one === (string) $two ? ' checked="checked"' : '';
}
function selected( $one, $two = true, $echo = true ) {
	return (string) $one === (string) $two ? " selected='selected'" : '';
}
function remove_accents( $text, $locale = '' ) {
	return (string) $text;
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

/* ---- the clock ------------------------------------------------------------------------------ */

function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, ( null === $timestamp ? (int) $GLOBALS['now'] : (int) $timestamp ) + (int) $GLOBALS['site_offset'] );
}
function current_time( $type, $gmt = false ) {
	return 'timestamp' === $type || 'U' === $type ? (int) $GLOBALS['now'] : gmdate( 'Y-m-d H:i:s', (int) $GLOBALS['now'] + ( $gmt ? 0 : (int) $GLOBALS['site_offset'] ) );
}
function human_time_diff( $from, $to = 0 ) {
	return '2 hours';
}

/* ---- addresses ------------------------------------------------------------------------------ */

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}
function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
}
function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php';
}
function get_privacy_policy_url() {
	return isset( $GLOBALS['policy'] ) ? (string) $GLOBALS['policy'] : 'https://example.test/privacy/';
}
function wp_parse_url( $url, $component = -1 ) {
	return -1 === $component ? parse_url( (string) $url ) : parse_url( (string) $url, $component );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( (array) $defaults, (array) $args );
}
function trailingslashit( $text ) {
	return rtrim( (string) $text, '/' ) . '/';
}
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = is_array( $args[0] ) ? ( isset( $args[1] ) ? $args[1] : '' ) : ( isset( $args[2] ) ? $args[2] : '' );
	foreach ( $pairs as $key => $value ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( (string) $value );
	}
	return $url;
}

/* ---- hooks, blocks, cron -------------------------------------------------------------------- */

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function apply_filters( $hook, $value, ...$args ) {
	return $value;
}
function do_action( $hook, ...$args ) {}
function add_shortcode( $tag, $callback ) {
	$GLOBALS['hooks'][ 'shortcode:' . $tag ][] = $callback;
}
function register_post_type( $type, $args = array() ) {
	$GLOBALS['post_types'][ $type ] = $args;
	return (object) $args;
}
function register_block_type( $dir, $args = array() ) {
	$GLOBALS['hooks'][ 'block:' . basename( (string) $dir ) ][] = $args;
	return true;
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ]['at'] : false;
}
function wp_schedule_event( $time, $recurrence, $hook, $args = array() ) {
	$GLOBALS['cron'][ $hook ] = array(
		'at'    => (int) $time,
		'every' => $recurrence,
	);
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	return 1;
}

/* ---- nonces, redirects, the end of a request ------------------------------------------------ */

function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . $action;
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return 'nonce-' . $action === (string) $nonce ? 1 : false;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
	$field = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';
	if ( $echo ) {
		echo $field; // phpcs:ignore -- a stand-in.
	}
	return $field;
}
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$nonce = isset( $_REQUEST[ $query_arg ] ) ? (string) $_REQUEST[ $query_arg ] : '';
	if ( ! wp_verify_nonce( $nonce, $action ) ) {
		throw new WPCPM_Test_Stop( 'nonce refused: ' . $action );
	}
	return 1;
}
function wp_safe_redirect( $location, $status = 302 ) {
	throw new WPCPM_Test_Stop( 'redirect: ' . $location );
}
function wp_die( $message = '', $title = '', $args = array() ) {
	throw new WPCPM_Test_Stop( 'wp_die: ' . ( is_scalar( $message ) ? $message : '' ) );
}
function nocache_headers() {
	$GLOBALS['nocache'] = isset( $GLOBALS['nocache'] ) ? $GLOBALS['nocache'] + 1 : 1;
}
function wp_hash( $data, $scheme = 'auth' ) {
	return md5( 'test-salt|' . (string) $data );
}
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	$GLOBALS['password'] = isset( $GLOBALS['password'] ) ? $GLOBALS['password'] + 1 : 1;
	return substr( str_repeat( 'AbCdEf0123456789', 4 ), 0, (int) $length - 4 ) . sprintf( '%04d', $GLOBALS['password'] );
}

/**
 * Run one request step and say how it ended: its output, and the redirect or the stop it ended with.
 *
 * @param callable $step The step.
 * @return array{html: string, stop: string}
 */
function run( callable $step ) {
	ob_start();
	$stop = '';
	try {
		call_user_func( $step );
	} catch ( WPCPM_Test_Stop $e ) {
		$stop = $e->getMessage();
	}
	return array(
		'html' => (string) ob_get_clean(),
		'stop' => $stop,
	);
}

/* ---- options and transients ----------------------------------------------------------------- */

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['opts'][ $key ] );
	return true;
}
function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false;
}
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ] );
	return true;
}

/* ---- posts and their meta ------------------------------------------------------------------- */

function wp_insert_post( $args, $wp_error = false ) {
	if ( $GLOBALS['insert_fails'] ) {
		return $wp_error ? new WP_Error( 'wpcpm_test_insert', 'refused' ) : 0;
	}
	$post                = new WP_Post();
	$post->ID            = $GLOBALS['next_id']++;
	$post->post_type     = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
	$post->post_status   = isset( $args['post_status'] ) ? $args['post_status'] : 'publish';
	$post->post_author   = isset( $args['post_author'] ) ? (int) $args['post_author'] : 0;
	// Unslashed, as core's insert unslashes what it is handed, and a bare "&" written as kses writes it.
	$post->post_title    = str_replace( '&', '&amp;', stripslashes( isset( $args['post_title'] ) ? (string) $args['post_title'] : '' ) );
	$post->post_name     = isset( $args['post_name'] ) ? (string) $args['post_name'] : '';
	$post->post_content  = isset( $args['post_content'] ) ? (string) $args['post_content'] : '';
	$post->post_date     = gmdate( 'Y-m-d H:i:s', (int) $GLOBALS['now'] + (int) $GLOBALS['site_offset'] );
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', (int) $GLOBALS['now'] );
	$GLOBALS['posts'][ $post->ID ] = $post;
	if ( '' !== $post->post_name ) {
		$GLOBALS['slugs'][ $post->ID ] = $post->post_name;
	}
	return $post->ID;
}
function get_post( $id ) {
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}
function get_post_status( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_status : false;
}
function get_post_time( $format = 'U', $gmt = false, $post = null ) {
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	$time = (int) strtotime( $post->post_date_gmt . ' UTC' );
	return 'U' === $format ? ( $gmt ? $time : $time + (int) $GLOBALS['site_offset'] ) : gmdate( $format, $gmt ? $time : $time + (int) $GLOBALS['site_offset'] );
}
function get_permalink( $id ) {
	return 'https://example.test/' . ( isset( $GLOBALS['slugs'][ (int) $id ] ) ? $GLOBALS['slugs'][ (int) $id ] . '/' : '?p=' . (int) $id );
}
function get_page_by_path( $path ) {
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'page' === $post->post_type && 'trash' !== $post->post_status && isset( $GLOBALS['slugs'][ $post->ID ] ) && $path === $GLOBALS['slugs'][ $post->ID ] ) {
			return $post;
		}
	}
	return null;
}
function is_page( $id = 0 ) {
	return isset( $GLOBALS['on_page'] ) && (int) $id === (int) $GLOBALS['on_page'];
}
function wp_delete_post( $id, $force = false ) {
	if ( ! isset( $GLOBALS['posts'][ (int) $id ] ) ) {
		return false;
	}
	$post = $GLOBALS['posts'][ (int) $id ];
	unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] );
	return $post;
}
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : array();
	if ( $single ) {
		return $rows ? $rows[0] : '';
	}
	return $rows;
}
// As core's: what is written is unslashed first, at any depth.
function add_post_meta( $id, $key, $value, $unique = false ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ][] = stripslashes_deep( $value );
	return true;
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array( stripslashes_deep( $value ) );
	return true;
}
function delete_post_meta( $id, $key, $value = '' ) {
	unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
	return true;
}

/**
 * `get_posts()` as the mentor application classes ask it: one type, a status, a state's IN clause,
 * the order asked for, a ceiling, IDs or posts. The order is `orderby` (`date`, which is
 * `post_date` on the site's clock, `ID`, or `meta_value_num`, which reads the number in the post
 * meta `meta_key` names; an array names the direction of each field) with `order` (ASC or DESC) for
 * a string, and core's own default when none is given: the newest first. A `meta_key` is an inner
 * join, as core's is: a post without that key is not found at all. A tie falls to the ID, in the
 * direction of the first field. Anything else it is asked, and an IN clause with an empty list
 * (what core answers to that depends on the version), is recorded in `$GLOBALS['unmodeled']`,
 * which a suite checks is empty at its end.
 *
 * @param array $args The query.
 * @return array
 */
function get_posts( $args = array() ) {
	$known = array( 'post_type', 'post_status', 'numberposts', 'posts_per_page', 'orderby', 'order', 'fields', 'meta_query', 'meta_key', 'suppress_filters', 'no_found_rows' );
	foreach ( array_keys( (array) $args ) as $key ) {
		if ( ! in_array( $key, $known, true ) ) {
			$GLOBALS['unmodeled'][] = 'get_posts: ' . $key;
		}
	}
	$meta_key = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';
	$clauses  = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
	foreach ( $clauses as $clause ) {
		if ( is_array( $clause ) && isset( $clause['key'], $clause['compare'] ) && 'IN' === $clause['compare'] && array() === ( isset( $clause['value'] ) ? (array) $clause['value'] : array() ) ) {
			$GLOBALS['unmodeled'][] = 'get_posts: a meta_query IN clause with an empty list, on ' . $clause['key'];
			return array();
		}
	}
	// The order, as pairs of a field and a direction (1 up, -1 down).
	$orderby = empty( $args['orderby'] ) ? 'date' : $args['orderby'];
	$way     = isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ? 1 : -1;
	$asked   = array();
	if ( is_array( $orderby ) ) {
		foreach ( $orderby as $field => $order ) {
			$asked[] = array( (string) $field, 'ASC' === strtoupper( (string) $order ) ? 1 : -1 );
		}
	} else {
		foreach ( explode( ' ', (string) $orderby ) as $field ) {
			$asked[] = array( $field, $way );
		}
	}
	$sort = array();
	foreach ( $asked as $pair ) {
		if ( in_array( $pair[0], array( 'date', 'post_date' ), true ) ) {
			$sort[] = array( 'post_date', $pair[1] );
		} elseif ( 'ID' === $pair[0] ) {
			$sort[] = array( 'ID', $pair[1] );
		} elseif ( 'meta_value_num' === $pair[0] && '' !== $meta_key ) {
			$sort[] = array( 'meta_value_num', $pair[1] );
		} else {
			$GLOBALS['unmodeled'][] = 'get_posts: orderby ' . $pair[0];
		}
	}
	if ( ! $sort ) {
		$sort[] = array( 'post_date', $way );
	}
	$sort[] = array( 'ID', $sort[0][1] );
	$out    = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && $post->post_status !== $args['post_status'] ) {
			continue;
		}
		if ( '' !== $meta_key && ! isset( $GLOBALS['pmeta'][ (int) $post->ID ][ $meta_key ] ) ) {
			continue;
		}
		foreach ( $clauses as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
				continue;
			}
			$have = get_post_meta( $post->ID, $clause['key'], true );
			if ( isset( $clause['compare'] ) && 'IN' === $clause['compare'] ? ! in_array( $have, (array) $clause['value'], true ) : $have !== $clause['value'] ) {
				continue 2;
			}
		}
		$out[] = $post;
	}
	usort(
		$out,
		function ( $a, $b ) use ( $sort, $meta_key ) {
			foreach ( $sort as $by ) {
				if ( 'ID' === $by[0] ) {
					$compared = $a->ID - $b->ID;
				} elseif ( 'meta_value_num' === $by[0] ) {
					$compared = (float) get_post_meta( $a->ID, $meta_key, true ) <=> (float) get_post_meta( $b->ID, $meta_key, true );
				} else {
					$compared = strcmp( $a->post_date, $b->post_date );
				}
				if ( 0 !== $compared ) {
					return $by[1] * ( $compared < 0 ? -1 : 1 );
				}
			}
			return 0;
		}
	);
	$limit = isset( $args['numberposts'] ) ? (int) $args['numberposts'] : ( isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5 );
	if ( $limit > 0 ) {
		$out = array_slice( $out, 0, $limit );
	}
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_map(
			function ( $post ) {
				return $post->ID;
			},
			$out
		);
	}
	return $out;
}

/* ---- accounts ------------------------------------------------------------------------------- */

/**
 * Add an account to the site.
 *
 * @param int    $id    Its ID.
 * @param string $name  Its name.
 * @param string $email Its address.
 * @param array  $roles Its roles.
 * @param array  $meta  Its meta.
 * @return WP_User
 */
function an_account( $id, $name, $email, array $roles = array(), array $meta = array() ) {
	$GLOBALS['users'][ (int) $id ] = new WP_User( $id, $name, $email, $roles );
	foreach ( $meta as $key => $value ) {
		$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;
	}
	return $GLOBALS['users'][ (int) $id ];
}
function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) || ( 'id' === $field && $user->ID === (int) $value ) || ( 'login' === $field && $user->user_login === (string) $value ) ) {
			return $user;
		}
	}
	return false;
}
function get_current_user_id() {
	return (int) $GLOBALS['uid'];
}
function wp_get_current_user() {
	return isset( $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ (int) $GLOBALS['uid'] ] : new WP_User();
}
function get_user_meta( $id, $key = '', $single = false ) {
	$value = isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : '';
	return $single ? $value : ( '' === $value ? array() : array( $value ) );
}
function update_user_meta( $id, $key, $value ) {
	$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;
	return true;
}
function delete_user_meta( $id, $key, $value = '' ) {
	unset( $GLOBALS['umeta'][ (int) $id ][ $key ] );
	return true;
}

/**
 * `get_users()` as the duplicate check asks it: the accounts holding a role, or a meta key, or
 * either (`relation` OR across a role and a meta key is asked as two calls by the plugin), with
 * `fields` all or ID.
 *
 * @param array $args The query.
 * @return array
 */
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['role__in'] ) && ! array_intersect( (array) $args['role__in'], $user->roles ) ) {
			continue;
		}
		if ( isset( $args['meta_key'] ) && '' === (string) get_user_meta( $user->ID, $args['meta_key'], true ) ) {
			continue;
		}
		if ( isset( $args['capability'] ) && ! user_can( $user, $args['capability'] ) ) {
			continue;
		}
		$out[] = $user;
	}
	if ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) {
		return array_map(
			function ( $user ) {
				return (string) $user->ID;
			},
			$out
		);
	}
	return $out;
}
