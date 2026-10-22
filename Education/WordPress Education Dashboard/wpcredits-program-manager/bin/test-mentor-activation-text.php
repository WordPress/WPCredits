<?php
/**
 * The emails an activation brings, word for word: what the invitation a new mentor is sent says in
 * 1.122.33, with the welcome after it, and what Send the acknowledgement sends, as `wp_mail()` is
 * handed them.
 *
 * 1.122.33 makes a selected applicant a mentor on the site (design of 16 October 2026, section 10).
 * The mentor's invitation carries the welcome, which the Airtable automation used to send, and the
 * program's Reply-To, since the welcome asks for a reply; Send the acknowledgement sends the one
 * acknowledgement a row never had. M1's recording of the five application emails stays as it is and
 * still passes (bin/test-mentor-applications-text.php); this suite is the recording of the new ones.
 * Through the real senders and the real mail layer, it recorded every email of every case below into
 * bin/fixtures/mentor-activation-1-122-33.json (4 cases, 16 emails): who it went to, the id it was
 * logged under, its subject, its body with the signature and footer, and its headers. From then on it
 * sends the same cases and compares them with the recording, byte for byte.
 *
 * The real senders: the form's submission (`WPCPM_Mentor_Application_Form::handle_submit()`), the
 * confirming link it sends, followed as an applicant follows it (`handle_verify()`), the queue's
 * Select, The course is completed and Send the acknowledgement (`WPCPM_Mentor_Application_Queue`),
 * the Mentor Status Checker's daily run (`WPCPM_Mentor_Checker_Runner::run_all()`, under
 * `wp_doing_cron()`), the activation they both reach (`WPCPM_Mentor_Activation::activate()`, and the
 * account it makes through `WPCPM_Mentor_Provision`), and the invitation queue's drain
 * (`WPCPM_Mail::drain_queue()` through `send_invite_now()`, under the real `WPCPM_Lock` over
 * bin/stubs/options-lock-wpdb.php). The invitation is built by `WPCPM_Mail::welcome_email()` on the
 * filter core's `wp_new_user_notification()` runs, which is stood in as core 7.1 builds its message:
 * the keyed link alone, with the fixed fake key `TEST-KEY-NOT-A-REAL-LINK`, and `headers` ''. The
 * real mail layer: `WPCPM_Mail`, the template registry and the signature and footer. Only WordPress,
 * the settings, the Airtable client (the contract of bin/test-mentor-airtable-row.php), the profile
 * reader, the Slack message and the two dashboards' addresses are stood in.
 *
 * The cases: an applicant selected whose course the daily check finds on the profile; one selected
 * whose course an Administrator presses The course is completed for, with a note; a returning mentor,
 * whose account is on the site already, set back to active by The course is completed, and invited at
 * that account's own username; and one applying while the day's limit on acknowledgements is spent,
 * who is sent the acknowledgement by Send the acknowledgement. The names are invented and every
 * address is example.test's. The recording writes every address "at example.test" and the signature of
 * every confirming link as a fake, so it holds no address and no secret; what is sent now is written
 * the same way before it is compared.
 *
 * The welcome's private links, the four student courses' group invitation links and the mentor
 * resource folder's link, are never in the shipped text: one bracketed line stands where they go,
 * and the recording holds that line. Nothing private is in the recording, and a check says so.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.33's.
 *
 * One line has been added to the welcome since, on purpose, in 1.122.38: it lists the WordPress
 * Credits Translation Track among the student courses, after the Design Track. The recording stays
 * as 1.122.33 wrote it, file and pin; the suite puts that one line into the welcome of each recorded
 * invitation before comparing, as bin/test-invitation-text.php does for the changes made to its own
 * recording, and checks it fell once on each of the three invitations and nowhere else.
 *
 * And the footer has gained a line since, on purpose, in 1.123.24: the site's address under the
 * signature's first line. bin/stubs/mail-since-1-123-24.php puts it into each recorded email, this
 * recording's and M1's, and the suite checks it fell on all sixteen of this one's.
 *
 * Run from the plugin root:  php bin/test-mentor-activation-text.php
 * Record (once, against 1.122.33):  php bin/test-mentor-activation-text.php --record
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
require_once __DIR__ . '/stubs/mail-since-1-123-24.php';

$GLOBALS['sent']       = array();
$GLOBALS['settings']   = array();
$GLOBALS['completed']  = array();
$GLOBALS['slack']      = array();
$GLOBALS['doing_cron'] = false;

function get_bloginfo( $show = 'name' ) {
	return 'WordPress Credits';
}
function wp_doing_cron() {
	return (bool) $GLOBALS['doing_cron'];
}
function wp_schedule_single_event( $time, $hook, $args = array() ) {
	$GLOBALS['cron'][ $hook ] = array(
		'at'    => (int) $time,
		'every' => false,
	);

	return true;
}
function remove_action( $hook, $callback, $priority = 10 ) {
	if ( isset( $GLOBALS['hooks'][ $hook ] ) ) {
		$GLOBALS['hooks'][ $hook ] = array_values(
			array_filter(
				$GLOBALS['hooks'][ $hook ],
				function ( $kept ) use ( $callback ) {
					return $kept !== $callback;
				}
			)
		);
	}

	return true;
}

/**
 * What the mail layer hands WordPress, kept in the order it was handed. The headers are read as
 * `wp_mail()` reads them: a string is its lines, and an empty one holds none.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['sent'][] = array(
		'to'      => $to,
		'context' => WPCPM_Mail::take_context(),
		'subject' => $subject,
		'body'    => $body,
		'headers' => is_array( $headers ) ? array_values( $headers ) : preg_split( '/\R/', (string) $headers, -1, PREG_SPLIT_NO_EMPTY ),
		'files'   => count( (array) $attachments ),
	);

	WPCPM_Mail::take_test();

	return true;
}

/**
 * Core 7.1's `wp_new_user_notification()` for the user's own email: the message is the keyed link
 * alone, the filter the plugin hooks (`wp_new_user_notification_email`) runs over it with the site's
 * name, and `wp_mail()` is handed what comes back, its subject with the name in it. The filter's
 * callbacks are the ones the plugin registered (`WPCPM_Mail::init()`).
 *
 * @param int    $user_id    The new user.
 * @param mixed  $deprecated Unused, as core's.
 * @param string $notify     Who is told, as core's.
 */
