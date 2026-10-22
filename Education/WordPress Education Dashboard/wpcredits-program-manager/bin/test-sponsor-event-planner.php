<?php
/**
 * The Sponsor Dashboard's event planner (1.117.0): what the Events card draws of a sponsor's
 * co-hosted event, and the three presses that plan, change and cancel it.
 *
 * What is pinned here and why:
 *
 * - The wiring: the planner hooks its three presses as admin-post actions, and the tier card hooks
 *   Talk to your program contact alone, with no Plan it now left to hook. The loader and
 *   uninstall.php require the planner, and the Sponsors module boots it.
 * - The order every press keeps: the nonce keyed to the sponsor first, so a stale page is stopped
 *   before anything is read and a stranger's refusal is not even counted; then the roster's claim
 *   of `ACT_PLAN_EVENT`, which refuses a stranger and meters the refusal; then the launch switch and
 *   the sponsor's row; for a plan and a change, whether the sponsor holds the planner, before the
 *   form is read, so a Backer's plan looks its call link up nowhere, and whether the title or the
 *   description would lose words; then ten presses an hour per account, the three presses
 *   together, the eleventh leaving with the ceiling's sentence before the store is asked, while
 *   another account keeps its own ten, whether it is a second member of the same sponsor or another
 *   sponsor's member, and a press refused for lost words spending none of them; only then the
 *   store, and a press looks its call link up once, though the form and the store each check it
 *   (core's check resolves the host in DNS on a site). A member naming another sponsor plans for
 *   its own; a change or a cancel reaches only an event of a sponsor the person plans for, so
 *   another sponsor's member can do neither, whichever sponsor the form names; and a program
 *   manager who changes or cancels through the switcher lands on the event's own sponsor, whose
 *   contact is told.
 * - Change is drawn, and taken, only while the sponsor holds the planner: a sponsor set to Paused,
 *   or one whose term a manager ended early, keeps Cancel alone, and a change it posts anyway is
 *   refused as a tier without the co-hosted event, its link looked up nowhere.
 * - What a press posts reaches the store as typed: the day and the time read on the planner's own
 *   clock (New York here, while the site keeps Warsaw), a quote, an ampersand and a backslash in
 *   the title, the description's line break, and a call link whose query holds a percent-encoded
 *   character, which the text cleaner would strip.
 * - Every outcome leaves on the Events card with a sentence of the planner's own: planned (which
 *   waits for the program's OK and promises no confirmation), changed, canceled (which claim no mail
 *   nobody sent), "Nothing changed." in the profile card's words and notice class for a change that
 *   alters nothing, each refusal the allowance and the store can give (the two waits by the term the
 *   sponsor's row holds, in the store's own words; a start after the term's last day or before a
 *   renewed term's first day with that day and the site's clock, which the store names and the
 *   dashboard's flash fills into the same words, the west of the site's last evening among them),
 *   the form's refusal with the fields' sentences as its detail (whole sentences, inside the three
 *   hundred characters the dashboard's flash keeps), and a held lock. A refused press writes nothing
 *   and mails nobody.
 * - The program contact is mailed on every plan and cancel, and every change that alters something,
 *   in the `sponsor-event` context: the assigned manager, else the addresses the Interest mail
 *   setting holds. The subject names the sponsor and what it did; the body says a program manager
 *   did it on the sponsor's behalf when one did, and gives the title, the day and the time on the
 *   recipient's own clock (the assigned manager keeps Tokyo's here) or the site's for a bare
 *   address, with the zone named, the length, the places, the call link, the description and,
 *   while the event waits, where to approve it. It counts the people who joined and names none.
 * - A change or a cancel of an event the program approved tells, in this order, the people on it
 *   (the store does), every live member of the sponsor, the member who pressed included, with the
 *   calendar file that updates or removes the entry their approval mail made, which names that
 *   member as its attendee, and then the program contact; the members' mail says a program manager
 *   acted on the sponsor's behalf when one did.
 *   A waiting event's change or cancel tells the contact alone. The outcome adds "Everyone who
 *   joined has been told." only when the store told every person on the event in the press: not
 *   for an event nobody is on, not when one of the mails was refused, and not while an account that
 *   is gone is still on the event, since nobody can tell it. With fifty-five on the event, the
 *   store tells fifty in the press and leaves five to its drain, and the outcome of a change or a
 *   cancel says "Everyone who joined is being told." instead.
 * - A change that alters nothing, the same form pressed again or saved by a program manager on
 *   another clock, says "Nothing changed." and writes and mails nothing; one of the description or
 *   the places alone reaches the member and the contact and nobody on the event, and the outcome
 *   claims nothing about them.
 * - What the planner draws: nothing while the launch switch is off; the sponsor's event still to
 *   take place in the viewer's own zone, its state, how many joined (never who), Change with every
 *   box filled on the viewer's clock and Cancel behind a question; the form when a plan is allowed,
 *   with the note a return came back with above it; the one sentence in its place, in the flash's
 *   words: while a term runs, the event for this term is planned already; with no term, the next
 *   event can be planned once this one has taken place; and once the term's event is held, it has
 *   taken place already; nothing in its place for a sponsor that holds no tier with events, whose
 *   approved event reads as no longer offered; the events that took place, read only, each on its
 *   day on the viewer's clock. The date box runs from the site's today, the day the store reads a
 *   start on, to the term's last day while a term runs, a renewed term's first day being no floor
 *   for a plan, since the term it renewed still runs; a change's box for an event of the renewed
 *   term starts on that first day when it is the later, where the store keeps the event; and a
 *   change's box never starts after the day the event is drawn on, nor ends before it, so the form
 *   as drawn can always be saved. The link box asks for an http or https address, so the browser
 *   says it before a press is refused, and every box takes the sponsor stylesheet's width cap. The
 *   description box says it is shown on the Partners page once the event is approved, and
 *   that the call link goes in its own box. Every value is escaped, the description's quote and
 *   ampersand included.
 * - A refused form comes back with what was typed: a plan or a change refused for its fields, or by
 *   the store for a day and a time that have passed or a lock held, keeps what it posted, as the
 *   request readers read it, with the sponsor and the event it was for, for the person who pressed;
 *   the form it came from is drawn with every one of the seven values once, each escaped as a stored
 *   value is, the call link as typed with its markup printed as text, and a reload draws it as
 *   before. The sponsor's other member, another sponsor's form a program manager looks at through
 *   the switcher (which drops it unread, so the first sponsor's form is drawn as before afterwards),
 *   and another event's Change form never draw it; a sponsor that no longer holds the planner keeps
 *   nothing, and a plan that succeeds keeps nothing and drops what the refusal before it kept. The
 *   outcomes that keep it are pinned by name. Through the plugin's own flash, a backslash in the
 *   title, two in a row in the call link and a shrug over two lines in the description come back
 *   exactly as typed, in the Plan form and in the Change form. The Change form's fold is drawn open
 *   when its form comes back with what was typed, and closed without it, on the next draw and while
 *   what was kept is another event's; the Plan form sits in no fold. What is kept is bounded by
 *   what a box lets a person type: a longer title, description, link, day or number of places comes
 *   back empty while the others come back as typed, one at its limit comes back whole, and a line
 *   break a description posts as two bytes counts once. The count is the store's: an entity the
 *   text cleaner wrote for a < that opens no tag, and for the quote marks and the ampersand after
 *   it, counts as the one character it stands for, so a title of 120 characters as typed comes
 *   back whole though the cleaner made it longer, while the call link, read as typed, counts as it
 *   is; the keeper keeps what the store accepts and drops what it refuses. A Change form's title
 *   and description boxes are as long as their drawn limit, three more for each > the box holds
 *   as `&gt;`, and a refused title or description is kept up to that limit, so an edit that writes
 *   the held `&gt;` out and fills the box comes back as typed. That bound is the
 *   store's count, entities decoded and a line break once, not the box's in bytes: entity text the
 *   store accepts, up to six times the limit in bytes, is kept as well, while the four short boxes
 *   count what was posted. What comes back is drawn as it was typed: the title box keeps an entity
 *   it is given, and the description box reads back the entities the text cleaner wrote for a < and
 *   for the quote marks and the ampersand after it, so a person sees "2 < 3" and not `2 &lt; 3`, in
 *   a kept form and in a stored event's Change form alike, and what the box posts back is cleaned
 *   to what the first press kept. A sponsor member holds no unfiltered_html, so WordPress runs kses
 *   on their title and description when the event is stored (bin/stubs/member-kses.php, on for
 *   every check): every bare & is stored as `&amp;` and every bare > as `&gt;`, wherever they
 *   fall. Their Change form shows "Q&A with our team", "Ages > 5 welcome" and "Q&A
 *   first: is 2 < 3? "Yes"" exactly as typed, in the title box and in the description box, a
 *   description of 1,999 characters made of "Q&A " within the box's maxlength of 2,000, and the
 *   boxes posted back as drawn change nothing the store says; an administrator's own event, for
 *   whom no kses runs, is drawn the same, and a description of theirs stored as `x < y > z &gt; w`
 *   is drawn into its box exactly as stored: the `&gt;` after a raw < stays as it is. kses also
 *   reads a < the cleaner left, with the words up to the next >, as a tag and drops it, so for a
 *   member the store writes each < and > the cleaner left as `&lt;` and `&gt;` first: "Ages 8 < 12
 *   welcome, adults > 18 pay", "Use 2 < 3 then 5 > 4 in the demo" and "We <3 mentors. Ages 8 < 12
 *   welcome, adults > 18 pay" are stored with every word, planned or changed to, and come back
 *   into both boxes exactly as typed, and so does a < at the end of a line with a > after it,
 *   the line break one byte or two; the boxes posted back change nothing. What 1.123.2 stored is
 *   drawn as it was then: `We &lt;3 our mentors &gt; all else` keeps its `&gt;`, after a < the
 *   cleaner would take for a tag's, `Ages 8  18 pay` shows what kses left of its words, and a
 *   title of that time is drawn as typed. Words the cleaner took a typed tag out of, "We <3 our
 *   <b>team</b> > all", as a release before this one stored them, show the > after the <3 as
 *   `&gt;` in both boxes, a member's and an administrator's alike, so posting them back unedited
 *   loses nothing. Each box's maxlength is its limit and three for each > it draws as `&gt;`: a
 *   title of 120 characters drawn at 122 has 129, a description of 2,000 drawn at 2,002 has 2,009,
 *   and a box with nothing held, line breaks and all, keeps its limit. A "<" a tab follows comes
 *   back as typed, as a space or a line break.
 * - The mails give a member's words as typed: a title and a description holding a "<3", a "<" and
 *   a ">", an ampersand, quotes and an apostrophe, the description over two lines, are stored as
 *   entities alone, and reach the program contact's mail of a plan, a change and a cancel and the
 *   members' mail of a change and a cancel as typed, every entity read back, in the Title line,
 *   under Description and in the members' subject, which goes to the mail layer as plain text, its
 *   cleaner never reading the "<3" as a tag.
 * - A title or a description the cleaner would take words from, "Ages 8 <12 welcome, adults >18
 *   pay" or a "%" with two letters after it, is refused before the form is read for the store: no
 *   link is looked up, nothing is stored, logged, mailed, sent as a calendar file or left for the
 *   drain, an approved event with people on it included. The refusal names the box in the words of
 *   the form's button, Plan the event or Save the changes, the title first when both lose words.
 *   The form comes back once with exactly what was typed, the title in the room its box had, the
 *   Change form's fold open: to the member who pressed and in that form alone, never to the
 *   sponsor's other member, a program manager looking at the same planner, another sponsor's form
 *   a program manager looks at, or the next event's Change form, and a refusal never drawn is gone
 *   once the form is posted again. "Ages 8 < 12 welcome, adults > 18 pay" is planned and changed to
 *   with every word, stored and mailed. A Change form posted back as drawn changes nothing, and an
 *   event whose title holds a line break and words the cleaner would take, as no form of the plugin
 *   stores them, draws the break as a space and keeps both boxes as it held them, the start moved
 *   or not; edited, such a title is asked as any typing is. A description held with LF breaks and
 *   posted back with CR LF ones is unedited too. A kept description that begins with a line break,
 *   and a kept title holding an entity typed out, come back exactly, and a kept description fills
 *   the room its box was drawn with, past the limit by each held `&gt;`.
 * - Extra events for one term, granted on the Sponsors screen's Tier box: the form says under its
 *   heading how many events the term allows, the tier's one and those granted; while one of them is
 *   still to take place and another can follow it, the card in the form's place, the flash of a
 *   plan pressed anyway and the store's refusal all say the next can be planned once this one has
 *   taken place; once it has taken place the form is back; while the one still to take place is the
 *   last the term holds, all three say the events for this term are planned already; and once that
 *   one has taken place, that they have taken place already. A sponsor granted none, or granted a
 *   number for another day, reads as before.
 * - The house rules: no em or en dash, no sponsor ID compared with ===, no Airtable, and the
 *   planner writes nothing of its own: the store writes, the planner reads the request, draws, and
 *   leaves a refused form's typing on the flash.
 *
 * WordPress is the events suite's small store in memory (bin/test-sponsor-events.php): posts, their
 * meta as repeated rows, the slash pair core applies, options with `add_option()` as the lock's
 * test-and-set and the table read a lock is taken after (bin/stubs/options-table.php), and the
 * clock, which the store reads through its seam; `wp_date()` honors the zone it is given; the cron
 * schedule's single events are kept, and every call to core's link check is counted. The request is
 * posted slashed, as WordPress hands `$_POST` to a handler. The classes the planner asks are the
 * plugin's own: the store, the roster, the policy, the sponsor memberships, the request reader, the
 * tier ladder, the report's period, the zone readers, the sessions' date reader, the calls' range
 * formatter, the calendar builder, the interests' road to the program contact, and the tier
 * card. The sponsors store, the audit trail, the settings, the ceiling, the mail layer, the flash
 * and the dashboard's `leave()` are stood in for the calls made; the flash's stand-in keeps each
 * person's channels apart and remembers what a request took, as the real one does, each press and
 * each draw a request of its own, and `leave()` puts its outcome on the dashboard's channel, which
 * each draw takes first, as the page does. The checks that pin what comes back of a backslash turn
 * the stand-in over to the plugin's own flash, loaded through a rewrite, over user meta that
 * unslashes what it writes as core's does, each with a person of its own. The ceiling's stand-in
 * counts per key with no window, so each press is made in an hour of its own, the presses' count
 * starting again, but where a check says its presses share one; the mail layer's stand-in builds
 * each message for its account, as the real one does, and keeps each calendar file's text before
 * removing it. Every check runs inside its own guard, so a planner that is missing fails each check
 * rather than the run.
 *
 * Run from the plugin root:  php bin/test-sponsor-event-planner.php
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
$GLOBALS['mail']      = array();
$GLOBALS['ics']       = array();
$GLOBALS['refuse_to'] = array();
$GLOBALS['buckets']   = array();
$GLOBALS['nonces']    = array();
$GLOBALS['nonce_ok']  = true;
$GLOBALS['left']      = null;
$GLOBALS['unmodeled'] = array();
$GLOBALS['lookups']   = array();
$GLOBALS['cron']      = array();
$GLOBALS['flash']     = array();

/* ---- WordPress, stood in for the parts the planner and the store touch ------- */

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
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_content = '', $post_name = '', $post_author = 0, $post_parent = 0;
}

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '' ) { $this->ID = (int) $id; $this->display_name = (string) $name; }
	public function exists() { return $this->ID > 0; }
}

/** A press that reached the dashboard's `leave()`. */
class Planner_Test_Left extends Exception {}

/** A press that died on the nonce screen. */
class Planner_Test_Die extends Exception {}

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
function apply_filters( $hook, $value, ...$args ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_title( $title ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' ); }

// Core's two text cleaners as _sanitize_text_fields() in wp-includes/formatting.php writes them: a "<"
// that opens no tag is written as "&lt;", with everything after it escaped (wp_pre_kses_less_than()),
// tags go, percent-encoded octets go (which is why a call link must never pass through them), only the
// text field folds its line breaks, and the text area keeps each one as a browser posts it, two bytes.
function wp_check_invalid_utf8( $text, $strip = false ) { return (string) $text; }
function wp_pre_kses_less_than_callback( $matches ) { return false === strpos( $matches[0], '>' ) ? htmlspecialchars( $matches[0], ENT_QUOTES, 'UTF-8', false ) : $matches[0]; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ) ); }
function _sanitize_text_fields( $text, $keep_newlines = false ) {
	if ( is_object( $text ) || is_array( $text ) ) { return ''; }
	$filtered = wp_check_invalid_utf8( (string) $text );
	if ( false !== strpos( $filtered, '<' ) ) {
		$filtered = wp_strip_all_tags( preg_replace_callback( '%<[^>]*?((?=<)|>|$)%', 'wp_pre_kses_less_than_callback', $filtered ) );
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}
	if ( ! $keep_newlines ) { $filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered ); }
	$filtered = trim( $filtered );
	$found    = false;
	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) { $filtered = str_replace( $match[0], '', $filtered ); $found = true; }
	return $found ? trim( preg_replace( '/ +/', ' ', $filtered ) ) : $filtered;
}
function sanitize_text_field( $text ) { return _sanitize_text_fields( $text, false ); }
function sanitize_textarea_field( $text ) { return _sanitize_text_fields( $text, true ); }
function is_email( $value ) { return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : false; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function home_url( $path = '' ) { return 'https://site.example' . $path; }
function admin_url( $path = '' ) { return 'https://site.example/wp-admin/' . $path; }

/**
 * Core's check of an address before a request is made to it, without the host's lookup, which on
 * a site resolves the host in DNS: every call is kept, so a check can count the lookups a press
 * makes.
 */
function wp_http_validate_url( $url ) {
	$GLOBALS['lookups'][] = $url;
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
		throw new Planner_Test_Die( 'The link you followed has expired.' );
	}
	return 1;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />'; }

/** A value as the database hands it back: text, whatever went in, and an array whole. */
function db_value( $value ) {
	if ( is_array( $value ) ) { return $value; }
	if ( is_bool( $value ) ) { return $value ? '1' : ''; }
	return null === $value ? '' : (string) $value;
}

function wp_insert_post( $args, $wp_error = false ) {
	$args = wpcpm_stub_kses_post_args( wp_unslash( (array) $args ) );
	$post = new WP_Post();
	foreach ( $args as $key => $value ) {
		if ( property_exists( $post, $key ) ) { $post->$key = $value; }
	}
	$post->ID                      = $GLOBALS['next_id']++;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
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

/** Enough of `get_posts()`: a type, a status or a list of them, a meta query, a limit that defaults to five, and IDs. */
function get_posts( $args = array() ) {
	$types  = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'post' );
	$status = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
	$out    = array();
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

// The calendar file's scratch space: this run's own folder (bin/stubs/temp-dir.php).
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/mail-recording.php';
/** This run's folder, or, while a check says the site cannot write one, a folder that is not there. */
function get_temp_dir() { return empty( $GLOBALS['no_temp'] ) ? wpcpm_test_temp_dir() : sys_get_temp_dir() . '/wpcpm-test-no-such-folder-' . getmypid() . '/'; }
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return substr( bin2hex( random_bytes( 16 ) ), 0, (int) $length ); }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0700, true ); }
function wp_delete_file( $path ) { if ( is_file( $path ) ) { unlink( $path ); } }
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name ); }
function wp_specialchars_decode( $text, $quotes = ENT_NOQUOTES ) { return htmlspecialchars_decode( (string) $text, ENT_QUOTES ); }

// The accounts, and the one capability of the program's own (bin/stubs/caps.php).
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( 'id' === $field && $user->ID === (int) $value ) || ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) ) { return $user; }
	}
	return false;
}
function get_userdata( $id ) { return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false; }
/** The accounts holding a meta value, as the sponsor memberships ask for their holders. */
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['meta_key'] ) && (string) get_user_meta( $user->ID, $args['meta_key'], true ) !== (string) $args['meta_value'] ) { continue; }
		$out[] = $user;
	}
	return $out;
}
/** Core's warm-up of many accounts in one query: nothing to warm here. */
function cache_users( $user_ids ) {}
function wp_get_current_user() { return isset( $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ (int) $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
// As core writes it: `update_metadata()` passes the value it is handed through `wp_unslash()`.
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ (int) $id ][ $key ] = wp_unslash( $value ); return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ (int) $id ][ $key ] ); return true; }
require_once __DIR__ . '/stubs/caps.php';
// What core does to a title and a text for a person without `unfiltered_html`, a sponsor member (bin/stubs/member-kses.php).
require_once __DIR__ . '/stubs/member-kses.php';

/* ---- the collaborators the planner reads beside the plugin's own classes ------ */

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

/** The refusal meter's buckets, counted per key as the real ceiling counts them. */
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) { $n = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; if ( $n + $amount > $limit ) { return false; } $GLOBALS['buckets'][ $key ] = $n + $amount; return true; }
	public static function count( $key, $window ) { return isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; }
	public static function key( ...$parts ) { return implode( ':', $parts ); }
}

/** The sponsors store, for the calls made: the key's shape, a row, Approved, and the assigned manager as the real store answers one. */
class WPCPM_Sponsors_Index {
	const POST_STATUSES   = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );
	const STATUS_APPROVED = 'Approved';
	public static function is_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', $value ); }
	public static function rows() { return $GLOBALS['index']; }
	public static function row( $record ) { $record = trim( (string) $record ); return self::is_key( $record ) && isset( $GLOBALS['index'][ $record ] ) ? $GLOBALS['index'][ $record ] : null; }
	public static function has( $record ) { return null !== self::row( $record ); }
	public static function manager_of( $record ) {
		$row = self::row( $record );
		if ( ! is_array( $row ) || '' === (string) $row['manager'] || ! ctype_digit( (string) $row['manager'] ) ) { return null; }
		$user = get_userdata( (int) $row['manager'] );
		return false === $user ? null : array( 'name' => $user->display_name, 'email' => $user->user_email, 'calendly' => '' );
	}
}

/** The sponsors module's trail: every row kept whole. */
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';
	const EVIDENCE_CACHE = 'cache';
	const EVIDENCE_LIVE  = 'live';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}

/**
 * The mail layer: every message built for its account, as the real layer builds it (an account
 * given by ID is looked up first), and kept, never sent. A calendar file handed over is read into
 * `$GLOBALS['ics']` beside the message's place in the list and then removed, as the real layer's
 * cleanup removes it. A message to an address in `$GLOBALS['refuse_to']` is built and refused, as
 * a mail server's refusal reaches the real layer.
 */
class WPCPM_Mail {
	public static function site_name() { return isset( $GLOBALS['site_name'] ) ? (string) $GLOBALS['site_name'] : 'WordPress Education'; }
	public static function sender() { return array( 'email' => 'wordpress@site.example', 'name' => 'WordPress' ); }
	public static function send( $user, $context, $build ) {
		$user = $user instanceof WP_User ? $user : get_user_by( 'id', (int) $user );
		if ( ! $user instanceof WP_User ) {
			return false;
		}
		$mail  = call_user_func( $build, $user );
		$files = array();
		foreach ( isset( $mail['attachments'] ) ? (array) $mail['attachments'] : array() as $path ) {
			$files[] = is_file( (string) $path ) ? str_replace( "\r\n ", '', (string) file_get_contents( (string) $path ) ) : null;
		}
		foreach ( isset( $mail['cleanup'] ) ? (array) $mail['cleanup'] : array() as $path ) {
			WPCPM_ICS::cleanup( (string) $path );
		}
		if ( in_array( $user->user_email, $GLOBALS['refuse_to'], true ) ) {
			return false;
		}
		$GLOBALS['ics'][ count( $GLOBALS['mail'] ) ] = $files;
		$GLOBALS['mail'][]                           = array( 'to' => $user->user_email, 'context' => $context, 'mail' => $mail );
		wpcpm_record_mail( $user->user_email, $context, isset( $mail['subject'] ) ? $mail['subject'] : '', isset( $mail['body'] ) ? $mail['body'] : '', isset( $mail['headers'] ) ? (array) $mail['headers'] : array(), count( $files ) );
		return true;
	}
	public static function send_to( $email, $context, $build, $locale = '' ) {
		$mail              = call_user_func( $build, null );
		$GLOBALS['mail'][] = array( 'to' => (string) $email, 'context' => $context, 'mail' => $mail );
		wpcpm_record_mail( (string) $email, $context, isset( $mail['subject'] ) ? $mail['subject'] : '', isset( $mail['body'] ) ? $mail['body'] : '', isset( $mail['headers'] ) ? (array) $mail['headers'] : array(), isset( $mail['attachments'] ) ? count( (array) $mail['attachments'] ) : 0 );
		return true;
	}
}

/** Every manager the Interest mail setting names, one message each, as the real notifier sends them. */
class WPCPM_Institutions {
	public static function notify_managers( $context, $build, $key = 'agreement_notify' ) {
		$sent = 0;
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) WPCPM_Settings::get_value( $key, '' ) ) ) ) as $address ) {
			$mail              = call_user_func( $build, null );
			$GLOBALS['mail'][] = array( 'to' => $address, 'context' => $context, 'mail' => $mail, 'via' => $key );
			wpcpm_record_mail( $address, $context, isset( $mail['subject'] ) ? $mail['subject'] : '', isset( $mail['body'] ) ? $mail['body'] : '', isset( $mail['headers'] ) ? (array) $mail['headers'] : array(), isset( $mail['attachments'] ) ? count( (array) $mail['attachments'] ) : 0 );
			++$sent;
		}
		return $sent;
	}
}

/** The dashboard's one door out: what a press left with, then the end of the request. */
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	/**
	 * What a press left with; the values a sentence names follow the detail when there are any. The
	 * outcome goes on the dashboard's own channel, as the real one puts it, beside whatever the
	 * planner kept, and the page the press goes back to takes it first (`draw()`).
	 */
	public static function leave( $status, $card, $record = '', $detail = '', $args = array() ) {
		$GLOBALS['left'] = array_merge( array( $status, $card, $record, $detail ), array() === $args ? array() : array( $args ) );
		WPCPM_Flash::set( self::FLASH, array( 'status' => (string) $status, 'card' => (string) $card, 'detail' => (string) $detail ) );
		throw new Planner_Test_Left( (string) $status );
	}
}

/** Where the program approves an event: a page, unless a check says the site has none published. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return empty( $GLOBALS['no_pages'] ) ? 'https://site.example/administrator-dashboard/' : ''; }
}

/**
 * The flash, kept for each person apart as the real one keeps it in their user meta: what a press
 * sets waits for the next page that person loads, and what a page takes is remembered for the rest
 * of that page, so every form it draws reads the same value. The memory is forgotten at the start
 * of each press and each draw (`press()`, `draw()`), as a new request starts with nothing taken.
 */
class WPCPM_Flash {
	/** @var array<string, mixed> Person and channel => what this request took. */
	public static $taken = array();
	/** @var bool Whether `set()` and `take()` are the plugin's own (`through_real_flash()`). */
	public static $real = false;
	public static function set( $channel, $value, $user_id = 0 ) {
		if ( self::$real ) {
			WPCPM_Flash_Real::set( $channel, $value, $user_id );
			return;
		}
		$user = $user_id ? (int) $user_id : get_current_user_id();
		$GLOBALS['flash'][ $user ][ (string) $channel ] = $value;
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

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/class-wpcpm-ics.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-group-sessions.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tier-card.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
// The template registry, which every event and term email is filled from since 1.123.9.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php';

// Loaded only when it is there, so that before it exists every check below fails on its own.
$planner_file = __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-planner.php';

if ( file_exists( $planner_file ) ) {
	require_once $planner_file;
}

// A warning, a notice or a deprecation is an error here, raised inside the guard of the check that
// caused it, so the planner cannot pass a check while complaining on the way.
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
 * One check. `$got` may be a closure, run here inside a guard: a planner that is missing or throws
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
 * A press, as a person makes it: signed in as `$uid`, the form's fields posted slashed as WordPress
 * hands them over. What it left with: `status`, `card`, `record`, `detail`, or `died` and the
 * sentence the nonce screen shows.
 *
 * @param string $method `handle_plan`, `handle_change` or `handle_cancel`.
 * @param array  $fields What the form posts.
 * @param int    $uid    Who presses.
 * @return array
 */
function press( $method, array $fields, $uid ) {
	$GLOBALS['uid']  = (int) $uid;
	$GLOBALS['left'] = null;
	$_POST           = wp_slash( $fields );

	WPCPM_Flash::new_request();

	// An hour of its own for every press but those of a check about the ceiling itself.
	if ( empty( $GLOBALS['same_hour'] ) ) {
		foreach ( array_keys( $GLOBALS['buckets'] ) as $key ) {
			if ( 0 === strpos( (string) $key, 'sponsor-event-press:' ) ) { unset( $GLOBALS['buckets'][ $key ] ); }
		}
	}

	try {
		call_user_func( array( 'WPCPM_Sponsor_Event_Planner', $method ) );
	} catch ( Planner_Test_Left $e ) {
		return $GLOBALS['left'];
	} catch ( Planner_Test_Die $e ) {
		return array( 'died', $e->getMessage() );
	}

	return array( 'fell through' );
}

/** The planner's markup for a sponsor, as one viewer sees it, drawn from the row and the tier the card reads. */
function draw( $record, $uid ) {
	$GLOBALS['uid'] = (int) $uid;
	$row            = WPCPM_Sponsors_Index::row( $record );
	$row            = is_array( $row ) ? $row : array();

	// The dashboard takes its own channel at the top of the page, before any card is drawn.
	WPCPM_Flash::new_request();
	WPCPM_Flash::take( WPCPM_Sponsors_Dashboard::FLASH );
	ob_start();

	try {
		WPCPM_Sponsor_Event_Planner::render( $record, $row, WPCPM_Sponsor_Tiers::effective( $row ), $GLOBALS['users'][ (int) $uid ] );
	} finally {
		$html = (string) ob_get_clean();
	}

	return $html;
}

/** The planning form's fields, the Partner's by default, with any of them replaced. */
function form( $record, array $over = array() ) {
	return array_merge(
		array(
			'wpcpm_sponsor'            => $record,
			'wpcpm_sevent_date'        => '2026-11-10',
			'wpcpm_sevent_time'        => '14:00',
			'wpcpm_sevent_minutes'     => '90',
			'wpcpm_sevent_places'      => '40',
			'wpcpm_sevent_title'       => 'Partners "Q&A" in C:\\talks',
			'wpcpm_sevent_description' => "What we \"cover\" & more.\nBring questions.",
			'wpcpm_sevent_link'        => 'https://call.example/room?pwd=a%2Fb&id=7',
		),
		$over
	);
}

/**
 * An event's fields as the store takes them, for asking the store itself what it refuses and in
 * which words.
 *
 * @param int $start The start, a UTC timestamp.
 * @return array
 */
function store_fields( $start ) {
	return array(
		'start'       => (int) $start,
		'minutes'     => 60,
		'places'      => 0,
		'title'       => 'Asked of the store',
		'description' => 'Asked of the store itself.',
		'link'        => 'https://call.example/store',
		'zone'        => 'Europe/Warsaw',
	);
}

/** The IDs of the sponsor events the store holds, oldest first. */
function event_ids() {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'wpcpm_sponsor_event' === $post->post_type ) { $ids[] = (int) $post->ID; }
	}
	return $ids;
}

