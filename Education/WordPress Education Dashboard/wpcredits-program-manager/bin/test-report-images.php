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
// This run's own folder (bin/stubs/temp-dir.php): the image handler's copies land in it, the
// fixtures and the uploads folder too, and all of it goes when the run ends.
function get_temp_dir() { return wpcpm_test_temp_dir(); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function add_action() {} function register_rest_route() {}
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
// The flash slashes what it writes for core's user meta, which unslashes it; this one keeps what it
// is handed, so the slash is the identity here (bin/test-flash.php holds the flash to core's).
function wp_slash( $v ) { return $v; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return isset( $GLOBALS['opts'][ 'T_' . $k ] ) ? $GLOBALS['opts'][ 'T_' . $k ] : false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ] ); return true; }
function wp_upload_dir() { $d = wpcpm_test_temp_dir() . 'uploads'; if ( ! is_dir( $d ) ) { mkdir( $d ); } return array( 'path' => $d, 'url' => 'https://example.test/uploads', 'error' => false ); }
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
	const META_COURSE  = 'wpcpm_student_course';
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
require_once __DIR__ . '/stubs/temp-dir.php';
// Declares add_filter() and apply_filters(), which run what is hooked: the program map is made of filters.
require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-flash.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/class-wpcpm-image-upload.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';

// The program map and the Designer Track's form are the compiled tracks' (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

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
	$p  = wpcpm_test_tempnam( 'wpcpm-shot-', 'png' );
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 30, 90, 200 ) );
	imagepng( $im, $p );

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
	$p = wpcpm_test_tempnam( 'wpcpm-shot-', 'png' );
	file_put_contents( $p, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><script>alert(1)</script></svg>' );

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

// A sponsor's logo is held to a wider floor than this, and only where the logo upload asks for
// it. A screenshot is not a logo: the same handler takes it at the base minimum, so a 250 pixel
// wide export still saves, and the form must not ask the handler for the logo floor.
$GLOBALS['patched'] = array();
post_images( array( $COL_LOCAL => png( 250, 150 ) ) );
ck( 'a screenshot 250 pixels wide is saved: the logo floor is not the screenshot\'s', ran( 'handle_save' ), 'report-saved' );
ck( 'and it is stored', count( $GLOBALS['attachments'] ), 1 );
ck( 'the form asks the handler for no floor of its own', false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php' ), 'min_width' ), false );

// Back to a clean student for the checks that follow, the file this one wrote included: the next
// upload is named by the free name in the folder.
foreach ( $GLOBALS['attachments'] as $attachment ) {
	wp_delete_file( $attachment['file'] );
}
$GLOBALS['attachments'] = array();
$GLOBALS['patched']     = array();
$GLOBALS['ceiling']     = array();
delete_user_meta( $SID, WPCPM_Student_Report_Form::META_IMAGES );

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

// Remove deletes the file from the Media Library and empties the program records' cell, so it asks
// first (forms.js reads the mark). The question is on the form every Remove posts through, which holds
// hidden fields and nothing else, and not on a button: a button that names its form in a `form`
// attribute is still a descendant of the report form in the markup, and forms.js looks for a marked
// submit control among a form's descendants when it cannot tell what was pressed, so a question on the
// button could be the one a Save was asked.
$remove_asks = 'Remove this screenshot? It goes from this site and from the program records. You can upload another one afterward.';
$remove_form = (string) substr( $saved, (int) strpos( $saved, '<form class="wpcpm-report__remove" id="wpcpm-report-remove-7"' ) );
$remove_form = substr( $remove_form, 0, (int) strpos( $remove_form, '</form>' ) );

ck( 'Remove asks before it deletes: the form it posts through carries the question, escaped as an attribute',
    false !== strpos( $remove_form, ' data-wpcpm-confirm="' . esc_attr( $remove_asks ) . '">' ), true );
ck( 'and it is the only question on the card: the Remove button and the report form carry none',
    array( substr_count( $saved, 'data-wpcpm-confirm' ), substr_count( $remove_form, 'data-wpcpm-confirm' ) ),
    array( 1, 1 ) );

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
// The report route draws this view into the Mentor Report Card and the Institution's student page
// after they load, so nothing in it can post, or ask.
ck( 'and no question to ask: the Remove that asks is drawn for an editor only',
    substr_count( $read, 'data-wpcpm-confirm' ), 0 );

echo "\n=== A student on no track saves hours (TRACKS-5) ===\n";

// Paused, Pending graduation and the finished states are on no track, and since TRACKS-5 the
// Student Report Card draws them the 150-hour form's hours box: the box posts the Hours column,
// the save writes it through the same fields() read, and the box reads it back. Checked here
// rather than in bin/test-student-modules.php, whose report form is a stand-in: this is the suite
// that saves a report through the real handler and reads what reached Airtable.
$GLOBALS['uid']     = $SID;
$GLOBALS['manage']  = array();
$GLOBALS['patched'] = array();
update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => 'Paused' ) );
set_transient( 'wpcpm_report_' . md5( $REC ), array() );

