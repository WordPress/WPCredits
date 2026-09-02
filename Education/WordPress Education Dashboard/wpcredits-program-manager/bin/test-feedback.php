<?php
/**
 * The feedback surveys: the columns they write to, and what may reach Airtable.
 *
 * **The column names are pinned here against the base's own schema**, read from the Airtable
 * metadata endpoint on 6 August 2026. They are long, inconsistently spaced — Form 1's were created
 * without the space after `F1` — and one of them is 108 characters. A name that does not match its
 * column is not a visible failure: Airtable refuses the whole record, so one typo takes the other
 * eight answers down with it, and the student is told their feedback could not be sent with nothing
 * to say which question was to blame.
 *
 * The single-select choices are pinned for the same reason. `clean()` will not send a value that is
 * not on the list, so a list that has drifted from the base silently drops answers rather than
 * writing bad ones — which is safer, and completely invisible.
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
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
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
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_users( $a = array() ) { return array(); }
function get_user_by( $f, $v ) { return new WP_User( (int) $v ); }
function get_current_user_id() { return $GLOBALS['uid'] ?? 0; }
function is_user_logged_in() { return ! empty( $GLOBALS['uid'] ); }
function current_user_can( $c ) { return ! empty( $GLOBALS['caps'] ); }
function user_can( $u, $c ) { return ! empty( $GLOBALS['caps'] ); }
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

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-settings.php';
require_once __DIR__ . '/../includes/class-wpcpm-flash.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-icons.php';
require_once __DIR__ . '/../includes/class-wpcpm-contribution-teams.php';
require_once __DIR__ . '/../includes/class-wpcpm-wporg-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-dashboard.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-feedback.php';

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
		$method = new ReflectionMethod( 'WPCPM_Student_Feedback', 'clean' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
	}

	return $method->invoke( null, $raw, $spec );
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
ck( 'Form 3 asks sixteen, permissions included', count( $forms['f3']['fields'] ), 16 );
ck( 'Form 4 asks four', count( $forms['f4']['fields'] ), 4 );

echo "\n=== The retired questions stay retired ===\n";

// Each of these was dropped by the analysis for a stated reason — duplication, or the highest rate
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
ck( 'the exit survey has none — it asks a different thing entirely',
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
			// A rule pointing at a question in another form — or at a column that was renamed —
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
 * stage — three repeated questions only mean something if the answers are months apart — and a
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

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
