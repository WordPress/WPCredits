<?php
/**
 * The Sponsors module: the wp-admin screen, provisioning and members.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - Provisioning creates an account from a sponsor's contact address, or attaches an existing
 *   one at that address; it never provisions two accounts for the same sponsor and never
 *   touches an administrator's account, because a manager already reaches every sponsor.
 * - The provision nonce is keyed to the record (`wpcpm_sponsor_provision_<record>`), so a
 *   token for one sponsor is not a token for another.
 * - Every handler checks the capability, then the nonce, and only then asks
 *   `WPCPM_Sponsor_Policy::decide()` or changes anything. That order is proved where the handlers
 *   are pressed: bin/test-handlers.php presses every one of the module's handlers by name, by
 *   somebody without the capability and by a manager whose nonce fails, and
 *   bin/test-sponsors-accounts.php presses the Accounts tab's controls with a forged nonce and
 *   finds nothing done before the refusal.
 * - The welcome is queued through `WPCPM_Mail::queue_invites()` and never sent from here:
 *   `wp_mail()` is stubbed to record a call precisely so that promise has a check behind it.
 * - `Dashboard account` is written to Airtable, and to the index at once, the moment an
 *   account is created or attached, and cleared the moment a sponsor's last account is
 *   detached: nobody waits for the next sync run to see it.
 * - The screen is drawn in six tabs by job, the queue first, which is the tab the screen's own
 *   address opens: each tab draws its own cards and no other tab's, and reads only what it draws
 *   (counted by the stand-ins for options, user queries and post queries). The Sponsors card offers
 *   no Create account: a sponsor's account is created on the Accounts tab's No account view. Its
 *   last column is each sponsor's Manage accounts.
 * - The Accounts tab draws the accounts locked for the day, the invitations card and the sponsor
 *   accounts list, and prints no address. The list itself, its views, its Create account, its row
 *   actions and its invitations are bin/test-sponsors-accounts.php's. With a sponsor in its address
 *   it draws that sponsor's accounts in their place, reading the index and that sponsor's accounts
 *   alone: the sponsor's name, the way back to the list as it stood, each account by name and
 *   address with Remove, then, for an Approved sponsor, the Attach account form and the posting
 *   switch, and for one that is not, one sentence saying neither is offered; for a value the index
 *   does not hold, that no sponsor has it and where the sync is run, never the value itself.
 * - Every press comes back to the tab it was made on, and its outcome prints there once, above
 *   the bar; one made on a sponsor's accounts comes back to them, and one that names no sponsor or
 *   another tab does not; the outcome a failed press leaves is worded by that tab. The warning
 *   while Airtable is not connected prints once, above the bar, on every tab and on a sponsor's
 *   accounts, from the screen's one copy of its words.
 * - The queue tab reads and the Administrator Dashboard decides: the sponsor applications, the
 *   sponsor posts and the signed agreements waiting, each in a card of its own in the dashboard's
 *   order, each counting everything waiting and listing no more than the dashboard's card lists,
 *   and each item linking to its own item there; while that page is missing the tab says so once
 *   and nothing links. An opened application draws only the decisions the dashboard cannot reach.
 *   The Agreements tab is every Approved sponsor's standing state; Reinstate is the dashboard's
 *   while its list of agreements out of force holds the document, and this tab's past that list.
 * - Every form the module draws on a tab carries the once attribute (`data-wpcpm-once`). The
 *   invitations card's are the mail layer's, which draws them without it on every audience's screen.
 * - `mark_dashboard_account()` refuses a malformed record before any request leaves the site,
 *   and hands back the client's own error when Airtable refuses the write.
 * - `boot()`'s hooks, the Accounts tab's two invitation handlers and the screen's own load hook,
 *   are bin/test-sponsors-accounts.php's, which boots the module and reads them.
 * - Every reference to `WPCPM_Sponsors_Dashboard`, the Sponsor Dashboard's class, is guarded, so
 *   the screen draws with or without the front end: this suite never defines that class.
 *
 * Run from the plugin root:  php bin/test-sponsors-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// Cron, recorded rather than run: WPCPM_Sponsors_Sync::schedule() reads wp_next_scheduled() and
// WPCPM_Sponsors::uninstall() clears two hooks, though neither boot() nor uninstall() is ever
// called here - kept for the same reason the copied suite keeps it, at no cost to this one.
function wp_next_scheduled( $hook ) {
	return $GLOBALS['cron'][ $hook ] ?? false;
}
function wp_schedule_event( $when, $recurrence, $hook ) {
	$GLOBALS['cron'][ $hook ] = (int) $when;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	return 1;
}
// The sync's start puts its first slice on the clock: recorded the same way, for the press of
// Sync sponsors now that the screen's checks make through its handler.
function wp_schedule_single_event( $when, $hook, $args = array() ) {
	$GLOBALS['cron'][ $hook ] = (int) $when;
	return true;
}

$GLOBALS['opts']          = array();
$GLOBALS['umeta']         = array();
$GLOBALS['users']         = array();
$GLOBALS['nonce_checked'] = array();
$GLOBALS['inserted']      = array();
$GLOBALS['posts']         = array();
$GLOBALS['pmeta']         = array();
$GLOBALS['next_post']     = 900;

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}

/**
 * Enough of `WP_User` for the real `WPCPM_Sponsor_Members` to run against: `add_role()` and
 * `remove_role()` that mutate the role list without demoting anyone else, and `set_role()` for
 * the account that ends up with no role at all once a sponsor's is removed.
 *
 * The constructor takes the role list before the name and address, matching the fixture below
 * rather than the institutions screen suite's own order: that suite never gives `WP_User` a
 * role at construction time, and this one has to, since accounts arrive from Airtable already
 * carrying the sponsor role.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, array $roles = array(), $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->roles        = $roles;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $role ) {
		if ( ! in_array( $role, $this->roles, true ) ) {
			$this->roles[] = $role;
		}
	}
	public function remove_role( $role ) {
		$this->roles = array_values( array_diff( $this->roles, array( $role ) ) );
	}
	public function set_role( $role ) {
		$this->roles = '' === $role ? array() : array( $role );
	}
}

/**
 * Enough of `WP_Post` for the post store below: the offers module reads and writes these five
 * properties alone, so nothing else is stood in for it.
 */
class WP_Post {
	public $ID = 0, $post_type = '', $post_title = '', $post_status = 'private', $post_author = 0;
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}

/**
 * The two outcomes `post()` tells apart. The institutions screen suite this file's other stubs
 * are copied from throws a plain `Exception` for both `wp_die()` and `wp_safe_redirect()` and
 * tells them apart by a string prefix on the message; that will not do here, because `post()`
 * needs two separate `catch` clauses to hand the caller a `die` outcome or a `redirect` one
 * without parsing the message first. Neither exception carries anything beyond its message:
 * `WPCPM_Test_Die` folds the status code into it (`wp_die( $message, 403 )` becomes
 * `"$message [403]"`) since that is the only way a check on the message text can see the code.
 */
class WPCPM_Test_Die extends Exception {}
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
// A translation is the English unless $GLOBALS['l10n'] holds one, so a check can read where a string
// is translated: the tab bar's labels and its name, as the translation tools collect them.
function __( $s, $d = null ) { return isset( $GLOBALS['l10n'][ $s ] ) ? $GLOBALS['l10n'][ $s ] : $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses( $s, $a = array() ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( __( $s, $d ) ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( __( $s, $d ) ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_user( $u, $strict = false ) { return preg_replace( '/[^a-z0-9 _.\-@]/i', '', (string) $u ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) {}
function add_filter() {}
function register_post_type( $type, $args ) {}
// Every read counted by its option, so a check can say which tab asked for the sponsors index and
// the sync's progress (render_screen() starts the count afresh for each draw).
function get_option( $k, $d = false ) {
	$GLOBALS['option_reads'][ $k ] = ( isset( $GLOBALS['option_reads'][ $k ] ) ? (int) $GLOBALS['option_reads'][ $k ] : 0 ) + 1;
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
// The menu bubble's cache (1.98.1), in the shape of bin/test-form-stash.php's transient store
// rather than a new one.
$GLOBALS['transients'] = array();
function set_transient( $k, $v, $ttl ) { $GLOBALS['transients'][ $k ] = array( 'v' => $v, 'ttl' => $ttl ); return true; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ]['v'] : false; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
/** The pool's lock and WPCPM_Secret's key both need the test-and-set add_option() makes: the
 * write only happens when the row does not exist yet, which update_option() alone cannot tell. */
function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
/** delete_metadata( 'user', 0, $key, '', true ) is how uninstall() clears a meta key across
 * every account; only that shape is exercised here, so only it is stood in for. */
function delete_metadata( $type, $object_id, $key, $value = '', $delete_all = false ) {
	$GLOBALS['deleted_meta'][] = $key;
	if ( $delete_all ) {
		foreach ( array_keys( $GLOBALS['umeta'] ) as $id ) {
			unset( $GLOBALS['umeta'][ $id ][ $key ] );
		}
	} else {
		unset( $GLOBALS['umeta'][ (int) $object_id ][ $key ] );
	}
	return true;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'login' === $field && $user->user_login === (string) $value ) { return $user; }
	}
	return false;
}
/** `get_users()` by meta key and value, and everyone for a call that names neither. */
/**
 * Enough of `$wpdb` for `WPCPM_Sponsors_Index::delete_all()`'s one raw query: a LIKE-prefix
 * DELETE against the options table it keeps in `$GLOBALS['opts']`, and for
 * `WPCPM_Sponsor_Approval::delete_all()`'s LIKE-prefix SELECT of the lock rows, which
 * `uninstall()` reaches for real once this suite has loaded that class.
 */
class WPCPM_Test_DB {
	public $options = 'wp_options';
	public function prepare( $sql, ...$args ) { return vsprintf( str_replace( '%s', "'%s'", $sql ), $args ); }
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function get_col( $sql ) {
		$out = array();

		if ( preg_match( "/LIKE '(.*)%'\$/", $sql, $m ) ) {
			$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, $prefix ) ) { $out[] = $name; }
			}
		}

		return $out;
	}
	public function query( $sql ) {
		if ( preg_match( "/LIKE '(.*)%'\$/", $sql, $m ) ) {
			$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, $prefix ) ) { unset( $GLOBALS['opts'][ $name ] ); }
			}
		}
		return true;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_DB();
function get_users( $args = array() ) {
	// Counted by the meta key asked for, so a check can say which tab read the live accounts.
	$asked = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';
	$GLOBALS['user_queries'][ $asked ] = ( isset( $GLOBALS['user_queries'][ $asked ] ) ? (int) $GLOBALS['user_queries'][ $asked ] : 0 ) + 1;
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( ! isset( $args['meta_key'] ) ) { $out[] = $user; continue; }
		$value = $GLOBALS['umeta'][ (int) $id ][ $args['meta_key'] ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $args['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function username_exists( $login ) { $u = get_user_by( 'login', $login ); return $u ? $u->ID : false; }
// How a meta query is read, the one reading every stand-in user query shares; it notes in
// `$GLOBALS['unmodeled']` anything it is asked that it does not model.
$GLOBALS['unmodeled'] = array();
require_once __DIR__ . '/stubs/meta-matcher.php';
/**
 * The Accounts tab's list of sponsor accounts and its views' counts: the role, the view's meta query
 * read by the matcher above, the IDs a search hands in (`include`) and a contains search over the
 * columns it names, ordered by name and then by ID, the order a list nobody sorted is in, which is
 * the one this suite draws; a page of them, or IDs when `fields` asks; and the total. Each call is
 * kept, so a check can say which tab read the list. The list itself, paged, searched and sorted, is
 * bin/test-sponsors-accounts.php's.
 */
class WP_User_Query {
	private $results = array();
	private $total   = 0;
	public function __construct( $args = array() ) {
		$GLOBALS['list_queries'][] = $args;
		$include                   = ! empty( $args['include'] ) ? array_map( 'intval', (array) $args['include'] ) : null;
		$found                     = array();
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( ! empty( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) { continue; }
			if ( null !== $include && ! in_array( (int) $id, $include, true ) ) { continue; }
			if ( ! wpcpm_stub_meta_matches( (int) $id, isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() ) ) { continue; }
			if ( ! empty( $args['search'] ) ) {
				$term = trim( (string) $args['search'], '*' );
				$hit  = false;
				foreach ( isset( $args['search_columns'] ) ? (array) $args['search_columns'] : array() as $column ) {
					$hit = $hit || false !== stripos( (string) $user->$column, $term );
				}
				if ( ! $hit ) { continue; }
			}
			$found[] = $user;
		}
		usort( $found, static function ( $a, $b ) { $order = strcasecmp( $a->display_name, $b->display_name ); return 0 !== $order ? $order : $a->ID - $b->ID; } );
		$this->total = count( $found );
		if ( isset( $args['number'] ) && (int) $args['number'] > 0 ) {
			$found = array_slice( $found, isset( $args['offset'] ) ? (int) $args['offset'] : 0, (int) $args['number'] );
		}
		if ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) {
			$found = array_map( static function ( $user ) { return (string) $user->ID; }, $found );
		}
		$this->results = array_values( $found );
	}
	public function get_results() { return $this->results; }
	public function get_total() { return $this->total; }
}
// What else the Accounts tab's list reaches of WordPress: its arguments, its rows-per-page choice,
// the names it orders, its view's label held until the count is known, the empty row, whether an
// account holds a stamp, and the account's editor.
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function get_user_option( $option, $user = 0 ) { return $GLOBALS['umeta'][ $user ? (int) $user : $GLOBALS['uid'] ][ $option ] ?? false; }
function remove_accents( $text, $locale = '' ) { return (string) $text; }
function esc_html_e( $s, $d = null ) { echo esc_html__( $s, $d ); }
function _n_noop( $singular, $plural, $domain = null ) { return array( 0 => $singular, 1 => $plural, 'singular' => $singular, 'plural' => $plural, 'context' => null, 'domain' => $domain ); }
function translate_nooped_plural( $nooped, $count, $domain = 'default' ) { return __( _n( $nooped['singular'], $nooped['plural'], $count ) ); }
function metadata_exists( $type, $id, $key ) { return 'user' === $type && isset( $GLOBALS['umeta'][ (int) $id ] ) && array_key_exists( $key, $GLOBALS['umeta'][ (int) $id ] ); }
function get_edit_user_link( $id ) { return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id; }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( str_repeat( md5( (string) mt_rand() ), 2 ), 0, (int) $l ); }
function wp_mail( $to, $subject, $message = '', $headers = '', $attachments = array() ) {
	// Never expected to run: provisioning and attaching both queue through WPCPM_Mail::queue_invites()
	// rather than calling this, and a check later asserts $GLOBALS['mailed'] was never touched.
	$GLOBALS['mailed'][] = array( $to, $subject );
	return true;
}
// The post store: enough of posts, meta and get_posts() for the offers to live in.
function wp_insert_post( array $args, $wp_error = false ) {
	$id = $GLOBALS['next_post']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $args['post_type'] ?? 'post', 'post_title' => $args['post_title'] ?? '', 'post_status' => $args['post_status'] ?? 'publish', 'post_author' => $args['post_author'] ?? 0 ) );
	return $id;
}
function wp_update_post( array $args ) { $id = (int) $args['ID']; if ( isset( $GLOBALS['posts'][ $id ] ) && isset( $args['post_title'] ) ) { $GLOBALS['posts'][ $id ]->post_title = $args['post_title']; } return $id; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function wp_delete_attachment( $id, $force = false ) { $GLOBALS['deleted_attachments'][] = (int) $id; return true; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
// Post meta here keeps what it is handed, so the slash is the identity (bin/test-sponsor-offers.php
// holds the offers' writes to core's, whose meta unslashes).
function wp_slash( $v ) { return $v; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function get_posts( array $args ) {
	$GLOBALS['queries'] = ( isset( $GLOBALS['queries'] ) ? (int) $GLOBALS['queries'] : 0 ) + 1;
	// And by the post type asked for, so a check can say which tab read the applications.
	$type = isset( $args['post_type'] ) ? implode( ',', (array) $args['post_type'] ) : '';
	$GLOBALS['post_queries'][ $type ] = ( isset( $GLOBALS['post_queries'][ $type ] ) ? (int) $GLOBALS['post_queries'][ $type ] : 0 ) + 1;
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && $post->post_status !== $args['post_status'] ) { continue; }
		$ok = true;
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = array_key_exists( $clause['key'], $GLOBALS['pmeta'][ $id ] ?? array() );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { if ( ! $have ) { $ok = false; } continue; }
			// The application queue asks for several states at once.
			if ( isset( $clause['compare'] ) && 'IN' === $clause['compare'] ) { if ( ! $have || ! in_array( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], array_map( 'strval', (array) $clause['value'] ), true ) ) { $ok = false; } continue; }
			if ( ! $have || (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ] !== (string) $clause['value'] ) { $ok = false; }
		}
		if ( $ok ) { $out[] = $post; }
	}
	// decided_posts() (1.98.1) orders by a meta value instead of the post ID; this is the one
	// other shape this class asks for, so it is the one other shape this stub answers.
	if ( isset( $args['orderby'] ) && 'meta_value_num' === $args['orderby'] && isset( $args['meta_key'] ) ) {
		$meta_key = $args['meta_key'];
		$desc     = isset( $args['order'] ) && 'DESC' === $args['order'];
		usort( $out, static function ( $a, $b ) use ( $meta_key, $desc ) {
			$by_meta = (int) get_post_meta( $a->ID, $meta_key, true ) - (int) get_post_meta( $b->ID, $meta_key, true );
			return $desc ? -$by_meta : $by_meta;
		} );
	} else {
		usort( $out, static function ( $a, $b ) { return $a->ID - $b->ID; } );
	}
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $args['numberposts'] );
	}
	// The IDs alone, as core answers `'fields' => 'ids'`: an opened application reads its place
	// among the open ones that way.
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_map( static function ( $post ) { return $post->ID; }, $out );
	}
	return $out;
}
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['nonce_checked'][] = $action;
	if ( ! $GLOBALS['nonce_ok'] ) {
		wp_die( 'The link you followed has expired.' );
	}
	return true;
}
function wp_die( $message = '', $code = 0 ) {
	throw new WPCPM_Test_Die( (string) $message . ( $code ? ' [' . (int) $code . ']' : '' ) );
}
function wp_safe_redirect( $location, $status = 302 ) {
	throw new WPCPM_Test_Redirect( (string) $location );
}
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Both shapes core accepts: a map of arguments and an address, which the tab bar and the queue's
// links use, and one key, its value and an address, which the way back to a tab uses (`tab_url()`).
// Nothing to add leaves the address as it was, as core's does: the way back to the accounts list
// adds where the list stood, which is nothing when it stood at its start.
function add_query_arg( $args, $url = '', $third = '' ) {
	if ( ! is_array( $args ) ) {
		$args = array( (string) $args => $url );
		$url  = $third;
	}
	if ( empty( $args ) ) {
		return $url;
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function wp_nonce_url( $url, $a ) { return $url . '&_wpnonce=' . rawurlencode( $a ); }
// The nonce field as core prints it: the nonce, and beside it, unless the caller asks for none, the
// address of the request that drew the form, which a handler that follows its referer goes back to.
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />';
	if ( $r ) {
		echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) . '" />';
	}
}
function wp_create_nonce( $a = '' ) { return 'nonce'; }
function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true, $other = array() ) {
	$attrs = '';
	foreach ( (array) $other as $key => $value ) { $attrs .= ' ' . $key . '="' . esc_attr( $value ) . '"'; }
	printf( '<button type="submit" class="button button-%s" name="%s"%s>%s</button>', $type, $name, $attrs, esc_html( $text ) );
}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function size_format( $b, $d = 0 ) { return (int) $b . ' B'; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_date( $format, $ts = null, $zone = null ) { return gmdate( $format, (int) $ts ); }
function get_post_time( $format = 'U', $gmt = false, $post = null ) { return 1757000000; }
function wp_get_attachment_image( $id, $size = 'medium', $icon = false, $attr = array() ) { return '<img class="wpcpm-test-logo" data-id="' . (int) $id . '" src="https://example.test/uploads/' . (int) $id . '.png" alt="" />'; }
function esc_textarea( $s ) { return esc_html( $s ); }

// The shape a sponsor's record ID has, read from the Airtable class itself, which the accounts list
// matches a ticked sponsor by.
preg_match( "/const RECORD_ID_PATTERN = '([^']+)';/", (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-airtable.php' ), $wpcpm_test_pattern );
define( 'WPCPM_TEST_RECORD_PATTERN', isset( $wpcpm_test_pattern[1] ) ? $wpcpm_test_pattern[1] : '/^no pattern read$/' );
class WPCPM_Airtable {
	const RECORD_ID_PATTERN = WPCPM_TEST_RECORD_PATTERN;
	public function update_records( $table, array $records ) { $GLOBALS['patched'][] = array( $table, $records ); return isset( $GLOBALS['airtable_fail'] ) ? new WP_Error( 'x', 'Airtable said no' ) : array( $records[0]['id'] => true ); }
}
class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
	public static function insert_user( array $data ) { $id = 100 + count( $GLOBALS['users'] ); $u = new WP_User( $id, array( $data['role'] ), $data['display_name'], $data['user_email'] ); $u->user_login = $data['user_login']; $GLOBALS['users'][ $id ] = $u; $GLOBALS['inserted'][] = $data; return $id; }
}
// The stamps an invitation leaves, named once for every stand-in mail class.
require_once __DIR__ . '/stubs/stamps.php';
/**
 * The mail layer, as far as the screen reaches it: the queue provisioning puts a welcome in, the
 * masked address, and the invitations as the Accounts tab's frame reaches them: the stamps the
 * list's views read, the words a row's invitation leaves, who was never sent one (the accounts
 * holding the role with no stamp), and the card, drawn as the real one draws its button, a form
 * posting the action and the fields the module hands it, without the once attribute. The
 * invitations themselves and the card's own words are bin/test-sponsors-accounts.php's, against the
 * real class.
 */
class WPCPM_Mail {
	const STAMPS = WPCPM_STUB_STAMPS;
	public static function queue_invites( array $ids ) { $GLOBALS['queued'] = array_merge( isset( $GLOBALS['queued'] ) ? $GLOBALS['queued'] : array(), $ids ); return count( $ids ); }
	public static function mask_address( $a ) { return substr( $a, 0, 1 ) . '***' . strstr( $a, '@' ); }
	public static function invite_notices() {
		return array(
			'invited'         => array( 'success', 'Invitation email sent.' ),
			'invite-too-soon' => array( 'warning', 'Nothing was sent: too soon.' ),
		);
	}
	public static function never_invited( $role, $meta ) {
		$GLOBALS['never_invited_asked'][] = array( $role, $meta );
		$never                            = array();
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( in_array( $role, $user->roles, true ) && empty( array_intersect_key( isset( $GLOBALS['umeta'][ $id ] ) ? $GLOBALS['umeta'][ $id ] : array(), array_flip( self::STAMPS ) ) ) ) {
				$never[] = (int) $id;
			}
		}
		return $never;
	}
	public static function render_invite_card( array $args ) {
		echo '<div class="wpcpm-card wpcpm-invites"><h2>Invitations</h2>';
		if ( ! empty( $args['pending'] ) ) {
			echo '<form method="post" action="https://example.test/wp-admin/admin-post.php">';
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );
			foreach ( (array) $args['hidden'] as $name => $value ) {
				printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $value ) );
			}
			printf( '<button type="submit">%s</button></form>', esc_html( sprintf( translate_nooped_plural( $args['button'], count( $args['pending'] ) ), count( $args['pending'] ) ) ) );
		}
		echo '</div>';
	}
}
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
	public static function sponsorship() { return array(); }
}
class WPCPM_Students_Sync {
	const META_RECORD_ID = 'wpcpm_student_record_id';
	public static function get_program( $user_id ) { return array(); }
}
class WPCPM_Institutions { public static function notify_managers( $context, $build, $key = 'agreement_notify' ) { $GLOBALS['sent'][] = array( 'managers', $key, $context, call_user_func( $build, null ) ); return 1; } }
class WPCPM_Institution_Members { const META_RECORD_ID = 'wpcpm_institution_record_id'; const META_ACTIVE = 'wpcpm_institution_active'; public static function institution_of( $user = null ) { return ''; } }
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache';
	public static function record_sponsor( array $e ) { $GLOBALS['audit'][] = $e; return count( $GLOBALS['audit'] ); }
	public static function sponsor_entries( $kind = '', $limit = 50 ) { $out = array(); foreach ( array_reverse( $GLOBALS['audit'] ) as $i => $e ) { if ( '' !== $kind && $e['kind'] !== $kind ) { continue; } $out[] = array_merge( array( 'id' => $i, 'actor' => 0, 'time' => 1757000000 + $i, 'message' => '', 'data' => array() ), $e ); } return array_slice( $out, 0, $limit ); }
}
class WPCPM_Ceiling { public static function init() {} public static function claim( $k, $l, $w, $a = 1 ) { return true; } public static function count( $k, $w ) { return isset( $GLOBALS['ceiling_counts'][ $k ] ) ? (int) $GLOBALS['ceiling_counts'][ $k ] : 0; } public static function key( ...$p ) { return implode( ':', $p ); } }
/**
 * The flash channels as the real class keeps them: what a press sets waits for the next page, and
 * what a page takes is remembered for the rest of that page, so a second printer on the same channel
 * reads the same value again and a notice printed twice is seen. The memory is forgotten at the start
 * of each press and each page draw (`post()`, `render_screen()`), as a new request starts with
 * nothing taken.
 */