$hours_box = function () use ( $SID ) {
	ob_start();
	WPCPM_Student_Report_Form::render_hours( new WP_User( $SID ), WPCPM_Students_Sync::get_program( $SID ) );

	return (string) ob_get_clean();
};

ck( 'a student on no track is drawn the hours box, posting the Hours column, empty',
    1 === preg_match( '/<input type="number" id="wpcpm-report-' . key_of( 'Hours' ) . '" name="report\[' . key_of( 'Hours' ) . '\]" value=""/', $hours_box() ), true );

post_images( array() );
$_POST = array( 'student' => $SID, 'report' => array( key_of( 'Hours' ) => '42' ) );

ck( 'the save writes Hours to their record and nothing else, and clears the copy it saved over',
    array( ran( 'handle_save' ), patched_cells(), get_transient( 'wpcpm_report_' . md5( $REC ) ) ),
    array( 'report-saved', array( 'Hours' => 42 ), false ) );

// What the base holds now, put where values() reads first.
set_transient( 'wpcpm_report_' . md5( $REC ), array( 'Hours' => 42 ) );

ck( 'and the box reads the saved value back',
    1 === preg_match( '/name="report\[' . key_of( 'Hours' ) . '\]" value="42"/', $hours_box() ), true );

echo "\n=== A student waiting to graduate keeps their own track's form ===\n";

// Pending graduation is on no track, so read off the status alone the form fell back to the 150-hour
// track's questions the day a student finished the work, and the answers they gave under their own
// track's columns went out of sight. The account remembers the track the students sync last saw
// (`WPCPM_Students_Sync::META_COURSE`), and the form, the save and the hours box all read it.

/**
 * Whether a rendered form draws a question: by the id its control carries, or for the team tiles,
 * which are a group of checkboxes with no one control to give an id to, by the name they post under.
 *
 * @param string $html The form.
 * @param string $name Airtable field name.
 * @return bool
 */
function asks( $html, $name ) {
	return false !== strpos( $html, 'id="wpcpm-report-' . key_of( $name ) . '"' )
		|| false !== strpos( $html, 'name="report[' . key_of( $name ) . '][]"' );
}

$GLOBALS['uid']     = $SID;
$GLOBALS['manage']  = array();
$GLOBALS['patched'] = array();
$PATCH              = 'Patch Testing: Trac ticket comments';
$FINAL              = 'Final Contribution Project Report';
$dev_only           = array_values( array_diff( array_keys( WPCPM_Student_Report_Form::fields( 'dev' ) ), array_keys( WPCPM_Student_Report_Form::fields( '150h' ) ) ) );
$long_only          = array_values( array_diff( array_keys( WPCPM_Student_Report_Form::fields( '150h' ) ), array_keys( WPCPM_Student_Report_Form::fields( '50h' ) ) ) );

