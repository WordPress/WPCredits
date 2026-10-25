<?php
/**
 * The code Institutions release 2 retires stays gone.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The institution's notes are gone whole.** The class and its suite are not files any more, and no
 *   other file of the plugin names the class, its two handlers' actions or its flash. Live holds none
 *   of its notes, so nothing was migrated; the point of reading every file is that a `require_once` of
 *   a file that is not there is a fatal on the day the plugin boots, and a stand-in left in a suite is a
 *   claim that the class still exists.
 * - **A mentor note written for the institution's audience is read by nobody.** The gate used to hand
 *   such a note to the notes class; with that class retired the audience is no audience at all, and it
 *   must stay so even where something by the old class's name has been loaded. A row an older install
 *   still holds is neither shown on a mentor's page nor handed to a school: it waits for uninstall, which
 *   removes every row of the type.
 * - The mentor's own notes read as they did, so "nobody" is the audience's answer and not the gate's.
 *
 * Run from the plugin root:  php bin/test-institution-retired.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['users'] = array();
$GLOBALS['uid']   = 0;
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['manage'] = array();

class WP_User {
	public $ID = 0, $roles = array();
	public function __construct( $id = 0, $roles = array() ) { $this->ID = $id; $this->roles = $roles; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = 'private';
}

function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function get_user_by( $field, $value ) { return $GLOBALS['users'][ (int) $value ] ?? false; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function user_can( $user, $cap ) {
	$id = is_object( $user ) ? $user->ID : (int) $user;
	return WPCPM_Roles::CAP_MANAGE === $cap && in_array( $id, $GLOBALS['manage'], true );
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $key, $single = false ) {
	$value = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? ( $single ? '' : array() );
	return ( ! $single && ! is_array( $value ) ) ? array( $value ) : $value;
}

/** Stands in for the mentors sync: the record-ID shape. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return is_scalar( $value ) && (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}

/** Stands in for the mentors dashboard: each mentor's mentee list. */
class WPCPM_Mentors_Dashboard {
	public static function get_mentees( $user_id ) { return $GLOBALS['mentees'][ (int) $user_id ] ?? array(); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-notes.php';

$fail  = 0;
$total = 0;

function ck( $label, $actual, $expected ) {
	global $fail, $total;
	++$total;
	if ( $actual === $expected ) {
		echo 'ok   ' . $label . "\n";
		return;
	}
	++$fail;
	echo 'FAIL ' . $label . "\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

/** One note post, with the meta the gate reads. */
function note( $id, $record, $audience ) {
	$post              = new WP_Post();
	$post->ID          = $id;
	$post->post_type   = WPCPM_Mentor_Notes::POST_TYPE;
	$post->post_status = 'private';

	$GLOBALS['posts'][ $id ] = $post;
	$GLOBALS['pmeta'][ $id ] = array( WPCPM_Mentor_Notes::META_STUDENT => $record );

	if ( '' !== $audience ) {
		$GLOBALS['pmeta'][ $id ][ WPCPM_Mentor_Notes::META_AUDIENCE ] = $audience;
	}

	return $post;
}

echo "=== The institution's notes are gone whole ===\n";

ck( 'the class file is not there', file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-notes.php' ), false );
ck( 'nor its suite', file_exists( WPCPM_PLUGIN_DIR . 'bin/test-institution-notes.php' ), false );

// What would name the class, its two actions or its flash. This suite is the one file that has to name
// them, so it is the one file not read.
$needles = array(
	'Institution_Notes',
	'class-wpcpm-institution-notes',
	'wpcpm_add_institution_note',
	'wpcpm_delete_institution_note',
	'wpcpm-institution-notes',
);
$pattern = '/' . implode( '|', $needles ) . '/';
$named   = array();
$roots   = array( 'includes', 'bin', 'blocks', 'assets' );
$files   = array( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php', WPCPM_PLUGIN_DIR . 'uninstall.php' );

foreach ( $roots as $root ) {
	$walk = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . $root, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $walk as $file ) {
		if ( __FILE__ === $file->getPathname() ) {
			continue;
		}

		if ( in_array( strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) ), array( 'php', 'js', 'css', 'json' ), true ) ) {
			$files[] = $file->getPathname();
		}
	}
}

foreach ( $files as $file ) {
	if ( 1 === preg_match( $pattern, (string) file_get_contents( $file ) ) ) {
		$named[] = substr( $file, strlen( WPCPM_PLUGIN_DIR ) );
	}
}

sort( $named );
ck( 'no file of the plugin, its suites or its stand-ins names the class, its actions or its flash', $named, array() );

$main = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );
$boot = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );
ck( 'the plugin loads no file for them and the module boots no class for them', array(
	false !== strpos( $main, 'institution-notes' ),
	false !== strpos( $boot, 'Institution_Notes' ),
), array( false, false ) );