function wp_new_user_notification( $user_id, $deprecated = null, $notify = '' ) {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return;
	}

	$blogname = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$email    = array(
		'to'      => $user->user_email,
		'subject' => '[%s] Login Details',
		'message' => "To set your password, visit the following address:\r\n\r\nhttps://example.test/wp-login.php?login=" . rawurlencode( $user->user_login ) . "&key=TEST-KEY-NOT-A-REAL-LINK&action=rp\r\n",
		'headers' => '',
	);

	foreach ( isset( $GLOBALS['hooks']['wp_new_user_notification_email'] ) ? $GLOBALS['hooks']['wp_new_user_notification_email'] : array() as $callback ) {
		$email = call_user_func( $callback, $email, $user, $blogname );
	}

	wp_mail( $email['to'], wp_specialchars_decode( sprintf( $email['subject'], $blogname ), ENT_QUOTES ), $email['message'], $email['headers'] );
}

/** The settings the senders read, as a case sets them over the ones a site holds. */
class WPCPM_Settings {
	public static function get() {
		return array_merge(
			array(
				'mentor_applications_enabled' => true,
				'mentor_activation_enabled'   => true,
				'application_trusted_proxy'   => '',
				'mail_contact'                => 'programs@example.test',
				'mentor_notify'               => '',
				'mentor_course_link'          => 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE',
				'mentors_table'               => 'tblMENTORS0000001',
				'mentor_status'               => 'Active',
				'api_token'                   => 'patTEST',
				'base_id'                     => 'appTEST0000000000',
				'checker_source_status'       => 'Vetted - positive',
				'checker_target_status'       => 'Active',
				'checker_course_slug'         => 'wordpress-credits-mentors-course',
				'checker_course_title'        => 'WordPress Credits Mentor\'s Course',
				'checker_completion_phrase'   => 'Completed the course',
				'checker_timeline_filter'     => 'meta',
				'checker_max_pages'           => 15,
				'checker_batch_size'          => 1,
				'checker_request_delay'       => 0,
				'checker_cache_ttl'           => 0,
				'checker_cron_enabled'        => true,
				'checker_cron_promotes'       => true,
				'checker_skip_airtable'       => false,
			),
			$GLOBALS['settings']
		);
	}

	public static function get_value( $key, $fallback = null ) {
		$settings = self::get();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	public static function is_connected() {
		return true;
	}
}

/** The Administrator Dashboard, published: the page an Administrator's footer names. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() {
		return 'https://example.test/administrator-dashboard/';
	}
}

/** The Mentor Report Card, published: the page the mentor's invitation names under its last label. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() {
		return 'https://example.test/mentor-report-card/';
	}
}

/** The Mentor Status Checker's Slack message, which is no email: each mentor it is told of is kept. */
class WPCPM_Mentor_Checker_Slack {
	public static function record( array $mentor ) {
		$GLOBALS['slack'][] = $mentor;
	}

	public static function flush() {}
}

/**
 * The profile reader: the usernames a case names completed the course, and the username a profile
 * address reduces to (the last segment of it).
 */
class WPCPM_Mentor_Checker_Profile {
	public function __construct( $settings = array() ) {}

	public static function normalize_username( $profile ) {
		$parts = array_values( array_filter( explode( '/', trim( (string) $profile ) ) ) );

		return empty( $parts ) ? '' : strtolower( ltrim( end( $parts ), '@' ) );
	}

	public function check( $profile, $use_cache = true ) {
		$username  = self::normalize_username( $profile );
		$completed = in_array( $username, $GLOBALS['completed'], true );

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
}

/**
 * The Airtable client, at its contract, as bin/test-mentor-airtable-row.php has it. The answers come
 * from the globals `fresh()` sets; every read and write is kept for a check to read. `table_in()`,
 * `formula_in()`, `flatten()` and `is_record_id()` are the real client's, copied.
 */
class WPCPM_Airtable {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';

	public function __construct( $settings = array() ) {}

