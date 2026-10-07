<?php
/**
 * The Mentor Status Checker's daily schedule and the Slack message it sends after a promotion.
 *
 * The owner's request of 7 October 2026: run the check every day instead of every week, let it
 * promote on its own, and after every promotion tell the program's administrators on Slack who was
 * moved to Active, with each mentor's WordPress.org profile and Slack name, so one of them adds the
 * mentors to the mentors' channel. One message per action - a run, Promote all, a single Promote -
 * and a message Slack refused is kept and goes out with the next one.
 *
 * WordPress, Airtable, the profile reader and Slack are stood in: the options table is an ordered
 * array that `$wpdb` can list by prefix, Airtable is a table of records by ID, a profile check
 * answers from a table of usernames, and Slack is a table of answers with every request it was sent
 * kept, so a check can see what was posted, how often, and where. Two hooks let a check act as
 * another request would in the middle of a send: while a profile is read, and while Slack is asked.
 *
 * Run from the plugin root:  php bin/test-mentor-checker.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WP_User {
	public $ID;
	public $display_name;
	public function __construct( $id = 0, $name = '' ) { $this->ID = $id; $this->display_name = $name; }
	public function exists() { return $this->ID > 0; }
}

// Translation, with a locale that can differ from the site's: a request in a manager's own Spanish
// translates every string, and `switch_to_locale()` moves to another and back, as core's does. Core
// answers false, and switches nothing, when asked for the locale already in use.
$GLOBALS['site_locale'] = 'en_US';
$GLOBALS['locale']      = 'en_US';
$GLOBALS['locales']     = array();
function __( $s, $d = null ) { return 'es_ES' === $GLOBALS['locale'] ? '[es] ' . $s : $s; }
function _n( $a, $b, $n, $d = null ) { return __( 1 === (int) $n ? $a : $b ); }
function get_locale() { return $GLOBALS['site_locale']; }
function switch_to_locale( $locale ) {
	if ( $locale === $GLOBALS['locale'] ) {
		return false;
	}
	$GLOBALS['locales'][] = $GLOBALS['locale'];
	$GLOBALS['locale']    = $locale;
	return true;
}
function restore_previous_locale() {
	$GLOBALS['locale'] = array_pop( $GLOBALS['locales'] );
	return $GLOBALS['locale'];
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $from, $to = 0 ) { return '5 mins'; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_generate_password( $n = 12, $s = true, $e = false ) { return 'tok' . ( ++$GLOBALS['runs'] ); }
function add_action() {}
function do_action( $tag, ...$args ) {}
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function wp_unslash( $v ) { return $v; }
// The tool hands each row back drawn, so its drawing's escaping is stood in as core's.
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( __( $s ) ); }
function esc_html_e( $s, $d = null ) { echo esc_html( __( $s ) ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_date( $format, $ts = null ) { return gmdate( $format, (int) $ts ); }

// Core's list sentence: "A", "A and B", "A, B, and C".
function wp_sprintf( $pattern, ...$args ) {
	if ( '%l' !== $pattern ) {
		return vsprintf( $pattern, $args );
	}
	$items = array_values( (array) $args[0] );
	if ( count( $items ) < 3 ) {
		return implode( ' and ', $items );
	}
	$last = array_pop( $items );
	return implode( ', ', $items ) . ', and ' . $last;
}

// The options table, in the order rows were added. `add_option()` refuses a name it finds, as core's
// does after its own read; core's then upserts, so two requests can both take a name in the same
// instant, which the lock's docblock owns up to.
$GLOBALS['opts'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v = '', $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

/**
 * `$wpdb`, for the one question asked of it: the names of the options under a prefix, oldest first.
 */
class Options_Table {
	public $options = 'wp_options';
	public $asked   = array();

	public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }

	public function prepare( $query, ...$args ) { return array( $query, $args ); }

	public function get_col( $prepared ) {
		list( $query, $args ) = $prepared;
		$this->asked[]        = $query;
		$prefix               = stripcslashes( substr( $args[0], 0, -1 ) );

		return array_values(
			array_filter(
				array_keys( $GLOBALS['opts'] ),
				function ( $name ) use ( $prefix ) {
					return 0 === strpos( $name, $prefix );
				}
			)
		);
	}
}
$GLOBALS['wpdb'] = new Options_Table();

$GLOBALS['transients'] = array();
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }

// Cron, as the array core keeps: hook => (timestamp, schedule).
$GLOBALS['cron'] = array();
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ]['timestamp'] : false; }
function wp_get_scheduled_event( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? (object) array_merge( array( 'hook' => $hook ), $GLOBALS['cron'][ $hook ] ) : false; }
function wp_schedule_event( $ts, $recurrence, $hook ) { $GLOBALS['cron'][ $hook ] = array( 'timestamp' => $ts, 'schedule' => $recurrence ); return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 1; }
$GLOBALS['doing_cron'] = false;
function wp_doing_cron() { return $GLOBALS['doing_cron']; }

