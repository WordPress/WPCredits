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
 *   from the student's funnel and the mentor's sessions column, and the Institution Dashboard on its
 *   Events view, each behind class_exists(). On the Student Report Card
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
 *   event whose sponsor no longer offers events or that is full, and nothing on a waiting one. With the
 *   Administrator Dashboard's switch on they are a decision of the event's Decide row (`decision_parts()`).
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
// The Institution Dashboard's view address (Task 9: its event list's way back opens the Events view), as WordPress
// builds it: the new keys and values set on the query as given, the fragment kept at the end.
function add_query_arg( $set, $url = '' ) {
	$frag = '';
	if ( false !== ( $h = strpos( (string) $url, '#' ) ) ) {
		$frag = substr( (string) $url, $h );
		$url  = substr( (string) $url, 0, $h );
	}
	$pairs = array();
	foreach ( (array) $set as $k => $v ) {
		$pairs[] = $k . '=' . $v;
	}
	return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs ) . $frag;
}
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
// The nonce as the suite has always read it: the nonce keyed to its action, alone. With `$GLOBALS['faithful_nonces']`
// set, as WordPress prints it: the field with the id core gives it and the referer beside it, so a form drawn through
// `WPCPM_UI::nonce()` (the same name and value, no id) can be compared with today's field by field (Task 13c).
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
	if ( empty( $GLOBALS['faithful_nonces'] ) ) {
		echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />';
		return;
	}
	echo '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
	if ( $referer ) {
		wp_referer_field();
	}
}
function wp_create_nonce( $action = -1 ) { return 'nonce-' . $action; }
function wp_referer_field( $display = true ) {
	$field = '<input type="hidden" name="_wp_http_referer" value="/administrator-dashboard/" />';
	if ( $display ) {
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A constant.
	}
	return $field;
}
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
require_once __DIR__ . '/stubs/view-faults.php';
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
	// Read-only: what `take()` would return, nothing deleted (Institution Dashboard frame, Task 3).
	public static function peek( $channel, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return isset( $GLOBALS['flash'][ $user_id ][ (string) $channel ] ) ? $GLOBALS['flash'][ $user_id ][ (string) $channel ] : '';
	}
}

// What the Institution Dashboard's `views_for()` asks besides the list, for the contact's school alone:
// the policy allows every question, the agreement is settled, enrollment is on (Task 3, fix round 1).
class WPCPM_Institution_Policy {
	const ACT_VIEW_ROSTER          = 'view_roster';
	const ACT_ADD_STUDENT          = 'add_student';
	const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
	public static function subject_institution( $record ) { return array( 'institution_ids' => array( (string) $record ) ); }
	public static function decide( $act, array $subject, $user = null ) { return array( 'allowed' => array( 'recSEED0000000301' ) === $subject['institution_ids'] ); }
}
class WPCPM_Institution_Agreement {
	public static function is_settled( $record ) { return 'recSEED0000000301' === (string) $record; }
}
class WPCPM_Institution_Import_Form {
	public static function enabled() { return true; }
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
// The surface switch and the shared parts: `render_actions()` draws its buttons through them on the Administrator
// Dashboard while its switch is on, and today's markup everywhere else (UI shared parts, Task 13c).
require_once __DIR__ . '/../includes/class-wpcpm-ui.php';
// The Institution Dashboard's list on the parts (Task 22): its outcome and its folded description need the card shell, the form errors' link and the icons; the sanitizer is
// core's, as the other card suites stand it in.
require_once __DIR__ . '/../includes/class-wpcpm-icons.php';
require_once __DIR__ . '/../includes/class-wpcpm-form-errors.php';
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $c, $f = '' ) { $c = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); return '' === $c ? (string) $f : $c; }
}

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
const TUTOR       = 31;

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
// A tutor of the same school (Institutions release 1): an account of the institution that sees and changes nothing.
a_user( TUTOR, 'Tess Tutor', array( 'wpcpm_institution' ), array( 'wpcpm_institution_record_id' => 'recSEED0000000301', 'wpcpm_institution_active' => 1, 'wpcpm_institution_level' => 'tutor' ) );

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
// The Institution Dashboard in the frame (its Task 5): the list is the Events view's card, drawn behind
// class_exists(), and the menu offers Events on a contact's own page alone, a tutor not among them (a Join would put
// a tutor on the event), while the school is not locked and the list has something to show, asked by a peek.
$school_view  = body_of( $school_src, 'private static function render_view(' );
$school_views = body_of( $school_src, 'private static function work_out_views(' );
ck(
	'the Institution Dashboard calls it behind class_exists() on its Events view, offered for a contact of the institution on their own page',
	array(
		false !== strpos( $school_view, "case self::VIEW_EVENTS:" ),
		false !== strpos( $school_view, "if ( class_exists( 'WPCPM_Sponsor_Event_List' ) ) {\n\t\t\t\t\tWPCPM_Sponsor_Event_List::render( \$viewer, WPCPM_Sponsor_Event_List::BACK_INSTITUTION );" ),
		strpos( $school_view, 'case self::VIEW_EVENTS:' ) < strpos( $school_view, 'WPCPM_Sponsor_Event_List::render(' ),
		false !== strpos( $school_views, "\$own_page = (string) WPCPM_Institution_Members::institution_of( \$viewer ) === \$record\n\t\t\t&& WPCPM_Institution_Members::LEVEL_TUTOR !== WPCPM_Institution_Members::level_of( \$viewer );" ),
		false !== strpos( $school_views, "if ( \$own_page && ! \$locked && class_exists( 'WPCPM_Sponsor_Event_List' ) && WPCPM_Sponsor_Event_List::shows( \$viewer ) ) {" ),
		substr_count( $school_src, 'WPCPM_Sponsor_Event_List::render(' ),
	),
	array( true, true, true, true, true, 1 )
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

// Whether the list would print its section (Institution Dashboard frame, Task 3): the Events view is
// in a school's menu exactly when the list has something to draw there, and `render()` asks the same
// three questions through it, so the two cannot disagree. The list itself prints byte for byte what it
// printed at `c0dfdf0a`: an institution's contact with two events, and a graduate no event is for.
/** Whether the list shows for `$viewer`, signed in as `$uid` (the viewer when not given). */
function shows_for( $viewer, $uid = null ) {
	$GLOBALS['uid'] = null === $uid ? (int) $viewer : (int) $uid;
	return WPCPM_Sponsor_Event_List::shows( $GLOBALS['users'][ (int) $viewer ] );
}
ck( 'the list shows for a viewer with an open event', static function () { return shows_for( CONTACT ); }, true );
ck( 'the list does not show for another user than the one signed in', static function () { return array( shows_for( CONTACT, MANAGER ), shows_for( CONTACT, 0 ) ); }, array( false, false ) );
ck(
	'the list does not show while it is not live',
	static function () {
		$GLOBALS['settings']['sponsor_tiers_live'] = false;
		try {
			return shows_for( CONTACT );
		} finally {
			$GLOBALS['settings']['sponsor_tiers_live'] = true;
		}
	},
	false
);
ck( 'the list does not show with no event and no pending message', static function () { return array( shows_for( GRADUATE ), shows_for( STRANGER ) ); }, array( false, false ) );
ck(
	'the list shows with no event but a pending message',
	static function () {
		$GLOBALS['flash'][ GRADUATE ]['sevent-list'] = 'gone';
		try {
			return shows_for( GRADUATE );
		} finally {
			unset( $GLOBALS['flash'][ GRADUATE ] );
		}
	},
	true
);
// Fix round 1: asking whether the list shows peeks at its message and takes nothing, so a page that
// asks and then draws no list leaves the message, in the suite's own storage, for the next request.
ck(
	'shows() leaves the pending message stored',
	static function () {
		$GLOBALS['flash'][ CONTACT ]['sevent-list'] = 'full';
		try {
			return array( shows_for( CONTACT ), $GLOBALS['flash'][ CONTACT ]['sevent-list'] ?? null );
		} finally {
			unset( $GLOBALS['flash'][ CONTACT ] );
		}
	},
	array( true, 'full' )
);
ck(
	'shows() twice, then render() still prints the message, and takes it',
	static function () {
		$GLOBALS['flash'][ CONTACT ]['sevent-list'] = 'full';
		try {
			shows_for( CONTACT );
			shows_for( CONTACT );
			$html = draw( CONTACT, 'institution' );
			return array( false !== strpos( $html, '<p class="wpcpm-sevent__message is-error" role="status">That event filled up before you could join it.</p>' ), isset( $GLOBALS['flash'][ CONTACT ]['sevent-list'] ) );
		} finally {
			unset( $GLOBALS['flash'][ CONTACT ] );
		}
	},
	array( true, false )
);
ck(
	'a request that asks views_for() and draws no list leaves the message for the next request, which prints it',
	static function () {
		$GLOBALS['flash'][ CONTACT ]['sevent-list'] = 'full';
		try {
			$GLOBALS['uid'] = CONTACT;
			$views          = WPCPM_Institutions_Dashboard::views_for( 'recSEED0000000301', $GLOBALS['users'][ CONTACT ], false );
			$left           = $GLOBALS['flash'][ CONTACT ]['sevent-list'] ?? null;
			$next           = draw( CONTACT, 'institution' );
			return array( in_array( 'events', $views, true ), $left, false !== strpos( $next, 'That event filled up before you could join it.' ), isset( $GLOBALS['flash'][ CONTACT ]['sevent-list'] ) );
		} finally {
			unset( $GLOBALS['flash'][ CONTACT ] );
		}
	},
	array( true, 'full', true, false )
);
ck( 'shows() peeks and render() takes, through the one helper', array( false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-list.php' ), 'return null !== self::drawable( $viewer, false );' ), false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-list.php' ), '$drawable = self::drawable( $viewer );' ) ), array( true, true ) );
ck( 'the list a contact with two events reads is today\'s, byte for byte', static function () { return md5( draw( CONTACT, 'institution' ) ); }, 'c9bac41992e301f4900997237f7c633d' );
ck( 'the list a graduate with no event reads is today\'s: nothing', static function () { return md5( draw( GRADUATE, 'student' ) ); }, 'd41d8cd98f00b204e9800998ecf8427e' );
ck( 'render() and shows() ask their three questions through one helper, so the two cannot disagree', false !== strpos( body_of( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-list.php' ), 'public static function render( $viewer, $back )' ), 'self::drawable( $viewer )' ) && false !== strpos( body_of( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-list.php' ), 'public static function shows( WP_User $viewer ): bool' ), 'self::drawable( $viewer, false )' ), true );

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
	array( array( 'died', 'Log in to join an event.', 403 ), array( 'died', 'Log in to leave an event.', 403 ), array(), true, array() )
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
	'a tutor of an institution pressing Leave on an event they are not on writes and mails nothing',
	static function () use ( $partner ) {
		$before = store_state();
		press( 'handle_leave', pressing( $partner, 'institution' ), TUTOR );
		return store_state() === $before;
	},
	true
);
ck(
	'joining again is said as already on it, and nothing is written or mailed',
	static function () use ( $partner ) { $before = store_state(); return array( press( 'handle_join', pressing( $partner ), STUDENT ), store_state() === $before ); },
	array( array( 'already', 'https://site.example/student-dashboard/#wpcpm-sevents' ), true )
);

