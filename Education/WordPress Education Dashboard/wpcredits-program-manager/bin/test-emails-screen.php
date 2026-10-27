<?php
/**
 * The Emails tool and its Log screen.
 *
 * The screen is drawn through the real tool, the real list table over bin/stubs/class-wp-list-table.php,
 * the real store over an in-memory SQLite database (bin/stubs/sqlite-wpdb.php), and the real Email
 * field (`WPCPM_Dashboards::render_combo()`). What each block pins, and why:
 *
 * - **The tool draws and counts, and boots nothing else**: the recording, the upgrade, the cleanup
 *   and the privacy hooks are the store's, booted from the plugin's bootstrap, so filtering the tool
 *   out of `wpcpm_tools` cannot stop the log.
 * - **When is counted in the site's time zone**: Today starts at the site's midnight, not UTC's, and
 *   a From and To date take whole local days, so a row sent just before UTC's midnight is on the
 *   right day.
 * - **The filters are a GET form of their own**, closed before the table, with the search as its
 *   own field: no nonce or referer joins a filter's address, and the box is there with no row.
 *   Area, Recipient type, Status and When are plain selects; Email is the one field that drops down,
 *   takes typing and narrows, and its label is the one its list is labeled by, which is how
 *   assets/js/switcher.js finds it (the three links the script follows to wire the field up in wp-admin).
 *   The script itself is run by node (bin/js/emails-field.js) on the form as it is drawn: the select
 *   hides, the field shows, and typing a few letters narrows the list. Skipped, with a note, where
 *   node is not on the path, as bin/test-dashboard-switcher.php skips its own.
 * - **No screen word says module**: the column and the filter say Area.
 * - **No table**: the screen says the log is not ready, and the status line says so.
 *
 * Run from the plugin root:  php bin/test-emails-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']       = array();
$GLOBALS['hooked']     = array();
$GLOBALS['styles']     = array();
$GLOBALS['registered'] = array();
$GLOBALS['enqueued']   = array();
$GLOBALS['can']        = true;
$GLOBALS['users']      = array();
$GLOBALS['cached']     = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $e ) { $e = trim( (string) $e ); return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : ''; }
function wp_unslash( $v ) { return $v; }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Core's two forms: `add_query_arg( $key, $value, $url )`, the Log's own links, and
// `add_query_arg( $args, $url )`, the tab bar's.
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$url = (string) $args[1];

		foreach ( $args[0] as $k => $v ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
		}

		return $url;
	}

	return $args[2] . ( false === strpos( $args[2], '?' ) ? '?' : '&' ) . $args[0] . '=' . $args[1];
}
// The flash the screen reads a press's notice from: one Administrator's, in user meta.
$GLOBALS['umeta'] = array();
function get_current_user_id() { return 1; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ $id ][ $k ] ); return true; }
function wp_slash( $v ) { return $v; }
// Core's, as `selected()` writes it: a string comparison, so 7 and '7' are one value.
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = array( $h, $cb, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function apply_filters( $h, $v, ...$rest ) { return $v; }
function wp_timezone() { $tz = (string) get_option( 'timezone_string', '' ); return new DateTimeZone( '' !== $tz ? $tz : 'UTC' ); }
function wp_date( $format, $timestamp = null ) { return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() )->format( $format ); }
function current_user_can( $cap ) { return (bool) $GLOBALS['can']; }
function wp_die( $m = '', $t = '', $a = array() ) { throw new Exception( 'wp_die: ' . $m ); }
function get_user_by( $field, $value ) { return $GLOBALS['users'][ (int) $value ] ?? false; }
// Core's: one query for the accounts a page names, so that get_user_by() finds each in the cache.
function cache_users( $ids ) { $GLOBALS['cached'][] = $ids; }
function get_edit_user_link( $id ) { return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id; }
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) { $GLOBALS['styles'][ $h ] = $src; }
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['registered'][ $h ] = $src; }
function wp_script_is( $h, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['registered'][ $h ] ) : in_array( $h, $GLOBALS['enqueued'], true ); }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) { echo '<input type="hidden" id="' . $name . '" name="' . $name . '" value="nonce:' . $action . '" />'; }
function remove_accents( $text, $locale = '' ) { return (string) $text; }

class WP_User {
	public $ID, $display_name, $user_login, $user_email, $roles, $caps;
	public function __construct( $id, $name, $login = '', $roles = array(), $caps = array(), $email = '' ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_login   = $login ?: strtolower( explode( ' ', $name )[0] );
		$this->user_email   = $email ?: $this->user_login . '@example.test';
		$this->roles        = $roles;
		$this->caps         = $caps;
	}
}
// Core's, as far as `WPCPM_Mail_Capture::type_of()` asks it: whether the account was given the capability.
function user_can( $user, $cap, ...$args ) { return in_array( $cap, (array) $user->caps, true ); }
class WPCPM_Roles {
	const CAP_MANAGE       = 'wpcpm_manage_program';
	const ROLE_STUDENT     = 'wpcpm_student';
	const ROLE_MENTOR      = 'wpcpm_mentor';
	const ROLE_INSTITUTION = 'wpcpm_institution';
	const ROLE_SPONSOR     = 'wpcpm_sponsor';
	const ROLE_ADMIN       = 'administrator';
}

require __DIR__ . '/stubs/class-wp-list-table.php';
require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['wpdb']->create_mail_log();

require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
require WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';

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

/** The screen as an Administrator opening it with these query arguments sees it. */
function screen( array $query ) {
	$_GET                   = $query;
	$_REQUEST               = $query;
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( $query );

	ob_start();
	( new WPCPM_Emails() )->render_admin_page();

	return (string) ob_get_clean();
}

