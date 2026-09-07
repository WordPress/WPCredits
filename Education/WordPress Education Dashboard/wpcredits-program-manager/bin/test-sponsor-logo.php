<?php
/**
 * WPCPM_Sponsor_Logo: two optional images, one record, one write back to Airtable.
 *
 * Design spec of 4 September 2026, section 8.1 and decision 11. What is worth pinning:
 *
 * - **Every byte goes through `WPCPM_Image_Upload`.** An SVG named `.png` is refused by its
 *   content, and the class never calls `wp_handle_upload()` or reads `$_FILES` anywhere but in
 *   its one reader.
 * - **All or nothing.** Two files arrive together and one bad one refuses the pair, the way
 *   one rejected field refuses the whole profile save.
 * - **The record says the site owns the logo**, so the sync leaves it alone every three
 *   hours; Remove empties the base's `Logo` first and clears the record only if that
 *   worked, or the next sync run would copy the site's own URLs back in as Airtable's.
 * - **Airtable's `Logo` is replaced with the site's public URLs, color first**, so the base
 *   shows the picture the site shows and the WPCredits tracker keeps reading the base.
 * - **A failed PATCH never loses the logo.** The site keeps it and says so.
 *
 * Real GD images and a real editor stand-in, as bin/test-image-upload.php uses.
 *
 * Run from the plugin root:  php bin/test-sponsor-logo.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']        = array();
$GLOBALS['umeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array( 1 );
$GLOBALS['index']       = array();
$GLOBALS['attachments'] = array();
$GLOBALS['patched']     = array();
$GLOBALS['audit']       = array();
$GLOBALS['ceiling']     = array();
$GLOBALS['nonce']       = array();
$GLOBALS['hooks']       = array();
$GLOBALS['temp_files']  = array();
$GLOBALS['patch_fails'] = false;
$GLOBALS['settings']    = array( 'sponsors_table' => 'tbluji8wknOZr55fa', 'logo_max_kb' => 1024 );

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

class WP_User {
	public $ID = 0, $roles = array(), $display_name = '', $user_email = '', $user_login = '';
	public function __construct( $id = 0, array $roles = array(), $name = 'Someone', $email = 'maciej@a8c.com' ) {
		$this->ID = (int) $id; $this->roles = $roles; $this->display_name = $name; $this->user_email = $email; $this->user_login = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}
class WPCPM_Test_Redirect extends Exception {}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $n ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function absint( $n ) { return abs( (int) $n ); }
function wp_unslash( $v ) { return $v; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $a ) . '" />'; }
function check_admin_referer( $a ) { $GLOBALS['nonce'][] = $a; return true; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_by( $f, $v ) { return ( 'id' === $f && isset( $GLOBALS['users'][ (int) $v ] ) ) ? $GLOBALS['users'][ (int) $v ] : false; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $k ] ) ? $GLOBALS['umeta'][ $id ][ $k ] : ''; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_upload_dir() { $dir = sys_get_temp_dir() . '/wpcpm-logo-uploads-' . getmypid(); if ( ! is_dir( $dir ) ) { mkdir( $dir ); } return array( 'path' => $dir, 'url' => 'https://example.test/uploads', 'error' => false ); }
function wp_unique_filename( $dir, $name ) { $i = 0; $try = $name; while ( file_exists( $dir . '/' . $try ) ) { $try = preg_replace( '/(\.[a-z]+)$/', '-' . ( ++$i ) . '$1', $name ); } return $try; }
function wp_insert_attachment( array $a, $file, $parent = 0, $wp_error = false ) { $id = 200 + count( $GLOBALS['attachments'] ); $GLOBALS['attachments'][ $id ] = array_merge( $a, array( 'file' => $file ) ); return $id; }
function wp_generate_attachment_metadata( $id, $file ) { return array( 'file' => basename( $file ) ); }
function wp_update_attachment_metadata( $id, $data ) { return true; }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function wp_get_attachment_url( $id ) { return isset( $GLOBALS['attachments'][ $id ] ) ? 'https://example.test/uploads/' . basename( $GLOBALS['attachments'][ $id ]['file'] ) : false; }
function wp_get_attachment_image_url( $id, $size = 'medium' ) { return wp_get_attachment_url( $id ); }
function wp_get_attachment_image( $id, $size = 'medium', $icon = false, $attr = array() ) { return '<img src="' . esc_attr( (string) wp_get_attachment_url( $id ) ) . '" alt="" />'; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function wp_safe_redirect( $u ) { throw new WPCPM_Test_Redirect( $u ); }
function wp_die( $m, $t = '', $a = array() ) { throw new WPCPM_Test_Redirect( 'die:' . $m ); }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }

// The editor: what WordPress does with a real image, in miniature.
class WPCPM_Test_Editor {
	private $path;
	public function __construct( $path ) { $this->path = $path; }
	public function save( $dest, $mime = null ) { copy( $this->path, $dest ); $size = getimagesize( $dest ); return array( 'path' => $dest, 'file' => basename( $dest ), 'width' => $size[0], 'height' => $size[1], 'mime-type' => $size['mime'] ); }
}
function wp_get_image_editor( $path ) { return new WPCPM_Test_Editor( $path ); }

class WPCPM_Settings {
	public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; }
	public static function get() { return $GLOBALS['settings']; }
}
class WPCPM_Roles {
	const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator';
	const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Mentors_Sync { public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); } }
class WPCPM_Sponsors_Sync { public static function fields() { return array( 'logo' => 'Logo', 'name' => 'Company Name' ); } }
class WPCPM_Airtable {
	public function update_records( $table, array $records ) {
		$GLOBALS['patched'][] = array( $table, $records );
		return $GLOBALS['patch_fails'] ? new WP_Error( 'wpcpm_airtable_http', 'Airtable said no.' ) : $records;
	}
}
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$GLOBALS['ceiling'][ $key ] = isset( $GLOBALS['ceiling'][ $key ] ) ? $GLOBALS['ceiling'][ $key ] + 1 : 1;
		return $GLOBALS['ceiling'][ $key ] <= (int) $limit;
	}
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const EVIDENCE_INDEX = 'index';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}
class WPCPM_Refusal_Meter { public static function is_locked( $scope, $user ) { return false; } public static function refuse( $scope, $user ) { return 0; } }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function leave( $status, $card, $record = '', $detail = '' ) { throw new WPCPM_Test_Redirect( $status . '|' . $card . '|' . $record . '|' . $detail ); }
}

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-image-upload.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-logo.php';

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

/**
 * One method's body, by brace depth, for the assertions that read source rather than behavior.
 *
 * @param string $source The file.
 * @param string $name   The method.
 * @return string
 */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}

	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	$length = strlen( $source );

	while ( $end < $length && $depth > 0 ) {
		if ( '{' === $source[ $end ] ) { ++$depth; } elseif ( '}' === $source[ $end ] ) { --$depth; }
		++$end;
	}

	return substr( $source, $offset, $end - $offset );
}

