<?php
/**
 * The Administrator Dashboard's sponsor events (1.117.0): what the card reads, and the three presses
 * a program manager makes on it, Approve, Return with a note and Cancel with a note.
 *
 * What is pinned here and why:
 *
 * - The wiring: the three presses are admin-post actions; the loader and uninstall.php require the
 *   class, and the Sponsors module boots it.
 * - What the card reads: nothing while the launch switch is off; the events waiting for the
 *   program's OK, every sponsor's, the one planned first at the top, as a queue is worked; the
 *   approved events still to take place, the soonest first, a Paused sponsor's among them, which no
 *   list offers and only a manager can call off; each with its sponsor's name, its facts, its call
 *   link and the names of the people on it, sorted as a person reads a list, for program managers
 *   alone, and who planned it, by the ID the post holds, whose account is not read here, and when.
 *   The tile counts what waits. What waits is a read of its own, the one the Sponsors screen's
 *   Waiting for review tab lists from, which reads nothing of an approved event; the menu bubble
 *   counts what the tile counts, whoever asks, and reads no event while the launch switch is off.
 * - Each event's item on the card carries its own id, `wpcpm-sponsor-event-<id>`, which that tab
 *   links to, and opens with its time as one reader words it (`time_fact()`), which that tab gives
 *   each event it lists too.
 * - The order every press keeps: the nonce keyed to the event first, so a stale page is stopped
 *   before anything is read, a stranger's included; then the management capability; then the
 *   launch switch and the event; then whose it is, the three decisions refusing an event of the
 *   program's own. A refused press writes nothing and mails nobody.
 * - Approve: the store approves and writes its row; every live member of the event's sponsor is
 *   mailed once, on their own clock, with the event's calendar file, which names that member as its
 *   attendee and each send removes once it is handed off, and says nothing of a file the site could
 *   not write; a former member and another sponsor's member are not mailed. A sponsor that no
 *   longer offers events (set to Paused here) has its waiting event drawn as no longer offered, with
 *   Return alone, and an approval posted anyway is refused with its own sentence, nothing written or
 *   mailed.
 * - Return with a note, sent with the sponsor posts' own button, Return it with this note: a note is
 *   required, kept whole up to the store's longest as typed, and a longer one refused, never cut;
 *   the store cancels the event and keeps the note; the members are mailed the note, with the
 *   acting manager to reply to, and told they can plan again only while the sponsor still offers
 *   events; an event approved since the page was drawn is not returned, which the store refuses
 *   itself.
 * - Cancel with a note: a note is required, and one longer than the store keeps is refused; the
 *   store cancels and tells the people on the event, answering how many it told and how many it
 *   left to its drain; the members are mailed the note, that number (one person in the singular)
 *   only when it is every person on the event, and the calendar file that takes it off their
 *   calendars, and never a name: an account that is gone, or
 *   a send the mail server refuses, leaves the number unsaid, since "have been told" would be
 *   untrue. With fifty-five on the event, the store tells fifty in the press and puts the drain on
 *   the clock for the other five, and the members read that the 55 people who joined are being
 *   told. Each person on the event is mailed once, by the store, on their own clock, with that
 *   file, which names them, and never the note, a member's name or a member's address (the queue's
 *   own mailer to them is gone, so nobody is told twice); an account that is gone is counted on the
 *   event, named nowhere and mailed nothing.
 * - Coming up carries the manager's own Join or Leave on each approved event, drawn by the list the
 *   dashboards share, before Cancel, and the list's outcome right under its heading; the names of
 *   the people on an event are read after one query warms their accounts.
 * - Across the run, no mail to a sponsor's member names a person on an event, though the waiting
 *   events approved and returned here carry one.
 * - The mails give a member's words as typed: WordPress stores a member's title and description
 *   with every bare & and > written as entities (bin/stubs/member-kses.php, on for every check),
 *   beside the cleaner's for a < and the quote marks after it. A title holding a "<3", a tag, a ">",
 *   an ampersand and quotes reaches the approved, returned and canceled mails as typed, in the
 *   subject and in the Title line, and a note with a ">", a "<3", an ampersand and quotes over two
 *   lines reaches them as typed, its line break as posted, the note of 2,000 typed characters among
 *   them; the subject goes to the mail layer as plain text, whose cleaner would read "<3 ... >" as a
 *   tag and drop it.
 * - The program's own events: the card's third part, Plan an event of the program's, draws the
 *   planner's form rows, folded, posting `wpcpm_sevent_program_plan` under a nonce of its own; the
 *   plan, change and cancel presses ask the nonce first, then the capability, the switch and an event
 *   of the program's own; the store plans it approved with no sponsor, and nobody is mailed. Coming
 *   up names it for the WordPress Credits Program, with the manager's Join, Change the event (the
 *   planner's form filled from it) and Cancel the event (no note); a change and a cancel tell the
 *   people on it and no member; each refusal is said in the card's words, naming the program.
 * - The program's plan or change refused for its fields, or by the store for a start that has
 *   passed, comes back with what was typed, through the planner's own keeper and reader: kept for
 *   the manager who pressed with no sponsor and the event a change was for, drawn once in the form
 *   it came from with every value escaped and the call link as typed, never in another manager's
 *   form, another event's form or a reload; with two events of the program's own on the card, the
 *   sooner one's form and the plan form, drawn before and after the refused one, keep their own
 *   values, every form reading what the page took once. A success keeps nothing and drops what the
 *   refusal before it kept; a press stopped before its form is read keeps nothing. The outcomes
 *   that keep it are pinned by name. Through the plugin's own flash, a backslash in the title, two
 *   in a row in the call link and a shrug over two lines in the description come back exactly as
 *   typed, in the program's Plan form and in its Change form. A title and a description holding a
 *   < , quote marks and an ampersand are drawn in their boxes as typed, not as the entities the
 *   text cleaner wrote for them. The program's Change form shows a manager's words as they were
 *   typed whether or not WordPress ran kses on them, as an administrator who holds unfiltered_html
 *   is stored as typed and one on a site that withholds it has every bare & stored as `&amp;` and
 *   every bare > as `&gt;` (bin/stubs/member-kses.php), and the boxes posted back as drawn change
 *   nothing. "Ages 8 < 12 welcome, adults > 18 pay" and "Use 2 < 3 then 5 > 4 in the demo" keep
 *   every word either way: stored as typed for the one, and with each < and > written as entities
 *   for the other, where kses would have dropped the words between them, and "We <3 our
 *   <b>team</b> > all", which an earlier release stored with the tag taken out, shows the > after
 *   the <3 as `&gt;`, so posting it back loses nothing. A fold holding one of these forms is drawn open when the form
 *   comes back with what was typed, and closed before, for another manager, for the other event's
 *   form on the same page and on the next draw.
 * - A title, a description or a note the text cleaner would take words from ("Ages 8 <12 welcome,
 *   adults >18 pay", "Get 10%cashback at the booth") is refused in the program's Plan and Change
 *   forms and in Return with a note and Cancel with a note, before the store is asked, the box
 *   named in the words of the form's button, and nothing is stored, logged or mailed; the form comes
 *   back once with exactly what was typed, its fold open, and a note refused for its length comes
 *   back too. A Change form's box posted back as drawn is not asked, and the event keeps what it
 *   holds there; refused for its fields, such a form comes back with the event's own words, not
 *   the cleaner's, so the next press changes nothing. The same words with a space after each "<"
 *   and "%" keep every word. A note that is a tag alone is refused for its words. A kept note is
 *   drawn escaped, so one holding "</textarea>" closes nothing; a press drops what an earlier
 *   refusal of the form kept; and a note is asked as a text area's typing.
 * - Every outcome returns to the card, its sentence on the Administrator Dashboard's channel.
 * - The house rules: no em or en dash, no sponsor ID compared with ===, no Airtable, and the class
 *   writes nothing of its own: the store writes.
 *
 * WordPress is the events suite's small store in memory (bin/test-sponsor-events.php): posts, their
 * meta as repeated rows, the slash pair core applies, options with `add_option()` as the lock's
 * test-and-set and the table read a lock is taken after (bin/stubs/options-table.php), the cron
 * schedule's single events, and the clock, which the store reads through its
 * seam; `wp_date()` honors the zone it is given. The request is posted slashed, as WordPress hands
 * `$_POST` to a handler. The mail layer, the calendar builder and the list of events are the
 * plugin's own, and `wp_mail()` keeps what it is handed, each attachment read at the moment of the
 * send, and refuses an address the check names, as a mail server's refusal reaches the mail
 * layer. The sponsors store, the audit trail, the settings, the flash and the two dashboards'
 * addresses are stood in for the calls made; the flash's stand-in keeps each person's channels
 * apart and remembers what a request took, as the real one does, each press and each draw of the
 * card a request of its own, the card's own channel taken first, as the page takes it. The checks
 * that pin what comes back of a backslash turn the stand-in over to the plugin's own flash, loaded
 * through a rewrite, over user meta that unslashes what it writes as core's does, each with a
 * manager of their own. Every check runs inside its own guard, so a class that is missing fails
 * each check rather than the run.
 *
 * Run from the plugin root:  php bin/test-sponsor-event-queue.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

date_default_timezone_set( 'UTC' );

$GLOBALS['now']       = (int) strtotime( '2026-10-06 12:00:00 UTC' );
$GLOBALS['site_tz']   = 'Europe/Warsaw';
$GLOBALS['opts']      = array(
	'date_format' => 'F j, Y',
	'time_format' => 'g:i a',
);
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['next_id']   = 100;
$GLOBALS['hooks']     = array();
$GLOBALS['users']     = array();
$GLOBALS['umeta']     = array();
$GLOBALS['manage']    = array();
$GLOBALS['uid']       = 0;
$GLOBALS['audit']     = array();
$GLOBALS['program_audit'] = array();
$GLOBALS['sent']      = array();
$GLOBALS['contexts']  = array();
$GLOBALS['flash']     = array();
$GLOBALS['nonces']    = array();
$GLOBALS['nonce_ok']  = true;
$GLOBALS['died_code'] = null;
$GLOBALS['unmodeled'] = array();
$GLOBALS['refuse_to'] = array();
$GLOBALS['cron']      = array();

/* ---- WordPress, stood in for the parts the queue, the store and the mail layer touch ---- */

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
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_content = '', $post_name = '', $post_author = 0, $post_parent = 0, $post_date_gmt = '0000-00-00 00:00:00';
}

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '' ) { $this->ID = (int) $id; $this->display_name = (string) $name; }
	public function exists() { return $this->ID > 0; }
}

/** A press that redirected: where to, the flash beside it. */
class Queue_Test_Left extends Exception {}

/** A press that died: the nonce screen, or the capability's refusal. */
class Queue_Test_Die extends Exception {}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function _x( $text, $context, $domain = null ) { return $text; }
// As core's esc_html(): an entity the text already holds, the ones kses writes for a member among them, is kept, not encoded again.
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
// As core's esc_attr(): an entity the value already holds is kept, not encoded again, so a box shows it as the character.
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
function esc_textarea( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function number_format_i18n( $number, $decimals = 0 ) { return (string) $number; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_title( $title ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' ); }
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }

/** Every filter passes its value through; the mail layer's is where a message's context is read. */
function apply_filters( $hook, $value, ...$args ) {
	if ( 'wpcpm_mail' === $hook ) {
		$GLOBALS['contexts'][] = isset( $args[0] ) ? (string) $args[0] : '';
	}
	return $value;
}

// Core's two text cleaners, as 7.1.2 writes them (bin/stubs/cleaners.php): a "<" that opens no tag
// is written as "&lt;", with everything after it escaped as esc_html() escapes it, a reference typed
// out in it in core's own form (`&#9;` as `&#009;`); tags go; percent-encoded octets go (which is why
// a call link must never pass through them); only the text field folds its line breaks, and the text
// area keeps each one as a browser posts it, two bytes. `wp_check_invalid_utf8()` stands beside
// them, as the plugin calls it itself.
require_once __DIR__ . '/stubs/cleaners.php';
function wp_check_invalid_utf8( $text, $strip = false ) { return (string) $text; }
function is_email( $value ) { return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : false; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_specialchars_decode( $text, $quotes = ENT_NOQUOTES ) { return htmlspecialchars_decode( (string) $text, ENT_QUOTES ); }
function home_url( $path = '' ) { return 'https://site.example' . $path; }
function network_home_url( $path = '' ) { return 'https://site.example' . $path; }
function admin_url( $path = '' ) { return 'https://site.example/wp-admin/' . $path; }
function get_bloginfo( $show = '' ) { return isset( $GLOBALS['site_name'] ) ? (string) $GLOBALS['site_name'] : 'WordPress Education'; }

/** Core's check of an address before a request is made to it, without the host's lookup. */
function wp_http_validate_url( $url ) {
	if ( ! is_string( $url ) || '' === $url || is_numeric( $url ) ) { return false; }
	$parts = parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || empty( $parts['host'] ) ) { return false; }
	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || false !== strpbrk( $parts['host'], ':#?[]' ) ) { return false; }
	if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80, 443, 8080 ), true ) ) { return false; }
	return $url;
}

/** Core's raw cleaner of an address: a protocol the list does not name is nothing, and a space is %20. */
function esc_url_raw( $url, $protocols = null ) {
	$url    = str_replace( ' ', '%20', trim( (string) $url ) );
	$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
	if ( is_array( $protocols ) && ! in_array( $scheme, $protocols, true ) ) { return ''; }
	return (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', $url );
}

// The real slash pair; the stores below unslash what they are given, as core's do.
function wp_slash( $value ) {
	if ( is_array( $value ) ) { return array_map( 'wp_slash', $value ); }
	return is_string( $value ) ? addslashes( $value ) : $value;
}
function wp_unslash( $value ) {
	if ( is_array( $value ) ) { return array_map( 'wp_unslash', $value ); }
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/** The site's clock and zone: a moment asked for with no timestamp is the suite's own now. */
function wp_date( $format, $timestamp = null, $timezone = null ) {
	$when = new DateTime( '@' . ( null === $timestamp ? (int) $GLOBALS['now'] : (int) $timestamp ) );
	$when->setTimezone( $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( $GLOBALS['site_tz'] ) );
	return $when->format( (string) $format );
}
function wp_timezone_string() { return $GLOBALS['site_tz']; }
function wp_timezone() { return new DateTimeZone( $GLOBALS['site_tz'] ); }

function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $name ] : $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['opts'][ $name ] = $value; return true; }
function delete_option( $name ) { $had = array_key_exists( $name, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $name ] ); return $had; }
/** The test-and-set a lock is built on: one row per name, refused when the row is there. */
function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $name, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $name ] = $value;
	return true;
}
// The table a lock reads its held value from, over the options above.
require_once __DIR__ . '/stubs/options-table.php';

/** Every hook registered, so `init()` can be read back. */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ (string) $hook ][] = $callback;
	return true;
}

/** The nonce: each action asked for is kept, and a stale one dies with WordPress's own sentence. */
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['nonces'][] = $action;
	if ( ! $GLOBALS['nonce_ok'] ) {
		throw new Queue_Test_Die( 'The link you followed has expired.' );
	}
	return 1;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />'; }
function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['died_code'] = is_int( $title ) ? $title : null;
	throw new Queue_Test_Die( (string) $message );
}
function wp_safe_redirect( $location, $status = 302 ) { throw new Queue_Test_Left( (string) $location ); }

/** A value as the database hands it back: text, whatever went in, and an array whole. */
function db_value( $value ) {
	if ( is_array( $value ) ) { return $value; }
	if ( is_bool( $value ) ) { return $value ? '1' : ''; }
	return null === $value ? '' : (string) $value;
}

/** A new post, dated the suite's now as core dates one that names no date. */
function wp_insert_post( $args, $wp_error = false ) {
	$args = wpcpm_stub_kses_post_args( wp_unslash( (array) $args ) );
	$post = new WP_Post();
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', (int) $GLOBALS['now'] );
	foreach ( $args as $key => $value ) {
		if ( property_exists( $post, $key ) ) { $post->$key = $value; }
	}
	$post->ID                      = $GLOBALS['next_id']++;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
/** A post's date as core answers it: the GMT date as a timestamp, the one reading the queue asks for. */
function get_post_time( $format = 'U', $gmt = false, $post = null, $translate = false ) {
	if ( 'U' !== $format || true !== $gmt ) { $GLOBALS['unmodeled'][] = 'get_post_time ' . $format; }
	$post = get_post( $post );
	return $post instanceof WP_Post ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : false;
}
function wp_update_post( $args, $wp_error = false ) {
	$args = wpcpm_stub_kses_post_args( wp_unslash( (array) $args ) );
	$id   = isset( $args['ID'] ) ? (int) $args['ID'] : 0;
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0;
	}
	foreach ( $args as $key => $value ) {
		if ( 'ID' !== $key && property_exists( $GLOBALS['posts'][ $id ], $key ) ) { $GLOBALS['posts'][ $id ]->$key = $value; }
	}
	return $id;
}
function get_post( $id ) {
	if ( $id instanceof WP_Post ) { return $id; }
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}
function wp_cache_delete( $key, $group = '' ) { return true; }

/** Post meta as core keeps it: repeated rows under one key, and `true` answers the first. */
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ][] = db_value( wp_unslash( $value ) );
	return true;
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array( db_value( wp_unslash( $value ) ) );
	return true;
}
/** Rows gone, and whether any were, as core answers: false when no row matched. */
function delete_post_meta( $id, $key, $value = '' ) {
	if ( '' === $value ) {
		$had = ! empty( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
		unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
		return $had;
	}
	$rows    = isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : array();
	$deleted = false;
	foreach ( $rows as $i => $row ) {
		if ( (string) $row === (string) $value ) {
			unset( $rows[ $i ] );
			$deleted = true;
		}
	}
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array_values( $rows );
	return $deleted;
}

/** The cron schedule, as core keeps single events: each its hook and the moment it is due. */
function wp_next_scheduled( $hook, $args = array() ) {
	foreach ( $GLOBALS['cron'] as $event ) {
		if ( $hook === $event['hook'] ) { return $event['at']; }
	}
	return false;
}
function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
	$GLOBALS['cron'][] = array( 'hook' => (string) $hook, 'at' => (int) $timestamp );
	return true;
}

/** Whether a post's rows satisfy a meta query of equal clauses, or NUMERIC greater ones, joined by AND. */
function meta_query_holds( $id, array $query ) {
	foreach ( $query as $key => $clause ) {
		if ( 'relation' === $key ) {
			if ( 'AND' !== strtoupper( (string) $clause ) ) { $GLOBALS['unmodeled'][] = 'relation ' . $clause; }
			continue;
		}
		$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';
		$numeric = isset( $clause['type'] ) && 'NUMERIC' === $clause['type'];
		$hit     = false;
		foreach ( get_post_meta( $id, $clause['key'], false ) as $value ) {
			if ( '=' === $compare ) {
				$hit = (string) $value === (string) $clause['value'];
			} elseif ( '>' === $compare && $numeric ) {
				$hit = (int) $value > (int) $clause['value'];
			} else {
				$GLOBALS['unmodeled'][] = 'compare ' . $compare;
			}
			if ( $hit ) { break; }
		}
		if ( ! $hit ) { return false; }
	}
	return true;
}

/**
 * Enough of `get_posts()`: a type, a status or a list of them, a meta query, a limit that defaults to
 * five, and IDs. The newest first, as core's default order is, so a reader that needs another order
 * has to make it. The post types each call asks for are kept in `$GLOBALS['queried']`, so a check can
 * see whether a reader asked for an event at all.
 */
function get_posts( $args = array() ) {
	$types  = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'post' );
	$status = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
	$out    = array();

	$GLOBALS['queried'][] = implode( ',', $types );
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ! in_array( $post->post_type, $types, true ) || ! in_array( $post->post_status, $status, true ) ) { continue; }
		if ( ! meta_query_holds( $post->ID, isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() ) ) { continue; }
		$out[] = $post;
	}
	usort( $out, static function ( $a, $b ) { return $b->ID - $a->ID; } );
	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5;
	if ( $limit > 0 ) { $out = array_slice( $out, 0, $limit ); }
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_map( static function ( $post ) { return $post->ID; }, $out );
	}
	return $out;
}

// The accounts, and the one capability of the program's own (bin/stubs/caps.php). An account looked
// up by a field is kept with the reads below, so a check can count every account a reader asks for.
function get_user_by( $field, $value ) {
	$GLOBALS['reads'][] = 'by ' . $field . ' ' . $value;
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( 'id' === $field && $user->ID === (int) $value ) || ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) ) { return $user; }
	}
	return false;
}
/** An account read by ID, each read kept in order with the warm-ups, so a check can see which came first. */
function get_userdata( $id ) { $GLOBALS['reads'][] = 'read ' . (int) $id; return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false; }
/** Core's warm-up of many accounts in one query, kept as it is asked. */
function cache_users( $user_ids ) { $GLOBALS['reads'][] = 'cache ' . implode( ',', array_map( 'intval', (array) $user_ids ) ); }
function wp_get_current_user() { return isset( $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ (int) $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
// As core writes it: `update_metadata()` passes the value it is handed through `wp_unslash()`.
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ (int) $id ][ $key ] = wp_unslash( $value ); return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ (int) $id ][ $key ] ); return true; }
/** The accounts holding a meta value, as the sponsor memberships ask for their holders. */
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['meta_key'] ) && (string) get_user_meta( $user->ID, $args['meta_key'], true ) !== (string) $args['meta_value'] ) { continue; }
		$out[] = $user;
	}
	return $out;
}
require_once __DIR__ . '/stubs/caps.php';
// What core does to a title and a text for a person without `unfiltered_html` (bin/stubs/member-kses.php).
require_once __DIR__ . '/stubs/member-kses.php';

/**
 * Mail: every message handed to WordPress is kept whole, its context read off the mail layer's
 * filter, and each attachment read at the moment of the send, since the mail layer removes it
 * straight after.
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	// An address in `$GLOBALS['refuse_to']` is one the mail server refuses: nothing goes, and the
	// mail layer is answered false.
	if ( in_array( (string) $to, (array) $GLOBALS['refuse_to'], true ) ) {
		return false;
	}
	$files = array();
	foreach ( (array) $attachments as $path ) {
		$files[] = array(
			'path' => (string) $path,
			'name' => basename( (string) $path ),
			'ics'  => is_file( (string) $path ) ? str_replace( "\r\n ", '', (string) file_get_contents( (string) $path ) ) : null,
		);
	}
	$GLOBALS['all_sent'][] = array( (string) $to, (string) $subject, wpcpm_footer_split( (string) $message )[0], (array) $headers );
	$GLOBALS['sent'][]     = array(
		'to'      => (string) $to,
		'context' => $GLOBALS['contexts'] ? (string) end( $GLOBALS['contexts'] ) : '',
		'subject' => (string) $subject,
		'body'    => wpcpm_footer_split( (string) $message )[0],
		'headers' => (array) $headers,
		'files'   => $files,
	);
	wpcpm_record_mail( (string) $to, $GLOBALS['contexts'] ? (string) end( $GLOBALS['contexts'] ) : '', (string) $subject, (string) $message, (array) $headers, count( $files ) );
	return true;
}

// The calendar file's scratch space: this run's own folder (bin/stubs/temp-dir.php).
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/mail-recording.php';
/** This run's folder, or, while a check says the site cannot write one, a folder that is not there. */
function get_temp_dir() { return empty( $GLOBALS['no_temp'] ) ? wpcpm_test_temp_dir() : sys_get_temp_dir() . '/wpcpm-test-no-such-folder-' . getmypid() . '/'; }
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return substr( bin2hex( random_bytes( 16 ) ), 0, (int) $length ); }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0700, true ); }
function wp_delete_file( $path ) { if ( is_file( $path ) ) { unlink( $path ); } }

/* ---- the collaborators the queue reads beside the plugin's own classes ---------- */

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