/**
 * The link the Log gives a person: their row on one screen's Accounts tab, found by the account's email,
 * encoded whole, since an address can hold a plus and add_query_arg() sets a value as it is given.
 */
function accounts_link( $screen, $email, $name ) {
	return '<a href="https://example.test/wp-admin/admin.php?page=wpcpm-' . $screen . '&tab=accounts&s=' . rawurlencode( $email ) . '#wpcpm-accounts-list">' . $name . '</a>';
}

/** The words some markup says, as a person reads them. */
function words_of( $html ) {
	return trim( preg_replace( '/\s+/', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $html ), ENT_QUOTES ) ) );
}

// The tool.
$tool = new WPCPM_Emails();
ck( 'the tool is Emails at wpcpm-tool-emails, always ready', array( $tool->id(), $tool->label(), $tool->page_slug(), $tool->is_ready() ), array( 'emails', 'Emails', 'wpcpm-tool-emails', true ) );

$tool->boot();
ck( 'it boots its stylesheet\'s hook and the editor\'s two forms, and nothing else: the log boots from the plugin\'s bootstrap', array_map( function ( $h ) { return $h[0]; }, $GLOBALS['hooked'] ), array( 'admin_enqueue_scripts', 'admin_post_wpcpm_email_save', 'admin_post_wpcpm_email_restore' ) );
$tool->enqueue_assets( 'index.php' );
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-emails' );
ck( 'its stylesheet loads on its screen alone', $GLOBALS['styles'], array( 'wpcpm-emails' => WPCPM_PLUGIN_URL . 'assets/css/emails.css' ) );