/** Everything a refused press must leave as it was: the posts, their meta, the trail and the mail. */
function store_state() { return array( $GLOBALS['posts'], $GLOBALS['pmeta'], $GLOBALS['audit'], $GLOBALS['mail'] ); }

/** The last audit row. */
function last_row() { return $GLOBALS['audit'] ? end( $GLOBALS['audit'] ) : array(); }

/** Who each kept message went to, in the order they were sent. */
function mailed_to() { return array_map( static function ( $sent ) { return $sent['to']; }, $GLOBALS['mail'] ); }

/** The one kept message to an address, with its calendar file's text as `ics`, or an empty one. */
function mail_for( $address ) {
	foreach ( $GLOBALS['mail'] as $index => $sent ) {
		if ( $address === $sent['to'] ) {
			return array_merge( $sent['mail'], array( 'ics' => isset( $GLOBALS['ics'][ $index ][0] ) ? (string) $GLOBALS['ics'][ $index ][0] : '', 'context' => $sent['context'] ) );
		}
	}
	return array( 'subject' => '', 'body' => '', 'ics' => '', 'context' => '' );
}

/* ---- the fixture ------------------------------------------------------------ */

const MANAGER      = 1;
const PARTNER_REP  = 2;
const CHAMPION_REP = 3;
const PAUSED_REP   = 4;
const BACKER_REP   = 5;
const DEPARTED_REP = 6;
const CONTACT      = 7;
const LAPSED_REP   = 8;
const STRANGER     = 9;
const JOINER       = 40;
const JOINER_TWO   = 41;

const PARTNER  = 'spoAAAA0000000001'; // Partner, the term runs to 31 January 2027, its program manager assigned.
const CHAMPION = 'spoAAAA0000000002'; // Champion, no term, nobody assigned.
const PAUSED   = 'spoAAAA0000000003'; // Champion, set to Paused.
const BACKER   = 'spoAAAA0000000004'; // Backer: no events.
const LAPSED   = 'spoAAAA0000000005'; // Champion, the term runs to 31 January 2027 until a manager ends it early.
const GONE     = 'spoAAAA0000000009'; // A sponsor the store no longer holds.

function a_user( $id, $name, array $roles, array $meta = array() ) {
	$user                    = new WP_User( $id, $name );
	$user->roles             = $roles;
	$user->user_email        = strtolower( str_replace( ' ', '.', $name ) ) . '@people.example';
	$GLOBALS['users'][ $id ] = $user;
	$GLOBALS['umeta'][ $id ] = $meta;
	return $user;
}

a_user( MANAGER, 'Mira Manager', array( 'administrator' ) );
// The Partner's program contact is a program manager too, on Tokyo's clock: the mail about the
// Partner's event reaches them in their zone, while the manager who acts keeps the site's.
a_user( CONTACT, 'Mo Contact', array( 'administrator' ), array( 'wpcpm_timezone' => 'Asia/Tokyo' ) );
// The Partner's member keeps New York's clock, the site Warsaw's: what the form reads and what the
// mail prints are then two different clocks.
a_user( PARTNER_REP, 'Pat Partnerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'America/New_York' ) );
a_user( CHAMPION_REP, 'Chris Championrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => CHAMPION, 'wpcpm_sponsor_active' => 1 ) );
a_user( PAUSED_REP, 'Paula Pausedrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PAUSED, 'wpcpm_sponsor_active' => 1 ) );
a_user( BACKER_REP, 'Bea Backerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => BACKER, 'wpcpm_sponsor_active' => 1 ) );
a_user( DEPARTED_REP, 'Dora Departedrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => GONE, 'wpcpm_sponsor_active' => 1 ) );
a_user( LAPSED_REP, 'Lee Lapsedrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => LAPSED, 'wpcpm_sponsor_active' => 1 ) );
a_user( STRANGER, 'Stan Stranger', array( 'subscriber' ) );
a_user( JOINER, 'Zelda Privacywalk', array( 'wpcpm_student' ) );
a_user( JOINER_TWO, 'Yuri Privacywalk', array( 'wpcpm_student' ) );

$GLOBALS['manage']   = array( MANAGER, CONTACT );
// A program manager is an administrator, who holds `unfiltered_html`: what they write is stored as typed; a sponsor member's is not.
$GLOBALS['grants']   = array( MANAGER => array( 'unfiltered_html' ), CONTACT => array( 'unfiltered_html' ) );
$GLOBALS['settings'] = array(
	'sponsor_tiers_live' => true,
	'sponsor_notify'     => 'events@program.example',
);
$GLOBALS['index']    = array(
	PARTNER  => array( 'record_id' => PARTNER, 'name' => 'Partner Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31', 'manager' => (string) CONTACT ),
	CHAMPION => array( 'record_id' => CHAMPION, 'name' => 'Champion Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' ),
	PAUSED   => array( 'record_id' => PAUSED, 'name' => 'Paused Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' ),
	BACKER   => array( 'record_id' => BACKER, 'name' => 'Backer Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2027-06-30', 'manager' => '' ),
	LAPSED   => array( 'record_id' => LAPSED, 'name' => 'Lapsed Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-01-31', 'manager' => '' ),
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

/* ---- the wiring ------------------------------------------------------------- */

echo "=== The wiring ===\n";

ck(
	'the planner hooks its three presses as admin-post actions',
	static function () {
		$GLOBALS['hooks'] = array();
		WPCPM_Sponsor_Event_Planner::init();
		return array( $GLOBALS['hooks']['admin_post_wpcpm_sevent_plan'] ?? null, $GLOBALS['hooks']['admin_post_wpcpm_sevent_change'] ?? null, $GLOBALS['hooks']['admin_post_wpcpm_sevent_cancel'] ?? null );
	},
	array(
		array( array( 'WPCPM_Sponsor_Event_Planner', 'handle_plan' ) ),
		array( array( 'WPCPM_Sponsor_Event_Planner', 'handle_change' ) ),
		array( array( 'WPCPM_Sponsor_Event_Planner', 'handle_cancel' ) ),
	)
);
ck(
	'the tier card hooks Talk to your program contact alone: Plan it now is gone, its action, its handler and its hook',
	static function () {
		$GLOBALS['hooks'] = array();
		WPCPM_Sponsor_Tier_Card::init();
		return array( array_keys( $GLOBALS['hooks'] ), method_exists( 'WPCPM_Sponsor_Tier_Card', 'handle_plan' ), defined( 'WPCPM_Sponsor_Tier_Card::ACTION_PLAN' ) );
	},
	array( array( 'admin_post_wpcpm_tier_talk' ), false, false )
);

$loader_src    = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$uninstall_src = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
$sponsors_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );

// Where each line stands decides nothing: a static call resolves when it is made, and init() only
// adds hooks. So the pins are that each line is there.
ck(
	'the loader and uninstall.php require the planner',
	array(
		false !== strpos( $loader_src, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-event-planner.php';\n" ),
		false !== strpos( $uninstall_src, "require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sponsor-event-planner.php';\n" ),
	),
	array( true, true )
);
ck( 'the Sponsors module boots the planner behind the same class_exists() guard as its other classes', 1 === preg_match( "/foreach \\( array\\([^)]*'WPCPM_Sponsor_Event_Planner'[^)]*\\) as \\\$front \\)/", $sponsors_src ), true );

/* ---- the nonce first, then the claim ----------------------------------------- */

echo "\n=== The nonce first, then the claim ===\n";

ck(
	'a stale page is stopped on the nonce keyed to the sponsor, before anything is claimed, read or written: a stranger\'s press is not even counted',
	static function () {
		$before              = store_state();
		$GLOBALS['nonces']   = array();
		$GLOBALS['nonce_ok'] = false;
		$left                = array();
		foreach ( array( 'handle_plan', 'handle_change', 'handle_cancel' ) as $method ) {
			$left[] = press( $method, form( PARTNER, array( 'wpcpm_sevent_id' => '1' ) ), STRANGER );
		}
		$GLOBALS['nonce_ok'] = true;
		return array( $left, $GLOBALS['nonces'], $GLOBALS['buckets'], store_state() === $before );
	},
	array(
		array( array( 'died', 'The link you followed has expired.' ), array( 'died', 'The link you followed has expired.' ), array( 'died', 'The link you followed has expired.' ) ),
		array( 'wpcpm_sevent_plan_' . PARTNER, 'wpcpm_sevent_change_' . PARTNER, 'wpcpm_sevent_cancel_' . PARTNER ),
		array(),
		true,
	)
);
ck(
	'a stranger is refused by the claim, lands with no sponsor named, and each refusal is counted; nothing is written or mailed',
	static function () {
		$before = store_state();
		$left   = array();
		foreach ( array( 'handle_plan', 'handle_change', 'handle_cancel' ) as $method ) {
			$left[] = press( $method, form( PARTNER, array( 'wpcpm_sevent_id' => '1' ) ), STRANGER );
		}
		return array( $left, $GLOBALS['buckets'], store_state() === $before );
	},
	array(
		array( array( 'refused', 'events', '', '' ), array( 'refused', 'events', '', '' ), array( 'refused', 'events', '', '' ) ),
		array( 'sponsor-refused:' . STRANGER => 3 ),
		true,
	)
);
ck(
	'with the launch switch off every press leaves with tier-off and writes nothing',
	static function () {
		$before                                    = store_state();
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$left                                      = array();
		foreach ( array( 'handle_plan', 'handle_change', 'handle_cancel' ) as $method ) {
			$left[] = press( $method, form( PARTNER, array( 'wpcpm_sevent_id' => '1' ) ), PARTNER_REP );
		}
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $left, store_state() === $before );
	},
	array( array( array( 'tier-off', 'events', PARTNER, '' ), array( 'tier-off', 'events', PARTNER, '' ), array( 'tier-off', 'events', PARTNER, '' ) ), true )
);
ck(
	'a member whose sponsor the store no longer holds leaves with tier-off and writes nothing',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( GONE ), DEPARTED_REP ), store_state() === $before );
	},
	array( array( 'tier-off', 'events', GONE, '' ), true )
);

/* ---- planning --------------------------------------------------------------- */

echo "\n=== Planning ===\n";

ck(
	'a day after the term\'s last day is refused in its own words, with the last day and the site\'s clock for the flash to name, and nothing is written or mailed',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2027-02-10' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-past-term', 'events', PARTNER, '', array( 'January 31, 2027', 'Europe / Warsaw' ) ), true )
);
ck(
	'a day and time that have passed are refused',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2026-10-01' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-in-past', 'events', PARTNER, '' ), true )
);
ck(
	'the form\'s refusal carries the fields\' sentences, in the form\'s order, as its detail',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_minutes' => '5', 'wpcpm_sevent_title' => '', 'wpcpm_sevent_link' => 'not a link' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-fields', 'events', PARTNER, 'The length has to be between 15 and 480 minutes. Give your event a title. The call link has to be a web address that starts with https:// or http://.' ), true )
);
ck(
	'an hour the clocks skip on the planner\'s own clock is refused as a time that does not exist',
	static function () {
		return press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2027-03-14', 'wpcpm_sevent_time' => '02:30' ) ), PARTNER_REP );
	},
	array( 'sevent-fields', 'events', PARTNER, 'That time does not exist on that day in your time zone, because the clocks change that night. Choose another time.' )
);
ck(
	'a detail holds whole sentences only, as many as the three hundred characters of the flash hold',
	static function () {
		$left   = press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2027-03-14', 'wpcpm_sevent_time' => '02:30', 'wpcpm_sevent_minutes' => '5', 'wpcpm_sevent_places' => '999', 'wpcpm_sevent_title' => str_repeat( 't', 121 ), 'wpcpm_sevent_description' => '', 'wpcpm_sevent_link' => 'ftp://call.example/x' ) ), PARTNER_REP );
		$detail = (string) $left[3];
		$next   = 'The call link has to be a web address that starts with https:// or http://.';
		return array( $left[0], $detail, mb_strlen( $detail ) <= 300, mb_strlen( $detail . ' ' . $next ) > 300, strpos( $detail, 'call link' ) );
	},
	array(
		'sevent-fields',
		'That time does not exist on that day in your time zone, because the clocks change that night. Choose another time. The length has to be between 15 and 480 minutes. Leave the places blank for no limit, or give from 1 to 500 places. Keep the title to 120 characters. Say what your event is about.',
		true,
		true,
		false,
	)
);
ck(
	'a held lock is said as such, and nothing is written or mailed',
	static function () {
		$before = store_state();
		add_option( 'wpcpm_sevent_lock_' . PARTNER, $GLOBALS['now'] );
		$left = press( 'handle_plan', form( PARTNER ), PARTNER_REP );
		delete_option( 'wpcpm_sevent_lock_' . PARTNER );
		return array( $left, store_state() === $before );
	},
	array( array( 'sevent-busy', 'events', PARTNER, '' ), true )
);
ck(
	'a sponsor whose tier holds no co-hosted event is refused before its form is read: sevent-no-tier, its call link looked up nowhere, and nothing written or mailed',
	static function () {
		$before             = store_state();
		$GLOBALS['lookups'] = array();
		return array( press( 'handle_plan', form( BACKER, array( 'wpcpm_sevent_link' => 'https://call.example/backer-room' ) ), BACKER_REP ), $GLOBALS['lookups'], store_state() === $before );
	},
	array( array( 'sevent-no-tier', 'events', BACKER, '' ), array(), true )
);

// A second member of the Partner's, made for the ceiling's check and gone after it, so no other
// check meets a member it does not expect.
const PARTNER_REP_TWO = 11;

ck(
	'ten presses an hour per account, the three presses together: the eleventh, a plan the store would take, leaves with the ceiling\'s sentence, before the store is asked, and writes and mails nothing, and so does a cancel pressed as the eleventh, before the event it names is asked for, while another account keeps its own ten, whether it is a second member of the same sponsor or another sponsor\'s member',
	static function () {
		$GLOBALS['same_hour'] = true;
		try {
			$ten = array();
			for ( $i = 1; $i <= 10; $i++ ) {
				$left  = press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2027-02-10' ) ), PARTNER_REP );
				$ten[] = $left[0];
			}
			$before   = store_state();
			$eleventh = press( 'handle_plan', form( PARTNER ), PARTNER_REP );
			$cancel   = press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => '0' ), PARTNER_REP );
			$after    = store_state() === $before;
			// Past the ceiling to the form's own refusal, which writes nothing either: first the
			// Partner's second member, whose presses a count kept per sponsor would have spent on the
			// first member's ten, then another sponsor's member.
			a_user( PARTNER_REP_TWO, 'Pia Partnerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1 ) );
			$same  = press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_minutes' => '5' ) ), PARTNER_REP_TWO );
			$other = press( 'handle_plan', form( CHAMPION, array( 'wpcpm_sevent_minutes' => '5' ) ), CHAMPION_REP );
		} finally {
			unset( $GLOBALS['same_hour'], $GLOBALS['users'][ PARTNER_REP_TWO ], $GLOBALS['umeta'][ PARTNER_REP_TWO ] );
		}
		return array( array_values( array_unique( $ten ) ), $eleventh, $cancel, $after, $same, $other[0] );
	},
	array( array( 'sevent-past-term' ), array( 'sevent-ceiling', 'events', PARTNER, '' ), array( 'sevent-ceiling', 'events', PARTNER, '' ), true, array( 'sevent-fields', 'events', PARTNER, 'The length has to be between 15 and 480 minutes.' ), 'sevent-fields' )
);

$plan_left = attempt( static function () { return press( 'handle_plan', form( PARTNER ), PARTNER_REP ); } );
$e1        = attempt( static function () { $ids = event_ids(); return $ids ? end( $ids ) : null; } );

ck( 'the Partner\'s member plans: sevent-sent, on the Events card', $plan_left, array( 'sevent-sent', 'events', PARTNER, '' ) );
ck(
	'the event reaches the store as typed, each ampersand written `&amp;` as WordPress writes it for a member, its day and time read on the member\'s own clock (2 pm in New York is 19:00 UTC)',
	static function () use ( $e1 ) {
		$post = get_post( $e1 );
		return array( $post->post_title, $post->post_content, meta( $e1, '_wpcpm_sevent_start' ), meta( $e1, '_wpcpm_sevent_minutes' ), meta( $e1, '_wpcpm_sevent_places' ), meta( $e1, '_wpcpm_sevent_link' ), meta( $e1, '_wpcpm_sevent_zone' ), meta( $e1, '_wpcpm_sevent_state' ) );
	},
	array( 'Partners "Q&amp;A" in C:\\talks', "What we \"cover\" &amp; more.\nBring questions.", (string) utc( '2026-11-10 19:00' ), '90', '40', 'https://call.example/room?pwd=a%2Fb&id=7', 'America/New_York', 'planned' )
);
ck( 'the store wrote its row, on the member ground', static function () { $last = last_row(); return array( $last['kind'], $last['ground'], $last['actor'] ); }, array( 'sponsor_event_planned', 'member', PARTNER_REP ) );
ck(
	'the Partner\'s Administrator is mailed once, logged as event-planned, with the event on their own clock (Tokyo\'s, where it is the next morning) and where to approve it',
	static function () {
		return $GLOBALS['mail'];
	},
	array(
		array(
			'to'      => 'mo.contact@people.example',
			'context' => 'event-planned',
			'mail'    => array(
				'subject' => '[WordPress Education] Partner Example planned an event',
				'body'    => "Partner Example planned an event on the Sponsor Dashboard. Nobody else sees it until an Administrator approves it.\n\n"
					. "Title: Partners \"Q&A\" in C:\\talks\nWhen: November 11, 2026 4:00 am to 5:30 am (Asia / Tokyo)\nLength: 90 minutes\nPlaces: 40\nCall link: https://call.example/room?pwd=a%2Fb&id=7\n\n"
					. "Description:\nWhat we \"cover\" & more.\nBring questions.\n\n"
					. "Approve it, or return it with a note, on the Administrator Dashboard under Events.\nhttps://site.example/administrator-dashboard/#wpcpm-sponsor-events",
			),
		),
	)
);
ck(
	'a second plan while this one is still to take place is refused in the words for a sponsor whose term runs, and nothing is written or mailed',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2026-12-01' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-one-open-term', 'events', PARTNER, '' ), true )
);
ck(
	'the store refuses it in the same words, for the press it answers and for a caller that reads its sentence: the event for this term is planned already',
	static function () {
		$before  = store_state();
		$refused = WPCPM_Sponsor_Event::plan( PARTNER, store_fields( utc( '2026-12-01 15:00' ) ), PARTNER_REP, 'member' );
		return array( $refused->get_error_code(), $refused->get_error_message(), WPCPM_Sponsor_Event_Planner::messages()['sevent-one-open-term'][1], store_state() === $before );
	},
	array( 'one_open', 'Your event for this term is planned already.', 'Your event for this term is planned already.', true )
);
ck(
	'a plan press looks its call link up once, though the form and the store each check it, the store the link as the form cleaned it: the store\'s refusal comes after both',
	static function () {
		$GLOBALS['lookups'] = array();
		return array( press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2026-12-01', 'wpcpm_sevent_link' => 'https://call.example/looked up once' ) ), PARTNER_REP ), $GLOBALS['lookups'] );
	},
	array( array( 'sevent-one-open-term', 'events', PARTNER, '' ), array( 'https://call.example/looked up once' ) )
);
ck(
	'a member naming another sponsor plans for its own: the claim acts on the member\'s stamp',
	static function () {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2026-12-03', 'wpcpm_sevent_title' => 'Champion webinar' ) ), CHAMPION_REP );
		$ids             = event_ids();
		return array( $left, WPCPM_Sponsor_Event::sponsor_of( get_post( end( $ids ) ) ), get_post( end( $ids ) )->post_title, $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['mail']['subject'] );
	},
	array( array( 'sevent-sent', 'events', CHAMPION, '' ), CHAMPION, 'Champion webinar', 'events@program.example', '[WordPress Education] Champion Example planned an event' )
);

$c1 = attempt( static function () { $ids = event_ids(); return end( $ids ); } );

/* ---- what the planner draws: an event waiting ------------------------------- */

echo "\n=== What the planner draws: an event waiting ===\n";

$partner_html = attempt( static function () { return draw( PARTNER, PARTNER_REP ); } );

ck(
	'the event, in the viewer\'s own zone and named, its length and its state, every value escaped',
	static function () use ( $partner_html ) {
		return false !== strpos( $partner_html, '<h4 class="wpcpm-sponsor__subheading">Your event</h4><div class="wpcpm-sevent__own"><p class="wpcpm-sevent__title">Partners &quot;Q&amp;A&quot; in C:\\talks</p><p class="wpcpm-sevent__meta">November 10, 2026 2:00 pm to 3:30 pm, 90 minutes, in your time zone (America / New York).</p><p class="wpcpm-sevent__state">Waiting for the program&#039;s OK.</p>' );
	},
	true
);
ck(
	'Change: a folded form keyed to the sponsor, naming the event, every box filled on the viewer\'s clock and escaped, the day bounded by today and the term\'s last day, the link by its scheme',
	static function () use ( $partner_html, $e1 ) {
		$id = 'wpcpm-sevent-' . $e1;
		return array(
			false !== strpos( $partner_html, '<details class="wpcpm-sevent__change"><summary class="wpcpm-sevent__change-toggle">Change the event</summary><form method="post" action="https://site.example/wp-admin/admin-post.php" class="wpcpm-sponsor__form wpcpm-sevent__form" data-wpcpm-once data-wpcpm-busy="Saving"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_change_' . PARTNER . '" /><input type="hidden" name="action" value="wpcpm_sevent_change" /><input type="hidden" name="wpcpm_sponsor" value="' . PARTNER . '" /><input type="hidden" name="wpcpm_sevent_id" value="' . $e1 . '" />' ),
			false !== strpos( $partner_html, '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="' . $id . '-date">Date</label><input type="date" id="' . $id . '-date" name="wpcpm_sevent_date" value="2026-11-10" min="2026-10-06" max="2027-01-31" required /></p>' ),
			false !== strpos( $partner_html, '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="' . $id . '-time">Start time</label><input type="time" id="' . $id . '-time" name="wpcpm_sevent_time" value="14:00" required aria-describedby="' . $id . '-zone" /><span class="wpcpm-student__note" id="' . $id . '-zone">In your time zone, America / New York. Everyone who joins sees it in theirs.</span></p>' ),
			false !== strpos( $partner_html, 'name="wpcpm_sevent_minutes" value="90" min="15" max="480" step="5" required />' ),
			false !== strpos( $partner_html, 'name="wpcpm_sevent_places" value="40" min="1" max="500" step="1"' ),
			false !== strpos( $partner_html, 'name="wpcpm_sevent_title" value="Partners &quot;Q&amp;A&quot; in C:\\talks" maxlength="120" required />' ),
			false !== strpos( $partner_html, 'name="wpcpm_sevent_description" rows="4" maxlength="2000" required aria-describedby="' . $id . '-description-note">What we &quot;cover&quot; &amp; more.' . "\n" . 'Bring questions.</textarea>' ),
			false !== strpos( $partner_html, 'name="wpcpm_sevent_link" value="https://call.example/room?pwd=a%2Fb&amp;id=7" maxlength="500" pattern="https?://.+" required' ),
			false !== strpos( $partner_html, '<p class="wpcpm-sevent__submit"><button type="submit" class="wpcpm-button">Save the changes</button></p></form></details>' ),
		);
	},
	array( true, true, true, true, true, true, true, true, true )
);
ck(
	'Cancel: one button keyed to the sponsor, naming the event, asking before it posts',
	static function () use ( $partner_html, $e1 ) {
		return false !== strpos( $partner_html, '<form method="post" action="https://site.example/wp-admin/admin-post.php" class="wpcpm-sponsor__form wpcpm-inline-form wpcpm-sevent__cancel" data-wpcpm-once data-wpcpm-busy="Canceling"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_cancel_' . PARTNER . '" /><input type="hidden" name="action" value="wpcpm_sevent_cancel" /><input type="hidden" name="wpcpm_sponsor" value="' . PARTNER . '" /><input type="hidden" name="wpcpm_sevent_id" value="' . $e1 . '" /><button type="submit" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-confirm="Cancel this event? This cannot be undone.">Cancel the event</button></form></div>' );
	},
	true
);
ck(
	'in place of the form, the one sentence, in the flash\'s words: the Partner\'s term runs, and its event for this term is planned already',
	static function () use ( $partner_html ) {
		return array( false !== strpos( $partner_html, '</form></div><p class="wpcpm-student__note">Your event for this term is planned already.</p>' ), strpos( $partner_html, 'wpcpm_sevent_plan' ), strpos( $partner_html, 'Plan your co-hosted event' ), strpos( $partner_html, 'once this one has taken place' ) );
	},
	array( true, false, false, false )
);
ck(
	'a program manager looking through the switcher reads the same event on their own clock, the site\'s',
	static function () {
		$html = draw( PARTNER, MANAGER );
		return array( false !== strpos( $html, '<p class="wpcpm-sevent__meta">November 10, 2026 8:00 pm to 9:30 pm, 90 minutes, in your time zone (Europe / Warsaw).</p>' ), false !== strpos( $html, 'value="20:00"' ) );
	},
	array( true, true )
);

/* ---- changing --------------------------------------------------------------- */

echo "\n=== Changing ===\n";

ck(
	'a change to a time that has passed is refused, and the event stays as it was',
	static function () use ( $e1 ) {
		$before = store_state();
		return array( press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-10-02' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-in-past', 'events', PARTNER, '' ), true )
);
ck(
	'and a change press looks its call link up once too',
	static function () use ( $e1 ) {
		$GLOBALS['lookups'] = array();
		return array( press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-10-02', 'wpcpm_sevent_link' => 'https://call.example/changed-once' ) ), PARTNER_REP ), $GLOBALS['lookups'] );
	},
	array( array( 'sevent-in-past', 'events', PARTNER, '' ), array( 'https://call.example/changed-once' ) )
);
ck(
	'a change the form refuses carries the fields\' sentences',
	static function () use ( $e1 ) {
		$before = store_state();
		return array( press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_description' => '' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-fields', 'events', PARTNER, 'Say what your event is about.' ), true )
);
ck(
	'another sponsor\'s member cannot change the Partner\'s event, whichever sponsor the form names',
	static function () use ( $e1 ) {
		$before = store_state();
		return array( press( 'handle_change', form( CHAMPION, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_title' => 'Taken over' ) ), CHAMPION_REP ), press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_title' => 'Taken over' ) ), CHAMPION_REP ), store_state() === $before );
	},
	array( array( 'refused', 'events', '', '' ), array( 'refused', 'events', '', '' ), true )
);
ck(
	'nor cancel it, whichever sponsor the form names: the event stays planned, and nothing is written or mailed',
	static function () use ( $e1 ) {
		$before = store_state();
		return array( press( 'handle_cancel', array( 'wpcpm_sponsor' => CHAMPION, 'wpcpm_sevent_id' => (string) $e1 ), CHAMPION_REP ), press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => (string) $e1 ), CHAMPION_REP ), meta( $e1, '_wpcpm_sevent_state' ), store_state() === $before );
	},
	array( array( 'refused', 'events', '', '' ), array( 'refused', 'events', '', '' ), 'planned', true )
);
ck(
	'nor can anybody change or cancel something that is not an event',
	static function () {
		$before = store_state();
		$page   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'private', 'post_title' => 'A page' ) );
		$state  = store_state();
		return array( press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $page ) ), PARTNER_REP ), press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => '0' ), PARTNER_REP ), store_state() === $state, count( $state[0] ) === count( $before[0] ) + 1 );
	},
	array( array( 'refused', 'events', '', '' ), array( 'refused', 'events', '', '' ), true, true )
);

$change_left = attempt(
	static function () use ( $e1 ) {
		$GLOBALS['mail'] = array();
		return press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-11-12', 'wpcpm_sevent_time' => '15:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_title' => 'Partners Q&A, moved' ) ), PARTNER_REP );
	}
);

