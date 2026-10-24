<?php
/**
 * The public application form: its anti-spam, its eighteen fields and its two mails.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **Consent is a precondition and nothing else will do.** Absent, `"0"` and `"yes"` are
 *   each posted here and each has to refuse the whole submission and leave no row behind.
 *   The one that matters is `"yes"`: it is what a hand-made request sends when somebody has
 *   read the form and decided the checkbox is a formality, and an `! empty()` test would take
 *   it. There is a fourth case, `"true"`, which is accepted, because that is what the rule
 *   says and a rule with an untested arm is half a rule.
 * - **The dwell token is single use.** A harvested one is refused the second time it is
 *   posted, however close together the two uses are, because the claim behind it is an
 *   `add_option()` and not a read-then-write.
 * - **The two ceilings do different things on purpose.** Five an hour from one source
 *   refuses and stores nothing; forty a day across the site degrades - the row is kept and
 *   held, and the managers are not paged - so a flood cannot close the form to the one real
 *   institution applying that afternoon. Both are exercised as sequences of real submissions
 *   rather than by calling `WPCPM_Ceiling` directly, because what is being pinned is the
 *   handler's behaviour at the boundary and not the counter's.
 * - **The per-actor ceiling stands in front of everything that writes.** A hundred posts with
 *   a wrong nonce leave one option row and no transient at all, because a refusal that hands
 *   nothing back travels as its own slug instead of two rows in the options table, and because
 *   the one layer that counts an unauthenticated writer runs before the nonce does.
 * - **A held row is acknowledged and only the managers are spared.** Held means "a person
 *   should read this first", never "this application is over": the applicant's message carries
 *   the link that stamps `_wpcpm_app_verified`, and without that stamp
 *   `WPCPM_Institution_Approval` refuses the row for ever.
 * - **An answer is drawn even when the form cannot be.** The confirmation link keeps working
 *   after the form is switched off, and the `closed` sentence is only ever stashed while it is.
 * - **The honeypot is a hold, not a refusal, and the sender is told nothing.** A bot that
 *   learns which attempt was recognised writes a better one.
 * - **Three columns never reach the stored fields.** `Country`, `Current Stage` and
 *   `Privacy Policy Compliance` are the server's, and the fields meta is what an approval
 *   writes to Airtable unchanged.
 * - **The base's spelling is the fixture's.** Every one of the thirteen column names and all
 *   four internship choices are compared byte for byte against
 *   `bin/fixtures/institutions-table-fields.json`, including the U+2019 in one name and the
 *   leading space on one choice. `create_records()` sends no `typecast`, so a name spelled
 *   any other way is a 422 for the whole record. The check reads only the questions that go
 *   to Airtable, and is shown failing for a column the base does not have.
 * - **Five answers are kept on the site and never reach Airtable** (1.123.29). The contact's
 *   role, the languages the students study in, who signs the agreement, their title and the
 *   institution's page about the program carry `'site' => true`: they are stored under their own
 *   meta key, and the fields meta keeps exactly today's eleven columns, because an approval
 *   writes every key of it to the base and a column the base does not have refuses the record.
 *   The languages are the mentor form's own list, read from its class. A tampered post is
 *   cleaned or refused, never stored as typed: a language not on the list is dropped, a title
 *   with no signer refuses the signer's name, a signer with no title refuses the title, and a
 *   page address naming any scheme but http or https is refused.
 * - **A failure never hands back a tick.** The stash that refills the form is asserted to
 *   carry no consent value at all, in every failure path.
 * - **A label is the applicant's words and a key is the base's.** The internship question asks
 *   about "practicums" (1.123.27) over the column that says "practices": the thirteen columns and
 *   the field name each posts under, hashed from the column, are pinned as they were, with the five
 *   site answers' names hashed from their keys among them in form order, so only the label moved. It is a heading with the "Required" mark over its boxes in a group the heading
 *   names and the hint describes.
 * - **The country is one field to type into, and it still posts a record ID.** The combobox is
 *   `WPCPM_Dashboards::render_combo()`'s; the hidden select carries no `required` attribute, or a
 *   browser would refuse the form over a control nobody can see, while the server refuses a form
 *   with no country as it always has, and a country's name is not an answer. A refused country
 *   marks the select and the field `aria-invalid`, and another question's refusal does not.
 * - **The old address answers exactly /apply/, and the move takes only what is the plugin's.**
 *   A 404 whose matched path is the old slug sends a 301 to the form with its query string; a
 *   deeper path ending in it, a slug that starts with it and a real page there are left alone.
 *   The one-time move changes the slug, the title and the content each only while it is still
 *   what the plugin wrote, never takes a slug another post holds, and is stamped before it works.
 *
 * The other pieces are stood in for exactly at their contracts: the mail exit, the manager
 * notification, the settings. `WPCPM_Countries`, `WPCPM_Dashboards`, `WPCPM_Field_Value`,
 * `WPCPM_Ceiling`, `WPCPM_Request` and `WPCPM_Roles` are the real files, because what half of
 * this suite is about is whether this form uses them properly. So is
 * `WPCPM_Mentor_Application_Form`, whose list of languages the form offers, and which this suite
 * does not require: the application's own file brings it, as a site's loader would.
 *
 * Run from the plugin root:  php bin/test-institution-application.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'OBJECT', 'OBJECT' );

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
$GLOBALS['mx']          = true;
$GLOBALS['password']    = 0;
$GLOBALS['enqueued']    = array();
$GLOBALS['nocache']     = 0;
$GLOBALS['on_page']     = 0;
$GLOBALS['hooks']       = array();
$GLOBALS['redirects']   = array();
$GLOBALS['updates']     = array();
$GLOBALS['is_404']      = false;
$GLOBALS['query_vars']  = array();

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_author = 0, $post_title = '', $post_name = '', $post_content = '', $post_date = '', $post_modified_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
// The words as the site's translation gives them, when a test sets one (`$GLOBALS['translations']`).
function __( $s, $d = null ) { return isset( $GLOBALS['translations'][ $s ] ) ? $GLOBALS['translations'][ $s ] : $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) $n; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( __( $s, $d ) ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
// Enough of the real thing for a test: the plugin only ever passes it an anchor whose href
// is already through esc_url(), and what is being asserted is the sentence, not kses itself.
function wp_kses( $html, $allowed = array() ) {
	return strip_tags( (string) $html, '<a>' );
}

function esc_url( $u ) { return (string) $u; }
function esc_url_raw( $u, $protocols = null ) {
	// Only what `WPCPM_Field_Value::clean_url()` needs of it: an http(s) address, or nothing.
	return preg_match( '#^https?://[^\s<>"]+$#i', (string) $u ) ? (string) $u : '';
}
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
function selected( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
// Recorded, so the checks can read which hook and which priority `init()` gives each method.
function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['hooks'][] = array( $h, $c, $p ); }
function add_filter( $h, $c, $p = 10, $a = 1 ) {}
function add_shortcode( $t, $c ) {}
function register_post_type( $t, $a = array() ) { $GLOBALS['post_types'][ $t ] = $a; return (object) $a; }
function apply_filters( $hook, $value ) {
	// The one filter this class offers: the deliverability short circuit, so no test makes a
	// DNS lookup and no test depends on what a domain's records happen to say today.
	if ( 'wpcpm_application_domain_takes_mail' === $hook ) {
		return (bool) $GLOBALS['mx'];
	}
	return $value;
}
function wp_check_comment_disallowed_list( $author, $email, $url, $comment, $ip, $agent ) { return (bool) $GLOBALS['disallowed']; }
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style( $h, $s, $d = array(), $v = false ) {}
function wp_register_script( $h, $s, $d = array(), $v = false, $f = false ) {}
function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
require_once __DIR__ . '/stubs/caps.php';
// The template registry reads every value back as a person reads it (`wp_specialchars_decode()`).
require_once __DIR__ . '/stubs/specialchars.php';
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function get_privacy_policy_url() { return (string) $GLOBALS['policy']; }
function nocache_headers() { ++$GLOBALS['nocache']; }
function is_page( $id = 0 ) { return (int) $id === (int) ( $GLOBALS['on_page'] ?? 0 ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }

function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = is_array( $args[0] ) ? $args[1] : $args[2];
	foreach ( $pairs as $key => $value ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( (string) $value );
	}
	return $url;
}
// Recorded with its status, then thrown, so a handler's `exit` after it is never reached.
function wp_safe_redirect( $to, $status = 302 ) {
	$GLOBALS['redirects'][] = array( $to, $status );
	throw new Exception( 'redirect: ' . $to );
}
function is_404() { return (bool) $GLOBALS['is_404']; }
function get_query_var( $name, $fallback = '' ) { return array_key_exists( $name, $GLOBALS['query_vars'] ) ? $GLOBALS['query_vars'][ $name ] : $fallback; }

/**
 * `wp_kses_post()` as core runs it on the words this suite hands it: a page's block comments and
 * paragraphs. A comment is kept as `wp_kses_split2()` keeps one, its words through kses again, a
 * run of hyphens made one and a hyphen at its end dropped; a bare "&" is written `&amp;`
 * (`wp_kses_normalize_entities()`), a ">" outside a tag `&gt;`, a tag the post context allows is
 * kept only while it carries no attribute, which is stricter than core, and any other tag goes.
 * Core's `pre_kses` filters are not run: the one for blocks parses and serializes the content
 * again, which leaves blocks written as core writes them, as the page's are, as they were.
 */
function wp_kses_post( $html ) {
	$html = preg_replace( '/&(?![A-Za-z]{2,8}[0-9]{0,2};|#[0-9]{1,7};|#[xX][0-9A-Fa-f]{1,6};)/', '&amp;', (string) $html );

	return (string) preg_replace_callback(
		'~(<!--.*?(?:-->|$))|(<[^>]*(?:>|$)|>)~',
		static function ( $found ) {
			if ( '' !== $found[1] ) {
				$inner = wp_kses_post( str_replace( array( '<!--', '-->' ), '', $found[1] ) );

				return '' === $inner ? '' : '<!--' . preg_replace( '/-$/', '', preg_replace( '/--+/', '-', $inner ) ) . '-->';
			}

			if ( '>' === $found[2] ) {
				return '&gt;';
			}

			return preg_match( '%^</?(p|strong|em|br)>$%', $found[2] ) ? $found[2] : '';
		},
		$html
	);
}
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die: ' . $m ); }

function wp_hash( $data, $scheme = 'auth' ) { return md5( 'test-salt|' . (string) $data ); }
function wp_create_nonce( $action = -1 ) { return 'nonce-' . $action; }
function wp_verify_nonce( $nonce, $action = -1 ) { return 'nonce-' . $action === (string) $nonce ? 1 : false; }
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	// Deterministic and unique, which is all the stash id has to be here.
	return substr( str_repeat( 'AbCdEf0123456789', 4 ), 0, (int) $length - 4 ) . sprintf( '%04d', ++$GLOBALS['password'] );
}

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; $GLOBALS['ttl'][ $k ] = $t; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }

function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
// As core's: what is written is unslashed first, at any depth, so words reach it as slashed copies.
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = stripslashes_deep( $value ); return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( stripslashes_deep( $value ) ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }

function wp_insert_post( $a, $error = false ) {
	// What it was handed, as it was handed, for a test that asks whether the words arrived slashed.
	$GLOBALS['inserts'][] = $a;

	if ( ! empty( $GLOBALS['post_fails'] ) ) {
		return $error ? new WP_Error( 'wpcpm_test_insert', 'refused' ) : 0;
	}
	$post                          = new WP_Post();
	$post->ID                      = $GLOBALS['next_id']++;
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	// Unslashed as core's insert unslashes it, so a title handed over as it stands loses a backslash.
	$post->post_title              = stripslashes( (string) ( $a['post_title'] ?? '' ) );
	$post->post_name               = (string) ( $a['post_name'] ?? '' );
	// The content too, as core's insert unslashes the whole post: handed over as it stands, a
	// backslash in a translated intro is lost on the way in.
	$post->post_content            = stripslashes( (string) ( $a['post_content'] ?? '' ) );
	$post->post_date               = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$post->post_modified_gmt       = $post->post_date;
	$GLOBALS['clock']             += 60;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; }
function get_permalink( $id ) { return 'https://example.test/institution-application/'; }

/**
 * Core's lookup by slug, for a top-level slug: a page or an attachment by default, the types
 * named when it is given a list, and in any status, as core's own query asks none.
 */
function get_page_by_path( $slug, $output = 'OBJECT', $post_type = 'page' ) {
	$types = is_array( $post_type ) ? $post_type : array( $post_type, 'attachment' );
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $slug === $post->post_name && in_array( $post->post_type, $types, true ) ) {
			return $post;
		}
	}
	return null;
}

/**
 * Recorded, then applied to the stored post, with whether the move's flag was already written
 * when the update was asked for.
 */
function wp_update_post( $a, $error = false ) {
	$post = get_post( $a['ID'] ?? 0 );
	$GLOBALS['updates'][] = array(
		'args'    => $a,
		'stamped' => get_option( 'wpcpm_application_page_moved', false ),
	);
	if ( ! $post ) {
		return 0;
	}
	// Unslashed as core's update ends up unslashing it (it hands the post to `wp_insert_post()`), so
	// a value handed over as it stands loses a backslash.
	foreach ( array( 'post_title', 'post_name', 'post_content' ) as $field ) {
		if ( array_key_exists( $field, $a ) ) {
			$post->$field = stripslashes( (string) $a[ $field ] );
		}
	}
	return $post->ID;
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }

/**
 * `get_posts()` as this class uses it: one type, one status, one IN clause, oldest first.
 *
 * The `orderby` is honored rather than assumed. Two rows stored in the same second are one
 * `ORDER BY post_date` and no more, so the database returns them in whichever order it likes;
 * asked for an `ID` term as well, it returns them in that. The stub reverses the untied pair,
 * which is one of the orders MySQL is free to give and the one that shows a window taken in
 * SQL keeping a different row from the one a fixed order would keep. `numberposts` is that
 * window, applied here because the class asks the database for it rather than slicing after.
 */
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
	$tied = isset( ( (array) ( $a['orderby'] ?? array() ) )['ID'] );
	usort( $out, function ( $x, $y ) use ( $tied ) {
		$by_date = strcmp( $x->post_date, $y->post_date );
		return 0 !== $by_date ? $by_date : ( $tied ? $x->ID - $y->ID : $y->ID - $x->ID );
	} );
	$window = (int) ( $a['numberposts'] ?? -1 );
	if ( $window > 0 ) { $out = array_slice( $out, 0, $window ); }
	if ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) {
		return array_map( function ( $p ) { return $p->ID; }, $out );
	}
	return $out;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

if ( ! class_exists( 'WPCPM_Settings' ) ) {
	/** The settings, at their contract: the saved array and one value out of it. */
	class WPCPM_Settings {
		public static function get() { return $GLOBALS['settings']; }
		public static function get_value( $key, $fallback = null ) {
			return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
		}
	}
}

if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/**
	 * The single exit for plugin mail, at its contract.
	 *
	 * `send_to()` records what it was asked to send rather than sending it, and calls the
	 * builder the way the real one does - with the address, and with the builder free to
	 * ignore it.
	 */
	class WPCPM_Mail {
		public static function send_to( $email, $context, $build, $locale = '' ) {
			if ( ! is_email( $email ) || ! is_callable( $build ) ) { return false; }
			// A mail server that refuses the hand-off, for the sends that must say so.
			if ( ! empty( $GLOBALS['mail_fails'] ) ) { return false; }
			$mail                = (array) call_user_func( $build, $email );
			$mail['to']          = $email;
			$mail['context']     = $context;
			$mail['locale']      = $locale;
			$GLOBALS['mail'][]   = $mail;
			return true;
		}
		public static function site_name() { return 'WordPress Education'; }
	}
}

if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/** The module, for the one method this form calls. */
	class WPCPM_Institutions {
		public static function notify_managers( $context, $build ) {
			if ( ! is_callable( $build ) ) { return 0; }
			$mail                     = (array) call_user_func( $build, null );
			$mail['context']          = $context;
			$GLOBALS['managermail'][] = $mail;
			return 1;
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
// The class that draws the one field for a long list, which the country is since 1.123.27.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';

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
 * Forget every option, post, transient and message, and put the site back in a state where
 * the form renders: applications on, a published page, a countries map, and a privacy page of the
 * site's own, which the form no longer links (1.123.24) and which the suite checks it ignores.
 *
 * The capability goes back to nobody's, because most of this suite is a stranger posting a
 * form and a block that started with a reset would otherwise inherit whichever viewer the
 * last render happened to leave behind.
 */
function reset_world() {
	$GLOBALS['opts']        = array();
	$GLOBALS['autoload']    = array();
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['transients']  = array();
	$GLOBALS['mail']        = array();
	$GLOBALS['managermail'] = array();
	$GLOBALS['caps']        = false;
	$GLOBALS['uid']         = 0;
	$GLOBALS['policy']      = 'https://example.test/privacy/';
	$GLOBALS['disallowed']  = false;
	$GLOBALS['mx']          = true;
	$GLOBALS['post_fails']  = false;
	$GLOBALS['mail_fails']  = false;
	$GLOBALS['enqueued']    = array();
	$GLOBALS['nocache']     = 0;
	$GLOBALS['on_page']     = 0;
	$GLOBALS['hooks']       = array();
	$GLOBALS['redirects']   = array();
	$GLOBALS['updates']     = array();
	$GLOBALS['inserts']     = array();
	$GLOBALS['is_404']      = false;
	$GLOBALS['query_vars']  = array();
	$GLOBALS['wp']          = (object) array( 'request' => '' );
	$GLOBALS['settings']    = array(
		'applications_enabled'      => true,
		'application_trusted_proxy' => '',
	);

	// The words a test gives a translation, by their English (see `__()`).
	$GLOBALS['translations'] = array();

	$_POST                    = array();
	$_GET                     = array();
	$_SERVER['REMOTE_ADDR']   = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['QUERY_STRING'] );

	// The page the form lives on, published, so `page_url()` answers.
	$page              = new WP_Post();
	$page->ID          = 42;
	$page->post_type   = 'page';
	$page->post_status = 'publish';
	$page->post_title  = 'Apply';
	$page->post_name   = 'institution-application';
	$page->post_date   = '2026-09-01 09:00:00';

	$GLOBALS['posts'][42] = $page;
	$GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] = 42;

	// A privacy page of the site's own, which since 1.123.24 the consent record never names.
	$policy                    = new WP_Post();
	$policy->ID                = 43;
	$policy->post_type         = 'page';
	$policy->post_status       = 'publish';
	$policy->post_title        = 'Privacy';
	$policy->post_date         = '2026-08-01 09:00:00';
	$policy->post_modified_gmt = '2026-08-20 11:30:00';

	$GLOBALS['posts'][43]                        = $policy;
	$GLOBALS['opts']['wp_page_for_privacy_policy'] = 43;

	seed_countries();
}

