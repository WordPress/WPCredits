<?php
/**
 * WPCPM_Accounts_Table: one audience's accounts as a WordPress list table, and what every
 * audience's table shares.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The table is WordPress's own: a checkbox column, then the audience's columns in the order it
 *   gives them, a row per account, and the row actions under the first column, drawn as core's own
 *   lists draw them (`handle_row_actions()`), so a row has one "Show more details" toggle, a row
 *   with no action too. The Students screen's list was drawn by hand with a form in every row until
 *   1.118.0; this is one list, one form. The columns a person hid under Screen Options stay hidden,
 *   the primary one never, and Screen Options is never offered the primary column to hide, whatever
 *   its key.
 * - The views count from the stamps an invitation leaves, read the way `WPCPM_Mail::never_invited()`
 *   reads them: any of them there is Invited and none of them Never invited, which is the reading
 *   the invitations card counts by, since an account has one password, and a stamp of 0 is still a
 *   stamp. So an account in two audiences, stamped under either of its roles, is Invited on the list
 *   of each, and its row and the bulk actions read it so. The counts are the whole audience, as
 *   WordPress's own views are, not the search.
 * - A row's invitation is a nonce link to the audience's own invite handler carrying the account and
 *   the tab it came from, and it says Resend invite for anybody the Invited view counts. An audience
 *   adds its own actions beside it through `row_actions_for()`. Every value the table prints is
 *   escaped, the account's name in its checkbox label and every link it draws among them.
 * - The bulk actions are the two invitations, in the table's one form, under the table's own nonce.
 * - What they queue is worked out once, in the base, for every audience (`queue_ticked()`), read
 *   through the audience's own class for its role and its stamp. Send invite queues the ticked
 *   accounts never invited through `WPCPM_Mail::queue_invites()`, the invitations card's own way
 *   in; Resend invite queues each ticked account invited before through
 *   `WPCPM_Mail::queue_invite()`, and leaves out, and counts, anybody sent an invitation in the
 *   last fifteen minutes. An account outside the role is left out, a repeat counts once, and the
 *   ticked accounts are read in one go. What came of it is the outcome and its detail:
 *   `invites-queued` with how many, or `invites-none` with why, each saying which of the two was
 *   pressed. The queue here is a stand-in that notes each way in it is asked to take
 *   (bin/test-students-screen.php and bin/test-mentors-screen.php make the same presses through the
 *   real one), and the Students module is read, with the screen plumbing it shares with every
 *   audience's module (`WPCPM_Accounts_Screen`), to show its list calls the base rather than keeping
 *   a copy of its own.
 * - The words for what came of it are the base's too (`selected_sentence()`), one wording for every
 *   audience's list: how many were queued, by which of the two, or why none was, and how many a
 *   Resend invite left out for the fifteen minutes. The Students and Mentors modules are read, with
 *   the plumbing they share, to show each prints them rather than a wording of its own, loading the
 *   tables for them first.
 * - The name, the username and a row's Edit are the base's for every audience, drawn alike: the name
 *   in bold, to the account's editor when the person looking may open it, and plain otherwise, and
 *   the username as code. And the screen plumbing the Students and Mentors modules held twice is one
 *   trait now (`WPCPM_Accounts_Screen`): its methods are read from its file, and neither module may
 *   declare one of them again.
 * - A record view: an audience that keeps records without an account, as the Institutions and
 *   Sponsors screens do, lists them on a view of their own, after the three invitation views and
 *   counted apart from them, its label singular or plural by that count, with its own columns, sorts
 *   and bulk actions, a record a row, paged by the base, each record's checkbox posting its ID under
 *   `records[]`. A search narrows the rows and not the view's count; a row without an ID draws no
 *   checkbox and one without a name is labeled by its ID; whatever is handed back that is not a row
 *   is dropped. On a list that has one, the three views are what they are without it; on the base
 *   every seam answers none, so the lists that keep no records draw their accounts alone. A record
 *   row's own actions (`record_row_actions()`) are drawn under its primary cell in the markup an
 *   account's are, one toggle a row; the base gives a record row none, so it draws the toggle alone.
 * - A bulk action the table carries out itself (`owns_action()`, `handle_action()`): the screen
 *   plumbing every audience's module shares (`WPCPM_Accounts_Screen`) asks the table about any
 *   action but the two invitations, by its name alone, checks the capability and then the list's
 *   nonce, lets the table carry it out and comes back to the list with its outcome flashed, so the
 *   plumbing holds no branch for any one audience's action; an action no table owns still does
 *   nothing. The page the press comes back to prints that outcome from its map, in the table's own
 *   words for it (`action_sentence()`) or the map's, never the invitations'. And the tab a screen
 *   opens on is its first: Accounts on the Students, Mentors and Administrators screens, and the
 *   queue on a screen whose first tab is its queue. All of it is read on a stand-in module drawn in
 *   tabs, on the plumbing itself.
 * - The per-page screen option: its name, its default of 20, the choice core may save (1 to 999)
 *   and the choice read back through the option's filter, as core reads it. The audience wires
 *   both, the option from its screen's load hook and the save through a filter its module adds at
 *   boot (bin/test-students-screen.php and bin/test-mentors-screen.php pin the two screens'), so
 *   the base offers no static a table would have to be loaded for before the screen loads it.
 * - A list sorts only by what the audience declares, matched as `sanitize_key()` leaves both sides;
 *   any other `orderby` is ignored, because it goes into a query. The name sort asks for the ID
 *   after the name, so two accounts of one name keep one order from page to page and neither is
 *   shown twice while the other is on no page. The search is `WP_User_Query`'s contains search,
 *   over name, username and email, and the list asks for its total to be counted.
 * - The plugin declares no list table as it loads: its loader `wpcpm_load_accounts_tables()` loads
 *   the base, the Students table, the Mentors table, the Administrators table, the Institutions table
 *   and the Sponsors table when a screen first needs one, so the front end, REST and cron never load
 *   core's list table for them. The
 *   base loads core's list table itself when it is not there yet: core loads it for wp-admin
 *   requests, after plugins have loaded.
 *
 * The table is drawn by the stand-in in bin/stubs/class-wp-list-table.php, whose markup is close to
 * core's and is not core's; the checks read what a screen depends on, not core's exact markup.
 *
 * Run from the plugin root:  php bin/test-accounts-table.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

// The runs that load the class the way a site does, in a process of their own, before anything
// below stands in for WordPress: a class defined here could not be undefined again.
if ( isset( $argv[1] ) && in_array( $argv[1], array( 'child-missing', 'child-present' ), true ) ) {
	wpcpm_accounts_table_child( $argv[1], isset( $argv[2] ) ? (string) $argv[2] : '' );
	exit( 0 );
}

if ( isset( $argv[1] ) && 'child-plugin' === $argv[1] ) {
	wpcpm_accounts_table_plugin_child( isset( $argv[2] ) ? (string) $argv[2] : '' );
	exit( 0 );
}

/**
 * The plugin loaded as a site loads it: its main file, with the WordPress functions it calls as it
 * loads, then its lazy loader, saying what each step declared.
 *
 * `add_action()` is this file's own stand-in, declared already, as a file's functions are before it
 * runs; it records the hook and does nothing else. The other four the main file calls are declared
 * here, because nothing else in this suite needs them.
 *
 * @param string $abspath The directory standing in for the WordPress root, holding core's list table.
 */
function wpcpm_accounts_table_plugin_child( $abspath ) {
	define( 'ABSPATH', rtrim( $abspath, '/' ) . '/' );

	function plugin_dir_path( $file ) {
		return rtrim( dirname( $file ), '/' ) . '/';
	}
	function plugin_dir_url( $file ) {
		return 'https://example.test/wp-content/plugins/wpcredits-program-manager/';
	}
	function register_activation_hook() {}
	function register_deactivation_hook() {}

	require dirname( __DIR__ ) . '/wpcredits-program-manager.php';

	$declared = function () {
		return array( class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Students_Table', false ), class_exists( 'WPCPM_Mentors_Table', false ), class_exists( 'WPCPM_Administrators_Table', false ), class_exists( 'WPCPM_Institutions_Table', false ), class_exists( 'WPCPM_Sponsors_Table', false ) );
	};

	$as_loaded = $declared();
	$lazy      = function_exists( 'wpcpm_load_accounts_tables' );

	if ( $lazy ) {
		wpcpm_load_accounts_tables();
	}

	$core = class_exists( 'WP_List_Table', false ) ? new ReflectionClass( 'WP_List_Table' ) : null;

	echo json_encode(
		array(
			'loaded' => $as_loaded,
			'lazy'   => $lazy,
			'after'  => $declared(),
			'from'   => $core ? (string) $core->getFileName() : '',
		)
	);
}

/**
 * One child run: define ABSPATH, maybe define core's class first, load the base, say what happened.
 *
 * @param string $case      `child-missing` (core's class is not loaded) or `child-present` (it is).
 * @param string $abspath   The directory standing in for the WordPress root.
 */
function wpcpm_accounts_table_child( $case, $abspath ) {
	define( 'ABSPATH', rtrim( $abspath, '/' ) . '/' );

	if ( 'child-present' === $case ) {
		// Core's list table already loaded, as it is on core's own list screens. ABSPATH holds no
		// wp-admin at all, so a second require would be a fatal and this run would print nothing.
		class WP_List_Table {} // Declared when this line runs, never in the parent run.
	}

	require dirname( __DIR__ ) . '/includes/class-wpcpm-accounts-table.php';

	$core = new ReflectionClass( 'WP_List_Table' );

	echo json_encode(
		array(
			'base' => class_exists( 'WPCPM_Accounts_Table', false ),
			'from' => (string) $core->getFileName(),
		)
	);
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once __DIR__ . '/stubs/temp-dir.php';
// How a meta query is read, which this suite's stand-in user query shares with the screen suites'.
require_once __DIR__ . '/stubs/meta-matcher.php';

$GLOBALS['uid']            = 1;
$GLOBALS['users']          = array(); // User ID => WP_User.
$GLOBALS['umeta']          = array(); // User ID => meta key => value.
$GLOBALS['hooks']          = array(); // Hook => each callback added to it, with its argument count.
$GLOBALS['screen_options'] = array(); // Option => the arguments add_screen_option() was given.
$GLOBALS['queries']        = array(); // Every WP_User_Query's arguments, in order.
$GLOBALS['nonce_fields']   = array(); // The action of every nonce field printed.
$GLOBALS['translations']   = array(); // Text => a translation, for the escaping checks.
$GLOBALS['opts']           = array(); // Option => value: the invitation queue, as the stand-in WPCPM_Mail keeps it.
$GLOBALS['reads']          = array(); // Every read of an account the bulk invitation makes: the priming, each lookup.
$GLOBALS['queue_calls']    = array(); // Each way into the invitation queue taken, with the accounts it was handed.
$GLOBALS['unmodeled']      = array(); // Anything asked of a stand-in that it does not model.
$GLOBALS['screen']         = null;    // What convert_to_screen() answers: null for a table with no screen.
$GLOBALS['no_editor']      = false;   // Whether the person looking may not open the accounts' editor.
$GLOBALS['can_manage']     = true;    // Whether the person looking holds the program's capability.
$GLOBALS['events']         = array(); // What a press in a list reached, in order: the capability and nonce asked about, a table's own action.
$GLOBALS['wp_version']     = '7.1';   // The WordPress version get_bloginfo() reports: the one the plugin is tested up to.

/* ---- WordPress, as far as the table reaches ------------------------------ */

class WP_User {
	public $ID = 0, $user_login = '', $user_email = '', $display_name = '', $roles = array();
	public function __construct( $id = 0, $login = '', $name = '', $roles = array() ) {
		$this->ID           = (int) $id;
		$this->user_login   = $login;
		$this->user_email   = $login . '@example.test';
		$this->display_name = $name;
		$this->roles        = $roles;
	}
}

/** An error, as far as the bulk invitation asks one anything: whether it is one (`is_wp_error()`). */
class WP_Error {
	public $code = '';
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = (string) $code;
	}
}

/**
 * The accounts query, answered over the fixture users the way WordPress answers it.
 *
 * The role; `meta_query` clauses as bin/stubs/meta-matcher.php reads them, on whether a key is
 * there; a search with a wildcard at both ends as "contains", over the columns named, without
 * regard to case as MySQL compares; the order, one `orderby` with the query's `order` or the array
 * form, each key an orderby and its value that key's order, a key settling what the one before it
 * leaves tied; `number` and `offset`; `fields` of `ID`; the total counted only when `count_total`
 * asks, as WordPress counts it, 0 otherwise. Rows that tie on every key the order names come back
 * as MySQL is free to return them, in any order and in another for another LIMIT: here the lowest
 * ID first on a first page and the highest first on any later one, the least kind reading, so a
 * list that leaves a tie to chance loses an account between two pages here as it can on a site.
 * Only what the table asks is modeled, and anything else it asks is kept apart and fails a check
 * below, so nothing passes by being quietly ignored.
 */
class WP_User_Query {
	private $results = array();
	private $total   = 0;

	public function __construct( $args = array() ) {
		$GLOBALS['queries'][] = $args;

		foreach ( array_keys( $args ) as $key ) {
			if ( ! in_array( $key, array( 'role', 'number', 'offset', 'orderby', 'order', 'search', 'search_columns', 'meta_query', 'fields', 'count_total' ), true ) ) {
				$GLOBALS['unmodeled'][] = 'WP_User_Query argument ' . $key;
			}
		}

		$found = array();

		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( ! empty( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) {
				continue;
			}

			if ( wpcpm_stub_meta_matches( $id, isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() ) && wpcpm_stub_search_matches( $user, $args ) ) {
				$found[] = $user;
			}
		}

		$fields = array( 'display_name' => 'display_name', 'login' => 'user_login', 'user_login' => 'user_login', 'email' => 'user_email', 'user_email' => 'user_email', 'ID' => 'ID' );
		$keys   = array();

		foreach ( wpcpm_stub_orderby( $args ) as $orderby => $desc ) {
			if ( ! isset( $fields[ $orderby ] ) ) {
				$GLOBALS['unmodeled'][] = 'WP_User_Query orderby ' . $orderby;
				$orderby                = 'login';
			}

			$keys[] = array( $fields[ $orderby ], $desc );
		}

		$later = isset( $args['offset'] ) && (int) $args['offset'] > 0;

		usort(
			$found,
			function ( $a, $b ) use ( $keys, $later ) {
				foreach ( $keys as $key ) {
					$field = $key[0];
					$order = is_int( $a->$field ) ? $a->$field <=> $b->$field : strcasecmp( $a->$field, $b->$field );

					if ( 0 !== $order ) {
						return $key[1] ? -$order : $order;
					}
				}

				return $later ? $b->ID <=> $a->ID : $a->ID <=> $b->ID;
			}
		);

		// WordPress counts the matches only when asked, and reports 0 otherwise.
		$this->total = ( ! isset( $args['count_total'] ) || $args['count_total'] ) ? count( $found ) : 0;

		if ( isset( $args['number'] ) && $args['number'] > 0 ) {
			$found = array_slice( $found, isset( $args['offset'] ) ? (int) $args['offset'] : 0, (int) $args['number'] );
		}

		if ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) {
			$found = array_map(
				function ( $user ) {
					return $user->ID;
				},
				$found
			);
		}

		$this->results = array_values( $found );
	}

	public function get_results() {
		return $this->results;
	}

	public function get_total() {
		return $this->total;
	}
}

/**
 * The order a query asks for, as WordPress reads it: orderby => whether it runs Z to A. One
 * `orderby` takes the query's `order`; in the array form each key takes its own value, ASC or, for
 * anything else, DESC, as WordPress's `parse_order()` has it. A flat list of orderbys is not asked
 * for, and not modeled.
 *
 * @param array $args The query's arguments.
 * @return array<string, bool>
 */
function wpcpm_stub_orderby( array $args ) {
	$asked = isset( $args['orderby'] ) ? $args['orderby'] : 'login';

	if ( ! is_array( $asked ) ) {
		return array( (string) $asked => isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) );
	}

	$keys = array();

	foreach ( $asked as $orderby => $order ) {
		if ( is_int( $orderby ) ) {
			$GLOBALS['unmodeled'][] = 'WP_User_Query orderby as a flat list';
			continue;
		}

		$keys[ $orderby ] = 'ASC' !== strtoupper( (string) $order );
	}

	return $keys;
}

/**
 * Whether an account matches the query's search, when it has one.
 *
 * @param WP_User $user The account.
 * @param array   $args The query's arguments.
 * @return bool
 */
function wpcpm_stub_search_matches( $user, array $args ) {
	if ( ! isset( $args['search'] ) || '' === trim( $args['search'] ) ) {
		return true;
	}

	$search = trim( $args['search'] );

	if ( '*' !== substr( $search, 0, 1 ) || '*' !== substr( $search, -1 ) || empty( $args['search_columns'] ) ) {
		$GLOBALS['unmodeled'][] = 'a search that is not "contains" over named columns: ' . $search;
		return true;
	}

	$term = trim( $search, '*' );

	foreach ( (array) $args['search_columns'] as $column ) {
		if ( ! in_array( $column, array( 'user_login', 'user_email', 'display_name' ), true ) ) {
			$GLOBALS['unmodeled'][] = 'search column ' . $column;
			continue;
		}

		if ( false !== stripos( (string) $user->$column, $term ) ) {
			return true;
		}
	}

	return false;
}

function __( $text, $domain = 'default' ) {
	return isset( $GLOBALS['translations'][ $text ] ) ? $GLOBALS['translations'][ $text ] : $text;
}
function _n( $single, $plural, $number, $domain = 'default' ) {
	return __( 1 === (int) $number ? $single : $plural, $domain );
}
/**
 * Core's plural held for later, as `_n_noop()` holds it: both forms and the domain, translated once
 * the count is known (`translate_nooped_plural()`).
 *
 * @param string      $singular The singular.
 * @param string      $plural   The plural.
 * @param string|null $domain   The text domain.
 * @return array
 */
function _n_noop( $singular, $plural, $domain = null ) {
	return array(
		0          => $singular,
		1          => $plural,
		'singular' => $singular,
		'plural'   => $plural,
		'context'  => null,
		'domain'   => $domain,
	);
}
function translate_nooped_plural( $nooped_plural, $count, $domain = 'default' ) {
	return _n( $nooped_plural['singular'], $nooped_plural['plural'], $count, $nooped_plural['domain'] ? $nooped_plural['domain'] : $domain );
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
}
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( __( $text, $domain ) );
}
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}
/**
 * Core's escaping for a URL on the page: what a URL cannot hold is dropped, `&` and `'` become entities.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	$url = str_replace( ' ', '%20', ltrim( (string) $url ) );
	$url = preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );
	$url = str_replace( array( '&#038;', '&amp;' ), '&', $url );

	return str_replace( array( '&', "'" ), array( '&#038;', '&#039;' ), $url );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals );
}
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}
/**
 * Core's add_query_arg(), both shapes: an array of arguments, or one key and its value. False removes.
 *
 * @param mixed ...$args The arguments, then the URL.
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$set = $args[0];
		$url = isset( $args[1] ) ? (string) $args[1] : '';
	} else {
		$set = array( $args[0] => $args[1] );
		$url = isset( $args[2] ) ? (string) $args[2] : '';
	}

	$parts = explode( '?', $url, 2 );
	$query = array();

	if ( isset( $parts[1] ) ) {
		parse_str( $parts[1], $query );
	}

	foreach ( $set as $key => $value ) {
		if ( false === $value ) {
			unset( $query[ $key ] );
		} else {
			$query[ $key ] = $value;
		}
	}

	return $parts[0] . ( empty( $query ) ? '' : '?' . http_build_query( $query ) );
}
function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . substr( md5( (string) $action ), 0, 10 );
}
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	return esc_html( add_query_arg( $name, wp_create_nonce( $action ), $url ) );
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$GLOBALS['nonce_fields'][] = $action;
	$field                     = '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';

	if ( $display ) {
		echo $field;
	}

	return $field;
}
function get_current_user_id() {
	return (int) $GLOBALS['uid'];
}
function get_user_option( $option, $user = 0 ) {
	$user = $user ? (int) $user : get_current_user_id();

	return isset( $GLOBALS['umeta'][ $user ][ $option ] ) ? $GLOBALS['umeta'][ $user ][ $option ] : false;
}
function metadata_exists( $type, $id, $key ) {
	return 'user' === $type && isset( $GLOBALS['umeta'][ (int) $id ] ) && array_key_exists( $key, $GLOBALS['umeta'][ (int) $id ] );
}
function get_user_by( $field, $value ) {
	if ( 'id' !== $field ) {
		$GLOBALS['unmodeled'][] = 'get_user_by() by ' . $field;
	}

	$GLOBALS['reads'][] = 'user ' . $value;

	return isset( $GLOBALS['users'][ (int) $value ] ) ? $GLOBALS['users'][ (int) $value ] : false;
}
function get_edit_user_link( $id ) {
	return $GLOBALS['no_editor'] ? '' : 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id;
}
/**
 * Core's priming of accounts and their meta, two queries whatever the count: kept as one read.
 *
 * @param int[] $ids User IDs.
 */
