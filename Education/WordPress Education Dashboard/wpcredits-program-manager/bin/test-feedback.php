<?php
/**
 * The feedback surveys: the columns they write to, and what may reach Airtable.
 *
 * **The column names are pinned here against the base's own schema**, read from the Airtable
 * metadata endpoint on 6 August 2026. They are long, inconsistently spaced - Form 1's were created
 * without the space after `F1` - and one of them is 108 characters. A name that does not match its
 * column is not a visible failure: Airtable refuses the whole record, so one typo takes the other
 * eight answers down with it, and the student is told their feedback could not be sent with nothing
 * to say which question was to blame.
 *
 * The single-select choices are pinned for the same reason. `clean()` will not send a value that is
 * not on the list, so a list that has drifted from the base silently drops answers rather than
 * writing bad ones - which is safer, and completely invisible.
 *
 * **The permissions box is checked against the live client, not against a stand-in.** Two of its
 * questions decide whether a named student appears in a document their university sends out, and
 * the rule about them is a rule about *who is posting*: a program manager may fill in the rest of
 * the form on somebody's behalf and may not answer these. So the last three sections drive the real
 * `handle_save()` and the real `WPCPM_Airtable` over canned HTTP responses and read what would have
 * been sent to Airtable, because "the manager's answer was dropped" is only worth asserting on the
 * bytes that leave the site.
 *
 * Run from the plugin root:  php bin/test-feedback.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opts']  = array();
$GLOBALS['umeta'] = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0 ) { $this->ID = $id; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = 'publish';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $s, $p = null ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function absint( $v ) { return abs( (int) $v ); }
function add_action() {}
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u ); }
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['opts'][ 'T_' . $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { if ( ! empty( $GLOBALS['meta_false'][ $k ] ) ) { return false; } $GLOBALS['umeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
// Core's own, beside a user meta stand-in that unslashes what it is handed as core's does: the
// flash and the permissions stamp hand it a slashed copy, and it reads back as it was given.
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function get_users( $a = array() ) { return array(); }
function get_user_by( $f, $v ) { return new WP_User( (int) $v ); }
function get_current_user_id() { return $GLOBALS['uid'] ?? 0; }
function is_user_logged_in() { return ! empty( $GLOBALS['uid'] ); }
require_once __DIR__ . '/stubs/caps.php';
function is_admin() { return false; }
function get_post( $id = null ) { return null; }
function get_posts( $a = array() ) { return array(); }
function get_post_meta( $id, $k = '', $single = false ) { return $single ? '' : array(); }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_single_event() {} function wp_clear_scheduled_hook() {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_create_nonce( $a = -1 ) { return 'nonce'; }
function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $echo = true ) {
	$field = sprintf( '<input type="hidden" name="%s" value="%s" />', $n, wp_create_nonce( $a ) );
	if ( $echo ) { echo $field; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test double.
	return $field;
}
function checked( $a, $b = true, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? ' checked="checked"' : '';
	if ( $echo ) { echo $r; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal.
	return $r;
}
function selected( $a, $b = true, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? ' selected="selected"' : '';
	if ( $echo ) { echo $r; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal.
	return $r;
}
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function wp_die( $m = '', $c = 0 ) { throw new Denied( is_string( $m ) ? $m : 'died' ); }
function wp_safe_redirect( $u ) { $GLOBALS['redirect'] = $u; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function add_query_arg( $k, $v = null, $url = '' ) {
	if ( is_array( $k ) ) { $url = (string) $v; $pairs = $k; } else { $pairs = array( $k => $v ); }
	$join = false === strpos( $url, '?' ) ? '?' : '&';
	$bits = array();
	foreach ( $pairs as $key => $value ) { $bits[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value ); }
	return $url . $join . implode( '&', $bits );
}

/*
 * The HTTP layer, so the real Airtable client can be driven end to end. Every request is recorded
 * and answered from a queue; an empty queue throws, because a canned answer invented on the spot is
 * an assertion passing on a request nobody meant to make.
 */
$GLOBALS['sent']  = array();
$GLOBALS['queue'] = array();

function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['sent'][] = array( 'url' => $url, 'args' => $args );
	// Whether the student's consent stamp was already written when this request left (1.123.95).
	$GLOBALS['stamped_then'][] = defined( 'STUDENT' ) && isset( $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'] );

	// A clock that moves on with every request, when a check sets a step: how long the status may make a save take.
	if ( ! empty( $GLOBALS['clock_step'] ) ) {
		$GLOBALS['clock_now'] += $GLOBALS['clock_step'];
	}

	if ( empty( $GLOBALS['queue'] ) ) {
		throw new Exception( 'wp_remote_request() called with nothing queued: ' . $url );
	}

	$next = array_shift( $GLOBALS['queue'] );

	// A queued Throwable is a fault inside the call, which a check means to see thrown.
	if ( $next instanceof Throwable ) {
		throw $next;
	}

	return $next;
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['response']['code'] ?? 200 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $r, $h ) { return ''; }

/** `wp_die()` ends the request; here it ends the call, so the runner can say what was refused. */
class Denied extends Exception {}

/** The handler ends in `exit`; the rewrite below turns that into something catchable. */
class Left extends Exception {}
function wpcpm_test_exit() { throw new Left( (string) $GLOBALS['redirect'] ); }

// Declares add_filter() and apply_filters(), which run what is hooked: the program map is made of filters.
require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-settings.php';
require_once __DIR__ . '/../includes/class-wpcpm-flash.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-icons.php';
// The dashboards' one outcome printer, which Airtable's refusal is printed through (the fix wave of 1.123.58).
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
}
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
require_once __DIR__ . '/../includes/class-wpcpm-contribution-teams.php';
require_once __DIR__ . '/../includes/class-wpcpm-wporg-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-dashboard.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';

// The program map, which gates the surveys, is the compiled tracks' (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

/*
 * The semester report, as far as this module can see it: one call, expiring what that institution's
 * report has cached, so a consent taken back is gone from the next render rather than up to five
 * minutes later. That call is the whole of the seam between the two classes. Defined here rather
 * than required, because the report is a much larger file with dependencies of its own that have
 * nothing to do with a survey; the check further down keeps this stand-in honest against it.
 */
$GLOBALS['forgotten'] = array();

class WPCPM_Semester_Report {
	public static function forget( $institution ) {
		$GLOBALS['forgotten'][] = (string) $institution;
	}
}

/*
 * The audit trail, as far as the status a finished Form 3 sets reaches it (1.123.95): one row about the program's
 * own work. Defined here for the reason the report's stand-in is, and held to the real class further down.
 */
$GLOBALS['logged'] = array();

class WPCPM_Institution_Audit {
	const GROUND_SYSTEM = 'system';
	const EVIDENCE_LIVE = 'live';
	public static function record_program( array $entry ) {
		// A fault on the audit trail, after the status was written (round 4).
		if ( ! empty( $GLOBALS['audit_throw'] ) ) {
			throw new RuntimeException( 'The audit trail refused the row.' );
		}
		$GLOBALS['logged'][] = $entry;
		return 1;
	}
}

// The site sends no mail when a finished Form 3 sets the status (1.123.95): a mail would be one here.
$GLOBALS['mails'] = array();
function wp_mail() { $GLOBALS['mails'][] = func_get_args(); return true; }

// The roster index the Administrator Dashboard's card reads, and the status a finished Form 3 sets (1.123.95).
require_once __DIR__ . '/../includes/class-wpcpm-roster-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-graduation.php';

/*
 * The feedback class is loaded through a rewrite rather than required: `handle_save()` ends in
 * `exit`, and a test that cannot see past the exit cannot say what was written on the way there.
 */
$feedback_source = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-student-feedback.php' );
$feedback_source = preg_replace( '/^\s*exit;\s*$/m', "\t\twpcpm_test_exit();", $feedback_source );
$feedback_source = preg_replace( '/^<\?php/', '', $feedback_source, 1 );
eval( $feedback_source );

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
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
 * Run the private validator.
 *
 * @param mixed $raw  Posted value.
 * @param array $spec Field spec.
 * @return array{0:bool,1:mixed}
 */
