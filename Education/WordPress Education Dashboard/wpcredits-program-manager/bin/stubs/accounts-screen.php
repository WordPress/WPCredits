<?php
/**
 * WordPress, as far as an audience's accounts screen reaches it, for the suites that draw one.
 *
 * bin/test-students-screen.php and bin/test-mentors-screen.php each carried a copy of these stand-ins,
 * so a fix to how one of them modeled core would not have reached the other. What they model: the
 * port of core's `add_query_arg()`, which re-encodes what an address already holds and sets a given
 * value as it is given, so a value the screen forgets to encode breaks its link here as it would on
 * the site; core's `esc_url()` and `wp_nonce_url()`; the nonce check, which dies with core's sentence;
 * the redirect and the death, thrown, so a check reads where a handler was sending the browser; and
 * the accounts query (`WP_User_Query`), answered over the fixture accounts the way WordPress answers
 * the arguments a screen passes, anything else it is asked kept apart for the suite's last check;
 * and core's `remove_accents()`, for the names a list that orders its accounts itself compares.
 * Each suite requires this file from its header, after its constants, and keeps its own fixtures, its
 * own stand-ins for the plugin's other classes and its checks.
 *
 * The state the stand-ins keep is set here, in globals a check reads and resets. A few are switches
 * a suite or a check turns: `query_include`, for a screen that narrows its list to IDs of its own
 * reading, as the Students screen does to its institution and its search; `ids_as_rows`, for the
 * answer the program's own site gives a request for IDs; `no_editor`, for a manager who may not open
 * the accounts' editor; `list_screen`, for a table built on a screen; and `l10n`, for a screen drawn
 * in another language.
 *
 * The capability is bin/stubs/caps.php's, which each suite requires beside this file, and the list
 * table is bin/stubs/class-wp-list-table.php, which each suite's own `wpcpm_load_accounts_tables()`
 * loads, as the plugin's loader loads core's.
 *
 * bin/test-administrators-screen.php requires this file too, and the three suites read what a screen
 * drew with the helpers they share beside it (bin/stubs/screen-helpers.php).
 *
 * Loaded with `require_once __DIR__ . '/stubs/accounts-screen.php';` from a suite's header.
 */

$GLOBALS['uid']              = 1;       // The program manager looking at the screen.
$GLOBALS['caps']             = true;    // Whether that person holds the program's capability.
$GLOBALS['users']            = array(); // User ID => WP_User.
$GLOBALS['umeta']            = array(); // User ID => meta key => value.
$GLOBALS['opts']             = array(); // Option => value: the invitation queue and its run, the sync's state.
$GLOBALS['hooks']            = array(); // Hook => every callback added to it, with its priority and argument count.
$GLOBALS['queries']          = array(); // Every WP_User_Query's arguments, in order.
$GLOBALS['reads']            = array(); // Every read of an account: a query, a lookup, a meta read.
$GLOBALS['mails']            = array(); // The accounts wp_new_user_notification() was asked to write to.
$GLOBALS['scheduled']        = array(); // Cron hook => the time it is next due.
$GLOBALS['nonce_checks']     = array(); // The action of every nonce check_admin_referer() was asked about.
$GLOBALS['screen_options']   = array(); // Option => the arguments add_screen_option() was given.
$GLOBALS['admin_page_hooks'] = array(); // Menu slug => the name core files its pages' hooks under, once the menu exists.
$GLOBALS['unmodeled']        = array(); // Anything asked of a stand-in that it does not model.
$GLOBALS['query_include']    = false;   // Whether the screen narrows its list to IDs (`include`), which is modeled then.
$GLOBALS['ids_as_rows']      = false;   // Whether `fields` of `ID` answers with rows, as the program's own site does.
$GLOBALS['no_editor']        = false;   // Whether the manager may not open the accounts' editor.
$GLOBALS['current_screen']   = null;    // What get_current_screen() answers: set before each press.
$GLOBALS['list_screen']      = null;    // What convert_to_screen() answers when a table is built: none, unless a check plants one.
$GLOBALS['l10n']             = array(); // Text => its translation, for a check that draws the screen in another language.

/* ---- WordPress, as far as the screen reaches ----------------------------- */

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}