function cache_users( $ids ) {
	$GLOBALS['reads'][] = 'cache ' . implode( ',', (array) $ids );
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = array( $callback, $accepted_args, $priority );

	return true;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_action( $hook, $callback, $priority, $accepted_args );
}
/**
 * Take a callback off a hook, as WordPress does: the same callback at the same priority.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback The callback.
 * @param int      $priority Its priority.
 * @return bool Whether it was there.
 */
function remove_filter( $hook, $callback, $priority = 10 ) {
	$kept  = array();
	$found = false;

	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $added ) {
		if ( $added[0] === $callback && (int) $added[2] === (int) $priority ) {
			$found = true;
			continue;
		}

		$kept[] = $added;
	}

	$GLOBALS['hooks'][ $hook ] = $kept;

	return $found;
}
/**
 * Run the filters added to a hook, in the order added, each handed as many arguments as it takes.
 *
 * @param string $hook     Hook name.
 * @param mixed  $value    The value to filter.
 * @param mixed  ...$args  The rest of the caller's arguments.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $filter ) {
		$value = call_user_func_array( $filter[0], array_slice( array_merge( array( $value ), $args ), 0, $filter[1] ) );
	}

	return $value;
}
/**
 * The screen a table is built on: whatever the check planted, or none.
 *
 * @param mixed $hook_name The screen asked for; the checks plant the one they want instead.
 * @return object|null
 */
function convert_to_screen( $hook_name ) {
	return $GLOBALS['screen'];
}
function add_screen_option( $option, $args = array() ) {
	$GLOBALS['screen_options'][ $option ] = $args;
}
/**
 * What core says of the site, as far as the screen plumbing asks: its version, which core keeps in
 * `$wp_version`, and which says whether core names a list's Apply button (it does from 6.7 on).
 * Here the version the plugin is tested up to (readme.txt), unless a check sets another. Anything
 * else asked is kept apart and fails the suite's check of what the stand-ins do not model.
 *
 * @param string $show   What is asked.
 * @param string $filter Core's display filter, not modeled.
 * @return string
 */
function get_bloginfo( $show = '', $filter = 'raw' ) {
	if ( 'version' === $show ) {
		return (string) $GLOBALS['wp_version'];
	}

	$GLOBALS['unmodeled'][] = 'get_bloginfo ' . $show;

	return '';
}

/** What wp_safe_redirect() does here: it stops the press and carries the address it was sending the browser to. */
class WPCPM_Test_Redirect extends Exception {}

/** What wp_die() does here, a failed nonce check's among them: it stops the press and carries what it said. */
class WPCPM_Test_Death extends Exception {}

/**
 * Whether the person looking holds a capability: as the check says. Each question is kept with
 * the other events, so a check can see what a press asked first.
 *
 * @param string $capability The capability.
 * @return bool
 */
function current_user_can( $capability ) {
	$GLOBALS['events'][] = 'capability ' . $capability;

	return (bool) $GLOBALS['can_manage'];
}
/**
 * Core's nonce check, which dies with core's sentence when the request carries anything but the
 * nonce for this action. Each action asked about is kept with the other events, in order.
 *
 * @param string $action    The nonce action.
 * @param string $query_arg Where the request carries it.
 * @return int
 */
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['events'][] = 'nonce ' . $action;

	if ( ! isset( $_REQUEST[ $query_arg ] ) || wp_create_nonce( $action ) !== $_REQUEST[ $query_arg ] ) {
		throw new WPCPM_Test_Death( 'The link you followed has expired.' );
	}

	return 1;
}
function wp_die( $message = '', $title = '', $args = array() ) {
	throw new WPCPM_Test_Death( is_string( $message ) ? $message : 'died' );
}
function wp_safe_redirect( $location ) {
	throw new WPCPM_Test_Redirect( (string) $location );
}
/**
 * One user meta value, as WordPress reads it: the value kept, or '' for a single one never written.
 * The flash a press leaves is kept here, for the person looking (`WPCPM_Flash`).
 *
 * @param int    $user_id User ID.
 * @param string $key     Meta key.
 * @param bool   $single  Whether one value is asked for.
 * @return mixed
 */
function get_user_meta( $user_id, $key = '', $single = false ) {
	if ( isset( $GLOBALS['umeta'][ (int) $user_id ] ) && array_key_exists( $key, $GLOBALS['umeta'][ (int) $user_id ] ) ) {
		return $GLOBALS['umeta'][ (int) $user_id ][ $key ];
	}

	return $single ? '' : array();
}
function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['umeta'][ (int) $user_id ][ $key ] = $value;

	return true;
}
// The flash slashes what it writes for core's user meta, which unslashes it; this one keeps what it
// is handed, so the slash is the identity here (bin/test-flash.php holds the flash to core's).
function wp_slash( $value ) {
	return $value;
}
function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['umeta'][ (int) $user_id ][ $key ] );

	return true;
}

require_once __DIR__ . '/stubs/class-wp-list-table.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once __DIR__ . '/stubs/stamps.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';

// The screen plumbing every audience's module shares, the module base it builds on and the flash
// and the way back a press leaves through, for the checks that press a list and read the tab a
// screen opens on; and the three modules drawn on it, whose tabs those checks read too.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php';

// The plugin's lazy loader, which the plumbing calls before it words an outcome: the tables are
// loaded above, so it has nothing to do here. Declared as the run reaches this line, never as the
// file is read, because the run that loads the plugin as a site does declares the plugin's own.
if ( ! function_exists( 'wpcpm_load_accounts_tables' ) ) {
	/** Nothing to load: the suite loads the tables itself. */
	function wpcpm_load_accounts_tables() {}
}

/* ---- the invitation queue, as far as the bulk invitation reaches it ------ */

// Declared inside a condition, so PHP declares it when the run reaches this line: a class written
// at the top level of a file exists before the file's first line runs, and the run above that
// loads the plugin as a site does requires the plugin's own WPCPM_Mail.
if ( ! class_exists( 'WPCPM_Mail', false ) ) {
	/**
	 * Who is waiting, the queue's two ways in and the fifteen-minute guard, answered as WPCPM_Mail
	 * answers them, the queue kept in the option it keeps it in. Each way in notes the accounts it
	 * was handed, so a check can tell a first invitation's path from a re-invitation's. The run the
	 * card reports and the cron event the queue asks for are the mail layer's own, and nothing the
	 * bulk invitation reads, so neither is modeled.
	 */
	class WPCPM_Mail {
		const QUEUE_OPTION = 'wpcpm_invite_queue';
		const INVITE_GAP   = 900;

		/**
		 * The stamps an invitation can leave, by the role each belongs to, as WPCPM_Mail keeps them,
		 * the map every stand-in mail class reads (bin/stubs/stamps.php): the queue, the guard and the
		 * table's views read all four.
		 */
		const STAMPS = WPCPM_STUB_STAMPS;

		/**
		 * Everybody waiting, in order.
		 *
		 * @return int[]
		 */
		public static function queue() {
			$queue = isset( $GLOBALS['opts'][ self::QUEUE_OPTION ] ) ? $GLOBALS['opts'][ self::QUEUE_OPTION ] : array();

			return is_array( $queue ) ? array_values( array_map( 'intval', $queue ) ) : array();
		}

		/**
		 * One account into the queue, unless it is waiting already.
		 *
		 * @param int $user_id User ID.
		 */
		public static function queue_invite( $user_id ) {
			$GLOBALS['queue_calls'][] = array( 'queue_invite', (int) $user_id );

			$queue = self::queue();

			if ( (int) $user_id && ! in_array( (int) $user_id, $queue, true ) ) {
				$queue[]                               = (int) $user_id;
				$GLOBALS['opts'][ self::QUEUE_OPTION ] = $queue;
			}
		}

		/**
		 * Accounts into the queue for a first invitation: a repeat once, and anybody waiting or
		 * carrying any of the four stamps dropped, as the mail layer drops them.
		 *
		 * @param int[] $user_ids User IDs.
		 * @return int How many went in.
		 */
		public static function queue_invites( array $user_ids ) {
			$GLOBALS['queue_calls'][] = array( 'queue_invites', array_values( array_map( 'intval', $user_ids ) ) );

			$queue = self::queue();
			$fresh = array();

			foreach ( array_diff( array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) ), $queue ) as $id ) {
				foreach ( self::STAMPS as $stamp ) {
					if ( ! empty( $GLOBALS['umeta'][ $id ][ $stamp ] ) ) {
						continue 2;
					}
				}

				$fresh[] = $id;
			}

			if ( ! empty( $fresh ) ) {
				$GLOBALS['opts'][ self::QUEUE_OPTION ] = array_merge( $queue, $fresh );
			}

			return count( $fresh );
		}

		/**
		 * Whether an invitation may go now: true, or an error when one went out, under any stamp,
		 * inside the gap.
		 *
		 * @param int $user_id User ID.
		 * @return true|WP_Error
		 */
		public static function may_invite( $user_id ) {
			$last = 0;

			foreach ( self::STAMPS as $stamp ) {
				$last = max( $last, isset( $GLOBALS['umeta'][ (int) $user_id ][ $stamp ] ) ? (int) $GLOBALS['umeta'][ (int) $user_id ][ $stamp ] : 0 );
			}

			return ( $last && $last + self::INVITE_GAP > time() ) ? new WP_Error( 'wpcpm_invite_too_soon' ) : true;
		}
	}
}

/* ---- the audiences under test -------------------------------------------- */

/**
 * The Students shape: two columns, sortable both ways, the query through WP_User_Query.
 *
 * Its two cells are its own and plain, in place of the base's name and username (`column_name()`,
 * `column_login()`), so the checks read the markup the base draws around a cell rather than a
 * cell's own. The methods at the end are seams, so a check can read what the table keeps to itself.
 */
class WPCPM_Test_Accounts_Table extends WPCPM_Accounts_Table {
	protected static function audience() {
		return 'students';
	}
	public static function role() {
		return WPCPM_Roles::ROLE_STUDENT;
	}
	protected function columns() {
		return array(
			'name'  => 'Name',
			'login' => 'Username',
		);
	}
	protected function sortable_map() {
		return array(
			'name'  => 'display_name',
			'login' => 'login',
		);
	}
	protected function query( array $args ) {
		$query = new WP_User_Query( $args );

		return array(
			'items' => $query->get_results(),
			'total' => $query->get_total(),
		);
	}
	protected function column_name( $user ) {
		return esc_html( $user->display_name );
	}
	protected function column_login( $user ) {
		return esc_html( $user->user_login );
	}
	protected function row_actions_for( WP_User $user ) {
		return array_merge(
			array( 'view' => '<a href="' . esc_url( add_query_arg( 'wpcpm_student_view', $user->ID, 'https://example.test/student/' ) ) . '">View page</a>' ),
			parent::row_actions_for( $user )
		);
	}
	public function views_now() {
		return $this->get_views();
	}
	public function actions_for( WP_User $user ) {
		return $this->invite_row_actions( $user );
	}
	public function search_now() {
		return $this->search_clause();
	}
	public function sortable_now() {
		return $this->get_sortable_columns();
	}
	public function pagination_now() {
		return $this->_pagination_args;
	}
	public function headers_now() {
		return $this->_column_headers;
	}
	public static function stamp() {
		return static::invite_meta();
	}
	public static function action() {
		return static::invite_action();
	}
}

/**
 * Another audience, to show nothing above is the Students' by accident: the mentors, with a column
 * label that has to be escaped, and no sortable column declared.
 */
class WPCPM_Test_Mentors_Table extends WPCPM_Accounts_Table {
	protected static function audience() {
		return 'mentors';
	}
	public static function role() {
		return WPCPM_Roles::ROLE_MENTOR;
	}
	protected function columns() {
		return array( 'name' => 'Name & <em>email</em>' );
	}
	protected function query( array $args ) {
		$query = new WP_User_Query( $args );

		return array(
			'items' => $query->get_results(),
			'total' => $query->get_total(),
		);
	}
	public function views_now() {
		return $this->get_views();
	}
	public function actions_for( WP_User $user ) {
		return $this->invite_row_actions( $user );
	}
	public function headers_now() {
		return $this->_column_headers;
	}
	public static function stamp() {
		return static::invite_meta();
	}
	public static function action() {
		return static::invite_action();
	}
}

/**
 * A third audience whose screen sends a row's invitation through the shared handler: the sponsors,
 * for the one name their row's link is built with.
 */
class WPCPM_Test_Sponsors_Table extends WPCPM_Accounts_Table {
	protected static function audience() {
		return 'sponsors';
	}
	public static function role() {
		return WPCPM_Roles::ROLE_SPONSOR;
	}
	protected function columns() {
		return array( 'name' => 'Name' );
	}
	protected function query( array $args ) {
		return array(
			'items' => array(),
			'total' => 0,
		);
	}
	public static function action() {
		return static::invite_action();
	}
}

/**
 * Any role, for reading the stamp each role's accounts carry.
 */
class WPCPM_Test_Role_Table extends WPCPM_Accounts_Table {
	public static $as_role     = '';
	public static $as_sortable = array();
	protected static function audience() {
		return 'people';
	}
	public static function role() {
		return self::$as_role;
	}
	protected function columns() {
		return array(
			'name' => 'Name',
			'id'   => 'ID',
		);
	}
	protected function sortable_map() {
		return self::$as_sortable;
	}
	protected function query( array $args ) {
		$query = new WP_User_Query( $args );

		return array(
			'items' => $query->get_results(),
			'total' => $query->get_total(),
		);
	}
	public function views_now() {
		return $this->get_views();
	}
	public function actions_for( WP_User $user ) {
		return $this->invite_row_actions( $user );
	}
	public static function stamp() {
		return static::invite_meta();
	}
}

/**
 * An audience that keeps records without an account, as the Institutions and Sponsors screens do:
 * the Students shape above with one record view, No account, holding the records the fixture gives
 * it in the order it gives them, searched by name, three columns of its own sorted by the first,
 * and one bulk action the table carries out itself, Create account, which reads the ticked records
 * the way a table's own action has to, once the screen has checked the nonce, and has words of its
 * own for one of its two outcomes. Each question the screen asks it is kept with the other events.
 */
class WPCPM_Test_Records_Table extends WPCPM_Test_Accounts_Table {
	/**
	 * The records the view holds, as the audience reads them: an ID, a name and each column's value.
	 *
	 * @var array[]
	 */
	public static $records = array();

	public function owns_action( $action ) {
		$GLOBALS['events'][] = 'owns_action ' . ( is_string( $action ) ? $action : gettype( $action ) );

		return 'create' === $action;
	}
	public function handle_action( $action ) {
		$ticked = WPCPM_Request::list( self::RECORDS_FIELD, '/^rec[A-Za-z0-9]{14}$/D' );

		$GLOBALS['events'][] = 'handle_action ' . $action . ': ' . implode( ',', $ticked );

		if ( empty( $ticked ) ) {
			return array( 'records-none', array( 'why' => 'none-ticked' ) );
		}

		return array( 'records-created', array( 'created' => count( $ticked ) ) );
	}
	public static function action_sentence( $status, array $detail ) {
		$GLOBALS['events'][] = 'action_sentence ' . $status;

		return 'records-created' === $status ? sprintf( '%d accounts created.', $detail['created'] ) : '';
	}
	protected function record_views() {
		return array( 'no-account' => _n_noop( 'No account %s', 'No accounts %s', 'wpcredits-program-manager' ) );
	}
	protected function count_records( $view ) {
		return 'no-account' === $view ? count( self::$records ) : 0;
	}
	protected function record_rows( $view ) {
		$term = WPCPM_Request::text( 's' );

		return array_values(
			array_filter(
				'no-account' === $view ? self::$records : array(),
				function ( $row ) use ( $term ) {
					return '' === $term || false !== stripos( (string) $row['name'], $term );
				}
			)
		);
	}
	protected function record_columns() {
		return array(
			'institution' => 'Institution',
			'contact'     => 'Contact',
			'account'     => 'Account',
		);
	}
	protected function record_sortable() {
		return array( 'institution' => array( 'institution', false ) );
	}
	protected function record_bulk_actions( $view ) {
		return 'no-account' === $view ? array( 'create' => __( 'Create account', 'wpcredits-program-manager' ) ) : array();
	}
	public function no_items() {
		if ( '' !== $this->record_view() ) {
			echo 'Every record has an account.';

			return;
		}

		parent::no_items();
	}
}

/**
 * A table that answers the three seams around its list, as the Institutions table does: a row its
 * bulk action could only refuse, which may not be ticked (one account, one record), a sentence
 * above its views and one under its table, each marked so a check finds where it was printed.
 */
class WPCPM_Test_Seams_Table extends WPCPM_Test_Records_Table {
	protected function row_tickable( $item ) {
		return is_array( $item ) ? 'recNOACCOUNT00002' !== $item['id'] : 12 !== (int) $item->ID;
	}
	protected function list_intro() {
		echo '<p class="wpcpm-test-intro">Above the views.</p>';
	}
	protected function list_outro() {
		echo '<p class="wpcpm-test-outro">Under the table.</p>';
	}
}

/**
 * A module drawn in tabs whose first is not Accounts, as the Institutions screen's first is its
 * queue, on the screen plumbing every audience's module shares (`WPCPM_Accounts_Screen`), listing
 * the records table above: what the plumbing reads of a module, and nothing more. Its page is the
 * table's audience's, so a press comes back to the list the table's own links go to.
 */
class WPCPM_Test_Queue_Module extends WPCPM_Module {
	use WPCPM_Accounts_Screen;

	const TABS = array(
		'queue'    => 'Waiting for review',
		'accounts' => 'Accounts',
		'sync'     => 'Sync',
	);

	const TAB_ACCOUNTS    = 'accounts';
	const TAB_SYNC        = 'sync';
	const PER_PAGE_OPTION = 'wpcpm_students_per_page';
	const FLASH_DETAIL    = 'queue_admin_detail';
	const ACTION_INVITE   = 'wpcpm_students_invite';

	/**
	 * The table the screen lists, which a check swaps for one that owns no action. Not `$table`: the
	 * plumbing keeps the table it built under that name.
	 *
	 * @var string
	 */
	public static $listed = 'WPCPM_Test_Records_Table';

