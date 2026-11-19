<?php
/**
 * The talks with a school, read (Institutions off Airtable, release 2): what the Talks card, the Talks view
 * and the weekly email read of one school or of all of them, from the index's row and the school's record
 * on the site.
 *
 * What is pinned here and why:
 *
 * - **The names later tasks write by.** The seven record keys the talks are kept under, the record key of
 *   the application's answers, the two history kinds, and the Talks view's name and its lead argument.
 * - **`facts()` is one school's talks:** the index row's name, stage, country, city, website and contact,
 *   the assigned Administrator, the seven keys and the application's answers. A key the index does not
 *   hold, a key of neither shape and a lead key the site keeps no lead for are null (the leads are
 *   bin/test-institution-leads.php's). An institution is never closed and keeps no country as text.
 * - **`all_facts()` reads every record in one go** (one `answers_for()` read, counted), keyed like the index.
 * - **The words survive the round trip** through the record, in Polish, Romanian, Arabic and Japanese,
 *   compared byte for byte.
 * - **`stages()`** is Lead, then the agreement's order, then the three that close the talks; **`closed()`**
 *   is those three.
 * - **`url()`** is a school's Talks card through `card_url()`, a lead's page on the Administrator
 *   Dashboard's Talks view, and '' for anything else.
 * - **Wired in:** both files are required by the loader and by uninstall.php, the module boots the notes and
 *   its uninstall deletes them.
 *
 * Run from the plugin root:  php bin/test-institution-talks.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['posts']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['opts']    = array();
$GLOBALS['answers'] = 0;
$GLOBALS['card']    = array();

class WP_Error {
	public function __construct( $code = '', $message = '' ) {}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

function update_meta_cache( $type, $ids ) {}

function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

// The shared post stand-in records each get_posts() call; the records' reads are counted from it.
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/stubs/institution-card-url.php';

/** The Institution Dashboard's addresses, as the real class builds them, for one page address. */
class WPCPM_Institutions_Dashboard {
	public static $page = 'https://example.test/institution-dashboard/';
	public static function card_url( string $card, string $record = '', string $item = '', array $args = array() ): string {
		$GLOBALS['card'][] = array( $card, $record, $item, $args );
		return wpcpm_test_institution_card_url( self::$page, $card, $record, $item, $args );
	}
}