ck(
	'a tutor of an institution is not in the institutions\' audience: a hand-made Join with a valid nonce seats nobody, mails nobody and writes nothing',
	static function () use ( $partner ) {
		$before = store_state();
		$join   = press( 'handle_join', pressing( $partner, 'institution' ), TUTOR );
		return array( WPCPM_Sponsor_Event::audience_of( TUTOR ), WPCPM_Sponsor_Event::audience_of( CONTACT ), $join[0] ?? null, store_state() === $before );
	},
	array( '', WPCPM_Sponsor_Event::AUDIENCE_INSTITUTIONS, 'audience', true )
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
	array( 'https://site.example/student-dashboard/#wpcpm-sevents', 'https://site.example/mentor-dashboard/#wpcpm-sevents', 'https://site.example/institution-dashboard/?wpcpm_view=events#wpcpm-sevents', 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events' )
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
	array( array( 'joined', 'https://site.example/institution-dashboard/?wpcpm_view=events#wpcpm-sevents' ), 'https://site.example/mentor-dashboard/#wpcpm-sevents', 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events', 'https://site.example/student-dashboard/#wpcpm-sevents' )
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
	array( 'https://site.example/student-dashboard/#wpcpm-sevents', 'https://site.example/mentor-dashboard/#wpcpm-sevents', 'https://site.example/institution-dashboard/?wpcpm_view=events#wpcpm-sevents', 'https://site.example/administrator-dashboard/#wpcpm-sponsor-events', '', '', '' )
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

/* ---- Join and Leave on the shared parts (UI shared parts, Task 13c) ------------ */

echo "\n=== Join and Leave on the Administrator Dashboard's parts, today's markup everywhere else (UI shared parts, Task 13c) ===\n";

// `render_actions()` is drawn by the program's Events card on the Administrator Dashboard, and by the list on the
// Student and Mentor Report Cards (surface `report-cards`) and on the Institution Dashboard (surface `institution`),
// both off for this whole release. With the dashboard's switch on, Join and Leave are drawn through
// `WPCPM_UI::button()`, in today's words, posting what today's forms post; everywhere else, today's markup byte for
// byte. Two events of the program's own (a sponsor plans one at a time): one nobody here is on, one the student, the mentor, the institution's contact and
// the second manager are on.
set_now( utc( '2026-10-06 12:00' ) );
$ra_open   = attempt( static function () { return WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2026-12-15 10:00' ), array( 'title' => 'Parts webinar & "more"', 'places' => 0, 'link' => 'https://meet.example/parts-open', 'zone' => 'Europe/Warsaw' ) ), MANAGER ); } );
$ra_joined = attempt(
	static function () {
		// Its title is stored with the tag taken out, as the store's cleaner keeps a title.
		$id = WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2026-12-16 10:00' ), array( 'title' => 'Parts <b>talk</b>', 'places' => 9, 'link' => 'https://meet.example/parts-joined?a=1&b=2', 'zone' => 'Europe/Warsaw' ) ), MANAGER );
		foreach ( array( STUDENT, MENTOR, CONTACT, MANAGER_TWO ) as $person ) {
			add_post_meta( $id, '_wpcpm_sevent_person', $person );
		}
		return $id;
	}
);
// Each page's viewer: on the joined event, and not on the open one. The Administrator Dashboard's viewer on the open
// event is the manager nobody put on it.
$ra_viewers = array(
	'student'     => array( STUDENT, STUDENT ),
	'mentor'      => array( MENTOR, MENTOR ),
	'institution' => array( CONTACT, CONTACT ),
	'admin'       => array( MANAGER_TWO, MANAGER ),
);

/**
 * Join or Leave for one event, as one person sees them on one page, with the Administrator Dashboard's switch on and
 * a surface named, or with the switch as it ships and no surface named ('').
 *
 * @param int    $id      The event.
 * @param int    $uid     Who looks.
 * @param string $back    Which page.
 * @param string $surface The surface drawing, or ''.
 * @return string
 */
function ra_actions( $id, $uid, $back, $surface ) {
	$GLOBALS['uid'] = (int) $uid;
	if ( '' !== $surface ) {
		WPCPM_UI::set_live( array( 'administrator' ) );
		WPCPM_UI::enter( $surface );
	}
	ob_start();
	try {
		WPCPM_Sponsor_Event_List::render_actions( get_post( $id ), $GLOBALS['users'][ (int) $uid ], $back );
	} finally {
		$html = (string) ob_get_clean();
		if ( '' !== $surface ) {
			WPCPM_UI::leave();
			WPCPM_UI::set_live( null );
		}
	}
	return $html;
}

/**
 * Every form a render holds: how it posts, its guard, its hidden fields as `name=value` in the order a browser posts
 * them, its submits and the question it asks.
 *
 * @param string $html Markup.
 * @return array[]
 */
function ra_posted( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . (string) $html . '</div>' );
	libxml_clear_errors();
	$x   = new DOMXPath( $doc );
	$out = array();
	foreach ( $x->query( '//form' ) as $form ) {
		$hidden = array();
		foreach ( $x->query( './/input[@type="hidden"]', $form ) as $input ) {
			$hidden[] = $input->getAttribute( 'name' ) . '=' . $input->getAttribute( 'value' );
		}
		$submits = array();
		$asks    = '';
		foreach ( $x->query( './/button', $form ) as $button ) {
			$submits[] = $button->getAttribute( 'type' ) . ' ' . $button->getAttribute( 'name' ) . '=' . $button->getAttribute( 'value' ) . ' ' . trim( $button->textContent );
			$asks      = $button->getAttribute( 'data-wpcpm-confirm' );
		}
		$out[] = array(
			'posts'   => $form->getAttribute( 'method' ) . ' ' . $form->getAttribute( 'action' ),
			'guard'   => ( $form->hasAttribute( 'data-wpcpm-once' ) ? 'once ' : '' ) . $form->getAttribute( 'data-wpcpm-busy' ),
			'hidden'  => $hidden,
			'submits' => $submits,
			'asks'    => $asks,
		);
	}
	return $out;
}

// Check 4: off, and on the two surfaces that share the renderer (off in this release), today's markup. The digests
// are of `render_actions()` as the code at 6125f645 drew it, before Task 13c: this fixture, run on that commit's
// renderer before it was changed. Nothing in them is random per run: the post IDs are this suite's counter, the
// nonces its stand-in's.
$ra_off   = array();
$ra_pins  = array();
foreach ( $ra_viewers as $ra_back => $ra_who ) {
	foreach ( array( 'on it' => array( $ra_joined, $ra_who[0] ), 'not on it' => array( $ra_open, $ra_who[1] ) ) as $ra_state => $ra_case ) {
		$ra_off[ $ra_back . ', ' . $ra_state ] = ra_actions( $ra_case[0], $ra_case[1], $ra_back, '' );
		foreach ( array( 'report-cards', 'institution', 'wpcpm-sponsors/queue' ) as $ra_surface ) {
			$ra_pins[ $ra_back . ', ' . $ra_state . ', ' . $ra_surface ] = ra_actions( $ra_case[0], $ra_case[1], $ra_back, $ra_surface ) === $ra_off[ $ra_back . ', ' . $ra_state ];
		}
	}
}
ck(
	'off, and on the Report Cards, the Institution Dashboard and a wp-admin tab (each off in this release), Join and Leave are today\'s, byte for byte, for every page and both states, as before Task 13c',
	static function () use ( $ra_off, $ra_pins ) {
		return array( array_map( 'md5', $ra_off ), array_keys( array_filter( $ra_pins, static function ( $same ) { return ! $same; } ) ), strpos( implode( '', $ra_off ), 'wpcpm-ui-' ), strpos( implode( '', $ra_off ), 'wpcpm-btn' ) );
	},
	array(
		array(
			'student, on it'         => '92f2c1866382915a0d7379a0673b0529',
			'student, not on it'     => '40331a138978a7d48fa1aa1545b400af',
			'mentor, on it'          => '50e8662db24c08cce2bcd740318ec49d',
			'mentor, not on it'      => 'bcfb18a9138f20dc86834995b5e3b253',
			'institution, on it'     => '926b9c81786b7966cb17f7340b2f6358',
			'institution, not on it' => 'c6a2263d1422376ae0a90d0801763721',
			'admin, on it'           => '1f24348fe60dc526a3aa05d535fb3718',
			'admin, not on it'       => 'e5243f06db17f022e21ac84795f74aaa',
		),
		array(),
		false,
		false,
	)
);
ck(
	'and the whole list each of the three pages draws is the same off and with the Administrator Dashboard\'s switch on and its own surface named, Join, Leave and the call link as today',
	static function () {
		$out = array();
		foreach ( array( 'student' => array( STUDENT, 'report-cards' ), 'mentor' => array( MENTOR, 'report-cards' ), 'institution' => array( CONTACT, 'institution' ) ) as $back => $case ) {
			// Each draw a page of its own, with no outcome of an earlier press waiting to be said on one of them alone.
			unset( $GLOBALS['flash'][ $case[0] ] );
			$off = draw( $case[0], $back );
			WPCPM_UI::set_live( array( 'administrator' ) );
			WPCPM_UI::enter( $case[1] );
			try {
				$pinned = draw( $case[0], $back );
			} finally {
				WPCPM_UI::leave();
				WPCPM_UI::set_live( null );
			}
			$out[ $back ] = array( $pinned === $off, false !== strpos( $off, 'class="wpcpm-sevent__actions"' ), false !== strpos( $off, '>Join the call<' ) );
		}
		return $out;
	},
	array( 'student' => array( true, true, true ), 'mentor' => array( true, true, true ), 'institution' => array( true, true, true ) )
);

// Fix round 1, ruling 5: the parts drew Join and Leave for the Administrator Dashboard's card alone. Since the Institution
// Dashboard's own release (Task 22) its surface, live and named, draws the list and its presses on the parts, the call
// link with them; the Report Cards, which share the renderer, keep today's markup with it live (pinned below, in the
// Task 22 section).
ck(
	'with the Institution Dashboard\'s own surface live and named, its list, its Join, its Leave and the call link are drawn on the parts, the call link kept (Task 22, in place of fix round 1, ruling 5)',
	static function () use ( $ra_joined, $ra_open, $ra_off ) {
		$off = draw( CONTACT, 'institution' );
		WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
		WPCPM_UI::enter( 'institution' );
		try {
			unset( $GLOBALS['flash'][ CONTACT ] );
			$list = draw( CONTACT, 'institution' );
			ob_start();
			WPCPM_Sponsor_Event_List::render_actions( get_post( $ra_joined ), $GLOBALS['users'][ CONTACT ], 'institution' );
			$on_it = (string) ob_get_clean();
			ob_start();
			WPCPM_Sponsor_Event_List::render_actions( get_post( $ra_open ), $GLOBALS['users'][ CONTACT ], 'institution' );
			$not_on_it = (string) ob_get_clean();
		} finally {
			WPCPM_UI::leave();
			WPCPM_UI::set_live( null );
		}
		return array( $list === $off, $on_it === $ra_off['institution, on it'], $not_on_it === $ra_off['institution, not on it'], false !== strpos( $on_it, '>Join the call<' ), 0 === strpos( $list, '<section class="wpcpm-ui-block" id="wpcpm-sevents">' ) );
	},
	array( false, false, false, true, true )
);

// The Administrator Dashboard with its switch on: the card puts Join or Leave in the event's own Decide row (Task 18d),
// a decision that acts at once (`decision_parts()`), drawn here as the card draws it, in a Decide area named for the
// event; `render_actions()` prints nothing there.
/**
 * The manager's own press for one event as the Administrator Dashboard's card draws it: `decision_parts()`'s decision
 * in a Decide area of its own, the area named for the event, with the switch on; and whether they are on it.
 *
 * @param int $id  The event.
 * @param int $uid Who looks.
 * @return array{0: string, 1: bool}
 */
function ra_decide( $id, $uid ) {
	$GLOBALS['uid'] = (int) $uid;
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	try {
		$mine = WPCPM_Sponsor_Event_List::decision_parts( get_post( $id ), $GLOBALS['users'][ (int) $uid ] );
		$html = null === $mine['decision'] ? '' : WPCPM_UI::decide(
			array(
				'id'        => 'wpcpm-decide-event-' . (int) $id,
				'level'     => 'h5',
				'context'   => get_post( $id )->post_title,
				'side'      => 'front',
				'decisions' => array( $mine['decision'] ),
			)
		);
	} finally {
		WPCPM_UI::leave();
		WPCPM_UI::set_live( null );
	}
	return array( $html, $mine['joined'] );
}
$GLOBALS['faithful_nonces'] = true;
$ra_old_join  = ra_actions( $ra_open, MANAGER, 'admin', '' );
$ra_old_leave = ra_actions( $ra_joined, MANAGER_TWO, 'admin', '' );
list( $ra_new_join, $ra_join_on )   = ra_decide( $ra_open, MANAGER );
list( $ra_new_leave, $ra_leave_on ) = ra_decide( $ra_joined, MANAGER_TWO );
$ra_printed                         = ra_actions( $ra_open, MANAGER, 'admin', 'administrator' ) . ra_actions( $ra_joined, MANAGER_TWO, 'admin', 'administrator' );
unset( $GLOBALS['faithful_nonces'] );
ck(
	'on the Administrator Dashboard, Join and Leave post exactly what today\'s forms post: the same address, guard and busy words, the hidden fields in order with the nonce keyed to the event and its referer, the event and the way back to the card (posted fields)',
	static function () use ( $ra_old_join, $ra_old_leave, $ra_new_join, $ra_new_leave, $ra_open, $ra_joined ) {
		return array( ra_posted( $ra_new_join ) === ra_posted( $ra_old_join ), ra_posted( $ra_new_leave ) === ra_posted( $ra_old_leave ), ra_posted( $ra_old_join )[0]['hidden'] ?? null, ra_posted( $ra_old_leave )[0]['hidden'] ?? null, ra_posted( $ra_old_leave )[0]['asks'] ?? null, ra_posted( $ra_old_join )[0]['guard'] ?? null );
	},
	array(
		true,
		true,
		array( '_wpnonce=nonce-wpcpm_sevent_join_' . $ra_open, '_wp_http_referer=/administrator-dashboard/', 'action=wpcpm_sevent_join', 'wpcpm_sevent_id=' . $ra_open, 'wpcpm_sevent_back=admin' ),
		array( '_wpnonce=nonce-wpcpm_sevent_leave_' . $ra_joined, '_wp_http_referer=/administrator-dashboard/', 'action=wpcpm_sevent_leave', 'wpcpm_sevent_id=' . $ra_joined, 'wpcpm_sevent_back=admin' ),
		'Leave this event? Your place goes back.',
		'once Joining',
	)
);
ck(
	'Join is a decision of the event\'s Decide row that acts at once: the button part, the secondary (the card\'s one primary is the row\'s Approve: task V1, ruling 9), in today\'s word and named for its event to a screen reader, in its own form; nothing of today\'s classes is left, the nonce has no id, and no line of its own says anything (Task 18d)',
	static function () use ( $ra_new_join, $ra_open, $ra_join_on ) {
		return array(
			false !== strpos( $ra_new_join, '<div class="wpcpm-ui-actions"><form method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Joining">' ),
			false !== strpos( $ra_new_join, '<button type="submit" class="wpcpm-btn wpcpm-btn--secondary">Join<span class="screen-reader-text"> ' . esc_html( get_post( $ra_open )->post_title ) . '</span></button></form></div>' ),
			strpos( $ra_new_join, 'wpcpm-button' ),
			strpos( $ra_new_join, 'wpcpm-sevent__' ),
			strpos( $ra_new_join, 'wpcpm-inline-form' ),
			substr_count( $ra_new_join, ' id=' ),
			strpos( $ra_new_join, 'wpcpm-ui-intro' ),
			$ra_join_on,
		);
	},
	array( true, true, false, false, false, 1, false, false )
);
ck(
	'for a manager on the event: Leave, red (it leaves), at once in the Decide row, asking today\'s question before it posts, named for its event, its form today\'s; the card says "You are on it." among the event\'s facts (`joined`), so no line of its own over the row; no call link (the card has one)',
	static function () use ( $ra_new_leave, $ra_leave_on ) {
		return array(
			$ra_new_leave,
			strpos( $ra_new_leave, 'Join the call' ),
			$ra_leave_on,
		);
	},
	array(
		'<div class="wpcpm-ui-decide" id="wpcpm-decide-event-' . $ra_joined . '"><h5 class="wpcpm-ui-h">Decide</h5><div class="wpcpm-ui-actions"><form method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Leaving"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sevent_leave_' . $ra_joined . '"><input type="hidden" name="_wp_http_referer" value="/administrator-dashboard/" /><input type="hidden" name="action" value="wpcpm_sevent_leave" /><input type="hidden" name="wpcpm_sevent_id" value="' . $ra_joined . '" /><input type="hidden" name="wpcpm_sevent_back" value="admin" /><button type="submit" class="wpcpm-btn wpcpm-btn--danger" data-wpcpm-confirm="Leave this event? Your place goes back.">Leave<span class="screen-reader-text"> Parts talk</span></button></form></div></div>',
		false,
		true,
	)
);
ck(
	'and on the parts as today, nothing for an event with nothing to press: one that is full, for a manager not on it; and with the switch on render_actions() prints nothing for the card, whose Decide row carries the press',
	static function () use ( $ra_joined, $ra_printed ) {
		$full = ra_decide( $ra_joined, MANAGER )[0];
		update_post_meta( $ra_joined, '_wpcpm_sevent_places', 4 );
		$now_full = ra_decide( $ra_joined, MANAGER );
		update_post_meta( $ra_joined, '_wpcpm_sevent_places', 9 );
		return array( '' !== $full, $now_full, $ra_printed );
	},
	array( true, array( '', false ), '' )
);

/* ---- Events on the parts (Institution Dashboard, Task 22) ---------------------- */

echo "\n=== Events on the parts, the Report Cards' calendars unchanged (Institution Dashboard, Task 22) ===\n";

// A clock of its own, past every event above, so the lists below hold this section's three events alone. The mails the
// plans and approvals send are not the recording's: what was sent is put back after the section.
$t22_sent     = $GLOBALS['sent'];
$t22_recorded = isset( $GLOBALS['wpcpm_recorded'] ) ? $GLOBALS['wpcpm_recorded'] : null;
$t22_now      = $GLOBALS['now'];
set_now( utc( '2027-03-01 12:00' ) );
const T22_LONG = 'spoAAAA0000000006';
$GLOBALS['index'][ T22_LONG ] = array( 'record_id' => T22_LONG, 'name' => 'Examplesponsorforappliedwebengineeringco', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '' );

// One the reader is on (the contact, a student and a mentor), one with no places left (a student nobody else is), one of
// a sponsor whose name and whose title are 40-character words, past midnight on the reader's clock, its description long.
$t22_on   = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2027-03-10 10:00' ), array( 'title' => 'Program office hours & "more"', 'places' => 5, 'minutes' => 60, 'link' => 'https://meet.example/office?a=1&b=2', 'zone' => 'Europe/Warsaw', 'description' => 'Short words & "quotes".' ) ), MANAGER );
		foreach ( array( CONTACT, STUDENT, MENTOR ) as $person ) {
			add_post_meta( $id, '_wpcpm_sevent_person', $person );
		}
		return $id;
	}
);
$t22_full = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan_program( event_fields( utc( '2027-03-15 09:00' ), array( 'title' => 'A full workshop', 'places' => 1, 'link' => 'https://meet.example/full', 'zone' => 'Europe/Warsaw' ) ), MANAGER );
		add_post_meta( $id, '_wpcpm_sevent_person', JOINER_TWO );
		return $id;
	}
);
$t22_long = attempt(
	static function () {
		$id = WPCPM_Sponsor_Event::plan( T22_LONG, event_fields( utc( '2027-03-20 22:30' ), array( 'title' => 'Exampleworkshoponappliedwebengineeringxx', 'places' => 0, 'minutes' => 120, 'link' => 'https://meet.example/long', 'zone' => 'Europe/Warsaw' ) ), MANAGER, 'manager' );
		WPCPM_Sponsor_Event::approve( $id, MANAGER );
		return $id;
	}
);