/**
 * A real PNG on disk.
 *
 * @param int $w Width.
 * @param int $h Height.
 * @return string Path.
 */
function png( $w, $h ) {
	$p  = tempnam( sys_get_temp_dir(), 'wpcpm-logo-' ) . '.png';
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 30, 30 ) );
	imagepng( $im, $p );
	$GLOBALS['temp_files'][] = $p;

	return $p;
}

/**
 * A file that claims to be a PNG and is an SVG.
 *
 * @return string Path.
 */
function fake_svg() {
	$p = tempnam( sys_get_temp_dir(), 'wpcpm-logo-' ) . '.png';
	file_put_contents( $p, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><script>alert(1)</script></svg>' );
	$GLOBALS['temp_files'][] = $p;

	return $p;
}

/**
 * Put one or both logo files in `$_FILES`, the way a browser would.
 *
 * @param string $color A path, or '' for no color file.
 * @param string $white A path, or '' for no white file.
 * @param string $name  The name the color file came with.
 */
function post_logos( $color, $white = '', $name = 'logo.png' ) {
	$_FILES = array();

	if ( '' !== $color ) {
		$_FILES['wpcpm_logo_color'] = array( 'name' => $name, 'type' => 'image/png', 'tmp_name' => $color, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize( $color ) );
	}

	if ( '' !== $white ) {
		$_FILES['wpcpm_logo_white'] = array( 'name' => 'logo-white.png', 'type' => 'image/png', 'tmp_name' => $white, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize( $white ) );
	}
}

/**
 * Run a handler and report where it left.
 *
 * @param string $method The handler.
 * @return string The status, card, record and detail the redirect carried.
 */
function ran( $method ) {
	try {
		call_user_func( array( 'WPCPM_Sponsor_Logo', $method ) );
	} catch ( WPCPM_Test_Redirect $e ) {
		return $e->getMessage();
	}

	return 'no redirect';
}

/**
 * The cells one PATCH carried.
 *
 * @param int $which Which PATCH; 0 for the first.
 * @return array
 */
function patched_cells( $which = 0 ) {
	return isset( $GLOBALS['patched'][ $which ][1][0]['fields'] ) ? $GLOBALS['patched'][ $which ][1][0]['fields'] : array();
}

$S = 'recSPN00000000001';
$GLOBALS['opts']['wpcpm_sponsors_index'] = array(
	'v'    => WPCPM_Sponsors_Index::VERSION,
	'read' => 1757000000,
	'rows' => array( $S => array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $S, 'name' => 'TEST Sponsor', 'status' => 'Approved' ) ) ),
);
$GLOBALS['users'][1]  = new WP_User( 1, array( 'administrator' ), 'Manager' );
$GLOBALS['users'][20] = new WP_User( 20, array( 'wpcpm_sponsor' ), 'Member One' );
$GLOBALS['umeta'][20] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $S, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']       = 20;

