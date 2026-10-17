<?php
/**
 * The mentor application as the site keeps it (1.122.31): `WPCPM_Mentor_Application`.
 *
 * What this pins, and why:
 *
 * - **A private row nobody reaches but the Mentors screen.** The post type is fourteen characters,
 *   inside WordPress's twenty, and is public nowhere: not queried, not searched, not in the REST
 *   API, not in a menu, with a capability type no role holds.
 * - **The nine states of the design, and the four the queue lists** (section 3, decision 2).
 * - **A submission is stored whole.** The answers as typed, a backslash included; the reference
 *   `MAPP-<year>-<id>`, the year on the site's clock; the consent evidence; the checks' signals once
 *   each; the address only as a hash, lowercased; a row in the history; nobody as the author.
 * - **Every decision is a state and a dated row with who took it**, in one call, and the history
 *   reads oldest first, leaving out a row stored in another shape.
 * - **A decision's time is its own.** A row filed as spam or declined was decided when it was marked
 *   or declined, or, for a row the form's checks filed, when it arrived: never when the newest thing
 *   happened to it, so an address confirmed afterwards moves nothing. That time is kept as an index
 *   on the row while it is one of the two, and the decisions are read newest first, a tie of a
 *   second by ID newest first, bounded. A signal is added to a row once.
 * - **The confirming link is signed and bound to the address**: it carries the application and a
 *   signature, and the signature changes with the address's hash. Confirming stamps once.
 * - **The lists are oldest first, by ID within one second**, and a state that is not one reads no
 *   rows rather than every row.
 * - **The daily run** lapses a row selected longer ago than the days set (two weeks at least), with
 *   a dated note by the site, then deletes what retention says to: spam after its days, declined,
 *   lapsed and held rows after the rejected days, never a new, info, second, selected or active row
 *   while approved ones are kept for ever (the sponsor application's three settings, reused). The
 *   days of a spam, declined or lapsed row run from its decision, which an address confirmed
 *   afterwards does not move.
 * - **History survives retention**: what a row counted for the feed, by month on the site's clock,
 *   is kept when the row is deleted, and a spam row counts for nothing.
 *
 * Run from the plugin root:  php bin/test-mentor-application.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';

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

/** A clean site. */
function fresh() {
	$GLOBALS['opts']         = array();
	$GLOBALS['posts']        = array();
	$GLOBALS['pmeta']        = array();
	$GLOBALS['hooks']        = array();
	$GLOBALS['post_types']   = array();
	$GLOBALS['insert_fails'] = false;
	$GLOBALS['next_id']      = 500;
	$GLOBALS['now']          = gmmktime( 10, 0, 0, 10, 16, 2026 );
}