	public function id() {
		return 'students';
	}
	public function label() {
		return 'Queue';
	}
	public function role() {
		return WPCPM_Roles::ROLE_STUDENT;
	}
	public function description() {
		return '';
	}
	protected function flash_key() {
		return 'queue_admin';
	}
	protected static function table_class() {
		return self::$listed;
	}
	protected function screen_words() {
		return array();
	}
	protected function dashboard_url() {
		return '';
	}
}

/* ---- fixtures and helpers ------------------------------------------------ */

// Three students whose name order, username order and invitation state all differ, and a mentor.
$GLOBALS['users'][11] = new WP_User( 11, 'zbruno', 'Bruno Diaz', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][12] = new WP_User( 12, 'yada', 'Ada Kowalski', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][13] = new WP_User( 13, 'xcleo', 'Cleo Ahn', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][14] = new WP_User( 14, 'wdana', 'Dana Mentor', array( WPCPM_Roles::ROLE_MENTOR ) );

$GLOBALS['umeta'][11]['wpcpm_student_invited'] = 1790000000;
$GLOBALS['umeta'][14]['wpcpm_mentor_invited']  = 1790000000;

// Twenty-one records kept without an account, one more than a page holds; the first with markup in
// its name, which is printed as text.
for ( $i = 1; $i <= 21; $i++ ) {
	WPCPM_Test_Records_Table::$records[] = array(
		'id'          => sprintf( 'recNOACCOUNT%05d', $i ),
		'name'        => sprintf( 'Institution %02d', $i ),
		'institution' => sprintf( 'Institution %02d', $i ),
		'contact'     => 0 === $i % 2 ? 'no email' : 'email on record',
		'account'     => 'Ready',
	);
}

WPCPM_Test_Records_Table::$records[0]['name']        = 'Institution <b>01</b>';
WPCPM_Test_Records_Table::$records[0]['institution'] = 'Institution <b>01</b>';

$total = 0;
$fails = 0;
function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}
	++$fails;
	echo "FAIL $label\n     got:  " . var_export( $actual, true ) . "\n     want: " . var_export( $expected, true ) . "\n";
}
function has( $haystack, $needle ) {
	return false !== strpos( (string) $haystack, (string) $needle );
}
function at( array $values, $key ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
}

/**
 * A PHP file's code with its comments left out, so a docblock naming a call is not read as one.
 *
 * @param string $src The file's source.
 * @return string
 */
function code_of( $src ) {
	return implode(
		'',
		array_map(
			function ( $token ) {
				if ( ! is_array( $token ) ) {
					return $token;
				}

				return in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1];
			},
			token_get_all( (string) $src )
		)
	);
}

/**
 * The request a screen is drawn for: the query string, as WordPress hands it to the page.
 *
 * @param array $query Query arguments.
 */
function request( array $query ) {
	$_GET                   = $query;
	$_POST                  = array();
	$_REQUEST               = $query;
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( $query );
}

/**
 * What a table's display() prints.
 *
 * @param WP_List_Table $table The table.
 * @return string
 */
function drawn( $table ) {
	ob_start();
	$table->display();
	return (string) ob_get_clean();
}

/**
 * What a table's views() prints.
 *
 * @param WP_List_Table $table The table.
 * @return string
 */
function views_of( $table ) {
	ob_start();
	$table->views();
	return (string) ob_get_clean();
}

/**
 * What core's own method of a list table prints for a table, the table's overrides passed over:
 * core's `views()` or `display()`, as the base would print them with nothing of its own around
 * them.
 *
 * @param WP_List_Table $table  The table.
 * @param string        $method `views` or `display`.
 * @return string
 */
function core_drawn( $table, $method ) {
	ob_start();
	( new ReflectionMethod( 'WP_List_Table', $method ) )->invoke( $table );
	return (string) ob_get_clean();
}

/**
 * The part of the markup between two strings.
 *
 * @param string $html  Markup.
 * @param string $start Where it starts.
 * @param string $end   Where it ends.
 * @return string
 */
function between( $html, $start, $end ) {
	$from = strpos( $html, $start );

	if ( false === $from ) {
		return '';
	}

	$to = strpos( $html, $end, $from );

	return false === $to ? substr( $html, $from ) : substr( $html, $from, $to - $from );
}

/**
 * The account IDs a drawn table's rows hold, from their checkboxes, in order.
 *
 * @param string $html Markup.
 * @return int[]
 */
function row_ids( $html ) {
	preg_match_all( '/<input type="checkbox" name="users\[\]" id="[^"]*" value="(\d+)"/', between( $html, '<tbody', '</tbody>' ), $found );

	return array_map( 'intval', $found[1] );
}

/**
 * A link's address and its query arguments, entities decoded.
 *
 * @param string $html Markup holding one link.
 * @return array{0: string, 1: array}
 */
function link_of( $html ) {
	if ( ! preg_match( '/href=["\']([^"\']*)["\']/', (string) $html, $found ) ) {
		return array( '', array() );
	}

	$url   = html_entity_decode( $found[1], ENT_QUOTES, 'UTF-8' );
	$parts = explode( '?', $url, 2 );
	$query = array();

	if ( isset( $parts[1] ) ) {
		parse_str( $parts[1], $query );
	}

	return array( $parts[0], $query );
}

/**
 * The counts a set of view links print, by view.
 *
 * @param string[] $views View => link.
 * @return array<string, string>
 */
function view_counts( array $views ) {
	$out = array();

	foreach ( $views as $view => $link ) {
		$out[ $view ] = preg_match( '/<span class="count">\(([^)]*)\)<\/span>/', $link, $found ) ? $found[1] : '';
	}

	return $out;
}

/**
 * Which views are marked current.
 *
 * @param string[] $views View => link.
 * @return string[]
 */
function current_views( array $views ) {
	return array_keys(
		array_filter(
			$views,
			function ( $link ) {
				return has( $link, ' class="current" aria-current="page"' );
			}
		)
	);
}

/**
 * The last WP_User_Query that listed rows rather than counted them.
 *
 * @return array
 */
function list_query() {
	foreach ( array_reverse( $GLOBALS['queries'] ) as $args ) {
		if ( ! isset( $args['fields'] ) ) {
			return $args;
		}
	}

	return array();
}

/**
 * The meta query a view asks for: on Invited, any of the four invitation stamps there, in one clause
 * whose key is the four, which WordPress joins once (`meta_key IN`); on Never invited, none of them,
 * a clause a stamp, each joined on its own key.
 *
 * @param string $view `invited` or `never-invited`.
 * @return array
 */
function stamps_query( $view ) {
	$keys = array( 'wpcpm_student_invited', 'wpcpm_mentor_invited', 'wpcpm_inst_invited', 'wpcpm_sponsor_invited' );

	if ( 'invited' === $view ) {
		return array(
			'relation' => 'OR',
			array(
				'key'     => $keys,
				'compare' => 'EXISTS',
			),
		);
	}

	$query = array( 'relation' => 'AND' );

	foreach ( $keys as $key ) {
		$query[] = array(
			'key'     => $key,
			'compare' => 'NOT EXISTS',
		);
	}

	return $query;
}

/**
 * One bulk invitation on an audience's list, from a queue holding whom the check says: what came
 * of it, whom the queue holds after, and each way into the queue taken, with the accounts handed
 * to it. What the method threw is what came of it, so a check against a method that is not there
 * fails as a check rather than ending the run.
 *
 * @param string $table   The audience's table class.
 * @param array  $ids     The ticked accounts, as the form posts them.
 * @param bool   $resend  Resend invite, rather than Send invite.
 * @param int[]  $waiting Whom the queue holds already.
 * @return array{0: mixed, 1: int[], 2: array} What came of it, the queue, the ways in taken.
 */
function queue_press( $table, array $ids, $resend, array $waiting = array() ) {
	$GLOBALS['opts']        = empty( $waiting ) ? array() : array( WPCPM_Mail::QUEUE_OPTION => $waiting );
	$GLOBALS['reads']       = array();
	$GLOBALS['queue_calls'] = array();

	try {
		$outcome = $table::queue_ticked( $ids, $resend );
	} catch ( Throwable $thrown ) {
		$outcome = 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
	}

	return array( $outcome, WPCPM_Mail::queue(), $GLOBALS['queue_calls'] );
}

/**
 * A method a table keeps to itself, called as the table calls it, or what it threw when it is not
 * there, so a check against a missing seam fails as a check rather than ending the run.
 *
 * @param object $table  The table.
 * @param string $method The method.
 * @param array  $args   Its arguments.
 * @return mixed
 */
function seam( $table, $method, array $args = array() ) {
	try {
		$reached = new ReflectionMethod( $table, $method );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$reached->setAccessible( true );
		}

		return $reached->invokeArgs( $table, $args );
	} catch ( Throwable $thrown ) {
		return 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
	}
}

/**
 * The record IDs a drawn table's rows hold, from their checkboxes, in order.
 *
 * @param string $html Markup.
 * @return string[]
 */
function record_ids( $html ) {
	preg_match_all( '/<input type="checkbox" name="records\[\]" id="[^"]*" value="([^"]*)"/', between( $html, '<tbody', '</tbody>' ), $found );

	return $found[1];
}

/**
 * One press in a list's form, handled as the screen plumbing handles it on the screen's load hook
 * (`WPCPM_Accounts_Screen::handle_list_form()`), on the stand-in module and the table it lists:
 * where the press sent the browser, what stopped it, what threw, whether the screen went on to draw,
 * what the press reached in order, and what it flashed for the person looking.
 *
 * @param array  $query The request.
 * @param string $table The table the module lists.
 * @param bool   $keep  Whether what it flashed stays for the page the press comes back to, which a
 *                      check then prints (`printed()`).
 * @return array{redirect: string, died: string, error: string, drawn: bool, events: string[], flash: mixed}
 */
function list_press( array $query, $table = 'WPCPM_Test_Records_Table', $keep = false ) {
	$person = get_current_user_id();

	request( $query );
	$GLOBALS['events']      = array();
	$GLOBALS['opts']        = array();
	$GLOBALS['queue_calls'] = array();
	unset( $GLOBALS['umeta'][ $person ][ WPCPM_Flash::META ] );
	WPCPM_Test_Queue_Module::$listed = $table;

	$out = array(
		'redirect' => '',
		'died'     => '',
		'error'    => '',
		'drawn'    => false,
	);

	try {
		$handle = new ReflectionMethod( 'WPCPM_Test_Queue_Module', 'handle_list_form' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$handle->setAccessible( true );
		}

		$handle->invoke( new WPCPM_Test_Queue_Module(), new $table() );
		$out['drawn'] = true;
	} catch ( WPCPM_Test_Redirect $signal ) {
		$out['redirect'] = $signal->getMessage();
	} catch ( WPCPM_Test_Death $signal ) {
		$out['died'] = $signal->getMessage();
	} catch ( Throwable $thrown ) {
		$out['error'] = get_class( $thrown ) . ': ' . $thrown->getMessage();
	}

	$out['events'] = $GLOBALS['events'];
	$out['flash']  = isset( $GLOBALS['umeta'][ $person ][ WPCPM_Flash::META ] ) ? $GLOBALS['umeta'][ $person ][ WPCPM_Flash::META ] : null;

	if ( ! $keep ) {
		unset( $GLOBALS['umeta'][ $person ][ WPCPM_Flash::META ] );
	}

	WPCPM_Test_Queue_Module::$listed = 'WPCPM_Test_Records_Table';

	return $out;
}

/**
 * The notice the page a press comes back to prints for what the press left, from a map of
 * outcomes, as the Accounts tab prints its own (`render_notice_from()`), its sentence worded by the
 * screen plumbing (`notice_sentence()`): what it printed, or what threw, and what the table was
 * asked on the way.
 *
 * @param array $messages Status => notice type and sentence.
 * @return array{printed: string, events: string[]}
 */
function printed( array $messages ) {
	$GLOBALS['events'] = array();

	ob_start();

	try {
		$render = new ReflectionMethod( 'WPCPM_Test_Queue_Module', 'render_notice_from' );

		// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
		if ( PHP_VERSION_ID < 80100 ) {
			$render->setAccessible( true );
		}

		$render->invoke( new WPCPM_Test_Queue_Module(), $messages );
	} catch ( Throwable $thrown ) {
		echo 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
	}

	return array(
		'printed' => (string) ob_get_clean(),
		'events'  => $GLOBALS['events'],
	);
}

/**
 * A press, then the notice the page it comes back to prints for it, as a person of the press's own:
 * the outcome a page takes is kept for the rest of its request (`WPCPM_Flash::take()`), so a second
 * press printed in this run as the same person would print the first one's.
 *
 * @param array $query    The request.
 * @param array $messages Status => notice type and sentence.
 * @param int   $person   The person pressing, one no other press here is made as.
 * @return array{printed: string, events: string[]}
 */
function press_and_print( array $query, array $messages, $person ) {
	$GLOBALS['uid'] = (int) $person;

	list_press( $query, 'WPCPM_Test_Records_Table', true );
	$out = printed( $messages );

	$GLOBALS['uid'] = 1;

	return $out;
}

/* ---- the checks ---------------------------------------------------------- */

echo "=== The table is WordPress's list table: a checkbox, the audience's columns, a row per account ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
$html  = drawn( $table );
$thead = between( $html, '<thead>', '</thead>' );

preg_match_all( "/<t[hd] [^>]*id='([a-z_]+)'/", $thead, $header_ids );

ck( 'the headers are the checkbox, then the audience\'s two columns in its order', $header_ids[1], array( 'cb', 'name', 'login' ) );
ck( 'each column says its name', array( has( $thead, '<span>Name</span>' ), has( $thead, '<span>Username</span>' ) ), array( true, true ) );
ck( 'the footer repeats the headers, without their ids', substr_count( between( $html, '<tfoot>', '</tfoot>' ), "id='" ), 0 );
ck( 'one row per account, in name order: Ada, Bruno, Cleo', row_ids( $html ), array( 12, 11, 13 ) );

$rows = array_slice( explode( '<tr>', between( $html, '<tbody', '</tbody>' ) ), 1 );

ck( 'each row holds the audience\'s own cells',
	array( has( $rows[0], 'Ada Kowalski' ), has( $rows[0], 'yada' ), has( $rows[2], 'Cleo Ahn' ), has( $rows[2], 'xcleo' ) ),
	array( true, true, true, true ) );
ck( 'the table is WordPress\'s list table, named for its audience',
	has( $html, '<table class="wp-list-table widefat fixed striped table-view-list students">' ), true );
ck( 'the column headers are set by the table, with the first column as the primary one',
	array( array_keys( $table->headers_now()[0] ), $table->headers_now()[1], $table->headers_now()[3] ),
	array( array( 'cb', 'name', 'login' ), array(), 'name' ) );
ck( 'the primary column is where the row actions are drawn',
	1 === preg_match( "/<th class='name column-name has-row-actions column-primary'[^>]*>Ada Kowalski<div class=\"row-actions\">/", $rows[0] ), true );
ck( 'one "Show more details" toggle a row, as on core\'s own lists, and the other cells hold nothing but their value',
	array( array_map( function ( $row ) { return substr_count( $row, 'toggle-row' ); }, $rows ), has( $rows[0], "<td class='login column-login' data-colname=\"Username\">yada</td>" ) ),
	array( array( 1, 1, 1 ), true ) );

$mentors = new WPCPM_Test_Mentors_Table();
$mentors->prepare_items();
$mentors_html = drawn( $mentors );

ck( 'a column\'s label is printed as text, never as markup',
	array( has( $mentors_html, 'Name &amp; &lt;em&gt;email&lt;/em&gt;' ), has( $mentors_html, '<em>' ) ), array( true, false ) );
ck( 'and another audience lists its own role\'s accounts only', row_ids( $mentors_html ), array( 14 ) );

$GLOBALS['users'][13]->display_name = 'Cleo <b>Ahn</b>';
$table                              = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
$html                               = drawn( $table );
$GLOBALS['users'][13]->display_name = 'Cleo Ahn';

ck( 'an account\'s name is printed as text in its checkbox\'s label, as everywhere the table prints it',
	array( has( $html, '<span class="screen-reader-text">Select Cleo &lt;b&gt;Ahn&lt;/b&gt;</span>' ), has( $html, '<b>' ) ), array( true, false ) );

// The name and the username of an audience that draws no cell of its own for them: the base's, the
// cells every audience's list draws alike, the name to the account's editor when the person looking
// may open it.
$plain = new class() extends WPCPM_Accounts_Table {
	protected static function audience() {
		return 'students';
	}
	public static function role() {
		return WPCPM_Roles::ROLE_STUDENT;
	}
	protected function columns() {
		return array(
			'name'  => 'Name',
			'login' => 'Username',
		);
	}
	protected function query( array $args ) {
		$query = new WP_User_Query( $args );

		return array(
			'items' => $query->get_results(),
			'total' => $query->get_total(),
		);
	}
};

$GLOBALS['users'][13]->display_name = 'Cleo <b>Ahn</b>';
$plain->prepare_items();
$with_editor                        = drawn( $plain );
$GLOBALS['no_editor']               = true;
$without_editor                     = drawn( $plain );
$GLOBALS['no_editor']               = false;
$GLOBALS['users'][13]->display_name = 'Cleo Ahn';

ck( 'an audience that draws no name or username of its own gets the base\'s: the name in bold, to the account\'s editor, and the username as code, each printed as text',
	array(
		has( $with_editor, '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=12">Ada Kowalski</a></strong>' ),
		has( $with_editor, '<code>yada</code>' ),
		has( $with_editor, '<strong><a href="https://example.test/wp-admin/user-edit.php?user_id=13">Cleo &lt;b&gt;Ahn&lt;/b&gt;</a></strong>' ),
		has( $with_editor, '<b>' ),
	),
	array( true, true, true, false ) );
ck( 'and for a person who may not open the editor, the name in bold alone, linked nowhere',
	array( has( $without_editor, '<strong>Ada Kowalski</strong>' ), has( $without_editor, 'user-edit.php' ) ),
	array( true, false ) );

echo "\n=== Hidden columns: what a person unticked under Screen Options stays hidden, never the primary column ===\n";

$GLOBALS['screen'] = (object) array(
	'id'   => 'wpcredits-program_page_wpcpm-students',
	'base' => 'wpcredits-program_page_wpcpm-students',
);
$table             = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'on a screen where nothing was unticked, nothing is hidden', $table->headers_now()[1], array() );

// Core's Screen Options offers a box to hide each column the screen's columns filter answers, but
// for a short list of names of its own (`_title`, `cb`, `comment`, `media`, `name`, `title`,
// `username`, `blogname`), and core's constructor answers that filter with every column. A box for
// the primary column would not stick: the table never hides it.
$offered                      = 'manage_wpcredits-program_page_wpcpm-students_columns';
$GLOBALS['hooks'][ $offered ] = array();
$table                        = new WPCPM_Test_Accounts_Table();
$answer                       = function () use ( $offered, $table ) {
	return array_map(
		function ( $added ) use ( $table ) {
			return array( is_array( $added[0] ) && $added[0][0] === $table ? 'the table' : 'something else', is_array( $added[0] ) ? $added[0][1] : '', $added[2] );
		},
		$GLOBALS['hooks'][ $offered ]
	);
};

ck( 'core\'s filter for the screen\'s columns, which core\'s constructor answers with every column, is answered by the table with every column but the primary one, at core\'s priority',
	array( $answer(), apply_filters( $offered, array() ) ),
	array( array( array( 'the table', 'columns_offered', 0 ) ), array( 'cb' => '<input type="checkbox" />', 'login' => 'Username' ) ) );

