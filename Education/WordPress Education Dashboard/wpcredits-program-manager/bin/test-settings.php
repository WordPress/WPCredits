<?php
/**
 * Does pressing Save actually save?
 *
 * This exists because it did not. Adding a field took three edits - render it, sanitise it in
 * `WPCPM_Settings::save()`, and add it to a hand-written allowlist in the save handler - and
 * forgetting the third produced a field that renders, accepts what you type, posts it, and
 * discards it without a word. Twenty-one settings were in that state, including the AI
 * provider and its key, which is how it was found: somebody entered a key and it vanished.
 *
 * The allowlist is now derived from the defaults, and this suite pins that: every setting the
 * form renders must survive a round trip through the real handler.
 *
 * Run from the plugin root:  php bin/test-settings.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts'] = array();

class WP_Error {
	public function __construct( $c = '', $m = '' ) {}
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $roles = array(), $display_name = '';
	public function __construct( $id = 0 ) { $this->ID = $id; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_status = 'publish', $post_title = '', $post_name = ''; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
/**
 * Honours the protocol allowlist, as the real one does.
 *
 * A pass-through stub here would make the "a javascript: endpoint is refused" assertion pass
 * no matter what the plugin did, which is worse than not asserting it.
 */
function esc_url_raw( $u, $protocols = null ) {
	$u = trim( (string) $u );

	if ( '' === $u ) {
		return '';
	}

	$scheme = strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) );

	if ( null === $protocols ) {
		$protocols = array( 'http', 'https', 'mailto' );
	}

	return in_array( $scheme, (array) $protocols, true ) ? $u : '';
}
function esc_textarea( $s ) { return esc_html( $s ); }
/**
 * Core's, in the two ways a status list tells apart (BUILDER-3's fix round): a "<" that opens no tag
 * is kept as `&lt;` rather than taken with the rest of the line, and every run of spaces, tabs and
 * line breaks is one space. A stand-in that did neither could not see a guard compare a raw status
 * with a sanitized one.
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
/**
 * Strips what the real one strips, so a junk address does not become a valid-looking one
 * by accident here and get asserted as kept.
 */
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
// A filter a check hooks answers through its callback, with every argument the plugin passes; any
// other value passes through.
$GLOBALS['filters'] = array();
function apply_filters( $t, $v, ...$args ) { return isset( $GLOBALS['filters'][ $t ] ) ? call_user_func( $GLOBALS['filters'][ $t ], $v, ...$args ) : $v; }
// The pages the site has, by path, as `get_page_by_path()` finds them, and each page's address: the
// screen's section intros link the page the program manager guide is published at.
$GLOBALS['pages'] = array();
function get_page_by_path( $path, $output = 'OBJECT', $type = 'page' ) { return $GLOBALS['pages'][ $path ] ?? null; }
function get_permalink( $post = 0 ) { return $post instanceof WP_Post ? 'https://example.test/' . $post->post_name . '/' : false; }
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function wp_timezone_string() { return 'UTC'; }
require_once __DIR__ . '/stubs/caps.php';
$GLOBALS['caps'] = true; // the settings screen is a manager's
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { return true; }
// Kept, so a check can say where a save sent the manager back to.
function wp_safe_redirect( $to ) { $GLOBALS['redirected_to'] = $to; throw new Exception( 'redirect' ); }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function wp_die( $m = '' ) { throw new Exception( 'wp_die: ' . $m ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = 'name' ) { return 'Test'; }
function wp_specialchars_decode( $s, $q = null ) { return (string) $s; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
// Kept, so a flash can be read back from the raw meta the real WPCPM_Flash writes (BUILDER-3).
$GLOBALS['umeta'] = array();
function get_user_meta( $id, $k, $s = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
// User 1 unless a check signs somebody else in: a flash is read once per user and request, so a check
// that reads a second notice reads it as a second user.
function get_current_user_id() { return isset( $GLOBALS['signed_in'] ) ? (int) $GLOBALS['signed_in'] : 1; }
function wp_get_current_user() { return new WP_User( get_current_user_id() ); }
// The screen's own drawing, as core prints it, so each tab can be drawn and read back the way a
// browser would post its forms: the nonce field is core's input, its id its name, then the referer.
function wp_create_nonce( $action = -1 ) { return 'nonce:' . $action; }
function wp_referer_field( $display = true ) {
	$field = '<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=wpcpm-settings" />';
	if ( $display ) {
		echo $field;
	}
	return $field;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$field = '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />' . ( $referer ? wp_referer_field( false ) : '' );
	if ( $display ) {
		echo $field;
	}
	return $field;
}
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
	$id = is_array( $other ) && isset( $other['id'] ) ? $other['id'] : $name;
	echo '<p class="submit"><input type="submit" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" class="button button-' . esc_attr( $type ) . '" value="' . esc_attr( $text ) . '" /></p>';
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
function human_time_diff( $from, $to = 0 ) { return '2 hours'; }
function disabled( $disabled, $current = true, $display = true ) {
	$result = (string) $disabled === (string) $current ? " disabled='disabled'" : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}
// The Mentor Status Checker's screen asks whether the plugin it replaced is still active: it is not.
function is_plugin_active( $plugin ) { return false; }
// A result row the checker's screen draws is filled in from its defaults, as core's does it.
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
// The lists the screen draws its choices from, as the plugin's loader requires them. Neither the
// Airtable client nor the Institutions module is loaded here, so every list answers nothing and each
// tab draws the text inputs and textareas it always had, which the checks below read back.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-choices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';
// The rows every settings form is drawn with, and the settings screen with its own save handler,
// pressed for real in the BUILDER-3 section below.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-rows.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
// The track rules, whose comparison of a status with the past statuses the "Past students" section
// below holds the save to: the real one, since a stand-in would only agree with itself.
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';
// The three tools that keep settings, whose own IDs and labels name the scopes their settings save
// under, and whose own screens draw those settings: the real classes, since a scope named from a
// stand-in's ID would prove nothing.
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php';
// The guide the Resources section links for program managers, which the Administrator Dashboard's
// Resources button opens and the screen's intros leave alone: the real list, since a stand-in's
// address would only agree with itself.
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-assistant.php';
// The enrollment ceilings the Institutions tab states: the real ones.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import.php';

/**
 * The dashboards whose pages the landing-page rows link to, each at its published address, or at ''
 * once a check takes the page away.
 */
class WPCPM_Mentors_Dashboard {
	public static $url = 'https://example.test/mentor-report-card/';

	public static function page_url() {
		return self::$url;
	}
}

/** As above, for students. */
class WPCPM_Students_Dashboard {
	public static $url = 'https://example.test/student-dashboard/';

	public static function page_url() {
		return self::$url;
	}
}

/** As above, for institutions. */
class WPCPM_Institutions_Dashboard {
	public static $url = 'https://example.test/institution-dashboard/';

	public static function page_url() {
		return self::$url;
	}
}

/** As above, for sponsors. */
class WPCPM_Sponsors_Dashboard {
	public static $url = 'https://example.test/sponsor-dashboard/';

	public static function page_url() {
		return self::$url;
	}
}

/** The two-factor policy, as the Security tab reads it: whether the plugin is active, and its count. */
class WPCPM_Two_Factor {
	public static $available = true;
	public static $roles     = array();

	public static function status() {
		return array( 'available' => self::$available, 'roles' => self::$roles );
	}

	public static function required_roles() {
		return (array) WPCPM_Settings::get_value( 'two_factor_roles', array() );
	}
}

/**
 * The mentors sync's column names, which the Mentor Status Checker's screen reads beside its own
 * settings, and which say where the Mentors table's statuses are.
 */
class WPCPM_Mentors_Sync {
	public static function fields() {
		return array( 'mentor_name' => 'Full Name', 'mentor_profile' => 'WordPress profile', 'mentor_status' => 'Status', 'report_status' => 'Status' );
	}
}

/** The mail layer, for the Mail tab: what is queued, the sample buttons' action, the log. */
class WPCPM_Mail {
	const ACTION_TEST = 'wpcpm_send_test_mail';

	public static $queued = 0;

	public static function queued() {
		return self::$queued;
	}

	public static function log() {
		return array(
			array( 'time' => 1757000000, 'to' => 'maciej@a8c.com', 'subject' => 'Welcome', 'context' => 'invite-student', 'sent' => true ),
		);
	}

	public static function failures() {
		return 0;
	}
}

/**
 * One method's source, from its signature to its closing brace at class indent.
 *
 * @param string $source The class file.
 * @param string $name   The method's name.
 * @return string The method, or '' when the class has none by that name.
 */
function method_source( $source, $name ) {
	if ( ! preg_match( '/\n\t(?:public |private |protected )?(?:static )?function ' . preg_quote( $name, '/' ) . '\(/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}

	$method = substr( $source, $m[0][1] );
	$end    = strpos( $method, "\n\t}\n" );

	return false === $end ? $method : substr( $method, 0, $end );
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

/* ---- every rendered field survives a save ------------------------------- */

echo "=== Nothing the form renders is discarded ===\n";

// Every form that saves settings: the Settings screen's tabs, and the Settings section each of three
// tools draws on its own screen, posting to the same handler.
$screen_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php' );
$tool_files    = array(
	'mentor-status-checker' => 'includes/tools/class-wpcpm-mentor-checker.php',
	'duplicate-finder'      => 'includes/tools/class-wpcpm-duplicate-finder.php',
	'handbook'              => 'includes/tools/class-wpcpm-handbook.php',
);
$tool_sources  = '';

foreach ( $tool_files as $tool_file ) {
	$tool_sources .= (string) file_get_contents( WPCPM_PLUGIN_DIR . $tool_file );
}

$forms_source = $screen_source . $tool_sources;
$defaults     = WPCPM_Settings::defaults();

// The fields the forms put on the page, however they draw them: through one of the shared row
// helpers, or by hand.
$rendered = array();

preg_match_all( "/WPCPM_Settings_Rows::(?:text|number|select|checklist|reviewers|landing)_row\(\s*\n?\s*'([a-z_]+)'/", $forms_source, $m );
$rendered = array_merge( $rendered, $m[1] );

preg_match_all( '/name="([a-z_]+)(?:\[\])?"/', $forms_source, $m );
$rendered = array_merge( $rendered, $m[1] );

$rendered = array_values( array_intersect( array_unique( $rendered ), array_keys( $defaults ) ) );

ck( 'the forms render a meaningful number of settings', array( count( $rendered ) > 20 ), array( true ) );

// One set of row helpers for every settings form, public and static, so the Settings screen and each
// tool's Settings section draw a setting the same way. Asked before anything is drawn with them.
$helpers = array();

foreach ( array( 'text_row', 'number_row', 'select_row', 'checklist_row', 'reviewers_row', 'landing_row', 'close_row', 'details_after', 'open_details', 'close_details', 'screen_reader_about', 'typed' ) as $helper ) {
	$found              = method_exists( 'WPCPM_Settings_Rows', $helper ) ? new ReflectionMethod( 'WPCPM_Settings_Rows', $helper ) : null;
	$helpers[ $helper ] = null === $found ? null : array( $found->isPublic(), $found->isStatic() );
}

ck( 'the row helpers are one class\'s, public and static, shared by the Settings screen and the tools, and neither keeps a copy of its own',
    array( $helpers, preg_match_all( '/function (?:text|number|select|checklist|reviewers|textarea|landing)_row\(/', $forms_source ) ),
    array( array_fill_keys( array_keys( $helpers ), array( true, true ) ), 0 ) );

// A hand-drawn row with a fold ends through the rows' own closer, which draws the fold named for the
// row, and a fold opened by hand is closed by the rows' own closer: neither the screen nor a tool
// draws a fold directly, writes its end tag or ends a row after one by hand, so no fold goes unnamed.
ck( 'a hand-drawn row with a fold ends, and a fold opened by hand closes, through the rows class, so every fold is named for its row',
    array( substr_count( $forms_source, 'WPCPM_Settings_Rows::details_after(' ), substr_count( $forms_source, '</details>' ), substr_count( $forms_source, "echo '</td></tr>';" ) ),
    array( 0, 0, 0 ) );

// The handler reads from the defaults, so what it forwards is derivable rather than listed.
// Asserted by *reading the source*: the failure being pinned is a hand-written list drifting
// from the form, and a list cannot drift if there is not one.
ck( 'the handler derives its keys from the defaults, not a hand-written list',
    array(
        false !== strpos( $screen_source, '$defaults = WPCPM_Settings::defaults();' ),
        false !== strpos( $screen_source, "\$keys = array( 'api_token'" ),
    ),
    array( true, false ) );

// A round trip through the real sanitiser for every non-boolean field, with a value that is
// recognisably ours coming back. Every key in the defaults must appear here or in the
// boolean probe below, and that is asserted, so a setting cannot be added without its
// round trip - the failure this file exists for is a field that saves nothing.
$probe = array(
	'api_token'                     => 'patTESTTOKEN1234567890',
	'schema_token'                  => 'patSCHEMATOKEN9876543210',
	'base_id'                       => 'appPROBE0000000001',
	'mentors_table'                 => 'tblPROBE0000000002',
	'mentor_status'                 => 'Probe active',
	'reports_table'                 => 'tblPROBE0000000003',
	'students_table'                => 'tblPROBE0000000004',
	'tutors_table'                  => 'tblPROBE0000000014',
	'feedback_table'                => 'tblPROBE0000000005',
	'institutions_table'            => 'tblPROBE0000000001',
	'teams_table'                   => 'tblPROBE0000000006',
	'sponsors_table'                => 'tblPROBE0000000007',
	'institutions_name_field'       => 'Probe institution',
	'teams_name_field'              => 'Probe field',
	'sponsors_name_field'           => 'Probe sponsor',
	'student_statuses'              => array( 'Probe current', 'Probe paused' ),
	'past_statuses'                 => array( 'Probe past' ),
	'on_inactive'                   => 'keep',
	'handbook_provider'             => 'gemini',
	'handbook_key'                  => 'AQ.probe-key-value',
	'handbook_model'                => 'gemini-2.5-flash',
	'handbook_access'               => 'program',
	'handbook_limit'                => '35',
	'student_on_inactive'           => 'keep',
	'checker_source_status'         => 'Probe status',
	'checker_target_status'         => 'Probe target',
	'checker_course_slug'           => 'probe-course',
	'checker_course_title'          => 'Probe course',
	'checker_completion_phrase'     => 'Probe phrase',
	'checker_timeline_filter'       => 'all',
	'checker_max_pages'             => '7',
	'checker_batch_size'            => '4',
	'checker_request_delay'         => '250',
	'checker_cache_ttl'             => '7200',
	// Institutions module.
	'countries_table'               => 'tblPROBE0000000008',
	'countries_name_field'          => 'Probe country',
	'institution_new_stage'         => 'Probe stage',
	'institution_active_stages'     => array( 'Probe stage', 'Probe confirmed' ),
	'two_factor_roles'              => array( 'administrator', 'wpcpm_mentor' ),
	'institution_on_inactive'       => 'keep',
	'application_spam_days'         => '45',
	'application_rejected_days'     => '400',
	'application_approved_days'     => '90',
	'application_trusted_proxy'     => '203.0.113.7',
	'agreement_max_mb'              => '20',
	'agreement_uploads_per_day'     => '8',
	'agreement_generations_per_day' => '15',
	'agreement_review_days'         => '5',
	'agreement_doc_url'             => 'https://docs.google.com/document/d/PROBEDOC/edit',
	'agreement_notify'              => 'one@example.org,two@example.org',
	'agreement_discard_days'        => '60',
	'invite_retention_days'         => '45',
	// The semester report approval flow.
	'report_autodraft_grace_days'   => '30',
	'report_notify'                 => 'one@example.org,two@example.org',
	// The Sponsors module.
	'team_members_table'            => 'tblPROBE0000000015',
	'sponsor_on_inactive'           => 'revoke',
	'sponsor_notify'                => 'probe-one@example.org,probe-two@example.org',
	'logo_max_kb'                   => '2048',
	'offer_low_stock'               => '250',
);

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();

$saved = WPCPM_Settings::save( $probe );

foreach ( $probe as $key => $value ) {
	// Integers are posted as strings and come back cast; everything else comes back as sent.
	$expected = is_int( $defaults[ $key ] ) ? (int) $value : $value;

	ck( sprintf( '%s survives the save', $key ), array( $saved[ $key ] ), array( $expected ) );
}

// Every checkbox, flipped away from its default so a value stuck at the default shows.
$bool_probe = array();

foreach ( $defaults as $key => $default ) {
	if ( is_bool( $default ) ) {
		$bool_probe[ $key ] = ! $default;
	}
}

$saved = WPCPM_Settings::save( $bool_probe );

foreach ( $bool_probe as $key => $value ) {
	ck( sprintf( '%s can be flipped to %s', $key, var_export( $value, true ) ), array( $saved[ $key ] ), array( $value ) );
}

echo "\n=== The schema token, the second secret ===\n";

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

ck( 'a blank schema token leaves the stored one alone, as the everyday token does',
    WPCPM_Settings::save( array( 'schema_token' => '' ) )['schema_token'], 'patKEEPME0000000000' );

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

ck( 'the mask coming back on submit never overwrites it either',
    WPCPM_Settings::save( array( 'schema_token' => WPCPM_Settings::masked_schema_token() ) )['schema_token'], 'patKEEPME0000000000' );

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

// A field that only ever shows a mask has no other way to say "take it away", and a site that
// has finished creating columns should be able to put the right back.
ck( 'and the word remove clears it, which is the only way to take the right away again',
    WPCPM_Settings::save( array( 'schema_token' => 'Remove' ) )['schema_token'], '' );

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

ck( 'the mask shows the last four characters and nothing else',
    WPCPM_Settings::masked_schema_token(), str_repeat( "\u{2022}", 12 ) . '0000' );

ck( 'and the site knows whether it may create columns at all',
    array( WPCPM_Settings::has_schema_token(), ( function () { $GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => '' ) ); return WPCPM_Settings::has_schema_token(); } )() ),
    array( true, false ) );

ck( 'every setting has a round-trip probe',
    array_values( array_diff( array_keys( $defaults ), array_keys( $probe ), array_keys( $bool_probe ) ) ),
    array() );

ck( 'and every probe names a real setting',
    array_values( array_diff( array_keys( $probe ), array_keys( $defaults ) ) ),
    array() );

/* ---- the shapes that are easy to get wrong ------------------------------ */

echo "\n=== Awkward shapes ===\n";

// An unchecked checkbox posts nothing, so "off" and "this form did not render it" look the
// same. The handler always supplies every boolean, which is why this works.
$saved = WPCPM_Settings::save( array( 'handbook_enabled' => false ) );
ck( 'a boolean can be switched off', array( $saved['handbook_enabled'] ), array( false ) );

$saved = WPCPM_Settings::save( array( 'handbook_enabled' => '1' ) );
ck( 'and on again', array( $saved['handbook_enabled'] ), array( true ) );

// The key is write-only from the form's point of view: the screen shows a mask, and posting
// the mask back must not overwrite the real key with a row of dots.
WPCPM_Settings::save( array( 'handbook_key' => 'AQ.the-real-key' ) );
$saved = WPCPM_Settings::save( array( 'handbook_key' => WPCPM_Settings::masked_handbook_key() ) );
ck( 'posting the masked key back leaves the real one alone',
    array( $saved['handbook_key'] ), array( 'AQ.the-real-key' ) );

$saved = WPCPM_Settings::save( array( 'handbook_key' => '' ) );
ck( 'and so does posting nothing', array( $saved['handbook_key'] ), array( 'AQ.the-real-key' ) );

// Only providers the plugin can actually talk to.
$saved = WPCPM_Settings::save( array( 'handbook_provider' => 'nonsense' ) );
ck( 'an unknown provider falls back to none', array( $saved['handbook_provider'] ), array( '' ) );

$saved = WPCPM_Settings::save( array( 'handbook_access' => 'nonsense' ) );
ck( 'an unknown audience falls back to mentors', array( $saved['handbook_access'] ), array( 'mentor' ) );

$saved = WPCPM_Settings::save( array( 'handbook_limit' => '9999' ) );
ck( 'the rate limit is capped', array( $saved['handbook_limit'] ), array( 200 ) );

// Google AI Studio is the only provider, so there is no endpoint to configure and nothing
// here should invent one.
ck( 'no endpoint setting exists to be got wrong',
    array( array_key_exists( 'handbook_endpoint', WPCPM_Settings::defaults() ) ), array( false ) );

/* ---- the institutions settings ------------------------------------------ */

echo "\n=== Institutions settings ===\n";

// The settings screen does not render the four institution switches yet, so a save of the
// existing form omits them. That must leave them alone - the same input switches off a
// checkbox the form does render, which is the contrast being pinned.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array_merge(
	WPCPM_Settings::defaults(),
	array(
		'institution_provision' => true,
		'institution_home'      => true,
		'applications_enabled'  => true,
		'import_enabled'        => true,
		'auto_sync'             => true,
	)
);

$saved = WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000002' ) );

foreach ( array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled' ) as $flag ) {
	ck( sprintf( '%s survives a save that omits it', $flag ), array( $saved[ $flag ] ), array( true ) );
}

ck( 'while a checkbox the form renders is switched off by the same save', array( $saved['auto_sync'] ), array( false ) );

$saved = WPCPM_Settings::save( array( 'institution_provision' => '', 'institution_home' => '0' ) );
ck( 'and an explicit off is honoured', array( $saved['institution_provision'], $saved['institution_home'] ), array( false, false ) );

// Each integer is clamped at both ends. The floors are the point: a typo of 0 in a retention
// field would otherwise purge everything on the next cron run.
$ranges = array(
	'application_spam_days'         => array( 1, 365 ),
	'application_rejected_days'     => array( 30, 3650 ),
	'application_approved_days'     => array( 0, 3650 ),
	'agreement_max_mb'              => array( 1, 50 ),
	'agreement_uploads_per_day'     => array( 1, 50 ),
	'agreement_generations_per_day' => array( 1, 100 ),
	'agreement_review_days'         => array( 1, 60 ),
	'agreement_discard_days'        => array( 7, 365 ),
	'invite_retention_days'         => array( 7, 365 ),
);

foreach ( $ranges as $key => $range ) {
	$saved = WPCPM_Settings::save( array( $key => (string) ( $range[0] - 1 ) ) );
	ck( sprintf( '%s is floored at %d', $key, $range[0] ), array( $saved[ $key ] ), array( $range[0] ) );

	$saved = WPCPM_Settings::save( array( $key => (string) ( $range[1] + 1 ) ) );
	ck( sprintf( '%s is capped at %d', $key, $range[1] ), array( $saved[ $key ] ), array( $range[1] ) );

	$saved = WPCPM_Settings::save( array( $key => (string) $range[0] ) );
	ck( sprintf( '%s accepts its floor', $key ), array( $saved[ $key ] ), array( $range[0] ) );
}

// The notify list: commas or newlines, valid addresses kept once, everything else dropped.
$saved = WPCPM_Settings::save(
	array(
		'agreement_notify' => "one@example.org, not-an-address\ntwo@example.org,name@\n@example.org, one@example.org\n\n  three@example.org  ",
	)
);
ck( 'agreement_notify keeps the addresses and drops the junk',
    array( $saved['agreement_notify'] ), array( 'one@example.org,two@example.org,three@example.org' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => "nothing here\njavascript:alert(1)" ) );
ck( 'and is empty when nothing valid was typed', array( $saved['agreement_notify'] ), array( '' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => 'kept@example.org' ) );
$saved = WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000003' ) );
ck( 'a save that omits the field leaves it alone', array( $saved['agreement_notify'] ), array( 'kept@example.org' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => 'A@Example.org, a@example.org' ) );
ck( 'one mailbox typed two ways is kept once', array( $saved['agreement_notify'] ), array( 'a@example.org' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => array( array( 'nested' ), 'b@example.org' ) ) );
ck( 'a nested array in a crafted request is skipped, not fatal', array( $saved['agreement_notify'] ), array( 'b@example.org' ) );

// The trusted proxy is compared with `REMOTE_ADDR`, so it is an IP or nothing. A value that
// can never match would read as a setting while trusting no header, which is empty in disguise;
// a list, a range or a URL is refused for the same reason, since none of them is one address.
foreach ( array(
	'not an ip'                => '',
	'203.0.113.7, 203.0.113.8' => '',
	'203.0.113.0/24'           => '',
	'https://203.0.113.7'      => '',
	'203.0.113.7'              => '203.0.113.7',
	' 203.0.113.7 '            => '203.0.113.7',
	'2001:db8::7'              => '2001:db8::7',
	''                         => '',
) as $typed => $want ) {
	$saved = WPCPM_Settings::save( array( 'application_trusted_proxy' => $typed ) );
	ck( sprintf( 'application_trusted_proxy refuses or keeps "%s"', $typed ), $saved['application_trusted_proxy'], $want );
}

$saved = WPCPM_Settings::save( array( 'application_trusted_proxy' => '203.0.113.7' ) );
$saved = WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000004' ) );
ck( 'a save that omits the proxy leaves it alone', $saved['application_trusted_proxy'], '203.0.113.7' );

$saved = WPCPM_Settings::save( array( 'application_trusted_proxy' => array( '203.0.113.7' ) ) );
ck( 'an array in a crafted request is dropped, not fatal', $saved['application_trusted_proxy'], '' );

// The generation ceiling is clamped like the other counts; its floor is 1 because 0 would refuse
// every institution its own template.
$saved = WPCPM_Settings::save( array( 'agreement_generations_per_day' => '250' ) );
ck( 'agreement_generations_per_day is capped at 100', $saved['agreement_generations_per_day'], 100 );
$saved = WPCPM_Settings::save( array( 'agreement_generations_per_day' => '0' ) );
ck( 'and floored at 1', $saved['agreement_generations_per_day'], 1 );
$saved = WPCPM_Settings::save( array( 'agreement_generations_per_day' => '10' ) );
ck( 'and the default of ten is inside the range', $saved['agreement_generations_per_day'], WPCPM_Settings::defaults()['agreement_generations_per_day'] );

// The save handler reads every rendered checkbox unconditionally and skips the ones the
// form does not render yet; the two lists must agree with the form itself, or a switch is
// either silently turned off on every save or silently never saved.
preg_match( '/const UNRENDERED_SWITCHES = array\((.*?)\);/', $screen_source, $m );
preg_match_all( "/'([a-z_]+)'/", isset( $m[1] ) ? $m[1] : '', $listed );
preg_match_all( '/name="([a-z_]+)"/', $forms_source, $rendered );
// A landing page's switch is drawn by the row helper the four landing pages share, which names it.
preg_match_all( "/WPCPM_Settings_Rows::landing_row\(\s*\n?\s*'([a-z_]+)'/", $forms_source, $landing_switches );
$rendered[1] = array_merge( $rendered[1], $landing_switches[1] );
$switches    = array_keys( array_filter( WPCPM_Settings::defaults(), 'is_bool' ) );

// **The list being empty is a fine answer, and used not to be.** This asserted it was not
// empty, on the reasoning that a switch was always going to be waiting for its screen. Then
// the import's screen shipped, the last entry came out, and the assertion failed for the
// state everybody wants: every switch has a box. What actually matters is the pairing below,
// and a switch that had no box and no entry would still be caught by it.
ck( 'the declared list is a list, whether or not anything is in it', is_array( $listed[1] ), true );
ck( 'every switch is either rendered by the form or declared unrendered',
    array_values( array_diff( $switches, $rendered[1], $listed[1] ) ), array() );
ck( 'and none is both', array_values( array_intersect( $rendered[1], $listed[1] ) ), array() );

$keep = $GLOBALS['opts'];
$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array() ) );
WPCPM_Settings::maybe_upgrade();
ck( 'a saved but empty status list is given every status a version has added', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], array( 'Paused', 'Pending graduation', 'Designer Track' ) );
$GLOBALS['opts'] = $keep;