class WP_User {
	public $ID = 0, $display_name = '', $user_login = '', $user_email = '', $roles = array();
	public function __construct( $id, $name, array $roles, $login = '' ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_login   = '' !== $login ? $login : strtolower( str_replace( ' ', '', $name ) );
		$this->user_email   = $this->user_login . '@example.test';
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

/** What wp_safe_redirect() does here: it stops the handler and carries the address it was sending the browser to. */
class RedirectSignal extends Exception {}

/** What wp_die() does here: it stops the handler and carries what it said. */
class DieSignal extends Exception {}

/**
 * The accounts query, answered over the fixture accounts the way WordPress answers it.
 *
 * What a screen asks: the role; every `meta_query` clause joined by AND, `EXISTS` and `NOT EXISTS`
 * on whether the key is there; a search with a wildcard at both ends as "contains" over the columns
 * named, without regard to case, as MySQL compares; `orderby` by name, username or ID, one with the
 * query's `order` or the array form, each key an orderby and its value that key's order, a key
 * settling what the one before it leaves tied; `number` and `offset`, -1 being no limit; `fields`
 * of `ID`, answered as the numeric strings WordPress's column read returns, or as the rows the
 * program's own site returns when a check asks (`ids_as_rows`, see `WPCPM_Roles::id_of()`); the
 * total counted only when `count_total` asks, 0 otherwise. And `include`, which narrows to those IDs
 * and, as `orderby`, keeps their order, for a suite whose screen narrows its list to IDs of its own
 * reading (`query_include`): to any other suite it is something the stand-in does not model, so a
 * screen that has no reason to ask it fails its suite's last check the day it does. Rows that tie
 * on every key the order names come back as MySQL is free to return them, in any order and in
 * another for another LIMIT: here the lowest ID first on a first page and the highest first on any
 * later one, the least kind reading, so a list that leaves a tie to chance loses an account between
 * two pages here as it can on a site.
 */
class WP_User_Query {
	private $results = array();
	private $total   = 0;

	public function __construct( $args = array() ) {
		$GLOBALS['queries'][] = $args;
		$GLOBALS['reads'][]   = 'query';

		$modeled = array( 'role', 'number', 'offset', 'orderby', 'order', 'search', 'search_columns', 'meta_query', 'fields', 'count_total' );

		if ( $GLOBALS['query_include'] ) {
			$modeled[] = 'include';
		}

		foreach ( array_keys( $args ) as $key ) {
			if ( ! in_array( $key, $modeled, true ) ) {
				$GLOBALS['unmodeled'][] = 'WP_User_Query argument ' . $key;
			}
		}

		$include = ( $GLOBALS['query_include'] && ! empty( $args['include'] ) ) ? array_map( 'intval', (array) $args['include'] ) : null;
		$found   = array();

		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( ! empty( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) {
				continue;
			}

			if ( null !== $include && ! in_array( (int) $id, $include, true ) ) {
				continue;
			}

			if ( wpcpm_stub_meta_matches( $id, isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() ) && wpcpm_stub_search_matches( $user, $args ) ) {
				$found[] = $user;
			}
		}

		$orderby = isset( $args['orderby'] ) ? $args['orderby'] : 'login';

		if ( $GLOBALS['query_include'] && 'include' === $orderby ) {
			if ( null === $include ) {
				$GLOBALS['unmodeled'][] = 'WP_User_Query orderby include without an include';
			}

			usort(
				$found,
				function ( $a, $b ) use ( $include ) {
					return array_search( $a->ID, (array) $include, true ) - array_search( $b->ID, (array) $include, true );
				}
			);
		} else {
			$fields = array( 'display_name' => 'display_name', 'login' => 'user_login', 'user_login' => 'user_login', 'ID' => 'ID' );
			$keys   = array();

			foreach ( wpcpm_stub_orderby( $args ) as $asked => $desc ) {
				if ( ! isset( $fields[ $asked ] ) ) {
					$GLOBALS['unmodeled'][] = 'WP_User_Query orderby ' . $asked;
					$asked                  = 'login';
				}

				$keys[] = array( $fields[ $asked ], $desc );
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
		}

		// WordPress counts the matches only when asked, and reports 0 otherwise.
		$this->total = ( ! isset( $args['count_total'] ) || $args['count_total'] ) ? count( $found ) : 0;

		if ( isset( $args['number'] ) && (int) $args['number'] > 0 ) {
			$found = array_slice( $found, isset( $args['offset'] ) ? (int) $args['offset'] : 0, (int) $args['number'] );
		}

		if ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) {
			$found = array_map(
				function ( $user ) {
					return $GLOBALS['ids_as_rows'] ? (object) array( 'ID' => (string) $user->ID ) : (string) $user->ID;
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
 * Whether an account meets every clause of a meta query, joined with AND.
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
 * Whether an account matches the query's search, when it has one: "contains", over the named columns.
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

function get_users( $args = array() ) {
	$query = new WP_User_Query( $args );

	return (array) $query->get_results();
}
function get_user_by( $field, $value ) {
	$GLOBALS['reads'][] = 'user ' . $value;

	return isset( $GLOBALS['users'][ (int) $value ] ) ? $GLOBALS['users'][ (int) $value ] : false;
}
function wp_get_current_user() {
	return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0, '', array() );
}
function get_current_user_id() {
	return (int) $GLOBALS['uid'];
}
function get_user_meta( $id, $key, $single = false ) {
	$GLOBALS['reads'][] = 'meta ' . (int) $id . ' ' . $key;

	return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ( $single ? '' : array() );
}
function update_user_meta( $id, $key, $value ) {
	$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;

	return true;
}
function delete_user_meta( $id, $key ) {
	unset( $GLOBALS['umeta'][ (int) $id ][ $key ] );

	return true;
}
function metadata_exists( $type, $id, $key ) {
	$GLOBALS['reads'][] = 'meta ' . (int) $id . ' ' . $key;

	return 'user' === $type && isset( $GLOBALS['umeta'][ (int) $id ] ) && array_key_exists( $key, $GLOBALS['umeta'][ (int) $id ] );
}
function get_user_option( $option, $user = 0 ) {
	$user = $user ? (int) $user : get_current_user_id();

	return isset( $GLOBALS['umeta'][ $user ][ $option ] ) ? $GLOBALS['umeta'][ $user ][ $option ] : false;
}
function get_edit_user_link( $id ) {
	return $GLOBALS['no_editor'] ? '' : 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id;
}
function wp_new_user_notification( $id, $deprecated = null, $notify = '' ) {
	$GLOBALS['mails'][] = (int) $id;
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;

	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['opts'][ $key ] );

	return true;
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['scheduled'][ $hook ] ) ? $GLOBALS['scheduled'][ $hook ] : false;
}
function wp_schedule_single_event( $time, $hook ) {
	$GLOBALS['scheduled'][ $hook ] = (int) $time;

	return true;
}
function wp_get_scheduled_event( $hook ) {
	return false;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['scheduled'][ $hook ] );
}
function wp_schedule_event( $time, $recurrence, $hook ) {
	$GLOBALS['scheduled'][ $hook ] = (int) $time;

	return true;
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function __( $text, $domain = 'default' ) {
	return isset( $GLOBALS['l10n'][ $text ] ) ? $GLOBALS['l10n'][ $text ] : $text;
}
function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
}
function esc_attr( $text ) {
	return esc_html( $text );
}
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( __( $text, $domain ) );
}
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( __( $text, $domain ) );
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
/**
 * Core's `remove_accents()`, as far as the fixtures' names reach it: each accented letter of the
 * Latin-1 Supplement and each Polish letter of Latin Extended-A as its plain letter or letters, the
 * way core's own table maps them, Ł to L among them, which Unicode's decomposition leaves as it is.
 * A text still holding a letter the map lacks is kept apart for the suite's last check, since core
 * would read it and this would not.
 *
 * @param string $text   The text.
 * @param string $locale The locale, which core reads for a few letters; none of the fixtures' names.
 * @return string
 */
function remove_accents( $text, $locale = '' ) {
	$plain = strtr(
		(string) $text,
		array(
			'ª' => 'a', 'º' => 'o', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
			'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
			'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
			'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 's', 'à' => 'a', 'á' => 'a', 'â' => 'a',
			'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
			'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
			'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th',
			'ÿ' => 'y', 'Ą' => 'A', 'ą' => 'a', 'Ć' => 'C', 'ć' => 'c', 'Ę' => 'E', 'ę' => 'e', 'Ł' => 'L', 'ł' => 'l',
			'Ń' => 'N', 'ń' => 'n', 'Ś' => 'S', 'ś' => 's', 'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z',
		)
	);

	if ( preg_match( '/[\x80-\xff]/', $plain ) ) {
		$GLOBALS['unmodeled'][] = 'remove_accents() of ' . $text;
	}

	return $plain;
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
}
function absint( $value ) {
	return abs( (int) $value );
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
 * Core's `_http_build_query()` as `build_query()` calls it: the values as given, nothing encoded.
 *
 * @param array  $data The arguments.
 * @param string $key  The parent key, for a nested list.
 * @return string
 */
function wpcpm_stub_build_query( $data, $key = '' ) {
	$ret = array();

	foreach ( (array) $data as $k => $v ) {
		if ( '' !== $key ) {
			$k = $key . '%5B' . $k . '%5D';
		}

		if ( null === $v ) {
			continue;
		} elseif ( false === $v ) {
			$v = '0';
		}

		$ret[] = is_array( $v ) ? wpcpm_stub_build_query( $v, $k ) : $k . '=' . $v;
	}

	return implode( '&', $ret );
}
/**
 * Core's `urlencode_deep()`.
 *
 * @param mixed $value A value or a list of them.
 * @return mixed
 */
function wpcpm_stub_urlencode_deep( $value ) {
	return is_array( $value ) ? array_map( 'wpcpm_stub_urlencode_deep', $value ) : urlencode( (string) $value );
}
/**
 * Core's `add_query_arg()`, both shapes: what the address already holds is re-encoded, a value given
 * here goes in as given (the caller encodes it, as core's documentation asks), and false removes.
 *
 * @param mixed ...$args The arguments, then the address, or the request's own address when there is none.
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$uri = ( count( $args ) < 2 || false === $args[1] ) ? $_SERVER['REQUEST_URI'] : $args[1];
	} else {
		$uri = ( count( $args ) < 3 || false === $args[2] ) ? $_SERVER['REQUEST_URI'] : $args[2];
	}

	$frag = strstr( (string) $uri, '#' );

	if ( $frag ) {
		$uri = substr( $uri, 0, -strlen( $frag ) );
	} else {
		$frag = '';
	}

	if ( 0 === stripos( $uri, 'https://' ) ) {
		$protocol = 'https://';
		$uri      = substr( $uri, 8 );
	} elseif ( 0 === stripos( $uri, 'http://' ) ) {
		$protocol = 'http://';
		$uri      = substr( $uri, 7 );
	} else {
		$protocol = '';
	}

	if ( false !== strpos( $uri, '?' ) ) {
		list( $base, $query ) = explode( '?', $uri, 2 );
		$base                .= '?';
	} elseif ( $protocol || false === strpos( $uri, '=' ) ) {
		$base  = $uri . '?';
		$query = '';
	} else {
		$base  = '';
		$query = $uri;
	}

	parse_str( $query, $qs );
	$qs = wpcpm_stub_urlencode_deep( $qs );

	if ( is_array( $args[0] ) ) {
		foreach ( $args[0] as $k => $v ) {
			$qs[ $k ] = $v;
		}
	} else {
		$qs[ $args[0] ] = $args[1];
	}

	foreach ( $qs as $k => $v ) {
		if ( false === $v ) {
			unset( $qs[ $k ] );
		}
	}

	$ret = trim( wpcpm_stub_build_query( $qs ), '?' );
	$ret = preg_replace( '#=(&|$)#', '$1', $ret );
	$ret = rtrim( $protocol . $base . $ret . $frag, '?' );

	return str_replace( '?#', '#', $ret );
}
function remove_query_arg( $key, $query = false ) {
	foreach ( (array) $key as $k ) {
		$query = add_query_arg( $k, false, $query );
	}

	return $query;
}
function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . substr( md5( (string) $action ), 0, 10 );
}
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	return esc_html( add_query_arg( $name, wp_create_nonce( $action ), str_replace( '&amp;', '&', $url ) ) );
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$field = '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';

	if ( $referer ) {
		$field .= '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) . '" />';
	}

	if ( $display ) {
		echo $field;
	}

	return $field;
}
/**
 * Core's nonce check, which dies with core's sentence when the request carries anything but the
 * nonce for this action. Every action asked about is kept, so a check can see what was asked first.
 *
 * @param string $action    The nonce action.
 * @param string $query_arg Where the request carries it.
 * @return int
 */
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['nonce_checks'][] = $action;

	if ( ! isset( $_REQUEST[ $query_arg ] ) || wp_create_nonce( $action ) !== $_REQUEST[ $query_arg ] ) {
		throw new DieSignal( 'The link you followed has expired.' );
	}

	return 1;
}
function wp_safe_redirect( $location ) {
	throw new RedirectSignal( (string) $location );
}
function wp_die( $message = '', $title = '', $args = array() ) {
	throw new DieSignal( is_string( $message ) ? $message : 'died' );
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = array( $callback, (int) $priority, (int) $accepted_args );

	return true;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_action( $hook, $callback, $priority, $accepted_args );
}
/**
 * Take a callback off a hook, as WordPress does: the same callback at the same priority. The accounts
 * base calls it for a table built on a screen; the screen here gives the list none.
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
		if ( $added[0] === $callback && $added[1] === (int) $priority ) {
			$found = true;
			continue;
		}

		$kept[] = $added;
	}

	$GLOBALS['hooks'][ $hook ] = $kept;

	return $found;
}
/**
 * Run the filters added to a hook, lowest priority first, each handed as many arguments as it takes.
 *
 * @param string $hook    Hook name.
 * @param mixed  $value   The value to filter.
 * @param mixed  ...$args The rest of the caller's arguments.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	$added = isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array();

	usort(
		$added,
		function ( $a, $b ) {
			return $a[1] - $b[1];
		}
	);

	foreach ( $added as $filter ) {
		$value = call_user_func_array( $filter[0], array_slice( array_merge( array( $value ), $args ), 0, $filter[2] ) );
	}

	return $value;
}
function convert_to_screen( $hook_name ) {
	return $GLOBALS['list_screen'];
}
/**
 * The screen the page is drawn on, as far as the load hook asks anything of it: the headings
 * WordPress prints for screen readers above a list, kept as they were set.
 */
class WPCPM_Stub_Screen {
	public $reader_content = array();
	public function set_screen_reader_content( $content = array() ) {
		$this->reader_content = $content;
	}
}
function get_current_screen() {
	return $GLOBALS['current_screen'];
}
/**
 * Core's priming of accounts and their meta, two queries whatever the count: recorded as one read.
 *
 * @param int[] $ids User IDs.
 */
function cache_users( $ids ) {
	$GLOBALS['reads'][] = 'cache ' . implode( ',', (array) $ids );
}
function add_screen_option( $option, $args = array() ) {
	$GLOBALS['screen_options'][ $option ] = $args;
}
/**
 * Core's name for a plugin page's hooks: the parent menu's registered name, `_page_`, the page's slug.
 * Before the menu exists there is no registered name, and core files the page under `admin`.
 *
 * @param string $plugin_page The page's slug.
 * @param string $parent_page The parent menu's slug.
 * @return string
 */
function get_plugin_page_hookname( $plugin_page, $parent_page ) {
	$type = isset( $GLOBALS['admin_page_hooks'][ $parent_page ] ) ? $GLOBALS['admin_page_hooks'][ $parent_page ] : 'admin';

	return $type . '_page_' . $plugin_page;
}
/**
 * Core's submit button, as `get_submit_button()` shapes it: `button` and the type's classes, the
 * name, and the ID from the extra attributes or the name.
 *
 * @param string       $text  The label.
 * @param string       $type  The type's classes.
 * @param string       $name  The name.
 * @param bool         $wrap  Whether to wrap it in a paragraph.
 * @param array|string $other Extra attributes.
 */
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other = '' ) {
	$classes = array( 'button' );

	foreach ( explode( ' ', (string) $type ) as $one ) {
		if ( '' === $one || 'secondary' === $one ) {
			continue;
		}

		$classes[] = in_array( $one, array( 'primary', 'small', 'large' ), true ) ? 'button-' . $one : $one;
	}

	$id     = is_array( $other ) && isset( $other['id'] ) ? $other['id'] : $name;
	$button = '<input type="submit" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" value="' . esc_attr( $text ) . '" />';

	echo $wrap ? '<p class="submit">' . $button . '</p>' : $button;
}

/**
 * Core's time difference in words, as the sync card and the invitations card print it: one answer,
 * since no check reads the words.
 *
 * @param int $from A time.
 * @param int $to   Another.
 * @return string
 */
function human_time_diff( $from, $to = 0 ) {
	return '5 mins';
}
function esc_js( $text ) {
	return addslashes( htmlspecialchars( (string) $text, ENT_COMPAT, 'UTF-8' ) );
}
/**
 * Core's reading of where a form was sent from: the `_wp_http_referer` it posted, or the browser's
 * referer, unless that is the request's own address.
 *
 * @return string|false
 */
function wp_get_referer() {
	if ( isset( $_REQUEST['_wp_http_referer'] ) ) {
		$ref = wp_unslash( $_REQUEST['_wp_http_referer'] );
	} elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {
		$ref = wp_unslash( $_SERVER['HTTP_REFERER'] );
	} else {
		$ref = '';
	}

	return ( '' !== $ref && $ref !== $_SERVER['REQUEST_URI'] ) ? $ref : false;
}
