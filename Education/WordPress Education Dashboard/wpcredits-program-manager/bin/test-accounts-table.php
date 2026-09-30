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
 * - The views count from the stamp an invitation leaves, read the way `WPCPM_Mail::never_invited()`
 *   reads it: the stamp being there is Invited and its absence is Never invited, which is the
 *   reading the invitations card counts by, and a stamp of 0 is still a stamp. The counts are the
 *   whole audience, as WordPress's own views are, not the search.
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
 *   the base, the Students table, the Mentors table and the Administrators table when a screen
 *   first needs one, so the front end, REST and cron never load core's list table for them. The
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
		return array( class_exists( 'WP_List_Table', false ), class_exists( 'WPCPM_Accounts_Table', false ), class_exists( 'WPCPM_Students_Table', false ), class_exists( 'WPCPM_Mentors_Table', false ), class_exists( 'WPCPM_Administrators_Table', false ) );
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
 * The role; every `meta_query` clause under AND, `EXISTS` and `NOT EXISTS` on whether the key is
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
 * Whether an account meets every clause of a meta query, which the table joins with AND.
 *
 * @param int   $id      User ID.
 * @param array $clauses The meta query.
 * @return bool
 */
function wpcpm_stub_meta_matches( $id, array $clauses ) {
	foreach ( $clauses as $name => $clause ) {
		if ( 'relation' === $name ) {
			if ( 'AND' !== strtoupper( (string) $clause ) ) {
				$GLOBALS['unmodeled'][] = 'meta_query relation ' . $clause;
			}
			continue;
		}

		$present = isset( $GLOBALS['umeta'][ $id ] ) && array_key_exists( $clause['key'], $GLOBALS['umeta'][ $id ] );
		$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';

		if ( 'EXISTS' === $compare ) {
			if ( ! $present ) {
				return false;
			}
		} elseif ( 'NOT EXISTS' === $compare ) {
			if ( $present ) {
				return false;
			}
		} else {
			$GLOBALS['unmodeled'][] = 'meta_query compare ' . $compare;
		}
	}

	return true;
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

require_once __DIR__ . '/stubs/class-wp-list-table.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';

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

		/** The stamps an invitation can leave, one a role: the queue and the guard read all four. */
		const STAMPS = array( 'wpcpm_student_invited', 'wpcpm_mentor_invited', 'wpcpm_inst_invited', 'wpcpm_sponsor_invited' );

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

/* ---- fixtures and helpers ------------------------------------------------ */

// Three students whose name order, username order and invitation state all differ, and a mentor.
$GLOBALS['users'][11] = new WP_User( 11, 'zbruno', 'Bruno Diaz', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][12] = new WP_User( 12, 'yada', 'Ada Kowalski', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][13] = new WP_User( 13, 'xcleo', 'Cleo Ahn', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][14] = new WP_User( 14, 'wdana', 'Dana Mentor', array( WPCPM_Roles::ROLE_MENTOR ) );

$GLOBALS['umeta'][11]['wpcpm_student_invited'] = 1790000000;
$GLOBALS['umeta'][14]['wpcpm_mentor_invited']  = 1790000000;

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

echo "\n=== The views: All, Invited, Never invited, counted from the invitation stamp ===\n";

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts' ) );
$GLOBALS['queries'] = array();
$table              = new WPCPM_Test_Accounts_Table();
$views              = $table->views_now();

ck( 'three views, in this order', array_keys( $views ), array( 'all', 'invited', 'never-invited' ) );
ck( 'counted from the stamp: one student invited, two never', view_counts( $views ), array( 'all' => '3', 'invited' => '1', 'never-invited' => '2' ) );

$clauses = array();
foreach ( $GLOBALS['queries'] as $args ) {
	foreach ( (array) ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() ) as $clause ) {
		$clauses[] = array( $args['role'], $clause['key'], $clause['compare'] );
	}
}

ck( 'the counts ask for the stamp WPCPM_Mail writes for the audience\'s role, there and not there',
	$clauses,
	array( array( 'wpcpm_student', 'wpcpm_student_invited', 'EXISTS' ), array( 'wpcpm_student', 'wpcpm_student_invited', 'NOT EXISTS' ) ) );
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
ck( 'by asking the list query for the stamp not being there',
	at( list_query(), 'meta_query' ), array( array( 'key' => 'wpcpm_student_invited', 'compare' => 'NOT EXISTS' ) ) );

request( array( 'page' => 'wpcpm-students', 'tab' => 'accounts', 'wpcpm_view' => 'invited' ) );
$table = new WPCPM_Test_Accounts_Table();
$table->prepare_items();

ck( 'Invited lists the accounts with the stamp', row_ids( drawn( $table ) ), array( 11 ) );

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

$nonce = wp_create_nonce( 'wpcpm_students_invite' );

ck( 'Send invite links to the audience\'s invite handler, with the account, the tab and the handler\'s nonce',
	link_of( at( $never, 'invite' ) ),
	array( 'https://example.test/wp-admin/admin-post.php', array( 'action' => 'wpcpm_students_invite', 'user' => '12', 'wpcpm_tab' => 'accounts', '_wpnonce' => $nonce ) ) );
ck( 'Resend invite goes to the same handler, for its own account',
	link_of( at( $again, 'reinvite' ) ),
	array( 'https://example.test/wp-admin/admin-post.php', array( 'action' => 'wpcpm_students_invite', 'user' => '11', 'wpcpm_tab' => 'accounts', '_wpnonce' => $nonce ) ) );

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
$students_act = preg_match( "/const ACTION_INVITE = '([a-z_]+)';/", $students_src, $found ) ? $found[1] : 'no ACTION_INVITE in the Students module';
$mentors_act  = preg_match( "/const ACTION_INVITE = '([a-z_]+)';/", $mentors_src, $found ) ? $found[1] : 'no ACTION_INVITE in the Mentors module';

