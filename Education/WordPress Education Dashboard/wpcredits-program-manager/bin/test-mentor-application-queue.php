<?php
/**
 * The Waiting for review tab and its ten decisions (1.122.31, 1.122.33): `WPCPM_Mentor_Application_Queue`.
 *
 * Design of 16 October 2026, sections 5 to 7. What each block pins, and why:
 *
 * - **Who may press**: an Administrator, the capability asked before anything else, the nonce keyed
 *   to the decision and the row next, then the row, which must be an application in a state the
 *   decision takes. Every decision is registered for signed-in requests alone.
 * - **Each decision from each state**, every pair of the ten and the nine in one table: the state it
 *   moves to and its sentence, the email it sends or does not, or, from a state it does not take,
 *   the row left as it was and nothing sent; the row's history with who, when and the note; Select
 *   offered again on a lapsed or a declined row; Withdraw on a selected row alone, under the
 *   application's lock, so a row an activation holds or has filed active is left as it is; Put back
 *   in the queue on a spam row alone, sending the acknowledgement once to a row that never had it,
 *   and nothing to one acknowledged before.
 * - **The course is completed** (1.122.33), on a selected row while Make mentors from applications
 *   is on: the activation, by who pressed and with the note, the Slack message, and the notice each
 *   outcome leaves, the one Task 3's step words carried with it; an invitation held because the
 *   account was linked by its username alone, or because another account also answered for the
 *   applicant, or not sent to an Administrator, says so. The Selected row says what the daily check
 *   last found, or that the applicant waits while the switch is off.
 * - **Activated** (1.122.33): the rows the activation's list gives, in its order, each with its
 *   account, the invitation's fate since the activation and never before it, and the Airtable row's,
 *   and Write the Airtable row again only on a row still waiting for it.
 * - **Send the acknowledgement** (1.122.33), on an open row whose acknowledgement never went: once,
 *   with the confirming link, the row left in its state; under the application's lock, so two
 *   presses at one moment send it once.
 * - **The address is confirmed first** for every decision that emails the applicant, and Select
 *   needs the course invitation link; a send that fails leaves the row as it was.
 * - **A note** that is needed and too short, too long, or that the cleaner would take words from is
 *   refused, nothing done, and the typing comes back in that row's box.
 * - **The tab**: six views with their counts; each row whole, its answers escaped, its marks (the
 *   acknowledgement not sent among them), its history, "asked for more information on" where it
 *   applies, a selected row's lapse day, and the decisions its state allows, each a form with its
 *   own nonce that comes back to its view; under the views, Recently decided, the rows filed as spam
 *   and the declined ones, newest first, with Put back in the queue and Select.
 * - **Recently decided is ordered by each row's own decision**: the day it was marked or declined, or,
 *   for a row the checks filed, the day it arrived, a tie of a second by ID newest first, and never
 *   the day of the newest thing that happened to it, such as the applicant confirming the address;
 *   the age line of a spam, declined or lapsed row reads the same day; the list is bounded at fifty
 *   and says when it was cut.
 * - **A row whose acknowledgement never went says so once**: one mark about it, with the cause the
 *   signals give, and no sentence that the applicant has not followed the link; a held one keeps its
 *   held mark, worded without the claim that the applicant was acknowledged; a row that was
 *   acknowledged keeps the held and unconfirmed sentences as they are. A hand-off the form could not
 *   make is such a row too, and Put back in the queue sends it.
 *
 * Run from the plugin root:  php bin/test-mentor-application-queue.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';

$GLOBALS['mail']         = array();
$GLOBALS['mail_fails']   = false;
$GLOBALS['settings']     = array();
$GLOBALS['invite_queue'] = array();
$GLOBALS['invited_at']   = array();
$GLOBALS['log_rows']     = array();

/** The settings the decisions read, as a check sets them. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** The mail layer at its contract, which a check can make refuse. */
class WPCPM_Mail {
	public static function send_to( $email, $context, $build, $locale = '' ) {
		if ( ! is_email( $email ) || ! is_callable( $build ) || $GLOBALS['mail_fails'] ) {
			return false;
		}
		$mail              = (array) call_user_func( $build, $email );
		$mail['to']        = $email;
		$mail['context']   = $context;
		$GLOBALS['mail'][] = $mail;
		return true;
	}
	public static function site_name() {
		return 'WordPress Credits';
	}
	// Who waits in the invitation queue, when each account was last invited, and the sentence for a
	// refused invitation, the real one's for an Administrator.
	public static function queue() {
		return array_values( array_map( 'intval', $GLOBALS['invite_queue'] ) );
	}
	public static function last_invited( $user_id ) {
		return isset( $GLOBALS['invited_at'][ (int) $user_id ] ) ? (int) $GLOBALS['invited_at'][ (int) $user_id ] : 0;
	}
	public static function invite_refused_message( $reader = 'administrator' ) {
		return 'The site\'s mail host refused the invitation, so nothing was sent, and the link in any earlier invitation no longer works. Send it again once the site\'s email is working.';
	}
}

/** The mail log at its contract: the rows a check plants, newest first, and what it was asked. */
class WPCPM_Mail_Log {
	const STATUS_SENT        = 'sent';
	const STATUS_FAILED      = 'failed';
	const STATUS_UNCONFIRMED = 'unconfirmed';
	public static $asked = array();
	public static function find( array $filters, $page = 1, $per_page = 25 ) {
		self::$asked[] = array( $filters, $page, $per_page );
		return array(
			'rows'  => $GLOBALS['log_rows'],
			'total' => count( $GLOBALS['log_rows'] ),
		);
	}
}

/** The shared activation step (Task 3's), as a check sets its answers, keeping what it was asked. */
class WPCPM_Mentor_Activation {
	public static $calls        = array();
	public static $retries      = array();
	public static $answer       = array();
	public static $retry_answer = array();
	public static $enabled      = true;
	public static $unwritten    = 0;
	public static $activated    = array();
	public static $limits       = array();
	public static function activate( $post_id, $actor = 0, $note = '' ) {
		self::$calls[] = array( (int) $post_id, (int) $actor, (string) $note );
		return self::$answer;
	}
	public static function retry_airtable( $post_id, $actor ) {
		self::$retries[] = array( (int) $post_id, (int) $actor );
		return self::$retry_answer;
	}
	public static function enabled() {
		return self::$enabled;
	}
	public static function unwritten_count() {
		return self::$unwritten;
	}
	public static function activated_posts( $limit ) {
		self::$limits[] = (int) $limit;
		return array_slice( self::$activated, 0, max( 1, (int) $limit ) );
	}
}

/** The account key an activation leaves while the Mentors row is still to be written, the real one's name. */
class WPCPM_Mentor_Provision {
	const USER_ROW_PENDING = 'wpcpm_mentor_row_pending';
}

/** The Mentor Status Checker's Slack message, counting each send of what waits. */
class WPCPM_Mentor_Checker_Slack {
	public static $flushed = 0;
	public static function flush() {
		++self::$flushed;
		return null;
	}
}

/** An account's profile screen, as core addresses it. */
function get_edit_user_link( $user_id = null ) {
	return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $user_id;
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-airtable-row.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-queue.php';

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

/** A site with one Administrator signed in and the course link set. */
function reset_world() {
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['users']      = array();
	$GLOBALS['opts']       = array( 'date_format' => 'F j, Y' );
	$GLOBALS['mail']       = array();
	$GLOBALS['mail_fails'] = false;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['settings']   = array(
		'mentor_course_link'   => 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE',
		'checker_course_title' => 'WordPress Credits Mentor\'s Course',
		'mentor_lapse_days'    => 90,
		'mail_contact'         => 'programs@example.test',
	);
	an_account( 9, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
	$GLOBALS['uid']                         = 9;
	$GLOBALS['manage']                      = array( 9 );
	$GLOBALS['invite_queue']                = array();
	$GLOBALS['invited_at']                  = array();
	$GLOBALS['log_rows']                    = array();
	WPCPM_Mail_Log::$asked                  = array();
	WPCPM_Mentor_Activation::$calls         = array();
	WPCPM_Mentor_Activation::$retries       = array();
	WPCPM_Mentor_Activation::$answer        = array(
		'outcome'  => 'activated',
		'user_id'  => 70,
		'airtable' => 'created',
		'message'  => '',
	);
	WPCPM_Mentor_Activation::$retry_answer  = array(
		'outcome' => 'written',
		'message' => '',
	);
	WPCPM_Mentor_Activation::$enabled       = true;
	WPCPM_Mentor_Activation::$unwritten     = 0;
	WPCPM_Mentor_Activation::$activated     = array();
	WPCPM_Mentor_Activation::$limits        = array();
	WPCPM_Mentor_Checker_Slack::$flushed    = 0;
	$GLOBALS['wpdb']->statements            = array();
	$GLOBALS['wpdb']->before_takeover       = null;
	$_POST                                  = array();
	$_GET                                   = array();
	$_REQUEST                               = array();
}

/** The number of forms a part of the tab holds that post one decision. */
function count_forms( $html, $action ) {
	return substr_count( $html, 'name="action" value="' . $action . '"' );
}

/** When an application was made a mentor, as the activation writes it. */
function activated_at( $id, $time ) {
	update_post_meta( $id, WPCPM_Mentor_Application::META_ACTIVATED_AT, (int) $time );
}

/**
 * What a press carried beside its outcome for the notice, read from the store rather than taken,
 * for the reason `kept()` reads there: a take is kept for the rest of a request.
 *
 * @return mixed
 */
function carried() {
	$flash = get_user_meta( 9, 'wpcpm_flash', true );

	return is_array( $flash ) && array_key_exists( WPCPM_Mentor_Application_Queue::FLASH_MESSAGE, $flash ) ? $flash[ WPCPM_Mentor_Application_Queue::FLASH_MESSAGE ] : null;
}

/** One application, stored as the form stores it, in a state, its address confirmed or not. */
function an_application( $state = 'new', $confirmed = true, array $more = array(), array $signals = array() ) {
	$id = WPCPM_Mentor_Application::store(
		array_merge(
			array(
				'name'      => 'Ada Okonjo',
				'email'     => 'ada@example.test',
				'profile'   => 'ada-example',
				'country'   => 'Poland',
				'teacher'   => 'No',
				'languages' => array( 'English', 'Polish' ),
				'expertise' => array( 'Polyglots', 'Training' ),
				'hours'     => '2.5',
				'gpl'       => true,
				'anything'  => 'I lead a meetup.',
			),
			$more
		),
		in_array( $state, array( 'new', 'held', 'spam' ), true ) ? $state : 'new',
		$signals,
		array()
	);

	$events = array(
		'info'     => WPCPM_Mentor_Application::EVENT_INFO,
		'second'   => WPCPM_Mentor_Application::EVENT_SECOND,
		'selected' => WPCPM_Mentor_Application::EVENT_SELECTED,
		'declined' => WPCPM_Mentor_Application::EVENT_DECLINED,
		'lapsed'   => WPCPM_Mentor_Application::EVENT_LAPSED,
		'active'   => WPCPM_Mentor_Application::EVENT_ACTIVATED,
	);

	if ( isset( $events[ $state ] ) ) {
		WPCPM_Mentor_Application::decide( $id, $state, $events[ $state ], 9, '', time() - DAY_IN_SECONDS );
	}

	if ( $confirmed ) {
		WPCPM_Mentor_Application::confirm( $id );
	}

	return $id;
}

/**
 * Press one decision on one row, as its form posts it, and say how it ended.
 *
 * @param string $action The decision's action.
 * @param int    $id     The row.
 * @param array  $more   `note`, `nonce`, `view`.
 * @return array `stop`, `flash`, `state`, `mail`.
 */
function press( $action, $id, array $more = array() ) {
	$GLOBALS['mail'] = array();
	$_POST           = array(
		'action'                                             => $action,
		'_wpnonce'                                           => isset( $more['nonce'] ) ? $more['nonce'] : wp_create_nonce( $action . '_' . $id ),
		WPCPM_Mentor_Application_Queue::FIELD_APPLICATION   => (string) $id,
		WPCPM_Mentor_Application_Queue::VIEW_ARG             => isset( $more['view'] ) ? $more['view'] : 'waiting',
	);
	if ( isset( $more['note'] ) ) {
		$_POST[ WPCPM_Mentor_Application_Queue::FIELD_NOTE ] = wp_slash( $more['note'] );
	}
	$_REQUEST = $_POST;
	unset( $GLOBALS['umeta'][9]['wpcpm_flash'] );

	$methods = WPCPM_Mentor_Application_Queue::decisions();
	$out     = run( array( 'WPCPM_Mentor_Application_Queue', $methods[ $action ] ) );
	$flash   = get_user_meta( 9, 'wpcpm_flash', true );
	$post    = get_post( $id );

	return array(
		'stop'  => $out['stop'],
		'flash' => is_array( $flash ) && isset( $flash['mentors_admin'] ) ? $flash['mentors_admin'] : '',
		'state' => $post instanceof WP_Post ? WPCPM_Mentor_Application::state_of( $post ) : null,
		'mail'  => $GLOBALS['mail'],
	);
}

/**
 * What a refused press kept of its typing for a row's box, read where the next page reads it. Read
 * from the store rather than through `WPCPM_Typed_Text::kept_box()`, which takes it through the
 * flash, whose reads are kept for the rest of a request: in one process the press's own forgetting
 * would be read back.
 *
 * @param int    $id  The row.
 * @param string $key The decision's box.
 * @return string|null
 */
function kept( $id, $key ) {
	$flash = get_user_meta( 9, 'wpcpm_flash', true );
	$entry = is_array( $flash ) ? ( $flash[ WPCPM_Typed_Text::KEPT_CHANNEL . WPCPM_Typed_Text::box_form( WPCPM_Mentor_Application_Queue::TYPED, $id ) ] ?? null ) : null;

	return is_array( $entry ) && isset( $entry['typed'][ $key ] ) ? $entry['typed'][ $key ] : null;
}

/** The newest row of a row's history: what happened, who, and the note. */
function last_event( $id ) {
	$events = WPCPM_Mentor_Application::events( $id );
	$last   = end( $events );

	return array( $last['event'], $last['actor'], $last['note'] );
}

/** The tab, drawn at a view. */
function tab( $view = 'waiting' ) {
	$_GET = 'waiting' === $view ? array() : array( WPCPM_Mentor_Application_Queue::VIEW_ARG => $view );

	return run( array( 'WPCPM_Mentor_Application_Queue', 'render' ) )['html'];
}

/** The part of the tab above Recently decided: the view's own rows. */
function view_part( $html ) {
	$at = strpos( $html, '<section class="wpcpm-mapp-decided">' );

	return false === $at ? $html : substr( $html, 0, $at );
}

/** Recently decided, the part of the tab under the views. */
function decided_part( $html ) {
	$at = strpos( $html, '<section class="wpcpm-mapp-decided">' );

	return false === $at ? '' : substr( $html, $at );
}

/** The marks the rows of a part of the tab carry: each one's kind, its label and its sentence, as a person reads them. */
function marks_of( $html ) {
	preg_match_all( '#<p><span class="wpcpm-inst-mark wpcpm-inst-mark--([a-z]+)">([^<]*)</span> ([^<]*)</p>#', $html, $found, PREG_SET_ORDER );

	return array_map(
		function ( $mark ) {
			return array( $mark[1], html_entity_decode( $mark[2], ENT_QUOTES ), html_entity_decode( $mark[3], ENT_QUOTES ) );
		},
		$found
	);
}

/** The rows of a part of the tab, by ID, in the order they are drawn. */
function row_ids( $html ) {
	preg_match_all( '#<li class="wpcpm-queue-item" id="wpcpm-mentor-application-(\d+)">#', $html, $found );

	return array_map( 'intval', $found[1] );
}

/** One row of a part of the tab, from its opening to the next row's, or to the end of the part. */
function row_for_id( $html, $id ) {
	$at = strpos( $html, '<li class="wpcpm-queue-item" id="wpcpm-mentor-application-' . (int) $id . '">' );

	if ( false === $at ) {
		return '';
	}

	$next = strpos( $html, '<li class="wpcpm-queue-item"', $at + 1 );

	return false === $next ? substr( $html, $at ) : substr( $html, $at, $next - $at );
}

/** The age line each row of a part of the tab carries, by ID. */
function age_lines( $html ) {
	preg_match_all( '#id="wpcpm-mentor-application-(\d+)">.*?<p class="wpcpm-queue-age">([^<]*)</p>#s', $html, $found, PREG_SET_ORDER );

	$out = array();

	foreach ( $found as $line ) {
		$out[ (int) $line[1] ] = html_entity_decode( $line[2], ENT_QUOTES );
	}

	return $out;
}

echo "=== The hooks ===\n";

$GLOBALS['hooks'] = array();
WPCPM_Mentor_Application_Queue::init();
ck(
	'ten decisions, each for a signed-in request alone',
	array( array_values( array_filter( array_keys( $GLOBALS['hooks'] ), function ( $hook ) { return 0 === strpos( $hook, 'admin_post_' ); } ) ) ),
	array( array( 'admin_post_wpcpm_mapp_select', 'admin_post_wpcpm_mapp_info', 'admin_post_wpcpm_mapp_second', 'admin_post_wpcpm_mapp_decline', 'admin_post_wpcpm_mapp_spam', 'admin_post_wpcpm_mapp_withdraw', 'admin_post_wpcpm_mapp_reopen', 'admin_post_wpcpm_mapp_complete', 'admin_post_wpcpm_mapp_airtable', 'admin_post_wpcpm_mapp_acknowledge' ) )
);

echo "\n=== Who may press ===\n";

reset_world();
$id               = an_application();
$GLOBALS['manage'] = array();
$_POST            = array( WPCPM_Mentor_Application_Queue::FIELD_APPLICATION => (string) $id );
$refused          = press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'nonce' => 'nonce-wrong' ) );
ck( 'without the capability, a press is refused before its nonce is read, and nothing moves', array( 0 === strpos( $refused['stop'], 'wp_die: You do not have permission' ), $refused['state'], $refused['mail'] ), array( true, 'new', array() ) );
$GLOBALS['manage'] = array( 9 );
ck( 'a nonce that is not this decision\'s for this row is refused', press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'nonce' => wp_create_nonce( WPCPM_Mentor_Application_Queue::ACTION_DECLINE . '_' . $id ) ) )['stop'], 'nonce refused: wpcpm_mapp_select_' . $id );
$page              = new WP_Post();
$page->ID          = 42;
$page->post_type   = 'page';
$GLOBALS['posts'][42] = $page;
ck( 'a page is no application', press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, 42 )['flash'], 'mapp-unknown' );

