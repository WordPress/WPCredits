<?php
/**
 * Need help?: who may ask, what the provider is told, and what comes back.
 *
 * There is no local copy of the documentation any more - the provider searches the web
 * itself - so the assertions that used to check a corpus now check the two things that
 * replaced it:
 *
 * - the **host matcher**, which decides whether a citation is really from wordpress.org, and
 * - the **unsourced flag**, which marks an answer that cites nothing from those sites.
 *
 * Those two are all that stands between a reader and a confident answer from somebody's 2019
 * blog post, and neither is visible from reading a template.
 *
 * Run from the plugin root:  php bin/test-handbook.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WEEK_IN_SECONDS', 604800 );

$GLOBALS['opts']   = array();
$GLOBALS['umeta']  = array();
$GLOBALS['pmeta']  = array();
$GLOBALS['users']  = array();
$GLOBALS['posts']  = array();
$GLOBALS['manage'] = array();
$GLOBALS['uid']    = 0;
$GLOBALS['status'] = array();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
	// Carries the HTTP status, which is what the failure messages branch on. A stub without
	// this made every failure look like an unknown one.
	public function get_error_data() { return $this->data; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $menu_order = 0;
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $u, $p = null ) { return $u; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
// Lowercases the host, which is why the matcher can be case-insensitive without saying so.
function wp_parse_url( $u, $c = -1 ) {
	$parts = parse_url( (string) $u );
	if ( isset( $parts['host'] ) ) { $parts['host'] = strtolower( $parts['host'] ); }
	if ( -1 === $c ) { return $parts; }
	$map = array( PHP_URL_HOST => 'host', PHP_URL_SCHEME => 'scheme', PHP_URL_PATH => 'path' );
	return $parts[ $map[ $c ] ?? '' ] ?? null;
}
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {} function add_shortcode() {}
function register_post_type() {} function register_block_type() {}
function wp_register_style() {} function wp_enqueue_style() {}
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_trim_words( $t, $n = 55, $more = null ) {
	$w = preg_split( '/\s+/', trim( (string) $t ) );
	return count( $w ) <= $n ? (string) $t : implode( ' ', array_slice( $w, 0, $n ) ) . '…';
}
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['opts'][ 'T_' . $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
/** Settings::save() flashes the fields it put back to their defaults; the stand-in just holds the value. */
class WPCPM_Flash {
	public static function set( $channel, $value, $user = 0 ) { $GLOBALS['flash'][ $channel ] = $value; }
	public static function take( $channel, $user = 0 ) { $v = $GLOBALS['flash'][ $channel ] ?? null; unset( $GLOBALS['flash'][ $channel ] ); return $v; }
}
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function update_meta_cache( $type, $ids ) { return true; }
function wp_list_pluck( $list, $field ) {
	$out = array();
	foreach ( $list as $item ) { $out[] = is_object( $item ) ? $item->$field : $item[ $field ]; }
	return $out;
}
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
require_once __DIR__ . '/stubs/caps.php';
function get_posts( $a = array() ) { return $GLOBALS['posts']; }
function wp_count_posts( $t ) { $o = new stdClass(); $o->publish = count( $GLOBALS['posts'] ); return $o; }
function is_admin() { return false; }
function wp_trim_words_stub() {}
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function wp_create_nonce( $a ) { return 'nonce'; }
function register_rest_route() {}
function wp_enqueue_script() {} function wp_localize_script() {}
function get_queried_object_id() { return 0; }
function wpautop( $s ) { return '<p>' . $s . '</p>'; }
function wp_kses_post( $s ) { return (string) $s; }
function get_bloginfo( $k = 'name' ) { return 'Test'; }
function get_term_by( $f, $v, $t = 'category' ) { return false; }
function wp_specialchars_decode( $s, $q = null ) { return (string) $s; }

/**
 * The HTTP layer, driven by `$GLOBALS['http']`.
 *
 * Set it to a WP_Error to simulate the provider being unreachable, or to a code and body to
 * simulate what it said. Nothing here ever touches the network.
 */
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['http_sent'] = array( 'url' => $url, 'args' => $args );

	return $GLOBALS['http'] ?? new WP_Error( 'none', 'no fixture' );
}
function wp_remote_get( $url, $args = array() ) { return wp_remote_post( $url, $args ); }
/**
 * Answers the redirect resolution, keyed on the chunk number in the fake redirect URL.
 *
 * `$GLOBALS['redirects']` maps chunk index => the real URL Google would 302 to. A missing
 * entry stands for a redirect that could not be followed, which is a case the code has to
 * handle without losing the citation entirely.
 */
function wp_remote_head( $url, $args = array() ) {
	if ( preg_match( '/CHUNK(\d+)/', $url, $m ) && isset( $GLOBALS['redirects'][ (int) $m[1] ] ) ) {
		return array( 'code' => 302, 'headers' => array( 'location' => $GLOBALS['redirects'][ (int) $m[1] ] ) );
	}

	return new WP_Error( 'no_redirect', 'nothing to follow' );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['code'] ?? 200 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? ( $r['headers'][ $h ] ?? '' ) : ''; }
function wp_json_encode( $v ) { return json_encode( $v ); }