/**
 * The list as one person sees it on one page, under a surface: `''` (none named), `off` (the Institution Dashboard
 * named, its switch as it ships), `parts` (the Institution Dashboard named and live), or another surface's name with
 * the Institution Dashboard live. No outcome waits unless one is given.
 *
 * @param int    $uid     Who looks, on their own page.
 * @param string $back    Which page.
 * @param string $surface The surface.
 * @param string $flash   An outcome waiting on the list's channel, or ''.
 * @return string
 */
function t22_draw( $uid, $back, $surface, $flash = '' ) {
	unset( $GLOBALS['flash'][ (int) $uid ] );
	if ( '' !== $flash ) {
		$GLOBALS['flash'][ (int) $uid ]['sevent-list'] = $flash;
	}
	t22_enter( $surface );
	try {
		return draw( $uid, $back );
	} finally {
		t22_leave( $surface );
	}
}

/** Name the surface t22_draw() is told, with the switch it says. */
function t22_enter( $surface ) {
	if ( '' === $surface ) {
		return;
	}
	if ( 'off' === $surface ) {
		WPCPM_UI::enter( 'institution' );
		return;
	}
	WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
	WPCPM_UI::enter( 'parts' === $surface ? 'institution' : $surface );
}

/** Leave what t22_enter() named. */
function t22_leave( $surface ) {
	if ( '' === $surface ) {
		return;
	}
	WPCPM_UI::leave();
	WPCPM_UI::set_live( null );
}

