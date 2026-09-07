<?php
/**
 * The Designer Track's screenshots: the file control, the Media Library copy, the Airtable write.
 *
 * Design spec of 7 September 2026, section 5. What is worth pinning:
 *
 * - **Every byte goes through `WPCPM_Image_Upload`**, the same rules the sponsor logo uses: the
 *   type is read off the bytes, so an SVG named `.png` is refused, and the file stored is the one
 *   WordPress re-saved.
 * - **Airtable is sent the Media Library's public URL**, not the bytes: the base fetches the file
 *   itself and keeps its own copy, which is what `WPCPM_Sponsor_Logo` does for a logo.
 * - **The card never links an Airtable attachment URL.** Those expire within hours, so the card
 *   shows the site's own copy, and says how many files the base holds when the site has none.
 * - **One screenshot is one lesson's answer.** A file that cannot be used refuses itself and not
 *   the twenty other answers in the same save.
 * - **Twenty uploads a student a day**, through the one ceiling primitive.
 *
 * Real GD images and a real editor stand-in, as bin/test-sponsor-logo.php uses.
 *
 * Run from the plugin root:  php bin/test-report-images.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opts']        = array();
$GLOBALS['umeta']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['attachments'] = array();
$GLOBALS['next_id']     = 300;
$GLOBALS['deleted']     = array();
$GLOBALS['patched']     = array();
$GLOBALS['ceiling']     = array();
$GLOBALS['nonce']       = array();
$GLOBALS['nonce_fails'] = false;
$GLOBALS['temp_files']  = array();
$GLOBALS['patch_fails'] = false;
// `wp_delete_attachment()` returns false when the row will not go; the handler has to notice.
$GLOBALS['delete_fails'] = false;
$GLOBALS['settings']    = array( 'reports_table' => 'tbljYkkVGbeoaWEtY', 'logo_max_kb' => 1024 );

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

class WP_User {
	public $ID = 0, $roles = array(), $display_name = 'A Student';
	public function __construct( $id = 0 ) { $this->ID = (int) $id; }
	public function exists() { return $this->ID > 0; }
}
class WPCPM_Test_Redirect extends Exception {}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $u, $p = null ) { $u = trim( (string) $u ); $s = strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) ); if ( null !== $p && ! in_array( $s, (array) $p, true ) ) { return ''; } return $u; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $n ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function absint( $n ) { return abs( (int) $n ); }
function wp_unslash( $v ) { return $v; }
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_generate_password( $len = 12, $special = true, $extra = false ) { return substr( str_repeat( 'abc123', 10 ), 0, (int) $len ); }
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {} function register_rest_route() {}
function wp_kses_post( $s ) { return $s; }
function checked( $a, $b = true, $e = true ) { $r = ( (string) $a === (string) $b ) ? ' checked="checked"' : ''; if ( $e ) { echo $r; } return $r; }
function selected( $a, $b = true, $e = true ) { $r = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $e ) { echo $r; } return $r; }
function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $echo = true ) { $f = sprintf( '<input type="hidden" name="%s" value="%s" />', $n, 'nonce' ); if ( $echo ) { echo $f; } return $f; }
function check_admin_referer( $a = -1 ) {
	$GLOBALS['nonce'][] = $a;

	// A wrong or missing nonce dies before the handler does anything else, the same as the
	// real function; the flag lets a check ask for that on demand, as bin/test-unlinked-link.php
	// already does for its own nonce.
	if ( ! empty( $GLOBALS['nonce_fails'] ) ) {
		wp_die( 'The link has expired. Please try again.' );
	}

	return true;
}
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_by( $f, $v ) { return new WP_User( (int) $v ); }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return isset( $GLOBALS['opts'][ 'T_' . $k ] ) ? $GLOBALS['opts'][ 'T_' . $k ] : false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ] ); return true; }
function wp_upload_dir() { $d = sys_get_temp_dir() . '/wpcpm-report-uploads-' . getmypid(); if ( ! is_dir( $d ) ) { mkdir( $d ); } return array( 'path' => $d, 'url' => 'https://example.test/uploads', 'error' => false ); }
function wp_unique_filename( $dir, $name ) { $i = 0; $try = $name; while ( file_exists( $dir . '/' . $try ) ) { $try = preg_replace( '/(\.[a-z]+)$/', '-' . ( ++$i ) . '$1', $name ); } return $try; }
// A counter that only climbs, never `count( $GLOBALS['attachments'] )`: real WordPress post IDs
// auto-increment and are never handed out twice, even after the row they named is deleted. A
// Replace that deletes a superseded attachment and then stores a new one in the same request
// would otherwise let the count fall back to a number Remove had already retired, and the new
// row would collide with whichever row still happened to sit at that slot.
function wp_insert_attachment( array $a, $file, $parent = 0, $wp_error = false ) { $id = $GLOBALS['next_id']++; $GLOBALS['attachments'][ $id ] = array_merge( $a, array( 'file' => $file ) ); return $id; }
function wp_generate_attachment_metadata( $id, $file ) { return array( 'file' => basename( $file ) ); }
function wp_update_attachment_metadata( $id, $data ) { return true; }
function wp_delete_attachment( $id, $force = false ) { if ( ! empty( $GLOBALS['delete_fails'] ) ) { return false; } $GLOBALS['deleted'][] = (int) $id; unset( $GLOBALS['attachments'][ (int) $id ] ); return true; }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function wp_get_attachment_url( $id ) { return isset( $GLOBALS['attachments'][ $id ] ) ? 'https://example.test/uploads/' . basename( $GLOBALS['attachments'][ $id ]['file'] ) : false; }
function wp_get_attachment_image_url( $id, $size = 'medium' ) { return wp_get_attachment_url( $id ); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function wp_safe_redirect( $u ) { throw new WPCPM_Test_Redirect( $u ); }
function wp_die( $m, $t = '', $a = array() ) { throw new WPCPM_Test_Redirect( 'die:' . $m ); }

// The editor: what WordPress does with a real image, in miniature.
class WPCPM_Test_Editor {
	private $path;
	public function __construct( $path ) { $this->path = $path; }
	public function save( $dest, $mime = null ) { copy( $this->path, $dest ); $s = getimagesize( $dest ); return array( 'path' => $dest, 'file' => basename( $dest ), 'width' => $s[0], 'height' => $s[1], 'mime-type' => $s['mime'] ); }
}
function wp_get_image_editor( $path ) { return new WPCPM_Test_Editor( $path ); }

class WPCPM_Settings {
	public static function get() { return $GLOBALS['settings']; }
	public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; }
}
class WPCPM_Airtable {
	public function __construct( $settings = array() ) {}
	public function update_records( $table, array $records ) {
		$GLOBALS['patched'][] = array( $table, $records );
		return $GLOBALS['patch_fails'] ? new WP_Error( 'wpcpm_airtable_http', 'Airtable said no.' ) : $records;
	}
	public function get_record( $table, $id ) { return array( 'id' => $id, 'fields' => array() ); }
	public static function flatten( $v, $glue = ', ' ) { return is_scalar( $v ) ? (string) $v : ''; }
}
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$taken = isset( $GLOBALS['ceiling'][ $key ] ) ? (int) $GLOBALS['ceiling'][ $key ] : 0;
		$amount = max( 1, (int) $amount );

		if ( $amount > (int) $limit || $taken + $amount > (int) $limit ) {
			return false;
		}

		$GLOBALS['ceiling'][ $key ] = $taken + $amount;

		return true;
	}
}
class WPCPM_Mentor_Calls {
	public static function student_record( $id ) { return isset( $GLOBALS['record'] ) ? $GLOBALS['record'] : ''; }
}
class WPCPM_Students_Sync {
	const META_PROGRAM = 'wpcpm_student_program';
	const META_RECORD_ID = 'wpcpm_student_record';
	public static function get_program( $id ) { $p = get_user_meta( (int) $id, self::META_PROGRAM, true ); return is_array( $p ) ? $p : array(); }
	public static function apply_report( $id, array $cells ) { $GLOBALS['applied'] = $cells; return true; }
	public static function forget_report_file( $id, $column ) {
		$program = self::get_program( $id );

		if ( isset( $program['report_files'][ $column ] ) ) {
			unset( $program['report_files'][ $column ] );
			update_user_meta( (int) $id, self::META_PROGRAM, $program );

			return true;
		}

		return false;
	}
	public static function user_for_record( $r ) { return new WP_User( 7 ); }
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-dashboard/'; }
}
class WPCPM_Mentors_Sync {
	public static function fields() { return array( 'report_profile' => 'WordPress Profile', 'report_slack' => 'Slack Name', 'report_team' => 'Main Contribution Team', 'report_website' => 'Personal Website URL', 'report_hours' => 'Hours' ); }
	public static function resolve_stored( $v, $kind ) { return $v; }
	public static function wporg_username( $v ) { return ''; }
}
class WPCPM_Contribution_Teams {
	public static function options() { return array( 'recTeam0000000001' => 'Design' ); }
	public static function label_icon( $name ) { return ''; }
}
class WPCPM_Mentors_Dashboard {
	public static function get_mentees( $id ) { return array(); }
}

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-flash.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/class-wpcpm-image-upload.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';

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
 * A real PNG on disk.
 *
 * @param int $w Width.
 * @param int $h Height.
 * @return string Path.
 */