echo "=== Refusals ===\n";
$_POST = array( 'wpcpm_sponsor' => $S );
post_logos( '' );
// With the record, as every other outcome here carries it: a manager who pressed Save on a
// sponsor's behalf lands back on that sponsor and not on whichever one the page opens with.
ck( 'nothing chosen is a refusal that says so', ran( 'handle_upload' ), 'logo-none|profile|' . $S . '|' );
// FSPON-4: five empty presses used to fill the day's ceiling and refuse every colleague, and
// the sponsor never uploaded anything. A submission with no file in it costs nothing.
ck( 'and it spends none of the five uploads the day allows', $GLOBALS['ceiling'], array() );
post_logos( fake_svg() );
ck( 'an SVG named .png is refused by its content', substr( ran( 'handle_upload' ), 0, 12 ), 'logo-refused' );
ck( 'and nothing was stored', $GLOBALS['attachments'], array() );
// A file that did arrive still spends its place before a byte of it is read: that is what
// keeps a runaway script from feeding this handler a megabyte at a time.
ck( 'a file that did arrive spends one, refused or not', $GLOBALS['ceiling'], array( 'sponsor-logo:' . $S => 1 ) );
post_logos( png( 199, 80 ) );
ck( 'a logo narrower than 200px is refused', substr( ran( 'handle_upload' ), 0, 12 ), 'logo-refused' );
post_logos( png( 300, 100 ), fake_svg() );
ck( 'one bad file of two refuses the pair', substr( ran( 'handle_upload' ), 0, 12 ), 'logo-refused' );
ck( 'and neither half was stored', $GLOBALS['attachments'], array() );

// A fresh day. The ceiling is claimed once a file has arrived and before a byte of it is
// read, so the three refusals above that carried a file spent one each and the empty one
// spent nothing.
$GLOBALS['ceiling'] = array();

echo "\n=== The upload ===\n";
post_logos( png( 400, 120 ), png( 400, 120 ) );
ck( 'both halves land', ran( 'handle_upload' ), 'logo-saved|profile|' . $S . '|' );
$record = WPCPM_Sponsors_Index::logo_record( $S );
ck( 'the record names two attachments and says the site owns them', array( $record['colour'] > 0, $record['white'] > 0, $record['source'], $record['airtable_id'] ), array( true, true, 'site', '' ) );
ck( 'the attachments carry the acting account and the titles the spec spells', array(
	$GLOBALS['attachments'][ $record['colour'] ]['post_author'],
	$GLOBALS['attachments'][ $record['colour'] ]['post_title'],
	$GLOBALS['attachments'][ $record['white'] ]['post_title'],
), array( 20, 'TEST Sponsor logo (color)', 'TEST Sponsor logo (white)' ) );
ck( 'the nonce was keyed to the record', end( $GLOBALS['nonce'] ), 'wpcpm_sponsor_logo_' . $S );
$cells = patched_cells( 0 );
ck( 'Airtable\'s Logo is replaced with the two public URLs, color first', array(
	array_keys( $cells ),
	count( $cells['Logo'] ),
	array_keys( $cells['Logo'][0] ),
	0 === strpos( $cells['Logo'][0]['url'], 'https://example.test/uploads/' ),
), array( array( 'Logo' ), 2, array( 'url', 'filename' ), true ) );
ck( 'the identity block now draws the site\'s color logo', WPCPM_Sponsors_Index::display_logo( $S )['id'], $record['colour'] );
ck( 'an audit row names the upload and not the file', array( end( $GLOBALS['audit'] )['kind'], isset( end( $GLOBALS['audit'] )['data']['halves'] ) ), array( 'logo_uploaded', true ) );