echo "\n=== Select ===\n";

reset_world();
$id     = an_application();
$select = press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'note' => 'Strong Polyglots record.' ) );
ck( 'Select moves the row to selected and lands back on the tab with its sentence', array( $select['state'], $select['flash'], $select['stop'] ), array( 'selected', 'mapp-selected', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' ) );
ck(
	'the selection email goes to the applicant, with the course, its invitation link and the program\'s address to write to when it is done, and the program\'s Reply-To',
	array( $select['mail'][0]['to'] ?? null, $select['mail'][0]['context'] ?? null, false !== strpos( $select['mail'][0]['body'] ?? '', "please access the WordPress Credits Mentor's Course through this invitation link: https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE" ), false !== strpos( $select['mail'][0]['body'] ?? '', "When you finish the course, please reply to this email to let us know.\nYou can also write to programs@example.test." ), $select['mail'][0]['headers'] ?? null ),
	array( 'ada@example.test', 'mentor-selected', true, true, array( 'Reply-To: programs@example.test' ) )
);
ck( 'and the history says selected, by whom, with the note', last_event( $id ), array( 'selected', 9, 'Strong Polyglots record.' ) );

// Each of the three decisions that email the applicant waits for the address to be confirmed.
$refused = array();
foreach ( array( WPCPM_Mentor_Application_Queue::ACTION_SELECT, WPCPM_Mentor_Application_Queue::ACTION_INFO, WPCPM_Mentor_Application_Queue::ACTION_DECLINE ) as $action ) {
	reset_world();
	$id                 = an_application( 'new', false );
	$refused[ $action ] = array_intersect_key( press( $action, $id ), array_flip( array( 'flash', 'state', 'mail' ) ) );
}
ck( 'an address nobody confirmed is refused by Select, Ask for more information and Decline alike, nothing sent and nothing moved', $refused, array_fill_keys( array( 'wpcpm_mapp_select', 'wpcpm_mapp_info', 'wpcpm_mapp_decline' ), array( 'flash' => 'mapp-unconfirmed', 'state' => 'new', 'mail' => array() ) ) );

reset_world();
$id                                     = an_application();
$GLOBALS['settings']['mentor_course_link'] = '';
ck( 'with no course invitation link set, Select is refused and sends nothing', array_intersect_key( press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id ), array_flip( array( 'flash', 'state', 'mail' ) ) ), array( 'flash' => 'mapp-no-course-link', 'state' => 'new', 'mail' => array() ) );

// The template's {course_name} is optional but shares its sentence with the required link, so a
// title an Administrator blanked is the default title and never a hole before the link; a title
// that is set is the one named.
$named = array();
foreach ( array(
	'blank'  => array( '', "WordPress Credits Mentor's Course" ),
	'spaces' => array( '   ', "WordPress Credits Mentor's Course" ),
	'set'    => array( 'Example Mentors Course', 'Example Mentors Course' ),
) as $case => $title ) {
	reset_world();
	$id                                          = an_application();
	$GLOBALS['settings']['checker_course_title'] = $title[0];
	$sent                                        = press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id );
	$named[ $case ]                              = array( $sent['state'], false !== strpos( $sent['mail'][0]['body'] ?? '', 'please access the ' . $title[1] . ' through this invitation link: https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE' ) );
}
ck( 'a blank Course title still names the default title in the selection email, so its sentence has no hole before the link, and a title that is set is the one named', $named, array( 'blank' => array( 'selected', true ), 'spaces' => array( 'selected', true ), 'set' => array( 'selected', true ) ) );

reset_world();
$id                    = an_application();
$GLOBALS['mail_fails'] = true;
ck( 'a send that fails leaves the row as it was', array_intersect_key( press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id ), array_flip( array( 'flash', 'state' ) ) ), array( 'flash' => 'mapp-not-sent', 'state' => 'new' ) );

$again = array();
foreach ( array( 'lapsed', 'declined', 'spam', 'selected', 'active' ) as $state ) {
	reset_world();
	$id               = an_application( $state );
	$again[ $state ] = press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'note' => 'They wrote again with their meetup work.' ) )['flash'];
}
ck( 'Select is offered again on a lapsed or a declined row, and refused on a spam, selected or active one', $again, array( 'lapsed' => 'mapp-selected', 'declined' => 'mapp-selected', 'spam' => 'mapp-state', 'selected' => 'mapp-state', 'active' => 'mapp-state' ) );

echo "\n=== Every decision from every state ===\n";

// The states each decision takes, what it moves a row to (null: it stays where it is), its sentence
// and how many emails it sends; from any other state it is refused, the row left as it was and nothing
// sent. The course is completed and Write the Airtable row again reach the activation, stood in here,
// which moves nothing itself; Send the acknowledgement is pressed on rows that never had theirs.
$takes = array(
	'wpcpm_mapp_select'      => array( array( 'new', 'held', 'info', 'second', 'lapsed', 'declined' ), 'selected', 'mapp-selected', 1 ),
	'wpcpm_mapp_info'        => array( array( 'new', 'held', 'info', 'second' ), 'info', 'mapp-info', 1 ),
	'wpcpm_mapp_second'      => array( array( 'new', 'held', 'info', 'second' ), 'second', 'mapp-second', 0 ),
	'wpcpm_mapp_decline'     => array( array( 'new', 'held', 'info', 'second' ), 'declined', 'mapp-declined', 1 ),
	'wpcpm_mapp_spam'        => array( array( 'new', 'held', 'info', 'second' ), 'spam', 'mapp-spam', 0 ),
	'wpcpm_mapp_withdraw'    => array( array( 'selected' ), 'lapsed', 'mapp-withdrawn', 0 ),
	'wpcpm_mapp_reopen'      => array( array( 'spam' ), 'new', 'mapp-reopened', 0 ),
	'wpcpm_mapp_complete'    => array( array( 'selected' ), null, 'mapp-activated', 0 ),
	'wpcpm_mapp_airtable'    => array( array( 'active' ), null, 'mapp-airtable-written', 0 ),
	'wpcpm_mapp_acknowledge' => array( array( 'new', 'held', 'info', 'second' ), null, 'mapp-acknowledged', 1 ),
);
$pressed  = array();
$wanted   = array();
$asked_of = array();
foreach ( $takes as $action => $rule ) {
	$asked_of[ $action ] = 0;
	foreach ( WPCPM_Mentor_Application::states() as $state ) {
		reset_world();
		$id                           = an_application( $state, true, array(), 'wpcpm_mapp_acknowledge' === $action ? array( 'mail-ceiling' ) : array() );
		$out                          = press( $action, $id, array( 'note' => 'A note long enough for every decision.' ) );
		$pressed[ $action ][ $state ] = $out['state'] . ' ' . $out['flash'] . ' ' . count( $out['mail'] );
		$wanted[ $action ][ $state ]  = in_array( $state, $rule[0], true ) ? ( null === $rule[1] ? $state : $rule[1] ) . ' ' . $rule[2] . ' ' . $rule[3] : $state . ' mapp-state 0';
		$asked_of[ $action ]         += count( WPCPM_Mentor_Activation::$calls ) + count( WPCPM_Mentor_Activation::$retries );
	}
}
ck( 'every decision from each of the nine states: where it takes the row, its sentence and its email; anywhere else the row as it was, mapp-state and nothing sent', array( array_keys( $takes ), $pressed ), array( array_keys( WPCPM_Mentor_Application_Queue::decisions() ), $wanted ) );
ck( 'and the activation is reached from the one state each of its two presses takes, and by no other decision', $asked_of, array_merge( array_fill_keys( array_keys( $takes ), 0 ), array( 'wpcpm_mapp_complete' => 1, 'wpcpm_mapp_airtable' => 1 ) ) );