class WP_REST_Response {
	public $data;
	public function __construct( $data, $status = 200 ) { $this->data = $data; }
	public function get_data() { return $this->data; }
}
class WP_REST_Request {
	private $params = array();
	public function __construct( $params = array() ) { $this->params = $params; }
	public function get_param( $k ) { return $this->params[ $k ] ?? null; }
}
function get_post_status( $id ) { return $GLOBALS['status'][ (int) $id ] ?? false; }
function get_post( $id ) {
	if ( ! isset( $GLOBALS['status'][ (int) $id ] ) ) { return null; }
	$p = new WP_Post(); $p->ID = (int) $id; $p->post_status = $GLOBALS['status'][ (int) $id ]; $p->post_type = 'page';
	return $p;
}
function get_page_by_path( $p ) { return null; }
function get_permalink( $id ) { return 'https://example.test/handbook-assistant/'; }
function wp_insert_post( $a, $e = false ) {
	$id = 900 + count( $GLOBALS['status'] );
	$GLOBALS['status'][ $id ] = $a['post_status'] ?? 'publish';
	return $id;
}
function wp_update_post( $a, $e = false ) {
	if ( isset( $a['post_status'] ) ) { $GLOBALS['status'][ (int) $a['ID'] ] = $a['post_status']; }
	return (int) $a['ID'];
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-updates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-assistant.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php';

class WPCPM_Admin { public static function settings_url() { return 'https://example.test/wp-admin/admin.php?page=wpcpm-settings'; } }

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();

/* ---- runner ------------------------------------------------------------- */
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

/* ---- which citations count ---------------------------------------------- */

echo "=== The host matcher ===\n";

// The only thing restricting answers to WordPress documentation now. A `strpos` would wave
// through `notwordpress.org` and `wordpress.org.evil.com`, both of which are the shape a
// citation would take if the model wandered.
foreach ( array(
	'https://wordpress.org/documentation/article/pages/'            => true,
	'https://make.wordpress.org/community/handbook/education/'       => true,
	'https://learn.wordpress.org/lesson/whatever/'                   => true,
	'https://developer.wordpress.org/block-editor/'                  => true,
	'https://WORDPRESS.ORG/documentation/'                           => true,
	'https://notwordpress.org/education/'                            => false,
	'https://wordpress.org.evil.example/education/'                  => false,
	'https://wpbeginner.com/wordpress-education/'                    => false,
	'https://en.wikipedia.org/wiki/WordPress'                        => false,
	'not a url at all'                                               => false,
	''                                                               => false,
) as $url => $expected ) {
	ck(
		sprintf( '%s %s', $expected ? 'allowed:' : 'refused:', '' === $url ? '(empty)' : $url ),
		array( WPCPM_Handbook_Answer::is_allowed( $url ) ),
		array( $expected )
	);
}

/* ---- the answer as HTML -------------------------------------------------- */

echo "\n=== Markdown in an answer ===\n";

// Providers answer in Markdown whichever way the instructions are worded, and the panel
// printed it raw: literal `**During weekly syncs:**` and a column of asterisks. Only bold
// and bullets are handled, so the cases that matter are the ones that must *not* be
// touched - a lone asterisk mid-sentence, and a line that opens on emphasis rather than
// on a bullet.
foreach ( array(
	'bold becomes strong'      => array(
		"**During weekly syncs:** Meet once a week.",
		'<strong>During weekly syncs:</strong> Meet once a week.',
	),
	'two runs on a line'       => array(
		'Ask **Slack** or **email**.',
		'Ask <strong>Slack</strong> or <strong>email</strong>.',
	),
	'a bullet becomes a list'  => array(
		"* First\n* Second",
		'<ul><li>First</li><li>Second</li></ul>',
	),
	'a dash bullet counts too' => array(
		"- Only one",
		'<ul><li>Only one</li></ul>',
	),
	'the list closes'          => array(
		"* Only one\nAfter it.",
		"<ul><li>Only one</li></ul>\nAfter it.",
	),
	'emphasis is not a bullet' => array(
		'*emphasis* leads this line',
		'*emphasis* leads this line',
	),
	'a lone asterisk survives' => array(
		'A 3 * 4 sum.',
		'A 3 * 4 sum.',
	),
	'blank lines survive'      => array(
		"One.\n\nTwo.",
		"One.\n\nTwo.",
	),
) as $label => $case ) {
	ck( $label, array( WPCPM_Handbook_Answer::to_html( $case[0] ) ), array( $case[1] ) );
}

/* ---- what the provider is told ------------------------------------------ */

echo "\n=== The instructions ===\n";

$rules = WPCPM_Handbook_Answer::instructions();

// With no local copy, the restriction to wordpress.org exists *only* because it is asked for
// here. A rewrite that softens any of these four is a rewrite that lets the model answer from
// memory, and nothing else would catch it.
foreach ( array(
	'names the sites'            => 'ONLY these sites',
	'lists every allowed host'   => 'developer.wordpress.org',
	'rejects blogs explicitly'   => 'Ignore blogs, forums, tutorials',
	'prefers the program handbook' => 'handbook/education',
	'forbids filling gaps'       => 'Do not fill the gap from memory',
) as $what => $needle ) {
	ck( 'the prompt ' . $what, array( false !== stripos( $rules, $needle ) ), array( true ) );
}

/* ---- answering ---------------------------------------------------------- */

echo "\n=== Answers ===\n";

// No provider is the honest failure case, and the one a reader will hit first if nobody
// finishes the setup. It must say so rather than returning an empty answer.
$none = WPCPM_Handbook_Answer::ask( 'how does a student get their certificate?', 1 );
ck( 'with no provider it says so plainly',
    array( false !== strpos( $none['text'], 'No AI provider is configured' ), $none['generated'] ),
    array( true, false ) );

$settings                      = WPCPM_Settings::defaults();
$settings['handbook_provider'] = 'gemini';
$settings['handbook_key']      = 'test-key';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

ck( 'a provider with a key is ready', array( WPCPM_Handbook_Answer::provider_ready() ), array( true ) );

/**
 * A Gemini response, in the shape the API really returns.
 *
 * The domain arrives in `web.title`, not in the `web.domain` field the documentation
 * describes, and the `uri` is always a `vertexaisearch` redirect. The first version of this
 * helper invented `domain` - so it validated a shape that never occurs, and the host filter
 * it was testing would have dropped every real citation and marked every real answer
 * unverified. That is why this fixture is copied from an actual response.
 *
 * @param string $text   The answer.
 * @param array  $hosts  Source hostnames, as Google reports them in `title`.
 * @param bool   $legacy Put them in `domain` instead, to cover that field reappearing.
 * @return array
 */
function gemini_reply( $text, array $hosts = array(), $legacy = false ) {
	$grounding = array();

	foreach ( $hosts as $i => $host ) {
		$web = array( 'uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/CHUNK' . $i );

		if ( $legacy ) {
			$web['domain'] = $host;
			$web['title']  = 'Some page title';
		} else {
			$web['title'] = $host;
		}

		$grounding[] = array( 'web' => $web );
	}

	return array(
		'code' => 200,
		'body' => json_encode(
			array(
				'candidates' => array(
					array(
						'content'           => array( 'parts' => array( array( 'text' => $text ) ) ),
						'groundingMetadata' => array( 'groundingChunks' => $grounding ),
					),
				),
			)
		),
	);
}

$GLOBALS['http'] = gemini_reply( 'Approve the quiz in Sensei.', array( 'make.wordpress.org' ) );

$grounded = WPCPM_Handbook_Answer::ask( 'how does a student get their certificate?', 2 );

ck( 'a grounded answer is used and marked as generated',
    array( $grounded['text'], $grounded['generated'], $grounded['unsourced'] ),
    array( 'Approve the quiz in Sensei.', true, false ) );
ck( 'its citation survives, with the redirect kept as the link and the host as the label',
    array( count( $grounded['sources'] ), $grounded['sources'][0]['title'], 0 === strpos( $grounded['sources'][0]['link'], 'https://vertexaisearch.cloud.google.com/' ) ),
    array( 1, 'make.wordpress.org', true ) );

// The judgement has to be made on `domain`, because Google returns its own redirect URL -
// whose host says nothing about where the page actually is. Judging the URI would refuse
// every citation Gemini ever returns.
$GLOBALS['http'] = gemini_reply( 'From a blog.', array( 'wpbeginner.example' ) );
$offsite = WPCPM_Handbook_Answer::ask( 'how does a student get their certificate?', 3 );

ck( 'a citation from elsewhere is dropped, and the answer marked unverified',
    array( count( $offsite['sources'] ), $offsite['unsourced'], $offsite['generated'] ),
    array( 0, true, true ) );

$GLOBALS['http'] = gemini_reply( 'No citations at all.' );
$bare = WPCPM_Handbook_Answer::ask( 'how does a student get their certificate?', 4 );
ck( 'an answer citing nothing is marked unverified too',
    array( $bare['unsourced'] ), array( true ) );

$GLOBALS['http'] = gemini_reply( 'Mixed.', array( 'learn.wordpress.org', 'medium.example', 'developer.wordpress.org' ) );
$mixed = WPCPM_Handbook_Answer::ask( 'anything', 5 );
ck( 'only the WordPress citations are kept', array( count( $mixed['sources'] ) ), array( 2 ) );

/* ---- the actual addresses ------------------------------------------------ */

echo "\n=== Citations resolve to real pages ===\n";

// Google's `uri` is an opaque redirect and its `title` is only the domain, so a list of
// citations read "wordpress.org" four times over. Following the redirect one hop gives the
// page - and the host check is then made against where the page *is* rather than against a
// label Google supplied, which is the stronger of the two checks.
$GLOBALS['redirects'] = array(
	0 => 'https://make.wordpress.org/community/handbook/education/credits/program-manager-guide/students-management/certificate-graduation/',
	1 => 'https://learn.wordpress.org/lesson/get-your-certificate/',
);
$GLOBALS['http'] = gemini_reply( 'Certificates.', array( 'wordpress.org', 'wordpress.org' ) );

$resolved = WPCPM_Handbook_Answer::ask( 'anything', 60 );

ck( 'the link is the real page, not the redirect',
    array( $resolved['sources'][0]['link'] ),
    array( 'https://make.wordpress.org/community/handbook/education/credits/program-manager-guide/students-management/certificate-graduation/' ) );
ck( 'the address is shown, without the scheme',
    array( $resolved['sources'][0]['extract'] ),
    array( 'make.wordpress.org/community/handbook/education/credits/program-manager-guide/students-management/certificate-graduation' ) );
ck( 'and the name comes from the page, not the domain',
    array( $resolved['sources'][0]['title'] ),
    array( 'Certificate graduation - make.wordpress.org' ) );
ck( 'a second page on another site resolves too',
    array( $resolved['sources'][1]['title'] ),
    array( 'Get your certificate - learn.wordpress.org' ) );

// The resolved address is what the host check now judges, so a redirect that lands somewhere
// else is refused however Google labelled it.
$GLOBALS['redirects'] = array( 0 => 'https://wpbeginner.example/wordpress-certificates/' );
$GLOBALS['http'] = gemini_reply( 'Mislabelled.', array( 'wordpress.org' ) );
$lied = WPCPM_Handbook_Answer::ask( 'anything', 61 );
ck( 'a citation labelled wordpress.org that resolves elsewhere is refused',
    array( count( $lied['sources'] ), $lied['unsourced'] ), array( 0, true ) );

// A redirect that cannot be followed must not lose the citation; the label falls back to the
// host and the link to the redirect.
$GLOBALS['redirects'] = array();
$GLOBALS['http'] = gemini_reply( 'Unfollowable.', array( 'developer.wordpress.org' ) );
$fallback = WPCPM_Handbook_Answer::ask( 'anything', 62 );
ck( 'an unfollowable redirect still yields a citation, named by its host',
    array( count( $fallback['sources'] ), $fallback['sources'][0]['title'], $fallback['sources'][0]['extract'] ),
    array( 1, 'developer.wordpress.org', '' ) );

// Two citations of one page arrive as two different redirects; the reader wants it once.
$GLOBALS['redirects'] = array( 0 => 'https://learn.wordpress.org/lesson/x/', 1 => 'https://learn.wordpress.org/lesson/x/' );
$GLOBALS['http'] = gemini_reply( 'Twice.', array( 'wordpress.org', 'wordpress.org' ) );
$once = WPCPM_Handbook_Answer::ask( 'anything', 63 );
ck( 'the same page behind two redirects is shown once', array( count( $once['sources'] ) ), array( 1 ) );

$GLOBALS['redirects'] = array();

// The documented `domain` field, in case it comes back.
$GLOBALS['http'] = gemini_reply( 'Either shape.', array( 'learn.wordpress.org', 'elsewhere.example' ), true );
$legacy = WPCPM_Handbook_Answer::ask( 'anything', 51 );
ck( 'the documented domain field is read too, when present',
    array( count( $legacy['sources'] ), $legacy['sources'][0]['title'] ),
    array( 1, 'learn.wordpress.org' ) );

// A real page title in `title` must not be mistaken for a hostname. Refusing it is the safe
// way round: an unplaceable citation is dropped, never trusted.
$GLOBALS['http'] = array(
	'code' => 200,
	'body' => json_encode(
		array(
			'candidates' => array(
				array(
					'content'           => array( 'parts' => array( array( 'text' => 'Titled.' ) ) ),
					'groundingMetadata' => array(
						'groundingChunks' => array(
							array( 'web' => array( 'uri' => 'https://r/x', 'title' => 'How to get your certificate' ) ),
						),
					),
				),
			),
		)
	),
);
$titled = WPCPM_Handbook_Answer::ask( 'anything', 52 );
ck( 'a page title is not treated as a host',
    array( count( $titled['sources'] ), $titled['unsourced'] ), array( 0, true ) );

// Three chunks on one host are three *pages* - Google gives each its own redirect - so all
// three are kept. Only a genuinely repeated URI collapses.
$GLOBALS['http'] = gemini_reply( 'Three pages.', array( 'wordpress.org', 'wordpress.org', 'wordpress.org' ) );
$three = WPCPM_Handbook_Answer::ask( 'anything', 53 );
ck( 'several pages on one site are all kept', array( count( $three['sources'] ) ), array( 3 ) );

$GLOBALS['http'] = array(
	'code' => 200,
	'body' => json_encode(
		array(
			'candidates' => array(
				array(
					'content'           => array( 'parts' => array( array( 'text' => 'Same page twice.' ) ) ),
					'groundingMetadata' => array(
						'groundingChunks' => array(
							array( 'web' => array( 'uri' => 'https://r/same', 'title' => 'wordpress.org' ) ),
							array( 'web' => array( 'uri' => 'https://r/same', 'title' => 'wordpress.org' ) ),
						),
					),
				),
			),
		)
	),
);
$dup = WPCPM_Handbook_Answer::ask( 'anything', 54 );
ck( 'but the same page cited twice is shown once', array( count( $dup['sources'] ) ), array( 1 ) );

// The request itself: search has to be switched on, or the model answers from memory about a
// program that changed its onboarding last month.
$sent = $GLOBALS['http_sent'];
ck( 'the request enables the search tool',
    array( false !== strpos( $sent['args']['body'], 'google_search' ) ), array( true ) );
ck( 'the rules travel as a system instruction',
    array( false !== strpos( $sent['args']['body'], 'systemInstruction' ) ), array( true ) );
ck( 'and the key goes in a header, never the query string',
    array(
        false === strpos( $sent['url'], 'test-key' ),
        isset( $sent['args']['headers']['x-goog-api-key'] ),
    ),
    array( true, true ) );

// Failures. Each one has to produce a readable answer rather than a blank panel.
$GLOBALS['http'] = new WP_Error( 'down', 'connection refused' );
$down = WPCPM_Handbook_Answer::ask( 'anything', 6 );
ck( 'an unreachable provider explains itself',
    array( '' !== $down['text'], $down['generated'], false !== strpos( $down['notice'], 'connection refused' ) ),
    array( true, false, true ) );

// The message the reader sees is decided by the status, not by the words in it. Matching words
// is how "this model is currently experiencing high demand" - a busy model, which works on the
// next attempt - came to be reported as "no longer available, change it in settings".
$GLOBALS['http'] = array( 'code' => 503, 'body' => json_encode( array( 'error' => array( 'message' => 'This model is currently experiencing high demand.' ) ) ) );
$busy = WPCPM_Handbook_Answer::ask( 'anything', 7 );
ck( 'a busy provider is reported as busy, not as a model to replace',
    array(
        false !== strpos( $busy['text'], 'busy at the moment' ),
        false !== stripos( $busy['text'], 'settings' ),
    ),
    array( true, false ) );

$GLOBALS['http'] = array( 'code' => 404, 'body' => json_encode( array( 'error' => array( 'message' => 'models/whatever is no longer available' ) ) ) );
$gone = WPCPM_Handbook_Answer::ask( 'anything', 8 );
ck( 'a model that is really gone does send them to the settings',
    array( false !== stripos( $gone['text'], 'plugin settings' ) ), array( true ) );

$GLOBALS['http'] = array( 'code' => 400, 'body' => json_encode( array( 'error' => array( 'message' => 'API key not valid' ) ) ) );
$badkey = WPCPM_Handbook_Answer::ask( 'anything', 9 );
ck( 'a refused request points at the key',
    array( false !== stripos( $badkey['text'], 'API key' ) ), array( true ) );

// The provider's own words are always kept in the notice, whatever the reader is told.
ck( 'and the provider\'s own message is still recorded',
    array( false !== strpos( $busy['notice'], 'high demand' ) ), array( true ) );

// A blocked or truncated response arrives 200 with no text, which would otherwise present as
// a successful empty answer.
$GLOBALS['http'] = array( 'code' => 200, 'body' => json_encode( array( 'candidates' => array() ) ) );
$blank = WPCPM_Handbook_Answer::ask( 'anything', 8 );
ck( 'an empty 200 is a failure, not an empty answer',
    array( '' !== $blank['text'], $blank['generated'] ), array( true, false ) );

$GLOBALS['http'] = null;

/* ---- rate limiting ------------------------------------------------------ */

echo "\n=== Rate limits ===\n";

// A provider has to be configured for any of this to be reachable: with none, `ask()`
// returns the quoted answer before the limits are ever consulted.
$settings['handbook_provider'] = 'openai';
$settings['handbook_key']      = 'test-key';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

ck( 'a fresh user is within limits', array( true === WPCPM_Handbook_Answer::within_limits( 7 ) ), array( true ) );

set_transient( WPCPM_Handbook_Answer::RATE_PREFIX . 7, 20, HOUR_IN_SECONDS );
ck( 'past the hourly limit they are not',
    array( is_wp_error( WPCPM_Handbook_Answer::within_limits( 7 ) ) ), array( true ) );
// The point of the limit: it degrades the answer, it does not remove it.
$limited = WPCPM_Handbook_Answer::ask( 'how does a student get their certificate?', 7 );
ck( 'but they still get an answer, quoted from the handbook',
    array( '' !== $limited['text'], $limited['generated'], '' !== $limited['notice'] ),
    array( true, false, true ) );

set_transient( WPCPM_Handbook_Answer::RATE_DAY, WPCPM_Handbook_Answer::DAILY_CAP, DAY_IN_SECONDS );
ck( 'the site-wide cap stops everybody, not just the heavy user',
    array( is_wp_error( WPCPM_Handbook_Answer::within_limits( 8 ) ) ), array( true ) );
delete_transient( WPCPM_Handbook_Answer::RATE_DAY );
delete_transient( WPCPM_Handbook_Answer::RATE_PREFIX . 7 );

$settings['handbook_provider'] = '';
$settings['handbook_key']      = '';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

/* ---- who may ask -------------------------------------------------------- */

echo "\n=== Access ===\n";

$GLOBALS['users'][2] = new WP_User( 2, 'Mentor', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][3] = new WP_User( 3, 'Student', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][4] = new WP_User( 4, 'Admin', array( 'administrator' ) );
$GLOBALS['users'][5] = new WP_User( 5, 'Subscriber', array( 'subscriber' ) );
$GLOBALS['manage']   = array( 4 );

// The shipped default: the handbook describes running the program, so mentors and managers
// see it and students do not until somebody widens it deliberately.
$settings['handbook_access'] = WPCPM_Settings::defaults()['handbook_access'];
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

ck( 'the default audience is mentors', array( $settings['handbook_access'] ), array( 'mentor' ) );
ck( 'a mentor may ask',   array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][2] ) ), array( true ) );
ck( 'a manager may ask',  array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][4] ) ), array( true ) );
ck( 'a student may not, by default',
    array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][3] ) ), array( false ) );