// The stage list goes through the same loop as the status lists.
$saved = WPCPM_Settings::save( array( 'institution_active_stages' => " Confirmed \n\nConfirmed\n<b>Student</b>\n" ) );
ck( 'institution_active_stages is split, trimmed, stripped and de-duplicated',
    array( $saved['institution_active_stages'] ), array( array( 'Confirmed', 'Student' ) ) );

$saved = WPCPM_Settings::save( array( 'institution_on_inactive' => 'delete' ) );
ck( 'institution_on_inactive never becomes anything but keep or revoke', array( $saved['institution_on_inactive'] ), array( 'revoke' ) );

/* ---- the two statuses reach a saved list --------------------------------- */

echo "\n=== The statuses a new version adds reach a saved list ===\n";

// Both syncs build their Airtable formula from the saved list, so a site that saved before
// a status existed fetches none of its students while every line of code looks right.
$three = array( 'In Sensei', 'In Sensei 50h', 'Developer Track' );
// The default, in the order `defaults()` lists it: the four tracks, then the two states.
$all   = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
// What a version-0 site's list of three grows into. The appends land at the end, version by
// version, so this is the default's members in the order the upgrade added them.
$five  = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Designer Track' );

$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array(
		'student_statuses' => $three,
		'base_id'          => 'appSAVED0000000001',
	),
);

WPCPM_Settings::maybe_upgrade();

ck( 'every missing status is appended to a saved list of three, oldest version first', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], $five );
ck( 'the rest of the option is untouched', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['base_id'], 'appSAVED0000000001' );
ck( 'and the version is stamped', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );

$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'Pending graduation' ) ),
);

WPCPM_Settings::maybe_upgrade();

ck( 'only the missing ones are appended, after what was there',
    $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], array( 'In Sensei', 'Pending graduation', 'Paused', 'Designer Track' ) );

// Already has them: nothing is written to the option at all.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => $five ),
);
$before = $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ];

WPCPM_Settings::maybe_upgrade();

ck( 'a list that already has them is left alone', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ], $before );
ck( 'but the version is still stamped', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );

// Idempotent: a second run on an upgraded site changes nothing.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => $three ),
);
WPCPM_Settings::maybe_upgrade();
$after_once = $GLOBALS['opts'];
WPCPM_Settings::maybe_upgrade();
ck( 'a second run changes nothing', $GLOBALS['opts'], $after_once );

// A site that has never saved has no option to migrate: the new default already holds both.
$GLOBALS['opts'] = array();

WPCPM_Settings::maybe_upgrade();

ck( 'with no saved option, the version is stamped', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );
ck( 'and no option is invented', array_key_exists( WPCPM_Settings::OPT_NAME, $GLOBALS['opts'] ), false );
ck( 'the default carries all six', WPCPM_Settings::get_value( 'student_statuses' ), $all );

// A saved option from before the list existed at all inherits the default rather than
// getting a two-item list written over it.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'base_id' => 'appSAVED0000000002' ),
);

WPCPM_Settings::maybe_upgrade();

ck( 'a saved option with no list is not given one', array_key_exists( 'student_statuses', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] ), false );
ck( 'so it reads the default', WPCPM_Settings::get_value( 'student_statuses' ), $all );

// The point of the version key: a manager who removes a status on purpose must not find it
// back after the next request.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => $three ),
);

WPCPM_Settings::maybe_upgrade();
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Pending graduation' ) ) );
WPCPM_Settings::maybe_upgrade();

ck( 'a status a manager removed stays removed',
    WPCPM_Settings::get_value( 'student_statuses' ), array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Pending graduation' ) );

/* ---- the Designer Track reaches a saved list, and only once -------------- */

// Version 3, added with the Designer Track. The rule this pins is the one the version key
// exists for: a status is appended by the version that introduced it and never again, so a
// manager who takes one out later keeps it out. `Developer Track` shipped before there was
// an upgrade path at all and is in no version's list, so it is the case that proves the
// difference: the same run that appends `Designer Track` must leave it removed.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ) ),
	WPCPM_Settings::OPT_VERSION => 2,
);

WPCPM_Settings::maybe_upgrade();

ck( 'a version-2 list gains the Designer Track and nothing else',
    $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'],
    array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Designer Track' ) );
ck( 'and the version moves to 3', get_option( WPCPM_Settings::OPT_VERSION ), 3 );
ck( 'which is what the class calls current', WPCPM_Settings::SETTINGS_VERSION, 3 );

// A manager who deliberately removed the Developer Track: version 2 is stamped, so the
// version-2 appends are done, and the version-3 append must not smuggle the old one back.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Paused', 'Pending graduation' ) ),
	WPCPM_Settings::OPT_VERSION => 2,
);

WPCPM_Settings::maybe_upgrade();

ck( 'a status a manager removed before this version is not put back by it',
    $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'],
    array( 'In Sensei', 'In Sensei 50h', 'Paused', 'Pending graduation', 'Designer Track' ) );

// Already has it: no write at all, and a second run changes nothing.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME    => array( 'student_statuses' => $all, 'base_id' => 'appSAVED0000000003' ),
	WPCPM_Settings::OPT_VERSION => 2,
);
$before = $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ];

WPCPM_Settings::maybe_upgrade();

ck( 'a list that already names the Designer Track is untouched', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ], $before );

$after_once = $GLOBALS['opts'];
WPCPM_Settings::maybe_upgrade();
ck( 'and a second run at the current version changes nothing', $GLOBALS['opts'], $after_once );

// A manager who removes it *after* this version has been stamped keeps it removed, which is
// the same rule one version on.
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei', 'Designer Track' ) ) );
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );
WPCPM_Settings::maybe_upgrade();
ck( 'a Designer Track a manager removes after the upgrade stays removed',
    WPCPM_Settings::get_value( 'student_statuses' ), array( 'In Sensei' ) );

// And a fresh save on a site that never ran the upgrade stamps the version itself, so the
// upgrade cannot arrive later and put back what that save left out.
$GLOBALS['opts'] = array();

WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );
ck( 'save() stamps the version', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );

WPCPM_Settings::maybe_upgrade();
ck( 'so an upgrade after a save appends nothing', WPCPM_Settings::get_value( 'student_statuses' ), array( 'In Sensei' ) );

// The address of the agreement wording is rendered as a link on a manager's screen, so it is
// held to https and to Google's own hosts rather than taken as typed.
foreach ( array(
	'http://docs.google.com/document/d/x'   => '',
	'https://evil.example/document/d/x'     => '',
	'javascript:alert(1)'                   => '',
	'not a url at all'                      => '',
	'https://drive.google.com/drive/f/abc'  => 'https://drive.google.com/drive/f/abc',
) as $typed => $want ) {
	$saved = WPCPM_Settings::save( array( 'agreement_doc_url' => $typed ) );
	ck( sprintf( 'agreement_doc_url refuses or keeps %s', $typed ), $saved['agreement_doc_url'], $want );
}

/* ---- the Institutions module has a tab of its own ------------------------- */

// It used to be one row at the foot of the Students card, under a heading that said Students
// module, which is where a program manager would never look for it. The other four had no
// control at all and could only be changed in code. They are the Institutions tab's now, and the
// tabs section at the end holds every tab to exactly its own settings.
$card = method_source( $screen_source, 'render_institution_settings' );

ck( 'the Institutions tab exists and is named for its audience', method_exists( 'WPCPM_Settings_Screen', 'settings_tabs' ) ? ( WPCPM_Settings_Screen::settings_tabs()['institutions'] ?? null ) : null, 'Institutions' );

foreach ( array( 'applications_enabled', 'institution_home', 'institution_provision', 'institution_on_inactive', 'agreement_review_days', 'agreement_notify', 'agreement_doc_url' ) as $name ) {
	// Drawn by hand, or through a row helper named with the setting, as the reviewer lists are.
	ck( sprintf( '%s has a control on it', $name ), false !== strpos( $card, 'name="' . $name . '"' ) || 1 === preg_match( "/WPCPM_Settings_Rows::[a-z]+_row\(\s*'" . preg_quote( $name, '/' ) . "'/", $card ), true );
}

$people_forms = forms_of( draw_settings( 'people' ) );

ck( 'and the application form is not filed under Students and mentors', in_array( 'applications_enabled', isset( $people_forms[0] ) ? $people_forms[0]['fields'] : array( 'applications_enabled' ), true ), false );

// A rendered checkbox is read unconditionally on save, so the two that moved must be off the
// unrendered list or the first save of this screen would switch them both off.
// The list is empty today, so the pattern has to allow `array()` with nothing between the
// parentheses: written to expect a value, it matched nothing and the two reads below were of
// an array key that was not there.
$found = preg_match( '/const UNRENDERED_SWITCHES = array\((.*?)\);/s', $screen_source, $unrendered );

ck( 'the unrendered list is where the check looks for it', $found, 1 );

ck( 'the two switches that gained a control are off the unrendered list', array(
	false !== strpos( $unrendered[1], "'institution_home'" ),
	false !== strpos( $unrendered[1], "'institution_provision'" ),
), array( false, false ) );


echo "\n=== Three fields that must never be blank ===\n";

// formula_in() turns an empty list into no filter at all, so a blank status setting does not
// fetch nobody, it fetches every row of the table: an account and a role for every SPAM and
// rejected row, or, for the current-student list alone, the revocation of every current student.
// The default goes back in and the screen is told.
$GLOBALS['flash'] = array();
$blank = WPCPM_Settings::save( array( 'mentor_status' => '', 'student_statuses' => '', 'institution_active_stages' => '' ) );
$saved = WPCPM_Settings::get();
ck( 'a blank mentor status is put back to the default', $saved['mentor_status'], WPCPM_Settings::defaults()['mentor_status'] );
ck( 'so is a blank current-student list', $saved['student_statuses'], WPCPM_Settings::defaults()['student_statuses'] );
ck( 'and the blank institution stages', $saved['institution_active_stages'], WPCPM_Settings::defaults()['institution_active_stages'] );
// Asserted by reading the source: save() queues the restored fields, and the notice reads the same
// channel with the labels. (The user-meta stand-ins keep what they are given since BUILDER-3,
// whose section below reads a refusal back from the raw meta.)
$settings_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php' );
ck( 'save() tells the screen which fields it restored', false !== strpos( $settings_src, "WPCPM_Flash::set( 'settings-defaults', \$restored );" ), true );
ck( 'and the notice reads that channel', false !== strpos( $settings_src, "WPCPM_Flash::take( 'settings-defaults' )" ), true );
// And the stage an approved application's institution starts at, which a manager can reach on the
// Advanced tab: blank, the approval would fall back to the same default without a word, so the
// default goes back in and the notice names it as it names the other three.
ck( 'the labels the notice prints are the four fields\' own', WPCPM_Settings::never_blank(), array( 'mentor_status' => 'Mentor status to sync', 'student_statuses' => 'Currently mentoring', 'institution_active_stages' => 'Institution pipeline stages', 'institution_new_stage' => 'Institution starting stage' ) );
$filled = WPCPM_Settings::save( array( 'mentor_status' => 'Active' ) );
ck( 'a filled field is saved as given', WPCPM_Settings::get()['mentor_status'], 'Active' );

echo "\n=== Semester report settings ===\n";

$defaults = WPCPM_Settings::defaults();
ck( 'the three report settings and their defaults', array( $defaults['report_autodraft'], $defaults['report_autodraft_grace_days'], $defaults['report_notify'] ), array( true, 45, '' ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();
$saved = WPCPM_Settings::save( array( 'report_autodraft_grace_days' => '2', 'report_notify' => "one@example.org, junk\nmaciej@a8c.com" ) );
ck( 'the grace has a floor of a week', $saved['report_autodraft_grace_days'], 7 );
ck( 'the addresses are cleaned like the agreement ones', $saved['report_notify'], 'one@example.org,maciej@a8c.com' );
ck( 'a save that does not carry the switch leaves it on', $saved['report_autodraft'], true );
$saved = WPCPM_Settings::save( array( 'report_autodraft' => '', 'report_autodraft_grace_days' => '900' ) );
ck( 'the switch carried empty is off', $saved['report_autodraft'], false );
ck( 'and the grace has a ceiling of a year', $saved['report_autodraft_grace_days'], 365 );

echo "\n=== Sponsors module settings ===\n";

// The fifth audience's settings (design spec of 4 September 2026, section 5).
ck( 'the Team Members table has its own setting: teams_table is the Contribution areas table', WPCPM_Settings::defaults()['team_members_table'], 'tblUYWUSEcRLJ5BaR' );
ck( 'sponsors are routed home at login by default, like everybody', WPCPM_Settings::defaults()['sponsor_home'], true );
ck( 'a sponsor that stops being Approved keeps its accounts unless told otherwise', WPCPM_Settings::defaults()['sponsor_on_inactive'], 'keep' );
ck( 'interest mail falls back to every manager', WPCPM_Settings::defaults()['sponsor_notify'], '' );
ck( 'a logo may be a megabyte', WPCPM_Settings::defaults()['logo_max_kb'], 1024 );

$saved = WPCPM_Settings::save( array( 'sponsor_on_inactive' => 'anything', 'logo_max_kb' => '999999', 'sponsor_notify' => ' maciej@a8c.com ', 'team_members_table' => 'tblX' ) );
ck( 'an unknown on-inactive answer reads as keep', $saved['sponsor_on_inactive'], 'keep' );
ck( 'and revoke is the one other answer', WPCPM_Settings::save( array( 'sponsor_on_inactive' => 'revoke' ) )['sponsor_on_inactive'], 'revoke' );
ck( 'the logo ceiling is clamped', $saved['logo_max_kb'], 8192 );
ck( 'the notify list is trimmed', $saved['sponsor_notify'], 'maciej@a8c.com' );
ck( 'and cleaned like its siblings: split, lowered, the non-addresses dropped, duplicates gone',
	WPCPM_Settings::save( array( 'sponsor_notify' => 'Maciej@a8c.com, not-an-address maciej@a8c.com' ) )['sponsor_notify'],
	'maciej@a8c.com' );
ck( 'the table ID is saved as text', $saved['team_members_table'], 'tblX' );

echo "\n=== S2: the tools switches and the low-stock default ===\n";
$defaults = WPCPM_Settings::defaults();
ck( 'students see the Tools section by default, mentors do not', array( $defaults['tools_students'], $defaults['tools_mentors'] ), array( true, false ) );
ck( 'the low-stock threshold defaults to ten codes', $defaults['offer_low_stock'], 10 );

$saved = WPCPM_Settings::save( array( 'offer_low_stock' => '0' ) );
ck( 'a low-stock threshold of zero is floored at one', $saved['offer_low_stock'], 1 );
$saved = WPCPM_Settings::save( array( 'offer_low_stock' => '5000' ) );
ck( 'and five thousand is capped at a thousand', $saved['offer_low_stock'], 1000 );
$saved = WPCPM_Settings::save( array( 'offer_low_stock' => '19' ) );
ck( 'while an ordinary value is kept as given', $saved['offer_low_stock'], 19 );

// tools_students and tools_mentors join the same guarded flags list sponsor_home is in
// (see save()): absent from an input means "leave it as it was", not "off". A checkbox
// already off - set here explicitly, since a fresh run starts every flag at its default
// of on - stays off when a later save does not mention it, exactly like sponsor_home.
WPCPM_Settings::save( array( 'tools_students' => '' ) );
$saved = WPCPM_Settings::save( array( 'tools_mentors' => '1' ) );
ck( 'tools_students stays off when a save omits it, like sponsor_home', $saved['tools_students'], false );
ck( 'tools_mentors carried as 1 reads as on', $saved['tools_mentors'], true );

echo "\n=== A published track's status joins the list, and nothing leaves it (1.101.0) ===\n";

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'Paused' ) ) );
ck( 'a status the list lacks is appended', array( WPCPM_Settings::add_student_status( 'Marketing Track' ), $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ), array( true, array( 'In Sensei', 'Paused', 'Marketing Track' ) ) );
ck( 'a status it holds is not added twice, and nothing is taken away', array( WPCPM_Settings::add_student_status( 'Marketing Track' ), $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ), array( false, array( 'In Sensei', 'Paused', 'Marketing Track' ) ) );
ck( 'trimmed like every status, and nothing for an empty one', array( WPCPM_Settings::add_student_status( ' Writing Track ' ), WPCPM_Settings::add_student_status( '  ' ), end( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ) ), array( true, false, 'Writing Track' ) );
$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'api_token' => 'kept' ) );
WPCPM_Settings::add_student_status( 'Marketing Track' );
ck( 'a saved option with no list starts from the default one, and keeps its other settings', array( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] ), array( array_merge( WPCPM_Settings::defaults()['student_statuses'], array( 'Marketing Track' ) ), 'kept' ) );

echo "\n=== The Student Duplicate Finder's switch (1.102.0) ===\n";

// Off until a manager turns it on, the way the import's switch shipped: a delete removes rows from
// the shared base, so the finder ships scanning and listing and deleting nothing (spec decision
// 3.10). The generic checks above already hold that the box is rendered and that it can be flipped.
ck( 'deleting duplicates is off until a manager turns it on', isset( WPCPM_Settings::defaults()['duplicate_delete_enabled'] ) ? WPCPM_Settings::defaults()['duplicate_delete_enabled'] : null, false );
ck( 'and the switch is a checkbox of its own, in the Settings section of the finder\'s own screen', false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . $tool_files['duplicate-finder'] ), 'name="duplicate_delete_enabled"' ), true );

echo "\n=== Saving the settings compiles the tracks again ===\n";

// "Currently mentoring" and the past statuses are rules every published track was compiled
// against, so a save that changed them left the live site running the last compile's answer until
// something unrelated published (the design's decision 14).
class WPCPM_Track_Store {
	public static $compiled = 0;

	public static function compile() {
		++self::$compiled;

		return array();
	}
}

$compiled_before = WPCPM_Track_Store::$compiled;
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );

// The recompile hangs on the settings screen's own handler, not on `save()`: `save()`'s callers once
// included the handbook's model migration on `init` priority 5, before the track post type is
// registered at 10 (the T2b whole-branch review). The migration writes through `patch()` now, which
// asks the compile question for itself, only when it changed a status list. Asserted by reading the
// sources, because what is being pinned is which method carries the call.
ck( 'saving through the sanitiser alone compiles nothing', WPCPM_Track_Store::$compiled - $compiled_before, 0 );

$settings_source = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-settings.php' );

ck( 'and the sanitizer carries no compile at all: neither save() nor the writer it shares holds one, and the class\'s only one is patch()\'s',
    array(
        substr_count( method_source( $settings_source, 'save' ) . method_source( $settings_source, 'write' ), 'WPCPM_Track_Store::compile()' ),
        substr_count( method_source( $settings_source, 'patch' ), 'WPCPM_Track_Store::compile()' ),
        substr_count( $settings_source, 'WPCPM_Track_Store::compile()' ),
    ),
    array( 0, 1, 1 ) );

ck( 'the settings screen handler compiles after it saves the scope its form named, which is what decision 14 asks for',
    array(
        false !== strpos( $screen_source, '$saved = WPCPM_Settings::save( $input, $scope );' ),
        false !== strpos( $screen_source, 'WPCPM_Track_Store::compile();' ),
        strpos( $screen_source, '$saved = WPCPM_Settings::save( $input, $scope );' ) < strpos( $screen_source, 'WPCPM_Track_Store::compile();' ),
    ),
    array( true, true, true ) );

echo "\n=== A Settings page drawn before a publish keeps the published track's status (BUILDER-3) ===\n";

// The deep check of 1.109.1, BUILDER-3: the form posted "Currently mentoring" whole from its
// textarea, so a Settings tab drawn before a track was published and saved after it wrote the old
// list back, and the next students sync took the Student role from everybody on the track. The
// form now carries the list as it drew it, and a save that would take a live track's status out
// is refused, naming the track (the design's 7.5). Pressed through the real handler.

/** The compiled tracks, stood in for the one thing the save asks of them: which run from their definitions. */
class WPCPM_Tracks {
	public static $live = array();

