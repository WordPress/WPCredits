<?php
/**
 * The sponsor event store (1.117.0): the post type, the allowance, the states, who may join, the
 * calendar file, the audit and the policy's action.
 *
 * What is pinned here and why:
 *
 * - The names: the post type `wpcpm_sponsor_event` (nineteen characters, inside the twenty
 *   `register_post_type()` accepts), its thirteen meta keys and the mark of its reminders, the three
 *   states, the one kind, the limits, the five audit kinds, the lock's prefix and its two minutes,
 *   the four audiences, fifty told in the press, the drain's cron event and its minute, the three
 *   kinds of row still to tell and the reminders' window, twelve to thirty-six hours after the run.
 *   `init()` hooks the post type and its meta on `init` at 5, the sponsors store's own priority, and
 *   the drain on its cron event; the type is private everywhere, with a title and an editor, under a
 *   capability type nothing is granted, and every key is registered out of REST and out of reach of
 *   the meta API. The loader and uninstall.php require the file right after the sponsor report card,
 *   and the Sponsors module boots it with its other classes.
 * - `fields_from()`: the day and the time are read on the planner's clock and stored as UTC, an
 *   hour the clocks skip is refused, the places are blank for no limit, the link is an http or
 *   https address kept as typed (a percent-encoded query survives), and every limit is refused
 *   with a sentence keyed by its field. It writes nothing. The title's and the description's
 *   limits count what the person typed, as the box counted it (`typed_length()`): the entities
 *   core's cleaner writes for a < that opens no tag, and for the quote marks and the ampersand
 *   after it, count as the one character each stands for, and a line break counts once however a
 *   text area posts it, so typing within the box is never refused for what the cleaner made of it,
 *   one more character is, the value is stored as cleaned, and the call link keeps its own count.
 *   What kses writes for a person without unfiltered_html, `&amp;` for an ampersand and `&gt;` for a
 *   >, counts as one character each in the same way.
 * - `may_plan()`: the switch, the tier (a Backer, a lapsed Partner, a key the store lacks), one
 *   event at a time, one per term, the term's last day on the site's own clock (2027-01-31 allowed,
 *   2027-02-01 refused, and a start that is 1 February in the site's zone refused too, in words
 *   that name the last day and the site's clock), a start that has passed, a start of 0 asking the
 *   sponsor's questions alone; a Champion with no term is bound by one event at a time and nothing
 *   else.
 * - The term as the row records it: `term_from()` is the day after the latest end recorded before
 *   the last day, the term a renewal moved off, the term the run last drafted or the sponsor's
 *   latest report, and nothing for an end on or after the last day or a day that does not exist.
 *   Renewed early, a start before that day is counted in the term that still runs, spent by its
 *   event (term_used, `plan()` writing nothing), while the renewed term is free from its first day;
 *   renewed early with no event yet, that term's own is still planned, and moved inside it; a report
 *   drafted by hand before the renewal bounds the term the same way. A term renewed after it ended
 *   is still the twelve months ending on its last day, so an event of the term before dated in them
 *   spends it, and an end recorded before those twelve months widens nothing. `change()` refuses
 *   `before_term` for an event of the renewed term moved back before its first day, in words that
 *   name that day and the site's clock, and nothing moves; an event of the term before moves inside
 *   it or on into the renewed term.
 * - Extra events for one term: `extra_events()` answers the number the row's allowance holds while it
 *   names the last day asked about, and none for another day or for anything that is no allowance. A
 *   term counts its approved events against the tier's one and that many more, one event at a time
 *   still coming first. Renewed early, the number stays with the term it was granted for, which keeps
 *   it for the days it still runs, while the renewed term counts it for nothing until the Tier box
 *   grants one for that term; and a number granted for the renewed term reaches no day of the term
 *   before.
 * - The states: `plan()` writes a private post and its meta, slashed as core unslashes them, under
 *   the sponsor's lock, released after; `approve()`, `change()`, `return_with_note()` and `cancel()`
 *   move it, each raising the calendar version and writing one audit row of its kind with the
 *   ground it acted on, a member's and a manager's cancel both; a change keeps the new start on or
 *   before the term's last day, the lapsed term's included; a return's note is kept to 2,000
 *   characters, and `returned_note()` answers it while the returned event is the sponsor's latest;
 *   an event whose end has passed takes no move; a title and a description the Change form posted
 *   back as drawn, named `unedited`, are the event's own, so a change handed them in other words
 *   changes nothing; a canceled event or a returned one frees the
 *   allowance, an approved one that took place spends the term's, and not the next term's once a
 *   renewal has moved the last day on. `approve()` refuses a sponsor that no longer offers events
 *   (Paused, its term ended, gone from the store), writing nothing and giving the lock back. A
 *   return takes a waiting event alone: on an approved one, somebody on it, the store itself
 *   answers `not_planned`, writes nothing and mails nobody, since a return would tell nobody on it.
 * - Who may join: current students as the Tools section counts them, Active mentors as the mentors
 *   module reads them, program managers, institution contacts as the Institution Dashboard
 *   resolves them; a sponsor's own members are refused their own event and do not see it listed.
 *   The places hold, a leave frees one, a planned or canceled event is not open, a past one is
 *   past. `open_for()` lists the approved events not over yet, soonest first, and only while their
 *   sponsor is in the program and its tier includes events: a sponsor set to Paused, one the store
 *   no longer holds and one whose term has lapsed offer nothing, while the sponsor's own card keeps
 *   the event.
 * - `held_in()`: approved events that have ended, whose end falls inside the period on the site's
 *   clock, at both edges, each as its title, start, minutes and the count of people on it; one
 *   still to come is not held yet.
 * - `public_upcoming()`, the Partners page's reader: the approved events not over yet, one
 *   already running among them, soonest first, of a sponsor that still offers events on the day
 *   given (not one set to Paused, lapsed, on a tier with no events, gone from the store or with no
 *   name) or the program's own, each as its title, its host, the host's website as the sponsors
 *   store holds it, its start, end and minutes and its description with every http, https and
 *   mailto address taken out, a bare email address and a bare www. one kept, and its spaces run
 *   together, and nothing else: no call link, nobody on it by
 *   name, address or count, no places, no clock, no ID and no key. A title changed after the
 *   approval is the page's at once. Nothing at all while the launch switch is off.
 * - The page's cache: an approval, a change of an approved event, the cancel of an approved event
 *   and a plan of the program's own each tell the host's page cache the Partners page
 *   changed, once, through `clean_post_cache()` of the page `WPCPM_Sponsors_Page` records; a
 *   sponsor's plan, a return, the change or the cancel of an event still waiting, a change that
 *   changes nothing and a refused plan tell it nothing.
 * - The calendar file: the organizer is who the program's mail comes from (core's own sender,
 *   through the two filters `wp_mail()` applies), never an address a setting holds for something
 *   else; the title, the description and the link, the start and end in UTC, the event's version,
 *   a cancellation for a canceled event, and the person it is for as its attendee, by name and
 *   address, as a request names one: nobody else is named in it, so each person gets a file of
 *   their own, and one asked for nobody names the program alone. A caller may name the method
 *   instead (a leave's cancel of an approved event), and a request and a cancel at one version are
 *   two files; each is given back, to the same person, while it is still there.
 * - The people on an event are told by the store: `cancel()` answers how many it told in the press
 *   and how many it left to the drain, and `change()` whether it changed anything besides; each
 *   person on the event is mailed once, in the `sponsor-event` context, on their own clock, with a
 *   request at the new version for a change or the cancel for a cancel, the file naming them and
 *   removed once the send is handed off; the accounts are read in one query before the first send;
 *   an event nobody is on answers nobody told and nobody left, and puts nothing on the clock. The
 *   first mail of a move leaves after the move's audit row is written and the sponsor's lock is
 *   given back. A Leave on a canceled event is refused `cancelled`, and the person stays on its
 *   record.
 * - What a change tells: one that alters nothing writes nothing, raises no version, writes no row
 *   and mails nobody, saved from another clock, with a title typed with a named entity, or with a
 *   title WordPress stored with an entity for its ampersand; one of the places or the description
 *   alone is written and raises the version, and tells nobody on the event; one of the time, the
 *   length, the title or the link tells everybody on it.
 * - Fifty in the press, the rest by the drain: a cancel of 120 people tells the first fifty, in the
 *   order they joined, and leaves seventy rows at the cancel's version, with the drain on the clock
 *   a minute out before the first send; every row is written before that send, and each person's is
 *   off the event before their mail leaves. The drain tells fifty a run, back on the clock before
 *   its first send while it finds anybody, and the run that finds nobody puts nothing back; each of
 *   the 120 is told once. A run that dies just after a hand-off leaves the rest to the next runs,
 *   nobody told twice; one that dies in the middle of a send loses that one person alone. A change
 *   of the time tells fifty and leaves seventy; a change of the description after it leaves them
 *   as they were; a person who left before the drain reached them is told nothing; a cancel before
 *   the drain is done replaces the list. A press while a run is due puts no second one on the
 *   clock, a row another request took is not sent again, and an account that is gone is written
 *   down for nobody. The drain takes the oldest event first. A drain whose event the clock lost is
 *   put back by `resume_drain()`, once, while rows wait, and not at all once none do. A drain
 *   that runs after the event is over takes a change's rows and sends nothing, while a cancel's
 *   still go.
 * - The day before: `queue_reminders()`, while Sponsor tiers is on, writes a `reminder` row at the
 *   event's version for each person on an approved event that starts from twelve hours after the
 *   run up to thirty-six, in the order they joined, and then for each live member of its sponsor
 *   not on it, a member who joined once and a detached one never. The window is hours on the
 *   store's clock, not the site's days: at 22:30 on the site's clock it reminds people of an event
 *   at 10:29 the day after tomorrow and of none just after midnight. Only while the sponsor offers
 *   events on the day handed in, or on the site's own day when none is, which the run asks of
 *   `offers_events()`, as every other reader does, and never by hand; each event once, marked
 *   with the version reminded, so a second run, a person who joined since and a change after the
 *   mark write nothing. Nothing is mailed in the run: the drain tells the rows, fifty a run. A
 *   person on the event is reminded whose event it is, its title, when on their own clock, its
 *   length and the call link; a member of the title, when, and how many have joined so far, or that
 *   nobody has yet; neither with a calendar file, each in the `sponsor-event` context and the
 *   recipient's language, naming nobody else. Who is told is asked when they are told, so a person
 *   who left since and a member detached since are told nothing; a cancel before the drain replaces
 *   the reminders with its own rows; and a reminder row on an event that is over, or canceled by
 *   another hand, is taken off and sends nothing.
 * - The kinds of row still to tell: a change writes `change` rows and a cancel `cancel` rows; a
 *   row of the shape before rows carried a kind, a person and a version, is told as the event's
 *   state says, as it always was; a change row on an event canceled since is taken off and sends
 *   nothing; and a row of a kind the store never writes is left out, and left alone.
 * - A run that fires late, and a lock held at the run: the window starts where the last complete
 *   one stopped, kept in `wpcpm_sevent_reminder_edge`, when that is sooner than twelve hours on, so
 *   a run 30 hours after the one before reminds the events in the band it would have left, while a
 *   run a day after it opens its window twelve hours on as always, and the first run ever does too;
 *   never before the run's own moment, so an event that has started is not reminded. A sponsor's
 *   lock another write holds leaves its event unmarked, keeps the edge where the window began, the
 *   first run's own start included, and puts one retry on the clock five minutes out; the retry
 *   reminds the event and moves the edge on, and a run that leaves nothing for a retry moves it on
 *   and schedules none. Under the lock the event is read again past the request's copy, an event
 *   another run marked since the walk writes nobody down and keeps that mark, and an account that
 *   is gone gets no row; a run cut off part way through its rows has put the drain on the clock
 *   before the first of them.
 * - `open_for()` keeps, for a person on an approved event not over yet, that event whatever became
 *   of its sponsor or of their own place in the program, so they can leave it; nobody else is
 *   offered it, and `join()` still refuses it.
 * - The program's own events: `plan_program()` is a program manager's alone, refused while the
 *   switch is off and for a start that has passed, and writes a private post with no sponsor, the
 *   program's kind, approved as it is planned and stamped with the manager, under the one lock
 *   `wpcpm_sevent_lock_program`, with a row through the audit's writer of the program's own rows
 *   that names no sponsor; none of a sponsor's rules holds it, a second event while the first is to
 *   come and a day a year and more ahead included. `sponsor_of()` is nobody's for it whatever its
 *   rows hold, `offers_events()` asked of it is true, `facts()` names the WordPress Credits Program
 *   as its host, and every audience sees and joins it, the Partner's own mentor included. A change
 *   and a cancel tell the people on it, naming the program as the host; the daily run reminds them
 *   alone, and a held lock leaves it to the retry; no sponsor's card, allowance or report counts it;
 *   its calendar file names the program's sender as its organizer, as a co-hosted event's does;
 *   `delete_all()` takes it and its lock; and its rows hold no name, link, description or title.
 * - The words every line about an event is said in, one copy each on the store: `mail_values()`, the
 *   values every email about an event fills its template with, and `length_line()`, beside
 *   `sponsor_name()`; the four sponsor event files hold no other, and the When line is the template
 *   registry's.
 * - The lock, held and stale, around every write, a plan and a leave included, read from the table
 *   itself, so a request whose own copy of the options says it is free while another request holds
 *   it is turned away all the same, and every read under it fresh; `delete_all()`, every event
 *   deleted past the trash with the rows still to tell on it, every lock, the reminders' edge, and
 *   the drain and the reminders' retry off the clock, and nothing else.
 * - The mails give a member's words as typed: a title holding a "<3", a tag, a ">", an ampersand
 *   and quotes, which the post holds as entities, reaches the change, the reminder and the cancel
 *   as typed, every entity read back, in the Title line and in the subject, which goes to the mail
 *   layer as plain text: the layer's cleaner, as core's, would read the "<3" as the start of a tag
 *   and drop the words after it.
 * - The privacy walk: the audit's messages and facts and `held_in()`'s answer hold no joiner's
 *   name or address, no link and no description, and the audit's facts no title; no mail to a
 *   person on an event names anybody else on it, or the description.
 * - The house rules: the store mails the people on an event from a change and a cancel, and them
 *   and its sponsor's members from the daily run's reminders, through the mail layer from one
 *   place; it renders nothing, names no Airtable class and holds no em or en dash.
 *
 * WordPress is a small store in memory: posts, their meta as repeated rows the way core keeps them
 * (a person on an event is one row each, and `get_post_meta( ..., true )` answers the first), the
 * slash pair core applies to a post and to its meta, values read back as the strings a database
 * returns, options with `add_option()` as the test-and-set, a request's own copy of the options
 * holding a name as absent where a check lists it, as core's list of absent names does on a site
 * with a persistent object cache (`get_option()` answers it absent, and `add_option()` skips its
 * own look and writes over the row, reporting a win), a `$wpdb` that runs the two statements the
 * store sends, a lock's read of its row and the clean-up's, the cron schedule as core keeps single
 * events, a due one taken off before its hook fires as wp-cron does, the one user query the sponsor
 * memberships send, the switch into the language of the person a mail is for, which the mail layer
 * makes around each send, and the clock, which the store reads through its own seam so that a
 * term's last day written here stays ahead of "now" however old the suite grows; the Partners page
 * as `WPCPM_Sponsors_Page` records it, whose own class is loaded, and `clean_post_cache()`, which
 * keeps each post it is asked for. A row deleted answers whether it was there, as core's delete
 * does. A query the stand-ins do not model is kept apart and fails a check. The classes whose
 * answers the store asks for are the plugin's own: the roles, the tier ladder, the report's period,
 * the policy and the sponsor memberships, the Tools section's current student, the mentors module's
 * Active mentor, the institution roster, the sessions' date reader, the calls' range formatter, the
 * calendar builder and the mail layer, whose `wp_mail()` keeps what it is handed, each attachment
 * read at the moment of the send, with where the rows to tell stood then, and dies where a check
 * asks it to. What they read beside those, the settings, a student's cached status, the sponsors
 * store and the audit trail, is stood in for the calls made. Every check runs inside its own guard,
 * so a store that is missing fails each check rather than ending the run at the first.
 *
 * Run from the plugin root:  php bin/test-sponsor-events.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

date_default_timezone_set( 'UTC' );

$GLOBALS['now']         = (int) strtotime( '2026-10-06 12:00:00 UTC' );
$GLOBALS['site_tz']     = 'UTC';
$GLOBALS['opts']        = array(
	'admin_email' => 'admin@site.example',
	'blogname'    => 'WordPress Education',
	'date_format' => 'F j, Y',
	'time_format' => 'g:i a',
);
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['next_id']     = 100;
$GLOBALS['hooks']       = array();
$GLOBALS['post_types']  = array();
$GLOBALS['post_meta']   = array();
$GLOBALS['users']       = array();
$GLOBALS['umeta']       = array();
$GLOBALS['manage']      = array();
$GLOBALS['uid']         = 0;
$GLOBALS['audit']       = array();
$GLOBALS['program_audit'] = array();
$GLOBALS['refused']     = array();
$GLOBALS['mail']        = array();
$GLOBALS['forgotten']   = array();
$GLOBALS['added']       = array();
$GLOBALS['unmodeled']   = array();
$GLOBALS['notoptions']  = array();
$GLOBALS['purged']      = array();
$GLOBALS['insert_fail'] = false;
$GLOBALS['filters']     = array();
$GLOBALS['deleted']     = array();
$GLOBALS['cached']      = array();
$GLOBALS['all_mail']    = array();
$GLOBALS['cron']        = array();
$GLOBALS['send_log']    = array();
$GLOBALS['locale_for']  = array();

/* ---- WordPress, stood in for the parts the store touches ------------------ */

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

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function _x( $text, $context, $domain = null ) { return $text; }
/** `apply_filters()`, running what a check registered under `$GLOBALS['filters']`. */
function apply_filters( $hook, $value, ...$args ) {
	// The mail layer's filter names the id a mail is logged under, which the recording keeps.
	if ( 'wpcpm_mail' === $hook ) {
		$GLOBALS['mail_context'] = isset( $args[0] ) ? (string) $args[0] : '';
	}
	foreach ( isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ] : array() as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}
	return $value;
}
function absint( $value ) { return abs( (int) $value ); }
function number_format_i18n( $number, $decimals = 0 ) { return (string) $number; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_title( $title ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' ); }

// Core's two text cleaners, as 7.1.2 writes them (bin/stubs/cleaners.php): a "<" that opens no tag
// is written as "&lt;", with everything after it escaped as esc_html() escapes it, a reference typed
// out in it in core's own form (`&#9;` as `&#009;`); tags go; percent-encoded octets go (which is why
// a call link must never pass through them); only the text field folds its line breaks, and the text
// area keeps each one as a browser posts it, two bytes. `wp_check_invalid_utf8()` stands beside
// them, as the plugin calls it itself.
require_once __DIR__ . '/stubs/cleaners.php';
function wp_check_invalid_utf8( $text, $strip = false ) { return (string) $text; }
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name ); }
function is_email( $value ) { return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : false; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_specialchars_decode( $text, $quotes = ENT_NOQUOTES ) { return htmlspecialchars_decode( (string) $text, ENT_QUOTES ); }
function home_url( $path = '' ) { return 'https://site.example' . $path; }
function network_home_url( $path = '' ) { return ( isset( $GLOBALS['network_home'] ) ? $GLOBALS['network_home'] : 'https://site.example' ) . $path; }
function get_bloginfo( $show = '' ) { return isset( $GLOBALS['site_name'] ) ? (string) $GLOBALS['site_name'] : 'WordPress Education'; }

/**
 * Core's check of an address before a request is made to it, without the one part that reaches the
 * network (the host's lookup): http or https alone, a host, no user or password in it, no stray
 * character in the host, and the three ports core allows.
 */
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

/** The site's clock and zone: a date asked for with no moment is the suite's own now. */
function wp_date( $format, $timestamp = null, $timezone = null ) {
	$when = new DateTime( '@' . ( null === $timestamp ? (int) $GLOBALS['now'] : (int) $timestamp ) );
	$when->setTimezone( $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( $GLOBALS['site_tz'] ) );
	return $when->format( (string) $format );
}
function wp_timezone_string() { return $GLOBALS['site_tz']; }
function wp_timezone() { return new DateTimeZone( $GLOBALS['site_tz'] ); }

/**
 * An option, as this request reads it: a name its copy of the options holds as absent
 * (`$GLOBALS['notoptions']`, which a check fills) is answered absent, whatever the table holds, as
 * core answers from its list of absent names before it asks the cache or the table.
 */
function get_option( $name, $default = false ) {
	if ( isset( $GLOBALS['notoptions'][ $name ] ) ) { return $default; }
	return array_key_exists( $name, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) { $GLOBALS['opts'][ $name ] = $value; return true; }
function delete_option( $name ) { $had = array_key_exists( $name, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $name ] ); return $had; }
/**
 * The test-and-set a lock is built on: one row per name, refused when the row is there. A name this
 * request's copy holds as absent is not looked for: core skips its own look for one, and its
 * `INSERT ... ON DUPLICATE KEY UPDATE` writes over a row another request holds and reports a win.
 */
function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
	// Another request's step, run once as the lock of the name a check gives is claimed for the time
	// it counts, before that claim: what that request did between this one's look at an event and
	// its lock.
	if ( isset( $GLOBALS['claim_hook'] ) && 0 === strpos( (string) $name, $GLOBALS['claim_hook'][0] ) && 0 === --$GLOBALS['claim_hook'][2] ) {
		$step = $GLOBALS['claim_hook'][1];
		unset( $GLOBALS['claim_hook'] );
		$step();
	}
	if ( ! isset( $GLOBALS['notoptions'][ $name ] ) && array_key_exists( $name, $GLOBALS['opts'] ) ) { return false; }
	unset( $GLOBALS['notoptions'][ $name ] );
	$GLOBALS['opts'][ $name ] = $value;
	return true;
}

/** Every hook registered, with its priority, so `init()` can be read and then fired. */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ (string) $hook ][] = array( 'callback' => $callback, 'priority' => (int) $priority );
	return true;
}
function fire( $hook ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $listener ) {
		call_user_func( $listener['callback'] );
	}
}
function register_post_type( $type, $args = array() ) { $GLOBALS['post_types'][ $type ] = $args; return true; }
function register_post_meta( $type, $key, $args = array() ) { $GLOBALS['post_meta'][ $type ][ $key ] = $args; return true; }

/** A value as the database hands it back: text, whatever went in, and an array whole. */
function db_value( $value ) {
	if ( is_array( $value ) ) { return $value; }
	if ( is_bool( $value ) ) { return $value ? '1' : ''; }
	return null === $value ? '' : (string) $value;
}

function wp_insert_post( $args, $wp_error = false ) {
	$args = wp_unslash( (array) $args );
	if ( $GLOBALS['insert_fail'] ) {
		return $wp_error ? new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ) : 0;
	}
	$post = new WP_Post();
	foreach ( $args as $key => $value ) {
		if ( property_exists( $post, $key ) ) { $post->$key = $value; }
	}
	$post->ID                      = $GLOBALS['next_id']++;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function wp_update_post( $args, $wp_error = false ) {
	$args = wp_unslash( (array) $args );
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
/** A post and its meta, gone, and whether it was forced past the trash. */
function wp_delete_post( $id, $force = false ) {
	$id = (int) $id;
	$GLOBALS['deleted'][ $id ] = (bool) $force;
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return false; }
	$post = $GLOBALS['posts'][ $id ];
	unset( $GLOBALS['posts'][ $id ], $GLOBALS['pmeta'][ $id ] );
	return $post;
}
function wp_cache_delete( $key, $group = '' ) { $GLOBALS['forgotten'][] = $group . ':' . $key; return true; }
function get_post_status( $id ) { $post = get_post( $id ); return $post instanceof WP_Post ? $post->post_status : false; }
/** A post's caches cleared, which the host's page cache purges the post's address on: each post ID asked, in order. */
function clean_post_cache( $post ) { $GLOBALS['purged'][] = $post instanceof WP_Post ? (int) $post->ID : (int) $post; }

/** Post meta as core keeps it: repeated rows under one key, and `true` answers the first. */
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) {
	// A request that dies part way through the rows it writes, where a check asks for one: the row it
	// numbers throws before it is written, as a request a host's time limit cuts off ends. Once.
	if ( isset( $GLOBALS['rows_die_at'] ) && '_wpcpm_sevent_pending' === $key && 0 === --$GLOBALS['rows_die_at'] ) {
		unset( $GLOBALS['rows_die_at'] );
		throw new RuntimeException( 'the request died writing the rows' );
	}
	$GLOBALS['added'][]                         = array( $key, $unique );
	$GLOBALS['pmeta'][ (int) $id ][ $key ][] = db_value( wp_unslash( $value ) );
	return true;
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array( db_value( wp_unslash( $value ) ) );
	return true;
}
/**
 * Rows gone, and whether any were, as core answers: false when no row matched, which is how a row
 * another request took first is not taken twice.
 */
function delete_post_meta( $id, $key, $value = '' ) {
	if ( '' === $value ) {
		$had = ! empty( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
		unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
		return $had;
	}
	$rows    = isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : array();
	$deleted = false;
	foreach ( $rows as $i => $row ) {
		// Loose, as core's is: the database compares the text.
		if ( (string) $row === (string) $value ) {
			unset( $rows[ $i ] );
			$deleted = true;
		}
	}
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array_values( $rows );
	return $deleted;
}

/**
 * Whether a post's rows satisfy a meta query: clauses joined by AND, each equal or, typed NUMERIC,
 * greater; a clause holds when any row under its key does, as the join core writes finds it. What
 * else a query asks is kept apart, and a check fails on it.
 */
function meta_query_holds( $id, array $query ) {
	foreach ( $query as $key => $clause ) {
		if ( 'relation' === $key ) {
			if ( 'AND' !== strtoupper( (string) $clause ) ) { $GLOBALS['unmodeled'][] = 'relation ' . $clause; }
			continue;
		}
		$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';
		$numeric = isset( $clause['type'] ) && 'NUMERIC' === $clause['type'];
		$hit     = false;
		// A key that exists: any row under it, whatever it holds, as the join core writes finds one.
		if ( 'EXISTS' === $compare ) {
			if ( array() === get_post_meta( $id, $clause['key'], false ) ) { return false; }
			continue;
		}
		foreach ( get_post_meta( $id, $clause['key'], false ) as $value ) {
			if ( '=' === $compare ) {
				$hit = (string) $value === (string) $clause['value'];
			} elseif ( '>' === $compare && $numeric ) {
				$hit = (int) $value > (int) $clause['value'];
			} else {
				$GLOBALS['unmodeled'][] = 'compare ' . $compare . ( $numeric ? ' NUMERIC' : '' );
			}
			if ( $hit ) { break; }
		}
		if ( ! $hit ) { return false; }
	}
	return true;
}

/**
 * The cron schedule, as core keeps single events: each its hook and the moment it is due. Asked
 * for the next of a hook, it answers that moment, or false.
 */
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
function wp_clear_scheduled_hook( $hook, $args = array(), $wp_error = false ) {
	$cleared = 0;
	foreach ( $GLOBALS['cron'] as $i => $event ) {
		if ( $hook === $event['hook'] ) {
			unset( $GLOBALS['cron'][ $i ] );
			++$cleared;
		}
	}
	$GLOBALS['cron'] = array_values( $GLOBALS['cron'] );
	return $cleared;
}

/** What wp-cron does with a due event of a hook: takes it off the schedule first, then fires the hook. */
function run_cron( $hook ) {
	foreach ( $GLOBALS['cron'] as $i => $event ) {
		if ( $hook === $event['hook'] ) { unset( $GLOBALS['cron'][ $i ] ); }
	}
	$GLOBALS['cron'] = array_values( $GLOBALS['cron'] );
	fire( $hook );
}

/** Every row still to be told, on every event, as `<user ID>:<revision>:<kind>`, or without the kind as a row was written before rows carried one. */
function all_pending() {
	$rows = array();
	foreach ( $GLOBALS['pmeta'] as $meta ) {
		if ( isset( $meta['_wpcpm_sevent_pending'] ) ) { $rows = array_merge( $rows, $meta['_wpcpm_sevent_pending'] ); }
	}
	return $rows;
}

/**
 * Enough of `get_posts()`: a type, a status or a list of them, a meta query, a limit that defaults
 * to core's five, and IDs. In core's order: by date or by ID, the newest first unless ASC is asked,
 * a post's ID standing in for its date, since the store writes its posts one after another; any
 * other order is kept apart and fails a check.
 */
function get_posts( $args = array() ) {
	$types  = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'post' );
	$status = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
	$out    = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ! in_array( $post->post_type, $types, true ) || ! in_array( $post->post_status, $status, true ) ) { continue; }
		if ( ! meta_query_holds( $post->ID, isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() ) ) { continue; }
		$out[] = $post;
	}
	$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'date';
	if ( ! in_array( $orderby, array( 'date', 'ID' ), true ) ) { $GLOBALS['unmodeled'][] = 'orderby ' . $orderby; }
	$ascending = isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] );
	usort( $out, static function ( $a, $b ) use ( $ascending ) { return $ascending ? $a->ID - $b->ID : $b->ID - $a->ID; } );
	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5;
	if ( $limit > 0 ) { $out = array_slice( $out, 0, $limit ); }
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_map( static function ( $post ) { return $post->ID; }, $out );
	}
	return $out;
}

/**
 * The options table, for the two statements the store sends: a lock's read of its own row, answered
 * as bin/stubs/options-table.php answers it, and the clean-up's option names by LIKE.
 */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	public $queries = array();
	public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$at = strpos( $query, '%s' );
			if ( false !== $at ) { $query = substr_replace( $query, "'" . str_replace( "'", "''", (string) $arg ) . "'", $at, 2 ); }
		}
		return $query;
	}
	public function get_var( $query ) {
		$this->queries[] = $query;
		return wpcpm_test_option_row( $query );
	}
	public function get_col( $query ) {
		$this->queries[] = $query;
		if ( ! preg_match( "/^SELECT option_name FROM wp_options WHERE option_name LIKE '(.*)'$/", (string) $query, $match ) ) {
			$GLOBALS['unmodeled'][] = $query;
			return array();
		}
		$regex = '/^' . like_to_regex( $match[1] ) . '$/';
		return array_values( array_filter( array_map( 'strval', array_keys( $GLOBALS['opts'] ) ), static function ( $name ) use ( $regex ) { return 1 === preg_match( $regex, $name ); } ) );
	}
}
function like_to_regex( $like ) {
	$out = '';
	for ( $i = 0, $n = strlen( $like ); $i < $n; $i++ ) {
		$c = $like[ $i ];
		if ( '\\' === $c && $i + 1 < $n ) { $out .= preg_quote( $like[ ++$i ], '/' ); continue; }
		$out .= '%' === $c ? '.*' : ( '_' === $c ? '.' : preg_quote( $c, '/' ) );
	}
	return $out;
}
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
require_once __DIR__ . '/stubs/options-table.php';

// The accounts, and the one capability of the program's own (bin/stubs/caps.php).
function get_user_by( $field, $value ) { return ( 'id' === $field && isset( $GLOBALS['users'][ (int) $value ] ) ) ? $GLOBALS['users'][ (int) $value ] : false; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ (int) $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
require_once __DIR__ . '/stubs/caps.php';

/**
 * Mail: every message handed to WordPress is kept whole, each attachment read at the moment of the
 * send, since the mail layer removes it straight after; its place among the account reads is kept
 * too, so a check can see the accounts were read before the first send.
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	// A request that dies part way through its sends, where a check asks for one: the send it
	// numbers throws, before its hand-off or just after it, as a request a host's time limit cuts
	// off ends. Once.
	$dies = isset( $GLOBALS['dies'] ) ? $GLOBALS['dies'] : null;
	if ( null !== $dies && count( $GLOBALS['all_mail'] ) + 1 === $dies['at'] && ! $dies['handed_off'] ) {
		unset( $GLOBALS['dies'] );
		throw new RuntimeException( 'the request died before the send was handed off' );
	}
	// Where the rows stood at the send: whether the person it goes to was still down as one to
	// tell, how many rows were left on every event, and whether the drain was on the clock.
	$person = 0;
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( (string) $to === $user->user_email ) { $person = (int) $id; }
	}
	$GLOBALS['send_log'][] = array(
		'to'            => (string) $to,
		'still_pending' => array() !== preg_grep( '/^' . $person . ':/', all_pending() ),
		'rows_left'     => count( all_pending() ),
		'drain'         => false !== wp_next_scheduled( 'wpcpm_sevent_drain' ),
	);
	// Another request acting in the middle of this one's sends, where a check asks for one. Once.
	if ( isset( $GLOBALS['meanwhile'] ) ) {
		$meanwhile = $GLOBALS['meanwhile'];
		unset( $GLOBALS['meanwhile'] );
		$meanwhile();
	}
	$files = array();
	foreach ( (array) $attachments as $path ) {
		$files[] = array(
			'path' => (string) $path,
			'ics'  => is_file( (string) $path ) ? str_replace( "\r\n ", '', (string) file_get_contents( (string) $path ) ) : null,
		);
	}
	// When the send happened, as the move that made it had left the store: its last audit row, and
	// the event locks still held.
	$last                  = $GLOBALS['audit'] ? $GLOBALS['audit'][ count( $GLOBALS['audit'] ) - 1 ] : array();
	wpcpm_record_mail( (string) $to, isset( $GLOBALS['mail_context'] ) ? (string) $GLOBALS['mail_context'] : '', (string) $subject, (string) $message, (array) $headers, count( $files ) );
	$GLOBALS['mail'][]     = array(
		'to'      => (string) $to,
		'subject' => (string) $subject,
		'body'    => wpcpm_footer_split( (string) $message )[0],
		'headers' => (array) $headers,
		'files'   => $files,
		'moment'  => array(
			'row'   => isset( $last['kind'], $last['subject'] ) ? array( $last['kind'], $last['subject'] ) : array(),
			'locks' => array_values( preg_grep( '/^wpcpm_sevent_lock_/', array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ),
		),
	);
	$GLOBALS['all_mail'][] = (string) $to;
	$GLOBALS['cached'][]   = 'mail ' . (string) $to;
	if ( null !== $dies && count( $GLOBALS['all_mail'] ) === $dies['at'] && $dies['handed_off'] ) {
		unset( $GLOBALS['dies'] );
		throw new RuntimeException( 'the request died just after the send was handed off' );
	}
	return true;
}
/** Core's warm-up of many accounts in one query, kept as it is asked, in order with the sends. */
function cache_users( $user_ids ) { $GLOBALS['cached'][] = 'cache ' . implode( ',', array_map( 'intval', (array) $user_ids ) ); }

/**
 * Core's user query, in the one shape the sponsor memberships send: every account holding a meta
 * value, with no limit. The value matches without regard to case, as the database's collation
 * compares it, and the memberships' own reader compares it again byte for byte. Any other shape is
 * kept apart and fails a check.
 */
function get_users( $args = array() ) {
	$args  = (array) $args;
	$shape = array_keys( $args );
	sort( $shape );
	if ( array( 'meta_key', 'meta_value', 'number' ) !== $shape || -1 !== (int) $args['number'] ) {
		$GLOBALS['unmodeled'][] = 'get_users ' . json_encode( $args );
		return array();
	}
	$found = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		$held = isset( $GLOBALS['umeta'][ $id ][ $args['meta_key'] ] ) ? (string) $GLOBALS['umeta'][ $id ][ $args['meta_key'] ] : null;
		if ( null !== $held && 0 === strcasecmp( $held, (string) $args['meta_value'] ) ) { $found[] = $user; }
	}
	return $found;
}

/** The mail layer's switch into the language of the person a mail is for, around its builder: the account switched to, kept for the checks, and the switch back. */
function switch_to_user_locale( $user_id ) { $GLOBALS['locale_for'][] = (int) $user_id; return true; }
function restore_previous_locale() { array_pop( $GLOBALS['locale_for'] ); return true; }

// The calendar file's scratch space: this run's own folder (bin/stubs/temp-dir.php).
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/mail-recording.php';
function get_temp_dir() { return wpcpm_test_temp_dir(); }
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return substr( bin2hex( random_bytes( 16 ) ), 0, (int) $length ); }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0700, true ); }
function wp_delete_file( $path ) { if ( is_file( $path ) ) { unlink( $path ); } }

/* ---- the collaborators the store reads beside the plugin's own classes ------ */

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

/** A student's status as the students sync caches it, under `program`. */
class WPCPM_Students_Sync {
	public static function get_program( $user_id ) { return isset( $GLOBALS['program'][ (int) $user_id ] ) ? $GLOBALS['program'][ (int) $user_id ] : array(); }
}

/** The sponsors store, for the calls made: the key's shape, a row, the statuses a delete walks, and Approved. */
class WPCPM_Sponsors_Index {
	const POST_STATUSES   = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );
	const STATUS_APPROVED = 'Approved';
	public static function is_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', $value ); }
	public static function rows() { return $GLOBALS['index']; }
	public static function row( $record ) { $record = trim( (string) $record ); return self::is_key( $record ) && isset( $GLOBALS['index'][ $record ] ) ? $GLOBALS['index'][ $record ] : null; }
	public static function has( $record ) { return null !== self::row( $record ); }
	public static function is_test( $sponsor ) { $row = is_array( $sponsor ) ? $sponsor : self::row( (string) $sponsor ); return is_array( $row ) && ! empty( $row['test'] ); }
}