echo "\n=== A note for the institution's audience is read by nobody ===\n";

$record = 'recSTUDENT1234567';

$GLOBALS['users']   = array(
	1  => new WP_User( 1, array( 'administrator' ) ),
	20 => new WP_User( 20, array( WPCPM_Roles::ROLE_MENTOR ) ),
	21 => new WP_User( 21, array( WPCPM_Roles::ROLE_INSTITUTION ) ),
	22 => new WP_User( 22, array( WPCPM_Roles::ROLE_STUDENT ) ),
);
$GLOBALS['manage']  = array( 1 );
$GLOBALS['mentees'] = array( 20 => array( array( 'record_id' => $record ) ) );

$mentors = note( 301, $record, '' );
$named_m = note( 302, $record, WPCPM_Mentor_Notes::AUDIENCE_MENTOR );
$schools = note( 303, $record, WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION );
$strange = note( 304, $record, 'something-else' );

$readers = array( 'a program manager' => 1, 'the student\'s mentor' => 20, 'a member of the school' => 21, 'the student' => 22 );

// The baseline: the gate still passes the mentor's own notes, or "nobody" below would prove nothing.
ck( 'the mentor\'s own notes, written before and since the audience existed, are read by the mentor and the program',
	array(
		WPCPM_Mentor_Notes::user_can_read_note( $mentors, 20 ), WPCPM_Mentor_Notes::user_can_read_note( $named_m, 20 ),
		WPCPM_Mentor_Notes::user_can_read_note( $mentors, 1 ), WPCPM_Mentor_Notes::user_can_read_note( $named_m, 1 ),
	),
	array( true, true, true, true ) );
ck( 'and by nobody else', array(
	WPCPM_Mentor_Notes::user_can_read_note( $mentors, 21 ), WPCPM_Mentor_Notes::user_can_read_note( $named_m, 22 ),
), array( false, false ) );

$answers = array();
foreach ( $readers as $who => $id ) {
	$answers[ $who ] = WPCPM_Mentor_Notes::user_can_read_note( $schools, $id );
}

ck( 'a note for the institution\'s audience is read by none of them, a program manager included', $answers,
	array( 'a program manager' => false, 'the student\'s mentor' => false, 'a member of the school' => false, 'the student' => false ) );
ck( 'and a note whose audience is neither is read by nobody either', array(
	WPCPM_Mentor_Notes::user_can_read_note( $strange, 1 ), WPCPM_Mentor_Notes::user_can_read_note( $strange, 20 ),
), array( false, false ) );
ck( 'the audience is still told apart from the mentor\'s, so such a row is never shown as one of them', array(
	WPCPM_Mentor_Notes::audience_of( $schools ), WPCPM_Mentor_Notes::audience_of( $mentors ),
), array( 'institution', 'mentor' ) );

// Something by the old class's name, loaded on a site that still had the old file: the gate does not
// ask it. Declared last, after every answer above was read without it.
if ( ! class_exists( 'WPCPM_Institution_Notes' ) ) {
	/** A stand-in for a class the plugin no longer ships, whose answer is always yes. */
	class WPCPM_Institution_Notes {
		public static function user_can_read( $note, $user = null ) { return true; }
	}
}

ck( 'and it stays unread even where a class by the old name answers yes', array(
	WPCPM_Institution_Notes::user_can_read( $schools, 21 ),
	WPCPM_Mentor_Notes::user_can_read_note( $schools, 21 ),
	WPCPM_Mentor_Notes::user_can_read_note( $schools, 1 ),
), array( true, false, false ) );

echo "\n=== Words ===\n";

$dashes = array();
foreach ( array( 'bin/test-institution-retired.php', 'includes/modules/class-wpcpm-mentor-notes.php' ) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}
ck( 'no dash but the plain hyphen in the suite or the gate', $dashes, array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
