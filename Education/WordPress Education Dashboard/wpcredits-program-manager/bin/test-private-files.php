<?php
/**
 * The private file store: where a signed agreement lives, and what protects it.
 *
 * The host this program runs on hands out any file under `wp-content/uploads` to anyone who
 * knows its name, and will not add a rule to stop doing so. Probing it on 2 September 2026:
 * a file in `uploads/wpcpm-probe-plain/` answered 200 with its body, the same file in
 * `uploads/.wpcpm-probe-dot/` answered 403 with none. So the store leans on two things it can
 * do for itself, and this suite pins both:
 *
 * 1. The directory name begins with a dot, which is what the host's own rule refuses. The
 *    probe measures it against a control path so the card cannot credit the dot for a refusal
 *    that was really the host blocking everything.
 * 2. Every file is encrypted before it is written, so the day the host changes its mind, what
 *    it hands over is ciphertext.
 *
 * The assertions that matter most here are the negative ones: that no plaintext byte ever
 * reaches the disk, and that a file changed behind the plugin's back is refused rather than
 * returned to a reviewer as if it were the document that was signed.
 *
 * Run from the plugin root:  php bin/test-private-files.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['head']     = array( 'response' => array( 'code' => 403 ) );
$GLOBALS['heads']    = array();
$GLOBALS['uploads']  = rtrim( sys_get_temp_dir(), '/' ) . '/wpcpm-private-test-' . getmypid();

class WP_Error {
	private $code;
	private $message;
	public function __construct( $c = '', $m = '', $d = null ) {
		$this->code    = $c;
		$this->message = $m;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_upload_dir( $t = null, $c = true ) {
	return array(
		'basedir' => $GLOBALS['uploads'],
		'baseurl' => 'https://example.test/wp-content/uploads',
	);
}
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function wp_is_writable( $p ) { return is_writable( $p ); }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function wp_generate_password( $len = 12, $special = true, $extra = false ) {
	return substr( str_replace( array( '+', '/', '=' ), '', base64_encode( random_bytes( $len * 2 ) ) ), 0, $len );
}
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function add_option( $k, $v, $dep = '', $a = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ]     = $v;
	$GLOBALS['autoload'][ $k ] = $a;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_remote_head( $url, $args = array() ) {
	$GLOBALS['heads'][] = $url;
	// A dot anywhere in the path is the host's own refusal; anything else is served. This is
	// exactly what the real host does, measured.
	if ( false !== strpos( wp_parse_url( $url, PHP_URL_PATH ), '/.' ) ) {
		return array( 'response' => array( 'code' => 403 ) );
	}
	return $GLOBALS['head'];
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['response']['code'] : 0; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}
function rmrf( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		is_dir( $path ) ? rmrf( $path ) : unlink( $path );
	}
	rmdir( $dir );
}
register_shutdown_function( function () { rmrf( $GLOBALS['uploads'] ); } );

/* ---- the directory ------------------------------------------------------ */

echo "=== The directory the host refuses ===\n";

ck( 'the directory name begins with a dot', 0 === strpos( WPCPM_Private_Files::DIRECTORY, '.' ), true );
ck( 'and the base sits under uploads', WPCPM_Private_Files::base(), $GLOBALS['uploads'] . '/.wpcpm-private/' );
ck( 'the URL path names the directory, never a file', WPCPM_Private_Files::url_path(), '/wp-content/uploads/.wpcpm-private/' );
ck( 'ensure() makes it with both guard files', array(
	true === WPCPM_Private_Files::ensure(),
	is_dir( WPCPM_Private_Files::base() ),
	file_exists( WPCPM_Private_Files::base() . 'index.php' ),
	file_exists( WPCPM_Private_Files::base() . '.htaccess' ),
), array( true, true, true, true ) );

/* ---- the round trip ----------------------------------------------------- */

echo "\n=== Storing and reading back ===\n";

$pdf    = "%PDF-1.7\nsigned by a rector\n%%EOF\n";
$stored = WPCPM_Private_Files::store( $pdf, 'pdf' );

ck( 'store() returns a path, a checksum and a size', array( is_array( $stored ) && isset( $stored['path'], $stored['sha256'], $stored['size'] ) ), array( true ) );
ck( 'the checksum is of the plaintext', $stored['sha256'], hash( 'sha256', $pdf ) );
ck( 'and the size is the plaintext length', $stored['size'], strlen( $pdf ) );
ck( 'the path is relative, under a year folder, with 32 hex characters', 1 === preg_match( '#^\d{4}/[0-9a-f]{32}\.pdf$#', $stored['path'] ), true );
ck( 'the file is on disk', file_exists( WPCPM_Private_Files::base() . $stored['path'] ), true );
ck( 'read() gives back exactly what was stored', WPCPM_Private_Files::read( $stored['path'] ), $pdf );