ck( 'the member changes it: sevent-changed, on the Events card', $change_left, array( 'sevent-changed', 'events', PARTNER, '' ) );
ck(
	'the store holds the change, its ampersand written `&amp;` for a member, still waiting, and wrote its row',
	static function () use ( $e1 ) {
		$last = last_row();
		return array( get_post( $e1 )->post_title, meta( $e1, '_wpcpm_sevent_start' ), meta( $e1, '_wpcpm_sevent_minutes' ), meta( $e1, '_wpcpm_sevent_state' ), $last['kind'], $last['ground'] );
	},
	array( 'Partners Q&amp;A, moved', (string) utc( '2026-11-12 20:00' ), '60', 'planned', 'sponsor_event_changed', 'member' )
);
ck(
	'the program manager is told of the change, with the new day on their own clock, and that it still waits',
	static function () {
		$mail = $GLOBALS['mail'];
		return array( count( $mail ), $mail[0]['to'], $mail[0]['context'], $mail[0]['mail']['subject'], 0 === strpos( $mail[0]['mail']['body'], "Partner Example changed its event on the Sponsor Dashboard. It is still waiting for an Administrator's approval.\n\nTitle: Partners Q&A, moved\nWhen: November 13, 2026 5:00 am to 6:00 am (Asia / Tokyo)\nLength: 60 minutes\n" ), false !== strpos( $mail[0]['mail']['body'], "Approve it, or return it with a note, on the Administrator Dashboard under Events.\nhttps://site.example/administrator-dashboard/#wpcpm-sponsor-events" ) );
	},
	array( 1, 'mo.contact@people.example', 'event-changed-waiting', '[WordPress Education] Partner Example changed its event', true, true )
);

/* ---- an approved event, with people on it ----------------------------------- */

echo "\n=== An approved event, with people on it ===\n";

attempt(
	static function () use ( $e1 ) {
		WPCPM_Sponsor_Event::approve( $e1, MANAGER );
		add_post_meta( $e1, '_wpcpm_sevent_person', JOINER );
		add_post_meta( $e1, '_wpcpm_sevent_person', JOINER_TWO );
	}
);

ck(
	'approved, it says so and how many joined, and never who',
	static function () {
		$html = draw( PARTNER, PARTNER_REP );
		return array( false !== strpos( $html, '<p class="wpcpm-sevent__state">Approved. 2 people have joined.</p>' ), strpos( $html, 'Zelda' ), strpos( $html, 'Yuri' ), strpos( $html, 'people.example' ), false !== strpos( $html, 'name="wpcpm_sevent_places" value="40" min="2" max="500" step="1"' ) );
	},
	array( true, false, false, false, true )
);
ck(
	'the places cannot be changed to fewer than the people on it',
	static function () use ( $e1 ) {
		$before = store_state();
		return array( press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-11-12', 'wpcpm_sevent_time' => '15:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_places' => '1' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-shrink', 'events', PARTNER, '' ), true )
);

$approved_change = attempt(
	static function () use ( $e1 ) {
		$GLOBALS['mail'] = array();
		$GLOBALS['ics']  = array();
		return press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-11-13', 'wpcpm_sevent_time' => '15:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_title' => 'Partners Q&A, moved' ) ), PARTNER_REP );
	}
);

ck( 'the member changes the approved event: sevent-changed, and since the store told the people on it, that everyone who joined has been told', $approved_change, array( 'sevent-changed', 'events', PARTNER, 'Everyone who joined has been told.' ) );
ck(
	'the people on it are told first, by the store, then every live member of the Partner, the one who pressed among them, then the program contact',
	static function () { return mailed_to(); },
	array( 'zelda.privacywalk@people.example', 'yuri.privacywalk@people.example', 'pat.partnerrep@people.example', 'mo.contact@people.example' )
);
ck(
	'the member reads that the company\'s event is changed, on their own clock, what it now is, and that the calendar file replaces the one they had, logged as event-changed-sponsor',
	static function () { $mail = mail_for( 'pat.partnerrep@people.example' ); return array( $GLOBALS['mail'][2]['context'], $mail['subject'], $mail['body'] ); },
	array(
		'event-changed-sponsor',
		'[WordPress Education] Your event is changed: Partners Q&A, moved',
		"Your company's event is changed. The calendar file attached replaces the one you had.\n\n"
			. "Title: Partners Q&A, moved\nWhen: November 13, 2026 3:00 pm to 4:00 pm (America / New York)\nLength: 60 minutes\nPlaces: 40\nCall link: https://call.example/room?pwd=a%2Fb&id=7",
	)
);
ck(
	'the file is a request at the version the change raised, under the event\'s own identity, so it moves the entry the approval put in their calendar, and it names the member as its attendee and nobody else but the program',
	static function () use ( $e1 ) {
		$ics = mail_for( 'pat.partnerrep@people.example' )['ics'];
		return array( false !== strpos( $ics, "METHOD:REQUEST\r\n" ), false !== strpos( $ics, 'UID:wpcpm-call-' . $e1 . '@site.example' ), false !== strpos( $ics, 'SEQUENCE:' . meta( $e1, '_wpcpm_sevent_revision' ) . "\r\n" ), false !== strpos( $ics, "DTSTART:20261113T200000Z\r\n" ), array_values( preg_grep( '/people\.example/', explode( "\r\n", $ics ) ) ) );
	},
	array( true, true, true, true, array( 'ATTENDEE;CN="Pat Partnerrep";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:pat.partnerrep@people.example' ) )
);
ck(
	'the program contact\'s mail about the change says the event stays approved and where the program keeps it: the Administrator Dashboard lists it under Events',
	static function () {
		$body = mail_for( 'mo.contact@people.example' )['body'];
		return array( 0 === strpos( $body, 'Partner Example changed its approved event on the Sponsor Dashboard. It stays approved.' ), false !== strpos( $body, "The Administrator Dashboard lists it under Events.\nhttps://site.example/administrator-dashboard/#wpcpm-sponsor-events" ) );
	},
	array( true, true )
);
ck(
	'no mail to the Partner\'s member or to the program contact names anybody on the event',
	static function () {
		$leaks = array();
		foreach ( array( 'pat.partnerrep@people.example', 'mo.contact@people.example' ) as $address ) {
			$mail = mail_for( $address );
			foreach ( array( 'Zelda', 'Yuri', 'privacywalk@' ) as $needle ) {
				if ( false !== stripos( $mail['subject'] . $mail['body'] . $mail['ics'], $needle ) ) { $leaks[] = $address . ': ' . $needle; }
			}
		}
		return $leaks;
	},
	array()
);

ck(
	'the same form pressed again changes nothing: "Nothing changed.", nothing written, and nobody mailed, the people on it, the members and the program contact alike',
	static function () use ( $e1 ) {
		$before = store_state();
		$left   = press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-11-13', 'wpcpm_sevent_time' => '15:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_title' => 'Partners Q&A, moved' ) ), PARTNER_REP );
		return array( $left, store_state() === $before );
	},
	array( array( 'sevent-unchanged', 'events', PARTNER, '' ), true )
);
ck(
	'nor does a program manager\'s save of it as it stands, on the manager\'s own clock: 3 pm in New York is 9 pm in Warsaw, the same moment',
	static function () use ( $e1 ) {
		$before = store_state();
		$left   = press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-11-13', 'wpcpm_sevent_time' => '21:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_title' => 'Partners Q&A, moved' ) ), MANAGER );
		return array( $left, store_state() === $before );
	},
	array( array( 'sevent-unchanged', 'events', PARTNER, '' ), true )
);
ck(
	'a change of the description alone reaches the member and the program contact, and nobody on the event, and the outcome claims nothing about them',
	static function () use ( $e1 ) {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-11-13', 'wpcpm_sevent_time' => '15:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_title' => 'Partners Q&A, moved', 'wpcpm_sevent_description' => "What we cover.\nBring questions." ) ), PARTNER_REP );
		return array( $left, mailed_to(), get_post( $e1 )->post_content );
	},
	array( array( 'sevent-changed', 'events', PARTNER, '' ), array( 'pat.partnerrep@people.example', 'mo.contact@people.example' ), "What we cover.\nBring questions." )
);
ck(
	'and so does a change of the places alone',
	static function () use ( $e1 ) {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1, 'wpcpm_sevent_date' => '2026-11-13', 'wpcpm_sevent_time' => '15:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_places' => '41', 'wpcpm_sevent_title' => 'Partners Q&A, moved', 'wpcpm_sevent_description' => "What we cover.\nBring questions." ) ), PARTNER_REP );
		return array( $left, mailed_to(), meta( $e1, '_wpcpm_sevent_places' ) );
	},
	array( array( 'sevent-changed', 'events', PARTNER, '' ), array( 'pat.partnerrep@people.example', 'mo.contact@people.example' ), '41' )
);

$cancel_left = attempt(
	static function () use ( $e1 ) {
		$GLOBALS['mail'] = array();
		$GLOBALS['ics']  = array();
		return press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => (string) $e1 ), PARTNER_REP );
	}
);

ck( 'the member cancels it: sevent-canceled, on the Events card, and everyone who joined has been told', $cancel_left, array( 'sevent-canceled', 'events', PARTNER, 'Everyone who joined has been told.' ) );
ck( 'the store canceled it and wrote its row, on the member ground', static function () use ( $e1 ) { $last = last_row(); return array( meta( $e1, '_wpcpm_sevent_state' ), $last['kind'], $last['ground'], $last['actor'] ); }, array( 'cancelled', 'sponsor_event_cancelled', 'member', PARTNER_REP ) );
ck(
	'the people on it, the member and the program contact are told, in that order',
	static function () { return mailed_to(); },
	array( 'zelda.privacywalk@people.example', 'yuri.privacywalk@people.example', 'pat.partnerrep@people.example', 'mo.contact@people.example' )
);
ck(
	'the member reads that the company\'s event is canceled, and when it was to be on their clock, with the file that takes it off their calendar, which names them as its attendee',
	static function () use ( $e1 ) {
		$mail = mail_for( 'pat.partnerrep@people.example' );
		return array( $mail['subject'], $mail['body'], false !== strpos( $mail['ics'], "METHOD:CANCEL\r\n" ), false !== strpos( $mail['ics'], 'SEQUENCE:' . meta( $e1, '_wpcpm_sevent_revision' ) . "\r\n" ), array_values( preg_grep( '/people\.example/', explode( "\r\n", $mail['ics'] ) ) ) );
	},
	array(
		'[WordPress Education] Your event is canceled: Partners Q&A, moved',
		"Your company's event is canceled. The calendar file attached takes it off your calendar.\n\n"
			. 'Title: Partners Q&A, moved' . "\nWhen: November 13, 2026 3:00 pm to 4:00 pm (America / New York)",
		true,
		true,
		array( 'ATTENDEE;CN="Pat Partnerrep";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:pat.partnerrep@people.example' ),
	)
);
ck(
	'the program manager is told, with how many had joined and without a name or an address of anybody on it',
	static function () {
		$mail = mail_for( 'mo.contact@people.example' );
		$text = $mail['subject'] . "\n" . $mail['body'];
		return array( $mail['subject'], 0 === strpos( $mail['body'], "Partner Example canceled its event on the Sponsor Dashboard.\n\nTitle: Partners Q&A, moved\n" ), false !== strpos( $text, "\nPeople who joined: 2\n" ), strpos( $text, 'Zelda' ), strpos( $text, 'Yuri' ), strpos( $text, 'privacywalk@people.example' ), strpos( $text, 'Approve it' ) );
	},
	array( '[WordPress Education] Partner Example canceled its event', true, true, false, false, false, false )
);
ck(
	'a second press on a page drawn before the cancel is told it was canceled, and nothing is written or mailed',
	static function () use ( $e1 ) {
		$before = store_state();
		return array( press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => (string) $e1 ), PARTNER_REP ), press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e1 ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-was-canceled', 'events', PARTNER, '' ), array( 'sevent-was-canceled', 'events', PARTNER, '' ), true )
);
ck(
	'with the event canceled the Partner may plan again, and the form is drawn in its place, on the member\'s clock',
	static function () {
		$html = draw( PARTNER, PARTNER_REP );
		return array( strpos( $html, 'wpcpm-sevent__own' ), false !== strpos( $html, '<h4 class="wpcpm-sponsor__subheading">Plan your co-hosted event</h4><form method="post" action="https://site.example/wp-admin/admin-post.php" class="wpcpm-sponsor__form wpcpm-sevent__form" data-wpcpm-once data-wpcpm-busy="Sending"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_plan_' . PARTNER . '" /><input type="hidden" name="action" value="wpcpm_sevent_plan" /><input type="hidden" name="wpcpm_sponsor" value="' . PARTNER . '" />' ), false !== strpos( $html, 'In your time zone, America / New York.' ), strpos( $html, 'Your last plan was returned' ) );
	},
	array( false, true, true, false )
);
ck(
	'the date box starts on the site\'s today, the day the store reads a start on: at 2 in the morning in UTC it is still the day before in New York, and the member there is offered Warsaw\'s day, as the manager in Warsaw is',
	static function () {
		set_now( utc( '2026-10-06 02:00' ) );
		$rep     = draw( PARTNER, PARTNER_REP );
		$manager = draw( PARTNER, MANAGER );
		set_now( utc( '2026-10-06 12:00' ) );
		return array( false !== strpos( $rep, 'name="wpcpm_sevent_date" value="" min="2026-10-06" max="2027-01-31" required />' ), false !== strpos( $manager, 'name="wpcpm_sevent_date" value="" min="2026-10-06" max="2027-01-31" required />' ) );
	},
	array( true, true )
);

/* ---- the form, whole ---------------------------------------------------------- */

echo "\n=== The form ===\n";

ck(
	'the form: the day from today to the term\'s last day, the start time with the zone named beside it, the length, the places, the title, the description, the call link as an http or https address, and the button',
	static function () {
		$html = draw( PARTNER, PARTNER_REP );
		$form = substr( $html, (int) strpos( $html, '<input type="hidden" name="wpcpm_sponsor" value="' . PARTNER . '" />' ) );
		$form = substr( $form, 0, (int) strpos( $form, '</form>' ) + 7 );
		return $form;
	},
	'<input type="hidden" name="wpcpm_sponsor" value="' . PARTNER . '" />'
		. '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="wpcpm-sevent-new-date">Date</label><input type="date" id="wpcpm-sevent-new-date" name="wpcpm_sevent_date" value="" min="2026-10-06" max="2027-01-31" required /></p>'
		. '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="wpcpm-sevent-new-time">Start time</label><input type="time" id="wpcpm-sevent-new-time" name="wpcpm_sevent_time" value="" required aria-describedby="wpcpm-sevent-new-zone" /><span class="wpcpm-student__note" id="wpcpm-sevent-new-zone">In your time zone, America / New York. Everyone who joins sees it in theirs.</span></p>'
		. '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="wpcpm-sevent-new-minutes">Length in minutes</label><input type="number" id="wpcpm-sevent-new-minutes" name="wpcpm_sevent_minutes" value="60" min="15" max="480" step="5" required /></p>'
		. '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="wpcpm-sevent-new-places">Places</label><input type="number" id="wpcpm-sevent-new-places" name="wpcpm_sevent_places" value="" min="1" max="500" step="1" aria-describedby="wpcpm-sevent-new-places-note" /><span class="wpcpm-student__note" id="wpcpm-sevent-new-places-note">Leave it blank for no limit.</span></p>'
		. '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="wpcpm-sevent-new-title">Title</label><input type="text" id="wpcpm-sevent-new-title" name="wpcpm_sevent_title" value="" maxlength="120" required /></p>'
		. '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="wpcpm-sevent-new-description">Description</label><textarea id="wpcpm-sevent-new-description" name="wpcpm_sevent_description" rows="4" maxlength="2000" required aria-describedby="wpcpm-sevent-new-description-note"></textarea><span class="wpcpm-student__note" id="wpcpm-sevent-new-description-note">Shown on the Partners page once the event is approved. Put the call link in its own box.</span></p>'
		. '<p class="wpcpm-sponsor__field wpcpm-sevent__row"><label for="wpcpm-sevent-new-link">Call link</label><input type="url" id="wpcpm-sevent-new-link" name="wpcpm_sevent_link" value="" maxlength="500" pattern="https?://.+" required aria-describedby="wpcpm-sevent-new-link-note" /><span class="wpcpm-student__note" id="wpcpm-sevent-new-link-note">Only the people who join, and the program, see this link.</span></p>'
		. '<p class="wpcpm-sevent__submit"><button type="submit" class="wpcpm-button">Plan the event</button></p></form>'
);

// The description is shown on the Partners page once the event is approved, with any web
// address in it taken out, so its box says where its words go before a sponsor pastes the call
// link into it; the link box keeps its own sentence. The change form's box is the same row.
ck(
	'the description box says it is public, once the event is approved, and that the call link goes in its own box, on the plan and the change alike',
	static function () {
		$plan    = draw( PARTNER, PARTNER_REP );
		$note    = 'Shown on the Partners page once the event is approved. Put the call link in its own box.';
		$planned = '<span class="wpcpm-student__note" id="wpcpm-sevent-new-description-note">' . $note . '</span>';
		ob_start();
		WPCPM_Sponsor_Event_Planner::render_fields( 'wpcpm-sevent-check', array( 'date' => '', 'time' => '', 'minutes' => '60', 'places' => '', 'title' => '', 'description' => '', 'link' => '' ), new DateTimeZone( 'UTC' ), 1, '2026-10-06', '' );
		$rows = (string) ob_get_clean();
		return array( false !== strpos( $plan, $planned ), false !== strpos( $rows, 'aria-describedby="wpcpm-sevent-check-description-note"></textarea><span class="wpcpm-student__note" id="wpcpm-sevent-check-description-note">' . $note . '</span>' ), substr_count( $plan . $rows, 'shown to everyone who can join' ) );
	},
	array( true, true, 0 )
);

// The plugin's sponsor stylesheet caps the forms' boxes at one width, and the theme stretches each
// box it dresses to that cap: a box the cap does not name runs the whole card, as the start time
// box did beside the others.
ck(
	'every box the form draws takes the width cap the plugin\'s stylesheet gives the sponsor forms, the start time box among them',
	static function () {
		$html = draw( PARTNER, PARTNER_REP );
		$form = substr( $html, (int) strpos( $html, 'data-wpcpm-busy="Sending"' ) );
		$form = substr( $form, 0, (int) strpos( $form, '</form>' ) );
		$css  = (string) file_get_contents( __DIR__ . '/../assets/css/sponsor.css' );
		preg_match_all( '/<(?:input type="(\w+)"|(textarea))/', $form, $boxes, PREG_SET_ORDER );
		$capped = preg_match( '/([^{}]*)\{\s*max-width:\s*32em;\s*width:\s*100%;\s*\}/', $css, $rule ) ? array_map( 'trim', explode( ',', $rule[1] ) ) : array();
		$loose  = array();
		foreach ( $boxes as $box ) {
			$selector = isset( $box[2] ) && '' !== $box[2] ? '.wpcpm-sponsor__form textarea' : '.wpcpm-sponsor__form input[type="' . $box[1] . '"]';
			if ( 'hidden' !== $box[1] && ! in_array( $selector, $capped, true ) ) {
				$loose[] = $selector;
			}
		}
		return array( count( $boxes ) > 6, array_values( array_unique( $loose ) ) );
	},
	array( true, array() )
);

/* ---- the Champion: a plan on its behalf, returned with a note ----------------- */

echo "\n=== A plan returned with a note ===\n";

ck(
	'the Champion\'s own event, planned above, waits, and in the form\'s place the sentence for a sponsor with no term',
	static function () use ( $c1 ) {
		$html = draw( CHAMPION, CHAMPION_REP );
		return array( false !== strpos( $html, '<input type="hidden" name="wpcpm_sevent_id" value="' . $c1 . '" />' ), false !== strpos( $html, '<p class="wpcpm-student__note">Your next event can be planned once this one has taken place.</p>' ), strpos( $html, 'for this term' ) );
	},
	array( true, true, false )
);
ck(
	'with no term the press and the store refuse a second plan in those words too, and nothing is written or mailed',
	static function () {
		$before  = store_state();
		$left    = press( 'handle_plan', form( CHAMPION, array( 'wpcpm_sevent_date' => '2026-12-04' ) ), CHAMPION_REP );
		$refused = WPCPM_Sponsor_Event::plan( CHAMPION, store_fields( utc( '2026-12-04 15:00' ) ), CHAMPION_REP, 'member' );
		return array( $left, $refused->get_error_code(), $refused->get_error_message(), WPCPM_Sponsor_Event_Planner::messages()['sevent-one-open'][1], store_state() === $before );
	},
	array( array( 'sevent-one-open', 'events', CHAMPION, '' ), 'one_open', 'Your next event can be planned once this one has taken place.', 'Your next event can be planned once this one has taken place.', true )
);

attempt( static function () use ( $c1 ) { WPCPM_Sponsor_Event::return_with_note( $c1, MANAGER, 'Add the "agenda" & the speakers.' ); } );

ck(
	'returned, the last plan\'s note stands above the form, escaped; a sponsor with no term has no last day for the date box',
	static function () {
		$html = draw( CHAMPION, CHAMPION_REP );
		return array( strpos( $html, 'wpcpm-sevent__own' ), false !== strpos( $html, '<h4 class="wpcpm-sponsor__subheading">Plan your co-hosted event</h4><p class="wpcpm-sevent__returned">Your last plan was returned: Add the &quot;agenda&quot; &amp; the speakers.</p><form method="post"' ), false !== strpos( $html, 'name="wpcpm_sevent_date" value="" min="2026-10-06" required />' ) );
	},
	array( false, true, true )
);
ck(
	'a program manager plans on the Champion\'s behalf: the row says manager, and with nobody assigned the Interest mail addresses are told, on the site\'s clock, that a program manager did it',
	static function () {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_plan', form( CHAMPION, array( 'wpcpm_sevent_date' => '2026-12-02', 'wpcpm_sevent_title' => 'Champion webinar, again' ) ), MANAGER );
		$last            = last_row();
		return array( $left, $last['kind'], $last['ground'], $last['actor'], count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['via'], $GLOBALS['mail'][0]['mail']['subject'], 0 === strpos( $GLOBALS['mail'][0]['mail']['body'], "An Administrator planned an event on the Sponsor Dashboard on Champion Example's behalf. Nobody else sees it until an Administrator approves it.\n\nTitle: Champion webinar, again\nWhen: December 2, 2026 2:00 pm to 3:30 pm (Europe / Warsaw)\n" ) );
	},
	array( array( 'sevent-sent', 'events', CHAMPION, '' ), 'sponsor_event_planned', 'manager', MANAGER, 1, 'events@program.example', 'sponsor_notify', '[WordPress Education] Champion Example planned an event', true )
);

$c2 = attempt( static function () { $ids = event_ids(); return end( $ids ); } );

ck( 'a new plan puts the returned note away', static function () { return strpos( draw( CHAMPION, CHAMPION_REP ), 'Your last plan was returned' ); }, false );

/* ---- a sponsor that holds no tier with events --------------------------------- */

echo "\n=== A sponsor that holds no tier with events ===\n";

$paused_event = attempt(
	static function () {
		$planned = WPCPM_Sponsor_Event::plan( PAUSED, array( 'start' => utc( '2026-12-05 15:00' ), 'minutes' => 60, 'places' => 0, 'title' => 'Paused webinar', 'description' => 'Before the pause.', 'link' => 'https://call.example/paused', 'zone' => 'Europe/Warsaw' ), PAUSED_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $planned, MANAGER );
		$GLOBALS['index'][ PAUSED ]['status'] = 'Paused';
		return $planned;
	}
);

ck(
	'a sponsor set to Paused sees its approved event as no longer offered, with Cancel alone, and neither the form nor a sentence in its place',
	static function () use ( $paused_event ) {
		$html = draw( PAUSED, PAUSED_REP );
		return array( is_int( $paused_event ), false !== strpos( $html, '<p class="wpcpm-sevent__state">Approved, but no longer offered: your company&#039;s sponsorship does not include events at the moment. Nobody has joined yet.</p>' ), false !== strpos( $html, 'Cancel the event' ), strpos( $html, 'wpcpm-sevent__change' ), strpos( $html, 'wpcpm_sevent_change' ), strpos( $html, 'wpcpm_sevent_plan' ), strpos( $html, 'wpcpm-student__note">Your' ) );
	},
	array( true, true, true, false, false, false, false )
);
ck(
	'a change it posts anyway is refused as a tier without the co-hosted event, and nothing is written or mailed',
	static function () use ( $paused_event ) {
		$before = store_state();
		return array( press( 'handle_change', form( PAUSED, array( 'wpcpm_sevent_id' => (string) $paused_event, 'wpcpm_sevent_date' => '2026-12-06', 'wpcpm_sevent_link' => 'https://other.example/new' ) ), PAUSED_REP ), store_state() === $before );
	},
	array( array( 'sevent-no-tier', 'events', PAUSED, '' ), true )
);
ck(
	'a plan it posts anyway is refused by the allowance, and nothing is written or mailed',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( PAUSED, array( 'wpcpm_sevent_date' => '2026-12-20' ) ), PAUSED_REP ), store_state() === $before );
	},
	array( array( 'sevent-no-tier', 'events', PAUSED, '' ), true )
);
ck(
	'it may still cancel its event: its member is sent the file that takes the approved event out of their calendar, and the program is told',
	static function () use ( $paused_event ) {
		$GLOBALS['mail'] = array();
		return array( press( 'handle_cancel', array( 'wpcpm_sponsor' => PAUSED, 'wpcpm_sevent_id' => (string) $paused_event ), PAUSED_REP ), meta( $paused_event, '_wpcpm_sevent_state' ), mailed_to(), mail_for( 'paula.pausedrep@people.example' )['subject'], mail_for( 'events@program.example' )['subject'] );
	},
	array( array( 'sevent-canceled', 'events', PAUSED, '' ), 'cancelled', array( 'paula.pausedrep@people.example', 'events@program.example' ), '[WordPress Education] Your event is canceled: Paused webinar', '[WordPress Education] Paused Example canceled its event' )
);
ck( 'a Backer\'s tier has no events: the planner draws nothing at all', static function () { return draw( BACKER, BACKER_REP ); }, '' );
ck(
	'and a Backer\'s press is refused by the allowance',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( BACKER, array( 'wpcpm_sevent_date' => '2026-12-20' ) ), BACKER_REP ), store_state() === $before );
	},
	array( array( 'sevent-no-tier', 'events', BACKER, '' ), true )
);
ck(
	'nothing at all while the launch switch is off, a waiting event included',
	static function () {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$html                                      = draw( CHAMPION, CHAMPION_REP );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return $html;
	},
	''
);

/* ---- a program manager through the switcher ------------------------------------ */

echo "\n=== A program manager through the switcher ===\n";

// The form names the Partner, the sponsor the switcher showed last; the event is the Champion's.
// The press lands on the event's own sponsor: its flash, its row and its contact.
ck(
	'a program manager changes the Champion\'s event through a form keyed to the Partner: it lands on the Champion, on the manager ground, and the Champion\'s contact reads that a program manager did it',
	static function () use ( $c2 ) {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $c2, 'wpcpm_sevent_date' => '2026-12-03', 'wpcpm_sevent_title' => 'Champion webinar, moved' ) ), MANAGER );
		$last            = last_row();
		return array( $left, get_post( $c2 )->post_title, $last['kind'], $last['ground'], count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['mail']['subject'], 0 === strpos( $GLOBALS['mail'][0]['mail']['body'], "An Administrator changed the event on the Sponsor Dashboard on Champion Example's behalf. It is still waiting for an Administrator's approval.\n\nTitle: Champion webinar, moved\nWhen: December 3, 2026 2:00 pm to 3:30 pm (Europe / Warsaw)\n" ) );
	},
	array( array( 'sevent-changed', 'events', CHAMPION, '' ), 'Champion webinar, moved', 'sponsor_event_changed', 'manager', 1, 'events@program.example', '[WordPress Education] Champion Example changed its event', true )
);
ck(
	'and cancels it the same way: on the Champion, on the manager ground, its contact told',
	static function () use ( $c2 ) {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => (string) $c2 ), MANAGER );
		$last            = last_row();
		return array( $left, meta( $c2, '_wpcpm_sevent_state' ), $last['kind'], $last['ground'], $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['mail']['subject'], 0 === strpos( $GLOBALS['mail'][0]['mail']['body'], "An Administrator canceled the event on the Sponsor Dashboard on Champion Example's behalf.\n\n" ) );
	},
	array( array( 'sevent-canceled', 'events', CHAMPION, '' ), 'cancelled', 'sponsor_event_cancelled', 'manager', 'events@program.example', '[WordPress Education] Champion Example canceled its event', true )
);

// An event of the program's own, as its plan writes one: no sponsor, the program's kind, approved as
// it was planned. The Administrator Dashboard's card changes and cancels it; the planner, a sponsor's,
// never does, a program manager's press through the switcher included, which would otherwise mail a
// program contact about a sponsor that is nobody.
$program_own = attempt(
	static function () {
		$id = wp_insert_post( array( 'post_type' => 'wpcpm_sponsor_event', 'post_status' => 'private', 'post_author' => MANAGER, 'post_title' => 'The partners session', 'post_content' => 'What the program plans with its partners.' ) );
		foreach ( array( '_wpcpm_sevent_kind' => 'program', '_wpcpm_sevent_start' => utc( '2026-12-20 10:00' ), '_wpcpm_sevent_end' => utc( '2026-12-20 11:00' ), '_wpcpm_sevent_zone' => 'UTC', '_wpcpm_sevent_minutes' => 60, '_wpcpm_sevent_places' => 0, '_wpcpm_sevent_link' => 'https://call.example/partners', '_wpcpm_sevent_state' => 'approved', '_wpcpm_sevent_revision' => 0 ) as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}
);

ck(
	'nor does a program manager reach an event of the program\'s own through the switcher, whichever sponsor the form names: its change and its cancel meet the one refusal, the event stays approved as it was, and nobody is mailed',
	static function () use ( $program_own ) {
		$before = store_state();
		$change = press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $program_own, 'wpcpm_sevent_title' => 'Taken over' ) ), MANAGER );
		$cancel = press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => (string) $program_own ), MANAGER );
		return array( is_int( $program_own ), $change, $cancel, meta( $program_own, '_wpcpm_sevent_state' ), store_state() === $before );
	},
	array( true, array( 'refused', 'events', '', '' ), array( 'refused', 'events', '', '' ), 'approved', true )
);