echo "\n=== Ask for more information, Second opinion, Decline, Mark as spam ===\n";

reset_world();
$id   = an_application( 'held' );
$info = press( WPCPM_Mentor_Application_Queue::ACTION_INFO, $id, array( 'note' => 'Asked about Training.' ) );
ck( 'Ask for more information sends its email and keeps the row in the queue, as info', array( $info['state'], $info['mail'][0]['context'] ?? null, in_array( $id, array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states() ) ), true ), last_event( $id ) ), array( 'info', 'mentor-more-information', true, array( 'information requested', 9, 'Asked about Training.' ) ) );
ck( 'and the note never reaches the applicant', false !== strpos( $info['mail'][0]['body'] ?? '', 'Training.' ), false );

reset_world();
$id    = an_application( 'new', false );
$short = press( WPCPM_Mentor_Application_Queue::ACTION_SECOND, $id, array( 'note' => 'Ask Ben.' ) );
ck( 'Second opinion needs a note: one too short is refused, nothing done, and the typing kept for that row\'s box', array( $short['flash'], $short['state'], kept( $id, 'second' ) ), array( 'mapp-note-short', 'new', 'Ask Ben.' ) );
$second = press( WPCPM_Mentor_Application_Queue::ACTION_SECOND, $id, array( 'note' => 'Ben, does this profile show Polyglots work?' ) );
ck( 'with a note it marks the row, sends nothing, and needs no confirmed address', array( $second['state'], $second['mail'], last_event( $id ) ), array( 'second', array(), array( 'second opinion', 9, 'Ben, does this profile show Polyglots work?' ) ) );

reset_world();
$id      = an_application( 'second' );
$decline = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => 'No contribution yet.' ) );
ck( 'Decline sends the decline email and closes the row; the note stays on the site', array( $decline['state'], $decline['mail'][0]['context'] ?? null, false !== strpos( $decline['mail'][0]['body'] ?? '', 'No contribution' ), last_event( $id ) ), array( 'declined', 'mentor-declined', false, array( 'declined', 9, 'No contribution yet.' ) ) );

reset_world();
$id   = an_application( 'new', false );
$spam = press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $id );
ck( 'Mark as spam closes the row, sends nothing, and needs no confirmed address', array( $spam['state'], $spam['mail'], $spam['flash'] ), array( 'spam', array(), 'mapp-spam' ) );

echo "\n=== Withdraw ===\n";

reset_world();
$open     = an_application();
$selected = an_application( 'selected' );
ck( 'Withdraw takes a selected row alone', press( WPCPM_Mentor_Application_Queue::ACTION_WITHDRAW, $open, array( 'note' => 'They wrote to stop.' ) )['flash'], 'mapp-state' );
$withdraw = press( WPCPM_Mentor_Application_Queue::ACTION_WITHDRAW, $selected, array( 'note' => 'They wrote that they cannot continue.', 'view' => 'selected' ) );
ck( 'it lapses the row with the note, sends nothing, and comes back to the view it was pressed on', array( $withdraw['state'], $withdraw['mail'], last_event( $selected ), $withdraw['stop'] ), array( 'lapsed', array(), array( 'withdrawn', 9, 'They wrote that they cannot continue.' ), 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue&mapp_view=selected' ) );
ck( 'under the application\'s lock, which the activation and the lapse clock take, let go once the row has lapsed',
	array( count( preg_grep( '/^INSERT IGNORE .*\'' . WPCPM_Mentor_Application::lock_name( $selected ) . '\'/', $GLOBALS['wpdb']->statements ) ), count( preg_grep( '/^DELETE .*\'' . WPCPM_Mentor_Application::lock_name( $selected ) . '\'/', $GLOBALS['wpdb']->statements ) ), array_key_exists( WPCPM_Mentor_Application::lock_name( $selected ), $GLOBALS['opts'] ) ),
	array( 1, 1, false ) );

// A request making the applicant a mentor holds the application's lock: a Withdraw in that moment would
// file Lapsed an application that is becoming active, whose row could then never be written again.
reset_world();
$in_hand   = an_application( 'selected' );
$held_till = (string) ( time() + 600 );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $in_hand ) ] = $held_till;
$busy_withdraw = press( WPCPM_Mentor_Application_Queue::ACTION_WITHDRAW, $in_hand, array( 'note' => 'They wrote that they cannot continue.' ) );
ck( 'Withdraw while the application\'s lock is held changes nothing, and says another request is working on it',
	array( $busy_withdraw['state'], $busy_withdraw['flash'], carried(), last_event( $in_hand ), $GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $in_hand ) ] ),
	array( 'selected', 'mapp-busy', array( 'status' => 'mapp-busy', 'message' => 'Another request is working on this application at this moment, so nothing was done.' ), array( 'address confirmed', 0, '' ), $held_till ) );

// The activation finishes in the moment between the press's first read of the row and its lock: a lock
// the activation left behind is taken over, and the row, read again under it, is active.
reset_world();
$taken = an_application( 'selected' );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $taken ) ] = '1';
$GLOBALS['wpdb']->before_takeover = function () use ( $taken ) {
	WPCPM_Mentor_Application::decide( $taken, WPCPM_Mentor_Application::STATE_ACTIVE, WPCPM_Mentor_Application::EVENT_ACTIVATED, 0 );
};
$late_withdraw = press( WPCPM_Mentor_Application_Queue::ACTION_WITHDRAW, $taken, array( 'note' => 'They wrote that they cannot continue.' ) );
ck( 'Withdraw after an activation took the row changes nothing: the row is read again under the lock, and stays active',
	array( $late_withdraw['state'], $late_withdraw['flash'], last_event( $taken ), array_key_exists( WPCPM_Mentor_Application::lock_name( $taken ), $GLOBALS['opts'] ) ),
	array( 'active', 'mapp-state', array( 'activated', 0, '' ), false ) );

echo "\n=== A note refused for its words ===\n";

reset_world();
$id   = an_application();
$long = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => str_repeat( 'a', WPCPM_Mentor_Application_Queue::MAX_NOTE + 1 ) ) );
ck( 'a note longer than the box holds is refused, nothing sent', array( $long['flash'], $long['state'], $long['mail'] ), array( 'mapp-note-long', 'new', array() ) );
$loss = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => 'Kids <12 free, adults >18 pay' ) );
ck( 'and so is one the cleaner would take words from, its typing kept', array( $loss['flash'], $loss['state'], kept( $id, 'decline' ) ), array( 'mapp-note-loss', 'new', 'Kids <12 free, adults >18 pay' ) );
ck( 'every outcome a press leaves has a sentence', array_values( array_diff( array( 'mapp-selected', 'mapp-info', 'mapp-second', 'mapp-declined', 'mapp-spam', 'mapp-withdrawn', 'mapp-reopened', 'mapp-reopened-sent', 'mapp-unknown', 'mapp-state', 'mapp-unconfirmed', 'mapp-no-email', 'mapp-no-course-link', 'mapp-not-sent', 'mapp-note-short', 'mapp-note-long', 'mapp-note-loss', 'mapp-activated', 'mapp-activated-refused', 'mapp-activated-login', 'mapp-activated-login-refused', 'mapp-activated-record', 'mapp-activated-record-refused', 'mapp-activated-admin', 'mapp-activated-admin-refused', 'mapp-activated-linked', 'mapp-activated-linked-refused', 'mapp-already', 'mapp-already-mentor', 'mapp-off', 'mapp-busy', 'mapp-no-account', 'mapp-airtable-written', 'mapp-airtable-refused', 'mapp-airtable-not-needed', 'mapp-airtable-gone', 'mapp-acknowledged' ), array_keys( WPCPM_Mentor_Application_Queue::messages() ) ) ), array() );

$words = WPCPM_Mentor_Application_Queue::messages();
ck(
	'and the activation\'s, the retry\'s and the acknowledgement\'s are worded as the design gives them, each with its kind of notice',
	array_intersect_key( $words, array_flip( array( 'mapp-activated', 'mapp-activated-refused', 'mapp-activated-linked', 'mapp-activated-linked-refused', 'mapp-already', 'mapp-already-mentor', 'mapp-off', 'mapp-busy', 'mapp-no-account', 'mapp-airtable-written', 'mapp-airtable-refused', 'mapp-airtable-not-needed', 'mapp-airtable-gone', 'mapp-acknowledged' ) ) ),
	array(
		'mapp-activated'           => array( 'success', 'The applicant is a mentor now: their account is made, the Airtable row written, and the invitation with the welcome is on its way.' ),
		'mapp-activated-refused'   => array( 'warning', 'The applicant is a mentor now: their account is made and the invitation with the welcome is on its way. Airtable refused the row, so Write the Airtable row again waits on it under Activated.' ),
		'mapp-activated-linked'    => array( 'success', 'The applicant is a mentor now: their existing account is linked, the Airtable row written, and the invitation with the welcome is on its way.' ),
		'mapp-activated-linked-refused' => array( 'warning', 'The applicant is a mentor now: their existing account is linked and the invitation with the welcome is on its way. Airtable refused the row, so Write the Airtable row again waits on it under Activated.' ),
		'mapp-already'             => array( 'warning', 'That application was made a mentor already. Nothing was changed.' ),
		'mapp-already-mentor'      => array( 'success', 'That applicant is a mentor on this site already: the application is filed under Activated with a note, and nothing was sent or written to Airtable.' ),
		'mapp-off'                 => array( 'error', 'Nothing was done: Make mentors from applications is off, under Mentor applications on the Students and mentors tab of Settings. It is switched on at the launch.' ),
		'mapp-busy'                => array( 'warning', 'Another request is making that applicant a mentor at this moment. Reload the page in a minute to see where it got to.' ),
		'mapp-no-account'          => array( 'error', 'The account could not be made, so the application waits under Selected (course pending), with the reason on it.' ),
		'mapp-airtable-written'    => array( 'success', 'The Airtable row is written, and the mentor\'s account holds its record.' ),
		'mapp-airtable-refused'    => array( 'error', 'Airtable refused the row again. The reason is on the application; nothing else changed.' ),
		'mapp-airtable-not-needed' => array( 'success', 'Nothing was written: the mentor\'s account holds its Airtable record already.' ),
		'mapp-airtable-gone'       => array( 'warning', 'The account made for this mentor no longer exists; nothing more is written for it.' ),
		'mapp-acknowledged'        => array( 'success', 'The acknowledgement is sent, with the link that confirms the address. The application stays where it was.' ),
	)
);
ck(
	'and an invitation held, or not sent to an Administrator, never reads as on its way: the reason, what to do, and the Airtable row\'s fate after it',
	array_intersect_key( $words, array_flip( array( 'mapp-activated-login', 'mapp-activated-login-refused', 'mapp-activated-record', 'mapp-activated-record-refused', 'mapp-activated-admin', 'mapp-activated-admin-refused' ) ) ),
	array(
		'mapp-activated-login'          => array( 'warning', 'The applicant is a mentor now, but the invitation with the welcome is held: the account was linked by WordPress.org username alone. Confirm it is the same person, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab. The Airtable row is written.' ),
		'mapp-activated-login-refused'  => array( 'warning', 'The applicant is a mentor now, but the invitation with the welcome is held: the account was linked by WordPress.org username alone. Confirm it is the same person, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab. Airtable refused the row, so Write the Airtable row again waits on it under Activated.' ),
		'mapp-activated-record'         => array( 'warning', 'The applicant is a mentor now, but the invitation with the welcome is held: another account also answered for the applicant. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab. The Airtable row is written.' ),
		'mapp-activated-record-refused' => array( 'warning', 'The applicant is a mentor now, but the invitation with the welcome is held: another account also answered for the applicant. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab. Airtable refused the row, so Write the Airtable row again waits on it under Activated.' ),
		'mapp-activated-admin'          => array( 'success', 'The applicant is a mentor now. No invitation was sent: the account is an Administrator\'s, which signs in already. The Airtable row is written.' ),
		'mapp-activated-admin-refused'  => array( 'warning', 'The applicant is a mentor now. No invitation was sent: the account is an Administrator\'s, which signs in already. Airtable refused the row, so Write the Airtable row again waits on it under Activated.' ),
	)
);

echo "\n=== The course is completed ===\n";
reset_world();
$sel = an_application( 'selected' );
update_post_meta( $sel, WPCPM_Mentor_Application::META_CHECKED, array( 'at' => $GLOBALS['now'], 'state' => 'not_completed', 'message' => '' ) );
$selected_tab = view_part( tab( 'selected' ) );
ck( 'the Selected view shows the last check and the press, which asks first',
	array( false !== strpos( $selected_tab, 'Checked on October 16, 2026: the course does not show on the profile yet.' ), false !== strpos( $selected_tab, 'data-wpcpm-confirm="Make Ada Okonjo a mentor now?' ), count_forms( $selected_tab, WPCPM_Mentor_Application_Queue::ACTION_COMPLETE ) ),
	array( true, true, 1 ) );
