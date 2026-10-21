<?php
/**
 * The annual sponsor report's store (1.115.0): the post type, the period, the snapshot, the
 * states, and the numbers, each defined once.
 *
 * What is pinned here and why:
 *
 * - The post type is `wpcpm_sponsor_report`, twenty characters, the most `register_post_type()`
 *   accepts, registered on `init` at 5 with the semester report's private arguments, a title and
 *   revisions and no editor; the narratives and the snapshot are revisioned meta, so every version
 *   of a report holds the words and the figures they were written against. The Sponsors module
 *   boots the store with its other classes.
 * - The period is the twelve months ending on the day a report is keyed by, reckoned at noon in
 *   UTC so that no timezone moves a day, and consecutive terms never share a day, around a leap day
 *   included. The title names the company and the two months the same way, whatever the site's
 *   own timezone, and the tier ladder's `format_day()`, which prints the Term row's two days, writes
 *   the day it is given on a site twelve or more hours ahead of UTC too.
 * - The numbers: every figure of the seeded program (started, finished, active, hours,
 *   contributions, teams, institutions, countries), each rule again at its edges on a roster of its
 *   own: no end day is still going for a current status alone (the `student_statuses` setting,
 *   seeded here so that emptying it proves it is read), a finished status with no end day is
 *   active only in the period it started in, and a row with no start day is active by the letter
 *   of the rule. The students the Students table files under no institution count, and add no
 *   institution. A track's figures are restricted to its status and carry no `finished`, since the
 *   record keeps no graduate's track. The sponsorship: the tier held on the period's last day, the
 *   funded line from Backer up, the mentors with the students each guided in the period, the claims
 *   by month inside the period and never outside it, the posts published or put dormant in it, the
 *   events the tier includes.
 * - The events held (1.117.0): the sponsorship lists the events the sponsor held in the period, the
 *   events store's own answer, over events the store itself planned, approved and filled on its own
 *   clock: soonest first, and never one called off, or one still to come on the day of the draft,
 *   which counts once it is over. The snapshot keeps each one's title, start, length and count and
 *   nothing else, and a snapshot drafted before the list existed reads whole at version 1, as none
 *   held. The printed report and the editor draw them as one table under one caption, right under
 *   the brief, each day on the site's clock in the site's own format, and nothing, not even a
 *   sentence, where none was held. The privacy walk looks for the people on them, their addresses,
 *   the call link and the description as well.
 * - The sections per tier, from the policy: the program and the sponsorship with `report_program`,
 *   the cost line with `report_cost_line`, one block per partner track with `report_track`, and
 *   looking ahead always.
 * - Generating refuses a sponsor the store does not hold, a value that is not a day, a second
 *   report for one period end, and a tier that carries no report on that day; `find()` finds the
 *   one there is and `reports_of()` lists them newest period first.
 * - One draft of a term at a time: `generate()` takes a lock per sponsor, the option
 *   `wpcpm_sreport_lock_` and the sponsor's key, before it looks for the report, and gives it back
 *   once the report and its keys are stored, so the daily run and a program manager's draft of one
 *   term store one report, and the second is told `busy`. The second draft is pressed inside the
 *   first one's insert, and again while its keys are written, each time as another request's step
 *   while the first holds the lock. A lock older than five minutes is taken over and a younger one
 *   honored; the lock is given back on every refusal, on an insert that fails and on an error
 *   thrown inside, and never taken for a value that is no key. It is read from the table itself,
 *   so a request whose own copy of the options says it is free while another request holds it is
 *   refused all the same, and the daily run refused as busy stamps nothing, so its next run drafts
 *   the term. Under it the report is looked for past this request's cache of the query, so a draft
 *   another request stored after this one last looked is found.
 * - The privacy walk: the stored snapshots, what `snapshot()` answers, every title and every tier's
 *   sections are walked for the seeded students' names, addresses, usernames and sites, for the
 *   institutions' names and their contacts, and for any address at all. The fixture hands the
 *   numbers rosters and mentee rows carrying every one of them, so the walk is about what the store
 *   leaves behind rather than about a fixture that never held it. The editor the Sponsors screen
 *   draws for each report is walked with them, and its program table must be the printed report's,
 *   row for row. So are the rows a report writes on the audit trail, their messages and their
 *   facts: the drafts' with the rest, and every kind once each has been written.
 * - The narratives are written meta first and post second, so the revision WordPress takes inside
 *   `wp_update_post()` holds the save; a save whose update is refused is stored all the same, and
 *   says so; a regeneration keeps them; a final report takes neither. Over core's cleaners, a
 *   narrative is counted as typed, kept whole at its limit and refused, never cut, one character
 *   over; the editor draws it back as typed with the shared rules' `maxlength`; posted back
 *   unedited it writes nothing, a held `&gt;` included; and the printed report prints the stored
 *   text as it always has, `wpautop( esc_html() )`, which reads as typed.
 * - The partners' narrative: a Partner's draft starts each track block with the text the track was
 *   last published with, read through the Track Builder's own store from the track's published
 *   copy, over posts the fixture plants, and never from its saved draft; kept as a manager's words
 *   are kept, a backslash and a quote whole and the tags gone; and stored before the sponsor's lock
 *   is given back. A track published with none leaves its block empty, the longest text a track
 *   holds fits a block whole and comes back whole from the editor's Save, which posts each line
 *   break as two characters, and a regeneration keeps the report's own copy, pulled or edited,
 *   whatever the track was published with since.
 * - The states: draft, final with its stamp, a draft again without it, each move a row on the
 *   sponsors module's audit trail; `queue()` and `final_since()` read them.
 * - The daily draft: `maybe_draft()` drafts the day after a term ends, once, stamps the row's
 *   `report_drafted_for` once a report for that end exists (its own draft stored, or one a manager
 *   made first), and never for a Friend, a paused one or a term still running; `due()` is
 *   the same rule, with no report for that end, reading every report once.
 * - The renewed term: a renewal made before a term ends leaves that term owed its report on the row
 *   (`report_owed`, the tier it held, its last day and its funded line), which the run, the due list and a manager's
 *   draft read first, so the Backer year is a Backer's report whatever the renewal made the row; the
 *   owed term is cleared once its report exists, and a regeneration keeps the tier the term held.
 *   `owed_of()` is how the Sponsors screen reads it, and `LOG_OWED_CLEARED` the kind its Not a
 *   renewal writes on the trail. A renewal a program manager saves from another request while the
 *   run drafts, after the run read the row, is kept: the run's stamp lands beside it, and the next
 *   run clears the owed term the renewal recorded, since its report exists.
 * - `delete_all()` removes every report and forgets the file attached to it, then every lock a
 *   request left behind, found by its prefix through the site's database object and escaped for
 *   LIKE, and nothing else.
 * - The PDF and the printed document (`WPCPM_Sponsor_Report_Files`): the four routes and their
 *   hooks, the two links answering a signed-out visitor too; the document's shape (one standalone
 *   page, the semester report's stylesheet inlined unless it carries the two characters that end a
 *   tag, its print script, a body class of its own that the stylesheet's sponsor rules alone are
 *   scoped to, the title as the heading, the sections in the order the tier's policy gives them,
 *   each generated section a captioned table of its figures, each narrative its paragraphs or
 *   "Nothing written yet.", one block per track), every value escaped, and no student in it,
 *   walked with the rest; the upload's checks, the agreement's, each refusal reaching nothing in
 *   the store; a good PDF stored with its hash and size, on a draft or a final report alike, a
 *   replacement forgetting the file it replaced, a failed store or a refused record keeping it, a
 *   removal forgetting it and both keys; the audit row of each; the download's six headers, sent
 *   once any stray buffered bytes are dropped, and its name, built from the sponsor's slug and the
 *   period's last day and never from the name the file came with; and who reads either route: the
 *   nonce first, so that no answer tells one id from another, then a manager always, a member of
 *   the report's sponsor once it is final and while the tiers are live, a signed-out visitor sent
 *   to log in, anyone else the one refusal.
 *
 * WordPress is a small store in memory: posts with their meta, a key `add_post_meta()` writes kept
 * as repeated rows the way core keeps the people on an event, a revision taken inside
 * `wp_update_post()` the way core takes it, the slash pair core applies to a post and to meta,
 * options with `add_option()` as the test-and-set the events store's lock and the report's take,
 * each read of a lock's row from the table, take, refusal and delete kept in a journal a check
 * reads, and core's checks of a call link. A request's own copy of the options, which on a site
 * with a persistent object cache can name a row absent that another request has written, is
 * modeled for the names a check lists as core's list of absent names (`notoptions`): `get_option()`
 * answers them absent, and `add_option()` skips its own look and writes over the row, as core's
 * `INSERT ... ON DUPLICATE KEY UPDATE` does and reports a win. The cache WP_Query keeps of a query
 * for the request is modeled for queries of reports while a check turns it on, and `$wpdb` for the
 * two statements the store sends: a lock's read of its row, and the sweep of its locks.
 * The stores the numbers read are the plugin's own classes over it: the roster
 * index, the institutions index, the sponsors store, the tier ladder, the program map, the linked
 * mentors, the claims counts, the Track Builder's compiled tracks, and the events store, with the
 * sessions' zone reader its fields are read through. What they read beside those, the
 * offers and their ledgers, the posts, the mentors sync and the mentees, is stood in for the calls
 * made, and so are the audit trail and the private files. The request is stood in as core reads it
 * (the query string, the posted fields, `$_REQUEST` and `$_FILES`), with nonces minted per action
 * and per account, so that a link minted for one report or one person opens nothing else; the
 * accounts and their sponsor stamps are the real membership and policy classes' to read; and a
 * route that sends a response ends at `nocache_headers()`, which reads what the send was handed,
 * since the CLI keeps no headers to read back. A run that ends before its last check fails, so a
 * sender that stopped reaching that stand-in cannot end the run with the status of a pass.
 *
 * Run from the plugin root:  php bin/test-sponsor-report.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );

date_default_timezone_set( 'UTC' );

$GLOBALS['opts']        = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['meta_rows']   = array();
$GLOBALS['next_id']     = 100;
$GLOBALS['hooks']       = array();
$GLOBALS['post_types']  = array();
$GLOBALS['post_meta']   = array();
// The statuses the program counts as current: WPCPM_Settings::defaults()'s list, which a check
// empties to prove the numbers read it.
$GLOBALS['settings']    = array( 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' ) );
$GLOBALS['today']       = '2026-10-01';
$GLOBALS['site_tz']     = 'UTC';
$GLOBALS['uid']         = 7;
$GLOBALS['audit']       = array();
$GLOBALS['forgotten']   = array();
$GLOBALS['deleted']     = array();
$GLOBALS['insert_fail'] = '';
$GLOBALS['update_fail'] = '';
$GLOBALS['meta_fail']   = '';
$GLOBALS['queries']     = 0;
$GLOBALS['dormant']     = array();
$GLOBALS['offers']      = array();
$GLOBALS['claims']      = array();
$GLOBALS['sposts']      = array();
$GLOBALS['mentors']     = array();
$GLOBALS['mentees']     = array();
// The accounts, their meta, and who holds the program's own capability (bin/stubs/caps.php).
$GLOBALS['users']       = array();
$GLOBALS['umeta']       = array();
$GLOBALS['manage']      = array( 7, 8 );
// The private store's files, every call it took, and whether the next store() fails.
$GLOBALS['files']       = array();
$GLOBALS['journal']     = array();
$GLOBALS['store_fails'] = false;
// What the routes flashed, what a send was handed, and every nonce action a handler checked.
$GLOBALS['flash']       = array();
$GLOBALS['sent']        = null;
$GLOBALS['nonce_asked'] = array();
// Every read of a lock's row, add_option() and delete_option(), in order: what a lock went
// through. The names this request's copy of the options holds as absent, which a check fills. The
// cache of the report queries, null while it is off. What was asked of the database stand-in and
// it does not model.
$GLOBALS['option_calls'] = array();
$GLOBALS['notoptions']   = array();
$GLOBALS['query_memo']   = null;
$GLOBALS['unmodeled']    = array();

/* ---- WordPress, stood in for the parts this code touches ------------------ */

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_name = '', $post_author = 0, $post_parent = 0, $post_date = '', $post_content = '';
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
// Every hook goes to the runner in bin/stubs/compiled-seeds.php (loaded with the classes below): the
// program map is made of the filters the tracks' reader hooks, and a hook with none hooked comes
// back as it was handed.
function apply_filters( $hook, $value, ...$args ) { return wpcpm_stub_apply_filters( $hook, $value, ...$args ); }
// The PDF check hooks a fence on `upload_mimes` for its one question and takes it off again
// (`WPCPM_Pdf_Check::named_pdf()`), so core's remove_filter() works on the runner's store too.
function remove_filter( $hook, $callback, $priority = 10 ) {
	$hooked = isset( $GLOBALS['wpcpm_stub_filters'][ (string) $hook ][ (int) $priority ] ) ? $GLOBALS['wpcpm_stub_filters'][ (string) $hook ][ (int) $priority ] : array();

	foreach ( $hooked as $i => $filter ) {
		if ( $filter[0] === $callback ) {
			unset( $GLOBALS['wpcpm_stub_filters'][ (string) $hook ][ (int) $priority ][ $i ] );

			return true;
		}
	}

	return false;
}
function sanitize_title( $title ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' ); }
// Core's two cleaners (bin/stubs/cleaners.php): the tags stripped and the ends trimmed, every line
// break left as posted, so a store that counts a browser's CRLF as two characters is caught rather
// than covered for, and a "<" that opens no tag written `&lt;` with the quote marks and ampersands
// after it, so a narrative counted, drawn or compared as stored is caught too.
require_once __DIR__ . '/stubs/cleaners.php';
function is_email( $value ) { return is_string( $value ) && (bool) filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }

// The real slash pair, and the stores below unslash what they are given as core's do: a post
// array through wp_insert_post() and wp_update_post(), a value through update_post_meta(). A
// name with a backslash in it comes back whole only from code that slashed it first.
function wp_slash( $value ) {
	if ( is_array( $value ) ) { return array_map( 'wp_slash', $value ); }
	return is_string( $value ) ? addslashes( $value ) : $value;
}
function wp_unslash( $value ) {
	if ( is_array( $value ) ) { return array_map( 'wp_unslash', $value ); }
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/**
 * The site's clock and timezone. A date the code reads for today is `$GLOBALS['today']`; anything
 * else is written in the zone handed over, else in the site's, which a check moves far from UTC.
 */
function wp_date( $format, $timestamp = null, $timezone = null ) {
	if ( null === $timestamp && 'Y-m-d' === $format ) { return $GLOBALS['today']; }
	$when = new DateTime( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );
	$when->setTimezone( $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( $GLOBALS['site_tz'] ) );
	return $when->format( (string) $format );
}

/**
 * An option, as this request reads it: a name its copy of the options holds as absent
 * (`$GLOBALS['notoptions']`) is answered absent, whatever the table holds, as core answers from the
 * list before it asks the cache or the table.
 */
function get_option( $name, $default = false ) {
	if ( isset( $GLOBALS['notoptions'][ $name ] ) ) { return $default; }
	return array_key_exists( $name, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) { $GLOBALS['opts'][ $name ] = $value; return true; }
/**
 * A delete, journaled. Another request's step may run once as a named option is deleted, before it
 * goes: what that request finds at the moment a lock is given back.
 */
function delete_option( $name ) {
	if ( isset( $GLOBALS['delete_hook'] ) && $GLOBALS['delete_hook'][0] === $name ) {
		$step = $GLOBALS['delete_hook'][1];
		unset( $GLOBALS['delete_hook'] );
		$step();
	}
	$GLOBALS['option_calls'][] = array( 'deleted', (string) $name );
	unset( $GLOBALS['opts'][ $name ] );
	return true;
}
/**
 * The test-and-set the events store and the report store take their locks with: one row a name,
 * refused while the row is there. Each call is journaled, taken or refused, as each delete is. A
 * name this request's copy holds as absent is not looked for: core skips its own look for one, and
 * its `INSERT ... ON DUPLICATE KEY UPDATE` writes over a row another request holds and reports a
 * win, after which the name is no longer on the list.
 */
function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
	if ( ! isset( $GLOBALS['notoptions'][ $name ] ) && array_key_exists( $name, $GLOBALS['opts'] ) ) { $GLOBALS['option_calls'][] = array( 'refused', (string) $name ); return false; }
	unset( $GLOBALS['notoptions'][ $name ] );
	$GLOBALS['opts'][ $name ]  = $value;
	$GLOBALS['option_calls'][] = array( 'taken', (string) $name );
	return true;
}
/** Nothing is cached for a request here, so the events store's fresh reads have nothing to drop. */
function wp_cache_delete( $key, $group = '' ) { return true; }

/**
 * Core's check of an address before a request is made to it, without the one part that reaches the
 * network (the host's lookup), and its raw cleaner: what the events store asks of a call link.
 */
function wp_http_validate_url( $url ) {
	if ( ! is_string( $url ) || '' === $url || is_numeric( $url ) ) { return false; }
	$parts = parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || empty( $parts['host'] ) ) { return false; }
	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || false !== strpbrk( $parts['host'], ':#?[]' ) ) { return false; }
	if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80, 443, 8080 ), true ) ) { return false; }
	return $url;
}
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function esc_url_raw( $url, $protocols = null ) {
	$url    = str_replace( ' ', '%20', trim( (string) $url ) );
	$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
	if ( is_array( $protocols ) && ! in_array( $scheme, $protocols, true ) ) { return ''; }
	return (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', $url );
}

/** Every hook registered, with its priority, so `init()` can be read and then fired. */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ (string) $hook ][] = array( 'callback' => $callback, 'priority' => (int) $priority );
	return true;
}
function fire( $hook ) {
	$listeners = isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array();
	// Stable by priority, as core runs them: the order they were added breaks a tie.
	$order = array_keys( $listeners );
	usort( $order, static function ( $a, $b ) use ( $listeners ) { return ( $listeners[ $a ]['priority'] <=> $listeners[ $b ]['priority'] ) ?: ( $a <=> $b ); } );
	foreach ( $order as $i ) { call_user_func( $listeners[ $i ]['callback'] ); }
}

function register_post_type( $type, $args = array() ) { $GLOBALS['post_types'][ $type ] = $args; return true; }
function register_post_meta( $type, $key, $args = array() ) { $GLOBALS['post_meta'][ $type ][ $key ] = $args; return true; }

function wp_unique_post_slug( $slug, $post_id, $status, $type, $parent ) {
	$base = $slug;
	for ( $n = 2; ; $n++ ) {
		$clash = false;
		foreach ( $GLOBALS['posts'] as $post ) {
			if ( (int) $post->ID !== (int) $post_id && $post->post_type === $type && $post->post_name === $slug ) { $clash = true; break; }
		}
		if ( ! $clash ) { return $slug; }
		$slug = $base . '-' . $n;
	}
}

function wp_insert_post( $args, $wp_error = false ) {
	$args = wp_unslash( (array) $args );
	$type = isset( $args['post_type'] ) ? (string) $args['post_type'] : 'post';

	// Another request's step, run once while a post of the named type is being inserted and before
	// it is stored: what a program manager saves at the moment the daily run drafts a report.
	if ( isset( $GLOBALS['insert_hook'] ) && $GLOBALS['insert_hook'][0] === $type ) {
		$step = $GLOBALS['insert_hook'][1];
		unset( $GLOBALS['insert_hook'] );
		$step();
	}

	// A callback on `wp_insert_post_data` that throws, once, before anything is stored: another
	// plugin's code runs inside every insert, and an error it throws unwinds through the caller.
	if ( isset( $GLOBALS['insert_throws'] ) && $GLOBALS['insert_throws'] === $type ) {
		unset( $GLOBALS['insert_throws'] );
		throw new RuntimeException( 'A callback on the insert failed.' );
	}

	if ( '' !== $GLOBALS['insert_fail'] && $GLOBALS['insert_fail'] === $type ) {
		return $wp_error ? new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ) : 0;
	}

	$post = new WP_Post();
	foreach ( $args as $key => $value ) {
		if ( property_exists( $post, $key ) ) { $post->$key = $value; }
	}
	$post->ID = $GLOBALS['next_id']++;
	if ( '' === $post->post_date ) { $post->post_date = gmdate( 'Y-m-d H:i:s' ); }
	$GLOBALS['posts'][ $post->ID ] = $post;

	return $post->ID;
}

/**
 * The update, and the revision core takes inside it for a type that supports revisions: the post,
 * and every meta key registered with `revisions_enabled`, copied as they stand at this moment.
 * Which is why a writer that puts the meta after the post files every revision one save behind.
 */
function wp_update_post( $args, $wp_error = false ) {
	$args = wp_unslash( (array) $args );
	$id   = isset( $args['ID'] ) ? (int) $args['ID'] : 0;

	if ( ! isset( $GLOBALS['posts'][ $id ] ) || ( '' !== $GLOBALS['update_fail'] && $GLOBALS['update_fail'] === $GLOBALS['posts'][ $id ]->post_type ) ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0;
	}

	$post = $GLOBALS['posts'][ $id ];
	foreach ( $args as $key => $value ) {
		if ( 'ID' !== $key && property_exists( $post, $key ) ) { $post->$key = $value; }
	}

	$type_args = isset( $GLOBALS['post_types'][ $post->post_type ] ) ? $GLOBALS['post_types'][ $post->post_type ] : array();

	if ( in_array( 'revisions', isset( $type_args['supports'] ) ? (array) $type_args['supports'] : array(), true ) ) {
		$revision = wp_insert_post( array( 'post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $id, 'post_title' => wp_slash( $post->post_title ) ) );
		foreach ( isset( $GLOBALS['post_meta'][ $post->post_type ] ) ? $GLOBALS['post_meta'][ $post->post_type ] : array() as $key => $meta_args ) {
			if ( ! empty( $meta_args['revisions_enabled'] ) && isset( $GLOBALS['pmeta'][ $id ][ $key ] ) ) {
				$GLOBALS['pmeta'][ $revision ][ $key ] = $GLOBALS['pmeta'][ $id ][ $key ];
			}
		}
	}

	return $id;
}

function get_post( $id ) {
	if ( $id instanceof WP_Post ) { return $id; }
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}
/**
 * A key's value, as update_post_meta() keeps one; or, for a key add_post_meta() wrote, its rows, as
 * core keeps the people on a sponsor's event, one row each, `true` answering the first.
 */
function get_post_meta( $id, $key = '', $single = false ) {
	if ( ! isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ) { return $single ? '' : array(); }
	$value = $GLOBALS['pmeta'][ (int) $id ][ $key ];
	if ( isset( $GLOBALS['meta_rows'][ (int) $id ][ $key ] ) ) { return $single ? ( $value ? reset( $value ) : '' ) : array_values( $value ); }
	return $single ? $value : array( $value );
}
/** One more row under a key, as core adds one. */
function add_post_meta( $id, $key, $value, $unique = false ) {
	$GLOBALS['meta_rows'][ (int) $id ][ $key ] = true;
	$GLOBALS['pmeta'][ (int) $id ][ $key ][]   = wp_unslash( $value );
	return true;
}
/**
 * A write, or a refusal for the one key a check names in `meta_fail`: core answers false and stores
 * nothing. Another request's step may run once as a named key is written, before it is stored.
 */
function update_post_meta( $id, $key, $value ) {
	if ( isset( $GLOBALS['meta_hook'] ) && $GLOBALS['meta_hook'][0] === $key ) {
		$step = $GLOBALS['meta_hook'][1];
		unset( $GLOBALS['meta_hook'] );
		$step();
	}
	if ( '' !== $GLOBALS['meta_fail'] && $GLOBALS['meta_fail'] === $key ) { return false; }
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );
	return true;
}
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }

/** A post, its meta, and its revisions with theirs, as core deletes them. */
function wp_delete_post( $id, $force = false ) {
	$id = (int) $id;
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return false; }
	foreach ( $GLOBALS['posts'] as $other ) {
		if ( 'revision' === $other->post_type && $id === (int) $other->post_parent ) {
			unset( $GLOBALS['posts'][ $other->ID ], $GLOBALS['pmeta'][ $other->ID ] );
		}
	}
	$post = $GLOBALS['posts'][ $id ];
	unset( $GLOBALS['posts'][ $id ], $GLOBALS['pmeta'][ $id ] );
	$GLOBALS['deleted'][] = $id;
	return $post;
}

/**
 * Enough of `get_posts()`: types, a status or a list of them (`any` without trash), a meta query of
 * equal values, a bare meta key and value, a limit that defaults to core's five, and IDs. Oldest
 * first, whatever was asked, so no order is inherited from the query.
 *
 * And the cache WP_Query keeps of the queries a request makes, for queries of reports alone and
 * only while a check turns it on (`query_memo` an array): a query made again is answered as it was
 * the first time, whatever another request has stored since, as core answers it from the object
 * cache until the request itself writes a post or a post's meta; a query that asks for no cache
 * (`cache_results` false) reads the table. The one check that turns it on turns it off before it
 * reads anything this request wrote.
 */
function get_posts( $args = array() ) {
	$memo = null;

	if ( is_array( $GLOBALS['query_memo'] ) && in_array( 'wpcpm_sponsor_report', isset( $args['post_type'] ) ? (array) $args['post_type'] : array(), true ) && ! ( isset( $args['cache_results'] ) && false === $args['cache_results'] ) ) {
		$memo = serialize( $args );

		if ( array_key_exists( $memo, $GLOBALS['query_memo'] ) ) {
			return $GLOBALS['query_memo'][ $memo ];
		}
	}

	++$GLOBALS['queries'];

	$types  = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'post' );
	$status = isset( $args['post_status'] ) ? $args['post_status'] : 'publish';
	$status = 'any' === $status ? array( 'publish', 'private', 'draft', 'pending', 'future' ) : (array) $status;
	$out    = array();

	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( ! in_array( $post->post_type, $types, true ) || ! in_array( $post->post_status, $status, true ) ) { continue; }

		$ok = true;

		foreach ( isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			if ( ! isset( $GLOBALS['pmeta'][ $id ][ $clause['key'] ] ) || (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ] !== (string) $clause['value'] ) { $ok = false; break; }
		}

		if ( $ok && isset( $args['meta_key'] ) ) {
			$want = isset( $args['meta_value'] ) ? (string) $args['meta_value'] : '';
			if ( ! isset( $GLOBALS['pmeta'][ $id ][ $args['meta_key'] ] ) || (string) $GLOBALS['pmeta'][ $id ][ $args['meta_key'] ] !== $want ) { $ok = false; }
		}

		if ( $ok ) { $out[] = $post; }
	}

	usort( $out, static function ( $a, $b ) { return $a->ID - $b->ID; } );

	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : ( isset( $args['numberposts'] ) ? (int) $args['numberposts'] : 5 );

	if ( $limit > 0 ) { $out = array_slice( $out, 0, $limit ); }

	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		$out = array_map( static function ( $p ) { return $p->ID; }, $out );
	}

	if ( null !== $memo ) {
		$GLOBALS['query_memo'][ $memo ] = $out;
	}

	return $out;
}

/**
 * The database, for the two statements the store sends it: a lock's read of its own row, answered
 * from the table as bin/stubs/options-table.php answers it and journaled with the lock's other
 * steps, and `delete_all()`'s read of its lock rows by their prefix, the shape
 * bin/test-uninstall.php reads too. `esc_like()` escapes as WordPress does and `prepare()` quotes
 * as SQL does, and a LIKE is matched as MySQL matches one: `\_` is an underscore, `_` any one
 * character and `%` any run, without regard to case, so a prefix sent unescaped would take a name
 * it only looks like. Every statement is kept in `$queries`, in the order it was sent, and one this
 * does not model is kept apart for a check to refuse.
 */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	public $queries = array();

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		$next = 0;

		return preg_replace_callback(
			'/%[sd]/',
			static function ( $m ) use ( $args, &$next ) {
				$arg = isset( $args[ $next ] ) ? $args[ $next ] : '';
				++$next;

				return '%d' === $m[0] ? (string) (int) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
			},
			$query
		);
	}

	public function get_var( $query ) {
		$this->queries[] = $query;
		$value           = wpcpm_test_option_row( $query );

		if ( preg_match( "/^SELECT option_value FROM wp_options WHERE option_name = '((?:[^']|'')*)'$/", $query, $m ) ) {
			$GLOBALS['option_calls'][] = array( 'read', str_replace( "''", "'", $m[1] ) );
		}

		return $value;
	}

	public function get_col( $query ) {
		$this->queries[] = $query;

		if ( preg_match( "/^SELECT option_name FROM wp_options WHERE option_name LIKE '((?:[^']|'')*)'$/", $query, $m ) ) {
			$regex = like_regex( str_replace( "''", "'", $m[1] ) );

			return array_values( array_filter( array_map( 'strval', array_keys( $GLOBALS['opts'] ) ), static function ( $name ) use ( $regex ) { return 1 === preg_match( $regex, $name ); } ) );
		}

		$GLOBALS['unmodeled'][] = 'get_col: ' . $query;

		return array();
	}

	public function query( $query ) {
		$this->queries[]        = $query;
		$GLOBALS['unmodeled'][] = 'query: ' . $query;

		return false;
	}
}

/** A LIKE pattern as the regular expression MySQL reads it as. */
function like_regex( $pattern ) {
	$regex = '';

	for ( $i = 0, $length = strlen( $pattern ); $i < $length; ++$i ) {
		$c = $pattern[ $i ];

		if ( '\\' === $c && $i + 1 < $length ) {
			++$i;
			$regex .= preg_quote( $pattern[ $i ], '/' );
		} elseif ( '%' === $c ) {
			$regex .= '.*';
		} elseif ( '_' === $c ) {
			$regex .= '.';
		} else {
			$regex .= preg_quote( $c, '/' );
		}
	}

	return '/^' . $regex . '$/is';
}

$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
require_once __DIR__ . '/stubs/options-table.php';

/* ---- the request, the accounts and the page, stood in for the routes -------- */

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '' ) { $this->ID = (int) $id; $this->display_name = (string) $name; }
	public function exists() { return $this->ID > 0; }
}

/** Every route ends here: a redirect, a wp_die(), or a response sent. */
class WPCPM_Test_Left extends Exception {}