/** The Administrator Dashboard's view addresses, as the real class builds them. */
class WPCPM_Administrators_Dashboard {
	const VIEW_ARG = 'wpcpm_view';
	public static $page = 'https://example.test/administrator-dashboard/';
	public static function view_url( $view = 'overview' ) {
		if ( '' === self::$page || 'overview' === $view ) {
			return self::$page;
		}
		return add_query_arg( self::VIEW_ARG, sanitize_key( (string) $view ), self::$page );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php';

$total = 0;
$fails = 0;

function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;

	if ( $actual === $expected ) {
		echo "  ok   {$label}\n";
		return;
	}

	++$fails;
	echo "  FAIL {$label}\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

/** How many reads of the records' posts were made since the count was last taken. */
function record_reads() {
	$n = 0;

	foreach ( isset( $GLOBALS['get_posts_calls'] ) ? $GLOBALS['get_posts_calls'] : array() as $args ) {
		if ( isset( $args['post_type'] ) && WPCPM_Institution_Record::POST_TYPE === $args['post_type'] ) {
			++$n;
		}
	}

	$GLOBALS['get_posts_calls'] = array();

	return $n;
}

/** An index row, shaped as the index keeps one. */
function row( $id, $name, $stage, array $more = array() ) {
	return array_merge(
		WPCPM_Institutions_Index::empty_row(),
		array(
			'record_id' => $id,
			'name'      => $name,
			'stage'     => $stage,
		),
		$more
	);
}

$deck   = 'recEXA00000000001';
$quiet  = 'recEXA00000000002';
$closed = 'recEXA00000000003';

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array(
	'v'    => WPCPM_Institutions_Index::VERSION,
	'read' => 1763546400,
	'rows' => array(
		$deck   => row(
			$deck,
			'Example University ',
			'Info Sent',
			array(
				'country'        => 'recCTY00000000001',
				'country_name'   => 'Exampleland',
				'city'           => 'Example City',
				'website'        => 'https://university.example',
				'contact_person' => 'Example Contact',
				'contact_email'  => 'contact@university.example',
			)
		),
		$quiet  => row( $quiet, 'Example College', 'First Contact Made' ),
		$closed => row( $closed, 'Example Institute', 'SPAM' ),
	),
);

echo "=== The names later tasks write by ===\n";

ck( 'the eight record keys, the Program details last (19 Nov 2026)', WPCPM_Institution_Talks::RECORD_KEYS, array( 'estimated_students', 'first_students', 'next_step', 'follow_up', 'last_contact', 'help_desk', 'lead_owner', 'program_details' ) );
ck(
	'the application\'s answers, the two history kinds, the view and its lead argument, and the Lead stage',
	array( WPCPM_Institution_Talks::RECORD_APPLICATION, WPCPM_Institution_Talks::LOG_STAGE, WPCPM_Institution_Talks::LOG_SAVED, WPCPM_Institution_Talks::VIEW_TALKS, WPCPM_Institution_Talks::ARG_LEAD, WPCPM_Institution_Talks::STAGE_LEAD ),
	array( 'application_answers', 'talks_stage', 'talks_saved', 'talks', 'wpcpm_lead', 'Lead' )
);

echo "\n=== The stages ===\n";

ck(
	'Lead, then the agreement\'s order, then the three that close the talks',
	WPCPM_Institution_Talks::stages(),
	array( 'Lead', 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Confirmed', 'Student', 'Not Moving Forward', 'SPAM', 'Revisit Later' )
);
ck( 'closed() is the three', WPCPM_Institution_Talks::closed(), array( 'Not Moving Forward', 'SPAM', 'Revisit Later' ) );
ck( 'and they are the agreement\'s own list', WPCPM_Institution_Talks::closed(), WPCPM_Institution_Agreement::TERMINAL_STAGES );

echo "\n=== facts() is one school's talks ===\n";

WPCPM_Institution_Record::set(
	$deck,
	array(
		'next_step'           => 'Send the deck',
		'follow_up'           => '2026-11-04',
		'estimated_students'  => '40',
		'assigned'            => 7,
		'contact_role'        => 'Registrar',
		'application_answers' => array( 'department' => 'Computer Science' ),
	)
);
record_reads();

$facts = WPCPM_Institution_Talks::facts( $deck );
ck(
	'the row\'s name, trimmed, and stage, with the record\'s next step and follow-up',
	array( $facts['name'], $facts['stage'], $facts['next_step'], $facts['follow_up'] ),
	array( 'Example University', 'Info Sent', 'Send the deck', '2026-11-04' )
);
ck(
	'every key, in one shape',
	$facts,
	array(
		'key'                 => $deck,
		'lead'                => false,
		'closed'              => false,
		'name'                => 'Example University',
		'stage'               => 'Info Sent',
		'country'             => 'recCTY00000000001',
		'country_name'        => 'Exampleland',
		'country_text'        => '',
		'city'                => 'Example City',
		'website'             => 'https://university.example',
		'contact_person'      => 'Example Contact',
		'contact_email'       => 'contact@university.example',
		'assigned'            => 7,
		'estimated_students'  => '40',
		'first_students'      => '',
		'next_step'           => 'Send the deck',
		'follow_up'           => '2026-11-04',
		'last_contact'        => '',
		'help_desk'           => '',
		'lead_owner'          => '',
		'program_details'     => '',
		'application_answers' => array( 'department' => 'Computer Science' ),
	)
);
ck( 'one read of the record', record_reads(), 1 );

$bare = WPCPM_Institution_Talks::facts( $quiet );
ck(
	'a school with no record has its row and blanks',
	array( $bare['name'], $bare['stage'], $bare['assigned'], $bare['next_step'], $bare['application_answers'] ),
	array( 'Example College', 'First Contact Made', 0, '', array() )
);
ck( 'a key the index does not hold is null', WPCPM_Institution_Talks::facts( 'recEXA00000000099' ), null );
ck( 'and so is a key of neither shape', WPCPM_Institution_Talks::facts( 'anything' ), null );
ck( 'and so is a lead key the site keeps no lead for (bin/test-institution-leads.php reads the leads)', WPCPM_Institution_Talks::facts( 'lead-5' ), null );

echo "\n=== all_facts() reads every record in one go ===\n";

WPCPM_Institution_Record::set( $closed, array( 'next_step' => 'Nothing more' ) );
record_reads();

$all = WPCPM_Institution_Talks::all_facts();
ck( 'every index row, keyed like the index', array_keys( $all ), array( $deck, $quiet, $closed ) );
ck( 'with one read of the records for all of them', record_reads(), 1 );
ck( 'each the same as facts() says of it', array( $all[ $deck ] === WPCPM_Institution_Talks::facts( $deck ), $all[ $quiet ] === WPCPM_Institution_Talks::facts( $quiet ), $all[ $closed ]['next_step'] ), array( true, true, 'Nothing more' ) );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = array();
record_reads();
ck( 'an empty index reads no record at all', array( WPCPM_Institution_Talks::all_facts(), record_reads() ), array( array(), 0 ) );

echo "\n=== The words survive the round trip ===\n";

$names = array( 'Wyższa Szkoła Górnołąćka', 'Universitatea Câmpiei Rîușițeni', 'جامعة الواحة الخضراء المتخيلة', '架空山学園大学' );
$rows  = array();

foreach ( $names as $i => $name ) {
	$id          = 'recEXA0000000002' . $i;
	$rows[ $id ] = row( $id, $name, 'Info Sent' );
}

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;

foreach ( $names as $i => $name ) {
	$id = 'recEXA0000000002' . $i;
	WPCPM_Institution_Record::set( $id, array( 'next_step' => 'Write to ' . $name, 'lead_owner' => $name ) );
	$back = WPCPM_Institution_Talks::facts( $id );
	ck( 'byte for byte: ' . bin2hex( substr( $name, 0, 6 ) ), array( bin2hex( $back['name'] ), bin2hex( $back['next_step'] ), bin2hex( $back['lead_owner'] ) ), array( bin2hex( $name ), bin2hex( 'Write to ' . $name ), bin2hex( $name ) ) );
}

echo "\n=== url() ===\n";

$GLOBALS['card'] = array();
// The card is the first of the For Administrators view since Task 15 (PROVISIONAL, owner question 1).
ck( 'a school\'s Talks card, through card_url(), on the For Administrators view', WPCPM_Institution_Talks::url( $deck ), 'https://example.test/institution-dashboard/?wpcpm_view=administrators&wpcpm_institution_view=' . $deck . '#wpcpm-talks' );
ck( 'asked of card_url() with the card and the school', $GLOBALS['card'], array( array( 'talks', $deck, '', array() ) ) );
ck( 'a lead\'s page is the Talks view with the lead', WPCPM_Institution_Talks::url( 'lead-5' ), 'https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=lead-5' );
ck( 'anything else has no address', array( WPCPM_Institution_Talks::url( 'anything' ), WPCPM_Institution_Talks::url( 'lead-0' ), WPCPM_Institution_Talks::url( '' ) ), array( '', '', '' ) );
ck( 'a key is read with the space around it trimmed', WPCPM_Institution_Talks::url( ' lead-5 ' ), 'https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=lead-5' );

WPCPM_Administrators_Dashboard::$page = '';
ck( 'a lead\'s page while the dashboard has no address is none', WPCPM_Institution_Talks::url( 'lead-5' ), '' );

echo "\n=== The lead key ===\n";

ck(
	'lead- and a post ID, nothing else',
	array_map( array( 'WPCPM_Institution_Talks', 'is_lead_key' ), array( 'lead-5', 'lead-12345', 'lead-0', 'lead-05', 'lead-', 'Lead-5', 'lead-5 ', 'lead-5a', $deck, 5, null ) ),
	array( true, true, false, false, false, false, false, false, false, false, false )
);

echo "\n=== Wired in ===\n";

$loader    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );
$uninstall = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'uninstall.php' );
$module    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );

foreach ( array( 'class-wpcpm-institution-talks.php', 'class-wpcpm-institution-talk-notes.php' ) as $file ) {
	ck( 'the loader and uninstall.php require ' . $file, array( false !== strpos( $loader, "includes/modules/{$file}'" ), false !== strpos( $uninstall, "includes/modules/{$file}'" ) ), array( true, true ) );
}

ck(
	'the module boots the notes and its uninstall deletes them',
	array(
		1 === preg_match( '/public function boot\(\) \{.*?WPCPM_Institution_Talk_Notes::init\(\);/s', $module ),
		1 === preg_match( '/public function uninstall\(\) \{.*?WPCPM_Institution_Talk_Notes::delete_all\(\);/s', $module ),
	),
	array( true, true )
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