	public function cached_schema() {
		return array(
			'schema' => $GLOBALS['schema'],
			'age'    => 0,
		);
	}

	public function fetch_schema() {
		return $GLOBALS['schema'];
	}

	public function fetch_all( $table, array $args = array() ) {
		return isset( $GLOBALS['linked'][ $table ] ) ? $GLOBALS['linked'][ $table ] : array();
	}

	public function fetch_page( $table, array $args = array() ) {
		$records = array();

		foreach ( $GLOBALS['by_email'] as $id ) {
			$records[] = array(
				'id'     => $id,
				'fields' => array( 'Email' => 'ada@example.test' ),
			);
		}

		return array(
			'records' => $records,
			'offset'  => null,
		);
	}

	public function create_records( $table, array $records ) {
		foreach ( $records as $record ) {
			$GLOBALS['created'][] = $record;
		}

		return $GLOBALS['created_ids'];
	}

	public function update_records( $table, array $records ) {
		$done = array();

		foreach ( $records as $record ) {
			$GLOBALS['updated'][]  = $record;
			$done[ $record['id'] ] = true;
		}

		return $done;
	}

	public static function table_in( array $schema, $table ) {
		$table = (string) $table;

		if ( '' === $table ) {
			return new WP_Error( 'wpcpm_airtable_no_table', 'No table is set.', array( 'table' => '' ) );
		}

		if ( isset( $schema[ $table ] ) && is_array( $schema[ $table ] ) ) {
			return $schema[ $table ];
		}

		foreach ( $schema as $candidate ) {
			if ( is_array( $candidate ) && isset( $candidate['name'] ) && $table === (string) $candidate['name'] ) {
				return $candidate;
			}
		}

		return new WP_Error( 'wpcpm_airtable_no_table', 'The base has no table ' . $table . '.', array( 'table' => $table ) );
	}

	public function formula_in( $field, array $values, $lower = false, $trim = false ) {
		$values = array_map( 'strval', $values );
		$values = array_values( array_filter( $trim ? array_map( 'trim', $values ) : $values, 'strlen' ) );

		if ( empty( $values ) ) {
			return '';
		}

		$column = '{' . str_replace( array( '\\', '}' ), array( '\\\\', '\\}' ), $field ) . '}';
		$column = $lower ? 'LOWER(' . $column . ')' : $column;
		$column = $trim ? 'TRIM(' . $column . ')' : $column;
		$tests  = array();

		foreach ( $values as $value ) {
			if ( $lower ) {
				$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
			}

			$tests[] = $column . ' = \'' . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $value ) . '\'';
		}

		return 1 === count( $tests ) ? $tests[0] : 'OR(' . implode( ',', $tests ) . ')';
	}

	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) {
				return (string) $value['name'];
			}

			$parts = array();

			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$parts[] = (string) $item;
				} elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
					$parts[] = (string) $item['name'];
				}
			}

			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-queue.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-provision.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-airtable-row.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-activation.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-runner.php';

// The filters and actions the mail layer hooks, so the invitation reaches `welcome_email()` as it does on the site.
WPCPM_Mail::init();

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

/** The note The course is completed is pressed with: the site's alone, which no email may carry. */
const NOTE = 'A note for the Administrators, NEVER-SENT, about this application.';

/**
 * The cases, each a world and what happens in it, in order: `apply` posts the form, `confirm`
 * follows the confirming link of the acknowledgement just sent, `select` and `complete` press that
 * decision on the one row as the first Administrator, `check` runs the Mentor Status Checker's daily
 * run under cron, `drain` sends the invitation queue, and `acknowledge` presses Send the
 * acknowledgement. `completed` names the WordPress.org usernames whose profile shows the course,
 * `former` an account that is a returning mentor's, and `ceiling` spends the day's limit on
 * acknowledgements before anyone applies.
 *
 * @return array[]
 */
function cases() {
	return array(
		'course-shown-to-the-daily-check' => array(
			'completed' => array( 'ada-example' ),
			'steps'     => array( 'apply', 'confirm', 'select', 'check', 'drain' ),
		),
		'course-completed-pressed'        => array(
			'steps' => array( 'apply', 'confirm', 'select', 'complete', 'drain' ),
		),
		'returning-mentor'                => array(
			'former' => true,
			'steps'  => array( 'apply', 'confirm', 'select', 'complete', 'drain' ),
		),
		'acknowledged-by-hand'            => array(
			'ceiling' => true,
			'steps'   => array( 'apply', 'acknowledge' ),
		),
	);
}

/** The answers a good application carries, as the form posts them. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'      => 'Ada Okonjo',
			'email'     => 'ada@example.test',
			'profile'   => 'https://profiles.wordpress.org/ada-example/',
			'country'   => 'Poland',
			'teacher'   => 'No',
			'languages' => array( 'Polish', 'English' ),
			'expertise' => array( 'Training', 'Polyglots' ),
			'hours'     => '3',
			'gpl'       => '1',
			'anything'  => 'I lead a meetup.',
			'consent'   => '1',
		),
		$more
	);
}

/** A dwell token half a minute old, signed the way the guard signs one for this form. */
function dwell_token() {
	$issued = time() - 30;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( WPCPM_Mentor_Application_Form::DWELL_SCOPE . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ) ), 0, 32 );
}