// The status line the Tools screen and the Overview print.
ck( 'the status line counts the last 30 days\' emails', $tool->status_line(), '0 emails in the last 30 days.' );
WPCPM_Mail_Log::add( array( 'sent_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ), 'to_email' => 'ben@example.test', 'module' => 'calls', 'template' => 'call-reminder', 'subject' => 'Reminder', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ), 'to_email' => 'ben@example.test', 'module' => 'calls', 'template' => 'call-reminder', 'subject' => 'Old reminder', 'status' => 'sent' ) );
ck( 'one, in the singular, and none older than 30 days', $tool->status_line(), '1 email in the last 30 days.' );

$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
ck( 'and with no table it says the log is not ready yet', $tool->status_line(), 'The email log is not ready yet.' );
$GLOBALS['wpdb']->create_mail_log();
WPCPM_Mail_Log::reset();

// The When choices in the site's time zone: Today from the site's midnight, whole local days for From and To.
$GLOBALS['opts']['timezone_string'] = 'Europe/Warsaw';
$now = strtotime( '2026-10-10 08:00:00 UTC' ); // 10:00 in Warsaw.
ck( 'Today runs from local midnight, in UTC', WPCPM_Emails::range( 'today', '', '', $now ), array( 'from' => '2026-10-09 22:00:00', 'to' => '' ) );
ck( 'the last 7 days count back from now', WPCPM_Emails::range( '7', '', '', $now ), array( 'from' => '2026-10-03 08:00:00', 'to' => '' ) );
ck( 'the last 30 days count back from now', WPCPM_Emails::range( '30', '', '', $now ), array( 'from' => '2026-09-10 08:00:00', 'to' => '' ) );
ck( 'from and to dates take whole local days', WPCPM_Emails::range( 'range', '2026-10-01', '2026-10-02', $now ), array( 'from' => '2026-09-30 22:00:00', 'to' => '2026-10-02 22:00:00' ) );
ck( 'a date that is not one is ignored', WPCPM_Emails::range( 'range', 'yesterday', '2026-13-40', $now ), array( 'from' => '', 'to' => '' ) );
ck( 'and no choice is the 30 days the log keeps', WPCPM_Emails::range( '', '', '', $now ), array( 'from' => '', 'to' => '' ) );
// A row at 23:30 UTC on 9 October is 01:30 on 10 October in Warsaw: Today holds it.
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-09 23:30:00', 'to_email' => 'ada@example.test', 'user_id' => 7, 'to_name' => 'Ada Lin', 'recipient_type' => 'student', 'module' => 'calls', 'template' => 'call-booked', 'subject' => 'Call booked', 'status' => 'sent' ) );
ck( 'and Today holds a row sent at 23:30 UTC the day before', WPCPM_Mail_Log::find( WPCPM_Emails::range( 'today', '', '', $now ) )['total'], 1 );

// The filters, from the request.
$_GET = array( 's' => ' ada ', 'module' => 'calls', 'type' => 'student', 'status' => 'test', 'email' => 'call-booked', 'when' => 'range', 'from' => '2026-10-01', 'to' => '2026-10-02' );
ck( 'filters_from_request() reads every filter as find() reads it', WPCPM_Emails::filters_from_request(), array( 'search' => 'ada', 'module' => 'calls', 'recipient_type' => 'student', 'status' => 'test', 'template' => 'call-booked', 'from' => '2026-09-30 22:00:00', 'to' => '2026-10-02 22:00:00' ) );

// Dates given with When left empty apply on their own: the request reads When as From and to dates.
$_GET = array( 'from' => '2026-10-01', 'to' => '2026-10-02' );
ck( 'dates given with When empty are read as From and to dates', array( WPCPM_Emails::filters_from_request()['from'], WPCPM_Emails::filters_from_request()['to'] ), array( '2026-09-30 22:00:00', '2026-10-02 22:00:00' ) );
$_GET = array( 'to' => '2026-10-02' );
ck( 'and so is one date alone', array( WPCPM_Emails::filters_from_request()['from'], WPCPM_Emails::filters_from_request()['to'] ), array( '', '2026-10-02 22:00:00' ) );
$_GET = array( 'from' => 'yesterday', 'to' => '2026-13-40' );
ck( 'but dates that are not dates leave the list at any time', array( WPCPM_Emails::filters_from_request()['from'], WPCPM_Emails::filters_from_request()['to'] ), array( '', '' ) );
$_GET = array( 'when' => 'today', 'from' => '2026-10-01', 'to' => '2026-10-02' );
ck( 'and a When that was chosen is never overridden by dates', array( WPCPM_Emails::filters_from_request()['from'], WPCPM_Emails::filters_from_request()['to'] ), array( WPCPM_Emails::range( 'today' )['from'], '' ) );

// The screen. The cleanup ran a moment ago, so the screen does not run it over the rows above.
$GLOBALS['opts'][ WPCPM_Mail_Log::OPT_PURGED ] = time();
$GLOBALS['users'][7]                           = new WP_User( 7, 'Ada Lin', '', array( WPCPM_Roles::ROLE_STUDENT ) );

$html  = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'when' => 'range', 'from' => '2026-10-10', 'to' => '2026-10-10' ) );
$form  = preg_match( '#<form method="get" class="wpcpm-emails__filters">(.*?)</form>#s', $html, $found ) ? $found[1] : '';
$words = words_of( $html );