function png( $w, $h ) {
	$p  = tempnam( sys_get_temp_dir(), 'wpcpm-shot-' ) . '.png';
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 30, 90, 200 ) );
	imagepng( $im, $p );
	$GLOBALS['temp_files'][] = $p;

	return $p;
}

/**
 * A real PNG, padded past a target size while it still reads as one.
 *
 * `getimagesize()` and `finfo` both read a PNG's signature and its header chunk, never the
 * whole file, so bytes appended after a valid image still pass both - which is what lets a
 * fixture this size exist without `imagecreatetruecolor()` spending real memory to earn it.
 *
 * @param int $w     Width.
 * @param int $h     Height.
 * @param int $bytes The file's size once padded, in bytes.
 * @return string Path.
 */
function big_png( $w, $h, $bytes ) {
	$p   = png( $w, $h );
	$pad = $bytes - filesize( $p );

	if ( $pad > 0 ) {
		$fh = fopen( $p, 'ab' );
		fwrite( $fh, str_repeat( "\0", $pad ) );
		fclose( $fh );
	}

	return $p;
}

/**
 * A file that claims to be a PNG and is an SVG carrying script.
 *
 * @return string Path.
 */
function fake_svg() {
	$p = tempnam( sys_get_temp_dir(), 'wpcpm-shot-' ) . '.png';
	file_put_contents( $p, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><script>alert(1)</script></svg>' );
	$GLOBALS['temp_files'][] = $p;

	return $p;
}

/** The Airtable column each screenshot below belongs to. */
$COL_LOCAL = 'Practical: Local WordPress Environment for Design Testing - Screenshot';
$COL_STYLE = 'Practical: Style Book - Screenshot';
$COL_TOOL  = 'Practical: Local WordPress Environment for Design Testing - Tool used';

/**
 * The form key one Airtable column posts under.
 *
 * @param string $column Airtable field name.
 * @return string
 */
function key_of( $column ) {
	return WPCPM_Student_Report_Form::key( $column );
}

/**
 * Put screenshots in `$_FILES`, the way a browser posts `report_image[<key>]`.
 *
 * @param array $files Airtable column name => path, or column => array( path, name ).
 */
function post_images( array $files ) {
	$_FILES = array();

	if ( empty( $files ) ) {
		return;
	}

	$shape = array( 'name' => array(), 'type' => array(), 'tmp_name' => array(), 'error' => array(), 'size' => array() );

	foreach ( $files as $column => $file ) {
		$path = is_array( $file ) ? $file[0] : $file;
		$name = is_array( $file ) ? $file[1] : 'screenshot.png';
		$key  = key_of( $column );

		$shape['name'][ $key ]     = $name;
		$shape['type'][ $key ]     = 'image/png';
		$shape['tmp_name'][ $key ] = $path;
		$shape['error'][ $key ]    = UPLOAD_ERR_OK;
		$shape['size'][ $key ]     = (int) filesize( $path );
	}

	$_FILES['report_image'] = $shape;
}

/**
 * Post the form and report the flash the handler left.
 *
 * @param string $method The handler.
 * @return string
 */
function ran( $method ) {
	try {
		call_user_func( array( 'WPCPM_Student_Report_Form', $method ) );
	} catch ( WPCPM_Test_Redirect $e ) {
		$pending = get_user_meta( $GLOBALS['uid'], WPCPM_Flash::META, true );

		return is_array( $pending ) && isset( $pending['report'] ) ? (string) $pending['report'] : 'no flash';
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

/**
 * The report body, rendered.
 *
 * @param bool   $read_only Force a record rather than a form.
 * @param string $audience  Who is reading.
 * @return string
 */
function body( $read_only = false, $audience = 'own' ) {
	$method = new ReflectionMethod( 'WPCPM_Student_Report_Form', 'render_body' );

	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	ob_start();
	$method->invoke(
		null,
		new WP_User( 7 ),
		WPCPM_Students_Sync::get_program( 7 ),
		$read_only,
		$audience
	);

	return (string) ob_get_clean();
}

$SID = 7;
$REC = 'recDESIGNER000001';

$GLOBALS['uid']    = $SID;
$GLOBALS['record'] = $REC;

update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => WPCPM_Program::STATUS_DESIGN ) );

// Seeded through the cache `values()` reads first, so nothing here reaches Airtable.
set_transient( 'wpcpm_report_' . md5( $REC ), array() );

echo "=== The control before anything is uploaded ===\n";

$blank = body();

ck( 'the form can carry a file at all', false !== strpos( $blank, 'enctype="multipart/form-data"' ), true );
ck( 'the screenshot question is a file input that takes the three types the site accepts',
    preg_match( '/<input type="file" id="wpcpm-report-' . key_of( $COL_LOCAL ) . '" name="report_image\[' . key_of( $COL_LOCAL ) . '\]" accept="image\/png,image\/jpeg,image\/webp"/', $blank ) === 1, true );
ck( 'and says there is nothing on record yet', false !== strpos( $blank, 'No screenshot yet' ), true );
ck( 'with nothing to remove', false !== strpos( $blank, '>Remove</button>' ), false );

// The tool question, the form's one single select.
ck( 'the tool question is a select whose first option chooses nothing',
    preg_match( '/<select id="wpcpm-report-' . key_of( $COL_TOOL ) . '" name="report\[' . key_of( $COL_TOOL ) . '\]"[^>]*>\s*<option value="">Choose one<\/option><option value="WordPress Studio">WordPress Studio<\/option><option value="MAAMP">MAAMP<\/option><option value="DevKinsta">DevKinsta<\/option><\/select>/', $blank ) === 1, true );

// The long course has no screenshot on it, so its form must not become a file upload.
update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => WPCPM_Program::STATUS_150H ) );
ck( 'a track with no screenshots posts no files', false !== strpos( body(), 'enctype="multipart/form-data"' ), false );
update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => WPCPM_Program::STATUS_DESIGN ) );