/** Join or Leave for one event, as `render_actions()` draws it for one person on one page, under a surface. */
function t22_actions( $id, $uid, $back, $surface ) {
	$GLOBALS['uid'] = (int) $uid;
	t22_enter( $surface );
	ob_start();
	try {
		WPCPM_Sponsor_Event_List::render_actions( get_post( $id ), $GLOBALS['users'][ (int) $uid ], $back );
	} finally {
		$html = (string) ob_get_clean();
		t22_leave( $surface );
	}
	return $html;
}

/** The outcome as the program's card draws it (`render_message()`), under a surface. */
function t22_message( $uid, $flash, $surface ) {
	$GLOBALS['uid']                                = (int) $uid;
	$GLOBALS['flash'][ (int) $uid ]['sevent-list'] = $flash;
	t22_enter( $surface );
	ob_start();
	try {
		WPCPM_Sponsor_Event_List::render_message();
	} finally {
		$html = (string) ob_get_clean();
		t22_leave( $surface );
	}
	return $html;
}

/** The words a reader sees and hears, a word each, sorted: what a drawing says, whatever its markup. */
function t22_words( $html ) {
	$text = html_entity_decode( strip_tags( str_replace( array( '<wbr>', '<' ), array( '', ' <' ), (string) $html ) ), ENT_QUOTES, 'UTF-8' );
	preg_match_all( '/[\p{L}\p{N}]+/u', $text, $m );
	$words = array_values( array_unique( $m[0] ) );
	sort( $words );
	return $words;
}

/** Today's list without its heading, which the parts do not print (fix round 1, point 1): the words the rest says. */
function t22_unheaded( $html ) {
	return (string) preg_replace( '#<h2 class="wpcpm-sevents__heading wpcpm-student__heading">[^<]*</h2>#', '', (string) $html );
}

/** What a drawing offers to press: each form's address and posted fields, and each link's address, how it opens and its words. */
function t22_offers( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . (string) $html . '</div>' );
	libxml_clear_errors();
	$x   = new DOMXPath( $doc );
	$out = array();
	foreach ( ra_posted( $html ) as $form ) {
		$out[] = 'form ' . $form['posts'] . ' ' . $form['guard'] . ' ' . implode( '&', $form['hidden'] ) . ' asks ' . $form['asks'];
	}
	foreach ( $x->query( '//a' ) as $a ) {
		$out[] = 'link ' . $a->getAttribute( 'href' ) . ' rel=' . $a->getAttribute( 'rel' ) . ' target=' . $a->getAttribute( 'target' ) . ' ' . trim( preg_replace( '/\s+/', ' ', $a->textContent ) );
	}
	sort( $out );
	return $out;
}