function get_user_by( $field, $value ) { return ( 'id' === $field && isset( $GLOBALS['users'][ (int) $value ] ) ) ? $GLOBALS['users'][ (int) $value ] : false; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ (int) $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
function is_user_logged_in() { return (int) $GLOBALS['uid'] > 0; }

// Core's own escaping, as far as a document built here needs it: entities left as they are, as
// esc_html() leaves them, and an address kept to the characters esc_url() keeps.
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $url ) { return str_replace( "'", '&#039;', (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', (string) $url ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $class ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_check_invalid_utf8( $text, $strip = false ) { return (string) $text; }
// Core's reader of the five entities (bin/stubs/specialchars.php), which a narrative's length is
// counted by.
require_once __DIR__ . '/stubs/specialchars.php';

/** Core's cleaner, in the parts that matter to a name in a header: no quote, no percent sign, no space. */
function sanitize_file_name( $name ) {
	$special = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', chr( 0 ) );
	$name    = preg_replace( '/[\r\n\t -]+/', '-', str_replace( $special, '', (string) $name ) );
	return trim( (string) $name, '.-_' );
}

/** Core's paragraphs, for text with no markup in it: a blank line ends one, a single break is a <br />. */
function wpautop( $text, $br = true ) {
	$text = trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) );
	$out  = '';
	foreach ( '' === $text ? array() : preg_split( '/\n\s*\n/', $text ) as $chunk ) {
		$chunk = trim( $chunk );
		if ( '' !== $chunk ) { $out .= '<p>' . ( $br ? preg_replace( '/\s*\n/', "<br />\n", $chunk ) : $chunk ) . "</p>\n"; }
	}
	return $out;
}

function get_bloginfo( $show = '' ) { return 'language' === $show ? 'en-US' : 'WordPress Education'; }
function admin_url( $path = '' ) { return 'https://site.example/wp-admin/' . ltrim( (string) $path, '/' ); }
function wp_login_url( $redirect = '' ) { return 'https://site.example/wp-login.php?redirect_to=' . rawurlencode( (string) $redirect ); }

/** Core's two forms: `add_query_arg( $args, $url )` and `add_query_arg( $key, $value, $url )`. */
function add_query_arg( ...$args ) {
	$query = is_array( $args[0] ) ? http_build_query( $args[0] ) : rawurlencode( (string) $args[0] ) . '=' . rawurlencode( (string) $args[1] );
	$url   = (string) ( is_array( $args[0] ) ? ( isset( $args[1] ) ? $args[1] : '' ) : ( isset( $args[2] ) ? $args[2] : '' ) );
	return '' === $query ? $url : $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
}

// A nonce is good for one action and one account, as core's are: a link minted for one report, or
// by one person, opens nothing for another.
function wp_create_nonce( $action = -1 ) { return substr( md5( 'nonce|' . (string) $action . '|' . (int) $GLOBALS['uid'] ), 0, 10 ); }
function wp_verify_nonce( $nonce, $action = -1 ) { return is_string( $nonce ) && hash_equals( wp_create_nonce( $action ), $nonce ) ? 1 : false; }
function wp_nonce_url( $url, $action = -1 ) { return esc_html( add_query_arg( '_wpnonce', wp_create_nonce( $action ), $url ) ); }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['nonce_asked'][] = (string) $action;
	if ( ! wp_verify_nonce( isset( $_REQUEST[ $query_arg ] ) ? $_REQUEST[ $query_arg ] : '', $action ) ) {
		wp_die( 'The link you followed has expired.', 403 );
	}
	return 1;
}

// The editor on the Sponsors screen (1.115.0), which the privacy walk reads as a manager sees it:
// its fields, its questions, its count of a report's revisions and the size of an attached PDF.
function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
/** Core's: every "&" written again, an entity the text holds included, unlike esc_html(). */
function esc_textarea( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
/** Core's filter, for markup the editor escaped already: a table cell's text, or a profile's link. */
function wp_kses( $html, $allowed = array() ) { return (string) $html; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />'; }
function size_format( $bytes, $decimals = 0 ) { return (int) $bytes . ' B'; }
/** The report type keeps revisions, as its registration says, and a site's own setting does not take them away here. */
function wp_revisions_enabled( $post ) { return true; }
/** A post's revisions as core's reader answers them, the newest first, as IDs when asked for them. */
function wp_get_post_revisions( $post = 0, $args = null ) {
	$ids = array_reverse( revisions_of( $post instanceof WP_Post ? $post->ID : (int) $post ) );
	return is_array( $args ) && isset( $args['fields'] ) && 'ids' === $args['fields'] ? $ids : array_map( 'get_post', $ids );
}

function wp_safe_redirect( $url, $status = 302 ) { throw new WPCPM_Test_Left( 'redirect:' . $url ); }
function wp_die( $message = '', $title = '', $args = array() ) { throw new WPCPM_Test_Left( 'die:' . ( is_int( $title ) ? $title : 0 ) . ':' . $message ); }

/**
 * The start of every response the files class sends: its one sender calls this first, with the
 * header lines and the body in hand. The CLI keeps no headers to read back, so this reads what the
 * sender was handed off the call stack, and ends the request there, as the sender's `exit` would.
 */
function nocache_headers() {
	$frames = debug_backtrace();
	$args   = isset( $frames[1]['args'] ) ? $frames[1]['args'] : array();
	$GLOBALS['sent'] = array(
		'by'      => ( isset( $frames[1]['class'] ) ? $frames[1]['class'] . '::' : '' ) . ( isset( $frames[1]['function'] ) ? $frames[1]['function'] : '' ),
		'headers' => isset( $args[0] ) ? $args[0] : null,
		'body'    => isset( $args[1] ) ? $args[1] : null,
	);
	throw new WPCPM_Test_Left( 'sent' );
}

/**
 * WordPress's own name-and-type map, over the real finfo, as the agreement's suite stands it in;
 * with `name_map_only` set it answers from the name alone, as core does on a host with no finfo.
 */
function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$ext  = strtolower( (string) pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
	$real = '';
	if ( empty( $GLOBALS['name_map_only'] ) && function_exists( 'finfo_open' ) && is_readable( $file ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$real  = $finfo ? (string) finfo_file( $finfo, $file ) : '';
	}
	if ( 'pdf' !== $ext || ( '' !== $real && 'application/pdf' !== $real ) ) {
		return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
	}
	return array( 'ext' => 'pdf', 'type' => 'application/pdf', 'proper_filename' => false );
}

/* ---- the collaborators the numbers read, stood in for the calls made ------ */

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

/** The Airtable record shape, trimmed first as the real check trims it, and the sponsorship index. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return is_string( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( $value ) ); }
	public static function sponsorship() { return $GLOBALS['mentors']; }
}

/** A mentor's students as the mentors sync caches them: whole rows, names and addresses in them. */
class WPCPM_Mentors_Dashboard {
	public static function get_mentees( $user_id ) { return isset( $GLOBALS['mentees'][ (int) $user_id ] ) ? $GLOBALS['mentees'][ (int) $user_id ] : array(); }
}

class WPCPM_Sponsor_Offers {
	public static function offers_of( $record ) {
		$out = array();
		foreach ( $GLOBALS['offers'] as $id => $offer ) {
			if ( $offer['sponsor'] === $record ) { $out[ $id ] = $offer; }
		}
		ksort( $out );
		return $out;
	}
}

/** The ledgers: a claim is `u`, `i`, `at` and `v`, the void time or 0. The pool counts do not matter here. */
class WPCPM_Sponsor_Codes {
	const LOCK_PREFIX  = 'wpcpm_claim_';
	const LOCK_TIMEOUT = 30;
	const SHARED_INDEX = -1;
	public static function claims( $offer_id ) { return isset( $GLOBALS['claims'][ (int) $offer_id ] ) ? $GLOBALS['claims'][ (int) $offer_id ] : array(); }
	public static function counts( $offer_id ) { return array( 'available' => 10, 'claimed' => count( self::claims( $offer_id ) ), 'void' => 0, 'total' => 10 + count( self::claims( $offer_id ) ) ); }
}

/**
 * A sponsor's posts, the newest change first as the real query reads them: the published ones, and
 * the dormant ones, pending with the stamp the daily run leaves on a post it put back from publish.
 * A pending post without the stamp was never published, and is in neither list.
 */
class WPCPM_Sponsor_Posts {
	public static function published_of( $record ) { return self::of( $record, 'publish', false ); }
	public static function dormant_of( $record ) { return self::of( $record, 'pending', true ); }
	private static function of( $record, $status, $dormant ) {
		$out = array();
		foreach ( isset( $GLOBALS['sposts'][ $record ] ) ? array_reverse( $GLOBALS['sposts'][ $record ] ) : array() as $post ) {
			if ( $status === $post->post_status && $dormant === isset( $GLOBALS['dormant'][ $post->ID ] ) ) { $out[] = $post; }
		}
		return $out;
	}
}

/** The sponsors module's trail: every row kept whole. */
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
	public static function record_program( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}

/**
 * The private store, in memory, journaling every call: half the checks of an upload are about a
 * call that must not have happened. A stored file's path is fresh each time, as the real store's
 * random name is.
 */
class WPCPM_Private_Files {
	public static function store( $bytes, $extension = 'pdf' ) {
		static $stored = 0;
		$GLOBALS['journal'][] = 'store';
		if ( $GLOBALS['store_fails'] ) { return new WP_Error( 'wpcpm_private_write', 'The file could not be written.' ); }
		$path = '2026/' . substr( hash( 'sha256', ++$stored . '|' . (string) $bytes ), 0, 32 ) . '.' . $extension;
		$GLOBALS['files'][ $path ] = (string) $bytes;
		return array( 'path' => $path, 'sha256' => hash( 'sha256', (string) $bytes ), 'size' => strlen( (string) $bytes ) );
	}
	public static function read( $relative ) { return array_key_exists( (string) $relative, $GLOBALS['files'] ) ? $GLOBALS['files'][ (string) $relative ] : new WP_Error( 'wpcpm_private_missing', 'That file is not in the store.' ); }
	public static function forget( $relative ) { $GLOBALS['journal'][] = 'forget'; $GLOBALS['forgotten'][] = (string) $relative; unset( $GLOBALS['files'][ (string) $relative ] ); return true; }
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}

class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][] = array( (string) $channel, (string) $value ); return true; }
	// The editor asks for what a refused Save kept; nothing here refuses one, so nothing is waiting:
	// bin/test-sponsors-screen.php presses the Save and reads the typing that comes back.
	public static function take( $channel, $user_id = 0 ) { return ''; }
}

/** The Sponsors screen, for the channel its notices are read from. */
class WPCPM_Sponsors {
	const FLASH = 'sponsors_admin';
}

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-cohort.php';
require_once __DIR__ . '/../includes/class-wpcpm-roster-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institutions-index.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-mentors.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-numbers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-semester-report-screen.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-screen.php';
// The events store (1.117.0), which the numbers ask for the events a sponsor held, and the sessions'
// zone reader its planner's fields are read through.
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php';
// The four original tracks as a site holds them compiled, planted below, and the runner the
// suite's apply_filters() hands its hooks to.
require_once __DIR__ . '/stubs/compiled-seeds.php';
// The Track Builder's store, whose published copy of a track is where a Partner's draft reads the
// track's partners' narrative.
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-store.php';

// The program map answers through the filters the tracks' reader hooks, so they are hooked as the
// plugin hooks them. The one action it adds, the chips' styles on `init`, is set aside: the checks
// below read the report store's own `init` hooks, and fire them.
$hooks_before_tracks = $GLOBALS['hooks'];
WPCPM_Tracks::init();
$GLOBALS['hooks'] = $hooks_before_tracks;

// The plugin's folder is this run's own, holding a copy of the semester report's stylesheet: one
// check writes the two characters that end a tag into it and sees the document drop it, and the
// file itself is never touched.
define( 'WPCPM_PLUGIN_DIR', wpcpm_test_temp_dir() . 'plugin/' );
define( 'WPCPM_PLUGIN_URL', 'https://site.example/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
mkdir( dirname( WPCPM_PLUGIN_DIR . WPCPM_Semester_Report_Screen::STYLE_FILE ), 0700, true );
copy( __DIR__ . '/../' . WPCPM_Semester_Report_Screen::STYLE_FILE, WPCPM_PLUGIN_DIR . WPCPM_Semester_Report_Screen::STYLE_FILE );

$fails = 0;
$total = 0;

// A route that sends a response ends in `exit`, and the suite's stand-in for `nocache_headers()`
// is what ends it here first. A sender that stopped calling it would echo its body and end the run
// with the status of a pass, every check after it unread; so an end before the last check is a
// failure, the badge suite's own guard.
register_shutdown_function(
	static function () {
		if ( empty( $GLOBALS['run_complete'] ) ) {
			echo "\nFAIL the run ended before its last check\n";
			exit( 1 );
		}
	}
);

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

/** A refusal's code, or what came back instead of one. */
function code_of( $result ) { return is_wp_error( $result ) ? $result->get_error_code() : 'no refusal: ' . var_export( $result, true ); }

/** The report posts in the given statuses, as IDs, oldest first. */
function report_posts( $statuses = array( 'private' ) ) {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( WPCPM_Sponsor_Report::POST_TYPE === $post->post_type && in_array( $post->post_status, (array) $statuses, true ) ) { $ids[] = $post->ID; }
	}
	sort( $ids );
	return $ids;
}

/** A post's revisions, oldest first. */
function revisions_of( $id ) {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'revision' === $post->post_type && (int) $id === (int) $post->post_parent ) { $ids[] = $post->ID; }
	}
	sort( $ids );
	return $ids;
}

function latest_revision( $id ) { $all = revisions_of( $id ); return $all ? end( $all ) : 0; }

function ids( array $posts ) { return array_map( static function ( $p ) { return $p instanceof WP_Post ? (int) $p->ID : 'not a post'; }, array_values( $posts ) ); }

/** Every report post gone from the store, between parts of the suite that each start from none. */
function clear_reports() {
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( WPCPM_Sponsor_Report::POST_TYPE === $post->post_type || 'revision' === $post->post_type ) {
			unset( $GLOBALS['posts'][ $post->ID ], $GLOBALS['pmeta'][ $post->ID ] );
		}
	}
}

/**
 * What the report store's locks went through since the journal was last emptied, in order: each
 * step `read` (the row read from the table), `taken`, `refused` or `deleted`, then the key the lock
 * was named after. Another store's lock is not listed.
 */
function lock_ops() {
	$ops = array();

	foreach ( $GLOBALS['option_calls'] as $call ) {
		if ( 0 === strpos( $call[1], 'wpcpm_sreport_lock_' ) ) {
			$ops[] = $call[0] . ':' . substr( $call[1], strlen( 'wpcpm_sreport_lock_' ) );
		}
	}

	return $ops;
}

/* ---- the fixture ----------------------------------------------------------- */

$I1 = 'recSEED0000000101';
$I2 = 'recSEED0000000102';
$I3 = 'recSEED0000000103';
$C1 = 'recSEED0000000901';
$C2 = 'recSEED0000000902';

$A  = 'spoAAAA0000000001'; // Backer, the term ends 30 September 2026.
$B  = 'spoAAAA0000000002'; // Supporter, the term ended 31 August 2026.
$M  = 'spoAAAA0000000003'; // A Friend.
$F  = 'spoAAAA0000000004'; // Backer, the term runs to 31 December 2026; two more linked mentors.
$P  = 'spoAAAA0000000005'; // Paused.
$P2 = 'spoAAAA0000000006'; // Partner of the 150-hour track.
$Z  = 'spoAAAA0000000007'; // Supporter, the term ends 30 September 2026; inserted last, first by name.

/** An institution as the institutions index holds it, a contact person and an address in it. */
function institution_row( $record, $name, $country, $contact, $email ) {
	return array_merge( WPCPM_Institutions_Index::empty_row(), array( 'record_id' => $record, 'name' => $name, 'stage' => 'Active', 'country' => $country, 'country_name' => '' === $country ? '' : 'Country ' . substr( $country, -1 ), 'city' => 'City Example', 'website' => 'https://' . sanitize_title( $name ) . '.example/', 'contact_person' => $contact, 'contact_email' => $email ) );
}

/** A student as the roster index holds one: every key the index keeps, a name and an address among them. */
function student( $record, $name, $status, $institution, $start, $end, $hours = '', $team = '' ) {
	$handle = sanitize_title( $name ) . '-example';
	$domain = 0 === strpos( $name, 'E' ) || in_array( $name, array( 'Chloe Student', 'Farid Student' ), true ) ? 'southern.example' : 'uni-north.example';
	return array(
		'record_id'      => $record,
		'name'           => $name,
		'email'          => str_replace( ' ', '.', strtolower( $name ) ) . '@' . $domain,
		'email_key'      => str_replace( ' ', '.', strtolower( $name ) ) . '@' . $domain,
		'status'         => $status,
		'institution'    => $institution,
		'start'          => $start,
		'end'            => $end,
		'has_mentor'     => true,
		'username'       => $handle,
		'field_of_study' => 'Computer Science',
		'tutor'          => 'Professor Tutor Example',
		'mentor_name'    => 'Marta Mentor Example',
		'team'           => $team,
		'website'        => 'https://' . sanitize_title( $name ) . '.example/',
		'hours'          => $hours,
		'import_key'     => '',
		'reports'        => array(),
		'user_id'        => 0,
		'touched'        => 0,
	);
}

/** One institution's roster, as the students sync writes the option. */
function roster( array $rows ) {
	$keyed = array();
	foreach ( $rows as $row ) { $keyed[ $row['record_id'] ] = $row; }
	return array( 'v' => WPCPM_Roster_Index::VERSION, 'read' => 1757000000, 'rows' => $keyed );
}

/** A mentee as the mentors sync caches one, the institution's name resolved into it, the handle of the fixtures' own shape. */
function mentee( $record, $name, $status, $start, $institution ) {
	$handle = sanitize_title( $name ) . '-example';
	return array( 'record_id' => $record, 'name' => $name, 'email' => str_replace( ' ', '.', strtolower( $name ) ) . '@uni-north.example', 'status' => $status, 'is_past' => 'Graduate' === $status, 'start' => $start, 'end' => '', 'institution' => $institution, 'profile' => 'https://profiles.wordpress.org/' . $handle . '/', 'username' => $handle, 'team' => '', 'website' => '', 'hours' => '', 'link' => '', 'tutor' => '', 'field_of_study' => '', 'accessibility' => '', 'mentor_ids' => array() );
}

/** A claim on an offer, at noon UTC on a day; a void one is voided an hour later. */
function claim( $user, $index, $day, $void = false ) {
	$at = strtotime( $day . ' 12:00:00 UTC' );
	return array( 'u' => $user, 'i' => $index, 'at' => $at, 'v' => $void ? $at + 3600 : 0 );
}

/** A post of a sponsor's, dated the day it went live: published, dormant (put back by the run), or pending and never published. */
function sponsor_post( $record, $day, $status = 'publish', $dormant = false ) {
	$post              = new WP_Post();
	$post->ID          = $GLOBALS['next_id']++;
	$post->post_type   = 'post';
	$post->post_status = $status;
	$post->post_date   = $day;
	$GLOBALS['sposts'][ $record ][] = $post;
	if ( $dormant ) { $GLOBALS['dormant'][ $post->ID ] = substr( $day, 0, 10 ); }
	return $post->ID;
}

/** A post in the store that is not a report: a sponsor's published post, for the refusals. */
function plain_post() {
	return get_post( wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'A post' ) ) );
}

/** The whole seeded site, options and stores: called once, and again by the parts that start over. */
function seed_program() {
	global $I1, $I2, $C1, $C2;

	$GLOBALS['opts']['wpcpm_institutions_index'] = array(
		'v'    => WPCPM_Institutions_Index::VERSION,
		'read' => 1757000000,
		'rows' => array(
			$I1 => institution_row( $I1, 'Uniwersytet Północny Example', $C1, 'Irena Contact Example', 'irena.contact@uni-north.example' ),
			$I2 => institution_row( $I2, 'Southern Institute Example', $C2, 'Samuel Contact Example', 'samuel.contact@southern.example' ),
		),
	);

	// Six students across two cohorts, 2025-H2 and 2026-H1; one Graduate, one Dropped out, one
	// SPAM; hours on three, 12, 6.5 and 100; a contribution team on three, one naming two teams
	// the way the sync joins them.
	$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I1 ) ] = roster(
		array(
			student( 'recSEED0000000201', 'Ada Student', 'In Sensei', $I1, '2025-10-06', '', '12', 'Polyglots' ),
			student( 'recSEED0000000202', 'Bruno Student', 'Graduate', $I1, '2025-07-14', '2026-01-30', '100', 'Training' ),
			student( 'recSEED0000000204', 'Dmitri Student', 'SPAM', $I1, '2026-02-09', '' ),
		)
	);
	$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I2 ) ] = roster(
		array(
			student( 'recSEED0000000203', 'Chloe Student', 'Dropped out', $I2, '2025-07-14', '2025-09-19' ),
			student( 'recSEED0000000205', 'Esi Student', 'In Sensei 50h', $I2, '2026-02-16', '', '6.5', 'Training, Polyglots' ),
			student( 'recSEED0000000206', 'Farid Student', 'In Sensei', $I2, '2026-03-02', '' ),
		)
	);
}

seed_program();

// The Track Builder's compiled tracks: the four original tracks as a site holds them, the 150-hour
// track with a partner.
wpcpm_seed_compiled_options( $GLOBALS['opts'] );
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ]['In Sensei']['sponsors'] = array( $P2 );
WPCPM_Tracks::flush();

/**
 * One of the four original tracks' posts as the Track Builder keeps it, published from its seed:
 * a partners' narrative in its published copy, what a Partner's draft reads, and another in its
 * saved draft, an edit nobody has published yet; '' leaves one out.
 *
 * @param string $key       The track's key, which names its seed and, through the compiled index, its post.
 * @param string $published The published copy's narrative.
 * @param string $saved     The saved draft's narrative.
 * @return int The track's post.
 */
function plant_track( $key, $published, $saved ) {
	$post_id = 0;

	foreach ( WPCPM_Tracks::rows() as $row ) {
		if ( $key === $row['key'] ) {
			$post_id = (int) $row['post'];
		}
	}

	$seed = WPCPM_Track_Definition::normalize( WPCPM_Track_Definition::decode( (string) file_get_contents( __DIR__ . '/../includes/tracks/seeds/' . $key . '.json' ) ) );
	$copy = $seed;
	$edit = $seed;

	if ( '' !== $published ) {
		$copy['partners_narrative'] = $published;
	}

	if ( '' !== $saved ) {
		$edit['partners_narrative'] = $saved;
	}

	$post              = new WP_Post();
	$post->ID          = $post_id;
	$post->post_type   = WPCPM_Track_Store::POST_TYPE;
	$post->post_status = 'publish';
	$post->post_title  = (string) $seed['label'];

	// Kept as the store keeps them, after core has unslashed what it was handed.
	$GLOBALS['posts'][ $post_id ]                                       = $post;
	$GLOBALS['pmeta'][ $post_id ][ WPCPM_Track_Store::META_PUBLISHED ]  = WPCPM_Track_Definition::encode( $copy );
	$GLOBALS['pmeta'][ $post_id ][ WPCPM_Track_Store::META_DEFINITION ] = WPCPM_Track_Definition::encode( $edit );

	return $post_id;
}

// The 150-hour track's published words for its partners, and an edit of them saved since, which no
// report reads until the track is published again.
$track_words = "Our mentors met the track's students every week.\n\nThey kept their notes in C:\\mentors\\2026 and \"shared\" them.";
plant_track( '150h', $track_words, 'An edit saved and never published.' );

// The mentors the sponsorship index holds (Active mentors alone), and their students.
$GLOBALS['mentors'] = array(
	'recSEED0000000301' => array( 'name' => 'Marta Mentor Example', 'profile' => 'https://profiles.wordpress.org/marta-example/', 'user_id' => 30, 'status' => 'Active', 'expertise' => array( 'Polyglots' ) ),
	'recSEED0000000302' => array( 'name' => 'Oscar Mentor Example', 'profile' => 'https://profiles.wordpress.org/oscar-example/', 'user_id' => 31, 'status' => 'Active', 'expertise' => array() ),
	'recSEED0000000304' => array( 'name' => 'Nadia Mentor Example', 'profile' => 'https://profiles.wordpress.org/nadia-example/', 'user_id' => 0, 'status' => 'Active', 'expertise' => array() ),
);
$GLOBALS['mentees'] = array(
	30 => array( mentee( 'recSEED0000000201', 'Ada Student', 'In Sensei', '2025-10-06', 'Uniwersytet Północny Example' ), mentee( 'recSEED0000000202', 'Bruno Student', 'Graduate', '2025-07-14', 'Uniwersytet Północny Example' ) ),
	31 => array( mentee( 'recSEED0000000205', 'Esi Student', 'In Sensei 50h', '2026-02-16', 'Southern Institute Example' ), mentee( 'recSEED0000000206', 'Farid Student', 'In Sensei', '2026-03-02', 'Southern Institute Example' ), mentee( 'recSEED0000000204', 'Dmitri Student', 'SPAM', '2026-02-09', 'Uniwersytet Północny Example' ), mentee( 'recSEED0000000207', 'Gosia Student', 'In Sensei', '2026-10-05', 'Uniwersytet Północny Example' ) ),
);

// The offers, and claims in and out of the period.
$GLOBALS['offers'] = array(
	801 => array( 'id' => 801, 'sponsor' => $A, 'title' => 'One year of the premium plugin', 'kind' => 'codes', 'state' => 'live' ),
	802 => array( 'id' => 802, 'sponsor' => $A, 'title' => 'A hosting trial', 'kind' => 'shared', 'state' => 'ended' ),
	803 => array( 'id' => 803, 'sponsor' => $B, 'title' => 'Cirrus hosting', 'kind' => 'codes', 'state' => 'live' ),
);
$GLOBALS['claims'] = array(
	801 => array( claim( 20, 0, '2025-09-20' ), claim( 21, 1, '2025-10-15' ), claim( 22, 2, '2026-03-10' ), claim( 23, 3, '2026-03-22' ), claim( 24, 4, '2026-04-01', true ), claim( 25, 5, '2026-10-02' ) ),
	802 => array( claim( 26, -1, '2024-12-01' ) ),
	803 => array( claim( 27, 0, '2026-01-10' ) ),
);

// Two posts, one in the period.
sponsor_post( $A, '2025-06-01 10:00:00' );
sponsor_post( $A, '2026-05-04 09:30:00' );

// Future Example's posts for its own year: one published, one the daily run put dormant, both in
// it; one pending that was never published; and one dormant from before it.
sponsor_post( $F, '2026-03-01 09:00:00' );
sponsor_post( $F, '2026-07-01 09:00:00', 'pending', true );
sponsor_post( $F, '2026-08-01 09:00:00', 'pending' );
sponsor_post( $F, '2025-12-15 09:00:00', 'pending', true );

// The sponsors, in the store.
$sponsors = array(
	$A  => array( 'name' => 'Mango Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30', 'funded_line' => "Stipends for two of the program's mentors", 'mentors' => array( 'recSEED0000000301', 'recSEED0000000302' ), 'contact_person' => 'Rep One Example', 'contact_email' => 'maciej@a8c.com' ),
	$B  => array( 'name' => 'Cirrus Example', 'status' => 'Approved', 'tier' => 'supporter', 'tier_until' => '2026-08-31', 'funded_line' => 'Hosting for the students' ),
	$M  => array( 'name' => 'Mentor Only Example', 'status' => 'Approved', 'tier' => 'mentor', 'tier_until' => '2026-06-30' ),
	$F  => array( 'name' => 'Future Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-12-31', 'mentors' => array( 'recSEED0000000303', 'recSEED0000000304' ) ),
	$P  => array( 'name' => 'Paused Example', 'status' => 'Paused', 'tier' => 'backer', 'tier_until' => '2026-06-30' ),
	$P2 => array( 'name' => 'Partner Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2026-09-30', 'funded_line' => 'Mentors for the 150-hour track' ),
	$Z  => array( 'name' => 'Aardvark Example', 'status' => 'Approved', 'tier' => 'supporter', 'tier_until' => '2026-09-30' ),
);
foreach ( $sponsors as $key => $fields ) {
	WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $key ), $fields ) );
}

// Everything a report must never carry: the students' names, addresses, usernames, sites and record
// IDs, the institutions' names, their contacts and the tutors. The mentees carry the same people
// again. A first name alone is matched as a whole word (`$first_names`), so it is found wherever it
// stands and not inside a longer word.
$needles     = array( 'Uniwersytet Północny Example', 'Southern Institute Example', 'Edge Institute Example', 'Irena Contact Example', 'irena.contact@uni-north.example', 'Samuel Contact Example', 'samuel.contact@southern.example', 'Professor Tutor Example', 'uni-north.example', 'southern.example', 'recSEED00000002', 'Computer Science' );
$first_names = array( 'Ada', 'Bruno', 'Chloe', 'Dmitri', 'Esi', 'Farid', 'Gosia', 'Hana', 'Ivo', 'Jun', 'Kofi', 'Lena', 'Milo', 'Nia', 'Omar', 'Pia', 'Quinn', 'Rafa', 'Sam', 'Tia', 'Uma', 'Vera', 'Wes' );
foreach ( $first_names as $first ) {
	$needles[] = $first . ' Student';
	$needles[] = 'Student ' . $first;
	$needles[] = strtolower( $first ) . '-student-example';
	$needles[] = strtolower( $first ) . '-student.example';
	$needles[] = strtolower( $first ) . '.student';
}

/** What of a value would tell a reader about a seeded person or school: a needle, an address, a key naming one. */
function leaks( $value, $where = '' ) {
	$found = array();

	if ( is_array( $value ) ) {
		foreach ( $value as $key => $inner ) {
			if ( in_array( (string) $key, array( 'email', 'email_key', 'username', 'user_id', 'record_id', 'institution', 'tutor', 'website', 'field_of_study' ), true ) ) {
				$found[] = 'key ' . $key . ' at ' . $where;
			}
			$found = array_merge( $found, leaks( (string) $key, $where . '/' . $key ), leaks( $inner, $where . '/' . $key ) );
		}
		return array_values( array_unique( $found ) );
	}

	if ( ! is_scalar( $value ) ) {
		return $found;
	}

	$text = (string) $value;

	if ( is_email( $text ) ) {
		$found[] = 'address ' . $text . ' at ' . $where;
	}

	foreach ( $GLOBALS['needles'] as $needle ) {
		if ( false !== mb_stripos( $text, $needle ) ) {
			$found[] = $needle . ' at ' . $where;
		}
	}

	foreach ( $GLOBALS['first_names'] as $first ) {
		if ( 1 === preg_match( '/\b' . preg_quote( $first, '/' ) . '\b/iu', $text ) ) {
			$found[] = $first . ' at ' . $where;
		}
	}

	return $found;
}

/**
 * Every address inside a longer text, such as a whole document: `leaks()` knows an address only
 * when it is the whole value.
 *
 * @param string $text Anything.
 * @return string[]
 */
function addresses( $text ) {
	preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+/', (string) $text, $found );
	return $found[0];
}

/**
 * The editor a program manager writes a report in, as the Sponsors screen draws it.
 *
 * @param int $post_id The report.
 * @return string '' when the class draws nothing for it.
 */
function editor_markup( $post_id ) {
	if ( ! is_callable( array( 'WPCPM_Sponsor_Report_Screen', 'render_editor' ) ) ) {
		return '';
	}

	ob_start();
	WPCPM_Sponsor_Report_Screen::render_editor( (int) $post_id );
	return (string) ob_get_clean();
}