// A Developer Track student, moved to Pending graduation, with an answer only their track asks.
update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => WPCPM_Program::STATUS_PENDING ) );
update_user_meta( $SID, WPCPM_Students_Sync::META_COURSE, WPCPM_Program::STATUS_DEV );
set_transient( 'wpcpm_report_' . md5( $REC ), array( $PATCH => 'Tested a patch on ticket 12345.', 'Hours' => 30 ) );
post_images( array() );

$dev_form = body();

ck( 'a pending Developer Track student is asked every question their track asks',
    count( array_filter( $dev_only, static function ( $name ) use ( $dev_form ) { return asks( $dev_form, $name ); } ) ), count( $dev_only ) );
ck( 'with the answer they saved under it still in the box', false !== strpos( $dev_form, 'Tested a patch on ticket 12345.' ), true );

$_POST = array( 'student' => $SID, 'report' => array( key_of( $PATCH ) => 'Tested two patches.' ) );

ck( 'and saving it writes their own track\'s column', array( ran( 'handle_save' ), patched_cells() ), array( 'report-saved', array( $PATCH => 'Tested two patches.' ) ) );

set_transient( 'wpcpm_report_' . md5( $REC ), array( $PATCH => 'Tested two patches.', 'Hours' => 30 ) );

ck( 'and their hours box still reads the hours they logged',
    1 === preg_match( '/name="report\[' . key_of( 'Hours' ) . '\]" value="30"/', $hours_box() ), true );

// A 50-hour student, the same: their eleven questions, the final project report among them, and none
// of the questions only the 150-hour course asks.
$GLOBALS['patched'] = array();
update_user_meta( $SID, WPCPM_Students_Sync::META_COURSE, WPCPM_Program::STATUS_50H );
set_transient( 'wpcpm_report_' . md5( $REC ), array( $FINAL => "My project.\nIt shipped." ) );

$fifty_form = body();
$fifty      = array_diff( array_keys( WPCPM_Student_Report_Form::fields( '50h' ) ), array( 'Hours' ) );

ck( 'the 50-hour form has eleven questions, one of them the hours the box above it asks', count( WPCPM_Student_Report_Form::fields( '50h' ) ), 11 );
ck( 'a pending 50-hour student is asked the other ten, the final project report among them',
    array( count( array_filter( $fifty, static function ( $name ) use ( $fifty_form ) { return asks( $fifty_form, $name ); } ) ), asks( $fifty_form, $FINAL ) ),
    array( 10, true ) );
ck( 'and none of the questions only the 150-hour course asks',
    count( array_filter( $long_only, static function ( $name ) use ( $fifty_form ) { return asks( $fifty_form, $name ); } ) ), 0 );
ck( 'with the final project report they saved in its box', false !== strpos( $fifty_form, 'My project.' ), true );

$_POST = array( 'student' => $SID, 'report' => array( key_of( $FINAL ) => 'My project, finished.' ) );

ck( 'and saving it writes the final project report', array( ran( 'handle_save' ), patched_cells() ), array( 'report-saved', array( $FINAL => 'My project, finished.' ) ) );

// A pending student the site knows no course for reads what every student on no track reads: the
// 150-hour form. So does a paused one, whatever their account remembers.
update_user_meta( $SID, WPCPM_Students_Sync::META_COURSE, '' );
set_transient( 'wpcpm_report_' . md5( $REC ), array() );

$unknown = body();

ck( 'a pending student with no known course is drawn the 150-hour form, as before',
    array( asks( $unknown, $long_only[0] ), asks( $unknown, $FINAL ) ), array( true, false ) );

update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => 'Paused' ) );
update_user_meta( $SID, WPCPM_Students_Sync::META_COURSE, WPCPM_Program::STATUS_DEV );

ck( 'and a paused student is drawn it too, whatever their account remembers', asks( body(), $dev_only[0] ), false );

echo "\n=== A question filled in by the program is never written by the card (1.123.46) ===\n";