/** The answers a good application carries. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'    => 'Ada Example',
			'profile' => 'ada-example',
			'email'   => 'Ada@Example.test',
		),
		$more
	);
}

echo "=== The post type ===\n";

fresh();
WPCPM_Mentor_Application::init();
ck( 'init hooks the post type, and the daily run\'s place on the clock, on init', isset( $GLOBALS['hooks']['init'] ) ? $GLOBALS['hooks']['init'] : null, array( array( 'WPCPM_Mentor_Application', 'register_post_type' ), array( 'WPCPM_Mentor_Application', 'schedule' ) ) );

WPCPM_Mentor_Application::register_post_type();
$type = isset( $GLOBALS['post_types']['wpcpm_ment_app'] ) ? $GLOBALS['post_types']['wpcpm_ment_app'] : array();
ck( 'the post type is wpcpm_ment_app, inside the twenty characters register_post_type() allows', array( WPCPM_Mentor_Application::POST_TYPE, strlen( WPCPM_Mentor_Application::POST_TYPE ) <= 20 ), array( 'wpcpm_ment_app', true ) );
ck(
	'and public nowhere: not queried, not searched, no screen, no menu, no REST, no archive, no address, with a capability type no role holds',
	array( $type['public'], $type['publicly_queryable'], $type['exclude_from_search'], $type['show_ui'], $type['show_in_menu'], $type['show_in_rest'], $type['has_archive'], $type['rewrite'], $type['query_var'], $type['capability_type'] ),
	array( false, false, true, false, false, false, false, false, false, array( 'wpcpm_ment_app', 'wpcpm_ment_apps' ) )
);

echo "\n=== The states ===\n";

ck( 'nine states, in the design\'s order', WPCPM_Mentor_Application::states(), array( 'new', 'held', 'spam', 'info', 'second', 'selected', 'active', 'declined', 'lapsed' ) );
ck( 'and the queue lists four of them', WPCPM_Mentor_Application::open_states(), array( 'new', 'held', 'info', 'second' ) );

echo "\n=== Storing a submission ===\n";

fresh();
$evidence = array(
	'sentence' => 'I confirm.',
	'at'       => 1,
);
$id       = WPCPM_Mentor_Application::store( answers( array( 'anything' => 'C:\\path & "quotes" <b>' ) ), 'new', array( 'links', 'links', 'duplicate' ), $evidence );
$post     = get_post( $id );

ck( 'a submission is a private row of the type, authored by nobody, titled with the name', array( $id, $post->post_type, $post->post_status, $post->post_author, $post->post_title ), array( 500, 'wpcpm_ment_app', 'private', 0, 'Ada Example' ) );
ck( 'the answers are kept as typed, a backslash and a quote among them', WPCPM_Mentor_Application::fields_of( $post ), answers( array( 'anything' => 'C:\\path & "quotes" <b>' ) ) );
ck( 'its state, its reference on the site\'s clock, its consent and its signals once each', array( WPCPM_Mentor_Application::state_of( $post ), WPCPM_Mentor_Application::reference_of( $post ), get_post_meta( $id, WPCPM_Mentor_Application::META_CONSENT, true ), WPCPM_Mentor_Application::signals_of( $post ) ), array( 'new', 'MAPP-2026-0500', $evidence, array( 'links', 'duplicate' ) ) );
ck( 'the address only as a hash of it lowercased', get_post_meta( $id, WPCPM_Mentor_Application::META_EMAIL, true ), wp_hash( 'ada@example.test' ) );
ck( 'and one row in its history: submitted, by nobody', array_map( function ( $row ) { return array( $row['event'], $row['actor'], $row['note'] ); }, WPCPM_Mentor_Application::events( $id ) ), array( array( 'submitted', 0, '' ) ) );

$GLOBALS['now'] = gmmktime( 23, 30, 0, 12, 31, 2026 );
$late           = WPCPM_Mentor_Application::store( answers(), 'held', array(), array() );
ck( 'the reference takes the year of the site\'s clock, already 2027 at 23:30 UTC on December 31', WPCPM_Mentor_Application::reference_of( get_post( $late ) ), 'MAPP-2027-0501' );

$odd = WPCPM_Mentor_Application::store( answers( array( 'name' => '' ) ), 'selected', array(), array() );
ck( 'a state the form does not file in is held, and a row with no name is titled so', array( WPCPM_Mentor_Application::state_of( get_post( $odd ) ), get_post( $odd )->post_title ), array( 'held', 'Application with no name' ) );

$GLOBALS['insert_fails'] = true;
ck( 'when WordPress refuses the row, nothing is stored and the answer is 0', array( WPCPM_Mentor_Application::store( answers(), 'new', array(), array() ), count( $GLOBALS['posts'] ) ), array( 0, 3 ) );

echo "\n=== Decisions and their history ===\n";

fresh();
$id = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
ck( 'a decision moves the state and writes its row, with who and what they wrote', array( WPCPM_Mentor_Application::decide( $id, 'second', 'second opinion', 7, "Ask Ben.\nHe knows Polyglots.", 1000 ), WPCPM_Mentor_Application::state_of( get_post( $id ) ), array_slice( WPCPM_Mentor_Application::events( $id ), 0, 1 ) ), array( true, 'second', array( array( 'event' => 'second opinion', 'at' => 1000, 'actor' => 7, 'note' => "Ask Ben.\nHe knows Polyglots." ) ) ) );
ck( 'a state that is not one is refused and changes nothing', array( WPCPM_Mentor_Application::decide( $id, 'approved', 'approved', 7 ), WPCPM_Mentor_Application::state_of( get_post( $id ) ) ), array( false, 'second' ) );

add_post_meta( $id, WPCPM_Mentor_Application::META_EVENT, 'not a row' );
add_post_meta( $id, WPCPM_Mentor_Application::META_EVENT, array( 'event' => 'no time' ) );
ck( 'the history reads oldest first and leaves out a row stored in another shape', array_column( WPCPM_Mentor_Application::events( $id ), 'event' ), array( 'second opinion', 'submitted' ) );
WPCPM_Mentor_Application::add_event( $id, 'withdrawn', 7, '', 1000 );
ck( 'and rows of one second in the order they were written', array_column( WPCPM_Mentor_Application::events( $id ), 'event' ), array( 'second opinion', 'withdrawn', 'submitted' ) );
WPCPM_Mentor_Application::add_event( $id, 'selected', 7, '', 2000 );
WPCPM_Mentor_Application::add_event( $id, 'selected', 7, '', 3000 );
ck( 'the last time of an event, of any event, and of one that never happened', array( WPCPM_Mentor_Application::last_at( $id, 'selected' ), WPCPM_Mentor_Application::last_at( $id ) >= 3000, WPCPM_Mentor_Application::last_at( $id, 'declined' ) ), array( 3000, true, 0 ) );

echo "\n=== When a row was decided, and the decisions listed ===\n";

fresh();
$day  = DAY_IN_SECONDS;
$base = time();

// Marked as spam by mistake five days ago, and its applicant then followed the acknowledgement's link.
$mistake = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $mistake, 'spam', 'marked as spam', 7, '', $base - 5 * $day );
WPCPM_Mentor_Application::confirm( $mistake );
ck( 'a spam row was decided when it was marked as spam, and an address confirmed afterwards moves nothing', array( WPCPM_Mentor_Application::decided_at( get_post( $mistake ) ), WPCPM_Mentor_Application::last_at( $mistake ) > $base - 5 * $day ), array( $base - 5 * $day, true ) );

// Filed as spam by the checks: never marked, so decided when it arrived, whatever came after.
$filed   = WPCPM_Mentor_Application::store( answers(), 'spam', array( 'honeypot' ), array() );
$arrived = WPCPM_Mentor_Application::events( $filed )[0]['at'];
WPCPM_Mentor_Application::add_event( $filed, 'address confirmed', 0, '', $arrived + 1000 );
ck( 'a row the checks filed as spam was decided when it arrived, whatever happened to it after', WPCPM_Mentor_Application::decided_at( get_post( $filed ) ), $arrived );

// Marked, put back, marked again: the later marking.
$twice = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $twice, 'spam', 'marked as spam', 7, '', $base - 10 * $day );
WPCPM_Mentor_Application::decide( $twice, 'new', 'put back in the queue', 7, '', $base - 9 * $day );
WPCPM_Mentor_Application::decide( $twice, 'spam', 'marked as spam', 7, '', $base - 8 * $day );
ck( 'marked as spam again after it was put back, the decision is the later marking', WPCPM_Mentor_Application::decided_at( get_post( $twice ) ), $base - 8 * $day );

$no = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $no, 'declined', 'declined', 7, '', $base - 3 * $day );
$gone = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $gone, 'selected', 'selected', 7, '', $base - 9 * $day );
WPCPM_Mentor_Application::decide( $gone, 'lapsed', 'withdrawn', 7, '', $base - 4 * $day );
WPCPM_Mentor_Application::decide( $gone, 'selected', 'selected', 7, '', $base - 3 * $day );
WPCPM_Mentor_Application::decide( $gone, 'lapsed', 'lapsed', 0, '', $base - 2 * $day );
$open = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
ck( 'a declined row was decided when it was declined, a lapsed one at its newest lapse or withdrawal, and a row still open has no decision', array( WPCPM_Mentor_Application::decided_at( get_post( $no ) ), WPCPM_Mentor_Application::decided_at( get_post( $gone ) ), WPCPM_Mentor_Application::decided_at( get_post( $open ) ) ), array( $base - 3 * $day, $base - 2 * $day, 0 ) );

ck( 'spam and declined are the two states decided', WPCPM_Mentor_Application::decided_states(), array( 'spam', 'declined' ) );
ck(
	'the decision time is kept on the row while it is spam or declined: at arrival for a row the checks filed, at each marking and decline, and on none still open',
	array( (int) get_post_meta( $filed, WPCPM_Mentor_Application::META_DECIDED, true ), (int) get_post_meta( $mistake, WPCPM_Mentor_Application::META_DECIDED, true ), (int) get_post_meta( $twice, WPCPM_Mentor_Application::META_DECIDED, true ), (int) get_post_meta( $no, WPCPM_Mentor_Application::META_DECIDED, true ), isset( $GLOBALS['pmeta'][ $open ][ WPCPM_Mentor_Application::META_DECIDED ] ), isset( $GLOBALS['pmeta'][ $gone ][ WPCPM_Mentor_Application::META_DECIDED ] ) ),
	array( $arrived, $base - 5 * $day, $base - 8 * $day, $base - 3 * $day, false, false )
);
WPCPM_Mentor_Application::decide( $mistake, 'new', 'put back in the queue', 7 );
WPCPM_Mentor_Application::decide( $no, 'selected', 'selected', 7, 'They appealed.' );
ck( 'and it goes when the row is put back in the queue or selected again', array( isset( $GLOBALS['pmeta'][ $mistake ][ WPCPM_Mentor_Application::META_DECIDED ] ), isset( $GLOBALS['pmeta'][ $no ][ WPCPM_Mentor_Application::META_DECIDED ] ) ), array( false, false ) );
WPCPM_Mentor_Application::decide( $no, 'declined', 'declined', 7, '', $base - 1 * $day );
ck( 'and comes back with the next decision', (int) get_post_meta( $no, WPCPM_Mentor_Application::META_DECIDED, true ), $base - 1 * $day );

fresh();
$base = time();
$ids  = function ( array $posts ) {
	return array_map( function ( $p ) { return $p->ID; }, $posts );
};
$a    = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$b    = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$c    = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$d    = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$wait = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$pick = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $a, 'spam', 'marked as spam', 7, '', $base - 3 * $day );
WPCPM_Mentor_Application::decide( $b, 'declined', 'declined', 7, '', $base - 1 * $day );
WPCPM_Mentor_Application::decide( $c, 'spam', 'marked as spam', 7, '', $base - 2 * $day );
WPCPM_Mentor_Application::decide( $d, 'declined', 'declined', 7, '', $base - 1 * $day );
WPCPM_Mentor_Application::decide( $pick, 'selected', 'selected', 7 );
$e    = WPCPM_Mentor_Application::store( answers(), 'spam', array( 'dwell' ), array() );
ck( 'the decisions come newest first, the tie of a second by ID newest first, and the open and selected rows not at all', $ids( WPCPM_Mentor_Application::decided_posts( 10 ) ), array( $e, $d, $b, $c, $a ) );
ck( 'bounded: the limit counts from the newest decision, and a limit below one still reads one row', array( $ids( WPCPM_Mentor_Application::decided_posts( 2 ) ), $ids( WPCPM_Mentor_Application::decided_posts( 0 ) ) ), array( array( $e, $d ), array( $e ) ) );
ck( 'a row without the index is not found, as a meta key is an inner join in core too', array( $ids( WPCPM_Mentor_Application::decided_posts( 10 ) ) === array( $e, $d, $b, $c, $a ), isset( $GLOBALS['pmeta'][ $wait ][ WPCPM_Mentor_Application::META_DECIDED ] ) ), array( true, false ) );

echo "\n=== A signal is added once ===\n";

fresh();
$id   = WPCPM_Mentor_Application::store( answers(), 'new', array( 'links' ), array() );
$meta = $GLOBALS['pmeta'][ $id ];
ck( 'a signal is added to the signals the checks raised, once', array( WPCPM_Mentor_Application::add_signal( $id, 'mail-failed' ), WPCPM_Mentor_Application::add_signal( $id, 'mail-failed' ), WPCPM_Mentor_Application::signals_of( get_post( $id ) ) ), array( true, false, array( 'links', 'mail-failed' ) ) );
$after = $GLOBALS['pmeta'][ $id ];
unset( $meta[ WPCPM_Mentor_Application::META_SIGNALS ], $after[ WPCPM_Mentor_Application::META_SIGNALS ] );
ck( 'and writes nothing else of the row', $after, $meta );
$page                 = new WP_Post();
$page->ID             = 42;
$page->post_type      = 'page';
$GLOBALS['posts'][42] = $page;
ck( 'an empty signal, and a post that is no application, add nothing', array( WPCPM_Mentor_Application::add_signal( $id, '  ' ), WPCPM_Mentor_Application::add_signal( 42, 'mail-failed' ), isset( $GLOBALS['pmeta'][42] ), WPCPM_Mentor_Application::signals_of( get_post( $id ) ) ), array( false, false, false, array( 'links', 'mail-failed' ) ) );

echo "\n=== Reading one application ===\n";

$page              = new WP_Post();
$page->ID          = 42;
$page->post_type   = 'page';
$GLOBALS['posts'][42] = $page;
ck( 'an ID that is a page is no application', array( WPCPM_Mentor_Application::application( 42 ), WPCPM_Mentor_Application::application( 999 ), WPCPM_Mentor_Application::application( $id ) instanceof WP_Post ), array( null, null, true ) );

$post = get_post( $id );
ck( 'the name, the address and the handle as stored, and the profile built from the handle', array( WPCPM_Mentor_Application::name_of( $post ), WPCPM_Mentor_Application::email_of( $post ), WPCPM_Mentor_Application::handle_of( $post ), WPCPM_Mentor_Application::profile_url( 'ada-example' ), WPCPM_Mentor_Application::profile_url( '' ) ), array( 'Ada Example', 'Ada@Example.test', 'ada-example', 'https://profiles.wordpress.org/ada-example/', '' ) );

$bad = WPCPM_Mentor_Application::store( answers( array( 'email' => 'not an address' ) ), 'new', array(), array() );
ck( 'an address that is not one reads as none', WPCPM_Mentor_Application::email_of( get_post( $bad ) ), '' );

echo "\n=== The address, confirmed by its link ===\n";

$link = WPCPM_Mentor_Application::verify_url( $id );
ck( 'the link goes to the confirming handler with the application and a signature', $link, 'https://example.test/wp-admin/admin-post.php?action=wpcpm_mentor_apply_verify&app=' . $id . '&t=' . WPCPM_Mentor_Application::verify_signature( $id ) );
$signed = WPCPM_Mentor_Application::verify_signature( $id );
update_post_meta( $id, WPCPM_Mentor_Application::META_EMAIL, WPCPM_Mentor_Application::email_hash( 'other@example.test' ) );
ck( 'and the signature is bound to the address: another address, another signature', $signed !== WPCPM_Mentor_Application::verify_signature( $id ), true );

ck( 'an address is not confirmed until the link is followed', WPCPM_Mentor_Application::is_confirmed( $id ), false );
ck( 'confirming stamps it once, with a row in the history', array( WPCPM_Mentor_Application::confirm( $id ), WPCPM_Mentor_Application::is_confirmed( $id ), WPCPM_Mentor_Application::confirm( $id ), count( array_keys( array_column( WPCPM_Mentor_Application::events( $id ), 'event' ), 'address confirmed', true ) ) ), array( true, true, false, 1 ) );

echo "\n=== The lists ===\n";

fresh();
$first  = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$second = WPCPM_Mentor_Application::store( answers(), 'held', array(), array() );
$GLOBALS['now'] -= DAY_IN_SECONDS;
$older  = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$GLOBALS['now'] += DAY_IN_SECONDS;
$picked = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $picked, 'selected', 'selected', 7 );
$spam = WPCPM_Mentor_Application::store( answers(), 'spam', array(), array() );

ck( 'the open ones, oldest first and by ID within one second', array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states() ) ), array( $older, $first, $second ) );
ck( 'with a ceiling', array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states(), 2 ) ), array( $older, $first ) );
ck( 'counted by state, and waiting means the four open states', array( WPCPM_Mentor_Application::count_in( array( 'selected' ) ), WPCPM_Mentor_Application::count_in( array( 'spam', 'selected' ) ), WPCPM_Mentor_Application::waiting_count() ), array( 1, 2, 3 ) );
ck( 'and a state that is not one reads no rows, never every row', array( WPCPM_Mentor_Application::applications( array( 'approved' ) ), WPCPM_Mentor_Application::count_in( array() ) ), array( array(), 0 ) );

echo "\n=== The daily run: the lapse clock, then retention ===\n";

$GLOBALS['settings'] = array();

/** The settings the daily run reads, as a check sets them. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** One application in a state, its last event that many days before the clock. */
function aged( $state, $days_ago, $event = '' ) {
	$id = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
	$GLOBALS['pmeta'][ $id ][ WPCPM_Mentor_Application::META_EVENT ][0]['at'] = $GLOBALS['now'] - ( $days_ago + 1 ) * DAY_IN_SECONDS;

	if ( 'new' !== $state ) {
		WPCPM_Mentor_Application::decide( $id, $state, '' !== $event ? $event : $state, 7, '', $GLOBALS['now'] - $days_ago * DAY_IN_SECONDS );
	}

	return $id;
}