ck( 'nor a plain subscriber',
    array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][5] ) ), array( false ) );

$settings['handbook_access'] = 'program';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'widening it to the program lets a student in',
    array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][3] ) ), array( true ) );

$settings['handbook_access'] = 'any';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'on "any", a subscriber may',
    array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][5] ) ), array( true ) );

$settings['handbook_access'] = 'manage';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'on "manage", a mentor may not',
    array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][2] ) ), array( false ) );

// The one that matters most: whatever the setting, logged out is never allowed.
$settings['handbook_access'] = 'any';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'nobody logged out may ask, on any setting',
    array( WPCPM_Handbook_Assistant::user_can_ask( new WP_User( 0 ) ) ), array( false ) );

$GLOBALS['uid'] = 0;
$rendered = WPCPM_Handbook_Assistant::render();
ck( 'and the shortcode refuses to draw for a visitor',
    array(
        false !== strpos( $rendered, 'Please log in' ),
        false !== strpos( $rendered, 'wpcpm-handbook__form' ),
    ),
    array( true, false ) );

/* ---- the on/off switch -------------------------------------------------- */

echo "\n=== Switched off ===\n";

$settings['handbook_access']  = 'program';
$settings['handbook_enabled'] = true;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

$tool = new WPCPM_Handbook();