// Every state the class draws, on the old branch: each reader's list of the three events, the contact's with each
// outcome, and with no event to list each outcome alone (a graduate, whom no event is for), each event's Join or Leave,
// and the Report Cards' two lists; the program's card's outcome under Coming up.
$t22_statuses = array_keys( WPCPM_Sponsor_Event_List::messages() );
$t22_cases    = array(
	'contact, three events' => array( 'list', CONTACT, 'institution', '' ),
	'tutor, three events'   => array( 'list', TUTOR, 'institution', '' ),
	'manager, three events' => array( 'list', MANAGER, 'institution', '' ),
	'student, Student Report Card' => array( 'list', STUDENT, 'student', '' ),
	'mentor, Mentor Report Card'   => array( 'list', MENTOR, 'mentor', '' ),
	'student, a full one, Student Report Card' => array( 'list', JOINER_TWO, 'student', '' ),
);
foreach ( $t22_statuses as $t22_status ) {
	$t22_cases[ 'contact, three events, ' . $t22_status ] = array( 'list', CONTACT, 'institution', $t22_status );
	$t22_cases[ 'no event, ' . $t22_status ]              = array( 'list', GRADUATE, 'institution', $t22_status );
	$t22_cases[ 'student, ' . $t22_status ]               = array( 'list', STUDENT, 'student', $t22_status );
	$t22_cases[ 'program card, ' . $t22_status ]          = array( 'message', MANAGER, '', $t22_status );
}
foreach ( array( 'on it' => $t22_on, 'full' => $t22_full, 'long' => $t22_long ) as $t22_which => $t22_id ) {
	foreach ( array( 'contact' => array( CONTACT, 'institution' ), 'tutor' => array( TUTOR, 'institution' ), 'student' => array( STUDENT, 'student' ), 'mentor' => array( MENTOR, 'mentor' ), 'manager' => array( MANAGER, 'admin' ) ) as $t22_who => $t22_case ) {
		$t22_cases[ 'actions, ' . $t22_which . ', ' . $t22_who ] = array( 'actions', $t22_case[0], $t22_case[1], (int) $t22_id );
	}
}

/** One old-branch case, drawn under a surface. */
function t22_case( array $case, $surface ) {
	if ( 'list' === $case[0] ) {
		return t22_draw( $case[1], $case[2], $surface, $case[3] );
	}
	if ( 'message' === $case[0] ) {
		return t22_message( $case[1], $case[3], $surface );
	}
	return t22_actions( $case[3], $case[1], $case[2], $surface );
}

// Digests of each case as the class at 68d6c986 draws it (T22_PRINT=1 prints them), before the parts' branch.
// `format_range()`'s words for the fix round's real calls, at 68d6c986 (T22_PRINT=1 prints them).
const T22_RANGE_BASE = 'e20551e12810373ff97d1a366ac9e87f';

const T22_BASE = array(
	'contact, three events' => 'bba8096db304be2d29296ef9559a7ae2',
	'tutor, three events' => 'd41d8cd98f00b204e9800998ecf8427e',
	'manager, three events' => 'b20080b59e55f74bc6725977428cd788',
	'student, Student Report Card' => 'e17cf3d4ab89a9a9355c1b8bf9262b1c',
	'mentor, Mentor Report Card' => 'e3de8758c8bd142b105ab169ebb6783b',
	'student, a full one, Student Report Card' => '26b5300b4157468414076169bbb90c3a',
	'contact, three events, joined' => '06529fd30d69a18c82be17031af8c7ec',
	'no event, joined' => 'a3246612ff857f705a0bba3e6acc9455',
	'student, joined' => 'ad9f2bb3c9e14523f18946ec29cb9102',
	'program card, joined' => '75f928644942986165c63ca897635989',
	'contact, three events, left' => 'ad82bf00759cdbc38391c3dd29531221',
	'no event, left' => '4a63773d5a7014ed93977d532e278d90',
	'student, left' => 'ead83b93fa67a60cc85267e37813adda',
	'program card, left' => '28fcc6c095c77dd238b630548c12d5ae',
	'contact, three events, full' => '844242a423dae05635619d3c7fa80343',
	'no event, full' => 'a1ccd834904c416f855409992c50a87c',
	'student, full' => '382a1232a7ce99d373f5db78c6317128',
	'program card, full' => '4dda23f300aabedb269d0a5471634c88',
	'contact, three events, past' => '01545a7feba18eacaa75fb244d07682a',
	'no event, past' => '14d02bddefabd3a2d3a86e8923f64fb2',
	'student, past' => 'b9fe3f4f6cce8d4ee46de96083cc85f6',
	'program card, past' => '7f6ae4b5d3fc114653f04ddf2d44cffc',
	'contact, three events, not-open' => '99ccedd1ba50926a64e0707318410af1',
	'no event, not-open' => '35634c335f14a353458d1e470148c5ec',
	'student, not-open' => '15ae3a06978ccaca6366abadbfb6c2a3',
	'program card, not-open' => '0c6b5c65da0eb6dcdfb5447b83df0397',
	'contact, three events, audience' => '440cfdedbaa8c129914d814e6cc78c6b',
	'no event, audience' => 'da5bbd95cc561fd6c7ff32324fb96d2f',
	'student, audience' => 'e46f1dc28254b854fbc479f315fbe182',
	'program card, audience' => '920a0eb8f5708cf2206f253f6ec984c0',
	'contact, three events, own' => 'c3f87b65a6db93c88a5cbcefb8f46ae8',
	'no event, own' => '53b562c044cde1d8b66494d10f6b7d45',
	'student, own' => '054aa45e6446c73ee45d024452699883',
	'program card, own' => '702c842cc00bbff8283d32d41d6ef166',
	'contact, three events, busy' => '1f3787e0992bf08ae03c9af232794797',
	'no event, busy' => 'c5d45386fb5e328cfd7ce0cbfd296d02',
	'student, busy' => 'f4787c92421f2264efde5f234eccdf4c',
	'program card, busy' => '79e85cbf2cf1c8f926810f2e5f38e044',
	'contact, three events, already' => 'd52d91bceaf7818f36563ba6bea6435b',
	'no event, already' => '015260beaa79bf6d769bd449b7a4df7c',
	'student, already' => 'fcbece24fe043a6b4067ed92e5de9d2c',
	'program card, already' => '9af694c0beceacfbc79d831292893a6f',
	'contact, three events, not-joined' => '08c856ee2de85e8e61fe6560cbc72f1e',
	'no event, not-joined' => '3ce334bc39142f56a42c95349a2d65c1',
	'student, not-joined' => '51d735b6f30d742fd04168e03e053985',
	'program card, not-joined' => '6f7be4a927f35e586db8cf66097f334f',
	'contact, three events, canceled' => '9dbeefb7f1d6ea8f902d06c6ee9ff265',
	'no event, canceled' => '0088a5667367555d6dee691fe15c1a45',
	'student, canceled' => '891629143a51864e0cb65b510c4cd7c9',
	'program card, canceled' => '2820f93ed8b9f40f04276c0624212123',
	'contact, three events, gone' => '1b92354ea06c9b2ab58ac98fd1273560',
	'no event, gone' => '19e6e4ab38a4ae641970272ba0602c4d',
	'student, gone' => '315c9664c52e68d317d8210856999523',
	'program card, gone' => 'ef3399d9b2be58b16e2b7896a60f7072',
	'contact, three events, failed' => 'b21e98197add679b52eb69847a3b779d',
	'no event, failed' => '806c847331ea2c75d4a49ff51ee3add9',
	'student, failed' => '39c96042a955679caa2b924183c35d4d',
	'program card, failed' => 'ba8b0b1eb498259d05bce9a5ef4a0362',
	'actions, on it, contact' => 'f58cd410ce96b1693e52a0c4c07e33d0',
	'actions, on it, tutor' => '3a0e643448009fcb033393b11b1fd251',
	'actions, on it, student' => '58ec65749380e828009c0581cbb300f8',
	'actions, on it, mentor' => '899ac43f9dc9442241e9390701fd1049',
	'actions, on it, manager' => '6c79d73ffecfea80003543f24bd1b2eb',
	'actions, full, contact' => 'd41d8cd98f00b204e9800998ecf8427e',
	'actions, full, tutor' => 'd41d8cd98f00b204e9800998ecf8427e',
	'actions, full, student' => 'd41d8cd98f00b204e9800998ecf8427e',
	'actions, full, mentor' => 'd41d8cd98f00b204e9800998ecf8427e',
	'actions, full, manager' => 'd41d8cd98f00b204e9800998ecf8427e',
	'actions, long, contact' => 'ba2eecd6c917f4107075e2e842ef0c0d',
	'actions, long, tutor' => 'ba2eecd6c917f4107075e2e842ef0c0d',
	'actions, long, student' => 'df95f2dd573b7e6cabebcdd7bc758d4e',
	'actions, long, mentor' => 'dbf0ee2a0229229cb4d05923c4cdb3df',
	'actions, long, manager' => '5259791b0d80d2c5c271ed27688bd589',
);