/**
 * The sponsors module's trail: every row kept whole, and a row the real writer would refuse (no
 * kind, a ground or an evidence level it does not know, no sponsor key) kept apart as well.
 */
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';
	const EVIDENCE_CACHE = 'cache';
	const EVIDENCE_LIVE  = 'live';
	public static function record_sponsor( array $entry ) {
		$GLOBALS['audit'][] = $entry;
		if ( '' === sanitize_key( isset( $entry['kind'] ) ? $entry['kind'] : '' )
			|| ! in_array( isset( $entry['ground'] ) ? $entry['ground'] : '', array( 'manager', 'member', 'system' ), true )
			|| ! in_array( isset( $entry['evidence'] ) ? $entry['evidence'] : '', array( 'index', 'cache', 'live' ), true )
			|| ! WPCPM_Sponsors_Index::is_key( isset( $entry['sponsor'] ) ? (string) $entry['sponsor'] : '' ) ) {
			$GLOBALS['refused'][] = $entry;
			return new WP_Error( 'wpcpm_audit', 'refused' );
		}
		return count( $GLOBALS['audit'] );
	}
	/**
	 * The program's own rows, about no sponsor: kept whole on the trail and apart as well, and a row
	 * the real writer would refuse (no kind, a ground or an evidence level it does not know) kept
	 * with the refused ones.
	 */
	public static function record_program( array $entry ) {
		$GLOBALS['audit'][]         = $entry;
		$GLOBALS['program_audit'][] = $entry;
		if ( '' === sanitize_key( isset( $entry['kind'] ) ? $entry['kind'] : '' )
			|| ! in_array( isset( $entry['ground'] ) ? $entry['ground'] : '', array( 'manager', 'member', 'system' ), true )
			|| ! in_array( isset( $entry['evidence'] ) ? $entry['evidence'] : '', array( 'index', 'cache', 'live' ), true ) ) {
			$GLOBALS['refused'][] = $entry;
			return new WP_Error( 'wpcpm_audit', 'refused' );
		}
		return count( $GLOBALS['audit'] );
	}
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/class-wpcpm-ics.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
// The template registry, which every event and term email is filled from since 1.123.9.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-dashboard.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-group-sessions.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-page.php';

// A warning, a notice or a deprecation is an error here, raised inside the guard of the check that
// caused it, so the store cannot pass a check while complaining on the way; a read the suite
// silences on purpose with `@` stays silent.
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

// An end before the last check is a failure: a fatal the guards below did not catch would
// otherwise end the run with nothing said about the checks it never reached.
register_shutdown_function(
	static function () {
		if ( empty( $GLOBALS['run_complete'] ) ) {
			echo "\nFAIL the run ended before its last check\n";
			exit( 1 );
		}
	}
);

/**
 * One check. `$got` may be a closure, run here inside a guard: a store that is missing or throws
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

/** A refusal's code, or what came back instead of one. */
function code_of( $result ) { return is_wp_error( $result ) ? $result->get_error_code() : 'no refusal: ' . var_export( $result, true ); }

/** Posts as their IDs. */
function ids( $posts ) { return array_map( static function ( $p ) { return $p instanceof WP_Post ? (int) $p->ID : 'not a post'; }, array_values( (array) $posts ) ); }

/** A moment in UTC. */
function utc( $when ) { return (int) strtotime( $when . ' UTC' ); }

/** An event's ID as text, for an expectation; what a refused step left instead names no event. */
function id_text( $id ) { return is_int( $id ) ? (string) $id : 'no event'; }

function set_now( $timestamp ) { $GLOBALS['now'] = (int) $timestamp; }

/** One meta value as the store reads it back. */
function meta( $id, $key ) { return get_post_meta( (int) $id, $key, true ); }

/** The fields `plan()` and `change()` are handed, as `fields_from()` answers them. */
function event_fields( $start, array $over = array() ) {
	return array_merge(
		array(
			'start'       => (int) $start,
			'minutes'     => 60,
			'places'      => 0,
			'title'       => 'A co-hosted webinar',
			'description' => 'What the webinar covers.',
			'link'        => 'https://call.example/room-0',
			'zone'        => 'UTC',
		),
		$over
	);
}

/** The audit rows of one event, as kind, ground and actor. */
function trail_of( $id ) {
	$out = array();
	foreach ( $GLOBALS['audit'] as $row ) {
		if ( isset( $row['subject'] ) && (string) $id === $row['subject'] ) { $out[] = array( $row['kind'], $row['ground'], $row['actor'] ); }
	}
	return $out;
}

/** The last audit row. */
function last_row() { return $GLOBALS['audit'] ? end( $GLOBALS['audit'] ) : array(); }

/**
 * An event written into the store the way `plan()` writes one, for the edges of a period that one
 * sponsor could never plan through the store itself: two events a second apart overlap, and the
 * store holds a sponsor to one at a time. A check below proves the layout is the writer's own.
 */
function seed_event( $record, $state, $start, $minutes, $title, array $people = array() ) {
	$id = wp_insert_post( array( 'post_type' => 'wpcpm_sponsor_event', 'post_status' => 'private', 'post_author' => 1, 'post_title' => $title, 'post_content' => 'Seeded description Quillfeather.' ) );
	$meta = array(
		'_wpcpm_sevent_sponsor'  => $record,
		'_wpcpm_sevent_kind'     => 'co_hosted',
		'_wpcpm_sevent_start'    => $start,
		'_wpcpm_sevent_end'      => $start + $minutes * MINUTE_IN_SECONDS,
		'_wpcpm_sevent_zone'     => 'UTC',
		'_wpcpm_sevent_minutes'  => $minutes,
		'_wpcpm_sevent_places'   => 0,
		'_wpcpm_sevent_link'     => 'https://call.example/secret-room-5150',
		'_wpcpm_sevent_state'    => $state,
		'_wpcpm_sevent_revision' => 0,
	);
	foreach ( $meta as $key => $value ) { update_post_meta( $id, $key, $value ); }
	foreach ( $people as $person ) { add_post_meta( $id, '_wpcpm_sevent_person', $person ); }
	return $id;
}

/* ---- the fixture ------------------------------------------------------------ */

const MANAGER         = 1;
const PARTNER_REP     = 2;
const CHAMPION_REP    = 3;
const STUDENT         = 10;
const PAUSED          = 11;
const GRADUATE        = 12;
const NEWCOMER        = 13;
const MENTOR          = 20;
const INACTIVE_MENTOR = 21;
const MENTOR_REP      = 22;
const CONTACT         = 30;
const FORMER_CONTACT  = 31;
const WALKER          = 40;

const PARTNER  = 'spoAAAA0000000001'; // Partner, the term runs to 31 January 2027.
const CHAMPION = 'spoAAAA0000000002'; // Champion, no term.
const BACKER   = 'spoAAAA0000000003'; // Backer: no events.
const LAPSED   = 'spoAAAA0000000004'; // Partner whose term ended on 30 June 2026.
const HELD     = 'spoAAAA0000000005'; // Champion, no term: the edges of a period.
const STALE    = 'spoAAAA0000000006'; // Champion, the term runs to 31 March 2027: an event never approved.
const LAPSING  = 'spoAAAA0000000007'; // Partner, the term runs to 31 January 2027: an event across its last midnight.
const RENEWED  = 'spoAAAA0000000008'; // Partner, the term runs to 31 January 2027, renewed before it ends.
const STANDING = 'spoAAAA0000000009'; // Champion, the term runs to 30 June 2027: an approval that waits on its standing.

function a_user( $id, $name, array $roles, array $meta = array() ) {
	$user                    = new WP_User( $id, $name );
	$user->roles             = $roles;
	$user->user_email        = strtolower( str_replace( ' ', '.', $name ) ) . '@people.example';
	$GLOBALS['users'][ $id ] = $user;
	$GLOBALS['umeta'][ $id ] = $meta;
	return $user;
}

a_user( MANAGER, 'Mira Manager', array( 'administrator' ) );
a_user( PARTNER_REP, 'Pat Partnerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1 ) );
a_user( CHAMPION_REP, 'Chris Championrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => CHAMPION, 'wpcpm_sponsor_active' => 1 ) );
// Two of the people who join keep a clock of their own: a mail about an event reads it on theirs.
a_user( STUDENT, 'Sam Current', array( 'wpcpm_student' ), array( 'wpcpm_timezone' => 'America/New_York' ) );
a_user( PAUSED, 'Paula Paused', array( 'wpcpm_student' ) );
a_user( GRADUATE, 'Gina Graduate', array( 'wpcpm_student' ) );
a_user( NEWCOMER, 'Nora Newcomer', array( 'wpcpm_student' ) );
a_user( MENTOR, 'Mona Mentor', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recSEED0000000201', 'wpcpm_mentor_active' => 1 ) );
// Gone inactive: the role taken away, the flag at 0, the Airtable link left in place.
a_user( INACTIVE_MENTOR, 'Ian Inactive', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recSEED0000000202', 'wpcpm_mentor_active' => '0' ) );
// A mentor attached to the Partner, which `WPCPM_Sponsor_Members::attach()` allows on purpose.
a_user( MENTOR_REP, 'Mel Mentorrep', array( 'wpcpm_mentor', 'wpcpm_sponsor' ), array( 'wpcpm_mentor_record_id' => 'recSEED0000000203', 'wpcpm_mentor_active' => 1, 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1 ) );
a_user( CONTACT, 'Ines Institution', array( 'wpcpm_institution' ), array( 'wpcpm_institution_record_id' => 'recSEED0000000301', 'wpcpm_institution_active' => 1, 'wpcpm_timezone' => 'Asia/Tokyo' ) );
a_user( FORMER_CONTACT, 'Fred Formercontact', array( 'wpcpm_institution' ), array( 'wpcpm_institution_record_id_was' => 'recSEED0000000301', 'wpcpm_institution_active' => 0 ) );
a_user( WALKER, 'Zelda Privacywalk', array( 'wpcpm_student' ) );

$GLOBALS['manage']  = array( MANAGER );
$GLOBALS['program'] = array(
	STUDENT  => array( 'program' => 'In Sensei' ),
	PAUSED   => array( 'program' => 'Paused' ),
	GRADUATE => array( 'program' => 'Graduate' ),
	WALKER   => array( 'program' => 'Developer Track' ),
);

$GLOBALS['settings'] = array(
	'sponsor_tiers_live' => true,
	'student_statuses'   => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' ),
	'past_statuses'      => array( 'Graduate', 'Dropped out' ),
	'sponsor_notify'     => 'events@program.example, second@program.example',
);

$GLOBALS['index'] = array(
	PARTNER  => array( 'record_id' => PARTNER, 'name' => 'Partner Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31' ),
	CHAMPION => array( 'record_id' => CHAMPION, 'name' => 'Champion Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' ),
	BACKER   => array( 'record_id' => BACKER, 'name' => 'Backer Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2027-06-30' ),
	LAPSED   => array( 'record_id' => LAPSED, 'name' => 'Lapsed Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2026-06-30' ),
	HELD     => array( 'record_id' => HELD, 'name' => 'Held Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' ),
	STALE    => array( 'record_id' => STALE, 'name' => 'Stale Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-03-31' ),
	LAPSING  => array( 'record_id' => LAPSING, 'name' => 'Lapsing Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31' ),
	RENEWED  => array( 'record_id' => RENEWED, 'name' => 'Renewed Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31' ),
	STANDING => array( 'record_id' => STANDING, 'name' => 'Standing Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-06-30' ),
);

// A post of the site's own and a sponsor report: what the store's delete must leave alone.
$page   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'About' ) );

// The Partners page, as `WPCPM_Sponsors_Page` records it, under an ID of its own so that no
// event's ID moves: the page whose cache a move purges.
const SPONSORS_PAGE                       = 5000;
$GLOBALS['posts'][ SPONSORS_PAGE ]        = new WP_Post();
$GLOBALS['posts'][ SPONSORS_PAGE ]->ID    = SPONSORS_PAGE;
$GLOBALS['posts'][ SPONSORS_PAGE ]->post_type   = 'page';
$GLOBALS['posts'][ SPONSORS_PAGE ]->post_status = 'publish';
$GLOBALS['posts'][ SPONSORS_PAGE ]->post_name   = 'sponsors';
$GLOBALS['opts']['wpcpm_sponsors_page_id']     = SPONSORS_PAGE;
$report = wp_insert_post( array( 'post_type' => 'wpcpm_sponsor_report', 'post_status' => 'private', 'post_title' => 'A report' ) );

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

/* ---- the names -------------------------------------------------------------- */

echo "=== The names ===\n";

ck(
	'the post type is wpcpm_sponsor_event, nineteen characters, inside the twenty register_post_type() accepts',
	static function () { return array( WPCPM_Sponsor_Event::POST_TYPE, strlen( WPCPM_Sponsor_Event::POST_TYPE ) ); },
	array( 'wpcpm_sponsor_event', 19 )
);
ck(
	'the thirteen meta keys',
	static function () {
		return array( WPCPM_Sponsor_Event::META_SPONSOR, WPCPM_Sponsor_Event::META_START, WPCPM_Sponsor_Event::META_END, WPCPM_Sponsor_Event::META_ZONE, WPCPM_Sponsor_Event::META_MINUTES, WPCPM_Sponsor_Event::META_PLACES, WPCPM_Sponsor_Event::META_KIND, WPCPM_Sponsor_Event::META_LINK, WPCPM_Sponsor_Event::META_STATE, WPCPM_Sponsor_Event::META_DECISION, WPCPM_Sponsor_Event::META_REVISION, WPCPM_Sponsor_Event::META_PERSON, WPCPM_Sponsor_Event::META_PENDING );
	},
	array( '_wpcpm_sevent_sponsor', '_wpcpm_sevent_start', '_wpcpm_sevent_end', '_wpcpm_sevent_zone', '_wpcpm_sevent_minutes', '_wpcpm_sevent_places', '_wpcpm_sevent_kind', '_wpcpm_sevent_link', '_wpcpm_sevent_state', '_wpcpm_sevent_decision', '_wpcpm_sevent_revision', '_wpcpm_sevent_person', '_wpcpm_sevent_pending' )
);
ck(
	'fifty told in the press, the rest by one cron event that tells fifty a run, a minute apart',
	static function () { return array( WPCPM_Sponsor_Event::TELL_IN_REQUEST, WPCPM_Sponsor_Event::CRON_DRAIN, WPCPM_Sponsor_Event::DRAIN_GAP ); },
	array( 50, 'wpcpm_sevent_drain', 60 )
);
ck(
	'the three states, the one kind, the limits, the lock and the audiences',
	static function () {
		return array( WPCPM_Sponsor_Event::STATE_PLANNED, WPCPM_Sponsor_Event::STATE_APPROVED, WPCPM_Sponsor_Event::STATE_CANCELLED, WPCPM_Sponsor_Event::KIND_CO_HOSTED, WPCPM_Sponsor_Event::MIN_MINUTES, WPCPM_Sponsor_Event::MAX_MINUTES, WPCPM_Sponsor_Event::MAX_PLACES, WPCPM_Sponsor_Event::MAX_TITLE, WPCPM_Sponsor_Event::MAX_DESCRIPTION, WPCPM_Sponsor_Event::MAX_LINK, WPCPM_Sponsor_Event::LOCK_PREFIX, WPCPM_Sponsor_Event::LOCK_TIMEOUT, WPCPM_Sponsor_Event::AUDIENCE_STUDENTS, WPCPM_Sponsor_Event::AUDIENCE_MENTORS, WPCPM_Sponsor_Event::AUDIENCE_MANAGERS, WPCPM_Sponsor_Event::AUDIENCE_INSTITUTIONS );
	},
	array( 'planned', 'approved', 'cancelled', 'co_hosted', 15, 480, 500, 120, 2000, 500, 'wpcpm_sevent_lock_', 120, 'students', 'mentors', 'managers', 'institutions' )
);
ck(
	'the five kinds of row on the sponsors module\'s audit trail',
	static function () {
		return array( WPCPM_Sponsor_Event::LOG_PLANNED, WPCPM_Sponsor_Event::LOG_APPROVED, WPCPM_Sponsor_Event::LOG_RETURNED, WPCPM_Sponsor_Event::LOG_CHANGED, WPCPM_Sponsor_Event::LOG_CANCELLED );
	},
	array( 'sponsor_event_planned', 'sponsor_event_approved', 'sponsor_event_returned', 'sponsor_event_changed', 'sponsor_event_cancelled' )
);
ck(
	'the reminders\' names: the mark on the event, the three kinds of row still to tell, and the window, twelve to thirty-six hours after the run',
	static function () {
		return array( WPCPM_Sponsor_Event::META_REMINDED, WPCPM_Sponsor_Event::ROW_CHANGE, WPCPM_Sponsor_Event::ROW_CANCEL, WPCPM_Sponsor_Event::ROW_REMINDER, WPCPM_Sponsor_Event::REMIND_FROM, WPCPM_Sponsor_Event::REMIND_UNTIL );
	},
	array( '_wpcpm_sevent_reminded', 'change', 'cancel', 'reminder', 12, 36 )
);
ck(
	'the late run\'s and the retry\'s names: the option that keeps where the last complete window stopped, the retry\'s cron event and its five minutes',
	static function () { return array( WPCPM_Sponsor_Event::OPT_REMINDER_EDGE, WPCPM_Sponsor_Event::CRON_RETRY, WPCPM_Sponsor_Event::RETRY_GAP ); },
	array( 'wpcpm_sevent_reminder_edge', 'wpcpm_sevent_remind_retry', 300 )
);

attempt( static function () { WPCPM_Sponsor_Event::init(); } );

ck(
	'init() hooks the post type and then its meta on init at 5, the drain on its cron event and the reminders\' retry on its own, and nothing else',
	static function () {
		return array( array_keys( $GLOBALS['hooks'] ), array_map( static function ( $h ) { return array( $h['callback'], $h['priority'] ); }, isset( $GLOBALS['hooks']['init'] ) ? $GLOBALS['hooks']['init'] : array() ), isset( $GLOBALS['hooks']['wpcpm_sevent_drain'] ) ? $GLOBALS['hooks']['wpcpm_sevent_drain'] : array(), isset( $GLOBALS['hooks']['wpcpm_sevent_remind_retry'] ) ? $GLOBALS['hooks']['wpcpm_sevent_remind_retry'] : array() );
	},
	array( array( 'init', 'wpcpm_sevent_drain', 'wpcpm_sevent_remind_retry' ), array( array( array( 'WPCPM_Sponsor_Event', 'register_post_type' ), 5 ), array( array( 'WPCPM_Sponsor_Event', 'register_meta' ), 5 ) ), array( array( 'callback' => array( 'WPCPM_Sponsor_Event', 'drain' ), 'priority' => 10 ) ), array( array( 'callback' => array( 'WPCPM_Sponsor_Event', 'queue_reminders' ), 'priority' => 10 ) ) )
);

fire( 'init' );

ck(
	'private everywhere: not public, not queryable, not searched, no screen, no menu, no REST, no archive, no rewrite, no query var',
	static function () {
		$type = $GLOBALS['post_types']['wpcpm_sponsor_event'];
		return array( $type['public'], $type['publicly_queryable'], $type['exclude_from_search'], $type['show_ui'], $type['show_in_menu'], $type['show_in_rest'], $type['has_archive'], $type['rewrite'], $type['query_var'], $type['map_meta_cap'] );
	},
	array( false, false, true, false, false, false, false, false, false, true )
);
ck(
	'a title and an editor, the description being the post\'s content, under a capability type nothing is granted',
	static function () {
		$type = $GLOBALS['post_types']['wpcpm_sponsor_event'];
		return array( $type['supports'], $type['capability_type'] );
	},
	array( array( 'title', 'editor' ), array( 'wpcpm_sponsor_event', 'wpcpm_sponsor_events' ) )
);

$registered = static function ( $type, $single ) {
	return array(
		'type'          => $type,
		'single'        => $single,
		'show_in_rest'  => false,
		'auth_callback' => '__return_false',
	);
};

ck(
	'every key is registered out of REST and out of the meta API\'s reach, single but for the one row per person and the one per person still to tell',
	static function () use ( $registered ) {
		return isset( $GLOBALS['post_meta']['wpcpm_sponsor_event'] ) ? $GLOBALS['post_meta']['wpcpm_sponsor_event'] : array();
	},
	array(
		'_wpcpm_sevent_sponsor'  => $registered( 'string', true ),
		'_wpcpm_sevent_start'    => $registered( 'integer', true ),
		'_wpcpm_sevent_end'      => $registered( 'integer', true ),
		'_wpcpm_sevent_zone'     => $registered( 'string', true ),
		'_wpcpm_sevent_minutes'  => $registered( 'integer', true ),
		'_wpcpm_sevent_places'   => $registered( 'integer', true ),
		'_wpcpm_sevent_kind'     => $registered( 'string', true ),
		'_wpcpm_sevent_link'     => $registered( 'string', true ),
		'_wpcpm_sevent_state'    => $registered( 'string', true ),
		'_wpcpm_sevent_decision' => $registered( 'array', true ),
		'_wpcpm_sevent_revision' => $registered( 'integer', true ),
		'_wpcpm_sevent_person'   => $registered( 'integer', false ),
		'_wpcpm_sevent_pending'  => $registered( 'string', false ),
		'_wpcpm_sevent_reminded' => $registered( 'integer', true ),
	)
);

echo "\n=== The wiring ===\n";

$loader_src    = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$uninstall_src = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
$sponsors_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$event_src     = (string) @file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php' );

ck(
	'the loader requires the store right after the sponsor report card',
	false !== strpos( $loader_src, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-report-card.php';\nrequire_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-event.php';\n" ),
	true
);
ck(
	'and so does uninstall.php, so the Sponsors module\'s clean-up can reach it',
	false !== strpos( $uninstall_src, "require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sponsor-report-card.php';\nrequire_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sponsor-event.php';\n" ),
	true
);

$booted = array();
if ( 1 === preg_match( "/foreach \( array\(([^)]*)\) as \\\$front \)/", $sponsors_src, $boot_list ) ) {
	$booted = array_map( static function ( $name ) { return trim( $name, " '\t\n" ); }, explode( ',', $boot_list[1] ) );
}

ck( 'the Sponsors module boots the store with its other classes, behind the same class_exists() guard', array( count( $booted ) > 10, in_array( 'WPCPM_Sponsor_Event', $booted, true ) ), array( true, true ) );

echo "\n=== The policy's action ===\n";

ck( 'ACT_PLAN_EVENT is plan_event', static function () { return WPCPM_Sponsor_Policy::ACT_PLAN_EVENT; }, 'plan_event' );
ck(
	'the manager ground first, then the member ground, and no gate',
	static function () {
		$grounds = WPCPM_Sponsor_Policy::grounds();
		return array( $grounds['plan_event'], in_array( 'plan_event', WPCPM_Sponsor_Policy::ungated(), true ) );
	},
	array( array( 'manager', 'member' ), true )
);
ck(
	'a member of the Partner plans its event as a member, a manager as a manager, the Champion\'s member not at all',
	static function () {
		$subject = WPCPM_Sponsor_Policy::subject_sponsor( PARTNER );
		return array( WPCPM_Sponsor_Policy::decide( 'plan_event', $subject, PARTNER_REP )['ground'], WPCPM_Sponsor_Policy::decide( 'plan_event', $subject, MANAGER )['ground'], WPCPM_Sponsor_Policy::decide( 'plan_event', $subject, CHAMPION_REP )['allowed'] );
	},
	array( 'member', 'manager', false )
);

/* ---- fields_from() ------------------------------------------------------------ */

echo "\n=== fields_from() ===\n";

$new_york = new DateTimeZone( 'America/New_York' );
$good     = array(
	'date'        => '2026-11-10',
	'time'        => '14:00',
	'minutes'     => '60',
	'places'      => '',
	'title'       => '  Accessible themes  ',
	'description' => "Line one\nLine two",
	'link'        => 'https://call.example/j/123?pwd=a%2Fb',
);

ck(
	'a form read on the planner\'s clock: 14:00 in New York on 10 November is 19:00 UTC, blank places mean no limit, the link keeps its percent-encoded query',
	static function () use ( $good, $new_york ) { return WPCPM_Sponsor_Event::fields_from( $good, $new_york ); },
	array(
		'fields' => array(
			'start'       => utc( '2026-11-10 19:00' ),
			'minutes'     => 60,
			'places'      => 0,
			'title'       => 'Accessible themes',
			'description' => "Line one\nLine two",
			'link'        => 'https://call.example/j/123?pwd=a%2Fb',
			'zone'        => 'America/New_York',
		),
		'errors' => array(),
	)
);
ck(
	'twenty-five places are twenty-five, and a 0 is no limit, as a blank is',
	static function () use ( $good, $new_york ) {
		return array(
			WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'places' => '25' ) ), $new_york )['fields']['places'],
			WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'places' => '0' ) ), $new_york )['fields']['places'],
		);
	},
	array( 25, 0 )
);

$bad = array(
	'date'        => '2026-02-30',
	'time'        => '25:00',
	'minutes'     => 'abc',
	'places'      => 'x',
	'title'       => '',
	'description' => '',
	'link'        => 'ftp://files.example/room',
);

ck(
	'every field refused is named, day and time first, each with a sentence',
	static function () use ( $bad, $new_york ) {
		$read = WPCPM_Sponsor_Event::fields_from( $bad, $new_york );
		return array( array_keys( $read['errors'] ), count( array_filter( $read['errors'], static function ( $s ) { return is_string( $s ) && strlen( $s ) > 10; } ) ) );
	},
	array( array( 'date', 'time', 'minutes', 'places', 'title', 'description', 'link' ), 7 )
);
ck(
	'an hour the clocks skip is refused on the time: 02:30 on 14 March 2027 does not exist in New York',
	static function () use ( $good, $new_york ) { return array_keys( WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'date' => '2027-03-14', 'time' => '02:30' ) ), $new_york )['errors'] ); },
	array( 'time' )
);

/**
 * The fields a form with one value changed is refused on.
 *
 * @param array  $good  The good form.
 * @param string $field The field changed.
 * @param mixed  $value Its new value.
 * @return string[]
 */
function refused_on( array $good, $field, $value ) {
	return array_keys( WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( $field => $value ) ), new DateTimeZone( 'UTC' ) )['errors'] );
}

ck(
	'the length runs from 15 to 480 minutes, both ends included',
	static function () use ( $good ) { return array( refused_on( $good, 'minutes', '14' ), refused_on( $good, 'minutes', '15' ), refused_on( $good, 'minutes', '480' ), refused_on( $good, 'minutes', '481' ), refused_on( $good, 'minutes', '' ) ); },
	array( array( 'minutes' ), array(), array(), array( 'minutes' ), array( 'minutes' ) )
);
ck(
	'the places run to 500, and a negative or a word is refused',
	static function () use ( $good ) { return array( refused_on( $good, 'places', '500' ), refused_on( $good, 'places', '501' ), refused_on( $good, 'places', '-3' ), refused_on( $good, 'places', 'ten' ) ); },
	array( array(), array( 'places' ), array( 'places' ), array( 'places' ) )
);
ck(
	'a title up to 120 characters and a description up to 2,000, both required',
	static function () use ( $good ) {
		return array( refused_on( $good, 'title', str_repeat( 't', 120 ) ), refused_on( $good, 'title', str_repeat( 't', 121 ) ), refused_on( $good, 'description', str_repeat( 'd', 2000 ) ), refused_on( $good, 'description', str_repeat( 'd', 2001 ) ), refused_on( $good, 'title', '   ' ), refused_on( $good, 'description', '' ) );
	},
	array( array(), array( 'title' ), array(), array( 'description' ), array( 'title' ), array( 'description' ) )
);

// The limits count what a person typed, as the box counted it. Core's cleaner writes "&lt;" for a "<"
// that opens no tag and escapes the quote marks and the ampersand after it ("Why 2 < 3, and "Q&A""
// is 20 characters in the box and 37 once cleaned), and a text area posts each line break as two
// bytes where the box counted one. Only the measuring changes: the value is stored as cleaned.
$typed_title   = 'Why 2 < 3, and "Q&A"' . str_repeat( 't', 100 );
$cleaned_title = 'Why 2 &lt; 3, and &quot;Q&amp;A&quot;' . str_repeat( 't', 100 );
$ten_lines     = implode( "\r\n", array_fill( 0, 10, str_repeat( 'x', 199 ) ) ) . 'x';
$typed_story   = 'Is 2 < 3? "Yes" & more.' . str_repeat( 'd', 1977 );
$cleaned_story = 'Is 2 &lt; 3? &quot;Yes&quot; &amp; more.' . str_repeat( 'd', 1977 );

ck(
	'a title of 120 characters as typed, holding a < and quote marks and an ampersand, is accepted though the cleaner made it 137, and is stored as cleaned; one more character is refused',
	static function () use ( $good, $typed_title, $cleaned_title ) {
		$zone = new DateTimeZone( 'UTC' );
		$read = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'title' => $typed_title ) ), $zone );
		$over = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'title' => $typed_title . 't' ) ), $zone );
		return array( mb_strlen( $typed_title ), mb_strlen( $read['fields']['title'] ), array_keys( $read['errors'] ), $read['fields']['title'] === $cleaned_title, array_keys( $over['errors'] ) );
	},
	array( 120, 137, array(), true, array( 'title' ) )
);
ck(
	'a description of 2,000 characters as typed over ten lines, each line break posted as two bytes, is accepted and stored with its line breaks as posted; one more character is refused',
	static function () use ( $good, $ten_lines ) {
		$zone = new DateTimeZone( 'UTC' );
		$read = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'description' => $ten_lines ) ), $zone );
		$over = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'description' => $ten_lines . 'x' ) ), $zone );
		return array( strlen( $ten_lines ), count( explode( "\n", $ten_lines ) ), array_keys( $read['errors'] ), $read['fields']['description'] === $ten_lines, array_keys( $over['errors'] ) );
	},
	array( 2009, 10, array(), true, array( 'description' ) )
);
ck(
	'a description of 2,000 characters as typed that holds a < , quote marks and an ampersand is accepted though the cleaner made it longer, and is stored as cleaned; one more character is refused',
	static function () use ( $good, $typed_story, $cleaned_story ) {
		$zone = new DateTimeZone( 'UTC' );
		$read = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'description' => $typed_story ) ), $zone );
		$over = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'description' => $typed_story . 'd' ) ), $zone );
		return array( mb_strlen( $typed_story ), mb_strlen( $read['fields']['description'] ) > 2000, array_keys( $read['errors'] ), $read['fields']['description'] === $cleaned_story, array_keys( $over['errors'] ) );
	},
	array( 2000, true, array(), true, array( 'description' ) )
);
ck(
	'a form refused on its length still hands back the title and the description whole, as the cleaner left them: only the length is named',
	static function () use ( $good, $typed_title, $cleaned_title, $ten_lines ) {
		$read = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'minutes' => '5', 'title' => $typed_title, 'description' => $ten_lines ) ), new DateTimeZone( 'UTC' ) );
		return array( array_keys( $read['errors'] ), $read['fields']['title'] === $cleaned_title, $read['fields']['description'] === $ten_lines );
	},
	array( array( 'minutes' ), true, true )
);
ck(
	'the call link keeps its own count: it is read as typed, so a link of 501 characters holding an entity is refused, and one of 500 is accepted',
	static function () use ( $good ) {
		$zone = new DateTimeZone( 'UTC' );
		$link = 'https://call.example/q?a=1&amp;b=2' . str_repeat( 'l', 466 );
		return array( strlen( $link ), array_keys( WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'link' => $link ) ), $zone )['errors'] ), array_keys( WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'link' => $link . 'l' ) ), $zone )['errors'] ) );
	},
	array( 500, array(), array( 'link' ) )
);
ck(
	'typed_length() counts what the box counted: an entity as the one character it stands for, once and no deeper, a line break once however it is posted, and a letter with accents once',
	static function () {
		return array(
			WPCPM_Sponsor_Event::typed_length( 'Why 2 &lt; 3, and &quot;Q&amp;A&quot;' ),
			WPCPM_Sponsor_Event::typed_length( 'it&#039;s' ),
			WPCPM_Sponsor_Event::typed_length( '&amp;lt;' ),
			WPCPM_Sponsor_Event::typed_length( "one\r\ntwo\nthree\rfour" ),
			WPCPM_Sponsor_Event::typed_length( "\xC5\xBB\xC3\xB3\xC5\x82\xC4\x87" ),
			WPCPM_Sponsor_Event::typed_length( '' ),
		);
	},
	array( 20, 4, 4, 18, 4, 0 )
);
ck(
	'and counts what kses wrote for a person without unfiltered_html the same way, so a member\'s stored words count as they were typed: &amp; for a bare ampersand and &gt; for a bare > are one character each, and a description of 1,999 characters made of "Q&A " counts 1,999 stored with every ampersand written out',
	static function () {
		$stored = str_repeat( 'Q&amp;A ', 499 ) . 'Q&amp;A';
		return array(
			WPCPM_Sponsor_Event::typed_length( 'Q&amp;A with our team' ) === mb_strlen( 'Q&A with our team' ),
			WPCPM_Sponsor_Event::typed_length( 'Ages &gt; 5 welcome' ) === mb_strlen( 'Ages > 5 welcome' ),
			WPCPM_Sponsor_Event::typed_length( 'Q&amp;A first: is 2 &lt; 3? &quot;Yes&quot;' ) === mb_strlen( 'Q&A first: is 2 < 3? "Yes"' ),
			mb_strlen( $stored ),
			WPCPM_Sponsor_Event::typed_length( $stored ),
		);
	},
	array( true, true, true, 3999, 1999 )
);
ck(
	'the link is required, http or https, no account in it, a port core allows, at most 500 characters',
	static function () use ( $good ) {
		$at_most = 'https://call.example/' . str_repeat( 'a', 479 );
		return array( refused_on( $good, 'link', '' ), refused_on( $good, 'link', 'javascript:alert(1)' ), refused_on( $good, 'link', 'https://user:secret@call.example/room' ), refused_on( $good, 'link', 'https://call.example:8443/room' ), refused_on( $good, 'link', 'http://call.example/room' ), strlen( $at_most ), refused_on( $good, 'link', $at_most ), refused_on( $good, 'link', $at_most . 'a' ) );
	},
	array( array( 'link' ), array( 'link' ), array( 'link' ), array( 'link' ), array(), 500, array(), array( 'link' ) )
);
ck(
	'a value posted as a list is no value at all',
	static function () use ( $good ) { return refused_on( $good, 'title', array( 'Accessible themes' ) ); },
	array( 'title' )
);

$store_state = static function () {
	return md5( serialize( array( $GLOBALS['posts'], $GLOBALS['pmeta'], $GLOBALS['opts'], $GLOBALS['audit'], $GLOBALS['mail'] ) ) );
};

ck(
	'fields_from() writes nothing: no post, no meta, no option, no audit row, no mail',
	static function () use ( $good, $new_york, $store_state ) {
		$before = $store_state();
		$read   = WPCPM_Sponsor_Event::fields_from( $good, $new_york );
		return array( isset( $read['fields'], $read['errors'] ), $store_state() === $before );
	},
	array( true, true )
);

/* ---- may_plan() --------------------------------------------------------------- */

echo "\n=== may_plan() ===\n";

$row = static function ( $record ) { return $GLOBALS['index'][ $record ]; };