class WPCPM_Flash {
	/** @var array<string, mixed> Channel => what this request took from it. */
	public static $taken = array();
	public static function set( $k, $v ) { $GLOBALS['flash'][ $k ] = $v; }
	public static function take( $k ) {
		if ( array_key_exists( $k, self::$taken ) ) {
			return self::$taken[ $k ];
		}
		$v = isset( $GLOBALS['flash'][ $k ] ) ? $GLOBALS['flash'][ $k ] : '';
		unset( $GLOBALS['flash'][ $k ] );
		self::$taken[ $k ] = $v;
		return $v;
	}
	/** A new request: nothing taken yet. */
	public static function new_request() { self::$taken = array(); }
}
class WPCPM_Request {
	public static function posted_text( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? trim( (string) $GLOBALS['post'][ $n ] ) : $f; }
	public static function posted_key( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $GLOBALS['post'][ $n ] ) ) : $f; }
	public static function posted_id( $n ) { return isset( $GLOBALS['post'][ $n ] ) ? (int) $GLOBALS['post'][ $n ] : 0; }
	public static function text( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? trim( (string) $GLOBALS['get'][ $n ] ) : $f; }
	public static function key( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? (string) $GLOBALS['get'][ $n ] : $f; }
	public static function id( $n ) { return isset( $GLOBALS['get'][ $n ] ) ? (int) $GLOBALS['get'][ $n ] : 0; }
}
// Connected unless a check says otherwise, as the warning's own check does.
class WPCPM_Settings { public static function get() { return $GLOBALS['settings']; } public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } public static function is_connected() { return empty( $GLOBALS['not_connected'] ); } }
class WPCPM_Mentors { public static function format_duration( $s ) { return $s . 's'; } }
// The real class: it prints the way from this screen into the Administrator Dashboard, and maps
// every press's way back, which names no dashboard here and so comes back to this screen.
require_once __DIR__ . '/../includes/class-wpcpm-return.php';
// The Administrator Dashboard's page, which every item on the queue tab links into.
require_once __DIR__ . '/stubs/administrators-dashboard.php';
if ( ! class_exists( 'WPCPM_Administrators_Cards' ) ) {
	/**
	 * The Administrator Dashboard's cards, for the one number this screen reads from them: how many
	 * items each of the dashboard's sponsor cards lists, which is where the queue tab's lists stop so
	 * that no row links to an item the dashboard does not draw. A number of its own here, apart from
	 * the applications' `QUEUE_MAX` and the interests card's `INTERESTS_SHOWN`, which equal it on a
	 * real site, so a check can tell which of them a list stops at.
	 */
	class WPCPM_Administrators_Cards {
		const LIMIT = 40;
	}
}
/** The real class is not loaded here (nothing else in this suite needs it), so a stub
 * stands in: ACTION_FLAGS names the admin-post action the posting switch on a sponsor's accounts
 * posts a nonce for, and posting_enabled() reads the same $GLOBALS['posting_off'] the checks below
 * set and clear.
 * pending_all() answers the queue tab's posts card from $GLOBALS['pending_posts'], in the facts'
 * own shape, and counts each read, so a check can say which tab asked. As the real read does, it
 * applies its limit first and leaves out a row afterward (one marked `dropped` here, where the real
 * class drops a post whose sponsor stamp is not a record ID), so a read can return fewer than asked.
 * apply_caps(), drop_caps() and delete_all() are never asserted here; they exist only because
 * class_exists( 'WPCPM_Sponsor_Posts' ) is already true the moment this stub is defined, and the
 * real WPCPM_Sponsor_Members::attach()/detach() and WPCPM_Sponsors::uninstall() (both loaded
 * for real below) call them behind that same guard - a bare stub without them would fatal the
 * first time this suite attaches or detaches an account, or runs uninstall(). */
class WPCPM_Sponsor_Posts {
	const ACTION_FLAGS = 'wpcpm_sponsor_flags';
	public static function posting_enabled( $record ) { return empty( $GLOBALS['posting_off'][ $record ] ); }
	public static function pending_all( $limit = 50 ) {
		$GLOBALS['stand_in_reads']['posts'] = ( isset( $GLOBALS['stand_in_reads']['posts'] ) ? (int) $GLOBALS['stand_in_reads']['posts'] : 0 ) + 1;
		$rows = array_slice( isset( $GLOBALS['pending_posts'] ) ? $GLOBALS['pending_posts'] : array(), 0, max( 1, (int) $limit ) );
		return array_values( array_filter( $rows, static function ( $row ) { return empty( $row['dropped'] ); } ) );
	}
	public static function apply_caps( $user_id, $record ) {}
	public static function drop_caps( $user_id ) {}
	public static function delete_all() {}
	public static function uninstall_accounts() {}
}
require_once __DIR__ . '/stubs/caps.php';
// The readers every screen suite shares: a drawn form's fields by its action, an address's
// arguments, the notices a press left.
require_once __DIR__ . '/stubs/screen-helpers.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The bar every audience's screen prints above its tab, drawn here by the real printer.
require_once __DIR__ . '/../includes/class-wpcpm-screen-tabs.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-module.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sync-module.php';
// Before the module, which uses it: PHP declares a class only once the traits it uses are declared.
require_once __DIR__ . '/../includes/modules/trait-wpcpm-accounts-screen.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-sync.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php';

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, then the
 * accounts base and the Sponsors table, so the Accounts tab draws its own list, which
 * bin/test-sponsors-accounts.php reads row by row. The table's file is required once it exists, so
 * a copy of the plugin without it fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once __DIR__ . '/../includes/class-wpcpm-accounts-table.php';

	if ( file_exists( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-table.php' ) ) {
		require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-table.php';
	}
}

// ck(), then the fixture: an index of three sponsors written through the real index class.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002'; $C = 'recSPONSOR0000003';
WPCPM_Sponsors_Index::write( array(
	$A => array( 'name' => 'Mango Example ', 'status' => 'Approved', 'product_type' => 'Hosting', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'manager' => 'recTEAM0000000001' ),
	$B => array( 'name' => 'Wexample', 'status' => 'Approved', 'contact_email' => '' ),
	$C => array( 'name' => 'Ember Example', 'status' => 'Paused', 'contact_email' => 'maciej@a8c.com' ),
), time() );
WPCPM_Sponsors_Index::write_team( array( 'recTEAM0000000001' => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => '' ) ), time() );
$GLOBALS['settings'] = array( 'sponsors_table' => 'tblSPONSORS', 'sponsor_on_inactive' => 'keep' );
// A distinct address from every sponsor's contact_email: get_user_by( 'email', ... ) must not
// resolve the acting manager when it looks up a sponsor's contact address.
$GLOBALS['users']    = array( 1 => new WP_User( 1, array( 'administrator' ), 'Manager', 'manager@example.test' ) );
$GLOBALS['manage']   = array( 1 );
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['patched']  = array();
$GLOBALS['queued']   = array();
$GLOBALS['audit']    = array();
$module = new WPCPM_Sponsors();

function post( array $fields, $action ) {
	$GLOBALS['post'] = $fields;
	WPCPM_Flash::new_request();
	try { call_user_func( $action ); } catch ( WPCPM_Test_Redirect $e ) { return array( 'redirect', $e->getMessage(), WPCPM_Flash::take( WPCPM_Sponsors::FLASH ) ); } catch ( WPCPM_Test_Die $e ) { return array( 'die', $e->getMessage() ); }
	return array( 'fell-through' );
}

/** One method's body, for the assertions that read the source. */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );
	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

/**
 * The screen's HTML, captured, for the address's query (`tab`, `wpcpm_sapp_id`); the read counters
 * start afresh, so what they hold afterward is what this one draw asked for (`reads_now()`).
 *
 * @param array $get Query arguments the render should see.
 * @return string
 */
function render_screen( array $get = array() ) {
	WPCPM_Flash::new_request();
	$GLOBALS['get']            = $get;
	$GLOBALS['option_reads']   = array();
	$GLOBALS['user_queries']   = array();
	$GLOBALS['list_queries']   = array();
	$GLOBALS['post_queries']   = array();
	$GLOBALS['stand_in_reads'] = array();
	// The list table reads the request where core's does, beside the plugin's reader above.
	$_GET                   = $get;
	$_REQUEST               = $get;
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( array_merge( array( 'page' => 'wpcpm-sponsors' ), $get ) );
	ob_start();
	( new WPCPM_Sponsors() )->render_admin_page();
	return (string) ob_get_clean();
}

/**
 * One tab of the screen, captured: the screen's address naming the tab, and any other arguments.
 *
 * @param string $tab A tab's slug, or any other value.
 * @param array  $get Other query arguments.
 * @return string
 */
function render_tab( $tab, array $get = array() ) {
	return render_screen( array_merge( array( 'tab' => $tab ), $get ) );
}

/**
 * The heading of every card a render draws, in document order, without its count.
 *
 * @param string $html Rendered screen.
 * @return string[]
 */
function cards_of( $html ) {
	preg_match_all( '#<div class="wpcpm-card[^"]*"[^>]*><h2[^>]*>(.*?)</h2>#s', (string) $html, $m );
	$out = array();
	foreach ( $m[1] as $heading ) {
		$out[] = trim( html_entity_decode( strip_tags( preg_replace( '# <span class="wpcpm-count">[^<]*</span>#', '', $heading ) ), ENT_QUOTES ) );
	}
	return $out;
}

/**
 * The tab bar a render prints: core's newer bar, a navigation region named for a screen reader,
 * holding plain links.
 *
 * @param string $html Rendered screen.
 * @return array|null The name the region gives itself, then each tab: its address, entities
 *                    decoded; its label as printed; whether its class marks it as the tab shown;
 *                    and whether it says so to a screen reader. Null when there is no bar.
 */
function bar_of( $html ) {
	if ( ! preg_match( '#<nav class="nav-tab-wrapper wp-clearfix" aria-label="([^"]*)">(.*?)</nav>#s', (string) $html, $bar ) ) {
		return null;
	}
	preg_match_all( '#<a href="([^"]*)" class="nav-tab( nav-tab-active)?"( aria-current="page")?>([^<]*)</a>#', $bar[2], $links, PREG_SET_ORDER );
	$tabs = array();
	foreach ( $links as $link ) {
		$tabs[] = array( html_entity_decode( $link[1], ENT_QUOTES, 'UTF-8' ), html_entity_decode( $link[4], ENT_QUOTES, 'UTF-8' ), '' !== $link[2], '' !== $link[3] );
	}
	return array( html_entity_decode( $bar[1], ENT_QUOTES, 'UTF-8' ), $tabs );
}

/**
 * The tabs a render's forms name in the field a press comes back by, each with how many forms name it.
 *
 * @param string $html Rendered screen.
 * @return array<string, int>
 */
function tab_fields( $html ) {
	preg_match_all( '#<input type="hidden" name="wpcpm_tab" value="([^"]*)" />#', (string) $html, $m );
	return array_count_values( $m[1] );
}

/**
 * The forms a render posts, each by the admin-post action it sends, and with `$unguarded` only those
 * that lack the once attribute, so a failure names the form.
 *
 * @param string $html      Rendered screen.
 * @param bool   $unguarded Whether to keep only the forms without `data-wpcpm-once`.
 * @return string[]
 */
function post_forms( $html, $unguarded = false ) {
	preg_match_all( '#<form\b([^>]*)>(.*?)</form>#s', (string) $html, $found, PREG_SET_ORDER );
	$out = array();
	foreach ( $found as $form ) {
		// The method in any case, as HTML reads it, so a form written method="POST" is not skipped.
		if ( ! preg_match( '/\bmethod="post"/i', $form[1] ) || ( $unguarded && false !== strpos( $form[1], 'data-wpcpm-once' ) ) ) {
			continue;
		}
		$fields = hidden_fields_of( $form[2] );
		$out[]  = isset( $fields['action'] ) ? $fields['action'] : '(no action)';
	}
	return $out;
}

/**
 * A render without the invitations card, and the card alone. The card is the mail layer's
 * (`WPCPM_Mail::render_invite_card()`), drawn on every audience's accounts screen with forms of its
 * own that carry no once attribute on any of them, so the forms the module draws are read
 * apart from it.
 *
 * @param string $html Rendered screen.
 * @return string
 */
function without_invitations( $html ) {
	return (string) preg_replace( '#<div class="wpcpm-card wpcpm-invites">.*?</div>#s', '', (string) $html );
}
function invitations_of( $html ) {
	return preg_match( '#<div class="wpcpm-card wpcpm-invites">.*?</div>#s', (string) $html, $found ) ? $found[0] : '';
}

/**
 * What the last draw read of the eight things a tab reads only when it draws from them: the sponsors
 * index, the sync's progress, the sync's last read, the accounts (the live ones, or the list of every
 * account holding the Sponsor role), the applications, the sponsor posts waiting, the signed
 * agreements waiting and the agreements out of force.
 *
 * @return array<string, bool>
 */