	public static function live() {
		return self::$live;
	}
}

/**
 * Press Save on the Students and mentors tab the way a page drew it: the textarea as typed, the
 * list the page drew beside it, every switch as it was drawn, and any other field given.
 *
 * @param string[] $drawn The list the page drew.
 * @param string   $typed What the textarea holds when Save is pressed.
 * @param array    $extra Other fields, by name.
 * @param bool     $carry Whether the page carried the drawn list; a page drawn before 1.110.0 did not.
 * @return string How the handler ended.
 */
function press_settings( array $drawn, $typed, array $extra = array(), $carry = true ) {
	$settings = WPCPM_Settings::get();
	// The Students and mentors tab, which draws both status lists and posts its own name.
	$_POST = array_merge(
		array(
			WPCPM_Settings_Screen::SETTINGS_NONCE     => 'x',
			WPCPM_Settings_Screen::SETTINGS_TAB_FIELD => 'people',
			'student_statuses'                        => $typed,
		),
		$extra
	);

	if ( $carry ) {
		$_POST[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] = $drawn;
	}

	foreach ( WPCPM_Settings::defaults() as $key => $default ) {
		if ( is_bool( $default ) && ! empty( $settings[ $key ] ) ) {
			$_POST[ $key ] = '1';
		}
	}

	$ended = 'no outcome';

	try {
		( new WPCPM_Settings_Screen() )->handle_settings_save();
	} catch ( Exception $e ) {
		$ended = $e->getMessage();
	}

	$_POST = array();

	return $ended;
}

$GLOBALS['opts']  = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta'] = array();
$six              = WPCPM_Settings::get()['student_statuses'];

// Tab A draws the Settings screen; tab B publishes a track of somebody's own, which appends its
// status; tab A is saved for something else.
WPCPM_Settings::add_student_status( 'Mentor Track' );
WPCPM_Tracks::$live = array( 'Mentor Track' => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ) );
$seven              = array_merge( $six, array( 'Mentor Track' ) );

ck( 'a page drawn before a track was published, saved after it for something else, keeps the track\'s status and saves the rest',
    array( press_settings( $six, implode( "\n", $six ), array( 'mentor_status' => 'Active, changed' ) ), WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['mentor_status'] ),
    array( 'redirect', $seven, 'Active, changed' ) );