$GLOBALS['hooks'][ $offered ] = array();
$keyed                        = new class() extends WPCPM_Test_Accounts_Table {
	protected function columns() {
		return array(
			'mentor'   => 'Mentor',
			'login'    => 'Username',
			'students' => 'Students',
		);
	}
};

ck( 'whatever the primary column is keyed: one keyed `mentor`, a name core would offer to hide, is left out, the other columns offered',
	apply_filters( $offered, array() ), array( 'cb' => '<input type="checkbox" />', 'login' => 'Username', 'students' => 'Students' ) );

$GLOBALS['hooks'][ $offered ] = array();

$GLOBALS['umeta'][1]['managewpcredits-program_page_wpcpm-studentscolumnshidden'] = array( 'login', 'name' );

$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
$html  = drawn( $table );
$rows  = array_slice( explode( '<tr>', between( $html, '<tbody', '</tbody>' ) ), 1 );

ck( 'a column unticked under Screen Options stays hidden, and the primary one, which holds the row\'s actions, never is',
	$table->headers_now()[1], array( 'login' ) );
ck( 'which WordPress draws as a hidden header and hidden cells, the name column shown',
	array( has( $html, "id='login' class='manage-column column-login hidden sortable desc'" ), has( at( $rows, 0 ), "<td class='login column-login hidden'" ), has( $html, "id='name' class='manage-column column-name column-primary sorted asc'" ) ),
	array( true, true, true ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 's' => 'nobody' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'and the empty row spans the columns shown', has( drawn( $table ), '<tr class="no-items"><td class="colspanchange" colspan="2">' ), true );

unset( $GLOBALS['umeta'][1]['managewpcredits-program_page_wpcpm-studentscolumnshidden'] );
$GLOBALS['screen'] = null;

echo "\n=== The query stand-ins read a meta query as WordPress does: its relation, a list of keys ===\n";

// The matcher every stand-in user query reads a meta query with (bin/stubs/meta-matcher.php, which
// this suite and the screen suites' stand-ins share): the Invited view asks for any of several
// stamps in one clause whose key is the list of them, and Never invited for none of them, a clause a
// key joined by AND, so the matcher reads both relations as WordPress does, AND for a query that
// names none, and a list of keys under EXISTS as any of them. A key's presence is what EXISTS asks,
// so a key holding 0 is there. What a probe threw is its answer, so a matcher that cannot read a
// query fails a check rather than ending the run.
$GLOBALS['umeta'][901] = array( 'wpcpm_probe_a' => 1 );
$GLOBALS['umeta'][902] = array( 'wpcpm_probe_b' => 0 );
$GLOBALS['umeta'][903] = array();
$unmodeled_before      = $GLOBALS['unmodeled'];
$probe                 = function ( array $query ) {
	$met = array();

	foreach ( array( 901, 902, 903 ) as $id ) {
		try {
			$met[ $id ] = wpcpm_stub_meta_matches( $id, $query );
		} catch ( Throwable $thrown ) {
			$met[ $id ] = get_class( $thrown );
		}
	}

	return $met;
};
$probe_a               = array(
	'key'     => 'wpcpm_probe_a',
	'compare' => 'EXISTS',
);
$probe_b               = array(
	'key'     => 'wpcpm_probe_b',
	'compare' => 'EXISTS',
);

ck( 'OR finds an account holding either key, AND of NOT EXISTS one holding neither, and a query naming no relation joins its clauses with AND, all without a word about what it does not model',
	array(
		$probe( array( 'relation' => 'OR', $probe_a, $probe_b ) ),
		$probe( array( 'relation' => 'AND', array( 'compare' => 'NOT EXISTS' ) + $probe_a, array( 'compare' => 'NOT EXISTS' ) + $probe_b ) ),
		$probe( array( $probe_a, $probe_b ) ),
		$probe( array( 'relation' => 'OR' ) ),
		$GLOBALS['unmodeled'] === $unmodeled_before,
	),
	array(
		array( 901 => true, 902 => true, 903 => false ),
		array( 901 => false, 902 => false, 903 => true ),
		array( 901 => false, 902 => false, 903 => false ),
		array( 901 => true, 902 => true, 903 => true ),
		true,
	) );

wpcpm_stub_meta_matches( 901, array( 'relation' => 'XOR', $probe_a ) );

ck( 'and a relation WordPress does not have is noted as one it does not model', array_slice( $GLOBALS['unmodeled'], count( $unmodeled_before ) ), array( 'meta_query relation XOR' ) );

$GLOBALS['unmodeled'] = $unmodeled_before;

ck( 'a list of keys under EXISTS is any of them, a key holding 0 among them, and a list holding the empty key alone finds nobody, all without a word about what it does not model',
	array(
		$probe(
			array(
				'relation' => 'OR',
				array(
					'key'     => array( 'wpcpm_probe_a', 'wpcpm_probe_b' ),
					'compare' => 'EXISTS',
				),
			)
		),
		$probe(
			array(
				'relation' => 'OR',
				array(
					'key'     => array( '' ),
					'compare' => 'EXISTS',
				),
			)
		),
		$GLOBALS['unmodeled'] === $unmodeled_before,
	),
	array(
		array( 901 => true, 902 => true, 903 => false ),
		array( 901 => false, 902 => false, 903 => false ),
		true,
	) );

// WordPress binds one key in the join a NOT EXISTS clause makes, so a list there is no query a view
// may ask, and the matcher says so rather than answering it.
try {
	wpcpm_stub_meta_matches(
		903,
		array(
			array(
				'key'     => array( 'wpcpm_probe_a' ),
				'compare' => 'NOT EXISTS',
			),
		)
	);
} catch ( Throwable $thrown ) {
	$GLOBALS['unmodeled'][] = 'threw ' . get_class( $thrown );
}

ck( 'and a list of keys under NOT EXISTS, which WordPress cannot join on, is noted as one it does not model', array_slice( $GLOBALS['unmodeled'], count( $unmodeled_before ) ), array( 'meta_query key list under NOT EXISTS' ) );

$GLOBALS['unmodeled'] = $unmodeled_before;

// A clause naming a value and no compare is WordPress's `=`, as the reconciliation's live flag asks:
// the key holding that value, compared as strings with the clause's trimmed, so a stored 1 meets '1'
// and a stored 0 meets '0'; another value, or no key, does not.
ck( 'a clause naming a value is met by the key holding that value as a string, the clause\'s trimmed, and by nothing else, all without a word about what it does not model',
	array(
		$probe( array( array( 'key' => 'wpcpm_probe_a', 'value' => '1' ) ) ),
		$probe( array( array( 'key' => 'wpcpm_probe_b', 'value' => '0', 'compare' => '=' ) ) ),
		$probe( array( array( 'key' => 'wpcpm_probe_a', 'value' => ' 1 ' ) ) ),
		$probe( array( array( 'key' => 'wpcpm_probe_a', 'value' => '2' ) ) ),
		$probe( array( 'relation' => 'AND', array( 'key' => 'wpcpm_probe_b', 'compare' => 'NOT EXISTS' ), array( 'key' => 'wpcpm_probe_a', 'value' => '1' ) ) ),
		$GLOBALS['unmodeled'] === $unmodeled_before,
	),
	array(
		array( 901 => true, 902 => false, 903 => false ),
		array( 901 => false, 902 => true, 903 => false ),
		array( 901 => true, 902 => false, 903 => false ),
		array( 901 => false, 902 => false, 903 => false ),
		array( 901 => true, 902 => false, 903 => false ),
		true,
	) );

$probe( array( array( 'key' => 'wpcpm_probe_a' ) ) );
$probe( array( array( 'key' => 'wpcpm_probe_a', 'value' => array( '1', '2' ) ) ) );

ck( 'and a clause under = with no value, or a list of values, is noted as one it does not model, once an account',
	array_count_values( array_slice( $GLOBALS['unmodeled'], count( $unmodeled_before ) ) ),
	array( 'meta_query compare =' => 6 ) );

$GLOBALS['unmodeled'] = $unmodeled_before;
unset( $GLOBALS['umeta'][901], $GLOBALS['umeta'][902], $GLOBALS['umeta'][903] );

echo "\n=== The views: All, Invited, Never invited, counted from the invitation stamps ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$GLOBALS['queries'] = array();
$table              = new WPCPM_Test_Accounts_Table();
$views              = $table->views_now();

ck( 'three views, in this order', array_keys( $views ), array( 'all', 'invited', 'never-invited' ) );
ck( 'counted from the stamp: one student invited, two never', view_counts( $views ), array( 'all' => '3', 'invited' => '1', 'never-invited' => '2' ) );

$asked = array();
foreach ( $GLOBALS['queries'] as $args ) {
	$asked[] = array( $args['role'], isset( $args['meta_query'] ) ? $args['meta_query'] : array() );
}

ck( 'the counts ask whether any of the stamps WPCPM_Mail writes is there, and whether none of them is: every audience\'s stamp, not the audience\'s own alone',
	$asked,
	array( array( 'wpcpm_student', stamps_query( 'invited' ) ), array( 'wpcpm_student', stamps_query( 'never-invited' ) ) ) );
ck( 'and each count reads a count, not the rows',
	array_map(
		function ( $args ) {
			return array( $args['number'], $args['fields'], $args['count_total'] );
		},
		$GLOBALS['queries']
	),
	array( array( 1, 'ID', true ), array( 1, 'ID', true ) ) );
ck( 'each label is its word and a count, as WordPress draws a view',
	array( has( $views['all'], '>All <span class="count">(3)</span></a>' ), has( $views['never-invited'], '>Never invited <span class="count">(2)</span></a>' ) ),
	array( true, true ) );

$links = array();
foreach ( $views as $view => $link ) {
	$links[ $view ] = link_of( $link );
}

ck( 'each view links to the Accounts tab of the audience\'s screen, and names itself unless it is All',
	$links,
	array(
		'all'           => array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) ),
		'invited'       => array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'invited' ) ),
		'never-invited' => array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'never-invited' ) ),
	) );
ck( 'All is the current view when none is asked for', current_views( $views ), array( 'all' ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'never-invited' ) );
$table = new WPCPM_Test_Accounts_Table();
$views = $table->views_now();
$table->prepare_items();