ck( 'the handler is the one each module has: the Students\' and the Mentors\' ACTION_INVITE',
	array( WPCPM_Test_Accounts_Table::action(), WPCPM_Test_Mentors_Table::action() ), array( $students_act, $mentors_act ) );

$mentors = new WPCPM_Test_Mentors_Table();
$dana    = $mentors->actions_for( $GLOBALS['users'][14] );

ck( 'and a mentor already invited is offered Resend invite through the Mentors\' handler',
	array( array_keys( $dana ), at( link_of( at( $dana, 'reinvite' ) )[1], 'action' ), at( link_of( at( $dana, 'reinvite' ) )[1], '_wpnonce' ) ),
	array( array( 'reinvite' ), 'wpcpm_mentors_invite', wp_create_nonce( 'wpcpm_mentors_invite' ) ) );

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
// declaring one of them again, whose copy would quietly win over the trait's, fails here, and so does
// a method moved back out of the trait.
preg_match_all( '/^\t(?:(?:public|protected|private|static)\s+)*function\s+(\w+)\s*\(/m', $screen_code, $screen_methods );

$declared_again = function ( $code ) use ( $screen_methods ) {
	preg_match_all( '/function\s+(\w+)\s*\(/', $code, $own );

	return array_values( array_intersect( $screen_methods[1], $own[1] ) );
};
$uses_screen    = function ( $code ) {
	return 1 === preg_match( '/^\tuse WPCPM_Accounts_Screen;$/m', $code );
};

ck( 'the screen plumbing the Students and Mentors modules held twice is one trait\'s, which both use without declaring one of its methods again',
	array(
		'trait'    => array_values( array_diff( array( 'hook_screen', 'load_screen', 'handle_list_form', 'save_per_page', 'invite_selected', 'leave', 'accounts_url', 'list_url', 'table', 'accounts_messages', 'notice_sentence', 'tab', 'tab_labels', 'tab_field', 'render_admin_page', 'render_tab_accounts', 'render_not_connected', 'never_invited', 'handle_invite' ), $screen_methods[1] ) ),
		'students' => array( $uses_screen( $students_code ), $declared_again( $students_code ) ),
		'mentors'  => array( $uses_screen( $mentors_code ), $declared_again( $mentors_code ) ),
	),
	array(
		'trait'    => array(),
		'students' => array( true, array() ),
		'mentors'  => array( true, array() ),
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

echo "\n=== The stamp and the screen the table reads from the rest of the plugin ===\n";

$mail = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php' );
preg_match( '/public static function drain_queue\(\).*?\n\t}\n/s', $mail, $drain );
preg_match_all( "/WPCPM_Roles::ROLE_([A-Z]+) \) \) \{\s*\\\$meta = '([a-z_]+)';/", isset( $drain[0] ) ? $drain[0] : '', $pairs, PREG_SET_ORDER );

$written = array();
foreach ( $pairs as $pair ) {
	$written[ constant( 'WPCPM_Roles::ROLE_' . $pair[1] ) ] = $pair[2];
}
$written[ WPCPM_Roles::ROLE_STUDENT ] = preg_match( "/\} else \{\s*\\\$meta = '([a-z_]+)';/", isset( $drain[0] ) ? $drain[0] : '', $found ) ? $found[1] : 'no last branch in drain_queue()';

$read = array();
foreach ( array_keys( $written ) as $role ) {
	WPCPM_Test_Role_Table::$as_role = $role;
	$read[ $role ]                  = WPCPM_Test_Role_Table::stamp();
}

ck( 'each role\'s accounts are read by the stamp WPCPM_Mail::drain_queue() writes for that role',
	array( count( $written ), $read ), array( 4, $written ) );
ck( 'which for the two audiences here is theirs', array( WPCPM_Test_Accounts_Table::stamp(), WPCPM_Test_Mentors_Table::stamp() ), array( 'wpcpm_student_invited', 'wpcpm_mentor_invited' ) );

// WordPress's Administrator role is one the plugin never invites, so no stamp is its audience's: had
// its accounts been read by the student stamp, drain_queue()'s last branch, an administrator invited
// as a student would count as an invited administrator, without a word. Hana is one: an
// administrator holding a student's stamp. Ivo is an administrator holding none.
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

ck( 'the plugin loads as a site loads it, declaring no list table: not core\'s, not the base, not the Students table, not the Mentors table, not the Administrators table',
	array( $plugin_status, isset( $plugin['loaded'] ) ? $plugin['loaded'] : implode( "\n", $plugin_out ) ),
	array( 0, array( false, false, false, false, false ) ) );
ck( 'its lazy loader brings core\'s list table from the WordPress root, then the base, the Students table, the Mentors table and the Administrators table',
	array( isset( $plugin['lazy'] ) ? $plugin['lazy'] : null, isset( $plugin['after'] ) ? $plugin['after'] : null, isset( $plugin['from'] ) ? realpath( $plugin['from'] ) : null ),
	array( true, array( true, true, true, true, true ), realpath( $root . 'wp-admin/includes/class-wp-list-table.php' ) ) );

ck( 'and nothing asked a stand-in for anything it does not model', $GLOBALS['unmodeled'], array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