/** Two countries: one that routes to a manager with a booking link, one that routes nowhere. */
function seed_countries() {
	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1788322400,
		'rows' => array(
			'recCR000000000001' => array(
				'name'     => 'Costa Rica',
				'manager'  => 'recTEAM00000001',
				'email'    => 'manager@example.test',
				'calendly' => 'https://calendly.com/wpcredits-cr',
			),
			'recNG000000000002' => array(
				'name'     => 'Nigeria',
				'manager'  => '',
				'email'    => '',
				'calendly' => '',
			),
		),
	);
}

/** A dwell token of a given age, signed the way the form signs one. */
function dwell_token( $age = 30 ) {
	$issued = time() - (int) $age;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( 'wpcpm-application-dwell|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Institution_Application::ACTION_SUBMIT ) ), 0, 32 );
}

/**
 * The eighteen answers a good application carries, keyed by form key: the thirteen that go to
 * Airtable and the five kept on the site, every one of them given.
 */
function answers( array $overrides = array() ) {
	$values = array(
		'Name'                                                                  => 'Universidad Example',
		'Country'                                                               => 'recCR000000000001',
		'City'                                                                  => 'San Jose',
		'Website'                                                               => 'example.edu',
		'Contact Person'                                                        => 'Ana Ruiz',
		'Contact Email'                                                         => 'ana@example.edu',
		'contact_role'                                                          => 'Internship coordinator',
		'Department'                                                            => 'Faculty of Engineering',
		'How do your internships or practices typically work?'                  => array( ' Based on required hours (e.g. 150 hours)' ),
		'Comments'                                                              => '',
		'Estimated number of students who may be interested'                    => '25',
		'student_languages'                                                     => array( 'Spanish', 'English' ),
		'signer_name'                                                           => 'Signer Example',
		'signer_title'                                                          => 'Dean of the Faculty of Engineering',
		'credits_page'                                                          => 'example.edu/credits',
		'Why are you interested in offering WordPress Credits to your students?' => 'Our students need real open source experience before they graduate.',
		'Anything else you’d like us to know?'                                  => 'We run two intakes a year.',
		'Privacy Policy Compliance'                                             => '1',
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
		$posted[ WPCPM_Institution_Application::form_key( $column ) ] = $value;
	}

	return $posted;
}

/**
 * Read a redirect the way the page behind it would.
 *
 * There are two shapes, and which one a handler used is itself part of what is asserted: a
 * stash id in the URL and everything behind it in a transient, or - when there was nothing to
 * hand back - the outcome slug in the URL and nothing written anywhere. The stash is looked up
 * without consuming it, which is what the page itself would do next.
 *
 * @param string $url Where the handler redirected to.
 * @return array `outcome`, `stash`, `url`, `id`.
 */
function landed( $url ) {
	$id    = '';
	$parts = array();

	if ( preg_match( '/[?&]' . WPCPM_Institution_Application::QUERY_STASH . '=([^&]+)/', $url, $parts ) ) {
		$id = $parts[1];
	}

	$stash = '' === $id ? array() : ( $GLOBALS['transients'][ WPCPM_Institution_Application::TRANSIENT_PREFIX . $id ] ?? array() );

	if ( '' === $id && preg_match( '/[?&]' . WPCPM_Institution_Application::QUERY_OUTCOME . '=([^&]+)/', $url, $parts ) ) {
		$stash = array( 'outcome' => $parts[1] );
	}

	return array(
		'outcome' => isset( $stash['outcome'] ) ? $stash['outcome'] : '',
		'stash'   => $stash,
		'url'     => $url,
		'id'      => $id,
	);
}

/**
 * Put a redirect's query string into `$_GET`, which is what the applicant's browser does next.
 *
 * @param string $url Where the handler redirected to.
 */
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
 * The handler always ends in a redirect, so the redirect is the return value.
 *
 * @param array $answers What to post, from `answers()`.
 * @param array $extra   `token`, `honeypot`, `nonce`, `ip`.
 * @return array `outcome`, `stash`, `url`.
 */
function submit( array $answers, array $extra = array() ) {
	$_POST = array(
		'action'                                          => WPCPM_Institution_Application::ACTION_SUBMIT,
		'_wpnonce'                                        => array_key_exists( 'nonce', $extra ) ? $extra['nonce'] : wp_create_nonce( WPCPM_Institution_Application::ACTION_SUBMIT ),
		WPCPM_Institution_Application::FIELD_ANSWERS      => $answers,
		WPCPM_Institution_Application::TOKEN_FIELD        => array_key_exists( 'token', $extra ) ? $extra['token'] : dwell_token( 30 + ( $GLOBALS['password'] % 600 ) ),
		WPCPM_Institution_Application::HONEYPOT           => isset( $extra['honeypot'] ) ? $extra['honeypot'] : '',
	);

	if ( isset( $extra['ip'] ) ) {
		$_SERVER['REMOTE_ADDR'] = $extra['ip'];
	}

	$url = '';

	try {
		WPCPM_Institution_Application::handle_submit();
	} catch ( Exception $e ) {
		$url = str_replace( 'redirect: ', '', $e->getMessage() );
	}

	return landed( $url );
}

/**
 * One field of one message, or an empty string when that message was never sent.
 *
 * Read through this rather than off `$GLOBALS['mail']` directly so that a regression which
 * stops a message being sent reads as a failed assertion here, and not as a PHP notice about
 * an array index in the middle of the run.
 *
 * @param int    $index Which message, from 0; negative counts back from the last.
 * @param string $key   `to`, `subject`, `body`, `context` or `locale`.
 * @return string
 */
function mail_said( $index, $key ) {
	$index = (int) $index < 0 ? count( $GLOBALS['mail'] ) + (int) $index : (int) $index;

	return isset( $GLOBALS['mail'][ $index ][ $key ] ) ? (string) $GLOBALS['mail'][ $index ][ $key ] : '';
}

/**
 * The answers a redirect handed back, or an empty array when it handed back none.
 *
 * @param array $landed What `submit()` answered with.
 * @return array
 */
function handed_back( array $landed ) {
	return isset( $landed['stash']['values'] ) && is_array( $landed['stash']['values'] ) ? $landed['stash']['values'] : array();
}

/** Every application row, newest last, as the queue would read them. */
function stored() {
	return WPCPM_Institution_Application::applications( WPCPM_Institution_Application::states() );
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

/**
 * A method of the class that 1.123.29 adds, called only once the class has it, so each check that
 * needs it fails on its own before it exists rather than ending the run.
 *
 * @param string $method The method's name.
 * @param mixed  ...$args What to call it with.
 * @return mixed Its answer, or null while the class has no such method.
 */
function app_call( $method, ...$args ) {
	return method_exists( 'WPCPM_Institution_Application', $method ) ? call_user_func_array( array( 'WPCPM_Institution_Application', $method ), $args ) : null;
}

/** One question's spec, its keys sorted, so a check reads what it holds and not the order it was written in. */
function spec_of( $key ) {
	$fields = WPCPM_Institution_Application::fields();

	if ( ! isset( $fields[ $key ] ) ) {
		return null;
	}

	$spec = $fields[ $key ];
	ksort( $spec );

	return $spec;
}

/** The same, for a spec a check writes out. */
function sorted( array $spec ) {
	ksort( $spec );

	return $spec;
}

/**
 * The columns of a set of questions the Institutions table does not have.
 *
 * Only the questions that go to Airtable are read: an answer kept on the site (`'site' => true`)
 * is never a cell, so it is no column the base has to hold.
 *
 * @param array $fields  Questions, keyed as `fields()` keys them.
 * @param array $fixture The fixture, read.
 * @return string[]
 */
function missing_from_fixture( array $fields, array $fixture ) {
	$missing = array();

	foreach ( $fields as $column => $spec ) {
		if ( empty( $spec['site'] ) && ! in_array( (string) $column, $fixture['fields'], true ) ) {
			$missing[] = (string) $column;
		}
	}

	return $missing;
}

/** The meta key the site-only answers are stored under, as the class names it from 1.123.29. */
const SITE_META = '_wpcpm_app_site';

/** The five kept on the site, by key, in the order the form asks them. */
const SITE_KEYS = array( 'contact_role', 'student_languages', 'signer_name', 'signer_title', 'credits_page' );

/** The eleven columns `_wpcpm_app_fields` held before 1.123.29, in order, and holds still. */
const STORED_COLUMNS = array(
	'Name',
	'City',
	'Website',
	'Contact Person',
	'Contact Email',
	'Department',
	'How do your internships or practices typically work?',
	'Comments',
	'Estimated number of students who may be interested',
	'Why are you interested in offering WordPress Credits to your students?',
	"Anything else you\xE2\x80\x99d like us to know?",
);

$src     = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php' );
$fixture = json_decode( file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-table-fields.json' ), true );

echo "\n-- the base's spelling, byte for byte --------------------------------\n";

// Every column this form claims an applicant filled in has to exist in the Institutions
// table under exactly that name: `create_records()` sends no `typecast`, so one wrong name is
// a 422 for the whole record and the application is lost at the moment of approval. The five
// answers kept on the site are no cell, so the check reads the others, and it still refuses a
// question that goes to Airtable under a name the base does not have.
$all_fields = WPCPM_Institution_Application::fields();
$to_airtable = array_filter( $all_fields, static function ( $spec ) { return empty( $spec['site'] ); } );
$on_site     = array_filter( $all_fields, static function ( $spec ) { return ! empty( $spec['site'] ); } );

ck( 'every one of the thirteen columns that go to Airtable is in the fixture', missing_from_fixture( $all_fields, $fixture ), array() );
ck( 'and the check fails for a column the base does not have', missing_from_fixture( $all_fields + array( 'Signer Title' => array( 'group' => 'agreement' ) ), $fixture ), array( 'Signer Title' ) );
ck( 'eighteen questions: thirteen columns of the base and five kept on the site', array( count( $all_fields ), count( $to_airtable ), array_keys( $on_site ) ), array( 18, 13, SITE_KEYS ) );
ck( 'and none of the five is a column of the base, which is why the check passes them by', array_values( array_intersect( array_keys( $on_site ), $fixture['fields'] ) ), array() );

// The languages are the mentor form's own list, so the two forms never offer different names, and
// its class comes with the application's file wherever the form runs, this suite included.
$languages = class_exists( 'WPCPM_Mentor_Application_Form', false ) ? WPCPM_Mentor_Application_Form::languages() : array( 'the mentor form is not loaded' );

ck( 'the mentor form\'s class is loaded with the application\'s file, which this suite requires alone', class_exists( 'WPCPM_Mentor_Application_Form', false ), true );

echo "\n-- the five answers kept on the site ----------------------------------\n";

ck( 'the site-only answers have a meta key of their own', defined( 'WPCPM_Institution_Application::META_SITE' ) ? constant( 'WPCPM_Institution_Application::META_SITE' ) : null, SITE_META );

ck( 'the contact\'s role: who we should contact, required, a short line', spec_of( 'contact_role' ), sorted( array(
	'group'     => 'contact',
	'label'     => 'Their role at the institution',
	'type'      => 'text',
	'required'  => true,
	'maxlength' => 120,
	'help'      => 'For example: internship coordinator, or head of the computer science department.',
	'site'      => true,
) ) );
ck( 'the languages: how the program works, required, the mentor form\'s list drawn in columns', spec_of( 'student_languages' ), sorted( array(
	'group'    => 'program',
	'label'    => 'In which languages do your students study?',
	'type'     => 'choices',
	'required' => true,
	'choices'  => $languages,
	'columns'  => true,
	'help'     => 'Tick every language your students study in.',
	'site'     => true,
) ) );
ck( 'who signs: the agreement, not required', spec_of( 'signer_name' ), sorted( array(
	'group'     => 'agreement',
	'label'     => 'Who will sign the agreement for the institution?',
	'type'      => 'text',
	'required'  => false,
	'maxlength' => 120,
	'help'      => 'Somebody who can commit the institution, such as a dean. Leave it empty if you do not know yet.',
	'site'      => true,
) ) );
ck( 'their title: the agreement, required only beside a name', spec_of( 'signer_title' ), sorted( array(
	'group'     => 'agreement',
	'label'     => 'Their title',
	'type'      => 'text',
	'required'  => false,
	'maxlength' => 120,
	'help'      => 'For example: Dean of the Faculty of Engineering.',
	'site'      => true,
) ) );
ck( 'the page about WordPress Credits: the agreement, an address, not required', spec_of( 'credits_page' ), sorted( array(
	'group'     => 'agreement',
	'label'     => 'Your page about WordPress Credits',
	'type'      => 'url',
	'required'  => false,
	'maxlength' => 200,
	'help'      => 'The agreement asks the institution to publish a page about the program on its website. If it is up already, give its address; if not, leave this empty.',
	'site'      => true,
) ) );
ck( 'the internship question names its own choices, which stay the column\'s', isset( $all_fields['How do your internships or practices typically work?']['choices'] ) ? $all_fields['How do your internships or practices typically work?']['choices'] : null, WPCPM_Institution_Application::internship_choices() );

$order = array_keys( $all_fields );
$in    = static function ( $group ) use ( $all_fields ) {
	return array_keys( array_filter( $all_fields, static function ( $spec ) use ( $group ) { return $group === $spec['group']; } ) );
};

ck( 'the role is asked right after the contact\'s address, before the department', array_slice( $order, (int) array_search( 'Contact Email', $order, true ), 3 ), array( 'Contact Email', 'contact_role', 'Department' ) );
ck( 'the languages are the last question of How your program works', $in( 'program' ), array( 'How do your internships or practices typically work?', 'Comments', 'Estimated number of students who may be interested', 'student_languages' ) );
ck( 'and the agreement asks who signs, their title, then the page', $in( 'agreement' ), array( 'signer_name', 'signer_title', 'credits_page' ) );
ck( 'six groups, the agreement after How your program works', array_keys( WPCPM_Institution_Application::groups() ), array( 'about', 'contact', 'program', 'agreement', 'more', 'consent' ) );
ck( 'and the new one is headed The agreement', WPCPM_Institution_Application::groups()['agreement'] ?? null, 'The agreement' );
// The form draws group by group and, inside a group, in the order `fields()` lists, so a list that
// runs group by group is the order the form asks: what makes `site_fields()` the form's order.
$rank  = array_flip( array_keys( WPCPM_Institution_Application::groups() ) );
$ranks = array();

foreach ( $all_fields as $spec ) {
	$ranks[] = isset( $rank[ $spec['group'] ] ) ? $rank[ $spec['group'] ] : -1;
}

$in_order = $ranks;
sort( $in_order );

ck( 'the questions are listed group by group, in the groups\' order, so the list is the form\'s order', array( $ranks, min( $ranks ) ), array( $in_order, 0 ) );
ck( 'site_fields() is the five specs of fields() that carry the flag, in form order', app_call( 'site_fields' ), $on_site );
ck( 'by their keys', array_keys( (array) app_call( 'site_fields' ) ), SITE_KEYS );

ck(
	'the internship choices are the column\'s own, in order',
	WPCPM_Institution_Application::internship_choices(),
	$fixture['choices']['How do your internships or practices typically work?']
);

// The two strings that look like typos and are not, asserted as bytes rather than as strings,
// because an editor that helpfully replaced either would leave a file that still reads right.
$columns = array_keys( WPCPM_Institution_Application::fields() );

ck(
	'the "anything else" column carries U+2019 and not an apostrophe',
	bin2hex( $columns[16] ?? '' ),
	bin2hex( "Anything else you\xE2\x80\x99d like us to know?" )
);
ck( 'the first internship choice keeps its leading space', substr( WPCPM_Institution_Application::internship_choices()[0], 0, 1 ), ' ' );
ck( 'and the "Other" choice is spelled the way the column spells it', WPCPM_Institution_Application::other_choice(), 'Other (please specify)' );

$keys = array();

foreach ( $columns as $column ) {
	$keys[] = WPCPM_Institution_Application::form_key( $column );
}

ck( 'the eighteen form keys are distinct', count( array_unique( $keys ) ), 18 );
$shaped = 0;

foreach ( $keys as $key ) {
	$shaped += preg_match( '/^f[0-9a-f]{12}$/', $key );
}

ck( 'and each is one letter and twelve hex digits', $shaped, 18 );
ck( 'the U+2019 column hashes to a key nothing else has to spell', WPCPM_Institution_Application::form_key( "Anything else you\xE2\x80\x99d like us to know?" ), 'f3e11bfbf4a59' );

// The three the server holds. Named here as well as in the class, because "never stored" is
// the promise the fields meta makes to the approval that writes it to Airtable unchanged.
ck( 'the server holds three columns of its own', WPCPM_Institution_Application::server_held(), array( 'Country', 'Current Stage', 'Privacy Policy Compliance' ) );

echo "\n-- one answer at a time ----------------------------------------------\n";

reset_world();

$clean = WPCPM_Institution_Application::clean( 'Name', '  Universidad Example  ' );
ck( 'a name is trimmed and kept', array( $clean['ok'], $clean['value'] ), array( true, 'Universidad Example' ) );

$clean = WPCPM_Institution_Application::clean( 'Name', str_repeat( 'a', 300 ) );
ck( 'and cut to the column\'s length rather than refused', mb_strlen( $clean['value'] ), 200 );

$clean = WPCPM_Institution_Application::clean( 'Contact Email', 'ana at example dot edu' );
ck( 'an address that is not one is refused by name', array( $clean['ok'], $clean['problem'] ), array( false, 'bad_email' ) );

$clean = WPCPM_Institution_Application::clean( 'Website', 'example.edu' );
ck( 'a scheme-less website gets the scheme rather than a telling-off', $clean['value'], 'https://example.edu' );

$clean = WPCPM_Institution_Application::clean( 'Estimated number of students who may be interested', '25' );
ck( 'a count is a whole number', array( $clean['ok'], $clean['value'] ), array( true, 25 ) );

ck( 'zero students is below the floor', WPCPM_Institution_Application::clean( 'Estimated number of students who may be interested', '0' )['problem'], 'below_min' );
ck( 'and ten thousand and one is above the ceiling', WPCPM_Institution_Application::clean( 'Estimated number of students who may be interested', '10001' )['problem'], 'above_max' );
ck( 'and a word is not a number', WPCPM_Institution_Application::clean( 'Estimated number of students who may be interested', 'twelve' )['problem'], 'not_a_number' );

ck( 'a country is the record ID the map offers', WPCPM_Institution_Application::clean( 'Country', 'recCR000000000001' )['value'], 'recCR000000000001' );

// A well-formed record ID for a record in some other table is exactly what a hand-made
// request sends, and shape alone would take it.
ck( 'a record ID the map does not offer is refused', WPCPM_Institution_Application::clean( 'Country', 'recZZ000000000009' )['problem'], 'bad_choice' );

$clean = WPCPM_Institution_Application::clean(
	'How do your internships or practices typically work?',
	array( ' Based on required hours (e.g. 150 hours)', 'Something nobody offered', 'Flexible duration or year-round', ' Based on required hours (e.g. 150 hours)' )
);
ck(
	'the multi-select keeps what the column offers, once each, and drops the rest',
	$clean['value'],
	array( ' Based on required hours (e.g. 150 hours)', 'Flexible duration or year-round' )
);

// The consent arm, all four values, because this is the one answer where "nearly" is no.
ck( 'consent: "1" is a tick', WPCPM_Institution_Application::clean( 'Privacy Policy Compliance', '1' )['value'], true );
ck( 'consent: "true" is a tick', WPCPM_Institution_Application::clean( 'Privacy Policy Compliance', 'true' )['value'], true );
ck( 'consent: "yes" is not', WPCPM_Institution_Application::clean( 'Privacy Policy Compliance', 'yes' )['value'], false );
ck( 'consent: "0" is not', WPCPM_Institution_Application::clean( 'Privacy Policy Compliance', '0' )['value'], false );

ck( 'a column nobody asked about is refused rather than stored', WPCPM_Institution_Application::clean( 'Notes', 'anything' )['problem'], 'unknown_field' );

// The five kept on the site, cleaned by the same rules as the thirteen.
$clean = WPCPM_Institution_Application::clean( 'contact_role', '  Head of the computer science department  ' );
ck( 'a role is trimmed and kept', array( $clean['ok'], $clean['value'] ), array( true, 'Head of the computer science department' ) );
ck( 'and cut to its 120 characters', mb_strlen( (string) WPCPM_Institution_Application::clean( 'contact_role', str_repeat( 'a', 300 ) )['value'] ), 120 );
ck( 'and so are a signer\'s name and title', array( mb_strlen( (string) WPCPM_Institution_Application::clean( 'signer_name', str_repeat( 'a', 300 ) )['value'] ), mb_strlen( (string) WPCPM_Institution_Application::clean( 'signer_title', str_repeat( 'a', 300 ) )['value'] ) ), array( 120, 120 ) );

$clean = WPCPM_Institution_Application::clean( 'student_languages', array( 'Spanish', 'Klingon', 'English', 'Spanish', array( 'Polish' ) ) );
ck( 'a language not on the list is dropped, and the rest kept once each, as ticked', array( $clean['ok'], $clean['value'] ), array( true, array( 'Spanish', 'English' ) ) );
ck( 'and a list with nothing on it the form offers is left empty, for requiredness to refuse', WPCPM_Institution_Application::clean( 'student_languages', array( 'Klingon' ) )['value'], array() );
ck( 'every language the mentor form offers is one the institution form takes', WPCPM_Institution_Application::clean( 'student_languages', $languages )['value'], $languages );

$clean = WPCPM_Institution_Application::clean( 'credits_page', 'example.edu/credits' );
ck( 'the page about WordPress Credits gains its scheme, as Website does', array( $clean['ok'], $clean['value'] ), array( true, 'https://example.edu/credits' ) );
ck( 'one given with its scheme keeps it', WPCPM_Institution_Application::clean( 'credits_page', 'http://example.edu/credits' )['value'], 'http://example.edu/credits' );
ck( 'an address with a port is an address and not a scheme', WPCPM_Institution_Application::clean( 'credits_page', 'example.edu:8080/credits' )['value'], 'https://example.edu:8080/credits' );
ck( 'and an empty one stays empty', WPCPM_Institution_Application::clean( 'credits_page', '' ), array( 'ok' => true, 'value' => '', 'problem' => '' ) );

// Anything that names a scheme of its own other than http or https is refused rather than given
// `https://` in front of it, and the Website answer, cleaned by the same rule, the same.
$schemes = array();

foreach ( array( 'javascript:alert(1)', ' JavaScript:alert(1)', 'data:text/html,hello', 'mailto:office@example.test', 'ftp://example.edu/credits' ) as $typed ) {
	$clean     = WPCPM_Institution_Application::clean( 'credits_page', $typed );
	$schemes[] = array( $typed, $clean['ok'], $clean['problem'] );
}

ck( 'a page address naming any other scheme is refused, never stored as typed', $schemes, array(
	array( 'javascript:alert(1)', false, 'bad_url' ),
	array( ' JavaScript:alert(1)', false, 'bad_url' ),
	array( 'data:text/html,hello', false, 'bad_url' ),
	array( 'mailto:office@example.test', false, 'bad_url' ),
	array( 'ftp://example.edu/credits', false, 'bad_url' ),
) );
ck( 'and so is a website that names one', WPCPM_Institution_Application::clean( 'Website', 'javascript:alert(1)' )['problem'], 'bad_url' );

echo "\n-- what is drawn, and when nothing is ---------------------------------\n";

// Since 1.123.24 the form links the WordPress.org privacy policy (the site's owner, 22 October
// 2026), so a site with no privacy page of its own draws it all the same, for the public and for
// an Administrator, with no sentence about a missing page.
reset_world();
$GLOBALS['policy'] = '';
unset( $GLOBALS['opts']['wp_page_for_privacy_policy'] );

$no_page = WPCPM_Institution_Application::render();

ck( 'with no privacy page on the site the public are shown the form, linking WordPress.org\'s policy', array( false !== strpos( $no_page, '<form' ), false !== strpos( $no_page, 'href="https://wordpress.org/about/privacy/" rel="noopener">here</a>' ) ), array( true, true ) );

$GLOBALS['caps'] = true;
$managers        = WPCPM_Institution_Application::render();

ck( 'and so is a manager, with no sentence about a privacy page', array( false !== strpos( $managers, '<form' ), strpos( $managers, 'wpcpm-application__notice' ), strpos( $managers, 'no published privacy policy page' ) ), array( true, false, false ) );

reset_world();
$GLOBALS['settings']['applications_enabled'] = false;

// Since 1.123.26 the one sentence the guide promises, in the form's place, for every visitor: until
// then a visitor who followed a link to the page while the form was off was shown an empty page,
// and the sentence could be read only after a post the page did not offer.
$switched_off = WPCPM_Institution_Application::render();
ck( 'with the form switched off every visitor is shown the one sentence saying applications are closed, and no form', array(
	$switched_off,
	strpos( $switched_off, 'switched off' ),
), array( '<div class="wpcpm-application"><p class="wpcpm-application__message is-info" role="status">The program is not taking applications through this form at the moment.</p></div>', false ) );

$GLOBALS['caps'] = true;
ck( 'and a manager is told it is switched off', false !== strpos( WPCPM_Institution_Application::render(), 'switched off' ), true );
ck( 'under the same sentence every visitor reads', substr_count( WPCPM_Institution_Application::render(), 'The program is not taking applications through this form at the moment.' ), 1 );
// By the name the switch has on the screen that holds it, and where that screen is, as the sponsors'
// form says of its own switch: no control on any screen is called "Accept institution applications".
ck( 'and which switch turns it on, by the name it has on the Settings screen, and where it is', false !== strpos( WPCPM_Institution_Application::render(), 'Turn on &quot;Applications from institutions&quot; on the Institutions tab of WPCredits Program &gt; Settings when the program is ready to take them.' ), true );

reset_world();
unset( $GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] );

ck( 'with no countries map the public are shown nothing', WPCPM_Institution_Application::render(), '' );

$GLOBALS['caps'] = true;
ck( 'and a manager is told the list is empty', false !== strpos( WPCPM_Institution_Application::render(), 'countries list is empty' ), true );

reset_world();
$form = WPCPM_Institution_Application::render();

ck( 'the form posts to admin-post.php', false !== strpos( $form, 'action="https://example.test/wp-admin/admin-post.php"' ), true );
ck( 'and names its action', false !== strpos( $form, 'value="wpcpm_apply"' ), true );
ck( 'and carries a nonce', false !== strpos( $form, 'name="_wpnonce" value="nonce-wpcpm_apply"' ), true );
ck( 'and a dwell token', false !== strpos( $form, 'name="wpcpm_application_token"' ), true );
ck( 'and the honeypot, which is not a hidden input', array( false !== strpos( $form, 'name="wpcpm_confirm_url"' ), false !== strpos( $form, 'type="hidden" id="wpcpm_confirm_url"' ) ), array( true, false ) );

$drawn = 0;

foreach ( $keys as $key ) {
	$drawn += substr_count( $form, 'wpcpm_application[' . $key . ']' ) > 0 ? 1 : 0;
}

ck( 'all eighteen questions are drawn', $drawn, 18 );

echo "\n-- required fields say so ----------------------------------------------\n";

// Every rendered `required` attribute pairs with exactly one visible mark: both come from the
// same boolean in render_field(), so a field that is not required prints neither and one that
// is prints both. Three required questions are the exception, each with a mark and no attribute:
// the internship checklist and the languages, because a checkbox array has no native `required`
// that means "at least one", and the country, because its list is hidden behind its field once
// the script runs, where a browser would refuse the form over a control nobody can see. They are
// checked by name below instead of by count. Since 1.123.29 the contact's role is one more of
// each, and the languages one more mark; who signs, their title and the page are neither.
ck(
	'a visible mark for every rendered `required` attribute and one more for each of the three questions that cannot carry the attribute: ten and thirteen',
	array( substr_count( $form, ' required="required"' ), substr_count( $form, 'wpcpm-field__required' ) ),
	array( 10, 13 )
);

$name_key = WPCPM_Institution_Application::form_key( 'Name' );
$why_key = WPCPM_Institution_Application::form_key( 'Why are you interested in offering WordPress Credits to your students?' );

ck( 'a required text field\'s label carries the mark', false !== strpos( $form, '<label for="wpcpm-application-' . $name_key . '">Name of your institution <span class="wpcpm-field__required">Required</span></label>' ), true );
ck( 'and a required textarea\'s', false !== strpos( $form, '<label for="wpcpm-application-' . $why_key . '">Why are you interested in offering WordPress Credits to your students? <span class="wpcpm-field__required">Required</span></label>' ), true );
ck( 'the consent label ends with the mark, after the whole sentence', false !== strpos( $form, "students&#039; details with the program under it. <span class=\"wpcpm-field__required\">Required</span></label>" ), true );
ck( 'an optional textarea carries no mark', false !== strpos( $form, 'please tell us how</label>' ), true );

ck( 'the six groups are drawn as fieldsets', substr_count( $form, '<fieldset' ), 6 );

preg_match_all( '#<legend>([^<]*)</legend>#', $form, $legends );
ck( 'under their headings, the agreement fourth', $legends[1], array( 'About your institution', 'Who we should contact', 'How your program works', 'The agreement', 'Tell us more', 'Before you send this' ) );

preg_match( '#<legend>The agreement</legend>(.*?)</fieldset>#s', $form, $agreement_set );
preg_match_all( '#name="wpcpm_application\[(f[0-9a-f]{12})\]"#', $agreement_set[1] ?? '', $agreement_names );
ck( 'which asks who signs, their title and the page, and nothing else', $agreement_names[1], array( WPCPM_Institution_Application::form_key( 'signer_name' ), WPCPM_Institution_Application::form_key( 'signer_title' ), WPCPM_Institution_Application::form_key( 'credits_page' ) ) );

$role_id   = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'contact_role' );
$signer_id = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'signer_name' );
$title_id  = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'signer_title' );
$page_id   = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'credits_page' );
$speaks_id = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'student_languages' );