function clean( $raw, array $spec ) {
	static $method = null;

	if ( null === $method ) {
		$method = new ReflectionMethod( 'WPCPM_Student_Feedback', 'clean_result' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
	}

	$result = $method->invoke( null, $raw, $spec );

	return array( $result['ok'], $result['value'] );
}

/*
 * The Feedback table's columns, as the base holds them. Copied from the schema endpoint rather
 * than from the plugin, which is the whole point: if the two ever disagree, one of them is wrong
 * and this test says so instead of Airtable saying it to a student.
 */
$schema = array(
	'F1- Overall experience so far',
	'F1- How confident do you feel contributing?',
	"F1 - How well is your mentor's support helping you make progress?",
	'F1 - How easy was it to get started?',
	'F1 - How clear was choosing your contribution and project?',
	'F1* - What specifically slowed you down or was unclear?',
	'F1 - What helped you most in getting started?',
	'F1 - What was hardest or most confusing?',
	'F1 - Are the course materials available in a language you are comfortable working in?',
	'F1 - Mentor',
	'F2 - Overall experience so far',
	'F2 - How confident do you feel contributing?',
	"F2 - How well is your mentor's support helping you make progress?",
	'F2 - Do you feel part of the WordPress community?',
	'F2 - What is making you feel part of the community, or what is missing?',
	"F2 - What was most helpful about your mentor's support?",
	"F2 - What could your mentor's support do better?",
	'F2 - Do the required hours feel achievable?',
	'F2* - What is making the hours hard to reach?',
	'F2 - Mentor',
	'F3 - Overall experience so far',
	'F3 - How confident do you feel contributing?',
	"F3 - How well is your mentor's support helping you make progress?",
	'F3 - How impactful do you feel your contributions were?',
	'F3 - Do you feel part of the WordPress community?',
	'F3 - What made you feel part of the community, or what was missing?',
	'F3 - How likely are you to recommend WP Credits to another student?',
	'F3 - What would have made you more likely to recommend?',
	'F3 - How likely are you to keep contributing to WordPress?',
	'F3 - How much did your mentor influence your intention to keep contributing to WordPress after the program?',
	'F3 - What would make you keep contributing?',
	'F3 - One example of a contribution you are proud of',
	'F3 - New skills, knowledge or experiences you gained',
	'F3 - One main change that would improve the program',
	'F3 - May we share a quote about your experience publicly? If so, please share your thoughts below',
	'F3 - Yes, you can contact me about WordPress events, learning and skill building opportunities, and career opportunities.',
	// Created on 2 September 2026 for the Institutions module. The wording is the base's, down to
	// the colon after "Report", and it is what `bin/fixtures/feedback-table-fields.json` holds.
	'F3 - Report: my institution may list me in its semester report',
	'F3 - Report: my institution may quote my feedback in its semester report',
	'F3 - Mentor',
	'F4 - How far did you get?',
	'F4 - What stopped you?',
	'F4 - What could we have done differently?',
	'F4 - Would you consider coming back?',
	'Name',
	'Email',
	'Course',
);

/** The choices each single-select column offers, as the base holds them. */
$choices = array(
	'F1 - How clear was choosing your contribution and project?' => array( 'Very clear', 'Clear enough', 'Neutral', 'Somewhat unclear', 'Very unclear' ),
	'F1 - Are the course materials available in a language you are comfortable working in?' => array( 'Yes', 'Partly', 'No' ),
	'F2 - Do the required hours feel achievable?' => array( 'Yes', 'Unsure', 'No' ),
	'F3 - How likely are you to recommend WP Credits to another student?' => array( 'Unlikely', 'Neither likely nor unlikely', 'Likely' ),
	'F3 - How likely are you to keep contributing to WordPress?' => array( 'Unlikely', 'Neither likely nor unlikely', 'Likely' ),
	'F4 - How far did you get?' => array( 'Never started', 'Started the course', 'Chose a contribution', 'Started contributing' ),
	'F4 - What stopped you?' => array( 'Did not know where to start', 'No time', 'Not what I expected', 'Not enough support', 'Not relevant to my studies', 'Technical problems', 'Other' ),
	'F4 - Would you consider coming back?' => array( 'Yes', 'No', 'Maybe' ),
	'F3 - Report: my institution may list me in its semester report' => array( 'Yes, with my name', 'Yes, by my blog address only', 'No' ),
	'F3 - Report: my institution may quote my feedback in its semester report' => array( 'Yes, with my name', 'Yes, without my name', 'No' ),
);

$forms = WPCPM_Student_Feedback::forms();

echo "=== Every question writes to a column that exists ===\n";

$unknown = array();

foreach ( $forms as $key => $form ) {
	foreach ( array_keys( $form['fields'] ) as $name ) {
		if ( ! in_array( $name, $schema, true ) ) {
			$unknown[] = $key . ': ' . $name;
		}
	}

	if ( ! empty( $form['mentor'] ) && ! in_array( $form['mentor'], $schema, true ) ) {
		$unknown[] = $key . ' mentor: ' . $form['mentor'];
	}
}

ck( 'no question names a column the base does not have', $unknown, array() );
ck( 'all four forms are defined', array_keys( $forms ), array( 'f1', 'f2', 'f3', 'f4' ) );

// The counts the July 2026 proposal settled on. A question quietly added or dropped changes what
// the analysis can compare, so the number is asserted rather than left to drift.
ck( 'Form 1 asks nine questions', count( $forms['f1']['fields'] ), 9 );
ck( 'Form 2 asks nine questions', count( $forms['f2']['fields'] ), 9 );
ck( 'Form 3 asks eighteen, permissions included', count( $forms['f3']['fields'] ), 18 );
ck( 'Form 4 asks four', count( $forms['f4']['fields'] ), 4 );

echo "\n=== The retired questions stay retired ===\n";

// Each of these was dropped by the analysis for a stated reason - duplication, or the highest rate
// of empty answers. They still exist as columns, so nothing but this stops one being added back.
$retired = array(
	'How to make onboarding smoother',
	'Project/website progress satisfaction',
	'Additional resources, guidance, or support that would make you feel more effective',
	'Interaction with mentor pre-website',
	'Interaction with mentor post-website',
	'Why not likely recommend?',
	'Why not likely keep participating?',
	'Rate your mentor’s support',
);

$asked = array();

foreach ( $forms as $form ) {
	$asked = array_merge( $asked, array_keys( $form['fields'] ) );
}

ck( 'none of the eight retired questions is asked', array_values( array_intersect( $retired, $asked ) ), array() );

echo "\n=== The anchors really are the same question ===\n";

/*
 * The three anchors are the reason the surveys are split by stage at all: they repeat word for word
 * so a student's answers can be plotted over time. A label or a scale that differs between forms
 * makes the comparison meaningless, and nothing about the rendered page would look wrong.
 */
$anchor_specs = array();

foreach ( array( 'f1', 'f2', 'f3' ) as $key ) {
	$specs = array();

	foreach ( $forms[ $key ]['fields'] as $spec ) {
		if ( ! empty( $spec['anchor'] ) ) {
			$specs[] = array( $spec['label'], $spec['type'], $spec['max'], $spec['ends'] );
		}
	}

	$anchor_specs[ $key ] = $specs;
}

ck( 'three anchors in each of the three stage forms',
    array_map( 'count', $anchor_specs ), array( 'f1' => 3, 'f2' => 3, 'f3' => 3 ) );
ck( 'asked identically in Forms 1 and 2', $anchor_specs['f1'], $anchor_specs['f2'] );
ck( 'and identically in Form 3', $anchor_specs['f1'], $anchor_specs['f3'] );
ck( 'the exit survey has none - it asks a different thing entirely',
    count( array_filter( $forms['f4']['fields'], function ( $s ) { return ! empty( $s['anchor'] ); } ) ), 0 );

echo "\n=== The conditional follow-ups ===\n";

$conditionals = array();

foreach ( $forms as $key => $form ) {
	foreach ( $form['fields'] as $name => $spec ) {
		if ( empty( $spec['when'] ) ) {
			continue;
		}

		$conditionals[] = $name;

		foreach ( $spec['when'] as $rule ) {
			// A rule pointing at a question in another form - or at a column that was renamed -
			// never fires, so the follow-up would be hidden for everybody and nobody would notice.
			ck( sprintf( 'the rule on "%s" watches a question in the same form', $name ),
			    isset( $form['fields'][ $rule['field'] ] ), true );

			$trigger = $form['fields'][ $rule['field'] ] ?? array();

			if ( isset( $trigger['choices'] ) ) {
				ck( sprintf( '  and every value it waits for is a real choice (%s)', $rule['field'] ),
				    array_values( array_diff( $rule['values'], $trigger['choices'] ) ), array() );
			} elseif ( 'rating' === ( $trigger['type'] ?? '' ) ) {
				$max = (int) $trigger['max'];
				$bad = array_filter( $rule['values'], function ( $v ) use ( $max ) { return (int) $v < 1 || (int) $v > $max; } );

				ck( sprintf( '  and every value it waits for is on the scale (%s)', $rule['field'] ),
				    array_values( $bad ), array() );
			}
		}
	}
}

ck( 'exactly the two follow-ups the analysis asked for',
    $conditionals,
    array( 'F1* - What specifically slowed you down or was unclear?', 'F2* - What is making the hours hard to reach?' ) );

echo "\n=== Who is asked what ===\n";

ck( 'a student on the 150h track gets the three stage forms',
    WPCPM_Student_Feedback::forms_for( array( 'program' => 'In Sensei' ) ), array( 'f1', 'f2', 'f3' ) );
ck( 'so does one on the 50h track',
    WPCPM_Student_Feedback::forms_for( array( 'program' => 'In Sensei 50h' ) ), array( 'f1', 'f2', 'f3' ) );

// A graduate is asked the final form, not an exit survey: they did not leave, they finished.
ck( 'a graduate is not handed the exit survey',
    WPCPM_Student_Feedback::forms_for( array( 'program' => 'Graduate', 'is_past' => true ) ), array( 'f1', 'f2', 'f3' ) );
ck( 'somebody who dropped out is asked why, and only that',
    WPCPM_Student_Feedback::forms_for( array( 'program' => 'Dropped out', 'is_past' => true ) ), array( 'f4' ) );
ck( 'and so is anyone else who did not finish',
    WPCPM_Student_Feedback::forms_for( array( 'program' => 'Not moving forward', 'is_past' => true ) ), array( 'f4' ) );

echo "\n=== What may reach Airtable ===\n";

$rating = array( 'type' => 'rating', 'max' => 5 );

ck( 'a rating on the scale is kept', clean( '4', $rating ), array( true, 4 ) );
ck( 'the ends of the scale are on it', array( clean( '1', $rating ), clean( '5', $rating ) ), array( array( true, 1 ), array( true, 5 ) ) );
ck( 'nothing chosen clears it rather than failing', clean( '', $rating ), array( true, null ) );
ck( 'a six is refused', clean( '6', $rating ), array( false, null ) );
ck( 'a zero is refused', clean( '0', $rating ), array( false, null ) );
ck( 'and so is a word', clean( 'five', $rating ), array( false, null ) );

$select = array( 'type' => 'select', 'choices' => $choices['F2 - Do the required hours feel achievable?'] );

ck( 'a real choice is kept', clean( 'Unsure', $select ), array( true, 'Unsure' ) );
ck( 'no answer clears it', clean( '', $select ), array( true, null ) );

// The security-relevant one: a hand-edited form must not be able to add an option to the column,
// which is what Airtable does with `typecast` on and an unknown value.
ck( 'a value that is not on the list is refused', clean( 'Definitely not', $select ), array( false, null ) );
ck( 'and case matters, because Airtable\'s choices do', clean( 'unsure', $select ), array( false, null ) );

$checkbox = array( 'type' => 'checkbox' );

ck( 'a ticked consent box is true', clean( '1', $checkbox ), array( true, true ) );
// The half that matters: consent has to be withdrawable, and an unticked box posts nothing at all.
ck( 'an unticked one is false, not absent', clean( '', $checkbox ), array( true, false ) );

$text = array( 'type' => 'textarea' );

ck( 'an answer is kept', clean( '  Slack was slow  ', $text ), array( true, 'Slack was slow' ) );
ck( 'an emptied box clears it', clean( '', $text ), array( true, '' ) );
ck( 'a very long answer is cut rather than refused',
    strlen( clean( str_repeat( 'x', 6000 ), $text )[1] ), 5000 );
ck( 'an array where prose belongs is refused', clean( array( 'x' ), $text ), array( false, null ) );

echo "\n=== Form keys ===\n";

$keys = array();

foreach ( $forms as $form ) {
	foreach ( array_keys( $form['fields'] ) as $name ) {
		$keys[] = WPCPM_Student_Feedback::key( $name );
	}
}

ck( 'every question has a distinct key', count( array_unique( $keys ) ), count( $keys ) );
ck( 'keys are safe in a form name and an attribute selector', count( preg_grep( '/^[a-z0-9]+$/', $keys ) ), count( $keys ) );

// The two forms whose columns differ only by their `F1`/`F2` prefix would collide under a scheme
// that stripped it, and the answers would be written to the wrong stage.
ck( 'the anchors of two stages do not share a key',
    WPCPM_Student_Feedback::key( 'F1- Overall experience so far' ) === WPCPM_Student_Feedback::key( 'F2 - Overall experience so far' ),
    false );

echo "\n=== One stage at a time ===\n";

/*
 * A form appears once the one before it is finished. The surveys are meant to be answered *at* each
 * stage - three repeated questions only mean something if the answers are months apart - and a
 * student who opens all three on their last day gives three copies of one opinion.
 *
 * The rule is asserted through `unlocked()` rather than through the rendered page, because what is
 * being checked is which forms a student can reach, and that is a decision, not a layout.
 */

/**
 * Answers that finish a form, with the conditional included only when it applies.
 *
 * @param string $key   Form key.
 * @param array  $forms All forms.
 * @param array  $skip  Column names to leave blank.
 * @return array
 */
function fill( $key, array $forms, array $skip = array() ) {
	$out = array();

	foreach ( $forms[ $key ]['fields'] as $name => $spec ) {
		if ( in_array( $name, $skip, true ) ) {
			continue;
		}

		if ( isset( $spec['group'] ) && 'permissions' === $spec['group'] ) {
			continue;
		}

		// The follow-ups are only asked when the answer above them was poor. Left blank here, and
		// the ratings below are deliberately good, so they do not apply.
		if ( ! empty( $spec['when'] ) ) {
			continue;
		}

		$type = isset( $spec['type'] ) ? $spec['type'] : 'textarea';

		if ( 'rating' === $type ) {
			$out[ $name ] = 4;
		} elseif ( 'select' === $type ) {
			$out[ $name ] = $spec['choices'][0];
		} else {
			$out[ $name ] = 'an answer';
		}
	}

	return $out;
}

$stages = array( 'f1', 'f2', 'f3' );

ck( 'with nothing answered, only the first form is open',
    WPCPM_Student_Feedback::unlocked( $stages, $forms, array() ), array( 'f1' ) );

$part = fill( 'f1', $forms );
array_pop( $part );

ck( 'a part-finished first form does not open the second',
    WPCPM_Student_Feedback::unlocked( $stages, $forms, $part ), array( 'f1' ) );

$one = fill( 'f1', $forms );

ck( 'finishing the first opens the second, and only the second',
    WPCPM_Student_Feedback::unlocked( $stages, $forms, $one ), array( 'f1', 'f2' ) );

$two = $one + fill( 'f2', $forms );

ck( 'finishing the second opens the third',
    WPCPM_Student_Feedback::unlocked( $stages, $forms, $two ), array( 'f1', 'f2', 'f3' ) );

// The conditional is the case that would otherwise lock a student out for ever: it is not asked, so
// it cannot be answered, so a form that counted it would never be finished.
$low = fill( 'f1', $forms );
$low['F1 - How easy was it to get started?'] = 1;

ck( 'a poor answer asks the follow-up, and the form is unfinished until it is answered',
    WPCPM_Student_Feedback::unlocked( $stages, $forms, $low ), array( 'f1' ) );

$low['F1* - What specifically slowed you down or was unclear?'] = 'The Slack invite took a week.';

ck( 'answering the follow-up finishes it',
    WPCPM_Student_Feedback::unlocked( $stages, $forms, $low ), array( 'f1', 'f2' ) );

// Form 3's permissions say they are optional. A student who declines both has still finished.
$all = $two + fill( 'f3', $forms );

ck( 'the optional permissions are not required to finish the last form',
    WPCPM_Student_Feedback::is_complete( $forms['f3'], $all ), true );

// Never take away a form somebody has already written in.
$stranded = array( 'F2 - Overall experience so far' => 3 );

ck( 'a form already started stays open even with the one before it unfinished',
    WPCPM_Student_Feedback::unlocked( $stages, $forms, $stranded ), array( 'f1', 'f2' ) );

// The exit survey is on its own list and waits for nothing.
ck( 'the exit survey is never gated',
    WPCPM_Student_Feedback::unlocked( array( 'f4' ), $forms, array() ), array( 'f4' ) );

echo "\n=== The two questions the semester report reads ===\n";

$list_column  = 'F3 - Report: my institution may list me in its semester report';
$quote_column = 'F3 - Report: my institution may quote my feedback in its semester report';
$f3           = $forms['f3']['fields'];

ck( 'both are asked, in the last form', array( isset( $f3[ $list_column ] ), isset( $f3[ $quote_column ] ) ), array( true, true ) );
ck( 'and both are in the permissions box, which is what fences them off',
    array( $f3[ $list_column ]['group'], $f3[ $quote_column ]['group'] ), array( 'permissions', 'permissions' ) );

/*
 * The choices, against the fixture the base itself was read into. `update_records()` sends no
 * `typecast`, so a choice spelled any other way is a 422 for the whole record: the student is told
 * their answers could not be sent, and the eight real answers on the same form go down with the
 * permission they were trying to give.
 */
$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/feedback-table-fields.json' ), true );
$pinned  = isset( $fixture['choices'] ) ? $fixture['choices'] : array();

ck( 'the listing choices are the base\'s, byte for byte',
    $f3[ $list_column ]['choices'], isset( $pinned[ $list_column ] ) ? $pinned[ $list_column ] : null );
ck( 'and so are the quoting choices',
    $f3[ $quote_column ]['choices'], isset( $pinned[ $quote_column ] ) ? $pinned[ $quote_column ] : null );

// The label is what the student actually reads, and the column name is written from the school's
// side ("my institution may list me"). Asked back in the second person it is a question about the
// person answering it, which is what a consent question has to be.
ck( 'each is asked as a question, of the person whose name it is',
    array_map(
        function ( $spec ) {
            return '?' === substr( $spec['label'], -1 ) && false !== strpos( $spec['label'], 'your institution' );
        },
        array( $f3[ $list_column ], $f3[ $quote_column ] )
    ),
    array( true, true ) );

// The report is a document, not a survey: a permission left blank must not read as an unfinished
// form, or the student is nagged for an answer they have deliberately not given.
ck( 'neither counts towards finishing the form', WPCPM_Student_Feedback::is_complete( $forms['f3'], $all ), true );

echo "\n=== Which of several rows for one address is this student's ===\n";

define( 'STUDENT', 7 );
define( 'MANAGER', 3 );
define( 'HERE', 'recUEK000000000AA' );
define( 'THERE', 'recAGH000000000BB' );

$GLOBALS['opts']['wpcpm_settings'] = array(
	'api_token'      => 'pat-test',
	'base_id'        => 'appTest',
	'feedback_table' => 'tblFeedback',
);

/** A canned HTTP API response, in the shape `wp_remote_request()` returns. */
function response( $code, array $body = array() ) {
	return array( 'response' => array( 'code' => $code ), 'headers' => array(), 'body' => json_encode( $body ) );
}

/** The student's cached program row: the address every one of these lookups is keyed on. */
function program_row() {
	return array( 'email' => 'ana@institution-3.example', 'name' => 'Ana Nowak', 'program' => 'In Sensei' );
}

/** One Feedback row as Airtable returns it, with or without an institution link. */
function row( $id, $institution = '' ) {
	$fields = array( 'Email' => 'ana@institution-3.example' );

	if ( '' !== $institution ) {
		$fields['Institution'] = array( $institution );
	}

	return array( 'id' => $id, 'fields' => $fields );
}

/**
 * Look the student's row up from scratch over canned responses.
 *
 * The remembered record ID is cleared first: it short-circuits the lookup, which is the whole
 * point of it, and would make every case here return the answer from the case before.
 *
 * @param string $institution What the site says the student's institution is, or ''.
 * @param array  $rows        What the address lookup returns.
 * @param bool   $create      Whether a row may be created.
 * @param array  $created     What the create call returns.
 * @return string|WP_Error
 */
function lookup( $institution, array $rows, $create = false, array $created = array() ) {
	$GLOBALS['umeta'][ STUDENT ] = array( 'wpcpm_student_program' => program_row() );

	if ( '' !== $institution ) {
		$GLOBALS['umeta'][ STUDENT ]['wpcpm_student_institution'] = $institution;
	}

	$GLOBALS['sent']  = array();
	$GLOBALS['queue'] = array( response( 200, array( 'records' => $rows ) ) );

	if ( $create ) {
		$GLOBALS['queue'][] = response( 200, array( 'records' => $created ) );
	}

	return WPCPM_Student_Feedback::record_for( new WP_User( STUDENT ), program_row(), $create );
}

/** The fields one of the recorded requests carried. */
function sent_fields( $i ) {
	$body = json_decode( $GLOBALS['sent'][ $i ]['args']['body'], true );

	return isset( $body['records'][0]['fields'] ) ? $body['records'][0]['fields'] : array();
}

ck( 'one row, and it is the answer', lookup( HERE, array( row( 'recFB0000000000AA', HERE ) ) ), 'recFB0000000000AA' );
ck( 'looked up in one request', count( $GLOBALS['sent'] ), 1 );

// The extra column rides along on the request that was being made anyway. A second read to find
// out which duplicate is which would be a second call to a base with a five-a-second ceiling, on a
// page a student is waiting on.
ck( 'which asked for Institution alongside Email',
    (bool) preg_match( '/fields%5B%5D=Email&fields%5B%5D=Institution/', $GLOBALS['sent'][0]['url'] ), true );

// The case this rule exists for. A consent written to a row linked to another school is never read
// by the report that wanted it, so the student is left out of a report they said yes to.
ck( 'among duplicates, the row linked to this student\'s own institution wins',
    lookup( HERE, array( row( 'recFB0000000000AA', THERE ), row( 'recFB0000000000BB', HERE ) ) ), 'recFB0000000000BB' );

// An unlinked row is kept by every institution's report, so it is the next best thing.
ck( 'failing that, a row with no link at all beats one linked elsewhere',
    lookup( HERE, array( row( 'recFB0000000000AA', THERE ), row( 'recFB0000000000BB' ) ) ), 'recFB0000000000BB' );

ck( 'with nothing to prefer, the first row is still the answer',
    lookup( HERE, array( row( 'recFB0000000000AA', THERE ), row( 'recFB0000000000BB', THERE ) ) ), 'recFB0000000000AA' );

ck( 'and a student the site cannot place keeps the old behaviour',
    lookup( '', array( row( 'recFB0000000000AA', THERE ), row( 'recFB0000000000BB' ) ) ), 'recFB0000000000AA' );

ck( 'the row it settled on is remembered, so this is one lookup per student ever',
    $GLOBALS['umeta'][ STUDENT ]['wpcpm_feedback_record'], 'recFB0000000000AA' );

echo "\n=== A new row is linked to the institution on the way in ===\n";

$made = lookup( HERE, array(), true, array( array( 'id' => 'recFB0000000000CC' ) ) );

ck( 'the row is created', $made, 'recFB0000000000CC' );
ck( 'with the institution linked, as a record ID in an array', sent_fields( 1 )['Institution'], array( HERE ) );
ck( 'and the rest of the row as before',
    array_diff_key( sent_fields( 1 ), array( 'Institution' => 1 ) ),
    array( 'Name' => 'Ana Nowak', 'Email' => 'ana@institution-3.example', 'Course' => 'In Sensei' ) );

// An unlinked row is read by every institution's report. That is the pre-existing state of 834
// rows and is not this module's to change, but a row it creates itself has no such excuse - so the
// only reason to leave the link out is not knowing where the student belongs.
$made = lookup( '', array(), true, array( array( 'id' => 'recFB0000000000DD' ) ) );

ck( 'a student with no institution on file creates a row with no link',
    array_key_exists( 'Institution', sent_fields( 1 ) ), false );

// A name in the link column would be refused for the whole record, taking the row with it.
$GLOBALS['umeta'][ STUDENT ]                                 = array( 'wpcpm_student_program' => program_row() );
$GLOBALS['umeta'][ STUDENT ]['wpcpm_student_institution']    = 'Uniwersytet Przykładowy';
$GLOBALS['sent']                                             = array();
$GLOBALS['queue']                                            = array(
	response( 200, array( 'records' => array() ) ),
	response( 200, array( 'records' => array( array( 'id' => 'recFB0000000000EE' ) ) ) ),
);

WPCPM_Student_Feedback::record_for( new WP_User( STUDENT ), program_row(), true );

ck( 'and so does one whose stamp is a name rather than a record ID',
    array_key_exists( 'Institution', sent_fields( 1 ) ), false );

echo "\n=== The remembered row is believed only when it was chosen with what the site knows now ===\n";

/*
 * Every student who opened the feedback section before `preferred()` existed carries a stamp
 * chosen by "the first row Airtable returned". For the fifty duplicated addresses that is a
 * coin toss, and a stamp read before `preferred()` runs means `preferred()` never runs for the
 * people it exists for: a withdrawal typed by the student landed on the other school's row while
 * their own school's report went on reading the row that said yes. So the stamp is re-resolved,
 * once, when the placement it was made with differs from the placement the site holds now.
 */

// An old stamp pointing at the other school's row, no record of what it was chosen with, and
// a student the site can now place: one lookup, this school's row wins, both stamps written.
$GLOBALS['umeta'][ STUDENT ] = array(
	'wpcpm_student_program'     => program_row(),
	'wpcpm_student_institution' => HERE,
	'wpcpm_feedback_record'     => 'recFB0000000000BB',
);
$GLOBALS['queue'] = array( response( 200, array( 'records' => array( row( 'recFB0000000000BB', THERE ), row( 'recFB0000000000AA', HERE ) ) ) ) );

$got = WPCPM_Student_Feedback::record_for( new WP_User( STUDENT ), program_row() );

ck( 'a stale stamp on a placed student is resolved again rather than believed', $got, 'recFB0000000000AA' );
ck( 'and the lookup was actually made', count( $GLOBALS['queue'] ), 0 );
ck( 'the new stamp names this school\'s row', $GLOBALS['umeta'][ STUDENT ]['wpcpm_feedback_record'], 'recFB0000000000AA' );
ck( 'and records the placement it was chosen with', $GLOBALS['umeta'][ STUDENT ]['wpcpm_feedback_record_for'], HERE );

// The same student again: the stamp matches the placement, so no read is spent.
$GLOBALS['queue'] = array();
ck( 'a stamp made with the current placement is believed without a lookup',
    WPCPM_Student_Feedback::record_for( new WP_User( STUDENT ), program_row() ), 'recFB0000000000AA' );

// A student the site cannot place keeps whatever stamp they have: there is nothing to prefer.
$GLOBALS['umeta'][ STUDENT ] = array(
	'wpcpm_student_program' => program_row(),
	'wpcpm_feedback_record' => 'recFB0000000000BB',
);
$GLOBALS['queue'] = array();
ck( 'an unplaced student\'s stamp stands, and costs no read',
    WPCPM_Student_Feedback::record_for( new WP_User( STUDENT ), program_row() ), 'recFB0000000000BB' );

// Placed somewhere else since: the stamp was chosen against THERE and the site now says HERE.
$GLOBALS['umeta'][ STUDENT ] = array(
	'wpcpm_student_program'     => program_row(),
	'wpcpm_student_institution' => HERE,
	'wpcpm_feedback_record'     => 'recFB0000000000BB',
	'wpcpm_feedback_record_for' => THERE,
);
$GLOBALS['queue'] = array( response( 200, array( 'records' => array( row( 'recFB0000000000BB', THERE ), row( 'recFB0000000000AA', HERE ) ) ) ) );
ck( 'a stamp chosen against a different placement is resolved again',
    WPCPM_Student_Feedback::record_for( new WP_User( STUDENT ), program_row() ), 'recFB0000000000AA' );

// The address has no row at all any more: the stamp is not evidence, and a reader gets "none".
$GLOBALS['umeta'][ STUDENT ] = array(
	'wpcpm_student_program'     => program_row(),
	'wpcpm_student_institution' => HERE,
	'wpcpm_feedback_record'     => 'recFB0000000000BB',
);
$GLOBALS['queue'] = array( response( 200, array( 'records' => array() ) ) );
$none = WPCPM_Student_Feedback::record_for( new WP_User( STUDENT ), program_row() );
ck( 'a stamp for a row that no longer exists is not returned as if it did', is_wp_error( $none ) && 'wpcpm_feedback_none' === $none->get_error_code(), true );

echo "\n=== A permission is the student's own to give ===\n";

/**
 * Post Form 3 as somebody, and report what reached Airtable.
 *
 * @param int   $actor   Who is logged in.
 * @param bool  $manage  Whether they hold the manage capability.
 * @param array $answers Column name => posted value.
 * @param bool  $expect  Whether a write is expected, so a response is queued for it.
 * @param string $form   Which form is being sent.
 * @return string The flash status the actor was left with.
 */
function post_form( $actor, $manage, array $answers, $expect = true, $form = 'f3' ) {
	$GLOBALS['uid']   = $actor;
	$GLOBALS['caps']  = $manage;
	$GLOBALS['sent']  = array();
	$GLOBALS['queue'] = $expect ? array( response( 200, array( 'records' => array( array( 'id' => 'recFB0000000000AA' ) ) ) ) ) : array();
	$GLOBALS['redirect'] = '';

	// The row is already known, so the save is one PATCH and nothing else: what is being checked
	// here is what that PATCH carries, not how the row was found. The stamp is left where a
	// previous case put it, because whether a save rewrites it is one of the things being checked.
	$stamp = isset( $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'] )
		? $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions']
		: null;

	$GLOBALS['umeta'][ STUDENT ] = array(
		'wpcpm_student_program'     => program_row(),
		'wpcpm_student_institution' => HERE,
		'wpcpm_feedback_record'     => 'recFB0000000000AA',
		// Chosen with the placement above, so the stamp is believed and the save is one PATCH.
		'wpcpm_feedback_record_for' => HERE,
	);

	if ( null !== $stamp ) {
		$GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'] = $stamp;
	}

	// The row's answers before the save, from the record cache, so the save reads nothing first: Form 3's state before
	// it is what decides whether it was finished by it (1.123.95). Empty, as for a student who has answered nothing yet.
	$GLOBALS['opts'][ 'T_wpcpm_feedback_' . md5( 'recFB0000000000AA' ) ] = array();

	unset( $GLOBALS['umeta'][ MANAGER ] );

	$posted = array();

	foreach ( $answers as $column => $value ) {
		$posted[ WPCPM_Student_Feedback::key( $column ) ] = $value;
	}

	$_POST = array( 'student' => (string) STUDENT, 'form' => $form, 'feedback' => $posted );

	try {
		WPCPM_Student_Feedback::handle_save();
	} catch ( Left $e ) {
		// Where the handler meant to end.
	}

	$pending = isset( $GLOBALS['umeta'][ $actor ]['wpcpm_flash'] ) ? $GLOBALS['umeta'][ $actor ]['wpcpm_flash'] : array();

	return isset( $pending['feedback'] ) ? (string) $pending['feedback'] : '';
}

/** What the PATCH this save sent carried, or an empty array when it sent none. */
function written() {
	return empty( $GLOBALS['sent'] ) ? array() : sent_fields( 0 );
}

$proud   = 'F3 - One example of a contribution you are proud of';
$contact = 'F3 - Yes, you can contact me about WordPress events, learning and skill building opportunities, and career opportunities.';

// What a browser actually sends for Form 3: every select posts, whether or not it was touched, and
// the ticked checkbox posts a 1. The permissions box travels as a whole, which is why the stamp
// below can be replaced rather than merged.
$answers = array(
	$proud        => 'I fixed a broken link in the docs.',
	$list_column  => 'Yes, with my name',
	$quote_column => 'Yes, without my name',
	$contact      => '1',
);

$GLOBALS['forgotten'] = array();

ck( 'the student\'s own save goes through', post_form( STUDENT, false, $answers ), 'feedback-saved' );

$cells = written();

ck( 'and carries both permissions, spelled as the base spells them',
    array( $cells[ $list_column ], $cells[ $quote_column ] ),
    array( 'Yes, with my name', 'Yes, without my name' ) );
ck( 'along with the answer above them', $cells[ $proud ], 'I fixed a broken link in the docs.' );

// The stamp: what was said, the wording it was said to, and when. Airtable stays the authority on
// what may be printed; this is the site's record that the question was asked at all.
$stamp = $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'];

ck( 'the answers are stamped on the student', $stamp['answers'][ $list_column ], 'Yes, with my name' );
ck( 'with the wording they were shown, not the column name',
    $stamp['wording'][ $list_column ], $forms['f3']['fields'][ $list_column ]['label'] );
ck( 'and a time', is_int( $stamp['time'] ) && $stamp['time'] > 0, true );

// The whole box, not only the two the report reads: they are asked together, and half a record of
// what somebody agreed to is worse than none.
ck( 'every permission the box carried is in it',
    array_keys( $stamp['answers'] ), array( $contact, $list_column, $quote_column ) );

// The withdrawal has to reach a report that was generated before it. The report caches what it read
// from Airtable for five minutes per institution, which is exactly long enough to print a name
// somebody has just taken back.
ck( 'and saving it expires that institution\'s cached report reads, and only that one\'s',
    $GLOBALS['forgotten'], array( HERE ) );

$GLOBALS['forgotten'] = array();

// Form 1 has no permissions box at all, so it stamps nothing and expires nothing. The stamp from
// the save above has to survive it: an earlier consent is not withdrawn by answering a later
// question about something else.
post_form( STUDENT, false, array( 'F1 - What helped you most in getting started?' => 'The Slack channel.' ), true, 'f1' );

ck( 'a form with no permissions box expires nothing', $GLOBALS['forgotten'], array() );
ck( 'and leaves the stamp where it was',
    $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'], $stamp );

// The call this test stands in for is the report's, and the two halves were written in parallel: a
// method renamed on that side would leave a withdrawal sitting behind a cache nobody expires, with
// every test here still green.
$report_file = __DIR__ . '/../includes/modules/class-wpcpm-semester-report.php';

if ( file_exists( $report_file ) ) {
	ck( 'the report really does offer the forget() this module calls',
	    (bool) preg_match( '/public static function forget\(/', (string) file_get_contents( $report_file ) ), true );
}

echo "\n=== The negative: a manager may fill in the form, never the permissions ===\n";

// The student's own stamp is cleared first, so "nothing was stamped" means nothing at all rather
// than an answer from an earlier case still standing.
unset( $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'] );

$status = post_form( MANAGER, true, $answers );
$cells  = written();

ck( 'the manager\'s save is accepted', $status, 'feedback-not-yours' );
ck( 'and it says which part was not', is_array( WPCPM_Student_Feedback::message( $status ) ), true );
ck( 'the answer they were entitled to type in is written', $cells[ $proud ], 'I fixed a broken link in the docs.' );
ck( 'the listing permission is not', array_key_exists( $list_column, $cells ), false );
ck( 'nor the quoting one', array_key_exists( $quote_column, $cells ), false );
ck( 'and nothing is stamped on the student',
    isset( $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'] ), false );

// The checkbox is the trap: an absent one reads as "unticked" everywhere else in this handler, so
// a manager saving a form they were shown with the box disabled would withdraw a consent by
// touching nothing at all.
ck( 'a standing consent the manager was not shown is not withdrawn',
    array_key_exists( $contact, $cells ), false );

// A student's own save still reads the absent checkbox as false, which is what makes the consent
// withdrawable at all: unticking a box posts nothing, so silence from the person it belongs to has
// to mean no.
$unticked = $answers;
unset( $unticked[ $contact ] );

post_form( STUDENT, false, $unticked );

ck( 'while the student\'s own save can still withdraw it', written()[ $contact ], false );

$status = post_form( MANAGER, true, array( $list_column => 'Yes, with my name' ), false );

ck( 'a post carrying nothing but somebody else\'s permission writes nothing at all',
    array( $status, count( $GLOBALS['sent'] ) ), array( 'feedback-not-yours', 0 ) );

// The oldest field in the box is under the same rule, and it is the one that matters most: the
// semester report prints this text as the student's own words, in quotation marks, in a document
// their university sends out. Typed in by somebody else it would be words put in their mouth.
$quote_text = 'F3 - May we share a quote about your experience publicly? If so, please share your thoughts below';

post_form( MANAGER, true, array( $proud => 'Typed up from the paper form.', $quote_text => 'She said it was great.' ) );

ck( 'a quote typed in by a manager never reaches the row', array_key_exists( $quote_text, written() ), false );
ck( 'though the answer beside it does', written()[ $proud ], 'Typed up from the paper form.' );

// The named flash is not cried wolf: a manager saving the rest of the form gets the ordinary
// "saved", or they would learn to ignore the message that matters.
ck( 'a manager who touched no permission is simply told it saved',
    post_form( MANAGER, true, array( $proud => 'Typed up from the paper form.' ) ), 'feedback-saved' );

echo "\n=== And the form says so before they try ===\n";

/**
 * How one question is rendered: editable, disabled, or not there at all.
 *
 * @param string $html   The rendered page.
 * @param string $column Airtable column name.
 * @return string
 */
function control_for( $html, $column ) {
	$key = WPCPM_Student_Feedback::key( $column );

	if ( ! preg_match( '/<(select|textarea|input)[^>]*(name="feedback\[' . preg_quote( $key, '/' ) . '\]")([^>]*)>/', $html, $m ) ) {
		return 'missing';
	}

	return false !== strpos( $m[3], 'disabled' ) ? 'disabled' : 'editable';
}

/** Draw the Report Card's feedback section as somebody. */
function render_as( $actor, $manage, array $values ) {
	$GLOBALS['uid']              = $actor;
	$GLOBALS['caps']             = $manage;
	$GLOBALS['sent']             = array();
	$GLOBALS['queue']            = array();
	$GLOBALS['umeta'][ STUDENT ] = array(
		'wpcpm_student_program'     => program_row(),
		'wpcpm_student_institution' => HERE,
		'wpcpm_feedback_record'     => 'recFB0000000000AA',
		// Chosen with the placement above, so the stamp is believed and the save is one PATCH.
		'wpcpm_feedback_record_for' => HERE,
	);

	// Seeded straight into the record cache, so drawing the page makes no request at all.
	$GLOBALS['opts'][ 'T_wpcpm_feedback_' . md5( 'recFB0000000000AA' ) ] = $values;

	ob_start();
	WPCPM_Student_Feedback::render( new WP_User( STUDENT ), program_row() );

	return (string) ob_get_clean();
}

$answered = $two + fill( 'f3', $forms );
$own      = render_as( STUDENT, false, $answered );
$theirs   = render_as( MANAGER, true, $answered );

ck( 'the student may answer both', array( control_for( $own, $list_column ), control_for( $own, $quote_column ) ),
    array( 'editable', 'editable' ) );
ck( 'a manager may not', array( control_for( $theirs, $list_column ), control_for( $theirs, $quote_column ) ),
    array( 'disabled', 'disabled' ) );

// The rest of the form stays theirs to fill in: this is a fence around consent, not a read-only
// page. A manager typing up a paper survey is the reason the capability includes them.
ck( 'while the questions above stay editable for both',
    array( control_for( $own, $proud ), control_for( $theirs, $proud ) ), array( 'editable', 'editable' ) );

// Grayed-out boxes with no explanation read as a bug, and the next thing that happens is somebody
// asking the student to send their password.
ck( 'and the page says why, rather than leaving grey boxes to be puzzled over',
    array(
        false !== strpos( $theirs, 'Only the student can answer these' ),
        false !== strpos( $own, 'Only the student can answer these' ),
    ),
    array( true, false ) );

// The promise this section makes to the student is qualified in the same breath, because the
// permissions box is the one place an answer here reaches the institution.
ck( 'the intro names the exception to "your institution does not see them"',
    false !== strpos( $own, 'The one exception is the permissions box' ), true );

echo "\n=== The quote in the permissions box keeps its backslashes ===\n";

// User meta unslashes what it is handed, as core's does, and the box is read unslashed (WordPress
// hands it over slashed, as posted here): the stamp keeps a backslash only as a slashed copy.
$share = 'F3 - May we share a quote about your experience publicly? If so, please share your thoughts below';
$typed = 'I kept my notes in C:\drafts\wp, two \\\\ in a row, and called it "the patch".';

unset( $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions'] );
ck( 'a save with the quote goes through', post_form( STUDENT, false, array_merge( $answers, array( $share => wp_slash( $typed ) ) ) ), 'feedback-saved' );
ck( 'and the stamp keeps it exactly as typed', $GLOBALS['umeta'][ STUDENT ]['wpcpm_report_permissions']['answers'][ $share ] ?? null, $typed );

echo "\n=== A refused form keeps its answers, and a saved one says so inside itself (1.123.58) ===\n";

require_once __DIR__ . '/../includes/class-wpcpm-form-errors.php';

/**
 * Send a form as the student, Airtable answering the write with `$code`.
 *
 * @param array  $answers Column name => posted value.
 * @param string $form    Which form.
 * @param int    $code    What Airtable answers the write with.
 * @return string Where the handler sent the student.
 */
function feedback_post( array $answers, $form, $code ) {
	$GLOBALS['uid']      = STUDENT;
	$GLOBALS['caps']     = false;
	$GLOBALS['sent']     = array();
	$GLOBALS['redirect'] = '';
	$GLOBALS['queue']    = array( response( $code, 200 === $code ? array( 'records' => array( array( 'id' => 'recFB0000000000AA' ) ) ) : array( 'error' => array( 'type' => 'INVALID_VALUE_FOR_COLUMN', 'message' => 'Refused.' ) ) ) );

	$GLOBALS['umeta'][ STUDENT ] = array(
		'wpcpm_student_program'     => program_row(),
		'wpcpm_student_institution' => HERE,
		'wpcpm_feedback_record'     => 'recFB0000000000AA',
		'wpcpm_feedback_record_for' => HERE,
	);

	// As in post_form(): the answers before the save come from the record cache (1.123.95).
	$GLOBALS['opts'][ 'T_wpcpm_feedback_' . md5( 'recFB0000000000AA' ) ] = array();

	$posted = array();

	// Slashed, as WordPress hands a post over.
	foreach ( $answers as $column => $value ) {
		$posted[ WPCPM_Student_Feedback::key( $column ) ] = wp_slash( $value );
	}

	$_POST = array( 'student' => (string) STUDENT, 'form' => $form, 'feedback' => $posted );

	try {
		WPCPM_Student_Feedback::handle_save();
	} catch ( Left $e ) {
		return $e->getMessage();
	}

	return '';
}

/**
 * The section as the next request draws it, from `$stored` on the record: an Administrator's fresh
 * account holding the student's pending flash, since the flash memoizes per request.
 *
 * @param array $stored Column name => the answer on the record.
 * @return string
 */
function feedback_page( array $stored ) {
	static $next = 900;

	++$next;

	$flash                     = $GLOBALS['umeta'][ STUDENT ]['wpcpm_flash'] ?? null;
	$GLOBALS['umeta'][ $next ] = null === $flash ? array() : array( 'wpcpm_flash' => $flash );
	$page                      = render_as( $next, true, $stored );

	return $page;
}

/** One form's disclosure, opening tag to its close. */
function disclosure( $html, $key ) {
	$at = strpos( $html, 'id="wpcpm-feedback-' . $key . '"' );

	if ( false === $at ) {
		return '';
	}

	$start = strrpos( substr( $html, 0, $at ), '<details' );

	return substr( $html, $start, strpos( $html, '</details>', $at ) + 10 - $start );
}

$fifteen = array();
$n       = 0;

foreach ( $forms['f3']['fields'] as $column => $spec ) {
	$type = isset( $spec['type'] ) ? $spec['type'] : 'textarea';

	if ( 'checkbox' === $type || ( 'select' === $type && isset( $spec['group'] ) && 'permissions' === $spec['group'] ) ) {
		continue;
	}

	++$n;

	if ( 'rating' === $type ) {
		$fifteen[ $column ] = (string) ( 1 + $n % 5 );
	} elseif ( 'select' === $type ) {
		$fifteen[ $column ] = $spec['choices'][ count( $spec['choices'] ) - 1 ];
	} else {
		$fifteen[ $column ] = 'Typed answer ' . $n . ', with a "quote" and C:\path.';
	}
}

$went = feedback_post( $fifteen, 'f3', 422 );
$page = feedback_page( $two );
$f3   = disclosure( $page, 'f3' );

$held = array();

foreach ( $fifteen as $column => $value ) {
	$key  = WPCPM_Student_Feedback::key( $column );
	$type = isset( $forms['f3']['fields'][ $column ]['type'] ) ? $forms['f3']['fields'][ $column ]['type'] : 'textarea';

	if ( 'rating' === $type ) {
		$held[] = 1 === preg_match( '/name="feedback\[' . $key . '\]" value="' . preg_quote( $value, '/' ) . '" checked="checked"/', $f3 );
	} elseif ( 'select' === $type ) {
		$held[] = false !== strpos( substr( $f3, (int) strpos( $f3, 'name="feedback[' . $key . ']"' ) ), '<option value="' . esc_attr( $value ) . '" selected="selected">' );
	} else {
		$held[] = false !== strpos( $f3, 'name="feedback[' . $key . ']"' ) && false !== strpos( $f3, '>' . esc_textarea( $value ) . '</textarea>' );
	}
}

ck( 'a refused feedback form keeps its fifteen answers and comes back open with its status inside',
	array(
		count( $fifteen ),
		array_values( array_unique( $held ) ),
		substr( $went, -strlen( '#wpcpm-feedback-f3' ) ),
		1 === preg_match( '/^<details class="wpcpm-report__disclosure wpcpm-feedback" id="wpcpm-feedback-f3" open>/', $f3 ),
		false !== strpos( $f3, 'Airtable did not take your answers.' ),
	),
	array( 15, array( true ), '#wpcpm-feedback-f3', true, true ) );

ck( 'the Airtable refusal says the answers are still in the form',
	array(
		false !== strpos( $page, 'Airtable did not take your answers. They are still in the form below: send it again in a moment.' ),
		strpos( $page, 'Nothing has been lost' ),
		WPCPM_Student_Feedback::message( 'feedback-refused' )[1],
	),
	array( true, false, 'Airtable did not take your answers. They are still in the form below: send it again in a moment.' ) );
ck( 'and the refusal is an alert in the error tone, carrying data-wpcpm-card-message, so the page moves the focus to it, as the hours box does',
	array(
		false !== strpos( $page, '<p class="wpcpm-calls__message wpcpm-calls__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>Airtable did not take your answers. They are still in the form below: send it again in a moment.</p>' ),
		false !== strpos( $page, 'role="status">Airtable did not take your answers.' ),
	),
	array( true, false ) );

// Every other error-toned outcome is an alert focused on load too (1.123.58, trial T12): here the one
// a student whose program record cannot be matched is given. The flash as the handler leaves it.
$GLOBALS['umeta'][ STUDENT ]['wpcpm_flash'] = array( 'feedback' => 'feedback-failed', 'feedback-form' => 'f3' );
$failed_page = feedback_page( $two );
ck( 'an unmatched record\'s outcome is an alert in the error tone, carrying data-wpcpm-card-message, and no status',
	array(
		false !== strpos( $failed_page, '<p class="wpcpm-calls__message wpcpm-calls__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>Your feedback could not be matched to your program record, so nothing was sent.</p>' ),
		false !== strpos( $failed_page, 'role="status">Your feedback could not be matched' ),
	),
	array( true, false ) );

$overall = 'F3 - Overall experience so far';
$went    = feedback_post( array( $overall => '9', $proud => 'Kept, beside the refused rating.' ), 'f3', 200 );
$page    = feedback_page( $two );
$f3      = disclosure( $page, 'f3' );
$rating  = WPCPM_Student_Feedback::key( $overall );

ck( 'a rating off the scale is named at its field, the summary links it, and the rest of the form is drawn as sent',
	array(
		false !== strpos( $f3, '<h3 class="wpcpm-errors__title">One answer needs a change</h3>' ),
		false !== strpos( $f3, '<a href="#wpcpm-feedback-' . $rating . '">' ),
		1 === preg_match( '/<fieldset class="wpcpm-rating" id="wpcpm-feedback-' . $rating . '" role="radiogroup" data-wpcpm-field="' . $rating . '" aria-invalid="true" aria-describedby="wpcpm-feedback-' . $rating . '-problem">/', $f3 ),
		false !== strpos( $f3, 'Your other answers have been sent. The ones marked below need a change.' ),
		strpos( $f3, 'Thank you - your answers have been sent.' ),
		false !== strpos( $f3, '<p class="wpcpm-field__problem" id="wpcpm-feedback-' . $rating . '-problem">Choose a rating from 1 to 5.</p>' ),
		false !== strpos( $f3, '>Kept, beside the refused rating.</textarea>' ),
	),
	array( true, true, true, true, false, true, true ) );

ck( 'a send with a refused answer is its own outcome, and a clean send keeps its own',
	array( WPCPM_Student_Feedback::message( 'feedback-partly' ), WPCPM_Student_Feedback::message( 'feedback-saved' )[0] ),
	array( array( 'success', 'Your other answers have been sent. The ones marked below need a change.' ), 'success' ) );

$went = feedback_post( fill( 'f2', $forms ), 'f2', 200 );
$page = feedback_page( $two );
$f2   = disclosure( $page, 'f2' );
$head = substr( $page, 0, (int) strpos( $page, 'wpcpm-feedback__title' ) );

ck( 'a saved form\'s disclosure is drawn open with its status inside it, not above the heading',
	array(
		substr( $went, -strlen( '#wpcpm-feedback-f2' ) ),
		1 === preg_match( '/^<details class="wpcpm-report__disclosure wpcpm-feedback" id="wpcpm-feedback-f2" open>/', $f2 ),
		false !== strpos( $f2, 'Thank you - your answers have been sent.' ),
		strpos( $head, 'wpcpm-calls__message' ),
		1 === preg_match( '/<details class="wpcpm-report__disclosure wpcpm-feedback" id="wpcpm-feedback-f1">/', $page ),
	),
	array( '#wpcpm-feedback-f2', true, true, false, true ) );

/*
 * What a student reads in a single-select list (1.123.75). The value is the base's, and stays so;
 * the words beside it come from `choice_labels()`, where each is written out for the translation
 * template. A choice a form offers with no label there would be read in English on a translated
 * form, so every one is held to the list.
 */
$labels  = WPCPM_Student_Feedback::choice_labels();
$offered = array();

foreach ( WPCPM_Student_Feedback::forms() as $form ) {
	foreach ( $form['fields'] as $spec ) {
		if ( isset( $spec['choices'] ) ) {
			$offered = array_merge( $offered, $spec['choices'] );
		}
	}
}

$offered = array_values( array_unique( $offered ) );

ck( 'every choice a form offers has words for the reader', array_values( array_diff( $offered, array_keys( $labels ) ) ), array() );
ck( 'and the list holds no choice no form offers', array_values( array_diff( array_keys( $labels ), $offered ) ), array() );
ck( 'in English a choice reads as the base spells it', WPCPM_Student_Feedback::choice_label( 'Yes, by my blog address only' ), 'Yes, by my blog address only' );
ck( 'a choice with no words is read as it is stored', WPCPM_Student_Feedback::choice_label( 'A choice added later' ), 'A choice added later' );

echo "\n=== A Form 3 finished on the site sets Pending graduation (1.123.95) ===\n";

/*
 * A track ends with Form 3. Sent through the base's own form, its automation sets the student's Students row to
 * Pending graduation, and the Administrator Dashboard's card "Students waiting to graduate" lists them. Sent here, the
 * site does the same: the student's own Students row, found by the record ID the roster index holds for the account,
 * read again first, written only from a track. These drive the real handler and the real client over canned
 * responses, and read what left the site.
 */
define( 'STUDENTS_ROW', 'recSTUDENTROW0001' );
define( 'OLD_ROW', 'recSTUDENTROW0002' );

$GLOBALS['opts']['wpcpm_settings']['students_table'] = 'tblStudents';

$f3_all  = fill( 'f3', $forms );
$f3_last = 'F3 - One main change that would improve the program';

/**
 * Save Form 3 as somebody, over a world set up for it, and say what happened.
 *
 * @param int    $actor   Who is logged in.
 * @param bool   $manage  Whether they hold the manage capability.
 * @param array  $prior   Column name => the answers already on the Feedback row, in the record cache.
 * @param array  $answers Column name => posted value.
 * @param array  $queue   The responses after the Feedback write's own, in order.
 * @param array  $world   `status` (the program row's), `rows` (Students record ID => roster status), `pending` (the
 *                        account's waiting stamp, or none), `institution`.
 * @return array `flash`, `sent` (method and URL each), `bodies` (each request's decoded body), `meta` (the account's).
 */
function finish_f3( $actor, $manage, array $prior, array $answers, array $queue, array $world = array() ) {
	$world = array_merge(
		array(
			'status'      => 'In Sensei',
			'rows'        => array( STUDENTS_ROW => 'In Sensei' ),
			'institution' => HERE,
		),
		$world
	);

	$GLOBALS['uid']          = $actor;
	$GLOBALS['caps']         = $manage;
	$GLOBALS['sent']         = array();
	$GLOBALS['stamped_then'] = array();
	$GLOBALS['redirect']     = '';
	$GLOBALS['logged']       = array();
	$GLOBALS['mails']        = array();
	$GLOBALS['queue']        = array_merge( isset( $world['before'] ) ? $world['before'] : array(), array( response( 200, array( 'records' => array( array( 'id' => 'recFB0000000000AA' ) ) ) ) ), $queue );

	$GLOBALS['umeta'][ STUDENT ] = array(
		'wpcpm_student_program'     => array_merge( program_row(), array( 'program' => $world['status'], 'record_id' => isset( $world['report'] ) ? $world['report'] : 'recREPORTROW00001', 'email' => isset( $world['email'] ) ? $world['email'] : 'ana@institution-3.example' ) ),
		'wpcpm_student_institution' => $world['institution'],
		'wpcpm_feedback_record'     => 'recFB0000000000AA',
		'wpcpm_feedback_record_for' => $world['institution'],
	);
	unset( $GLOBALS['umeta'][ MANAGER ] );

	if ( isset( $world['pending'] ) ) {
		$GLOBALS['umeta'][ STUDENT ]['wpcpm_student_pending_since'] = $world['pending'];
	}

	if ( isset( $world['set'] ) ) {
		$GLOBALS['umeta'][ STUDENT ]['wpcpm_student_pending_set'] = $world['set'];
	}

	$rows = array();

	foreach ( $world['rows'] as $record => $status ) {
		$rows[ $record ] = array( 'record_id' => $record, 'name' => 'Ana Nowak', 'status' => $status, 'institution' => $world['institution'], 'user_id' => STUDENT, 'reports' => array(), 'touched' => 0 );
	}

	// Somebody else's rows on the same roster, joined to another account.
	foreach ( isset( $world['others'] ) ? $world['others'] : array() as $record => $status ) {
		$rows[ $record ] = array( 'record_id' => $record, 'name' => 'Ola Other', 'status' => $status, 'institution' => $world['institution'], 'user_id' => 8, 'reports' => array(), 'touched' => 0 );
	}

	$GLOBALS['opts']['wpcpm_roster_counts']          = array( 'v' => 4, 'read' => 1700000000, 'institutions' => array( HERE => array() ), 'reconciliation' => array() );
	$GLOBALS['opts'][ 'wpcpm_roster_' . HERE ]       = array( 'v' => 4, 'read' => 1700000000, 'rows' => $rows );
	$GLOBALS['opts']['wpcpm_roster_unlinked']        = array( 'v' => 4, 'read' => 1700000000, 'rows' => array() );
	$GLOBALS['opts'][ 'T_wpcpm_feedback_' . md5( 'recFB0000000000AA' ) ] = $prior;

	// The answers before the save not in the record cache: the save reads them, which is the first request.
	if ( ! empty( $world['uncached'] ) ) {
		unset( $GLOBALS['opts'][ 'T_wpcpm_feedback_' . md5( 'recFB0000000000AA' ) ] );
	}

	$posted = array();

	foreach ( $answers as $column => $value ) {
		$posted[ WPCPM_Student_Feedback::key( $column ) ] = $value;
	}

	$_POST = array( 'student' => (string) STUDENT, 'form' => 'f3', 'feedback' => $posted );
	$crash = '';

	try {
		WPCPM_Student_Feedback::handle_save();
	} catch ( Left $e ) {
		// Where the handler meant to end.
	} catch ( Throwable $e ) {
		// A request nobody queued, or a fault that got out: named by the checks, never a crash of the suite.
		$crash = $e->getMessage();
	}

	$pending = isset( $GLOBALS['umeta'][ $actor ]['wpcpm_flash'] ) ? $GLOBALS['umeta'][ $actor ]['wpcpm_flash'] : array();

	return array(
		'crash'  => $crash,
		'flash'  => isset( $pending['feedback'] ) ? (string) $pending['feedback'] : '',
		'sent'   => array_map( static function ( $s ) { return $s['args']['method'] . ' ' . $s['url']; }, $GLOBALS['sent'] ),
		'bodies' => array_map( static function ( $s ) { return isset( $s['args']['body'] ) ? json_decode( $s['args']['body'], true ) : null; }, $GLOBALS['sent'] ),
		'meta'   => $GLOBALS['umeta'][ STUDENT ],
		'left'   => count( $GLOBALS['queue'] ),
		'waits'  => array_map( static function ( $s ) { return isset( $s['args']['timeout'] ) ? $s['args']['timeout'] : null; }, $GLOBALS['sent'] ),
	);
}

/** The live read of a Students row, saying `$status`. */
function students_read( $record, $status ) {
	return response( 200, array( 'id' => $record, 'fields' => array( 'Status' => $status, 'Email' => 'ana@institution-3.example' ) ) );
}

/** Airtable's answer to the status write. */
function students_written( $record ) {
	return response( 200, array( 'records' => array( array( 'id' => $record, 'fields' => array( 'Status' => 'Pending graduation' ) ) ) ) );
}

$students_url = 'https://api.airtable.com/v0/appTest/tblStudents/';
$write_body   = array( 'records' => array( array( 'id' => STUDENTS_ROW, 'fields' => array( 'Status' => 'Pending graduation' ) ) ) );
$roster_of    = static function () { return $GLOBALS['opts'][ 'wpcpm_roster_' . HERE ]['rows']; };

$before = time();
$one    = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) ) );

ck( 'complete_on_this_save: a save that finishes Form 3 reads the student\'s Students row by its record ID, then writes Pending graduation to it alone', array(
	$one['flash'],
	array_slice( $one['sent'], 1 ),
	$one['bodies'][2] ?? null,
	$one['left'],
), array(
	'feedback-saved',
	array( 'GET ' . $students_url . STUDENTS_ROW, 'PATCH ' . 'https://api.airtable.com/v0/appTest/tblStudents' ),
	$write_body,
	0,
) );
ck( 'the answers are written first, as before: the Feedback row\'s PATCH is the first request', 0 === strpos( $one['sent'][0], 'PATCH https://api.airtable.com/v0/appTest/tblFeedback' ), true );
ck( 'record_by_id: no request looks the student up by an address', count( array_filter( $one['sent'], static function ( $s ) { return false !== stripos( $s, 'filterByFormula' ); } ) ), 0 );

$set = $one['meta']['wpcpm_student_pending_set'] ?? array();
ck( 'the account records when the site set the status, who saved the form, as whom, and on which rows', array(
	isset( $set['at'] ) && $set['at'] >= $before,
	$set['by'] ?? null,
	$set['as'] ?? null,
	$set['records'] ?? null,
	isset( $one['meta']['wpcpm_student_pending_owed'] ),
), array( true, STUDENT, 'student', array( STUDENTS_ROW ), false ) );
ck( 'card_at_once: the roster row the card reads says Pending graduation now, stamped as a write made here', array(
	$roster_of()[ STUDENTS_ROW ]['status'],
	$roster_of()[ STUDENTS_ROW ]['touched'] >= $before,
), array( 'Pending graduation', true ) );
ck( 'and the account is stamped waiting since now, as the students sync stamps a first sight', array(
	( $one['meta']['wpcpm_student_pending_since']['at'] ?? 0 ) >= $before,
	$one['meta']['wpcpm_student_pending_since']['before'] ?? null,
), array( true, false ) );
ck( 'course_kept: the account keeps the track the student finished, for the card\'s Track column', array(
	$one['meta']['wpcpm_student_course'] ?? null,
	WPCPM_Program::course_status( 'Pending graduation', STUDENT ),
), array( 'In Sensei', 'In Sensei' ) );
ck( 'the account\'s own program row is left as the base\'s reports row last said: the sync carries the status over', $one['meta']['wpcpm_student_program']['program'], 'In Sensei' );
ck( 'no_mail: nothing is mailed', $GLOBALS['mails'], array() );
ck( 'R6.1 own_write_form: the save path\'s own write keeps today\'s audit message', $GLOBALS['logged'][0]['message'] ?? null, 'Status: In Sensei to Pending graduation.' );
ck( 'the write is on the audit trail, as the program\'s own row: who, which row, from which status', array(
	count( $GLOBALS['logged'] ),
	$GLOBALS['logged'][0]['kind'] ?? null,
	$GLOBALS['logged'][0]['subject'] ?? null,
	$GLOBALS['logged'][0]['actor'] ?? null,
	$GLOBALS['logged'][0]['ground'] ?? null,
	$GLOBALS['logged'][0]['evidence'] ?? null,
	$GLOBALS['logged'][0]['data'] ?? null,
), array( 1, 'student_pending', STUDENTS_ROW, STUDENT, 'system', 'live', array( 'field' => 'Status', 'from' => 'In Sensei', 'to' => 'Pending graduation', 'students_record' => STUDENTS_ROW, 'as' => 'student' ) ) );

// The real audit class offers what the stand-in above stands in for.
$audit_file = __DIR__ . '/../includes/modules/class-wpcpm-institution-audit.php';
$audit_text = (string) file_get_contents( $audit_file );
ck( 'the real audit trail offers record_program() and the ground and evidence named', array(
	(bool) preg_match( '/public static function record_program\(/', $audit_text ),
	false !== strpos( $audit_text, "const GROUND_SYSTEM  = 'system';" ),
	false !== strpos( $audit_text, "const EVIDENCE_LIVE  = 'live';" ),
), array( true, true, true ) );

$partial = $f3_all;
unset( $partial[ $f3_last ] );

$sitting = finish_f3( STUDENT, false, $partial, array( $f3_last => 'Shorter calls.' ), array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) ) );
ck( 'second_sitting: a form sent in two sittings counts when the last required answer arrives', array( array_slice( $sitting['sent'], 1 ), $sitting['bodies'][2] ?? null ), array( array( 'GET ' . $students_url . STUDENTS_ROW, 'PATCH https://api.airtable.com/v0/appTest/tblStudents' ), $write_body ) );

$unfinished = finish_f3( STUDENT, false, array(), $partial, array() );
ck( 'not_complete: a save that leaves Form 3 unfinished writes nothing more and stamps nothing', array(
	$unfinished['flash'],
	count( $unfinished['sent'] ),
	isset( $unfinished['meta']['wpcpm_student_pending_set'] ),
	isset( $unfinished['meta']['wpcpm_student_pending_owed'] ),
	$roster_of()[ STUDENTS_ROW ]['status'],
), array( 'feedback-saved', 1, false, false, 'In Sensei' ) );

$again = finish_f3( STUDENT, false, $f3_all, array( $f3_last => 'A second thought.' ), array() );
ck( 'already_complete: a save that changes an already finished Form 3 does nothing more', array( count( $again['sent'] ), isset( $again['meta']['wpcpm_student_pending_set'] ) ), array( 1, false ) );

$earlier = array();

foreach ( array( 'f1', 'f2' ) as $earlier_form ) {
	post_form( STUDENT, false, fill( $earlier_form, $forms ), true, $earlier_form );
	$earlier[ $earlier_form ] = array( count( $GLOBALS['sent'] ), isset( $GLOBALS['umeta'][ STUDENT ]['wpcpm_student_pending_set'] ) );
}

ck( 'and finishing Form 1 or Form 2 never does', $earlier, array( 'f1' => array( 1, false ), 'f2' => array( 1, false ) ) );

foreach ( array( 'Graduate', 'Dropped out', 'Pending graduation', 'Paused', 'Not a status the site knows' ) as $not_track ) {
	$off = finish_f3( STUDENT, false, array(), $f3_all, array(), array( 'status' => $not_track ) );
	ck( 'not_track: a student whose status on the site is "' . $not_track . '" gets no read and no write', array( count( $off['sent'] ), isset( $off['meta']['wpcpm_student_pending_set'] ), isset( $off['meta']['wpcpm_student_pending_owed'] ), $off['flash'] ), array( 1, false, false, 'feedback-saved' ) );
}

$stale = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'Graduate' ) ) );
ck( 'stale_local: a track on the site but Graduate in the base, read live before the write, is not written over', array(
	array_slice( $stale['sent'], 1 ),
	isset( $stale['meta']['wpcpm_student_pending_set'] ),
	isset( $stale['meta']['wpcpm_student_pending_owed'] ),
	$roster_of()[ STUDENTS_ROW ]['status'],
	$stale['flash'],
), array( array( 'GET ' . $students_url . STUDENTS_ROW ), false, false, 'Graduate', 'feedback-saved' ) );

