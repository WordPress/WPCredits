<?php
/**
 * WPCPM_Form_Guard: the seven checks a public form runs, at their contracts.
 *
 * Extracted from the institution application for the sponsor application (design spec of
 * 4 September 2026, section 3 decision 1 and section 9.1). What is worth pinning here rather
 * than through a form:
 *
 * - **The numbers are this class's, the order is the caller's.** Every constant keeps the value
 *   the institution form had, and the institution class aliases each one.
 * - **The dwell token is scoped.** A token carries its form's scope in its signature, so one
 *   harvested from the institution page is a forgery on the sponsor page even with the nonce
 *   right.
 * - **Single use is exact.** The second use of a token is refused however close the two uses
 *   are, because the claim behind it is `add_option()`.
 * - **Consent is two values.** `"1"` and `"true"`, and an array is not a tick.
 * - **The evidence is seven keys**, the sentence first and the browser last.
 * - **The client address is read the way the institution form learned to.** The forwarded
 *   header is believed only from the configured edge, and from the right.
 *
 * `WPCPM_Ceiling` and `WPCPM_Request` are the real files: half of this suite is whether the
 * guard uses them properly.
 *
 * Run from the plugin root:  php bin/test-form-guard.php
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

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['password'] = 0;
$GLOBALS['posts']    = array();
$GLOBALS['settings'] = array( 'application_trusted_proxy' => '' );
$GLOBALS['policy']   = 'https://example.test/privacy/';

class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_title = '', $post_modified_gmt = '';
}

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_hash( $data, $scheme = 'auth' ) { return md5( 'test-salt|' . (string) $data ); }
function wp_create_nonce( $action = -1 ) { return 'nonce-' . $action; }
// Alphanumeric, of the asked-for length, and different on every call, which is the whole of
// what the token's random half needs of it (added for the S5 fix wave).
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return substr( str_repeat( 'AbCdEf0123456789', 4 ), 0, (int) $length - 4 ) . sprintf( '%04d', ++$GLOBALS['password'] ); }
function get_privacy_policy_url() { return (string) $GLOBALS['policy']; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $a; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';

$fail = 0; $checks = 0;

/**
 * One assertion.
 *
 * @param string $label    What is being asserted.
 * @param mixed  $actual   What the code answered.
 * @param mixed  $expected What it should have answered.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

/** Forget every option and put the request back to a plain one. */
function reset_world() {
	$GLOBALS['opts']     = array();
	$GLOBALS['autoload'] = array();
	$GLOBALS['posts']    = array();
	$GLOBALS['settings'] = array( 'application_trusted_proxy' => '' );
	$GLOBALS['policy']   = 'https://example.test/privacy/';
	$_POST               = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

	$policy                    = new WP_Post();
	$policy->ID                = 43;
	$policy->post_type         = 'page';
	$policy->post_status       = 'publish';
	$policy->post_title        = 'Privacy';
	$policy->post_modified_gmt = '2026-08-20 11:30:00';

	$GLOBALS['posts'][43]                          = $policy;
	$GLOBALS['opts']['wp_page_for_privacy_policy'] = 43;
}

/**
 * A dwell token of a given age, signed the way the guard signs one for a scope.
 *
 * With no `$random` it is the two-part shape a token minted before the S5 fix wave has, which
 * was accepted for one deploy window and is now a forgery (clean-up 1.98.1); with one it is the
 * shape every token has since.
 *
 * @param string $scope  The form's scope.
 * @param int    $age    Seconds ago it was minted.
 * @param string $action The nonce action it is bound to.
 * @param string $random The token's random half, or '' for the shape minted without one.
 * @return string
 */
function dwell_token( $scope, $age = 30, $action = 'act', $random = '' ) {
	$issued = time() - (int) $age;

	if ( '' === $random ) {
		return $issued . '.' . substr( wp_hash( $scope . '|' . $issued . '|' . wp_create_nonce( $action ) ), 0, 32 );
	}

	return $issued . '.' . $random . '.' . substr( wp_hash( $scope . '|' . $issued . '|' . $random . '|' . wp_create_nonce( $action ) ), 0, 32 );
}