ck( 'the Log is the tab shown, between Templates and Signature and footer and its own link', array( 1 === preg_match( '#<a href="https://example.test/wp-admin/admin.php\?page=wpcpm-tool-emails&tab=log" class="nav-tab nav-tab-active" aria-current="page">Log</a>#', $html ), 1 === preg_match( '#>Templates</a><a [^>]*>Signature and footer</a><a [^>]*>Log</a></nav>#', $html ) ), array( true, true ) );
ck( 'the row shows its time in the site\'s zone, its address, its person linked to their accounts screen where Resend invite lives, its area and its email', array( false !== strpos( $html, '10 Oct 2026, 01:30' ), false !== strpos( $html, 'ada@example.test' ), false !== strpos( $html, accounts_link( 'students', 'ada@example.test', 'Ada Lin' ) ), false !== strpos( $html, 'Mentor calls' ), false !== strpos( $html, 'Call booked' ) ), array( true, true, true, true, true ) );
ck( 'the count is shown', false !== strpos( $html, '1 email matches.' ), true );
ck( 'the screen says, under the table, what Handed to the mail server means', strpos( $html, 'means the site passed the email on, not that it reached the inbox' ) > strpos( $html, '</table>' ), true );
ck( 'the filters are a GET form of their own, closed before the table, naming the screen and the Log tab', array( '' !== $form, strpos( $html, '</form>' ) < strpos( $html, '<table' ), false !== strpos( $form, '<input type="hidden" name="page" value="wpcpm-tool-emails" />' ), false !== strpos( $form, '<input type="hidden" name="tab" value="log" />' ) ), array( true, true, true, true ) );
ck( 'with its own search box, and no nonce or referer of the table\'s', array( 1 === preg_match( '#<input type="search" id="wpcpm-emails-search" name="s" value="" />#', $form ), false !== strpos( $form, '_wpnonce' ), false !== strpos( $form, '_wp_http_referer' ), false !== strpos( $html, 'search-submit' ) ), array( true, false, false, false ) );
ck( 'Area, Recipient type, Status and When are plain selects', array( 1 === preg_match( '#<label for="wpcpm-emails-module">Area</label> <select id="wpcpm-emails-module" name="module"><option value="">Every area</option>#', $form ), 1 === preg_match( '#<select id="wpcpm-emails-type" name="type"><option value="">All</option>#', $form ), 1 === preg_match( '#<select id="wpcpm-emails-status" name="status"><option value="">All</option>#', $form ), 1 === preg_match( '#<select id="wpcpm-emails-when" name="when"><option value="">Any time</option>#', $form ) ), array( true, true, true, true ) );
ck( 'and the dates chosen are put back', array( false !== strpos( $form, '<option value="range" selected=\'selected\'>From and to dates</option>' ), false !== strpos( $form, 'name="from" value="2026-10-10"' ) ), array( true, true ) );

// The Email field wires up, which assets/js/switcher.js does through its ARIA.
preg_match( '#<label for="([^"]*)" id="([^"]*)">Email</label> <select name="email" id="([^"]*)" autocomplete="off">#', $form, $email_label );
preg_match( '#aria-controls="([^"]*)"#', $form, $controls );
preg_match( '#<ul id="([^"]*)" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="([^"]*)" hidden></ul>#', $form, $listbox );
ck( 'Email is the one field that drops down and takes typing, inside the form', array( isset( $email_label[3] ), false !== strpos( $form, '<div class="wpcpm-dashboard__switcher-combo" hidden>' ), false !== strpos( $form, 'role="combobox"' ) ), array( true, true, true ) );
ck( 'its label\'s id is the listbox\'s aria-labelledby, its label names the select, and the field names the listbox', array( ( $email_label[2] ?? 'a' ) === ( $listbox[2] ?? 'b' ), ( $email_label[1] ?? 'a' ) === ( $email_label[3] ?? 'b' ), ( $controls[1] ?? 'a' ) === ( $listbox[1] ?? 'b' ) ), array( true, true, true ) );
ck( 'and the page loads the script that works it', in_array( WPCPM_Dashboards::SWITCHER_SCRIPT, $GLOBALS['enqueued'], true ), true );

preg_match( '#<select name="email"[^>]*>(.*?)</select>#s', $form, $email_select );
preg_match_all( '#<option value="([^"]*)"[^>]*>([^<]*)</option>#', $email_select[1] ?? '', $email_options );
$labels = array_slice( array_map( 'html_entity_decode', $email_options[2] ), 1 );
$sorted = $labels;
usort( $sorted, array( 'WPCPM_Dashboards', 'compare_names' ) );
ck( 'its list opens on Every email, then every email the catalog names and Other email, A to Z', array( $email_options[2][0] ?? '', $email_options[1][0] ?? 'x', count( $labels ), $labels === $sorted, in_array( 'Other email', $labels, true ) ), array( 'Every email', '', count( WPCPM_Mail_Catalog::emails() ) + 1, true, true ) );
ck( 'and its count sentence is the Log\'s own', false !== strpos( $form, 'data-wpcpm-count="Emails in the list: %s" data-wpcpm-none="No email has that name."' ), true );