$already = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'Pending graduation' ) ) );
ck( 'and one the base already holds at Pending graduation is not written again; the card reads it so at once', array( count( $already['sent'] ), $roster_of()[ STUDENTS_ROW ]['status'], isset( $already['meta']['wpcpm_student_pending_owed'] ) ), array( 2, 'Pending graduation', false ) );

$two_rows = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), students_read( OLD_ROW, 'Graduate' ), students_written( STUDENTS_ROW ) ), array( 'rows' => array( STUDENTS_ROW => 'In Sensei', OLD_ROW => 'Graduate' ) ) );
ck( 'a student with two Students rows has only the one on a track written', array( array_slice( $two_rows['sent'], 1 ), $two_rows['bodies'][3] ?? null ), array( array( 'GET ' . $students_url . STUDENTS_ROW, 'GET ' . $students_url . OLD_ROW, 'PATCH https://api.airtable.com/v0/appTest/tblStudents' ), $write_body ) );

$no_row = finish_f3( STUDENT, false, array(), $f3_all, array(), array( 'rows' => array() ) );
ck( 'no_record: a student the site holds no Students row for gets no read and no write, and is stamped as owed', array(
	count( $no_row['sent'] ),
	$no_row['flash'],
	isset( $no_row['meta']['wpcpm_student_pending_set'] ),
	( $no_row['meta']['wpcpm_student_pending_owed']['at'] ?? 0 ) >= $before,
	$no_row['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
), array( 1, 'feedback-saved', false, true, 'The site holds no Students row for this student.' ) );

$GLOBALS['umeta'][ MANAGER ] = array();
$typed = finish_f3( MANAGER, true, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) ) );
ck( 'by_administrator: an Administrator typing up a paper Form 3 has the same effect, recorded as theirs', array(
	$typed['flash'],
	$typed['bodies'][2] ?? null,
	$typed['meta']['wpcpm_student_pending_set']['by'] ?? null,
	$typed['meta']['wpcpm_student_pending_set']['as'] ?? null,
	$GLOBALS['logged'][0]['actor'] ?? null,
), array( 'feedback-saved', $write_body, MANAGER, 'administrator', MANAGER ) );