$GLOBALS['user'] = new WP_User( 7, 'Ada Admin' );
function wp_get_current_user() { return $GLOBALS['user']; }
function current_user_can( $cap ) { return true; }
function check_ajax_referer( $a = -1, $q = false ) { return true; }

// Slack, as a table of answers: the next answers in order, then "ok". Every request is kept with its
// address and body; an answer of null is a network failure, the shape `wp_remote_post()` gives when
// nothing answers.
$GLOBALS['slack_answers'] = array();
$GLOBALS['posted']        = array();
$GLOBALS['during_post']   = null;
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['posted'][] = array( 'url' => $url, 'args' => $args, 'payload' => json_decode( $args['body'], true ) );
	if ( is_callable( $GLOBALS['during_post'] ) ) {
		call_user_func( $GLOBALS['during_post'] );
	}
	$answer = array() === $GLOBALS['slack_answers'] ? 'ok' : array_shift( $GLOBALS['slack_answers'] );
	if ( null === $answer ) {
		return new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' );
	}
	return is_array( $answer ) ? $answer : array( 'response' => array( 'code' => 200 ), 'body' => $answer );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }

// An AJAX answer ends the request; here it ends the call, with what would have been sent.
class Json_Sent extends Exception {
	public $ok;
	public $data;
	public function __construct( $ok, $data ) { parent::__construct( 'json' ); $this->ok = $ok; $this->data = $data; }
}
function wp_send_json_success( $data = null ) { throw new Json_Sent( true, $data ); }
function wp_send_json_error( $data = null, $code = null ) { throw new Json_Sent( false, $data ); }

// Made up, and put together here rather than written out: GitHub's push protection reads any literal
// in the shape of a Slack webhook as a leaked secret and refuses the public mirror's push.
define( 'WEBHOOK', 'https://hooks.slack.com/services/' . 'T00000000/B00000000/' . str_repeat( 'x', 24 ) );

/**
 * The plugin's settings, as the checker reads them: the defaults that matter here and whatever a
 * check sets.
 */
class WPCPM_Settings {
	public static $values = array();

	public static function get() {
		return array_merge(
			array(
				'api_token'                 => 'patTEST',
				'base_id'                   => 'appTEST0000000000',
				'mentors_table'             => 'tblMENTORS0000000',
				'checker_source_status'     => 'Vetted - positive',
				'checker_target_status'     => 'Active',
				'checker_course_slug'       => 'wordpress-credits-mentors-course',
				'checker_course_title'      => "WordPress Credits Mentor's Course",
				'checker_completion_phrase' => 'Completed the course',
				'checker_timeline_filter'   => 'meta',
				'checker_max_pages'         => 15,
				'checker_batch_size'        => 1,
				'checker_request_delay'     => 0,
				'checker_cache_ttl'         => 0,
				'checker_cron_enabled'      => true,
				'checker_cron_promotes'     => true,
				'checker_slack_webhook'     => WEBHOOK,
				'checker_slack_channel'     => '#mentors',
			),
			self::$values
		);
	}

	public static function is_connected() { return true; }
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage';
}

class WPCPM_Mentors_Sync {
	public static function fields() {
		return array( 'mentor_name' => 'Full Name', 'mentor_profile' => 'WordPress profile', 'mentor_status' => 'Status' );
	}
}

/**
 * The Mentors table: records by ID, each write applied, and a switch that makes the next writes fail.
 */
class WPCPM_Airtable {
	public static $records    = array();
	public static $fail_write = false;

	public function __construct( $settings = array() ) {}

	public static function mentor( $id, $name, $profile, $status = 'Vetted - positive' ) {
		self::$records[ $id ] = array(
			'id'     => $id,
			'fields' => array( 'Full Name' => $name, 'WordPress profile' => $profile, 'Status' => $status ),
		);
	}

	public function fetch_all( $table, $args = array() ) {
		return array_values(
			array_filter(
				self::$records,
				function ( $r ) {
					return 'Vetted - positive' === $r['fields']['Status'];
				}
			)
		);
	}

	public function formula_in( $field, $values ) { return ''; }

	public function get_record( $table, $id ) {
		return isset( self::$records[ $id ] ) ? self::$records[ $id ] : new WP_Error( 'not_found', 'Record not found.' );
	}

	public function update_records( $table, $rows ) {
		if ( self::$fail_write ) {
			return new WP_Error( 'airtable', 'Airtable refused the write.' );
		}
		foreach ( $rows as $row ) {
			self::$records[ $row['id'] ]['fields'] = array_merge( self::$records[ $row['id'] ]['fields'], $row['fields'] );
		}
		return $rows;
	}

	public static function flatten( $v ) { return is_array( $v ) ? implode( ', ', $v ) : (string) $v; }

	public static function is_record_id( $id ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $id ); }
}

/**
 * The profile reader: the usernames that completed the course, and the username a profile field
 * reduces to (the last segment of the address).
 */
class WPCPM_Mentor_Checker_Profile {
	public static $completed = array();

	public function __construct( $settings = array() ) {}