$GLOBALS['settings']['sponsor_tiers_live'] = false;
ck( 'with the switch off nobody plans: switch_off', static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2026-11-10 14:00' ) ); }, 'switch_off' );
$GLOBALS['settings']['sponsor_tiers_live'] = true;

ck(
	'a Backer, a Partner whose term has lapsed, and a key the store does not hold have no event in their tier: no_tier',
	static function () use ( $row ) {
		return array( WPCPM_Sponsor_Event::may_plan( BACKER, $row( BACKER ), utc( '2026-11-10 14:00' ) ), WPCPM_Sponsor_Event::may_plan( LAPSED, $row( LAPSED ), utc( '2026-11-10 14:00' ) ), WPCPM_Sponsor_Event::may_plan( 'spoAAAA0000000099', array(), utc( '2026-11-10 14:00' ) ), WPCPM_Sponsor_Event::may_plan( 'not a key', $row( PARTNER ), utc( '2026-11-10 14:00' ) ) );
	},
	array( 'no_tier', 'no_tier', 'no_tier', 'no_tier' )
);

// A sponsor the Sponsors screen sets to Paused keeps its tier and its term on its row, and the
// planner is a tier's benefit, which only an Approved sponsor holds. plan() is asked too, since it
// reads the row from the store itself; whatever it wrote is removed again at once, so a store that
// lets it through fails this check alone.
$GLOBALS['index'][ PARTNER ]['status'] = 'Paused';
ck(
	'a Partner set to Paused may not plan: no_tier, and plan() refuses it and writes nothing',
	static function () use ( $row ) {
		$before  = count( $GLOBALS['posts'] );
		$planned = WPCPM_Sponsor_Event::plan( PARTNER, event_fields( utc( '2026-11-10 14:00' ) ), PARTNER_REP, 'member' );
		$wrote   = count( $GLOBALS['posts'] ) - $before;
		if ( is_int( $planned ) ) { wp_delete_post( $planned, true ); }
		return array( WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2026-11-10 14:00' ) ), WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), 0 ), code_of( $planned ), $wrote );
	},
	array( 'no_tier', 'no_tier', 'no_tier', 0 )
);
$GLOBALS['index'][ PARTNER ]['status'] = 'Approved';
ck( 'Approved again, the same Partner may', static function () use ( $row ) { return array( WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2026-11-10 14:00' ) ), WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), 0 ) ); }, array( '', '' ) );

ck(
	'the Partner, with nothing planned: the term\'s last day is allowed, the day after it is past_term',
	static function () use ( $row ) { return array( WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2027-01-31 15:00' ) ), WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2027-02-01 15:00' ) ) ); },
	array( '', 'past_term' )
);
ck(
	'a start that has passed, or is this very moment, is in_past; a start of 0 asks only the sponsor\'s questions',
	static function () use ( $row ) { return array( WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2026-10-06 11:00' ) ), WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), $GLOBALS['now'] ), WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), 0 ) ); },
	array( 'in_past', 'in_past', '' )
);

$GLOBALS['site_tz'] = 'Europe/Warsaw';
ck(
	'the day is the site\'s: 23:30 UTC on 31 January is 1 February in Warsaw, past_term, and 22:30 UTC is still the last day',
	static function () use ( $row ) { return array( WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2027-01-31 23:30' ) ), WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2027-01-31 22:30' ) ) ); },
	array( 'past_term', '' )
);
ck(
	'and plan() refuses that start in words that name the term\'s last day and the clock its days are read on, the site\'s, writing nothing',
	static function () {
		$before  = count( $GLOBALS['posts'] );
		$refused = WPCPM_Sponsor_Event::plan( PARTNER, event_fields( utc( '2027-01-31 23:30' ) ), PARTNER_REP, 'member' );
		$wrote   = count( $GLOBALS['posts'] ) - $before;
		return array( code_of( $refused ), is_wp_error( $refused ) ? $refused->get_error_message() : '', $wrote );
	},
	array( 'past_term', 'Choose a start on or before January 31, 2027, the last day of your sponsorship term on the site\'s clock (Europe / Warsaw).', 0 )
);
$GLOBALS['site_tz'] = 'UTC';

ck(
	'today can be given: on 5 February 2027 the Partner\'s term has lapsed and its tier is no longer one with events',
	static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2027-03-01 15:00' ), '2027-02-05' ); },
	'no_tier'
);
ck(
	'a Champion with no term plans as far ahead as it likes',
	static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( CHAMPION, $row( CHAMPION ), utc( '2028-10-01 10:00' ) ); },
	''
);

/* ---- plan(), and a cancel that frees the allowance ------------------------------ */

echo "\n=== plan() ===\n";

$p0 = attempt( static function () { return WPCPM_Sponsor_Event::plan( PARTNER, event_fields( utc( '2026-11-03 15:00' ) ), PARTNER_REP, 'member' ); } );

ck( 'a member of the Partner plans an event: its post ID', is_int( $p0 ) && $p0 > 0, true );
ck( 'and the Partner has one upcoming now: one_open', static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2026-12-01 15:00' ) ); }, 'one_open' );
ck( 'the member cancels it before it takes place: nobody is on it, so nobody is told and nobody is left to tell, nothing waits for the drain, and nobody is mailed', static function () use ( $p0 ) { $before = count( $GLOBALS['mail'] ); return array( WPCPM_Sponsor_Event::cancel( $p0, PARTNER_REP ), meta( $p0, '_wpcpm_sevent_state' ), count( $GLOBALS['mail'] ) - $before, get_post_meta( $p0, '_wpcpm_sevent_pending', false ), $GLOBALS['cron'] ); }, array( array( 'told' => 0, 'pending' => 0 ), 'cancelled', 0, array(), array() ) );
ck( 'and a canceled event spends nothing: the Partner may plan again', static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2026-11-10 14:00' ) ); }, '' );

$p1_start = utc( '2026-11-10 14:00' );
$p1       = attempt(
	static function () use ( $p1_start ) {
		return WPCPM_Sponsor_Event::plan(
			PARTNER,
			event_fields(
				$p1_start,
				array(
					'places'      => 2,
					'title'       => 'Accessible themes, start to finish',
					'description' => 'How the theme team checks contrast. Bring a C:\\themes folder.',
					'link'        => 'https://call.example/room-1',
				)
			),
			PARTNER_REP,
			'member'
		);
	}
);
$planned_keys = is_int( $p1 ) && isset( $GLOBALS['pmeta'][ $p1 ] ) ? array_keys( $GLOBALS['pmeta'][ $p1 ] ) : array();

ck(
	'the event is a private post of the type, written by the member, the title its title and the description its content, a backslash kept',
	static function () use ( $p1 ) {
		$post = get_post( $p1 );
		return array( $post->post_type, $post->post_status, (int) $post->post_author, $post->post_title, $post->post_content );
	},
	array( 'wpcpm_sponsor_event', 'private', PARTNER_REP, 'Accessible themes, start to finish', 'How the theme team checks contrast. Bring a C:\\themes folder.' )
);
ck(
	'its meta: the sponsor, the start and the end in UTC, the zone, the minutes, the places, the kind, the link, planned, version 0, nobody on it, no decision',
	static function () use ( $p1, $p1_start ) {
		return array( meta( $p1, '_wpcpm_sevent_sponsor' ), meta( $p1, '_wpcpm_sevent_start' ), meta( $p1, '_wpcpm_sevent_end' ), meta( $p1, '_wpcpm_sevent_zone' ), meta( $p1, '_wpcpm_sevent_minutes' ), meta( $p1, '_wpcpm_sevent_places' ), meta( $p1, '_wpcpm_sevent_kind' ), meta( $p1, '_wpcpm_sevent_link' ), meta( $p1, '_wpcpm_sevent_state' ), meta( $p1, '_wpcpm_sevent_revision' ), get_post_meta( $p1, '_wpcpm_sevent_person', false ), meta( $p1, '_wpcpm_sevent_decision' ) );
	},
	array( PARTNER, (string) $p1_start, (string) ( $p1_start + HOUR_IN_SECONDS ), 'UTC', '60', '2', 'co_hosted', 'https://call.example/room-1', 'planned', '0', array(), '' )
);
ck(
	'one audit row: planned, on the member ground, the start, minutes, places and people in its facts, the day in its words',
	static function () use ( $p1, $p1_start ) { return last_row(); },
	array(
		'kind'     => 'sponsor_event_planned',
		'sponsor'  => PARTNER,
		'subject'  => id_text( $p1 ),
		'actor'    => PARTNER_REP,
		'ground'   => 'member',
		'evidence' => 'index',
		'message'  => 'A sponsor event was planned for November 10, 2026.',
		'data'     => array(
			'start'   => $p1_start,
			'minutes' => 60,
			'places'  => 2,
			'joined'  => 0,
		),
	)
);
ck( 'the sponsor\'s lock is released once the event is written', static function () use ( $p1 ) { return array( is_int( $p1 ), get_option( 'wpcpm_sevent_lock_' . PARTNER, 'gone' ) ); }, array( true, 'gone' ) );
ck(
	'facts(): everything a list needs, the name it gives beside the title the sponsor\'s, and neither the link nor anybody\'s name',
	static function () use ( $p1, $p1_start ) { return WPCPM_Sponsor_Event::facts( get_post( $p1 ) ); },
	array(
		'id'          => $p1,
		'sponsor'     => PARTNER,
		'host'        => 'Partner Example',
		'title'       => 'Accessible themes, start to finish',
		'description' => 'How the theme team checks contrast. Bring a C:\\themes folder.',
		'start'       => $p1_start,
		'end'         => $p1_start + HOUR_IN_SECONDS,
		'minutes'     => 60,
		'places'      => 2,
		'zone'        => 'UTC',
		'kind'        => 'co_hosted',
		'state'       => 'planned',
		'decision'    => array(
			'at'   => 0,
			'by'   => 0,
			'note' => '',
		),
		'revision'    => 0,
		'joined'      => 0,
	)
);
ck( 'the link is read on its own, by link_of()', static function () use ( $p1 ) { return WPCPM_Sponsor_Event::link_of( get_post( $p1 ) ); }, 'https://call.example/room-1' );

$GLOBALS['forgotten'] = array();
ck(
	'the allowance reads each of the sponsor\'s events afresh, not from this request\'s copy: under the lock it decides a write',
	static function () use ( $p1, $row ) { return array( WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), 0 ), in_array( 'post_meta:' . $p1, $GLOBALS['forgotten'], true ), in_array( 'post_meta:' . $GLOBALS['p0'], $GLOBALS['forgotten'], true ) ); },
	array( 'one_open', true, true )
);

$posts_before = count( $GLOBALS['posts'] );
ck(
	'a second plan while this one is upcoming is refused one_open, and writes nothing',
	static function () use ( $posts_before ) { return array( code_of( WPCPM_Sponsor_Event::plan( PARTNER, event_fields( utc( '2026-12-10 15:00' ) ), PARTNER_REP, 'member' ) ), count( $GLOBALS['posts'] ) === $posts_before, get_option( 'wpcpm_sevent_lock_' . PARTNER, 'gone' ) ); },
	array( 'one_open', true, 'gone' )
);
ck(
	'fields the store cannot hold, a ground that is neither, and nobody acting are refused before anything is read',
	static function () {
		return array( code_of( WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-10 15:00' ), array( 'minutes' => 5 ) ), CHAMPION_REP, 'member' ) ), code_of( WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-10 15:00' ), array( 'link' => 'ftp://files.example/x' ) ), CHAMPION_REP, 'member' ) ), code_of( WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-10 15:00' ) ), CHAMPION_REP, 'system' ) ), code_of( WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-10 15:00' ) ), 0, 'member' ) ) );
	},
	array( 'fields', 'fields', 'not_allowed', 'not_allowed' )
);
ck(
	'the allowance is asked again under the lock: a Backer and a Partner whose term has lapsed are refused no_tier',
	static function () {
		return array( code_of( WPCPM_Sponsor_Event::plan( BACKER, event_fields( utc( '2026-12-10 15:00' ) ), MANAGER, 'manager' ) ), code_of( WPCPM_Sponsor_Event::plan( LAPSED, event_fields( utc( '2026-12-10 15:00' ) ), MANAGER, 'manager' ) ) );
	},
	array( 'no_tier', 'no_tier' )
);
ck( 'the refusal carries a sentence', static function () { return strlen( WPCPM_Sponsor_Event::plan( BACKER, event_fields( utc( '2026-12-10 15:00' ) ), MANAGER, 'manager' )->get_error_message() ) > 10; }, true );

$GLOBALS['insert_fail'] = true;
ck( 'an insert core refuses is not_saved, and the lock goes back', static function () { return array( code_of( WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-10 15:00' ) ), CHAMPION_REP, 'member' ) ), get_option( 'wpcpm_sevent_lock_' . CHAMPION, 'gone' ) ); }, array( 'not_saved', 'gone' ) );
$GLOBALS['insert_fail'] = false;

echo "\n=== The readers before approval ===\n";

ck( 'upcoming_of() the Partner is the planned event', static function () use ( $p1 ) { return ids( WPCPM_Sponsor_Event::upcoming_of( PARTNER ) ); }, array( $p1 ) );
ck( 'waiting() for the program is the planned event', static function () use ( $p1 ) { return ids( WPCPM_Sponsor_Event::waiting() ); }, array( $p1 ) );
ck( 'and nobody is shown it yet: open_for() a current student is empty', static function () use ( $p1 ) { return array( is_int( $p1 ), ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ) ); }, array( true, array() ) );
ck( 'nor is it on the program\'s own list of approved events: approved_upcoming() holds nothing planned', static function () use ( $p1 ) { return array( is_int( $p1 ), ids( WPCPM_Sponsor_Event::approved_upcoming() ) ); }, array( true, array() ) );

echo "\n=== approve() ===\n";

$purged_by_plans = $GLOBALS['purged'];

ck( 'a program manager approves it', static function () use ( $p1 ) { return WPCPM_Sponsor_Event::approve( $p1, MANAGER ); }, true );
ck(
	'and the host\'s page cache is told the Partners page changed, through clean_post_cache() of the page, since an approved event is listed there at once; a sponsor\'s plans, which wait for the program\'s OK, the cancel of one still waiting and every refusal before told it nothing',
	static function () use ( $purged_by_plans ) { return array( $purged_by_plans, $GLOBALS['purged'] ); },
	array( array(), array( SPONSORS_PAGE ) )
);
ck(
	'approved, the decision stamped with the moment and the manager and no note, version 1',
	static function () use ( $p1 ) { return array( meta( $p1, '_wpcpm_sevent_state' ), meta( $p1, '_wpcpm_sevent_decision' ), meta( $p1, '_wpcpm_sevent_revision' ) ); },
	array( 'approved', array( 'at' => $GLOBALS['now'], 'by' => MANAGER, 'note' => '' ), '1' )
);
ck(
	'the row: approved, on the manager ground, by the manager, the day in its words',
	static function () { $last = last_row(); return array( $last['kind'], $last['ground'], $last['actor'], $last['message'] ); },
	array( 'sponsor_event_approved', 'manager', MANAGER, 'An Administrator approved the sponsor event on November 10, 2026.' )
);
ck( 'approving it again is not_planned', static function () use ( $p1 ) { return code_of( WPCPM_Sponsor_Event::approve( $p1, MANAGER ) ); }, 'not_planned' );
ck( 'nothing waits now, and a current student sees it', static function () use ( $p1 ) { return array( ids( WPCPM_Sponsor_Event::waiting() ), ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ) ); }, array( array(), array( $p1 ) ) );
ck( 'and the program\'s own list has it: approved_upcoming()', static function () use ( $p1 ) { return ids( WPCPM_Sponsor_Event::approved_upcoming() ); }, array( $p1 ) );

echo "\n=== change() ===\n";

$p1_moved = utc( '2026-11-12 16:00' );
$moved    = event_fields(
	$p1_moved,
	array(
		'minutes'     => 90,
		'places'      => 2,
		'title'       => 'Accessible themes, the second look',
		'description' => 'Moved by two days. Slides in C:\\talks.',
		'link'        => 'https://call.example/room-2',
		'zone'        => 'Europe/Warsaw',
	)
);

// What was forgotten so far is the reads before this one's: the change has to forget the event itself.
$GLOBALS['forgotten'] = array();

// The member's change leaves three places; the manager's, below, brings them to two.
$moved_member       = array_merge( $moved, array( 'places' => 3 ) );
$GLOBALS['purged'] = array();

ck( 'the member moves it: nobody is on it yet, so the answer is a change that told nobody and left nobody to tell, and no mail', static function () use ( $p1, $moved_member ) { $before = count( $GLOBALS['mail'] ); return array( WPCPM_Sponsor_Event::change( $p1, $moved_member, PARTNER_REP ), count( $GLOBALS['mail'] ) - $before ); }, array( array( 'changed' => true, 'told' => 0, 'pending' => 0 ), 0 ) );
ck(
	'still approved, version 2, and every field is the new one',
	static function () use ( $p1, $p1_moved ) {
		$post = get_post( $p1 );
		return array( meta( $p1, '_wpcpm_sevent_state' ), meta( $p1, '_wpcpm_sevent_revision' ), meta( $p1, '_wpcpm_sevent_start' ), meta( $p1, '_wpcpm_sevent_end' ), meta( $p1, '_wpcpm_sevent_minutes' ), meta( $p1, '_wpcpm_sevent_places' ), meta( $p1, '_wpcpm_sevent_zone' ), meta( $p1, '_wpcpm_sevent_link' ), $post->post_title, $post->post_content );
	},
	array( 'approved', '2', (string) $p1_moved, (string) ( $p1_moved + 90 * MINUTE_IN_SECONDS ), '90', '3', 'Europe/Warsaw', 'https://call.example/room-2', 'Accessible themes, the second look', 'Moved by two days. Slides in C:\\talks.' )
);

/** What a move could have written: the post, its meta, the trail, the mail, the schedule and the lock. */
$p1_now = static function () use ( $p1 ) { return array( serialize( get_post( $p1 ) ), $GLOBALS['pmeta'][ $p1 ], count( $GLOBALS['audit'] ), count( $GLOBALS['all_mail'] ), $GLOBALS['cron'], get_option( 'wpcpm_sevent_lock_' . PARTNER, 'gone' ) ); };

ck(
	'the same fields again change nothing: nothing written, the version where it was, no row on the trail, nobody mailed, the lock given back, and the answer says so',
	static function () use ( $p1, $moved_member, $p1_now ) {
		$before = $p1_now();
		return array( WPCPM_Sponsor_Event::change( $p1, $moved_member, PARTNER_REP ), $p1_now() === $before, meta( $p1, '_wpcpm_sevent_revision' ) );
	},
	array( array( 'changed' => false, 'told' => 0, 'pending' => 0 ), true, '2' )
);
ck(
	'nor does the event saved as it stands from another clock: the moment is the same one, and the clock it was entered on is no change of its own',
	static function () use ( $p1, $moved_member, $p1_now ) {
		$before = $p1_now();
		return array( WPCPM_Sponsor_Event::change( $p1, array_merge( $moved_member, array( 'zone' => 'America/New_York' ) ), PARTNER_REP ), $p1_now() === $before, meta( $p1, '_wpcpm_sevent_zone' ) );
	},
	array( array( 'changed' => false, 'told' => 0, 'pending' => 0 ), true, 'Europe/Warsaw' )
);
ck(
	'a title and a description the Change form posted back as it drew them are the event\'s own: handed over in other words but named unedited, they change nothing, and the event keeps what it holds',
	static function () use ( $p1, $moved_member, $p1_now ) {
		$before = $p1_now();
		$answer = WPCPM_Sponsor_Event::change( $p1, array_merge( $moved_member, array( 'title' => 'Handed over in other words', 'description' => 'Handed over in other words too.', 'unedited' => array( 'title', 'description' ) ) ), PARTNER_REP );
		return array( $answer, $p1_now() === $before, get_post( $p1 )->post_title, get_post( $p1 )->post_content );
	},
	array( array( 'changed' => false, 'told' => 0, 'pending' => 0 ), true, 'Accessible themes, the second look', 'Moved by two days. Slides in C:\\talks.' )
);
ck( 'the change of the approved event told the host\'s page cache the Partners page changed, once, and the two that changed nothing told it nothing', static function () { return $GLOBALS['purged']; }, array( SPONSORS_PAGE ) );
ck(
	'the row: changed, on the member ground, the new day in its words',
	static function () { $last = last_row(); return array( $last['kind'], $last['ground'], $last['actor'], $last['message'] ); },
	array( 'sponsor_event_changed', 'member', PARTNER_REP, 'The sponsor event was changed; it takes place on November 12, 2026.' )
);
ck( 'the event was read again under the lock, not from this request\'s copy', static function () use ( $p1 ) { return in_array( 'post_meta:' . $p1, $GLOBALS['forgotten'], true ); }, true );
ck(
	'a manager changing it acts on the manager ground, version 3: the places alone are a change, written and on the trail',
	static function () use ( $p1, $moved ) { $done = WPCPM_Sponsor_Event::change( $p1, $moved, MANAGER ); $last = last_row(); return array( $done, $last['kind'], $last['ground'], meta( $p1, '_wpcpm_sevent_revision' ), meta( $p1, '_wpcpm_sevent_places' ) ); },
	array( array( 'changed' => true, 'told' => 0, 'pending' => 0 ), 'sponsor_event_changed', 'manager', '3', '2' )
);
ck(
	'a change to a start that has passed, one after the term, fields the store cannot hold, a post that is no event, and nobody acting are all refused, and nothing moves',
	static function () use ( $p1, $moved, $page ) {
		return array(
			code_of( WPCPM_Sponsor_Event::change( $p1, array_merge( $moved, array( 'start' => utc( '2026-10-06 11:59' ) ) ), PARTNER_REP ) ),
			code_of( WPCPM_Sponsor_Event::change( $p1, array_merge( $moved, array( 'start' => utc( '2027-02-01 15:00' ) ) ), PARTNER_REP ) ),
			code_of( WPCPM_Sponsor_Event::change( $p1, array_merge( $moved, array( 'minutes' => 500 ) ), PARTNER_REP ) ),
			code_of( WPCPM_Sponsor_Event::change( $page, $moved, PARTNER_REP ) ),
			code_of( WPCPM_Sponsor_Event::change( $p1, $moved, 0 ) ),
			meta( $p1, '_wpcpm_sevent_revision' ),
			meta( $p1, '_wpcpm_sevent_start' ),
		);
	},
	array( 'in_past', 'past_term', 'fields', 'not_found', 'not_allowed', '3', (string) $p1_moved )
);

/* ---- who may join --------------------------------------------------------------- */

echo "\n=== Who may join ===\n";

ck(
	'managers first, then current students, Active mentors and institution contacts; nobody else',
	static function () {
		$out = array();
		foreach ( array( MANAGER, STUDENT, GRADUATE, NEWCOMER, MENTOR, INACTIVE_MENTOR, MENTOR_REP, CONTACT, FORMER_CONTACT, PARTNER_REP, CHAMPION_REP, 0, 9999 ) as $who ) {
			$out[ $who ] = WPCPM_Sponsor_Event::audience_of( $who );
		}
		return $out;
	},
	array(
		MANAGER         => 'managers',
		STUDENT         => 'students',
		GRADUATE        => '',
		NEWCOMER        => '',
		MENTOR          => 'mentors',
		INACTIVE_MENTOR => '',
		MENTOR_REP      => 'mentors',
		CONTACT         => 'institutions',
		FORMER_CONTACT  => '',
		PARTNER_REP     => '',
		CHAMPION_REP    => '',
		0               => '',
		9999            => '',
	)
);
// The design's word (18.2) is "current students (as the Tools section counts them)", and the Tools
// section counts a Paused student as current: `Paused` is one of the student statuses and not one
// of the past ones (bin/test-sponsor-tools.php pins "a Paused student is current").
ck( 'a Paused student is a current student as the Tools section counts them, so a student here too', static function () { return WPCPM_Sponsor_Event::audience_of( PAUSED ); }, 'students' );
ck( 'the account given as an object reads the same, and no account is the current one', static function () { $GLOBALS['uid'] = CONTACT; $answer = array( WPCPM_Sponsor_Event::audience_of( $GLOBALS['users'][ MENTOR ] ), WPCPM_Sponsor_Event::audience_of( null ) ); $GLOBALS['uid'] = 0; return $answer; }, array( 'mentors', 'institutions' ) );
ck( 'the mentors module\'s own reader decides who is an Active mentor', method_exists( 'WPCPM_Mentors_Dashboard', 'is_active_mentor' ), true );

echo "\n=== join() and leave() ===\n";

$GLOBALS['forgotten'] = array();

ck( 'a current student joins: on it, version 4', static function () use ( $p1 ) { return array( WPCPM_Sponsor_Event::join( $p1, STUDENT ), WPCPM_Sponsor_Event::joiners( $p1 ), meta( $p1, '_wpcpm_sevent_revision' ) ); }, array( true, array( STUDENT ), '4' ) );
ck( 'the join read the event again under the lock, where the last place is counted', static function () use ( $p1 ) { return in_array( 'post_meta:' . $p1, $GLOBALS['forgotten'], true ); }, true );
ck( 'one row per person, added as a row of its own', static function () use ( $p1 ) { return array( get_post_meta( $p1, '_wpcpm_sevent_person', false ), end( $GLOBALS['added'] ) ); }, array( array( (string) STUDENT ), array( '_wpcpm_sevent_person', false ) ) );
ck( 'joining twice is joined', static function () use ( $p1 ) { return code_of( WPCPM_Sponsor_Event::join( $p1, STUDENT ) ); }, 'joined' );
ck( 'an Active mentor takes the second place', static function () use ( $p1 ) { return array( WPCPM_Sponsor_Event::join( $p1, MENTOR ), WPCPM_Sponsor_Event::joiners( $p1 ) ); }, array( true, array( STUDENT, MENTOR ) ) );
ck( 'two places, two people: a manager is refused full', static function () use ( $p1 ) { return code_of( WPCPM_Sponsor_Event::join( $p1, MANAGER ) ); }, 'full' );
ck(
	'a graduate, the sponsor\'s own representative, an inactive mentor, a former contact and nobody are not in the audience',
	static function () use ( $p1 ) { return array( code_of( WPCPM_Sponsor_Event::join( $p1, GRADUATE ) ), code_of( WPCPM_Sponsor_Event::join( $p1, PARTNER_REP ) ), code_of( WPCPM_Sponsor_Event::join( $p1, INACTIVE_MENTOR ) ), code_of( WPCPM_Sponsor_Event::join( $p1, FORMER_CONTACT ) ), code_of( WPCPM_Sponsor_Event::join( $p1, 0 ) ), code_of( WPCPM_Sponsor_Event::join( $p1, null ) ) ); },
	array( 'audience', 'audience', 'audience', 'audience', 'audience', 'audience' )
);
ck( 'a mentor who acts for the Partner is refused its own event', static function () use ( $p1 ) { return code_of( WPCPM_Sponsor_Event::join( $p1, MENTOR_REP ) ); }, 'own' );
$GLOBALS['forgotten'] = array();

ck( 'the mentor leaves, and the place is free at once', static function () use ( $p1 ) { return array( WPCPM_Sponsor_Event::leave( $p1, MENTOR ), WPCPM_Sponsor_Event::joiners( $p1 ), meta( $p1, '_wpcpm_sevent_revision' ) ); }, array( true, array( STUDENT ), '6' ) );
ck( 'and the leave read it again under the lock too', static function () use ( $p1 ) { return in_array( 'post_meta:' . $p1, $GLOBALS['forgotten'], true ); }, true );
ck( 'leaving again is not_joined, and so is somebody who never joined', static function () use ( $p1 ) { return array( code_of( WPCPM_Sponsor_Event::leave( $p1, MENTOR ) ), code_of( WPCPM_Sponsor_Event::leave( $p1, GRADUATE ) ) ); }, array( 'not_joined', 'not_joined' ) );
ck( 'an institution contact takes the freed place', static function () use ( $p1 ) { return array( WPCPM_Sponsor_Event::join( $p1, CONTACT ), WPCPM_Sponsor_Event::joiners( $p1 ) ); }, array( true, array( STUDENT, CONTACT ) ) );
ck( 'and the Paused student, in the audience, finds it full', static function () use ( $p1 ) { return code_of( WPCPM_Sponsor_Event::join( $p1, PAUSED ) ); }, 'full' );
ck( 'joined() answers per person; facts() counts them', static function () use ( $p1 ) { return array( WPCPM_Sponsor_Event::joined( $p1, STUDENT ), WPCPM_Sponsor_Event::joined( $p1, MENTOR ), WPCPM_Sponsor_Event::joined( get_post( $p1 ), $GLOBALS['users'][ CONTACT ] ), WPCPM_Sponsor_Event::facts( get_post( $p1 ) )['joined'] ); }, array( true, false, true, 2 ) );
ck( 'places may not fall below the people on it: shrink', static function () use ( $p1, $moved ) { return code_of( WPCPM_Sponsor_Event::change( $p1, array_merge( $moved, array( 'places' => 1 ) ), PARTNER_REP ) ); }, 'shrink' );
ck( 'a canceled event and a post that is no event are not open', static function () use ( $p0, $page ) { return array( code_of( WPCPM_Sponsor_Event::join( $p0, STUDENT ) ), code_of( WPCPM_Sponsor_Event::join( $page, STUDENT ) ) ); }, array( 'not_open', 'not_open' ) );
ck( 'no join or leave wrote an audit row: those are not the sponsor\'s moves', static function () use ( $p1 ) { return trail_of( $p1 ); }, array( array( 'sponsor_event_planned', 'member', PARTNER_REP ), array( 'sponsor_event_approved', 'manager', MANAGER ), array( 'sponsor_event_changed', 'member', PARTNER_REP ), array( 'sponsor_event_changed', 'manager', MANAGER ) ) );

echo "\n=== The Champion's events: a return with a note, a cancel ===\n";

$GLOBALS['purged'] = array();

$c1 = attempt( static function () { return WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-01 10:00' ), array( 'title' => 'Champion webinar', 'description' => 'Open to everyone.', 'link' => 'https://call.example/champion' ) ), CHAMPION_REP, 'member' ); } );

ck( 'a planned event is not open to join', static function () use ( $c1 ) { return code_of( WPCPM_Sponsor_Event::join( $c1, STUDENT ) ); }, 'not_open' );
ck( 'and it waits, soonest first', static function () use ( $c1 ) { return ids( WPCPM_Sponsor_Event::waiting() ); }, array( $c1 ) );
ck( 'a return needs its note', static function () use ( $c1 ) { return array( code_of( WPCPM_Sponsor_Event::return_with_note( $c1, MANAGER, '   ' ) ), meta( $c1, '_wpcpm_sevent_state' ) ); }, array( 'note', 'planned' ) );
ck( 'a manager returns it with one', static function () use ( $c1 ) { return WPCPM_Sponsor_Event::return_with_note( $c1, MANAGER, "Please add the agenda to C:\\shared.\nThank you." ); }, true );
ck(
	'canceled, the note kept in the decision, version 1',
	static function () use ( $c1 ) { return array( meta( $c1, '_wpcpm_sevent_state' ), meta( $c1, '_wpcpm_sevent_decision' ), meta( $c1, '_wpcpm_sevent_revision' ) ); },
	array( 'cancelled', array( 'at' => $GLOBALS['now'], 'by' => MANAGER, 'note' => "Please add the agenda to C:\\shared.\nThank you." ), '1' )
);
ck(
	'the row: returned, on the manager ground, and the note is not in it',
	static function () { $last = last_row(); return array( $last['kind'], $last['ground'], $last['message'], array_keys( $last['data'] ) ); },
	array( 'sponsor_event_returned', 'manager', 'An Administrator returned the sponsor event on December 1, 2026 with a note.', array( 'start', 'minutes', 'places', 'joined' ) )
);
ck(
	'a canceled event takes no move at all',
	static function () use ( $c1 ) { return array( code_of( WPCPM_Sponsor_Event::return_with_note( $c1, MANAGER, 'Again.' ) ), code_of( WPCPM_Sponsor_Event::approve( $c1, MANAGER ) ), code_of( WPCPM_Sponsor_Event::change( $c1, event_fields( utc( '2026-12-02 10:00' ) ), CHAMPION_REP ) ), code_of( WPCPM_Sponsor_Event::cancel( $c1, CHAMPION_REP ) ) ); },
	array( 'cancelled', 'cancelled', 'cancelled', 'cancelled' )
);
ck( 'a returned event frees the allowance too', static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( CHAMPION, $row( CHAMPION ), utc( '2026-12-02 10:00' ) ); }, '' );
ck( 'returned_note(): the note the sponsor\'s latest event came back with, for the planner to show above its form', static function () { return WPCPM_Sponsor_Event::returned_note( CHAMPION ); }, "Please add the agenda to C:\\shared.\nThank you." );

$c2 = attempt( static function () { return WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-02 10:00' ), array( 'title' => 'Champion webinar, again', 'description' => 'Open to everyone, with the agenda.', 'link' => 'https://call.example/champion-2' ) ), MANAGER, 'manager' ); } );

ck( 'a manager plans on the Champion\'s behalf, on the manager ground', static function () { $last = last_row(); return array( $last['kind'], $last['ground'], $last['actor'] ); }, array( 'sponsor_event_planned', 'manager', MANAGER ) );
ck( 'a new plan puts the note away: the sponsor\'s latest event was not returned', static function () { return WPCPM_Sponsor_Event::returned_note( CHAMPION ); }, '' );

attempt( static function () use ( $c2 ) { return WPCPM_Sponsor_Event::approve( $c2, MANAGER ); } );

ck(
	'no places means no limit: five people join, the Partner\'s mentor among them, since this event is not the Partner\'s',
	static function () use ( $c2 ) {
		$joined = array();
		foreach ( array( MENTOR_REP, STUDENT, PAUSED, CONTACT, MANAGER ) as $who ) { $joined[] = WPCPM_Sponsor_Event::join( $c2, $who ); }
		return array( $joined, WPCPM_Sponsor_Event::joiners( $c2 ) );
	},
	array( array( true, true, true, true, true ), array( MENTOR_REP, STUDENT, PAUSED, CONTACT, MANAGER ) )
);
ck(
	'open_for(): the approved events not over yet, soonest first, and never a sponsor\'s own to its own member',
	static function () use ( $p1, $c2 ) { return array( ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ), ids( WPCPM_Sponsor_Event::open_for( MENTOR_REP ) ), ids( WPCPM_Sponsor_Event::open_for( MANAGER ) ), ids( WPCPM_Sponsor_Event::open_for( PARTNER_REP ) ), ids( WPCPM_Sponsor_Event::open_for( GRADUATE ) ) ); },
	array( array( $p1, $c2 ), array( $c2 ), array( $p1, $c2 ), array(), array() )
);
ck( 'approved_upcoming(): every sponsor\'s approved event not over yet, soonest first, whoever asks', static function () use ( $p1, $c2 ) { return ids( WPCPM_Sponsor_Event::approved_upcoming() ); }, array( $p1, $c2 ) );
$GLOBALS['mail']   = array();
$GLOBALS['cached'] = array();

ck(
	'the Champion\'s member cancels it; the people on it stay on the record, and the answer is how many of them were told, all five in the press, and that nobody is left to tell',
	static function () use ( $c2 ) { $done = WPCPM_Sponsor_Event::cancel( $c2, CHAMPION_REP ); $last = last_row(); return array( $done, $last['kind'], $last['ground'], $last['actor'], $last['data']['joined'], WPCPM_Sponsor_Event::joiners( $c2 ), get_post_meta( $c2, '_wpcpm_sevent_pending', false ) ); },
	array( array( 'told' => 5, 'pending' => 0 ), 'sponsor_event_cancelled', 'member', CHAMPION_REP, 5, array( MENTOR_REP, STUDENT, PAUSED, CONTACT, MANAGER ), array() )
);
ck( 'the cancel of the approved event told the host\'s page cache the Partners page changed, as its approval did; the return of the one still waiting, and the plans, told it nothing', static function () { return $GLOBALS['purged']; }, array( SPONSORS_PAGE, SPONSORS_PAGE ) );

/** The mails the last move sent, as each recipient's address. */
function mailed_to() { return array_map( static function ( $mail ) { return $mail['to']; }, $GLOBALS['mail'] ); }

/** The one mail sent to an address, or an empty one. */
function mail_for( $address ) {
	foreach ( $GLOBALS['mail'] as $mail ) {
		if ( $address === $mail['to'] ) { return $mail; }
	}
	return array( 'to' => '', 'subject' => '', 'body' => '', 'headers' => array(), 'files' => array() );
}

$c2_mails = attempt( static function () { return $GLOBALS['mail']; } );

ck(
	'each person on it is mailed once, in the order they joined, and the accounts were read in one query before the first send',
	static function () { return array( mailed_to(), array_slice( $GLOBALS['cached'], 0, 2 ) ); },
	array(
		array( 'mel.mentorrep@people.example', 'sam.current@people.example', 'paula.paused@people.example', 'ines.institution@people.example', 'mira.manager@people.example' ),
		array( 'cache ' . MENTOR_REP . ',' . STUDENT . ',' . PAUSED . ',' . CONTACT . ',' . MANAGER, 'mail mel.mentorrep@people.example' ),
	)
);
ck(
	'the mail says it is canceled, which event, whose, and when on the person\'s own clock, with the file that takes it off their calendar',
	static function () { $mail = mail_for( 'sam.current@people.example' ); return array( $mail['subject'], $mail['body'], $mail['headers'] ); },
	array(
		'[WordPress Education] Event canceled: Champion webinar, again',
		"An event you joined is canceled.\n\n"
			. "Title: Champion webinar, again\nSponsor: Champion Example\nWhen: December 2, 2026 5:00 am to 6:00 am (America / New York)\n\n"
			. 'The calendar file attached takes it off your calendar.',
		array(),
	)
);
ck(
	'the next person reads it on their clock, and one with none of their own on the site\'s',
	static function () { return array( 1 === preg_match( '/^When: December 2, 2026 7:00 pm to 8:00 pm \(Asia \/ Tokyo\)$/m', mail_for( 'ines.institution@people.example' )['body'] ), 1 === preg_match( '/^When: December 2, 2026 10:00 am to 11:00 am \(UTC\)$/m', mail_for( 'mira.manager@people.example' )['body'] ) ); },
	array( true, true )
);
ck(
	'every one carries the cancel under the event\'s own identity, at the version the cancel raised, naming the person it went to as its attendee, and each send removed its file once it was handed off',
	static function () use ( $c2 ) {
		$out   = array();
		$paths = array();
		foreach ( $GLOBALS['mail'] as $mail ) {
			$ics     = isset( $mail['files'][0]['ics'] ) ? (string) $mail['files'][0]['ics'] : '';
			$paths[] = isset( $mail['files'][0]['path'] ) ? $mail['files'][0]['path'] : '';
			$out[]   = array( count( $mail['files'] ), false !== strpos( $ics, "METHOD:CANCEL\r\n" ), false !== strpos( $ics, "STATUS:CANCELLED\r\n" ), false !== strpos( $ics, 'UID:wpcpm-call-' . $c2 . '@site.example' ), false !== strpos( $ics, 'SEQUENCE:' . meta( $c2, '_wpcpm_sevent_revision' ) . "\r\n" ), false !== strpos( $ics, 'RSVP=FALSE:mailto:' . $mail['to'] . "\r\n" ) );
		}
		return array( count( $out ), array_values( array_unique( array_map( 'serialize', $out ) ) ) === array( serialize( array( 1, true, true, true, true, true ) ) ), meta( $c2, '_wpcpm_sevent_revision' ), array_values( array_filter( $paths, 'file_exists' ) ) );
	},
	array( 5, true, '7', array() )
);
ck(
	'the cancel\'s first mail left once the cancel\'s row was on the trail and the sponsor\'s lock was given back: nobody waits on the lock while the mail goes',
	static function () use ( $c2_mails, $c2 ) { return isset( $c2_mails[0]['moment'] ) ? $c2_mails[0]['moment'] : 'no mail'; },
	array( 'row' => array( 'sponsor_event_cancelled', id_text( $c2 ) ), 'locks' => array() )
);
ck(
	'the drain the cancel put on the clock runs, finds nobody left to tell, mails nobody and puts nothing back on the clock',
	static function () {
		$mails = count( $GLOBALS['all_mail'] );
		$was   = $GLOBALS['cron'];
		run_cron( 'wpcpm_sevent_drain' );
		return array( $was, count( $GLOBALS['all_mail'] ) - $mails, $GLOBALS['cron'] );
	},
	array( array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 60 ) ), 0, array() )
);
ck(
	'a Leave on the canceled event, from a page drawn before the cancel, is refused cancelled under the lock: the person stays on its record, the version stays, nobody is mailed, and the lock goes back',
	static function () use ( $c2 ) {
		$before = array( meta( $c2, '_wpcpm_sevent_revision' ), count( $GLOBALS['all_mail'] ) );
		$code   = code_of( WPCPM_Sponsor_Event::leave( $c2, STUDENT ) );
		return array( $code, WPCPM_Sponsor_Event::joiners( $c2 ), array( meta( $c2, '_wpcpm_sevent_revision' ), count( $GLOBALS['all_mail'] ) ) === $before, get_option( 'wpcpm_sevent_lock_' . CHAMPION, 'gone' ) );
	},
	array( 'cancelled', array( MENTOR_REP, STUDENT, PAUSED, CONTACT, MANAGER ), true, 'gone' )
);
ck( 'and it leaves every list', static function () use ( $p1 ) { return array( ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ), ids( WPCPM_Sponsor_Event::upcoming_of( CHAMPION ) ), ids( WPCPM_Sponsor_Event::approved_upcoming() ) ); }, array( array( $p1 ), array(), array( $p1 ) ) );
ck( 'a cancel is no return, and a sponsor with no returned event, or a value that is no key, has no note', static function () { return array( WPCPM_Sponsor_Event::returned_note( CHAMPION ), WPCPM_Sponsor_Event::returned_note( PARTNER ), WPCPM_Sponsor_Event::returned_note( 'not a key' ) ); }, array( '', '', '' ) );

// A Champion with a term plans an event that nobody approves before its day.
$stale = attempt( static function () { return WPCPM_Sponsor_Event::plan( STALE, event_fields( utc( '2026-11-15 10:00' ), array( 'title' => 'Never approved' ) ), MANAGER, 'manager' ); } );

ck( 'it waits beside nothing else of its sponsor\'s, and holds the sponsor to one at a time', static function () use ( $stale, $row ) { return array( in_array( $stale, ids( WPCPM_Sponsor_Event::waiting() ), true ), WPCPM_Sponsor_Event::may_plan( STALE, $row( STALE ), utc( '2027-01-10 10:00' ) ) ); }, array( true, 'one_open' ) );

echo "\n=== Once the Partner's event has taken place ===\n";

set_now( utc( '2026-11-20 09:00' ) );

ck(
	'a planned event whose day passed without an approval waits no longer, spends nothing of its sponsor\'s term, and takes no approval or return: past',
	static function () use ( $stale, $row ) { return array( is_int( $stale ), in_array( $stale, ids( WPCPM_Sponsor_Event::waiting() ), true ), WPCPM_Sponsor_Event::may_plan( STALE, $row( STALE ), utc( '2027-01-10 10:00' ) ), code_of( WPCPM_Sponsor_Event::approve( $stale, MANAGER ) ), code_of( WPCPM_Sponsor_Event::return_with_note( $stale, MANAGER, 'Late.' ) ) ); },
	array( true, false, '', 'past', 'past' )
);

ck( 'an approved event in the running term spends it: term_used, whatever the date asked for', static function () use ( $row ) { return array( WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), utc( '2026-12-15 15:00' ) ), WPCPM_Sponsor_Event::may_plan( PARTNER, $row( PARTNER ), 0 ) ); }, array( 'term_used', 'term_used' ) );
ck( 'past_of() the Partner is the event it held; upcoming_of() is empty', static function () use ( $p1 ) { return array( ids( WPCPM_Sponsor_Event::past_of( PARTNER ) ), ids( WPCPM_Sponsor_Event::upcoming_of( PARTNER ) ) ); }, array( array( $p1 ), array() ) );
ck(
	'an event whose end has passed takes no join, leave, change or cancel: past; and, approved, no return either, which is for a waiting event alone: not_planned',
	static function () use ( $p1, $moved ) { return array( code_of( WPCPM_Sponsor_Event::join( $p1, PAUSED ) ), code_of( WPCPM_Sponsor_Event::leave( $p1, STUDENT ) ), code_of( WPCPM_Sponsor_Event::change( $p1, array_merge( $moved, array( 'start' => utc( '2026-12-01 10:00' ) ) ), PARTNER_REP ) ), code_of( WPCPM_Sponsor_Event::cancel( $p1, PARTNER_REP ) ), code_of( WPCPM_Sponsor_Event::return_with_note( $p1, MANAGER, 'Late.' ) ) ); },
	array( 'past', 'past', 'past', 'past', 'not_planned' )
);
ck( 'and it is no longer open to anybody, nor on the program\'s own list', static function () { return array( ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ), ids( WPCPM_Sponsor_Event::approved_upcoming() ) ); }, array( array(), array() ) );
ck( 'a Champion with no term is bound by one at a time alone', static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( CHAMPION, $row( CHAMPION ), utc( '2026-12-20 10:00' ) ); }, '' );