// The field's rules are wp-admin's since 1.123.29: admin.css, which every screen of the plugin loads,
// holds them under the wrapper each such field is drawn in, here and on the Institutions screen, so
// emails.css keeps none of them and no rule dresses the class the front end's stylesheet owns bare.
$selectors_of = function ( $file ) {
	preg_match_all( '/([^{}@]+)\{[^{}]*\}/', (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/' . $file ) ), $rules );
	$out = array();
	foreach ( $rules[1] as $list ) {
		foreach ( explode( ',', $list ) as $selector ) {
			$selector = trim( preg_replace( '/\s+/', ' ', $selector ) );
			if ( false !== strpos( $selector, 'switcher' ) || false !== strpos( $selector, 'select[hidden]' ) ) { $out[] = $selector; }
		}
	}
	return $out;
};
ck( 'the Email field\'s rules are in admin.css alone, every one under .wpcpm-admin-combo, and the Email filter is drawn inside that wrapper', array(
	$selectors_of( 'emails.css' ),
	$selectors_of( 'admin.css' ),
	false !== strpos( $form, '<div class="wpcpm-emails__filter wpcpm-emails__email wpcpm-admin-combo"><label for="wpcpm-emails-email"' ),
), array( array(), array(
	'.wpcpm-admin-combo select[hidden]',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-combo[hidden]',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-list[hidden]',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-combo',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-input',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-list',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-option',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-empty',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-option',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-option:hover',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-option.is-active',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-option.is-current',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-empty',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-option.is-active',
	'.wpcpm-admin-combo .wpcpm-dashboard__switcher-status',
), true ) );

// The Email field, run by node on the form as drawn: the script wires it up, or the select stays what
// it was and the field stays hidden.
echo "\n=== The Email field, run by node ===\n";

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the Email field was not run: node is not on the path\n";
} else {
	$query    = 'call';
	$choices  = array_combine( array_map( 'html_entity_decode', $email_options[1] ), array_map( 'html_entity_decode', $email_options[2] ) );
	$narrowed = array_values(
		array_filter(
			$choices,
			function ( $label ) use ( $query ) {
				return false !== stripos( $label, $query );
			}
		)
	);

	$process = proc_open(
		escapeshellarg( $node ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'bin/js/emails-field.js' ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) . ' - 2>&1',
		array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ) ),
		$pipes
	);

	fwrite( $pipes[0], (string) json_encode( array( 'html' => $form, 'query' => $query ) ) );
	fclose( $pipes[0] );

	$ran = json_decode( (string) stream_get_contents( $pipes[1] ), true );

	fclose( $pipes[1] );
	proc_close( $process );

	$ran = is_array( $ran ) ? $ran : array( 'error' => 'node printed no answer' );

	ck( 'the script ran on the form as drawn, and it hides the select and shows the field in its place, the label now naming the field', array( $ran['error'], $ran['wired'] ?? null ), array( null, array( 'select_hidden' => true, 'combo_shown' => true, 'label_names_field' => true, 'field' => 'Every email', 'list_closed' => true, 'options' => count( $choices ) ) ) );
	ck( 'typing a few letters narrows the list to the emails whose names hold them, and says how many', $ran['typed'] ?? null, array( 'listed' => $narrowed, 'said' => 'Emails in the list: ' . count( $narrowed ) ) );
	ck( 'Enter picks the first of them, and the filter sends its ID', $ran['picked'] ?? null, array( 'prevented' => true, 'field' => $narrowed[0], 'select' => (string) array_search( $narrowed[0], $choices, true ), 'sent' => array( (string) array_search( $narrowed[0], $choices, true ) ), 'closed' => true ) );
	ck( 'and a form whose list names no label is left as it was, the select shown and the field hidden: the control for the three checks above', $ran['unwired'] ?? null, array( 'select_hidden' => false, 'combo_shown' => false ) );
}

