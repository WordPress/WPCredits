<?php
/**
 * The one field for a long list, drawn on its own (`WPCPM_Dashboards::render_combo()`).
 *
 * The Viewing as switcher draws it inside its form (bin/test-dashboard-switcher.php holds that
 * markup, byte for byte, and runs assets/js/switcher.js on it); the Emails tool's Log draws it as its
 * Email filter, in a form of its own. What each block pins, and why:
 *
 * - **The label is the field's.** switcher.js finds its parts through their ARIA: the field names
 *   its list, the list names the label, the label names the select. A form that drew its own label
 *   without the `-label` id would leave the list naming nothing, and the script would leave the
 *   select where it is. So the label's id is the list's `aria-labelledby`, its `for` the select's
 *   id, and the field's `aria-controls` the list's id.
 * - **The options are drawn as given.** The caller sorts them: the switcher A to Z, the Log A to Z
 *   after its "Every email". The chosen one is selected, and only it.
 * - **The count's sentence is the caller's**, with the switcher's "Names in the list: %s" when none
 *   is given.
 * - **No form, no Show and no note**: those are the switcher's. The script is registered and
 *   enqueued by the field, so a page that draws one loads it.
 *
 * Run from the plugin root:  php bin/test-combo-field.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']       = array();
$GLOBALS['registered'] = array();
$GLOBALS['enqueued']   = array();

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
// Core's, as `selected()` writes it: a string comparison, so 7 and '7' are one value.
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['registered'][ $h ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $footer );
}
function wp_script_is( $h, $list = 'enqueued' ) {
	return 'registered' === $list ? isset( $GLOBALS['registered'][ $h ] ) : in_array( $h, $GLOBALS['enqueued'], true );
}
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function remove_accents( $text, $locale = '' ) { return (string) $text; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';

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
 * Draw one field and hand back its markup.
 *
 * @param array $more Arguments to add, or to put in place of the ones below.
 * @return string
 */
function combo( array $more = array() ) {
	ob_start();

	WPCPM_Dashboards::render_combo(
		array_merge(
			array(
				'id'      => 'wpcpm-test-email',
				'name'    => 'email',
				'options' => array( '' => 'Every email', 'call-booked' => 'Call booked', 'call-cancelled' => 'Call canceled', 'other' => 'Other email' ),
				'current' => 'call-booked',
				'label'   => 'Email',
				'find'    => 'Type to find an email',
				'none'    => 'No email has that name.',
				'count'   => 'Emails in the list: %s',
			),
			$more
		)
	);

	return (string) ob_get_clean();
}

ck( 'WPCPM_Dashboards draws the field on its own', method_exists( 'WPCPM_Dashboards', 'render_combo' ), true );

$html = combo();

preg_match( '#<label for="([^"]*)" id="([^"]*)">([^<]*)</label>#', $html, $label );
preg_match( '#<select name="([^"]*)" id="([^"]*)" autocomplete="off">#', $html, $select );
preg_match( '#<input type="text" id="([^"]*)"[^>]*aria-controls="([^"]*)"#', $html, $field );
preg_match( '#<ul id="([^"]*)" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="([^"]*)" hidden></ul>#', $html, $list );

ck( 'the field draws its label, with the id its list is labeled by', array( $label[1] ?? null, $label[2] ?? null, $label[3] ?? null ), array( 'wpcpm-test-email', 'wpcpm-test-email-label', 'Email' ) );
ck( 'the three links switcher.js follows hold: the field names its list, the list names the label, the label names the select', array( ( $field[2] ?? 'a' ) === ( $list[1] ?? 'b' ), ( $list[2] ?? 'a' ) === ( $label[2] ?? 'b' ), ( $label[1] ?? 'a' ) === ( $select[2] ?? 'b' ) ), array( true, true, true ) );
ck( 'the select posts the name given', $select[1] ?? null, 'email' );

preg_match_all( '#<option value="([^"]*)"( selected=\'selected\')?>([^<]*)</option>#', $html, $options, PREG_SET_ORDER );
ck( 'the options are drawn in the order given, and the chosen one alone is selected', array_map( function ( $o ) { return $o[1] . ( '' !== $o[2] ? '*' : '' ) . '=' . $o[3]; }, $options ), array( '=Every email', 'call-booked*=Call booked', 'call-cancelled=Call canceled', 'other=Other email' ) );
ck( 'the field and its parts are drawn hidden, after the select', array( false !== strpos( $html, '<div class="wpcpm-dashboard__switcher-combo" hidden>' ), strpos( $html, '</select>' ) < strpos( $html, 'wpcpm-dashboard__switcher-combo' ) ), array( true, true ) );
ck( 'the status line carries the caller\'s count and no-match sentences', false !== strpos( $html, '<span class="wpcpm-dashboard__switcher-status" role="status" data-wpcpm-count="Emails in the list: %s" data-wpcpm-none="No email has that name."></span>' ), true );
ck( 'and with no count given, the switcher\'s', false !== strpos( combo( array( 'count' => '' ) ), 'data-wpcpm-count="Names in the list: %s"' ), true );
ck( 'no form, no Show and no note: those are the switcher\'s', array( strpos( $html, '<form' ), strpos( $html, '<button' ), strpos( $html, 'switcher-note' ) ), array( false, false, false ) );
ck( 'the field loads the script that works it, registered once in the footer', array( $GLOBALS['enqueued'][0] ?? null, $GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ]['src'] ?? null, $GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ]['footer'] ?? null ), array( WPCPM_Dashboards::SWITCHER_SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/switcher.js', true ) );

// The switcher draws its fields through this one, so the two cannot drift apart.
$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php' );
$body   = preg_match( '/function render_switcher\(.*?\n\t}\n/s', $source, $found ) ? $found[0] : '';
ck( 'render_switcher() draws its label, list and field through render_combo(), and prints none of them itself', array( false !== strpos( $body, 'self::render_combo(' ), strpos( $body, '<select' ), strpos( $body, '<label' ), strpos( $body, 'role="combobox"' ) ), array( true, false, false, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