echo "\n=== A file that cannot be used ===\n";

$_POST = array( 'student' => $SID );
post_images( array( $COL_LOCAL => fake_svg() ) );
ck( 'an SVG named .png is refused by its content', ran( 'handle_save' ), 'report-image-refused' );
ck( 'and nothing was stored', $GLOBALS['attachments'], array() );
ck( 'and no attachment cell was written', array_key_exists( $COL_LOCAL, patched_cells() ), false );

$GLOBALS['patched'] = array();
post_images( array( $COL_LOCAL => png( 120, 90 ) ) );
ck( 'a picture narrower than the site\'s minimum is refused', ran( 'handle_save' ), 'report-image-refused' );
ck( 'and still nothing was stored', $GLOBALS['attachments'], array() );

echo "\n=== One screenshot, saved ===\n";

$GLOBALS['patched'] = array();
post_images( array( $COL_LOCAL => png( 900, 600 ) ) );

ck( 'the save goes through', ran( 'handle_save' ), 'report-saved' );
ck( 'one attachment was stored, authored by the student and titled by the question',
    array(
        count( $GLOBALS['attachments'] ),
        current( $GLOBALS['attachments'] )['post_author'],
        current( $GLOBALS['attachments'] )['post_title'],
    ),
    array( 1, $SID, 'A screenshot of your local site' ) );

