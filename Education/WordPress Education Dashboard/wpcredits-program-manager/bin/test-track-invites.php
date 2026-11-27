<?php
/**
 * WPCPM_Track_Invites: each track's private course invitation link (#298), which the student
 * welcome prints.
 *
 * What is pinned here and why:
 *
 * - is_link() keeps only an https address on learn.wordpress.org, with no credentials, port,
 *   fragment or whitespace: the link goes into an email as it is, so anything else is refused.
 * - save() stores a link by track post, clears it on '', refuses a link off Learn with nothing
 *   written, and removes the option once the last link is gone.
 * - for_status() answers the link of the live track a status names, and '' for a status that is no
 *   live track or a track with none.
 * - for_student() reads the student's program row as the Student Report Card does, a student
 *   waiting to graduate through the course they took, and answers '' for a student on no track.
 *
 * Run from the plugin root:  php bin/test-track-invites.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']  = array();
$GLOBALS['umeta'] = array();

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $name ] : $fallback;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['opts'][ $name ] = $value;
	$GLOBALS['autoload']      = $autoload;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['opts'][ $name ] );
	return true;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['umeta'][ $user_id ][ $key ] ) ? $GLOBALS['umeta'][ $user_id ][ $key ] : '';
}

class WPCPM_Tracks {
	public static function live() {
		return array(
			'In Sensei'         => array( 'key' => 'base', 'post' => 11 ),
			'Developer Track'   => array( 'key' => 'dev', 'post' => 12 ),
			'Translation Track' => array( 'key' => 'translation', 'post' => 13 ),
		);
	}
}

class WPCPM_Students_Sync {
	const META_PROGRAM = 'wpcpm_student_program';
	const META_COURSE  = 'wpcpm_student_course';
}

class WPCPM_Program {
	const STATUS_PENDING = 'Pending graduation';

	public static function is_track( $status ) {
		return isset( WPCPM_Tracks::live()[ $status ] );
	}

	public static function course_status( $status, $user_id = 0 ) {
		$status = trim( (string) $status );

		if ( self::is_track( $status ) ) {
			return $status;
		}

		if ( self::STATUS_PENDING !== $status || (int) $user_id < 1 ) {
			return '';
		}

		$kept = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_COURSE, true );

		return self::is_track( $kept ) ? $kept : '';
	}
}

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-invites.php';

$failures = 0;

function check( $label, $got, $want ) {
	global $failures;

	if ( $got === $want ) {
		echo "ok   {$label}\n";
		return;
	}

	++$failures;
	echo "FAIL {$label}\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) . "\n";
}

echo "\n=== is_link() ===\n";

check(
	'an https address on learn.wordpress.org is a link, whatever its path and query',
	array(
		WPCPM_Track_Invites::is_link( 'https://learn.wordpress.org/?group_invite=abc123' ),
		WPCPM_Track_Invites::is_link( 'https://learn.wordpress.org/course/wordpress-credits-foundations/join/abc' ),
		WPCPM_Track_Invites::is_link( 'HTTPS://Learn.WordPress.org/x' ),
	),
	array( true, true, true )
);

check(
	'and nothing else is: http, another host, a look-alike host, credentials, a port, a fragment, whitespace, an empty string',
	array(
		WPCPM_Track_Invites::is_link( 'http://learn.wordpress.org/x' ),
		WPCPM_Track_Invites::is_link( 'https://example.test/x' ),
		WPCPM_Track_Invites::is_link( 'https://learn.wordpress.org.example.test/x' ),
		WPCPM_Track_Invites::is_link( 'https://user:pass@learn.wordpress.org/x' ),
		WPCPM_Track_Invites::is_link( 'https://learn.wordpress.org:8443/x' ),
		WPCPM_Track_Invites::is_link( 'https://learn.wordpress.org/x#y' ),
		WPCPM_Track_Invites::is_link( "https://learn.wordpress.org/x\nBcc: a@b" ),
		WPCPM_Track_Invites::is_link( '' ),
	),
	array( false, false, false, false, false, false, false, false )
);

echo "\n=== save() ===\n";

check( 'a link is stored by track post, not autoloaded', array( WPCPM_Track_Invites::save( 11, ' https://learn.wordpress.org/?invite=base ' ), get_option( WPCPM_Track_Invites::OPTION ), $GLOBALS['autoload'] ), array( true, array( 11 => 'https://learn.wordpress.org/?invite=base' ), false ) );

WPCPM_Track_Invites::save( 12, 'https://learn.wordpress.org/?invite=dev' );
$refused = WPCPM_Track_Invites::save( 12, 'https://example.test/?invite=dev' );

check( 'a link off Learn is refused with its sentence, and the one stored stays', array( is_wp_error( $refused ), WPCPM_Track_Invites::for_post( 12 ) ), array( true, 'https://learn.wordpress.org/?invite=dev' ) );
check( 'no track is refused', is_wp_error( WPCPM_Track_Invites::save( 0, 'https://learn.wordpress.org/?invite=x' ) ), true );
check( 'an empty link clears the track\'s and leaves the others', array( WPCPM_Track_Invites::save( 12, '' ), WPCPM_Track_Invites::all() ), array( true, array( 11 => 'https://learn.wordpress.org/?invite=base' ) ) );

WPCPM_Track_Invites::save( 11, '' );
check( 'and the option goes once the last link is gone', array_key_exists( WPCPM_Track_Invites::OPTION, $GLOBALS['opts'] ), false );

$GLOBALS['opts'][ WPCPM_Track_Invites::OPTION ] = array( 11 => 'https://learn.wordpress.org/?invite=base', 12 => 'javascript:alert(1)', 'x' => 'https://learn.wordpress.org/?invite=x' );
check( 'all() leaves out a stored value that is no link or is under no track', WPCPM_Track_Invites::all(), array( 11 => 'https://learn.wordpress.org/?invite=base' ) );

echo "\n=== for_status() and for_student() ===\n";

check(
	'the live track a status names answers its link; a track without one, an unknown status and none answer nothing',
	array( WPCPM_Track_Invites::for_status( 'In Sensei' ), WPCPM_Track_Invites::for_status( 'Developer Track' ), WPCPM_Track_Invites::for_status( 'Retired Track' ), WPCPM_Track_Invites::for_status( '' ) ),
	array( 'https://learn.wordpress.org/?invite=base', '', '', '' )
);

$GLOBALS['umeta'][ 5 ] = array( WPCPM_Students_Sync::META_PROGRAM => array( 'program' => 'In Sensei' ) );
$GLOBALS['umeta'][ 6 ] = array( WPCPM_Students_Sync::META_PROGRAM => array( 'program' => 'Pending graduation' ), WPCPM_Students_Sync::META_COURSE => 'In Sensei' );
$GLOBALS['umeta'][ 7 ] = array( WPCPM_Students_Sync::META_PROGRAM => array( 'program' => 'Paused' ) );

check(
	'a student on the track gets its link, so does one waiting to graduate from it; a student on no track, with no program row, or no account gets none',
	array( WPCPM_Track_Invites::for_student( 5 ), WPCPM_Track_Invites::for_student( 6 ), WPCPM_Track_Invites::for_student( 7 ), WPCPM_Track_Invites::for_student( 8 ), WPCPM_Track_Invites::for_student( 0 ) ),
	array( 'https://learn.wordpress.org/?invite=base', 'https://learn.wordpress.org/?invite=base', '', '', '' )
);

echo "\n" . ( 0 === $failures ? 'All passed.' : "{$failures} failed." ) . "\n";
exit( 0 === $failures ? 0 : 1 );