$t22_off = array();
foreach ( $t22_cases as $t22_name => $t22_case ) {
	$t22_off[ $t22_name ] = attempt( static function () use ( $t22_case ) { return t22_case( $t22_case, '' ); } );
}
if ( getenv( 'T22_PRINT' ) ) {
	echo var_export( array_map( 'md5', array_map( 'strval', $t22_off ) ), true ), "\n";
	$t22_ranges = array();
	foreach ( array( 'Europe/Warsaw', 'America/New_York', 'Asia/Tokyo' ) as $t22_zone ) {
		foreach ( array( array( '2027-03-10 10:00', '2027-03-10 11:00' ), array( '2027-03-20 22:30', '2027-03-21 00:30' ), array( '2026-11-10 19:00', '2026-11-10 20:30' ) ) as $t22_pair ) {
			$t22_ranges[] = WPCPM_Mentor_Calls::format_range( utc( $t22_pair[0] ), utc( $t22_pair[1] ), new DateTimeZone( $t22_zone ) );
		}
	}
	echo 'T22_RANGE_BASE ', md5( implode( '|', $t22_ranges ) ), "\n", implode( "\n", $t22_ranges ), "\n";
}
ck(
	'the old branch: every state the class draws is the base\'s, byte for byte (each reader\'s list, each outcome with and without an event, each Join and Leave, the Report Cards\' lists, the program card\'s outcome)',
	static function () use ( $t22_off ) { return array( count( $t22_off ), array_map( 'md5', array_map( 'strval', $t22_off ) ) === T22_BASE ); },
	array( count( T22_BASE ), true )
);
ck(
	'and the same bytes with the Institution Dashboard live under every other surface: the Report Cards, the Administrator Dashboard, the Sponsor Dashboard, a wp-admin tab',
	static function () use ( $t22_cases, $t22_off ) {
		$differ = array();
		foreach ( array( 'report-cards', 'administrator', 'sponsor', 'wpcpm-sponsors/queue' ) as $surface ) {
			foreach ( $t22_cases as $name => $case ) {
				// The program's card draws its outcome on the Administrator Dashboard; its Join and Leave are its Decide row's there.
				if ( 'administrator' === $surface && 'actions' === $case[0] && 'admin' === $case[2] ) {
					continue;
				}
				if ( t22_case( $case, $surface ) !== $t22_off[ $name ] ) {
					$differ[] = $surface . ': ' . $name;
				}
			}
		}
		return $differ;
	},
	array()
);
ck(
	'the Report Cards\' calendars: the Student Report Card\'s and the Mentor Report Card\'s lists, with the Institution Dashboard live and the Report Cards named, are today\'s, their call links among them',
	static function () use ( $t22_off ) {
		$student = t22_draw( STUDENT, 'student', 'report-cards' );
		$mentor  = t22_draw( MENTOR, 'mentor', 'report-cards' );
		return array( $student === $t22_off['student, Student Report Card'], $mentor === $t22_off['mentor, Mentor Report Card'], false !== strpos( $student, 'class="wpcpm-sevent__link"' ), strpos( $student . $mentor, 'wpcpm-ui-' ) );
	},
	array( true, true, true, false )
);

