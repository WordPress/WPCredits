<?php
/**
 * The citations a grounded answer hands out: safe to put in an `href`, or not handed out.
 *
 * `grounding()` builds each source's `link`, `rest_ask()` returns the list exactly as built,
 * and `assets/js/handbook.js` assigns `link` to an anchor's `href` with nothing in between.
 * The shortcode path wraps the same value in `esc_url()`; the panel's does not, and cannot -
 * a script has no `esc_url()`. So the address has to be safe where it is made. Google's
 * redirects were resolved and passed through `esc_url_raw()` already; the branch this suite
 * is about is the other one: a `uri` that is not a redirect, kept verbatim, while the host it
 * was judged on came from the chunk's `domain` or `title` rather than from the `uri` itself.
 *
 * The handbook suite stubs `esc_url_raw()` as the identity, which is right for what it
 * tests and blind to this. Here the stub keeps the one WordPress contract that matters: an
 * address whose scheme is not on `wp_allowed_protocols()` comes back empty. `grounding()` is
 * reached directly, through reflection, because everything between `ask()` and it - the key,
 * the rate limit, the provider call - is the handbook suite's to test and would only be
 * stubbed here to be got past.
 *
 * Run from the plugin root:  php bin/test-handbook-sources.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _x( $s, $c, $d = null ) { return $s; }
function apply_filters( $t, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
// Lowercases the host, as WordPress does, which is why the matcher can be case-insensitive.
function wp_parse_url( $u, $c = -1 ) {
	$parts = parse_url( (string) $u );
	if ( false === $parts ) { return -1 === $c ? false : null; }
	if ( isset( $parts['host'] ) ) { $parts['host'] = strtolower( $parts['host'] ); }
	if ( -1 === $c ) { return $parts; }
	$map = array( PHP_URL_HOST => 'host', PHP_URL_SCHEME => 'scheme', PHP_URL_PATH => 'path' );
	return $parts[ $map[ $c ] ?? '' ] ?? null;
}

/**
 * WordPress's contract, not an identity: an address whose scheme is not one of
 * `wp_allowed_protocols()` comes back as an empty string.
 *
 * The list is WordPress's own. Dropping control characters and spaces first is `esc_url()`'s
 * own first move, and it is what stops a scheme broken up with a newline from slipping past
 * the test - `kses` normalises the same way before it judges the protocol.
 */
function esc_url_raw( $url, $protocols = null ) {
	$url = preg_replace( '/[\x00-\x20\x7f]+/', '', (string) $url );

	if ( '' === $url ) {
		return '';
	}

	$allowed = array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );

	if ( preg_match( '/^([a-z][a-z0-9+.\-]*):/i', $url, $m ) && ! in_array( strtolower( $m[1] ), $allowed, true ) ) {
		return '';
	}

	return $url;
}

/**
 * The one hop `resolve()` makes. `$GLOBALS['location']` is the page Google's redirect would
 * 302 to; unset, the redirect cannot be followed, which is a case the code has to survive.
 */
function wp_remote_head( $url, $args = array() ) {
	return isset( $GLOBALS['location'] ) ? array( 'headers' => array( 'location' => $GLOBALS['location'] ) ) : new WP_Error( 'no_redirect', 'nothing to follow' );
}
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? ( $r['headers'][ $h ] ?? '' ) : ''; }

require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';

$fail = 0;

function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $label . "\n";
	if ( ! $ok ) {
		echo '      got      ' . json_encode( $actual ) . "\n";
		echo '      expected ' . json_encode( $expected ) . "\n";
	}
}

/**
 * What the panel would be handed for one set of grounding chunks.
 *
 * @param array $chunks Each one a `web` object: `uri`, and `title` or `domain`.
 * @return array
 */
function sources_for( array $chunks ) {
	static $grounding = null;

	if ( null === $grounding ) {
		$grounding = new ReflectionMethod( 'WPCPM_Handbook_Answer', 'grounding' );
	}

	$wrapped = array();
	foreach ( $chunks as $web ) { $wrapped[] = array( 'web' => $web ); }

	return $grounding->invoke( null, array( 'groundingChunks' => $wrapped ) );
}

echo "=== An address that is not an address is not a citation ===\n";

// The branch under test: not one of Google's redirects, so nothing is resolved, and the host
// is read off the chunk's title - which passes. The uri itself was never looked at.
ck( 'a javascript: address on an allowed host reaches nobody',
	sources_for( array( array( 'uri' => 'javascript:alert(document.cookie)', 'title' => 'make.wordpress.org' ) ) ), array() );
ck( 'nor a data: one, whichever field names the host',
	sources_for( array( array( 'uri' => 'data:text/html,<script>1</script>', 'domain' => 'learn.wordpress.org' ) ) ), array() );
ck( 'nor a scheme broken up with a newline, which kses would have normalised',
	sources_for( array( array( 'uri' => "java\nscript:alert(1)", 'title' => 'wordpress.org' ) ) ), array() );

// Two chunks, one bad: the good one is still the only one, and it is first.
$mixed = sources_for( array(
	array( 'uri' => 'javascript:void(0)', 'title' => 'wordpress.org' ),
	array( 'uri' => 'https://developer.wordpress.org/plugins/', 'title' => 'developer.wordpress.org' ),
) );
ck( 'a bad address does not take the good one down with it',
	array( count( $mixed ), $mixed[0]['link'] ?? null ), array( 1, 'https://developer.wordpress.org/plugins/' ) );

echo "\n=== And a real one still arrives whole ===\n";

$plain = sources_for( array( array( 'uri' => 'https://make.wordpress.org/community/handbook/', 'title' => 'make.wordpress.org' ) ) );
ck( 'a plain https address that is not a redirect is kept verbatim',
	array( count( $plain ), $plain[0]['link'] ?? null, $plain[0]['extract'] ?? null ), array( 1, 'https://make.wordpress.org/community/handbook/', '' ) );
ck( 'and is named by its host, as an unresolved citation always was',
	$plain[0]['title'] ?? null, 'make.wordpress.org' );

$GLOBALS['location'] = 'https://learn.wordpress.org/lesson/get-your-certificate/';
$resolved = sources_for( array( array( 'uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc', 'title' => 'wordpress.org' ) ) );
ck( 'a redirect still resolves to the page behind it',
	array( count( $resolved ), $resolved[0]['link'] ?? null ), array( 1, 'https://learn.wordpress.org/lesson/get-your-certificate/' ) );

// `resolve()` refuses a destination that is not an address, and the citation then falls back
// to the redirect, which is https and Google's. That is where the fallback must stop.
$GLOBALS['location'] = 'javascript:alert(1)';
$behind = sources_for( array( array( 'uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/def', 'title' => 'wordpress.org' ) ) );
ck( 'a redirect to something that is not an address falls back to the redirect, never the destination',
	array( count( $behind ), $behind[0]['link'] ?? null ), array( 1, 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/def' ) );
unset( $GLOBALS['location'] );

echo "\n=== Why it has to be safe at the source ===\n";

// The two consumers that make the check above necessary. The day either changes, the
// comment in grounding() that names them is wrong and this is what says so.
$js        = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/handbook.js' );
$assistant = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-assistant.php' );

ck( 'the panel script still puts the address straight into an href', false !== strpos( $js, 'link.href = source.link' ), true );
ck( 'and the REST route still hands the list over as built', (bool) preg_match( "/'sources'\s*=>\s*\\\$answer\['sources'\]/", $assistant ), true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