ck( 'the view asked for is the current one, and the only one', current_views( $views ), array( 'never-invited' ) );
ck( 'Never invited lists the accounts without the stamp', row_ids( drawn( $table ) ), array( 12, 13 ) );
ck( 'by asking the list query for none of the stamps being there',
	at( list_query(), 'meta_query' ), stamps_query( 'never-invited' ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'invited' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'Invited lists the accounts with the stamp', row_ids( drawn( $table ) ), array( 11 ) );

// "Any of the four" as four EXISTS clauses under OR gives each clause a join of its own with no key
// in it, so the database walks every account's meta rows to the fourth power; one clause whose key
// is the four is one join on `meta_key IN`. The OR stays: it is what has WordPress select DISTINCT
// for an account holding two stamps.
$invited_query   = (array) at( list_query(), 'meta_query' );
$invited_clauses = array_values( array_filter( $invited_query, 'is_array' ) );

ck( 'and asks it in one clause whose key is the four stamps, so the list joins every account\'s meta once, however many stamps there are',
	array(
		count( $invited_clauses ),
		isset( $invited_clauses[0]['key'] ) && is_array( $invited_clauses[0]['key'] ) ? count( $invited_clauses[0]['key'] ) : 0,
		$invited_query,
	),
	array( 1, 4, stamps_query( 'invited' ) ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'everybody' ) );
$table = new WPCPM_Test_Accounts_Table();
$views = $table->views_now();
$table->prepare_items();

ck( 'a view the table does not have is All', array( current_views( $views ), row_ids( drawn( $table ) ), at( list_query(), 'meta_query' ) ), array( array( 'all' ), array( 12, 11, 13 ), array() ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 's' => 'ada' ) );
$table = new WPCPM_Test_Accounts_Table();

ck( 'the counts are the whole audience, not the search, as WordPress\'s own views count', view_counts( $table->views_now() ), array( 'all' => '3', 'invited' => '1', 'never-invited' => '2' ) );

// A stamp of 0 is somebody having written one, which never_invited() reads as invited: the view
// and the row have to agree with it, or a row says Send invite under the Invited view.
$GLOBALS['umeta'][13]['wpcpm_student_invited'] = '0';
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();

ck( 'a stamp of 0 is still a stamp: counted as invited, and its row offers Resend invite',
	array( view_counts( $table->views_now() )['invited'], array_keys( $table->actions_for( $GLOBALS['users'][13] ) ) ),
	array( '2', array( 'reinvite' ) ) );
unset( $GLOBALS['umeta'][13]['wpcpm_student_invited'] );

$GLOBALS['translations'] = array( 'Invited %s' => 'Invited <b>%s</b>' );
$table                   = new WPCPM_Test_Accounts_Table();
$views                   = $table->views_now();
$GLOBALS['translations'] = array();

ck( 'a translation\'s markup in a view\'s label is printed as text, and the count is still the table\'s',
	array( has( $views['invited'], 'Invited &lt;b&gt;<span class="count">(1)</span>&lt;/b&gt;' ), has( $views['invited'], '<b>' ) ),
	array( true, false ) );

ob_start();
$table->views();
$printed = (string) ob_get_clean();

ck( 'views() prints them as WordPress\'s list of views', array( has( $printed, "<ul class='subsubsub'>" ), has( $printed, "<li class='never-invited'>" ), substr_count( $printed, '<li ' ) ), array( true, true, 3 ) );

$mentors = new WPCPM_Test_Mentors_Table();

ck( 'another audience counts its own role against its own stamp', view_counts( $mentors->views_now() ), array( 'all' => '1', 'invited' => '1', 'never-invited' => '0' ) );

echo "\n=== The row actions: Send invite, or Resend invite, as a nonce link to the audience's handler ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();
$never = $table->actions_for( $GLOBALS['users'][12] );
$again = $table->actions_for( $GLOBALS['users'][11] );

ck( 'an account never invited is offered Send invite', array( array_keys( $never ), strip_tags( at( $never, 'invite' ) ) ), array( array( 'invite' ), 'Send invite' ) );
ck( 'an account already invited is offered Resend invite', array( array_keys( $again ), strip_tags( at( $again, 'reinvite' ) ) ), array( array( 'reinvite' ), 'Resend invite' ) );

// The handler's nonce is keyed to the account, its action with the account's ID after it, so a
// link taken from one row is no use on another.
$nonce = wp_create_nonce( 'wpcpm_students_invite_12' );

ck( 'Send invite links to the audience\'s invite handler, with the account, the tab and the handler\'s nonce, keyed to the account',
	link_of( at( $never, 'invite' ) ),
	array( 'https://example.test/wp-admin/admin-post.php', array( 'action' => 'wpcpm_students_invite', 'user' => '12', 'wpcpm_tab' => 'accounts', '_wpnonce' => $nonce ) ) );
ck( 'Resend invite goes to the same handler, for its own account, under a nonce keyed to that account',
	link_of( at( $again, 'reinvite' ) ),
	array( 'https://example.test/wp-admin/admin-post.php', array( 'action' => 'wpcpm_students_invite', 'user' => '11', 'wpcpm_tab' => 'accounts', '_wpnonce' => wp_create_nonce( 'wpcpm_students_invite_11' ) ) ) );

// An audience that says where its list stands: the link carries it, so the handler can come back to
// the same place, and the link's own action, account and tab are never the state's to change.
$placed = new class() extends WPCPM_Test_Accounts_Table {
	protected function list_state() {
		return array(
			'wpcpm_view' => 'never-invited',
			'paged'      => '3',
			'action'     => 'wpcpm_elsewhere',
			'user'       => '99',
			'wpcpm_tab'  => 'sync',
		);
	}
};

ck( 'the list\'s state the audience gives rides on the invitation link, and never over the link\'s own action, account or tab',
	link_of( at( $placed->actions_for( $GLOBALS['users'][12] ), 'invite' ) ),
	array(
		'https://example.test/wp-admin/admin-post.php',
		array(
			'action'     => 'wpcpm_students_invite',
			'user'       => '12',
			'wpcpm_tab'  => 'accounts',
			'wpcpm_view' => 'never-invited',
			'paged'      => '3',
			'_wpnonce'   => $nonce,
		),
	) );

$students_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php' );
$mentors_src  = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php' );
$sponsors_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors.php' );
$students_act = preg_match( "/const ACTION_INVITE = '([a-z_]+)';/", $students_src, $found ) ? $found[1] : 'no ACTION_INVITE in the Students module';
$mentors_act  = preg_match( "/const ACTION_INVITE = '([a-z_]+)';/", $mentors_src, $found ) ? $found[1] : 'no ACTION_INVITE in the Mentors module';
$sponsors_act = preg_match( "/const ACTION_INVITE = '([a-z_]+)';/", $sponsors_src, $found ) ? $found[1] : 'no ACTION_INVITE in the Sponsors module';

ck( 'the handler is the one each module has: the Students\', the Mentors\' and the Sponsors\' ACTION_INVITE',
	array( WPCPM_Test_Accounts_Table::action(), WPCPM_Test_Mentors_Table::action(), WPCPM_Test_Sponsors_Table::action() ), array( $students_act, $mentors_act, $sponsors_act ) );

$mentors = new WPCPM_Test_Mentors_Table();
$dana    = $mentors->actions_for( $GLOBALS['users'][14] );

ck( 'and a mentor already invited is offered Resend invite through the Mentors\' handler, under its nonce keyed to her account',
	array( array_keys( $dana ), at( link_of( at( $dana, 'reinvite' ) )[1], 'action' ), at( link_of( at( $dana, 'reinvite' ) )[1], '_wpnonce' ) ),
	array( array( 'reinvite' ), 'wpcpm_mentors_invite', wp_create_nonce( 'wpcpm_mentors_invite_14' ) ) );

$table->prepare_items();
$html = drawn( $table );

ck( 'the base draws the row\'s actions under the name: the audience\'s own first, then the invitation, then the one toggle',
	1 === preg_match( '~Ada Kowalski<div class="row-actions"><span class=\'view\'><a href="[^"]*">View page</a> \| </span><span class=\'invite\'><a href="[^"]*">Send invite</a></span></div><button type="button" class="toggle-row">~', $html ), true );

$mentors = new WPCPM_Test_Mentors_Table();
$mentors->prepare_items();
$mentors_row = between( drawn( $mentors ), '<tbody', '</tbody>' );

ck( 'an audience that adds none draws the invitation alone, still with one toggle',
	array( substr_count( $mentors_row, "<span class='" ), has( $mentors_row, "<span class='reinvite'>" ), substr_count( $mentors_row, 'toggle-row' ) ), array( 1, true, 1 ) );

// A row can have nothing to press: the Administrators list's Edit alone, for a person who may not open
// another account's editor. Core draws no toggle for a row with no action, and the toggle is what
// opens a row's other cells on a narrow screen, where core folds every cell after the primary one.
$bare = new class() extends WPCPM_Test_Accounts_Table {
	protected function row_actions_for( WP_User $user ) {
		return array();
	}
};
$bare->prepare_items();
$bare_rows = array_slice( explode( '<tr>', between( drawn( $bare ), '<tbody', '</tbody>' ) ), 1 );

ck( 'a row with no action still has the one "Show more details" toggle, under its primary cell alone, as core\'s own default draws it, and no empty actions',
	array(
		array_map( function ( $row ) { return substr_count( $row, '<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>' ); }, $bare_rows ),
		1 === preg_match( "~<th class='name column-name has-row-actions column-primary'[^>]*>Ada Kowalski<button type=\"button\" class=\"toggle-row\">~", at( $bare_rows, 0 ) ),
		substr_count( implode( '', $bare_rows ), '<div class="row-actions' ),
	),
	array( array( 1, 1, 1 ), true, 0 ) );

$row_href  = preg_match( '/href="([^"]*)"/', at( $never, 'invite' ), $found ) ? $found[1] : '';
$view_href = preg_match( '/href="([^"]*)"/', at( $table->views_now(), 'invited' ), $found ) ? $found[1] : '';

ck( 'the links the table draws are escaped for the page: &#038; between the arguments, never a bare &',
	array( has( $row_href, '&#038;' ), preg_match( '/&(?!#038;)/', $row_href ), has( $view_href, '&#038;' ), preg_match( '/&(?!#038;)/', $view_href ) ),
	array( true, 0, true, 0 ) );

// A row's Edit is the base's, drawn once for every audience, which each puts first among its own.
$editing  = new class() extends WPCPM_Test_Accounts_Table {
	public function edit_now( WP_User $user ) {
		return $this->edit_row_action( $user );
	}
};
$edit_for = function ( $user_id ) use ( $editing ) {
	try {
		return $editing->edit_now( $GLOBALS['users'][ $user_id ] );
	} catch ( Throwable $thrown ) {
		return 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
	}
};
$edit                 = $edit_for( 12 );
$GLOBALS['no_editor'] = true;
$no_edit              = $edit_for( 12 );
$GLOBALS['no_editor'] = false;

ck( 'the base\'s Edit, which an audience puts first among a row\'s actions, is the account\'s editor, and nothing for a person who may not open it',
	array( $edit, $no_edit ),
	array( array( 'edit' => '<a href="https://example.test/wp-admin/user-edit.php?user_id=12">Edit</a>' ), array() ) );

echo "\n=== The bulk actions: the two invitations, in the one form, under the table's nonce ===\n";

ck( 'the bulk actions are Send invite and Resend invite', $table->get_bulk_actions(), array( 'invite' => 'Send invite', 'reinvite' => 'Resend invite' ) );

$GLOBALS['nonce_fields'] = array();
$table                   = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
$html = drawn( $table );

ck( 'the form carries the table\'s own nonce, once', $GLOBALS['nonce_fields'], array( 'bulk-students' ) );
ck( 'which is the nonce the table says a handler checks', WPCPM_Test_Accounts_Table::bulk_nonce_action(), 'bulk-students' );

$GLOBALS['nonce_fields'] = array();
$other                   = new WPCPM_Test_Accounts_Table( array( 'plural' => 'posts' ) );
$other->prepare_items();
$other_html = drawn( $other );

ck( 'a plural passed in is not taken, so the nonce stays the one a handler checks',
	array( $GLOBALS['nonce_fields'], has( $other_html, 'table-view-list students">' ) ), array( array( 'bulk-students' ), true ) );
ck( 'the select above the table offers the two, after WordPress\'s "Bulk actions"',
	has( $html, "<select name=\"action\" id=\"bulk-action-selector-top\">\n<option value=\"-1\">Bulk actions</option>\n\t<option value=\"invite\">Send invite</option>\n\t<option value=\"reinvite\">Resend invite</option>\n</select>" ),
	true );
ck( 'and the one below it too, under WordPress\'s second name', has( $html, '<select name="action2" id="bulk-action-selector-bottom">' ), true );
ck( 'each with WordPress\'s Apply button, named as core names it, so the list form sends it too',
	array( has( $html, '<input type="submit" name="bulk_action" id="doaction" class="button action button-compact" value="Apply"  />' ), has( $html, '<input type="submit" name="bulk_action" id="doaction2"' ) ),
	array( true, true ) );
ck( 'every row\'s checkbox posts its account under one name', array( WPCPM_Accounts_Table::USERS_FIELD, substr_count( $html, 'name="users[]"' ) ), array( 'users', 3 ) );

// The list's form as every audience's screen draws it (`render_accounts_list()`): core's search box,
// then the table, whose Apply buttons come after it. A browser sends a form by its first submit
// button when Enter is pressed in one of its fields, so Enter in the search field sends the search,
// which carries no `bulk_action`, and never Apply.
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$list_module = new class() extends WPCPM_Test_Queue_Module {
	protected function screen_words() {
		return array(
			'list_heading' => 'Student accounts',
			'page_label'   => 'Their page:',
			'page_missing' => 'Their page is missing.',
			'search'       => 'Search accounts',
			'list_note'    => 'Under the list.',
		);
	}
};
$draw_list   = new ReflectionMethod( 'WPCPM_Test_Queue_Module', 'render_accounts_list' );

// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
if ( PHP_VERSION_ID < 80100 ) {
	$draw_list->setAccessible( true );
}

ob_start();
$draw_list->invoke( $list_module );
$list_form = between( (string) ob_get_clean(), '<form method="get">', '</form>' );
$search_at = strpos( $list_form, 'id="search-submit"' );
$apply_at  = strpos( $list_form, 'name="bulk_action"' );

ck( 'the list\'s form, as the screens draw it, puts the search\'s submit before the first Apply, so Enter in the search field sends the search and never Apply',
	array( false !== $search_at, false !== $apply_at, false !== $search_at && false !== $apply_at && $search_at < $apply_at ),
	array( true, true, true ) );

request( array( 'action' => 'invite' ) );
$picked = $table->current_action();
request( array( 'action' => '-1' ) );

ck( 'the action picked is read back as WordPress reads it, and "Bulk actions" is none', array( $picked, $table->current_action() ), array( 'invite', false ) );

$GLOBALS['translations'] = array( 'Resend invite' => 'Resend <script>' );
$labels                  = $table->get_bulk_actions();
$GLOBALS['translations'] = array();

ck( 'a bulk action\'s label is escaped before WordPress prints it as it is', at( $labels, 'reinvite' ), 'Resend &lt;script&gt;' );

echo "\n=== What the bulk actions queue: worked out by the base for every audience, and what came of it ===\n";

// Bruno was invited as a student and Dana as a mentor, both long ago; Ada and Cleo never were.

ck( 'Send invite on Bruno, Ada and Cleo queues Ada and Cleo, never invited, through queue_invites(), the invitations card\'s own way in, and skips Bruno, invited before',
	queue_press( 'WPCPM_Test_Accounts_Table', array( '11', '12', '13' ), false ),
	array( array( 'invites-queued', array( 'resend' => false, 'queued' => 2 ) ), array( 12, 13 ), array( array( 'queue_invites', array( 12, 13 ) ) ) ) );
ck( 'Resend invite on Bruno and Ada queues Bruno, invited before, through queue_invite(), one account at a time, and skips Ada, never invited',
	queue_press( 'WPCPM_Test_Accounts_Table', array( '11', '12' ), true ),
	array( array( 'invites-queued', array( 'resend' => true, 'queued' => 1 ) ), array( 11 ), array( array( 'queue_invite', 11 ) ) ) );

$outside = queue_press( 'WPCPM_Test_Accounts_Table', array( '14', '12', '999', '12', '0', '-3' ), false );

ck( 'an account outside the audience\'s role is left out, Dana the mentor on the students\' list, and so are an ID nobody holds and what is no ID at all; a repeat counts once, and the ticked accounts are read in one go before any is looked at',
	array( $outside, $GLOBALS['reads'] ),
	array(
		array( array( 'invites-queued', array( 'resend' => false, 'queued' => 1 ) ), array( 12 ), array( array( 'queue_invites', array( 12 ) ) ) ),
		array( 'cache 14,12,999', 'user 14', 'user 12', 'user 999' ),
	) );

$none = array();
foreach ( array(
	'nothing ticked'                  => array( array(), false, array() ),
	'Send invite on Bruno'            => array( array( '11' ), false, array() ),
	'Resend invite on Ada'            => array( array( '12' ), true, array() ),
	'Send invite on Ada, waiting'     => array( array( '12' ), false, array( 12 ) ),
	'Resend invite on Bruno, waiting' => array( array( '11' ), true, array( 11 ) ),
) as $case => $press ) {
	$none[ $case ] = queue_press( 'WPCPM_Test_Accounts_Table', $press[0], $press[1], $press[2] );
}

// The base's method is public and written for every audience, and a form never hands it anything
// but IDs (`WPCPM_Request::ids()`), but a direct caller can: what is no account's ID is no account.
ck( 'ticked values that are no account\'s ID are nothing ticked, whichever of the two is pressed, and nothing is read for them',
	array(
		queue_press( 'WPCPM_Test_Accounts_Table', array( '0' ), false ),
		queue_press( 'WPCPM_Test_Accounts_Table', array( '0', 'abc', '-3' ), true ),
		$GLOBALS['reads'],
	),
	array(
		array( array( 'invites-none', array( 'resend' => false, 'why' => 'none-selected' ) ), array(), array() ),
		array( array( 'invites-none', array( 'resend' => true, 'why' => 'none-selected' ) ), array(), array() ),
		array(),
	) );

ck( 'with nobody to queue, the outcome says why: nothing ticked, nobody left for a first invitation, nobody invited before to send another to, or everybody waiting already',
	$none,
	array(
		'nothing ticked'                  => array( array( 'invites-none', array( 'resend' => false, 'why' => 'none-selected' ) ), array(), array() ),
		'Send invite on Bruno'            => array( array( 'invites-none', array( 'resend' => false, 'why' => 'invited-already' ) ), array(), array() ),
		'Resend invite on Ada'            => array( array( 'invites-none', array( 'resend' => true, 'why' => 'never-invited' ) ), array(), array() ),
		'Send invite on Ada, waiting'     => array( array( 'invites-none', array( 'resend' => false, 'why' => 'queued-already' ) ), array( 12 ), array( array( 'queue_invites', array( 12 ) ) ) ),
		'Resend invite on Bruno, waiting' => array( array( 'invites-none', array( 'resend' => true, 'why' => 'queued-already' ) ), array( 11 ), array( array( 'queue_invite', 11 ) ) ),
	) );

// Cleo was sent an invitation five minutes ago. The queue's drain passes over anybody sent one in
// the last fifteen minutes, whose link another would cancel, so a re-invitation queued for her
// may never go, and an outcome counting her as queued would say it had.
$GLOBALS['umeta'][13]['wpcpm_student_invited'] = time() - 5 * 60;
$recent                                        = array();

foreach ( array(
	'Bruno and Cleo'          => array( array( '11', '13' ), array() ),
	'Cleo alone'              => array( array( '13' ), array() ),
	'Cleo, and Bruno waiting' => array( array( '11', '13' ), array( 11 ) ),
) as $case => $press ) {
	$recent[ $case ] = queue_press( 'WPCPM_Test_Accounts_Table', $press[0], true, $press[1] );
}

unset( $GLOBALS['umeta'][13]['wpcpm_student_invited'] );

ck( 'Resend invite leaves out anybody sent an invitation in the last fifteen minutes and says how many, unless that is why nobody was queued',
	$recent,
	array(
		'Bruno and Cleo'          => array( array( 'invites-queued', array( 'resend' => true, 'recent' => 1, 'queued' => 1 ) ), array( 11 ), array( array( 'queue_invite', 11 ) ) ),
		'Cleo alone'              => array( array( 'invites-none', array( 'resend' => true, 'why' => 'too-soon' ) ), array(), array() ),
		'Cleo, and Bruno waiting' => array( array( 'invites-none', array( 'resend' => true, 'recent' => 1, 'why' => 'queued-already' ) ), array( 11 ), array( array( 'queue_invite', 11 ) ) ),
	) );

ck( 'each audience queues by its own role and its own stamp: on the mentors\' list, Resend invite queues Dana, invited as a mentor, and leaves out Bruno, a student',
	queue_press( 'WPCPM_Test_Mentors_Table', array( '14', '11' ), true ),
	array( array( 'invites-queued', array( 'resend' => true, 'queued' => 1 ) ), array( 14 ), array( array( 'queue_invite', 14 ) ) ) );

// The module's code with its comments left out, so a docblock naming the queue's ways in is not
// read as a call to one, and with it the plumbing it shares with every accounts screen
// (`WPCPM_Accounts_Screen`), where the list's press is handled, on the table the module names.
$students_code = code_of( $students_src );
$screen_code   = code_of( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php' ) );

// The list's press itself, read to its closing brace: the module's own `invite_selected()` or, where
// it declares none, the plumbing's. The invitations card's button queues through the mail layer too,
// and is the card's, not the list's, so it is not read: a change to it is no change to the bulk move.
$press_of = function ( $code ) use ( $screen_code ) {
	$holder = false === strpos( $code, 'function invite_selected(' ) ? $screen_code : $code;
	$start  = strpos( $holder, 'function invite_selected(' );
	$end    = false === $start ? false : strpos( $holder, "\n\t}\n", $start );

	return false === $end ? '' : substr( $holder, $start, $end - $start );
};
$press    = $press_of( $students_code );

ck( 'the Students module\'s list queues through the base, called on its own table: the list\'s press calls queue_ticked() and none of the mail layer\'s ways in itself, and neither the module nor the plumbing keeps a copy of the base\'s arithmetic',
	array(
		1 === preg_match( "/function table_class\(\) \{\s*return 'WPCPM_Students_Table';/", $students_code ),
		substr_count( $press, '$table_class::queue_ticked(' ),
		substr_count( $press, 'WPCPM_Mail::' ),
		substr_count( $students_code . $screen_code, 'WPCPM_Mail::queue_invite(' ),
		substr_count( $students_code . $screen_code, 'WPCPM_Mail::may_invite(' ),
	),
	array( true, 1, 0, 0, 0 ) );

echo "\n=== The words for what came of it: the base's, one wording for every audience's list ===\n";

/**
 * What the base says a press on the ticked accounts did, for one detail, or what it threw when the
 * method is not there, so a check against a missing one fails as a check rather than ending the run.
 *
 * @param array $detail What the press carried back: `resend`, `queued` or `why`, and `recent`.
 * @return string
 */
function worded( array $detail ) {
	try {
		return WPCPM_Accounts_Table::selected_sentence( $detail );
	} catch ( Throwable $thrown ) {
		return 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
	}
}

ck( 'Send invite says how many it queued, one or more, and that they go out in the background',
	array( worded( array( 'resend' => false, 'queued' => 1 ) ), worded( array( 'resend' => false, 'queued' => 2 ) ) ),
	array( '1 invitation queued. It goes out in the background - the progress is shown below.', '2 invitations queued. They go out in the background - the progress is shown below.' ) );
ck( 'Resend invite says how many, and that each replaces the link in any earlier invitation',
	array( worded( array( 'resend' => true, 'queued' => 1 ) ), worded( array( 'resend' => true, 'queued' => 3 ) ) ),
	array( '1 invitation queued. It goes out with the next batch and replaces the link in any earlier invitation.', '3 invitations queued. They go out with the next batch, and each replaces the link in any earlier invitation.' ) );

$why = array();
foreach ( array( 'none-selected', 'never-invited', 'queued-already', 'too-soon', 'invited-already', 'something-else' ) as $reason ) {
	$why[ $reason ] = worded( array( 'resend' => false, 'why' => $reason ) );
}

ck( 'with nobody queued, it says why, each reason in its own words, and any other as nobody needing a first invitation',
	$why,
	array(
		'none-selected'   => 'Nothing to send: no accounts were selected.',
		'never-invited'   => 'Nothing to send: none of the selected accounts has been invited yet. Use Send invite for a first invitation.',
		'queued-already'  => 'Nothing to send: the selected accounts are already waiting in the queue.',
		'too-soon'        => 'Nothing to send: the selected accounts were each sent an invitation less than 15 minutes ago, and another one now would cancel the link in it. Ask them to use their newest email, or try again later.',
		'invited-already' => 'Nothing to send: none of the selected accounts needs a first invitation.',
		'something-else'  => 'Nothing to send: none of the selected accounts needs a first invitation.',
	) );
ck( 'and a Resend invite that left anybody out for the fifteen minutes says how many, one or more, after what it did',
	array(
		worded( array( 'resend' => true, 'recent' => 1, 'queued' => 1 ) ),
		worded( array( 'resend' => true, 'recent' => 2, 'why' => 'queued-already' ) ),
	),
	array(
		'1 invitation queued. It goes out with the next batch and replaces the link in any earlier invitation. 1 selected account was left out: it was sent an invitation less than 15 minutes ago, and another one now would cancel the link in it.',
		'Nothing to send: the selected accounts are already waiting in the queue. 2 selected accounts were left out: each was sent an invitation less than 15 minutes ago, and another one now would cancel the link in it.',
	) );

// Each module's code with its comments left out, as above, and the method that prints a press on
// the ticked accounts, the module's own or, where it declares none, the one in the plumbing it shares
// with every accounts screen: it takes the base's words, and loads the tables for them first, because
// a module draws its list without the screen's load hook when nothing built the table (`table()`).
$mentors_code = code_of( $mentors_src );
$words_of     = function ( $code ) use ( $screen_code ) {
	$printer = ( false === strpos( $code, 'function notice_sentence(' ) && 1 === preg_match( '/^\tuse WPCPM_Accounts_Screen;$/m', $code ) ) ? $screen_code : $code;
	$worded  = $printer === $code ? $code : $code . $printer;
	$start   = strpos( $printer, 'function notice_sentence(' );
	$end     = false === $start ? false : strpos( $printer, "\n\t}\n", $start );
	$body    = false === $end ? '' : substr( $printer, $start, $end - $start );
	$load    = strpos( $body, 'wpcpm_load_accounts_tables();' );
	$base    = strpos( $body, 'WPCPM_Accounts_Table::selected_sentence(' );

	return array( false !== $base, false !== $load && false !== $base && $load < $base, substr_count( $worded, 'function selected_sentence(' ) + substr_count( $worded, 'function queued_sentence(' ) );
};

ck( 'the Students and Mentors modules print a press on the ticked accounts in the base\'s words, loading the tables for them first, and neither keeps a wording of its own',
	array(
		'students' => $words_of( $students_code ),
		'mentors'  => $words_of( $mentors_code ),
	),
	array(
		'students' => array( true, true, 0 ),
		'mentors'  => array( true, true, 0 ),
	) );

echo "\n=== The screen plumbing the audiences share: one trait, which neither module copies ===\n";

// The Students and Mentors modules held these methods twice, the same but for the audience's name,
// so a later fix to the press checked before anything is read, or to the count read once, had to be
// made in each, and a third audience's screen would have been a third copy. They are one trait now.
// Its methods are read from its own file, so one added to it is covered the day it lands; a module
// declaring one of them again, whose copy would quietly win over the trait's, fails here, and so
// does a method moved back out of the trait. Two of them are defaults a module replaces where its
// audience differs, by design: where its list stands (`list_state()`), which the Students module
// keeps for its institution filter, and how one account is sent its invitation (`invite_one()`).
preg_match_all( '/^\t(?:(?:public|protected|private|static)\s+)*function\s+(\w+)\s*\(/m', $screen_code, $screen_methods );

$replaceable    = array( 'list_state', 'invite_one' );
$declared_again = function ( $code ) use ( $screen_methods, $replaceable ) {
	preg_match_all( '/function\s+(\w+)\s*\(/', $code, $own );

	return array_values( array_diff( array_intersect( $screen_methods[1], $own[1] ), $replaceable ) );
};
$replaced       = function ( $code ) use ( $replaceable ) {
	preg_match_all( '/function\s+(\w+)\s*\(/', $code, $own );

	return array_values( array_intersect( $replaceable, $own[1] ) );
};
$uses_screen    = function ( $code ) {
	return 1 === preg_match( '/^\tuse WPCPM_Accounts_Screen;$/m', $code );
};

ck( 'the screen plumbing the Students and Mentors modules held twice is one trait\'s, which both use without declaring one of its methods again, but for a default the trait leaves to a module: the Students module\'s own list state, for its institution filter',
	array(
		'trait'    => array_values( array_diff( array( 'list_state', 'hook_screen', 'load_screen', 'handle_list_form', 'apply_pressed', 'save_per_page', 'handle_invite', 'invite_one', 'invite_selected', 'leave', 'accounts_url', 'list_url', 'table', 'accounts_messages', 'notice_sentence', 'tab', 'tab_labels', 'tab_field', 'render_admin_page', 'render_tab_accounts', 'render_not_connected', 'never_invited' ), $screen_methods[1] ) ),
		'students' => array( $uses_screen( $students_code ), $declared_again( $students_code ), $replaced( $students_code ) ),
		'mentors'  => array( $uses_screen( $mentors_code ), $declared_again( $mentors_code ), $replaced( $mentors_code ) ),
	),
	array(
		'trait'    => array(),
		'students' => array( true, array(), array( 'list_state' ) ),
		'mentors'  => array( true, array(), array() ),
	) );

echo "\n=== The seams answer none on the base, so a list that keeps no records draws as it did ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();

ck( 'a list that declares none of the seams owns no bulk action, an invitation included, which the screen takes itself, and carries out nothing, saying so in the shape queue_ticked() says what it did, with no words of its own for any outcome',
	array(
		seam( $table, 'owns_action', array( 'create' ) ),
		seam( $table, 'owns_action', array( 'invite' ) ),
		seam( $table, 'handle_action', array( 'create' ) ),
		seam( $table, 'action_sentence', array( 'records-created', array( 'created' => 2 ) ) ),
	),
	array( false, false, array( '', array() ), '' ) );
ck( 'and keeps no records: no record view, and for any view no count, no row, no column, no sort and no bulk action; the field a record\'s checkbox would post is named all the same',
	array(
		seam( $table, 'record_views' ),
		seam( $table, 'count_records', array( 'no-account' ) ),
		seam( $table, 'record_rows', array( 'no-account' ) ),
		seam( $table, 'record_columns' ),
		seam( $table, 'record_sortable' ),
		seam( $table, 'record_bulk_actions', array( 'no-account' ) ),
		defined( 'WPCPM_Accounts_Table::RECORDS_FIELD' ) ? WPCPM_Accounts_Table::RECORDS_FIELD : 'no RECORDS_FIELD',
	),
	array( array(), 0, array(), array(), array(), array(), 'records' ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$table = new WPCPM_Test_Accounts_Table();
$views = $table->views_now();
$table->prepare_items();

ck( 'so on such a list an address naming a record view is All: the three views, the accounts\' columns, the invitations and a row an account',
	array( array_keys( $views ), current_views( $views ), array_keys( $table->headers_now()[0] ), array_keys( $table->get_bulk_actions() ), row_ids( drawn( $table ) ) ),
	array( array( 'all', 'invited', 'never-invited' ), array( 'all' ), array( 'cb', 'name', 'login' ), array( 'invite', 'reinvite' ), array( 12, 11, 13 ) ) );

echo "\n=== A record view: the records an audience keeps without an account, a view of their own ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$plain_views = ( new WPCPM_Test_Accounts_Table() )->views_now();
$records     = new WPCPM_Test_Records_Table();
$views       = $records->views_now();

ck( 'a fourth view after the three, No account, counting the records it holds and linking to itself on the Accounts tab',
	array(
		array_keys( $views ),
		view_counts( $views ),
		has( at( $views, 'no-account' ), '>No accounts <span class="count">(21)</span></a>' ),
		link_of( at( $views, 'no-account' ) ),
	),
	array(
		array( 'all', 'invited', 'never-invited', 'no-account' ),
		array( 'all' => '3', 'invited' => '1', 'never-invited' => '2', 'no-account' => '21' ),
		true,
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) ),
	) );
ck( 'and the three are the ones the list draws without it, link for link: All counts the accounts alone, since a record is none',
	array_slice( $views, 0, 3, true ), $plain_views );

$records->prepare_items();

ck( 'on the account views the list is the accounts\', as it is without the record view: their columns, their sorts, the invitations, a row an account',
	array( array_keys( $records->headers_now()[0] ), $records->headers_now()[3], array_keys( $records->sortable_now() ), array_keys( $records->get_bulk_actions() ), row_ids( drawn( $records ) ) ),
	array( array( 'cb', 'name', 'login' ), 'name', array( 'name', 'login' ), array( 'invite', 'reinvite' ), array( 12, 11, 13 ) ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$records            = new WPCPM_Test_Records_Table();
$views              = $records->views_now();
$GLOBALS['queries'] = array();
$records->prepare_items();
$read               = $GLOBALS['queries'];
$html               = drawn( $records );
$rows               = array_slice( explode( '<tr>', between( $html, '<tbody', '</tbody>' ) ), 1 );

ob_start();
$records->view_field();
$kept = (string) ob_get_clean();

ck( 'on the record view it is the view in force, and the only one, the three keeping their counts, and the list\'s form keeps it for a search, a sort or a page',
	array( current_views( $views ), view_counts( $views ), $kept ),
	array( array( 'no-account' ), array( 'all' => '3', 'invited' => '1', 'never-invited' => '2', 'no-account' => '21' ), '<input type="hidden" name="wpcpm_view" value="no-account" />' ) );
ck( 'its own columns after the checkbox, in its order, the first the primary one, and its own sort',
	array( array_keys( $records->headers_now()[0] ), $records->headers_now()[3], $records->sortable_now(), $records->headers_now()[2] ),
	array( array( 'cb', 'institution', 'contact', 'account' ), 'institution', array( 'institution' => array( 'institution', false ) ), array( 'institution' => array( 'institution', false ) ) ) );
ck( 'its own bulk action, Create account, in place of the invitations, which the view does not offer',
	array( $records->get_bulk_actions(), has( $html, "\t<option value=\"create\">Create account</option>\n" ), has( $html, 'value="invite"' ), has( $html, 'value="reinvite"' ) ),
	array( array( 'create' => 'Create account' ), true, false, false ) );
ck( 'a page of the records, twenty of the twenty-one, in the order the audience gave them, the pagination counting them all, and no account read for them',
	array( record_ids( $html ), $records->pagination_now(), has( $html, '<span class="displaying-num">21 items</span>' ), $read ),
	array(
		array_map(
			function ( $i ) {
				return sprintf( 'recNOACCOUNT%05d', $i );
			},
			range( 1, 20 )
		),
		array( 'total_items' => 21, 'total_pages' => 2, 'per_page' => 20 ),
		true,
		array(),
	) );
ck( 'each record\'s checkbox posts its ID under records[], labeled with its name as text, and no row posts an account',
	array(
		has( at( $rows, 0 ), '<td class="check-column"><input type="checkbox" name="records[]" id="wpcpm-record-recNOACCOUNT00001" value="recNOACCOUNT00001" /><label for="wpcpm-record-recNOACCOUNT00001"><span class="screen-reader-text">Select Institution &lt;b&gt;01&lt;/b&gt;</span></label></td>' ),
		substr_count( $html, 'name="records[]"' ),
		has( $html, 'name="users[]"' ),
	),
	array( true, 20, false ) );
ck( 'each cell is the record\'s value for its column, printed as text; the first is the row\'s header, holding the one toggle and none of an account\'s actions',
	array(
		has( at( $rows, 0 ), "<th class='institution column-institution has-row-actions column-primary' data-colname=\"Institution\" scope=\"row\">Institution &lt;b&gt;01&lt;/b&gt;<button type=\"button\" class=\"toggle-row\"><span class=\"screen-reader-text\">Show more details</span></button></th>" ),
		has( at( $rows, 0 ), "<td class='contact column-contact' data-colname=\"Contact\">email on record</td><td class='account column-account' data-colname=\"Account\">Ready</td>" ),
		has( at( $rows, 1 ), "<td class='contact column-contact' data-colname=\"Contact\">no email</td>" ),
		array_sum( array_map( function ( $row ) { return substr_count( $row, 'toggle-row' ); }, $rows ) ),
		substr_count( $html, '<div class="row-actions' ),
		has( $html, '<b>' ),
	),
	array( true, true, true, 20, 0, false ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 'paged' => '2' ) );
$records = new WPCPM_Test_Records_Table();
$records->prepare_items();

ck( 'and page 2 holds the one left', record_ids( drawn( $records ) ), array( 'recNOACCOUNT00021' ) );

$kept_records = WPCPM_Test_Records_Table::$records;
$labels       = array();

foreach ( array( 1, 2 ) as $how_many ) {
	WPCPM_Test_Records_Table::$records = array_slice( $kept_records, 0, $how_many );
	request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
	$labels[ $how_many ] = strip_tags( at( ( new WPCPM_Test_Records_Table() )->views_now(), 'no-account' ) );
}

WPCPM_Test_Records_Table::$records = $kept_records;

ck( 'the record view\'s label is picked by its count, as WordPress picks a plural: the singular for one record, the plural for two',
	$labels, array( 1 => 'No account (1)', 2 => 'No accounts (2)' ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account', 's' => '2' ) );
$records = new WPCPM_Test_Records_Table();
$views   = $records->views_now();
$records->prepare_items();

ck( 'a search narrows the rows, which the pagination counts, and not the view\'s link, which counts the whole view as WordPress\'s own views count',
	array( at( view_counts( $views ), 'no-account' ), at( $records->pagination_now(), 'total_items' ), record_ids( drawn( $records ) ) ),
	array( '21', 4, array( 'recNOACCOUNT00002', 'recNOACCOUNT00012', 'recNOACCOUNT00020', 'recNOACCOUNT00021' ) ) );

WPCPM_Test_Records_Table::$records = array(
	array(
		'id'          => 'rec"QUOTED"',
		'name'        => 'Quoted',
		'institution' => 'Quoted',
		'contact'     => 'no email',
		'account'     => 'Ready',
	),
);
$GLOBALS['translations']           = array( 'Create account' => 'Create <script>' );
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$records = new WPCPM_Test_Records_Table();
$records->prepare_items();
$html                              = drawn( $records );
$GLOBALS['translations']           = array();
WPCPM_Test_Records_Table::$records = $kept_records;

ck( 'a record bulk action\'s label and a record\'s ID are printed as text: the label in the select, the ID in the checkbox\'s value and HTML id',
	array(
		has( $html, '<option value="create">Create &lt;script&gt;</option>' ),
		has( $html, '<input type="checkbox" name="records[]" id="wpcpm-record-rec&quot;QUOTED&quot;" value="rec&quot;QUOTED&quot;" />' ),
		has( $html, '<script>' ),
		has( $html, 'rec"QUOTED"' ),
	),
	array( true, true, false, false ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$records = new WPCPM_Test_Records_Table();
$ticks   = function ( array $row ) use ( $records ) {
	return seam( $records, 'column_cb', array( $row ) );
};

ck( 'a record row whose ID is missing, empty or not a scalar draws no checkbox, which would post nothing under an HTML id every other such row shares',
	array( $ticks( array( 'name' => 'No ID' ) ), $ticks( array( 'id' => '', 'name' => 'Empty ID' ) ), $ticks( array( 'id' => array( 'recNOACCOUNT00001' ), 'name' => 'Listed ID' ) ) ),
	array( '', '', '' ) );
ck( 'and one whose name is missing, empty or not a scalar is labeled by its ID',
	array( $ticks( array( 'id' => 'recNONAME00000001' ) ), $ticks( array( 'id' => 'recNONAME00000002', 'name' => '' ) ), $ticks( array( 'id' => 'recNONAME00000003', 'name' => array( 'Listed name' ) ) ) ),
	array(
		'<input type="checkbox" name="records[]" id="wpcpm-record-recNONAME00000001" value="recNONAME00000001" /><label for="wpcpm-record-recNONAME00000001"><span class="screen-reader-text">Select recNONAME00000001</span></label>',
		'<input type="checkbox" name="records[]" id="wpcpm-record-recNONAME00000002" value="recNONAME00000002" /><label for="wpcpm-record-recNONAME00000002"><span class="screen-reader-text">Select recNONAME00000002</span></label>',
		'<input type="checkbox" name="records[]" id="wpcpm-record-recNONAME00000003" value="recNONAME00000003" /><label for="wpcpm-record-recNONAME00000003"><span class="screen-reader-text">Select recNONAME00000003</span></label>',
	) );

/**
 * A record view's page as the base reads it from what the audience handed back: how many rows it
 * counted and the IDs it drew, or what threw on the way, the output buffer left as it was found.
 *
 * @param WPCPM_Accounts_Table $table The table, on its record view.
 * @return mixed
 */
function records_drawn( $table ) {
	$level = ob_get_level();

	try {
		$table->prepare_items();

		return array( at( $table->pagination_now(), 'total_items' ), record_ids( drawn( $table ) ) );
	} catch ( Throwable $thrown ) {
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}

		return 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
	}
}

$failed = new class() extends WPCPM_Test_Records_Table {
	protected function record_rows( $view ) {
		return new WP_Error( 'wpcpm_read_failed' );
	}
};
$mixed  = new class() extends WPCPM_Test_Records_Table {
	protected function record_rows( $view ) {
		return array( 'a stray string', WPCPM_Test_Records_Table::$records[1], 42 );
	}
};

ck( 'only rows are rows: an error handed back in place of the rows is none, and a string or a number among them is dropped, never drawn as an account',
	array( records_drawn( $failed ), records_drawn( $mixed ) ),
	array( array( 0, array() ), array( 1, array( 'recNOACCOUNT00002' ) ) ) );

WPCPM_Test_Records_Table::$records = array();
$records                           = new WPCPM_Test_Records_Table();
$level                             = ob_get_level();

try {
	$records->prepare_items();
	$empty_row = between( drawn( $records ), '<tr class="no-items">', '</tr>' );
} catch ( Throwable $thrown ) {
	while ( ob_get_level() > $level ) {
		ob_end_clean();
	}

	$empty_row = 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
}

WPCPM_Test_Records_Table::$records = $kept_records;

ck( 'an audience can tell its record view from its account views (`record_view()`), so its empty row, across the view\'s four columns, says what an empty record view means',
	$empty_row, '<tr class="no-items"><td class="colspanchange" colspan="4">Every record has an account.</td>' );

// A record row's own actions, such as Create account on the row of a record ready for one: a seam
// the base answers with none, read by the base for a record row the way an account's actions are
// read for an account's row, and drawn in the same markup under the same cell.
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$records = new WPCPM_Test_Records_Table();

ck( 'the base gives a record row no action of its own, whatever the row',
	array( seam( $records, 'record_row_actions', array( WPCPM_Test_Records_Table::$records[0] ) ), seam( $records, 'record_row_actions', array( array() ) ) ),
	array( array(), array() ) );

$acting = new class() extends WPCPM_Test_Records_Table {
	protected function record_row_actions( array $row ) {
		if ( 'recNOACCOUNT00001' !== $row['id'] ) {
			return array();
		}

		return array(
			'create' => '<a href="https://example.test/wp-admin/admin-post.php?action=create">Create account</a>',
			'manage' => '<a href="https://example.test/wp-admin/admin.php?page=wpcpm-students">Manage</a>',
		);
	}
};
$acting->prepare_items();
$acted = array_slice( explode( '<tr>', between( drawn( $acting ), '<tbody', '</tbody>' ) ), 1 );

ck( 'a record row the table gives actions draws them under its primary cell, in the markup an account\'s are drawn in, with the one toggle; a row given none draws the toggle alone, and no other cell holds an action',
	array(
		has( at( $acted, 0 ), "<th class='institution column-institution has-row-actions column-primary' data-colname=\"Institution\" scope=\"row\">Institution &lt;b&gt;01&lt;/b&gt;<div class=\"row-actions\"><span class='create'><a href=\"https://example.test/wp-admin/admin-post.php?action=create\">Create account</a> | </span><span class='manage'><a href=\"https://example.test/wp-admin/admin.php?page=wpcpm-students\">Manage</a></span></div><button type=\"button\" class=\"toggle-row\"><span class=\"screen-reader-text\">Show more details</span></button></th>" ),
		has( at( $acted, 1 ), "<th class='institution column-institution has-row-actions column-primary' data-colname=\"Institution\" scope=\"row\">Institution 02<button type=\"button\" class=\"toggle-row\"><span class=\"screen-reader-text\">Show more details</span></button></th>" ),
		array_sum( array_map( function ( $row ) { return substr_count( $row, 'toggle-row' ); }, $acted ) ),
		array_sum( array_map( function ( $row ) { return substr_count( $row, '<div class="row-actions">' ); }, $acted ) ),
		substr_count( (string) at( $acted, 0 ), 'Create account' ),
	),
	array( true, true, 20, 1, 1 ) );

// The number in the list card's heading, whose words name accounts on every view: the list's total on
// an account view, and on a record view the accounts the list holds in all, not the view's records.
// Each list is asked under its own request, since the view in force is read from the request.
$heading_of = array();
foreach ( array( 'all' => array(), 'never-invited' => array( 'wpcpm_view' => 'never-invited' ), 'no-account' => array( 'wpcpm_view' => 'no-account' ) ) as $view => $more ) {
	request( array_merge( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ), $more ) );
	$table = new WPCPM_Test_Records_Table();
	$table->prepare_items();

	$heading_of[ $view ] = array( seam( $table, 'heading_count' ), at( $table->pagination_now(), 'total_items' ), seam( $table, 'is_record_view' ) );
}

ck( 'the heading\'s number is the list\'s total on an account view, the view\'s accounts on Never invited, and on a record view the accounts the list holds in all, never its twenty-one records',
	array_map(
		function ( $read ) {
			return array( $read[0], $read[1] );
		},
		$heading_of
	),
	array(
		'all'           => array( 3, 3 ),
		'never-invited' => array( 2, 2 ),
		'no-account'    => array( 3, 21 ),
	) );
ck( 'and a list says whether a record view is in force, which the screen reads to leave out what concerns accounts alone',
	array_map(
		function ( $read ) {
			return $read[2];
		},
		$heading_of
	),
	array(
		'all'           => false,
		'never-invited' => false,
		'no-account'    => true,
	) );

echo "\n=== A bulk action the table owns: the capability and the list's nonce, then the table, then back to the list ===\n";

// What the list's form sends besides the choice: core's nonce, the address it came from, and the
// Apply button that sent it, which core names from WordPress 6.7 on, as the version here does.
$form   = array(
	'page'             => 'wpcpm-students',
	'tab'              => 'accounts',
	'wpcpm_view'       => 'no-account',
	'_wpnonce'         => wp_create_nonce( 'bulk-students' ),
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-students&tab=accounts&wpcpm_view=no-account',
	'action2'          => '-1',
	'bulk_action'      => 'Apply',
);
$ticked = array( 'records' => array( 'recNOACCOUNT00003', 'recNOACCOUNT00007' ) );
$manage = 'capability ' . WPCPM_Roles::CAP_MANAGE;

$created = list_press( array_merge( $form, $ticked, array( 'action' => 'create' ) ) );

ck( 'Create account, which the table owns, reaches the table with the records ticked: the table is asked whether the action is its own, by its name alone, then the capability and the list\'s nonce are checked, and only then does the table carry it out',
	$created['events'],
	array( 'owns_action create', $manage, 'nonce bulk-students', 'handle_action create: recNOACCOUNT00003,recNOACCOUNT00007' ) );
ck( 'what came of it is flashed as a press on the ticked accounts flashes it, the outcome and beside it its detail, and the press comes back to the list as it stood, without the form\'s own fields',
	array( $created['flash'], link_of( '<a href="' . $created['redirect'] . '">' ) ),
	array(
		array(
			'queue_admin'        => 'records-created',
			'queue_admin_detail' => array(
				'status'  => 'records-created',
				'created' => 2,
			),
		),
		array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) ),
	) );

$forged = list_press( array_merge( $form, $ticked, array( 'action' => 'create', '_wpnonce' => 'forged' ) ) );

ck( 'with the wrong nonce the press dies with WordPress\'s own sentence, before the table carries out anything and with nothing flashed',
	array( $forged['died'], $forged['events'], $forged['flash'] ),
	array( 'The link you followed has expired.', array( 'owns_action create', $manage, 'nonce bulk-students' ), null ) );

$GLOBALS['can_manage'] = false;
$refused               = list_press( array_merge( $form, $ticked, array( 'action' => 'create' ) ) );
$GLOBALS['can_manage'] = true;

ck( 'and somebody without the program\'s capability is refused before the nonce is asked about',
	array( $refused['died'], $refused['events'], $refused['flash'] ),
	array( 'You do not have permission to manage the program.', array( 'owns_action create', $manage ), null ) );

$unowned = array(
	'an action the table does not own'        => list_press( array_merge( $ticked, array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'action' => 'delete', 'bulk_action' => 'Apply' ) ) ),
	'an action sent as a list'                => list_press( array_merge( $ticked, array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'action' => array( 'create' ), 'bulk_action' => 'Apply' ) ) ),
	'Create account on a list that owns none' => list_press( array_merge( $ticked, array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'action' => 'create', 'bulk_action' => 'Apply' ) ), 'WPCPM_Test_Accounts_Table' ),
);

ck( 'an action nobody owns still does nothing: the table is asked by the action\'s name alone, and an action sent as a list is never asked about; nothing is checked, nothing carried out, nothing flashed, and the screen goes on to draw',
	array_map(
		function ( $out ) {
			return array( $out['drawn'], $out['events'], $out['flash'], $out['redirect'], $out['error'] );
		},
		$unowned
	),
	array(
		'an action the table does not own'        => array( true, array( 'owns_action delete' ), null, '', '' ),
		'an action sent as a list'                => array( true, array(), null, '', '' ),
		'Create account on a list that owns none' => array( true, array(), null, '', '' ),
	) );

$invited = list_press( array_merge( $form, array( 'wpcpm_view' => 'never-invited', 'action' => 'invite', 'users' => array( '12' ) ) ) );

ck( 'the two invitations stay the screen\'s own on a table that owns an action: Send invite queues through the base, and the table carries out nothing',
	array( $invited['events'], $GLOBALS['queue_calls'], at( (array) $invited['flash'], 'queue_admin' ) ),
	array( array( $manage, 'nonce bulk-students' ), array( array( 'queue_invites', array( 12 ) ) ), 'invites-queued' ) );

// The page each press comes back to, printing the outcome from a map that holds the table's two
// outcomes and the invitations' two, as the Accounts tab of a screen that offers Create account
// prints from its one map.
$notices = array(
	'records-created' => array( 'success', 'Accounts created.' ),
	'records-none'    => array( 'info', 'No account was created.' ),
	'invites-queued'  => array( 'success', 'Invitations queued.' ),
	'invites-none'    => array( 'info', 'Nobody was waiting for an invitation.' ),
);
$own_words = press_and_print( array_merge( $form, $ticked, array( 'action' => 'create' ) ), $notices, 301 );
$map_words = press_and_print( array_merge( $form, array( 'action' => 'create' ) ), $notices, 302 );
$unmapped  = press_and_print( array_merge( $form, $ticked, array( 'action' => 'create' ) ), array_diff_key( $notices, array( 'records-created' => true ) ), 303 );
$sent      = press_and_print( array_merge( $form, array( 'wpcpm_view' => 'never-invited', 'action' => 'invite', 'users' => array( '12' ) ) ), $notices, 304 );
$nobody    = press_and_print( array_merge( $form, array( 'wpcpm_view' => 'never-invited', 'action' => 'invite' ) ), $notices, 305 );

ck( 'an outcome of the table\'s own that it has no words for prints the sentence the screen\'s map holds for it, never the invitations\' words, the table asked once for that outcome',
	array( $map_words['printed'], $map_words['events'] ),
	array( '<div class="notice notice-info is-dismissible"><p>No account was created.</p></div>', array( 'action_sentence records-none' ) ) );
ck( 'and one it has words for prints those, worded from the detail its press carried back',
	array( $own_words['printed'], $own_words['events'] ),
	array( '<div class="notice notice-success is-dismissible"><p>2 accounts created.</p></div>', array( 'action_sentence records-created' ) ) );
ck( 'an outcome the map does not hold prints nothing, and the table is not asked for words for it',
	array( $unmapped['printed'], $unmapped['events'] ),
	array( '', array() ) );
ck( 'the invitations\' two outcomes are worded by the base, as on every audience\'s list, and the table is never asked for words for them',
	array( $sent['printed'], $nobody['printed'], $sent['events'], $nobody['events'] ),
	array(
		'<div class="notice notice-success is-dismissible"><p>1 invitation queued. It goes out in the background - the progress is shown below.</p></div>',
		'<div class="notice notice-info is-dismissible"><p>Nothing to send: no accounts were selected.</p></div>',
		array(),
		array(),
	) );

// Core reads the bulk select's choice whatever sent the form (`current_action()`), so a search
// pressed with an action chosen and rows ticked would carry the action out. From WordPress 6.7 core
// names the Apply button, and a press of it says so as `bulk_action`; any other press, a search's
// among them, sends the form without it. Below 6.7 core prints the button unnamed, the request
// cannot say which press sent it, and core's own behavior stands: the choice runs.
$press_on = function ( $version, array $fields ) use ( $form ) {
	$GLOBALS['wp_version'] = $version;
	$out                   = list_press( array_merge( array_diff_key( $form, array( 'bulk_action' => true ) ), $fields ) );
	$GLOBALS['wp_version'] = '7.1';

	return array(
		'events' => $out['events'],
		'queued' => $GLOBALS['queue_calls'],
		'flash'  => at( (array) $out['flash'], 'queue_admin' ),
		'back'   => '' === $out['redirect'] ? 'no redirect: ' . $out['error'] . $out['died'] : link_of( '<a href="' . $out['redirect'] . '">' ),
	);
};
$apply    = array( 'bulk_action' => 'Apply' );
$invite   = array( 'wpcpm_view' => 'never-invited', 'action' => 'invite', 'users' => array( '12' ) );
$create   = array_merge( $ticked, array( 'action' => 'create' ) );
$on_list  = function ( $view ) {
	return array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => $view ) );
};
$untouched = function ( $view ) use ( $on_list ) {
	return array(
		'events' => array(),
		'queued' => array(),
		'flash'  => '',
		'back'   => $on_list( $view ),
	);
};
$queued_one  = array(
	'events' => array( $manage, 'nonce bulk-students' ),
	'queued' => array( array( 'queue_invites', array( 12 ) ) ),
	'flash'  => 'invites-queued',
	'back'   => $on_list( 'never-invited' ),
);
$created_two = array(
	'events' => array( 'owns_action create', $manage, 'nonce bulk-students', 'handle_action create: recNOACCOUNT00003,recNOACCOUNT00007' ),
	'queued' => array(),
	'flash'  => 'records-created',
	'back'   => $on_list( 'no-account' ),
);

