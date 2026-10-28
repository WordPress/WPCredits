<?php
/**
 * The Institutions manager screen, the private-files probe and notify_managers().
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The screen reads the pipeline index, the roster counts, the countries map and the
 *   per-institution agreement summaries, never Airtable. Rendered here against the seed
 *   fixture with every other piece stubbed to its contract, so the group counts, the
 *   agreement-gap count and the consent sentence are the fixture's numbers and nothing else.
 * - Names print trimmed and the row says when the stored one was not; ten names in the base
 *   end in a space and a manager searching the grid should know why a match is not exact.
 * - The Pipeline counts what is in play (1.123.29). SPAM, Not Moving Forward, Revisit Later and no
 *   stage, read trimmed, are left out of every count on the tab and folded under "Not counted
 *   above"; a stage the lists do not know is counted. The Sync and storage tab's member count is
 *   still over every record. Each name opens the institution's page, and one field, Find an
 *   institution, opens any record's, whatever its stage; with no page published there is neither.
 * - The consent card never says "lost". The brief read a date boundary as consent dropping
 *   between form and record; the sentence here is the one the design spec fixes.
 * - Every handler checks the capability before the nonce, asserted by reading the source,
 *   because the order is invisible at runtime and wrong in one place is wrong for everyone.
 * - The probe records what the host does, and the storage card says it in the right words:
 *   a 403 is "blocked", a 200 is the warning naming the directory, a failed request is neither.
 * - notify_managers() reaches every manager when the setting is empty and only the listed
 *   addresses when it is set, through send() for accounts and send_to() for bare addresses.
 * - The review queue is one list of three kinds of row, oldest first, and the menu bubble is
 *   the same number: an application, a signed agreement and a mentor request are one person's
 *   work, and a queue split in three is a queue whose other parts nobody finishes. A request is
 *   overdue by its own fourteen days, the other two by the setting's.
 * - Both are bounded. `/apply` is open to strangers, so a flood is somebody else's decision:
 *   the card draws the oldest `QUEUE_MAX` and says it is doing so, and the bubble stops
 *   counting at `COUNT_MAX` rather than putting the cost of a flood on every admin page.
 * - The queue reads and the Administrator Dashboard decides. No row, and nothing on an opened
 *   open application, draws a decision: every row links to the dashboard's card that decides it,
 *   an opened application says whether that card lists it yet, and while the dashboard's page is
 *   missing the list says so once and links nowhere. The record-keeping on a closed application
 *   stays here, because the dashboard folds in only the oldest fifty rejected and spam ones and
 *   never lists an approved one. A request row speaks by its kind: a mentor request, the details a
 *   school asks to correct (counted with the rest, and decided on the dashboard's card like every
 *   request), and any other. The dashboard's own draw of an application keeps every form it had.
 * - A held row says on the list that it is held, and the application says in plain words
 *   which checks held it. The list is what a manager triages from, so a manager rejecting a
 *   submission the site quietly decided was suspect has to be told that it did, and why.
 * - Nothing on the screen sends a manager to wait for a mail that may never have left: the
 *   address line is the state's own sentence, and a held row gets the one that fits it.
 * - A question that the mail server would not take moves nothing. `info` means "asked, and
 *   waiting on them", and writing it after a failed send invents both halves.
 * - Every decision checks the capability, then a nonce keyed to that application, then the
 *   state, so a stale page refuses rather than acting on a decision somebody already took.
 * - A rejection's acknowledgement carries no reason and a spam mark sends nothing at all;
 *   the reason lives on the application, where only a manager reads it (decision 16).
 * - Deleting keeps a reference, a state and a date, and never an address or a word anybody
 *   wrote, so the log cannot become the copy the retention rule was there to remove; and a
 *   retention setting of 0 means never, which is what the approved default is.
 * - The screen is six tabs by job, the queue first, and the screen's own address is the queue.
 *   Each tab draws its own cards and no other tab's, and asks only what it draws: the membership
 *   counts, a query per institution, on the two tabs that print them, and the provisioning
 *   reasons on the one that lists them. Every form whose press comes back to a tab other than the
 *   queue names it, so the press lands on the tab it was made on, and the screen's one map of
 *   outcomes prints its sentence there; the forms on an opened application name none and come back
 *   to the screen's own address, which is the queue, but for a question or a Reject reason refused
 *   for its own words, which comes back to the application opened.
 * - A question or a Reject reason the cleaner would take words from, or one too long as typed, is
 *   refused before anything is sent or written, rather than cut, in a sentence that names its box,
 *   and the box gets back what was typed, once, for that application and that person alone, on the
 *   Institutions screen and on the Administrator Dashboard. A question is mailed as it was typed.
 * - The Accounts tab draws the accounts locked for the day, the invitations card and the institution
 *   accounts list, and nothing of the provisioning card that list replaced. The list itself, its
 *   No account view and the accounts created from it, and the invitations sent from it, are
 *   bin/test-institutions-accounts.php's.
 *
 * Run from the plugin root:  php bin/test-institutions-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/meta-matcher.php';
require_once __DIR__ . '/stubs/screen-helpers.php';

// Cron, recorded rather than run: activation schedules the ceiling's sweep, and uninstall
// clears it, so both need somewhere to land.
function wp_next_scheduled( $hook ) {
	return $GLOBALS['cron'][ $hook ] ?? false;
}
function wp_schedule_event( $when, $recurrence, $hook ) {
	$GLOBALS['cron'][ $hook ] = (int) $when;
	$GLOBALS['calls'][]       = array( 'schedule', $hook, $recurrence );
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	$GLOBALS['calls'][] = array( 'unschedule', $hook );
	return 1;
}
/** Every event of a hook, whatever its arguments: what uninstall asks of the single events. */
function wp_unschedule_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	$GLOBALS['calls'][] = array( 'unschedule_hook', $hook );
	return 1;
}
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts']    = array();
$GLOBALS['umeta']   = array();
$GLOBALS['users']   = array();
$GLOBALS['manage']  = array();
$GLOBALS['caps']    = true;
$GLOBALS['uid']     = 1;
$GLOBALS['mail']    = array();
$GLOBALS['head']    = array( 'response' => array( 'code' => 403 ) );
$GLOBALS['referer'] = array();
$GLOBALS['calls']   = array();
$GLOBALS['loaded']  = 0;
// Anything asked of a stand-in that it does not model: the meta matcher notes it here.
$GLOBALS['unmodeled'] = array();
// Under this run's own folder (bin/stubs/temp-dir.php), which goes with everything the storage
// card's checks write there when the run ends, however it ends.
$GLOBALS['uploads'] = wpcpm_test_temp_dir() . 'uploads';

// The live membership half of the backstop counts: who acts for each institution, and every
// institution the screen asked about.
$GLOBALS['members_of']   = array();
$GLOBALS['member_reads'] = array();

// How many times one render asked for the sync's progress and for the roster counts: each tab
// reads what it draws, and these two are read for the Sync and storage tab alone.
$GLOBALS['progress_reads'] = 0;
$GLOBALS['counts_reads']   = 0;

// The institution accounts the roster's ceiling has locked for the day: none unless a check
// says otherwise.
$GLOBALS['locked'] = array();

// Translations: none but for the check that the tab bar's words are translated where the bar
// prints them.
$GLOBALS['l10n'] = array();

// Provisioning: why each institution may not have an account, and which ones the screen asked
// about. Creating the accounts is bin/test-institutions-accounts.php's.
$GLOBALS['blocks']      = array();
$GLOBALS['blocks_read'] = array();

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_type = '', $post_status = 'publish', $post_title = '', $post_date_gmt = '', $post_modified_gmt = ''; }
class WP_Role {}

/**
 * The two queries the screen makes: tracked student accounts with no institution stamp, and the
 * Accounts tab's list of institution accounts and its views' counts.
 *
 * Answers over the fixture users the way the real query would: the role, then the `meta_query`
 * read by the one matcher every stand-in user query shares (bin/stubs/meta-matcher.php), which
 * notes any shape it does not model for this suite's last check; IDs when `fields` asks for them,
 * and the accounts otherwise. The args of every call are recorded for the assertions below. The
 * list itself, paged, searched and sorted, is bin/test-institutions-accounts.php's.
 */
class WP_User_Query {
	private $results = array();
	public function __construct( $args = array() ) {
		$GLOBALS['calls'][] = array( 'WP_User_Query', $args );
		$role    = isset( $args['role'] ) ? $args['role'] : '';
		$clauses = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
		$ids     = isset( $args['fields'] ) && 'ID' === $args['fields'];
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( '' !== $role && ! in_array( $role, $user->roles, true ) ) { continue; }
			// `$GLOBALS['ids_as_rows']` answers a request for IDs with rows, as WordPress can (see
			// `WPCPM_Roles::id_of()`).
			if ( wpcpm_stub_meta_matches( (int) $id, $clauses ) ) { $this->results[] = $ids ? ( empty( $GLOBALS['ids_as_rows'] ) ? $id : (object) array( 'ID' => (string) $id ) ) : $user; }
		}
	}
	public function get_results() { return $this->results; }
	public function get_total() { return count( $this->results ); }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $GLOBALS['l10n'][ $s ] ?? $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( __( $s ) ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( __( $s ) ); }
function esc_url( $s ) { return (string) $s; }
// Core's, which escapes every "&" again: a refused question or reason comes back in its box.
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
// Core's two cleaners and its decoder as 7.1.2 writes them, which the typed-text rules read: a
// question or a reason the cleaner would take words from is refused, and one stand-in on
// `strip_tags()` alone writes no `&lt;` and would pass every check made with it.
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function sanitize_email( $e ) { return (string) $e; }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h ); }
function add_filter() {}
// Every option read is journaled by name, so a check can say a load read none it had no need of.
function get_option( $k, $d = false ) { $GLOBALS['option_reads'][] = $k; return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
// Core's reads the option before it writes, and writes nothing over a value already stored.
function add_option( $k, $v, $deprecated = '', $a = null ) {
	$GLOBALS['option_reads'][] = $k;
	$GLOBALS['calls'][]        = array( 'add_option', $k, $a );
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_option', $k, $a ); return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); $GLOBALS['calls'][] = array( 'delete_option', $k ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
// The flash slashes what it writes for core's user meta, which unslashes it; this one keeps what it
// is handed, so the slash is the identity here (bin/test-flash.php holds the flash to core's).
function wp_slash( $v ) { return $v; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function delete_metadata( $type, $id, $key, $value = '', $all = false ) { $GLOBALS['calls'][] = array( 'delete_metadata', $key ); return true; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
	}
	return false;
}
function get_users( $args = array() ) {
	if ( isset( $args['capability'] ) ) {
		$out = array();
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( in_array( $id, $GLOBALS['manage'], true ) ) { $out[] = $user; }
		}
		return $out;
	}
	return array_values( $GLOBALS['users'] );
}
require_once __DIR__ . '/stubs/caps.php';
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function check_ajax_referer( $a = -1, $q = false ) { $GLOBALS['referer'][] = $a; return true; }
function wp_send_json_error( $d = null, $code = null ) { throw new Exception( 'json_error:' . (int) $code ); }
function wp_send_json_success( $d = null ) { $GLOBALS['json'] = $d; throw new Exception( 'json_success' ); }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die: ' . $m ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Both shapes core accepts: a key and a value, and a map of them. The reports card uses the
// map form for the switcher argument, and a stub that only knew the first would drop it and
// let a link that sends a manager to the wrong institution pass.
function add_query_arg( $k, $v = '', $u = '' ) {
	$pairs = is_array( $k ) ? $k : array( $k => $v );
	$url   = is_array( $k ) ? (string) $v : (string) $u;

	foreach ( $pairs as $key => $value ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
	}

	return $url;
}
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function wp_create_nonce( $a = '' ) { return 'nonce'; }
function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true, $other = array() ) {
	$attrs = '';
	foreach ( (array) $other as $key => $value ) { $attrs .= ' ' . $key . '="' . esc_attr( $value ) . '"'; }
	printf( '<button type="submit" class="button button-%s" name="%s"%s>%s</button>', $type, $name, $attrs, esc_html( $text ) );
}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_date( $format, $ts = null, $zone = null ) { return gmdate( $format, null === $ts ? time() : (int) $ts ); }
function date_i18n( $format, $ts = false ) { return gmdate( $format, (int) $ts ); }
function wp_timezone_string() { return 'UTC'; }
function get_role( $r ) { return new WP_Role(); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => $GLOBALS['uploads'], 'baseurl' => 'https://example.test/wp-content/uploads' ); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_is_writable( $p ) { return is_writable( $p ); }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( str_repeat( md5( (string) mt_rand() ), 2 ), 0, (int) $l ); }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function wp_remote_head( $url, $args = array() ) {
	$GLOBALS['calls'][] = array( 'wp_remote_head', $url, $args );
	// The host as it was measured on 2 September 2026: any path with a dot-prefixed segment is
	// refused, and everything else under uploads is served. `$GLOBALS['head']` is what a
	// scenario wants the served case to answer.
	if ( is_wp_error( $GLOBALS['head'] ) ) {
		return $GLOBALS['head'];
	}
	if ( false !== strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/.' ) ) {
		return array( 'response' => array( 'code' => 403 ) );
	}
	return $GLOBALS['head'];
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) && isset( $r['response']['code'] ) ? (int) $r['response']['code'] : ''; }

// What the Accounts tab's list reaches of WordPress besides: its arguments, its rows-per-page
// choice, the names it orders, its view's label held until the count is known, and the empty row.
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function get_user_option( $option, $user = 0 ) { return $GLOBALS['umeta'][ $user ? (int) $user : $GLOBALS['uid'] ][ $option ] ?? false; }
function remove_accents( $text, $locale = '' ) { return strtr( (string) $text, array( 'É' => 'E', 'é' => 'e', 'È' => 'E', 'è' => 'e' ) ); }
function esc_html_e( $s, $d = null ) { echo esc_html__( $s ); }
function _n_noop( $singular, $plural, $domain = null ) { return array( 0 => $singular, 1 => $plural, 'singular' => $singular, 'plural' => $plural, 'context' => null, 'domain' => $domain ); }
function translate_nooped_plural( $nooped, $count, $domain = 'default' ) { return __( _n( $nooped['singular'], $nooped['plural'], $count ) ); }

// What the Pipeline's Find an institution reaches of WordPress (`WPCPM_Dashboards::render_combo()`):
// core's `selected()`, a string comparison as core's is; the script the field registers and enqueues,
// recorded; and the query of the Institution Dashboard's address, read as core's `wp_parse_str()`
// reads one.
$GLOBALS['registered'] = array();
$GLOBALS['enqueued']   = array();
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['registered'][ $h ] = $src; }
function wp_script_is( $h, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['registered'][ $h ] ) : in_array( $h, $GLOBALS['enqueued'], true ); }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_parse_str( $input, &$result ) { parse_str( (string) $input, $result ); }


/*
 * Posts, as the queue reads them.
 *
 * Applications and agreement documents are posts with meta, so the store is the same shape
 * `bin/test-institution-panel.php` uses: one map of `WP_Post` objects and one of repeating
 * meta rows, with `get_post_meta()` answering single or all the way the real one does.
 */
$GLOBALS['posts']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['deleted'] = array();

function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function get_post_time( $format, $gmt = false, $post = null, $translate = false ) {
	$stamp = $post instanceof WP_Post ? strtotime( $post->post_date_gmt . ' +0000' ) : 0;
	return 'U' === $format ? (int) $stamp : gmdate( $format, (int) $stamp );
}
function wp_delete_post( $id, $force = false ) {
	$GLOBALS['deleted'][] = array( (int) $id, (bool) $force );
	if ( ! isset( $GLOBALS['posts'][ (int) $id ] ) ) { return false; }
	$post = $GLOBALS['posts'][ (int) $id ];
	unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] );
	return $post;
}

/**
 * Stand one application up in the store.
 *
 * @param int    $id      Post ID.
 * @param string $name    Institution name.
 * @param string $state   Application state.
 * @param int    $at      When it arrived, unix time.
 * @param array  $meta    Extra meta, key => value.
 * @return WP_Post
 */
function seed_application( $id, $name, $state, $at, array $meta = array() ) {
	$post                = new WP_Post();
	$post->ID            = (int) $id;
	$post->post_type     = WPCPM_Institution_Application::POST_TYPE;
	$post->post_status   = 'private';
	$post->post_title    = $name;
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', (int) $at );

	$GLOBALS['posts'][ (int) $id ] = $post;
	$GLOBALS['pmeta'][ (int) $id ] = array();

	update_post_meta( $id, WPCPM_Institution_Application::META_STATE, $state );

	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}

	return $post;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once __DIR__ . '/stubs/stamps.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
// Before the module, which uses it: PHP declares a class only once the traits it uses are declared.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
// The real tab bar, the one every audience screen prints, so the bar read here is the bar a
// manager gets.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
// The real one field for a long list, which the Pipeline's Find an institution is drawn with, so the
// field read here is the field a manager gets.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, then the
 * accounts base and the Institutions table, so the Accounts tab draws its own list, which
 * bin/test-institutions-accounts.php reads row by row. The table's file is required once it
 * exists, so a copy of the plugin without it fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';

	if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-table.php' ) ) {
		require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-table.php';
	}
}
// The real allowlist of the places a decision goes back to, and of the ids the Administrator
// Dashboard's cards carry: the queue links each row to one of those cards, and the dashboard's own
// draw of an application, with its return fields, is read here off the real class.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		const OPT_NAME  = 'wpcpm_institutions_index';
		const OPT_LOCK  = 'wpcpm_institutions_index_lock';
		const VERSION = 1;
		public static function read() {
			$o = get_option( self::OPT_NAME );
			return ( is_array( $o ) && isset( $o['v'] ) && self::VERSION === $o['v'] ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function rows() { $r = self::read(); return $r['rows']; }
		public static function row( $id ) { $rows = self::rows(); return isset( $rows[ $id ] ) ? $rows[ $id ] : null; }
		public static function has( $id ) { return null !== self::row( $id ); }
		public static function write( array $rows, $read ) { update_option( self::OPT_NAME, array( 'v' => 1, 'read' => (int) $read, 'rows' => $rows ), false ); }
		public static function insert( array $row ) { $r = self::read(); $r['rows'][ $row['record_id'] ] = $row; update_option( self::OPT_NAME, $r, false ); }
		/**
		 * The real one's contract: the stages with rows, `STAGE_ORDER` then the terminal stages, then
		 * any stage the lists do not know as stored (so "SPAM " and "Paused" are groups of their own),
		 * sorted, then the empty stage.
		 */
		public static function by_stage() {
			$groups = array();
			foreach ( self::rows() as $id => $row ) {
				$groups[ (string) $row['stage'] ][ $id ] = $row;
			}
			$ordered = array();
			foreach ( array_merge( WPCPM_Institution_Agreement::STAGE_ORDER, WPCPM_Institution_Agreement::TERMINAL_STAGES ) as $stage ) {
				if ( isset( $groups[ $stage ] ) ) { $ordered[ $stage ] = $groups[ $stage ]; unset( $groups[ $stage ] ); }
			}
			$unknown = array_keys( $groups );
			sort( $unknown, SORT_STRING );
			foreach ( $unknown as $stage ) {
				if ( '' !== $stage ) { $ordered[ $stage ] = $groups[ $stage ]; }
			}
			if ( isset( $groups[''] ) ) { $ordered[''] = $groups['']; }
			return $ordered;
		}
	}
}

if ( ! class_exists( 'WPCPM_Countries' ) ) {
	class WPCPM_Countries {
		const OPT_NAME  = 'wpcpm_countries';
		const VERSION = 1;
		public static function read() {
			$o = get_option( self::OPT_NAME );
			return ( is_array( $o ) && isset( $o['v'] ) && self::VERSION === $o['v'] ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function all() { $r = self::read(); return $r['rows']; }
		public static function name_of( $id ) { $all = self::all(); return isset( $all[ $id ] ) ? $all[ $id ]['name'] : ''; }
		public static function routing( $id ) {
			$all = self::all();
			if ( ! isset( $all[ $id ] ) || ( '' === $all[ $id ]['manager'] && '' === $all[ $id ]['email'] ) ) { return null; }
			return $all[ $id ];
		}
		public static function contact_of( $id ) {
			$all = self::all();
			if ( ! isset( $all[ $id ] ) ) { return ''; }
			return ( '' !== $all[ $id ]['manager'] && ! WPCPM_Mentors_Sync::is_record_id( $all[ $id ]['manager'] ) ) ? $all[ $id ]['manager'] : $all[ $id ]['email'];
		}
		public static function gaps() { return array_filter( self::all(), function ( $r ) { return '' === $r['manager'] && '' === $r['email']; } ); }
		public static function refresh( $airtable = null ) { $GLOBALS['calls'][] = array( 'WPCPM_Countries::refresh' ); return true; }
	}
}

if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	class WPCPM_Roster_Index {
		const OPT_PREFIX   = 'wpcpm_roster_';
		const OPT_UNLINKED = 'wpcpm_roster_unlinked';
		const OPT_COUNTS   = 'wpcpm_roster_counts';
		const VERSION         = 1;
		public static function option_name( $id ) { return self::OPT_PREFIX . $id; }
		public static function read( $id ) { $o = get_option( self::option_name( $id ) ); return is_array( $o ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() ); }
		public static function rows( $id ) { $r = self::read( $id ); return $r['rows']; }
		public static function unlinked() { $o = get_option( self::OPT_UNLINKED ); return is_array( $o ) && isset( $o['rows'] ) ? $o['rows'] : array(); }
		public static function counts() {
			++$GLOBALS['counts_reads'];
			$o = get_option( self::OPT_COUNTS );
			return is_array( $o ) ? $o : array( 'v' => 1, 'read' => 0, 'institutions' => array(), 'reconciliation' => array() );
		}
		public static function write_all( array $b, array $u, array $c, array $r, $read ) {}
		public static function insert( $id, array $row ) {}
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Roster_Index::delete_all' ); }
	}
}

if ( ! class_exists( 'WPCPM_Ceiling' ) ) {
	/** Stands in for the rate-limit primitive: its own suite covers the counting. */
	class WPCPM_Ceiling {
		const CRON_SWEEP = 'wpcpm_ceiling_sweep';
		public static function init() {
			$GLOBALS['calls'][] = array( 'ceiling_init' );
		}
		public static function claim( $key, $limit, $window ) {
			return true;
		}
		public static function delete_all() {
			$GLOBALS['calls'][] = array( 'ceiling_delete_all' );
			return 0;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/** Stands in for the institution's own page: this suite is about the manager screen. */
	class WPCPM_Institutions_Dashboard {
		const OPT_PAGE        = 'wpcpm_institution_page_id';
		const OPT_TITLE_FIXED = 'wpcpm_institution_page_title_fixed';
		public static function init() {
			$GLOBALS['calls'][] = array( 'dashboard_init' );
		}
		public static function ensure_page() {
			$GLOBALS['calls'][] = array( 'dashboard_ensure_page' );
		}
		public static function page_url() {
			// A suite may take the page away, as a trashed page does, to see what a link to it becomes.
			return isset( $GLOBALS['institution_page_url'] ) ? (string) $GLOBALS['institution_page_url'] : 'https://example.test/institution-dashboard/';
		}
		public static function is_member( $user = null ) {
			return false;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Student_Form' ) ) {
	/** Stands in for the student edit form: its own suite covers the allowlist. */
	class WPCPM_Institution_Student_Form {
		public static function init() {
			$GLOBALS['calls'][] = array( 'student_form_init' );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Invite' ) ) {
	/** Stands in for member invitations. */
	class WPCPM_Institution_Invite {
		const CRON_EXPIRE = 'wpcpm_invite_expire';
		public static function init() {
			$GLOBALS['calls'][] = array( 'invite_init' );
		}
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Invite::delete_all' ); return 0; }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Request' ) ) {
	/** Stands in for mentor requests. */
	/**
	 * The three modules `boot()` starts that this screen never calls into.
	 *
	 * Stubs rather than requires: this suite is about the Institutions screen, and loading the
	 * real graduation, export and import modules would bring their own dependencies in to prove
	 * nothing about it. What the suite does need is that `boot()` starting them is not a fatal.
	 */
	class WPCPM_Institution_Students {
		public static function init() {
			$GLOBALS['calls'][] = array( 'students_init' );
		}
	}

	class WPCPM_Institution_Export {
		public static function init() {
			$GLOBALS['calls'][] = array( 'export_init' );
		}
	}

	class WPCPM_Institution_Import {
		const CRON_TICK = 'wpcpm_import_tick';
		public static function init() {
			$GLOBALS['calls'][] = array( 'import_init' );
		}
		// The batch posts hold a school's list of names, so the uninstall has to reach them.
		public static function delete_all() {
			$GLOBALS['calls'][] = array( 'import_delete_all' );
			return 0;
		}
	}

	class WPCPM_Institution_Import_Form {
		public static function init() {
			$GLOBALS['calls'][] = array( 'import_form_init' );
		}
	}

	class WPCPM_Institution_Create {
		public static function init() {
			$GLOBALS['calls'][] = array( 'create_init' );
		}
	}

	/** A student's invitation, sent again from their card: started by `boot()` and nothing more here. */
	class WPCPM_Institution_Resend {
		public static function init() {
			$GLOBALS['calls'][] = array( 'resend_init' );
		}
	}

	/** The school's details card (Institutions release 2): its two handlers are started by `boot()` and nothing more here. */
	class WPCPM_Institution_Details_Card {
		public static function init() {
			$GLOBALS['calls'][] = array( 'details_card_init' );
		}
	}

	/**
	 * Mentor requests, as the queue reads them: the open ones oldest first, each one's facts in the
	 * shape `facts()` answers, the numbers and the kind the queue reads off the class, and the
	 * dashboard's decisions on a request, which the queue must never draw.
	 *
	 * `$GLOBALS['open_requests']` is the open rows in the order the real reader returns them, and
	 * `$GLOBALS['request_facts']` each row's facts. Every limit asked is recorded, and every facts()
	 * built is counted: the bubble asks under the same ceiling as its other two reads, and the queue
	 * pays for the facts of the rows it can draw and no more.
	 */
	class WPCPM_Institution_Request {
		const QUEUE_MAX    = 200;
		const OVERDUE_DAYS = 14;
		const KIND_MENTOR  = 'mentor';
		const KIND_DETAILS = 'details';
		public static function init() {
			$GLOBALS['calls'][] = array( 'request_init' );
		}
		public static function open_requests( $limit = 20 ) {
			$GLOBALS['requests_asked'][] = (int) $limit;
			$limit = (int) $limit > 0 ? min( (int) $limit, self::QUEUE_MAX ) : self::QUEUE_MAX;
			return array_slice( $GLOBALS['open_requests'] ?? array(), 0, $limit );
		}
		public static function facts( $post_id ) {
			$GLOBALS['facts_built'] = ( $GLOBALS['facts_built'] ?? 0 ) + 1;
			return $GLOBALS['request_facts'][ (int) $post_id ] ?? array();
		}
		// The Administrator Dashboard's decisions on a request, recorded and drawn as a marker form,
		// so a list that called them would be seen calling them.
		public static function render_decisions( $post_id, $return = '' ) {
			$GLOBALS['request_decisions'][] = array( (int) $post_id, (string) $return );
			printf( '<form class="wpcpm-request__decide" data-request="%d"><input type="hidden" name="action" value="wpcpm_resolve_request" /></form>', (int) $post_id );
		}
		// One of the outcomes a decision on a request flashes on this screen's channel, in the
		// request class's own words, which the screen merges into its one map.
		public static function messages() {
			return array( 'request-done' => array( 'success', 'That request is closed as handled.' ) );
		}
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Request::delete_all' ); return 0; }
	}
}

if ( ! class_exists( 'WPCPM_Institution_People' ) ) {
	/** Stands in for the People card: its own suite covers it. */
	class WPCPM_Institution_People {
		public static function init() {
			$GLOBALS['calls'][] = array( 'people_init' );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Stands in for the members module: the screen names its meta keys through it. */
	class WPCPM_Institution_Members {
		const META_RECORD_ID     = 'wpcpm_institution_record_id';
		const META_ACTIVE        = 'wpcpm_institution_active';
		const META_RECORD_ID_WAS = 'wpcpm_institution_record_id_was';
		const META_MEMBERSHIP    = 'wpcpm_institution_membership';
		const META_INVITED       = 'wpcpm_inst_invited';
		const META_PROFILE       = 'wpcpm_institution_profile';
		public static function members_of( $record_id ) {
			// Recorded, so the backstop counts can be shown to ask each institution once:
			// the real call is a user query apiece, on a screen that draws 106 of them.
			$GLOBALS['member_reads'][] = $record_id;
			return isset( $GLOBALS['members_of'][ $record_id ] ) ? $GLOBALS['members_of'][ $record_id ] : array();
		}
		public static function former_members_of( $record_id ) {
			return isset( $GLOBALS['former_members_of'][ $record_id ] ) ? $GLOBALS['former_members_of'][ $record_id ] : array();
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	class WPCPM_Institution_Agreement {
		const POST_TYPE         = 'wpcpm_agreement';
		const ACTION_ON_FILE     = 'wpcpm_agreement_on_file';
		const CRON_DISCARD       = 'wpcpm_agreement_discard';
		const CRON_REMINDERS     = 'wpcpm_agreement_reminders';
		const MAX_LOCATION       = 200;
		const STATE_GENERATED   = 'generated';
		const STATE_SUBMITTED   = 'submitted';
		const STATE_ACCEPTED    = 'accepted';
		const STATE_RETURNED    = 'returned';
		const STATE_WITHDRAWN   = 'withdrawn';
		const STATE_SUPERSEDED  = 'superseded';
		const STATE_REVOKED     = 'revoked';
		const KIND_TEMPLATE     = 'template';
		const KIND_OWN          = 'own';
		const KIND_LEGACY       = 'legacy';
		const SUMMARY_NONE      = 'none';
		const SUMMARY_GENERATED = 'generated';
		const SUMMARY_SUBMITTED = 'submitted';
		const SUMMARY_RETURNED  = 'returned';
		const SUMMARY_REVOKED   = 'revoked';
		const SUMMARY_ACCEPTED  = 'accepted';
		const SUMMARY_ON_FILE   = 'on_file';
		const ACTION_REVOKE     = 'wpcpm_agreement_revoke';
		const MIN_NOTE          = 20;
		const MAX_NOTE          = 2000;
		const STAGE_ORDER       = array( 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Confirmed', 'Student' );
		const TERMINAL_STAGES   = array( 'Not Moving Forward', 'SPAM', 'Revisit Later' );
		const AIRTABLE_SETTLED  = array( 'Accepted', 'On file' );
		const META_INSTITUTION  = '_wpcpm_agr_institution';
		const META_STATE        = '_wpcpm_agr_state';
		const OPT_PREFIX     = 'wpcpm_agreement_';
		const LOCK_PREFIX       = 'wpcpm_agreement_lock_';
		const VERSION           = 1;
		public static function init() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Agreement::init' ); }
		public static function register_post_type() {}
		public static function is_settled( $id ) { $s = self::summary( $id ); return in_array( $s['state'], array( self::SUMMARY_ACCEPTED, self::SUMMARY_ON_FILE ), true ); }
		public static function option( $id ) { return null; }
		public static function option_name( $id ) { return self::OPT_PREFIX . $id; }
		public static function summary( $id ) {
			$GLOBALS['summary_reads'][] = $id;
			$none = array( 'state' => self::SUMMARY_NONE, 'kind' => '', 'accepted_at' => '', 'agreement_id' => 0, 'pending_id' => 0, 'generated_id' => 0, 'airtable_status' => '', 'route' => '' );
			return isset( $GLOBALS['summaries'][ $id ] ) ? array_merge( $none, $GLOBALS['summaries'][ $id ] ) : $none;
		}
		/** The words an Administrator reads for a summary state, as the real class holds them. */
		public static function summary_label( $state ) {
			$words = array( 'none' => 'Not started', 'generated' => 'Template generated', 'submitted' => 'Awaiting review', 'returned' => 'Returned', 'revoked' => 'Revoked', 'accepted' => 'Accepted', 'on_file' => 'On file' );
			return isset( $words[ $state ] ) ? $words[ $state ] : (string) $state;
		}
		/** What a refused revoke kept for that document's box, for the one time the form is drawn again: set per document by the suite. */
		public static function kept_revoke_note( $post_id ) { return $GLOBALS['kept_revoke'][ (int) $post_id ] ?? null; }
		public static function rebuild( $id, array $airtable ) { return array(); }
		public static function discrepancies() { return $GLOBALS['discrepancies'] ?? array(); }
		public static function posts_for( $id ) { return array(); }
		public static function awaiting_review() { return $GLOBALS['awaiting'] ?? array(); }
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Agreement::delete_all' ); }
		public static function manifest_kept_files() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Agreement::manifest_kept_files' ); return array( 'files' => 0, 'mailed' => false, 'written' => '' ); }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	class WPCPM_Institution_Audit {
		const POST_TYPE = 'wpcpm_audit_entry';
		public static function init() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Audit::init' ); }
		public static function register_post_type() {}
		public static function record( array $entry ) { return 1; }
		public static function entries_for( $institution, $limit = 50 ) { return array(); }
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Audit::delete_all' ); }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Record' ) ) {
	/** Stands in for the institutions' own records (1.123.29): booted and swept at uninstall, drawn nowhere here. */
	class WPCPM_Institution_Record {
		const POST_TYPE = 'wpcpm_inst_record';
		public static function init() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Record::init' ); }
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Record::delete_all' ); }
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Sync' ) ) {
	class WPCPM_Institutions_Sync {
		const CRON_DAILY = 'wpcpm_institutions_sync_daily';
		const CRON_TICK  = 'wpcpm_institutions_sync_tick';
		const OPT_STATE  = 'wpcpm_institutions_state';
		const OPT_REPORT = 'wpcpm_institutions_report';
		const OPT_LAST   = 'wpcpm_institutions_last_sync';
		const OPT_ERROR  = 'wpcpm_institutions_last_error';
		const OPT_LOCK   = 'wpcpm_institutions_lock';
		const BUDGET_AJAX = 8;
		public static function fields() {
			return array(
				'name' => 'Name', 'stage' => 'Current Stage', 'country' => 'Country', 'city' => 'City',
				'website' => 'Website', 'contact_person' => 'Contact Person', 'contact_email' => 'Contact Email',
				'confirmed_on' => 'Confirmed on', 'consent' => 'Privacy Policy Compliance',
				'agr_status' => 'Agreement Status', 'agr_kind' => 'Agreement Kind', 'agr_accepted_on' => 'Agreement Accepted On',
				'agr_signed_on' => 'Agreement Signed On', 'agr_accepted_by' => 'Agreement Accepted By',
				'agr_document' => 'Agreement Document', 'agr_submitted_on' => 'Agreement Submitted On',
				'agr_template' => 'Agreement Template Version',
			);
		}
		public static function register_cron() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::register_cron' ); }
		public static function start() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::start' ); return empty( $GLOBALS['sync_refuses'] ) ? true : new WP_Error( 'wpcpm_not_connected', 'not connected' ); }
		public static function tick( $budget = null ) { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::tick', $budget ); }
		public static function cancel() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::cancel' ); }
		public static function is_running() { return ! empty( $GLOBALS['sync_running'] ); }
		public static function progress() {
			++$GLOBALS['progress_reads'];
			return array_merge(
				array( 'running' => false, 'phase' => '', 'label' => '', 'detail' => '', 'percent' => 100, 'step' => 4, 'step_total' => 4, 'step_label' => '', 'stats' => array(), 'elapsed' => 0, 'idle' => 0, 'error' => '', 'stalled' => false ),
				$GLOBALS['sync_progress'] ?? array()
			);
		}
		public static function activate() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::activate' ); }
		public static function deactivate() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::deactivate' ); }
		public static function last_read() { return $GLOBALS['sync_last'] ?? 0; }

		/*
		 * Provisioning. The screen asks why an institution may not have an account, and its
		 * Accounts tab counts what the answers leave; whether an answer is right is
		 * bin/test-institutions-sync.php's business, so the stub answers from a map. Its default is
		 * the day-one state the design describes: every Confirmed institution is legacy and none
		 * has an agreement recorded yet.
		 */
		const BLOCK_NOT_INDEXED   = 'not_indexed';
		const BLOCK_NOT_CONFIRMED = 'not_confirmed';
		const BLOCK_NO_EMAIL      = 'no_email';
		const BLOCK_NO_AGREEMENT  = 'no_agreement';
		const BLOCK_HAS_MEMBER    = 'has_member';
		const BLOCK_FORMER_MEMBER = 'former_member';
		const BLOCK_CONFLICT      = 'account_exists';
		public static function provision_block( $record_id ) {
			$GLOBALS['blocks_read'][] = $record_id;
			return isset( $GLOBALS['blocks'][ $record_id ] ) ? $GLOBALS['blocks'][ $record_id ] : self::BLOCK_NO_AGREEMENT;
		}
		public static function provision_message( $reason ) { return 'Refused: ' . $reason . '.'; }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Application' ) ) {
	/**
	 * Stands in for the public form. This suite is about the queue that reads its posts, so
	 * the stub answers from the post store and nothing here decides what a submission does.
	 */
	class WPCPM_Institution_Application {
		const POST_TYPE = 'wpcpm_inst_app';
		const OPT_PAGE  = 'wpcpm_application_page_id';
		const OPT_MOVED = 'wpcpm_application_page_moved';

		public static function init() {
			$GLOBALS['calls'][] = array( 'application_init' );
		}
		public static function ensure_page() {
			$GLOBALS['calls'][] = array( 'application_ensure_page' );
			return 0;
		}
		public static function delete_all() {
			$GLOBALS['calls'][] = array( 'application_delete_all' );
			return 0;
		}

		const STATE_NEW      = 'new';
		const STATE_HELD     = 'held';
		const STATE_SPAM     = 'spam';
		const STATE_INFO     = 'info';
		const STATE_APPROVED = 'approved';
		const STATE_REJECTED = 'rejected';

		/*
		 * The four limits the queue quotes when it says in plain words why a submission was
		 * held, and the day's limit on acknowledgements it quotes when a send is refused. The
		 * screen names them through this class rather than writing 6 and 3 and 30 and 40 into
		 * its sentences, so a limit changed on the form cannot leave the manager
		 * screen quoting the old one; `php bin/check-references.php` is what proves the real
		 * class still declares each of them.
		 */
		const MIN_SECONDS  = 6;
		const MAX_LINKS    = 3;
		const MIN_REASON   = 30;
		const PER_DAY      = 40;
		const MAIL_PER_DAY = 200;

		const META_FIELDS       = '_wpcpm_app_fields';
		const META_STATE        = '_wpcpm_app_state';
		const META_REFERENCE    = '_wpcpm_app_reference';
		const META_COUNTRY      = '_wpcpm_app_country';
		const META_COUNTRY_NAME = '_wpcpm_app_country_name';
		const META_MANAGER      = '_wpcpm_app_manager';
		const META_CONSENT      = '_wpcpm_app_consent';
		const META_SIGNALS      = '_wpcpm_app_signals';
		const META_EMAIL        = '_wpcpm_app_email';
		const META_VERIFIED     = '_wpcpm_app_verified';
		const META_RECORD       = '_wpcpm_app_record';
		const META_USER         = '_wpcpm_app_user';
		const META_EVENT        = '_wpcpm_app_event';
		const META_SITE         = '_wpcpm_app_site';

		/**
		 * The thirteen columns of design spec 7.1, keyed by Airtable column name, and the five
		 * answers kept on the site since 1.123.29, keyed by name and flagged `site`, in form order.
		 *
		 * The spec's shape, because the queue prints the question from `label` and matches the
		 * two the server holds by column name: a value that was only a group name would let
		 * either of those pass without being exercised. The flag is what tells the queue an answer
		 * is read from `site_answers()` and has no column to print.
		 */
		public static function fields() {
			return array(
				'Name'           => array( 'group' => 'about', 'label' => 'Name of your institution' ),
				'Country'        => array( 'group' => 'about', 'label' => 'Country' ),
				'City'           => array( 'group' => 'about', 'label' => 'City' ),
				'Website'        => array( 'group' => 'about', 'label' => 'Website' ),
				'Contact Person' => array( 'group' => 'contact', 'label' => 'Name of the person we should contact' ),
				'Contact Email'  => array( 'group' => 'contact', 'label' => 'Their email address' ),
				'contact_role'   => array( 'group' => 'contact', 'label' => 'Their role at the institution', 'site' => true ),
				'Department'     => array( 'group' => 'contact', 'label' => 'Department or faculty' ),
				'How do your internships or practices typically work?' => array( 'group' => 'program', 'label' => 'How do your internships or practices typically work?' ),
				'Comments'       => array( 'group' => 'program', 'label' => 'If you ticked "Other", please tell us how' ),
				'Estimated number of students who may be interested'   => array( 'group' => 'program', 'label' => 'How many students might be interested?' ),
				'student_languages' => array( 'group' => 'program', 'label' => 'In which languages do your students study?', 'site' => true ),
				'signer_name'    => array( 'group' => 'agreement', 'label' => 'Who will sign the agreement for the institution?', 'site' => true ),
				'signer_title'   => array( 'group' => 'agreement', 'label' => 'Their title', 'site' => true ),
				'credits_page'   => array( 'group' => 'agreement', 'label' => 'Your page about WordPress Credits', 'site' => true ),
				'Why are you interested in offering WordPress Credits to your students?' => array( 'group' => 'more', 'label' => 'Why are you interested in offering WordPress Credits to your students?' ),
				'Anything else you’d like us to know?' => array( 'group' => 'more', 'label' => 'Anything else you’d like us to know?' ),
				// The words the form asks since 1.123.24; the application seeded below was filed before,
				// under the sentence its consent record holds.
				'Privacy Policy Compliance' => array( 'group' => 'consent', 'label' => 'I confirm I have read the WordPress.org privacy policy and that this institution can share students\' details with the program under it.' ),
			);
		}

		/** The answers kept on the site, as the real one reads them: the stored list, or none. */
		public static function site_answers( $application_id ) {
			$answers = get_post_meta( (int) $application_id, self::META_SITE, true );
			return is_array( $answers ) ? $answers : array();
		}

		/** The reference, computed from the ID the way the real one is. */
		public static function reference( $post_id ) {
			return sprintf( 'APP-%1$s-%2$04d', gmdate( 'Y' ), (int) $post_id );
		}

		/** The one writer of an application's history. */
		public static function add_event( $post_id, $event, $actor = 0, $note = '' ) {
			add_post_meta( (int) $post_id, self::META_EVENT, array(
				'event' => sanitize_text_field( (string) $event ),
				'at'    => time(),
				'actor' => (int) $actor,
				'note'  => sanitize_textarea_field( (string) $note ),
			) );
		}

		/**
		 * The rows in those states, oldest first, bounded when the caller says so.
		 *
		 * `$GLOBALS['loaded']` counts the post objects handed out, which is what the real
		 * `get_posts()` builds and primes the meta cache for - and the meta here is
		 * `META_FIELDS`, the applicant's whole submitted form, per row. The queue must never
		 * pay that for a row it does not draw (deep check FADMN-2, whose Administrator
		 * Dashboard half shipped with it; this is the wp-admin queue's copy).
		 */
		public static function applications( $states, $limit = 0 ) {
			$out = self::matching( $states );
			if ( (int) $limit > 0 ) { $out = array_slice( $out, 0, (int) $limit ); }
			$GLOBALS['loaded'] += count( $out );
			return $out;
		}

		/** The same rows as IDs: no object, no meta cache, which is what a total costs. */
		public static function application_ids( $states ) {
			return array_map( static function ( $post ) { return (int) $post->ID; }, self::matching( $states ) );
		}

		private static function matching( $states ) {
			$out = array();
			foreach ( $GLOBALS['posts'] as $post ) {
				if ( self::POST_TYPE !== $post->post_type ) { continue; }
				if ( ! in_array( (string) get_post_meta( $post->ID, self::META_STATE, true ), (array) $states, true ) ) { continue; }
				$out[] = $post;
			}
			usort( $out, function ( $a, $b ) {
				$by_date = strcmp( $a->post_date_gmt, $b->post_date_gmt );
				return 0 !== $by_date ? $by_date : $a->ID - $b->ID;
			} );
			return $out;
		}

		/**
		 * The acknowledgement sent again, at an Administrator's press: recorded, and answered as the
		 * suite says (`sent`, `ceiling` or `failed`). What it sends and the limit it counts against
		 * are bin/test-institution-application.php's; what the queue owes it is the call, once.
		 */
		public static function acknowledge_again( $post_id ) {
			$GLOBALS['acknowledged'][] = (int) $post_id;
			return $GLOBALS['ack_result'] ?? 'sent';
		}

		/** IDs, like the real one: the bubble counts rows and draws none of them. */
		public static function pending_count( $limit = 0 ) {
			$found = count( self::application_ids( array( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO ) ) );
			return (int) $limit > 0 ? min( $found, (int) $limit ) : $found;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Approval' ) ) {
	/** Stands in for the ten steps of approval: what the queue owes it is the delegation. */
	/** Stands in for the generate route: its own suite covers the document. */
	class WPCPM_Agreement_Generate {
		const ACTION_GENERATE = 'wpcpm_agreement_generate';
		public static function init() {
			$GLOBALS['calls'][] = array( 'generate_init' );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Approval' ) ) {
	class WPCPM_Institution_Approval {
		public static function delete_all() {
			$GLOBALS['calls'][] = array( 'approval_delete_all' );
			return 0;
		}
		public static function approve( $application_id, $manager_id ) {
			$GLOBALS['approved'][] = array( (int) $application_id, (int) $manager_id );
			return $GLOBALS['approve_result'] ?? array( 'record' => 'recAPPROVED00001', 'user_id' => 77, 'adopted' => false );
		}
		/** An approval begun and left unfinished, for the applications the suite names. */
		public static function is_half_done( $application_id ) {
			return in_array( (int) $application_id, (array) ( $GLOBALS['half_done'] ?? array() ), true );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Panel' ) ) {
	/**
	 * Stands in for the panel: the queue asks it to draw the review block and nothing else, and the
	 * map asks its outcomes, of which the upload's and the withdrawal's are the two this suite reads,
	 * in the panel's own words. What the block holds is bin/test-institution-panel.php's; what the
	 * queue owes it is the post and whether the block may decide, which the marker prints and
	 * `$GLOBALS['reviews']` records.
	 */
	class WPCPM_Institution_Panel {
		const ANCHOR = 'wpcpm-agreement';
		/** The revoke note's box by its name, as the real panel words it: the form and the refusal name it alike. */
		public static function revoke_label() {
			return 'Why it is revoked, in your own words';
		}
		public static function messages() {
			return array(
				'agreement-uploaded'  => array( 'success', 'The signed agreement is uploaded. An Administrator reviews it and you will get an email either way.' ),
				'agreement-withdrawn' => array( 'success', 'The signed agreement is withdrawn and its file is deleted. Upload another whenever you are ready.' ),
			);
		}
		public static function render_review( $post_id, $decide = true ) {
			$GLOBALS['reviews'][] = array( (int) $post_id, $decide );
			printf( '<div class="wpcpm-agreement-review" data-post="%d" data-decide="%s"></div>', (int) $post_id, $decide ? 'yes' : 'no' );
		}
	}
}

// The Administrator Dashboard's page, which every row of the queue links into.
require_once __DIR__ . '/stubs/administrators-dashboard.php';

if ( ! class_exists( 'WPCPM_Administrators_Cards' ) ) {
	/**
	 * The Administrator Dashboard's cards, for the one number the screen reads from them: how many
	 * open applications the applications card lists, past which an opened application is decided on
	 * this screen. A number of its own here, apart from the queue's `QUEUE_MAX`, which equals it on a
	 * real site, so a check can tell which of the two the opened application reads.
	 */
	class WPCPM_Administrators_Cards {
		const LIMIT = 40;
	}
}

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/**
	 * Stands in for the client, recording every request.
	 *
	 * The point of most of these assertions is that the queue makes none: the list is drawn
	 * from posts and options, and only an application somebody opened is searched for.
	 */
	class WPCPM_Airtable {
		public function fetch_page( $table, array $args = array() ) {
			$GLOBALS['calls'][] = array( 'fetch_page', $table, $args['formula'] ?? '' );
			return $GLOBALS['airtable_page'] ?? array( 'records' => array(), 'offset' => null );
		}
		public function formula_in( $field, array $values, $lower = false ) {
			$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );
			if ( empty( $values ) ) { return ''; }
			return sprintf( "LOWER({%s}) = '%s'", $field, $lower ? strtolower( $values[0] ) : $values[0] );
		}
		public static function flatten( $value, $glue = ', ' ) {
			return is_array( $value ) ? implode( $glue, array_map( 'strval', $value ) ) : (string) $value;
		}
	}
}

if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	/** The three keys the reconciliation card reads: the stamp, the active flag, the program. */
	class WPCPM_Students_Sync {
		const META_INSTITUTION = 'wpcpm_student_institution';
		const META_ACTIVE      = 'wpcpm_student_active';
		const META_PROGRAM     = 'wpcpm_student_program';
	}
}

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $v ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); }
	}
}

if ( ! class_exists( 'WPCPM_Mentors' ) ) {
	class WPCPM_Mentors {
		public static function format_duration( $s ) { return sprintf( '%d:%02d', intdiv( (int) $s, 60 ), (int) $s % 60 ); }
	}
}

/*
 * The semester reports card.
 *
 * The card is one query and one row renderer, so the two report classes are stubbed to what it
 * actually reads: the post type it queries, the two meta accessors, the state, the generated
 * date, the switcher link and the consent form. The last of those is the only control on a
 * report that lives outside the institution's own dashboard - design spec open question 2 puts
 * the send in a program manager's hands - so this screen is where it has to appear.
 */
if ( ! class_exists( 'WPCPM_Semester_Report' ) ) {
	class WPCPM_Semester_Report {
		const POST_TYPE           = 'wpcpm_inst_report';
		const STATE_DRAFT         = 'draft';
		const STATE_APPROVED      = 'approved';
		const ORIGIN_AUTO         = 'auto';
		const OPT_AUTODRAFT_SINCE = 'wpcpm_report_autodraft_since';
		public static function institution_of( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_institution', true ); }
		public static function cohort_of( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_cohort', true ); }
		public static function generated_at( WP_Post $post ) { return (int) get_post_meta( $post->ID, '_wpcpm_report_generated', true ); }
		public static function state( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_state', true ); }
		/** The words an Administrator reads for a report's state, as the real class holds them. */
		public static function state_label( $state ) {
			$words = array( 'none' => 'Not drafted', 'approved' => 'Approved' );
			return isset( $words[ $state ] ) ? $words[ $state ] : 'Draft';
		}
		public static function origin_of( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_origin', true ) === 'auto' ? 'auto' : 'manager'; }
		public static function approved_at( WP_Post $post ) { $s = get_post_meta( $post->ID, '_wpcpm_report_approved', true ); return is_array( $s ) ? $s : array(); }
		public static function due( $today ) { return isset( $GLOBALS['due'] ) ? $GLOBALS['due'] : array(); }
		public static function log_entries() { return isset( $GLOBALS['report_log'] ) ? $GLOBALS['report_log'] : array(); }
		public static function init() {}
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Semester_Report::delete_all' ); return 0; }
	}
}

if ( ! class_exists( 'WPCPM_Semester_Report_Screen' ) ) {
	class WPCPM_Semester_Report_Screen {
		const ACTION_ASK     = 'wpcpm_report_ask';
		const ASK_PER_RUN    = 25;
		const CRON_ASK       = 'wpcpm_report_ask_queue';
		const CRON_AUTODRAFT = 'wpcpm_report_autodraft';
		const ACTION_DRAFT   = 'wpcpm_report_draft';
		const META_ASKED     = 'wpcpm_report_consent_asked';
		const META_STASH     = 'wpcpm_report_stash';
		public static function report_url( $cohort ) { return '' === (string) $cohort ? '' : 'https://example.test/institution-dashboard/?wpcpm_report=' . $cohort; }
		public static function render_ask_form( $post_id ) {
			printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::ACTION_ASK . '_' . (int) $post_id );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_ASK ) );
			printf( '<input type="hidden" name="report" value="%d" />', (int) $post_id );
			echo '<button type="submit">Ask the students</button></form>';
		}
		public static function render_draft_form( $record, $cohort, $label = '' ) {
			printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			printf( '<input type="hidden" name="action" value="%s" /><input type="hidden" name="institution" value="%s" /><input type="hidden" name="cohort" value="%s" />', esc_attr( self::ACTION_DRAFT ), esc_attr( $record ), esc_attr( $cohort ) );
			echo '<button type="submit">Draft now</button></form>';
		}
		// Reduced to what the card is asserted to contain: a heading and a form naming the
		// institution select. render_draft_picker()'s own contract is pinned in the report
		// suite, against the real class.
		public static function render_draft_picker() {
			echo '<h3>Draft any semester</h3><form><select name="institution"></select></form>';
		}
		public static function init() {}
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Semester_Report_Screen::delete_all' ); return 0; }
	}
}

if ( ! class_exists( 'WPCPM_Student_Feedback' ) ) {
	class WPCPM_Student_Feedback {
		const META_REPORT_PERMISSIONS = 'wpcpm_report_permissions';
	}
}

if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	class WPCPM_Institution_Roster {
		public static function locked_today() { return $GLOBALS['locked']; }
		const ARG_VIEW = 'wpcpm_institution_view';
	}
}

if ( ! class_exists( 'WPCPM_Cohort' ) ) {
	class WPCPM_Cohort {
		public static function label( $key ) { return 'Semester ' . (string) $key; }
	}
}

/*
 * `get_posts()` and `wp_count_posts()`, for the reports card and nothing else.
 *
 * Filtered here rather than in the store, because the card's own query arguments are what is
 * being pinned: it asks for `private` and not `any`, so a report somebody trashed by hand is a
 * withdrawn document and not a row to list, and this stub would list it if the card stopped
 * saying so.
 */
function get_posts( $args = array() ) {
	$type   = isset( $args['post_type'] ) ? (string) $args['post_type'] : '';
	$status = isset( $args['post_status'] ) ? (string) $args['post_status'] : 'publish';
	$found  = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_type === $type && $post->post_status === $status ) { $found[] = $post; }
	}

	usort( $found, static function ( $a, $b ) { return strcmp( $b->post_modified_gmt, $a->post_modified_gmt ); } );

	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : -1;

	return $limit > 0 ? array_slice( $found, 0, $limit ) : $found;
}

function wp_count_posts( $type = 'post' ) {
	$tally = array( 'private' => 0 );

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_type === $type && 'private' === $post->post_status ) { ++$tally['private']; }
	}

	return (object) $tally;
}

function get_post_modified_time( $format, $gmt = false, $post = null, $translate = false ) {
	$stamp = $post instanceof WP_Post ? strtotime( $post->post_modified_gmt . ' +0000' ) : 0;

	return 'U' === $format ? (int) $stamp : gmdate( $format, (int) $stamp );
}

/**
 * The mail layer, recording which door each message left by.
 *
 * `send()` takes an account and builds in its language; `send_to()` takes a bare address.
 * The assertion that matters is which one notify_managers() chose, so the stub records the
 * method and not only the recipient.
 *
 * And the invitations, as far as the Accounts tab's frame reaches them: the stamps the list's
 * views read, the map every stand-in mail class reads (bin/stubs/stamps.php), the words a row's
 * invitation leaves, its failure's among them, who was never sent one, and the card, drawn as a
 * marker holding the form the module hands it. The invitations themselves, and the card's own
 * words, are bin/test-institutions-accounts.php's, against the real class.
 */
if ( ! class_exists( 'WPCPM_Mail' ) ) {
	class WPCPM_Mail {
		const STAMPS = WPCPM_STUB_STAMPS;
		public static function invite_notices() {
			return array(
				'invited'         => array( 'success', 'Invitation email sent.' ),
				'invite-too-soon' => array( 'warning', 'Nothing was sent: too soon.' ),
			);
		}
		public static function invite_failed_message() {
			return 'The invitation could not be sent.';
		}
		public static function never_invited( $role, $meta ) {
			$GLOBALS['never_invited_asked'][] = array( $role, $meta );
			return $GLOBALS['never_invited'] ?? array();
		}
		/** Nobody's invitation held here: bin/test-institutions-accounts.php plays the held account. */
		public static function held_back( $role, $meta ) {
			return array();
		}
		/** Everybody holding the role, invited or not: what the screen asks when nobody is left to invite. */
		public static function everyone( $role ) {
			$GLOBALS['everyone_asked'][] = $role;
			return $GLOBALS['everyone'] ?? array();
		}
		public static function render_invite_card( array $args ) {
			echo '<div class="wpcpm-card wpcpm-invites"><h2>Invitations</h2>';
			if ( empty( $args['pending'] ) && ! empty( $args['none'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $args['none'] ) );
			}
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
		public static function send( $recipient, $context, $build ) {
			$user = $recipient instanceof WP_User ? $recipient : get_user_by( 'id', (int) $recipient );
			if ( ! $user instanceof WP_User || ! $user->exists() || '' === $user->user_email ) { return false; }
			$GLOBALS['mail'][] = array( 'send', $user->ID, $context, call_user_func( $build, $user ) );
			return true;
		}
		public static function site_name() { return 'WPCredits'; }
		public static function reply_to( $person ) {
			return $person instanceof WP_User && '' !== $person->user_email ? array( sprintf( 'Reply-To: "%1$s" <%2$s>', $person->display_name, $person->user_email ) ) : array();
		}
		public static function send_to( $email, $context, $build, $locale = '' ) {
			if ( ! is_email( $email ) ) { return false; }
			// A mail server that would not take the message: `wp_mail()` answers false and the
			// message is nowhere. The only thing a caller can do about that is not act as
			// though it went, which is what `$GLOBALS['mail_refuses']` is here to exercise.
			if ( ! empty( $GLOBALS['mail_refuses'] ) ) { return false; }
			$GLOBALS['mail'][] = array( 'send_to', $email, $context, call_user_func( $build, $email ) );
			return true;
		}
	}
}

/* ---- runner ------------------------------------------------------------- */

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * The screen's HTML, captured.
 *
 * @param array $get Query arguments the render should see.
 * @return string
 */
function render_screen( array $get = array() ) {
	$_GET = $get;
	$GLOBALS['summary_reads']  = array();
	$GLOBALS['blocks_read']    = array();
	$GLOBALS['member_reads']   = array();
	$GLOBALS['progress_reads'] = 0;
	$GLOBALS['counts_reads']   = 0;
	ob_start();
	( new WPCPM_Institutions() )->render_admin_page();
	return ob_get_clean();
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
 * The hidden fields of the first form on a render that posts the given action: what a press of
 * it sends, the nonce aside, read by the one reader of a form's fields by its action
 * (`form_fields_of()`, bin/stubs/screen-helpers.php), sorted by name.
 *
 * @param string $html   Rendered screen.
 * @param string $action The form's `action` field.
 * @return array<string, string> Name => value; empty when no form posts that action.
 */
function form_fields( $html, $action ) {
	$forms  = form_fields_of( $html, $action );
	$fields = isset( $forms[0] ) ? $forms[0] : array();

	unset( $fields['_wpnonce'] );

	return $fields;
}

/**
 * The query arguments of the address a handler redirected to, read off how it ended.
 *
 * @param string $outcome What `outcome()` returned: `redirect: <url>`.
 * @return array<string, string>
 */
function landed_on( $outcome ) {
	parse_str( (string) parse_url( substr( (string) $outcome, strlen( 'redirect: ' ) ), PHP_URL_QUERY ), $args );
	return $args;
}

/**
 * Every `<h3 class="wpcpm-inst-stage">` heading with its count, in document order.
 *
 * @param string $html Rendered screen.
 * @return array<string, int>
 */
function stage_headings( $html ) {
	preg_match_all( '#<h3 class="wpcpm-inst-stage">(.*?) <span class="wpcpm-count">(\d+)</span></h3>#', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $h ) { $out[ html_entity_decode( $h[1], ENT_QUOTES ) ] = (int) $h[2]; }
	return $out;
}

/**
 * Every folded stage under "Not counted above", with its count, in document order, and whether it
 * is drawn open.
 *
 * @param string $html Rendered screen.
 * @return array<string, array{0: int, 1: bool}> Label => count and open.
 */
function folded_groups( $html ) {
	preg_match_all( '#<details class="wpcpm-inst-folded"( open)?><summary>(.*?) <span class="wpcpm-count">(\d+)</span></summary>#', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $f ) { $out[ html_entity_decode( $f[2], ENT_QUOTES ) ] = array( (int) $f[3], '' !== $f[1] ); }
	return $out;
}

/**
 * Every queue row in document order: the name, what kind of row it is, and whether it is overdue.
 *
 * @param string $html Rendered screen.
 * @return array[]
 */
function queue_items( $html ) {
	preg_match_all( '#<li class="wpcpm-queue-item([^"]*)"><h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">(.*?)</span> <span class="wpcpm-inst-muted">(.*?)</span></h3>#', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $row ) { $out[] = array( html_entity_decode( $row[2], ENT_QUOTES ), html_entity_decode( $row[3], ENT_QUOTES ), ' is-overdue' === $row[1] ); }
	return $out;
}

/**
 * Which queue rows carry a given mark, keyed by the institution name, in document order.
 *
 * @param string $html Rendered screen.
 * @param string $mark A string that appears inside the row's own markup.
 * @return array<string, bool>
 */
function queue_marked( $html, $mark ) {
	$out = array();
	foreach ( array_slice( explode( '<li class="wpcpm-queue-item', $html ), 1 ) as $item ) {
		if ( preg_match( '#<span class="wpcpm-inst-name__text">(.*?)</span>#', $item, $m ) ) {
			$out[ html_entity_decode( $m[1], ENT_QUOTES ) ] = false !== strpos( $item, $mark );
		}
	}
	return $out;
}

/**
 * Each queue row's own markup, in document order: from its opening tag to the next row's, and the
 * last one to the end of the list.
 *
 * @param string $html Rendered screen.
 * @return string[]
 */
function queue_chunks( $html ) {
	$list = (string) $html;
	$end  = strpos( $list, '</ol>' );
	$list = false === $end ? $list : substr( $list, 0, $end );
	return array_slice( explode( '<li class="wpcpm-queue-item', $list ), 1 );
}

/**
 * Where each queue row's "Open on the Administrator Dashboard" link goes, in document order: the
 * row's name and kind, then the address with its entities decoded, or '' for a row that prints none.
 *
 * @param string $html Rendered screen.
 * @return array[]
 */
function queue_links( $html ) {
	$out = array();
	foreach ( queue_chunks( $html ) as $item ) {
		preg_match( '#<span class="wpcpm-inst-name__text">(.*?)</span> <span class="wpcpm-inst-muted">(.*?)</span>#', $item, $title );
		preg_match( '#<a href="([^"]*)">Open on the Administrator Dashboard</a>#', $item, $link );
		$out[] = array(
			html_entity_decode( $title[1] ?? '', ENT_QUOTES ),
			html_entity_decode( $title[2] ?? '', ENT_QUOTES ),
			isset( $link[1] ) ? html_entity_decode( $link[1], ENT_QUOTES ) : '',
		);
	}
	return $out;
}

/**
 * Stand one open request up, in the shape `WPCPM_Institution_Request::facts()` answers.
 *
 * The institution's name and country are the pipeline index's, as the real facts' are, and the
 * overdue mark is the real rule: past the request's own `OVERDUE_DAYS`, whatever the setting gives
 * an application or an agreement. The kind's label is the real class's words for it, and a
 * request about no student, as the `format` kind is, has neither a record nor a name, the way
 * `student_name()` falls back to an empty record.
 *
 * @param int    $id      Post ID.
 * @param string $record  Institutions record ID.
 * @param string $student The student's name, or '' for a request about no student.
 * @param string $actor   Who raised it, or '' for an account that is gone.
 * @param string $note    The note on the row, or ''.
 * @param int    $at      When it was raised, unix time.
 * @param string $kind    `mentor`, `add`, `format` or `details`.
 */
function seed_request( $id, $record, $student, $actor, $note, $at, $kind = 'mentor' ) {
	$row    = WPCPM_Institutions_Index::row( $record );
	$labels = array(
		'add'     => 'A student to add',
		'mentor'  => 'A mentor is wanted',
		'format'  => 'A change to the report',
		'details' => 'A change to its details',
	);

	$GLOBALS['request_facts'][ (int) $id ] = array(
		'id'               => (int) $id,
		'kind'             => $kind,
		'kind_label'       => $labels[ $kind ],
		'state'            => 'open',
		'institution'      => $record,
		'institution_name' => trim( (string) $row['name'] ),
		'country'          => (string) $row['country'],
		'country_name'     => (string) $row['country_name'],
		'student'          => '' !== $student ? sprintf( 'recSTUREQ%08d', (int) $id ) : '',
		'student_name'     => $student,
		'actor'            => '' !== $actor ? 50 : 0,
		'actor_name'       => $actor,
		'note'             => $note,
		'at'               => (int) $at,
		'closed_at'        => 0,
		'overdue'          => ( time() - (int) $at ) > ( WPCPM_Institution_Request::OVERDUE_DAYS * DAY_IN_SECONDS ),
	);
}

/**
 * The body of one function in a source file, by brace depth.
 *
 * @param string $src  File contents.
 * @param string $name Function name.
 * @return string
 */
function function_body( $src, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE ) ) { return ''; }
	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	while ( $end < strlen( $src ) && $depth > 0 ) {
		if ( '{' === $src[ $end ] ) { $depth++; } elseif ( '}' === $src[ $end ] ) { $depth--; }
		$end++;
	}
	return substr( $src, $offset, $end - $offset );
}

/* ---- fixtures ----------------------------------------------------------- */

$seed = json_decode( file_get_contents( __DIR__ . '/fixtures/institutions-index-seed.json' ), true );

if ( ! is_array( $seed ) || empty( $seed['institutions'] ) ) {
	echo "Could not read bin/fixtures/institutions-index-seed.json\n";
	exit( 1 );
}

$read_at   = 1756800000; // 2025-09-02 08:00 UTC, a fixed instant so the read line is deterministic.
$countries = array();

foreach ( $seed['countries'] as $country ) {
	$countries[ $country['id'] ] = array(
		'name'     => $country['name'],
		'manager'  => $country['has_contact'] ? 'A Manager' : '',
		'email'    => $country['has_email'] ? 'manager@example.test' : '',
		'calendly' => $country['has_calendly'] ? 'https://calendly.com/example' : '',
	);
}

$rows = array();

foreach ( $seed['institutions'] as $institution ) {
	$country = ! empty( $institution['country'] ) ? (string) $institution['country'][0] : '';

	$rows[ $institution['id'] ] = array(
		'record_id'      => $institution['id'],
		'name'           => $institution['name'],
		'stage'          => $institution['stage'],
		'country'        => $country,
		'country_name'   => '' !== $country && isset( $countries[ $country ] ) ? $countries[ $country ]['name'] : '',
		'city'           => $institution['city'],
		'website'        => $institution['website'],
		'contact_person' => $institution['has_contact_person'] ? 'A Person' : '',
		'contact_email'  => $institution['has_contact_email'] ? strtolower( $institution['id'] ) . '@example.test' : '',
		'created'        => substr( $institution['createdTime'], 0, 10 ),
		'consent'        => (bool) $institution['consent'],
		'confirmed_on'   => $institution['confirmed_on'],
		'agreement'      => $institution['agreement'],
	);
}

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]        = array_merge( WPCPM_Settings::defaults(), array( 'api_token' => 'pat', 'base_id' => 'appIzQKfwTn5dyPVp' ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array( 'v' => 1, 'read' => $read_at, 'rows' => $rows );
$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]          = array( 'v' => 1, 'read' => $read_at - 600, 'rows' => $countries );
$GLOBALS['opts'][ WPCPM_Roster_Index::OPT_COUNTS ] = array(
	'v'              => 1,
	'read'           => $read_at - 3600,
	'institutions'   => array(),
	'reconciliation' => array(
		'students_without_reports' => array( 'Not moving forward' => 15, '' => 7, 'Graduate' => 6, 'In Sensei' => 2, 'SPAM' => 1 ),
		'reports_without_students' => array( 'In Sensei' => 7, 'Graduate' => 6, 'Not moving forward' => 4, '' => 1, 'SPAM' => 1 ),
		'status_disagreements'     => 10,
		'status_disagreement_rows' => array(
			array( 'name' => 'Disagreeing One', 'students_record' => 'recSTUDIS0000001', 'reports_record' => 'recREPDIS0000001', 'students_status' => 'Graduate', 'reports_status' => 'In Sensei' ),
			array( 'name' => 'Disagreeing <b>Two</b>', 'students_record' => 'recSTUDIS0000002', 'reports_record' => 'recREPDIS0000002', 'students_status' => '', 'reports_status' => 'Paused' ),
		),
		'duplicate_emails'         => array( 'recSEED0000000008' => 5, 'recUNKNOWN0000001' => 4 ),
		'no_institution'           => 3,
		'no_start_date'            => array( 'Not moving forward' => 4, '' => 2, 'Developer Track' => 1 ),
		'mentored_without_reports' => array(
			array( 'students_record' => 'recSTUMISS0000001', 'name' => 'Mismatched Student', 'email' => 'mismatched@school.example.test', 'institution' => 'recSEED0000000008', 'status' => 'In Sensei', 'outcome' => 'unmatched', 'reports_record' => 'recREPMISS0000001', 'reports_email' => 'mismatched@home.example.test', 'joined_to' => '', 'joined_to_institution' => '' ),
			array( 'students_record' => 'recSTUTWICE000001', 'name' => 'Twice Filed', 'email' => 'twice@school.example.test', 'institution' => 'recSEED0000000008', 'status' => 'In Sensei', 'outcome' => 'elsewhere', 'reports_record' => 'recREPTWICE00001', 'reports_email' => 'twice@home.example.test', 'joined_to' => 'recSTUTWICE000002', 'joined_to_institution' => 'recUNKNOWN0000001' ),
			array( 'students_record' => 'recSTULONELY00001', 'name' => 'Lonely Mentored', 'email' => 'lonely@school.example.test', 'institution' => 'recSEED0000000008', 'status' => 'Developer Track', 'outcome' => 'none', 'reports_record' => '', 'reports_email' => '', 'joined_to' => '', 'joined_to_institution' => '' ),
		),
	),
);
$GLOBALS['opts'][ WPCPM_Roster_Index::OPT_UNLINKED ] = array(
	'v'    => 1,
	'read' => $read_at - 3600,
	'rows' => array(
		'recS1' => array( 'record_id' => 'recS1', 'name' => 'Unlinked One', 'email' => 'u1@example.test', 'email_key' => 'u1@example.test', 'status' => 'In Sensei', 'institution' => '', 'start' => '', 'end' => '', 'has_mentor' => false, 'username' => '', 'field_of_study' => '', 'tutor' => '', 'import_key' => '', 'reports' => array(), 'user_id' => 0 ),
		'recS2' => array( 'record_id' => 'recS2', 'name' => 'Unlinked Two', 'email' => 'u2@example.test', 'email_key' => 'u2@example.test', 'status' => '', 'institution' => '', 'start' => '', 'end' => '', 'has_mentor' => false, 'username' => '', 'field_of_study' => '', 'tutor' => '', 'import_key' => '', 'reports' => array(), 'user_id' => 0 ),
		'recS3' => array( 'record_id' => 'recS3', 'name' => ' Unlinked Three ', 'email' => '', 'email_key' => '', 'status' => 'Graduate', 'institution' => '', 'start' => '', 'end' => '', 'has_mentor' => false, 'username' => '', 'field_of_study' => '', 'tutor' => '', 'import_key' => '', 'reports' => array(), 'user_id' => 0 ),
	),
);

$GLOBALS['users'][1]  = new WP_User( 1, 'Ada Admin', 'admin@example.test', array( 'administrator' ) );
$GLOBALS['users'][2]  = new WP_User( 2, 'Max Manager', 'max@example.test', array( 'administrator' ) );
$GLOBALS['users'][3]  = new WP_User( 3, 'No Address', '', array( 'administrator' ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Sam Student', 'sam@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][31] = new WP_User( 31, 'Sue Student', 'sue@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['manage']    = array( 1, 2, 3 );

// Two students the way a finished sync leaves them: stamped, flagged active, and with the
// program meta naming which table's word the stamp is on.
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_INSTITUTION ] = 'recSEED0000000008';
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_ACTIVE ]      = 1;
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_PROGRAM ]     = array( 'institution_source' => 'students' );
$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_INSTITUTION ] = 'recSEED0000000011';
$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_ACTIVE ]      = 1;
$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_PROGRAM ]     = array( 'institution_source' => 'reports' );

/* ---- source: the skeleton and the order of checks ----------------------- */

echo "=== Source: the skeleton and the handler order ===\n";

$src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );

ck( 'the screen keeps div.wrap.wpcpm-wrap > h1 > p.wpcpm-lede', array(
	false !== strpos( $src, "echo '<div class=\"wrap wpcpm-wrap\">';" ),
	false !== strpos( $src, "echo '<h1>' . esc_html( \$this->label() ) . '</h1>';" ),
	false !== strpos( $src, "echo '<p class=\"wpcpm-lede\">' . esc_html( \$this->description() ) . '</p>';" ),
), array( true, true, true ) );
ck( 'and draws its cards as .wpcpm-card', substr_count( $src, "'<div class=\"wpcpm-card\">'" ) >= 6, true );

// Every handler: the capability is decided before the nonce is read, so an anonymous request
// gets the 403 the design names rather than a nonce failure that tells it the handler exists.
// The three sync handlers live on WPCPM_Sync_Module since 1.90.0, shared with the Students and
// Mentors modules, verify() on the module base every module's screen shares (WPCPM_Module), and a
// row's invitation on the screen plumbing every audience's accounts list shares
// (WPCPM_Accounts_Screen); the scan reads those three sources after this module's own.
$handler_src = $src . (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php' ) . (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php' ) . (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php' );
preg_match_all( '/public function (handle_[a-z_]+)\s*\(/', $handler_src, $handlers );
ck( 'the fifteen handlers exist', $handlers[1], array(
	// The account presses of the Accounts tab: one institution's Create account, and the
	// invitations card's button. Create account on the ticked institutions and the invitations on
	// the ticked accounts are the list's own form, handled on the screen's load hook.
	'handle_probe', 'handle_provision_one', 'handle_bulk_invite',
	// Linking an unlinked Students row to an institution, which used to be a sentence saying
	// it would ship later.
	'handle_link',
	'handle_approve', 'handle_info', 'handle_reject', 'handle_spam', 'handle_reopen',
	// Sending the confirmation link again, on an open application whose address is unconfirmed
	// (1.123.26).
	'handle_resend_link', 'handle_purge',
	// From the shared sync module.
	'handle_tick', 'handle_sync', 'handle_cancel',
	// A row's invitation, from the shared accounts screen.
	'handle_invite',
) );

foreach ( $handlers[1] as $handler ) {
	$body  = function_body( $handler_src, $handler );
	$cap   = strpos( $body, 'current_user_can' );
	$via   = strpos( $body, '$this->verify(' );
	$nonce = strpos( $body, 'check_admin_referer' );
	$ajax  = strpos( $body, 'check_ajax_referer' );
	$first = false !== $cap ? $cap : $via;
	$check = false !== $nonce ? $nonce : $ajax;

	ck( sprintf( '%s decides the capability before reading a nonce', $handler ), array(
		false !== $first,
		false === $check || $first < $check,
		false === $nonce || false !== $via,
	), array( true, true, true ) );
}

$verify = function_body( $handler_src, 'verify' );
ck( 'verify() itself checks the capability first', array(
	false !== strpos( $verify, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ),
	strpos( $verify, 'current_user_can' ) < strpos( $verify, 'check_admin_referer( $action )' ),
	false !== strpos( $verify, "wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 )" ),
), array( true, true, true ) );

ck( 'no institution id is compared with === in the screen',
	preg_match( '/\$record_id\s*===|===\s*\$record_id|\$country\s*===\s*\$|\$institution\s*===/', $src ), 0 );

// The provisioning rule has one copy, in the sync. The screen asks it and prints the answer;
// a second copy here is how the button and the next sync run come to disagree about the same
// institution, which is the whole reason the rule was written down once.
$reasons_body = function_body( $src, 'provision_reasons' );
ck( 'the screen asks the sync why an institution may not be provisioned, and decides nothing itself', array(
	false !== strpos( $reasons_body, 'WPCPM_Institutions_Sync::provision_block(' ),
	strpos( $reasons_body, 'is_settled' ),
	strpos( $reasons_body, 'members_of' ),
	strpos( $reasons_body, 'get_user_by' ),
), array( true, false, false, false ) );
// One read exception, and design spec 7.2 asks for it by name: an application somebody has
// opened is searched for in the base, because "has this institution applied before" cannot be
// answered from a copy that is a day old. The one write is the link control, which is a write
// handler and so builds its own client the way every other write handler in this plugin does.
// What the assertion is really guarding is unchanged: the queue's own list reads nothing, and
// nothing anywhere pages the table.
ck( 'the screen reads Airtable in one place, writes in one, and pages nothing', array(
	substr_count( $src, 'new WPCPM_Airtable' ),
	false !== strpos( function_body( $src, 'duplicate_search' ), 'new WPCPM_Airtable' ),
	false !== strpos( function_body( $src, 'handle_link' ), 'new WPCPM_Airtable' ),
	strpos( $src, 'fetch_all' ),
	strpos( function_body( $src, 'queue_rows' ), 'WPCPM_Airtable' ),
), array( 2, true, true, false, false ) );
ck( 'the screen never renders the option key of a file URL', strpos( $src, 'base_url' ), false );
ck( 'no em or en dash anywhere in the module', preg_match( "/\xE2\x80\x93|\xE2\x80\x94/", $src ), 0 );

$files_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php' );
ck( 'nor in the private-files class', preg_match( "/\xE2\x80\x93|\xE2\x80\x94/", $files_src ), 0 );
ck( 'nor in the CSS section', preg_match( "/\xE2\x80\x93|\xE2\x80\x94/", substr( file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' ), strpos( file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' ), 'Institutions screen' ) ) ), 0 );
// The key's own add_option() moved into WPCPM_Secret in 1.94.0, so the
// option-writing this assertion is about now spans both files; comment lines are stripped
// first from each, because both writers explain themselves in prose that names the function,
// and counting that prose would make this assertion pass or fail for the wrong reason.
$secret_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php' );
$strip_comments = function ( $src ) {
	return implode( "\n", array_filter( explode( "\n", $src ), function ( $line ) {
		$t = ltrim( $line );
		return '' !== $t && 0 !== strpos( $t, '*' ) && 0 !== strpos( $t, '//' ) && 0 !== strpos( $t, '/*' );
	} ) );
};
$files_code = $strip_comments( $files_src ) . "\n" . $strip_comments( $secret_src );
ck( 'every option the store writes is kept out of the autoloaded set', array(
	preg_match_all( '/update_option\(/', $files_code ),
	preg_match_all( '/update_option\([^;]*,\s*false\s*\);/s', $files_code ),
	// `add_option()` takes the flag in a fourth argument; the key must not be loaded on every
	// request of every page either.
	preg_match_all( '/add_option\(/', $files_code ),
	preg_match_all( '/add_option\([^;]*,\s*false\s*\)/s', $files_code ),
), array( 3, 3, 1, 1 ) );

/* ---- the screen, rendered from the seed fixture ------------------------- */

echo "\n=== The screen, rendered from the seed fixture ===\n";

/**
 * Stand one semester report up in the store.
 *
 * @param int    $id      Post ID.
 * @param string $record  Institutions record ID.
 * @param string $cohort  Cohort key.
 * @param string $state   `draft` or `approved`.
 * @param int    $at      When it was generated and last edited, unix time.
 * @param string $status  Post status, so a trashed report can be seeded too.
 * @return WP_Post
 */
function seed_report( $id, $record, $cohort, $state, $at, $status = 'private', $origin = '' ) {
	$post                    = new WP_Post();
	$post->ID                = (int) $id;
	$post->post_type         = WPCPM_Semester_Report::POST_TYPE;
	$post->post_status       = $status;
	$post->post_title        = 'Report ' . $cohort;
	$post->post_date_gmt     = gmdate( 'Y-m-d H:i:s', (int) $at );
	$post->post_modified_gmt = gmdate( 'Y-m-d H:i:s', (int) $at );

	$GLOBALS['posts'][ (int) $id ] = $post;
	$GLOBALS['pmeta'][ (int) $id ] = array();

	update_post_meta( $id, '_wpcpm_report_institution', $record );
	update_post_meta( $id, '_wpcpm_report_cohort', $cohort );
	update_post_meta( $id, '_wpcpm_report_state', $state );
	update_post_meta( $id, '_wpcpm_report_generated', (int) $at );

	if ( '' !== $origin ) {
		update_post_meta( $id, '_wpcpm_report_origin', $origin );
	}

	return $post;
}

$report_record = (string) $seed['institutions'][1]['id'];

seed_report( 9101, $report_record, '2026-H1', 'draft', 1756000000, 'private', 'auto' );
seed_report( 9102, $report_record, '2025-H2', 'approved', 1757000000 );
// Trashed by hand: a document somebody withdrew, which is not a row to list.
seed_report( 9103, $report_record, '2025-H1', 'draft', 1754000000, 'trash' );

$GLOBALS['due']        = array( array( 'institution' => $report_record, 'cohort' => '2024-H2', 'in_progress' => 0, 'window_end' => '2024-12-31' ) );
$GLOBALS['report_log'] = array( array( 'event' => 'approved', 'institution' => $report_record, 'cohort' => '2025-H2', 'actor' => 1, 'at' => 1755000000 ) );

// The six tabs, in the bar's order, each drawn once on the same fixture at the address that names
// it, and the screen's own address, which names none.
$slugs     = array( 'queue', 'pipeline', 'accounts', 'reports', 'agreements', 'sync' );
$tabs      = array();
$confirmed = array_keys( array_filter( $rows, function ( $r ) { return 'Confirmed' === $r['stage']; } ) );

// The Pipeline tab's counts leave out the four stages a record is set aside at (1.123.29): SPAM,
// Not Moving Forward, Revisit Later and no stage, read trimmed. Written out here rather than read
// off the class, so a list that loses or gains a stage fails below. The fixture holds 17 of them.
$uncounted_stages = array( 'SPAM', 'Not Moving Forward', 'Revisit Later', '' );
$counted_of       = function ( array $of ) use ( $uncounted_stages ) {
	return array_filter( $of, function ( $r ) use ( $uncounted_stages ) { return ! in_array( trim( (string) $r['stage'] ), $uncounted_stages, true ); } );
};
$counted          = $counted_of( $rows );

foreach ( $slugs as $slug ) {
	$tabs[ $slug ] = render_tab( $slug );
}

$bare = render_screen();

$skeleton = array();
foreach ( $tabs as $slug => $drawn ) {
	$skeleton[ $slug ] = 0 === strpos( $drawn, '<div class="wrap wpcpm-wrap"><h1>Institutions</h1><p class="wpcpm-lede">' ) && '</div>' === substr( $drawn, -6 );
}
ck( 'every tab opens with the skeleton', $skeleton, array_fill_keys( $slugs, true ) );

echo "\n=== Six tabs by job, in the bar every audience screen prints ===\n";

ck( 'the screen holds six tabs, the queue first, in the words a manager knows each job by', WPCPM_Institutions::TABS, array(
	'queue'      => 'Waiting for review',
	'pipeline'   => 'Pipeline',
	'accounts'   => 'Accounts',
	'reports'    => 'Semester reports',
	'agreements' => 'Agreements',
	'sync'       => 'Sync and storage',
) );

$home    = 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions';
$bar_for = function ( $shown ) use ( $home, $slugs ) {
	$labels = array_combine( $slugs, array( 'Waiting for review', 'Pipeline', 'Accounts', 'Semester reports', 'Agreements', 'Sync and storage' ) );
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
ck( 'the bar is the screen\'s one bar, right under its title and lede when no notice is printed, and no heading holds it', array(
	substr_count( $bare, 'nav-tab-wrapper' ),
	false !== strpos( $bare, '</p><nav class="nav-tab-wrapper wp-clearfix"' ),
	preg_match( '#<h[1-6][^>]*nav-tab-wrapper#', $bare ),
), array( 1, true, 0 ) );

$GLOBALS['l10n'] = array(
	'Waiting for review' => 'En espera de revisión',
	'Pipeline'           => 'Embudo',
	'Accounts'           => 'Cuentas',
	'Semester reports'   => 'Informes semestrales',
	'Agreements'         => 'Convenios',
	'Sync and storage'   => 'Sincronización y almacenamiento',
	'Secondary menu'     => 'Menú secundario',
);
$spanish         = bar_of( render_tab( 'agreements' ) );
$GLOBALS['l10n'] = array();
ck( 'the labels are translated where the bar prints them, and so is the name the bar gives itself',
	null === $spanish ? null : array( $spanish[0], array_column( $spanish[1], 1 ) ),
	array( 'Menú secundario', array( 'En espera de revisión', 'Embudo', 'Cuentas', 'Informes semestrales', 'Convenios', 'Sincronización y almacenamiento' ) ) );

echo "\n=== Each tab draws its own cards and no other tab's ===\n";

ck( 'each tab draws the cards of its job and none of another tab\'s', array_map( 'cards_of', $tabs ), array(
	'queue'      => array( 'Waiting for review' ),
	'pipeline'   => array( 'Pipeline', 'Consent' ),
	'accounts'   => array( 'Invitations', 'Institution accounts' ),
	'reports'    => array( 'Semester reports' ),
	'agreements' => array( 'Agreements on file', 'Agreements in force', 'Agreement discrepancies', 'Agreement template' ),
	'sync'       => array( 'Airtable sync', 'Reconciliation', 'Storage' ),
) );

// What each tab offers to press, by the action its forms post: the sync and the probe on Sync and
// storage alone. Agreements offers none while no agreement is in force (the fixture's, here): its
// first card lists the institutions with no agreement recorded and links each to its own page, where
// one agreement is recorded, and its second draws a revoke form for each agreement in force, which
// is checked where it is drawn. The Accounts
// tab's presses are its list's, a form to the screen itself, and the invitations card's, which
// offers none while nobody is waiting for one.
$presses = function ( $html ) {
	preg_match_all( '#<input type="hidden" name="action" value="([^"]+)" />#', (string) $html, $m );
	return array_values( array_unique( $m[1] ) );
};
ck( 'each tab offers the presses of its own cards and no other tab\'s', array_map( $presses, $tabs ), array(
	'queue'      => array(),
	'pipeline'   => array(),
	'accounts'   => array(),
	'reports'    => array( 'wpcpm_report_ask', 'wpcpm_report_draft' ),
	'agreements' => array(),
	'sync'       => array( 'wpcpm_institutions_sync', 'wpcpm_institutions_probe' ),
) );

// The field a press comes back by. The semester report forms name none: their press opens the
// report it drafted or asked about, on the Institution Dashboard, and comes back to no tab here.
// The queue's list draws no form; the forms on an opened application name no tab either, and their
// presses come back to the screen's own address, which is the queue (checked where they are drawn).
$tab_fields = function ( $html ) {
	preg_match_all( '#<input type="hidden" name="wpcpm_tab" value="([^"]*)" />#', (string) $html, $m );
	return array_count_values( $m[1] );
};
ck( 'every form whose press comes back to a tab other than the queue names it', array_map( $tab_fields, $tabs ), array(
	'queue'      => array(),
	'pipeline'   => array(),
	'accounts'   => array(),
	'reports'    => array(),
	'agreements' => array(),
	'sync'       => array( 'sync' => 2 ),
) );

echo "\n=== Each tab reads what it draws ===\n";

// The membership counts are a user query per institution, a hundred and four of them here, and the
// provisioning reasons up to two more per Confirmed one, which the Accounts tab's list asks for the
// count its No account view carries: a tab that does not print them must not pay for them. Pipeline
// asks them of the institutions it counts, 87 of the 104, and Sync and storage of every one.
$reads = array();
foreach ( $slugs as $slug ) {
	render_tab( $slug );
	$reads[ $slug ] = array( count( $GLOBALS['member_reads'] ), count( $GLOBALS['blocks_read'] ), $GLOBALS['progress_reads'], $GLOBALS['counts_reads'] );
}
ck( 'the membership counts are asked on Pipeline, of the institutions it counts, and on Sync and storage, of every one, the provisioning reasons on Accounts alone, and the sync\'s progress and the roster counts on Sync and storage alone', $reads, array(
	'queue'      => array( 0, 0, 0, 0 ),
	'pipeline'   => array( count( $counted ), 0, 0, 0 ),
	'accounts'   => array( 0, count( $confirmed ), 0, 0 ),
	'reports'    => array( 0, 0, 0, 0 ),
	'agreements' => array( 0, 0, 0, 0 ),
	'sync'       => array( count( $rows ), 0, 1, 1 ),
) );

echo "\n=== The notices, on every tab ===\n";

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] = '';
$warned = array();
foreach ( $slugs as $slug ) {
	$drawn           = render_tab( $slug );
	$at              = strpos( $drawn, '<div class="notice notice-warning"><p>Airtable is not connected yet, so no institutions can be synced. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-settings">Open settings</a></p></div>' );
	$warned[ $slug ] = false !== $at && $at < (int) strpos( $drawn, '<nav class="nav-tab-wrapper' );
}
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] = 'pat';
ck( 'while Airtable is not connected every tab says so, above its bar and its cards', $warned, array_fill_keys( $slugs, true ) );

// A mentor request decided without the Administrator Dashboard's return field goes back to the
// screen's own address, the queue, and its outcome is in the request class's words. Read as a
// manager of its own, because `WPCPM_Flash::take()` memoizes per person and per channel for the
// life of a request.
$GLOBALS['uid'] = 60;
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'request-done' );
$request_notice = render_screen();
$GLOBALS['uid'] = 1;
$printed_at     = strpos( $request_notice, '<div class="notice notice-success is-dismissible"><p>That request is closed as handled.</p></div>' );
ck( 'a mentor request decided without the dashboard\'s return lands on the queue, and its outcome prints there, above the bar', array(
	false !== $printed_at && $printed_at < (int) strpos( $request_notice, '<nav class="nav-tab-wrapper' ),
	cards_of( $request_notice ),
), array( true, array( 'Waiting for review' ) ) );
delete_user_meta( 60, WPCPM_Flash::META );

// A manager's upload on the institution's behalf, from the Manage members view, comes back there,
// and the frame words it for the person who pressed: the panel's own sentence is the institution's,
// which a program manager reviews.
$GLOBALS['uid'] = 67;
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-uploaded' );
$uploaded_notice = render_tab( 'accounts' );
$GLOBALS['uid']  = 1;
ck( 'a manager\'s upload is told where the agreement waits and who was emailed, in words for the person who pressed', array(
	false !== strpos( $uploaded_notice, '<div class="notice notice-success is-dismissible"><p>The signed agreement is uploaded. It waits on the Waiting for review tab, and everybody at the institution has been emailed that it arrived.</p></div>' ),
	strpos( $uploaded_notice, 'An Administrator reviews it' ),
), array( true, false ) );
delete_user_meta( 67, WPCPM_Flash::META );

// So is a withdrawal there: the panel's sentence asks the institution to upload another whenever it
// is ready, which the manager does from the same view, once the institution sends one.
$GLOBALS['uid'] = 644;
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-withdrawn' );
$withdrawn_notice = render_tab( 'accounts' );
$GLOBALS['uid']   = 1;
ck( 'a manager\'s withdrawal on the institution\'s behalf is told the file is gone and where another is uploaded, in words for the person who pressed', array(
	false !== strpos( $withdrawn_notice, '<div class="notice notice-success is-dismissible"><p>The signed agreement is withdrawn and its file is deleted. Upload another from this view when the institution sends one.</p></div>' ),
	strpos( $withdrawn_notice, 'Upload another whenever you are ready.' ),
), array( true, false ) );
delete_user_meta( 644, WPCPM_Flash::META );

$GLOBALS['locked'] = array( new WP_User( 50, 'Rep One', 'rep.one@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ) );
$locked_on         = array();
foreach ( $slugs as $slug ) {
	$locked_on[ $slug ] = substr_count( render_tab( $slug ), '1 institution account is locked out of roster changes for the rest of today.' );
}
$locked_tab        = render_tab( 'accounts' );
$GLOBALS['locked'] = array();
ck( 'the accounts locked for the day are named on the Accounts tab and on no other', $locked_on, array( 'queue' => 0, 'pipeline' => 0, 'accounts' => 1, 'reports' => 0, 'agreements' => 0, 'sync' => 0 ) );
ck( 'by display name and username, and never by address', array( false !== strpos( $locked_tab, 'Rep One (repone)' ), strpos( $locked_tab, 'rep.one@example.test' ) ), array( true, false ) );

echo "\n=== The pipeline, on its tab ===\n";

$html = render_tab( 'pipeline' );

ck( 'the pipeline counts the fixture rows at a counted stage, 87 of its 104', array( count( $counted ), false !== strpos( $html, 'Pipeline <span class="wpcpm-count">' . count( $counted ) . '</span>' ) ), array( $seed['counts']['institutions'] - 17, true ) );

$headings = stage_headings( $html );
$expected = array();
foreach ( WPCPM_Institution_Agreement::STAGE_ORDER as $stage ) {
	if ( isset( $seed['counts']['by_stage'][ $stage ] ) ) { $expected[ $stage ] = (int) $seed['counts']['by_stage'][ $stage ]; }
}
ck( 'the groups above the line are the fixture\'s counted stages and their counts, in STAGE_ORDER', $headings, $expected );
ck( 'the three terminal stages fold under it, closed, in the order they always had, each with its count', folded_groups( $html ), array(
	'Not Moving Forward' => array( (int) $seed['counts']['by_stage']['Not Moving Forward'], false ),
	'SPAM'               => array( (int) $seed['counts']['by_stage']['SPAM'], false ),
	'Revisit Later'      => array( (int) $seed['counts']['by_stage']['Revisit Later'], false ),
) );
ck( 'and no group is drawn for the empty stage the fixture does not have', array( isset( $headings['No stage'] ), isset( folded_groups( $html )['No stage'] ) ), array( false, false ) );

// The read time, once per card that reads the index (the pipeline and the consent report on the
// Pipeline tab, the agreements on file, the agreements in force, the discrepancies and the template
// versions on the Agreements tab) and once for
// the countries map and the roster counts, each on the tab of the card that reads it. A stale
// count must never look fresh.
$read_lines = function ( $needle ) use ( $tabs ) {
	return array_map( function ( $drawn ) use ( $needle ) { return substr_count( (string) $drawn, $needle ); }, $tabs );
};
ck( 'the index read time is printed with the date and the age, once by each card that reads the index, on its tab', $read_lines( 'Pipeline index: read ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).' ), array( 'queue' => 0, 'pipeline' => 2, 'accounts' => 0, 'reports' => 0, 'agreements' => 4, 'sync' => 0 ) );
ck( 'so is the roster counts\' read time, on Sync and storage', $read_lines( 'Roster counts: read ' . gmdate( 'Y-m-d H:i', $read_at - 3600 ) . ' (4 hours ago).' ), array( 'queue' => 0, 'pipeline' => 0, 'accounts' => 0, 'reports' => 0, 'agreements' => 0, 'sync' => 1 ) );
ck( 'and the countries map\'s, on Pipeline', $read_lines( 'Countries map: read ' . gmdate( 'Y-m-d H:i', $read_at - 600 ) ), array( 'queue' => 0, 'pipeline' => 1, 'accounts' => 0, 'reports' => 0, 'agreements' => 0, 'sync' => 0 ) );

// Names: trimmed, with the mark where the stored one was not.
$trailing = array();
foreach ( $seed['institutions'] as $i ) { if ( $i['name'] !== rtrim( $i['name'] ) ) { $trailing[ $i['id'] ] = $i['name']; } }
ck( 'the fixture has ten names ending in a space', count( $trailing ), $seed['counts']['trailing_space_names'] );

$trimmed_ok = true;
foreach ( $trailing as $id => $name ) {
	$trimmed_ok = $trimmed_ok
		&& false !== strpos( $html, '<span class="wpcpm-inst-name__text"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $id . '">' . esc_html( trim( $name ) ) . '</a></span>' )
		&& false === strpos( $html, esc_html( $name ) . '</a>' );
}
ck( 'every such name prints trimmed', $trimmed_ok, true );
ck( 'and carries the whitespace mark', substr_count( $html, 'wpcpm-inst-mark--space' ), $seed['counts']['trailing_space_names'] );
ck( 'the two nameless records are marked, not printed blank', substr_count( $html, 'wpcpm-inst-mark--empty' ), $seed['counts']['nameless'] );
// Proven with a row the suite empties: the two records that had no name on 2 September were
// deleted by a program manager the same day, and this has to keep working for the next one.
$blank_id   = $seed['institutions'][0]['id'];
$kept       = get_option( WPCPM_Institutions_Index::OPT_NAME );
$with_blank = $kept;
$with_blank['rows'][ $blank_id ]['name'] = '';
update_option( WPCPM_Institutions_Index::OPT_NAME, $with_blank, false );
$blank_html = render_tab( 'pipeline' );
update_option( WPCPM_Institutions_Index::OPT_NAME, $kept, false );
ck( 'a nameless record is marked rather than printed blank', substr_count( $blank_html, 'wpcpm-inst-mark--empty' ), 1 );
ck( 'and shows its record id so it can be found in the grid', false !== strpos( $blank_html, '<code class="wpcpm-inst-record">' . $blank_id . '</code>' ), true );

// Countries: every one an institution names resolves; the ones with no contact are marked.
$named_without_contact = 0;
foreach ( $rows as $row ) {
	if ( '' !== $row['country'] && null === WPCPM_Countries::routing( $row['country'] ) ) { $named_without_contact++; }
}
ck( 'the fixture names three countries with no contact', count( array_unique( array_map( function ( $r ) { return $r['country']; }, array_filter( $rows, function ( $r ) { return '' !== $r['country'] && null === WPCPM_Countries::routing( $r['country'] ); } ) ) ) ), $seed['counts']['countries_used_without_contact'] );
ck( 'each row naming one carries the no-contact mark, in the folded stages too', substr_count( $html, 'wpcpm-inst-mark--routing' ), $named_without_contact );
// Cambodia, Nigeria and Thailand are named only by institutions at Not Moving Forward, which the counts
// leave out, so the line under the counts names none of them. A counted institution naming one is
// listed by name below, where the Review Focus rows are drawn.
$gap_rows = array_filter( $rows, function ( $r ) { return '' !== $r['country'] && null === WPCPM_Countries::routing( $r['country'] ); } );
ck( 'every row naming one is at Not Moving Forward, so the routing line, over the counted rows, says every country has a contact', array(
	array_values( array_unique( array_column( $gap_rows, 'stage' ) ) ),
	preg_match( '/Countries named by institutions with no Administrator contact/', $html ),
	false !== strpos( $html, '<p>Every country an institution names has an Administrator contact.</p>' ),
), array( array( 'Not Moving Forward' ), 0, true ) );
ck( 'no row prints an unresolved country', strpos( $html, 'unknown country' ), false );
ck( 'the rows with no country say so', substr_count( $html, '>no country<' ), count( array_filter( $rows, function ( $r ) { return '' === $r['country']; } ) ) );

// Contact and consent columns. Once per pipeline row with no address. The Confirmed ones are
// listed again on the Accounts tab's No account view, whose Contact column says the same, and
// bin/test-institutions-accounts.php reads that view.
$no_email = array_filter( $rows, function ( $r ) { return '' === $r['contact_email']; } );
ck( 'the records with no email are marked, each on the pipeline', array(
	substr_count( $html, '<span class="wpcpm-warning">no email</span>' ),
	substr_count( $tabs['accounts'], '<span class="wpcpm-warning">no email</span>' ),
), array( count( $no_email ), 0 ) );
ck( 'no address is printed on any tab', preg_match( '/@example\.test/', implode( '', $tabs ) ), 0 );

// The agreement column reads the summary, once per row.
ck( 'one summary is read per row', count( array_unique( $GLOBALS['summary_reads'] ) ), count( $rows ) );
ck( 'with nothing recorded every row reads Not started', substr_count( $html, '<td class="wpcpm-inst-agreement">Not started</td>' ), count( $rows ) );

/* ---- the agreement-gap filter ------------------------------------------- */

echo "\n=== The agreement-gap filter ===\n";

ck( 'on day one the link counts every Confirmed row', false !== strpos( $html, 'Confirmed with no agreement recorded <span class="wpcpm-count">' . $seed['counts']['by_stage']['Confirmed'] . '</span></a>' ), true );
ck( 'and points at the sanitised filter argument, on the Pipeline tab', false !== strpos( $html, '?page=wpcpm-institutions&tab=pipeline&wpcpm_filter=agreement_gap' ), true );

// Three Confirmed institutions settle: two recorded in the grid, one on the site.
$GLOBALS['summaries'] = array(
	$confirmed[0] => array( 'state' => 'on_file', 'kind' => 'legacy', 'accepted_at' => '2026-09-02', 'airtable_status' => 'On file', 'route' => 'grid' ),
	$confirmed[1] => array( 'state' => 'on_file', 'kind' => 'legacy', 'accepted_at' => '2026-09-02', 'airtable_status' => 'On file', 'route' => 'grid' ),
	$confirmed[2] => array( 'state' => 'accepted', 'kind' => 'template', 'accepted_at' => '2026-09-01', 'airtable_status' => 'Accepted', 'route' => 'site' ),
	// A submitted one does not settle, and neither does an accepted post Airtable disagrees with.
	$confirmed[3] => array( 'state' => 'submitted', 'kind' => 'own', 'airtable_status' => 'Awaiting review', 'route' => 'site' ),
);

$html = render_tab( 'pipeline' );

ck( 'three settled rows leave 39 in the gap', false !== strpos( $html, 'Confirmed with no agreement recorded <span class="wpcpm-count">' . ( $seed['counts']['by_stage']['Confirmed'] - 3 ) . '</span></a>' ), true );
ck( 'the agreement column names state, kind, date and route', array(
	false !== strpos( $html, '<td class="wpcpm-inst-agreement wpcpm-inst-agreement--settled">On file, legacy, accepted 2026-09-02, recorded in the Airtable grid</td>' ),
	false !== strpos( $html, '<td class="wpcpm-inst-agreement wpcpm-inst-agreement--settled">Accepted, program template, accepted 2026-09-01, recorded on the site</td>' ),
	false !== strpos( $html, '<td class="wpcpm-inst-agreement">Awaiting review, institution-specific, recorded on the site</td>' ),
), array( true, true, true ) );

$filtered = render_tab( 'pipeline', array( 'wpcpm_filter' => 'agreement_gap' ) );
$headings = stage_headings( $filtered );

ck( 'the filtered view draws one group of 39 Confirmed rows', $headings, array( 'Confirmed' => $seed['counts']['by_stage']['Confirmed'] - 3 ) );
ck( 'none of which is settled', strpos( $filtered, 'wpcpm-inst-agreement--settled' ), false );
ck( 'and the way back is offered, to the Pipeline tab', false !== strpos( $filtered, 'Showing the 39 Confirmed institutions with no agreement recorded. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=pipeline">Show every stage</a>' ), true );

$junk = render_tab( 'pipeline', array( 'wpcpm_filter' => '<script>agreement_gap' ) );
ck( 'a filter value that is not the one offered shows every stage', count( stage_headings( $junk ) ), count( $expected ) );

/* ---- the consent report ------------------------------------------------- */

echo "\n=== The consent report ===\n";

// Over the counted rows, as every count on the tab is: of the fixture's 84 records from before the
// question, the 16 at a stage left out are not counted, and all 38 at Confirmed are.
$before = 0;
foreach ( $counted as $row ) { if ( ! $row['consent'] && '' !== $row['created'] && strcmp( $row['created'], '2026-07-20' ) < 0 ) { $before++; } }
$sentence = sprintf(
	'%d institution records were collected before the consent question was added on 20 July 2026, %d of them at Confirmed.',
	$before,
	$seed['counts']['created_before_consent_question_confirmed']
);
ck( 'the sentence counts the counted records: 68 of the fixture\'s 84, and its 38 at Confirmed', array( $before, false !== strpos( $html, '<p class="wpcpm-inst-consent">' . $sentence . '</p>' ) ), array( $seed['counts']['created_before_consent_question'] - 16, true ) );

$since = 0;
foreach ( $counted as $row ) { if ( ! $row['consent'] && strcmp( $row['created'], '2026-07-20' ) >= 0 ) { $since++; } }
ck( 'and the count of hand-entered records since', false !== strpos( $html, 'Since then, ' . $since . ' records have been created without the tick' ), true );
ck( 'the word "lost" appears on no tab', stripos( implode( '', $tabs ), 'lost' ), false );
ck( 'nor does "modules", a word for the plugin\'s code and not for anything a manager reads', stripos( implode( '', $tabs ), 'modules' ), false );
ck( 'nor in the module source', stripos( $src, 'lost' ), false );

// A record created on the boundary day counts as after it, whatever timezone the site is in.
$boundary = $rows;
$boundary['recBOUNDARY0000001'] = array_merge( reset( $rows ), array( 'record_id' => 'recBOUNDARY0000001', 'name' => 'Boundary', 'stage' => 'Confirmed', 'created' => '2026-07-20', 'consent' => false ) );
$boundary['recEVE000000000001'] = array_merge( reset( $rows ), array( 'record_id' => 'recEVE000000000001', 'name' => 'Eve', 'stage' => 'Confirmed', 'created' => '2026-07-19', 'consent' => false ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $boundary;
$edge = render_tab( 'pipeline' );
ck( '20 July itself is after the question, 19 July before it', false !== strpos( $edge, sprintf( '%d institution records were collected before the consent question was added on 20 July 2026, %d of them at Confirmed.', $before + 1, $seed['counts']['created_before_consent_question_confirmed'] + 1 ) ), true );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;

/* ---- what the Pipeline counts, what it folds, and the way to a record ---- */

echo "\n=== The Pipeline's counts, its folded stages, its names and its field ===\n";

// Review Focus 4. A stage stored with a space around it is the stage it names, so "SPAM " is left out
// of every count as "SPAM" is and folds with it; a stage the lists do not know, "Paused", is counted
// and drawn above the line, after the stages the lists know. Two records beside the fixture's 104:
// the spaced one names Cambodia, created after the consent question without the tick and with a
// contact address, and the paused one names Nigeria, created before it. Neither country has an
// Administrator contact.
$country_id = function ( $name ) use ( $countries ) {
	foreach ( $countries as $id => $country ) { if ( $name === $country['name'] ) { return $id; } }
	return '';
};
$spaced = 'recSPACEDSPAM0001';
$paused = 'recPAUSEDSTAGE001';
$focus  = $rows;

$focus[ $spaced ] = array_merge( reset( $rows ), array( 'record_id' => $spaced, 'name' => 'Spaced Spam College', 'stage' => 'SPAM ', 'country' => $country_id( 'Cambodia' ), 'country_name' => 'Cambodia', 'city' => 'City S', 'contact_email' => 'spaced@example.test', 'created' => '2026-08-01', 'consent' => false ) );
$focus[ $paused ] = array_merge( reset( $rows ), array( 'record_id' => $paused, 'name' => 'Paused Polytechnic', 'stage' => 'Paused', 'country' => $country_id( 'Nigeria' ), 'country_name' => 'Nigeria', 'city' => 'City P', 'contact_email' => '', 'created' => '2026-03-01', 'consent' => false ) );

$focus_counted = $counted_of( $focus );
$focus_emailed = array_filter( $focus, function ( $r ) { return '' !== $r['contact_email']; } );

ck( 'the four stages the counts leave out are SPAM, Not Moving Forward, Revisit Later and no stage', WPCPM_Institutions::UNCOUNTED_STAGES, array( 'SPAM', 'Not Moving Forward', 'Revisit Later', '' ) );
ck( 'counted_rows() keeps the 88 rows whose trimmed stage is not one of them, keyed as given: "Paused" kept, "SPAM " left out', array(
	count( WPCPM_Institutions::counted_rows( $focus ) ),
	array_keys( WPCPM_Institutions::counted_rows( $focus ) ) === array_keys( $focus_counted ),
	isset( WPCPM_Institutions::counted_rows( $focus )[ $paused ] ),
	isset( WPCPM_Institutions::counted_rows( $focus )[ $spaced ] ),
	WPCPM_Institutions::counted_rows( array() ),
), array( 88, true, true, false, array() ) );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $focus;
$GLOBALS['enqueued']                                           = array();
$focus_html                                                    = render_tab( 'pipeline' );
$focus_pipeline_reads                                          = count( $GLOBALS['member_reads'] );
$focus_sync                                                    = render_tab( 'sync' );
$focus_sync_reads                                              = count( $GLOBALS['member_reads'] );
$focus_filtered                                                = render_tab( 'pipeline', array( 'wpcpm_filter' => 'agreement_gap' ) );

$above = $expected;
$above['Paused'] = 1;
$line_at         = strpos( $focus_html, '<h3 class="wpcpm-inst-uncounted">Not counted above</h3>' );

ck( 'the card total is the 88 of the 106 at a counted stage', false !== strpos( $focus_html, 'Pipeline <span class="wpcpm-count">88</span>' ), true );
ck( 'the stage counts above the line are the counted stages\', "Paused" drawn after the ones the lists know', stage_headings( substr( $focus_html, 0, (int) $line_at ) ), $above );
ck( 'the line "Not counted above" comes once, after the last counted table and before the folded stages, and nothing counted is drawn after it', array(
	substr_count( $focus_html, 'Not counted above' ),
	false !== $line_at && $line_at > strrpos( substr( $focus_html, 0, (int) $line_at ), '</table>' ),
	$line_at < strpos( $focus_html, '<details class="wpcpm-inst-folded">' ),
	stage_headings( substr( $focus_html, (int) $line_at ) ),
), array( 1, true, true, array() ) );
ck( 'under it, each stage left out is a closed fold whose summary is the stage and its count, "SPAM " folded in with SPAM', folded_groups( $focus_html ), array(
	'Not Moving Forward' => array( 14, false ),
	'SPAM'               => array( 3, false ),
	'Revisit Later'      => array( 1, false ),
) );

$spam_fold = preg_match( '#<details class="wpcpm-inst-folded"><summary>SPAM <span class="wpcpm-count">3</span></summary>(.*?)</details>#s', $focus_html, $fold ) ? $fold[1] : '';
ck( 'a fold\'s rows draw as before: the table\'s columns, each row\'s name, record ID, country mark and agreement, "SPAM " among them', array(
	0 === strpos( $spam_fold, '<table class="widefat striped wpcpm-list wpcpm-inst-table"><thead><tr><th scope="col">Institution</th><th scope="col">Country</th>' ),
	substr_count( $spam_fold, '<tr><td class="wpcpm-inst-name">' ),
	false !== strpos( $spam_fold, '<a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $spaced . '">Spaced Spam College</a></span><br /><code class="wpcpm-inst-record">' . $spaced . '</code></td><td>Cambodia <span class="wpcpm-inst-mark wpcpm-inst-mark--routing"' ),
	substr_count( $spam_fold, '<td class="wpcpm-inst-agreement">Not started</td>' ),
), array( true, 3, true, 3 ) );
ck( 'and "Paused" is a counted table of its own, above the line', false !== strpos( substr( $focus_html, 0, (int) $line_at ), '<code class="wpcpm-inst-record">' . $paused . '</code>' ), true );

$focus_before = 0;
$focus_since  = 0;
foreach ( $focus_counted as $row ) {
	if ( ! $row['consent'] && '' !== $row['created'] ) {
		if ( strcmp( $row['created'], '2026-07-20' ) < 0 ) { $focus_before++; } else { $focus_since++; }
	}
}
ck( 'every other count on the tab is over the counted rows too: Confirmed with no agreement, no live member, the routing line and the consent card', array(
	false !== strpos( $focus_html, 'Confirmed with no agreement recorded <span class="wpcpm-count">' . ( $seed['counts']['by_stage']['Confirmed'] - 3 ) . '</span></a>' ),
	false !== strpos( $focus_html, 'Institutions with no live member <span class="wpcpm-count">88</span>' ),
	$focus_pipeline_reads,
	false !== strpos( $focus_html, '<p class="wpcpm-inst-routing">Countries named by institutions with no Administrator contact in the Countries table: Nigeria (1)</p>' ),
	array( $focus_before, $focus_since ),
	false !== strpos( $focus_html, '<p class="wpcpm-inst-consent">' . $focus_before . ' institution records were collected before the consent question was added on 20 July 2026, ' . $seed['counts']['created_before_consent_question_confirmed'] . ' of them at Confirmed.</p>' ),
	false !== strpos( $focus_html, 'Since then, ' . $focus_since . ' records have been created without the tick' ),
), array( true, true, 88, true, array( 69, 4 ), true, true ) );
ck( 'and the Sync and storage tab still counts every one: each institution asked once, every contact address that is not a member\'s counted, the spaced SPAM record\'s among them', array(
	$focus_sync_reads,
	false !== strpos( $focus_sync, '<th scope="row">Contacts who are not members</th><td>' . count( $focus_emailed ) . ' ' ),
	count( $focus_emailed ),
), array( 106, true, 103 ) );
ck( 'the filtered view draws Confirmed alone, with no line and no fold', array( array_keys( stage_headings( $focus_filtered ) ), strpos( $focus_filtered, 'Not counted above' ), strpos( $focus_filtered, '<details' ) ), array( array( 'Confirmed' ), false, false ) );

// No stage is the fourth: a record with none folds last, under the name the card has always given it.
$stageless                                                     = 'recNOSTAGEROW0001';
$with_stageless                                                = $focus;
$with_stageless[ $stageless ]                                  = array_merge( reset( $rows ), array( 'record_id' => $stageless, 'name' => 'Stageless School', 'stage' => '', 'city' => 'City N' ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $with_stageless;
$stageless_html                                                = render_tab( 'pipeline' );
ck( 'a record with no stage folds last, as No stage, and is not counted', array( array_keys( folded_groups( $stageless_html ) ), folded_groups( $stageless_html )['No stage'] ?? null, false !== strpos( $stageless_html, 'Pipeline <span class="wpcpm-count">88</span>' ) ), array( array( 'Not Moving Forward', 'SPAM', 'Revisit Later', 'No stage' ), array( 1, false ), true ) );
ck( 'and its option in the field reads its name and city alone', false !== strpos( $stageless_html, '<option value="' . $stageless . '">Stageless School, City N</option>' ), true );

// Names: each opens the institution's page as an Administrator sees it, the address the Agreements
// tab links to, with no panel anchor.
$linked_ok = true;
foreach ( $focus as $id => $row ) {
	$linked_ok = $linked_ok && false !== strpos( $focus_html, '<span class="wpcpm-inst-name__text"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $id . '">' . esc_html( trim( $row['name'] ) ) . '</a></span>' );
}
ck( 'every Pipeline name, folded or not, links to its Institution Dashboard as an Administrator sees it, and none to a panel', array( $linked_ok, substr_count( $focus_html, '<span class="wpcpm-inst-name__text"><a href="' ), substr_count( $focus_html, '#wpcpm-agreement' ) ), array( true, 106, 0 ) );

// The field: Find an institution, over every record whatever its stage, to the same address.
$find_form = preg_match( '#<form method="get" action="https://example.test/institution-dashboard/" class="wpcpm-inst-find wpcpm-admin-picker">(.*?)</form>#s', $focus_html, $found ) ? $found[1] : '';
preg_match( '#<select name="wpcpm_institution_view" id="wpcpm-pipeline-find" autocomplete="off">(.*?)</select>#s', $find_form, $find_select );
preg_match_all( '#<option value="([^"]*)"( selected=\'selected\')?>([^<]*)</option>#', $find_select[1] ?? '', $find_options, PREG_SET_ORDER );
$find_labels = array();
foreach ( $find_options as $option ) { $find_labels[ $option[1] ] = html_entity_decode( $option[3], ENT_QUOTES ); }
// The first option is the placeholder, chosen, with no record behind it: the sort check and the
// records' labels read over the records only.
$find_first  = $find_options[0] ?? array();
$find_empty  = $find_labels[''] ?? null;
unset( $find_labels[''] );
$find_ids  = array_keys( $find_labels );
$sorted_ok = count( $find_ids ) > 1;
for ( $i = 1; $i < count( $find_ids ); $i++ ) {
	$sorted_ok = $sorted_ok && WPCPM_Dashboards::compare_names( trim( $focus[ $find_ids[ $i - 1 ] ]['name'] ), trim( $focus[ $find_ids[ $i ] ]['name'] ) ) <= 0;
}
ck( 'one form above the groups sends a GET to the Institution Dashboard\'s page, with the field, wrapped for its rules, and its Open its page button', array(
	'' !== $find_form,
	false !== strpos( $find_form, '<div class="wpcpm-admin-combo"><label for="wpcpm-pipeline-find" id="wpcpm-pipeline-find-label">Find an institution</label> <select name="wpcpm_institution_view" id="wpcpm-pipeline-find" autocomplete="off">' ),
	false !== strpos( $find_form, '<input type="text" id="wpcpm-pipeline-find-input" class="wpcpm-dashboard__switcher-input" role="combobox"' ),
	false !== strpos( $find_form, '<ul id="wpcpm-pipeline-find-list" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="wpcpm-pipeline-find-label" hidden></ul>' ),
	false !== strpos( $find_form, '</div> </div><button type="submit" class="button">Open its page</button>' ),
	substr_count( $focus_html, 'id="wpcpm-pipeline-find"' ),
	strpos( $focus_html, '<form method="get" action="https://example.test/institution-dashboard/"' ) < strpos( $focus_html, '<h3 class="wpcpm-inst-stage">' ),
	in_array( WPCPM_Dashboards::SWITCHER_SCRIPT, $GLOBALS['enqueued'], true ),
), array( true, true, true, true, true, 1, true, true ) );
ck( 'its options are the placeholder first, then all 106 records, keyed by record ID, sorted by name', array( count( $find_options ), count( $find_labels ), array_diff( array_keys( $focus ), $find_ids ), $sorted_ok ), array( 107, 106, array(), true ) );
ck( 'the placeholder is "Choose an institution", empty, the one chosen, and the only empty option, so an untouched Open its page sends no record', array(
	$find_first[1] ?? null,
	! empty( $find_first[2] ),
	$find_empty,
	substr_count( $find_select[1] ?? '', '<option value=""' ),
	substr_count( $find_select[1] ?? '', 'selected=' ),
), array( '', true, 'Choose an institution', 1, 1 ) );
ck( 'each reads "Name, City (Stage)", trimmed, the city or the stage left out when there is none', array(
	$find_labels[ $paused ] ?? null,
	$find_labels[ $spaced ] ?? null,
	$find_labels['recSEED0000000118'] ?? null,
	$find_labels['recSEED0000000048'] ?? null,
	$find_labels['recSEED0000000002'] ?? null,
), array( 'Paused Polytechnic, City P (Paused)', 'Spaced Spam College, City S (SPAM)', 'Institution 86 (SPAM)', 'Institution 30, City 2 (First Contact Made)', 'Institution 1, City 1 (Info Sent)' ) );
ck( 'and the field carries no address, and the form no nonce and no action', array( preg_match( '/@example\.test/', $find_form ), strpos( $find_form, '_wpnonce' ), strpos( $find_form, 'name="action"' ) ), array( 0, false, false ) );

// The page addressed by its query, as a site without pretty permalinks addresses it: a GET form drops
// the query of its action, so each argument goes in a hidden field, and the links carry it.
$GLOBALS['institution_page_url'] = 'https://example.test/?page_id=42';
$plain_permalinks                = render_tab( 'pipeline' );
$GLOBALS['institution_page_url'] = '';
$no_page                         = render_tab( 'pipeline' );
unset( $GLOBALS['institution_page_url'] );
$queried_form = preg_match( '#<form method="get" action="https://example.test/\?page_id=42" class="wpcpm-inst-find wpcpm-admin-picker">(.*?)</form>#s', $plain_permalinks, $found ) ? $found[1] : '';
ck( 'a page addressed by its query keeps it: each argument in a hidden field of the form, and in every link', array(
	0 === strpos( $queried_form, '<input type="hidden" name="page_id" value="42" /><div class="wpcpm-admin-combo">' ),
	false !== strpos( $plain_permalinks, '<a href="https://example.test/?page_id=42&wpcpm_institution_view=' . $paused . '">Paused Polytechnic</a>' ),
), array( true, true ) );
ck( 'with no published Institution Dashboard the names are plain and no field is drawn', array(
	false !== strpos( $no_page, '<span class="wpcpm-inst-name__text">Paused Polytechnic</span>' ),
	strpos( $no_page, 'wpcpm_institution_view' ),
	strpos( $no_page, 'wpcpm-pipeline-find' ),
	strpos( $no_page, 'Open its page' ),
	false !== strpos( $no_page, 'Pipeline <span class="wpcpm-count">88</span>' ),
), array( true, false, false, false, true ) );

// The Find options come from the shared sort (`WPCPM_Dashboards::sort_switcher_options()`, the one the Viewing
// as switchers sort their entries with), and not from a sort of the Pipeline's own. The two differ for a name
// that opens another: sorted by the whole entry, "Teo School, Athens" comes before "Teo, Zagreb", where a sort
// by the bare name put "Teo" first. The entries are the same ones in both, so the fixture holds such a pair.
$twins          = $focus;
$twins['recTWINALPHA00001'] = array_merge( reset( $rows ), array( 'record_id' => 'recTWINALPHA00001', 'name' => 'Teo', 'stage' => 'Info Sent', 'city' => 'Zagreb' ) );
$twins['recTWINBETA000001'] = array_merge( reset( $rows ), array( 'record_id' => 'recTWINBETA000001', 'name' => 'Teo School', 'stage' => 'Info Sent', 'city' => 'Athens' ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $twins;
$twins_html = render_tab( 'pipeline' );
preg_match( '#<select name="wpcpm_institution_view" id="wpcpm-pipeline-find" autocomplete="off">(.*?)</select>#s', $twins_html, $twins_select );
preg_match_all( '#<option value="([^"]*)"[^>]*>([^<]*)</option>#', $twins_select[1] ?? '', $twins_options, PREG_SET_ORDER );
$twins_shown = array();
foreach ( $twins_options as $option ) { if ( '' !== $option[1] ) { $twins_shown[ $option[1] ] = html_entity_decode( $option[2], ENT_QUOTES ); } }
$shared_sort = is_callable( array( 'WPCPM_Dashboards', 'sort_switcher_options' ) ) ? call_user_func( array( 'WPCPM_Dashboards', 'sort_switcher_options' ), $twins_shown ) : null;
ck( 'the Find options are the shared sort of their labels, in that order and no other', array( array_keys( $twins_shown ) === array_keys( (array) $shared_sort ), count( $twins_shown ) ), array( true, 108 ) );
ck( 'so a name that opens another comes after it as the whole entry sorts: "Teo School, Athens" before "Teo, Zagreb"', array(
	array_search( 'recTWINBETA000001', array_keys( $twins_shown ), true ) < array_search( 'recTWINALPHA00001', array_keys( $twins_shown ), true ),
	$twins_shown['recTWINBETA000001'] ?? null,
	$twins_shown['recTWINALPHA00001'] ?? null,
), array( true, 'Teo School, Athens (Info Sent)', 'Teo, Zagreb (Info Sent)' ) );
$find_body = body_of_method( file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' ), 'render_pipeline_find' );
ck( 'and the method sorts nothing itself: it hands its options to the shared sort', array( false !== strpos( $find_body, 'WPCPM_Dashboards::sort_switcher_options(' ), false !== strpos( $find_body, 'usort(' ), false !== strpos( $find_body, 'compare_names(' ) ), array( true, false, false ) );

// The routing line, over two countries: sorted by name and not in the order the records came, each with its
// count, joined by a comma and a space. Two records name Nigeria and one Cambodia, and Nigeria is met first.
$routing_rows = $focus;
$routing_rows['recGAPCAMBODIA001'] = array_merge( reset( $rows ), array( 'record_id' => 'recGAPCAMBODIA001', 'name' => 'Gap College Cambodia', 'stage' => 'Info Sent', 'country' => $country_id( 'Cambodia' ), 'country_name' => 'Cambodia', 'city' => 'City G' ) );
$routing_rows['recGAPNIGERIA0001'] = array_merge( reset( $rows ), array( 'record_id' => 'recGAPNIGERIA0001', 'name' => 'Gap College Nigeria', 'stage' => 'Info Sent', 'country' => $country_id( 'Nigeria' ), 'country_name' => 'Nigeria', 'city' => 'City H' ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $routing_rows;
$routing_html = render_tab( 'pipeline' );
ck( 'the routing line lists two countries sorted and joined: "Cambodia (1), Nigeria (2)"', preg_match( '#<p class="wpcpm-inst-routing">(.*?)</p>#', $routing_html, $routing_line ) ? $routing_line[1] : null, 'Countries named by institutions with no Administrator contact in the Countries table: Cambodia (1), Nigeria (2)' );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;

/* ---- the reconciliation card -------------------------------------------- */

echo "\n=== The reconciliation card ===\n";

$html = render_tab( 'sync' );

ck( 'the card reads 31 / 19 / 10 / 9 / 3', array(
	false !== strpos( $html, '<th scope="row">Students rows with no reports row</th><td>31 <span class="wpcpm-inst-muted">(Not moving forward 15, (empty) 7, Graduate 6, In Sensei 2, SPAM 1)</span></td>' ),
	false !== strpos( $html, '<th scope="row">Reports rows with no Students row</th><td>19 <span class="wpcpm-inst-muted">(In Sensei 7, Graduate 6, Not moving forward 4, (empty) 1, SPAM 1)</span></td>' ),
	false !== strpos( $html, '<th scope="row">Status disagreements on joined rows</th><td>10</td>' ),
	false !== strpos( $html, '<th scope="row">Duplicate emails in the Students table</th><td>9 <span class="wpcpm-inst-muted">(Institution 4 (5), recUNKNOWN0000001 (4))</span></td>' ),
	false !== strpos( $html, '<th scope="row">Students rows with no institution</th><td>3</td>' ),
), array( true, true, true, true, true ) );
ck( 'the no-start-date count is split by status', false !== strpos( $html, '<th scope="row">Students rows with no start date</th><td>7 <span class="wpcpm-inst-muted">(Not moving forward 4, (empty) 2, Developer Track 1)</span></td>' ), true );
ck( 'the no-stamp count reads zero, counted now', false !== strpos( $html, '<th scope="row">Tracked student accounts with no institution stamp</th><td>0 <span class="wpcpm-inst-muted">(counted now)</span></td>' ), true );
ck( 'and says "tracked", which is what it counts', false !== strpos( $src, "'Tracked student accounts with no institution stamp'" ), true );

$students_url = 'https://airtable.com/appIzQKfwTn5dyPVp/tbla8GZg5x6NY7aWt/';
$reports_url  = 'https://airtable.com/appIzQKfwTn5dyPVp/tbljYkkVGbeoaWEtY/';
ck( 'the card counts the mentored rows the address join missed', false !== strpos( $html, '<th scope="row">Mentored students whose report record the address join missed</th><td>3</td>' ), true );
ck( 'and lists each with the institution and status muted', array(
	false !== strpos( $html, '<li>Mismatched Student <span class="wpcpm-inst-muted">Institution 4 · In Sensei</span><br>' ),
	false !== strpos( $html, '<li>Lonely Mentored <span class="wpcpm-inst-muted">Institution 4 · Developer Track</span><br>' ),
), array( true, true ) );
ck( 'the mismatched address names both rows, both addresses and the fix', false !== strpos( $html, 'The <a href="' . $students_url . 'recSTUMISS0000001" target="_blank" rel="noopener noreferrer">Students row</a> carries mismatched@school.example.test; a <a href="' . $reports_url . 'recREPMISS0000001" target="_blank" rel="noopener noreferrer">Students Reports row</a> with this name carries mismatched@home.example.test and matched no Students row. Make the two addresses identical and run the students sync.' ), true );
ck( 'the second Students row names the other row and its institution', false !== strpos( $html, 'a <a href="' . $reports_url . 'recREPTWICE00001" target="_blank" rel="noopener noreferrer">Students Reports row</a> with this name carries the address of <a href="' . $students_url . 'recSTUTWICE000002" target="_blank" rel="noopener noreferrer">another Students row</a>, filed under recUNKNOWN0000001. One of the two Students rows is a duplicate.' ), true );
ck( 'no report row among the rows the sync reads says both things it could mean', false !== strpos( $html, 'carries lonely@school.example.test, and no Students Reports row with this name is among the rows the sync reads (the tracked statuses): either the automation has not created the record yet, or the record carries a status the sync does not read.' ), true );

// The count says ten; the card says who. The two the last run kept are listed with both statuses and
// a link to each row, and the card says how many the list stops short of.
$dis_heading = '<h3>Status disagreements on joined rows</h3>';
ck( 'the card lists the students whose two tables disagree, under a heading of their own', false !== strpos( $html, $dis_heading ), true );
ck( 'each with both statuses and a link to each of the two rows, a missing status said so',
	array(
		false !== strpos( $html, '<li>Disagreeing One<br>The <a href="' . $students_url . 'recSTUDIS0000001" target="_blank" rel="noopener noreferrer">Students row</a> says Graduate; the <a href="' . $reports_url . 'recREPDIS0000001" target="_blank" rel="noopener noreferrer">Students Reports row</a> says In Sensei.</li>' ),
		false !== strpos( $html, '(no status); the <a href="' . $reports_url . 'recREPDIS0000002" target="_blank" rel="noopener noreferrer">Students Reports row</a> says Paused.</li>' ),
	),
	array( true, true ) );
ck( 'a name is escaped as it is printed', array( false !== strpos( $html, '<li>Disagreeing &lt;b&gt;Two&lt;/b&gt;<br>' ), false === strpos( $html, 'Disagreeing <b>Two</b>' ) ), array( true, true ) );
ck( 'and the list says how many more there are than it shows: ten, and two shown', false !== strpos( $html, '<li class="wpcpm-inst-muted">and 8 more</li>' ), true );

// Names are for the Administrators. The screen is behind the management capability already; the
// list asks for it again where the names are printed, so a render for anybody else carries the
// count and not the people.
$GLOBALS['uid']  = 30;
$GLOBALS['caps'] = false;
$for_student     = render_tab( 'sync' );
$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
ck( 'rendered for somebody without the management capability, the card keeps its count and prints no student\'s name',
	array( false !== strpos( $for_student, '<th scope="row">Status disagreements on joined rows</th><td>10</td>' ), false === strpos( $for_student, 'Disagreeing One' ), false === strpos( $for_student, $dis_heading ), false === strpos( $for_student, 'recSTUDIS0000001' ) ),
	array( true, true, true, true ) );
ck( 'the list is drawn behind the capability, read off the source', false !== strpos( function_body( $src, 'render_disagreements' ), 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ), true );

// A run from before the list was kept holds the count and no names; the card says when they come
// rather than leaving a number that cannot be followed up. A base with none to list prints nothing.
$kept_rec = $GLOBALS['opts'][ WPCPM_Roster_Index::OPT_COUNTS ];
unset( $GLOBALS['opts'][ WPCPM_Roster_Index::OPT_COUNTS ]['reconciliation']['status_disagreement_rows'] );
$before_list = render_tab( 'sync' );
ck( 'a count from a run that kept no names says the names come with the next students sync, and lists nobody',
	array( false !== strpos( $before_list, $dis_heading ), false !== strpos( $before_list, 'The names arrive with the next students sync.' ), false === strpos( $before_list, 'Disagreeing One' ) ),
	array( true, true, true ) );
$GLOBALS['opts'][ WPCPM_Roster_Index::OPT_COUNTS ]['reconciliation']['status_disagreements']     = 0;
$GLOBALS['opts'][ WPCPM_Roster_Index::OPT_COUNTS ]['reconciliation']['status_disagreement_rows'] = array();
$none_to_list = render_tab( 'sync' );
ck( 'with none disagreeing there is no heading and no sentence about names',
	array( false === strpos( $none_to_list, $dis_heading ), false === strpos( $none_to_list, 'The names arrive' ), false !== strpos( $none_to_list, '<th scope="row">Status disagreements on joined rows</th><td>0</td>' ) ),
	array( true, true, true ) );
$GLOBALS['opts'][ WPCPM_Roster_Index::OPT_COUNTS ] = $kept_rec;

$query = null;
foreach ( $GLOBALS['calls'] as $call ) { if ( 'WP_User_Query' === $call[0] ) { $query = $call[1]; } }
ck( 'through a NOT EXISTS query on the stamp AND the live flag, over the student role', array(
	$query['role'],
	$query['meta_query']['relation'],
	$query['meta_query'][0]['key'], $query['meta_query'][0]['compare'],
	$query['meta_query'][1]['key'], $query['meta_query'][1]['value'],
	$query['count_total'],
), array( WPCPM_Roles::ROLE_STUDENT, 'AND', 'wpcpm_student_institution', 'NOT EXISTS', 'wpcpm_student_active', '1', false ) );

ck( 'the unlinked rows are listed by name and status only', array(
	false !== strpos( $html, '<li>Unlinked One <span class="wpcpm-inst-muted">In Sensei</span></li>' ),
	false !== strpos( $html, '<li>Unlinked Two <span class="wpcpm-inst-muted">(no status)</span></li>' ),
	false !== strpos( $html, '<li>Unlinked Three <span class="wpcpm-inst-muted">Graduate</span></li>' ),
	false === strpos( $html, 'u1@example.test' ),
), array( true, true, true, true ) );
// The note that said linking would ship later is gone, because it has. What replaced it is a
// control that refuses more often than it accepts: writing the institution link onto a row
// that already has a mentor fires the Airtable automation and creates a second reports row.
ck( 'the promise that it would ship later is gone', false !== strpos( $html, 'ships with the next phase' ), false );
// These fixture rows are accounts the sync could only place from the reports side, so none of
// them carries a Students record ID and the control has nothing to address. It says so rather
// than drawing a control that could not work, and that sentence is what stands here now.
// `bin/test-unlinked-link.php` owns the control's own behavior, on rows that do have one.
ck( 'and a row with no record ID is told what to do in Airtable instead', false !== strpos( $html, 'carries no usable Airtable record ID' ), true );

// A stamp the sync deleted on purpose is not a broken sync: it wrote `institution_source`,
// which is its word that it looked. Account 31 is one of those, and unstamping it must leave
// the row at zero.
unset( $GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_INSTITUTION ] );
$deliberate = render_tab( 'sync' );
ck( 'an account the sync unstamped on purpose is not reported as a broken sync', false !== strpos( $deliberate, '<td>0 <span class="wpcpm-inst-muted">(counted now)</span></td>' ), true );
ck( 'and the row is not a warning', false === strpos( $deliberate, '<td class="wpcpm-warning">1 <span class="wpcpm-inst-muted">(counted now; should be 0' ), true );

// A broken sync is an account the sync has never described: tracked, live, no stamp and no
// `institution_source` at all.
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_ACTIVE ] = 1;
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'status' => 'In Sensei' );
$GLOBALS['users'][32] = new WP_User( 32, 'Never Described', 'nd@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$broken = render_tab( 'sync' );
ck( 'an account no run has ever described turns the row into a warning', false !== strpos( $broken, '<td class="wpcpm-warning">1 <span class="wpcpm-inst-muted">(counted now; should be 0, anything else is a broken sync)</span></td>' ), true );
unset( $GLOBALS['umeta'][32], $GLOBALS['users'][32] );

$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_INSTITUTION ] = 'recSEED0000000011';

// The two accounts the sync leaves without a stamp on purpose. Neither is a broken sync, and
// counting them by role alone reported both: the first for as long as the account exists.
$GLOBALS['users'][32] = new WP_User( 32, 'Dee Departed', 'dee@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_ACTIVE ]  = 0;
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'institution_source' => 'students' );

$GLOBALS['users'][33] = new WP_User( 33, 'Dan Duplicate', 'dan@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][33][ WPCPM_Students_Sync::META_ACTIVE ]  = 1;
$GLOBALS['umeta'][33][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'institution_source' => '' );

$narrowed = render_tab( 'sync' );
ck( 'a departed account that kept the role is not one, whatever student_on_inactive says', false !== strpos( $narrowed, '<th scope="row">Tracked student accounts with no institution stamp</th><td>0 <span class="wpcpm-inst-muted">(counted now)</span></td>' ), true );
// Which of the two the query excluded and which the card did: the departed account never
// reaches PHP, the duplicate does and is skipped on its program meta, and the count is 0.
$candidates = null;
foreach ( $GLOBALS['calls'] as $call ) { if ( 'WP_User_Query' === $call[0] ) { $candidates = $call[1]; } }
$candidates = ( new WP_User_Query( $candidates ) )->get_results();
ck( 'nor is the duplicate email the sync unstamped on purpose, which the duplicates row already counts', array(
	in_array( 32, $candidates, true ),
	in_array( 33, $candidates, true ),
), array( false, true ) );

// An account holding the role and the live flag that the sync has never described is still
// counted: nothing says it was left unstamped on purpose.
$GLOBALS['users'][34] = new WP_User( 34, 'Una Undescribed', 'una@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][34][ WPCPM_Students_Sync::META_ACTIVE ] = 1;

ck( 'an account with the live flag the sync has never described is a broken sync', false !== strpos( render_tab( 'sync' ), '<td class="wpcpm-warning">1 <span class="wpcpm-inst-muted">(counted now; should be 0, anything else is a broken sync)</span></td>' ), true );

// The count asks WordPress for the accounts' IDs, which WordPress can answer with rows (see
// `WPCPM_Roles::id_of()`). Cast to an int, every row is account 1, with a warning for each, and
// account 1 has no program meta to say it was described: Dan, skipped above, would be counted with Una.
$GLOBALS['ids_as_rows'] = true;
$row_warnings           = array();
set_error_handler( static function ( $number, $message ) use ( &$row_warnings ) { $row_warnings[] = $message; return true; } );
$by_rows = render_tab( 'sync' );
restore_error_handler();
$GLOBALS['ids_as_rows'] = false;

ck( 'the same where WordPress answers the request for IDs with rows: Una alone is counted, and nothing warns',
	array( false !== strpos( $by_rows, '<td class="wpcpm-warning">1 <span class="wpcpm-inst-muted">(counted now; should be 0, anything else is a broken sync)</span></td>' ), $row_warnings ),
	array( true, array() ) );

unset( $GLOBALS['users'][32], $GLOBALS['users'][33], $GLOBALS['users'][34], $GLOBALS['umeta'][32], $GLOBALS['umeta'][33], $GLOBALS['umeta'][34] );

/* ---- the manager backstop counts ---------------------------------------- */

echo "\n=== The manager backstop counts ===\n";

// Day one, with nothing provisioned: every institution is missing a member, and every
// address the base names for one belongs to nobody on this site.
$emailed = array_keys( array_filter( $rows, function ( $r ) { return '' !== $r['contact_email']; } ) );
ck( 'the fixture names a contact address for 102 of the 104 institutions', count( $emailed ), 102 );
$pipeline_html = render_tab( 'pipeline' );
$sync_html     = render_tab( 'sync' );

ck( 'with nobody provisioned, both counts are the whole pipeline: the Pipeline\'s over the institutions it counts, Sync and storage\'s over every one', array(
	false !== strpos( $pipeline_html, 'Institutions with no live member <span class="wpcpm-count">' . count( $counted ) . '</span>' ),
	false !== strpos( $sync_html, '<th scope="row">Contacts who are not members</th><td>' . count( $emailed ) . ' ' ),
), array( true, true ) );

// The one that pages a manager prints inside the pipeline card, above the stage tables; the
// contacts count prints on the reconciliation card, where the design puts it. Each is on the
// tab of its card, and only there.
ck( 'the no-member count is in the pipeline card and the contacts count on the reconciliation card, each on its own tab', array(
	false !== strpos( $pipeline_html, 'Institutions with no live member' ) && strpos( $pipeline_html, 'Institutions with no live member' ) < strpos( $pipeline_html, '<h3 class="wpcpm-inst-stage">' ),
	strpos( $sync_html, 'Contacts who are not members' ) > strpos( $sync_html, 'Students rows with no institution' ),
	strpos( $pipeline_html, 'Contacts who are not members' ),
	strpos( $sync_html, 'Institutions with no live member' ),
), array( true, true, false, false ) );

// Three places join the index to the live stamps: the no-member count, the contacts count and
// the Accounts tab's No account view, and every one of them says which half is as old as the sync.
$provenance = '(from the pipeline index read ' . gmdate( 'Y-m-d H:i', $read_at ) . '; memberships counted now)';
ck( 'each count says which half is as old as the sync and which was read now, on Pipeline, on the Accounts tab\'s No account view and on Sync and storage', array(
	substr_count( $pipeline_html, $provenance ),
	substr_count( render_tab( 'accounts', array( 'wpcpm_view' => 'no-account' ) ), $provenance ),
	substr_count( $sync_html, $provenance ),
), array( 1, 1, 1 ) );

// The routes in are named rather than printed as a zero: for a Confirmed institution, the
// Accounts tab, linked, where No account creates the account for the Contact Email and an
// institution's Manage members view adds a person by name and address; for one at an earlier
// stage, the approval of its application or its reaching Confirmed, since both counts span every
// stage and the Accounts tab lists Confirmed institutions alone; and the invitations the
// institution's own dashboard lists (they are not counted here). The institution's card on this
// screen that both sentences used to send a manager to was never drawn.
$give_it = 'For a Confirmed institution, give it an account on <a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts">the Accounts tab</a>: create one for its Contact Email under No account, or add a person by name and address under Manage members. An institution at an earlier stage gets its first account when its application is approved or it reaches Confirmed.';
ck( 'the routes in are named, not printed as a zero: under the no-member count on Pipeline and under the contacts count on Sync and storage, the same words send the reader to the Accounts tab, linked, for a Confirmed institution and say how one at an earlier stage gets its first account, and the card that was never drawn is named on neither', array(
	false !== strpos( $pipeline_html, 'Invitations older than seven days are not counted here' ),
	false !== strpos( $pipeline_html, '<p class="description">Nobody at an institution counted here can act for it on this site. ' . $give_it . ' Once one member is in, they can invite colleagues from their own dashboard.</p>' ),
	false !== strpos( $sync_html, '<p class="description">A Contact Email that belongs to no member is the address Airtable names for the institution and nobody who can act for it here. ' . $give_it . ' The sync provisions the address on its own only for an institution that has never had a member, so a removed contact is not re-created on every run.</p>' ),
	strpos( $pipeline_html . $sync_html, 'institution&#039;s card' ),
), array( true, true, true, false ) );

// Two institutions acquire a member: one is the contact herself, recorded in another case
// and with the spaces a form leaves behind; the other is somebody else entirely.
$GLOBALS['members_of'] = array(
	$emailed[0] => array( new WP_User( 40, 'Contact One', ' ' . strtoupper( $rows[ $emailed[0] ]['contact_email'] ) . ' ', array( WPCPM_Roles::ROLE_INSTITUTION ) ) ),
	$emailed[1] => array( new WP_User( 41, 'Someone Else', 'someone@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ) ),
);
$members        = render_tab( 'pipeline' );
$pipeline_reads = $GLOBALS['member_reads'];
$members_sync   = render_tab( 'sync' );
$sync_reads     = $GLOBALS['member_reads'];

ck( 'two counted institutions with a live member leave 85 with none', array( count( array_intersect_key( $GLOBALS['members_of'], $counted ) ), false !== strpos( $members, 'Institutions with no live member <span class="wpcpm-count">' . ( count( $counted ) - 2 ) . '</span>' ) ), array( 2, true ) );
ck( 'and only the one whose member is the contact leaves the address counted', false !== strpos( $members_sync, '<th scope="row">Contacts who are not members</th><td>' . ( count( $emailed ) - 1 ) . ' ' ), true );
ck( 'and the card says what a contact who is not a member means, and what is not built for it yet', false !== strpos( $members_sync, 'A Contact Email that belongs to no member is the address Airtable names for the institution' ), true );
ck( 'each tab that prints a membership count asks each institution it counts for its members exactly once, for both counts together: Pipeline the 87 it counts, Sync and storage all 104', array(
	count( $pipeline_reads ),
	count( array_unique( $pipeline_reads ) ),
	count( $sync_reads ),
	count( array_unique( $sync_reads ) ),
), array( count( $counted ), count( $counted ), count( $rows ), count( $rows ) ) );
ck( 'no address reaches the screen, the member\'s least of all', preg_match( '/@example\.test/i', $members . $members_sync ), 0 );

$GLOBALS['members_of']   = array();
$GLOBALS['member_reads'] = array();

/* ---- the Accounts tab --------------------------------------------------- */

// The provisioning card is gone from the Accounts tab: its worklist is the accounts list's No
// account view and its bulk button the view's Create account, which bin/test-institutions-accounts.php
// reads row by row against the institutions sync's own rule. What is checked here is the frame: the
// tab draws the accounts locked for the day, the invitations card and the list, in that order, asks
// why each Confirmed institution has no account once, for the count the view's link carries, and
// draws none of the card's controls.

echo "\n=== The Accounts tab: the accounts locked today, the invitations and the accounts list ===\n";

$GLOBALS['uid']                 = 63;
$GLOBALS['locked']              = array( new WP_User( 50, 'Rep One', 'rep.one@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ) );
$GLOBALS['never_invited']       = array( 51, 52 );
$GLOBALS['never_invited_asked'] = array();
$GLOBALS['everyone_asked']      = array();
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'invited' );
$accounts_tab              = render_tab( 'accounts' );
$GLOBALS['uid']            = 1;
$GLOBALS['locked']         = array();
$GLOBALS['never_invited']  = array();
delete_user_meta( 63, WPCPM_Flash::META );

$at_each = array();
foreach ( array(
	'notice'      => '<div class="notice notice-success is-dismissible"><p>Invitation email sent.</p></div>',
	'bar'         => '<nav class="nav-tab-wrapper',
	'locked'      => '1 institution account is locked out of roster changes for the rest of today.',
	'invitations' => '<div class="wpcpm-card wpcpm-invites"><h2>Invitations</h2>',
	'list'        => '<h2>Institution accounts <span class="wpcpm-count">0</span></h2>',
) as $part => $needle ) {
	$at_each[ $part ] = strpos( $accounts_tab, $needle );
}
$found_parts = array_filter( $at_each, 'is_int' );
asort( $found_parts );

ck( 'the tab reads top to bottom: the press\'s notice above the bar, as on every tab, then the accounts locked for the day, the invitations card and the institution accounts list',
	array( array_keys( $found_parts ), substr_count( $accounts_tab, 'is-dismissible' ) ),
	array( array( 'notice', 'bar', 'locked', 'invitations', 'list' ), 1 ) );
ck( 'the invitations card is the module\'s: its button posts the module\'s own action and names the Accounts tab, and it counts the accounts the mail layer finds never invited, by the institution account\'s role and stamp',
	array( form_fields( $accounts_tab, 'wpcpm_institutions_bulk_invite' ), false !== strpos( $accounts_tab, '<button type="submit">Invite 2 institution accounts that have never been invited</button>' ), $GLOBALS['never_invited_asked'] ),
	array( array( 'action' => 'wpcpm_institutions_bulk_invite', 'wpcpm_tab' => 'accounts' ), true, array( array( WPCPM_Roles::ROLE_INSTITUTION, 'wpcpm_inst_invited' ) ) ) );
ck( 'while some are left to invite, the card does not count the accounts holding the role',
	$GLOBALS['everyone_asked'],
	array() );

// With nobody left to invite, the screen counts everybody holding the institution account's role,
// invited or not, and hands the card its sentence only when that is nobody: a role no account holds
// is not a list whose accounts were all invited. The stand-in card prints the sentence it is handed.
$no_account_sentence = 'No institution has an account on the site yet.';
$GLOBALS['everyone'] = array();
$nobody_tab          = render_tab( 'accounts' );
$GLOBALS['everyone'] = array( 51, 52 );
$invited_tab         = render_tab( 'accounts' );
$GLOBALS['everyone'] = array();

ck( 'with nobody left to invite, the card says no institution has an account where the role is held by nobody, and says nothing of it where accounts exist, the role asked for each time',
	array( false !== strpos( $nobody_tab, '<p class="description">' . $no_account_sentence . '</p>' ), false !== strpos( $invited_tab, $no_account_sentence ), $GLOBALS['everyone_asked'] ),
	array( true, false, array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_INSTITUTION ) ) );
ck( 'the list counts the institution accounts and, on the link to its No account view, the 42 Confirmed institutions without one, each asked about once',
	array( false !== strpos( $accounts_tab, 'No account <span class="count">(42)</span>' ), count( $GLOBALS['blocks_read'] ), count( array_unique( $GLOBALS['blocks_read'] ) ) ),
	array( true, count( $confirmed ), count( $confirmed ) ) );

// The count is what the answers leave, not the Confirmed institutions: one that already has a member
// is asked about and is not on the view.
$GLOBALS['blocks'][ $confirmed[0] ] = WPCPM_Institutions_Sync::BLOCK_HAS_MEMBER;
$with_member                        = render_tab( 'accounts' );
$GLOBALS['blocks']                  = array();

ck( 'and with one of them answered as having a member already, the view counts 41, every one still asked about once',
	array( false !== strpos( $with_member, 'No account <span class="count">(41)</span>' ), count( $GLOBALS['blocks_read'] ), count( array_unique( $GLOBALS['blocks_read'] ) ) ),
	array( true, count( $confirmed ), count( $confirmed ) ) );
ck( 'and nothing of the provisioning card is drawn: no bulk button, no worklist table, no form that creates an account',
	array( strpos( $accounts_tab, 'wpcpm_institutions_provision' ), strpos( $accounts_tab, 'wpcpm-inst-provision' ), strpos( $accounts_tab, 'Create the accounts' ), strpos( $accounts_tab, 'are not listed above' ) ),
	array( false, false, false, false ) );

// The one outcome a failed press leaves whatever it was, `error`, a failed sync start, a failed row
// invitation and a failed decision on the queue among them, is worded by the tab the press came
// back to: the Sync and storage tab's points to the last sync's error, which it prints below; the
// Accounts tab, which prints no sync error, says the invitation could not be sent; and the other
// four, which print no error below, send the reader to the screen again.
$worded = array();
foreach ( array( 'queue' => 640, 'pipeline' => 641, 'accounts' => 65, 'reports' => 642, 'agreements' => 643, 'sync' => 64 ) as $tab => $viewer ) {
	$GLOBALS['uid'] = $viewer;
	WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'error' );
	preg_match( '#<div class="notice notice-error is-dismissible"><p>(.*?)</p></div>#', render_tab( $tab ), $error_notice );
	$worded[ $tab ] = isset( $error_notice[1] ) ? $error_notice[1] : '';
	delete_user_meta( $viewer, WPCPM_Flash::META );
}
$GLOBALS['uid'] = 1;

ck( 'the outcome a failed press leaves is worded by the tab it comes back to: the last sync\'s error on Sync and storage, the invitation on Accounts, and on the four tabs that print no error below, a reload',
	$worded,
	array(
		'queue'      => 'That action could not be completed. Reload the screen and try again.',
		'pipeline'   => 'That action could not be completed. Reload the screen and try again.',
		'accounts'   => 'The invitation could not be sent.',
		'reports'    => 'That action could not be completed. Reload the screen and try again.',
		'agreements' => 'That action could not be completed. Reload the screen and try again.',
		'sync'       => 'That action could not be completed. See the error below.',
	) );

/* ---- agreements on file ------------------------------------------------- */

// The card lists every Confirmed record whose agreement is not settled, and since 1.123.26 it records
// nothing itself. Until then a button under the list marked every one of them signed in one press,
// with one Drive link and no date guard: a day-one migration tool that, left live, would have marked
// an institution confirmed last week as signed. Each name now opens that institution's own page, at
// the Collaboration Agreement panel, where "Record an agreement the program already holds" records
// one agreement with the copy the program holds for it.

echo "\n=== Agreements on file ===\n";

$kept_summaries       = $GLOBALS['summaries'];
$GLOBALS['summaries'] = array();
$on_file_tab          = render_tab( 'agreements' );
$on_file_card         = preg_match( '#<div class="wpcpm-card"><h2>Agreements on file</h2>(.*?)</div><div class="wpcpm-card">#s', $on_file_tab, $card_match ) ? $card_match[1] : '';

preg_match_all( '#<li><a href="([^"]*)">(.*?)</a> <code class="wpcpm-inst-record">([^<]*)</code></li>#', $on_file_card, $listed, PREG_SET_ORDER );

ck( 'the card says how many Confirmed institutions have no agreement recorded, and names every one with its record ID', array(
	false !== strpos( $on_file_card, '<p>42 Confirmed institutions have no agreement recorded:</p><ul class="wpcpm-notices wpcpm-inst-unrecorded">' ),
	array_column( $listed, 3 ),
	isset( $listed[0][2] ) ? html_entity_decode( $listed[0][2], ENT_QUOTES ) : '',
), array( true, $confirmed, trim( $rows[ $confirmed[0] ]['name'] ) ) );
ck( 'each name opens that institution\'s own page at its Collaboration Agreement panel, where one agreement is recorded', array(
	isset( $listed[0][1] ) ? $listed[0][1] : '',
	count( $listed ),
), array( 'https://example.test/institution-dashboard/?wpcpm_institution_view=' . $confirmed[0] . '#wpcpm-agreement', 42 ) );
ck( 'when the index was read comes first, and the one sentence under the list says how each is recorded', array(
	0 === strpos( $on_file_card, '<p class="wpcpm-inst-read">Pipeline index: read ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).</p><p>42 Confirmed institutions' ),
	false !== strpos( $on_file_card, '</ul><p class="description">Record each one from its own page, with the signed copy the program holds.</p>' ),
	substr_count( $on_file_tab, 'Record each one from its own page, with the signed copy the program holds.' ),
), array( true, true, 1 ) );
ck( 'the card records nothing itself: no form, no button, no bulk words', array(
	strpos( $on_file_card, '<form' ),
	strpos( $on_file_card, '<button' ),
	strpos( $on_file_tab, 'wpcpm_agreement_on_file_all' ),
	strpos( $on_file_tab, 'institutions as signed' ),
	strpos( $on_file_tab, 'Every Confirmed institution signed a Collaboration Agreement before this site could record one.' ),
), array( false, false, false, false, false ) );
ck( 'and no address reaches the card, which is there, only the names', array( '' !== $on_file_card, preg_match( '/@example\.test/', $on_file_card ) ), array( true, 0 ) );

// Two recorded and one waiting for review: the recorded ones leave the list and the count, the
// one waiting stays, since nothing is recorded for it yet.
$GLOBALS['summaries'] = array(
	$confirmed[0] => array( 'state' => 'on_file' ),
	$confirmed[1] => array( 'state' => 'accepted' ),
	$confirmed[2] => array( 'state' => 'submitted' ),
);
$fewer = render_tab( 'agreements' );
ck( 'an institution whose agreement is recorded leaves the list and the count, and one waiting for review stays on it', array(
	false !== strpos( $fewer, '<p>40 Confirmed institutions have no agreement recorded:</p>' ),
	strpos( $fewer, '<code class="wpcpm-inst-record">' . $confirmed[0] . '</code>' ),
	strpos( $fewer, '<code class="wpcpm-inst-record">' . $confirmed[1] . '</code>' ),
	false !== strpos( $fewer, '<code class="wpcpm-inst-record">' . $confirmed[2] . '</code>' ),
), array( true, false, false, true ) );

// With the Institution Dashboard's page missing there is nowhere to send the reader: the names are
// printed plain, and the tab's other cards say the page is missing where it matters.
$kept_url                                  = $GLOBALS['institution_page_url'] ?? null;
$GLOBALS['institution_page_url']           = '';
$unlinked                                  = render_tab( 'agreements' );
$GLOBALS['institution_page_url']           = $kept_url;
ck( 'with no Institution Dashboard page the names are listed without a link', array(
	false !== strpos( $unlinked, '<li>' . esc_html( trim( $rows[ $confirmed[3] ]['name'] ) ) . ' <code class="wpcpm-inst-record">' . $confirmed[3] . '</code></li>' ),
	substr_count( $unlinked, '#wpcpm-agreement' ),
), array( true, 0 ) );

$GLOBALS['summaries'] = array_fill_keys( $confirmed, array( 'state' => 'on_file' ) );
$recorded             = render_tab( 'agreements' );
ck( 'with every Confirmed institution recorded the card says so, and draws no list and no sentence', array(
	false !== strpos( $recorded, '<div class="wpcpm-card"><h2>Agreements on file</h2><p class="wpcpm-inst-read">Pipeline index: read ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).</p><p>No Confirmed institution is waiting for its agreement to be recorded.</p></div>' ),
	strpos( $recorded, 'wpcpm-inst-unrecorded' ),
	strpos( $recorded, 'Record each one from its own page' ),
), array( true, false, false ) );

$GLOBALS['summaries'] = $kept_summaries;

/* ---- agreements in force ------------------------------------------------ */

// The card lists every institution whose agreement is in force, the one accepted on the site and the
// ones recorded on file alike, and draws a folded form to revoke each (1.123.29). It posts to the
// handler that has been there since the revoke was built, which nothing posted to: the form is
// the new part, and what it posts is what the handler reads.

echo "\n=== Agreements in force ===\n";

$force_ids   = array_keys( $rows );
$force_rows  = $rows;
$force_names = array(
	0 => 'Zeta College',
	1 => 'alpha Institute ',
	2 => 'Émile School',
);
foreach ( $force_names as $at => $force_name ) {
	$force_rows[ $force_ids[ $at ] ]['name'] = $force_name;
}
// The second is in force and no longer Confirmed: the card lists what is in force, not a stage.
$force_rows[ $force_ids[2] ]['stage'] = 'Under Review';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $force_rows;

$GLOBALS['summaries'] = array(
	$force_ids[0] => array( 'state' => 'accepted', 'kind' => 'template', 'accepted_at' => '2026-02-02', 'agreement_id' => 7001, 'route' => 'site' ),
	$force_ids[1] => array( 'state' => 'on_file', 'kind' => 'legacy', 'accepted_at' => '2026-09-02', 'agreement_id' => 7002, 'route' => 'grid' ),
	$force_ids[2] => array( 'state' => 'accepted', 'kind' => 'own', 'accepted_at' => '2026-03-05', 'agreement_id' => 7003, 'route' => 'site' ),
	// Not in force: nothing to revoke, so no row.
	$force_ids[3] => array( 'state' => 'generated', 'generated_id' => 7004 ),
	$force_ids[4] => array( 'state' => 'submitted', 'pending_id' => 7005 ),
	$force_ids[5] => array( 'state' => 'returned' ),
	$force_ids[6] => array( 'state' => 'revoked' ),
);
$in_force_tab  = render_tab( 'agreements' );
$in_force_card = preg_match( '#<div class="wpcpm-card"><h2>Agreements in force</h2>(.*?)</div><div class="wpcpm-card">#s', $in_force_tab, $force_match ) ? $force_match[1] : '';
preg_match_all( '#<li class="wpcpm-inst-inforce__row">(.*?)</li>#s', $in_force_card, $force_listed );
$force_li = $force_listed[1];
$link_of  = function ( $name, $record ) {
	return '<a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $record . '">' . esc_html( $name ) . '</a>';
};

ck( 'the card comes after Agreements on file, and the tab draws four cards in their order', array(
	strpos( $in_force_tab, '<h2>Agreements on file</h2>' ) < strpos( $in_force_tab, '<h2>Agreements in force</h2>' ),
	strpos( $in_force_tab, '<h2>Agreements in force</h2>' ) < strpos( $in_force_tab, 'Agreement discrepancies' ),
	cards_of( $in_force_tab ),
), array( true, true, array( 'Agreements on file', 'Agreements in force', 'Agreement discrepancies', 'Agreement template' ) ) );
ck( 'it holds one row for each agreement in force, the one accepted on the site and the ones recorded on file, whatever the stage, and none for a generated, submitted, returned or revoked one', count( $force_li ), 3 );
ck( 'sorted by name, without regard to case or accents, the name linked to its Institution Dashboard as an Administrator sees it', array(
	0 === strpos( $force_li[0], '<p class="wpcpm-inst-inforce__line"><span class="wpcpm-inst-name__text">' . $link_of( 'alpha Institute', $force_ids[1] ) . '</span>' ),
	0 === strpos( $force_li[1], '<p class="wpcpm-inst-inforce__line"><span class="wpcpm-inst-name__text">' . $link_of( 'Émile School', $force_ids[2] ) . '</span>' ),
	0 === strpos( $force_li[2], '<p class="wpcpm-inst-inforce__line"><span class="wpcpm-inst-name__text">' . $link_of( 'Zeta College', $force_ids[0] ) . '</span>' ),
), array( true, true, true ) );
ck( 'each row says how its agreement came to be in force, and the day, in the site\'s date format', array(
	false !== strpos( $force_li[0], '<span class="wpcpm-inst-muted">Recorded on file, September 2, 2026</span>' ),
	false !== strpos( $force_li[1], '<span class="wpcpm-inst-muted">Accepted on the site, March 5, 2026</span>' ),
	false !== strpos( $force_li[2], '<span class="wpcpm-inst-muted">Accepted on the site, February 2, 2026</span>' ),
), array( true, true, true ) );
ck( 'and a closed fold, "Revoke this agreement", holds the row\'s form', array(
	substr_count( $in_force_card, '<details class="wpcpm-inst-folded"><summary>Revoke this agreement</summary>' ),
	substr_count( $in_force_card, '<details' ),
	substr_count( $in_force_card, '<details class="wpcpm-inst-folded" open>' ),
	substr_count( $in_force_card, '<form' ),
), array( 3, 3, 0, 3 ) );

// The form: what the handler reads, under the names it reads them by.
$form_of = function ( $li ) {
	return preg_match( '#<form[^>]*>.*?</form>#s', $li, $m ) ? $m[0] : '';
};
$first_form = $form_of( $force_li[2] );
ck( 'the form posts to the revoke handler, keyed to the document, once', array(
	0 === strpos( $first_form, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-review__form" data-wpcpm-once data-wpcpm-busy="Revoking">' ),
	false !== strpos( $first_form, '<input type="hidden" name="_wpnonce" value="nonce-wpcpm_agreement_revoke_7001" />' ),
	false !== strpos( $first_form, '<input type="hidden" name="action" value="wpcpm_agreement_revoke" />' ),
	false !== strpos( $first_form, '<input type="hidden" name="wpcpm_agreement_post" value="7001" />' ),
), array( true, true, true, true ) );
ck( 'each row\'s form is keyed to its own document', array(
	false !== strpos( $form_of( $force_li[0] ), 'nonce-wpcpm_agreement_revoke_7002' ) && false !== strpos( $form_of( $force_li[0] ), 'name="wpcpm_agreement_post" value="7002"' ),
	false !== strpos( $form_of( $force_li[1] ), 'nonce-wpcpm_agreement_revoke_7003' ) && false !== strpos( $form_of( $force_li[1] ), 'name="wpcpm_agreement_post" value="7003"' ),
), array( true, true ) );
ck( 'the note is a required box of 20 to 2,000 characters, named the way the handler reads it, under the label the refusal names it by', array(
	false !== strpos( $first_form, '<label for="wpcpm-revoke-note-7001">Why it is revoked, in your own words</label>' ),
	false !== strpos( $first_form, '<textarea id="wpcpm-revoke-note-7001" name="wpcpm_agreement_note" rows="4" minlength="20" maxlength="2000" required></textarea>' ),
	WPCPM_Institution_Panel::revoke_label(),
), array( true, true, 'Why it is revoked, in your own words' ) );
ck( 'and the line under it says who receives the note', false !== strpos( $first_form, '<p class="description">Every member of this institution receives this note by email.</p>' ), true );
ck( 'the button is "Revoke this agreement" and asks first, naming the institution and what follows', false !== strpos( $first_form, '<button type="submit" class="button button-primary" data-wpcpm-confirm="Revoke the agreement with Zeta College? Its members keep their accounts but reach only the agreement panel, pending invitations are canceled, and every member receives your note by email.">Revoke this agreement</button>' ), true );
ck( 'the form comes back to the tab it was drawn on: no way back to a dashboard is carried, so the handler goes back where the request came from', array(
	strpos( $first_form, 'wpcpm_return' ),
	strpos( $in_force_card, 'wpcpm_return' ),
), array( false, false ) );
ck( 'the card is the only place the tab offers a press, and this is the handler\'s own action', array_values( array_unique( ( function ( $html ) { preg_match_all( '#<input type="hidden" name="action" value="([^"]+)" />#', $html, $m ); return $m[1]; } )( $in_force_tab ) ) ), array( 'wpcpm_agreement_revoke' ) );
ck( 'when the index was read comes first, as every card that reads the index says', 0 === strpos( $in_force_card, '<p class="wpcpm-inst-read">Pipeline index: read ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).</p>' ), true );
ck( 'the card asks for no member and no address, and no record ID is printed', array( count( $GLOBALS['member_reads'] ), preg_match( '/@example\.test/', $in_force_card ), false !== strpos( $in_force_card, 'wpcpm-inst-record' ) ), array( 0, 0, false ) );

// A name with markup, an apostrophe and a quote: escaped in the row and in the question.
$force_rows[ $force_ids[0] ]['name'] = 'St. Mary\'s <b>& "Sons"</b> College';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $force_rows;
$escaped = render_tab( 'agreements' );
ck( 'a name is escaped wherever it is printed', array(
	false !== strpos( $escaped, esc_html( 'St. Mary\'s <b>& "Sons"</b> College' ) ),
	false !== strpos( $escaped, 'Revoke the agreement with ' . esc_attr( 'St. Mary\'s <b>& "Sons"</b> College' ) . '?' ),
	false !== strpos( $escaped, '<b>& "Sons"</b>' ),
), array( true, true, false ) );
$force_rows[ $force_ids[0] ]['name'] = 'Zeta College';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $force_rows;

// A refused revoke comes back to its row: the fold open, the note as typed, once, in that row's box
// alone. A line break at the start of the note is kept, which the parser would otherwise drop.
$GLOBALS['kept_revoke'] = array( 7003 => "\nThe rector asked us to pause <every> agreement & \"all\" of them." );
$kept_tab               = render_tab( 'agreements' );
$GLOBALS['kept_revoke'] = array();
preg_match_all( '#<li class="wpcpm-inst-inforce__row">(.*?)</li>#s', $kept_tab, $kept_listed );
ck( 'the refused row\'s fold is open and its box holds the note as typed, escaped; the other rows stay closed and empty', array(
	substr_count( $kept_tab, '<details class="wpcpm-inst-folded" open><summary>Revoke this agreement</summary>' ),
	false !== strpos( $kept_listed[1][1], '<details class="wpcpm-inst-folded" open>' ),
	false !== strpos( $kept_listed[1][1], 'required>' . "\n" . "\n" . 'The rector asked us to pause &lt;every&gt; agreement &amp; &quot;all&quot; of them.</textarea>' ),
	false !== strpos( $kept_listed[1][0], '<details class="wpcpm-inst-folded">' ) && false !== strpos( $kept_listed[1][0], 'required></textarea>' ),
	false !== strpos( $kept_listed[1][2], '<details class="wpcpm-inst-folded">' ) && false !== strpos( $kept_listed[1][2], 'required></textarea>' ),
), array( 1, true, true, true, true ) );

// With the Institution Dashboard's page missing there is nowhere to send the reader: plain names.
$kept_url                        = $GLOBALS['institution_page_url'] ?? null;
$GLOBALS['institution_page_url'] = '';
$force_plain                     = render_tab( 'agreements' );
$GLOBALS['institution_page_url'] = $kept_url;
preg_match_all( '#<li class="wpcpm-inst-inforce__row">(.*?)</li>#s', $force_plain, $plain_listed );
ck( 'with no Institution Dashboard page the names are listed without a link, and the forms are still there', array(
	0 === strpos( $plain_listed[1][0], '<p class="wpcpm-inst-inforce__line"><span class="wpcpm-inst-name__text">alpha Institute</span>' ),
	false !== strpos( $plain_listed[1][2], '<span class="wpcpm-inst-name__text">Zeta College</span>' ),
	substr_count( $force_plain, 'wpcpm_institution_view' ),
	substr_count( $force_plain, 'value="wpcpm_agreement_revoke"' ),
), array( true, true, 0, 3 ) );

// A summary in force with no document behind it cannot be revoked: its row is drawn and its form is not.
$GLOBALS['summaries'] = array( $force_ids[0] => array( 'state' => 'on_file', 'agreement_id' => 0, 'accepted_at' => '2026-02-02' ) );
$no_document          = render_tab( 'agreements' );
ck( 'a row with no document to act on says what it is and draws no form', array( substr_count( $no_document, 'wpcpm-inst-inforce__row' ), substr_count( $no_document, 'wpcpm_agreement_revoke' ), substr_count( $no_document, '<details' ) ), array( 1, 0, 0 ) );

// None in force: the card says so, with no list.
$GLOBALS['summaries'] = array(
	$force_ids[3] => array( 'state' => 'generated', 'generated_id' => 7004 ),
	$force_ids[6] => array( 'state' => 'revoked' ),
);
$none_in_force = render_tab( 'agreements' );
ck( 'with none in force the card says so and draws no row and no form', array(
	false !== strpos( $none_in_force, '<div class="wpcpm-card"><h2>Agreements in force</h2><p class="wpcpm-inst-read">Pipeline index: read ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).</p><p>No institution has an agreement in force.</p></div>' ),
	substr_count( $none_in_force, 'wpcpm-inst-inforce' ),
	substr_count( $none_in_force, 'wpcpm_agreement_revoke' ),
), array( true, 0, 0 ) );

// The queue and the other tabs are not given the card.
$GLOBALS['summaries'] = array( $force_ids[0] => array( 'state' => 'accepted', 'agreement_id' => 7001, 'accepted_at' => '2026-02-02' ) );
$elsewhere            = array();
foreach ( array( 'queue', 'pipeline', 'accounts', 'reports', 'sync' ) as $other_tab ) {
	$elsewhere[ $other_tab ] = substr_count( render_tab( $other_tab ), 'Agreements in force' ) + substr_count( render_tab( $other_tab ), 'wpcpm_agreement_revoke' );
}
ck( 'no other tab draws the card or the form', $elsewhere, array( 'queue' => 0, 'pipeline' => 0, 'accounts' => 0, 'reports' => 0, 'sync' => 0 ) );

$GLOBALS['summaries'] = $kept_summaries;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;

/* ---- discrepancies and the template card -------------------------------- */

echo "\n=== Discrepancies and the template card ===\n";

$html = render_tab( 'agreements' );

ck( 'with none, the card says the two sides agree', false !== strpos( $html, 'Agreement discrepancies <span class="wpcpm-count">0</span></h2>' ) && false !== strpos( $html, 'The site and Airtable agree on every agreement.' ), true );

$GLOBALS['discrepancies'] = array(
	'recSEED0000000008' => array( 'site_state' => 'accepted', 'airtable_status' => 'Revoked' ),
	'recNOTINDEXED0001' => array( 'site_state' => '', 'airtable_status' => 'On file' ),
);
$with = render_tab( 'agreements' );
ck( 'each discrepancy is listed by name with both sides', array(
	false !== strpos( $with, '<tr><td>Institution 4<br /><code>recSEED0000000008</code></td><td>accepted</td><td>Revoked</td></tr>' ),
	false !== strpos( $with, '<tr><td>recNOTINDEXED0001<br /><code>recNOTINDEXED0001</code></td><td>(nothing recorded)</td><td>On file</td></tr>' ),
), array( true, true ) );
$GLOBALS['discrepancies'] = array();

$template = WPCPM_Agreement_Template::load( 'en' );
ck( 'the template card shows the version, the read date, the source and the checksum prefix', array(
	false !== strpos( $html, '<th scope="row">Version</th><td>' . $template['version'] . '</td>' ),
	false !== strpos( $html, '<th scope="row">Copied from the Doc on</th><td>' . $template['read'] . '</td>' ),
	false !== strpos( $html, esc_html( $template['source'] ) ),
	false !== strpos( $html, '<code>' . substr( WPCPM_Agreement_Template::checksum( $template ), 0, 12 ) . '</code>' ),
), array( true, true, true, true ) );

// The wording's address is a setting rather than a value in the code: the document is editable
// by anyone holding its link, and this plugin's source is public. With no address given the card
// says so; given one, it links it.
ck( 'with no address given, the card says where the address lives and prints none', array(
	false !== strpos( $html, '(its address is a setting, not carried in the code)' ),
	false !== strpos( $html, 'docs.google.com' ),
), array( true, false ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_doc_url'] = 'https://docs.google.com/document/d/EXAMPLEDOCID/edit';
$with_doc = render_tab( 'agreements' );
unset( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_doc_url'] );

ck( 'and links it once the site has been given one', array(
	false !== strpos( $with_doc, 'href="https://docs.google.com/document/d/EXAMPLEDOCID/edit"' ),
	false !== strpos( $with_doc, '>Open it</a>' ),
), array( true, true ) );
ck( 'and offers no drift button', array( stripos( $html, 'drift' ), stripos( $html, 'Check against the Doc' ) ), array( false, false ) );

// Step four of keeping the copy in step with the Doc: who signed which version. The version
// the site generates is named first, so "an earlier version" has something to be earlier than.
ck( 'the card names the version the site generates today', false !== strpos( $html, 'The site generates ' . $template['version'] . ' (en) today, so anything listed below it was signed against wording the program has changed since.' ), true );
ck( 'and with no agreement recorded anywhere, says no version has been signed', array(
	false !== strpos( $html, '<h3>Institutions per template version</h3>' ),
	false !== strpos( $html, 'No institution has an agreement recorded, so no template version has been signed yet.' ),
), array( true, true ) );

// Four institutions with an agreement: three from the template at two versions, one legacy
// copy with none. The other 102 rows carry an empty agreement block and are not listed at
// all, because a record nobody has asked an agreement from has not signed an old one.
$signed = $rows;
$ids    = array_keys( $signed );

$signed[ $ids[0] ]['agreement'] = array_merge( $signed[ $ids[0] ]['agreement'], array( 'status' => 'Accepted', 'kind' => 'Program template', 'template_version' => '2025-06-12' ) );
$signed[ $ids[1] ]['agreement'] = array_merge( $signed[ $ids[1] ]['agreement'], array( 'status' => 'Accepted', 'kind' => 'Program template', 'template_version' => $template['version'] ) );
$signed[ $ids[2] ]['agreement'] = array_merge( $signed[ $ids[2] ]['agreement'], array( 'status' => 'Accepted', 'kind' => 'Program template', 'template_version' => '2025-06-12' ) );
$signed[ $ids[3] ]['agreement'] = array_merge( $signed[ $ids[3] ]['agreement'], array( 'status' => 'On file', 'kind' => 'Legacy' ) );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $signed;
$versioned = render_tab( 'agreements' );

preg_match_all(
	'#<tr><th scope="row">([^<]+)</th><td>(\d+) <span class="wpcpm-inst-muted">\(([^<]*)\)</span></td></tr>#',
	substr( $versioned, strpos( $versioned, 'Institutions per template version' ) ),
	$vm,
	PREG_SET_ORDER
);

$listed = array();
foreach ( $vm as $row ) { $listed[] = array( html_entity_decode( $row[1], ENT_QUOTES ), (int) $row[2], html_entity_decode( $row[3], ENT_QUOTES ) ); }

ck( 'each version is listed newest first with its count and its institutions', $listed, array(
	array( $template['version'], 1, trim( $rows[ $ids[1] ]['name'] ) ),
	array( '2025-06-12', 2, trim( $rows[ $ids[0] ]['name'] ) . ', ' . trim( $rows[ $ids[2] ]['name'] ) ),
	array( 'No version recorded (the bespoke and legacy agreements)', 1, trim( $rows[ $ids[3] ]['name'] ) ),
) );
ck( 'the rows with no agreement at all are not listed as having signed nothing', array_sum( array_column( $listed, 1 ) ), 4 );
ck( 'and the ones with no version are named for what they are, never called unknown', array(
	stripos( implode( ' ', array_column( $listed, 0 ) ), 'unknown' ),
	false !== strpos( $listed[2][0], 'the bespoke and legacy agreements' ),
), array( false, true ) );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;

/* ---- the semester reports card ------------------------------------------ */

echo "\n=== The semester reports card ===\n";

$html = render_tab( 'reports' );

// One list of what every institution is writing. Two things are being pinned: what it counts,
// and that the consent request is offered here and only here. Open question 2 puts the send in
// a program manager's hands, because the institution is the party that gains from a student's
// yes and must not be the party that asks for it, so this screen is the one that draws it.
// The heading's number is the drafts waiting for review, the number the Administrator Dashboard's
// strip gives as "Semester reports to review" and its Semester reports card lists under "To review",
// not every report the site holds: the list below it still holds the approved ones, and a number
// that counted them would not match the drafts it is read beside.
ck( 'the heading counts the drafts waiting, one of the two reports that exist', false !== strpos( $html, 'Semester reports <span class="wpcpm-count">1</span>' ), true );
ck( 'and not the one somebody trashed', false !== strpos( $html, 'Report 2025-H1' ), false );

$report_rows = array();

if ( preg_match_all( '#<tr><td>(.*?)</tr>#s', $html, $found ) ) {
	foreach ( $found[1] as $row ) {
		if ( false !== strpos( $row, WPCPM_Semester_Report_Screen::ACTION_ASK ) ) { $report_rows[] = $row; }
	}
}

ck( 'each report is a row', count( $report_rows ), 2 );
ck( 'newest edit first', false !== strpos( $report_rows[0], '2026-H1' ), true );
ck( 'a draft says so', false !== strpos( $report_rows[0], 'Draft' ), true );
ck( 'the draft says the site drafted it', false !== strpos( $report_rows[0], 'by the site' ), true );
ck( 'and the approved one that a manager did', false !== strpos( $report_rows[1], 'by an Administrator' ), true );
ck( 'and an approved report says so', false !== strpos( $report_rows[1], 'Approved' ), true );
ck( 'drafts come first whatever their date', false !== strpos( $report_rows[0], 'Draft' ), true );

// A draft must never fall off this queue for being older than the newest sixty edits: sixty-one
// further approved reports, every one newer than the draft 9101, prove the draft still holds row
// 0 while it is the approved half below it that actually gets capped at REPORTS_SHOWN.
for ( $i = 0; $i <= 60; $i++ ) {
	seed_report( 9200 + $i, $report_record, '2020-H1', 'approved', 1758000000 + $i );
}

$capped = render_tab( 'reports' );

ck( 'the heading counts the draft, and not the sixty-one approved reports the list caps', false !== strpos( $capped, 'Semester reports <span class="wpcpm-count">1</span>' ), true );

$capped_rows = array();

if ( preg_match_all( '#<tr><td>(.*?)</tr>#s', $capped, $capped_found ) ) {
	foreach ( $capped_found[1] as $row ) {
		if ( false !== strpos( $row, WPCPM_Semester_Report_Screen::ACTION_ASK ) ) { $capped_rows[] = $row; }
	}
}

ck( 'the draft is still row 0 among sixty-one newer approved reports', false !== strpos( $capped_rows[0], '2026-H1' ) && false !== strpos( $capped_rows[0], 'Draft' ), true );
// One draft, kept whatever its date, plus the newest sixty of the now sixty-two approved
// reports. The cap is on recency, not on which report was here first: the fixture's own
// original approved 2025-H2 report is older than every one of the sixty-one just seeded, so
// it is one of the two the cap leaves out, the other being the single oldest of the sixty-one.
ck( 'every draft plus only the newest REPORTS_SHOWN approved reports', count( $capped_rows ), 1 + 60 );
ck( 'the heading sentence names the approved list as what was capped', false !== strpos( $capped, 'most recently edited approved reports' ), true );

foreach ( range( 9200, 9260 ) as $id ) {
	unset( $GLOBALS['posts'][ $id ], $GLOBALS['pmeta'][ $id ] );
}

// Another draft is one more waiting; with the drafts all approved the number is a zero, and the
// approved reports are still listed.
seed_report( 9300, $report_record, '2024-H1', 'draft', 1757000000 );
ck( 'a second draft makes the heading say 2', false !== strpos( render_tab( 'reports' ), 'Semester reports <span class="wpcpm-count">2</span>' ), true );
unset( $GLOBALS['posts'][9300], $GLOBALS['pmeta'][9300] );
update_post_meta( 9101, '_wpcpm_report_state', 'approved' );
$no_drafts = render_tab( 'reports' );
ck( 'with no draft waiting the heading says 0, over the approved reports still listed', array( false !== strpos( $no_drafts, 'Semester reports <span class="wpcpm-count">0</span>' ), substr_count( $no_drafts, 'value="' . WPCPM_Semester_Report_Screen::ACTION_ASK . '"' ) ), array( true, 2 ) );
update_post_meta( 9101, '_wpcpm_report_state', 'draft' );

ck( 'the due list offers Draft now for the cohort the job would draft', false !== strpos( $html, 'name="cohort" value="2024-H2"' ) && false !== strpos( $html, 'value="' . WPCPM_Semester_Report_Screen::ACTION_DRAFT . '"' ), true );
ck( 'and the picker for any semester is drawn', false !== strpos( $html, 'Draft any semester' ) && false !== strpos( $html, 'name="institution"' ), true );
ck( 'and the log is drawn', false !== strpos( $html, 'Report log' ) && false !== strpos( $html, 'approved' ), true );

// The switcher argument, and the reason it is asserted: the link goes to the institution's own
// dashboard, and without it a manager lands on whichever institution is their fallback and
// reads a different school's report under this row's name.
ck( 'the row opens the report as that institution', false !== strpos( $report_rows[0], WPCPM_Institution_Roster::ARG_VIEW . '=' . $report_record ), true );
ck( 'every row offers the consent request', substr_count( $html, 'value="' . WPCPM_Semester_Report_Screen::ACTION_ASK . '"' ), 2 );
ck( 'each with a nonce keyed to its own report', false !== strpos( $report_rows[0], 'nonce-' . WPCPM_Semester_Report_Screen::ACTION_ASK . '_9101' ), true );

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();

$empty_card = render_tab( 'reports' );

ck( 'with nothing written, the card says so', false !== strpos( $empty_card, 'No report has been drafted yet.' ), true );
ck( 'and offers nobody a request to send', false !== strpos( $empty_card, 'value="' . WPCPM_Semester_Report_Screen::ACTION_ASK . '"' ), false );

/* ---- the words an Administrator reads ------------------------------------ */

echo "\n=== The screen speaks in an Administrator's words ===\n";

// The module's description, the sync card and the checksum row each named a mechanism: the role an
// account is built on, the columns of a table, the hash function behind a pin. An Administrator
// reads what the screen does for the program, so each says that, and none of the three technical
// phrases is on any tab.
$words_module = new WPCPM_Institutions();
$words_lede   = 'Educational institution records from Airtable, the students each one has in the program, and the state of every Collaboration Agreement. Each institution has an Institution Dashboard, its own people and its students; their accounts are created and invited on the Accounts tab.';
$words_sync   = 'Reads the Countries table, then each institution\'s name, stage, country, city, website and contact, and its eight agreement columns, and rebuilds the institution list and each institution\'s agreement state. The long written answers in the base are never read.';
$words_fprint = '(the start of the fingerprint the plugin\'s tests check, which changes only when a word of the agreement does)';

ck( 'the description says what the module holds, and where the institutions\' accounts are made', $words_module->description(), $words_lede );
ck( 'and the screen prints it as its lede', false !== strpos( render_screen(), '<p class="wpcpm-lede">' . esc_html( $words_lede ) . '</p>' ), true );
ck( 'the sync card says what the sync reads and rebuilds, in the program\'s words', false !== strpos( render_tab( 'sync' ), '<h2>Airtable sync</h2><p class="description">' . esc_html( $words_sync ) . '</p>' ), true );
ck( 'the checksum row says what the twelve characters are', false !== strpos( render_tab( 'agreements' ), '<th scope="row">Checksum</th><td><code>' . substr( WPCPM_Agreement_Template::checksum( WPCPM_Agreement_Template::load( 'en' ) ), 0, 12 ) . '</code> <span class="wpcpm-inst-muted">' . esc_html( $words_fprint ) . '</span></td>' ), true );

$still_technical = array();
foreach ( array( 'queue', 'pipeline', 'accounts', 'reports', 'agreements', 'sync' ) as $words_tab ) {
	$words_html = render_tab( $words_tab );
	foreach ( array( 'Subscriber role', 'public columns', 'sha256' ) as $phrase ) {
		if ( false !== stripos( $words_html, $phrase ) ) { $still_technical[] = $words_tab . ': ' . $phrase; }
	}
}
foreach ( array( 'Subscriber role', 'public columns', 'sha256' ) as $phrase ) {
	if ( false !== stripos( $words_module->description(), $phrase ) ) { $still_technical[] = 'description: ' . $phrase; }
}
ck( 'none of "Subscriber role", "public columns" and "sha256" is on the description or any tab', $still_technical, array() );

// The Administrator Dashboard's applications card prints a state in this screen's words, so the
// method that holds them is public, and holds the words it always has.
$state_method = new ReflectionMethod( 'WPCPM_Institutions', 'application_state_label' );
ck( 'application_state_label() is public and static, for the Administrator Dashboard', array( $state_method->isPublic(), $state_method->isStatic() ), array( true, true ) );
ck( 'and its words are the ones the screen has always used, a state it does not know as it stands', array_map(
	array( 'WPCPM_Institutions', 'application_state_label' ),
	array( WPCPM_Institution_Application::STATE_NEW, WPCPM_Institution_Application::STATE_HELD, WPCPM_Institution_Application::STATE_INFO, WPCPM_Institution_Application::STATE_SPAM, WPCPM_Institution_Application::STATE_REJECTED, WPCPM_Institution_Application::STATE_APPROVED, 'other' )
), array( 'new', 'held by the anti-spam checks', 'waiting on the applicant', 'marked as spam', 'rejected', 'approved', 'other' ) );

/* ---- the storage card --------------------------------------------------- */

// The store's own behavior, its directory and its encryption belong to
// bin/test-private-files.php. What is checked here is only what this screen says about it.

echo "\n=== The storage card ===\n";

$base = $GLOBALS['uploads'] . '/' . WPCPM_Private_Files::DIRECTORY . '/';

// What this host does: the dot path is refused by its own rule, a plain uploads path is served.
$GLOBALS['head'] = array( 'response' => array( 'code' => 200 ) );
$result          = WPCPM_Private_Files::probe();
$html            = render_tab( 'sync' );

ck( 'the card says the host refuses direct requests', false !== strpos( $html, 'The host refuses direct requests to the private directory (HTTP 403 on ' . gmdate( 'Y-m-d H:i', $result['time'] ) . ').' ), true );
ck( 'and says the files are encrypted, which is the control that does not need the host', false !== strpos( $html, 'Stored files are encrypted with AES-256-GCM.' ), true );
ck( 'with a Run probe button posting the probe action and naming its tab', array( form_fields( $html, 'wpcpm_institutions_probe' ), false !== strpos( $html, '>Run probe</button>' ) ), array( array( 'action' => 'wpcpm_institutions_probe', 'wpcpm_tab' => 'sync' ), true ) );

// The control is what makes the refusal attributable to the leading dot rather than to a host
// that refuses everything under uploads.
ck( 'the control path was measured and served', array( $result['control_status'] >= 200 && $result['control_status'] < 300 ), array( true ) );
ck( 'so the card explains what the dot is doing', false !== strpos( $html, 'so the dot is what makes the difference' ), true );

// A record from before the control existed must not make the card claim something it did not measure.
$GLOBALS['opts']['wpcpm_private_probe'] = array( 'status' => 403, 'time' => $result['time'], 'blocked' => true, 'error' => '' );
ck( 'an older record leaves the explanation out rather than inventing it', false === strpos( render_tab( 'sync' ), 'so the dot is what makes the difference' ), true );

// The host changing its mind: the card must still be honest, and must say the bytes are useless.
$GLOBALS['head'] = array( 'response' => array( 'code' => 200 ) );
$result          = WPCPM_Private_Files::probe_result();
$result          = array( 'status' => 200, 'time' => $result['time'], 'blocked' => false, 'error' => '', 'control_status' => 200, 'encrypted' => true );
$GLOBALS['opts']['wpcpm_private_probe'] = $result;
$html = render_tab( 'sync' );
ck( 'a served verdict warns, names the path and says what is exposed', array(
	false !== strpos( $html, 'The host hands out files in the private directory to anyone who asks (HTTP 200 on ' . gmdate( 'Y-m-d H:i', $result['time'] ) . ').' ),
	false !== strpos( $html, 'What it hands over is encrypted' ),
	false !== strpos( $html, '/wp-content/uploads/.wpcpm-private/ should not be reachable' ),
	false !== strpos( $html, 'wpcpm-warning' ),
), array( true, true, true, true ) );

$GLOBALS['opts']['wpcpm_private_probe'] = array( 'status' => 0, 'time' => $result['time'], 'blocked' => false, 'error' => 'cURL error 28', 'control_status' => 0, 'encrypted' => true );
ck( 'a failed probe says it could not tell', false !== strpos( render_tab( 'sync' ), 'The probe could not tell what the host does (on ' . gmdate( 'Y-m-d H:i', $result['time'] ) . '): cURL error 28' ), true );

$GLOBALS['opts']['wpcpm_private_probe'] = array( 'status' => 503, 'time' => $result['time'], 'blocked' => false, 'error' => '', 'control_status' => 0, 'encrypted' => true );
ck( 'and a 5xx is neither verdict', false !== strpos( render_tab( 'sync' ), 'it answered HTTP 503 on ' ), true );

delete_option( 'wpcpm_private_probe' );
ck( 'with no record probe_result() is null', WPCPM_Private_Files::probe_result(), null );
ck( 'and the card says the probe has not run', false !== strpos( render_tab( 'sync' ), 'The probe has not run yet.' ), true );

update_option( 'wpcpm_private_probe', 'garbage', false );
ck( 'a malformed record is null too', WPCPM_Private_Files::probe_result(), null );

// path(): inside only, files only, resolved through realpath.
mkdir( $base . 'agreements/2026', 0777, true );
file_put_contents( $base . 'agreements/2026/abc.pdf', '%PDF-' );
file_put_contents( $GLOBALS['uploads'] . '/outside.txt', 'secret' );
$real = realpath( $base . 'agreements/2026/abc.pdf' );

ck( 'a stored relative path resolves to the file', WPCPM_Private_Files::path( 'agreements/2026/abc.pdf' ), $real );
ck( 'a leading slash is tolerated', WPCPM_Private_Files::path( '/agreements/2026/abc.pdf' ), $real );
ck( 'dot-dot out of the base is refused', WPCPM_Private_Files::path( '../outside.txt' ), false );
ck( 'so is a longer climb', WPCPM_Private_Files::path( 'agreements/../../outside.txt' ), false );
ck( 'the base itself is refused', WPCPM_Private_Files::path( '.' ), false );
ck( 'a directory inside is refused', WPCPM_Private_Files::path( 'agreements/2026' ), false );
ck( 'a file that does not exist is refused', WPCPM_Private_Files::path( 'agreements/2026/nope.pdf' ), false );
ck( 'an empty path is refused', WPCPM_Private_Files::path( '' ), false );
ck( 'a NUL byte is refused', WPCPM_Private_Files::path( "agreements/2026/abc.pdf\0" ), false );

if ( function_exists( 'symlink' ) && @symlink( $GLOBALS['uploads'] . '/outside.txt', $base . 'agreements/link.txt' ) ) {
	ck( 'a symlink pointing outside is refused', WPCPM_Private_Files::path( 'agreements/link.txt' ), false );
}

/* ---- notify_managers() -------------------------------------------------- */

echo "\n=== notify_managers() ===\n";

$build = function ( $who ) {
	return array( 'subject' => 'Signed agreement waiting', 'body' => is_object( $who ) ? 'Hello ' . $who->display_name : 'Hello ' . $who );
};

$GLOBALS['mail'] = array();
$sent = WPCPM_Institutions::notify_managers( 'agreement-landed', $build );
ck( 'with the setting empty, every manager with an address is reached through send()', array( $sent, array_map( function ( $s ) { return array( $s[0], $s[1] ); }, $GLOBALS['mail'] ) ), array( 2, array( array( 'send', 1 ), array( 'send', 2 ) ) ) );
ck( 'the manager with no address is skipped, not failed', in_array( 3, array_column( $GLOBALS['mail'], 1 ), true ), false );
ck( 'and the builder saw the account', $GLOBALS['mail'][0][3]['body'], 'Hello Ada Admin' );
ck( 'the context reaches the log', $GLOBALS['mail'][0][2], 'agreement-landed' );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_notify'] = 'one@example.org,two@example.org';
$GLOBALS['mail'] = array();
$sent = WPCPM_Institutions::notify_managers( 'agreement-landed', $build );
ck( 'with the setting set, only the listed addresses are reached, through send_to()', array( $sent, array_map( function ( $s ) { return array( $s[0], $s[1] ); }, $GLOBALS['mail'] ) ), array( 2, array( array( 'send_to', 'one@example.org' ), array( 'send_to', 'two@example.org' ) ) ) );
ck( 'and the builder saw the bare address', $GLOBALS['mail'][0][3]['body'], 'Hello one@example.org' );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_notify'] = 'max@example.test, stranger@example.org';
$GLOBALS['mail'] = array();
WPCPM_Institutions::notify_managers( 'agreement-landed', $build );
ck( 'a listed address that belongs to an account goes through send(), so it is built in their language', array_map( function ( $s ) { return array( $s[0], $s[1] ); }, $GLOBALS['mail'] ), array( array( 'send', 2 ), array( 'send_to', 'stranger@example.org' ) ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_notify'] = 'not-an-address';
$GLOBALS['mail'] = array();
ck( 'junk in the setting sends nothing and does not fall back to every manager', array( WPCPM_Institutions::notify_managers( 'agreement-landed', $build ), $GLOBALS['mail'] ), array( 0, array() ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_notify'] = '';
ck( 'a builder that is not callable sends nothing', WPCPM_Institutions::notify_managers( 'agreement-landed', 'nope' ), 0 );

/* ---- handlers ----------------------------------------------------------- */

echo "\n=== Handlers ===\n";

$module = new WPCPM_Institutions();

/**
 * Run a handler and report how it ended.
 *
 * @param callable $fn The handler.
 * @return string The exception message, or 'returned'.
 */
function outcome( callable $fn ) {
	$GLOBALS['referer'] = array();
	try { $fn(); return 'returned'; } catch ( Exception $e ) { return $e->getMessage(); }
}

$GLOBALS['caps'] = false;
ck( 'handle_sync without the capability dies 403 before any nonce is read', array( outcome( array( $module, 'handle_sync' ) ), $GLOBALS['referer'] ), array( 'wp_die: You do not have permission to manage the program.', array() ) );
ck( 'so does handle_cancel', array( outcome( array( $module, 'handle_cancel' ) ), $GLOBALS['referer'] ), array( 'wp_die: You do not have permission to manage the program.', array() ) );
ck( 'and handle_probe', array( outcome( array( $module, 'handle_probe' ) ), $GLOBALS['referer'] ), array( 'wp_die: You do not have permission to manage the program.', array() ) );
ck( 'handle_tick answers a 403 JSON error', array( outcome( array( $module, 'handle_tick' ) ), $GLOBALS['referer'] ), array( 'json_error:403', array() ) );

// What each form on the Sync and storage tab posts, read off the tab: a press comes back to the
// tab it was made on only when the form and the handler agree on which tab that is.
$sync_tab  = render_tab( 'sync' );
$back_sync = 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=sync';

$GLOBALS['sync_progress'] = array( 'running' => true );
$cancel_post              = form_fields( render_tab( 'sync' ), 'wpcpm_institutions_cancel' );
$GLOBALS['sync_progress'] = array();

ck( 'the sync, the cancel and the probe forms each name the Sync and storage tab', array(
	form_fields( $sync_tab, 'wpcpm_institutions_sync' ),
	$cancel_post,
	form_fields( $sync_tab, 'wpcpm_institutions_probe' ),
), array(
	array( 'action' => 'wpcpm_institutions_sync', 'wpcpm_tab' => 'sync' ),
	array( 'action' => 'wpcpm_institutions_cancel', 'wpcpm_tab' => 'sync' ),
	array( 'action' => 'wpcpm_institutions_probe', 'wpcpm_tab' => 'sync' ),
) );

$GLOBALS['caps']  = true;
$GLOBALS['calls'] = array();
$_POST            = form_fields( $sync_tab, 'wpcpm_institutions_sync' );
ck( 'handle_sync starts the sync and comes back to the Sync and storage tab', array( outcome( array( $module, 'handle_sync' ) ), $GLOBALS['referer'], in_array( array( 'WPCPM_Institutions_Sync::start' ), $GLOBALS['calls'], true ) ), array( $back_sync, array( 'wpcpm_institutions_sync' ), true ) );
// Read from the pending meta rather than through take(): take() memoizes per request, and
// the renders above already consumed this channel for this process.
ck( 'leaving a one-shot flash for the screen to show', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'started' ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['sync_refuses'] = true;
outcome( array( $module, 'handle_sync' ) );
ck( 'a refused start flashes error', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'error' ) );
$GLOBALS['sync_refuses'] = false;
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['calls'] = array();
$_POST            = $cancel_post;
ck( 'handle_cancel cancels, flashes and comes back to the Sync and storage tab', array( outcome( array( $module, 'handle_cancel' ) ), $GLOBALS['referer'], in_array( array( 'WPCPM_Institutions_Sync::cancel' ), $GLOBALS['calls'], true ), get_user_meta( 1, WPCPM_Flash::META ) ), array( $back_sync, array( 'wpcpm_institutions_cancel' ), true, array( 'institutions' => 'cancelled' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['head'] = array( 'response' => array( 'code' => 403 ) );
$_POST           = form_fields( $sync_tab, 'wpcpm_institutions_probe' );
ck( 'handle_probe runs the probe, flashes and comes back to the Sync and storage tab', array( outcome( array( $module, 'handle_probe' ) ), $GLOBALS['referer'], get_option( 'wpcpm_private_probe' )['status'], get_user_meta( 1, WPCPM_Flash::META ) ), array( $back_sync, array( 'wpcpm_institutions_probe' ), 403, array( 'institutions' => 'probed' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

// The whole way round, as a manager who has drawn nothing in this request: the press made on the
// tab lands on it, and the outcome it left prints there.
$GLOBALS['uid'] = 61;
$landed         = outcome( array( $module, 'handle_probe' ) );
$landed_html    = render_screen( landed_on( $landed ) );
$GLOBALS['uid'] = 1;
ck( 'a press on Sync and storage comes back to that tab and its outcome prints there', array(
	$landed,
	cards_of( $landed_html ),
	false !== strpos( $landed_html, '<div class="notice notice-success is-dismissible"><p>The probe ran. The storage card says what the host did.</p></div>' ),
), array( $back_sync, array( 'Airtable sync', 'Reconciliation', 'Storage' ), true ) );
delete_user_meta( 61, WPCPM_Flash::META );

$GLOBALS['head'] = new WP_Error( 'http_request_failed', 'no route' );
outcome( array( $module, 'handle_probe' ) );
ck( 'a probe that could not ask flashes probe-failed', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'probe-failed' ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['sync_running'] = true;
$GLOBALS['calls'] = array();
ck( 'handle_tick advances a running sync and answers with progress', array( outcome( array( $module, 'handle_tick' ) ), $GLOBALS['referer'], in_array( array( 'WPCPM_Institutions_Sync::tick', 8 ), $GLOBALS['calls'], true ), $GLOBALS['json']['running'] ), array( 'json_success', array( 'wpcpm_institutions_tick' ), true, false ) );
$GLOBALS['sync_running'] = false;
$GLOBALS['calls'] = array();
outcome( array( $module, 'handle_tick' ) );
ck( 'and leaves an idle one alone', in_array( array( 'WPCPM_Institutions_Sync::tick', 8 ), $GLOBALS['calls'], true ), false );

/* ---- the decisions' way back -------------------------------------------- */

// The six decisions on an application post no tab: they come back to the screen's own address,
// which is the queue. One institution's Create account, a Create account on the ticked
// institutions and the invitations come back to the Accounts tab, which
// bin/test-institutions-accounts.php follows them to.
$back = 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions';

// The running panel, with the attributes admin.js reads.
$GLOBALS['sync_progress'] = array( 'running' => true, 'label' => 'Reading institution records…', 'step_label' => 'Step 2 of 4', 'percent' => 40, 'detail' => '53 of 106', 'elapsed' => 75, 'stalled' => false );
$running = render_tab( 'sync' );
ck( 'a running sync draws the progress panel admin.js polls', array(
	false !== strpos( $running, '<div class="wpcpm-progress" data-wpcpm-progress data-action="wpcpm_institutions_tick" data-nonce="nonce" data-poll="3">' ),
	false !== strpos( $running, '<strong data-wpcpm-label>Reading institution records…</strong>' ),
	false !== strpos( $running, 'aria-valuenow="40"' ),
	false !== strpos( $running, '<span data-wpcpm-elapsed data-label="running for %s">running for 1:15</span>' ),
	false !== strpos( $running, '<input type="hidden" name="action" value="wpcpm_institutions_cancel" />' ),
	false === strpos( $running, 'value="wpcpm_institutions_sync"' ),
), array( true, true, true, true, true, true ) );
$GLOBALS['sync_progress'] = array( 'error' => 'Airtable said no' );
$idle = render_tab( 'sync' );
ck( 'an idle sync offers the start button and the last error', array(
	false !== strpos( $idle, '<input type="hidden" name="action" value="wpcpm_institutions_sync" />' ),
	false !== strpos( $idle, '<strong>Last sync error:</strong> Airtable said no' ),
	false !== strpos( $idle, 'No sync has run yet.' ),
), array( true, true, true ) );
ck( 'and the last error is the Sync and storage tab\'s, above its sync card, and no other tab\'s', array(
	strpos( $idle, 'Last sync error:' ) < strpos( $idle, '<h2>Airtable sync</h2>' ),
	strpos( render_screen(), 'Last sync error:' ),
	strpos( render_tab( 'pipeline' ), 'Last sync error:' ),
), array( true, false, false ) );
$GLOBALS['sync_progress'] = array();
$GLOBALS['sync_last'] = $read_at;
ck( 'a completed run prints when', false !== strpos( render_tab( 'sync' ), 'Last completed ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).' ), true );

/* ---- the review queue --------------------------------------------------- */

echo "\n=== The review queue ===\n";

$day = 86400;
$now = time();

// A country the seed routes to somebody, so the "for information" line has a name on it,
// and the first record the pipeline index holds, so an agreement row has an institution.
$routed = '';

foreach ( $countries as $country_id => $country_row ) {
	if ( '' !== $country_row['manager'] ) { $routed = $country_id; break; }
}

$unrouted = '';

foreach ( $countries as $country_id => $country_row ) {
	if ( '' === $country_row['manager'] && '' === $country_row['email'] ) { $unrouted = $country_id; break; }
}

$record       = array_key_first( $rows );
$record_name  = trim( $rows[ $record ]['name'] );
$routed_name  = $countries[ $routed ]['name'];

$consent = array(
	'sentence' => 'I confirm this institution complies with its privacy policy.',
	'url'      => 'https://example.test/privacy/',
	'policy'   => 12,
	'modified' => '2026-08-01 09:00',
	'at'       => $now - ( 10 * $day ),
	'ip'       => '203.0.113.0',
	'agent'    => 'Mozilla/5.0',
);

$answers = array(
	'Name'           => 'Universidad Example',
	'City'           => 'Cartago',
	'Website'        => 'universidad.example',
	'Contact Person' => 'Ana Example',
	'Contact Email'  => 'ana@example.test',
	'Department'     => 'Computer Science',
	'How do your internships or practices typically work?'                  => ' Credit-bearing internships, Final projects',
	'Comments'       => '<script>alert(1)</script>',
	'Estimated number of students who may be interested'                    => '25',
	'Why are you interested in offering WordPress Credits to your students?' => 'Our students need real projects.',
	'Anything else you’d like us to know?'                                   => '',
);

// The five answers kept on the site since 1.123.29, under their own key and never in the fields an
// approval writes to Airtable.
$site_answers = array(
	'contact_role'      => 'Internship coordinator',
	'student_languages' => array( 'Spanish', 'Portuguese' ),
	'signer_name'       => 'Signer Example',
	'signer_title'      => '<em>Dean</em> of the Faculty of Engineering',
	'credits_page'      => 'https://universidad.example/wordpress-credits',
);

seed_application(
	501,
	'Universidad Example',
	WPCPM_Institution_Application::STATE_NEW,
	$now - ( 10 * $day ),
	array(
		WPCPM_Institution_Application::META_FIELDS       => $answers,
		WPCPM_Institution_Application::META_SITE         => $site_answers,
		WPCPM_Institution_Application::META_REFERENCE    => 'APP-2026-0007',
		WPCPM_Institution_Application::META_COUNTRY      => $routed,
		WPCPM_Institution_Application::META_COUNTRY_NAME => $routed_name,
		WPCPM_Institution_Application::META_CONSENT      => $consent,
		WPCPM_Institution_Application::META_EMAIL        => 'hash-of-ana',
		WPCPM_Institution_Application::META_VERIFIED     => (string) ( $now - ( 9 * $day ) ),
	)
);

// The same institution again, under a name that differs only by case and whitespace: the
// pair the queue flags and never merges.
seed_application(
	502,
	' Universidad EXAMPLE ',
	WPCPM_Institution_Application::STATE_HELD,
	$now - ( 5 * $day ),
	array(
		WPCPM_Institution_Application::META_FIELDS    => array( 'Contact Email' => 'someone.else@example.test' ),
		WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0008',
		WPCPM_Institution_Application::META_COUNTRY   => $routed,
		WPCPM_Institution_Application::META_EMAIL     => 'hash-of-someone-else',
		// Three content signals, which is what a held row really carries: `honeypot` and
		// `dwell` make a submission spam rather than held, and `duplicate` on its own holds
		// nothing.
		WPCPM_Institution_Application::META_SIGNALS   => array( 'no-mx', 'short', 'duplicate' ),
	)
);

seed_application(
	503,
	'Escola Nova',
	WPCPM_Institution_Application::STATE_INFO,
	$now - ( 2 * $day ),
	array(
		WPCPM_Institution_Application::META_FIELDS    => array( 'Contact Email' => 'reitoria@example.test' ),
		WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0009',
		WPCPM_Institution_Application::META_COUNTRY   => $unrouted,
		WPCPM_Institution_Application::META_EMAIL     => 'hash-of-reitoria',
	)
);

// One signed agreement waiting, four days old.
$agreement                = new WP_Post();
$agreement->ID            = 601;
$agreement->post_type     = WPCPM_Institution_Agreement::POST_TYPE;
$agreement->post_status   = 'private';
$agreement->post_title    = 'Signed agreement';
$agreement->post_date_gmt = gmdate( 'Y-m-d H:i:s', $now - ( 4 * $day ) );

$GLOBALS['posts'][601] = $agreement;
update_post_meta( 601, WPCPM_Institution_Agreement::META_INSTITUTION, $record );
update_post_meta( 601, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_SUBMITTED );
$GLOBALS['awaiting'] = array( 601 );

// Two mentor requests, at the two institutions the index holds after the agreement's. One has
// waited sixteen days, past the fourteen a request is given before it is overdue, and is seeded
// with a note, which only closing a request writes today, so the row's note branch and its
// escaping are read; the other seven, which the three days an application or an agreement is
// given would mark overdue and the request's own fourteen do not, and its author's account is
// gone.
$request_records = array_slice( array_keys( $rows ), 1, 2 );
$request_names   = array( trim( $rows[ $request_records[0] ]['name'] ), trim( $rows[ $request_records[1] ]['name'] ) );

seed_request( 802, $request_records[0], 'Ana Student', 'Rep One', 'Both of them start in <b>March</b>.', $now - ( 16 * $day ) );
seed_request( 801, $request_records[1], 'Bo Student', '', '', $now - ( 7 * $day ) );
// Oldest first, the order the real reader answers in.
$GLOBALS['open_requests'] = array( 802, 801 );

$GLOBALS['calls']             = array();
$GLOBALS['reviews']           = array();
$GLOBALS['request_decisions'] = array();
// The screen's own address, which names no tab: the queue.
$html         = render_screen();
$html_reviews = $GLOBALS['reviews'];
$dashboard    = 'https://example.test/administrator-dashboard/';

ck( 'the queue is one list, oldest first, of applications, agreements and mentor requests together', queue_items( $html ), array(
	array( $request_names[0], 'Mentor request', true ),
	array( 'Universidad Example', 'Application', true ),
	array( $request_names[1], 'Mentor request', false ),
	array( 'Universidad EXAMPLE', 'Application', true ),
	array( $record_name, 'Signed agreement', true ),
	array( 'Escola Nova', 'Application', false ),
) );
ck( 'the card counts what is waiting', preg_match( '#<h2 id="wpcpm-queue">Waiting for review <span class="wpcpm-count">6</span></h2>#', $html ), 1 );
// Overdue is `agreement_review_days` for an application and an agreement, which the fixture leaves
// at the shipped 3, and the request's own fourteen days for a request, which `facts()` answers.
ck( 'and the four that have waited past their own kind\'s days carry is-overdue, the seven-day request not among them', substr_count( $html, 'wpcpm-queue-item is-overdue' ), 4 );
ck( 'the list says what it holds, where each is decided, and both thresholds', false !== strpos( $html, '<p class="description">Applications from institutions, signed agreements and requests from institutions, in one list, oldest first: they are one queue and a person works it from the top. Each is decided on the Administrator Dashboard, and each row links to its place there. An application or a signed agreement is marked overdue once it has waited longer than 3 days. A request from an institution is marked overdue once it has waited longer than 14 days.</p>' ), true );
ck( 'and an empty one says all three kinds appear on it', false !== strpos( $tabs['queue'], 'Nothing is waiting. New applications, uploaded agreements and requests from institutions appear here.' ), true );

// The setting runs from one day, and a count is a plural like any other.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_review_days'] = 1;
$one_day = render_screen();
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['agreement_review_days'] = 3;
ck( 'and a threshold of one day says one day', array(
	false !== strpos( $one_day, 'An application or a signed agreement is marked overdue once it has waited longer than 1 day.' ),
	strpos( $one_day, '1 days' ),
), array( true, false ) );

// A request row's own facts, under its age and its country: who the mentor is wanted for and who
// asked, then the note when one is stored. That is the closing note, empty while a request is
// open; the row prints it so it stays right if raising a request ever stores one, escaped like
// every other value on the screen.
ck( 'a mentor request names the student and who raised it, and a stored note, which only closing writes today, is printed escaped', array(
	false !== strpos( $html, '<p>A mentor is wanted for Ana Student. Raised by Rep One.</p>' ),
	false !== strpos( $html, '<p>Both of them start in &lt;b&gt;March&lt;/b&gt;.</p>' ),
	strpos( $html, '<b>March</b>' ),
), array( true, true, false ) );
ck( 'and one whose author has no account says so, with no empty note under it', array(
	false !== strpos( $html, '<p>A mentor is wanted for Bo Student. Raised by somebody whose account is gone.</p>' ),
	strpos( $html, '<p></p>' ),
), array( true, false ) );
ck( 'a request row says its country for information, as every row does', false !== strpos( queue_chunks( $html )[0], esc_html( $countries[ $rows[ $request_records[0] ]['country'] ]['name'] ) . '.' ), true );

// A request of another kind is labeled a request and says its kind in the request class's own
// words, and its sentence follows the kind: the student it is about when there is one, and only who
// raised it when there is none, as a change to the report is about no student.
$mentor_requests = $GLOBALS['open_requests'];
seed_request( 803, $request_records[0], 'Cy Student', 'Rep One', '', $now - ( 6 * $day ), 'add' );
seed_request( 804, $request_records[1], '', 'Rep One', '', $now - ( 3 * $day ), 'format' );
$GLOBALS['open_requests'] = array( 802, 801, 803, 804 );
$kinds_html               = render_screen();
$GLOBALS['open_requests'] = $mentor_requests;
unset( $GLOBALS['request_facts'][803], $GLOBALS['request_facts'][804] );

$kind_rows  = queue_chunks( $kinds_html );
$kind_links = array_column( queue_links( $kinds_html ), 2 );
ck( 'a request of another kind is labeled a request, says its kind, and names a student only when it is about one', array(
	array_column( queue_items( $kinds_html ), 1 ),
	array(
		false !== strpos( $kind_rows[3], '<p>A student to add</p><p>The student is Cy Student. Raised by Rep One.</p>' ),
		strpos( $kind_rows[3], 'A mentor is wanted' ),
	),
	array(
		false !== strpos( $kind_rows[6], '<p>A change to the report</p><p>Raised by Rep One.</p>' ),
		strpos( $kind_rows[6], 'wanted for' ),
		strpos( $kind_rows[6], 'The student is' ),
	),
	array(
		false !== strpos( $kind_rows[0], '<p>A mentor is wanted for Ana Student. Raised by Rep One.</p>' ),
		strpos( $kind_rows[0], '<p>A mentor is wanted</p>' ),
	),
	array( $kind_links[3], $kind_links[6] ),
), array(
	array( 'Mentor request', 'Application', 'Mentor request', 'Request', 'Application', 'Signed agreement', 'Request', 'Application' ),
	array( true, false ),
	array( true, false, false ),
	array( true, false ),
	array( $dashboard . '#wpcpm-requests', $dashboard . '#wpcpm-requests' ),
) );

// A school's request to correct its details (Institutions release 2) is a row of its own kind: labeled
// "Details to correct" in the row's heading, counted in the queue's number and the menu's bubble with
// the rest, about no student, and decided on the Administrator Dashboard like every request.
$before_details = $GLOBALS['open_requests'];
seed_request( 805, $request_records[0], '', 'Rep One', '', $now - ( 2 * $day ), 'details' );
$GLOBALS['open_requests'] = array( 802, 801, 805 );
$GLOBALS['calls']         = array();
$details_html             = render_screen();
$details_rows             = queue_chunks( $details_html );
$details_pos              = array_search( 'Details to correct', array_column( queue_items( $details_html ), 1 ), true );
$GLOBALS['request_facts'][805]['actor_name'] = '';
$gone_html                = render_screen();
$GLOBALS['open_requests'] = $before_details;
unset( $GLOBALS['request_facts'][805] );

ck( 'a details request is labeled Details to correct, among the others by its age: the newest of them last', array_column( queue_items( $details_html ), 1 ), array( 'Mentor request', 'Application', 'Mentor request', 'Application', 'Signed agreement', 'Application', 'Details to correct' ) );
ck( 'it is counted with the rest, and says what it asks and who asked, and no student', array(
	preg_match( '#<h2 id="wpcpm-queue">Waiting for review <span class="wpcpm-count">7</span></h2>#', $details_html ),
	false !== $details_pos && false !== strpos( $details_rows[ $details_pos ], '<p>A change to its details</p><p>Raised by Rep One.</p>' ),
	false !== $details_pos && false !== strpos( $details_rows[ $details_pos ], 'student' ),
), array( 1, true, false ) );
ck( 'its row links to the card that decides it, as a request\'s does, and an account that is gone is said so', array(
	false !== $details_pos ? array_column( queue_links( $details_html ), 2 )[ $details_pos ] : null,
	false !== strpos( $gone_html, '<p>A change to its details</p><p>Raised by somebody whose account is gone.</p>' ),
), array( $dashboard . '#wpcpm-requests', true ) );
ck( 'and nothing on the list decides it: no form, and none of its decisions drawn', array( substr_count( $details_html, '<form' ), $GLOBALS['request_decisions'] ), array( 0, array() ) );

// The queue reads and the Administrator Dashboard decides: every row ends with the way to its place
// there, an application to its own item on the card that decides it, which carries the
// application's id, a request to the card that decides it, and the agreement row's block, drawn by
// the panel, carries its own.
ck( 'every row links to the Administrator Dashboard, an application to its own item there and a request to its card, and the agreement row leaves its link to the panel', queue_links( $html ), array(
	array( $request_names[0], 'Mentor request', $dashboard . '#wpcpm-requests' ),
	array( 'Universidad Example', 'Application', $dashboard . '#wpcpm-application-501' ),
	array( $request_names[1], 'Mentor request', $dashboard . '#wpcpm-requests' ),
	array( 'Universidad EXAMPLE', 'Application', $dashboard . '#wpcpm-application-502' ),
	array( $record_name, 'Signed agreement', '' ),
	array( 'Escola Nova', 'Application', $dashboard . '#wpcpm-application-503' ),
) );
// One printer draws that line on every wp-admin screen that links into the dashboard, the Sponsors
// screen's too, on the class that owns the dashboard's card ids; this screen keeps none of its own.
ck( 'the way there is the printer every wp-admin screen shares, and this screen keeps no printer of its own', array(
	method_exists( 'WPCPM_Institutions', 'render_open_on_dashboard' ),
	substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' ), 'WPCPM_Return::render_dashboard_link(' ) > 0,
), array( false, true ) );
ck( 'an agreement row hands the review block to the panel that owns it, to be read: its two decisions are the dashboard\'s', array( $html_reviews, false !== strpos( $html, '<div class="wpcpm-agreement-review" data-post="601" data-decide="no"></div>' ) ), array( array( array( 601, false ) ), true ) );
ck( 'an application row still opens itself here, for what only this screen shows', false !== strpos( $html, WPCPM_Institutions::ARG_APPLICATION . '=501">Open this application' ), true );
ck( 'and nothing on the list decides anything: no form, and a request\'s decisions never asked for', array( substr_count( $html, '<form' ), $GLOBALS['request_decisions'] ), array( 0, array() ) );

// While the dashboard's page is missing there is nowhere to send a row: the dashboard class's own
// sentence, once, above the rows, and no row links anywhere. The agreement row's block prints
// nothing in its link's place then, which the panel's suite pins.
WPCPM_Administrators_Dashboard::$url = '';
$GLOBALS['reviews']                  = array();
$no_page                             = render_screen();
WPCPM_Administrators_Dashboard::$url = $dashboard;
$warned_at                           = strpos( $no_page, '<p class="wpcpm-warning">The dashboard class says its page is missing.</p>' );
ck( 'while the Administrator Dashboard page is missing the list says so once, above the rows, and no row links there', array(
	substr_count( $no_page, 'The dashboard class says its page is missing.' ),
	false !== $warned_at && $warned_at < (int) strpos( $no_page, '<ol class="wpcpm-queue">' ),
	substr_count( $no_page, 'Open on the Administrator Dashboard' ),
	count( queue_items( $no_page ) ),
	$GLOBALS['reviews'],
), array( 1, true, 0, 6, array( array( 601, false ) ) ) );

ck( 'the country and its person of contact are printed for information', false !== strpos( $html, esc_html( $routed_name . '. Person of contact: A Manager, for information.' ) ), true );
ck( 'a row whose country routes nowhere says so rather than printing nothing', false !== strpos( render_screen(), 'The Countries table names nobody for it, for information.' ), true );

ck( 'the two that name the same institution are flagged as possible duplicates', substr_count( $html, 'possible duplicate' ), 2 );
ck( 'drawing the list asks Airtable nothing', array_filter( $GLOBALS['calls'], function ( $c ) { return 'fetch_page' === $c[0]; } ), array() );

// The list is what a manager triages from, and the decisions are a click away on the
// Administrator Dashboard, whose card does not print the checks. A row the site quietly decided
// was suspect must therefore say so on the list, or the manager rejecting it is acting on an
// opinion nobody showed them.
ck( 'the held row says on the list that it is held', queue_marked( $html, 'wpcpm-inst-mark--held' ), array(
	$request_names[0]     => false,
	'Universidad Example' => false,
	$request_names[1]     => false,
	'Universidad EXAMPLE' => true,
	$record_name          => false,
	'Escola Nova'         => false,
) );
ck( 'and says how many checks held it rather than making somebody open it to find out', false !== strpos( $html, '>held</span> 3 checks held it; open the application to read them.' ), true );

/* ---- which applications are flagged as duplicates ----------------------- */

echo "\n=== Which applications are flagged as duplicates ===\n";

// Its own store, so the three ways a row gets flagged can be seen apart from each other. The
// queue above pins the name branch; these are the other two, and the address one is what the
// design's threat model rests on: a stranger applying first with an institution's published
// address must be flagged for a person, and never merged into the genuine submission.
$queue_posts              = $GLOBALS['posts'];
$queue_pmeta              = $GLOBALS['pmeta'];
$queue_requests           = $GLOBALS['open_requests'];
$GLOBALS['posts']         = array();
$GLOBALS['pmeta']         = array();
$GLOBALS['awaiting']      = array();
$GLOBALS['open_requests'] = array();

// The address is stored as `wp_hash()` of the lowercased one and never as the address, so the
// equal hashes here are the whole of what the queue can compare. The names differ on purpose.
seed_application( 521, 'Colegio Uno', WPCPM_Institution_Application::STATE_NEW, $now - ( 6 * $day ), array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-shared' ) );
seed_application( 522, 'Instituto Dos', WPCPM_Institution_Application::STATE_NEW, $now - ( 5 * $day ), array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-shared' ) );
// Flagged by the form's own signal, which is how a duplicate of something that has already
// left the queue is still flagged after its twin has gone.
seed_application( 523, 'Escuela Tres', WPCPM_Institution_Application::STATE_NEW, $now - ( 4 * $day ), array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-tres', WPCPM_Institution_Application::META_SIGNALS => array( 'duplicate' ) ) );
seed_application( 524, 'Liceo Cuatro', WPCPM_Institution_Application::STATE_NEW, $now - ( 3 * $day ), array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-cuatro' ) );

$flagged = render_screen();
ck( 'one address under two names flags both, the form\'s own signal flags a third, and a row that matches nothing is left alone', queue_marked( $flagged, 'possible duplicate' ), array(
	'Colegio Uno'   => true,
	'Instituto Dos' => true,
	'Escuela Tres'  => true,
	'Liceo Cuatro'  => false,
) );
ck( 'and nothing is merged: every row still stands and every row is still listed', count( queue_items( $flagged ) ), 4 );

$GLOBALS['posts']         = $queue_posts;
$GLOBALS['pmeta']         = $queue_pmeta;
$GLOBALS['awaiting']      = array( 601 );
$GLOBALS['open_requests'] = $queue_requests;

/* ---- the menu bubble ---------------------------------------------------- */

echo "\n=== The menu bubble ===\n";

$GLOBALS['requests_asked'] = array();
ck( 'the menu title carries the pending count, every row the queue lists', $module->menu_label(), 'Institutions <span class="awaiting-mod count-6"><span class="pending-count">6</span></span>' );
ck( 'and the page heading stays plain', $module->label(), 'Institutions' );
// One more than the bubble shows, the question its other two reads ask as well.
ck( 'the mentor requests are asked under the bubble\'s own ceiling', $GLOBALS['requests_asked'], array( WPCPM_Institutions::COUNT_MAX + 1 ) );

$GLOBALS['awaiting'] = array();
ck( 'the count is applications plus agreements plus mentor requests', $module->menu_label(), 'Institutions <span class="awaiting-mod count-5"><span class="pending-count">5</span></span>' );

$GLOBALS['open_requests'] = array();
ck( 'and every mentor request is one of them', $module->menu_label(), 'Institutions <span class="awaiting-mod count-3"><span class="pending-count">3</span></span>' );

$held = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
ck( 'an empty queue hangs nothing on the menu', $module->menu_label(), 'Institutions' );
$GLOBALS['posts']         = $held;
$GLOBALS['awaiting']      = array( 601 );
$GLOBALS['open_requests'] = $queue_requests;

/* ---- a flood ------------------------------------------------------------ */

echo "\n=== A flood ===\n";

// The form is open to strangers, so how many rows are waiting is not this site's decision.
// Two hundred and ten of them, all older than the fixture's three, is a bad afternoon.
$before_flood_posts = $GLOBALS['posts'];
$before_flood_pmeta = $GLOBALS['pmeta'];

for ( $i = 1; $i <= 210; $i++ ) {
	seed_application( 700 + $i, sprintf( 'Flood %d', $i ), WPCPM_Institution_Application::STATE_NEW, $now - ( 20 * $day ) + $i, array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-flood-' . $i ) );
}

$GLOBALS['loaded'] = 0;
$flood             = render_screen();

ck( 'the card draws its ceiling and no more, however many are waiting', count( queue_items( $flood ) ), WPCPM_Institutions::QUEUE_MAX );
// And it pays for the rows it draws, not for the rows that are waiting. The queue used to
// ask for every open application as a WP_Post to count them and then keep fifty, which on
// this afternoon is two hundred and thirteen applicants' whole submitted forms read into the
// meta cache to print one number (deep check FADMN-2, whose Administrator Dashboard half
// shipped with it; the open states are the ones a stranger can fill and nothing purges them).
ck( 'and it builds a post object for the rows it draws, never for the rows it counts', $GLOBALS['loaded'], WPCPM_Institutions::QUEUE_MAX );
ck( 'the oldest are the ones it draws, so the row whose turn it is cannot fall off the end', queue_items( $flood )[0][0], 'Flood 1' );
ck( 'it counts what is waiting and not what it drew', preg_match( '#<h2 id="wpcpm-queue">Waiting for review <span class="wpcpm-count">216</span></h2>#', $flood ), 1 );
ck( 'and says out loud that the list is part of the queue, and where the rows that take their place come from', false !== strpos( $flood, 'Showing the oldest 50 of 216. The list stops there so that a burst of applications cannot make this screen too slow to open; as these are decided on the Administrator Dashboard, the next of them take their place.' ), true );
// The bubble is drawn on every admin page in the site, not only on this screen, which is why
// it stops counting rather than paying for a flood on all of them.
ck( 'the bubble stops at its ceiling and says so', $module->menu_label(), 'Institutions <span class="awaiting-mod count-200"><span class="pending-count">200+</span></span>' );

$GLOBALS['posts'] = $before_flood_posts;
$GLOBALS['pmeta'] = $before_flood_pmeta;

ck( 'both ceilings are ceilings and not page sizes: the ordinary queue is drawn whole and counted exactly', array( $module->menu_label(), count( queue_items( render_screen() ) ), false !== strpos( render_screen(), 'Showing the oldest' ) ), array( 'Institutions <span class="awaiting-mod count-6"><span class="pending-count">6</span></span>', 6, false ) );

// The requests half pays for the rows that can reach the window and no more, as the applications
// half does: sixty open requests older than everything else, and the facts built are the oldest
// fifty's, every row that can reach the top of a list cut at fifty. The window is the oldest of the
// three kinds together, so here it holds requests alone.
$before_requests = $GLOBALS['open_requests'];

for ( $i = 1; $i <= 60; $i++ ) {
	seed_request( 900 + $i, $request_records[0], sprintf( 'Student %d', $i ), 'Rep One', '', $now - ( 30 * $day ) + $i );
}

$GLOBALS['open_requests'] = array_merge( range( 901, 960 ), $before_requests );
$GLOBALS['facts_built']   = 0;
$many_requests            = render_screen();

ck( 'sixty open requests cost fifty rows\' facts, and the window is the oldest of the three kinds together', array(
	$GLOBALS['facts_built'],
	count( queue_items( $many_requests ) ),
	array_values( array_unique( array_column( queue_items( $many_requests ), 1 ) ) ),
	false !== strpos( $many_requests, 'Showing the oldest 50 of 66.' ),
), array( WPCPM_Institutions::QUEUE_MAX, WPCPM_Institutions::QUEUE_MAX, array( 'Mentor request' ), true ) );

foreach ( range( 901, 960 ) as $request_id ) {
	unset( $GLOBALS['request_facts'][ $request_id ] );
}

$GLOBALS['open_requests'] = $before_requests;

/* ---- one application, open ---------------------------------------------- */

echo "\n=== One application, open ===\n";

$GLOBALS['calls']         = array();
$GLOBALS['airtable_page'] = array( 'records' => array(), 'offset' => null );
$open                     = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) );

$asked = array();
$rowed = array();

foreach ( WPCPM_Institution_Application::fields() as $column => $spec ) {
	// The consent under the sentence its applicant agreed to, which the record holds: this one was
	// filed before 1.123.24, when the form asked other words. An answer kept on the site has no
	// column, so its heading is the question alone.
	$heading = 'Privacy Policy Compliance' === $column ? $consent['sentence'] : $spec['label'];
	$asked[] = false !== strpos( $open, empty( $spec['site'] )
		? '<th scope="row">' . esc_html( $heading ) . '<br /><code class="wpcpm-inst-record">' . esc_html( $column ) . '</code></th>'
		: '<tr><th scope="row">' . esc_html( $heading ) . '</th>' );
	$rowed[] = empty( $spec['site'] ) ? esc_html( $heading ) . '<br /><code class="wpcpm-inst-record">' . esc_html( $column ) . '</code>' : esc_html( $heading );
}

/** The answers table of a drawn application, row by row: each heading cell and its answer. */
$answer_rows = static function ( $html ) {
	if ( ! preg_match( '#<table class="widefat striped wpcpm-list wpcpm-app-answers"><tbody>(.*?)</tbody></table>#s', $html, $table ) ) {
		return array();
	}
	preg_match_all( '#<tr><th scope="row">(.*?)</th><td>(.*?)</td></tr>#s', $table[1], $found, PREG_SET_ORDER );
	return array_map( static function ( $row ) { return array( $row[1], $row[2] ); }, $found );
};
$open_rows = $answer_rows( $open );

ck( 'all eighteen questions are shown, the consent under the sentence its applicant agreed to', $asked, array_fill( 0, 18, true ) );
ck( 'in eighteen rows, in the order the form asks them, the five kept on the site with no column name under the question', array_column( $open_rows, 0 ), $rowed );
ck( 'so the table names thirteen columns, and none of the five keys is printed anywhere in it', array(
	substr_count( implode( '', array_column( $open_rows, 0 ) ), '<code class="wpcpm-inst-record">' ),
	array_values( array_filter( array( 'contact_role', 'student_languages', 'signer_name', 'signer_title', 'credits_page' ), static function ( $key ) use ( $open_rows ) { return false !== strpos( implode( '', array_merge( array_column( $open_rows, 0 ), array_column( $open_rows, 1 ) ) ), $key ); } ) ),
), array( 13, array() ) );
ck( 'the five are answered from the site\'s own key, the languages joined as the internship choices are, and escaped', array_slice( array_column( $open_rows, 1 ), 0, 17 ), array(
	'Universidad Example',
	esc_html( $routed_name ),
	'Cartago',
	'universidad.example',
	'Ana Example',
	'ana@example.test',
	'Internship coordinator',
	'Computer Science',
	' Credit-bearing internships, Final projects',
	'&lt;script&gt;alert(1)&lt;/script&gt;',
	'25',
	'Spanish, Portuguese',
	'Signer Example',
	'&lt;em&gt;Dean&lt;/em&gt; of the Faculty of Engineering',
	'https://universidad.example/wordpress-credits',
	'Our students need real projects.',
	'<span class="wpcpm-inst-muted">no answer</span>',
) );
ck( 'the answers are escaped, prose from a stranger being what they are', array(
	false !== strpos( $open, '&lt;script&gt;alert(1)&lt;/script&gt;' ),
	false === strpos( $open, '<script>alert(1)</script>' ),
), array( true, true ) );
ck( 'the multi-select answer keeps the leading space the base has', false !== strpos( $open, '<td> Credit-bearing internships, Final projects</td>' ), true );
ck( 'an unanswered question says so rather than printing an empty cell', substr_count( $open, 'no answer' ), 1 );

ck( 'the consent sentence prints with its timestamp and the policy it was given against', array(
	false !== strpos( $open, 'Agreed ' . gmdate( 'Y-m-d H:i', $now - ( 10 * $day ) ) ),
	false !== strpos( $open, 'I confirm this institution complies with its privacy policy.' ),
	false !== strpos( $open, 'Policy: https://example.test/privacy/' ),
	false !== strpos( $open, 'The policy was last changed 2026-08-01 09:00' ),
), array( true, true, true, true ) );
// One filed since 1.123.24 names WordPress.org's policy with no page and no modified time: its row
// says the address and no "last changed" sentence, under the words its applicant read.
$consent_row = static function ( $html ) {
	return preg_match( '#<tr><th scope="row">([^<]*)<br /><code class="wpcpm-inst-record">Privacy Policy Compliance</code></th><td>([^<]*)</td></tr>#', $html, $found ) ? array( $found[1], $found[2] ) : null;
};
$since_sentence = WPCPM_Institution_Application::fields()['Privacy Policy Compliance']['label'];
update_post_meta( 501, WPCPM_Institution_Application::META_CONSENT, array_merge( $consent, array( 'sentence' => $since_sentence, 'url' => 'https://wordpress.org/about/privacy/', 'policy' => 0, 'modified' => '' ) ) );
$open_since = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) );
update_post_meta( 501, WPCPM_Institution_Application::META_CONSENT, $consent );
ck( 'an application filed before 1.123.24 reads as it did: the sentence agreed to, the site\'s page and its version', $consent_row( $open ), array( 'I confirm this institution complies with its privacy policy.', 'Agreed ' . gmdate( 'Y-m-d H:i', $now - ( 10 * $day ) ) . '. I confirm this institution complies with its privacy policy. Policy: https://example.test/privacy/ The policy was last changed 2026-08-01 09:00; anything later than the agreement above is not what they read.' ) );
ck( 'one filed since names WordPress.org\'s policy and says nothing of a change it cannot know', $consent_row( $open_since ), array( esc_html( $since_sentence ), 'Agreed ' . gmdate( 'Y-m-d H:i', $now - ( 10 * $day ) ) . '. ' . esc_html( $since_sentence ) . ' Policy: https://wordpress.org/about/privacy/' ) );
ck( 'the verification state is its own line', false !== strpos( $open, 'The applicant confirmed their address on ' . gmdate( 'Y-m-d H:i', $now - ( 9 * $day ) ) ), true );

$formula = '';

foreach ( $GLOBALS['calls'] as $call ) {
	if ( 'fetch_page' === $call[0] ) { $formula = $call[2]; }
}

ck( 'opening one asks the base about the trimmed name and the lowered address', $formula, "OR(TRIM(LOWER({Name})) = 'universidad example', LOWER({Contact Email}) = 'ana@example.test')" );

// An application filed before 1.123.29 holds no site answers: its five rows are drawn all the same,
// unanswered as any question left empty is, with no column name under them.
$before_rows = $answer_rows( render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 503 ) ) );
$before_site = array();

foreach ( array_keys( WPCPM_Institution_Application::fields() ) as $index => $column ) {
	if ( ! empty( WPCPM_Institution_Application::fields()[ $column ]['site'] ) ) {
		$before_site[] = $before_rows[ $index ] ?? null;
	}
}

ck( 'one filed before the five were asked draws their rows unanswered, with no column name', array( count( $before_rows ), $before_site ), array( 18, array(
	array( 'Their role at the institution', '<span class="wpcpm-inst-muted">no answer</span>' ),
	array( 'In which languages do your students study?', '<span class="wpcpm-inst-muted">no answer</span>' ),
	array( 'Who will sign the agreement for the institution?', '<span class="wpcpm-inst-muted">no answer</span>' ),
	array( 'Their title', '<span class="wpcpm-inst-muted">no answer</span>' ),
	array( 'Your page about WordPress Credits', '<span class="wpcpm-inst-muted">no answer</span>' ),
) ) );

// A view of the queue tab, reached by its query argument: drawn in place of the list rather than
// above it, with the way back to the list.
$open_bar = bar_of( $open );
ck( 'the application is drawn in place of the list, on the queue tab', array(
	cards_of( $open ),
	strpos( $open, '<h2 id="wpcpm-queue">' ),
	strpos( $open, 'wpcpm-queue-item' ),
	null === $open_bar ? null : $open_bar[1][0][2],
), array( array( 'Universidad Example APP-2026-0007' ), false, false, true ) );
ck( 'and its way back is the queue tab', false !== strpos( $open, '<a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=queue">Back to the queue</a>' ), true );
ck( 'the queue tab named in the address opens it the same, and another tab draws itself and no application', array(
	render_tab( 'queue', array( WPCPM_Institutions::ARG_APPLICATION => 501 ) ) === $open,
	cards_of( render_tab( 'pipeline', array( WPCPM_Institutions::ARG_APPLICATION => 501 ) ) ),
), array( true, array( 'Pipeline', 'Consent' ) ) );
ck( 'and says so when it finds nothing', false !== strpos( $open, 'No Institutions record carries this name or this address.' ), true );

$GLOBALS['airtable_page'] = array(
	'records' => array( array( 'id' => 'recSEED0000000008', 'fields' => array( 'Name' => 'Universidad Example ', 'Current Stage' => 'Under Review' ) ) ),
	'offset'  => null,
);
$matched = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) );
ck( 'a hit is listed with its record ID and stage, and nothing is merged', array(
	false !== strpos( $matched, 'recSEED0000000008' ),
	false !== strpos( $matched, 'Under Review' ),
	false !== strpos( $matched, 'Approving adopts the first of them rather than creating a second' ),
), array( true, true, true ) );

$GLOBALS['airtable_page'] = new WP_Error( 'wpcpm_http', 'Airtable did not answer.' );
ck( 'a search that could not be made says so and shows the application anyway', array(
	false !== strpos( render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) ), 'The search could not be made: Airtable did not answer.' ),
	false !== strpos( render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) ), 'APP-2026-0007' ),
), array( true, true ) );
$GLOBALS['airtable_page'] = array( 'records' => array(), 'offset' => null );

/**
 * How many forms of each of the six decisions a render draws for one application, counted by the
 * nonce keyed to the decision and the application together.
 *
 * @param string $html What a render printed.
 * @param int    $id   Application post ID.
 * @return array<string, int>
 */
function decisions_on( $html, $id ) {
	$out = array();
	foreach ( array( 'approve', 'info', 'reject', 'spam', 'reopen', 'purge' ) as $verb ) {
		$out[ $verb ] = substr_count( (string) $html, 'name="_wpnonce" value="nonce-wpcpm_app_' . $verb . '_' . (int) $id . '"' );
	}
	return $out;
}

// Read here and decided on the Administrator Dashboard: an opened open application the card there
// lists draws none of the decisions, says where they are made, and links to that card; one past the
// card's window is decided here. The record-keeping on a closed one stays here: Put back in the
// queue and Delete for good on a rejected or spam application, which the dashboard folds in only the
// oldest fifty of, and Delete for good alone on an approved one, which it never lists.
$no_decision = array( 'approve' => 0, 'info' => 0, 'reject' => 0, 'spam' => 0, 'reopen' => 0, 'purge' => 0 );
$open_four   = array( 'approve' => 1, 'info' => 1, 'reject' => 1, 'spam' => 1, 'reopen' => 0, 'purge' => 0 );
$to_card     = '<a href="' . $dashboard . '#wpcpm-applications">Open on the Administrator Dashboard</a>';
// An application the card lists is an item of it with an id of its own, and the link names it.
$to_item     = static function ( $id ) use ( $dashboard ) {
	return '<a href="' . $dashboard . '#wpcpm-application-' . (int) $id . '">Open on the Administrator Dashboard</a>';
};
$listed      = '<p>Applications are decided on the Administrator Dashboard. This one is listed in its Institution applications card, with every decision its state allows.</p>';
$past_window = '<p>The Administrator Dashboard&#039;s card lists the ' . WPCPM_Administrators_Cards::LIMIT . ' oldest open applications, and this one is past them, so it is decided here.</p>';

ck( 'a new application opened here draws no decision, and no form at all', array( decisions_on( $open, 501 ), substr_count( $open, '<form' ) ), array( $no_decision, 0 ) );
ck( 'it says where it is decided, that the card there lists it, and links to its own item on that card', array(
	false !== strpos( $open, '<h3>Where it is decided</h3>' ),
	strpos( $open, 'What happens next' ),
	false !== strpos( $open, $listed ),
	substr_count( $open, $to_item( 501 ) ),
	substr_count( $open, 'Open on the Administrator Dashboard' ),
), array( true, false, true, 1, 1 ) );

$decided = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 503 ) );
ck( 'one waiting on the applicant draws none either, and links to its own item the same way', array( decisions_on( $decided, 503 ), substr_count( $decided, $to_item( 503 ) ) ), array( $no_decision, 1 ) );

// The card lists the oldest `WPCPM_Administrators_Cards::LIMIT` open applications, so one opened
// from its address while that many older ones wait is on no card at all: it is decided here, with
// the four decisions the card would have drawn, under the sentence that says why. One fewer older
// one leaves it the last the card lists, read here and linked there as before. The window is the
// card's number and not the queue's `QUEUE_MAX`, which the stand-in keeps apart from it.
for ( $i = 1; $i < WPCPM_Administrators_Cards::LIMIT; $i++ ) {
	seed_application( 1100 + $i, sprintf( 'Older %d', $i ), WPCPM_Institution_Application::STATE_NEW, $now - ( 60 * $day ) + $i, array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-older-' . $i ) );
}

$last_listed = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) );

seed_application( 1100 + WPCPM_Administrators_Cards::LIMIT, 'Older last', WPCPM_Institution_Application::STATE_NEW, $now - ( 60 * $day ), array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-older-last' ) );

$first_past = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) );
$held_past  = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 502 ) );
$info_past  = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 503 ) );

WPCPM_Administrators_Dashboard::$url = '';
$past_no_page                        = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) );
WPCPM_Administrators_Dashboard::$url = $dashboard;

ck( 'the last application the card lists is read here and linked to its item there, with no form', array(
	false !== strpos( $last_listed, $listed ),
	decisions_on( $last_listed, 501 ),
	substr_count( $last_listed, '<form' ),
	substr_count( $last_listed, $to_item( 501 ) ),
	strpos( $last_listed, 'so it is decided here' ),
), array( true, $no_decision, 0, 1, false ) );
ck( 'the first one past it is decided here: Approve, Send this question, Reject and Reject as spam, under the sentence that says why, and no way to a card that does not list it', array(
	false !== strpos( $first_past, '<h3>Where it is decided</h3>' . $past_window ),
	decisions_on( $first_past, 501 ),
	substr_count( $first_past, '<form' ),
	substr_count( $first_past, 'Open on the Administrator Dashboard' ),
	strpos( $first_past, 'This one is listed' ),
	strpos( $first_past, 'What happens next' ),
), array( true, $open_four, 4, 0, false, false ) );
ck( 'and so is a held one and one waiting on the applicant, each with the same four', array(
	decisions_on( $held_past, 502 ),
	decisions_on( $info_past, 503 ),
	substr_count( $held_past . $info_past, $past_window ),
	substr_count( $held_past . $info_past, 'Open on the Administrator Dashboard' ),
), array( $open_four, $open_four, 2, 0 ) );
ck( 'the forms are the ones the card draws, each posting no return and no tab, so the press comes back to the queue', array(
	form_fields( $first_past, 'wpcpm_app_approve' ),
	form_fields( $first_past, 'wpcpm_app_info' ),
	form_fields( $first_past, 'wpcpm_app_reject' ),
	form_fields( $first_past, 'wpcpm_app_spam' ),
	false !== strpos( $first_past, ' data-wpcpm-confirm="' . esc_attr( 'Create an Airtable record and a site account for Universidad Example, and email a password-set link to ana@example.test? The Airtable record cannot be removed from here.' ) . '"' ),
	false !== strpos( $first_past, 'name="wpcpm_question"' ) && false !== strpos( $first_past, 'name="wpcpm_reason"' ),
), array(
	array( 'action' => 'wpcpm_app_approve', 'wpcpm_application' => '501' ),
	array( 'action' => 'wpcpm_app_info', 'wpcpm_application' => '501' ),
	array( 'action' => 'wpcpm_app_reject', 'wpcpm_application' => '501' ),
	array( 'action' => 'wpcpm_app_spam', 'wpcpm_application' => '501' ),
	true,
	true,
) );
ck( 'the Reject box names the program\'s Administrators, in the words of the sponsor application\'s Reject box', array(
	false !== strpos( $first_past, '>Why, for the next Administrator who reads this. It is never sent to the applicant.<' ),
	strpos( $first_past, 'next manager' ),
), array( true, false ) );
ck( 'while the dashboard page is missing one past the window keeps its four decisions, and nothing about the page', array(
	decisions_on( $past_no_page, 501 ),
	false !== strpos( $past_no_page, $past_window ),
	strpos( $past_no_page, 'The dashboard class says its page is missing.' ),
	substr_count( $past_no_page, 'Open on the Administrator Dashboard' ),
), array( $open_four, true, false, 0 ) );

// Each of the four, pressed from the form this view drew, comes back to the screen's own address,
// which is the queue, and flashes an outcome the queue's own map words. What each press wrote on the
// application, its state and its history, is put back after it, and the question's and the
// rejection's mail is the handlers' own business, checked further down.
$landed         = array();
$meta_before    = $GLOBALS['pmeta'][501];
$mailed_before  = $GLOBALS['mail'] ?? array();
$approved_saved = $GLOBALS['approved'] ?? array();
$posted_words   = array(
	'wpcpm_app_approve' => array( 'handle_approve', array() ),
	'wpcpm_app_info'    => array( 'handle_info', array( 'wpcpm_question' => 'Which department would run the internships?' ) ),
	'wpcpm_app_reject'  => array( 'handle_reject', array( 'wpcpm_reason' => 'Not a teaching institution.' ) ),
	'wpcpm_app_spam'    => array( 'handle_spam', array() ),
);

foreach ( $posted_words as $form_action => $press ) {
	$_POST                  = array_merge( form_fields( $first_past, $form_action ), $press[1] );
	$went                   = outcome( array( $module, $press[0] ) );
	$flashed                = get_user_meta( 1, WPCPM_Flash::META );
	$landed[ $form_action ] = array(
		$went,
		$GLOBALS['referer'],
		isset( $flashed['institutions'] ) && isset( WPCPM_Institutions::queue_messages()[ $flashed['institutions'] ] ),
	);
	delete_user_meta( 1, WPCPM_Flash::META );
	$GLOBALS['pmeta'][501] = $meta_before;
}

$GLOBALS['mail']     = $mailed_before;
$GLOBALS['approved'] = $approved_saved;
$_POST               = array();

ck( 'each of the four lands on the queue with an outcome the queue words, its nonce keyed to the decision and the application', $landed, array(
	'wpcpm_app_approve' => array( $back, array( 'wpcpm_app_approve_501' ), true ),
	'wpcpm_app_info'    => array( $back, array( 'wpcpm_app_info_501' ), true ),
	'wpcpm_app_reject'  => array( $back, array( 'wpcpm_app_reject_501' ), true ),
	'wpcpm_app_spam'    => array( $back, array( 'wpcpm_app_spam_501' ), true ),
) );

// And the outcome prints on the queue, above the bar, as every decision's does. Read as a manager
// of its own, because `WPCPM_Flash::take()` memoizes per person and per channel.
$GLOBALS['uid'] = 66;
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'app-spam' );
$spam_notice    = render_screen();
$GLOBALS['uid'] = 1;
$spam_at        = strpos( $spam_notice, 'The application is marked as spam. Nothing was sent' );
ck( 'and the queue prints that outcome above the bar', array(
	false !== $spam_at && $spam_at < (int) strpos( $spam_notice, '<nav class="nav-tab-wrapper' ),
	cards_of( $spam_notice ),
), array( true, array( 'Waiting for review' ) ) );
delete_user_meta( 66, WPCPM_Flash::META );

for ( $i = 1; $i <= WPCPM_Administrators_Cards::LIMIT; $i++ ) {
	wp_delete_post( 1100 + $i, true );
}

$GLOBALS['deleted'] = array();

seed_application( 530, 'Aprobada Example', WPCPM_Institution_Application::STATE_APPROVED, $now - ( 40 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0030' ) );
seed_application( 531, 'Spam Example', WPCPM_Institution_Application::STATE_SPAM, $now - ( 3 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0031' ) );
seed_application( 532, 'Rechazada Example', WPCPM_Institution_Application::STATE_REJECTED, $now - ( 3 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0032' ) );
// A state no decision writes, which no list holds: the screen says where applications are decided
// and claims no place on the card for it.
seed_application( 533, 'Sin Estado Example', '', $now - ( 3 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0033' ) );

$approved  = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 530 ) );
$spammed   = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 531 ) );
$rejected  = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 532 ) );
$stateless = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 533 ) );
$kept_here = array_merge( $no_decision, array( 'reopen' => 1, 'purge' => 1 ) );

ck( 'a rejected or spam one keeps Put back in the queue and Delete for good here, exactly as they were, says why, and links nowhere', array(
	decisions_on( $spammed, 531 ),
	decisions_on( $rejected, 532 ),
	form_fields( $spammed, 'wpcpm_app_reopen' ),
	form_fields( $rejected, 'wpcpm_app_purge' ),
	substr_count( $spammed . $rejected, '<p>Putting it back in the queue and deleting it for good are done here: the Administrator Dashboard folds only the oldest 50 rejected or spam applications into its Institution applications card.</p>' ),
	substr_count( $spammed . $rejected, 'Open on the Administrator Dashboard' ),
), array(
	$kept_here,
	$kept_here,
	array( 'action' => 'wpcpm_app_reopen', 'wpcpm_application' => '531' ),
	array( 'action' => 'wpcpm_app_purge', 'wpcpm_application' => '532' ),
	2,
	0,
) );
ck( 'an application in a state no decision writes is pointed at the dashboard with no claim about the card', array(
	false !== strpos( $stateless, '<p>Applications are decided on the Administrator Dashboard.</p>' ),
	decisions_on( $stateless, 533 ),
	substr_count( $stateless, $to_card ),
), array( true, $no_decision, 1 ) );
ck( 'an approved one keeps Delete for good, exactly as it was, and nothing else', array(
	decisions_on( $approved, 530 ),
	form_fields( $approved, 'wpcpm_app_purge' ),
	false !== strpos( $approved, ' data-wpcpm-confirm="' . esc_attr( 'Delete the application from Aprobada Example for good? Every answer on it goes; only its reference and the date are kept. This cannot be undone.' ) . '"' ),
	substr_count( $approved, '<form' ),
), array( array_merge( $no_decision, array( 'purge' => 1 ) ), array( 'action' => 'wpcpm_app_purge', 'wpcpm_application' => '530' ), true, 1 ) );
ck( 'and says why that one control stays here, with no way to a card that does not list it', array(
	false !== strpos( $approved, '<p>It is approved, so nothing is left to decide on it, and the Administrator Dashboard, where applications are decided, does not list it. Deleting it for good is record-keeping rather than a decision, so it is done here.</p>' ),
	substr_count( $approved, 'Open on the Administrator Dashboard' ),
), array( true, 0 ) );

WPCPM_Administrators_Dashboard::$url = '';
$open_no_page                        = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 501 ) );
$closed_no_page                      = array(
	530 => render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 530 ) ),
	531 => render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 531 ) ),
	532 => render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 532 ) ),
);
WPCPM_Administrators_Dashboard::$url = $dashboard;
ck( 'while the dashboard page is missing an open application says so in the link\'s place, and a closed one draws its forms and nothing about the page', array(
	false !== strpos( $open_no_page, '<p>Applications are decided on the Administrator Dashboard.</p><p class="wpcpm-warning">The dashboard class says its page is missing.</p>' ),
	strpos( $open_no_page, 'This one is listed' ),
	substr_count( $open_no_page, 'Open on the Administrator Dashboard' ),
	substr_count( implode( '', $closed_no_page ), 'The dashboard class says its page is missing.' ),
	substr_count( implode( '', $closed_no_page ), 'Open on the Administrator Dashboard' ),
	array( decisions_on( $closed_no_page[530], 530 ), decisions_on( $closed_no_page[531], 531 ), decisions_on( $closed_no_page[532], 532 ) ),
), array( true, false, 0, 0, 0, array( array_merge( $no_decision, array( 'purge' => 1 ) ), $kept_here, $kept_here ) ) );

// The Administrator Dashboard draws the same method with its return and gets every form as it
// did, under the heading it had, each with the fields that bring the press back to its card.
$on_dashboard = array();

foreach ( array( 501 => WPCPM_Institution_Application::STATE_NEW, 503 => WPCPM_Institution_Application::STATE_INFO, 532 => WPCPM_Institution_Application::STATE_REJECTED ) as $application_id => $application_state ) {
	ob_start();
	$module->render_application_actions( get_post( $application_id ), $application_state, WPCPM_Return::DASHBOARD );
	$on_dashboard[ $application_id ] = (string) ob_get_clean();
}

ck( 'on the Administrator Dashboard the same method draws every decision the state allows, as before', array(
	false !== strpos( $on_dashboard[501], '<h3>What happens next</h3>' ),
	decisions_on( $on_dashboard[501], 501 ),
	decisions_on( $on_dashboard[503], 503 ),
	decisions_on( $on_dashboard[532], 532 ),
	strpos( implode( '', $on_dashboard ), 'Open on the Administrator Dashboard' ),
), array(
	true,
	array( 'approve' => 1, 'info' => 1, 'reject' => 1, 'spam' => 1, 'reopen' => 0, 'purge' => 0 ),
	array( 'approve' => 1, 'info' => 1, 'reject' => 1, 'spam' => 1, 'reopen' => 1, 'purge' => 0 ),
	array( 'approve' => 0, 'info' => 0, 'reject' => 0, 'spam' => 0, 'reopen' => 1, 'purge' => 1 ),
	false,
) );
ck( 'each with the fields that bring the press back to the dashboard\'s applications card', form_fields( $on_dashboard[501], 'wpcpm_app_approve' ), array( 'action' => 'wpcpm_app_approve', 'wpcpm_application' => '501', 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'applications' ) );

// The dashboard's return alone gets an open application's decisions: anything else is this
// screen, the way `WPCPM_Return::field()` reads it, the wp-admin return among them.
ob_start();
$module->render_application_actions( get_post( 501 ), WPCPM_Institution_Application::STATE_NEW, WPCPM_Return::ADMIN );
$as_admin = (string) ob_get_clean();
ck( 'the wp-admin return is this screen too, with no decision on an open application', array( decisions_on( $as_admin, 501 ), false !== strpos( $as_admin, '<h3>Where it is decided</h3>' ) ), array( $no_decision, true ) );
// The one address the decisions print, and design spec 7.3 asks for it by name.
ck( 'the Approve confirm names the record, the account and the address it will write to', false !== strpos( $on_dashboard[501], esc_attr( 'Create an Airtable record and a site account for Universidad Example, and email a password-set link to ana@example.test? The Airtable record cannot be removed from here.' ) ), true );

$odd_name = "Acme&#092;'s College";
seed_application( 509, $odd_name, WPCPM_Institution_Application::STATE_NEW, time() - DAY_IN_SECONDS );
ob_start();
$module->render_application_actions( get_post( 509 ), WPCPM_Institution_Application::STATE_NEW, WPCPM_Return::DASHBOARD );
$odd_forms = (string) ob_get_clean();
ck( 'a name holding an apostrophe and a character reference reaches the decisions as the attribute\'s escaping gives it, and no form prints an inline handler', array(
	preg_match( '/\son[a-z]+=/i', $odd_forms ),
	substr_count( $odd_forms, 'data-wpcpm-confirm="' ),
	substr_count( $odd_forms, esc_attr( $odd_name ) ),
), array( 0, 3, 3 ) );
// Put back in the queue and Delete for good name no tab: pressed from the view that drew them, each
// comes back to the screen's own address, which is the queue, with an outcome the queue's own map
// words. The log row the purge writes is put back after it, for the log's own checks further down.
$log_before    = $GLOBALS['opts'][ WPCPM_Institutions::OPT_APP_LOG ] ?? null;
$closed_landed = array();

foreach ( array( 'wpcpm_app_reopen' => array( $spammed, 'handle_reopen' ), 'wpcpm_app_purge' => array( $rejected, 'handle_purge' ) ) as $form_action => $press ) {
	$_POST                         = form_fields( $press[0], $form_action );
	$went                          = outcome( array( $module, $press[1] ) );
	$flashed                       = get_user_meta( 1, WPCPM_Flash::META );
	$closed_landed[ $form_action ] = array(
		$went,
		$GLOBALS['referer'],
		isset( $flashed['institutions'] ) ? $flashed['institutions'] : '',
		isset( $flashed['institutions'] ) && isset( WPCPM_Institutions::queue_messages()[ $flashed['institutions'] ] ),
	);
	delete_user_meta( 1, WPCPM_Flash::META );
}

$_POST = array();

if ( null === $log_before ) {
	unset( $GLOBALS['opts'][ WPCPM_Institutions::OPT_APP_LOG ] );
} else {
	$GLOBALS['opts'][ WPCPM_Institutions::OPT_APP_LOG ] = $log_before;
}

ck( 'Put back in the queue and Delete for good, pressed from the closed application\'s view, come back to the screen\'s own address, which is the queue, with an outcome the queue words', $closed_landed, array(
	'wpcpm_app_reopen' => array( $back, array( 'wpcpm_app_reopen_531' ), 'app-reopened', true ),
	'wpcpm_app_purge'  => array( $back, array( 'wpcpm_app_purge_532' ), 'app-purged', true ),
) );

foreach ( array( 530, 531, 532, 533 ) as $application_id ) {
	wp_delete_post( $application_id, true );
}

$GLOBALS['deleted'] = array();

/* ---- what the checks made of it ----------------------------------------- */

echo "\n=== What the checks made of it ===\n";

// The absence of a flag is evidence a manager decides on too, so it is printed rather than
// left as an empty space that could equally mean nobody looked.
ck( 'an application nothing was flagged on says so', array(
	false !== strpos( $open, '<h3>What the checks made of it</h3>' ),
	false !== strpos( $open, 'Nothing. Every check the form makes passed.' ),
), array( true, true ) );

$open_held = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 502 ) );

ck( 'a held one says why it is held, in the words of the checks that held it', array(
	false !== strpos( $open_held, '<h3>Why this application is held</h3>' ),
	// The sentence must say what holding actually does. It spares the managers a message and
	// nothing else: the applicant is acknowledged like anybody else and holds the link that
	// confirms their address. An earlier wording said nobody had written to them, which was
	// the opposite of what the form does and would have steered the manager's decision.
	false !== strpos( $open_held, 'Holding spares the Administrators a message and nothing more' ),
	false === strpos( $open_held, 'nothing on this row says the applicant was ever written to' ),
	false !== strpos( $open_held, 'None of these checks refused anything' ),
	false !== strpos( $open_held, 'looks able to receive mail' ),
	false !== strpos( $open_held, 'is shorter than 30 characters.' ),
	false !== strpos( $open_held, 'Another application already named this institution or this address.' ),
), array( true, true, true, true, true, true, true ) );

// The sentence this used to print sent the manager off to wait for the link in an
// acknowledgement, and a held submission is the one nothing was announced about.
ck( 'and its address line names no mail, points at the checks, and leaves the confirming to the applicant', array(
	false !== strpos( $open_held, 'nothing on this row says the applicant was ever asked to' ),
	false !== strpos( $open_held, 'read the checks above before you read the silence' ),
	false !== strpos( $open_held, 'only the applicant can confirm it' ),
	strpos( $open_held, 'link in their acknowledgement' ),
), array( true, true, true, false ) );

// The question is the Administrator Dashboard's to send, so the line names where it is asked.
ck( 'and sends the question to the place it is asked, the Administrator Dashboard', array(
	false !== strpos( $open_held, 'Ask them something on the Administrator Dashboard if you need to, or decide the row on what is on it.' ),
	strpos( $open_held, 'Ask them something from here' ),
), array( true, false ) );

ck( 'one that was acknowledged is still sent to the link that acknowledgement carried', array(
	false !== strpos( $decided, 'The acknowledgement carried the link that confirms it' ),
	strpos( $decided, 'nothing on this row says the applicant was ever asked to' ),
), array( true, false ) );

// A held row can be confirmed like any other - the link is signed against the application and
// not against its state - and the line has to say the confirmed thing when it is confirmed,
// or a manager reads "held" as "cannot be approved" and rejects something approvable. The
// approval itself is the Administrator Dashboard's, so the way there is what the row offers.
update_post_meta( 502, WPCPM_Institution_Application::META_VERIFIED, (string) ( $now - $day ) );
$open_held_verified = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 502 ) );
ck( 'a held application that has been confirmed says the confirmed thing, still says why it is held, and links to its own item where it is approved', array(
	false !== strpos( $open_held_verified, 'The applicant confirmed their address on ' . gmdate( 'Y-m-d H:i', $now - $day ) ),
	false !== strpos( $open_held_verified, '<h3>Why this application is held</h3>' ),
	substr_count( $open_held_verified, $to_item( 502 ) ),
	decisions_on( $open_held_verified, 502 ),
), array( true, true, 1, $no_decision ) );
update_post_meta( 502, WPCPM_Institution_Application::META_VERIFIED, '' );

// Every check the form can raise, on one row, plus one it cannot: the words are the whole
// point of the block, and a slug this screen has no sentence for is still the reason
// somebody's application is sitting in front of a manager.
seed_application(
	510,
	'Todos los Chequeos',
	WPCPM_Institution_Application::STATE_HELD,
	$now - $day,
	array(
		WPCPM_Institution_Application::META_FIELDS  => array( 'Contact Email' => 'todos@example.test' ),
		WPCPM_Institution_Application::META_SIGNALS => array( 'honeypot', 'dwell', 'dwell-fast', 'disallowed', 'links', 'identical', 'short', 'no-mx', 'name-is-contact', 'site-ceiling', 'duplicate', 'wobble' ),
	)
);

$every = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 510 ) );
$said  = array();

foreach ( array(
	'honeypot'        => 'A field no visitor can see was filled in',
	'dwell'           => 'less than 6 seconds after the page was drawn',
	'dwell-fast'      => 'sent again less than 6 seconds after the form was redrawn',
	'disallowed'      => 'comment disallowed list',
	'links'           => 'The written answers carry 3 links or more.',
	'identical'       => 'The same paragraph was given as the answer to more than one question.',
	'short'           => 'is shorter than 30 characters.',
	'no-mx'           => 'looks able to receive mail',
	'name-is-contact' => 'were given the same name',
	'site-ceiling'    => 'The site had already taken 40 applications that day',
	'duplicate'       => 'Another application already named this institution or this address.',
	'wobble'          => 'recorded as &quot;wobble&quot;',
) as $signal => $sentence ) {
	$said[ $signal ] = false !== strpos( $every, $sentence );
}

ck( 'every check the form can raise reaches the manager in words, including one this screen has none for', $said, array_fill_keys( array_keys( $said ), true ) );
// The four numbers are read from the form's own constants, so a limit changed there cannot
// leave this screen quoting the old one.
ck( 'and the numbers in them are the form\'s own', array(
	WPCPM_Institution_Application::MIN_SECONDS,
	WPCPM_Institution_Application::MAX_LINKS,
	WPCPM_Institution_Application::MIN_REASON,
	WPCPM_Institution_Application::PER_DAY,
), array( 6, 3, 30, 40 ) );

wp_delete_post( 510, true );
$GLOBALS['deleted'] = array();

/* ---- the queue's six handlers ------------------------------------------- */

echo "\n=== The queue's handlers ===\n";

$queue_handlers = array( 'handle_approve', 'handle_info', 'handle_reject', 'handle_spam', 'handle_reopen', 'handle_resend_link', 'handle_purge' );

$GLOBALS['caps'] = false;
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 501 );

foreach ( $queue_handlers as $handler ) {
	ck(
		sprintf( '%s without the capability dies 403 before any nonce is read', $handler ),
		array( outcome( array( $module, $handler ) ), $GLOBALS['referer'] ),
		array( 'wp_die: You do not have permission to manage the program.', array() )
	);
}

$GLOBALS['caps'] = true;

// Approve.
$GLOBALS['approved'] = array();
ck( 'handle_approve checks a nonce keyed to the application and hands it to the approval', array(
	outcome( array( $module, 'handle_approve' ) ),
	$GLOBALS['referer'],
	$GLOBALS['approved'],
	get_user_meta( 1, WPCPM_Flash::META ),
), array( $back, array( 'wpcpm_app_approve_501' ), array( array( 501, 1 ) ), array( 'institutions' => 'app-approved' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['approve_result'] = array( 'record' => 'recSEED0000000008', 'user_id' => 78, 'adopted' => true );
outcome( array( $module, 'handle_approve' ) );
ck( 'an adopted record says so, because nothing was created in the base', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'app-adopted' ) );
delete_user_meta( 1, WPCPM_Flash::META );

// Every code the approval can refuse with, and the sentence each one reaches the reader as.
$refusals = array(
	'wpcpm_app_unknown'    => 'app-unknown',
	'wpcpm_app_state'      => 'app-state',
	'wpcpm_app_unverified' => 'app-unverified',
	'wpcpm_app_email'      => 'app-email',
	'wpcpm_app_country'    => 'app-country',
	'wpcpm_app_busy'       => 'app-busy',
	'wpcpm_app_fields'     => 'app-incomplete',
	'wpcpm_app_no_email'   => 'app-incomplete',
	'wpcpm_app_name'       => 'app-incomplete',
	'wpcpm_app_airtable'   => 'app-failed',
	'wpcpm_app_answers'    => 'app-failed',
	'wpcpm_app_actor'      => 'app-failed',
);

foreach ( $refusals as $code => $slug ) {
	$GLOBALS['approve_result'] = new WP_Error( $code, 'refused' );
	outcome( array( $module, 'handle_approve' ) );
	ck( sprintf( 'a %s refusal reaches the reader as %s', $code, $slug ), get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => $slug ) );
	delete_user_meta( 1, WPCPM_Flash::META );
}

unset( $GLOBALS['approve_result'] );

// The sentence itself and not only the slug: this one used to send a manager off to wait for
// an acknowledgement, which for a held row is a mail that may never have left. Read as a
// second manager, because `WPCPM_Flash::take()` memoizes per person and per channel for the
// life of a request and this one process renders the screen dozens of times.
$GLOBALS['uid'] = 2;
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'app-unverified' );
$unverified_notice = render_screen();
$GLOBALS['uid']    = 1;
ck( 'the unverified refusal names the applicant\'s own act and no mail at all', array(
	false !== strpos( $unverified_notice, 'Confirming it is the applicant&#039;s own act and no Administrator can take it for them' ),
	false !== strpos( $unverified_notice, 'open the application, where the line under the heading says what that means' ),
	strpos( $unverified_notice, 'acknowledgement' ),
), array( true, true, false ) );
delete_user_meta( 2, WPCPM_Flash::META );

$GLOBALS['approved'] = array();
$_POST               = array( WPCPM_Institutions::FIELD_APPLICATION => 999 );
outcome( array( $module, 'handle_approve' ) );
ck( 'a post that is not one of ours is refused before anything is asked of the approval', array( get_user_meta( 1, WPCPM_Flash::META ), $GLOBALS['approved'] ), array( array( 'institutions' => 'app-unknown' ), array() ) );
delete_user_meta( 1, WPCPM_Flash::META );

update_post_meta( 502, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_REJECTED );
$_POST = array( WPCPM_Institutions::FIELD_APPLICATION => 502 );
outcome( array( $module, 'handle_approve' ) );
ck( 'and a decided application cannot be approved from a stale page', array( get_user_meta( 1, WPCPM_Flash::META ), $GLOBALS['approved'] ), array( array( 'institutions' => 'app-state' ), array() ) );
delete_user_meta( 1, WPCPM_Flash::META );
update_post_meta( 502, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_HELD );

// Request more information.
$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 501, 'wpcpm_question' => 'Which department would run the internships?' );
outcome( array( $module, 'handle_info' ) );
$question_mail = $GLOBALS['mail'][0] ?? array();
ck( 'handle_info mails the question with the manager to reply to and parks the application', array(
	$question_mail[0] ?? '',
	$question_mail[1] ?? '',
	$question_mail[2] ?? '',
	false !== strpos( $question_mail[3]['body'] ?? '', 'Which department would run the internships?' ),
	$question_mail[3]['headers'] ?? array(),
	(string) get_post_meta( 501, WPCPM_Institution_Application::META_STATE, true ),
	get_user_meta( 1, WPCPM_Flash::META ),
), array( 'send_to', 'ana@example.test', 'institution-information', true, array( 'Reply-To: "Ada Admin" <admin@example.test>' ), 'info', array( 'institutions' => 'app-info' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

$events = get_post_meta( 501, WPCPM_Institution_Application::META_EVENT, false );
ck( 'the question is on the application\'s own history too', array(
	count( $events ),
	$events[0]['event'] ?? '',
	$events[0]['actor'] ?? 0,
	$events[0]['note'] ?? '',
), array( 1, 'information requested', 1, 'Which department would run the internships?' ) );

// A send that failed. `info` is this queue's word for "asked, and waiting on them", so
// writing it here would tell the next manager that an applicant who was never asked anything
// is the one holding this up, and would take the question off the screen that could resend it.
$GLOBALS['mail_refuses'] = true;
$GLOBALS['mail']         = array();
$_POST                   = array( WPCPM_Institutions::FIELD_APPLICATION => 501, 'wpcpm_question' => 'Which term would the first students start in?' );
outcome( array( $module, 'handle_info' ) );
ck( 'a question the mail server would not take is reported, and moves nothing', array(
	get_user_meta( 1, WPCPM_Flash::META ),
	$GLOBALS['mail'],
	(string) get_post_meta( 501, WPCPM_Institution_Application::META_STATE, true ),
	count( get_post_meta( 501, WPCPM_Institution_Application::META_EVENT, false ) ),
), array( array( 'institutions' => 'app-not-sent' ), array(), 'info', 1 ) );
delete_user_meta( 1, WPCPM_Flash::META );
$GLOBALS['mail_refuses'] = false;

// A third reader, for the same reason as above.
$GLOBALS['uid'] = 3;
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'app-not-sent' );
$not_sent_notice = render_screen();
$GLOBALS['uid']  = 1;
ck( 'and the reader is told that nothing moved and the question is still theirs to ask', array(
	false !== strpos( $not_sent_notice, 'Nothing was sent and nothing moved.' ),
	false !== strpos( $not_sent_notice, 'the question is still yours to ask' ),
), array( true, true ) );
delete_user_meta( 3, WPCPM_Flash::META );

$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 501, 'wpcpm_question' => 'why?' );
$short_went      = outcome( array( $module, 'handle_info' ) );
$short_left      = get_user_meta( 1, WPCPM_Flash::META );
$short_keys      = array_keys( (array) $short_left );
sort( $short_keys );
// Refused for its own words, so the press lands on the opened application, where its box is, and
// the box gets back what was typed.
ck( 'a question too short to be one is refused and nothing is sent, and what was typed comes back to its box', array(
	$short_went,
	$short_left['institutions'] ?? '',
	$short_left[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-question:501' ]['typed'] ?? null,
	$short_keys,
	$GLOBALS['mail'],
), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions&wpcpm_app_id=501', 'app-question', array( 'question' => 'why?' ), array( 'institutions', WPCPM_Typed_Text::KEPT_CHANNEL . 'app-question:501' ), array() ) );
// The sentence sits beside the kept question, so it says how short is too short, in the words the
// sponsor application's question uses.
ck( 'and its sentence says the least a question has to be, as the sponsor application\'s does', WPCPM_Institutions::queue_messages()['app-question'] ?? null, array( 'error', 'Nothing was sent. The question has to be at least 10 characters: it is the whole of what the applicant is told.' ) );
delete_user_meta( 1, WPCPM_Flash::META );

$_POST = array( WPCPM_Institutions::FIELD_APPLICATION => 503, 'wpcpm_question' => 'Could you tell us which department this is?' );
update_post_meta( 503, WPCPM_Institution_Application::META_FIELDS, array( 'Contact Email' => 'not an address' ) );
outcome( array( $module, 'handle_info' ) );
ck( 'an application with no usable address has nobody to ask', array( get_user_meta( 1, WPCPM_Flash::META ), $GLOBALS['mail'] ), array( array( 'institutions' => 'app-no-email' ), array() ) );
delete_user_meta( 1, WPCPM_Flash::META );
update_post_meta( 503, WPCPM_Institution_Application::META_FIELDS, array( 'Contact Email' => 'reitoria@example.test' ) );

/* ---- the question and the Reject reason, as they were typed ------------- */

echo "\n=== A question or a reason the cleaner would take words from is refused, and comes back as typed ===\n";

/**
 * What a browser shows in each text area of a render that posts the given field, in document order:
 * the parser drops one line feed right after the opening tag, then reads each entity once.
 *
 * @param string $html Rendered markup.
 * @param string $name The text area's name.
 * @return string[]
 */
function boxes_named( $html, $name ) {
	preg_match_all( '#<textarea[^>]* name="' . preg_quote( $name, '#' ) . '"[^>]*>(.*?)</textarea>#s', (string) $html, $m );
	return array_map(
		static function ( $body ) {
			return html_entity_decode( 0 === strpos( $body, "\n" ) ? substr( $body, 1 ) : $body, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		},
		$m[1]
	);
}

/**
 * The decisions on one open application as the Administrator Dashboard draws them, for one person.
 *
 * @param int $uid            Who reads the page.
 * @param int $application_id The application.
 * @return string
 */
function dashboard_decisions( $uid, $application_id ) {
	$GLOBALS['uid'] = (int) $uid;
	ob_start();
	( new WPCPM_Institutions() )->render_application_actions( get_post( (int) $application_id ), (string) get_post_meta( (int) $application_id, WPCPM_Institution_Application::META_STATE, true ), WPCPM_Return::DASHBOARD );
	$html           = (string) ob_get_clean();
	$GLOBALS['uid'] = 1;
	return $html;
}

/**
 * Hand what one person's flash holds to a manager of their own, who stands for that person on the
 * next page: a new request, which reads the flash afresh. `WPCPM_Flash::take()` memoizes per person
 * and per channel for the life of a request, and the press took the form's channel once already.
 *
 * @param int $from Who pressed.
 * @param int $to   The manager who reads the next page.
 */
function next_request( $from, $to ) {
	$GLOBALS['umeta'][ (int) $to ][ WPCPM_Flash::META ] = get_user_meta( (int) $from, WPCPM_Flash::META );
	delete_user_meta( (int) $from, WPCPM_Flash::META );
}

// "Kids <12 free, adults >18 pay": the cleaner takes "<12 free, adults >" for a tag and drops it,
// words and all, and "10%ca" loses "%ca" as a URL octet. Each box refuses such a typing before
// anything is sent or written, names itself in the sentence, and keeps the typing for the one time
// its form is drawn again: in that application's box of that form, for that person alone.
$lossy_question = "Kids <12 free, adults >18 pay: is that so?\r\nWhich department would run it?";
$lossy_reason   = "Kids <12 free, adults >18 pay, and 10%cashback on top.\r\nNot a program we run.";
$kept_question  = str_replace( "\r\n", "\n", $lossy_question );
$kept_reason    = str_replace( "\r\n", "\n", $lossy_reason );
$opened         = static function ( $id ) {
	return 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions&wpcpm_app_id=' . (int) $id;
};
$history        = static function ( $id ) {
	return count( get_post_meta( (int) $id, WPCPM_Institution_Application::META_EVENT, false ) );
};
$state_of       = static function ( $id ) {
	return (string) get_post_meta( (int) $id, WPCPM_Institution_Application::META_STATE, true );
};

// The question.
// Each press below is put back after it: the checks further down read these applications as the
// queue's fixtures left them.
$saved_501       = $GLOBALS['pmeta'][501];
$saved_502       = $GLOBALS['pmeta'][502];
$history_501     = $history( 501 );
$state_501       = $state_of( 501 );
$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 501, 'wpcpm_question' => $lossy_question );
$went            = outcome( array( $module, 'handle_info' ) );
$left            = get_user_meta( 1, WPCPM_Flash::META );
ck( 'a question the cleaner would take words from is refused before anything is sent or written, and the press lands on the opened application', array(
	$went,
	$left['institutions'] ?? '',
	$GLOBALS['mail'],
	$state_of( 501 ),
	$history( 501 ),
), array( $opened( 501 ), 'app-question-loss', array(), $state_501, $history_501 ) );
ck( 'what was typed waits for that application\'s question box, a line break read as the one LF its box counts', $left[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-question:501' ]['typed'] ?? null, array( 'question' => $kept_question ) );
ck( 'and the sentence names the box by its label and says to send it again', WPCPM_Institutions::queue_messages()['app-question-loss'] ?? null, array( 'error', 'Nothing was sent, because WordPress would remove part of what you typed in "Ask the applicant something". To keep every word, put a space after each "<" (or write "less than") and after each "%", then send it again.' ) );

// Another person, drawn the same application while the typing waits, sees an empty box.
$other_sees = boxes_named( dashboard_decisions( 72, 501 ), 'wpcpm_question' );
next_request( 1, 71 );
$elsewhere  = boxes_named( dashboard_decisions( 71, 503 ), 'wpcpm_question' );
$drawn      = dashboard_decisions( 71, 501 );
ck( 'on the Administrator Dashboard the next page gives the typing back exactly, in the question box of that application, once', array(
	boxes_named( $drawn, 'wpcpm_question' ),
	substr_count( $drawn, '>' . "\n" . esc_textarea( $kept_question ) . '</textarea>' ),
	boxes_named( $drawn, 'wpcpm_reason' ),
), array( array( $kept_question ), 1, array( '' ) ) );
$still_kept = isset( get_user_meta( 71, WPCPM_Flash::META )[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-question:501' ] );
next_request( 71, 73 );
ck( 'and it is taken: the page after that finds nothing waiting', array( $still_kept, boxes_named( dashboard_decisions( 73, 501 ), 'wpcpm_question' ) ), array( false, array( '' ) ) );
ck( 'another person never sees it, nor does another application\'s box', array( $other_sees, $elsewhere ), array( array( '' ), array( '' ) ) );
foreach ( array( 71, 72, 73 ) as $reader ) {
	delete_user_meta( $reader, WPCPM_Flash::META );
}

// The Reject reason.
$history_502     = $history( 502 );
$state_502       = $state_of( 502 );
$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 502, 'wpcpm_reason' => $lossy_reason );
$went            = outcome( array( $module, 'handle_reject' ) );
$left            = get_user_meta( 1, WPCPM_Flash::META );
ck( 'a Reject reason the cleaner would take words from is refused before anything is sent or written: no acknowledgement, no state, no history line', array(
	$went,
	$left['institutions'] ?? '',
	$GLOBALS['mail'],
	$state_of( 502 ),
	$history( 502 ),
), array( $opened( 502 ), 'app-reason-loss', array(), $state_502, $history_502 ) );
ck( 'what was typed waits for that application\'s Reject box', $left[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-reason:502' ]['typed'] ?? null, array( 'reason' => $kept_reason ) );
ck( 'and the sentence names the Reject box by its label and says to reject the application again', WPCPM_Institutions::queue_messages()['app-reason-loss'] ?? null, array( 'error', 'Nothing was rejected, because WordPress would remove part of what you typed in "Why, for the next Administrator who reads this". To keep every word, put a space after each "<" (or write "less than") and after each "%", then reject the application again.' ) );

// On the Institutions screen the decisions are drawn for an application past the dashboard card's
// window, and the press comes back to it opened: the typing is given back there too.
for ( $i = 1; $i <= WPCPM_Administrators_Cards::LIMIT; $i++ ) {
	seed_application( 1200 + $i, sprintf( 'Earlier %d', $i ), WPCPM_Institution_Application::STATE_NEW, $now - ( 90 * $day ) + $i, array( WPCPM_Institution_Application::META_EMAIL => 'hash-of-earlier-' . $i ) );
}
next_request( 1, 74 );
$GLOBALS['uid'] = 75;
$other_screen   = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 502 ) );
$GLOBALS['uid'] = 74;
$other_opened   = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 503 ) );
$screen_drawn   = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 502 ) );
$GLOBALS['uid'] = 1;
for ( $i = 1; $i <= WPCPM_Administrators_Cards::LIMIT; $i++ ) {
	wp_delete_post( 1200 + $i, true );
}
$GLOBALS['deleted'] = array();
ck( 'on the Institutions screen the opened application gives the Reject reason back exactly, once, under the refusal\'s sentence, and the question box stays empty', array(
	boxes_named( $screen_drawn, 'wpcpm_reason' ),
	substr_count( $screen_drawn, '>' . "\n" . esc_textarea( $kept_reason ) . '</textarea>' ),
	boxes_named( $screen_drawn, 'wpcpm_question' ),
	false !== strpos( $screen_drawn, esc_html( WPCPM_Institutions::queue_messages()['app-reason-loss'][1] ?? 'the sentence' ) ),
	isset( get_user_meta( 74, WPCPM_Flash::META )[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-reason:502' ] ),
), array( array( $kept_reason ), 1, array( '' ), true, false ) );
ck( 'and another person drawn the same application there sees an empty Reject box, as does another application opened by the same person', array( boxes_named( $other_screen, 'wpcpm_reason' ), boxes_named( $other_opened, 'wpcpm_reason' ) ), array( array( '' ), array( '' ) ) );
foreach ( array( 74, 75 ) as $reader ) {
	delete_user_meta( $reader, WPCPM_Flash::META );
}

// Measured as typed and refused rather than cut. The box takes two thousand characters, and the
// cleaner writes a "<" that opens no tag as `&lt;`, with each quote mark and ampersand after it as
// an entity, so a reason that fits its box can be stored longer than that: it is kept whole.
$fits = mb_substr( str_repeat( 'Ages 8 < 12 & "up": ', 120 ), 0, WPCPM_Institutions::MAX_NOTE );
ck( 'the reason below fits its box as typed, and the cleaner stores it longer', array( WPCPM_Typed_Text::typed_length( $fits ), mb_strlen( sanitize_textarea_field( $fits ) ) > WPCPM_Institutions::MAX_NOTE, WPCPM_Typed_Text::cleaner_loses( $fits, 'lines' ) ), array( WPCPM_Institutions::MAX_NOTE, true, false ) );

seed_application( 541, 'Reason Example', WPCPM_Institution_Application::STATE_NEW, $now - $day, array( WPCPM_Institution_Application::META_FIELDS => array( 'Contact Email' => 'administrator+reason@example.test' ) ) );
$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 541, 'wpcpm_reason' => $fits );
$went            = outcome( array( $module, 'handle_reject' ) );
$whole           = get_post_meta( 541, WPCPM_Institution_Application::META_EVENT, false );
ck( 'so it is rejected with every word of it on the history, none cut', array(
	$went,
	get_user_meta( 1, WPCPM_Flash::META )['institutions'] ?? '',
	$state_of( 541 ),
	( $whole[0]['note'] ?? '' ) === trim( sanitize_textarea_field( $fits ) ),
	WPCPM_Typed_Text::typed_length( $whole[0]['note'] ?? '' ),
), array( $back, 'app-rejected', 'rejected', true, WPCPM_Typed_Text::typed_length( trim( $fits ) ) ) );
delete_user_meta( 1, WPCPM_Flash::META );
wp_delete_post( 541, true );
$GLOBALS['deleted'] = array();

$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 502, 'wpcpm_reason' => str_repeat( 'a', WPCPM_Institutions::MAX_NOTE + 1 ) );
$went            = outcome( array( $module, 'handle_reject' ) );
$left            = get_user_meta( 1, WPCPM_Flash::META );
ck( 'a reason over the limit as typed is refused, not cut: nothing sent, the state and the history as they were, the typing kept up to the box\'s room', array(
	$went,
	$left['institutions'] ?? '',
	$GLOBALS['mail'],
	$state_of( 502 ),
	$history( 502 ),
	mb_strlen( (string) ( $left[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-reason:502' ]['typed']['reason'] ?? '' ) ),
), array( $opened( 502 ), 'app-reason-long', array(), $state_502, $history_502, WPCPM_Institutions::MAX_NOTE ) );
delete_user_meta( 1, WPCPM_Flash::META );
ck( 'and its sentence names the Reject box', WPCPM_Institutions::queue_messages()['app-reason-long'] ?? null, array( 'error', 'Nothing was rejected. What you typed in "Why, for the next Administrator who reads this" is longer than 2000 characters: shorten it and reject the application again.' ) );

$_POST = array( WPCPM_Institutions::FIELD_APPLICATION => 501, 'wpcpm_question' => str_repeat( 'a', WPCPM_Institutions::MAX_NOTE + 1 ) );
$went  = outcome( array( $module, 'handle_info' ) );
$left  = get_user_meta( 1, WPCPM_Flash::META );
ck( 'a question over the limit as typed is refused the same way, never cut and sent', array(
	$went,
	$left['institutions'] ?? '',
	$GLOBALS['mail'],
	$state_of( 501 ),
	$history( 501 ),
	mb_strlen( (string) ( $left[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-question:501' ]['typed']['question'] ?? '' ) ),
), array( $opened( 501 ), 'app-question-long', array(), $state_501, $history_501, WPCPM_Institutions::MAX_NOTE ) );
delete_user_meta( 1, WPCPM_Flash::META );
ck( 'and its sentence names the question box', WPCPM_Institutions::queue_messages()['app-question-long'] ?? null, array( 'error', 'Nothing was sent. What you typed in "Ask the applicant something" is longer than 2000 characters: shorten it and send it again.' ) );

// Both limits are counted on what was typed, as the box counts it: an entity typed out is as many
// characters as it was typed with, and a reference typed out after a "<", which the cleaner writes
// in core's longer form (`&#9;` as `&#009;`), is counted as it was typed too.
$length_press = static function ( $id, $field, $typed, $handler ) use ( $module ) {
	seed_application( $id, 'Length Example', WPCPM_Institution_Application::STATE_NEW, time() - DAY_IN_SECONDS, array( WPCPM_Institution_Application::META_FIELDS => array( 'Contact Email' => 'administrator+length@example.test' ) ) );
	$GLOBALS['mail'] = array();
	$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => $id, $field => $typed );
	$went            = outcome( array( $module, $handler ) );
	$left            = get_user_meta( 1, WPCPM_Flash::META );
	$got             = array(
		'went'  => $went,
		'said'  => $left['institutions'] ?? '',
		'sent'  => count( $GLOBALS['mail'] ),
		'state' => (string) get_post_meta( $id, WPCPM_Institution_Application::META_STATE, true ),
		'kept'  => $left[ WPCPM_Typed_Text::KEPT_CHANNEL . ( 'wpcpm_question' === $field ? 'app-question:' : 'app-reason:' ) . $id ]['typed'] ?? array(),
	);
	delete_user_meta( 1, WPCPM_Flash::META );
	wp_delete_post( $id, true );
	$GLOBALS['deleted'] = array();
	$GLOBALS['mail']    = array();

	return $got;
};
$at_most_typed = str_repeat( 'Ages 8 < 12 &#9; ok. ', 95 ) . 'Done.';
ck( 'the question below is 2,000 characters as typed, and the count of what the cleaner keeps reads it as more', array( WPCPM_Typed_Text::box_length( $at_most_typed ), WPCPM_Typed_Text::typed_length( sanitize_textarea_field( $at_most_typed ) ) > WPCPM_Institutions::MAX_NOTE, WPCPM_Typed_Text::cleaner_loses( $at_most_typed, 'lines' ) ), array( WPCPM_Institutions::MAX_NOTE, true, false ) );
ck( 'a question of 14 characters as typed, "Why &lt;b&gt;?" typed out, is sent', $length_press( 551, 'wpcpm_question', 'Why &lt;b&gt;?', 'handle_info' ), array( 'went' => $back, 'said' => 'app-info', 'sent' => 1, 'state' => 'info', 'kept' => array() ) );
ck( 'one of exactly 10 characters as typed, "Q &amp; A?", is sent', $length_press( 552, 'wpcpm_question', 'Q &amp; A?', 'handle_info' ), array( 'went' => $back, 'said' => 'app-info', 'sent' => 1, 'state' => 'info', 'kept' => array() ) );
ck( 'one of 9 characters as typed, "1 < &#50;", is refused as too short, though the cleaner writes it longer, and comes back to its box', $length_press( 553, 'wpcpm_question', '1 < &#50;', 'handle_info' ), array( 'went' => $opened( 553 ), 'said' => 'app-question', 'sent' => 0, 'state' => 'new', 'kept' => array( 'question' => '1 < &#50;' ) ) );
ck( 'one of exactly 2,000 characters as typed is sent', $length_press( 554, 'wpcpm_question', $at_most_typed, 'handle_info' ), array( 'went' => $back, 'said' => 'app-info', 'sent' => 1, 'state' => 'info', 'kept' => array() ) );
ck( 'one of 2,001 characters as typed, each "&lt;" typed out, is refused as too long, the typing kept up to the box\'s room', $length_press( 555, 'wpcpm_question', str_repeat( '&lt;', 500 ) . '?', 'handle_info' ), array( 'went' => $opened( 555 ), 'said' => 'app-question-long', 'sent' => 0, 'state' => 'new', 'kept' => array( 'question' => str_repeat( '&lt;', 500 ) ) ) );
ck( 'a Reject reason of exactly 2,000 characters as typed is kept', $length_press( 556, 'wpcpm_reason', $at_most_typed, 'handle_reject' ), array( 'went' => $back, 'said' => 'app-rejected', 'sent' => 1, 'state' => 'rejected', 'kept' => array() ) );
ck( 'and one of 2,001 characters as typed, each "&lt;" typed out, is refused as too long, the typing kept up to the box\'s room', $length_press( 557, 'wpcpm_reason', str_repeat( '&lt;', 500 ) . '.', 'handle_reject' ), array( 'went' => $opened( 557 ), 'said' => 'app-reason-long', 'sent' => 0, 'state' => 'new', 'kept' => array( 'reason' => str_repeat( '&lt;', 500 ) ) ) );

// A clean question, with a "<" and an "&" in it, goes as it did: mailed as it was typed, kept on
// the history as stored. Its press drops what an earlier refusal of the form kept, pressed by a
// manager of their own whose flash this run has not read yet.
$GLOBALS['uid'] = 76;
WPCPM_Typed_Text::keep_box( WPCPM_Typed_Text::box_form( WPCPM_Institutions::TYPED_QUESTION, 501 ), 'question', 'An earlier refused question', WPCPM_Institutions::MAX_NOTE );
$was_kept        = isset( get_user_meta( 76, WPCPM_Flash::META )[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-question:501' ] );
$clean_question  = "Is the age 8 < 12 & \"up\"?\r\nWhich department would run it?";
$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 501, 'wpcpm_question' => $clean_question );
$went            = outcome( array( $module, 'handle_info' ) );
$left            = get_user_meta( 76, WPCPM_Flash::META );
$GLOBALS['uid']  = 1;
$asked           = get_post_meta( 501, WPCPM_Institution_Application::META_EVENT, false );
$asked_last      = end( $asked );
ck( 'a clean question is sent as before, its mail reading as it was typed, and the history keeps it as stored', array(
	$went,
	$left['institutions'] ?? '',
	count( $GLOBALS['mail'] ),
	false !== strpos( $GLOBALS['mail'][0][3]['body'] ?? '', $clean_question ),
	strpos( $GLOBALS['mail'][0][3]['body'] ?? '', '&lt;' ),
	strpos( $GLOBALS['mail'][0][3]['body'] ?? '', '&amp;' ),
	is_array( $asked_last ) ? $asked_last['note'] : '',
), array( $back, 'app-info', 1, true, false, false, sanitize_textarea_field( $clean_question ) ) );
ck( 'and its press dropped what the refusal before it kept', array( $was_kept, isset( $left[ WPCPM_Typed_Text::KEPT_CHANNEL . 'app-question:501' ] ) ), array( true, false ) );
delete_user_meta( 76, WPCPM_Flash::META );
$GLOBALS['pmeta'][501] = $saved_501;
$GLOBALS['pmeta'][502] = $saved_502;
$GLOBALS['mail']       = array();

// Reject: the acknowledgement carries no reason, decision 16.
$GLOBALS['mail'] = array();
$reason          = 'Duplicate of APP-2026-0007, and the department does not exist.';
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 502, 'wpcpm_reason' => $reason );
outcome( array( $module, 'handle_reject' ) );
$reject_mail = $GLOBALS['mail'][0] ?? array();
ck( 'handle_reject mails a neutral acknowledgement with no reason anywhere in it', array(
	$reject_mail[0] ?? '',
	$reject_mail[1] ?? '',
	$reject_mail[2] ?? '',
	0 === strpos( $reject_mail[3]['body'] ?? '', implode( "\r\n\r\n", array( 'Thank you for applying to the WordPress Credits Program, and for the time you took to write to us.', 'We have read your institution\'s application, and we cannot accept it into the program at this time. We are sorry not to have better news.', 'You are welcome to apply again later, for example when your programs change. A new application is read on its own merits, and this decision does not count against it.' ) ) ),
	strpos( json_encode( $GLOBALS['mail'] ), 'Duplicate of APP-2026-0007' ),
	strpos( json_encode( $GLOBALS['mail'] ), 'department does not exist' ),
	(string) get_post_meta( 502, WPCPM_Institution_Application::META_STATE, true ),
	get_user_meta( 1, WPCPM_Flash::META ),
), array( 'send_to', 'someone.else@example.test', 'institution-declined', true, false, false, 'rejected', array( 'institutions' => 'app-rejected' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );
$rejected_notice = (string) ( WPCPM_Institutions::queue_messages()['app-rejected'][1] ?? '' );
ck( 'the notice after Reject names the program\'s Administrators, as the Reject box does', array(
	false !== strpos( $rejected_notice, 'Your reason is on the application\'s own history, where the next Administrator can read it.' ),
	strpos( $rejected_notice, 'next manager' ),
), array( true, false ) );

$rejection = get_post_meta( 502, WPCPM_Institution_Application::META_EVENT, false );
ck( 'and the reason is kept where only a manager reads it', array( $rejection[0]['event'] ?? '', $rejection[0]['note'] ?? '' ), array( 'rejected', $reason ) );

// Spam: nothing is sent, because the address is forged or is somebody else's.
$GLOBALS['mail'] = array();
$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => 503 );
outcome( array( $module, 'handle_spam' ) );
ck( 'handle_spam sends nothing at all', array(
	$GLOBALS['mail'],
	(string) get_post_meta( 503, WPCPM_Institution_Application::META_STATE, true ),
	get_user_meta( 1, WPCPM_Flash::META ),
), array( array(), 'spam', array( 'institutions' => 'app-spam' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

// Reopen: the abort that makes the other four safe to press.
$GLOBALS['mail'] = array();
outcome( array( $module, 'handle_reopen' ) );
ck( 'handle_reopen puts it back to new and sends nothing', array(
	$GLOBALS['mail'],
	(string) get_post_meta( 503, WPCPM_Institution_Application::META_STATE, true ),
	get_user_meta( 1, WPCPM_Flash::META ),
), array( array(), 'new', array( 'institutions' => 'app-reopened' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

outcome( array( $module, 'handle_reopen' ) );
ck( 'and refuses one that is already open', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'app-state' ) );
delete_user_meta( 1, WPCPM_Flash::META );

/* ---- the confirmation link, sent again ----------------------------------- */

// Approve refuses until the applicant's address is confirmed, and only the acknowledgement's link
// confirms it. Until 1.123.26 nothing sent that link again, so a row over the day's limit on
// acknowledgements, a spam-filed row put back and an applicant who lost the email were stuck for
// good. Send the confirmation link again sends it on an open application whose address is not
// confirmed; Put back in the queue sends it to a spam-filed row that never had it.
echo "\n=== The confirmation link, sent again ===\n";

seed_application(
	540,
	'Sin Confirmar Example',
	WPCPM_Institution_Application::STATE_NEW,
	$now - ( 3 * $day ),
	array(
		WPCPM_Institution_Application::META_FIELDS    => array( 'Contact Email' => 'pendiente@example.test' ),
		WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0040',
		WPCPM_Institution_Application::META_EMAIL     => 'hash-of-pendiente',
		WPCPM_Institution_Application::META_SIGNALS   => array( 'mail-ceiling' ),
	)
);

$link_events = static function ( $id ) {
	return array_values(
		array_filter(
			array_map(
				static function ( $row ) { return is_array( $row ) ? (string) $row['event'] : ''; },
				(array) get_post_meta( (int) $id, WPCPM_Institution_Application::META_EVENT, false )
			),
			static function ( $event ) { return in_array( $event, array( 'confirmation link sent again', 'reopened, acknowledgement sent' ), true ); }
		)
	);
};
$link_press = static function ( $id, $result = 'sent' ) use ( $module ) {
	$GLOBALS['acknowledged'] = array();
	$GLOBALS['ack_result']   = $result;
	$_POST                   = array( WPCPM_Institutions::FIELD_APPLICATION => (int) $id );
	$went                    = outcome( array( $module, 'handle_resend_link' ) );
	$flashed                 = get_user_meta( 1, WPCPM_Flash::META );
	delete_user_meta( 1, WPCPM_Flash::META );
	unset( $GLOBALS['ack_result'] );
	return array( $went, $GLOBALS['referer'], $GLOBALS['acknowledged'], $flashed['institutions'] ?? '' );
};

ck( 'pressed on an open application whose address is not confirmed, it sends the acknowledgement once, with its nonce keyed to the application, and comes back to the queue', $link_press( 540 ), array( $back, array( 'wpcpm_app_resend_link_540' ), array( 540 ), 'app-link-sent' ) );
ck( 'the notice says so, in the words asked for', WPCPM_Institutions::queue_messages()['app-link-sent'], array( 'success', 'The confirmation link was sent again.' ) );
$link_history = get_post_meta( 540, WPCPM_Institution_Application::META_EVENT, false );
ck( 'and the application\'s history says it went, and who sent it', array( $link_events( 540 ), end( $link_history )['actor'] ?? 0 ), array( array( 'confirmation link sent again' ), 1 ) );
ck( 'the application stays where it was', (string) get_post_meta( 540, WPCPM_Institution_Application::META_STATE, true ), 'new' );

ck( 'over the day\'s limit on acknowledgements nothing is sent and nothing is written, and the notice says why', array( $link_press( 540, 'ceiling' ), count( $link_events( 540 ) ) ), array( array( $back, array( 'wpcpm_app_resend_link_540' ), array( 540 ), 'app-mail-ceiling' ), 1 ) );
ck( 'the limit is named, and so is when to try again', array(
	WPCPM_Institutions::queue_messages()['app-mail-ceiling'][0],
	false !== strpos( WPCPM_Institutions::queue_messages()['app-mail-ceiling'][1], 'Nothing was sent and nothing moved. This site has already sent the ' . WPCPM_Institution_Application::MAIL_PER_DAY . ' acknowledgements it sends in a day' ),
	false !== strpos( WPCPM_Institutions::queue_messages()['app-mail-ceiling'][1], 'Try again tomorrow.' ),
), array( 'error', true, true ) );
ck( 'a hand-off the mail server refuses writes nothing either, and says so', array( $link_press( 540, 'failed' ), count( $link_events( 540 ) ) ), array( array( $back, array( 'wpcpm_app_resend_link_540' ), array( 540 ), 'app-ack-not-sent' ), 1 ) );
ck( 'an application whose address is confirmed is sent nothing', $link_press( 501 ), array( $back, array( 'wpcpm_app_resend_link_501' ), array(), 'app-link-confirmed' ) );
seed_application( 541, 'Closed Example', WPCPM_Institution_Application::STATE_REJECTED, $now - ( 30 * $day ), array( WPCPM_Institution_Application::META_FIELDS => array( 'Contact Email' => 'cerrada@example.test' ) ) );
ck( 'and so is a closed one', $link_press( 541 ), array( $back, array( 'wpcpm_app_resend_link_541' ), array(), 'app-state' ) );
seed_application( 542, 'No Address Example', WPCPM_Institution_Application::STATE_NEW, $now - ( 3 * $day ) );
ck( 'and one with no address to send it to', $link_press( 542 ), array( $back, array( 'wpcpm_app_resend_link_542' ), array(), 'app-link-no-email' ) );

// Drawn where the application's decisions are drawn, and only where the address waits on the link.
ob_start();
$module->render_application_actions( get_post( 540 ), WPCPM_Institution_Application::STATE_NEW, WPCPM_Return::DASHBOARD );
$unconfirmed_drawn = (string) ob_get_clean();
ob_start();
$module->render_application_actions( get_post( 501 ), WPCPM_Institution_Application::STATE_NEW, WPCPM_Return::DASHBOARD );
$confirmed_drawn = (string) ob_get_clean();
ob_start();
$module->render_application_actions( get_post( 542 ), WPCPM_Institution_Application::STATE_NEW, WPCPM_Return::DASHBOARD );
$no_address_drawn = (string) ob_get_clean();
ck( 'on the Administrator Dashboard it is offered on an open application whose address is unconfirmed, first, with the fields that bring the press back to the card', array(
	form_fields( $unconfirmed_drawn, 'wpcpm_app_resend_link' ),
	strpos( $unconfirmed_drawn, 'wpcpm_app_resend_link' ) < strpos( $unconfirmed_drawn, 'wpcpm_app_approve' ),
	false !== strpos( $unconfirmed_drawn, '>Send the confirmation link again</button>' ),
	false !== strpos( $unconfirmed_drawn, esc_attr( 'Email the acknowledgement to pendiente@example.test again, with the link that confirms the address?' ) ),
), array( array( 'action' => 'wpcpm_app_resend_link', 'wpcpm_application' => '540', 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'applications' ), true, true, true ) );
ck( 'and not on one whose address is confirmed, nor on one with no address', array( strpos( $confirmed_drawn, 'wpcpm_app_resend_link' ), strpos( $no_address_drawn, 'wpcpm_app_resend_link' ) ), array( false, false ) );
ck( 'on this screen it is drawn with the four decisions of an application past the card\'s window', array(
	substr_count( $held_past, 'nonce-wpcpm_app_resend_link_502' ),
	substr_count( $info_past, 'nonce-wpcpm_app_resend_link_503' ),
	substr_count( $first_past, 'wpcpm_app_resend_link' ),
), array( 1, 1, 0 ) );

// Put back in the queue: a spam-filed row the form's checks filed, or one past the day's limit,
// never had the acknowledgement, so it is sent now, as the mentor applications' Reopen sends theirs.
seed_application( 543, 'Filed As Spam Example', WPCPM_Institution_Application::STATE_SPAM, $now - ( 4 * $day ), array(
	WPCPM_Institution_Application::META_FIELDS  => array( 'Contact Email' => 'rescatada@example.test' ),
	WPCPM_Institution_Application::META_SIGNALS => array( 'dwell' ),
) );
$reopen_press = static function ( $id, $result = 'sent' ) use ( $module ) {
	$GLOBALS['acknowledged'] = array();
	$GLOBALS['ack_result']   = $result;
	$_POST                   = array( WPCPM_Institutions::FIELD_APPLICATION => (int) $id );
	outcome( array( $module, 'handle_reopen' ) );
	$flashed = get_user_meta( 1, WPCPM_Flash::META );
	delete_user_meta( 1, WPCPM_Flash::META );
	unset( $GLOBALS['ack_result'] );
	return array( $GLOBALS['acknowledged'], (string) get_post_meta( (int) $id, WPCPM_Institution_Application::META_STATE, true ), $flashed['institutions'] ?? '' );
};

ck( 'over the day\'s limit, a spam-filed row that never had the acknowledgement stays where it was', $reopen_press( 543, 'ceiling' ), array( array( 543 ), 'spam', 'app-mail-ceiling' ) );
ck( 'and a send the mail server refuses leaves it there too', $reopen_press( 543, 'failed' ), array( array( 543 ), 'spam', 'app-ack-not-sent' ) );
ck( 'put back in the queue, it is sent the acknowledgement with the link, and opens again', $reopen_press( 543 ), array( array( 543 ), 'new', 'app-reopened-sent' ) );
ck( 'its history says both', $link_events( 543 ), array( 'reopened, acknowledgement sent' ) );
ck( 'and the notice says the link went', false !== strpos( WPCPM_Institutions::queue_messages()['app-reopened-sent'][1], 'with the link that confirms the address' ), true );

// Marked as spam again and put back once more: the history says it went, so it does not go twice.
update_post_meta( 543, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_SPAM );
ck( 'one that has had it is put back with nothing sent', $reopen_press( 543 ), array( array(), 'new', 'app-reopened' ) );

// A row an Administrator marked as spam after the form acknowledged it carries no signal that kept
// the acknowledgement: the applicant holds the link already, and is sent nothing.
seed_application( 544, 'Marked By Hand Example', WPCPM_Institution_Application::STATE_SPAM, $now - ( 4 * $day ), array(
	WPCPM_Institution_Application::META_FIELDS  => array( 'Contact Email' => 'marcada@example.test' ),
	WPCPM_Institution_Application::META_SIGNALS => array( 'short' ),
) );
ck( 'one acknowledged before it was marked as spam is put back with nothing sent', $reopen_press( 544 ), array( array(), 'new', 'app-reopened' ) );
// And a rejected row is put back with nothing sent: the link it needs, if it is lost, is the
// open application's own decision.
seed_application( 545, 'Rejected Unsent Example', WPCPM_Institution_Application::STATE_REJECTED, $now - ( 4 * $day ), array(
	WPCPM_Institution_Application::META_FIELDS  => array( 'Contact Email' => 'rechazada@example.test' ),
	WPCPM_Institution_Application::META_SIGNALS => array( 'mail-ceiling' ),
) );
ck( 'and a rejected one is put back with nothing sent', $reopen_press( 545 ), array( array(), 'new', 'app-reopened' ) );

foreach ( array( 540, 541, 542, 543, 544, 545 ) as $gone ) {
	unset( $GLOBALS['posts'][ $gone ], $GLOBALS['pmeta'][ $gone ] );
}
$_POST = array( WPCPM_Institutions::FIELD_APPLICATION => 502 );

/* ---- a half-done approval ------------------------------------------------ */

// An approval that created the Airtable record, and maybe the account, and then stopped is
// finished by pressing Approve again. Until 1.123.26 `is_half_done()` was asked by no screen and
// no handler, so Reject and Reject as spam took such an application and the decline went out while
// the record stood for the institution. They refuse it now, and so does Put back in the queue, as
// the sponsor applications' do, and the decisions say why before anybody presses one.
echo "\n=== A half-done approval ===\n";

$half_fields = array( 'Contact Email' => 'mitad@example.test' );
// Older than every other application seeded, so the queue's list draws it first.
seed_application( 550, 'Half Done Example', WPCPM_Institution_Application::STATE_NEW, $now - ( 400 * $day ), array(
	WPCPM_Institution_Application::META_FIELDS   => $half_fields,
	WPCPM_Institution_Application::META_VERIFIED => (string) ( $now - ( 2 * $day ) ),
) );
seed_application( 551, 'Half Done Held Example', WPCPM_Institution_Application::STATE_HELD, $now - ( 3 * $day ), array(
	WPCPM_Institution_Application::META_FIELDS   => $half_fields,
	WPCPM_Institution_Application::META_VERIFIED => (string) ( $now - ( 2 * $day ) ),
) );
$GLOBALS['half_done'] = array( 550, 551 );

$half_press = static function ( $id, $handler, array $posted = array() ) use ( $module ) {
	$GLOBALS['mail'] = array();
	$_POST           = array( WPCPM_Institutions::FIELD_APPLICATION => (int) $id ) + $posted;
	$went            = outcome( array( $module, $handler ) );
	$flashed         = get_user_meta( 1, WPCPM_Flash::META );
	delete_user_meta( 1, WPCPM_Flash::META );
	return array( $went, $flashed['institutions'] ?? '', (string) get_post_meta( (int) $id, WPCPM_Institution_Application::META_STATE, true ), count( $GLOBALS['mail'] ), count( (array) get_post_meta( (int) $id, WPCPM_Institution_Application::META_EVENT, false ) ) );
};

ck( 'Reject refuses a half-done approval: nothing is sent, nothing moves, nothing is written', $half_press( 550, 'handle_reject', array( 'wpcpm_reason' => 'Changed our mind.' ) ), array( $back, 'app-half-done', 'new', 0, 0 ) );
ck( 'so does Reject as spam', $half_press( 550, 'handle_spam' ), array( $back, 'app-half-done', 'new', 0, 0 ) );
ck( 'and Put back in the queue, on a held one', $half_press( 551, 'handle_reopen' ), array( $back, 'app-half-done', 'held', 0, 0 ) );
ck( 'the refusal says what stopped partway and what finishes it', WPCPM_Institutions::queue_messages()['app-half-done'], array( 'error', 'Nothing happened. Approving this application stopped partway: the institution\'s Airtable record, and maybe its account, already exist. Press Approve again to finish.' ) );

$GLOBALS['approved'] = array();
$_POST               = array( WPCPM_Institutions::FIELD_APPLICATION => 550 );
outcome( array( $module, 'handle_approve' ) );
delete_user_meta( 1, WPCPM_Flash::META );
ck( 'Approve is still taken, since pressing it again is what finishes it', $GLOBALS['approved'], array( array( 550, 1 ) ) );

$half_mark = '<p><span class="wpcpm-inst-mark wpcpm-inst-mark--half-done" title="' . esc_attr( 'An earlier press of Approve created the institution\'s Airtable record and then stopped, so the record, and maybe the account, already exist.' ) . '">approval stopped partway</span> Press Approve again to finish. Reject, Reject as spam and Put back in the queue are refused until it is.</p>';

ob_start();
$module->render_application_actions( get_post( 550 ), WPCPM_Institution_Application::STATE_NEW, WPCPM_Return::DASHBOARD );
$half_on_card = (string) ob_get_clean();
$half_opened  = render_screen( array( WPCPM_Institutions::ARG_APPLICATION => 550 ) );
ob_start();
$module->render_application_actions( get_post( 501 ), WPCPM_Institution_Application::STATE_NEW, WPCPM_Return::DASHBOARD );
$whole_on_card = (string) ob_get_clean();

$half_note = '<p class="wpcpm-administrator__note">' . esc_html( 'Approving this application stopped partway: the institution\'s Airtable record, and maybe its account, already exist. Press Approve again to finish. Reject, Reject as spam and Put back in the queue are refused until it is.' ) . '</p>';
ck( 'on the Administrator Dashboard\'s card it is the card\'s note, under the heading, above the decisions', array(
	substr_count( $half_on_card, $half_note ),
	strpos( $half_on_card, '<h3>What happens next</h3>' . $half_note ) !== false,
	strpos( $half_on_card, 'wpcpm-inst-mark' ),
), array( 1, true, false ) );
ck( 'and on this screen the queue\'s mark, under the heading of where it is decided', array(
	substr_count( $half_opened, $half_mark ),
	strpos( $half_opened, '<h3>Where it is decided</h3>' . $half_mark ) !== false,
), array( 1, true ) );
ck( 'and on no application whose approval was not begun', strpos( $whole_on_card, 'stopped partway' ), false );
// And on its row in the queue's list, as the sponsor applications' rows carry theirs, before the way
// to open it: the list is where an Administrator meets it first.
preg_match_all( '#<li class="wpcpm-queue-item[^"]*">.*?</li>#s', render_tab( 'queue' ), $queue_items );
$half_rows = array_values( array_filter( $queue_items[0], static function ( $item ) { return false !== strpos( $item, 'Half Done' ); } ) );
$rest_rows = array_values( array_filter( $queue_items[0], static function ( $item ) { return false === strpos( $item, 'Half Done' ); } ) );
ck( 'the queue\'s list marks the rows of both approvals that stopped partway, each before its Open this application, and no other row', array(
	count( $half_rows ),
	count( array_filter( $half_rows, static function ( $item ) use ( $half_mark ) { return false !== strpos( $item, $half_mark . '<p><a class="button"' ); } ) ),
	substr_count( implode( '', $rest_rows ), 'stopped partway' ),
), array( 2, 2, 0 ) );


$GLOBALS['half_done'] = array();
foreach ( array( 550, 551 ) as $gone ) {
	unset( $GLOBALS['posts'][ $gone ], $GLOBALS['pmeta'][ $gone ] );
}

// Purge by hand.
$GLOBALS['deleted'] = array();
$_POST              = array( WPCPM_Institutions::FIELD_APPLICATION => 501 );
outcome( array( $module, 'handle_purge' ) );
ck( 'handle_purge refuses an application that is still open', array( get_user_meta( 1, WPCPM_Flash::META ), $GLOBALS['deleted'] ), array( array( 'institutions' => 'app-state' ), array() ) );
delete_user_meta( 1, WPCPM_Flash::META );

$_POST = array( WPCPM_Institutions::FIELD_APPLICATION => 502 );
outcome( array( $module, 'handle_purge' ) );
$log = WPCPM_Institutions::application_log();
ck( 'and deletes a rejected one for good, keeping only what a reference is', array(
	$GLOBALS['deleted'],
	null === get_post( 502 ),
	count( $log ),
	$log[0],
	strpos( json_encode( $log ), 'someone.else@example.test' ),
	strpos( json_encode( $log ), 'Duplicate of APP-2026-0007' ),
	get_user_meta( 1, WPCPM_Flash::META ),
), array(
	array( array( 502, true ) ),
	true,
	1,
	array( 'at' => $log[0]['at'] ?? 0, 'id' => 502, 'reference' => 'APP-2026-0008', 'state' => 'rejected', 'days' => 0, 'actor' => 1 ),
	false,
	false,
	array( 'institutions' => 'app-purged' ),
) );
delete_user_meta( 1, WPCPM_Flash::META );
ck( 'the log is not autoloaded', in_array( array( 'update_option', 'wpcpm_application_log', false ), $GLOBALS['calls'], true ), true );

/* ---- the retention cron ------------------------------------------------- */

echo "\n=== The retention cron ===\n";

$GLOBALS['opts'][ WPCPM_Institutions::OPT_APP_LOG ] = array();

seed_application( 504, 'Old Rejection', WPCPM_Institution_Application::STATE_REJECTED, $now - ( 400 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2025-0001' ) );
seed_application( 505, 'Old Spam', WPCPM_Institution_Application::STATE_SPAM, $now - ( 400 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0002' ) );
// The approved one holds the answers kept on the site since 1.123.29, which go with it when it goes.
seed_application( 506, 'Old Approval', WPCPM_Institution_Application::STATE_APPROVED, $now - ( 400 * $day ), array(
	WPCPM_Institution_Application::META_REFERENCE => 'APP-2025-0003',
	WPCPM_Institution_Application::META_SITE      => array( 'contact_role' => 'Internship coordinator', 'student_languages' => array( 'Spanish' ) ),
) );

// Decided yesterday, arrived a year ago: the clock runs from the decision, so lengthening a
// retention setting gives every row the longer life rather than deleting a batch at once.
seed_application( 507, 'Recently Marked', WPCPM_Institution_Application::STATE_SPAM, $now - ( 400 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0004' ) );
add_post_meta( 507, WPCPM_Institution_Application::META_EVENT, array( 'event' => 'marked as spam', 'at' => $now - $day, 'actor' => 1, 'note' => '' ) );

$purged = WPCPM_Institutions::purge_applications();
ck( 'the cron takes the spam and the old rejection, and 0 days means the approved one is never taken', array(
	$purged,
	null === get_post( 505 ),
	null === get_post( 504 ),
	get_post( 506 ) instanceof WP_Post,
	get_post( 507 ) instanceof WP_Post,
), array( 2, true, true, true, true ) );

$log = WPCPM_Institutions::application_log();
ck( 'each deletion is logged with the rule that removed it and nobody who pressed it', array(
	count( $log ),
	$log[0]['reference'],
	$log[0]['state'],
	$log[0]['days'],
	$log[0]['actor'],
	$log[1]['reference'],
	$log[1]['days'],
), array( 2, 'APP-2026-0002', 'spam', 30, 0, 'APP-2025-0001', 365 ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['application_approved_days'] = 30;
ck( 'the approved one keeps its site-only answers while it is kept', WPCPM_Institution_Application::site_answers( 506 ), array( 'contact_role' => 'Internship coordinator', 'student_languages' => array( 'Spanish' ) ) );
ck( 'and takes the approved one as soon as the setting names a number of days, its site-only answers with it', array( WPCPM_Institutions::purge_applications(), null === get_post( 506 ), isset( $GLOBALS['pmeta'][506][ WPCPM_Institution_Application::META_SITE ] ), WPCPM_Institution_Application::site_answers( 506 ) ), array( 1, true, false, array() ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['application_approved_days'] = 0;

// FANON-3 gave the sponsor queue held-row retention; the institution queue shares the same
// map entry now, so a held application goes the same way a rejected one does and is not left
// in the queue for ever. No event meta to seed: `decided_at()` falls back to the arrival
// time for a row with no history, which is exactly what a held row has.
seed_application( 512, 'Old Held', WPCPM_Institution_Application::STATE_HELD, $now - ( 400 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2025-0006' ) );
seed_application( 513, 'Recent Held', WPCPM_Institution_Application::STATE_HELD, $now - ( 10 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0007' ) );
ck( 'a held application goes the same way a rejected one does, and a recent one stays', array(
	WPCPM_Institutions::purge_applications(),
	null === get_post( 512 ),
	get_post( 513 ) instanceof WP_Post,
), array( 1, true, true ) );
$log = WPCPM_Institutions::application_log();
ck( 'the log names the held row by its own state and the rejected window that removed it', array( end( $log )['state'], end( $log )['days'] ), array( 'held', 365 ) );

$GLOBALS['opts'][ WPCPM_Institutions::OPT_APP_LOG ] = array_fill( 0, WPCPM_Institutions::APP_LOG_MAX, array( 'at' => 1, 'id' => 1, 'reference' => 'APP-0000-0000', 'state' => 'spam', 'days' => 30, 'actor' => 0 ) );
seed_application( 508, 'One More', WPCPM_Institution_Application::STATE_SPAM, $now - ( 400 * $day ), array( WPCPM_Institution_Application::META_REFERENCE => 'APP-2026-0005' ) );
WPCPM_Institutions::purge_applications();
$log = WPCPM_Institutions::application_log();
ck( 'the log is capped and drops its oldest row rather than growing', array( count( $log ), end( $log )['reference'] ), array( WPCPM_Institutions::APP_LOG_MAX, 'APP-2026-0005' ) );

/* ---- lifecycle ---------------------------------------------------------- */

echo "\n=== Lifecycle ===\n";

$GLOBALS['calls'] = array();
$module->boot();
$hooks = array_map( function ( $c ) { return $c[1]; }, array_filter( $GLOBALS['calls'], function ( $c ) { return 'add_action' === $c[0]; } ) );
// The whole list, in order, and not a subset of it. Every handler below is called directly
// by this suite, so a subset check would pass on a tree where none of them was ever hooked -
// and an unhooked `admin_post_` action is a decision that answers nothing, while an unhooked
// cron is a retention rule that never runs.
$wanted = array(
	// First of all: the daily jobs, put back on the clock on every load (schedule_cron()).
	'init',
	'admin_post_wpcpm_institutions_sync', 'admin_post_wpcpm_institutions_cancel', 'admin_post_wpcpm_institutions_probe',
	// The Accounts tab's presses that post to admin-post.php: one institution's Create account, a
	// row's invitation and the invitations card's button.
	'admin_post_wpcpm_institutions_provision_one', 'admin_post_wpcpm_institutions_invite', 'admin_post_wpcpm_institutions_bulk_invite',
	// Linking an unlinked Students row.
	'admin_post_wpcpm_institutions_link', 'wp_ajax_wpcpm_institutions_tick',
	'admin_post_wpcpm_app_approve', 'admin_post_wpcpm_app_info', 'admin_post_wpcpm_app_reject',
	'admin_post_wpcpm_app_spam', 'admin_post_wpcpm_app_reopen', 'admin_post_wpcpm_app_resend_link', 'admin_post_wpcpm_app_purge',
	'wpcpm_purge_applications',
	// Last, the screen's own load hook, hooked once the menu exists, as every audience's
	// accounts screen hooks it (`boot_screen()`); its rows-per-page save is a filter beside it.
	'admin_menu',
);
ck( 'boot() wires every handler this module has, the retention cron and the screen\'s load hook', array_values( $hooks ), $wanted );
// The institution's own page and the People card's handlers boot here too, between the post
// types and the cron: both register hooks, so they belong on `plugins_loaded` with the rest
// rather than being reached from a render.
// Everything `boot()` starts, in order. Read as a slice so a handler added later fails here
// rather than passing unnoticed: this assertion is the only thing that says the module's
// pieces are actually started, and a subset check would say nothing.
ck( 'boots the post types, then every module this screen owns (the institution\'s notes among them no more), then hands the cron to the sync', array_slice( $GLOBALS['calls'], 0, 11 ), array(
	array( 'WPCPM_Institution_Agreement::init' ),
	array( 'WPCPM_Institution_Audit::init' ),
	array( 'WPCPM_Institution_Record::init' ),
	array( 'add_action', 'init' ),
	array( 'ceiling_init' ),
	array( 'application_init' ),
	array( 'generate_init' ),
	array( 'dashboard_init' ),
	array( 'people_init' ),
	array( 'student_form_init' ),
	array( 'invite_init' ),
) );
$started = array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] );
ck( 'and starts the resend of a student\'s invitation right after graduate and withdraw, the card\'s other student control',
	array_slice( $started, (int) array_search( 'students_init', $started, true ), 2 ), array( 'students_init', 'resend_init' ) );
ck( 'and the school\'s details card, whose handlers post to the requests it raises, right after the requests', array_slice( $started, (int) array_search( 'request_init', $started, true ), 2 ), array( 'request_init', 'details_card_init' ) );

// The six daily jobs are scheduled from boot as well as from activation, because the
// activation hook never fires on this site's deploy path (`wp plugin install --force` goes
// through the upgrader's silent reactivation). Recorded, not run: the stub says nothing is
// scheduled yet, so every job is put on the clock; a second call schedules nothing.
$GLOBALS['calls'] = array();
$GLOBALS['cron']  = array();
unset( $GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] );
WPCPM_Institutions::schedule_cron();
$scheduled = array_map( function ( $c ) { return $c[1]; }, array_filter( $GLOBALS['calls'], function ( $c ) { return 'schedule' === $c[0]; } ) );
ck( 'schedule_cron() puts the six daily jobs on the clock', array_values( $scheduled ), array(
	WPCPM_Ceiling::CRON_SWEEP, WPCPM_Institutions::CRON_PURGE, WPCPM_Institution_Agreement::CRON_DISCARD,
	WPCPM_Institution_Agreement::CRON_REMINDERS, WPCPM_Institution_Invite::CRON_EXPIRE,
	WPCPM_Semester_Report_Screen::CRON_AUTODRAFT,
) );

// The drafting day: nothing whose semester ended before it is drafted by the job, so a site whose
// first run had no such day would draft every semester its rosters hold. It is stored where the job
// is put on the clock, as an add_option() that keeps a day already there, and asked nowhere else.
ck( 'scheduling the drafting job on a site with no drafting day stores today, not autoloaded', array(
	$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] ?? null,
	in_array( array( 'add_option', WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE, false ), $GLOBALS['calls'], true ),
), array( gmdate( 'Y-m-d' ), true ) );

$GLOBALS['cron']                                              = array();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
WPCPM_Institutions::schedule_cron();
ck( 'and on a site that has one, as a reactivation does, keeps it', $GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ], '2026-05-01' );

$GLOBALS['calls']        = array();
$GLOBALS['option_reads'] = array();
WPCPM_Institutions::schedule_cron();
ck( 'with every job on the clock, as on an ordinary load, it schedules nothing and reads no option', array(
	array_values( array_filter( $GLOBALS['calls'], function ( $c ) { return in_array( $c[0], array( 'schedule', 'add_option' ), true ); } ) ),
	$GLOBALS['option_reads'],
), array( array(), array() ) );

// Only the drafting job's own branch asks: the other five missing, and the drafting job on the
// clock, nothing is stored.
$GLOBALS['cron'] = array( WPCPM_Semester_Report_Screen::CRON_AUTODRAFT => time() );
unset( $GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] );
WPCPM_Institutions::schedule_cron();
ck( 'and the other jobs going back on the clock store no day', array_key_exists( WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE, $GLOBALS['opts'] ), false );

$institutions_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );
$boot_body        = substr( $institutions_src, strpos( $institutions_src, 'public function boot()' ), strpos( $institutions_src, 'public function activate()' ) - strpos( $institutions_src, 'public function boot()' ) );
ck( 'and boot() is what hooks it, on init', false !== strpos( $boot_body, "add_action( 'init', array( __CLASS__, 'schedule_cron' )" ), true );
$mentors_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php' );
ck( 'the Mentors module does the same for the hourly call reminders', false !== strpos( $mentors_src, "add_action( 'init', array( 'WPCPM_Mentor_Calls', 'schedule' )" ), true );

$GLOBALS['calls'] = array();
$GLOBALS['head']  = array( 'response' => array( 'code' => 403 ) );
$module->activate();
$names = array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] );
ck( 'activate() probes, refreshes the countries and schedules the sync', array(
	in_array( 'wp_remote_head', $names, true ),
	in_array( 'WPCPM_Countries::refresh', $names, true ),
	in_array( 'WPCPM_Institutions_Sync::activate', $names, true ),
), array( true, true, true ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] = '';
$GLOBALS['calls'] = array();
$module->activate();
ck( 'but does not touch Airtable when nothing is connected', in_array( 'WPCPM_Countries::refresh', array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] ), true ), false );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] = 'pat';

// Off the clock with the plugin, as the Sponsors module takes its own: a daily event with no
// listener is what deactivation would otherwise leave behind. The single events (a report's ask
// queue, an import's next slice) stay: a due event with no listener is dropped, and a reactivation
// inside their minutes picks them up.
$GLOBALS['calls'] = array();
$module->deactivate();
$cleared = array_map( function ( $c ) { return $c[1]; }, array_filter( $GLOBALS['calls'], function ( $c ) { return 'unschedule' === $c[0]; } ) );
ck( 'deactivate() still hands the sync its own, and takes off the clock exactly the jobs schedule_cron() puts on it', array(
	in_array( array( 'WPCPM_Institutions_Sync::deactivate' ), $GLOBALS['calls'], true ),
	array_values( $cleared ),
	count( $GLOBALS['calls'] ),
), array( true, array_values( $scheduled ), 1 + count( $scheduled ) ) );
ck( 'both read the one list, and deactivate() names no job of its own', array(
	false !== strpos( body_of_method( $institutions_src, 'schedule_cron' ), 'self::daily_jobs()' ),
	false !== strpos( body_of_method( $institutions_src, 'deactivate' ), 'self::daily_jobs()' ),
	preg_match( '/CRON_/', body_of_method( $institutions_src, 'deactivate' ) ),
), array( true, true, 0 ) );

$GLOBALS['calls'] = array();
$module->uninstall();
$names = array_map( function ( $c ) { return $c[0] . ( isset( $c[1] ) ? ':' . $c[1] : '' ); }, $GLOBALS['calls'] );
ck( 'uninstall() drops the three options, every delete_all(), the membership stamps and the rows-per-page choice', array(
	in_array( 'delete_option:wpcpm_institutions_index', $names, true ),
	in_array( 'delete_option:wpcpm_countries', $names, true ),
	in_array( 'delete_option:wpcpm_private_probe', $names, true ),
	in_array( 'WPCPM_Roster_Index::delete_all', $names, true ),
	in_array( 'WPCPM_Institution_Agreement::delete_all', $names, true ),
	in_array( 'WPCPM_Institution_Audit::delete_all', $names, true ),
	// The inventory of kept files goes out before the posts that name them are deleted.
	array_search( 'WPCPM_Institution_Agreement::manifest_kept_files', $names, true ) < array_search( 'WPCPM_Institution_Agreement::delete_all', $names, true ),
	// Two delete_all() methods documented as "called on uninstall" that nothing called.
	in_array( 'WPCPM_Institution_Invite::delete_all', $names, true ),
	in_array( 'WPCPM_Institution_Request::delete_all', $names, true ),
	// The institutions' own records (1.123.29) outlive the applications' purge, so this is what removes them.
	in_array( 'WPCPM_Institution_Record::delete_all', $names, true ),
	in_array( 'delete_option:wpcpm_institutions_state', $names, true ) && in_array( 'delete_option:wpcpm_institutions_report', $names, true ) && in_array( 'delete_option:wpcpm_institutions_last_sync', $names, true ) && in_array( 'delete_option:wpcpm_institutions_last_error', $names, true ) && in_array( 'delete_option:wpcpm_institutions_lock', $names, true ),
	in_array( 'delete_metadata:wpcpm_institution_record_id', $names, true ),
	in_array( 'delete_metadata:wpcpm_institution_record_id_was', $names, true ),
	in_array( 'delete_metadata:wpcpm_institution_profile', $names, true ),
	// A manager's rows-per-page choice for the institution accounts, which core keeps as user meta.
	in_array( 'delete_metadata:wpcpm_institutions_per_page', $names, true ),
), array( true, true, true, true, true, true, true, true, true, true, true, true, true, true, true ) );
ck( 'and the lock a patch of the index holds while it rewrites it, which a request that died holding it leaves', in_array( 'delete_option:wpcpm_institutions_index_lock', $names, true ), true );
ck( 'and leaves the signed files where they are', is_file( $base . 'agreements/2026/abc.pdf' ), true );
// The form's page is the site's to keep, but the plugin's note of which page it is goes, and so does
// the stamp of its one-time move to /institution-application/, or a reinstall would never move it.
ck( 'and forgets the application form\'s page and its one-time move', array(
	in_array( 'delete_option:wpcpm_application_page_id', $names, true ),
	in_array( 'delete_option:wpcpm_application_page_moved', $names, true ),
), array( true, true ) );

/*
 * The semester report, on the way out. Both halves hold something an uninstall has to take
 * with it: the reports themselves, which carry students' own words released to one university
 * for one document; the three user meta keys; the ask cron; and the per-report options, which
 * are named after post IDs and so have no name this file could carry a list of.
 */
ck( 'and takes the semester reports and their leftovers with it', array(
	in_array( 'WPCPM_Semester_Report::delete_all', $names, true ),
	in_array( 'WPCPM_Semester_Report_Screen::delete_all', $names, true ),
	in_array( 'unschedule_hook:' . WPCPM_Semester_Report_Screen::CRON_ASK, $names, true ),
	in_array( 'delete_metadata:' . WPCPM_Student_Feedback::META_REPORT_PERMISSIONS, $names, true ),
	in_array( 'delete_metadata:' . WPCPM_Semester_Report_Screen::META_ASKED, $names, true ),
	in_array( 'delete_metadata:' . WPCPM_Semester_Report_Screen::META_STASH, $names, true ),
	in_array( 'unschedule:' . WPCPM_Semester_Report_Screen::CRON_AUTODRAFT, $names, true ),
), array( true, true, true, true, true, true, true ) );

// A report's ask queue and an import's next slice are scheduled with the report's or the batch's
// ID, and a clear that names no arguments clears neither; and a check against the Doc that a
// version before this one could store left its row under a name of its own.
ck( 'and unschedules the two single events whatever their arguments, and forgets a stored check against the Doc', array(
	in_array( 'unschedule_hook:' . WPCPM_Semester_Report_Screen::CRON_ASK, $names, true ),
	in_array( 'unschedule_hook:' . WPCPM_Institution_Import::CRON_TICK, $names, true ),
	in_array( 'delete_option:wpcpm_agreement_drift', $names, true ),
), array( true, true, true ) );

/* ---- one map for every outcome a press here leaves ---------------------- */

echo "\n=== Every outcome a press here leaves is one the screen's map words ===\n";

/**
 * A method's body in a source, from its signature to the brace that closes it at one tab.
 *
 * @param string $src  The source.
 * @param string $name The method.
 * @return string '' when the source has no such method.
 */
function body_of_method( $src, $name ) {
	$at = strpos( (string) $src, 'function ' . $name . '(' );

	if ( false === $at ) {
		return '';
	}

	$body = substr( (string) $src, $at );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

/**
 * The outcomes a source hands to a call, read with PHP's own tokenizer: for each call to `$callee`,
 * the argument at `$position`, and in it each quoted string that is the whole argument or a branch
 * of a ternary that is. An argument holding no such string is kept as code, whitespace dropped, for
 * the check to account for.
 *
 * @param string $code     Source, without its opening tag.
 * @param string $callee   The function or method called, by name.
 * @param int    $position The argument's place, from 0.
 * @return array{0: string[], 1: string[]} The quoted outcomes, and the arguments kept as code.
 */
function outcomes_handed( $code, $callee, $position ) {
	$tokens = array();

	foreach ( token_get_all( '<?php ' . $code ) as $token ) {
		if ( ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG ), true ) ) {
			$tokens[] = $token;
		}
	}

	$quoted  = array();
	$as_code = array();
	$count   = count( $tokens );

	for ( $i = 1; $i < $count - 1; $i++ ) {
		$called = is_array( $tokens[ $i ] ) && T_STRING === $tokens[ $i ][0] && $callee === $tokens[ $i ][1] && '(' === $tokens[ $i + 1 ];

		if ( ! $called || ( is_array( $tokens[ $i - 1 ] ) && T_FUNCTION === $tokens[ $i - 1 ][0] ) ) {
			continue;
		}

		$depth = 0;
		$arg   = 0;
		$piece = array();

		for ( $k = $i + 2; $k < $count; $k++ ) {
			$token = $tokens[ $k ];

			if ( ')' === $token || ']' === $token ) {
				if ( 0 === $depth ) {
					break;
				}
				--$depth;
			}

			if ( ',' === $token && 0 === $depth ) {
				++$arg;
				continue;
			}

			if ( $arg === $position ) {
				$piece[] = array( $token, $depth );
			}

			if ( '(' === $token || '[' === $token ) {
				++$depth;
			}
		}

		$top   = array_values( array_filter( $piece, function ( $part ) { return 0 === $part[1]; } ) );
		$found = array();

		foreach ( $top as $n => $part ) {
			$before = $n > 0 ? $top[ $n - 1 ][0] : null;
			$after  = isset( $top[ $n + 1 ] ) ? $top[ $n + 1 ][0] : null;

			if ( is_array( $part[0] ) && T_CONSTANT_ENCAPSED_STRING === $part[0][0] && in_array( $before, array( null, '?', ':' ), true ) && in_array( $after, array( null, ':' ), true ) ) {
				$found[] = trim( $part[0][1], '\'"' );
			}
		}

		if ( empty( $found ) ) {
			$as_code[] = implode( '', array_map( function ( $part ) { return is_array( $part[0] ) ? $part[0][1] : $part[0]; }, $piece ) );
		}

		$quoted = array_merge( $quoted, $found );
	}

	return array( $quoted, $as_code );
}

/**
 * The keys a method that builds a map of outcomes spells, each as `'key' => array(`.
 *
 * @param string $body The method's body.
 * @return string[]
 */
function outcome_keys( $body ) {
	preg_match_all( "/'([a-z0-9-]+)'\s*=>\s*array\(/", (string) $body, $m );

	return $m[1];
}

// The screen prints a press's outcome from one map, on whichever tab the press comes back to, and an
// outcome the map does not know is taken from the channel and dropped, with nothing said. The
// outcomes are pinned one by one above; this reads them all off the source, by the tab each press
// lands on, and the map's keys off the methods that build it, since this suite stands in for
// several of the classes that hold them. An outcome added to a press without its sentence fails here.
$module_src  = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );
$sync_src    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php' );
$trait_src   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php' );
$table_src   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php' );
$mail_src    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php' );
$agree_src   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
$panel_src   = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php' );
$request_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php' );
$pdf_src     = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-pdf-check.php' );

// The map, as the screen builds it: the three every sync screen shares under the screen's own
// (`render_status_notice()`), and the screen's own outcomes merged with the agreements', the
// queue's, the requests' and the invitations', the invitations' `error` on the Accounts tab alone.
$map_body    = body_of_method( $module_src, 'outcome_messages' );
$shared_keys = array_merge(
	outcome_keys( body_of_method( $sync_src, 'sync_messages' ) ),
	outcome_keys( $map_body ),
	outcome_keys( body_of_method( $panel_src, 'messages' ) ),
	outcome_keys( body_of_method( $module_src, 'queue_messages' ) ),
	outcome_keys( body_of_method( $request_src, 'messages' ) )
);
$invite_keys = array_merge( outcome_keys( body_of_method( $trait_src, 'accounts_messages' ) ), outcome_keys( body_of_method( $mail_src, 'invite_notices' ) ) );
$keys_on     = function ( $tab ) use ( $shared_keys, $invite_keys ) {
	return array_values( array_unique( array_merge( $shared_keys, 'accounts' === $tab ? $invite_keys : array_diff( $invite_keys, array( 'error' ) ) ) ) );
};

ck( 'the map is built as read here: the screen\'s own outcomes with the agreements\', the queue\'s, the requests\' and the invitations\', the invitations\' error on the Accounts tab alone, printed over the three every sync screen shares',
	array(
		false !== strpos( $map_body, 'WPCPM_Institution_Panel::messages()' ),
		false !== strpos( $map_body, 'self::queue_messages()' ),
		false !== strpos( $map_body, 'WPCPM_Institution_Request::messages()' ),
		false !== strpos( $map_body, '$invitations = $this->accounts_messages();' ),
		1 === preg_match( '/if \( self::TAB_ACCOUNTS !== \$tab \) \{\s*unset\( \$invitations\[\'error\'\] \);/', $map_body ),
		false !== strpos( body_of_method( $module_src, 'render_admin_page' ), '$this->render_status_notice( $this->outcome_messages( $tab ) );' ),
		false !== strpos( body_of_method( $sync_src, 'render_status_notice' ), 'array_merge( static::sync_messages(), $extra )' ),
		false !== strpos( body_of_method( $trait_src, 'accounts_messages' ), 'WPCPM_Mail::invite_notices()' ),
	),
	array( true, true, true, true, true, true, true, true ) );

// Where each press lands: a handler of the screen's own comes back to the tab its form names, the
// queue's decisions to the screen's own address, the queue, or to the application opened on it when
// the question or the reason is refused for its own words; the ticked accounts, a row's invitation
// and the invitations card's Stop to the Accounts tab, as do the upload and the withdraw on an
// institution's Manage members view, by their referer; a request's decision posted here to the
// queue. The Agreements tab has no press of its own since 1.123.26.
$handler_tabs = array(
	'handle_probe'         => 'sync',
	'handle_provision_one' => 'accounts',
	'handle_bulk_invite'   => 'accounts',
	'handle_approve'       => 'queue',
	'handle_info'          => 'queue',
	'handle_reject'        => 'queue',
	'handle_spam'          => 'queue',
	'handle_reopen'        => 'queue',
	'handle_resend_link'   => 'queue',
	'handle_purge'         => 'queue',
);
$left         = array_fill_keys( array( 'queue', 'sync', 'accounts', 'agreements' ), array() );
$as_code      = array();
$unplaced     = array();
$lands        = function ( $tab, $where, array $handed ) use ( &$left, &$as_code ) {
	$left[ $tab ] = array_merge( $left[ $tab ], $handed[0] );

	foreach ( $handed[1] as $code ) {
		$as_code[] = $where . ': ' . $code;
	}
};

preg_match_all( '/public function (handle_[a-z_]+)\(/', $module_src, $module_handlers );
foreach ( $module_handlers[1] as $handler ) {
	$body   = body_of_method( $module_src, $handler );
	// Each reader's quoted outcomes with the others', and its arguments kept as code with theirs.
	$handed = array( array(), array() );
	foreach ( array( outcomes_handed( $body, 'redirect_back', 0 ), outcomes_handed( $body, 'leave', 1 ), outcomes_handed( $body, 'back_to_application', 0 ) ) as $read ) {
		$handed = array( array_merge( $handed[0], $read[0] ), array_merge( $handed[1], $read[1] ) );
	}

	if ( empty( $handed[0] ) && empty( $handed[1] ) ) {
		continue;
	}

	if ( ! isset( $handler_tabs[ $handler ] ) ) {
		$unplaced[] = $handler;
		continue;
	}

	$lands( $handler_tabs[ $handler ], $handler, $handed );
}

foreach ( array( 'handle_sync', 'handle_cancel' ) as $handler ) {
	$lands( 'sync', $handler, outcomes_handed( body_of_method( $sync_src, $handler ), 'redirect_back', 0 ) );
}
foreach ( array( 'handle_list_form', 'invite_selected', 'handle_invite' ) as $method ) {
	$lands( 'accounts', $method, outcomes_handed( body_of_method( $trait_src, $method ), 'leave', 1 ) );
}
$lands( 'accounts', 'handle_stop', outcomes_handed( body_of_method( $mail_src, 'handle_stop' ), 'set', 1 ) );
$lands( 'accounts', 'handle_upload', outcomes_handed( body_of_method( $agree_src, 'handle_upload' ), 'bounce', 0 ) );
$lands( 'accounts', 'handle_withdraw', outcomes_handed( body_of_method( $agree_src, 'handle_withdraw' ), 'bounce', 0 ) );
$lands( 'queue', 'handle_resolve', outcomes_handed( body_of_method( $request_src, 'handle_resolve' ), 'finish', 0 ) );

// The outcomes handed over as code, each read where it is decided, as that code spells them: the
// approval's refusals by their codes, a map's values and its default; Create account on the ticked
// institutions, which the list's form carries out through the Institutions table's action, and the
// ticked accounts' invitations, each the first of the pair it returns; a row's invitation, the string
// it returns; a PDF the scan refused, its map's values.
$values   = "/(?:=>|:)\s*'([a-z][a-z0-9-]*)'\s*[,;]/";
$pairs    = "/(?<![a-z_])array\(\s*'([a-z][a-z0-9-]*)'\s*,/";
$returned = "/(?:return\s+|[?:]\s*)'([a-z][a-z0-9-]*)'\s*[;:]/";
$decided  = array(
	'handle_approve: self::approval_outcome($result->get_error_code())' => array( 'queue', body_of_method( $module_src, 'approval_outcome' ), $values ),
	'handle_list_form: $status'                                         => array( 'accounts', body_of_method( $module_src, 'provision_ticked' ), $pairs ),
	'invite_selected: $status'                                          => array( 'accounts', body_of_method( $table_src, 'queue_ticked' ), $pairs ),
	'handle_invite: WPCPM_Mail::invite_outcome($result)'                => array( 'accounts', body_of_method( $mail_src, 'invite_outcome' ), $returned ),
	'handle_upload: $scan[\'reason\']'                                  => array( 'accounts', preg_match( '/const SCAN_REFUSALS = array\((.*?)\);/s', $pdf_src, $refusals ) ? $refusals[1] : '', $values ),
);
foreach ( $decided as $code => $where ) {
	preg_match_all( $where[2], $where[1], $decided_here );
	$left[ $where[0] ] = array_merge( $left[ $where[0] ], $decided_here[1] );
}

$unworded = array();
foreach ( $left as $tab => $statuses ) {
	foreach ( array_unique( $statuses ) as $status ) {
		if ( ! in_array( $status, $keys_on( $tab ), true ) ) {
			$unworded[] = $tab . ': ' . $status;
		}
	}
}

ck( 'every outcome a press on the screen leaves on its channel, read off the source by the tab it lands on, is a key of the map that tab prints from, and every press is placed',
	array( $unworded, $unplaced, $as_code ),
	array( array(), array(), array_keys( $decided ) ) );
ck( 'and the read reaches every source: a sample of each press\'s outcomes is among them',
	array_values(
		array_diff(
			array( 'queue: app-reopened', 'queue: app-member-taken', 'queue: app-question-loss', 'queue: app-reason-long', 'queue: request-done', 'queue: request-declined-loss', 'queue: request-done-long', 'queue: error', 'sync: probed', 'sync: cancelled', 'accounts: provision-already', 'accounts: provision-blocked', 'accounts: invites-stopped', 'accounts: invite-too-soon', 'accounts: invites-none', 'accounts: agreement-launch', 'accounts: agreement-withdrawn' ),
			call_user_func_array(
				'array_merge',
				array_map(
					function ( $tab ) use ( $left ) {
						return array_map( function ( $status ) use ( $tab ) { return $tab . ': ' . $status; }, $left[ $tab ] );
					},
					array_keys( $left )
				)
			)
		)
	),
	array() );

// The Pipeline's two DRY points beside the shared sort. The SPAM stage has a name on the class, and the Link
// picker and the uncounted list both say it through that name; the Link control's institution field is the
// class's own argument name, in the form that draws it and in the handler that reads it.
$pipeline_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );
$choices_body = body_of_method( $pipeline_src, 'link_choices' );
$form_body    = body_of_method( $pipeline_src, 'render_link_form' );
$handler_body = body_of_method( $pipeline_src, 'handle_link' );
ck( 'the SPAM stage is a constant of the class, and the uncounted stages begin with it', array( WPCPM_Institutions::STAGE_SPAM, WPCPM_Institutions::UNCOUNTED_STAGES[0], false !== strpos( $pipeline_src, 'const UNCOUNTED_STAGES = array( self::STAGE_SPAM,' ) ), array( 'SPAM', 'SPAM', true ) );
ck( 'link_choices() leaves it out by that constant, and has no literal "SPAM" in it', array( false !== strpos( $choices_body, 'self::STAGE_SPAM' ), false !== strpos( $choices_body, "'SPAM'" ), '' !== $choices_body ), array( true, false, true ) );
ck( 'render_link_form() names its institution field with ARG_INSTITUTION, whose value is the field the handler has always read', array(
	false !== strpos( $form_body, "'name'    => self::ARG_INSTITUTION" ),
	false !== strpos( $form_body, "'wpcpm_institution'" ),
	WPCPM_Institutions::ARG_INSTITUTION,
), array( true, false, 'wpcpm_institution' ) );
ck( 'and the handler that reads the press reads the same constant', array( '' !== $handler_body, false !== strpos( $handler_body, 'posted_text( self::ARG_INSTITUTION )' ), false !== strpos( $handler_body, "'wpcpm_institution'" ) ), array( true, true, false ) );

ck( 'and nothing asked a stand-in for anything it does not model', $GLOBALS['unmodeled'], array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