	public static function normalize_username( $profile ) {
		$parts = array_values( array_filter( explode( '/', trim( (string) $profile ) ) ) );
		return empty( $parts ) ? '' : strtolower( ltrim( end( $parts ), '@' ) );
	}

	public function check( $profile, $use_cache = true ) {
		$username  = self::normalize_username( $profile );
		$completed = in_array( $username, self::$completed, true );

		return array(
			'username'  => $username,
			'completed' => $completed,
			'state'     => $completed ? 'completed' : 'not_completed',
			'message'   => '',
			'timestamp' => 0,
			'pages'     => 1,
			'cached'    => false,
		);
	}

	public static function flush_cache() {}
}

/**
 * The public profile reader, for the Slack name: a table of names by username, every lookup kept,
 * and a hook for what another request does while one is read.
 */
class WPCPM_WPorg_Profile {
	public static $slack   = array();
	public static $looked  = array();
	public static $reading = null;

	public static function get( $username, $use_cache = true ) {
		self::$looked[] = $username;
		if ( is_callable( self::$reading ) ) {
			call_user_func( self::$reading );
		}
		return array_key_exists( $username, self::$slack ) ? array( 'username' => $username, 'slack' => self::$slack[ $username ] ) : null;
	}
}

class WPCPM_Request {
	public static function key( $k ) { return ''; }
}

$GLOBALS['runs'] = 0;

require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-slack.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';

$total = 0;
$fail  = 0;

