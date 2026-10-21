<?php
/**
 * The events from the program's sponsors, as the people they are for see them (1.117.0): the list on
 * the Student Report Card, the Mentor Report Card and the Institution Dashboard, the manager's Join
 * and Leave on the Administrator Dashboard, and the two presses behind them.
 *
 * What is pinned here and why:
 *
 * - The wiring: Join and Leave are admin-post actions, and their nopriv twins too, so a login that
 *   lapsed reaches the handlers' own 403 rather than core's blank page; the loader and uninstall.php require the
 *   class right after the program's card, and the Sponsors module boots it; the calendar calls it
 *   from the student's funnel and the mentor's sessions column, and the Institution Dashboard after
 *   its modules and before the read line, each behind class_exists(). On the Student Report Card
 *   the list and the group sessions sit in the calls card's pair of columns, after both, and the
 *   calendar stylesheet spans them across the pair, one rule for the two.
 * - The list, for each of the four audiences on their own page: "Events from our sponsors", under
 *   the house's heading for its place (the sessions' subheading on the Student Report Card, the
 *   calls card's own heading on the Mentor Report Card, where the sessions panel's heading is one,
 *   a module title's level and class on the Institution Dashboard), the zone named once on the
 *   person's own clock, every approved event not over whose sponsor still offers events, soonest
 *   first, each with its sponsor's name, its title, when, how long, the places left ("Open to
 *   everyone" for no limit, "Full" when none is left), the description whole under 300 characters
 *   and folded above, and Join; every value escaped. Nothing while the launch switch is off,
 *   nothing for somebody no event is for, nothing drawn for anybody but the person signed in, and a
 *   sponsor's own member never sees its own event. An event whose sponsor no longer offers events
 *   stays on the list of the person on it, marked no longer offered, with Leave and the link alone;
 *   nobody else sees it, and a Join posted for it is refused.
 * - The privacy walk: before joining, no call link and nobody's name; once on the event, the mark,
 *   Leave, and the link, which opens apart and names its event to a screen reader, as Join and
 *   Leave name theirs; still nobody else's name, to the people on the event and to those who are
 *   not.
 * - The presses, in their order: a lapsed login told to log in before the nonce; the nonce keyed to
 *   the event, the ID the one thing read before it, a stale page stopped there with nothing written
 *   and nothing flashed, the switch off or on; the launch switch, off answered with a 403 and
 *   nothing flashed; an event; then the store, whose every refusal is said in words of the list's
 *   own, a Leave on an event canceled since the page was drawn among them. A refused press writes
 *   nothing and mails nobody.
 * - The mails: a join mails the person the event, whose it is, when on their clock, how long, the
 *   call link and the calendar file that adds it, a request at the version the join raised; a leave
 *   mails them the cancel of the approved event, at the version the leave raised, which takes it off
 *   their calendar alone. Each file names the person as its attendee, is removed once its send is
 *   handed off, and a mail says nothing of a file the site could not write. Nobody else on the event
 *   is named in either. A member's title holding a "<3", a tag, a ">", an ampersand and quotes, which
 *   the store holds as entities, reaches both as typed, in the subject and the Title line, every word
 *   kept, the subject going to the mail layer as plain text.
 * - The way back: each of the four keys to its dashboard at the list's anchor (the program's card's
 *   on the Administrator Dashboard); a posted address, an unknown key or an unpublished page to the
 *   page the person's audience implies; with none of those, the front page. Each audience's own
 *   page, the one a press with no page of its own goes back to and the Partners page sends a
 *   signed-in viewer to (`page_for()`): nothing for somebody no event is for, or for an unpublished
 *   page.
 * - The flash: one channel of the list's own, per person, drawn at the head of the section and
 *   under the program's Coming up, taken once; a sentence for every status the presses flash; with
 *   no event left to list, the section holds the sentence alone; an outcome still waiting when the
 *   switch goes off is taken and not drawn.
 * - The manager's actions on the program's card: Join for an event they are not on, the mark and
 *   Leave for one they are, no call link of the list's (the card has its own), nothing to join on an
 *   event whose sponsor no longer offers events or that is full, and nothing on a waiting one.
 * - The program's own events: listed beside a sponsor's and named for the WordPress Credits
 *   Program, under "Events from our sponsors and the program" while one is listed, in each place's
 *   markup; the Partner's own mentor sees and joins one, being nobody's own; the join and leave
 *   mails say the WordPress Credits Program hosts it; and nothing while the switch is off.
 * - The house rules: no em or en dash, no sponsor ID compared with ===, no Airtable, and the list
 *   writes nothing of its own: the store writes, and the flash.
 *
 * WordPress is the events suite's small store in memory (bin/test-sponsor-events.php): posts, their
 * meta as repeated rows, the slash pair core applies, options with `add_option()` as the lock's
 * test-and-set and the table read a lock is taken after (bin/stubs/options-table.php), the cron
 * schedule's single events, and the clock, which the store reads through its
 * seam; `wp_date()` honors the zone it is given. The request is posted slashed, as WordPress hands
 * `$_POST` to a handler. Core's two text cleaners are stood in as the events suite writes them. The
 * store, the four dashboards' addresses, the audiences' readers, the mail layer and the calendar
 * builder are the plugin's own, and `wp_mail()` keeps what it is handed, each attachment read at the
 * moment of the send. The sponsors store, the audit trail, the settings, a
 * student's cached status and the flash are stood in for the calls made. Every check runs inside
 * its own guard, so a class that is missing fails each check rather than the run.
 *
 * Run from the plugin root:  php bin/test-sponsor-event-list.php
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
$GLOBALS['sent']      = array();
$GLOBALS['contexts']  = array();
$GLOBALS['flash']     = array();
$GLOBALS['nonces']    = array();
$GLOBALS['nonce_ok']  = true;
$GLOBALS['died_code'] = null;
$GLOBALS['unmodeled'] = array();
$GLOBALS['cron']      = array();

/* ---- WordPress, stood in for the parts the list, the store and the mail layer touch ---- */

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

/** A press that redirected: where to, the flash beside it. */
class List_Test_Left extends Exception {}

/** A press that died: the login screen's sentence, or the nonce's. */
class List_Test_Die extends Exception {}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function _x( $text, $context, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
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

/** Signed in: somebody is the current user. */
function is_user_logged_in() { return (int) $GLOBALS['uid'] > 0; }

/** The nonce: each action asked for is kept, and a stale one dies with WordPress's own sentence. */
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['nonces'][] = $action;
	if ( ! $GLOBALS['nonce_ok'] ) {
		throw new List_Test_Die( 'The link you followed has expired.' );
	}
	return 1;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />'; }
function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['died_code'] = is_int( $title ) ? $title : null;
	throw new List_Test_Die( (string) $message );
}
function wp_safe_redirect( $location, $status = 302 ) { throw new List_Test_Left( (string) $location ); }

/** A value as the database hands it back: text, whatever went in, and an array whole. */
function db_value( $value ) {
	if ( is_array( $value ) ) { return $value; }
	if ( is_bool( $value ) ) { return $value ? '1' : ''; }
	return null === $value ? '' : (string) $value;
}

function wp_insert_post( $args, $wp_error = false ) {
	$args = wp_unslash( (array) $args );
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
function get_post_status( $id ) { $post = get_post( $id ); return $post instanceof WP_Post ? $post->post_status : false; }
function get_permalink( $id ) { $post = get_post( $id ); return $post instanceof WP_Post ? 'https://site.example/' . $post->post_name . '/' : false; }
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

/** Enough of `get_posts()`: a type, a status or a list of them, a meta query, a limit that defaults to five, and IDs. The newest first. */
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

// The accounts, and the one capability of the program's own (bin/stubs/caps.php).
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( 'id' === $field && $user->ID === (int) $value ) || ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) ) { return $user; }
	}
	return false;
}
function get_userdata( $id ) { return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false; }
function wp_get_current_user() { return isset( $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ (int) $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
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
require_once __DIR__ . '/stubs/caps.php';

/**
 * Mail: every message handed to WordPress is kept whole, its context read off the mail layer's
 * filter, and each attachment read at the moment of the send, since the mail layer removes it
 * straight after.
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	$files = array();
	foreach ( (array) $attachments as $path ) {
		$files[] = array(
			'path' => (string) $path,
			'name' => basename( (string) $path ),
			'ics'  => is_file( (string) $path ) ? str_replace( "\r\n ", '', (string) file_get_contents( (string) $path ) ) : null,
		);
	}
	$GLOBALS['sent'][] = array(
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

/* ---- the collaborators the list reads beside the plugin's own classes ---------- */

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

/** A student's status as the students sync caches it, under `program`. */
class WPCPM_Students_Sync {
	public static function get_program( $user_id ) { return isset( $GLOBALS['program'][ (int) $user_id ] ) ? $GLOBALS['program'][ (int) $user_id ] : array(); }
}

/** The sponsors store, for the calls made: the key's shape, a row, Approved. */
class WPCPM_Sponsors_Index {
	const POST_STATUSES   = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );
	const STATUS_APPROVED = 'Approved';
	public static function is_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', $value ); }
	public static function rows() { return $GLOBALS['index']; }
	public static function row( $record ) { $record = trim( (string) $record ); return self::is_key( $record ) && isset( $GLOBALS['index'][ $record ] ) ? $GLOBALS['index'][ $record ] : null; }
	public static function has( $record ) { return null !== self::row( $record ); }
	public static function is_test( $sponsor ) { $row = is_array( $sponsor ) ? $sponsor : self::row( (string) $sponsor ); return is_array( $row ) && ! empty( $row['test'] ); }
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
	public static function record_program( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}

/** The flash, per person and per channel, as a page takes it: once. */
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id ) { $GLOBALS['flash'][ $user_id ][ (string) $channel ] = $value; }
		return true;
	}
	public static function take( $channel, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$value   = isset( $GLOBALS['flash'][ $user_id ][ (string) $channel ] ) ? $GLOBALS['flash'][ $user_id ][ (string) $channel ] : '';
		unset( $GLOBALS['flash'][ $user_id ][ (string) $channel ] );
		return $value;
	}
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/class-wpcpm-ics.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
// The template registry, which every event and term email is filled from since 1.123.9.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-dashboard.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-dashboard.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-administrators-dashboard.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institutions-dashboard.php';
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