// The certificate code of three live tracks is a line of text an Airtable automation fills in, and
// the card drew it as a box like any other. A save posts every box on the form, so a page drawn
// before the code arrived, or inside the five-minute cache after it did, sent the code cell empty
// and wiped it; a typed or cleared box was saved as it was; and a hand-made request could set it
// to anything (the test of 28 October 2026, cases a to g). A question flagged `read_only` is drawn
// as a row the student reads and copies, with no control and so nothing posted, and the save skips
// it before it looks at what was posted. Asked here, through the real save, of a form shaped like
// those tracks', compiled from a definition the way a publish compiles one.

/**
 * What a browser sends when the report form in this markup is submitted.
 *
 * Every named control that is not disabled, in document order: a box's value, a text area's text,
 * a select's chosen option, a checkbox or radio only when ticked, and no file input or button.
 * Built into a query string and parsed the way PHP parses a post, so a name sent twice keeps the
 * last value and `report[key][]` arrives as a list, as on the live site.
 *
 * @param string $html The rendered card.
 * @return array What `$_POST` would hold.
 */
function submitted( $html ) {
	$doc = new DOMDocument();

	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();

	$xpath = new DOMXPath( $doc );
	$form  = $xpath->query( '//form[contains(concat(" ", normalize-space(@class), " "), " wpcpm-report ")]' )->item( 0 );
	$pairs = array();

	if ( ! $form instanceof DOMElement ) {
		return array();
	}

	foreach ( $xpath->query( './/input|.//textarea|.//select', $form ) as $control ) {
		$name = $control->getAttribute( 'name' );

		if ( '' === $name || $control->hasAttribute( 'disabled' ) ) {
			continue;
		}

		$tag = strtolower( $control->nodeName );

		if ( 'input' === $tag ) {
			$type = strtolower( '' !== $control->getAttribute( 'type' ) ? $control->getAttribute( 'type' ) : 'text' );

			if ( in_array( $type, array( 'file', 'submit', 'button', 'reset', 'image' ), true ) || ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $control->hasAttribute( 'checked' ) ) ) {
				continue;
			}

			$value = $control->getAttribute( 'value' );
		} elseif ( 'textarea' === $tag ) {
			$value = $control->textContent;
		} else {
			$value = '';

			foreach ( $xpath->query( './/option', $control ) as $index => $option ) {
				if ( 0 === $index || $option->hasAttribute( 'selected' ) ) {
					$value = $option->getAttribute( 'value' );
				}
			}
		}

		$pairs[] = rawurlencode( $name ) . '=' . rawurlencode( $value );
	}

	parse_str( implode( '&', $pairs ), $post );

	return $post;
}

/**
 * What the record holds after the save's PATCH: the cells it named merged over what was there, an
 * empty value clearing its cell, as Airtable merges a PATCH.
 *
 * @param array $before The record's cells before the save.
 * @return array
 */
function record_after( array $before ) {
	foreach ( patched_cells() as $column => $value ) {
		if ( null === $value || '' === $value || array() === $value ) {
			unset( $before[ $column ] );
		} else {
			$before[ $column ] = $value;
		}
	}

	return $before;
}

$CODE      = 'Certificate code (Foundations Track)';
$CODE_KEY  = key_of( $CODE );
$CLOSING   = 'Closing post URL';
$code_spec = array( 'label' => 'When visible, copy and paste this number in the course Final Lesson Quiz', 'type' => 'text', 'group' => 'wrapup', 'help' => 'Please, do not modify this field by yourself.', 'airtable_type' => 'singleLineText' );

/**
 * Plant the 150-hour track's compiled form as a Foundations-shaped track of six questions, the code
 * question flagged or not, the way publishing writes a form: normalized, then compiled.
 *
 * @param bool $flagged Whether the code question carries `read_only`.
 */