ck( 'and the same page with the list changed keeps the change, with the status gained since it was drawn put after it',
    array( press_settings( $six, "In Sensei\nIn Sensei 50h\nDeveloper Track\nDesigner Track\nPending graduation" ), WPCPM_Settings::get()['student_statuses'] ),
    array( 'redirect', array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Pending graduation', 'Mentor Track' ) ) );

$compiled_then  = WPCPM_Track_Store::$compiled;
$drawn_now      = WPCPM_Settings::get()['student_statuses'];
$without_mentor = implode( "\n", array_diff( $drawn_now, array( 'Mentor Track' ) ) );
$kept_presses   = array();

// The ruling of BUILDER-3's fix round, 23 September 2026: such a save is not refused whole. Everything
// else it posts is saved as ever, Currently mentoring stays as stored, and the screen gets both
// notices: saved, and why the list was left as it was. Each press here changes the mentor status too,
// so each shows the rest was saved. Neither status list changes, so there is nothing to compile: the
// live tracks were compiled against the lists as they still are.
foreach ( array(
	'taken out on a page drawn now' => array( $drawn_now, $without_mentor, true ),
	'on a page from before 1.110.0' => array( array(), $without_mentor, false ),
	'blanked'                       => array( $drawn_now, '', true ),
) as $case => $page ) {
	$GLOBALS['umeta']      = array();
	$ended                 = press_settings( $page[0], $page[1], array( 'mentor_status' => 'Changed, ' . $case ), $page[2] );
	$queued                = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();
	$kept_presses[ $case ] = array( $ended, $queued['settings'] ?? null, $queued['settings-refused'] ?? null, $queued['settings-defaults'] ?? null, WPCPM_Settings::get()['mentor_status'], WPCPM_Settings::get()['student_statuses'] );
}

$kept_by = array( 'Mentor Track' => 'Mentor Track' );

ck( 'a save whose Currently mentoring change would take a live track\'s status out saves everything else, and leaves the list as stored, however the page came to lack the status, with both notices and the track named, and compiles nothing, since neither list changed',
    array( $kept_presses, WPCPM_Track_Store::$compiled - $compiled_then ),
    array(
        array(
            'taken out on a page drawn now' => array( 'redirect', 'saved', $kept_by, null, 'Changed, taken out on a page drawn now', $drawn_now ),
            'on a page from before 1.110.0' => array( 'redirect', 'saved', $kept_by, null, 'Changed, on a page from before 1.110.0', $drawn_now ),
            'blanked'                       => array( 'redirect', 'saved', $kept_by, null, 'Changed, blanked', $drawn_now ),
        ),
        0,
    ) );

// A live track whose status the list already lacks is not the save's doing: an unrelated change to
// the list saves, and the Track Builder's list flags the track, which is where the ruling puts that
// state. Only a save that would take a status out is refused.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = array( 'In Sensei', 'Paused' );
WPCPM_Tracks::$live['Writing Track'] = array( 'key' => 'writing', 'label' => 'Writing Track', 'source' => 'definition', 'post' => 42 );

ck( 'a live status the stored list already lacks does not refuse an unrelated change to the list',
    array( press_settings( array( 'In Sensei', 'Paused' ), 'In Sensei' ), WPCPM_Settings::get()['student_statuses'] ),
    array( 'redirect', array( 'In Sensei' ) ) );

unset( WPCPM_Tracks::$live['Writing Track'] );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = $drawn_now;

// Once the track is off the live site, taking its status out is the manager's decision (7.5).
WPCPM_Tracks::$live = array();

ck( 'and once the track is off the live site, its status may leave the list',
    array( press_settings( $drawn_now, $without_mentor ), in_array( 'Mentor Track', WPCPM_Settings::get()['student_statuses'], true ) ),
    array( 'redirect', false ) );

// A blanked textarea on a page drawn before the list gained a status (the fix round of BUILDER-3):
// the gained status was merged into the blank and written alone, past the never-blank rule and
// its notice, and with live tracks the refusal named every track but the one the blank would
// drop. A blank is a blank however stale the page: the defaults go back, and the notice says so.
$GLOBALS['opts']    = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta']   = array();
WPCPM_Tracks::$live = array();
WPCPM_Settings::add_student_status( 'Mentor Track' );

ck( 'a blank on a page drawn before the list gained a status is saved as the default list, with the notice that says so',
    array( press_settings( $six, '' ), WPCPM_Settings::get()['student_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null ),
    array( 'redirect', $six, array( 'student_statuses' ) ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = $seven;
$GLOBALS['umeta'] = array();
WPCPM_Tracks::$live = array(
	'In Sensei'       => array( 'key' => '150h', 'label' => 'WordPress Credits Program 150h', 'source' => 'definition', 'post' => 11 ),
	'In Sensei 50h'   => array( 'key' => '50h', 'label' => 'WordPress Credits Program 50h', 'source' => 'definition', 'post' => 12 ),
	'Developer Track' => array( 'key' => 'dev', 'label' => 'Developer Track', 'source' => 'definition', 'post' => 13 ),
	'Designer Track'  => array( 'key' => 'design', 'label' => 'Designer Track', 'source' => 'definition', 'post' => 14 ),
	'Mentor Track'    => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ),
);
press_settings( $six, '' );

ck( 'and with every track live, the blank is held against the default list, so only the track it would drop is named',
    array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-refused'] ?? null, WPCPM_Settings::get()['student_statuses'] ),
    array( array( 'Mentor Track' => 'Mentor Track' ), $seven ) );

WPCPM_Tracks::$live = array();

// The other half of the rule: Currently mentoring is written only when the person changed the
// textarea (the fix round of BUILDER-3, which found nothing pinning it). Another administrator took
// Paused out after this page was drawn, and this page is saved for something else with the textarea
// as it was drawn: the list stays as stored, Paused out, rather than the drawn list written back.
$GLOBALS['opts']  = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta'] = array();
$without_paused   = array_values( array_diff( $six, array( 'Paused' ) ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = $without_paused;

ck( 'a textarea nobody changed leaves the list as stored, so a status another administrator took out since the page was drawn stays out',
    array( press_settings( $six, implode( "\n", $six ), array( 'mentor_status' => 'Active, again' ) ), WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['mentor_status'] ),
    array( 'redirect', $without_paused, 'Active, again' ) );

// A live status as its track holds it may not be what the list holds: publishing appends it trimmed
// alone, and the list is sanitized when it is read, which folds a run of spaces into one and keeps a
// "<" that opens no tag as `&lt;`. Compared raw, such a status was never found in the list, and
// taking it out slipped past the guard (the fix round of BUILDER-3). Both are judged sanitized now.
$GLOBALS['opts']  = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta'] = array();
WPCPM_Settings::add_student_status( 'Mentor  Track' );
WPCPM_Settings::add_student_status( 'Less < More' );
WPCPM_Tracks::$live = array(
	'Mentor  Track' => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ),
	'Less < More'   => array( 'key' => 'less', 'label' => 'Less or More Track', 'source' => 'definition', 'post' => 43 ),
);
$drawn_odd = WPCPM_Settings::get()['student_statuses'];

press_settings( $drawn_odd, implode( "\n", $six ) );

ck( 'a live status holding a run of spaces or a "<" is judged as the list keeps it, so a save taking it out is caught too',
    array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-refused'] ?? null, WPCPM_Settings::get()['student_statuses'] ),
    array( array( 'Mentor  Track' => 'Mentor Track', 'Less < More' => 'Less or More Track' ), $drawn_odd ) );

WPCPM_Tracks::$live = array();

$GLOBALS['umeta'] = array();

echo "\n=== A live track's status is kept out of \"Past students\" ===\n";

// A past status is refused to every track (`status_refused`), and a Settings save compiles
// (decision 14), so a save putting a live track's status among the past statuses took the track off
// the live site with nothing standing in: its students lost its form, and with the 150-hour track
// every student on no track lost theirs too (decision 34). The twin of BUILDER-3's rule: the list
// stays as stored, everything else is saved, and a notice names each track; with neither list
// changed, nothing is compiled. Pressed through the real handler.
$GLOBALS['opts']    = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta']   = array();
WPCPM_Tracks::$live = array(
	'In Sensei'       => array( 'key' => '150h', 'label' => 'WordPress Credits Program 150h', 'post' => 11 ),
	'In Sensei 50h'   => array( 'key' => '50h', 'label' => 'WordPress Credits Program 50h', 'post' => 12 ),
	'Developer Track' => array( 'key' => 'dev', 'label' => 'Developer Track', 'post' => 13 ),
	'Designer Track'  => array( 'key' => 'design', 'label' => 'Designer Track', 'post' => 14 ),
	'Mentor Track'    => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'post' => 41 ),
);
$past_stored   = WPCPM_Settings::get()['past_statuses'];
$compiled_then = WPCPM_Track_Store::$compiled;
$ended         = press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\nIn Sensei 50h\nMentor Track", 'mentor_status' => 'Active, past' ) );
$queued        = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();

ck( 'a save putting live tracks\' statuses among the past statuses saves everything else, leaves the list as stored, and names each track, one of the four original tracks included, and compiles nothing, since neither list changed',
    array( $ended, $queued['settings'] ?? null, $queued['settings-past-refused'] ?? null, WPCPM_Settings::get()['past_statuses'], WPCPM_Settings::get()['mentor_status'], WPCPM_Track_Store::$compiled - $compiled_then ),
    array( 'redirect', 'saved', array( 'In Sensei 50h' => 'WordPress Credits Program 50h', 'Mentor Track' => 'Mentor Track' ), $past_stored, 'Active, past', 0 ) );

// The rule compares folded, case and runs of spaces aside, so a past status typed in lower case
// took the track off as surely as one typed as the track holds it.
$GLOBALS['umeta'] = array();
press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\n  in  sensei  " ) );

ck( 'a past status is judged as the track rules judge it, so "in  sensei" in lower case is caught as the 150-hour track\'s status',
    array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null, WPCPM_Settings::get()['past_statuses'] ),
    array( array( 'In Sensei' => 'WordPress Credits Program 150h' ), $past_stored ) );

$GLOBALS['umeta'] = array();

ck( 'a past status no live track holds is saved as ever, with no notice',
    array( press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\nWithdrawn" ) ), WPCPM_Settings::get()['past_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null ),
    array( 'redirect', array( 'Graduate', 'Dropped out', 'Withdrawn' ), null ) );

// A live status the stored list already holds, which only a hand edit can leave there since Publish
// refuses a past status, is not the save's doing: keeping the list would not keep the track, which
// the Track Builder's list shows as left out. As BUILDER-3's rule treats a status Currently
// mentoring already lacks, an unrelated change to the list saves.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['past_statuses'] = array( 'Graduate', 'Mentor Track' );
$GLOBALS['umeta']                                             = array();

ck( 'a live status the stored list already holds does not refuse an unrelated change to the list',
    array( press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nMentor Track\nDropped out" ) ), WPCPM_Settings::get()['past_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null ),
    array( 'redirect', array( 'Graduate', 'Mentor Track', 'Dropped out' ), null ) );

// Once a track is off the live site, its status may become a past status, as it may then leave
// Currently mentoring (7.5).
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['past_statuses'] = $past_stored;
$GLOBALS['umeta']                                             = array();
unset( WPCPM_Tracks::$live['Mentor Track'] );

ck( 'and once the track is off the live site, its status may join the list',
    array( press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\nMentor Track" ) ), WPCPM_Settings::get()['past_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null ),
    array( 'redirect', array( 'Graduate', 'Dropped out', 'Mentor Track' ), null ) );

WPCPM_Tracks::$live = array();

$GLOBALS['umeta'] = array();

echo "\n=== A save writes only the settings of the tab that posted it ===\n";

// The screen is tabs, each its own form with its own Save, and three tools keep their settings on
// their own screens (the Settings design of 28 September 2026). A save that went on
// writing every setting its input lacked would switch off every box and reset every leaving rule
// the posting form does not draw, the first time one tab saved alone. So each form names its scope,
// a save reads that scope's settings alone, and everything outside it stays as it was.

/**
 * The weekly check's schedule, stood in for the one call a save makes of it, and the last run the
 * checker's screen reads: none, unless a check gives it one.
 */
class WPCPM_Mentor_Checker_Runner {
	public static $synced   = array();
	public static $last_run = array();

	public static function sync_cron( $enabled ) {
		self::$synced[] = (bool) $enabled;
	}

	public static function get_last_run() {
		return self::$last_run;
	}

	public static function summarize( array $rows ) {
		return array( 'checked' => count( $rows ), 'completed' => 0, 'eligible' => 0, 'promoted' => 0, 'skipped' => 0, 'failed' => 0, 'problems' => 0 );
	}
}

/**
 * The tool registry, over the three real tools that keep settings, less any a check takes out of it,
 * as a site's `wpcpm_tools` filter may.
 */
class WPCPM_Tools {
	public static $removed = array();

	public static function get( $id ) {
		foreach ( array( new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder(), new WPCPM_Handbook() ) as $tool ) {
			if ( $tool->id() === $id && ! in_array( $id, self::$removed, true ) ) {
				return $tool;
			}
		}

		return null;
	}
}

/**
 * Press Save on one form: the nonce, the tab the form names, and exactly the fields given.
 *
 * @param string|null $tab     The tab the form names, or null for a form that names none.
 * @param array       $fields  The other fields, by name.
 * @param string      $handler The class whose handler is pressed.
 * @return string How the handler ended.
 */
function press_tab( $tab, array $fields, $handler = 'WPCPM_Settings_Screen' ) {
	$_POST = array_merge( array( WPCPM_Settings_Screen::SETTINGS_NONCE => 'x' ), $fields );

	if ( null !== $tab ) {
		$_POST['wpcpm_tab'] = $tab;
	}

	$GLOBALS['redirected_to'] = null;
	$ended                    = 'no outcome';

	try {
		( new $handler() )->handle_settings_save();
	} catch ( Exception $e ) {
		$ended = $e->getMessage();
	}

	$_POST = array();

	return $ended;
}

/**
 * The settings that differ from a stored copy, sorted.
 *
 * @param array $before The settings as they were.
 * @return string[]
 */
function settings_changed_since( array $before ) {
	$changed = array();

	foreach ( WPCPM_Settings::get() as $key => $value ) {
		if ( ! array_key_exists( $key, $before ) || $before[ $key ] !== $value ) {
			$changed[] = $key;
		}
	}

	sort( $changed );

	return $changed;
}

/**
 * Store a copy of the settings, stamped with the current version.
 *
 * @param array $settings The settings.
 */
function store_settings( array $settings ) {
	$GLOBALS['opts']  = array(
		WPCPM_Settings::OPT_NAME    => $settings,
		WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
	);
	$GLOBALS['umeta'] = array();
}

$scopes   = WPCPM_Settings::scopes();
$defaults = WPCPM_Settings::defaults();
$in_scope = array();

foreach ( $scopes as $keys ) {
	$in_scope = array_merge( $in_scope, $keys );
}

$not_once = array();

foreach ( array_keys( $defaults ) as $key ) {
	$times = count( array_keys( $in_scope, $key, true ) );

	if ( 1 !== $times ) {
		$not_once[ $key ] = $times;
	}
}

ck( 'every setting is in exactly one scope, so a setting added without one, or put in two, fails here', $not_once, array() );
ck( 'and no scope names a setting that does not exist', array_values( array_diff( $in_scope, array_keys( $defaults ) ) ), array() );
ck( 'the scopes are the six tabs that save, in their order, then the three tools that keep settings; Mail saves nothing of its own',
    array_keys( $scopes ),
    array( 'connection', 'people', 'institutions', 'sponsors', 'security', 'advanced', 'tool:mentor-status-checker', 'tool:duplicate-finder', 'tool:handbook' ) );

$tool_scopes = array();

foreach ( array( new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder(), new WPCPM_Handbook() ) as $tool ) {
	$tool_scopes[] = 'tool:' . $tool->id();
}

ck( 'each tool\'s scope is named from the tool\'s own ID, as its screen posts it', array_values( array_intersect( $tool_scopes, array_keys( $scopes ) ) ), $tool_scopes );

// Where each setting goes: the design's table of tabs, then each tool's own. The tables and columns
// no screen drew belong with the others on Connection; Advanced holds the rest of what had no control.
$by_design = array(
	'connection'                 => array( 'api_token', 'schema_token', 'base_id', 'mentors_table', 'reports_table', 'students_table', 'institutions_table', 'teams_table', 'tutors_table', 'feedback_table', 'countries_table', 'team_members_table', 'sponsors_table', 'institutions_name_field', 'teams_name_field', 'countries_name_field', 'sponsors_name_field' ),
	'people'                     => array( 'student_statuses', 'past_statuses', 'mentor_status', 'on_inactive', 'student_on_inactive', 'send_welcome_email', 'auto_sync', 'mentor_home', 'student_home' ),
	'institutions'               => array( 'applications_enabled', 'import_enabled', 'institution_provision', 'institution_home', 'institution_on_inactive', 'agreement_review_days', 'agreement_notify', 'agreement_doc_url', 'report_autodraft', 'report_autodraft_grace_days', 'report_notify' ),
	'sponsors'                   => array( 'sponsor_applications_enabled', 'sponsor_home', 'sponsor_on_inactive', 'sponsor_notify', 'logo_max_kb', 'tools_students', 'tools_mentors', 'offer_low_stock' ),
	'security'                   => array( 'two_factor_roles' ),
	'advanced'                   => array( 'institution_new_stage', 'institution_active_stages', 'application_spam_days', 'application_rejected_days', 'application_approved_days', 'application_trusted_proxy', 'agreement_max_mb', 'agreement_uploads_per_day', 'agreement_generations_per_day', 'agreement_discard_days', 'invite_retention_days' ),
	'tool:mentor-status-checker' => array_values( array_filter( array_keys( $defaults ), static function ( $key ) { return 0 === strpos( $key, 'checker_' ); } ) ),
	'tool:duplicate-finder'      => array( 'duplicate_delete_enabled' ),
	'tool:handbook'              => array_values( array_filter( array_keys( $defaults ), static function ( $key ) { return 0 === strpos( $key, 'handbook_' ); } ) ),
);

$sorted = static function ( array $map ) {
	foreach ( $map as $scope => $keys ) {
		sort( $keys );
		$map[ $scope ] = $keys;
	}

	return $map;
};

ck( 'each scope holds the settings the design gives its tab or tool, twelve for the Mentor Status Checker and six for Need help?',
    array( $sorted( $scopes ), count( $by_design['tool:mentor-status-checker'] ), count( $by_design['tool:handbook'] ) ),
    array( $sorted( $by_design ), 12, 6 ) );

// Every setting stored at its default with every switch on, and a save handed the round-trip probe
// with every switch off: every setting the save may write shows as a change.
$switched_on = $defaults;

foreach ( $switched_on as $key => $default ) {
	if ( is_bool( $default ) ) {
		$switched_on[ $key ] = true;
	}
}

$everything = $probe;

foreach ( $defaults as $key => $default ) {
	if ( is_bool( $default ) ) {
		$everything[ $key ] = false;
	}
}

foreach ( $scopes as $scope => $keys ) {
	store_settings( $switched_on );
	WPCPM_Settings::save( $everything, $scope );

	$own = $keys;
	sort( $own );

	ck( sprintf( 'a save of %s writes its own %d settings and no other, though its input holds every setting', $scope, count( $keys ) ), settings_changed_since( $switched_on ), $own );
}

// The ten settings the one form's save wrote whatever its input held: a leaving rule reads as its
// fallback answer when absent, a switch as off. Stored at the other answer and on, so a write shows.
$ten = array(
	'on_inactive'             => 'people',
	'send_welcome_email'      => 'people',
	'auto_sync'               => 'people',
	'mentor_home'             => 'people',
	'student_home'            => 'people',
	'student_on_inactive'     => 'people',
	'institution_on_inactive' => 'institutions',
	'sponsor_on_inactive'     => 'sponsors',
	'checker_cron_enabled'    => 'tool:mentor-status-checker',
	'checker_cron_promotes'   => 'tool:mentor-status-checker',
);

$other_answers = array_merge(
	$switched_on,
	array(
		'on_inactive'             => 'keep',
		'student_on_inactive'     => 'keep',
		'institution_on_inactive' => 'keep',
		'sponsor_on_inactive'     => 'revoke',
	)
);

$written_by = array_fill_keys( array_keys( $ten ), array() );

foreach ( array_keys( $scopes ) as $scope ) {
	store_settings( $other_answers );
	WPCPM_Settings::save( array(), $scope );

	foreach ( array_keys( $ten ) as $key ) {
		if ( WPCPM_Settings::get()[ $key ] !== $other_answers[ $key ] ) {
			$written_by[ $key ][] = $scope;
		}
	}
}

foreach ( $ten as $key => $own ) {
	ck( sprintf( '%s, which every save used to write whether or not its input held it, is written by a save of %s alone', $key, $own ), $written_by[ $key ], array( $own ) );
}

// A save that names no scope is every scope's, by the rules the screen's single form was saved by:
// every setting is read, the ten are written from any input, and the other switches only when the
// input holds them. The screen's one form is the caller that needs it, until each tab posts its own.
store_settings( $switched_on );
WPCPM_Settings::save( $everything );
$every_key = array_keys( $defaults );
sort( $every_key );

ck( 'a save that names no scope reads every scope\'s settings', settings_changed_since( $switched_on ), $every_key );

store_settings( $other_answers );
WPCPM_Settings::save( array() );
$the_ten = array_keys( $ten );
sort( $the_ten );

ck( 'and writes the ten from any input, leaving every other switch as it was, as the single form\'s save did', settings_changed_since( $other_answers ), $the_ten );
// From the same stored settings each time, so the two agree because they are the same save rather than
// because the second starts where the first left off.
store_settings( $other_answers );
$named_empty = WPCPM_Settings::save( array(), '' );
store_settings( $other_answers );
$named_none = WPCPM_Settings::save( array() );

ck( 'and naming no scope is the same as naming the empty one, from the same stored settings', $named_none, $named_empty );

// The unticked box: a tab's form draws every box of its scope, and a box left unticked posts
// nothing, so in a tab's own save a switch the input lacks is off, the ones that used to be left
// alone included.
store_settings( $switched_on );
WPCPM_Settings::save( array( 'tools_mentors' => '1', 'logo_max_kb' => '2048' ), 'sponsors' );
$sponsors_after = WPCPM_Settings::get();
WPCPM_Settings::save( array( 'handbook_model' => 'gemini-2.5-flash' ), 'tool:handbook' );

ck( 'a box of the tab being saved that the input lacks is off, as an unticked box posts nothing, and the same for a tool\'s switch',
    array( $sponsors_after['sponsor_applications_enabled'], $sponsors_after['sponsor_home'], $sponsors_after['tools_students'], $sponsors_after['tools_mentors'], WPCPM_Settings::get()['handbook_enabled'] ),
    array( false, false, false, true, false ) );

// The case the design names: a Sponsors save handed the Mentors settings leaves them as stored.
store_settings( $other_answers );
WPCPM_Settings::save( array( 'send_welcome_email' => '', 'auto_sync' => false, 'mentor_home' => '0', 'student_home' => false, 'on_inactive' => 'revoke', 'mentor_status' => 'Changed', 'sponsor_home' => '1' ), 'sponsors' );
$after = WPCPM_Settings::get();

ck( 'a Sponsors save whose input holds the Mentors settings leaves every one of them as stored',
    array( $after['send_welcome_email'], $after['auto_sync'], $after['mentor_home'], $after['student_home'], $after['on_inactive'], $after['mentor_status'], $after['sponsor_home'] ),
    array( true, true, true, true, 'keep', $other_answers['mentor_status'], true ) );

// The three that cannot be blank go back to their defaults, with the notice, on the tab that holds
// them. A blank stored outside the tab being saved (before the guard, or by hand) is that tab's to
// put right: both syncs refuse to start on it, and a notice about it after a Sponsors save would
// name a setting the manager never touched.
store_settings( $defaults );
WPCPM_Settings::save( array( 'mentor_status' => '', 'student_statuses' => '' ), 'people' );
$people_blank = array( WPCPM_Settings::get()['mentor_status'], WPCPM_Settings::get()['student_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null );

store_settings( $defaults );
WPCPM_Settings::save( array( 'institution_active_stages' => '' ), 'advanced' );
$advanced_blank = array( WPCPM_Settings::get()['institution_active_stages'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null );

ck( 'a setting that cannot be blank is still put back to its default, with its notice, by a save of the tab that holds it',
    array( $people_blank, $advanced_blank ),
    array(
        array( $defaults['mentor_status'], $defaults['student_statuses'], array( 'mentor_status', 'student_statuses' ) ),
        array( $defaults['institution_active_stages'], array( 'institution_active_stages' ) ),
    ) );

store_settings( array_merge( $defaults, array( 'mentor_status' => '' ) ) );
WPCPM_Settings::save( array( 'logo_max_kb' => '2048' ), 'sponsors' );

ck( 'while a blank stored outside the tab being saved is left for that tab, with no notice',
    array( WPCPM_Settings::get()['mentor_status'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null ),
    array( '', null ) );

// A scope this version does not have is not every scope: read that way, a mistyped name would let
// one tab's form reset every setting the others hold.
store_settings( $switched_on );
$GLOBALS['opts'][ WPCPM_Settings::OPT_VERSION ] = 2;
$before_opts                                    = $GLOBALS['opts'];
$returned                                       = WPCPM_Settings::save( $everything, 'no-such-tab' );

ck( 'a save naming a scope this version does not have writes nothing, and hands back the settings as they are',
    array( $GLOBALS['opts'] === $before_opts, $returned === WPCPM_Settings::get() ),
    array( true, true ) );

// Stamping the settings version vouches for the status lists (see `maybe_upgrade()`), which only
// the form that carries them can do.
store_settings( $defaults );
$GLOBALS['opts'][ WPCPM_Settings::OPT_VERSION ] = 2;
WPCPM_Settings::save( array( 'logo_max_kb' => '2048' ), 'sponsors' );
$version_after_sponsors = get_option( WPCPM_Settings::OPT_VERSION );
WPCPM_Settings::save( array( 'mentor_status' => 'Active' ), 'people' );

ck( 'only a save of the tab that carries the status lists stamps the settings version',
    array( $version_after_sponsors, get_option( WPCPM_Settings::OPT_VERSION ) ),
    array( 2, WPCPM_Settings::SETTINGS_VERSION ) );

// The weekly schedule follows the checker's settings, so it is looked at only when a save changed
// one of them.
store_settings( $defaults );
$synced_then = count( WPCPM_Mentor_Checker_Runner::$synced );
WPCPM_Settings::save( array( 'logo_max_kb' => '2048', 'sponsor_home' => '1' ), 'sponsors' );
WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000005' ) );
$synced_by_others = count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then;
WPCPM_Settings::save( array( 'checker_batch_size' => '5' ), 'tool:mentor-status-checker' );
$synced_by_batch = count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then;
WPCPM_Settings::save( array( 'checker_batch_size' => '5' ), 'tool:mentor-status-checker' );
$synced_by_same = count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then;
WPCPM_Settings::save( array( 'checker_batch_size' => '5', 'checker_cron_enabled' => '1' ), 'tool:mentor-status-checker' );

ck( 'the weekly check is rescheduled only by a save that changed one of the checker\'s settings, not by another tab\'s or by one that changed none of them, and with the switch as saved',
    array( $synced_by_others, $synced_by_batch, $synced_by_same, count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then, end( WPCPM_Mentor_Checker_Runner::$synced ) ),
    array( 0, 1, 1, 2, true ) );

// Through the handler: what the Save on a tab sends, and where it leaves the manager.
$six_statuses = $defaults['student_statuses'];
$past_as_is   = implode( "\n", $defaults['past_statuses'] );

store_settings( $other_answers );
WPCPM_Tracks::$live = array();
$compiled_then      = WPCPM_Track_Store::$compiled;
$synced_then        = count( WPCPM_Mentor_Checker_Runner::$synced );
$ended              = press_tab( 'sponsors', array( 'sponsor_home' => '1', 'tools_students' => '1', 'logo_max_kb' => '2048', 'auto_sync' => '', 'mentor_home' => '', 'mentor_status' => 'Changed' ) );
$queued             = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();
$after              = WPCPM_Settings::get();

ck( 'Save on the Sponsors tab writes its settings, its unticked boxes off, and nothing else, whatever else the request holds',
    array( settings_changed_since( $other_answers ), $after['sponsor_home'], $after['tools_students'], $after['logo_max_kb'], $after['auto_sync'], $after['mentor_home'], $after['mentor_status'] ),
    array( array( 'logo_max_kb', 'sponsor_applications_enabled', 'sponsor_on_inactive', 'tools_mentors' ), true, true, 2048, true, true, $other_answers['mentor_status'] ) );

ck( 'and says so naming the tab, and goes back to it, with nothing compiled or rescheduled',
    array( $ended, $queued['settings'] ?? null, $queued['settings-scope'] ?? null, $GLOBALS['redirected_to'], WPCPM_Track_Store::$compiled - $compiled_then, count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then ),
    array( 'redirect', 'saved', 'sponsors', 'https://example.test/wp-admin/admin.php?page=wpcpm-settings&tab=sponsors', 0, 0 ) );

store_settings( $switched_on );
$ended  = press_tab( 'tool:handbook', array( 'handbook_model' => 'gemini-2.5-flash', 'auto_sync' => '' ) );
$queued = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();

// Back to the tool's own screen, whose Settings section holds the tool's settings: the Settings
// screen has no tab for them.
ck( 'a tool\'s settings save under the tool\'s scope the same way, and go back to the tool\'s own screen, where its Settings section is',
    array( settings_changed_since( $switched_on ), $queued['settings-scope'] ?? null, $GLOBALS['redirected_to'] ),
    array( array( 'handbook_enabled', 'handbook_model' ), 'tool:handbook', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-handbook' ) );

// A tab this version does not have is refused whole: the save does not guess which settings the
// form held, and writes none of them.
store_settings( $switched_on );
$before_opts   = $GLOBALS['opts'];
$compiled_then = WPCPM_Track_Store::$compiled;
$synced_then   = count( WPCPM_Mentor_Checker_Runner::$synced );
$ended         = press_tab( 'no-such-tab', array( 'mentor_status' => 'Changed', 'student_statuses' => "In Sensei\nSomething new", 'checker_cron_enabled' => '1' ) );
$queued        = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();

ck( 'Save from a form naming a tab this version does not have is refused: nothing written, compiled or rescheduled, and the refusal said on the screen it goes back to',
    array( $ended, $GLOBALS['opts'] === $before_opts, WPCPM_Track_Store::$compiled - $compiled_then, count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then, $queued['settings'] ?? null, $queued['settings-scope'] ?? null, $GLOBALS['redirected_to'] ),
    array( 'redirect', true, 0, 0, 'refused', '', 'https://example.test/wp-admin/admin.php?page=wpcpm-settings' ) );

// Every form on the screen names its tab, so one that names none is a page drawn before the screen
// had tabs, or no page of this screen at all. Read as every scope, as the single form once was, its
// Save would switch off every box and reset every leaving rule of every tab it did not draw: it is
// refused whole, like a tab this version does not have, and the manager is told to reload.
store_settings( $switched_on );
$before_opts   = $GLOBALS['opts'];
$compiled_then = WPCPM_Track_Store::$compiled;
$synced_then   = count( WPCPM_Mentor_Checker_Runner::$synced );
$ended         = press_tab( null, array( 'student_statuses' => implode( "\n", $six_statuses ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $six_statuses, 'past_statuses' => $past_as_is, 'logo_max_kb' => '2048', 'mentor_status' => 'Changed', 'sponsor_home' => '1' ) );
$queued        = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();

ck( 'Save from a form that names no tab, such as a page drawn before the screen had tabs, is refused: nothing written, compiled or rescheduled, and the refusal said on the screen it goes back to',
    array( $ended, $GLOBALS['opts'] === $before_opts, WPCPM_Track_Store::$compiled - $compiled_then, count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then, $queued['settings'] ?? null, $queued['settings-scope'] ?? null, $GLOBALS['redirected_to'] ),
    array( 'redirect', true, 0, 0, 'refused', '', 'https://example.test/wp-admin/admin.php?page=wpcpm-settings' ) );

// A scope still queued from a notice that was never drawn cannot name a later save: every outcome
// sets the scope its notice names, a tab's save to that tab and a refusal's to nothing.
store_settings( $switched_on );
WPCPM_Flash::set( 'settings-scope', 'sponsors' );
press_tab( 'people', array( 'mentor_status' => 'Active' ) );
$scope_after_tab = $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null;
WPCPM_Flash::set( 'settings-scope', 'sponsors' );
press_tab( null, array( 'logo_max_kb' => '2048' ) );
$scope_after_none = $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null;
WPCPM_Flash::set( 'settings-scope', 'sponsors' );
press_tab( 'no-such-tab', array( 'logo_max_kb' => '4096' ) );
$scope_after_refused = $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null;

// The Mail tab's sample buttons post to a handler of their own, which queues its outcome on the same
// channel, through the same door: the sample's notice cannot be read with a scope another save left.
$scope_after_sample = null;
$mail_source        = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php' );

if ( is_callable( array( 'WPCPM_Settings_Screen', 'flash_outcome' ) ) ) {
	WPCPM_Flash::set( 'settings-scope', 'sponsors' );
	WPCPM_Settings_Screen::flash_outcome( 'test-sent' );
	$scope_after_sample = array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings'] ?? null, $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null );
}

ck( 'a scope left queued by a notice nobody saw is replaced on every outcome, so it cannot name another tab\'s save, a refusal or a sample invitation, which the mail handler queues through the same door',
    array( $scope_after_tab, $scope_after_none, $scope_after_refused, $scope_after_sample, substr_count( method_source( $mail_source, 'handle_test' ), 'WPCPM_Settings_Screen::flash_outcome( ' ), substr_count( $mail_source, "WPCPM_Flash::set( 'settings'" ) ),
    array( 'people', '', '', array( 'test-sent', '' ), 1, 0 ) );

// The tracks are compiled against the two status lists, so only a save that changed one of them
// has anything to compile (decision 14).
store_settings( $defaults );
$compiled_then = WPCPM_Track_Store::$compiled;
press_tab( 'sponsors', array( 'logo_max_kb' => '2048' ) );
$by_sponsors = WPCPM_Track_Store::$compiled - $compiled_then;
press_tab( 'people', array( 'student_statuses' => implode( "\n", $six_statuses ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $six_statuses, 'past_statuses' => $past_as_is, 'mentor_status' => 'Active, changed' ) );
$by_people_as_is = WPCPM_Track_Store::$compiled - $compiled_then;
press_tab( 'people', array( 'student_statuses' => implode( "\n", $six_statuses ) . "\nWriting Track", WPCPM_Settings::FIELD_DRAWN_STATUSES => $six_statuses, 'past_statuses' => $past_as_is ) );
$by_current = WPCPM_Track_Store::$compiled - $compiled_then;
$drawn_then = WPCPM_Settings::get()['student_statuses'];
press_tab( 'people', array( 'student_statuses' => implode( "\n", $drawn_then ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $drawn_then, 'past_statuses' => $past_as_is . "\nWithdrawn" ) );
$by_past = WPCPM_Track_Store::$compiled - $compiled_then;
press_tab( 'connection', array( 'student_statuses' => implode( "\n", $drawn_then ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $drawn_then, 'past_statuses' => $past_as_is . "\nWithdrawn\nAnother", 'base_id' => 'appPROBE0000000006' ) );
$by_connection = WPCPM_Track_Store::$compiled - $compiled_then;

ck( 'the tracks are compiled only by a save that changed a status list: not by another tab\'s, not by one that left both lists as they were, once for Currently mentoring, once for Past students, and not by the Connection tab\'s though its request carries a changed list',
    array( $by_sponsors, $by_people_as_is, $by_current, $by_past, $by_connection ),
    array( 0, 0, 1, 2, 2 ) );

// The refusals that keep a live track's status in Currently mentoring and out of Past students are
// about the lists a save reads: on the tab that holds the lists they stand as ever, and another tab's
// Save, whatever its request holds for the lists, is neither refused over them nor told it was.
store_settings( $defaults );
WPCPM_Settings::add_student_status( 'Mentor Track' );
WPCPM_Tracks::$live = array( 'Mentor Track' => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ) );
$with_mentor        = WPCPM_Settings::get()['student_statuses'];

press_tab( 'sponsors', array( 'logo_max_kb' => '2048', 'student_statuses' => implode( "\n", $six_statuses ), 'past_statuses' => $past_as_is . "\nMentor Track" ) );
$queued_by_sponsors = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();
$lists_by_sponsors  = array( WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['past_statuses'] );

$GLOBALS['umeta'] = array();
press_tab( 'people', array( 'student_statuses' => implode( "\n", $six_statuses ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $with_mentor, 'past_statuses' => $past_as_is . "\nMentor Track" ) );
$queued_by_people = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();

ck( 'a live track\'s status is still kept in Currently mentoring and out of Past students by a save of the tab that holds them, and another tab\'s save neither touches the lists nor says it refused them',
    array( $queued_by_sponsors['settings-refused'] ?? null, $queued_by_sponsors['settings-past-refused'] ?? null, $lists_by_sponsors, $queued_by_people['settings-refused'] ?? null, $queued_by_people['settings-past-refused'] ?? null, WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['past_statuses'] ),
    array( null, null, array( $with_mentor, $defaults['past_statuses'] ), array( 'Mentor Track' => 'Mentor Track' ), array( 'Mentor Track' => 'Mentor Track' ), $with_mentor, $defaults['past_statuses'] ) );

WPCPM_Tracks::$live = array();

// A switch that ships before its box is carried as stored, on a tab's save as on the one form's:
// for a switch the form has no box for, absent means nothing, not off.
class WPCPM_Settings_Screen_Unrendered extends WPCPM_Settings_Screen {
	const UNRENDERED_SWITCHES = array( 'sponsor_home', 'auto_sync' );
}

store_settings( $switched_on );
press_tab( 'sponsors', array( 'logo_max_kb' => '2048' ), 'WPCPM_Settings_Screen_Unrendered' );
$tab_kept = array( WPCPM_Settings::get()['sponsor_home'], WPCPM_Settings::get()['tools_students'] );
press_tab( 'people', array( 'mentor_status' => 'Active' ), 'WPCPM_Settings_Screen_Unrendered' );

ck( 'a switch declared unrendered keeps its stored value when its own tab is saved, on the Sponsors tab and on Students and mentors alike, while a drawn box left unticked is off',
    array( $tab_kept, WPCPM_Settings::get()['auto_sync'], WPCPM_Settings::get()['mentor_home'] ),
    array( array( true, false ), true, false ) );

// What the screen says once a save lands: the tab's own name, or the tool's.
$said = array();

foreach ( array_merge( array( '' ), array_keys( $scopes ) ) as $scope ) {
	$said[ $scope ] = WPCPM_Settings_Screen::settings_messages( $scope )['saved'];
}

ck( 'the notice names the tab, or the tool, that was saved',
    $said,
    array(
        ''                           => array( 'success', 'Settings saved.' ),
        'connection'                 => array( 'success', 'Connection settings saved.' ),
        'people'                     => array( 'success', 'Students and mentors settings saved.' ),
        'institutions'               => array( 'success', 'Institutions settings saved.' ),
        'sponsors'                   => array( 'success', 'Sponsors settings saved.' ),
        'security'                   => array( 'success', 'Security settings saved.' ),
        'advanced'                   => array( 'success', 'Advanced settings saved.' ),
        'tool:mentor-status-checker' => array( 'success', 'Settings saved for the Mentor Status Checker tool.' ),
        'tool:duplicate-finder'      => array( 'success', 'Settings saved for the Student Duplicate Finder tool.' ),
        'tool:handbook'              => array( 'success', 'Settings saved for the Need help? tool.' ),
    ) );

$messages = WPCPM_Settings_Screen::settings_messages();

ck( 'every outcome the settings flash can carry has words, the refusal among them as an error that says what the form asked for',
    array( array_keys( $messages ), $messages['refused'] ),
    array( array( 'saved', 'refused', 'test-sent', 'test-failed', 'log-cleared', 'lists-read' ), array( 'error', 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.' ) ) );

WPCPM_Tracks::$live = array();
$GLOBALS['umeta']   = array();

// One answer to which settings a scope saves, asked by both the handler and save(), so the two cannot
// drift: the scope's own, every setting for none, and nothing at all for a scope this version does
// not have, which both refuse rather than read as every scope.
ck( 'one answer says which settings a scope saves: every setting for none, the scope\'s own for a scope, and none at all for a scope this version does not have',
    array( WPCPM_Settings::scope_keys( '' ), WPCPM_Settings::scope_keys( 'sponsors' ), WPCPM_Settings::scope_keys( 'tool:handbook' ), WPCPM_Settings::scope_keys( 'no-such-tab' ) ),
    array( array_keys( $defaults ), $scopes['sponsors'], $scopes['tool:handbook'], null ) );

$settings_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php' );

ck( 'and the save handler and save() both ask it, while neither resolves a scope on its own',
    array(
        substr_count( method_source( $screen_source, 'handle_settings_save' ), 'WPCPM_Settings::scope_keys( $scope )' ),
        substr_count( method_source( $settings_source, 'save' ), 'self::scope_keys( $scope )' ),
        substr_count( method_source( $screen_source, 'handle_settings_save' ) . method_source( $settings_source, 'save' ), 'scopes()' ),
    ),
    array( 1, 1, 0 ) );

echo "\n=== Writing some settings and leaving the rest: patch() ===\n";

// The handbook's migration moves a site off a retired model or provider on `init`, one setting at a
// time. Through save() with no scope it took the single form's rules and wrote the four leaving rules
// and six switches from its one-setting input: the landing pages, the invitation emails, the sync and
// the weekly check went off, and each leaving rule went to its fallback answer. patch() writes the
// settings it is given, each by the rule save() applies to it, and leaves every other one as stored.
store_settings( $other_answers );
$GLOBALS['opts'][ WPCPM_Settings::OPT_VERSION ] = 2;
$synced_then                                    = count( WPCPM_Mentor_Checker_Runner::$synced );
$compiled_then                                  = WPCPM_Track_Store::$compiled;
$patched                                        = WPCPM_Settings::patch( array( 'handbook_model' => 'gemini-2.5-flash' ) );

ck( 'patch() of one setting writes that setting alone: the four leaving rules and the six switches stay as stored, and so does everything else',
    array( settings_changed_since( $other_answers ), array_intersect_key( WPCPM_Settings::get(), $ten ), $patched['handbook_model'] ),
    array( array( 'handbook_model' ), array_intersect_key( $other_answers, $ten ), 'gemini-2.5-flash' ) );

ck( 'and, touching neither status list nor the checker, stamps no version, reschedules nothing and compiles nothing',
    array( get_option( WPCPM_Settings::OPT_VERSION ), count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then, WPCPM_Track_Store::$compiled - $compiled_then ),
    array( 2, 0, 0 ) );

store_settings( array_merge( $defaults, array( 'api_token' => 'patKEEPME0000000000' ) ) );
$patched = WPCPM_Settings::patch(
	array(
		'logo_max_kb'       => '999999',
		'handbook_provider' => 'nonsense',
		'agreement_notify'  => 'A@Example.org, not-an-address a@example.org',
		'api_token'         => '',
		'on_inactive'       => 'delete',
	)
);

ck( 'each setting patched is held to save()\'s rule for it: clamped, refused, cleaned, a blank token left alone, an unknown answer read as the fallback',
    array( $patched['logo_max_kb'], $patched['handbook_provider'], $patched['agreement_notify'], $patched['api_token'], $patched['on_inactive'] ),
    array( 8192, '', 'a@example.org', 'patKEEPME0000000000', 'revoke' ) );

store_settings( $defaults );
WPCPM_Settings::patch( array( 'mentor_status' => '' ) );
$patched_blank = array( WPCPM_Settings::get()['mentor_status'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null );

store_settings( array_merge( $defaults, array( 'student_statuses' => array() ) ) );
WPCPM_Settings::patch( array( 'handbook_model' => 'gemini-2.5-flash' ) );

ck( 'a setting that cannot be blank is put back with its notice when it is patched blank, and a blank stored in one not patched is left, with no notice',
    array( $patched_blank, WPCPM_Settings::get()['student_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null ),
    array( array( $defaults['mentor_status'], array( 'mentor_status' ) ), array(), null ) );

store_settings( $defaults );
$GLOBALS['opts'][ WPCPM_Settings::OPT_VERSION ] = 2;
WPCPM_Settings::patch( array( 'past_statuses' => array( 'Graduate', 'Withdrawn' ) ) );
$version_after_past = get_option( WPCPM_Settings::OPT_VERSION );
WPCPM_Settings::patch( array( 'student_statuses' => array( 'In Sensei' ) ) );

ck( 'the settings version is stamped only by a patch of Currently mentoring, the list the version vouches for',
    array( $version_after_past, get_option( WPCPM_Settings::OPT_VERSION ) ),
    array( 2, WPCPM_Settings::SETTINGS_VERSION ) );

store_settings( $defaults );
$synced_then = count( WPCPM_Mentor_Checker_Runner::$synced );
WPCPM_Settings::patch( array( 'handbook_model' => 'gemini-2.5-flash' ) );
$synced_by_other = count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then;
WPCPM_Settings::patch( array( 'checker_cron_enabled' => '1' ) );
$synced_by_switch = count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then;
WPCPM_Settings::patch( array( 'checker_cron_enabled' => '1' ) );

ck( 'the weekly check is rescheduled only by a patch that changed one of its settings, with the switch as patched',
    array( $synced_by_other, $synced_by_switch, count( WPCPM_Mentor_Checker_Runner::$synced ) - $synced_then, end( WPCPM_Mentor_Checker_Runner::$synced ) ),
    array( 0, 1, 1, true ) );

store_settings( $defaults );
$compiled_then = WPCPM_Track_Store::$compiled;
WPCPM_Settings::patch( array( 'handbook_model' => 'gemini-2.5-flash' ) );
$compiled_by_other = WPCPM_Track_Store::$compiled - $compiled_then;
WPCPM_Settings::patch( array( 'student_statuses' => $defaults['student_statuses'] ) );
$compiled_by_same = WPCPM_Track_Store::$compiled - $compiled_then;
WPCPM_Settings::patch( array( 'student_statuses' => array_merge( $defaults['student_statuses'], array( 'Writing Track' ) ) ) );
$compiled_by_current = WPCPM_Track_Store::$compiled - $compiled_then;
WPCPM_Settings::patch( array( 'past_statuses' => array( 'Graduate', 'Dropped out', 'Withdrawn' ) ) );

ck( 'the tracks are compiled by a patch only when it changed a status list, the question the handler asks after a save',
    array( $compiled_by_other, $compiled_by_same, $compiled_by_current, WPCPM_Track_Store::$compiled - $compiled_then ),
    array( 0, 0, 1, 2 ) );

// A patch of a status list is asked the two questions the settings handler asks before a save: one
// that would take a live track's status out of Currently mentoring, or put one among the past
// statuses, leaves that list as stored and writes the rest, as a Save does.
store_settings( $defaults );
WPCPM_Settings::add_student_status( 'Mentor Track' );
WPCPM_Tracks::$live = array( 'Mentor Track' => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ) );
$held_current       = WPCPM_Settings::get()['student_statuses'];
$compiled_then      = WPCPM_Track_Store::$compiled;
$patch_dropped      = WPCPM_Settings::patch( array( 'student_statuses' => $defaults['student_statuses'], 'handbook_limit' => '35' ) );
$patch_ended        = WPCPM_Settings::patch( array( 'past_statuses' => array_merge( $defaults['past_statuses'], array( 'Mentor Track' ) ), 'logo_max_kb' => '2048' ) );
$compiled_refused   = WPCPM_Track_Store::$compiled - $compiled_then;
$patch_kept         = WPCPM_Settings::patch( array( 'student_statuses' => array_merge( $held_current, array( 'Writing Track' ) ), 'past_statuses' => array_merge( $defaults['past_statuses'], array( 'Withdrawn' ) ) ) );

ck( 'a patch that would take a live track\'s status out of Currently mentoring, or put one among the past statuses, leaves that list as stored and writes the rest, compiling nothing, and a patch neither refuses is written and compiled',
    array( $patch_dropped['student_statuses'], $patch_dropped['handbook_limit'], $patch_ended['past_statuses'], $patch_ended['logo_max_kb'], $compiled_refused, $patch_kept['student_statuses'], $patch_kept['past_statuses'], WPCPM_Track_Store::$compiled - $compiled_then ),
    array( $held_current, 35, $defaults['past_statuses'], 2048, 0, array_merge( $held_current, array( 'Writing Track' ) ), array_merge( $defaults['past_statuses'], array( 'Withdrawn' ) ), 1 ) );

WPCPM_Tracks::$live = array();

$handbook_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php' );

$migration_source = method_source( $handbook_source, 'maybe_update_model' );

ck( 'the handbook\'s migration writes through patch(), both times, and never through save()',
    array( substr_count( $migration_source, 'WPCPM_Settings::patch( array(' ), substr_count( $migration_source, 'WPCPM_Settings::save(' ) ),
    array( 2, 0 ) );

$GLOBALS['umeta'] = array();

echo "\n=== The screen is tabs in WordPress's own bar, each tab its own form ===\n";

// The Settings design of 28 September 2026: seven tabs in core's own tab bar, each a form of its own
// with its own Save posting the tab it is, so a save writes that tab's settings and leaves the rest
// as they are; and each of the three tools that keep settings draws them in a Settings section on its
// own screen, one form posting the tool's scope to the same handler. Drawn through the real screen
// and the real tools, and read back the way a browser would post each form.

/**
 * The settings screen as a manager opening it at this tab sees it.
 *
 * @param string|null $tab The `tab` query argument, or null for an address that names none.
 * @return string The screen's markup.
 */
function draw_settings( $tab ) {
	$_GET = null === $tab ? array() : array( 'tab' => $tab );

	ob_start();
	( new WPCPM_Settings_Screen() )->render_settings();
	$html = (string) ob_get_clean();

	$_GET = array();

	return $html;
}

/**
 * A tool's Settings section, as its screen draws it.
 *
 * @param string $id The tool's ID.
 * @return string The section's markup, or '' when the tool draws none.
 */
function draw_tool_settings( $id ) {
	$tool = WPCPM_Tools::get( $id );

	if ( null === $tool || ! method_exists( $tool, 'render_settings' ) ) {
		return '';
	}

	ob_start();
	$tool->render_settings();

	return (string) ob_get_clean();
}

/**
 * A tool's whole screen, as a manager opening it sees it.
 *
 * @param string $id  The tool's ID.
 * @param array  $get The query arguments.
 * @return string The screen's markup.
 */
function draw_tool_screen( $id, array $get = array() ) {
	$_GET = $get;

	ob_start();
	WPCPM_Tools::get( $id )->render_admin_page();
	$html = (string) ob_get_clean();

	$_GET = array();

	return $html;
}

/**
 * The tabs the screen's bar draws, in its order: core's newer bar, a navigation region named for
 * a screen reader, holding plain links.
 *
 * @param string $html The screen.
 * @return array[]|null Each tab's address, its label, whether its class marks it as the tab shown,
 *                      and whether it says so to a screen reader; null when there is no bar.
 */
function tab_bar_of( $html ) {
	if ( ! preg_match( '#<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">(.*?)</nav>#s', $html, $bar ) ) {
		return null;
	}

	preg_match_all( '#<a href="([^"]*)" class="nav-tab( nav-tab-active)?"( aria-current="page")?>([^<]*)</a>#', $bar[1], $links, PREG_SET_ORDER );

	$tabs = array();

	foreach ( $links as $link ) {
		$tabs[] = array( $link[1], $link[4], '' !== $link[2], '' !== $link[3] );
	}

	return $tabs;
}

/**
 * The forms the screen draws, each as a browser would post it.
 *
 * @param string $html The screen.
 * @return array[] Each form's opening tag and markup, the tab it names (null for none), the settings
 *                 it posts (sorted, a list's `[]` dropped, the nonce and the drawn list left out),
 *                 whether it carries the settings nonce, and how many Save buttons it has.
 */
function forms_of( $html ) {
	preg_match_all( '#<form\b([^>]*)>(.*?)</form>#s', $html, $found, PREG_SET_ORDER );

	$plumbing = array( WPCPM_Settings_Screen::SETTINGS_NONCE, '_wp_http_referer', 'wpcpm_tab', 'submit', WPCPM_Settings::FIELD_DRAWN_STATUSES );
	$forms    = array();

	foreach ( $found as $form ) {
		preg_match_all( '#<(?:input|select|textarea)\b[^>]*?\bname="([^"]+)"#', $form[2], $names );

		$fields = array();

		foreach ( $names[1] as $name ) {
			$name = preg_replace( '/\[\]$/', '', $name );

			if ( ! in_array( $name, $plumbing, true ) ) {
				$fields[ $name ] = true;
			}
		}

		$fields = array_keys( $fields );
		sort( $fields );

		$forms[] = array(
			'open'   => $form[1],
			'body'   => $form[2],
			'tab'    => preg_match( '#<input type="hidden" name="wpcpm_tab" value="([^"]*)" />#', $form[2], $tab ) ? $tab[1] : null,
			'fields' => $fields,
			'nonce'  => false !== strpos( $form[2], 'name="' . WPCPM_Settings_Screen::SETTINGS_NONCE . '"' ),
			'saves'  => preg_match_all( '#<input type="submit"[^>]*value="Save settings"#', $form[2] ),
		);
	}

	return $forms;
}

/**
 * The sections the screen draws: each heading, its intro, and whether a form table of rows follows.
 *
 * @param string $html The screen.
 * @return array[] Each: the heading, the intro's words before its link, where the link goes, the
 *                 link's own words, what it adds for a screen reader, and whether the rows follow in
 *                 a form table.
 */
function sections_of( $html ) {
	$html = preg_replace( '#<nav class="nav-tab-wrapper[^"]*"[^>]*>.*?</nav>#s', '', $html );

	preg_match_all( '#<h2>([^<]*)</h2>(\s*<p class="description wpcpm-settings__intro">(.*?)</p>)?(\s*<table class="form-table" role="presentation">)?#s', $html, $found, PREG_SET_ORDER );

	$sections = array();

	foreach ( $found as $section ) {
		$intro = isset( $section[3] ) ? $section[3] : '';
		$link  = preg_match( '#^(.*?) <a href="([^"]*)" target="_blank" rel="noopener noreferrer">([^<]*)<span class="screen-reader-text"> ([^<]*)</span></a>$#s', $intro, $parts ) ? $parts : array( '', $intro, '', '', '' );

		$sections[] = array(
			'heading' => $section[1],
			'intro'   => $link[1],
			'href'    => $link[2],
			'link'    => $link[3],
			'hidden'  => html_entity_decode( $link[4], ENT_QUOTES ),
			'rows'    => isset( $section[4] ) && '' !== $section[4],
		);
	}

	return $sections;
}

/**
 * The value a field is drawn with: an input's value, or a textarea's text.
 *
 * @param string $html The screen.
 * @param string $name The field's name.
 * @return string|null Null when the screen draws no such field.
 */
function drawn_value( $html, $name ) {
	$quoted = preg_quote( $name, '#' );

	if ( preg_match( '#<textarea\b[^>]*\bname="' . $quoted . '"[^>]*>(.*?)</textarea>#s', $html, $m ) ) {
		return html_entity_decode( $m[1], ENT_QUOTES );
	}

	if ( preg_match( '#<input\b[^>]*\bname="' . $quoted . '"[^>]*\bvalue="([^"]*)"#', $html, $m ) ) {
		return html_entity_decode( $m[1], ENT_QUOTES );
	}

	return null;
}

$want_tabs = array(
	'connection'   => 'Connection',
	'people'       => 'Students and mentors',
	'institutions' => 'Institutions',
	'sponsors'     => 'Sponsors',
	'security'     => 'Security',
	'mail'         => 'Mail',
	'advanced'     => 'Advanced',
);
$saving        = array( 'connection', 'people', 'institutions', 'sponsors', 'security', 'advanced' );
$settings_home = 'https://example.test/wp-admin/admin.php?page=wpcpm-settings';

// The three tools that keep settings, each by its ID, its scope and its screen's address.
$keeping = array(
	'mentor-status-checker' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-mentor-status-checker',
	'duplicate-finder'      => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder',
	'handbook'              => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-handbook',
);

ck( 'the screen has the design\'s seven tabs in its order, and no tab for the tools, whose settings are on their own screens',
    method_exists( 'WPCPM_Settings_Screen', 'settings_tabs' ) ? WPCPM_Settings_Screen::settings_tabs() : null,
    $want_tabs );

ck( 'each tab has an address of its own, and the screen\'s own address names none',
    array( WPCPM_Settings_Screen::settings_url(), WPCPM_Settings_Screen::settings_url( 'sponsors' ), WPCPM_Settings_Screen::settings_url( 'advanced' ) ),
    array( $settings_home, $settings_home . '&tab=sponsors', $settings_home . '&tab=advanced' ) );

store_settings( $defaults );

$drawn_bars = array();
$want_bars  = array();

foreach ( array_keys( $want_tabs ) as $shown ) {
	$drawn_bars[ $shown ] = tab_bar_of( draw_settings( $shown ) );
	$want_bars[ $shown ]  = array();

	foreach ( $want_tabs as $slug => $label ) {
		$want_bars[ $shown ][] = array( $settings_home . '&tab=' . $slug, $label, $slug === $shown, $slug === $shown );
	}
}

ck( 'the bar is WordPress\'s own, every tab in order at its address, and the tab shown marked as the one shown, to the eye and to a screen reader, and no other',
    $drawn_bars, $want_bars );

// Core's newer bars are a navigation region, not a heading: a heading around the links made the seven
// tabs one heading to anybody moving through the screen by its headings.
$bar_screen = draw_settings( 'people' );

ck( 'and it is a navigation region, as core\'s newer bars are, the screen\'s one bar, and no heading holds it',
    array( substr_count( $bar_screen, '<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">' ), substr_count( $bar_screen, 'nav-tab-wrapper' ), preg_match( '#<h[1-6][^>]*nav-tab-wrapper#', $bar_screen ) ),
    array( 1, 1, 0 ) );

// A tab the bar holds but the screen draws no sections for, which only a tab added to the bar and to
// the scopes without its sections here could be, draws no form: its Save would post the tab's scope
// with none of its switches, and the save reads a switch it does not receive as off.
$sectionless = null;

if ( method_exists( 'WPCPM_Settings_Screen', 'render_tab_form' ) ) {
	$tab_form = new ReflectionMethod( 'WPCPM_Settings_Screen', 'render_tab_form' );

	if ( PHP_VERSION_ID < 80100 ) {
		$tab_form->setAccessible( true );
	}

	ob_start();
	$tab_form->invoke( new WPCPM_Settings_Screen(), 'no-such-tab', $defaults );
	$sectionless = (string) ob_get_clean();
}

ck( 'a tab with no sections to draw draws no form, no field and no Save, and says so, rather than another tab\'s sections under its name',
    null === $sectionless ? null : array( substr_count( $sectionless, '<form' ), preg_match( '#\sname="#', $sectionless ), substr_count( $sectionless, 'Save settings' ), html_entity_decode( strip_tags( $sectionless ), ENT_QUOTES ) ),
    array( 0, 0, 0, 'This tab has nothing to show yet, so it has nothing to save.' ) );

$fallbacks = array();

foreach ( array( 'none' => null, 'empty' => '', 'unknown' => 'no-such-tab', 'a tool\'s scope' => 'tool:handbook', 'the Tools tab, which is gone' => 'tools' ) as $case => $asked ) {
	$html  = draw_settings( $asked );
	$shown = array();

	foreach ( (array) tab_bar_of( $html ) as $tab ) {
		if ( $tab[2] ) {
			$shown[] = $tab[1];
		}
	}

	$fallbacks[ $case ] = array( $shown, array_column( forms_of( $html ), 'tab' ) );
}

// Connection's own form, then the button that reads the Airtable lists again, a form of its own.
ck( 'an address that names no tab, or one the screen does not have, the Tools tab a bookmark may keep among them, draws Connection',
    $fallbacks,
    array_fill_keys( array( 'none', 'empty', 'unknown', 'a tool\'s scope', 'the Tools tab, which is gone' ), array( array( 'Connection' ), array( 'connection', null ) ) ) );

// A check per tab that saves: one form, holding exactly the settings a save of that tab writes. A
// field missing from it would be a setting its Save switches off or leaves unreachable; a field of
// another tab's would be one it posts and throws away.
$on_a_form = array();

foreach ( $saving as $slug ) {
	$own = WPCPM_Settings::scope_keys( $slug );
	sort( $own );

	$drawn = array();

	foreach ( forms_of( draw_settings( $slug ) ) as $form ) {
		$drawn[]   = array( $form['tab'], $form['nonce'], $form['saves'], $form['fields'] );
		$on_a_form = array_merge( $on_a_form, $form['fields'] );
	}

	// The Connection tab has a second form after its own: the button that reads the Airtable lists
	// again, posting its action and nonce to admin-post.php and no setting.
	$want = array( array( $slug, true, 1, $own ) );

	if ( 'connection' === $slug ) {
		$want[] = array( null, false, 0, array( '_wpnonce', 'action' ) );
	}

	ck( sprintf( 'the %s tab is one form, with the nonce and one Save, posting "%s" and holding exactly the %d settings a save of it writes%s', $want_tabs[ $slug ], $slug, count( $own ), 'connection' === $slug ? ', then the form that reads the lists again' : '' ),
	    $drawn,
	    $want );
}

// No tab draws a tool's settings any more, nor does the address of the Tools tab a bookmark may
// keep: each tool that keeps some draws them on its own screen.
$tool_keys = array();

foreach ( array_keys( $keeping ) as $id ) {
	$tool_keys = array_merge( $tool_keys, (array) WPCPM_Settings::scope_keys( 'tool:' . $id ) );
}

$on_tabs = array();

foreach ( array_merge( array_keys( $want_tabs ), array( 'tools' ) ) as $slug ) {
	foreach ( forms_of( draw_settings( $slug ) ) as $form ) {
		$on_tabs = array_merge( $on_tabs, array_intersect( $form['fields'], $tool_keys ), null === $form['tab'] || 0 !== strpos( $form['tab'], 'tool:' ) ? array() : array( $form['tab'] ) );
	}
}

ck( 'no tab of the Settings screen draws a tool\'s settings, or a form posting a tool\'s scope', $on_tabs, array() );

// Each tool's Settings section: its heading, then one form, since a form posts one scope, with the
// nonce and a Save of its own, posting the tool's scope and holding exactly the settings a save of
// that scope writes, which are the settings the tool says it keeps.
$drawn = array();
$want  = array();

foreach ( array_keys( $keeping ) as $id ) {
	$html = draw_tool_settings( $id );
	$tool = WPCPM_Tools::get( $id );
	$own  = WPCPM_Settings::scope_keys( 'tool:' . $id );
	$says = method_exists( $tool, 'settings_keys' ) ? $tool->settings_keys() : array();

	sort( $own );
	sort( $says );

	$forms = array();

	foreach ( forms_of( $html ) as $form ) {
		$forms[]   = array( $form['open'], $form['tab'], $form['nonce'], $form['saves'], $form['fields'] );
		$on_a_form = array_merge( $on_a_form, $form['fields'] );
	}

	preg_match_all( '#<input type="submit"[^>]*\bid="([^"]*)"#', $html, $button_ids );

	$drawn[ $id ] = array(
		substr_count( $html, '<h2 id="settings">Settings</h2>' ),
		method_exists( $tool, 'settings_scope' ) ? $tool->settings_scope() : null,
		$says,
		$forms,
		$button_ids[1],
	);
	$want[ $id ]  = array( 1, 'tool:' . $id, $own, array( array( ' method="post" action=""', 'tool:' . $id, true, 1, $own ) ), array( 'wpcpm-save-' . $id ) );
}

ck( 'each tool that keeps settings draws them in a Settings section: one form, with the nonce and a Save of its own, posting the tool\'s scope and holding exactly the settings it says it keeps, which a save of that scope writes',
    $drawn, $want );

// A tool that keeps no settings, as the Header notices and the Track Builder keep none of these,
// draws no section at all.
$keeps_none = new class() extends WPCPM_Tool {
	public function id() {
		return 'keeps-none';
	}

	public function label() {
		return 'Keeps none';
	}

	public function description() {
		return '';
	}

	public function render_admin_page() {}
};

ob_start();

if ( method_exists( $keeps_none, 'render_settings' ) ) {
	$keeps_none->render_settings();
}

$none_drawn = (string) ob_get_clean();

ck( 'a tool that keeps no settings says so, and draws no Settings section',
    array( method_exists( $keeps_none, 'settings_keys' ) ? $keeps_none->settings_keys() : null, $none_drawn ),
    array( array(), '' ) );

$times_drawn = array_count_values( $on_a_form );
$not_once    = array();

foreach ( array_keys( $defaults ) as $key ) {
	if ( 1 !== ( $times_drawn[ $key ] ?? 0 ) ) {
		$not_once[ $key ] = $times_drawn[ $key ] ?? 0;
	}
}

ck( 'every setting has a control on exactly one form, a tab\'s or a tool\'s Settings section, the ones no screen drew before among them', $not_once, array() );

echo "\n=== Each section's intro links its part of the site's own program manager guide ===\n";

// The sections of each tab, as the design lists them. Each opens with one sentence and a link to its
// part of the program manager guide as this site publishes it: the page at the path
// `program-manager-guide`, which holds the guide bin/build-docs.php builds, opened at the id the
// build gives the section's heading in guide 31. Not the handbook page the Resources section links,
// which has none of these parts.

/**
 * The page the program manager guide is published at, as `get_page_by_path()` answers it.
 *
 * @param string $status The page's status.
 * @return WP_Post
 */
function guide_page( $status ) {
	$page              = new WP_Post();
	$page->ID          = 560;
	$page->post_name   = 'program-manager-guide';
	$page->post_status = $status;

	return $page;
}

/**
 * The ids bin/build-docs.php gave one part's headings in the program manager guide, read from the
 * built guide inside that part: from the part's first heading to the next part's.
 *
 * The build numbers a heading that repeats within a guide, so an id is a part's own only when it is
 * read inside the part: a heading added to an earlier part under a name guide 31 also has would take
 * the plain id, and guide 31's would become `-2`. The parts and their order are the build's own
 * list, and each heading of the built guide is held to its part's source, level and words, so a
 * built guide the parts no longer make cannot pass for one they do.
 *
 * @param string $part A file of docs/sections, without its extension.
 * @return array The part's ids, in order, and whether the built guide's headings are its parts'.
 */
function guide_part_ids( $part ) {
	$build = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'bin/build-docs.php' );
	$built = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/build/administrators.html' );

	preg_match( "/'administrators'\s*=>\s*array\(.*?'parts'\s*=>\s*array\(([^)]*)\)/s", $build, $list );
	preg_match_all( "/'([0-9a-z-]+)'/", isset( $list[1] ) ? $list[1] : '', $parts );
	preg_match_all( '#<h([2-4]) class="wp-block-heading" id="([a-z0-9-]+)">(.*?)</h\1>#s', $built, $headings, PREG_SET_ORDER );

	$ids     = array();
	$at      = 0;
	$matched = in_array( $part, $parts[1], true );

	foreach ( $parts[1] as $name ) {
		preg_match_all( '/^(#{2,4}) (.+)$/m', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/' . $name . '.md' ), $source, PREG_SET_ORDER );

		foreach ( $source as $heading ) {
			$made  = isset( $headings[ $at ] ) ? $headings[ $at ] : null;
			$words = str_replace( array( '**', '*', '`' ), '', preg_replace( '/\[([^\]]+)\]\([^)]*\)/', '$1', rtrim( $heading[2] ) ) );

			if ( null === $made || strlen( $heading[1] ) !== (int) $made[1] || html_entity_decode( strip_tags( $made[3] ), ENT_QUOTES ) !== $words ) {
				$matched = false;
			}

			if ( $name === $part && null !== $made ) {
				$ids[] = $made[2];
			}

			++$at;
		}
	}

	return array( $ids, $matched && count( $headings ) === $at );
}

list( $part_31, $lined_up ) = guide_part_ids( '31-admin-settings' );

// The sentinel is an id the built guide has in another part: guide 32's heading for Need help?, the
// part right after guide 31's. A reading that let another part's ids into guide 31's list would hold
// it, and an intro anchor pointing into guide 32 would pass; the id has to be in the built guide for
// its absence from the list to prove anything.
$built_guide = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/build/administrators.html' );

ck( 'the built guide is its parts, heading for heading, in the build\'s order, so the ids of guide 31\'s own part can be read from it, and another part\'s id is not among them',
    array( $lined_up, count( $part_31 ) > 20, in_array( 'airtable', $part_31, true ), 1 === substr_count( $built_guide, ' id="need-help"' ), in_array( 'need-help', $part_31, true ) ),
    array( true, true, true, true, false ) );

$GLOBALS['pages'] = array( 'program-manager-guide' => guide_page( 'publish' ) );
$guide_home       = 'https://example.test/program-manager-guide/';

// Each tab's sections, as the design lists them, each with the id of its own heading in guide 31: a
// heading the guide shares with another tab's section is named apart there, so the part a link opens
// is its own section's, not merely one of guide 31's.
$design_sections = array(
	'connection'   => array(
		'Airtable' => 'airtable',
		'Tables'   => 'tables',
	),
	'people'       => array(
		'Who is on the program' => 'who-is-on-the-program',
		'When someone leaves'   => 'when-someone-leaves',
		'Accounts'              => 'accounts',
		'Landing pages'         => 'landing-pages',
	),
	'institutions' => array(
		'Applications and enrollment'             => 'institution-applications-and-enrollment',
		'Landing page'                            => 'institution-landing-page',
		'When an institution leaves the pipeline' => 'when-an-institution-leaves-the-pipeline',
		'Collaboration Agreements'                => 'collaboration-agreements',
		'Semester reports'                        => 'semester-report-drafting',
	),
	'sponsors'     => array(
		'Applications'                         => 'sponsor-applications',
		'Landing page'                         => 'sponsor-landing-page',
		'When a sponsor is no longer Approved' => 'when-a-sponsor-is-no-longer-approved',
		'Interest mail'                        => 'interest-mail',
		'Offers'                               => 'offers',
	),
	'security'     => array(
		'Two-factor authentication' => 'two-factor-authentication',
	),
	'mail'         => array(
		'Invitations' => 'sending-yourself-an-invitation',
		'Recent mail' => 'recent-mail',
	),
	'advanced'     => array(
		'Pipeline stages' => 'pipeline-stages',
		'Applications'    => 'keeping-applications',
		'Agreements'      => 'agreement-files',
		'Invitations'     => 'lapsed-invitations',
	),
);
$anchors_used = array();
$not_in_31    = array();

foreach ( $design_sections as $slug => $headings ) {
	$html  = draw_settings( $slug );
	$drawn = array();
	$want  = array();

	foreach ( sections_of( $html ) as $section ) {
		$anchor = 0 === strpos( $section['href'], $guide_home . '#' ) ? substr( $section['href'], strlen( $guide_home ) + 1 ) : null;

		$drawn[] = array(
			$section['heading'],
			'' !== $section['intro'] && '.' === substr( $section['intro'], -1 ) && ! preg_match( '/[.?!] /', $section['intro'] ),
			$anchor,
			$section['link'],
			$section['hidden'],
			$section['rows'],
		);

		$anchors_used[] = $anchor;
	}

	// The link reads "Program manager guide" to the eye, and to a screen reader names the section it
	// opens the guide at, since a tab's list of links would otherwise read the same words once a
	// section.
	foreach ( $headings as $heading => $anchor ) {
		$want[] = array( $heading, true, $anchor, 'Program manager guide', 'about ' . $heading . ' (opens in a new tab)', 'mail' !== $slug );

		if ( ! in_array( $anchor, $part_31, true ) ) {
			$not_in_31[] = $anchor;
		}
	}

	ck( sprintf( 'the %s tab\'s sections are the design\'s, in its order, each its heading, one sentence ending in a link to its own section of the program manager guide on this site, named for a screen reader, then its rows in a form table%s', $want_tabs[ $slug ], 'mail' === $slug ? ' (Mail\'s are its forms and its log)' : '' ),
	    array( $drawn, preg_match_all( '#<h2[ >]#', preg_replace( '#<nav class="nav-tab-wrapper[^"]*"[^>]*>.*?</nav>#s', '', $html ) ) ),
	    array( $want, count( $headings ) ) );
}

ck( 'and every section\'s anchor is an id of guide 31\'s own part of the built guide', $not_in_31, array() );

ck( 'and no two sections send the manager to the same part of the guide', count( $anchors_used ) === count( array_unique( $anchors_used ) ), true );

// The Resources section's address is another page's: the program's handbook page, which the
// Administrator Dashboard's Resources button opens and which has none of these parts.
ck( 'the Resources section still links the program\'s handbook page, whatever the intros link',
    WPCPM_Handbook_Assistant::guides()['administrator']['url'], 'https://make.wordpress.org/community/handbook/education/credits/' );

// With no published page at the path, an intro says its sentence and links nothing, rather than
// sending the manager to a page that is not there or that they would read as a draft.
$unlinked = array();

foreach ( array( 'no page' => null, 'a draft' => 'draft', 'a private page' => 'private', 'a trashed page' => 'trash' ) as $case => $status ) {
	$GLOBALS['pages'] = null === $status ? array() : array( 'program-manager-guide' => guide_page( $status ) );
	$intros           = array();

	foreach ( array_keys( $design_sections ) as $slug ) {
		preg_match_all( '#<p class="description wpcpm-settings__intro">(.*?)</p>#s', draw_settings( $slug ), $found );

		$intros = array_merge( $intros, $found[1] );
	}

	$unlinked[ $case ] = array( count( $intros ), count( preg_grep( '#<a\b#', $intros ) ), count( preg_grep( '/^[^<]+\.$/', $intros ) ) );
}

ck( 'with no published page at that path, whether there is none, a draft, a private or a trashed one, each of the 23 intros says its sentence and links nothing',
    $unlinked, array_fill_keys( array( 'no page', 'a draft', 'a private page', 'a trashed page' ), array( 23, 0, 23 ) ) );

// A site that publishes the guide elsewhere says where, and a filter that answers no address leaves
// the intros without a link, as a missing page does.
$GLOBALS['pages'] = array( 'program-manager-guide' => guide_page( 'publish' ) );
$heard            = array();

$GLOBALS['filters']['wpcpm_settings_guide_url'] = function ( $url, $page = null ) use ( &$heard ) {
	$heard[] = array( $url, $page instanceof WP_Post ? $page->ID : null );

	return 'https://guides.example/program-manager/#contents';
};
$moved = array_column( sections_of( draw_settings( 'connection' ) ), 'href' );

$GLOBALS['filters']['wpcpm_settings_guide_url'] = function () {
	return '';
};
$gone = array_column( sections_of( draw_settings( 'connection' ) ), 'href' );

unset( $GLOBALS['filters']['wpcpm_settings_guide_url'] );

ck( 'the wpcpm_settings_guide_url filter is given the page\'s address and the page, and the intros link the address it answers, its own fragment dropped for the section\'s',
    array( isset( $heard[0] ) ? $heard[0] : null, $moved ),
    array( array( $guide_home, 560 ), array( 'https://guides.example/program-manager/#airtable', 'https://guides.example/program-manager/#tables' ) ) );
ck( 'and a filter that answers no address leaves the intros without a link', $gone, array( '', '' ) );

$GLOBALS['pages'] = array();

// Mail saves nothing of its own: each control is a form posting to its own handler, and a Save there
// would post a tab that holds no settings.
WPCPM_Mail::$queued = 3;
$mail_html          = draw_settings( 'mail' );
WPCPM_Mail::$queued = 0;
$drawn              = array();

foreach ( forms_of( $mail_html ) as $form ) {
	$drawn[] = array(
		false !== strpos( $form['open'], 'action="https://example.test/wp-admin/admin-post.php"' ),
		preg_match( '#name="action" value="([^"]*)"#', $form['body'], $posted ) ? $posted[1] : null,
		preg_match( '#name="kind" value="([^"]*)"#', $form['body'], $posted ) ? $posted[1] : null,
		$form['tab'],
		$form['saves'],
	);
}

ck( 'the Mail tab has no Save: its four sample buttons are each a form posting to their own handler, one for each audience',
    $drawn,
    array(
        array( true, 'wpcpm_send_test_mail', 'student', null, 0 ),
        array( true, 'wpcpm_send_test_mail', 'mentor', null, 0 ),
        array( true, 'wpcpm_send_test_mail', 'institution', null, 0 ),
        array( true, 'wpcpm_send_test_mail', 'sponsor', null, 0 ),
    ) );
ck( 'and it draws what is waiting to be sent and the recent mail, with no Save anywhere on it',
    array( false !== strpos( $mail_html, '3 invitations are waiting to be sent.' ), false !== strpos( $mail_html, '<td>maciej@a8c.com</td>' ), substr_count( $mail_html, 'value="Save settings"' ) ),
    array( true, true, 0 ) );

// Each sample form carries its nonce under the name its handler reads it by, in a field with an id of
// its own: core's nonce field takes its id from its name, which gave four elements of the tab one id.
$mail_nonces = array();

foreach ( forms_of( $mail_html ) as $form ) {
	$mail_nonces[] = preg_match( '#<input type="hidden" id="([^"]*)" name="wpcpm_send_test_mail" value="([^"]*)" />#', $form['body'], $nonce ) ? array( $nonce[1], $nonce[2] ) : null;
}

ck( 'each sample form carries the nonce its handler checks, under the name the handler reads, each in a field with an id of its own',
    $mail_nonces,
    array(
        array( 'wpcpm_send_test_mail-student', 'nonce:wpcpm_send_test_mail' ),
        array( 'wpcpm_send_test_mail-mentor', 'nonce:wpcpm_send_test_mail' ),
        array( 'wpcpm_send_test_mail-institution', 'nonce:wpcpm_send_test_mail' ),
        array( 'wpcpm_send_test_mail-sponsor', 'nonce:wpcpm_send_test_mail' ),
    ) );

$twice = array();

foreach ( array_keys( $want_tabs ) as $slug ) {
	preg_match_all( '#\sid="([^"]*)"#', 'mail' === $slug ? $mail_html : draw_settings( $slug ), $ids );

	foreach ( array_count_values( $ids[1] ) as $id => $times ) {
		if ( $times > 1 ) {
			$twice[ $slug ][ $id ] = $times;
		}
	}
}

ck( 'and no tab of the screen gives two of its elements one id', $twice, array() );

// The Advanced tab: the eleven settings that had no control on any screen, each drawn with the value
// stored, and each held by its Save to the rule `WPCPM_Settings::save()` always held it to.
$advanced = WPCPM_Settings::scope_keys( 'advanced' );

store_settings( $defaults );

$stored        = WPCPM_Settings::save( array_intersect_key( $probe, array_flip( $advanced ) ), 'advanced' );
$advanced_html = draw_settings( 'advanced' );
$drawn         = array();
$want          = array();

foreach ( $advanced as $key ) {
	$drawn[ $key ] = drawn_value( $advanced_html, $key );
	$want[ $key ]  = is_array( $stored[ $key ] ) ? implode( "\n", $stored[ $key ] ) : (string) $stored[ $key ];
}

$still_default = array();

foreach ( $advanced as $key ) {
	if ( $stored[ $key ] === $defaults[ $key ] ) {
		$still_default[] = $key;
	}
}

ck( 'the Advanced tab draws each of its eleven settings with the value stored, none of them the default',
    array( count( $drawn ), $drawn, $still_default ),
    array( 11, $want, array() ) );

store_settings( $defaults );

press_tab(
	'advanced',
	array(
		'institution_new_stage'         => '   ',
		'institution_active_stages'     => " Confirmed \n\nConfirmed\n<b>Student</b>\n",
		'application_spam_days'         => '0',
		'application_rejected_days'     => '99999',
		'application_approved_days'     => '-5',
		'application_trusted_proxy'     => '203.0.113.0/24',
		'agreement_max_mb'              => '500',
		'agreement_uploads_per_day'     => '0',
		'agreement_generations_per_day' => '1000',
		'agreement_discard_days'        => '1',
		'invite_retention_days'         => '9999',
	)
);

$after = WPCPM_Settings::get();

ck( 'Save on the Advanced tab holds each setting to its rule: a blank starting stage back to its default with the notice, the stages cleaned like the status lists, each number clamped, and a range refused as the trusted proxy, which must be one address',
    array( array_intersect_key( $after, array_flip( $advanced ) ), $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null ),
    array(
        array(
            'institution_new_stage'         => 'First Contact Made',
            'institution_active_stages'     => array( 'Confirmed', 'Student' ),
            'application_spam_days'         => 1,
            'application_rejected_days'     => 3650,
            'application_approved_days'     => 0,
            'application_trusted_proxy'     => '',
            'agreement_max_mb'              => 50,
            'agreement_uploads_per_day'     => 1,
            'agreement_generations_per_day' => 100,
            'agreement_discard_days'        => 7,
            'invite_retention_days'         => 365,
        ),
        array( 'institution_new_stage' ),
    ) );

// The notice says why for each setting it put back, and only the reasons true of it: the starting
// stage filters no sync, so it is not told that a sync would read every row.
WPCPM_Flash::set( 'settings-defaults', array( 'institution_new_stage' ) );
ob_start();
WPCPM_Settings::render_notices();
$stage_notice = (string) ob_get_clean();

$GLOBALS['signed_in'] = 2;
WPCPM_Flash::set( 'settings-defaults', array( 'mentor_status', 'institution_new_stage' ) );
ob_start();
WPCPM_Settings::render_notices();
$both_notice = (string) ob_get_clean();

$GLOBALS['signed_in'] = 3;
WPCPM_Flash::set( 'settings-defaults', array( 'institution_active_stages' ) );
ob_start();
WPCPM_Settings::render_notices();
$stages_notice = (string) ob_get_clean();
unset( $GLOBALS['signed_in'] );

ck( 'the notice names a starting stage it put back, with its default, and gives it its own reason rather than the syncs\' one, both reasons when a sync\'s setting went back beside it, and the pipeline stages theirs, which filter no read either',
    array(
        false !== strpos( $stage_notice, 'Institution starting stage (now First Contact Made)' ),
        false !== strpos( $stage_notice, 'a sync would read every row' ),
        false !== strpos( $stage_notice, 'created in Airtable at the starting stage' ),
        false !== strpos( $both_notice, 'a sync would read every row' ) && false !== strpos( $both_notice, 'created in Airtable at the starting stage' ),
        false !== strpos( $stages_notice, 'count every institution as having left it' ),
        false !== strpos( $stages_notice, 'a sync would read every row' ),
    ),
    array( true, false, true, true, true, false ) );

// Each number says the limits its save holds it to, so the browser refuses what the save would
// quietly clamp, and a limit changed on one side and not the other fails here.
$drawn = array();
$want  = array();

foreach ( $advanced as $key ) {
	if ( ! is_int( $defaults[ $key ] ) ) {
		continue;
	}

	$drawn[ $key ] = preg_match( '#name="' . $key . '" value="[^"]*" min="(-?\d+)" max="(-?\d+)"#', $advanced_html, $limits ) ? array( (int) $limits[1], (int) $limits[2] ) : null;

	store_settings( $defaults );
	$floor = WPCPM_Settings::save( array( $key => '-999999' ), 'advanced' )[ $key ];
	$cap   = WPCPM_Settings::save( array( $key => '999999' ), 'advanced' )[ $key ];

	$want[ $key ] = array( $floor, $cap );
}

ck( 'each of the eight numbers on the Advanced tab says the limits its save holds it to', array( count( $drawn ), $drawn ), array( 8, $want ) );

// Where each Save goes: back to the tab it was pressed on, and from a tool's Settings section to the
// tool's own screen, where the section is.
$went = array();
$want = array();

foreach ( array_keys( $scopes ) as $scope ) {
	store_settings( $defaults );
	press_tab( $scope, array() );

	$went[ $scope ] = $GLOBALS['redirected_to'];
	$want[ $scope ] = 0 === strpos( $scope, 'tool:' ) ? $keeping[ substr( $scope, strlen( 'tool:' ) ) ] : $settings_home . '&tab=' . $scope;
}

ck( 'Save on each tab goes back to that tab, and in a tool\'s Settings section to the tool\'s own screen', $went, $want );

// A tool taken out of the registry, as a site's filter may, has no screen to go back to: its scope's
// Save, from a page drawn before, is saved as ever and goes back to the Settings screen, whose notice
// names no tool it cannot find.
store_settings( $defaults );
WPCPM_Tools::$removed = array( 'handbook' );
press_tab( 'tool:handbook', array( 'handbook_limit' => '35' ) );
$unregistered         = array( WPCPM_Settings::get()['handbook_limit'], $GLOBALS['redirected_to'], WPCPM_Settings_Screen::settings_messages( 'tool:handbook' )['saved'] );
WPCPM_Tools::$removed = array();

ck( 'the Save of a tool that is not registered is saved, goes back to the Settings screen, and names no tool',
    $unregistered, array( 35, $settings_home, array( 'success', 'Settings saved.' ) ) );

// And the notice it leaves is read there: on the tool's screen, above its Settings section, naming the
// tool. A flash is read once a request, so each tool's is read by a person of its own.
$notices = array();
$signed  = 10;

foreach ( array_keys( $keeping ) as $id ) {
	$GLOBALS['signed_in'] = ++$signed;
	store_settings( $defaults );
	press_tab( 'tool:' . $id, array() );

	$html           = draw_tool_settings( $id );
	$notices[ $id ] = preg_match( '#^<div class="notice notice-success is-dismissible"><p>([^<]*)</p></div><div class="wpcpm-card"><h2 id="settings">Settings</h2>#', $html, $said ) ? $said[1] : null;
}

unset( $GLOBALS['signed_in'] );

ck( 'and the notice a tool\'s Save leaves is printed on the tool\'s screen, above its Settings section, naming the tool',
    $notices,
    array(
        'mentor-status-checker' => 'Settings saved for the Mentor Status Checker tool.',
        'duplicate-finder'      => 'Settings saved for the Student Duplicate Finder tool.',
        'handbook'              => 'Settings saved for the Need help? tool.',
    ) );

$GLOBALS['umeta'] = array();

echo "\n=== A setting that holds a choice from a known list is drawn from the list ===\n";

// The Settings design of 28 September 2026: the settings typed by hand although each holds a
// choice from a known list are drawn as selects and checkbox lists, each falling back to the text
// input it replaced when its list cannot be read. bin/test-settings-choices.php draws each through
// the real screen with its list answering and refused; this pins which row helper draws which
// setting, the Settings screen's and the tools' Settings sections', so a field moved back to a typed
// input fails here by name.
$drawn_by = array();

foreach ( array( 'text_row', 'select_row', 'checklist_row', 'reviewers_row' ) as $helper ) {
	preg_match_all( "/WPCPM_Settings_Rows::" . $helper . "\(\s*\n?\s*'([a-z_]+)'/", $forms_source, $m );

	$drawn_by[ $helper ] = array_values( array_unique( $m[1] ) );
	sort( $drawn_by[ $helper ] );
}

$chosen = array( 'base_id', 'mentors_table', 'reports_table', 'students_table', 'institutions_table', 'teams_table', 'tutors_table', 'feedback_table', 'countries_table', 'team_members_table', 'sponsors_table', 'institutions_name_field', 'teams_name_field', 'countries_name_field', 'sponsors_name_field', 'mentor_status', 'checker_source_status', 'checker_target_status', 'institution_new_stage' );
$offered_by_select = array_merge( $chosen, array( 'handbook_model' ) );
sort( $chosen );
sort( $offered_by_select );

ck( 'the base, the ten tables, the four name columns, the three statuses and the starting stage are each one choice of a select, and the model too once the provider lists models to choose from',
    $drawn_by['select_row'], $offered_by_select );

// The provider lists no models, so the only list there could be is the default beside the model
// saved, which looks like a choice and offers none. The field is the text input it always was,
// holding the model saved.
store_settings( array_merge( $defaults, array( 'handbook_model' => 'gemini-2.5-flash' ) ) );
$model_row = draw_tool_settings( 'handbook' );
store_settings( $defaults );

ck( 'the model is typed while the provider lists no models, the field holding the model saved, with no select',
    array( false !== strpos( $model_row, '<input type="text" id="wpcpm-handbook_model" name="handbook_model" value="gemini-2.5-flash" class="regular-text" autocomplete="off" />' ), preg_match( '#<select\b[^>]*name="handbook_model"#', $model_row ) ),
    array( true, 0 ) );
ck( 'the two status lists and the pipeline stages are checkbox lists',
    $drawn_by['checklist_row'], array( 'institution_active_stages', 'past_statuses', 'student_statuses' ) );
ck( 'the three reviewer lists are the program managers to tick, with a line for other addresses',
    $drawn_by['reviewers_row'], array( 'agreement_notify', 'report_notify', 'sponsor_notify' ) );
// The reviewer lists are drawn by the two helpers every other field is, not by markup of their own:
// the program managers through the checkbox list, and with nobody to list, the text input.
$rows_source      = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-rows.php' );
$reviewers_source = method_source( $rows_source, 'reviewers_row' );

ck( 'a reviewer list is drawn through the checkbox list and the text input rows, and prints no input of its own',
    array( false !== strpos( $reviewers_source, 'self::checklist_row(' ), false !== strpos( $reviewers_source, 'self::text_row(' ), false !== strpos( $reviewers_source, '<input' ), false !== strpos( $reviewers_source, '<fieldset' ) ),
    array( true, true, false, false ) );


ck( 'and the course title and slug are still typed, since learn.wordpress.org has no list to read them from',
    array( in_array( 'checker_course_title', $drawn_by['text_row'], true ), in_array( 'checker_course_slug', $drawn_by['text_row'], true ) ),
    array( true, true ) );

// The save's side of the boxes. A checkbox list posts its ticked boxes as an array, in the order the
// lists draw them, which is not the order the list is stored in; a textarea posts its lines in the
// order somebody typed them. So boxes holding the statuses the page drew leave the list as stored,
// and a textarea whose lines were put in another order is a change, as it always was.
$drawn_six = WPCPM_Settings::defaults()['student_statuses'];
$shuffled  = array_reverse( $drawn_six );

ck( 'Currently mentoring posted as boxes holding the statuses the page drew, in another order, is the list unchanged, which leaves it as stored',
    WPCPM_Settings::student_statuses_from( array( 'student_statuses' => array_merge( array( '' ), $shuffled ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $drawn_six ) ),
    null );
ck( 'while the same statuses typed one a line in another order are a change, written as typed',
    WPCPM_Settings::student_statuses_from( array( 'student_statuses' => implode( "\n", $shuffled ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $drawn_six ) ),
    $shuffled );
ck( 'and boxes that changed the list keep the statuses still ticked in the order they are stored in, the empty value that makes an all-clear list post dropped',
    WPCPM_Settings::student_statuses_from( array( 'student_statuses' => array( '', 'Paused', 'In Sensei' ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $drawn_six ) ),
    array( 'In Sensei', 'Paused' ) );
ck( 'as do boxes posted with no list drawn beside them, the newly ticked after the rest',
    WPCPM_Settings::student_statuses_from( array( 'student_statuses' => array( '', 'Graduate', 'Paused', 'In Sensei' ) ) ),
    array( 'In Sensei', 'Paused', 'Graduate' ) );

// A status gained since the page was drawn, a track published meanwhile: boxes that changed the list
// keep it, where the stored list holds it, as the textarea's save keeps it after the rest.
$gained_stored = array_merge( array_slice( $drawn_six, 0, 2 ), array( 'Mentor Track' ), array_slice( $drawn_six, 2 ) );
store_settings( array_merge( $defaults, array( 'student_statuses' => $gained_stored ) ) );

ck( 'and boxes that changed a list drawn before a status joined it keep that status where the stored list holds it',
    WPCPM_Settings::student_statuses_from( array( 'student_statuses' => array_values( array_diff( $drawn_six, array( 'Paused' ) ) ), WPCPM_Settings::FIELD_DRAWN_STATUSES => $drawn_six ) ),
    array_values( array_diff( $gained_stored, array( 'Paused' ) ) ) );

store_settings( $defaults );

// A reviewer list posted as boxes and the Other addresses line: each entry is split as the text is,
// so the list saved is the comma list the text input saved for the same addresses.
store_settings( $defaults );

ck( 'a reviewer list posted as boxes and an Other addresses line saves the comma list its text input saved for the same addresses',
    array(
        WPCPM_Settings::save( array( 'agreement_notify' => array( 'Ana@Program.example', 'pat@program.example', 'Board@Partner.example, second@partner.example second@partner.example' ) ), 'institutions' )['agreement_notify'],
        WPCPM_Settings::save( array( 'agreement_notify' => 'Ana@Program.example, pat@program.example, Board@Partner.example, second@partner.example second@partner.example' ), 'institutions' )['agreement_notify'],
    ),
    array_fill( 0, 2, 'ana@program.example,pat@program.example,board@partner.example,second@partner.example' ) );

store_settings( $defaults );

echo "\n=== Help is one sentence under each control, and the rest is in a Details fold ===\n";

// The Settings design of 28 September 2026: one sentence of help under each control, and whatever
// else a row has to say, the reasons and the consequences, in a Details fold under it, closed until
// somebody opens it. Every tab is drawn, the Security tab in each of its states and the Mail tab
// with invitations waiting, and each line a person reads is measured there, where it is printed.

/**
 * The paragraphs and the notes in some markup, each its class, the words a person reads, and its
 * markup.
 *
 * @param string $html The markup.
 * @return array[]
 */
function notes_of( $html ) {
	preg_match_all( '#<p\b([^>]*)>(.*?)</p>|<span class="description">(.*?)</span>#s', $html, $found, PREG_SET_ORDER );

	$notes = array();

	foreach ( $found as $note ) {
		$span    = isset( $note[3] ) && '' !== $note[3];
		$inner   = $span ? $note[3] : $note[2];
		$class   = $span ? 'span' : ( preg_match( '#class="([^"]*)"#', $note[1], $c ) ? $c[1] : '' );
		$notes[] = array( $class, trim( html_entity_decode( strip_tags( $inner ), ENT_QUOTES ) ), trim( $inner ) );
	}

	return $notes;
}

/**
 * The rows a drawn screen has: each row's label, its cell, what the cell says outside any fold, and
 * its folds.
 *
 * @param string $html The screen.
 * @return array[]
 */
function rows_of( $html ) {
	preg_match_all( '#<tr><th scope="row">(.*?)</th><td>(.*?)</td></tr>#s', $html, $found, PREG_SET_ORDER );

	$rows = array();

	foreach ( $found as $row ) {
		preg_match_all( '#<details\b.*?</details>#s', $row[2], $folds );

		$rows[] = array(
			'label' => trim( html_entity_decode( strip_tags( $row[1] ), ENT_QUOTES ) ),
			'cell'  => $row[2],
			'said'  => notes_of( preg_replace( '#<details\b.*?</details>#s', '', $row[2] ) ),
			'folds' => $folds[0],
		);
	}

	return $rows;
}

/**
 * One row's help, the paragraphs a person reads under its control, and what its fold holds.
 *
 * @param string $html  The screen.
 * @param string $label The row's label.
 * @return array|null The help's paragraphs and the fold's, or null when the screen has no such row.
 */
function help_of( $html, $label ) {
	foreach ( rows_of( $html ) as $row ) {
		if ( $label !== $row['label'] ) {
			continue;
		}

		$help = array();
		$fold = array();

		foreach ( $row['said'] as $note ) {
			if ( 'description' === $note[0] ) {
				$help[] = $note[1];
			}
		}

		foreach ( $row['folds'] as $details ) {
			foreach ( notes_of( $details ) as $note ) {
				$fold[] = $note[1];
			}
		}

		return array( $help, $fold );
	}

	return null;
}

/**
 * How many sentences some words are: one, and one more for each full stop, question or exclamation
 * mark that a capital, a figure or a quote follows.
 *
 * @param string $words The words.
 * @return int
 */
function sentences_in( $words ) {
	return 1 + preg_match_all( '/[.?!]["\')]*\s+(?=[A-Z0-9"(])/', $words );
}

store_settings( $defaults );

// Drawn with no page at the guide's path, so an intro is its sentence alone, which is what is
// measured of it.
$states = array();

foreach ( array_keys( $want_tabs ) as $slug ) {
	$states[ $slug ] = draw_settings( $slug );
}

WPCPM_Two_Factor::$available                         = false;
$states['security, with the Two Factor plugin off'] = draw_settings( 'security' );
WPCPM_Two_Factor::$available                         = true;
WPCPM_Two_Factor::$roles                             = array( array( 'label' => 'Administrator', 'covered' => 1, 'total' => 2, 'app' => 1 ) );
$states['security, with its count']                 = draw_settings( 'security' );
WPCPM_Two_Factor::$roles                             = array();
WPCPM_Mail::$queued                                  = 3;
$states['mail, with invitations waiting']           = draw_settings( 'mail' );
WPCPM_Mail::$queued                                  = 0;

// The people who manage the program have one name on every screen, Administrators, the role they
// hold, and the Security row gives them that name too, first among the roles a code is asked of.
preg_match_all( '#<label><input type="checkbox" name="two_factor_roles\[\]" value="([^"]*)"[^>]*> ([^<]*)</label>#', $states['security'], $role_boxes, PREG_SET_ORDER );

ck( 'the Security row offers the second factor to Administrators, by the name every screen gives the people who manage the program, then to each of the plugin\'s roles by its own',
    array_column( $role_boxes, 2, 1 ),
    array(
		'administrator'     => 'Administrators',
		'wpcpm_student'     => 'Student',
		'wpcpm_mentor'      => 'Mentor',
		'wpcpm_institution' => 'Institution',
		'wpcpm_sponsor'     => 'Sponsor',
	) );

// And each tool's Settings section, on the tool's own screen, held to the same rules.
foreach ( array_keys( $keeping ) as $id ) {
	$states[ 'tool: ' . $id ] = draw_tool_settings( $id );
}

// And Need help?'s Who can ask names them so too, beside the mentors or alone.
preg_match_all( '#<label><input type="radio" name="handbook_access" value="([^"]*)"[^>]*> ([^<]*)</label>#', $states['tool: handbook'], $asking, PREG_SET_ORDER );

ck( 'and Need help?\'s Who can ask names them Administrators too, beside the mentors or alone',
    array_column( $asking, 2, 1 ),
    array(
		'mentor'  => 'Mentors and administrators',
		'program' => 'Students and institutions as well',
		'any'     => 'Anybody logged in to this site',
		'manage'  => 'Administrators only',
	) );

$too_long    = array();
$two_or_more = array();
$bad_folds   = array();
$fold_names  = array();
$folds       = 0;

$rows_file_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-rows.php' );

// Two rows print two paragraphs of help by design, each one sentence: Past students, whose group of
// the live tracks' statuses says once, under its name, why each of its boxes is locked; and the
// Security row with the Two Factor plugin off, which says above the boxes that nobody is asked, and
// under them what turning the plugin on does.
$two_by_design = array( 'Past students', 'security, with the Two Factor plugin off: Roles that must use it' );

foreach ( $states as $state => $html ) {
	$in_rows = 0;

	foreach ( rows_of( $html ) as $row ) {
		$helps = 0;

		foreach ( $row['said'] as $note ) {
			if ( strlen( $note[1] ) > 160 ) {
				$too_long[ $state . ': ' . $row['label'] ][] = $note[1];
			}

			// The row's help, not a warning, the sentence saying why a field is typed, or the
			// address of a page.
			if ( 'description' !== $note[0] || preg_match( '#^<a\b[^>]*>[^<]*</a>$#', $note[2] ) ) {
				continue;
			}

			++$helps;

			if ( sentences_in( $note[1] ) > 1 ) {
				$two_or_more[ $state . ': ' . $row['label'] ][] = $note[1];
			}
		}

		// One sentence under the control, not one sentence a paragraph: a second paragraph of help
		// is a second sentence all the same.
		if ( $helps > 1 && ! in_array( $row['label'], $two_by_design, true ) && ! in_array( $state . ': ' . $row['label'], $two_by_design, true ) ) {
			$two_or_more[ $state . ': ' . $row['label'] ][] = $helps . ' paragraphs of help';
		}

		foreach ( $row['folds'] as $details ) {
			++$folds;
			++$in_rows;

			// "Details" to the eye, and to a screen reader the row it belongs to, since a tab's list
			// of controls would otherwise read "Details" once a row.
			if ( ! preg_match( '#^<details class="wpcpm-settings__details"><summary>Details<span class="screen-reader-text"> about ([^<]*)</span></summary>(?!</details>).+</details>$#s', $details, $named ) ) {
				$bad_folds[ $state ][] = $row['label'] . ': not a closed Details fold, named for a screen reader, holding something';
			} elseif ( html_entity_decode( $named[1], ENT_QUOTES ) !== $row['label'] ) {
				$bad_folds[ $state ][] = $row['label'] . ': its fold is named for "' . html_entity_decode( $named[1], ENT_QUOTES ) . '"';
			} else {
				$fold_names[ $state ][] = $named[1];
			}
		}

		if ( count( $row['folds'] ) > 1 ) {
			$bad_folds[ $state ][] = $row['label'] . ': more than one fold';
		}

		if ( array() !== $row['folds'] && ! preg_match( '#</details>$#', $row['cell'] ) ) {
			$bad_folds[ $state ][] = $row['label'] . ': the fold is not the last thing under the control';
		}
	}

	// The screen's own lines, outside the rows: the intros' sentences, the Connection tab's line
	// over its button, and the Mail tab's lines.
	foreach ( notes_of( preg_replace( array( '#<tr><th scope="row">.*?</td></tr>#s', '#<details\b.*?</details>#s' ), '', $html ) ) as $note ) {
		if ( false !== strpos( $note[0], 'description' ) && strlen( $note[1] ) > 160 ) {
			$too_long[ $state ][] = $note[1];
		}
	}

	// A tool's section may open, as a tab's intro never does, on a sentence with its fold under it,
	// the fold named for the section it opens.
	$intro_folds = 0 === strpos( $state, 'tool: ' ) ? preg_match_all( '#<h2 id="settings">Settings</h2><p class="description">[^<]*</p><details class="wpcpm-settings__details"><summary>Details<span class="screen-reader-text"> about Settings</span></summary>(?!</details>).+?</details><form\b#s', $html ) : 0;

	if ( substr_count( $html, '<details' ) !== $in_rows + $intro_folds ) {
		$bad_folds[ $state ][] = 'a fold outside a row';
	}
}

ck( 'no line of help any tab or tool\'s Settings section prints outside a fold is longer than 160 characters', $too_long, array() );
ck( 'and each row\'s help outside its fold is one sentence, in one paragraph but for the two rows that print a second by design',
    $two_or_more, array() );
ck( 'and every fold is one closed Details fold, its summary "Details" to the eye and to a screen reader "Details about" its row\'s label, last under its row\'s control, holding what the row\'s help leaves out',
    array( $folds > 20, $bad_folds ), array( true, array() ) );

// One pattern, one helper: a guide link and a fold's summary each add, for a screen reader alone, what
// they belong to, so no two of a screen's links, and no two of its folds, read alike.
$read_alike = array();

foreach ( $fold_names as $state => $names ) {
	if ( count( $names ) !== count( array_unique( $names ) ) ) {
		$read_alike[] = $state . ': folds';
	}
}

$GLOBALS['pages'] = array( 'program-manager-guide' => guide_page( 'publish' ) );

foreach ( array_keys( $design_sections ) as $slug ) {
	$hidden = array_column( sections_of( draw_settings( $slug ) ), 'hidden' );

	if ( count( $hidden ) !== count( array_unique( $hidden ) ) || in_array( '', $hidden, true ) ) {
		$read_alike[] = $slug . ': guide links';
	}
}

$GLOBALS['pages'] = array();

ck( 'the guide links and the folds say what they belong to through one helper, and no two links or folds of one screen read alike to a screen reader',
    array( substr_count( method_source( $screen_source, 'section_heading' ), 'WPCPM_Settings_Rows::screen_reader_about( ' ), substr_count( method_source( $rows_file_source, 'open_details' ), 'self::screen_reader_about( ' ), $read_alike ),
    array( 1, 1, array() ) );

// The Need help? section's intro, 275 characters on the Settings screen's card: one sentence on the
// tool's own screen, and the rest in the fold under it, every word of it kept.
preg_match( '#<h2 id="settings">Settings</h2><p class="description">([^<]*)</p>(<details class="wpcpm-settings__details"><summary>Details<span class="screen-reader-text"> about Settings</span></summary>.*?</details>)<form\b#s', $states['tool: handbook'], $handbook_intro );

ck( 'the Need help? section\'s intro, once 275 characters, is one sentence, and the rest is in the fold under it',
    isset( $handbook_intro[1] ) ? array( html_entity_decode( $handbook_intro[1], ENT_QUOTES ), array_column( notes_of( $handbook_intro[2] ), 1 ) ) : null,
    array(
        'A question box for people on the program, answered from the WordPress documentation.',
        array( 'The AI provider below does the searching, so nothing is stored on this site, and without a provider there is no answer at all. Each question, and the pages found for it, go to that company.' ),
    ) );

// The texts that ran past 300 characters: each is one sentence now, and the rest is in its fold,
// every word of it kept.
ck( 'the texts that ran past 300 characters are one sentence each, the rest in its fold',
    array(
        'Schema token'                       => help_of( $states['connection'], 'Schema token' ),
        'Invitation emails'                  => help_of( $states['people'], 'Invitation emails' ),
        'Automatic sync'                     => help_of( $states['people'], 'Automatic sync' ),
        'Enrollment lists from institutions' => help_of( $states['institutions'], 'Enrollment lists from institutions' ),
        'Create accounts automatically'      => help_of( $states['institutions'], 'Create accounts automatically' ),
    ),
    array(
        'Schema token'                       => array(
            array( 'Optional, and used by the Track Builder alone: the token that creates a track\'s columns when it is published.' ),
            array( 'It needs the "schema.bases:write" scope and must belong to somebody with the base creator role on the base. Leave blank to keep the current one, or type remove to take it away. Without it, publishing lists the columns for somebody to create by hand.' ),
        ),
        'Invitation emails'                  => array(
            array( 'Off by default: a first sync creates around ninety accounts at once, so leave this off unless you mean to email all of them.' ),
            array( 'Invitations are queued and sent a few at a time rather than all inside the sync, so a mail limit cannot swallow half of them unnoticed. You can also invite people one at a time from the Mentors and Students screens, or tick several in the Students or Mentors screen\'s list and invite them together.' ),
        ),
        'Automatic sync'                     => array(
            array( 'Runs the students, mentors and institutions syncs every three hours, the mentors half an hour after the students; the sponsors sync runs regardless.' ),
            array( 'The student rows carry what people are shown on their cards, and the students run is the expensive one, reading a WordPress.org profile per mentor, cached for twelve hours; the mentors run reads three Airtable tables and no WordPress.org profile. A run already in progress is left to finish rather than restarted. The students and mentors runs can also be run by hand from the Students and Mentors screens.' ),
        ),
        'Enrollment lists from institutions' => array(
            array( 'Adds an "Enroll students" section to the Institution Dashboard, where a school chooses the program and the term and then adds one student or sends a CSV.' ),
            array(
                'The list is read and checked against the program records, and the school sees what was understood before anything is created. While this is off the section does not appear at all.',
                'Ceilings per institution: 5 checks an hour and 600 students a day. Files are read and never stored.',
            ),
        ),
        'Create accounts automatically'      => array(
            array( 'From the Contact Email Airtable holds, and only for a Confirmed institution whose agreement is recorded and that has never had a member.' ),
            array( 'An address that already belongs to an account is left alone and named on the Institutions screen. With this off, accounts are created only when somebody presses the button there.' ),
        ),
    ) );

preg_match( '#<tr><th scope="row">Token scopes</th>.*?</tr>#s', $states['connection'], $scopes_row );
preg_match( '#<tr><th scope="row">Automatic sync</th>.*?</tr>#s', $states['people'], $sync_row );

ck( 'the Token scopes row says one sentence, and its fold holds the scopes, each with what it is for, the schema scope with every list the screens draw from Airtable, and the line saying the list is the reference',
    isset( $scopes_row[0] ) ? $scopes_row[0] : null,
    '<tr><th scope="row">Token scopes</th><td><p class="description">Set the scopes listed under Details on the token itself at airtable.com/create/tokens, and give it access to the WPCredits base.</p><details class="wpcpm-settings__details"><summary>Details<span class="screen-reader-text"> about Token scopes</span></summary><ul class="wpcpm-scopes"><li><code>data.records:read</code> <strong>Required</strong><br /><span class="description">Reading mentors, students and tutors.</span></li><li><code>data.records:write</code> <strong>Required by the Mentor Status Checker</strong><br /><span class="description">Changing a mentor&#039;s status when you promote them. Without it the tool can still run in report-only mode, but promoting fails.</span></li><li><code>schema.bases:read</code> <strong>Optional</strong><br /><span class="description">Listing the bases, tables, columns, statuses and stages the Settings tabs and the Mentor Status Checker&#039;s screen offer, and reading each column&#039;s description from Airtable. Without it each of those settings is typed, and the built-in descriptions are shown instead.</span></li></ul><p>Scopes cannot be checked from here without writing to the base, so this list is the reference.</p></details></td></tr>' );
ck( 'and the Automatic sync row its switch, one sentence, and its fold',
    isset( $sync_row[0] ) ? $sync_row[0] : null,
    '<tr><th scope="row">Automatic sync</th><td><label><input type="checkbox" name="auto_sync" value="1" checked=\'checked\'> Read Airtable on a schedule</label><p class="description">Runs the students, mentors and institutions syncs every three hours, the mentors half an hour after the students; the sponsors sync runs regardless.</p><details class="wpcpm-settings__details"><summary>Details<span class="screen-reader-text"> about Automatic sync</span></summary><p>The student rows carry what people are shown on their cards, and the students run is the expensive one, reading a WordPress.org profile per mentor, cached for twelve hours; the mentors run reads three Airtable tables and no WordPress.org profile. A run already in progress is left to finish rather than restarted. The students and mentors runs can also be run by hand from the Students and Mentors screens.</p></details></td></tr>' );

// The save keeps one valid address or none (`WPCPM_Settings::save()`), so a range typed in comes back
// empty: the help says so where the address is typed.
ck( 'the trusted proxy\'s help says it is one address, not a range, and its fold what a range is saved as',
    help_of( $states['advanced'], 'Trusted proxy' ),
    array(
        array( 'The one IP address, not a range, of the proxy whose forwarded header the application forms believe for a sender\'s address.' ),
        array( 'The forms\' limits count by the sender\'s address, so leave it empty unless the site sits behind a proxy: anybody can send that header. Anything but one address, a range included, is saved as empty.' ),
    ) );

// Two helps joined from two sentences into one keep the referent the second sentence had: it is the
// starting stage that cannot be left blank, not the application, and a rejected application is
// deleted that many days after the decision, not after anything else.
ck( 'the starting stage\'s help says the stage cannot be left blank, and the rejected applications\' help from when the days are counted',
    array( help_of( $states['advanced'], 'Institution starting stage' )[0], help_of( $states['advanced'], 'Rejected kept for (days)' )[0] ),
    array(
        array( 'The Current Stage an institution is created at in Airtable when its application is approved; the stage cannot be left blank.' ),
        array( 'A rejected application, or one the checks held, is deleted this many days after the decision: long enough to recognize the same applicant applying again.' ),
    ) );

// The Token scopes help sends the manager to the fold by the word the fold's summary shows, one string
// for the translators, so a translation cannot name the fold one way and show it another.

ck( 'the Token scopes help names its fold by the word the fold shows, the one string the rows class gives for it',
    array( substr_count( method_source( $screen_source, 'render_scopes_row' ), 'WPCPM_Settings_Rows::details_word()' ), substr_count( $rows_file_source, "__( 'Details', 'wpcredits-program-manager' )" ), substr_count( $screen_source, 'listed under Details' ) ),
    array( 1, 1, 0 ) );

// A section whose heading is its only row's label says the same thing twice, and a section named as
// a tab is ("Mail" on the Sponsors tab, beside the Mail tab) reads as a way to that tab.
$repeated = array();

foreach ( array_keys( $design_sections ) as $slug ) {
	foreach ( preg_split( '#(?=<h2>)#', preg_replace( '#<nav class="nav-tab-wrapper[^"]*"[^>]*>.*?</nav>#s', '', $states[ $slug ] ) ) as $section ) {
		if ( ! preg_match( '#^<h2>([^<]*)</h2>#', $section, $heading ) ) {
			continue;
		}

		$heading = html_entity_decode( $heading[1], ENT_QUOTES );

		if ( in_array( $heading, array_column( rows_of( $section ), 'label' ), true ) || in_array( $heading, $want_tabs, true ) ) {
			$repeated[ $want_tabs[ $slug ] ][] = $heading;
		}
	}
}

ck( 'no section\'s heading is the label of a row it holds, or the name of a tab', $repeated, array() );

$GLOBALS['umeta'] = array();

echo "\n=== The stylesheet: the intros' spacing applies, and a fold reads as something to open ===\n";

/**
 * The rules of a stylesheet, comments dropped: each selector, one at a time, with its declarations.
 *
 * @param string $css The stylesheet.
 * @return array[] Each: the selector, and its declarations as property => value.
 */
function css_rules( $css ) {
	preg_match_all( '#([^{}]+)\{([^}]*)\}#', preg_replace( '#/\*.*?\*/#s', '', $css ), $found, PREG_SET_ORDER );

	$rules = array();

	foreach ( $found as $rule ) {
		$declared = array();

		foreach ( array_filter( array_map( 'trim', explode( ';', $rule[2] ) ) ) as $declaration ) {
			list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) + array( '', '' ) );
			$declared[ strtolower( $property ) ] = $value;
		}

		foreach ( array_map( 'trim', explode( ',', $rule[1] ) ) as $selector ) {
			$rules[] = array( preg_replace( '/\s+/', ' ', $selector ), $declared );
		}
	}

	return $rules;
}

/**
 * A selector's specificity, as a browser ranks it: its ids, then its classes, attributes and
 * pseudo-classes, then its elements and pseudo-elements.
 *
 * @param string $selector One selector.
 * @return int[]
 */
function specificity( $selector ) {
	return array(
		preg_match_all( '/#[\w-]+/', $selector ),
		preg_match_all( '/\.[\w-]+|\[[^\]]*\]|(?<!:):(?!:)[\w-]+/', $selector ),
		preg_match_all( '/(?:^|[\s>+~])[a-z][\w-]*/i', $selector ) + preg_match_all( '/::[\w-]+/', $selector ),
	);
}

$admin_css = css_rules( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' ) );
$intro_css = array();
$fold_css  = array();

foreach ( $admin_css as $rule ) {
	if ( false !== strpos( $rule[0], 'wpcpm-settings__intro' ) && isset( $rule[1]['margin'] ) ) {
		$intro_css[] = array( $rule[0], specificity( $rule[0] ) >= specificity( 'p.description' ) );
	}

	if ( '.wpcpm-settings__details summary' === $rule[0] ) {
		$fold_css[] = array( $rule[1]['color'] ?? null, $rule[1]['cursor'] ?? null );
	}
}

// Core's `p.description { margin: 2px 0 5px; }` (wp-admin/css/edit.css) outranks a class alone, so a
// rule for the intro's class alone never applied; the element and the class together rank with
// core's, and the plugin's stylesheet is printed after core's.
ck( 'the intros\' spacing rule is at least as specific as core\'s p.description, so it applies',
    $intro_css, array( array( 'p.wpcpm-settings__intro', true ) ) );
ck( 'and a fold\'s summary is a line in WordPress\'s link color that the pointer shows can be clicked',
    $fold_css, array( array( '#2271b1', 'pointer' ) ) );

echo "\n=== One name for each thing: the title, the landing pages, the leaving rules ===\n";

// The Settings design of 28 September 2026: the screen is titled with its name under the plugin's
// menu, every audience has a landing page by that name, and the four rules for somebody who leaves
// offer the same two answers in the same order.

store_settings( $defaults );

preg_match_all( '#<h1>(.*?)</h1>#s', draw_settings( 'connection' ), $title );

ck( 'the screen\'s title is its name under the WPCredits Program menu, Settings, as the menu titles the page',
    $title[1], array( 'Settings' ) );

/**
 * A landing page's row as drawn: its label, the address printed under its switch, and the sentence
 * printed in place of the address when the page is missing.
 *
 * @param string $html The tab.
 * @param string $name The switch's name.
 * @return array|null Null when the tab draws no such switch.
 */
function landing_row( $html, $name ) {
	foreach ( rows_of( $html ) as $row ) {
		if ( false === strpos( $row['cell'], 'name="' . $name . '"' ) ) {
			continue;
		}

		return array(
			$row['label'],
			preg_match( '#<p class="description"><a href="([^"]*)">\1</a></p>#', $row['cell'], $address ) ? $address[1] : null,
			preg_match( '#<p class="description wpcpm-warning">([^<]*)</p>#', $row['cell'], $missing ) ? html_entity_decode( $missing[1], ENT_QUOTES ) : null,
		);
	}

	return null;
}

$landing_tabs = array(
	'mentor_home'      => 'people',
	'student_home'     => 'people',
	'institution_home' => 'institutions',
	'sponsor_home'     => 'sponsors',
);

$published = array();

foreach ( $landing_tabs as $name => $tab ) {
	$published[ $name ] = landing_row( draw_settings( $tab ), $name );
}

WPCPM_Mentors_Dashboard::$url      = '';
WPCPM_Students_Dashboard::$url     = '';
WPCPM_Institutions_Dashboard::$url = '';
WPCPM_Sponsors_Dashboard::$url     = '';

$missing = array();

foreach ( $landing_tabs as $name => $tab ) {
	$missing[ $name ] = landing_row( draw_settings( $tab ), $name );
}

WPCPM_Mentors_Dashboard::$url      = 'https://example.test/mentor-report-card/';
WPCPM_Students_Dashboard::$url     = 'https://example.test/student-dashboard/';
WPCPM_Institutions_Dashboard::$url = 'https://example.test/institution-dashboard/';
WPCPM_Sponsors_Dashboard::$url     = 'https://example.test/sponsor-dashboard/';

ck( 'each audience\'s landing page is called its landing page, the sponsors\' too, and each row prints its page\'s address under the switch',
    $published,
    array(
        'mentor_home'      => array( 'Mentor landing page', 'https://example.test/mentor-report-card/', null ),
        'student_home'     => array( 'Student landing page', 'https://example.test/student-dashboard/', null ),
        'institution_home' => array( 'Institution landing page', 'https://example.test/institution-dashboard/', null ),
        'sponsor_home'     => array( 'Sponsor landing page', 'https://example.test/sponsor-dashboard/', null ),
    ) );

ck( 'and with its page missing, each says so in the same words',
    $missing,
    array_map(
        function ( $label ) {
            return array( $label, null, 'The page is missing: re-activate the plugin to recreate it.' );
        },
        array(
            'mentor_home'      => 'Mentor landing page',
            'student_home'     => 'Student landing page',
            'institution_home' => 'Institution landing page',
            'sponsor_home'     => 'Sponsor landing page',
        )
    ) );

// The same words because the same code: the four landing pages are drawn by one row, and each row that
// prints a page, the four and the two application forms, prints its address, or the sentence saying
// it is missing, through one line of the rows class.
preg_match_all( "/WPCPM_Settings_Rows::landing_row\(\s*'([a-z_]+)'/", $screen_source, $landing_drawn );

ck( 'the four landing pages are drawn by the one landing row, and the sentence saying a page is missing is written once, in the rows\' page line',
    array( $landing_drawn[1], substr_count( $screen_source . $rows_file_source, 'The page is missing: re-activate the plugin to recreate it.' ), substr_count( $screen_source, 'WPCPM_Settings_Rows::page_line( ' ) ),
    array( array( 'mentor_home', 'student_home', 'institution_home', 'sponsor_home' ), 1, 2 ) );

// The student's page is the Student Report Card, as the toolbar names it for the student; the name
// it had before, My Program, is one the page no longer carries.
$student_row = null;

foreach ( rows_of( draw_settings( 'people' ) ) as $row ) {
	if ( 'Student landing page' === $row['label'] ) {
		$student_row = $row;
	}
}

// The switch's words, then its help: the row's first line of help, the page's address after it.
ck( 'the Student landing page names the Student Report Card, as the Mentor landing page names the Mentor Report Card',
    null === $student_row ? null : array(
        preg_match( '#name="student_home" value="1"[^>]*> ([^<]*)</label>#', $student_row['cell'], $switch ) ? html_entity_decode( $switch[1], ENT_QUOTES ) : null,
        help_of( draw_settings( 'people' ), 'Student landing page' )[0][0],
        false !== strpos( $student_row['cell'], 'My Program' ),
    ),
    array(
        'Use the Student Report Card page as the student dashboard',
        'Students go there when they log in and in place of the wp-admin Dashboard, and get a "Student Report Card" link in the toolbar.',
        false,
    ) );

/**
 * A leaving rule's answers as drawn, in their order: each one's value, its words, and whether it is
 * ticked; null when the rule's radios are not the one fieldset of the row.
 *
 * @param string $html The tab.
 * @param string $name The rule's name.
 * @return array[]|null
 */
function answers_of( $html, $name ) {
	$quoted = preg_quote( $name, '#' );

	if ( ! preg_match( '#<fieldset>(?:<legend class="screen-reader-text"><span>[^<]*</span></legend>)?((?:<label><input type="radio" name="' . $quoted . '"[^>]*> [^<]*</label>(?:<br>|<br />)?)+)</fieldset>#', $html, $fieldset ) ) {
		return null;
	}

	preg_match_all( '#<input type="radio" name="' . $quoted . '" value="([^"]*)"([^>]*)> ([^<]*)</label>#', $fieldset[1], $radios, PREG_SET_ORDER );

	$answers = array();

	foreach ( $radios as $radio ) {
		$answers[] = array( $radio[1], html_entity_decode( $radio[3], ENT_QUOTES ), false !== strpos( $radio[2], 'checked' ) );
	}

	return $answers;
}

/**
 * What a screen reader names a leaving rule's group of answers by: the legend of its fieldset.
 *
 * @param string $html The tab.
 * @param string $name The rule's name.
 * @return string|null Null when the fieldset has no legend.
 */
function legend_of( $html, $name ) {
	return preg_match( '#<fieldset><legend class="screen-reader-text"><span>([^<]*)</span></legend><label><input type="radio" name="' . preg_quote( $name, '#' ) . '"#', $html, $legend ) ? html_entity_decode( $legend[1], ENT_QUOTES ) : null;
}

$rules = array(
	'on_inactive'             => 'people',
	'student_on_inactive'     => 'people',
	'institution_on_inactive' => 'institutions',
	'sponsor_on_inactive'     => 'sponsors',
);

// The two answers each rule offers, the one that takes the role or the access away first, then the
// one that leaves it: value and words.
$remove_then_leave = array(
	'on_inactive'             => array( array( 'revoke', 'Remove the Mentor role and clear their student list' ), array( 'keep', 'Leave the role in place' ) ),
	'student_on_inactive'     => array( array( 'revoke', 'Remove the Student role, so they lose access to Student-level content' ), array( 'keep', 'Leave the role in place' ) ),
	'institution_on_inactive' => array( array( 'revoke', 'Remove its people, so they lose access to its students' ), array( 'keep', 'Leave their access in place' ) ),
	'sponsor_on_inactive'     => array( array( 'revoke', 'Remove its accounts from the sponsor, so they lose access to its Sponsor Dashboard' ), array( 'keep', 'Leave their access in place' ) ),
);

/**
 * The answers each rule should draw with a given answer stored for every rule: the two answers in
 * their order, the stored one ticked.
 *
 * @param array $answers Rule => its two answers, each its value and its words.
 * @param array $stored  Rule => the answer stored.
 * @return array
 */
function rules_ticked( array $answers, array $stored ) {
	$want = array();

	foreach ( $answers as $name => $pair ) {
		foreach ( $pair as $answer ) {
			$want[ $name ][] = array( $answer[0], $answer[1], $stored[ $name ] === $answer[0] );
		}
	}

	return $want;
}

/**
 * Every rule's answers as its tab draws them.
 *
 * @param array $rules Rule => the tab that draws it.
 * @return array
 */
function rules_drawn( array $rules ) {
	$drawn = array();

	foreach ( $rules as $name => $tab ) {
		$drawn[ $name ] = answers_of( draw_settings( $tab ), $name );
	}

	return $drawn;
}

store_settings( $defaults );

$defaults_drawn = rules_drawn( $rules );
$rule_defaults  = array_intersect_key( WPCPM_Settings::defaults(), $rules );

ck( 'each of the four leaving rules is one fieldset of two answers, the one that removes the role or the access first and the one that leaves it second, the default ticked',
    array( $defaults_drawn, $rule_defaults ),
    array(
        rules_ticked( $remove_then_leave, $rule_defaults ),
        array(
            'on_inactive'             => 'revoke',
            'student_on_inactive'     => 'revoke',
            'institution_on_inactive' => 'revoke',
            'sponsor_on_inactive'     => 'keep',
        ),
    ) );

$first_words = array();

foreach ( $defaults_drawn as $name => $answers ) {
	foreach ( (array) $answers as $answer ) {
		$first_words[ $name ][] = strtok( $answer[1], ' ' );
	}
}

ck( 'and the four rules\' answers begin with the same words, Remove and then Leave',
    $first_words,
    array_fill_keys( array_keys( $rules ), array( 'Remove', 'Leave' ) ) );

// A screen reader entering a rule's answers hears the group's name first, the question the rule
// answers, as core's forms name a group of radios: "Its people" and "Its accounts" name what two of
// them decide, and the heading above says when, which the group then says itself.
$legends = array();

foreach ( $rules as $name => $tab ) {
	$legends[ $name ] = legend_of( draw_settings( $tab ), $name );
}

ck( 'and each rule\'s answers are a group named for a screen reader by the question it answers',
    $legends,
    array(
        'on_inactive'             => 'When a mentor is no longer active',
        'student_on_inactive'     => 'When a student leaves the program',
        'institution_on_inactive' => 'When an institution leaves the pipeline',
        'sponsor_on_inactive'     => 'When a sponsor is no longer Approved',
    ) );

$rules_other = array(
	'on_inactive'             => 'keep',
	'student_on_inactive'     => 'keep',
	'institution_on_inactive' => 'keep',
	'sponsor_on_inactive'     => 'revoke',
);

store_settings( array_merge( $defaults, $rules_other ) );

$other_drawn = rules_drawn( $rules );

// What each tab's form posts for its rules, as a browser sends it: the ticked answer's value.
$posted = array();

foreach ( $rules as $name => $tab ) {
	foreach ( (array) $other_drawn[ $name ] as $answer ) {
		if ( $answer[2] ) {
			$posted[ $tab ][ $name ] = $answer[0];
		}
	}
}

$saved_back = array();

foreach ( $posted as $tab => $fields ) {
	store_settings( array_merge( $defaults, $rules_other ) );
	press_tab( $tab, $fields );

	$saved_back = array_merge( $saved_back, array_intersect_key( WPCPM_Settings::get(), $fields ) );
}

ck( 'and with each rule stored at its other answer, that answer is the one ticked, and the tab\'s Save posts it back as stored',
    array( $other_drawn, $saved_back ),
    array( rules_ticked( $remove_then_leave, $rules_other ), $rules_other ) );

$GLOBALS['umeta'] = array();

echo "\n=== Each tool keeps its settings on its own screen ===\n";

// The Settings design of 28 September 2026: a tool with settings draws them in a Settings section at
// the top of its own screen, after its title, its lede and its notices and before the rest of it, and
// the screen's links that sent a manager to the Settings screen for them open that section instead.
// The Student Duplicate Finder's screen is drawn whole in bin/test-duplicate-finder.php.

/**
 * Where each of these first occurs on a screen, in the order given.
 *
 * @param string   $html    The screen.
 * @param string[] $needles What to find.
 * @return array<string, int|null> Each needle => its offset, or null when it does not occur.
 */
function offsets_of( $html, array $needles ) {
	$found = array();

	foreach ( $needles as $needle ) {
		$at               = strpos( $html, $needle );
		$found[ $needle ] = false === $at ? null : $at;
	}

	return $found;
}

/**
 * Whether the needles occur, every one of them, in the order given.
 *
 * @param array $offsets `offsets_of()`'s answer.
 * @return bool
 */
function in_order( array $offsets ) {
	$last = -1;

	foreach ( $offsets as $at ) {
		if ( null === $at || $at <= $last ) {
			return false;
		}

		$last = $at;
	}

	return true;
}

/**
 * The ids a screen's Settings section gives its elements that another element of the screen also has.
 *
 * @param string $html The screen.
 * @return string[]
 */
function section_ids_shared( $html ) {
	$section = preg_match( '#<div class="wpcpm-card"><h2 id="settings">Settings</h2>.*?</form>\s*</div>#s', $html, $found ) ? $found[0] : '';

	preg_match_all( '#\sid="([^"]*)"#', $section, $own );
	preg_match_all( '#\sid="([^"]*)"#', $html, $all );

	$counts = array_count_values( $all[1] );
	$shared = array();

	foreach ( array_unique( $own[1] ) as $id ) {
		if ( $counts[ $id ] > 1 ) {
			$shared[] = $id;
		}
	}

	return $shared;
}

// Need help?, switched off and with no provider, as a manager finds it who has just turned it off.
store_settings( array_merge( $defaults, array( 'handbook_enabled' => false ) ) );
$GLOBALS['signed_in'] = 30;
$handbook_screen      = draw_tool_screen( 'handbook' );

ck( 'Need help?\'s screen opens on its Settings section: after its title, its lede and its notice, and before how answers are produced and the question box',
    in_order( offsets_of( $handbook_screen, array( '<h1>Need help?</h1>', '<p class="wpcpm-lede">', 'Need help? is switched off, so it is not answering anybody.', '<h2 id="settings">Settings</h2>', '<h2>How answers are produced</h2>', '<h2>Try a question</h2>' ) ) ),
    true );

ck( 'and the two links that sent a manager to the Settings screen open the section on this screen',
    array(
        substr_count( $handbook_screen, '<a href="#settings">Turn it on in Settings</a>' ),
        substr_count( $handbook_screen, '<a href="#settings">Add one in Settings</a>' ),
        preg_match_all( '#href="[^"]*page=wpcpm-settings\b#', $handbook_screen ),
        substr_count( $handbook_screen, 'id="settings"' ),
    ),
    array( 1, 1, 0, 1 ) );

// The Mentor Status Checker, with Airtable not connected yet.
store_settings( $defaults );
$GLOBALS['signed_in'] = 31;
$checker_screen       = draw_tool_screen( 'mentor-status-checker' );

ck( 'the Mentor Status Checker\'s screen opens on its Settings section: after its title, its notice and its lede, and before what a run writes, its buttons and its results',
    in_order( offsets_of( $checker_screen, array( '<h1>Mentor Status Checker</h1>', 'Add an Airtable Personal Access Token before running a check.', '<p class="wpcpm-lede">', '<h2 id="settings">Settings</h2>', 'Promoting writes to the shared Airtable base', '<div class="wpcpm-checker-controls">', 'wpcpm-checker-results' ) ) ),
    true );

// The token is not one of the checker's settings: that link still opens the Settings screen, where
// the token is, and nothing else on the screen does.
ck( 'and its one link to the Settings screen is the token\'s, which is kept there',
    array( preg_match_all( '#href="[^"]*page=wpcpm-settings\b#', $checker_screen ), substr_count( $checker_screen, '<a href="https://example.test/wp-admin/admin.php?page=wpcpm-settings">Open settings</a>' ), substr_count( $checker_screen, 'id="settings"' ) ),
    array( 1, 1, 1 ) );

unset( $GLOBALS['signed_in'] );

// A tool is called by its name, as the menu and the Overview call it, and its section by what it is.
ck( 'on each tool\'s screen the title is the tool\'s name, the section is called Settings, with no "Tool:" and no "Tool" pill, and none of the section\'s ids is another element\'s',
    array(
        array( substr_count( $handbook_screen, '<h1>Need help?</h1>' ), substr_count( $checker_screen, '<h1>Mentor Status Checker</h1>' ) ),
        array( substr_count( $handbook_screen . $checker_screen, 'Tool:' ), substr_count( $handbook_screen . $checker_screen, 'wpcpm-count' ) ),
        array( section_ids_shared( $handbook_screen ), section_ids_shared( $checker_screen ) ),
    ),
    array( array( 1, 1 ), array( 0, 0 ), array( array(), array() ) ) );

// With results on the screen, the checker draws both of its small forms, Clear cached profile results
// and Clear results: each carries its nonce under the name its handler reads, in a field with an id
// of its own, where core's field takes its id from its name and gave two elements of the screen one.
WPCPM_Mentor_Checker_Runner::$last_run = array(
	'started' => 1757000000,
	'rows'    => array( array( 'record_id' => 'recPROBE0000000001', 'name' => 'Probe Mentor', 'state' => 'not_completed' ) ),
);
$GLOBALS['signed_in']                  = 33;
$checker_with_rows                     = draw_tool_screen( 'mentor-status-checker' );
WPCPM_Mentor_Checker_Runner::$last_run = array();

unset( $GLOBALS['signed_in'] );

preg_match_all( '#\sid="([^"]*)"#', $checker_with_rows, $checker_ids );
preg_match_all( '#<input type="hidden" id="([^"]*)" name="_wpnonce" value="([^"]*)" />#', $checker_with_rows, $checker_nonces, PREG_SET_ORDER );

$checker_twice = array();

foreach ( array_count_values( $checker_ids[1] ) as $id => $times ) {
	if ( $times > 1 ) {
		$checker_twice[ $id ] = $times;
	}
}

$checker_nonce_fields = array();

foreach ( $checker_nonces as $nonce ) {
	$checker_nonce_fields[] = array( $nonce[1], $nonce[2] );
}

ck( 'the checker\'s two small forms each carry the nonce their handler checks, in a field with an id of its own, and no element of the screen shares an id',
    array( false !== strpos( $checker_with_rows, 'Probe Mentor' ), $checker_nonce_fields, $checker_twice ),
    array(
        true,
        array(
            array( 'wpcpm-checker-flush-nonce', 'nonce:wpcpm_checker_flush_cache' ),
            array( 'wpcpm-checker-clear-nonce', 'nonce:wpcpm_checker_clear_results' ),
        ),
        array(),
    ) );

// Every settings form opens the one way, through the screen's one opener: the form, the nonce under
// its own name, which is also how the save handler tells a settings form was posted, and the scope.
$tool_base_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php' );
$tool_render      = method_source( $tool_base_source, 'render_settings' );
$opener           = method_exists( 'WPCPM_Settings_Screen', 'open_settings_form' ) ? new ReflectionMethod( 'WPCPM_Settings_Screen', 'open_settings_form' ) : null;

ck( 'every settings form, a tab\'s and a tool\'s Settings section, opens through the screen\'s one public opener, and the tool base prints neither the nonce nor the scope itself',
    array(
        null === $opener ? null : array( $opener->isPublic(), $opener->isStatic() ),
        substr_count( $tool_render, 'WPCPM_Settings_Screen::open_settings_form( ' ),
        substr_count( $tool_render, 'SETTINGS_NONCE' ),
        substr_count( $tool_render, 'SETTINGS_TAB_FIELD' ),
    ),
    array( array( true, true ), 1, 0, 0 ) );

$GLOBALS['umeta'] = array();

echo "\n=== The Connection tab says where the tools' own settings are ===\n";

// A manager who remembers the three tools' settings on this screen opens it looking for them, and it
// opens on Connection: the tab's first line, under the tab bar and above Airtable, names the three
// and links each to the Settings section of its own screen. No other tab says it again.
$connection_lede = preg_match( '#</nav>\s*<form\b[^>]*>.*?<p class="wpcpm-lede">(.*?)</p>\s*<h2>Airtable</h2>#s', draw_settings( 'connection' ), $found ) ? $found[1] : '';

preg_match_all( '#<a href="([^"]*)">([^<]*)</a>#', $connection_lede, $lede_links, PREG_SET_ORDER );

$linked = array();

foreach ( $lede_links as $link ) {
	$linked[] = array( html_entity_decode( $link[2], ENT_QUOTES ), $link[1] );
}

$keeps = array();

foreach ( array_keys( $keeping ) as $id ) {
	$keeps[ $id ] = array() !== WPCPM_Tools::get( $id )->settings_keys();
}

$ledes_elsewhere = array();

foreach ( array_keys( $want_tabs ) as $slug ) {
	if ( 'connection' !== $slug && false !== strpos( draw_settings( $slug ), 'wpcpm-lede' ) ) {
		$ledes_elsewhere[] = $slug;
	}
}

ck( 'the Connection tab opens on one sentence naming the three tools that keep their settings on their own screens, each a link to its Settings section there, and no other tab says it',
    array( html_entity_decode( strip_tags( $connection_lede ), ENT_QUOTES ), $linked, $keeps, $ledes_elsewhere ),
    array(
        'The Mentor Status Checker, the Student Duplicate Finder and Need help? keep their settings on their own screens, under WPCredits Program > Tools.',
        array(
            array( 'Mentor Status Checker', $keeping['mentor-status-checker'] . '#settings' ),
            array( 'Student Duplicate Finder', $keeping['duplicate-finder'] . '#settings' ),
            array( 'Need help?', $keeping['handbook'] . '#settings' ),
        ),
        array_fill_keys( array_keys( $keeping ), true ),
        array(),
    ) );

// A tool a site's filter took out of the registry has no screen to send anybody to, so the sentence,
// which names all three, is not drawn rather than drawn with a hole in it.
WPCPM_Tools::$removed = array( 'handbook' );
$without_one          = draw_settings( 'connection' );
WPCPM_Tools::$removed = array();

ck( 'and with one of the three taken out of the registry, the sentence is not drawn',
    false !== strpos( $without_one, 'wpcpm-lede' ), false );

echo "\n=== The guides say where each tool's settings are ===\n";

// Guide 31 is the Settings screen's: it has no Tools tab, and says where the three tools' settings
// went. Guide 32 is the tools': each of the three has a Settings section there, as on its screen.
$guide_31 = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/31-admin-settings.md' );
$guide_32 = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/32-admin-tools.md' );

$tool_parts = array();

foreach ( array( 'Mentor Status Checker', 'Student Duplicate Finder', 'Need help?' ) as $tool_name ) {
	$part                     = preg_match( '/^### ' . preg_quote( $tool_name, '/' ) . '\n(.*?)(?=^### |\z)/ms', $guide_32, $found ) ? $found[1] : '';
	$tool_parts[ $tool_name ] = 1 === preg_match( '/^#### Settings$/m', $part );
}

ck( 'guide 31 has no Tools tab and says the three tools keep their settings on their own screens, and guide 32 gives each of the three a Settings section',
    array( 1 === preg_match( '/^### The Tools tab$/m', $guide_31 ), false !== strpos( $guide_31, 'eighth' ), 1 === preg_match( '/Mentor Status Checker.*Student Duplicate Finder.*Need help\?.*own screen/s', $guide_31 ), $tool_parts ),
    array( false, false, true, array_fill_keys( array( 'Mentor Status Checker', 'Student Duplicate Finder', 'Need help?' ), true ) ) );

echo "\n=== airtable_record_url() ===\n";
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array_merge( WPCPM_Settings::defaults(), array( 'base_id' => 'appTEST', 'students_table' => 'tbl STU', 'reports_table' => '' ) );
ck( 'a Students row address from the settings, each part encoded', WPCPM_Settings::airtable_record_url( 'students_table', 'recABCDEFGHIJKLMN' ), 'https://airtable.com/appTEST/tbl%20STU/recABCDEFGHIJKLMN' );
ck( 'a table the settings do not name gives no address', WPCPM_Settings::airtable_record_url( 'reports_table', 'recABCDEFGHIJKLMN' ), '' );
ck( 'and so does an empty record', WPCPM_Settings::airtable_record_url( 'students_table', '' ), '' );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

exit( $fail ? 1 : 0 );