echo "=== The numbers ===\n";
ck( 'the six constants keep the institution values', array( WPCPM_Form_Guard::MIN_SECONDS, WPCPM_Form_Guard::TOKEN_LIFETIME, WPCPM_Form_Guard::PER_HOUR, WPCPM_Form_Guard::PER_DAY, WPCPM_Form_Guard::MAIL_PER_DAY, WPCPM_Form_Guard::MAX_LINKS ), array( 6, 43200, 5, 40, 200, 3 ) );
ck( 'the mail ceiling stands far above the degrade, or it would silence the held rows it exists for', WPCPM_Form_Guard::MAIL_PER_DAY > WPCPM_Form_Guard::PER_DAY, true );
ck( 'the three token answers', array( WPCPM_Form_Guard::TOKEN_OK, WPCPM_Form_Guard::TOKEN_SPAM, WPCPM_Form_Guard::TOKEN_STALE ), array( 'ok', 'spam', 'stale' ) );

echo "\n=== 1. The honeypot ===\n";
reset_world();
ck( 'an absent field is not filled', WPCPM_Form_Guard::honeypot_filled( 'wpcpm_confirm_url' ), false );
$_POST['wpcpm_confirm_url'] = '   ';
ck( 'nor is whitespace, which a browser can put there', WPCPM_Form_Guard::honeypot_filled( 'wpcpm_confirm_url' ), false );
$_POST['wpcpm_confirm_url'] = 'https://example.com/';
ck( 'a value is', WPCPM_Form_Guard::honeypot_filled( 'wpcpm_confirm_url' ), true );
ob_start();
WPCPM_Form_Guard::render_honeypot( 'wpcpm_confirm_url' );
$pot = (string) ob_get_clean();
ck( 'the honeypot is a text input hidden by class, never a hidden input', array( false !== strpos( $pot, 'name="wpcpm_confirm_url"' ), false !== strpos( $pot, 'type="text"' ), strpos( $pot, 'type="hidden"' ), false !== strpos( $pot, 'aria-hidden="true"' ), false !== strpos( $pot, 'tabindex="-1"' ) ), array( true, true, false, true, true ) );
ck( 'and its label says what to do with it', false !== strpos( $pot, 'Leave this field empty.' ), true );