function plant_code_track( $flagged ) {
	global $CODE, $CLOSING, $code_spec;

	$definition = WPCPM_Track_Definition::normalize(
		array(
			'schema_version' => 1,
			'status'         => WPCPM_Program::STATUS_150H,
			'key'            => '150h',
			'label'          => 'Foundations Track',
			'hue'            => 'blue',
			'questions'      => array(
				'Hours'                        => array( 'label' => 'Hours contributed', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 10000, 'group' => 'hours', 'airtable_type' => 'number' ),
				'Slack Name'                   => array( 'label' => 'Your Slack name', 'type' => 'text', 'group' => 'onboarding', 'maxlength' => 100, 'airtable_type' => 'singleLineText' ),
				'WordPress Profile'            => array( 'label' => 'Your WordPress.org profile', 'type' => 'url', 'group' => 'onboarding', 'airtable_type' => 'url' ),
				'Contribution Project Summary' => array( 'label' => 'What you worked on', 'type' => 'textarea', 'group' => 'project', 'airtable_type' => 'multilineText' ),
				'Personal Website URL'         => array( 'label' => 'Your website', 'type' => 'url', 'group' => 'wrapup', 'airtable_type' => 'url' ),
				$CLOSING                       => array( 'label' => 'Your closing post', 'type' => 'url', 'group' => 'wrapup', 'airtable_type' => 'url' ),
				$CODE                          => $code_spec + array( 'read_only' => (bool) $flagged ),
			),
		)
	);

	$GLOBALS['opts'][ WPCPM_Tracks::OPT_FIELDS_PREFIX . '150h' ] = WPCPM_Track_Definition::compile_fields( $definition );
	WPCPM_Tracks::flush();
}

$GLOBALS['uid']     = $SID;
$GLOBALS['manage']  = array();
$GLOBALS['patched'] = array();
update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => WPCPM_Program::STATUS_150H ) );
update_user_meta( $SID, WPCPM_Students_Sync::META_COURSE, '' );
post_images( array() );

$answers = array( 'Slack Name' => 'student-one', 'WordPress Profile' => 'https://profiles.wordpress.org/student-one/', 'Contribution Project Summary' => 'Triaged tickets.', 'Personal Website URL' => 'https://example.org/', $CLOSING => 'https://example.org/closing/' );

echo "\n--- the control: the same form, the code question not flagged ---\n";

// What the test of 28 October 2026 found, reproduced through the real save, so the checks below
// are seen to fail for the reason the fix removes and not for one of the harness's own.
plant_code_track( false );
set_transient( 'wpcpm_report_' . md5( $REC ), $answers );
$stale_unflagged = body();
$GLOBALS['patched'] = array();
$_POST              = submitted( $stale_unflagged );
$_POST['student']   = $SID;

ck( 'unflagged, the code is a box like any other, and a page drawn before the code arrived sends its cell empty, so the save wipes the code',
    array( false !== strpos( $stale_unflagged, 'name="report[' . $CODE_KEY . ']"' ), ran( 'handle_save' ), array_key_exists( $CODE, patched_cells() ) ? patched_cells()[ $CODE ] : 'not sent', record_after( $answers + array( $CODE => '7421' ) ) ),
    array( true, 'report-saved', '', $answers ) );

echo "\n--- the card ---\n";

plant_code_track( true );
set_transient( 'wpcpm_report_' . md5( $REC ), $answers );
$stale = body();

// The row is the plugin's read-only drawing, a reader's label and value, with the help under it.
ck( 'a page drawn before the code arrived shows the question\'s words and its help, and no box at all: no control carries its id or its name',
    array(
        false !== strpos( $stale, '<p class="wpcpm-field wpcpm-field--read wpcpm-field--by-program wpcpm-field--text" id="wpcpm-report-' . $CODE_KEY . '"><span class="wpcpm-field__label">When visible, copy and paste this number in the course Final Lesson Quiz</span><span class="wpcpm-field__hint">Please, do not modify this field by yourself.</span></p>' ),
        substr_count( $stale, 'report[' . $CODE_KEY . ']' ),
        1 === preg_match( '/<(input|textarea|select)[^>]*id="wpcpm-report-' . $CODE_KEY . '"/', $stale ),
        array_key_exists( $CODE_KEY, isset( submitted( $stale )['report'] ) ? submitted( $stale )['report'] : array() ),
    ),
    array( true, 0, false, false ) );