ck( 'no word on the screen says module: the column and the filter say Area', array( preg_match_all( '/\bmodules?\b/i', $words ), 1 === preg_match( "#<th scope=\"col\" id='module' class='manage-column column-module'\s*>Area</th>#", $html ) ), array( 0, true ) );
ck( 'the body never reaches the screen, and no em or en dash', array( false === strpos( $html, 'BODY' ), 0 === preg_match( '/\x{2013}|\x{2014}/u', $html ) ), array( true, true ) );

// From and To dates with When left empty narrow the list, and the When select prints what is applied.
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-05 10:00:00', 'to_email' => 'earlier@example.test', 'module' => 'calls', 'template' => 'call-booked', 'subject' => 'Call booked', 'status' => 'sent' ) );
$dated = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'from' => '2026-10-10', 'to' => '2026-10-10' ) );
ck( 'dates given with When empty narrow the list to those days', array( false !== strpos( $dated, '1 email matches.' ), false !== strpos( $dated, 'ada@example.test' ), false !== strpos( $dated, 'earlier@example.test' ) ), array( true, true, false ) );
ck( 'and the When select shows From and to dates, so what is printed is what is applied', array( false !== strpos( $dated, '<option value="range" selected=\'selected\'>From and to dates</option>' ), false !== strpos( $dated, '<option value="">Any time</option>' ) ), array( true, true ) );
$other = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'from' => '2026-10-05', 'to' => '2026-10-05' ) );
ck( 'the other day alone finds the other row', array( false !== strpos( $other, 'earlier@example.test' ), false !== strpos( $other, 'ada@example.test' ) ), array( true, false ) );
$any = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log' ) );
ck( 'and with no dates and no When the list is any time, Any time chosen', array( false !== strpos( $any, '2 emails match.' ), false !== strpos( $any, 'selected=\'selected\'>From and to dates' ) ), array( true, false ) );

// The accounts a page names are primed in one call, not fetched one by one for each row.
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-09 12:00:00', 'to_email' => 'ben@example.test', 'user_id' => 8, 'to_name' => 'Ben Ode', 'recipient_type' => 'mentor', 'module' => 'calls', 'template' => 'call-booked', 'subject' => 'Call booked', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-08 12:00:00', 'to_email' => 'ada@example.test', 'user_id' => 7, 'to_name' => 'Ada Lin', 'recipient_type' => 'student', 'module' => 'calls', 'template' => 'call-booked', 'subject' => 'Call booked again', 'status' => 'sent' ) );
$GLOBALS['users'][8] = new WP_User( 8, 'Ben Ode', '', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['cached']   = array();
$calls_html = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'module' => 'calls' ) );
ck( 'the table primes the users its page names in one call: each id once, the rows that name nobody left out', $GLOBALS['cached'], array( array( 7, 8 ) ) );
ck( 'a mentor\'s row links to their Accounts tab on the Mentors screen', false !== strpos( $calls_html, accounts_link( 'mentors', 'ben@example.test', 'Ben Ode' ) ), true );

// An account with no program audience falls back to user-edit.php, and one with no user account stays plain text.
$GLOBALS['users'][9] = new WP_User( 9, 'Dan Subscriber', 'dan' );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-09 11:00:00', 'to_email' => 'dan@example.test', 'user_id' => 9, 'to_name' => 'Dan Subscriber', 'recipient_type' => 'other', 'module' => 'wordpress', 'template' => 'other', 'subject' => 'Password reset', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-09 10:00:00', 'to_email' => 'guest@example.test', 'user_id' => null, 'to_name' => 'Guest Person', 'recipient_type' => 'applicant', 'module' => 'other', 'template' => 'other', 'subject' => 'Application received', 'status' => 'sent' ) );
$misc_html = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 's' => 'Dan' ) );
ck( 'an account with no program audience falls back to user-edit.php', false !== strpos( $misc_html, '<a href="https://example.test/wp-admin/user-edit.php?user_id=9">Dan Subscriber</a>' ), true );
$guest_html = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 's' => 'Guest' ) );
ck( 'a recipient with no account attached stays plain text', array( false !== strpos( $guest_html, 'Guest Person' ), false !== strpos( $guest_html, '>Guest Person</a>' ) ), array( true, false ) );