ck( 'on by default', array( WPCPM_Handbook::is_enabled() ), array( true ) );
ck( 'and a mentor may ask', array( WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][2] ) ), array( true ) );

$settings['handbook_enabled'] = false;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

ck( 'switched off, nobody may ask - not even a manager',
    array(
        WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][2] ),
        WPCPM_Handbook_Assistant::user_can_ask( $GLOBALS['users'][4] ),
    ),
    array( false, false ) );
ck( 'the tool reports itself off rather than unsynced',
    array( $tool->status_line(), $tool->is_ready() ), array( 'Switched off.', false ) );

// A page announcing a switched-off feature is worse than a blank one; a manager still needs
// to know why the page they are looking at is empty.
$GLOBALS['uid'] = 2;
ck( 'an ordinary reader gets nothing at all',
    array( WPCPM_Handbook_Assistant::render() ), array( '' ) );
// Not even the log-in prompt, which would otherwise advertise a feature that is off.
$GLOBALS['uid'] = 0;
ck( 'and neither does a visitor',
    array( WPCPM_Handbook_Assistant::render() ), array( '' ) );

// Including a manager. Switched off means nothing is rendered for anybody - the page is
// unpublished as well, and a page that is gone cannot carry an explanation. The tool screen
// in wp-admin is where a manager is told why.
$GLOBALS['uid'] = 4;
ck( 'a manager gets nothing either',
    array( WPCPM_Handbook_Assistant::render() ), array( '' ) );

$GLOBALS['uid'] = 0;

// The handler now supplies every boolean on every save, because an unchecked checkbox posts
// nothing and "absent" would otherwise be indistinguishable from "off". An unrelated save -
// one that never mentions the key - must still leave it alone. `bin/test-settings.php` drives
// the whole round trip; this only pins the two outcomes this module depends on.
$settings['handbook_enabled'] = true;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

$saved = WPCPM_Settings::save( array( 'base_id' => 'appOTHER' ) );
ck( 'a save that never mentions it leaves it alone',
    array( $saved['handbook_enabled'], WPCPM_Handbook::is_enabled() ), array( true, true ) );

$saved = WPCPM_Settings::save( array( 'handbook_enabled' => false ) );
ck( 'the settings form can switch it off',
    array( $saved['handbook_enabled'], WPCPM_Handbook::is_enabled() ), array( false, false ) );