// The parts: the Institution Dashboard live and named.
$t22_new = array();
foreach ( $t22_cases as $t22_name => $t22_case ) {
	if ( 'institution' === $t22_case[2] ) {
		$t22_new[ $t22_name ] = attempt( static function () use ( $t22_case ) { return t22_case( $t22_case, 'parts' ); } );
	}
}
// The whole view on the real list (Task 23, fix round 1): every case the Institution Dashboard draws, its long
// descriptions' folds open, under the Events view's own head (`bin/stubs/view-faults.php`).
$t22_views = array_map( static function ( $html ) { return str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner">', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open>', (string) $html ); }, $t22_new );
ck( 'the whole Events view on the real list, every case: one H1, the headings in order, no id twice, the lede once, no title again', array( wpcpm_view_faults_of( 'Events', $t22_views ), count( array_filter( array_map( 'trim', $t22_views ) ) ) >= 20 ), array( array(), true ) );
// An event with its own actions (Task 23, fix round 1, S5): each event of the three, name, facts and actions read on
// its own line. Read off the fixture by hand: the school's contact is on the office hours (Leave and the call there),
// the full workshop offers nobody a place, the long-named sponsor's event is open to join; an Administrator is on
// none of them, so both open events offer Join.
$t22_rows_first = 'Program office hours & "more" | the WordPress Credits Program; March 10, 2027 11:00 am to 12:00 pm; 60 minutes; 2 places left | ';
$t22_rows_full  = 'A full workshop | the WordPress Credits Program; March 15, 2027 10:00 am to 11:30 am; 90 minutes; Full | ';
$t22_rows_long  = 'Exampleworkshoponappliedwebengineeringxx | Examplesponsorforappliedwebengineeringco; March 20, 2027 11:30 pm to March 21, 2027 1:30 am; 120 minutes; Open to everyone | Join';
ck( 'each event reads its own facts and offers its own actions on its own line, for the contact and for an Administrator', array( wpcpm_list_rows( $t22_new['contact, three events'] ), wpcpm_list_rows( $t22_new['manager, three events'] ) ), array(
	array( $t22_rows_first . 'Leave, Join the call', $t22_rows_full, $t22_rows_long ),
	array( $t22_rows_first . 'Join', $t22_rows_full, $t22_rows_long ),
) );
$t22_list = (string) $t22_new['contact, three events'];
if ( getenv( 'T22_PRINT' ) ) {
	echo $t22_list, "\n";
}

ck(
	'the view\'s content is one headless block, the list\'s anchor on it: no card head, no fold, none of today\'s classes, and no heading at all: "Events from our sponsors" says what the view\'s lede says one line above, and each row names its host (fix round 1, point 1)',
	static function () use ( $t22_list ) {
		return array(
			0 === strpos( $t22_list, '<section class="wpcpm-ui-block" id="wpcpm-sevents"><p class="wpcpm-ui-intro">Times are in your time zone' ),
			substr_count( $t22_list, 'id="wpcpm-sevents"' ),
			substr( $t22_list, -10 ),
			preg_match( '/<h[1-6]|Events from our sponsors/', $t22_list ),
			strpos( $t22_list, 'wpcpm-ui-fold-card' ),
			strpos( $t22_list, '<h2' ),
			preg_match( '/wpcpm-sevent__|wpcpm-sevents__|wpcpm-button|wpcpm-inline-form|wpcpm-student__/', $t22_list ),
		);
	},
	array( true, 1, '</section>', 0, false, false, 0 )
);
ck(
	'the zone is named once, in today\'s words, a fact only it holds; then one list of rows, its facts under the name, a row per event, soonest first',
	static function () use ( $t22_list ) {
		preg_match_all( '/<li class="wpcpm-ui-list__row[^"]*">/', $t22_list, $rows );
		return array(
			substr_count( $t22_list, '<p class="wpcpm-ui-intro">Times are in your time zone, Europe / Warsaw.</p>' ),
			substr_count( $t22_list, '<ul class="wpcpm-ui-list wpcpm-ui-list--under">' ),
			count( $rows[0] ),
			strpos( $t22_list, 'Program office hours' ) < strpos( $t22_list, 'A full workshop' ) && strpos( $t22_list, 'A full workshop' ) < strpos( $t22_list, 'Exampleworkshop' ),
		);
	},
	array( 1, 1, 3, true )
);
ck(
	'the event the reader is on: its name, the host, its day and time on the reader\'s clock, each date whole, how long and the places left as facts in today\'s words; "You are on it." its note; Leave quiet red with today\'s question and Join the call a quiet link that opens apart as today, both named for the event',
	static function () use ( $t22_list ) {
		preg_match( '/<li class="wpcpm-ui-list__row wpcpm-ui-list__row--note wpcpm-ui-list__row--more">.*?<\/li>/s', $t22_list, $row );
		$row = $row[0] ?? '';
		return array(
			false !== strpos( $row, '<span class="wpcpm-ui-list__name">Program office hours &amp; &quot;more&quot;</span>' ),
			false !== strpos( $row, '<span class="wpcpm-ui-item__fact">the WordPress Credits Program</span>' ),
			false !== strpos( $row, '<span class="wpcpm-ui-item__fact"><span class="wpcpm-ui-whole">March 10, 2027 11:00 am</span> to <span class="wpcpm-ui-whole">12:00 pm</span></span>' ),
			false !== strpos( $row, '<span class="wpcpm-ui-item__fact">60 minutes</span><span class="wpcpm-ui-item__fact">2 places left</span>' ),
			false !== strpos( $row, '<p class="wpcpm-ui-list__note">You are on it.</p>' ),
			false !== strpos( $row, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger" data-wpcpm-confirm="Leave this event? Your place goes back.">Leave<span class="screen-reader-text"> Program office hours &amp; &quot;more&quot;</span></button>' ),
			false !== strpos( $row, '<a class="wpcpm-btn wpcpm-btn--quiet" href="https://meet.example/office?a=1&amp;b=2" rel="noopener noreferrer" target="_blank">Join the call<span class="screen-reader-text"> Program office hours &amp; &quot;more&quot;</span></a>' ),
			false !== strpos( $row, '<p class="wpcpm-ui-note">Short words &amp; &quot;quotes&quot;.</p>' ),
		);
	},
	array( true, true, true, true, true, true, true, true )
);
ck(
	'the full one says so in words, and offers nothing: no Join drawn to look disabled',
	static function () use ( $t22_list ) {
		preg_match( '/<li class="wpcpm-ui-list__row wpcpm-ui-list__row--more">(?:(?!<\/li>).)*A full workshop.*?<\/li>/s', $t22_list, $row );
		$row = $row[0] ?? '';
		return array( false !== strpos( $row, '<span class="wpcpm-ui-item__fact">Full</span>' ), strpos( $row, 'wpcpm-ui-list__act' ), strpos( $row, 'disabled' ), strpos( $row, '<form' ) );
	},
	array( true, false, false, false )
);
ck(
	'the 40-character name and host are plain words the sheet breaks where they would overflow, no `<wbr>` (fix round 1, point 2), the range past midnight keeps each of its dates whole, the long description folds under today\'s words, and Join is quiet, named for the event',
	static function () use ( $t22_list ) {
		preg_match( '/<li class="wpcpm-ui-list__row wpcpm-ui-list__row--more">(?:(?!<\/li>).)*Exampleworkshop.*?<\/li>/s', $t22_list, $row );
		$row = $row[0] ?? '';
		return array(
			false !== strpos( $row, '<span class="wpcpm-ui-list__name">Exampleworkshoponappliedwebengineeringxx</span>' ),
			false !== strpos( $row, '<span class="wpcpm-ui-item__fact">Examplesponsorforappliedwebengineeringco</span>' ),
			false !== strpos( $row, '<span class="wpcpm-ui-whole">March 20, 2027 11:30 pm</span> to <span class="wpcpm-ui-whole">March 21, 2027 1:30 am</span>' ),
			false !== strpos( $row, '<span class="wpcpm-ui-item__fact">120 minutes</span><span class="wpcpm-ui-item__fact">Open to everyone</span>' ),
			false !== strpos( $row, '<span class="wpcpm-ui-fold__title">Read the description</span>' ),
			false !== strpos( $row, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet">Join<span class="screen-reader-text"> Exampleworkshoponappliedwebengineeringxx</span></button>' ),
			strpos( $row, 'Join the call' ),
		);
	},
	array( true, true, true, true, true, true, false )
);

// Join and Leave post exactly what they post today: core's nonce with its id and its referer stood in, compared form by
// form with today's (`ra_posted()`), and the call link with today's.
$GLOBALS['faithful_nonces'] = true;
$t22_faithful_off = t22_draw( CONTACT, 'institution', '' );
$t22_faithful_new = t22_draw( CONTACT, 'institution', 'parts' );
$t22_faithful_mgr = array( t22_draw( MANAGER, 'institution', '' ), t22_draw( MANAGER, 'institution', 'parts' ) );
unset( $GLOBALS['faithful_nonces'] );
ck(
	'Join and Leave post what they post today: the method, the address, the guard and its busy words, every hidden field in order (the nonce keyed to the event, the referer, the action, the event, the way back), the submit (no name, no value) and the browser\'s question',
	static function () use ( $t22_faithful_off, $t22_faithful_new, $t22_faithful_mgr, $t22_on, $t22_long ) {
		$old = ra_posted( $t22_faithful_off );
		$new = ra_posted( $t22_faithful_new );
		return array(
			count( $old ),
			$new === $old,
			ra_posted( $t22_faithful_mgr[1] ) === ra_posted( $t22_faithful_mgr[0] ),
			$new[0]['hidden'] ?? null,
			$new[0]['asks'] ?? null,
			$new[0]['guard'] ?? null,
			$new[1]['hidden'] ?? null,
			$new[1]['asks'] ?? null,
			$new[1]['submits'] ?? null,
		);
	},
	array(
		2,
		true,
		true,
		array( '_wpnonce=nonce-wpcpm_sevent_leave_' . $t22_on, '_wp_http_referer=/administrator-dashboard/', 'action=wpcpm_sevent_leave', 'wpcpm_sevent_id=' . $t22_on, 'wpcpm_sevent_back=institution' ),
		'Leave this event? Your place goes back.',
		'once Leaving',
		array( '_wpnonce=nonce-wpcpm_sevent_join_' . $t22_long, '_wp_http_referer=/administrator-dashboard/', 'action=wpcpm_sevent_join', 'wpcpm_sevent_id=' . $t22_long, 'wpcpm_sevent_back=institution' ),
		'',
		array( 'submit = Join Exampleworkshoponappliedwebengineeringxx' ),
	)
);
ck(
	'the referer is in the drawn markup of each form on both branches, once; the parts\' nonce carries no id, and no id repeats',
	static function () use ( $t22_faithful_off, $t22_faithful_new ) {
		preg_match_all( '/ id="([^"]+)"/', $t22_faithful_new, $ids );
		return array( substr_count( $t22_faithful_off, 'name="_wp_http_referer"' ), substr_count( $t22_faithful_new, 'name="_wp_http_referer"' ), substr_count( $t22_faithful_off, 'id="_wpnonce"' ), substr_count( $t22_faithful_new, 'id="_wpnonce"' ), count( $ids[1] ) === count( array_unique( $ids[1] ) ) );
	},
	array( 2, 2, 2, 0, true )
);
ck(
	'and the call link opens what it opens today, with today\'s rel and target, for the person on the event alone',
	static function () use ( $t22_faithful_off, $t22_faithful_new ) {
		$links = static function ( $html ) {
			return array_values( array_filter( t22_offers( $html ), static function ( $o ) { return 0 === strpos( $o, 'link ' ); } ) );
		};
		return array( $links( $t22_faithful_new ) === $links( $t22_faithful_off ), $links( $t22_faithful_new ) );
	},
	array( true, array( 'link https://meet.example/office?a=1&b=2 rel=noopener noreferrer target=_blank Join the call Program office hours & "more"' ) )
);

ck(
	'who sees what does not move: for the contact, a tutor (whom no event is for: nothing on either branch) and an Administrator, both branches say the same words and offer the same presses and links',
	static function () use ( $t22_off, $t22_new ) {
		$out = array();
		// The presses compared as WordPress prints today's nonce, with its referer, as the parts' nonce prints one.
		$GLOBALS['faithful_nonces'] = true;
		try {
			foreach ( array( 'contact, three events' => CONTACT, 'tutor, three events' => TUTOR, 'manager, three events' => MANAGER ) as $name => $uid ) {
				$offers       = array( t22_offers( t22_draw( $uid, 'institution', '' ) ), t22_offers( t22_draw( $uid, 'institution', 'parts' ) ) );
				$out[ $name ] = array( t22_words( $t22_new[ $name ] ) === t22_words( t22_unheaded( $t22_off[ $name ] ) ), $offers[1] === $offers[0], count( $offers[1] ) );
			}
		} finally {
			unset( $GLOBALS['faithful_nonces'] );
		}
		return $out;
	},
	array(
		'contact, three events' => array( true, true, 3 ),
		'tutor, three events'   => array( true, true, 0 ),
		'manager, three events' => array( true, true, 2 ),
	)
);
ck(
	'and so do the outcomes, with and without an event to list: each in its words, on each branch',
	static function () use ( $t22_off, $t22_new, $t22_statuses ) {
		$differ = array();
		$GLOBALS['faithful_nonces'] = true;
		try {
			foreach ( $t22_statuses as $status ) {
				foreach ( array( 'contact, three events, ' => CONTACT, 'no event, ' => GRADUATE ) as $base => $uid ) {
					$offers = array( t22_offers( t22_draw( $uid, 'institution', '', $status ) ), t22_offers( t22_draw( $uid, 'institution', 'parts', $status ) ) );
					if ( t22_words( $t22_new[ $base . $status ] ) !== t22_words( t22_unheaded( $t22_off[ $base . $status ] ) ) || $offers[1] !== $offers[0] ) {
						$differ[] = $base . $status;
					}
				}
			}
		} finally {
			unset( $GLOBALS['faithful_nonces'] );
		}
		return $differ;
	},
	array()
);
ck(
	'every outcome is the shell\'s message, its tone from the list\'s own map: a refusal alerts, a success is polite, each once, after the zone and before the list; with no event, the block holds the sentence alone',
	static function () use ( $t22_new, $t22_statuses ) {
		$out = array();
		foreach ( $t22_statuses as $status ) {
			$said  = WPCPM_Sponsor_Event_List::messages()[ $status ];
			$want  = WPCPM_Dashboard_Card::message( $said[0], $said[1], 'wpcpm-sevent__message' );
			$with  = (string) $t22_new[ 'contact, three events, ' . $status ];
			$alone = (string) $t22_new[ 'no event, ' . $status ];
			$out[] = array(
				substr_count( $with, $want ),
				strpos( $with, 'Times are in your time zone' ) < strpos( $with, $want ) && strpos( $with, $want ) < strpos( $with, '<ul class="wpcpm-ui-list' ),
				$alone === '<section class="wpcpm-ui-block" id="wpcpm-sevents">' . $want . '</section>',
				false !== strpos( $want, 'error' === $said[0] ? 'role="alert"' : 'role="status"' ),
			);
		}
		return array_values( array_unique( array_map( 'serialize', $out ) ) );
	},
	array( serialize( array( 1, true, true, true ) ) )
);
ck(
	'`render_actions()` on the parts prints the row\'s presses and link alone, and nothing where there is nothing to press',
	static function () use ( $t22_new ) {
		return array(
			0 === strpos( (string) $t22_new['actions, on it, contact'], '<form method="post" action="https://site.example/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Leaving">' ),
			false !== strpos( (string) $t22_new['actions, on it, contact'], '>Join the call<' ),
			strpos( (string) $t22_new['actions, on it, contact'], 'wpcpm-sevent__' ),
			$t22_new['actions, full, contact'],
			0 === strpos( (string) $t22_new['actions, long, contact'], '<form method="post"' ),
		);
	},
	array( true, true, false, '', true )
);

// Hostile input: a sponsor's name is what its people typed. The store's cleaner takes tags out of a title and a
// description, so the sponsor's name carries the tag, the quote and the ampersand to both branches.
ck(
	'a sponsor\'s name holding a tag, a double quote and an ampersand is escaped once on both branches: no raw tag, no broken attribute, nothing escaped twice',
	static function () {
		$GLOBALS['index'][ T22_LONG ]['name'] = '<script>alert(1)</script> "Q" & Co';
		try {
			$old = t22_draw( CONTACT, 'institution', '' );
			$new = t22_draw( CONTACT, 'institution', 'parts' );
		} finally {
			$GLOBALS['index'][ T22_LONG ]['name'] = 'Examplesponsorforappliedwebengineeringco';
		}
		$out = array();
		foreach ( array( $old, $new ) as $html ) {
			// A long run of the name takes break opportunities on the parts (`breakable()`); read without them.
			$out[] = array( strpos( $html, '<script' ), preg_match( '/&amp;(amp|lt|gt|quot|#0?39);/', $html ), false !== strpos( str_replace( '<wbr>', '', $html ), '&lt;script&gt;alert(1)&lt;/script&gt; &quot;Q&quot; &amp; Co' ) );
		}
		return $out;
	},
	array( array( false, 0, true ), array( false, 0, true ) )
);

// The layout harness: every state the view draws on the parts, its long description's fold open.
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	foreach ( $t22_new as $t22_name => $t22_html ) {
		if ( 0 === strpos( $t22_name, 'actions, ' ) || '' === (string) $t22_html ) {
			continue;
		}
		wpcpm_layout_keep( 'Events: ' . $t22_name, str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner">', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open>', (string) $t22_html ) );
	}
}

ck(
	'one helper per rule: both drawings ask `press_of()` who gets which press, `press_fields()` what a press posts, `press_words()` its words, `call_link()` the link, `event_facts()` the facts, `description_shown()` the fold, `zone_sentence()` the line over the list',
	static function () use ( $list_file ) {
		$src = (string) file_get_contents( $list_file );
		$out = array();
		foreach ( array( 'press_of', 'press_fields', 'press_words', 'call_link', 'event_facts', 'description_shown', 'zone_sentence' ) as $helper ) {
			$out[ $helper ] = substr_count( $src, 'self::' . $helper . '(' ) >= 2;
		}
		return $out;
	},
	array( 'press_of' => true, 'press_fields' => true, 'press_words' => true, 'call_link' => true, 'event_facts' => true, 'description_shown' => true, 'zone_sentence' => true )
);

// Fix round 1, points 1 to 5.
ck(
	'fix round 1: no `<wbr>` anywhere the parts draw the list (`breakable()` is for a data table\'s cell), and no heading in either of its words',
	static function () use ( $t22_new ) {
		$all = implode( '', array_map( 'strval', $t22_new ) );
		return array( substr_count( $all, '<wbr>' ), preg_match( '/<h[1-6]/', $all ), substr_count( $all, 'Events from our sponsors' ) );
	},
	array( 0, 0, 0 )
);
ck(
	'fix round 1: the class says what is true of `render_actions()` now, and its `parts()` asks for the card shell too',
	static function () use ( $list_file ) {
		$src = (string) file_get_contents( $list_file );
		preg_match( '/private static function parts\(\) \{(.*?)\n\t\}/s', $src, $parts );
		return array( strpos( $src, 'until its own release' ), strpos( $src, 'until their own release' ), false !== strpos( $parts[1] ?? '', "class_exists( 'WPCPM_Dashboard_Card', false )" ) );
	},
	array( false, false, true )
);
// `format_range()`'s words for the real calls, as the class at 68d6c986 prints them: the same day, past midnight on the
// reader's clock, and an hour-long call, on three clocks.
ck(
	'fix round 1: `WPCPM_Mentor_Calls::format_range()` prints the base\'s words, and the list reads its two moments from the one place that formats them (`range_moments()`), never the formats itself',
	static function () use ( $list_file ) {
		$out = array();
		foreach ( array( 'Europe/Warsaw', 'America/New_York', 'Asia/Tokyo' ) as $zone ) {
			foreach ( array( array( '2027-03-10 10:00', '2027-03-10 11:00' ), array( '2027-03-20 22:30', '2027-03-21 00:30' ), array( '2026-11-10 19:00', '2026-11-10 20:30' ) ) as $pair ) {
				$out[] = WPCPM_Mentor_Calls::format_range( utc( $pair[0] ), utc( $pair[1] ), new DateTimeZone( $zone ) );
			}
		}
		$src = (string) file_get_contents( $list_file );
		return array( md5( implode( '|', $out ) ), substr_count( $src, 'WPCPM_Mentor_Calls::range_moments(' ), strpos( $src, "get_option( 'date_format' )" ), strpos( $src, "get_option( 'time_format' )" ) );
	},
	array( T22_RANGE_BASE, 1, false, false )
);

// The part extended (rule 5): row() takes `more`. Without it the row prints the base's bytes, pinned by digests taken at
// 68d6c986 with the Administrator Dashboard's out-of-force agreement row, its sponsor-notes row and a row with every
// other argument; with it, the markup under the note and over the form, the row marked.
ck(
	'row() without `more` prints the base\'s bytes; with it, the caller\'s markup under the note, over the form, the row marked `--more`',
	static function () {
		$sets = array(
			array( 'id' => 'wpcpm-sponsor-agreement-41', 'name' => 'Partner <b>Example</b> & Co', 'tag' => array( 'words' => 'Revoked', 'tone' => '' ), 'facts' => array( 'Revoked on <span>x</span>', array( 'html' => '<span class="wpcpm-ui-whole">May 1, 2026</span>' ) ), 'actions' => array( '<form method="post"><button>Reinstate</button></form>' ) ),
			array( 'name' => 'Champion Example', 'href' => 'https://example.test/sponsor/', 'facts' => array( '2 days ago' ), 'note' => "Line one & <i>two</i>\nline three" ),
			array( 'name' => 'Ana', 'note' => 'You are on it.', 'form' => '<form></form>', 'form_id' => 'f-1', 'media' => '<img alt="">', 'level' => 'h4' ),
		);
		$out  = array_map( static function ( $args ) { return md5( WPCPM_UI::row( $args ) ); }, $sets );
		$out[] = WPCPM_UI::row( $sets[2] ) === WPCPM_UI::row( array_merge( $sets[2], array( 'more' => '' ) ) );
		$out[] = WPCPM_UI::row( array( 'name' => 'E', 'note' => 'N', 'more' => '<p class="wpcpm-ui-note">D</p>', 'form' => '<form></form>', 'form_id' => 'f' ) );
		return $out;
	},
	array(
		'd1dc9381b56ae9c5e2aedbc2cb41f5bd',
		'c817cecd347dfb90cf5ec046d6ba51b2',
		'e2370619cd1a4110c9671409be20d0b2',
		true,
		'<li class="wpcpm-ui-list__row wpcpm-ui-list__row--note wpcpm-ui-list__row--more"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">E</span><span class="wpcpm-ui-list__facts"></span></div><p class="wpcpm-ui-list__note">N</p><div class="wpcpm-ui-list__more"><p class="wpcpm-ui-note">D</p></div><div class="wpcpm-ui-list__form" id="f"><form></form></div></li>',
	)
);

$GLOBALS['sent'] = $t22_sent;
if ( null !== $t22_recorded ) {
	$GLOBALS['wpcpm_recorded'] = $t22_recorded;
}
set_now( $t22_now );

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
// into the template registry holds it (bin/stubs/mail-recording.php), each ending with the footer as
// it reads since 1.123.24, the site's address under its first line.
foreach ( wpcpm_recording_checks( 'sponsor-event-mails-list.json', '6b823d154b980a2d8a843b5f4b55c318440e1730a67d31771d0a6b74b16995c4', array_keys( array_filter( WPCPM_Mail_Catalog::emails(), static function ( $e ) { return WPCPM_Mail_Catalog::MODULE_EVENTS === $e['module']; } ) ), array(), "-- \nWordPress Credits, WordPress Education\nhttps://site.example/\nThis email comes from the WordPress Credits program." ) as $recorded_check ) {
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
