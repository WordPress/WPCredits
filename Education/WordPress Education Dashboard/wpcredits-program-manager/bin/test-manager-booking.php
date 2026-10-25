<?php
/**
 * The booking link on a program manager's own profile (1.111.0, design decision 5 of 23
 * September 2026).
 *
 * A sponsor's contact block took its "Book a call" link from the Team Members option the
 * sponsors sync used to write. The assigned manager is a site user now, so the link is a field
 * on that user's profile, and this suite pins it:
 *
 * - `url_for()` is '' for somebody with no link, so a contact block draws the name and the
 *   address and no button that goes nowhere.
 * - `set()` keeps an https address and nothing else, and '' clears it: the link is printed on
 *   the Sponsor Dashboard as something a sponsor presses, so `http://` and `javascript:` are
 *   refused, and a value that reached the meta some other way is read back as ''.
 * - The field is drawn on a program manager's profile, to the manager or to somebody who may
 *   edit them, and on nobody else's: a student has no sponsor to book a call with them.
 * - `save_field()` writes only when the profile form's own nonce passed, only on a manager's
 *   profile and only for an actor who may edit that user; a form without the field leaves the
 *   link alone.
 *
 * The stand-ins: `bin/stubs/caps.php` grants the program capability to the IDs in
 * `$GLOBALS['manage']` and `edit_user` to the IDs `$GLOBALS['grants']` names;
 * `bin/stubs/posts.php` gives `get_userdata()` over `$GLOBALS['users']` and the identity
 * slash pair; the nonce a form carries is `nonce-<action>`.
 *
 * Run from the plugin root:  php bin/test-manager-booking.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['users']   = array();
$GLOBALS['umeta']   = array();
$GLOBALS['manage']  = array( 7 );
$GLOBALS['grants']  = array( 9 => array( 'edit_user' ) );
$GLOBALS['uid']     = 0;
$GLOBALS['actions'] = array();

class WP_User {
	public $ID = 0;
	public $display_name = '';
	public $user_email = '';

	public function __construct( $id, $display_name = '', $user_email = '' ) {
		$this->ID           = (int) $id;
		$this->display_name = $display_name;
		$this->user_email   = $user_email;
	}
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_check_invalid_utf8( $text ) {
	return (string) $text;
}

/**
 * Core's rule, in the part this class leans on: a URL whose scheme is not on the list comes back
 * empty, and so does one with no scheme at all (core would prefix `http://`, which an https-only
 * list refuses the same way).
 *
 * @param string     $url       The URL.
 * @param array|null $protocols The schemes allowed.
 * @return string
 */
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );

	if ( '' === $url || false === strpos( $url, ':' ) ) {
		return '';
	}

	$scheme = strtolower( substr( $url, 0, (int) strpos( $url, ':' ) ) );

	return in_array( $scheme, null === $protocols ? array( 'http', 'https' ) : (array) $protocols, true ) ? $url : '';
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['actions'][] = array( $hook, $callback );
	return true;
}

function get_current_user_id() {
	return (int) $GLOBALS['uid'];
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return 'nonce-' . $action === $nonce ? 1 : false;
}

function get_user_meta( $id, $key = '', $single = false ) {
	return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : '';
}

function update_user_meta( $id, $key, $value ) {
	$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;
	return true;
}

function delete_user_meta( $id, $key ) {
	unset( $GLOBALS['umeta'][ (int) $id ][ $key ] );
	return true;
}

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-manager-booking.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * What render_field() prints for one profile, seen by one viewer.
 *
 * @param int $viewer  The signed-in user.
 * @param int $profile The profile drawn.
 * @return string
 */
function field_for( $viewer, $profile ) {
	$GLOBALS['uid'] = $viewer;

	ob_start();
	WPCPM_Manager_Booking::render_field( $GLOBALS['users'][ $profile ] );

	return (string) ob_get_clean();
}

/**
 * Post the profile form as one actor, for one profile, and say what save_field() answered.
 *
 * @param int         $actor   The signed-in user.
 * @param int         $profile The profile saved.
 * @param mixed       $link    The posted field, or null for a form without it.
 * @param string      $nonce   The nonce the form carried; the profile's own when ''.
 * @return bool
 */
function save_as( $actor, $profile, $link, $nonce = '' ) {
	$GLOBALS['uid'] = $actor;
	$_POST          = array( '_wpnonce' => '' !== $nonce ? $nonce : 'nonce-update-user_' . $profile );

	if ( null !== $link ) {
		$_POST[ WPCPM_Manager_Booking::META ] = $link;
	}

	$saved = WPCPM_Manager_Booking::save_field( $profile );
	$_POST = array();

	return $saved;
}

// Seven is a program manager; eight a student; nine may edit other users; ten may not.
$GLOBALS['users'][7]  = new WP_User( 7, 'Manager Seven', 'administrator@example.test' );
$GLOBALS['users'][8]  = new WP_User( 8, 'Student Eight', 'administrator+student@example.test' );
$GLOBALS['users'][9]  = new WP_User( 9, 'Editor Nine', 'administrator+editor@example.test' );
$GLOBALS['users'][10] = new WP_User( 10, 'Stranger Ten', 'administrator+stranger@example.test' );

echo "=== The link ===\n";

ck( 'url_for() is empty by default',
	array( WPCPM_Manager_Booking::url_for( 7 ), WPCPM_Manager_Booking::url_for( 0 ), WPCPM_Manager_Booking::url_for( 404 ) ),
	array( '', '', '' )
);