/* ---- held_in() ------------------------------------------------------------------ */

echo "\n=== held_in() ===\n";

$h_before = seed_event( HELD, 'approved', utc( '2026-01-31 22:59:59' ), 60, 'Ends the second before' );
$h_first  = seed_event( HELD, 'approved', utc( '2026-01-31 23:00:00' ), 60, 'Ends as the first day begins' );
$h_last   = seed_event( HELD, 'approved', utc( '2027-01-31 22:59:59' ), 60, 'Ends on the last second', array( WALKER, MENTOR ) );
$h_after  = seed_event( HELD, 'approved', utc( '2027-01-31 23:00:00' ), 60, 'Ends as the day after begins' );
$h_gone   = seed_event( HELD, 'cancelled', utc( '2026-06-01 10:00' ), 60, 'Canceled in the middle' );
$h_never  = seed_event( HELD, 'planned', utc( '2026-07-01 10:00' ), 60, 'Never approved' );

ck( 'a seeded event carries the keys plan() writes, and no other', static function () use ( $planned_keys, $h_before ) { $seeded = array_keys( $GLOBALS['pmeta'][ $h_before ] ); sort( $seeded ); $written = $planned_keys; sort( $written ); return array( count( $written ) > 5, $seeded === $written ); }, array( true, true ) );

/** The titles `held_in()` answers for the Held Champion's period. */
function held_titles() { return array_map( static function ( $held ) { return $held['title']; }, WPCPM_Sponsor_Event::held_in( HELD, '2026-02-01', '2027-01-31' ) ); }

// The annual report can be drafted before a term ends, and the Reports card offers the running
// term's last day by default: an approved event still to come was not held, and nobody knows yet
// how many will be on it when it is.
ck(
	'an approved event is held once it has ended and not before: mid-period, the one whose end is still ahead is not listed',
	static function () { return held_titles(); },
	array( 'Ends as the first day begins' )
);
ck(
	'a second before its end it is still not held, and at its end it is',
	static function () {
		set_now( utc( '2027-01-31 23:59:58' ) );
		$before = held_titles();
		set_now( utc( '2027-01-31 23:59:59' ) );
		return array( $before, held_titles() );
	},
	array( array( 'Ends as the first day begins' ), array( 'Ends as the first day begins', 'Ends on the last second' ) )
);

$h_twice = seed_event( HELD, 'cancelled', utc( '2026-08-01 10:00' ), 60, 'Joined twice by one person', array( WALKER, WALKER, MENTOR ) );

ck( 'a person on an event by two rows is one person on it', static function () use ( $h_twice ) { return array( WPCPM_Sponsor_Event::joiners( $h_twice ), WPCPM_Sponsor_Event::facts( get_post( $h_twice ) )['joined'] ); }, array( array( WALKER, MENTOR ), 2 ) );

set_now( utc( '2027-03-01 12:00' ) );

ck(
	'the approved events whose end falls inside the period, at both edges, each as its title, start, minutes and the count of people on it',
	static function () { return WPCPM_Sponsor_Event::held_in( HELD, '2026-02-01', '2027-01-31' ); },
	array(
		array(
			'title'   => 'Ends as the first day begins',
			'start'   => utc( '2026-01-31 23:00:00' ),
			'minutes' => 60,
			'joined'  => 0,
		),
		array(
			'title'   => 'Ends on the last second',
			'start'   => utc( '2027-01-31 22:59:59' ),
			'minutes' => 60,
			'joined'  => 2,
		),
	)
);

$GLOBALS['site_tz'] = 'America/New_York';
ck(
	'the days are the site\'s: in New York the first of those ends on 31 January, and the one after the period ends inside it',
	static function () { return held_titles(); },
	array( 'Ends on the last second', 'Ends as the day after begins' )
);
$GLOBALS['site_tz'] = 'UTC';

ck(
	'past_of() is every approved event that is over, the latest first: never a canceled one, never one only planned',
	static function () use ( $h_before, $h_first, $h_last, $h_after ) { return ids( WPCPM_Sponsor_Event::past_of( HELD ) ); },
	array( $h_after, $h_last, $h_first, $h_before )
);
ck( 'the Partner held its one event, with the two people on it', static function () { return WPCPM_Sponsor_Event::held_in( PARTNER, '2026-02-01', '2027-01-31' ); }, array( array( 'title' => 'Accessible themes, the second look', 'start' => utc( '2026-11-12 16:00' ), 'minutes' => 90, 'joined' => 2 ) ) );
ck( 'a period that is not two days, or a key that is not one, holds nothing', static function () { return array( WPCPM_Sponsor_Event::held_in( HELD, 'garbage', '2027-01-31' ), WPCPM_Sponsor_Event::held_in( HELD, '2026-02-01', '2027-02-30' ), WPCPM_Sponsor_Event::held_in( 'not a key', '2026-02-01', '2027-01-31' ), count( WPCPM_Sponsor_Event::held_in( HELD, '2026-02-01', '2027-01-31' ) ) ); }, array( array(), array(), array(), 2 ) );

/* ---- the calendar file ------------------------------------------------------------ */

echo "\n=== The calendar file ===\n";

/** A calendar file's text, its folded lines put back together. */
function ics_of( array $files ) { return isset( $files[0] ) && is_file( $files[0] ) ? str_replace( "\r\n ", '', (string) file_get_contents( $files[0] ) ) : ''; }

// Built for the person it goes to, as every mail about an event asks for it: here the student on
// the Partner's event.
$p1_files = attempt( static function () use ( $p1 ) { return WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), '', $GLOBALS['users'][ STUDENT ] ); } );
$p1_ics   = ics_of( (array) $p1_files );

ck( 'one file, ready to attach and to clean up', is_array( $p1_files ) && 1 === count( $p1_files ) && is_file( $p1_files[0] ) && 'sponsor-event.ics' === basename( $p1_files[0] ), true );
ck(
	'the event as it stands: a request, its own UID, the version, the start and end in UTC, the title, the description with the link, confirmed',
	array(
		false !== strpos( $p1_ics, "METHOD:REQUEST\r\n" ),
		false !== strpos( $p1_ics, 'UID:wpcpm-call-' . id_text( $p1 ) . "@site.example\r\n" ),
		false !== strpos( $p1_ics, "SEQUENCE:7\r\n" ),
		false !== strpos( $p1_ics, "DTSTART:20261112T160000Z\r\n" ),
		false !== strpos( $p1_ics, "DTEND:20261112T173000Z\r\n" ),
		false !== strpos( $p1_ics, "SUMMARY:Accessible themes\\, the second look\r\n" ),
		false !== strpos( $p1_ics, "DESCRIPTION:Moved by two days. Slides in C:\\\\talks.\\n\\nJoin the call: https://call.example/room-2\r\n" ),
		false !== strpos( $p1_ics, "STATUS:CONFIRMED\r\n" ),
	),
	array( true, true, true, true, true, true, true, true )
);
/**
 * The ATTENDEE lines of a calendar file that name anybody but the program's own address.
 *
 * @param string $ics     The file's text, unfolded.
 * @param string $program The program's address.
 * @return string[]
 */
function attendees_but( $ics, $program ) {
	return array_values( array_filter( preg_split( '/\r\n/', (string) $ics ), static function ( $line ) use ( $program ) { return 0 === strpos( $line, 'ATTENDEE' ) && ! preg_match( '/:mailto:' . preg_quote( $program, '/' ) . '$/', $line ); } ) );
}

/**
 * The names and addresses of the accounts a calendar file holds, all but the one it is for.
 *
 * @param string $ics     The file's text, unfolded.
 * @param int    $user_id The account the file is for; 0 for nobody.
 * @return string[]
 */
function others_named( $ics, $user_id ) {
	$found = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( (int) $id === (int) $user_id ) { continue; }
		foreach ( array( $user->display_name, $user->user_email ) as $needle ) {
			if ( false !== stripos( (string) $ics, $needle ) ) { $found[] = $needle; }
		}
	}
	return $found;
}

/** The line a calendar file names a person with as its attendee, as the calendar builder writes it. */
function attendee_line( $user_id ) {
	$user = $GLOBALS['users'][ $user_id ];
	return 'ATTENDEE;CN="' . $user->display_name . '";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:' . $user->user_email;
}

// The organizer is who the mail the file arrives with comes from: core's own sender, since no
// message this plugin sends names a From of its own, and never an address a setting holds for
// some other purpose, however it is filled in (the sponsors module's interest addresses are set in
// this suite, and the site's admin address too). The attendee is the person the file goes to, as
// the sessions' files name theirs, since RFC 5546 asks a request to name at least one; their own
// name and address in their own file are theirs.
ck(
	'the organizer is the program\'s sender, as the mail layer sends from it; the person the file is for is its attendee, by name and address; and nobody else is named in it',
	array( false !== strpos( $p1_ics, "ORGANIZER;CN=\"WordPress\":mailto:wordpress@site.example\r\n" ), attendees_but( $p1_ics, 'wordpress@site.example' ), false !== strpos( $p1_ics, 'program.example' ), false !== strpos( $p1_ics, 'admin@site.example' ), others_named( $p1_ics, STUDENT ) ),
	array( true, array( attendee_line( STUDENT ) ), false, false, array() )
);
ck( 'asked again for the same person while the file is still there, the same file comes back', static function () use ( $p1, $p1_files ) { return WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), '', $GLOBALS['users'][ STUDENT ] ) === $p1_files; }, true );
ck(
	'another person is given a file of their own, which names them and not the first, and is given back to them in turn',
	static function () use ( $p1, $p1_files ) {
		$ines = WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), '', $GLOBALS['users'][ CONTACT ] );
		$text = ics_of( $ines );
		$out  = array( isset( $ines[0], $p1_files[0] ) && $ines[0] !== $p1_files[0], attendees_but( $text, 'wordpress@site.example' ), others_named( $text, CONTACT ), WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), '', $GLOBALS['users'][ CONTACT ] ) === $ines, is_file( $p1_files[0] ) );
		WPCPM_ICS::cleanup( $ines[0] );
		return $out;
	},
	array( true, array( attendee_line( CONTACT ) ), array(), true, true )
);
ck(
	'a file asked for nobody names the program alone, and is a file of its own',
	static function () use ( $p1, $p1_files ) {
		$none = WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ) );
		$text = ics_of( $none );
		$out  = array( isset( $none[0], $p1_files[0] ) && $none[0] !== $p1_files[0], false !== strpos( $text, "ORGANIZER;CN=\"WordPress\":mailto:wordpress@site.example\r\n" ), attendees_but( $text, 'wordpress@site.example' ), others_named( $text, 0 ) );
		WPCPM_ICS::cleanup( $none[0] );
		return $out;
	},
	array( true, true, array(), array() )
);

attempt( static function () use ( $p1_files ) { WPCPM_ICS::cleanup( $p1_files[0] ); } );

ck( 'once a send has cleaned it up, the next ask writes a new one', static function () use ( $p1, $p1_files ) { $again = WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), '', $GLOBALS['users'][ STUDENT ] ); $fresh = isset( $again[0] ) && $again[0] !== $p1_files[0] && is_file( $again[0] ); WPCPM_ICS::cleanup( $again[0] ); return $fresh; }, true );

ck(
	'a site that changes its sender changes the organizer with it: the two filters wp_mail() applies, and a host whose www. core drops',
	static function () use ( $c2 ) {
		$GLOBALS['filters']['wp_mail_from']      = array( static function () { return 'program@site.example'; } );
		$GLOBALS['filters']['wp_mail_from_name'] = array( static function () { return 'WordPress Education Program'; } );
		$files    = WPCPM_Sponsor_Event::calendar_file( get_post( $c2 ) );
		$filtered = ics_of( $files );
		WPCPM_ICS::cleanup( $files[0] );
		$GLOBALS['filters']      = array();
		$GLOBALS['network_home'] = 'https://www.site.example';
		$files                   = WPCPM_Sponsor_Event::calendar_file( get_post( $c2 ) );
		$www                     = ics_of( $files );
		WPCPM_ICS::cleanup( $files[0] );
		unset( $GLOBALS['network_home'] );
		return array( false !== strpos( $filtered, "ORGANIZER;CN=\"WordPress Education Program\":mailto:program@site.example\r\n" ), false !== strpos( $www, "ORGANIZER;CN=\"WordPress\":mailto:wordpress@site.example\r\n" ) );
	},
	array( true, true )
);

ck(
	'a canceled event\'s file cancels it, under the same UID and a higher version',
	static function () use ( $c2 ) { $files = WPCPM_Sponsor_Event::calendar_file( get_post( $c2 ) ); $text = ics_of( $files ); WPCPM_ICS::cleanup( $files[0] ); return array( false !== strpos( $text, "METHOD:CANCEL\r\n" ), false !== strpos( $text, "STATUS:CANCELLED\r\n" ), false !== strpos( $text, 'UID:wpcpm-call-' . $c2 . '@' ), false !== strpos( $text, "SEQUENCE:7\r\n" ) ); },
	array( true, true, true, true )
);
ck( 'a post that is no event has no file', static function () use ( $page ) { return WPCPM_Sponsor_Event::calendar_file( get_post( $page ) ); }, array() );
ck(
	'a caller may name the method: the cancel of an approved event, for a person who leaves it, under the same identity and version, a file of its own beside the request',
	static function () use ( $p1 ) {
		$request = WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ) );
		$cancel  = WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), WPCPM_ICS::METHOD_CANCEL );
		$again   = WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), WPCPM_ICS::METHOD_CANCEL );
		$text    = ics_of( $cancel );
		$out     = array( $request !== $cancel, $again === $cancel, false !== strpos( $text, "METHOD:CANCEL\r\n" ), false !== strpos( $text, "STATUS:CANCELLED\r\n" ), false !== strpos( $text, 'UID:wpcpm-call-' . $p1 . '@' ), false !== strpos( $text, "SEQUENCE:7\r\n" ), meta( $p1, '_wpcpm_sevent_state' ) );
		WPCPM_ICS::cleanup( $request[0] );
		WPCPM_ICS::cleanup( $cancel[0] );
		return $out;
	},
	array( true, true, true, true, true, true, 'approved' )
);
ck(
	'a request named for a canceled event is a request, and a method that is neither is the one the state calls for',
	static function () use ( $c2, $p1 ) {
		$request = WPCPM_Sponsor_Event::calendar_file( get_post( $c2 ), WPCPM_ICS::METHOD_REQUEST );
		$other   = WPCPM_Sponsor_Event::calendar_file( get_post( $p1 ), 'PUBLISH' );
		$out     = array( false !== strpos( ics_of( $request ), "METHOD:REQUEST\r\n" ), false !== strpos( ics_of( $other ), "METHOD:REQUEST\r\n" ) );
		WPCPM_ICS::cleanup( $request[0] );
		WPCPM_ICS::cleanup( $other[0] );
		return $out;
	},
	array( true, true )
);

/* ---- the lock ---------------------------------------------------------------------- */

echo "\n=== The lock ===\n";

set_now( utc( '2026-11-20 09:00' ) );

$c3 = attempt( static function () { return WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-10 10:00' ), array( 'places' => 3 ) ), CHAMPION_REP, 'member' ); } );
attempt( static function () use ( $c3 ) { return WPCPM_Sponsor_Event::approve( $c3, MANAGER ); } );

update_option( 'wpcpm_sevent_lock_' . CHAMPION, $GLOBALS['now'] - 120 );
ck(
	'a lock another request holds turns every write away as busy, writes nothing and leaves the lock where it is',
	static function () use ( $c3 ) {
		return array( is_int( $c3 ), code_of( WPCPM_Sponsor_Event::join( $c3, STUDENT ) ), code_of( WPCPM_Sponsor_Event::cancel( $c3, CHAMPION_REP ) ), code_of( WPCPM_Sponsor_Event::change( $c3, event_fields( utc( '2026-12-11 10:00' ) ), CHAMPION_REP ) ), code_of( WPCPM_Sponsor_Event::approve( $c3, MANAGER ) ), WPCPM_Sponsor_Event::joiners( $c3 ), meta( $c3, '_wpcpm_sevent_state' ), get_option( 'wpcpm_sevent_lock_' . CHAMPION ) );
	},
	array( true, 'busy', 'busy', 'busy', 'busy', array(), 'approved', $GLOBALS['now'] - 120 )
);
update_option( 'wpcpm_sevent_lock_' . CHAMPION, $GLOBALS['now'] - 121 );
ck( 'one older than two minutes belonged to a request that died, and is taken over, then released', static function () use ( $c3 ) { return array( WPCPM_Sponsor_Event::join( $c3, STUDENT ), get_option( 'wpcpm_sevent_lock_' . CHAMPION, 'gone' ) ); }, array( true, 'gone' ) );

update_option( 'wpcpm_sevent_lock_' . CHAMPION, $GLOBALS['now'] - 120 );
ck(
	'a leave waits for the lock too: busy, the person stays on it, and the lock is left where it is',
	static function () use ( $c3 ) { return array( code_of( WPCPM_Sponsor_Event::leave( $c3, STUDENT ) ), WPCPM_Sponsor_Event::joiners( $c3 ), get_option( 'wpcpm_sevent_lock_' . CHAMPION ) ); },
	array( 'busy', array( STUDENT ), $GLOBALS['now'] - 120 )
);
delete_option( 'wpcpm_sevent_lock_' . CHAMPION );

// Another request took the lock a moment ago, straight into the table, after this request had read
// the options and found no lock: on a site with a persistent object cache the name then sits on
// this request's list of absent options, and add_option() skips its own look for a name on that
// list and writes over the row, so a lock that asked get_option() whether it was held would be
// taken twice.
update_option( 'wpcpm_sevent_lock_' . CHAMPION, $GLOBALS['now'] - 5 );
$GLOBALS['notoptions'] = array( 'wpcpm_sevent_lock_' . CHAMPION => true );
ck(
	'a request whose own copy of the options says the lock is free, while the table holds another request\'s, is turned away all the same: the lock is read from the table itself, the join is busy, the mentor is not put on the event, and the row is left to the request holding it',
	static function () use ( $c3 ) {
		$joined = code_of( WPCPM_Sponsor_Event::join( $c3, MENTOR ) );
		$seen   = get_option( 'wpcpm_sevent_lock_' . CHAMPION, 'absent' );
		return array( $joined, $seen, WPCPM_Sponsor_Event::joiners( $c3 ), array_key_exists( 'wpcpm_sevent_lock_' . CHAMPION, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ 'wpcpm_sevent_lock_' . CHAMPION ] : 'released' );
	},
	array( 'busy', 'absent', array( STUDENT ), $GLOBALS['now'] - 5 )
);
$GLOBALS['notoptions'] = array();
delete_option( 'wpcpm_sevent_lock_' . CHAMPION );

// The Stale Champion may plan again (its one event was never approved): only the lock stands in
// the way here, the write that decides one event at a time.
update_option( 'wpcpm_sevent_lock_' . STALE, $GLOBALS['now'] - 120 );
ck(
	'and so does a plan: busy, no post written, and the lock left where it is',
	static function () use ( $row ) {
		$before  = count( $GLOBALS['posts'] );
		$allowed = WPCPM_Sponsor_Event::may_plan( STALE, $row( STALE ), utc( '2027-01-10 10:00' ) );
		return array( $allowed, code_of( WPCPM_Sponsor_Event::plan( STALE, event_fields( utc( '2027-01-10 10:00' ) ), MANAGER, 'manager' ) ), count( $GLOBALS['posts'] ) - $before, get_option( 'wpcpm_sevent_lock_' . STALE ) );
	},
	array( '', 'busy', 0, $GLOBALS['now'] - 120 )
);
delete_option( 'wpcpm_sevent_lock_' . STALE );

$s2 = attempt( static function () { return WPCPM_Sponsor_Event::plan( STALE, event_fields( utc( '2027-01-10 10:00' ), array( 'title' => 'Stale again' ) ), MANAGER, 'manager' ); } );

ck(
	'a change of an event still waiting for the program\'s OK tells the host\'s page cache nothing, since the Partners page lists approved events alone',
	static function () use ( $s2 ) {
		$GLOBALS['purged'] = array();
		$done              = WPCPM_Sponsor_Event::change( $s2, event_fields( utc( '2027-01-10 10:00' ), array( 'title' => 'Stale again', 'description' => 'The agenda, added.' ) ), MANAGER );
		return array( is_array( $done ) && ! empty( $done['changed'] ), meta( $s2, '_wpcpm_sevent_state' ), $GLOBALS['purged'] );
	},
	array( true, 'planned', array() )
);

// The note as a browser posts it, its line breaks CRLF: 2,000 typed characters, stored longer, since
// the cleaner writes the "<", and the quote marks and the ampersand after it, as entities. Kept
// whole; one typed character more is refused before the event is opened, and nothing is written.
ck(
	'a return\'s note of 2,000 typed characters with a "<", an "&" and line breaks is kept whole, and one typed character more is refused note_long with nothing written',
	static function () use ( $s2 ) {
		$typed  = str_repeat( 'Kids < 12 learn "blocks" & themes.' . "\n", 57 ) . 'Done.';
		$posted = str_replace( "\n", "\r\n", $typed );
		$read   = static function () use ( $s2 ) { return array( meta( $s2, '_wpcpm_sevent_state' ), meta( $s2, '_wpcpm_sevent_decision' ), meta( $s2, '_wpcpm_sevent_revision' ) ); };
		$before = $read();
		$over   = code_of( WPCPM_Sponsor_Event::return_with_note( $s2, MANAGER, $posted . '!' ) );
		$after  = $read();
		$done   = WPCPM_Sponsor_Event::return_with_note( $s2, MANAGER, $posted );
		$kept   = meta( $s2, '_wpcpm_sevent_decision' )['note'];
		return array( mb_strlen( $typed ), $over, $after === $before, $done, mb_strlen( $kept ) > 2000, WPCPM_Sponsor_Event::typed_length( $kept ), str_replace( "\r\n", "\n", wp_specialchars_decode( $kept, ENT_QUOTES ) ) === $typed );
	},
	array( 2000, 'note_long', true, true, true, 2000, true )
);

// The cleaner writes a reference typed out after a "<", such as "&#9;", in core's own form,
// `&#009;`, two characters longer than it was typed. The note is counted on what was typed, as its
// box counted it (`WPCPM_Typed_Text::box_length()`): the note itself when it is handed as typed, or
// the typing handed beside a cleaned note, as the Events card hands it, so the card and the store
// never part on a note's length. Two Champions with no term of their own plan the two events.
const COUNTED     = 'spoAAAA0000000017'; // Champion, no term: a note handed as typed.
const COUNTED_TWO = 'spoAAAA0000000018'; // Champion, no term: a cleaned note with the typing beside it.

$GLOBALS['index'][ COUNTED ]     = array( 'record_id' => COUNTED, 'name' => 'Counted Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' );
$GLOBALS['index'][ COUNTED_TWO ] = array( 'record_id' => COUNTED_TWO, 'name' => 'Counted Two Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' );

ck(
	'a return\'s note of 2,000 typed characters holding "Kids < 5, see &#9; list" is kept, though the cleaner stores it two characters longer: handed as typed, and handed cleaned with the typing beside it; one typed character more is refused note_long either way',
	static function () {
		$typed = str_pad( 'Kids < 5, see &#9; list. ', 2000, 'x' );
		$got   = array();
		foreach ( array( 'as typed', 'beside' ) as $way ) {
			$id = WPCPM_Sponsor_Event::plan( 'as typed' === $way ? COUNTED : COUNTED_TWO, event_fields( utc( '2027-01-12 10:00' ), array( 'title' => 'Counted ' . $way ) ), MANAGER, 'manager' );
			if ( 'as typed' === $way ) {
				$over = code_of( WPCPM_Sponsor_Event::return_with_note( $id, MANAGER, $typed . 'y' ) );
				$done = WPCPM_Sponsor_Event::return_with_note( $id, MANAGER, $typed );
			} else {
				$over = code_of( WPCPM_Sponsor_Event::return_with_note( $id, MANAGER, sanitize_textarea_field( $typed . 'y' ), $typed . 'y' ) );
				$done = WPCPM_Sponsor_Event::return_with_note( $id, MANAGER, sanitize_textarea_field( $typed ), $typed );
			}
			$made  = is_int( $id ) ? meta( $id, '_wpcpm_sevent_decision' ) : '';
			$kept  = is_array( $made ) && isset( $made['note'] ) ? (string) $made['note'] : '';
			$got[] = array( is_int( $id ), $over, $done, meta( $id, '_wpcpm_sevent_state' ), false !== strpos( $kept, '&#009;' ), WPCPM_Typed_Text::typed_length( $kept ) );
		}
		return $got;
	},
	array( array( true, 'note_long', true, 'cancelled', true, 2002 ), array( true, 'note_long', true, 'cancelled', true, 2002 ) )
);

echo "\n=== A sponsor that left offers nothing ===\n";

ck( 'while the Champion is in the program, its event is listed and open to join', static function () use ( $c3 ) { return array( in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( MANAGER ) ), true ), in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( PAUSED ) ), true ) ); }, array( true, true ) );

$GLOBALS['index'][ CHAMPION ]['status'] = 'Paused';
ck(
	'set to Paused, it offers nothing: its event leaves the lists of everybody not on it and takes no join, and its own card still has it',
	static function () use ( $c3 ) { return array( in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( MANAGER ) ), true ), in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( PAUSED ) ), true ), code_of( WPCPM_Sponsor_Event::join( $c3, PAUSED ) ), ids( WPCPM_Sponsor_Event::upcoming_of( CHAMPION ) ), WPCPM_Sponsor_Event::joiners( $c3 ) ); },
	array( false, false, 'not_open', array( $c3 ), array( STUDENT ) )
);
ck( 'but the program\'s own list keeps it: approved_upcoming() asks nothing of the sponsor, so a program manager can still call it off', static function () use ( $c3 ) { return in_array( $c3, ids( WPCPM_Sponsor_Event::approved_upcoming() ), true ); }, true );
ck(
	'and so does the list of the one person on it, so they can leave it; the others\' lists do not, and the event still takes no join',
	static function () use ( $c3 ) { return array( in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ), true ), in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( MENTOR ) ), true ), code_of( WPCPM_Sponsor_Event::join( $c3, MENTOR ) ) ); },
	array( true, false, 'not_open' )
);
$GLOBALS['index'][ CHAMPION ]['status'] = 'Approved';
ck( 'Approved again, the event is back, and the Paused student joins it', static function () use ( $c3 ) { return array( in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( PAUSED ) ), true ), WPCPM_Sponsor_Event::join( $c3, PAUSED ), WPCPM_Sponsor_Event::joiners( $c3 ) ); }, array( true, true, array( STUDENT, PAUSED ) ) );
ck(
	'a person on an event who has left the program\'s audiences since, as a graduate has, keeps it on their list to leave it, and is offered nothing else',
	static function () use ( $c3 ) {
		add_post_meta( $c3, '_wpcpm_sevent_person', GRADUATE );
		$kept = ids( WPCPM_Sponsor_Event::open_for( GRADUATE ) );
		delete_post_meta( $c3, '_wpcpm_sevent_person', GRADUATE );
		return array( WPCPM_Sponsor_Event::audience_of( GRADUATE ), $kept, ids( WPCPM_Sponsor_Event::open_for( GRADUATE ) ), WPCPM_Sponsor_Event::joiners( $c3 ) );
	},
	array( '', array( $c3 ), array(), array( STUDENT, PAUSED ) )
);

$champion_row = $GLOBALS['index'][ CHAMPION ];
unset( $GLOBALS['index'][ CHAMPION ] );
ck( 'a sponsor the store no longer holds offers nothing either, and the program still sees its event', static function () use ( $c3 ) { return array( in_array( $c3, ids( WPCPM_Sponsor_Event::open_for( MANAGER ) ), true ), code_of( WPCPM_Sponsor_Event::join( $c3, MENTOR ) ), in_array( $c3, ids( WPCPM_Sponsor_Event::approved_upcoming() ), true ) ); }, array( false, 'not_open', true ) );
$GLOBALS['index'][ CHAMPION ] = $champion_row;

$GLOBALS['mail']   = array();
$GLOBALS['cached'] = array();

/** What a change of the Champion's event could have written: the post, its meta, the trail, the mail and the schedule. */
$c3_now = static function () use ( $c3 ) { return array( serialize( get_post( $c3 ) ), $GLOBALS['pmeta'][ $c3 ], count( $GLOBALS['audit'] ), count( $GLOBALS['all_mail'] ), $GLOBALS['cron'] ); };

ck(
	'the places alone may come down to exactly the people on it: written, the version raised, the row on the trail, and nobody on it told, since the places are in nobody\'s calendar',
	static function () use ( $c3 ) {
		$was  = (int) meta( $c3, '_wpcpm_sevent_revision' );
		$rows = count( $GLOBALS['audit'] );
		return array( WPCPM_Sponsor_Event::change( $c3, event_fields( utc( '2026-12-10 10:00' ), array( 'places' => 2 ) ), CHAMPION_REP ), meta( $c3, '_wpcpm_sevent_places' ), (int) meta( $c3, '_wpcpm_sevent_revision' ) - $was, count( $GLOBALS['audit'] ) - $rows, mailed_to(), get_post_meta( $c3, '_wpcpm_sevent_pending', false ), $GLOBALS['cron'] );
	},
	array( array( 'changed' => true, 'told' => 0, 'pending' => 0 ), '2', 1, 1, array(), array(), array() )
);
ck(
	'and so is the description alone: written, the version raised, and nobody on it told',
	static function () use ( $c3 ) {
		$was = (int) meta( $c3, '_wpcpm_sevent_revision' );
		return array( WPCPM_Sponsor_Event::change( $c3, event_fields( utc( '2026-12-10 10:00' ), array( 'places' => 2, 'description' => 'Slides are posted the day before.' ) ), CHAMPION_REP ), get_post( $c3 )->post_content, (int) meta( $c3, '_wpcpm_sevent_revision' ) - $was, mailed_to(), get_post_meta( $c3, '_wpcpm_sevent_pending', false ), $GLOBALS['cron'] );
	},
	array( array( 'changed' => true, 'told' => 0, 'pending' => 0 ), 'Slides are posted the day before.', 1, array(), array(), array() )
);
ck(
	'a title WordPress stored with its ampersand as an entity, handed back decoded as the Change form hands a title back, is no change: nothing written, nobody mailed',
	static function () use ( $c3, $c3_now ) {
		$GLOBALS['posts'][ $c3 ]->post_title = 'Q&amp;A with the team';
		$before = $c3_now();
		$answer = WPCPM_Sponsor_Event::change( $c3, event_fields( utc( '2026-12-10 10:00' ), array( 'places' => 2, 'description' => 'Slides are posted the day before.', 'title' => 'Q&A with the team' ) ), CHAMPION_REP );
		$same   = $c3_now() === $before;
		$GLOBALS['posts'][ $c3 ]->post_title = 'A co-hosted webinar';
		return array( $answer, $same );
	},
	array( array( 'changed' => false, 'told' => 0, 'pending' => 0 ), true )
);
ck(
	'and so is a title typed with a named entity, stored as typed and handed back as the character the browser shows: nothing written, nobody mailed',
	static function () use ( $c3, $c3_now ) {
		$GLOBALS['posts'][ $c3 ]->post_title = '&copy; 2026 webinar';
		$before = $c3_now();
		$answer = WPCPM_Sponsor_Event::change( $c3, event_fields( utc( '2026-12-10 10:00' ), array( 'places' => 2, 'description' => 'Slides are posted the day before.', 'title' => "\u{00A9} 2026 webinar" ) ), CHAMPION_REP );
		$same   = $c3_now() === $before;
		$GLOBALS['posts'][ $c3 ]->post_title = 'A co-hosted webinar';
		return array( $answer, $same );
	},
	array( array( 'changed' => false, 'told' => 0, 'pending' => 0 ), true )
);
ck(
	'a change of the time tells both people on it in the press, and the drain was on the clock before the first send, so a press that died part way would leave the rest to it',
	static function () use ( $c3 ) { return array( WPCPM_Sponsor_Event::change( $c3, event_fields( utc( '2026-12-10 11:00' ), array( 'places' => 2, 'description' => 'Slides are posted the day before.' ) ), CHAMPION_REP ), meta( $c3, '_wpcpm_sevent_start' ), mailed_to(), get_post_meta( $c3, '_wpcpm_sevent_pending', false ), $GLOBALS['cron'], array_values( array_unique( array_column( array_slice( $GLOBALS['send_log'], -2 ), 'drain' ) ) ) ); },
	array( array( 'changed' => true, 'told' => 2, 'pending' => 0 ), (string) utc( '2026-12-10 11:00' ), array( 'sam.current@people.example', 'paula.paused@people.example' ), array(), array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 60 ) ), array( true ) )
);
ck(
	'the change\'s mail says what the event is now, on the person\'s clock, how long, and the call link, with the file that replaces the one they had',
	static function () { $mail = mail_for( 'sam.current@people.example' ); return array( $mail['subject'], $mail['body'], $GLOBALS['cached'][0] ); },
	array(
		'[WordPress Education] Event changed: A co-hosted webinar',
		"An event you joined has changed. Here is what it is now:\n\n"
			. "Title: A co-hosted webinar\nSponsor: Champion Example\nWhen: December 10, 2026 6:00 am to 7:00 am (America / New York)\nLength: 60 minutes\nJoin the call: https://call.example/room-0\n\n"
			. 'The calendar file attached replaces the one you had.',
		'cache ' . STUDENT . ',' . PAUSED,
	)
);
ck(
	'each carries a request at the version the change raised, so it replaces the entry the join put in their calendar, and names the person it went to as its attendee',
	static function () use ( $c3 ) {
		$out = array();
		foreach ( $GLOBALS['mail'] as $mail ) {
			$ics   = isset( $mail['files'][0]['ics'] ) ? (string) $mail['files'][0]['ics'] : '';
			$out[] = array( false !== strpos( $ics, "METHOD:REQUEST\r\n" ), false !== strpos( $ics, "STATUS:CONFIRMED\r\n" ), false !== strpos( $ics, 'SEQUENCE:' . meta( $c3, '_wpcpm_sevent_revision' ) . "\r\n" ), false !== strpos( $ics, 'Join the call: https://call.example/room-0' ), false !== strpos( $ics, 'RSVP=FALSE:mailto:' . $mail['to'] . "\r\n" ) );
		}
		return $out;
	},
	array( array( true, true, true, true, true ), array( true, true, true, true, true ) )
);

$c3_change_mails = attempt( static function () { return $GLOBALS['mail']; } );

ck(
	'the change\'s first mail left once the change\'s row was on the trail and the sponsor\'s lock was given back',
	static function () use ( $c3_change_mails, $c3 ) { return isset( $c3_change_mails[0]['moment'] ) ? $c3_change_mails[0]['moment'] : 'no mail'; },
	array( 'row' => array( 'sponsor_event_changed', id_text( $c3 ) ), 'locks' => array() )
);
$GLOBALS['mail'] = array();