// The record, not only the file. An `inherit` attachment with no parent reads as published, so
// the unauthenticated `wp/v2/media` listing hands out every screenshot with the student's user
// ID and the lesson's name on it, and the attachment page prints their display name beside the
// picture. `private` drops the record from the listing and 404s the page; the file's own address
// under `/wp-content/uploads/` is served whatever the status says, which is what Airtable fetches.
ck( 'the attachment is private, so a stranger cannot list it',
    current( $GLOBALS['attachments'] )['post_status'], 'private' );

$stored_id = (int) key( $GLOBALS['attachments'] );

// The site keeps the map, so the card can show its own copy without asking Airtable.
ck( 'the site remembers which attachment answers which question',
    get_user_meta( $SID, WPCPM_Student_Report_Form::META_IMAGES, true ),
    array( $COL_LOCAL => $stored_id ) );

// Airtable is sent a URL and a filename, because the base fetches the file itself. Sending the
// bytes is not a thing the attachment API does. The name carries the label plus twenty-four
// generated characters (`wp_generate_password()`'s stub is deterministic, so the suite can
// pin the whole string) rather than the label alone - see the next check for why.
ck( 'Airtable is sent the public URL and the filename, as one attachment',
    patched_cells(),
    array( $COL_LOCAL => array( array( 'url' => 'https://example.test/uploads/A-screenshot-of-your-local-site-abc123abc123abc123abc123.png', 'filename' => 'A-screenshot-of-your-local-site-abc123abc123abc123abc123.png' ) ) ) );