fresh();
$GLOBALS['hooks'] = array();
$GLOBALS['cron']  = array();
WPCPM_Mentor_Application::init();
WPCPM_Mentor_Application::schedule();
ck( 'the daily run is hooked and put on the clock once a day, on every load where it is missing', array( isset( $GLOBALS['hooks']['wpcpm_mentor_applications_daily'] ), $GLOBALS['cron']['wpcpm_mentor_applications_daily']['every'] ?? null, in_array( array( 'WPCPM_Mentor_Application', 'schedule' ), $GLOBALS['hooks']['init'], true ) ), array( true, 'daily', true ) );

fresh();
$GLOBALS['settings'] = array( 'mentor_lapse_days' => 90 );
$young               = aged( 'selected', 89, 'selected' );
$old                 = aged( 'selected', 91, 'selected' );
$open                = aged( 'info', 400, 'information requested' );
ck( 'the clock lapses a row selected longer ago than the days set, and only that one', array( WPCPM_Mentor_Application::lapse( $GLOBALS['now'] ), WPCPM_Mentor_Application::state_of( get_post( $young ) ), WPCPM_Mentor_Application::state_of( get_post( $old ) ), WPCPM_Mentor_Application::state_of( get_post( $open ) ) ), array( 1, 'selected', 'lapsed', 'info' ) );
$lapsed_row = WPCPM_Mentor_Application::events( $old );
$lapsed_row = end( $lapsed_row );
ck( 'with a dated note in the history, written by the site', array( $lapsed_row['event'], $lapsed_row['actor'], $lapsed_row['note'] ), array( 'lapsed', 0, 'Selected on ' . wp_date( 'Y-m-d', $GLOBALS['now'] - 91 * DAY_IN_SECONDS ) . ', and the mentors\' course had not shown 90 days later.' ) );
WPCPM_Mentor_Application::decide( $old, 'selected', 'selected', 7, '', $GLOBALS['now'] );
ck( 'a row selected again starts its days again', array( WPCPM_Mentor_Application::lapse( $GLOBALS['now'] ), WPCPM_Mentor_Application::state_of( get_post( $old ) ) ), array( 0, 'selected' ) );
fresh();
$GLOBALS['settings'] = array( 'mentor_lapse_days' => 3 );
$recent              = aged( 'selected', 10, 'selected' );
ck( 'and a setting below two weeks is read as two weeks: a row selected ten days ago stays', array( WPCPM_Mentor_Application::lapse( $GLOBALS['now'] ), WPCPM_Mentor_Application::state_of( get_post( $recent ) ) ), array( 0, 'selected' ) );