/** A choice list as the schema keeps one. */
function choices( array $names ) {
	return array(
		'choices' => array_map(
			function ( $name ) {
				return array( 'name' => $name );
			},
			$names
		),
	);
}

/**
 * The base's schema, table id to table, as `fetch_schema()` answers it: the Mentors table and the
 * two tables its links point to, as bin/test-mentor-airtable-row.php has it.
 *
 * @return array
 */
function schema() {
	$columns = array(
		'Full Name'                                 => array( 'type' => 'singleLineText' ),
		'WordPress profile'                         => array( 'type' => 'url' ),
		'Email'                                     => array( 'type' => 'email' ),
		'Status'                                    => array(
			'type'    => 'singleSelect',
			'options' => choices( array( 'Interested', 'Vetted - positive', 'Active', 'Inactive' ) ),
		),
		'Country'                                   => array(
			'type'    => 'multipleRecordLinks',
			'options' => array( 'linkedTableId' => 'tblCOUNTRIES00001' ),
		),
		'Languages'                                 => array(
			'type'    => 'multipleRecordLinks',
			'options' => array( 'linkedTableId' => 'tblLANGUAGES00001' ),
		),
		'Contribution Area - Expertise'             => array(
			'type'    => 'multipleSelects',
			'options' => choices( array( 'Polyglots', 'Training' ) ),
		),
		'Available hours per week'                  => array( 'type' => 'number' ),
		'Teacher from a participating institution?' => array(
			'type'    => 'singleSelect',
			'options' => choices( array( 'Yes', 'No' ) ),
		),
		"Anything else you\u{2019}d like us to know?" => array( 'type' => 'multilineText' ),
	);
	$linked  = array(
		'name'    => 'Linked',
		'primary' => 'Name',
		'fields'  => array( 'Name' => '' ),
		'columns' => array( 'Name' => array( 'type' => 'singleLineText' ) ),
		'ids'     => array(),
	);

	return array(
		'tblMENTORS0000001' => array(
			'name'    => 'Mentors',
			'primary' => 'Full Name',
			'fields'  => array_fill_keys( array_keys( $columns ), '' ),
			'columns' => $columns,
			'ids'     => array(),
		),
		'tblCOUNTRIES00001' => array_merge( $linked, array( 'name' => 'Countries' ) ),
		'tblLANGUAGES00001' => array_merge( $linked, array( 'name' => 'Languages' ) ),
	);
}

/**
 * A site where the form is open, with two Administrators, the course link set, Make mentors from
 * applications on, the Airtable base as the stand-in has it, and nothing sent.
 *
 * @param array $case The case.
 */
function fresh( array $case ) {
	$GLOBALS['opts']          = array( 'date_format' => 'F j, Y' );
	$GLOBALS['transients']    = array();
	$GLOBALS['posts']         = array();
	$GLOBALS['pmeta']         = array();
	$GLOBALS['users']         = array();
	$GLOBALS['umeta']         = array();
	$GLOBALS['slugs']         = array();
	$GLOBALS['sent']          = array();
	$GLOBALS['slack']         = array();
	$GLOBALS['cache_deleted'] = array();
	$GLOBALS['cron']          = array();
	$GLOBALS['uid']           = 0;
	$GLOBALS['next_id']       = 500;
	$GLOBALS['next_user']     = 1000;
	$GLOBALS['policy']        = 'https://example.test/privacy/';
	$GLOBALS['doing_cron']    = false;
	$GLOBALS['completed']     = isset( $case['completed'] ) ? $case['completed'] : array();
	$GLOBALS['settings']      = isset( $case['settings'] ) ? $case['settings'] : array();

	$GLOBALS['wpdb']->statements = array();

	$GLOBALS['schema']      = schema();
	$GLOBALS['linked']      = array(
		'tblCOUNTRIES00001' => array(
			array(
				'id'     => 'recCOUNTRYPL00001',
				'fields' => array( 'Name' => 'Poland' ),
			),
		),
		'tblLANGUAGES00001' => array(
			array(
				'id'     => 'recLANGENGLISH001',
				'fields' => array( 'Name' => 'English' ),
			),
			array(
				'id'     => 'recLANGPOLISH0001',
				'fields' => array( 'Name' => 'Polish' ),
			),
		),
	);
	$GLOBALS['by_email']    = array();
	$GLOBALS['created']     = array();
	$GLOBALS['created_ids'] = array( 'recNEWMENTOR00001' );
	$GLOBALS['updated']     = array();

	$_POST                      = array();
	$_GET                       = array();
	$_REQUEST                   = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';

	$page                 = new WP_Post();
	$page->ID             = 42;
	$page->post_type      = 'page';
	$page->post_status    = 'publish';
	$GLOBALS['posts'][42] = $page;
	$GLOBALS['slugs'][42] = 'become-a-mentor';
	$GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] = 42;

	$policy                    = new WP_Post();
	$policy->ID                = 43;
	$policy->post_type         = 'page';
	$policy->post_status       = 'publish';
	$policy->post_modified_gmt = '2026-08-20 11:30:00';
	$GLOBALS['posts'][43]                          = $policy;
	$GLOBALS['opts']['wp_page_for_privacy_policy'] = 43;

	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]       = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1,
		'rows' => array(
			'recCOUNTRY0000001' => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
		),
	);
	$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
		'v'     => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
		'teams' => array(
			'recTEAM0000000001' => 'Polyglots',
			'recTEAM0000000003' => 'Training',
		),
	);

	an_account( 40, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
	an_account( 41, 'Ben Admin', 'ben@example.test', array( 'administrator' ) );
	$GLOBALS['manage'] = array( 40, 41 );

	// A returning mentor: an account of the applicant's address that holds the Mentors row it had,
	// was set inactive by the mentors sync and holds no Mentor role now.
	if ( ! empty( $case['former'] ) ) {
		an_account(
			60,
			'Ada Okonjo',
			'ada@example.test',
			array(),
			array(
				WPCPM_Mentors_Sync::META_RECORD_ID => 'recRETURNING00001',
				WPCPM_Mentors_Sync::META_ACTIVE    => 0,
			)
		);
	}

	// The day's limit on acknowledgements, spent, so the form sends none.
	if ( ! empty( $case['ceiling'] ) ) {
		for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
			WPCPM_Form_Guard::claim_mail( WPCPM_Mentor_Application_Form::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY );
		}
	}
}