function ck( $label, $actual, $expected ) {
	global $total, $fail;
	++$total;

	if ( $actual === $expected ) {
		echo "ok   $label\n";

		return;
	}

	++$fail;
	echo "FAIL $label\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * A clean slate: no records, no requests, nothing waiting, Slack answering "ok", the site's locale.
 */
function reset_world() {
	$GLOBALS['opts']          = array();
	$GLOBALS['transients']    = array();
	$GLOBALS['cron']          = array();
	$GLOBALS['posted']        = array();
	$GLOBALS['slack_answers'] = array();
	$GLOBALS['during_post']   = null;
	$GLOBALS['doing_cron']    = false;
	$GLOBALS['locale']        = 'en_US';
	$GLOBALS['locales']       = array();
	$GLOBALS['user']          = new WP_User( 7, 'Ada Admin' );
	$_POST                    = array();

	WPCPM_Settings::$values                  = array();
	WPCPM_Airtable::$records                 = array();
	WPCPM_Airtable::$fail_write              = false;
	WPCPM_Mentor_Checker_Profile::$completed = array();
	WPCPM_WPorg_Profile::$slack              = array();
	WPCPM_WPorg_Profile::$looked             = array();
	WPCPM_WPorg_Profile::$reading            = null;
}

/**
 * Record a promotion as the runner does, for a mentor known by a short name.
 *
 * @param string $who One of the mentors below.
 */
function promoted( $who ) {
	$mentors = array(
		'one'   => array( 'id' => 'recMENTOR00000001', 'name' => 'Mentor One', 'profile' => 'https://profiles.wordpress.org/mentor-one/' ),
		'two'   => array( 'id' => 'recMENTOR00000002', 'name' => 'Mentor Two', 'profile' => 'mentor-two' ),
		'three' => array( 'id' => 'recMENTOR00000003', 'name' => 'Mentor Three', 'profile' => 'mentor-three' ),
	);

	WPCPM_Mentor_Checker_Slack::record( $mentors[ $who ] );
}

/**
 * The record IDs waiting, oldest first.
 *
 * @return string[]
 */
function waiting() {
	return array_column( WPCPM_Mentor_Checker_Slack::waiting(), 'record_id' );
}

/**
 * Run an AJAX handler of the tool and hand back what it would have answered.
 *
 * @param string $method The handler.
 * @return Json_Sent
 */
function ajax( $method ) {
	$tool = new WPCPM_Mentor_Checker();

	try {
		$tool->$method();
	} catch ( Json_Sent $sent ) {
		return $sent;
	}

	return new Json_Sent( null, 'no answer' );
}

/**
 * The text of each message posted to Slack, in order.
 *
 * @return string[]
 */
function texts() {
	return array_map(
		function ( $p ) {
			return $p['payload']['text'];
		},
		$GLOBALS['posted']
	);
}

/**
 * A message's last line.
 *
 * @param string $text The message.
 * @return string
 */
function last_line( $text ) {
	return substr( $text, strrpos( $text, "\n" ) + 1 );
}

$one = array( 'record_id' => 'recMENTOR00000001', 'name' => 'Mentor One', 'username' => 'mentor-one', 'via' => 'cron', 'by' => '', 'slack' => 'mentor.one' );
$two = array( 'record_id' => 'recMENTOR00000002', 'name' => 'Mentor Two', 'username' => 'mentor-two', 'via' => 'user', 'by' => 'Ada Admin', 'slack' => '' );

echo "=== The message ===\n";

reset_world();

ck( 'one mentor: the channel is called, the count is singular, the profile is a link and the Slack name follows as code',
    WPCPM_Mentor_Checker_Slack::message( array( $one ) ),
    "<!channel> 1 mentor was moved to Active. Please add them to #mentors:\n"
    . "• Mentor One - <https://profiles.wordpress.org/mentor-one/|@mentor-one> - Slack: `mentor.one`\n"
    . 'Promoted by the daily check.' );

ck( 'three mentors: plural, one line each, a mentor whose profile lists no Slack name has none, and each who is named once',
    WPCPM_Mentor_Checker_Slack::message( array( $one, $two, array_merge( $one, array( 'record_id' => 'recX', 'name' => 'Mentor Six', 'username' => 'mentor-six', 'slack' => '' ) ) ) ),
    "<!channel> 3 mentors were moved to Active. Please add them to #mentors:\n"
    . "• Mentor One - <https://profiles.wordpress.org/mentor-one/|@mentor-one> - Slack: `mentor.one`\n"
    . "• Mentor Two - <https://profiles.wordpress.org/mentor-two/|@mentor-two>\n"
    . "• Mentor Six - <https://profiles.wordpress.org/mentor-six/|@mentor-six>\n"
    . 'Promoted by the daily check and Ada Admin.' );

ck( 'what Slack would read as markup is escaped: a name or Slack name with <, > or & arrives as typed',
    WPCPM_Mentor_Checker_Slack::message( array( array_merge( $one, array( 'name' => 'Tom <b> & Jerry', 'slack' => 'tom>jerry' ) ) ) ),
    "<!channel> 1 mentor was moved to Active. Please add them to #mentors:\n"
    . "• Tom &lt;b&gt; &amp; Jerry - <https://profiles.wordpress.org/mentor-one/|@mentor-one> - Slack: `tom&gt;jerry`\n"
    . 'Promoted by the daily check.' );

ck( 'a name or Slack name over several lines is one line, so it cannot draw a bullet of its own; a backtick cannot end the Slack name\'s code early',
    explode( "\n", WPCPM_Mentor_Checker_Slack::message( array( array_merge( $one, array( 'name' => "Mentor\n• Someone Else  -  no WordPress.org profile", 'slack' => "evil`\nhttps://evil.test" ) ) ) ) )[1],
    '• Mentor • Someone Else - no WordPress.org profile - <https://profiles.wordpress.org/mentor-one/|@mentor-one> - Slack: `evil https://evil.test`' );

WPCPM_Settings::$values = array( 'checker_slack_channel' => 'mentors', 'checker_target_status' => 'Active mentor' );

ck( 'the channel is printed with one # however it was typed, and the status is the one the tool promotes to',
    strtok( WPCPM_Mentor_Checker_Slack::message( array( $one ) ), "\n" ),
    '<!channel> 1 mentor was moved to Active mentor. Please add them to #mentors:' );

WPCPM_Settings::$values = array( 'checker_slack_channel' => '' );

ck( 'with no channel set the sentence still reads',
    strtok( WPCPM_Mentor_Checker_Slack::message( array( $one ) ), "\n" ),
    '<!channel> 1 mentor was moved to Active. Please add them to the mentors channel:' );

ck( 'a mentor with no username is named without a link rather than linked to nobody',
    explode( "\n", WPCPM_Mentor_Checker_Slack::message( array( array_merge( $one, array( 'username' => '', 'slack' => '' ) ) ) ) )[1],
    '• Mentor One - no WordPress.org profile' );

ck( 'WP-CLI, and a manager with no display name, are named too',
    array(
        last_line( WPCPM_Mentor_Checker_Slack::message( array( array_merge( $one, array( 'via' => 'cli' ) ) ) ) ),
        last_line( WPCPM_Mentor_Checker_Slack::message( array( array_merge( $one, array( 'via' => 'user', 'by' => '' ) ) ) ) ),
    ),
    array( 'Promoted by WP-CLI.', 'Promoted by an administrator.' ) );

reset_world();
$GLOBALS['locale'] = 'es_ES';
$message           = WPCPM_Mentor_Checker_Slack::message( array( $one ) );

ck( 'a manager reading wp-admin in another language still sends the message in the site\'s, and their own comes back after',
    array( false === strpos( $message, '[es]' ), $GLOBALS['locale'], $GLOBALS['locales'] ),
    array( true, 'es_ES', array() ) );

echo "\n=== Who promoted ===\n";

reset_world();

ck( 'a manager pressing a button is kept by their display name', WPCPM_Mentor_Checker_Slack::actor(), array( 'via' => 'user', 'by' => 'Ada Admin' ) );

$GLOBALS['doing_cron'] = true;

ck( 'the scheduled run is kept as the daily check, named only when the message is written', WPCPM_Mentor_Checker_Slack::actor(), array( 'via' => 'cron', 'by' => '' ) );

echo "\n=== The webhook ===\n";

ck( 'an incoming webhook address on hooks.slack.com is accepted', WPCPM_Mentor_Checker_Slack::is_webhook( WEBHOOK ), true );
ck( 'any other address is not: another host, plain http, a workflow trigger, a look-alike host, a query, a trailing line break, or not a string',
    array(
        WPCPM_Mentor_Checker_Slack::is_webhook( 'https://example.test/services/T0/B0/x' ),
        WPCPM_Mentor_Checker_Slack::is_webhook( 'http://hooks.slack.com/services/T0/B0/x' ),
        WPCPM_Mentor_Checker_Slack::is_webhook( 'https://hooks.slack.com/triggers/T0/1/x' ),
        WPCPM_Mentor_Checker_Slack::is_webhook( 'https://hooks.slack.com.evil.test/services/T0/B0/x' ),
        WPCPM_Mentor_Checker_Slack::is_webhook( WEBHOOK . '?then=https://evil.test/' ),
        WPCPM_Mentor_Checker_Slack::is_webhook( WEBHOOK . "\n" ),
        WPCPM_Mentor_Checker_Slack::is_webhook( array( WEBHOOK ) ),
        WPCPM_Mentor_Checker_Slack::is_webhook( '' ),
    ),
    array( false, false, false, false, false, false, false, false ) );

echo "\n=== Recording a promotion ===\n";

reset_world();
WPCPM_Settings::$values = array( 'checker_slack_webhook' => '' );
promoted( 'one' );

ck( 'with no webhook nothing is kept, so a webhook added later does not announce old promotions', waiting(), array() );

reset_world();
WPCPM_Mentor_Checker_Slack::record( array( 'id' => 'recMENTOR00000001', 'name' => 'Mentor One', 'profile' => 'https://profiles.wordpress.org/Mentor-One/' ) );
WPCPM_Mentor_Checker_Slack::record( array( 'id' => 'recMENTOR00000001', 'name' => 'Mentor One', 'profile' => 'https://profiles.wordpress.org/Mentor-One/' ) );
promoted( 'two' );

ck( 'each promotion is kept in a row of its own, with the username its profile reduces to and who promoted, once however often it is recorded',
    array( WPCPM_Mentor_Checker_Slack::waiting(), array_keys( $GLOBALS['opts'] ) ),
    array(
        array(
            array( 'record_id' => 'recMENTOR00000001', 'name' => 'Mentor One', 'username' => 'mentor-one', 'via' => 'user', 'by' => 'Ada Admin' ),
            array( 'record_id' => 'recMENTOR00000002', 'name' => 'Mentor Two', 'username' => 'mentor-two', 'via' => 'user', 'by' => 'Ada Admin' ),
        ),
        array( 'wpcpm_checker_slack_pending_recMENTOR00000001', 'wpcpm_checker_slack_pending_recMENTOR00000002' ),
    ) );

echo "\n=== Sending ===\n";

reset_world();

ck( 'nothing waiting: Slack is not asked', array( WPCPM_Mentor_Checker_Slack::flush(), $GLOBALS['posted'] ), array( null, array() ) );

WPCPM_WPorg_Profile::$slack = array( 'mentor-one' => 'mentor.one' );
promoted( 'one' );
$sent = WPCPM_Mentor_Checker_Slack::flush();
$post = $GLOBALS['posted'][0];

ck( 'one request, to the webhook, as JSON, following no redirect, with the message and no link previews',
    array( count( $GLOBALS['posted'] ), $post['url'], $post['args']['headers']['Content-Type'], $post['args']['redirection'], $post['payload']['unfurl_links'], $post['payload']['unfurl_media'] ),
    array( 1, WEBHOOK, 'application/json; charset=utf-8', 0, false, false ) );
ck( 'the message names the mentor with the Slack name read from their profile',
    $post['payload']['text'],
    "<!channel> 1 mentor was moved to Active. Please add them to #mentors:\n"
    . "• Mentor One - <https://profiles.wordpress.org/mentor-one/|@mentor-one> - Slack: `mentor.one`\n"
    . 'Promoted by Ada Admin.' );
ck( 'a sent message forgets its mentors and is remembered as sent, with how many it named',
    array( $sent, waiting(), get_option( WPCPM_Mentor_Checker_Slack::LAST )['ok'], get_option( WPCPM_Mentor_Checker_Slack::LAST )['count'] ),
    array( true, array(), true, 1 ) );
ck( 'and the lock is let go', get_option( WPCPM_Mentor_Checker_Slack::LOCK, 'free' ), 'free' );

reset_world();
WPCPM_WPorg_Profile::$slack = array( 'mentor-one' => 'mentor.one', 'mentor-two' => 'mentor.two' );
promoted( 'one' );
$GLOBALS['slack_answers'] = array( array( 'response' => array( 'code' => 404 ), 'body' => 'no_service' ) );
$refused                  = WPCPM_Mentor_Checker_Slack::flush();
$last                     = get_option( WPCPM_Mentor_Checker_Slack::LAST );

ck( 'a message Slack refuses is an error, the mentor stays waiting, and the failure is remembered',
    array( is_wp_error( $refused ), waiting(), $last['ok'], $last['error'] ),
    array( true, array( 'recMENTOR00000001' ), false, 'HTTP 404: no_service' ) );

promoted( 'two' );
WPCPM_Mentor_Checker_Slack::flush();

ck( 'the next message carries the one that waited and the new one, the waiting one first',
    array( count( $GLOBALS['posted'] ), substr_count( texts()[1], '• ' ), strpos( texts()[1], 'Mentor One' ) < strpos( texts()[1], 'Mentor Two' ), waiting() ),
    array( 2, 2, true, array() ) );

reset_world();
promoted( 'one' );
$GLOBALS['slack_answers'] = array( 'invalid_token' );
WPCPM_Mentor_Checker_Slack::flush();

ck( 'an answer of 200 that is not "ok" is not taken as sent', waiting(), array( 'recMENTOR00000001' ) );

reset_world();
promoted( 'one' );
$GLOBALS['slack_answers'] = array( null );
WPCPM_Mentor_Checker_Slack::flush();

ck( 'a network failure keeps the mentor waiting too, and says what failed',
    array( waiting(), get_option( WPCPM_Mentor_Checker_Slack::LAST )['error'] ),
    array( array( 'recMENTOR00000001' ), 'cURL error 28: Connection timed out' ) );

reset_world();
WPCPM_WPorg_Profile::$slack = array( 'mentor-one' => 'mentor.one' );
promoted( 'one' );
WPCPM_WPorg_Profile::$reading = function () {
	WPCPM_WPorg_Profile::$reading = null;
	promoted( 'two' );
};
WPCPM_Mentor_Checker_Slack::flush();

ck( 'a mentor promoted by another request while a profile is read is not lost: not in this message, waiting for the next',
    array( substr_count( texts()[0], '• ' ), waiting() ), array( 1, array( 'recMENTOR00000002' ) ) );

reset_world();
promoted( 'one' );
$GLOBALS['during_post'] = function () {
	$GLOBALS['during_post'] = null;
	promoted( 'two' );
};
WPCPM_Mentor_Checker_Slack::flush();

ck( 'nor is one promoted while the message is on its way', array( substr_count( texts()[0], '• ' ), waiting() ), array( 1, array( 'recMENTOR00000002' ) ) );

reset_world();
promoted( 'one' );
$GLOBALS['slack_answers'] = array( 'invalid_payload' );
$GLOBALS['during_post']   = function () {
	$GLOBALS['during_post'] = null;
	promoted( 'two' );
};
WPCPM_Mentor_Checker_Slack::flush();

ck( 'nor when Slack refuses that message: both wait', waiting(), array( 'recMENTOR00000001', 'recMENTOR00000002' ) );

reset_world();

for ( $i = 1; $i <= 30; $i++ ) {
	WPCPM_Mentor_Checker_Slack::record( array( 'id' => sprintf( 'recMANY%010d', $i ), 'name' => 'Mentor ' . $i, 'profile' => 'mentor-' . $i ) );
}

$GLOBALS['slack_answers'] = array( 'ok', 'ok' );
WPCPM_Mentor_Checker_Slack::flush();

ck( 'a long list goes in messages of 25, each counting its own',
    array( count( $GLOBALS['posted'] ), substr_count( texts()[0], '• ' ), substr_count( texts()[1], '• ' ), strtok( texts()[1], "\n" ), waiting(), get_option( WPCPM_Mentor_Checker_Slack::LAST )['count'] ),
    array( 2, 25, 5, '<!channel> 5 mentors were moved to Active. Please add them to #mentors:', array(), 30 ) );

reset_world();

for ( $i = 1; $i <= 30; $i++ ) {
	WPCPM_Mentor_Checker_Slack::record( array( 'id' => sprintf( 'recMANY%010d', $i ), 'name' => 'Mentor ' . $i, 'profile' => 'mentor-' . $i ) );
}

$GLOBALS['slack_answers'] = array( 'ok', 'invalid_payload' );
WPCPM_Mentor_Checker_Slack::flush();

ck( 'when a later part is refused, the parts sent are forgotten and the rest waits',
    array( count( $GLOBALS['posted'] ), count( waiting() ), waiting()[0], get_option( WPCPM_Mentor_Checker_Slack::LAST )['ok'] ),
    array( 2, 5, 'recMANY0000000026', false ) );

reset_world();
promoted( 'one' );
update_option( WPCPM_Mentor_Checker_Slack::LOCK, ( time() - 30 ) . '|other' );

ck( 'while another request is sending, this one sends nothing and leaves the list to it',
    array( WPCPM_Mentor_Checker_Slack::flush(), $GLOBALS['posted'], waiting() ),
    array( null, array(), array( 'recMENTOR00000001' ) ) );

update_option( WPCPM_Mentor_Checker_Slack::LOCK, ( time() - 3600 ) . '|other' );
WPCPM_Mentor_Checker_Slack::flush();

ck( 'a lock left by a request that died is taken over', count( $GLOBALS['posted'] ), 1 );

reset_world();
promoted( 'one' );
$GLOBALS['during_post'] = function () {
	update_option( WPCPM_Mentor_Checker_Slack::LOCK, time() . '|someone-else' );
};
WPCPM_Mentor_Checker_Slack::flush();

ck( 'a lock another request took over meanwhile is theirs to let go', get_option( WPCPM_Mentor_Checker_Slack::LOCK ), time() . '|someone-else' );

reset_world();
promoted( 'one' );
WPCPM_Settings::$values = array( 'checker_slack_webhook' => '' );

ck( 'a webhook removed after a promotion was kept: nothing is sent and nothing is held for ever',
    array( WPCPM_Mentor_Checker_Slack::flush(), $GLOBALS['posted'], waiting() ),
    array( null, array(), array() ) );

reset_world();
WPCPM_WPorg_Profile::$slack = array( 'mentor-one' => 'mentor.one' );
promoted( 'one' );
WPCPM_Settings::$values = array( 'checker_slack_webhook' => WEBHOOK . "\n" );

ck( 'a stored webhook is checked again before use: one that is not a webhook any more sends nothing',
    array( WPCPM_Mentor_Checker_Slack::flush(), $GLOBALS['posted'] ), array( null, array() ) );

echo "\n=== What the screen says ===\n";

reset_world();

ck( 'never sent: nothing to say', WPCPM_Mentor_Checker_Slack::status_sentence(), '' );

update_option( WPCPM_Mentor_Checker_Slack::LAST, array( 'time' => time() - 300, 'ok' => true, 'count' => 2, 'error' => '' ) );

ck( 'sent', WPCPM_Mentor_Checker_Slack::status_sentence(), 'Last Slack message sent 5 mins ago, naming 2 mentors.' );

update_option( WPCPM_Mentor_Checker_Slack::LAST, array( 'time' => time() - 300, 'ok' => false, 'count' => 1, 'error' => 'HTTP 404: no_service' ) );
promoted( 'one' );

ck( 'refused, with how many wait for the next message',
    WPCPM_Mentor_Checker_Slack::status_sentence(),
    'The last Slack message could not be sent 5 mins ago (HTTP 404: no_service). 1 mentor waits for the next one.' );

WPCPM_Settings::$values = array( 'checker_slack_webhook' => '' );

ck( 'no webhook: nothing to say, whatever was sent before', WPCPM_Mentor_Checker_Slack::status_sentence(), '' );

echo "\n=== Promotions are recorded where they happen ===\n";

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'https://profiles.wordpress.org/mentor-one/' );
WPCPM_Airtable::mentor( 'recMENTOR00000004', 'Mentor Four', 'mentor-four', 'Inactive' );
WPCPM_Airtable::mentor( 'recMENTOR00000005', 'Mentor Five', 'mentor-five' );
$runner = new WPCPM_Mentor_Checker_Runner();