fresh();
$GLOBALS['settings'] = array(
	'application_spam_days'     => 30,
	'application_rejected_days' => 365,
	'application_approved_days' => 0,
);
$kept    = array(
	'new'      => aged( 'new', 4000 ),
	'info'     => aged( 'info', 4000, 'information requested' ),
	'second'   => aged( 'second', 4000, 'second opinion' ),
	'selected' => aged( 'selected', 80, 'selected' ),
	'active'   => aged( 'active', 4000, 'activated' ),
	'spam'     => aged( 'spam', 29, 'marked as spam' ),
	'declined' => aged( 'declined', 364, 'declined' ),
);
$gone    = array(
	'spam'     => aged( 'spam', 31, 'marked as spam' ),
	'declined' => aged( 'declined', 366, 'declined' ),
	'lapsed'   => aged( 'lapsed', 366, 'lapsed' ),
	'held'     => aged( 'held', 366, 'submitted' ),
);
ck( 'retention deletes spam after its days, and declined, lapsed and held rows after the rejected days', WPCPM_Mentor_Application::purge( $GLOBALS['now'] ), 4 );
ck( 'and keeps every new, info and second row, a selected one, an active one while approved ones are kept for ever, and the rest inside their days', array( array_map( function ( $id ) { return null !== get_post( $id ); }, $kept ), array_map( function ( $id ) { return null !== get_post( $id ); }, $gone ) ), array( array_fill_keys( array_keys( $kept ), true ), array_fill_keys( array_keys( $gone ), false ) ) );
$log = WPCPM_Mentor_Application::deletion_log();
ck( 'each deletion leaves a reference, a state, the days and a time, and nothing a person wrote', array( count( $log ), array_keys( $log[0] ), $log[0]['state'], $log[0]['days'] ), array( 4, array( 'at', 'id', 'reference', 'state', 'days', 'actor' ), 'spam', 30 ) );

