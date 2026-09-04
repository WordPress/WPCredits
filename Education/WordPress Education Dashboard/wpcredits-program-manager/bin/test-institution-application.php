<?php
/**
 * The public application form: its anti-spam, its thirteen fields and its two mails.
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
 *   any other way is a 422 for the whole record.
 * - **A failure never hands back a tick.** The stash that refills the form is asserted to
 *   carry no consent value at all, in every failure path.
 *
 * The other pieces are stood in for exactly at their contracts: the mail exit, the manager
 * notification, the settings. `WPCPM_Countries`, `WPCPM_Field_Value`, `WPCPM_Ceiling`,
 * `WPCPM_Request` and `WPCPM_Roles` are the real files, because what half of this suite is
 * about is whether this form uses them properly.
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
function add_action( $h, $c, $p = 10, $a = 1 ) {}
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
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function get_privacy_policy_url() { return (string) $GLOBALS['policy']; }
function nocache_headers() { ++$GLOBALS['nocache']; }
function is_page( $id = 0 ) { return (int) $id === (int) ( $GLOBALS['on_page'] ?? 0 ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }

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
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }

function wp_insert_post( $a, $error = false ) {
	if ( ! empty( $GLOBALS['post_fails'] ) ) {
		return $error ? new WP_Error( 'wpcpm_test_insert', 'refused' ) : 0;
	}
	$post                          = new WP_Post();
	$post->ID                      = $GLOBALS['next_id']++;
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_title              = $a['post_title'] ?? '';
	$post->post_date               = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$post->post_modified_gmt       = $post->post_date;
	$GLOBALS['clock']             += 60;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; }
function get_permalink( $id ) { return 'https://example.test/apply/'; }
function get_page_by_path( $slug ) { return null; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }

/** `get_posts()` as this class uses it: one type, one status, one IN clause, oldest first. */
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
	usort( $out, function ( $x, $y ) {
		$by_date = strcmp( $x->post_date, $y->post_date );
		return 0 !== $by_date ? $by_date : $x->ID - $y->ID;
	} );
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
 * the form renders: applications on, a privacy policy, a published page, a countries map.
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
	$GLOBALS['enqueued']    = array();
	$GLOBALS['nocache']     = 0;
	$GLOBALS['on_page']     = 0;
	$GLOBALS['settings']    = array(
		'applications_enabled'      => true,
		'application_trusted_proxy' => '',
	);

	$_POST                    = array();
	$_GET                     = array();
	$_SERVER['REMOTE_ADDR']   = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

	// The page the form lives on, published, so `page_url()` answers.
	$page              = new WP_Post();
	$page->ID          = 42;
	$page->post_type   = 'page';
	$page->post_status = 'publish';
	$page->post_title  = 'Apply';
	$page->post_date   = '2026-09-01 09:00:00';

	$GLOBALS['posts'][42] = $page;
	$GLOBALS['opts'][ WPCPM_Institution_Application::OPT_PAGE ] = 42;

	// A privacy policy page, so the consent record has something to name.
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

	return $issued . '.' . substr( wp_hash( 'wpcpm-application-dwell|' . $issued . '|' . wp_create_nonce( WPCPM_Institution_Application::ACTION_SUBMIT ) ), 0, 32 );
}