// A logged-out stranger who can fetch this address at all must not be able to guess the next
// one by counting: the stored name is not the label alone, the way a sponsor's logo is.
ck( 'the stored file is not named after the label alone',
    basename( current( $GLOBALS['attachments'] )['file'] ) === 'A-screenshot-of-your-local-site.png', false );

// A question nobody uploaded to must not be emptied by somebody else's upload.
ck( 'a question with no new file is not written at all', array_key_exists( $COL_STYLE, patched_cells() ), false );

echo "\n=== The card shows the site's copy, never Airtable's ===\n";

$saved = body();

ck( 'the thumbnail is the site\'s own file, linked to the full picture',
    preg_match( '#<a class="wpcpm-report__image-link" href="https://example\.test/uploads/[^"]+"[^>]*><img class="wpcpm-report__image-thumb" src="https://example\.test/uploads/#', $saved ) === 1, true );
ck( 'and it offers Replace and Remove',
    array( false !== strpos( $saved, 'Replace it by choosing a new file.' ), false !== strpos( $saved, '>Remove</button>' ) ),
    array( true, true ) );
// The button lives inside the report form, so its own form is a sibling it points at by id: a
// nested `<form>` is not markup a browser keeps.
ck( 'the Remove button belongs to a form of its own, outside the report form',
    array(
        preg_match( '/<button type="submit"[^>]*form="wpcpm-report-remove-7" name="field" value="' . key_of( $COL_LOCAL ) . '">Remove<\/button>/', $saved ) === 1,
        strpos( $saved, '<form class="wpcpm-report__remove" id="wpcpm-report-remove-7"' ) > strpos( $saved, '</form>' ),
        // Guarded like every other form on the page. Remove is a delete, and a second press
        // while the first is in flight sends a second PATCH of a cell that is already empty.
        false !== strpos( $saved, 'id="wpcpm-report-remove-7" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Removing"' ),
    ),
    array( true, true, true ) );

echo "\n=== A file only the program records hold ===\n";

// Uploaded in Airtable by hand: the sync counts it, and the card says so rather than linking an
// address that stops working within hours.
update_user_meta(
	$SID,
	WPCPM_Students_Sync::META_PROGRAM,
	array( 'record_id' => $REC, 'program' => WPCPM_Program::STATUS_DESIGN, 'report_files' => array( $COL_STYLE => 1, $COL_LOCAL => 1 ) )
);

$on_record = body();

ck( 'the count is said in words, and nothing links out to Airtable',
    array( false !== strpos( $on_record, '1 file on record in Airtable' ), false !== strpos( $on_record, 'airtable.com' ) ),
    array( true, false ) );

update_user_meta(
	$SID,
	WPCPM_Students_Sync::META_PROGRAM,
	array( 'record_id' => $REC, 'program' => WPCPM_Program::STATUS_DESIGN, 'report_files' => array( $COL_STYLE => 3 ) )
);

ck( 'and it is pluralized', false !== strpos( body(), '3 files on record in Airtable' ), true );