/** The sponsors store, for the calls made: the key's shape, a row, Approved. */
class WPCPM_Sponsors_Index {
	const POST_STATUSES   = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );
	const STATUS_APPROVED = 'Approved';
	public static function is_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', $value ); }
	public static function rows() { return $GLOBALS['index']; }
	public static function row( $record ) { $record = trim( (string) $record ); return self::is_key( $record ) && isset( $GLOBALS['index'][ $record ] ) ? $GLOBALS['index'][ $record ] : null; }
	public static function has( $record ) { return null !== self::row( $record ); }
}

/** The sponsors module's trail: every row kept whole, the program's own rows apart as well. */
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';
	const EVIDENCE_CACHE = 'cache';
	const EVIDENCE_LIVE  = 'live';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
	public static function record_program( array $entry ) { $GLOBALS['audit'][] = $entry; $GLOBALS['program_audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}

/**
 * The flash, kept for each person apart as the real one keeps it in their user meta: what a press
 * sets waits for the next page that person loads, and what a page takes is remembered for the rest
 * of that page, so every form the card draws reads the same value. The memory is forgotten at the
 * start of each press and each draw of the card (`press()`, `$card_for`), as a new request starts
 * with nothing taken.
 */
class WPCPM_Flash {
	/** @var array<string, mixed> Person and channel => what this request took. */
	public static $taken = array();
	/** @var bool Whether `set()` and `take()` are the plugin's own (`through_real_flash()`). */
	public static $real = false;
	public static function set( $channel, $value, $user_id = 0 ) {
		if ( self::$real ) {
			WPCPM_Flash_Real::set( $channel, $value, $user_id );
			return true;
		}
		$user = $user_id ? (int) $user_id : get_current_user_id();
		$GLOBALS['flash'][ $user ][ (string) $channel ] = $value;
		return true;
	}
	public static function take( $channel, $user_id = 0 ) {
		if ( self::$real ) {
			return WPCPM_Flash_Real::take( $channel, $user_id );
		}
		$user = $user_id ? (int) $user_id : get_current_user_id();
		$memo = $user . '|' . $channel;
		if ( ! array_key_exists( $memo, self::$taken ) ) {
			self::$taken[ $memo ] = isset( $GLOBALS['flash'][ $user ][ (string) $channel ] ) ? $GLOBALS['flash'][ $user ][ (string) $channel ] : '';
			unset( $GLOBALS['flash'][ $user ][ (string) $channel ] );
		}
		return self::$taken[ $memo ];
	}
	/** Clear, unread, each of the person's channels the test picks, as the real one does. */
	public static function sweep( $stale, $user_id = 0 ) {
		if ( self::$real ) {
			WPCPM_Flash_Real::sweep( $stale, $user_id );
			return;
		}
		$user = $user_id ? (int) $user_id : get_current_user_id();
		foreach ( isset( $GLOBALS['flash'][ $user ] ) ? $GLOBALS['flash'][ $user ] : array() as $channel => $value ) {
			if ( $stale( (string) $channel, $value ) ) {
				unset( $GLOBALS['flash'][ $user ][ $channel ] );
			}
		}
	}
	/** A new request: nothing taken yet. */
	public static function new_request() { self::$taken = array(); }
}

// The plugin's own flash, loaded through a rewrite under a name of its own. The stand-in above keeps
// each check's requests apart, which the real one cannot: what it took is a function's static for
// the life of the process. A check that pins what comes back turns the stand-in over to it
// (`through_real_flash()`), and its writes reach the user meta above, which unslashes every value
// it writes as core's does. Each such check looks with a person of its own, so the real one takes
// for each person once, as `bin/test-flash.php` takes each channel once.
$flash_source = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-flash.php' );
$flash_source = str_replace( 'class WPCPM_Flash {', 'class WPCPM_Flash_Real {', (string) preg_replace( '/^<\?php/', '', $flash_source, 1 ), $flash_renamed );

if ( 1 === $flash_renamed ) {
	eval( $flash_source );
}

/**
 * A step made through the plugin's own flash rather than the stand-in, the stand-in back after it.
 *
 * @param Closure $step The step.
 * @return mixed What the step answered.
 */
function through_real_flash( Closure $step ) {
	WPCPM_Flash::$real = true;
	try {
		return $step();
	} finally {
		WPCPM_Flash::$real = false;
	}
}

/** The channel the Administrator Dashboard prints. */
class WPCPM_Institutions {
	const FLASH = 'institutions';
}

/** Where the program decides an event. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://site.example/administrator-dashboard/'; }
}

/** Where the sponsor's members read their event: a page, unless a check says the site has none published. */
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return empty( $GLOBALS['no_pages'] ) ? 'https://site.example/sponsor-dashboard/' : ''; }
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-return.php';
require_once __DIR__ . '/../includes/class-wpcpm-ics.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
// The template registry, which every event and term email is filled from since 1.123.9.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-group-sessions.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php';

// Loaded only when it is there, so that before it exists every check below fails on its own.
$queue_file = __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-queue.php';

if ( file_exists( $queue_file ) ) {
	require_once $queue_file;
}

// The list the dashboards share, whose Join and Leave the card draws, likewise.
$list_file = __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-list.php';

if ( file_exists( $list_file ) ) {
	require_once $list_file;
}

// And the Sponsor Dashboard's planner, whose form rows and field readers the program's own form uses.
$planner_file = __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-planner.php';

if ( file_exists( $planner_file ) ) {
	require_once $planner_file;
}

// A warning, a notice or a deprecation is an error here, raised inside the guard of the check that
// caused it, so the queue cannot pass a check while complaining on the way.
set_error_handler(
	static function ( $level, $message, $file, $line ) {
		if ( ! ( error_reporting() & $level ) ) {
			return false;
		}
		throw new ErrorException( $message, 0, $level, $file, $line );
	}
);

$fails = 0;
$total = 0;

// An end before the last check is a failure: a fatal the guards did not catch would otherwise end
// the run with nothing said about the checks it never reached.
register_shutdown_function(
	static function () {
		if ( empty( $GLOBALS['run_complete'] ) ) {
			echo "\nFAIL the run ended before its last check\n";
			exit( 1 );
		}
	}
);

/**
 * One check. `$got` may be a closure, run here inside a guard: a queue that is missing or throws
 * fails this check with what it threw, and the run goes on to the next.
 *
 * @param string $label What is asked.
 * @param mixed  $got   What happened, or a closure that answers it.
 * @param mixed  $want  What should have happened.
 */
function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got instanceof Closure ) {
		try {
			$got = $got();
		} catch ( Throwable $e ) {
			$got = 'threw ' . get_class( $e ) . ': ' . $e->getMessage();
		}
	}

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/** A step the checks after it build on, inside the same guard; null when it threw. */
function attempt( Closure $step ) {
	try {
		return $step();
	} catch ( Throwable $e ) {
		return null;
	}
}

/** A moment in UTC. */
function utc( $when ) { return (int) strtotime( $when . ' UTC' ); }

function set_now( $timestamp ) { $GLOBALS['now'] = (int) $timestamp; }

/** One meta value as the store reads it back. */
function meta( $id, $key ) { return get_post_meta( (int) $id, $key, true ); }

/**
 * A press, as a manager makes it from the card: signed in as `$uid`, the form's fields posted
 * slashed as WordPress hands them over, bound for the card. What it left with: the flash on the
 * Administrator Dashboard's channel and where it went, or `died` and the sentence it died with.
 * That channel starts empty, as the page the press came from took it; what else waits for the
 * person, what a refused form typed among it, waits on, as it does on a site.
 *
 * @param string $method `handle_approve`, `handle_return` or `handle_cancel`.
 * @param array  $fields What the form posts besides the way back.
 * @param int    $uid    Who presses.
 * @return array
 */
function press( $method, array $fields, $uid ) {
	$GLOBALS['uid']       = (int) $uid;
	$GLOBALS['died_code'] = null;
	$_POST                = wp_slash( array_merge( array( 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'sponsor-events' ), $fields ) );

	unset( $GLOBALS['flash'][ (int) $uid ]['institutions'] );
	WPCPM_Flash::new_request();

	try {
		call_user_func( array( 'WPCPM_Sponsor_Event_Queue', $method ) );
	} catch ( Queue_Test_Left $e ) {
		// Through the plugin's own flash the outcome waits in the person's user meta instead.
		$waiting = WPCPM_Flash::$real ? get_user_meta( (int) $uid, 'wpcpm_flash', true ) : ( isset( $GLOBALS['flash'][ (int) $uid ] ) ? $GLOBALS['flash'][ (int) $uid ] : array() );
		return array( is_array( $waiting ) && isset( $waiting['institutions'] ) ? $waiting['institutions'] : '', $e->getMessage() );
	} catch ( Queue_Test_Die $e ) {
		return array( 'died', $e->getMessage() );
	}

	return array( 'fell through' );
}

/** What an event's form posts: its ID, and a note when one is given. */
function decision( $id, $note = null ) {
	$fields = array( 'wpcpm_sevent_id' => (string) $id );

	if ( null !== $note ) {
		$fields['wpcpm_sevent_note'] = $note;
	}

	return $fields;
}

/** The fields the store is handed to plan an event, the Partner's by default, with any replaced. */
function event_fields( $start, array $over = array() ) {
	return array_merge(
		array(
			'start'       => $start,
			'minutes'     => 90,
			'places'      => 40,
			'title'       => 'Partners "Q&A" in C:\\talks',
			'description' => "What we \"cover\" & more.\nBring questions.",
			'link'        => 'https://call.example/room?pwd=a%2Fb&id=7',
			'zone'        => 'America/New_York',
		),
		$over
	);
}

/** Everything a refused press must leave as it was: the posts, their meta, the trail, the mail and the locks. */
function store_state() { return array( $GLOBALS['posts'], $GLOBALS['pmeta'], $GLOBALS['audit'], $GLOBALS['sent'], $GLOBALS['opts'] ); }

/** The last audit row. */
function last_row() { return $GLOBALS['audit'] ? end( $GLOBALS['audit'] ) : array(); }

/** The IDs of a list of rows the card reads. */
function row_ids( array $rows ) { return array_map( static function ( $row ) { return is_array( $row ) && isset( $row['id'] ) ? $row['id'] : null; }, $rows ); }

/** The IDs of a list of events the store answers. */
function post_ids( array $posts ) { return array_map( static function ( $post ) { return $post instanceof WP_Post ? (int) $post->ID : null; }, $posts ); }

/** Who each sent mail went to, in the order sent. */
function sent_to() { return array_map( static function ( $mail ) { return $mail['to']; }, $GLOBALS['sent'] ); }

/** One calendar file's lines, as a set to look into. */
function ics_lines( $ics ) { return preg_split( '/\r\n/', (string) $ics ); }

/** The attendee lines of a calendar file that name anybody but the program's own sender. */
function people_attending( array $lines ) { return array_values( preg_grep( '/^ATTENDEE;(?!.*:mailto:wordpress@site\.example$)/', $lines ) ); }

/** The line a calendar file names the account with this address with, as its attendee. */
function attendee_for( $address ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( $user->user_email === $address ) {
			return 'ATTENDEE;CN="' . $user->display_name . '";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:' . $user->user_email;
		}
	}
	return 'no account has ' . $address;
}

/* ---- the fixture ------------------------------------------------------------ */

const MANAGER      = 1;
const PARTNER_REP  = 2;
const PARTNER_REP2 = 3;
const FORMER_REP   = 4;
const CHAMPION_REP = 5;
const PAUSED_REP   = 6;
const MANAGER_TWO  = 7;
const STRANGER     = 9;
// Two managers who each look once through the plugin's own flash (the program's refused forms).
const KEPT_PLAN_MANAGER   = 20;
const KEPT_CHANGE_MANAGER = 21;
const JOINER       = 40;
const JOINER_TWO   = 41;

const PARTNER  = 'spoAAAA0000000001'; // Partner, the term runs to 31 January 2027.
const CHAMPION = 'spoAAAA0000000002'; // Champion, no term.
const PAUSED   = 'spoAAAA0000000003'; // Champion, Approved while it plans, then set to Paused.

function a_user( $id, $name, array $roles, array $meta = array() ) {
	$user                    = new WP_User( $id, $name );
	$user->roles             = $roles;
	$user->user_email        = strtolower( str_replace( ' ', '.', $name ) ) . '@people.example';
	$GLOBALS['users'][ $id ] = $user;
	$GLOBALS['umeta'][ $id ] = $meta;
	return $user;
}