ck( 'the press carries its question whole and its note box, and comes before Withdraw',
	array( false !== strpos( $selected_tab, 'data-wpcpm-confirm="Make Ada Okonjo a mentor now? It makes or links their account, writes or reactivates their Airtable row, and queues the invitation with the welcome unless the account must be confirmed first or is an Administrator&#039;s. If Airtable refuses the row, they are a mentor all the same. An applicant who is a mentor already is only filed as one."' ), false !== strpos( $selected_tab, 'How you know, for the next Administrator, optional. It is never sent.' ), strpos( $selected_tab, 'value="wpcpm_mapp_complete"' ) < strpos( $selected_tab, 'value="wpcpm_mapp_withdraw"' ) ),
	array( true, true, true ) );
$checked = array();
foreach ( array(
	'never'     => null,
	'completed' => array( 'completed', '' ),
	'not found' => array( 'not_found', 'The profile page was not found.' ),
	'error'     => array( 'error', 'HTTP 503' ),
) as $case => $look ) {
	reset_world();
	$row = an_application( 'selected' );
	if ( null !== $look ) {
		update_post_meta( $row, WPCPM_Mentor_Application::META_CHECKED, array( 'at' => $GLOBALS['now'], 'state' => $look[0], 'message' => $look[1] ) );
	}
	preg_match( '#<p class="wpcpm-mapp-check">([^<]*)</p>#', view_part( tab( 'selected' ) ), $line );
	$checked[ $case ] = isset( $line[1] ) ? html_entity_decode( $line[1], ENT_QUOTES ) : null;
}
ck( 'and what the daily check last found, in each of its words',
	$checked,
	array(
		'never'     => 'Not checked yet: the daily check reads the profile next.',
		'completed' => 'Checked on October 16, 2026: the course shows on the profile.',
		'not found' => 'Checked on October 16, 2026: the profile could not be read (The profile page was not found.).',
		'error'     => 'Checked on October 16, 2026: the profile could not be read (HTTP 503).',
	) );
reset_world();
$sel = an_application( 'selected' );
update_post_meta( $sel, WPCPM_Mentor_Application::META_PROBLEM, array( 'at' => $GLOBALS['now'], 'message' => 'Sorry, that username already exists!' ) );
ck( 'a selected row whose account could not be made says when and why', false !== strpos( view_part( tab( 'selected' ) ), 'The account could not be made on October 16, 2026: Sorry, that username already exists!' ), true );
WPCPM_Mentor_Activation::$enabled = false;
$waiting_tab                      = view_part( tab( 'selected' ) );
ck( 'while Make mentors from applications is off, the row says the applicant waits and offers no press',
	array( false !== strpos( $waiting_tab, 'Making mentors from applications is off until the launch: this applicant waits.' ), count_forms( $waiting_tab, WPCPM_Mentor_Application_Queue::ACTION_COMPLETE ) ),
	array( true, 0 ) );
WPCPM_Mentor_Activation::$enabled = true;
$done                             = press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, $sel, array( 'note' => 'Wrote in on Monday.', 'view' => 'selected' ) );
ck( 'The course is completed on a selected row makes the applicant a mentor, by who pressed and with the note, sends the Slack message, and comes back to the Selected view saying so',
	array( $done['flash'], $done['stop'], WPCPM_Mentor_Activation::$calls, WPCPM_Mentor_Checker_Slack::$flushed ),
	array( 'mapp-activated', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue&mapp_view=selected', array( array( $sel, 9, 'Wrote in on Monday.' ) ), 1 ) );
ck( 'and carries no words of its own for the notice, whose sentence is the one above', carried(), '' );
foreach ( array(
	'refused'        => array( 'activated', 'refused', 'mapp-activated-refused' ),
	'already'        => array( 'already', '', 'mapp-already' ),
	'already-mentor' => array( 'already-mentor', '', 'mapp-already-mentor' ),
	'off'            => array( 'off', '', 'mapp-off' ),
	'busy'           => array( 'busy', '', 'mapp-busy' ),
	'no-account'     => array( 'no-account', '', 'mapp-no-account' ),
	'not-selected'   => array( 'not-selected', '', 'mapp-state' ),
	'unknown'        => array( 'unknown', '', 'mapp-unknown' ),
) as $case => $answer ) {
	WPCPM_Mentor_Activation::$answer = array( 'outcome' => $answer[0], 'user_id' => 0, 'airtable' => $answer[1], 'message' => '' );
	ck( 'and says ' . $case . ' in its own words', press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, an_application( 'selected' ), array( 'view' => 'selected' ) )['flash'], $answer[2] );
}
ck( 'it is refused on a row that is not selected, and nothing is activated', array( press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, an_application( 'new' ) )['flash'], count( WPCPM_Mentor_Activation::$calls ) ), array( 'mapp-state', 9 ) );
$GLOBALS['manage'] = array();
$refused           = press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, $sel, array( 'nonce' => 'nonce-wrong' ) );
ck( 'without the capability it is refused before its nonce is read, and nothing is activated', array( 0 === strpos( $refused['stop'], 'wp_die: You do not have permission' ), count( WPCPM_Mentor_Activation::$calls ) ), array( true, 9 ) );
$GLOBALS['manage'] = array( 9 );
ck( 'and a nonce that is not this press\'s for this row is refused', press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, $sel, array( 'nonce' => wp_create_nonce( WPCPM_Mentor_Application_Queue::ACTION_SELECT . '_' . $sel ) ) )['stop'], 'nonce refused: wpcpm_mapp_complete_' . $sel );

// An invitation held for a person to confirm the account (rulings R5 and P9b), or one an
// Administrator's account is never sent, is never said to be on its way.
$held = array();
foreach ( array(
	'login'          => array( false, 'login', 'created' ),
	'login refused'  => array( false, 'login', 'refused' ),
	'record'         => array( false, 'record', 'reactivated' ),
	'record refused' => array( false, 'record', 'refused' ),
	'admin'          => array( false, '', 'adopted' ),
	'admin refused'  => array( false, '', 'refused' ),
	'invited'        => array( true, '', 'created', false ),
	'linked'         => array( true, '', 'reactivated', true ),
	'linked refused' => array( true, '', 'refused', true ),
) as $case => $answer ) {
	reset_world();
	WPCPM_Mentor_Activation::$answer = array( 'outcome' => 'activated', 'user_id' => 70, 'airtable' => $answer[2], 'invited' => $answer[0], 'held' => $answer[1], 'linked' => isset( $answer[3] ) ? $answer[3] : true, 'message' => 'Made a mentor, but the invitation is held.' );
	$held[ $case ]                   = press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, an_application( 'selected' ), array( 'view' => 'selected' ) )['flash'];
}
ck( 'an invitation held because the account was linked by username alone, or because another account also answered, or not sent to an Administrator, leaves its own notice, and the refused row its own beside it',
	$held,
	array(
		'login'          => 'mapp-activated-login',
		'login refused'  => 'mapp-activated-login-refused',
		'record'         => 'mapp-activated-record',
		'record refused' => 'mapp-activated-record-refused',
		'admin'          => 'mapp-activated-admin',
		'admin refused'  => 'mapp-activated-admin-refused',
		'invited'        => 'mapp-activated',
		'linked'         => 'mapp-activated-linked',
		'linked refused' => 'mapp-activated-linked-refused',
	) );

// Task 3's step words a sentence for every outcome that is not a mentor made: the press carries it
// with its outcome, and the notice prints it.
reset_world();
WPCPM_Mentor_Activation::$answer = array( 'outcome' => 'no-account', 'user_id' => 0, 'airtable' => '', 'invited' => false, 'held' => '', 'message' => 'The account could not be made, so the application stays selected: Sorry, that username already exists!' );
$failed                          = press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, an_application( 'selected' ), array( 'view' => 'selected' ) );
ck( 'an activation that made no mentor carries the step\'s own sentence with its outcome',
	array( $failed['flash'], carried() ),
	array( 'mapp-no-account', array( 'status' => 'mapp-no-account', 'message' => 'The account could not be made, so the application stays selected: Sorry, that username already exists!' ) ) );
ck( 'and nothing is told to Slack for it', WPCPM_Mentor_Checker_Slack::$flushed, 0 );
$GLOBALS['uid'] = 31;
WPCPM_Flash::set( WPCPM_Mentor_Application_Queue::FLASH_MESSAGE, array( 'status' => 'mapp-no-account', 'message' => 'The account could not be made, so the application stays selected: Sorry, that username already exists!' ) );
ck( 'and the notice for that outcome prints it', WPCPM_Mentor_Application_Queue::notices()['mapp-no-account'], array( 'error', 'The account could not be made, so the application stays selected: Sorry, that username already exists!' ) );
$GLOBALS['uid'] = 32;
WPCPM_Flash::set( WPCPM_Mentor_Application_Queue::FLASH_MESSAGE, array( 'status' => 'mapp-off', 'message' => 'Nothing was done: Make mentors from applications is off.' ) );
ck( 'while a sentence carried for another outcome is that outcome\'s alone, and every other notice keeps its own', array_diff_key( WPCPM_Mentor_Application_Queue::notices(), array( 'mapp-off' => true ) ), array_diff_key( $words, array( 'mapp-off' => true ) ) );
$GLOBALS['uid'] = 33;
ck( 'and with nothing carried, the notices are the plain map', WPCPM_Mentor_Application_Queue::notices(), $words );
$GLOBALS['uid'] = 9;

echo "\n=== Activated ===\n";
reset_world();
$made    = an_application( 'active' );
$refused = an_application( 'active' );
an_account( 70, 'Ada Okonjo', 'ada@example.test', array( 'wpcpm_mentor' ), array( WPCPM_Mentor_Provision::USER_ROW_PENDING => 1 ) );
foreach ( array( $made => 'created', $refused => 'refused' ) as $id => $state ) {
	update_post_meta( $id, WPCPM_Mentor_Application::META_USER, 70 );
	activated_at( $id, $GLOBALS['now'] );
	update_post_meta( $id, WPCPM_Mentor_Application::META_AIRTABLE, array( 'state' => $state, 'how' => 'create', 'record' => 'created' === $state ? 'recNEWMENTOR00001' : '', 'error' => 'refused' === $state ? 'Airtable refused the write.' : '', 'left_out' => array(), 'at' => $GLOBALS['now'] ) );
}
WPCPM_Mentor_Activation::$activated = array( get_post( $refused ), get_post( $made ) );
$active_tab                         = view_part( tab( 'active' ) );
ck( 'the Activated view draws the rows in the order the list gives, the one waiting for its row first, with the retry only there',
	array( row_ids( $active_tab ), count_forms( $active_tab, WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE ) ), array( array( $refused, $made ), 1 ) );
ck( 'each with its Airtable line',
	array( false !== strpos( $active_tab, 'Airtable refused the row on October 16, 2026: Airtable refused the write.' ), false !== strpos( $active_tab, 'Airtable row written on October 16, 2026.' ) ), array( true, true ) );
ck( 'the day it was made a mentor, and the account, its username linked to its profile screen',
	array( age_lines( $active_tab )[ $made ], false !== strpos( $active_tab, 'Account: <a href="https://example.test/wp-admin/user-edit.php?user_id=70">adaokonjo</a>' ) ),
	array( 'Made a mentor on October 16, 2026.', true ) );
ck( 'the retry is the refused row\'s, asks nothing and has no note box', array( false !== strpos( $active_tab, '<input type="hidden" name="action" value="wpcpm_mapp_airtable" /><input type="hidden" name="wpcpm_mapp_app" value="' . $refused . '" />' ), substr_count( $active_tab, 'data-wpcpm-confirm' ), substr_count( $active_tab, '<textarea' ) ), array( true, 0, 0 ) );
$GLOBALS['log_rows'] = array( array( 'status' => 'failed', 'error' => 'Could not instantiate mail function.', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] + 60 ) ) );
ck( 'a refused welcome reads as refused, with what to do',
	WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ),
	'The invitation with the welcome went to the mail host on October 16, 2026. ' . WPCPM_Mail::invite_refused_message( 'administrator' ) . ' Send invite or Resend invite is on its row in the Mentors screen\'s Accounts tab.' );