/**
 * The body rows of the table a caption names, for comparing two pages' tables row for row.
 *
 * @param string $html    A page.
 * @param string $caption The table's caption.
 * @return string From `<tbody>` to just before `</tbody>`, or '' when there is no such table.
 */
function tbody_after( $html, $caption ) {
	$at = strpos( (string) $html, '<caption>' . $caption . '</caption>' );

	if ( false === $at ) {
		return '';
	}

	$start = strpos( (string) $html, '<tbody>', $at );
	$end   = false === $start ? false : strpos( (string) $html, '</tbody>', $start );

	return false === $end ? '' : substr( (string) $html, $start, $end - $start );
}

$R = 'WPCPM_Sponsor_Report';
$N = 'WPCPM_Sponsor_Report_Numbers';

$zero = array( 'started' => 0, 'finished' => 0, 'active' => 0, 'hours' => 0.0, 'contributions' => 0, 'teams' => 0, 'institutions' => 0, 'countries' => 0 );

echo "=== The post type and its two revisioned keys ===\n";

ck( 'the post type is wpcpm_sponsor_report, twenty characters, the most register_post_type() accepts', array( $R::POST_TYPE, strlen( $R::POST_TYPE ) ), array( 'wpcpm_sponsor_report', 20 ) );
ck( 'the version, the meta keys, the states, the sections and the audit kinds the other halves read',
	array( $R::VERSION, $R::META_SPONSOR, $R::META_UNTIL, $R::META_DATA, $R::META_SECTIONS, $R::META_STATE, $R::META_FINAL, $R::META_FILE, $R::META_FILE_NAME, $R::STATE_DRAFT, $R::STATE_FINAL, $R::SECTION_PROGRAM, $R::SECTION_SPONSORSHIP, $R::SECTION_COST_LINE, $R::SECTION_TRACK, $R::SECTION_AHEAD, $R::LOG_DRAFTED, $R::LOG_FINAL, $R::LOG_REOPENED, $R::LOG_PDF ),
	array( 1, '_wpcpm_sreport_sponsor', '_wpcpm_sreport_until', '_wpcpm_sreport_data', '_wpcpm_sreport_sections', '_wpcpm_sreport_state', '_wpcpm_sreport_final', '_wpcpm_sreport_file', '_wpcpm_sreport_file_name', 'draft', 'final', 'program', 'sponsorship', 'cost_line', 'track', 'ahead', 'sponsor_report_drafted', 'sponsor_report_final', 'sponsor_report_reopened', 'sponsor_report_pdf' )
);

$R::init();

ck( 'init() hooks the post type and then its meta on init at 5, and nothing else',
	array( array_keys( $GLOBALS['hooks'] ), array_map( static function ( $h ) { return array( $h['callback'], $h['priority'] ); }, $GLOBALS['hooks']['init'] ) ),
	array( array( 'init' ), array( array( array( $R, 'register_post_type' ), 5 ), array( array( $R, 'register_meta' ), 5 ) ) )
);

fire( 'init' );

$type = isset( $GLOBALS['post_types'][ $R::POST_TYPE ] ) ? $GLOBALS['post_types'][ $R::POST_TYPE ] : array();

ck( 'private everywhere, as the semester report is: not public, not queryable, not searched, no screen, no menu, no REST, no archive, no rewrite, no query var',
	array( $type['public'], $type['publicly_queryable'], $type['exclude_from_search'], $type['show_ui'], $type['show_in_menu'], $type['show_in_rest'], $type['has_archive'], $type['rewrite'], $type['query_var'], $type['map_meta_cap'] ),
	array( false, false, true, false, false, false, false, false, false, true )
);
ck( 'a title and revisions, and no editor: nothing is written to post_content', $type['supports'], array( 'title', 'revisions' ) );
ck( 'under a capability type nothing is granted', $type['capability_type'], array( 'wpcpm_sponsor_report', 'wpcpm_sponsor_reports' ) );

$revisioned = array( 'type' => 'array', 'single' => true, 'show_in_rest' => false, 'revisions_enabled' => true, 'auth_callback' => '__return_false' );

ck( 'the narratives and the snapshot are revisioned meta, not in REST and not editable through the meta API',
	isset( $GLOBALS['post_meta'][ $R::POST_TYPE ] ) ? $GLOBALS['post_meta'][ $R::POST_TYPE ] : array(),
	array( $R::META_SECTIONS => $revisioned, $R::META_DATA => $revisioned )
);

$sponsors_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );

// Membership, not a place in the list: the classes that join it later may stand anywhere.
$booted = array();
if ( 1 === preg_match( "/foreach \( array\(([^)]*)\) as \\\$front \)/", $sponsors_src, $boot_list ) ) {
	$booted = array_map( static function ( $name ) { return trim( $name, " '\t\n" ); }, explode( ',', $boot_list[1] ) );
}
ck( 'the Sponsors module boots the store with its other classes', array( count( $booted ) > 10, in_array( 'WPCPM_Sponsor_Report', $booted, true ) ), array( true, true ) );
ck( 'and the report\'s PDF and printed document after it',
	array( in_array( 'WPCPM_Sponsor_Report_Files', $booted, true ), array_search( 'WPCPM_Sponsor_Report_Files', $booted, true ) > array_search( 'WPCPM_Sponsor_Report', $booted, true ) ),
	array( true, true )
);

$loader_src = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );

// Required, in whatever order: neither class extends the other or names it outside a method, and
// PHP resolves a name inside a method when the method runs, so which comes first changes nothing.
ck( 'the loader requires the numbers and the files class', array(
	false !== strpos( $loader_src, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-report-numbers.php';" ),
	false !== strpos( $loader_src, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-report-files.php';" ),
), array( true, true ) );
ck( 'and the Sponsors screen has words for what the two forms flash, as it has for the agreement\'s', false !== strpos( $sponsors_src, 'WPCPM_Sponsor_Report_Files::manager_messages()' ), true );

echo "\n=== The period ===\n";

ck( 'a term ending on 30 September 2026 covers 1 October 2025 to 30 September 2026', $R::period( '2026-09-30' ), array( 'from' => '2025-10-01', 'until' => '2026-09-30' ) );
ck( 'the calendar year, a year that starts on the second of March, and the terms around a leap day, which never share a day or leave one out',
	array( $R::period( '2026-12-31' ), $R::period( '2026-03-01' ), $R::period( '2027-02-28' ), $R::period( '2028-02-29' ), $R::period( '2029-02-28' ) ),
	array(
		array( 'from' => '2026-01-01', 'until' => '2026-12-31' ),
		array( 'from' => '2025-03-02', 'until' => '2026-03-01' ),
		array( 'from' => '2026-03-01', 'until' => '2027-02-28' ),
		array( 'from' => '2027-03-01', 'until' => '2028-02-29' ),
		array( 'from' => '2028-03-01', 'until' => '2029-02-28' ),
	)
);

$zoned = array();
foreach ( array( 'Pacific/Kiritimati', 'Pacific/Pago_Pago', 'UTC' ) as $zone ) {
	date_default_timezone_set( $zone );
	$zoned[] = $R::period( '2026-09-30' )['from'] . ' ' . $R::period( '2028-02-29' )['from'];
}
date_default_timezone_set( 'UTC' );

ck( 'and the same days whatever timezone PHP runs in, fourteen hours ahead of UTC or eleven behind', $zoned, array( '2025-10-01 2027-03-01', '2025-10-01 2027-03-01', '2025-10-01 2027-03-01' ) );
ck( 'what is not a day has no period', array( $R::period( '2026-02-30' ), $R::period( '' ) ), array( array( 'from' => '', 'until' => '' ), array( 'from' => '', 'until' => '' ) ) );
ck( 'is_period_end(): a real day in Y-m-d, and nothing else',
	array( $R::is_period_end( '2026-09-30' ), $R::is_period_end( '2028-02-29' ), $R::is_period_end( '2026-02-29' ), $R::is_period_end( '2026-9-30' ), $R::is_period_end( ' 2026-09-30' ), $R::is_period_end( '2026-09-30 00:00:00' ), $R::is_period_end( '' ), $R::is_period_end( array() ) ),
	array( true, true, false, false, false, false, false, false )
);

echo "\n=== The title ===\n";

$row_a = WPCPM_Sponsors_Index::row( $A );

ck( 'the sponsor\'s name and the two months of the period', $R::title_for( $row_a, $R::period( '2026-09-30' ) ), 'Mango Example, October 2025 to September 2026' );

$GLOBALS['site_tz'] = 'Pacific/Kiritimati';
$naive              = wp_date( 'F Y', strtotime( '2026-09-30 12:00:00 UTC' ) );
$far_east           = $R::title_for( $row_a, $R::period( '2026-09-30' ) );
$GLOBALS['site_tz'] = 'UTC';

ck( 'on a site fourteen hours ahead of UTC, where noon on the 30th is already October, the title still ends in September', array( $naive, $far_east ), array( 'October 2026', 'Mango Example, October 2025 to September 2026' ) );

// The report's Term row, the Sponsors screen, the card and the mails print a term's day through
// format_day(): the day it is given, whichever side of UTC the site is on, as the title's months are.
$far_days = array();
foreach ( array( 'Pacific/Kiritimati', 'Pacific/Auckland', 'Pacific/Pago_Pago', 'UTC' ) as $zone ) {
	$GLOBALS['site_tz'] = $zone;
	$far_days[]         = WPCPM_Sponsor_Tiers::format_day( '2027-01-31' ) . ', ' . WPCPM_Sponsor_Tiers::format_day( '2026-02-01' );
}
$GLOBALS['site_tz'] = 'UTC';

ck( 'format_day() writes the day it is given on a site fourteen or thirteen hours ahead of UTC, and eleven behind: the term\'s first and last days, not the next ones',
	$far_days,
	array_fill( 0, 4, 'January 31, 2027, February 1, 2026' )
);
ck( 'a sponsor with no name is named by its key', $R::title_for( array( 'record_id' => $A, 'name' => '  ' ), $R::period( '2026-09-30' ) ), $A . ', October 2025 to September 2026' );

echo "\n=== The numbers: the program ===\n";

$program = array( 'started' => 3, 'finished' => 1, 'active' => 4, 'hours' => 118.5, 'contributions' => 3, 'teams' => 2, 'institutions' => 2, 'countries' => 2 );

// Started: Ada, Esi and Farid; not Bruno and Chloe, who started in July, nor Dmitri, who is SPAM.
// Finished: Bruno, a Graduate whose end is in the period. Active: Ada, Bruno, Esi and Farid; not
// Chloe, who dropped out before it began. Hours 12 + 100 + 6.5 over the active rows. Teams: three
// active rows name one, and Esi's names two; Polyglots and Training are two teams.
ck( 'the program over the seeded term: every figure', $N::program( '2025-10-01', '2026-09-30' ), $program );

$spam_row = $GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I1 ) ]['rows']['recSEED0000000204'];
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I1 ) ]['rows']['recSEED0000000204']['status'] = 'In Sensei';
$as_student = $N::program( '2025-10-01', '2026-09-30' );
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I1 ) ]['rows']['recSEED0000000204'] = $spam_row;

ck( 'the SPAM row counts nowhere: read as a student, it would start and be active', array( $as_student['started'], $as_student['active'] ), array( 4, 5 ) );

// A roster of its own for the rules at their edges.
$main_opts     = $GLOBALS['opts'];
$main_statuses = $GLOBALS['settings']['student_statuses'];
$GLOBALS['opts']['wpcpm_institutions_index']['rows'] = array( $I3 => institution_row( $I3, 'Edge Institute Example', '', 'Irena Contact Example', 'irena.contact@uni-north.example' ) );
$edge_rows = array(
	student( 'recSEED0000000211', 'Hana Student', 'Graduate', $I3, '2025-11-03', '', '7' ),             // Finished with no end: started in the period, so active and finished.
	student( 'recSEED0000000212', 'Ivo Student', 'Graduate', $I3, '2024-11-04', '', '40' ),              // No end, a past status, started long before: neither active nor finished.
	student( 'recSEED0000000213', 'Jun Student', 'In Sensei', $I3, '2026-10-05', '' ),                   // Starts after the period.
	student( 'recSEED0000000214', 'Kofi Student', 'In Sensei', $I3, '', '' ),                             // A current status and no dates: active, by the letter of the rule.
	student( 'recSEED0000000215', 'Lena Student', 'Duplicated', $I3, '2026-01-05', '' ),                 // Never shown.
	student( 'recSEED0000000216', 'Milo Student', 'Interested', $I3, '2026-01-05', '' ),                 // Did not sign up.
	student( 'recSEED0000000217', 'Nia Student', 'In Sensei', '', '2025-10-01', '', '3' ),               // Starts on the first day; no institution on the row.
	student( 'recSEED0000000218', 'Omar Student', 'Dropped out', $I3, '2025-03-03', '2025-10-01' ),      // Ends on the first day: active.
	student( 'recSEED0000000219', 'Pia Student', 'In Sensei', $I3, '2026-09-30', '' ),                   // Starts on the last day.
	student( 'recSEED0000000220', 'Quinn Student', 'In Sensei 50h', $I3, '2026-02-16 00:00:00', '', 'n/a' ), // A datetime, and hours that are no number.
	student( 'recSEED0000000221', 'Rafa Student', 'Graduate', $I3, '', '2026-05-20' ),                   // Finished in the period, start unknown: finished and active.
	student( 'recSEED0000000222', 'Sam Student', 'In Sensei', $I3, '2024-11-04', '', '9' ),              // A current status, no end, started long before: still going, active.
	student( 'recSEED0000000223', 'Tia Student', 'Dropped out', $I3, '2026-01-12', '', '2' ),            // A past status with no end that started in the period: active.
);
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I3 ) ] = roster( $edge_rows );

// Started: Hana, Nia, Pia, Quinn and Tia. Finished: Hana and Rafa. Active: Hana, Kofi, Nia, Omar,
// Pia, Quinn, Rafa, Sam and Tia; not Ivo, a past status with no end who started before the period,
// nor Jun, who starts after it. Hours over those: Hana 7, Nia 3, Sam 9, Tia 2.
ck( 'each rule at its edges: a first and a last day count, a datetime reads as its day, no end day is still going for a current status alone, a finished status with no end is active only in the period it started in, a row with no start day is active by the letter of the rule, Duplicated and Interested count nowhere, hours that are no number are none, a row with no institution is its roster\'s, an institution with no country adds none',
	$N::program( '2025-10-01', '2026-09-30' ),
	array( 'started' => 5, 'finished' => 2, 'active' => 9, 'hours' => 21.0, 'contributions' => 0, 'teams' => 0, 'institutions' => 1, 'countries' => 0 )
);

// The setting decides which statuses are current: emptied, Sam and Kofi are a past status with no
// end day that did not start in the period, and leave the active set, Sam's nine hours with him.
$GLOBALS['settings']['student_statuses'] = array();
$no_current = $N::program( '2025-10-01', '2026-09-30' );
$GLOBALS['settings']['student_statuses'] = $main_statuses;

ck( 'the current statuses are the student_statuses setting: with none, a student with no end day who started before the period is not active', array( $no_current['active'], $no_current['hours'], $no_current['started'], $no_current['finished'] ), array( 7, 12.0, 5, 2 ) );

// Kofi and Rafa on their own, each with hours and a team, so what being active adds is seen.
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I3 ) ] = roster(
	array(
		student( 'recSEED0000000214', 'Kofi Student', 'In Sensei', $I3, '', '', '3', 'Core' ),
		student( 'recSEED0000000221', 'Rafa Student', 'Graduate', $I3, '', '2026-05-20', '8', 'Design' ),
	)
);
ck( 'a graduate with only an end day in the period is finished and active, and a current student with no dates is active: their hours and teams count, and neither started in it',
	$N::program( '2025-10-01', '2026-09-30' ),
	array( 'started' => 0, 'finished' => 1, 'active' => 2, 'hours' => 11.0, 'contributions' => 2, 'teams' => 2, 'institutions' => 1, 'countries' => 0 )
);

// Hours below zero, and a start day that does not exist.
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I3 ) ] = roster(
	array(
		student( 'recSEED0000000224', 'Vera Student', 'In Sensei', $I3, '2026-01-05', '', '-4' ),
		student( 'recSEED0000000225', 'Wes Student', 'In Sensei', $I3, '2026-02-31', '' ),
	)
);
ck( 'hours below zero are none, and a start day that does not exist is no start day: not started, though a current student with no day of start is active',
	$N::program( '2025-10-01', '2026-09-30' ),
	array( 'started' => 1, 'finished' => 0, 'active' => 2, 'hours' => 0.0, 'contributions' => 0, 'teams' => 0, 'institutions' => 1, 'countries' => 0 )
);
$GLOBALS['opts'] = $main_opts;

// The students the Students table files under no institution: on no roster, in the program's
// figures all the same, and adding no institution and no country.
$unlinked_before = $N::program( '2025-10-01', '2026-09-30' );
$GLOBALS['opts'][ WPCPM_Roster_Index::OPT_UNLINKED ] = roster( array( student( 'recSEED0000000226', 'Uma Student', 'In Sensei', '', '2026-01-19', '', '4', 'Core' ) ) );
$unlinked_after = $N::program( '2025-10-01', '2026-09-30' );
$GLOBALS['opts'] = $main_opts;

ck( 'a student filed under no institution is started and active, her hours and team count, and she adds no institution and no country',
	array( $unlinked_after['started'] - $unlinked_before['started'], $unlinked_after['active'] - $unlinked_before['active'], $unlinked_after['hours'] - $unlinked_before['hours'], $unlinked_after['contributions'] - $unlinked_before['contributions'], $unlinked_after['teams'] - $unlinked_before['teams'], $unlinked_after['institutions'] - $unlinked_before['institutions'], $unlinked_after['countries'] - $unlinked_before['countries'] ),
	array( 1, 1, 4.0, 1, 1, 0, 0 )
);

ck( 'a period that ends before it starts, or is not two days, counts nothing', array( $N::program( '2026-09-30', '2025-10-01' ), $N::program( '', '2026-09-30' ), $N::program( '2025-10-01', '2026-02-30' ) ), array( $zero, $zero, $zero ) );
ck( 'hours print with one decimal only when they have one', array( $N::format_hours( 118.5 ), $N::format_hours( 10.0 ), $N::format_hours( 118.46 ), $N::format_hours( 2.04 ), $N::format_hours( 1234.0 ), $N::format_hours( 0 ) ), array( '118.5', '10', '118.5', '2', '1,234', '0' ) );

$track_zero = array( 'started' => 0, 'active' => 0, 'hours' => 0.0, 'contributions' => 0, 'teams' => 0, 'institutions' => 0, 'countries' => 0 );

ck( 'a track\'s figures: the program\'s over the rows whose status is on it, but finished, which the record cannot tell of a track, and its name',
	array( $N::track( '150h', '2025-10-01', '2026-09-30' ), $N::track( '50h', '2025-10-01', '2026-09-30' ), $N::track( 'dev', '2025-10-01', '2026-09-30' ) ),
	array(
		array( 'started' => 2, 'active' => 2, 'hours' => 12.0, 'contributions' => 1, 'teams' => 1, 'institutions' => 2, 'countries' => 2, 'label' => 'WordPress Credits Program 150h' ),
		array( 'started' => 1, 'active' => 1, 'hours' => 6.5, 'contributions' => 1, 'teams' => 2, 'institutions' => 1, 'countries' => 1, 'label' => 'WordPress Credits Program 50h' ),
		array_merge( $track_zero, array( 'label' => 'Developer Track' ) ),
	)
);
ck( 'no key is no track: a finished state maps to no key, and must not count every graduate as the track\'s', $N::track( '', '2025-10-01', '2026-09-30' ), array_merge( $track_zero, array( 'label' => '' ) ) );

echo "\n=== The numbers: the sponsorship ===\n";

$months = array( '2025-10' => 1, '2025-11' => 0, '2025-12' => 0, '2026-01' => 0, '2026-02' => 0, '2026-03' => 2, '2026-04' => 0, '2026-05' => 0, '2026-06' => 0, '2026-07' => 0, '2026-08' => 0, '2026-09' => 0 );

$sponsorship = array(
	'tier'        => 'backer',
	'tier_label'  => 'Backer',
	'from'        => '2025-10-01',
	'until'       => '2026-09-30',
	'funded_line' => "Stipends for two of the program's mentors",
	'mentors'     => array(
		array( 'name' => 'Marta Mentor Example', 'profile' => 'https://profiles.wordpress.org/marta-example/', 'students' => 1 ),
		array( 'name' => 'Oscar Mentor Example', 'profile' => 'https://profiles.wordpress.org/oscar-example/', 'students' => 2 ),
	),
	'months'      => $months,
	'offers'      => array( array( 'title' => 'One year of the premium plugin', 'claims' => 3, 'series' => $months ) ),
	'posts'       => 1,
	'events'      => array(),
	'events_held' => array(),
);

// The mentors: Marta guided Ada in the period, not Bruno, who started before it; Oscar guided Esi
// and Farid, not Dmitri, who is SPAM, nor Gosia, who starts after it. The claims: October's and the
// two in March; not September 2025's or October 2026's, outside the period, nor April's, voided;
// and not the offer whose one claim is from 2024, nor another sponsor's.
ck( 'the sponsorship: the tier and the term, the funded line, the mentors and the students each guided, the claims by month inside the period, the posts published in it, the events',
	$N::sponsorship( $A, $row_a, '2025-10-01', '2026-09-30' ),
	$sponsorship
);

// A term a year earlier counts that term's months and no others, whatever today is: the claim of
// September 2025 and the ended offer's one claim, of December 2024, are in it; nothing after is.
$earlier = array( '2024-10' => 0, '2024-11' => 0, '2024-12' => 0, '2025-01' => 0, '2025-02' => 0, '2025-03' => 0, '2025-04' => 0, '2025-05' => 0, '2025-06' => 0, '2025-07' => 0, '2025-08' => 0, '2025-09' => 0 );
$earlier_answer = $N::sponsorship( $A, $row_a, '2024-10-01', '2025-09-30' );
ck( 'the claims of an earlier term are that term\'s months alone, an ended offer listed for the claim it drew in them, and the post of June 2025 in it',
	array( $earlier_answer['months'], $earlier_answer['offers'], $earlier_answer['posts'], array_column( $earlier_answer['mentors'], 'students' ) ),
	array(
		array_merge( $earlier, array( '2024-12' => 1, '2025-09' => 1 ) ),
		array(
			array( 'title' => 'One year of the premium plugin', 'claims' => 1, 'series' => array_merge( $earlier, array( '2025-09' => 1 ) ) ),
			array( 'title' => 'A hosting trial', 'claims' => 1, 'series' => array_merge( $earlier, array( '2024-12' => 1 ) ) ),
		),
		1,
		array( 1, 0 ),
	)
);

// Two more linked mentors, on another sponsor: one not Active, whom the sponsorship index does not
// hold and the report does not name, and one Active with no account here to count students from.
ck( 'a linked mentor who is not Active is not named, and one with no account on the site guided no one it can count',
	$N::sponsorship( $F, WPCPM_Sponsors_Index::row( $F ), '2026-01-01', '2026-12-31' )['mentors'],
	array( array( 'name' => 'Nadia Mentor Example', 'profile' => 'https://profiles.wordpress.org/nadia-example/', 'students' => 0 ) )
);

// The posts of a lapsed sponsor: once the grace date has passed, the daily run puts back to
// pending the posts a Friend may not keep, dated as they were published. They were published
// in the period all the same; a pending post that was never published was not.
ck( 'the posts published in the period count whether they are still published or the daily run put them dormant, and a post never published does not, nor a dormant one from before it',
	$N::sponsorship( $F, WPCPM_Sponsors_Index::row( $F ), '2026-01-01', '2026-12-31' )['posts'],
	2
);

// The months a caller hands the claims class: a value that is not a month is dropped, and a month
// named twice is one column.
$filtered = WPCPM_Sponsor_Claims::stats_for_months( $A, array( '2026-03', 'x', '2026-03', array( '2026-04' ) ) );
ck( 'stats_for_months() keeps the months it is handed that are months, each once', array( $filtered['months'], $filtered['offers'][801]['series'], $filtered['offers'][801]['month'] ), array( array( '2026-03' ), array( '2026-03' => 2 ), 2 ) );

$tier_of = static function ( array $answer ) {
	return array( $answer['tier'], $answer['tier_label'], $answer['funded_line'], $answer['events'] );
};

ck( 'the funded line from Backer up only, the events a Champion is invited to, and the tier held on the period\'s last day: a day after the term, a Friend\'s',
	array(
		$tier_of( $N::sponsorship( $B, WPCPM_Sponsors_Index::row( $B ), '2025-09-01', '2026-08-31' ) ),
		$tier_of( $N::sponsorship( $A, array_merge( $row_a, array( 'tier' => 'champion' ) ), '2025-10-01', '2026-09-30' ) ),
		$tier_of( $N::sponsorship( $A, $row_a, '2025-10-02', '2026-10-01' ) ),
	),
	array(
		array( 'supporter', 'Supporter', '', array() ),
		array( 'champion', 'Champion', "Stipends for two of the program's mentors", array( 'partners_session', 'co_hosted_event' ) ),
		array( 'mentor', 'Friend', '', array() ),
	)
);

echo "\n=== The numbers: the events held ===\n";

// A Champion with no term, whose events the store itself plans, approves and fills, its clock held
// where each step needs it: two held in the twelve months to 30 September 2026, one approved and
// then called off, and one still to come on the day the report is drafted. Only a sponsor with no
// term can hold two events in one year: a term holds one. The people on them are two program
// managers, the one audience this fixture's capability stand-in answers, each named for the privacy
// walk below; every event carries the call link and the description the walk looks for too.
$E       = 'spoAAAA0000000009';
$joiners = array( 9 => 'Yara Joinersentinel', 10 => 'Ravi Secondjoiner' );

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $E, 'name' => 'Lighthouse Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' ) ) );

foreach ( $joiners as $joiner_id => $joiner_name ) {
	$GLOBALS['users'][ $joiner_id ]             = new WP_User( $joiner_id, $joiner_name );
	$GLOBALS['users'][ $joiner_id ]->user_email = str_replace( ' ', '.', strtolower( $joiner_name ) ) . '@people.example';
}

/** A UTC moment, written `Y-m-d H:i`. */
function utc( $when ) { return (int) strtotime( $when . ' UTC' ); }

/** An event's fields as the planner's form hands them to the store: every one with the same call link and description. */
function event_fields( $start, $title, $minutes = 60 ) {
	return array( 'start' => (int) $start, 'minutes' => (int) $minutes, 'places' => 0, 'title' => $title, 'description' => 'What the webinar covers, Wrenfeather.', 'link' => 'https://call.example/sentinel-room-4096', 'zone' => 'Europe/Warsaw' );
}

$GLOBALS['event_now'] = utc( '2025-11-02 12:00' );
WPCPM_Sponsor_Event::for_tests( static function () { return $GLOBALS['event_now']; } );

$trail_mark                                = count( $GLOBALS['audit'] );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$GLOBALS['manage']                         = array( 7, 8, 9, 10 );

$e1      = WPCPM_Sponsor_Event::plan( $E, event_fields( utc( '2025-11-20 15:00' ), 'Building accessible blocks', 90 ), 7, 'manager' );
$e_steps = array( WPCPM_Sponsor_Event::approve( $e1, 7 ), WPCPM_Sponsor_Event::join( $e1, 9 ), WPCPM_Sponsor_Event::join( $e1, 10 ) );

$GLOBALS['event_now'] = utc( '2025-12-01 12:00' );
$e2                   = WPCPM_Sponsor_Event::plan( $E, event_fields( utc( '2026-01-15 15:00' ), 'A webinar called off' ), 7, 'manager' );
$e_steps[]            = WPCPM_Sponsor_Event::approve( $e2, 7 );
$e_steps[]            = WPCPM_Sponsor_Event::cancel( $e2, 7 );
$e3                   = WPCPM_Sponsor_Event::plan( $E, event_fields( utc( '2026-05-12 17:30' ), 'Your first plugin review' ), 7, 'manager' );
$e_steps[]            = WPCPM_Sponsor_Event::approve( $e3, 7 );
$e_steps[]            = WPCPM_Sponsor_Event::join( $e3, 9 );

$GLOBALS['event_now'] = utc( '2026-06-01 12:00' );
$e4                   = WPCPM_Sponsor_Event::plan( $E, event_fields( utc( '2026-09-29 16:00' ), 'An autumn questions hour' ), 7, 'manager' );
$e_steps[]            = WPCPM_Sponsor_Event::approve( $e4, 7 );
$e_steps[]            = WPCPM_Sponsor_Event::join( $e4, 10 );

// An event of the program's own, held in the same twelve months with a person on it: no sponsor's,
// so no sponsor's report counts it. Asked through a guard, so a store without the program's own
// events fails the check below rather than ending the run here.
$program_held = method_exists( 'WPCPM_Sponsor_Event', 'plan_program' ) ? WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2026-07-01 15:00' ), 'The partners session' ), 7 ) : null;
$program_join = is_int( $program_held ) ? WPCPM_Sponsor_Event::join( $program_held, 9 ) : null;

// The report is drafted on 10 September, before the last of them takes place.
$GLOBALS['event_now'] = utc( '2026-09-10 12:00' );
$GLOBALS['manage']    = array( 7, 8 );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );

// The rows the store wrote on the trail are its own, walked by bin/test-sponsor-events.php, and are
// set aside so that the trail this suite walks holds the report's alone.
$GLOBALS['audit'] = array_slice( $GLOBALS['audit'], 0, $trail_mark );

ck( 'the store planned the four events, approved them, called one off and put the people on them, every step taken',
	array( is_int( $e1 ), is_int( $e2 ), is_int( $e3 ), is_int( $e4 ), $e_steps ),
	array( true, true, true, true, array( true, true, true, true, array( 'told' => 0, 'pending' => 0 ), true, true, true, true ) )
);

/** A sponsorship's events held, or a mark that fails a check rather than the run when there are none to read. */
function held_of( array $sponsorship ) { return array_key_exists( 'events_held', $sponsorship ) ? $sponsorship['events_held'] : 'no events held'; }

/** The titles of a list of events held, or what came back instead of one. */
function titles_of( $held ) { return is_array( $held ) ? array_column( $held, 'title' ) : $held; }

$row_e         = WPCPM_Sponsors_Index::row( $E );
$held_expected = array(
	array( 'title' => 'Building accessible blocks', 'start' => utc( '2025-11-20 15:00' ), 'minutes' => 90, 'joined' => 2 ),
	array( 'title' => 'Your first plugin review', 'start' => utc( '2026-05-12 17:30' ), 'minutes' => 60, 'joined' => 1 ),
);