a_user( MANAGER, 'Mira Manager', array( 'administrator' ) );
// Two of the Partner's members, on two clocks: each approval mail reads the event on its own.
a_user( PARTNER_REP, 'Pat Partnerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'America/New_York' ) );
a_user( PARTNER_REP2, 'Pia Partnerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'Asia/Tokyo' ) );
// A member the Partner removed: the stamp stays, the active flag is gone, and so is the mail.
a_user( FORMER_REP, 'Fay Formerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 0 ) );
a_user( CHAMPION_REP, 'Chris Championrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => CHAMPION, 'wpcpm_sponsor_active' => 1 ) );
a_user( PAUSED_REP, 'Paula Pausedrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PAUSED, 'wpcpm_sponsor_active' => 1 ) );
a_user( MANAGER_TWO, 'Mo Manager', array( 'administrator' ) );
a_user( STRANGER, 'Stan Stranger', array( 'subscriber' ) );
// The people on the Partner's event, each on a clock of their own; one name starts in lower case, so
// a sort that puts every capital first would put it last.
a_user( JOINER, 'Zelda Privacywalk', array( 'wpcpm_student' ), array( 'wpcpm_timezone' => 'Europe/Lisbon' ) );
a_user( JOINER_TWO, 'bea Privacywalk', array( 'wpcpm_student' ), array( 'wpcpm_timezone' => 'America/Sao_Paulo' ) );

$GLOBALS['manage']   = array( MANAGER, MANAGER_TWO, KEPT_PLAN_MANAGER, KEPT_CHANGE_MANAGER );
// An administrator holds `unfiltered_html`, and what they write is stored as typed. The second manager is one on a site that withholds it, whose words WordPress runs through kses.
$GLOBALS['grants']   = array( MANAGER => array( 'unfiltered_html' ) );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$GLOBALS['index']    = array(
	PARTNER  => array( 'record_id' => PARTNER, 'name' => 'Partner Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31', 'manager' => '' ),
	CHAMPION => array( 'record_id' => CHAMPION, 'name' => 'Champion Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' ),
	PAUSED   => array( 'record_id' => PAUSED, 'name' => 'Paused Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' ),
);

// The store's clock is the suite's, from here on.
attempt(
	static function () {
		WPCPM_Sponsor_Event::for_tests(
			static function () {
				return $GLOBALS['now'];
			}
		);
	}
);

$admin_card = 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events';

/* ---- the wiring ------------------------------------------------------------- */

echo "=== The wiring ===\n";

ck(
	'the queue hooks its three presses as admin-post actions',
	static function () {
		$GLOBALS['hooks'] = array();
		WPCPM_Sponsor_Event_Queue::init();
		return array( $GLOBALS['hooks']['admin_post_wpcpm_sevent_approve'] ?? null, $GLOBALS['hooks']['admin_post_wpcpm_sevent_return'] ?? null, $GLOBALS['hooks']['admin_post_wpcpm_sevent_manager_cancel'] ?? null );
	},
	array(
		array( array( 'WPCPM_Sponsor_Event_Queue', 'handle_approve' ) ),
		array( array( 'WPCPM_Sponsor_Event_Queue', 'handle_return' ) ),
		array( array( 'WPCPM_Sponsor_Event_Queue', 'handle_cancel' ) ),
	)
);

ck(
	'and the program\'s own three, plan, change and cancel, beside them: six in all',
	static function () {
		$GLOBALS['hooks'] = array();
		WPCPM_Sponsor_Event_Queue::init();
		return array( $GLOBALS['hooks']['admin_post_wpcpm_sevent_program_plan'] ?? null, $GLOBALS['hooks']['admin_post_wpcpm_sevent_program_change'] ?? null, $GLOBALS['hooks']['admin_post_wpcpm_sevent_program_cancel'] ?? null, count( $GLOBALS['hooks'] ) );
	},
	array(
		array( array( 'WPCPM_Sponsor_Event_Queue', 'handle_program_plan' ) ),
		array( array( 'WPCPM_Sponsor_Event_Queue', 'handle_program_change' ) ),
		array( array( 'WPCPM_Sponsor_Event_Queue', 'handle_program_cancel' ) ),
		6,
	)
);

$loader_src    = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$uninstall_src = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
$sponsors_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );

ck(
	'the loader and uninstall.php require the queue',
	array(
		false !== strpos( $loader_src, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-event-queue.php';\n" ),
		false !== strpos( $uninstall_src, "require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sponsor-event-queue.php';\n" ),
	),
	array( true, true )
);
ck( 'the Sponsors module boots the queue behind the same class_exists() guard as its other classes', 1 === preg_match( "/foreach \\( array\\([^)]*'WPCPM_Sponsor_Event_Queue'[^)]*\\) as \\\$front \\)/", $sponsors_src ), true );

/* ---- the events ------------------------------------------------------------- */

// The Champion plans first, for the later day; the Partner second, for the sooner one. A Paused
// sponsor's event, planned and approved while it was Approved, is the soonest of all.
$old = attempt( static function () { return WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-01 10:00' ), array( 'title' => 'Champion webinar', 'places' => 0, 'link' => 'https://meet.example/champion', 'zone' => 'Europe/Warsaw', 'description' => 'Hands-on, with <b>tags</b> & "quotes".' ) ), CHAMPION_REP, 'member' ); } );
$new = attempt( static function () { return WPCPM_Sponsor_Event::plan( PARTNER, event_fields( utc( '2026-11-10 19:00' ) ), PARTNER_REP, 'member' ); } );
$soon = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( PAUSED, event_fields( utc( '2026-10-20 09:00' ), array( 'title' => 'Paused workshop', 'places' => 12, 'link' => 'https://meet.example/paused' ) ), PAUSED_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);
$GLOBALS['index'][ PAUSED ]['status'] = 'Paused';
$GLOBALS['audit']                     = array();

/* ---- what the card reads ------------------------------------------------------ */

echo "\n=== What the card reads ===\n";

// The page is a program manager's, and so is every read below but the one that asks otherwise.
$GLOBALS['uid'] = MANAGER;

ck(
	'nothing at all while the launch switch is off, though events wait',
	static function () {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$data                                      = WPCPM_Sponsor_Event_Queue::data();
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $data, WPCPM_Sponsor_Event_Queue::count_for_tile( $data ) );
	},
	array( array( 'waiting' => array(), 'upcoming' => array() ), 0 )
);
ck(
	'waiting: every sponsor\'s planned event, the one planned first at the top, though the other takes place sooner',
	static function () use ( $old, $new, $soon ) { return array( is_int( $old ) && is_int( $new ) && is_int( $soon ), meta( $soon, '_wpcpm_sevent_state' ), post_ids( WPCPM_Sponsor_Event::waiting() ), row_ids( WPCPM_Sponsor_Event_Queue::data()['waiting'] ) ); },
	array( true, 'approved', array( $new, $old ), array( $old, $new ) )
);
ck(
	'each waiting row: the sponsor\'s name and key, the title, the description as the store holds it, its ampersand written `&amp;` for a member, the moment, the length, the places, how many joined, the call link, and nobody on it by name; a sponsor\'s, not the program\'s own; and who planned it, by the ID the post holds, and when, which the Sponsors screen\'s queue tab says of it',
	static function () use ( $old ) { return WPCPM_Sponsor_Event_Queue::data()['waiting'][0]; },
	array(
		'id'             => $old,
		'sponsor'        => CHAMPION,
		'sponsor_name'   => 'Champion Example',
		'title'          => 'Champion webinar',
		'description'    => 'Hands-on, with tags &amp; "quotes".',
		'start'          => utc( '2026-12-01 10:00' ),
		'end'            => utc( '2026-12-01 11:30' ),
		'minutes'        => 90,
		'places'         => 0,
		'joined'         => 0,
		'link'           => 'https://meet.example/champion',
		'people'         => array(),
		'offered'        => true,
		'program'        => false,
		'planned_author' => CHAMPION_REP,
		'planned_at'     => utc( '2026-10-06 12:00' ),
	)
);
// The Administrator Dashboard and the Overview draw these rows and print no planner, so a row reads no
// planner's account: it carries its author's ID as the post holds it, and the Sponsors screen's queue
// tab reads the planners' accounts where it prints their names. Nobody is on any event yet, so the card
// reads no account at all.
ck(
	'the rows read no planner\'s account, what waits and what is coming up alike: each carries its author\'s ID as the post holds it, an approved event\'s too',
	static function () use ( $new ) {
		$GLOBALS['reads'] = array();
		$card             = WPCPM_Sponsor_Event_Queue::data();
		$alone            = WPCPM_Sponsor_Event_Queue::waiting_rows();
		return array( $GLOBALS['reads'], $alone[1]['id'] === $new, $alone[1]['planned_author'], $card['upcoming'][0]['planned_author'] );
	},
	array( array(), true, PARTNER_REP, PAUSED_REP )
);
ck(
	'coming up: the approved event of a sponsor set to Paused, which no list offers, so that a manager can still call it off',
	static function () use ( $soon ) { $upcoming = WPCPM_Sponsor_Event_Queue::data()['upcoming']; return array( row_ids( $upcoming ), $upcoming[0]['offered'], in_array( $soon, post_ids( WPCPM_Sponsor_Event::open_for( MANAGER ) ), true ) ); },
	array( array( $soon ), false, false )
);
ck(
	'a person who does not manage the program reads nothing this way, a sponsor\'s own member included: the names and the call links are the program\'s',
	static function () {
		$read = array();
		foreach ( array( PARTNER_REP, STRANGER, 0 ) as $person ) {
			$GLOBALS['uid'] = $person;
			$read[]         = WPCPM_Sponsor_Event_Queue::data();
		}
		$GLOBALS['uid'] = MANAGER;
		return array_values( array_unique( array_map( 'serialize', $read ) ) ) === array( serialize( array( 'waiting' => array(), 'upcoming' => array() ) ) );
	},
	true
);
ck( 'the tile counts what waits', static function () { return WPCPM_Sponsor_Event_Queue::count_for_tile( WPCPM_Sponsor_Event_Queue::data() ); }, 2 );
ck( 'and reads a list the page never read as nothing', static function () { return array( WPCPM_Sponsor_Event_Queue::count_for_tile( array() ), WPCPM_Sponsor_Event_Queue::count_for_tile( array( 'waiting' => 'not a list' ) ) ); }, array( 0, 0 ) );

// The Sponsors screen's Waiting for review tab lists what waits from the card's own read of it, and
// reads nothing of what is coming up; its menu bubble counts what the tile counts.
ck(
	'what waits is a read of its own, the card\'s first part as the card reads it, in its order; it reads nothing of an approved event, not the names of the people on one',
	static function () use ( $old, $new, $soon ) {
		add_post_meta( $soon, '_wpcpm_sevent_person', JOINER );
		try {
			$GLOBALS['reads'] = array();
			$alone            = WPCPM_Sponsor_Event_Queue::waiting_rows();
			$alone_reads      = $GLOBALS['reads'];
			$GLOBALS['reads'] = array();
			$card             = WPCPM_Sponsor_Event_Queue::data();
			$card_reads       = $GLOBALS['reads'];
		} finally {
			delete_post_meta( $soon, '_wpcpm_sevent_person', JOINER );
		}
		return array( $alone === $card['waiting'], row_ids( $alone ) === array( $old, $new ), $alone_reads, $card_reads );
	},
	array( true, true, array(), array( 'cache ' . JOINER, 'read ' . JOINER ) )
);
ck(
	'and it is read for nobody but a program manager, and not at all while the launch switch is off',
	static function () {
		$read = array();
		foreach ( array( PARTNER_REP, STRANGER, 0 ) as $person ) {
			$GLOBALS['uid'] = $person;
			$read[]         = WPCPM_Sponsor_Event_Queue::waiting_rows();
		}
		$GLOBALS['uid']                            = MANAGER;
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$read[]                                    = WPCPM_Sponsor_Event_Queue::waiting_rows();
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return $read;
	},
	array( array(), array(), array(), array() )
);
ck(
	'the menu bubble\'s count is the tile\'s, whoever asks, since one cached number serves every admin page and names nobody; none while the launch switch is off',
	static function () {
		$counted = array();
		foreach ( array( MANAGER, STRANGER, 0 ) as $person ) {
			$GLOBALS['uid'] = $person;
			$counted[]      = WPCPM_Sponsor_Event_Queue::count_waiting();
		}
		$GLOBALS['uid']                            = MANAGER;
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$counted[]                                 = WPCPM_Sponsor_Event_Queue::count_waiting();
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $counted, WPCPM_Sponsor_Event_Queue::count_for_tile( WPCPM_Sponsor_Event_Queue::data() ) );
	},
	array( array( 2, 2, 2, 0 ), 2 )
);
ck(
	'and while the launch switch is off the bubble\'s count reads no event at all, where with it on it reads them',
	static function () {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$GLOBALS['queried']                        = array();
		WPCPM_Sponsor_Event_Queue::count_waiting();
		$read_off                                  = $GLOBALS['queried'];
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		$GLOBALS['queried']                        = array();
		WPCPM_Sponsor_Event_Queue::count_waiting();
		return array( $read_off, in_array( 'wpcpm_sponsor_event', $GLOBALS['queried'], true ) );
	},
	array( array(), true )
);

/* ---- the nonce first, then the capability ------------------------------------ */

echo "\n=== The nonce first, then the capability ===\n";

ck(
	'a stale page is stopped on the nonce keyed to the event, before the capability is asked or anything is read: a stranger meets the nonce screen too',
	static function () use ( $new ) {
		$before              = store_state();
		$GLOBALS['nonces']   = array();
		$GLOBALS['nonce_ok'] = false;
		$left                = array();
		foreach ( array( 'handle_approve', 'handle_return', 'handle_cancel' ) as $method ) {
			$left[] = press( $method, decision( $new, 'A note.' ), STRANGER );
		}
		$GLOBALS['nonce_ok'] = true;
		return array( $left, $GLOBALS['nonces'], store_state() === $before );
	},
	array(
		array( array( 'died', 'The link you followed has expired.' ), array( 'died', 'The link you followed has expired.' ), array( 'died', 'The link you followed has expired.' ) ),
		array( 'wpcpm_sevent_approve_' . $new, 'wpcpm_sevent_return_' . $new, 'wpcpm_sevent_manager_cancel_' . $new ),
		true,
	)
);
ck(
	'somebody without the management capability is refused with the program\'s 403, a sponsor\'s own member included, and nothing is written or mailed',
	static function () use ( $new ) {
		$before = store_state();
		$left   = array();
		foreach ( array( 'handle_approve', 'handle_return', 'handle_cancel' ) as $method ) {
			$left[] = array( press( $method, decision( $new, 'A note.' ), PARTNER_REP ), $GLOBALS['died_code'] );
		}
		return array( $left, store_state() === $before );
	},
	array(
		array(
			array( array( 'died', 'You do not have permission to manage the program.' ), 403 ),
			array( array( 'died', 'You do not have permission to manage the program.' ), 403 ),
			array( array( 'died', 'You do not have permission to manage the program.' ), 403 ),
		),
		true,
	)
);
ck(
	'with the launch switch off every press goes back to the card with its own sentence, and nothing is written or mailed',
	static function () use ( $new ) {
		$before                                    = store_state();
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$left                                      = array();
		foreach ( array( 'handle_approve', 'handle_return', 'handle_cancel' ) as $method ) {
			$left[] = press( $method, decision( $new, 'A note.' ), MANAGER );
		}
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $left, store_state() === $before );
	},
	array( array( array( 'sevent-off', $admin_card ), array( 'sevent-off', $admin_card ), array( 'sevent-off', $admin_card ) ), true )
);
ck(
	'an ID that is no event, or no ID at all, is said as gone, and nothing is written or mailed',
	static function () {
		$before = store_state();
		$left   = array();
		foreach ( array( 'handle_approve', 'handle_return', 'handle_cancel' ) as $method ) {
			$left[] = press( $method, decision( 99999, 'A note.' ), MANAGER );
		}
		$left[] = press( 'handle_approve', array(), MANAGER );
		return array( $left, $GLOBALS['nonces'][ count( $GLOBALS['nonces'] ) - 1 ], store_state() === $before );
	},
	array( array( array( 'sevent-gone', $admin_card ), array( 'sevent-gone', $admin_card ), array( 'sevent-gone', $admin_card ), array( 'sevent-gone', $admin_card ) ), 'wpcpm_sevent_approve_0', true )
);

/* ---- approve ------------------------------------------------------------------ */

echo "\n=== Approve ===\n";

// Nobody can join an event still waiting, but a person put on it by hand makes a mail that names the
// people on an event say so: the approval's mail below is pinned whole.
attempt( static function () use ( $new ) { add_post_meta( $new, '_wpcpm_sevent_person', JOINER ); } );

$GLOBALS['sent'] = array();
$approve_left    = attempt( static function () use ( $new ) { return press( 'handle_approve', decision( $new ), MANAGER ); } );

ck( 'the manager approves the Partner\'s event: sevent-approved, back at the card', $approve_left, array( 'sevent-approved', $admin_card ) );
ck( 'the store approved it, stamped by the manager, and wrote its row on the manager ground', static function () use ( $new ) { $last = last_row(); return array( meta( $new, '_wpcpm_sevent_state' ), meta( $new, '_wpcpm_sevent_decision' )['by'], $last['kind'], $last['ground'], $last['actor'], $last['subject'] ); }, array( 'approved', MANAGER, 'sponsor_event_approved', 'manager', MANAGER, (string) $new ) );
ck(
	'each live member of the Partner is mailed once, logged as event-approved; the member it removed and the Champion\'s member are not',
	static function () { return array( sent_to(), array_values( array_unique( array_map( static function ( $mail ) { return $mail['context']; }, $GLOBALS['sent'] ) ) ) ); },
	array( array( 'pat.partnerrep@people.example', 'pia.partnerrep@people.example' ), array( 'event-approved' ) )
);
ck(
	'the mail says the event is approved, on the member\'s own clock with the zone named, its length, its places and its call link, and where the sponsor reads it',
	static function () { return array( $GLOBALS['sent'][0]['subject'], $GLOBALS['sent'][0]['body'], $GLOBALS['sent'][0]['headers'] ); },
	array(
		'[WordPress Education] Your event is approved: Partners "Q&A" in C:\\talks',
		"An Administrator approved your company's event. The calendar file attached adds it to your calendar.\n\n"
			. "Title: Partners \"Q&A\" in C:\\talks\nWhen: November 10, 2026 2:00 pm to 3:30 pm (America / New York)\nLength: 90 minutes\nPlaces: 40\nCall link: https://call.example/room?pwd=a%2Fb&id=7\n\n"
			. "Your event is on the Events card of the Sponsor Dashboard:\nhttps://site.example/sponsor-dashboard/#wpcpm-sponsor-events",
		array(),
	)
);
ck(
	'the second member reads it on their own clock, where it is the next morning',
	static function () { return 1 === preg_match( '/^When: November 11, 2026 4:00 am to 5:30 am \(Asia \/ Tokyo\)$/m', $GLOBALS['sent'][1]['body'] ); },
	true
);
ck(
	'each approval carries the event\'s calendar file, a request at the approval\'s version under the event\'s own identity, with the call link in its description, and the member it went to as its attendee',
	static function () use ( $new ) {
		$out = array();
		foreach ( $GLOBALS['sent'] as $mail ) {
			$file  = isset( $mail['files'][0] ) ? $mail['files'][0] : array( 'name' => '', 'ics' => '' );
			$lines = ics_lines( $file['ics'] );
			$out[] = array(
				count( $mail['files'] ),
				$file['name'],
				in_array( 'METHOD:REQUEST', $lines, true ),
				in_array( 'UID:wpcpm-call-' . $new . '@site.example', $lines, true ),
				in_array( 'SEQUENCE:1', $lines, true ),
				in_array( 'STATUS:CONFIRMED', $lines, true ),
				in_array( 'SUMMARY:Partners "Q&A" in C:\\\\talks', $lines, true ),
				false !== strpos( (string) $file['ics'], 'Join the call: https://call.example/room?pwd=a%2Fb&id=7' ),
				people_attending( $lines ),
			);
		}
		return $out;
	},
	array(
		array( 1, 'sponsor-event.ics', true, true, true, true, true, true, array( attendee_for( 'pat.partnerrep@people.example' ) ) ),
		array( 1, 'sponsor-event.ics', true, true, true, true, true, true, array( attendee_for( 'pia.partnerrep@people.example' ) ) ),
	)
);
ck(
	'and each send removes its file once it is handed off, so the next member\'s mail has a file of its own',
	static function () {
		$paths = array_map( static function ( $mail ) { return isset( $mail['files'][0] ) ? $mail['files'][0]['path'] : ''; }, $GLOBALS['sent'] );
		return array( count( array_unique( $paths ) ), array_values( array_filter( $paths, 'file_exists' ) ) );
	},
	array( 2, array() )
);
ck(
	'approved already, a second approval is refused as no longer waiting, and nothing is written or mailed',
	static function () use ( $new ) {
		$before = store_state();
		return array( press( 'handle_approve', decision( $new ), MANAGER ), store_state() === $before );
	},
	array( array( 'sevent-not-waiting', $admin_card ), true )
);
ck(
	'a Return pressed on a page drawn before the approval is refused the same way: an approved event is called off with Cancel, the people on it told',
	static function () use ( $new ) {
		$before = store_state();
		return array( press( 'handle_return', decision( $new, 'Too late for this.' ), MANAGER ), store_state() === $before );
	},
	array( array( 'sevent-not-waiting', $admin_card ), true )
);

// Three people join the Partner's event: a student, a program manager, and a student whose name
// starts in lower case. The person put on it by hand before the approval comes off first.
attempt(
	static function () use ( $new ) {
		delete_post_meta( $new, '_wpcpm_sevent_person', JOINER );
		foreach ( array( JOINER, MANAGER_TWO, JOINER_TWO ) as $person ) {
			add_post_meta( $new, '_wpcpm_sevent_person', $person );
		}
	}
);

ck(
	'coming up now holds the Partner\'s event too, the soonest first, with how many joined and their names sorted as a person reads a list',
	static function () use ( $soon, $new ) {
		$upcoming = WPCPM_Sponsor_Event_Queue::data()['upcoming'];
		return array( row_ids( $upcoming ), $upcoming[1]['joined'], $upcoming[1]['people'], $upcoming[1]['link'], $upcoming[0]['people'] );
	},
	array( array( $soon, $new ), 3, array( 'bea Privacywalk', 'Mo Manager', 'Zelda Privacywalk' ), 'https://call.example/room?pwd=a%2Fb&id=7', array() )
);
ck(
	'the names are read after one query has warmed every account on the event, not one query each; an event nobody is on warms nothing',
	static function () {
		$GLOBALS['reads'] = array();
		WPCPM_Sponsor_Event_Queue::data();
		return $GLOBALS['reads'];
	},
	array( 'cache ' . JOINER . ',' . MANAGER_TWO . ',' . JOINER_TWO, 'read ' . JOINER, 'read ' . MANAGER_TWO, 'read ' . JOINER_TWO )
);

/** The card as one manager sees it, with whatever waits on the list's channel for them. */
$card_for = static function ( $uid ) {
	$GLOBALS['uid'] = (int) $uid;
	// The Administrator Dashboard takes its own channel at the top of the page, before any card.
	WPCPM_Flash::new_request();
	WPCPM_Flash::take( WPCPM_Institutions::FLASH );
	ob_start();
	try {
		WPCPM_Sponsor_Event_Queue::render( WPCPM_Sponsor_Event_Queue::data() );
	} finally {
		$html           = (string) ob_get_clean();
		$GLOBALS['uid'] = MANAGER;
	}
	return $html;
};

ck(
	'a manager who is not on an approved event gets its Join, inside its decisions and before Cancel, posting the event under a nonce keyed to it and coming back to this card; no call link of its own, since the card has one',
	static function () use ( $card_for, $new ) {
		$html = $card_for( MANAGER );
		$join = '<div class="wpcpm-sevent__actions"><form class="wpcpm-inline-form wpcpm-sevent__join" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Joining"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_join_' . $new . '" /><input type="hidden" name="action" value="wpcpm_sevent_join" /><input type="hidden" name="wpcpm_sevent_id" value="' . $new . '" /><input type="hidden" name="wpcpm_sevent_back" value="admin" /><button type="submit" class="wpcpm-button">Join<span class="screen-reader-text"> Partners &quot;Q&amp;A&quot; in C:\\talks</span></button></form></div>';
		return array(
			false !== strpos( $html, '<div class="wpcpm-request__decide wpcpm-sponsor-post__decide">' . $join . '<details class="wpcpm-sponsor-post__return"><summary class="button">Cancel with a note' ),
			substr_count( $html, 'wpcpm-sevent__link' ),
			substr_count( $html, 'wpcpm_sevent_leave' ),
		);
	},
	array( true, 0, 0 )
);
ck(
	'a manager on it gets the mark and Leave instead, asking before it posts, and still no call link of the list\'s',
	static function () use ( $card_for, $new ) {
		$html = $card_for( MANAGER_TWO );
		return array(
			false !== strpos( $html, '<div class="wpcpm-sevent__actions"><p class="wpcpm-sevent__joined">You are on it.</p><form class="wpcpm-inline-form wpcpm-sevent__leave" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Leaving"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_leave_' . $new . '" /><input type="hidden" name="action" value="wpcpm_sevent_leave" /><input type="hidden" name="wpcpm_sevent_id" value="' . $new . '" /><input type="hidden" name="wpcpm_sevent_back" value="admin" /><button type="submit" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-confirm="Leave this event? Your place goes back.">Leave<span class="screen-reader-text"> Partners &quot;Q&amp;A&quot; in C:\\talks</span></button></form></div><details class="wpcpm-sponsor-post__return">' ),
			substr_count( $html, 'wpcpm-sevent__link' ),
			substr_count( $html, 'wpcpm_sevent_join' ),
		);
	},
	array( true, 0, 0 )
);
ck(
	'the approved event of a sponsor that no longer offers events gets no Join, and a waiting event neither: one action form on the card, the Partner\'s',
	static function () use ( $card_for ) { return substr_count( $card_for( MANAGER ), 'class="wpcpm-sevent__actions"' ); },
	1
);
// The same decision reads the same on every card that sends a sponsor's own work back: a post, a
// sponsor's Collaboration Agreement and an event each go back with Return it with this note, the
// words the institutions' agreements use.
ck(
	'a waiting event goes back with the button the sponsor posts card uses: Return it with this note',
	static function () use ( $card_for ) {
		$html = $card_for( MANAGER );
		return array( substr_count( $html, 'data-wpcpm-busy="Returning">' ) > 0, substr_count( $html, 'data-wpcpm-busy="Returning">' ) === substr_count( $html, '<button type="submit" class="button">Return it with this note</button>' ), strpos( $html, 'Send back with this note' ) );
	},
	array( true, true, false )
);
// Cancel with this note cannot be taken back, and everyone on the event is told, so it asks first: the
// sentence rides on its button as a data-wpcpm-confirm mark, escaped for the attribute. Approve and
// Return it with this note, which the sponsor can follow with a new plan, ask nothing.
ck(
	'every Cancel with this note asks "Cancel this event for everyone?" on its button, the sentence escaped with esc_attr(), one for each Cancel with a note; Approve and Return it with this note ask nothing',
	static function () use ( $card_for ) {
		$html    = $card_for( MANAGER );
		$mark    = ' data-wpcpm-confirm="' . esc_attr( 'Cancel this event for everyone? This cannot be undone.' ) . '"';
		$cancels = preg_match_all( '#<button\b[^>]*>Cancel with this note</button>#', $html, $found ) ? $found[0] : array();
		$others  = preg_match_all( '#<button\b[^>]*>(?:Approve<span\b[^<]*</span>|Return it with this note)</button>#', $html, $found ) ? $found[0] : array();
		return array(
			count( $cancels ) >= 1,
			count( array_filter( $cancels, static function ( $button ) use ( $mark ) { return false !== strpos( $button, $mark ); } ) ) === count( $cancels ),
			count( $cancels ) === substr_count( $html, '<summary class="button">Cancel with a note' ),
			count( $others ) >= 2,
			substr_count( implode( '', $others ), 'data-wpcpm-confirm' ),
		);
	},
	array( true, true, true, true, 0 )
);
// The Sponsors screen's Waiting for review tab links each event it lists to its own item here, as it
// links each sponsor post and application to theirs: the item carries the id the link names, and the
// page's opener opens the card around it.
ck(
	'each event\'s item carries its own id, wpcpm-sponsor-event and the event\'s ID, what waits and what is coming up alike, once each',
	static function () use ( $card_for, $old, $new, $soon ) {
		$html = $card_for( MANAGER );
		preg_match_all( '#<article class="wpcpm-administrator__item wpcpm-sponsor-event" id="wpcpm-sponsor-event-(\d+)">#', $html, $ids );
		return array( array_map( 'intval', $ids[1] ), substr_count( $html, '<article ' ), substr_count( $html, 'id="wpcpm-sponsor-event-' . $old . '"' ) );
	},
	array( array( $old, $soon, $new ), 3, 1 )
);
// That tab says when each event takes place in the words its item here opens with: one reader, on the
// viewing manager's clock unless another is given.
ck(
	'an event\'s time is read by one reader, on the viewing manager\'s clock unless another is given: the day, the times and the length, the zone named, the fact its item opens with',
	static function () use ( $card_for, $old ) {
		$row  = WPCPM_Sponsor_Event_Queue::data()['waiting'][0];
		$html = $card_for( MANAGER );
		return array(
			$row['id'] === $old,
			WPCPM_Sponsor_Event_Queue::time_fact( $row ),
			WPCPM_Sponsor_Event_Queue::time_fact( $row, new DateTimeZone( 'Asia/Tokyo' ) ),
			false !== strpos( $html, 'id="wpcpm-sponsor-event-' . $old . '"><h4 class="wpcpm-administrator__item-title">Champion webinar <span class="wpcpm-administrator__kind">Champion Example</span></h4><p class="wpcpm-administrator__facts"><span class="wpcpm-administrator__fact">' . WPCPM_Sponsor_Event_Queue::time_fact( $row ) . '</span>' ),
		);
	},
	array( true, 'December 1, 2026 11:00 am to 12:30 pm, 90 minutes, in your time zone (Europe / Warsaw)', 'December 1, 2026 7:00 pm to 8:30 pm, 90 minutes, in your time zone (Asia / Tokyo)', true )
);
ck(
	'the outcome of the manager\'s own press is said right under Coming up, taken once, and nowhere else on the card',
	static function () use ( $card_for ) {
		$GLOBALS['flash'][ MANAGER ]['sevent-list'] = 'joined';
		$first                                      = $card_for( MANAGER );
		$second                                     = $card_for( MANAGER );
		return array( false !== strpos( $first, '<h4 class="wpcpm-administrator__subtitle">Coming up</h4><p class="wpcpm-sevent__message is-success" role="status">You joined the event.</p>' ), substr_count( $first, 'wpcpm-sevent__message' ), substr_count( $second, 'wpcpm-sevent__message' ) );
	},
	array( true, 1, 0 )
);

/* ---- return with a note -------------------------------------------------------- */

echo "\n=== Return with a note ===\n";

ck(
	'no note is refused, a note of spaces and line breaks too, and a note that is a tag alone, which the cleaner would take whole, is refused for its words; nothing is written or mailed',
	static function () use ( $old ) {
		$before = store_state();
		return array( press( 'handle_return', decision( $old ), MANAGER ), press( 'handle_return', decision( $old, "  \n\t " ), MANAGER ), press( 'handle_return', decision( $old, '<b></b>' ), MANAGER ), store_state() === $before );
	},
	array( array( 'sevent-note-empty', $admin_card ), array( 'sevent-note-empty', $admin_card ), array( 'sevent-return-note-loss', $admin_card ), true )
);

// A person put on the waiting event by hand, as before the approval: the return's mail is pinned whole.
attempt( static function () use ( $old ) { add_post_meta( $old, '_wpcpm_sevent_person', JOINER ); } );

$GLOBALS['sent'] = array();
$return_note     = "Move it to a weekday & say who \"hosts\" it.\nThe slides are in C:\\shared.";
$return_left     = attempt( static function () use ( $old, $return_note ) { return press( 'handle_return', decision( $old, $return_note ), MANAGER ); } );

attempt( static function () use ( $old ) { delete_post_meta( $old, '_wpcpm_sevent_person', JOINER ); } );

ck( 'the manager returns the Champion\'s event with a note: sevent-returned, back at the card', $return_left, array( 'sevent-returned', $admin_card ) );
ck(
	'the store canceled it and kept the note as written, and wrote its row on the manager ground, which holds no note',
	static function () use ( $old, $return_note ) {
		$last = last_row();
		return array( meta( $old, '_wpcpm_sevent_state' ), meta( $old, '_wpcpm_sevent_decision' )['note'], $last['kind'], $last['ground'], $last['actor'], false !== strpos( var_export( $last, true ), 'weekday' ) );
	},
	array( 'cancelled', $return_note, 'sponsor_event_returned', 'manager', MANAGER, false )
);
ck(
	'the Champion\'s one member is mailed the note, told the event is canceled and that it can plan again, with the manager to reply to, and no calendar file: it never had one',
	static function () {
		return array( sent_to(), $GLOBALS['sent'][0]['context'], $GLOBALS['sent'][0]['subject'], $GLOBALS['sent'][0]['body'], $GLOBALS['sent'][0]['headers'], $GLOBALS['sent'][0]['files'] );
	},
	array(
		array( 'chris.championrep@people.example' ),
		'event-returned',
		'[WordPress Education] Your event was returned: Champion webinar',
		"An Administrator returned your company's event with this note:\n\n"
			. "Move it to a weekday & say who \"hosts\" it.\nThe slides are in C:\\shared.\n\n"
			. "Title: Champion webinar\nWhen: December 1, 2026 11:00 am to 12:30 pm (Europe / Warsaw)\n\n"
			. "The event is canceled. You can plan it again on your dashboard.\nhttps://site.example/sponsor-dashboard/#wpcpm-sponsor-events",
		array( 'Reply-To: Mira Manager <mira.manager@people.example>' ),
		array(),
	)
);
ck(
	'returned already, it is refused as canceled, and nothing is written or mailed',
	static function () use ( $old ) {
		$before = store_state();
		return array( press( 'handle_return', decision( $old, 'Once more.' ), MANAGER ), press( 'handle_approve', decision( $old ), MANAGER ), store_state() === $before );
	},
	array( array( 'sevent-was-canceled', $admin_card ), array( 'sevent-was-canceled', $admin_card ), true )
);

// The Champion plans again, and the manager writes a note as long as the box takes, with a "<", an
// "&" and line breaks in it, posted as a browser posts it. The cleaner writes the "<", and the quote
// marks and the ampersand after it, as entities, so the note is stored longer than it was typed: the
// limit counts what was typed, as the box counts it, and the note is kept whole. One typed character
// more is refused, never cut, and nothing is written or mailed.
$again       = attempt( static function () { return WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-03 10:00' ), array( 'title' => 'Champion webinar, again', 'zone' => 'Europe/Warsaw' ) ), CHAMPION_REP, 'member' ); } );
$note_typed  = str_repeat( 'Kids < 12 learn "blocks" & themes.' . "\n", 57 ) . 'Done.';
$note_posted = str_replace( "\n", "\r\n", $note_typed );

ck(
	'a note of 2,000 typed characters with a "<", an "&" and line breaks is kept whole in the event, and the mail gives it as it was typed, its line breaks as posted; one typed character more is refused with nothing written or mailed',
	static function () use ( $again, $note_typed, $note_posted ) {
		$GLOBALS['sent'] = array();
		$before          = store_state();
		$over            = press( 'handle_return', decision( $again, $note_posted . '!' ), MANAGER );
		$untouched       = store_state() === $before;
		$left            = press( 'handle_return', decision( $again, $note_posted ), MANAGER );
		$kept            = meta( $again, '_wpcpm_sevent_decision' )['note'];
		return array(
			mb_strlen( $note_typed ),
			array( $over[0], $untouched ),
			$left[0],
			mb_strlen( $kept ) > 2000,
			WPCPM_Sponsor_Event::typed_length( $kept ),
			str_replace( "\r\n", "\n", wp_specialchars_decode( $kept, ENT_QUOTES ) ) === $note_typed,
			false !== strpos( $GLOBALS['sent'][0]['body'], "with this note:\n\n" . $note_posted . "\n\nTitle:" ),
		);
	},
	array( 2000, array( 'sevent-note-long', true ), 'sevent-returned', true, 2000, true, true )
);

/* ---- cancel with a note -------------------------------------------------------- */

echo "\n=== Cancel with a note ===\n";

ck(
	'no note is refused, and the event stays approved with nothing written or mailed',
	static function () use ( $new ) {
		$before = store_state();
		return array( press( 'handle_cancel', decision( $new ), MANAGER ), press( 'handle_cancel', decision( $new, ' ' ), MANAGER ), store_state() === $before, meta( $new, '_wpcpm_sevent_state' ) );
	},
	array( array( 'sevent-note-empty', $admin_card ), array( 'sevent-note-empty', $admin_card ), true, 'approved' )
);
ck(
	'a note one typed character over the limit is refused, not cut, and the event stays approved with nothing written or mailed',
	static function () use ( $new, $note_posted ) {
		$before = store_state();
		return array( press( 'handle_cancel', decision( $new, $note_posted . '!' ), MANAGER ), store_state() === $before, meta( $new, '_wpcpm_sevent_state' ) );
	},
	array( array( 'sevent-note-long', $admin_card ), true, 'approved' )
);
ck(
	'a held lock is said as such, and nothing is written or mailed',
	static function () use ( $new ) {
		$before = store_state();
		add_option( 'wpcpm_sevent_lock_' . PARTNER, $GLOBALS['now'] );
		$left = press( 'handle_cancel', decision( $new, 'The host is ill.' ), MANAGER );
		delete_option( 'wpcpm_sevent_lock_' . PARTNER );
		return array( $left, store_state() === $before );
	},
	array( array( 'sevent-busy', $admin_card ), true )
);

$GLOBALS['sent'] = array();
$cancel_note     = 'The host is ill; we will plan another date with you.';
$cancel_left     = attempt( static function () use ( $new, $cancel_note ) { return press( 'handle_cancel', decision( $new, $cancel_note ), MANAGER ); } );

ck( 'the manager cancels the Partner\'s approved event with a note: sevent-manager-canceled, back at the card', $cancel_left, array( 'sevent-manager-canceled', $admin_card ) );
ck(
	'the store canceled it and wrote its row on the manager ground, naming the manager; the people on it stay on the record',
	static function () use ( $new ) {
		$last = last_row();
		return array( meta( $new, '_wpcpm_sevent_state' ), $last['kind'], $last['ground'], $last['actor'], WPCPM_Sponsor_Event::joiners( $new ) );
	},
	array( 'cancelled', 'sponsor_event_cancelled', 'manager', MANAGER, array( JOINER, MANAGER_TWO, JOINER_TWO ) )
);

/** The mails the cancel sent, to the sponsor's members and to the people on the event. */
$by_address = static function () {
	$out = array();
	foreach ( $GLOBALS['sent'] as $mail ) {
		$out[ $mail['to'] ][] = $mail;
	}
	return $out;
};

ck(
	'everyone is mailed once: the Partner\'s two live members and the three people on the event, and nobody else',
	static function () use ( $by_address ) {
		$counts = array_map( 'count', $by_address() );
		ksort( $counts );
		return $counts;
	},
	array( 'bea.privacywalk@people.example' => 1, 'mo.manager@people.example' => 1, 'pat.partnerrep@people.example' => 1, 'pia.partnerrep@people.example' => 1, 'zelda.privacywalk@people.example' => 1 )
);
ck(
	'a member reads the note, on their own clock, how many of the people on it were told, and that the calendar file takes it off their calendar, with the manager to reply to',
	static function () use ( $by_address ) {
		$mail = $by_address()['pat.partnerrep@people.example'][0];
		return array( $mail['context'], $mail['subject'], $mail['body'], $mail['headers'] );
	},
	array(
		'event-canceled-with-note',
		'[WordPress Education] Your event is canceled: Partners "Q&A" in C:\\talks',
		"An Administrator canceled your company's event, with this note:\n\n"
			. "The host is ill; we will plan another date with you.\n\n"
			. "Title: Partners \"Q&A\" in C:\\talks\nWhen: November 10, 2026 2:00 pm to 3:30 pm (America / New York)\n\n"
			. "The 3 people who joined it have been told.\n\n"
			. 'The calendar file attached takes it off your calendar.',
		array( 'Reply-To: Mira Manager <mira.manager@people.example>' ),
	)
);
ck(
	'a person on the event reads that it is canceled, whose it was and when, on their own clock, and the calendar file, and never the note',
	static function () use ( $by_address ) {
		$mail = $by_address()['zelda.privacywalk@people.example'][0];
		return array( $mail['context'], $mail['subject'], $mail['body'], $mail['headers'] );
	},
	array(
		'event-canceled',
		'[WordPress Education] Event canceled: Partners "Q&A" in C:\\talks',
		"An event you joined is canceled.\n\n"
			. "Title: Partners \"Q&A\" in C:\\talks\nSponsor: Partner Example\nWhen: November 10, 2026 7:00 pm to 8:30 pm (Europe / Lisbon)\n\n"
			. 'The calendar file attached takes it off your calendar.',
		array(),
	)
);
ck(
	'the others read it on theirs: the program manager on the site\'s clock, the student in Sao Paulo on hers',
	static function () use ( $by_address ) {
		$all = $by_address();
		return array(
			1 === preg_match( '/^When: November 10, 2026 8:00 pm to 9:30 pm \(Europe \/ Warsaw\)$/m', $all['mo.manager@people.example'][0]['body'] ),
			1 === preg_match( '/^When: November 10, 2026 4:00 pm to 5:30 pm \(America \/ Sao Paulo\)$/m', $all['bea.privacywalk@people.example'][0]['body'] ),
		);
	},
	array( true, true )
);
ck(
	'every one of the five carries the calendar file that cancels the event, under its identity, at the version the cancel raised, naming the person it went to as its attendee and nobody else',
	static function () use ( $new ) {
		$out = array();
		foreach ( $GLOBALS['sent'] as $mail ) {
			$lines = isset( $mail['files'][0] ) ? ics_lines( $mail['files'][0]['ics'] ) : array();
			$out[] = array( count( $mail['files'] ), in_array( 'METHOD:CANCEL', $lines, true ), in_array( 'STATUS:CANCELLED', $lines, true ), in_array( 'UID:wpcpm-call-' . $new . '@site.example', $lines, true ), in_array( 'SEQUENCE:' . meta( $new, '_wpcpm_sevent_revision' ), $lines, true ), people_attending( $lines ) === array( attendee_for( $mail['to'] ) ) );
		}
		return array( count( $out ), array_values( array_unique( array_map( 'serialize', $out ) ) ) === array( serialize( array( 1, true, true, true, true, true ) ) ), meta( $new, '_wpcpm_sevent_revision' ) );
	},
	array( 5, true, '2' )
);
ck(
	'no name of a person on the event reaches a mail to the sponsor, and no member\'s name or address, and no note, reaches a person on the event',
	static function () use ( $by_address, $cancel_note ) {
		$all     = $by_address();
		$members = array_merge( $all['pat.partnerrep@people.example'], $all['pia.partnerrep@people.example'] );
		$joiners = array_merge( $all['zelda.privacywalk@people.example'], $all['bea.privacywalk@people.example'], $all['mo.manager@people.example'] );
		$leaks   = array();
		foreach ( $members as $mail ) {
			foreach ( array( 'Privacywalk', 'Mo Manager', 'privacywalk@', 'mo.manager@' ) as $needle ) {
				if ( false !== stripos( serialize( $mail ), $needle ) ) { $leaks[] = 'member mail: ' . $needle; }
			}
		}
		foreach ( $joiners as $mail ) {
			foreach ( array( 'Partnerrep', 'partnerrep@', 'Mira Manager', 'mira.manager@', $cancel_note, 'another date' ) as $needle ) {
				if ( false !== stripos( $mail['subject'] . $mail['body'] . serialize( $mail['headers'] ), $needle ) ) { $leaks[] = 'joiner mail: ' . $needle; }
			}
		}
		return $leaks;
	},
	array()
);
// The Champion plans once more, and a manager cancels the waiting event with a form made by hand: the
// card offers Cancel on approved events alone, and the store's cancel takes a waiting one too.
$third = attempt( static function () { return WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-05 10:00' ), array( 'title' => 'Champion webinar, third', 'zone' => 'Europe/Warsaw' ) ), CHAMPION_REP, 'member' ); } );

ck(
	'a waiting event canceled that way: its member is mailed the note and no calendar file, since no approval sent one, and nobody on it is counted',
	static function () use ( $third ) {
		$GLOBALS['sent'] = array();
		$left            = press( 'handle_cancel', decision( $third, 'Not this one.' ), MANAGER );
		return array( $left[0], meta( $third, '_wpcpm_sevent_state' ), sent_to(), $GLOBALS['sent'][0]['files'], strpos( $GLOBALS['sent'][0]['body'], 'calendar' ), strpos( $GLOBALS['sent'][0]['body'], 'joined' ) );
	},
	array( 'sevent-manager-canceled', 'cancelled', array( 'chris.championrep@people.example' ), array(), false, false )
);
// The Champion plans once more. This time the site cannot write a calendar file, and one of the two
// people on the event is an account that is gone.
$fourth = attempt( static function () { return WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-07 10:00' ), array( 'title' => 'Champion webinar, fourth', 'zone' => 'Europe/Warsaw' ) ), CHAMPION_REP, 'member' ); } );

ck(
	'with no calendar file to be had, the approval says nothing of one and attaches none',
	static function () use ( $fourth ) {
		$GLOBALS['sent']    = array();
		$GLOBALS['no_temp'] = true;
		$left               = press( 'handle_approve', decision( $fourth ), MANAGER );
		$GLOBALS['no_temp'] = false;
		return array( $left[0], sent_to(), $GLOBALS['sent'][0]['files'], strtok( $GLOBALS['sent'][0]['body'], "\n" ) );
	},
	array( 'sevent-approved', array( 'chris.championrep@people.example' ), array(), 'An Administrator approved your company\'s event.' )
);

attempt(
	static function () use ( $fourth ) {
		add_post_meta( $fourth, '_wpcpm_sevent_person', JOINER );
		add_post_meta( $fourth, '_wpcpm_sevent_person', 777 );
	}
);

ck(
	'an account that is gone is counted on the event and named nowhere: no empty name among who joined',
	static function () use ( $fourth ) {
		foreach ( WPCPM_Sponsor_Event_Queue::data()['upcoming'] as $row ) {
			if ( $fourth === $row['id'] ) {
				return array( $row['joined'], $row['people'] );
			}
		}
		return 'not listed';
	},
	array( 2, array( 'Zelda Privacywalk' ) )
);
ck(
	'canceled with no calendar file to be had: the one person told reads no word of a file, the gone account is mailed nothing, and the member reads no count of who was told: the gone account is on the event too and was told nothing',
	static function () use ( $fourth ) {
		$GLOBALS['sent']    = array();
		$GLOBALS['no_temp'] = true;
		$left               = press( 'handle_cancel', decision( $fourth, 'The room fell through.' ), MANAGER );
		$GLOBALS['no_temp'] = false;
		$out                = array( $left[0], sent_to() );
		foreach ( $GLOBALS['sent'] as $mail ) {
			$out[] = array( $mail['files'], false !== strpos( $mail['body'], 'calendar file' ) );
		}
		$out[] = strpos( $GLOBALS['sent'][1]['body'], 'joined it' );
		return $out;
	},
	array( 'sevent-manager-canceled', array( 'zelda.privacywalk@people.example', 'chris.championrep@people.example' ), array( array(), false ), array( array(), false ), false )
);

// The Champion plans once more, and two people are on the event this time: the mail server refuses
// the second one's address.
$fifth = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-09 10:00' ), array( 'title' => 'Champion webinar, fifth', 'zone' => 'Europe/Warsaw' ) ), CHAMPION_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		add_post_meta( $id, '_wpcpm_sevent_person', JOINER );
		add_post_meta( $id, '_wpcpm_sevent_person', JOINER_TWO );
		return $id;
	}
);

ck(
	'one of the two sends refused: the store told one of the two, so the member reads no count, since not everybody who joined was told',
	static function () use ( $fifth ) {
		$GLOBALS['sent']      = array();
		$GLOBALS['refuse_to'] = array( 'bea.privacywalk@people.example' );
		$left                 = press( 'handle_cancel', decision( $fifth, 'The host cannot make it.' ), MANAGER );
		$GLOBALS['refuse_to'] = array();
		$member               = end( $GLOBALS['sent'] );
		return array( is_int( $fifth ), $left[0], meta( $fifth, '_wpcpm_sevent_state' ), sent_to(), strpos( $member['body'], 'joined it' ), false !== strpos( $member['body'], 'The calendar file attached takes it off your calendar.' ) );
	},
	array( true, 'sevent-manager-canceled', 'cancelled', array( 'zelda.privacywalk@people.example', 'chris.championrep@people.example' ), false, true )
);

// Once more, with the one person on the event reached.
$sixth = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-11 10:00' ), array( 'title' => 'Champion webinar, sixth', 'zone' => 'Europe/Warsaw' ) ), CHAMPION_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		add_post_meta( $id, '_wpcpm_sevent_person', JOINER );
		return $id;
	}
);

ck(
	'the one person on it told: the member reads that the one person who joined it has been told, in the singular',
	static function () use ( $sixth ) {
		$GLOBALS['sent'] = array();
		$left            = press( 'handle_cancel', decision( $sixth, 'Moved to the new year.' ), MANAGER );
		$member          = end( $GLOBALS['sent'] );
		return array( is_int( $sixth ), $left[0], sent_to(), false !== strpos( $member['body'], "\n\nThe 1 person who joined it has been told.\n\n" ) );
	},
	array( true, 'sevent-manager-canceled', array( 'zelda.privacywalk@people.example', 'chris.championrep@people.example' ), true )
);

// Once more, with fifty-five people on the event: more than the fifty the store tells in the press.
$crowd   = array();
$seventh = attempt(
	static function () use ( &$crowd ) {
		for ( $i = 1; $i <= 55; $i++ ) {
			a_user( 7000 + $i, 'Crowd Member ' . $i, array( 'wpcpm_student' ) );
			$crowd[] = 7000 + $i;
		}
		$id = WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-13 10:00' ), array( 'title' => 'Champion webinar, seventh', 'zone' => 'Europe/Warsaw', 'places' => 0 ) ), CHAMPION_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		foreach ( $crowd as $person ) { add_post_meta( $id, '_wpcpm_sevent_person', $person ); }
		return $id;
	}
);

ck(
	'fifty-five on it: the store tells fifty in the press and leaves five to the drain on the clock, and the member reads that the 55 people who joined are being told',
	static function () use ( $seventh ) {
		$GLOBALS['sent'] = array();
		$GLOBALS['cron'] = array();
		$left            = press( 'handle_cancel', decision( $seventh, 'The speaker is unwell.' ), MANAGER );
		$member          = end( $GLOBALS['sent'] );
		return array( is_int( $seventh ), $left[0], count( $GLOBALS['sent'] ), $member['to'], false !== strpos( $member['body'], "\n\nThe 55 people who joined are being told.\n\n" ), strpos( $member['body'], 'have been told' ), count( get_post_meta( $seventh, '_wpcpm_sevent_pending', false ) ), array_column( $GLOBALS['cron'], 'hook' ) );
	},
	array( true, 'sevent-manager-canceled', 51, 'chris.championrep@people.example', true, false, 5, array( 'wpcpm_sevent_drain' ) )
);
ck(
	'canceled already, a second cancel is refused as such, and nothing is written or mailed',
	static function () use ( $new ) {
		$before = store_state();
		return array( press( 'handle_cancel', decision( $new, 'Again.' ), MANAGER ), store_state() === $before );
	},
	array( array( 'sevent-was-canceled', $admin_card ), true )
);
ck(
	'the program\'s trail holds no note, no call link and nobody\'s name',
	static function () use ( $return_note, $cancel_note ) {
		$trail = serialize( $GLOBALS['audit'] );
		$leaks = array();
		foreach ( array( 'weekday', 'The host is ill', 'call.example', 'meet.example', 'Privacywalk', 'Partnerrep', 'Championrep' ) as $needle ) {
			if ( false !== strpos( $trail, $needle ) ) { $leaks[] = $needle; }
		}
		return array( count( $GLOBALS['audit'] ) >= 4, $leaks );
	},
	array( true, array() )
);

/* ---- once the Paused sponsor's event has taken place ---------------------------- */

echo "\n=== Once an event's time has passed ===\n";

set_now( utc( '2026-10-21 12:00' ) );

ck(
	'the Paused sponsor\'s event, over now, leaves the card, and a cancel posted anyway is refused, and nothing is written or mailed',
	static function () use ( $soon ) {
		$before = store_state();
		return array( in_array( $soon, row_ids( WPCPM_Sponsor_Event_Queue::data()['upcoming'] ), true ), press( 'handle_cancel', decision( $soon, 'Too late.' ), MANAGER ), store_state() === $before );
	},
	array( false, array( 'sevent-past', $admin_card ), true )
);

/* ---- a sponsor that no longer offers events ---------------------------------- */

echo "\n=== A sponsor that no longer offers events ===\n";

// The Paused sponsor, Approved again now its event has passed, plans once more, and is set to Paused
// before the program has decided.
$paused_planned = attempt(
	static function () {
		$GLOBALS['index'][ PAUSED ]['status'] = 'Approved';
		$id                                   = WPCPM_Sponsor_Event::plan( PAUSED, event_fields( utc( '2026-12-20 09:00' ), array( 'title' => 'Paused planned', 'places' => 0, 'link' => 'https://meet.example/paused-2' ) ), PAUSED_REP, 'member' );
		$GLOBALS['index'][ PAUSED ]['status'] = 'Paused';
		return $id;
	}
);

ck(
	'its waiting event is read as no longer offered, and the card draws it so, with Return alone: no Approve, and no nonce to approve it with',
	static function () use ( $paused_planned ) {
		$GLOBALS['uid'] = MANAGER;
		$row            = null;
		foreach ( WPCPM_Sponsor_Event_Queue::data()['waiting'] as $waiting ) {
			if ( $paused_planned === $waiting['id'] ) {
				$row = $waiting;
			}
		}
		ob_start();
		WPCPM_Sponsor_Event_Queue::render( array( 'waiting' => array( $row ), 'upcoming' => array() ) );
		$html = (string) ob_get_clean();
		return array(
			is_int( $paused_planned ),
			$row['offered'],
			false !== strpos( $html, '<span class="wpcpm-administrator__fact">Open to everyone</span><span class="wpcpm-administrator__fact">No longer offered</span></p>' ),
			false !== strpos( $html, 'name="_wpnonce" value="nonce-wpcpm_sevent_return_' . $paused_planned . '"' ),
			strpos( $html, 'wpcpm_sevent_approve' ),
			false !== strpos( $html, 'your note is emailed to the sponsor&#039;s members. The sponsor no longer holds the tier that includes events.</p>' ),
		);
	},
	array( true, false, true, true, false, true )
);
ck(
	'an approval posted anyway is refused with its own sentence, and nothing is written or mailed',
	static function () use ( $paused_planned ) {
		$before = store_state();
		return array( press( 'handle_approve', decision( $paused_planned ), MANAGER ), store_state() === $before, meta( $paused_planned, '_wpcpm_sevent_state' ) );
	},
	array( array( 'sevent-no-tier', $admin_card ), true, 'planned' )
);
ck(
	'returned, its member is told it is canceled and that the company no longer holds the tier that includes events: no promise to plan again, and no way to a card it does not have',
	static function () use ( $paused_planned ) {
		$GLOBALS['sent'] = array();
		$left            = press( 'handle_return', decision( $paused_planned, 'Please wait for your renewal.' ), MANAGER );
		return array( $left[0], sent_to(), $GLOBALS['sent'][0]['subject'], $GLOBALS['sent'][0]['body'] );
	},
	array(
		'sevent-returned',
		array( 'paula.pausedrep@people.example' ),
		'[WordPress Education] Your event was returned: Paused planned',
		"An Administrator returned your company's event with this note:\n\n"
			. "Please wait for your renewal.\n\n"
			. "Title: Paused planned\nWhen: December 20, 2026 10:00 am to 11:30 am (Europe / Warsaw)\n\n"
			. 'The event is canceled. Your company no longer holds the tier that includes events.',
	)
);

/* ---- what the card draws from what it read --------------------------------------- */

echo "\n=== What the card draws from what it read ===\n";

set_now( utc( '2026-10-06 12:00' ) );

$drawn = attempt(
	static function () {
		$GLOBALS['uid'] = MANAGER;
		ob_start();
		try {
			WPCPM_Sponsor_Event_Queue::render(
				array(
					'waiting'  => array(),
					'upcoming' => array(
						array( 'id' => 555, 'sponsor' => PARTNER, 'sponsor_name' => 'Partner Example', 'title' => 'Drawn', 'description' => 'About it.', 'start' => utc( '2026-11-10 19:00' ), 'end' => utc( '2026-11-10 20:30' ), 'minutes' => 90, 'places' => 0, 'joined' => 2, 'link' => 'https://call.example/x?a=1&b=2', 'people' => array( 'Ann <i>One</i>', 'Bob Two' ), 'offered' => true ),
					),
				)
			);
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}
);

ck(
	'the names reach the card, escaped, in the order they were read, under Who joined; the call link is a link that opens apart',
	static function () use ( $drawn ) {
		return array(
			false !== strpos( (string) $drawn, '<summary>Who joined</summary><ul class="wpcpm-administrator__list"><li>Ann &lt;i&gt;One&lt;/i&gt;</li><li>Bob Two</li></ul>' ),
			false !== strpos( (string) $drawn, 'Call link: <a href="https://call.example/x?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">https://call.example/x?a=1&amp;b=2</a>' ),
		);
	},
	array( true, true )
);
ck( 'and nothing at all is drawn while the launch switch is off', static function () { $GLOBALS['settings']['sponsor_tiers_live'] = false; ob_start(); WPCPM_Sponsor_Event_Queue::render( WPCPM_Sponsor_Event_Queue::data() ); $html = (string) ob_get_clean(); $GLOBALS['settings']['sponsor_tiers_live'] = true; return $html; }, '' );

/* ---- the program's own events ---------------------------------------------------- */

echo "\n=== The program's own events ===\n";

// The program plans events of its own on this card: hosted by the WordPress Credits Program, with no
// sponsor, approved as they are planned. The form is the planner's own, read on the manager's clock,
// here the site's, Warsaw; the one event of a sponsor's still to come is the Paused sponsor's.

/** What the program's plan and change forms post: the planner's fields, the partners session unless one is replaced. */
function program_form( array $over = array() ) {
	return array_merge(
		array(
			'wpcpm_sevent_date'        => '2026-11-20',
			'wpcpm_sevent_time'        => '15:00',
			'wpcpm_sevent_minutes'     => '60',
			'wpcpm_sevent_places'      => '',
			'wpcpm_sevent_title'       => 'The partners session',
			'wpcpm_sevent_description' => "What the program plans with its partners.\nBring questions.",
			'wpcpm_sevent_link'        => 'https://meet.example/partners?pwd=a%2Fb',
		),
		$over
	);
}

/** The card's article about one event, found by its title, from the heading the article opens with, or ''. */
function article_of( $html, $title ) {
	foreach ( preg_split( '#<article class="wpcpm-administrator__item wpcpm-sponsor-event" id="wpcpm-sponsor-event-\d+">#', (string) $html ) as $part ) {
		if ( false !== strpos( $part, '<h4 class="wpcpm-administrator__item-title">' . $title . ' ' ) ) {
			$end = strpos( $part, '</article>' );
			return false === $end ? $part : substr( $part, 0, $end );
		}
	}
	return '';
}

$program_sentence = 'An event of the WordPress Credits Program\'s own, with no sponsor: it is approved as you plan it, and the program\'s students, mentors, Administrators and institution contacts can join it at once, as they join a sponsor\'s.';

ck(
	'the card has a third part after Coming up, Plan an event of the program\'s: the planner\'s form folded under Plan an event inside the decide row every item\'s decisions sit in, with a sentence saying what the program\'s own event is, posting wpcpm_sevent_program_plan under a nonce of its own and back to this card',
	static function () use ( $card_for, $program_sentence ) {
		$html  = $card_for( MANAGER );
		$part  = strpos( $html, '<h4 class="wpcpm-administrator__subtitle">Plan an event of the program&#039;s</h4>' );
		$after = strpos( $html, '<h4 class="wpcpm-administrator__subtitle">Coming up</h4>' );
		return array(
			false !== $part && false !== $after && $part > $after,
			false !== strpos( $html, '<h4 class="wpcpm-administrator__subtitle">Plan an event of the program&#039;s</h4><div class="wpcpm-request__decide wpcpm-sponsor-post__decide"><details class="wpcpm-sponsor-post__return"><summary class="button">Plan an event</summary><p class="wpcpm-administrator__note">' . esc_html( $program_sentence ) . '</p><form class="wpcpm-sponsor-post__form wpcpm-sponsor__form wpcpm-sevent__form" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Planning"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_program_plan" /><input type="hidden" name="action" value="wpcpm_sevent_program_plan" /><input type="hidden" name="wpcpm_return" value="dashboard" /><input type="hidden" name="wpcpm_return_to" value="sponsor-events" /><p class="wpcpm-sponsor__field wpcpm-sevent__row">' ),
			false !== strpos( $html, '<button type="submit" class="button button-primary">Plan the event</button></form></details></div>' ),
		);
	},
	array( true, true, true )
);
ck(
	'its rows are the planner\'s own, on the manager\'s clock: the day from the site\'s today with no last day, no term binding the program; the start time with the clock named; the length; the places from one; the title, the description and the call link, each once',
	static function () use ( $card_for ) {
		$html = $card_for( MANAGER );
		$form = (string) strstr( (string) strstr( $html, 'nonce-wpcpm_sevent_program_plan' ), '</form>', true );
		$out  = array(
			false !== strpos( $form, '<label for="wpcpm-sevent-program-new-date">Date</label><input type="date" id="wpcpm-sevent-program-new-date" name="wpcpm_sevent_date" value="" min="2026-10-06" required /></p>' ),
			false !== strpos( $form, 'In your time zone, Europe / Warsaw. Everyone who joins sees it in theirs.' ),
			false !== strpos( $form, 'name="wpcpm_sevent_minutes" value="60" min="15" max="480" step="5" required' ),
			false !== strpos( $form, 'name="wpcpm_sevent_places" value="" min="1" max="500" step="1"' ),
		);
		foreach ( array( 'date', 'time', 'minutes', 'places', 'title', 'description', 'link' ) as $field ) {
			$out[] = substr_count( $form, 'name="wpcpm_sevent_' . $field . '"' );
		}
		return $out;
	},
	array( true, true, true, true, 1, 1, 1, 1, 1, 1, 1 )
);
ck(
	'a stale page is stopped on the plan\'s own nonce before the capability is asked or anything is read, a stranger\'s too; without the capability, the program\'s 403; with the switch off, the card\'s sentence; and nothing is written or mailed',
	static function () use ( $admin_card ) {
		$before              = store_state();
		$GLOBALS['nonces']   = array();
		$GLOBALS['nonce_ok'] = false;
		$stale               = press( 'handle_program_plan', program_form(), STRANGER );
		$GLOBALS['nonce_ok'] = true;
		$refused             = array( press( 'handle_program_plan', program_form(), PARTNER_REP ), $GLOBALS['died_code'] );
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$off                                       = press( 'handle_program_plan', program_form(), MANAGER );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $stale, $GLOBALS['nonces'], $refused, $off, store_state() === $before );
	},
	array(
		array( 'died', 'The link you followed has expired.' ),
		array( 'wpcpm_sevent_program_plan', 'wpcpm_sevent_program_plan', 'wpcpm_sevent_program_plan' ),
		array( array( 'died', 'You do not have permission to manage the program.' ), 403 ),
		array( 'sevent-off', $admin_card ),
		true,
	)
);
ck(
	'a form the store cannot take, a start that has passed and the program\'s lock held are each said in the card\'s words, and nothing is written or mailed',
	static function () {
		$before = store_state();
		$out    = array( press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_title' => '' ) ), MANAGER )[0], press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_date' => '2026-10-01' ) ), MANAGER )[0] );
		add_option( 'wpcpm_sevent_lock_program', $GLOBALS['now'] );
		$out[] = press( 'handle_program_plan', program_form(), MANAGER )[0];
		delete_option( 'wpcpm_sevent_lock_program' );
		return array( $out, store_state() === $before );
	},
	array( array( 'sevent-program-fields', 'sevent-program-in-past', 'sevent-program-busy' ), true )
);

$GLOBALS['sent'] = array();
$program_left    = attempt( static function () { return press( 'handle_program_plan', program_form(), MANAGER ); } );
$program_event   = attempt(
	static function () {
		$found = array();
		foreach ( $GLOBALS['posts'] as $post ) {
			if ( 'wpcpm_sponsor_event' === $post->post_type && 'program' === meta( $post->ID, '_wpcpm_sevent_kind' ) ) { $found[] = (int) $post->ID; }
		}
		return 1 === count( $found ) ? $found[0] : null;
	}
);

ck( 'the manager plans the program\'s event: sevent-program-planned, back at the card', $program_left, array( 'sevent-program-planned', $admin_card ) );
ck(
	'the store holds it: the program\'s kind, no sponsor, approved as it was planned, the day and the time read on the manager\'s clock, and the planner\'s fields as typed, the call link\'s query kept',
	static function () use ( $program_event ) {
		$post = get_post( $program_event );
		return array( is_int( $program_event ), meta( $program_event, '_wpcpm_sevent_kind' ), array_key_exists( '_wpcpm_sevent_sponsor', $GLOBALS['pmeta'][ $program_event ] ), meta( $program_event, '_wpcpm_sevent_state' ), meta( $program_event, '_wpcpm_sevent_start' ), meta( $program_event, '_wpcpm_sevent_zone' ), $post->post_title, $post->post_content, meta( $program_event, '_wpcpm_sevent_link' ), meta( $program_event, '_wpcpm_sevent_places' ) );
	},
	array( true, 'program', false, 'approved', (string) utc( '2026-11-20 14:00' ), 'Europe/Warsaw', 'The partners session', "What the program plans with its partners.\nBring questions.", 'https://meet.example/partners?pwd=a%2Fb', '0' )
);
ck(
	'one row on the trail, the program\'s own, on the manager ground, naming no sponsor; and nobody is mailed, the program having no members and no contact to tell',
	static function () { $last = last_row(); return array( $last['kind'], $last['ground'], $last['actor'], array_key_exists( 'sponsor', $last ), count( $GLOBALS['program_audit'] ), sent_to() ); },
	array( 'sponsor_event_planned', 'manager', MANAGER, false, 1, array() )
);

attempt( static function () use ( $program_event ) { add_post_meta( $program_event, '_wpcpm_sevent_person', JOINER ); } );

ck(
	'Coming up holds it, the soonest first beside the Paused sponsor\'s: named for the WordPress Credits Program where a sponsor\'s event names its sponsor, no sponsor\'s key, and offered, as the program\'s own always is',
	static function () use ( $soon, $program_event ) {
		$upcoming = WPCPM_Sponsor_Event_Queue::data()['upcoming'];
		foreach ( $upcoming as $row ) {
			if ( $program_event === $row['id'] ) {
				return array( row_ids( $upcoming ), $row['sponsor'], $row['sponsor_name'], $row['offered'], $row['program'], $row['joined'], $row['people'] );
			}
		}
		return 'not listed';
	},
	array( array( $soon, $program_event ), '', 'the WordPress Credits Program', true, true, 1, array( 'Zelda Privacywalk' ) )
);
ck(
	'the card draws it with the program\'s name where a sponsor\'s is, its facts, its call link and who joined, the manager\'s own Join, then Change the event and Cancel the event, each folded and asking no note; nothing says it is no longer offered',
	static function () use ( $card_for, $program_event ) {
		$item = article_of( $card_for( MANAGER ), 'The partners session' );
		return array(
			0 === strpos( $item, '<h4 class="wpcpm-administrator__item-title">The partners session <span class="wpcpm-administrator__kind">the WordPress Credits Program</span></h4><p class="wpcpm-administrator__facts"><span class="wpcpm-administrator__fact">November 20, 2026 3:00 pm to 4:00 pm, 60 minutes, in your time zone (Europe / Warsaw)</span><span class="wpcpm-administrator__fact">Open to everyone</span><span class="wpcpm-administrator__fact">1 person has joined</span></p>' ),
			false !== strpos( $item, 'Call link: <a href="https://meet.example/partners?pwd=a%2Fb"' ),
			false !== strpos( $item, '<li>Zelda Privacywalk</li>' ),
			false !== strpos( $item, 'nonce-wpcpm_sevent_join_' . $program_event ),
			false !== strpos( $item, '<details class="wpcpm-sponsor-post__return"><summary class="button">Change the event<span class="screen-reader-text"> The partners session</span></summary>' ),
			false !== strpos( $item, '<details class="wpcpm-sponsor-post__return"><summary class="button">Cancel the event<span class="screen-reader-text"> The partners session</span></summary>' ),
			strpos( $item, 'nonce-wpcpm_sevent_join_' ) < strpos( $item, 'Change the event' ) && strpos( $item, 'Change the event' ) < strpos( $item, 'Cancel the event' ),
			array( strpos( $item, 'Cancel with a note' ), strpos( $item, 'wpcpm_sevent_note' ), strpos( $item, 'No longer offered' ), strpos( $item, 'wpcpm_sevent_manager_cancel' ) ),
		);
	},
	array( true, true, true, true, true, true, true, array( false, false, false, false ) )
);
ck(
	'Change the event holds the planner\'s form filled from the event on the manager\'s clock, the places no fewer than the people on it, posting wpcpm_sevent_program_change under a nonce keyed to the event',
	static function () use ( $card_for, $program_event ) {
		$item = article_of( $card_for( MANAGER ), 'The partners session' );
		return array(
			false !== strpos( $item, '<form class="wpcpm-sponsor-post__form wpcpm-sponsor__form wpcpm-sevent__form" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Saving"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_program_change_' . $program_event . '" /><input type="hidden" name="action" value="wpcpm_sevent_program_change" /><input type="hidden" name="wpcpm_sevent_id" value="' . $program_event . '" /><input type="hidden" name="wpcpm_return" value="dashboard" /><input type="hidden" name="wpcpm_return_to" value="sponsor-events" />' ),
			false !== strpos( $item, 'id="wpcpm-sevent-program-' . $program_event . '-date" name="wpcpm_sevent_date" value="2026-11-20" min="2026-10-06" required' ),
			false !== strpos( $item, 'name="wpcpm_sevent_time" value="15:00"' ),
			false !== strpos( $item, 'name="wpcpm_sevent_places" value="" min="1"' ),
			false !== strpos( $item, 'name="wpcpm_sevent_title" value="The partners session"' ),
			false !== strpos( $item, 'name="wpcpm_sevent_link" value="https://meet.example/partners?pwd=a%2Fb"' ),
			false !== strpos( $item, '<button type="submit" class="button button-primary">Save the changes</button></form></details>' ),
		);
	},
	array( true, true, true, true, true, true, true )
);
ck(
	'Cancel the event says what it does and asks before it posts wpcpm_sevent_program_cancel under a nonce keyed to the event',
	static function () use ( $card_for, $program_event ) {
		$item = article_of( $card_for( MANAGER ), 'The partners session' );
		return false !== strpos( $item, '<summary class="button">Cancel the event<span class="screen-reader-text"> The partners session</span></summary><p class="wpcpm-administrator__note">The event is canceled for everyone, and everyone who joined is told, with the calendar file that takes it off their calendar.</p><form class="wpcpm-sponsor-post__form" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Canceling"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_program_cancel_' . $program_event . '" /><input type="hidden" name="action" value="wpcpm_sevent_program_cancel" /><input type="hidden" name="wpcpm_sevent_id" value="' . $program_event . '" /><input type="hidden" name="wpcpm_return" value="dashboard" /><input type="hidden" name="wpcpm_return_to" value="sponsor-events" /><button type="submit" class="button" data-wpcpm-confirm="Cancel this event for everyone? This cannot be undone.">Cancel the event</button></form></details>' );
	},
	true
);
/**
 * How many elements an XPath query finds in a piece of the card, read by libxml's HTML parser as a
 * browser reads the markup into a tree: which of the card's rules reach a control depends on the
 * elements around it, which the text alone does not show.
 *
 * @param string $html  The markup.
 * @param string $query An XPath 1.0 query.
 * @return int How many it finds, or -1 for a query that is not one.
 */
function card_count( $html, $query ) {
	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$found = ( new DOMXPath( $doc ) )->query( $query );
	return false === $found ? -1 : (int) $found->length;
}

/** An XPath test that an element carries a class among its classes. */
function has_class( $name ) { return "contains(concat(' ', normalize-space(@class), ' '), ' " . $name . " ')"; }

ck(
	'the plan part sits in a decide row, the one every item\'s decisions sit in: its fold\'s summary, its submit and its description box are the row\'s own, as the Change form\'s box is, so the card\'s rules for a decision\'s fold, button and box reach them; both of the program\'s forms carry the planner\'s form classes beside the card\'s; and no button on the card, nor a fold whose summary is a button, is outside a decide row (the people\'s fold, Who joined, is a disclosure of its own)',
	static function () use ( $card_for ) {
		$html = $card_for( MANAGER );
		$row  = 'div[' . has_class( 'wpcpm-request__decide' ) . ' and ' . has_class( 'wpcpm-sponsor-post__decide' ) . ']';
		$fold = 'details[' . has_class( 'wpcpm-sponsor-post__return' ) . ']';
		return array(
			card_count( $html, "//{$row}/{$fold}/summary[" . has_class( 'button' ) . " and normalize-space(.) = 'Plan an event']" ),
			card_count( $html, "//{$row}/{$fold}/form[input[@name = 'action' and @value = 'wpcpm_sevent_program_plan']]/button[@type = 'submit' and " . has_class( 'button-primary' ) . " and normalize-space(.) = 'Plan the event']" ),
			card_count( $html, "//{$row}//form[input[@name = 'action' and @value = 'wpcpm_sevent_program_plan']]//textarea[@name = 'wpcpm_sevent_description']" ),
			card_count( $html, "//{$row}//form[input[@name = 'action' and @value = 'wpcpm_sevent_program_change']]//textarea[@name = 'wpcpm_sevent_description']" ),
			card_count( $html, '//form[' . has_class( 'wpcpm-sponsor-post__form' ) . ' and ' . has_class( 'wpcpm-sponsor__form' ) . ' and ' . has_class( 'wpcpm-sevent__form' ) . ']' ),
			card_count( $html, '//summary[' . has_class( 'button' ) . " and not(ancestor::{$row})] | //button[not(ancestor::{$row})]" ),
		);
	},
	array( 1, 1, 1, 1, 2, 0 )
);
ck(
	'a sponsor\'s event keeps what it had: Cancel with a note, and no Change or Cancel of the program\'s',
	static function () use ( $card_for, $soon ) {
		$item = article_of( $card_for( MANAGER ), 'Paused workshop' );
		return array( '' !== $item, false !== strpos( $item, 'nonce-wpcpm_sevent_manager_cancel_' . $soon ), strpos( $item, 'wpcpm_sevent_program_' ), strpos( $item, 'the WordPress Credits Program' ) );
	},
	array( true, true, false, false )
);
ck(
	'a change is stopped on its nonce keyed to the event before anything else, a stranger\'s too; then the capability, the switch and an event; and a sponsor\'s event, whose nonce the card never prints, is refused: nothing written or mailed',
	static function () use ( $program_event, $soon, $admin_card ) {
		$before              = store_state();
		$GLOBALS['nonces']   = array();
		$GLOBALS['nonce_ok'] = false;
		$stale               = press( 'handle_program_change', array_merge( program_form(), decision( $program_event ) ), STRANGER );
		$GLOBALS['nonce_ok'] = true;
		$refused             = press( 'handle_program_change', array_merge( program_form(), decision( $program_event ) ), PARTNER_REP );
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$off                                       = press( 'handle_program_change', array_merge( program_form(), decision( $program_event ) ), MANAGER );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		$gone                                      = press( 'handle_program_change', array_merge( program_form(), decision( 99999 ) ), MANAGER );
		$sponsors                                  = press( 'handle_program_change', array_merge( program_form(), decision( $soon ) ), MANAGER );
		return array( $stale, $GLOBALS['nonces'][0], $refused, $off, $gone, $sponsors, store_state() === $before );
	},
	array( array( 'died', 'The link you followed has expired.' ), 'wpcpm_sevent_program_change_' . $program_event, array( 'died', 'You do not have permission to manage the program.' ), array( 'sevent-off', $admin_card ), array( 'sevent-gone', $admin_card ), array( 'sevent-program-not-its', $admin_card ), true )
);

$GLOBALS['sent'] = array();

ck(
	'the manager moves it an hour: sevent-program-changed, back at the card; the store keeps it approved and tells the person on it the new time, the program named as its host, on their own clock; nobody else is mailed',
	static function () use ( $program_event, $admin_card ) {
		$left = press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_time' => '16:00' ) ), decision( $program_event ) ), MANAGER );
		$mail = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array( 'subject' => '', 'body' => '', 'files' => array() );
		return array( $left, meta( $program_event, '_wpcpm_sevent_state' ), meta( $program_event, '_wpcpm_sevent_start' ), sent_to(), $mail['subject'], $mail['body'], count( $mail['files'] ), last_row()['kind'], array_key_exists( 'sponsor', last_row() ) );
	},
	array(
		array( 'sevent-program-changed', $admin_card ),
		'approved',
		(string) utc( '2026-11-20 15:00' ),
		array( 'zelda.privacywalk@people.example' ),
		'[WordPress Education] Event changed: The partners session',
		"An event you joined has changed. Here is what it is now:\n\n"
			. "Title: The partners session\nHosted by the WordPress Credits Program\nWhen: November 20, 2026 3:00 pm to 4:00 pm (Europe / Lisbon)\nLength: 60 minutes\nJoin the call: https://meet.example/partners?pwd=a%2Fb\n\n"
			. 'The calendar file attached replaces the one you had.',
		1,
		'sponsor_event_changed',
		false,
	)
);

attempt( static function () use ( $program_event ) { add_post_meta( $program_event, '_wpcpm_sevent_person', JOINER_TWO ); } );

ck(
	'the same form again changes nothing and says so; a form the store cannot take, a start that has passed, fewer places than the two on it and the program\'s lock held are refused in the card\'s words; nothing written or mailed',
	static function () use ( $program_event ) {
		$before = store_state();
		$out    = array(
			press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_time' => '16:00' ) ), decision( $program_event ) ), MANAGER )[0],
			press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_link' => 'ftp://files.example/x' ) ), decision( $program_event ) ), MANAGER )[0],
			press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_date' => '2026-10-01' ) ), decision( $program_event ) ), MANAGER )[0],
			press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_time' => '16:00', 'wpcpm_sevent_places' => '1' ) ), decision( $program_event ) ), MANAGER )[0],
		);
		add_option( 'wpcpm_sevent_lock_program', $GLOBALS['now'] );
		$out[] = press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_time' => '17:00' ) ), decision( $program_event ) ), MANAGER )[0];
		delete_option( 'wpcpm_sevent_lock_program' );
		return array( $out, store_state() === $before );
	},
	array( array( 'sevent-program-unchanged', 'sevent-program-fields', 'sevent-program-in-past', 'sevent-program-shrink', 'sevent-program-busy' ), true )
);
ck(
	'a cancel is stopped on its nonce keyed to the event first; then the capability, the switch and an event; and a sponsor\'s event is refused: nothing written or mailed',
	static function () use ( $program_event, $soon, $admin_card ) {
		$before              = store_state();
		$GLOBALS['nonces']   = array();
		$GLOBALS['nonce_ok'] = false;
		$stale               = press( 'handle_program_cancel', decision( $program_event ), STRANGER );
		$GLOBALS['nonce_ok'] = true;
		$refused             = press( 'handle_program_cancel', decision( $program_event ), PARTNER_REP );
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$off                                       = press( 'handle_program_cancel', decision( $program_event ), MANAGER );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		$gone                                      = press( 'handle_program_cancel', decision( 99999 ), MANAGER );
		$sponsors                                  = press( 'handle_program_cancel', decision( $soon ), MANAGER );
		return array( $stale, $GLOBALS['nonces'][0], $refused, $off, $gone, $sponsors, store_state() === $before, meta( $soon, '_wpcpm_sevent_state' ) );
	},
	array( array( 'died', 'The link you followed has expired.' ), 'wpcpm_sevent_program_cancel_' . $program_event, array( 'died', 'You do not have permission to manage the program.' ), array( 'sevent-off', $admin_card ), array( 'sevent-gone', $admin_card ), array( 'sevent-program-not-its', $admin_card ), true, 'approved' )
);
ck(
	'the three decisions on a sponsor\'s event, pressed on the program\'s own by a hand-made request, keep the order every press keeps, the nonce keyed to the event, the capability and the switch, and then are each refused as the program\'s own, whose change and cancel are its own presses: nothing written or mailed, and it stays approved with the two people on it',
	static function () use ( $program_event, $admin_card ) {
		$before              = store_state();
		$GLOBALS['nonces']   = array();
		$GLOBALS['nonce_ok'] = false;
		$stale               = press( 'handle_cancel', decision( $program_event, 'A note.' ), STRANGER );
		$GLOBALS['nonce_ok'] = true;
		$refused             = press( 'handle_cancel', decision( $program_event, 'A note.' ), PARTNER_REP );
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$off                                       = press( 'handle_cancel', decision( $program_event, 'A note.' ), MANAGER );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		$own                                       = array();
		foreach ( array( 'handle_approve', 'handle_return', 'handle_cancel' ) as $method ) {
			$own[] = press( $method, decision( $program_event, 'A note.' ), MANAGER );
		}
		return array( $stale, $GLOBALS['nonces'][0], $refused, $off, $own, store_state() === $before, meta( $program_event, '_wpcpm_sevent_state' ), count( WPCPM_Sponsor_Event::joiners( $program_event ) ) );
	},
	array(
		array( 'died', 'The link you followed has expired.' ),
		'wpcpm_sevent_manager_cancel_' . $program_event,
		array( 'died', 'You do not have permission to manage the program.' ),
		array( 'sevent-off', $admin_card ),
		array( array( 'sevent-program-own', $admin_card ), array( 'sevent-program-own', $admin_card ), array( 'sevent-program-own', $admin_card ) ),
		true,
		'approved',
		2,
	)
);

$GLOBALS['sent'] = array();

ck(
	'the manager cancels it: back at the card, the store cancels it and tells both people on it, each with the file that takes it off their calendar, the mail naming the program as the host; no member is mailed, and the row is the program\'s own',
	static function () use ( $program_event, $admin_card ) {
		$left = press( 'handle_program_cancel', decision( $program_event ), MANAGER );
		$mail = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array( 'body' => '', 'files' => array() );
		return array( $left, meta( $program_event, '_wpcpm_sevent_state' ), sent_to(), $mail['body'], count( $mail['files'] ), last_row()['kind'], array_key_exists( 'sponsor', last_row() ), count( $GLOBALS['program_audit'] ) );
	},
	array(
		array( 'sevent-manager-canceled', $admin_card ),
		'cancelled',
		array( 'zelda.privacywalk@people.example', 'bea.privacywalk@people.example' ),
		"An event you joined is canceled.\n\nTitle: The partners session\nHosted by the WordPress Credits Program\nWhen: November 20, 2026 3:00 pm to 4:00 pm (Europe / Lisbon)\n\nThe calendar file attached takes it off your calendar.",
		1,
		'sponsor_event_cancelled',
		false,
		3,
	)
);
ck(
	'canceled already, a second cancel and a change are refused as such, and nothing is written or mailed; and it leaves Coming up',
	static function () use ( $program_event, $admin_card ) {
		$before = store_state();
		$out    = array( press( 'handle_program_cancel', decision( $program_event ), MANAGER ), press( 'handle_program_change', array_merge( program_form(), decision( $program_event ) ), MANAGER ), store_state() === $before, in_array( $program_event, row_ids( WPCPM_Sponsor_Event_Queue::data()['upcoming'] ), true ) );
		return $out;
	},
	array( array( 'sevent-was-canceled', $admin_card ), array( 'sevent-was-canceled', $admin_card ), true, false )
);

/* ---- the program's refused forms come back with what was typed ------------------ */

echo "\n=== The program's refused forms come back with what was typed ===\n";

// The program's plan and change forms are the planner's rows, and a refusal the manager puts right
// by pressing again brings them back with what was typed, through the planner's own keeper and
// reader: kept for the manager who pressed, with no sponsor, the program's own, and the event a
// change was for, and read once by the form it came from. Two events of the program's own are
// planned for it, the sooner drawn first under Coming up.

/** What a manager types into the program's forms, the length one the form refuses: markup in the link; any value replaced. */
function program_typed( array $over = array() ) {
	return program_form(
		array_merge(
			array(
				'wpcpm_sevent_date'        => '2026-11-30',
				'wpcpm_sevent_time'        => '17:15',
				'wpcpm_sevent_minutes'     => '5',
				'wpcpm_sevent_places'      => '12',
				'wpcpm_sevent_title'       => 'Typed "Q&A" in C:\\drafts',
				'wpcpm_sevent_description' => "Typed & \"kept\".\nSecond line.",
				'wpcpm_sevent_link'        => 'https://meet.example/typed?pwd=a%2Fb&x="><b>bold</b>',
			),
			$over
		)
	);
}

/**
 * What the seven rows of one form are drawn with, read from the markup as printed, escaped; null
 * for a row the markup does not hold.
 *
 * @param string $html   The card's markup.
 * @param string $prefix The form's ids' prefix.
 * @return array
 */
function rows_of( $html, $prefix ) {
	$rows = array();
	foreach ( array( 'date', 'time', 'minutes', 'places', 'title', 'description', 'link' ) as $field ) {
		$pattern         = 'description' === $field
			? '/id="' . preg_quote( $prefix, '/' ) . '-description" name="wpcpm_sevent_description"[^>]*>(.*?)<\/textarea>/s'
			: '/id="' . preg_quote( $prefix . '-' . $field, '/' ) . '" name="wpcpm_sevent_' . $field . '" value="([^"]*)"/';
		$rows[ $field ] = preg_match( $pattern, (string) $html, $found ) ? $found[1] : null;
	}
	return $rows;
}

/** What waits on the planner's channel for one person, as the flash keeps it, or null. */
function kept_for( $uid ) {
	return isset( $GLOBALS['flash'][ (int) $uid ][ WPCPM_Sponsor_Event_Planner::TYPED ] ) ? $GLOBALS['flash'][ (int) $uid ][ WPCPM_Sponsor_Event_Planner::TYPED ] : null;
}

// The title and the description come back exactly as typed, kept under the form's own name; the
// markup prints the line feed of its own a kept description is drawn after, which the parser drops.
$program_typed_rows = array(
	'date'        => '2026-11-30',
	'time'        => '17:15',
	'minutes'     => '5',
	'places'      => '12',
	'title'       => 'Typed &quot;Q&amp;A&quot; in C:\\drafts',
	'description' => "\nTyped &amp; &quot;kept&quot;.\nSecond line.",
	'link'        => 'https://meet.example/typed?pwd=a%2Fb&amp;x=&quot;&gt;&lt;b&gt;bold&lt;/b&gt;',
);
$program_empty_rows = array( 'date' => '', 'time' => '', 'minutes' => '60', 'places' => '', 'title' => '', 'description' => '', 'link' => '' );

ck(
	'the program\'s plan refused for its fields comes back with every one of the seven values as typed, each escaped as a stored value is, the call link as typed with its markup printed as text; another manager is drawn the form as it is drawn today, and so is a reload',
	static function () use ( $card_for, $admin_card, $program_typed_rows, $program_empty_rows ) {
		$left   = press( 'handle_program_plan', program_typed(), MANAGER );
		$other  = rows_of( $card_for( MANAGER_TWO ), 'wpcpm-sevent-program-new' );
		$first  = $card_for( MANAGER );
		$second = rows_of( $card_for( MANAGER ), 'wpcpm-sevent-program-new' );
		return array( $left, $other === $program_empty_rows, rows_of( $first, 'wpcpm-sevent-program-new' ) === $program_typed_rows, substr_count( $first, '"><b>' ), $second === $program_empty_rows );
	},
	array( array( 'sevent-program-fields', $admin_card ), true, true, 0, true )
);

$program_first  = attempt( static function () { return WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2026-11-25 10:00' ), array( 'title' => 'Program first', 'places' => 0, 'link' => 'https://meet.example/first', 'zone' => 'Europe/Warsaw' ) ), MANAGER ); } );
$program_second = attempt( static function () { return WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2026-11-28 10:00' ), array( 'title' => 'Program second', 'places' => 0, 'link' => 'https://meet.example/second', 'zone' => 'Europe/Warsaw' ) ), MANAGER ); } );

ck(
	'a refused change of the later of two program events comes back in that event\'s form alone, kept with the event it was for: the sooner one, drawn before it, and the plan form, drawn after it, hold their own values, every form on the card reading the one value the page took',
	static function () use ( $card_for, $admin_card, $program_first, $program_second, $program_typed_rows, $program_empty_rows ) {
		$left  = press( 'handle_program_change', array_merge( program_typed(), decision( $program_second ) ), MANAGER );
		$kept  = kept_for( MANAGER );
		$html  = $card_for( MANAGER );
		$first = rows_of( $html, 'wpcpm-sevent-program-' . $program_first );
		return array( is_int( $program_first ) && is_int( $program_second ), $left, is_array( $kept ) ? array( $kept['sponsor'], $kept['event'] ) : $kept, array( $first['date'], $first['time'], $first['minutes'], $first['title'], $first['link'] ), rows_of( $html, 'wpcpm-sevent-program-' . $program_second ) === $program_typed_rows, rows_of( $html, 'wpcpm-sevent-program-new' ) === $program_empty_rows );
	},
	array( true, array( 'sevent-program-fields', $admin_card ), array( '', $program_second ), array( '2026-11-25', '11:00', '90', 'Program first', 'https://meet.example/first' ), true, true )
);
ck(
	'a change the store refuses, a start that has passed, comes back too; a change that succeeds keeps nothing and drops what the refusal before it kept, and the event\'s form is drawn from the event',
	static function () use ( $card_for, $admin_card, $program_first ) {
		$late     = press( 'handle_program_change', array_merge( program_typed( array( 'wpcpm_sevent_date' => '2026-10-01', 'wpcpm_sevent_minutes' => '45' ) ), decision( $program_first ) ), MANAGER );
		$drawn    = rows_of( $card_for( MANAGER ), 'wpcpm-sevent-program-' . $program_first );
		press( 'handle_program_change', array_merge( program_typed(), decision( $program_first ) ), MANAGER );
		$kept     = kept_for( MANAGER );
		$changed  = press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_date' => '2026-11-26', 'wpcpm_sevent_time' => '11:00', 'wpcpm_sevent_title' => 'Program first, moved', 'wpcpm_sevent_link' => 'https://meet.example/first' ) ), decision( $program_first ) ), MANAGER );
		$after    = kept_for( MANAGER );
		$redrawn  = rows_of( $card_for( MANAGER ), 'wpcpm-sevent-program-' . $program_first );
		return array( $late, array( $drawn['date'], $drawn['minutes'] ), is_array( $kept ) ? $kept['event'] : $kept, $changed, $after, array( $redrawn['date'], $redrawn['minutes'], $redrawn['title'] ) );
	},
	array( array( 'sevent-program-in-past', $admin_card ), array( '2026-10-01', '45' ), $program_first, array( 'sevent-program-changed', $admin_card ), null, array( '2026-11-26', '60', 'Program first, moved' ) )
);
ck(
	'a plan that succeeds keeps nothing and drops what the refusal before it kept, and the plan form is drawn as it is drawn today',
	static function () use ( $card_for, $admin_card, $program_empty_rows ) {
		press( 'handle_program_plan', program_typed(), MANAGER );
		$kept    = kept_for( MANAGER );
		$planned = press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_date' => '2026-12-04', 'wpcpm_sevent_title' => 'Program third' ) ), MANAGER );
		$after   = kept_for( MANAGER );
		return array( is_array( $kept ) ? array( $kept['sponsor'], $kept['event'] ) : $kept, $planned, $after, rows_of( $card_for( MANAGER ), 'wpcpm-sevent-program-new' ) === $program_empty_rows );
	},
	array( array( '', 0 ), array( 'sevent-program-planned', $admin_card ), null, true )
);
ck(
	'the outcomes that keep what was typed are the ones a manager puts right by pressing again: the form\'s refusal, a start that has passed, fewer places than the people on the event, the program\'s lock held, and a write that failed, each one of the card\'s outcomes; a press stopped before its form is read keeps nothing, the switch off or an event gone',
	static function () use ( $admin_card ) {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		try {
			$off      = press( 'handle_program_plan', program_typed(), MANAGER );
			$kept_off = kept_for( MANAGER );
		} finally {
			$GLOBALS['settings']['sponsor_tiers_live'] = true;
		}
		$gone      = press( 'handle_program_change', array_merge( program_typed(), decision( 99999 ) ), MANAGER );
		$kept_gone = kept_for( MANAGER );
		$keeps     = WPCPM_Sponsor_Event_Queue::KEEPS_TYPED;
		return array( $keeps, array_values( array_diff( $keeps, array_keys( WPCPM_Sponsor_Event_Queue::messages() ) ) ), $off, $kept_off, $gone, $kept_gone );
	},
	array( array( 'sevent-program-fields', 'sevent-program-in-past', 'sevent-program-shrink', 'sevent-program-busy', 'sevent-failed' ), array(), array( 'sevent-off', $admin_card ), null, array( 'sevent-gone', $admin_card ), null )
);

ck(
	'the program\'s plan refused for its fields comes back with a title and a description holding a < , quote marks and an ampersand drawn in their boxes as they were typed, the flash holding them as the cleaner left them',
	static function () use ( $card_for ) {
		$text = static function ( $markup ) { return htmlspecialchars_decode( (string) $markup, ENT_QUOTES ); };
		press( 'handle_program_plan', program_typed( array( 'wpcpm_sevent_title' => 'Why 2 < 3, and "Q&A"', 'wpcpm_sevent_description' => 'Is 2 < 3? "Yes"' ) ), MANAGER );
		$kept = kept_for( MANAGER );
		$rows = rows_of( $card_for( MANAGER ), 'wpcpm-sevent-program-new' );
		// A kept description is drawn after a line feed of its own, which the parser drops.
		return array( is_array( $kept ) ? array( $kept['values']['title'], $kept['values']['description'] ) : $kept, $text( $rows['title'] ), $text( (string) preg_replace( '/^\n/', '', (string) $rows['description'] ) ) );
	},
	array( array( 'Why 2 &lt; 3, and &quot;Q&amp;A&quot;', 'Is 2 &lt; 3? &quot;Yes&quot;' ), 'Why 2 < 3, and "Q&A"', 'Is 2 < 3? "Yes"' )
);

/** Whether the card draws the fold whose summary opens with these words open, closed, or not at all. */
function fold_state( $html, $summary ) {
	foreach ( array( 'open' => ' open', 'closed' => '' ) as $state => $attribute ) {
		if ( false !== strpos( (string) $html, '<details class="wpcpm-sponsor-post__return"' . $attribute . '><summary class="button">' . $summary ) ) {
			return $state;
		}
	}
	return 'missing';
}

ck(
	'the fold holding the program\'s Plan form is drawn open when the form comes back with what was typed, and closed before the refusal, for another manager and on the next draw',
	static function () use ( $card_for ) {
		$plan   = 'Plan an event</summary>';
		$before = fold_state( $card_for( MANAGER ), $plan );
		press( 'handle_program_plan', program_typed(), MANAGER );
		$other = fold_state( $card_for( MANAGER_TWO ), $plan );
		$typed = fold_state( $card_for( MANAGER ), $plan );
		$after = fold_state( $card_for( MANAGER ), $plan );
		return array( $before, $other, $typed, $after );
	},
	array( 'closed', 'closed', 'open', 'closed' )
);
ck(
	'of the program\'s events, only the refused one\'s Change fold is drawn open: the sooner one\'s, drawn before it on the same page, and the Plan fold stay closed, and all are closed on the next draw',
	static function () use ( $card_for, $program_first, $program_second ) {
		$first  = 'Change the event<span class="screen-reader-text"> ' . get_post( $program_first )->post_title . '</span></summary>';
		$second = 'Change the event<span class="screen-reader-text"> ' . get_post( $program_second )->post_title . '</span></summary>';
		press( 'handle_program_change', array_merge( program_typed(), decision( $program_second ) ), MANAGER );
		$typed = $card_for( MANAGER );
		$after = $card_for( MANAGER );
		return array( array( fold_state( $typed, $first ), fold_state( $typed, $second ), fold_state( $typed, 'Plan an event</summary>' ) ), array( fold_state( $after, $first ), fold_state( $after, $second ), fold_state( $after, 'Plan an event</summary>' ) ), strpos( $typed, $first ) < strpos( $typed, $second ) );
	},
	array( array( 'closed', 'open', 'closed' ), array( 'closed', 'closed', 'closed' ), true )
);

// What comes back is what was typed, through the plugin's own flash over user meta that unslashes
// what it writes, as core's does: a backslash in the title, two in a row in the call link and a
// shrug over two lines in the description. The card's own outcome waits beside the typing and is
// taken first, so the typing is written again before the form reads it. Each look is by a manager
// of their own, since the real flash remembers what it took for the rest of the process. The title
// and the description are kept beside the typing exactly as typed, under the form's own name, which
// the press itself cleared first: this process has taken that name for the manager already, so the
// look draws as a look made once that entry has expired does, the channel's other rows with the
// title and the description the form holds of its own, empty for a plan and the event's for a
// change, and what the flash holds after the look is that entry alone, for the next page to take,
// as it was typed.
a_user( KEPT_PLAN_MANAGER, 'Kim Keptplan', array( 'administrator' ) );
a_user( KEPT_CHANGE_MANAGER, 'Kai Keptchange', array( 'administrator' ) );

/** The backslashes typed into the program's refused forms, the length the form refuses; any value replaced. */
function program_slashed( array $over = array() ) {
	return program_typed(
		array_merge(
			array(
				'wpcpm_sevent_title'       => 'Typed "Q&A" in C:\drafts',
				'wpcpm_sevent_description' => '¯\_(ツ)_/¯ typed on one line' . "\n" . 'and ¯\_(ツ)_/¯ on the next',
				'wpcpm_sevent_link'        => 'https://meet.example/kept?pwd=a%2Fb&path=two\\\\in-a-row',
			),
			$over
		)
	);
}

$program_slashed_rows  = array(
	'Typed &quot;Q&amp;A&quot; in C:\drafts',
	'¯\_(ツ)_/¯ typed on one line' . "\n" . 'and ¯\_(ツ)_/¯ on the next',
	'https://meet.example/kept?pwd=a%2Fb&amp;path=two\\\\in-a-row',
);
$program_slashed_boxes = array(
	'title'       => 'Typed "Q&A" in C:\drafts',
	'description' => '¯\_(ツ)_/¯ typed on one line' . "\n" . 'and ¯\_(ツ)_/¯ on the next',
);

/** What a manager's flash holds in user meta, by channel, as the real flash wrote it. */
function program_flash_meta( $uid ) {
	return isset( $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] ) && is_array( $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] ) ? $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] : array();
}

ck(
	'through the plugin\'s own flash, the program\'s Plan form refused for its fields comes back with the two in a row in the call link exactly as typed, its title and description boxes empty, as the plan drew them, and the flash holds nothing after but the title\'s backslash and the shrug over two lines kept exactly as typed under the form\'s name',
	static function () use ( $card_for, $admin_card, $program_slashed_rows, $program_slashed_boxes ) {
		return through_real_flash(
			static function () use ( $card_for, $admin_card, $program_slashed_rows, $program_slashed_boxes ) {
				$left = press( 'handle_program_plan', program_slashed(), KEPT_PLAN_MANAGER );
				$rows = rows_of( $card_for( KEPT_PLAN_MANAGER ), 'wpcpm-sevent-program-new' );
				$meta = program_flash_meta( KEPT_PLAN_MANAGER );
				return array( $left, array( $rows['title'], $rows['description'], $rows['link'] ), array_keys( $meta ), isset( $meta['typed:sevent::new']['typed'] ) && $meta['typed:sevent::new']['typed'] === $program_slashed_boxes );
			}
		);
	},
	array( array( 'sevent-program-fields', $admin_card ), array( '', '', $program_slashed_rows[2] ), array( 'typed:sevent::new' ), true )
);
ck(
	'and so does the program\'s Change form, of the later of its events, drawn after the sooner one\'s form took the flash, its title and description boxes holding the event\'s own',
	static function () use ( $card_for, $admin_card, $program_second, $program_slashed_rows, $program_slashed_boxes ) {
		return through_real_flash(
			static function () use ( $card_for, $admin_card, $program_second, $program_slashed_rows, $program_slashed_boxes ) {
				$left = press( 'handle_program_change', array_merge( program_slashed(), decision( $program_second ) ), KEPT_CHANGE_MANAGER );
				$rows = rows_of( $card_for( KEPT_CHANGE_MANAGER ), 'wpcpm-sevent-program-' . $program_second );
				$meta = program_flash_meta( KEPT_CHANGE_MANAGER );
				$name = 'typed:sevent::' . (int) $program_second;
				return array( $left, array( $rows['title'], $rows['description'], $rows['link'] ), array_keys( $meta ) === array( $name ), isset( $meta[ $name ]['typed'] ) && $meta[ $name ]['typed'] === $program_slashed_boxes );
			}
		);
	},
	array( array( 'sevent-program-fields', $admin_card ), array( 'Program second', 'What we &quot;cover&quot; &amp; more.' . "\n" . 'Bring questions.', $program_slashed_rows[2] ), true, true )
);

// The Change form shows a manager what they typed whether or not WordPress ran kses on it. An
// administrator holds `unfiltered_html`: what they write is stored as the cleaner left it. On a site
// that withholds it, kses writes every bare "&" as `&amp;` and every bare ">" as `&gt;` in the title
// and in the description, wherever they fall (bin/stubs/member-kses.php), and the form reads them
// back, as it does for a sponsor's member.
const PROGRAM_AMPERSAND = 'Q&A with our team';
const PROGRAM_GREATER   = 'Ages > 5 welcome';
const PROGRAM_MIXED     = 'Q&A first: is 2 < 3? "Yes"';

/**
 * A manager plans an event of the program's own with the words given, with kses on for whoever does
 * not hold `unfiltered_html`; its Change form is drawn on the card and its boxes posted back as
 * drawn, and the event is canceled. With `$held`, the event is planned with plain words and then
 * holds that text as its title and description, as a release before the cleaner's losses were
 * refused stored a typing it took words from.
 *
 * @param Closure     $card_for The card as one manager sees it.
 * @param int         $uid      Who plans, draws and changes.
 * @param string      $words    The title and the description typed.
 * @param string|null $held     The text the event holds instead, or null.
 * @return array The outcome of the plan (`planned`), what the store holds (`title`, `description`),
 *               what the boxes show (`box_title`, `box_description`), the outcome of posting the
 *               boxes back (`posted_back`) and whether the store holds the same (`kept`).
 */
function program_round_trip( Closure $card_for, $uid, $words, $held = null ) {
	return wpcpm_stub_with_member_kses(
		static function () use ( $card_for, $uid, $words, $held ) {
			$typed   = null === $held ? $words : 'Plain words';
			$planned = press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_date' => '2026-12-14', 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_title' => $typed, 'wpcpm_sevent_description' => $typed ) ), $uid );
			$id      = 0;
			foreach ( $GLOBALS['posts'] as $post ) {
				if ( 'wpcpm_sponsor_event' === $post->post_type && 'program' === meta( $post->ID, '_wpcpm_sevent_kind' ) ) { $id = max( $id, (int) $post->ID ); }
			}
			if ( null !== $held && $id > 0 ) {
				$GLOBALS['posts'][ $id ]->post_title   = $held;
				$GLOBALS['posts'][ $id ]->post_content = $held;
			}
			$once   = array( get_post( $id )->post_title, get_post( $id )->post_content );
			$back   = array_map( static function ( $markup ) { return htmlspecialchars_decode( (string) $markup, ENT_QUOTES ); }, rows_of( $card_for( $uid ), 'wpcpm-sevent-program-' . $id ) );
			$posted = press( 'handle_program_change', array_merge( program_form( array( 'wpcpm_sevent_date' => $back['date'], 'wpcpm_sevent_time' => $back['time'], 'wpcpm_sevent_minutes' => $back['minutes'], 'wpcpm_sevent_places' => $back['places'], 'wpcpm_sevent_title' => $back['title'], 'wpcpm_sevent_description' => $back['description'], 'wpcpm_sevent_link' => $back['link'] ) ), decision( $id ) ), $uid );
			$twice  = array( get_post( $id )->post_title, get_post( $id )->post_content );
			press( 'handle_program_cancel', decision( $id ), $uid );

			return array(
				'planned'         => $planned[0],
				'title'           => $once[0],
				'description'     => $once[1],
				'box_title'       => $back['title'],
				'box_description' => $back['description'],
				'posted_back'     => $posted[0],
				'kept'            => $twice === $once,
			);
		}
	);
}

ck(
	'a manager who holds unfiltered_html finds the program\'s own words in the Change form exactly as they typed them, and the store holds them as the cleaner left them: "Q&A with our team", "Ages > 5 welcome" and "Q&A first: is 2 < 3? "Yes""; posting the boxes back changes nothing',
	static function () use ( $card_for ) {
		$got = array();
		foreach ( array( PROGRAM_AMPERSAND => PROGRAM_AMPERSAND, PROGRAM_GREATER => PROGRAM_GREATER, PROGRAM_MIXED => 'Q&A first: is 2 &lt; 3? &quot;Yes&quot;' ) as $words => $stored ) {
			$trip  = program_round_trip( $card_for, MANAGER, $words );
			$got[] = array( $trip['planned'], $trip['title'] === $stored, $trip['description'] === $stored, $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-program-planned', true, true, PROGRAM_AMPERSAND, PROGRAM_AMPERSAND, 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', true, true, PROGRAM_GREATER, PROGRAM_GREATER, 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', true, true, PROGRAM_MIXED, PROGRAM_MIXED, 'sevent-program-unchanged', true ),
	)
);
ck(
	'a manager whose words WordPress runs through kses finds them in the Change form exactly as they typed them as well, though the store holds each with its ampersands and its > written as entities; posting the boxes back changes nothing',
	static function () use ( $card_for ) {
		$got = array();
		foreach ( array( PROGRAM_AMPERSAND => 'Q&amp;A with our team', PROGRAM_GREATER => 'Ages &gt; 5 welcome', PROGRAM_MIXED => 'Q&amp;A first: is 2 &lt; 3? &quot;Yes&quot;' ) as $words => $stored ) {
			$trip  = program_round_trip( $card_for, MANAGER_TWO, $words );
			$got[] = array( $trip['planned'], $trip['title'] === $stored, $trip['description'] === $stored, $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-program-planned', true, true, PROGRAM_AMPERSAND, PROGRAM_AMPERSAND, 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', true, true, PROGRAM_GREATER, PROGRAM_GREATER, 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', true, true, PROGRAM_MIXED, PROGRAM_MIXED, 'sevent-program-unchanged', true ),
	)
);

// The cleaner leaves a "<" as typed where a space follows it and a ">" comes after. An
// administrator's words meet no kses and are stored as the cleaner left them; on a site that
// withholds `unfiltered_html`, kses would read the "<", the ">" and the words between them as a tag
// and drop them, so the store writes each "<" and ">" the cleaner left as `&lt;` and `&gt;` first.
const PROGRAM_AGES = 'Ages 8 < 12 welcome, adults > 18 pay';
const PROGRAM_DEMO = 'Use 2 < 3 then 5 > 4 in the demo';

ck(
	'a manager\'s words with a "<" and a ">" after it keep every word either way: "Ages 8 < 12 welcome, adults > 18 pay" and "Use 2 < 3 then 5 > 4 in the demo" are stored as typed for a manager who holds unfiltered_html, and with each < and > written as entities for one whose words WordPress runs through kses, which would have dropped the words between them; the Change form shows both exactly as typed, and posting the boxes back changes nothing',
	static function () use ( $card_for ) {
		$got = array();
		foreach ( array( MANAGER, MANAGER_TWO ) as $uid ) {
			foreach ( array( PROGRAM_AGES, PROGRAM_DEMO ) as $words ) {
				$trip  = program_round_trip( $card_for, $uid, $words );
				$got[] = array( $trip['planned'], $trip['title'], $trip['description'], $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
			}
		}
		return $got;
	},
	array(
		array( 'sevent-program-planned', PROGRAM_AGES, PROGRAM_AGES, PROGRAM_AGES, PROGRAM_AGES, 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', PROGRAM_DEMO, PROGRAM_DEMO, PROGRAM_DEMO, PROGRAM_DEMO, 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay', 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay', PROGRAM_AGES, PROGRAM_AGES, 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', 'Use 2 &lt; 3 then 5 &gt; 4 in the demo', 'Use 2 &lt; 3 then 5 &gt; 4 in the demo', PROGRAM_DEMO, PROGRAM_DEMO, 'sevent-program-unchanged', true ),
	)
);

// Typed now, a tag is refused before anything is stored (see the losses below). A release before
// that took a typed tag out and stored `We &lt;3 our team > all`, `&gt;` for its ">" where kses ran:
// both boxes draw the ">" after the "<3" as `&gt;`, since a post-back would otherwise lose "3 our
// team", and posted back as drawn the boxes are not asked about losses.
const PROGRAM_TAG_TEAM = 'We <3 our <b>team</b> > all';

ck(
	'a manager\'s words an earlier release stored with a typed tag taken out keep every word left when the Change form is posted back unedited, whether or not WordPress runs kses on them: "We <3 our <b>team</b> > all", stored so, shows the > after the <3 as `&gt;` in the title box and the description box, and posting them back changes nothing',
	static function () use ( $card_for ) {
		$got  = array();
		$held = array(
			MANAGER     => 'We &lt;3 our team > all',
			MANAGER_TWO => 'We &lt;3 our team &gt; all',
		);
		foreach ( array( MANAGER, MANAGER_TWO ) as $uid ) {
			$trip  = program_round_trip( $card_for, $uid, PROGRAM_TAG_TEAM, $held[ $uid ] );
			$got[] = array( $trip['planned'], $trip['title'], $trip['description'], $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-program-planned', 'We &lt;3 our team > all', 'We &lt;3 our team > all', 'We <3 our team &gt; all', 'We <3 our team &gt; all', 'sevent-program-unchanged', true ),
		array( 'sevent-program-planned', 'We &lt;3 our team &gt; all', 'We &lt;3 our team &gt; all', 'We <3 our team &gt; all', 'We <3 our team &gt; all', 'sevent-program-unchanged', true ),
	)
);

/* ---- the mails give a member's words as typed --------------------------------- */

echo "\n=== The mails give a member's words as typed ===\n";

// A member of a sponsor of its own types a "<3", a tag, a ">", an ampersand and quotes into the
// title, and the manager writes a ">", a "<3", an ampersand and quotes into a note, over two lines as
// a browser posts them, with no tag, which the card refuses. The post and the note hold the cleaner's
// and the store's entities. A mail is plain text, so the members read
// the words, every entity read back, in the subject, the Title line and the note alike; the mail layer
// cleans each subject, whose cleaner would read "<3 ... >" as a tag and drop it, words and all, and
// these subjects go to it as plain text.
const SIGNS_SPONSOR     = 'spoAAAA0000000010'; // Champion, no term: a member's words with signs in them.
const SIGNS_REP         = 60;
const SIGNS_TYPED       = 'We <3 "our" <b>team</b> & Bob\'s > all';
const SIGNS_MAILED      = 'We <3 "our" team & Bob\'s > all';
const SIGNS_NOTE        = "Ages > 5: we <3 \"our\" team & Bob's\r\nSee you, 2 < 3.";
const SIGNS_NOTE_MAILED = SIGNS_NOTE;

$GLOBALS['index'][ SIGNS_SPONSOR ] = array( 'record_id' => SIGNS_SPONSOR, 'name' => 'Signs Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' );
a_user( SIGNS_REP, 'Sid Signsrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => SIGNS_SPONSOR, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'Europe/Warsaw' ) );

/** The sponsor's member plans an event with the signs in its title, signed in as that member. */
function signs_plan( $start ) {
	$GLOBALS['uid'] = SIGNS_REP;
	return WPCPM_Sponsor_Event::plan( SIGNS_SPONSOR, event_fields( $start, array( 'title' => SIGNS_TYPED, 'zone' => 'Europe/Warsaw' ) ), SIGNS_REP, 'member' );
}

/** The member's mail as these checks read it: its subject, the note it gives or '', and its Title line. */
function signs_mail() {
	foreach ( $GLOBALS['sent'] as $mail ) {
		if ( 'sid.signsrep@people.example' === $mail['to'] ) {
			return array(
				$mail['subject'],
				preg_match( '/with this note:\n\n(.*?)\n\nTitle: /s', $mail['body'], $note ) ? $note[1] : '',
				preg_match( '/^Title: .*$/m', $mail['body'], $title ) ? $title[0] : '',
			);
		}
	}
	return array();
}

$signs_first = attempt( static function () { return signs_plan( utc( '2026-12-15 10:00' ) ); } );

ck(
	'the member\'s title is stored with the tag taken out and the "<", the quotes after it, the ">" and the ampersand as entities',
	static function () use ( $signs_first ) { return get_post( $signs_first )->post_title; },
	'We &lt;3 &quot;our&quot; team &amp; Bob\'s &gt; all'
);
ck(
	'approved, the member reads the title as typed, in the subject and in the Title line, every word kept',
	static function () use ( $signs_first ) {
		$GLOBALS['sent'] = array();
		press( 'handle_approve', decision( $signs_first ), MANAGER );
		return signs_mail();
	},
	array( '[WordPress Education] Your event is approved: ' . SIGNS_MAILED, '', 'Title: ' . SIGNS_MAILED )
);
ck(
	'canceled with the note, the member reads the note as it was typed, its line break as posted, and the title as typed in the subject and in the Title line',
	static function () use ( $signs_first ) {
		$GLOBALS['sent'] = array();
		press( 'handle_cancel', decision( $signs_first, SIGNS_NOTE ), MANAGER );
		return signs_mail();
	},
	array( '[WordPress Education] Your event is canceled: ' . SIGNS_MAILED, SIGNS_NOTE_MAILED, 'Title: ' . SIGNS_MAILED )
);
ck(
	'returned with the note, the member reads the note and the title as typed as well',
	static function () {
		$again           = signs_plan( utc( '2026-12-17 10:00' ) );
		$GLOBALS['sent'] = array();
		press( 'handle_return', decision( $again, SIGNS_NOTE ), MANAGER );
		return signs_mail();
	},
	array( '[WordPress Education] Your event was returned: ' . SIGNS_MAILED, SIGNS_NOTE_MAILED, 'Title: ' . SIGNS_MAILED )
);

/* ---- a title, a description or a note the cleaner would take words from ---------- */

echo "\n=== A title, a description or a note the cleaner would take words from ===\n";

// The card's boxes of typed text are refused, as the Sponsor Dashboard's planner refuses its own,
// when WordPress's text cleaner would take words from what was typed: "<12" and ">18" read as a tag
// and dropped with the words between them, "%ca" read as a URL octet and dropped. Asked of what was
// typed, before the store is asked, so a refused press stores, logs, mails and sends nothing; the
// box named in the words of the form's button; the form coming back once with exactly what was
// typed, for the manager who pressed, its fold open. A Change form's box posted back as it was drawn
// is not asked, and the event keeps what it holds there.
const LOSS_SPONSOR  = 'spoAAAA0000000011'; // Champion, no term: its events are returned and canceled with notes.
const LOSS_REP      = 61;
const LOSS_TITLE    = 'Ages 8 <12 welcome, adults >18 pay';
const SPACED_TITLE  = 'Ages 8 < 12 welcome, adults > 18 pay';
const LOSS_STORY    = "Kids <12 free,\r\nadults >18 pay, and 10%cashback";
const SPACED_STORY  = "Kids < 12 free,\r\nadults > 18 pay, and 10% cashback";
const LOSS_NOTE     = "Kids <12 free,\r\nadults >18 pay";
const SPACED_NOTE   = "Kids < 12 free,\r\nadults > 18 pay";
const CASHBACK      = 'Get 10%cashback at the booth';
const CASHBACK_TEXT = "Bring a laptop,\r\nand 10%cashback.";

$GLOBALS['index'][ LOSS_SPONSOR ] = array( 'record_id' => LOSS_SPONSOR, 'name' => 'Loss Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' );
a_user( LOSS_REP, 'Lou Lossrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => LOSS_SPONSOR, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'Europe/Warsaw' ) );

$loss_words = array(
	'plan'   => 'Nothing was planned, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then plan the event again.',
	'save'   => 'Nothing was saved, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then save again.',
	'return' => 'Nothing was returned, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then return it again.',
	'cancel' => 'Nothing was canceled, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then cancel it again.',
);

/** What a text area shows of the markup it was drawn with: each CR LF one LF, as the parser reads it, and the line feed right after its opening tag dropped, as the parser drops it. */
function area_shown( $markup ) {
	return htmlspecialchars_decode( (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", (string) $markup ) ), ENT_QUOTES );
}

/** What a note box shows, read from the markup as area_shown() reads a text area; null when the card draws no such box. */
function note_text( $html, $kind, $id ) {
	return preg_match( '/<textarea id="wpcpm-sevent-' . $kind . '-' . (int) $id . '"[^>]*>(.*?)<\/textarea>/s', (string) $html, $found ) ? area_shown( $found[1] ) : null;
}

/** The newest event of the program's own. */
function newest_program_event() {
	$id = 0;
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'wpcpm_sponsor_event' === $post->post_type && 'program' === meta( $post->ID, '_wpcpm_sevent_kind' ) ) { $id = max( $id, (int) $post->ID ); }
	}
	return $id;
}

/** The program's Change form of one event as a manager's browser posts it, drawn for that manager first; any value replaced. */
function program_change_as_drawn( Closure $card_for, $uid, $id, array $over = array() ) {
	$rows = rows_of( $card_for( $uid ), 'wpcpm-sevent-program-' . (int) $id );
	$post = array();
	foreach ( $rows as $key => $markup ) {
		$post[ 'wpcpm_sevent_' . $key ] = 'description' === $key ? str_replace( "\n", "\r\n", area_shown( $markup ) ) : htmlspecialchars_decode( (string) $markup, ENT_QUOTES );
	}
	return array_merge( $post, decision( $id ), $over );
}

ck(
	'the program\'s plan titled "Ages 8 <12 welcome, adults >18 pay" is refused before the store is asked, the title named in the words of Plan the event: nothing is stored, logged or mailed',
	static function () use ( $admin_card ) {
		$before   = store_state();
		$left     = press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_date' => '2026-12-08', 'wpcpm_sevent_title' => LOSS_TITLE ) ), MANAGER );
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		return array( $left, store_state() === $before, isset( $messages['sevent-program-plan-title-loss'] ) ? $messages['sevent-program-plan-title-loss'] : null );
	},
	array( array( 'sevent-program-plan-title-loss', $admin_card ), true, array( 'error', sprintf( $loss_words['plan'], 'Title' ) ) )
);
ck(
	'the Plan form comes back once, its fold open, with the title exactly as typed and every other box as posted; another manager is drawn it empty, and so is the next draw',
	static function () use ( $card_for, $program_empty_rows ) {
		press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_date' => '2026-12-08', 'wpcpm_sevent_title' => LOSS_TITLE ) ), MANAGER );
		$other  = rows_of( $card_for( MANAGER_TWO ), 'wpcpm-sevent-program-new' );
		$html   = $card_for( MANAGER );
		$first  = rows_of( $html, 'wpcpm-sevent-program-new' );
		$again  = $card_for( MANAGER );
		return array( htmlspecialchars_decode( (string) $first['title'], ENT_QUOTES ), array( $first['date'], $first['time'], $first['minutes'], area_shown( $first['description'] ) ), fold_state( $html, 'Plan an event</summary>' ), $other === $program_empty_rows, rows_of( $again, 'wpcpm-sevent-program-new' ) === $program_empty_rows, fold_state( $again, 'Plan an event</summary>' ) );
	},
	array( LOSS_TITLE, array( '2026-12-08', '15:00', '60', "What the program plans with its partners.\nBring questions." ), 'open', true, true, 'closed' )
);
ck(
	'a description the cleaner would take words from is refused the same way, the description named, and comes back as typed, each line break one',
	static function () use ( $card_for, $admin_card ) {
		$before   = store_state();
		$left     = press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_date' => '2026-12-08', 'wpcpm_sevent_description' => LOSS_STORY ) ), MANAGER );
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		$rows     = rows_of( $card_for( MANAGER ), 'wpcpm-sevent-program-new' );
		return array( $left, store_state() === $before, isset( $messages['sevent-program-plan-description-loss'] ) ? $messages['sevent-program-plan-description-loss'] : null, area_shown( $rows['description'] ) );
	},
	array( array( 'sevent-program-plan-description-loss', $admin_card ), true, array( 'error', sprintf( $loss_words['plan'], 'Description' ) ), str_replace( "\r\n", "\n", LOSS_STORY ) )
);
ck(
	'with a space after each "<" and each "%" the plan keeps every word',
	static function () use ( $admin_card ) {
		$left = press( 'handle_program_plan', program_form( array( 'wpcpm_sevent_date' => '2026-12-08', 'wpcpm_sevent_title' => SPACED_TITLE, 'wpcpm_sevent_description' => SPACED_STORY ) ), MANAGER );
		$post = get_post( newest_program_event() );
		return array( $left, $post instanceof WP_Post ? array( $post->post_title, wp_specialchars_decode( $post->post_content, ENT_QUOTES ) ) : null );
	},
	array( array( 'sevent-program-planned', $admin_card ), array( SPACED_TITLE, SPACED_STORY ) )
);

$loss_program = attempt( static function () { return newest_program_event(); } );

ck(
	'a change of that event titled "Ages 8 <12 welcome, adults >18 pay" is refused, the title named in the words of Save the changes: nothing is stored, logged, mailed or sent, and nobody on it is told',
	static function () use ( $card_for, $admin_card, $loss_program ) {
		$drawn    = program_change_as_drawn( $card_for, MANAGER, $loss_program, array( 'wpcpm_sevent_title' => LOSS_TITLE ) );
		$before   = store_state();
		$left     = press( 'handle_program_change', $drawn, MANAGER );
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		return array( $left, store_state() === $before, get_post( $loss_program )->post_title, isset( $messages['sevent-program-change-title-loss'] ) ? $messages['sevent-program-change-title-loss'] : null, isset( $messages['sevent-program-change-description-loss'] ) ? $messages['sevent-program-change-description-loss'] : null );
	},
	array( array( 'sevent-program-change-title-loss', $admin_card ), true, SPACED_TITLE, array( 'error', sprintf( $loss_words['save'], 'Title' ) ), array( 'error', sprintf( $loss_words['save'], 'Description' ) ) )
);
ck(
	'and its Change form comes back once, its fold open, with the title exactly as typed; the next draw has the event\'s own title, the fold closed',
	static function () use ( $card_for, $loss_program ) {
		$summary = 'Change the event<span class="screen-reader-text"> ' . esc_html( SPACED_TITLE ) . '</span></summary>';
		press( 'handle_program_change', program_change_as_drawn( $card_for, MANAGER, $loss_program, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), MANAGER );
		$html  = $card_for( MANAGER );
		$again = $card_for( MANAGER );
		return array( htmlspecialchars_decode( (string) rows_of( $html, 'wpcpm-sevent-program-' . $loss_program )['title'], ENT_QUOTES ), fold_state( $html, $summary ), htmlspecialchars_decode( (string) rows_of( $again, 'wpcpm-sevent-program-' . $loss_program )['title'], ENT_QUOTES ), fold_state( $again, $summary ) );
	},
	array( LOSS_TITLE, 'open', SPACED_TITLE, 'closed' )
);
ck(
	'an event holding "Get 10%cashback at the booth", written by other means, posted back with its title and description as drawn and a new length, is not asked about them: the length is changed, and the event keeps its title and description as it holds them',
	static function () use ( $card_for, $admin_card, $loss_program ) {
		$GLOBALS['posts'][ $loss_program ]->post_title   = CASHBACK;
		$GLOBALS['posts'][ $loss_program ]->post_content = CASHBACK_TEXT;
		$left = press( 'handle_program_change', program_change_as_drawn( $card_for, MANAGER, $loss_program, array( 'wpcpm_sevent_minutes' => '45' ) ), MANAGER );
		$post = get_post( $loss_program );
		return array( $left, array( $post->post_title, $post->post_content ), (int) meta( $loss_program, '_wpcpm_sevent_minutes' ) );
	},
	array( array( 'sevent-program-changed', $admin_card ), array( CASHBACK, CASHBACK_TEXT ), 45 )
);
ck(
	'its Change form posted as drawn with a length of 5 is refused for its fields and comes back with the title and the description exactly as the event holds them; posted again with the length as it was, it changes nothing: nothing is stored, logged or mailed',
	static function () use ( $card_for, $admin_card, $loss_program ) {
		$first  = press( 'handle_program_change', program_change_as_drawn( $card_for, MANAGER, $loss_program, array( 'wpcpm_sevent_minutes' => '5' ) ), MANAGER );
		$again  = program_change_as_drawn( $card_for, MANAGER, $loss_program );
		$before = store_state();
		$second = press( 'handle_program_change', array_merge( $again, array( 'wpcpm_sevent_minutes' => '45' ) ), MANAGER );
		$post   = get_post( $loss_program );
		return array( $first, $again['wpcpm_sevent_minutes'], $again['wpcpm_sevent_title'], $again['wpcpm_sevent_description'], $second, array( $post->post_title, $post->post_content ), store_state() === $before );
	},
	array( array( 'sevent-program-fields', $admin_card ), '5', CASHBACK, CASHBACK_TEXT, array( 'sevent-program-unchanged', $admin_card ), array( CASHBACK, CASHBACK_TEXT ), true )
);

$loss_waiting = attempt( static function () { return WPCPM_Sponsor_Event::plan( LOSS_SPONSOR, event_fields( utc( '2026-12-09 10:00' ), array( 'title' => 'Loss webinar', 'zone' => 'Europe/Warsaw' ) ), LOSS_REP, 'member' ); } );
$return_fold  = 'Return with a note<span class="screen-reader-text"> Loss webinar</span></summary>';

ck(
	'a return with a note the cleaner would take words from, "Kids <12 free, adults >18 pay", is refused, the note named in the words of Return it with this note: the event still waits, and nothing is stored, logged or mailed',
	static function () use ( $admin_card, $loss_waiting ) {
		$before   = store_state();
		$left     = press( 'handle_return', decision( $loss_waiting, LOSS_NOTE ), MANAGER );
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		return array( $left, store_state() === $before, meta( $loss_waiting, '_wpcpm_sevent_state' ), isset( $messages['sevent-return-note-loss'] ) ? $messages['sevent-return-note-loss'] : null );
	},
	array( array( 'sevent-return-note-loss', $admin_card ), true, 'planned', array( 'error', sprintf( $loss_words['return'], 'A note for the sponsor' ) ) )
);
ck(
	'the event\'s Return fold comes back open once, with the note exactly as typed, each line break one; another manager draws it closed and empty, and so does the next draw',
	static function () use ( $card_for, $loss_waiting, $return_fold ) {
		press( 'handle_return', decision( $loss_waiting, LOSS_NOTE ), MANAGER );
		$other = $card_for( MANAGER_TWO );
		$html  = $card_for( MANAGER );
		$again = $card_for( MANAGER );
		return array( fold_state( $html, $return_fold ), note_text( $html, 'return', $loss_waiting ), fold_state( $other, $return_fold ), note_text( $other, 'return', $loss_waiting ), fold_state( $again, $return_fold ), note_text( $again, 'return', $loss_waiting ) );
	},
	array( 'open', str_replace( "\r\n", "\n", LOSS_NOTE ), 'closed', '', 'closed', '' )
);
ck(
	'a note refused for its length comes back as typed too, in the room its box has',
	static function () use ( $card_for, $admin_card, $loss_waiting, $return_fold ) {
		$long = str_repeat( 'x', 2001 );
		$left = press( 'handle_return', decision( $loss_waiting, $long ), MANAGER );
		$html = $card_for( MANAGER );
		return array( $left, fold_state( $html, $return_fold ), note_text( $html, 'return', $loss_waiting ) );
	},
	array( array( 'sevent-note-long', $admin_card ), 'open', str_repeat( 'x', 2000 ) )
);
ck(
	'with a space after each "<" the note keeps every word: the event is returned, and the member reads the note as typed',
	static function () use ( $admin_card, $loss_waiting ) {
		$GLOBALS['sent'] = array();
		$left            = press( 'handle_return', decision( $loss_waiting, SPACED_NOTE ), MANAGER );
		return array( $left, meta( $loss_waiting, '_wpcpm_sevent_state' ), isset( $GLOBALS['sent'][0] ) && false !== strpos( $GLOBALS['sent'][0]['body'], "with this note:\n\n" . SPACED_NOTE . "\n\nTitle:" ) );
	},
	array( array( 'sevent-returned', $admin_card ), 'cancelled', true )
);

$loss_approved = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( LOSS_SPONSOR, event_fields( utc( '2026-12-10 10:00' ), array( 'title' => 'Loss meetup', 'zone' => 'Europe/Warsaw' ) ), LOSS_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);
$cancel_fold = 'Cancel with a note<span class="screen-reader-text"> Loss meetup</span></summary>';

ck(
	'a cancel with a note the cleaner would take words from, "Get 10%cashback at the booth", is refused, the note named in the words of Cancel with this note: the event stays approved, and nothing is stored, logged or mailed',
	static function () use ( $admin_card, $loss_approved ) {
		$before   = store_state();
		$left     = press( 'handle_cancel', decision( $loss_approved, CASHBACK ), MANAGER );
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		return array( $left, store_state() === $before, meta( $loss_approved, '_wpcpm_sevent_state' ), isset( $messages['sevent-cancel-note-loss'] ) ? $messages['sevent-cancel-note-loss'] : null );
	},
	array( array( 'sevent-cancel-note-loss', $admin_card ), true, 'approved', array( 'error', sprintf( $loss_words['cancel'], 'A note for the sponsor' ) ) )
);
ck(
	'the event\'s Cancel fold comes back open once with the note exactly as typed, and the Return fold of another event stays closed',
	static function () use ( $card_for, $loss_approved, $cancel_fold ) {
		press( 'handle_cancel', decision( $loss_approved, CASHBACK ), MANAGER );
		$html  = $card_for( MANAGER );
		$again = $card_for( MANAGER );
		return array( fold_state( $html, $cancel_fold ), note_text( $html, 'cancel', $loss_approved ), fold_state( $again, $cancel_fold ), note_text( $again, 'cancel', $loss_approved ) );
	},
	array( 'open', CASHBACK, 'closed', '' )
);
ck(
	'with a space after the "%" the note keeps every word: the event is canceled, and the member reads the note as typed',
	static function () use ( $admin_card, $loss_approved ) {
		$GLOBALS['sent'] = array();
		$left            = press( 'handle_cancel', decision( $loss_approved, 'Get 10% cashback at the booth' ), MANAGER );
		$mailed          = '';
		foreach ( $GLOBALS['sent'] as $mail ) {
			if ( 'lou.lossrep@people.example' === $mail['to'] ) { $mailed = $mail['body']; }
		}
		return array( $left, meta( $loss_approved, '_wpcpm_sevent_state' ), false !== strpos( $mailed, "with this note:\n\nGet 10% cashback at the booth\n\nTitle:" ) );
	},
	array( array( 'sevent-manager-canceled', $admin_card ), 'cancelled', true )
);
// A kept note is drawn escaped, as every value of the card is: one holding the box's own closing tag
// and an ampersand typed out comes back as typed, and closes nothing.
const MARKUP_NOTE = '</textarea><b>x</b> Q&amp;A';

$loss_third  = attempt( static function () { return WPCPM_Sponsor_Event::plan( LOSS_SPONSOR, event_fields( utc( '2026-12-11 10:00' ), array( 'title' => 'Loss workshop', 'zone' => 'Europe/Warsaw' ) ), LOSS_REP, 'member' ); } );
$third_fold  = 'Return with a note<span class="screen-reader-text"> Loss workshop</span></summary>';
$third_inner = static function ( $html ) use ( $loss_third ) {
	return preg_match( '/<textarea id="wpcpm-sevent-return-' . (int) $loss_third . '"[^>]*>(.*?)<\/textarea>/s', (string) $html, $found ) ? $found[1] : null;
};

ck(
	'a kept note holding "</textarea><b>x</b> Q&amp;A" is drawn escaped in its box, the closing tag and the ampersand as text, and the box shows it exactly as typed',
	static function () use ( $card_for, $admin_card, $loss_third, $third_inner ) {
		$left = press( 'handle_return', decision( $loss_third, MARKUP_NOTE ), MANAGER );
		$html = $card_for( MANAGER );
		return array( $left, $third_inner( $html ), note_text( $html, 'return', $loss_third ) );
	},
	array( array( 'sevent-return-note-loss', $admin_card ), "\n" . '&lt;/textarea&gt;&lt;b&gt;x&lt;/b&gt; Q&amp;amp;A', MARKUP_NOTE )
);
ck(
	'a note an earlier refusal kept, whose page was never drawn, is dropped when the form is posted again: refused once for its words and pressed again with no note, the form draws closed and empty',
	static function () use ( $card_for, $admin_card, $loss_third, $third_fold ) {
		$first  = press( 'handle_return', decision( $loss_third, LOSS_NOTE ), MANAGER );
		$second = press( 'handle_return', decision( $loss_third, '' ), MANAGER );
		$html   = $card_for( MANAGER );
		return array( $first, $second, fold_state( $html, $third_fold ), note_text( $html, 'return', $loss_third ), meta( $loss_third, '_wpcpm_sevent_state' ) );
	},
	array( array( 'sevent-return-note-loss', $admin_card ), array( 'sevent-note-empty', $admin_card ), 'closed', '', 'planned' )
);
// A note is a text area's typing, so the cleaner asked of it is the text area's. Today the two
// cleaners give one answer on every typing (they differ only in folding white space, which the
// check folds on both sides, and whatever either takes away holds a character that is not white
// space), so no typing tells them apart, and the kind is pinned where it is asked.
ck(
	'a note is asked about lost words as a text area\'s typing, the kind its box is',
	static function () {
		$found = new ReflectionMethod( 'WPCPM_Sponsor_Event_Queue', 'refuse_note' );
		$text  = implode( '', array_slice( file( $found->getFileName() ), $found->getStartLine() - 1, $found->getEndLine() - $found->getStartLine() + 1 ) );
		return array( substr_count( $text, "WPCPM_Typed_Text::cleaner_loses( \$typed, 'lines' )" ), substr_count( $text, 'WPCPM_Typed_Text::cleaner_loses(' ) );
	},
	array( 1, 1 )
);
// The cleaner writes a reference typed out after a "<", such as "&#9;", in core's own form, `&#009;`,
// two characters longer than it was typed. The box took what was typed, so the note is counted as
// typed (`WPCPM_Typed_Text::box_length()`), by the card and by the store alike, and a note that fit
// its box is never refused for what the cleaner made of it.
ck(
	'a note of 2,000 typed characters holding "Kids < 5, see &#9; list", which the cleaner stores two characters longer, fits its box and the event is returned with it; one typed character more is refused, with nothing written',
	static function () use ( $admin_card, $loss_third ) {
		$typed  = str_pad( 'Kids < 5, see &#9; list. ', 2000, 'x' );
		$before = store_state();
		$over   = press( 'handle_return', decision( $loss_third, $typed . 'y' ), MANAGER );
		$same   = store_state() === $before;
		$left   = press( 'handle_return', decision( $loss_third, $typed ), MANAGER );
		$made   = meta( $loss_third, '_wpcpm_sevent_decision' );
		$kept   = is_array( $made ) && isset( $made['note'] ) ? (string) $made['note'] : '';
		return array( WPCPM_Typed_Text::box_length( $typed ), $over, $same, $left, meta( $loss_third, '_wpcpm_sevent_state' ), false !== strpos( $kept, '&#009;' ), WPCPM_Typed_Text::typed_length( $kept ) );
	},
	array( 2000, array( 'sevent-note-long', $admin_card ), true, array( 'sevent-returned', $admin_card ), 'cancelled', true, 2002 )
);
ck(
	'in each of the four presses the loss is asked before the form is read for the store, and before the store is asked',
	static function () {
		$body = static function ( $method ) {
			$found = new ReflectionMethod( 'WPCPM_Sponsor_Event_Queue', $method );
			return implode( '', array_slice( file( $found->getFileName() ), $found->getStartLine() - 1, $found->getEndLine() - $found->getStartLine() + 1 ) );
		};
		$plan   = $body( 'handle_program_plan' );
		$change = $body( 'handle_program_change' );
		$return = $body( 'handle_return' );
		$cancel = $body( 'handle_cancel' );
		$note   = $body( 'refuse_note' );
		$before = static function ( $text, $first, $then ) {
			$at = strpos( $text, $first );
			return false !== $at && false !== strpos( $text, $then ) && $at < strpos( $text, $then );
		};
		return array(
			$before( $plan, 'WPCPM_Sponsor_Event_Planner::loss_check(', 'WPCPM_Sponsor_Event::fields_from(' ),
			$before( $change, 'WPCPM_Sponsor_Event_Planner::loss_check(', 'WPCPM_Sponsor_Event::fields_from(' ),
			$before( $return, 'self::refuse_note(', 'WPCPM_Sponsor_Event::return_with_note(' ),
			$before( $cancel, 'self::refuse_note(', 'WPCPM_Sponsor_Event::cancel(' ),
			$before( $note, 'WPCPM_Typed_Text::cleaner_loses(', 'self::note_refusal(' ),
		);
	},
	array( true, true, true, true, true )
);

/* ---- the words ---------------------------------------------------------------- */

echo "\n=== The words ===\n";

$flashed = array( 'sevent-approved', 'sevent-returned', 'sevent-manager-canceled', 'sevent-note-empty', 'sevent-note-long', 'sevent-not-waiting', 'sevent-no-tier', 'sevent-was-canceled', 'sevent-past', 'sevent-busy', 'sevent-gone', 'sevent-off', 'sevent-failed', 'sevent-program-planned', 'sevent-program-changed', 'sevent-program-unchanged', 'sevent-program-fields', 'sevent-program-in-past', 'sevent-program-shrink', 'sevent-program-busy', 'sevent-program-not-its', 'sevent-program-own', 'sevent-program-plan-title-loss', 'sevent-program-plan-description-loss', 'sevent-program-change-title-loss', 'sevent-program-change-description-loss', 'sevent-return-note-loss', 'sevent-cancel-note-loss' );

ck(
	'a sentence for every status the six presses flash, each a notice class and words, and nothing more',
	static function () use ( $flashed ) {
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		$shapes   = array();
		foreach ( $messages as $key => $pair ) { $shapes[ $key ] = is_array( $pair ) && 2 === count( $pair ) && in_array( $pair[0], array( 'success', 'error' ), true ) && '' !== trim( (string) $pair[1] ); }
		$keys = array_keys( $messages );
		sort( $keys );
		$want = $flashed;
		sort( $want );
		return array( $keys === $want, array_values( array_unique( $shapes ) ) );
	},
	array( true, array( true ) )
);
ck(
	'the sentences a manager reads: approved, returned and canceled, which claim no mail; no note; approved already; a sponsor that no longer offers events; the switch',
	static function () {
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		return array( $messages['sevent-approved'], $messages['sevent-returned'], $messages['sevent-manager-canceled'], $messages['sevent-note-empty'], $messages['sevent-not-waiting'][1], $messages['sevent-no-tier'], $messages['sevent-off'][1] );
	},
	array(
		array( 'success', 'The event is approved.' ),
		array( 'success', 'The event went back to the sponsor, canceled, with your note.' ),
		array( 'success', 'The event is canceled.' ),
		array( 'error', 'Nothing was changed. Write a note for the sponsor: it is the whole of what the sponsor is told.' ),
		'Nothing was changed. That event was approved already; to call it off, cancel it under Coming up.',
		array( 'error', 'Nothing was changed. That sponsor no longer offers events, so its event cannot be approved. Return it with a note.' ),
		'Nothing was changed. Events wait until Sponsor tiers is switched on in Settings.',
	)
);
ck(
	'a note longer than the store keeps is refused in words that say how long a note may be',
	static function () {
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		return isset( $messages['sevent-note-long'] ) ? $messages['sevent-note-long'] : null;
	},
	array( 'error', 'Nothing was changed. Keep the note to 2000 characters.' )
);
ck(
	'the program\'s own presses say what became of its event, naming the program where a sponsor\'s refusal names the sponsor, and every refusal of theirs opens as the card\'s do; a sponsor\'s decision pressed on the program\'s own says where its change and cancel are',
	static function () {
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		$refusals = array();
		foreach ( array( 'sevent-program-fields', 'sevent-program-in-past', 'sevent-program-shrink', 'sevent-program-busy', 'sevent-program-not-its', 'sevent-program-own' ) as $key ) {
			$refusals[] = isset( $messages[ $key ] ) && 'error' === $messages[ $key ][0] && 0 === strpos( $messages[ $key ][1], 'Nothing was changed. ' );
		}
		return array( $messages['sevent-program-planned'], $messages['sevent-program-changed'], $messages['sevent-program-unchanged'], $messages['sevent-program-busy'][1], $messages['sevent-program-not-its'][1], isset( $messages['sevent-program-own'] ) ? $messages['sevent-program-own'][1] : '', array_values( array_unique( $refusals ) ) );
	},
	array(
		array( 'success', 'The event is planned, and open to join now: the program\'s own events need no approval.' ),
		array( 'success', 'The event is changed.' ),
		array( 'success', 'Nothing changed.' ),
		'Nothing was changed. Another change to the program\'s events was going through. Try again in a moment.',
		'Nothing was changed. That event is a sponsor\'s, not one of the program\'s own.',
		'Nothing was changed. That event is the program\'s own: change or cancel it under Coming up.',
		array( true ),
	)
);
ck(
	'an event that is gone is not on this site anymore, in US English, and no sentence of the card says any more',
	static function () {
		$messages = WPCPM_Sponsor_Event_Queue::messages();
		$uk       = array_keys( array_filter( $messages, static function ( $pair ) { return false !== stripos( (string) $pair[1], 'any more' ); } ) );
		return array( $messages['sevent-gone'][1], $uk );
	},
	array( 'Nothing was changed. That event is not on this site anymore.', array() )
);

/* ---- across the run ------------------------------------------------------------ */

echo "\n=== Across the run ===\n";

ck(
	'no mail to a sponsor\'s member names a person on an event, though the events approved and returned here had one on them',
	static function () {
		$members = array();
		foreach ( $GLOBALS['users'] as $user ) {
			if ( '' !== (string) get_user_meta( $user->ID, 'wpcpm_sponsor_record_id', true ) ) {
				$members[] = $user->user_email;
			}
		}
		$read  = 0;
		$leaks = array();
		foreach ( isset( $GLOBALS['all_sent'] ) ? $GLOBALS['all_sent'] : array() as $mail ) {
			if ( ! in_array( $mail[0], $members, true ) ) {
				continue;
			}
			++$read;
			foreach ( array( 'Privacywalk', 'privacywalk@', 'Mo Manager', 'mo.manager@' ) as $needle ) {
				if ( false !== stripos( serialize( $mail ), $needle ) ) {
					$leaks[] = $mail[0] . ': ' . $needle;
				}
			}
		}
		return array( $read >= 8, $leaks );
	},
	array( true, array() )
);

/* ---- the house rules ---------------------------------------------------------- */

echo "\n=== The house rules ===\n";

$queue_src = (string) @file_get_contents( $queue_file );

ck(
	'the queue holds no em or en dash, no sponsor ID compared with ===, no Airtable',
	array( '' !== $queue_src, preg_match( '/\x{2013}|\x{2014}/u', $queue_src ), preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $queue_src ), false !== strpos( $queue_src, 'WPCPM_Airtable' ) ),
	array( true, 0, 0, false )
);
ck(
	'and writes nothing of its own: the store writes, the program\'s own plan, change and cancel included, and the queue reads the request, draws and mails',
	array( '' !== $queue_src, preg_match( '/\b(update_post_meta|add_post_meta|delete_post_meta|wp_insert_post|wp_update_post|update_option|add_option|record_sponsor|record_program)\s*\(/', $queue_src ), 1 === preg_match( '/\$planned\s*=\s*WPCPM_Sponsor_Event::plan_program\(/', $queue_src ), 1 === preg_match( '/\$changed\s*=\s*WPCPM_Sponsor_Event::change\(/', $queue_src ) ),
	array( true, 0, true, true )
);
ck(
	'the program\'s form is the planner\'s: its rows and its field readers, and no field, row or reader of the queue\'s own',
	array( class_exists( 'WPCPM_Sponsor_Event_Queue' ), substr_count( $queue_src, 'WPCPM_Sponsor_Event_Planner::render_fields(' ) > 0, substr_count( $queue_src, 'WPCPM_Sponsor_Event_Planner::posted_fields(' ) > 0, defined( 'WPCPM_Sponsor_Event_Queue::FIELD_DATE' ), defined( 'WPCPM_Sponsor_Event_Queue::FIELD_TITLE' ), preg_match( '/type="(date|time|number|url)"/', $queue_src ), method_exists( 'WPCPM_Sponsor_Event_Queue', 'posted_fields' ) ),
	array( true, true, true, false, false, 0, false )
);
ck( 'every query the stand-ins were sent is one they model, over every press the queue took', array( class_exists( 'WPCPM_Sponsor_Event_Queue' ), $GLOBALS['unmodeled'] ), array( true, array() ) );
ck(
	'the queue tells nobody on an event itself: the store\'s cancel does, once, so its own mailer to them and that mail\'s words are gone',
	array( class_exists( 'WPCPM_Sponsor_Event_Queue' ), method_exists( 'WPCPM_Sponsor_Event_Queue', 'mail_joiners' ), method_exists( 'WPCPM_Sponsor_Event_Queue', 'joiner_mail' ), strpos( $queue_src, 'An event you joined' ) ),
	array( true, false, false, false )
);
ck(
	'and it says when, how long and whose through the store\'s one copy of each: no when(), minutes() or sponsor_name() of its own, the time and the length through mail_values()',
	array( class_exists( 'WPCPM_Sponsor_Event_Queue' ), method_exists( 'WPCPM_Sponsor_Event_Queue', 'when' ), method_exists( 'WPCPM_Sponsor_Event_Queue', 'minutes' ), method_exists( 'WPCPM_Sponsor_Event_Queue', 'sponsor_name' ), substr_count( $queue_src, 'WPCPM_Sponsor_Event::mail_values(' ) > 0, substr_count( $queue_src, 'WPCPM_Sponsor_Event::length_line(' ) > 0, substr_count( $queue_src, 'WPCPM_Sponsor_Event::sponsor_name(' ) > 0 ),
	array( true, false, false, false, true, true, true )
);

$GLOBALS['run_complete'] = true;

// What every mail of the suite says, as the recording made before the event and term emails moved
// into the template registry holds it (bin/stubs/mail-recording.php).
foreach ( wpcpm_recording_checks( 'sponsor-event-mails-queue.json', 'ac387e8f3f67f232e2e50c5e32295d7d9508639f441132035a30720d9d0611ee', array_keys( array_filter( WPCPM_Mail_Catalog::emails(), static function ( $e ) { return WPCPM_Mail_Catalog::MODULE_EVENTS === $e['module']; } ) ), array(), "-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program." ) as $recorded_check ) {
	ck( $recorded_check[0], $recorded_check[1], $recorded_check[2] );
}

// The site's name as WordPress stores it, its "&" an entity, is handed over as stored and read back once
// by the registry: the mail to a sponsor's member for an approval, a return that still offers events, a
// return that no longer does, and a cancel names the site as a person typed it, and none shows "&amp;"
// in its subject or its body. After the recording's checks, which build no mail of their own.
$GLOBALS['site_name'] = 'Arts &amp; Sciences Dashboard';
ck(
	'a site name holding an "&", stored as an entity, reads as typed in the subject of the mail to a sponsor\'s member for an approval, a return, a return without events and a cancel, and "&amp;" is in none of their subjects or bodies',
	static function () {
		$events = array_values( array_filter( $GLOBALS['posts'], static function ( $post ) { return 'wpcpm_sponsor_event' === $post->post_type; } ) );
		$out    = array();
		$said   = array( 'note' => 'Move it to a weekday', 'told' => 3, 'pending' => 0, 'people' => 3, 'manager' => MANAGER, 'page' => 'https://site.example/sponsor-dashboard/' );
		foreach ( array(
			'event-approved'           => array( WPCPM_Sponsor_Event_Queue::MAIL_APPROVED, true ),
			'event-returned'           => array( WPCPM_Sponsor_Event_Queue::MAIL_RETURNED, true ),
			'event-returned-no-events' => array( WPCPM_Sponsor_Event_Queue::MAIL_RETURNED, false ),
			'event-canceled-with-note' => array( WPCPM_Sponsor_Event_Queue::MAIL_CANCELED, true ),
		) as $id => $kind ) {
			$mail       = ( new ReflectionMethod( 'WPCPM_Sponsor_Event_Queue', 'member_mail' ) )->invoke( null, $events[0], array( 'what' => $kind[0], 'offered' => $kind[1] ) + $said, $id, $GLOBALS['users'][ PARTNER_REP ] );
			$out[ $id ] = array( 0 === strpos( $mail['subject'], '[Arts & Sciences Dashboard] ' ), false === strpos( $mail['subject'] . $mail['body'], '&amp;' ) );
		}
		return $out;
	},
	array_fill_keys( array( 'event-approved', 'event-returned', 'event-returned-no-events', 'event-canceled-with-note' ), array( true, true ) )
);
unset( $GLOBALS['site_name'] );

// The cases the recording never sends, each as the code wrote it before the registry held the
// sentences, but for what it called an Administrator: an event with no limit says
// "Places: no limit"; the Sponsor Dashboard has no published page, so the approval ends at the call link
// and a return that still offers events ends at its sentence, with no address under it. Each press is
// made for a sponsor of its own, which has one member to mail.
$plain_mail = static function ( $n, $title, $places, $day, $method, $note, $page ) {
	$key                      = 'spoBBBB00000000' . sprintf( '%02d', $n );
	$GLOBALS['index'][ $key ] = array( 'record_id' => $key, 'name' => 'Plain Example ' . $n, 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' );
	a_user( 80 + $n, 'Pam Plainrep' . $n, array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => $key, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'Europe/Warsaw' ) );
	$id = attempt(
		static function () use ( $key, $n, $title, $places, $day ) {
			return WPCPM_Sponsor_Event::plan( $key, event_fields( utc( $day ), array( 'title' => $title, 'zone' => 'Europe/Warsaw', 'places' => $places ) ), 80 + $n, 'member' );
		}
	);
	$GLOBALS['sent']     = array();
	$GLOBALS['no_pages'] = ! $page;
	press( $method, decision( $id, $note ), MANAGER );
	$GLOBALS['no_pages'] = false;
	return array_map( static function ( $mail ) { return array( $mail['to'], $mail['subject'], $mail['body'] ); }, $GLOBALS['sent'] );
};
ck(
	'an approval for an event with no limit says "Places: no limit", and names the card that holds the event under its sentence while the Sponsor Dashboard is published',
	$plain_mail( 1, 'Plain no limit', 0, '2026-12-21 10:00', 'handle_approve', null, true ),
	array(
		array(
			'pam.plainrep1@people.example',
			'[WordPress Education] Your event is approved: Plain no limit',
			"An Administrator approved your company's event. The calendar file attached adds it to your calendar.\n\n"
				. "Title: Plain no limit\nWhen: December 21, 2026 11:00 am to 12:30 pm (Europe / Warsaw)\nLength: 90 minutes\nPlaces: no limit\nCall link: https://call.example/room?pwd=a%2Fb&id=7\n\n"
				. "Your event is on the Events card of the Sponsor Dashboard:\nhttps://site.example/sponsor-dashboard/#wpcpm-sponsor-events",
		),
	)
);
ck(
	'with no published page for the Sponsor Dashboard, the approval ends at the call link: no line that names a card, no empty address',
	$plain_mail( 2, 'Plain no page', 25, '2026-12-23 10:00', 'handle_approve', null, false ),
	array(
		array(
			'pam.plainrep2@people.example',
			'[WordPress Education] Your event is approved: Plain no page',
			"An Administrator approved your company's event. The calendar file attached adds it to your calendar.\n\n"
				. "Title: Plain no page\nWhen: December 23, 2026 11:00 am to 12:30 pm (Europe / Warsaw)\nLength: 90 minutes\nPlaces: 25\nCall link: https://call.example/room?pwd=a%2Fb&id=7",
		),
	)
);
ck(
	'and a return that still offers events ends at its sentence, "You can plan it again on your dashboard.", with no address under it',
	$plain_mail( 3, 'Plain returned', 25, '2026-12-24 10:00', 'handle_return', 'Please pick a weekday.', false ),
	array(
		array(
			'pam.plainrep3@people.example',
			'[WordPress Education] Your event was returned: Plain returned',
			"An Administrator returned your company's event with this note:\n\nPlease pick a weekday.\n\n"
				. "Title: Plain returned\nWhen: December 24, 2026 11:00 am to 12:30 pm (Europe / Warsaw)\n\n"
				. 'The event is canceled. You can plan it again on your dashboard.',
		),
	)
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