ck(
	'a program manager cancels it: the row says manager, and names the manager, and the two on it are told',
	static function () use ( $c3 ) { $done = WPCPM_Sponsor_Event::cancel( $c3, MANAGER ); $last = last_row(); return array( $done, $last['kind'], $last['ground'], $last['actor'], $last['subject'], mailed_to() ); },
	array( array( 'told' => 2, 'pending' => 0 ), 'sponsor_event_cancelled', 'manager', MANAGER, id_text( $c3 ), array( 'sam.current@people.example', 'paula.paused@people.example' ) )
);

$c3_cancel_mails = attempt( static function () { return $GLOBALS['mail']; } );

echo "\n=== No approval for a sponsor that no longer offers events ===\n";

// A Champion plans while it is in the program. Then, in turn, it is set to Paused, a manager ends its
// term on a day already past, and it leaves the store; each time a program manager tries to approve.
$standing = attempt( static function () { return WPCPM_Sponsor_Event::plan( STANDING, event_fields( utc( '2026-12-15 10:00' ), array( 'title' => 'Standing webinar' ) ), MANAGER, 'manager' ); } );

/** What a refused approval leaves as it was: the event's state, decision and version, the trail, the lock. */
$standing_now    = static function () use ( $standing ) { return array( meta( $standing, '_wpcpm_sevent_state' ), meta( $standing, '_wpcpm_sevent_decision' ), meta( $standing, '_wpcpm_sevent_revision' ), count( $GLOBALS['audit'] ), get_option( 'wpcpm_sevent_lock_' . STANDING, 'none' ) ); };
$standing_before = attempt( $standing_now );

ck(
	'the sponsor offers events while it plans, and offers_events() says so',
	static function () use ( $standing, $standing_before ) { return array( is_int( $standing ), WPCPM_Sponsor_Event::offers_events( STANDING ), $standing_before ); },
	array( true, true, array( 'planned', '', '0', count( $GLOBALS['audit'] ), 'none' ) )
);
ck(
	'set to Paused, its waiting event is not approved: no_tier, nothing written, no row, the lock given back',
	static function () use ( $standing, $standing_now, $standing_before ) {
		$GLOBALS['index'][ STANDING ]['status'] = 'Paused';
		$result                                 = array( WPCPM_Sponsor_Event::offers_events( STANDING ), code_of( WPCPM_Sponsor_Event::approve( $standing, MANAGER ) ), $standing_now() === $standing_before );
		$GLOBALS['index'][ STANDING ]['status'] = 'Approved';
		return $result;
	},
	array( false, 'no_tier', true )
);
ck(
	'its term ended early, on a day already past, the same: the tier it holds today has no events',
	static function () use ( $standing, $standing_now, $standing_before ) {
		$GLOBALS['index'][ STANDING ]['tier_until'] = '2026-11-19';
		$result                                     = array( WPCPM_Sponsor_Event::offers_events( STANDING ), code_of( WPCPM_Sponsor_Event::approve( $standing, MANAGER ) ), $standing_now() === $standing_before );
		$GLOBALS['index'][ STANDING ]['tier_until'] = '2027-06-30';
		return $result;
	},
	array( false, 'no_tier', true )
);
ck(
	'gone from the store, the same',
	static function () use ( $standing, $standing_now, $standing_before ) {
		$row = $GLOBALS['index'][ STANDING ];
		unset( $GLOBALS['index'][ STANDING ] );
		$result                        = array( WPCPM_Sponsor_Event::offers_events( STANDING ), code_of( WPCPM_Sponsor_Event::approve( $standing, MANAGER ) ), $standing_now() === $standing_before );
		$GLOBALS['index'][ STANDING ] = $row;
		return $result;
	},
	array( false, 'no_tier', true )
);
ck(
	'back in the program, it is approved, and the lock given back',
	static function () use ( $standing ) { return array( WPCPM_Sponsor_Event::approve( $standing, MANAGER ), meta( $standing, '_wpcpm_sevent_state' ), last_row()['kind'], get_option( 'wpcpm_sevent_lock_' . STANDING, 'none' ) ); },
	array( true, 'approved', 'sponsor_event_approved', 'none' )
);
// A return is the program's answer to a plan that waits. The store asks it itself, under the lock
// on the fresh read: a return tells nobody on the event and sends the members no file to take it
// off their calendars, so an approved event is called off with a cancel, which does both, however
// the page that pressed Return was drawn.
ck(
	'a return is for a waiting event alone: on the approved one, with somebody on it now, the store itself answers not_planned, writes nothing, mails nobody and gives the lock back',
	static function () use ( $standing ) {
		$joined = WPCPM_Sponsor_Event::join( $standing, STUDENT );
		$state  = static function () use ( $standing ) { return array( meta( $standing, '_wpcpm_sevent_state' ), meta( $standing, '_wpcpm_sevent_decision' ), meta( $standing, '_wpcpm_sevent_revision' ), WPCPM_Sponsor_Event::joiners( $standing ), count( $GLOBALS['audit'] ), count( $GLOBALS['all_mail'] ) ); };
		$before = $state();
		$code   = code_of( WPCPM_Sponsor_Event::return_with_note( $standing, MANAGER, 'Please move it to the spring.' ) );
		return array( $joined, $code, $state() === $before, meta( $standing, '_wpcpm_sevent_state' ), get_option( 'wpcpm_sevent_lock_' . STANDING, 'none' ) );
	},
	array( true, 'not_planned', true, 'approved', 'none' )
);
ck( 'a value that is no sponsor key offers nothing', static function () { return array( WPCPM_Sponsor_Event::offers_events( '' ), WPCPM_Sponsor_Event::offers_events( 'not a key' ) ); }, array( false, false ) );
ck(
	'offers_events() asked on a day reads the sponsor\'s tier on that day, as the Partners page asks it: the Partner offers events on its term\'s last day and not on the day after, and asked with no day it reads today',
	static function () { return array( WPCPM_Sponsor_Event::offers_events( PARTNER, '2027-01-31' ), WPCPM_Sponsor_Event::offers_events( PARTNER, '2027-02-01' ), WPCPM_Sponsor_Event::offers_events( PARTNER ), WPCPM_Sponsor_Event::offers_events( 'not a key', '2027-01-31' ) ); },
	array( true, false, true, false )
);

echo "\n=== At a term's lapse ===\n";

set_now( utc( '2027-01-31 12:00' ) );

$lapse = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( LAPSING, event_fields( utc( '2027-01-31 20:00' ), array( 'minutes' => 480, 'title' => 'Across the last midnight' ) ), MANAGER, 'manager' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

ck( 'on the term\'s last day the event is planned for that evening, approved and listed', static function () use ( $lapse ) { return array( is_int( $lapse ), in_array( $lapse, ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ), true ) ); }, array( true, true ) );

set_now( utc( '2027-02-01 00:30' ) );

ck(
	'past the term\'s last midnight the Partner has lapsed: its event, still running, leaves every list and takes no join, and its card still has it',
	static function () use ( $lapse ) { return array( in_array( $lapse, ids( WPCPM_Sponsor_Event::open_for( STUDENT ) ), true ), code_of( WPCPM_Sponsor_Event::join( $lapse, STUDENT ) ), ids( WPCPM_Sponsor_Event::upcoming_of( LAPSING ) ) ); },
	array( false, 'not_open', array( $lapse ) )
);
ck(
	'and a change may not move it past the term it was planned in: past_term, and nothing moves',
	static function () use ( $lapse ) {
		$before = array( meta( $lapse, '_wpcpm_sevent_start' ), meta( $lapse, '_wpcpm_sevent_state' ), meta( $lapse, '_wpcpm_sevent_revision' ) );
		$code   = code_of( WPCPM_Sponsor_Event::change( $lapse, event_fields( utc( '2027-09-01 10:00' ), array( 'minutes' => 480, 'title' => 'Across the last midnight' ) ), MANAGER ) );
		return array( $code, array( meta( $lapse, '_wpcpm_sevent_start' ), meta( $lapse, '_wpcpm_sevent_state' ), meta( $lapse, '_wpcpm_sevent_revision' ) ) === $before, meta( $lapse, '_wpcpm_sevent_state' ) );
	},
	array( 'past_term', true, 'approved' )
);

echo "\n=== A renewal ===\n";

set_now( utc( '2027-01-10 12:00' ) );

seed_event( RENEWED, 'approved', utc( '2026-12-01 10:00' ), 60, 'Held in the first term' );

ck( 'the event held in the running term spends it', static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( RENEWED, $row( RENEWED ), utc( '2027-01-20 10:00' ) ); }, 'term_used' );

// Renewed before the term ends: the Sponsors screen moves the last day a year on, and the term that
// runs is the year ending there, which the event held in December is no part of.
$GLOBALS['index'][ RENEWED ]['tier_until'] = '2028-01-31';

ck( 'renewed, the term that runs is the next one, and the event held in the one before spends nothing of it', static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( RENEWED, $row( RENEWED ), utc( '2027-03-01 10:00' ) ); }, '' );

echo "\n=== The term as the row records it ===\n";

// The same renewal as the Sponsors screen's Edit save writes it: the day moved on, and the term it
// moved off kept on the row as owed its report. That term still runs until 31 January, and its
// event was held in December, so a day before 1 February is a day of a term already spent.
$GLOBALS['index'][ RENEWED ]['report_owed'] = attempt(
	static function () {
		return WPCPM_Sponsor_Report::owed_after_save( RENEWED, array_merge( $GLOBALS['index'][ RENEWED ], array( 'tier_until' => '2027-01-31' ) ), $GLOBALS['index'][ RENEWED ] );
	}
);

ck( 'the save kept the term it moved off, the Partner year to 31 January 2027, as owed its report', static function () use ( $row ) { return WPCPM_Sponsor_Report::owed_of( $row( RENEWED ) ); }, array( 'tier' => 'partner', 'until' => '2027-01-31', 'funded_line' => '' ) );
ck( 'term_from(): the term as the row records it starts the day after the term the renewal moved off', static function () use ( $row ) { return WPCPM_Sponsor_Event::term_from( $row( RENEWED ) ); }, '2027-02-01' );
ck(
	'a start before that day is in the term that still runs, which held its event: term_used, to its last hour; the renewed term is free from its first day, and so is the question with no start',
	static function () use ( $row ) {
		return array(
			WPCPM_Sponsor_Event::may_plan( RENEWED, $row( RENEWED ), utc( '2027-01-25 10:00' ) ),
			WPCPM_Sponsor_Event::may_plan( RENEWED, $row( RENEWED ), utc( '2027-01-31 23:00' ) ),
			WPCPM_Sponsor_Event::may_plan( RENEWED, $row( RENEWED ), utc( '2027-02-01 10:00' ) ),
			WPCPM_Sponsor_Event::may_plan( RENEWED, $row( RENEWED ), utc( '2027-03-10 15:00' ) ),
			WPCPM_Sponsor_Event::may_plan( RENEWED, $row( RENEWED ), 0 ),
		);
	},
	array( 'term_used', 'term_used', '', '', '' )
);
ck(
	'and plan() refuses a second event in that term the same way, writing nothing',
	static function () {
		$before  = count( $GLOBALS['posts'] );
		$planned = WPCPM_Sponsor_Event::plan( RENEWED, event_fields( utc( '2027-01-25 10:00' ), array( 'title' => 'A second event in a spent term' ) ), MANAGER, 'manager' );
		$wrote   = count( $GLOBALS['posts'] ) - $before;
		if ( is_int( $planned ) ) { wp_delete_post( $planned, true ); }
		return array( code_of( $planned ), $wrote );
	},
	array( 'term_used', 0 )
);

$next_term = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( RENEWED, event_fields( utc( '2027-03-10 15:00' ), array( 'title' => 'Planned for the renewed term' ) ), MANAGER, 'manager' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

ck( 'an event for the renewed term is planned and approved', static function () use ( $next_term ) { return array( is_int( $next_term ), meta( $next_term, '_wpcpm_sevent_state' ) ); }, array( true, 'approved' ) );
ck(
	'moved back to 25 January, out of its term into the one before, it is refused before_term, in words that name the term\'s first day and the site\'s clock, and nothing moves',
	static function () use ( $next_term ) {
		$before  = array( meta( $next_term, '_wpcpm_sevent_start' ), meta( $next_term, '_wpcpm_sevent_revision' ), count( $GLOBALS['audit'] ) );
		$refused = WPCPM_Sponsor_Event::change( $next_term, event_fields( utc( '2027-01-25 10:00' ), array( 'title' => 'Planned for the renewed term' ) ), MANAGER );
		return array( code_of( $refused ), is_wp_error( $refused ) ? $refused->get_error_message() : '', array( meta( $next_term, '_wpcpm_sevent_start' ), meta( $next_term, '_wpcpm_sevent_revision' ), count( $GLOBALS['audit'] ) ) === $before );
	},
	array( 'before_term', 'Choose a start on or after February 1, 2027, the first day of your current sponsorship term on the site\'s clock (UTC).', true )
);
ck( 'its term\'s first day is its own: moved to 1 February it goes through', static function () use ( $next_term ) { return array( WPCPM_Sponsor_Event::change( $next_term, event_fields( utc( '2027-02-01 10:00' ), array( 'title' => 'Planned for the renewed term' ) ), MANAGER ), meta( $next_term, '_wpcpm_sevent_start' ) ); }, array( array( 'changed' => true, 'told' => 0, 'pending' => 0 ), (string) utc( '2027-02-01 10:00' ) ) );

// Renewed early with no event in the term that still runs: that term's event is still to plan, and
// once planned it belongs to that term, so a change inside it goes through.
const EARLY = 'spoAAAA0000000010'; // Partner, the term runs to 31 January 2027, renewed early with no event in it.

$GLOBALS['index'][ EARLY ] = array( 'record_id' => EARLY, 'name' => 'Early Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31' );
$GLOBALS['index'][ EARLY ] = array_merge(
	$GLOBALS['index'][ EARLY ],
	array(
		'tier_until'  => '2028-01-31',
		'report_owed' => attempt( static function () { return WPCPM_Sponsor_Report::owed_after_save( EARLY, $GLOBALS['index'][ EARLY ], array_merge( $GLOBALS['index'][ EARLY ], array( 'tier_until' => '2028-01-31' ) ) ); } ),
	)
);

ck( 'renewed early with no event yet, the term that still runs keeps its own: 25 January is taken', static function () use ( $row ) { return array( WPCPM_Sponsor_Event::term_from( $row( EARLY ) ), WPCPM_Sponsor_Event::may_plan( EARLY, $row( EARLY ), utc( '2027-01-25 10:00' ) ) ); }, array( '2027-02-01', '' ) );

$old_term = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( EARLY, event_fields( utc( '2027-01-25 10:00' ), array( 'title' => 'The last event of a term' ) ), MANAGER, 'manager' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

ck(
	'that event is its term\'s own: moved inside the term, or changed and kept on its day, it goes through',
	static function () use ( $old_term ) {
		return array(
			WPCPM_Sponsor_Event::change( $old_term, event_fields( utc( '2027-01-27 10:00' ), array( 'title' => 'The last event of a term' ) ), MANAGER ),
			WPCPM_Sponsor_Event::change( $old_term, event_fields( utc( '2027-01-27 10:00' ), array( 'title' => 'The last event of a term, retitled' ) ), MANAGER ),
			meta( $old_term, '_wpcpm_sevent_start' ),
		);
	},
	array( array( 'changed' => true, 'told' => 0, 'pending' => 0 ), array( 'changed' => true, 'told' => 0, 'pending' => 0 ), (string) utc( '2027-01-27 10:00' ) )
);
ck( 'moved on into the renewed term it goes through, since the renewal moved the last day on and the bound with it', static function () use ( $old_term ) { return WPCPM_Sponsor_Event::change( $old_term, event_fields( utc( '2027-03-10 15:00' ), array( 'title' => 'The last event of a term, retitled' ) ), MANAGER ); }, array( 'changed' => true, 'told' => 0, 'pending' => 0 ) );
ck( 'and once there it is that term\'s event: moved back before its first day, before_term', static function () use ( $old_term ) { return array( code_of( WPCPM_Sponsor_Event::change( $old_term, event_fields( utc( '2027-01-27 10:00' ), array( 'title' => 'The last event of a term, retitled' ) ), MANAGER ) ), meta( $old_term, '_wpcpm_sevent_start' ) ); }, array( 'before_term', (string) utc( '2027-03-10 15:00' ) ) );

// A manager drafted the term's report before renewing: the save then owes nothing, and the report's
// own last day is where the term before ended.
const DRAFTED = 'spoAAAA0000000011'; // Partner, the term runs to 31 January 2027, its report drafted by hand before the renewal.

$GLOBALS['index'][ DRAFTED ] = array( 'record_id' => DRAFTED, 'name' => 'Drafted Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31' );
seed_event( DRAFTED, 'approved', utc( '2026-12-01 10:00' ), 60, 'Held before the report was drafted' );
$drafted_report = wp_insert_post( array( 'post_type' => 'wpcpm_sponsor_report', 'post_status' => 'private', 'post_title' => 'Drafted Example, February 2026 to January 2027' ) );
update_post_meta( $drafted_report, '_wpcpm_sreport_sponsor', DRAFTED );
update_post_meta( $drafted_report, '_wpcpm_sreport_until', '2027-01-31' );
$drafted_owed = attempt( static function () { return WPCPM_Sponsor_Report::owed_after_save( DRAFTED, $GLOBALS['index'][ DRAFTED ], array_merge( $GLOBALS['index'][ DRAFTED ], array( 'tier_until' => '2028-01-31' ) ) ); } );
$GLOBALS['index'][ DRAFTED ]['tier_until'] = '2028-01-31';

ck(
	'a report drafted by hand before the renewal leaves no term owed, and its last day bounds the term the same way',
	static function () use ( $row, $drafted_owed ) {
		return array(
			$drafted_owed,
			WPCPM_Sponsor_Event::term_from( $row( DRAFTED ) ),
			WPCPM_Sponsor_Event::may_plan( DRAFTED, $row( DRAFTED ), utc( '2027-01-25 10:00' ) ),
			WPCPM_Sponsor_Event::may_plan( DRAFTED, $row( DRAFTED ), utc( '2027-03-10 15:00' ) ),
		);
	},
	array( array(), '2027-02-01', 'term_used', '' )
);

// Renewed after the term ended, to the calendar year: the run drafted the term that ended on 31
// January and stamped the row, so the row records the renewed term's first day as 1 February. The
// term is still the design's twelve months ending on its last day, the calendar year, and the event
// held on 5 January is dated in them: it spends the term, as the annual report for it counts it.
const SHORTER = 'spoAAAA0000000012'; // Champion, the term ran to 31 January 2027, renewed after it to 31 December 2027.

$GLOBALS['index'][ SHORTER ] = array( 'record_id' => SHORTER, 'name' => 'Shorter Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-12-31', 'report_drafted_for' => '2027-01-31' );
seed_event( SHORTER, 'approved', utc( '2027-01-05 10:00' ), 60, 'Held in the term that ended' );

ck( 'a term renewed after it ended is still the twelve months ending on its last day: the event of the term before, dated in them, spends it, as the annual report counts it', static function () use ( $row ) { return array( WPCPM_Sponsor_Event::term_from( $row( SHORTER ) ), WPCPM_Sponsor_Event::may_plan( SHORTER, $row( SHORTER ), utc( '2027-06-01 15:00' ), '2027-02-03' ) ); }, array( '2027-02-01', 'term_used' ) );

// The last end the row records can be older than the twelve months ending on the last day, when a
// term between was marked no renewal: the running term is still those twelve months, so an event
// of the unrecorded term before them spends nothing.
const OLDEND = 'spoAAAA0000000013'; // Champion, the term runs to 31 January 2028, the last end recorded two years back.

$GLOBALS['index'][ OLDEND ] = array( 'record_id' => OLDEND, 'name' => 'Old End Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2028-01-31', 'report_drafted_for' => '2025-01-31' );
seed_event( OLDEND, 'approved', utc( '2026-12-01 10:00' ), 60, 'Held in a term the row records no end for' );

ck( 'a recorded end older than the twelve months widens nothing: the running term is still its twelve months, which hold no event', static function () use ( $row ) { return array( WPCPM_Sponsor_Event::term_from( $row( OLDEND ) ), WPCPM_Sponsor_Event::may_plan( OLDEND, $row( OLDEND ), utc( '2027-03-10 15:00' ) ) ); }, array( '2025-02-01', '' ) );

ck(
	'term_from(): nothing with no last day or no end recorded before it; the day after the latest end recorded before it, owed or drafted; an end on or after the last day, a day moved earlier, and a day that does not exist are no end of the term before',
	static function () {
		return array(
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '' ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-01-31' ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-01-31', 'report_owed' => array( 'tier' => 'partner', 'until' => '2027-01-31' ) ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-01-31', 'report_drafted_for' => '2027-01-31' ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-01-31', 'report_owed' => array( 'tier' => 'partner', 'until' => '2026-01-31' ), 'report_drafted_for' => '2027-01-31' ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2027-03-31', 'report_owed' => array( 'tier' => 'supporter', 'until' => '2027-06-30' ) ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-01-31', 'report_drafted_for' => '2028-01-31' ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-01-31', 'report_drafted_for' => '2027-02-30' ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-02-29', 'report_drafted_for' => '2027-02-28' ) ),
			WPCPM_Sponsor_Event::term_from( array( 'tier_until' => '2028-12-31', 'report_drafted_for' => '2027-12-31' ) ),
		);
	},
	array( '', '', '2027-02-01', '2027-02-01', '2027-02-01', '', '', '', '2027-03-01', '2028-01-01' )
);

/* ---- extra events for one term ------------------------------------------------------ */

echo "\n=== Extra events for one term ===\n";

// The Sponsors screen's Tier box keeps a number on the row with the last day of the term it was
// granted for: that term counts its approved events against the tier's one and that many more,
// while one event at a time still comes first. Each sponsor here is a row of its own, planned for
// through the store, and every event it holds has taken place or is canceled by the section's end,
// so no list after it offers one.
const EXTRA   = 'spoAAAA0000000014'; // Champion, the term runs to 31 March 2027, one more event granted for it.
const REGRANT = 'spoAAAA0000000015'; // Partner, the term runs to 31 January 2027, one more granted for it, renewed early.
const REACH   = 'spoAAAA0000000016'; // Partner, the term runs to 31 January 2027, renewed early, one more granted for the renewed term.

$extra_was = $GLOBALS['now'];
set_now( utc( '2027-01-10 12:00' ) );

ck(
	'extra_events(): the number the row\'s allowance holds while it names the last day asked about, written as a number or its digits; none for another day, a day that is not one, no allowance, a count of none or below, or a value that is no pair',
	static function () {
		$row = array( 'tier_until' => '2027-03-31', 'event_allowance' => array( 'until' => '2027-03-31', 'extra' => 2 ) );
		return array(
			WPCPM_Sponsor_Event::extra_events( $row, '2027-03-31' ),
			WPCPM_Sponsor_Event::extra_events( array( 'event_allowance' => array( 'until' => '2027-03-31', 'extra' => '3' ) ), '2027-03-31' ),
			WPCPM_Sponsor_Event::extra_events( $row, '2028-03-31' ),
			WPCPM_Sponsor_Event::extra_events( $row, '' ),
			WPCPM_Sponsor_Event::extra_events( array( 'event_allowance' => array( 'until' => 'soon', 'extra' => 2 ) ), 'soon' ),
			WPCPM_Sponsor_Event::extra_events( array( 'tier_until' => '2027-03-31' ), '2027-03-31' ),
			WPCPM_Sponsor_Event::extra_events( array( 'event_allowance' => array( 'until' => '2027-03-31', 'extra' => 0 ) ), '2027-03-31' ),
			WPCPM_Sponsor_Event::extra_events( array( 'event_allowance' => array( 'until' => '2027-03-31', 'extra' => -2 ) ), '2027-03-31' ),
			WPCPM_Sponsor_Event::extra_events( array( 'event_allowance' => '2027-03-31 2' ), '2027-03-31' ),
		);
	},
	array( 2, 3, 0, 0, 0, 0, 0, 0, 0 )
);

$GLOBALS['index'][ EXTRA ] = array( 'record_id' => EXTRA, 'name' => 'Extra Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-03-31', 'event_allowance' => array( 'until' => '2027-03-31', 'extra' => 1 ) );
seed_event( EXTRA, 'approved', utc( '2026-12-01 10:00' ), 60, 'The term\'s own event' );

$extra_asked  = attempt( static function () use ( $row ) { return array( WPCPM_Sponsor_Event::may_plan( EXTRA, $row( EXTRA ), 0 ), WPCPM_Sponsor_Event::may_plan( EXTRA, $row( EXTRA ), utc( '2027-02-10 15:00' ) ) ); } );
$extra_second = attempt( static function () { return WPCPM_Sponsor_Event::plan( EXTRA, event_fields( utc( '2027-02-10 15:00' ) ), MANAGER, 'manager' ); } );

ck(
	'one more event granted for the term: with the term\'s own event held, a second is taken, asked with no start or with one, and plan() writes it',
	static function () use ( $extra_asked, $extra_second ) { return array( $extra_asked, is_int( $extra_second ) ); },
	array( array( '', '' ), true )
);
ck(
	'while the second is still to take place, one event at a time comes first: one_open, before the term is counted',
	static function () use ( $row ) { return array( WPCPM_Sponsor_Event::may_plan( EXTRA, $row( EXTRA ), 0 ), WPCPM_Sponsor_Event::may_plan( EXTRA, $row( EXTRA ), utc( '2027-03-20 15:00' ) ) ); },
	array( 'one_open', 'one_open' )
);

$extra_third = attempt(
	static function () use ( $extra_second ) {
		WPCPM_Sponsor_Event::approve( $extra_second, MANAGER );
		set_now( utc( '2027-02-11 09:00' ) );
		$before  = count( $GLOBALS['posts'] );
		$planned = WPCPM_Sponsor_Event::plan( EXTRA, event_fields( utc( '2027-03-20 15:00' ) ), MANAGER, 'manager' );
		$wrote   = count( $GLOBALS['posts'] ) - $before;
		if ( is_int( $planned ) ) { wp_delete_post( $planned, true ); }
		return array( code_of( $planned ), $wrote );
	}
);

ck(
	'once the second has been approved and has taken place, the term is spent: a third is term_used, asked with no start or with one, and plan() refuses it, writing nothing',
	static function () use ( $row, $extra_second, $extra_third ) {
		return array( is_int( $extra_second ) ? meta( $extra_second, '_wpcpm_sevent_state' ) : 'no second event', WPCPM_Sponsor_Event::may_plan( EXTRA, $row( EXTRA ), 0 ), WPCPM_Sponsor_Event::may_plan( EXTRA, $row( EXTRA ), utc( '2027-03-20 15:00' ) ), $extra_third );
	},
	array( 'approved', 'term_used', 'term_used', array( 'term_used', 0 ) )
);
ck(
	'and it is refused in the planner\'s words for a term granted more than the tier\'s one: its events for this term have taken place already',
	static function () {
		$refused = WPCPM_Sponsor_Event::plan( EXTRA, event_fields( utc( '2027-03-20 15:00' ) ), MANAGER, 'manager' );
		return array( code_of( $refused ), is_wp_error( $refused ) ? $refused->get_error_message() : '' );
	},
	array( 'term_used', 'Your events for this term have taken place already.' )
);

// Renewed early, before the term it was granted for ends: the Edit save moves the day on and keeps
// the term it moved off owed its report, and the number stays with the day it was granted for. That
// term still runs to its own last day and keeps its number for the days it has left; the renewed
// term counts it for nothing, until the Tier box grants one for it.
set_now( utc( '2027-01-10 12:00' ) );
$GLOBALS['index'][ REGRANT ] = array( 'record_id' => REGRANT, 'name' => 'Regrant Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31', 'event_allowance' => array( 'until' => '2027-01-31', 'extra' => 1 ) );
seed_event( REGRANT, 'approved', utc( '2026-11-20 10:00' ), 60, 'The first term\'s own event' );
$GLOBALS['index'][ REGRANT ] = array_merge(
	$GLOBALS['index'][ REGRANT ],
	array(
		'tier_until'  => '2028-01-31',
		'report_owed' => attempt( static function () { return WPCPM_Sponsor_Report::owed_after_save( REGRANT, $GLOBALS['index'][ REGRANT ], array_merge( $GLOBALS['index'][ REGRANT ], array( 'tier_until' => '2028-01-31' ) ) ); } ),
	)
);

// Asked on 10 January, a start before the renewed term's first day falls in the term moved off.
$regrant_moved_off = attempt( static function () use ( $row ) { return WPCPM_Sponsor_Event::may_plan( REGRANT, $row( REGRANT ), utc( '2027-01-20 10:00' ) ); } );

// By 20 March the renewed term's own event has taken place.
set_now( utc( '2027-03-20 12:00' ) );
$regrant_renewed = seed_event( REGRANT, 'approved', utc( '2027-03-10 10:00' ), 60, 'The renewed term\'s own event' );

ck(
	'renewed early: the number names the term it was granted for, which keeps it for the days it still runs, while the renewed term counts it for nothing once its own event is held, and counts one granted for it',
	static function () use ( $row, $regrant_moved_off ) {
		$renewed = array( WPCPM_Sponsor_Event::may_plan( REGRANT, $row( REGRANT ), 0 ), WPCPM_Sponsor_Event::may_plan( REGRANT, $row( REGRANT ), utc( '2027-04-10 10:00' ) ) );
		$GLOBALS['index'][ REGRANT ]['event_allowance'] = array( 'until' => '2028-01-31', 'extra' => 1 );
		$granted = WPCPM_Sponsor_Event::may_plan( REGRANT, $row( REGRANT ), utc( '2027-04-10 10:00' ) );
		return array( WPCPM_Sponsor_Event::term_from( $row( REGRANT ) ), $regrant_moved_off, $renewed, $granted );
	},
	array( '2027-02-01', '', array( 'term_used', 'term_used' ), '' )
);

set_now( utc( '2027-01-10 12:00' ) );

// The mirror image: a number granted for the renewed term is that term's alone, and the term the
// renewal moved off, whose own event was held, is spent for the days it still runs.
$GLOBALS['index'][ REACH ] = array( 'record_id' => REACH, 'name' => 'Reach Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31' );
seed_event( REACH, 'approved', utc( '2026-11-20 10:00' ), 60, 'The first term\'s own event' );
$GLOBALS['index'][ REACH ] = array_merge(
	$GLOBALS['index'][ REACH ],
	array(
		'tier_until'      => '2028-01-31',
		'report_owed'     => attempt( static function () { return WPCPM_Sponsor_Report::owed_after_save( REACH, $GLOBALS['index'][ REACH ], array_merge( $GLOBALS['index'][ REACH ], array( 'tier_until' => '2028-01-31' ) ) ); } ),
		'event_allowance' => array( 'until' => '2028-01-31', 'extra' => 1 ),
	)
);

ck(
	'a number granted for the renewed term does not reach back into the term before: a start in the days that term still runs is term_used, one in the renewed term is taken',
	static function () use ( $row ) {
		return array( WPCPM_Sponsor_Event::extra_events( $row( REACH ), '2028-01-31' ), WPCPM_Sponsor_Event::may_plan( REACH, $row( REACH ), utc( '2027-01-20 10:00' ) ), WPCPM_Sponsor_Event::may_plan( REACH, $row( REACH ), utc( '2027-03-10 15:00' ) ) );
	},
	array( 1, 'term_used', '' )
);

// Put back as the section found it: its clock, with nothing of these sponsors still to take place on
// it, since two of their events are still to come on 10 January.
foreach ( array( $extra_second, $regrant_renewed ) as $extra_event ) {
	if ( is_int( $extra_event ) ) {
		update_post_meta( $extra_event, '_wpcpm_sevent_state', 'cancelled' );
	}
}
set_now( $extra_was );

/* ---- the privacy walk --------------------------------------------------------------- */

echo "\n=== The privacy walk ===\n";

$people = array();
foreach ( array( MANAGER, STUDENT, PAUSED, MENTOR, MENTOR_REP, CONTACT, WALKER ) as $who ) {
	$people[] = $GLOBALS['users'][ $who ]->display_name;
	$people[] = $GLOBALS['users'][ $who ]->user_email;
}
$links        = array( 'call.example', 'secret-room' );
$descriptions = array( 'contrast', 'C:\\themes', 'Moved by two days', 'Open to everyone', 'Quillfeather', 'agenda', 'What the webinar covers' );
$titles       = array( 'Accessible themes', 'Champion webinar', 'Ends on the last second', 'A co-hosted webinar' );

/**
 * The needles found in a haystack.
 *
 * @param string   $haystack Text.
 * @param string[] $needles  What must not be in it.
 * @return string[]
 */
function found_in( $haystack, array $needles ) {
	return array_values( array_filter( $needles, static function ( $needle ) use ( $haystack ) { return false !== stripos( $haystack, $needle ); } ) );
}

ck(
	'every audit row the store wrote holds no joiner\'s name or address, no link, no description and no title, in its words or its facts',
	static function () use ( $people, $links, $descriptions, $titles ) {
		$found = array();
		foreach ( $GLOBALS['audit'] as $entry ) {
			$found = array_merge( $found, found_in( $entry['message'] . ' ' . json_encode( $entry['data'] ), array_merge( $people, $links, $descriptions, $titles ) ) );
		}
		return array( count( $GLOBALS['audit'] ) >= 10, array_values( array_unique( $found ) ) );
	},
	array( true, array() )
);
ck(
	'and every row\'s facts are the start, the minutes, the places and the count, nothing more, on a row the real writer accepts',
	static function () {
		$keys = array();
		foreach ( $GLOBALS['audit'] as $entry ) { $keys[] = implode( ',', array_keys( $entry['data'] ) ); }
		return array( count( $keys ) >= 10, array_values( array_unique( $keys ) ), $GLOBALS['refused'] );
	},
	array( true, array( 'start,minutes,places,joined' ), array() )
);
ck(
	'no mail to a person on an event names anybody else on it or a member of the sponsor, in its words, its headers or its calendar file: the sponsor\'s name and the call link alone reach them',
	static function () use ( $c2_mails, $c3_change_mails, $c3_cancel_mails ) {
		$leaks = array();
		$read  = 0;
		foreach ( array_merge( (array) $c2_mails, (array) $c3_change_mails, (array) $c3_cancel_mails ) as $mail ) {
			++$read;
			$text = $mail['subject'] . "\n" . $mail['body'] . "\n" . serialize( $mail['headers'] ) . "\n" . ( isset( $mail['files'][0]['ics'] ) ? $mail['files'][0]['ics'] : '' );
			foreach ( $GLOBALS['users'] as $user ) {
				if ( $user->user_email === $mail['to'] ) { continue; }
				foreach ( array( $user->display_name, $user->user_email ) as $needle ) {
					if ( false !== stripos( $text, $needle ) ) { $leaks[] = $mail['to'] . ': ' . $needle; }
				}
			}
		}
		return array( $read, $leaks );
	},
	array( 9, array() )
);
ck(
	'held_in() answers titles and counts: no joiner\'s name or address, no link, no description',
	static function () use ( $people, $links, $descriptions ) {
		$answer = json_encode( array( WPCPM_Sponsor_Event::held_in( HELD, '2026-02-01', '2027-01-31' ), WPCPM_Sponsor_Event::held_in( PARTNER, '2026-02-01', '2027-01-31' ) ) );
		return array( strlen( $answer ) > 50, found_in( $answer, array_merge( $people, $links, $descriptions ) ) );
	},
	array( true, array() )
);

/* ---- delete_all() ------------------------------------------------------------------- */

echo "\n=== delete_all() ===\n";

$trashed = seed_event( HELD, 'approved', utc( '2026-05-01 10:00' ), 60, 'Trashed by hand' );
$GLOBALS['posts'][ $trashed ]->post_status = 'trash';
update_option( 'wpcpm_sevent_lock_' . PARTNER, $GLOBALS['now'] );
update_option( 'wpcpm_sevent_lock_' . HELD, $GLOBALS['now'] - 3600 );
update_option( 'wpcpm_settings', array( 'kept' => true ) );
$GLOBALS['wpdb']->queries = array();
// A drain and a retry of the reminders on the clock, a row still to tell on the trashed event, where
// the last reminders' window stopped, and one of core's own events.
add_post_meta( $trashed, '_wpcpm_sevent_pending', STUDENT . ':1' );
update_option( 'wpcpm_sevent_reminder_edge', $GLOBALS['now'] + DAY_IN_SECONDS );
$GLOBALS['cron'] = array( array( 'hook' => 'wp_version_check', 'at' => $GLOBALS['now'] + 3600 ), array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 60 ), array( 'hook' => 'wpcpm_sevent_remind_retry', 'at' => $GLOBALS['now'] + 300 ) );

/** The event posts still in the store, in any status. */
function events_left() {
	$left = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'wpcpm_sponsor_event' === $post->post_type ) { $left[] = $post->ID; }
	}
	return $left;
}

ck(
	'a post of the type in the trash is no event: nobody joins it, and no reader lists it',
	static function () use ( $trashed ) { return array( WPCPM_Sponsor_Event::is_event( get_post( $trashed ) ), code_of( WPCPM_Sponsor_Event::join( $trashed, STUDENT ) ), in_array( $trashed, ids( WPCPM_Sponsor_Event::past_of( HELD ) ), true ) ); },
	array( false, 'not_open', false )
);

$event_ids     = events_left();
$events_before = count( $event_ids );
$GLOBALS['deleted'] = array();
attempt( static function () { WPCPM_Sponsor_Event::delete_all(); } );