/** The thirteen answers a good application carries, keyed by form key. */
function answers( array $overrides = array() ) {
	$values = array(
		'Name'                                                                  => 'Universidad Example',
		'Country'                                                               => 'recCR000000000001',
		'City'                                                                  => 'San Jose',
		'Website'                                                               => 'example.edu',
		'Contact Person'                                                        => 'Ana Ruiz',
		'Contact Email'                                                         => 'ana@example.edu',
		'Department'                                                            => 'Faculty of Engineering',
		'How do your internships or practices typically work?'                  => array( ' Based on required hours (e.g. 150 hours)' ),
		'Comments'                                                              => '',
		'Estimated number of students who may be interested'                    => '25',
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

$src     = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php' );
$fixture = json_decode( file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-table-fields.json' ), true );

echo "\n-- the base's spelling, byte for byte --------------------------------\n";

// Every column this form claims an applicant filled in has to exist in the Institutions
// table under exactly that name: `create_records()` sends no `typecast`, so one wrong name is
// a 422 for the whole record and the application is lost at the moment of approval.
$missing = array();

foreach ( array_keys( WPCPM_Institution_Application::fields() ) as $column ) {
	if ( ! in_array( $column, $fixture['fields'], true ) ) {
		$missing[] = $column;
	}
}

ck( 'every one of the thirteen columns is in the fixture', $missing, array() );
ck( 'and there are thirteen of them', count( WPCPM_Institution_Application::fields() ), 13 );

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
	bin2hex( $columns[11] ),
	bin2hex( "Anything else you\xE2\x80\x99d like us to know?" )
);
ck( 'the first internship choice keeps its leading space', substr( WPCPM_Institution_Application::internship_choices()[0], 0, 1 ), ' ' );
ck( 'and the "Other" choice is spelled the way the column spells it', WPCPM_Institution_Application::other_choice(), 'Other (please specify)' );

$keys = array();

foreach ( $columns as $column ) {
	$keys[] = WPCPM_Institution_Application::form_key( $column );
}

ck( 'the thirteen form keys are distinct', count( array_unique( $keys ) ), 13 );
$shaped = 0;

foreach ( $keys as $key ) {
	$shaped += preg_match( '/^f[0-9a-f]{12}$/', $key );
}

ck( 'and each is one letter and twelve hex digits', $shaped, 13 );
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

echo "\n-- what is drawn, and when nothing is ---------------------------------\n";

reset_world();
$GLOBALS['policy'] = '';

ck( 'with no privacy policy the public are shown nothing at all', WPCPM_Institution_Application::render(), '' );

$GLOBALS['caps'] = true;
$managers        = WPCPM_Institution_Application::render();

ck( 'and a manager is told why, in one sentence', substr_count( $managers, '<p' ), 1 );
ck( 'which names the privacy policy', false !== strpos( $managers, 'privacy policy' ), true );
ck( 'and no form is drawn for them either', strpos( $managers, '<form' ), false );

reset_world();
$GLOBALS['settings']['applications_enabled'] = false;

ck( 'with the form switched off the public are shown nothing', WPCPM_Institution_Application::render(), '' );

$GLOBALS['caps'] = true;
ck( 'and a manager is told it is switched off', false !== strpos( WPCPM_Institution_Application::render(), 'switched off' ), true );

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

ck( 'all thirteen questions are drawn', $drawn, 13 );
ck( 'the five groups are drawn as fieldsets', substr_count( $form, '<fieldset' ), 5 );
ck( 'the country select offers the map, with a record ID as the value', false !== strpos( $form, '<option value="recCR000000000001">Costa Rica</option>' ), true );
ck( 'the U+2019 label survives to the page', false !== strpos( $form, "Anything else you\xE2\x80\x99d like us to know?" ), true );
ck( 'the checkbox values keep the leading space the column has', false !== strpos( $form, 'value=" Based on required hours (e.g. 150 hours)"' ), true );
ck( 'and show it trimmed', false !== strpos( $form, '<span>Based on required hours (e.g. 150 hours)</span>' ), true );
ck( 'the consent box is not ticked when nothing was posted', strpos( $form, 'checked="checked"' ), false );

// The policy line under the consent box. The link is one word inside the sentence, not the
// sentence's last two words after a colon: "read the privacy policy here" is what a person
// reads, and "here" is what they click.
ck( 'the policy line links a word inside the sentence', array(
	false !== strpos( $form, '>here</a>' ),
	false !== strpos( $form, 'You can read the privacy policy' ),
), array( true, true ) );
ck( 'and it points at the policy this site has set', false !== strpos( $form, 'href="' . get_privacy_policy_url() . '" rel="noopener">here</a>' ), true );
ck( 'the old shape, with the link trailing a colon, is gone', false !== strpos( $form, 'The policy is here:' ), false );
ck( 'the stylesheet and the submit guard are enqueued', $GLOBALS['enqueued'], array( 'wpcpm-institution-application', 'wpcpm-forms' ) );
ck( 'the form is guarded against a second press', false !== strpos( $form, 'data-wpcpm-once' ), true );

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

ck( 'the U+2019 column round-trips into storage under its own name', $fields[ "Anything else you\xE2\x80\x99d like us to know?" ], 'We run two intakes a year.' );
ck( 'the multi-select is stored as the column\'s own choice, leading space and all', $fields['How do your internships or practices typically work?'], array( ' Based on required hours (e.g. 150 hours)' ) );
ck( 'the website was normalised on the way in', $fields['Website'], 'https://example.edu' );
ck( 'and the count is an integer, not a string', $fields['Estimated number of students who may be interested'], 25 );

ck( 'the country is held on its own key', get_post_meta( $row->ID, WPCPM_Institution_Application::META_COUNTRY, true ), 'recCR000000000001' );
ck( 'with the name it had that day', get_post_meta( $row->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ), 'Costa Rica' );
ck( 'and a snapshot of who it routed to', get_post_meta( $row->ID, WPCPM_Institution_Application::META_MANAGER, true )['calendly'], 'https://calendly.com/wpcredits-cr' );

$consent = get_post_meta( $row->ID, WPCPM_Institution_Application::META_CONSENT, true );

ck( 'the consent record names the policy', array( $consent['url'], $consent['policy'] ), array( 'https://example.test/privacy/', 43 ) );
ck( 'and the version of it that was agreed to', $consent['modified'], '2026-08-20 11:30:00' );
ck( 'and the sentence as it was rendered', false !== strpos( $consent['sentence'], 'privacy policy' ), true );
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
ck( 'the redirect carries a random id and nothing else', 1, preg_match( '#^https://example\.test/apply/\?wpcpm_app=[a-z0-9]+$#', $sent['url'] ) );
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

// A honeypot row skips requiredness on purpose: telling a bot which of thirteen fields it
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
// be read and posted in the same second by a person.
ck( 'a token minted this instant is too fast to be a person', WPCPM_Institution_Application::check_token( WPCPM_Institution_Application::token() ), 'spam' );

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
ck( 'a token nobody signed is refused', WPCPM_Institution_Application::check_token( ( time() - 60 ) . '.0123456789abcdef0123456789abcdef' ), 'spam' );
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
ck( 'with no form under it', strpos( $page, '<form' ), false );

// Silence is still silence: nothing to say and nothing to draw is the empty string and not an
// empty wrapper, which is the same silence with markup in it.
$_GET = array();

ck( 'a stranger who simply walks onto the page sees nothing at all', WPCPM_Institution_Application::render(), '' );

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
ck( 'a caller that asks for no states gets no rows, never every row', WPCPM_Institution_Application::applications( array() ), array() );
ck( 'and a state nobody has heard of is dropped rather than queried', WPCPM_Institution_Application::applications( array( 'wide-open' ) ), array() );

WPCPM_Institution_Application::delete_all();
ck( 'uninstall takes every application with it', count( stored() ), 0 );

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

echo "\n-- the editor's preview -----------------------------------------------\n";

// Last, because this cannot be undone: a live preview would mint a nonce and a single-use
// token on every keystroke that reloads the block.
define( 'REST_REQUEST', true );

reset_world();
$preview = WPCPM_Institution_Application::render();

ck( 'the editor is shown no form at all', strpos( $preview, '<form' ), false );
ck( 'and no token', strpos( $preview, WPCPM_Institution_Application::TOKEN_FIELD ), false );
ck( 'it names the groups', substr_count( $preview, '<li>' ), 5 );
ck( 'says how many questions each asks', false !== strpos( $preview, '4 questions' ), true );
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


echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
