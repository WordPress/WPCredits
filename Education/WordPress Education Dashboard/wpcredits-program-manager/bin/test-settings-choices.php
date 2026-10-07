<?php
/**
 * Are the Settings screen's choices drawn from the lists they come from, and does each save what
 * the text input it replaced saved?
 *
 * Settings that each hold one choice, or several, from a known list were typed by hand: the base,
 * the tables and their name columns, three Airtable statuses, the two status lists, the three
 * reviewer lists, the model and the pipeline stages. A typo in a status list changes who keeps the
 * Student role, so the screen now draws each as a select or a checkbox list read from where the
 * list lives (the Settings design of 28 September 2026), and falls back to the text input it
 * always had, with the same name and value, when the list cannot be read.
 *
 * The lists are read through the real readers over a stand-in transport, which answers as Airtable
 * does or refuses as the local copies do; the tabs are drawn through the real screen and posted the
 * way a browser posts them, disabled boxes left out, through the real save handler. The stored value
 * a choice saves is held to the one its text input saved for the same string, so no reader of the
 * settings can tell which control wrote it.
 *
 * Run from the plugin root:  php bin/test-settings-choices.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts']       = array();
$GLOBALS['transients'] = array();
$GLOBALS['ttl']        = array();
$GLOBALS['umeta']      = array();
$GLOBALS['routes']     = array();
$GLOBALS['sent']       = array();
$GLOBALS['refuse']     = false;
$GLOBALS['users']      = array();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_User {
	public $ID = 0, $roles = array(), $display_name = '', $user_email = '', $user_login = '';
	public function __construct( $id = 0, $name = '', $email = '', array $roles = array() ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_status = 'publish', $post_title = ''; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
// Core's escapes an attribute's value without encoding an entity already in it a second time, so a
// status the list keeps as `&lt;` is printed as `&lt;` and read back by a browser as "<".
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $u, $protocols = null ) { return (string) $u; }
function esc_textarea( $s ) { return esc_html( $s ); }
/**
 * Core's, in the two ways a status list tells apart: a "<" that opens no tag is kept as `&lt;`, and
 * every run of spaces, tabs and line breaks is one space (as bin/test-settings.php has it).
 *
 * @param mixed $s The value.
 * @return string
 */
function sanitize_text_field( $s ) {
	$s = (string) $s;

	if ( false !== strpos( $s, '<' ) ) {
		$s = preg_replace_callback(
			'%<[^>]*?((?=<)|>|$)%',
			function ( $m ) {
				return false === strpos( $m[0], '>' ) ? esc_html( $m[0] ) : $m[0];
			},
			$s
		);
		$s = strip_tags( $s );
	}

	return trim( preg_replace( '/[\r\n\t ]+/', ' ', $s ) );
}
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) {
	$e = (string) $e;

	if ( strlen( $e ) < 3 || false === strpos( $e, '@', 1 ) || substr_count( $e, '@' ) > 1 ) {
		return '';
	}

	list( $local, $domain ) = explode( '@', $e, 2 );

	$local  = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.\[\]-]/', '', $local );
	$domain = preg_replace( '/[^a-zA-Z0-9\.\-]/', '', $domain );

	return ( '' === $local || '' === $domain ) ? '' : $local . '@' . $domain;
}
function is_email( $e ) { return false !== filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
// No page at the path the program manager guide is published at, so the section intros link nothing.
function get_page_by_path( $path, $output = 'OBJECT', $type = 'page' ) { return null; }
function add_action() {}
function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; $GLOBALS['ttl'][ $k ] = $ttl; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ], $GLOBALS['ttl'][ $k ] ); return true; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_doing_cron() { return false; }
function wp_timezone_string() { return 'UTC'; }

/**
 * The transport: answers each address from the routes the suite sets, as Airtable would, refuses
 * everything while `$GLOBALS['refuse']` is set, as the local copies refuse Airtable, and remembers
 * what was asked. An address nobody routed is Airtable's 404.
 */
function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['sent'][] = array(
		'url'    => $url,
		'method' => isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET',
	);

	if ( $GLOBALS['refuse'] ) {
		return new WP_Error( 'http_request_failed', 'Airtable is refused on this copy.' );
	}

	if ( isset( $GLOBALS['routes'][ $url ] ) ) {
		return $GLOBALS['routes'][ $url ];
	}

	return response( 404, array( 'error' => 'NOT_FOUND' ) );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['response']['code'] ?? 200 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $r, $h ) { return ''; }

/**
 * A canned HTTP API response in the shape `wp_remote_request()` returns.
 *
 * @param int   $code The status.
 * @param array $body The JSON body.
 * @return array
 */
function response( $code, array $body ) {
	return array(
		'response' => array( 'code' => $code ),
		'headers'  => array(),
		'body'     => json_encode( $body ),
	);
}