$refused = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), response( 422, array( 'error' => array( 'type' => 'INVALID_MULTIPLE_CHOICE_OPTIONS', 'message' => 'Insufficient permissions to create new select option.' ) ) ) ) );
$owed    = $refused['meta']['wpcpm_student_pending_owed'] ?? array();
ck( 'write_refused: the base refusing the status keeps the answers saved and the student\'s message as it was', array( $refused['flash'], 0 === strpos( $refused['sent'][0], 'PATCH https://api.airtable.com/v0/appTest/tblFeedback' ) ), array( 'feedback-saved', true ) );
ck( 'and stamps the account with what is owed: when, by whom, and the base\'s reason', array(
	( $owed['at'] ?? 0 ) >= $before,
	$owed['by'] ?? null,
	$owed['as'] ?? null,
	'' !== (string) ( $owed['reason'] ?? '' ),
	isset( $refused['meta']['wpcpm_student_pending_set'] ),
	isset( $refused['meta']['wpcpm_student_pending_since'] ),
	$roster_of()[ STUDENTS_ROW ]['status'],
	$GLOBALS['logged'],
), array( true, STUDENT, 'student', true, false, false, 'In Sensei', array() ) );

$errored = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ) );
ck( 'write_errored: a write the network lost is owed the same way, its reason kept', array( $errored['flash'], $errored['meta']['wpcpm_student_pending_owed']['reason'] ?? null, isset( $errored['meta']['wpcpm_student_pending_set'] ) ), array( 'feedback-saved', 'cURL error 28: Operation timed out', false ) );