$runner->promote( 'recMENTOR00000004' );

ck( 'a mentor whose status changed meanwhile is left alone and not announced', waiting(), array() );

WPCPM_Airtable::$fail_write = true;
$runner->promote( 'recMENTOR00000005' );
WPCPM_Airtable::$fail_write = false;

ck( 'a write Airtable refused is not announced', waiting(), array() );

$runner->promote( 'recMENTOR00000001' );

ck( 'a mentor actually moved is kept for the message', waiting(), array( 'recMENTOR00000001' ) );
ck( 'and promote() alone sends nothing: the action that called it does', $GLOBALS['posted'], array() );

echo "\n=== One message per action ===\n";

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'mentor-one' );
WPCPM_Airtable::mentor( 'recMENTOR00000002', 'Mentor Two', 'mentor-two' );
WPCPM_Airtable::mentor( 'recMENTOR00000003', 'Mentor Three', 'mentor-three' );
WPCPM_Mentor_Checker_Profile::$completed = array( 'mentor-one', 'mentor-two' );
$GLOBALS['doing_cron']                   = true;
$runner                                  = new WPCPM_Mentor_Checker_Runner();
$start                                   = $runner->start( true );
$runner->process_batch( $start['run_id'], 0 );
$runner->process_batch( $start['run_id'], 1 );
$after_two = count( $GLOBALS['posted'] );
$runner->process_batch( $start['run_id'], 2 );