echo "\n=== 2. The dwell token ===\n";
reset_world();
$_POST['_wpnonce'] = wp_create_nonce( 'act' );
ck( 'a token is a time, twelve random characters and a signature', preg_match( '/^\d+\.[A-Za-z0-9]{12}\.[0-9a-f]{32}$/', WPCPM_Form_Guard::token( 'scope-a', 'act' ) ), 1 );
// The S5 review's Minor 1: everything else in the signature is the same for everybody, so two
// logged-out visitors who opened the page in the same second were handed one token - and single
// use is exact, so the second of them to submit had a genuine application filed as spam.
ck( 'and two tokens minted in the same second differ', WPCPM_Form_Guard::token( 'scope-a', 'act' ) === WPCPM_Form_Guard::token( 'scope-a', 'act' ), false );
ck( 'a token minted this instant is too fast to be a person', WPCPM_Form_Guard::check_token( 'scope-a', WPCPM_Form_Guard::token( 'scope-a', 'act' ) ), 'spam' );
reset_world();
$_POST['_wpnonce'] = wp_create_nonce( 'act' );
ck( 'one minted half a minute ago is accepted', WPCPM_Form_Guard::check_token( 'scope-a', dwell_token( 'scope-a', 30, 'act', 'AbCdEf012345' ) ), 'ok' );
reset_world();
$_POST['_wpnonce'] = wp_create_nonce( 'act' );
$token = dwell_token( 'scope-a', 45, 'act', 'AbCdEf012345' );
ck( 'the first use is accepted', WPCPM_Form_Guard::check_token( 'scope-a', $token ), 'ok' );
ck( 'and the second use of the same token is not', WPCPM_Form_Guard::check_token( 'scope-a', $token ), 'spam' );
ck( 'the claim behind it is one non-autoloaded option row', array( count( $GLOBALS['opts'] ) - 1, in_array( true, $GLOBALS['autoload'], true ) ), array( 1, false ) );
reset_world();
$_POST['_wpnonce'] = wp_create_nonce( 'act' );
// Well shaped (three parts), so the refusals below are the scope's and the signature's, not the shape's (Task 3 review).
ck( 'a token minted for another form is a forgery on this one', WPCPM_Form_Guard::check_token( 'scope-a', dwell_token( 'scope-b', 30, 'act', 'AbCdEf012345' ) ), 'spam' );
ck( 'a token nobody signed is refused', WPCPM_Form_Guard::check_token( 'scope-a', ( time() - 60 ) . '.AbCdEf012345.0123456789abcdef0123456789abcdef' ), 'spam' );
ck( 'and so is one that is not a token at all', WPCPM_Form_Guard::check_token( 'scope-a', 'nonsense' ), 'spam' );
ck( 'and so is one with a fourth part bolted on', WPCPM_Form_Guard::check_token( 'scope-a', dwell_token( 'scope-a', 30, 'act', 'AbCdEf012345' ) . '.extra' ), 'spam' );
// A minted token, taken apart and put back with an empty random half: the two shapes sign
// different strings, so this is a forgery and not a second spelling of the two-part one, which
// is what keeps single use exact across the release.
$two_part = dwell_token( 'scope-a', 30 );
ck( 'an empty random half is a forgery, not a second spelling of the shape without one', WPCPM_Form_Guard::check_token( 'scope-a', str_replace( '.', '..', $two_part ) ), 'spam' );
ck( 'a token minted before the S5 fix wave, in two parts, is a forgery now (1.98.1)', WPCPM_Form_Guard::check_token( 'scope-a', $two_part ), 'spam' );
$three_part = dwell_token( 'scope-a', 30, 'act', 'AbCdEf012345' );
ck( 'a three-part token of an ordinary age is accepted', WPCPM_Form_Guard::check_token( 'scope-a', $three_part ), 'ok' );
ck( 'and the same token with another random half is a forgery: the half is signed, not carried', WPCPM_Form_Guard::check_token( 'scope-a', str_replace( 'AbCdEf012345', 'zzzzzzzzzzzz', $three_part ) ), 'spam' );
ck( 'a token older than half a day is stale, which is not spam', WPCPM_Form_Guard::check_token( 'scope-a', dwell_token( 'scope-a', 13 * HOUR_IN_SECONDS, 'act', 'AbCdEf012345' ) ), 'stale' );
reset_world();
ck( 'a token checked with no nonce in the request is a forgery', WPCPM_Form_Guard::check_token( 'scope-a', dwell_token( 'scope-a', 30, 'act', 'AbCdEf012345' ) ), 'spam' );
$_POST['_wpnonce'] = 'nonce-something-else';
ck( 'a token posted with another form\'s nonce is refused', WPCPM_Form_Guard::check_token( 'scope-a', dwell_token( 'scope-a', 30, 'act', 'AbCdEf012345' ) ), 'spam' );

echo "\n=== 3. The per-actor ceiling ===\n";
reset_world();
ck( 'the actor key is the prefix and the hashed address', WPCPM_Form_Guard::actor_key( 'apply' ), 'apply:' . wp_hash( '203.0.113.7' ) );
ck( 'and never the address itself', strpos( WPCPM_Form_Guard::actor_key( 'apply' ), '203.0.113.7' ), false );
ck( 'two prefixes are two keys, so two forms have two allowances', WPCPM_Form_Guard::actor_key( 'sponsor-apply' ) === WPCPM_Form_Guard::actor_key( 'apply' ), false );
$taken = 0;
for ( $i = 0; $i < 5; $i++ ) {
	$taken += WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( 'apply' ) ) ? 1 : 0;
}
ck( 'five in an hour from one source are taken', $taken, 5 );
ck( 'and the sixth is refused', WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( 'apply' ) ), false );
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
ck( 'another source is untouched by it', WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( 'apply' ) ), true );
ck( 'the ceiling wrote one row per source, and nothing readable', count( $GLOBALS['opts'] ) - 1, 2 );
ck( 'a limit passed in is honored', array( WPCPM_Form_Guard::claim_actor( 'k:one', 1 ), WPCPM_Form_Guard::claim_actor( 'k:one', 1 ) ), array( true, false ) );

echo "\n=== 4. The site-wide ceiling ===\n";
reset_world();
$taken = 0;
for ( $i = 0; $i < 40; $i++ ) {
	$taken += WPCPM_Form_Guard::claim_site( 'apply-site' ) ? 1 : 0;
}
ck( 'forty in a day are taken', $taken, 40 );
ck( 'and the forty-first is not: the caller holds it rather than refusing', WPCPM_Form_Guard::claim_site( 'apply-site' ), false );
ck( 'the sponsor form\'s key is its own allowance', WPCPM_Form_Guard::claim_site( 'sponsor-apply-site' ), true );
ck( 'a day-long window: the same key claimed through the ceiling with a day window shares the count', array( WPCPM_Form_Guard::claim_site( 'k:site', 2 ), WPCPM_Ceiling::claim( 'k:site', 2, DAY_IN_SECONDS ), WPCPM_Form_Guard::claim_site( 'k:site', 2 ) ), array( true, true, false ) );

