<?php
/**
 * The Waiting for review tab and its seven decisions (1.122.31): `WPCPM_Mentor_Application_Queue`.
 *
 * Design of 16 October 2026, section 5. What each block pins, and why:
 *
 * - **Who may press**: an Administrator, the capability asked before anything else, the nonce keyed
 *   to the decision and the row next, then the row, which must be an application in a state the
 *   decision takes. Every decision is registered for signed-in requests alone.
 * - **Each decision from each state**, every pair of the seven and the nine in one table: the state it
 *   moves to and its sentence, the email it sends or does not, or, from a state it does not take,
 *   the row left as it was and nothing sent; the row's history with who, when and the note; Select
 *   offered again on a lapsed or a declined row; Withdraw on a selected row alone; Put back in the
 *   queue on a spam row alone, sending the acknowledgement once to a row that never had it, and
 *   nothing to one acknowledged before.
 * - **The address is confirmed first** for every decision that emails the applicant, and Select
 *   needs the course invitation link; a send that fails leaves the row as it was.
 * - **A note** that is needed and too short, too long, or that the cleaner would take words from is
 *   refused, nothing done, and the typing comes back in that row's box.
 * - **The tab**: five views with their counts; each row whole, its answers escaped, its marks (the
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

$GLOBALS['mail']       = array();
$GLOBALS['mail_fails'] = false;
$GLOBALS['settings']   = array();

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
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
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
	$GLOBALS['uid']    = 9;
	$GLOBALS['manage'] = array( 9 );
	$_POST             = array();
	$_GET              = array();
	$_REQUEST          = array();
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
	'seven decisions, each for a signed-in request alone',
	array( array_values( array_filter( array_keys( $GLOBALS['hooks'] ), function ( $hook ) { return 0 === strpos( $hook, 'admin_post_' ); } ) ) ),
	array( array( 'admin_post_wpcpm_mapp_select', 'admin_post_wpcpm_mapp_info', 'admin_post_wpcpm_mapp_second', 'admin_post_wpcpm_mapp_decline', 'admin_post_wpcpm_mapp_spam', 'admin_post_wpcpm_mapp_withdraw', 'admin_post_wpcpm_mapp_reopen' ) )
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

// The states each decision takes, what it moves a row to, its sentence and how many emails it sends;
// from any other state it is refused, the row left as it was and nothing sent.
$takes = array(
	'wpcpm_mapp_select'   => array( array( 'new', 'held', 'info', 'second', 'lapsed', 'declined' ), 'selected', 'mapp-selected', 1 ),
	'wpcpm_mapp_info'     => array( array( 'new', 'held', 'info', 'second' ), 'info', 'mapp-info', 1 ),
	'wpcpm_mapp_second'   => array( array( 'new', 'held', 'info', 'second' ), 'second', 'mapp-second', 0 ),
	'wpcpm_mapp_decline'  => array( array( 'new', 'held', 'info', 'second' ), 'declined', 'mapp-declined', 1 ),
	'wpcpm_mapp_spam'     => array( array( 'new', 'held', 'info', 'second' ), 'spam', 'mapp-spam', 0 ),
	'wpcpm_mapp_withdraw' => array( array( 'selected' ), 'lapsed', 'mapp-withdrawn', 0 ),
	'wpcpm_mapp_reopen'   => array( array( 'spam' ), 'new', 'mapp-reopened', 0 ),
);
$pressed = array();
$wanted  = array();
foreach ( $takes as $action => $rule ) {
	foreach ( WPCPM_Mentor_Application::states() as $state ) {
		reset_world();
		$id                         = an_application( $state );
		$out                        = press( $action, $id, array( 'note' => 'A note long enough for every decision.' ) );
		$pressed[ $action ][ $state ] = $out['state'] . ' ' . $out['flash'] . ' ' . count( $out['mail'] );
		$wanted[ $action ][ $state ]  = in_array( $state, $rule[0], true ) ? $rule[1] . ' ' . $rule[2] . ' ' . $rule[3] : $state . ' mapp-state 0';
	}
}
ck( 'every decision from each of the nine states: where it takes the row, its sentence and its email; anywhere else the row as it was, mapp-state and nothing sent', array( array_keys( $takes ), $pressed ), array( array_keys( WPCPM_Mentor_Application_Queue::decisions() ), $wanted ) );

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

echo "\n=== A note refused for its words ===\n";

reset_world();
$id   = an_application();
$long = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => str_repeat( 'a', WPCPM_Mentor_Application_Queue::MAX_NOTE + 1 ) ) );
ck( 'a note longer than the box holds is refused, nothing sent', array( $long['flash'], $long['state'], $long['mail'] ), array( 'mapp-note-long', 'new', array() ) );
$loss = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => 'Kids <12 free, adults >18 pay' ) );
ck( 'and so is one the cleaner would take words from, its typing kept', array( $loss['flash'], $loss['state'], kept( $id, 'decline' ) ), array( 'mapp-note-loss', 'new', 'Kids <12 free, adults >18 pay' ) );
ck( 'every outcome a press leaves has a sentence', array_values( array_diff( array( 'mapp-selected', 'mapp-info', 'mapp-second', 'mapp-declined', 'mapp-spam', 'mapp-withdrawn', 'mapp-reopened', 'mapp-reopened-sent', 'mapp-unknown', 'mapp-state', 'mapp-unconfirmed', 'mapp-no-email', 'mapp-no-course-link', 'mapp-not-sent', 'mapp-note-short', 'mapp-note-long', 'mapp-note-loss' ), array_keys( WPCPM_Mentor_Application_Queue::messages() ) ) ), array() );

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

$none = ' The applicant has no link to confirm the address with, so Select, Ask for more information and Decline wait. Put back in the queue sends it to an application filed as spam, once.';
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
$gone    = an_application( 'lapsed' );
$closed  = an_application( 'declined' );
$waiting = tab();

preg_match_all( '#<li class="([a-z]+)"><a href="([^"]*)"( class="current" aria-current="page")?>([^<]*) <span class="count">\((\d+)\)</span>#', $waiting, $views, PREG_SET_ORDER );
ck(
	'five views, each at its own address with its count; Waiting is the queue, its four open states',
	array_map( function ( $v ) { return array( $v[1], $v[4], $v[5], '' !== $v[3] ); }, $views ),
	array( array( 'waiting', 'Waiting', '3', true ), array( 'info', 'Asked for more information', '1', false ), array( 'second', 'Second opinion', '1', false ), array( 'selected', 'Selected (course pending)', '1', false ), array( 'lapsed', 'Lapsed', '1', false ) )
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
ck( 'Selected lists the row waiting for the course, the day it lapses, and Withdraw alone', array( substr_count( $selected_view, 'class="wpcpm-queue-item"' ), false !== strpos( $selected_view, 'It lapses from ' ), $selected_forms[1] ), array( 1, true, array( 'wpcpm_mapp_withdraw' ) ) );
$lapsed_view = view_part( tab( 'lapsed' ) );
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" />#', $lapsed_view, $lapsed_forms );
ck( 'Lapsed lists the lapsed row with Select offered again', array( substr_count( $lapsed_view, 'class="wpcpm-queue-item"' ), $lapsed_forms[1] ), array( 1, array( 'wpcpm_mapp_select' ) ) );

reset_world();
ck( 'a view with nothing in it says so', false !== strpos( tab(), 'Nothing here. New applications appear in Waiting.' ), true );
$GLOBALS['manage'] = array();
an_application();
ck( 'and somebody without the capability is shown nothing', tab(), '' );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