ck( 'on WordPress 6.7, which names the Apply button, Send invite chosen with an account ticked runs only when Apply sent the form; sent by any other press, a search\'s among them, nothing is asked about or queued, and the press comes back to the list as it stood',
	array(
		'Apply'         => $press_on( '6.7', array_merge( $apply, $invite ) ),
		'another press' => $press_on( '6.7', $invite ),
	),
	array(
		'Apply'         => $queued_one,
		'another press' => $untouched( 'never-invited' ),
	) );
ck( 'below 6.7, where core prints Apply unnamed and the request cannot say which press sent it, the choice runs either way, as core\'s own lists run it',
	array(
		'Apply'         => $press_on( '6.6', array_merge( $apply, $invite ) ),
		'another press' => $press_on( '6.6', $invite ),
	),
	array(
		'Apply'         => $queued_one,
		'another press' => $queued_one,
	) );
ck( 'and so for an action the table owns: on 6.7 Create account is carried out only when Apply sent the form, the table not even asked whether the action is its own otherwise; below 6.7 either way',
	array(
		'6.7, Apply'         => $press_on( '6.7', array_merge( $apply, $create ) ),
		'6.7, another press' => $press_on( '6.7', $create ),
		'6.6, Apply'         => $press_on( '6.6', array_merge( $apply, $create ) ),
		'6.6, another press' => $press_on( '6.6', $create ),
	),
	array(
		'6.7, Apply'         => $created_two,
		'6.7, another press' => $untouched( 'no-account' ),
		'6.6, Apply'         => $created_two,
		'6.6, another press' => $created_two,
	) );