echo "\n=== 5. Consent ===\n";
ck( 'consent: "1" is a tick', WPCPM_Form_Guard::consented( '1' ), true );
ck( 'consent: "true" is a tick, in any case', array( WPCPM_Form_Guard::consented( 'true' ), WPCPM_Form_Guard::consented( 'TRUE' ) ), array( true, true ) );
ck( 'consent: "yes" is not', WPCPM_Form_Guard::consented( 'yes' ), false );
ck( 'consent: "0", "", null and an array are not', array( WPCPM_Form_Guard::consented( '0' ), WPCPM_Form_Guard::consented( '' ), WPCPM_Form_Guard::consented( null ), WPCPM_Form_Guard::consented( array( '1' ) ) ), array( false, false, false, false ) );
reset_world();
ck( 'the policy URL is the site\'s', WPCPM_Form_Guard::policy_url(), 'https://example.test/privacy/' );
$evidence = WPCPM_Form_Guard::consent_evidence( 'I confirm the sentence.' );
ck( 'the evidence is seven keys, in the order the queue prints them', array_keys( $evidence ), array( 'sentence', 'url', 'policy', 'modified', 'at', 'ip', 'agent' ) );
ck( 'the sentence as rendered, the policy and its version', array( $evidence['sentence'], $evidence['url'], $evidence['policy'], $evidence['modified'] ), array( 'I confirm the sentence.', 'https://example.test/privacy/', 43, '2026-08-20 11:30:00' ) );
ck( 'when, the truncated address and the browser', array( $evidence['at'] > 0, $evidence['ip'], $evidence['agent'] ), array( true, '203.0.113.0', 'Mozilla/5.0 (test)' ) );
$GLOBALS['policy'] = '';
unset( $GLOBALS['opts']['wp_page_for_privacy_policy'] );
$none = WPCPM_Form_Guard::consent_evidence( 'x' );
ck( 'a site with no policy records none, and the caller refuses to draw the form', array( WPCPM_Form_Guard::policy_url(), $none['url'], $none['policy'], $none['modified'] ), array( '', '', 0, '' ) );

echo "\n=== 6. Links ===\n";
ck( 'no prose, no links', WPCPM_Form_Guard::links_in( '' ), 0 );
ck( 'links are counted by scheme, whichever case', WPCPM_Form_Guard::links_in( 'See https://one.example and HTTP://two.example, not ftp://x' ), 2 );
ck( 'two links are not too many at the default', WPCPM_Form_Guard::too_many_links( 'https://a https://b' ), false );
ck( 'three are', WPCPM_Form_Guard::too_many_links( 'https://a https://b https://c' ), true );
ck( 'and the ceiling can be the caller\'s', WPCPM_Form_Guard::too_many_links( 'https://a', 1 ), true );

echo "\n=== 7. The mail ceiling ===\n";
reset_world();
ck( 'a mail claim counts down and refuses', array( WPCPM_Form_Guard::claim_mail( 'apply-mail', 2 ), WPCPM_Form_Guard::claim_mail( 'apply-mail', 2 ), WPCPM_Form_Guard::claim_mail( 'apply-mail', 2 ) ), array( true, true, false ) );
reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
	WPCPM_Ceiling::claim( 'apply-mail', WPCPM_Form_Guard::MAIL_PER_DAY, DAY_IN_SECONDS );
}
ck( 'the window is a day, so a suite that exhausts the key through the ceiling exhausts the guard', WPCPM_Form_Guard::claim_mail( 'apply-mail' ), false );