$unread = finish_f3( STUDENT, false, array(), $f3_all, array( response( 404, array( 'error' => 'NOT_FOUND' ) ) ) );
ck( 'and so is one whose Students row could not be read (a row deleted in the base): nothing is written blind', array( count( $unread['sent'] ), $unread['flash'], isset( $unread['meta']['wpcpm_student_pending_owed'] ), isset( $unread['meta']['wpcpm_student_pending_set'] ) ), array( 2, 'feedback-saved', true, false ) );

$GLOBALS['opts']['wpcpm_roster_unlinked'] = array( 'v' => 4, 'read' => 1700000000, 'rows' => array() );
$unlinked_world = array( 'rows' => array(), 'institution' => '' );
$GLOBALS['uid'] = STUDENT;
finish_f3( STUDENT, false, array(), $f3_all, array(), $unlinked_world );
$GLOBALS['opts']['wpcpm_roster_unlinked']['rows'][ STUDENTS_ROW ] = array( 'record_id' => STUDENTS_ROW, 'name' => 'Ana Nowak', 'status' => 'In Sensei', 'institution' => '', 'user_id' => STUDENT, 'reports' => array(), 'touched' => 0 );
$GLOBALS['umeta'][ STUDENT ]['wpcpm_feedback_record_for'] = '';
$GLOBALS['umeta'][ STUDENT ]['wpcpm_student_institution'] = '';
unset( $GLOBALS['umeta'][ STUDENT ]['wpcpm_student_pending_owed'] );
$GLOBALS['sent']  = array();
$GLOBALS['queue'] = array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) );
$unlinked_out     = WPCPM_Student_Graduation::form_finished( STUDENT, STUDENT );
ck( 'a student whose Students row names no institution is found among the unlinked rows, written, and that row says so at once', array(
	$unlinked_out,
	$GLOBALS['opts']['wpcpm_roster_unlinked']['rows'][ STUDENTS_ROW ]['status'] ?? null,
), array( 'set', 'Pending graduation' ) );