/**
 * One step of a case, as the request that takes it.
 *
 * @param string $step The step.
 * @param array  $case The case.
 */
function take_step( $step, array $case ) {
	if ( 'apply' === $step ) {
		$GLOBALS['uid'] = 0;
		$_POST          = array(
			'action'                                     => WPCPM_Mentor_Application_Form::ACTION_SUBMIT,
			'_wpnonce'                                   => wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ),
			WPCPM_Mentor_Application_Form::FIELD_ANSWERS => wp_slash( answers() ),
			WPCPM_Mentor_Application_Form::TOKEN_FIELD   => dwell_token(),
			WPCPM_Mentor_Application_Form::HONEYPOT      => '',
		);
		$_REQUEST       = $_POST;

		run( array( 'WPCPM_Mentor_Application_Form', 'handle_submit' ) );
		return;
	}

	if ( 'confirm' === $step ) {
		$link = '';

		foreach ( $GLOBALS['sent'] as $mail ) {
			if ( 'mentor-application-received' === $mail['context'] && preg_match( '#https://example\.test/wp-admin/admin-post\.php\?\S+#', $mail['body'], $found ) ) {
				$link = $found[0];
			}
		}

		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );

		$GLOBALS['uid'] = 0;
		$_POST          = array();
		$_GET           = $query;
		$_REQUEST       = $query;

		run( array( 'WPCPM_Mentor_Application_Form', 'handle_verify' ) );
		return;
	}

	if ( 'check' === $step ) {
		// The daily check: nobody is signed in, and the request is cron's.
		$GLOBALS['uid']        = 0;
		$GLOBALS['doing_cron'] = true;

		$runner = new WPCPM_Mentor_Checker_Runner();
		$runner->run_all( true );

		$GLOBALS['doing_cron'] = false;
		return;
	}

	if ( 'drain' === $step ) {
		// The invitation queue's cron event, which sends what the activation queued.
		$GLOBALS['uid'] = 0;

		WPCPM_Mail::drain_queue();
		return;
	}

	$decisions = array(
		'select'      => array( WPCPM_Mentor_Application_Queue::ACTION_SELECT, 'handle_select' ),
		'complete'    => array( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, 'handle_complete' ),
		'acknowledge' => array( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, 'handle_acknowledge' ),
	);
	$rows      = WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() );
	$id        = isset( $rows[0] ) ? (int) $rows[0]->ID : 0;

	$GLOBALS['uid'] = 40;
	$_GET           = array();
	$_POST          = array(
		'action'                                          => $decisions[ $step ][0],
		'_wpnonce'                                        => wp_create_nonce( $decisions[ $step ][0] . '_' . $id ),
		WPCPM_Mentor_Application_Queue::FIELD_APPLICATION => (string) $id,
		WPCPM_Mentor_Application_Queue::FIELD_NOTE        => NOTE,
	);
	$_REQUEST       = $_POST;

	run( array( 'WPCPM_Mentor_Application_Queue', $decisions[ $step ][1] ) );
}

/**
 * A case played to its end: every email it sends, in order, as the recording holds them, and how the
 * site stands after it.
 *
 * @param array $case The case.
 * @return array `emails` and `ended`.
 */
function play( array $case ) {
	fresh( $case );

	foreach ( $case['steps'] as $step ) {
		take_step( $step, $case );
	}

	$rows    = WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() );
	$mentors = array();

	foreach ( $GLOBALS['users'] as $user ) {
		if ( in_array( WPCPM_Roles::ROLE_MENTOR, $user->roles, true ) ) {
			$mentors[] = $user->user_login;
		}
	}

	return array(
		'emails' => array_map( 'recorded_form', $GLOBALS['sent'] ),
		'ended'  => array(
			'application' => isset( $rows[0] ) ? WPCPM_Mentor_Application::state_of( $rows[0] ) : null,
			'mentors'     => $mentors,
			'accounts'    => count( $GLOBALS['users'] ),
			'rows'        => count( $GLOBALS['created'] ) . ' created, ' . count( $GLOBALS['updated'] ) . ' updated',
		),
	);
}