ck( 'the mail log is asked for the newest mentor invitation to the account\'s own address', end( WPCPM_Mail_Log::$asked ), array( array( 'exact_email' => 'ada@example.test', 'template' => 'invite-mentor' ), 1, 1 ) );
an_account( 73, 'Bo Example', '', array( 'wpcpm_mentor' ) );
update_post_meta( $made, WPCPM_Mentor_Application::META_USER, 73 );
WPCPM_Mail_Log::$asked = array();
ck( 'an account with no address asks the log nothing, which would read an empty address as anybody\'s', array( WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), WPCPM_Mail_Log::$asked ), array( 'No invitation has gone since this applicant was made a mentor. Send invite or Resend invite is on its row in the Mentors screen\'s Accounts tab.', array() ) );
update_post_meta( $made, WPCPM_Mentor_Application::META_USER, 70 );
$GLOBALS['log_rows']       = array( array( 'status' => 'sent', 'error' => '', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] - 30 * DAY_IN_SECONDS ) ) );
$GLOBALS['invited_at'][70] = $GLOBALS['now'] - 30 * DAY_IN_SECONDS;
ck( 'an invitation older than the activation is never read as the welcome: a sent row and a stamp from before it say nothing went since',
	WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ),
	'No invitation has gone since this applicant was made a mentor. Send invite or Resend invite is on its row in the Mentors screen\'s Accounts tab.' );
$GLOBALS['log_rows'] = array( array( 'status' => 'sent', 'error' => '', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] ) ) );
ck( 'one sent at the activation\'s own second is the welcome', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome was sent on October 16, 2026.' );
$GLOBALS['log_rows']       = array();
$GLOBALS['invited_at'][70] = $GLOBALS['now'] + 120;
ck( 'past the log\'s thirty days, a stamp from after the activation is the welcome', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome was sent on October 16, 2026.' );
$GLOBALS['log_rows'] = array( array( 'status' => 'unconfirmed', 'error' => '', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] + 60 ) ) );
ck( 'one the mail log could not confirm says so', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome was handed over on October 16, 2026, and the mail log does not say whether it went.' );
$GLOBALS['invite_queue'] = array( 70 );
ck( 'one still in the queue waits there', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome waits in the email queue.' );
$GLOBALS['invite_queue']   = array();
$GLOBALS['log_rows']       = array();
$GLOBALS['invited_at'][70] = 0;
$linked                    = array();
foreach ( array( 'login', 'record', 'row', 'email' ) as $how ) {
	update_post_meta( $made, WPCPM_Mentor_Application::META_LINKED_BY, $how );
	$linked[ $how ] = WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) );
}
ck( 'an invitation the activation held, for an account linked by username alone or while another account also answered, says why and what to do, and an account found any other way reads as one nothing has gone to since',
	$linked,
	array(
		'login'  => 'No invitation has gone since this applicant was made a mentor: the account was linked by WordPress.org username alone. Confirm it is the same person, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.',
		'record' => 'No invitation has gone since this applicant was made a mentor: another account also answered for the applicant. Confirm which account is the person\'s, then press Send invite or Resend invite on its row in the Mentors screen\'s Accounts tab.',
		'row'    => 'No invitation has gone since this applicant was made a mentor. Send invite or Resend invite is on its row in the Mentors screen\'s Accounts tab.',
		'email'  => 'No invitation has gone since this applicant was made a mentor. Send invite or Resend invite is on its row in the Mentors screen\'s Accounts tab.',
	) );
update_post_meta( $made, WPCPM_Mentor_Application::META_LINKED_BY, 'login' );
$GLOBALS['log_rows'] = array( array( 'status' => 'sent', 'error' => '', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] + DAY_IN_SECONDS ) ) );
ck( 'and once a person has sent it, it reads as sent', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome was sent on October 17, 2026.' );
$GLOBALS['log_rows'] = array();
delete_post_meta( $made, WPCPM_Mentor_Application::META_LINKED_BY );
an_account( 72, 'Ida Okafor', 'ida.okafor@example.test', array( 'administrator' ) );
update_post_meta( $made, WPCPM_Mentor_Application::META_USER, 72 );
ck( 'and an Administrator\'s account, which gets none, says why', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'No invitation: the account is an Administrator\'s, which signs in already.' );
update_post_meta( $made, WPCPM_Mentor_Application::META_USER, 999 );
ck( 'an account deleted since the activation reads as gone, on both lines, and nothing fails',
	array( WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), false !== strpos( view_part( tab( 'active' ) ), 'Account: the account made for this mentor no longer exists.' ) ),
	array( 'The account is gone, so no invitation can reach it.', true ) );
update_post_meta( $made, WPCPM_Mentor_Application::META_AIRTABLE, array( 'state' => 'found', 'how' => 'create', 'record' => 'recBYHAND00000002', 'error' => '', 'left_out' => array(), 'at' => $GLOBALS['now'] ) );
ck( 'and a row the mentors sync linked reads as found and adds no retry: the one form left is the refused row\'s', array( false !== strpos( view_part( tab( 'active' ) ), 'The account holds its Airtable record, linked by the mentors sync or by hand; nothing was written from here.' ), count_forms( view_part( tab( 'active' ) ), WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE ) ), array( true, 1 ) );
// A refused row is offered the retry only while its account is there and still waits for its row.
$offered = array();
foreach ( array(
	'waits'         => array( 70, true ),
	'waits no more' => array( 70, false ),
	'gone'          => array( 999, true ),
) as $case => $account ) {
	update_post_meta( $refused, WPCPM_Mentor_Application::META_USER, $account[0] );
	// The account gone, its wait is planted all the same, so only the account's absence refuses it.
	if ( $account[1] ) {
		$GLOBALS['umeta'][ $account[0] ][ WPCPM_Mentor_Provision::USER_ROW_PENDING ] = 1;
	} else {
		unset( $GLOBALS['umeta'][ $account[0] ][ WPCPM_Mentor_Provision::USER_ROW_PENDING ] );
	}
	$offered[ $case ] = count_forms( row_for_id( view_part( tab( 'active' ) ), $refused ), WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE );
}
update_post_meta( $refused, WPCPM_Mentor_Application::META_USER, 70 );
$GLOBALS['umeta'][70][ WPCPM_Mentor_Provision::USER_ROW_PENDING ] = 1;
unset( $GLOBALS['umeta'][999] );
ck( 'a refused row is offered Write the Airtable row again while its account still waits for the row, and not once the sync linked it or the account is gone', $offered, array( 'waits' => 1, 'waits no more' => 0, 'gone' => 0 ) );
update_post_meta( $made, WPCPM_Mentor_Application::META_USER, 70 );
$airtable_lines = array();
foreach ( array(
	'reactivated' => array(),
	'adopted'     => array(),
	'gone'        => array(),
	'already'     => array(),
	'pending'     => array(),
	'created'     => array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Klingon' ) ) ),
) as $state => $left_out ) {
	update_post_meta( $made, WPCPM_Mentor_Application::META_AIRTABLE, array( 'state' => $state, 'how' => 'create', 'record' => '', 'error' => '', 'left_out' => $left_out, 'at' => $GLOBALS['now'] ) );
	preg_match( '#<p class="wpcpm-mapp-airtable">([^<]*)</p>#', row_for_id( view_part( tab( 'active' ) ), $made ), $line );
	$airtable_lines[ $state ] = isset( $line[1] ) ? html_entity_decode( $line[1], ENT_QUOTES ) : null;
}
ck( 'the Airtable line of every other fate the row can have, with what was left out of it after',
	$airtable_lines,
	array(
		'reactivated' => 'The mentor\'s own Airtable row was set back to Active on October 16, 2026.',
		'adopted'     => 'The Mentors row that held this address was set back to Active and linked on October 16, 2026.',
		'gone'        => 'The account made for this mentor no longer exists, so no Airtable row is written for it.',
		'already'     => 'A mentor already: nothing was written.',
		'pending'     => 'The Airtable row has not been written yet.',
		'created'     => 'Airtable row written on October 16, 2026. Languages: not in the base: Klingon.',
	) );
update_post_meta( $made, WPCPM_Mentor_Application::META_AIRTABLE, array( 'state' => 'already', 'how' => '', 'record' => 'recOWNMENTOR00001', 'error' => '', 'left_out' => array(), 'at' => $GLOBALS['now'] ) );
$filed = row_for_id( view_part( tab( 'active' ) ), $made );
ck( 'an application filed as already a mentor is dated so, and has no invitation line', array( age_lines( $filed )[ $made ], substr_count( $filed, 'class="wpcpm-mapp-invitation"' ) ), array( 'Filed as already a mentor on October 16, 2026.', 0 ) );
$retried = press( WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE, $refused, array( 'view' => 'active' ) );
ck( 'Write the Airtable row again on an activated application writes it, by who pressed, and comes back to Activated',
	array( $retried['flash'], $retried['stop'], WPCPM_Mentor_Activation::$retries ),
	array( 'mapp-airtable-written', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue&mapp_view=active', array( array( $refused, 9 ) ) ) );
ck( 'and is refused on a row that is not active', press( WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE, an_application( 'selected' ) )['flash'], 'mapp-state' );
$retry_flashes = array();
foreach ( array(
	'refused'    => array( 'refused', 'The Airtable row was not written: Airtable refused the write.', 'mapp-airtable-refused' ),
	'not-needed' => array( 'not-needed', 'Nothing was written: the mentor\'s account holds its Airtable record already.', 'mapp-airtable-not-needed' ),
	'gone'       => array( 'gone', 'The account made for this mentor no longer exists; nothing more is written for it.', 'mapp-airtable-gone' ),
	'busy'       => array( 'busy', 'Another request is working on this application at this moment, so nothing was done.', 'mapp-busy' ),
	'not-active' => array( 'not-active', 'Only an application that was made a mentor has an Airtable row to write, and this one was not.', 'mapp-state' ),
	'unknown'    => array( 'unknown', 'There is no such mentor application.', 'mapp-unknown' ),
) as $case => $answer ) {
	WPCPM_Mentor_Activation::$retry_answer = array( 'outcome' => $answer[0], 'message' => $answer[1] );
	$out                                    = press( WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE, $refused, array( 'view' => 'active' ) );
	$retry_flashes[ $case ]                 = array( $out['flash'], carried() );
}
ck( 'each other outcome of the retry leaves its notice, with the step\'s own sentence carried for it',
	$retry_flashes,
	array(
		'refused'    => array( 'mapp-airtable-refused', array( 'status' => 'mapp-airtable-refused', 'message' => 'The Airtable row was not written: Airtable refused the write.' ) ),
		// Read from the user meta as the flash wrote it, slashed for core's user meta, which unslashes it.
		'not-needed' => array( 'mapp-airtable-not-needed', array( 'status' => 'mapp-airtable-not-needed', 'message' => wp_slash( 'Nothing was written: the mentor\'s account holds its Airtable record already.' ) ) ),
		'gone'       => array( 'mapp-airtable-gone', array( 'status' => 'mapp-airtable-gone', 'message' => 'The account made for this mentor no longer exists; nothing more is written for it.' ) ),
		'busy'       => array( 'mapp-busy', array( 'status' => 'mapp-busy', 'message' => 'Another request is working on this application at this moment, so nothing was done.' ) ),
		'not-active' => array( 'mapp-state', array( 'status' => 'mapp-state', 'message' => 'Only an application that was made a mentor has an Airtable row to write, and this one was not.' ) ),
		'unknown'    => array( 'mapp-unknown', array( 'status' => 'mapp-unknown', 'message' => 'There is no such mentor application.' ) ),
	) );

reset_world();
$jo = an_application( 'active' );
WPCPM_Mentor_Application::add_event( $jo, WPCPM_Mentor_Application::EVENT_AIRTABLE_REFUSED, 0, 'Airtable refused the write.' );
WPCPM_Mentor_Application::add_event( $jo, WPCPM_Mentor_Application::EVENT_AIRTABLE_WRITTEN, 9 );
WPCPM_Mentor_Application::add_event( $jo, WPCPM_Mentor_Application::EVENT_AIRTABLE_SETTLED, 0 );
$ann = an_application( 'active' );
WPCPM_Mentor_Application::add_event( $ann, WPCPM_Mentor_Application::EVENT_ALREADY_MENTOR, 9 );
WPCPM_Mentor_Application::add_event( $ann, WPCPM_Mentor_Application::EVENT_AIRTABLE_SETTLED, 0 );
update_post_meta( $ann, WPCPM_Mentor_Application::META_AIRTABLE, array( 'state' => 'gone', 'how' => 'create', 'record' => '', 'error' => '', 'left_out' => array(), 'at' => $GLOBALS['now'] ) );
$mo = an_application( 'new', false, array(), array( 'mail-ceiling' ) );
WPCPM_Mentor_Application::add_event( $mo, WPCPM_Mentor_Application::EVENT_ACKNOWLEDGED, 9 );
WPCPM_Mentor_Application::add_event( $mo, WPCPM_Mentor_Application::EVENT_ACTIVATED, 0 );
WPCPM_Mentor_Activation::$activated = array( get_post( $jo ), get_post( $ann ) );
$active_part                        = view_part( tab( 'active' ) );
$jo_history                         = row_for_id( $active_part, $jo );
$ann_history                        = row_for_id( $active_part, $ann );
$mo_history                         = row_for_id( view_part( tab() ), $mo );
$settled_words                      = array( ': the row was linked by the mentors sync or by hand, by the site.', ': the account is gone, so nothing more is written for it, by the site.' );
ck( 'the history words what the activation and the acknowledgement write: the row written, refused and settled, linked or gone by the row\'s own fate, filed as already a mentor, the acknowledgement sent',
	array(
		'linked' => array( substr_count( $jo_history, ': Airtable refused the row, by the site.' ), substr_count( $jo_history, ': wrote the Airtable row, Ida Admin.' ), substr_count( $jo_history, $settled_words[0] ), substr_count( $jo_history, $settled_words[1] ) ),
		'gone'   => array( substr_count( $ann_history, ': filed as already a mentor, Ida Admin.' ), substr_count( $ann_history, $settled_words[0] ), substr_count( $ann_history, $settled_words[1] ) ),
		'open'   => array( substr_count( $mo_history, ': sent the acknowledgement, Ida Admin.' ), substr_count( $mo_history, ': made a mentor, by the site.' ) ),
	),
	array(
		'linked' => array( 1, 1, 1, 0 ),
		'gone'   => array( 1, 0, 1 ),
		'open'   => array( 1, 1 ),
	) );

reset_world();
$many = array();
for ( $i = 1; $i <= WPCPM_Mentor_Application_Queue::QUEUE_MAX + 1; $i++ ) {
	$many[] = get_post( an_application( 'active' ) );
}
WPCPM_Mentor_Activation::$activated = $many;
$cut                                = view_part( tab( 'active' ) );
ck( 'past fifty, Activated asks the list for one more than it draws, draws fifty, and says it was cut',
	array( WPCPM_Mentor_Activation::$limits, count( row_ids( $cut ) ), false !== strpos( $cut, 'Showing 50 of 51: those still waiting for their Airtable row first, then the most recently made mentors.' ) ),
	array( array( WPCPM_Mentor_Application_Queue::QUEUE_MAX + 1 ), 50, true ) );
WPCPM_Mentor_Activation::$activated = array_slice( $many, 0, 50 );
wp_delete_post( $many[50]->ID, true );
ck( 'and at fifty, all fifty with nothing said', array( count( row_ids( view_part( tab( 'active' ) ) ) ), false === strpos( view_part( tab( 'active' ) ), 'Showing ' ) ), array( 50, true ) );

echo "\n=== Send the acknowledgement ===\n";
reset_world();
$late = an_application( 'new', false, array(), array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING ) );
ck( 'a row in Waiting whose acknowledgement never went offers Send the acknowledgement', count_forms( view_part( tab() ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), 1 );
ck( 'and its press asks first', false !== strpos( view_part( tab() ), 'data-wpcpm-confirm="Send Ada Okonjo the acknowledgement, with the link that confirms the address?"' ), true );
$sent = press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $late );
ck( 'the press sends it once, with the confirming link, writes who sent it, and leaves the row in its state',
	array( $sent['flash'], count( $sent['mail'] ), $sent['mail'][0]['context'], last_event( $late ), $sent['state'] ),
	array( 'mapp-acknowledged', 1, 'mentor-application-received', array( 'acknowledgement sent', 9, '' ), 'new' ) );
ck( 'to the applicant, with the link that confirms the address', array( $sent['mail'][0]['to'], false !== strpos( $sent['mail'][0]['body'], WPCPM_Mentor_Application::verify_url( $late ) ) ), array( 'ada@example.test', true ) );
ck( 'after which the mark is gone, the press is not offered, and a second press sends nothing',
	array( false !== strpos( view_part( tab() ), 'The acknowledgement was not sent' ), count_forms( view_part( tab() ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $late )['flash'], press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $late )['mail'] ),
	array( false, 0, 'mapp-state', array() ) );
ck( 'and the row reads as unconfirmed until the applicant follows the link', array_column( marks_of( tab() ), 0 ), array( 'unconfirmed' ) );
press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $late );
ck( 'marked as spam and put back afterwards, it is sent nothing again', array_intersect_key( press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $late ), array_flip( array( 'flash', 'mail' ) ) ), array( 'flash' => 'mapp-reopened', 'mail' => array() ) );
reset_world();
an_application( 'new' );
ck( 'a row acknowledged when it came is offered no such press', count_forms( view_part( tab() ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), 0 );
$failing               = an_application( 'new', false, array(), array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_FAILED ) );
$GLOBALS['mail_fails'] = true;
ck( 'a hand-off that fails leaves the row as it was', array( press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $failing )['flash'], last_event( $failing )[0] ), array( 'mapp-not-sent', 'submitted' ) );
ck( 'and lets go of the application\'s lock all the same', array_key_exists( WPCPM_Mentor_Application::lock_name( $failing ), $GLOBALS['opts'] ), false );
$GLOBALS['mail_fails'] = false;