echo "\n=== Fix round 1: the status never costs the student anything, and is set once (1.123.95) ===\n";

$f3_consent = array_merge( $f3_all, array( $list_column => 'Yes, with my name' ) );
$consent    = finish_f3( STUDENT, false, array(), $f3_consent, array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) ) );
ck( 'I1 consent_first: the student\'s consent stamp is written before the status is read or written', array(
	count( $consent['sent'] ),
	array_slice( $GLOBALS['stamped_then'], 1 ),
	$consent['flash'],
), array( 3, array( true, true ), 'feedback-saved' ) );

$fault = finish_f3( STUDENT, false, array(), $f3_consent, array( new RuntimeException( 'A fault inside the status write.' ) ) );
ck( 'I1 fault_owed: a fault inside the status write is a debt with its words; the student gets the normal message and keeps their consent stamp', array(
	$fault['crash'],
	$fault['flash'],
	$fault['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
	isset( $fault['meta']['wpcpm_report_permissions'] ),
), array( '', 'feedback-saved', 'A fault inside the status write.', true ) );

$time_reason = 'The program\'s records were slow to answer, so the students sync sets the status instead.';
$has_clock   = property_exists( 'WPCPM_Student_Graduation', 'clock' );
if ( $has_clock ) {
	WPCPM_Student_Graduation::$clock = static function () { return (float) $GLOBALS['clock_now']; };
}
$GLOBALS['clock_now']  = 1000.0;
$GLOBALS['clock_step'] = 5;
$slow = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), students_read( OLD_ROW, 'In Sensei' ) ), array( 'rows' => array( STUDENTS_ROW => 'In Sensei', OLD_ROW => 'In Sensei' ) ) );
ck( 'I1 time_bound: each status request is given five seconds at most, and with no room left the write is not sent but owed to the sync', array(
	$has_clock,
	array_slice( $slow['sent'], 1 ),
	array_slice( $slow['waits'], 1 ),
	$slow['flash'],
	$slow['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
), array( true, array( 'GET ' . $students_url . STUDENTS_ROW, 'GET ' . $students_url . OLD_ROW ), array( 5.0, 5.0 ), 'feedback-saved', $time_reason ) );

ck( 'and the answers\' own write keeps the client\'s usual twenty seconds: no other caller\'s timeout changes', $slow['waits'][0] ?? null, 20 );

$GLOBALS['clock_now'] = 1000.0;
$cold = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ) ), array( 'uncached' => true, 'before' => array( response( 200, array( 'id' => 'recFB0000000000AA', 'fields' => array() ) ) ) ) );
ck( 'and the answers\' read before the save counts against the same ten seconds: five for it, five for the row, nothing more', array(
	$cold['sent'][0] ?? null,
	$cold['waits'][0] ?? null,
	array_slice( $cold['sent'], 2 ),
	array_slice( $cold['waits'], 2 ),
	$cold['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
), array( 'GET https://api.airtable.com/v0/appTest/tblFeedback/recFB0000000000AA', 5.0, array( 'GET ' . $students_url . STUDENTS_ROW ), array( 5.0 ), $time_reason ) );
$GLOBALS['clock_step'] = 0;
if ( $has_clock ) {
	WPCPM_Student_Graduation::$clock = null;
}

$GLOBALS['opts']['wpcpm_settings']['students_table'] = '';
$no_table = finish_f3( STUDENT, false, array(), $f3_all, array() );
$GLOBALS['opts']['wpcpm_settings']['students_table'] = 'tblStudents';
ck( 'M1 settings_first: with no Students table set, nothing is read: the debt names the settings', array(
	count( $no_table['sent'] ),
	$no_table['crash'],
	$no_table['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
), array( 1, '', 'The settings name no Students table or status column.' ) );

$other_mail = finish_f3( STUDENT, false, array(), $f3_all, array( response( 200, array( 'id' => STUDENTS_ROW, 'fields' => array( 'Status' => 'In Sensei', 'Email' => 'someone.else@institution-3.example' ) ) ) ) );
ck( 'M3 address_check: a row whose address read now is not the student\'s reports-row address is not written; the debt says so', array(
	array_slice( $other_mail['sent'], 1 ),
	$other_mail['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
	isset( $other_mail['meta']['wpcpm_student_pending_set'] ),
), array( array( 'GET ' . $students_url . STUDENTS_ROW ), 'The Students row on a track holds an email address other than this student\'s, so it was not written.', false ) );

$set_before = array( 'at' => 1790000000, 'finished' => 1790000000, 'by' => STUDENT, 'as' => 'student', 'records' => array( STUDENTS_ROW ), 'via' => 'form' );
$once       = finish_f3( STUDENT, false, array(), $f3_all, array(), array( 'set' => $set_before ) );
ck( 'M5 set_once: once the site has set the status, a later save of Form 3 never runs the write again, whatever the earlier answers read', array(
	count( $once['sent'] ),
	$once['crash'],
	$once['meta']['wpcpm_student_pending_set'],
	isset( $once['meta']['wpcpm_student_pending_owed'] ),
), array( 1, '', $set_before, false ) );

$others = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) ), array( 'others' => array( 'recOTHERSROW00001' => 'In Sensei' ) ) );
ck( 'I4 others_row: another account\'s row on a track in the same roster is neither read nor written', array(
	array_slice( $others['sent'], 1 ),
	$others['bodies'][2] ?? null,
	$roster_of()['recOTHERSROW00001']['status'],
), array( array( 'GET ' . $students_url . STUDENTS_ROW, 'PATCH https://api.airtable.com/v0/appTest/tblStudents' ), $write_body, 'In Sensei' ) );