// The statuses a delete walks are the stand-in's, so the stand-in has to be the sponsors store's.
$walked = (bool) preg_match( "/const POST_STATUSES = array\( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' \);/", (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php' ) );

ck(
	'every event goes, a trashed one included, with every row of its meta, in every status the sponsors store walks',
	array( $events_before > 10, events_left(), isset( $GLOBALS['pmeta'][ is_int( $p1 ) ? $p1 : 0 ] ), isset( $GLOBALS['pmeta'][ $trashed ] ), $walked ),
	array( true, array(), false, false, true )
);
ck(
	'each deleted for good, past the trash, and nothing but the events',
	static function () use ( $event_ids ) { $deleted = array_keys( $GLOBALS['deleted'] ); sort( $deleted ); return array( count( $event_ids ) > 10, $deleted === $event_ids, array_values( array_unique( $GLOBALS['deleted'] ) ) ); },
	array( true, true, array( true ) )
);
ck( 'and every lock', array_values( preg_grep( '/^wpcpm_sevent_lock_/', array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ), array() );
ck( 'the rows still to tell went with their event, and the drain and the reminders\' retry come off the clock, while core\'s own event stays', array( all_pending(), $GLOBALS['cron'] ), array( array(), array( array( 'hook' => 'wp_version_check', 'at' => $GLOBALS['now'] + 3600 ) ) ) );
ck( 'and where the last reminders\' window stopped goes too', get_option( 'wpcpm_sevent_reminder_edge', 'gone' ), 'gone' );
ck( 'found by prefix, escaped for LIKE, through the site\'s own database object', $GLOBALS['wpdb']->queries, array( "SELECT option_name FROM wp_options WHERE option_name LIKE 'wpcpm\\_sevent\\_lock\\_%'" ) );
ck( 'and nothing else: a page, a sponsor report, the settings and the site\'s own options stay', array( $events_before > 10 && array() === events_left(), null !== get_post( $page ), null !== get_post( $report ), get_option( 'wpcpm_settings' ), get_option( 'admin_email' ) ), array( true, true, true, array( 'kept' => true ), 'admin@site.example' ) );

/* ---- the house rules ------------------------------------------------------------------ */

echo "\n=== The house rules ===\n";

ck(
	'the store mails the people on an event from a change and a cancel, and from the daily run\'s reminders further on: up to here nothing else it did mailed anybody, a plan, an approval, a return, a join and a leave included',
	array( count( $GLOBALS['audit'] ) > 0, $GLOBALS['all_mail'] ),
	array( true, array( 'mel.mentorrep@people.example', 'sam.current@people.example', 'paula.paused@people.example', 'ines.institution@people.example', 'mira.manager@people.example', 'sam.current@people.example', 'paula.paused@people.example', 'sam.current@people.example', 'paula.paused@people.example' ) )
);
ck( 'and it goes through the mail layer from one place, never to wp_mail() itself', array( substr_count( $event_src, 'WPCPM_Mail::send(' ), preg_match( '/\bwp_mail\s*\(/', $event_src ) ), array( 1, 0 ) );

/* ---- fifty in the press, the rest by the drain ---------------------------------------- */

echo "\n=== Fifty in the press, the rest by the drain ===\n";

// A crowd of 120 current students on a Champion event: a blank places box is no limit, so the
// store's ceiling is the audience. The events above are gone with delete_all(), so the Champion
// plans again.
set_now( utc( '2027-02-01 09:00' ) );

$crowd = array();
for ( $i = 1; $i <= 120; $i++ ) {
	$crowd[] = 6000 + $i;
	a_user( 6000 + $i, 'Crowd Member ' . $i, array( 'wpcpm_student' ) );
}

/** An approved Champion event with the crowd on it, in the order they joined. */
function crowd_event( $start, $title, array $people ) {
	$id = WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( $start, array( 'title' => $title ) ), CHAMPION_REP, 'member' );
	WPCPM_Sponsor_Event::approve( $id, MANAGER );
	foreach ( $people as $person ) { add_post_meta( $id, '_wpcpm_sevent_person', $person ); }
	return $id;
}

/** The addresses of a run of the crowd, by their places in it, first to last, one left out if asked. */
function crowd_addresses( $from, $to, $but = 0 ) {
	$out = array();
	for ( $i = $from; $i <= $to; $i++ ) {
		if ( $i !== $but ) { $out[] = 'crowd.member.' . $i . '@people.example'; }
	}
	return $out;
}

/** The rows a run of the crowd holds at one version of the event, each of the kind of the move that wrote it, `cancel` or `change`. */
function crowd_rows( $from, $to, $revision, $kind, $but = 0 ) {
	$out = array();
	for ( $i = $from; $i <= $to; $i++ ) {
		if ( $i !== $but ) { $out[] = ( 6000 + $i ) . ':' . (int) $revision . ':' . $kind; }
	}
	return $out;
}

/** Every row an event holds of the people still to tell. */
function pending_of( $id ) { return get_post_meta( (int) $id, '_wpcpm_sevent_pending', false ); }

/** What the sends from a mark in the log were: how many, whether any went to a person still down to tell, and whether the drain was on the clock at the first. */
function sends_since( $mark ) {
	$log = array_slice( $GLOBALS['send_log'], $mark );
	return array( count( $log ), array_values( array_unique( array_column( $log, 'still_pending' ) ) ), isset( $log[0] ) ? $log[0]['drain'] : null );
}

$GLOBALS['mail'] = array();
$GLOBALS['cron'] = array();
$crowd_mark      = count( $GLOBALS['all_mail'] );
$big             = attempt( static function () use ( $crowd ) { return crowd_event( utc( '2027-03-01 15:00' ), 'A webinar for everyone', $crowd ); } );
$big_log         = count( $GLOBALS['send_log'] );

ck(
	'a cancel of 120 people tells the first fifty in the press, in the order they joined, and leaves the other seventy on the event, a row each at the cancel\'s version; the drain is on the clock once, a minute out',
	static function () use ( $big ) {
		$answer = WPCPM_Sponsor_Event::cancel( $big, CHAMPION_REP );
		return array( $answer, mailed_to() === crowd_addresses( 1, 50 ), pending_of( $big ) === crowd_rows( 51, 120, meta( $big, '_wpcpm_sevent_revision' ), 'cancel' ), $GLOBALS['cron'] );
	},
	array( array( 'told' => 50, 'pending' => 70 ), true, true, array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 60 ) ) )
);
ck(
	'every one of the 120 was written down before the first mail left, each of the fifty taken off the list before theirs, and the drain was on the clock by then: a press that dies part way leaves the rest to the drain, and tells nobody twice',
	static function () use ( $big_log ) { $log = array_slice( $GLOBALS['send_log'], $big_log ); return array( sends_since( $big_log ), isset( $log[0] ) ? $log[0]['rows_left'] : null ); },
	array( array( 50, array( false ), true ), 119 )
);

$big_log = count( $GLOBALS['send_log'] );

ck(
	'the drain tells the next fifty the event is canceled, each taken off the list before their mail left, and is back on the clock before its first send while rows remain',
	static function () use ( $big ) {
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		$subjects = array_values( array_unique( array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ) ) );
		return array( mailed_to() === crowd_addresses( 51, 100 ), $subjects, pending_of( $big ) === crowd_rows( 101, 120, meta( $big, '_wpcpm_sevent_revision' ), 'cancel' ), $GLOBALS['cron'] );
	},
	array( true, array( '[WordPress Education] Event canceled: A webinar for everyone' ), true, array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 60 ) ) )
);
ck( 'every one of those sends found its row gone already, and the next run on the clock', static function () use ( $big_log ) { return sends_since( $big_log ); }, array( 50, array( false ), true ) );
ck(
	'the next run tells the last twenty and empties the list; the run after it finds nothing, mails nobody and puts nothing on the clock',
	static function () use ( $big ) {
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		$last  = mailed_to();
		$after = $GLOBALS['cron'];
		$mails = count( $GLOBALS['all_mail'] );
		run_cron( 'wpcpm_sevent_drain' );
		return array( $last === crowd_addresses( 101, 120 ), pending_of( $big ), count( $after ), count( $GLOBALS['all_mail'] ) - $mails, $GLOBALS['cron'] );
	},
	array( true, array(), 1, 0, array() )
);
ck(
	'each of the 120 was told once, with the file that takes the event off their calendar, and nobody else was mailed',
	static function () use ( $crowd_mark ) {
		$counts = array_count_values( array_slice( $GLOBALS['all_mail'], $crowd_mark ) );
		ksort( $counts );
		$want = array_fill_keys( crowd_addresses( 1, 120 ), 1 );
		ksort( $want );
		return $counts === $want;
	},
	true
);

// Once more, and the drain's request dies at its tenth send, just after handing it off.
$big2 = attempt(
	static function () use ( $crowd ) {
		$id = crowd_event( utc( '2027-03-08 15:00' ), 'A second webinar for everyone', $crowd );
		WPCPM_Sponsor_Event::cancel( $id, CHAMPION_REP );
		return $id;
	}
);

ck(
	'a drain that dies at its tenth send, just after the hand-off, leaves the sixty it had not told on the event, and the next run on the clock',
	static function () use ( $big2 ) {
		$GLOBALS['dies'] = array( 'at' => count( $GLOBALS['all_mail'] ) + 10, 'handed_off' => true );
		$died            = '';
		try {
			run_cron( 'wpcpm_sevent_drain' );
		} catch ( RuntimeException $e ) {
			$died = $e->getMessage();
		}
		return array( $died, pending_of( $big2 ) === crowd_rows( 61, 120, meta( $big2, '_wpcpm_sevent_revision' ), 'cancel' ), false !== wp_next_scheduled( 'wpcpm_sevent_drain' ) );
	},
	array( 'the request died just after the send was handed off', true, true )
);
ck(
	'the runs after it tell those sixty, and every one of the 120 was told once: nobody twice, nobody left out',
	static function () use ( $big2, $crowd_mark ) {
		$mark = count( $GLOBALS['all_mail'] );
		run_cron( 'wpcpm_sevent_drain' );
		run_cron( 'wpcpm_sevent_drain' );
		$counts = array_count_values( array_slice( $GLOBALS['all_mail'], $mark - 60 ) );
		return array( count( $GLOBALS['all_mail'] ) - $mark, pending_of( $big2 ), count( $counts ), array_values( array_unique( $counts ) ) );
	},
	array( 60, array(), 120, array( 1 ) )
);

// And a third time, the drain's request dying in the middle of its tenth send, before the hand-off.
$big3 = attempt(
	static function () use ( $crowd ) {
		$id = crowd_event( utc( '2027-03-15 15:00' ), 'A third webinar for everyone', $crowd );
		WPCPM_Sponsor_Event::cancel( $id, CHAMPION_REP );
		return $id;
	}
);

ck(
	'a drain that dies in the middle of a send loses that one person alone: their row was gone before the send, so no run tells them twice, and every other one is told once',
	static function () use ( $big3 ) {
		$mark            = count( $GLOBALS['all_mail'] ) - 50;
		$GLOBALS['dies'] = array( 'at' => count( $GLOBALS['all_mail'] ) + 10, 'handed_off' => false );
		try {
			run_cron( 'wpcpm_sevent_drain' );
		} catch ( RuntimeException $e ) {
			unset( $GLOBALS['dies'] );
		}
		run_cron( 'wpcpm_sevent_drain' );
		run_cron( 'wpcpm_sevent_drain' );
		$counts = array_count_values( array_slice( $GLOBALS['all_mail'], $mark ) );
		ksort( $counts );
		$want = array_fill_keys( crowd_addresses( 1, 120, 60 ), 1 );
		ksort( $want );
		return array( $counts === $want, pending_of( $big3 ) );
	},
	array( true, array() )
);

// A change of the time on a fourth, the crowd on it again.
$big4 = attempt( static function () use ( $crowd ) { return crowd_event( utc( '2027-03-22 15:00' ), 'A fourth webinar for everyone', $crowd ); } );

ck(
	'a change of the time tells fifty in the press and leaves seventy for the drain, a row each at the change\'s version',
	static function () use ( $big4 ) {
		$GLOBALS['mail'] = array();
		$answer          = WPCPM_Sponsor_Event::change( $big4, event_fields( utc( '2027-03-22 16:00' ), array( 'title' => 'A fourth webinar for everyone' ) ), CHAMPION_REP );
		$subjects        = array_values( array_unique( array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ) ) );
		return array( $answer, mailed_to() === crowd_addresses( 1, 50 ), $subjects, pending_of( $big4 ) === crowd_rows( 51, 120, meta( $big4, '_wpcpm_sevent_revision' ), 'change' ) );
	},
	array( array( 'changed' => true, 'told' => 50, 'pending' => 70 ), true, array( '[WordPress Education] Event changed: A fourth webinar for everyone' ), true )
);

$big4_change = attempt( static function () use ( $big4 ) { return (int) meta( $big4, '_wpcpm_sevent_revision' ); } );

ck(
	'a change of the description after it leaves the seventy still to tell as they were, and tells nobody itself',
	static function () use ( $big4, $big4_change ) {
		$GLOBALS['mail'] = array();
		$answer          = WPCPM_Sponsor_Event::change( $big4, event_fields( utc( '2027-03-22 16:00' ), array( 'title' => 'A fourth webinar for everyone', 'description' => 'Bring a laptop.' ) ), CHAMPION_REP );
		return array( $answer, mailed_to(), pending_of( $big4 ) === crowd_rows( 51, 120, $big4_change, 'change' ), (int) meta( $big4, '_wpcpm_sevent_revision' ) - $big4_change );
	},
	array( array( 'changed' => true, 'told' => 0, 'pending' => 0 ), array(), true, 1 )
);
ck(
	'a person who left before the drain reached them is told nothing of the change: the drain asks who is still on it, and takes their row off all the same',
	static function () use ( $big4, $big4_change ) {
		$GLOBALS['mail'] = array();
		WPCPM_Sponsor_Event::leave( $big4, 6060 );
		run_cron( 'wpcpm_sevent_drain' );
		return array( mailed_to() === crowd_addresses( 51, 100, 60 ), pending_of( $big4 ) === crowd_rows( 101, 120, $big4_change, 'change' ) );
	},
	array( true, true )
);
ck(
	'a cancel before the drain is done replaces the list rather than adding to it: every row left is the cancel\'s, and nobody is sent the change after the cancel',
	static function () use ( $big4 ) {
		$GLOBALS['mail'] = array();
		$answer          = WPCPM_Sponsor_Event::cancel( $big4, CHAMPION_REP );
		$revision        = meta( $big4, '_wpcpm_sevent_revision' );
		$rows            = pending_of( $big4 );
		run_cron( 'wpcpm_sevent_drain' );
		run_cron( 'wpcpm_sevent_drain' );
		$subjects = array_values( array_unique( array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ) ) );
		return array( $answer, $rows === crowd_rows( 51, 120, $revision, 'cancel', 60 ), $subjects, count( $GLOBALS['mail'] ), pending_of( $big4 ) );
	},
	array( array( 'told' => 50, 'pending' => 69 ), true, array( '[WordPress Education] Event canceled: A fourth webinar for everyone' ), 119, array() )
);

// Three of the crowd on a fifth event: each of the length, the title and the link is what a
// calendar holds the event for.
$small = attempt( static function () use ( $crowd ) { return crowd_event( utc( '2027-03-29 15:00' ), 'A small webinar', array_slice( $crowd, 0, 3 ) ); } );

ck(
	'the length alone, the title alone and the link alone each tell the three on it',
	static function () use ( $small ) {
		$told = array();
		foreach ( array( array( 'minutes' => 90 ), array( 'minutes' => 90, 'title' => 'A small webinar, renamed' ), array( 'minutes' => 90, 'title' => 'A small webinar, renamed', 'link' => 'https://call.example/another-room' ) ) as $over ) {
			$GLOBALS['mail'] = array();
			$answer          = WPCPM_Sponsor_Event::change( $small, event_fields( utc( '2027-03-29 15:00' ), array_merge( array( 'title' => 'A small webinar' ), $over ) ), CHAMPION_REP );
			$told[]          = array( $answer, mailed_to() === crowd_addresses( 1, 3 ) );
		}
		return $told;
	},
	array(
		array( array( 'changed' => true, 'told' => 3, 'pending' => 0 ), true ),
		array( array( 'changed' => true, 'told' => 3, 'pending' => 0 ), true ),
		array( array( 'changed' => true, 'told' => 3, 'pending' => 0 ), true ),
	)
);
ck(
	'a press while a run of the drain is due already puts no second one on the clock: one event serves every event\'s rows',
	static function () use ( $small ) {
		$GLOBALS['cron'] = array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 30 ) );
		WPCPM_Sponsor_Event::change( $small, event_fields( utc( '2027-03-29 16:00' ), array( 'title' => 'A small webinar, renamed', 'minutes' => 90, 'link' => 'https://call.example/another-room' ) ), CHAMPION_REP );
		return $GLOBALS['cron'];
	},
	array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 30 ) )
);
ck(
	'a row another request took first is not sent again: the drain tells only the people whose rows it took itself',
	static function () use ( $small ) {
		$GLOBALS['mail'] = array();
		WPCPM_Sponsor_Event::cancel( $small, CHAMPION_REP );
		// Three rows, told in the press; written down again, as a later move would, for the drain.
		$revision = meta( $small, '_wpcpm_sevent_revision' );
		foreach ( crowd_rows( 1, 3, $revision, 'cancel' ) as $row ) { add_post_meta( $small, '_wpcpm_sevent_pending', $row ); }
		$GLOBALS['mail']      = array();
		$GLOBALS['meanwhile'] = static function () use ( $small, $revision ) { delete_post_meta( $small, '_wpcpm_sevent_pending', '6002:' . $revision . ':cancel' ); };
		run_cron( 'wpcpm_sevent_drain' );
		return array( mailed_to(), pending_of( $small ) );
	},
	array( crowd_addresses( 1, 3, 2 ), array() )
);

// Fifty-one of the crowd and an account that is gone, joined last.
$gone_on_it = attempt(
	static function () use ( $crowd ) {
		$id = crowd_event( utc( '2027-04-05 15:00' ), 'A webinar with an account gone', array_slice( $crowd, 0, 51 ) );
		add_post_meta( $id, '_wpcpm_sevent_person', 7777 );
		return $id;
	}
);

ck(
	'an account that is gone is written down for nobody to tell: fifty told in the press and the one of the crowd left, not the account',
	static function () use ( $gone_on_it ) {
		$answer = WPCPM_Sponsor_Event::cancel( $gone_on_it, CHAMPION_REP );
		return array( $answer, pending_of( $gone_on_it ) === crowd_rows( 51, 51, meta( $gone_on_it, '_wpcpm_sevent_revision' ), 'cancel' ), count( WPCPM_Sponsor_Event::joiners( $gone_on_it ) ) );
	},
	array( array( 'told' => 50, 'pending' => 1 ), true, 52 )
);

// Two events with people still to tell, the newer written down first: the drain asks core for the
// oldest event first, so the people a move left first are told first.
$older = attempt( static function () use ( $crowd ) { $id = crowd_event( utc( '2027-04-12 15:00' ), 'The older of two', array_slice( $crowd, 0, 2 ) ); WPCPM_Sponsor_Event::cancel( $id, CHAMPION_REP ); return $id; } );
$newer = attempt( static function () use ( $crowd ) { $id = crowd_event( utc( '2027-04-19 15:00' ), 'The newer of two', array_slice( $crowd, 2, 2 ) ); WPCPM_Sponsor_Event::cancel( $id, CHAMPION_REP ); return $id; } );

ck(
	'the drain tells the oldest event\'s people first, whichever event\'s rows were written first',
	static function () use ( $older, $newer ) {
		// Whatever the checks above left for the drain goes first, so the run below reads these two alone.
		for ( $run = 0; $run < 5 && array() !== all_pending(); $run++ ) { run_cron( 'wpcpm_sevent_drain' ); }
		foreach ( crowd_rows( 3, 4, meta( $newer, '_wpcpm_sevent_revision' ), 'cancel' ) as $row ) { add_post_meta( $newer, '_wpcpm_sevent_pending', $row ); }
		foreach ( crowd_rows( 1, 2, meta( $older, '_wpcpm_sevent_revision' ), 'cancel' ) as $row ) { add_post_meta( $older, '_wpcpm_sevent_pending', $row ); }
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		return array( mailed_to(), pending_of( $older ), pending_of( $newer ) );
	},
	array( crowd_addresses( 1, 4 ), array(), array() )
);

// The drain's event gone while rows wait: taken off the clock by a deactivation, or fired while the
// plugin was off, with nothing to put it back until a move on an event with somebody on it.
$lost = attempt( static function () use ( $crowd ) { $id = crowd_event( utc( '2027-04-26 15:00' ), 'A webinar the drain lost', $crowd ); WPCPM_Sponsor_Event::cancel( $id, CHAMPION_REP ); return $id; } );

ck(
	'resume_drain(): with rows waiting and no run due, it puts the drain a minute out, and asked again it puts no second one on the clock',
	static function () use ( $lost ) {
		$GLOBALS['cron'] = array();
		$rows            = count( pending_of( $lost ) );
		WPCPM_Sponsor_Event::resume_drain();
		$first = $GLOBALS['cron'];
		WPCPM_Sponsor_Event::resume_drain();
		return array( $rows, $first, $GLOBALS['cron'] );
	},
	array( 70, array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 60 ) ), array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $GLOBALS['now'] + 60 ) ) )
);
ck(
	'the runs it put back tell the seventy, and with no row left it puts nothing on the clock',
	static function () use ( $lost ) {
		$mark = count( $GLOBALS['all_mail'] );
		run_cron( 'wpcpm_sevent_drain' );
		run_cron( 'wpcpm_sevent_drain' );
		run_cron( 'wpcpm_sevent_drain' );
		WPCPM_Sponsor_Event::resume_drain();
		return array( count( $GLOBALS['all_mail'] ) - $mark, pending_of( $lost ), $GLOBALS['cron'] );
	},
	array( 70, array(), array() )
);

// A drain that runs after the event is over: the crowd on two more events, one canceled and one
// changed, each leaving seventy for the drain, and the drain's first run a day after both ended.
$late_cancel = attempt( static function () use ( $crowd ) { $id = crowd_event( utc( '2027-05-03 15:00' ), 'A webinar canceled, then over', $crowd ); WPCPM_Sponsor_Event::cancel( $id, CHAMPION_REP ); return $id; } );
$late_change = attempt( static function () use ( $crowd ) { $id = crowd_event( utc( '2027-05-10 15:00' ), 'A webinar changed, then over', $crowd ); WPCPM_Sponsor_Event::change( $id, event_fields( utc( '2027-05-10 16:00' ), array( 'title' => 'A webinar changed, then over' ) ), CHAMPION_REP ); return $id; } );

ck(
	'a drain that runs after the event is over takes a change\'s rows and mails nobody, since a new time for something past is noise, while a cancel\'s still go, since it takes the entry off the person\'s calendar',
	static function () use ( $late_cancel, $late_change ) {
		$was             = $GLOBALS['now'];
		$rows            = array( count( pending_of( $late_cancel ) ), count( pending_of( $late_change ) ) );
		$GLOBALS['mail'] = array();
		set_now( utc( '2027-05-11 09:00' ) );
		for ( $run = 0; $run < 4; $run++ ) { run_cron( 'wpcpm_sevent_drain' ); }
		set_now( $was );
		$subjects = array_count_values( array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ) );
		return array( $rows, $subjects, pending_of( $late_cancel ), pending_of( $late_change ), $GLOBALS['cron'] );
	},
	array( array( 70, 70 ), array( '[WordPress Education] Event canceled: A webinar canceled, then over' => 70 ), array(), array(), array() )
);

/* ---- the day before: reminders ---------------------------------------------------- */

echo "\n=== The day before: reminders ===\n";

// The daily run at 22:30 on 14 June 2027 on the site's clock, Los Angeles, which is 05:30 on 15 June
// in UTC. Every event above is over or canceled by then, so the approved events still to come are
// the ones seeded here, on four sponsors of their own.
const REMINDING      = 'spoAAAA0000000010'; // Champion, the term runs to 31 December 2027: the event the mails are read on, and its members.
const EDGES          = 'spoAAAA0000000011'; // Champion, no term, nobody signs in for it: the window's edges.
const ENDING         = 'spoAAAA0000000012'; // Partner whose term's last day is the site's day of the run.
const PAUSING        = 'spoAAAA0000000013'; // Champion the Sponsors screen set to Paused.
const REMIND_REP     = 50;                  // A member of Reminding Example, on a clock of her own.
const REMIND_REP_TWO = 51;                  // Another, who joined the event before the program attached him.
const REMIND_REP_WAS = 52;                  // Detached from Reminding Example: no power, and no reminder.
const ENDING_REP     = 53;                  // The member of Ending Example.

$GLOBALS['site_tz'] = 'America/Los_Angeles';
$run_at             = utc( '2027-06-15 05:30' );
set_now( $run_at );
$GLOBALS['cron'] = array();
$GLOBALS['mail'] = array();

$GLOBALS['index'][ REMINDING ] = array( 'record_id' => REMINDING, 'name' => 'Reminding Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-12-31' );
$GLOBALS['index'][ EDGES ]     = array( 'record_id' => EDGES, 'name' => 'Edges Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' );
$GLOBALS['index'][ ENDING ]    = array( 'record_id' => ENDING, 'name' => 'Ending Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-06-14' );
$GLOBALS['index'][ PAUSING ]   = array( 'record_id' => PAUSING, 'name' => 'Pausing Example', 'status' => 'Paused', 'tier' => 'champion', 'tier_until' => '' );

a_user( REMIND_REP, 'Rhea Reminderrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => REMINDING, 'wpcpm_sponsor_active' => 1, 'wpcpm_timezone' => 'Europe/Warsaw' ) );
a_user( REMIND_REP_TWO, 'Rory Reminderrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => REMINDING, 'wpcpm_sponsor_active' => 1 ) );
a_user( REMIND_REP_WAS, 'Wes Formerrep', array( 'subscriber' ), array( 'wpcpm_sponsor_record_id' => REMINDING, 'wpcpm_sponsor_active' => 0 ) );
a_user( ENDING_REP, 'Enid Endingrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => ENDING, 'wpcpm_sponsor_active' => 1 ) );

// The event the mails are read on, twenty hours after the run, at its fourth version: four people on
// it, the second member among them, who joined before the program attached him.
$main = seed_event( REMINDING, 'approved', $run_at + 20 * HOUR_IN_SECONDS, 60, 'A webinar to remember', array( STUDENT, CONTACT, REMIND_REP_TWO, MENTOR ) );
update_post_meta( $main, '_wpcpm_sevent_revision', 4 );

// The window's edges, one person on each, at the version seed_event() writes, 0. The site's midnight
// falls an hour and a half after the run, and the next one a day after that.
$after_midnight = seed_event( EDGES, 'approved', $run_at + 2 * HOUR_IN_SECONDS, 30, 'Just after midnight', array( NEWCOMER ) );
$under_twelve   = seed_event( EDGES, 'approved', $run_at + 12 * HOUR_IN_SECONDS - 60, 30, 'A minute short of twelve hours', array( NEWCOMER ) );
$at_twelve      = seed_event( EDGES, 'approved', $run_at + 12 * HOUR_IN_SECONDS, 30, 'Twelve hours on', array( NEWCOMER ) );
$under_36       = seed_event( EDGES, 'approved', $run_at + 36 * HOUR_IN_SECONDS - 60, 30, 'A minute short of thirty-six hours', array( NEWCOMER ) );
$at_36          = seed_event( EDGES, 'approved', $run_at + 36 * HOUR_IN_SECONDS, 30, 'Thirty-six hours on', array( NEWCOMER ) );
$still_waiting  = seed_event( EDGES, 'planned', $run_at + 20 * HOUR_IN_SECONDS, 30, 'Still waiting for the program', array() );
$called_off     = seed_event( EDGES, 'cancelled', $run_at + 20 * HOUR_IN_SECONDS, 30, 'Called off', array( NEWCOMER ) );
$paused_event   = seed_event( PAUSING, 'approved', $run_at + 20 * HOUR_IN_SECONDS, 30, 'A paused sponsor\'s webinar', array( NEWCOMER ) );
$ending_event   = seed_event( ENDING, 'approved', $run_at + 30 * HOUR_IN_SECONDS, 45, 'A webinar after the last day', array( GRADUATE ) );

// What the mail layer hands a send's filter: the context the mail log keeps it under, the account
// it is for, and the account whose language the layer switched to around it.
$GLOBALS['handed']                = array();
$GLOBALS['filters']['wpcpm_mail'] = array(
	static function ( $mail, $context = '', $recipient = null ) {
		$GLOBALS['handed'][] = array( (string) $context, $recipient instanceof WP_User ? (int) $recipient->ID : 0, $GLOBALS['locale_for'] ? (int) end( $GLOBALS['locale_for'] ) : 0 );
		return $mail;
	},
);

$GLOBALS['settings']['sponsor_tiers_live'] = false;

ck(
	'while Sponsor tiers is off, queue_reminders() writes nobody down, marks nothing and puts nothing on the clock',
	static function () use ( $main ) { return array( WPCPM_Sponsor_Event::queue_reminders( '2027-06-14' ), pending_of( $main ), meta( $main, '_wpcpm_sevent_reminded' ), $GLOBALS['cron'] ); },
	array( 0, array(), '', array() )
);

$GLOBALS['settings']['sponsor_tiers_live'] = true;

// Handed 15 June, the day after the Partner's term's last day.
$edge_before = get_option( 'wpcpm_sevent_reminder_edge', 'none' );
$first       = attempt( static function () { return WPCPM_Sponsor_Event::queue_reminders( '2027-06-15' ); } );

ck(
	'handed a day, it writes a reminder row at the event\'s version for each person on it, in the order they joined, then for each live member of its sponsor not on it: the member who joined once, the detached one not at all',
	static function () use ( $main ) { return pending_of( $main ); },
	array( STUDENT . ':4:reminder', CONTACT . ':4:reminder', REMIND_REP_TWO . ':4:reminder', MENTOR . ':4:reminder', REMIND_REP . ':4:reminder' )
);
ck(
	'the window is hours after the run, not the site\'s days: at 22:30 on 14 June in Los Angeles, the event twelve hours on, at 10:30 the next morning, and the one a minute short of thirty-six hours, at 10:29 the morning after, are reminded and marked; the one just after the site\'s midnight, the one a minute short of twelve hours and the one at thirty-six are the runs before and after it',
	static function () use ( $after_midnight, $under_twelve, $at_twelve, $under_36, $at_36 ) {
		$out = array( wp_date( 'Y-m-d H:i' ) );
		foreach ( array( $after_midnight, $under_twelve, $at_twelve, $under_36, $at_36 ) as $id ) {
			$out[] = array( wp_date( 'Y-m-d H:i', (int) meta( $id, '_wpcpm_sevent_start' ) ), pending_of( $id ), meta( $id, '_wpcpm_sevent_reminded' ) );
		}
		return $out;
	},
	array(
		'2027-06-14 22:30',
		array( '2027-06-15 00:30', array(), '' ),
		array( '2027-06-15 10:29', array(), '' ),
		array( '2027-06-15 10:30', array( NEWCOMER . ':0:reminder' ), '0' ),
		array( '2027-06-16 10:29', array( NEWCOMER . ':0:reminder' ), '0' ),
		array( '2027-06-16 10:30', array(), '' ),
	)
);
ck(
	'an event still waiting for the program and a canceled one are not reminded, nor one of a sponsor set to Paused, nor, handed 15 June, one of the sponsor whose term ended on 14 June',
	static function () use ( $still_waiting, $called_off, $paused_event, $ending_event ) {
		$out = array();
		foreach ( array( $still_waiting, $called_off, $paused_event, $ending_event ) as $id ) {
			$out[] = array( pending_of( $id ), meta( $id, '_wpcpm_sevent_reminded' ) );
		}
		return $out;
	},
	array_fill( 0, 4, array( array(), '' ) )
);
ck(
	'the run asks whether an event is still offered as every other reader asks it, offers_events() on the run\'s day, whose own branch offers the program\'s events, and asks none of its questions by hand',
	static function () use ( $event_src ) {
		$body = substr( $event_src, (int) strpos( $event_src, 'private static function write_reminders(' ) );
		$body = substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );

		return array( '' !== $body, substr_count( $body, 'self::offers_events( $event, $day )' ), preg_match( '/\b(is_approved|tier_offers|is_program)\(/', $body ) );
	},
	array( true, 1, 0 )
);
ck(
	'it answers how many people it wrote down, marks each event it reminded with the version reminded, puts the drain on the clock once, a minute out, and mails nobody in the run itself',
	static function () use ( $first, $main, $run_at ) { return array( $first, meta( $main, '_wpcpm_sevent_reminded' ), count( all_pending() ), $GLOBALS['cron'], $GLOBALS['mail'] ); },
	array( 7, '4', 7, array( array( 'hook' => 'wpcpm_sevent_drain', 'at' => $run_at + 60 ) ), array() )
);
ck(
	'the first run ever, with no edge on record, opens its window twelve hours after the run and, having written down every event in it, records where the window stopped as the edge and puts no retry on the clock',
	static function () use ( $edge_before, $run_at ) { return array( $edge_before, get_option( 'wpcpm_sevent_reminder_edge', 'none' ), wp_next_scheduled( 'wpcpm_sevent_remind_retry' ) ); },
	array( 'none', $run_at + 36 * HOUR_IN_SECONDS, false )
);

$second = attempt( static function () { return WPCPM_Sponsor_Event::queue_reminders(); } );

ck(
	'with no day handed in, it reads the site\'s own: 22:30 on 14 June in Los Angeles is 15 June in UTC, and on the site\'s 14 June the Partner\'s term still runs, so its event is reminded, the person on it and its member; nothing reminded already is written again',
	static function () use ( $second, $ending_event, $main ) { return array( $second, pending_of( $ending_event ), meta( $ending_event, '_wpcpm_sevent_reminded' ), count( pending_of( $main ) ), count( all_pending() ) ); },
	array( 2, array( GRADUATE . ':0:reminder', ENDING_REP . ':0:reminder' ), '0', 5, 9 )
);

$GLOBALS['mail']   = array();
$GLOBALS['handed'] = array();

ck(
	'the drain tells each of them once, the oldest event first, the people on an event and its sponsor\'s members alike, and leaves no row',
	static function () {
		run_cron( 'wpcpm_sevent_drain' );
		return array( mailed_to(), all_pending() );
	},
	array( array( 'sam.current@people.example', 'ines.institution@people.example', 'rory.reminderrep@people.example', 'mona.mentor@people.example', 'rhea.reminderrep@people.example', 'nora.newcomer@people.example', 'nora.newcomer@people.example', 'gina.graduate@people.example', 'enid.endingrep@people.example' ), array() )
);

$reminders = attempt( static function () { return $GLOBALS['mail']; } );

ck(
	'a person on the event is reminded whose event it is, its title, when on their own clock with the zone named, how long it runs and the call link, with no calendar file: theirs holds the event already',
	static function () { $mail = mail_for( 'sam.current@people.example' ); return array( $mail['subject'], $mail['body'], $mail['headers'], $mail['files'] ); },
	array(
		'[WordPress Education] Reminder: A webinar to remember',
		"A reminder of Reminding Example's event, which you joined.\n\n"
			. "Title: A webinar to remember\nWhen: June 15, 2027 9:30 pm to 10:30 pm (America / New York)\nLength: 60 minutes\nJoin the call: https://call.example/secret-room-5150",
		array(),
		array(),
	)
);
ck(
	'a member of the sponsor is reminded of the title, when on their own clock, and how many people have joined so far, never who, and never with the call link or a calendar file',
	static function () { $mail = mail_for( 'rhea.reminderrep@people.example' ); return array( $mail['subject'], $mail['body'], $mail['headers'], $mail['files'] ); },
	array(
		'[WordPress Education] Your event is coming up: A webinar to remember',
		"A reminder of your company's event.\n\nTitle: A webinar to remember\nWhen: June 16, 2027 3:30 am to 4:30 am (Europe / Warsaw)\nJoined so far: 4 people",
		array(),
		array(),
	)
);
ck(
	'a member who is on the event is reminded once, as a person on it, with the call link, on the site\'s clock for want of one of his own; and one person on an event is one person',
	static function () {
		$rory = array_values( array_filter( $GLOBALS['mail'], static function ( $mail ) { return 'rory.reminderrep@people.example' === $mail['to']; } ) );
		return array( count( $rory ), isset( $rory[0] ) ? array( $rory[0]['subject'], $rory[0]['body'] ) : array(), mail_for( 'enid.endingrep@people.example' )['body'] );
	},
	array(
		1,
		array(
			'[WordPress Education] Reminder: A webinar to remember',
			"A reminder of Reminding Example's event, which you joined.\n\n"
				. "Title: A webinar to remember\nWhen: June 15, 2027 6:30 pm to 7:30 pm (America / Los Angeles)\nLength: 60 minutes\nJoin the call: https://call.example/secret-room-5150",
		),
		"A reminder of your company's event.\n\nTitle: A webinar after the last day\nWhen: June 16, 2027 4:30 am to 5:15 am (America / Los Angeles)\nJoined so far: 1 person",
	)
);
ck(
	'each is handed to the mail layer under its own template\'s id, event-reminder to a person on the event and event-reminder-sponsor to a member of the sponsor who is not, and built in the language of the person it goes to',
	static function () {
		$out = array();
		foreach ( $GLOBALS['handed'] as $send ) {
			$out[] = array( $send[0], $send[1] > 0 && $send[1] === $send[2] );
		}
		return array( count( $out ), array_values( array_unique( array_map( 'serialize', $out ) ) ) );
	},
	array( 9, array( serialize( array( 'event-reminder', true ) ), serialize( array( 'event-reminder-sponsor', true ) ) ) )
);
ck(
	'no reminder names anybody but the person it goes to, or gives the description: the people on an event are counted for its sponsor, never named',
	static function () use ( $reminders ) {
		$leaks = array();
		foreach ( (array) $reminders as $mail ) {
			$text  = $mail['subject'] . "\n" . $mail['body'] . "\n" . serialize( $mail['headers'] );
			$leaks = array_merge( $leaks, found_in( $text, array( 'Quillfeather' ) ) );
			foreach ( $GLOBALS['users'] as $user ) {
				if ( $user->user_email === $mail['to'] ) { continue; }
				foreach ( array( $user->display_name, $user->user_email ) as $needle ) {
					if ( false !== stripos( $text, $needle ) ) { $leaks[] = $mail['to'] . ': ' . $needle; }
				}
			}
		}
		return array( count( (array) $reminders ), $leaks );
	},
	array( 9, array() )
);
ck(
	'the mark holds: a run again the same day writes nobody down, not even a person who joined since the reminders were written',
	static function () use ( $main ) { return array( WPCPM_Sponsor_Event::join( $main, WALKER ), WPCPM_Sponsor_Event::queue_reminders(), all_pending() ); },
	array( true, 0, array() )
);
ck(
	'a change of the time after the reminders tells the people on it the new time, and no run reminds anybody of the event again: the mark keeps the version reminded while the version moves on',
	static function () use ( $main ) {
		$GLOBALS['mail'] = array();
		$answer          = WPCPM_Sponsor_Event::change( $main, event_fields( $GLOBALS['now'] + 22 * HOUR_IN_SECONDS, array( 'title' => 'A webinar to remember', 'description' => 'Seeded description Quillfeather.', 'link' => 'https://call.example/secret-room-5150' ) ), REMIND_REP );
		$subjects        = array_values( array_unique( array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ) ) );
		return array( $answer, $subjects, WPCPM_Sponsor_Event::queue_reminders(), all_pending(), meta( $main, '_wpcpm_sevent_reminded' ), meta( $main, '_wpcpm_sevent_revision' ) );
	},
	array( array( 'changed' => true, 'told' => 5, 'pending' => 0 ), array( '[WordPress Education] Event changed: A webinar to remember' ), 0, array(), '4', '6' )
);

// Two people on an event and the sponsor's two members: one of each goes before the drain runs.
$leaving = seed_event( REMINDING, 'approved', $run_at + 24 * HOUR_IN_SECONDS, 30, 'Two who will not be told', array( STUDENT, NEWCOMER ) );

ck(
	'who is told is asked when they are told: a person who left the event since the reminders were written, and a member the program detached since, are taken off and told nothing, while the others are',
	static function () use ( $leaving ) {
		$written = WPCPM_Sponsor_Event::queue_reminders();
		$rows    = pending_of( $leaving );
		WPCPM_Sponsor_Event::leave( $leaving, NEWCOMER );
		$GLOBALS['umeta'][ REMIND_REP_TWO ]['wpcpm_sponsor_active'] = 0;
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		$GLOBALS['umeta'][ REMIND_REP_TWO ]['wpcpm_sponsor_active'] = 1;
		return array( $written, $rows, mailed_to(), array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ), pending_of( $leaving ) );
	},
	array(
		4,
		array( STUDENT . ':0:reminder', NEWCOMER . ':0:reminder', REMIND_REP . ':0:reminder', REMIND_REP_TWO . ':0:reminder' ),
		array( 'sam.current@people.example', 'rhea.reminderrep@people.example' ),
		array( '[WordPress Education] Reminder: Two who will not be told', '[WordPress Education] Your event is coming up: Two who will not be told' ),
		array(),
	)
);