// Two presses at one moment: the second finds the application's lock held, and sends nothing.
reset_world();
$twice     = an_application( 'new', false, array(), array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING ) );
$held_till = (string) ( time() + 600 );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $twice ) ] = $held_till;
$busy_ack = press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $twice );
ck( 'Send the acknowledgement while the application\'s lock is held sends nothing, writes nothing, and says another request is working on it',
	array( $busy_ack['flash'], carried(), $busy_ack['mail'], last_event( $twice )[0], $GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $twice ) ] ),
	array( 'mapp-busy', array( 'status' => 'mapp-busy', 'message' => 'Another request is working on this application at this moment, so nothing was done.' ), array(), 'submitted', $held_till ) );

// The first press sends in the moment between the second's first read of the row and its lock: the
// second, reading the row again under the lock, finds the acknowledgement sent.
reset_world();
$raced = an_application( 'new', false, array(), array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING ) );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $raced ) ] = '1';
$GLOBALS['wpdb']->before_takeover = function () use ( $raced ) {
	WPCPM_Mentor_Application::add_event( $raced, WPCPM_Mentor_Application::EVENT_ACKNOWLEDGED, 9 );
};
$second_ack = press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $raced );
ck( 'and a press that finds, under the lock, the acknowledgement sent by another sends it no second time',
	array( $second_ack['flash'], $second_ack['mail'], count( array_keys( array_column( WPCPM_Mentor_Application::events( $raced ), 'event' ), WPCPM_Mentor_Application::EVENT_ACKNOWLEDGED, true ) ), array_key_exists( WPCPM_Mentor_Application::lock_name( $raced ), $GLOBALS['opts'] ) ),
	array( 'mapp-state', array(), 1, false ) );
$nowhere               = an_application( 'held', false, array( 'email' => '' ), array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING ) );
ck( 'a row with no address is offered no such press, and a press made all the same sends nothing',
	array( count_forms( row_for_id( view_part( tab() ), $nowhere ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), array_intersect_key( press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $nowhere ), array_flip( array( 'flash', 'mail' ) ) ) ),
	array( 0, array( 'flash' => 'mapp-no-email', 'mail' => array() ) ) );
$filed = an_application( 'spam', false, array(), array( 'honeypot' ) );
ck( 'and one filed as spam is offered Put back in the queue, not this press', count_forms( decided_part( tab() ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), 0 );

echo "\n=== Recently decided, and Put back in the queue ===\n";

reset_world();
$GLOBALS['now'] = time();
$filed          = an_application( 'spam', false, array( 'name' => 'Honey Example' ), array( 'honeypot' ) );
$declined       = an_application( 'declined' );
WPCPM_Mentor_Application::add_event( $filed, WPCPM_Mentor_Application::EVENT_SUBMITTED, 0, '', time() );
$decided        = decided_part( tab() );
preg_match_all( '#<li class="wpcpm-queue-item" id="wpcpm-mentor-application-(\d+)">#', $decided, $decided_rows );
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" /><input type="hidden" name="wpcpm_mapp_app" value="(\d+)" />#', $decided, $decided_forms, PREG_SET_ORDER );
ck(
	'under the views, Recently decided lists the row the honeypot filed as spam and the declined one, newest first, the first with Put back in the queue and marked as never acknowledged, the second with Select',
	array( array_map( 'intval', $decided_rows[1] ), array_map( function ( $f ) { return $f[1] . ' ' . $f[2]; }, $decided_forms ), substr_count( $decided, 'wpcpm-inst-mark--unacknowledged' ), false !== strpos( $decided, 'Filed as spam on ' ), false !== strpos( $decided, 'Declined on ' ) ),
	array( array( $filed, $declined ), array( 'wpcpm_mapp_reopen ' . $filed, 'wpcpm_mapp_select ' . $declined ), 1, true, true )
);
$GLOBALS['mail_fails'] = true;
$failed                = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $filed );
$GLOBALS['mail_fails'] = false;
ck( 'Put back in the queue sends that row the acknowledgement it never had, and a send that fails leaves the row as it was', array( $failed['state'], $failed['flash'], $failed['mail'] ), array( 'spam', 'mapp-not-sent', array() ) );
$reopened = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $filed );
ck(
	'sent, the row is new again, the acknowledgement queued once with the link that confirms the address, the mark gone, and the history says so, with who did it',
	array( $reopened['state'], $reopened['flash'], count( $reopened['mail'] ), $reopened['mail'][0]['context'] ?? null, $reopened['mail'][0]['to'] ?? null, false !== strpos( $reopened['mail'][0]['body'] ?? '', WPCPM_Mentor_Application::verify_url( $filed ) ), last_event( $filed ), substr_count( tab(), 'wpcpm-inst-mark--unacknowledged' ) ),
	array( 'new', 'mapp-reopened-sent', 1, 'mentor-application-received', 'ada@example.test', true, array( 'put back in the queue, acknowledgement sent', 9, '' ), 0 )
);
press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $filed );
$again = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $filed );
ck( 'filed as spam again and put back again, it is sent nothing the second time', array( $again['state'], $again['flash'], $again['mail'], last_event( $filed ) ), array( 'new', 'mapp-reopened', array(), array( 'put back in the queue', 9, '' ) ) );
$acknowledged = an_application( 'new' );
press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $acknowledged );
$mistake = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $acknowledged );
ck( 'and a row acknowledged when it came, marked as spam by mistake, is put back with nothing sent', array( $mistake['state'], $mistake['flash'], $mistake['mail'], last_event( $acknowledged ) ), array( 'new', 'mapp-reopened', array(), array( 'put back in the queue', 9, '' ) ) );
reset_world();
an_application( 'held', false, array(), array( 'mail-ceiling' ) );
ck( 'a row past the day\'s limit on acknowledgements is marked so, as one the checks filed as spam is', substr_count( tab(), 'wpcpm-inst-mark--unacknowledged' ), 1 );
reset_world();
ck( 'and with nothing decided, Recently decided says so', false !== strpos( decided_part( tab() ), 'No application has been filed as spam or declined.' ), true );

echo "\n=== Recently decided: by each row's own decision, newest first, bounded ===\n";

reset_world();
$base = time();
$day  = DAY_IN_SECONDS;
$a    = an_application();
$b    = an_application();
$c    = an_application();
$d    = an_application();
$e    = an_application();
$l    = an_application();
// Arrival is a, b, c, d, e. Decided: a three days ago, b and d a day ago in the same second, c two days ago, e five days ago
// (marked as spam by mistake, its address confirmed since, which is the newest thing that happened to every row here).
WPCPM_Mentor_Application::decide( $a, 'spam', WPCPM_Mentor_Application::EVENT_SPAM, 9, '', $base - 3 * $day );
WPCPM_Mentor_Application::decide( $b, 'declined', WPCPM_Mentor_Application::EVENT_DECLINED, 9, '', $base - 1 * $day );
WPCPM_Mentor_Application::decide( $c, 'spam', WPCPM_Mentor_Application::EVENT_SPAM, 9, '', $base - 2 * $day );
WPCPM_Mentor_Application::decide( $d, 'declined', WPCPM_Mentor_Application::EVENT_DECLINED, 9, '', $base - 1 * $day );
WPCPM_Mentor_Application::decide( $e, 'spam', WPCPM_Mentor_Application::EVENT_SPAM, 9, '', $base - 5 * $day );
WPCPM_Mentor_Application::decide( $l, 'lapsed', WPCPM_Mentor_Application::EVENT_WITHDRAWN, 9, 'They wrote that they cannot continue.', $base - 4 * $day );
$decided = decided_part( tab() );
ck( 'the rows are ordered by the day each was decided, newest first, a tie of a second by ID newest first: not by arrival, and not by the newest thing that happened to a row', row_ids( $decided ), array( $d, $b, $c, $a, $e ) );
$day_of = function ( $at ) {
	return wp_date( 'F j, Y', $at );
};
$ages = age_lines( $decided );
ck(
	'and a row\'s age line reads that day, so a row marked as spam by mistake whose applicant then confirmed the address still says when it was marked',
	array( $ages[ $e ], $ages[ $b ], $ages[ $a ] ),
	array( 'Filed as spam on ' . $day_of( $base - 5 * $day ) . '.', 'Declined on ' . $day_of( $base - 1 * $day ) . '.', 'Filed as spam on ' . $day_of( $base - 3 * $day ) . '.' )
);
ck( 'as a lapsed row\'s does', age_lines( view_part( tab( 'lapsed' ) ) )[ $l ], 'Lapsed on ' . $day_of( $base - 4 * $day ) . '.' );