/**
 * Every email a case sends, in order, as the recording holds them.
 *
 * @param array $case The case.
 * @return array[]
 */
function emails_of( array $case ) {
	$played = play( $case );

	return $played['emails'];
}

/**
 * An email as the recording holds it: every address written "at example.test", and the signature
 * of a confirming link written as a fake.
 *
 * @param array $mail What `wp_mail()` was handed.
 * @return array
 */
function recorded_form( array $mail ) {
	$plain = function ( $text ) {
		$text = preg_replace( '/([A-Za-z0-9._%+-]+)@example\.test/', '$1 at example.test', (string) $text );

		return preg_replace( '/([?&]t=)[A-Za-z0-9]+/', '$1EXAMPLE-NOT-A-REAL-SIGNATURE', $text );
	};

	$mail['to']      = $plain( $mail['to'] );
	$mail['subject'] = $plain( $mail['subject'] );
	$mail['body']    = $plain( $mail['body'] );
	$mail['headers'] = array_map( $plain, $mail['headers'] );

	return $mail;
}

/** The line before the one 1.122.38 added to the welcome, as the recording of 1.122.33 holds it. */
const WELCOME_DESIGN_LINE = '- WordPress Credits Design Track: https://learn.wordpress.org/course/wordpress-credits-designer-track/';

/** The line 1.122.38 added to the welcome, after the Design Track's: this suite's own copy. */
const WELCOME_TRANSLATION_LINE = '- WordPress Credits Translation Track: https://learn.wordpress.org/course/wordpress-credits-translation-track/';

/**
 * The recording of 1.122.33 with the one line 1.122.38 added to the welcome put in: the Translation
 * Track's, after the Design Track's, in the welcome of each recorded mentor's invitation. Nothing
 * else is touched, and the recording itself is never written.
 *
 * @param array $cases The recording's cases.
 * @param int   $done  How many invitations the line was put into.
 * @return array
 */
function with_translation_line( array $cases, &$done = 0 ) {
	$done = 0;

	foreach ( $cases as $name => $mails ) {
		foreach ( $mails as $n => $mail ) {
			if ( 'invite-mentor' !== $mail['context'] ) {
				continue;
			}

			$count = 0;

			$cases[ $name ][ $n ]['body'] = str_replace( WELCOME_DESIGN_LINE . "\r\n", WELCOME_DESIGN_LINE . "\r\n" . WELCOME_TRANSLATION_LINE . "\r\n", $mail['body'], $count );
			$done                        += $count;
		}
	}

	return $cases;
}

$fixture = __DIR__ . '/fixtures/mentor-activation-1-122-33.json';

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.33: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What the emails an activation brings said in 1.122.33, recorded through the real senders and WPCPM_Mail by bin/test-mentor-activation-text.php --record: the mentor\'s invitation with the welcome and the program\'s Reply-To, and the acknowledgement Send the acknowledgement sends. Invented names, every address written "at example.test", the signature of every confirming link and the key of every password link a fake, and the welcome\'s one bracketed line where the private links go. Never edit by hand: a later release is held to these bytes.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	// A text JSON cannot hold stops the recording here, before a file is written.
	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $fixture ) );

	exit( 0 );
}

echo "=== The recording ===\n";

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( cases() ) );
ck( 'the recording is the one made from 1.122.33', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '22fa480d6885820b70ce487de541e98867ba48a6f5dacb43a54faf638ba932ba' );

// What 1.122.38 changed on purpose, put into the recording: the Translation Track's line, once in each
// of the three mentor invitations.
$translation_lines = 0;

if ( is_array( $recorded ) && isset( $recorded['cases'] ) ) {
	$recorded['cases'] = with_translation_line( $recorded['cases'], $translation_lines );
}

ck( 'and 1.122.38\'s one line, the Translation Track\'s after the Design Track\'s, is put into the welcome of each of its three mentor invitations, and into nothing else', $translation_lines, 3 );

// And what 1.123.24 changed, put into it too: the site's address on the line under the signature's
// first, in every recorded email but one whose body names the home page whole.
if ( is_array( $recorded ) && isset( $recorded['cases'] ) ) {
	$recorded['cases'] = wpcpm_mails_since_1_123_24( $recorded['cases'] );
}

ck( 'and 1.123.24\'s site address is put into the footer of every recorded email, and nothing else of 1.123.24', $GLOBALS['wpcpm_since_1_123_24'], array( 'declined' => 0, 'site_line' => 16, 'site_kept' => 0 ) );

echo "\n=== Every email of every case, as recorded ===\n";

$as_sent = array();
$ended   = array();