// Loaded only when it is there, so that before it exists every check below fails on its own.
$list_file = __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-list.php';

if ( file_exists( $list_file ) ) {
	require_once $list_file;
}

// A warning, a notice or a deprecation is an error here, raised inside the guard of the check that
// caused it, so the list cannot pass a check while complaining on the way.
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
 * One check. `$got` may be a closure, run here inside a guard: a list that is missing or throws
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

/** The people on an event, as the store answers them. */
function on( $id ) { return class_exists( 'WPCPM_Sponsor_Event' ) ? WPCPM_Sponsor_Event::joiners( $id ) : array(); }

/**
 * The list as one person sees it on one page, signed in as `$uid` unless another is named.
 *
 * @param int    $viewer Whose page it is.
 * @param string $back   Which page.
 * @param int    $uid    Who is signed in; the viewer when not given.
 * @return string
 */
function draw( $viewer, $back, $uid = null ) {
	$GLOBALS['uid'] = null === $uid ? (int) $viewer : (int) $uid;
	ob_start();
	try {
		WPCPM_Sponsor_Event_List::render( isset( $GLOBALS['users'][ (int) $viewer ] ) ? $GLOBALS['users'][ (int) $viewer ] : null, $back );
	} finally {
		$html = (string) ob_get_clean();
	}
	return $html;
}

/** The actions the program's card draws for one event, as one manager sees them. */
function actions_for( $id, $uid ) {
	$GLOBALS['uid'] = (int) $uid;
	ob_start();
	try {
		WPCPM_Sponsor_Event_List::render_actions( get_post( $id ), $GLOBALS['users'][ (int) $uid ], 'admin' );
	} finally {
		$html = (string) ob_get_clean();
	}
	return $html;
}

/**
 * A press, as a person makes it from a page: signed in as `$uid`, the form's fields posted slashed
 * as WordPress hands them over. What it left with: the flash on the list's channel and where it
 * went, or `died`, the sentence and the status it died with.
 *
 * @param string $method `handle_join` or `handle_leave`.
 * @param array  $fields What the form posts.
 * @param int    $uid    Who presses.
 * @return array
 */
function press( $method, array $fields, $uid ) {
	$GLOBALS['uid']       = (int) $uid;
	$GLOBALS['died_code'] = null;
	unset( $GLOBALS['flash'][ (int) $uid ] );
	$_POST = wp_slash( $fields );

	try {
		call_user_func( array( 'WPCPM_Sponsor_Event_List', $method ) );
	} catch ( List_Test_Left $e ) {
		return array( isset( $GLOBALS['flash'][ (int) $uid ]['sevent-list'] ) ? $GLOBALS['flash'][ (int) $uid ]['sevent-list'] : '', $e->getMessage() );
	} catch ( List_Test_Die $e ) {
		return array( 'died', $e->getMessage(), $GLOBALS['died_code'] );
	}

	return array( 'fell through' );
}

/** What a Join or a Leave form posts: the event and the page to come back to. */
function pressing( $id, $back = 'student' ) { return array( 'wpcpm_sevent_id' => (string) $id, 'wpcpm_sevent_back' => $back ); }

/** Everything a refused press must leave as it was: the posts, their meta, the trail, the mail and the locks. */
function store_state() { return array( $GLOBALS['posts'], $GLOBALS['pmeta'], $GLOBALS['audit'], $GLOBALS['sent'], $GLOBALS['opts'] ); }

/** Who each sent mail went to, in the order sent. */
function sent_to() { return array_map( static function ( $mail ) { return $mail['to']; }, $GLOBALS['sent'] ); }

/** The fields the store is handed to plan an event. */
function event_fields( $start, array $over = array() ) {
	return array_merge(
		array(
			'start'       => $start,
			'minutes'     => 90,
			'places'      => 2,
			'title'       => 'Partners "Q&A" in C:\\talks',
			'description' => str_repeat( 'What we "cover" & more, in <detail>. ', 12 ),
			'link'        => 'https://call.example/room?pwd=a%2Fb&id=7',
			'zone'        => 'America/New_York',
		),
		$over
	);
}

/* ---- the fixture ------------------------------------------------------------ */

const MANAGER     = 1;
const PARTNER_REP = 2;
const MANAGER_TWO = 7;
const STRANGER    = 9;
const STUDENT     = 10;
const JOINER_TWO  = 11;
const GRADUATE    = 12;
const MENTOR      = 20;
const MENTOR_REP  = 22;
const CONTACT     = 30;

const PARTNER  = 'spoAAAA0000000001'; // Partner, the term runs to 31 January 2027.
const CHAMPION = 'spoAAAA0000000002'; // Champion, no term.
const PAUSED   = 'spoAAAA0000000003'; // Champion, Approved while it plans, then set to Paused.
const WAITING  = 'spoAAAA0000000004'; // Champion, whose event waits for the program's OK.
const CALLOFF  = 'spoAAAA0000000005'; // Champion, whose event a manager cancels under a page left open.

// The four dashboards, published at their own addresses.
foreach ( array( 7001 => array( 'wpcpm_student_page_id', 'student-dashboard' ), 7002 => array( 'wpcpm_mentor_page_id', 'mentor-dashboard' ), 7003 => array( 'wpcpm_administrator_page_id', 'administrator-dashboard' ), 7004 => array( 'wpcpm_institution_page_id', 'institution-dashboard' ) ) as $page_id => $page ) {
	$post                            = new WP_Post();
	$post->ID                        = $page_id;
	$post->post_type                 = 'page';
	$post->post_status               = 'publish';
	$post->post_name                 = $page[1];
	$GLOBALS['posts'][ $page_id ]    = $post;
	$GLOBALS['opts'][ $page[0] ]     = $page_id;
}

function a_user( $id, $name, array $roles, array $meta = array() ) {
	$user                    = new WP_User( $id, $name );
	$user->roles             = $roles;
	$user->user_email        = strtolower( str_replace( ' ', '.', $name ) ) . '@people.example';
	$GLOBALS['users'][ $id ] = $user;
	$GLOBALS['umeta'][ $id ] = $meta;
	return $user;
}