ck( 'the role is a required line of 120 characters, with its mark and its hint', array(
	false !== strpos( $form, '<label for="' . $role_id . '">Their role at the institution <span class="wpcpm-field__required">Required</span></label><input type="text" id="' . $role_id . '" name="wpcpm_application[' . WPCPM_Institution_Application::form_key( 'contact_role' ) . ']" value="" maxlength="120" required="required" aria-describedby="' . $role_id . '-help" />' ),
	false !== strpos( $form, '<span class="wpcpm-field__hint" id="' . $role_id . '-help">For example: internship coordinator, or head of the computer science department.</span>' ),
), array( true, true ) );
ck( 'who signs and their title are lines of 120 characters with no mark and no attribute, each with its hint', array(
	false !== strpos( $form, '<label for="' . $signer_id . '">Who will sign the agreement for the institution?</label><input type="text" id="' . $signer_id . '" name="wpcpm_application[' . WPCPM_Institution_Application::form_key( 'signer_name' ) . ']" value="" maxlength="120" aria-describedby="' . $signer_id . '-help" />' ),
	false !== strpos( $form, '<span class="wpcpm-field__hint" id="' . $signer_id . '-help">Somebody who can commit the institution, such as a dean. Leave it empty if you do not know yet.</span>' ),
	false !== strpos( $form, '<label for="' . $title_id . '">Their title</label><input type="text" id="' . $title_id . '" name="wpcpm_application[' . WPCPM_Institution_Application::form_key( 'signer_title' ) . ']" value="" maxlength="120" aria-describedby="' . $title_id . '-help" />' ),
	false !== strpos( $form, '<span class="wpcpm-field__hint" id="' . $title_id . '-help">For example: Dean of the Faculty of Engineering.</span>' ),
), array( true, true, true, true ) );
ck( 'the page is a text box that takes an address, as Website is, of 200 characters, with its hint', array(
	false !== strpos( $form, '<label for="' . $page_id . '">Your page about WordPress Credits</label><input type="text" id="' . $page_id . '" name="wpcpm_application[' . WPCPM_Institution_Application::form_key( 'credits_page' ) . ']" value="" maxlength="200" inputmode="url" aria-describedby="' . $page_id . '-help" />' ),
	false !== strpos( $form, '<span class="wpcpm-field__hint" id="' . $page_id . '-help">The agreement asks the institution to publish a page about the program on its website. If it is up already, give its address; if not, leave this empty.</span>' ),
), array( true, true ) );

// The languages, drawn as the mentor form draws its own: a group of boxes the heading names and
// the hint describes, in columns, every box posting under the question's name as a list.
preg_match( '#<div class="wpcpm-field wpcpm-field--choices wpcpm-field--columns"><span class="wpcpm-field__label" id="' . $speaks_id . '-label">In which languages do your students study\? <span class="wpcpm-field__required">Required</span></span><span class="wpcpm-field__choices" role="group" aria-labelledby="' . $speaks_id . '-label" aria-describedby="' . $speaks_id . '-help">(.*?)</span><span class="wpcpm-field__hint" id="' . $speaks_id . '-help">Tick every language your students study in\.</span></div>#', $form, $speaks );
preg_match_all( '#<label class="wpcpm-field__check"><input type="checkbox" name="wpcpm_application\[' . WPCPM_Institution_Application::form_key( 'student_languages' ) . '\]\[\]" value="([^"]*)" /> <span>([^<]*)</span></label>#', $speaks[1] ?? '', $boxes );
ck( 'the languages are a group of boxes in columns, under a heading with the mark, the hint describing them', isset( $speaks[1] ), true );
ck( 'one box for each language the mentor form offers, in its order, each named as it is valued', array( $boxes[1], $boxes[2] ), array( $languages, $languages ) );
ck( 'and no box carries the attribute, none of them being the whole answer', preg_match( '#name="wpcpm_application\[' . WPCPM_Institution_Application::form_key( 'student_languages' ) . '\]\[\]"[^>]*required="required"#', $form ), 0 );
ck( 'the internship boxes are not drawn in columns', false !== strpos( $form, '<div class="wpcpm-field wpcpm-field--choices"><span class="wpcpm-field__label" id="wpcpm-application-' . WPCPM_Institution_Application::form_key( 'How do your internships or practices typically work?' ) . '-label">' ), true );

$application_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/application.css' );
ck( 'the columns are the rule the mentor form\'s languages and teams are drawn by', 1 === preg_match( '#\.wpcpm-field--languages \.wpcpm-field__choices,\s*\.wpcpm-field--teams \.wpcpm-field__choices,\s*\.wpcpm-field--columns \.wpcpm-field__choices \{\s*display: grid;#', $application_css ), true );
ck( 'the U+2019 label survives to the page', false !== strpos( $form, "Anything else you\xE2\x80\x99d like us to know?" ), true );
ck( 'the checkbox values keep the leading space the column has', false !== strpos( $form, 'value=" Based on required hours (e.g. 150 hours)"' ), true );
ck( 'and show it trimmed', false !== strpos( $form, '<span>Based on required hours (e.g. 150 hours)</span>' ), true );
ck( 'the consent box is not ticked when nothing was posted', strpos( $form, 'checked="checked"' ), false );

// The policy line under the consent box. The link is one word inside the sentence, not the
// sentence's last two words after a colon: "read the privacy policy here" is what a person
// reads, and "here" is what they click.
ck( 'the policy line links a word inside the sentence', array(
	false !== strpos( $form, '>here</a>' ),
	false !== strpos( $form, '<span class="wpcpm-field__hint">You can read the WordPress.org privacy policy <a href="https://wordpress.org/about/privacy/" rel="noopener">here</a>.</span>' ),
), array( true, true ) );
ck( 'and it points at WordPress.org\'s policy, not the page this site has set, opening where the form is', array( strpos( $form, 'href="' . get_privacy_policy_url() . '"' ), substr_count( $form, 'target=' ) ), array( false, 0 ) );
ck( 'the consent box says which policy, in the words of 1.123.24', isset( WPCPM_Institution_Application::fields()['Privacy Policy Compliance']['label'] ) ? WPCPM_Institution_Application::fields()['Privacy Policy Compliance']['label'] : null, 'I confirm I have read the WordPress.org privacy policy and that this institution can share students\' details with the program under it.' );
ck( 'the old shape, with the link trailing a colon, is gone', false !== strpos( $form, 'The policy is here:' ), false );
ck( 'the stylesheet, the submit guard and the script of the country field are enqueued', $GLOBALS['enqueued'], array( 'wpcpm-institution-application', 'wpcpm-forms', WPCPM_Dashboards::SWITCHER_SCRIPT ) );
ck( 'the form is guarded against a second press', false !== strpos( $form, 'data-wpcpm-once' ), true );

echo "\n-- the internship question: a plain label over the same column ---------\n";