ck( 'a promoting run over three batches sends nothing until it ends, then one message naming both promoted by the daily check',
    array( $after_two, count( $GLOBALS['posted'] ), substr_count( texts()[0], '• ' ), last_line( texts()[0] ) ),
    array( 0, 1, 2, 'Promoted by the daily check.' ) );

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'mentor-one' );
WPCPM_Mentor_Checker_Profile::$completed = array( 'mentor-one' );
$GLOBALS['doing_cron']                   = true;
WPCPM_Mentor_Checker_Runner::run_cron();

ck( 'the scheduled run promotes and announces on its own',
    array( WPCPM_Airtable::$records['recMENTOR00000001']['fields']['Status'], count( $GLOBALS['posted'] ), last_line( texts()[0] ) ),
    array( 'Active', 1, 'Promoted by the daily check.' ) );

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'mentor-one' );
WPCPM_Mentor_Checker_Profile::$completed = array( 'mentor-one' );
WPCPM_Settings::$values                  = array( 'checker_cron_promotes' => false );
$GLOBALS['doing_cron']                   = true;
WPCPM_Mentor_Checker_Runner::run_cron();

ck( 'a scheduled run that only reports moves nobody and announces nobody',
    array( WPCPM_Airtable::$records['recMENTOR00000001']['fields']['Status'], $GLOBALS['posted'] ), array( 'Vetted - positive', array() ) );

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'mentor-one' );
promoted( 'two' );
$runner = new WPCPM_Mentor_Checker_Runner();
$runner->run_all( false );