$to_cancel = seed_event( REMINDING, 'approved', $run_at + 26 * HOUR_IN_SECONDS, 30, 'Canceled before the drain', array( STUDENT, NEWCOMER ) );

ck(
	'a cancel after the reminders were written replaces their rows with its own: the people on it are told it is canceled, with the file that takes it off their calendar, the sponsor\'s members are the pages\' to tell, and nobody is reminded of it',
	static function () use ( $to_cancel ) {
		$written         = WPCPM_Sponsor_Event::queue_reminders();
		$GLOBALS['mail'] = array();
		$answer          = WPCPM_Sponsor_Event::cancel( $to_cancel, MANAGER );
		run_cron( 'wpcpm_sevent_drain' );
		$subjects = array_values( array_unique( array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ) ) );
		$files    = array_map( static function ( $mail ) { return count( $mail['files'] ); }, $GLOBALS['mail'] );
		return array( $written, $answer, mailed_to(), $subjects, $files, pending_of( $to_cancel ) );
	},
	array( 4, array( 'told' => 2, 'pending' => 0 ), array( 'sam.current@people.example', 'nora.newcomer@people.example' ), array( '[WordPress Education] Event canceled: Canceled before the drain' ), array( 1, 1 ), array() )
);

$to_end = seed_event( REMINDING, 'approved', $run_at + 28 * HOUR_IN_SECONDS, 30, 'Over before the drain', array( STUDENT ) );

ck(
	'a drain that runs once the event is over takes its reminder rows off and sends nothing: a reminder of something past is noise',
	static function () use ( $to_end, $run_at ) {
		$written         = WPCPM_Sponsor_Event::queue_reminders();
		$GLOBALS['mail'] = array();
		set_now( $run_at + 30 * HOUR_IN_SECONDS );
		run_cron( 'wpcpm_sevent_drain' );
		set_now( $run_at );
		return array( $written, mailed_to(), pending_of( $to_end ) );
	},
	array( 3, array(), array() )
);

$off_hand = seed_event( REMINDING, 'approved', $run_at + 30 * HOUR_IN_SECONDS, 30, 'Canceled by another hand', array( STUDENT ) );

ck(
	'and a reminder row on an event canceled since, which no cancel of the store\'s replaced, is taken off and sends nothing: a reminder is for an approved event still to come',
	static function () use ( $off_hand ) {
		$written = WPCPM_Sponsor_Event::queue_reminders();
		update_post_meta( $off_hand, '_wpcpm_sevent_state', 'cancelled' );
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		return array( $written, mailed_to(), pending_of( $off_hand ) );
	},
	array( 3, array(), array() )
);

$crowded = seed_event( EDGES, 'approved', $run_at + 32 * HOUR_IN_SECONDS, 60, 'A crowd to remind', $crowd );

ck(
	'the drain reminds a crowd fifty a run, back on the clock while rows remain, as it tells a move\'s: 120 written down in the run, and each of them reminded once',
	static function () use ( $crowded ) {
		$GLOBALS['cron'] = array();
		$written         = WPCPM_Sponsor_Event::queue_reminders();
		$mark            = count( $GLOBALS['all_mail'] );
		$runs            = array();
		for ( $run = 0; $run < 4; $run++ ) {
			$before = count( $GLOBALS['all_mail'] );
			run_cron( 'wpcpm_sevent_drain' );
			$runs[] = array( count( $GLOBALS['all_mail'] ) - $before, count( pending_of( $crowded ) ), count( $GLOBALS['cron'] ) );
		}
		$counts = array_count_values( array_slice( $GLOBALS['all_mail'], $mark ) );
		return array( $written, $runs, count( $counts ), array_values( array_unique( $counts ) ) );
	},
	array( 120, array( array( 50, 70, 1 ), array( 50, 20, 1 ), array( 20, 0, 1 ), array( 0, 0, 0 ) ), 120, array( 1 ) )
);

$nobody_on_it = seed_event( REMINDING, 'approved', $run_at + 34 * HOUR_IN_SECONDS, 30, 'Nobody on it yet', array() );

ck(
	'a member reminded of an event nobody has joined reads that nobody has joined yet, as the dashboards say it, not a count of none',
	static function () use ( $nobody_on_it ) {
		$written         = WPCPM_Sponsor_Event::queue_reminders();
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		return array( $written, mailed_to(), mail_for( 'rhea.reminderrep@people.example' )['body'], pending_of( $nobody_on_it ) );
	},
	array(
		2,
		array( 'rhea.reminderrep@people.example', 'rory.reminderrep@people.example' ),
		"A reminder of your company's event.\n\nTitle: Nobody on it yet\nWhen: June 16, 2027 5:30 pm to 6:00 pm (Europe / Warsaw)\nJoined so far: nobody yet",
		array(),
	)
);

/* ---- the kinds of row still to tell ----------------------------------------------- */

echo "\n=== The kinds of row still to tell ===\n";

$old_on     = seed_event( EDGES, 'approved', $run_at + 3 * DAY_IN_SECONDS, 30, 'An old row, approved', array( STUDENT ) );
$old_off    = seed_event( EDGES, 'cancelled', $run_at + 3 * DAY_IN_SECONDS + HOUR_IN_SECONDS, 30, 'An old row, canceled', array( STUDENT ) );
$change_off = seed_event( EDGES, 'cancelled', $run_at + 3 * DAY_IN_SECONDS + 2 * HOUR_IN_SECONDS, 30, 'A change row, canceled since', array( STUDENT ) );
$foreign    = seed_event( EDGES, 'approved', $run_at + 3 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS, 30, 'A row of another kind', array( STUDENT ) );

ck(
	'a row of the shape before rows carried a kind, a person and a version, is told as the event\'s state says, as such a row always was: a change on an approved event, a cancel on a canceled one',
	static function () use ( $old_on, $old_off ) {
		add_post_meta( $old_on, '_wpcpm_sevent_pending', STUDENT . ':0' );
		add_post_meta( $old_off, '_wpcpm_sevent_pending', STUDENT . ':0' );
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		return array( array_map( static function ( $mail ) { return $mail['subject']; }, $GLOBALS['mail'] ), pending_of( $old_on ), pending_of( $old_off ) );
	},
	array( array( '[WordPress Education] Event changed: An old row, approved', '[WordPress Education] Event canceled: An old row, canceled' ), array(), array() )
);
ck(
	'a change row on an event canceled since is taken off and sends nothing: the cancel\'s own row tells that person',
	static function () use ( $change_off ) {
		add_post_meta( $change_off, '_wpcpm_sevent_pending', STUDENT . ':0:change' );
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		return array( mailed_to(), pending_of( $change_off ) );
	},
	array( array(), array() )
);
ck(
	'a row of a kind the store never writes is left out, and left alone',
	static function () use ( $foreign ) {
		add_post_meta( $foreign, '_wpcpm_sevent_pending', STUDENT . ':0:party' );
		$GLOBALS['mail'] = array();
		run_cron( 'wpcpm_sevent_drain' );
		$left = pending_of( $foreign );
		delete_post_meta( $foreign, '_wpcpm_sevent_pending' );
		return array( mailed_to(), $left );
	},
	array( array(), array( STUDENT . ':0:party' ) )
);

/* ---- a run that fires late, and a lock held at the run ------------------------------ */

echo "\n=== A run that fires late, and a lock held at the run ===\n";

const LATE = 'spoAAAA0000000014'; // Champion, no term, nobody signs in for it: the late and held runs' events.

$GLOBALS['index'][ LATE ] = array( 'record_id' => LATE, 'name' => 'Late Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' );

// The run before fired 30 hours ago, the daily event six hours late, and its window stopped 36 hours
// after it: six hours from now, where this run's window starts. Every event above is over by now.
$late_run    = utc( '2027-07-01 06:00' );
$before_edge = seed_event( LATE, 'approved', $late_run + 5 * HOUR_IN_SECONDS, 30, 'Before the edge', array( STUDENT ) );
$band_first  = seed_event( LATE, 'approved', $late_run + 6 * HOUR_IN_SECONDS, 30, 'On the edge', array( STUDENT ) );
$band_last   = seed_event( LATE, 'approved', $late_run + 11 * HOUR_IN_SECONDS, 30, 'In the band a late run would leave', array( STUDENT ) );
$late_normal = seed_event( LATE, 'approved', $late_run + 20 * HOUR_IN_SECONDS, 30, 'Twenty hours on', array( STUDENT ) );
$late_beyond = seed_event( LATE, 'approved', $late_run + 36 * HOUR_IN_SECONDS, 30, 'Thirty-six hours on', array( STUDENT ) );
set_now( $late_run );
update_option( 'wpcpm_sevent_reminder_edge', $late_run - 30 * HOUR_IN_SECONDS + 36 * HOUR_IN_SECONDS );
$GLOBALS['cron'] = array();

ck(
	'a run 30 hours after the one before starts its window where that one\'s stopped, six hours on rather than twelve: the events in the band between are reminded, one before the edge was the run before\'s, and the window still ends 36 hours on; it records where it stopped and puts no retry on the clock',
	static function () use ( $late_run, $before_edge, $band_first, $band_last, $late_normal, $late_beyond ) {
		$written = WPCPM_Sponsor_Event::queue_reminders();
		$marks   = array();
		foreach ( array( $before_edge, $band_first, $band_last, $late_normal, $late_beyond ) as $id ) {
			$marks[] = meta( $id, '_wpcpm_sevent_reminded' );
		}
		return array( $written, $marks, get_option( 'wpcpm_sevent_reminder_edge' ), wp_next_scheduled( 'wpcpm_sevent_remind_retry' ) );
	},
	array( 3, array( '', '0', '0', '0', '' ), $late_run + 36 * HOUR_IN_SECONDS, false )
);

// The next run on time, a day later: the daily cadence, where the edge is twelve hours after the run.
$on_time     = $late_run + DAY_IN_SECONDS;
$short_of_it = seed_event( LATE, 'approved', $on_time + 12 * HOUR_IN_SECONDS - 60, 30, 'A minute short of the window', array( STUDENT ) );
$at_its_edge = seed_event( LATE, 'approved', $on_time + 12 * HOUR_IN_SECONDS, 30, 'Where the window opens', array( STUDENT ) );
set_now( $on_time );
$GLOBALS['cron'] = array();

ck(
	'a run a day after the one before, the daily cadence, is as it always was: its window opens twelve hours after it, where the last one stopped, and it records where its own stops',
	static function () use ( $on_time, $short_of_it, $at_its_edge, $late_beyond ) {
		$written = WPCPM_Sponsor_Event::queue_reminders();
		return array( $written, meta( $short_of_it, '_wpcpm_sevent_reminded' ), meta( $at_its_edge, '_wpcpm_sevent_reminded' ), meta( $late_beyond, '_wpcpm_sevent_reminded' ), get_option( 'wpcpm_sevent_reminder_edge' ), wp_next_scheduled( 'wpcpm_sevent_remind_retry' ) );
	},
	array( 2, '', '0', '0', $on_time + 36 * HOUR_IN_SECONDS, false )
);

// A run long after the last one, the site down for days, the edge ten hours behind it.
$long_after = $on_time + 3 * DAY_IN_SECONDS;
$running    = seed_event( LATE, 'approved', $long_after - 30 * MINUTE_IN_SECONDS, 90, 'Started half an hour ago', array( STUDENT ) );
$soon       = seed_event( LATE, 'approved', $long_after + 3 * HOUR_IN_SECONDS, 30, 'Three hours on', array( STUDENT ) );
set_now( $long_after );
update_option( 'wpcpm_sevent_reminder_edge', $long_after - 10 * HOUR_IN_SECONDS );

ck(
	'a run later still reaches back no further than its own moment: with the edge ten hours behind it, the event three hours on is reminded, closer than the day before, and the one that started half an hour ago is past reminding',
	static function () use ( $long_after, $running, $soon ) {
		$written = WPCPM_Sponsor_Event::queue_reminders();
		return array( $written, meta( $running, '_wpcpm_sevent_reminded' ), meta( $soon, '_wpcpm_sevent_reminded' ), get_option( 'wpcpm_sevent_reminder_edge' ) );
	},
	array( 1, '', '0', $long_after + 36 * HOUR_IN_SECONDS )
);

// A run on time while another write holds Late Example's lock. The event it meets starts a minute
// past where the window opens, which a window counted afresh five minutes later would leave out.
$held_run   = $long_after + DAY_IN_SECONDS;
$held_event = seed_event( LATE, 'approved', $held_run + 12 * HOUR_IN_SECONDS + 60, 30, 'Met under a held lock', array( STUDENT ) );
set_now( $held_run );
update_option( 'wpcpm_sevent_reminder_edge', $held_run + 12 * HOUR_IN_SECONDS );
update_option( 'wpcpm_sevent_lock_' . LATE, $held_run );
$GLOBALS['cron'] = array();

ck(
	'a lock another write holds at the run\'s moment leaves its event unmarked with nobody written down, keeps the edge where the window began, and puts one retry on the clock, five minutes out, however often a run meets the lock',
	static function () use ( $held_run, $held_event ) {
		$first   = WPCPM_Sponsor_Event::queue_reminders();
		$second  = WPCPM_Sponsor_Event::queue_reminders();
		$retries = array_values( array_filter( $GLOBALS['cron'], static function ( $event ) { return 'wpcpm_sevent_remind_retry' === $event['hook']; } ) );
		return array( $first, $second, meta( $held_event, '_wpcpm_sevent_reminded' ), pending_of( $held_event ), get_option( 'wpcpm_sevent_reminder_edge' ), $retries );
	},
	array( 0, 0, '', array(), $held_run + 12 * HOUR_IN_SECONDS, array( array( 'hook' => 'wpcpm_sevent_remind_retry', 'at' => $held_run + 300 ) ) )
);

// The lock given back, and the retry due five minutes on.
delete_option( 'wpcpm_sevent_lock_' . LATE );
set_now( $held_run + 300 );

ck(
	'the retry covers the same window: it reminds the event the run left, a minute past where that window opened, moves the edge on to where its own window stops, and leaves no retry on the clock',
	static function () use ( $held_run, $held_event ) {
		run_cron( 'wpcpm_sevent_remind_retry' );
		return array( meta( $held_event, '_wpcpm_sevent_reminded' ), pending_of( $held_event ), get_option( 'wpcpm_sevent_reminder_edge' ), wp_next_scheduled( 'wpcpm_sevent_remind_retry' ) );
	},
	array( '0', array( STUDENT . ':0:reminder' ), $held_run + 300 + 36 * HOUR_IN_SECONDS, false )
);

$clean_run = $held_run + DAY_IN_SECONDS;
set_now( $clean_run );
$GLOBALS['cron'] = array();

ck(
	'a run that leaves nothing for a retry moves the edge on to where its window stops and puts no retry on the clock',
	static function () use ( $clean_run ) {
		WPCPM_Sponsor_Event::queue_reminders();
		return array( get_option( 'wpcpm_sevent_reminder_edge' ), wp_next_scheduled( 'wpcpm_sevent_remind_retry' ) );
	},
	array( $clean_run + 36 * HOUR_IN_SECONDS, false )
);

// The first run ever meeting a held lock: no edge on record, and an event a minute past where its
// window opens.
$first_held       = $clean_run + DAY_IN_SECONDS;
$first_held_event = seed_event( LATE, 'approved', $first_held + 12 * HOUR_IN_SECONDS + 60, 30, 'Met by the first run under a held lock', array( STUDENT ) );
set_now( $first_held );
delete_option( 'wpcpm_sevent_reminder_edge' );
update_option( 'wpcpm_sevent_lock_' . LATE, $first_held );
$GLOBALS['cron'] = array();

ck(
	'the first run ever, meeting a held lock, records where its own window began as the edge, so its retry covers the same window and reminds the event',
	static function () use ( $first_held, $first_held_event ) {
		WPCPM_Sponsor_Event::queue_reminders();
		$kept = get_option( 'wpcpm_sevent_reminder_edge', 'none' );
		delete_option( 'wpcpm_sevent_lock_' . LATE );
		set_now( $first_held + 300 );
		run_cron( 'wpcpm_sevent_remind_retry' );
		return array( $kept, meta( $first_held_event, '_wpcpm_sevent_reminded' ), get_option( 'wpcpm_sevent_reminder_edge', 'none' ) );
	},
	array( $first_held + 12 * HOUR_IN_SECONDS, '0', $first_held + 300 + 36 * HOUR_IN_SECONDS )
);

delete_option( 'wpcpm_sevent_lock_' . LATE );

// The write under the lock, asked again there: three events in the next run's window, a day on.
$guard_run      = $first_held + DAY_IN_SECONDS;
$read_again     = seed_event( LATE, 'approved', $guard_run + 20 * HOUR_IN_SECONDS, 30, 'Read again under the lock', array( STUDENT ) );
$marked_between = seed_event( LATE, 'approved', $guard_run + 21 * HOUR_IN_SECONDS, 30, 'Marked by another run meanwhile', array( STUDENT ) );
$one_gone       = seed_event( LATE, 'approved', $guard_run + 22 * HOUR_IN_SECONDS, 30, 'One of its people gone', array( STUDENT, 7777 ) );
set_now( $guard_run );
$GLOBALS['cron']       = array();
$GLOBALS['forgotten']  = array();
$GLOBALS['claim_hook'] = array(
	'wpcpm_sevent_lock_' . LATE,
	// Another run, a retry or a server cron beside WP-Cron, marks the second event between this run's
	// walk, which found it unmarked, and its lock: the second lock this run claims is that event's.
	static function () use ( $marked_between ) { update_post_meta( $marked_between, '_wpcpm_sevent_reminded', 3 ); },
	2,
);
$guarded = attempt( static function () { return WPCPM_Sponsor_Event::queue_reminders(); } );
unset( $GLOBALS['claim_hook'] );

ck(
	'under the lock the event is read again, past this request\'s copy of it and its meta',
	static function () use ( $read_again ) { return array( in_array( 'post_meta:' . $read_again, $GLOBALS['forgotten'], true ), pending_of( $read_again ) ); },
	array( true, array( STUDENT . ':0:reminder' ) )
);
ck(
	'and asked again there: an event another run marked between this run\'s walk and its lock writes nobody down, and keeps the other run\'s mark',
	static function () use ( $marked_between ) { return array( pending_of( $marked_between ), meta( $marked_between, '_wpcpm_sevent_reminded' ) ); },
	array( array(), '3' )
);
ck(
	'an account that is gone is nobody to remind: the person on the event is written down, and no row names the account',
	static function () use ( $one_gone, $guarded ) { return array( pending_of( $one_gone ), $guarded ); },
	array( array( STUDENT . ':0:reminder' ), 2 )
);

// A run the host cuts off inside the walk, at the second row of the event it is writing down.
$cut_run   = $guard_run + DAY_IN_SECONDS;
$cut_short = seed_event( LATE, 'approved', $cut_run + 20 * HOUR_IN_SECONDS, 30, 'A run cut off part way', array( STUDENT, MENTOR ) );
set_now( $cut_run );
$GLOBALS['cron']        = array();
$GLOBALS['rows_die_at'] = 2;
$cut_off                = '';

try {
	WPCPM_Sponsor_Event::queue_reminders();
} catch ( RuntimeException $e ) {
	$cut_off = $e->getMessage();
}

unset( $GLOBALS['rows_die_at'] );
delete_option( 'wpcpm_sevent_lock_' . LATE );

ck(
	'a run cut off part way through the rows it writes has put the drain on the clock before the first of them, so the rows it wrote are told within minutes and not a day later',
	static function () use ( $cut_off, $cut_short, $cut_run ) { return array( $cut_off, pending_of( $cut_short ), wp_next_scheduled( 'wpcpm_sevent_drain' ) ); },
	array( 'the request died writing the rows', array( STUDENT . ':0:reminder' ), $cut_run + 60 )
);

// Whatever the runs above left for the drain is told, or dropped for an event that is over.
for ( $run = 0; $run < 3 && array() !== all_pending(); $run++ ) {
	run_cron( 'wpcpm_sevent_drain' );
}

unset( $GLOBALS['filters']['wpcpm_mail'] );
$GLOBALS['site_tz'] = 'UTC';
$GLOBALS['cron']    = array();

/* ---- the program's own events ------------------------------------------------------ */

echo "\n=== The program's own events ===\n";

// The program plans events of its own on the Administrator Dashboard's card, the partners session
// and the launch communications among them: hosted by the WordPress Credits Program, with no
// sponsor, approved as they are planned. Every event above is over by 2 August 2027, so the events
// still to come are the ones planned here, beside one of the Champion's.
set_now( utc( '2027-08-02 09:00' ) );
delete_option( 'wpcpm_sevent_reminder_edge' );
$GLOBALS['cron']          = array();
$GLOBALS['mail']          = array();
$GLOBALS['program_audit'] = array();
$program_mails            = array();

/** The fields of an event of the program's own, as the Administrator Dashboard's card hands them to the store. */
function program_fields( $start, array $over = array() ) {
	return event_fields( $start, array_merge( array( 'title' => 'The partners session', 'description' => 'What the program plans with its partners, Brightquill.', 'link' => 'https://call.example/partners-room-777' ), $over ) );
}

/**
 * An event of the program's own written into the store the way `plan_program()` writes one, and, when
 * a key is given, with a sponsor's key on it as a row written by hand would hold one, which no write
 * of the store's makes.
 */
function seed_program_event( $state, $start, $minutes, $title, array $people = array(), $record = '' ) {
	$id = seed_event( $record, $state, $start, $minutes, $title, $people );
	update_post_meta( $id, '_wpcpm_sevent_kind', 'program' );
	update_post_meta( $id, '_wpcpm_sevent_decision', array( 'at' => $GLOBALS['now'], 'by' => MANAGER, 'note' => '' ) );
	if ( '' === $record ) { unset( $GLOBALS['pmeta'][ $id ]['_wpcpm_sevent_sponsor'] ); }
	return $id;
}

/** What a refused plan of the program's could have written: the posts, their meta, the options, the trail and the mail. */
$program_state = static function () { return md5( serialize( array( $GLOBALS['posts'], $GLOBALS['pmeta'], $GLOBALS['opts'], $GLOBALS['audit'], $GLOBALS['all_mail'] ) ) ); };

ck(
	'the program\'s own kind, beside the co-hosted one a sponsor plans, and the one lock its events are written under, the option wpcpm_sevent_lock_program, which no sponsor\'s key can name',
	static function () { return array( WPCPM_Sponsor_Event::KIND_PROGRAM, WPCPM_Sponsor_Event::KIND_CO_HOSTED, WPCPM_Sponsor_Event::LOCK_PREFIX . WPCPM_Sponsor_Event::PROGRAM_LOCK, WPCPM_Sponsors_Index::is_key( WPCPM_Sponsor_Event::PROGRAM_LOCK ) ); },
	array( 'program', 'co_hosted', 'wpcpm_sevent_lock_program', false )
);

// The calendar file names the program's sender as its organizer whatever the event's kind: pinned on
// an event of the program's own as its plan writes one, beside the co-hosted events' pin above.
$seeded_program = seed_program_event( 'approved', utc( '2027-09-01 10:00' ), 60, 'A seeded event of the program\'s', array( STUDENT ) );

ck(
	'the calendar file of an event of the program\'s own names the program\'s sender as its organizer, as a co-hosted event\'s does, and the person it goes to as its attendee, and nobody else',
	static function () use ( $seeded_program ) {
		$files = WPCPM_Sponsor_Event::calendar_file( get_post( $seeded_program ), '', $GLOBALS['users'][ STUDENT ] );
		$text  = ics_of( $files );
		if ( isset( $files[0] ) ) { WPCPM_ICS::cleanup( $files[0] ); }
		return array( false !== strpos( $text, "ORGANIZER;CN=\"WordPress\":mailto:wordpress@site.example\r\n" ), attendees_but( $text, 'wordpress@site.example' ), others_named( $text, STUDENT ) );
	},
	array( true, array( attendee_line( STUDENT ) ), array() )
);

attempt( static function () use ( $seeded_program ) { wp_delete_post( $seeded_program, true ); } );

$GLOBALS['purged'] = array();

ck(
	'plan_program() is a program manager\'s: a student, a sponsor\'s member and nobody are refused not_allowed, and fields the store cannot hold are refused, nothing written',
	static function () use ( $program_state ) {
		$before = $program_state();
		$out    = array(
			code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-20 15:00' ) ), STUDENT ) ),
			code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-20 15:00' ) ), PARTNER_REP ) ),
			code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-20 15:00' ) ), 0 ) ),
			code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-20 15:00' ), array( 'minutes' => 5 ) ), MANAGER ) ),
			code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-20 15:00' ), array( 'link' => 'ftp://files.example/x' ) ), MANAGER ) ),
		);
		return array( $out, $program_state() === $before );
	},
	array( array( 'not_allowed', 'not_allowed', 'not_allowed', 'fields', 'fields' ), true )
);
ck(
	'with the switch off the program plans nothing, and a start that has passed, or is this very moment, is in_past: nothing written, and the program\'s lock given back',
	static function () use ( $program_state ) {
		$before                                    = $program_state();
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$off                                       = code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-20 15:00' ) ), MANAGER ) );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		$past                                      = array( code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-02 08:00' ) ), MANAGER ) ), code_of( WPCPM_Sponsor_Event::plan_program( program_fields( $GLOBALS['now'] ), MANAGER ) ) );
		return array( $off, $past, $program_state() === $before, get_option( 'wpcpm_sevent_lock_program', 'gone' ) );
	},
	array( 'switch_off', array( 'in_past', 'in_past' ), true, 'gone' )
);
ck(
	'the program\'s lock another request holds turns a plan away as busy, writes nothing and leaves the lock where it is',
	static function () use ( $program_state ) {
		update_option( 'wpcpm_sevent_lock_program', $GLOBALS['now'] - 120 );
		$posts = count( $GLOBALS['posts'] );
		$code  = code_of( WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-20 15:00' ) ), MANAGER ) );
		$left  = array( $code, count( $GLOBALS['posts'] ) - $posts, get_option( 'wpcpm_sevent_lock_program' ) );
		delete_option( 'wpcpm_sevent_lock_program' );
		return $left;
	},
	array( 'busy', 0, $GLOBALS['now'] - 120 )
);

$pe_start          = utc( '2027-08-20 15:00' );
$pe_mails          = count( $GLOBALS['all_mail'] );
$purged_by_refused = $GLOBALS['purged'];
$GLOBALS['purged'] = array();
$pe                = attempt( static function () use ( $pe_start ) { return WPCPM_Sponsor_Event::plan_program( program_fields( $pe_start, array( 'places' => 3 ) ), MANAGER ); } );

ck( 'a program manager plans the program\'s own event: its post ID, and nobody mailed', array( is_int( $pe ) && $pe > 0, count( $GLOBALS['all_mail'] ) - $pe_mails ), array( true, 0 ) );
ck(
	'and the host\'s page cache is told the Partners page changed, since the program\'s own event is listed there as it is planned, approved; the plans refused above told it nothing',
	static function () use ( $purged_by_refused ) { return array( $GLOBALS['purged'], $purged_by_refused ); },
	array( array( SPONSORS_PAGE ), array() )
);
ck(
	'a private post of the type, written by the manager, its title and its description; its meta: no sponsor at all, the program\'s kind, the start and the end in UTC, the zone, the minutes, the places, the link, approved as it is planned, version 0, nobody on it, and the decision stamped with the moment and the manager',
	static function () use ( $pe, $pe_start ) {
		$post = get_post( $pe );
		return array( $post->post_type, $post->post_status, (int) $post->post_author, $post->post_title, $post->post_content, array_key_exists( '_wpcpm_sevent_sponsor', $GLOBALS['pmeta'][ $pe ] ), meta( $pe, '_wpcpm_sevent_kind' ), meta( $pe, '_wpcpm_sevent_start' ), meta( $pe, '_wpcpm_sevent_end' ), meta( $pe, '_wpcpm_sevent_zone' ), meta( $pe, '_wpcpm_sevent_minutes' ), meta( $pe, '_wpcpm_sevent_places' ), meta( $pe, '_wpcpm_sevent_link' ), meta( $pe, '_wpcpm_sevent_state' ), meta( $pe, '_wpcpm_sevent_revision' ), get_post_meta( $pe, '_wpcpm_sevent_person', false ), meta( $pe, '_wpcpm_sevent_decision' ) );
	},
	array( 'wpcpm_sponsor_event', 'private', MANAGER, 'The partners session', 'What the program plans with its partners, Brightquill.', false, 'program', (string) $pe_start, (string) ( $pe_start + HOUR_IN_SECONDS ), 'UTC', '60', '3', 'https://call.example/partners-room-777', 'approved', '0', array(), array( 'at' => $GLOBALS['now'], 'by' => MANAGER, 'note' => '' ) )
);
ck(
	'a seeded event of the program\'s own carries the keys plan_program() writes, and no other',
	static function () use ( $pe ) {
		$seeded  = seed_program_event( 'approved', utc( '2027-09-02 10:00' ), 60, 'Seeded to be compared' );
		$keys    = array_keys( $GLOBALS['pmeta'][ $seeded ] );
		$written = array_keys( $GLOBALS['pmeta'][ $pe ] );
		wp_delete_post( $seeded, true );
		sort( $keys );
		sort( $written );
		return array( count( $written ) > 5, $keys === $written );
	},
	array( true, true )
);
ck(
	'one row on the trail, through the writer of the program\'s own rows, which the real writer accepts: planned, on the manager ground, by the manager, the day in its words, the start, the minutes, the places and the people in its facts, and no sponsor',
	static function () use ( $pe, $pe_start ) { $rows = $GLOBALS['program_audit']; return array( count( $rows ), end( $rows ), $GLOBALS['refused'] ); },
	array(
		1,
		array(
			'kind'     => 'sponsor_event_planned',
			'subject'  => id_text( $pe ),
			'actor'    => MANAGER,
			'ground'   => 'manager',
			'evidence' => 'index',
			'message'  => 'A program event was planned for August 20, 2027.',
			'data'     => array(
				'start'   => $pe_start,
				'minutes' => 60,
				'places'  => 3,
				'joined'  => 0,
			),
		),
		array(),
	)
);
ck( 'the program\'s lock is given back once the event is written, and no sponsor\'s lock was taken', static function () use ( $pe ) { return array( is_int( $pe ), array_values( preg_grep( '/^wpcpm_sevent_lock_/', array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ) ); }, array( true, array() ) );
ck(
	'facts(): no sponsor, the WordPress Credits Program as the host, the program\'s kind, approved and stamped, and neither the link nor anybody\'s name',
	static function () use ( $pe ) {
		$facts = WPCPM_Sponsor_Event::facts( get_post( $pe ) );
		return array( $facts['sponsor'], $facts['host'], $facts['kind'], $facts['state'], $facts['decision'], found_in( serialize( $facts ), array( 'call.example', 'partners-room', 'Mira Manager' ) ) );
	},
	array( '', 'the WordPress Credits Program', 'program', 'approved', array( 'at' => $GLOBALS['now'], 'by' => MANAGER, 'note' => '' ), array() )
);
ck(
	'sponsor_of() is nobody\'s key and is_program() says so; offers_events() asked of the event is true, while an empty key still offers nothing',
	static function () use ( $pe ) { $event = get_post( $pe ); return array( WPCPM_Sponsor_Event::sponsor_of( $event ), WPCPM_Sponsor_Event::is_program( $event ), WPCPM_Sponsor_Event::offers_events( $event ), WPCPM_Sponsor_Event::offers_events( '' ) ); },
	array( '', true, true, false )
);

$pe2 = attempt( static function () { return WPCPM_Sponsor_Event::plan_program( program_fields( utc( '2027-08-25 16:00' ), array( 'title' => 'The launch communications', 'description' => 'How the program tells the world, Brightquill.', 'link' => 'https://call.example/launch-room-888' ) ), MANAGER ); } );

ck(
	'none of a sponsor\'s rules holds the program: a second event of its own is planned while the first is still to come; neither waits for an approval; neither is any sponsor\'s, so the Champion may plan its own beside them',
	static function () use ( $pe, $pe2, $row ) { return array( is_int( $pe2 ), ids( WPCPM_Sponsor_Event::approved_upcoming() ) === array( $pe, $pe2 ), ids( WPCPM_Sponsor_Event::waiting() ), ids( WPCPM_Sponsor_Event::upcoming_of( CHAMPION ) ), WPCPM_Sponsor_Event::may_plan( CHAMPION, $row( CHAMPION ), utc( '2027-08-22 10:00' ) ) ); },
	array( true, true, array(), array(), '' )
);

$ce = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2027-08-22 10:00' ), array( 'title' => 'A webinar beside the program\'s' ) ), CHAMPION_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

ck(
	'open_for(): the program\'s events are on the list of every audience, soonest first beside a sponsor\'s; a mentor who acts for the Partner sees them too, being nobody\'s own; a graduate and a sponsor\'s member in no audience see nothing',
	static function () use ( $pe, $pe2, $ce ) {
		$out = array();
		foreach ( array( STUDENT, MENTOR, CONTACT, MANAGER, MENTOR_REP, GRADUATE, PARTNER_REP ) as $who ) {
			$out[] = ids( WPCPM_Sponsor_Event::open_for( $who ) );
		}
		return array( is_int( $ce ), $out );
	},
	array( true, array( array( $pe, $ce, $pe2 ), array( $pe, $ce, $pe2 ), array( $pe, $ce, $pe2 ), array( $pe, $ce, $pe2 ), array( $pe, $ce, $pe2 ), array(), array() ) )
);

$GLOBALS['forgotten'] = array();

ck(
	'the Partner\'s mentor joins the program\'s event, which is not its own, and so do a student and an institution contact; the fourth finds its three places taken',
	static function () use ( $pe ) { return array( WPCPM_Sponsor_Event::join( $pe, MENTOR_REP ), WPCPM_Sponsor_Event::join( $pe, STUDENT ), WPCPM_Sponsor_Event::join( $pe, CONTACT ), code_of( WPCPM_Sponsor_Event::join( $pe, MANAGER ) ), WPCPM_Sponsor_Event::joiners( $pe ), meta( $pe, '_wpcpm_sevent_revision' ) ); },
	array( true, true, true, 'full', array( MENTOR_REP, STUDENT, CONTACT ), '3' )
);
ck( 'each join read the event again under the program\'s lock and gave the lock back', static function () use ( $pe ) { return array( in_array( 'post_meta:' . $pe, $GLOBALS['forgotten'], true ), get_option( 'wpcpm_sevent_lock_program', 'gone' ) ); }, array( true, 'gone' ) );
ck( 'a sponsor\'s member in no audience, and a graduate, are nobody the program\'s events are for', static function () use ( $pe2 ) { return array( code_of( WPCPM_Sponsor_Event::join( $pe2, PARTNER_REP ) ), code_of( WPCPM_Sponsor_Event::join( $pe2, GRADUATE ) ) ); }, array( 'audience', 'audience' ) );
ck(
	'and the refusal says so in words that fit the program\'s events as well as a sponsor\'s',
	static function () use ( $pe2 ) { $refused = WPCPM_Sponsor_Event::join( $pe2, PARTNER_REP ); return is_wp_error( $refused ) ? $refused->get_error_message() : 'no refusal'; },
	'These events are for the program\'s students, mentors, Administrators and institutions.'
);
ck(
	'the program\'s lock another write holds turns a join and a leave away as busy and is left where it is; a sponsor\'s lock is no bar to them',
	static function () use ( $pe ) {
		update_option( 'wpcpm_sevent_lock_program', $GLOBALS['now'] - 120 );
		$held = array( code_of( WPCPM_Sponsor_Event::join( $pe, WALKER ) ), code_of( WPCPM_Sponsor_Event::leave( $pe, CONTACT ) ), get_option( 'wpcpm_sevent_lock_program' ) );
		delete_option( 'wpcpm_sevent_lock_program' );
		update_option( 'wpcpm_sevent_lock_' . CHAMPION, $GLOBALS['now'] - 120 );
		$left = WPCPM_Sponsor_Event::leave( $pe, CONTACT );
		delete_option( 'wpcpm_sevent_lock_' . CHAMPION );
		return array( $held, $left, WPCPM_Sponsor_Event::joiners( $pe ), meta( $pe, '_wpcpm_sevent_revision' ) );
	},
	array( array( 'busy', 'busy', $GLOBALS['now'] - 120 ), true, array( MENTOR_REP, STUDENT ), '4' )
);