$kept    = WPCPM_Manager_Booking::set( 7, '  https://calendly.example/seven  ' );
$read    = WPCPM_Manager_Booking::url_for( 7 );
$cleared = WPCPM_Manager_Booking::set( 7, '' );

ck( 'set() keeps an https address, trimmed, and an empty one clears it',
	array( $kept, $read, $cleared, WPCPM_Manager_Booking::url_for( 7 ), isset( $GLOBALS['umeta'][7][ WPCPM_Manager_Booking::META ] ) ),
	array( true, 'https://calendly.example/seven', true, '', false )
);

WPCPM_Manager_Booking::set( 7, 'https://calendly.example/seven' );

ck( 'set() refuses http:// and javascript:, an address with no scheme or no host, and an account that does not exist, and keeps the link already there',
	array(
		WPCPM_Manager_Booking::set( 7, 'http://calendly.example/seven' ),
		WPCPM_Manager_Booking::set( 7, 'javascript:alert(1)' ),
		WPCPM_Manager_Booking::set( 7, 'calendly.example/seven' ),
		WPCPM_Manager_Booking::set( 7, 'https://' ),
		WPCPM_Manager_Booking::set( 404, 'https://calendly.example/nobody' ),
		WPCPM_Manager_Booking::url_for( 7 ),
		isset( $GLOBALS['umeta'][404] ),
	),
	array( false, false, false, false, false, 'https://calendly.example/seven', false )
);

$GLOBALS['umeta'][8][ WPCPM_Manager_Booking::META ] = 'javascript:alert(1)';

ck( 'url_for() reads a value that reached the meta some other way as no link unless it is https',
	WPCPM_Manager_Booking::url_for( 8 ),
	''
);

unset( $GLOBALS['umeta'][8] );

echo "\n=== The field ===\n";

WPCPM_Manager_Booking::init();

ck( 'init() hooks the field onto both profile screens and the save onto both profile updates',
	$GLOBALS['actions'],
	array(
		array( 'show_user_profile', array( 'WPCPM_Manager_Booking', 'render_field' ) ),
		array( 'edit_user_profile', array( 'WPCPM_Manager_Booking', 'render_field' ) ),
		array( 'personal_options_update', array( 'WPCPM_Manager_Booking', 'save_field' ) ),
		array( 'edit_user_profile_update', array( 'WPCPM_Manager_Booking', 'save_field' ) ),
	)
);

$own     = field_for( 7, 7 );
$student = field_for( 8, 8 );

ck( 'the field is drawn for a manager and not for a student',
	array(
		false !== strpos( $own, '<table class="form-table"' ),
		false !== strpos( $own, '<th><label for="wpcpm_booking_url">Booking link</label></th>' ),
		1 === preg_match( '#<input type="url" name="wpcpm_booking_url" id="wpcpm_booking_url" value="https://calendly\.example/seven"#', $own ),
		false !== strpos( $own, '<p class="description" id="wpcpm_booking_url-description">The link a sponsor presses to book a call with you. An https address.</p></td>' ),
		$student,
	),
	array( true, true, true, true, '' )
);

ck( 'the field is drawn to somebody who may edit the manager, and to nobody else',
	array( '' !== field_for( 9, 7 ), field_for( 10, 7 ) ),
	array( true, '' )
);

echo "\n=== The save ===\n";

ck( 'save_field() refuses an actor who may not edit the user',
	array( save_as( 10, 7, 'https://calendly.example/stranger' ), WPCPM_Manager_Booking::url_for( 7 ) ),
	array( false, 'https://calendly.example/seven' )
);

$by_self   = save_as( 7, 7, 'https://calendly.example/seven-new' );
$self_link = WPCPM_Manager_Booking::url_for( 7 );
$by_editor = save_as( 9, 7, 'https://calendly.example/seven-by-nine' );

ck( 'save_field() saves for the manager themself and for somebody who may edit them',
	array( $by_self, $self_link, $by_editor, WPCPM_Manager_Booking::url_for( 7 ) ),
	array( true, 'https://calendly.example/seven-new', true, 'https://calendly.example/seven-by-nine' )
);

ck( 'save_field() writes nothing without the profile form\'s own nonce, from a form without the field or with a list in it, and a refused address leaves the link as it was',
	array(
		save_as( 7, 7, 'https://calendly.example/forged', 'nonce-update-user_9' ),
		save_as( 7, 7, null ),
		save_as( 7, 7, array( 'https://calendly.example/listed' ) ),
		save_as( 7, 7, 'http://calendly.example/seven' ),
		WPCPM_Manager_Booking::url_for( 7 ),
	),
	array( false, false, false, false, 'https://calendly.example/seven-by-nine' )
);

ck( 'save_field() writes nothing on a profile that is not a program manager\'s',
	array( save_as( 8, 8, 'https://calendly.example/student' ), isset( $GLOBALS['umeta'][8] ) ),
	array( false, false )
);

ck( 'an empty field clears the link',
	array( save_as( 7, 7, '' ), WPCPM_Manager_Booking::url_for( 7 ) ),
	array( true, '' )
);

echo "\n=== Wiring and house rules ===\n";

$module_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$boot       = substr( $module_src, (int) strpos( $module_src, 'public function boot()' ) );
$boot       = substr( $boot, 0, (int) strpos( $boot, "\n\t}\n" ) );

ck( 'the Sponsors module hooks the field on every load', substr_count( $boot, 'WPCPM_Manager_Booking::init();' ), 1 );

$class_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-manager-booking.php' );

ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $class_src ), 0 );

printf( "\n%s (%d checks)\n", $fails ? "$fails FAILED" : 'ALL PASS', (int) $total );
exit( $fails ? 1 : 0 );