// The site's own copy wins: the base holds one for this question too, and the card shows the
// picture rather than the sentence.
ck( 'a question the site has a copy of shows the picture, not the count',
    substr_count( body(), 'file on record in Airtable' ) + substr_count( body(), 'files on record in Airtable' ), 1 );

echo "\n=== Remove ===\n";

$GLOBALS['patched'] = array();
$_POST = array( 'student' => $SID, 'field' => key_of( $COL_LOCAL ) );

ck( 'the screenshot is removed', ran( 'handle_image_remove' ), 'report-image-removed' );
ck( 'the site\'s file is deleted', $GLOBALS['deleted'], array( $stored_id ) );
ck( 'the map no longer names it', get_user_meta( $SID, WPCPM_Student_Report_Form::META_IMAGES, true ), array() );
ck( 'and the cell is emptied in the program records', patched_cells(), array( $COL_LOCAL => array() ) );
// Without this the card would say "1 file on record in Airtable" for a cell that is now empty,
// until the next sync happened to run.
ck( 'the count the sync left is forgotten too',
    isset( WPCPM_Students_Sync::get_program( $SID )['report_files'][ $COL_LOCAL ] ), false );
ck( 'and the card says there is nothing there', false !== strpos( body(), 'No screenshot yet' ), true );

$GLOBALS['patched'] = array();
ck( 'removing what is not there says so, and writes nothing', ran( 'handle_image_remove' ), 'report-image-none' );
ck( 'nothing was patched', $GLOBALS['patched'], array() );

echo "\n=== The daily ceiling ===\n";

$GLOBALS['ceiling']  = array();
$GLOBALS['patched']  = array();
$GLOBALS['deleted']  = array();
$_POST = array( 'student' => $SID );

for ( $i = 0; $i < 20; $i++ ) {
	post_images( array( $COL_LOCAL => png( 400, 300 ) ) );
	ran( 'handle_save' );
}

// Twenty Replaces to the same column, not twenty different questions: a survivor count no
// longer proves how many were accepted, because Replace now deletes the one it supersedes
// (nineteen of these twenty did). The deleted list is what twenty accepted uploads leaves
// behind, and only a stored file's own id is ever added to it.
ck( 'twenty uploads in a day are accepted', count( $GLOBALS['deleted'] ), 19 );
ck( 'and only the twentieth attachment survives on the column', count( $GLOBALS['attachments'] ), 1 );

post_images( array( $COL_LOCAL => png( 400, 300 ) ) );
ck( 'the twenty-first is refused, and the message names the limit', ran( 'handle_save' ), 'report-images-busy' );
ck( 'and it says the number out loud',
    false !== strpos( WPCPM_Student_Report_Form::message( 'report-images-busy' )[1], '20' ), true );
ck( 'nothing more was stored', count( $GLOBALS['attachments'] ), 1 );

echo "\n=== Replace deletes the file it supersedes ===\n";

// A minimal, isolated version of what the ceiling loop just proved at scale: two uploads to one
// question, checked one at a time rather than by counting. A third, unrelated attachment stands
// in for "a file the map never named" - Replace must leave it alone.
$GLOBALS['ceiling'] = array();
$GLOBALS['patched'] = array();
$GLOBALS['deleted'] = array();
$_POST = array( 'student' => $SID );

$untouched = (int) WPCPM_Image_Upload::store( WPCPM_Image_Upload::accept( png( 900, 600 ) ), 'not this form', 0, 'Not this form\'s attachment' );

post_images( array( $COL_STYLE => png( 900, 600 ) ) );
ran( 'handle_save' );
$first = (int) WPCPM_Student_Report_Form::images( $SID )[ $COL_STYLE ];

post_images( array( $COL_STYLE => png( 900, 600 ) ) );
ran( 'handle_save' );
$second = (int) WPCPM_Student_Report_Form::images( $SID )[ $COL_STYLE ];

ck( 'the two uploads produced two different attachments', $first === $second, false );
ck( 'the first attachment is gone', in_array( $first, $GLOBALS['deleted'], true ), true );
ck( 'the map names the second, not the first',
    WPCPM_Student_Report_Form::images( $SID )[ $COL_STYLE ], $second );
ck( 'and an attachment the map never named is untouched',
    array( isset( $GLOBALS['attachments'][ $untouched ] ), in_array( $untouched, $GLOBALS['deleted'], true ) ),
    array( true, false ) );