function reads_now() {
	return array(
		'index'             => ! empty( $GLOBALS['option_reads'][ WPCPM_Sponsors_Index::OPT_NAME ] ),
		'progress'          => ! empty( $GLOBALS['option_reads'][ WPCPM_Sponsors_Sync::OPT_STATE ] ),
		'last read'         => ! empty( $GLOBALS['option_reads'][ WPCPM_Sponsors_Sync::OPT_LAST ] ),
		'accounts'          => ! empty( $GLOBALS['user_queries'][ WPCPM_Sponsor_Members::META_ACTIVE ] ) || ! empty( $GLOBALS['list_queries'] ),
		// The application class is loaded part way down this file; before then no application can be read.
		'applications'      => class_exists( 'WPCPM_Sponsor_Application', false ) && ! empty( $GLOBALS['post_queries'][ WPCPM_Sponsor_Application::POST_TYPE ] ),
		'posts'             => ! empty( $GLOBALS['stand_in_reads']['posts'] ),
		'waiting documents' => ! empty( $GLOBALS['stand_in_reads']['waiting documents'] ),
		'out of force'      => ! empty( $GLOBALS['stand_in_reads']['out of force'] ),
	);
}

/**
 * The cards a render draws that carry an id, in document order: each card's id, its heading without
 * the count, and the count.
 *
 * @param string $html Rendered screen.
 * @return array[]
 */
function counted_cards_of( $html ) {
	preg_match_all( '#<div class="wpcpm-card" id="([^"]*)"><h2>(.*?) <span class="wpcpm-count">([^<]*)</span></h2>#s', (string) $html, $found, PREG_SET_ORDER );
	$out = array();
	foreach ( $found as $card ) {
		$out[] = array( $card[1], html_entity_decode( $card[2], ENT_QUOTES ), $card[3] );
	}
	return $out;
}

/**
 * One card of a render, from its opening tag to the tag before the next card or the end of the wrap.
 *
 * @param string $html Rendered screen.
 * @param string $id   The card's id.
 * @return string
 */
function card_body( $html, $id ) {
	$at = strpos( (string) $html, '<div class="wpcpm-card" id="' . $id . '">' );
	if ( false === $at ) {
		return '';
	}
	$body = substr( (string) $html, $at );
	$next = strpos( $body, '<div class="wpcpm-card', 1 );
	return false === $next ? $body : substr( $body, 0, $next );
}

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

echo "=== The module ===\n";
ck( 'the menu label carries no bubble while nothing needs a manager', $module->menu_label(), 'Sponsors' );
ck( 'the sync module contract', array( WPCPM_Sponsors::ACTION_SYNC, WPCPM_Sponsors::ACTION_CANCEL, WPCPM_Sponsors::ACTION_TICK ), array( 'wpcpm_sponsors_sync', 'wpcpm_sponsors_cancel', 'wpcpm_sponsors_tick' ) );
ck( 'every status the handlers flash has a sentence', array_values( array_diff( array( 'provisioned', 'provision-attached', 'provision-admin', 'provision-inactive', 'provision-no-email', 'provision-refused', 'provision-failed', 'airtable-failed', 'attached', 'attach-no-account', 'attach-refused', 'detached', 'detach-refused', 'refused' ), array_keys( WPCPM_Sponsors::messages() ) ) ), array() );
ck( 'a refused provisioning says the account cannot act for the sponsor, an error, and sends the reader to no card', WPCPM_Sponsors::messages()['provision-refused'], array( 'error', 'That account cannot act for this sponsor.' ) );

echo "\n=== Provisioning ===\n";
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an Approved sponsor with a contact address gets an account', $r[0] === 'redirect' ? $r[2] : $r, 'provisioned' );
ck( 'the nonce is keyed to the record', end( $GLOBALS['nonce_checked'] ), 'wpcpm_sponsor_provision_' . $A );
$new = end( $GLOBALS['users'] );
ck( 'with the sponsor role, the contact\'s name and address', array( $new->roles, $new->display_name, $new->user_email ), array( array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ) );
ck( 'stamped for the sponsor, provisioned', array( WPCPM_Sponsor_Members::sponsor_of( $new ), get_user_meta( $new->ID, WPCPM_Sponsor_Members::META_MEMBERSHIP, true )['how'] ), array( $A, 'provisioned' ) );
ck( 'the welcome is queued, never sent here', array( $GLOBALS['queued'], isset( $GLOBALS['mailed'] ) ? $GLOBALS['mailed'] : array() ), array( array( $new->ID ), array() ) );

echo "\n=== Provisioning seeds the first offer ===\n";
$seeded = WPCPM_Sponsor_Offers::offers_of( $A );
ck( 'provisioning seeded one draft offer from the index, marked primary', array( count( $seeded ), reset( $seeded )['state'], reset( $seeded )['primary'] ), array( 1, 'draft', true ) );

ck( 'the base is told: Dashboard account, true', $GLOBALS['patched'][0], array( 'tblSPONSORS', array( array( 'id' => $A, 'fields' => array( 'Dashboard account' => true ) ) ) ) );
ck( 'and the index row says so at once', WPCPM_Sponsors_Index::row( $A )['dashboard_account'], true );
ck( 'and it is logged', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['sponsor'], end( $GLOBALS['audit'] )['ground'] ), array( 'provisioned', $A, 'manager' ) );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'pressing again makes and attaches nothing new and says the sponsor already has an account', array( $r[2], count( $GLOBALS['inserted'] ), count( WPCPM_Sponsor_Members::members_of( $A ) ) ), array( 'provision-already', 1, 1 ) );
$GLOBALS['users'][7] = new WP_User( 7, array( 'wpcpm_mentor' ), 'Ines', 'maciej@a8c.com' );
// The created account moves to another address, so the contact address now names the mentor
// alone: the stub's get_user_by( 'email' ) must not have two candidates.
$new->user_email = 'former@example.test';
WPCPM_Sponsor_Members::detach( $new->ID, 'removed', 1 );
$GLOBALS['queued'] = array();
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an existing account at that address is attached rather than duplicated', array( $r[2], count( $GLOBALS['inserted'] ) ), array( 'provision-attached', 1 ) );
$r = post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_provision' ) );
ck( 'a sponsor with no contact address cannot be given an account', $r[2], 'provision-no-email' );
$r = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
ck( 'nor can one that is not Approved', $r[2], 'provision-inactive' );
$GLOBALS['users'][7]->roles = array( 'administrator' );
foreach ( WPCPM_Sponsor_Members::members_of( $A ) as $m ) { WPCPM_Sponsor_Members::detach( $m->ID, 'removed', 1 ); }
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an administrator\'s address is refused', $r[2], 'provision-admin' );
$GLOBALS['nonce_ok'] = false;
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'a bad nonce dies', $r[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid'] = 9; $GLOBALS['users'][9] = new WP_User( 9, array( 'subscriber' ), 'Stranger', 'maciej@a8c.com' );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'a non-manager dies with 403', $r[0] === 'die' && false !== strpos( $r[1], '403' ), true );
$GLOBALS['uid'] = 1;

echo "\n=== Members ===\n";
$GLOBALS['users'][7]->roles = array( 'wpcpm_mentor' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'maciej@a8c.com' ), array( $module, 'handle_members' ) );
ck( 'attach by address finds the account', $r[2], 'attached' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'nobody@example.test' ), array( $module, 'handle_members' ) );
ck( 'an address with no account is not created here: provisioning does that', $r[2], 'attach-no-account' );
ck( 'and its sentence says Attach account takes an account that exists, and where a sponsor\'s first account is made', WPCPM_Sponsors::messages()['attach-no-account'], array( 'error', 'No account has that address. Attach account attaches an account that exists; a sponsor\'s first account is made with Create account on the No account view.' ) );
$GLOBALS['patched'] = array();
$attached = WPCPM_Sponsor_Members::members_of( $A );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'detach', 'wpcpm_user' => $attached[0]->ID ), array( $module, 'handle_members' ) );
ck( 'detach removes the one account', array( $r[2], WPCPM_Sponsor_Members::members_of( $A ) ), array( 'detached', array() ) );
ck( 'and tells the base the sponsor has no account now', $GLOBALS['patched'][0][1][0]['fields'], array( 'Dashboard account' => false ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'eat' ), array( $module, 'handle_members' ) );
ck( 'an op that is not one is the one refusal', $r[2], 'refused' );

echo "\n=== Provisioning refuses a student's address, and a detach refuses another sponsor's member ===\n";
// $C ('Ember Example') is Paused in the fixture; flipped to Approved only for this scenario and
// flipped back before the screen renders below, so nothing else in this file sees a fourth
// qualifying sponsor or an extra Create account button.
WPCPM_Sponsors_Index::patch( $C, array( 'status' => WPCPM_Sponsors_Index::STATUS_APPROVED ) );
// maciej@a8c.com currently names both Ines (id 7) and the Stranger (id 9); both moved
// aside so the student account below is the one and only match, the same technique the
// provisioning tests above already use to steer get_user_by( 'email', ... ).
$GLOBALS['users'][7]->user_email = 'moved-aside-1@example.test';
$GLOBALS['users'][9]->user_email = 'moved-aside-2@example.test';
$GLOBALS['users'][11]                                    = new WP_User( 11, array( 'wpcpm_student' ), 'A Student', 'maciej@a8c.com' );
$GLOBALS['umeta'][11]['wpcpm_student_record_id'] = 'recSTUDENT0000001';
$r = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
ck( 'provisioning a sponsor whose contact belongs to a student account is refused', $r[2], 'provision-refused' );
$GLOBALS['users'][7]->user_email = 'maciej@a8c.com';
$GLOBALS['users'][9]->user_email = 'maciej@a8c.com';
WPCPM_Sponsors_Index::patch( $C, array( 'status' => 'Paused' ) );

$GLOBALS['users'][12] = new WP_User( 12, array( 'subscriber' ), 'Another Rep', 'maciej@a8c.com' );
WPCPM_Sponsor_Members::attach( 12, $A, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$r = post( array( 'wpcpm_sponsor' => $B, 'wpcpm_op' => 'detach', 'wpcpm_user' => 12 ), array( $module, 'handle_members' ) );
ck( 'a detach naming a member of a different sponsor is refused', $r[2], 'detach-refused' );
WPCPM_Sponsor_Members::detach( 12, WPCPM_Sponsor_Members::REASON_REMOVED, 1 );

echo "\n=== The screen, in six tabs by job ===\n";
// Each tab drawn at its own address, and the screen at its own, which names no tab. The applications
// card is the application class's, which is loaded further down, so here the queue tab draws only its
// other two cards, the sponsor posts and the signed agreements, both empty; the tabs are read again
// once it is (each tab's cards and what each reads, in the queue's section).
$slugs = array( 'queue', 'sponsors', 'accounts', 'offers', 'interests', 'agreements' );
$tabs  = array();
foreach ( $slugs as $slug ) {
	$tabs[ $slug ] = render_tab( $slug );
}
$bare = render_screen();

ck( 'the screen holds six tabs, the queue first, in the words a manager knows each job by', WPCPM_Sponsors::TABS, array(
	'queue'      => 'Waiting for review',
	'sponsors'   => 'Sponsors',
	'accounts'   => 'Accounts',
	'offers'     => 'Offers and codes',
	'interests'  => 'Interests',
	'agreements' => 'Agreements',
) );

$home    = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors';
$bar_for = function ( $shown ) use ( $home, $slugs ) {
	$labels = array_combine( $slugs, array( 'Waiting for review', 'Sponsors', 'Accounts', 'Offers and codes', 'Interests', 'Agreements' ) );
	$out    = array();
	foreach ( $labels as $slug => $label ) {
		$out[] = array( $home . '&tab=' . $slug, $label, $shown === $slug, $shown === $slug );
	}
	return array( 'Secondary menu', $out );
};

ck( 'the bar holds the six in that order, each at the screen\'s address with its tab, and the screen\'s own address marks the queue, for the eye and for a screen reader', bar_of( $bare ), $bar_for( 'queue' ) );
$marked = array();
$wanted = array();
foreach ( $tabs as $slug => $drawn ) {
	$marked[ $slug ] = bar_of( $drawn );
	$wanted[ $slug ] = $bar_for( $slug );
}
ck( 'each tab asked for by its slug is the one the bar marks', $marked, $wanted );
ck( 'the screen\'s own address draws the queue tab, tag for tag', $bare, $tabs['queue'] );
ck( 'and so does a tab the screen does not have', render_tab( 'nope' ), $bare );
ck( 'the frame: the heading, the lede in the screen\'s words, then the screen\'s one bar, which no heading holds, all inside the wrap', array(
	0 === strpos( $bare, '<div class="wrap wpcpm-wrap"><h1>Sponsors</h1><p class="wpcpm-lede">The companies that sponsor mentors and offer their tools to students. Each has a Sponsor Dashboard, its own people and its offer; their accounts are created and invited on the Accounts tab.</p><nav class="nav-tab-wrapper wp-clearfix"' ),
	substr_count( $bare, 'nav-tab-wrapper' ),
	preg_match( '#<h[1-6][^>]*nav-tab-wrapper#', $bare ),
	'</div>' === substr( $bare, -6 ),
), array( true, 1, 0, true ) );

$GLOBALS['l10n'] = array(
	'Waiting for review' => 'En espera de revisión',
	'Sponsors'           => 'Patrocinadores',
	'Accounts'           => 'Cuentas',
	'Offers and codes'   => 'Ofertas y códigos',
	'Interests'          => 'Intereses',
	'Agreements'         => 'Acuerdos',
	'Secondary menu'     => 'Menú secundario',
);
$spanish         = bar_of( render_tab( 'interests' ) );
$GLOBALS['l10n'] = array();
ck( 'the labels are translated where the bar prints them, and so is the name the bar gives itself',
	null === $spanish ? null : array( $spanish[0], array_column( $spanish[1], 1 ) ),
	array( 'Menú secundario', array( 'En espera de revisión', 'Patrocinadores', 'Cuentas', 'Ofertas y códigos', 'Intereses', 'Acuerdos' ) ) );

ck( 'the Sponsors tab draws the sync card', false !== strpos( $tabs['sponsors'], 'wpcpm_sponsors_sync' ) && false !== strpos( $tabs['sponsors'], 'Sync sponsors now' ), true );
ck( 'and the index card, which lists every sponsor with its status and manager', false !== strpos( $tabs['sponsors'], 'Mango Example' ) && false !== strpos( $tabs['sponsors'], 'Paused' ) && false !== strpos( $tabs['sponsors'], 'Maciej (Matt) Pilarski' ), true );
ck( 'the Sponsors card offers no Create account: no tab draws a form that posts it', substr_count( implode( '', $tabs ), 'value="' . WPCPM_Sponsors::ACTION_PROVISION . '"' ), 0 );
// The card's last column opens each sponsor's accounts, named for a screen reader by the sponsor's
// name as the card prints it, trimmed: one link a row, Paused Ember Example's among them.
$manage_cell = static function ( $record, $name ) use ( $home ) {
	return '<td><a href="' . $home . '&tab=accounts&wpcpm_sponsor=' . $record . '">Manage accounts<span class="screen-reader-text"> of ' . $name . '</span></a></td></tr>';
};
ck( 'its last column is each sponsor\'s Manage accounts, to that sponsor\'s accounts on the Accounts tab, named by the sponsor for a screen reader: on each of its three rows, whatever the sponsor\'s status, and no row ends in an empty cell', array(
	substr_count( $tabs['sponsors'], '>Manage accounts<span class="screen-reader-text">' ),
	false !== strpos( $tabs['sponsors'], $manage_cell( $A, 'Mango Example' ) ),
	false !== strpos( $tabs['sponsors'], $manage_cell( $B, 'Wexample' ) ),
	false !== strpos( $tabs['sponsors'], $manage_cell( $C, 'Ember Example' ) ),
	substr_count( $tabs['sponsors'], '<td></td></tr>' ),
), array( 3, true, true, true, 0 ) );
ck( 'and that column\'s heading is Manage accounts for a screen reader, so no header cell is empty', array(
	false !== strpos( $tabs['sponsors'], '<th scope="col">Logo</th><th scope="col"><span class="screen-reader-text">Manage accounts</span></th></tr></thead>' ),
	substr_count( $tabs['sponsors'], '<th scope="col"></th>' ),
), array( true, 0 ) );
ck( 'the Accounts tab draws the invitations card and the sponsor accounts list, a WordPress list table under the tab\'s address, and no sponsor\'s accounts: no Remove, no Attach account and no posting switch, which are drawn for one sponsor at a time', array(
	cards_of( $tabs['accounts'] ),
	false !== strpos( $tabs['accounts'], '<form method="get">' ),
	false !== strpos( $tabs['accounts'], '<input type="hidden" name="tab" value="accounts" />' ),
	form_fields_of( $tabs['accounts'], WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $tabs['accounts'], WPCPM_Sponsor_Posts::ACTION_FLAGS ),
), array( array( 'Invitations', 'Sponsor accounts' ), true, true, array(), array() ) );
ck( 'the Interests tab draws the interests log card and no other', cards_of( $tabs['interests'] ), array( 'Interests' ) );
// The once attribute is what the submit guard reads, on the dashboards and, since 1.122.1, in
// wp-admin too; the script itself is not run here, so each check reads the attribute and nothing more.
ck( 'every form the module draws on a tab carries the once attribute', array_map( static function ( $html ) { return post_forms( without_invitations( $html ), true ); }, $tabs ), array_fill_keys( $slugs, array() ) );
// The agreement forms name no tab: they come back by their referer. The Accounts tab draws no form
// here: the invitations card draws its button only for an account never invited, and there is none.
ck( 'every form whose press comes back by the tab it names names its own: the sync the Sponsors tab', array_map( 'tab_fields', $tabs ), array(
	'queue'      => array(),
	'sponsors'   => array( 'sponsors' => 1 ),
	'accounts'   => array(),
	'offers'     => array(),
	'interests'  => array(),
	'agreements' => array(),
) );

// The last sync's error is the sync card's to explain, so it is said on the tab that draws the card,
// above it, and on no other.
update_option( WPCPM_Sponsors_Sync::OPT_ERROR, 'The base did not answer.' );
$error_line = '<div class="notice notice-error"><p><strong>Last sync error:</strong> The base did not answer.</p></div>';
$errored    = array();
foreach ( $slugs as $slug ) {
	$drawn            = render_tab( $slug );
	$at               = strpos( $drawn, $error_line );
	$errored[ $slug ] = false !== $at && $at > (int) strpos( $drawn, '</nav>' ) && $at < (int) strpos( $drawn, '<h2>Airtable sync</h2>' );
}
delete_option( WPCPM_Sponsors_Sync::OPT_ERROR );
ck( 'the last sync\'s error is said on the Sponsors tab alone, under the bar and above the sync card', $errored, array_merge( array_fill_keys( $slugs, false ), array( 'sponsors' => true ) ) );

$reload = 'That action could not be completed. Reload the screen and try again.';
$worded = array();
foreach ( $slugs as $slug ) {
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'error' );
	preg_match( '#<div class="notice notice-error is-dismissible"><p>(.*?)</p></div>#', render_tab( $slug ), $error_notice );
	$worded[ $slug ] = isset( $error_notice[1] ) ? $error_notice[1] : '';
}
ck( 'the outcome a failed press leaves is worded by the tab it comes back to: on the Sponsors tab the sync\'s, which points to the last sync\'s error printed there, on the Accounts tab the invitation\'s, the one press there that fails so, and on the four tabs that print no error, a reload', $worded, array(
	'queue'      => $reload,
	'sponsors'   => 'That action could not be completed. See the error below.',
	'accounts'   => 'The invitation could not be sent.',
	'offers'     => $reload,
	'interests'  => $reload,
	'agreements' => $reload,
) );
// One outcome, flashed before each draw, is printed once on every tab, before the bar: the suite's
// WPCPM_Flash remembers what a page took, as the real one does, so a second printer on the channel
// would print it again and be counted here.
$printed_once = array();
foreach ( $slugs as $slug ) {
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'error' );
	$drawn = render_tab( $slug );
	preg_match_all( '#<div class="notice notice-[a-z]+ is-dismissible">#', $drawn, $dismissible, PREG_OFFSET_CAPTURE );
	$printed_once[ $slug ] = array( count( $dismissible[0] ), isset( $dismissible[0][0][1] ) && $dismissible[0][0][1] < (int) strpos( $drawn, '<nav class="nav-tab-wrapper' ) );
}
ck( 'one outcome flashed is the one dismissible notice on every tab, printed once, before the bar', $printed_once, array_fill_keys( $slugs, array( 1, true ) ) );
$written = array();
foreach ( array_merge( glob( __DIR__ . '/../includes/*.php' ), glob( __DIR__ . '/../includes/modules/*.php' ) ) as $file ) {
	$times = substr_count( (string) file_get_contents( $file ), "'" . $reload . "'" );
	if ( $times > 0 ) {
		$written[ basename( $file ) ] = $times;
	}
}
ck( 'and the reload is one sentence, written once, in the accessor the Institutions and Sponsors screens both read', array(
	$written,
	method_exists( 'WPCPM_Sync_Module', 'failed_message' ) ? WPCPM_Sync_Module::failed_message() : null,
	substr_count( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institutions.php' ), 'self::failed_message()' ),
	substr_count( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'self::failed_message()' ),
), array( array( 'class-wpcpm-sync-module.php' => 1 ), array( 'error', $reload ), 1, 1 ) );

// Each press is made with the fields its drawn form posts. Sync sponsors now starts a run, so the
// Sponsors tab then draws Cancel sync in its place, which ends it.
$sync_form   = form_fields_of( $tabs['sponsors'], WPCPM_Sponsors::ACTION_SYNC );
$r_sync      = post( isset( $sync_form[0] ) ? $sync_form[0] : array(), array( $module, 'handle_sync' ) );
$cancel_form = form_fields_of( render_tab( 'sponsors' ), WPCPM_Sponsors::ACTION_CANCEL );
$r_cancel    = post( isset( $cancel_form[0] ) ? $cancel_form[0] : array(), array( $module, 'handle_cancel' ) );
ck( 'Sync sponsors now and Cancel sync come back to the Sponsors tab, each with its outcome', array( $r_sync, $r_cancel ), array(
	array( 'redirect', $home . '&tab=sponsors', 'started' ),
	array( 'redirect', $home . '&tab=sponsors', 'cancelled' ),
) );
// Create account is pressed while its sponsor is not Approved any more, so the handler refuses it and
// creates nothing, and Attach account, on Mango Example's accounts, with an address no account has:
// where a press lands is its own to say, whatever the outcome. Create account is pressed as the
// Sponsors card's form posted it, naming its old tab: it is the Accounts tab's list that creates
// accounts now, and that is where it comes back to (a row's link, and the list as it stood, are
// bin/test-sponsors-accounts.php's). Attach account comes back to the accounts it was pressed on.
WPCPM_Sponsors_Index::patch( $A, array( 'status' => 'Paused' ) );
$r_create = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_tab' => 'sponsors' ), array( $module, 'handle_provision' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'status' => WPCPM_Sponsors_Index::STATUS_APPROVED ) );
$attach_form = form_fields_of( render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $A ) ), WPCPM_Sponsors::ACTION_MEMBERS );
$r_attach    = post( array_merge( isset( $attach_form[0] ) ? $attach_form[0] : array(), array( 'wpcpm_email' => 'nobody@example.test' ) ), array( $module, 'handle_members' ) );
ck( 'Create account comes back to the Accounts tab, and Attach account to the accounts of the sponsor it was pressed for, on that tab, each with its outcome', array( $r_create, $r_attach ), array(
	array( 'redirect', $home . '&tab=accounts', 'provision-inactive' ),
	array( 'redirect', $home . '&tab=accounts&wpcpm_sponsor=' . $A, 'attach-no-account' ),
) );
// A press of the members handler comes back to a sponsor's accounts only when it names both the
// Accounts tab and the sponsor, as the forms there post them: one that names the tab alone comes
// back to the tab itself, and one that names a sponsor and another tab to that tab.
$r_no_sponsor = post( array( 'wpcpm_op' => 'attach', 'wpcpm_email' => 'nobody@example.test', 'wpcpm_tab' => 'accounts' ), array( $module, 'handle_members' ) );
$r_other_tab  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'nobody@example.test', 'wpcpm_tab' => 'sponsors' ), array( $module, 'handle_members' ) );
ck( 'a press that names the Accounts tab and no sponsor comes back to the tab itself, and one that names a sponsor and another tab to that tab', array( $r_no_sponsor, $r_other_tab ), array(
	array( 'redirect', $home . '&tab=accounts', 'attach-no-account' ),
	array( 'redirect', $home . '&tab=sponsors', 'attach-no-account' ),
) );
ck( 'a sponsor\'s accounts are the Accounts tab with the sponsor\'s record in the address, encoded as a query value', array(
	method_exists( 'WPCPM_Sponsors', 'accounts_view_url' ) ? WPCPM_Sponsors::accounts_view_url( $B ) : 'no accounts_view_url()',
	method_exists( 'WPCPM_Sponsors', 'accounts_view_url' ) ? WPCPM_Sponsors::accounts_view_url( 'rec a&b #1' ) : 'no accounts_view_url()',
), array( $home . '&tab=accounts&wpcpm_sponsor=' . $B, $home . '&tab=accounts&wpcpm_sponsor=rec%20a%26b%20%231' ) );