foreach ( cases() as $name => $case ) {
	$want             = isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : null;
	$played           = play( $case );
	$got              = $played['emails'];
	$as_sent[ $name ] = $got;
	$ended[ $name ]   = $played['ended'];

	ck( $name . ': the same emails, to the same people, in the same order', array_column( $got, 'to' ), null === $want ? null : array_column( $want, 'to' ) );

	foreach ( (array) $want as $n => $mail ) {
		$sent = isset( $got[ $n ] ) ? $got[ $n ] : array_fill_keys( array_keys( $mail ), null );

		ck( $name . ' #' . $n . ': logged under its own id', $sent['context'], $mail['context'] );
		ck( $name . ' #' . $n . ': the subject, byte for byte', $sent['subject'], $mail['subject'] );
		ck( $name . ' #' . $n . ': the body, byte for byte, the signature and footer included', $sent['body'], $mail['body'] );
		ck( $name . ' #' . $n . ': the headers, byte for byte, and no file attached', array( $sent['headers'], $sent['files'] ), array( $mail['headers'], 0 ) );
	}
}

ck( 'every case sends the emails it recorded, to the same people, in the same order, byte for byte', $as_sent, null === $recorded ? null : $recorded['cases'] );

echo "\n=== The emails M1 recorded, in these cases ===\n";

// The acknowledgement, the Administrators' notice and the selection are 1.122.31's, and must reach
// these cases' recipients as M1's recording has them: its 'applied' case holds the acknowledgement
// and the two notices, and its 'more-information-then-selected' case the selection's email.
$first = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/mentor-applications-1-122-31.json' ), true );
$first = isset( $first['cases'] ) ? $first['cases'] : array();
// As 1.123.24 sends them, the site's address in their footers (bin/stubs/mail-since-1-123-24.php).
$first = wpcpm_mails_since_1_123_24( $first );
$mine  = array(
	'applied'  => isset( $first['applied'] ) ? $first['applied'] : array(),
	'selected' => isset( $first['more-information-then-selected'][4] ) ? $first['more-information-then-selected'][4] : null,
);

ck(
	'the acknowledgement, the two notices and the selection of the two cases that select are byte for byte what M1 recorded, and so are the notices and the acknowledgement of the case that sends it by hand',
	array(
		array_slice( $as_sent['course-shown-to-the-daily-check'], 0, 4 ),
		array_slice( $as_sent['course-completed-pressed'], 0, 4 ),
		array_slice( $as_sent['acknowledged-by-hand'], 0, 3 ),
	),
	array(
		array_merge( $mine['applied'], array( $mine['selected'] ) ),
		array_merge( $mine['applied'], array( $mine['selected'] ) ),
		array( $mine['applied'][1], $mine['applied'][2], $mine['applied'][0] ),
	)
);

echo "\n=== The welcome ===\n";

/** The welcome's first line, as Task 4 ships it: this suite's own copy, so a drift in the template is caught. */
const WELCOME_FIRST = 'Thank you, and congratulations on completing the WordPress Credits Mentor\'s Course! As soon as the first students are assigned to you, you will receive the introduction email connecting you with them.';

/** The one bracketed line where the private links go, which the mentor coordinator replaces on the site. */
const WELCOME_BRACKETED = '[Each course\'s invitation link and the mentor resource folder\'s link are added here in Tools > Emails before this email is sent to mentors.]';

/** The seven public links the welcome holds, and no other. */
const WELCOME_LINKS = array(
	'https://wordpresseducation.org/2026/08/03/welcome-to-your-mentor-report-card/',
	'https://wordpresseducation.org/2026/08/03/welcome-to-your-student-report-card/',
	'https://learn.wordpress.org/course/wordpress-credits-foundations/',
	'https://learn.wordpress.org/course/wordpress-credits-developer-track/',
	'https://learn.wordpress.org/course/wordpress-credits-designer-track/',
	'https://learn.wordpress.org/course/wordpress-credits-translation-track/',
	'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/',
);

/**
 * The links an invitation to a mentor may hold besides the welcome's seven: the link that sets the
 * password (with the fake key), the login page, and the Mentor Report Card, the invitation's own
 * lines that are no part of the welcome, and the site's home page, which the footer names since
 * 1.123.24.
 *
 * @param string $link A link found in a body.
 * @return bool
 */
function own_link( $link ) {
	return 1 === preg_match( '#^https://example\.test/wp-login\.php\?login=[A-Za-z0-9-]+&key=TEST-KEY-NOT-A-REAL-LINK&action=rp$#', $link ) || in_array( $link, array( 'https://example.test/wp-login.php', 'https://example.test/mentor-report-card/', 'https://example.test/' ), true );
}

// Across the invitations: the welcome's first line, its bracketed line, the program's Reply-To, and
// any link that is none of the welcome's seven, the password link, the login page or the Report Card.
$welcome_checks = array( 0, 0, 0, 0 );
$invitations    = 0;

foreach ( $as_sent as $mails ) {
	foreach ( $mails as $mail ) {
		if ( 'invite-mentor' !== $mail['context'] ) {
			continue;
		}

		++$invitations;

		$lines = explode( "\r\n", $mail['body'] );

		$welcome_checks[0] += in_array( WELCOME_FIRST, $lines, true ) ? 1 : 0;
		$welcome_checks[1] += in_array( WELCOME_BRACKETED, $lines, true ) ? 1 : 0;
		$welcome_checks[2] += in_array( 'Reply-To: programs at example.test', $mail['headers'], true ) ? 1 : 0;

		preg_match_all( '#https?://[^\s]+#', $mail['body'], $links );

		foreach ( $links[0] as $link ) {
			if ( ! in_array( $link, WELCOME_LINKS, true ) && ! own_link( $link ) ) {
				++$welcome_checks[3];
			}
		}
	}
}