$GLOBALS['events']      = array();
$GLOBALS['opts']        = array();
$GLOBALS['queue_calls'] = array();

echo "\n=== Around the list: what is printed above its views and under its table, and which rows may be ticked ===\n";

// A table built and read for each draw: the list table stand-in, as core's, keeps the bulk actions
// it read on its first display, so a second display of one table names its top select the bottom's.
// And each draw numbers the select-all checkbox anew, as core counts them, so two draws are
// compared with that number set aside.
$fresh = function ( $class ) {
	$table = new $class();
	$table->prepare_items();

	return $table;
};
$alike = function ( $one, $other ) {
	return preg_replace( '/cb-select-all-\d+/', 'cb-select-all-', $one ) === preg_replace( '/cb-select-all-\d+/', 'cb-select-all-', $other );
};

// The base answers each seam with nothing of its own: every row may be ticked, nothing is printed
// above the views or under the table, so a list that says nothing there draws core's views and
// core's table as they are.
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$asked = new class() extends WPCPM_Test_Accounts_Table {
	public function seams_for( $item ) {
		try {
			ob_start();
			$this->list_intro();
			$this->list_outro();
			$printed = (string) ob_get_clean();

			return array( $this->row_tickable( $item ), $printed );
		} catch ( Throwable $thrown ) {
			ob_end_clean();

			return 'threw ' . get_class( $thrown ) . ': ' . $thrown->getMessage();
		}
	}
};

ck( 'on the base every row may be ticked, an account\'s and a record\'s, and nothing is printed above the views or under the table',
	array( $asked->seams_for( $GLOBALS['users'][12] ), $asked->seams_for( WPCPM_Test_Records_Table::$records[1] ) ),
	array( array( true, '' ), array( true, '' ) ) );
ck( 'so a list that says nothing there draws core\'s views and core\'s table as they are, a checkbox on every row',
	array(
		$alike( views_of( $fresh( 'WPCPM_Test_Accounts_Table' ) ), core_drawn( $fresh( 'WPCPM_Test_Accounts_Table' ), 'views' ) ),
		$alike( drawn( $fresh( 'WPCPM_Test_Accounts_Table' ) ), core_drawn( $fresh( 'WPCPM_Test_Accounts_Table' ), 'display' ) ),
		substr_count( drawn( $fresh( 'WPCPM_Test_Accounts_Table' ) ), 'name="users[]"' ),
	),
	array( true, true, 3 ) );

$seams_views = views_of( $fresh( 'WPCPM_Test_Seams_Table' ) );
$seams_table = drawn( $fresh( 'WPCPM_Test_Seams_Table' ) );
$seams_rows  = array_slice( explode( '<tr>', between( $seams_table, '<tbody', '</tbody>' ) ), 1 );
$outro       = '<p class="wpcpm-test-outro">Under the table.</p>';

ck( 'a table that says a row may not be ticked draws no checkbox on that row: Ada\'s is left out, Bruno\'s and Cleo\'s drawn as before',
	array( array_map( function ( $row ) { return preg_match( '/Ada Kowalski|Bruno Diaz|Cleo Ahn/', $row, $name ) ? $name[0] : ''; }, $seams_rows ), array_map( function ( $row ) { return substr_count( $row, 'type="checkbox"' ); }, $seams_rows ) ),
	array( array( 'Ada Kowalski', 'Bruno Diaz', 'Cleo Ahn' ), array( 0, 1, 1 ) ) );
ck( 'its sentence above the views is printed first, before core\'s views, and its sentence under the table last, after the table and its pagination, each once',
	array(
		0 === strpos( $seams_views, '<p class="wpcpm-test-intro">Above the views.</p>' ),
		$alike( substr( $seams_views, strlen( '<p class="wpcpm-test-intro">Above the views.</p>' ) ), core_drawn( $fresh( 'WPCPM_Test_Seams_Table' ), 'views' ) ),
		substr( $seams_table, -strlen( $outro ) ) === $outro,
		$alike( substr( $seams_table, 0, -strlen( $outro ) ), core_drawn( $fresh( 'WPCPM_Test_Seams_Table' ), 'display' ) ),
		substr_count( $seams_views . $seams_table, 'wpcpm-test-' ),
	),
	array( true, true, true, true, 2 ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$record_rows = array_slice( explode( '<tr>', between( drawn( $fresh( 'WPCPM_Test_Seams_Table' ) ), '<tbody', '</tbody>' ) ), 1 );

ck( 'and on a record view the record it says may not be ticked has none either, the records before and after it theirs',
	array_map( function ( $row ) { return substr_count( $row, 'name="records[]"' ); }, array_slice( $record_rows, 0, 3 ) ),
	array( 1, 0, 1 ) );

echo "\n=== The invitation views' counts: read once a table, however often they are asked ===\n";

// On a record view the views and the heading both ask how many accounts each invitation view holds,
// and each count is a query: the table reads them once and keeps them.
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'no-account' ) );
$GLOBALS['queries'] = array();
$counted            = new WPCPM_Test_Records_Table();
$counted->prepare_items();
$counted_views   = $counted->views_now();
$counted_heading = $counted->heading_count();
$counted_again   = $counted->views_now();
$count_queries   = array_values(
	array_filter(
		$GLOBALS['queries'],
		function ( $args ) {
			return isset( $args['fields'] ) && 'ID' === $args['fields'];
		}
	)
);

ck( 'the views, the heading and the views again read the counts three times, and the table counts each view once: two queries in all, Invited and Never invited',
	array( count( $count_queries ), array_map( function ( $args ) { return isset( $args['meta_query'][0]['compare'] ) ? $args['meta_query'][0]['compare'] : ''; }, $count_queries ) ),
	array( 2, array( 'EXISTS', 'NOT EXISTS' ) ) );
ck( 'and every read answers the same counts: three accounts, one Invited, two Never invited',
	array( $counted_heading, $counted_views === $counted_again, has( at( $counted_views, 'all' ), '(3)' ), has( at( $counted_views, 'invited' ), '(1)' ), has( at( $counted_views, 'never-invited' ), '(2)' ) ),
	array( 3, true, true, true, true ) );

echo "\n=== The tab a screen opens on is its first ===\n";

$opened = array();
foreach ( array(
	'none'     => null,
	'queue'    => 'queue',
	'accounts' => 'accounts',
	'sync'     => 'sync',
	'unknown'  => 'nope',
	'empty'    => '',
) as $case => $value ) {
	request( null === $value ? array( 'page' => 'wpcpm-students' ) : array( 'page' => 'wpcpm-students', 'tab' => $value ) );
	$opened[ $case ] = WPCPM_Test_Queue_Module::tab();
}

ck( 'a screen whose first tab is its queue, as the Institutions screen\'s is, opens on the queue when its address names no tab or one it does not have, and on the tab it names otherwise',
	$opened,
	array(
		'none'     => 'queue',
		'queue'    => 'queue',
		'accounts' => 'accounts',
		'sync'     => 'sync',
		'unknown'  => 'queue',
		'empty'    => 'queue',
	) );

$still = array();
foreach ( array( 'WPCPM_Students', 'WPCPM_Mentors', 'WPCPM_Administrators' ) as $module ) {
	foreach ( array(
		'none'    => null,
		'unknown' => 'nope',
	) as $case => $value ) {
		request( null === $value ? array( 'page' => 'wpcpm-students' ) : array( 'page' => 'wpcpm-students', 'tab' => $value ) );
		$still[ $module ][ $case ] = $module::tab();
	}
}

ck( 'and the Students, Mentors and Administrators screens, whose first tab is Accounts, still open on Accounts',
	$still,
	array_fill_keys(
		array( 'WPCPM_Students', 'WPCPM_Mentors', 'WPCPM_Administrators' ),
		array(
			'none'    => 'accounts',
			'unknown' => 'accounts',
		)
	) );

echo "\n=== Rows per page: the screen option, its default, what core may save, what is read ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();

ck( 'twenty rows a page until somebody chooses', array( WPCPM_Accounts_Table::PER_PAGE_DEFAULT, $table->per_page() ), array( 20, 20 ) );
ck( 'the option is named for the audience', array( WPCPM_Test_Accounts_Table::per_page_option(), WPCPM_Test_Mentors_Table::per_page_option() ), array( 'wpcpm_students_per_page', 'wpcpm_mentors_per_page' ) );

$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = '50';
ck( 'the choice is read back', $table->per_page(), 50 );

$read = array();
foreach ( array( '0', 'abc', '-5', '' ) as $junk ) {
	$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = $junk;
	$read[]                                          = $table->per_page();
}
unset( $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

ck( 'nothing usable reads as the default', $read, array( 20, 20, 20, 20 ) );

$GLOBALS['hooks']          = array();
$GLOBALS['screen_options'] = array();
WPCPM_Test_Accounts_Table::add_per_page_option();

ck( 'the audience\'s screen adds WordPress\'s rows-per-page option, with the audience\'s name and the default',
	$GLOBALS['screen_options'], array( 'per_page' => array( 'default' => 20, 'option' => 'wpcpm_students_per_page' ) ) );

$GLOBALS['screen_options'] = array();
WPCPM_Test_Mentors_Table::add_per_page_option();

ck( 'under each audience\'s own name, and adding it hooks nothing: when it runs is the audience screen\'s to say',
	array( $GLOBALS['screen_options'], $GLOBALS['hooks'] ), array( array( 'per_page' => array( 'default' => 20, 'option' => 'wpcpm_mentors_per_page' ) ), array() ) );

$GLOBALS['screen_options'] = array();

ck( 'the base leaves the wiring to the audience: no static a table would have to be named by at boot or on the menu, before anything loads it',
	array( method_exists( 'WPCPM_Accounts_Table', 'register_screen_option' ), method_exists( 'WPCPM_Accounts_Table', 'register_per_page_save' ) ),
	array( false, false ) );

$saved = array();
foreach ( array( '50', '1', '999', '0', '1000', 'abc', '-3' ) as $value ) {
	$saved[ $value ] = WPCPM_Test_Accounts_Table::per_page_to_save( false, 'wpcpm_students_per_page', $value );
}

ck( 'a number from 1 to 999 is saved as a number, and anything else is not saved at all',
	$saved, array( '50' => 50, '1' => 1, '999' => 999, '0' => false, '1000' => false, 'abc' => false, '-3' => false ) );

$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = 2;
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'paged' => '2' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
$html = drawn( $table );

ck( 'the list asks for a page of the chosen size, from where the page starts', array( at( list_query(), 'number' ), at( list_query(), 'offset' ) ), array( 2, 2 ) );
ck( 'so page 2 of three accounts at two a page is the third', row_ids( $html ), array( 13 ) );
ck( 'and the pagination knows the total, the page size and the page count',
	$table->pagination_now(), array( 'total_items' => 3, 'total_pages' => 2, 'per_page' => 2 ) );

$pages = between( $html, "<div class='tablenav-pages'>", '</div>' );

ck( 'which WordPress\'s pagination draws, linking back to page 1 of the same tab',
	array( has( $pages, '<span class="displaying-num">3 items</span>' ), 1 === preg_match( '/<span class=["\']total-pages["\']>2<\/span>/', $pages ), link_of( between( $pages, "<a class='prev-page", '</a>' ) )[1] ),
	array( true, true, array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'paged' => '1' ) ) );