// The page the redirect opens shows the outcome its press flashed: once, on the tab its address
// names, above the bar, and not again on the next page. Attach account's opens the sponsor's
// accounts it was pressed on, under the bar.
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, (string) $r_attach[2] );
$landed = render_screen( link_of( (string) $r_attach[1] )[1] );
$again  = render_screen( link_of( (string) $r_attach[1] )[1] );
$said   = '<div class="notice notice-error is-dismissible"><p>No account has that address. Attach account attaches an account that exists; a sponsor&#039;s first account is made with Create account on the No account view.</p></div>';
ck( 'the outcome a press leaves prints once, on the tab it comes back to, above the bar and every card, and not on the next page: Attach account\'s above the accounts of the sponsor it was pressed for', array(
	substr_count( $landed, $said ),
	false !== strpos( $landed, $said ) && strpos( $landed, $said ) < (int) strpos( $landed, '<nav class="nav-tab-wrapper' ),
	bar_of( $landed ) === $bar_for( 'accounts' ),
	false !== strpos( $landed, '</nav><div class="wpcpm-card"><h2>Mango Example</h2>' ),
	notices_in( $again ),
), array( 1, true, true, true, '' ) );

echo "\n=== The Airtable write ===\n";
$GLOBALS['airtable_fail'] = true;
ck( 'mark_dashboard_account() hands back the client\'s error', is_wp_error( WPCPM_Sponsors::mark_dashboard_account( $A, true ) ), true );
unset( $GLOBALS['airtable_fail'] );
ck( 'and refuses a malformed record before any request', is_wp_error( WPCPM_Sponsors::mark_dashboard_account( 'nope', true ) ), true );

echo "\n=== The Offers card and its handlers ===\n";
// Run here, before Uninstall: the Uninstall section below calls the real uninstall(), which
// also deletes every offer, pool and claim, so the offer seeded above must be exercised before
// that happens. The uninstall-side check on this same offer is appended after the Uninstall
// section's own call instead of calling uninstall() a second time here, which would both
// double-count that section's $GLOBALS['deleted_meta'] tally and erase every account's
// sponsor-member stamp before its "some account still carries this module's stamps before
// uninstall" check runs.
// B gets a live account, attached directly through WPCPM_Sponsor_Members::attach() rather than
// through WPCPM_Sponsors::provision(), so no offer is seeded for it and the Seed button's own
// condition (an account, no offer) is met by someone. Without it, accounts_by_sponsor() holds
// nothing for any sponsor at this point in the run (A's own account was detached above and never
// reattached), and the Seed checks below would have no button to read.
WPCPM_Sponsors_Index::patch( $B, array( 'contact_email' => 'maciej@a8c.com' ) );
$rep_b                      = 60;
$GLOBALS['users'][ $rep_b ] = new WP_User( $rep_b, array( 'subscriber' ), 'Rep B', 'repb@example.test' );
WPCPM_Sponsor_Members::attach( $rep_b, $B, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$screen = render_tab( 'offers' );
ck( 'the Offers and codes tab has the Offers card with the seeded offer and its counts', array( false !== strpos( $screen, 'id="wpcpm-sponsor-offers"' ), false !== strpos( $screen, 'Not switched on yet' ), false !== strpos( $screen, '<td>0</td>' ) ), array( true, true, true ) );
ck( 'a sponsor with an account and no offer gets a Seed button with its own nonce', false !== strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_SEED . '_' . $B ), true );
ck( 'and one with an offer does not', strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_SEED . '_' . $A ), false );
// Seed is the tab's one form here (nobody has claimed yet), so the attribute is read on a tab that draws it.
$seed_form = form_fields_of( $screen, WPCPM_Sponsors::ACTION_SEED );
ck( 'Seed carries the once attribute, saying it is seeding, and names the tab its press comes back to', array(
	post_forms( $screen ),
	post_forms( $screen, true ),
	false !== strpos( $screen, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Seeding">' ),
	isset( $seed_form[0][ WPCPM_Sponsors::TAB_FIELD ] ) ? $seed_form[0][ WPCPM_Sponsors::TAB_FIELD ] : '',
), array( array( WPCPM_Sponsors::ACTION_SEED ), array(), true, 'offers' ) );

echo "\n=== The posting switch, per sponsor ===\n";
// B carries a live account (Rep B, attached above), so this is a sponsor with an account, as the
// switch is meant for. The switch is drawn on the sponsor's accounts, a view of the Accounts tab.
$screen = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) );
ck( 'each sponsor with accounts has the posting switch on its accounts, keyed to its record', array(
	false !== strpos( $screen, '<input type="hidden" name="action" value="wpcpm_sponsor_flags" />' ),
	false !== strpos( $screen, 'wpcpm_sponsor_flags_' . $B ),
	false !== strpos( $screen, 'name="wpcpm_on" value="0"' ),
	false !== strpos( $screen, 'Turn posting off' ),
), array( true, true, true, true ) );
$GLOBALS['posting_off'][ $B ] = true;
$screen = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) );
ck( 'with posting off the switch offers to turn it on', array( false !== strpos( $screen, 'name="wpcpm_on" value="1"' ), false !== strpos( $screen, 'Turn posting on' ), false !== strpos( $screen, 'Posting is off for this sponsor.' ) ), array( true, true, true ) );
$GLOBALS['posting_off'] = array();
// The switch's press comes back to the sponsor's accounts (`WPCPM_Sponsor_Posts::handle_flags()`,
// pressed in bin/test-sponsor-posts.php), and that page says what it did in the switch's own words.
$switch_lands = link_of( WPCPM_Sponsors::accounts_view_url( $B ) )[1];
$posting_said = array();
foreach ( array( 'posting-off', 'posting-on' ) as $outcome ) {
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $outcome );
	$drawn                    = render_screen( $switch_lands );
	$posting_said[ $outcome ] = array( notices_in( $drawn ), (int) strpos( $drawn, 'is-dismissible' ) < (int) strpos( $drawn, '<nav class="nav-tab-wrapper' ) );
}
ck( 'the posting switch\'s two outcomes are said in their own words, once, above the bar, on the sponsor\'s accounts its press comes back to', $posting_said, array(
	'posting-off' => array( '<div class="notice notice-success is-dismissible"><p>Posting is off for this sponsor: its accounts can no longer write posts.</p></div>', true ),
	'posting-on'  => array( '<div class="notice notice-success is-dismissible"><p>Posting is on for this sponsor: its accounts can write posts and submit them for review.</p></div>', true ),
) );

echo "\n=== The Accounts tab, with an account on it ===\n";
// Rep B acts for B, attached above and never invited, so the tab draws its list with a row in it and
// the invitations card with its button; and the ceiling the roster reads has locked Rep B out of
// changes for the rest of today, so the tab names Rep B above the list.
$GLOBALS['ceiling_counts'] = array( WPCPM_Refusal_Meter::key( WPCPM_Sponsor_Roster::METER_SCOPE, WPCPM_Refusal_Meter::STEM_REFUSED, $GLOBALS['users'][ $rep_b ] ) => WPCPM_Refusal_Meter::PER_DAY );
$with_account              = render_tab( 'accounts' );
$with_account_read         = reads_now();
$locked_on                 = array();
foreach ( $slugs as $slug ) {
	$locked_on[ $slug ] = substr_count( render_tab( $slug ), 'locked out of changes for the rest of today' );
}
$GLOBALS['ceiling_counts'] = array();
$at                        = array(
	'locked'      => strpos( $with_account, '<div class="notice notice-warning"><p><strong>1 sponsor account is locked out of changes for the rest of today.</strong>' ),
	'invitations' => strpos( $with_account, '<div class="wpcpm-card wpcpm-invites">' ),
	'list'        => strpos( $with_account, '<h2>Sponsor accounts <span class="wpcpm-count">1</span></h2>' ),
);

ck( 'the tab draws, under the bar, the accounts locked today, the invitations card and the list, in that order, Rep B a row of it, and no sponsor\'s accounts: no other card, no Remove and no posting switch', array(
	! in_array( false, $at, true ) && $at['locked'] > strpos( $with_account, '</nav>' ) && $at['locked'] < $at['invitations'] && $at['invitations'] < $at['list'],
	false !== strpos( $with_account, 'id="wpcpm-account-' . $rep_b . '"' ),
	cards_of( $with_account ),
	form_fields_of( $with_account, WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $with_account, WPCPM_Sponsor_Posts::ACTION_FLAGS ),
), array( true, true, array( 'Invitations', 'Sponsor accounts' ), array(), array() ) );
ck( 'and no address is printed anywhere on it: the locked account is named by name and username, the list prints none', array( strpos( $with_account, '@' ), false !== strpos( $with_account, 'Rep B (repb)' ) ), array( false, true ) );
ck( 'the locked accounts are named by name and username, on the Accounts tab and on no other', array( false !== strpos( $with_account, 'Each was refused more requests than the daily ceiling allows: Rep B (repb).' ), $locked_on ), array( true, array(
	'queue'      => 0,
	'sponsors'   => 0,
	'accounts'   => 1,
	'offers'     => 0,
	'interests'  => 0,
	'agreements' => 0,
) ) );
ck( 'the invitations card counts Rep B and posts its own action, naming the Accounts tab, and every form the module draws on the tab carries the once attribute', array(
	form_fields_of( $with_account, WPCPM_Sponsors::ACTION_BULK ),
	false !== strpos( invitations_of( $with_account ), '>Invite 1 sponsor account that has never been invited</button>' ),
	post_forms( without_invitations( $with_account ), true ),
), array( array( array( 'action' => 'wpcpm_sponsors_bulk_invite', 'wpcpm_tab' => 'accounts' ) ), true, array() ) );
ck( 'the one form on it whose press comes back by the tab it names, the invitations card\'s, names the Accounts tab', tab_fields( $with_account ), array( 'accounts' => 1 ) );
ck( 'and it reads the index and the accounts, and nothing of the queue, the sync or the agreements', $with_account_read, array(
	'index'             => true,
	'progress'          => false,
	'last read'         => false,
	'accounts'          => true,
	'applications'      => false,
	'posts'             => false,
	'waiting documents' => false,
	'out of force'      => false,
) );

echo "\n=== One sponsor's accounts, a view of the Accounts tab ===\n";
// Wexample (B) is Approved and has Rep B, attached above; Mango Example (A) is Approved with no
// account; Ember Example (C) is Paused and is given an account for this section alone, as a sponsor
// that stopped being Approved keeps its accounts. Rep B is locked out of changes for today while
// Wexample's accounts are drawn, so the view is seen to leave out the locked accounts' notice as it
// leaves out the invitations card and the list.
$GLOBALS['ceiling_counts'] = array( WPCPM_Refusal_Meter::key( WPCPM_Sponsor_Roster::METER_SCOPE, WPCPM_Refusal_Meter::STEM_REFUSED, $GLOBALS['users'][ $rep_b ] ) => WPCPM_Refusal_Meter::PER_DAY );
$view_b                    = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) );
$view_b_read               = reads_now();
$view_b_queries            = array( $GLOBALS['user_queries'], $GLOBALS['list_queries'] );
$GLOBALS['ceiling_counts'] = array();
$view_b_body               = (string) strstr( $view_b, '</nav>' );
$drawn_at                  = array();
foreach ( array(
	'name'    => '<div class="wpcpm-card"><h2>Wexample</h2>',
	'back'    => '>Back to the accounts</a>',
	'account' => '<ul class="wpcpm-list"><li>Rep B (repb@example.test) <form',
	'attach'  => '<input type="hidden" name="wpcpm_op" value="attach" />',
	'posting' => '<p class="description">Posting is on: 1 account can write posts in wp-admin; a program manager publishes them.</p>',
	'switch'  => '>Turn posting off</button></form>',
) as $part => $needle ) {
	$drawn_at[ $part ] = strpos( $view_b_body, $needle );
}
$drawn_in_order = $drawn_at;
asort( $drawn_in_order );

ck( 'with a sponsor in its address the Accounts tab draws that sponsor\'s accounts in place of the accounts locked today, the invitations card and the list: one card under the bar holding the sponsor\'s name, the way back to the accounts, Rep B by name and address with Remove, the Attach account form, then the posting sentence and its switch, in that order', array(
	in_array( false, $drawn_at, true ) ? $drawn_at : array_keys( $drawn_in_order ),
	substr_count( $view_b, '<div class="wpcpm-card' ),
	strpos( $view_b, 'locked out of changes' ),
	strpos( $view_b, 'wpcpm-invites' ),
	strpos( $view_b, '<form method="get">' ),
	strpos( $view_b, 'Sponsor accounts' ),
	bar_of( $view_b ) === $bar_for( 'accounts' ),
), array( array( 'name', 'back', 'account', 'attach', 'posting', 'switch' ), 1, false, false, false, false, true ) );