ck( 'the sponsorship lists the events the sponsor held in the period, soonest first, each its title, its start, its length and how many were on it, the events store\'s own answer: not the one called off, not the one still to come',
	array( held_of( $N::sponsorship( $E, $row_e, '2025-10-01', '2026-09-30' ) ), WPCPM_Sponsor_Event::held_in( $E, '2025-10-01', '2026-09-30' ) ),
	array( $held_expected, $held_expected )
);
ck( 'and not the program\'s own, held in the same months with a person on it: planned, joined and over, and no sponsor\'s',
	array( is_int( $program_held ), $program_join, is_int( $program_held ) ? WPCPM_Sponsor_Event::sponsor_of( get_post( $program_held ) ) : 'no event', in_array( 'The partners session', titles_of( held_of( $N::sponsorship( $E, $row_e, '2025-10-01', '2026-09-30' ) ) ), true ) ),
	array( true, true, '', false )
);

$GLOBALS['event_now'] = utc( '2026-10-01 12:00' );
$after_term           = held_of( $N::sponsorship( $E, $row_e, '2025-10-01', '2026-09-30' ) );
$GLOBALS['event_now'] = utc( '2026-09-10 12:00' );

ck( 'the one still to come is counted once it is over, as a report generated again after the term counts it', titles_of( $after_term ), array( 'Building accessible blocks', 'Your first plugin review', 'An autumn questions hour' ) );
ck( 'none in the term before, and none for a period that is not two days in order',
	array( held_of( $N::sponsorship( $E, $row_e, '2024-10-01', '2025-09-30' ) ), held_of( $N::sponsorship( $E, $row_e, '2026-09-30', '2025-10-01' ) ), held_of( $N::sponsorship( $E, $row_e, '2025-10-01', '2026-02-30' ) ) ),
	array( array(), array(), array() )
);

echo "\n=== The sections per tier ===\n";

$two_tracks = array( array( 'key' => 'dev', 'label' => 'Developer Track', 'post' => 40 ), array( 'key' => 'growth', 'label' => 'Growth Track', 'post' => 41 ) );
$keys_of    = static function ( $tier, array $tracks = array() ) {
	return array_keys( WPCPM_Sponsor_Report::sections_for( $tier, $tracks ) );
};

ck( 'from the policy: program and sponsorship with the program report, the cost line from Backer up, one track block for a Partner\'s tracks, looking ahead always',
	array( $keys_of( 'mentor' ), $keys_of( 'supporter' ), $keys_of( 'backer' ), $keys_of( 'champion' ), $keys_of( 'partner', $two_tracks ), $keys_of( 'partner' ), $keys_of( 'gold', $two_tracks ), $keys_of( 'backer', $two_tracks ) ),
	array(
		array( 'ahead' ),
		array( 'program', 'sponsorship', 'ahead' ),
		array( 'program', 'sponsorship', 'cost_line', 'ahead' ),
		array( 'program', 'sponsorship', 'cost_line', 'ahead' ),
		array( 'program', 'sponsorship', 'cost_line', 'track', 'ahead' ),
		array( 'program', 'sponsorship', 'cost_line', 'ahead' ),
		array( 'ahead' ),
		array( 'program', 'sponsorship', 'cost_line', 'ahead' ),
	)
);

$partner_sections = $R::sections_for( 'partner', $two_tracks );

ck( 'each says its title, whether it is generated, whether a manager writes under it, and the tracks it is drawn once for',
	$partner_sections,
	array(
		'program'     => array( 'title' => 'The program this year', 'generated' => true, 'narrative' => false, 'per_track' => array() ),
		'sponsorship' => array( 'title' => 'Your sponsorship', 'generated' => true, 'narrative' => false, 'per_track' => array() ),
		'cost_line'   => array( 'title' => 'The cost line', 'generated' => false, 'narrative' => true, 'per_track' => array() ),
		'track'       => array( 'title' => 'The tracks', 'generated' => true, 'narrative' => true, 'per_track' => array( 'dev' => 'Developer Track', 'growth' => 'Growth Track' ) ),
		'ahead'       => array( 'title' => 'Looking ahead', 'generated' => false, 'narrative' => true, 'per_track' => array() ),
	)
);
ck( 'one track is the track; a track with no key is none, and one with no label is named by its key',
	array( $R::sections_for( 'partner', array( $two_tracks[0] ) )['track']['title'], $R::sections_for( 'partner', array( array( 'label' => 'Keyless' ), array( 'key' => 'growth' ) ) )['track']['per_track'] ),
	array( 'The track', array( 'growth' => 'growth' ) )
);
ck( 'the narratives a report takes: the cost line, one per track, looking ahead', $R::narrative_keys( $partner_sections ), array( 'cost_line', 'track_dev', 'track_growth', 'ahead' ) );

echo "\n=== Generating, and what it refuses ===\n";

$GLOBALS['uid'] = 7;

$refused = array(
	code_of( $R::generate( 'spoZZZZ0000000099', '2026-09-30' ) ),
	code_of( $R::generate( 'not-a-key', '2026-09-30' ) ),
	code_of( $R::generate( $A, '2026-02-30' ) ),
	code_of( $R::generate( $A, '30/09/2026' ) ),
	code_of( $R::generate( $M, '2026-06-30' ) ),
	code_of( $R::generate( $A, '2027-03-31' ) ),
);

ck( 'a sponsor the store does not hold, a value that is not a day, a Friend, and a day after the term, when the tier held is a Friend\'s: each refused, and nothing written',
	array( $refused, report_posts(), $GLOBALS['audit'] ),
	array( array( 'wpcpm_sreport_sponsor', 'wpcpm_sreport_sponsor', 'wpcpm_sreport_date', 'wpcpm_sreport_date', 'wpcpm_sreport_tier', 'wpcpm_sreport_tier' ), array(), array() )
);

$t0  = time();
$a26 = $R::generate( $A, '2026-09-30', 'manager', '2026-10-01' );
$t1  = time();

$post_a26 = get_post( $a26 );

ck( 'a report is a private post titled for the sponsor and the period, its slug the key and the day, by the manager who drafted it, and nothing in post_content',
	array( is_int( $a26 ), $post_a26->post_type, $post_a26->post_status, $post_a26->post_title, $post_a26->post_name, (int) $post_a26->post_author, $post_a26->post_content ),
	array( true, 'wpcpm_sponsor_report', 'private', 'Mango Example, October 2025 to September 2026', 'sreport-spoaaaa0000000001-2026-09-30', 7, '' )
);
ck( 'keyed by the sponsor and the period end, a draft with no narrative written yet',
	array( get_post_meta( $a26, $R::META_SPONSOR, true ), get_post_meta( $a26, $R::META_UNTIL, true ), get_post_meta( $a26, $R::META_STATE, true ), get_post_meta( $a26, $R::META_SECTIONS, true ), $R::state( $post_a26 ), $R::sponsor_of( $post_a26 ), $R::until_of( $post_a26 ) ),
	array( $A, '2026-09-30', 'draft', array(), 'draft', $A, '2026-09-30' )
);
ck( 'one row on the sponsor\'s audit trail: drafted, by the manager, against the index',
	$GLOBALS['audit'],
	array( array( 'kind' => 'sponsor_report_drafted', 'sponsor' => $A, 'subject' => (string) $a26, 'actor' => 7, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'A sponsor report was drafted for the term ending September 30, 2026.', 'data' => array( 'until' => '2026-09-30', 'regenerated' => 0 ) ) )
);

$snapshot  = $R::snapshot( $post_a26 );
$generated = $snapshot['generated'];
$stored    = get_post_meta( $a26, $R::META_DATA, true );

$snapshot['generated'] = 0;
$stored['generated']   = 0;

ck( 'the snapshot was generated as the report was', $generated >= $t0 && $generated <= $t1, true );
ck( 'the snapshot: its version, the day its figures were read, the sponsor, the period, the program, the sponsorship, and no track for a Backer',
	$snapshot,
	array( 'v' => 1, 'generated' => 0, 'as_of' => '2026-10-01', 'sponsor' => $A, 'from' => '2025-10-01', 'until' => '2026-09-30', 'program' => $program, 'sponsorship' => $sponsorship, 'tracks' => array() )
);
ck( 'and what is stored is what snapshot() answers', $stored, $snapshot );

ck( 'find() answers the report for a sponsor and a period end, and nothing for another end, another sponsor, or values that are not one',
	array( ids( array( $R::find( $A, '2026-09-30' ) ) ), $R::find( $A, '2025-09-30' ), $R::find( $B, '2026-09-30' ), $R::find( '', '' ), $R::find( $A, '2026-9-30' ), $R::find( 'not-a-key', '2026-09-30' ) ),
	array( array( $a26 ), null, null, null, null, null )
);

$again = $R::generate( $A, '2026-09-30' );

ck( 'a second report for one period end is refused, the manager told to open the first, and nothing is written',
	array( code_of( $again ), is_wp_error( $again ) ? $again->get_error_message() : '', report_posts(), count( $GLOBALS['audit'] ) ),
	array( 'wpcpm_sreport_exists', 'A report for that term exists; open it.', array( $a26 ), 1 )
);

$a25 = $R::generate( $A, '2025-09-30', 'manager', '2026-10-01' );

ck( 'a manager may draft for another period end the tier held; reports_of() lists a sponsor\'s reports newest period first',
	array( get_post( $a25 )->post_title, ids( $R::reports_of( $A ) ), $R::reports_of( $B ), $R::reports_of( 'not-a-key' ) ),
	array( 'Mango Example, October 2024 to September 2025', array( $a26, $a25 ), array(), array() )
);

// Another request's look at the report the moment the sponsor's lock is given back.
$GLOBALS['delete_hook'] = array(
	$R::LOCK_PREFIX . $P2,
	static function () use ( $R, $P2 ) {
		$found                 = $R::find( $P2, '2026-09-30', true );
		$GLOBALS['at_release'] = $found instanceof WP_Post ? get_post_meta( $found->ID, $R::META_SECTIONS, true ) : 'no report yet';
	},
);

$p26    = $R::generate( $P2, '2026-09-30', 'manager', '2026-10-01' );
$p_post = get_post( $p26 );

ck( 'a Partner\'s report carries one block of figures per track it partners, from the Track Builder\'s own tracks',
	$R::snapshot( $p_post )['tracks'],
	array( array( 'key' => '150h', 'started' => 2, 'active' => 2, 'hours' => 12.0, 'contributions' => 1, 'teams' => 1, 'institutions' => 2, 'countries' => 2, 'label' => 'WordPress Credits Program 150h' ) )
);

$stored_tracks = get_post_meta( $p26, $R::META_DATA, true )['tracks'];

ck( 'no track block says how many finished: not the numbers\' answer, not the stored snapshot, not what snapshot() answers',
	array( array_key_exists( 'finished', $N::track( '150h', '2025-10-01', '2026-09-30' ) ), array_key_exists( 'finished', $stored_tracks[0] ), array_key_exists( 'finished', $R::snapshot( $p_post )['tracks'][0] ) ),
	array( false, false, false )
);
ck( 'and its sections are the stored tier\'s and the stored tracks\', one narrative per track, the track\'s begun with the words the track was last published with',
	array( array_keys( $R::sections_of( $p_post ) ), $R::narratives( $p_post ) ),
	array( array( 'program', 'sponsorship', 'cost_line', 'track', 'ahead' ), array( 'cost_line' => '', 'track_150h' => $track_words, 'ahead' => '' ) )
);
ck( 'stored as save_narratives() stores a manager\'s words, slashed, so the backslashes come back whole; with nothing under the other keys, nothing of the edit saved since and never published, and all of it there before the sponsor\'s lock is given back',
	array( get_post_meta( $p26, $R::META_SECTIONS, true ), false !== strpos( serialize( get_post_meta( $p26, $R::META_SECTIONS, true ) ), 'An edit saved' ), $GLOBALS['at_release'] ?? 'the lock was never given back' ),
	array( array( 'track_150h' => $track_words ), false, array( 'track_150h' => $track_words ) )
);

$old_version      = get_post_meta( $a25, $R::META_DATA, true );
$bumped           = $old_version;
$bumped['v']      = 2;
$GLOBALS['pmeta'][ $a25 ][ $R::META_DATA ] = $bumped;

ck( 'a snapshot stored at another version reads as absent, never misread, and a report with no snapshot has no sections and takes no narrative',
	array( $R::snapshot( get_post( $a25 ) ), $R::sections_of( get_post( $a25 ) ), $R::narratives( get_post( $a25 ) ), $R::save_narratives( get_post( $a25 ), array( 'ahead' => 'Not while it cannot be read.' ), 7 ), get_post_meta( $a25, $R::META_SECTIONS, true ), revisions_of( $a25 ) ),
	array( array(), array(), array(), false, array(), array() )
);
$GLOBALS['pmeta'][ $a25 ][ $R::META_DATA ] = $old_version;

// A manager's draft with nobody signed in, from WP-CLI or a hook on cron, is the system's.
$GLOBALS['uid'] = 0;
$audit_mark     = count( $GLOBALS['audit'] );
$f_report       = $R::generate( $F, '2026-12-31', 'manager', '2026-10-01' );
$f_audit        = array_slice( $GLOBALS['audit'], $audit_mark );
$GLOBALS['uid'] = 7;

ck( 'a draft asked for by no account is on the system\'s ground, not a manager\'s', array( is_int( $f_report ), count( $f_audit ), isset( $f_audit[0] ) ? array( $f_audit[0]['actor'], $f_audit[0]['ground'] ) : array() ), array( true, 1, array( 0, 'system' ) ) );
wp_delete_post( $f_report, true );

echo "\n=== The privacy walk ===\n";

ck( 'the walk finds a student, an address and a key naming a person when they are there, and passes a company',
	array( count( leaks( array( 'x' => 'Ada Student' ) ) ) > 0, count( leaks( array( 'x' => 'someone@anywhere.example' ) ) ) > 0, count( leaks( array( 'email' => '' ) ) ) > 0, leaks( array( 'x' => 'Mango Example' ) ) ),
	array( true, true, true, array() )
);
ck( 'and finds a student and an address inside a whole page, which is how a printed document is walked',
	array( count( leaks( array( '<p>Written by Esi Student.</p>' ) ) ) > 0, count( leaks( array( '<td>Bruno</td>' ) ) ) > 0, addresses( '<p>Write to someone@anywhere.example today.</p>' ) ),
	array( true, true, array( 'someone@anywhere.example' ) )
);

$walked = array(
	'stored'   => array( get_post_meta( $a26, $R::META_DATA, true ), get_post_meta( $a25, $R::META_DATA, true ), get_post_meta( $p26, $R::META_DATA, true ) ),
	'answered' => array( $R::snapshot( get_post( $a26 ) ), $R::snapshot( get_post( $a25 ) ), $R::snapshot( $p_post ) ),
	'titles'   => array( get_post( $a26 )->post_title, get_post( $a25 )->post_title, $p_post->post_title, $R::title_for( $row_a, $R::period( '2026-09-30' ) ), $R::title_for( WPCPM_Sponsors_Index::row( $P2 ), $R::period( '2026-09-30' ) ) ),
	'sections' => array(),
	// The page a sponsor prints, for a Backer's two terms and a Partner's with its track block.
	'printed'  => array( WPCPM_Sponsor_Report_Files::document( get_post( $a26 ) ), WPCPM_Sponsor_Report_Files::document( get_post( $a25 ) ), WPCPM_Sponsor_Report_Files::document( $p_post ) ),
	// And the editor a program manager writes each of them in, on the Sponsors screen (1.115.0).
	'editors'  => array( editor_markup( $a26 ), editor_markup( $a25 ), editor_markup( $p26 ) ),
	// And the rows drafting them wrote on the sponsors' audit trail, each row's message and facts.
	'audit'    => $GLOBALS['audit'],
);
foreach ( WPCPM_Sponsor_Tiers::KEYS as $tier ) {
	$walked['sections'][ $tier ] = $R::sections_for( $tier, WPCPM_Tracks::partner_tracks( $P2 ) );
}

ck( 'no seeded student\'s name, address, username or site, no institution\'s name or contact, no tutor, and no address at all, in the stored snapshots, what snapshot() answers, the titles, any tier\'s sections, the printed documents, the editors a manager writes them in, or the rows drafting them wrote on the audit trail', leaks( $walked ), array() );
ck( 'and the rows walked are the drafts\' own, the manager\'s and the system\'s, so the walk reads a trail with something on it', array( count( $walked['audit'] ), array_values( array_unique( array_column( $walked['audit'], 'kind' ) ) ), array_values( array_unique( array_column( $walked['audit'], 'ground' ) ) ) ), array( 4, array( 'sponsor_report_drafted' ), array( 'manager', 'system' ) ) );
ck( 'and no address anywhere inside a printed document or an editor', addresses( implode( "\n", array_merge( $walked['printed'], $walked['editors'] ) ) ), array() );
ck( 'while the documents are the reports\' own, each naming its company and its mentors', array_map( static function ( $page ) { return array( false !== strpos( $page, 'Example, October 20' ), false !== strpos( $page, 'Marta Mentor Example' ) ); }, $walked['printed'] ), array( array( true, true ), array( true, true ), array( true, false ) ) );
ck( 'and so are the editors, each drawing the figures of its own report', array_map( static function ( $page ) { return array( false !== strpos( $page, '<h2>' ) && false !== strpos( $page, 'Example, October 20' ), false !== strpos( $page, 'Marta Mentor Example' ), false !== strpos( $page, '<caption>The program this year</caption>' ) ); }, $walked['editors'] ), array( array( true, true, true ), array( true, true, true ), array( true, false, true ) ) );
ck( 'the editor names and counts the program\'s figures exactly as the printed report does, for every report walked',
	array_map( static function ( $page ) { return tbody_after( $page, 'The program this year' ); }, $walked['editors'] ),
	array_map( static function ( $page ) { return tbody_after( $page, 'The program in figures' ); }, $walked['printed'] )
);
ck( 'and those rows are the figures, not an empty table', array_map( static function ( $page ) { return substr_count( tbody_after( $page, 'The program this year' ), '<tr>' ); }, $walked['editors'] ), array( 8, 8, 8 ) );
ck( 'while the mentors are named: the one person a report names', array_column( $R::snapshot( get_post( $a26 ) )['sponsorship']['mentors'], 'name' ), array( 'Marta Mentor Example', 'Oscar Mentor Example' ) );

echo "\n=== The narratives, and the revision that holds them ===\n";

ck( 'a Backer\'s report takes two narratives, the cost line and looking ahead, empty to start', $R::narratives( get_post( $a26 ) ), array( 'cost_line' => '', 'ahead' => '' ) );

$texts = array(
	'cost_line'  => "Two mentors' stipends, C:\\budget\\2026.\n\nFigures from the cost audit.",
	'ahead'      => '<strong>Next year</strong> we grow.',
	'program'    => 'Not a narrative section.',
	'track_150h' => 'Not a section this tier takes.',
	'bogus'      => 'Nothing at all.',
);
$saved = $R::save_narratives( get_post( $a26 ), $texts, 7 );
$words = array( 'cost_line' => "Two mentors' stipends, C:\\budget\\2026.\n\nFigures from the cost audit.", 'ahead' => 'Next year we grow.' );
$rev   = latest_revision( $a26 );

ck( 'save_narratives() keeps what the report\'s sections take, a quote and a backslash whole and the tags gone, and drops the rest', array( $saved, $R::narratives( get_post( $a26 ) ), get_post_meta( $a26, $R::META_SECTIONS, true ) ), array( true, $words, $words ) );
ck( 'the meta is written before the post, so the revision WordPress takes holds this save, beside the snapshot it was written against',
	array( $rev > 0, get_post_meta( $rev, $R::META_SECTIONS, true ), get_post_meta( $rev, $R::META_DATA, true ) === get_post_meta( $a26, $R::META_DATA, true ) ),
	array( true, $words, true )
);
ck( 'and the manager who saved is the post\'s last editor', get_post_meta( $a26, '_edit_last', true ), 7 );

$R::save_narratives( get_post( $a26 ), array( 'ahead' => 'Next year we grow again.' ), 8 );

ck( 'a save changes what it names and keeps the rest, and files a revision of its own',
	array( $R::narratives( get_post( $a26 ) ), count( revisions_of( $a26 ) ), get_post_meta( latest_revision( $a26 ), $R::META_SECTIONS, true ), get_post_meta( $a26, '_edit_last', true ) ),
	array( array( 'cost_line' => $words['cost_line'], 'ahead' => 'Next year we grow again.' ), 2, array( 'cost_line' => $words['cost_line'], 'ahead' => 'Next year we grow again.' ), 8 )
);

$long_before = get_post_meta( $a26, $R::META_SECTIONS, true );
$long_saved  = $R::save_narratives( get_post( $a26 ), array( 'ahead' => str_repeat( 'a', 6000 ) ), 8 );

ck( 'a narrative over five thousand characters is refused rather than cut, and nothing is stored', array( $long_saved, get_post_meta( $a26, $R::META_SECTIONS, true ) === $long_before ), array( false, true ) );

$R::save_narratives( get_post( $a26 ), array( 'ahead' => 'Next year we grow again.' ), 8 );

ck( 'nothing is saved onto a post that is not a report', $R::save_narratives( plain_post(), array( 'ahead' => 'x' ), 7 ), false );

// The update is where WordPress takes the revision and moves the modified time, and nothing more:
// once the meta is written the words are stored, as a regeneration's figures are, and the answer
// says so, so a manager is not told their words were lost when they are there.
$GLOBALS['update_fail'] = $R::POST_TYPE;
$revs_before            = count( revisions_of( $a26 ) );
$refused_save           = $R::save_narratives( get_post( $a26 ), array( 'ahead' => 'Stored though the update was refused.' ), 8 );
$GLOBALS['update_fail'] = '';

ck( 'a save whose post update is refused is stored all the same: the words are there and the answer is yes, though no revision was taken',
	array( $refused_save, $R::narratives( get_post( $a26 ) )['ahead'], count( revisions_of( $a26 ) ) - $revs_before ),
	array( true, 'Stored though the update was refused.', 0 )
);

$R::save_narratives( get_post( $a26 ), array( 'ahead' => 'Next year we grow again.' ), 8 );

echo "\n=== A narrative counted, kept and drawn as typed ===\n";
// A narrative is typed into a box that counts it as the manager typed it. The cleaner writes a "<"
// that opens no tag as `&lt;`, with the quote marks and ampersands after it, and the browser posts
// each line break as CR LF, so a text the box let through at five thousand stores more than that:
// it is counted as typed (`WPCPM_Typed_Text::typed_length()`), kept whole, refused one character
// over, drawn back as typed, and kept as it is when posted back unedited. The sponsor's report
// prints it as it always has, escaped and paragraphed (`wpautop( esc_html() )`), which keeps the
// cleaner's entities, so a reader sees what was typed.