ck( 'and the list asks WordPress to count every match, not the page alone, which WordPress reports as 0 when not asked',
	at( list_query(), 'count_total' ), true );
unset( $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

add_filter(
	'wpcpm_students_per_page',
	function ( $rows ) {
		return 7;
	}
);
request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
$filtered = $table->per_page();
unset( $GLOBALS['hooks']['wpcpm_students_per_page'] );

ck( 'the choice is read through the option\'s filter, as core reads it and as its Screen Options box shows it',
	array( $filtered, at( list_query(), 'number' ), $table->per_page() ), array( 7, 7, 20 ) );

echo "\n=== Sorting: by what the audience declares, and nothing else ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();

ck( 'the sortable columns are WordPress\'s shape of the audience\'s map, the name marked as the order the list starts in',
	$table->sortable_now(), array( 'name' => array( 'display_name', false, '', '', 'asc' ), 'login' => array( 'login', false ) ) );

$table->prepare_items();
$html = drawn( $table );

ck( 'a list nobody sorted is by name, A to Z, and by ID among accounts of one name', array( at( list_query(), 'orderby' ), at( list_query(), 'order' ) ), array( array( 'display_name' => 'ASC', 'ID' => 'ASC' ), 'ASC' ) );
ck( 'and its header says so, while Username offers to sort',
	array( has( $html, "class='manage-column column-name column-primary sorted asc'" ), has( $html, "class='manage-column column-login sortable desc'" ) ), array( true, true ) );

$sort_link = link_of( between( between( $html, '<thead>', '</thead>' ), "id='login'", '</th>' ) );

ck( 'the Username header links to the list by username, keeping the tab', $sort_link[1], array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'orderby' => 'login', 'order' => 'asc' ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'orderby' => 'login', 'order' => 'desc' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'a column the audience declares sorts the list, either way', array( at( list_query(), 'orderby' ), at( list_query(), 'order' ), row_ids( drawn( $table ) ) ), array( 'login', 'DESC', array( 11, 12, 13 ) ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'orderby' => 'user_pass', 'order' => 'sideways' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'anything else is ignored, because it goes into a query: by name, A to Z', array( at( list_query(), 'orderby' ), at( list_query(), 'order' ) ), array( array( 'display_name' => 'ASC', 'ID' => 'ASC' ), 'ASC' ) );

request( array( 'page' => 'wpcpm-mentors', 'tab' => 'accounts', 'orderby' => 'login' ) );
$mentors = new WPCPM_Test_Mentors_Table();
$mentors->prepare_items();

ck( 'an audience that declares no sortable column has none, and its list is by name', array( $mentors->headers_now()[2], at( list_query(), 'orderby' ) ), array( array(), array( 'display_name' => 'ASC', 'ID' => 'ASC' ) ) );

WPCPM_Test_Role_Table::$as_role     = WPCPM_Roles::ROLE_STUDENT;
WPCPM_Test_Role_Table::$as_sortable = array( 'id' => 'ID' );
$picked                             = array();

foreach ( array( 'ID', 'id' ) as $asked ) {
	request( array( 'page' => 'wpcpm-people', 'tab' => 'accounts', 'orderby' => $asked ) );
	$people = new WPCPM_Test_Role_Table();
	$people->prepare_items();
	$picked[] = at( list_query(), 'orderby' );
}

WPCPM_Test_Role_Table::$as_sortable = array();

ck( 'a declared value with a capital letter can be chosen, whichever case the request uses, as sanitize_key() reads both', $picked, array( 'ID', 'ID' ) );

// Two accounts of one name, a row a page, so the boundary between two pages falls between them. The
// database may return rows that tie on the whole order in any order, and in another for another
// page (the query stand-in above takes it at its word), so a list ordered by the name alone can
// show one of them on both pages and the other on neither.
$GLOBALS['users'][15]                           = new WP_User( 15, 'vada', 'Ada Kowalski', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][1]['wpcpm_students_per_page'] = 1;
$paged                                          = array();

foreach ( array( 'asc' => array( '1', '2' ), 'desc' => array( '3', '4' ) ) as $way => $pages ) {
	foreach ( $pages as $page ) {
		request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'order' => $way, 'paged' => $page ) );
		$table = new WPCPM_Test_Accounts_Table();
		$table->prepare_items();
		$paged[ $way ][] = row_ids( drawn( $table ) );
	}
}

unset( $GLOBALS['users'][15], $GLOBALS['umeta'][1]['wpcpm_students_per_page'] );

ck( 'two accounts of one name, at a page boundary of one row a page, are each on a page of their own, the lower ID first A to Z and the higher first Z to A',
	$paged, array( 'asc' => array( array( 12 ), array( 15 ) ), 'desc' => array( array( 15 ), array( 12 ) ) ) );

echo "\n=== The search: WP_User_Query's contains search, over name, username and email ===\n";

$clauses = array();
foreach ( array( array(), array( 's' => 'ada' ), array( 's' => '   ' ), array( 's' => ' <b>ada</b> ' ) ) as $query ) {
	request( $query );
	$table     = new WPCPM_Test_Accounts_Table();
	$clauses[] = $table->search_now();
}

ck( 'no search is no clause, a term is *term*, a blank is none, and markup is not part of a term', $clauses, array( '', '*ada*', '', '*ada*' ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 's' => 'ada' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'the list searches the name, the username and the email',
	array( at( list_query(), 'search' ), at( list_query(), 'search_columns' ) ), array( '*ada*', array( 'user_login', 'user_email', 'display_name' ) ) );
ck( 'and finds Ada, by her name and her username', row_ids( drawn( $table ) ), array( 12 ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'a list nobody searched asks for no search', array_key_exists( 'search', list_query() ), false );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 's' => 'nobody' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
$html = drawn( $table );

ck( 'a search nobody matches draws WordPress\'s empty row, across the three columns, with the table\'s sentence',
	has( $html, '<tr class="no-items"><td class="colspanchange" colspan="3">No accounts found.</td></tr>' ), true );

ob_start();
$table->search_box( 'Search accounts', 'wpcpm-accounts' );
$box = (string) ob_get_clean();

ck( 'the search box keeps what was searched for', has( $box, '<input type="search" id="wpcpm-accounts-search-input" name="s" value="nobody" />' ), true );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 's' => "O\\'Brien" ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();
ob_start();
$table->search_box( 'Search accounts', 'wpcpm-accounts' );
$box = (string) ob_get_clean();

ck( 'and shows it as typed, without the slashes WordPress adds to the request, as the search reads it',
	array( has( $box, 'name="s" value="O&#039;Brien"' ), $table->search_now() ), array( true, "*O'Brien*" ) );

echo "\n=== Invited is any stamp: an account in two audiences, on the list of each ===\n";

// An account has one password, so an invitation sent it under any of its roles is one sent it: the
// queue and the fifteen-minute guard read every stamp, and so do the views, a row's invitation and
// the bulk actions, on the list of every audience the account is in. Eli and Gus hold the Student
// and Mentor roles: Eli was stamped as a mentor alone, Gus as a student alone. Fay holds both and
// was never sent one.
$GLOBALS['users'][18]                          = new WP_User( 18, 'velisa', 'Eli Both', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][19]                          = new WP_User( 19, 'ufay', 'Fay Both', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][20]                          = new WP_User( 20, 'tgus', 'Gus Both', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['umeta'][18]['wpcpm_mentor_invited']  = 1790000000;
$GLOBALS['umeta'][20]['wpcpm_student_invited'] = 1790000000;

$each_list = array();

foreach ( array(
	'students' => 'WPCPM_Test_Accounts_Table',
	'mentors'  => 'WPCPM_Test_Mentors_Table',
) as $list => $class ) {
	request( array( 'page' => 'wpcpm-' . $list, 'tab' => 'accounts' ) );
	$table  = new $class();
	$counts = view_counts( $table->views_now() );
	$rows   = array();

	foreach ( array( 18, 19, 20 ) as $id ) {
		$rows[ $id ] = array_keys( $table->actions_for( $GLOBALS['users'][ $id ] ) );
	}

	$listed = array();

	foreach ( array( 'all', 'invited', 'never-invited' ) as $view ) {
		request( array( 'page' => 'wpcpm-' . $list, 'tab' => 'accounts', 'wpcpm_view' => $view ) );
		$table = new $class();
		$table->prepare_items();
		$listed[ $view ] = row_ids( drawn( $table ) );
	}

	$each_list[ $list ] = array(
		'counts' => $counts,
		'listed' => $listed,
		'rows'   => $rows,
	);
}

ck( 'on each audience\'s list, an account stamped under either of its roles is Invited and counted so, its row offers Resend invite, one never stamped is Never invited, and All is every account',
	$each_list,
	array(
		'students' => array(
			'counts' => array( 'all' => '6', 'invited' => '3', 'never-invited' => '3' ),
			'listed' => array( 'all' => array( 12, 11, 13, 18, 19, 20 ), 'invited' => array( 11, 18, 20 ), 'never-invited' => array( 12, 13, 19 ) ),
			'rows'   => array( 18 => array( 'reinvite' ), 19 => array( 'invite' ), 20 => array( 'reinvite' ) ),
		),
		'mentors'  => array(
			'counts' => array( 'all' => '4', 'invited' => '3', 'never-invited' => '1' ),
			'listed' => array( 'all' => array( 14, 18, 19, 20 ), 'invited' => array( 14, 18, 20 ), 'never-invited' => array( 19 ) ),
			'rows'   => array( 18 => array( 'reinvite' ), 19 => array( 'invite' ), 20 => array( 'reinvite' ) ),
		),
	) );
ck( 'and the bulk actions read it so: Resend invite queues Eli from the Students list and Gus from the Mentors list, and Send invite finds nobody left in Eli, but Fay',
	array(
		queue_press( 'WPCPM_Test_Accounts_Table', array( '18' ), true ),
		queue_press( 'WPCPM_Test_Mentors_Table', array( '20' ), true ),
		queue_press( 'WPCPM_Test_Accounts_Table', array( '18' ), false ),
		queue_press( 'WPCPM_Test_Mentors_Table', array( '19' ), false ),
	),
	array(
		array( array( 'invites-queued', array( 'resend' => true, 'queued' => 1 ) ), array( 18 ), array( array( 'queue_invite', 18 ) ) ),
		array( array( 'invites-queued', array( 'resend' => true, 'queued' => 1 ) ), array( 20 ), array( array( 'queue_invite', 20 ) ) ),
		array( array( 'invites-none', array( 'resend' => false, 'why' => 'invited-already' ) ), array(), array() ),
		array( array( 'invites-queued', array( 'resend' => false, 'queued' => 1 ) ), array( 19 ), array( array( 'queue_invites', array( 19 ) ) ) ),
	) );

unset( $GLOBALS['users'][18], $GLOBALS['users'][19], $GLOBALS['users'][20], $GLOBALS['umeta'][18], $GLOBALS['umeta'][20] );
$GLOBALS['opts'] = array();

echo "\n=== The stamp and the screen the table reads from the rest of the plugin ===\n";

// One map of the stamps, the mail layer's: the queue stamps through its one rule, the rule writes
// over the map, and the table reads each role's key from it, spelling none of its own. The map every
// stand-in mail layer reads, named once in bin/stubs/stamps.php and this suite's among them, is the
// real one's, read here from the real one's source.
$mail       = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php' );
$table_code = code_of( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php' ) );
preg_match( '/const STAMPS = array\((.*?)\);/s', $mail, $map );
preg_match( '/public static function drain_queue\(\).*?\n\t}\n/s', $mail, $drain );
preg_match( '/public static function stamp_invited\(.*?\n\t}\n/s', $mail, $rule );
preg_match_all( "/WPCPM_Roles::ROLE_([A-Z]+)\s*=> '([a-z_]+)'/", isset( $map[1] ) ? $map[1] : '', $pairs, PREG_SET_ORDER );

$real_map = array();
foreach ( $pairs as $pair ) {
	$real_map[ constant( 'WPCPM_Roles::ROLE_' . $pair[1] ) ] = $pair[2];
}

$read = array();
foreach ( array_keys( $real_map ) as $role ) {
	WPCPM_Test_Role_Table::$as_role = $role;
	$read[ $role ]                  = WPCPM_Test_Role_Table::stamp();
}

ck( 'the queue stamps through the mail layer\'s one rule, which writes over its one map, the map the stand-ins share is that one, and each role\'s accounts are read by that map\'s key, the table spelling no key of its own',
	array(
		has( isset( $drain[0] ) ? $drain[0] : '', 'self::stamp_invited( $user );' ),
		has( isset( $rule[0] ) ? $rule[0] : '', 'foreach ( self::STAMPS as $role => $meta )' ),
		count( $real_map ),
		WPCPM_STUB_STAMPS === $real_map,
		WPCPM_Mail::STAMPS === WPCPM_STUB_STAMPS,
		$read,
		preg_match_all( '/wpcpm_(?:student|mentor|inst|sponsor)_invited/', $table_code ),
	),
	array( true, true, 4, true, true, $real_map, 0 ) );
ck( 'which for the two audiences here is theirs', array( WPCPM_Test_Accounts_Table::stamp(), WPCPM_Test_Mentors_Table::stamp() ), array( 'wpcpm_student_invited', 'wpcpm_mentor_invited' ) );

// WordPress's Administrator role is one the plugin never invites, so no stamp is its audience's, and
// the base reads none for it: had its accounts been read by the stamps every other audience's are,
// an administrator invited as a student would count as an invited administrator, without a word.
// Hana is one: an administrator holding a student's stamp. Ivo is an administrator holding none.
$GLOBALS['users'][16]                          = new WP_User( 16, 'hana', 'Hana Admin', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][17]                          = new WP_User( 17, 'ivo', 'Ivo Admin', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['umeta'][16]['wpcpm_student_invited'] = 1790000000;
WPCPM_Test_Role_Table::$as_role                = WPCPM_Roles::ROLE_ADMIN;
request( array( 'page' => 'wpcpm-people', 'tab' => 'accounts' ) );
$people    = new WPCPM_Test_Role_Table();
$stampless = array(
	'stamp'  => WPCPM_Test_Role_Table::stamp(),
	'views'  => view_counts( $people->views_now() ),
	'row'    => array_keys( $people->actions_for( $GLOBALS['users'][16] ) ),
	'resend' => queue_press( 'WPCPM_Test_Role_Table', array( '16' ), true ),
	'send'   => queue_press( 'WPCPM_Test_Role_Table', array( '17' ), false ),
);

request( array( 'page' => 'wpcpm-people', 'tab' => 'accounts', 'wpcpm_view' => 'invited' ) );
$people = new WPCPM_Test_Role_Table();
$people->prepare_items();
$stampless['invited'] = row_ids( drawn( $people ) );
$stampless_invited    = at( list_query(), 'meta_query' );

request( array( 'page' => 'wpcpm-people', 'tab' => 'accounts', 'wpcpm_view' => 'never-invited' ) );
$people = new WPCPM_Test_Role_Table();
$people->prepare_items();
$stampless['never'] = row_ids( drawn( $people ) );

unset( $GLOBALS['users'][16], $GLOBALS['users'][17], $GLOBALS['umeta'][16] );
WPCPM_Test_Role_Table::$as_role = WPCPM_Roles::ROLE_STUDENT;

ck( 'a role the plugin never invites, WordPress\'s Administrator role, has no stamp, and every reader of it reads its accounts as never invited, one holding a student\'s stamp included: the views, the lists they narrow to, a row\'s invitation and the two bulk actions',
	$stampless,
	array(
		'stamp'   => '',
		'views'   => array( 'all' => '2', 'invited' => '0', 'never-invited' => '2' ),
		'row'     => array( 'invite' ),
		'resend'  => array( array( 'invites-none', array( 'resend' => true, 'why' => 'never-invited' ) ), array(), array() ),
		'send'    => array( array( 'invites-queued', array( 'resend' => false, 'queued' => 1 ) ), array( 17 ), array( array( 'queue_invites', array( 17 ) ) ) ),
		'invited' => array(),
		'never'   => array( 16, 17 ),
	) );
ck( 'and its Invited view asks the one clause with the empty key alone in the list, which WordPress finds on no account',
	$stampless_invited,
	array(
		'relation' => 'OR',
		array(
			'key'     => array( '' ),
			'compare' => 'EXISTS',
		),
	) );

$module = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php' );

ck( 'the views link to the page the Students module is: wpcpm- and its ID',
	array( has( $module, "return 'wpcpm-' . \$this->id();" ), 1 === preg_match( "/public function id\(\) \{\s*return 'students';/", $students_src ) ),
	array( true, true ) );

echo "\n=== Loading: the base brings core's list table when core has not ===\n";

$root = wpcpm_test_temp_dir() . 'wordpress/';
mkdir( $root . 'wp-admin/includes', 0700, true );
file_put_contents( $root . 'wp-admin/includes/class-wp-list-table.php', "<?php\nclass WP_List_Table {}\n" );
mkdir( wpcpm_test_temp_dir() . 'empty', 0700, true );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-missing ' . escapeshellarg( $root ) . ' 2>&1', $missing_out, $missing_status );
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-present ' . escapeshellarg( wpcpm_test_temp_dir() . 'empty' ) . ' 2>&1', $present_out, $present_status );

$missing = (array) json_decode( implode( "\n", $missing_out ), true );
$present = (array) json_decode( implode( "\n", $present_out ), true );

ck( 'with core\'s list table not loaded, the base loads it from the WordPress root',
	array( $missing_status, isset( $missing['base'] ) ? $missing['base'] : null, isset( $missing['from'] ) ? realpath( $missing['from'] ) : implode( "\n", $missing_out ) ),
	array( 0, true, realpath( $root . 'wp-admin/includes/class-wp-list-table.php' ) ) );
ck( 'with it loaded, the base loads nothing (the root here has no wp-admin to load from)',
	array( $present_status, isset( $present['base'] ) ? $present['base'] : implode( "\n", $present_out ) ),
	array( 0, true ) );

echo "\n=== Loading: the plugin declares no list table until a screen needs one ===\n";

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child-plugin ' . escapeshellarg( $root ) . ' 2>&1', $plugin_out, $plugin_status );

$plugin = (array) json_decode( implode( "\n", $plugin_out ), true );

ck( 'the plugin loads as a site loads it, declaring no list table: not core\'s, not the base, not the Students table, not the Mentors table, not the Administrators table, not the Institutions table, not the Sponsors table',
	array( $plugin_status, isset( $plugin['loaded'] ) ? $plugin['loaded'] : implode( "\n", $plugin_out ) ),
	array( 0, array( false, false, false, false, false, false, false ) ) );
ck( 'its lazy loader brings core\'s list table from the WordPress root, then the base, the Students table, the Mentors table, the Administrators table, the Institutions table and the Sponsors table',
	array( isset( $plugin['lazy'] ) ? $plugin['lazy'] : null, isset( $plugin['after'] ) ? $plugin['after'] : null, isset( $plugin['from'] ) ? realpath( $plugin['from'] ) : null ),
	array( true, array( true, true, true, true, true, true, true ), realpath( $root . 'wp-admin/includes/class-wp-list-table.php' ) ) );

ck( 'and nothing asked a stand-in for anything it does not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