// The label is what an applicant reads. The column beside it is what the record is written under
// and what the form's field name is hashed from (`form_key()`), so the label can change and the
// name cannot. The thirteen names are pinned as they were before the label changed, and the five
// kept on the site since 1.123.29 post under names hashed the same way from their keys.
$posted_before = array(
	'Name'                                                                   => 'f49ee3087348e',
	'Country'                                                                => 'f59716c97497e',
	'City'                                                                   => 'f57d056ed0984',
	'Website'                                                                => 'f15bbb9d0bbf2',
	'Contact Person'                                                         => 'f790a8c9ddb09',
	'Contact Email'                                                          => 'f3c2292f30e63',
	'Department'                                                             => 'f1d17cb9923b9',
	'How do your internships or practices typically work?'                   => 'f8302297bcd34',
	'Comments'                                                               => 'f8413c683b4b2',
	'Estimated number of students who may be interested'                     => 'f6c4c41623a3a',
	'Why are you interested in offering WordPress Credits to your students?' => 'f7c2e16b070b5',
	"Anything else you\xE2\x80\x99d like us to know?"                          => 'f3e11bfbf4a59',
	'Privacy Policy Compliance'                                              => 'f62b991a00670',
);
$posted_new = array(
	'contact_role'      => 'f7831932173fc',
	'student_languages' => 'ff2a977f69784',
	'signer_name'       => 'fb539cfbe7f25',
	'signer_title'      => 'f56c4aac67e61',
	'credits_page'      => 'f53cc2321c1a9',
);
// The eighteen in the order the form asks them: the role after the contact's address, the
// languages last in the program's group, the agreement's three before Tell us more.
$posted_now = array();

foreach ( array( 'Name', 'Country', 'City', 'Website', 'Contact Person', 'Contact Email', 'contact_role', 'Department', 'How do your internships or practices typically work?', 'Comments', 'Estimated number of students who may be interested', 'student_languages', 'signer_name', 'signer_title', 'credits_page', 'Why are you interested in offering WordPress Credits to your students?', "Anything else you\xE2\x80\x99d like us to know?", 'Privacy Policy Compliance' ) as $column ) {
	$posted_now[ $column ] = isset( $posted_before[ $column ] ) ? $posted_before[ $column ] : $posted_new[ $column ];
}

reset_world();
$form        = WPCPM_Institution_Application::render();
$internships = 'How do your internships or practices typically work?';
$field_names = array();

foreach ( array_keys( WPCPM_Institution_Application::fields() ) as $column ) {
	$field_names[ $column ] = WPCPM_Institution_Application::form_key( $column );
}

preg_match_all( '#name="wpcpm_application\[(f[0-9a-f]{12})\](?:\[\])?"#', $form, $posted );

ck( 'the thirteen columns are spelled as they were, in order, and each posts under the name it posted under before', array_diff_key( $field_names, $posted_new ), $posted_before );
ck( 'the eighteen are in form order, the five new ones under names hashed from their keys', $field_names, $posted_now );
ck( 'and the form posts those names and no others', array_values( array_unique( $posted[1] ) ), array_values( $posted_now ) );

ck(
	'the question\'s words say practicums, and the column it is written under does not',
	array( WPCPM_Institution_Application::fields()[ $internships ]['label'], bin2hex( array_keys( WPCPM_Institution_Application::fields() )[8] ?? '' ) ),
	array( 'How do your internships or practicums typically work?', bin2hex( $internships ) )
);
ck( 'nothing on the page says practices', array( substr_count( $form, 'practicums' ), strpos( $form, 'practices' ) ), array( 1, false ) );

$internship_id = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( $internships );

ck(
	'the question is a label with an id and the mark, over its choices in a group that label names and the hint describes',
	false !== strpos( $form, '<span class="wpcpm-field__label" id="' . $internship_id . '-label">How do your internships or practicums typically work? <span class="wpcpm-field__required">Required</span></span><span class="wpcpm-field__choices" role="group" aria-labelledby="' . $internship_id . '-label" aria-describedby="' . $internship_id . '-help">' ),
	true
);
ck(
	'four boxes sit in the group, each posting under the column\'s name as a list, and the hint follows it under the id the group names',
	1 === preg_match( '#role="group" aria-labelledby="' . $internship_id . '-label" aria-describedby="' . $internship_id . '-help">((?:<label class="wpcpm-field__check"><input type="checkbox" name="wpcpm_application\[' . $field_names[ $internships ] . '\]\[\]" value="[^"]*" /> <span>[^<]*</span></label>){4})</span><span class="wpcpm-field__hint" id="' . $internship_id . '-help">Tick everything that applies\.</span></div>#', $form ),
	true
);
ck( 'and no native attribute is on a box, because none of them is the whole answer', preg_match( '#name="wpcpm_application\[' . $field_names[ $internships ] . '\]\[\]"[^>]*required="required"#', $form ), 0 );

// Handed back for no box ticked, the group is described by the problem as well as the hint.
reset_world();
$none = submit( answers( array( $internships => array() ) ) );
follow( $none['url'] );
$again = WPCPM_Institution_Application::render();

ck(
	'a form handed back for no box ticked has the group describe itself by the hint and the problem',
	array( false !== strpos( $again, 'role="group" aria-labelledby="' . $internship_id . '-label" aria-describedby="' . $internship_id . '-help ' . $internship_id . '-problem">' ), false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $internship_id . '-problem">Please fill this in.</span>' ) ),
	array( true, true )
);

echo "\n-- the country: one field to type into, posting the record ID ---------\n";

reset_world();
$form       = WPCPM_Institution_Application::render();
$country_id = 'wpcpm-application-' . $field_names['Country'];

ck(
	'the label carries the id the list names and the mark, over the select with its empty first entry chosen and the countries valued by record ID',
	false !== strpos( $form, '<label for="' . $country_id . '" id="' . $country_id . '-label">Country <span class="wpcpm-field__required">Required</span></label> <select name="wpcpm_application[' . $field_names['Country'] . ']" id="' . $country_id . '" autocomplete="off"><option value="" selected="selected">Choose a country</option><option value="recCR000000000001">Costa Rica</option><option value="recNG000000000002">Nigeria</option></select>' ),
	true
);
ck( 'a record ID is what the select posts, and a country\'s name is not a value anywhere', array( strpos( $form, 'value="Costa Rica"' ), strpos( $form, 'value="Nigeria"' ) ), array( false, false ) );
ck( 'the select carries no `required`, and neither does the field beside it, or a browser would refuse the form over a control nobody can see', array( preg_match( '#<select name="wpcpm_application\[' . $field_names['Country'] . '\]"[^>]*required="required"#', $form ), preg_match( '#<input type="text" id="' . $country_id . '-input"[^>]*required="required"#', $form ) ), array( 0, 0 ) );
ck( 'the select is not hidden in the markup, so without the script it is the field', preg_match( '#<select name="wpcpm_application\[' . $field_names['Country'] . '\]"[^>]*hidden#', $form ), 0 );
ck(
	'beside it, hidden until the script shows it, is the field that takes typing, named by nobody',
	1 === preg_match( '#<div class="wpcpm-dashboard__switcher-combo" hidden><input type="text" id="' . $country_id . '-input" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . $country_id . '-list" autocomplete="off" spellcheck="false" placeholder="Type to find your country" />#', $form ) && 0 === preg_match( '#<input type="text" id="' . $country_id . '-input"[^>]* name=#', $form ),
	true
);
ck(
	'with its list named by the label, and its two sentences the translated strings the mentor form passes',
	array( false !== strpos( $form, '<ul id="' . $country_id . '-list" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="' . $country_id . '-label" hidden></ul>' ), false !== strpos( $form, 'data-wpcpm-count="Countries in the list: %s" data-wpcpm-none="No country has that name."' ) ),
	array( true, true )
);
ck( 'the question sits in a div, because a paragraph cannot hold the field', array( false !== strpos( $form, '<div class="wpcpm-field wpcpm-field--country">' ), strpos( $form, '<p class="wpcpm-field wpcpm-field--country' ) ), array( true, false ) );
ck( 'there is one search field for the country and no search box above a select', array( substr_count( $form, 'role="combobox"' ), substr_count( $form, 'type="search"' ) ), array( 1, 0 ) );

// A to Z by name whatever order the map is stored in, the value still the record ID.
reset_world();
$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]['rows'] = array(
	'recZM000000000003' => array( 'name' => 'Zambia', 'manager' => '', 'email' => '', 'calendly' => '' ),
	'recAL000000000004' => array( 'name' => 'Albania', 'manager' => '', 'email' => '', 'calendly' => '' ),
	'recMX000000000005' => array( 'name' => 'Mexico', 'manager' => '', 'email' => '', 'calendly' => '' ),
);
preg_match( '#<select name="wpcpm_application\[' . $field_names['Country'] . '\]"[^>]*>(.*?)</select>#', WPCPM_Institution_Application::render(), $list );
ck( 'the countries are listed A to Z by name, each valued by its record ID', $list[1], '<option value="" selected="selected">Choose a country</option><option value="recAL000000000004">Albania</option><option value="recMX000000000005">Mexico</option><option value="recZM000000000003">Zambia</option>' );

// What the server does with it has not moved.
reset_world();
$nobody = submit( answers( array( 'Country' => '' ) ) );

ck( 'a post with no country is refused as it always was: the field is asked for, and nothing is stored', array( $nobody['outcome'], $nobody['stash']['problems']['Country'], count( stored() ) ), array( 'again', 'required', 0 ) );

follow( $nobody['url'] );
$again = WPCPM_Institution_Application::render();