ck( 'a report-only run still sends what an earlier message failed to, so with the daily check on a mentor waits a day at most',
    array( count( $GLOBALS['posted'] ), waiting() ), array( 1, array() ) );

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'mentor-one' );
WPCPM_Mentor_Checker_Profile::$completed = array( 'mentor-one' );
$_POST                                   = array( 'record_id' => 'recMENTOR00000001', 'nonce' => 'n' );
$answer                                  = ajax( 'ajax_promote' );

ck( 'Promote on one row: the mentor is moved and one message names them, promoted by the manager who pressed it',
    array( $answer->ok, WPCPM_Airtable::$records['recMENTOR00000001']['fields']['Status'], count( $GLOBALS['posted'] ), last_line( texts()[0] ) ),
    array( true, 'Active', 1, 'Promoted by Ada Admin.' ) );

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'mentor-one' );
$_POST  = array( 'record_id' => 'recMENTOR00000001', 'nonce' => 'n' );
$answer = ajax( 'ajax_promote' );

ck( 'Promote on a mentor the re-check no longer finds: nothing moved, nothing sent',
    array( WPCPM_Airtable::$records['recMENTOR00000001']['fields']['Status'], $GLOBALS['posted'] ), array( 'Vetted - positive', array() ) );

reset_world();
WPCPM_Airtable::mentor( 'recMENTOR00000001', 'Mentor One', 'mentor-one' );
WPCPM_Airtable::mentor( 'recMENTOR00000002', 'Mentor Two', 'mentor-two' );
WPCPM_Mentor_Checker_Profile::$completed = array( 'mentor-one', 'mentor-two' );
update_option(
	WPCPM_Mentor_Checker_Runner::RESULT_KEY,
	array(
		'run_id' => 'old',
		'rows'   => array(
			array( 'record_id' => 'recMENTOR00000001', 'action' => 'eligible' ),
			array( 'record_id' => 'recMENTOR00000002', 'action' => 'eligible' ),
		),
	)
);
$_POST  = array( 'nonce' => 'n' );
$answer = ajax( 'ajax_promote_all' );