/** A narrative's box in the editor, by its key: the markup of its text and its maxlength, or null when it is not drawn. */
function narrative_box( $html, $key ) {
	return preg_match( '#<textarea id="wpcpm-sreport-text-' . preg_quote( $key, '#' ) . '" name="wpcpm_sreport_text_' . preg_quote( $key, '#' ) . '" rows="8" class="large-text" maxlength="(\d+)">(.*?)</textarea>#s', (string) $html, $m ) ? array( $m[2], (int) $m[1] ) : null;
}
/** What a browser shows for a text area's markup: each entity read once, a CR LF read as LF. */
function area_shows( $markup ) { return html_entity_decode( str_replace( "\r\n", "\n", (string) $markup ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
/** What a browser posts from a text area showing this text: each line break as CR LF. */
function area_posts( $shown ) { return str_replace( "\n", "\r\n", str_replace( array( "\r\n", "\r" ), "\n", (string) $shown ) ); }
/** A report's narratives as the editor's boxes post them back, unedited. */
function posted_back( $post_id ) {
	$html  = editor_markup( $post_id );
	$texts = array();
	foreach ( array_keys( WPCPM_Sponsor_Report::narratives( get_post( $post_id ) ) ) as $key ) {
		$box = narrative_box( $html, $key );
		if ( null !== $box ) { $texts[ $key ] = area_posts( area_shows( $box[0] ) ); }
	}
	return $texts;
}
/** One narrative as the printed report prints it: the paragraphs in its section. */
function printed_narrative( $post_id, $section ) {
	$body = section_of( WPCPM_Sponsor_Report_Files::document( get_post( $post_id ) ), $section );
	return preg_match_all( '#<p class="wpcpm-report-doc__p">.*?</p>#s', $body, $m ) ? implode( "\n", $m[0] ) : '';
}

$typed_head = "Costs \"audited\" & signed < 25 & 'final'\r\nSecond line\r\n";
$typed_5000 = $typed_head . str_repeat( 'w', 5000 - mb_strlen( str_replace( "\r\n", "\n", $typed_head ) ) );
$typed_kept = str_replace( "\r\n", "\n", trim( sanitize_textarea_field( $typed_5000 ) ) );
$revs       = count( revisions_of( $a26 ) );
$saved      = $R::save_narratives( get_post( $a26 ), array( 'cost_line' => $typed_5000 ), 8 );
$stored     = $R::narratives( get_post( $a26 ) )['cost_line'];

ck( 'a narrative of five thousand characters as typed, with a "<", quote marks, ampersands and CR LF breaks in it, is kept whole as the cleaner left it, each break one LF, though it stores more characters than that',
	array( $saved, $stored === $typed_kept, WPCPM_Typed_Text::typed_length( $stored ), mb_strlen( $stored ) > 5000, count( revisions_of( $a26 ) ) - $revs ),
	array( true, true, 5000, true, 1 )
);

$before = get_post_meta( $a26, $R::META_SECTIONS, true );
$revs   = count( revisions_of( $a26 ) );
$over   = $R::save_narratives( get_post( $a26 ), array( 'cost_line' => $typed_5000 . 'z', 'ahead' => 'A change beside it.' ), 7 );

ck( 'one character more is refused, not cut: the save stores nothing, the other box\'s change with it, files no revision and leaves the last editor, and the store names the box and how far over it is',
	array( $over, get_post_meta( $a26, $R::META_SECTIONS, true ) === $before, count( revisions_of( $a26 ) ) - $revs, get_post_meta( $a26, '_edit_last', true ), is_callable( array( $R, 'long_narrative' ) ) ? $R::long_narrative( get_post( $a26 ), array( 'cost_line' => $typed_5000 . 'z', 'ahead' => 'A change beside it.' ) ) : 'long_narrative() is missing' ),
	array( false, true, 0, 8, array( 'key' => 'cost_line', 'over' => 1 ) )
);

$box = narrative_box( editor_markup( $a26 ), 'cost_line' );

ck( 'the editor draws the narrative back as typed, through the shared read-back, with the maxlength the shared rule gives it',
	array( null === $box ? null : $box[0], null === $box ? null : area_shows( $box[0] ), null === $box ? null : $box[1] ),
	array( esc_textarea( WPCPM_Typed_Text::typed_text( $stored ) ), str_replace( "\r\n", "\n", $typed_5000 ), WPCPM_Typed_Text::drawn_limit( $stored, 5000 ) )
);

$before = get_post_meta( $a26, $R::META_SECTIONS, true );
$revs   = count( revisions_of( $a26 ) );
$same   = $R::save_narratives( get_post( $a26 ), posted_back( $a26 ), 9 );

ck( 'posted back unedited, as the editor draws every box, the save changes nothing: the words, the revisions and the last editor stay as they were',
	array( $same, get_post_meta( $a26, $R::META_SECTIONS, true ) === $before, count( revisions_of( $a26 ) ) - $revs, get_post_meta( $a26, '_edit_last', true ) ),
	array( true, true, 0, 8 )
);

ck( 'and the sponsor\'s report prints it as it always has, escaped and paragraphed from the stored text, which reads as typed',
	array( printed_narrative( $a26, 'cost_line' ), html_entity_decode( strip_tags( printed_narrative( $a26, 'cost_line' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
	array( trim( str_replace( '<p>', '<p class="wpcpm-report-doc__p">', wpautop( esc_html( $stored ) ) ) ), str_replace( "\r\n", "\n", $typed_5000 ) )
);

// "We <3 our <b>team</b> > all", as the cleaner stores it: the "<" before the tag it took out is left
// `&lt;`, the ">" after it raw. Drawn as stored, the box would post "<3 our team >", which the cleaner
// drops as a tag; drawn as typed, it holds the ">" as `&gt;`, with three characters more room.
$heart = 'We &lt;3 our team > all';
$GLOBALS['pmeta'][ $a26 ][ $R::META_SECTIONS ]['ahead'] = $heart;
$box    = narrative_box( editor_markup( $a26 ), 'ahead' );
$before = get_post_meta( $a26, $R::META_SECTIONS, true );
$revs   = count( revisions_of( $a26 ) );
$same   = $R::save_narratives( get_post( $a26 ), posted_back( $a26 ), 9 );

ck( 'a stored "We &lt;3 our team > all" shows with its ">" held, three characters more room for it, survives an unedited save, and prints as typed',
	array( null === $box ? null : area_shows( $box[0] ), null === $box ? null : $box[1], $same, get_post_meta( $a26, $R::META_SECTIONS, true ) === $before, count( revisions_of( $a26 ) ) - $revs, printed_narrative( $a26, 'ahead' ) ),
	array( 'We <3 our team &gt; all', 5003, true, true, 0, '<p class="wpcpm-report-doc__p">We &lt;3 our team &gt; all</p>' )
);

// A narrative with a "< 25", quote marks and ampersands: saved, drawn back exactly as typed,
// printed as typed, and saved again unedited, which changes nothing.
$plain = 'Next year "first-time" mentors & Q&A hours for teams < 25 & the \'Developer Track\'';
$R::save_narratives( get_post( $a26 ), array( 'ahead' => $plain ), 8 );
$box    = narrative_box( editor_markup( $a26 ), 'ahead' );
$before = get_post_meta( $a26, $R::META_SECTIONS, true );
$revs   = count( revisions_of( $a26 ) );
$same   = $R::save_narratives( get_post( $a26 ), posted_back( $a26 ), 9 );

ck( 'a narrative with a "< 25", quote marks and ampersands is saved, comes back in its box exactly as typed and in the printed report as typed, and an unedited save changes nothing',
	array( null === $box ? null : area_shows( $box[0] ), null === $box ? null : $box[1], html_entity_decode( strip_tags( printed_narrative( $a26, 'ahead' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $same, get_post_meta( $a26, $R::META_SECTIONS, true ) === $before, count( revisions_of( $a26 ) ) - $revs ),
	array( $plain, 5000, $plain, true, true, 0 )
);

// A narrative an earlier version cut at five thousand stored characters can end in a space or a
// line break, which the cleaner trims from the post-back: unedited, it is still the stored text,
// and the save writes nothing.
$GLOBALS['pmeta'][ $a26 ][ $R::META_SECTIONS ]['cost_line'] = 'Cut short by the old cap, at ';
$GLOBALS['pmeta'][ $a26 ][ $R::META_SECTIONS ]['ahead']     = "Cut short after a line break\n";
$back    = posted_back( $a26 );
$before  = get_post_meta( $a26, $R::META_SECTIONS, true );
$revs    = count( revisions_of( $a26 ) );
$changes = is_callable( array( $R, 'changes_narratives' ) ) ? array( $R::changes_narratives( get_post( $a26 ), $back ), $R::changes_narratives( get_post( $a26 ), array( 'ahead' => 'Words of its own.' ) ) ) : 'changes_narratives() is missing';
$same    = $R::save_narratives( get_post( $a26 ), $back, 9 );

ck( 'a narrative cut by an earlier version that ends in a space or a line break survives an unedited save: nothing written, no revision, the last editor kept, and the store says the save changes nothing',
	array( $back, $same, get_post_meta( $a26, $R::META_SECTIONS, true ) === $before, count( revisions_of( $a26 ) ) - $revs, get_post_meta( $a26, '_edit_last', true ), $changes ),
	array( array( 'cost_line' => 'Cut short by the old cap, at ', 'ahead' => "Cut short after a line break\r\n" ), true, true, 0, 8, array( false, true ) )
);

// Back to the words the checks after these were written against.
$R::save_narratives( get_post( $a26 ), array( 'cost_line' => $words['cost_line'], 'ahead' => 'Next year we grow again.' ), 8 );

echo "\n=== Regenerating ===\n";

// Farid's hours arrive and the company renames itself; the report is generated again.
$GLOBALS['opts'][ WPCPM_Roster_Index::option_name( $I2 ) ]['rows']['recSEED0000000206']['hours'] = '4';
WPCPM_Sponsors_Index::patch( $A, array( 'name' => "Mango \\ Example" ) );

$before = count( $GLOBALS['audit'] );
$revs   = count( revisions_of( $a26 ) );
$regen  = $R::regenerate( get_post( $a26 ), '2026-10-03' );
$fresh  = $R::snapshot( get_post( $a26 ) );

ck( 'regenerate() makes a fresh snapshot in the same post: the new hours, the day it was read, the new name in the title with its backslash whole',
	array( $regen, $fresh['program']['hours'], $fresh['as_of'], get_post( $a26 )->post_title, report_posts() ),
	array( true, 122.5, '2026-10-03', 'Mango \\ Example, October 2025 to September 2026', array( $a26, $a25, $p26 ) )
);
ck( 'the narratives are kept, and the revision it files holds the fresh snapshot beside them',
	array( $R::narratives( get_post( $a26 ) ), count( revisions_of( $a26 ) ) - $revs, get_post_meta( latest_revision( $a26 ), $R::META_DATA, true ) === get_post_meta( $a26, $R::META_DATA, true ), get_post_meta( latest_revision( $a26 ), $R::META_SECTIONS, true ) ),
	array( array( 'cost_line' => $words['cost_line'], 'ahead' => 'Next year we grow again.' ), 1, true, array( 'cost_line' => $words['cost_line'], 'ahead' => 'Next year we grow again.' ) )
);
ck( 'and the audit says it was generated again, the narratives kept',
	array_slice( $GLOBALS['audit'], $before ),
	array( array( 'kind' => 'sponsor_report_drafted', 'sponsor' => $A, 'subject' => (string) $a26, 'actor' => 7, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'A sponsor report was generated again for the term ending September 30, 2026, and its narratives were kept.', 'data' => array( 'until' => '2026-09-30', 'regenerated' => 1 ) ) )
);

WPCPM_Sponsors_Index::patch( $A, array( 'name' => 'Mango Example' ) );

// The snapshot is in the meta, so once it is written the regeneration is stored: a refused update of
// the post, which would only have moved its title and its modified time, loses none of the figures.
$GLOBALS['update_fail'] = $R::POST_TYPE;
$before                 = count( $GLOBALS['audit'] );
$regen_refused          = $R::regenerate( get_post( $a26 ), '2026-10-04' );
$GLOBALS['update_fail'] = '';

ck( 'a regeneration whose post update is refused is stored all the same: the fresh snapshot is there, the answer is true, and the trail says so',
	array( $regen_refused, $R::snapshot( get_post( $a26 ) )['as_of'], count( $GLOBALS['audit'] ) - $before ),
	array( true, '2026-10-04', 1 )
);

// The row's own term, the report's end, set to a tier that carries no report: that report is not
// generated again, and keeps the snapshot it has.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'mentor' ) );
ck( 'a report whose own term the row now says was a Friend\'s is not generated again, and keeps its snapshot', array( $R::regenerate( get_post( $a26 ), '2026-10-05' ), $R::snapshot( get_post( $a26 ) )['as_of'] ), array( false, '2026-10-04' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'backer' ) );

echo "\n=== A partners' narrative, pulled into a Partner's draft ===\n";

// The Partner partners three tracks for a term of its own: the 150-hour track, published with newer
// words since the draft above and a tag nobody should have typed; the 50-hour track, published with
// none, whatever its saved draft holds; and the Developer Track, published with the longest text a
// track holds, eleven paragraphs and twenty line breaks in five thousand characters, a break counted
// once, as the Track Builder's box and its save count it.
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ]['In Sensei 50h']['sponsors']   = array( $P2 );
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ]['Developer Track']['sponsors'] = array( $P2 );
WPCPM_Tracks::flush();
$longest_words = implode( "\n\n", array_merge( array_fill( 0, 10, str_repeat( 'a', 453 ) ), array( str_repeat( 'b', 450 ) ) ) );
plant_track( '150h', "Fourteen students joined the track this year, two of them \"first-time\" contributors.\n\nNotes live in C:\\mentors\\2025, <b>weekly</b>.", '' );
plant_track( '50h', '', 'Words saved and never published.' );
plant_track( 'dev', $longest_words, '' );

$newer_words = "Fourteen students joined the track this year, two of them \"first-time\" contributors.\n\nNotes live in C:\\mentors\\2025, weekly.";
$p25         = $R::generate( $P2, '2025-09-30', 'manager', '2026-10-01' );

/** Narratives with the Developer Track's told by their length, five thousand characters being too many to read. */
function dev_by_length( $narratives ) {
	if ( is_array( $narratives ) && isset( $narratives['track_dev'] ) ) {
		$narratives['track_dev'] = mb_strlen( $narratives['track_dev'] );
	}

	return $narratives;
}

ck( 'a Partner\'s draft begins each track block with the words its track was last published with, tags gone as a manager\'s are, and leaves empty the block of a track published with none, whatever that track\'s saved draft holds',
	array( dev_by_length( $R::narratives( get_post( $p25 ) ) ), dev_by_length( get_post_meta( $p25, $R::META_SECTIONS, true ) ) ),
	array( array( 'cost_line' => '', 'track_150h' => $newer_words, 'track_50h' => '', 'track_dev' => 5000, 'ahead' => '' ), array( 'track_150h' => $newer_words, 'track_dev' => 5000 ) )
);
ck( 'the longest text a track holds is the longest a report\'s block keeps, so it arrives whole, as the Developer Track\'s did',
	array( defined( 'WPCPM_Track_Definition::MAX_NARRATIVE' ) ? constant( 'WPCPM_Track_Definition::MAX_NARRATIVE' ) : null, $R::MAX_TEXT ),
	array( 5000, 5000 )
);

// The track is published again with other words; the report keeps its own, then a manager's, then
// the block a manager emptied on purpose.
plant_track( '150h', 'Words the track was published with after the draft.', '' );
$R::regenerate( get_post( $p25 ), '2026-10-02' );
$kept_pulled = $R::narratives( get_post( $p25 ) )['track_150h'];
$R::save_narratives( get_post( $p25 ), array( 'track_150h' => "A manager's own words for this partner." ), 7 );
$R::regenerate( get_post( $p25 ), '2026-10-03' );
$kept_edit = $R::narratives( get_post( $p25 ) )['track_150h'];
$R::save_narratives( get_post( $p25 ), array( 'track_150h' => '' ), 7 );
$R::regenerate( get_post( $p25 ), '2026-10-04' );
$kept_empty = $R::narratives( get_post( $p25 ) )['track_150h'];

ck( 'Regenerate keeps the report\'s own words, the copy its draft pulled, then a manager\'s edit of it, then the block emptied on purpose, and never takes the words the track was published with since',
	array( $kept_pulled, $kept_edit, $kept_empty, $R::snapshot( get_post( $p25 ) )['as_of'] ),
	array( $newer_words, "A manager's own words for this partner.", '', '2026-10-04' )
);

// The editor's Save posts every box it drew, each line break as the browser sends it, two
// characters, while its box let the text through at five thousand as it counts them, a break once.
// Counted as posted, the cap would cut the block's end, one character a break, on a press about any
// box of the report.
$pulled_dev = $R::narratives( get_post( $p25 ) )['track_dev'];
$R::save_narratives(
	get_post( $p25 ),
	array_map(
		static function ( $text ) {
			return str_replace( "\n", "\r\n", $text );
		},
		$R::narratives( get_post( $p25 ) )
	),
	7
);
$saved_dev = $R::narratives( get_post( $p25 ) )['track_dev'];

ck( 'a pulled block at the cap, eleven paragraphs and twenty line breaks in five thousand characters, comes back whole from the editor\'s Save, which posts each break as two characters',
	array( mb_strlen( $longest_words ), substr_count( $longest_words, "\n" ), mb_strlen( $pulled_dev ), mb_strlen( str_replace( "\r\n", "\n", $saved_dev ) ), $saved_dev === $pulled_dev ),
	array( 5000, 20, 5000, 5000, true )
);

// Back as the checks after this one expect them: the Partner of the 150-hour track alone, and no
// report of this term.
wp_delete_post( $p25, true );
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ]['In Sensei 50h']['sponsors']   = array();
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ]['Developer Track']['sponsors'] = array();
WPCPM_Tracks::flush();
plant_track( '150h', $track_words, 'An edit saved and never published.' );

echo "\n=== The states and their stamps ===\n";

$GLOBALS['pmeta'][ $a25 ][ $R::META_STATE ] = '';
$unknown_state = $R::state( get_post( $a25 ) );
$GLOBALS['pmeta'][ $a25 ][ $R::META_STATE ] = 'approved';
$foreign_state = $R::state( get_post( $a25 ) );
$GLOBALS['pmeta'][ $a25 ][ $R::META_STATE ] = 'draft';

ck( 'a report with no state, or one this store does not know, is a draft: never a document the sponsor reads', array( $unknown_state, $foreign_state ), array( 'draft', 'draft' ) );

$before = count( $GLOBALS['audit'] );
$t0     = time();
$fin    = $R::finalize( get_post( $a26 ), 7 );
$t1     = time();
$stamp  = $R::final_at( get_post( $a26 ) );

ck( 'finalize() marks a report final and stamps who and when', array( $fin, $R::state( get_post( $a26 ) ), $stamp['by'], $stamp['at'] >= $t0 && $stamp['at'] <= $t1, array_keys( $stamp ) ), array( true, 'final', 7, true, array( 'at', 'by' ) ) );
ck( 'the audit row: final, by the manager',
	array_slice( $GLOBALS['audit'], $before ),
	array( array( 'kind' => 'sponsor_report_final', 'sponsor' => $A, 'subject' => (string) $a26, 'actor' => 7, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'A sponsor report was marked final for the term ending September 30, 2026.', 'data' => array( 'until' => '2026-09-30' ) ) )
);

$frozen = get_post_meta( $a26, $R::META_DATA, true );
$before = count( $GLOBALS['audit'] );

ck( 'a final report is not finalized again, nor saved, nor regenerated, and nothing is written',
	array( $R::finalize( get_post( $a26 ), 8 ), $R::save_narratives( get_post( $a26 ), array( 'ahead' => 'Too late.' ), 8 ), $R::regenerate( get_post( $a26 ) ), $R::narratives( get_post( $a26 ) )['ahead'], $R::final_at( get_post( $a26 ) ) === $stamp, get_post_meta( $a26, $R::META_DATA, true ) === $frozen, count( $GLOBALS['audit'] ) - $before ),
	array( false, false, false, 'Next year we grow again.', true, true, 0 )
);
ck( 'queue() lists the drafts, oldest first; final_since() the final reports, stamped at or after a moment',
	array( ids( $R::queue() ), ids( $R::final_since( 0 ) ), ids( $R::final_since( $t0 ) ), ids( $R::final_since( $t1 + 60 ) ) ),
	array( array( $a25, $p26 ), array( $a26 ), array( $a26 ), array() )
);

$before = count( $GLOBALS['audit'] );
$reo    = $R::reopen( get_post( $a26 ), 8 );

ck( 'reopen() makes it a draft again, and the stamp goes with the state', array( $reo, $R::state( get_post( $a26 ) ), $R::final_at( get_post( $a26 ) ), get_post_meta( $a26, $R::META_FINAL, true ) ), array( true, 'draft', array(), '' ) );
ck( 'the audit row: reopened, by the manager who did it',
	array_slice( $GLOBALS['audit'], $before ),
	array( array( 'kind' => 'sponsor_report_reopened', 'sponsor' => $A, 'subject' => (string) $a26, 'actor' => 8, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'A sponsor report was reopened for the term ending September 30, 2026.', 'data' => array( 'until' => '2026-09-30' ) ) )
);
ck( 'a draft is not reopened; the queue has the report back in its place; a post that is not a report moves nowhere',
	array( $R::reopen( get_post( $a26 ), 8 ), ids( $R::queue() ), ids( $R::final_since( 0 ) ), $R::finalize( plain_post(), 7 ), count( $GLOBALS['audit'] ) - $before ),
	array( false, array( $a26, $a25, $p26 ), array(), false, 1 )
);

// Two reports marked final in the reverse of the order they were made in, the stamps set a minute
// apart as two presses a minute apart would leave them.
$R::finalize( get_post( $p26 ), 7 );
$R::finalize( get_post( $a26 ), 7 );
$GLOBALS['pmeta'][ $p26 ][ $R::META_FINAL ]['at'] = time() - 120;
$GLOBALS['pmeta'][ $a26 ][ $R::META_FINAL ]['at'] = time() - 60;

ck( 'final_since() lists the final reports in the order they were marked final, not the order they were made in', ids( $R::final_since( 0 ) ), array( $p26, $a26 ) );

echo "\n=== The printed document ===\n";

$F = 'WPCPM_Sponsor_Report_Files';

/** Whether a text holds another, for the checks that read markup. */
function has( $text, $needle ) { return false !== strpos( (string) $text, (string) $needle ); }

/** One section of a document, its heading and its body, or '' when the document has none by that key. */
function section_of( $page, $key ) {
	return 1 === preg_match( '/<section class="wpcpm-report-doc__section wpcpm-report-doc__section--' . preg_quote( $key, '/' ) . '">(.*?)<\/section>/s', (string) $page, $found ) ? $found[1] : '';
}

/** Markup with the whitespace between its tags taken out, so a check reads the elements and not their line breaks. */
function squeeze( $html ) { return (string) preg_replace( '/>\s+</', '><', trim( (string) $html ) ); }

/**
 * A stylesheet's rule blocks, comments left out: each one's selectors, trimmed, and its declarations.
 * The rules inside an at-rule are read as rules of their own.
 *
 * @param string $css A stylesheet.
 * @return array[] Each `selectors` (string[]) and `body` (string, whitespace collapsed).
 */
function css_rules( $css ) {
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', (string) preg_replace( '#/\*.*?\*/#s', '', (string) $css ), $found, PREG_SET_ORDER );
	$rules = array();
	foreach ( $found as $rule ) {
		$rules[] = array(
			'selectors' => array_map( static function ( $selector ) { return trim( (string) preg_replace( '/\s+/', ' ', $selector ) ); }, explode( ',', $rule[1] ) ),
			'body'      => trim( (string) preg_replace( '/\s+/', ' ', $rule[2] ) ),
		);
	}
	return $rules;
}

/** A table of figures as the document draws one: a caption, then one labeled row a figure. */
function figures_table( $caption, array $rows ) {
	$html = '<table class="wpcpm-report-doc__table"><caption>' . $caption . '</caption><tbody>';
	foreach ( $rows as $label => $value ) { $html .= '<tr><th scope="row">' . $label . '</th><td>' . $value . '</td></tr>'; }
	return $html . '</tbody></table>';
}

/** A month as the document names it, for the checks that build its table. */
function month_name( $key ) { return wp_date( 'F Y', strtotime( $key . '-01 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) ); }

ck( 'the four routes, the two fields, the argument a link names its report by, and the editor\'s',
	array( $F::ACTION_UPLOAD, $F::ACTION_REMOVE, $F::ACTION_DOWNLOAD, $F::ACTION_PRINT, $F::FIELD_FILE, $F::FIELD_POST, $F::ARG_REPORT, $F::ARG_EDITOR ),
	array( 'wpcpm_sreport_upload', 'wpcpm_sreport_remove', 'wpcpm_sreport_pdf', 'wpcpm_sreport_print', 'wpcpm_sreport_file', 'wpcpm_sreport_post', 'report', 'wpcpm_sreport' )
);

$hooks_held       = $GLOBALS['hooks'];
$GLOBALS['hooks'] = array();
$F::init();
$registered = array();
foreach ( $GLOBALS['hooks'] as $hook => $listeners ) {
	foreach ( $listeners as $listener ) { $registered[ $hook ][] = $listener['callback']; }
}
ksort( $registered );
$GLOBALS['hooks'] = $hooks_held;

ck( 'init() hooks the four routes, and the two links for a signed-out visitor too, who meets the login form rather than an empty page',
	$registered,
	array(
		'admin_post_nopriv_wpcpm_sreport_pdf'   => array( array( $F, 'handle_download' ) ),
		'admin_post_nopriv_wpcpm_sreport_print' => array( array( $F, 'handle_print' ) ),
		'admin_post_wpcpm_sreport_pdf'          => array( array( $F, 'handle_download' ) ),
		'admin_post_wpcpm_sreport_print'        => array( array( $F, 'handle_print' ) ),
		'admin_post_wpcpm_sreport_remove'       => array( array( $F, 'handle_remove' ) ),
		'admin_post_wpcpm_sreport_upload'       => array( array( $F, 'handle_upload' ) ),
	)
);

// The Backer's report is final here, with both narratives written; its snapshot is the one read on
// 4 October, the name a backslash had then in its title.
$a26_post = get_post( $a26 );
$a26_snap = $R::snapshot( $a26_post );
$doc      = $F::document( $a26_post );
$css      = trim( (string) file_get_contents( __DIR__ . '/../' . WPCPM_Semester_Report_Screen::STYLE_FILE ) );

ck( 'one standalone page: a doctype, the site\'s language, kept out of an index, the report\'s title, and the body the print stylesheet dresses',
	array( 0 === strpos( $doc, "<!DOCTYPE html>\n<html lang=\"en-US\">\n<head>\n<meta charset=\"utf-8\" />" ), has( $doc, '<meta name="robots" content="noindex, nofollow" />' ), has( $doc, '<title>' . esc_html( $a26_post->post_title ) . '</title>' ), has( $doc, '<body class="wpcpm-report-print wpcpm-report-print--sponsor">' ), substr( rtrim( $doc ), -7 ) ),
	array( true, true, true, true, '</html>' )
);
ck( 'the semester report\'s stylesheet inlined whole and never linked, and its print script, the one script on the page',
	array( has( $doc, "<style>\n" . $css . "\n</style>" ), preg_match( '/<link\b/i', $doc ), substr_count( $doc, '<script' ), has( $doc, '<script src="https://site.example/wp-content/plugins/wpcredits-program-manager/assets/js/report-print.js?ver=test"></script>' ) ),
	array( true, 0, 1, true )
);

$sheet_rules   = css_rules( $css );
$sponsor_rules = array_values( array_filter( $sheet_rules, static function ( $rule ) { return has( implode( ',', $rule['selectors'] ), 'wpcpm-report-print--sponsor' ); } ) );
$unscoped      = array();
foreach ( $sponsor_rules as $rule ) {
	foreach ( $rule['selectors'] as $selector ) {
		if ( 0 !== strpos( $selector, 'body.wpcpm-report-print--sponsor ' ) ) { $unscoped[] = $selector; }
	}
}
$declared = static function ( $selector ) use ( $sponsor_rules ) {
	foreach ( $sponsor_rules as $rule ) {
		if ( in_array( $selector, $rule['selectors'], true ) ) { return $rule['body']; }
	}
	return '';
};

ck( 'the stylesheet dresses the sponsor document by its own body class: room between two tables and after a note, the header cells of a table with a header row as wide as their words, the track\'s name one step below its section heading, a caption at the column\'s start',
	array(
		has( $declared( 'body.wpcpm-report-print--sponsor .wpcpm-report-doc__table + .wpcpm-report-doc__table' ), 'margin-top: 1.5em' ),
		has( $declared( 'body.wpcpm-report-print--sponsor .wpcpm-report-doc__note + .wpcpm-report-doc__table' ), 'margin-top: 1.5em' ),
		has( $declared( 'body.wpcpm-report-print--sponsor .wpcpm-report-doc__table + .wpcpm-report-doc__p' ), 'margin-top: 0.8em' ),
		has( $declared( 'body.wpcpm-report-print--sponsor .wpcpm-report-doc__table thead th' ), 'width: auto' ),
		has( $declared( 'body.wpcpm-report-print--sponsor .wpcpm-report-doc__table thead + tbody th' ), 'width: auto' ),
		has( $declared( 'body.wpcpm-report-print--sponsor .wpcpm-report-doc__subheading' ), 'font-size: 11pt' ),
		has( $declared( 'body.wpcpm-report-print--sponsor .wpcpm-report-doc__table caption' ), 'text-align: start' ),
	),
	array( true, true, true, true, true, true, true )
);
ck( 'and every one of those rules names that class at the head of every selector it lists, so the semester report is drawn exactly as before', array( count( $sponsor_rules ) >= 5, $unscoped ), array( true, array() ) );

$semester_screen_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-semester-report-screen.php' );

ck( 'the semester report\'s own document keeps its one body class', array( substr_count( $semester_screen_src, '<body class="wpcpm-report-print">' ), substr_count( $semester_screen_src, 'wpcpm-report-print--sponsor' ) ), array( 1, 0 ) );
ck( 'the report\'s title is its heading, the period is said under it, and the day its figures were read at its foot',
	array( has( $doc, '<h1 class="wpcpm-report-doc__title">' . esc_html( $a26_post->post_title ) . '</h1>' ), has( $doc, '<p class="wpcpm-report-doc__cohort">The twelve months from October 1, 2025 to September 30, 2026</p>' ), has( $doc, '<p class="wpcpm-report-doc__read">These figures were read from the program&#039;s records on October 4, 2026.</p></article>' ) ),
	array( true, true, true )
);

preg_match_all( '/<section class="wpcpm-report-doc__section wpcpm-report-doc__section--([a-z_]+)"><h2 class="wpcpm-report-doc__heading">([^<]*)<\/h2>/', $doc, $found );

ck( 'the sections a Backer\'s report carries, in the order the policy gives them, each under its own heading', array( $found[1], $found[2] ), array( array( 'program', 'sponsorship', 'cost_line', 'ahead' ), array( 'The program this year', 'Your sponsorship', 'The cost line', 'Looking ahead' ) ) );

$program_rows = array(
	'Students who started this year'                                => '3',
	'Students who finished this year'                               => '1',
	'Students active this year'                                     => '4',
	'Hours logged to date by the students active this year'         => '122.5',
	'Students active this year who contributed to a WordPress team' => '3',
	'WordPress teams they contributed to'                           => '2',
	'Institutions the students active this year study at'           => '2',
	'Countries those institutions are in'                           => '2',
);

ck( 'the program as a captioned table of every figure, in the numbers\' order, the hours labeled as the reviews asked: to date, by the students active this year',
	section_of( $doc, 'program' ),
	'<h2 class="wpcpm-report-doc__heading">The program this year</h2>' . figures_table( 'The program in figures', $program_rows )
);
ck( 'the sponsorship in brief: the tier, the term, what it funded, the posts published, and no events row for a tier invited to none',
	has( section_of( $doc, 'sponsorship' ), figures_table( 'The sponsorship in brief', array( 'Tier' => 'Backer', 'Term' => 'October 1, 2025 to September 30, 2026', 'What it funded' => 'Stipends for two of the program&#039;s mentors', 'Posts published' => '1' ) ) ),
	true
);

$mentors_table = '<table class="wpcpm-report-doc__table"><caption>The mentors you sponsored, and the students who started with each this year</caption><thead><tr><th scope="col">Mentor</th><th scope="col">WordPress.org profile</th><th scope="col">Students</th></tr></thead><tbody>'
	. '<tr><th scope="row">Marta Mentor Example</th><td><a class="wpcpm-report-doc__link" href="https://profiles.wordpress.org/marta-example/">https://profiles.wordpress.org/marta-example/</a></td><td>1</td></tr>'
	. '<tr><th scope="row">Oscar Mentor Example</th><td><a class="wpcpm-report-doc__link" href="https://profiles.wordpress.org/oscar-example/">https://profiles.wordpress.org/oscar-example/</a></td><td>2</td></tr>'
	. '</tbody></table>';

ck( 'the mentors it sponsored, each with the students who started with them in the period, the profile printed as its own address for a reader on paper', has( section_of( $doc, 'sponsorship' ), $mentors_table ), true );

$month_rows = '';
foreach ( $a26_snap['sponsorship']['months'] as $month => $count ) {
	$month_rows .= '<tr><th scope="row">' . month_name( $month ) . '</th><td>' . $count . '</td><td>' . $count . '</td></tr>';
}
$months_table = '<table class="wpcpm-report-doc__table"><caption>Offers and claims by calendar month</caption><thead><tr><th scope="col">Month</th><th scope="col">One year of the premium plugin</th><th scope="col">All offers</th></tr></thead><tbody>' . $month_rows . '</tbody></table>';

ck( 'the claims by calendar month, captioned as the reviews asked: a column for the offer that drew them and one for all offers, a row for every month of the period',
	array( has( section_of( $doc, 'sponsorship' ), $months_table ), substr_count( $month_rows, '<tr>' ), has( $month_rows, '<tr><th scope="row">October 2025</th><td>1</td><td>1</td></tr>' ), has( $month_rows, '<tr><th scope="row">March 2026</th><td>2</td><td>2</td></tr>' ) ),
	array( true, 12, true, true )
);
ck( 'a narrative is its paragraphs, a blank line between two, escaped: the apostrophe an entity and the backslash as it was typed',
	array( squeeze( section_of( $doc, 'cost_line' ) ), squeeze( section_of( $doc, 'ahead' ) ) ),
	array(
		'<h2 class="wpcpm-report-doc__heading">The cost line</h2><p class="wpcpm-report-doc__p">Two mentors&#039; stipends, C:\\budget\\2026.</p><p class="wpcpm-report-doc__p">Figures from the cost audit.</p>',
		'<h2 class="wpcpm-report-doc__heading">Looking ahead</h2><p class="wpcpm-report-doc__p">Next year we grow again.</p>',
	)
);

$a25_doc = $F::document( get_post( $a25 ) );

ck( 'a narrative nobody has written yet says so, once under each section a manager writes in, and nowhere else',
	array( substr_count( $a25_doc, '<p class="wpcpm-report-doc__note">Nothing written yet.</p>' ), section_of( $a25_doc, 'cost_line' ), section_of( $a25_doc, 'ahead' ) ),
	array( 2, '<h2 class="wpcpm-report-doc__heading">The cost line</h2><p class="wpcpm-report-doc__note">Nothing written yet.</p>', '<h2 class="wpcpm-report-doc__heading">Looking ahead</h2><p class="wpcpm-report-doc__note">Nothing written yet.</p>' )
);

$p26_doc = $F::document( $p_post );
preg_match_all( '/<section class="wpcpm-report-doc__section wpcpm-report-doc__section--([a-z_]+)">/', $p26_doc, $p_found );
$track_rows = array(
	'Students who started this year'                                => '2',
	'Students active this year'                                     => '2',
	'Hours logged to date by the students active this year'         => '12',
	'Students active this year who contributed to a WordPress team' => '1',
	'WordPress teams they contributed to'                           => '1',
	'Institutions the students active this year study at'           => '2',
	'Countries those institutions are in'                           => '2',
);

ck( 'a Partner\'s report carries the track section, one block for each track it partners: the track\'s name, its figures without finished, and its own narrative',
	array( $p_found[1], squeeze( section_of( $p26_doc, 'track' ) ) ),
	array( array( 'program', 'sponsorship', 'cost_line', 'track', 'ahead' ), '<h2 class="wpcpm-report-doc__heading">The track</h2><h3 class="wpcpm-report-doc__subheading">WordPress Credits Program 150h</h3>' . figures_table( 'WordPress Credits Program 150h in figures', $track_rows ) . '<p class="wpcpm-report-doc__p">Our mentors met the track&#039;s students every week.</p><p class="wpcpm-report-doc__p">They kept their notes in C:\\mentors\\2026 and &quot;shared&quot; them.</p>' )
);
ck( 'a sponsor with no linked mentor and no claimed offer is told so in a sentence, not with an empty table',
	array( has( section_of( $p26_doc, 'sponsorship' ), '<p class="wpcpm-report-doc__note">No mentor was linked to your sponsorship when this report was generated.</p>' ), has( section_of( $p26_doc, 'sponsorship' ), '<p class="wpcpm-report-doc__note">None of your offers was claimed in these months.</p>' ), has( section_of( $p26_doc, 'sponsorship' ), '<caption>The mentors you sponsored' ) ),
	array( true, true, false )
);

// A report whose every stored string is hostile: what a company or a manager typed, a mentor's
// name the sync brought in, a track's name. Each prints as text and never as markup.
$hostile      = wp_insert_post( array( 'post_type' => $R::POST_TYPE, 'post_status' => 'private', 'post_title' => wp_slash( '<script>alert("title")</script> & Sons' ) ) );
$hostile_data = get_post_meta( $p26, $R::META_DATA, true );

$hostile_data['sponsorship']['tier_label']  = '<b>Partner</b>';
$hostile_data['sponsorship']['funded_line'] = '<em>Stipends</em> & "more"';
$hostile_data['sponsorship']['mentors']     = array( array( 'name' => '<img src=x onerror=alert(1)>', 'profile' => 'https://profiles.example/"><script>alert(2)</script>', 'students' => 1 ) );
$hostile_data['sponsorship']['months']      = array( '2025-10' => 1 );
$hostile_data['sponsorship']['offers']      = array( array( 'title' => '<i>Offer</i>', 'claims' => 1, 'series' => array( '2025-10' => 1 ) ) );
$hostile_data['sponsorship']['events']      = array( WPCPM_Sponsor_Tiers::EVENT_PARTNERS_SESSION, WPCPM_Sponsor_Tiers::EVENT_CO_HOSTED );
$hostile_data['tracks'][0]['label']         = '<u>Track</u>';
$hostile_data['sponsorship']['events_held'] = array( array( 'title' => '<b>Webinar</b> & "friends"', 'start' => utc( '2026-03-03 10:00' ), 'minutes' => 60, 'joined' => 2 ) );

update_post_meta( $hostile, $R::META_SPONSOR, $P2 );
update_post_meta( $hostile, $R::META_UNTIL, '2026-09-30' );
update_post_meta( $hostile, $R::META_DATA, wp_slash( $hostile_data ) );
update_post_meta( $hostile, $R::META_SECTIONS, wp_slash( array( 'cost_line' => "<script>alert('cost')</script>\n\nA & B", 'track_150h' => '<i>track words</i>', 'ahead' => '"Quoted" <b>bold</b>' ) ) );

$hostile_doc    = $F::document( get_post( $hostile ) );
$hostile_editor = editor_markup( $hostile );

ck( 'every value is escaped: the title, the tier, the funded line, a mentor and a profile, an offer, a track and the narratives print as text, never as markup',
	array(
		substr_count( $hostile_doc, '<script' ),
		preg_match( '/<(b|em|i|u|img)\b/i', $hostile_doc ),
		has( $hostile_doc, '<title>&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt; &amp; Sons</title>' ),
		has( $hostile_doc, '<h1 class="wpcpm-report-doc__title">&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt; &amp; Sons</h1>' ),
		has( $hostile_doc, '<tr><th scope="row">Tier</th><td>&lt;b&gt;Partner&lt;/b&gt;</td></tr>' ),
		has( $hostile_doc, '<tr><th scope="row">What it funded</th><td>&lt;em&gt;Stipends&lt;/em&gt; &amp; &quot;more&quot;</td></tr>' ),
		has( $hostile_doc, '<tr><th scope="row">&lt;img src=x onerror=alert(1)&gt;</th><td><a class="wpcpm-report-doc__link" href="https://profiles.example/scriptalert(2)/script">https://profiles.example/&quot;&gt;&lt;script&gt;alert(2)&lt;/script&gt;</a></td><td>1</td></tr>' ),
		has( $hostile_doc, '<th scope="col">&lt;i&gt;Offer&lt;/i&gt;</th>' ),
		has( $hostile_doc, '<h3 class="wpcpm-report-doc__subheading">&lt;u&gt;Track&lt;/u&gt;</h3>' ),
		has( squeeze( $hostile_doc ), '<p class="wpcpm-report-doc__p">&lt;script&gt;alert(&#039;cost&#039;)&lt;/script&gt;</p><p class="wpcpm-report-doc__p">A &amp; B</p>' ),
		has( $hostile_doc, '<p class="wpcpm-report-doc__p">&lt;i&gt;track words&lt;/i&gt;</p>' ),
		has( $hostile_doc, '<p class="wpcpm-report-doc__p">&quot;Quoted&quot; &lt;b&gt;bold&lt;/b&gt;</p>' ),
	),
	array( 1, 0, true, true, true, true, true, true, true, true, true, true )
);
ck( 'and the events a tier is invited to are named in the brief, one after the other',
	has( $hostile_doc, '<tr><th scope="row">Events included</th><td>An invitation to the annual education partners session; A webinar or event co-hosted with the education program</td></tr>' ),
	true
);
ck( 'and an event held prints its title as text, in the printed report and in the editor alike',
	array( has( $hostile_doc, '<tr><th scope="row">March 3, 2026</th><td>&lt;b&gt;Webinar&lt;/b&gt; &amp; &quot;friends&quot;</td><td>2</td></tr>' ), has( $hostile_editor, '<tr><th scope="row">March 3, 2026</th><td>&lt;b&gt;Webinar&lt;/b&gt; &amp; &quot;friends&quot;</td><td>2</td></tr>' ), preg_match( '/<b\b/i', $hostile_editor ) ),
	array( true, true, 0 )
);
wp_delete_post( $hostile, true );

// A stylesheet that grew the two characters ending a tag would close the style element early and
// print the rest of the file as text, so the page is drawn unstyled instead; the copy in this run's
// folder is put back afterwards.
$sheet = WPCPM_PLUGIN_DIR . WPCPM_Semester_Report_Screen::STYLE_FILE;
$kept  = (string) file_get_contents( $sheet );
file_put_contents( $sheet, "body { color: #111; }\n/* </style><script>alert('sheet')</script> */\n" );
$unstyled = $F::document( $a26_post );
file_put_contents( $sheet, $kept );

ck( 'a stylesheet carrying the two characters that end a tag is dropped whole: the page is drawn unstyled rather than broken, and nothing after them runs',
	array( has( $unstyled, "<style>\n\n</style>" ), has( $unstyled, 'alert(' ), substr_count( $unstyled, '<script' ), has( $unstyled, section_of( $doc, 'program' ) ) ),
	array( true, false, 1, true )
);

$held_data         = get_post_meta( $a25, $R::META_DATA, true );
$bumped_data       = $held_data;
$bumped_data['v']  = 2;
$GLOBALS['pmeta'][ $a25 ][ $R::META_DATA ] = $bumped_data;
$unreadable = $F::document( get_post( $a25 ) );
$GLOBALS['pmeta'][ $a25 ][ $R::META_DATA ] = $held_data;

ck( 'a report whose figures cannot be read says so and draws no section, rather than a document with the wrong things in it',
	array( has( $unreadable, '<p class="wpcpm-report-doc__unreadable">This report&#039;s figures cannot be read, so the report is not shown. An Administrator can generate it again.</p>' ), has( $unreadable, '<section' ) ),
	array( true, false )
);

echo "\n=== The events held, in the report ===\n";

/** Whether every text is in the page, each after the one before it. */
function in_order( $html, array $texts ) {
	$at = -1;
	foreach ( $texts as $text ) {
		$next = strpos( (string) $html, (string) $text, $at + 1 );
		if ( false === $next ) { return false; }
		$at = $next;
	}
	return true;
}

/** One table's head and rows from the sponsorship's rows, or a mark that fails a check rather than the run when there is no such table. */
function rows_of( array $rows, $table ) { return isset( $rows[ $table ] ) ? $rows[ $table ] : 'no ' . $table . ' table'; }

// Lighthouse Example's report, drafted on 10 September on the store's clock: the two events over by
// then, and not the one still to come.
$e_report = $R::generate( $E, '2026-09-30', 'manager', '2026-09-10' );
$e_post   = get_post( $e_report );
$e_stored = get_post_meta( $e_report, $R::META_DATA, true );
$e_snap   = $R::snapshot( $e_post );
$e_rows   = $F::sponsorship_rows( $e_snap['sponsorship'] );
$e_doc    = $F::document( $e_post );
$e_editor = editor_markup( $e_report );

$events_head  = array( 'Date', 'Event', 'Joined' );
$events_table = '<table class="wpcpm-report-doc__table"><caption>Events held in this term</caption><thead><tr><th scope="col">Date</th><th scope="col">Event</th><th scope="col">Joined</th></tr></thead><tbody>'
	. '<tr><th scope="row">November 20, 2025</th><td>Building accessible blocks</td><td>2</td></tr>'
	. '<tr><th scope="row">May 12, 2026</th><td>Your first plugin review</td><td>1</td></tr>'
	. '</tbody></table>';

ck( 'the snapshot keeps the events held as the numbers answered them, stored and read back, at version 1',
	array( is_int( $e_report ), $e_snap['v'], held_of( $e_stored['sponsorship'] ), held_of( $e_snap['sponsorship'] ) ),
	array( true, 1, $held_expected, $held_expected )
);

// A report drafted before its snapshot listed the events held has no such key: it reads whole, at
// version 1, as none held, and prints nothing in their place, the very page it printed before.
$old_data = $e_stored;
unset( $old_data['sponsorship']['events_held'] );
$GLOBALS['pmeta'][ $e_report ][ $R::META_DATA ] = $old_data;
$old_snap   = $R::snapshot( $e_post );
$old_doc    = $F::document( $e_post );
$old_editor = editor_markup( $e_report );
$GLOBALS['pmeta'][ $e_report ][ $R::META_DATA ] = $e_stored;

$old_rest = isset( $old_snap['sponsorship'] ) ? $old_snap['sponsorship'] : array();
$new_rest = $e_snap['sponsorship'];
unset( $old_rest['events_held'], $new_rest['events_held'] );

ck( 'a snapshot stored without the events held, as every report drafted before them was, reads whole at version 1, as none held, and prints no table and no sentence in their place',
	array( isset( $old_snap['v'] ) ? $old_snap['v'] : 'unreadable', held_of( isset( $old_snap['sponsorship'] ) ? $old_snap['sponsorship'] : array() ), $old_rest === $new_rest, $old_doc === str_replace( $events_table, '', $e_doc ), has( $old_editor, 'Events held' ), in_order( $old_editor, array( '<caption>Your sponsorship</caption>', '</tbody></table><p class="description">No mentor was linked to this sponsor when the report was generated.</p>' ) ) ),
	array( 1, array(), true, true, false, true )
);

// Whatever a stored entry holds beside them, the snapshot passes on its title, start, length and
// count alone, typed; an entry that is not a list is none.
$odd_data                               = $e_stored;
$odd_data['sponsorship']['events_held'] = array(
	array( 'title' => '  Padded webinar  ', 'start' => (string) utc( '2026-03-03 10:00' ), 'minutes' => '45', 'joined' => '3', 'link' => 'https://call.example/sentinel-room-4096', 'description' => 'What the webinar covers, Wrenfeather.', 'people' => array( 'Yara Joinersentinel' ) ),
	'An event in a string',
);
$GLOBALS['pmeta'][ $e_report ][ $R::META_DATA ] = $odd_data;
$odd_held = held_of( $R::snapshot( $e_post )['sponsorship'] );
$GLOBALS['pmeta'][ $e_report ][ $R::META_DATA ] = $e_stored;

ck( 'the snapshot passes on each event\'s title, start, length and count, typed, and nothing else a stored entry holds: no call link, no description, no name; an entry that is not a list is none',
	$odd_held,
	array( array( 'title' => 'Padded webinar', 'start' => utc( '2026-03-03 10:00' ), 'minutes' => 45, 'joined' => 3 ) )
);

ck( 'the rows of the events table: the day each took place, in the site\'s date format, its title, and how many were on it, under three headings',
	rows_of( $e_rows, 'events' ),
	array( 'head' => $events_head, 'rows' => array( array( 'November 20, 2025', 'Building accessible blocks', '2' ), array( 'May 12, 2026', 'Your first plugin review', '1' ) ) )
);
ck( 'and no row for a report that held none, the headings kept', rows_of( $F::sponsorship_rows( $a26_snap['sponsorship'] ), 'events' ), array( 'head' => $events_head, 'rows' => array() ) );

$GLOBALS['site_tz']             = 'Pacific/Kiritimati';
$GLOBALS['opts']['date_format'] = 'j F Y';
$far_rows                       = rows_of( $F::sponsorship_rows( $e_snap['sponsorship'] ), 'events' );
$GLOBALS['site_tz']             = 'UTC';
unset( $GLOBALS['opts']['date_format'] );

ck( 'each day is read on the site\'s clock and written in the site\'s own format: fourteen hours ahead of UTC, the review that began at 17:30 UTC on 12 May took place on the 13th',
	is_array( $far_rows ) ? array_column( $far_rows['rows'], 0 ) : $far_rows,
	array( '21 November 2025', '13 May 2026' )
);

$many = rows_of( $F::sponsorship_rows( array_merge( $e_snap['sponsorship'], array( 'events_held' => array( array( 'title' => 'A late webinar', 'start' => utc( '2026-02-02 23:30' ), 'minutes' => 90, 'joined' => 1234 ) ) ) ) ), 'events' );

ck( 'an event is dated by the day it began, though it ended after midnight, and its count is in the site\'s own number format', is_array( $many ) ? $many['rows'] : $many, array( array( 'February 2, 2026', 'A late webinar', '1,234' ) ) );

$e_brief = figures_table( 'The sponsorship in brief', array( 'Tier' => 'Champion', 'Term' => 'October 1, 2025 to September 30, 2026', 'Posts published' => '0', 'Events included' => 'An invitation to the annual education partners session; A webinar or event co-hosted with the education program' ) );

ck( 'the printed report draws them as a table under the caption Events held in this term, right under the brief, whose last row names the events the tier includes, and before the mentors',
	section_of( $e_doc, 'sponsorship' ),
	'<h2 class="wpcpm-report-doc__heading">Your sponsorship</h2>' . $e_brief . $events_table . '<p class="wpcpm-report-doc__note">No mentor was linked to your sponsorship when this report was generated.</p><p class="wpcpm-report-doc__note">None of your offers was claimed in these months.</p>'
);
ck( 'a report that held none prints no events table and no sentence in its place: its sponsorship is the brief, the mentors and the claims, and nothing else',
	section_of( $doc, 'sponsorship' ),
	'<h2 class="wpcpm-report-doc__heading">Your sponsorship</h2>' . figures_table( 'The sponsorship in brief', array( 'Tier' => 'Backer', 'Term' => 'October 1, 2025 to September 30, 2026', 'What it funded' => 'Stipends for two of the program&#039;s mentors', 'Posts published' => '1' ) ) . $mentors_table . $months_table
);
ck( 'the editor draws the same rows under the same caption, right under the brief and before the mentors, as the sponsor will read them',
	array( tbody_after( $e_editor, 'Events held in this term' ), in_order( $e_editor, array( '<caption>Your sponsorship</caption>', '</tbody></table><table class="widefat striped wpcpm-sreport__table"><caption>Events held in this term</caption><thead><tr><th scope="col">Date</th><th scope="col">Event</th><th scope="col">Joined</th></tr></thead>', '</tbody></table><p class="description">No mentor was linked to this sponsor when the report was generated.</p>' ) ) ),
	array( tbody_after( $events_table, 'Events held in this term' ), true )
);

$a26_editor = editor_markup( $a26 );

ck( 'and where none was held, nothing: the brief is followed by the mentors\' table, with no table and no sentence between',
	array( has( $a26_editor, 'Events held' ), in_order( $a26_editor, array( '<caption>Your sponsorship</caption>', '</tbody></table><table class="widefat striped wpcpm-sreport__table"><caption>The mentors sponsored, and the students who started with each in these twelve months</caption>' ) ) ),
	array( false, true )
);

// The walk, looking besides for the two people on the events, their addresses, the call link and the
// description: each was in the store the report was drafted from, so its absence is the report's.
ck( 'the people on the events are accounts with those names, on the events the report counts, and the call link and the description are the events\' own',
	array( get_user_by( 'id', 9 )->display_name, get_user_by( 'id', 10 )->display_name, WPCPM_Sponsor_Event::joiners( $e1 ), WPCPM_Sponsor_Event::joiners( $e3 ), WPCPM_Sponsor_Event::link_of( get_post( $e1 ) ), get_post( $e3 )->post_content ),
	array( 'Yara Joinersentinel', 'Ravi Secondjoiner', array( 9, 10 ), array( 9 ), 'https://call.example/sentinel-room-4096', 'What the webinar covers, Wrenfeather.' )
);

$needles_held       = $GLOBALS['needles'];
$GLOBALS['needles'] = array_merge( $GLOBALS['needles'], array( 'Yara Joinersentinel', 'Ravi Secondjoiner', 'joinersentinel', 'secondjoiner', 'call.example', 'sentinel-room-4096', 'Wrenfeather', 'What the webinar covers' ) );

ck( 'the walk finds a joiner\'s name, an address, the call link and the description when they are there',
	array_map( static function ( $text ) { return count( leaks( $text ) ) > 0; }, array( 'Joined by Yara Joinersentinel', 'ravi.secondjoiner@people.example', 'https://call.example/sentinel-room-4096', '<p>What the webinar covers, Wrenfeather.</p>' ) ),
	array( true, true, true, true )
);
ck( 'no one on an event, no address of theirs, no call link and no description, in the stored snapshot, what snapshot() answers, the rows, the printed report or the editor; and no seeded student either',
	leaks( array( 'stored' => $e_stored, 'answered' => $e_snap, 'rows' => $e_rows, 'printed' => $e_doc, 'editor' => $e_editor ) ),
	array()
);
ck( 'and no address at all in the printed report or the editor', addresses( $e_doc . "\n" . $e_editor ), array() );
ck( 'while the events are in each of them, by their titles',
	array( titles_of( held_of( $e_stored['sponsorship'] ) ), titles_of( held_of( $e_snap['sponsorship'] ) ), is_array( rows_of( $e_rows, 'events' ) ) ? array_column( $e_rows['events']['rows'], 1 ) : 'no events table', has( $e_doc, '<td>Building accessible blocks</td>' ), has( $e_editor, '<td>Your first plugin review</td>' ) ),
	array( array( 'Building accessible blocks', 'Your first plugin review' ), array( 'Building accessible blocks', 'Your first plugin review' ), array( 'Building accessible blocks', 'Your first plugin review' ), true, true )
);

$GLOBALS['needles'] = $needles_held;

// The store's clock back to the real one: nothing below plans an event.
WPCPM_Sponsor_Event::for_tests();

echo "\n=== The PDF ===\n";

$GLOBALS['users'] = array(
	7  => new WP_User( 7, 'Manager Example' ),
	8  => new WP_User( 8, 'Second Manager Example' ),
	// The account of a mentor the Mango sponsorship funds (the mentors fixture's user 30), which is
	// not a member of the sponsor; and a student's.
	30 => new WP_User( 30, 'Marta Mentor Example' ),
	40 => new WP_User( 40, 'Mango Member Example' ),
	41 => new WP_User( 41, 'Cirrus Member Example' ),
	50 => new WP_User( 50, 'Account Example' ),
);
$GLOBALS['users'][30]->roles = array( 'wpcpm_mentor' );
$GLOBALS['users'][50]->roles = array( 'wpcpm_student' );
$GLOBALS['umeta'][40] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['umeta'][41] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $B, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']       = 7;
$GLOBALS['journal']   = array();
$GLOBALS['forgotten'] = array();
$GLOBALS['flash']     = array();

/** A minimal PDF every check passes, with anything added before its trailer. */
function pdf_bytes( $extra = '' ) { return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n" . $extra . "trailer<</Root 1 0 R>>\n%%EOF\n"; }

/** A real file in this run's folder, which goes when the run ends. */
function temp_file( $bytes, $extension = 'pdf' ) { $path = wpcpm_test_tempnam( 'wpcpm-sreport-', $extension ); file_put_contents( $path, $bytes ); return $path; }

/** A file in `$_FILES` as a browser posts one. */
function post_file( $path, $name = 'report.pdf', $size = null, $error = UPLOAD_ERR_OK ) {
	$_FILES = array( WPCPM_Sponsor_Report_Files::FIELD_FILE => array( 'name' => $name, 'type' => 'application/pdf', 'tmp_name' => $path, 'error' => $error, 'size' => null === $size ? (int) filesize( $path ) : (int) $size ) );
}

/** A request stood in: the query string, the posted fields, and core's merge of the two. */
function request( array $get, array $post = array() ) { $_GET = $get; $_POST = $post; $_REQUEST = array_merge( $get, $post ); }

/** A form's fields for one report, its nonce minted for that form and that report by whoever is signed in now. */
function form_for( $action, $post_id ) { return array( WPCPM_Sponsor_Report_Files::FIELD_POST => $post_id, '_wpnonce' => wp_create_nonce( $action . '_' . $post_id ) ); }

/** A link's arguments, as a browser sends them once the page's entities are read. */
function query_of( $url ) { parse_str( (string) parse_url( html_entity_decode( (string) $url, ENT_QUOTES ), PHP_URL_QUERY ), $args ); return $args; }

/** Run one of the files class's routes, and say where it ended. */
function ran( $method ) {
	$GLOBALS['sent'] = null;
	try {
		call_user_func( array( 'WPCPM_Sponsor_Report_Files', $method ) );
	} catch ( WPCPM_Test_Left $left ) {
		return $left->getMessage();
	}
	return 'no exit';
}

/** A report's PDF record as stored, or one marked absent, so that a missing record fails a check rather than the run. */
function record_of( $post_id ) {
	$file = get_post_meta( (int) $post_id, WPCPM_Sponsor_Report::META_FILE, true );
	return is_array( $file ) ? $file : array( 'path' => '', 'sha256' => '', 'size' => 0, 'absent' => true );
}

/** The status the last press flashed, or '' for none. */
function flashed_status() { $last = end( $GLOBALS['flash'] ); return is_array( $last ) ? $last[1] : ''; }

// Where the two forms land: the report's editor, the Sponsors screen's Reports tab with the report
// named, or that tab itself when there is no report to go back to.
$reports_tab = 'redirect:https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=reports';
$editor      = static function ( $id ) use ( $reports_tab ) { return $reports_tab . '&wpcpm_sreport=' . (int) $id; };

$good       = temp_file( pdf_bytes() );
$good_bytes = (string) file_get_contents( $good );
$audit_mark = count( $GLOBALS['audit'] );

// The capability comes first: an account without it meets wp_die() on both forms, and no nonce
// is read on the way.
$GLOBALS['uid']         = 40;
$GLOBALS['nonce_asked'] = array();
request( array(), form_for( $F::ACTION_UPLOAD, $a25 ) );
post_file( $good );

ck( 'an account without the program\'s capability is refused on both forms before a nonce is read',
	array( ran( 'handle_upload' ), ran( 'handle_remove' ), $GLOBALS['nonce_asked'] ),
	array( 'die:403:You do not have permission to manage the program.', 'die:403:You do not have permission to manage the program.', array() )
);

$GLOBALS['uid'] = 7;
request( array(), array( $F::FIELD_POST => $a25, '_wpnonce' => wp_create_nonce( $F::ACTION_UPLOAD . '_' . $a26 ) ) );
post_file( $good );

ck( 'a form\'s nonce is keyed to its report: one minted for another report is refused, and the one asked for is this report\'s',
	array( ran( 'handle_upload' ), end( $GLOBALS['nonce_asked'] ) ),
	array( 'die:403:The link you followed has expired.', 'wpcpm_sreport_upload_' . $a25 )
);

$plain_id = plain_post()->ID;
request( array(), form_for( $F::ACTION_UPLOAD, $plain_id ) );
post_file( $good );

ck( 'a post that is not a report has no PDF to take: back to the Sponsors screen, saying so', array( ran( 'handle_upload' ), end( $GLOBALS['flash'] ) ), array( $reports_tab, array( 'sponsors_admin', 'sreport-pdf-gone' ) ) );

// A report is marked final, printed, and then its PDF attached: the members' download is the final
// report's, so the PDF moves on a final report too, and only its narratives and its figures wait
// for a reopen. Each move is a row on the trail. Attached, replaced and removed, in that order, so
// the final report ends as it began, with no PDF, which the checks further down read.
$GLOBALS['journal'] = array();
$final_mark         = count( $GLOBALS['audit'] );
$final_copy         = temp_file( pdf_bytes( "% the final copy, printed and saved\n" ) );
request( array(), form_for( $F::ACTION_UPLOAD, $a26 ) );
post_file( $good, 'Mango final.pdf' );
$final_attached = array( ran( 'handle_upload' ), flashed_status(), $GLOBALS['journal'], record_of( $a26 )['sha256'], $R::state( get_post( $a26 ) ) );
$final_first    = record_of( $a26 );
post_file( $final_copy, 'Mango final v2.pdf' );
$final_replaced = array( ran( 'handle_upload' ), flashed_status(), $GLOBALS['forgotten'], record_of( $a26 )['sha256'] );
$final_second   = record_of( $a26 );
request( array(), form_for( $F::ACTION_REMOVE, $a26 ) );
$final_removed = array( ran( 'handle_remove' ), flashed_status(), $GLOBALS['forgotten'], get_post_meta( $a26, $R::META_FILE, true ), get_post_meta( $a26, $R::META_FILE_NAME, true ), $R::state( get_post( $a26 ) ) );
$final_rows    = array_map( static function ( $row ) { return array( $row['kind'], $row['subject'], $row['ground'], $row['message'] ); }, array_slice( $GLOBALS['audit'], $final_mark ) );

ck( 'a final report takes a PDF: a report is marked final, printed and then attached, and the members\' download is the final report\'s',
	$final_attached,
	array( $editor( $a26 ), 'sreport-pdf-attached', array( 'store' ), hash( 'sha256', $good_bytes ), 'final' )
);
ck( 'has it replaced, the file it replaced forgotten', $final_replaced, array( $editor( $a26 ), 'sreport-pdf-replaced', array( $final_first['path'] ), hash( 'sha256', (string) file_get_contents( $final_copy ) ) ) );
ck( 'and removed, both keys gone and the report still final: only its narratives and its figures wait for a reopen',
	$final_removed,
	array( $editor( $a26 ), 'sreport-pdf-removed', array( $final_first['path'], $final_second['path'] ), '', '', 'final' )
);
ck( 'each move a row on the sponsor\'s trail, on the manager\'s ground',
	$final_rows,
	array(
		array( 'sponsor_report_pdf', (string) $a26, 'manager', 'A PDF was attached to the sponsor report for the term ending September 30, 2026.' ),
		array( 'sponsor_report_pdf', (string) $a26, 'manager', 'The PDF of the sponsor report for the term ending September 30, 2026 was replaced, and the file it replaced was deleted.' ),
		array( 'sponsor_report_pdf', (string) $a26, 'manager', 'The PDF of the sponsor report for the term ending September 30, 2026 was removed and deleted.' ),
	)
);

$GLOBALS['journal']   = array();
$GLOBALS['forgotten'] = array();
$audit_mark           = count( $GLOBALS['audit'] );

$back     = $editor( $a25 );
$refusals = array();
request( array(), form_for( $F::ACTION_UPLOAD, $a25 ) );

post_file( $good, 'report.pdf', 11 * MB_IN_BYTES );
$refusals['larger than the setting'] = array( ran( 'handle_upload' ), flashed_status() );
post_file( '', 'report.pdf', 0, UPLOAD_ERR_NO_FILE );
$refusals['no file at all'] = array( ran( 'handle_upload' ), flashed_status() );
post_file( temp_file( 'Just some words, and not a report.' ) );
$refusals['text named .pdf'] = array( ran( 'handle_upload' ), flashed_status() );
post_file( $good, 'report.exe' );
$refusals['a PDF named .exe'] = array( ran( 'handle_upload' ), flashed_status() );
post_file( temp_file( pdf_bytes( "2 0 obj<</Encrypt 3 0 R>>endobj\n" ) ) );
$refusals['password protected'] = array( ran( 'handle_upload' ), flashed_status() );
post_file( temp_file( pdf_bytes( "2 0 obj<</S/Launch/F(calc.exe)>>endobj\n" ) ) );
$refusals['asks to run a program'] = array( ran( 'handle_upload' ), flashed_status() );
post_file( $good, 'report.pdf', 100, UPLOAD_ERR_INI_SIZE );
$refusals['over the server\'s own limit'] = array( ran( 'handle_upload' ), flashed_status() );
post_file( $good, 'report.pdf', 100, UPLOAD_ERR_FORM_SIZE );
$refusals['over the form\'s limit'] = array( ran( 'handle_upload' ), flashed_status() );
// A megabyte for this one: a file whose own report of its size is small, and whose bytes on disk
// are not. The size on disk decides, since that is what would be read into memory.
$GLOBALS['settings']['agreement_max_mb'] = 1;
post_file( temp_file( pdf_bytes( str_repeat( "% a line of padding, to take the file past a megabyte\n", 20000 ) ) ), 'report.pdf', 100 );
$refusals['small by its own report, larger on disk'] = array( ran( 'handle_upload' ), flashed_status() );
unset( $GLOBALS['settings']['agreement_max_mb'] );
// finfo calls this a PDF, and so does its name: only its first bytes, a line break ahead of the
// magic, say it is not one.
post_file( temp_file( "\n" . pdf_bytes() ) );
$refusals['a PDF to finfo and by its name, not by its first bytes'] = array( ran( 'handle_upload' ), flashed_status() );
// On a host with no finfo, the name map answers from the name alone, and the bytes are the one
// test left.
$GLOBALS['name_map_only'] = true;
post_file( temp_file( 'Just some words, and not a report.' ) );
$refusals['text named .pdf, on a host with no finfo'] = array( ran( 'handle_upload' ), flashed_status() );
unset( $GLOBALS['name_map_only'] );
// A path the request never received, named as a posted file's would be: under the CLI, PHP's list
// of uploads is stood in for by whether the path can be read.
post_file( wpcpm_test_temp_dir() . 'never-uploaded.pdf', 'report.pdf', 100 );
$refusals['a path this request did not receive'] = array( ran( 'handle_upload' ), flashed_status() );

ck( 'the agreement\'s checks, each refusal naming its reason on the report\'s editor: a file too big by its own report, by the server\'s limit, the form\'s or its bytes on disk; none at all, or a path the request did not receive; one that is not a PDF by its bytes or by its name, on a host with finfo and on one without; one no reader could open; one that would run a program',
	$refusals,
	array(
		'larger than the setting'                                => array( $back, 'sreport-pdf-too-big' ),
		'no file at all'                                         => array( $back, 'sreport-pdf-no-file' ),
		'text named .pdf'                                        => array( $back, 'sreport-pdf-not-pdf' ),
		'a PDF named .exe'                                       => array( $back, 'sreport-pdf-not-pdf' ),
		'password protected'                                     => array( $back, 'sreport-pdf-encrypted' ),
		'asks to run a program'                                  => array( $back, 'sreport-pdf-launch' ),
		'over the server\'s own limit'                           => array( $back, 'sreport-pdf-too-big' ),
		'over the form\'s limit'                                 => array( $back, 'sreport-pdf-too-big' ),
		'small by its own report, larger on disk'                => array( $back, 'sreport-pdf-too-big' ),
		'a PDF to finfo and by its name, not by its first bytes' => array( $back, 'sreport-pdf-not-pdf' ),
		'text named .pdf, on a host with no finfo'               => array( $back, 'sreport-pdf-not-pdf' ),
		'a path this request did not receive'                    => array( $back, 'sreport-pdf-no-file' ),
	)
);

$files_class_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php' );
$upload_src      = method_body( $files_class_src, 'handle_upload' );
$before_store    = static function ( $needle ) use ( $upload_src ) {
	$at    = strpos( $upload_src, $needle );
	$store = strpos( $upload_src, 'WPCPM_Private_Files::store' );
	return false !== $at && false !== $store && $at < $store;
};

ck( 'and the agreement\'s order holds, read off the source: every test of the file before anything is stored, and the file one PHP received on this request',
	array( $before_store( 'self::arrived_by_post( $file[\'tmp_name\'] )' ), $before_store( 'WPCPM_Pdf_Check::named_pdf(' ), $before_store( 'WPCPM_Pdf_Check::has_magic(' ), $before_store( 'WPCPM_Pdf_Check::mime_agrees( WPCPM_Pdf_Check::mime_of(' ), $before_store( 'WPCPM_Pdf_Check::inspect(' ), false !== strpos( method_body( $files_class_src, 'arrived_by_post' ), 'is_uploaded_file( $path )' ) ),
	array( true, true, true, true, true, true )
);
ck( 'and none of them reached the store, wrote to the report, or left a row on the trail', array( $GLOBALS['journal'], get_post_meta( $a25, $R::META_FILE, true ), get_post_meta( $a25, $R::META_FILE_NAME, true ), count( $GLOBALS['audit'] ) - $audit_mark ), array( array(), '', '', 0 ) );

$GLOBALS['store_fails'] = true;
post_file( $good, 'Mango report 2026.pdf' );
$failed = array( ran( 'handle_upload' ), flashed_status(), $GLOBALS['journal'], get_post_meta( $a25, $R::META_FILE, true ), count( $GLOBALS['audit'] ) - $audit_mark );
$GLOBALS['store_fails'] = false;

ck( 'a PDF that passed every check and could not be stored is the site\'s failure, and says so, with nothing recorded', $failed, array( $back, 'sreport-pdf-file', array( 'store' ), '', 0 ) );

$GLOBALS['journal'] = array();
post_file( $good, 'Mango report 2026.pdf' );
$attached = ran( 'handle_upload' );
$first    = record_of( $a25 );

ck( 'a PDF that passes is stored, and the report records what the store answered: the path, the hash of the bytes, their size',
	array( $attached, flashed_status(), $GLOBALS['journal'], $first, isset( $GLOBALS['files'][ $first['path'] ] ) ? $GLOBALS['files'][ $first['path'] ] === $good_bytes : false ),
	array( $back, 'sreport-pdf-attached', array( 'store' ), array( 'path' => $first['path'], 'sha256' => hash( 'sha256', $good_bytes ), 'size' => strlen( $good_bytes ) ), true )
);
ck( 'the name it came with is kept, cleaned, for a manager\'s eye, and file_of() reads the record back', array( get_post_meta( $a25, $R::META_FILE_NAME, true ), $F::file_of( get_post( $a25 ) ) ), array( 'Mango-report-2026.pdf', $first ) );
ck( 'one row on the sponsor\'s trail: the PDF attached, by the manager, against the index, with the size and the hash and never the name',
	array_slice( $GLOBALS['audit'], $audit_mark ),
	array( array( 'kind' => 'sponsor_report_pdf', 'sponsor' => $A, 'subject' => (string) $a25, 'actor' => 7, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'A PDF was attached to the sponsor report for the term ending September 30, 2025.', 'data' => array( 'size' => strlen( $good_bytes ), 'sha256' => hash( 'sha256', $good_bytes ) ) ) )
);

$second       = temp_file( pdf_bytes( "% the corrected copy\n" ) );
$second_bytes = (string) file_get_contents( $second );
$audit_mark   = count( $GLOBALS['audit'] );
post_file( $second, 'Mango report 2026 v2.pdf' );
$replaced = ran( 'handle_upload' );
$now      = record_of( $a25 );

ck( 'a replacement records the new file and forgets the one it replaced, which leaves the store',
	array( $replaced, flashed_status(), $now['sha256'], $GLOBALS['forgotten'], isset( $GLOBALS['files'][ $first['path'] ] ), isset( $GLOBALS['files'][ $now['path'] ] ), get_post_meta( $a25, $R::META_FILE_NAME, true ) ),
	array( $back, 'sreport-pdf-replaced', hash( 'sha256', $second_bytes ), array( $first['path'] ), false, true, 'Mango-report-2026-v2.pdf' )
);
ck( 'and the trail says so, with the new file\'s size and hash',
	array_slice( $GLOBALS['audit'], $audit_mark ),
	array( array( 'kind' => 'sponsor_report_pdf', 'sponsor' => $A, 'subject' => (string) $a25, 'actor' => 7, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'The PDF of the sponsor report for the term ending September 30, 2025 was replaced, and the file it replaced was deleted.', 'data' => array( 'size' => strlen( $second_bytes ), 'sha256' => hash( 'sha256', $second_bytes ) ) ) )
);

$GLOBALS['forgotten']   = array();
$GLOBALS['store_fails'] = true;
post_file( $good, 'Another copy.pdf' );
$kept_pdf = array( ran( 'handle_upload' ), flashed_status(), get_post_meta( $a25, $R::META_FILE, true ) === $now, get_post_meta( $a25, $R::META_FILE_NAME, true ), $GLOBALS['forgotten'], isset( $GLOBALS['files'][ $now['path'] ] ) );
$GLOBALS['store_fails'] = false;

ck( 'a replacement that could not be stored leaves the report the PDF it had: its file, its record and its name', $kept_pdf, array( $back, 'sreport-pdf-file', true, 'Mango-report-2026-v2.pdf', array(), true ) );

// The record is written before anything is forgotten: a write the database refuses leaves the
// report the PDF it had, and the new file goes rather than staying in the store with nothing
// pointing at it.
$GLOBALS['forgotten'] = array();
$GLOBALS['meta_fail'] = $R::META_FILE;
$audit_mark           = count( $GLOBALS['audit'] );
post_file( $good, 'A copy the record refused.pdf' );
$refused_write        = array(
	ran( 'handle_upload' ),
	flashed_status(),
	record_of( $a25 ) === $now,
	get_post_meta( $a25, $R::META_FILE_NAME, true ),
	isset( $GLOBALS['files'][ $now['path'] ] ),
	count( $GLOBALS['forgotten'] ),
	in_array( $now['path'], $GLOBALS['forgotten'], true ),
	isset( $GLOBALS['forgotten'][0] ) && isset( $GLOBALS['files'][ $GLOBALS['forgotten'][0] ] ),
	count( $GLOBALS['audit'] ) - $audit_mark,
);
$GLOBALS['meta_fail'] = '';
$GLOBALS['forgotten'] = array();

ck( 'a replacement whose record the database refuses keeps the PDF the report had, its file, its record and its name, and forgets the new file, with nothing on the trail',
	$refused_write,
	array( $back, 'sreport-pdf-file', true, 'Mango-report-2026-v2.pdf', true, 1, false, false, 0 )
);

$audit_mark = count( $GLOBALS['audit'] );
request( array(), form_for( $F::ACTION_REMOVE, $a25 ) );
$removed = ran( 'handle_remove' );

ck( 'a removal forgets the file and deletes both keys, under the nonce minted for that report',
	array( $removed, flashed_status(), end( $GLOBALS['nonce_asked'] ), $GLOBALS['forgotten'], isset( $GLOBALS['files'][ $now['path'] ] ), get_post_meta( $a25, $R::META_FILE, true ), get_post_meta( $a25, $R::META_FILE_NAME, true ), $F::file_of( get_post( $a25 ) ) ),
	array( $back, 'sreport-pdf-removed', 'wpcpm_sreport_remove_' . $a25, array( $now['path'] ), false, '', '', null )
);
ck( 'and the trail says so, with the size and the hash of the file removed',
	array_slice( $GLOBALS['audit'], $audit_mark ),
	array( array( 'kind' => 'sponsor_report_pdf', 'sponsor' => $A, 'subject' => (string) $a25, 'actor' => 7, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'The PDF of the sponsor report for the term ending September 30, 2025 was removed and deleted.', 'data' => array( 'size' => strlen( $second_bytes ), 'sha256' => hash( 'sha256', $second_bytes ) ) ) )
);

ck( 'a second removal finds nothing to remove', array( ran( 'handle_remove' ), flashed_status() ), array( $back, 'sreport-pdf-none' ) );

$files_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php' );
$words_at  = strpos( $files_src, 'function manager_messages(' );
$words_end = false === $words_at ? false : strpos( $files_src, "\n\t}\n", $words_at );
preg_match_all( "/'(sreport-pdf-[a-z-]+)'/", false === $words_end ? $files_src : substr( $files_src, 0, $words_at ) . substr( $files_src, $words_end ), $said );
$flashes = array_values( array_unique( $said[1] ) );
$worded  = array_keys( $F::manager_messages() );
sort( $flashes );
sort( $worded );

ck( 'every outcome the two forms flash has words on the Sponsors screen, and there are none for an outcome nothing flashes', array( count( $flashes ) > 10, $flashes ), array( true, $worded ) );
ck( 'each in a notice the screen knows how to draw', array_values( array_unique( array_map( static function ( $said ) { return $said[0]; }, $F::manager_messages() ) ) ), array( 'success', 'error', 'info' ) );

// The PDF the rest of the section downloads: attached again, then the name it goes out under.
request( array(), form_for( $F::ACTION_UPLOAD, $a25 ) );
post_file( $second, 'Mango report 2026.pdf' );
ran( 'handle_upload' );

$z_post = WPCPM_Sponsors_Index::post_id_of( $Z );
$z_slug = $GLOBALS['posts'][ $z_post ]->post_name;
$named  = wp_insert_post( array( 'post_type' => $R::POST_TYPE, 'post_status' => 'private', 'post_title' => 'A report to name' ) );

// A company whose name has no Latin letters has a slug core stored percent-encoded.
$GLOBALS['posts'][ $z_post ]->post_name = '%e3%81%82%e3%81%84';
WPCPM_Sponsors_Index::patch( $Z, array( 'notes' => 'A slug core stored encoded.' ) );
update_post_meta( $named, $R::META_SPONSOR, $Z );
update_post_meta( $named, $R::META_UNTIL, '2026-09-30' );
$encoded = $F::download_name( get_post( $named ) );
update_post_meta( $named, $R::META_SPONSOR, 'spoZZZZ0000000099' );
$orphan = $F::download_name( get_post( $named ) );
wp_delete_post( $named, true );
$GLOBALS['posts'][ $z_post ]->post_name = $z_slug;
WPCPM_Sponsors_Index::patch( $Z, array( 'notes' => '' ) );

ck( 'download_name(): the sponsor\'s slug and the period\'s last day, built by the site, never the name the file came with; a slug core stored encoded loses its percent signs, and a sponsor the store no longer holds leaves the day',
	array( $F::download_name( get_post( $a25 ) ), $encoded, $orphan ),
	array( 'wordpress-credits-report-mango-example-2025-09-30.pdf', 'wordpress-credits-report-e38182e38184-2026-09-30.pdf', 'wordpress-credits-report-2026-09-30.pdf' )
);

echo "\n=== Who reads the PDF and the printed document ===\n";

$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$GLOBALS['uid']                            = 40;

ck( 'download_url() and print_url() are admin-post links naming the report, each nonced for its route, its report and the account it was drawn for',
	array( query_of( $F::download_url( get_post( $a25 ) ) ), query_of( $F::print_url( get_post( $a25 ) ) ), 0 === strpos( $F::download_url( get_post( $a25 ) ), 'https://site.example/wp-admin/admin-post.php?action=wpcpm_sreport_pdf&amp;report=' . $a25 . '&amp;_wpnonce=' ) ),
	array(
		array( 'action' => 'wpcpm_sreport_pdf', 'report' => (string) $a25, '_wpnonce' => wp_create_nonce( 'wpcpm_sreport_pdf_' . $a25 ) ),
		array( 'action' => 'wpcpm_sreport_print', 'report' => (string) $a25, '_wpnonce' => wp_create_nonce( 'wpcpm_sreport_print_' . $a25 ) ),
		true,
	)
);

$pdf_headers = array(
	'Content-Type: application/pdf',
	'Content-Disposition: attachment; filename="wordpress-credits-report-mango-example-2025-09-30.pdf"',
	'X-Content-Type-Options: nosniff',
	"Content-Security-Policy: default-src 'none'; sandbox",
	'Cache-Control: private, no-store',
	'Content-Length: ' . strlen( $second_bytes ),
);
$routes = array(
	'handle_download' => array( 'download_url', array( 'by' => 'WPCPM_Sponsor_Report_Files::send', 'headers' => $pdf_headers, 'body' => $second_bytes ) ),
	'handle_print'    => array( 'print_url', array( 'by' => 'WPCPM_Sponsor_Report_Files::send', 'headers' => array( 'Content-Type: text/html; charset=utf-8', 'X-Content-Type-Options: nosniff' ), 'body' => $F::document( get_post( $a25 ) ) ) ),
);
$refusal = 'die:403:That is not something your account can do here.';

foreach ( $routes as $route => $expected ) {
	list( $link_of, $wanted_send ) = $expected;

	$outcomes = array();
	$bodies   = array();

	// A link drawn for the member, followed once the session has ended.
	$GLOBALS['uid'] = 40;
	$link           = call_user_func( array( $F, $link_of ), get_post( $a25 ) );
	$GLOBALS['uid'] = 0;
	request( query_of( $link ) );
	$outcomes['signed out'] = ran( $route );

	$GLOBALS['uid'] = 40;
	request( query_of( call_user_func( array( $F, $link_of ), get_post( $a25 ) ) ) );
	$outcomes['a member of the sponsor, the report a draft'] = ran( $route );

	$GLOBALS['uid'] = 7;
	unset( $GLOBALS['settings']['sponsor_tiers_live'] );
	request( query_of( call_user_func( array( $F, $link_of ), get_post( $a25 ) ) ) );
	$outcomes['a manager, the report a draft and the switch off'] = ran( $route );
	$bodies['a manager, the report a draft and the switch off']   = $GLOBALS['sent'];

	$R::finalize( get_post( $a25 ), 7 );

	$GLOBALS['uid'] = 40;
	request( query_of( call_user_func( array( $F, $link_of ), get_post( $a25 ) ) ) );
	$outcomes['a member of the sponsor, the report final and the switch off'] = ran( $route );

	$GLOBALS['settings']['sponsor_tiers_live'] = 1;
	$GLOBALS['uid']                            = 41;
	request( query_of( call_user_func( array( $F, $link_of ), get_post( $a25 ) ) ) );
	$outcomes['a member of another sponsor, the report final'] = ran( $route );

	$GLOBALS['uid'] = 40;
	request( query_of( call_user_func( array( $F, $link_of ), get_post( $a25 ) ) ) );
	$outcomes['a member of the sponsor, the report final and the switch on'] = ran( $route );
	$bodies['a member of the sponsor, the report final and the switch on']   = $GLOBALS['sent'];

	$minted_for_other      = query_of( call_user_func( array( $F, $link_of ), $a26_post ) );
	$minted_for_other['report'] = (string) $a25;
	request( $minted_for_other );
	$outcomes['a link minted for another report'] = ran( $route );

	// Signed in, the report final and the switch on, and neither a manager nor a member.
	foreach ( array( 30 => 'a mentor the sponsor funds, the report final', 50 => 'a student, the report final' ) as $account => $who ) {
		$GLOBALS['uid'] = $account;
		request( query_of( call_user_func( array( $F, $link_of ), get_post( $a25 ) ) ) );
		$outcomes[ $who ] = ran( $route );
	}

	$GLOBALS['uid'] = 7;
	request( query_of( call_user_func( array( $F, $link_of ), get_post( $plain_id ) ) ) );
	$outcomes['a post that is not a report'] = ran( $route );

	$R::reopen( get_post( $a25 ), 7 );

	ck( $route . ': a manager always, a member of the report\'s sponsor once it is final and while the tiers are live, a signed-out visitor to the login form, anyone else the one refusal: another sponsor\'s member, a mentor the sponsor funds, a student',
		$outcomes,
		array(
			'signed out'                                                   => 'redirect:https://site.example/wp-login.php?redirect_to=' . rawurlencode( html_entity_decode( $link, ENT_QUOTES ) ),
			'a member of the sponsor, the report a draft'                  => $refusal,
			'a manager, the report a draft and the switch off'             => 'sent',
			'a member of the sponsor, the report final and the switch off' => $refusal,
			'a member of another sponsor, the report final'                => $refusal,
			'a member of the sponsor, the report final and the switch on'  => 'sent',
			'a link minted for another report'                             => 'die:403:The link you followed has expired.',
			'a mentor the sponsor funds, the report final'                 => $refusal,
			'a student, the report final'                                  => $refusal,
			'a post that is not a report'                                  => 'die:404:That report is not one this site holds.',
		)
	);
	ck( $route . ': what it sends, through the one sender, to each reader it lets in', $bodies, array( 'a manager, the report a draft and the switch off' => $wanted_send, 'a member of the sponsor, the report final and the switch on' => $wanted_send ) );
}

// The nonce is asked for before anything is looked up, so no answer tells one id from another. A
// signed-in account without the link's nonce meets core's one expired-link refusal whether the id
// names a post that is not a report or somebody else's report; with the nonce the two answer apart,
// a 404 and the policy's refusal, which is exactly what a probe without it must never see.
$GLOBALS['uid'] = 41;
$probe          = array();
foreach ( array( 'handle_download' => 'download_url', 'handle_print' => 'print_url' ) as $route => $link_of ) {
	foreach ( array( 'a post that is not a report' => $plain_id, 'another sponsor\'s report' => $a25 ) as $what => $id ) {
		request( array( 'report' => (string) $id, '_wpnonce' => 'not-the-nonce' ) );
		$probe[ $route . ': ' . $what . ', a wrong nonce' ] = ran( $route );
		request( query_of( call_user_func( array( $F, $link_of ), get_post( $id ) ) ) );
		$probe[ $route . ': ' . $what . ', its own nonce' ] = ran( $route );
	}
}
$expired = 'die:403:The link you followed has expired.';

ck( 'the nonce is read before anything is looked up: without it a signed-in account meets the one expired-link refusal whatever the id names, so the routes cannot be walked for the ids that are reports',
	$probe,
	array(
		'handle_download: a post that is not a report, a wrong nonce' => $expired,
		'handle_download: a post that is not a report, its own nonce' => 'die:404:That report is not one this site holds.',
		'handle_download: another sponsor\'s report, a wrong nonce'   => $expired,
		'handle_download: another sponsor\'s report, its own nonce'   => $refusal,
		'handle_print: a post that is not a report, a wrong nonce'    => $expired,
		'handle_print: a post that is not a report, its own nonce'    => 'die:404:That report is not one this site holds.',
		'handle_print: another sponsor\'s report, a wrong nonce'      => $expired,
		'handle_print: another sponsor\'s report, its own nonce'      => $refusal,
	)
);

// Bytes a theme or a plugin left in an open buffer are not the report's: sent along they would go
// out ahead of it, past a PDF's own length or ahead of the document's doctype. A buffer holding
// some is emptied and closed before the headers; the first empty one stops that, and it and every
// buffer outside it stay open.
$GLOBALS['uid'] = 7;
request( query_of( $F::download_url( get_post( $a25 ) ) ) );
$base_level = ob_get_level();
ob_start();
ob_start();
echo 'stray bytes';
$buffered = ran( 'handle_download' );
$buffers  = array( ob_get_level() - $base_level, ob_get_length() );
while ( ob_get_level() > $base_level ) {
	ob_end_clean();
}

ck( 'a buffer holding stray bytes is emptied and closed before the headers, and the empty one outside it stays open',
	array( $buffered, $buffers, is_array( $GLOBALS['sent'] ) && $GLOBALS['sent']['body'] === $second_bytes ),
	array( 'sent', array( 1, 0 ), true )
);

// A report somebody put in the trash outside the plugin is no report the store lists, and no
// route serves or changes it, whatever it still holds.
$GLOBALS['uid'] = 7;
$GLOBALS['posts'][ $a25 ]->post_status = 'trash';
$in_trash = array();
foreach ( array( 'handle_download' => 'download_url', 'handle_print' => 'print_url' ) as $route => $link_of ) {
	request( query_of( call_user_func( array( $F, $link_of ), get_post( $a25 ) ) ) );
	$in_trash[ $route ] = ran( $route );
}
request( array(), form_for( $F::ACTION_REMOVE, $a25 ) );
$in_trash['handle_remove'] = array( ran( 'handle_remove' ), flashed_status(), is_array( get_post_meta( $a25, $R::META_FILE, true ) ) );
$GLOBALS['posts'][ $a25 ]->post_status = 'private';

ck( 'a report in the trash is none: neither link serves it, and its PDF is not removed',
	$in_trash,
	array(
		'handle_download' => 'die:404:That report is not one this site holds.',
		'handle_print'    => 'die:404:That report is not one this site holds.',
		'handle_remove'   => array( $reports_tab, 'sreport-pdf-gone', true ),
	)
);

request( query_of( $F::download_url( $a26_post ) ) );
$no_pdf = ran( 'handle_download' );
request( query_of( $F::print_url( $a26_post ) ) );
$printed_without = array( ran( 'handle_print' ), is_array( $GLOBALS['sent'] ) && $GLOBALS['sent']['body'] === $F::document( $a26_post ) );

$files_held       = $GLOBALS['files'];
$GLOBALS['files'] = array();
request( query_of( $F::download_url( get_post( $a25 ) ) ) );
$unreadable_pdf   = ran( 'handle_download' );
$GLOBALS['files'] = $files_held;

ck( 'a report with no PDF has none to download, though it prints; and a PDF the private store cannot give back is the site\'s failure, not a file',
	array( $no_pdf, $printed_without, $unreadable_pdf ),
	array( 'die:404:That report has no PDF on this site.', array( 'sent', true ), 'die:500:That PDF could not be read. An Administrator can attach it again.' )
);

unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['uid']       = 7;
$GLOBALS['journal']   = array();
$GLOBALS['forgotten'] = array();
$_GET                 = array();
$_POST                = array();
$_REQUEST             = array();
$_FILES               = array();

echo "\n=== The privacy walk, over the whole audit trail ===\n";

// The walk above read the trail while only drafts were on it. By now every move a report makes has
// written its row: drafted and drafted again, final, reopened, its PDF attached, replaced and
// removed. So the trail is walked once more, before the daily draft starts one of its own.
$trail_kinds = array_values( array_unique( array_column( $GLOBALS['audit'], 'kind' ) ) );
sort( $trail_kinds );

ck( 'no row a report wrote on the audit trail, of any kind, names a seeded student, a school or an address, in its message or its facts', leaks( $GLOBALS['audit'] ), array() );
ck( 'and the trail walked holds every kind the store and the PDF write, a regeneration among the drafts',
	array( $trail_kinds, in_array( 1, array_column( array_column( $GLOBALS['audit'], 'data' ), 'regenerated' ), true ) ),
	array( array( 'sponsor_report_drafted', 'sponsor_report_final', 'sponsor_report_pdf', 'sponsor_report_reopened' ), true )
);

echo "\n=== The daily draft ===\n";

clear_reports();
$GLOBALS['audit'] = array();
$GLOBALS['uid']   = 0;

ck( 'due() on the terms\' last day: only the term that ended in August',
	$R::due( '2026-09-30' ),
	array( array( 'sponsor' => $B, 'name' => 'Cirrus Example', 'until' => '2026-08-31', 'tier' => 'supporter' ) )
);
ck( 'the day after: the four terms that have ended, oldest first and by name on one day, and never a Friend, a paused sponsor or a term still running',
	$R::due( '2026-10-01' ),
	array(
		array( 'sponsor' => $B, 'name' => 'Cirrus Example', 'until' => '2026-08-31', 'tier' => 'supporter' ),
		array( 'sponsor' => $Z, 'name' => 'Aardvark Example', 'until' => '2026-09-30', 'tier' => 'supporter' ),
		array( 'sponsor' => $A, 'name' => 'Mango Example', 'until' => '2026-09-30', 'tier' => 'backer' ),
		array( 'sponsor' => $P2, 'name' => 'Partner Example', 'until' => '2026-09-30', 'tier' => 'partner' ),
	)
);

// The sponsors store holds its rows for the request, so what is counted here is due()'s own reading.
WPCPM_Sponsors_Index::rows();
$GLOBALS['queries'] = 0;
$R::due( '2026-10-01' );

ck( 'due() reads the reports once, however many terms have ended', $GLOBALS['queries'], 1 );

$row_a = WPCPM_Sponsors_Index::row( $A );

ck( 'maybe_draft() on the term\'s last day drafts nothing', array( $R::maybe_draft( $A, $row_a, '2026-09-30' ), report_posts(), WPCPM_Sponsors_Index::row( $A )['report_drafted_for'] ), array( false, array(), '' ) );
ck( 'the day after, it drafts that term\'s report and stamps the row', array( $R::maybe_draft( $A, $row_a, '2026-10-01' ), count( $R::reports_of( $A ) ), WPCPM_Sponsors_Index::row( $A )['report_drafted_for'] ), array( true, 1, '2026-09-30' ) );

$drafted = $R::find( $A, '2026-09-30' );

ck( 'drafted by the run: no author, the system on the audit trail, the run\'s day on the figures',
	array( $drafted instanceof WP_Post ? (int) $drafted->post_author : 'none', end( $GLOBALS['audit'] )['actor'], end( $GLOBALS['audit'] )['ground'], end( $GLOBALS['audit'] )['message'], $R::snapshot( $drafted )['as_of'] ),
	array( 0, 0, 'system', 'The daily run drafted a sponsor report for the term ending September 30, 2026.', '2026-10-01' )
);
ck( 'not twice: not with the stamp the draft wrote, and not with the row the run read before it',
	array( $R::maybe_draft( $A, WPCPM_Sponsors_Index::row( $A ), '2026-10-02' ), $R::maybe_draft( $A, $row_a, '2026-10-02' ), count( $R::reports_of( $A ) ), count( $GLOBALS['audit'] ) ),
	array( false, false, 1, 1 )
);
// The stamp is what keeps a term the run drafted from being drafted again once the draft is gone:
// a manager who threw the run's draft away has decided, and the run does not put it back.
$GLOBALS['posts'][ $drafted->ID ]->post_status = 'trash';
ck( 'a draft the run stored and a manager threw away is not drafted again: the stamp holds, though no report is there to refuse it',
	array( $R::find( $A, '2026-09-30' ), $R::maybe_draft( $A, WPCPM_Sponsors_Index::row( $A ), '2026-10-03' ), report_posts() ),
	array( null, false, array() )
);
$GLOBALS['posts'][ $drafted->ID ]->post_status = 'private';

ck( 'never for a Friend or a paused one, and nothing is stamped',
	array( $R::maybe_draft( $M, WPCPM_Sponsors_Index::row( $M ), '2026-10-01' ), $R::maybe_draft( $P, WPCPM_Sponsors_Index::row( $P ), '2026-10-01' ), WPCPM_Sponsors_Index::row( $M )['report_drafted_for'], WPCPM_Sponsors_Index::row( $P )['report_drafted_for'], $R::reports_of( $M ), $R::reports_of( $P ) ),
	array( false, false, '', '', array(), array() )
);

$GLOBALS['uid'] = 7;
$R::generate( $B, '2026-08-31', 'manager', '2026-09-10' );
$GLOBALS['uid'] = 0;

ck( 'a term a manager drafted first is not drafted again, and its row is stamped as the run\'s own draft would be, so the run stops asking',
	array( $R::maybe_draft( $B, WPCPM_Sponsors_Index::row( $B ), '2026-10-01' ), WPCPM_Sponsors_Index::row( $B )['report_drafted_for'], count( $R::reports_of( $B ) ) ),
	array( false, '2026-08-31', 1 )
);

// And so a draft thrown away is treated alike whoever made it: the manager's draft of that term
// gone, the run does not make one.
$b_report = $R::find( $B, '2026-08-31' );
$GLOBALS['posts'][ $b_report->ID ]->post_status = 'trash';
ck( 'a manager\'s draft thrown away is not drafted again by the run either', array( $R::maybe_draft( $B, WPCPM_Sponsors_Index::row( $B ), '2026-10-02' ), $R::reports_of( $B ) ), array( false, array() ) );
$GLOBALS['posts'][ $b_report->ID ]->post_status = 'private';

$GLOBALS['insert_fail'] = $R::POST_TYPE;
$failed                 = $R::maybe_draft( $P2, WPCPM_Sponsors_Index::row( $P2 ), '2026-10-01' );
$GLOBALS['insert_fail'] = '';

ck( 'a draft that could not be stored stamps nothing, so the next run tries again, and that run drafts it',
	array( $failed, WPCPM_Sponsors_Index::row( $P2 )['report_drafted_for'], $R::maybe_draft( $P2, WPCPM_Sponsors_Index::row( $P2 ), '2026-10-02' ), WPCPM_Sponsors_Index::row( $P2 )['report_drafted_for'] ),
	array( false, '', true, '2026-09-30' )
);
ck( 'with every ended term reported on, nothing is due', array( $R::maybe_draft( $Z, WPCPM_Sponsors_Index::row( $Z ), '2026-10-02' ), $R::due( '2026-10-02' ) ), array( true, array() ) );

WPCPM_Sponsors_Index::patch( $A, array( 'tier_until' => '2027-09-30' ) );

ck( 'a renewed term is a term still running: nothing is drafted and the stamp stays the old term\'s', array( $R::maybe_draft( $A, WPCPM_Sponsors_Index::row( $A ), '2026-10-05' ), WPCPM_Sponsors_Index::row( $A )['report_drafted_for'], count( $R::reports_of( $A ) ) ), array( false, '2026-09-30', 1 ) );

echo "\n=== The renewed term ===\n";

/** The due() entries for one sponsor. */
function due_for( $record, $today ) {
	return array_values(
		array_filter(
			WPCPM_Sponsor_Report::due( $today ),
			static function ( $entry ) use ( $record ) {
				return $record === $entry['sponsor'];
			}
		)
	);
}

// The renewal the thirty-day mail asks for, made on time: on 12 September a manager moves Renewal
// Example, a Backer to 30 September 2026, on to Champion to 30 September 2027, and rewrites what the
// money funds. The Edit save records the term it moves off through owed_after_save(), which
// bin/test-sponsors-screen.php pins the save calling; the store is patched here as that save patches it.
$R8       = 'spoAAAA0000000008';
$old_term = array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => 'Stipends for the Backer year' );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $R8, 'name' => 'Renewal Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30', 'funded_line' => 'Stipends for the Backer year' ) ) );
$before_save = WPCPM_Sponsors_Index::row( $R8 );
$owed        = $R::owed_after_save( $R8, $before_save, array_merge( $before_save, array( 'tier' => 'champion', 'tier_until' => '2027-09-30', 'funded_line' => 'Mentors for the Champion year' ) ) );
WPCPM_Sponsors_Index::patch( $R8, array( 'tier' => 'champion', 'tier_until' => '2027-09-30', 'funded_line' => 'Mentors for the Champion year', 'report_owed' => $owed ) );

ck( 'a renewal made before the term ends leaves that term owed its report on the row: the tier it held, its last day, and the line its money funded as it stood when the save moved the day',
	array( $owed, WPCPM_Sponsors_Index::row( $R8 )['report_owed'] ),
	array( $old_term, $old_term )
);

$backer_row = array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $R8, 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30' ) );

ck( 'nothing is owed by a save that leaves the day alone, by a term whose tier carries no report, or by no term; and while an owed term has no report, a second renewal keeps the first',
	array(
		$R::owed_after_save( $R8, $backer_row, array_merge( $backer_row, array( 'tier' => 'champion' ) ) ),
		$R::owed_after_save( $R8, array_merge( $backer_row, array( 'tier' => 'mentor' ) ), array_merge( $backer_row, array( 'tier_until' => '2027-09-30' ) ) ),
		$R::owed_after_save( $R8, array_merge( $backer_row, array( 'tier_until' => '' ) ), array_merge( $backer_row, array( 'tier_until' => '2027-09-30' ) ) ),
		$R::owed_after_save( $R8, WPCPM_Sponsors_Index::row( $R8 ), array_merge( WPCPM_Sponsors_Index::row( $R8 ), array( 'tier_until' => '2027-10-31' ) ) ),
	),
	array( array(), array(), array(), array() )
);

ck( 'on the term\'s last day nothing is due for it; the day after, due() lists the Backer year, a Backer\'s, though the row holds a Champion to 2027',
	array( due_for( $R8, '2026-09-30' ), due_for( $R8, '2026-10-01' ) ),
	array( array(), array( array( 'sponsor' => $R8, 'name' => 'Renewal Example', 'until' => '2026-09-30', 'tier' => 'backer' ) ) )
);

$drafted_r8 = $R::maybe_draft( $R8, WPCPM_Sponsors_Index::row( $R8 ), '2026-10-01' );
$r8_report  = $R::find( $R8, '2026-09-30' );
$r8_snap    = $r8_report instanceof WP_Post ? $R::snapshot( $r8_report ) : array( 'sponsorship' => array( 'tier' => 'none', 'tier_label' => 'none', 'events' => 'none' ) );

ck( 'the run drafts the Backer year as the Backer\'s report it was: its tier, its label, no events; and clears the owed term',
	array( $drafted_r8, $r8_snap['sponsorship']['tier'], $r8_snap['sponsorship']['tier_label'], $r8_snap['sponsorship']['events'], WPCPM_Sponsors_Index::row( $R8 )['report_owed'], WPCPM_Sponsors_Index::row( $R8 )['report_drafted_for'], due_for( $R8, '2026-10-02' ) ),
	array( true, 'backer', 'Backer', array(), array(), '2026-09-30', array() )
);
ck( 'a renewal that also rewrote the funded line leaves the old term\'s report with the old line',
	isset( $r8_snap['sponsorship']['funded_line'] ) ? $r8_snap['sponsorship']['funded_line'] : 'none',
	'Stipends for the Backer year'
);
ck( 'a regeneration of the Backer year keeps the tier and the funded line that term held, though the row holds the renewed ones',
	array( $r8_report instanceof WP_Post && $R::regenerate( get_post( $r8_report->ID ), '2026-10-02' ), $r8_report instanceof WP_Post ? $R::snapshot( get_post( $r8_report->ID ) )['sponsorship']['tier'] : 'none', $r8_report instanceof WP_Post ? $R::snapshot( get_post( $r8_report->ID ) )['sponsorship']['funded_line'] : 'none' ),
	array( true, 'backer', 'Stipends for the Backer year' )
);

// A manager's draft reads the owed term the same way: the run's draft gone, the owed term as the
// save left it, and a manager drafting that end by hand.
if ( $r8_report instanceof WP_Post ) {
	wp_delete_post( $r8_report->ID, true );
}
WPCPM_Sponsors_Index::patch( $R8, array( 'report_owed' => $old_term ) );
$GLOBALS['uid'] = 7;
$hand           = $R::generate( $R8, '2026-09-30', 'manager', '2026-10-03' );
$GLOBALS['uid'] = 0;

ck( 'a manager\'s draft for the owed end reads the tier and the funded line that term held, and clears the owed term',
	array( is_int( $hand ), is_int( $hand ) ? $R::snapshot( get_post( $hand ) )['sponsorship']['tier'] : 'none', is_int( $hand ) ? $R::snapshot( get_post( $hand ) )['sponsorship']['funded_line'] : 'none', WPCPM_Sponsors_Index::row( $R8 )['report_owed'] ),
	array( true, 'backer', 'Stipends for the Backer year', array() )
);

// With the Backer year's report there: a save moving off that day owes nothing, and an owed term
// that has its report is no longer the first one, so a later renewal records its own.
$r8_now = WPCPM_Sponsors_Index::row( $R8 );
ck( 'a term whose report exists owes nothing to a save that moves off it, and an owed term with its report gives way to the next renewal\'s',
	array(
		$R::owed_after_save( $R8, array_merge( $r8_now, array( 'tier' => 'backer', 'tier_until' => '2026-09-30' ) ), array_merge( $r8_now, array( 'tier_until' => '2027-09-30' ) ) ),
		$R::owed_after_save( $R8, array_merge( $r8_now, array( 'report_owed' => array( 'tier' => 'backer', 'until' => '2026-09-30' ) ) ), array_merge( $r8_now, array( 'tier_until' => '2028-09-30' ) ) ),
	),
	array( array(), array( 'tier' => 'champion', 'until' => '2027-09-30', 'funded_line' => 'Mentors for the Champion year' ) )
);

// An owed term whose report a manager already made, which a save can leave behind: the run finds
// the report, drafts nothing, and clears the owed term.
WPCPM_Sponsors_Index::patch( $R8, array( 'report_owed' => $old_term ) );
ck( 'an owed term with a report is cleared by the run, and nothing is drafted over it',
	array( $R::maybe_draft( $R8, WPCPM_Sponsors_Index::row( $R8 ), '2026-10-04' ), WPCPM_Sponsors_Index::row( $R8 )['report_owed'], count( $R::reports_of( $R8 ) ) ),
	array( false, array(), 1 )
);

// The daily run reads every Approved sponsor's row once, at its start, and drafts a term that ended
// with the row as it read it. A program manager who renews the sponsor while the run drafts saves
// the next term from another request, whose write this request's cache never sees; the run's stamp,
// written after, has to land beside that renewal rather than over it.
$RACE = 'spoAAAA0000000010';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $RACE, 'name' => 'Racing Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30', 'funded_line' => 'Mentor stipends', 'tier_mailed_for' => '2026-09-30' ) ) );
$race_run_row = WPCPM_Sponsors_Index::row( $RACE );

$GLOBALS['insert_hook'] = array(
	$R::POST_TYPE,
	static function () use ( $RACE ) {
		$cache = new ReflectionProperty( 'WPCPM_Sponsors_Index', 'rows_cache' );
		if ( PHP_VERSION_ID < 80100 ) {
			$cache->setAccessible( true );
		}
		$held = $cache->getValue();

		// The manager's request reads the store afresh, saves as the Edit save does, and leaves this
		// request's cache as it found it.
		WPCPM_Sponsors_Index::forget();
		$stored  = WPCPM_Sponsors_Index::row( $RACE );
		$renewal = array( 'tier' => 'champion', 'tier_until' => '2027-09-30', 'funded_line' => 'Mentors for the Champion year' );
		WPCPM_Sponsors_Index::patch( $RACE, array_merge( $renewal, array( 'report_owed' => WPCPM_Sponsor_Report::owed_after_save( $RACE, $stored, array_merge( $stored, $renewal ) ) ) ) );
		$cache->setValue( null, $held );
	},
);

$race_drafted = $R::maybe_draft( $RACE, $race_run_row, '2026-10-01' );
WPCPM_Sponsors_Index::forget();
$race_after = WPCPM_Sponsors_Index::row( $RACE );

ck( 'a renewal saved while the run drafts is kept: the run\'s stamp lands beside it, and the sponsor is a Champion to 30 September 2027 with its new line, not a Friend from the next day',
	array( $race_drafted, isset( $GLOBALS['insert_hook'] ), $race_after['tier'], $race_after['tier_until'], $race_after['funded_line'], $race_after['report_drafted_for'], WPCPM_Sponsor_Tiers::effective( $race_after, '2026-10-02' ) ),
	array( true, false, 'champion', '2027-09-30', 'Mentors for the Champion year', '2026-09-30', 'champion' )
);
ck( 'and the next run clears the owed term the renewal recorded, since its report exists, drafting nothing',
	array( $R::maybe_draft( $RACE, $race_after, '2026-10-02' ), WPCPM_Sponsors_Index::row( $RACE )['report_owed'], count( $R::reports_of( $RACE ) ), WPCPM_Sponsors_Index::row( $RACE )['tier_until'] ),
	array( false, array(), 1, '2027-09-30' )
);

// The race's report is set aside, so the uninstall below counts the reports the rest of this suite made.
foreach ( $R::reports_of( $RACE ) as $race_report ) {
	unset( $GLOBALS['posts'][ $race_report->ID ], $GLOBALS['pmeta'][ $race_report->ID ] );
}

// What the Sponsors screen reads of an owed term (1.115.0): the Tier box's Report due row, the
// Reports card's sentence and the draft form's day, and Not a renewal, which clears one on the
// trail under a kind of its own when a manager says the day was corrected rather than renewed.
ck( 'owed_of() reads the owed term as the row records it: a tier and a real day, its funded line trimmed, and nothing for half of one',
	is_callable( array( $R, 'owed_of' ) ) ? array(
		$R::owed_of( array( 'report_owed' => array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => ' Two stipends ' ) ) ),
		$R::owed_of( array( 'report_owed' => array( 'tier' => 'backer', 'until' => '2026-09-30' ) ) ),
		$R::owed_of( array( 'report_owed' => array( 'tier' => 'gold', 'until' => '2026-09-30' ) ) ),
		$R::owed_of( array( 'report_owed' => array( 'tier' => 'backer', 'until' => '2026-02-30' ) ) ),
		$R::owed_of( array() ),
	) : 'owed_of() cannot be called from outside the store',
	array( array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => 'Two stipends' ), array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => '' ), array(), array(), array() )
);
ck( 'and the kind Not a renewal writes on the trail', defined( 'WPCPM_Sponsor_Report::LOG_OWED_CLEARED' ) ? constant( 'WPCPM_Sponsor_Report::LOG_OWED_CLEARED' ) : null, 'sponsor_report_owed_cleared' );