$GLOBALS['mail'] = array();
$pe_moved        = utc( '2027-08-21 17:00' );

ck(
	'a program manager moves it: still approved, the version raised, and the two people on it told the new time in the press',
	static function () use ( $pe, $pe_moved ) { $answer = WPCPM_Sponsor_Event::change( $pe, program_fields( $pe_moved, array( 'places' => 3 ) ), MANAGER ); return array( $answer, meta( $pe, '_wpcpm_sevent_state' ), meta( $pe, '_wpcpm_sevent_start' ), meta( $pe, '_wpcpm_sevent_revision' ), mailed_to(), get_option( 'wpcpm_sevent_lock_program', 'gone' ) ); },
	array( array( 'changed' => true, 'told' => 2, 'pending' => 0 ), 'approved', (string) $pe_moved, '5', array( 'mel.mentorrep@people.example', 'sam.current@people.example' ), 'gone' )
);
ck(
	'the mail names the WordPress Credits Program as the host where a sponsor\'s mail names the sponsor, on the person\'s own clock, with the file that replaces theirs',
	static function () { $mail = mail_for( 'sam.current@people.example' ); return array( $mail['subject'], $mail['body'], count( $mail['files'] ) ); },
	array(
		'[WordPress Education] Event changed: The partners session',
		"An event you joined has changed. Here is what it is now:\n\n"
			. "Title: The partners session\nHosted by the WordPress Credits Program\nWhen: August 21, 2027 1:00 pm to 2:00 pm (America / New York)\nLength: 60 minutes\nJoin the call: https://call.example/partners-room-777\n\n"
			. 'The calendar file attached replaces the one you had.',
		1,
	)
);
ck(
	'the row: changed, on the manager ground, through the program\'s writer, the new day in its words, naming no sponsor',
	static function () { $rows = $GLOBALS['program_audit']; $last = end( $rows ); return array( $last['kind'], $last['ground'], $last['actor'], $last['message'], array_key_exists( 'sponsor', $last ) ); },
	array( 'sponsor_event_changed', 'manager', MANAGER, 'The program event was changed; it takes place on August 21, 2027.', false )
);

$program_mails   = array_merge( $program_mails, (array) $GLOBALS['mail'] );
$GLOBALS['mail'] = array();

ck(
	'no term binds it: moved a year and more ahead, it is changed; a start that has passed is in_past and places below the people on it shrink, and nothing moves on either',
	static function () use ( $pe, $pe_moved ) {
		$far    = WPCPM_Sponsor_Event::change( $pe, program_fields( utc( '2029-03-01 10:00' ), array( 'places' => 3 ) ), MANAGER );
		$before = array( meta( $pe, '_wpcpm_sevent_start' ), meta( $pe, '_wpcpm_sevent_revision' ) );
		$past   = code_of( WPCPM_Sponsor_Event::change( $pe, program_fields( utc( '2027-08-01 10:00' ), array( 'places' => 3 ) ), MANAGER ) );
		$shrink = code_of( WPCPM_Sponsor_Event::change( $pe, program_fields( utc( '2029-03-01 10:00' ), array( 'places' => 1 ) ), MANAGER ) );
		$same   = array( meta( $pe, '_wpcpm_sevent_start' ), meta( $pe, '_wpcpm_sevent_revision' ) ) === $before;
		$back   = WPCPM_Sponsor_Event::change( $pe, program_fields( $pe_moved, array( 'places' => 3 ) ), MANAGER );
		return array( $far['changed'], $past, $shrink, $same, $back['changed'], meta( $pe, '_wpcpm_sevent_start' ), get_option( 'wpcpm_sevent_lock_program', 'gone' ) );
	},
	array( true, 'in_past', 'shrink', true, true, (string) $pe_moved, 'gone' )
);

$program_mails   = array_merge( $program_mails, (array) $GLOBALS['mail'] );
$GLOBALS['mail'] = array();

// The daily run, twenty hours before the program's event: the Champion's is 37 hours away, past the
// window, and the second of the program's three days and more.
set_now( utc( '2027-08-20 21:00' ) );
$GLOBALS['cron'] = array();

ck(
	'the daily run reminds the people on the program\'s event, and them alone, since no sponsor\'s members stand behind it: a reminder row each at its version, the mark, and the drain on the clock',
	static function () use ( $pe ) {
		$written = WPCPM_Sponsor_Event::queue_reminders( '2027-08-20' );
		$rev     = (string) meta( $pe, '_wpcpm_sevent_revision' );
		return array( $written, pending_of( $pe ) === array( MENTOR_REP . ':' . $rev . ':reminder', STUDENT . ':' . $rev . ':reminder' ), meta( $pe, '_wpcpm_sevent_reminded' ) === $rev, array_column( $GLOBALS['cron'], 'hook' ), get_option( 'wpcpm_sevent_lock_program', 'gone' ) );
	},
	array( 2, true, true, array( 'wpcpm_sevent_drain' ), 'gone' )
);
ck(
	'the drain reminds each of them that the WordPress Credits Program hosts it, with the title, when on their own clock, the length and the call link, and no calendar file',
	static function () {
		run_cron( 'wpcpm_sevent_drain' );
		$mail = mail_for( 'sam.current@people.example' );
		return array( mailed_to(), $mail['subject'], $mail['body'], $mail['files'], all_pending() );
	},
	array(
		array( 'mel.mentorrep@people.example', 'sam.current@people.example' ),
		'[WordPress Education] Reminder: The partners session',
		"A reminder of an event hosted by the WordPress Credits Program, which you joined.\n\n"
			. "Title: The partners session\nWhen: August 21, 2027 1:00 pm to 2:00 pm (America / New York)\nLength: 60 minutes\nJoin the call: https://call.example/partners-room-777",
		array(),
		array(),
	)
);

$program_mails   = array_merge( $program_mails, (array) $GLOBALS['mail'] );
$GLOBALS['mail'] = array();

// Somebody joins the second of the program's events, and the run on 24 August meets its lock held.
attempt( static function () use ( $pe2 ) { return WPCPM_Sponsor_Event::join( $pe2, WALKER ); } );
set_now( utc( '2027-08-24 18:00' ) );
update_option( 'wpcpm_sevent_lock_program', $GLOBALS['now'] );
$GLOBALS['cron'] = array();

ck(
	'the program\'s lock held at the run leaves its event unmarked, nobody written down, and puts the one retry on the clock; the retry, the lock given back, reminds its one person',
	static function () use ( $pe2 ) {
		$first = WPCPM_Sponsor_Event::queue_reminders();
		$held  = array( $first, meta( $pe2, '_wpcpm_sevent_reminded' ), pending_of( $pe2 ), array_column( $GLOBALS['cron'], 'hook' ) );
		delete_option( 'wpcpm_sevent_lock_program' );
		set_now( $GLOBALS['now'] + 300 );
		run_cron( 'wpcpm_sevent_remind_retry' );
		$rev = (string) meta( $pe2, '_wpcpm_sevent_revision' );
		return array( $held, meta( $pe2, '_wpcpm_sevent_reminded' ) === $rev, pending_of( $pe2 ) === array( WALKER . ':' . $rev . ':reminder' ) );
	},
	array( array( 0, '', array(), array( 'wpcpm_sevent_remind_retry' ) ), true, true )
);

attempt( static function () { run_cron( 'wpcpm_sevent_drain' ); } );

ck(
	'and the drain reminds that person, on the site\'s clock for want of one of theirs',
	static function () { return array( mailed_to(), mail_for( 'zelda.privacywalk@people.example' )['body'] ); },
	array(
		array( 'zelda.privacywalk@people.example' ),
		"A reminder of an event hosted by the WordPress Credits Program, which you joined.\n\n"
			. "Title: The launch communications\nWhen: August 25, 2027 4:00 pm to 5:00 pm (UTC)\nLength: 60 minutes\nJoin the call: https://call.example/launch-room-888",
	)
);

$program_mails   = array_merge( $program_mails, (array) $GLOBALS['mail'] );
$GLOBALS['mail'] = array();

ck(
	'a program manager cancels the program\'s event: the person on it is told, with the file that takes it off their calendar, in a mail that names the program as the host, and the row goes through the program\'s writer',
	static function () use ( $pe2 ) {
		$answer = WPCPM_Sponsor_Event::cancel( $pe2, MANAGER );
		$mail   = mail_for( 'zelda.privacywalk@people.example' );
		$rows   = $GLOBALS['program_audit'];
		$last   = end( $rows );
		return array( $answer, meta( $pe2, '_wpcpm_sevent_state' ), $mail['subject'], $mail['body'], count( $mail['files'] ), $last['kind'], $last['ground'], $last['message'], get_option( 'wpcpm_sevent_lock_program', 'gone' ) );
	},
	array(
		array( 'told' => 1, 'pending' => 0 ),
		'cancelled',
		'[WordPress Education] Event canceled: The launch communications',
		"An event you joined is canceled.\n\n"
			. "Title: The launch communications\nHosted by the WordPress Credits Program\nWhen: August 25, 2027 4:00 pm to 5:00 pm (UTC)\n\n"
			. 'The calendar file attached takes it off your calendar.',
		1,
		'sponsor_event_cancelled',
		'manager',
		'The program event on August 25, 2027 was canceled.',
		'gone',
	)
);
ck( 'and it takes no move once canceled, and is no approval\'s or return\'s', static function () use ( $pe, $pe2 ) { return array( code_of( WPCPM_Sponsor_Event::cancel( $pe2, MANAGER ) ), code_of( WPCPM_Sponsor_Event::approve( $pe, MANAGER ) ), code_of( WPCPM_Sponsor_Event::return_with_note( $pe, MANAGER, 'A note.' ) ), get_option( 'wpcpm_sevent_lock_program', 'gone' ) ); }, array( 'cancelled', 'not_planned', 'not_planned', 'gone' ) );

$program_mails   = array_merge( $program_mails, (array) $GLOBALS['mail'] );
$GLOBALS['mail'] = array();

// Two of the program's events with the Held Champion's key written on them by hand, as no write of
// the store's makes: one held in July, one still to come.
$stray_held  = seed_program_event( 'approved', utc( '2027-07-15 10:00' ), 60, 'Held by the program in July', array( WALKER ), HELD );
$stray_ahead = seed_program_event( 'approved', utc( '2027-09-10 10:00' ), 60, 'The program\'s, still to come', array(), HELD );

ck(
	'a program event is no sponsor\'s whatever its rows hold: with a sponsor\'s key on it, sponsor_of() is still nobody\'s, neither the sponsor\'s card nor its annual report lists it, and the one still to come does not hold the sponsor to one event at a time',
	static function () use ( $stray_held, $stray_ahead, $row ) {
		return array( WPCPM_Sponsor_Event::sponsor_of( get_post( $stray_held ) ), WPCPM_Sponsor_Event::held_in( HELD, '2027-07-01', '2027-07-31' ), ids( WPCPM_Sponsor_Event::past_of( HELD ) ), ids( WPCPM_Sponsor_Event::upcoming_of( HELD ) ), WPCPM_Sponsor_Event::may_plan( HELD, $row( HELD ), utc( '2027-09-20 10:00' ) ), is_int( $stray_ahead ) );
	},
	array( '', array(), array(), array(), '', true )
);

attempt(
	static function () use ( $stray_held, $stray_ahead ) {
		wp_delete_post( $stray_held, true );
		wp_delete_post( $stray_ahead, true );
	}
);

ck(
	'the privacy walk: every row of the program\'s own holds no joiner\'s name or address, no link, no description and no title, names no sponsor, and holds the start, the minutes, the places and the count as its facts, each a row the real writer accepts',
	static function () use ( $people ) {
		$found = array();
		$keys  = array();
		$named = 0;
		foreach ( $GLOBALS['program_audit'] as $entry ) {
			$found  = array_merge( $found, found_in( $entry['message'] . ' ' . json_encode( $entry['data'] ), array_merge( $people, array( 'call.example', 'partners-room', 'launch-room', 'Brightquill', 'The partners session', 'The launch communications' ) ) ) );
			$keys[] = implode( ',', array_keys( $entry['data'] ) );
			$named += array_key_exists( 'sponsor', $entry ) ? 1 : 0;
		}
		return array( count( $GLOBALS['program_audit'] ) >= 6, array_values( array_unique( $found ) ), array_values( array_unique( $keys ) ), $named, $GLOBALS['refused'] );
	},
	array( true, array(), array( 'start,minutes,places,joined' ), 0, array() )
);
ck(
	'and no mail to a person on one of them names anybody else, a manager included, or gives the description',
	static function () use ( $program_mails ) {
		$leaks = array();
		foreach ( $program_mails as $mail ) {
			$text  = $mail['subject'] . "\n" . $mail['body'] . "\n" . serialize( $mail['headers'] ) . "\n" . ( isset( $mail['files'][0]['ics'] ) ? $mail['files'][0]['ics'] : '' );
			$leaks = array_merge( $leaks, found_in( $mail['subject'] . "\n" . $mail['body'], array( 'Brightquill' ) ) );
			foreach ( $GLOBALS['users'] as $user ) {
				if ( $user->user_email === $mail['to'] ) { continue; }
				foreach ( array( $user->display_name, $user->user_email ) as $needle ) {
					if ( false !== stripos( $text, $needle ) ) { $leaks[] = $mail['to'] . ': ' . $needle; }
				}
			}
		}
		return array( count( $program_mails ) >= 7, $leaks );
	},
	array( true, array() )
);

update_option( 'wpcpm_sevent_lock_program', $GLOBALS['now'] );
$GLOBALS['wpdb']->queries = array();
attempt( static function () { WPCPM_Sponsor_Event::delete_all(); } );

ck( 'delete_all() takes the program\'s events and its lock with the rest', array( events_left(), get_option( 'wpcpm_sevent_lock_program', 'gone' ) ), array( array(), 'gone' ) );

$GLOBALS['cron'] = array();
$GLOBALS['mail'] = array();

echo "\n=== The Partners page ===\n";

// public_upcoming(), the one reader the Partners page lists events from. Every event above is
// gone (delete_all()), so the events here are the page's alone, on a day inside the Partner's term.
set_now( utc( '2026-11-02 09:00' ) );
$saved_index                             = $GLOBALS['index'];
$GLOBALS['index'][ PARTNER ]['website']  = 'partner.example/';
$GLOBALS['index'][ CHAMPION ]['website'] = 'https://champion.example/';

$public = array(
	'running'   => seed_event( HELD, 'approved', utc( '2026-11-02 08:30' ), 60, 'Running now', array( STUDENT ) ),
	'program'   => seed_program_event( 'approved', utc( '2026-11-05 15:00' ), 90, 'The partners session', array( MENTOR ) ),
	'champion'  => seed_event( CHAMPION, 'approved', utc( '2026-11-10 12:00' ), 60, 'Champion webinar', array( STUDENT, MENTOR, CONTACT ) ),
	'partner'   => seed_event( PARTNER, 'approved', utc( '2026-11-20 10:00' ), 30, 'Partner webinar' ),
	'over'      => seed_event( CHAMPION, 'approved', utc( '2026-11-01 10:00' ), 60, 'Over already' ),
	'waiting'   => seed_event( STALE, 'planned', utc( '2026-11-12 10:00' ), 60, 'Waiting for the OK' ),
	'cancelled' => seed_event( CHAMPION, 'cancelled', utc( '2026-11-13 10:00' ), 60, 'Canceled already' ),
	'lapsed'    => seed_event( LAPSED, 'approved', utc( '2026-11-14 10:00' ), 60, 'Lapsed Partner webinar' ),
	'backer'    => seed_event( BACKER, 'approved', utc( '2026-11-15 10:00' ), 60, 'Backer webinar' ),
	'gone'      => seed_event( 'spoAAAA0000000099', 'approved', utc( '2026-11-16 10:00' ), 60, 'Gone sponsor webinar' ),
);
attempt(
	static function () use ( $public ) {
		wp_update_post( array( 'ID' => $public['program'], 'post_content' => "What the program plans\twith its partners.  " ) );
		wp_update_post( array( 'ID' => $public['champion'], 'post_content' => "Learn blocks with us.\n\nJoin at https://call.example/secret-room-5150 or HTTP://CALL.EXAMPLE/UPPER, then write to mailto:hello@champion.example, MAILTO:TEAM@CHAMPION.EXAMPLE or hello@champion.example, or see www.champion.example." ) );
		wp_update_post( array( 'ID' => $public['partner'], 'post_content' => 'https://call.example/only-a-link' ) );
	}
);

/** The titles the page is given, in its order. */
function public_titles( $today = '' ) {
	return array_map(
		static function ( $row ) {
			return $row['title'];
		},
		WPCPM_Sponsor_Event::public_upcoming( $today )
	);
}

ck(
	'public_upcoming(): the approved events not over yet, soonest first, one already running among them, each of a sponsor that still offers events or the program\'s own, as its title, its host and the host\'s website as the sponsors store holds it (none for the program\'s own, or for a sponsor with none), its start, end and minutes, and its description with every http, https and mailto address taken out, whatever its case, a bare email address and a bare www. one kept, and its spaces run together',
	static function () { return WPCPM_Sponsor_Event::public_upcoming(); },
	array(
		array( 'title' => 'Running now', 'host' => 'Held Example', 'host_url' => '', 'start' => utc( '2026-11-02 08:30' ), 'end' => utc( '2026-11-02 09:30' ), 'minutes' => 60, 'description' => 'Seeded description Quillfeather.' ),
		array( 'title' => 'The partners session', 'host' => 'the WordPress Credits Program', 'host_url' => '', 'start' => utc( '2026-11-05 15:00' ), 'end' => utc( '2026-11-05 16:30' ), 'minutes' => 90, 'description' => 'What the program plans with its partners.' ),
		array( 'title' => 'Champion webinar', 'host' => 'Champion Example', 'host_url' => 'https://champion.example/', 'start' => utc( '2026-11-10 12:00' ), 'end' => utc( '2026-11-10 13:00' ), 'minutes' => 60, 'description' => 'Learn blocks with us. Join at or then write to or hello@champion.example, or see www.champion.example.' ),
		array( 'title' => 'Partner webinar', 'host' => 'Partner Example', 'host_url' => 'partner.example/', 'start' => utc( '2026-11-20 10:00' ), 'end' => utc( '2026-11-20 10:30' ), 'minutes' => 30, 'description' => '' ),
	)
);
ck(
	'nothing public of an event waiting for the program\'s OK, one canceled, one over, one of a Backer, whose tier has no events, one of a lapsed term, or one of a sponsor the store no longer holds',
	static function () { return array_values( array_intersect( public_titles(), array( 'Waiting for the OK', 'Canceled already', 'Over already', 'Backer webinar', 'Lapsed Partner webinar', 'Gone sponsor webinar' ) ) ); },
	array()
);
ck(
	'a sponsor set to Paused offers the page nothing: its event goes, and is back once the sponsor is Approved again',
	static function () {
		$GLOBALS['index'][ CHAMPION ]['status'] = 'Paused';
		$paused                                 = public_titles();
		$GLOBALS['index'][ CHAMPION ]['status'] = 'Approved';
		return array( in_array( 'Champion webinar', $paused, true ), in_array( 'Champion webinar', public_titles(), true ) );
	},
	array( false, true )
);
// A test sponsor is never listed publicly (1.123.18): the Partners page lists none of its events,
// a student or a mentor is shown none and joins none, and the program team, who test with it, are.
ck(
	'a test sponsor\'s event is not on the Partners page, nor on a student\'s or a mentor\'s list, and no student joins it; an Administrator still sees it, and it is back once the flag is cleared',
	static function () use ( $public ) {
		$GLOBALS['index'][ CHAMPION ]['test'] = true;
		$titles  = public_titles();
		$student = ids( WPCPM_Sponsor_Event::open_for( PAUSED ) );
		$mentor  = ids( WPCPM_Sponsor_Event::open_for( MENTOR_REP ) );
		$manager = ids( WPCPM_Sponsor_Event::open_for( MANAGER ) );
		$join    = WPCPM_Sponsor_Event::join( $public['champion'], PAUSED );
		unset( $GLOBALS['index'][ CHAMPION ]['test'] );
		return array( in_array( 'Champion webinar', $titles, true ), in_array( $public['champion'], $student, true ), in_array( $public['champion'], $mentor, true ), in_array( $public['champion'], $manager, true ), is_wp_error( $join ) ? $join->get_error_code() : $join, in_array( 'Champion webinar', public_titles(), true ), in_array( $public['champion'], ids( WPCPM_Sponsor_Event::open_for( PAUSED ) ), true ) );
	},
	array( false, false, false, true, 'audience', true, true )
);
ck(
	'a sponsor with no name is no host: its event is left out, never shown under the sponsor\'s key',
	static function () {
		$GLOBALS['index'][ HELD ]['name'] = '';
		$rows                             = WPCPM_Sponsor_Event::public_upcoming();
		$GLOBALS['index'][ HELD ]['name'] = 'Held Example';
		return array( in_array( 'Running now', array_column( $rows, 'title' ), true ), strpos( json_encode( $rows ), HELD ) );
	},
	array( false, false )
);
ck(
	'the day given is the day a sponsor\'s tier is read on: the day after the Partner\'s term ends, its event is not the page\'s, the Champion\'s, with no term, still is',
	static function () {
		$titles = public_titles( '2027-02-01' );
		return array( in_array( 'Partner webinar', $titles, true ), in_array( 'Champion webinar', $titles, true ), in_array( 'Partner webinar', public_titles( '2027-01-31' ), true ) );
	},
	array( false, true, true )
);
ck(
	'a title changed after the program approved the event is the page\'s at once, as the program\'s card shows it to the managers who may cancel it',
	static function () use ( $public ) {
		wp_update_post( array( 'ID' => $public['champion'], 'post_title' => 'Champion webinar, renamed' ) );
		$titles = public_titles();
		wp_update_post( array( 'ID' => $public['champion'], 'post_title' => 'Champion webinar' ) );
		return in_array( 'Champion webinar, renamed', $titles, true );
	},
	true
);
ck(
	'the privacy walk: nothing the page is given holds a call link, the people on an event by name or address, its places, how many joined, the planner\'s clock, an event\'s ID or a sponsor\'s key, and each row holds its seven facts alone',
	static function () use ( $public ) {
		$rows  = WPCPM_Sponsor_Event::public_upcoming();
		$text  = json_encode( $rows );
		$found = array();
		foreach ( array( 'call.example', 'CALL.EXAMPLE', 'secret-room', 'only-a-link', 'UTC', 'spoAAAA' ) as $needle ) {
			if ( false !== strpos( $text, $needle ) ) { $found[] = $needle; }
		}
		foreach ( array( STUDENT, MENTOR, CONTACT ) as $person ) {
			foreach ( array( $GLOBALS['users'][ $person ]->display_name, $GLOBALS['users'][ $person ]->user_email ) as $needle ) {
				if ( false !== stripos( $text, $needle ) ) { $found[] = $needle; }
			}
		}
		$values = array();
		foreach ( $rows as $row ) { $values = array_merge( $values, array_values( $row ) ); }
		$keys = array_values( array_unique( array_map( static function ( $row ) { return implode( ',', array_keys( $row ) ); }, $rows ) ) );
		return array( $found, array_values( array_intersect( $values, array_values( $public ) ) ), $keys );
	},
	array( array(), array(), array( 'title,host,host_url,start,end,minutes,description' ) )
);
ck(
	'nothing at all while the launch switch is off: the reader answers for the public, and the public sees nothing of the tiers before the launch',
	static function () {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$off                                       = WPCPM_Sponsor_Event::public_upcoming();
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $off, count( WPCPM_Sponsor_Event::public_upcoming() ) );
	},
	array( array(), 4 )
);

attempt(
	static function () use ( $public ) {
		foreach ( $public as $id ) { wp_delete_post( $id, true ); }
	}
);
$GLOBALS['index'] = $saved_index;

echo "\n=== What the person typed, kept by the store ===\n";

$typed_program_start = utc( '2030-03-05 15:00' );
$typed_fields_read   = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'title' => $typed_title, 'description' => $ten_lines ) ), new DateTimeZone( 'UTC' ) );
$typed_before        = count( $GLOBALS['posts'] );
$typed_planned       = attempt( static function () use ( $typed_fields_read, $typed_program_start ) { return WPCPM_Sponsor_Event::plan_program( array_merge( $typed_fields_read['fields'], array( 'start' => $typed_program_start ) ), MANAGER ); } );
$typed_refused       = attempt( static function () use ( $typed_fields_read, $typed_program_start ) { return WPCPM_Sponsor_Event::plan_program( array_merge( $typed_fields_read['fields'], array( 'start' => $typed_program_start, 'title' => $typed_fields_read['fields']['title'] . 't' ) ), MANAGER ); } );

ck(
	'the fields the form handed back are the fields the store takes: the program plans an event whose title is 120 characters as typed and whose description is 2,000 over ten lines, stored as cleaned, and one more typed character in the title is refused as fields, writing nothing',
	static function () use ( $typed_planned, $typed_refused, $typed_before, $cleaned_title, $ten_lines ) {
		$post = is_int( $typed_planned ) ? get_post( $typed_planned ) : null;
		return array( is_int( $typed_planned ), $post ? $post->post_title === $cleaned_title : null, $post ? $post->post_content === $ten_lines : null, code_of( $typed_refused ), count( $GLOBALS['posts'] ) - $typed_before );
	},
	array( true, true, true, 'fields', 1 )
);

attempt( static function () use ( $typed_planned ) { if ( is_int( $typed_planned ) ) { wp_delete_post( $typed_planned, true ); } } );

echo "\n=== Words keep their backslashes ===\n";

// The post functions unslash what they are handed, as core's do (the stand-ins above), and the title
// and the description reach the store from the form unslashed: an event of the program's own keeps a
// backslash only when its post is written as a slashed copy, as a sponsor's is.
$slashed_typed = 'Kept in C:\drafts\events, two in a row \\\\ here, and a "quoted" word.';
$slashed_read  = WPCPM_Sponsor_Event::fields_from( array_merge( $good, array( 'title' => $slashed_typed, 'description' => $slashed_typed ) ), new DateTimeZone( 'UTC' ) );
$slashed_event = attempt( static function () use ( $slashed_read ) { return WPCPM_Sponsor_Event::plan_program( array_merge( $slashed_read['fields'], array( 'start' => utc( '2030-03-06 15:00' ) ) ), MANAGER ); } );

ck(
	'an event of the program\'s own keeps its title and its description exactly as typed, the backslashes and the quotes whole',
	static function () use ( $slashed_event, $slashed_typed ) {
		$post = is_int( $slashed_event ) ? get_post( $slashed_event ) : null;
		return array( $post ? $post->post_title : null, $post ? $post->post_content : null );
	},
	array( $slashed_typed, $slashed_typed )
);

attempt( static function () use ( $slashed_event ) { if ( is_int( $slashed_event ) ) { wp_delete_post( $slashed_event, true ); } } );

echo "\n=== The mails give a member's words as typed ===\n";

// A member's title holds a "<3", a tag, a ">", an ampersand and quotes. The text cleaner takes the tag
// out and writes the "<" and the quotes after it as entities, and for a member the store writes the
// ">" as one as well, so the post holds them all as entities. A mail is plain text: the people it goes
// to read the words, every entity read back, in the Title line and in the subject alike. The mail
// layer cleans every subject, and the cleaner reads "<3 ... >" as a tag and drops it, words and all;
// the subject of these mails is plain text, and goes out with its "<" kept.
const SIGNS_TYPED  = 'We <3 "our" <b>team</b> & Bob\'s > all';
const SIGNS_STORED = 'We &lt;3 &quot;our&quot; team & Bob\'s &gt; all';
const SIGNS_MAILED = 'We <3 "our" team & Bob\'s > all';

/** A mail's Title line, or ''. */
function title_line( $body ) { return preg_match( '/^Title: .*$/m', (string) $body, $found ) ? $found[0] : ''; }

set_now( utc( '2030-04-01 09:00' ) );
$GLOBALS['mail'] = array();
$GLOBALS['cron'] = array();
$signs_start     = utc( '2030-04-10 15:00' );
$signs           = attempt(
	static function () use ( $signs_start ) {
		$id = WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( $signs_start, array( 'title' => SIGNS_TYPED ) ), CHAMPION_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		WPCPM_Sponsor_Event::join( $id, STUDENT );
		return $id;
	}
);

ck(
	'a member\'s title holding a "<3", a tag, a ">", an ampersand and quotes is stored as the cleaner and the store write it: the tag gone, the "<", the quotes after it and the ">" as entities',
	static function () use ( $signs ) { return is_int( $signs ) ? get_post( $signs )->post_title : null; },
	SIGNS_STORED
);
ck(
	'the cleaner every subject goes through reads the "<3" as the start of a tag and drops it with the words after it, here to the end, the quote mark in it holding the tag open, as core\'s does',
	sanitize_text_field( '[WordPress Education] Reminder: ' . SIGNS_MAILED ),
	'[WordPress Education] Reminder: We'
);
ck(
	'a change tells the person on it the title as typed, its entities read back, in the Title line and in the subject, every word kept',
	static function () use ( $signs, $signs_start ) {
		$GLOBALS['mail'] = array();
		WPCPM_Sponsor_Event::change( $signs, event_fields( $signs_start + HOUR_IN_SECONDS, array( 'title' => SIGNS_TYPED ) ), CHAMPION_REP );
		$mail = mail_for( 'sam.current@people.example' );
		return array( $mail['subject'], title_line( $mail['body'] ) );
	},
	array( '[WordPress Education] Event changed: ' . SIGNS_MAILED, 'Title: ' . SIGNS_MAILED )
);
ck(
	'the reminder the day before gives the person on it and the sponsor\'s member the title as typed, in the Title line and in the subject',
	static function () use ( $signs_start ) {
		$GLOBALS['mail'] = array();
		set_now( $signs_start + HOUR_IN_SECONDS - 20 * HOUR_IN_SECONDS );
		WPCPM_Sponsor_Event::queue_reminders();
		run_cron( 'wpcpm_sevent_drain' );
		$joined = mail_for( 'sam.current@people.example' );
		$member = mail_for( 'chris.championrep@people.example' );
		return array( $joined['subject'], title_line( $joined['body'] ), $member['subject'], title_line( $member['body'] ) );
	},
	array( '[WordPress Education] Reminder: ' . SIGNS_MAILED, 'Title: ' . SIGNS_MAILED, '[WordPress Education] Your event is coming up: ' . SIGNS_MAILED, 'Title: ' . SIGNS_MAILED )
);
ck(
	'a cancel tells the person on it the title as typed, in the Title line and in the subject',
	static function () use ( $signs ) {
		$GLOBALS['mail'] = array();
		WPCPM_Sponsor_Event::cancel( $signs, CHAMPION_REP );
		$mail = mail_for( 'sam.current@people.example' );
		return array( $mail['subject'], title_line( $mail['body'] ) );
	},
	array( '[WordPress Education] Event canceled: ' . SIGNS_MAILED, 'Title: ' . SIGNS_MAILED )
);

attempt( static function () use ( $signs ) { if ( is_int( $signs ) ) { wp_delete_post( $signs, true ); } } );
$GLOBALS['mail'] = array();
$GLOBALS['cron'] = array();

echo "\n=== The words a line about an event is said in ===\n";

ck(
	'mail_values(): the day and the times through the calls\' own range, on the clock given, and the zone named apart, as the template\'s When line gives them; the places a number, or "no limit"; the title, the sponsor\'s name and the site\'s as stored, for the registry to read back once',
	static function () {
		$values = array(
			WPCPM_Sponsor_Event::mail_values( array( 'title' => 'Q&amp;A &lt;3', 'start' => utc( '2026-12-10 10:00' ), 'end' => utc( '2026-12-10 11:00' ), 'minutes' => 60, 'places' => 0 ), 'https://call.example/room', new DateTimeZone( 'America/New_York' ), 'Ada &amp; Bo' ),
			WPCPM_Sponsor_Event::mail_values( array( 'title' => 'Late', 'start' => utc( '2026-12-31 23:30' ), 'end' => utc( '2027-01-01 00:30' ), 'minutes' => 60, 'places' => 12 ), '', new DateTimeZone( 'UTC' ), '' ),
		);
		return array_map( static function ( $v ) { return array( $v['when'], $v['time_zone'], $v['places'], $v['event_title'], $v['company_name'], $v['length'], $v['call_link'], $v['site_name'] ); }, $values );
	},
	array(
		array( 'December 10, 2026 5:00 am to 6:00 am', 'America / New York', 'no limit', 'Q&amp;A &lt;3', 'Ada &amp; Bo', '60 minutes', 'https://call.example/room', 'WordPress Education' ),
		array( 'December 31, 2026 11:30 pm to January 1, 2027 12:30 am', 'UTC', '12', 'Late', '', '60 minutes', '', 'WordPress Education' ),
	)
);
ck( 'length_line(): the minutes in words, one in the singular', static function () { return array( WPCPM_Sponsor_Event::length_line( 1 ), WPCPM_Sponsor_Event::length_line( 90 ) ); }, array( '1 minute', '90 minutes' ) );
ck(
	'one copy each: the four sponsor event files say the minutes and the sponsor\'s name through the store alone, and the time and its zone through mail_values(), whose When line is the template registry\'s (includes/mail/templates/events.php)',
	static function () {
		$all = '';
		foreach ( array( 'class-wpcpm-sponsor-event.php', 'class-wpcpm-sponsor-event-queue.php', 'class-wpcpm-sponsor-event-planner.php', 'class-wpcpm-sponsor-event-list.php' ) as $file ) {
			$all .= (string) @file_get_contents( __DIR__ . '/../includes/modules/' . $file );
		}
		$area = (string) @file_get_contents( __DIR__ . '/../includes/mail/templates/events.php' );
		return array( substr_count( $all, "'When: %1\$s (%2\$s)'" ), substr_count( $all, "_n( '%s minute', '%s minutes'" ), substr_count( $all, 'function sponsor_name(' ), substr_count( $all, 'function mail_values(' ), substr_count( $all, 'function length_line(' ), substr_count( $area, "'When: %1\$s (%2\$s)'" ) );
	},
	array( 0, 1, 1, 1, 1, 1 )
);
ck( 'every query the store sent is one the stand-ins model', array( count( $GLOBALS['audit'] ) > 0, $GLOBALS['unmodeled'] ), array( true, array() ) );
ck(
	'the store renders nothing and reads no request: no echo, print or printf, no markup, no superglobal',
	array( class_exists( 'WPCPM_Sponsor_Event', false ), preg_match( '/\b(echo|print|printf|vprintf)\b|\?>|\$_(POST|GET|REQUEST|SERVER|COOKIE)/', $event_src ) ),
	array( true, 0 )
);
ck( 'no Airtable in it, and no em or en dash', array( class_exists( 'WPCPM_Sponsor_Event', false ), false !== strpos( $event_src, 'WPCPM_Airtable' ), preg_match( '/\x{2013}|\x{2014}/u', $event_src ) ), array( true, false, 0 ) );

$GLOBALS['run_complete'] = true;

// What every mail of the suite says, as the recording made before the event and term emails moved
// into the template registry holds it (bin/stubs/mail-recording.php).
foreach ( wpcpm_recording_checks( 'sponsor-event-mails-store.json', 'e1b0409229df02f89826f8cab29d6015350ca174dce8c64263def0e96aab7d2f', array_keys( array_filter( WPCPM_Mail_Catalog::emails(), static function ( $e ) { return WPCPM_Mail_Catalog::MODULE_EVENTS === $e['module']; } ) ), array(), "-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program." ) as $recorded_check ) {
	ck( $recorded_check[0], $recorded_check[1], $recorded_check[2] );
}

// The site's name as WordPress stores it, its "&" an entity, is handed over as stored and read back once
// by the registry: the store's mail to a person on an event, for a change, a cancel and a reminder, and
// its reminder to one of the sponsor's members who is not on it, name the site as a person typed it,
// and none shows "&amp;" in its subject or its body. After the recording's checks, which build no mail
// of their own.
$GLOBALS['site_name'] = 'Arts &amp; Sciences Dashboard';
ck(
	'a site name holding an "&", stored as an entity, reads as typed in the subject of the store\'s mail for a change, a cancel and both reminders, and "&amp;" is in none of their subjects or bodies',
	static function () {
		$event = get_post( seed_event( PARTNER, 'approved', utc( '2026-12-10 10:00' ), 60, 'Arts and sciences day', array( STUDENT ) ) );
		$out   = array();
		foreach ( array( 'joiner_mail' => array( 'event-changed', 'event-canceled' ), 'reminder_mail' => array( 'event-reminder', 'event-reminder-sponsor' ) ) as $method => $ids ) {
			foreach ( $ids as $id ) {
				$mail       = ( new ReflectionMethod( 'WPCPM_Sponsor_Event', $method ) )->invoke( null, $event, 'Partner Example', $id, $GLOBALS['users'][ STUDENT ] );
				$out[ $id ] = array( 0 === strpos( $mail['subject'], '[Arts & Sciences Dashboard] ' ), false === strpos( $mail['subject'] . $mail['body'], '&amp;' ) );
			}
		}
		return $out;
	},
	array_fill_keys( array( 'event-changed', 'event-canceled', 'event-reminder', 'event-reminder-sponsor' ), array( true, true ) )
);
unset( $GLOBALS['site_name'] );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
