<?php
/**
 * The outcome a dashboard handler leaves is printed inside the card it goes back to (SDB-01, SDB-02,
 * 1.123.58).
 *
 * A handler names the card (`WPCPM_Dashboard_Card::target()`), the page holds the outcome for it
 * (`hold()`), the card prints it at the top of its body and is drawn open (`open()`), and the slot
 * where the page's outcome used to go is filled only when no card printed it (`settle()`): a flash
 * naming a card the page does not draw is still said, at the top. Every outcome carries its tone, the
 * role its tone is heard with, and the mark assets/js/forms.js focuses.
 *
 * Run from the plugin root:  php bin/test-card-message.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['umeta'] = array();
$GLOBALS['uid']   = 7;

function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return (string) $url; }
function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = wp_unslash( $v ); return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

require_once dirname( __DIR__ ) . '/includes/class-wpcpm-flash.php';
require_once dirname( __DIR__ ) . '/includes/class-wpcpm-dashboard-card.php';

$fail = 0;
function ck( $l, $a, $e ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $l . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $e, true ) . '  got: ' . var_export( $a, true ) . "\n"; }
}

/**
 * A page as the two dashboards draw one: the slot, then each card, then settle().
 *
 * @param string[] $keys The cards drawn, by key.
 * @param string   $prefix The anchors' prefix.
 * @return string
 */
function page_of( array $keys, $prefix = 'wpcpm-sponsor-' ) {
	ob_start();
	echo '<div class="page">' . WPCPM_Dashboard_Card::SLOT;

	foreach ( $keys as $key ) {
		WPCPM_Dashboard_Card::open( array( 'key' => $key, 'title' => ucfirst( $key ), 'prefix' => $prefix ) );
		echo '<p>body of ' . $key . '</p>';
		WPCPM_Dashboard_Card::close();
	}

	echo '</div>';

	return WPCPM_Dashboard_Card::settle( (string) ob_get_clean() );
}

ck( 'a success is heard as a status, focusable, marked for the script, in the message classes with its tone',
	WPCPM_Dashboard_Card::message( 'success', 'Your profile was saved.' ),
	'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Your profile was saved.</p>' );
ck( 'an error is heard as an alert', false !== strpos( WPCPM_Dashboard_Card::message( 'error', 'The offer could not be saved.' ), 'role="alert"' ), true );
ck( 'an unknown tone is info, heard as a status', false !== strpos( WPCPM_Dashboard_Card::message( 'loud', 'x' ), 'wpcpm-dashboard__message--info is-info" role="status"' ), true );
ck( 'the sentence is escaped', false !== strpos( WPCPM_Dashboard_Card::message( 'info', 'A <b>"bold"</b> & claim' ), '>A &lt;b&gt;&quot;bold&quot;&lt;/b&gt; &amp; claim</p>' ), true );
ck( 'another part\'s own message class takes the same markup', WPCPM_Dashboard_Card::message( 'error', 'That file is not a PDF.', 'wpcpm-agreement-panel__message' ), '<p class="wpcpm-agreement-panel__message wpcpm-agreement-panel__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>That file is not a PDF.</p>' );

WPCPM_Dashboard_Card::target( 'wpcpm-sponsor-offers' );
ck( 'a handler names the card it goes back to, taken once', array( WPCPM_Dashboard_Card::targeted(), WPCPM_Flash::take( WPCPM_Dashboard_Card::TARGET ) ), array( 'wpcpm-sponsor-offers', 'wpcpm-sponsor-offers' ) );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-offers', 'success', 'Paused: Cloud credits is hidden until you resume it.' );
$page = page_of( array( 'profile', 'offers', 'tools' ) );
ck( 'the outcome is printed at the top of the card it names, and only there',
	array( substr_count( $page, 'data-wpcpm-card-message' ), strpos( $page, 'data-wpcpm-card-message' ) > strpos( $page, 'id="wpcpm-sponsor-offers"' ), strpos( $page, 'data-wpcpm-card-message' ) < strpos( $page, 'body of offers' ), strpos( $page, 'data-wpcpm-card-message' ) > strpos( $page, 'body of profile' ) ),
	array( 1, true, true, true ) );
ck( 'that card is drawn open, the others as they asked', array( 1 === preg_match( '#<details id="wpcpm-sponsor-offers" class="[^"]*" open>#', $page ), 1 === preg_match( '#<details id="wpcpm-sponsor-profile" class="[^"]*">#', $page ) ), array( true, true ) );
ck( 'and the slot at the top is cleared', strpos( $page, WPCPM_Dashboard_Card::SLOT ), false );

WPCPM_Dashboard_Card::hold( 'wpcpm-reports', 'error', 'That report was published since you opened the page.' );
$admin = page_of( array( 'reports' ), 'wpcpm-' );
ck( 'the Administrator Dashboard\'s anchors are held to the same rule', array( substr_count( $admin, 'role="alert"' ), strpos( $admin, 'role="alert"' ) > strpos( $admin, 'id="wpcpm-reports"' ) ), array( 1, true ) );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-retired', 'error', 'The offer could not be saved.' );
$missing = page_of( array( 'profile', 'offers' ) );
ck( 'a flash naming a card the page does not draw is said at the top, where the slot was, and no card is opened for it',
	array( strpos( $missing, '<div class="page"><p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>The offer could not be saved.</p>' ), substr_count( $missing, ' open>' ) ),
	array( 0, 0 ) );

WPCPM_Dashboard_Card::hold( '', 'info', 'Sync started.' );
ck( 'an outcome with no card is said at the top', 0 === strpos( page_of( array( 'profile' ) ), '<div class="page"><p class="wpcpm-dashboard__message wpcpm-dashboard__message--info' ), true );

ck( 'with nothing held, the slot is cleared and nothing is said', array( substr_count( page_of( array( 'profile' ) ), 'data-wpcpm-card-message' ), strpos( page_of( array( 'profile' ) ), '<!--' ) ), array( 0, false ) );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-profile', 'success', '   ' );
ck( 'a blank sentence holds nothing', substr_count( page_of( array( 'profile' ) ), 'data-wpcpm-card-message' ), 0 );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-profile', 'success', 'Saved.' );
page_of( array( 'profile' ) );
ck( 'an outcome is said once: a second render in the same request says nothing', substr_count( page_of( array( 'profile' ) ), 'data-wpcpm-card-message' ), 0 );

ck( 'a page with no slot is handed back as it was', WPCPM_Dashboard_Card::settle( '<p>no slot</p>' ), '<p>no slot</p>' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