echo "\n=== One draft of a term at a time ===\n";

/**
 * The reports the store holds for one sponsor and one period end, as IDs, oldest first.
 *
 * @param string $record Sponsor key.
 * @param string $until  The period's last day.
 * @return int[]
 */
function reports_for( $record, $until ) {
	return array_values(
		array_filter(
			report_posts(),
			static function ( $id ) use ( $record, $until ) {
				return $record === get_post_meta( $id, WPCPM_Sponsor_Report::META_SPONSOR, true ) && $until === get_post_meta( $id, WPCPM_Sponsor_Report::META_UNTIL, true );
			}
		)
	);
}

// A Backer whose term ended on 30 September 2026: the day after, the daily run drafts its report,
// and a program manager may press Draft a report for the same term at the same moment. Every day
// up to the term's last is a Backer's, so each check below drafts a period end of its own.
$LK      = 'spoAAAA0000000011';
$lk_lock = 'wpcpm_sreport_lock_' . $LK;
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $LK, 'name' => 'Lock Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30' ) ) );

ck( 'the lock is the option wpcpm_sreport_lock_ and the sponsor\'s key, honored for five minutes, the semester report\'s figure, since a report\'s figures are read over every roster',
	array( defined( 'WPCPM_Sponsor_Report::LOCK_PREFIX' ) ? constant( 'WPCPM_Sponsor_Report::LOCK_PREFIX' ) : null, defined( 'WPCPM_Sponsor_Report::LOCK_TIMEOUT' ) ? constant( 'WPCPM_Sponsor_Report::LOCK_TIMEOUT' ) : null ),
	array( 'wpcpm_sreport_lock_', 300 )
);