echo "\n=== One half at a time ===\n";
$before = WPCPM_Sponsors_Index::logo_record( $S );
post_logos( '', png( 500, 150 ) );
ck( 'a white logo on its own is accepted', ran( 'handle_upload' ), 'logo-saved|profile|' . $S . '|' );
$after = WPCPM_Sponsors_Index::logo_record( $S );
ck( 'and leaves the color one where it was', array( $after['colour'], $after['white'] !== $before['white'] ), array( $before['colour'], true ) );
ck( 'the PATCH still sends both, color first', count( patched_cells( 1 )['Logo'] ), 2 );

echo "\n=== Airtable refusing ===\n";
$GLOBALS['patch_fails'] = true;
post_logos( png( 420, 130 ) );
ck( 'a failed PATCH says so and never loses the logo', ran( 'handle_upload' ), 'logo-airtable|profile|' . $S . '|' );
ck( 'the site record still names the new attachment', WPCPM_Sponsors_Index::logo_record( $S )['colour'] > $after['colour'], true );
$GLOBALS['patch_fails'] = false;

echo "\n=== The ceiling ===\n";
// A fresh day again, so the count the labels below give is the count the ceiling sees.
$GLOBALS['ceiling'] = array();
for ( $i = 0; $i < 5; $i++ ) {
	post_logos( png( 300, 100 ) );
	ran( 'handle_upload' );
}
post_logos( png( 300, 100 ) );
ck( 'the sixth upload of the day is refused by the ceiling', ran( 'handle_upload' ), 'logo-busy|profile|' . $S . '|' );
ck( 'the ceiling is keyed to the record', array_keys( $GLOBALS['ceiling'] ), array( 'sponsor-logo:' . $S ) );

echo "\n=== Remove ===\n";
$_POST = array( 'wpcpm_sponsor' => $S );
$held  = WPCPM_Sponsors_Index::logo_record( $S );
// The base first. A record cleared ahead of a PATCH that then failed would leave the site's
// own URLs in the base under a record that no longer says the site owns them: the card would
// stop offering Remove and the next sync run would copy those URLs back in as Airtable's.
$GLOBALS['patch_fails'] = true;
ck( 'a base that refuses removes nothing, and says so as an error', ran( 'handle_remove' ), 'logo-removed-airtable|profile|' . $S . '|' );
ck( 'the site still holds the logo, and still says the site owns it', WPCPM_Sponsors_Index::logo_record( $S ), $held );
ck( 'the audit row says the logo was left in place', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['airtable'] ), array( 'logo_removed', false ) );
ck( 'and the message a person reads says nothing was removed', WPCPM_Sponsor_Logo::messages()['logo-removed-airtable'], array( 'error', 'Nothing was removed: the program records could not be told just now. Try again in a moment.' ) );
$GLOBALS['patch_fails'] = false;
ck( 'with the base told, remove clears the record', ran( 'handle_remove' ), 'logo-removed|profile|' . $S . '|' );
ck( 'so the next sync may copy Airtable\'s again', WPCPM_Sponsors_Index::logo_record( $S ), array( 'colour' => 0, 'white' => 0, 'source' => '', 'airtable_id' => '' ) );
ck( 'and the attachments are left in the Media Library', isset( $GLOBALS['attachments'][ $held['colour'] ] ), true );
// Eight: the pair, the white half on its own, the one the base refused, and the ceiling
// section's five. Then Remove's two attempts, the refused one and the one that worked.
ck( 'remove empties the base\'s Logo too, so the next sync run has nothing to copy back', array( count( $GLOBALS['patched'] ), patched_cells( 8 ), patched_cells( 9 ) ), array( 10, array( 'Logo' => array() ), array( 'Logo' => array() ) ) );
$logo_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-logo.php' );
ck( 'the PATCH is made before the site record is touched, read off the source', strpos( method_body( $logo_src, 'handle_remove' ), 'self::clear_airtable' ) < strpos( method_body( $logo_src, 'handle_remove' ), 'write_logo_record' ), true );
ck( 'and the card says the logo goes from both places, and that the files stay', false !== strpos( method_body( $logo_src, 'render_inner' ), 'It goes from this site and from the program records.' ), true );