$saved = WPCPM_Settings::save( array( 'handbook_enabled' => '1' ) );
ck( 'and on again',
    array( $saved['handbook_enabled'], WPCPM_Handbook::is_enabled() ), array( true, true ) );

/* ---- the panel's endpoint ------------------------------------------------ */

echo "\n=== The endpoint the panel calls ===\n";

$settings['handbook_enabled'] = true;
$settings['handbook_access']  = 'program';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

// Permission is the whole security boundary for the panel: the endpoint is reachable by
// anybody who can find the URL, so it has to make the same decision the page does.
$GLOBALS['uid'] = 0;
ck( 'the endpoint refuses a logged-out caller',
    array( WPCPM_Handbook_Assistant::rest_permission() ), array( false ) );

$GLOBALS['uid'] = 5;
ck( 'and somebody outside the audience',
    array( WPCPM_Handbook_Assistant::rest_permission() ), array( false ) );

$GLOBALS['uid'] = 2;
ck( 'a mentor may call it', array( WPCPM_Handbook_Assistant::rest_permission() ), array( true ) );

$settings['handbook_enabled'] = false;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'switched off, the endpoint refuses everybody',
    array( WPCPM_Handbook_Assistant::rest_permission() ), array( false ) );

$settings['handbook_enabled'] = true;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

// A provider, because an earlier section cleared it and the endpoint has nothing to say
// without one.
$settings['handbook_provider'] = 'gemini';
$settings['handbook_key']      = 'test-key';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

$GLOBALS['http'] = gemini_reply( 'Approve the quiz.', array( 'make.wordpress.org' ) );

$response = WPCPM_Handbook_Assistant::rest_ask( new WP_REST_Request( array( 'question' => 'how does a student get their certificate?' ) ) );
$data     = $response->get_data();

ck( 'it answers with rendered html and its citations',
    array( '' !== $data['html'], count( $data['sources'] ), $data['generated'], $data['unsourced'] ),
    array( true, 1, true, false ) );
ck( 'each citation carries a title and a link',
    array_keys( $data['sources'][0] ), array( 'title', 'link', 'extract' ) );
// The panel sets everything else with textContent; this one field is inserted as markup,
// so it has to arrive already filtered rather than being trusted in the browser.
ck( 'the answer arrives as filtered markup, not as raw provider text',
    array( 0 === strpos( $data['html'], '<p>' ) ), array( true ) );

// The flag the panel needs in order to warn. Without it in the payload the browser has no way
// to know, and the warning silently never appears.
$GLOBALS['http'] = gemini_reply( 'From somewhere else.', array( 'example.test' ) );
$flagged = WPCPM_Handbook_Assistant::rest_ask( new WP_REST_Request( array( 'question' => 'anything' ) ) )->get_data();
ck( 'and the endpoint passes the unverified flag through to the panel',
    array( $flagged['unsourced'], count( $flagged['sources'] ) ), array( true, 0 ) );

$GLOBALS['http'] = null;

$empty = WPCPM_Handbook_Assistant::rest_ask( new WP_REST_Request( array( 'question' => '   ' ) ) )->get_data();
ck( 'an empty question is answered, not fatal', array( isset( $empty['text'] ) ), array( true ) );

/* ---- the launcher contract ---------------------------------------------- */

echo "\n=== The launcher contract ===\n";

// The theme's header button and the plugin's panel meet at one data attribute and one
// method. Both sides are asserted here because neither repository can see the other.
$GLOBALS['uid'] = 2;
ck( 'the plugin tells the theme when there is something to open',
    array( WPCPM_Handbook_Assistant::is_available() ), array( true ) );

$settings['handbook_enabled'] = false;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'and when there is not', array( WPCPM_Handbook_Assistant::is_available() ), array( false ) );
$settings['handbook_enabled'] = true;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

$js = file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/handbook.js' );
ck( 'the panel opens from any element carrying the agreed attribute',
    array( false !== strpos( $js, 'data-wpcpm-handbook-open' ) ), array( true ) );
$prompt = WPCPM_Handbook_Assistant::render_resources( 'mentor' );

ck( 'the Resources section carries it too',
    array( false !== strpos( $prompt, 'data-wpcpm-handbook-open' ) ), array( true ) );
// The same classes the course and report buttons wear, so the theme's treatment of those
// covers this one rather than there being a second kind of button on the same card.
ck( 'and wears the same button classes as the other actions on the card',
    array( false !== strpos( $prompt, 'wpcpm-button wpcpm-button--secondary' ) ), array( true ) );
ck( 'and is a Resources section labelled "Need help?"',
    array( false !== strpos( $prompt, 'Need help?' ), false !== strpos( $prompt, 'Resources' ) ), array( true, true ) );

// Where it appears on the student side is decided by the audience setting, asserted in its
// own section further down rather than here.

/* ---- the guide buttons --------------------------------------------------- */

echo "\n=== The guide beside it ===\n";

$settings                      = WPCPM_Settings::defaults();
$settings['handbook_provider'] = 'gemini';
$settings['handbook_key']      = 'test-key';
$settings['handbook_access']   = 'program';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
$GLOBALS['uid'] = 2;

$student_section = WPCPM_Handbook_Assistant::render_resources( 'student' );
$mentor_section  = WPCPM_Handbook_Assistant::render_resources( 'mentor' );

ck( 'the student card links the Student guide',
    array(
        false !== strpos( $student_section, 'Student guide' ),
        false !== strpos( $student_section, 'handbook/education/credits/student-guide/' ),
    ),
    array( true, true ) );
ck( 'the mentor card links the Mentor guide',
    array(
        false !== strpos( $mentor_section, 'Mentor guide' ),
        false !== strpos( $mentor_section, 'handbook/education/credits/mentor-guide/' ),
    ),
    array( true, true ) );
ck( 'and neither shows the other\'s',
    array(
        false !== strpos( $student_section, 'Mentor guide' ),
        false !== strpos( $mentor_section, 'Student guide' ),
    ),
    array( false, false ) );

// The guide comes first, which is the point of the order: it is the thing to read, and the
// assistant is what you use when it has not answered your question.
ck( 'the guide is before "Need help?"',
    array( strpos( $mentor_section, 'Mentor guide' ) < strpos( $mentor_section, 'Need help?' ) ),
    array( true ) );

// The Slack channel, before the guide, as Slack's own logo and no words of ours.
ck( 'the student card links the students Slack channel',
    array(
        false !== strpos( $student_section, 'wordpress.slack.com/archives/C0959D2M3T8' ),
        false !== strpos( $student_section, 'class="wpcpm-resources__slack"' ),
    ),
    array( true, true ) );

// A link and not a button: none of the `wpcpm-button` classes, so nothing the theme or the
// plugin draws borders and fills with can reach it. Pinned here because the class list is the
// whole mechanism - one stray `wpcpm-button` and the box is back.
ck( 'the logo is a bare link, not a button',
    array(
        false !== strpos( $student_section, 'wpcpm-resources__slack" href' ),
        (bool) preg_match( '/class="wpcpm-button[^"]*"[^>]*wordpress\.slack\.com/', $student_section ),
        (bool) preg_match( '/wordpress\.slack\.com[^>]*class="wpcpm-button/', $student_section ),
    ),
    array( true, false, false ) );
ck( 'the mentor card links the mentors channel, a different one',
    array(
        false !== strpos( $mentor_section, 'wordpress.slack.com/archives/C09KYQLS7F1' ),
        false !== strpos( $mentor_section, 'C0959D2M3T8' ),
    ),
    array( true, false ) );