reset_world();
$base = time();
$ids  = array();
// The later a row arrived, the more recently it was decided: the first to arrive is the oldest decision, and is the one left out.
for ( $i = 1; $i <= WPCPM_Mentor_Application_Queue::QUEUE_MAX + 1; $i++ ) {
	$ids[ $i ] = an_application();
	WPCPM_Mentor_Application::decide( $ids[ $i ], 'spam', WPCPM_Mentor_Application::EVENT_SPAM, 9, '', $base - ( WPCPM_Mentor_Application_Queue::QUEUE_MAX + 2 - $i ) * HOUR_IN_SECONDS );
}
$cut    = decided_part( tab() );
$listed = row_ids( $cut );
ck(
	'past fifty, the fifty newest decisions are listed, newest first, the oldest one left out, and the list says it was cut',
	array( count( $listed ), $listed[0] === $ids[51], $listed[49] === $ids[2], in_array( $ids[1], $listed, true ), false !== strpos( $cut, 'The 50 most recent applications filed as spam or declined, newest first.' ) ),
	array( 50, true, true, false, true )
);
wp_delete_post( $ids[1], true );
$whole = decided_part( tab() );
ck( 'at exactly fifty, all fifty are listed and nothing says it was cut', array( count( row_ids( $whole ) ), false === strpos( $whole, 'most recent' ), false !== strpos( $whole, 'The applications filed as spam and the declined ones, newest first, until they are deleted.' ) ), array( 50, true, true ) );

echo "\n=== The marks: a row whose acknowledgement never went says so once ===\n";

$none = ' The applicant has no link to confirm the address with, so Select, Ask for more information and Decline wait. Send the acknowledgement sends it now, once; for an application filed as spam, Put back in the queue does.';
$mark = function ( $cause ) use ( $none ) {
	return array( 'unacknowledged', 'acknowledgement not sent', 'The acknowledgement was not sent, because ' . $cause . '.' . $none );
};
// The held mark of a row that never had its acknowledgement does not say the applicant was acknowledged.
$held = array( 'held', 'held', 'The form\'s checks held it for a person to read first. Nobody was refused.' );
$rows = array(
	'held, past the day\'s limit'            => array( array( 'held', false, array( 'mail-ceiling' ) ), array( $held, $mark( 'the day\'s limit on acknowledgements had been reached' ) ) ),
	'held, its acknowledgement could not go' => array( array( 'held', false, array( 'links', 'mail-failed' ) ), array( $held, $mark( 'the email could not be handed off' ) ) ),
	'held, a duplicate, past the limit'      => array(
		array( 'held', false, array( 'duplicate', 'mail-ceiling' ) ),
		array(
			$held,
			array( 'duplicate', 'possible duplicate', 'Another application in the queue or waiting for the course has the same address or WordPress.org username. Nothing is ever merged.' ),
			$mark( 'the day\'s limit on acknowledgements had been reached' ),
		),
	),
	'filed as spam by the honeypot'          => array( array( 'spam', false, array( 'honeypot' ) ), array( $mark( 'the form\'s checks filed the application as spam' ) ) ),
	'filed as spam by the dwell check'       => array( array( 'spam', false, array( 'dwell' ) ), array( $mark( 'the form\'s checks filed the application as spam' ) ) ),
	'its acknowledgement could not go'       => array( array( 'new', false, array( 'mail-failed' ) ), array( $mark( 'the email could not be handed off' ) ) ),
	'a duplicate and a mentor, past the limit' => array(
		array( 'new', false, array( 'duplicate', 'mentor', 'mail-ceiling' ) ),
		array(
			array( 'duplicate', 'possible duplicate', 'Another application in the queue or waiting for the course has the same address or WordPress.org username. Nothing is ever merged.' ),
			array( 'account', 'already a mentor', 'A mentor the site knows, current or former, has this address or WordPress.org username.' ),
			$mark( 'the day\'s limit on acknowledgements had been reached' ),
		),
	),
	'held, acknowledged, address not confirmed' => array(
		array( 'held', false, array( 'links' ) ),
		array(
			array( 'held', 'held', 'The form\'s checks held it for a person to read first. Nobody was refused, and the applicant was acknowledged.' ),
			array( 'unconfirmed', 'address not confirmed', 'The applicant has not followed the link in the acknowledgement yet, so Select, Ask for more information and Decline wait.' ),
		),
	),
	'acknowledged and confirmed'             => array( array( 'new', true, array() ), array() ),
);
$printed = array();
$wanted  = array();
foreach ( $rows as $case => $about ) {
	reset_world();
	an_application( $about[0][0], $about[0][1], array(), $about[0][2] );
	$printed[ $case ] = marks_of( tab() );
	$wanted[ $case ]  = $about[1];
}
ck( 'the sentences of each: a row that never had its acknowledgement carries one mark about it, with its cause, and, when held, a held mark that does not say the applicant was acknowledged, and nothing of a link not followed; a row that was acknowledged keeps the held and unconfirmed sentences as they are', $printed, $wanted );

reset_world();
$filed = an_application( 'spam', false, array(), array( 'honeypot' ) );
press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $filed );
ck( 'and once Put back in the queue has sent the acknowledgement, that row says only that its address is not confirmed yet', array_column( marks_of( tab() ), 0 ), array( 'unconfirmed' ) );

reset_world();
$failed = an_application( 'new', false, array(), array( 'mail-failed' ) );
$marked = substr_count( tab(), 'wpcpm-inst-mark--unacknowledged' );
press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $failed );
$sent = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $failed );
ck(
	'a row whose acknowledgement the form could not hand off carries the mark, and once marked as spam, Put back in the queue sends it the acknowledgement it never had, and the mark goes',
	array( $marked, $sent['state'], $sent['flash'], count( $sent['mail'] ), $sent['mail'][0]['context'] ?? null, last_event( $failed ), substr_count( tab(), 'wpcpm-inst-mark--unacknowledged' ) ),
	array( 1, 'new', 'mapp-reopened-sent', 1, 'mentor-application-received', array( 'put back in the queue, acknowledgement sent', 9, '' ), 0 )
);

echo "\n=== The tab ===\n";

reset_world();
$GLOBALS['now'] = time();
$first   = an_application( 'held', false, array( 'name' => 'Cy <b>Tag</b>', 'anything' => "Line <script>x</script>\nTwo" ), array( 'links', 'duplicate', 'mentor' ) );
$asked   = an_application( 'info' );
$second  = an_application( 'second' );
$picked  = an_application( 'selected' );
$made    = an_application( 'active' );
$gone    = an_application( 'lapsed' );
$closed  = an_application( 'declined' );
$waiting = tab();