// The link follows the role the account holds now (Review Focus 1), not the type the row was recorded
// under: a student who is a mentor now, and no student any more, is on the Mentors screen's Accounts tab.
// The Administrators screen lists the Administrator role alone, so an account that holds only the program's
// capability is sent to its user editor, where its roles are. The search is the account's current email,
// which belongs to one account, so the person is on the first page: not the address the row was sent to,
// which the account may have changed since, and not a username that many accounts contain.
$GLOBALS['users'][10] = new WP_User( 10, 'Eli Park', 'epark', array( WPCPM_Roles::ROLE_MENTOR ), array(), 'eli.park@new.example.test' );
$GLOBALS['users'][11] = new WP_User( 11, 'Fay Boss', 'fboss', array(), array( WPCPM_Roles::CAP_MANAGE ) );
$GLOBALS['users'][12] = new WP_User( 12, 'Gus Trust', 'gtrust', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][13] = new WP_User( 13, 'Hal Both', 'hboth', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ), array(), 'hal+lms@example.test' );
$GLOBALS['users'][14] = new WP_User( 14, 'Ivy Hands', 'ihands', array( WPCPM_Roles::ROLE_MENTOR, WPCPM_Roles::ROLE_ADMIN ), array( WPCPM_Roles::CAP_MANAGE ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-07 12:00:00', 'to_email' => 'eli@example.test', 'user_id' => 10, 'to_name' => 'Eli Park', 'recipient_type' => 'student', 'module' => 'students', 'template' => 'other', 'subject' => 'Welcome Eli', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-07 11:00:00', 'to_email' => 'fay@example.test', 'user_id' => 11, 'to_name' => 'Fay Boss', 'recipient_type' => 'other', 'module' => 'other', 'template' => 'other', 'subject' => 'Welcome Fay', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-07 10:00:00', 'to_email' => 'gus@example.test', 'user_id' => 12, 'to_name' => 'Gus Trust', 'recipient_type' => 'administrator', 'module' => 'other', 'template' => 'other', 'subject' => 'Welcome Gus', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-07 09:00:00', 'to_email' => 'hal@example.test', 'user_id' => 13, 'to_name' => 'Hal Both', 'recipient_type' => 'mentor', 'module' => 'other', 'template' => 'other', 'subject' => 'Welcome Hal', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-07 08:00:00', 'to_email' => 'ghost@example.test', 'user_id' => 99, 'to_name' => 'Ghost Gone', 'recipient_type' => 'student', 'module' => 'other', 'template' => 'other', 'subject' => 'Welcome Ghost', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-07 07:00:00', 'to_email' => 'ivy@example.test', 'user_id' => 14, 'to_name' => 'Ivy Hands', 'recipient_type' => 'administrator', 'module' => 'other', 'template' => 'other', 'subject' => 'Welcome Ivy', 'status' => 'sent' ) );
$welcome_html = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 's' => 'Welcome' ) );
ck( 'a row recorded as student whose account now holds the mentor role and not the student role links to the Mentors screen\'s Accounts tab',
	false !== strpos( $welcome_html, accounts_link( 'mentors', 'eli.park@new.example.test', 'Eli Park' ) ), true );
ck( 'the search is the account\'s current email, not the address the row was sent to',
	array( false !== strpos( $welcome_html, 'eli@example.test' ), false !== strpos( $welcome_html, 's=eli%40example.test' ), false !== strpos( $welcome_html, 's=epark' ) ),
	array( true, false, false ) );
ck( 'an account that holds only the program\'s capability links to its user editor, since the Administrators Accounts tab lists the Administrator role alone',
	array( false !== strpos( $welcome_html, '<a href="https://example.test/wp-admin/user-edit.php?user_id=11">Fay Boss</a>' ), false !== strpos( $welcome_html, 'tab=accounts&s=fboss' ), false !== strpos( $welcome_html, 's=fboss' ) ),
	array( true, false, false ) );
ck( 'a row recorded as an Administrator, to an account that holds the Administrator role, links to the Administrators screen\'s Accounts tab',
	false !== strpos( $welcome_html, accounts_link( 'administrators', 'gtrust@example.test', 'Gus Trust' ) ), true );
ck( 'an account that holds two program roles keeps the recorded type while it still holds that role, and an address with a plus is encoded whole, so the plus is not read as a space',
	array( false !== strpos( $welcome_html, accounts_link( 'mentors', 'hal+lms@example.test', 'Hal Both' ) ), false !== strpos( $welcome_html, 's=hal%2Blms%40example.test#' ) ),
	array( true, true ) );