ck( 'and nothing standing in for an answer: no "Not filled in", which would read as something the student left undone',
    false !== strpos( $stale, 'Not filled in' ), false );
ck( 'while every other question is the box it was, still posted',
    array_keys( submitted( $stale )['report'] ),
    array( key_of( 'Slack Name' ), key_of( 'WordPress Profile' ), key_of( 'Contribution Project Summary' ), key_of( 'Personal Website URL' ), key_of( $CLOSING ) ) );

set_transient( 'wpcpm_report_' . md5( $REC ), $answers + array( $CODE => '7421' ) );
$arrived = body();

ck( 'once the code is on record it is shown as text the student can select and copy, between the words and the help',
    false !== strpos( $arrived, '<span class="wpcpm-field__label">When visible, copy and paste this number in the course Final Lesson Quiz</span><span class="wpcpm-field__value">7421</span><span class="wpcpm-field__hint">Please, do not modify this field by yourself.</span></p>' ), true );
ck( 'and still with nothing that posts it', substr_count( $arrived, 'report[' . $CODE_KEY . ']' ), 0 );

echo "\n--- the save: the cases of 28 October 2026 ---\n";

/**
 * Save a page as a browser would, with these changes to what it posts, and report the flash, the
 * cells the PATCH named, and the record after it.
 *
 * @param string $html    The page as it was drawn.
 * @param array  $changes Form key => what the student sent instead; null removes the key.
 * @param array  $before  What the record held when the save arrived.
 * @return array
 */
function save_page( $html, array $changes, array $before ) {
	global $SID;

	$GLOBALS['patched'] = array();
	$_POST              = submitted( $html );
	$_POST['student']   = $SID;

	foreach ( $changes as $key => $value ) {
		if ( null === $value ) {
			unset( $_POST['report'][ $key ] );
		} else {
			$_POST['report'][ $key ] = $value;
		}
	}

	$flash = ran( 'handle_save' );

	return array( $flash, array_keys( patched_cells() ), array() === $GLOBALS['patched'] ? $before : record_after( $before ) );
}

$every   = array( 'Slack Name', 'WordPress Profile', 'Contribution Project Summary', 'Personal Website URL', $CLOSING );
$on_base = $answers + array( $CODE => '7421' );

ck( 'c: the stale page, the code arrived since and the student changed another answer: the other answers are saved and the code is not sent, so it stays',
    save_page( $stale, array( key_of( $CLOSING ) => 'https://example.org/closing-2/' ), $on_base ),
    array( 'report-saved', $every, array_merge( $on_base, array( $CLOSING => 'https://example.org/closing-2/' ) ) ) );
ck( 'c2: a fresh page drawn from the cache before it knew the code is the same page, and the same save',
    save_page( body(), array(), $on_base )[1], $every );

set_transient( 'wpcpm_report_' . md5( $REC ), $answers );
ck( 'a: a code typed into a hand-made copy of the form is not saved, and every other answer is',
    save_page( $stale, array( $CODE_KEY => '1234-typed' ), $on_base ),
    array( 'report-saved', $every, $on_base ) );
ck( 'b: a cleared code is not saved either: the empty string never reaches the cell',
    save_page( $arrived, array( $CODE_KEY => '' ), $on_base ),
    array( 'report-saved', $every, $on_base ) );

set_transient( 'wpcpm_report_' . md5( $REC ), $answers + array( $CODE => '1111' ) );
$old_page = body();
ck( 'd: a page holding an old code, saved after the code changed, leaves the new one: the old one is not posted, and posted by hand it is not sent',
    array( save_page( $old_page, array(), $answers + array( $CODE => '2222' ) ), save_page( $old_page, array( $CODE_KEY => '1111' ), $answers + array( $CODE => '2222' ) ) ),
    array( array( 'report-saved', $every, $answers + array( $CODE => '2222' ) ), array( 'report-saved', $every, $answers + array( $CODE => '2222' ) ) ) );