// The whole point: what is on disk is not the document.
$raw = file_get_contents( WPCPM_Private_Files::base() . $stored['path'] );
ck( 'no plaintext byte reached the disk', false === strpos( $raw, 'signed by a rector' ), true );
ck( 'nor the PDF header, which is what a scanner would look for', false === strpos( $raw, '%PDF' ), true );
ck( 'the stored file is longer than the plaintext by the version, nonce and tag', strlen( $raw ), strlen( $pdf ) + 29 );
ck( 'and starts with the format version', ord( $raw[0] ), WPCPM_Private_Files::FORMAT );

/* ---- the key ------------------------------------------------------------ */

echo "\n=== The key ===\n";

ck( 'is made once, on first use', array( isset( $GLOBALS['opts']['wpcpm_private_key'] ), 1 === preg_match( '/^[0-9a-f]{64}$/', $GLOBALS['opts']['wpcpm_private_key'] ) ), array( true, true ) );
ck( 'and is not autoloaded', $GLOBALS['autoload']['wpcpm_private_key'], false );

$key    = $GLOBALS['opts']['wpcpm_private_key'];
$second = WPCPM_Private_Files::store( 'another', 'pdf' );
ck( 'a second file does not make a second key', $GLOBALS['opts']['wpcpm_private_key'], $key );
ck( 'and the two files are not the same bytes, though one plaintext repeats', file_get_contents( WPCPM_Private_Files::base() . $second['path'] ) !== file_get_contents( WPCPM_Private_Files::base() . $stored['path'] ), true );

// Two files with identical contents must not produce identical ciphertext, or the store leaks
// which institutions submitted the same document.
$a = WPCPM_Private_Files::store( 'identical', 'pdf' );
$b = WPCPM_Private_Files::store( 'identical', 'pdf' );
ck( 'the same document stored twice looks different on disk', file_get_contents( WPCPM_Private_Files::base() . $a['path'] ) !== file_get_contents( WPCPM_Private_Files::base() . $b['path'] ), true );
ck( 'and both still read back', array( WPCPM_Private_Files::read( $a['path'] ), WPCPM_Private_Files::read( $b['path'] ) ), array( 'identical', 'identical' ) );

$GLOBALS['opts']['wpcpm_private_key'] = str_repeat( 'a', 64 );
$wrong                                = WPCPM_Private_Files::read( $stored['path'] );
ck( 'a replaced key does not decrypt, and says so', array( is_wp_error( $wrong ), $wrong->get_error_code() ), array( true, 'wpcpm_private_tampered' ) );
$GLOBALS['opts']['wpcpm_private_key'] = $key;
ck( 'and the file is readable again once the real key is back', WPCPM_Private_Files::read( $stored['path'] ), $pdf );

$GLOBALS['opts']['wpcpm_private_key'] = 'not-a-key';
$broken                               = WPCPM_Private_Files::read( $stored['path'] );
ck( 'a key that is not one is refused rather than used', array( is_wp_error( $broken ), $broken->get_error_code() ), array( true, 'wpcpm_private_key' ) );
$GLOBALS['opts']['wpcpm_private_key'] = $key;

/* ---- tampering ---------------------------------------------------------- */

echo "\n=== A file changed behind the plugin's back ===\n";

$path = WPCPM_Private_Files::base() . $stored['path'];
$good = file_get_contents( $path );
$bent = $good;
$bent[ strlen( $bent ) - 1 ] = chr( ( ord( $bent[ strlen( $bent ) - 1 ] ) + 1 ) % 256 );
file_put_contents( $path, $bent );

$result = WPCPM_Private_Files::read( $stored['path'] );
ck( 'one flipped byte is refused, not returned', array( is_wp_error( $result ), $result->get_error_code() ), array( true, 'wpcpm_private_tampered' ) );

file_put_contents( $path, 'plain text somebody dropped in' );
$result = WPCPM_Private_Files::read( $stored['path'] );
ck( 'a file this store did not write is refused by its format', array( is_wp_error( $result ), $result->get_error_code() ), array( true, 'wpcpm_private_format' ) );

file_put_contents( $path, $good );
ck( 'and the original still reads', WPCPM_Private_Files::read( $stored['path'] ), $pdf );

/* ---- what may be stored ------------------------------------------------- */

echo "\n=== What may be stored ===\n";