echo "\n=== The helpers ===\n";
ck( 'an IPv4 address loses its last octet', WPCPM_Form_Guard::truncate_ip( '203.0.113.7' ), '203.0.113.0' );
ck( 'an IPv6 address keeps its first four groups', WPCPM_Form_Guard::truncate_ip( '2001:db8:85a3:8d3:1319:8a2e:370:7348' ), '2001:db8:85a3:8d3::' );
ck( 'and nothing stays nothing', WPCPM_Form_Guard::truncate_ip( '' ), '' );
reset_world();
$_SERVER['HTTP_USER_AGENT'] = str_repeat( 'a', 300 );
ck( 'the browser is recorded to two hundred characters', strlen( WPCPM_Form_Guard::user_agent() ), 200 );
unset( $_SERVER['HTTP_USER_AGENT'] );
ck( 'and a request with none records none', WPCPM_Form_Guard::user_agent(), '' );

echo "\n=== Which address the request came from ===\n";
reset_world();
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.5, 10.0.0.1';
ck( 'a forwarded header from nowhere in particular is ignored', WPCPM_Form_Guard::client_ip(), '203.0.113.7' );
$GLOBALS['settings']['application_trusted_proxy'] = '192.0.2.1';
ck( 'and so is one from an address that is not the known edge', WPCPM_Form_Guard::client_ip(), '203.0.113.7' );
$GLOBALS['settings']['application_trusted_proxy'] = '203.0.113.7';
ck( 'the known edge is believed, and its own entry is the rightmost', WPCPM_Form_Guard::client_ip(), '10.0.0.1' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 10.0.0.1';
ck( 'so an address the client wrote in front of it changes nothing', WPCPM_Form_Guard::client_ip(), '10.0.0.1' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 203.0.113.7';
ck( 'the edge itself, appearing in the chain, is skipped for the entry before it', WPCPM_Form_Guard::client_ip(), '10.0.0.1' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.5, not an address';
ck( 'an entry that is not an address is skipped for the next', WPCPM_Form_Guard::client_ip(), '198.51.100.5' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not an address at all';
ck( 'a header the edge sent that is not an address falls back', WPCPM_Form_Guard::client_ip(), '203.0.113.7' );
$_SERVER['REMOTE_ADDR'] = 'nonsense';
ck( 'and a connection with no address at all answers empty', WPCPM_Form_Guard::client_ip(), '' );

echo "\n=== House rules ===\n";
$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the guard verifies no nonce of its own: that stays in each handler', array( strpos( $src, 'wp_verify_nonce(' ), strpos( $src, 'check_admin_referer' ) ), array( false, false ) );
ck( 'the posted nonce is read in one place, to re-derive a signature', substr_count( $src, "\$_POST['_wpnonce']" ), 1 );
ck( 'every ceiling goes through WPCPM_Ceiling and hashes nothing readable', array( substr_count( $src, 'WPCPM_Ceiling::claim(' ), strpos( $src, 'wp_hash( self::client_ip() )' ) !== false ), array( 4, true ) );
ck( 'nothing here moves a file or reads a query string', preg_match( '/\$_GET|\$_FILES|wp_handle_upload|move_uploaded_file/', $src ), 0 );

$institution = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php' );
ck( 'the institution class aliases the six numbers rather than repeating them', array(
	false !== strpos( $institution, 'const MIN_SECONDS = WPCPM_Form_Guard::MIN_SECONDS;' ),
	false !== strpos( $institution, 'const TOKEN_LIFETIME = WPCPM_Form_Guard::TOKEN_LIFETIME;' ),
	false !== strpos( $institution, 'const PER_HOUR = WPCPM_Form_Guard::PER_HOUR;' ),
	false !== strpos( $institution, 'const PER_DAY = WPCPM_Form_Guard::PER_DAY;' ),
	false !== strpos( $institution, 'const MAIL_PER_DAY = WPCPM_Form_Guard::MAIL_PER_DAY;' ),
	false !== strpos( $institution, 'const MAX_LINKS = WPCPM_Form_Guard::MAX_LINKS;' ),
), array( true, true, true, true, true, true ) );
ck( 'and keeps its own dwell scope, so every token its suite mints still checks', false !== strpos( $institution, "const DWELL_SCOPE = 'wpcpm-application-dwell';" ), true );
ck( 'the institution class holds no copy of the guard any more', preg_match( '/function (sign_token|truncate_ip|user_agent)\(/', $institution ), 0 );
ck( 'and reaches every stage through the extraction', substr_count( $institution, 'WPCPM_Form_Guard::' ) >= 12, true );
ck( 'it loads the guard itself, so its own suite needs no seventh require', false !== strpos( $institution, "require_once dirname( __DIR__ ) . '/class-wpcpm-form-guard.php';" ), true );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