ck( 'Slack comes before the guide',
    array( strpos( $mentor_section, 'slack.com' ) < strpos( $mentor_section, 'Mentor guide' ) ),
    array( true ) );

// Artwork with no text of ours needs a name, or a screen reader announces "link" and nothing
// else.
ck( 'the logo link is named for a screen reader',
    array(
        false !== strpos( $mentor_section, 'aria-label="Ask in the mentors Slack channel"' ),
        false !== strpos( $mentor_section, 'aria-hidden="true"' ),
    ),
    array( true, true ) );

// Slack's logo is licensed only while it looks like the logo, so it keeps its own colours
// rather than inheriting the button's text colour the way every other icon here does.
ck( 'the logo keeps Slack\'s own colours and its viewBox',
    array(
        false !== strpos( $mentor_section, '#36C5F0' ),
        false !== strpos( $mentor_section, '#E01E5A' ),
        false !== strpos( $mentor_section, 'currentColor' ),
        false !== strpos( $mentor_section, 'viewBox="0 0 622.3 254.4"' ),
    ),
    array( true, true, false, true ) );

// Every drawable element of the published file, not just its paths. The wordmark's "l" is a
// `<rect>` and its "k" a `<polygon>`; an embed that keeps only the eleven paths renders as
// "s ac", which looks close enough to right to ship unnoticed.
$logo = WPCPM_Icons::slack_logo( 24 );

ck( 'the whole lockup is embedded, wordmark included',
    array(
        substr_count( $logo, '<path' ),
        substr_count( $logo, '<rect' ),
        substr_count( $logo, '<polygon' ),
    ),
    array( 11, 1, 1 ) );

// Scaled from the lockup's own 622.3 × 254.4, so it is never stretched.
ck( 'the lockup is asked for by height and keeps its proportions',
    array(
        false !== strpos( $logo, 'width="59" height="24"' ),
        false !== strpos( WPCPM_Icons::slack_logo( 48 ), 'width="117" height="48"' ),
    ),
    array( true, true ) );

// The height of the buttons beside it - their 1px border, 15px padding and 24px line, twice
// over - so the three read as one row rather than a logo tucked in beside two big buttons.
// The Student Report Card's course and report form are two sections now, not one holding two
// buttons. Asserted on the source because rendering that page needs the whole student record;
// this suite already guards the card's copy the same way, and the alternative is no cover at all.
$student_page_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php' );

ck( 'the course and the report form are separate sections',
    array(
        false !== strpos( $student_page_src, "__( 'My course', 'wpcredits-program-manager' )" ),
        false !== strpos( $student_page_src, "__( 'Report form', 'wpcredits-program-manager' )" ),
        false !== strpos( $student_page_src, 'My course and report form' ),
        // My course draws itself since 1.49.0 - it has two columns, the button and the hours box -
        // so the shared link-section helper is gone. What still has to hold is that the course
        // section and the report form are separate things, which the two calls below say.
        false === strpos( $student_page_src, 'render_link_section' ),
        false !== strpos( $student_page_src, 'WPCPM_Student_Report_Form::render_hours(' ),
        false !== strpos( $student_page_src, 'self::render_report_form(' ),
        false !== strpos( $student_page_src, 'WPCPM_Student_Report_Form::render(' ),
    ),
    array( true, true, false, true, true, true, true ) );

// The example question is shown to students, mentors, institutions and administrators alike,
// so it must not be a question only one of them would ask.
$assistant_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-assistant.php' );

ck( 'the example question is neutral, not mentor-flavoured',
    array(
        false !== strpos( $assistant_src, 'How does the WordPress Credits Program work?' ),
        false !== strpos( $assistant_src, 'onboard a 50-hour student' ),
        2 === substr_count( $assistant_src, 'How does the WordPress Credits Program work?' ),
    ),
    array( true, false, true ) );

ck( 'the card asks for it at the buttons\' own height',
    array(
        false !== strpos( $mentor_section, 'height="56"' ),
        false !== strpos( $mentor_section, 'width="137"' ),
    ),
    array( true, true ) );

// Two halves of one section: updates on the left, resources on the right. Asserted here as
// well as in bin/test-updates.php because it is this function that composes them, and a
// Resources section that quietly stopped calling the Updates column would still pass every
// assertion above it.
ck( 'the section is split into two columns',
    array(
        false !== strpos( $mentor_section, 'wpcpm-resources--split' ),
        false !== strpos( $mentor_section, 'wpcpm-resources__col--help' ),
        false !== strpos( $mentor_section, 'wpcpm-resources__col--updates' ),
        substr_count( $mentor_section, '<section' ),
    ),
    array( true, true, true, 1 ) );

// Ordered in the markup rather than turned around in CSS, so the order a screen reader hears
// is the order the page shows. A rewrite that moved the columns back into visual order only
// would pass every other assertion here.
ck( 'both halves are headed, and the updates half comes first',
    array(
        false !== strpos( $mentor_section, '>Resources</h3>' ),
        false !== strpos( $mentor_section, '>Program updates and announcements</h3>' ),
        strpos( $mentor_section, '>Program updates and announcements</h3>' ) < strpos( $mentor_section, '>Resources</h3>' ),
    ),
    array( true, true, true ) );

// The student card gets the same split. Its updates are its own - the column reads the access
// level on each post rather than being told who is looking.
ck( 'the student card is split the same way',
    array(
        false !== strpos( $student_section, 'wpcpm-resources__col--updates' ),
        false !== strpos( $student_section, '>Program updates and announcements</h3>' ),
    ),
    array( true, true ) );

// `wp_kses_post()` strips `<svg>` outright, so a caller that filtered this would silently
// drop the mark and leave an empty button. Both callers echo it directly.
foreach ( array( 'students', 'mentors' ) as $side ) {
	$page = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-' . $side . '-dashboard.php' );

	ck( sprintf( 'the %s dashboard does not run the section through kses', $side ),
	    array( false !== strpos( $page, 'wp_kses_post( WPCPM_Handbook_Assistant::render_resources' ) ),
	    array( false ) );
}

// Both buttons wear the classes the theme's large-button rule covers, so they match "Open your
// course" rather than being a third kind of button on the same card. Two, not three: the Slack
// logo beside them is a bare link and deliberately none of this.
// Counted on the attribute, not the class name: `wpcpm-button--secondary` contains
// `wpcpm-button`, so counting the bare string reports more than there are.
ck( 'the two labelled links are the card\'s own buttons',
    array( 2 === substr_count( $mentor_section, 'class="wpcpm-button' ) ), array( true ) );

// A handbook link has nothing to do with whether an AI provider is configured. Hiding it
// because an API key is missing would make no sense to anybody looking at the page.
$settings['handbook_provider'] = '';
$settings['handbook_key']      = '';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

$no_provider = WPCPM_Handbook_Assistant::render_resources( 'student' );
ck( 'the guide still shows with no provider configured, and the button does not',
    array(
        false !== strpos( $no_provider, 'Student guide' ),
        false !== strpos( $no_provider, 'Need help?' ),
    ),
    array( true, false ) );

// Narrowing the audience to mentors must not take the Student guide off the student's card.
$settings['handbook_provider'] = 'gemini';
$settings['handbook_key']      = 'test-key';
$settings['handbook_access']   = 'mentor';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