ck(
	'and the form handed back says so under the field, which the select and the field are marked invalid by and described by',
	array( false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $country_id . '-problem">Please fill this in.</span>' ), false !== strpos( $again, 'id="' . $country_id . '" autocomplete="off" aria-invalid="true" aria-describedby="' . $country_id . '-problem">' ), false !== strpos( $again, 'id="' . $country_id . '-input" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . $country_id . '-list" autocomplete="off" spellcheck="false" placeholder="Type to find your country" aria-invalid="true" aria-describedby="' . $country_id . '-problem"' ) ),
	array( true, true, true )
);
// The mark is the refusal's, so the form drawn before any post, and a form handed back over another
// answer, leave the country's select and field unmarked.
$country_invalid = static function ( $page ) use ( $country_id ) {
	return array( 1 === preg_match( '#id="' . preg_quote( $country_id, '#' ) . '"[^>]*aria-invalid#', $page ), 1 === preg_match( '#id="' . preg_quote( $country_id, '#' ) . '-input"[^>]*aria-invalid#', $page ) );
};
ck( 'the country carries aria-invalid on the select and on the field after its own refusal', $country_invalid( $again ), array( true, true ) );
ck( 'and not on the form before any post', $country_invalid( $form ), array( false, false ) );

reset_world();
$named = submit( answers( array( 'Country' => 'Costa Rica' ) ) );

ck( 'a country\'s name is not what the form posts and is refused, nothing stored', array( $named['stash']['problems']['Country'], count( stored() ) ), array( 'bad_choice', 0 ) );

reset_world();
$name_missing = submit( answers( array( 'Name' => '', 'Country' => 'recNG000000000002' ) ) );
follow( $name_missing['url'] );
$again = WPCPM_Institution_Application::render();

ck( 'a form handed back for another answer keeps the country picked, chosen in the select', array( false !== strpos( $again, '<option value="recNG000000000002" selected="selected">Nigeria</option>' ), false !== strpos( $again, '<option value="" selected="selected">' ) ), array( true, false ) );
ck( 'and does not mark it invalid: another answer was refused, not the country', $country_invalid( $again ), array( false, false ) );

echo "\n-- one good application, end to end -----------------------------------\n";

reset_world();
$sent = submit( answers() );
$row  = only_row();

ck( 'the sender is thanked', $sent['outcome'], 'sent' );
ck( 'one row was stored', $row instanceof WP_Post, true );
ck( 'private, so nothing serves it to the public', $row->post_status, 'private' );
ck( 'authored by nobody, because nobody was signed in', $row->post_author, 0 );
ck( 'titled with the institution\'s name', $row->post_title, 'Universidad Example' );
ck( 'and filed as new', get_post_meta( $row->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_NEW );
ck( 'with no signals against it', get_post_meta( $row->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array() );

$fields = get_post_meta( $row->ID, WPCPM_Institution_Application::META_FIELDS, true );

// The whole point of the fields meta: an approval writes it to Airtable unchanged, so the
// three the server holds must not be in it, whatever the applicant posted.
ck( 'the stored fields hold no Country', array_key_exists( 'Country', $fields ), false );
ck( 'no Current Stage', array_key_exists( 'Current Stage', $fields ), false );
ck( 'and no Privacy Policy Compliance', array_key_exists( 'Privacy Policy Compliance', $fields ), false );
ck( 'eleven columns are stored: the thirteen less the country and the consent', count( $fields ), 11 );
ck( 'exactly the eleven they were before 1.123.29, in order, and none of the five kept on the site', array_keys( $fields ), STORED_COLUMNS );

// The five go to their own key, cleaned, in form order: the page has its scheme and the languages
// are the list ticked. An approval writes every key of the fields meta to Airtable, so not one of
// them may be in it.
$site = get_post_meta( $row->ID, SITE_META, true );

ck( 'the five answers kept on the site are stored under their own key, cleaned, in form order', $site, array(
	'contact_role'      => 'Internship coordinator',
	'student_languages' => array( 'Spanish', 'English' ),
	'signer_name'       => 'Signer Example',
	'signer_title'      => 'Dean of the Faculty of Engineering',
	'credits_page'      => 'https://example.edu/credits',
) );
ck( 'and site_answers() reads them back', app_call( 'site_answers', $row->ID ), $site );

// An application filed before 1.123.29 has none, and is answered with an empty list rather than
// an empty string, as is a post that is no application at all.
$before_release = $GLOBALS['pmeta'][ $row->ID ][ SITE_META ] ?? null;
unset( $GLOBALS['pmeta'][ $row->ID ][ SITE_META ] );
ck( 'an application filed before the five were asked has no site answers, and says so with an empty list', array( app_call( 'site_answers', $row->ID ), app_call( 'site_answers', 42 ), app_call( 'site_answers', 9999 ) ), array( array(), array(), array() ) );
if ( null !== $before_release ) {
	$GLOBALS['pmeta'][ $row->ID ][ SITE_META ] = $before_release;
}
$holding = array();

foreach ( $GLOBALS['pmeta'][ $row->ID ] as $key => $meta_rows ) {
	if ( false !== strpos( serialize( $meta_rows ), 'Internship coordinator' ) || false !== strpos( serialize( $meta_rows ), 'example.edu/credits' ) ) {
		$holding[] = $key;
	}
}

ck( 'and no other meta of the row holds any of them', $holding, array( SITE_META ) );

ck( 'the U+2019 column round-trips into storage under its own name', $fields[ "Anything else you\xE2\x80\x99d like us to know?" ], 'We run two intakes a year.' );
ck( 'the multi-select is stored as the column\'s own choice, leading space and all', $fields['How do your internships or practices typically work?'], array( ' Based on required hours (e.g. 150 hours)' ) );
ck( 'the website was normalised on the way in', $fields['Website'], 'https://example.edu' );
ck( 'and the count is an integer, not a string', $fields['Estimated number of students who may be interested'], 25 );

ck( 'the country is held on its own key', get_post_meta( $row->ID, WPCPM_Institution_Application::META_COUNTRY, true ), 'recCR000000000001' );
ck( 'with the name it had that day', get_post_meta( $row->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ), 'Costa Rica' );
ck( 'and a snapshot of who it routed to', get_post_meta( $row->ID, WPCPM_Institution_Application::META_MANAGER, true )['calendly'], 'https://calendly.com/wpcredits-cr' );

$consent = get_post_meta( $row->ID, WPCPM_Institution_Application::META_CONSENT, true );

// The address agreed to, WordPress.org's, with no post and no modified time: the site's own page,
// set in this world as page 43, is not what the applicant read (1.123.24).
ck( 'the consent record names the policy agreed to', array( $consent['url'], $consent['policy'] ), array( 'https://wordpress.org/about/privacy/', 0 ) );
ck( 'and no version of a page on this site', $consent['modified'], '' );
ck( 'and the sentence as it was rendered', $consent['sentence'], 'I confirm I have read the WordPress.org privacy policy and that this institution can share students\' details with the program under it.' );
ck( 'the address is truncated, not kept', $consent['ip'], '203.0.113.0' );
ck( 'the browser is recorded', $consent['agent'], 'Mozilla/5.0 (test)' );

ck( 'the address is stored only as a hash, for duplicate flagging', get_post_meta( $row->ID, WPCPM_Institution_Application::META_EMAIL, true ), wp_hash( 'ana@example.edu' ) );
ck( 'nothing says the address is confirmed yet', get_post_meta( $row->ID, WPCPM_Institution_Application::META_VERIFIED, true ), '' );

$events = get_post_meta( $row->ID, WPCPM_Institution_Application::META_EVENT );

ck( 'one event row was written', count( $events ), 1 );
ck( 'saying what happened and by whom', array( $events[0]['event'], $events[0]['actor'] ), array( 'submitted', 0 ) );

ck( 'the reference reads like a reference', WPCPM_Institution_Application::reference( $row->ID ), 'APP-2026-0500' );
ck( 'and is stored on the row', get_post_meta( $row->ID, WPCPM_Institution_Application::META_REFERENCE, true ), 'APP-2026-0500' );

// The confirmation is drawn from a one-shot stash and never from a reference in the address
// bar, so the reference cannot become a bearer token for somebody else's address.
ck( 'the redirect carries a random id and nothing else', 1, preg_match( '#^https://example\.test/institution-application/\?wpcpm_app=[a-z0-9]+$#', $sent['url'] ) );
ck( 'and the reference is nowhere in it', strpos( $sent['url'], 'APP-' ), false );

$_GET[ WPCPM_Institution_Application::QUERY_STASH ] = $sent['id'];
$page = WPCPM_Institution_Application::render();

ck( 'the confirmation names the reference', false !== strpos( $page, 'APP-2026-0500' ), true );
ck( 'and does not draw the form again', strpos( $page, '<form' ), false );
$again = WPCPM_Institution_Application::render();

// One-shot: the id is still in the address bar, and a reload or a forwarded link gets the
// plain form rather than repeating somebody else's confirmation back at whoever opens it.
ck( 'and is gone on the next page load', strpos( $again, 'APP-' ), false );
ck( 'which shows the plain form again', false !== strpos( $again, '<form' ), true );

echo "\n-- the two mails ------------------------------------------------------\n";

ck( 'one message to the applicant', count( $GLOBALS['mail'] ), 1 );
ck( 'through the single exit, with its own context', array( $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['context'] ), array( 'ana@example.edu', 'institution-applied' ) );
ck( 'the subject carries the site and the reference', $GLOBALS['mail'][0]['subject'], '[WordPress Education] We have your application: APP-2026-0500' );
ck( 'the body names the routed manager\'s booking link', false !== strpos( $GLOBALS['mail'][0]['body'], 'https://calendly.com/wpcredits-cr' ), true );
ck( 'and the link that confirms the address', false !== strpos( $GLOBALS['mail'][0]['body'], 'action=wpcpm_apply_verify' ), true );
ck( 'and says what happens next', false !== strpos( $GLOBALS['mail'][0]['body'], 'What happens next' ), true );

ck( 'one message to the managers', count( $GLOBALS['managermail'] ), 1 );
ck( 'with its own context', $GLOBALS['managermail'][0]['context'], 'institution-application' );
ck( 'naming the institution in the subject', $GLOBALS['managermail'][0]['subject'], '[WordPress Education] New institution application from Universidad Example' );
ck( 'and linking the queue row', false !== strpos( $GLOBALS['managermail'][0]['body'], 'page=wpcpm-institutions&wpcpm_app_id=' . $row->ID ), true );

// The queue shows all thirteen answers to somebody who has signed in. A mail is a copy in an
// inbox that nothing on this site can withdraw, so it carries facts and a link.
ck( 'the managers\' mail does not carry the applicant\'s own writing', strpos( $GLOBALS['managermail'][0]['body'], 'real open source experience' ), false );

echo "\n-- a country that routes to nobody ------------------------------------\n";

reset_world();
submit( answers( array( 'Country' => 'recNG000000000002' ) ) );

ck( 'the application is taken all the same', get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_NEW );
ck( 'the routing snapshot is simply empty', get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_MANAGER, true ), array() );
ck( 'and the acknowledgement offers no call rather than a broken link', strpos( $GLOBALS['mail'][0]['body'], 'book a call' ), false );

echo "\n-- consent is a precondition ------------------------------------------\n";

foreach ( array( 'absent' => null, 'zero' => '0', 'yes' => 'yes', 'empty' => '' ) as $label => $value ) {
	reset_world();
	$sent = submit( answers( array( 'Privacy Policy Compliance' => $value ) ) );

	ck( "consent $label: the submission is refused", $sent['outcome'], 'consent' );
	ck( "consent $label: and nothing at all is stored", count( stored() ), 0 );
	ck( "consent $label: and nobody is mailed", array( count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( 0, 0 ) );
}

// The other arm of the same rule, so that "only these two values" is tested from both sides.
reset_world();
submit( answers( array( 'Privacy Policy Compliance' => 'true' ) ) );
ck( 'consent "true": the application is taken', count( stored() ), 1 );

reset_world();
$sent = submit( answers( array( 'Privacy Policy Compliance' => '0' ) ) );

ck( 'a refusal hands back what was typed', $sent['stash']['values']['Name'], 'Universidad Example' );
ck( 'and the country, so the select comes back on the right one', $sent['stash']['values']['Country'], 'recCR000000000001' );
ck( 'and never the tick', array_key_exists( 'Privacy Policy Compliance', $sent['stash']['values'] ), false );

$_GET[ WPCPM_Institution_Application::QUERY_STASH ] = $sent['id'];
$back = WPCPM_Institution_Application::render();

ck( 'the form comes back with the answers in it', false !== strpos( $back, 'value="Universidad Example"' ), true );
ck( 'and the consent box unticked', strpos( $back, 'value="1" required="required" checked' ), false );
ck( 'and says what to do', false !== strpos( $back, 'tick the last box' ), true );

echo "\n-- the honeypot -------------------------------------------------------\n";

reset_world();
$sent = submit( answers(), array( 'honeypot' => 'https://example.com/' ) );
$row  = only_row();

ck( 'the sender is told exactly what a real applicant is told', $sent['outcome'], 'sent' );
ck( 'the row is kept', $row instanceof WP_Post, true );
ck( 'and held as spam', get_post_meta( $row->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_SPAM );
ck( 'saying why', in_array( 'honeypot', get_post_meta( $row->ID, WPCPM_Institution_Application::META_SIGNALS, true ), true ), true );
ck( 'and nothing is sent to anybody', array( count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( 0, 0 ) );

// A honeypot row skips requiredness on purpose: telling a bot which of eighteen fields it
// missed is free tuition, and the row is going to a human either way.
reset_world();
submit( answers( array( 'Name' => '', 'City' => '' ) ), array( 'honeypot' => 'x' ) );
ck( 'an incomplete spam row is still stored rather than argued with', count( stored() ), 1 );

echo "\n-- the dwell token ----------------------------------------------------\n";

/**
 * Put the form's nonce in the request, which is where `check_token()` reads it from.
 *
 * The token is signed with it, so a token checked against no nonce at all is a forgery as
 * far as this class is concerned - which is the behaviour, and is why the direct checks below
 * arm it first.
 */
function arm_nonce() {
	$_POST['_wpnonce'] = wp_create_nonce( WPCPM_Institution_Application::ACTION_SUBMIT );
}

reset_world();
arm_nonce();

// A token minted this instant is younger than MIN_SECONDS, which is the point: a form cannot
// be read and posted in the same second by a person. It is its own answer rather than spam
// since FANON-1, because the one person who does post that fast is an applicant re-sending
// after a bounce redrew the page: the handler holds that row instead of filing it silently.
ck( 'a token minted this instant is too fast to be a person, and says so as itself', WPCPM_Institution_Application::check_token( WPCPM_Institution_Application::token() ), 'fast' );

reset_world();
arm_nonce();
ck( 'and one minted half a minute ago is accepted', WPCPM_Institution_Application::check_token( dwell_token( 30 ) ), 'ok' );

reset_world();
arm_nonce();
$token = dwell_token( 45 );

ck( 'the first use of a token is accepted', WPCPM_Institution_Application::check_token( $token ), 'ok' );
ck( 'and the second use of the same token is not', WPCPM_Institution_Application::check_token( $token ), 'spam' );

reset_world();
arm_nonce();
// Well shaped (three parts), so the refusal is the signature's, not the shape's.
ck( 'a token nobody signed is refused', WPCPM_Institution_Application::check_token( ( time() - 60 ) . '.AbCdEf012345.0123456789abcdef0123456789abcdef' ), 'spam' );
ck( 'and so is one that is not a token at all', WPCPM_Institution_Application::check_token( 'nonsense' ), 'spam' );
ck( 'a token older than half a day is stale, which is not the same as spam', WPCPM_Institution_Application::check_token( dwell_token( 13 * HOUR_IN_SECONDS ) ), 'stale' );

// A token is worth nothing without the nonce it was signed against, which is what stops one
// harvested from a page drawn for somebody else being posted with a form of one's own.
reset_world();
ck( 'a token checked with no nonce in the request is a forgery', WPCPM_Institution_Application::check_token( dwell_token( 30 ) ), 'spam' );

// Bound to the nonce: a token harvested from a form drawn for somebody else does not travel.
reset_world();
$_POST['_wpnonce'] = 'nonce-something-else';
ck( 'a token posted with another form\'s nonce is refused', WPCPM_Institution_Application::check_token( dwell_token( 30 ) ), 'spam' );

reset_world();
$harvested = dwell_token( 60 );
$first     = submit( answers(), array( 'token' => $harvested ) );
$second    = submit( answers( array( 'Name' => 'Second University', 'Contact Email' => 'two@example.edu' ) ), array( 'token' => $harvested ) );
$rows      = stored();

ck( 'a harvested token gets one application through', get_post_meta( $rows[0]->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_NEW );
ck( 'and the replay is held as spam', get_post_meta( $rows[1]->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_SPAM );
ck( 'saying why', in_array( 'dwell', get_post_meta( $rows[1]->ID, WPCPM_Institution_Application::META_SIGNALS, true ), true ), true );
ck( 'the sender of the replay is told nothing about it', array( $first['outcome'], $second['outcome'] ), array( 'sent', 'sent' ) );
ck( 'and only the first was mailed about', count( $GLOBALS['mail'] ), 1 );

reset_world();
$stale = submit( answers(), array( 'token' => dwell_token( 13 * HOUR_IN_SECONDS ) ) );

ck( 'an overnight tab is asked to send again', $stale['outcome'], 'stale' );
ck( 'nothing is stored for it', count( stored() ), 0 );
ck( 'and the writing is handed back', $stale['stash']['values']['Why are you interested in offering WordPress Credits to your students?'], 'Our students need real open source experience before they graduate.' );

// The nonce is the other half of the same courtesy: no 403 death screen, no lost writing.
reset_world();
$expired = submit( answers(), array( 'nonce' => 'nonce-from-last-week' ) );

ck( 'an expired nonce is a message and not a death screen', $expired['outcome'], 'expired' );
ck( 'nothing is stored for it either', count( stored() ), 0 );
ck( 'and the writing is still handed back', $expired['stash']['values']['City'], 'San Jose' );
ck( 'and still without the tick', array_key_exists( 'Privacy Policy Compliance', $expired['stash']['values'] ), false );

echo "\n-- a re-send straight after a bounce (FANON-1) -------------------------\n";

/** The dwell token the drawn page is carrying, exactly as a browser would post it back. */
function token_on_page( $html ) {
	return preg_match( '/name="' . preg_quote( WPCPM_Institution_Application::TOKEN_FIELD, '/' ) . '" value="([^"]+)"/', (string) $html, $found ) ? $found[1] : '';
}

// Every bounce redraws the form with a token minted at the redraw, and the two the applicant
// reaches with one click ("stale" and "expired") both ask them to send it again with every
// answer restored. Pressing Send inside six seconds used to file the application as spam
// behind the ordinary confirmation, which for this form is worse than being lost: the
// acknowledgement carries the link that stamps `_wpcpm_app_verified`, and without it no
// shipped path can ever approve the row.
reset_world();
$bounced = submit( answers(), array( 'token' => dwell_token( 13 * HOUR_IN_SECONDS ) ) );
follow( $bounced['url'] );
$fresh = token_on_page( WPCPM_Institution_Application::render() );

ck( 'the bounce redraws the form with a token minted at the redraw', array( $bounced['outcome'], (int) explode( '.', $fresh )[0] === time() ), array( 'stale', true ) );

$resend = submit( answers(), array( 'token' => $fresh ) );
$row    = only_row();

ck( 'the re-send at age zero is held, not filed as spam', array( $resend['outcome'], get_post_meta( $row->ID, WPCPM_Institution_Application::META_STATE, true ), get_post_meta( $row->ID, WPCPM_Institution_Application::META_SIGNALS, true ) ), array( 'sent', WPCPM_Institution_Application::STATE_HELD, array( 'dwell-fast' ) ) );
ck( 'so it reaches the queue as one pending row', array( count( stored() ), WPCPM_Institution_Application::pending_count() ), array( 1, 1 ) );
ck( 'and gets the held-row mails: the applicant is acknowledged, the managers are spared', array( count( $GLOBALS['mail'] ), mail_said( -1, 'to' ), count( $GLOBALS['managermail'] ) ), array( 1, 'ana@example.edu', 0 ) );
ck( 'with the link that makes the row approvable at all', false !== strpos( mail_said( -1, 'body' ), 'action=wpcpm_apply_verify' ), true );

// A young token nobody signed here is still spam: the signature is what tells a re-sending
// applicant apart from a script posting a token it made up.
reset_world();
submit( answers(), array( 'token' => time() . '.AbCdEf012345.0123456789abcdef0123456789abcdef' ) );
ck( 'a young token nobody signed is spam, as it always was', array( get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_STATE, true ), get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_SIGNALS, true ) ), array( WPCPM_Institution_Application::STATE_SPAM, array( 'dwell' ) ) );

echo "\n-- the two ceilings, which do different things ------------------------\n";

reset_world();

for ( $i = 1; $i <= 5; $i++ ) {
	submit( answers( array( 'Name' => 'University ' . $i, 'Contact Email' => 'a' . $i . '@example.edu' ) ) );
}

ck( 'five in an hour from one source are taken', count( stored() ), 5 );

$sixth = submit( answers( array( 'Name' => 'University 6', 'Contact Email' => 'a6@example.edu' ) ) );

ck( 'the sixth is refused outright', $sixth['outcome'], 'busy' );
ck( 'and stores nothing at all: this is the one layer that does not keep the row', count( stored() ), 5 );

// `busy` has nothing to hand back, so it says so in the address bar and writes nothing. Five
// transients, one for each of the five that were taken, and not a sixth.
ck( 'and writes nothing either: the refusal travels as itself', false !== strpos( $sixth['url'], 'wpcpm_app_said=busy' ), true );
ck( 'so a source that keeps posting stops costing the options table anything', count( $GLOBALS['transients'] ), 5 );

$elsewhere = submit(
	array_merge( answers( array( 'Name' => 'Somewhere Else', 'Contact Email' => 'else@example.edu' ) ) ),
	array( 'ip' => '198.51.100.9' )
);

ck( 'and another source is untouched by it', $elsewhere['outcome'], 'sent' );

reset_world();
$made = 0;

// Eight sources, five each, which is the day's whole site-wide allowance. Posted rather than
// claimed directly, because what is being pinned is what the handler does at the boundary.
for ( $source = 1; $source <= 8; $source++ ) {
	for ( $i = 1; $i <= 5; $i++ ) {
		++$made;
		submit(
			answers( array( 'Name' => 'University ' . $made, 'Contact Email' => 'u' . $made . '@example.edu' ) ),
			array( 'ip' => '198.51.100.' . $source )
		);
	}
}

ck( 'forty applications from eight sources are all taken', count( stored() ), 40 );

$mails_so_far = count( $GLOBALS['mail'] );
$genuine      = submit(
	answers( array( 'Name' => 'Real University', 'Contact Email' => 'real@example.edu' ) ),
	array( 'ip' => '198.51.100.42' )
);
$rows         = stored();
$last         = end( $rows );

ck( 'the form is still open to the next source afterwards', $genuine['outcome'], 'sent' );
ck( 'and its application is kept', count( $rows ), 41 );
ck( 'held rather than refused, which is what "degrades" means', get_post_meta( $last->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_HELD );
ck( 'saying why', in_array( 'site-ceiling', get_post_meta( $last->ID, WPCPM_Institution_Application::META_SIGNALS, true ), true ), true );

// Sparing the managers is what the degrade is for. Sparing the applicant is not: the message
// they get carries the link that confirms the address, and an application whose address nobody
// has confirmed is refused for ever by `WPCPM_Institution_Approval::approve()`. A flood that
// held the one real institution applying that afternoon used to make their row unapprovable.
ck( 'the applicant is written to all the same', count( $GLOBALS['mail'] ), $mails_so_far + 1 );
ck( 'and it is their acknowledgement', mail_said( -1, 'to' ), 'real@example.edu' );
ck( 'carrying the link that makes the row approvable', false !== strpos( mail_said( -1, 'body' ), 'action=wpcpm_apply_verify' ), true );
ck( 'and only the managers are spared, which is what a hold is for', count( $GLOBALS['managermail'] ), 40 );

echo "\n-- nothing unauthenticated writes before the ceiling ------------------\n";

// One number matters here: how many rows a stranger with a script and no account can make the
// site write. A transient is two of them, and every refusal used to open one, ahead of the one
// layer that counts anybody.

reset_world();
$opts_before = count( $GLOBALS['opts'] );
$rubbish     = array();

for ( $i = 0; $i < 100; $i++ ) {
	$rubbish = submit( array(), array( 'nonce' => 'not-a-nonce' ) );
}

ck( 'a hundred posts with a wrong nonce write no transient at all', count( $GLOBALS['transients'] ), 0 );
ck( 'and nothing but the single ceiling row that counted them', count( $GLOBALS['opts'] ) - $opts_before, 1 );
ck( 'nothing is stored either', count( stored() ), 0 );
ck( 'and by then the sender is being refused rather than answered', $rubbish['outcome'], 'busy' );

// The first of those hundred is the one that used to cost two rows: an expired nonce with
// nothing behind it. It says so in the address bar instead.
reset_world();
$empty = submit( array(), array( 'nonce' => 'not-a-nonce' ) );

ck( 'the first one is told the form had been open too long', $empty['outcome'], 'expired' );
ck( 'as a slug and not as a stash', array( $empty['id'], false !== strpos( $empty['url'], 'wpcpm_app_said=expired' ) ), array( '', true ) );
ck( 'so it leaves nothing behind', count( $GLOBALS['transients'] ), 0 );

follow( $empty['url'] );
$page = WPCPM_Institution_Application::render();

ck( 'and the page still says it', false !== strpos( $page, 'had been open for a while' ), true );

// A slug the page has no sentence for is not a way to put text on it.
$_GET = array( WPCPM_Institution_Application::QUERY_OUTCOME => 'approved' );
$page = WPCPM_Institution_Application::render();

ck( 'an outcome nobody defined says nothing', substr_count( $page, 'wpcpm-application__message' ), 0 );
ck( 'and draws the plain form', false !== strpos( $page, '<form' ), true );

// A sender whose writing came with it still gets every word back. That is what a transient is
// for, and five an hour per address is what stops it being a way to fill the options table.
reset_world();
$refused = array();

for ( $i = 1; $i <= 6; $i++ ) {
	$refused = submit( answers(), array( 'nonce' => 'not-a-nonce' ) );
}

ck( 'five attempts with real writing in them are handed back', count( $GLOBALS['transients'] ), 5 );
ck( 'and the sixth is refused before anything is written', $refused['outcome'], 'busy' );
ck( 'the ceiling counts a wrong nonce like any other attempt', count( $GLOBALS['transients'] ), 5 );

// The confirmation link is the other logged-out handler and had the same hole: a GET anybody
// can send in a loop, and three answers that carry nothing.
reset_world();
submit( answers() );
$before = count( $GLOBALS['transients'] );

for ( $i = 0; $i < 20; $i++ ) {
	verify( array( 'app' => 9999, 't' => str_repeat( 'a', 32 ) ) );
}

ck( 'twenty broken confirmation links write nothing', count( $GLOBALS['transients'] ), $before );
ck( 'and still say what is wrong', landed( $GLOBALS['landed'] )['outcome'], 'verify-failed' );

echo "\n-- requiredness, once, after cleaning ---------------------------------\n";

reset_world();
$missing = submit( answers( array( 'Name' => '', 'City' => '   ' ) ) );

ck( 'a missing answer is a message rather than a refusal', $missing['outcome'], 'again' );
ck( 'naming each field that is missing', array( $missing['stash']['problems']['Name'], $missing['stash']['problems']['City'] ), array( 'required', 'required' ) );
ck( 'and storing nothing', count( stored() ), 0 );

reset_world();
$bad = submit( answers( array( 'Contact Email' => 'ana at example dot edu' ) ) );

ck( 'a refused answer keeps its own reason rather than being called missing', $bad['stash']['problems']['Contact Email'], 'bad_email' );

// The one conditional question. Rendered always, required only when "Other" is ticked.
reset_world();
$other = submit(
	answers(
		array(
			'How do your internships or practices typically work?' => array( 'Other (please specify)' ),
			'Comments' => '',
		)
	)
);

ck( '"Other" with nothing said about it is refused', $other['stash']['problems']['Comments'], 'required' );

reset_world();
submit(
	answers(
		array(
			'How do your internships or practices typically work?' => array( 'Other (please specify)' ),
			'Comments' => 'We place students with local nonprofits.',
		)
	)
);

ck( 'and "Other" with a note is taken', count( stored() ), 1 );

reset_world();
submit( answers( array( 'Comments' => '' ) ) );

ck( 'while an empty note without "Other" is nobody\'s business', count( stored() ), 1 );

reset_world();
$none = submit( answers( array( 'How do your internships or practices typically work?' => array() ) ) );

ck( 'the multi-select needs at least one tick', $none['stash']['problems']['How do your internships or practices typically work?'], 'required' );

reset_world();
submit( answers( array( "Anything else you\xE2\x80\x99d like us to know?" => '' ) ) );

ck( 'and the last question is genuinely optional', count( stored() ), 1 );

// The five kept on the site. The role and the languages are required; who signs, their title and
// the page are not, and the title is asked for exactly when somebody is named to sign.
reset_world();
$no_role = submit( answers( array( 'contact_role' => '  ' ) ) );

ck( 'the contact\'s role is required', array( $no_role['outcome'], $no_role['stash']['problems']['contact_role'] ?? null, count( stored() ) ), array( 'again', 'required', 0 ) );

reset_world();
$titled = submit( answers( array( 'signer_title' => '' ) ) );

ck( 'a signer named with no title is refused on the title, and nothing is stored', array( $titled['outcome'], $titled['stash']['problems'] ?? null, count( stored() ) ), array( 'again', array( 'signer_title' => 'required' ), 0 ) );

follow( $titled['url'] );
$again    = WPCPM_Institution_Application::render();
$title_id = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'signer_title' );

ck( 'and the form handed back says so under the title, with the name kept as typed', array(
	false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $title_id . '-problem">Please fill this in.</span>' ),
	false !== strpos( $again, 'value="Signer Example"' ),
), array( true, true ) );

// The other half of the pair, which only a post made by hand or a box left by mistake sends: a
// title with nobody's name beside it is refused on the name, the title handed back as typed, and
// never stored as an answer about nobody.
reset_world();
$untitled = submit( answers( array( 'signer_name' => '', 'signer_title' => 'Dean of the Faculty of Engineering' ) ) );

ck( 'a title with no signer is refused on the signer, and nothing is stored', array( $untitled['outcome'], $untitled['stash']['problems'] ?? null, count( stored() ) ), array( 'again', array( 'signer_name' => 'required' ), 0 ) );

follow( $untitled['url'] );
$again     = WPCPM_Institution_Application::render();
$signer_id = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'signer_name' );

ck( 'and the form handed back says so under the name, with the title kept as typed', array(
	false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $signer_id . '-problem">Please fill this in.</span>' ),
	false !== strpos( $again, 'value="Dean of the Faculty of Engineering"' ),
), array( true, true ) );

reset_world();
submit( answers( array( 'signer_name' => '', 'signer_title' => '', 'credits_page' => '' ) ) );
$row = only_row();

ck( 'no signer, no title and no page is an application all the same', $row instanceof WP_Post, true );
ck( 'with the three kept empty on the site, and the role and the languages as given', $row ? get_post_meta( $row->ID, SITE_META, true ) : null, array(
	'contact_role'      => 'Internship coordinator',
	'student_languages' => array( 'Spanish', 'English' ),
	'signer_name'       => '',
	'signer_title'      => '',
	'credits_page'      => '',
) );

$speaks_key = 'student_languages';
$speaks_id  = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( $speaks_key );

reset_world();
$silent = submit( answers( array( $speaks_key => array() ) ) );

ck( 'no language ticked is refused, and nothing is stored', array( $silent['outcome'], $silent['stash']['problems'][ $speaks_key ] ?? null, count( stored() ) ), array( 'again', 'required', 0 ) );

follow( $silent['url'] );
$again = WPCPM_Institution_Application::render();

ck( 'and the form handed back says Please fill this in. under the boxes, which the problem describes as well as the hint', array(
	false !== strpos( $again, 'role="group" aria-labelledby="' . $speaks_id . '-label" aria-describedby="' . $speaks_id . '-help ' . $speaks_id . '-problem">' ),
	false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $speaks_id . '-problem">Please fill this in.</span>' ),
), array( true, true ) );

reset_world();
$unknown = submit( answers( array( $speaks_key => array( 'Klingon', 'Elvish' ) ) ) );

ck( 'a post ticking only languages not on the list has them dropped, and with nothing left is refused the same way', array( $unknown['stash']['problems'][ $speaks_key ] ?? null, count( stored() ) ), array( 'required', 0 ) );

reset_world();
submit( answers( array( $speaks_key => array( 'Klingon', 'Polish' ) ) ) );

ck( 'and one known language beside an unknown one is stored alone', only_row() ? get_post_meta( only_row()->ID, SITE_META, true )[ $speaks_key ] ?? null : null, array( 'Polish' ) );

reset_world();
$script  = submit( answers( array( 'credits_page' => 'javascript:alert(1)' ) ) );
$page_id = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'credits_page' );