// A search kept whole on its way back is bin/test-sponsors-accounts.php's, against core's port of
// add_query_arg(); this suite's encodes what it is given.
$opened_from = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B, 'wpcpm_view' => 'never-invited', 'orderby' => 'sponsor', 'order' => 'desc', 'paged' => '2' ) );
$back_of     = static function ( $html ) {
	return link_of( preg_match( '#<a href="([^"]*)">Back to the accounts</a>#', (string) $html, $found ) ? $found[1] : '' );
};
ck( 'its way back is the list as it stood when the link that opened it was followed, its view, sort and page, which that link carried; opened with none, the Accounts tab itself', array( $back_of( $opened_from ), $back_of( $view_b ) ), array(
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'never-invited', 'orderby' => 'sponsor', 'order' => 'desc', 'paged' => '2' ) ),
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) ),
) );
ck( 'every form on it posts the Accounts tab and the sponsor beside its own fields, so a press made there comes back to it: Rep B\'s Remove and Attach account under the members handler\'s nonce, the switch under the one keyed to the sponsor', array(
	form_fields_of( $view_b, WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $view_b, WPCPM_Sponsor_Posts::ACTION_FLAGS ),
	tab_fields( $view_b ),
), array(
	array(
		array( '_wpnonce' => 'nonce-wpcpm_sponsor_members', 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => $B, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => (string) $rep_b ),
		array( '_wpnonce' => 'nonce-wpcpm_sponsor_members', 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'attach', 'wpcpm_sponsor' => $B, 'wpcpm_tab' => 'accounts' ),
	),
	array( array( '_wpnonce' => 'nonce-wpcpm_sponsor_flags_' . $B, 'action' => 'wpcpm_sponsor_flags', 'wpcpm_on' => '0', 'wpcpm_sponsor' => $B, 'wpcpm_tab' => 'accounts' ) ),
	array( 'accounts' => 3 ),
) );
ck( 'each is an inline form posting to admin-post.php, with the once attribute and its busy word: Remove with its confirm, Attach account with the address it asks for, and the switch', array(
	post_forms( $view_b ),
	post_forms( $view_b, true ),
	false !== strpos( $view_b, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Removing" data-wpcpm-confirm="Remove this account from the sponsor?">' ),
	false !== strpos( $view_b, '<button type="submit" class="button-link-delete">Remove</button></form></li></ul>' ),
	false !== strpos( $view_b, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Attaching">' ),
	false !== strpos( $view_b, '<label class="screen-reader-text" for="wpcpm-attach-' . $B . '">Email address</label><input type="email" id="wpcpm-attach-' . $B . '" name="wpcpm_email" placeholder="name@company.example" required /> <button type="submit" class="button">Attach account</button></form>' ),
	false !== strpos( $view_b, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Switching">' ),
), array( array( 'wpcpm_sponsor_members', 'wpcpm_sponsor_members', 'wpcpm_sponsor_flags' ), array(), true, true, true, true, true ) );
ck( 'it reads the index and that sponsor\'s accounts alone: no list is built for it, the live accounts of every sponsor are not read, and nothing of the queue, the sync or the agreements', array( $view_b_read, $view_b_queries ), array(
	array(
		'index'             => true,
		'progress'          => false,
		'last read'         => false,
		'accounts'          => false,
		'applications'      => false,
		'posts'             => false,
		'waiting documents' => false,
		'out of force'      => false,
	),
	array( array( WPCPM_Sponsor_Members::META_RECORD_ID => 1 ), array() ),
) );

$view_a = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $A ) );
ck( 'an Approved sponsor with no account yet says so, and offers Attach account and its posting switch', array(
	false !== strpos( $view_a, '<h2>Mango Example</h2><p><a href="' . $home . '&tab=accounts">Back to the accounts</a></p><p class="description">No account yet.</p><form method="post"' ),
	post_forms( $view_a ),
	strpos( $view_a, '<ul' ),
), array( true, array( 'wpcpm_sponsor_members', 'wpcpm_sponsor_flags' ), false ) );

$cara                      = 62;
$GLOBALS['users'][ $cara ] = new WP_User( $cara, array( 'subscriber' ), 'Cara Rep', 'cara@example.test' );
$cara_attached             = WPCPM_Sponsor_Members::attach( $cara, $C, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$view_c                    = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $C ) );
WPCPM_Sponsor_Members::detach( $cara, WPCPM_Sponsor_Members::REASON_REMOVED, 1 );
ck( 'a sponsor that is not Approved is drawn its accounts with Remove, then one sentence saying why nothing else is offered: no Attach account form and no posting switch', array(
	true === $cara_attached,
	false !== strpos( $view_c, '</nav><div class="wpcpm-card"><h2>Ember Example</h2><p><a href="' . $home . '&tab=accounts">Back to the accounts</a></p><ul class="wpcpm-list"><li>Cara Rep (cara@example.test) <form' ),
	false !== strpos( $view_c, '</form></li></ul><p class="description">This sponsor is not Approved, so no account can be attached to it and its posting cannot be switched.</p></div>' ),
	form_fields_of( $view_c, WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $view_c, WPCPM_Sponsor_Posts::ACTION_FLAGS ),
	strpos( $view_c, 'Posting is' ),
	post_forms( $view_c, true ),
), array(
	true,
	true,
	true,
	array( array( '_wpnonce' => 'nonce-wpcpm_sponsor_members', 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => $C, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => (string) $cara ) ),
	array(),
	false,
	array(),
) );

// A value the index does not hold names no sponsor, whatever it looks like: a record the sync never
// read, a real one in another case (a record ID is case-sensitive), no record ID at all, and markup.
$asked_for = array( 'recSPONSOR0000099', strtolower( $B ), 'not-a-record', '<b>x</b>' );
$unknown   = array();
foreach ( $asked_for as $asked ) {
	$drawn             = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $asked ) );
	$unknown[ $asked ] = array(
		false !== strpos( $drawn, '</nav><div class="wpcpm-card"><p>No sponsor record has that ID. If it should be here, run the sponsors sync on the <a href="' . $home . '&tab=sponsors">Sponsors</a> tab.</p><p><a href="' . $home . '&tab=accounts">Back to the accounts</a></p></div></div>' ),
		strpos( $drawn, $asked ),
		strpos( $drawn, esc_html( $asked ) ),
		$GLOBALS['user_queries'],
		$GLOBALS['list_queries'],
	);
}
ck( 'a value the index does not hold, a real record in another case among them, one that is no record ID and one that is markup, is said to be no sponsor\'s, with the remedy, the sponsors sync, on the Sponsors tab, and the way back to the list; nothing else is drawn or read for it, and the value is never printed', $unknown, array_fill_keys( $asked_for, array( true, false, false, array(), array() ) ) );

$module_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'the card that drew every Approved sponsor\'s accounts under the list is gone and nothing calls it: one sponsor\'s accounts are drawn by their view alone', array(
	method_exists( 'WPCPM_Sponsors', 'render_members' ),
	preg_match_all( '/(?:->|::)render_members\(/', $module_src ),
	method_exists( 'WPCPM_Sponsors', 'render_sponsor_accounts' ),
), array( false, 0, true ) );

// The warning while Airtable is not connected is the frame's, printed by the printer every accounts
// screen shares, from the one copy of its words the screen keeps.
$GLOBALS['not_connected'] = true;
$warned                   = array();
foreach ( array_merge( $slugs, array( 'one sponsor' ) ) as $slug ) {
	$drawn           = 'one sponsor' === $slug ? render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) ) : render_tab( $slug );
	$warning_at      = strpos( $drawn, '<div class="notice notice-warning"><p>Airtable is not connected yet, so no sponsors can be synced. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-settings">Open settings</a></p></div>' );
	$warned[ $slug ] = array( substr_count( $drawn, 'Airtable is not connected yet' ), false !== $warning_at && $warning_at < (int) strpos( $drawn, '<nav class="nav-tab-wrapper' ) );
}
unset( $GLOBALS['not_connected'] );
ck( 'while Airtable is not connected the warning prints once, above the bar, on every tab and on a sponsor\'s accounts, and its words are written once in the screen, where the shared printer reads them', array(
	$warned,
	substr_count( $module_src, "'Airtable is not connected yet, so no sponsors can be synced.'" ),
), array( array_fill_keys( array_merge( $slugs, array( 'one sponsor' ) ), array( 1, true ) ), 1 ) );

$r        = post( isset( $seed_form[0] ) ? $seed_form[0] : array(), array( $module, 'handle_seed' ) );
$seeded_b = WPCPM_Sponsor_Offers::offers_of( $B );
ck( 'the manager presses B\'s Seed button: one draft offer, marked primary, logged as seeded, and the press comes back to the Offers and codes tab', array( $r[2], count( $seeded_b ), reset( $seeded_b )['state'], reset( $seeded_b )['primary'], end( $GLOBALS['audit'] )['kind'], $r[1] ), array( 'offer-seeded', 1, 'draft', true, WPCPM_Sponsor_Offers::LOG_SEEDED, $home . '&tab=offers' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, (string) $r[2] );
$seeded_page = render_screen( link_of( (string) $r[1] )[1] );
ck( 'and the Offers and codes tab it lands on says so in Seed\'s own words, once, above the bar', array( notices_in( $seeded_page ), (int) strpos( $seeded_page, 'is-dismissible' ) < (int) strpos( $seeded_page, '<nav class="nav-tab-wrapper' ), bar_of( $seeded_page ) === $bar_for( 'offers' ) ), array( '<div class="notice notice-success is-dismissible"><p>The first offer was seeded from the base; the sponsor completes it and switches it on from the Sponsor Dashboard.</p></div>', true, true ) );
$r = post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) );
ck( 'seeding it again does nothing, since it already has one', $r[2], 'offer-seed-none' );
$r = post( array( 'wpcpm_sponsor' => 'recNOTINDEXED0001' ), array( $module, 'handle_seed' ) );
ck( 'an unindexed record is refused before seeding', $r[2], 'refused' );
$offer_id = (int) key( WPCPM_Sponsor_Offers::offers_of( $A ) );
WPCPM_Sponsor_Offers::add_codes( $offer_id, "S-1\nS-2" );
WPCPM_Sponsor_Offers::set_state( $offer_id, 'live' );
$GLOBALS['program'] = array();
$claimant = ( new WP_User( 77, array( 'wpcpm_student' ), 'Student Seven', 'maciej@a8c.com' ) );
$GLOBALS['users'][77] = $claimant;
WPCPM_Sponsor_Codes::take( $offer_id, 77 );
update_user_meta( 77, WPCPM_Sponsor_Claims::META_CLAIMS, array( $offer_id => array( 'i' => 0, 'at' => time() ) ) );
$screen = render_tab( 'offers' );
ck( 'a claimant is listed to the manager with name, address, the last four characters and a Void button', array( false !== strpos( $screen, 'Student Seven' ), false !== strpos( $screen, 'maciej@a8c.com' ), false !== strpos( $screen, '>S-1<' ) || false !== strpos( $screen, 'S-1' ), false !== strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_CLAIM_VOID . '_' . $offer_id . '_77' ) ), array( true, true, true, true ) );
$void_form = form_fields_of( $screen, WPCPM_Sponsors::ACTION_CLAIM_VOID );
ck( 'Void carries the once attribute too, saying it is voiding, keeps its confirm, and names the tab its press comes back to', array(
	post_forms( $screen, true ),
	false !== strpos( $screen, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Voiding" data-wpcpm-confirm="Void this claim? The person may then claim again.">' ),
	isset( $void_form[0][ WPCPM_Sponsors::TAB_FIELD ] ) ? $void_form[0][ WPCPM_Sponsors::TAB_FIELD ] : '',
), array( array(), true, 'offers' ) );
$GLOBALS['uid'] = 1;
// post()'s redirect outcome is array( 'redirect', <url>, <flashed status> ) (see post() below),
// so $r[2] is what carries the flashed status.
$r = post( isset( $void_form[0] ) ? $void_form[0] : array(), array( $module, 'handle_claim_void' ) );
ck( 'a manager presses Void: the claim is voided, the manager is told, and the press comes back to the Offers and codes tab', array( $r[2], WPCPM_Sponsor_Claims::has_claimed( 77, $offer_id ), WPCPM_Sponsor_Codes::counts( $offer_id )['void'], $r[1] ), array( 'claim-voided', false, 1, $home . '&tab=offers' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, (string) $r[2] );
$voided_page = render_screen( link_of( (string) $r[1] )[1] );
ck( 'and the Offers and codes tab it lands on says so in Void\'s own words, once, above the bar', array( notices_in( $voided_page ), (int) strpos( $voided_page, 'is-dismissible' ) < (int) strpos( $voided_page, '<nav class="nav-tab-wrapper' ), bar_of( $voided_page ) === $bar_for( 'offers' ) ), array( '<div class="notice notice-success is-dismissible"><p>The claim was voided. The person may claim again; the code stays void for the count.</p></div>', true, true ) );
$r = post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) );
ck( 'voiding again finds nothing', $r[2], 'claim-void-none' );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_seed' ) );
ck( 'seeding a sponsor that has an offer does nothing', $r[2], 'offer-seed-none' );
$GLOBALS['uid'] = 5;
ck( 'a sponsor member cannot void a claim', post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) )[0], 'die' );
// handle_seed() refuses a sponsor member as handle_claim_void() does, pressed by the fixture's
// one real sponsor member (Rep B, attached to B above) rather than a bare non-manager ID, and
// both handlers die on a bad nonce too.
$GLOBALS['uid'] = $rep_b;
ck( 'a sponsor member cannot seed an offer either', post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) )[0], 'die' );
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = false;
ck( 'a bad nonce dies for seeding too', post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) )[0], 'die' );
ck( 'and for voiding a claim, with a real offer and claimant so the nonce alone explains it', post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) )[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid']      = 1;

// Remove, pressed on Rep B's account on Wexample's accounts, the one live account here: the last
// press on this fixture before uninstall, since it leaves B with nobody.
$remove_form = array_values(
	array_filter(
		form_fields_of( render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) ), WPCPM_Sponsors::ACTION_MEMBERS ),
		static function ( $fields ) {
			return isset( $fields['wpcpm_op'] ) && 'detach' === $fields['wpcpm_op'];
		}
	)
);
$r = post( isset( $remove_form[0] ) ? $remove_form[0] : array(), array( $module, 'handle_members' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r[2] ) ? (string) $r[2] : '' );
$after_remove = 'redirect' === $r[0] ? render_screen( link_of( (string) $r[1] )[1] ) : '';
$removed_said = '<div class="notice notice-success is-dismissible"><p>The account no longer acts for the sponsor.</p></div>';
ck( 'Remove comes back to the accounts it was pressed on, with its outcome, printed once above the bar, over Wexample with no account left', array(
	count( $remove_form ),
	$r,
	substr_count( $after_remove, $removed_said ),
	false !== strpos( $after_remove, $removed_said ) && strpos( $after_remove, $removed_said ) < (int) strpos( $after_remove, '<nav class="nav-tab-wrapper' ),
	false !== strpos( $after_remove, '</nav><div class="wpcpm-card"><h2>Wexample</h2>' ) && false !== strpos( $after_remove, '<p class="description">No account yet.</p>' ),
), array( 1, array( 'redirect', $home . '&tab=accounts&wpcpm_sponsor=' . $B, 'detached' ), 1, true, true ) );

echo "\n=== Uninstall ===\n";
// The last is a literal since 1.99.0: nothing writes it any more, and uninstall still removes
// what 1.93.0 to 1.98.1 wrote.
$meta_keys = array( WPCPM_Sponsor_Members::META_RECORD_ID, WPCPM_Sponsor_Members::META_ACTIVE, WPCPM_Sponsor_Members::META_RECORD_ID_WAS, WPCPM_Sponsor_Members::META_MEMBERSHIP, WPCPM_Sponsor_Members::META_INVITED, 'wpcpm_sponsor_profile' );
$before_users = count( $GLOBALS['users'] );
$stamped_before = array();
foreach ( $GLOBALS['users'] as $id => $user ) {
	foreach ( $meta_keys as $key ) {
		if ( '' !== get_user_meta( $id, $key, true ) ) {
			$stamped_before[] = $id . ':' . $key;
		}
	}
}
ck( 'some account still carries this module\'s stamps before uninstall (the scenario is real)', empty( $stamped_before ), false );
$module->uninstall();
// uninstall() also clears WPCPM_Sponsor_Claims::META_CLAIMS (its own check is below, "uninstall
// removes the offers, their pools and the claims"); filtered out here so this check pins exactly
// the six WPCPM_Sponsor_Members stamps this module's own loop deletes.
ck( 'uninstall() asks to delete every stamp this module owns', array_values( array_diff( $GLOBALS['deleted_meta'], array( WPCPM_Sponsor_Claims::META_CLAIMS ) ) ), $meta_keys );
$stamped_after = array();
foreach ( $GLOBALS['users'] as $id => $user ) {
	foreach ( $meta_keys as $key ) {
		if ( '' !== get_user_meta( $id, $key, true ) ) {
			$stamped_after[] = $id . ':' . $key;
		}
	}
}
ck( 'and every stamp is gone after uninstall, on every account', $stamped_after, array() );
ck( 'but no account was deleted', count( $GLOBALS['users'] ), $before_users );
ck( 'uninstall removes the offers, their pools and the claims', array( WPCPM_Sponsor_Offers::all(), get_option( WPCPM_Sponsor_Codes::option_name( $offer_id ) ), get_user_meta( 77, WPCPM_Sponsor_Claims::META_CLAIMS, true ) ), array( array(), false, '' ) );

echo "\n=== The agreement review on the Sponsors screen ===\n";
// The real WPCPM_Sponsor_Agreement is never loaded by this suite, so a stub stands in,
// answering from globals so each state below can be drawn. It declares all six SUMMARY_*
// constants, because agreement_word() below switches on all six (the real class declares all six
// too) and an undefined class constant is a fatal, not a warning. CRON_DISCARD and
// delete_all() are declared for the same reason from the other direction: PHP registers an
// unconditional top-level class the moment this file is compiled, not when execution reaches
// its declaration, so the Uninstall section far above (which calls the real uninstall(), and
// through it WPCPM_Sponsor_Agreement::delete_all() under a class_exists() guard that is
// already true) needs this stub to answer for those two as well, even though nothing below
// exercises them directly.
class WPCPM_Sponsor_Agreement {
	const ACTION_ACCEPT = 'wpcpm_sponsor_agr_accept';
	const ACTION_RETURN = 'wpcpm_sponsor_agr_return';
	const ACTION_REVOKE = 'wpcpm_sponsor_agr_revoke';
	const ACTION_REINSTATE = 'wpcpm_sponsor_agr_reinstate';
	const ACTION_ON_FILE = 'wpcpm_sponsor_agr_on_file';
	const ACTION_DOWNLOAD = 'wpcpm_sponsor_agr_download';
	const FIELD_POST = 'wpcpm_sponsor_agr_post';
	const FIELD_NOTE = 'wpcpm_sponsor_agr_note';
	const FIELD_DRIVE = 'wpcpm_sponsor_agr_drive';
	const MIN_NOTE = 20;
	const MAX_NOTE = 2000;
	const META_STATE = '_wpcpm_sagr_state';
	const STATE_REVOKED = 'revoked';
	const CRON_DISCARD = 'wpcpm_sponsor_agreement_discard';
	const SUMMARY_NONE = 'none';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED = 'returned';
	const SUMMARY_REVOKED = 'revoked';
	const SUMMARY_ACCEPTED = 'accepted';
	const SUMMARY_ON_FILE = 'on_file';
	public static function awaiting_review( $limit = 200 ) {
		$GLOBALS['queries'] = ( isset( $GLOBALS['queries'] ) ? (int) $GLOBALS['queries'] : 0 ) + 1;
		$GLOBALS['stand_in_reads']['waiting documents'] = ( isset( $GLOBALS['stand_in_reads']['waiting documents'] ) ? (int) $GLOBALS['stand_in_reads']['waiting documents'] : 0 ) + 1;
		return array_slice( isset( $GLOBALS['queue'] ) ? $GLOBALS['queue'] : array(), 0, max( 1, (int) $limit ) );
	}
	// Every document out of force, oldest first, as the Administrator Dashboard's card reads them, as
	// many as asked for, each read counted and the number it asked for kept.
	public static function revoked_all( $limit = 200 ) {
		$GLOBALS['stand_in_reads']['out of force'] = ( isset( $GLOBALS['stand_in_reads']['out of force'] ) ? (int) $GLOBALS['stand_in_reads']['out of force'] : 0 ) + 1;
		$GLOBALS['stand_in_limits']['out of force'][] = (int) $limit;
		return array_slice( isset( $GLOBALS['revoked_ids'] ) ? $GLOBALS['revoked_ids'] : array(), 0, max( 1, (int) $limit ) );
	}
	public static function review_facts( $post_id ) { return isset( $GLOBALS['facts'][ $post_id ] ) ? $GLOBALS['facts'][ $post_id ] : array(); }
	// The question asked before Reinstate, in the stand-in's own words, so a check can tell the
	// screen printed the class's question and not one of its own.
	public static function reinstate_question() { return 'The agreement class asks before it reinstates.'; }
	public static function summary( $record ) { return isset( $GLOBALS['summaries'][ $record ] ) ? $GLOBALS['summaries'][ $record ] : array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '', 'kind' => '', 'drive_url' => '', 'airtable_status' => '' ); }
	public static function posts_for( $record ) { return isset( $GLOBALS['agr_posts'][ $record ] ) ? $GLOBALS['agr_posts'][ $record ] : array(); }
	public static function manager_messages() { return array( 'agreement-accepted' => array( 'success', 'Accepted.' ) ); }
	public static function delete_all() {}
}