// The run's draft is being inserted when the manager presses: the press runs inside the run's
// insert, as another request's step, while the run holds the lock.
$pressed                 = null;
$audit_mark              = count( $GLOBALS['audit'] );
$GLOBALS['option_calls'] = array();
$GLOBALS['insert_hook']  = array(
	$R::POST_TYPE,
	static function () use ( $LK, &$pressed ) {
		$pressed = WPCPM_Sponsor_Report::generate( $LK, '2026-09-30', 'manager', '2026-10-01' );
	},
);
$run_draft = $R::generate( $LK, '2026-09-30', 'system', '2026-10-01' );

ck( 'the daily run and a manager\'s draft of one term, the manager\'s pressed while the run holds the lock, store one report, the run\'s, with one row on the trail, and the manager is told a report is being drafted',
	array( isset( $GLOBALS['insert_hook'] ), is_int( $run_draft ), reports_for( $LK, '2026-09-30' ), code_of( $pressed ), is_wp_error( $pressed ) ? $pressed->get_error_message() : '', count( $GLOBALS['audit'] ) - $audit_mark ),
	array( false, true, is_int( $run_draft ) ? array( $run_draft ) : array(), 'busy', 'A report for this sponsor is being drafted right now. Try again in a minute.', 1 )
);
ck( 'the run read the lock free from the table and took it, the press read it held and was refused it, taking nothing, and the run gave it back once the report was stored',
	array( lock_ops(), get_option( $lk_lock, 'released' ) ),
	array( array( 'read:' . $LK, 'taken:' . $LK, 'read:' . $LK, 'deleted:' . $LK ), 'released' )
);