ck( 'a page address with a javascript: scheme is refused, and nothing is stored', array( $script['outcome'], $script['stash']['problems'] ?? null, count( stored() ) ), array( 'again', array( 'credits_page' => 'bad_url' ), 0 ) );

follow( $script['url'] );
$again = WPCPM_Institution_Application::render();

ck( 'and the form handed back says the answer could not be read, the address gone from the box', array(
	false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $page_id . '-problem">That answer could not be read. Please try it again.</span>' ),
	strpos( $again, 'javascript:' ),
), array( true, false ) );

// A row filed as spam skips requiredness, so a title with no signer is not refused there; it is
// not kept either, or approving that row once it was put back in the queue would copy a title
// with nobody to hold it. The tampered answers that are cleaned are still dropped on that path.
reset_world();
submit(
	answers(
		array(
			'signer_name'       => '',
			'signer_title'      => 'Dean of the Faculty of Engineering',
			'student_languages' => array( 'Klingon', 'Polish' ),
			'credits_page'      => 'javascript:alert(1)',
		)
	),
	array( 'honeypot' => 'x' )
);
$row = only_row();

ck( 'a spam-filed submission with a title and no signer is stored, as spam', $row ? get_post_meta( $row->ID, WPCPM_Institution_Application::META_STATE, true ) : null, WPCPM_Institution_Application::STATE_SPAM );
ck( 'its site answers keep no title alone, no language off the list and no javascript: page (the page is left out, as an answer that could not be read always is)', $row ? get_post_meta( $row->ID, SITE_META, true ) : null, array(
	'contact_role'      => 'Internship coordinator',
	'student_languages' => array( 'Polish' ),
	'signer_name'       => '',
	'signer_title'      => '',
) );

// A post that leaves the signer's field out altogether is the same: no name, so no title.
reset_world();
submit( answers( array( 'signer_name' => null ) ), array( 'honeypot' => 'x' ) );
$row = only_row();

ck( 'a spam-filed submission that leaves the signer out altogether keeps no title either', $row ? ( get_post_meta( $row->ID, SITE_META, true )['signer_title'] ?? 'absent' ) : null, '' );

reset_world();
submit( answers(), array( 'honeypot' => 'x' ) );
$row = only_row();

ck( 'a spam-filed submission that names a signer keeps the title beside the name', $row ? array( get_post_meta( $row->ID, SITE_META, true )['signer_name'] ?? null, get_post_meta( $row->ID, SITE_META, true )['signer_title'] ?? null ) : null, array( 'Signer Example', 'Dean of the Faculty of Engineering' ) );

// A form handed back for another answer keeps the five as they were given.
reset_world();
$elsewhere = submit( answers( array( 'Name' => '' ) ) );
follow( $elsewhere['url'] );
$again = WPCPM_Institution_Application::render();

ck( 'a form handed back for another answer keeps the role, the languages ticked, the signer, the title and the page', array(
	false !== strpos( $again, 'value="Internship coordinator"' ),
	substr_count( $again, 'name="wpcpm_application[' . WPCPM_Institution_Application::form_key( $speaks_key ) . '][]" value="Spanish" checked="checked"' ) + substr_count( $again, 'name="wpcpm_application[' . WPCPM_Institution_Application::form_key( $speaks_key ) . '][]" value="English" checked="checked"' ),
	substr_count( $again, 'name="wpcpm_application[' . WPCPM_Institution_Application::form_key( $speaks_key ) . '][]" value="Polish" checked="checked"' ),
	false !== strpos( $again, 'value="Signer Example"' ),
	false !== strpos( $again, 'value="Dean of the Faculty of Engineering"' ),
	false !== strpos( $again, 'value="https://example.edu/credits"' ),
), array( true, 2, 0, true, true, true ) );

echo "\n-- duplicates are flagged and never merged ----------------------------\n";

reset_world();
submit( answers() );
submit( answers( array( 'Name' => 'A Different Name' ) ) );
$rows = stored();

