<?php
/**
 * WPCPM_Image_Upload: each rule refuses on its own, and the bytes stored are the editor's.
 *
 * Run: php bin/test-image-upload.php   (needs the GD extension, which the CLI here has)
 *
 * @package WPCreditsProgramManager
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $t ) { return $t; }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_http_validate_url( $url ) { return preg_match( '#^https?://[a-z0-9.-]+/#i', (string) $url ) ? $url : false; }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $n ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function wp_unique_filename( $dir, $name ) { $i = 0; $try = $name; while ( file_exists( $dir . '/' . $try ) ) { $try = preg_replace( '/(\.[a-z]+)$/', '-' . ( ++$i ) . '$1', $name ); } return $try; }
function wp_upload_dir() {
	if ( ! empty( $GLOBALS['upload_dir_override'] ) ) {
		return array( 'path' => $GLOBALS['upload_dir_override'], 'url' => 'https://example.test/uploads', 'error' => false );
	}
	$dir = sys_get_temp_dir() . '/wpcpm-uploads-' . getmypid();
	if ( ! is_dir( $dir ) ) { mkdir( $dir ); }
	return array( 'path' => $dir, 'url' => 'https://example.test/uploads', 'error' => false );
}
function wp_insert_attachment( array $a, $file, $parent = 0, $wp_error = false ) { $GLOBALS['attachments'][] = array_merge( $a, array( 'file' => $file ) ); return count( $GLOBALS['attachments'] ) + 100; }
function wp_generate_attachment_metadata( $id, $file ) { return array( 'file' => basename( $file ) ); }
function wp_update_attachment_metadata( $id, $data ) { $GLOBALS['meta'][ $id ] = $data; return true; }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function download_url( $url, $timeout = 300 ) { if ( empty( $GLOBALS['download'][ $url ] ) ) { return new WP_Error( 'http_404', 'Not Found' ); } $tmp = tempnam( sys_get_temp_dir(), 'dl' ); copy( $GLOBALS['download'][ $url ], $tmp ); $GLOBALS['downloaded'][] = $tmp; return $tmp; }
class WPCPM_Settings { public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } }
// The editor: what WordPress does with a real image, in miniature. `save()` writes a copy and
// says so, which is what "the bytes served are bytes WordPress wrote" means here.
class WPCPM_Test_Editor {
	private $path;
	public function __construct( $path ) { $this->path = $path; }
	public function save( $dest, $mime = null ) { copy( $this->path, $dest ); $GLOBALS['editor_calls'][] = array( $this->path, $dest, $mime ); $size = getimagesize( $dest ); return array( 'path' => $dest, 'file' => basename( $dest ), 'width' => $size[0], 'height' => $size[1], 'mime-type' => $size['mime'] ); }
}
function wp_get_image_editor( $path ) { return $GLOBALS['no_editor'] ? new WP_Error( 'image_no_editor', 'No editor' ) : new WPCPM_Test_Editor( $path ); }

require_once __DIR__ . '/../includes/class-wpcpm-image-upload.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}
function code( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : 'accepted'; }
function png( $w, $h ) { $p = tempnam( sys_get_temp_dir(), 'img' ) . '.png'; $im = imagecreatetruecolor( $w, $h ); imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 30, 30 ) ); imagepng( $im, $p ); return $p; }
function jpg( $w, $h ) { $p = tempnam( sys_get_temp_dir(), 'img' ) . '.jpg'; $im = imagecreatetruecolor( $w, $h ); imagejpeg( $im, $p, 80 ); return $p; }

$GLOBALS['attachments'] = array();
$GLOBALS['editor_calls'] = array();
$GLOBALS['no_editor'] = false;
$GLOBALS['settings'] = array();

echo "=== Each rule refuses on its own ===\n";
ck( 'a missing file', code( WPCPM_Image_Upload::accept( '/nowhere/logo.png' ) ), 'wpcpm_image_missing' );
$svg = tempnam( sys_get_temp_dir(), 'img' ) . '.png';
file_put_contents( $svg, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><script>alert(1)</script></svg>' );
ck( 'SVG is refused by its content, whatever the name says', code( WPCPM_Image_Upload::accept( $svg ) ), 'wpcpm_image_type' );
$txt = tempnam( sys_get_temp_dir(), 'img' ) . '.png';
file_put_contents( $txt, 'not an image at all' );
ck( 'and so is text', code( WPCPM_Image_Upload::accept( $txt ) ), 'wpcpm_image_type' );
ck( 'a PNG under a .jpg name is refused: the name and the bytes disagree', code( WPCPM_Image_Upload::accept( png( 300, 100 ), array( 'name' => 'logo.jpg' ) ) ), 'wpcpm_image_name' );
ck( 'narrower than 200px', code( WPCPM_Image_Upload::accept( png( 199, 100 ) ) ), 'wpcpm_image_dimensions' );
ck( 'a side past 4000px', code( WPCPM_Image_Upload::accept( png( 4001, 100 ) ) ), 'wpcpm_image_dimensions' );
ck( 'over the size ceiling', code( WPCPM_Image_Upload::accept( png( 1500, 1500 ), array( 'max_kb' => 1 ) ) ), 'wpcpm_image_size' );
$GLOBALS['no_editor'] = true;
ck( 'no editor on the host is a refusal, never a raw copy', code( WPCPM_Image_Upload::accept( png( 300, 100 ) ) ), 'wpcpm_image_editor' );
$GLOBALS['no_editor'] = false;

echo "\n=== Accepting ===\n";
$source   = png( 300, 100 );
$accepted = WPCPM_Image_Upload::accept( $source, array( 'name' => 'Weglot Logo Light-1.png' ) );
ck( 'a PNG the right size is accepted with its facts', is_array( $accepted ) ? array( $accepted['mime'], $accepted['ext'], $accepted['width'], $accepted['height'] ) : $accepted, array( 'image/png', 'png', 300, 100 ) );
ck( 'the bytes are the editor\'s: a re-saved copy, not the upload', is_array( $accepted ) && $accepted['path'] !== $source && file_exists( $accepted['path'] ), true );
ck( 'the editor was asked once, for this file', count( $GLOBALS['editor_calls'] ) === 1 && $GLOBALS['editor_calls'][0][0] === $source, true );
$orphans = array_filter( (array) glob( get_temp_dir() . 'wpcpm-image-*' ), static function ( $f ) { return is_file( $f ) && 0 === filesize( $f ); } );
ck( 'the name reserved by tempnam() leaves no zero-byte file behind', array_values( $orphans ), array() );
$jpeg = WPCPM_Image_Upload::accept( jpg( 640, 480 ), array( 'name' => 'photo.jpeg' ) );
ck( 'a JPEG is accepted, .jpeg and .jpg both naming it', is_array( $jpeg ) ? array( $jpeg['mime'], $jpeg['ext'] ) : $jpeg, array( 'image/jpeg', 'jpg' ) );
ck( 'the default ceiling is the setting, a megabyte', WPCPM_Image_Upload::max_bytes( array() ), 1024 * 1024 );
$GLOBALS['settings']['logo_max_kb'] = 2048;
ck( 'and follows the setting', WPCPM_Image_Upload::max_bytes( array() ), 2048 * 1024 );

echo "\n=== Storing ===\n";
$id = WPCPM_Image_Upload::store( $accepted, 'Weglot logo', 7, 'Weglot logo (colour)' );
ck( 'store() inserts an attachment', $id, 101 );
$att = $GLOBALS['attachments'][0];
ck( 'with the type, the title, the author and inherit status', array( $att['post_mime_type'], $att['post_title'], $att['post_author'], $att['post_status'] ), array( 'image/png', 'Weglot logo (colour)', 7, 'inherit' ) );
ck( 'the file lives in the upload directory under a clean name', dirname( $att['file'] ) === wp_upload_dir()['path'] && 'Weglot-logo.png' === basename( $att['file'] ), true );
ck( 'and the re-saved temporary copy is gone', file_exists( $accepted['path'] ), false );
ck( 'metadata was generated', isset( $GLOBALS['meta'][101]['file'] ), true );
$broken           = WPCPM_Image_Upload::accept( png( 300, 100 ), array( 'name' => 'Weglot Logo Light-1.png' ) );
$GLOBALS['upload_dir_override'] = sys_get_temp_dir() . '/wpcpm-test-missing-dir-' . getmypid();
// copy() to a directory that does not exist is the refusal under test here, not a bug in
// this suite: the warning it raises is expected and swallowed for the one call.
set_error_handler( function () { return true; } );
$store_result = WPCPM_Image_Upload::store( $broken, 'Weglot logo', 7, 'Weglot logo (colour)' );
restore_error_handler();
unset( $GLOBALS['upload_dir_override'] );
ck( 'store() refuses when copy() fails', code( $store_result ), 'wpcpm_image_store' );
ck( 'and the re-saved temporary file is cleaned up rather than left behind', file_exists( $broken['path'] ), false );

echo "\n=== Sideloading ===\n";
$GLOBALS['download'] = array( 'https://v5.airtableusercontent.com/x/logo.png' => png( 400, 120 ), 'https://v5.airtableusercontent.com/x/logo.svg' => $svg );
$id = WPCPM_Image_Upload::sideload( 'https://v5.airtableusercontent.com/x/logo.png', 'logo.png', 0, 'Acme logo' );
ck( 'a logo is fetched, accepted and stored', $id, 102 );
ck( 'with no author: the sync has none', $GLOBALS['attachments'][1]['post_author'], 0 );
ck( 'the downloaded temporary file is removed', file_exists( $GLOBALS['downloaded'][0] ), false );
ck( 'an SVG at the other end is refused by content', code( WPCPM_Image_Upload::sideload( 'https://v5.airtableusercontent.com/x/logo.svg', 'logo.svg', 0, 'x' ) ), 'wpcpm_image_type' );
ck( 'a URL that is not http(s) is refused before any request', code( WPCPM_Image_Upload::sideload( 'ftp://example.test/logo.png', 'logo.png', 0, 'x' ) ), 'wpcpm_image_url' );
ck( 'a download that fails is a refusal that names the download', code( WPCPM_Image_Upload::sideload( 'https://v5.airtableusercontent.com/x/missing.png', 'missing.png', 0, 'x' ) ), 'wpcpm_image_download' );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/class-wpcpm-image-upload.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'wp_handle_upload() is never trusted here', strpos( $src, 'wp_handle_upload' ), false );
ck( 'SVG is named nowhere as a type it takes', strpos( $src, "'image/svg+xml' =>" ), false );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', 31 );
exit( $fail ? 1 : 0 );