// Out of the store again, so nothing below meets it.
unset( $GLOBALS['posts'][ (int) $program_own ], $GLOBALS['pmeta'][ (int) $program_own ] );

/* ---- the members of a sponsor whose approved event a manager changes ----------- */

echo "\n=== The members, told of their approved event ===\n";

// The Champion plans again, and the program approves: its one member holds the approval's calendar
// file. A program manager then changes it and cancels it through the switcher.
$c3 = attempt(
	static function () {
		press( 'handle_plan', form( CHAMPION, array( 'wpcpm_sevent_date' => '2026-12-04', 'wpcpm_sevent_title' => 'Champion webinar, third' ) ), CHAMPION_REP );
		$ids = event_ids();
		$id  = end( $ids );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

ck(
	'a program manager changes the approved event on the Champion\'s behalf with nobody on it: no sentence about the people who joined, the Champion\'s member told first, then its contact',
	static function () use ( $c3 ) {
		$GLOBALS['mail'] = array();
		$GLOBALS['ics']  = array();
		$left            = press( 'handle_change', form( CHAMPION, array( 'wpcpm_sevent_id' => (string) $c3, 'wpcpm_sevent_date' => '2026-12-05', 'wpcpm_sevent_title' => 'Champion webinar, third' ) ), MANAGER );
		return array( is_int( $c3 ), $left, mailed_to() );
	},
	array( true, array( 'sevent-changed', 'events', CHAMPION, '' ), array( 'chris.championrep@people.example', 'events@program.example' ) )
);
ck(
	'the member reads that a program manager changed it on the company\'s behalf, then that it is changed, with the file',
	static function () { $mail = mail_for( 'chris.championrep@people.example' ); return array( $mail['subject'], $mail['body'], false !== strpos( $mail['ics'], "METHOD:REQUEST\r\n" ) ); },
	array(
		'[WordPress Education] Your event is changed: Champion webinar, third',
		"An Administrator changed it on Champion Example's behalf. Your company's event is changed. The calendar file attached replaces the one you had.\n\n"
			. "Title: Champion webinar, third\nWhen: December 5, 2026 2:00 pm to 3:30 pm (Europe / Warsaw)\nLength: 90 minutes\nPlaces: 40\nCall link: https://call.example/room?pwd=a%2Fb&id=7",
		true,
	)
);

attempt( static function () use ( $c3 ) { add_post_meta( $c3, '_wpcpm_sevent_person', JOINER ); } );

ck(
	'and cancels it with somebody on it: they are told first, then the member, with the manager\'s sentence and the cancel, then the contact, and the outcome says everyone who joined has been told',
	static function () use ( $c3 ) {
		$GLOBALS['mail'] = array();
		$GLOBALS['ics']  = array();
		$left            = press( 'handle_cancel', array( 'wpcpm_sponsor' => CHAMPION, 'wpcpm_sevent_id' => (string) $c3 ), MANAGER );
		$mail            = mail_for( 'chris.championrep@people.example' );
		return array( $left, mailed_to(), $mail['subject'], $mail['body'], false !== strpos( $mail['ics'], "METHOD:CANCEL\r\n" ) );
	},
	array(
		array( 'sevent-canceled', 'events', CHAMPION, 'Everyone who joined has been told.' ),
		array( 'zelda.privacywalk@people.example', 'chris.championrep@people.example', 'events@program.example' ),
		'[WordPress Education] Your event is canceled: Champion webinar, third',
		"An Administrator canceled it on Champion Example's behalf. Your company's event is canceled. The calendar file attached takes it off your calendar.\n\n"
			. "Title: Champion webinar, third\nWhen: December 5, 2026 2:00 pm to 3:30 pm (Europe / Warsaw)",
		true,
	)
);

// Once more, the one person on the event is a mail the server refuses: the store told nobody.
$c4 = attempt(
	static function () {
		press( 'handle_plan', form( CHAMPION, array( 'wpcpm_sevent_date' => '2026-12-06', 'wpcpm_sevent_title' => 'Champion webinar, fourth' ) ), CHAMPION_REP );
		$ids = event_ids();
		$id  = end( $ids );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		add_post_meta( $id, '_wpcpm_sevent_person', JOINER_TWO );
		return $id;
	}
);

ck(
	'the sentence is said only when the store told somebody: the one mail to the person on it refused, the member is still told and the outcome claims nothing',
	static function () use ( $c4 ) {
		$GLOBALS['mail']      = array();
		$GLOBALS['refuse_to'] = array( 'yuri.privacywalk@people.example' );
		$left                 = press( 'handle_change', form( CHAMPION, array( 'wpcpm_sevent_id' => (string) $c4, 'wpcpm_sevent_date' => '2026-12-07', 'wpcpm_sevent_title' => 'Champion webinar, fourth' ) ), CHAMPION_REP );
		$GLOBALS['refuse_to'] = array();
		return array( is_int( $c4 ), $left, mailed_to() );
	},
	array( true, array( 'sevent-changed', 'events', CHAMPION, '' ), array( 'chris.championrep@people.example', 'events@program.example' ) )
);

// A second person joins the fourth event: now one mail of two is refused, then none, and then an
// account that is gone sits on the event beside the two.
attempt( static function () use ( $c4 ) { add_post_meta( $c4, '_wpcpm_sevent_person', JOINER ); } );

ck(
	'one of the two told and the other\'s mail refused: the outcome claims nothing, since not everybody who joined was told',
	static function () use ( $c4 ) {
		$GLOBALS['mail']      = array();
		$GLOBALS['refuse_to'] = array( 'yuri.privacywalk@people.example' );
		$left                 = press( 'handle_change', form( CHAMPION, array( 'wpcpm_sevent_id' => (string) $c4, 'wpcpm_sevent_date' => '2026-12-08', 'wpcpm_sevent_title' => 'Champion webinar, fourth' ) ), CHAMPION_REP );
		$GLOBALS['refuse_to'] = array();
		return array( $left, mailed_to() );
	},
	array( array( 'sevent-changed', 'events', CHAMPION, '' ), array( 'zelda.privacywalk@people.example', 'chris.championrep@people.example', 'events@program.example' ) )
);
ck(
	'both told: the outcome says everyone who joined has been told',
	static function () use ( $c4 ) {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_change', form( CHAMPION, array( 'wpcpm_sevent_id' => (string) $c4, 'wpcpm_sevent_date' => '2026-12-09', 'wpcpm_sevent_title' => 'Champion webinar, fourth' ) ), CHAMPION_REP );
		return array( $left, mailed_to() );
	},
	array( array( 'sevent-changed', 'events', CHAMPION, 'Everyone who joined has been told.' ), array( 'yuri.privacywalk@people.example', 'zelda.privacywalk@people.example', 'chris.championrep@people.example', 'events@program.example' ) )
);
ck(
	'an account that is gone, still on the event beside the two, can be told nothing: both told, and the outcome still claims nothing',
	static function () use ( $c4 ) {
		add_post_meta( $c4, '_wpcpm_sevent_person', 777 );
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_cancel', array( 'wpcpm_sponsor' => CHAMPION, 'wpcpm_sevent_id' => (string) $c4 ), CHAMPION_REP );
		return array( $left, mailed_to(), meta( $c4, '_wpcpm_sevent_state' ) );
	},
	array( array( 'sevent-canceled', 'events', CHAMPION, '' ), array( 'yuri.privacywalk@people.example', 'zelda.privacywalk@people.example', 'chris.championrep@people.example', 'events@program.example' ), 'cancelled' )
);

/* ---- more people on an event than one press tells ------------------------------- */

echo "\n=== More people on an event than one press tells ===\n";

// The Champion plans once more and the program approves; fifty-five people join, more than the
// fifty a press tells.
$crowd = array();
for ( $i = 1; $i <= 55; $i++ ) {
	a_user( 7000 + $i, 'Crowd Member ' . $i, array( 'wpcpm_student' ) );
	$crowd[] = 7000 + $i;
}

$c5 = attempt(
	static function () use ( $crowd ) {
		press( 'handle_plan', form( CHAMPION, array( 'wpcpm_sevent_date' => '2026-12-10', 'wpcpm_sevent_places' => '', 'wpcpm_sevent_title' => 'Champion webinar, fifth' ) ), CHAMPION_REP );
		$ids = event_ids();
		$id  = end( $ids );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		foreach ( $crowd as $person ) { add_post_meta( $id, '_wpcpm_sevent_person', $person ); }
		return $id;
	}
);

ck(
	'a change of the time with fifty-five on it: fifty told in the press, then the member and the contact, five left to the drain on the clock, and the outcome says everyone who joined is being told',
	static function () use ( $c5 ) {
		$GLOBALS['mail'] = array();
		$GLOBALS['cron'] = array();
		$left            = press( 'handle_change', form( CHAMPION, array( 'wpcpm_sevent_id' => (string) $c5, 'wpcpm_sevent_date' => '2026-12-11', 'wpcpm_sevent_places' => '', 'wpcpm_sevent_title' => 'Champion webinar, fifth' ) ), CHAMPION_REP );
		$sent            = mailed_to();
		return array( is_int( $c5 ), $left, count( $sent ), array_slice( $sent, 50 ), count( get_post_meta( $c5, '_wpcpm_sevent_pending', false ) ), array_column( $GLOBALS['cron'], 'hook' ) );
	},
	array( true, array( 'sevent-changed', 'events', CHAMPION, 'Everyone who joined is being told.' ), 52, array( 'chris.championrep@people.example', 'events@program.example' ), 5, array( 'wpcpm_sevent_drain' ) )
);
ck(
	'and its cancel the same: fifty told in the press, five left, and everyone who joined is being told',
	static function () use ( $c5 ) {
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_cancel', array( 'wpcpm_sponsor' => CHAMPION, 'wpcpm_sevent_id' => (string) $c5 ), CHAMPION_REP );
		return array( $left, count( mailed_to() ), count( get_post_meta( $c5, '_wpcpm_sevent_pending', false ) ), count( $GLOBALS['cron'] ) );
	},
	array( array( 'sevent-canceled', 'events', CHAMPION, 'Everyone who joined is being told.' ), 52, 5, 1 )
);

/* ---- once an event has taken place -------------------------------------------- */

echo "\n=== Once an event has taken place ===\n";

$e2 = attempt(
	static function () {
		// 8 pm in New York is already the next day in UTC and in Warsaw: the past list reads the day
		// on each viewer's own clock.
		press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2026-11-20', 'wpcpm_sevent_time' => '20:00', 'wpcpm_sevent_minutes' => '60', 'wpcpm_sevent_title' => 'Partners webinar, second' ) ), PARTNER_REP );
		$ids = event_ids();
		$e2  = end( $ids );
		WPCPM_Sponsor_Event::approve( $e2, MANAGER );
		add_post_meta( $e2, '_wpcpm_sevent_person', JOINER );
		add_post_meta( $e2, '_wpcpm_sevent_person', JOINER_TWO );
		set_now( utc( '2026-11-21 09:00' ) );
		return $e2;
	}
);

ck(
	'it is listed read only with its day on the viewer\'s clock and how many joined, and in the form\'s place the sentence that the term\'s event has taken place',
	static function () use ( $e2 ) {
		$html    = draw( PARTNER, PARTNER_REP );
		$manager = draw( PARTNER, MANAGER );
		return array( is_int( $e2 ), false !== strpos( $html, '<p class="wpcpm-student__note">Your event for this term has taken place already.</p><h4 class="wpcpm-sponsor__subheading">Past events</h4><ul class="wpcpm-sevent__past"><li><span class="wpcpm-sevent__title">Partners webinar, second</span> <span class="wpcpm-sevent__meta">November 20, 2026. 2 people joined.</span></li></ul>' ), false !== strpos( $manager, '<span class="wpcpm-sevent__meta">November 21, 2026. 2 people joined.</span>' ), strpos( $html, 'wpcpm-sevent__own' ), strpos( $html, 'wpcpm_sevent_plan' ), strpos( $html, 'Zelda' ) );
	},
	array( true, true, true, false, false, false )
);
ck(
	'a plan posted anyway is refused: the term\'s event has been held',
	static function () {
		$before = store_state();
		return array( press( 'handle_plan', form( PARTNER, array( 'wpcpm_sevent_date' => '2026-12-15' ) ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-term-used', 'events', PARTNER, '' ), true )
);
ck(
	'and the store refuses it in the flash\'s words: the event for this term has taken place already',
	static function () {
		$refused = WPCPM_Sponsor_Event::plan( PARTNER, store_fields( utc( '2026-12-15 15:00' ) ), PARTNER_REP, 'member' );
		return array( $refused->get_error_code(), $refused->get_error_message(), WPCPM_Sponsor_Event_Planner::messages()['sevent-term-used'][1] );
	},
	array( 'term_used', 'Your event for this term has taken place already.', 'Your event for this term has taken place already.' )
);
ck(
	'an event that has taken place is changed or canceled by nobody',
	static function () use ( $e2 ) {
		$before = store_state();
		return array( press( 'handle_change', form( PARTNER, array( 'wpcpm_sevent_id' => (string) $e2, 'wpcpm_sevent_date' => '2026-12-15' ) ), PARTNER_REP ), press( 'handle_cancel', array( 'wpcpm_sponsor' => PARTNER, 'wpcpm_sevent_id' => (string) $e2 ), PARTNER_REP ), store_state() === $before );
	},
	array( array( 'sevent-past', 'events', PARTNER, '' ), array( 'sevent-past', 'events', PARTNER, '' ), true )
);

/* ---- a term a program manager ended early --------------------------------------- */

echo "\n=== A term ended early ===\n";

// The Champion plans while its term runs, the program approves, and then a program manager ends the
// term on a day already past: the approved event outlives the term, and the tier no longer includes
// events, so no list offers the event and no change could keep it inside a term.
$lapsed_event = attempt(
	static function () {
		$planned = WPCPM_Sponsor_Event::plan( LAPSED, array( 'start' => utc( '2026-12-20 15:00' ), 'minutes' => 60, 'places' => 0, 'title' => 'Lapsed webinar', 'description' => 'Planned while the term ran.', 'link' => 'https://call.example/lapsed', 'zone' => 'Europe/Warsaw' ), LAPSED_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $planned, MANAGER );
		$GLOBALS['index'][ LAPSED ]['tier_until'] = '2026-11-20';
		return $planned;
	}
);

ck(
	'the sponsor reads its event as no longer offered, with Cancel alone, and neither the form nor a sentence in its place',
	static function () use ( $lapsed_event ) {
		$html = draw( LAPSED, LAPSED_REP );
		return array( is_int( $lapsed_event ), false !== strpos( $html, 'Approved, but no longer offered: ' ), false !== strpos( $html, 'name="wpcpm_sevent_id" value="' . $lapsed_event . '"' ) && false !== strpos( $html, 'Cancel the event' ), strpos( $html, 'wpcpm-sevent__change' ), strpos( $html, 'wpcpm_sevent_change' ), strpos( $html, 'wpcpm_sevent_plan' ) );
	},
	array( true, true, true, false, false, false )
);
ck(
	'a change posted anyway is refused as a tier without the co-hosted event, whatever day it names, before its form is read: no call link looked up, and nothing written or mailed',
	static function () use ( $lapsed_event ) {
		$before             = store_state();
		$GLOBALS['lookups'] = array();
		return array( press( 'handle_change', form( LAPSED, array( 'wpcpm_sevent_id' => (string) $lapsed_event, 'wpcpm_sevent_date' => '2026-12-20', 'wpcpm_sevent_time' => '16:00', 'wpcpm_sevent_link' => 'https://call.example/lapsed-room' ) ), LAPSED_REP ), press( 'handle_change', form( LAPSED, array( 'wpcpm_sevent_id' => (string) $lapsed_event, 'wpcpm_sevent_date' => '2026-11-21', 'wpcpm_sevent_time' => '23:00' ) ), LAPSED_REP ), $GLOBALS['lookups'], store_state() === $before );
	},
	array( array( 'sevent-no-tier', 'events', LAPSED, '' ), array( 'sevent-no-tier', 'events', LAPSED, '' ), array(), true )
);
ck(
	'it may still cancel the event',
	static function () use ( $lapsed_event ) {
		return array( press( 'handle_cancel', array( 'wpcpm_sponsor' => LAPSED, 'wpcpm_sevent_id' => (string) $lapsed_event ), LAPSED_REP ), meta( $lapsed_event, '_wpcpm_sevent_state' ) );
	},
	array( array( 'sevent-canceled', 'events', LAPSED, '' ), 'cancelled' )
);

/* ---- the date box after an early renewal -------------------------------------- */

echo "\n=== The date box after an early renewal ===\n";

// A Partner renewed early, on November 21: the term it renewed ends on January 31 and is owed its
// report, and the renewed term runs from February 1 to January 31 a year on. Its member keeps New
// York's clock, the site Warsaw's.
const RENEWED     = 'spoAAAA0000000006';
const RENEWED_REP = 10;

a_user( RENEWED_REP, 'Rene Renewedrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => RENEWED, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'America/New_York' ) );
$GLOBALS['index'][ RENEWED ] = array( 'record_id' => RENEWED, 'name' => 'Renewed Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2028-01-31', 'manager' => '', 'report_owed' => array( 'tier' => 'partner', 'until' => '2027-01-31', 'funded_line' => '' ) );
set_now( utc( '2026-11-21 09:00' ) );

// The renewed term's first day is February 1, but the term it renewed still runs to January 31,
// and the store counts a start before February 1 in that term: the box offers those days too.
ck(
	'a plan\'s date box starts on the site\'s today, not on the renewed term\'s first day: the term it renewed still runs, and a start before that first day is counted in it',
	static function () {
		$html = draw( RENEWED, RENEWED_REP );
		return array( WPCPM_Sponsor_Event::term_from( $GLOBALS['index'][ RENEWED ] ), false !== strpos( $html, 'id="wpcpm-sevent-new-date" name="wpcpm_sevent_date" value="" min="2026-11-21" max="2028-01-31" required />' ) );
	},
	array( '2027-02-01', true )
);

$renewed_event = attempt( static function () { return WPCPM_Sponsor_Event::plan( RENEWED, store_fields( utc( '2027-01-20 15:00' ) ), RENEWED_REP, 'member' ); } );

// An event of the renewed term keeps to it: the store refuses moving it back before February 1
// (before_term), so its box starts there, while an event of the term it renewed may move to any
// day from the site's today on, into the renewed term included.
ck(
	'a change box starts on the site\'s today for an event of the term it renewed, and on the renewed term\'s first day once the event is moved into that term, where the store keeps it',
	static function () use ( $renewed_event ) {
		$before = draw( RENEWED, RENEWED_REP );
		$moved  = WPCPM_Sponsor_Event::change( $renewed_event, store_fields( utc( '2027-03-10 03:00' ) ), RENEWED_REP );
		$after  = draw( RENEWED, RENEWED_REP );
		return array( is_int( $renewed_event ), false !== strpos( $before, 'name="wpcpm_sevent_date" value="2027-01-20" min="2026-11-21" max="2028-01-31" required />' ), is_array( $moved ) && ! empty( $moved['changed'] ), false !== strpos( $after, 'name="wpcpm_sevent_date" value="2027-03-09" min="2027-02-01" max="2028-01-31" required />' ) );
	},
	array( true, true, true, true )
);
ck(
	'and a change box never starts after the day it is drawn with, so the form as drawn can be saved: past midnight in Warsaw the site\'s day is the tenth, while the event is still on the ninth in New York',
	static function () {
		set_now( utc( '2027-03-09 23:30' ) );
		$rep     = draw( RENEWED, RENEWED_REP );
		$manager = draw( RENEWED, MANAGER );
		set_now( utc( '2026-11-21 09:00' ) );
		return array( false !== strpos( $rep, 'name="wpcpm_sevent_date" value="2027-03-09" min="2027-03-09" max="2028-01-31" required />' ), false !== strpos( $manager, 'name="wpcpm_sevent_date" value="2027-03-10" min="2027-03-10" max="2028-01-31" required />' ) );
	},
	array( true, true )
);
// The mirror image, east of the site: 10 pm in Warsaw on the term's last day is already 6 am the
// next day in Tokyo, where a program manager on Tokyo's clock reads it through the switcher. At
// 5 pm in Warsaw it is past midnight in Tokyo, and the box still starts on the site's day, not on
// Tokyo's.
ck(
	'nor does it end before the day it is drawn with: on the term\'s last evening in Warsaw the event is the next morning in Tokyo, and the form as drawn can be saved there too, from the site\'s today',
	static function () use ( $renewed_event ) {
		$moved = WPCPM_Sponsor_Event::change( $renewed_event, store_fields( utc( '2028-01-31 21:00' ) ), RENEWED_REP );
		set_now( utc( '2028-01-31 16:00' ) );
		$east    = draw( RENEWED, CONTACT );
		$manager = draw( RENEWED, MANAGER );
		set_now( utc( '2026-11-21 09:00' ) );
		return array( is_array( $moved ) && ! empty( $moved['changed'] ), false !== strpos( $east, 'name="wpcpm_sevent_date" value="2028-02-01" min="2028-01-31" max="2028-02-01" required />' ), false !== strpos( $manager, 'name="wpcpm_sevent_date" value="2028-01-31" min="2028-01-31" max="2028-01-31" required />' ) );
	},
	array( true, true, true )
);
// West of the site the edge falls the other way: 8 pm in New York on the term's last day is 2 am
// on February 1 in Warsaw, a day the store refuses. The box offered January 31, so the flash says
// which day is the last and on whose clock.
ck(
	'west of the site, the term\'s last evening is already the next day on the site\'s clock: a change to 8 pm in New York on the last day is refused with the last day and the site\'s clock for the flash to name, and nothing moves',
	static function () use ( $renewed_event ) {
		$before = store_state();
		$left   = press( 'handle_change', form( RENEWED, array( 'wpcpm_sevent_id' => (string) $renewed_event, 'wpcpm_sevent_date' => '2028-01-31', 'wpcpm_sevent_time' => '20:00' ) ), RENEWED_REP );
		return array( $left, store_state() === $before );
	},
	array( array( 'sevent-past-term', 'events', RENEWED, '', array( 'January 31, 2028', 'Europe / Warsaw' ) ), true )
);
ck(
	'moved back before the renewed term\'s first day, the event is refused with that first day and the site\'s clock for the flash to name, and nothing moves',
	static function () use ( $renewed_event ) {
		$before = store_state();
		$left   = press( 'handle_change', form( RENEWED, array( 'wpcpm_sevent_id' => (string) $renewed_event, 'wpcpm_sevent_date' => '2027-01-25', 'wpcpm_sevent_time' => '10:00' ) ), RENEWED_REP );
		return array( $left, store_state() === $before );
	},
	array( array( 'sevent-before-term', 'events', RENEWED, '', array( 'February 1, 2027', 'Europe / Warsaw' ) ), true )
);
ck(
	'the store refuses both in the words the flash fills in: the planner\'s copy, with the day and the clock the store names',
	static function () use ( $renewed_event ) {
		$row      = $GLOBALS['index'][ RENEWED ];
		$messages = WPCPM_Sponsor_Event_Planner::messages();
		$out      = array();
		foreach ( array( 'past_term' => array( utc( '2028-02-01 01:00' ), 'sevent-past-term' ), 'before_term' => array( utc( '2027-01-25 09:00' ), 'sevent-before-term' ) ) as $key => $asked ) {
			$refused = WPCPM_Sponsor_Event::change( $renewed_event, store_fields( $asked[0] ), RENEWED_REP );
			$out[]   = array( $refused->get_error_code(), $refused->get_error_message() === vsprintf( $messages[ $asked[1] ][1], WPCPM_Sponsor_Event::edge_of( $key, $row ) ), $refused->get_error_message() );
		}
		return $out;
	},
	array(
		array( 'past_term', true, 'Choose a start on or before January 31, 2028, the last day of your sponsorship term on the site\'s clock (Europe / Warsaw).' ),
		array( 'before_term', true, 'Choose a start on or after February 1, 2027, the first day of your current sponsorship term on the site\'s clock (Europe / Warsaw).' ),
	)
);

/* ---- extra events for one term ------------------------------------------------ */

echo "\n=== Extra events for one term ===\n";

// A Partner the program granted two more events for its term, to 31 January 2027, on the Sponsors
// screen's Tier box: the form says how many events the term allows. While one of them is still to
// take place and more can follow it, the card, the flash and the store say the next can be planned
// once it has taken place; while the one still to take place is the last the term holds, that the
// events for the term are planned already; and once the last has taken place, that they have taken
// place already. Granted none, or granted for another day, a sponsor reads what the checks above
// pin.
const EXTRA     = 'spoAAAA0000000007'; // Partner, the term runs to 31 January 2027, two more events granted for it.
const EXTRA_REP = 11;

a_user( EXTRA_REP, 'Ezra Extrarep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => EXTRA, 'wpcpm_sponsor_active' => 1 ) );
$GLOBALS['index'][ EXTRA ] = array( 'record_id' => EXTRA, 'name' => 'Extra Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31', 'manager' => '', 'event_allowance' => array( 'until' => '2027-01-31', 'extra' => 2 ) );
set_now( utc( '2026-11-21 09:00' ) );

ck(
	'the form says under its heading what the term allows, the tier\'s one and the two granted; granted none, or granted for another day, it says nothing of it, as before',
	static function () {
		$granted = draw( EXTRA, EXTRA_REP );
		$GLOBALS['index'][ EXTRA ]['event_allowance'] = array();
		$none = draw( EXTRA, EXTRA_REP );
		$GLOBALS['index'][ EXTRA ]['event_allowance'] = array( 'until' => '2026-01-31', 'extra' => 2 );
		$other = draw( EXTRA, EXTRA_REP );
		$GLOBALS['index'][ EXTRA ]['event_allowance'] = array( 'until' => '2027-01-31', 'extra' => 2 );
		return array(
			false !== strpos( $granted, '<h4 class="wpcpm-sponsor__subheading">Plan your co-hosted event</h4><p class="wpcpm-student__note">Your term allows 3 events.</p><form method="post"' ),
			substr_count( $granted, 'Your term allows' ),
			false !== strpos( $none, '<h4 class="wpcpm-sponsor__subheading">Plan your co-hosted event</h4><form method="post"' ),
			strpos( $none, 'Your term allows' ),
			strpos( $other, 'Your term allows' ),
		);
	},
	array( true, 1, true, false, false )
);

$x1 = attempt(
	static function () {
		press( 'handle_plan', form( EXTRA, array( 'wpcpm_sevent_date' => '2026-12-01', 'wpcpm_sevent_title' => 'Extra webinar, first' ) ), EXTRA_REP );
		$ids = event_ids();
		return end( $ids );
	}
);

ck(
	'with one of its events still to take place and two more granted, none of them held yet, the card says in the form\'s place that the next can be planned once this one has taken place, since more can follow it',
	static function () use ( $x1 ) {
		$html = draw( EXTRA, EXTRA_REP );
		return array( is_int( $x1 ) && EXTRA === WPCPM_Sponsor_Event::sponsor_of( get_post( $x1 ) ), false !== strpos( $html, '</form></div><p class="wpcpm-student__note">Your next event can be planned once this one has taken place.</p>' ), strpos( $html, 'planned already' ), strpos( $html, 'wpcpm_sevent_plan' ) );
	},
	array( true, true, false, false )
);
ck(
	'a plan pressed anyway is refused in the same words, and the store refuses it in them too, for a caller that reads its sentence; nothing is written or mailed',
	static function () {
		$before  = store_state();
		$left    = press( 'handle_plan', form( EXTRA, array( 'wpcpm_sevent_date' => '2026-12-20' ) ), EXTRA_REP );
		$refused = WPCPM_Sponsor_Event::plan( EXTRA, store_fields( utc( '2026-12-20 15:00' ) ), EXTRA_REP, 'member' );
		return array( $left, $refused->get_error_code(), $refused->get_error_message(), WPCPM_Sponsor_Event_Planner::messages()['sevent-one-open'], store_state() === $before );
	},
	array( array( 'sevent-one-open', 'events', EXTRA, '' ), 'one_open', 'Your next event can be planned once this one has taken place.', array( 'error', 'Your next event can be planned once this one has taken place.' ), true )
);
ck(
	'once that event has been approved and has taken place, the term takes more: the form is back, saying what the term allows, with no sentence in its place',
	static function () use ( $x1 ) {
		WPCPM_Sponsor_Event::approve( $x1, MANAGER );
		set_now( utc( '2026-12-02 09:00' ) );
		$html = draw( EXTRA, EXTRA_REP );
		set_now( utc( '2026-11-21 09:00' ) );
		return array( meta( $x1, '_wpcpm_sevent_state' ), false !== strpos( $html, 'Plan your co-hosted event</h4><p class="wpcpm-student__note">Your term allows 3 events.</p><form method="post"' ), strpos( $html, 'planned already' ), strpos( $html, 'taken place already' ) );
	},
	array( 'approved', true, false, false )
);

// The second and the third: the second held on 10 December, the third planned for 20 December, the
// last the term holds, the tier's one and the two granted.
$x3 = attempt(
	static function () {
		set_now( utc( '2026-12-02 09:00' ) );
		press( 'handle_plan', form( EXTRA, array( 'wpcpm_sevent_date' => '2026-12-10', 'wpcpm_sevent_title' => 'Extra webinar, second' ) ), EXTRA_REP );
		$ids = event_ids();
		WPCPM_Sponsor_Event::approve( end( $ids ), MANAGER );
		set_now( utc( '2026-12-11 09:00' ) );
		press( 'handle_plan', form( EXTRA, array( 'wpcpm_sevent_date' => '2026-12-20', 'wpcpm_sevent_title' => 'Extra webinar, third' ) ), EXTRA_REP );
		$ids = event_ids();
		return end( $ids );
	}
);

ck(
	'with two of the term\'s events held and the third still to take place, the last the term holds, the card says in the form\'s place that its events for this term are planned already',
	static function () use ( $x3 ) {
		$html = draw( EXTRA, EXTRA_REP );
		return array( is_int( $x3 ) ? get_post( $x3 )->post_title : 'no third event', false !== strpos( $html, '</form></div><p class="wpcpm-student__note">Your events for this term are planned already.</p>' ), strpos( $html, 'once this one has taken place' ), strpos( $html, 'wpcpm_sevent_plan' ) );
	},
	array( 'Extra webinar, third', true, false, false )
);
ck(
	'a plan pressed anyway is refused in the same words, and the store refuses it in them too; nothing is written or mailed',
	static function () {
		$before  = store_state();
		$left    = press( 'handle_plan', form( EXTRA, array( 'wpcpm_sevent_date' => '2027-01-20' ) ), EXTRA_REP );
		$refused = WPCPM_Sponsor_Event::plan( EXTRA, store_fields( utc( '2027-01-20 15:00' ) ), EXTRA_REP, 'member' );
		return array( $left, $refused->get_error_code(), $refused->get_error_message(), WPCPM_Sponsor_Event_Planner::messages()['sevent-one-open-more'], store_state() === $before );
	},
	array( array( 'sevent-one-open-more', 'events', EXTRA, '' ), 'one_open', 'Your events for this term are planned already.', array( 'error', 'Your events for this term are planned already.' ), true )
);
ck(
	'once the third has been approved and has taken place, the term is spent: the card says its events for this term have taken place already, a plan pressed anyway is refused in those words, and the store refuses it in them too',
	static function () use ( $x3 ) {
		WPCPM_Sponsor_Event::approve( $x3, MANAGER );
		set_now( utc( '2026-12-21 09:00' ) );
		$html    = draw( EXTRA, EXTRA_REP );
		$before  = store_state();
		$left    = press( 'handle_plan', form( EXTRA, array( 'wpcpm_sevent_date' => '2027-01-20' ) ), EXTRA_REP );
		$refused = WPCPM_Sponsor_Event::plan( EXTRA, store_fields( utc( '2027-01-20 15:00' ) ), EXTRA_REP, 'member' );
		$same    = store_state() === $before;
		set_now( utc( '2026-11-21 09:00' ) );
		return array( false !== strpos( $html, '<p class="wpcpm-student__note">Your events for this term have taken place already.</p>' ), strpos( $html, 'Your event for this term has taken place already.' ), $left, $refused->get_error_code(), $refused->get_error_message(), WPCPM_Sponsor_Event_Planner::messages()['sevent-term-used-more'], $same );
	},
	array( true, false, array( 'sevent-term-used-more', 'events', EXTRA, '' ), 'term_used', 'Your events for this term have taken place already.', array( 'error', 'Your events for this term have taken place already.' ), true )
);

/* ---- a refused form comes back with what was typed ------------------------------- */

echo "\n=== A refused form comes back with what was typed ===\n";

// A Champion with no term and two members, and a second Champion a program manager looks at
// through the switcher. A plan or a change refused for a reason the person puts right by pressing
// again keeps what it posted, as the request readers read it, for the person who pressed; the form
// it came from reads it once, the next time a page draws it, and every other outcome keeps nothing.
const TYPED         = 'spoAAAA0000000008'; // Champion, no term, two members.
const TYPED_OTHER   = 'spoAAAA0000000010'; // Champion, no term: another sponsor to look at.
const TYPED_REP     = 12;
const TYPED_REP_TWO = 13;

a_user( TYPED_REP, 'Tara Typedrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => TYPED, 'wpcpm_sponsor_active' => 1 ) );
a_user( TYPED_REP_TWO, 'Tom Typedrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => TYPED, 'wpcpm_sponsor_active' => 1 ) );
$GLOBALS['index'][ TYPED ]       = array( 'record_id' => TYPED, 'name' => 'Typed Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' );
$GLOBALS['index'][ TYPED_OTHER ] = array( 'record_id' => TYPED_OTHER, 'name' => 'Other Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' );
set_now( utc( '2026-11-21 09:00' ) );

/**
 * What a press types, the length one the form refuses: a quote, an ampersand and a backslash in
 * the title, a quote, an ampersand and a line break in the description, and a call link holding
 * markup and a percent-encoded character; any value replaced.
 *
 * @param string $record The sponsor the form names.
 * @param array  $over   Values to replace.
 * @return array
 */
function typed_form( $record, array $over = array() ) {
	return array_merge(
		array(
			'wpcpm_sponsor'            => $record,
			'wpcpm_sevent_date'        => '2026-12-01',
			'wpcpm_sevent_time'        => '14:30',
			'wpcpm_sevent_minutes'     => '5',
			'wpcpm_sevent_places'      => '25',
			'wpcpm_sevent_title'       => 'Typed "Q&A" in C:\\drafts',
			'wpcpm_sevent_description' => "Typed & \"kept\".\nSecond line.",
			'wpcpm_sevent_link'        => 'https://call.example/typed?pwd=a%2Fb&x="><b>bold</b>',
		),
		$over
	);
}

/**
 * What the seven rows of one form are drawn with, read from the markup as printed, escaped; null
 * for a row the markup does not hold.
 *
 * @param string $html   The planner's markup.
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

/** What a box shows of the markup it was drawn with: the text a browser reads from it, entities decoded once. */
function box_text( $markup ) {
	return htmlspecialchars_decode( (string) $markup, ENT_QUOTES );
}

// The title and the description come back exactly as typed, the description after a line feed of
// its own, which the parser drops, so a typing that begins with a line break keeps it.
$typed_rows = array(
	'date'        => '2026-12-01',
	'time'        => '14:30',
	'minutes'     => '5',
	'places'      => '25',
	'title'       => 'Typed &quot;Q&amp;A&quot; in C:\\drafts',
	'description' => "\nTyped &amp; &quot;kept&quot;.\nSecond line.",
	'link'        => 'https://call.example/typed?pwd=a%2Fb&amp;x=&quot;&gt;&lt;b&gt;bold&lt;/b&gt;',
);
$empty_rows = array( 'date' => '', 'time' => '', 'minutes' => '60', 'places' => '', 'title' => '', 'description' => '', 'link' => '' );

ck(
	'a plan refused for its fields comes back with every one of the seven values as typed, each escaped as the rows escape a stored value, the call link as typed with its percent-encoded character and its markup printed as text; a reload draws the form as it is drawn today',
	static function () {
		$left   = press( 'handle_plan', typed_form( TYPED ), TYPED_REP );
		$first  = draw( TYPED, TYPED_REP );
		$second = draw( TYPED, TYPED_REP );
		return array( $left, rows_of( $first, 'wpcpm-sevent-new' ), substr_count( $first, '<b>' ), rows_of( $second, 'wpcpm-sevent-new' ) );
	},
	array( array( 'sevent-fields', 'events', TYPED, 'The length has to be between 15 and 480 minutes.' ), $typed_rows, 0, $empty_rows )
);
ck(
	'what is kept is what the form posted, as the request readers read it, unslashed, with the sponsor it was for and no event, for the person who pressed alone',
	static function () {
		press( 'handle_plan', typed_form( TYPED ), TYPED_REP );
		$kept = kept_for( TYPED_REP );
		draw( TYPED, TYPED_REP );
		return array( $kept, kept_for( TYPED_REP_TWO ), kept_for( TYPED_REP ) );
	},
	array(
		array(
			'sponsor' => TYPED,
			'event'   => 0,
			'values'  => array(
				'date'        => '2026-12-01',
				'time'        => '14:30',
				'minutes'     => '5',
				'places'      => '25',
				'title'       => 'Typed "Q&A" in C:\\drafts',
				'description' => "Typed & \"kept\".\nSecond line.",
				'link'        => 'https://call.example/typed?pwd=a%2Fb&x="><b>bold</b>',
			),
		),
		null,
		null,
	)
);

// What is kept is bounded by what a box lets a person type, so a crafted post cannot park a value of
// any length in the presser's meta: the title's 120, the description's 2,000 and the link's 500, as
// the rows and the store define them, and 20 for the four short boxes. A longer value is kept as
// an empty string on the planner's channel; one at its limit is kept whole. The title and the
// description the form draws are the ones kept exactly as typed, which come back cut to the room
// their box was drawn with, as every kept typing does (`WPCPM_Typed_Text::keep()`).
ck(
	'a value longer than its box lets a person type comes back empty while the others come back as typed: the date, the places and the link; the title and the description come back cut to their box',
	static function () use ( $typed_rows ) {
		press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_title' => str_repeat( 't', 121 ) ) ), TYPED_REP );
		$title = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-new' );
		press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_date' => '2026-12-01' . str_repeat( '9', 11 ), 'wpcpm_sevent_places' => str_repeat( '9', 21 ), 'wpcpm_sevent_description' => str_repeat( 'd', 2001 ), 'wpcpm_sevent_link' => 'https://call.example/' . str_repeat( 'l', 480 ) ) ), TYPED_REP );
		$rest = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-new' );
		return array( $title === array_merge( $typed_rows, array( 'title' => str_repeat( 't', 120 ) ) ), $rest === array_merge( $typed_rows, array( 'date' => '', 'places' => '', 'description' => "\n" . str_repeat( 'd', 2000 ), 'link' => '' ) ) );
	},
	array( true, true )
);
ck(
	'a value at its limit comes back whole: a title of 120, a description of 2,000 over two lines, a link of 500 and a length of 20 characters',
	static function () {
		$description = str_repeat( 'd', 999 ) . "\n" . str_repeat( 'e', 1000 );
		$link        = 'https://call.example/' . str_repeat( 'l', 479 );
		press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_minutes' => str_repeat( '9', 20 ), 'wpcpm_sevent_title' => str_repeat( 't', 120 ), 'wpcpm_sevent_description' => $description, 'wpcpm_sevent_link' => $link ) ), TYPED_REP );
		$rows = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-new' );
		return array( mb_strlen( $description ), mb_strlen( $link ), $rows['minutes'] === str_repeat( '9', 20 ), $rows['title'] === str_repeat( 't', 120 ), $rows['description'] === "\n" . $description, $rows['link'] === $link );
	},
	array( 2000, 500, true, true, true, true )
);
ck(
	'a description posts each line break as two bytes, and each is counted once: 2,000 characters over ten lines are kept whole with their line breaks as posted, and one more character is kept as an empty string',
	static function () {
		$whole = implode( "\r\n", array_fill( 0, 10, str_repeat( 'x', 199 ) ) ) . 'x';
		$over  = $whole . 'x';
		$kept  = array();
		$GLOBALS['uid'] = TYPED_REP;
		foreach ( array( $whole, $over ) as $description ) {
			WPCPM_Flash::new_request();
			WPCPM_Sponsor_Event_Planner::keep_typed( 'sevent-fields', WPCPM_Sponsor_Event_Planner::KEEPS_TYPED, array( 'date' => '2026-12-01', 'time' => '14:30', 'minutes' => '5', 'places' => '', 'title' => 'Ten lines', 'description' => $description, 'link' => 'https://call.example/lines' ), TYPED, 0 );
			$value  = kept_for( TYPED_REP );
			$kept[] = is_array( $value ) ? $value['values']['description'] : $value;
		}
		draw( TYPED, TYPED_REP );
		return array( strlen( $whole ), $kept[0] === $whole, $kept[1] );
	},
	array( 2009, true, '' )
);

// The keeper counts what a person typed, as the store and the box do: the entities core's cleaner
// writes for a "<" that opens no tag, and for the quote marks and the ampersand after it, read back as
// the one character each stands for, and a line break counted once. Through the press, as a browser
// posts it, so the cleaner and the keeper meet as they do on a site.
$typed_title_120 = 'Why 2 < 3, and "Q&A"' . str_repeat( 't', 100 );
$typed_story     = 'Is 2 < 3? "Yes" & more.' . str_repeat( 'd', 1977 );
$typed_lines     = implode( "\r\n", array_fill( 0, 10, str_repeat( 'x', 199 ) ) ) . 'x';

ck(
	'a title of 120 characters as typed that holds a < , quote marks and an ampersand, in a plan refused for its length, comes back whole as the cleaner left it, though the cleaner made it 137; one more character comes back empty',
	static function () use ( $typed_title_120 ) {
		$kept = array();
		foreach ( array( $typed_title_120, $typed_title_120 . 't' ) as $title ) {
			press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_title' => $title ) ), TYPED_REP );
			$value  = kept_for( TYPED_REP );
			$kept[] = is_array( $value ) ? $value['values']['title'] : $value;
			draw( TYPED, TYPED_REP );
		}
		return array( mb_strlen( $typed_title_120 ), $kept[0] === 'Why 2 &lt; 3, and &quot;Q&amp;A&quot;' . str_repeat( 't', 100 ), mb_strlen( (string) $kept[0] ), $kept[1] );
	},
	array( 120, true, 137, '' )
);
ck(
	'a description of 2,000 characters as typed over ten lines, and one that holds a < , quote marks and an ampersand, come back whole through the press as the cleaner left them; one more character comes back empty',
	static function () use ( $typed_lines, $typed_story ) {
		$kept = array();
		foreach ( array( $typed_lines, $typed_lines . 'x', $typed_story, $typed_story . 'd' ) as $description ) {
			press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_description' => $description ) ), TYPED_REP );
			$value  = kept_for( TYPED_REP );
			$kept[] = is_array( $value ) ? $value['values']['description'] : $value;
			draw( TYPED, TYPED_REP );
		}
		return array( $kept[0] === $typed_lines, $kept[1], $kept[2] === 'Is 2 &lt; 3? &quot;Yes&quot; &amp; more.' . str_repeat( 'd', 1977 ), $kept[3] );
	},
	array( true, '', true, '' )
);
ck(
	'the keeper keeps exactly what the store accepts and drops exactly what it refuses, for the title, the description and the call link, at both edges, with and without a < , a quote mark, an ampersand and a line break in them; the link is counted as typed, an entity in it counting for all its characters',
	static function () use ( $typed_title_120, $typed_story, $typed_lines ) {
		$cases = array(
			'title'       => array( str_repeat( 't', 120 ), str_repeat( 't', 121 ), $typed_title_120, $typed_title_120 . 't', 'Why 2 < 3', '"Q&A" ' . str_repeat( 't', 114 ), '"Q&A" ' . str_repeat( 't', 115 ) ),
			'description' => array( str_repeat( 'd', 2000 ), str_repeat( 'd', 2001 ), $typed_lines, $typed_lines . 'x', $typed_story, $typed_story . 'd', "a\r\nb\r\nc" ),
			'link'        => array( 'https://call.example/q?a=1&amp;b=2' . str_repeat( 'l', 466 ), 'https://call.example/q?a=1&amp;b=2' . str_repeat( 'l', 467 ) ),
		);
		$zone   = new DateTimeZone( 'UTC' );
		$agree  = array();
		$refuse = array();
		foreach ( $cases as $field => $values ) {
			foreach ( $values as $typed ) {
				press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_' . $field => $typed ) ), TYPED_REP );
				$value     = kept_for( TYPED_REP );
				$refused   = isset( WPCPM_Sponsor_Event::fields_from( WPCPM_Sponsor_Event_Planner::posted_fields(), $zone )['errors'][ $field ] );
				$agree[]   = is_array( $value ) && ( '' !== $value['values'][ $field ] ) === ! $refused;
				$refuse[]  = $refused;
				draw( TYPED, TYPED_REP );
			}
		}
		return array( count( array_filter( $agree ) ) === count( $agree ), $refuse );
	},
	array( true, array( false, true, false, true, false, false, true, false, true, false, true, false, true, false, false, true ) )
);
ck(
	'a plan the store refuses comes back with what was typed too: a day and a time that have passed, and the sponsor\'s lock held',
	static function () {
		$late       = press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_date' => '2026-11-20', 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_link' => 'https://call.example/late' ) ), TYPED_REP );
		$after_late = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-new' );
		add_option( 'wpcpm_sevent_lock_' . TYPED, $GLOBALS['now'] );
		$busy = press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_minutes' => '50', 'wpcpm_sevent_link' => 'https://call.example/busy' ) ), TYPED_REP );
		delete_option( 'wpcpm_sevent_lock_' . TYPED );
		$after_busy = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-new' );
		return array( $late, array( $after_late['date'], $after_late['minutes'], $after_late['title'], $after_late['link'] ), $busy, array( $after_busy['date'], $after_busy['minutes'], $after_busy['link'] ) );
	},
	array(
		array( 'sevent-in-past', 'events', TYPED, '' ),
		array( '2026-11-20', '45', 'Typed &quot;Q&amp;A&quot; in C:\\drafts', 'https://call.example/late' ),
		array( 'sevent-busy', 'events', TYPED, '' ),
		array( '2026-12-01', '50', 'https://call.example/busy' ),
	)
);
ck(
	'what was typed is the pressing person\'s own: the sponsor\'s other member is drawn the form as it is drawn today, and the member who pressed still finds it',
	static function () use ( $typed_rows, $empty_rows ) {
		press( 'handle_plan', typed_form( TYPED ), TYPED_REP );
		$other = rows_of( draw( TYPED, TYPED_REP_TWO ), 'wpcpm-sevent-new' );
		$own   = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-new' );
		return array( $other === $empty_rows, $own === $typed_rows );
	},
	array( true, true )
);
ck(
	'a program manager\'s typing for one sponsor comes back on that sponsor\'s form; looking at another sponsor through the switcher first, they are drawn its form as it is drawn today, and that look drops the typing unread: back on the first sponsor, the form is drawn as it is drawn today',
	static function () use ( $typed_rows, $empty_rows ) {
		press( 'handle_plan', typed_form( TYPED ), MANAGER );
		$same  = rows_of( draw( TYPED, MANAGER ), 'wpcpm-sevent-new' );
		$left  = press( 'handle_plan', typed_form( TYPED ), MANAGER );
		$other = rows_of( draw( TYPED_OTHER, MANAGER ), 'wpcpm-sevent-new' );
		$back  = rows_of( draw( TYPED, MANAGER ), 'wpcpm-sevent-new' );
		return array( $same === $typed_rows, $left, $other === $empty_rows, $back === $empty_rows );
	},
	array( true, array( 'sevent-fields', 'events', TYPED, 'The length has to be between 15 and 480 minutes.' ), true, true )
);
ck(
	'a sponsor that no longer holds the planner keeps nothing of what its press typed, refused before the form is read, while the same press keeps it once the sponsor holds the planner again',
	static function () use ( $empty_rows ) {
		$GLOBALS['index'][ TYPED ]['status'] = 'Paused';
		try {
			$paused      = press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_title' => 'Typed while paused' ) ), TYPED_REP );
			$kept_paused = kept_for( TYPED_REP );
		} finally {
			$GLOBALS['index'][ TYPED ]['status'] = 'Approved';
		}
		$rows      = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-new' );
		$held      = press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_title' => 'Typed while held' ) ), TYPED_REP );
		$kept_held = kept_for( TYPED_REP );
		draw( TYPED, TYPED_REP );
		return array( $paused, $kept_paused, $rows === $empty_rows, $held[0], is_array( $kept_held ) ? $kept_held['values']['title'] : $kept_held );
	},
	array( array( 'sevent-no-tier', 'events', TYPED, '' ), null, true, 'sevent-fields', 'Typed while held' )
);

// The plan the store takes, after a refusal whose typing nothing has drawn yet.
$typed_sent = attempt(
	static function () {
		press( 'handle_plan', typed_form( TYPED ), TYPED_REP );
		$before = kept_for( TYPED_REP );
		$left   = press( 'handle_plan', typed_form( TYPED, array( 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_title' => 'Typed webinar', 'wpcpm_sevent_link' => 'https://call.example/typed-webinar' ) ), TYPED_REP );
		return array( is_array( $before ), $left, kept_for( TYPED_REP ) );
	}
);
$t1 = attempt( static function () { $ids = event_ids(); return end( $ids ); } );

ck(
	'a plan that succeeds keeps nothing and drops what the refusal before it kept; the event it planned is drawn with its Change form filled from the event',
	static function () use ( $typed_sent, $t1 ) {
		$rows = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-' . $t1 );
		return array( $typed_sent, WPCPM_Sponsor_Event::sponsor_of( get_post( $t1 ) ), $rows );
	},
	array(
		array( true, array( 'sevent-sent', 'events', TYPED, '' ), null ),
		TYPED,
		array( 'date' => '2026-12-01', 'time' => '14:30', 'minutes' => '45', 'places' => '25', 'title' => 'Typed webinar', 'description' => "Typed &amp; &quot;kept&quot;.\nSecond line.", 'link' => 'https://call.example/typed-webinar' ),
	)
);

$changed_rows = array(
	'date'        => '2026-12-02',
	'time'        => '16:45',
	'minutes'     => '5',
	'places'      => '30',
	'title'       => 'Changed &quot;B&amp;C&quot;',
	'description' => "\nChanged &amp; typed.",
	'link'        => 'https://call.example/changed?pwd=c%2Fd&amp;y=&quot;&gt;&lt;i&gt;it&lt;/i&gt;',
);

/** A change of the event typed over every row, the length one the form refuses; any value replaced. */
function typed_change( $event_id, array $over = array() ) {
	return typed_form( TYPED, array_merge( array( 'wpcpm_sevent_id' => (string) $event_id, 'wpcpm_sevent_date' => '2026-12-02', 'wpcpm_sevent_time' => '16:45', 'wpcpm_sevent_places' => '30', 'wpcpm_sevent_title' => 'Changed "B&C"', 'wpcpm_sevent_description' => 'Changed & typed.', 'wpcpm_sevent_link' => 'https://call.example/changed?pwd=c%2Fd&y="><i>it</i>' ), $over ) );
}

ck(
	'a change refused for its fields comes back with what was typed in that event\'s form, kept with the event it was for, and a reload draws the event again',
	static function () use ( $t1, $changed_rows ) {
		$left   = press( 'handle_change', typed_change( $t1 ), TYPED_REP );
		$kept   = kept_for( TYPED_REP );
		$first  = draw( TYPED, TYPED_REP );
		$second = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-' . $t1 );
		return array( $left, is_array( $kept ) ? array( $kept['sponsor'], $kept['event'] ) : $kept, rows_of( $first, 'wpcpm-sevent-' . $t1 ) === $changed_rows, substr_count( $first, '<i>' ), array( $second['title'], $second['minutes'], $second['date'] ) );
	},
	array( array( 'sevent-fields', 'events', TYPED, 'The length has to be between 15 and 480 minutes.' ), array( TYPED, $t1 ), true, 0, array( 'Typed webinar', '45', '2026-12-01' ) )
);
ck(
	'and so does a change the store refuses, a day and a time that have passed',
	static function () use ( $t1 ) {
		$left = press( 'handle_change', typed_change( $t1, array( 'wpcpm_sevent_date' => '2026-11-20', 'wpcpm_sevent_minutes' => '30' ) ), TYPED_REP );
		$rows = rows_of( draw( TYPED, TYPED_REP ), 'wpcpm-sevent-' . $t1 );
		return array( $left, array( $rows['date'], $rows['minutes'], $rows['title'] ) );
	},
	array( array( 'sevent-in-past', 'events', TYPED, '' ), array( '2026-11-20', '30', 'Changed &quot;B&amp;C&quot;' ) )
);
ck(
	'another event\'s Change form is drawn with its own stored values: what a refused change typed for an event since canceled is drawn in no form, and the look drops it',
	static function () use ( $t1 ) {
		$left = press( 'handle_change', typed_change( $t1 ), TYPED_REP );
		WPCPM_Sponsor_Event::cancel( $t1, TYPED_REP );
		$t2   = WPCPM_Sponsor_Event::plan( TYPED, store_fields( utc( '2026-12-20 10:00' ) ), TYPED_REP, 'member' );
		$html = draw( TYPED, TYPED_REP );
		$rows = rows_of( $html, 'wpcpm-sevent-' . $t2 );
		return array( $left[0], is_int( $t2 ), array( $rows['date'], $rows['time'], $rows['minutes'], $rows['title'], $rows['link'] ), substr_count( $html, 'Changed' ), kept_for( TYPED_REP ) );
	},
	array( 'sevent-fields', true, array( '2026-12-20', '11:00', '60', 'Asked of the store', 'https://call.example/store' ), 0, null )
);
ck(
	'the Change form\'s fold is drawn open when its form comes back with what was typed, and closed without it, on the next draw, and while what was kept is another event\'s; the Plan form sits in no fold',
	static function () {
		$events = WPCPM_Sponsor_Event::upcoming_of( TYPED );
		$event  = 1 === count( $events ) ? (int) $events[0]->ID : 0;
		$open   = '<details class="wpcpm-sevent__change" open><summary class="wpcpm-sevent__change-toggle">Change the event</summary>';
		$closed = '<details class="wpcpm-sevent__change"><summary class="wpcpm-sevent__change-toggle">Change the event</summary>';
		$folds  = static function ( $html ) use ( $open, $closed ) { return array( substr_count( $html, $open ), substr_count( $html, $closed ) ); };
		$before = draw( TYPED, TYPED_REP );
		press( 'handle_change', typed_change( $event ), TYPED_REP );
		$typed = draw( TYPED, TYPED_REP );
		$after = draw( TYPED, TYPED_REP );
		// A change typed for the canceled event is refused for its fields before the store is asked, so it is kept.
		$canceled = array_values( array_diff( event_ids(), array( $event ) ) );
		press( 'handle_change', typed_change( end( $canceled ) ), TYPED_REP );
		$other = draw( TYPED, TYPED_REP );
		ob_start();
		WPCPM_Sponsor_Event_Planner::render_fields( 'wpcpm-sevent-check', array( 'date' => '', 'time' => '', 'minutes' => '60', 'places' => '', 'title' => '', 'description' => '', 'link' => '' ), new DateTimeZone( 'UTC' ), 1, '2026-10-06', '' );
		$rows = (string) ob_get_clean();
		return array( $event > 0, $folds( $before ), $folds( $typed ), $folds( $after ), $folds( $other ), substr_count( $rows, '<details' ) );
	},
	array( true, array( 0, 1 ), array( 1, 0 ), array( 0, 1 ), array( 0, 1 ), 0 )
);
ck(
	'the outcomes that keep what was typed are the ones a person puts right by pressing again: the form\'s refusal, a start outside the term, a start that has passed, a lock held, fewer places than the people on it, and a write that failed; each is one of the planner\'s outcomes',
	static function () {
		$keeps = WPCPM_Sponsor_Event_Planner::KEEPS_TYPED;
		return array( $keeps, array_values( array_diff( $keeps, array_keys( WPCPM_Sponsor_Event_Planner::messages() ) ) ) );
	},
	array( array( 'sevent-fields', 'sevent-past-term', 'sevent-before-term', 'sevent-in-past', 'sevent-busy', 'sevent-shrink', 'sevent-failed' ), array() )
);

// What comes back is what was typed, through the plugin's own flash over user meta that unslashes
// what it writes, as core's does: a backslash in the title, two in a row in the call link and a
// shrug over two lines in the description. The dashboard's own channel waits beside the typing and
// is taken first, so the typing is written again before the form reads it. Each look is by a member
// of its own, since the real flash remembers what it took for the rest of the process. The title and
// the description are kept beside the typing exactly as typed, under the form's own name, which the
// press itself cleared first: this process has taken that name for the person already, so what the
// flash holds after the look is that entry alone, for the next page to take, as it was typed. The
// look here draws as a look made once that entry has expired does: the channel's other rows, with
// the title and the description the form holds of its own, empty for a plan and the event's for a
// change, never the cleaner's text the channel holds for the two.
const KEPT_PLAN_REP   = 14; // The other Champion's member, whose Plan form is drawn.
const KEPT_CHANGE_REP = 15; // A member of the Champion whose event is still to take place.

a_user( KEPT_PLAN_REP, 'Kim Keptplan', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => TYPED_OTHER, 'wpcpm_sponsor_active' => 1 ) );
a_user( KEPT_CHANGE_REP, 'Kai Keptchange', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => TYPED, 'wpcpm_sponsor_active' => 1 ) );

/** The backslashes typed into a refused form, the length the form refuses; any value replaced. */
function slashed_form( $record, array $over = array() ) {
	return typed_form(
		$record,
		array_merge(
			array(
				'wpcpm_sevent_title'       => 'Typed "Q&A" in C:\drafts',
				'wpcpm_sevent_description' => '¯\_(ツ)_/¯ typed on one line' . "\n" . 'and ¯\_(ツ)_/¯ on the next',
				'wpcpm_sevent_link'        => 'https://call.example/kept?pwd=a%2Fb&path=two\\\\in-a-row',
			),
			$over
		)
	);
}

$slashed_rows = array(
	'title'       => 'Typed &quot;Q&amp;A&quot; in C:\drafts',
	'description' => '¯\_(ツ)_/¯ typed on one line' . "\n" . 'and ¯\_(ツ)_/¯ on the next',
	'link'        => 'https://call.example/kept?pwd=a%2Fb&amp;path=two\\\\in-a-row',
);
$slashed_boxes = array(
	'title'       => 'Typed "Q&A" in C:\drafts',
	'description' => '¯\_(ツ)_/¯ typed on one line' . "\n" . 'and ¯\_(ツ)_/¯ on the next',
);

/** What a person's flash holds in user meta, by channel, as the real flash wrote it. */
function flash_meta( $uid ) {
	return isset( $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] ) && is_array( $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] ) ? $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] : array();
}

/** The typing a flash entry keeps under a form's name, or null. */
function flash_typed( array $meta, $name ) {
	return isset( $meta[ 'typed:' . $name ]['typed'] ) ? $meta[ 'typed:' . $name ]['typed'] : null;
}

ck(
	'through the plugin\'s own flash, a Plan form refused for its fields comes back with the two in a row in the call link exactly as typed, its title and description boxes empty, as the plan drew them, and the flash holds nothing after but the title\'s backslash and the shrug over two lines kept exactly as typed under the form\'s name',
	static function () use ( $slashed_rows, $slashed_boxes ) {
		return through_real_flash(
			static function () use ( $slashed_rows, $slashed_boxes ) {
				$left = press( 'handle_plan', slashed_form( TYPED_OTHER ), KEPT_PLAN_REP );
				$rows = rows_of( draw( TYPED_OTHER, KEPT_PLAN_REP ), 'wpcpm-sevent-new' );
				$meta = flash_meta( KEPT_PLAN_REP );
				return array( $left[0], $rows['link'] === $slashed_rows['link'], array( $rows['title'], $rows['description'], $rows['link'] ), array_keys( $meta ), flash_typed( $meta, 'sevent:' . TYPED_OTHER . ':new' ) === $slashed_boxes );
			}
		);
	},
	array( 'sevent-fields', true, array( '', '', $slashed_rows['link'] ), array( 'typed:sevent:' . TYPED_OTHER . ':new' ), true )
);
ck(
	'and so does the Change form of the sponsor\'s event still to take place, its title and description boxes holding the event\'s own',
	static function () use ( $slashed_rows, $slashed_boxes ) {
		return through_real_flash(
			static function () use ( $slashed_rows, $slashed_boxes ) {
				$events = WPCPM_Sponsor_Event::upcoming_of( TYPED );
				$event  = 1 === count( $events ) ? (int) $events[0]->ID : 0;
				$left   = press( 'handle_change', slashed_form( TYPED, array( 'wpcpm_sevent_id' => (string) $event ) ), KEPT_CHANGE_REP );
				$rows   = rows_of( draw( TYPED, KEPT_CHANGE_REP ), 'wpcpm-sevent-' . $event );
				$meta   = flash_meta( KEPT_CHANGE_REP );
				$post   = get_post( $event );
				return array( $event > 0, $left[0], array( $rows['title'], $rows['description'], $rows['link'] ), $post instanceof WP_Post ? array( $post->post_title, $post->post_content ) : null, array_keys( $meta ) === array( 'typed:sevent:' . TYPED . ':' . $event ), flash_typed( $meta, 'sevent:' . TYPED . ':' . $event ) === $slashed_boxes );
			}
		);
	},
	array( true, 'sevent-fields', array( 'Asked of the store', 'Asked of the store itself.', $slashed_rows['link'] ), array( 'Asked of the store', 'Asked of the store itself.' ), true, true )
);

// A box shows what the person typed. Core's cleaner wrote "&lt;" for a "<" that opens no tag, and
// entities for the quote marks and the ampersand after it; the title box keeps an entity it is given,
// as core's esc_attr() does, and the description box reads those entities back before it escapes the
// text, which core's esc_textarea() would otherwise encode a second time and show as entity text. What
// the box posts back is cleaned to what the first press stored.
$box_title = 'Why 2 < 3, and "Q&A"';
$box_story = 'Is 2 < 3? "Yes"';

ck(
	'a plan refused for its fields comes back with a title and a description holding a < , quote marks and an ampersand drawn in their boxes as they were typed, the flash holding them as the cleaner left them',
	static function () use ( $box_title, $box_story ) {
		press( 'handle_plan', typed_form( TYPED_OTHER, array( 'wpcpm_sevent_title' => $box_title, 'wpcpm_sevent_description' => $box_story ) ), KEPT_PLAN_REP );
		$kept = kept_for( KEPT_PLAN_REP );
		$rows = rows_of( draw( TYPED_OTHER, KEPT_PLAN_REP ), 'wpcpm-sevent-new' );
		return array( is_array( $kept ) ? array( $kept['values']['title'], $kept['values']['description'] ) : $kept, box_text( $rows['title'] ), area_text( $rows['description'] ) );
	},
	array( array( 'Why 2 &lt; 3, and &quot;Q&amp;A&quot;', 'Is 2 &lt; 3? &quot;Yes&quot;' ), $box_title, $box_story )
);
ck(
	'a stored event with them is drawn in its Change form as they were typed, and posting the boxes back changes nothing and leaves the title and the description stored as the first press stored them',
	static function () use ( $box_title, $box_story ) {
		$sent = press( 'handle_plan', typed_form( TYPED_OTHER, array( 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_title' => $box_title, 'wpcpm_sevent_description' => $box_story ) ), KEPT_PLAN_REP );
		$ids  = event_ids();
		$id   = (int) end( $ids );
		$post = get_post( $id );
		$once = array( $post->post_title, $post->post_content );
		$rows = rows_of( draw( TYPED_OTHER, KEPT_PLAN_REP ), 'wpcpm-sevent-' . $id );
		$back = array_map( 'box_text', $rows );
		$left = press( 'handle_change', typed_form( TYPED_OTHER, array( 'wpcpm_sevent_id' => (string) $id, 'wpcpm_sevent_date' => $back['date'], 'wpcpm_sevent_time' => $back['time'], 'wpcpm_sevent_minutes' => $back['minutes'], 'wpcpm_sevent_places' => $back['places'], 'wpcpm_sevent_title' => $back['title'], 'wpcpm_sevent_description' => $back['description'], 'wpcpm_sevent_link' => $back['link'] ) ), KEPT_PLAN_REP );
		$post = get_post( $id );
		$twice = array( $post->post_title, $post->post_content );
		WPCPM_Sponsor_Event::cancel( $id, KEPT_PLAN_REP );
		return array( $sent[0], $once, array( $back['title'], $back['description'] ), $left[0], $twice === $once );
	},
	array( 'sevent-sent', array( 'Why 2 &lt; 3, and &quot;Q&amp;A&quot;', 'Is 2 &lt; 3? &quot;Yes&quot;' ), array( $box_title, $box_story ), 'sevent-unchanged', true )
);
ck(
	'what a description box posts back is read to what the first press kept, for each of these: a < among quote marks, apostrophes and ampersands, over several lines, with an entity typed after the < and a plain one; the ordinary ones are drawn exactly as typed',
	static function () {
		$zone    = new DateTimeZone( 'UTC' );
		$honest  = array( 'Is 2 < 3? "Yes"', 'Tom\'s "plan" & 5 < 6 & \'more\'', "first line\r\nsecond < line\r\nthird & \"q\"", 'Use -> for 2 < 3, "ok"', 'Nothing special here' );
		$literal = array( 'Written as &lt;b&gt; text', '2 < 3 &amp; 4 &quot;five&quot;' );
		$drawn   = array();
		$stable  = array();
		foreach ( array_merge( $honest, $literal ) as $typed ) {
			press( 'handle_plan', typed_form( TYPED_OTHER, array( 'wpcpm_sevent_description' => $typed ) ), KEPT_PLAN_REP );
			$kept = kept_for( KEPT_PLAN_REP );
			// What the box posts back: what it shows, each line break CR LF, as a browser posts a text area.
			$box  = str_replace( "\n", "\r\n", area_text( rows_of( draw( TYPED_OTHER, KEPT_PLAN_REP ), 'wpcpm-sevent-new' )['description'] ) );
			$once = is_array( $kept ) ? $kept['values']['description'] : null;
			$twice = WPCPM_Sponsor_Event::fields_from( array( 'description' => $box ), $zone )['fields']['description'];
			$drawn[]  = $box === str_replace( "\r\n", "\n", $typed ) || $box === $typed;
			$stable[] = $twice === $once;
		}
		return array( array_slice( $drawn, 0, count( $honest ) ), $stable );
	},
	array( array( true, true, true, true, true ), array( true, true, true, true, true, true, true ) )
);
// A sponsor member holds no `unfiltered_html`, so WordPress runs kses on what their press stores
// (bin/stubs/member-kses.php): every bare "&" is written `&amp;` and every bare ">" `&gt;`, in the
// title and in the description, wherever they fall, not only after a "<". An administrator holds
// `unfiltered_html`, and what they write is stored as the cleaner left it. The Change form shows a
// person what they typed either way, and the boxes posted back change nothing.
const WORDS_AMPERSAND = 'Q&A with our team';
const WORDS_GREATER   = 'Ages > 5 welcome';
const WORDS_MIXED     = 'Q&A first: is 2 < 3? "Yes"';
// The cleaner leaves a "<" as typed when a ">" follows it, and kses would take the two for a tag, so
// only an administrator's words can hold a raw "<" with an entity typed after it: the description box
// reads `&gt;` back before the first "<" of the text alone, a raw one as well as one stored `&lt;`.
const WORDS_RAW_LESS  = 'x < y > z &gt; w';

/**
 * One person plans an event with the words given, with kses on for whoever does not hold
 * `unfiltered_html`; the Change form of that event is drawn and its boxes posted back as drawn, and
 * the event is canceled, so the next can be planned.
 *
 * @param int    $uid         Who plans, draws and changes.
 * @param string $record      The sponsor.
 * @param string $title       The title typed.
 * @param string $description The description typed.
 * @param array  $stored      `post_title` and `post_content` as an earlier version stored them,
 *                            written over what the plan stored before the form is drawn; none by
 *                            default.
 * @return array `planned`, what the store holds (`title`, `description`), what the boxes show
 *               (`box_title`, `box_description`), the description box's `maxlength` and the title
 *               box's (`title_maxlength`), the outcome of posting the boxes back (`posted_back`) and
 *               whether the store holds the same (`kept`).
 */
function member_round_trip( $uid, $record, $title, $description, array $stored = array() ) {
	return wpcpm_stub_with_member_kses(
		static function () use ( $uid, $record, $title, $description, $stored ) {
			$planned = press( 'handle_plan', typed_form( $record, array( 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_title' => $title, 'wpcpm_sevent_description' => $description ) ), $uid );
			$ids     = event_ids();
			$id      = (int) end( $ids );
			foreach ( $stored as $key => $value ) {
				$GLOBALS['posts'][ $id ]->$key = $value;
			}
			$once    = array( get_post( $id )->post_title, get_post( $id )->post_content );
			$html    = draw( $record, $uid );
			$back    = array_map( 'box_text', rows_of( $html, 'wpcpm-sevent-' . $id ) );
			$posted  = press( 'handle_change', typed_form( $record, array( 'wpcpm_sevent_id' => (string) $id, 'wpcpm_sevent_date' => $back['date'], 'wpcpm_sevent_time' => $back['time'], 'wpcpm_sevent_minutes' => $back['minutes'], 'wpcpm_sevent_places' => $back['places'], 'wpcpm_sevent_title' => $back['title'], 'wpcpm_sevent_description' => $back['description'], 'wpcpm_sevent_link' => $back['link'] ) ), $uid );
			$twice   = array( get_post( $id )->post_title, get_post( $id )->post_content );
			WPCPM_Sponsor_Event::cancel( $id, $uid );

			return array(
				'planned'         => $planned[0],
				'title'           => $once[0],
				'description'     => $once[1],
				'box_title'       => $back['title'],
				'box_description' => $back['description'],
				'maxlength'       => preg_match( '/<textarea id="wpcpm-sevent-' . $id . '-description"[^>]* maxlength="(\d+)"/', $html, $found ) ? (int) $found[1] : 0,
				'title_maxlength' => preg_match( '/<input type="text" id="wpcpm-sevent-' . $id . '-title"[^>]* maxlength="(\d+)"/', $html, $found ) ? (int) $found[1] : 0,
				'posted_back'     => $posted[0],
				'kept'            => $twice === $once,
			);
		}
	);
}

ck(
	'a sponsor member whose words WordPress runs through kses finds them in the Change form as they typed them, in the title box and the description box: "Q&A with our team", "Ages > 5 welcome" and "Q&A first: is 2 < 3? "Yes"", though the store holds each with its ampersands and its > written as entities; posting the boxes back changes nothing, the store says so and holds what it held',
	static function () {
		$got = array();
		foreach ( array( WORDS_AMPERSAND, WORDS_GREATER, WORDS_MIXED ) as $words ) {
			$trip  = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, $words, $words );
			$got[] = array( $trip['planned'], $trip['title'], $trip['description'], $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-sent', 'Q&amp;A with our team', 'Q&amp;A with our team', WORDS_AMPERSAND, WORDS_AMPERSAND, 'sevent-unchanged', true ),
		array( 'sevent-sent', 'Ages &gt; 5 welcome', 'Ages &gt; 5 welcome', WORDS_GREATER, WORDS_GREATER, 'sevent-unchanged', true ),
		array( 'sevent-sent', 'Q&amp;A first: is 2 &lt; 3? &quot;Yes&quot;', 'Q&amp;A first: is 2 &lt; 3? &quot;Yes&quot;', WORDS_MIXED, WORDS_MIXED, 'sevent-unchanged', true ),
	)
);
ck(
	'a description of 1,999 characters made of "Q&A " comes back into the Change form within the box\'s maxlength of 2,000, as typed, though the store holds it with every ampersand written out and nearly twice as long, and the store counts what it holds as the box counted it',
	static function () {
		$typed = str_repeat( 'Q&A ', 499 ) . 'Q&A';
		$trip  = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, 'Q&A for everyone', $typed );
		return array( 1999, $trip['planned'], mb_strlen( $trip['description'] ), $trip['maxlength'], mb_strlen( $trip['box_description'] ), mb_strlen( $trip['box_description'] ) <= $trip['maxlength'], $trip['box_description'] === $typed, WPCPM_Sponsor_Event::typed_length( $trip['description'] ), $trip['posted_back'], $trip['kept'] );
	},
	array( 1999, 'sevent-sent', 3999, 2000, 1999, true, true, 1999, 'sevent-unchanged', true )
);
ck(
	'an entity a member typed into the description is read back as the character it stands for, in the box, and posting it back changes nothing the store says: "Q&amp;A first, then 2 < 3 & "Yes"" shows as "Q&A first, then 2 < 3 & "Yes""',
	static function () {
		$trip = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, 'Entities typed', 'Q&amp;A first, then 2 < 3 & "Yes"' );
		return array( $trip['planned'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
	},
	array( 'sevent-sent', 'Q&A first, then 2 < 3 & "Yes"', 'sevent-unchanged', true )
);
ck(
	'an administrator\'s own event, for whom WordPress runs no kses, is drawn exactly as before: the store holds the words as the cleaner left them, and the Change form shows them as they were typed, posted back to no change; a description stored as "x < y > z &gt; w", a raw < with an entity after it, is drawn into its box exactly as stored',
	static function () {
		$got = array();
		foreach ( array( WORDS_AMPERSAND => 'Q&A with our team', WORDS_GREATER => 'Ages > 5 welcome', WORDS_MIXED => 'Q&A first: is 2 &lt; 3? &quot;Yes&quot;' ) as $words => $stored ) {
			$trip  = member_round_trip( MANAGER, TYPED_OTHER, $words, $words );
			$got[] = array( $trip['planned'], $trip['title'] === $stored, $trip['description'] === $stored, $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		$trip  = member_round_trip( MANAGER, TYPED_OTHER, 'Signs typed', WORDS_RAW_LESS );
		$got[] = array( $trip['planned'], $trip['description'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		return $got;
	},
	array(
		array( 'sevent-sent', true, true, WORDS_AMPERSAND, WORDS_AMPERSAND, 'sevent-unchanged', true ),
		array( 'sevent-sent', true, true, WORDS_GREATER, WORDS_GREATER, 'sevent-unchanged', true ),
		array( 'sevent-sent', true, true, WORDS_MIXED, WORDS_MIXED, 'sevent-unchanged', true ),
		array( 'sevent-sent', WORDS_RAW_LESS, WORDS_RAW_LESS, 'sevent-unchanged', true ),
	)
);
// The cleaner leaves a "<" as typed where a space follows it and a ">" comes after, and kses would
// read the two, and every word between them, as a tag it does not allow and drop them: "Ages 8 < 12
// welcome, adults > 18 pay" was stored as `Ages 8  18 pay`. For a person without `unfiltered_html`
// the store writes each "<" and ">" the cleaner left as `&lt;` and `&gt;` first, and the Change form
// reads them back. A ">" read back after a "<" that a space or a line break follows closes no tag
// when the box is posted, so it is drawn as typed; after a "<" followed by anything else the cleaner
// would take the words up to it for a tag, and the `&gt;` stays as it is.
const WORDS_AGES  = 'Ages 8 < 12 welcome, adults > 18 pay';
const WORDS_DEMO  = 'Use 2 < 3 then 5 > 4 in the demo';
const WORDS_HEART = 'We <3 mentors. Ages 8 < 12 welcome, adults > 18 pay';
// A "<" at the end of a line, a ">" after it: the cleaner writes the "<" as `&lt;` before a line
// break of one byte and leaves it as typed before one of two, as a browser posts it. A tab after
// the "<", as text pasted from a spreadsheet holds, is white space to the cleaner as well.
const WORDS_LINE_END      = "Ages 8 <\n12 welcome, adults > 18 pay";
const WORDS_LINE_END_CRLF = "Ages 8 <\r\n12 welcome, adults > 18 pay";
const WORDS_TAB           = "Ages 8 <\t12 welcome, adults > 18 pay";

/**
 * One person plans an event with plain words and changes its title and its description to the words
 * given, with kses on for whoever does not hold `unfiltered_html`; the Change form is drawn again and
 * its boxes posted back as drawn, and the event is canceled.
 *
 * @param int    $uid    Who plans, changes and draws.
 * @param string $record The sponsor.
 * @param string $words  The title and the description the change types.
 * @return array The change's outcome (`changed`), what the store holds (`title`, `description`),
 *               what the boxes show (`box_title`, `box_description`), the outcome of posting the
 *               boxes back (`posted_back`) and whether the store holds the same (`kept`).
 */
function member_change_trip( $uid, $record, $words ) {
	return wpcpm_stub_with_member_kses(
		static function () use ( $uid, $record, $words ) {
			press( 'handle_plan', typed_form( $record, array( 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_title' => 'Plain words', 'wpcpm_sevent_description' => 'Plain words.' ) ), $uid );
			$ids  = event_ids();
			$id   = (int) end( $ids );
			$form = static function ( array $boxes ) use ( $record, $id ) {
				return typed_form( $record, array( 'wpcpm_sevent_id' => (string) $id, 'wpcpm_sevent_date' => $boxes['date'], 'wpcpm_sevent_time' => $boxes['time'], 'wpcpm_sevent_minutes' => $boxes['minutes'], 'wpcpm_sevent_places' => $boxes['places'], 'wpcpm_sevent_title' => $boxes['title'], 'wpcpm_sevent_description' => $boxes['description'], 'wpcpm_sevent_link' => $boxes['link'] ) );
			};
			$drawn   = array_map( 'box_text', rows_of( draw( $record, $uid ), 'wpcpm-sevent-' . $id ) );
			$changed = press( 'handle_change', $form( array_merge( $drawn, array( 'title' => $words, 'description' => $words ) ) ), $uid );
			$once    = array( get_post( $id )->post_title, get_post( $id )->post_content );
			$back    = array_map( 'box_text', rows_of( draw( $record, $uid ), 'wpcpm-sevent-' . $id ) );
			$posted  = press( 'handle_change', $form( $back ), $uid );
			$twice   = array( get_post( $id )->post_title, get_post( $id )->post_content );
			WPCPM_Sponsor_Event::cancel( $id, $uid );

			return array(
				'changed'         => $changed[0],
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
	'a sponsor member\'s words with a "<" and a ">" after it keep every word: "Ages 8 < 12 welcome, adults > 18 pay", "Use 2 < 3 then 5 > 4 in the demo" and "We <3 mentors. Ages 8 < 12 welcome, adults > 18 pay" are stored with each < and > written as entities, where kses would have dropped the words between them, and come back into the title box and the description box exactly as typed; posting the boxes back changes nothing, the store says so and holds what it held',
	static function () {
		$got = array();
		foreach ( array( WORDS_AGES, WORDS_DEMO, WORDS_HEART ) as $words ) {
			$trip  = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, $words, $words );
			$got[] = array( $trip['planned'], $trip['title'], $trip['description'], $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-sent', 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay', 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay', WORDS_AGES, WORDS_AGES, 'sevent-unchanged', true ),
		array( 'sevent-sent', 'Use 2 &lt; 3 then 5 &gt; 4 in the demo', 'Use 2 &lt; 3 then 5 &gt; 4 in the demo', WORDS_DEMO, WORDS_DEMO, 'sevent-unchanged', true ),
		array( 'sevent-sent', 'We &lt;3 mentors. Ages 8 &lt; 12 welcome, adults &gt; 18 pay', 'We &lt;3 mentors. Ages 8 &lt; 12 welcome, adults &gt; 18 pay', WORDS_HEART, WORDS_HEART, 'sevent-unchanged', true ),
	)
);
ck(
	'and a change to those words keeps them the same way: the store holds every word, the boxes show them as typed, and posting them back changes nothing',
	static function () {
		$got = array();
		foreach ( array( WORDS_AGES, WORDS_DEMO ) as $words ) {
			$trip  = member_change_trip( KEPT_PLAN_REP, TYPED_OTHER, $words );
			$got[] = array( $trip['changed'], $trip['title'], $trip['description'], $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-changed', 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay', 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay', WORDS_AGES, WORDS_AGES, 'sevent-unchanged', true ),
		array( 'sevent-changed', 'Use 2 &lt; 3 then 5 &gt; 4 in the demo', 'Use 2 &lt; 3 then 5 &gt; 4 in the demo', WORDS_DEMO, WORDS_DEMO, 'sevent-unchanged', true ),
	)
);
ck(
	'a "<" at the end of a line in a member\'s description, with a ">" after it, comes back into the box exactly as typed, the line break posted as one byte or as two, and so does a "<" a tab follows; every word is stored, and posting the box back changes nothing',
	static function () {
		$got = array();
		foreach ( array( WORDS_LINE_END, WORDS_LINE_END_CRLF, WORDS_TAB ) as $words ) {
			$trip  = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, 'A sign at the end of a line', $words );
			$got[] = array( $trip['planned'], $trip['description'], $trip['box_description'] === $words, $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-sent', "Ages 8 &lt;\n12 welcome, adults &gt; 18 pay", true, 'sevent-unchanged', true ),
		array( 'sevent-sent', "Ages 8 &lt;\r\n12 welcome, adults &gt; 18 pay", true, 'sevent-unchanged', true ),
		array( 'sevent-sent', "Ages 8 &lt;\t12 welcome, adults &gt; 18 pay", true, 'sevent-unchanged', true ),
	)
);
ck(
	'what 1.123.2 stored for a member is drawn as it was then: "We <3 our <b>mentors</b> > all else", stored as `We &lt;3 our mentors &gt; all else`, shows "We <3 our mentors &gt; all else", the `&gt;` after a "<" the cleaner would take for a tag\'s kept as it is, and "Ages 8 < 12 welcome, adults > 18 pay", stored as `Ages 8  18 pay` once kses had dropped its words, shows what is left; the title `Q&amp;A with our team` shows "Q&A with our team"; posting the boxes back changes nothing, the store says so and holds what it held',
	static function () {
		$got = array();
		foreach ( array( 'We &lt;3 our mentors &gt; all else', 'Ages 8  18 pay' ) as $stored ) {
			$trip  = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, 'Planned again', 'Planned again.', array( 'post_title' => 'Q&amp;A with our team', 'post_content' => $stored ) );
			$got[] = array( $trip['planned'], $trip['description'], $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
		}
		return $got;
	},
	array(
		array( 'sevent-sent', 'We &lt;3 our mentors &gt; all else', WORDS_AMPERSAND, 'We <3 our mentors &gt; all else', 'sevent-unchanged', true ),
		array( 'sevent-sent', 'Ages 8  18 pay', WORDS_AMPERSAND, 'Ages 8  18 pay', 'sevent-unchanged', true ),
	)
);
// The cleaner took a typed tag out on the first press, and could leave a "<" followed by a letter
// or a digit before a ">": "We <3 our <b>team</b> > all" was cleaned to `We &lt;3 our team > all`.
// Such a typing is refused now, but an event a release before this one stored holds it. Read back
// whole, the box would hold "We <3 our team > all", and a post-back would lose "3 our team" to the
// cleaner, which takes such a "<" for a tag's. Both boxes draw that ">" as `&gt;` instead, for a
// member, whose text the store holds as `&gt;`, and for an administrator, whose text holds the ">".
const WORDS_TAG_TEAM   = 'We <3 our <b>team</b> > all';
const WORDS_TAG_EDITOR = 'I <3 the <em>new</em> editor > the old one';

ck(
	'words the cleaner took a typed tag out of, as a release before this one stored them, keep every word left when the Change form is posted back unedited, in the title box as in the description box, a member\'s and an administrator\'s: "We <3 our <b>team</b> > all" and "I <3 the <em>new</em> editor > the old one" show the > after the <3 as `&gt;`, and posting the boxes back changes nothing, the store says so and holds what it held',
	static function () {
		$got = array();
		foreach ( array( KEPT_PLAN_REP, MANAGER ) as $uid ) {
			foreach ( array( WORDS_TAG_TEAM, WORDS_TAG_EDITOR ) as $words ) {
				// As that release stored it: cleaned, and for a member each ">" written `&gt;`.
				$held  = MANAGER === $uid ? sanitize_text_field( $words ) : str_replace( '>', '&gt;', sanitize_text_field( $words ) );
				$trip  = member_round_trip( $uid, TYPED_OTHER, 'Planned again', 'Planned again.', array( 'post_title' => $held, 'post_content' => $held ) );
				$got[] = array( $trip['planned'], $trip['title'], $trip['description'], $trip['box_title'], $trip['box_description'], $trip['posted_back'], $trip['kept'] );
			}
		}
		return $got;
	},
	array(
		array( 'sevent-sent', 'We &lt;3 our team &gt; all', 'We &lt;3 our team &gt; all', 'We <3 our team &gt; all', 'We <3 our team &gt; all', 'sevent-unchanged', true ),
		array( 'sevent-sent', 'I &lt;3 the new editor &gt; the old one', 'I &lt;3 the new editor &gt; the old one', 'I <3 the new editor &gt; the old one', 'I <3 the new editor &gt; the old one', 'sevent-unchanged', true ),
		array( 'sevent-sent', 'We &lt;3 our team > all', 'We &lt;3 our team > all', 'We <3 our team &gt; all', 'We <3 our team &gt; all', 'sevent-unchanged', true ),
		array( 'sevent-sent', 'I &lt;3 the new editor > the old one', 'I &lt;3 the new editor > the old one', 'I <3 the new editor &gt; the old one', 'I <3 the new editor &gt; the old one', 'sevent-unchanged', true ),
	)
);
ck(
	'a title as 1.123.2 stored it for a member is drawn as it was then: "Ages > 5 welcome: is 2 < 3? "Yes" & more", stored as `Ages &gt; 5 welcome: is 2 &lt; 3? &quot;Yes&quot; &amp; more`, shows as typed, and posting the boxes back changes nothing',
	static function () {
		$trip = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, 'Planned again', 'Planned again.', array( 'post_title' => 'Ages &gt; 5 welcome: is 2 &lt; 3? &quot;Yes&quot; &amp; more' ) );
		return array( $trip['planned'], $trip['title'], $trip['box_title'], $trip['posted_back'], $trip['kept'] );
	},
	array( 'sevent-sent', 'Ages &gt; 5 welcome: is 2 &lt; 3? &quot;Yes&quot; &amp; more', 'Ages > 5 welcome: is 2 < 3? "Yes" & more', 'sevent-unchanged', true )
);
// A ">" drawn as `&gt;` is four characters in the box and one to the store. A title of 120
// characters, its tags taken out by the cleaner and three ">" held after the "<3", is counted 113
// and drawn at 122: each box's maxlength is its limit and three for each, so the browser never
// refuses what the store accepts. A box with nothing held, line breaks and all, keeps its limit.
// The typing is refused now, for the tag: the event holds it as a release before this one stored it.
const WORDS_LEAD = 'We <3 <b>WordPress</b>: install -> configure -> publish -> share ';

/**
 * A title or a description as a release before this one stored a typing the cleaner took a tag out
 * of: cleaned, and for a member each ">" written `&gt;`, as the store and kses wrote it.
 *
 * @param string $typed What was typed.
 * @param bool   $lines Whether it is the description, a text area.
 * @param int    $uid   Who planned it.
 * @return string
 */
function held_as_before( $typed, $lines, $uid ) {
	$cleaned = $lines ? sanitize_textarea_field( $typed ) : sanitize_text_field( $typed );
	return MANAGER === (int) $uid ? $cleaned : str_replace( '>', '&gt;', $cleaned );
}

ck(
	'a box that draws a ">" as `&gt;` raises its maxlength by three for each, so the browser never refuses what the store accepts: a title stored from 120 characters typed, "We <3 <b>WordPress</b>: install -> configure -> ...", counted 113 and drawn at 122, has a maxlength of 129, and a description of 2,000 shaped the same, counted 1,993 and drawn at 2,002, one of 2,009, a member\'s and an administrator\'s alike; posting the boxes back changes nothing; a description of two lines with nothing held keeps the box\'s 2,000',
	static function () {
		$title       = str_pad( WORDS_LEAD, 120, 'x' );
		$description = str_pad( WORDS_LEAD, 2000, 'x' );
		$got         = array();
		foreach ( array( KEPT_PLAN_REP, MANAGER ) as $uid ) {
			$trip  = member_round_trip( $uid, TYPED_OTHER, 'Planned again', 'Planned again.', array( 'post_title' => held_as_before( $title, false, $uid ), 'post_content' => held_as_before( $description, true, $uid ) ) );
			$got[] = array( $trip['planned'], WPCPM_Sponsor_Event::typed_length( $trip['title'] ), mb_strlen( $trip['box_title'] ), $trip['title_maxlength'], WPCPM_Sponsor_Event::typed_length( $trip['description'] ), mb_strlen( $trip['box_description'] ), $trip['maxlength'], $trip['posted_back'], $trip['kept'] );
		}
		$trip  = member_round_trip( KEPT_PLAN_REP, TYPED_OTHER, 'Two lines', "First line, Q&A.\r\nSecond line." );
		$got[] = array( $trip['planned'], $trip['title_maxlength'], $trip['maxlength'] );
		return array( mb_strlen( $title ), mb_strlen( $description ), $got );
	},
	array(
		120,
		2000,
		array(
			array( 'sevent-sent', 113, 122, 129, 1993, 2002, 2009, 'sevent-unchanged', true ),
			array( 'sevent-sent', 113, 122, 129, 1993, 2002, 2009, 'sevent-unchanged', true ),
			array( 'sevent-sent', 120, 2000 ),
		),
	)
);
// The same title, and the same description, edited in its Change form: each `-&gt;`, the ">" the box
// holds, read as a broken arrow and written "then", and the box filled to its maxlength. The browser
// takes it, the store refuses it as over its limit, and the form comes back with what was typed: a
// refused title or description is kept as long as the box it was typed into let it be, its drawn
// limit, and one character more, which no browser posts into that box, is kept as nothing.
ck(
	'a title or a description over its limit is kept for the person as long as its box let them type it: the held title, its three `-&gt;` written "then" and the box filled to its 129, is refused, kept whole and drawn back as typed; the description the same at its 2,009; one character more is kept as nothing',
	static function () {
		return wpcpm_stub_with_member_kses(
			static function () {
				$got = array();
				foreach ( array( 'title' => 129, 'description' => 2009 ) as $box => $room ) {
					$title       = str_pad( WORDS_LEAD, 120, 'x' );
					$description = str_pad( WORDS_LEAD, 2000, 'x' );
					press( 'handle_plan', typed_form( TYPED_OTHER, array( 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_title' => 'Planned again', 'wpcpm_sevent_description' => 'Planned again.' ) ), KEPT_PLAN_REP );
					$ids    = event_ids();
					$id     = (int) end( $ids );
					$GLOBALS['posts'][ $id ]->post_title   = held_as_before( $title, false, KEPT_PLAN_REP );
					$GLOBALS['posts'][ $id ]->post_content = held_as_before( $description, true, KEPT_PLAN_REP );
					$back   = array_map( 'box_text', rows_of( draw( TYPED_OTHER, KEPT_PLAN_REP ), 'wpcpm-sevent-' . $id ) );
					$edited = str_replace( '-&gt;', 'then', (string) $back[ $box ] );
					$edited = str_pad( $edited, $room, 'y' );
					$rows   = array();
					foreach ( array( $edited, $edited . 'z' ) as $typed ) {
						$fields                         = $back;
						$fields[ $box ]                 = $typed;
						$form                           = array( 'wpcpm_sevent_id' => (string) $id );
						foreach ( $fields as $key => $value ) {
							$form[ 'wpcpm_sevent_' . $key ] = (string) $value;
						}
						$left   = press( 'handle_change', typed_form( TYPED_OTHER, $form ), KEPT_PLAN_REP );
						$kept   = kept_for( KEPT_PLAN_REP );
						$drawn  = rows_of( draw( TYPED_OTHER, KEPT_PLAN_REP ), 'wpcpm-sevent-' . $id );
						$shown  = 'description' === $box ? area_text( $drawn[ $box ] ) : box_text( $drawn[ $box ] );
						$rows[] = array( $left[0], is_array( $kept ) ? WPCPM_Sponsor_Event::typed_length( $kept['values'][ $box ] ) : null, $shown === $typed );
					}
					WPCPM_Sponsor_Event::cancel( $id, KEPT_PLAN_REP );
					$got[ $box ] = array( mb_strlen( (string) $back[ $box ] ), mb_strlen( $edited ), $rows );
				}
				return $got;
			}
		);
	},
	array(
		'title'       => array( 122, 129, array( array( 'sevent-fields', 129, true ), array( 'sevent-fields', 0, false ) ) ),
		'description' => array( 2002, 2009, array( array( 'sevent-fields', 2009, true ), array( 'sevent-fields', 0, false ) ) ),
	)
);
ck(
	'the short boxes count what was posted, as they did: five entities in the places box are 25 characters and come back empty, four are 20 and come back whole',
	static function () {
		$kept = array();
		foreach ( array( str_repeat( '&amp;', 4 ), str_repeat( '&amp;', 5 ) ) as $places ) {
			press( 'handle_plan', typed_form( TYPED_OTHER, array( 'wpcpm_sevent_places' => $places ) ), KEPT_PLAN_REP );
			$value  = kept_for( KEPT_PLAN_REP );
			$kept[] = is_array( $value ) ? $value['values']['places'] : $value;
			draw( TYPED_OTHER, KEPT_PLAN_REP );
		}
		return array( $kept[0] === str_repeat( '&amp;', 4 ), $kept[1] );
	},
	array( true, '' )
);

/* ---- the mails give a member's words as typed --------------------------------- */

echo "\n=== The mails give a member's words as typed ===\n";

// A member types a "<3", a "<" and a ">" a space follows, an ampersand, quotes and an apostrophe
// into the title and the description, the description over two lines. The cleaner writes the "<3"
// and the quote marks, the ampersand and the apostrophe after it as entities, the store writes the
// other "<" and ">" as entities: the post holds entities alone, and every word. A mail is plain
// text, so each one reads them back, in the Title line, under Description and in the subject alike,
// and a subject that carries the title is handed to the mail layer as plain text, whose cleaner would
// otherwise read "<3 ... >" as a tag and drop it, words and all. (A typed tag, which the cleaner
// would take, is refused before anything is stored.)
const SIGNS_TYPED              = 'We <3 "our" team & Bob\'s, 2 < 3 > 1';
const SIGNS_MAILED             = SIGNS_TYPED;
const SIGNS_DESCRIPTION        = "We <3 \"our\" team & Bob's, 2 < 3 > 1\r\nSecond line, 2 < 3.";
const SIGNS_DESCRIPTION_MAILED = SIGNS_DESCRIPTION;

/** A mail's Title line, or ''. */
function title_line( $body ) { return preg_match( '/^Title: .*$/m', (string) $body, $found ) ? $found[0] : ''; }

/** What a mail gives under Description:, up to the paragraph after it, or ''. */
function description_of( $body ) { return preg_match( '/\nDescription:\n(.*?)(?:\n\n|$)/s', (string) $body, $found ) ? $found[1] : ''; }

/**
 * A mail as these checks read it: its subject, its Title line, and whether its subject is plain text,
 * which the mail layer decides for every email the template registry holds (`WPCPM_Mail::hand_off()`).
 */
function signs_read( array $mail ) { return array( $mail['subject'], title_line( $mail['body'] ), null !== WPCPM_Mail_Templates::get( $mail['context'] ) ); }

$signs_mails = attempt(
	static function () {
		return wpcpm_stub_with_member_kses(
			static function () {
				$typed = array( 'wpcpm_sevent_minutes' => '45', 'wpcpm_sevent_title' => SIGNS_TYPED, 'wpcpm_sevent_description' => SIGNS_DESCRIPTION );
				$out   = array();

				$GLOBALS['mail'] = array();
				press( 'handle_plan', typed_form( TYPED_OTHER, $typed ), KEPT_PLAN_REP );
				$ids            = event_ids();
				$id             = (int) end( $ids );
				$out['stored']  = array( get_post( $id )->post_title, get_post( $id )->post_content );
				$out['planned'] = mail_for( 'events@program.example' );
				WPCPM_Sponsor_Event::approve( $id, MANAGER );

				$GLOBALS['mail'] = array();
				press( 'handle_change', typed_form( TYPED_OTHER, array_merge( $typed, array( 'wpcpm_sevent_id' => (string) $id, 'wpcpm_sevent_time' => '15:30' ) ) ), KEPT_PLAN_REP );
				$out['changed'] = array( mail_for( 'kim.keptplan@people.example' ), mail_for( 'events@program.example' ) );

				$GLOBALS['mail'] = array();
				press( 'handle_cancel', array( 'wpcpm_sponsor' => TYPED_OTHER, 'wpcpm_sevent_id' => (string) $id ), KEPT_PLAN_REP );
				$out['canceled'] = array( mail_for( 'kim.keptplan@people.example' ), mail_for( 'events@program.example' ) );

				return $out;
			}
		);
	}
);

ck(
	'a member\'s title and description holding a "<3", a "<" and a ">", an ampersand, quotes and an apostrophe are stored as entities alone, every word kept',
	static function () use ( $signs_mails ) { return $signs_mails['stored']; },
	array( 'We &lt;3 &quot;our&quot; team &amp; Bob&#039;s, 2 &lt; 3 &gt; 1', "We &lt;3 &quot;our&quot; team &amp; Bob&#039;s, 2 &lt; 3 &gt; 1\r\nSecond line, 2 &lt; 3." )
);
ck(
	'the program contact is told of the plan with the title and the description as typed, every entity read back, in the Title line and under Description, its line break kept',
	static function () use ( $signs_mails ) { return array( title_line( $signs_mails['planned']['body'] ), description_of( $signs_mails['planned']['body'] ) ); },
	array( 'Title: ' . SIGNS_MAILED, SIGNS_DESCRIPTION_MAILED )
);
ck(
	'a change of the approved event gives the member the title as typed, in the Title line and in the subject, which goes to the mail layer as plain text; and the contact the title and the description as typed',
	static function () use ( $signs_mails ) { return array( signs_read( $signs_mails['changed'][0] ), title_line( $signs_mails['changed'][1]['body'] ), description_of( $signs_mails['changed'][1]['body'] ) ); },
	array( array( '[WordPress Education] Your event is changed: ' . SIGNS_MAILED, 'Title: ' . SIGNS_MAILED, true ), 'Title: ' . SIGNS_MAILED, SIGNS_DESCRIPTION_MAILED )
);
ck(
	'a cancel gives the member and the contact the same: the title as typed in the Title line and in the member\'s plain subject, the description as typed for the contact',
	static function () use ( $signs_mails ) { return array( signs_read( $signs_mails['canceled'][0] ), title_line( $signs_mails['canceled'][1]['body'] ), description_of( $signs_mails['canceled'][1]['body'] ) ); },
	array( array( '[WordPress Education] Your event is canceled: ' . SIGNS_MAILED, 'Title: ' . SIGNS_MAILED, true ), 'Title: ' . SIGNS_MAILED, SIGNS_DESCRIPTION_MAILED )
);

/* ---- a title or a description the cleaner would take words from ----------------- */

echo "\n=== A title or a description the cleaner would take words from ===\n";

// The cleaner takes "<12 welcome, adults >" for a tag and drops it with every word in it, and "%ca"
// for a percent octet. A plan or a change whose title or description it would take words from is
// refused before the form is read for the store, so nothing is looked up, stored, mailed or sent as
// a calendar file, and the refusal names the box in the words of the form's button. The form comes
// back with what was typed, once, for the person who pressed and in that form alone. With a space
// after each sign every word is kept, and a box posted back as it was drawn is not asked.
const LOSS         = 'spoAAAA0000000011'; // Champion, no term, two members.
const LOSS_OTHER   = 'spoAAAA0000000012'; // Champion, no term: another sponsor a program manager looks at.
const LOSS_REP     = 16;
const LOSS_REP_TWO = 17;
const LOSS_TITLE   = 'Ages 8 <12 welcome, adults >18 pay';
const SPACED_TITLE = 'Ages 8 < 12 welcome, adults > 18 pay';
const LOSS_STORY   = "Kids <12 free,\r\nadults >18 pay, and 10%cashback";
const SPACED_STORY = "Kids < 12 free,\r\nadults > 18 pay, and 10% cashback";

a_user( LOSS_REP, 'Lou Lossrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => LOSS, 'wpcpm_sponsor_active' => 1 ) );
a_user( LOSS_REP_TWO, 'Liv Lossrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => LOSS, 'wpcpm_sponsor_active' => 1 ) );
$GLOBALS['index'][ LOSS ]       = array( 'record_id' => LOSS, 'name' => 'Loss Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' );
$GLOBALS['index'][ LOSS_OTHER ] = array( 'record_id' => LOSS_OTHER, 'name' => 'Other Loss Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => '' );
set_now( utc( '2026-11-21 09:00' ) );

/** The Plan form as a member fills it in, every box valid; any value replaced. */
function loss_form( $record, array $over = array() ) {
	return array_merge(
		array(
			'wpcpm_sponsor'            => $record,
			'wpcpm_sevent_date'        => '2026-12-10',
			'wpcpm_sevent_time'        => '15:00',
			'wpcpm_sevent_minutes'     => '60',
			'wpcpm_sevent_places'      => '40',
			'wpcpm_sevent_title'       => 'Plain words',
			'wpcpm_sevent_description' => "Plain words.\r\nOn two lines.",
			'wpcpm_sevent_link'        => 'https://call.example/loss',
		),
		$over
	);
}

/** What a text area shows of the markup it was drawn with: each CR LF one LF, and the line feed right after its opening tag dropped, as the parser drops it. */
function area_text( $markup ) {
	return (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", box_text( $markup ) ) );
}

/**
 * A form's rows as a browser posts them once drawn: what each box shows, a text input's line breaks
 * dropped, as a browser drops them from its value, and a text area's posted as CR LF.
 *
 * @param array $rows The rows' markup (`rows_of()`).
 * @return array
 */
function posted_rows( array $rows ) {
	$post = array();
	foreach ( $rows as $key => $markup ) {
		$post[ 'wpcpm_sevent_' . $key ] = 'description' === $key ? str_replace( "\n", "\r\n", area_text( $markup ) ) : str_replace( array( "\r", "\n" ), '', box_text( $markup ) );
	}
	return $post;
}

/** A sponsor's event's Change form as one person's browser posts it, drawn for that person first; any value replaced. */
function loss_change( $record, $event_id, $uid, array $over = array() ) {
	$rows = rows_of( draw( $record, $uid ), 'wpcpm-sevent-' . (int) $event_id );
	return array_merge( array( 'wpcpm_sponsor' => $record, 'wpcpm_sevent_id' => (string) (int) $event_id ), posted_rows( $rows ), $over );
}

/** What a refusal could have touched: the store's state, the calendar files mailed and the schedule. */
function loss_state() { return array( store_state(), $GLOBALS['ics'], $GLOBALS['cron'] ); }

/** Whether a person has a kept typing waiting on any form. */
function loss_kept( $uid ) { return array() !== preg_grep( '/^typed:/', array_keys( isset( $GLOBALS['flash'][ (int) $uid ] ) ? $GLOBALS['flash'][ (int) $uid ] : array() ) ); }

$loss_plan_title = 'Nothing was planned, because WordPress would remove part of what you typed in "Title". To keep every word, put a space after each "<" (or write "less than") and after each "%", then plan the event again.';
$loss_save_title = 'Nothing was saved, because WordPress would remove part of what you typed in "Title". To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.';

ck(
	'a plan titled "Ages 8 <12 welcome, adults >18 pay" is refused before the form is read for the store, the title named in the words of Plan the event: nothing is looked up, stored, logged, mailed or sent as a calendar file',
	static function () {
		$GLOBALS['lookups'] = array();
		$before             = loss_state();
		$left               = press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), LOSS_REP );
		$messages           = WPCPM_Sponsor_Event_Planner::messages();
		return array( $left, loss_state() === $before, $GLOBALS['lookups'], isset( $messages['sevent-plan-title-loss'] ) ? $messages['sevent-plan-title-loss'] : null );
	},
	array( array( 'sevent-plan-title-loss', 'events', LOSS, '' ), true, array(), array( 'error', $loss_plan_title ) )
);
ck(
	'the Plan form comes back with what was typed, once: the title exactly as typed in the room the box had, every other box as posted; drawn again, it is empty',
	static function () use ( $empty_rows ) {
		$html   = draw( LOSS, LOSS_REP );
		$first  = rows_of( $html, 'wpcpm-sevent-new' );
		$second = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-new' );
		$room   = preg_match( '/id="wpcpm-sevent-new-title" name="wpcpm_sevent_title" value="[^"]*" maxlength="(\d+)"/', $html, $found ) ? (int) $found[1] : 0;
		return array( box_text( $first['title'] ), $first['title'], $room, array( $first['date'], $first['time'], $first['minutes'], $first['places'], box_text( $first['link'] ), area_text( $first['description'] ) ), $second === $empty_rows );
	},
	array( LOSS_TITLE, 'Ages 8 &lt;12 welcome, adults &gt;18 pay', 120, array( '2026-12-10', '15:00', '60', '40', 'https://call.example/loss', "Plain words.\nOn two lines." ), true )
);
ck(
	'what was typed is the pressing member\'s own: the sponsor\'s other member and a program manager drawing the same planner are drawn the empty form, and the member who pressed still finds it',
	static function () use ( $empty_rows ) {
		press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), LOSS_REP );
		$other   = rows_of( draw( LOSS, LOSS_REP_TWO ), 'wpcpm-sevent-new' );
		$manager = rows_of( draw( LOSS, MANAGER ), 'wpcpm-sevent-new' );
		$own     = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-new' );
		return array( $other === $empty_rows, $manager === $empty_rows, box_text( $own['title'] ) );
	},
	array( true, true, LOSS_TITLE )
);
ck(
	'and that form\'s alone: a program manager\'s refused plan for one sponsor is not drawn in another sponsor\'s Plan form, and comes back on its own',
	static function () use ( $empty_rows ) {
		$left  = press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), MANAGER );
		$other = rows_of( draw( LOSS_OTHER, MANAGER ), 'wpcpm-sevent-new' );
		$own   = rows_of( draw( LOSS, MANAGER ), 'wpcpm-sevent-new' );
		return array( $left[0], $other === $empty_rows, box_text( $own['title'] ) );
	},
	array( 'sevent-plan-title-loss', true, LOSS_TITLE )
);
ck(
	'a plan whose description the cleaner would take words from, a tag over two lines and a percent octet, is refused the same way, naming the description, and comes back with every line as typed',
	static function () {
		$before   = loss_state();
		$left     = press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_description' => LOSS_STORY ) ), LOSS_REP );
		$after    = loss_state() === $before;
		$rows     = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-new' );
		$messages = WPCPM_Sponsor_Event_Planner::messages();
		return array( $left, $after, area_text( $rows['description'] ), box_text( $rows['title'] ), isset( $messages['sevent-plan-description-loss'] ) ? $messages['sevent-plan-description-loss'][1] : null );
	},
	array( array( 'sevent-plan-description-loss', 'events', LOSS, '' ), true, str_replace( "\r\n", "\n", LOSS_STORY ), 'Plain words', str_replace( '"Title"', '"Description"', $loss_plan_title ) )
);
ck(
	'with words lost from both, the title is named, as the form orders its boxes, and both come back as typed',
	static function () {
		$left = press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_title' => LOSS_TITLE, 'wpcpm_sevent_description' => LOSS_STORY ) ), LOSS_REP );
		$rows = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-new' );
		return array( $left[0], box_text( $rows['title'] ), area_text( $rows['description'] ) );
	},
	array( 'sevent-plan-title-loss', LOSS_TITLE, str_replace( "\r\n", "\n", LOSS_STORY ) )
);

// The refusal's typing is never drawn: the next plan, with a space after each sign, is posted at once.
$loss_event = attempt(
	static function () {
		press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), LOSS_REP );
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_title' => SPACED_TITLE, 'wpcpm_sevent_description' => SPACED_STORY ) ), LOSS_REP );
		$ids             = event_ids();
		return array(
			'left' => $left,
			'id'   => (int) end( $ids ),
			'kept' => loss_kept( LOSS_REP ),
			'mail' => mail_for( 'events@program.example' ),
		);
	}
);

ck(
	'"Ages 8 < 12 welcome, adults > 18 pay" plans the event with every word, stored and in the program contact\'s mail, the description\'s "<", ">" and "%" as well; the refusal before it, never drawn, left nothing',
	static function () use ( $loss_event ) {
		$post = get_post( $loss_event['id'] );
		return array( $loss_event['left'], $post->post_title, $post->post_content, title_line( $loss_event['mail']['body'] ), description_of( $loss_event['mail']['body'] ), $loss_event['kept'] );
	},
	array( array( 'sevent-sent', 'events', LOSS, '' ), 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay', "Kids &lt; 12 free,\r\nadults &gt; 18 pay, and 10% cashback", 'Title: ' . SPACED_TITLE, SPACED_STORY, false )
);

// Approved, with two people on it: a change of its title would tell them, and send the sponsor's
// members the calendar file that replaces theirs.
attempt(
	static function () use ( $loss_event ) {
		WPCPM_Sponsor_Event::approve( $loss_event['id'], MANAGER );
		add_post_meta( $loss_event['id'], '_wpcpm_sevent_person', JOINER );
		add_post_meta( $loss_event['id'], '_wpcpm_sevent_person', JOINER_TWO );
	}
);
$loss_other_event = attempt( static function () { return WPCPM_Sponsor_Event::plan( LOSS_OTHER, store_fields( utc( '2026-12-12 10:00' ) ), MANAGER, 'manager' ); } );

ck(
	'a change of the approved event, people on it, whose title would lose words is refused before the store is asked, the title named in the words of Save the changes: nothing is looked up or stored, nobody on it and none of the members is told, no calendar file is sent and nothing waits for the drain',
	static function () use ( $loss_event ) {
		$form               = loss_change( LOSS, $loss_event['id'], LOSS_REP, array( 'wpcpm_sevent_time' => '16:00', 'wpcpm_sevent_title' => LOSS_TITLE ) );
		$GLOBALS['lookups'] = array();
		$before             = loss_state();
		$left               = press( 'handle_change', $form, LOSS_REP );
		$messages           = WPCPM_Sponsor_Event_Planner::messages();
		return array( $left, loss_state() === $before, $GLOBALS['lookups'], isset( $messages['sevent-change-title-loss'] ) ? $messages['sevent-change-title-loss'] : null );
	},
	array( array( 'sevent-change-title-loss', 'events', LOSS, '' ), true, array(), array( 'error', $loss_save_title ) )
);
ck(
	'that event\'s Change form comes back open with what was typed, once: the title as typed, the start time as posted; drawn again, it is closed and holds the event',
	static function () use ( $loss_event ) {
		$open   = '<details class="wpcpm-sevent__change" open>';
		$first  = draw( LOSS, LOSS_REP );
		$second = draw( LOSS, LOSS_REP );
		$kept   = rows_of( $first, 'wpcpm-sevent-' . $loss_event['id'] );
		$stored = rows_of( $second, 'wpcpm-sevent-' . $loss_event['id'] );
		return array( substr_count( $first, $open ), box_text( $kept['title'] ), $kept['time'], substr_count( $second, $open ), box_text( $stored['title'] ), $stored['time'] );
	},
	array( 1, LOSS_TITLE, '16:00', 0, SPACED_TITLE, '15:00' )
);
ck(
	'a change whose description would lose words is refused the same way, naming the description; the sponsor\'s other member is drawn the event as stored, and the member who pressed still finds every line as typed',
	static function () use ( $loss_event, $loss_save_title ) {
		$form     = loss_change( LOSS, $loss_event['id'], LOSS_REP, array( 'wpcpm_sevent_description' => LOSS_STORY ) );
		$before   = loss_state();
		$left     = press( 'handle_change', $form, LOSS_REP );
		$after    = loss_state() === $before;
		$other    = rows_of( draw( LOSS, LOSS_REP_TWO ), 'wpcpm-sevent-' . $loss_event['id'] );
		$own      = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-' . $loss_event['id'] );
		$messages = WPCPM_Sponsor_Event_Planner::messages();
		return array( $left, $after, area_text( $other['description'] ), area_text( $own['description'] ), isset( $messages['sevent-change-description-loss'] ) ? $messages['sevent-change-description-loss'][1] : null );
	},
	array( array( 'sevent-change-description-loss', 'events', LOSS, '' ), true, str_replace( "\r\n", "\n", SPACED_STORY ), str_replace( "\r\n", "\n", LOSS_STORY ), str_replace( '"Title"', '"Description"', $loss_save_title ) )
);
ck(
	'a program manager\'s refused change of one sponsor\'s event is not drawn in another sponsor\'s event, looked at first, and comes back in its own',
	static function () use ( $loss_event, $loss_other_event ) {
		$left  = press( 'handle_change', loss_change( LOSS, $loss_event['id'], MANAGER, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), MANAGER );
		$other = rows_of( draw( LOSS_OTHER, MANAGER ), 'wpcpm-sevent-' . (int) $loss_other_event );
		$own   = rows_of( draw( LOSS, MANAGER ), 'wpcpm-sevent-' . $loss_event['id'] );
		return array( $left[0], is_int( $loss_other_event ), box_text( $other['title'] ), box_text( $own['title'] ) );
	},
	array( 'sevent-change-title-loss', true, 'Asked of the store', LOSS_TITLE )
);
ck(
	'the Change form posted back as drawn changes nothing, as before: nothing stored, logged, mailed or scheduled',
	static function () use ( $loss_event ) {
		$form   = loss_change( LOSS, $loss_event['id'], LOSS_REP );
		$before = loss_state();
		$left   = press( 'handle_change', $form, LOSS_REP );
		return array( $left, loss_state() === $before );
	},
	array( array( 'sevent-unchanged', 'events', LOSS, '' ), true )
);
ck(
	'a change to "Ages 8 < 12 welcome, adults > 18 pay" keeps every word, stored and in what the members are sent; the refused change before it, never drawn, left nothing in the form',
	static function () use ( $loss_event ) {
		$form = loss_change( LOSS, $loss_event['id'], LOSS_REP, array( 'wpcpm_sevent_title' => SPACED_TITLE . ', moved', 'wpcpm_sevent_time' => '16:00' ) );
		press( 'handle_change', array_merge( $form, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), LOSS_REP );
		$GLOBALS['mail'] = array();
		$left            = press( 'handle_change', $form, LOSS_REP );
		$rows            = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-' . $loss_event['id'] );
		return array( $left[0], get_post( $loss_event['id'] )->post_title, mail_for( 'lou.lossrep@people.example' )['subject'], box_text( $rows['title'] ) );
	},
	array( 'sevent-changed', 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay, moved', '[WordPress Education] Your event is changed: ' . SPACED_TITLE . ', moved', SPACED_TITLE . ', moved' )
);
// No form of the plugin stores a title with a line break or a percent octet, but a row written by
// other means can: the title box draws the break as a space, and a box posted back as drawn is not
// asked and keeps what the event holds, whatever else the change moves.
ck(
	'an event whose title holds a line break and words the cleaner would take, and whose description holds such words too: the title box draws the break as a space, the form posted back as drawn changes nothing, and with the start moved both are kept as the event held them',
	static function () use ( $loss_event ) {
		$id = $loss_event['id'];
		$GLOBALS['posts'][ $id ]->post_title   = "Get 10%cashback\nat the booth";
		$GLOBALS['posts'][ $id ]->post_content = "Bring a laptop,\r\nand 10%cashback.";
		$form   = loss_change( LOSS, $id, LOSS_REP );
		$before = loss_state();
		$same   = press( 'handle_change', $form, LOSS_REP );
		$after  = loss_state() === $before;
		$moved  = press( 'handle_change', array_merge( $form, array( 'wpcpm_sevent_time' => '17:00' ) ), LOSS_REP );
		$post   = get_post( $id );
		return array( $form['wpcpm_sevent_title'], $same, $after, $moved[0], $post->post_title, $post->post_content );
	},
	array( 'Get 10%cashback at the booth', array( 'sevent-unchanged', 'events', LOSS, '' ), true, 'sevent-changed', "Get 10%cashback\nat the booth", "Bring a laptop,\r\nand 10%cashback." )
);
// The fold is the shared one (`WPCPM_Typed_Text::one_line()`), which the Sponsors screen and the
// profile card draw with too: a lone CR and a blank line between CR LFs are a space a break, and the
// planner keeps no fold of its own.
ck(
	'a title held with a lone CR and a blank line between CR LFs is drawn as WPCPM_Typed_Text::one_line() draws it, a space a break, and posted back as drawn changes nothing; the planner keeps no fold of its own',
	static function () use ( $loss_event ) {
		$id    = $loss_event['id'];
		$title = "Meet\rthe team\r\n\r\ntoday";
		$GLOBALS['posts'][ $id ]->post_title   = $title;
		$GLOBALS['posts'][ $id ]->post_content = 'Bring a laptop.';
		$form   = loss_change( LOSS, $id, LOSS_REP );
		$before = loss_state();
		$same   = press( 'handle_change', $form, LOSS_REP );
		return array( $form['wpcpm_sevent_title'], WPCPM_Typed_Text::one_line( $title ), $same[0], loss_state() === $before, get_post( $id )->post_title, method_exists( 'WPCPM_Sponsor_Event_Planner', 'one_line' ) );
	},
	array( 'Meet the team  today', 'Meet the team  today', 'sevent-unchanged', true, "Meet\rthe team\r\n\r\ntoday", false )
);
ck(
	'edited, such a title is asked as any typing is: "Get 10%cashback at the booth, again" is refused, the title named, and nothing is stored',
	static function () use ( $loss_event ) {
		$form   = loss_change( LOSS, $loss_event['id'], LOSS_REP, array( 'wpcpm_sevent_title' => 'Get 10%cashback at the booth, again' ) );
		$before = loss_state();
		$left   = press( 'handle_change', $form, LOSS_REP );
		$after  = loss_state() === $before;
		draw( LOSS, LOSS_REP );
		return array( $left[0], $after );
	},
	array( 'sevent-change-title-loss', true )
);
ck(
	'what a refused change kept for one event is drawn in no other event\'s Change form: the event canceled and the next one planned, its form holds its own values',
	static function () use ( $loss_event ) {
		$left = press( 'handle_change', loss_change( LOSS, $loss_event['id'], LOSS_REP, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), LOSS_REP );
		WPCPM_Sponsor_Event::cancel( $loss_event['id'], LOSS_REP );
		$next = WPCPM_Sponsor_Event::plan( LOSS, store_fields( utc( '2026-12-20 10:00' ) ), LOSS_REP, 'member' );
		$html = draw( LOSS, LOSS_REP );
		$rows = rows_of( $html, 'wpcpm-sevent-' . (int) $next );
		return array( $left[0], is_int( $next ), box_text( $rows['title'] ), substr_count( $html, 'Ages 8 &lt;12' ) );
	},
	array( 'sevent-change-title-loss', true, 'Asked of the store', 0 )
);
// A press refused for lost words wrote, mailed and scheduled nothing, so it spends none of the
// account's ten presses an hour, as the profile's refusal for lost words writes nothing either. A
// press past the loss check spends one, refused by the form's own check or not, before its call
// link is looked up; and a cancel spends one before the event it names is asked for.
ck(
	'a plan or a change refused for words the cleaner would take spends none of the hour\'s ten presses; a change the store is asked about spends one, a plan and a change refused for their fields after the loss check spend one each, and so does a cancel, refused for the event it names',
	static function () {
		$key    = WPCPM_Ceiling::key( WPCPM_Sponsor_Event_Planner::CEILING, (string) LOSS_REP );
		$events = WPCPM_Sponsor_Event::upcoming_of( LOSS );
		$event  = 1 === count( $events ) ? (int) $events[0]->ID : 0;
		$counts = array();
		$GLOBALS['same_hour'] = true;
		try {
			unset( $GLOBALS['buckets'][ $key ] );
			$plan     = press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_title' => LOSS_TITLE ) ), LOSS_REP );
			$counts[] = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;
			draw( LOSS, LOSS_REP );
			$change   = press( 'handle_change', loss_change( LOSS, $event, LOSS_REP, array( 'wpcpm_sevent_description' => LOSS_STORY ) ), LOSS_REP );
			$counts[] = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;
			draw( LOSS, LOSS_REP );
			$asked    = press( 'handle_change', loss_change( LOSS, $event, LOSS_REP ), LOSS_REP );
			$counts[] = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;
			$fields   = press( 'handle_plan', loss_form( LOSS, array( 'wpcpm_sevent_minutes' => '5' ) ), LOSS_REP );
			$counts[] = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;
			draw( LOSS, LOSS_REP );
			$changed  = press( 'handle_change', loss_change( LOSS, $event, LOSS_REP, array( 'wpcpm_sevent_minutes' => '5' ) ), LOSS_REP );
			$counts[] = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;
			draw( LOSS, LOSS_REP );
			$cancel   = press( 'handle_cancel', array( 'wpcpm_sponsor' => LOSS, 'wpcpm_sevent_id' => '0' ), LOSS_REP );
			$counts[] = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;
		} finally {
			unset( $GLOBALS['same_hour'] );
		}
		return array( $event > 0, $plan[0], $change[0], $asked[0], $fields[0], $changed[0], $cancel[0], $counts );
	},
	array( true, 'sevent-plan-title-loss', 'sevent-change-description-loss', 'sevent-unchanged', 'sevent-fields', 'sevent-fields', 'refused', array( 0, 0, 1, 2, 3, 4 ) )
);
// The protections a kept typing and an unedited box rest on, each on the event still to take place.
$loss_next = attempt(
	static function () {
		$events = WPCPM_Sponsor_Event::upcoming_of( LOSS );
		return 1 === count( $events ) ? (int) $events[0]->ID : 0;
	}
);
ck(
	'a description the event holds with LF breaks, posted back as drawn with CR LF ones as a browser posts them, is the same text: not asked, though it holds words the cleaner would take, and nothing changes',
	static function () use ( $loss_next ) {
		$GLOBALS['posts'][ $loss_next ]->post_content = "Bring a laptop,\nand 10%cashback.";
		$form   = loss_change( LOSS, $loss_next, LOSS_REP );
		$before = loss_state();
		$left   = press( 'handle_change', $form, LOSS_REP );
		return array( $form['wpcpm_sevent_description'], $left[0], loss_state() === $before );
	},
	array( "Bring a laptop,\r\nand 10%cashback.", 'sevent-unchanged', true )
);
ck(
	'a kept description that begins with a line break comes back with it: the box prints a line feed of its own after its opening tag, which the parser drops',
	static function () use ( $loss_next ) {
		$left = press( 'handle_change', loss_change( LOSS, $loss_next, LOSS_REP, array( 'wpcpm_sevent_description' => "\r\n" . LOSS_STORY ) ), LOSS_REP );
		$rows = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-' . $loss_next );
		return array( $left[0], area_text( $rows['description'] ) );
	},
	array( 'sevent-change-description-loss', "\n" . str_replace( "\r\n", "\n", LOSS_STORY ) )
);
ck(
	'a kept title that holds an entity typed out comes back exactly as typed: "Q&amp;A <b>x</b>" shows its "&amp;" as five characters, each "&" of it written out once more for the box',
	static function () use ( $loss_next ) {
		$left = press( 'handle_change', loss_change( LOSS, $loss_next, LOSS_REP, array( 'wpcpm_sevent_title' => 'Q&amp;A <b>x</b>' ) ), LOSS_REP );
		$rows = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-' . $loss_next );
		return array( $left[0], $rows['title'], box_text( $rows['title'] ) );
	},
	array( 'sevent-change-title-loss', 'Q&amp;amp;A &lt;b&gt;x&lt;/b&gt;', 'Q&amp;A <b>x</b>' )
);
ck(
	'a kept description is kept in the room its box was drawn with, past the limit by each ">" the box held as `&gt;`: a description drawn at 2,002 in a box of 2,009, edited to 2,008 with a tag the cleaner would take, comes back whole',
	static function () use ( $loss_next ) {
		$GLOBALS['posts'][ $loss_next ]->post_content = held_as_before( str_pad( WORDS_LEAD, 2000, 'x' ), true, LOSS_REP );
		$drawn  = rows_of( draw( LOSS, LOSS_REP ), 'wpcpm-sevent-' . $loss_next );
		$typed  = mb_substr( area_text( $drawn['description'] ), 0, 2000 ) . '<b>y</b>';
		$left   = press( 'handle_change', loss_change( LOSS, $loss_next, LOSS_REP, array( 'wpcpm_sevent_description' => $typed ) ), LOSS_REP );
		$html   = draw( LOSS, LOSS_REP );
		$rows   = rows_of( $html, 'wpcpm-sevent-' . $loss_next );
		$room   = preg_match( '/<textarea id="wpcpm-sevent-' . $loss_next . '-description"[^>]* maxlength="(\d+)"/', $html, $found ) ? (int) $found[1] : 0;
		return array( mb_strlen( area_text( $drawn['description'] ) ), mb_strlen( $typed ), $left[0], area_text( $rows['description'] ) === $typed, $room );
	},
	array( 2002, 2008, 'sevent-change-description-loss', true, 2009 )
);

// A refusal the person puts right by pressing again brings the title and the description back
// exactly as typed too, as a refusal for lost words does. The request readers hand back the
// cleaner's text, which drops the "%ca" an event's own words can hold, so a box drawn from it would
// show "Get 10shback at the booth", and the next press would post that as an edit, store it and tell
// everyone of a new title. Drawn as typed, a box left as the event drew it stays the event's own
// through both presses. The event is approved, with two people on it, so a new title would be mailed.
attempt(
	static function () use ( $loss_next ) {
		WPCPM_Sponsor_Event::approve( $loss_next, MANAGER );
		add_post_meta( $loss_next, '_wpcpm_sevent_person', JOINER );
		add_post_meta( $loss_next, '_wpcpm_sevent_person', JOINER_TWO );
	}
);
const CASHBACK_TITLE = 'Get 10%cashback at the booth';
const CASHBACK_STORY = "Bring a laptop,\r\nand 10%cashback.";

ck(
	'an event holding "Get 10%cashback at the booth", its Change form posted as drawn with a length of 5, is refused for its fields and comes back with the title and the description exactly as the event holds them; that form posted again with the length as it was changes nothing: nothing is stored, logged, mailed or scheduled',
	static function () use ( $loss_next ) {
		$GLOBALS['posts'][ $loss_next ]->post_title   = CASHBACK_TITLE;
		$GLOBALS['posts'][ $loss_next ]->post_content = CASHBACK_STORY;
		$drawn  = loss_change( LOSS, $loss_next, LOSS_REP );
		$before = loss_state();
		$sent   = count( $GLOBALS['mail'] );
		$first  = press( 'handle_change', array_merge( $drawn, array( 'wpcpm_sevent_minutes' => '5' ) ), LOSS_REP );
		$again  = loss_change( LOSS, $loss_next, LOSS_REP, array( 'wpcpm_sevent_minutes' => $drawn['wpcpm_sevent_minutes'] ) );
		$second = press( 'handle_change', $again, LOSS_REP );
		$post   = get_post( $loss_next );
		return array( $first[0], $again['wpcpm_sevent_title'], $again['wpcpm_sevent_description'], $second, array( $post->post_title, $post->post_content ), array_slice( mailed_to(), $sent ), loss_state() === $before );
	},
	array( 'sevent-fields', CASHBACK_TITLE, CASHBACK_STORY, array( 'sevent-unchanged', 'events', LOSS, '' ), array( CASHBACK_TITLE, CASHBACK_STORY ), array(), true )
);
ck(
	'and for a change the store refuses because the sponsor\'s lock is held: the start moved, refused, the form comes back with the start as typed and the title and the description as the event holds them; pressed again once the lock is free, the change moves the start alone, and the event keeps its title and description, stored and in every mail',
	static function () use ( $loss_next ) {
		$drawn = loss_change( LOSS, $loss_next, LOSS_REP, array( 'wpcpm_sevent_time' => '17:00' ) );
		add_option( 'wpcpm_sevent_lock_' . LOSS, $GLOBALS['now'] );
		try {
			$first = press( 'handle_change', $drawn, LOSS_REP );
		} finally {
			delete_option( 'wpcpm_sevent_lock_' . LOSS );
		}
		$again           = loss_change( LOSS, $loss_next, LOSS_REP );
		$GLOBALS['mail'] = array();
		$second          = press( 'handle_change', $again, LOSS_REP );
		$post            = get_post( $loss_next );
		$titles          = array();
		$lost            = 0;
		foreach ( $GLOBALS['mail'] as $sent ) {
			$titles[] = title_line( $sent['mail']['body'] );
			$lost    += false !== strpos( $sent['mail']['subject'] . $sent['mail']['body'], '10shback' ) ? 1 : 0;
		}
		return array( $first[0], $again['wpcpm_sevent_time'], $again['wpcpm_sevent_title'], $again['wpcpm_sevent_description'], $second[0], array( $post->post_title, $post->post_content ), count( $titles ) > 0, array_values( array_unique( $titles ) ), $lost );
	},
	array( 'sevent-busy', '17:00', CASHBACK_TITLE, CASHBACK_STORY, 'sevent-changed', array( CASHBACK_TITLE, CASHBACK_STORY ), true, array( 'Title: ' . CASHBACK_TITLE ), 0 )
);
// The exact typing waits `WPCPM_Typed_Text::KEPT_FOR` and no longer, while the planner's channel,
// which holds the other rows as the request readers read them, waits for the next draw. A refusal
// whose page is looked at only after that draws the event's own title and description beside the
// channel's other rows, never the cleaner's text the channel holds for the two.
ck(
	'a refusal for its fields looked at only once the exact typing has expired draws the title and the description as the event holds them, the other rows as typed; posted again with the length as it was, it changes nothing',
	static function () use ( $loss_next ) {
		$GLOBALS['posts'][ $loss_next ]->post_title   = CASHBACK_TITLE;
		$GLOBALS['posts'][ $loss_next ]->post_content = CASHBACK_STORY;
		$drawn = loss_change( LOSS, $loss_next, LOSS_REP );
		$first = press( 'handle_change', array_merge( $drawn, array( 'wpcpm_sevent_minutes' => '5' ) ), LOSS_REP );
		$name  = 'typed:sevent:' . LOSS . ':' . $loss_next;
		$aged  = isset( $GLOBALS['flash'][ LOSS_REP ][ $name ]['at'] );
		if ( $aged ) {
			$GLOBALS['flash'][ LOSS_REP ][ $name ]['at'] = time() - WPCPM_Typed_Text::KEPT_FOR - 1;
		}
		$again  = loss_change( LOSS, $loss_next, LOSS_REP );
		$before = loss_state();
		$second = press( 'handle_change', array_merge( $again, array( 'wpcpm_sevent_minutes' => $drawn['wpcpm_sevent_minutes'] ) ), LOSS_REP );
		$post   = get_post( $loss_next );
		return array( $first[0], $aged, $again['wpcpm_sevent_minutes'], $again['wpcpm_sevent_title'], $again['wpcpm_sevent_description'], $second, array( $post->post_title, $post->post_content ), loss_state() === $before );
	},
	array( 'sevent-fields', true, '5', CASHBACK_TITLE, CASHBACK_STORY, array( 'sevent-unchanged', 'events', LOSS, '' ), array( CASHBACK_TITLE, CASHBACK_STORY ), true )
);

/* ---- the words ---------------------------------------------------------------- */

echo "\n=== The words ===\n";

$flashed = array( 'sevent-sent', 'sevent-changed', 'sevent-unchanged', 'sevent-canceled', 'sevent-fields', 'sevent-no-tier', 'sevent-one-open', 'sevent-one-open-term', 'sevent-one-open-more', 'sevent-term-used', 'sevent-term-used-more', 'sevent-past-term', 'sevent-before-term', 'sevent-in-past', 'sevent-busy', 'sevent-was-canceled', 'sevent-past', 'sevent-shrink', 'sevent-failed', 'sevent-ceiling', 'sevent-plan-title-loss', 'sevent-plan-description-loss', 'sevent-change-title-loss', 'sevent-change-description-loss', 'tier-off', 'refused' );

ck(
	'a sentence for every status the three presses flash, each a notice class and words, and nothing more',
	static function () use ( $flashed ) {
		$messages = WPCPM_Sponsor_Event_Planner::messages();
		$shapes   = array();
		foreach ( $messages as $key => $pair ) { $shapes[ $key ] = is_array( $pair ) && 2 === count( $pair ) && in_array( $pair[0], array( 'success', 'error', 'info' ), true ) && '' !== trim( (string) $pair[1] ); }
		$keys = array_keys( $messages );
		sort( $keys );
		$want = $flashed;
		sort( $want );
		return array( $keys === $want, array_values( array_unique( $shapes ) ) );
	},
	array( true, array( true ) )
);
ck(
	'the sentences a sponsor reads: planned, which waits for the program\'s OK and promises no confirmation; changed and canceled, which claim no mail nobody sent; one open, with no term or more to follow it, while a term runs, and when it is the last of the more events granted for the term; the term\'s event held, and the more events granted for it; a held lock',
	static function () {
		$messages = WPCPM_Sponsor_Event_Planner::messages();
		return array( $messages['sevent-sent'], $messages['sevent-changed'], $messages['sevent-canceled'], $messages['sevent-one-open'][1], $messages['sevent-one-open-term'][1], $messages['sevent-one-open-more'][1], $messages['sevent-term-used'][1], isset( $messages['sevent-term-used-more'] ) ? $messages['sevent-term-used-more'][1] : '', $messages['sevent-busy'][1] );
	},
	array(
		array( 'success', 'Your event is planned. It waits for the program\'s OK, and nobody else sees it until then.' ),
		array( 'success', 'Your event is changed.' ),
		array( 'success', 'Your event is canceled.' ),
		'Your next event can be planned once this one has taken place.',
		'Your event for this term is planned already.',
		'Your events for this term are planned already.',
		'Your event for this term has taken place already.',
		'Your events for this term have taken place already.',
		'Another change to your company\'s events was going through. Try again in a moment.',
	)
);
ck(
	'a start outside the term is said in the store\'s words, the day and the clock left for the flash to fill in',
	static function () {
		$messages = WPCPM_Sponsor_Event_Planner::messages();
		return array( $messages['sevent-past-term'], $messages['sevent-before-term'] );
	},
	array(
		array( 'error', 'Choose a start on or before %1$s, the last day of your sponsorship term on the site\'s clock (%2$s).' ),
		array( 'error', 'Choose a start on or after %1$s, the first day of your current sponsorship term on the site\'s clock (%2$s).' ),
	)
);
ck(
	'the ceiling is said as the Mentors card says its own, "Five a day is the limit. Try again tomorrow.", per hour where that card counts per day',
	static function () { return WPCPM_Sponsor_Event_Planner::messages()['sevent-ceiling']; },
	array( 'error', 'Ten an hour is the limit. Try again in an hour.' )
);
ck(
	'a change that changes nothing says so, in the words and the notice class the profile card says it in on the same page',
	static function () { return WPCPM_Sponsor_Event_Planner::messages()['sevent-unchanged']; },
	array( 'info', 'Nothing changed.' )
);
ck(
	'refused and tier-off read exactly as the tier card\'s, so whichever wins the dashboard\'s merge reads the same',
	static function () {
		$planner = WPCPM_Sponsor_Event_Planner::messages();
		$card    = WPCPM_Sponsor_Tier_Card::messages();
		return array( $planner['refused'] === $card['refused'], $planner['tier-off'] === $card['tier-off'] );
	},
	array( true, true )
);

/* ---- the house rules ---------------------------------------------------------- */

echo "\n=== The house rules ===\n";

$planner_src = (string) @file_get_contents( $planner_file );

ck(
	'the planner holds no em or en dash, no sponsor ID compared with ===, no Airtable',
	array( '' !== $planner_src, preg_match( '/\x{2013}|\x{2014}/u', $planner_src ), preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $planner_src ), false !== strpos( $planner_src, 'WPCPM_Airtable' ) ),
	array( true, 0, 0, false )
);
ck(
	'and writes nothing of its own: the store writes, the planner reads the request, draws, and leaves a refused form\'s typing on the flash',
	array( '' !== $planner_src, preg_match( '/\b(update_post_meta|add_post_meta|delete_post_meta|wp_insert_post|wp_update_post|update_option|add_option|record_sponsor)\s*\(/', $planner_src ) ),
	array( true, 0 )
);
ck( 'every query the stand-ins were sent is one they model, over every event the presses wrote', array( count( event_ids() ) > 2, $GLOBALS['unmodeled'] ), array( true, array() ) );

$GLOBALS['run_complete'] = true;

// What every mail of the suite says, as the recording made before the event and term emails moved
// into the template registry holds it (bin/stubs/mail-recording.php).
// A title held with a line break, by the store's own rule, kept in a subject that is one line: the
// registry writes each break a space, where the builder left it for the mail layer's cleaner, which
// folds every run of white space into one space, so the subject that goes out is the same.
$one_line = array();
foreach ( array( 195, 196, 197, 198 ) as $n ) {
	$one_line[ $n ] = array( 'subject' => array( "Get 10%cashback\nat the booth" => 'Get 10%cashback at the booth' ) );
}
foreach ( array( 200, 201 ) as $n ) {
	$one_line[ $n ] = array( 'subject' => array( "Meet\rthe team\r\n\r\ntoday" => 'Meet the team  today' ) );
}
foreach ( wpcpm_recording_checks( 'sponsor-event-mails-planner.json', 'e18bd3f139436547f0ce5e98d11dd6036ae06444fb5bfa9042961d20c57cad02', array_keys( array_filter( WPCPM_Mail_Catalog::emails(), static function ( $e ) { return WPCPM_Mail_Catalog::MODULE_EVENTS === $e['module']; } ) ), $one_line, '' ) as $recorded_check ) {
	ck( $recorded_check[0], $recorded_check[1], $recorded_check[2] );
}

// The site's name as WordPress stores it, its "&" an entity, is handed over as stored and read back once
// by the registry: the planner's four mails to a sponsor's member and its eight to the program contact
// name the site as a person typed it, and none shows "&amp;" in its subject or its body. After the
// recording's checks, which build no mail of their own.
$GLOBALS['site_name'] = 'Arts &amp; Sciences Dashboard';
ck(
	'a site name holding an "&", stored as an entity, reads as typed in the subject of each of the planner\'s 4 mails to a sponsor\'s member and 8 to the program contact, and "&amp;" is in none of their subjects or bodies',
	static function () {
		$ids   = event_ids();
		$event = get_post( $ids[0] );
		$facts = WPCPM_Sponsor_Event::facts( $event );
		$out   = array();
		foreach ( array( 'event-changed-sponsor', 'event-changed-sponsor-for', 'event-canceled-sponsor', 'event-canceled-sponsor-for' ) as $id ) {
			$mail       = ( new ReflectionMethod( 'WPCPM_Sponsor_Event_Planner', 'member_mail' ) )->invoke( null, $event, $id, 'Partner Example', $GLOBALS['users'][ PARTNER_REP ] );
			$out[ $id ] = array( 0 === strpos( $mail['subject'], '[Arts & Sciences Dashboard] ' ), false === strpos( $mail['subject'] . $mail['body'], '&amp;' ) );
		}
		foreach ( array( 'event-planned', 'event-planned-for', 'event-changed-waiting', 'event-changed-waiting-for', 'event-changed-approved', 'event-changed-approved-for', 'event-canceled-by-sponsor', 'event-canceled-for' ) as $id ) {
			$mail       = ( new ReflectionMethod( 'WPCPM_Sponsor_Event_Planner', 'contact_mail' ) )->invoke( null, $id, 'Partner Example', $facts, 'https://call.example/room', 'https://site.example/administrator-dashboard/', $GLOBALS['users'][ MANAGER ] );
			$out[ $id ] = array( 0 === strpos( $mail['subject'], '[Arts & Sciences Dashboard] ' ), false === strpos( $mail['subject'] . $mail['body'], '&amp;' ) );
		}
		return $out;
	},
	array_fill_keys( array( 'event-changed-sponsor', 'event-changed-sponsor-for', 'event-canceled-sponsor', 'event-canceled-sponsor-for', 'event-planned', 'event-planned-for', 'event-changed-waiting', 'event-changed-waiting-for', 'event-changed-approved', 'event-changed-approved-for', 'event-canceled-by-sponsor', 'event-canceled-for' ), array( true, true ) )
);
unset( $GLOBALS['site_name'] );

// The cases the recording never sends, each as the code wrote it before the registry held the
// sentences, but for what it called an Administrator: the Administrator Dashboard has no published page,
// so the mails to the program contact about an event that waits, or stays approved, end at their
// sentence with no address under it; and the site cannot write a calendar file, so the mails to the
// sponsor's member about a change and a cancel say nothing of one. Run once for a sponsor whose member
// presses, and once for a sponsor an Administrator presses for through the switcher, whose mails say
// so in words of their own.
$plain_run = static function ( $key, $rep, $name, $actor ) {
	$GLOBALS['index'][ $key ] = array( 'record_id' => $key, 'name' => $name, 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '', 'manager' => (string) CONTACT );
	a_user( $rep, 'Pam Plainrep' . $rep, array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => $key, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'America/New_York' ) );
	$out  = array();
	$take = static function ( $step ) use ( &$out ) {
		foreach ( $GLOBALS['mail'] as $sent ) {
			$out[ $step . ': ' . $sent['context'] ] = array( $sent['to'], $sent['mail']['subject'], $sent['mail']['body'], isset( $sent['mail']['attachments'] ) ? count( $sent['mail']['attachments'] ) : 0 );
		}
		$GLOBALS['mail'] = array();
	};

	$GLOBALS['mail']     = array();
	$GLOBALS['no_pages'] = true;
	press( 'handle_plan', form( $key, array( 'wpcpm_sevent_date' => '2027-01-12', 'wpcpm_sevent_title' => 'Plain event' ) ), $actor );
	$ids = event_ids();
	$id  = end( $ids );
	$take( 'plan' );
	press( 'handle_change', form( $key, array( 'wpcpm_sevent_id' => (string) $id, 'wpcpm_sevent_date' => '2027-01-13', 'wpcpm_sevent_title' => 'Plain event' ) ), $actor );
	$take( 'change while it waits' );
	WPCPM_Sponsor_Event::approve( $id, MANAGER );
	$GLOBALS['mail']    = array();
	$GLOBALS['no_temp'] = true;
	press( 'handle_change', form( $key, array( 'wpcpm_sevent_id' => (string) $id, 'wpcpm_sevent_date' => '2027-01-14', 'wpcpm_sevent_title' => 'Plain event' ) ), $actor );
	$take( 'change once approved' );
	press( 'handle_cancel', array( 'wpcpm_sponsor' => $key, 'wpcpm_sevent_id' => (string) $id ), $actor );
	$take( 'cancel' );
	$GLOBALS['no_temp']  = false;
	$GLOBALS['no_pages'] = false;

	return $out;
};
// What the program contact's mails end with, the same in each: the event's length, places and call link,
// then its description.
$plain_facts = "Length: 90 minutes\nPlaces: 40\nCall link: https://call.example/room?pwd=a%2Fb&id=7\n\nDescription:\nWhat we \"cover\" & more.\nBring questions.";
$plain_wait  = 'Approve it, or return it with a note, on the Administrator Dashboard under Events.';
ck(
	'a sponsor\'s member presses, and the site has no Administrator Dashboard page and cannot write a calendar file: the program contact\'s mails about a plan, a change while it waits and a change once approved end at their sentence, and the member\'s mails about a change and a cancel say nothing of a file',
	$plain_run( 'spoCCCC0000000001', 90, 'Plain Example One', 90 ),
	array(
		'plan: event-planned'                           => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example One planned an event',
			"Plain Example One planned an event on the Sponsor Dashboard. Nobody else sees it until an Administrator approves it.\n\nTitle: Plain event\nWhen: January 13, 2027 4:00 am to 5:30 am (Asia / Tokyo)\n$plain_facts\n\n$plain_wait",
			0,
		),
		'change while it waits: event-changed-waiting'  => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example One changed its event',
			"Plain Example One changed its event on the Sponsor Dashboard. It is still waiting for an Administrator's approval.\n\nTitle: Plain event\nWhen: January 14, 2027 4:00 am to 5:30 am (Asia / Tokyo)\n$plain_facts\n\n$plain_wait",
			0,
		),
		'change once approved: event-changed-sponsor'   => array(
			'pam.plainrep90@people.example',
			'[WordPress Education] Your event is changed: Plain event',
			"Your company's event is changed.\n\nTitle: Plain event\nWhen: January 14, 2027 2:00 pm to 3:30 pm (America / New York)\nLength: 90 minutes\nPlaces: 40\nCall link: https://call.example/room?pwd=a%2Fb&id=7",
			0,
		),
		'change once approved: event-changed-approved'  => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example One changed its event',
			"Plain Example One changed its approved event on the Sponsor Dashboard. It stays approved.\n\nTitle: Plain event\nWhen: January 15, 2027 4:00 am to 5:30 am (Asia / Tokyo)\n$plain_facts\n\nThe Administrator Dashboard lists it under Events.",
			0,
		),
		'cancel: event-canceled-sponsor'                => array(
			'pam.plainrep90@people.example',
			'[WordPress Education] Your event is canceled: Plain event',
			"Your company's event is canceled.\n\nTitle: Plain event\nWhen: January 14, 2027 2:00 pm to 3:30 pm (America / New York)",
			0,
		),
		'cancel: event-canceled-by-sponsor'             => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example One canceled its event',
			"Plain Example One canceled its event on the Sponsor Dashboard.\n\nTitle: Plain event\nWhen: January 15, 2027 4:00 am to 5:30 am (Asia / Tokyo)\n$plain_facts",
			0,
		),
	)
);
ck(
	'and an Administrator presses for a sponsor through the switcher: the same, in the words of what an Administrator did on the sponsor\'s behalf',
	$plain_run( 'spoCCCC0000000002', 91, 'Plain Example Two', MANAGER ),
	array(
		'plan: event-planned-for'                          => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example Two planned an event',
			"An Administrator planned an event on the Sponsor Dashboard on Plain Example Two's behalf. Nobody else sees it until an Administrator approves it.\n\nTitle: Plain event\nWhen: January 12, 2027 10:00 pm to 11:30 pm (Asia / Tokyo)\n$plain_facts\n\n$plain_wait",
			0,
		),
		'change while it waits: event-changed-waiting-for' => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example Two changed its event',
			"An Administrator changed the event on the Sponsor Dashboard on Plain Example Two's behalf. It is still waiting for an Administrator's approval.\n\nTitle: Plain event\nWhen: January 13, 2027 10:00 pm to 11:30 pm (Asia / Tokyo)\n$plain_facts\n\n$plain_wait",
			0,
		),
		'change once approved: event-changed-sponsor-for'  => array(
			'pam.plainrep91@people.example',
			'[WordPress Education] Your event is changed: Plain event',
			"An Administrator changed it on Plain Example Two's behalf. Your company's event is changed.\n\nTitle: Plain event\nWhen: January 14, 2027 8:00 am to 9:30 am (America / New York)\nLength: 90 minutes\nPlaces: 40\nCall link: https://call.example/room?pwd=a%2Fb&id=7",
			0,
		),
		'change once approved: event-changed-approved-for' => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example Two changed its event',
			"An Administrator changed the approved event on the Sponsor Dashboard on Plain Example Two's behalf. It stays approved.\n\nTitle: Plain event\nWhen: January 14, 2027 10:00 pm to 11:30 pm (Asia / Tokyo)\n$plain_facts\n\nThe Administrator Dashboard lists it under Events.",
			0,
		),
		'cancel: event-canceled-sponsor-for'               => array(
			'pam.plainrep91@people.example',
			'[WordPress Education] Your event is canceled: Plain event',
			"An Administrator canceled it on Plain Example Two's behalf. Your company's event is canceled.\n\nTitle: Plain event\nWhen: January 14, 2027 8:00 am to 9:30 am (America / New York)",
			0,
		),
		'cancel: event-canceled-for'                       => array(
			'mo.contact@people.example',
			'[WordPress Education] Plain Example Two canceled its event',
			"An Administrator canceled the event on the Sponsor Dashboard on Plain Example Two's behalf.\n\nTitle: Plain event\nWhen: January 14, 2027 10:00 pm to 11:30 pm (Asia / Tokyo)\n$plain_facts",
			0,
		),
	)
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