$empty = WPCPM_Private_Files::store( '', 'pdf' );
ck( 'nothing is not a file', array( is_wp_error( $empty ), $empty->get_error_code() ), array( true, 'wpcpm_private_empty' ) );
// The list is an allowlist, and `php` is the reason it is one: the directory sits under the
// document root, so an accepted extension is a name the host could be asked to execute.
foreach ( array( 'php', 'phtml', 'html', 'PDF', 'p hp', '../x', '', 'pdf.php' ) as $bad ) {
	$refused = WPCPM_Private_Files::store( 'x', $bad );
	ck( sprintf( 'the extension %s is refused', '' === $bad ? '(empty)' : $bad ), array( is_wp_error( $refused ), is_wp_error( $refused ) ? $refused->get_error_code() : '' ), array( true, 'wpcpm_private_extension' ) );
}
ck( 'and the allowlist is the whole story', WPCPM_Private_Files::EXTENSIONS, array( 'pdf' ) );
$ok = WPCPM_Private_Files::store( 'x', 'pdf' );
ck( 'pdf is accepted', is_wp_error( $ok ), false );

/* ---- reading what is not there ------------------------------------------ */

echo "\n=== Reading what is not there ===\n";

foreach ( array( '../../wp-config.php', '2026/../../../etc/passwd', 'no-such-file.pdf', '' ) as $bad ) {
	$refused = WPCPM_Private_Files::read( $bad );
	ck( sprintf( 'read(%s) is refused', '' === $bad ? '(empty)' : $bad ), array( is_wp_error( $refused ), $refused->get_error_code() ), array( true, 'wpcpm_private_missing' ) );
}
ck( 'forget() removes a file', array( WPCPM_Private_Files::forget( $ok['path'] ), file_exists( WPCPM_Private_Files::base() . $ok['path'] ) ), array( true, false ) );
ck( 'and forgetting what is gone is not a failure', WPCPM_Private_Files::forget( $ok['path'] ), true );

/* ---- the probe ---------------------------------------------------------- */

echo "\n=== The probe, and its control ===\n";

// The host as measured: a dot in the path is refused, anything else is served.
$GLOBALS['head']  = array( 'response' => array( 'code' => 200 ) );
$GLOBALS['heads'] = array();
$result           = WPCPM_Private_Files::probe();

ck( 'the real path is refused by the host', array( $result['status'], $result['blocked'], WPCPM_Private_Files::verdict( $result ) ), array( 403, true, 'blocked' ) );
ck( 'the control path, without the dot, is served', array( $result['control_status'] >= 200 && $result['control_status'] < 300 ), array( true ) );
ck( 'so the refusal is the dot and not the host blocking uploads wholesale', array( $result['status'], $result['control_status'] ), array( 403, 200 ) );
ck( 'the probe records that this site can encrypt', $result['encrypted'], true );
ck( 'two requests were made, the real one first', array( count( $GLOBALS['heads'] ), false !== strpos( $GLOBALS['heads'][0], '/.wpcpm-private/' ), false !== strpos( $GLOBALS['heads'][1], '/wpcpm-private/' ) ), array( 2, true, true ) );
ck( 'neither probe file is left behind', array( glob( WPCPM_Private_Files::base() . 'probe-*' ), glob( $GLOBALS['uploads'] . '/wpcpm-private/probe-*' ) ), array( array(), array() ) );
ck( 'the result is stored non-autoloaded', $GLOBALS['autoload']['wpcpm_private_probe'], false );
ck( 'probe_result() reads it back', WPCPM_Private_Files::probe_result(), $result );

// A record written before the control existed must read as "not measured", never as a refusal.
$GLOBALS['opts']['wpcpm_private_probe'] = array( 'status' => 403, 'time' => 100, 'blocked' => true, 'error' => '' );
ck( 'an older record reads the control as not measured', WPCPM_Private_Files::probe_result()['control_status'], 0 );

/* ---- the move out of the old directory ---------------------------------- */

echo "\n=== Moving out of the directory the host serves ===\n";

$legacy = $GLOBALS['uploads'] . '/wpcpm-private/';
wp_mkdir_p( $legacy . '2026' );
file_put_contents( $legacy . 'index.php', '<?php' );
file_put_contents( $legacy . '.htaccess', 'Deny from all' );
file_put_contents( $legacy . '2026/abcdef.pdf', 'an agreement from before' );
file_put_contents( $legacy . 'loose.pdf', 'another one' );

WPCPM_Private_Files::ensure();

ck( 'the old directory is gone', is_dir( $legacy ), false );
ck( 'and both files moved across, keeping their paths', array(
	file_exists( WPCPM_Private_Files::base() . '2026/abcdef.pdf' ),
	file_get_contents( WPCPM_Private_Files::base() . '2026/abcdef.pdf' ),
	file_exists( WPCPM_Private_Files::base() . 'loose.pdf' ),
), array( true, 'an agreement from before', true ) );
ck( 'running it again changes nothing', array( true === WPCPM_Private_Files::ensure(), is_dir( $legacy ) ), array( true, false ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