ck( 'an account that holds the Administrator role and the mentor role, recorded as an Administrator, keeps the Administrators screen',
	false !== strpos( $welcome_html, accounts_link( 'administrators', 'ihands@example.test', 'Ivy Hands' ) ), true );
ck( 'a row whose account is gone stays plain text', array( false !== strpos( $welcome_html, 'Ghost Gone' ), false !== strpos( $welcome_html, '>Ghost Gone</a>' ) ), array( true, false ) );

// Every link to an Accounts tab ends at the list, by the anchor the accounts table names. The Emails screen
// does not load that table (it would cost a require the screen never otherwise makes), so the Log keeps the
// anchor as a literal, and this holds the literal and the table's constant equal.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';
preg_match_all( '#<a href="([^"]*page=wpcpm-(?:students|mentors|institutions|sponsors|administrators)[^"]*)"#', $calls_html . $welcome_html, $accounts_links );
ck( 'every link to an Accounts tab ends with the accounts table\'s list anchor',
	array( count( $accounts_links[1] ) > 4, array_values( array_unique( array_map( function ( $href ) { return substr( $href, (int) strrpos( $href, '#' ) ); }, $accounts_links[1] ) ) ) ),
	array( true, array( '#' . WPCPM_Accounts_Table::LIST_ANCHOR ) ) );
ck( 'and the anchor the Log writes is that constant\'s value', WPCPM_Accounts_Table::LIST_ANCHOR, 'wpcpm-accounts-list' );
$log_table_code = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log-table.php' );
ck( 'the Log asks the capture for the type the account holds now, and keeps no list of the program\'s roles of its own: the Administrator role is the one it names, for the Administrators screen',
	array(
		false !== strpos( $log_table_code, 'WPCPM_Mail_Capture::type_of(' ),
		0 === preg_match( '/ROLE_(STUDENT|MENTOR|INSTITUTION|SPONSOR)/', $log_table_code ),
		substr_count( $log_table_code, 'WPCPM_Roles::' ),
		false !== strpos( $log_table_code, '$user->user_email' ),
		false === strpos( $log_table_code, 'user_login' ),
	),
	array( true, true, 1, true, true ) );

$GLOBALS['cached'] = array();
screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'module' => 'sponsors', 's' => 'nobody' ) );
ck( 'and a page that names nobody asks for none', $GLOBALS['cached'], array() );

// A filter that matches nothing, and the box still there to search again.
$none = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'module' => 'wordpress', 's' => 'nobody' ) );
ck( 'a filter that matches nothing says so, and the search box is still there, holding the search', array( false !== strpos( $none, '0 emails match.' ), false !== strpos( $none, 'No email matches.' ), false !== strpos( $none, 'name="s" value="nobody"' ), false !== strpos( $none, '<option value="wordpress" selected=\'selected\'>WordPress</option>' ) ), array( true, true, true, true ) );

// The page links carry the filters, which are the address's.
for ( $i = 0; $i < 51; $i++ ) {
	WPCPM_Mail_Log::add( array( 'sent_at' => gmdate( 'Y-m-d H:i:s', time() - $i * 60 ), 'to_email' => 'many@example.test', 'module' => 'sponsors', 'template' => 'offer-low-stock', 'subject' => 'Codes running low', 'status' => 'sent' ) );
}
$paged = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'module' => 'sponsors' ) );
ck( '50 a page, and the next page\'s link keeps the Log tab and the filter', array( substr_count( $paged, 'many@example.test' ), 1 === preg_match( "#class='next-page button' href='[^']*\?page=wpcpm-tool-emails&tab=log&module=sponsors&paged=2'#", $paged ) ), array( 50, true ) );

// No table: the screen says the log is not ready, and draws no table.
$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
$missing = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log' ) );
ck( 'with no table the screen says the log is not ready yet, and draws no table', array( false !== strpos( $missing, 'The email log is not ready yet' ), strpos( $missing, '<table' ) ), array( true, false ) );

// Only Administrators.
$GLOBALS['can'] = false;
$died           = '';
try {
	screen( array( 'page' => 'wpcpm-tool-emails' ) );
} catch ( Exception $e ) {
	$died = $e->getMessage();
}
ck( 'anyone else is refused', false !== strpos( $died, 'permission' ), true );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