fresh();
$GLOBALS['settings'] = array( 'application_spam_days' => 30 );
$confirmed           = aged( 'spam', 31, 'marked as spam' );
WPCPM_Mentor_Application::add_event( $confirmed, WPCPM_Mentor_Application::EVENT_CONFIRMED, 0, '', $GLOBALS['now'] - 2 * DAY_IN_SECONDS );
ck( 'a spam row is deleted when its days have passed since it was filed, though its address was confirmed since: confirming does not restart them', array( WPCPM_Mentor_Application::last_at( $confirmed ) > $GLOBALS['now'] - 30 * DAY_IN_SECONDS, WPCPM_Mentor_Application::purge( $GLOBALS['now'] ), null === get_post( $confirmed ) ), array( true, 1, true ) );

echo "\n=== History survives retention ===\n";

fresh();
$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;
$GLOBALS['now']         = gmmktime( 9, 0, 0, 1, 10, 2027 );
$row                    = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::add_event( $row, 'selected', 7, '', gmmktime( 23, 30, 0, 1, 31, 2027 ) );
WPCPM_Mentor_Application::add_event( $row, 'withdrawn', 7, '', gmmktime( 10, 0, 0, 3, 2, 2027 ) );
WPCPM_Mentor_Application::decide( $row, 'lapsed', 'lapsed', 0, '', gmmktime( 10, 0, 0, 4, 2, 2027 ) );
$spam = WPCPM_Mentor_Application::store( answers(), 'spam', array(), array() );
$counts = WPCPM_Mentor_Application::month_counts_of( get_post( $row ) );
ck(
	'a row counts once where it arrived, and each selection, decline, withdrawal and lapse in its month on the site\'s clock: 23:30 UTC on January 31 is February there',
	$counts,
	array(
		'2027-01' => array( 'received' => 1, 'selected' => 0, 'activated' => 0, 'declined' => 0, 'lapsed' => 0 ),
		'2027-02' => array( 'received' => 0, 'selected' => 1, 'activated' => 0, 'declined' => 0, 'lapsed' => 0 ),
		'2027-03' => array( 'received' => 0, 'selected' => 0, 'activated' => 0, 'declined' => 0, 'lapsed' => 1 ),
		'2027-04' => array( 'received' => 0, 'selected' => 0, 'activated' => 0, 'declined' => 0, 'lapsed' => 1 ),
	)
);
ck( 'and a spam row counts for nothing', WPCPM_Mentor_Application::month_counts_of( get_post( $spam ) ), array() );
WPCPM_Mentor_Application::forget( get_post( $row ), 365, 0 );
WPCPM_Mentor_Application::forget( get_post( $spam ), 30, 0 );
ck( 'deleting a row keeps what it counted, by month, and a spam row adds nothing', WPCPM_Mentor_Application::kept_counts(), $counts );
$again = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$GLOBALS['now'] = gmmktime( 9, 0, 0, 2, 10, 2027 );
$later = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::forget( get_post( $again ), 365, 0 );
WPCPM_Mentor_Application::forget( get_post( $later ), 365, 0 );
ck( 'and a second deletion adds to the same months', array( WPCPM_Mentor_Application::kept_counts()['2027-01']['received'], WPCPM_Mentor_Application::kept_counts()['2027-02']['received'], WPCPM_Mentor_Application::kept_counts()['2027-02']['selected'] ), array( 2, 1, 1 ) );

echo "\n=== Uninstall ===\n";

fresh();
$GLOBALS['cron'] = array( 'wpcpm_mentor_applications_daily' => array( 'at' => 1, 'every' => 'daily' ) );
WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
update_option( WPCPM_Mentor_Application::OPT_LOG, array( array( 'id' => 1 ) ) );
update_option( WPCPM_Mentor_Application::OPT_KEPT, array( '2027-01' => array() ) );
WPCPM_Mentor_Application::delete_all();
ck( 'deleting everything removes every row, the log, the kept counts and the daily run', array( count( $GLOBALS['posts'] ), get_option( WPCPM_Mentor_Application::OPT_LOG ), get_option( WPCPM_Mentor_Application::OPT_KEPT ), isset( $GLOBALS['cron']['wpcpm_mentor_applications_daily'] ) ), array( 0, false, false, false ) );

ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