a_user( MANAGER, 'Mira Manager', array( 'administrator' ) );
a_user( MANAGER_TWO, 'Mo Manager', array( 'administrator' ) );
a_user( PARTNER_REP, 'Pat Partnerrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1 ) );
a_user( STRANGER, 'Stan Stranger', array( 'subscriber' ) );
// Each audience on a clock of its own, so every list and every mail reads the event on the reader's.
a_user( STUDENT, 'Sam Current', array( 'wpcpm_student' ), array( 'wpcpm_timezone' => 'America/New_York' ) );
a_user( JOINER_TWO, 'Zelda Privacywalk', array( 'wpcpm_student' ), array( 'wpcpm_timezone' => 'Europe/Lisbon' ) );
a_user( GRADUATE, 'Gina Graduate', array( 'wpcpm_student' ) );
a_user( MENTOR, 'Mona Mentor', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recSEED0000000201', 'wpcpm_mentor_active' => 1, 'wpcpm_timezone' => 'Asia/Tokyo' ) );
// A mentor who also acts for the Partner, which the memberships allow on purpose.
a_user( MENTOR_REP, 'Mel Mentorrep', array( 'wpcpm_mentor', 'wpcpm_sponsor' ), array( 'wpcpm_mentor_record_id' => 'recSEED0000000203', 'wpcpm_mentor_active' => 1, 'wpcpm_sponsor_record_id' => PARTNER, 'wpcpm_sponsor_active' => 1 ) );
a_user( CONTACT, 'Ines Institution', array( 'wpcpm_institution' ), array( 'wpcpm_institution_record_id' => 'recSEED0000000301', 'wpcpm_institution_active' => 1 ) );

$GLOBALS['manage']   = array( MANAGER, MANAGER_TWO );
$GLOBALS['program']  = array(
	STUDENT    => array( 'program' => 'In Sensei' ),
	JOINER_TWO => array( 'program' => 'Developer Track' ),
	GRADUATE   => array( 'program' => 'Graduate' ),
);
$GLOBALS['settings'] = array(
	'sponsor_tiers_live' => true,
	'student_statuses'   => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' ),
	'past_statuses'      => array( 'Graduate', 'Dropped out' ),
);
$GLOBALS['index']    = array(
	PARTNER  => array( 'record_id' => PARTNER, 'name' => 'Partner Example', 'status' => 'Approved', 'tier' => 'partner', 'tier_until' => '2027-01-31' ),
	CHAMPION => array( 'record_id' => CHAMPION, 'name' => 'Champion <b>Example</b> & Co', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' ),
	PAUSED   => array( 'record_id' => PAUSED, 'name' => 'Paused Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' ),
	WAITING  => array( 'record_id' => WAITING, 'name' => 'Waiting Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' ),
	CALLOFF  => array( 'record_id' => CALLOFF, 'name' => 'Calloff Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' ),
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

// The Partner's event, two places, its description long; the Champion's, open to everyone, its
// description short; the Paused sponsor's, approved before it was paused, the soonest of all; and an
// event still waiting for the program's OK.
$partner  = attempt( static function () { $id = WPCPM_Sponsor_Event::plan( PARTNER, event_fields( utc( '2026-11-10 19:00' ) ), PARTNER_REP, 'member' ); WPCPM_Sponsor_Event::approve( $id, MANAGER ); return $id; } );
$champion = attempt( static function () { $id = WPCPM_Sponsor_Event::plan( CHAMPION, event_fields( utc( '2026-12-01 10:00' ), array( 'minutes' => 60, 'places' => 0, 'title' => 'Champion webinar & more', 'description' => 'Hands-on, with "quotes" & <b>tags</b>.', 'link' => 'https://meet.example/champion', 'zone' => 'Europe/Warsaw' ) ), MANAGER, 'manager' ); WPCPM_Sponsor_Event::approve( $id, MANAGER ); return $id; } );
$paused   = attempt( static function () { $id = WPCPM_Sponsor_Event::plan( PAUSED, event_fields( utc( '2026-10-20 09:00' ), array( 'title' => 'Paused workshop', 'places' => 12, 'link' => 'https://meet.example/paused' ) ), MANAGER, 'manager' ); WPCPM_Sponsor_Event::approve( $id, MANAGER ); return $id; } );
$waiting  = attempt( static function () { return WPCPM_Sponsor_Event::plan( WAITING, event_fields( utc( '2026-11-20 10:00' ), array( 'title' => 'Waiting webinar' ) ), MANAGER, 'manager' ); } );

$GLOBALS['index'][ PAUSED ]['status'] = 'Paused';
$GLOBALS['audit']                     = array();

/* ---- the wiring ------------------------------------------------------------- */

echo "=== The wiring ===\n";

ck(
	'the list hooks its two presses as admin-post actions, and their nopriv twins, so a login that lapsed while the page sat open reaches the handlers\' own 403',
	static function () {
		$GLOBALS['hooks'] = array();
		WPCPM_Sponsor_Event_List::init();
		$hooks = $GLOBALS['hooks'];
		ksort( $hooks );
		return $hooks;
	},
	array(
		'admin_post_nopriv_wpcpm_sevent_join'  => array( array( 'WPCPM_Sponsor_Event_List', 'handle_join' ) ),
		'admin_post_nopriv_wpcpm_sevent_leave' => array( array( 'WPCPM_Sponsor_Event_List', 'handle_leave' ) ),
		'admin_post_wpcpm_sevent_join'         => array( array( 'WPCPM_Sponsor_Event_List', 'handle_join' ) ),
		'admin_post_wpcpm_sevent_leave'        => array( array( 'WPCPM_Sponsor_Event_List', 'handle_leave' ) ),
	)
);

$loader_src    = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$uninstall_src = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
$sponsors_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$calendar_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-call-calendar.php' );
$school_src    = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institutions-dashboard.php' );

ck(
	'the loader and uninstall.php require the list right after the program\'s card',
	array(
		false !== strpos( $loader_src, "require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-event-queue.php';\nrequire_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-event-list.php';\n" ),
		false !== strpos( $uninstall_src, "require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sponsor-event-queue.php';\nrequire_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sponsor-event-list.php';\n" ),
	),
	array( true, true )
);
ck( 'the Sponsors module boots the list behind the same class_exists() guard as its other classes', 1 === preg_match( "/foreach \\( array\\([^)]*'WPCPM_Sponsor_Event_List'[^)]*\\) as \\\$front \\)/", $sponsors_src ), true );

/**
 * The body of one method in a source, from its signature to the next method's docblock.
 *
 * @param string $src       The file.
 * @param string $signature Where the method starts.
 * @return string
 */
function body_of( $src, $signature ) {
	$at = strpos( $src, $signature );
	if ( false === $at ) { return ''; }
	$next = strpos( $src, "\n\t/**", $at );
	return false === $next ? substr( $src, $at ) : substr( $src, $at, $next - $at );
}

$funnel  = body_of( $calendar_src, 'private static function close_student(' );
$diary   = body_of( $calendar_src, 'public static function render_mentor(' );
$modules = body_of( $school_src, 'public static function render( $atts = array() )' );

ck(
	'the calendar calls the list behind class_exists(): after the student\'s sessions and inside the pair, for the student themselves; in the mentor\'s sessions column after the panel, for the mentor themselves',
	array(
		1 === preg_match( "/render_student_sessions\\([^;]*;\\s*\\}\\s*(?:\\/\\/[^\\n]*\\n\\s*)*if \\( \\\$viewer_is_student && \\\$student instanceof WP_User && class_exists\\( 'WPCPM_Sponsor_Event_List' \\) \\) \\{\\s*WPCPM_Sponsor_Event_List::render\\( \\\$student, WPCPM_Sponsor_Event_List::BACK_STUDENT \\);\\s*\\}\\s*echo '<\\/div>';\\s*echo '<\\/section>';/", $funnel ),
		1 === preg_match( "/WPCPM_Group_Sessions::render_mentor_panel\\( \\\$mentor \\);\\s*(?:\\/\\/[^\\n]*\\n\\s*)*if \\( get_current_user_id\\(\\) === \\(int\\) \\\$mentor->ID && class_exists\\( 'WPCPM_Sponsor_Event_List' \\) \\) \\{\\s*WPCPM_Sponsor_Event_List::render\\( \\\$mentor, WPCPM_Sponsor_Event_List::BACK_MENTOR \\);\\s*\\}\\s*echo '<\\/div>';/", $diary ),
	),
	array( true, true )
);
// The funnel draws the group sessions and the list after the two columns and inside their pair, so
// they sit under the booked calls and the picker alike; a grid puts a child after the two in one
// cell of a new row, half the card, so the calendar stylesheet spans both across the pair. Read
// from the rule that gives the span, as the report form's suite reads its own: the one rule naming
// either block as a child of the pair, and it sets `grid-column: 1 / -1`.
ck(
	'the calendar stylesheet spans the group sessions and the list across the calls card\'s two columns, in one rule',
	static function () {
		$css   = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( __DIR__ . '/../assets/css/calendar.css' ) );
		$found = array();
		preg_match_all( '/([^{}]+)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER );
		foreach ( $rules as $rule ) {
			$selectors = array_map( static function ( $selector ) { return preg_replace( '/\s+/', ' ', trim( $selector ) ); }, explode( ',', $rule[1] ) );
			if ( array_intersect( $selectors, array( '.wpcpm-calls__cols > .wpcpm-sessions', '.wpcpm-calls__cols > .wpcpm-sevents' ) ) ) {
				$found[] = array( $selectors, 1 === preg_match( '#(?:^|;|\s)grid-column:\s*1\s*/\s*-1\s*;#', $rule[2] ) );
			}
		}
		return $found;
	},
	array( array( array( '.wpcpm-calls__cols > .wpcpm-sessions', '.wpcpm-calls__cols > .wpcpm-sevents' ), true ) )
);
ck(
	'the Institution Dashboard calls it behind class_exists() after its modules and before the read line, for a contact of the institution drawn',
	array(
		false !== strpos( $modules, "if ( \$own_page && class_exists( 'WPCPM_Sponsor_Event_List' ) ) {\n\t\t\t\tWPCPM_Sponsor_Event_List::render( \$viewer, WPCPM_Sponsor_Event_List::BACK_INSTITUTION );" ),
		strpos( $modules, 'self::render_module( $key' ) < strpos( $modules, 'WPCPM_Sponsor_Event_List::render(' ),
		strpos( $modules, 'WPCPM_Sponsor_Event_List::render(' ) < strpos( $modules, 'read_line(' ),
		false !== strpos( $modules, '$own_page = (string) WPCPM_Institution_Members::institution_of( $viewer ) === $record;' ),
	),
	array( true, true, true, true )
);
ck(
	'the four keys, the list\'s anchor, the program\'s card\'s, and the channel',
	static function () { return array( WPCPM_Sponsor_Event_List::BACK_STUDENT, WPCPM_Sponsor_Event_List::BACK_MENTOR, WPCPM_Sponsor_Event_List::BACK_ADMIN, WPCPM_Sponsor_Event_List::BACK_INSTITUTION, WPCPM_Sponsor_Event_List::ANCHOR, WPCPM_Sponsor_Event_List::ADMIN_ANCHOR, WPCPM_Sponsor_Event_List::FLASH ); },
	array( 'student', 'mentor', 'admin', 'institution', 'wpcpm-sevents', 'wpcpm-sponsor-events', 'sevent-list' )
);

/* ---- the list, for each audience -------------------------------------------- */

echo "\n=== The list, for each audience ===\n";

$student_html = attempt( static function () { return draw( STUDENT, 'student' ); } );

$long = str_repeat( 'What we &quot;cover&quot; &amp; more, in . ', 12 );

ck(
	'the student\'s list: the heading, the zone on their own clock, then the events they may join, the soonest first; the paused sponsor\'s and the waiting one are not among them',
	static function () use ( $student_html, $partner, $champion, $paused, $waiting ) {
		return array(
			0 === strpos( (string) $student_html, '<section class="wpcpm-sevents" id="wpcpm-sevents"><h4 class="wpcpm-sevents__heading wpcpm-calls__subheading">Events from our sponsors</h4><p class="wpcpm-sevents__zone">Times are in your time zone, America / New York.</p><ul class="wpcpm-sevent__list"><li class="wpcpm-sevent__item">' ),
			substr_count( (string) $student_html, '<li class="wpcpm-sevent__item">' ),
			strpos( (string) $student_html, 'Partners &quot;' ) < strpos( (string) $student_html, 'Champion webinar' ),
			strpos( (string) $student_html, 'Paused workshop' ),
			strpos( (string) $student_html, 'Waiting webinar' ),
			'</ul></section>' === substr( (string) $student_html, -15 ),
			is_int( $paused ) && is_int( $waiting ),
		);
	},
	array( true, 2, true, false, false, true, true )
);
ck(
	'one event, whole: the title and the sponsor\'s name, when on the student\'s clock, how long, open to everyone, the short description, and Join, every value escaped',
	static function () use ( $student_html, $champion ) {
		return false !== strpos(
			(string) $student_html,
			'<li class="wpcpm-sevent__item"><p class="wpcpm-sevent__title">Champion webinar &amp; more <span class="wpcpm-sevent__sponsor">Champion &lt;b&gt;Example&lt;/b&gt; &amp; Co</span></p>'
				. '<p class="wpcpm-sevent__meta">December 1, 2026 5:00 am to 6:00 am. 60 minutes. Open to everyone.</p>'
				. '<p class="wpcpm-sevent__description">Hands-on, with &quot;quotes&quot; &amp; tags.</p>'
				. '<div class="wpcpm-sevent__actions"><form class="wpcpm-inline-form wpcpm-sevent__join" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Joining"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_join_' . $champion . '" /><input type="hidden" name="action" value="wpcpm_sevent_join" /><input type="hidden" name="wpcpm_sevent_id" value="' . $champion . '" /><input type="hidden" name="wpcpm_sevent_back" value="student" /><button type="submit" class="wpcpm-button">Join<span class="screen-reader-text"> Champion webinar &amp; more</span></button></form></div></li>'
		);
	},
	true
);
ck(
	'a description longer than 300 characters is folded under Read the description; the places left are counted',
	static function () use ( $student_html, $long ) {
		return array(
			false !== strpos( (string) $student_html, '<p class="wpcpm-sevent__meta">November 10, 2026 2:00 pm to 3:30 pm. 90 minutes. 2 places left.</p><details class="wpcpm-sevent__more"><summary>Read the description</summary><p class="wpcpm-sevent__description">' . trim( $long ) . '</p></details>' ),
			mb_strlen( trim( str_repeat( 'What we "cover" & more, in . ', 12 ) ) ) > 300,
		);
	},
	array( true, true )
);
ck(
	'before joining, no call link and nobody\'s name: the two events drawn name their sponsors alone',
	static function () use ( $student_html ) {
		$leaks = array();
		foreach ( array( 'call.example', 'meet.example', 'wpcpm-sevent__link', 'Join the call', 'Mira', 'Pat Partnerrep', 'people.example', 'You are on it' ) as $needle ) {
			if ( false !== strpos( (string) $student_html, $needle ) ) { $leaks[] = $needle; }
		}
		return array( substr_count( (string) $student_html, '<span class="wpcpm-sevent__sponsor">' ), $leaks );
	},
	array( 2, array() )
);
ck(
	'a mentor, an institution contact and a program manager each get the list on their own clock, posting their own page to come back to',
	static function () {
		$out = array();
		foreach ( array( MENTOR => array( 'mentor', 'Asia / Tokyo' ), CONTACT => array( 'institution', 'Europe / Warsaw' ), MANAGER => array( 'admin', 'Europe / Warsaw' ) ) as $who => $page ) {
			$html  = draw( $who, $page[0] );
			$out[] = array( false !== strpos( $html, '<p class="wpcpm-sevents__zone">Times are in your time zone, ' . $page[1] . '.</p>' ), substr_count( $html, '<li class="wpcpm-sevent__item">' ), substr_count( $html, 'name="wpcpm_sevent_back" value="' . $page[0] . '"' ) );
		}
		return $out;
	},
	array( array( true, 2, 2 ), array( true, 2, 2 ), array( true, 2, 2 ) )
);
// The heading is the house's for the place the list is drawn, so the theme's existing rules dress it
// on each page: on the Student Report Card it sits under the student's group sessions, whose own
// subheading is an h4; on the Mentor Report Card the sessions panel's heading is the calls card's
// own h3, so the events take that heading, beside the sessions rather than filed under them; and on
// the Institution Dashboard among the modules, whose titles are h2s in the Student Report Card's
// heading class: at their level, the page's outline keeps the events beside the modules rather
// than under the last of them. A key that names no page drawing the list claims none of them.
ck(
	'the heading wears the house\'s class for its place: the sessions\' subheading on the Student Report Card, the calls card\'s heading on the Mentor Report Card, a module title\'s level and class on the Institution Dashboard, and none for a key no list is drawn on',
	static function () {
		$out = array();
		foreach ( array( STUDENT => 'student', MENTOR => 'mentor', CONTACT => 'institution', MANAGER => 'admin' ) as $who => $back ) {
			$out[] = preg_match( '#<h[1-6] class="wpcpm-sevents__heading[^"]*">Events from our sponsors</h[1-6]>#', draw( $who, $back ), $found ) ? $found[0] : 'no heading';
		}
		return $out;
	},
	array(
		'<h4 class="wpcpm-sevents__heading wpcpm-calls__subheading">Events from our sponsors</h4>',
		'<h3 class="wpcpm-sevents__heading wpcpm-calls__heading">Events from our sponsors</h3>',
		'<h2 class="wpcpm-sevents__heading wpcpm-student__heading">Events from our sponsors</h2>',
		'<h3 class="wpcpm-sevents__heading">Events from our sponsors</h3>',
	)
);
ck(
	'the Tokyo mentor reads the Partner\'s event on Tokyo\'s clock, where it is the next morning',
	static function () { return false !== strpos( draw( MENTOR, 'mentor' ), '<p class="wpcpm-sevent__meta">November 11, 2026 4:00 am to 5:30 am. 90 minutes. 2 places left.</p>' ); },
	true
);
ck(
	'a mentor who acts for the Partner is never shown the Partner\'s own event: it is on its Sponsor Dashboard',
	static function () { $html = draw( MENTOR_REP, 'mentor' ); return array( substr_count( $html, '<li class="wpcpm-sevent__item">' ), strpos( $html, 'Partners &quot;' ), false !== strpos( $html, 'Champion webinar' ) ); },
	array( 1, false, true )
);
ck(
	'nothing at all for somebody no event is for: a graduate, a stranger, a sponsor\'s member',
	static function () { return array( draw( GRADUATE, 'student' ), draw( STRANGER, 'student' ), draw( PARTNER_REP, 'student' ) ); },
	array( '', '', '' )
);
ck(
	'nothing drawn for anybody but the person signed in: a program manager reading a student\'s page is offered no Join as the student',
	static function () { return array( draw( STUDENT, 'student', MANAGER ), draw( STUDENT, 'student', 0 ), draw( 0, 'student', 0 ) ); },
	array( '', '', '' )
);
ck(
	'nothing at all while the launch switch is off',
	static function () {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$html                                      = draw( STUDENT, 'student' );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return $html;
	},
	''
);

/* ---- joining: the order of the checks ------------------------------------------- */

echo "\n=== Joining: the order of the checks ===\n";

ck(
	'a lapsed login is told to log in, with the 403, before the nonce is asked, and nothing is written, mailed or flashed',
	static function () use ( $partner ) {
		$before            = store_state();
		$GLOBALS['nonces'] = array();
		$join              = press( 'handle_join', pressing( $partner ), 0 );
		$leave             = press( 'handle_leave', pressing( $partner ), 0 );
		return array( $join, $leave, $GLOBALS['nonces'], store_state() === $before, $GLOBALS['flash'] );
	},
	array( array( 'died', 'Please log in to join an event.', 403 ), array( 'died', 'Please log in to leave an event.', 403 ), array(), true, array() )
);
ck(
	'a stale page is stopped on the nonce keyed to the event before anything else is read: nothing is written, mailed or flashed',
	static function () use ( $partner ) {
		$before              = store_state();
		$GLOBALS['nonces']   = array();
		$GLOBALS['nonce_ok'] = false;
		$join                = press( 'handle_join', pressing( $partner ), STUDENT );
		$leave               = press( 'handle_leave', pressing( $partner ), STUDENT );
		$GLOBALS['nonce_ok'] = true;
		return array( $join, $leave, $GLOBALS['nonces'], store_state() === $before, isset( $GLOBALS['flash'][ STUDENT ]['sevent-list'] ) );
	},
	array( array( 'died', 'The link you followed has expired.', null ), array( 'died', 'The link you followed has expired.', null ), array( 'wpcpm_sevent_join_' . $partner, 'wpcpm_sevent_leave_' . $partner ), true, false )
);
ck(
	'with the launch switch off a press is answered with the 403, once the nonce keyed to the event has passed: nothing is written, mailed or flashed',
	static function () use ( $partner ) {
		$before                                    = store_state();
		$GLOBALS['nonces']                         = array();
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$left                                      = array( press( 'handle_join', pressing( $partner ), STUDENT ), press( 'handle_leave', pressing( $partner ), STUDENT ) );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $left, $GLOBALS['nonces'], store_state() === $before, isset( $GLOBALS['flash'][ STUDENT ]['sevent-list'] ) );
	},
	array( array( array( 'died', 'This is not available.', 403 ), array( 'died', 'This is not available.', 403 ) ), array( 'wpcpm_sevent_join_' . $partner, 'wpcpm_sevent_leave_' . $partner ), true, false )
);
ck(
	'and with the switch off a stale page still stops on the nonce first',
	static function () use ( $partner ) {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$GLOBALS['nonce_ok']                       = false;
		$left                                      = array( press( 'handle_join', pressing( $partner ), STUDENT ), press( 'handle_leave', pressing( $partner ), STUDENT ) );
		$GLOBALS['nonce_ok']                       = true;
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return $left;
	},
	array( array( 'died', 'The link you followed has expired.', null ), array( 'died', 'The link you followed has expired.', null ) )
);
ck(
	'an ID that is no event, or none at all, is said as gone, and the nonce was keyed to what was posted',
	static function () {
		$before            = store_state();
		$GLOBALS['nonces'] = array();
		return array( press( 'handle_join', pressing( 99999 ), STUDENT ), press( 'handle_join', pressing( 7001 ), STUDENT ), press( 'handle_leave', array(), STUDENT ), $GLOBALS['nonces'], store_state() === $before );
	},
	array( array( 'gone', 'https://site.example/student-dashboard/#wpcpm-sevents' ), array( 'gone', 'https://site.example/student-dashboard/#wpcpm-sevents' ), array( 'gone', 'https://site.example/student-dashboard/#wpcpm-sevents' ), array( 'wpcpm_sevent_join_99999', 'wpcpm_sevent_join_7001', 'wpcpm_sevent_leave_0' ), true )
);

/* ---- joining ------------------------------------------------------------------ */

echo "\n=== Joining ===\n";

$GLOBALS['sent'] = array();
$joined          = attempt( static function () use ( $partner ) { return press( 'handle_join', pressing( $partner ), STUDENT ); } );

ck( 'the student joins the Partner\'s event: joined, back on the Student Report Card at the list', $joined, array( 'joined', 'https://site.example/student-dashboard/#wpcpm-sevents' ) );
ck( 'the store put them on it, and raised the event\'s version', static function () use ( $partner ) { return array( on( $partner ), meta( $partner, '_wpcpm_sevent_revision' ) ); }, array( array( STUDENT ), '2' ) );
ck(
	'they are mailed once, logged as event-joined, the template of a sponsor\'s event: whose event, which, when on their clock, how long, the call link, and the calendar file that adds it',
	static function () {
		$mail = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array();
		return array( sent_to(), $mail['context'], $mail['subject'], $mail['body'], $mail['headers'] );
	},
	array(
		array( 'sam.current@people.example' ),
		'event-joined',
		'[WordPress Education] You joined: Partners "Q&A" in C:\\talks',
		"You joined Partner Example's event.\n\n"
			. "Title: Partners \"Q&A\" in C:\\talks\nWhen: November 10, 2026 2:00 pm to 3:30 pm (America / New York)\nLength: 90 minutes\nJoin the call: https://call.example/room?pwd=a%2Fb&id=7\n\n"
			. 'The calendar file attached adds it to your calendar.',
		array(),
	)
);
ck(
	'the file is a request under the event\'s own identity at the version the join raised, with the call link, naming the person who joined as its attendee and nobody else but the program, and the send removed it',
	static function () use ( $partner ) {
		$file = isset( $GLOBALS['sent'][0]['files'][0] ) ? $GLOBALS['sent'][0]['files'][0] : array( 'ics' => '', 'path' => '', 'name' => '' );
		$ics  = (string) $file['ics'];
		return array( count( $GLOBALS['sent'][0]['files'] ), $file['name'], false !== strpos( $ics, "METHOD:REQUEST\r\n" ), false !== strpos( $ics, 'UID:wpcpm-call-' . $partner . '@site.example' ), false !== strpos( $ics, "SEQUENCE:2\r\n" ), false !== strpos( $ics, 'Join the call: https://call.example/room?pwd=a%2Fb&id=7' ), array_values( preg_grep( '/people\.example/', explode( "\r\n", $ics ) ) ), file_exists( $file['path'] ) );
	},
	array( 1, 'sponsor-event.ics', true, true, true, true, array( 'ATTENDEE;CN="Sam Current";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:sam.current@people.example' ), false )
);
ck(
	'joining again is said as already on it, and nothing is written or mailed',
	static function () use ( $partner ) { $before = store_state(); return array( press( 'handle_join', pressing( $partner ), STUDENT ), store_state() === $before ); },
	array( array( 'already', 'https://site.example/student-dashboard/#wpcpm-sevents' ), true )
);

$GLOBALS['sent'] = array();
attempt( static function () use ( $partner ) { return press( 'handle_join', pressing( $partner ), JOINER_TWO ); } );

ck(
	'the second student takes the last place; their mail names nobody else on the event',
	static function () use ( $partner ) { return array( on( $partner ), sent_to(), strpos( serialize( $GLOBALS['sent'] ), 'Sam Current' ), strpos( serialize( $GLOBALS['sent'] ), 'sam.current@' ) ); },
	array( array( STUDENT, JOINER_TWO ), array( 'zelda.privacywalk@people.example' ), false, false )
);
ck(
	'full now: the mentor is told it filled up, and nothing is written or mailed',
	static function () use ( $partner ) { $before = store_state(); return array( press( 'handle_join', pressing( $partner, 'mentor' ), MENTOR ), store_state() === $before ); },
	array( array( 'full', 'https://site.example/mentor-dashboard/#wpcpm-sevents' ), true )
);
ck(
	'the other refusals, each in the list\'s words: a graduate is no audience; a sponsor\'s own member meets its own event; a waiting event is not open; a held lock is busy',
	static function () use ( $champion, $partner, $waiting ) {
		$before = store_state();
		$out    = array( press( 'handle_join', pressing( $champion ), GRADUATE )[0], press( 'handle_join', pressing( $partner, 'mentor' ), MENTOR_REP )[0], press( 'handle_join', pressing( $waiting ), STUDENT )[0] );
		add_option( 'wpcpm_sevent_lock_' . CHAMPION, $GLOBALS['now'] );
		$out[] = press( 'handle_join', pressing( $champion ), STUDENT )[0];
		delete_option( 'wpcpm_sevent_lock_' . CHAMPION );
		return array( $out, store_state() === $before );
	},
	array( array( 'audience', 'own', 'not-open', 'busy' ), true )
);

/* ---- the list, once on an event: the privacy walk -------------------------------- */

echo "\n=== The list, once on an event ===\n";

ck(
	'the student on it: the mark, Leave asking before it posts, and the call link, which opens apart; still the one place left counted as none',
	static function () use ( $partner ) {
		return false !== strpos(
			draw( STUDENT, 'student' ),
			'<p class="wpcpm-sevent__meta">November 10, 2026 2:00 pm to 3:30 pm. 90 minutes. Full.</p>'
		) && false !== strpos(
			draw( STUDENT, 'student' ),
			'<div class="wpcpm-sevent__actions"><p class="wpcpm-sevent__joined">You are on it.</p><form class="wpcpm-inline-form wpcpm-sevent__leave" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Leaving"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_leave_' . $partner . '" /><input type="hidden" name="action" value="wpcpm_sevent_leave" /><input type="hidden" name="wpcpm_sevent_id" value="' . $partner . '" /><input type="hidden" name="wpcpm_sevent_back" value="student" /><button type="submit" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-confirm="Leave this event? Your place goes back.">Leave<span class="screen-reader-text"> Partners &quot;Q&amp;A&quot; in C:\\talks</span></button></form><a class="wpcpm-sevent__link" href="https://call.example/room?pwd=a%2Fb&amp;id=7" target="_blank" rel="noopener noreferrer">Join the call<span class="screen-reader-text"> Partners &quot;Q&amp;A&quot; in C:\\talks</span></a></div>'
		);
	},
	true
);
ck(
	'a person not on the full event reads Full and gets no Join and no link; the event with room still offers Join',
	static function () use ( $partner, $champion ) {
		$html = draw( MENTOR, 'mentor' );
		return array( false !== strpos( $html, '90 minutes. Full.</p>' ), substr_count( $html, 'wpcpm_sevent_join_' . $partner ), substr_count( $html, 'wpcpm_sevent_join_' . $champion ), strpos( $html, 'call.example' ), strpos( $html, 'wpcpm-sevent__link' ) );
	},
	array( true, 0, 1, false, false )
);
ck(
	'nobody\'s name reaches any list, the people on the event\'s own included: not the other joiner, not a member, not a manager',
	static function () {
		$leaks = array();
		$items = 0;
		foreach ( array( STUDENT => 'student', JOINER_TWO => 'student', MENTOR => 'mentor', CONTACT => 'institution', MANAGER => 'admin' ) as $who => $page ) {
			$html   = draw( $who, $page );
			$items += substr_count( $html, '<li class="wpcpm-sevent__item">' );
			foreach ( $GLOBALS['users'] as $user ) {
				foreach ( array( $user->display_name, $user->user_email ) as $needle ) {
					if ( false !== strpos( $html, $needle ) ) { $leaks[] = $who . ': ' . $needle; }
				}
			}
		}
		return array( $items, $leaks );
	},
	array( 10, array() )
);

/* ---- leaving ----------------------------------------------------------------- */

echo "\n=== Leaving ===\n";

$GLOBALS['sent'] = array();
$left            = attempt( static function () use ( $partner ) { return press( 'handle_leave', pressing( $partner ), STUDENT ); } );

ck( 'the student leaves: left, back on their page at the list', $left, array( 'left', 'https://site.example/student-dashboard/#wpcpm-sevents' ) );
ck( 'off the event, the place free at once, and the version raised again', static function () use ( $partner ) { return array( on( $partner ), meta( $partner, '_wpcpm_sevent_revision' ), meta( $partner, '_wpcpm_sevent_state' ) ); }, array( array( JOINER_TWO ), '4', 'approved' ) );
ck(
	'they are mailed once, logged as event-left: whose event they left, and the file that takes it off their calendar',
	static function () { $mail = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array(); return array( sent_to(), $mail['context'], $mail['subject'], $mail['body'] ); },
	array( array( 'sam.current@people.example' ), 'event-left', '[WordPress Education] You left: Partners "Q&A" in C:\\talks', "You left Partner Example's event. The calendar file attached takes it off your calendar." )
);
ck(
	'the file cancels the approved event, for them alone, at the version the leave raised, naming them as its attendee and nobody else but the program, and the send removed it',
	static function () use ( $partner ) {
		$file = isset( $GLOBALS['sent'][0]['files'][0] ) ? $GLOBALS['sent'][0]['files'][0] : array( 'ics' => '', 'path' => '' );
		$ics  = (string) $file['ics'];
		return array( false !== strpos( $ics, "METHOD:CANCEL\r\n" ), false !== strpos( $ics, "STATUS:CANCELLED\r\n" ), false !== strpos( $ics, 'UID:wpcpm-call-' . $partner . '@site.example' ), false !== strpos( $ics, "SEQUENCE:4\r\n" ), array_values( preg_grep( '/people\.example/', explode( "\r\n", $ics ) ) ), file_exists( $file['path'] ) );
	},
	array( true, true, true, true, array( 'ATTENDEE;CN="Sam Current";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:sam.current@people.example' ), false )
);
ck(
	'leaving again is said as not on it, and nothing is written or mailed',
	static function () use ( $partner ) { $before = store_state(); return array( press( 'handle_leave', pressing( $partner ), STUDENT ), store_state() === $before ); },
	array( array( 'not-joined', 'https://site.example/student-dashboard/#wpcpm-sevents' ), true )
);
ck(
	'with no calendar file to be had, a join and a leave say nothing of one and attach none',
	static function () use ( $champion ) {
		$GLOBALS['sent']    = array();
		$GLOBALS['no_temp'] = true;
		$out                = array( press( 'handle_join', pressing( $champion, 'mentor' ), MENTOR )[0], press( 'handle_leave', pressing( $champion, 'mentor' ), MENTOR )[0] );
		$GLOBALS['no_temp'] = false;
		foreach ( $GLOBALS['sent'] as $mail ) {
			$out[] = array( $mail['files'], strpos( $mail['body'], 'calendar file' ) );
		}
		return $out;
	},
	array( 'joined', 'left', array( array(), false ), array( array(), false ) )
);

/* ---- an event whose sponsor no longer offers events ------------------------------- */

echo "\n=== An event whose sponsor no longer offers events ===\n";

// The student joined the Paused sponsor's workshop while the sponsor was in the program.
attempt( static function () use ( $paused ) { add_post_meta( $paused, '_wpcpm_sevent_person', STUDENT ); } );

ck(
	'the person on it keeps it on their list, the soonest, marked no longer offered in place of the places, with the mark, Leave and the link, and no Join',
	static function () use ( $paused ) {
		$html = draw( STUDENT, 'student' );
		return array(
			false !== strpos( $html, '<ul class="wpcpm-sevent__list"><li class="wpcpm-sevent__item"><p class="wpcpm-sevent__title">Paused workshop <span class="wpcpm-sevent__sponsor">Paused Example</span></p><p class="wpcpm-sevent__meta">October 20, 2026 5:00 am to 6:30 am. 90 minutes. No longer offered.</p>' ),
			false !== strpos( $html, '<div class="wpcpm-sevent__actions"><p class="wpcpm-sevent__joined">You are on it.</p><form class="wpcpm-inline-form wpcpm-sevent__leave"' ),
			false !== strpos( $html, 'name="wpcpm_sevent_id" value="' . $paused . '" /><input type="hidden" name="wpcpm_sevent_back" value="student" /><button type="submit" class="wpcpm-button wpcpm-button--secondary"' ),
			false !== strpos( $html, '<a class="wpcpm-sevent__link" href="https://meet.example/paused" target="_blank" rel="noopener noreferrer">Join the call<span class="screen-reader-text"> Paused workshop</span></a>' ),
			substr_count( $html, 'wpcpm_sevent_join_' . $paused ),
			substr_count( $html, 'No longer offered' ),
		);
	},
	array( true, true, true, true, 0, 1 )
);
ck(
	'nobody else sees it, and a Join posted for it anyway is refused as not open, with nothing written or mailed',
	static function () use ( $paused ) {
		$before = store_state();
		return array( strpos( draw( MENTOR, 'mentor' ), 'Paused workshop' ), strpos( draw( JOINER_TWO, 'student' ), 'Paused workshop' ), press( 'handle_join', pressing( $paused, 'mentor' ), MENTOR )[0], store_state() === $before );
	},
	array( false, false, 'not-open', true )
);
ck(
	'the person on it leaves it: off the event, mailed the file that takes it out of their calendar, and it leaves their list',
	static function () use ( $paused ) {
		$GLOBALS['sent'] = array();
		$left            = press( 'handle_leave', pressing( $paused ), STUDENT );
		$ics             = isset( $GLOBALS['sent'][0]['files'][0]['ics'] ) ? (string) $GLOBALS['sent'][0]['files'][0]['ics'] : '';
		return array( $left, on( $paused ), sent_to(), false !== strpos( $ics, "METHOD:CANCEL\r\n" ), strpos( draw( STUDENT, 'student' ), 'Paused workshop' ) );
	},
	array( array( 'left', 'https://site.example/student-dashboard/#wpcpm-sevents' ), array(), array( 'sam.current@people.example' ), true, false )
);

/* ---- a Leave on an event canceled since the page was drawn ------------------------ */

echo "\n=== A Leave on an event canceled since the page was drawn ===\n";

$calloff = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( CALLOFF, event_fields( utc( '2026-12-10 15:00' ), array( 'title' => 'Calloff webinar', 'places' => 0, 'link' => 'https://meet.example/calloff' ) ), MANAGER, 'manager' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		WPCPM_Sponsor_Event::join( $id, STUDENT );
		WPCPM_Sponsor_Event::cancel( $id, MANAGER );
		return $id;
	}
);

ck(
	'the cancel told the person on it; the Leave they press on the page still open is refused as canceled, the sentence the list has for it, and they stay on the canceled event\'s record, mailed nothing more',
	static function () use ( $calloff ) {
		$GLOBALS['sent'] = array();
		$before          = array( meta( $calloff, '_wpcpm_sevent_revision' ), meta( $calloff, '_wpcpm_sevent_state' ) );
		$left            = press( 'handle_leave', pressing( $calloff ), STUDENT );
		return array( is_int( $calloff ), $left, WPCPM_Sponsor_Event_List::messages()['canceled'], on( $calloff ), array( meta( $calloff, '_wpcpm_sevent_revision' ), meta( $calloff, '_wpcpm_sevent_state' ) ) === $before, $GLOBALS['sent'] );
	},
	array( true, array( 'canceled', 'https://site.example/student-dashboard/#wpcpm-sevents' ), array( 'error', 'That event is canceled.' ), array( STUDENT ), true, array() )
);

/* ---- the way back -------------------------------------------------------------- */

echo "\n=== The way back ===\n";

ck(
	'each of the four keys goes to its dashboard, at the list, or at the program\'s card on the Administrator Dashboard',
	static function () {
		return array( WPCPM_Sponsor_Event_List::back_to( 'student', STUDENT ), WPCPM_Sponsor_Event_List::back_to( 'mentor', STUDENT ), WPCPM_Sponsor_Event_List::back_to( 'institution', STUDENT ), WPCPM_Sponsor_Event_List::back_to( 'admin', STUDENT ) );
	},
	array( 'https://site.example/student-dashboard/#wpcpm-sevents', 'https://site.example/mentor-dashboard/#wpcpm-sevents', 'https://site.example/institution-dashboard/#wpcpm-sevents', 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events' )
);
ck(
	'a posted address is never followed: it goes to the page the person\'s audience implies, as an unknown key and no key do',
	static function () use ( $champion ) {
		return array(
			press( 'handle_join', pressing( $champion, 'https://evil.example/' ), CONTACT ),
			WPCPM_Sponsor_Event_List::back_to( 'dashboard', MENTOR ),
			WPCPM_Sponsor_Event_List::back_to( '', MANAGER ),
			WPCPM_Sponsor_Event_List::back_to( '', STUDENT ),
		);
	},
	array( array( 'joined', 'https://site.example/institution-dashboard/#wpcpm-sevents' ), 'https://site.example/mentor-dashboard/#wpcpm-sevents', 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events', 'https://site.example/student-dashboard/#wpcpm-sevents' )
);
ck(
	'a page that is not published sends the press to the page the audience implies, and with none of those, or nobody\'s, to the front page',
	static function () {
		$GLOBALS['posts'][7002]->post_status = 'draft';
		$mentor                              = WPCPM_Sponsor_Event_List::back_to( 'mentor', STUDENT );
		$own                                 = WPCPM_Sponsor_Event_List::back_to( 'mentor', MENTOR );
		$GLOBALS['posts'][7002]->post_status = 'publish';
		return array( $mentor, $own, WPCPM_Sponsor_Event_List::back_to( 'nowhere', STRANGER ), WPCPM_Sponsor_Event_List::back_to( 'nowhere', 0 ) );
	},
	array( 'https://site.example/student-dashboard/#wpcpm-sevents', 'https://site.example/', 'https://site.example/', 'https://site.example/' )
);
ck(
	'each audience\'s own page, as the store reads who a person is, the one the Partners page sends a signed-in viewer to: a student\'s, a mentor\'s and an institution contact\'s at the list, a manager\'s at the program\'s card; nothing for somebody no event is for, for an audience the store never answers, or for a page that is not published',
	static function () {
		$pages = array_map(
			static function ( $person ) {
				return WPCPM_Sponsor_Event_List::page_for( WPCPM_Sponsor_Event::audience_of( $person ) );
			},
			array( STUDENT, MENTOR, CONTACT, MANAGER, STRANGER )
		);

		$pages[]                             = WPCPM_Sponsor_Event_List::page_for( 'sponsors' );
		$GLOBALS['posts'][7002]->post_status = 'draft';
		$pages[]                             = WPCPM_Sponsor_Event_List::page_for( WPCPM_Sponsor_Event::audience_of( MENTOR ) );
		$GLOBALS['posts'][7002]->post_status = 'publish';

		return $pages;
	},
	array( 'https://site.example/student-dashboard/#wpcpm-sevents', 'https://site.example/mentor-dashboard/#wpcpm-sevents', 'https://site.example/institution-dashboard/#wpcpm-sevents', 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events', '', '', '' )
);

/* ---- the flash ----------------------------------------------------------------- */

echo "\n=== The flash ===\n";

ck(
	'the outcome is drawn at the head of the section, under the zone and above the events, announced, and taken once',
	static function () {
		WPCPM_Flash::set( 'sevent-list', 'left', STUDENT );
		$first  = draw( STUDENT, 'student' );
		$second = draw( STUDENT, 'student' );
		return array( false !== strpos( $first, 'America / New York.</p><p class="wpcpm-sevent__message is-success" role="status">You left the event, and your place is free for somebody else.</p><ul class="wpcpm-sevent__list">' ), strpos( $second, 'wpcpm-sevent__message' ) );
	},
	array( true, false )
);
ck(
	'a refusal is drawn as an error, and a status that is none of the list\'s is drawn as nothing',
	static function () {
		WPCPM_Flash::set( 'sevent-list', 'full', STUDENT );
		$full = draw( STUDENT, 'student' );
		WPCPM_Flash::set( 'sevent-list', '<script>', STUDENT );
		$odd = draw( STUDENT, 'student' );
		return array( false !== strpos( $full, '<p class="wpcpm-sevent__message is-error" role="status">That event filled up before you could join it.</p>' ), strpos( $odd, 'wpcpm-sevent__message' ), strpos( $odd, 'script>' ) );
	},
	array( true, false, false )
);
ck(
	'with no event left to list, the section holds the outcome alone, so a refusal whose event has gone is still explained; with nothing to say, nothing at all',
	static function () {
		WPCPM_Flash::set( 'sevent-list', 'not-open', GRADUATE );
		$explained = draw( GRADUATE, 'student' );
		return array( $explained, draw( GRADUATE, 'student' ) );
	},
	array( '<section class="wpcpm-sevents" id="wpcpm-sevents"><h4 class="wpcpm-sevents__heading wpcpm-calls__subheading">Events from our sponsors</h4><p class="wpcpm-sevent__message is-error" role="status">That event is no longer open to join.</p></section>', '' )
);
ck(
	'an outcome still waiting when the launch switch goes off is taken and not drawn, so it never surfaces beside a later list',
	static function () {
		WPCPM_Flash::set( 'sevent-list', 'joined', STUDENT );
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$off                                       = draw( STUDENT, 'student' );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $off, isset( $GLOBALS['flash'][ STUDENT ]['sevent-list'] ), strpos( draw( STUDENT, 'student' ), 'wpcpm-sevent__message' ) );
	},
	array( '', false, false )
);
ck(
	'the program\'s card draws the same outcome where it asks, taken once, and nothing while the switch is off',
	static function () {
		$GLOBALS['uid'] = MANAGER;
		WPCPM_Flash::set( 'sevent-list', 'joined', MANAGER );
		ob_start();
		WPCPM_Sponsor_Event_List::render_message();
		WPCPM_Sponsor_Event_List::render_message();
		$html = (string) ob_get_clean();
		WPCPM_Flash::set( 'sevent-list', 'joined', MANAGER );
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		ob_start();
		WPCPM_Sponsor_Event_List::render_message();
		$off                                       = (string) ob_get_clean();
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return array( $html, $off, isset( $GLOBALS['flash'][ MANAGER ]['sevent-list'] ) );
	},
	array( '<p class="wpcpm-sevent__message is-success" role="status">You joined the event.</p>', '', false )
);

$flashed = array( 'joined', 'left', 'full', 'past', 'not-open', 'audience', 'own', 'busy', 'already', 'not-joined', 'canceled', 'gone', 'failed' );

ck(
	'a sentence for every status the two presses flash, each a notice kind and words, and nothing more',
	static function () use ( $flashed ) {
		$messages = WPCPM_Sponsor_Event_List::messages();
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
	'the sentences a person reads, in plain US English: joined and left claim no mail; a sponsor\'s member is sent to its own dashboard; the switch off has no sentence, since it is answered with a 403',
	static function () {
		$messages = WPCPM_Sponsor_Event_List::messages();
		return array( $messages['joined'], $messages['left'], $messages['own'], $messages['not-open'][1], $messages['canceled'], $messages['gone'][1], isset( $messages['off'] ) );
	},
	array(
		array( 'success', 'You joined the event.' ),
		array( 'success', 'You left the event, and your place is free for somebody else.' ),
		array( 'error', 'Your company\'s event is on your Sponsor Dashboard.' ),
		'That event is no longer open to join.',
		array( 'error', 'That event is canceled.' ),
		'That event is not on this site anymore.',
		false,
	)
);

/* ---- the manager's actions on the program's card ---------------------------------- */

echo "\n=== The manager's actions on the program's card ===\n";

ck(
	'a manager not on an approved event gets Join, coming back to the program\'s card; no call link, which the card draws itself',
	static function () use ( $champion ) {
		return actions_for( $champion, MANAGER_TWO );
	},
	'<div class="wpcpm-sevent__actions"><form class="wpcpm-inline-form wpcpm-sevent__join" method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Joining"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_join_' . $champion . '" /><input type="hidden" name="action" value="wpcpm_sevent_join" /><input type="hidden" name="wpcpm_sevent_id" value="' . $champion . '" /><input type="hidden" name="wpcpm_sevent_back" value="admin" /><button type="submit" class="wpcpm-button">Join<span class="screen-reader-text"> Champion webinar &amp; more</span></button></form></div>'
);
ck(
	'the manager joins as themselves and comes back to the card; then the mark and Leave, still without the list\'s call link',
	static function () use ( $champion ) {
		$left = press( 'handle_join', pressing( $champion, 'admin' ), MANAGER_TWO );
		$html = actions_for( $champion, MANAGER_TWO );
		return array( $left, in_array( MANAGER_TWO, on( $champion ), true ), false !== strpos( $html, '<p class="wpcpm-sevent__joined">You are on it.</p><form class="wpcpm-inline-form wpcpm-sevent__leave"' ), strpos( $html, 'wpcpm-sevent__link' ), strpos( $html, 'meet.example' ) );
	},
	array( array( 'joined', 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events' ), true, true, false, false )
);
ck(
	'nothing to join on an event whose sponsor no longer offers events, or that is full, or that waits; a manager put on the paused sponsor\'s event may still leave it',
	static function () use ( $paused, $partner, $waiting ) {
		$none = array( actions_for( $paused, MANAGER ), actions_for( $waiting, MANAGER ) );
		add_post_meta( $partner, '_wpcpm_sevent_person', STUDENT );
		$none[] = actions_for( $partner, MANAGER );
		delete_post_meta( $partner, '_wpcpm_sevent_person', STUDENT );
		add_post_meta( $paused, '_wpcpm_sevent_person', MANAGER );
		$leave = actions_for( $paused, MANAGER );
		delete_post_meta( $paused, '_wpcpm_sevent_person', MANAGER );
		return array( $none, false !== strpos( $leave, 'wpcpm_sevent_leave_' . $paused ), strpos( $leave, 'wpcpm_sevent_join' ) );
	},
	array( array( '', '', '' ), true, false )
);
ck(
	'and nothing at all while the launch switch is off',
	static function () use ( $champion ) {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$html                                      = actions_for( $champion, MANAGER );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return $html;
	},
	''
);

/* ---- once the event has taken place ------------------------------------------------ */

echo "\n=== Once an event has taken place ===\n";

set_now( utc( '2026-11-10 21:00' ) );

ck(
	'it leaves the list, and a Leave posted from a page drawn before is said as taken place',
	static function () use ( $partner ) { return array( strpos( draw( JOINER_TWO, 'student' ), 'Partners &quot;' ), press( 'handle_leave', pressing( $partner ), JOINER_TWO )[0], on( $partner ) ); },
	array( false, 'past', array( JOINER_TWO ) )
);

set_now( utc( '2026-10-06 12:00' ) );

/* ---- the program's own events ---------------------------------------------------- */

echo "\n=== The program's own events ===\n";

// The program plans an event of its own, hosted by the WordPress Credits Program with no sponsor and
// approved as it is planned; a Champion plans one beside it. Every event above is over by 10 January
// 2027, so these two are the list.
set_now( utc( '2027-01-10 12:00' ) );
$GLOBALS['sent'] = array();

$program_event = attempt( static function () { return WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2027-01-15 14:00' ), array( 'minutes' => 60, 'places' => 3, 'title' => 'The partners session', 'description' => 'What the program plans with its partners.', 'link' => 'https://meet.example/partners' ) ), MANAGER ); } );
$beside        = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( CALLOFF, event_fields( utc( '2027-01-20 15:00' ), array( 'minutes' => 60, 'places' => 0, 'title' => 'A webinar beside it', 'description' => 'Short and sweet.', 'link' => 'https://meet.example/beside' ) ), MANAGER, 'manager' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

ck(
	'the student\'s list names the program where a sponsor\'s event names the sponsor, soonest first beside a sponsor\'s, headed Events from our sponsors and the program while one of the program\'s is listed',
	static function () use ( $program_event, $beside ) {
		$html = draw( STUDENT, 'student' );
		return array(
			is_int( $program_event ) && is_int( $beside ),
			0 === strpos( $html, '<section class="wpcpm-sevents" id="wpcpm-sevents"><h4 class="wpcpm-sevents__heading wpcpm-calls__subheading">Events from our sponsors and the program</h4><p class="wpcpm-sevents__zone">Times are in your time zone, America / New York.</p><ul class="wpcpm-sevent__list"><li class="wpcpm-sevent__item"><p class="wpcpm-sevent__title">The partners session <span class="wpcpm-sevent__sponsor">the WordPress Credits Program</span></p><p class="wpcpm-sevent__meta">January 15, 2027 9:00 am to 10:00 am. 60 minutes. 3 places left.</p><p class="wpcpm-sevent__description">What the program plans with its partners.</p><div class="wpcpm-sevent__actions"><form class="wpcpm-inline-form wpcpm-sevent__join"' ),
			false !== strpos( $html, '<p class="wpcpm-sevent__title">A webinar beside it <span class="wpcpm-sevent__sponsor">Calloff Example</span></p>' ),
			substr_count( $html, '<li class="wpcpm-sevent__item">' ),
		);
	},
	array( true, true, true, 2 )
);
ck(
	'the heading wears the house\'s class for its place with the program in it, on the Mentor Report Card and the Institution Dashboard too',
	static function () {
		$out = array();
		foreach ( array( MENTOR => 'mentor', CONTACT => 'institution', MANAGER => 'admin' ) as $who => $back ) {
			$out[] = preg_match( '#<h[1-6] class="wpcpm-sevents__heading[^"]*">[^<]*</h[1-6]>#', draw( $who, $back ), $found ) ? $found[0] : 'no heading';
		}
		return $out;
	},
	array(
		'<h3 class="wpcpm-sevents__heading wpcpm-calls__heading">Events from our sponsors and the program</h3>',
		'<h2 class="wpcpm-sevents__heading wpcpm-student__heading">Events from our sponsors and the program</h2>',
		'<h3 class="wpcpm-sevents__heading">Events from our sponsors and the program</h3>',
	)
);
ck(
	'a mentor who acts for the Partner sees the program\'s event, which is nobody\'s own; a sponsor\'s member in no audience and a graduate see nothing',
	static function () use ( $program_event ) {
		$mentor_rep = draw( MENTOR_REP, 'mentor' );
		return array( false !== strpos( $mentor_rep, 'name="wpcpm_sevent_id" value="' . $program_event . '"' ), substr_count( $mentor_rep, '<li class="wpcpm-sevent__item">' ), draw( PARTNER_REP, 'student' ), draw( GRADUATE, 'student' ) );
	},
	array( true, 2, '', '' )
);

$GLOBALS['sent'] = array();

ck(
	'the Partner\'s mentor joins it and comes back to the Mentor Report Card; mailed once that the WordPress Credits Program hosts it, with when on their clock, how long, the call link and the calendar file that adds it',
	static function () use ( $program_event ) {
		$left = press( 'handle_join', pressing( $program_event, 'mentor' ), MENTOR_REP );
		$mail = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array( 'subject' => '', 'body' => '', 'files' => array() );
		return array( $left, on( $program_event ), sent_to(), $mail['subject'], $mail['body'], count( $mail['files'] ) );
	},
	array(
		array( 'joined', 'https://site.example/mentor-dashboard/#wpcpm-sevents' ),
		array( MENTOR_REP ),
		array( 'mel.mentorrep@people.example' ),
		'[WordPress Education] You joined: The partners session',
		"You joined an event hosted by the WordPress Credits Program.\n\n"
			. "Title: The partners session\nWhen: January 15, 2027 3:00 pm to 4:00 pm (Europe / Warsaw)\nLength: 60 minutes\nJoin the call: https://meet.example/partners\n\n"
			. 'The calendar file attached adds it to your calendar.',
		1,
	)
);

$GLOBALS['sent'] = array();

ck(
	'and leaves it: mailed that they left the event the WordPress Credits Program hosts, with the file that takes it off their calendar',
	static function () use ( $program_event ) {
		$left = press( 'handle_leave', pressing( $program_event, 'mentor' ), MENTOR_REP );
		$mail = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array( 'subject' => '', 'body' => '', 'files' => array() );
		return array( $left, on( $program_event ), $mail['subject'], $mail['body'], count( $mail['files'] ) );
	},
	array( array( 'left', 'https://site.example/mentor-dashboard/#wpcpm-sevents' ), array(), '[WordPress Education] You left: The partners session', 'You left an event hosted by the WordPress Credits Program. The calendar file attached takes it off your calendar.', 1 )
);
ck(
	'a person no event is for is told so in words that fit the program\'s events as well as a sponsor\'s',
	static function () { return WPCPM_Sponsor_Event_List::messages()['audience']; },
	array( 'error', 'These events are for the program\'s students, mentors, Administrators and institutions.' )
);
ck(
	'a program manager gets Join on it on the program\'s card, coming back there',
	static function () use ( $program_event ) { return false !== strpos( actions_for( $program_event, MANAGER_TWO ), '<input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_join_' . $program_event . '" /><input type="hidden" name="action" value="wpcpm_sevent_join" /><input type="hidden" name="wpcpm_sevent_id" value="' . $program_event . '" /><input type="hidden" name="wpcpm_sevent_back" value="admin" />' ); },
	true
);
ck(
	'and nothing at all while the launch switch is off',
	static function () use ( $program_event ) {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		$html                                      = draw( STUDENT, 'student' ) . actions_for( $program_event, MANAGER_TWO );
		$GLOBALS['settings']['sponsor_tiers_live'] = true;
		return $html;
	},
	''
);

set_now( utc( '2026-10-06 12:00' ) );

/* ---- the mails give a member's words as typed --------------------------------- */

echo "\n=== The mails give a member's words as typed ===\n";

// A sponsor's member types a "<3", a tag, a ">", an ampersand and quotes into the title. The cleaner
// takes the tag out and writes the "<" and the quotes after it as entities, and the store writes the
// ">" as one. A mail is plain text, so the person who joins or leaves reads the words, every entity
// read back, in the subject and the Title line alike; the mail layer cleans each subject, whose
// cleaner would read "<3 ... >" as a tag and drop it, words and all, and these go to it as plain text.
const SIGNS_SPONSOR = 'spoAAAA0000000010'; // Champion, no term: a member's words with signs in them.
const SIGNS_REP     = 60;
const SIGNS_TYPED   = 'We <3 "our" <b>team</b> & Bob\'s > all';
const SIGNS_MAILED  = 'We <3 "our" team & Bob\'s > all';

$GLOBALS['index'][ SIGNS_SPONSOR ] = array( 'record_id' => SIGNS_SPONSOR, 'name' => 'Signs Example', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' );
a_user( SIGNS_REP, 'Sid Signsrep', array( 'wpcpm_sponsor' ), array( 'wpcpm_sponsor_record_id' => SIGNS_SPONSOR, 'wpcpm_sponsor_active' => 1 ) );

$signs = attempt(
	static function () {
		$GLOBALS['uid'] = SIGNS_REP;
		$id             = WPCPM_Sponsor_Event::plan( SIGNS_SPONSOR, event_fields( utc( '2026-12-15 10:00' ), array( 'title' => SIGNS_TYPED ) ), SIGNS_REP, 'member' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

/** The one mail the last press sent, as its subject and its Title line. */
function signs_mail() {
	$mail = $GLOBALS['sent'] ? $GLOBALS['sent'][0] : array( 'subject' => '', 'body' => '' );
	return array( count( $GLOBALS['sent'] ), $mail['subject'], preg_match( '/^Title: .*$/m', $mail['body'], $found ) ? $found[0] : '' );
}

ck(
	'the member\'s title is stored with the tag taken out and the "<", the quotes after it and the ">" as entities',
	static function () use ( $signs ) { return get_post( $signs )->post_title; },
	'We &lt;3 &quot;our&quot; team & Bob\'s &gt; all'
);
ck(
	'a person who joins reads the title as typed, in the subject and in the Title line, every word kept',
	static function () use ( $signs ) {
		$GLOBALS['sent'] = array();
		press( 'handle_join', pressing( $signs ), STUDENT );
		return signs_mail();
	},
	array( 1, '[WordPress Education] You joined: ' . SIGNS_MAILED, 'Title: ' . SIGNS_MAILED )
);
ck(
	'and one who leaves reads it as typed in the subject',
	static function () use ( $signs ) {
		$GLOBALS['sent'] = array();
		press( 'handle_leave', pressing( $signs ), STUDENT );
		return array_slice( signs_mail(), 0, 2 );
	},
	array( 1, '[WordPress Education] You left: ' . SIGNS_MAILED )
);

attempt( static function () use ( $signs ) { return WPCPM_Sponsor_Event::cancel( $signs, MANAGER ); } );
$GLOBALS['sent'] = array();

/* ---- the house rules ---------------------------------------------------------- */

echo "\n=== The house rules ===\n";

$list_src = (string) @file_get_contents( $list_file );

ck(
	'the list holds no em or en dash, no sponsor ID compared with ===, no Airtable',
	array( '' !== $list_src, preg_match( '/\x{2013}|\x{2014}/u', $list_src ), preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $list_src ), false !== strpos( $list_src, 'WPCPM_Airtable' ) ),
	array( true, 0, 0, false )
);
ck(
	'and writes nothing of its own: the store writes, and the flash; the list reads the request, draws and mails',
	array( '' !== $list_src, preg_match( '/\b(update_post_meta|add_post_meta|delete_post_meta|wp_insert_post|wp_update_post|update_option|add_option|update_user_meta|record_sponsor)\s*\(/', $list_src ) ),
	array( true, 0 )
);
ck( 'every query the stand-ins were sent is one they model', array( class_exists( 'WPCPM_Sponsor_Event_List' ), $GLOBALS['unmodeled'] ), array( true, array() ) );

$GLOBALS['run_complete'] = true;

// What every mail of the suite says, as the recording made before the event and term emails moved
// into the template registry holds it (bin/stubs/mail-recording.php).
foreach ( wpcpm_recording_checks( 'sponsor-event-mails-list.json', '6b823d154b980a2d8a843b5f4b55c318440e1730a67d31771d0a6b74b16995c4', array_keys( array_filter( WPCPM_Mail_Catalog::emails(), static function ( $e ) { return WPCPM_Mail_Catalog::MODULE_EVENTS === $e['module']; } ) ), array(), "-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program." ) as $recorded_check ) {
	ck( $recorded_check[0], $recorded_check[1], $recorded_check[2] );
}

// The site's name as WordPress stores it, its "&" an entity, is handed over as stored and read back once
// by the registry: the mail to a person who joined and the mail to one who left name the site as a
// person typed it, and neither shows "&amp;" in its subject or its body. After the recording's
// checks, which build no mail of their own.
$GLOBALS['site_name'] = 'Arts &amp; Sciences Dashboard';
ck(
	'a site name holding an "&", stored as an entity, reads as typed in the subject of the mail to a person who joined and of the mail to one who left, and "&amp;" is in neither their subjects nor their bodies',
	static function () use ( $partner ) {
		$out = array();
		foreach ( array( 'joined_mail' => 'event-joined', 'left_mail' => 'event-left' ) as $method => $id ) {
			$mail       = ( new ReflectionMethod( 'WPCPM_Sponsor_Event_List', $method ) )->invoke( null, get_post( $partner ), 'Partner Example', $id, $GLOBALS['users'][ STUDENT ] );
			$out[ $id ] = array( 0 === strpos( $mail['subject'], '[Arts & Sciences Dashboard] ' ), false === strpos( $mail['subject'] . $mail['body'], '&amp;' ) );
		}
		return $out;
	},
	array( 'event-joined' => array( true, true ), 'event-left' => array( true, true ) )
);
unset( $GLOBALS['site_name'] );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