// Uninstall (above) wiped the index, so the TEST sponsor is written fresh rather than added to
// $A/$B/$C: nothing after this point reads their rows, and every check below keys off $T alone.
$T = 'recSPONSORTEST001';
WPCPM_Sponsors_Index::write( array(
	$T => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );

$GLOBALS['queue'] = array( 900 );
$GLOBALS['facts'] = array(
	900 => array( 'post_id' => 900, 'state' => 'submitted', 'kind' => 'own', 'sponsor' => $T, 'sponsor_name' => 'TEST Sponsor', 'uploaded_by' => 'Member One', 'uploaded_at' => '2026-09-05', 'original_name' => 'agreement.pdf', 'flags' => array( '/JavaScript' ), 'size' => 20480, 'members' => 2 ),
);
$GLOBALS['summaries'] = array( $T => array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 900, 'accepted_at' => '', 'kind' => '', 'drive_url' => '', 'airtable_status' => 'Awaiting review' ) );

// The document waiting to be read is on the queue tab, read there and decided on the Administrator
// Dashboard: the review block is drawn as it was, the facts, the checklist, the scan and the download,
// and in the place of its two decisions the way to its own item on the dashboard's card.
$queue_tab = render_tab( 'queue' );
$review    = substr( $queue_tab, (int) strpos( $queue_tab, '<section class="wpcpm-review" id="wpcpm-review-900">' ) );
$review    = substr( $review, 0, (int) strpos( $review, '</section>' ) + strlen( '</section>' ) );

ck( 'the queue tab draws one review block with the facts a reviewer reads first', array(
	substr_count( $queue_tab, '<section class="wpcpm-review"' ),
	false !== strpos( $review, 'Review the signed agreement from TEST Sponsor' ),
	false !== strpos( $review, 'Member One' ),
	false !== strpos( $review, '2026-09-05' ),
	false !== strpos( $review, 'Read the whole document.' ),
), array( 1, true, true, true, true ) );
ck( 'the scan is named and said to be a courtesy', array( false !== strpos( $review, '/JavaScript' ), false !== strpos( $review, 'courtesy' ) ), array( true, true ) );
ck( 'the download is a link, keyed to the document', array( false !== strpos( $review, 'wpcpm_sponsor_agr_download' ), false !== strpos( $review, 'wpcpm_sponsor_agr_download_900' ) ), array( true, true ) );
ck( 'and neither Accept it nor Return it with this note is drawn: in their place, the way to the document\'s own item on the Administrator Dashboard, which decides it', array(
	substr_count( $queue_tab, '<form' ),
	strpos( $queue_tab, 'value="wpcpm_sponsor_agr_accept"' ),
	strpos( $queue_tab, 'value="wpcpm_sponsor_agr_return"' ),
	strpos( $queue_tab, 'Accept it' ),
	strpos( $queue_tab, 'Return it with this note' ),
	false !== strpos( $review, '<p class="wpcpm-agreement-panel__download"><a href="https://example.test/wp-admin/admin-post.php?action=wpcpm_sponsor_agr_download&post=900&_wpnonce=wpcpm_sponsor_agr_download_900">Download the signed agreement</a></p><p class="wpcpm-review__open"><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-agreement-900">Open on the Administrator Dashboard</a></p></section>' ),
), array( 0, false, false, false, false, true ) );
// The block only reads, whoever calls it: it takes the document alone and draws no form, so no call
// can put the two decisions back on this screen. Its last line is the institution review block's,
// class and all, so the two blocks end alike.
$review_body = method_body( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'render_agreement_review' );
ck( 'the review block takes the document alone and draws no form of its own', array(
	false !== strpos( $review_body, 'function render_agreement_review( $post_id ) {' ),
	substr_count( $review_body, 'render_agreement_form(' ),
	substr_count( $review_body, '<form' ),
), array( true, 0, 0 ) );

// The Agreements tab is every Approved sponsor's standing state, and says where the document waiting
// is: on the queue tab, at its card. It draws no review block and no word about the queue being empty.
$screen = render_tab( 'agreements' );
ck( 'the Agreements tab says what it holds, and where a document a company uploaded waits, linked to that card on the Waiting for review tab', array(
	false !== strpos( $screen, '<h2>Agreements</h2><p class="description">A sponsor agreement is optional: a company&#039;s Sponsor Dashboard, offers and codes work without one. This is the state of every Approved sponsor&#039;s agreement. A document a company uploaded waits on the <a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements">Waiting for review</a> tab.</p>' ),
	strpos( $screen, '<section class="wpcpm-review"' ),
	strpos( $screen, 'Nothing is waiting to be read.' ),
	strpos( $screen, 'value="wpcpm_sponsor_agr_accept"' ),
), array( true, false, false, false ) );
ck( 'a sponsor with nothing recorded is offered the on-file form', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_on_file"' ),
	false !== strpos( $screen, 'name="wpcpm_sponsor_agr_drive"' ),
), array( true, true ) );
ck( 'the on-file form takes the signed-on day too, and every required field says so in the label\'s voice', array(
	false !== strpos( $screen, 'type="date" id="wpcpm-signed-' . $T . '" name="wpcpm_sponsor_agr_signed_on"' ),
	false !== strpos( $screen, 'The link to the signed copy in the program&#039;s Drive folder. <span class="wpcpm-field__required">Required</span></label>' ),
	1 === preg_match( '/name="wpcpm_sponsor_agr_drive"[^>]*required/', $screen ),
), array( true, true, true ) );
ck( 'and the on-file form, the one form the tab draws for it, carries the once attribute', array( post_forms( $screen ), post_forms( $screen, true ) ), array( array( 'wpcpm_sponsor_agr_on_file' ), array() ) );
// The address each agreement form carries beside its nonce, which its handler goes back to
// (`WPCPM_Sponsor_Agreement::bounce()` follows the referer). The agreement class here is a stand-in
// without its handlers, so the way back is read off the drawn forms, and the page at that address
// is drawn with an agreement outcome flashed.
$referer_of      = static function ( $html, $action ) {
	preg_match_all( '#<form\b[^>]*>(.*?)</form>#s', (string) $html, $forms );
	foreach ( $forms[1] as $form ) {
		$fields = hidden_fields_of( $form );
		if ( isset( $fields['action'] ) && $action === $fields['action'] ) {
			return isset( $fields['_wp_http_referer'] ) ? link_of( $fields['_wp_http_referer'] ) : array( 'no referer', array() );
		}
	}
	return array( 'no such form', array() );
};
$agreement_backs = array( 'on file' => $referer_of( $screen, WPCPM_Sponsor_Agreement::ACTION_ON_FILE ) );
// The tab's name is the bar's own, so renaming the tab renames it in this sentence too.
$agreements_body = method_body( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'render_agreements' );
ck( 'and the sentence takes the tab\'s name from the labels the bar prints, writing none of its own', array( substr_count( $agreements_body, "self::tab_labels()['queue']" ), substr_count( $agreements_body, "'Waiting for review'" ) ), array( 1, 0 ) );

$GLOBALS['queue']     = array();
$GLOBALS['summaries'] = array( $T => array( 'state' => 'accepted', 'agreement_id' => 901, 'pending_id' => 0, 'accepted_at' => '2026-09-06', 'kind' => 'own', 'drive_url' => '', 'airtable_status' => 'Accepted' ) );
$screen = render_tab( 'agreements' );
ck( 'an accepted agreement is offered Take it out of force, keyed to it, and no on-file form', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_revoke"' ),
	false !== strpos( $screen, 'nonce-wpcpm_sponsor_agr_revoke_901' ),
	false !== strpos( $screen, 'Take it out of force' ),
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_on_file"' ),
	false !== strpos( $screen, '2026-09-06' ),
), array( true, true, true, false, true ) );
ck( 'its note box carries the length the handler enforces', array( false !== strpos( $screen, 'minlength="20"' ), false !== strpos( $screen, 'maxlength="2000"' ) ), array( true, true ) );
ck( 'and Take it out of force, the one form the tab draws for it, carries the once attribute', array( post_forms( $screen ), post_forms( $screen, true ) ), array( array( 'wpcpm_sponsor_agr_revoke' ), array() ) );
$agreement_backs['out of force'] = $referer_of( $screen, WPCPM_Sponsor_Agreement::ACTION_REVOKE );
$empty_queue = render_tab( 'queue' );
ck( 'with nothing waiting, the queue tab\'s agreements card says so rather than printing nothing', array( false !== strpos( card_body( $empty_queue, 'wpcpm-sponsor-agreements' ), '<p>Nothing is waiting to be read.</p>' ), substr_count( $empty_queue, '<section class="wpcpm-review"' ) ), array( true, 0 ) );

// Reinstate is the Administrator Dashboard's, which lists every agreement out of force with it: the
// block says so, and links to the document's own item there, the latest revoked one, which the form
// that used to be here was keyed to.
$revoked = new WP_Post( array( 'ID' => 902, 'post_type' => 'wpcpm_sponsor_agr' ) );
$GLOBALS['pmeta'][902]     = array( '_wpcpm_sagr_state' => 'revoked' );
$GLOBALS['agr_posts']      = array( $T => array( $revoked ) );
$GLOBALS['summaries'][ $T ] = array( 'state' => 'revoked', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '', 'kind' => 'own', 'drive_url' => '', 'airtable_status' => 'Revoked' );
// The one document out of force on the site, so the dashboard's "Out of force" list holds it.
$GLOBALS['revoked_ids'] = array( 902 );
$screen = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = '';
$revoked_no_page                     = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
ck( 'a revoked one says it is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card, and links to that document\'s own item there, with no Put it back in force form', array(
	false !== strpos( $screen, '<p>It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.</p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-agreement-902">Open on the Administrator Dashboard</a></p>' ),
	strpos( $screen, 'value="wpcpm_sponsor_agr_reinstate"' ),
	strpos( $screen, 'Put it back in force' ),
	post_forms( $screen ),
), array( true, false, false, array() ) );
ck( 'while the dashboard\'s page is missing, the page-missing sentence stands in the link\'s place', array(
	false !== strpos( $revoked_no_page, '<p>It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.</p><p class="wpcpm-warning">The dashboard class says its page is missing.</p>' ),
	substr_count( $revoked_no_page, 'Open on the Administrator Dashboard' ),
	post_forms( $revoked_no_page ),
), array( true, 0, array() ) );