$GLOBALS['patched'] = array();
$_POST              = array( 'student' => $SID, 'report' => array( $CODE_KEY => 'anything-at-all' ) );
ck( 'g: a hand-made request naming the code alone is ignored: nothing to save, and no request to Airtable at all',
    array( ran( 'handle_save' ), $GLOBALS['patched'] ), array( 'report-nothing', array() ) );

echo "\n--- an Administrator on the student's card, and a reader ---\n";

// An Administrator may fill in a student's report from the student's own card (`user_can_edit()`):
// the same page, the same save, and so the same five-minute cache and the same stale tab. The flag
// holds for them too; the program's own writer of the code is the Airtable automation.
$GLOBALS['uid']    = 99;
$GLOBALS['manage'] = array( 99 );
set_transient( 'wpcpm_report_' . md5( $REC ), $answers );
$on_behalf = body();

ck( 'an Administrator on the student\'s card is drawn the same row, with nothing that posts it, and a code they send is not saved',
    array( substr_count( $on_behalf, 'report[' . $CODE_KEY . ']' ), false !== strpos( $on_behalf, 'wpcpm-field--by-program' ), save_page( $on_behalf, array( $CODE_KEY => '9999' ), $on_base )[1] ),
    array( 0, true, $every ) );

set_transient( 'wpcpm_report_' . md5( $REC ), $answers + array( $CODE => '7421' ) );
$reading = body( true, 'manager' );
set_transient( 'wpcpm_report_' . md5( $REC ), $answers );
$reading_empty = body( true, 'manager' );

ck( 'a reader of the card, an Administrator from a mentor\'s page or the student\'s mentor, reads it as every other answer, as before',
    array(
        false !== strpos( $reading, '<p class="wpcpm-field wpcpm-field--read wpcpm-field--text" id="wpcpm-report-' . $CODE_KEY . '"><span class="wpcpm-field__label">When visible, copy and paste this number in the course Final Lesson Quiz</span><span class="wpcpm-field__value">7421</span></p>' ),
        false !== strpos( $reading_empty, 'id="wpcpm-report-' . $CODE_KEY . '"><span class="wpcpm-field__label">When visible, copy and paste this number in the course Final Lesson Quiz</span><span class="wpcpm-field__value wpcpm-field__value--empty">Not filled in</span></p>' ),
    ),
    array( true, true ) );

$GLOBALS['uid']    = $SID;
$GLOBALS['manage'] = array();

// The four original tracks' forms back, for whatever follows.
wpcpm_seed_compiled_options( $GLOBALS['opts'] );
update_user_meta( $SID, WPCPM_Students_Sync::META_PROGRAM, array( 'record_id' => $REC, 'program' => 'Paused' ) );
update_user_meta( $SID, WPCPM_Students_Sync::META_COURSE, WPCPM_Program::STATUS_DEV );
set_transient( 'wpcpm_report_' . md5( $REC ), array() );
$_POST = array();

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

// The question is translatable and escaped for an attribute; the suite's esc_attr__() cannot tell the
// two escapers apart, so the source is read.
ck( 'the Remove question is read through esc_attr__() in the plugin\'s text domain, with no placeholder to explain',
    false !== strpos( $source, "esc_attr__( 'Remove this screenshot? It goes from this site and from the program records. You can upload another one afterward.', 'wpcredits-program-manager' )" ), true );

// The user meta has to go when the plugin does; a stray row per student otherwise outlives it.
ck( 'the screenshot map is removed on uninstall',
    false !== strpos( (string) file_get_contents( __DIR__ . '/../uninstall.php' ), 'WPCPM_Student_Report_Form::META_IMAGES' ), true );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $checks );

exit( $fail ? 1 : 0 );