// The accounts `get_users()` answers from, narrowed by the capability as WordPress narrows it, and
// asked of each by `user_can()`: the manager IDs are in `$GLOBALS['manage']`. Each query is counted.
$GLOBALS['user_queries'] = 0;
function get_users( $args = array() ) {
	++$GLOBALS['user_queries'];
	$found = array();

	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['capability'] ) && ! user_can( $user, $args['capability'] ) ) {
			continue;
		}

		$found[] = $user;
	}

	return $found;
}
require_once __DIR__ . '/stubs/caps.php';
// The manager at the screen may manage the program whoever else may: `caps` answers for the current
// request, and `manage` for the accounts the reviewer lists look up.
$GLOBALS['caps']   = true;
$GLOBALS['manage'] = array();
// Each nonce a handler checks is recorded, and refused on demand as WordPress refuses one: with its
// own screen, before anything else the handler would say.
$GLOBALS['nonces']   = array();
$GLOBALS['nonce_ok'] = true;
function check_admin_referer( $a = -1, $q = '_wpnonce' ) {
	$GLOBALS['nonces'][] = $a;

	if ( ! $GLOBALS['nonce_ok'] ) {
		throw new Exception( 'nonce refused' );
	}

	return true;
}
function wp_safe_redirect( $to ) { $GLOBALS['redirected_to'] = $to; throw new Exception( 'redirect' ); }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function wp_die( $m = '' ) { throw new Exception( 'wp_die: ' . $m ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function get_user_meta( $id, $k, $s = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
// The flash slashes what it writes for core's user meta, which unslashes it; this one keeps what it
// is handed, so the slash is the identity here (bin/test-flash.php holds the flash to core's).
function wp_slash( $v ) { return $v; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return 1; }
function wp_get_current_user() { return new WP_User( 1 ); }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	echo '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="nonce:' . esc_attr( $action ) . '" />';
}
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
	$id     = is_array( $other ) && isset( $other['id'] ) ? $other['id'] : $name;
	$button = '<input type="submit" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" class="button button-' . esc_attr( $type ) . '" value="' . esc_attr( $text ) . '" />';

	echo $wrap ? '<p class="submit">' . $button . '</p>' : $button;
}
function checked( $checked, $current = true, $display = true ) {
	$result = (string) $checked === (string) $current ? " checked='checked'" : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}
function selected( $selected, $current = true, $display = true ) {
	$result = (string) $selected === (string) $current ? " selected='selected'" : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}
function wp_kses( $s, $allowed = array() ) { return strip_tags( (string) $s, '<code>' ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
// Required as the plugin's own loader requires it, when it is there: a run before it existed says
// which checks it answers rather than stopping at the first line that names it.
if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-choices.php' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-choices.php';
}
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
// The real tracks reader, over its compiled index in the options, and the real track rules the save
// holds "Past students" to.
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-tracks.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';
// The real column maps the syncs read Airtable by, which name the Status and Current Stage columns
// the lists come from, and the real program managers the reviewer lists are drawn from.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
// Before the Institutions module, which uses it: PHP declares a class only once its traits are.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';
// The rows every settings form is drawn with, the Settings screen, and the three tools that draw their
// own settings on their own screens, as the plugin's loader requires them.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-rows.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php';

/** The dashboards whose pages the landing-page rows link to. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() {
		return 'https://example.test/mentor-report-card/';
	}
}

/** As above, for students. */
class WPCPM_Students_Dashboard {
	public static function page_url() {
		return 'https://example.test/student-dashboard/';
	}
}

/** The two-factor policy, as the Security tab reads it. */
class WPCPM_Two_Factor {
	public static function status() {
		return array( 'available' => true, 'roles' => array() );
	}

	public static function required_roles() {
		return (array) WPCPM_Settings::get_value( 'two_factor_roles', array() );
	}
}

/** The compile a save runs when a status list changed, counted. */
class WPCPM_Track_Store {
	public static $compiled = 0;

	public static function compile() {
		++self::$compiled;

		return array();
	}
}

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * A static method of a class that may not exist yet, called, or null when there is nothing to call.
 *
 * @param string $class  The class.
 * @param string $method The method.
 * @param mixed  ...$args Its arguments.
 * @return mixed
 */
function call( $class, $method, ...$args ) {
	return is_callable( array( $class, $method ) ) ? call_user_func_array( array( $class, $method ), $args ) : null;
}

/**
 * One reader of the Airtable client the plugin builds from the stored settings, or null when the
 * client has no such reader.
 *
 * @param string $method The reader.
 * @param mixed  ...$args Its arguments.
 * @return mixed
 */
function airtable( $method, ...$args ) {
	$client = new WPCPM_Airtable();

	return method_exists( $client, $method ) ? call_user_func_array( array( $client, $method ), $args ) : null;
}

/**
 * One of the row helpers every settings form is drawn with, drawn, as a request of its own: the lists
 * read for the one before are not its lists.
 *
 * @param string $method The helper.
 * @param mixed  ...$args Its arguments.
 * @return string|null The row's markup, or null when there is no such helper.
 */
function row( $method, ...$args ) {
	if ( ! is_callable( array( 'WPCPM_Settings_Rows', $method ) ) ) {
		return null;
	}

	if ( class_exists( 'WPCPM_Settings_Choices' ) && method_exists( 'WPCPM_Settings_Choices', 'flush' ) ) {
		WPCPM_Settings_Choices::flush();
	}

	ob_start();
	call_user_func_array( array( 'WPCPM_Settings_Rows', $method ), $args );

	return (string) ob_get_clean();
}

/**
 * An element's attributes, as a browser reads them.
 *
 * @param string $tag The attributes of one opening tag.
 * @return array<string, string>
 */
function attrs( $tag ) {
	preg_match_all( '#([a-z][a-z-]*)(?:=(["\'])(.*?)\2)?#i', $tag, $found, PREG_SET_ORDER );

	$out = array();

	foreach ( $found as $attr ) {
		$out[ strtolower( $attr[1] ) ] = isset( $attr[3] ) ? html_entity_decode( $attr[3], ENT_QUOTES ) : '';
	}

	return $out;
}

/**
 * What a browser posts from this markup: each named input, its checked boxes only and never a
 * disabled one, each select's chosen option or its first, each textarea's text; a name ending in
 * `[]` as a list, in the order drawn.
 *
 * @param string $html A form's markup.
 * @return array The request, by name.
 */
function browser_post( $html ) {
	$post = array();
	$add  = function ( $name, $value ) use ( &$post ) {
		if ( '[]' === substr( $name, -2 ) ) {
			$post[ substr( $name, 0, -2 ) ][] = $value;
		} else {
			$post[ $name ] = $value;
		}
	};

	preg_match_all( '#<(input|select|textarea)\b([^>]*)>(?:(.*?)</\1>)?#s', $html, $controls, PREG_SET_ORDER );

	foreach ( $controls as $control ) {
		$a = attrs( $control[2] );

		if ( ! isset( $a['name'] ) || isset( $a['disabled'] ) ) {
			continue;
		}

		if ( 'input' === $control[1] ) {
			$type = isset( $a['type'] ) ? strtolower( $a['type'] ) : 'text';

			if ( 'submit' === $type || ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! isset( $a['checked'] ) ) ) {
				continue;
			}

			$add( $a['name'], isset( $a['value'] ) ? $a['value'] : '' );
		} elseif ( 'select' === $control[1] ) {
			preg_match_all( '#<option\b([^>]*)>#', isset( $control[3] ) ? $control[3] : '', $options );

			$first  = null;
			$chosen = null;

			foreach ( $options[1] as $option ) {
				$o     = attrs( $option );
				$first = null === $first ? ( isset( $o['value'] ) ? $o['value'] : '' ) : $first;

				if ( isset( $o['selected'] ) ) {
					$chosen = isset( $o['value'] ) ? $o['value'] : '';
				}
			}

			$add( $a['name'], null !== $chosen ? $chosen : (string) $first );
		} else {
			$add( $a['name'], html_entity_decode( isset( $control[3] ) ? $control[3] : '', ENT_QUOTES ) );
		}
	}

	return $post;
}

/**
 * The values a select offers, with their labels, as drawn: option groups flattened in order.
 *
 * @param string $html The markup.
 * @param string $name The select's name.
 * @return array<string, string>|null Null when there is no such select.
 */
function select_options( $html, $name ) {
	if ( ! preg_match( '#<select\b[^>]*\bname="' . preg_quote( $name, '#' ) . '"[^>]*>(.*?)</select>#s', $html, $select ) ) {
		return null;
	}

	preg_match_all( '#<option\b([^>]*)>([^<]*)</option>#', $select[1], $options, PREG_SET_ORDER );

	$offered = array();

	foreach ( $options as $option ) {
		$a                            = attrs( $option[1] );
		$offered[ (string) $a['value'] ] = html_entity_decode( $option[2], ENT_QUOTES );
	}

	return $offered;
}

/**
 * The value a text input is drawn with, as a browser reads it.
 *
 * @param string $html The markup.
 * @param string $name The input's name.
 * @return string|null Null when there is no such text input.
 */
function drawn_input( $html, $name ) {
	if ( ! preg_match( '#<input type="text"[^>]*\bname="' . preg_quote( $name, '#' ) . '"[^>]*>#', $html, $input ) ) {
		return null;
	}

	$a = attrs( $input[0] );

	return isset( $a['value'] ) ? $a['value'] : '';
}

/**
 * The boxes a checkbox list draws: each value with its label, whether it is ticked and whether it
 * is locked, by the group it sits in (`''` for none). Read from the row that holds the list, since
 * two lists on one tab carry groups of the same names.
 *
 * @param string $html The markup.
 * @param string $name The list's name, without its `[]`.
 * @return array<string, array> Group => value => array( label, ticked, locked ).
 */
function boxes_of( $html, $name ) {
	$groups = array();
	$row    = '';

	preg_match_all( '#<tr>.*?</tr>#s', $html, $rows );

	foreach ( $rows[0] as $candidate ) {
		if ( false !== strpos( $candidate, 'type="checkbox" name="' . $name . '[]"' ) ) {
			$row = $candidate;
			break;
		}
	}

	preg_match_all( '#<fieldset class="wpcpm-settings__group"><legend>([^<]*)</legend>(.*?)</fieldset>#s', $row, $found, PREG_SET_ORDER );

	$rest = $row;

	foreach ( $found as $group ) {
		$groups[ html_entity_decode( $group[1], ENT_QUOTES ) ] = $group[2];
		$rest = str_replace( $group[0], '', $rest );
	}

	$groups[''] = $rest;
	$boxes      = array();

	foreach ( $groups as $group => $markup ) {
		preg_match_all( '#<label><input type="checkbox" ([^>]*)/> ([^<]*)(?: <span class="description">[^<]*</span>)?</label>#', $markup, $labels, PREG_SET_ORDER );

		foreach ( $labels as $label ) {
			$a = attrs( $label[1] );

			if ( ! isset( $a['name'] ) || $name . '[]' !== $a['name'] ) {
				continue;
			}

			$boxes[ $group ][ $a['value'] ] = array( html_entity_decode( $label[2], ENT_QUOTES ), isset( $a['checked'] ), isset( $a['disabled'] ) );
		}
	}

	return $boxes;
}

/**
 * The settings screen at one tab, as a manager opening it sees it.
 *
 * @param string $tab The tab.
 * @return string
 */
function draw_settings( $tab ) {
	$_GET = array( 'tab' => $tab );

	if ( class_exists( 'WPCPM_Settings_Choices' ) && method_exists( 'WPCPM_Settings_Choices', 'flush' ) ) {
		WPCPM_Settings_Choices::flush();
	}

	ob_start();
	( new WPCPM_Settings_Screen() )->render_settings();
	$html = (string) ob_get_clean();

	$_GET = array();

	return $html;
}

/**
 * A tool's Settings section, as its own screen draws it, the lists read afresh.
 *
 * @param string $scope The tool's scope, `tool:` and its ID.
 * @return string The section's markup, or '' when the tool draws none.
 */
function draw_tool( $scope ) {
	$tools = array(
		'tool:mentor-status-checker' => 'WPCPM_Mentor_Checker',
		'tool:duplicate-finder'      => 'WPCPM_Duplicate_Finder',
		'tool:handbook'              => 'WPCPM_Handbook',
	);
	$tool  = new $tools[ $scope ]();

	if ( ! method_exists( $tool, 'render_settings' ) ) {
		return '';
	}

	if ( class_exists( 'WPCPM_Settings_Choices' ) && method_exists( 'WPCPM_Settings_Choices', 'flush' ) ) {
		WPCPM_Settings_Choices::flush();
	}

	ob_start();
	$tool->render_settings();

	return (string) ob_get_clean();
}

/**
 * The one form a settings screen draws: a tab of the Settings screen, or a tool's Settings section.
 *
 * @param string $scope A tab's slug, or a tool's scope.
 * @return string The form's markup.
 */
function form_for( $scope ) {
	return form_of( 0 === strpos( $scope, 'tool:' ) ? draw_tool( $scope ) : draw_settings( $scope ) );
}

/**
 * The one form a tab draws, or the markup of its first form.
 *
 * @param string $html The screen.
 * @return string
 */
function form_of( $html ) {
	return preg_match( '#<form\b[^>]*>(.*?)</form>#s', $html, $form ) ? $form[1] : '';
}

/**
 * The names a form's controls post under, `[]` dropped, each once, sorted.
 *
 * @param string $form A form's markup.
 * @return string[]
 */
function names_of( $form ) {
	preg_match_all( '#<(?:input|select|textarea)\b[^>]*?\bname="([^"]+)"#', $form, $found );

	$names = array();

	foreach ( $found[1] as $name ) {
		$names[ preg_replace( '/\[\]$/', '', $name ) ] = true;
	}

	$names = array_keys( array_diff_key( $names, array_flip( array( WPCPM_Settings_Screen::SETTINGS_NONCE, 'wpcpm_tab', 'submit', WPCPM_Settings::FIELD_DRAWN_STATUSES ) ) ) );
	sort( $names );

	return $names;
}

/**
 * Press Save with this request, through the real handler.
 *
 * @param array $post The request.
 * @return string How the handler ended.
 */
function press( array $post ) {
	$_POST                    = $post;
	$GLOBALS['redirected_to'] = null;
	$ended                    = 'no outcome';

	try {
		( new WPCPM_Settings_Screen() )->handle_settings_save();
	} catch ( Exception $e ) {
		$ended = $e->getMessage();
	}

	$_POST = array();

	return $ended;
}

/**
 * The settings stored afresh, the version stamped, the queue of notices empty, and the tracks the
 * site runs compiled into their index.
 *
 * @param array $settings The settings.
 * @param array $tracks   Status => the track's name, for each track the site runs.
 */
function store( array $settings, array $tracks = array() ) {
	$index = array();

	foreach ( $tracks as $status => $label ) {
		$index[ $status ] = array( 'key' => sanitize_key( $label ), 'label' => $label, 'post' => 10 + count( $index ) );
	}

	$GLOBALS['opts']  = array(
		WPCPM_Settings::OPT_NAME    => $settings,
		WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
		WPCPM_Tracks::OPT_TRACKS    => $index,
	);
	$GLOBALS['umeta'] = array();

	WPCPM_Tracks::flush();
}

/**
 * Airtable answering, or not, from nothing held: every copy the readers keep dropped.
 *
 * @param bool $answers Whether Airtable answers.
 */
function airtable_answers( $answers ) {
	$GLOBALS['refuse']     = ! $answers;
	$GLOBALS['transients'] = array();
	$GLOBALS['ttl']        = array();
	$GLOBALS['sent']       = array();

	if ( class_exists( 'WPCPM_Settings_Choices' ) && method_exists( 'WPCPM_Settings_Choices', 'flush' ) ) {
		WPCPM_Settings_Choices::flush();
	}
}

/**
 * The flash a save queued for the signed-in manager.
 *
 * @param string $channel The channel.
 * @return mixed
 */
function queued( $channel ) {
	$pending = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();

	return $pending[ $channel ] ?? null;
}

/* ---- the base, as Airtable describes it --------------------------------- */

// The vocabularies are the base's own, read from its metadata into the fixtures: the Mentors table's
// Status, the shared student Status the Students Reports table uses too, and the Institutions table's
// Current Stage.
$fixture_mentors      = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/mentors-table-fields.json' ), true );
$fixture_students     = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/students-table-fields.json' ), true );
$fixture_institutions = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/institutions-table-fields.json' ), true );
$mentor_statuses      = $fixture_mentors['choices']['Status'];
$report_statuses      = $fixture_students['choices']['Status'];
$stages               = $fixture_institutions['choices']['Current Stage'];

/**
 * A single-select field as the schema reads it.
 *
 * @param string   $id      The field's ID.
 * @param string   $name    Its name.
 * @param string[] $choices Its options, in order.
 * @return array
 */
function select_field( $id, $name, array $choices ) {
	$options = array();

	foreach ( $choices as $i => $choice ) {
		$options[] = array( 'id' => 'sel' . $id . $i, 'name' => $choice, 'color' => 'blueLight2' );
	}

	return array( 'id' => $id, 'name' => $name, 'type' => 'singleSelect', 'options' => array( 'choices' => $options ) );
}

/**
 * A table as the schema reads it: its primary column first unless said otherwise.
 *
 * @param string $id      The table's ID.
 * @param string $name    Its name.
 * @param array  $fields  Its fields, each a name or a full field.
 * @param int    $primary Which field is primary.
 * @return array
 */
function table( $id, $name, array $fields, $primary = 0 ) {
	$built = array();

	foreach ( $fields as $i => $field ) {
		$built[] = is_array( $field ) ? $field : array( 'id' => 'fld' . substr( $id, 3, 5 ) . $i, 'name' => $field, 'type' => 'singleLineText' );
	}

	return array( 'id' => $id, 'name' => $name, 'primaryFieldId' => $built[ $primary ]['id'], 'fields' => $built );
}

$defaults = WPCPM_Settings::defaults();
$base     = $defaults['base_id'];

// The ten tables the Connection tab names, at the IDs the defaults hold, the Institutions table
// with its primary column listed second, as the base may list it.
$schema_body = array(
	'tables' => array(
		table( $defaults['mentors_table'], 'Mentors', array( 'Full Name', 'Email', select_field( 'fldMSTATUS00001', 'Status', $mentor_statuses ) ) ),
		table( $defaults['reports_table'], 'Students Reports', array( 'Name', 'Email', select_field( 'fldRSTATUS00001', 'Status', $report_statuses ) ) ),
		table( $defaults['students_table'], 'Students', array( 'Name', 'Tutor ' ) ),
		table( $defaults['institutions_table'], 'Institutions', array( 'Country', 'Name', select_field( 'fldISTAGE000001', 'Current Stage', $stages ) ), 1 ),
		table( $defaults['teams_table'], 'Contribution areas', array( 'Contribution teams or areas', 'Description' ) ),
		table( $defaults['tutors_table'], 'Tutors', array( 'Name', 'Institution' ) ),
		table( $defaults['feedback_table'], 'Feedback', array( 'Name', 'F1 How was it?' ) ),
		table( $defaults['countries_table'], 'Countries', array( 'Name', 'Program manager' ) ),
		table( $defaults['team_members_table'], 'Team Members', array( 'Name', 'Email' ) ),
		table( $defaults['sponsors_table'], 'Sponsors', array( 'Company Name', 'Status' ) ),
	),
);
$schema_url  = 'https://api.airtable.com/v0/meta/bases/' . $base . '/tables';
$bases_url   = 'https://api.airtable.com/v0/meta/bases';

// Two pages of bases, as Airtable pages a long list.
$GLOBALS['routes'] = array(
	$schema_url                      => response( 200, $schema_body ),
	$bases_url                       => response(
		200,
		array(
			'bases'  => array( array( 'id' => $base, 'name' => 'WPCredits', 'permissionLevel' => 'create' ) ),
			'offset' => 'itrPAGETWO000001/appPROBE000000002',
		)
	),
	$bases_url . '?offset=' . rawurlencode( 'itrPAGETWO000001/appPROBE000000002' ) => response(
		200,
		array( 'bases' => array( array( 'id' => 'appPROBE000000002', 'name' => 'Sandbox', 'permissionLevel' => 'read' ) ) )
	),
);

// The program managers: two administrators and one account given the capability another way, each
// with an address; an administrator with no address, and a mentor who manages nothing.
$GLOBALS['users']  = array(
	new WP_User( 1, 'Zoe Admin', 'zoe@program.example', array( 'administrator' ) ),
	new WP_User( 2, 'Ana Admin', 'Ana@Program.example', array( 'administrator' ) ),
	new WP_User( 3, 'Pat Coordinator', 'pat@program.example', array( 'editor' ) ),
	new WP_User( 4, 'No Address', '', array( 'administrator' ) ),
	new WP_User( 5, 'Mia Mentor', 'mia@program.example', array( WPCPM_Roles::ROLE_MENTOR ) ),
);
$GLOBALS['manage'] = array( 1, 2, 3, 4 );

$token     = 'patEVERYDAY00001.' . str_repeat( 'a', 64 );
$connected = array_merge( $defaults, array( 'api_token' => $token ) );

// The four original tracks and one of somebody's own, which Airtable's list does not name.
$live = array(
	'In Sensei'       => 'WordPress Credits Program 150h',
	'In Sensei 50h'   => 'WordPress Credits Program 50h',
	'Developer Track' => 'Developer Track',
	'Designer Track'  => 'Designer Track',
	'Mentor Track'    => 'Mentor Track',
);

/* ---- the two readers the Airtable client gains ---------------------------- */

echo "=== The Airtable client reads the bases and a field's options, and writes nothing ===\n";

// The stand-in escapes an attribute as core's does, so what a browser reads back from a value the
// suite prints is what it reads from WordPress.
ck( 'the suite\'s esc_attr() is core\'s: a quote and a "<" are encoded, an entity already there is kept as it is',
    array( esc_attr( 'Say "hi" <b>' ), esc_attr( 'Less &lt; More &amp; less' ) ),
    array( 'Say &quot;hi&quot; &lt;b&gt;', 'Less &lt; More &amp; less' ) );

store( $connected );
airtable_answers( true );

$first_read = airtable( 'bases' );
$first_sent = $GLOBALS['sent'];

ck( 'bases() answers every base the token can open, every page of them, by ID with its name',
    $first_read, array( $base => 'WPCredits', 'appPROBE000000002' => 'Sandbox' ) );
ck( 'and asks for them as reads of the bases list, one a page, and nothing else',
    $first_sent,
    array(
        array( 'url' => $bases_url, 'method' => 'GET' ),
        array( 'url' => $bases_url . '?offset=' . rawurlencode( 'itrPAGETWO000001/appPROBE000000002' ), 'method' => 'GET' ),
    ) );

$held = array_values( array_filter( array_keys( $GLOBALS['ttl'] ), function ( $key ) { return false !== strpos( $key, 'bases' ); } ) );

$GLOBALS['sent'] = array();
$again           = airtable( 'bases' );

ck( 'and keeps the list for a day, so the next draw asks Airtable nothing',
    array( count( $held ), isset( $held[0] ) ? $GLOBALS['ttl'][ $held[0] ] : null, $again, $GLOBALS['sent'] ),
    array( 1, DAY_IN_SECONDS, $first_read, array() ) );

ck( 'and keeps nothing of the token itself where the list is held',
    false !== strpos( serialize( $GLOBALS['transients'] ), $token ), false );

$bases_held = isset( $GLOBALS['transients'][ WPCPM_Airtable::BASES_TRANSIENT ] ) ? (array) $GLOBALS['transients'][ WPCPM_Airtable::BASES_TRANSIENT ] : array();

ck( 'but a fingerprint of it, under a name that says it is one',
    array( array_keys( $bases_held ), isset( $bases_held['print'] ) ? $bases_held['print'] : null ),
    array( array( 'print', 'bases' ), hash( 'sha256', $token ) ) );

// Another token can open other bases: the list held for the first one is not its answer.
store( array_merge( $connected, array( 'api_token' => 'patANOTHER000001.' . str_repeat( 'b', 64 ) ) ) );
$GLOBALS['sent'] = array();
airtable( 'bases' );

ck( 'and a different token has the list read again rather than handed the first token\'s',
    count( $GLOBALS['sent'] ) > 0, true );

store( $connected );
airtable_answers( false );

ck( 'bases() answers nothing when Airtable refuses the read, and holds nothing for the next draw',
    array( airtable( 'bases' ), $GLOBALS['transients'] ), array( array(), array() ) );

store( array_merge( $connected, array( 'api_token' => '' ) ) );
airtable_answers( true );

ck( 'and nothing, having asked nothing, with no token to ask with',
    array( airtable( 'bases' ), $GLOBALS['sent'] ), array( array(), array() ) );

// A token without the schema scope: Airtable answers 403 to both lists.
store( $connected );
airtable_answers( true );
$routes_kept                      = $GLOBALS['routes'];
$GLOBALS['routes'][ $bases_url ]  = response( 403, array( 'error' => array( 'type' => 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ) );
$GLOBALS['routes'][ $schema_url ] = response( 403, array( 'error' => array( 'type' => 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ) );

ck( 'and nothing for a token Airtable refuses the list to, without the schema.bases:read scope',
    array( airtable( 'bases' ), airtable( 'field_options', $defaults['mentors_table'], 'Status' ) ), array( array(), array() ) );

$GLOBALS['routes'] = $routes_kept;
airtable_answers( true );

$options    = airtable( 'field_options', $defaults['mentors_table'], 'Status' );
$options_to = $GLOBALS['sent'];

ck( 'field_options() answers a single-select field\'s options in the base\'s order, from the base\'s schema',
    $options, $mentor_statuses );
ck( 'and reads them with the schema read, once, and writes nothing',
    $options_to, array( array( 'url' => $schema_url, 'method' => 'GET' ) ) );

$held_options = array_values( array_filter( array_keys( $GLOBALS['ttl'] ), function ( $key ) { return false !== strpos( $key, 'options' ); } ) );

// The schema copy the track editor keeps lasts fifteen minutes; the options are kept a day of their
// own, so the next draw asks nothing although that copy is gone.
delete_transient( WPCPM_Airtable::SCHEMA_TRANSIENT );
$GLOBALS['sent'] = array();

ck( 'and keeps them for a day, beside the schema\'s own copy, so the next draw asks Airtable nothing',
    array( count( $held_options ), isset( $held_options[0] ) ? $GLOBALS['ttl'][ $held_options[0] ] : null, airtable( 'field_options', $defaults['mentors_table'], 'Status' ), $GLOBALS['sent'] ),
    array( 1, DAY_IN_SECONDS, $mentor_statuses, array() ) );

airtable_answers( true );

ck( 'and nothing for a column the table does not have, or one with no options, or a table the base does not have',
    array(
        airtable( 'field_options', $defaults['mentors_table'], 'No such column' ),
        airtable( 'field_options', $defaults['mentors_table'], 'Email' ),
        airtable( 'field_options', 'tblNOSUCHTABLE0001', 'Status' ),
    ),
    array( array(), array(), array() ) );

// A column named by nothing, which only a filter over a sync's column map can make, is said without
// asking Airtable, in a sentence of its own rather than one naming a column with no name.
airtable_answers( true );
$no_column = airtable( 'read_field_options', $defaults['mentors_table'], '' );

ck( 'and a blank column is answered without asking Airtable anything, in words that say no column is set',
    array( is_wp_error( $no_column ) ? $no_column->get_error_code() : null, is_wp_error( $no_column ) ? $no_column->get_error_message() : null, $GLOBALS['sent'] ),
    array( 'wpcpm_airtable_no_column', 'No column is set.', array() ) );

airtable_answers( false );

ck( 'and nothing when Airtable refuses the read',
    airtable( 'field_options', $defaults['mentors_table'], 'Status' ), array() );

airtable_answers( true );
store( array_merge( $connected, array( 'api_token' => '' ) ) );

ck( 'and nothing, having asked nothing, with no token',
    array( airtable( 'field_options', $defaults['mentors_table'], 'Status' ), $GLOBALS['sent'] ), array( array(), array() ) );

// Both lists outlive a request by a day, so a site that removes the plugin takes them with it, as it
// takes the schema copy beside them.
$uninstall_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'uninstall.php' );

ck( 'uninstall.php takes the two lists the screen keeps, beside the schema copy, and the failed read the screen remembers beside them',
    array(
        substr_count( $uninstall_source, 'delete_transient( WPCPM_Airtable::BASES_TRANSIENT );' ),
        substr_count( $uninstall_source, 'delete_transient( WPCPM_Airtable::OPTIONS_TRANSIENT );' ),
        substr_count( $uninstall_source, 'delete_transient( WPCPM_Airtable::FAILED_TRANSIENT );' ),
        defined( 'WPCPM_Airtable::BASES_TRANSIENT' ) && defined( 'WPCPM_Airtable::OPTIONS_TRANSIENT' ) && false !== strpos( WPCPM_Airtable::BASES_TRANSIENT, 'bases' ) && false !== strpos( WPCPM_Airtable::OPTIONS_TRANSIENT, 'options' ),
        defined( 'WPCPM_Airtable::FAILED_TRANSIENT' ) && 0 === strpos( (string) constant( 'WPCPM_Airtable::FAILED_TRANSIENT' ), 'wpcpm_airtable_' ),
    ),
    array( 1, 1, 1, true, true ) );

/* ---- the lists ------------------------------------------------------------ */

echo "\n=== WPCPM_Settings_Choices answers each list, and nothing when it cannot be read ===\n";

store( $connected, $live );
airtable_answers( true );

$table_names = array(
	$defaults['mentors_table']      => 'Mentors',
	$defaults['reports_table']      => 'Students Reports',
	$defaults['students_table']     => 'Students',
	$defaults['institutions_table'] => 'Institutions',
	$defaults['teams_table']        => 'Contribution areas',
	$defaults['tutors_table']       => 'Tutors',
	$defaults['feedback_table']     => 'Feedback',
	$defaults['countries_table']    => 'Countries',
	$defaults['team_members_table'] => 'Team Members',
	$defaults['sponsors_table']     => 'Sponsors',
);

ck( 'bases(): each base the token can open, by ID with its name',
    call( 'WPCPM_Settings_Choices', 'bases' ), array( $base => 'WPCredits', 'appPROBE000000002' => 'Sandbox' ) );
ck( 'tables(): each table of the base, by ID with its name, in the base\'s order',
    call( 'WPCPM_Settings_Choices', 'tables' ), $table_names );
ck( 'columns(): a table\'s columns by name, its primary column first, found by the table\'s ID or its name',
    array( call( 'WPCPM_Settings_Choices', 'columns', $defaults['institutions_table'] ), call( 'WPCPM_Settings_Choices', 'columns', 'Institutions' ) ),
    array_fill( 0, 2, array( 'Name' => 'Name', 'Country' => 'Country', 'Current Stage' => 'Current Stage' ) ) );
ck( 'and nothing for a table the base does not have',
    call( 'WPCPM_Settings_Choices', 'columns', 'tblNOSUCHTABLE0001' ), array() );
ck( 'status_options(): a single-select column\'s options, each its own value',
    call( 'WPCPM_Settings_Choices', 'status_options', $defaults['institutions_table'], 'Current Stage' ), array_combine( $stages, $stages ) );
ck( 'tracks_statuses(): the status of every track the site runs, with the track\'s name',
    call( 'WPCPM_Settings_Choices', 'tracks_statuses' ), $live );
ck( 'managers(): every account holding the capability that has an address, in one group, Administrators, the one name the screens give the people who manage the program, whether the account holds WordPress\'s Administrator role or holds the capability another way, each by address with its name, in name order',
    call( 'WPCPM_Settings_Choices', 'managers' ),
    array(
        'Administrators' => array( 'Ana@Program.example' => 'Ana Admin', 'pat@program.example' => 'Pat Coordinator', 'zoe@program.example' => 'Zoe Admin' ),
    ) );
ck( 'models(): the provider names no models, so the default the plugin ships, alone',
    call( 'WPCPM_Settings_Choices', 'models' ), array( $defaults['handbook_model'] => $defaults['handbook_model'] ) );

ck( 'the lists the screen draws are put together here as well, where the tool screens can reach them: IDs with their names, and the statuses and stages of the tables the settings name',
    array(
        call( 'WPCPM_Settings_Choices', 'named_ids', array( 'appA' => 'WPCredits', 'appB' => '' ) ),
        call( 'WPCPM_Settings_Choices', 'mentor_statuses', $connected ),
        call( 'WPCPM_Settings_Choices', 'report_statuses', $connected ),
        call( 'WPCPM_Settings_Choices', 'institution_stages', $connected ),
    ),
    array(
        array( 'appA' => 'WPCredits (appA)', 'appB' => 'appB' ),
        array_combine( $mentor_statuses, $mentor_statuses ),
        array_combine( $report_statuses, $report_statuses ),
        array_combine( $stages, $stages ),
    ) );

$statuses_from_airtable = array();

foreach ( $report_statuses as $status ) {
	if ( ! isset( $live[ $status ] ) ) {
		$statuses_from_airtable[ $status ] = $status;
	}
}

ck( 'and the status lists: the Students Reports statuses From Airtable, and every live track\'s From the Track Builder, each once',
    call( 'WPCPM_Settings_Choices', 'status_lists', $connected ),
    array( 'From Airtable' => $statuses_from_airtable, 'From the Track Builder' => array_combine( array_keys( $live ), array_keys( $live ) ) ) );

$assembled = array();

foreach ( array( 'WPCPM_Admin', 'WPCPM_Settings_Screen', 'WPCPM_Settings_Rows' ) as $screen_class ) {
	foreach ( array( 'named_ids', 'mentor_statuses', 'report_statuses', 'institution_stages', 'status_list_choices' ) as $reader ) {
		if ( method_exists( $screen_class, $reader ) ) {
			$assembled[] = $screen_class . '::' . $reader;
		}
	}
}

ck( 'and neither the screen nor the rows keep any of that assembly of their own', $assembled, array() );

airtable_answers( true );
call( 'WPCPM_Settings_Choices', 'tables' );
call( 'WPCPM_Settings_Choices', 'columns', $defaults['institutions_table'] );
call( 'WPCPM_Settings_Choices', 'columns', $defaults['teams_table'] );
call( 'WPCPM_Settings_Choices', 'columns', $defaults['countries_table'] );

ck( 'the tables and every table\'s columns are one read of the schema a request, not one a list',
    count( $GLOBALS['sent'] ), 1 );

airtable_answers( false );
$GLOBALS['sent'] = array();
call( 'WPCPM_Settings_Choices', 'tables' );
call( 'WPCPM_Settings_Choices', 'columns', $defaults['institutions_table'] );

ck( 'and a refused read is asked once a request too, not again for every list drawn from it',
    count( $GLOBALS['sent'] ), 1 );

airtable_answers( false );
call( 'WPCPM_Settings_Choices', 'tables' );
call( 'WPCPM_Settings_Choices', 'status_options', $defaults['mentors_table'], 'Status' );
call( 'WPCPM_Settings_Choices', 'status_options', $defaults['reports_table'], 'Status' );
call( 'WPCPM_Settings_Choices', 'status_options', $defaults['institutions_table'], 'Current Stage' );

ck( 'which holds for the status and stage columns too: one read of the schema a request, however many columns it is asked for',
    count( $GLOBALS['sent'] ), 1 );

airtable_answers( false );

ck( 'with Airtable refusing, each Airtable list answers nothing, and says nothing',
    array(
        call( 'WPCPM_Settings_Choices', 'bases' ),
        call( 'WPCPM_Settings_Choices', 'tables' ),
        call( 'WPCPM_Settings_Choices', 'columns', $defaults['institutions_table'] ),
        call( 'WPCPM_Settings_Choices', 'status_options', $defaults['mentors_table'], 'Status' ),
    ),
    array( array(), array(), array(), array() ) );

store( $connected );
$GLOBALS['manage'] = array();

ck( 'and with no track live and no account holding the capability, those two lists are empty too',
    array( call( 'WPCPM_Settings_Choices', 'tracks_statuses' ), call( 'WPCPM_Settings_Choices', 'managers' ) ),
    array( array(), array() ) );

$GLOBALS['manage'] = array( 1, 2, 3, 4 );

/* ---- the select ----------------------------------------------------------- */

echo "\n=== A select, grouped, always offering the value saved, or the text input when there is no list ===\n";

$flat = row( 'select_row', 'mentor_status', 'Mentor status to sync', 'Active', array( 'Interested' => 'Interested', 'Active' => 'Active' ), 'Only mentors holding this Airtable status get an account.', 'Type the status.' );

ck( 'select_row() draws a labeled select by the setting\'s name, each choice an option, the value saved chosen, and its help',
    array(
        null !== $flat && false !== strpos( $flat, '<label for="wpcpm-mentor_status">Mentor status to sync</label>' ),
        null !== $flat && false !== strpos( $flat, '<select id="wpcpm-mentor_status" name="mentor_status">' ),
        null === $flat ? null : select_options( $flat, 'mentor_status' ),
        null === $flat ? null : browser_post( $flat ),
        null !== $flat && false !== strpos( $flat, '<p class="description">Only mentors holding this Airtable status get an account.</p>' ),
        null !== $flat && false !== strpos( $flat, 'Type the status.' ),
    ),
    array( true, true, array( 'Interested' => 'Interested', 'Active' => 'Active' ), array( 'mentor_status' => 'Active' ), true, false ) );

$grouped = row( 'select_row', 'handbook_model', 'Model', 'b', array( 'First' => array( 'a' => 'A' ), 'Second' => array( 'b' => 'B', 'c' => 'C' ) ) );

ck( 'a group of choices is an option group of its own, labeled',
    array(
        null !== $grouped && false !== strpos( $grouped, '<optgroup label="First"><option value="a">A</option></optgroup>' ),
        null !== $grouped && false !== strpos( $grouped, '<optgroup label="Second"><option value="b" selected=\'selected\'>B</option><option value="c">C</option></optgroup>' ),
        null === $grouped ? null : browser_post( $grouped ),
    ),
    array( true, true, array( 'handbook_model' => 'b' ) ) );

$missing = row( 'select_row', 'mentors_table', 'Mentors table', 'tblGONE0000000001', array( 'tblA' => 'Mentors (tblA)' ) );
$blank   = row( 'select_row', 'base_id', 'Base ID', '', array( 'appA' => 'WPCredits (appA)' ) );

ck( 'a value saved that the list lacks is offered all the same, marked current and chosen, after the list, so a save cannot drop it',
    array(
        null === $missing ? null : select_options( $missing, 'mentors_table' ),
        null === $missing ? null : browser_post( $missing ),
        null === $blank ? null : select_options( $blank, 'base_id' ),
        null === $blank ? null : browser_post( $blank ),
    ),
    array(
        array( 'tblA' => 'Mentors (tblA)', 'tblGONE0000000001' => 'tblGONE0000000001 (current)' ),
        array( 'mentors_table' => 'tblGONE0000000001' ),
        array( 'appA' => 'WPCredits (appA)', '' => 'None (current)' ),
        array( 'base_id' => '' ),
    ) );

$escaped = row( 'select_row', 'mentor_status', 'Mentor status to sync', 'Say "hi" <b>', array( 'A & B' => 'A & B <i>' ), '' );

ck( 'every value and label is escaped',
    array( null !== $escaped && false !== strpos( $escaped, '<option value="A &amp; B">A &amp; B &lt;i&gt;</option>' ), null !== $escaped && false !== strpos( $escaped, 'value="Say &quot;hi&quot; &lt;b&gt;"' ), null !== $escaped && false === strpos( $escaped, '<b>' ) && false === strpos( $escaped, '<i>' ) ),
    array( true, true, true ) );

$fallback = row( 'select_row', 'mentor_status', 'Mentor status to sync', 'Active', array(), 'Only mentors holding this Airtable status get an account.', 'Type the status; the list needs a Personal Access Token with the <code>schema.bases:read</code> scope.' );

ck( 'with no list, select_row() draws the text input it stands in for, the same name and value, its help, and the sentence saying why there is no list',
    array(
        null !== $fallback && false !== strpos( $fallback, '<input type="text" id="wpcpm-mentor_status" name="mentor_status" value="Active" class="regular-text" autocomplete="off" />' ),
        null !== $fallback && false !== strpos( $fallback, '<select' ),
        null !== $fallback && false !== strpos( $fallback, '<p class="description">Only mentors holding this Airtable status get an account.</p>' ),
        null !== $fallback && false !== strpos( $fallback, '<p class="description wpcpm-settings__fallback">Type the status; the list needs a Personal Access Token with the <code>schema.bases:read</code> scope.</p>' ),
        null === $fallback ? null : browser_post( $fallback ),
    ),
    array( true, false, true, true, array( 'mentor_status' => 'Active' ) ) );

/* ---- the checkbox list ---------------------------------------------------- */

echo "\n=== A checkbox list, grouped, with locked boxes, or the textarea when there is no list ===\n";

$list = row(
	'checklist_row',
	'student_statuses',
	'Currently mentoring',
	array( 'Paused', 'In Sensei', 'Old status' ),
	array(
		'From Airtable'          => array( 'Paused' => 'Paused', 'Graduate' => 'Graduate' ),
		'From the Track Builder' => array( 'In Sensei' => 'In Sensei', 'Mentor Track' => 'Mentor Track' ),
	),
	'Students holding any of these appear under "Currently mentoring".',
	array( 'In Sensei' => 'Kept while WordPress Credits Program 150h is live in the Track Builder.', 'Mentor Track' => 'Cannot be ticked here.' ),
	'Type one status per line.'
);

ck( 'checklist_row() draws a box per choice by the setting\'s name, in groups under their names, the saved ones ticked, a saved value the lists lack ticked and marked current, after them',
    null === $list ? null : boxes_of( $list, 'student_statuses' ),
    array(
        'From Airtable'          => array( 'Paused' => array( 'Paused', true, false ), 'Graduate' => array( 'Graduate', false, false ) ),
        'From the Track Builder' => array( 'In Sensei' => array( 'In Sensei', true, true ), 'Mentor Track' => array( 'Mentor Track', false, true ) ),
        ''                       => array( 'Old status' => array( 'Old status (current)', true, false ) ),
    ) );

ck( 'a locked box is switched off with its note beside it, and a ticked one carries its value in a hidden field, since a switched-off box posts nothing',
    array(
        null !== $list && false !== strpos( $list, 'Kept while WordPress Credits Program 150h is live in the Track Builder.' ),
        null !== $list && false !== strpos( $list, 'Cannot be ticked here.' ),
        null === $list ? null : substr_count( $list, '<input type="hidden" name="student_statuses[]" value="In Sensei" />' ),
        null === $list ? null : substr_count( $list, 'type="hidden" name="student_statuses[]" value="Mentor Track"' ),
    ),
    array( true, true, 1, 0 ) );

ck( 'and each note is part of its box\'s label, so assistive technology reads it with the box',
    array(
        null !== $list && false !== strpos( $list, '<label><input type="checkbox" name="student_statuses[]" value="In Sensei" checked=\'checked\' disabled="disabled" /> In Sensei <span class="description">Kept while WordPress Credits Program 150h is live in the Track Builder.</span></label>' ),
        null !== $list && false !== strpos( $list, '<label><input type="checkbox" name="student_statuses[]" value="Mentor Track" disabled="disabled" /> Mentor Track <span class="description">Cannot be ticked here.</span></label>' ),
    ),
    array( true, true ) );

ck( 'and what a browser posts from it is the ticked boxes, the locked ones kept, after an empty value that makes an all-clear list still post',
    null === $list ? null : browser_post( $list ),
    array( 'student_statuses' => array( '', 'Paused', 'In Sensei', 'Old status' ) ) );

ck( 'the list is one labeled fieldset, with its help under it, and the fallback sentence nowhere while the list is drawn',
    array(
        null !== $list && false !== strpos( $list, '<fieldset><legend class="screen-reader-text">Currently mentoring</legend>' ),
        null !== $list && false !== strpos( $list, '<p class="description">Students holding any of these appear under "Currently mentoring".</p>' ),
        null !== $list && false !== strpos( $list, 'Type one status per line.' ),
    ),
    array( true, true, false ) );

// A group's note: said once, under the group's name, for every locked box in it that has no note of
// its own, each of which the note describes for assistive technology, since a switched-off box takes
// no focus.
$noted = row(
	'checklist_row',
	'past_statuses',
	'Past students',
	array( 'Graduate' ),
	array(
		'From Airtable'          => array( 'Graduate' => 'Graduate' ),
		'From the Track Builder' => array( 'In Sensei' => 'In Sensei', 'Mentor Track' => 'Mentor Track' ),
	),
	'Statuses that mean mentoring has finished.',
	array( 'In Sensei' => '', 'Mentor Track' => '' ),
	'',
	array(),
	'',
	array( 'From the Track Builder' => 'A live track\'s status cannot be a past status.' )
);

ck( 'a group\'s note is drawn once, under the group\'s name, and each locked box it speaks for has no note of its own and is described by it',
    null === $noted ? null : array(
        substr_count( $noted, '<fieldset class="wpcpm-settings__group"><legend>From the Track Builder</legend><p class="description" id="wpcpm-past_statuses-note-2">A live track&#039;s status cannot be a past status.</p>' ),
        substr_count( $noted, 'A live track&#039;s status cannot be a past status.' ),
        substr_count( $noted, '<span class="description">' ),
        substr_count( $noted, '<input type="checkbox" name="past_statuses[]" value="In Sensei" disabled="disabled" aria-describedby="wpcpm-past_statuses-note-2" /> In Sensei</label>' ),
        substr_count( $noted, 'aria-describedby=' ),
        boxes_of( $noted, 'past_statuses' ),
    ),
    array(
        1,
        1,
        0,
        1,
        2,
        array(
            'From Airtable'          => array( 'Graduate' => array( 'Graduate', true, false ) ),
            'From the Track Builder' => array( 'In Sensei' => array( 'In Sensei', false, true ), 'Mentor Track' => array( 'Mentor Track', false, true ) ),
        ),
    ) );

$flat_list = row( 'checklist_row', 'institution_active_stages', 'Institution pipeline stages', array( 'Confirmed' ), array( 'Info Sent' => 'Info Sent', 'Confirmed' => 'Confirmed' ) );

ck( 'a list of choices with no groups is one run of boxes',
    null === $flat_list ? null : boxes_of( $flat_list, 'institution_active_stages' ),
    array( '' => array( 'Info Sent' => array( 'Info Sent', false, false ), 'Confirmed' => array( 'Confirmed', true, false ) ) ) );

$area = row( 'checklist_row', 'past_statuses', 'Past students', array( 'Graduate', 'Dropped <out>' ), array(), 'Statuses that mean mentoring has finished.', array(), 'Type one status per line; the list needs a Personal Access Token with the <code>schema.bases:read</code> scope.' );

ck( 'with no list, checklist_row() draws the textarea it stands in for, the same name, one value a line, its help and the sentence saying why',
    array(
        null !== $area && 1 === preg_match( '#<textarea id="wpcpm-past_statuses" name="past_statuses" rows="\d+" class="regular-text">Graduate' . "\n" . 'Dropped &lt;out&gt;</textarea>#', $area ),
        null !== $area && false !== strpos( $area, 'type="checkbox"' ),
        null !== $area && false !== strpos( $area, '<p class="description wpcpm-settings__fallback">Type one status per line; the list needs a Personal Access Token with the <code>schema.bases:read</code> scope.</p>' ),
        null === $area ? null : browser_post( $area ),
    ),
    array( true, false, true, array( 'past_statuses' => "Graduate\nDropped <out>" ) ) );

/* ---- the reviewer lists ----------------------------------------------------- */

echo "\n=== A reviewer list: the program managers under Administrators, and a line for any other address ===\n";

store( $connected );
airtable_answers( true );

$reviewers = row( 'reviewers_row', 'agreement_notify', 'Who reviews agreements', 'zoe@program.example,board@partner.example,ana@program.example', 'Addresses told when an agreement arrives.' );

ck( 'reviewers_row() draws a box per program manager, all under Administrators, each named with the address the box saves, the saved ones ticked whatever case the account spells its address in',
    null === $reviewers ? null : boxes_of( $reviewers, 'agreement_notify' ),
    array(
        'Administrators' => array(
            'Ana@Program.example' => array( 'Ana Admin (Ana@Program.example)', true, false ),
            'pat@program.example' => array( 'Pat Coordinator (pat@program.example)', false, false ),
            'zoe@program.example' => array( 'Zoe Admin (zoe@program.example)', true, false ),
        ),
    ) );

ck( 'and puts every saved address that is nobody\'s account on the Other addresses line, which posts with the boxes',
    array(
        null !== $reviewers && false !== strpos( $reviewers, '<label for="wpcpm-agreement_notify-other">Other addresses</label>' ),
        null === $reviewers ? null : browser_post( $reviewers ),
    ),
    array( true, array( 'agreement_notify' => array( 'Ana@Program.example', 'zoe@program.example', 'board@partner.example' ) ) ) );

$GLOBALS['manage'] = array();
$typed             = row( 'reviewers_row', 'agreement_notify', 'Who reviews agreements', 'zoe@program.example,board@partner.example', 'Addresses told when an agreement arrives.' );
$GLOBALS['manage'] = array( 1, 2, 3, 4 );

ck( 'with no program manager to list, the text input it stands in for, the same name and value, and the sentence saying why',
    array(
        null !== $typed && false !== strpos( $typed, 'type="checkbox"' ),
        null === $typed ? null : browser_post( $typed ),
        null !== $typed && false !== strpos( $typed, '<p class="description wpcpm-settings__fallback">Type the addresses, separated by commas. No program manager account with an email address was found.</p>' ),
        null !== $typed && false !== strpos( $typed, ' placeholder="one@example.org, two@example.org"' ),
    ),
    array( false, array( 'agreement_notify' => 'zoe@program.example,board@partner.example' ), true, true ) );

/* ---- each tab, with the lists answering ------------------------------------- */

echo "\n=== Each tab draws its choices from their lists ===\n";

$stored_now = array_merge(
	$connected,
	array(
		'agreement_notify' => 'zoe@program.example,board@partner.example',
		'report_notify'    => 'pat@program.example',
		'sponsor_notify'   => '',
	)
);

store( $stored_now, $live );
airtable_answers( true );

$connection   = form_of( draw_settings( 'connection' ) );
$named_tables = array();

foreach ( $table_names as $id => $name ) {
	$named_tables[ $id ] = $name . ' (' . $id . ')';
}

$table_selects = array();

foreach ( array( 'mentors_table', 'reports_table', 'students_table', 'institutions_table', 'teams_table', 'tutors_table', 'feedback_table', 'countries_table', 'team_members_table', 'sponsors_table' ) as $key ) {
	$table_selects[ $key ] = select_options( $connection, $key );
}

ck( 'the Connection tab draws the base as a select of the bases the token can open, the ID beside each name',
    select_options( $connection, 'base_id' ), array( $base => 'WPCredits (' . $base . ')', 'appPROBE000000002' => 'Sandbox (appPROBE000000002)' ) );
ck( 'and each of the ten tables as a select of the base\'s tables, the ID beside each name',
    $table_selects, array_fill_keys( array_keys( $table_selects ), $named_tables ) );
// A blank name column is not nothing for three of them: the institutions sync, the countries and the
// sponsors sync each read a column of their own when it is blank, so each offers None, first. The
// Contribution areas name column has no column of its own to fall back on.
ck( 'and each name column as a select of its own table\'s columns, the three whose readers fall back on a column of their own offering None first',
    array(
        select_options( $connection, 'institutions_name_field' ),
        select_options( $connection, 'teams_name_field' ),
        select_options( $connection, 'countries_name_field' ),
        select_options( $connection, 'sponsors_name_field' ),
    ),
    array(
        array( '' => 'None (the built-in column)', 'Name' => 'Name', 'Country' => 'Country', 'Current Stage' => 'Current Stage' ),
        array( 'Contribution teams or areas' => 'Contribution teams or areas', 'Description' => 'Description' ),
        array( '' => 'None (the built-in column)', 'Name' => 'Name', 'Program manager' => 'Program manager' ),
        array( '' => 'None (the built-in column)', 'Company Name' => 'Company Name', 'Status' => 'Status' ),
    ) );

$none_chosen = browser_post( $connection );
$none_saves  = array();

foreach ( array( 'institutions_name_field', 'countries_name_field', 'sponsors_name_field' ) as $key ) {
	store( $stored_now, $live );
	press( array_merge( $none_chosen, array( $key => '' ) ) );

	$none_saves[ $key ] = WPCPM_Settings::get()[ $key ];
}

store( array_merge( $stored_now, array( 'sponsors_name_field' => '' ) ), $live );
airtable_answers( true );
$none_drawn = form_of( draw_settings( 'connection' ) );

ck( 'and None saves the column blank, which a blank stored draws chosen, not as a current value the list lacks',
    array( $none_saves, attrs( preg_match( '#<option value=""[^>]*>#', (string) preg_replace( '#^.*?name="sponsors_name_field">#s', '', $none_drawn ), $none_option ) ? $none_option[0] : '' ), substr_count( $none_drawn, 'None (current)' ) ),
    array( array_fill_keys( array( 'institutions_name_field', 'countries_name_field', 'sponsors_name_field' ), '' ), array( 'option' => '', 'value' => '', 'selected' => 'selected' ), 0 ) );

// A table stored by its name, as one typed before the tables were chosen from a list could be. The
// mentors sync knows a table's primary column by the table's ID alone, so for this table it asks for
// the name column itself, and a blank one would be a column with no name: the name column is the text
// input it was, holding the name saved, with no None to offer. Chosen from the list, which stores the
// ID, the table's name column offers None first.
store( array_merge( $stored_now, array( 'institutions_table' => 'Institutions', 'sponsors_table' => 'Sponsors' ) ), $live );
airtable_answers( true );
$tables_by_name = form_of( draw_settings( 'connection' ) );
store( $stored_now, $live );
airtable_answers( true );
$tables_by_id = form_of( draw_settings( 'connection' ) );

ck( 'a name column whose table is stored by its name is the text input it was, holding the name saved, and offers no None, while one whose table is stored by its ID offers None first',
    array(
        select_options( $tables_by_name, 'institutions_name_field' ),
        drawn_input( $tables_by_name, 'institutions_name_field' ),
        select_options( $tables_by_name, 'sponsors_name_field' ),
        drawn_input( $tables_by_name, 'sponsors_name_field' ),
        array_slice( (array) select_options( $tables_by_id, 'institutions_name_field' ), 0, 1, true ),
        array_slice( (array) select_options( $tables_by_id, 'sponsors_name_field' ), 0, 1, true ),
    ),
    array( null, $stored_now['institutions_name_field'], null, $stored_now['sponsors_name_field'], array( '' => 'None (the built-in column)' ), array( '' => 'None (the built-in column)' ) ) );

store( $stored_now, $live );
airtable_answers( true );

$connection_post = browser_post( $connection );
$connection_keys = array( 'base_id', 'mentors_table', 'reports_table', 'students_table', 'institutions_table', 'teams_table', 'tutors_table', 'feedback_table', 'countries_table', 'team_members_table', 'sponsors_table', 'institutions_name_field', 'teams_name_field', 'countries_name_field', 'sponsors_name_field' );

$posted_values = array();
$saved_values  = array();

foreach ( $connection_keys as $key ) {
	$posted_values[ $key ] = isset( $connection_post[ $key ] ) ? $connection_post[ $key ] : null;
	$saved_values[ $key ]  = $stored_now[ $key ];
}

ck( 'and each posts, untouched, exactly the value saved',
    $posted_values, $saved_values );

$people = form_of( draw_settings( 'people' ) );
$from_airtable_current = array();
$from_airtable_past    = array();

foreach ( $report_statuses as $status ) {
	if ( ! isset( $live[ $status ] ) ) {
		$from_airtable_current[ $status ] = array( $status, in_array( $status, $defaults['student_statuses'], true ), false );
		$from_airtable_past[ $status ]    = array( $status, in_array( $status, $defaults['past_statuses'], true ), false );
	}
}

$tracks_current = array();
$tracks_past    = array();

foreach ( $live as $status => $track ) {
	$held                      = in_array( $status, $defaults['student_statuses'], true );
	$tracks_current[ $status ] = array( $status, $held, $held );
	$tracks_past[ $status ]    = array( $status, false, true );
}

ck( 'the Students and mentors tab draws Currently mentoring as boxes From Airtable, the Students Reports statuses, and From the Track Builder, every live track\'s status, each once, a status the list holds and a track runs on ticked and locked',
    boxes_of( $people, 'student_statuses' ),
    array( 'From Airtable' => $from_airtable_current, 'From the Track Builder' => $tracks_current ) );

ck( 'and each locked box names the track it is kept for',
    array(
        false !== strpos( $people, 'Kept while WordPress Credits Program 150h is live in the Track Builder.' ),
        false !== strpos( $people, 'Kept while Designer Track is live in the Track Builder.' ),
        false !== strpos( $people, 'Kept while Mentor Track is live in the Track Builder.' ),
    ),
    array( true, true, false ) );

ck( 'and Past students the same way, a live track\'s status locked unticked, since no track runs on a past status',
    boxes_of( $people, 'past_statuses' ),
    array( 'From Airtable' => $from_airtable_past, 'From the Track Builder' => $tracks_past ) );

// Every box under From the Track Builder in Past students is a live track's status, and each is
// locked: one note for the group says why, where a note a box said the same thing five times over.
// Currently mentoring's locked boxes each still name their track.
$past_row = preg_match( '#<tr><th scope="row">Past students</th><td>.*?</td></tr>#s', $people, $found ) ? $found[0] : '';

ck( 'and says once, under the From the Track Builder legend, that a live track\'s status cannot be a past status, each locked box described by it and none with a note of its own',
    array(
        substr_count( $past_row, '<legend>From the Track Builder</legend><p class="description" id="wpcpm-past_statuses-note-2">A live track&#039;s status cannot be a past status.</p>' ),
        substr_count( $past_row, '<span class="description">' ),
        substr_count( $past_row, 'disabled="disabled"' ),
        substr_count( $past_row, 'disabled="disabled" aria-describedby="wpcpm-past_statuses-note-2"' ),
        substr_count( $past_row, 'is live in the Track Builder' ),
    ),
    array( 1, 0, count( $live ), count( $live ), 0 ) );

ck( 'and the mentor status as a select of the Mentors table\'s statuses',
    select_options( $people, 'mentor_status' ), array_combine( $mentor_statuses, $mentor_statuses ) );

ck( 'and still carries Currently mentoring as it drew it, one hidden field a status',
    substr_count( $people, 'name="' . WPCPM_Settings::FIELD_DRAWN_STATUSES . '[]"' ), count( $defaults['student_statuses'] ) );

$GLOBALS['user_queries'] = 0;
$institutions            = form_of( draw_settings( 'institutions' ) );
$managers_asked          = $GLOBALS['user_queries'];
$sponsors                = form_of( draw_settings( 'sponsors' ) );
$managers_box            = function ( array $ticked ) {
	return array(
		'Administrators' => array(
			'Ana@Program.example' => array( 'Ana Admin (Ana@Program.example)', in_array( 'Ana@Program.example', $ticked, true ), false ),
			'pat@program.example' => array( 'Pat Coordinator (pat@program.example)', in_array( 'pat@program.example', $ticked, true ), false ),
			'zoe@program.example' => array( 'Zoe Admin (zoe@program.example)', in_array( 'zoe@program.example', $ticked, true ), false ),
		),
	);
};

ck( 'the Institutions tab draws both reviewer lists, and the Sponsors tab its interest mail, as the program managers under Administrators, the saved ones ticked',
    array( boxes_of( $institutions, 'agreement_notify' ), boxes_of( $institutions, 'report_notify' ), boxes_of( $sponsors, 'sponsor_notify' ) ),
    array( $managers_box( array( 'zoe@program.example' ) ), $managers_box( array( 'pat@program.example' ) ), $managers_box( array() ) ) );

ck( 'each with the saved addresses nobody\'s account holds on its Other addresses line',
    array( browser_post( $institutions )['agreement_notify'] ?? null, browser_post( $institutions )['report_notify'] ?? null, browser_post( $sponsors )['sponsor_notify'] ?? null ),
    array( array( 'zoe@program.example', 'board@partner.example' ), array( 'pat@program.example', '' ), array( '' ) ) );

ck( 'and the program managers are asked for once a draw, though the Institutions tab lists them twice',
    $managers_asked, 1 );

// A live track's status as publishing appends it, trimmed alone, and its copy in the list as a Save
// leaves it, sanitized: a run of spaces folded into one, a "<" that opens no tag kept as `&lt;`. The
// screen locks it by the form the list keeps it in, as the save's own rule judges it
// (`WPCPM_Settings::tracks_dropped_by()`), so the box it draws is the box the save guards.
$odd_live   = array(
	'In Sensei'       => 'WordPress Credits Program 150h',
	'In Sensei 50h'   => 'WordPress Credits Program 50h',
	'Developer Track' => 'Developer Track',
	'Designer Track'  => 'Designer Track',
	'Writing  Track'  => 'Writing Track',
	'Less < More'     => 'Less or More Track',
);
$odd_drawn  = array();
$odd_saved  = array();
$odd_wanted = array();

foreach ( array(
	'as a Save leaves it'      => array( 'Writing Track', 'Less &lt; More' ),
	'as publishing appends it' => array( 'Writing  Track', 'Less < More' ),
) as $case => $held ) {
	$odd                     = $stored_now;
	$odd['student_statuses'] = array_merge( $defaults['student_statuses'], $held );

	store( $odd, $odd_live );
	airtable_answers( true );

	$odd_form = form_of( draw_settings( 'people' ) );
	$odd_box  = boxes_of( $odd_form, 'student_statuses' );

	$odd_drawn[ $case ] = array(
		isset( $odd_box['From the Track Builder'] ) ? array_slice( $odd_box['From the Track Builder'], 4, null, true ) : null,
		isset( $odd_box[''] ) ? $odd_box[''] : array(),
	);

	press( browser_post( $odd_form ) );

	$odd_saved[ $case ]  = array( queued( 'settings-refused' ), WPCPM_Settings::get()['student_statuses'] );
	$odd_wanted[ $case ] = array( null, $odd['student_statuses'] );
}

// Each box's value is the list's own form of the status, which a browser reads back as the status
// itself ("Less &lt; More" as "Less < More") and a Save turns into the list's form again.
ck( 'a live track\'s status holding a run of spaces or a "<" is drawn ticked and locked in the form the list keeps it in, not beside a current copy of itself, whichever form the list holds it in',
    $odd_drawn,
    array_fill_keys(
        array( 'as a Save leaves it', 'as publishing appends it' ),
        array(
            array(
                'Writing Track' => array( 'Writing  Track', true, true ),
                'Less < More'   => array( 'Less < More', true, true ),
            ),
            array(),
        )
    ) );
ck( 'and a Save of that page changes nothing and is refused nothing',
    $odd_saved, $odd_wanted );

// Past students holding a live track's status in the list's own form, which neither the refusal nor
// the compile folds to the track's spelling, so the track stays live: the box is ticked, and it is
// free, since taking a status out of Past students is never refused. Locked, it could not be
// unticked at all.
$past_edge                  = $stored_now;
$past_edge['past_statuses'] = array_merge( $defaults['past_statuses'], array( 'Less &lt; More' ) );

store( $past_edge, $odd_live );
airtable_answers( true );

$past_edge_boxes = boxes_of( form_of( draw_settings( 'people' ) ), 'past_statuses' );

press( tab_post( 'people', array( 'past_statuses' => array( 'Less &lt; More' ) ) ) );

ck( 'a live track\'s status Past students holds in the list\'s own form, which the track rules do not fold to the track\'s spelling, is a ticked box left free, and unticking it takes it out, refused nothing',
    array( isset( $past_edge_boxes['From the Track Builder']['Less < More'] ) ? $past_edge_boxes['From the Track Builder']['Less < More'] : null, WPCPM_Settings::get()['past_statuses'], queued( 'settings-past-refused' ) ),
    array( array( 'Less < More', true, false ), $defaults['past_statuses'], null ) );

store( $stored_now, $live );
airtable_answers( true );

$advanced = form_of( draw_settings( 'advanced' ) );

ck( 'the Advanced tab draws the starting stage as a select, and the pipeline stages as boxes, of the Institutions table\'s stages',
    array( select_options( $advanced, 'institution_new_stage' ), boxes_of( $advanced, 'institution_active_stages' ) ),
    array(
        array_combine( $stages, $stages ),
        array( '' => array_combine( $stages, array_map( function ( $stage ) use ( $defaults ) { return array( $stage, in_array( $stage, $defaults['institution_active_stages'], true ), false ); }, $stages ) ) ),
    ) );

// The two tools with a choice among their settings draw them in the Settings section of their own
// screens, from the same lists.
$checker  = draw_tool( 'tool:mentor-status-checker' );
$handbook = draw_tool( 'tool:handbook' );

ck( 'the Mentor Status Checker\'s Settings section draws its two statuses as selects of the Mentors table\'s statuses',
    array( select_options( $checker, 'checker_source_status' ), select_options( $checker, 'checker_target_status' ) ),
    array_fill( 0, 2, array_combine( $mentor_statuses, $mentor_statuses ) ) );

// The provider lists no models, so the only list there could be is the default the plugin ships,
// which offers no choice: the model stays the text input it always was.
ck( 'and Need help?\'s Settings section draws the model as the text input it always was, holding the model saved, since the provider lists no models to choose from',
    array( select_options( $handbook, 'handbook_model' ), false !== strpos( $handbook, '<input type="text" id="wpcpm-handbook_model" name="handbook_model" value="' . esc_attr( $defaults['handbook_model'] ) . '"' ) ),
    array( null, true ) );

ck( 'and the checker\'s course title and slug as the text inputs they were, since learn.wordpress.org has no list to read',
    array(
        false !== strpos( $checker, '<input type="text" id="wpcpm-checker_course_title" name="checker_course_title" value="' . esc_attr( $defaults['checker_course_title'] ) . '"' ),
        false !== strpos( $checker, '<input type="text" id="wpcpm-checker_course_slug" name="checker_course_slug" value="' . esc_attr( $defaults['checker_course_slug'] ) . '"' ),
        select_options( $checker, 'checker_course_title' ),
        select_options( $checker, 'checker_course_slug' ),
    ),
    array( true, true, null, null ) );

$drawn_scopes = array();
$want_scopes  = array();

foreach ( array( 'connection', 'people', 'institutions', 'sponsors', 'advanced', 'tool:mentor-status-checker', 'tool:handbook' ) as $tab ) {
	$own = WPCPM_Settings::scope_keys( $tab );
	sort( $own );

	$drawn_scopes[ $tab ] = names_of( form_for( $tab ) );
	$want_scopes[ $tab ]  = $own;
}

ck( 'with every list drawn, each tab\'s form and each tool\'s Settings section still holds exactly the settings its Save writes, nothing posted under another name',
    $drawn_scopes, $want_scopes );

/* ---- each tab, with the lists refused ---------------------------------------- */

echo "\n=== With nothing to list, every field is the text input it was, the same name and value, saying why ===\n";

store( $stored_now, $live );
airtable_answers( false );
$GLOBALS['manage'] = array();

$GLOBALS['sent']   = array();
$refused_people    = form_of( draw_settings( 'people' ) );
$people_asked      = count( $GLOBALS['sent'] );

$refused = array(
	'connection'                 => form_of( draw_settings( 'connection' ) ),
	'people'                     => $refused_people,
	'institutions'               => form_of( draw_settings( 'institutions' ) ),
	'sponsors'                   => form_of( draw_settings( 'sponsors' ) ),
	'advanced'                   => form_of( draw_settings( 'advanced' ) ),
	'tool:mentor-status-checker' => form_for( 'tool:mentor-status-checker' ),
);

$GLOBALS['manage'] = array( 1, 2, 3, 4 );

$typed_fields = array(
	'connection'                 => array_merge( $connection_keys ),
	'people'                     => array( 'mentor_status', 'student_statuses', 'past_statuses' ),
	'institutions'               => array( 'agreement_notify', 'report_notify' ),
	'sponsors'                   => array( 'sponsor_notify' ),
	'advanced'                   => array( 'institution_new_stage', 'institution_active_stages' ),
	'tool:mentor-status-checker' => array( 'checker_source_status', 'checker_target_status' ),
);
$as_typed = array();
$want     = array();

foreach ( $typed_fields as $tab => $keys ) {
	$posted = browser_post( $refused[ $tab ] );

	foreach ( $keys as $key ) {
		$value            = $stored_now[ $key ];
		$as_typed[ $key ] = array( select_options( $refused[ $tab ], $key ), isset( $posted[ $key ] ) ? $posted[ $key ] : null );
		$want[ $key ]     = array( null, is_array( $value ) ? implode( "\n", $value ) : (string) $value );
	}
}

ck( 'every field drawn from a list is drawn as the text input or textarea it replaced, posting the value saved as it was typed',
    $as_typed, $want );

ck( 'and the Students and mentors tab asked a refusing Airtable once, for both of its status columns',
    $people_asked, 1 );

ck( 'and no box is drawn for any of them',
    array(
        boxes_of( $refused['people'], 'student_statuses' ),
        boxes_of( $refused['people'], 'past_statuses' ),
        boxes_of( $refused['institutions'], 'agreement_notify' ),
        boxes_of( $refused['advanced'], 'institution_active_stages' ),
    ),
    array( array(), array(), array(), array() ) );

$sentences = array(
	'Type the ID. The list could not be read from Airtable just now.'                                         => 11,
	'Type the column\'s name. The list could not be read from Airtable just now.'                             => 4,
	'Type the status. The list could not be read from Airtable just now.'                                     => 3,
	'Type one status per line. The list could not be read from Airtable just now.'                            => 2,
	'Type the stage. The list could not be read from Airtable just now.'                                      => 1,
	'Type one stage per line. The list could not be read from Airtable just now.'                             => 1,
	'Type the addresses, separated by commas. No program manager account with an email address was found.'  => 3,
);
$said = array();

foreach ( array_keys( $sentences ) as $sentence ) {
	$said[ $sentence ] = substr_count( implode( '', $refused ), '<p class="description wpcpm-settings__fallback">' . $sentence . '</p>' );
}

ck( 'and each says what to type, and in one sentence why its list is not there: here, a read Airtable refused',
    $said, $sentences );

/* ---- why a list is not there ---------------------------------------------------- */

echo "\n=== A list that cannot be read says why, one sentence a cause ===\n";

/**
 * The sentence a field drawn as a text input says about its missing list, as a browser shows it
 * (its `<code>` tags kept), or null for none.
 *
 * @param string $html The screen.
 * @param string $name The field's name.
 * @return string|null
 */
function fallback_of( $html, $name ) {
	preg_match_all( '#<tr>.*?</tr>#s', $html, $rows );

	foreach ( $rows[0] as $row ) {
		if ( false !== strpos( $row, ' name="' . $name . '"' ) ) {
			return preg_match( '#<p class="description wpcpm-settings__fallback">(.*?)</p>#s', $row, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null;
		}
	}

	return null;
}

/**
 * Draw a tab with these settings and Airtable answering these routes, and read what a field says.
 *
 * @param array  $changes Settings changed from the stored ones.
 * @param array  $routes  Routes changed from Airtable's.
 * @param string $tab     The tab.
 * @param string $name    The field.
 * @return string|null
 */
function why_for( array $changes, array $routes, $tab, $name ) {
	global $stored_now, $live;

	$kept_routes       = $GLOBALS['routes'];
	$GLOBALS['routes'] = array_merge( $GLOBALS['routes'], $routes );

	store( array_merge( $stored_now, $changes ), $live );
	airtable_answers( true );

	$said              = fallback_of( draw_settings( $tab ), $name );
	$GLOBALS['routes'] = $kept_routes;

	return $said;
}

$refusal   = function ( $code, $type ) {
	return response( $code, array( 'error' => array( 'type' => $type ) ) );
};
$no_status = $schema_body;
$as_text   = $schema_body;

// The Mentors table without its Status column, and with a Status column of plain text.
$no_status['tables'][0]['fields'] = array_slice( $no_status['tables'][0]['fields'], 0, 2 );
$as_text['tables'][0]['fields'][2] = array( 'id' => 'fldMSTATUS00001', 'name' => 'Status', 'type' => 'singleLineText' );

$GLOBALS['manage'] = array();
$no_managers       = why_for( array(), array(), 'institutions', 'agreement_notify' );
$GLOBALS['manage'] = array( 1, 2, 3, 4 );

ck( 'each cause is named in its own true sentence, after what to type',
    array(
        'no token, the base'             => why_for( array( 'api_token' => '' ), array(), 'connection', 'base_id' ),
        'no token, a table'              => why_for( array( 'api_token' => '' ), array(), 'connection', 'mentors_table' ),
        'no token, the statuses'         => why_for( array( 'api_token' => '' ), array(), 'people', 'student_statuses' ),
        'no Base ID, a table'            => why_for( array( 'base_id' => '' ), array(), 'connection', 'mentors_table' ),
        'the token refused, the base'    => why_for( array(), array( $bases_url => $refusal( 401, 'AUTHENTICATION_REQUIRED' ) ), 'connection', 'base_id' ),
        'the token refused, a table'     => why_for( array(), array( $schema_url => $refusal( 401, 'AUTHENTICATION_REQUIRED' ) ), 'connection', 'mentors_table' ),
        'no scope, the base'             => why_for( array(), array( $bases_url => $refusal( 403, 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ), 'connection', 'base_id' ),
        'no scope, a table'              => why_for( array(), array( $schema_url => $refusal( 403, 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ), 'connection', 'mentors_table' ),
        'no bases to open'               => why_for( array(), array( $bases_url => response( 200, array( 'bases' => array() ) ) ), 'connection', 'base_id' ),
        'a table the base lacks'         => why_for( array( 'institutions_table' => 'tblNOSUCHTABLE0001' ), array(), 'connection', 'institutions_name_field' ),
        'the same, for the stages'       => why_for( array( 'institutions_table' => 'tblNOSUCHTABLE0001' ), array(), 'advanced', 'institution_new_stage' ),
        'no table set'                   => why_for( array( 'mentors_table' => '' ), array(), 'people', 'mentor_status' ),
        'no Status column'               => why_for( array(), array( $schema_url => response( 200, $no_status ) ), 'people', 'mentor_status' ),
        'a Status column of plain text'  => why_for( array(), array( $schema_url => response( 200, $as_text ) ), 'people', 'mentor_status' ),
        'Airtable failing'               => why_for( array(), array( $schema_url => $refusal( 503, 'SERVICE_UNAVAILABLE' ) ), 'connection', 'mentors_table' ),
        'nobody to list'                 => $no_managers,
    ),
    array(
        'no token, the base'             => 'Type the ID. No Personal Access Token is set, so the list cannot be read from Airtable.',
        'no token, a table'              => 'Type the ID. No Personal Access Token is set, so the list cannot be read from Airtable.',
        'no token, the statuses'         => 'Type one status per line. No Personal Access Token is set, so the list cannot be read from Airtable.',
        'no Base ID, a table'            => 'Type the ID. No Base ID is set, so the base\'s tables cannot be read from Airtable.',
        'the token refused, the base'    => 'Type the ID. Airtable refused the Personal Access Token, so the list cannot be read.',
        'the token refused, a table'     => 'Type the ID. Airtable refused the Personal Access Token, so the list cannot be read.',
        'no scope, the base'             => 'Type the ID. Airtable refused the read: the Personal Access Token needs the <code>schema.bases:read</code> scope.',
        'no scope, a table'              => 'Type the ID. Airtable refused the read: the Personal Access Token needs the <code>schema.bases:read</code> scope and access to the base.',
        'no bases to open'               => 'Type the ID. Airtable lists no base the Personal Access Token can open.',
        'a table the base lacks'         => 'Type the column\'s name. The base has no table tblNOSUCHTABLE0001.',
        'the same, for the stages'       => 'Type the stage. The base has no table tblNOSUCHTABLE0001.',
        'no table set'                   => 'Type the status. No table is set.',
        'no Status column'               => 'Type the status. The Mentors table has no Status column.',
        'a Status column of plain text'  => 'Type the status. The Mentors table\'s Status column holds no options to choose from.',
        'Airtable failing'               => 'Type the ID. The list could not be read from Airtable just now.',
        'nobody to list'                 => 'Type the addresses, separated by commas. No program manager account with an email address was found.',
    ) );

/* ---- what a choice saves ------------------------------------------------------ */

echo "\n=== What a choice saves is what its text input saved for the same string ===\n";

/**
 * Save a tab as a browser posts it, with some fields changed, and read back what was stored.
 *
 * @param string $tab     The tab.
 * @param bool   $answers Whether Airtable, and the program managers, answer while it is drawn.
 * @param array  $changes Name => what the field is changed to before Save.
 * @return array The settings stored.
 */
function save_tab( $tab, $answers, array $changes ) {
	global $stored_now, $live;

	store( $stored_now, $live );
	airtable_answers( $answers );

	$kept_managers = $GLOBALS['manage'];

	if ( ! $answers ) {
		$GLOBALS['manage'] = array();
	}

	$html              = form_for( $tab );
	$GLOBALS['manage'] = $kept_managers;

	press( array_merge( browser_post( $html ), $changes ) );

	return WPCPM_Settings::get();
}

$select_saves = array();
$text_saves   = array();
$offered_all  = array();

foreach ( array(
	'connection'                 => array(
		'base_id'                 => 'appPROBE000000002',
		'mentors_table'           => $defaults['students_table'],
		'reports_table'           => $defaults['feedback_table'],
		'institutions_name_field' => 'Country',
		'teams_name_field'        => 'Description',
	),
	'people'                     => array( 'mentor_status' => 'Vetted - positive' ),
	'advanced'                   => array( 'institution_new_stage' => 'Info Sent' ),
	'tool:mentor-status-checker' => array( 'checker_source_status' => 'Interested', 'checker_target_status' => 'Needs second opinion' ),
) as $tab => $changes ) {
	store( $stored_now, $live );
	airtable_answers( true );
	$drawn = form_for( $tab );

	foreach ( $changes as $key => $value ) {
		$offered_all[ $key ] = array_key_exists( $value, (array) select_options( $drawn, $key ) );
	}

	$chosen = save_tab( $tab, true, $changes );
	$typed  = save_tab( $tab, false, $changes );

	foreach ( $changes as $key => $value ) {
		$select_saves[ $key ] = $chosen[ $key ];
		$text_saves[ $key ]   = $typed[ $key ];
	}
}

ck( 'each value chosen is one the select offers',
    $offered_all, array_fill_keys( array_keys( $offered_all ), true ) );
ck( 'and each saves the very string its text input saves, the base, the tables, the columns, the statuses and the stage',
    $select_saves, $text_saves );
ck( 'which is the string itself',
    $select_saves,
    array(
        'base_id'                 => 'appPROBE000000002',
        'mentors_table'           => $defaults['students_table'],
        'reports_table'           => $defaults['feedback_table'],
        'institutions_name_field' => 'Country',
        'teams_name_field'        => 'Description',
        'mentor_status'           => 'Vetted - positive',
        'institution_new_stage'   => 'Info Sent',
        'checker_source_status'   => 'Interested',
        'checker_target_status'   => 'Needs second opinion',
    ) );

$model_saves = save_tab( 'tool:mentor-status-checker', true, array() );

ck( 'and a Save that changes no select leaves each as it was stored, a model the list does not name included',
    array( save_tab( 'connection', true, array() )['base_id'], save_tab( 'people', true, array() )['mentor_status'], $model_saves['checker_source_status'] ),
    array( $stored_now['base_id'], $stored_now['mentor_status'], $stored_now['checker_source_status'] ) );

$stored_now['handbook_model'] = 'gemini-2.5-flash';
store( $stored_now, $live );
airtable_answers( true );
$handbook_card = form_for( 'tool:handbook' );
press( browser_post( $handbook_card ) );
$model_kept    = WPCPM_Settings::get()['handbook_model'];
store( $stored_now, $live );
press( array_merge( browser_post( $handbook_card ), array( 'handbook_model' => 'gemini-2.0-flash-lite' ) ) );

ck( 'a model other than the default is typed in the field as saved, a Save keeps it, and another typed is saved as typed',
    array( select_options( $handbook_card, 'handbook_model' ), drawn_input( $handbook_card, 'handbook_model' ), $model_kept, WPCPM_Settings::get()['handbook_model'] ),
    array( null, 'gemini-2.5-flash', 'gemini-2.5-flash', 'gemini-2.0-flash-lite' ) );

$stored_now['handbook_model'] = $defaults['handbook_model'];

/* ---- the status lists ---------------------------------------------------------- */

echo "\n=== The status lists save from their boxes what their textareas saved ===\n";

/**
 * The Students and mentors tab as drawn, and what a browser posts from it with some boxes changed.
 *
 * @param array $untick Name => the values unticked.
 * @param array $tick   Name => the values ticked.
 * @return array The request.
 */
function people_post( array $untick = array(), array $tick = array() ) {
	$html = form_of( draw_settings( 'people' ) );

	foreach ( $untick as $name => $values ) {
		foreach ( $values as $value ) {
			$html = str_replace( 'name="' . $name . '[]" value="' . esc_attr( $value ) . '" checked=\'checked\'', 'name="' . $name . '[]" value="' . esc_attr( $value ) . '"', $html );
		}
	}

	foreach ( $tick as $name => $values ) {
		foreach ( $values as $value ) {
			$html = str_replace( 'name="' . $name . '[]" value="' . esc_attr( $value ) . '" ', 'name="' . $name . '[]" value="' . esc_attr( $value ) . '" checked=\'checked\' ', $html );
		}
	}

	return browser_post( $html );
}

// From the boxes: Paused unticked, Graduate ticked in Currently mentoring; Withdrawn is not in the
// list, so Dropped out is unticked in Past students and Fail ticked.
store( $stored_now, $live );
airtable_answers( true );
$compiled_then = WPCPM_Track_Store::$compiled;
press( people_post( array( 'student_statuses' => array( 'Paused' ), 'past_statuses' => array( 'Dropped out' ) ), array( 'student_statuses' => array( 'Graduate' ), 'past_statuses' => array( 'Fail' ) ) ) );
$from_boxes    = array( WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['past_statuses'] );
$compiled_by_boxes = WPCPM_Track_Store::$compiled - $compiled_then;

// From the textareas, the same statuses typed one a line, in the order the boxes' save writes them:
// the stored ones where they stood, the newly ticked one after them.
store( $stored_now, $live );
airtable_answers( false );
$typed_post                     = browser_post( form_of( draw_settings( 'people' ) ) );
$typed_post['student_statuses'] = implode( "\n", array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Pending graduation', 'Graduate' ) );
$typed_post['past_statuses']    = "Graduate\nFail";
press( $typed_post );
$from_text = array( WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['past_statuses'] );

ck( 'Currently mentoring and Past students saved from their boxes are the lists their textareas saved for the same statuses, the same text one a line',
    array( implode( "\n", $from_boxes[0] ), implode( "\n", $from_boxes[1] ), $from_boxes ),
    array( implode( "\n", $from_text[0] ), implode( "\n", $from_text[1] ), $from_text ) );
ck( 'which hold the change in the order the lists were stored in: Paused out and Graduate in after the rest, Dropped out past no longer and Fail past',
    $from_boxes,
    array( array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Pending graduation', 'Graduate' ), array( 'Graduate', 'Fail' ) ) );
ck( 'and the save compiled the tracks once, since both lists changed',
    $compiled_by_boxes, 1 );

// Nothing changed: the boxes post in the order the lists draw them, which is not the order the list
// is stored in, and a page left open while another administrator took a status out must not put it back.
$stored_order                     = array( 'Pending graduation', 'Designer Track', 'Paused', 'Developer Track', 'In Sensei 50h', 'In Sensei' );
$reordered                        = $stored_now;
$reordered['student_statuses']    = $stored_order;
store( $reordered, $live );
airtable_answers( true );
$compiled_then                    = WPCPM_Track_Store::$compiled;
$untouched                        = people_post();
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = array_values( array_diff( $stored_order, array( 'Paused' ) ) );
press( $untouched );

ck( 'boxes nobody changed leave Currently mentoring as stored, although they post it in another order, so a status another administrator took out since the page was drawn stays out, and nothing is compiled',
    array( WPCPM_Settings::get()['student_statuses'], WPCPM_Track_Store::$compiled - $compiled_then ),
    array( array_values( array_diff( $stored_order, array( 'Paused' ) ) ), 0 ) );

// A crafted request without a locked status, which no browser sends from the drawn boxes: the rule
// that keeps a live track's status holds for the boxes as it held for the textarea.
store( $stored_now, $live );
airtable_answers( true );
$crafted                     = people_post();
$crafted['student_statuses'] = array_values( array_diff( (array) $crafted['student_statuses'], array( 'Developer Track' ) ) );
$crafted['mentor_status']    = 'Interested';
press( $crafted );

ck( 'a request that drops a live track\'s status from Currently mentoring is refused as the textarea\'s was, naming the track, and everything else is saved',
    array( queued( 'settings-refused' ), WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['mentor_status'] ),
    array( array( 'Developer Track' => 'Developer Track' ), $stored_now['student_statuses'], 'Interested' ) );

store( $stored_now, $live );
airtable_answers( true );
$crafted                  = people_post();
$crafted['past_statuses'] = array_merge( (array) $crafted['past_statuses'], array( 'Mentor Track' ) );
press( $crafted );

ck( 'and one that puts a live track\'s status among the past statuses, whose box is locked, is refused as before, naming the track',
    array( queued( 'settings-past-refused' ), WPCPM_Settings::get()['past_statuses'] ),
    array( array( 'Mentor Track' => 'Mentor Track' ), $stored_now['past_statuses'] ) );

store( $stored_now, $live );
airtable_answers( true );
press( people_post( array( 'past_statuses' => array( 'Graduate', 'Dropped out' ) ) ) );

ck( 'every Past students box unticked saves an empty list, as an emptied textarea did',
    WPCPM_Settings::get()['past_statuses'], array() );

store( $stored_now );
airtable_answers( true );
press( people_post( array( 'student_statuses' => $defaults['student_statuses'] ) ) );

ck( 'and every Currently mentoring box unticked, with no track live, is a blank list, so the default goes back and the notice says so, as a blank textarea did',
    array( WPCPM_Settings::get()['student_statuses'], queued( 'settings-defaults' ) ),
    array( $defaults['student_statuses'], array( 'student_statuses' ) ) );

// The pipeline stages, the third list of boxes.
store( $stored_now, $live );
airtable_answers( true );
$advanced_post                              = browser_post( form_of( draw_settings( 'advanced' ) ) );
$advanced_post['institution_active_stages'] = array( '', 'Confirmed', 'Student' );
press( $advanced_post );
$stages_from_boxes = WPCPM_Settings::get()['institution_active_stages'];

store( $stored_now, $live );
airtable_answers( false );
$advanced_post                              = browser_post( form_of( draw_settings( 'advanced' ) ) );
$advanced_post['institution_active_stages'] = "Confirmed\nStudent";
press( $advanced_post );

ck( 'the pipeline stages saved from their boxes are the stages their textarea saved',
    $stages_from_boxes, WPCPM_Settings::get()['institution_active_stages'] );

/* ---- the reviewer lists ------------------------------------------------------- */

echo "\n=== The reviewer lists save the comma list their text inputs saved ===\n";

store( $stored_now, $live );
airtable_answers( true );
$institutions_post                     = browser_post( form_of( draw_settings( 'institutions' ) ) );
$institutions_post['agreement_notify'] = array( 'Ana@Program.example', 'pat@program.example', 'Board@Partner.example, second@partner.example' );
$institutions_post['report_notify']    = array( '' );
press( $institutions_post );
$reviewers_from_boxes = array( WPCPM_Settings::get()['agreement_notify'], WPCPM_Settings::get()['report_notify'] );

// The same addresses typed, in the order the boxes' save writes them: the one still saved where it
// stood (Zoe was unticked), the newly ticked and typed after it.
store( $stored_now, $live );
airtable_answers( false );
$GLOBALS['manage']                     = array();
$institutions_post                     = browser_post( form_of( draw_settings( 'institutions' ) ) );
$GLOBALS['manage']                     = array( 1, 2, 3, 4 );
$institutions_post['agreement_notify'] = 'Board@Partner.example, Ana@Program.example, pat@program.example, second@partner.example';
$institutions_post['report_notify']    = '';
press( $institutions_post );

ck( 'the ticked program managers and the Other addresses line save the comma list the text input saved for the same addresses, lowercased and each once',
    $reviewers_from_boxes, array( WPCPM_Settings::get()['agreement_notify'], WPCPM_Settings::get()['report_notify'] ) );
ck( 'which is today\'s stored shape, and nobody ticked with an empty line is the empty setting, which writes to every program manager',
    $reviewers_from_boxes, array( 'board@partner.example,ana@program.example,pat@program.example,second@partner.example', '' ) );

store( $stored_now, $live );
airtable_answers( true );
press( browser_post( form_of( draw_settings( 'institutions' ) ) ) );

ck( 'and an Institutions Save that changes nothing leaves both lists as stored',
    array( WPCPM_Settings::get()['agreement_notify'], WPCPM_Settings::get()['report_notify'] ),
    array( $stored_now['agreement_notify'], $stored_now['report_notify'] ) );

store( $stored_now, $live );
airtable_answers( true );
$sponsors_post                   = browser_post( form_of( draw_settings( 'sponsors' ) ) );
$sponsors_post['sponsor_notify'] = array( 'zoe@program.example', 'zoe@program.example, not an address' );
press( $sponsors_post );

ck( 'the interest mail\'s list is held to the same rule: an address twice is kept once, and what is not an address is dropped',
    WPCPM_Settings::get()['sponsor_notify'], 'zoe@program.example' );

/* ---- a Save that changes nothing writes nothing ------------------------------------ */

echo "\n=== A Save that changes nothing leaves every list as stored, whatever order it is stored in ===\n";

/**
 * A tab as drawn, and what a browser posts from it with some boxes changed.
 *
 * @param string $tab    The tab.
 * @param array  $untick Name => the values unticked.
 * @param array  $tick   Name => the values ticked.
 * @return array The request.
 */
function tab_post( $tab, array $untick = array(), array $tick = array() ) {
	$html = form_of( draw_settings( $tab ) );

	foreach ( $untick as $name => $values ) {
		foreach ( $values as $value ) {
			$html = str_replace( 'name="' . $name . '[]" value="' . esc_attr( $value ) . '" checked=\'checked\'', 'name="' . $name . '[]" value="' . esc_attr( $value ) . '"', $html );
		}
	}

	foreach ( $tick as $name => $values ) {
		foreach ( $values as $value ) {
			$html = str_replace( 'name="' . $name . '[]" value="' . esc_attr( $value ) . '" ', 'name="' . $name . '[]" value="' . esc_attr( $value ) . '" checked=\'checked\' ', $html );
		}
	}

	return browser_post( $html );
}

// Each list stored in an order its boxes are not drawn in: the boxes draw a list's own order, the
// Institutions table's stages as the base lists them, the program managers by role and name, the
// Other addresses after them, and the roles as the Security tab lists them.
$unordered = array_merge(
	$stored_now,
	array(
		'past_statuses'             => array( 'Dropped out', 'Graduate' ),
		'institution_active_stages' => array_reverse( $defaults['institution_active_stages'] ),
		'agreement_notify'          => 'board@partner.example,zoe@program.example',
		'report_notify'             => 'zoe@program.example,ana@program.example',
		'sponsor_notify'            => 'zoe@program.example,pat@program.example',
		'two_factor_roles'          => array( 'wpcpm_institution', 'administrator' ),
	)
);

$as_stored     = array();
$want_stored   = array();
$compiled_then = WPCPM_Track_Store::$compiled;

foreach ( array(
	'people'       => array( 'student_statuses', 'past_statuses' ),
	'advanced'     => array( 'institution_active_stages' ),
	'institutions' => array( 'agreement_notify', 'report_notify' ),
	'sponsors'     => array( 'sponsor_notify' ),
	'security'     => array( 'two_factor_roles' ),
) as $tab => $keys ) {
	store( $unordered, $live );
	airtable_answers( true );
	press( tab_post( $tab ) );

	foreach ( $keys as $key ) {
		$as_stored[ $key ]   = WPCPM_Settings::get()[ $key ];
		$want_stored[ $key ] = $unordered[ $key ];
	}
}

ck( 'a Save of each tab that changes nothing leaves every list of boxes exactly as stored, in the order it is stored in',
    $as_stored, $want_stored );
ck( 'and so compiles nothing, since neither status list changed',
    WPCPM_Track_Store::$compiled - $compiled_then, 0 );

// A change: the entries still ticked stay where they were stored, and the newly ticked follow them.
$changed = array();

store( $unordered, $live );
airtable_answers( true );
press( tab_post( 'people', array( 'student_statuses' => array( 'Paused' ) ), array( 'student_statuses' => array( 'Graduate' ), 'past_statuses' => array( 'Fail' ) ) ) );
$changed['student_statuses'] = WPCPM_Settings::get()['student_statuses'];
$changed['past_statuses']    = WPCPM_Settings::get()['past_statuses'];

store( $unordered, $live );
airtable_answers( true );
press( tab_post( 'advanced', array( 'institution_active_stages' => array( 'Info Sent' ) ), array( 'institution_active_stages' => array( 'Revisit Later' ) ) ) );
$changed['institution_active_stages'] = WPCPM_Settings::get()['institution_active_stages'];

store( $unordered, $live );
airtable_answers( true );
press( tab_post( 'institutions', array(), array( 'agreement_notify' => array( 'Ana@Program.example' ), 'report_notify' => array( 'pat@program.example' ) ) ) );
$changed['agreement_notify'] = WPCPM_Settings::get()['agreement_notify'];
$changed['report_notify']    = WPCPM_Settings::get()['report_notify'];

ck( 'a Save that changes a list keeps its stored entries in their stored order and puts the newly ticked after them',
    $changed,
    array(
        'student_statuses'          => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Pending graduation', 'Graduate' ),
        'past_statuses'             => array( 'Dropped out', 'Graduate', 'Fail' ),
        'institution_active_stages' => array_merge( array_values( array_diff( array_reverse( $defaults['institution_active_stages'] ), array( 'Info Sent' ) ) ), array( 'Revisit Later' ) ),
        'agreement_notify'          => 'board@partner.example,zoe@program.example,ana@program.example',
        'report_notify'             => 'zoe@program.example,ana@program.example,pat@program.example',
    ) );

/* ---- the lists read again ---------------------------------------------------------- */

echo "\n=== The lists can be read from Airtable again ===\n";

/**
 * The copies the screen keeps of the Airtable lists: the schema, the bases, the column options, and
 * the read that failed, which it remembers beside them.
 *
 * @return string[]
 */
function list_copies() {
	$copies = array( WPCPM_Airtable::SCHEMA_TRANSIENT, WPCPM_Airtable::BASES_TRANSIENT, WPCPM_Airtable::OPTIONS_TRANSIENT );

	if ( defined( 'WPCPM_Airtable::FAILED_TRANSIENT' ) ) {
		$copies[] = constant( 'WPCPM_Airtable::FAILED_TRANSIENT' );
	}

	return $copies;
}

/**
 * Hold a copy of each Airtable list the screen keeps.
 */
function hold_lists() {
	foreach ( list_copies() as $copy ) {
		$GLOBALS['transients'][ $copy ] = array( 'held' => true );
	}
}

/**
 * Which of the copies are held.
 *
 * @return string[]
 */
function lists_held() {
	return array_values( array_intersect( list_copies(), array_keys( $GLOBALS['transients'] ) ) );
}

$all_held = list_copies();
$after    = array();

foreach ( array( 'connection', 'people', 'advanced' ) as $tab ) {
	store( $stored_now, $live );
	airtable_answers( true );
	$posted = tab_post( $tab );
	hold_lists();
	press( $posted );
	$after[ $tab ] = lists_held();
}

ck( 'a Save of the Connection tab, which holds the token and the base the lists are read with, drops every copy of them, and another tab\'s Save drops none',
    $after, array( 'connection' => array(), 'people' => $all_held, 'advanced' => $all_held ) );

store( $stored_now, $live );
airtable_answers( true );
$connection_screen = draw_settings( 'connection' );

preg_match_all( '#<form\b([^>]*)>(.*?)</form>#s', $connection_screen, $connection_forms, PREG_SET_ORDER );

$refresh_form = null;

foreach ( $connection_forms as $form ) {
	if ( false !== strpos( $form[2], 'name="action" value="wpcpm_refresh_lists"' ) ) {
		$refresh_form = $form;
	}
}

ck( 'the Connection tab has a form of its own that reads the lists again: posted to admin-post.php with its own action and nonce, and a button that says so',
    null === $refresh_form ? null : array(
        attrs( $refresh_form[1] ),
        browser_post( $refresh_form[2] ),
        1 === preg_match( '#<input type="submit" name="submit" id="wpcpm-refresh-lists" class="button button-secondary" value="Read the lists from Airtable again" />#', $refresh_form[2] ),
    ),
    array(
        array( 'method' => 'post', 'action' => 'https://example.test/wp-admin/admin-post.php' ),
        array( 'action' => 'wpcpm_refresh_lists', '_wpnonce' => 'nonce:wpcpm_refresh_lists' ),
        true,
    ) );

// The sentence above the button says how long each list is kept, which is how long the Airtable
// client keeps it: the bases, the statuses and the stages a day (`LISTS_TTL`), the stages being a
// column's options as the statuses are, and the tables and the columns, which come from its copy of
// the base's schema, fifteen minutes (`SCHEMA_TTL`).
ck( 'and the sentence above the button says how long each list is kept, as the Airtable client keeps it: the bases, statuses and stages a day, the tables and columns fifteen minutes',
    array(
        null === $refresh_form ? null : ( preg_match( '#<p class="description">(.*?)</p>#s', $refresh_form[2], $said ) ? $said[1] : null ),
        WPCPM_Airtable::LISTS_TTL,
        WPCPM_Airtable::SCHEMA_TTL,
    ),
    array(
        'The bases, statuses and stages are read once a day, the tables and columns every fifteen minutes, and all of them again after a Save of this tab.',
        86400,
        900,
    ) );

/**
 * Press the button that reads the lists again, through its handler.
 *
 * @param bool $nonce_ok Whether the nonce holds.
 * @param bool $can      Whether the person may manage the program.
 * @return string How the handler ended.
 */
function press_refresh( $nonce_ok = true, $can = true ) {
	if ( ! method_exists( 'WPCPM_Settings_Screen', 'handle_refresh_lists' ) ) {
		return 'no handler';
	}

	$_POST                    = array( 'action' => 'wpcpm_refresh_lists', '_wpnonce' => 'nonce:wpcpm_refresh_lists' );
	$GLOBALS['nonce_ok']      = $nonce_ok;
	$GLOBALS['caps']          = $can;
	$GLOBALS['nonces']        = array();
	$GLOBALS['redirected_to'] = null;
	$GLOBALS['umeta']         = array();
	$ended                    = 'no outcome';

	try {
		( new WPCPM_Settings_Screen() )->handle_refresh_lists();
	} catch ( Exception $e ) {
		$ended = $e->getMessage();
	}

	$GLOBALS['nonce_ok'] = true;
	$GLOBALS['caps']     = true;
	$_POST               = array();

	return $ended;
}

hold_lists();
$ended = press_refresh();

ck( 'pressed by a program manager, it checks its nonce, drops every copy of the lists, says so, and goes back to the Connection tab',
    array( $ended, $GLOBALS['nonces'], lists_held(), queued( 'settings' ), $GLOBALS['redirected_to'] ),
    array( 'redirect', array( 'wpcpm_refresh_lists' ), array(), 'lists-read', 'https://example.test/wp-admin/admin.php?page=wpcpm-settings&tab=connection' ) );

hold_lists();

ck( 'with a nonce that fails it is refused for the nonce first, whoever presses it, and drops nothing',
    array( press_refresh( false, false ), lists_held() ),
    array( 'nonce refused', $all_held ) );

hold_lists();

ck( 'and with a good nonce and nobody who may manage the program it is refused for the right, and drops nothing',
    array( 0 === strpos( press_refresh( true, false ), 'wp_die: ' ), $GLOBALS['nonces'], lists_held() ),
    array( true, array( 'wpcpm_refresh_lists' ), $all_held ) );

$read_again = WPCPM_Settings_Screen::settings_messages();

ck( 'and the screen it goes back to says the lists were read again',
    isset( $read_again['lists-read'] ) ? $read_again['lists-read'] : null, array( 'success', 'The lists were read again.' ) );

/* ---- a read that failed is remembered a while ----------------------------------------------- */

echo "\n=== A read Airtable failed is not asked again for five minutes, unless the lists are read again ===\n";

// Each draw of a screen that reads Airtable asks it for the bases or the schema, and with Airtable
// hanging rather than refusing, each read waits the client's twenty seconds, on every draw. A read
// that failed is remembered for five minutes, so each of the four draws answers from it: the
// Connection tab (the bases and the schema), and the Students and mentors tab, the Advanced tab and
// the Mentor Status Checker's screen, whose statuses and stages are the schema's. The button that reads
// the lists again, and a Save of the Connection tab, forget it with the lists.

/**
 * One of the four screens that read Airtable, as a request of its own.
 *
 * @param string $screen A tab of the Settings screen, or the checker's scope.
 * @return string The markup.
 */
function draw_reading( $screen ) {
	return 0 === strpos( $screen, 'tool:' ) ? draw_tool( $screen ) : draw_settings( $screen );
}

$reading_screens = array(
	'connection'                 => 'base_id',
	'people'                     => 'mentor_status',
	'advanced'                   => 'institution_new_stage',
	'tool:mentor-status-checker' => 'checker_source_status',
);
$asked_again     = array();

foreach ( $reading_screens as $screen => $field ) {
	store( $stored_now, $live );
	airtable_answers( false );
	draw_reading( $screen );
	$asked_first = count( $GLOBALS['sent'] );

	// Airtable answers again; the next draw, a request of its own, asks it nothing all the same.
	$GLOBALS['refuse'] = false;
	$GLOBALS['sent']   = array();
	$again_drawn       = draw_reading( $screen );

	$asked_again[ $screen ] = array( $asked_first > 0, count( $GLOBALS['sent'] ), select_options( $again_drawn, $field ), fallback_of( $again_drawn, $field ) );
}

$typed_sentences = array(
	'connection'                 => 'Type the ID. The list could not be read from Airtable just now.',
	'people'                     => 'Type the status. The list could not be read from Airtable just now.',
	'advanced'                   => 'Type the stage. The list could not be read from Airtable just now.',
	'tool:mentor-status-checker' => 'Type the status. The list could not be read from Airtable just now.',
);
$asked_want      = array();

foreach ( $typed_sentences as $screen => $sentence ) {
	$asked_want[ $screen ] = array( true, 0, null, $sentence );
}

ck( 'after a read Airtable failed, the next draw of each of the four screens that read it asks Airtable nothing, and draws the field typed, saying why, as the failed read left it',
    $asked_again, $asked_want );

$remembered = defined( 'WPCPM_Airtable::FAILED_TRANSIENT' ) ? constant( 'WPCPM_Airtable::FAILED_TRANSIENT' ) : '';

ck( 'the failed read is remembered five minutes, and holds nothing of the token',
    array( '' !== $remembered && isset( $GLOBALS['transients'][ $remembered ] ), '' !== $remembered ? ( $GLOBALS['ttl'][ $remembered ] ?? null ) : null, defined( 'WPCPM_Airtable::FAILED_TTL' ) ? constant( 'WPCPM_Airtable::FAILED_TTL' ) : null, false !== strpos( serialize( $GLOBALS['transients'] ), $token ) ),
    array( true, 5 * MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, false ) );

// Five minutes on, the read is Airtable's to answer again.
if ( '' !== $remembered && isset( $GLOBALS['transients'][ $remembered ] ) && is_array( $GLOBALS['transients'][ $remembered ] ) ) {
	foreach ( $GLOBALS['transients'][ $remembered ] as $what => $failure ) {
		$GLOBALS['transients'][ $remembered ][ $what ]['at'] = time() - 5 * MINUTE_IN_SECONDS - 1;
	}
}

$GLOBALS['sent'] = array();
$later           = draw_reading( 'tool:mentor-status-checker' );

ck( 'and five minutes on it is asked again, and the list drawn from its answer',
    array( count( $GLOBALS['sent'] ) > 0, select_options( $later, 'checker_source_status' ) ),
    array( true, array_combine( $mentor_statuses, $mentor_statuses ) ) );

// The cause a remembered failure names is the cause the read failed with, not a guess.
store( $stored_now, $live );
airtable_answers( true );
$routes_kept                     = $GLOBALS['routes'];
$GLOBALS['routes'][ $bases_url ] = $refusal( 403, 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' );
$refused_first                   = fallback_of( draw_settings( 'connection' ), 'base_id' );
$GLOBALS['routes']               = $routes_kept;
$GLOBALS['sent']                 = array();
$refused_again                   = fallback_of( draw_settings( 'connection' ), 'base_id' );

ck( 'a failure remembered says the cause the read failed with, here the missing scope, and asks nothing',
    array( $refused_first, $refused_again, count( $GLOBALS['sent'] ) ),
    array( 'Type the ID. Airtable refused the read: the Personal Access Token needs the <code>schema.bases:read</code> scope.', 'Type the ID. Airtable refused the read: the Personal Access Token needs the <code>schema.bases:read</code> scope.', 0 ) );

// A list that asked Airtable nothing, for want of a token, remembers nothing: there is no wait to save.
store( array_merge( $stored_now, array( 'api_token' => '' ) ), $live );
airtable_answers( true );
draw_settings( 'connection' );

ck( 'and a list read without a token, which asks Airtable nothing, remembers nothing',
    '' !== $remembered && isset( $GLOBALS['transients'][ $remembered ] ), false );

// The button that reads the lists again forgets the failure with the lists, and so does a Save of the
// Connection tab: with Airtable answering, the next draw asks it and draws the lists.
$fresh_bases = array( $base => 'WPCredits (' . $base . ')', 'appPROBE000000002' => 'Sandbox (appPROBE000000002)' );

store( $stored_now, $live );
airtable_answers( false );
draw_settings( 'connection' );
$GLOBALS['refuse'] = false;
press_refresh();
store( $stored_now, $live );
$GLOBALS['sent']   = array();
$after_button      = form_of( draw_settings( 'connection' ) );
$button_asked      = count( $GLOBALS['sent'] );

store( $stored_now, $live );
airtable_answers( false );
$connection_failed = tab_post( 'connection' );
$GLOBALS['refuse'] = false;
press( $connection_failed );
$GLOBALS['sent']   = array();
$after_save        = form_of( draw_settings( 'connection' ) );
$save_asked        = count( $GLOBALS['sent'] );

ck( 'after a failure, the button that reads the lists again and a Save of the Connection tab each forget it: the next draw asks Airtable, and draws the lists it answers',
    array( $button_asked > 0, select_options( $after_button, 'base_id' ), $save_asked > 0, select_options( $after_save, 'base_id' ) ),
    array( true, $fresh_bases, true, $fresh_bases ) );

echo "\n" . ( $fail ? "$fail FAILED\n" : "All passed.\n" );
exit( $fail ? 1 : 0 );