ck( 'Promote all: both moved, one message naming both',
    array( $answer->ok, count( $GLOBALS['posted'] ), substr_count( texts()[0], '• ' ) ), array( true, 1, 2 ) );

echo "\n=== Every day ===\n";

reset_world();
WPCPM_Mentor_Checker_Runner::register_cron();

ck( 'switched on and not yet scheduled: scheduled daily', $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ]['schedule'], 'daily' );

$at = $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ]['timestamp'];
WPCPM_Mentor_Checker_Runner::register_cron();

ck( 'already daily: left where it is', $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ]['timestamp'], $at );

reset_world();
$GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ] = array( 'timestamp' => time() + 6 * DAY_IN_SECONDS, 'schedule' => 'weekly' );
WPCPM_Mentor_Checker_Runner::register_cron();

ck( 'a site still on the weekly event is moved to daily on its first request after the update, the next run within the hour rather than in six days',
    array( $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ]['schedule'], $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ]['timestamp'] <= time() + HOUR_IN_SECONDS ),
    array( 'daily', true ) );

reset_world();
$GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ] = array( 'timestamp' => time() + DAY_IN_SECONDS, 'schedule' => 'weekly' );
WPCPM_Mentor_Checker_Runner::sync_cron( true );

ck( 'a save that switches it on moves a weekly event to daily too', $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ]['schedule'], 'daily' );

WPCPM_Mentor_Checker_Runner::sync_cron( false );

ck( 'and switching it off takes it away', isset( $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ] ), false );

reset_world();
WPCPM_Settings::$values = array( 'checker_cron_enabled' => false );
WPCPM_Mentor_Checker_Runner::register_cron();

ck( 'switched off: nothing is scheduled', isset( $GLOBALS['cron'][ WPCPM_Mentor_Checker_Runner::CRON_HOOK ] ), false );

echo "\n=== Uninstall ===\n";

reset_world();
promoted( 'one' );
promoted( 'two' );
update_option( WPCPM_Mentor_Checker_Slack::LAST, array( 'time' => 1, 'ok' => true, 'count' => 1, 'error' => '' ) );
update_option( WPCPM_Mentor_Checker_Slack::LOCK, '1|x' );
update_option( 'wpcpm_checker_other', 'kept' );
( new WPCPM_Mentor_Checker() )->uninstall();

ck( 'uninstall forgets every waiting mentor, the last send and the lock, and nothing else of the checker\'s that it does not own',
    array_keys( $GLOBALS['opts'] ), array( 'wpcpm_checker_other' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S) of $total\n" : "ALL PASS ($total)\n" );
exit( $fail ? 1 : 0 );