// FSPON-5: the card offers Remove only for a logo this site owns, and the handler asks the
// same question rather than trusting that it did. A nonce minted while the site owned the
// logo stays valid for its life, and the sponsors sync can set the source back to airtable in
// between; the PATCH would then empty the base's Logo and destroy the only copy the program
// holds, under an audit row saying the logo was removed from the program records.
$patched_before = count( $GLOBALS['patched'] );
ck( 'Remove pressed with nothing of the site\'s left is refused', ran( 'handle_remove' ), 'logo-not-site|profile|' . $S . '|' );
WPCPM_Sponsors_Index::write_logo_record( $S, array( 'colour' => $held['colour'], 'white' => $held['white'], 'source' => 'airtable', 'airtable_id' => 'attTESTLOGO00001' ) );
ck( 'and so is a logo the base owns, however valid the form', ran( 'handle_remove' ), 'logo-not-site|profile|' . $S . '|' );
ck( 'neither press touched the base', count( $GLOBALS['patched'] ), $patched_before );
ck( 'and the record the sync wrote is left exactly as it was', WPCPM_Sponsors_Index::logo_record( $S ), array( 'colour' => $held['colour'], 'white' => $held['white'], 'source' => 'airtable', 'airtable_id' => 'attTESTLOGO00001' ) );
// Load-bearing, not tidying: "The card" section below expects to start from an empty record.
WPCPM_Sponsors_Index::write_logo_record( $S, array( 'colour' => 0, 'white' => 0, 'source' => '', 'airtable_id' => '' ) );

echo "\n=== The card ===\n";
$GLOBALS['ceiling'] = array();
$GLOBALS['uid']     = 20;
post_logos( png( 400, 120 ) );
ran( 'handle_upload' );
ob_start();
WPCPM_Sponsor_Logo::render( $S, array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][20] ) );
$html = (string) ob_get_clean();
ck( 'the card carries the two file fields, the multipart form and the record', array(
	false !== strpos( $html, 'enctype="multipart/form-data"' ),
	false !== strpos( $html, 'name="wpcpm_logo_color"' ),
	false !== strpos( $html, 'name="wpcpm_logo_white"' ),
	false !== strpos( $html, 'value="' . $S . '"' ),
), array( true, true, true, true ) );
ck( 'and a preview tile for the half that exists and a note for the one that does not', array(
	substr_count( $html, 'wpcpm-logo__tile--' ),
	false !== strpos( $html, 'wpcpm-logo__empty' ),
), array( 1, true ) );
ck( 'a site logo offers Remove', false !== strpos( $html, WPCPM_Sponsor_Logo::ACTION_REMOVE ), true );
$GLOBALS['uid'] = 1;
ob_start();
WPCPM_Sponsor_Logo::render( $S, array( 'can_manage' => true, 'open' => '', 'viewer' => $GLOBALS['users'][1] ) );
$manager_html = (string) ob_get_clean();
ck( 'a manager sees the same card for any sponsor', false !== strpos( $manager_html, 'name="wpcpm_logo_color"' ), true );

echo "\n=== House rules ===\n";
$src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-logo.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'wp_handle_upload() is never called', preg_match( '/wp_handle_upload|move_uploaded_file/', $src ), 0 );
ck( '$_FILES is read in one helper and nowhere else', substr_count( $src, '$_FILES' ), 4 );
ck( 'the fence runs before the ceiling', strpos( $src, 'WPCPM_Sponsor_Roster::claim' ) < strpos( $src, 'WPCPM_Ceiling::claim' ), true );
$upload_body = method_body( $src, 'handle_upload' );
ck( 'the ceiling is claimed after the form is found to carry a file and before one is read', array(
	strpos( $upload_body, "self::leave( 'logo-none'" ) < strpos( $upload_body, 'WPCPM_Ceiling::claim' ),
	strpos( $upload_body, 'WPCPM_Ceiling::claim' ) < strpos( $upload_body, 'WPCPM_Image_Upload::accept' ),
), array( true, true ) );
ck( 'and nothing is stored before every file is accepted', strpos( $src, 'WPCPM_Image_Upload::accept' ) < strpos( $src, 'WPCPM_Image_Upload::store' ), true );
ck( 'every string a person reads says color', preg_match( '/\bcolour\b/', preg_replace( "/'colour'/", '', $src ) ), 0 );

foreach ( $GLOBALS['temp_files'] as $temp ) {
	if ( is_file( $temp ) ) { unlink( $temp ); }
}

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