$narrowed = WPCPM_Handbook_Assistant::render_resources( 'student' );
ck( 'and it survives the audience being narrowed to mentors',
    array(
        false !== strpos( $narrowed, 'Student guide' ),
        false !== strpos( $narrowed, 'Need help?' ),
    ),
    array( true, false ) );

// An unknown audience has no guide, so with nothing to offer there is no empty section. Not
// `institution`, which used to stand for "unknown" here and is a real audience with a guide of
// its own since the Institution Dashboard grew a Resources section.
$settings['handbook_provider'] = '';
$settings['handbook_key']      = '';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'nothing to show means no empty section',
    array( WPCPM_Handbook_Assistant::render_resources( 'nobody-in-particular' ) ), array( '' ) );

// The five the plugin actually draws, each with a guide and a channel of its own - sponsors
// included, ahead of their own dashboard, which lands in Task 10 of this phase.
$guides = WPCPM_Handbook_Assistant::guides();
ck( 'and the audiences that do have a guide are the five that have a dashboard, sponsors a step ahead of theirs',
    array_keys( $guides ), array( 'student', 'mentor', 'institution', 'administrator', 'sponsor' ) );
ck( 'the sponsor guide points at the program handbook until S6 ships its own', WPCPM_Handbook_Assistant::guides()['sponsor']['url'], 'https://make.wordpress.org/community/handbook/education/credits/' );
ck( 'the program managers\' guide is the handbook\'s education section', $guides['administrator']['url'], 'https://make.wordpress.org/community/handbook/education/credits/' );
ck( 'and their channel is the program\'s', $guides['administrator']['slack'], $guides['institution']['slack'] );
ck( 'the institution\'s guide is the handbook page written for them', $guides['institution']['url'], 'https://make.wordpress.org/community/handbook/education/credits/institutions/' );
ck( 'and its channel is the program\'s own, not a student or mentor one',
    array(
		$guides['institution']['slack'] !== $guides['mentor']['slack'],
		'' !== $guides['institution']['slack'],
	),
    array( true, true ) );

// The audience-specific block is what the Institution Dashboard puts its contact in, and it
// is enough on its own to make a section worth drawing.
ck( 'an audience with nothing but a block of its own still gets a section',
    false !== strpos( WPCPM_Handbook_Assistant::render_resources( 'nobody-in-particular', '<div class="wpcpm-resources__contact">Ola</div>' ), 'wpcpm-resources__contact' ),
    true );

$settings['handbook_provider'] = 'gemini';
$settings['handbook_key']      = 'test-key';
$settings['handbook_access']   = 'program';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
$GLOBALS['uid'] = 0;
// A second question while the first is in flight must not be answered by whichever reply
// happens to land last.
ck( 'an in-flight question is abandoned when another is asked',
    array( false !== strpos( $js, 'pending.abort()' ) ), array( true ) );

/* ---- complete removal ---------------------------------------------------- */

echo "\n=== The Resources section follows the audience ===\n";

// Removing the section from the Student Report Card outright meant that widening the audience
// to include students changed nothing there - the setting and the page disagreed, and the page
// won. Asked of the audience, not of the viewer, so a manager inspecting a student does not see
// it on the student's own page while the student never would.
$settings = WPCPM_Settings::defaults();
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
ck( 'on the default audience, students are not included',
    array( WPCPM_Handbook_Assistant::audience_includes_students() ), array( false ) );

foreach ( array( 'program' => true, 'any' => true, 'mentor' => false, 'manage' => false ) as $access => $expected ) {
	$settings['handbook_access'] = $access;
	$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

	ck( sprintf( 'audience "%s" includes students: %s', $access, $expected ? 'yes' : 'no' ),
	    array( WPCPM_Handbook_Assistant::audience_includes_students() ), array( $expected ) );
}

// The decision lives in `render_resources()` rather than in the page, so the page always asks
// for its section and the section decides what goes in it. Asserted on the outcome: with
// students excluded the guide stays and the button goes.
$settings = WPCPM_Settings::defaults();
$settings['handbook_provider'] = 'gemini';
$settings['handbook_key']      = 'test-key';
$settings['handbook_access']   = 'mentor';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
$GLOBALS['uid'] = 2;

$excluded = WPCPM_Handbook_Assistant::render_resources( 'student' );
ck( 'with students excluded, their card keeps the guide and loses the button',
    array( false !== strpos( $excluded, 'Student guide' ), false !== strpos( $excluded, 'Need help?' ) ),
    array( true, false ) );

$settings['handbook_access'] = 'program';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;

$included = WPCPM_Handbook_Assistant::render_resources( 'student' );
ck( 'and with students included it gains the button',
    array( false !== strpos( $included, 'Student guide' ), false !== strpos( $included, 'Need help?' ) ),
    array( true, true ) );

$GLOBALS['uid'] = 0;

echo "\n=== Google AI Studio is the only provider ===\n";

ck( 'the provider list offers Gemini and nothing else',
    array_keys( WPCPM_Handbook_Answer::providers() ), array( '', 'gemini' ) );

$saved = WPCPM_Settings::save( array( 'handbook_provider' => 'openai' ) );
ck( 'a provider that no longer exists is not accepted',
    array( $saved['handbook_provider'] ), array( '' ) );

// A grounded answer truncated by the token ceiling comes back with its citations *gone*, so
// the ceiling has to be generous. This pins that it was raised, because the symptom - every
// answer marked unverified - looks like a filter bug rather than a limit.
$answer_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php' );
// Measured on the live site: the same three questions took 9.4, 18.7 and 24.1 seconds. The
// old 25-second limit was not a margin, it was a coin toss, and the failure is a cURL 28 with
// no answer at all.
ck( 'the provider is given long enough to search, read and answer',
    array( WPCPM_Handbook_Answer::TIMEOUT >= 45 ), array( true ) );

// A wait that long needs something moving on screen, not a line of text.
$panel_js = file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/handbook.js' );
ck( 'the panel shows a progress bar and a running count while it waits',
    array(
        false !== strpos( $panel_js, 'wpcpm-hb-panel__bar' ),
        false !== strpos( $panel_js, 'setInterval' ),
    ),
    array( true, true ) );
ck( 'and gives up on its own rather than spinning for ever',
    array( false !== strpos( $panel_js, '75000' ) ), array( true ) );

ck( 'the token ceiling leaves room for a grounded answer',
    array( false !== strpos( $answer_src, "'maxOutputTokens' => 2048" ) ), array( true ) );
ck( 'and there is no OpenAI-compatible path left',
    array( false !== stripos( $answer_src, 'openai' ) ), array( false ) );

echo "\n=== The copy describes what the module actually does ===\n";

// Every one of these sentences was true of the version that kept a local copy, and false the
// moment that copy was deleted - and a settings screen that describes a design the plugin no
// longer has is worse than no description, because somebody will rely on it. Two of them
// survived three releases and were spotted by a reader, not by a test.
$copy = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php' )
	. file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php' )
	. file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-assistant.php' )
	. file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php' );

// Only the claims. Each is quoted here exactly as it appeared, so a search for it is precise:
// the words "index" and "refresh" appear legitimately in comments explaining why there is
// neither.
foreach ( array(
	'a private copy of the documentation'  => 'A private copy of the WordPress documentation',
	'answers with no provider at all'      => 'It works with no provider at all',
	'that nothing leaves the site'         => 'Nothing you type leaves this site',
	'a daily refresh'                      => 'the daily refresh stops',
	'that anything is indexed'             => 'What is already indexed is kept',
	'quoting the documentation on a limit' => 'the documentation is quoted instead',
) as $what => $claim ) {
	ck( 'no longer claims ' . $what, array( false !== strpos( $copy, $claim ) ), array( false ) );
}