$GLOBALS['option_calls'] = array();
$again                   = $R::generate( $LK, '2026-09-30', 'manager', '2026-10-01' );

ck( 'pressed again once the run is done, the draft finds the run\'s report under the lock, is told to open it, and gives the lock back',
	array( code_of( $again ), reports_for( $LK, '2026-09-30' ), lock_ops() ),
	array( 'wpcpm_sreport_exists', is_int( $run_draft ) ? array( $run_draft ) : array(), array( 'read:' . $LK, 'taken:' . $LK, 'deleted:' . $LK ) )
);

// Two drafts of an earlier term, the manager's pressed inside the run's once the run's report is
// stored and before its keys are written, when no look for it would find it yet.
$pressed_meta            = null;
$GLOBALS['option_calls'] = array();
$GLOBALS['meta_hook']    = array(
	$R::META_SPONSOR,
	static function () use ( $LK, &$pressed_meta ) {
		$pressed_meta = WPCPM_Sponsor_Report::generate( $LK, '2025-09-30', 'manager', '2026-10-01' );
	},
);
$meta_draft = $R::generate( $LK, '2025-09-30', 'system', '2026-10-01' );

ck( 'and while the report is stored and its keys are not yet written, when no look would find it, a second draft is still refused: the lock is held until the keys are in',
	array( isset( $GLOBALS['meta_hook'] ), code_of( $pressed_meta ), reports_for( $LK, '2025-09-30' ), lock_ops() ),
	array( false, 'busy', is_int( $meta_draft ) ? array( $meta_draft ) : array(), array( 'read:' . $LK, 'taken:' . $LK, 'read:' . $LK, 'deleted:' . $LK ) )
);

$refusals = array();

foreach ( array( array( 'not-a-key', '2024-09-30' ), array( 'spoZZZZ0000000098', '2024-09-30' ), array( $LK, '2026-02-30' ), array( $LK, '2027-03-31' ) ) as $pair ) {
	$GLOBALS['option_calls'] = array();
	$refusals[]              = array( code_of( $R::generate( $pair[0], $pair[1] ) ), lock_ops() );
}

$GLOBALS['option_calls'] = array();
$GLOBALS['insert_fail']  = $R::POST_TYPE;
$refusals[]              = array( code_of( $R::generate( $LK, '2024-09-30' ) ), lock_ops() );
$GLOBALS['insert_fail']  = '';
$after_failure           = $R::generate( $LK, '2024-09-30', 'system', '2026-10-01' );

ck( 'every refusal gives the lock back: a key the store does not hold, a value that is not a day, a day after the term, whose tier is a Friend\'s, and an insert that fails, after which the next draft is stored; a value that is no key names no lock and takes none',
	array( $refusals, array_values( preg_grep( '/^wpcpm_sreport_lock_/', array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ), is_int( $after_failure ) ),
	array(
		array(
			array( 'wpcpm_sreport_sponsor', array() ),
			array( 'wpcpm_sreport_sponsor', array( 'read:spoZZZZ0000000098', 'taken:spoZZZZ0000000098', 'deleted:spoZZZZ0000000098' ) ),
			array( 'wpcpm_sreport_date', array( 'read:' . $LK, 'taken:' . $LK, 'deleted:' . $LK ) ),
			array( 'wpcpm_sreport_tier', array( 'read:' . $LK, 'taken:' . $LK, 'deleted:' . $LK ) ),
			array( 'db_insert_error', array( 'read:' . $LK, 'taken:' . $LK, 'deleted:' . $LK ) ),
		),
		array(),
		true,
	)
);

$GLOBALS['option_calls']  = array();
$GLOBALS['insert_throws'] = $R::POST_TYPE;
$thrown                   = '';

try {
	$R::generate( $LK, '2023-09-30', 'system', '2026-10-01' );
} catch ( RuntimeException $e ) {
	$thrown = $e->getMessage();
}

unset( $GLOBALS['insert_throws'] );
$thrown_ops  = lock_ops();
$after_throw = $R::generate( $LK, '2023-09-30', 'system', '2026-10-01' );

ck( 'an error thrown inside a draft, by a callback on the insert, gives the lock back as it unwinds, so the next draft is not turned away for five minutes',
	array( $thrown, $thrown_ops, is_int( $after_throw ) ),
	array( 'A callback on the insert failed.', array( 'read:' . $LK, 'taken:' . $LK, 'deleted:' . $LK ), true )
);

// A request that died holding the lock took it five minutes and a second ago.
$GLOBALS['opts'][ $lk_lock ] = time() - 301;
$GLOBALS['option_calls']     = array();
$taken_over                  = $R::generate( $LK, '2022-09-30', 'system', '2026-10-01' );

ck( 'a lock older than five minutes belonged to a request that died holding it: it is taken over, the report drafted, and the lock given back',
	array( is_int( $taken_over ), reports_for( $LK, '2022-09-30' ), lock_ops(), get_option( $lk_lock, 'released' ) ),
	array( true, is_int( $taken_over ) ? array( $taken_over ) : array(), array( 'read:' . $LK, 'deleted:' . $LK, 'taken:' . $LK, 'deleted:' . $LK ), 'released' )
);

// A draft in flight took the lock a little under five minutes ago.
$held_at                     = time() - 290;
$GLOBALS['opts'][ $lk_lock ] = $held_at;
$GLOBALS['option_calls']     = array();
$posts_mark                  = count( $GLOBALS['posts'] );
$audit_mark                  = count( $GLOBALS['audit'] );
$refused                     = $R::generate( $LK, '2021-09-30', 'manager', '2026-10-01' );

ck( 'one younger than that is honored: the draft is refused as busy, the lock read from the table and not taken, nothing is written, and the lock is left to the request holding it',
	array( code_of( $refused ), lock_ops(), get_option( $lk_lock ), count( $GLOBALS['posts'] ) - $posts_mark, count( $GLOBALS['audit'] ) - $audit_mark ),
	array( 'busy', array( 'read:' . $LK ), $held_at, 0, 0 )
);
unset( $GLOBALS['opts'][ $lk_lock ] );

// Another request took the lock a moment ago, straight into the table, after this request had read
// the options and found no lock: on a site with a persistent object cache the name then sits on
// this request's list of absent options, which core answers from before it looks anywhere else, and
// add_option() skips its own look for a name on that list and writes over the row.
$held_at                     = time() - 5;
$GLOBALS['opts'][ $lk_lock ] = $held_at;
$GLOBALS['notoptions']       = array( $lk_lock => true );
$GLOBALS['option_calls']     = array();
$posts_mark                  = count( $GLOBALS['posts'] );
$stale_copy                  = $R::generate( $LK, '2019-09-30', 'system', '2026-10-01' );
$stale_seen                  = get_option( $lk_lock, 'absent' );
$GLOBALS['notoptions']       = array();

ck( 'a request whose own copy of the options says the lock is free, while the table holds another request\'s, is refused all the same: the lock is read from the table itself, the row left to the request holding it, and no report written',
	array( code_of( $stale_copy ), $stale_seen, array_key_exists( $lk_lock, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $lk_lock ] : 'released', count( $GLOBALS['posts'] ) - $posts_mark, lock_ops() ),
	array( 'busy', 'absent', $held_at, 0, array( 'read:' . $LK ) )
);
unset( $GLOBALS['opts'][ $lk_lock ] );

// The daily run meets a draft of the same sponsor in flight, a manager's pressed a moment before:
// the run's draft of the term that ended is refused as busy, and the run stamps nothing, so its
// next run drafts that term.
$LB      = 'spoAAAA0000000012';
$lb_lock = 'wpcpm_sreport_lock_' . $LB;
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $LB, 'name' => 'Busy Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30' ) ) );
$lb_at                       = time();
$GLOBALS['opts'][ $lb_lock ] = $lb_at;
$busy_run                    = array( $R::maybe_draft( $LB, WPCPM_Sponsors_Index::row( $LB ), '2026-10-01' ), $R::reports_of( $LB ), WPCPM_Sponsors_Index::row( $LB )['report_drafted_for'], $GLOBALS['opts'][ $lb_lock ] );
unset( $GLOBALS['opts'][ $lb_lock ] );
$next_run                    = $R::maybe_draft( $LB, WPCPM_Sponsors_Index::row( $LB ), '2026-10-02' );

ck( 'the daily run whose draft of a term meets the sponsor\'s lock held is refused, drafts nothing, stamps nothing and leaves the lock to its holder; the next run, the lock given back, drafts that term and stamps it',
	array( $busy_run, $next_run, count( $R::reports_of( $LB ) ), WPCPM_Sponsors_Index::row( $LB )['report_drafted_for'] ),
	array( array( false, array(), '', $lb_at ), true, 1, '2026-09-30' )
);

// This request looked for the term's report a moment ago and found none, and the query's cache
// holds that answer; then another request stored its draft of the term, straight into the table,
// where this request's cache does not see it.
$GLOBALS['query_memo']   = array();
$looked                  = $R::find( $LK, '2020-09-30' );
$elsewhere               = new WP_Post();
$elsewhere->ID           = $GLOBALS['next_id']++;
$elsewhere->post_type    = $R::POST_TYPE;
$elsewhere->post_status  = 'private';
$elsewhere->post_title   = 'Lock Example, October 2019 to September 2020';
$GLOBALS['posts'][ $elsewhere->ID ] = $elsewhere;
$GLOBALS['pmeta'][ $elsewhere->ID ] = array( $R::META_SPONSOR => $LK, $R::META_UNTIL => '2020-09-30', $R::META_STATE => 'draft' );
$cached                  = $R::find( $LK, '2020-09-30' );
$under_lock              = $R::generate( $LK, '2020-09-30', 'system', '2026-10-01' );
$GLOBALS['query_memo']   = null;

ck( 'under the lock the report is looked for past this request\'s cache of the query: a draft another request stored after this one last looked is found, though a cached look still misses it, and no second report is made',
	array( $looked, $cached, code_of( $under_lock ), reports_for( $LK, '2020-09-30' ) ),
	array( null, null, 'wpcpm_sreport_exists', array( $elsewhere->ID ) )
);

// The lock's reports are set aside, so the uninstall below counts the reports the rest of this
// suite made.
foreach ( array_merge( $R::reports_of( $LK ), $R::reports_of( $LB ) ) as $lk_report ) {
	unset( $GLOBALS['posts'][ $lk_report->ID ], $GLOBALS['pmeta'][ $lk_report->ID ] );
}

echo "\n=== Words keep their backslashes ===\n";

// A new report's post and snapshot, and a regenerated snapshot, are written as slashed copies, and
// the stand-ins unslash them as core's do: a company named with backslashes, and a funded line typed
// with them on the Sponsors screen, come back whole only so. The report goes again before Uninstall
// counts the reports.
$SL            = 'spoSLASHED0000001';
$slashed_name  = 'Kept in C:\drafts\reports, two in a row \\\\ here, and a "quoted" word.';
$slashed_line  = 'Funded in C:\drafts\budget, two \\\\ in a row, and "stipends".';
$slashed_later = 'Funded again in C:\drafts\later, two more \\\\ of them, and "again".';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $SL, 'name' => $slashed_name, 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30', 'funded_line' => $slashed_line ) ) );
$slashed_report = $R::generate( $SL, '2026-09-30', 'manager', '2026-10-01' );
$slashed_post   = is_int( $slashed_report ) ? get_post( $slashed_report ) : null;

ck( 'a new report is titled with the company\'s name exactly, and its snapshot keeps the funded line exactly',
	array( $slashed_post ? $slashed_post->post_title : code_of( $slashed_report ), $slashed_post ? $R::snapshot( $slashed_post )['sponsorship']['funded_line'] : null ),
	array( $slashed_name . ', October 2025 to September 2026', $slashed_line )
);

WPCPM_Sponsors_Index::patch( $SL, array( 'funded_line' => $slashed_later ) );
$slashed_again = $slashed_post ? $R::regenerate( $slashed_post, '2026-10-03' ) : false;

ck( 'and a regeneration\'s snapshot keeps the new funded line exactly',
	array( $slashed_again, $slashed_post ? $R::snapshot( get_post( $slashed_post->ID ) )['sponsorship']['funded_line'] : null ),
	array( true, $slashed_later )
);

if ( $slashed_post ) {
	foreach ( array_merge( array( $slashed_post->ID ), revisions_of( $slashed_post->ID ) ) as $slashed_id ) {
		unset( $GLOBALS['posts'][ $slashed_id ], $GLOBALS['pmeta'][ $slashed_id ] );
	}
}

echo "\n=== Uninstall ===\n";

$with_file = $R::find( $A, '2026-09-30' )->ID;
update_post_meta( $with_file, $R::META_FILE, array( 'path' => '2026/0123456789abcdef0123456789abcdef.pdf', 'sha256' => str_repeat( 'a', 64 ), 'size' => 1024 ) );
update_post_meta( $with_file, $R::META_FILE_NAME, 'Mango report.pdf' );
$R::save_narratives( get_post( $with_file ), array( 'ahead' => 'A revision to remove.' ), 7 );
$trashed = $R::find( $B, '2026-08-31' )->ID;
$GLOBALS['posts'][ $trashed ]->post_status = 'trash';

$sponsor_posts = array();
foreach ( $GLOBALS['posts'] as $post ) {
	if ( WPCPM_Sponsors_Index::POST_TYPE === $post->post_type || 'post' === $post->post_type ) { $sponsor_posts[] = $post->ID; }
}
// The locks requests left behind: one of a sponsor the store holds and one of a sponsor it has
// forgotten, both swept by the prefix; beside them the events store's own lock, and a name the
// prefix would match only if its underscores were read as any character, which both stay.
$swept_locks = array(
	'wpcpm_sreport_lock_' . $A             => time() - DAY_IN_SECONDS,
	'wpcpm_sreport_lock_spoZZZZ0000000099' => time() - 600,
);

foreach ( $swept_locks as $name => $at ) {
	$GLOBALS['opts'][ $name ] = $at;
}

$GLOBALS['opts'][ 'wpcpm_sevent_lock_' . $A ]            = time();
$GLOBALS['opts']['wpcpmXsreportXlockXspoAAAA0000000001'] = 'not a lock';
$GLOBALS['wpdb']->queries                                = array();

$opts_before = $GLOBALS['opts'];
$reports     = report_posts( array( 'private', 'trash' ) );

$R::delete_all();

$left_meta = array();
foreach ( $reports as $id ) {
	if ( ! empty( $GLOBALS['pmeta'][ $id ] ) ) { $left_meta[] = $id; }
}
$left_other = array();
foreach ( $GLOBALS['posts'] as $post ) {
	if ( WPCPM_Sponsors_Index::POST_TYPE === $post->post_type || 'post' === $post->post_type ) { $left_other[] = $post->ID; }
}

ck( 'delete_all() removes every report, a trashed one included, with its meta and its revisions', array( count( $reports ), report_posts( array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' ) ), $left_meta, revisions_of( $with_file ) ), array( 5, array(), array(), array() ) );
ck( 'and forgets the file attached to one, through the private files, once', $GLOBALS['forgotten'], array( '2026/0123456789abcdef0123456789abcdef.pdf' ) );
ck( 'and sweeps the locks requests left behind by their prefix, escaped for LIKE, through the site\'s database object: one of a sponsor the store holds and one of a sponsor it forgot, while the events store\'s lock and a name that only looks like the prefix stay',
	array( array_values( array_intersect( array_keys( $swept_locks ), array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ), 'gone' !== get_option( 'wpcpm_sevent_lock_' . $A, 'gone' ), get_option( 'wpcpmXsreportXlockXspoAAAA0000000001', 'gone' ), $GLOBALS['wpdb']->queries, $GLOBALS['unmodeled'] ),
	array( array(), true, 'not a lock', array( "SELECT option_name FROM wp_options WHERE option_name LIKE 'wpcpm\\_sreport\\_lock\\_%'" ), array() )
);
ck( 'and removes nothing else: the sponsors and their posts, the rows\' stamps and every other option stay', array( $left_other === $sponsor_posts, WPCPM_Sponsors_Index::row( $A )['report_drafted_for'], $GLOBALS['opts'] === array_diff_key( $opts_before, $swept_locks ) ), array( true, '2026-09-30', true ) );

echo "\n=== House rules ===\n";

$report_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php' );
$numbers_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-numbers.php' );

ck( 'no em or en dash in the two classes, neither names Airtable\'s class, and neither compares a sponsor ID literal with ===',
	array( preg_match( '/\x{2013}|\x{2014}/u', $report_src . $numbers_src ), strpos( $report_src . $numbers_src, 'WPCPM_Airtable' ), preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $report_src ), preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $numbers_src ) ),
	array( 0, false, 0, 0 )
);
ck( 'the numbers read no clock: the period is always the caller\'s', array( strpos( $numbers_src, '::today(' ), strpos( $numbers_src, 'time()' ), strpos( $numbers_src, "wp_date( 'Y-m-d' )" ) ), array( false, false, false ) );

/**
 * One method's body, by brace depth, for the checks that read the source rather than behavior.
 *
 * @param string $source The file.
 * @param string $name   The method.
 * @return string
 */
function method_body( $source, $name ) {
	if ( 1 !== preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}
	$at    = $m[0][1] + strlen( $m[0][0] );
	$depth = 1;
	for ( $end = $at, $length = strlen( $source ); $end < $length && $depth > 0; ++$end ) {
		$depth += '{' === $source[ $end ] ? 1 : ( '}' === $source[ $end ] ? -1 : 0 );
	}
	return substr( $source, $at, $end - $at );
}

$files_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php' );
$reader    = method_body( $files_src, 'uploaded_file' );

ck( 'the files class: no em or en dash, no Airtable, no sponsor ID compared with ===, and the uploader\'s own name nowhere near the name a download goes out under',
	array( preg_match( '/\x{2013}|\x{2014}/u', $files_src ), strpos( $files_src, 'WPCPM_Airtable' ), preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $files_src ), strpos( method_body( $files_src, 'download_name' ), 'META_FILE_NAME' ), strpos( method_body( $files_src, 'handle_download' ), 'META_FILE_NAME' ) ),
	array( 0, false, 0, false, false )
);
ck( 'it reads $_FILES in one helper alone, moves no upload, and only the two links answer a signed-out visitor',
	array( '' !== $reader && substr_count( $reader, '$_FILES' ) === substr_count( $files_src, '$_FILES' ), preg_match( '/wp_handle_upload|move_uploaded_file/', $files_src ), substr_count( $files_src, "'admin_post_nopriv_'" ) ),
	array( true, 0, 2 )
);

$GLOBALS['run_complete'] = true;

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