// The dashboard's "Out of force" list is the oldest LIMIT documents out of force on the site, and it
// does not drain: a document leaves it only when it is reinstated, and one that is not its sponsor's
// latest never can be. So a sponsor's latest revoked document can stand past it, on no list at all,
// and it is reinstated here then, by the form the dashboard's row posts, under the sentence that says
// why. One fewer older document leaves it the last the list holds, linked there as before.
$limit       = WPCPM_Administrators_Cards::LIMIT;
$past_list   = '<p>The Administrator Dashboard lists the ' . $limit . ' oldest agreements out of force, and this one is past them, so it is reinstated here.</p>';
$reinstate   = '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-review__form" data-wpcpm-once data-wpcpm-busy="Reinstating">';
$older_ids   = range( 7001, 7000 + $limit );
$GLOBALS['revoked_ids'] = array_merge( array_slice( $older_ids, 0, $limit - 1 ), array( 902 ) );
$last_listed = render_tab( 'agreements' );
$GLOBALS['revoked_ids'] = array_merge( $older_ids, array( 902 ) );
$past        = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = '';
$past_no_page                        = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
// Not found in the read, which is the Administrator Dashboard's own, is past the list too.
$GLOBALS['revoked_ids'] = $older_ids;
$unread      = render_tab( 'agreements' );
$reinstate_fields = form_fields_of( $past, WPCPM_Sponsor_Agreement::ACTION_REINSTATE );
ck( 'with one fewer older document out of force than the dashboard lists, it is the last that list holds: linked there, no form', array(
	false !== strpos( $last_listed, '<p>It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.</p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-agreement-902">Open on the Administrator Dashboard</a></p>' ),
	post_forms( $last_listed ),
	strpos( $last_listed, 'so it is reinstated here' ),
), array( true, array(), false ) );
ck( 'one more and it is past that list: reinstated here, under the sentence that says why, by the form the dashboard posts, its button in the dashboard\'s word asking the agreement class\'s own question, with the once attribute, and no way to a list that does not hold it', array(
	false !== strpos( $past, $past_list . $reinstate ),
	post_forms( $past ),
	post_forms( $past, true ),
	isset( $reinstate_fields[0] ) ? $reinstate_fields[0] : array(),
	false !== strpos( $past, 'data-wpcpm-confirm="The agreement class asks before it reinstates.">Reinstate</button></form>' ),
	substr_count( $past, 'Open on the Administrator Dashboard' ),
	strpos( $past, 'It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.' ),
), array( true, array( 'wpcpm_sponsor_agr_reinstate' ), array(), array( '_wpnonce' => 'nonce-wpcpm_sponsor_agr_reinstate_902', 'action' => 'wpcpm_sponsor_agr_reinstate', 'wpcpm_sponsor_agr_post' => '902' ), true, 0, false ) );
ck( 'it needs no page there, so it keeps the form while the dashboard\'s page is missing, and says nothing about the page', array(
	false !== strpos( $past_no_page, $past_list . $reinstate ),
	post_forms( $past_no_page ),
	strpos( $past_no_page, 'The dashboard class says its page is missing.' ),
), array( true, array( 'wpcpm_sponsor_agr_reinstate' ), false ) );
ck( 'and so is one the read of the documents out of force does not reach', array( false !== strpos( $unread, $past_list . $reinstate ), post_forms( $unread ) ), array( true, array( 'wpcpm_sponsor_agr_reinstate' ) ) );
$agreement_backs['reinstate'] = $referer_of( $past, WPCPM_Sponsor_Agreement::ACTION_REINSTATE );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'agreement-accepted' );
$agreement_lands = render_screen( $agreement_backs['on file'][1] );
ck( 'each agreement form, the on-file form, Take it out of force and Reinstate past the dashboard\'s list, carries the Agreements tab as its way back, and the page there prints the outcome once, above the bar', array(
	$agreement_backs,
	notices_in( $agreement_lands ),
	(int) strpos( $agreement_lands, 'is-dismissible' ) < (int) strpos( $agreement_lands, '<nav class="nav-tab-wrapper' ),
	bar_of( $agreement_lands ) === $bar_for( 'agreements' ),
), array(
	array_fill_keys( array( 'on file', 'out of force', 'reinstate' ), array( '/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'agreements' ) ) ),
	'<div class="notice notice-success is-dismissible"><p>Accepted.</p></div>',
	true,
	true,
) );
// The read is the one the Administrator Dashboard's card makes, its oldest LIMIT documents out of
// force: a document found in it is one that card lists, and one not found is past it.
$GLOBALS['stand_in_limits'] = array();
render_tab( 'agreements' );
ck( 'the documents out of force are read as the Administrator Dashboard\'s card reads them, up to its own limit and no further', isset( $GLOBALS['stand_in_limits']['out of force'] ) ? $GLOBALS['stand_in_limits']['out of force'] : array(), array( WPCPM_Administrators_Cards::LIMIT ) );

// The documents out of force are read once for the whole tab, however many sponsors stand there.
$T2 = 'recSPONSORTEST004';
WPCPM_Sponsors_Index::write( array(
	$T  => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
	$T2 => array( 'name' => 'Second TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );
$GLOBALS['pmeta'][903]       = array( '_wpcpm_sagr_state' => 'revoked' );
$GLOBALS['agr_posts'][ $T2 ] = array( new WP_Post( array( 'ID' => 903, 'post_type' => 'wpcpm_sponsor_agr' ) ) );
$GLOBALS['summaries'][ $T2 ] = $GLOBALS['summaries'][ $T ];
$GLOBALS['revoked_ids']      = array( 902, 903 );
$two_out = render_tab( 'agreements' );
ck( 'two sponsors out of force are two blocks, each linked to its own document there, from one read of the documents out of force', array(
	substr_count( $two_out, 'It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.' ),
	false !== strpos( $two_out, '#wpcpm-sponsor-agreement-902"' ) && false !== strpos( $two_out, '#wpcpm-sponsor-agreement-903"' ),
	isset( $GLOBALS['stand_in_reads']['out of force'] ) ? (int) $GLOBALS['stand_in_reads']['out of force'] : 0,
), array( 2, true, 1 ) );
WPCPM_Sponsors_Index::write( array(
	$T => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );
unset( $GLOBALS['agr_posts'][ $T2 ], $GLOBALS['summaries'][ $T2 ] );
$GLOBALS['revoked_ids'] = array( 902 );

echo "\n=== The application queue on the Sponsors screen ===\n";

// The real application class, loaded here rather than stubbed: what is being pinned is that
// the screen draws what the class stores, and the image handler and the mail exit are never
// reached by a render. The guard comes with it because the open application prints the
// checks' words, which name the guard's numbers.
require_once __DIR__ . '/../includes/class-wpcpm-form-guard.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-application.php';

// The real approval class too, for its `is_half_done()` alone: the queue's half-done mark is
// read off the stamp and the state, and a stub here would pin the mark against a flag this
// file sets rather than against the condition. Nothing in this suite approves
// anything, so none of the class's collaborators is ever reached.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-approval.php';


// Uninstall (above) wiped the index; the TEST sponsor was written fresh for the agreement
// section, with no website. One more row with a website, for the in-base match.
WPCPM_Sponsors_Index::write( array(
	$T                  => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
	'recSPONSORTEST002' => array( 'name' => 'Widgetry Ltd ', 'website' => 'https://www.widgetry.example/', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );

/** An application row, stored the way the form stores one. */
function seed_sponsor_application( $name, $state, array $signals = array(), array $logos = array() ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Application::POST_TYPE, 'post_status' => 'private', 'post_title' => $name, 'post_author' => 0 ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_FIELDS, array( 'Company Name' => $name, 'Website' => 'https://widgetry.example', 'Contact Person Full Name' => 'Sam Sponsor', 'Contact Email' => 'maciej@a8c.com', 'Sponsorship options' => 'Sponsor mentors + tools/services', "Anything else you'd like to share." => 'We make gadgets.' ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, $state );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_REFERENCE, sprintf( 'SAPP-2026-%04d', $id ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_SIGNALS, $signals );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_EMAIL, md5( 'maciej@a8c.com' ) );
	// 'at' is 2026-09-01 12:00 UTC, which gmdate() (this file's wp_date() stub) reads as the date
	// the check below asserts ('Agreed 2026-09-01').
	update_post_meta( $id, WPCPM_Sponsor_Application::META_CONSENT, array( 'sentence' => 'I confirm the privacy policy.', 'url' => 'https://example.test/privacy/', 'policy' => 43, 'modified' => '2026-08-20 11:30:00', 'at' => 1788264000, 'ip' => '203.0.113.0', 'agent' => 'Mozilla/5.0 (test)' ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_LOGOS, array_merge( array( 'colour' => 0, 'white' => 0 ), $logos ) );
	return $id;
}

ck( 'with nothing waiting the bubble is not drawn', $module->menu_label(), 'Sponsors' );

$bare = render_screen();
ck( 'with no application at all the queue and the closed list under it each say so, quietly', array(
	false !== strpos( $bare, 'Nothing is waiting. New applications appear here.' ),
	false !== strpos( $bare, 'Recently decided' ),
	false !== strpos( $bare, 'No application has been decided yet.' ),
), array( true, true, true ) );

$open_id = seed_sponsor_application( 'Gadgetry Inc', 'new', array( 'in-base' ), array( 'colour' => 640, 'white' => 641 ) );
$held_id = seed_sponsor_application( 'Held Co', 'held', array( 'links', 'duplicate' ) );
$done_id = seed_sponsor_application( 'Done Co', 'rejected' );

// The bubble was just cached at zero by the "nothing waiting" check above; this stub's
// add_action() is a no-op, so none of the three real hooks fires when wp_insert_post() and
// update_post_meta() seed the fixture above, the way they would on a real site. Forgotten by
// hand so this check reads the fixture just seeded, not the count from before it existed.
WPCPM_Sponsors::forget_attention();
ck( 'the bubble counts the applications waiting', array( false !== strpos( $module->menu_label(), 'count-2' ), false !== strpos( $module->menu_label(), 'pending-count">2<' ) ), array( true, true ) );
ck( 'and the screen\'s sentences include the decisions\'', isset( WPCPM_Sponsors::messages()['sapp-approved'] ), true );

// The application's one-shot detail is printed in its own sentence on the queue tab, and only under
// the status it was tagged to. A detail the accounts list left on its own channel is not printed
// under an application's status, and is gone after that page, so it cannot surface under a later one.
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sapp-account' );
WPCPM_Flash::set( WPCPM_Sponsor_Application::FLASH_DETAIL, array( 'status' => 'sapp-account', 'detail' => 'Sorry, that username already exists!' ) );
$account_failed = render_tab( 'queue' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sapp-approved' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH_DETAIL, array( 'status' => 'provision-failed', 'created' => 0, 'attached' => 0, 'airtable' => 0, 'left' => 0, 'limit' => 10, 'failed' => array( 'Left Behind Co: That account belongs to a student' ), 'failed_more' => 0 ) );
$approved_page = render_tab( 'queue' );
ck( 'on the queue tab, sapp-account prints the detail it carried in its own sentence, once, above the bar', array(
	notices_in( $account_failed ),
	(int) strpos( $account_failed, 'is-dismissible' ) < (int) strpos( $account_failed, '<nav class="nav-tab-wrapper' ),
), array( '<div class="notice notice-error is-dismissible"><p>The Airtable record was created, but the account could not be made. The site said: Sorry, that username already exists! Press Approve again once that is fixed.</p></div>', true ) );
ck( 'and a detail the accounts list left on its channel is not printed under an application\'s status, nor kept for a later page', array(
	notices_in( $approved_page ),
	strpos( $approved_page, 'Left Behind Co' ),
	isset( $GLOBALS['flash'][ WPCPM_Sponsors::FLASH_DETAIL ] ),
), array( '<div class="notice notice-success is-dismissible"><p>The application is approved. The Airtable record, the account, the category, the logo and the first offer are in place, and the welcome is queued.</p></div>', false, false ) );

$screen = render_screen();

ck( 'the screen\'s own address draws the queue tab\'s three cards, the applications first, with their count and the two open rows, oldest first', array(
	cards_of( $screen ),
	false !== strpos( $screen, 'Sponsor applications <span class="wpcpm-count">2</span>' ),
	strpos( $screen, 'Gadgetry Inc' ) < strpos( $screen, 'Held Co' ),
	// The decided row is on the screen, but under "Recently decided"
	// and never in the queue itself: the count above is the queue's and stays at two.
	strpos( $screen, 'Done Co' ) > strpos( $screen, 'Recently decided' ),
), array( array( 'Sponsor applications', 'Sponsor posts to review', 'Signed agreements' ), true, true, true ) );
ck( 'a held row says it was held and how many checks held it, the duplicate mark aside', array( false !== strpos( $screen, 'wpcpm-inst-mark--held' ), false !== strpos( $screen, '1 check held it' ) ), array( true, true ) );
ck( 'the two duplicate marks are drawn where they apply', array( false !== strpos( $screen, 'possible duplicate' ), false !== strpos( $screen, 'already in the base' ) ), array( true, true ) );
ck( 'each row opens itself on this screen', false !== strpos( $screen, 'wpcpm_sapp_id=' . $open_id ), true );
ck( 'and nothing on the queue is a form', strpos( $screen, 'value="wpcpm_sapp_approve"' ), false );

$opened = render_screen( array( 'wpcpm_sapp_id' => $open_id ) );

ck( 'the opened application is drawn and the queue card is not: one card, on the queue tab, with the six columns and their answers', array(
	false !== strpos( $opened, 'id="wpcpm-sponsor-application"' ),
	strpos( $opened, 'id="wpcpm-sponsor-applications"' ),
	substr_count( $opened, '<div class="wpcpm-card' ),
	bar_of( $opened ) === $bar_for( 'queue' ),
	substr_count( $opened, '<code class="wpcpm-inst-record">' ) >= 8,
	false !== strpos( $opened, 'Sponsor mentors + tools/services' ),
	false !== strpos( $opened, 'We make gadgets.' ),
), array( true, false, 1, true, true, true, true ) );
$back = preg_match( '#<a href="([^"]*)">Back to the queue</a>#', $opened, $found ) ? html_entity_decode( $found[1], ENT_QUOTES, 'UTF-8' ) : '';
ck( 'its way back is the screen\'s own address at the queue card, which opens the queue tab', array( $back, cards_of( render_screen( link_of( $back )[1] ) ) ), array( $home . '#wpcpm-sponsor-applications', array( 'Sponsor applications', 'Sponsor posts to review', 'Signed agreements' ) ) );
ck( 'the consent evidence is one sentence naming the policy and its version', array( false !== strpos( $opened, 'Agreed 2026-09-01' ), false !== strpos( $opened, 'https://example.test/privacy/' ), false !== strpos( $opened, '2026-08-20 11:30:00' ) ), array( true, true, true ) );
ck( 'the two logos are shown from the Media Library', array( false !== strpos( $opened, 'data-id="640"' ), false !== strpos( $opened, 'data-id="641"' ), false !== strpos( $opened, 'In white' ) ), array( true, true, true ) );
ck( 'what the base already has: the row that matches by website, with its record and its status', array( false !== strpos( $opened, 'What the base already has' ), false !== strpos( $opened, 'recSPONSORTEST002' ), false !== strpos( $opened, 'creates a second record' ) ), array( true, true, true ) );
ck( 'and for the same company, the way to its account is Create account on this screen\'s Accounts tab', array( false !== strpos( $opened, 'use Create account on the Sponsors screen&#039;s Accounts tab instead.' ), strpos( $opened, 'Sponsors card' ) ), array( true, false ) );
ck( 'the checks are printed, and the in-base one in words', false !== strpos( $opened, 'already holds a sponsor with this name or website' ), true );
// The Administrator Dashboard's card lists the oldest open applications, and this one among them, so
// it is decided there: the opened application draws no decision, says so under one heading, and links
// to its own item on that card.
ck( 'an open application the dashboard\'s card lists is decided there: no decision is drawn, one heading says where, and the way to its own item there', array(
	substr_count( $opened, '<form' ),
	strpos( $opened, 'nonce-wpcpm_sapp_approve_' . $open_id ),
	strpos( $opened, 'What happens next' ),
	false !== strpos( $opened, '<h3>Where it is decided</h3><p>Applications are decided on the Administrator Dashboard. This one is listed in its Sponsor applications card, with every decision its state allows.</p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-application-' . $open_id . '">Open on the Administrator Dashboard</a></p>' ),
	substr_count( $opened, 'Open on the Administrator Dashboard' ),
), array( 0, false, false, true, 1 ) );
ck( 'and the answers never print the applicant\'s address unescaped or a nonce for another row', array( false !== strpos( $opened, 'maciej@a8c.com' ), strpos( $opened, 'nonce-wpcpm_sapp_approve_' . $held_id ) ), array( true, false ) );

echo "\n=== The queue marks a contact address that already belongs to an account ===\n";
// A dedicated user and a dedicated application, at an address none of the fixture's other
// accounts holds, so the mark below is read off this one application rather than off whatever
// this file's own account churn happened to leave sitting at maciej@a8c.com.
$GLOBALS['users'][95] = new WP_User( 95, array( 'wpcpm_mentor' ), 'Existing Owner', 'existing-owner@example.test' );
$conflict_id = seed_sponsor_application( 'Conflict Co', 'new' );
update_post_meta( $conflict_id, WPCPM_Sponsor_Application::META_FIELDS, array_merge( get_post_meta( $conflict_id, WPCPM_Sponsor_Application::META_FIELDS, true ), array( 'Contact Email' => 'existing-owner@example.test' ) ) );
$with_conflict = render_screen();
ck( 'the queue marks a contact address that already belongs to an account', false !== strpos( $with_conflict, 'already an account' ), true );
ck( 'with the account modifier on the mark', false !== strpos( $with_conflict, 'wpcpm-inst-mark--account' ), true );

$decided = render_screen( array( 'wpcpm_sapp_id' => $done_id ) );
ck( 'a decided application offers the way back and the deletion', array( false !== strpos( $decided, 'value="wpcpm_sapp_reopen"' ), false !== strpos( $decided, 'value="wpcpm_sapp_purge"' ), strpos( $decided, 'value="wpcpm_sapp_approve"' ) ), array( true, true, false ) );

echo "\n=== A half-done approval is marked wherever the row is drawn ===\n";
// The record stamped with the row still open is what `WPCPM_Sponsor_Approval::is_half_done()`
// reads, and the real class is loaded in this file: the mark is read off the condition itself.
update_post_meta( $held_id, WPCPM_Sponsor_Application::META_RECORD, 'recSPONSORTEST003' );
// The opened application is drawn in the queue card's place, so its mark and its row's are read on
// two draws: the application opened, and the queue.
$half     = render_screen( array( 'wpcpm_sapp_id' => $held_id ) );
$half_row = render_screen();
ck( 'the mark is on the open application and on its queue row, once on each, in the same words', array(
	substr_count( $half, 'wpcpm-inst-mark--half-done' ),
	substr_count( $half, 'approval half done' ),
	false !== strpos( $half, 'Press Approve again to finish.' ),
	substr_count( $half_row, 'wpcpm-inst-mark--half-done' ),
	substr_count( $half_row, 'approval half done' ),
), array( 1, 1, true, 1, 1 ) );
ck( 'and no other row carries it', substr_count( $half_row, 'wpcpm_sapp_id=' . $open_id ) > 0 && 1 === substr_count( $half_row, 'wpcpm-inst-mark--half-done' ), true );
delete_post_meta( $held_id, WPCPM_Sponsor_Application::META_RECORD );

echo "\n=== Recently decided: the closed list under the queue ===\n";
// Two of the six decisions, Put back in the queue and Delete for good, are offered on a
// decided application alone, and until this list existed nothing on either surface listed
// one: they were reachable only by typing ?wpcpm_sapp_id= by hand, so a genuine application
// the checks filed as spam was seen by nobody. The three decided states are seeded with their
// own decision times, because the list is ordered by the decision and not by the row's age,
// and one open row is seeded beside them to prove it stays out of the list.

/**
 * The event row `decided_at()` reads and the meta `decided_posts()` sorts by, so the closed
 * list's order can be set on purpose (both stamped together since 1.98.1, as `add_event()` does).
 */
function decided_on( $id, $at ) {
	update_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT, array( array( 'event' => 'decided', 'at' => (int) $at, 'actor' => 3, 'note' => '' ) ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_DECIDED, (int) $at );
}

$spam_id     = seed_sponsor_application( 'Spammy Co', 'spam' );
$rejected_id = seed_sponsor_application( 'Rejected Co', 'rejected' );
$approved_id = seed_sponsor_application( 'Approved Ltd', 'approved' );
$waiting_id  = seed_sponsor_application( 'Waiting Co', 'new' );

// Deliberately not in ID order: a list that followed the posts rather than the decisions
// would come out as Done, Spammy, Rejected, Approved and pass nothing below.
decided_on( $done_id, 1788000000 );
decided_on( $spam_id, 1788200000 );
decided_on( $rejected_id, 1788100000 );
decided_on( $approved_id, 1788300000 );

$screen = render_screen();
$split  = (int) strpos( $screen, 'wpcpm-sapp-decided' );
$queued = substr( $screen, 0, $split );
$closed = substr( $screen, $split );

// The card's own body: from its id to the first tag that closes a div, which is its own.
$card = substr( $screen, (int) strpos( $screen, 'id="wpcpm-sponsor-applications"' ) );
$card = substr( $card, 0, (int) strpos( $card, '</div>' ) );
ck( 'the closed list is inside the queue\'s own card, after the queue itself', array(
	false !== strpos( $card, '<section class="wpcpm-sapp-decided">' ),
	strpos( $card, '<section class="wpcpm-sapp-decided">' ) > strpos( $card, '</ol>' ),
	false !== strpos( $card, '<h3>Recently decided</h3>' ),
), array( true, true, true ) );
ck( 'it lists the three decided states and nothing else, newest decision first', array(
	strpos( $closed, 'Approved Ltd' ) < strpos( $closed, 'Spammy Co' ),
	strpos( $closed, 'Spammy Co' ) < strpos( $closed, 'Rejected Co' ),
	strpos( $closed, 'Rejected Co' ) < strpos( $closed, 'Done Co' ),
), array( true, true, true ) );
ck( 'the open row is in the queue and never in the list under it', array( false !== strpos( $queued, 'Waiting Co' ), strpos( $closed, 'Waiting Co' ) ), array( true, false ) );
// An open row ends with the way to its own item on the Administrator Dashboard's card, as a post and
// a document do; a decided row draws none, since that card lists open applications only.
ck( 'the waiting row ends with the way to its own item on the Administrator Dashboard, and no decided row carries one', array(
	false !== strpos( $queued, '<span class="wpcpm-inst-muted">new</span></p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-application-' . $waiting_id . '">Open on the Administrator Dashboard</a></p></li>' ),
	substr_count( $closed, '#wpcpm-sponsor-application-' ),
), array( true, 0 ) );
ck( 'every decided row carries its Open link, so both decisions can be reached by pressing', array(
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $spam_id ),
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $rejected_id ),
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $approved_id ),
	false !== strpos( $closed, 'Open this application' ),
), array( true, true, true, true ) );
ck( 'and its state, in the words the screens use', array(
	false !== strpos( $closed, 'marked as spam' ),
	false !== strpos( $closed, '>rejected<' ),
	false !== strpos( $closed, '>approved<' ),
), array( true, true, true ) );
ck( 'a decided row says when it was decided; only a waiting one says how long it has waited', array(
	false !== strpos( $closed, 'Decided 4 hours ago, on 2026-09-01 22:00' ),
	strpos( $closed, 'Waiting 4 hours' ),
	false !== strpos( $queued, 'Waiting 4 hours' ),
), array( true, false, true ) );
// The cap is a constant, so the count is asserted against it rather than by lowering it: four
// decided rows are drawn because four is under `QUEUE_MAX`, and the slice that enforces it is
// read off the source.
ck( 'the list draws every decided row up to the queue\'s own cap', array(
	substr_count( $closed, 'class="wpcpm-queue-item"' ),
	min( 4, WPCPM_Sponsor_Application::QUEUE_MAX ),
	false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-application.php' ), 'array_slice( $rows, 0, self::QUEUE_MAX )' ),
), array( 4, 4, true ) );

$wrong = render_screen( array( 'wpcpm_sapp_id' => 42 ) );
ck( 'a post that is not an application opens nothing, and the queue tab\'s cards are drawn in its place', array( strpos( $wrong, 'id="wpcpm-sponsor-application"' ), cards_of( $wrong ) ), array( false, array( 'Sponsor applications', 'Sponsor posts to review', 'Signed agreements' ) ) );
$GLOBALS['get'] = array();

$screen_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'the bubble reads three queues behind guards', substr_count( method_body( $screen_src, 'attention_count' ), 'class_exists(' ) >= 3, true );

echo "\n=== The queue tab: the applications, the sponsor posts and the signed agreements, read here and decided on the Administrator Dashboard ===\n";
// The three kinds a manager decides on the Administrator Dashboard, each in a card of its own, in the
// dashboard's order. Each card counts everything waiting, lists no more than the dashboard's own card
// does, so that no row links to an item the dashboard does not draw, and says so when it lists fewer;
// each item links to its own item there.
$dashboard = 'https://example.test/administrator-dashboard/';
$post_row  = static function ( $id, $title, $author, $preview ) {
	return array( 'id' => $id, 'title' => $title, 'record' => 'recSPONSORTEST001', 'company' => 'TEST Sponsor', 'author' => $author, 'at' => time() - 3600, 'preview' => $preview );
};
$agr_facts = static function ( $id ) use ( $T ) {
	return array( 'post_id' => $id, 'state' => 'submitted', 'kind' => 'own', 'sponsor' => $T, 'sponsor_name' => 'TEST Sponsor', 'uploaded_by' => 'Member One', 'uploaded_at' => '2026-09-05', 'original_name' => 'agreement.pdf', 'flags' => array(), 'size' => 20480, 'members' => 2 );
};
$GLOBALS['pending_posts'] = array(
	$post_row( 905, 'Ten tips', 'Member One', 'https://example.test/?p=905&preview=true' ),
	$post_row( 906, 'Untitled thoughts <b>', '', '' ),
	array_merge( $post_row( 907, 'Notes from the field', '<b>Ann</b>', '' ), array( 'company' => '<b>Co</b>' ) ),
);
$GLOBALS['queue'] = array( 910 );
$GLOBALS['facts'] = array( 910 => $agr_facts( 910 ) );

$queue_tab  = render_tab( 'queue' );
$posts_card = card_body( $queue_tab, 'wpcpm-sponsor-posts' );
$agr_card   = card_body( $queue_tab, 'wpcpm-sponsor-agreements' );

ck( 'the queue tab draws three cards in the Administrator Dashboard\'s order, each with its id and its count of everything waiting', counted_cards_of( $queue_tab ), array(
	array( 'wpcpm-sponsor-applications', 'Sponsor applications', (string) count( WPCPM_Sponsor_Application::applications( WPCPM_Sponsor_Application::open_states() ) ) ),
	array( 'wpcpm-sponsor-posts', 'Sponsor posts to review', '3' ),
	array( 'wpcpm-sponsor-agreements', 'Signed agreements', '1' ),
) );
ck( 'the posts card says what it lists and where each post is decided', false !== strpos( $posts_card, '<p class="description">Posts that sponsors wrote and submitted for review, oldest first. A post is published or returned on the Administrator Dashboard.</p>' ), true );
ck( 'a post\'s row: its title, its sponsor and its author, how long ago it was submitted, Preview, and the way to its own item on the Administrator Dashboard', false !== strpos( $posts_card, '<ol class="wpcpm-queue"><li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Ten tips</h3><p>From TEST Sponsor, by Member One. Submitted 4 hours ago.</p><p><a class="button" href="https://example.test/?p=905&preview=true">Preview</a></p><p><a href="' . $dashboard . '#wpcpm-sponsor-post-905">Open on the Administrator Dashboard</a></p></li>' ), true );
ck( 'a row with no preview prints no Preview, its title is escaped, and an author whose account is gone is said to be one', array(
	false !== strpos( $posts_card, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Untitled thoughts &lt;b&gt;</h3><p>From TEST Sponsor, by somebody whose account is gone. Submitted 4 hours ago.</p><p><a href="' . $dashboard . '#wpcpm-sponsor-post-906">Open on the Administrator Dashboard</a></p></li>' ),
	substr_count( $posts_card, '>Preview</a>' ),
), array( true, 1 ) );
ck( 'and a sponsor\'s name and an author\'s name are escaped as the title is', false !== strpos( $posts_card, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Notes from the field</h3><p>From &lt;b&gt;Co&lt;/b&gt;, by &lt;b&gt;Ann&lt;/b&gt;. Submitted 4 hours ago.</p>' ), true );
ck( 'the agreements card says what it lists and where each document is decided, then each document\'s review block, with the way to its own item on the Administrator Dashboard in the place of its decisions', array(
	false !== strpos( $agr_card, '<p class="description">Signed agreements that companies uploaded and that wait to be read, oldest first. Each is accepted or returned on the Administrator Dashboard.</p><section class="wpcpm-review" id="wpcpm-review-910">' ),
	false !== strpos( $agr_card, '<p class="wpcpm-review__open"><a href="' . $dashboard . '#wpcpm-sponsor-agreement-910">Open on the Administrator Dashboard</a></p></section>' ),
), array( true, true ) );
ck( 'nothing on the queue tab is a form, and no card says it lists fewer than wait while it lists them all', array( substr_count( $queue_tab, '<form' ), strpos( $queue_tab, 'Showing the oldest' ) ), array( 0, false ) );

// One more of each than the dashboard's cards list.
$GLOBALS['pending_posts'] = array();
$GLOBALS['queue']         = array();
$GLOBALS['facts']         = array();
for ( $i = 0; $i <= WPCPM_Administrators_Cards::LIMIT; $i++ ) {
	$GLOBALS['pending_posts'][]    = $post_row( 2000 + $i, 'Post ' . $i, 'Member One', '' );
	$GLOBALS['queue'][]            = 3000 + $i;
	$GLOBALS['facts'][ 3000 + $i ] = $agr_facts( 3000 + $i );
}
$full        = render_tab( 'queue' );
$full_posts  = card_body( $full, 'wpcpm-sponsor-posts' );
$full_agr    = card_body( $full, 'wpcpm-sponsor-agreements' );
$limit       = WPCPM_Administrators_Cards::LIMIT;
$window_said = '<p class="description">Showing the oldest ' . $limit . ' of ' . ( $limit + 1 ) . '. As these are decided on the Administrator Dashboard, the next of them take their place.</p>';
ck( 'past the dashboard\'s window each card counts every one waiting, lists as many as the dashboard\'s card does, oldest first, under the sentence that says so, and links none it does not list', array(
	array_column( array_slice( counted_cards_of( $full ), 1 ), 2 ),
	substr_count( $full_posts, '<li class="wpcpm-queue-item">' ),
	substr_count( $full_agr, '<section class="wpcpm-review"' ),
	false !== strpos( $full_posts, 'Administrator Dashboard.</p>' . $window_said . '<ol class="wpcpm-queue">' ),
	false !== strpos( $full_agr, 'Administrator Dashboard.</p>' . $window_said . '<section class="wpcpm-review" id="wpcpm-review-3000">' ),
	false !== strpos( $full_posts, '#wpcpm-sponsor-post-' . ( 2000 + $limit - 1 ) . '"' ) && false !== strpos( $full_agr, '#wpcpm-sponsor-agreement-' . ( 3000 + $limit - 1 ) . '"' ),
	strpos( $full_posts, '#wpcpm-sponsor-post-' . ( 2000 + $limit ) . '"' ),
	strpos( $full_agr, '#wpcpm-sponsor-agreement-' . ( 3000 + $limit ) . '"' ),
), array( array( (string) ( $limit + 1 ), (string) ( $limit + 1 ) ), $limit, $limit, true, true, true, false, false ) );

// The posts listed are the dashboard card's own read, not the first of a longer one: the read applies
// its limit before it leaves out a post whose sponsor stamp is no record, so the dashboard can draw one
// fewer than its number, and a list cut from the longer read would link to a post the card does not draw.
$GLOBALS['pending_posts'] = array();
for ( $i = 0; $i <= $limit; $i++ ) {
	$GLOBALS['pending_posts'][] = array_merge( $post_row( 4000 + $i, 'Post ' . $i, 'Member One', '' ), 0 === $i ? array( 'dropped' => true ) : array() );
}
$dropped_card = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-posts' );
ck( 'a post the read leaves out under the dashboard\'s number shortens the list as it shortens the card there, and nothing past the card is linked', array(
	array_column( array_slice( counted_cards_of( render_tab( 'queue' ) ), 1, 1 ), 2 ),
	substr_count( $dropped_card, '<li class="wpcpm-queue-item">' ),
	false !== strpos( $dropped_card, '#wpcpm-sponsor-post-' . ( 4000 + $limit - 1 ) . '"' ),
	strpos( $dropped_card, '#wpcpm-sponsor-post-' . ( 4000 + $limit ) . '"' ),
	false !== strpos( $dropped_card, 'Showing the oldest ' . ( $limit - 1 ) . ' of ' . $limit . '. As these are decided' ),
), array( array( (string) $limit ), $limit - 1, true, false, true ) );

// One shown of more: the line in the singular.
$GLOBALS['pending_posts'] = array();
for ( $i = 0; $i <= $limit; $i++ ) {
	$GLOBALS['pending_posts'][] = array_merge( $post_row( 4000 + $i, 'Post ' . $i, 'Member One', '' ), $i < $limit - 1 ? array( 'dropped' => true ) : array() );
}
$one_card = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-posts' );
ck( 'with one shown of two, the window line says so in the singular', array(
	false !== strpos( $one_card, '<p class="description">Showing the oldest 1 of 2. As this one is decided on the Administrator Dashboard, the next takes its place.</p>' ),
	substr_count( $one_card, '<li class="wpcpm-queue-item">' ),
), array( true, 1 ) );

// The counts stop where the menu bubble's does, and say so in its words past it: each card reads one
// more than COUNT_MAX, so it can tell a full ceiling from a count that reached it.
$at_ceiling = static function ( $how_many ) use ( $post_row, $agr_facts ) {
	$GLOBALS['pending_posts'] = array();
	$GLOBALS['queue']         = array();
	$GLOBALS['facts']         = array();
	for ( $i = 0; $i < $how_many; $i++ ) {
		$GLOBALS['pending_posts'][]    = $post_row( 5000 + $i, 'Post ' . $i, 'Member One', '' );
		$GLOBALS['queue'][]            = 6000 + $i;
		$GLOBALS['facts'][ 6000 + $i ] = $agr_facts( 6000 + $i );
	}
	return render_tab( 'queue' );
};
$full_ceiling = $at_ceiling( WPCPM_Sponsors::COUNT_MAX );
$past_ceiling = $at_ceiling( WPCPM_Sponsors::COUNT_MAX + 1 );
$ceiling      = WPCPM_Sponsors::COUNT_MAX;
ck( 'at the ceiling the two cards count it as it is; past it they print the bubble\'s "200+", in the heading and in the window line', array(
	array_column( array_slice( counted_cards_of( $full_ceiling ), 1 ), 2 ),
	substr_count( $full_ceiling, 'Showing the oldest ' . $limit . ' of ' . $ceiling . '. As these are decided' ),
	array_column( array_slice( counted_cards_of( $past_ceiling ), 1 ), 2 ),
	substr_count( $past_ceiling, 'Showing the oldest ' . $limit . ' of ' . $ceiling . '+. As these are decided' ),
), array( array( (string) $ceiling, (string) $ceiling ), 2, array( $ceiling . '+', $ceiling . '+' ), 2 ) );
$GLOBALS['pending_posts'] = array();
$GLOBALS['queue']         = array();
$GLOBALS['facts']         = array();
for ( $i = 0; $i <= $limit; $i++ ) {
	$GLOBALS['pending_posts'][]    = $post_row( 2000 + $i, 'Post ' . $i, 'Member One', '' );
	$GLOBALS['queue'][]            = 3000 + $i;
	$GLOBALS['facts'][ 3000 + $i ] = $agr_facts( 3000 + $i );
}

// While the dashboard's page is missing there is nowhere to link: the dashboard class's own sentence,
// once, above the three cards, and no card or row prints a link. An opened application says it in its
// own place instead, as it is drawn in the cards' place.
WPCPM_Administrators_Dashboard::$url = '';
$no_page     = render_tab( 'queue' );
$opened_gone = render_screen( array( 'wpcpm_sapp_id' => $open_id ) );
WPCPM_Administrators_Dashboard::$url = $dashboard;
$warned_at   = strpos( $no_page, '<p class="wpcpm-warning">The dashboard class says its page is missing.</p>' );
ck( 'while the dashboard\'s page is missing the queue tab says so once, under the bar and above its three cards, and no card or row links there', array(
	substr_count( $no_page, 'The dashboard class says its page is missing.' ),
	false !== $warned_at && $warned_at > (int) strpos( $no_page, '</nav>' ) && $warned_at < (int) strpos( $no_page, '<div class="wpcpm-card' ),
	substr_count( $no_page, 'Open on the Administrator Dashboard' ),
	array_column( counted_cards_of( $no_page ), 0 ),
	substr_count( card_body( $no_page, 'wpcpm-sponsor-posts' ), '<li class="wpcpm-queue-item">' ),
	substr_count( card_body( $no_page, 'wpcpm-sponsor-agreements' ), '<section class="wpcpm-review"' ),
), array( 1, true, 0, array( 'wpcpm-sponsor-applications', 'wpcpm-sponsor-posts', 'wpcpm-sponsor-agreements' ), $limit, $limit ) );
ck( 'and an opened application says it once, in its own place, with no link', array( substr_count( $opened_gone, 'The dashboard class says its page is missing.' ), substr_count( $opened_gone, 'Open on the Administrator Dashboard' ), cards_of( $opened_gone ) ), array( 1, 0, array( 'Gadgetry Inc SAPP-2026-' . sprintf( '%04d', $open_id ) ) ) );

$GLOBALS['pending_posts'] = array();
$GLOBALS['queue']         = array();
$GLOBALS['facts']         = array();
$empty_tab = render_tab( 'queue' );
ck( 'with nothing waiting each of the two cards says so, counts nothing and lists nothing', array(
	false !== strpos( card_body( $empty_tab, 'wpcpm-sponsor-posts' ), '<p>Nothing is waiting.</p>' ),
	false !== strpos( card_body( $empty_tab, 'wpcpm-sponsor-agreements' ), '<p>Nothing is waiting to be read.</p>' ),
	substr_count( card_body( $empty_tab, 'wpcpm-sponsor-posts' ) . card_body( $empty_tab, 'wpcpm-sponsor-agreements' ), '<ol' ),
	array_column( array_slice( counted_cards_of( $empty_tab ), 1 ), 2 ),
), array( true, true, 0, array( '0', '0' ) ) );

echo "\n=== Each tab draws its own cards and reads only what it draws ===\n";
// Every card's class is loaded by now, the queue's among them, so the six tabs are read once more.
$all   = array();
$reads = array();
foreach ( $slugs as $slug ) {
	$all[ $slug ]   = render_tab( $slug );
	$reads[ $slug ] = reads_now();
}
ck( 'each tab draws the cards of its job and none of another tab\'s: the applications, the posts and the signed agreements waiting only on Waiting for review, the sync and the index only on Sponsors, the offers only on Offers and codes', array_map( 'cards_of', $all ), array(
	'queue'      => array( 'Sponsor applications', 'Sponsor posts to review', 'Signed agreements' ),
	'sponsors'   => array( 'Airtable sync', 'Sponsors' ),
	'accounts'   => array( 'Invitations', 'Sponsor accounts' ),
	'offers'     => array( 'Offers and codes' ),
	'interests'  => array( 'Interests' ),
	'agreements' => array( 'Agreements' ),
) );
$read = static function ( $index, $progress, $last, $accounts, $applications, $posts, $documents, $out_of_force ) {
	return array(
		'index'             => $index,
		'progress'          => $progress,
		'last read'         => $last,
		'accounts'          => $accounts,
		'applications'      => $applications,
		'posts'             => $posts,
		'waiting documents' => $documents,
		'out of force'      => $out_of_force,
	);
};
// The fixture's TEST Sponsor is out of force here, so the Agreements tab reads the documents out of
// force for its block, and no other tab does.
ck( 'each tab reads only what it draws: the applications, the posts and the documents waiting on the queue alone, the documents out of force on Agreements alone, the index on the five tabs that draw from it, the sync\'s progress and last read on Sponsors alone, the accounts by sponsor where they are counted or listed', $reads, array(
	'queue'      => $read( false, false, false, false, true, true, true, false ),
	'sponsors'   => $read( true, true, true, true, false, false, false, false ),
	'accounts'   => $read( true, false, false, true, false, false, false, false ),
	'offers'     => $read( true, false, false, true, false, false, false, false ),
	'interests'  => $read( true, false, false, false, false, false, false, false ),
	'agreements' => $read( true, false, false, false, false, false, false, true ),
) );
// An open application the dashboard's card lists draws no form here, so the decisions an opened
// application still draws are read on a decided one: Put back in the queue and Delete for good.
$opened_decided = render_screen( array( 'wpcpm_sapp_id' => $spam_id ) );
ck( 'and every form a tab posts carries the once attribute, the record-keeping an opened application keeps here among them', array(
	array_map(
		static function ( $html ) {
			return post_forms( without_invitations( $html ), true );
		},
		array_merge( $all, array( 'opened' => $opened_decided ) )
	),
	post_forms( $opened_decided ),
), array( array_fill_keys( array_merge( $slugs, array( 'opened' ) ), array() ), array( 'wpcpm_sapp_reopen', 'wpcpm_sapp_purge' ) ) );

echo "\n=== The menu bubble is cached for a minute (1.98.1) ===\n";
$GLOBALS['transients'] = array();
$GLOBALS['queries']    = 0;
$first  = WPCPM_Sponsors::attention_count();
$after_first = (int) $GLOBALS['queries'];
$second = WPCPM_Sponsors::attention_count();
ck( 'the second call within a minute reads the transient and runs no query: the first counted, the second added nothing', array( $first === $second, $after_first > 0, (int) $GLOBALS['queries'] - $after_first, isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] ), $GLOBALS['transients']['wpcpm_sponsors_attention']['ttl'] ), array( true, true, 0, true, 60 ) );
WPCPM_Sponsors::forget_attention();
ck( 'forgetting drops the transient, so the next page counts again', isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] ), false );
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'a state change forgets the bubble: the three hooks are registered', array( substr_count( $src, "add_action( 'transition_post_status', array( __CLASS__, 'forget_attention_on_status' )" ), substr_count( $src, "add_action( 'updated_post_meta', array( __CLASS__, 'forget_attention_on_meta' )" ), substr_count( $src, "add_action( 'added_post_meta', array( __CLASS__, 'forget_attention_on_meta' )" ), substr_count( $src, "delete_transient( self::TRANSIENT_ATTENTION )" ) >= 2 ), array( 1, 1, 1, true ) );

echo "\n=== The retention run is on the clock ===\n";
$GLOBALS['cron'] = array();
WPCPM_Sponsors::schedule_cron();
ck( 'schedule_cron() puts the application purge on the clock beside the agreement discard, daily, nine hours out', array( isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ), isset( $GLOBALS['cron']['wpcpm_sponsor_agreement_discard'] ), $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] - time() > 8 * HOUR_IN_SECONDS ), array( true, true, true ) );
$before = $GLOBALS['cron']['wpcpm_purge_sponsor_applications'];
WPCPM_Sponsors::schedule_cron();
ck( 'and a second call leaves a job already scheduled alone', $GLOBALS['cron']['wpcpm_purge_sponsor_applications'], $before );
$sponsors_src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$deactivate   = substr( $sponsors_src, (int) strpos( $sponsors_src, 'public function deactivate()' ) );
$deactivate   = substr( $deactivate, 0, (int) strpos( $deactivate, "\n\t}\n" ) );
ck( 'and deactivation takes the purge off the clock beside the discard', array( substr_count( $deactivate, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE )' ), substr_count( $deactivate, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Agreement::CRON_DISCARD )' ) ), array( 1, 1 ) );