$t_save = time();
$dated  = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) ) );
ck( 'I6 finished_day: the save path records the day the form was finished, the save\'s own time, and "Waiting since" is that day', array(
	( $dated['meta']['wpcpm_student_pending_set']['finished'] ?? 0 ) >= $t_save,
	( $dated['meta']['wpcpm_student_pending_set']['finished'] ?? -1 ) === ( $dated['meta']['wpcpm_student_pending_since']['at'] ?? -2 ),
), array( true, true ) );

echo "\n=== Fix round 2: once per program, and an empty address is its own case (1.123.95) ===\n";

$set_of = static function ( $report, $track ) { return array( 'at' => 1790000000, 'finished' => 1790000000, 'by' => STUDENT, 'as' => 'student', 'records' => array( STUDENTS_ROW ), 'via' => 'form', 'report' => $report, 'track' => $track ); };
$full   = array( students_read( STUDENTS_ROW, 'In Sensei' ), students_written( STUDENTS_ROW ) );

$same = finish_f3( STUDENT, false, array(), $f3_all, array(), array( 'set' => $set_of( 'recREPORTROW00001', 'In Sensei' ) ) );
ck( 'N2 same_program: set once for this reports record and this track, a later save never sets it again', array( count( $same['sent'] ), $same['crash'] ), array( 1, '' ) );