echo "\n=== Moving off a retired model ===\n";

// Changing the default is not enough: defaults only reach a site that has never saved its
// settings, so an install carries its old model for ever and the provider simply refuses.
$settings                   = WPCPM_Settings::defaults();
$settings['handbook_model'] = 'gemini-2.0-flash';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
delete_option( WPCPM_Handbook::OPT_MODEL_FIXED );

WPCPM_Handbook::maybe_update_model();
ck( 'a retired default is replaced with the current one',
    array( WPCPM_Settings::get_value( 'handbook_model' ) ),
    array( WPCPM_Settings::defaults()['handbook_model'] ) );

// A model somebody chose is theirs, even if it is retired - they may be waiting on a
// replacement, or pointing at something this plugin has never heard of.
$settings['handbook_model'] = 'some-model-they-chose';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
delete_option( WPCPM_Handbook::OPT_MODEL_FIXED );

WPCPM_Handbook::maybe_update_model();
ck( 'a model chosen by hand is left alone',
    array( WPCPM_Settings::get_value( 'handbook_model' ) ), array( 'some-model-they-chose' ) );

// Once per revision, so a site that has since chosen its own is not overruled on every load.
$settings['handbook_model'] = 'gemini-2.0-flash';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
WPCPM_Handbook::maybe_update_model();
ck( 'and it does not run twice',
    array( WPCPM_Settings::get_value( 'handbook_model' ) ), array( 'gemini-2.0-flash' ) );

// The failure a retired model produces says nothing about where to change it, so the answer
// does. Asserted because "try again" is actively wrong advice here - it will never work.
$settings['handbook_model']    = 'gemini-2.0-flash';
$settings['handbook_provider'] = 'gemini';
$settings['handbook_key']      = 'test-key';
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings;
$GLOBALS['http'] = array(
	'code' => 400,
	'body' => json_encode( array( 'error' => array( 'message' => 'This model models/gemini-2.0-flash is no longer available.' ) ) ),
);

$retired = WPCPM_Handbook_Answer::ask( 'anything', 99 );
ck( 'a retired model sends them to the settings, not back to try again',
    array(
        false !== stripos( $retired['text'], 'plugin settings' ),
        false !== strpos( $retired['text'], 'Trying again' ),
    ),
    array( true, false ) );

// Google has answered 404 for one retired model and 400 for another, so the phrase has to be
// recognised whatever the status says.
$GLOBALS['http'] = array( 'code' => 404, 'body' => json_encode( array( 'error' => array( 'message' => 'models/x is no longer available' ) ) ) );
ck( 'and so does the same thing on a 404',
    array( false !== stripos( WPCPM_Handbook_Answer::ask( 'anything', 98 )['text'], 'plugin settings' ) ),
    array( true ) );
$GLOBALS['http'] = null;

echo "\n=== Removing the module ===\n";

// Read from the source rather than executed: uninstall needs a real database, and the mistake
// this catches is not a broken query but a *forgotten* one - a name the module ever wrote and
// the removal path never mentions.
//
// Every name below is now a literal, because the classes that owned them as constants have
// been deleted. That is exactly why this check matters more than it did: nothing in the
// codebase names them any more, so nothing but this would notice them being dropped.
$removal = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php' )
	. file_get_contents( WPCPM_PLUGIN_DIR . 'uninstall.php' );

$must_remove = array(
	'the passage table'            => 'DROP TABLE IF EXISTS',
	'the schema version'           => "'wpcpm_handbook_schema'",
	'the run state'                => "'wpcpm_handbook_state'",
	'the last report'              => "'wpcpm_handbook_report'",
	'the last-run stamp'           => "'wpcpm_handbook_last'",
	'the last error'               => "'wpcpm_handbook_error'",
	'the source list'              => "'wpcpm_handbook_sources'",
	'the daily cron'               => "'wpcpm_handbook_sync_daily'",
	'the tick cron'                => "'wpcpm_handbook_sync_tick'",
	'the page id'                  => 'OPT_PAGE',
	'the visibility mark'          => 'OPT_APPLIED',
	'the daily rate counter'       => 'clear_limits',
	'the per-user rate counters'   => 'RATE_PREFIX',
	'the orphaned posts'           => "'wpcpm_handbook' )",
	'their meta'                   => '_wpcpm_hb_passages',
);

foreach ( $must_remove as $what => $needle ) {
	ck( 'removal covers ' . $what, array( false !== strpos( $removal, $needle ) ), array( true ) );
}

// The table and both schedules belong to versions whose classes no longer exist, so the
// removal path has to name them itself. A site that upgraded through those versions still has
// all three, and a scheduled hook with no callback fails silently for ever.
ck( 'the legacy schedules are cleared while the plugin runs, too',
    array( false !== strpos( $removal, 'clear_legacy_schedules' ) ), array( true ) );

echo "\n=== When the model says it is busy ===\n";

/*
 * "This model is currently experiencing high demand" is a statement about *one model's* capacity, so
 * the retry has to ask a different one - `gemini-flash-latest` answered 503 twice in a row on
 * 6 August 2026 while `gemini-flash-lite-latest` answered the same grounded question in three
 * seconds. Both halves are asserted here: which model is chosen, and which statuses count as busy
 * at all.
 */
$busy = new ReflectionMethod( 'WPCPM_Handbook_Answer', 'is_busy' );
$pick = new ReflectionMethod( 'WPCPM_Handbook_Answer', 'fallback_model' );

if ( PHP_VERSION_ID < 80100 ) {
	$busy->setAccessible( true );
	$pick->setAccessible( true );
}

/**
 * A failure carrying an HTTP status, as the provider call builds one.
 *
 * @param int $status HTTP status.
 * @return WP_Error
 */
function failure( $status ) {
	return new WP_Error( 'wpcpm_handbook_http', 'upstream said no', array( 'status' => $status ) );
}

foreach ( array( 429, 500, 502, 503, 504 ) as $status ) {
	ck( "$status counts as busy", array( $busy->invoke( null, failure( $status ) ) ), array( true ) );
}

// 404 is the retired-model case, which never fixes itself by waiting and must not be retried.
foreach ( array( 400, 401, 403, 404 ) as $status ) {
	ck( "$status is not busy", array( $busy->invoke( null, failure( $status ) ) ), array( false ) );
}

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'handbook_model' => 'gemini-flash-latest' );

ck( 'a busy Flash falls back to the lighter model',
    array( $pick->invoke( null ) ), array( 'gemini-flash-lite-latest' ) );

// Falling back to itself would be asking the thing that is full.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'handbook_model' => 'gemini-flash-lite-latest' );

ck( 'a site already on the light model falls back to the other one',
    array( $pick->invoke( null ) ), array( 'gemini-flash-latest' ) );

ck( 'the fallback is never the model that just failed',
    array( $pick->invoke( null ) !== 'gemini-flash-lite-latest' ), array( true ) );

// The default matters: it used to be a model Google has retired, so a site with nothing saved got a
// 404 rather than an answer.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array();

ck( 'the shipped default is not a retired model',
    array( in_array( WPCPM_Settings::get_value( 'handbook_model', 'gemini-flash-latest' ), array( 'gemini-2.5-flash', 'gemini-1.5-flash' ), true ) ),
    array( false ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