echo "\n=== Uninstall and deactivation take the applications with them ===\n";
$GLOBALS['deleted_attachments'] = array();
$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_PAGE ] = 77;
$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_LOG ]  = array( array( 'at' => 1, 'id' => 1, 'reference' => 'SAPP-2026-0001', 'state' => 'spam', 'days' => 30, 'actor' => 0 ) );
$kept_id = seed_sponsor_application( 'Approved Co', 'approved', array(), array( 'colour' => 700, 'white' => 0 ) );
$gone_id = seed_sponsor_application( 'Open Co', 'new', array(), array( 'colour' => 701, 'white' => 702 ) );
WPCPM_Sponsors::schedule_cron();
$module->deactivate();
ck( 'deactivation takes the purge off the clock', isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ), false );
WPCPM_Sponsors::schedule_cron();
$module->uninstall();
ck( 'uninstall deletes every application, whatever its state', array( get_post( $open_id ), get_post( $held_id ), get_post( $done_id ), get_post( $kept_id ), get_post( $gone_id ) ), array( null, null, null, null, null ) );
ck( 'and the files of the ones nobody approved, never an approved one\'s', array( in_array( 640, $GLOBALS['deleted_attachments'], true ), in_array( 701, $GLOBALS['deleted_attachments'], true ), in_array( 702, $GLOBALS['deleted_attachments'], true ), in_array( 700, $GLOBALS['deleted_attachments'], true ) ), array( true, true, true, false ) );
ck( 'the page option and the log go, and the purge is off the clock', array( get_option( WPCPM_Sponsor_Application::OPT_PAGE ), get_option( WPCPM_Sponsor_Application::OPT_LOG ), isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ) ), array( false, false, false ) );

$sponsors_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'uninstall() reaches the approval\'s lock sweep behind a guard, and clears the purge hook', array( false !== strpos( method_body( $sponsors_src, 'uninstall' ), 'WPCPM_Sponsor_Approval::delete_all()' ), substr_count( $sponsors_src, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE )' ) ), array( true, 2 ) );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'accounts come from WPCPM_Roles::insert_user() alone', strpos( $src, 'wp_insert_user(' ), false );
ck( 'activate() sets the repair flag itself, so a fresh install never queries for accounts it cannot have', false !== strpos( method_body( $src, 'activate' ), 'update_option( WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED, 1, true )' ), true );

ck( 'and the accounts list asked the meta matcher nothing it does not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