$new_program = finish_f3( STUDENT, false, array(), $f3_all, $full, array( 'set' => $set_of( 'recREPORTROW00009', 'In Sensei' ) ) );
ck( 'N2 new_program: a student on a new program (a new reports record) who finishes Form 3 again is set again, and the set meta says what for', array(
	array_slice( $new_program['sent'], 1 ),
	$new_program['meta']['wpcpm_student_pending_set']['report'] ?? null,
	$new_program['meta']['wpcpm_student_pending_set']['track'] ?? null,
), array( array( 'GET ' . $students_url . STUDENTS_ROW, 'PATCH https://api.airtable.com/v0/appTest/tblStudents' ), 'recREPORTROW00001', 'In Sensei' ) );

$new_track = finish_f3( STUDENT, false, array(), $f3_all, $full, array( 'set' => $set_of( 'recREPORTROW00001', 'Developer Track' ) ) );
ck( 'N2 new_track: the same reports record on another track is set again', array_slice( $new_track['sent'], 1 ), array( 'GET ' . $students_url . STUDENTS_ROW, 'PATCH https://api.airtable.com/v0/appTest/tblStudents' ) );

$old_shape = finish_f3( STUDENT, false, array(), $f3_all, array(), array( 'set' => array( 'at' => 1790000000, 'by' => STUDENT, 'as' => 'student', 'records' => array( STUDENTS_ROW ), 'via' => 'form' ) ) );
ck( 'N2 old_shape: a set meta of round 1\'s shape, naming no program, blocks as it did', array( count( $old_shape['sent'] ), $old_shape['crash'] ), array( 1, '' ) );

$no_mail = finish_f3( STUDENT, false, array(), $f3_all, array( students_read( STUDENTS_ROW, 'In Sensei' ) ), array( 'email' => '' ) );
ck( 'N3 empty_address: a student with no address on their program row is not written, with its own reason and line', array(
	array_slice( $no_mail['sent'], 1 ),
	$no_mail['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
	$no_mail['meta']['wpcpm_student_pending_owed']['kind'] ?? null,
), array( array( 'GET ' . $students_url . STUDENTS_ROW ), 'The site holds no email address for this student, so their Students row cannot be matched to them and was not written.', 'no-row' ) );
ck( 'N4 debt_kinds: the debt says which line the card draws: another address, no row, or one the sync pays itself', array(
	$other_mail['meta']['wpcpm_student_pending_owed']['kind'] ?? null,
	$no_row['meta']['wpcpm_student_pending_owed']['kind'] ?? null,
	$refused['meta']['wpcpm_student_pending_owed']['kind'] ?? null,
	$fault['meta']['wpcpm_student_pending_owed']['kind'] ?? null,
	$slow['meta']['wpcpm_student_pending_owed']['kind'] ?? null,
), array( 'other-email', 'no-row', 'retry', 'retry', 'retry' ) );

echo "\n=== Round 4: what a debt is for, and nothing after a write makes one (1.123.95) ===\n";

ck( 'R4.1 debt_for: a debt records the reports record and the track it was made for', array(
	$refused['meta']['wpcpm_student_pending_owed']['report'] ?? null,
	$refused['meta']['wpcpm_student_pending_owed']['track'] ?? null,
), array( 'recREPORTROW00001', 'In Sensei' ) );

$GLOBALS['audit_throw'] = true;
$audited = finish_f3( STUDENT, false, array(), $f3_all, $full );
unset( $GLOBALS['audit_throw'] );
ck( 'R4.3 audit_after_write: a fault on the audit trail after the write leaves the set meta and no debt; the student is told the answers were saved', array(
	isset( $audited['meta']['wpcpm_student_pending_set'] ),
	isset( $audited['meta']['wpcpm_student_pending_owed'] ),
	$audited['flash'],
	$audited['crash'],
), array( true, false, 'feedback-saved', '' ) );

$GLOBALS['meta_false']['wpcpm_student_pending_set'] = true;
$unmarked = finish_f3( STUDENT, false, array(), $f3_all, $full );
unset( $GLOBALS['meta_false'] );
ck( 'R4.5 marker_checked: when the set meta is not kept, the debt stays, saying the records were set and the site could not record it', array(
	isset( $unmarked['meta']['wpcpm_student_pending_set'] ),
	$unmarked['meta']['wpcpm_student_pending_owed']['reason'] ?? null,
	$unmarked['flash'],
), array( false, 'The status was set in the program\'s records, but the site could not record that it set it: the next students sync looks again.', 'feedback-saved' ) );

echo "\n=== The save's own checks are as they were ===\n";

$GLOBALS['referer'] = array();
finish_f3( STUDENT, false, array(), $partial, array() );
ck( 'the nonce is checked for this student and this form, before anything else', $GLOBALS['referer'], array( 'wpcpm_save_feedback_' . STUDENT . '_f3' ) );

$GLOBALS['uid']  = 99;
$GLOBALS['caps'] = false;
$GLOBALS['sent'] = array();
$_POST           = array( 'student' => (string) STUDENT, 'form' => 'f3', 'feedback' => array() );
$denied          = '';

try {
	WPCPM_Student_Feedback::handle_save();
} catch ( Denied $e ) {
	$denied = $e->getMessage();
} catch ( Left $e ) {
	$denied = 'left';
}

ck( 'somebody who may not fill in the form is refused before anything is read or written', array( $denied, count( $GLOBALS['sent'] ) ), array( 'You cannot fill in that feedback form.', 0 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