ck( 'both rows are kept', count( $rows ), 2 );
ck( 'the first is not flagged', get_post_meta( $rows[0]->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array() );
ck( 'the second is, on the address', get_post_meta( $rows[1]->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array( 'duplicate' ) );

// A flag is not a hold: an institution whose published address a stranger used first must not
// end up worse off than one nobody targeted.
ck( 'and it is still a new application, not a held one', get_post_meta( $rows[1]->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_NEW );
ck( 'the managers hear about both', count( $GLOBALS['managermail'] ), 2 );

reset_world();
submit( answers() );
submit( answers( array( 'Contact Email' => 'someone.else@example.edu' ) ) );
$rows = stored();

ck( 'the same name under another address is flagged too', get_post_meta( $rows[1]->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array( 'duplicate' ) );

reset_world();
submit( answers() );
$rows = stored();
update_post_meta( $rows[0]->ID, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_APPROVED );
submit( answers() );
$rows = stored();

ck( 'a settled application is not something a new one duplicates', get_post_meta( $rows[1]->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array() );

echo "\n-- content scoring holds and never refuses ----------------------------\n";

reset_world();
$GLOBALS['disallowed'] = true;
submit( answers() );
$row = only_row();

ck( 'a submission on the disallowed list is stored', $row instanceof WP_Post, true );
ck( 'and held', get_post_meta( $row->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_HELD );
ck( 'saying why', get_post_meta( $row->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array( 'disallowed' ) );
ck( 'the applicant is acknowledged, and only the managers are spared', array( count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( 1, 0 ) );

reset_world();
$GLOBALS['mx'] = false;
submit( answers() );
ck( 'an address at a domain that takes no mail is held', get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array( 'no-mx' ) );

reset_world();
submit( answers( array( 'Why are you interested in offering WordPress Credits to your students?' => 'we want it' ) ) );
ck( 'a one-line reason is held', get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array( 'short' ) );

reset_world();
submit( answers( array( 'Contact Person' => 'Universidad Example' ) ) );
ck( 'an institution named after its own contact is held', get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_SIGNALS, true ), array( 'name-is-contact' ) );

reset_world();
$link = 'Visit https://one.example https://two.example https://three.example for more.';
submit(
	answers(
		array(
			'Why are you interested in offering WordPress Credits to your students?' => $link,
			"Anything else you\xE2\x80\x99d like us to know?"                        => $link . ' And more.',
		)
	)
);
ck( 'a submission full of links is held', in_array( 'links', get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_SIGNALS, true ), true ), true );

reset_world();
$same = 'We would like our students to contribute to WordPress this semester.';
submit(
	answers(
		array(
			'Why are you interested in offering WordPress Credits to your students?' => $same,
			"Anything else you\xE2\x80\x99d like us to know?"                        => $same,
		)
	)
);
ck( 'and one paragraph pasted into two boxes is held', in_array( 'identical', get_post_meta( only_row()->ID, WPCPM_Institution_Application::META_SIGNALS, true ), true ), true );

echo "\n-- confirming the address ---------------------------------------------\n";

/**
 * Follow a verification link and answer with the outcome it lands on.
 *
 * The redirect itself is kept in `$GLOBALS['landed']`, so a test that wants to see what the
 * page then draws can `follow()` it rather than guessing which shape it took.
 */
function verify( $args ) {
	$_GET = (array) $args;
	$url  = '';

	try {
		WPCPM_Institution_Application::handle_verify();
	} catch ( Exception $e ) {
		$url = str_replace( 'redirect: ', '', $e->getMessage() );
	}

	$GLOBALS['landed'] = $url;

	return landed( $url )['outcome'];
}

reset_world();
submit( answers() );
$row   = only_row();
$link  = WPCPM_Institution_Application::verify_url( $row->ID );
$parts = array();
preg_match( '/app=(\d+)&t=([0-9a-f]+)/', $link, $parts );

ck( 'the link goes to the nopriv handler', false !== strpos( $link, 'action=wpcpm_apply_verify' ), true );
ck( 'and is signed', 1, preg_match( '/^[0-9a-f]{32}$/', $parts[2] ) );

ck( 'a wrong signature is refused', verify( array( 'app' => $row->ID, 't' => str_repeat( 'a', 32 ) ) ), 'verify-failed' );
ck( 'and nothing is stamped', get_post_meta( $row->ID, WPCPM_Institution_Application::META_VERIFIED, true ), '' );
ck( 'an application nobody has is refused the same way', verify( array( 'app' => 9999, 't' => $parts[2] ) ), 'verify-failed' );

// The page the applicant is standing on is not this plugin's post type, and is the one thing
// a stranger walking the IDs would otherwise be able to tell apart from an application.
ck( 'and so is a post of some other type', verify( array( 'app' => 42, 't' => $parts[2] ) ), 'verify-failed' );

ck( 'the right link confirms the address', verify( array( 'app' => $parts[1], 't' => $parts[2] ) ), 'verified' );

$stamp = get_post_meta( $row->ID, WPCPM_Institution_Application::META_VERIFIED, true );

ck( 'and stamps when', is_int( $stamp ) && $stamp > 0, true );
ck( 'with an event row to say so', count( get_post_meta( $row->ID, WPCPM_Institution_Application::META_EVENT ) ), 2 );

ck( 'a second visit says it is already done', verify( array( 'app' => $parts[1], 't' => $parts[2] ) ), 'verified-already' );
ck( 'and does not move the stamp', get_post_meta( $row->ID, WPCPM_Institution_Application::META_VERIFIED, true ), $stamp );

echo "\n-- a held row is acknowledged, and only the managers are spared -------\n";

// The bug this pins. Both mails were gated on `new` together, so a held row never carried the
// verification link, `_wpcpm_app_verified` was never stamped, and
// `WPCPM_Institution_Approval::approve()` - which accepts `held` and then refuses outright on
// an unconfirmed address - could never approve it. Held meant unapprovable for ever, and both
// the site-wide degrade and every content signal produce held.

reset_world();
$GLOBALS['disallowed'] = true;
submit( answers() );
$GLOBALS['disallowed'] = false;
$held                  = only_row();

ck( 'the row is held', get_post_meta( $held->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_HELD );
ck( 'the applicant is written to all the same', array( count( $GLOBALS['mail'] ), mail_said( 0, 'to' ) ), array( 1, 'ana@example.edu' ) );
ck( 'through the same exit and in the same words as any other application', mail_said( 0, 'context' ), 'institution-applied' );
ck( 'nothing in it says the row was held', array( strpos( mail_said( 0, 'subject' ), 'held' ), strpos( mail_said( 0, 'body' ), 'held' ) ), array( false, false ) );
ck( 'and the managers are not paged, which is the whole of what a hold buys', count( $GLOBALS['managermail'] ), 0 );

// Why it has to be sent: that link is the only thing that stamps the row, and an unstamped row
// is one no shipped path can approve.
$link  = WPCPM_Institution_Application::verify_url( $held->ID );
$parts = array();
preg_match( '/app=(\d+)&t=([0-9a-f]+)/', $link, $parts );

ck( 'the link in the message is this row\'s own', false !== strpos( mail_said( 0, 'body' ), $link ), true );
ck( 'following it confirms the address', verify( array( 'app' => $parts[1], 't' => $parts[2] ) ), 'verified' );
ck( 'so a held application ends up carrying the stamp approval demands', is_int( get_post_meta( $held->ID, WPCPM_Institution_Application::META_VERIFIED, true ) ), true );

// Spam is the one hold that stays silent in both directions: the address is forged or a probe,
// so a reply is wrong, and the sender learns nothing about which attempt was recognised.
reset_world();
$quiet = submit( answers(), array( 'honeypot' => 'x' ) );

ck( 'a spam row sends nothing to anybody', array( count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( 0, 0 ) );
ck( 'and its sender is shown what everybody else is shown', $quiet['outcome'], 'sent' );

echo "\n-- an answer is drawn even when the form cannot be --------------------\n";

// `handle_verify()` is a different handler with different rules: the link in an applicant's
// mail keeps working after a manager switches the form off, because the stamp it writes is what
// makes the application approvable at all. While the three refusals in `render()` returned
// above `render_message()`, somebody following it that afternoon was shown an empty page.

reset_world();
submit( answers() );
$row   = only_row();
$parts = array();
preg_match( '/app=(\d+)&t=([0-9a-f]+)/', WPCPM_Institution_Application::verify_url( $row->ID ), $parts );

$GLOBALS['settings']['applications_enabled'] = false;

ck( 'the confirmation link still works with the form switched off', verify( array( 'app' => $parts[1], 't' => $parts[2] ) ), 'verified' );

follow( $GLOBALS['landed'] );
$page = WPCPM_Institution_Application::render();

ck( 'and the applicant is told their address is confirmed', false !== strpos( $page, 'address is confirmed' ), true );
// The closed sentence stands in the form's place, and a confirmation draws no form: an applicant
// whose application is with an Administrator is not told the program takes none.
ck( 'and not that the program is taking no applications, since no form would be drawn here anyway', strpos( $page, 'not taking applications' ), false );
ck( 'without a form they could not use', strpos( $page, '<form' ), false );
ck( 'and without the sentence that is only a manager\'s business', strpos( $page, 'switched off' ), false );

$GLOBALS['caps'] = true;
$page            = WPCPM_Institution_Application::render();

ck( 'a manager on the same page sees the answer and the reason', array( false !== strpos( $page, 'address is confirmed' ), false !== strpos( $page, 'switched off' ) ), array( true, true ) );

// The other half of the same bug, and the sharper one: `closed` is stashed only when the form
// is off, which was exactly the state the first refusal returned in. The one sentence about the
// form being off could never be read by anybody.
reset_world();
$GLOBALS['settings']['applications_enabled'] = false;
$replay = submit( answers() );

ck( 'a form posted after it was switched off is told so', $replay['outcome'], 'closed' );

follow( $replay['url'] );
$page = WPCPM_Institution_Application::render();

ck( 'and the sentence can now be read', false !== strpos( $page, 'not taking applications' ), true );
ck( 'once, though the form is off as well', substr_count( $page, 'not taking applications' ), 1 );
ck( 'with no form under it', strpos( $page, '<form' ), false );

// A stranger who simply walks onto the page while the form is off reads the one sentence (1.123.26).
// Silence is still silence where there is nothing to say: with the countries list empty instead,
// nothing to say and nothing to draw is the empty string and not an empty wrapper, which is the
// same silence with markup in it.
$_GET = array();

ck( 'a stranger who simply walks onto the page while the form is off reads that applications are closed', false !== strpos( WPCPM_Institution_Application::render(), 'not taking applications' ), true );

$GLOBALS['settings']['applications_enabled'] = true;
unset( $GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] );

ck( 'and with the form on and no countries to offer, sees nothing at all', WPCPM_Institution_Application::render(), '' );

echo "\n-- when storage itself fails -----------------------------------------\n";

// The one branch nothing else in this suite reaches: `wp_insert_post()` refusing. It is
// contract behaviour rather than an accident. The sender is told the truth - nothing saved and
// nothing sent - and gets every word back, because the alternative is an institution retyping
// three paragraphs it has no reason to believe will survive this time either.

reset_world();
$GLOBALS['post_fails'] = true;
$lost                  = submit( answers() );
$GLOBALS['post_fails'] = false;

ck( 'the sender is told nothing was saved', $lost['outcome'], 'lost' );
ck( 'and nothing was', count( stored() ), 0 );
ck( 'nobody is mailed about a row that does not exist', array( count( $GLOBALS['mail'] ), count( $GLOBALS['managermail'] ) ), array( 0, 0 ) );
ck( 'their writing is handed back', handed_back( $lost )['Why are you interested in offering WordPress Credits to your students?'] ?? '', 'Our students need real open source experience before they graduate.' );
ck( 'still without the tick', array_key_exists( 'Privacy Policy Compliance', handed_back( $lost ) ), false );

follow( $lost['url'] );
$page = WPCPM_Institution_Application::render();

ck( 'and the page says so', false !== strpos( $page, 'went wrong at our end' ), true );
ck( 'above a form drawn again with the answers in it', false !== strpos( $page, 'value="Universidad Example"' ), true );

echo "\n-- the one page that is never cached ----------------------------------\n";

// `no_cache()` runs on `template_redirect` for every request the site takes, so what it does
// on the pages that are not this one matters as much as what it does on the one that is: a
// site\'s caching is not this plugin\'s to turn off. The order below is forced, because
// `DONOTCACHEPAGE` is a define and a define cannot be taken back.

reset_world();
$GLOBALS['on_page'] = 99;

WPCPM_Institution_Application::no_cache();

ck( 'a page that is not the form is left alone', array( $GLOBALS['nocache'], defined( 'DONOTCACHEPAGE' ) ), array( 0, false ) );

$GLOBALS['on_page'] = 42;
unset( $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );

WPCPM_Institution_Application::no_cache();

ck( 'and so is every page on a site that has no application page at all', array( $GLOBALS['nocache'], defined( 'DONOTCACHEPAGE' ) ), array( 0, false ) );

$GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] = 42;

WPCPM_Institution_Application::no_cache();

// A cached copy of this page hands one nonce and one single-use dwell token to everybody who
// asks for it: the token is spent by the first of them and refused for all the rest.
ck( 'the form\'s own page sends the no-cache headers', $GLOBALS['nocache'], 1 );
ck( 'and says so by the name every page cache on the market reads', defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, true );

$GLOBALS['on_page'] = 0;

echo "\n-- the form's address, its title and the address it had before -------\n";

/**
 * Asks `redirect_old_address()` about one request and says where it sent the visitor.
 *
 * @param string $path  The path WordPress matched, as `$wp->request` holds it.
 * @param array  $vars  The query variables the request ended with.
 * @param bool   $is_404 Whether WordPress found nothing there.
 * @param string $query The request's query string, if it had one.
 * @return array|null The address and the status, or null when nothing was sent.
 */
function old_address( $path, array $vars, $is_404 = true, $query = '' ) {
	$GLOBALS['wp']         = (object) array( 'request' => $path );
	$GLOBALS['query_vars'] = $vars;
	$GLOBALS['is_404']     = $is_404;
	$GLOBALS['redirects']  = array();

	if ( '' !== $query ) {
		$_SERVER['QUERY_STRING'] = $query;
	} else {
		unset( $_SERVER['QUERY_STRING'] );
	}

	try {
		WPCPM_Institution_Application::redirect_old_address();
	} catch ( Exception $e ) {
		// The stand-in's redirect, recorded before it was thrown.
		unset( $e );
	}

	return $GLOBALS['redirects'] ? $GLOBALS['redirects'][0] : null;
}

/** The page as live holds it before the move: page 751's slug, title and content. */
function live_page() {
	$page               = get_post( 42 );
	$page->post_name    = WPCPM_Institution_Application::OLD_SLUG;
	$page->post_title   = WPCPM_Institution_Application::OLD_TITLE;
	$page->post_content = '<!-- wp:' . WPCPM_Institution_Application::BLOCK . ' /-->';

	return $page;
}

$intro = 'For universities, colleges and schools that would like to offer WordPress Credits to their students: an Administrator reads every application and writes back, usually within a few working days.';

ck( 'the address names the institutions it is for, and the old one is kept by name', array( WPCPM_Institution_Application::SLUG, WPCPM_Institution_Application::OLD_SLUG ), array( 'institution-application', 'apply' ) );
ck( 'the title says who the form is for', WPCPM_Institution_Application::page_title(), 'Bring WordPress Credits to your institution' );
ck( 'and the old title is the words live holds, untranslated', WPCPM_Institution_Application::OLD_TITLE, 'Apply to the WordPress Credits Program' );
ck( 'the page is the intro line, then the form, each in its block comments', WPCPM_Institution_Application::page_content(), "<!-- wp:paragraph -->\n<p>" . $intro . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:wpcpm/institution-application /-->" );
// The move runs on whichever request comes first after the update, a visitor's among them, and
// a visitor has no `unfiltered_html`: kses runs on that save, so the words must be ones it keeps.
ck( 'kses keeps the page\'s words as they are', wp_kses_post( WPCPM_Institution_Application::page_content() ), WPCPM_Institution_Application::page_content() );
ck( 'and the title\'s', wp_kses_post( WPCPM_Institution_Application::page_title() ), WPCPM_Institution_Application::page_title() );
ck( 'the stand-in for kses is not a pass-through', wp_kses_post( '<!-- a -- b --><p class="x">A & B ></p><script>x</script>' ), '<!-- a - b -->A &amp; B &gt;</p>x' );

// An empty site: activation creates the page at the new address, ungated as before.
reset_world();
unset( $GLOBALS['posts'][42], $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );
$made = WPCPM_Institution_Application::ensure_page();
$page = get_post( $made );

ck( 'ensure_page() on an empty site creates a published page', array( $page instanceof WP_Post, $page ? $page->post_type : '', $page ? $page->post_status : '' ), array( true, 'page', 'publish' ) );
ck( 'at the new address, under the new title, holding the intro and the form', array( $page->post_name, $page->post_title, $page->post_content ), array( 'institution-application', WPCPM_Institution_Application::page_title(), WPCPM_Institution_Application::page_content() ) );
ck( 'and records it', (int) get_option( WPCPM_Institution_Application::OPT_PAGE ), $made );
ck( 'with no meta at all, so no access level gates it', $GLOBALS['pmeta'][ $made ] ?? array(), array() );
ck( 'a second call finds it and makes nothing more', array( WPCPM_Institution_Application::ensure_page(), count( $GLOBALS['posts'] ) ), array( $made, 2 ) );

// The insert is handed slashed copies, as `wp_insert_post()` unslashes what it is given: the exact
// bytes of an English site's title and intro reach the page, and so does a backslash in a
// translation of either, which `maybe_move_page()` already keeps on its way in.
$pages_inserted = static function () {
	return array_values( array_filter( $GLOBALS['inserts'], static function ( $args ) { return 'page' === ( $args['post_type'] ?? '' ); } ) );
};

reset_world();
unset( $GLOBALS['posts'][42], $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );
WPCPM_Institution_Application::ensure_page();
$handed = $pages_inserted();

ck( 'an English site\'s title and content are handed over byte for byte, there being nothing in them to slash', array( count( $handed ), $handed[0]['post_title'] ?? null, $handed[0]['post_content'] ?? null ), array( 1, 'Bring WordPress Credits to your institution', WPCPM_Institution_Application::page_content() ) );

reset_world();
unset( $GLOBALS['posts'][42], $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );
$GLOBALS['translations']['Bring WordPress Credits to your institution'] = 'Bring WordPress Credits to your institution \\ school';
$made   = WPCPM_Institution_Application::ensure_page();
$handed = $pages_inserted();

ck( 'a translated title with a backslash is handed over slashed', $handed[0]['post_title'] ?? null, 'Bring WordPress Credits to your institution \\\\ school' );
ck( 'so the backslash is still there on the page that was made', get_post( $made )->post_title, 'Bring WordPress Credits to your institution \\ school' );

reset_world();
unset( $GLOBALS['posts'][42], $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );
$GLOBALS['translations']['For universities, colleges and schools that would like to offer WordPress Credits to their students: an Administrator reads every application and writes back, usually within a few working days.'] = 'An intro with a \\ backslash and "quotes" and an apostrophe\'s.';
$made   = WPCPM_Institution_Application::ensure_page();
$handed = $pages_inserted();

ck( 'a translated intro with a backslash is handed over slashed', false !== strpos( (string) ( $handed[0]['post_content'] ?? '' ), 'An intro with a \\\\ backslash and &quot;quotes&quot; and an apostrophe&#039;s.' ), true );
ck( 'so the backslash is still there on the page that was made, with its quotes escaped as they were', false !== strpos( get_post( $made )->post_content, 'An intro with a \\ backslash and &quot;quotes&quot; and an apostrophe&#039;s.' ), true );
ck( 'and the slug and the status are handed over as they are', array( $handed[0]['post_name'] ?? null, $handed[0]['post_status'] ?? null, $handed[0]['post_type'] ?? null ), array( 'institution-application', 'publish', 'page' ) );

// A site restored from a backup, or migrated, that has the page and not the option.
reset_world();
unset( $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );

ck( 'a page already at the new address is adopted rather than a second made', array( WPCPM_Institution_Application::ensure_page(), (int) get_option( WPCPM_Institution_Application::OPT_PAGE ), count( $GLOBALS['posts'] ) ), array( 42, 42, 2 ) );

reset_world();
unset( $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );
live_page();

ck( 'and so is one still at the old address, from a backup taken before the move', array( WPCPM_Institution_Application::ensure_page(), (int) get_option( WPCPM_Institution_Application::OPT_PAGE ), count( $GLOBALS['posts'] ) ), array( 42, 42, 2 ) );

// Live: the recorded page still has the slug, the title and the content the plugin gave it.
reset_world();
live_page();
WPCPM_Institution_Application::maybe_move_page();

ck( 'the move changes all three in one update', count( $GLOBALS['updates'] ), 1 );
ck( 'naming the page and the three new values', $GLOBALS['updates'][0]['args'], array(
	'ID'           => 42,
	'post_name'    => 'institution-application',
	'post_title'   => WPCPM_Institution_Application::page_title(),
	'post_content' => WPCPM_Institution_Application::page_content(),
) );
ck( 'with the flag written before the work, so a move that fails halfway is not tried on every request', $GLOBALS['updates'][0]['stamped'], WPCPM_Institution_Application::MOVE_VERSION );
ck( 'the flag is the move\'s revision, and not autoloaded', array( get_option( WPCPM_Institution_Application::OPT_MOVED ), $GLOBALS['autoload'][ WPCPM_Institution_Application::OPT_MOVED ] ), array( 1, false ) );
ck( 'and the page now answers at the new address', get_post( 42 )->post_name, 'institution-application' );

// Once only: a slug, title or content put back by hand afterwards is an Administrator's.
live_page();
$GLOBALS['updates'] = array();
WPCPM_Institution_Application::maybe_move_page();

ck( 'a second run does nothing', $GLOBALS['updates'], array() );

// A field an Administrator changed by hand is theirs, and only what is still the plugin's moves.
reset_world();
live_page()->post_title = 'Partner with WordPress Credits';
WPCPM_Institution_Application::maybe_move_page();

ck( 'a title changed by hand is kept, and the slug and the content still move', $GLOBALS['updates'][0]['args'], array(
	'ID'           => 42,
	'post_name'    => 'institution-application',
	'post_content' => WPCPM_Institution_Application::page_content(),
) );

reset_world();
live_page()->post_content = "<!-- wp:paragraph -->\n<p>Our own words.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:wpcpm/institution-application /-->";
WPCPM_Institution_Application::maybe_move_page();

ck( 'content changed by hand is kept, and the slug and the title still move', $GLOBALS['updates'][0]['args'], array(
	'ID'         => 42,
	'post_name'  => 'institution-application',
	'post_title' => WPCPM_Institution_Application::page_title(),
) );

reset_world();
live_page()->post_name = 'join';
get_post( 42 )->post_title   = 'Join us';
get_post( 42 )->post_content = 'Our own words.';
WPCPM_Institution_Application::maybe_move_page();

ck( 'a page changed by hand in all three is not touched at all', $GLOBALS['updates'], array() );
ck( 'and the move is still stamped as done', get_option( WPCPM_Institution_Application::OPT_MOVED ), 1 );

// Another post already at the new address keeps it: core would hand the page
// `institution-application-2`, or, for a post, hide it behind the page.
foreach ( array( 'a page' => 'page', 'a post' => 'post', 'an attachment' => 'attachment' ) as $kind => $type ) {
	reset_world();
	live_page();
	$other              = new WP_Post();
	$other->ID          = 77;
	$other->post_type   = $type;
	$other->post_status = 'publish';
	$other->post_name   = 'institution-application';

	$GLOBALS['posts'][77] = $other;
	WPCPM_Institution_Application::maybe_move_page();

	ck( 'with ' . $kind . ' already at the new address the slug is left, and the title and the content move', array( $GLOBALS['updates'][0]['args'] ?? null, get_post( 42 )->post_name ), array(
		array(
			'ID'           => 42,
			'post_title'   => WPCPM_Institution_Application::page_title(),
			'post_content' => WPCPM_Institution_Application::page_content(),
		),
		'apply',
	) );
	ck( 'and the move is stamped all the same', get_option( WPCPM_Institution_Application::OPT_MOVED ), 1 );
}

reset_world();
unset( $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );
WPCPM_Institution_Application::maybe_move_page();

ck( 'a site with no recorded page has nothing moved, and is stamped', array( $GLOBALS['updates'], get_option( WPCPM_Institution_Application::OPT_MOVED ) ), array( array(), 1 ) );

// The update is handed slashed copies, as `wp_insert_post()` unslashes what it is given: a
// backslash in a translation of the title or the intro survives the move.
reset_world();
live_page();
$GLOBALS['translations']['Bring WordPress Credits to your institution'] = 'Bring WordPress Credits to your institution \\ school';
WPCPM_Institution_Application::maybe_move_page();

ck( 'the update is handed slashed copies', $GLOBALS['updates'][0]['args']['post_title'], 'Bring WordPress Credits to your institution \\\\ school' );
ck( 'so a backslash in the translated title is still there after the move', get_post( 42 )->post_title, 'Bring WordPress Credits to your institution \\ school' );

reset_world();
live_page();
$GLOBALS['translations']['For universities, colleges and schools that would like to offer WordPress Credits to their students: an Administrator reads every application and writes back, usually within a few working days.'] = 'An intro with a \\ backslash and "quotes" and an apostrophe\'s.';
WPCPM_Institution_Application::maybe_move_page();

ck( 'and so is one in the intro line, with its quotes', strpos( get_post( 42 )->post_content, 'An intro with a \\ backslash and &quot;quotes&quot; and an apostrophe&#039;s.' ) !== false, true );

// The old address: a 404 there sends the visitor to the form, and nothing else is answered.
reset_world();

ck( 'a 404 at /apply/ that WordPress read as a page sends a 301 to the form', old_address( 'apply', array( 'pagename' => 'apply' ) ), array( 'https://example.test/institution-application/', 301 ) );
// Under /%postname%/ links a missing page falls through to the post rule, which sets `name`.
ck( 'and so does one it read as a post', old_address( 'apply', array( 'name' => 'apply' ) ), array( 'https://example.test/institution-application/', 301 ) );
// A visitor mid-redirect at deploy time carries the outcome or the stash id.
ck( 'the query string goes with the visitor', old_address( 'apply', array( 'name' => 'apply' ), true, 'wpcpm_app_said=closed' ), array( 'https://example.test/institution-application/?wpcpm_app_said=closed', 301 ) );
ck( 'every part of it', old_address( 'apply', array( 'name' => 'apply' ), true, 'wpcpm_app=AbCd0123&utm_source=slides' ), array( 'https://example.test/institution-application/?wpcpm_app=AbCd0123&utm_source=slides', 301 ) );
// A real page published at /apply/ later is not a 404, so it wins.
ck( 'a page that is there is not a 404, and is left alone', old_address( 'apply', array( 'pagename' => 'apply' ), false ), null );
ck( 'any other path is left alone', old_address( 'about', array( 'pagename' => 'about' ) ), null );
ck( 'and so is a slug that starts with the old one', old_address( 'apply-now', array( 'name' => 'apply-now' ) ), null );
// By `template_redirect` WP_Query has cut a missing page's path to its last part and copied it
// into `name`, and a post type's address sets `name` too: the query variables alone would send
// /about/apply/ and /<type>/apply/ to the form.
ck( 'a deeper path whose last part is the old slug is left alone', old_address( 'about/apply', array( 'pagename' => 'apply', 'name' => 'apply' ) ), null );
ck( 'and so is a post type\'s address that ends in it', old_address( 'sponsor-posts/apply', array( 'name' => 'apply', 'post_type' => 'wpcpm_sponsor_post' ) ), null );
ck( 'and so is the old path where WordPress read something else there', old_address( 'apply', array( 'category_name' => 'apply' ) ), null );

// The Institutions module's comment about who can reach the form names the address it has, and
// says the old one redirects, rather than naming the one it had.
$module_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );

ck( 'the queue ceiling\'s comment names the form\'s address and says the old one redirects',
	array( false !== strpos( $module_source, '`/institution-application/`' ), false !== strpos( $module_source, '/apply/` redirects' ), false === strpos( $module_source, '`/apply` is open' ) ),
	array( true, true, true ) );

// Page addresses are matched without regard to case, so /Apply/ served the form before the move; the
// path is read as the visitor typed it, and it is compared as lower case, which widens nothing: it
// is still the one path.
ck( '/Apply/ typed with a capital is sent to the form too', old_address( 'Apply', array( 'pagename' => 'Apply' ) ), array( 'https://example.test/institution-application/', 301 ) );
ck( 'and /APPLY/, read as a post', old_address( 'APPLY', array( 'name' => 'APPLY' ) ), array( 'https://example.test/institution-application/', 301 ) );
ck( 'with its query string', old_address( 'Apply', array( 'pagename' => 'Apply' ), true, 'wpcpm_app_said=closed' ), array( 'https://example.test/institution-application/?wpcpm_app_said=closed', 301 ) );
ck( 'a page that is there at /Apply/ is not a 404, and is left alone', old_address( 'Apply', array( 'pagename' => 'Apply' ), false ), null );
ck( 'a deeper path ending in a capitalized old slug is left alone', old_address( 'about/Apply', array( 'pagename' => 'Apply', 'name' => 'Apply' ) ), null );
ck( 'and so is a post type\'s address that ends in it', old_address( 'sponsor-posts/Apply', array( 'name' => 'Apply', 'post_type' => 'wpcpm_sponsor_post' ) ), null );
ck( 'a longer slug in capitals is left alone', old_address( 'Apply-Now', array( 'name' => 'Apply-Now' ) ), null );
ck( 'and a variable that is not a string is not read as the slug', old_address( 'apply', array( 'pagename' => array( 'apply' ) ) ), null );

get_post( 42 )->post_status = 'draft';
ck( 'with the form\'s page not published there is nowhere to send anybody', old_address( 'apply', array( 'pagename' => 'apply' ) ), null );

unset( $GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] );
ck( 'nor on a site with no form page at all', old_address( 'apply', array( 'pagename' => 'apply' ) ), null );

// Before core's `redirect_canonical()` and `wp_old_slug_redirect()`, both at 10, so core's guess
// at a post whose slug starts with the old one cannot send the visitor somewhere else; and the
// move after `register`, at 20, the place every dashboard's one-time rename has.
reset_world();
WPCPM_Institution_Application::init();
$hooked = array();

foreach ( $GLOBALS['hooks'] as $hook ) {
	if ( is_array( $hook[1] ) && in_array( $hook[1][1], array( 'redirect_old_address', 'maybe_move_page' ), true ) ) {
		$hooked[ $hook[1][1] ] = array( $hook[0], $hook[2] );
	}
}

ck( 'the redirect is on template_redirect at 9, and the move on init at 20', $hooked, array(
	'maybe_move_page'      => array( 'init', 20 ),
	'redirect_old_address' => array( 'template_redirect', 9 ),
) );
$GLOBALS['hooks'] = array();

echo "\n-- which address the request came from --------------------------------\n";

reset_world();
$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.5, 10.0.0.1';

ck( 'a forwarded header from nowhere in particular is ignored', WPCPM_Institution_Application::client_ip(), '203.0.113.7' );

$GLOBALS['settings']['application_trusted_proxy'] = '192.0.2.1';
ck( 'and so is one from an address that is not the known edge', WPCPM_Institution_Application::client_ip(), '203.0.113.7' );

// The edge appends the address it saw, so its own word is the RIGHTMOST entry and the
// leftmost is whatever the client wrote. Believing the left end let an applicant pick their
// own limiter bucket and spam the form once a proxy was configured.
$GLOBALS['settings']['application_trusted_proxy'] = '203.0.113.7';
ck( 'the known edge is believed, and its own entry is the rightmost', WPCPM_Institution_Application::client_ip(), '10.0.0.1' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 10.0.0.1';
ck( 'so an address the client wrote in front of it changes nothing', WPCPM_Institution_Application::client_ip(), '10.0.0.1' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 203.0.113.7';
ck( 'the edge itself, appearing in the chain, is skipped for the entry before it', WPCPM_Institution_Application::client_ip(), '10.0.0.1' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.5, not an address';
ck( 'an entry that is not an address is skipped for the next', WPCPM_Institution_Application::client_ip(), '198.51.100.5' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not an address at all';
ck( 'a header the edge sent that is not an address falls back', WPCPM_Institution_Application::client_ip(), '203.0.113.7' );

$_SERVER['REMOTE_ADDR'] = 'nonsense';
ck( 'and a connection with no address at all answers empty', WPCPM_Institution_Application::client_ip(), '' );

echo "\n-- the queue's two readers -------------------------------------------\n";

reset_world();
submit( answers( array( 'Name' => 'One', 'Contact Email' => 'one@example.edu' ) ) );
submit( answers( array( 'Name' => 'Two', 'Contact Email' => 'two@example.edu' ) ), array( 'honeypot' => 'x' ) );
submit( answers( array( 'Name' => 'Three', 'Contact Email' => 'three@example.edu' ) ) );

$rows = stored();
update_post_meta( $rows[2]->ID, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_INFO );

ck( 'the pending count is what is waiting for somebody', WPCPM_Institution_Application::pending_count(), 2 );
ck( 'spam is not waiting for anybody', count( WPCPM_Institution_Application::applications( array( WPCPM_Institution_Application::STATE_SPAM ) ) ), 1 );

$queue = WPCPM_Institution_Application::applications( array( WPCPM_Institution_Application::STATE_NEW, WPCPM_Institution_Application::STATE_INFO ) );

ck( 'the queue is oldest first', array( $queue[0]->post_title, $queue[1]->post_title ), array( 'One', 'Three' ) );

// Two applications stored in the same second. The queue ordered by `date` alone, which was
// nobody's problem while the caller read every row and sliced in PHP; now that the window is
// taken in SQL, which of a tied pair falls inside it is the database's own preference - the
// defect P1 was raised for, on this reader (deep check, fix round). A public form really can
// take two submissions in one second, and what follows from it is a queue that reorders
// itself between loads.
$tied_states = array( WPCPM_Institution_Application::STATE_NEW, WPCPM_Institution_Application::STATE_INFO );
$GLOBALS['posts'][ $rows[0]->ID ]->post_date = '2026-08-01 09:00:00';
$GLOBALS['posts'][ $rows[2]->ID ]->post_date = '2026-08-01 09:00:00';
$tied_queue                                  = WPCPM_Institution_Application::applications( $tied_states );

ck( 'two rows stored in the same second are ordered by ID, so a window keeps the same one every time', array( $tied_queue[0]->post_title, $tied_queue[1]->post_title, WPCPM_Institution_Application::applications( $tied_states, 1 )[0]->post_title ), array( 'One', 'Three', 'One' ) );
ck( 'a caller that asks for no states gets no rows, never every row', WPCPM_Institution_Application::applications( array() ), array() );
ck( 'and a state nobody has heard of is dropped rather than queried', WPCPM_Institution_Application::applications( array( 'wide-open' ) ), array() );

// Post meta goes with its post, the site-only answers' key included, which is why `delete_all()`
// names no meta key: three rows hold them before, and none after.
$with_site_answers = static function () {
	return count( array_filter( $GLOBALS['pmeta'], static function ( $meta ) { return isset( $meta[ SITE_META ] ); } ) );
};

ck( 'before uninstall, the three applications hold their site-only answers', $with_site_answers(), 3 );

WPCPM_Institution_Application::delete_all();
ck( 'uninstall takes every application with it', count( stored() ), 0 );
ck( 'and every one\'s site-only answers', $with_site_answers(), 0 );

echo "\n-- read off the source ------------------------------------------------\n";

$submit = method_body( $src, 'handle_submit' );

// The order is the design, so it is asserted as an order: a reader changing this method has
// the sequence in front of them either way.
// The per-actor ceiling leads it, and that is the part worth reading twice: it is the only
// layer here that counts a writer with no account, and everything below it can write.
$offsets = array(
	'actor'    => strpos( $submit, 'self::actor_key()' ),
	'nonce'    => strpos( $submit, 'wp_verify_nonce(' ),
	'closed'   => strpos( $submit, 'self::is_open()' ),
	'honeypot' => strpos( $submit, 'self::HONEYPOT' ),
	'dwell'    => strpos( $submit, 'self::check_token(' ),
	'consent'  => strpos( $submit, "self::form_key( 'Privacy Policy Compliance' )" ),
	'fields'   => strpos( $submit, '$cleaned  = self::clean_all(' ),
	'required' => strpos( $submit, 'self::add_required(' ),
	'scoring'  => strpos( $submit, 'self::score(' ),
	'site'     => strpos( $submit, "'apply-site'" ),
	'store'    => strpos( $submit, 'self::store(' ),
	'mail'     => strpos( $submit, 'self::mail_applicant(' ),
);
$sorted  = $offsets;
asort( $sorted );

ck( 'the submit path reads in the order the design fixes', array_keys( $sorted ), array_keys( $offsets ) );

// `check_admin_referer()` dies with a 403 screen and three paragraphs gone. This path must
// never reach for it. Read off the code with the comments taken out, the way
// `bin/check-references.php` reads its own second pass, because the docblock that explains
// the rule names the function it is about.
$code = array();

foreach ( explode( "\n", $src ) as $line ) {
	$trimmed = ltrim( $line );

	if ( '' === $trimmed || 0 === strpos( $trimmed, '*' ) || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '/*' ) ) {
		continue;
	}

	$code[] = $line;
}

$code = implode( "\n", $code );

ck( 'nothing on the public path calls check_admin_referer()', strpos( $code, 'check_admin_referer' ), false );
ck( 'and the nonce is verified rather than asserted', substr_count( $code, 'wp_verify_nonce(' ), 1 );
ck( 'and the submit handler reads no query string', substr_count( $submit, 'WPCPM_Request::text(' ) + substr_count( $submit, 'WPCPM_Request::key(' ) + substr_count( $submit, 'WPCPM_Request::id(' ), 0 );

// Every slug a handler can stash has a sentence to go with it, or the page would visibly have
// done something and said nothing about it.
$slugs = array();
preg_match_all( "/self::bounce\(\s*'([a-z-]+)'/", $src, $found );

foreach ( $found[1] as $slug ) {
	if ( 'sent' !== $slug && ! isset( WPCPM_Institution_Application::outcomes()[ $slug ] ) ) {
		$slugs[] = $slug;
	}
}

ck( 'every outcome the handlers stash has a sentence', $slugs, array() );

// The confirmation is the one outcome with no sentence in that map, because it is a panel of
// its own rather than a line above the form.
ck( 'and "sent" is drawn as a panel instead', isset( WPCPM_Institution_Application::outcomes()['sent'] ), false );

// Nothing this class writes is autoloaded: the ceiling rows and the page ID are read on the
// two requests that want them and on none of the others.
reset_world();
WPCPM_Institution_Application::ensure_page();

$autoloaded = array();

foreach ( $GLOBALS['autoload'] as $name => $autoload ) {
	if ( 0 === strpos( $name, 'wpcpm_' ) && false !== $autoload ) {
		$autoloaded[] = $name;
	}
}

ck( 'no option this class writes is autoloaded', $autoloaded, array() );

echo "\n=== The acknowledgement says the link is needed ===\n";

reset_world();
$applied = submit( answers() );

ck( 'the acknowledgement asks for the link to be followed, and says why', array(
	false !== strpos( mail_said( 0, 'body' ), "Please follow this link: we cannot take your application further until you do.\r\n\r\n" . WPCPM_Institution_Application::verify_url( only_row()->ID ) ),
	strpos( mail_said( 0, 'body' ), 'it does nothing else' ),
), array( true, false ) );

follow( $applied['url'] );
$done = WPCPM_Institution_Application::render();

ck( 'and the page after sending says the same need where it speaks of the email', array(
	false !== strpos( $done, 'with a link that confirms the address. Please follow it: we cannot take your application further until you do.' ),
), array( true ) );

echo "\n-- the editor's preview -----------------------------------------------\n";

// Last, because this cannot be undone: a live preview would mint a nonce and a single-use
// token on every keystroke that reloads the block.
define( 'REST_REQUEST', true );

reset_world();
$preview = WPCPM_Institution_Application::render();

ck( 'the editor is shown no form at all', strpos( $preview, '<form' ), false );
ck( 'and no token', strpos( $preview, WPCPM_Institution_Application::TOKEN_FIELD ), false );
ck( 'it names the groups', substr_count( $preview, '<li>' ), 6 );
ck( 'says how many questions each asks', false !== strpos( $preview, '4 questions' ), true );
ck( 'the agreement among them, with its three', false !== strpos( $preview, '<li><strong>The agreement</strong> 3 questions</li>' ), true );
ck( 'and how many countries the list offers', false !== strpos( $preview, 'offers 2 countries' ), true );

/* ---- the cap on unauthenticated outbound mail ---------------------------- */

echo "\n=== The form acknowledges held rows, but is not a mailer ===\n";

// Held rows are written to, which is the whole point of that change: a real institution held
// by a busy afternoon must still get the link that makes it approvable. But that removed the
// only thing capping outbound mail from a page needing no account, so the send has a ceiling
// of its own, set far above the storage degrade so it never silences the rows it exists for.
// Past it the application is stored and queued exactly the same; only the message stops.
ck( 'the two ceilings are different numbers, or the cap would silence exactly the held rows it is for',
    WPCPM_Institution_Application::MAIL_PER_DAY > WPCPM_Institution_Application::PER_DAY, true );

reset_world();

// Exhausted directly rather than through the form, so this block is about the send and not
// about the forty-a-day degrade beside it.
for ( $i = 0; $i < WPCPM_Institution_Application::MAIL_PER_DAY; $i++ ) {
	WPCPM_Ceiling::claim( 'apply-mail', WPCPM_Institution_Application::MAIL_PER_DAY, DAY_IN_SECONDS );
}

$capped = submit( answers( array( 'Contact Email' => 'capped@example.edu' ) ) );
$row    = only_row();

ck( 'past the ceiling the application is still stored', $row instanceof WP_Post, true );
ck( 'and still new, not held or spammed for it', get_post_meta( $row->ID, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Institution_Application::STATE_NEW );
ck( 'the queue is told why', in_array( 'mail-ceiling', (array) get_post_meta( $row->ID, WPCPM_Institution_Application::META_SIGNALS, true ), true ), true );
ck( 'and the applicant is told plainly rather than promised a message that will not come', $capped['outcome'], 'sent-quiet' );

echo "\n=== The confirmation link, sent again at an Administrator's press ===\n";

// Approve refuses until the address is confirmed, and only the acknowledgement's link confirms it.
// Until 1.123.26 nothing sent that link a second time, so a row past the day's limit on
// acknowledgements, a spam-filed row put back, and an applicant who lost the email were stuck for
// good. `acknowledge_again()` is the one way the queue sends it again: the same email the form
// sends, through the same sender, counted against the same daily limit.
reset_world();
submit( answers() );
$row             = only_row();
$first_link      = WPCPM_Institution_Application::verify_url( $row->ID );
$claimed_before  = WPCPM_Ceiling::count( WPCPM_Institution_Application::MAIL_KEY, DAY_IN_SECONDS );
$GLOBALS['mail'] = array();

ck( 'sent again, it goes', WPCPM_Institution_Application::acknowledge_again( $row->ID ), 'sent' );
ck( 'once, to the address on the application, as the same email the form sends', array( count( $GLOBALS['mail'] ), mail_said( 0, 'to' ), mail_said( 0, 'context' ) ), array( 1, 'ana@example.edu', 'institution-applied' ) );
ck( 'with the link that confirms the address', false !== strpos( mail_said( 0, 'body' ), WPCPM_Institution_Application::verify_url( $row->ID ) ), true );
// The link is a signature over the application and its address, not a stored secret, so a send
// made again carries the same link, and the one in the first email keeps working: nothing an
// applicant who finds the first email follows is refused.
ck( 'which is the link the first email carried, so that one keeps working', WPCPM_Institution_Application::verify_url( $row->ID ), $first_link );
ck( 'and it counts against the day\'s limit on acknowledgements, as the first send does', WPCPM_Ceiling::count( WPCPM_Institution_Application::MAIL_KEY, DAY_IN_SECONDS ), $claimed_before + 1 );

$GLOBALS['mail'] = array();

for ( $i = 0; $i < WPCPM_Institution_Application::MAIL_PER_DAY; $i++ ) {
	WPCPM_Ceiling::claim( WPCPM_Institution_Application::MAIL_KEY, WPCPM_Institution_Application::MAIL_PER_DAY, DAY_IN_SECONDS );
}

ck( 'past the day\'s limit it is refused, and nothing is sent', array( WPCPM_Institution_Application::acknowledge_again( $row->ID ), count( $GLOBALS['mail'] ) ), array( 'ceiling', 0 ) );

reset_world();
submit( answers() );
$row                   = only_row();
$GLOBALS['mail']       = array();
$GLOBALS['mail_fails'] = true;

ck( 'a hand-off the mail server refuses says so', WPCPM_Institution_Application::acknowledge_again( $row->ID ), 'failed' );

$GLOBALS['mail_fails'] = false;

echo "\n=== Words keep their backslashes ===\n";

// Post meta and `wp_insert_post()` unslash what they are handed, as core's do, and what reaches
// these writes is unslashed already: the answers and the browser's name (WordPress hands them
// over slashed, as posted here), the country's row from the base, a manager's note. Each keeps
// a backslash only when it is written as a slashed copy.
reset_world();
$typed       = 'Universidad C:\drafts, two \\\\ in a row, "Sede Norte"';
$typed_agent = 'Mozilla/5.0 (C:\drafts, two \\\\ in a row, "test")';
$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]['rows']['recXX000000000003'] = array(
	'name'     => 'Pais C:\drafts, two \\\\ in a row, "Norte"',
	'manager'  => 'Ana C:\drafts, two \\\\ in a row, "Ruiz"',
	'email'    => 'manager@example.test',
	'calendly' => 'https://calendly.com/wpcredits-xx',
);
$_SERVER['HTTP_USER_AGENT'] = wp_slash( $typed_agent );
$sent   = submit( wp_slash( answers( array( 'Name' => $typed, 'Country' => 'recXX000000000003', 'Comments' => $typed ) ) ) );
$row    = only_row();
$fields = $row ? get_post_meta( $row->ID, WPCPM_Institution_Application::META_FIELDS, true ) : array();

ck( 'an application with backslashes in it is sent', $sent['outcome'], 'sent' );
ck( 'its title is the institution\'s name exactly as typed', $row ? $row->post_title : null, $typed );
ck( 'and the answers are stored exactly as typed', array( $fields['Name'] ?? null, $fields['Comments'] ?? null ), array( $typed, $typed ) );
ck( 'the country\'s name is kept exactly as the base holds it', get_post_meta( $row->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ), 'Pais C:\drafts, two \\\\ in a row, "Norte"' );
ck( 'and so is who it routed to', get_post_meta( $row->ID, WPCPM_Institution_Application::META_MANAGER, true )['manager'], 'Ana C:\drafts, two \\\\ in a row, "Ruiz"' );
ck( 'and the browser in the consent evidence', get_post_meta( $row->ID, WPCPM_Institution_Application::META_CONSENT, true )['agent'], $typed_agent );

WPCPM_Institution_Application::add_event( $row->ID, 'information requested', 3, $typed );
$events = get_post_meta( $row->ID, WPCPM_Institution_Application::META_EVENT );
ck( 'a manager\'s note is kept in the history exactly as typed', end( $events )['note'], $typed );


echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