preg_match_all( '#<li class="([a-z]+)"><a href="([^"]*)"( class="current" aria-current="page")?>([^<]*) <span class="count">\((\d+)\)</span>#', $waiting, $views, PREG_SET_ORDER );
ck(
	'six views, each at its own address with its count; Waiting is the queue, its four open states, and Activated comes between Selected and Lapsed',
	array_map( function ( $v ) { return array( $v[1], $v[4], $v[5], '' !== $v[3] ); }, $views ),
	array( array( 'waiting', 'Waiting', '3', true ), array( 'info', 'Asked for more information', '1', false ), array( 'second', 'Second opinion', '1', false ), array( 'selected', 'Selected (course pending)', '1', false ), array( 'active', 'Activated', '1', false ), array( 'lapsed', 'Lapsed', '1', false ) )
);
ck( 'Activated is at its own address', WPCPM_Mentor_Application_Queue::view_url( 'active' ), 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue&mapp_view=active' );
ck(
	'the tab says what each decision sends, and how a selected applicant is made a mentor',
	false !== strpos( $waiting, '<p class="description">People who applied to mentor through the form on the site, oldest first. Select sends the mentors&#039; course invitation link; Ask for more information and Decline send their emails; Second opinion, Mark as spam and Withdraw send nothing. A decision that emails the applicant waits until they have confirmed the address. The course is completed, or the daily check finding the course on the profile, makes a selected applicant a mentor once Make mentors from applications is on.</p>' ),
	true
);
preg_match_all( '#<li class="wpcpm-queue-item" id="wpcpm-mentor-application-(\d+)">#', view_part( $waiting ), $listed );
ck( 'Waiting lists the queue oldest first, and nothing decided', array_map( 'intval', $listed[1] ), array( $first, $asked, $second ) );
ck(
	'a row says who and when, its answers escaped, the profile a link with the username, the address and whether it is confirmed',
	array( false !== strpos( $waiting, 'Cy &lt;b&gt;Tag&lt;/b&gt;' ), false === strpos( $waiting, '<b>Tag</b>' ), false !== strpos( $waiting, '<a href="https://profiles.wordpress.org/ada-example/" rel="noopener">ada-example</a>' ), false !== strpos( $waiting, 'ada@example.test (not confirmed yet)' ), false !== strpos( $waiting, 'ada@example.test (confirmed)' ), false !== strpos( $waiting, 'Line &lt;script&gt;x&lt;/script&gt;<br />' ), false !== strpos( $waiting, 'English, Polish' ), false !== strpos( $waiting, 'Agreed' ) ),
	array( true, true, true, true, true, true, true, true )
);
ck(
	'its marks: held, a possible duplicate, already a mentor, the address not confirmed; and an asked row when it was asked',
	array( substr_count( $waiting, 'wpcpm-inst-mark--held' ), substr_count( $waiting, 'wpcpm-inst-mark--duplicate' ), substr_count( $waiting, 'wpcpm-inst-mark--account' ), substr_count( $waiting, 'wpcpm-inst-mark--unconfirmed' ), substr_count( $waiting, 'Asked for more information on ' ) ),
	array( 1, 1, 1, 1, 1 )
);
ck(
	'the history names each decision, its day, who took it and the note',
	array( false !== strpos( $waiting, ': applied, by the site.' ), false !== strpos( $waiting, ': asked for more information, Ida Admin.' ), false !== strpos( $waiting, ': confirmed the address, by the site.' ) ),
	array( true, true, true )
);
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" /><input type="hidden" name="wpcpm_mapp_app" value="' . $first . '" />#', $waiting, $first_forms );
ck( 'an open row offers Select, Ask for more information, Second opinion, Decline and Mark as spam, in that order', $first_forms[1], array( 'wpcpm_mapp_select', 'wpcpm_mapp_info', 'wpcpm_mapp_second', 'wpcpm_mapp_decline', 'wpcpm_mapp_spam' ) );
ck( 'each form carries the nonce of its decision for its row, and the view to come back to', array( false !== strpos( $waiting, 'value="nonce-wpcpm_mapp_decline_' . $first . '"' ), substr_count( view_part( $waiting ), 'name="mapp_view" value="waiting"' ) ), array( true, 15 ) );

$selected_view = view_part( tab( 'selected' ) );
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" />#', $selected_view, $selected_forms );
ck( 'Selected lists the row waiting for the course, the day it lapses, and The course is completed and Withdraw alone', array( substr_count( $selected_view, 'class="wpcpm-queue-item"' ), false !== strpos( $selected_view, 'It lapses from ' ), $selected_forms[1] ), array( 1, true, array( 'wpcpm_mapp_complete', 'wpcpm_mapp_withdraw' ) ) );
$lapsed_view = view_part( tab( 'lapsed' ) );
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" />#', $lapsed_view, $lapsed_forms );
ck( 'Lapsed lists the lapsed row with Select offered again', array( substr_count( $lapsed_view, 'class="wpcpm-queue-item"' ), $lapsed_forms[1] ), array( 1, array( 'wpcpm_mapp_select' ) ) );

echo "\n=== Find by reference ===\n";

/**
 * The tab, drawn for a reference typed in the Find by reference field, at a view.
 *
 * @param string $reference What was typed.
 * @param string $view      The view.
 * @return string
 */
function tab_for( $reference, $view = 'waiting' ) {
	$_GET = array( 'mapp_ref' => $reference );

	if ( 'waiting' !== $view ) {
		$_GET[ WPCPM_Mentor_Application_Queue::VIEW_ARG ] = $view;
	}

	return run( array( 'WPCPM_Mentor_Application_Queue', 'render' ) )['html'];
}

/** What the lookup answered: the region the field's form lands on, from its opening to the views' bar. */
function found_part( $html ) {
	$at = strpos( $html, '<div id="wpcpm-mapp-found">' );

	if ( false === $at ) {
		return '';
	}

	$end = strpos( $html, '<ul class="subsubsub">', $at );

	return false === $end ? substr( $html, $at ) : substr( $html, $at, $end - $at );
}

/** The Find by reference form of a drawn tab: from its opening to its end. */
function find_form( $html ) {
	$at = strpos( $html, '<form method="get"' );

	return false === $at ? '' : substr( $html, $at, strpos( $html, '</form>', $at ) + 7 - $at );
}

/** The reference an application carries, as the row prints it. */
function reference_for( $id ) {
	return WPCPM_Mentor_Application::reference_of( get_post( $id ) );
}

reset_world();
$GLOBALS['now'] = time();
$queue          = array();

for ( $n = 0; $n < 51; $n++ ) {
	$queue[] = an_application();
}

$oldest      = $queue[0];
$fifty_first = $queue[50];
$plain       = tab();

// What the tab drew before the field: the oldest fifty of the queue, and a note that it was cut.
ck( 'the queue draws its oldest fifty, and says it showed fifty of 51',
	array( row_ids( view_part( $plain ) ) === array_slice( $queue, 0, 50 ), false !== strpos( $plain, 'Showing the oldest 50 of 51.' ) ),
	array( true, true ) );
ck( 'with no reference typed, nothing is answered, and an empty reference answers nothing either',
	array( found_part( $plain ), found_part( tab_for( '' ) ), found_part( tab_for( '   ' ) ), row_ids( view_part( tab_for( '' ) ) ) === array_slice( $queue, 0, 50 ) ),
	array( '', '', '', true ) );

$by_reference = tab_for( reference_for( $fifty_first ) );
$answered     = found_part( $by_reference );

ck( 'the 51st application, which the queue does not draw, is drawn by its reference, whole, with the decisions its state allows',
	array( row_ids( $answered ), count_forms( $answered, 'wpcpm_mapp_select' ), count_forms( $answered, 'wpcpm_mapp_spam' ), false !== strpos( $answered, reference_for( $fifty_first ) ), false !== strpos( $answered, 'ada@example.test (confirmed)' ) ),
	array( array( $fifty_first ), 1, 1, true, true ) );
ck( 'and the queue under it is drawn as it was: its oldest fifty, and the note that it was cut',
	array( row_ids( view_part( $by_reference ) ) === array_merge( array( $fifty_first ), array_slice( $queue, 0, 50 ) ), false !== strpos( $by_reference, 'Showing the oldest 50 of 51.' ) ),
	array( true, true ) );
ck( 'its row has the anchor the page lands on, and the field\'s form lands on the answer',
	array( false !== strpos( $answered, '<li class="wpcpm-queue-item" id="wpcpm-mentor-application-' . $fifty_first . '">' ), false !== strpos( $by_reference, '<form method="get" action="#wpcpm-mapp-found" class="wpcpm-mapp-find">' ) ),
	array( true, true ) );
ck( 'one of the fifty the queue does draw is drawn by the answer and not again below it, so no anchor is on the page twice',
	array( row_ids( found_part( tab_for( reference_for( $oldest ) ) ) ), array_count_values( row_ids( tab_for( reference_for( $oldest ) ) ) ) === array_fill_keys( array_slice( $queue, 0, 50 ), 1 ) ),
	array( array( $oldest ), true ) );
ck( 'white space around the reference is forgiven',
	row_ids( found_part( tab_for( '  ' . reference_for( $fifty_first ) . "\t" ) ) ),
	array( $fifty_first ) );

// Every application the tab lists is found, whatever its age and the view it is listed under: the open ones, selected,
// lapsed, declined (under Recently decided) and made a mentor (under Activated). One filed as spam is never found.
$states = array();

foreach ( array( 'new', 'held', 'info', 'second', 'selected', 'lapsed', 'spam', 'declined', 'active' ) as $state ) {
	$states[ $state ] = an_application( $state );
}

ck( 'a new, held, asked, second-opinion, selected and lapsed application is found',
	array_map(
		function ( $state ) use ( $states ) {
			return row_ids( found_part( tab_for( reference_for( $states[ $state ] ) ) ) ) === array( $states[ $state ] );
		},
		array( 'new', 'held', 'info', 'second', 'selected', 'lapsed' )
	),
	array( true, true, true, true, true, true ) );

// Recently decided is listed under every view, the declined row among its newest fifty: pinned before the field found it.
$decided_before = row_ids( decided_part( tab() ) );
ck( 'Recently decided lists the declined row and the spam row, newest first, as it did',
	array( in_array( $states['declined'], $decided_before, true ), in_array( $states['spam'], $decided_before, true ), count( $decided_before ) ),
	array( true, true, 2 ) );

$declined      = tab_for( reference_for( $states['declined'] ) );
$declined_part = found_part( $declined );

ck( 'a declined application is found: drawn whole, in the answer, with the decision its state allows, Select, and the Recently decided row is not drawn again',
	array( row_ids( $declined_part ), count_forms( $declined_part, 'wpcpm_mapp_select' ), count_forms( $declined_part, 'wpcpm_mapp_spam' ), false !== strpos( $declined_part, 'Declined on ' ), array_count_values( row_ids( $declined ) )[ $states['declined'] ] ),
	array( array( $states['declined'] ), 1, 0, true, 1 ) );
ck( 'and the rest of Recently decided is as it was, the spam row still in it',
	row_ids( decided_part( $declined ) ),
	array_values( array_diff( $decided_before, array( $states['declined'] ) ) ) );

// Made a mentor: the row sits under Activated, so the view is set to it, and it is drawn once, in the answer.
WPCPM_Mentor_Activation::$activated = array( get_post( $states['active'] ) );
$activated_view                     = tab_for( reference_for( $states['active'] ), 'active' );
$activated_part                     = found_part( $activated_view );
$activated_plain                    = tab( 'active' );
WPCPM_Mentor_Activation::$activated = array();

ck( 'an application made a mentor is found: drawn whole in the answer, and once on the page when Activated lists it too',
	array( row_ids( $activated_part ), false !== strpos( $activated_part, 'wpcpm-mentor-application-' . $states['active'] ), array_count_values( row_ids( $activated_view ) )[ $states['active'] ], row_ids( view_part( $activated_plain ) ) ),
	array( array( $states['active'] ), true, 1, array( $states['active'] ) ) );
ck( 'and found from any other view, where Activated does not list it',
	array( row_ids( found_part( tab_for( reference_for( $states['active'] ) ) ) ), row_ids( found_part( tab_for( reference_for( $states['active'] ), 'lapsed' ) ) ) ),
	array( array( $states['active'] ), array( $states['active'] ) ) );

$spam = tab_for( reference_for( $states['spam'] ) );

ck( 'an application filed as spam is not drawn by its reference: the answer is one sentence, and nothing of the application is in it',
	array( row_ids( found_part( $spam ) ), trim( strip_tags( found_part( $spam ) ) ), false !== strpos( found_part( $spam ), 'Ada Okonjo' ) ),
	array( array(), 'No application was found for that reference.', false ) );
ck( 'and the sentence is the one a reference nobody has gets, so the answer does not say that a spam row exists; its row is where it was, under Recently decided, once',
	array( found_part( $spam ) === found_part( tab_for( 'MAPP-2026-9999' ) ), row_ids( decided_part( $spam ) ) === $decided_before, array_count_values( row_ids( $spam ) )[ $states['spam'] ] ),
	array( true, true, 1 ) );

// A row whose state is empty or is none of the nine is not found either.
$odd_empty   = an_application();
$odd_unknown = an_application();
update_post_meta( $odd_empty, WPCPM_Mentor_Application::META_STATE, '' );
update_post_meta( $odd_unknown, WPCPM_Mentor_Application::META_STATE, 'archived' );

ck( 'an application with no state, or a state that is none of the nine, is not found',
	array( row_ids( found_part( tab_for( reference_for( $odd_empty ) ) ) ), trim( strip_tags( found_part( tab_for( reference_for( $odd_empty ) ) ) ) ), row_ids( found_part( tab_for( reference_for( $odd_unknown ) ) ) ), trim( strip_tags( found_part( tab_for( reference_for( $odd_unknown ) ) ) ) ) ),
	array( array(), 'No application was found for that reference.', array(), 'No application was found for that reference.' ) );

$unknown = tab_for( 'MAPP-2026-9999' );

ck( 'a reference nobody has says so in one sentence and draws no row in the answer, the queue under it unchanged',
	array( row_ids( found_part( $unknown ) ), trim( strip_tags( found_part( $unknown ) ) ), row_ids( view_part( $unknown ) ) === row_ids( view_part( $plain ) ) ),
	array( array(), 'No application was found for that reference.', true ) );

$hostile = tab_for( 'MAPP" autofocus onfocus="x' );

ck( 'what was typed cannot break out of the field, which keeps it, escaped, so it can be corrected, and the answer does not repeat it',
	array( false === strpos( $hostile, 'autofocus onfocus="' ), false !== strpos( $hostile, 'value="MAPP&quot; autofocus onfocus=&quot;x"' ), trim( strip_tags( found_part( $hostile ) ) ) ),
	array( true, true, 'No application was found for that reference.' ) );

// The field: a GET form that changes nothing, which keeps the view in force.
$field = tab_for( '' );
ck( 'the field is a GET form to the tab, with its label, the search box and the button, no nonce, and no view named while the Waiting view is shown',
	find_form( $field ),
	'<form method="get" action="#wpcpm-mapp-found" class="wpcpm-mapp-find"><input type="hidden" name="page" value="wpcpm-mentors" /><input type="hidden" name="tab" value="queue" /><label for="wpcpm-mapp-ref">Find by reference</label> <input type="search" id="wpcpm-mapp-ref" name="mapp_ref" value="" /> <button type="submit" class="button">Find</button></form>' );
$lapsed_field = tab_for( reference_for( $states['lapsed'] ), 'lapsed' );
ck( 'in another view the form names it, so the view stays, and keeps what was typed',
	find_form( $lapsed_field ),
	'<form method="get" action="#wpcpm-mapp-found" class="wpcpm-mapp-find"><input type="hidden" name="page" value="wpcpm-mentors" /><input type="hidden" name="tab" value="queue" /><input type="hidden" name="mapp_view" value="lapsed" /><label for="wpcpm-mapp-ref">Find by reference</label> <input type="search" id="wpcpm-mapp-ref" name="mapp_ref" value="' . reference_for( $states['lapsed'] ) . '" /> <button type="submit" class="button">Find</button></form>' );
ck( 'and the row found there comes back to that view when a decision is taken on it',
	array( row_ids( found_part( $lapsed_field ) ), substr_count( found_part( $lapsed_field ), 'name="mapp_view" value="lapsed"' ), count_forms( found_part( $lapsed_field ), 'wpcpm_mapp_select' ) ),
	array( array( $states['lapsed'] ), 1, 1 ) );

// The field's spacing (1.123.48), as the browser measured it: the description above it ends 5px
// above the form (core's p.description, bottom margin 5px), the views' bar is a float 8px below the
// form's own margin, and the answer's sentence is in a block with no margin of its own under it. The form's
// margins are the one rule that sets all three gaps: a top margin of 12px meets the description's 5px and
// the larger wins (12px, at every width); a bottom margin of 12px is 12px above the sentence, whose own top
// margin is the smaller, and 12px beside the float's 8px (a float's margin never collapses) for 20px.
$stylesheet = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' );
$rule       = 1 === preg_match( '/\n\.wpcpm-mapp-find\s*\{([^}]*)\}/', $stylesheet, $found_rule ) ? preg_split( '/\s*;\s*/', trim( $found_rule[1] ), -1, PREG_SPLIT_NO_EMPTY ) : array();
ck( 'admin.css gives the Find by reference form 12px above and 12px below it, and sets nothing else on it: no float, padding or border to change the gaps under it',
	$rule,
	array( 'margin: 12px 0' ) );

$GLOBALS['manage'] = array();
ck( 'somebody without the capability is shown nothing, the field and the answer included',
	array( tab(), tab_for( reference_for( $fifty_first ) ) ),
	array( '', '' ) );

// A list is drawn only when a row remains in it once the answer has the row it found.
reset_world();
$GLOBALS['now'] = time();
$alone          = an_application( 'selected' );
$alone_page     = tab_for( reference_for( $alone ), 'selected' );

ck( 'a view whose one row is the application found draws it once, in the answer, and no empty list under the views',
	array( row_ids( $alone_page ), substr_count( $alone_page, '<ol class="wpcpm-queue">' ), false === strpos( $alone_page, 'Nothing here.' ) ),
	array( array( $alone ), 1, true ) );

$only_declined = an_application( 'declined' );
$decided_page  = tab_for( reference_for( $only_declined ) );

ck( 'and so does Recently decided when the application found is the only one in it, the section and its note still there',
	array( row_ids( $decided_page ), substr_count( $decided_page, '<ol class="wpcpm-queue">' ), false !== strpos( $decided_page, '<section class="wpcpm-mapp-decided">' ), false === strpos( $decided_page, 'No application has been filed as spam or declined.' ) ),
	array( array( $only_declined ), 1, true, true ) );

reset_world();
ck( 'a view with nothing in it says so', false !== strpos( tab(), 'Nothing here. New applications appear in Waiting.' ), true );
$GLOBALS['manage'] = array();
an_application();
ck( 'and somebody without the capability is shown nothing', tab(), '' );
ck( 'nothing was asked of WordPress that the stand-ins do not model', array( isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), $GLOBALS['wpdb']->unmodeled ), array( array(), array() ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