echo "\n=== The Remove handler's own gate ===\n";

// A missing or wrong nonce refuses the request before anything else runs, the same promise
// check_admin_referer() makes for every other admin-post handler in this plugin.
delete_user_meta( $SID, WPCPM_Flash::META );
$GLOBALS['nonce']       = array();
$GLOBALS['deleted']     = array();
$GLOBALS['patched']     = array();
$GLOBALS['nonce_fails'] = true;
$_POST = array( 'student' => $SID, 'field' => key_of( $COL_LOCAL ) );

ck( 'a missing or wrong nonce is refused before anything is touched', ran( 'handle_image_remove' ), 'no flash' );
ck( 'and it checked the action keyed to this student\'s own card',
    end( $GLOBALS['nonce'] ), WPCPM_Student_Report_Form::ACTION_REMOVE_IMAGE . '_' . $SID );
ck( 'nothing was deleted', $GLOBALS['deleted'], array() );
ck( 'and nothing was written to the program records', $GLOBALS['patched'], array() );

$GLOBALS['nonce_fails'] = false;

// A stranger, signed in as neither the card's own student nor a program manager.
delete_user_meta( $SID, WPCPM_Flash::META );
$GLOBALS['uid']     = 404;
$GLOBALS['manage']  = array();
$GLOBALS['deleted'] = array();
$GLOBALS['patched'] = array();

ck( 'a signed-in stranger, not this student and not a manager, is refused', ran( 'handle_image_remove' ), 'no flash' );
ck( 'nothing was deleted', $GLOBALS['deleted'], array() );
ck( 'and nothing was written to the program records', $GLOBALS['patched'], array() );

$GLOBALS['uid'] = $SID;

echo "\n=== The screenshot's own size ceiling ===\n";

// The rule the report path passes is its own, not the sponsor logo's fallback default: a
// full-page site screenshot passes the 1024 KB `logo_max_kb` default more often than it fails
// it, and that setting is named, and sized, for a logo (design spec of 7 September 2026,
// section 5, and the product owner's ruling on the first round's Concern 1).
$GLOBALS['ceiling'] = array();
$GLOBALS['patched'] = array();
$_POST = array( 'student' => $SID );

post_images( array( $COL_STYLE => big_png( 900, 600, 3 * 1024 * 1024 ) ) );
ck( 'a picture over the sponsor logo\'s 1024 KB default is accepted, on the report\'s own ceiling', ran( 'handle_save' ), 'report-saved' );

ck( 'and the sponsor logo\'s own default would have refused that same size',
    is_wp_error( WPCPM_Image_Upload::accept( big_png( 900, 600, 3 * 1024 * 1024 ), array( 'name' => 'shot.png' ) ) ), true );

$GLOBALS['patched'] = array();
post_images( array( $COL_STYLE => big_png( 900, 600, 5 * 1024 * 1024 ) ) );
ck( 'and one past the report\'s own ceiling is refused', ran( 'handle_save' ), 'report-image-refused' );
ck( 'with the ceiling named in the message',
    false !== strpos( WPCPM_Student_Report_Form::message( 'report-image-refused' )[1], (string) WPCPM_Student_Report_Form::IMAGE_MAX_KB ), true );

echo "\n=== Remove, when the delete does not happen ===\n";

// Two halves of the same promise: the button is offered over a file, and pressing it either
// removes the file or says it did not. Neither is true if the map is trusted on its own - a
// manager can delete an attachment in wp-admin, and `wp_delete_attachment()` can refuse.
$GLOBALS['ceiling'] = array();
$GLOBALS['patched'] = array();
$GLOBALS['deleted'] = array();
$_POST = array( 'student' => $SID );

post_images( array( $COL_LOCAL => png( 900, 600 ) ) );
ran( 'handle_save' );

$orphan = (int) WPCPM_Student_Report_Form::images( $SID )[ $COL_LOCAL ];

// The attachment goes the way a manager deleting it in wp-admin makes it go: the row is gone and
// the map still names it. The card must read the file, not the map.
unset( $GLOBALS['attachments'][ $orphan ] );

$gone = body();