ck( 'three invitations are sent, one to each mentor made', $invitations, 3 );
ck( 'and the welcome reaches each invitation whole, its bracketed line included, with the program\'s Reply-To and no private link', $welcome_checks, array( 3, 3, 3, 0 ) );

echo "\n=== What the cases say, apart from the recording ===\n";

ck(
	'each case sends what its steps say: the acknowledgement and the notice, in the order the form sends them, or, past the day\'s limit, the notice alone until Send the acknowledgement sends the acknowledgement; the selection\'s email; and the invitation once the queue is drained',
	array_map(
		function ( $mails ) {
			return array_map(
				function ( $mail ) {
					return $mail['context'] . ' > ' . $mail['to'];
				},
				$mails
			);
		},
		$as_sent
	),
	array(
		'course-shown-to-the-daily-check' => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-selected > ada at example.test', 'invite-mentor > ada at example.test' ),
		'course-completed-pressed'        => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-selected > ada at example.test', 'invite-mentor > ada at example.test' ),
		'returning-mentor'                => array( 'mentor-application-received > ada at example.test', 'mentor-selected > ada at example.test', 'invite-mentor > ada at example.test' ),
		'acknowledged-by-hand'            => array( 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-application-received > ada at example.test' ),
	)
);

ck(
	'each case ends as its steps say: the daily check and The course is completed make a new account named after the WordPress.org username and write a new Mentors row; the returning mentor is made a mentor on the account that was theirs, with no second account, and their row is written again; and the applicant sent the acknowledgement by hand is still waiting',
	$ended,
	array(
		'course-shown-to-the-daily-check' => array(
			'application' => WPCPM_Mentor_Application::STATE_ACTIVE,
			'mentors'     => array( 'ada-example' ),
			'accounts'    => 3,
			'rows'        => '1 created, 0 updated',
		),
		'course-completed-pressed'        => array(
			'application' => WPCPM_Mentor_Application::STATE_ACTIVE,
			'mentors'     => array( 'ada-example' ),
			'accounts'    => 3,
			'rows'        => '1 created, 0 updated',
		),
		'returning-mentor'                => array(
			'application' => WPCPM_Mentor_Application::STATE_ACTIVE,
			'mentors'     => array( 'adaokonjo' ),
			'accounts'    => 3,
			'rows'        => '0 created, 1 updated',
		),
		'acknowledged-by-hand'            => array(
			'application' => WPCPM_Mentor_Application::STATE_NEW,
			'mentors'     => array(),
			'accounts'    => 2,
			'rows'        => '0 created, 0 updated',
		),
	)
);

$username_of = array();

foreach ( $as_sent as $name => $mails ) {
	foreach ( $mails as $mail ) {
		if ( 'invite-mentor' === $mail['context'] ) {
			$username_of[ $name ] = 1 === preg_match( '/ Your username is ([A-Za-z0-9-]+)\./', $mail['body'], $found ) ? $found[1] : null;
		}
	}
}

ck(
	'the invitation names the account it was sent to: the new mentor\'s the WordPress.org username, the returning mentor\'s the username that was theirs',
	$username_of,
	array(
		'course-shown-to-the-daily-check' => 'ada-example',
		'course-completed-pressed'        => 'ada-example',
		'returning-mentor'                => 'adaokonjo',
	)
);

$noted = array();

foreach ( $as_sent as $name => $mails ) {
	foreach ( $mails as $mail ) {
		if ( false !== strpos( wp_json_encode( $mail ), 'NEVER-SENT' ) ) {
			$noted[] = $name . ': ' . $mail['context'];
		}
	}
}

ck( 'a press\'s note is the site\'s alone: no email carries it', $noted, array() );

$text = is_file( $fixture ) ? (string) file_get_contents( $fixture ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
preg_match_all( '/key=([A-Za-z0-9-]+)/', $text, $keys );
ck(
	'and the recording holds no address, no secret but a fake one, and no private link: the keys of its password links are the fixed fake, the signatures of its confirming links are a fake, and the only course invitation link is the selection\'s example code',
	is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', $text ), array_values( array_unique( $tokens[1] ) ), array_values( array_unique( $keys[1] ) ), preg_match_all( '/invite=([A-Za-z0-9-]+)/', $text, $codes ) > 0 ? array_values( array_unique( $codes[1] ) ) : array() ) : null,
	array( 0, array( 'EXAMPLE-NOT-A-REAL-SIGNATURE' ), array( 'TEST-KEY-NOT-A-REAL-LINK' ), array( 'EXAMPLE-NOT-A-REAL-CODE' ) )
);
ck( 'nothing was asked of WordPress that the stand-ins do not model', array( isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), $GLOBALS['wpdb']->unmodeled, $GLOBALS['wpdb']->errors ), array( array(), array(), array() ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
