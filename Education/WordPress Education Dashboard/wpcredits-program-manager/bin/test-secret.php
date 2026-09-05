<?php
/**
 * WPCPM_Secret: the site key made once, the seal and unseal every store uses, and the two
 * things the sponsor codes add to it.
 *
 * What is pinned here and why:
 *
 * - The format WPCPM_Private_Files has always written (one version byte, 12 of nonce, 16 of
 *   tag, the ciphertext) is the format WPCPM_Secret writes, so every agreement sealed before
 *   the extraction (1.94.0) still opens.
 * - A sealed value bound for an option is base64: wpdb strips bytes that are not valid UTF-8
 *   from a utf8mb4 column, and ciphertext is not text (plan ruling 1).
 * - The duplicate fingerprint is keyed: a bare SHA-256 of a six-character coupon code is
 *   enumerated in seconds by anyone who can read the option (plan ruling 2).
 *
 * Run from the plugin root:  php bin/test-secret.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
/** `add_option()` is a test-and-set: it fails when the row exists, which is what the key relies on. */
function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = ( 'yes' === $autoload || true === $autoload );
	return true;
}

require_once __DIR__ . '/../includes/class-wpcpm-secret.php';

$fail = 0; $checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	$checks++;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

echo "=== The seal ===\n";
ck( 'this PHP can encrypt', WPCPM_Secret::can_encrypt(), true );
$sealed = WPCPM_Secret::seal( 'WPCE-2026-ABCD' );
ck( 'a sealed value is a string', is_string( $sealed ), true );
ck( 'and leads with the format version', ord( $sealed[0] ), WPCPM_Secret::FORMAT );
ck( 'the format version is the one the private files store has always written', WPCPM_Secret::FORMAT, 1 );
ck( 'and it is at least a version byte, a nonce, a tag and one byte of ciphertext long', strlen( $sealed ) >= 30, true );
ck( 'it unseals to what went in', WPCPM_Secret::unseal( $sealed ), 'WPCE-2026-ABCD' );
ck( 'sealing the same text twice gives two different blobs (a fresh nonce each time)', WPCPM_Secret::seal( 'WPCE-2026-ABCD' ) === $sealed, false );
$tampered = $sealed;
$tampered[ strlen( $tampered ) - 1 ] = chr( ord( $tampered[ strlen( $tampered ) - 1 ] ) ^ 1 );
$broken = WPCPM_Secret::unseal( $tampered );
ck( 'a changed byte is a refusal, not garbage', array( is_wp_error( $broken ), $broken->get_error_code() ), array( true, 'wpcpm_private_tampered' ) );
$short = WPCPM_Secret::unseal( 'nope' );
ck( 'something too short to be a blob is refused as the wrong format', array( is_wp_error( $short ), $short->get_error_code() ), array( true, 'wpcpm_private_format' ) );
$wrong = WPCPM_Secret::unseal( chr( 9 ) . substr( $sealed, 1 ) );
ck( 'and so is a blob from a format version this code never wrote', $wrong->get_error_code(), 'wpcpm_private_format' );

echo "\n=== The key ===\n";
ck( 'the key is made once, on first use, as 64 hex characters', array( isset( $GLOBALS['opts']['wpcpm_private_key'] ), 1 === preg_match( '/^[0-9a-f]{64}$/', (string) $GLOBALS['opts']['wpcpm_private_key'] ) ), array( true, true ) );
ck( 'under the option name the private files store has always used', WPCPM_Secret::OPT_KEY, 'wpcpm_private_key' );
ck( 'and is not autoloaded', $GLOBALS['autoload']['wpcpm_private_key'], false );
$key = $GLOBALS['opts']['wpcpm_private_key'];
WPCPM_Secret::seal( 'again' );
ck( 'a second seal does not make a second key', $GLOBALS['opts']['wpcpm_private_key'], $key );
$GLOBALS['opts']['wpcpm_private_key'] = 'not-a-key';
$refused = WPCPM_Secret::seal( 'x' );
ck( 'a stored key that is not one is refused rather than used', array( is_wp_error( $refused ), $refused->get_error_code() ), array( true, 'wpcpm_private_key' ) );
$GLOBALS['opts']['wpcpm_private_key'] = str_repeat( 'a', 64 );
$other = WPCPM_Secret::unseal( $sealed );
ck( 'a blob sealed under another key does not open', $other->get_error_code(), 'wpcpm_private_tampered' );
$GLOBALS['opts']['wpcpm_private_key'] = $key;

echo "\n=== For an option ===\n";
$stored = WPCPM_Secret::seal_for_option( 'https://checkout.example.test/?code=ABCD' );
ck( 'a value sealed for an option is base64, so wpdb cannot strip a byte of it', array( is_string( $stored ), 1 === preg_match( '/^[A-Za-z0-9+\/]+=*$/', $stored ) ), array( true, true ) );
ck( 'and it round-trips', WPCPM_Secret::unseal_from_option( $stored ), 'https://checkout.example.test/?code=ABCD' );
$bad = WPCPM_Secret::unseal_from_option( '***not base64***' );
ck( 'something that is not base64 is refused as the wrong format', array( is_wp_error( $bad ), $bad->get_error_code() ), array( true, 'wpcpm_private_format' ) );
ck( 'an empty text seals and unseals to an empty text', WPCPM_Secret::unseal_from_option( WPCPM_Secret::seal_for_option( '' ) ), '' );

echo "\n=== The fingerprint ===\n";
$fp = WPCPM_Secret::fingerprint( 'ABCD-1234' );
ck( 'a fingerprint is 64 hex characters', 1 === preg_match( '/^[0-9a-f]{64}$/', (string) $fp ), true );
ck( 'equal texts give equal fingerprints', WPCPM_Secret::fingerprint( 'ABCD-1234' ), $fp );
ck( 'different texts give different ones', WPCPM_Secret::fingerprint( 'ABCD-1235' ) === $fp, false );
ck( 'and it is not a bare SHA-256 anyone could enumerate', hash( 'sha256', 'ABCD-1234' ) === $fp, false );
$GLOBALS['opts']['wpcpm_private_key'] = 'broken';
$nofp = WPCPM_Secret::fingerprint( 'x' );
ck( 'without a usable key there is no fingerprint, not a keyless one', array( is_wp_error( $nofp ), $nofp->get_error_code() ), array( true, 'wpcpm_private_key' ) );
$GLOBALS['opts']['wpcpm_private_key'] = $key;

echo "\n=== House rules ===\n";
$src = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-secret.php' );
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the key is never printed or sent: no echo, print or wp_mail in the class', preg_match( '/\becho\b|\bprint\b|wp_mail/', $src ), 0 );
$store = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-private-files.php' );
ck( 'the private files store no longer carries a cipher of its own', preg_match( '/openssl_(en|de)crypt|random_bytes\( 32 \)|hex2bin/', $store ), 0 );
ck( 'and delegates to WPCPM_Secret', substr_count( $store, 'WPCPM_Secret::' ) >= 3, true );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