ck( 'a map entry with no file behind it offers nothing to remove',
    array(
        substr_count( $gone, 'wpcpm-report__image-thumb' ),
        false !== strpos( $gone, 'value="' . key_of( $COL_LOCAL ) . '">Remove</button>' ),
        false !== strpos( $gone, 'value="' . key_of( $COL_STYLE ) . '">Remove</button>' ),
    ),
    array( 1, false, true ) );

// Put it back and refuse the delete instead: the base's cell is emptied first, so a map entry
// dropped here as well would leave the file in the uploads directory with nothing naming it.
$GLOBALS['attachments'][ $orphan ] = array( 'post_title' => 'A screenshot of your local site', 'file' => 'restored.png' );

$GLOBALS['patched']      = array();
$GLOBALS['deleted']      = array();
$GLOBALS['delete_fails'] = true;
$_POST = array( 'student' => $SID, 'field' => key_of( $COL_LOCAL ) );

ck( 'a delete that does not happen is said out loud', ran( 'handle_image_remove' ), 'report-image-partly' );
ck( 'and the map still names the file, so pressing Remove again can finish it',
    WPCPM_Student_Report_Form::images( $SID )[ $COL_LOCAL ], $orphan );

$GLOBALS['delete_fails'] = false;

ck( 'pressing it again finishes it',
    array( ran( 'handle_image_remove' ), isset( WPCPM_Student_Report_Form::images( $SID )[ $COL_LOCAL ] ) ),
    array( 'report-image-removed', false ) );

$_POST = array( 'student' => $SID );
post_images( array() );

echo "\n=== A grade and a screenshot refused in the same save ===\n";

// The `$rejected` pair exists so that an answer nobody could read is named rather than dropped in
// silence. A screenshot the student can see is missing from their own card; a grade typed as
// "eighty" looks saved unless the page says otherwise, so the grade is the one that has to be
// said out loud when both go wrong in one save.
$GLOBALS['ceiling'] = array();
$GLOBALS['patched'] = array();

$_POST = array(
	'student' => $SID,
	'report'  => array(
		key_of( 'Hours' )   => 'eighty',
		key_of( $COL_TOOL ) => 'WordPress Studio',
	),
);
post_images( array( $COL_LOCAL => fake_svg() ) );

ck( 'a rejected grade and a refused screenshot in one save names the grade', ran( 'handle_save' ), 'report-partly' );
ck( 'and the readable answer beside them was still written', patched_cells(), array( $COL_TOOL => 'WordPress Studio' ) );

// The same choice where nothing at all could be read: "nothing was saved" is the truer half of
// the two, because a refused screenshot stored no file either.
$GLOBALS['ceiling'] = array();
$GLOBALS['patched'] = array();

$_POST = array( 'student' => $SID, 'report' => array( key_of( 'Hours' ) => 'eighty' ) );
post_images( array( $COL_LOCAL => fake_svg() ) );

ck( 'and with nothing readable at all it still names the grade', ran( 'handle_save' ), 'report-rejected' );

$_POST = array( 'student' => $SID );
post_images( array() );

echo "\n=== A reader of somebody else's card ===\n";

$GLOBALS['uid']    = 99;
$GLOBALS['manage'] = array( 99 );

$read = body( true, 'manager' );

ck( 'a reader sees the picture', false !== strpos( $read, 'wpcpm-report__image-thumb' ), true );
ck( 'and no way to change it',
    array( false !== strpos( $read, 'type="file"' ), false !== strpos( $read, '>Remove</button>' ) ),
    array( false, false ) );

echo "\n=== The decisions are written down where they were made ===\n";

$source = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php' );

// Two decisions a later reader would otherwise undo: the file is public because that is the only
// way Airtable can fetch it, and the base's own URLs are never linked because they expire.
ck( 'the public URL is explained where it is written',
    array(
        false !== stripos( $source, 'Airtable fetches' ),
        false !== stripos( $source, 'expire' ),
    ),
    array( true, true ) );

// The user meta has to go when the plugin does; a stray row per student otherwise outlives it.
ck( 'the screenshot map is removed on uninstall',
    false !== strpos( (string) file_get_contents( __DIR__ . '/../uninstall.php' ), 'WPCPM_Student_Report_Form::META_IMAGES' ), true );

foreach ( $GLOBALS['temp_files'] as $tmp ) {
	if ( file_exists( $tmp ) ) {
		unlink( $tmp );
	}
}

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $checks );

exit( $fail ? 1 : 0 );
