<?php
/**
 * The sponsors store (1.111.0): one private post per sponsor, replacing the option the sync
 * used to write every three hours.
 *
 * What is pinned here and why:
 *
 * - The post type is under WordPress's twenty-character limit and private everywhere: no public
 *   URL, no admin list, no REST route.
 * - `is_key()` accepts both shapes a sponsor's key can take - a migrated Airtable record ID, or
 *   a minted `spo` key of the same length - and nothing else.
 * - `insert()` is the one write path: it creates a post for a key nothing holds yet, or replaces
 *   the row of the post that already carries the key. The slug is set once, from the name, and a
 *   later insert() over the same key never moves it, however the name changes.
 * - `rows()`, `row()`, `has()`, `approved()` and `status_counts()` read the store built from a
 *   single `get_posts()` call, cached for the request and dropped by `forget()`, which every
 *   writer calls, so a read right after a write never sees the row it replaced. `rows()` shapes
 *   each row it reads, so a row an older release stored carries every key `empty_row()` names,
 *   the six tier keys of 1.112.0 and the report stamp of 1.115.0 included, as empty strings, and the
 *   owed term of 1.115.0 and the event allowance as empty arrays; an owed term is a tier and a day
 *   with the term's funded line, or nothing, and an event allowance a day and a whole count of one
 *   or more, or nothing.
 * - `row_by_slug()` answers the row a slug names, and null for a slug no sponsor holds or an empty
 *   one: the badge route has a slug and no key, and reads the one post of that name, never every
 *   sponsor. The request's spelling is decoded once and asked for as core stores a name, so a slug
 *   core stored percent-encoded is found however a request spells it; the post found must be that
 *   slug decoded once, so a slug encoded twice, or one core's query folds into a stored name,
 *   names nothing.
 * - `patch()` changes only the keys it is given, merged into the row as the table holds it at the
 *   write: a member's profile save that read the row before a manager's save landed from another
 *   request keeps the manager's Pause, tier and term, and a request that dropped its own row cache
 *   but still holds the object cache's copy drops that too before it reads, so a renewal saved in
 *   between is kept beside the daily run's stamp; the post's status is read past that copy as well,
 *   so a sponsor another request trashed since takes no patch.
 * - `manager_of()` resolves a row's `manager` as a site user ID, not a Team Members record, and
 *   only while that account holds the program's capability: a manager who left the team is
 *   nobody's contact, though the row keeps the id. `manager_label()` names that id for the
 *   program's own screens: the name while the account holds the capability, the name marked
 *   "(not an Administrator now)" once it does not, the id so marked for an account that is gone,
 *   and nothing for nobody.
 * - `write()` is not a path production code calls; it is the one bulk insert a suite seeds a
 *   store with: insert every well-formed row of a batch, skip the rest, report how many landed.
 * - `delete_all()` clears every sponsor post, the logo options, the old option and the
 *   migration's report, and leaves every other post alone.
 * - The migration (`WPCPM_Sponsors_Migration`, design decision 7) turns the option the sponsors
 *   sync held into posts under the same keys, whatever the option's `v` says; maps each
 *   manager by address to an account that holds the program capability, and names the
 *   sponsors it could not map; seeds a draft offer where a row carried offer text and the
 *   sponsor has no offer, on every run until the offer exists, and names the sponsors still
 *   waiting for one; seeds a program manager's booking link from the team once; and removes the
 *   old option, the team, the sync's six options, its two hooks and the marks the agreement left
 *   for it only when every row is a post and every draft offer due exists. A later run never
 *   writes over a row the store already holds, and `maybe_run()` does nothing once the report
 *   says complete and at most once a minute before. A run takes its lock option with
 *   `add_option()` before it reads anything, does nothing at all while another run holds it,
 *   gives it back when it is done, and takes over a lock older than the timeout (the review of
 *   1.111.0).
 *
 * The stand-ins below model enough of WordPress for a private post type with two meta values:
 * `wp_insert_post()`/`wp_update_post()` unslash the post array as core does, and
 * `wp_insert_post()` fails for the title `$GLOBALS['insert_fail']` names; `get_posts()`
 * honors `post_type`, `post_status` (including `any`, meaning everything but trash), `name`
 * (matched on the post's name as core's query matches it, the name asked for folded as core folds
 * it: lowercase, a dot a dash, what a slug cannot hold dropped),
 * `meta_key`/`meta_value`, `fields`, `numberposts` and ascending post ID order;
 * `wp_unique_post_slug()` appends `-2`, `-3` on a clash; `wp_generate_password()` is
 * deterministic, from a counter, so a collision can be staged on purpose. `get_option()`
 * counts its reads by name and `update_option()` keeps the autoload flag it was given, so the
 * migration's throttle and its report can be seen; `add_option()` is the test-and-set the
 * migration's lock is taken with, keeping its flag the same way, and `wp_insert_post()` records
 * whether that lock was held when it made each post. The booking link is the real
 * `WPCPM_Manager_Booking` over `$GLOBALS['umeta']`; `WPCPM_Sponsor_Offers` is a stand-in whose
 * `offers_of()` reads `$GLOBALS['offers']` and whose `seed()` records what it was given in
 * `$GLOBALS['seeded']` and, for the key `$GLOBALS['seed_fail']` names, fails and makes nothing;
 * `bin/stubs/caps.php` makes the IDs in `$GLOBALS['manage']` program managers. While
 * `$GLOBALS['object_cache']` is on, `get_post_meta()` and `get_post()` answer from this request's
 * own copy of a post's meta and of the post, as core's object cache does, until a write of its own
 * or `wp_cache_delete()` drops it; `as_another_request()` runs a step with caches of its own, whose
 * writes reach the table alone. The post stand-ins are this suite's own rather than `bin/stubs/posts.php`'s: they
 * are a superset of that file's, and the slash round trip below needs the real slash pair
 * where that file's is identity on purpose. The file is loaded for the one thing the two share,
 * the fold of a `name` query (`wpcpm_test_query_name()`).
 *
 * Run from the plugin root:  php bin/test-sponsors-store.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['posts']         = array();
$GLOBALS['pmeta']         = array();
$GLOBALS['opts']          = array();
$GLOBALS['post_types']    = array();
$GLOBALS['users']         = array();
$GLOBALS['umeta']         = array();
$GLOBALS['manage']        = array();
$GLOBALS['next_post_id']  = 100;
$GLOBALS['pw_seq']        = 0;
$GLOBALS['option_reads']  = array(); // Option name => how many times get_option() read it.
$GLOBALS['autoload']      = array(); // Option name => the autoload flag update_option() or add_option() was given.
$GLOBALS['transients']    = array();
$GLOBALS['transient_ttl'] = array();
$GLOBALS['cleared']       = array(); // Each hook wp_clear_scheduled_hook() was asked to clear.
$GLOBALS['offers']        = array(); // Sponsor key => the offers it has.
$GLOBALS['seeded']        = array(); // Each seed() call: the key and the legacy values.
$GLOBALS['insert_fail']   = '';      // A post title wp_insert_post() refuses.
$GLOBALS['lock_seen']     = array(); // For each post wp_insert_post() made, whether the migration's lock was held.
$GLOBALS['seed_fail']     = '';      // A sponsor key the offers stand-in's seed() refuses.
$GLOBALS['post_updates']  = array(); // Each wp_update_post() call: the postarr it was given.
$GLOBALS['object_cache']  = false;   // Whether get_post_meta() answers from this request's own copy, as core's object cache does.
$GLOBALS['meta_cache']    = array(); // That copy: post ID => its meta, as first read.
$GLOBALS['post_cache']    = array(); // And of each post: post ID => the post, as first read.
$GLOBALS['cache_deleted'] = array(); // Each wp_cache_delete() call, as group:key.

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

class WP_Post {
	public $ID = 0;
	public $post_type = 'post';
	public $post_status = 'draft';
	public $post_title = '';
	public $post_name = '';
	public $post_author = 0;

	public function __construct( array $fields = array() ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_User {
	public $ID = 0;
	public $display_name = '';
	public $user_email = '';

	public function __construct( $id, $display_name = '', $user_email = '' ) {
		$this->ID           = (int) $id;
		$this->display_name = $display_name;
		$this->user_email   = $user_email;
	}
}

/** Enough of the offers class for the migration: which offers a sponsor has, and what seed() was given. */
class WPCPM_Sponsor_Offers {
	public static function offers_of( $record ) {
		return isset( $GLOBALS['offers'][ $record ] ) ? $GLOBALS['offers'][ $record ] : array();
	}

	public static function seed( $record, $legacy = array() ) {
		$GLOBALS['seeded'][] = array( $record, $legacy );

		// As the real seed() answers when wp_insert_post() refuses the offer: an error, and no offer.
		if ( $GLOBALS['seed_fail'] === $record ) {
			return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' );
		}

		$id = 5000 + count( $GLOBALS['seeded'] );

		$GLOBALS['offers'][ $record ][ $id ] = array( 'id' => $id );

		return $id;
	}
}

function __( $text, $domain = null ) {
	return $text;
}

function wp_slash( $value ) {
	return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
}

function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
}

function is_email( $email ) {
	return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function register_post_type( $type, $args ) {
	$GLOBALS['post_types'][ $type ] = $args;
}

function get_option( $key, $default = false ) {
	$GLOBALS['option_reads'][ $key ] = ( isset( $GLOBALS['option_reads'][ $key ] ) ? $GLOBALS['option_reads'][ $key ] : 0 ) + 1;

	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ]     = $value;
	$GLOBALS['autoload'][ $key ] = $autoload;
	return true;
}

// The test-and-set the migration's lock is taken with: true only for the call that creates the row.
function add_option( $key, $value = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['opts'] ) ) {
		return false;
	}

	$GLOBALS['opts'][ $key ]     = $value;
	$GLOBALS['autoload'][ $key ] = $autoload;
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['opts'][ $key ] );
	return true;
}

function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
}

function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( trim( (string) $value ) ) ) {
			return $user;
		}

		if ( 'id' === $field && (int) $user->ID === (int) $value ) {
			return $user;
		}
	}

	return false;
}

function get_user_meta( $id, $key = '', $single = false ) {
	return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : '';
}

function update_user_meta( $id, $key, $value ) {
	$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;
	return true;
}

function delete_user_meta( $id, $key ) {
	unset( $GLOBALS['umeta'][ (int) $id ][ $key ] );
	return true;
}

function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['transients'][ $key ]    = $value;
	$GLOBALS['transient_ttl'][ $key ] = $expiration;
	return true;
}

function wp_clear_scheduled_hook( $hook, $args = array() ) {
	$GLOBALS['cleared'][] = $hook;
	return 0;
}

/**
 * Core's rule, in the part the booking class leans on: a URL whose scheme is not on the list
 * comes back empty, and so does one with no scheme at all.
 *
 * @param string     $url       The URL.
 * @param array|null $protocols The schemes allowed.
 * @return string
 */
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );

	if ( '' === $url || false === strpos( $url, ':' ) ) {
		return '';
	}

	$scheme = strtolower( substr( $url, 0, (int) strpos( $url, ':' ) ) );

	return in_array( $scheme, null === $protocols ? array( 'http', 'https' ) : (array) $protocols, true ) ? $url : '';
}

function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	return 'https://example.test/logo-' . (int) $id . '.png';
}

// Deterministic and alphanumeric: a run of the call counter's own digit, fourteen of them. Never
// realistic-looking (bin/test-fixtures.php would refuse a `rec`-shaped one; this is `spo`-shaped
// and out of that check's reach either way), and predictable enough to stage a collision on
// purpose for the mint_key() check.
function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	$GLOBALS['pw_seq'] = $GLOBALS['pw_seq'] + 1;

	return str_repeat( (string) ( $GLOBALS['pw_seq'] % 10 ), $length );
}

function wp_unique_post_slug( $slug, $post_id, $post_status, $post_type, $post_parent ) {
	$base = $slug;
	$n    = 2;

	while ( true ) {
		$clash = false;

		foreach ( $GLOBALS['posts'] as $post ) {
			if ( (int) $post->ID === (int) $post_id ) {
				continue;
			}

			if ( $post->post_type === $post_type && $post->post_name === $slug ) {
				$clash = true;
				break;
			}
		}

		if ( ! $clash ) {
			return $slug;
		}

		$slug = $base . '-' . $n;
		++$n;
	}
}

function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );

	// A database that refuses one row, so the migration's incomplete run can be staged.
	if ( '' !== $GLOBALS['insert_fail'] && isset( $postarr['post_title'] ) && $GLOBALS['insert_fail'] === $postarr['post_title'] ) {
		return $wp_error ? new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ) : 0;
	}

	$id = $GLOBALS['next_post_id']++;

	// Whether a run of the migration held its lock when it made this post.
	$GLOBALS['lock_seen'][] = array_key_exists( 'wpcpm_sponsors_migrate_lock', $GLOBALS['opts'] );

	$GLOBALS['posts'][ $id ] = new WP_Post(
		array(
			'ID'          => $id,
			'post_type'   => isset( $postarr['post_type'] ) ? $postarr['post_type'] : 'post',
			'post_status' => isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'draft',
			'post_title'  => isset( $postarr['post_title'] ) ? $postarr['post_title'] : '',
			'post_name'   => isset( $postarr['post_name'] ) ? $postarr['post_name'] : '',
			'post_author' => isset( $postarr['post_author'] ) ? (int) $postarr['post_author'] : 0,
		)
	);

	return $id;
}

function wp_update_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );
	$id      = (int) ( isset( $postarr['ID'] ) ? $postarr['ID'] : 0 );

	// So a check can tell whether patch() called this at all, not only what it did when it did.
	$GLOBALS['post_updates'][] = $postarr;

	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post' ) : 0;
	}

	foreach ( array( 'post_title', 'post_status', 'post_name' ) as $field ) {
		if ( isset( $postarr[ $field ] ) ) {
			$GLOBALS['posts'][ $id ]->$field = $postarr[ $field ];
		}
	}

	unset( $GLOBALS['post_cache'][ $id ] );

	return $id;
}

// While the object cache is on, a post is this request's own copy of it, taken at its first read,
// as the meta is: a status another request writes afterward reaches the table alone.
function get_post( $id ) {
	$id = (int) $id;

	if ( $GLOBALS['object_cache'] && isset( $GLOBALS['posts'][ $id ] ) ) {
		if ( ! isset( $GLOBALS['post_cache'][ $id ] ) ) {
			$GLOBALS['post_cache'][ $id ] = clone $GLOBALS['posts'][ $id ];
		}

		return $GLOBALS['post_cache'][ $id ];
	}

	return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null;
}

/** Folds a `name` query with bin/stubs/posts.php's `wpcpm_test_query_name()`, which this file loads for it. */
function get_posts( $args = array() ) {
	$out = array();

	$GLOBALS['get_posts_calls'][] = $args;

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( isset( $args['name'] ) && $post->post_name !== wpcpm_test_query_name( $args['name'] ) ) {
			continue;
		}

		if ( isset( $args['post_status'] ) ) {
			if ( 'any' === $args['post_status'] ) {
				if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					continue;
				}
			} elseif ( ! in_array( $post->post_status, (array) $args['post_status'], true ) ) {
				continue;
			}
		}

		if ( isset( $args['meta_key'] ) ) {
			$have = isset( $GLOBALS['pmeta'][ $post->ID ][ $args['meta_key'] ] ) ? $GLOBALS['pmeta'][ $post->ID ][ $args['meta_key'] ] : null;

			if ( null === $have || (string) $have !== (string) ( isset( $args['meta_value'] ) ? $args['meta_value'] : '' ) ) {
				continue;
			}
		}

		$out[] = $post;
	}

	usort(
		$out,
		static function ( $a, $b ) {
			return $a->ID - $b->ID;
		}
	);

	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $args['numberposts'] );
	}

	if ( 'ids' === ( isset( $args['fields'] ) ? $args['fields'] : '' ) ) {
		$out = array_map(
			static function ( $post ) {
				return $post->ID;
			},
			$out
		);
	}

	return $out;
}

/**
 * A post's meta: the table's, or, while `$GLOBALS['object_cache']` is on, this request's own copy of
 * it, taken at its first read and answered until a write of this request's own or
 * `wp_cache_delete()` drops it, as core's object cache answers. Another request's write reaches
 * the table alone.
 */
function get_post_meta( $id, $key = '', $single = false ) {
	$id = (int) $id;

	if ( $GLOBALS['object_cache'] ) {
		if ( ! array_key_exists( $id, $GLOBALS['meta_cache'] ) ) {
			$GLOBALS['meta_cache'][ $id ] = isset( $GLOBALS['pmeta'][ $id ] ) ? $GLOBALS['pmeta'][ $id ] : array();
		}

		return isset( $GLOBALS['meta_cache'][ $id ][ $key ] ) ? $GLOBALS['meta_cache'][ $id ][ $key ] : '';
	}

	return isset( $GLOBALS['pmeta'][ $id ][ $key ] ) ? $GLOBALS['pmeta'][ $id ][ $key ] : '';
}

// A write drops the writing request's own copy, as core's does, so its next read is the table's.
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );
	unset( $GLOBALS['meta_cache'][ (int) $id ] );
	return true;
}

function delete_post_meta( $id, $key ) {
	unset( $GLOBALS['pmeta'][ (int) $id ][ $key ], $GLOBALS['meta_cache'][ (int) $id ] );
	return true;
}

/** Core's object cache, for the one group the store drops: a post's meta. Every call is kept. */
function wp_cache_delete( $key, $group = '' ) {
	$GLOBALS['cache_deleted'][] = $group . ':' . $key;

	if ( 'post_meta' === $group ) {
		unset( $GLOBALS['meta_cache'][ (int) $key ] );
	}

	if ( 'posts' === $group ) {
		unset( $GLOBALS['post_cache'][ (int) $key ] );
	}

	return true;
}

// Core's answer: whether any post held the key.
function delete_post_meta_by_key( $key ) {
	$found = false;

	foreach ( array_keys( $GLOBALS['pmeta'] ) as $id ) {
		if ( array_key_exists( $key, $GLOBALS['pmeta'][ $id ] ) ) {
			unset( $GLOBALS['pmeta'][ $id ][ $key ] );
			$found = true;
		}
	}

	return $found;
}

function wp_delete_post( $id, $force = false ) {
	unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ], $GLOBALS['meta_cache'][ (int) $id ], $GLOBALS['post_cache'][ (int) $id ] );
	return true;
}

/** Enough of $wpdb for delete_all()'s prefix sweep of the logo options. */
class WPCPM_Test_DB {
	public $options = 'wp_options';

	public function prepare( $query, ...$args ) {
		return array(
			'args' => $args,
		);
	}

	public function esc_like( $text ) {
		// A plain passthrough: query() below matches by literal prefix, not a real SQL LIKE
		// evaluation, so escaping the wildcard characters here would only break that match.
		return (string) $text;
	}

	public function query( $prepared ) {
		$prefix = is_array( $prepared ) && isset( $prepared['args'][0] ) ? rtrim( (string) $prepared['args'][0], '%' ) : '';

		foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
			if ( '' !== $prefix && 0 === strpos( $name, $prefix ) ) {
				unset( $GLOBALS['opts'][ $name ] );
			}
		}

		return true;
	}
}

$wpdb = new WPCPM_Test_DB();

require_once __DIR__ . '/stubs/caps.php';
// For the one function it shares with this suite, the fold of a `name` query: every other one it
// holds is defined above, so it adds nothing else.
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-manager-booking.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-migration.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/** Empty every store-shaped global between groups of checks that count rows. */
function reset_store() {
	$GLOBALS['posts']        = array();
	$GLOBALS['pmeta']        = array();
	$GLOBALS['post_updates'] = array();
	$GLOBALS['meta_cache']   = array();
	$GLOBALS['post_cache']   = array();
	WPCPM_Sponsors_Index::forget();
}

echo "=== The post type ===\n";

ck( 'the post type name is under the limit', strlen( WPCPM_Sponsors_Index::POST_TYPE ) <= 20, true );

WPCPM_Sponsors_Index::register_post_type();
$args = $GLOBALS['post_types'][ WPCPM_Sponsors_Index::POST_TYPE ];

ck( 'and private: no public URL, no admin list, no REST route, a capability type nobody is granted',
	array( $args['public'], $args['show_ui'], $args['show_in_rest'], $args['capability_type'] ),
	array( false, false, false, array( 'wpcpm_sponsor_co', 'wpcpm_sponsor_cos' ) )
);

echo "\n=== is_key() ===\n";

ck( 'a record id and a minted key both pass, a third shape fails, and so does a trailing newline',
	array(
		WPCPM_Sponsors_Index::is_key( 'recSEED0000000001' ),
		WPCPM_Sponsors_Index::is_key( 'spoAAAA0000000001' ),
		WPCPM_Sponsors_Index::is_key( 'usrSEED0000000001' ),
		WPCPM_Sponsors_Index::is_key( 'rec12' ),
		WPCPM_Sponsors_Index::is_key( array() ),
		WPCPM_Sponsors_Index::is_key( "recSEED0000000001\n" ),
	),
	array( true, true, false, false, false, false )
);

ck( 'a track\'s partner is a sponsor of this shape: the Track Builder keeps its own copy of the pattern rather than loading this store, so the two must be kept as twins by hand',
	WPCPM_Track_Definition::SPONSOR_KEY,
	WPCPM_Sponsors_Index::KEY_PATTERN
);

echo "\n=== mint_key() ===\n";

$first = WPCPM_Sponsors_Index::mint_key();

$fake_id                       = 999;
$GLOBALS['posts'][ $fake_id ]  = new WP_Post(
	array(
		'ID'          => $fake_id,
		'post_type'   => WPCPM_Sponsors_Index::POST_TYPE,
		'post_status' => 'private',
		'post_title'  => 'Collider',
		'post_name'   => 'collider',
		'post_author' => 0,
	)
);
// The next wp_generate_password() call (deterministic, from the counter above) mints
// 'spo22222222222222'; pre-seeding a post under that exact key forces mint_key() to retry once,
// landing on the third.
$GLOBALS['pmeta'][ $fake_id ][ WPCPM_Sponsors_Index::META_KEY ] = 'spo22222222222222';

$second = WPCPM_Sponsors_Index::mint_key();

unset( $GLOBALS['posts'][ $fake_id ], $GLOBALS['pmeta'][ $fake_id ] );
WPCPM_Sponsors_Index::forget();

ck( "mint_key() is spo plus fourteen characters from the store's alphabet, and never a key the store holds",
	array( $first, $second ),
	array( 'spo11111111111111', 'spo33333333333333' )
);

echo "\n=== insert(), insert() over a known key, rows(), and the status readers ===\n";
reset_store();

ck( 'insert() refuses a row whose record_id is not a key',
	WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => 'not-a-key', 'name' => 'Nope' ) ) ),
	false
);

$KEY_WEGLOT    = 'recSEED0000000401';
$KEY_DREAMHOST = 'recSEED0000000402';

$ok1 = WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_WEGLOT, 'name' => '  Weglot  ' ) ) );
$ok2 = WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_DREAMHOST, 'name' => 'Dream Host' ) ) );

$sponsor_posts = array_values(
	array_filter(
		$GLOBALS['posts'],
		static function ( $post ) {
			return WPCPM_Sponsors_Index::POST_TYPE === $post->post_type;
		}
	)
);

ck( 'insert() refuses a malformed key and creates one private post per new key, titled with the trimmed name and slugged from it',
	array(
		$ok1,
		$ok2,
		count( $sponsor_posts ),
		array( $sponsor_posts[0]->post_status, $sponsor_posts[1]->post_status ),
		array( $sponsor_posts[0]->post_title, $sponsor_posts[1]->post_title ),
		array( $sponsor_posts[0]->post_name, $sponsor_posts[1]->post_name ),
	),
	array(
		true,
		true,
		2,
		array( 'private', 'private' ),
		array( 'Weglot', 'Dream Host' ),
		array( 'weglot', 'dream-host' ),
	)
);

$weglot_id_before = WPCPM_Sponsors_Index::post_id_of( $KEY_WEGLOT );
$ok3               = WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_WEGLOT, 'name' => 'Weglot SAS' ) ) );

ck( 'insert() over a known key replaces the row, keeps the post and keeps the slug',
	array(
		$ok3,
		WPCPM_Sponsors_Index::post_id_of( $KEY_WEGLOT ),
		$GLOBALS['posts'][ $weglot_id_before ]->post_name,
		WPCPM_Sponsors_Index::row( $KEY_WEGLOT )['name'],
	),
	array( true, $weglot_id_before, 'weglot', 'Weglot SAS' )
);

$rows                = WPCPM_Sponsors_Index::rows();
$expected_weglot     = array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_WEGLOT, 'slug' => 'weglot', 'name' => 'Weglot SAS' ) );
$expected_dreamhost  = array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_DREAMHOST, 'slug' => 'dream-host', 'name' => 'Dream Host' ) );

ck( 'rows() is keyed by key in post ID order, every key present and typed',
	array(
		array_keys( $rows ),
		$rows[ $KEY_WEGLOT ],
		$rows[ $KEY_DREAMHOST ],
		is_bool( $rows[ $KEY_WEGLOT ]['consent'] ),
		is_array( $rows[ $KEY_WEGLOT ]['mentors'] ),
	),
	array(
		array( $KEY_WEGLOT, $KEY_DREAMHOST ),
		$expected_weglot,
		$expected_dreamhost,
		true,
		true,
	)
);

$ok4 = WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_WEGLOT, 'name' => 'Weglot SAS', 'status' => 'Approved' ) ) );
$ok5 = WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_DREAMHOST, 'name' => 'Dream Host', 'status' => 'In review' ) ) );

ck( 'row(), has(), approved() and status_counts() read the store',
	array(
		$ok4,
		$ok5,
		WPCPM_Sponsors_Index::row( $KEY_WEGLOT )['status'],
		WPCPM_Sponsors_Index::has( $KEY_WEGLOT ),
		WPCPM_Sponsors_Index::has( 'recSEED0000000499' ),
		array_keys( WPCPM_Sponsors_Index::approved() ),
		WPCPM_Sponsors_Index::status_counts(),
	),
	array(
		true,
		true,
		'Approved',
		true,
		false,
		array( $KEY_WEGLOT ),
		array(
			'Approved'  => 1,
			'In review' => 1,
		),
	)
);

// insert() over a trashed twin: post_id_of() now finds a post
// in any status, so a key whose post was trashed outside the plugin - nothing in the plugin
// itself trashes one - must come back private, not silently update a row nothing but
// mint_key() and delete_all() can see.
$KEY_TRASHED_TWIN = 'recSEED0000000701';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_TRASHED_TWIN, 'name' => 'Trashed Twin Co' ) ) );
$twin_id                                   = WPCPM_Sponsors_Index::post_id_of( $KEY_TRASHED_TWIN );
$GLOBALS['posts'][ $twin_id ]->post_status = 'trash';
WPCPM_Sponsors_Index::forget();

$ok_twin = WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_TRASHED_TWIN, 'name' => 'Trashed Twin Co Ltd' ) ) );

ck( 'insert() over a trashed twin brings it back private with the row',
	array(
		$ok_twin,
		$GLOBALS['posts'][ $twin_id ]->post_status,
		WPCPM_Sponsors_Index::row( $KEY_TRASHED_TWIN )['name'],
	),
	array( true, 'private', 'Trashed Twin Co Ltd' )
);

echo "\n=== distinct slugs ===\n";
reset_store();

$KEY_ACME1 = 'recSEED0000000601';
$KEY_ACME2 = 'recSEED0000000602';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_ACME1, 'name' => 'Acme' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_ACME2, 'name' => 'Acme' ) ) );

ck( 'two sponsors with the same name get distinct slugs',
	array( WPCPM_Sponsors_Index::row( $KEY_ACME1 )['slug'], WPCPM_Sponsors_Index::row( $KEY_ACME2 )['slug'] ),
	array( 'acme', 'acme-2' )
);

echo "\n=== row_by_slug() ===\n";
reset_store();

$KEY_NORTHWIND = 'recSEED0000000611';
$KEY_SOUTHWIND = 'recSEED0000000612';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_NORTHWIND, 'name' => 'Northwind Tools' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_SOUTHWIND, 'name' => 'Southwind Labs' ) ) );

ck( 'row_by_slug() answers the row a known slug names, the slug trimmed as row() trims a key',
	array( WPCPM_Sponsors_Index::row_by_slug( 'northwind-tools' )['name'], WPCPM_Sponsors_Index::row_by_slug( ' southwind-labs ' )['name'] ),
	array( 'Northwind Tools', 'Southwind Labs' )
);

ck( 'and null for a slug no sponsor holds, or for none at all',
	array( WPCPM_Sponsors_Index::row_by_slug( 'eastwind' ), WPCPM_Sponsors_Index::row_by_slug( '' ), WPCPM_Sponsors_Index::row_by_slug( '   ' ) ),
	array( null, null, null )
);

// Core stores a name in another script as its UTF-8 bytes, percent-encoded in lowercase; this
// suite's sanitize_title() drops them, so the post is given the name core would have given it and
// patch() copies it into the row, as the store copies a post's name. The hiragana a and i, written
// as escapes so this file stays in ASCII.
$KEY_KANA = 'recSEED0000000613';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_KANA, 'name' => "\u{3042}\u{3044}" ) ) );
$GLOBALS['posts'][ WPCPM_Sponsors_Index::post_id_of( $KEY_KANA ) ]->post_name = '%e3%81%82%e3%81%84';
WPCPM_Sponsors_Index::patch( $KEY_KANA, array() );

ck( 'a slug stored percent-encoded is found as stored, in the capitals a browser sends and decoded, and decoded once only: encoded twice, it names nothing',
	array(
		WPCPM_Sponsors_Index::row_by_slug( '%e3%81%82%e3%81%84' )['record_id'],
		WPCPM_Sponsors_Index::row_by_slug( '%E3%81%82%E3%81%84' )['record_id'],
		WPCPM_Sponsors_Index::row_by_slug( "\u{3042}\u{3044}" )['record_id'],
		WPCPM_Sponsors_Index::row_by_slug( '%25e3%2581%2582%25e3%2581%2584' ),
	),
	array( $KEY_KANA, $KEY_KANA, $KEY_KANA, null )
);

// Core's query folds the name it is asked for (a dot is a dash to it), so "northwind.tools" finds
// Northwind's post; that post is not what the slug names, and the badge route must not answer a
// name its sponsor never had.
WPCPM_Sponsors_Index::forget();
$GLOBALS['get_posts_calls'] = array();
ck( 'row_by_slug() reads the one post of the name, never every sponsor, and a name core\'s query folds into a stored one names nothing',
	array(
		WPCPM_Sponsors_Index::row_by_slug( 'northwind-tools' )['record_id'],
		array_map(
			static function ( $call ) {
				return array( $call['post_type'], $call['post_status'], isset( $call['name'] ) ? $call['name'] : null, isset( $call['numberposts'] ) ? $call['numberposts'] : null );
			},
			$GLOBALS['get_posts_calls']
		),
		count( get_posts( array( 'post_type' => WPCPM_Sponsors_Index::POST_TYPE, 'post_status' => 'private', 'name' => 'northwind.tools' ) ) ),
		WPCPM_Sponsors_Index::row_by_slug( 'northwind.tools' ),
		WPCPM_Sponsors_Index::row_by_slug( 'Northwind-Tools!' ),
	),
	array( $KEY_NORTHWIND, array( array( 'wpcpm_sponsor_co', 'private', 'northwind-tools', 1 ) ), 1, null, null )
);

echo "\n=== patch() ===\n";
reset_store();

$KEY_PATCH = 'recSEED0000000901';

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id'     => $KEY_PATCH,
			'name'          => 'Patchable Co',
			'contact_email' => 'old@example.test',
			'website'       => 'https://patchable.example/',
		)
	)
);

$patched      = WPCPM_Sponsors_Index::patch( $KEY_PATCH, array( 'contact_email' => 'new@example.test' ) );
$after_patch  = WPCPM_Sponsors_Index::row( $KEY_PATCH );

ck( 'patch() changes only the keys it is given',
	array( $patched, $after_patch['contact_email'], $after_patch['website'], $after_patch['name'] ),
	array( true, 'new@example.test', 'https://patchable.example/', 'Patchable Co' )
);

ck( 'and refuses an unknown key', WPCPM_Sponsors_Index::patch( 'spoZZZZ0000000009', array( 'name' => 'Ghost' ) ), false );

$patch_post_id = WPCPM_Sponsors_Index::post_id_of( $KEY_PATCH );
WPCPM_Sponsors_Index::patch( $KEY_PATCH, array( 'name' => 'Patchable Co Ltd' ) );

ck( 'and renames the title with the name',
	array( WPCPM_Sponsors_Index::row( $KEY_PATCH )['name'], $GLOBALS['posts'][ $patch_post_id ]->post_title ),
	array( 'Patchable Co Ltd', 'Patchable Co Ltd' )
);

// patch() used to call wp_update_post() on every save, whether or not the
// name changed, an unconditional write for a field most saves never touch.
$GLOBALS['post_updates'] = array();
WPCPM_Sponsors_Index::patch( $KEY_PATCH, array( 'name' => 'Patchable Co Ltd', 'contact_email' => 'administrator+another@example.test' ) );

ck( "patch() leaves the post's title alone when the name did not change",
	array( count( $GLOBALS['post_updates'] ), $GLOBALS['posts'][ $patch_post_id ]->post_title ),
	array( 0, 'Patchable Co Ltd' )
);

// patch() refuses a key whose post is in the trash. rows()
// below primes the request cache while the post is still private, as an earlier read in the
// same request would; the trash then lands the way a WP-CLI command outside the plugin does,
// without calling forget(). post_id_of() (never cached) finds the post in any status, so the
// refusing is the status guard's, which reads the post past the cache: a patch() that trusted
// the stale copy would write a row has() (forget()'d fresh, below) already says does not exist.
WPCPM_Sponsors_Index::rows();
$GLOBALS['posts'][ $patch_post_id ]->post_status = 'trash';

$patch_on_trash = WPCPM_Sponsors_Index::patch( $KEY_PATCH, array( 'name' => 'Should Not Land' ) );

WPCPM_Sponsors_Index::forget();

ck( 'patch() refuses a key whose post is in the trash, and has() agrees',
	array( $patch_on_trash, WPCPM_Sponsors_Index::has( $KEY_PATCH ) ),
	array( false, false )
);

echo "\n=== patch() and another request's save ===\n";
reset_store();

/**
 * A step run as another request: with a request cache and a copy of the meta of its own, both
 * empty, so it reads the table; then this request's come back as they were, the row they hold now
 * stale, which is all another process's write leaves behind here.
 *
 * @param Closure $step What the other request does.
 */
function as_another_request( Closure $step ) {
	$cache = new ReflectionProperty( 'WPCPM_Sponsors_Index', 'rows_cache' );

	if ( PHP_VERSION_ID < 80100 ) {
		$cache->setAccessible( true );
	}

	$rows  = $cache->getValue();
	$meta  = $GLOBALS['meta_cache'];
	$posts = $GLOBALS['post_cache'];

	WPCPM_Sponsors_Index::forget();
	$GLOBALS['meta_cache'] = array();
	$GLOBALS['post_cache'] = array();

	$step();

	$cache->setValue( null, $rows );
	$GLOBALS['meta_cache'] = $meta;
	$GLOBALS['post_cache'] = $posts;
}

$KEY_RACE = 'recSEED0000000951';

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id'      => $KEY_RACE,
			'name'           => 'Racing Co',
			'status'         => 'Approved',
			'tier'           => 'partner',
			'tier_until'     => '2027-06-30',
			'contact_person' => 'Ada Contact',
		)
	)
);

$race_id                 = WPCPM_Sponsors_Index::post_id_of( $KEY_RACE );
$GLOBALS['object_cache'] = true;

// A member's profile save: the claim reads the row first, and this request keeps it, in its own
// cache and in the object cache. A manager's save lands before the member's patch, from another
// request: Paused, lowered to Champion, the term cut to 31 October.
WPCPM_Sponsors_Index::row( $KEY_RACE );
as_another_request(
	static function () use ( $KEY_RACE ) {
		WPCPM_Sponsors_Index::patch( $KEY_RACE, array( 'status' => 'Paused', 'tier' => 'champion', 'tier_until' => '2026-10-31' ) );
	}
);

$member_saved = WPCPM_Sponsors_Index::patch( $KEY_RACE, array( 'contact_person' => 'Bob Contact' ) );
$race_stored  = $GLOBALS['pmeta'][ $race_id ]['_wpcpm_sponsor_row'];

ck( 'a member\'s profile save that read the row before a manager\'s save landed writes its own field and keeps the manager\'s: Paused, Champion, the term to 31 October',
	array( $member_saved, $race_stored['status'], $race_stored['tier'], $race_stored['tier_until'], $race_stored['contact_person'], $race_stored['name'] ),
	array( true, 'Paused', 'champion', '2026-10-31', 'Bob Contact', 'Racing Co' )
);

// The daily run's stamp, from a request that dropped its own row cache but still holds the object
// cache's copy of the row: forget() alone would read that copy, so the patch has to get past it.
WPCPM_Sponsors_Index::row( $KEY_RACE );
as_another_request(
	static function () use ( $KEY_RACE ) {
		WPCPM_Sponsors_Index::patch(
			$KEY_RACE,
			array(
				'tier_until'  => '2027-10-31',
				'report_owed' => array(
					'tier'        => 'champion',
					'until'       => '2026-10-31',
					'funded_line' => '',
				),
			)
		);
	}
);
WPCPM_Sponsors_Index::forget();

$GLOBALS['cache_deleted'] = array();
$run_saved                = WPCPM_Sponsors_Index::patch( $KEY_RACE, array( 'report_drafted_for' => '2026-10-31' ) );
$race_stored              = $GLOBALS['pmeta'][ $race_id ]['_wpcpm_sponsor_row'];

ck( 'and a patch reads the row past the object cache too: a renewal saved in between is kept beside the stamp, the member\'s field with it',
	array( $run_saved, $race_stored['tier_until'], $race_stored['report_owed'], $race_stored['report_drafted_for'], $race_stored['contact_person'], $race_stored['status'] ),
	array( true, '2027-10-31', array( 'tier' => 'champion', 'until' => '2026-10-31', 'funded_line' => '' ), '2026-10-31', 'Bob Contact', 'Paused' )
);
ck( 'by dropping this request\'s copy of the post before it reads the row', in_array( 'post_meta:' . $race_id, $GLOBALS['cache_deleted'], true ), true );

// A post another request trashed outside the plugin, after this request read it: the patch reads
// the post's status past this request's copy too, and refuses a row has() will not see.
get_post( $race_id );
as_another_request(
	static function () use ( $race_id ) {
		$GLOBALS['posts'][ $race_id ]->post_status = 'trash';
	}
);
$trashed_saved = WPCPM_Sponsors_Index::patch( $KEY_RACE, array( 'contact_person' => 'Should Not Land' ) );

ck( 'and the post\'s status past it too: a sponsor another request trashed since this one read it takes no patch',
	array( get_post( $race_id )->post_status, $trashed_saved, $GLOBALS['pmeta'][ $race_id ]['_wpcpm_sponsor_row']['contact_person'] ),
	array( 'trash', false, 'Bob Contact' )
);

$GLOBALS['object_cache'] = false;
$GLOBALS['meta_cache']   = array();
$GLOBALS['post_cache']   = array();

echo "\n=== the request cache ===\n";
reset_store();

$KEY_CACHE1 = 'recSEED0000001001';
$KEY_CACHE2 = 'recSEED0000001002';
$KEY_CACHE3 = 'recSEED0000001003';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_CACHE1, 'name' => 'Cache One' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_CACHE2, 'name' => 'Cache Two' ) ) );

$before_count = count( WPCPM_Sponsors_Index::rows() );

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_CACHE3, 'name' => 'Cache Three' ) ) );

$after_count = count( WPCPM_Sponsors_Index::rows() );

ck( 'the request cache is dropped by every writer', array( $before_count, $after_count ), array( 2, 3 ) );

echo "\n=== manager_of() ===\n";
reset_store();

$GLOBALS['users'][7] = new WP_User( 7, 'Program Administrator', 'administrator@example.test' );
WPCPM_Manager_Booking::set( 7, 'https://calendly.example/seven' );
// User 7 is a program manager, as every manager the Edit form offers is: the contact is answered
// only while the account still holds the program's capability.
$GLOBALS['manage'] = array( 7 );

$KEY_MANAGED   = 'recSEED0000001101';
$KEY_NOMANAGER = 'recSEED0000001102';
$KEY_UNKNOWN   = 'recSEED0000001103';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_MANAGED, 'name' => 'Has A Manager', 'manager' => '7' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_NOMANAGER, 'name' => 'No Manager', 'manager' => '' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_UNKNOWN, 'name' => 'Unknown Manager', 'manager' => '99' ) ) );

ck( 'manager_of() resolves a user id to the name, the address and the booking link, and answers null for nobody',
	array(
		WPCPM_Sponsors_Index::manager_of( $KEY_MANAGED ),
		WPCPM_Sponsors_Index::manager_of( $KEY_NOMANAGER ),
		WPCPM_Sponsors_Index::manager_of( $KEY_UNKNOWN ),
	),
	array(
		array(
			'name'     => 'Program Administrator',
			'email'    => 'administrator@example.test',
			'calendly' => 'https://calendly.example/seven',
		),
		null,
		null,
	)
);

// A manager who left the team and kept the account, as a mentor say: the row still names them,
// and the Edit form reads that id itself to mark them "(not an Administrator now)", but nothing
// that asks for the contact is answered with them - neither the mails a contact gets (a student's
// name and address in a claim problem, an event's call link) nor the contact block a member reads.
$GLOBALS['users'][12] = new WP_User( 12, 'Former Manager', 'administrator@example.test' );
WPCPM_Manager_Booking::set( 12, 'https://calendly.example/twelve' );

$KEY_DEMOTED = 'recSEED0000001104';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_DEMOTED, 'name' => 'Former Manager Kept', 'manager' => '12' ) ) );

$demoted_while_manager = ( function () use ( $KEY_DEMOTED ) {
	$GLOBALS['manage'] = array( 7, 12 );
	$answer            = WPCPM_Sponsors_Index::manager_of( $KEY_DEMOTED );
	$GLOBALS['manage'] = array( 7 );

	return $answer;
} )();

ck( 'manager_of() answers a contact only while the account holds the program capability: once it is lost, nobody, and the row keeps the id',
	array(
		$demoted_while_manager,
		WPCPM_Sponsors_Index::manager_of( $KEY_DEMOTED ),
		WPCPM_Sponsors_Index::row( $KEY_DEMOTED )['manager'],
	),
	array(
		array(
			'name'     => 'Former Manager',
			'email'    => 'administrator@example.test',
			'calendly' => 'https://calendly.example/twelve',
		),
		null,
		'12',
	)
);

// The program's own screens name that id, marked, so a manager scanning a list sees the
// assignment waiting to be replaced, where the sponsor's pages name nobody.
ck( 'manager_label() names a program manager, marks one who is not one now and an account that is gone, and names nobody for no manager',
	method_exists( 'WPCPM_Sponsors_Index', 'manager_label' )
		? array(
			WPCPM_Sponsors_Index::manager_label( WPCPM_Sponsors_Index::row( $KEY_MANAGED ) ),
			WPCPM_Sponsors_Index::manager_label( WPCPM_Sponsors_Index::row( $KEY_DEMOTED ) ),
			WPCPM_Sponsors_Index::manager_label( WPCPM_Sponsors_Index::row( $KEY_UNKNOWN ) ),
			WPCPM_Sponsors_Index::manager_label( WPCPM_Sponsors_Index::row( $KEY_NOMANAGER ) ),
		)
		: 'no manager_label()',
	array( 'Program Administrator', 'Former Manager (not an Administrator now)', '99 (not an Administrator now)', '' )
);

// The sections below were written with nobody holding the capability; the migration's own reset
// names its managers when it needs them.
$GLOBALS['manage'] = array();

// The team the old sync kept went with it (1.111.0): only the migration reads the held option, by
// itself, and nothing on the site keeps a team to resolve a manager through.
ck( 'and the store keeps no team of its own any more: team() and write_team() went with the sponsors sync',
	array( method_exists( 'WPCPM_Sponsors_Index', 'team' ), method_exists( 'WPCPM_Sponsors_Index', 'write_team' ) ),
	array( false, false )
);

echo "\n=== write() ===\n";
reset_store();

$KEY_WRITE1 = 'recSEED0000001201';
$KEY_WRITE2 = 'recSEED0000001202';

$written = WPCPM_Sponsors_Index::write(
	array(
		$KEY_WRITE1 => array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'name' => 'Write One' ) ),
		$KEY_WRITE2 => array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'name' => 'Write Two' ) ),
		'nope'      => array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'name' => 'Malformed' ) ),
	),
	time()
);

ck( 'write() inserts every well-formed row and reports the count',
	array( $written, count( WPCPM_Sponsors_Index::rows() ) ),
	array( 2, 2 )
);

echo "\n=== delete_all() ===\n";
reset_store();
$GLOBALS['opts'] = array();

$KEY_DELETE1 = 'recSEED0000001301';
$KEY_DELETE2 = 'recSEED0000001302';

WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_DELETE1, 'name' => 'Delete One' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_DELETE2, 'name' => 'Delete Two' ) ) );

$delete1_id = WPCPM_Sponsors_Index::post_id_of( $KEY_DELETE1 );
$delete2_id = WPCPM_Sponsors_Index::post_id_of( $KEY_DELETE2 );

WPCPM_Sponsors_Index::write_logo_record( $KEY_DELETE1, array( 'colour' => 55, 'source' => 'site' ) );

// And a logo option whose sponsor the store no longer holds: a sponsor removed between a logo
// write and an uninstall leaves one. The sweep goes by prefix for exactly this, where a delete
// that walked the rows would never find it (the deleted sponsors sync's suite pinned it until
// 1.111.0; restored here).
$KEY_ORPHANED = 'recSEED0000001303';
WPCPM_Sponsors_Index::write_logo_record( $KEY_ORPHANED, array( 'colour' => 56, 'source' => 'site' ) );
$orphan_before = array( WPCPM_Sponsors_Index::has( $KEY_ORPHANED ), is_array( get_option( WPCPM_Sponsors_Index::logo_option( $KEY_ORPHANED ) ) ) );

$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ] = array(
	'v'    => 1,
	'rows' => array( 'legacy' => true ),
);
$GLOBALS['opts'][ WPCPM_Sponsors_Migration::OPT_REPORT ] = array( 'complete' => true );

$control_id                      = 700;
$GLOBALS['posts'][ $control_id ] = new WP_Post(
	array(
		'ID'          => $control_id,
		'post_type'   => 'post',
		'post_status' => 'publish',
		'post_title'  => 'Not a sponsor',
		'post_name'   => 'not-a-sponsor',
		'post_author' => 1,
	)
);

// A trashed sponsor post: core's own 'any' means everything but trash and auto-draft, so a
// sponsor moved to the trash outlived both delete_all() and post_id_of() until the review of
// 1.111.0; both now name every status, trash and auto-draft included, instead of relying on 'any'.
$KEY_TRASHED = 'recSEED0000001304';
$trashed_id  = 701;

$GLOBALS['posts'][ $trashed_id ] = new WP_Post(
	array(
		'ID'          => $trashed_id,
		'post_type'   => WPCPM_Sponsors_Index::POST_TYPE,
		'post_status' => 'trash',
		'post_title'  => 'Trashed Sponsor',
		'post_name'   => 'trashed-sponsor',
		'post_author' => 0,
	)
);
$GLOBALS['pmeta'][ $trashed_id ][ WPCPM_Sponsors_Index::META_KEY ] = $KEY_TRASHED;

$trashed_found_before = WPCPM_Sponsors_Index::post_id_of( $KEY_TRASHED );

WPCPM_Sponsors_Index::delete_all();

ck( 'delete_all() removes every post of the type, the logo options and the old option, and leaves other posts alone',
	array(
		WPCPM_Sponsors_Index::rows(),
		isset( $GLOBALS['posts'][ $delete1_id ] ),
		isset( $GLOBALS['posts'][ $delete2_id ] ),
		isset( $GLOBALS['posts'][ $control_id ] ),
		$GLOBALS['opts'],
	),
	array( array(), false, false, true, array() )
);
ck( 'delete_all() removes a logo option whose sponsor the store no longer holds',
	array( $orphan_before, get_option( WPCPM_Sponsors_Index::logo_option( $KEY_ORPHANED ), 'gone' ) ),
	array( array( false, true ), 'gone' )
);

ck( 'post_id_of() finds a trashed sponsor post too', $trashed_found_before, $trashed_id );

ck( 'delete_all() removes a trashed sponsor post too', isset( $GLOBALS['posts'][ $trashed_id ] ), false );

echo "\n=== The slash round trip ===\n";
// insert() writes update_post_meta( $id, self::META_ROW, wp_slash( $row ) ); this suite's own
// wp_slash()/wp_unslash() are the real WordPress pair (unlike bin/stubs/posts.php's, which the
// twelve reconciled suites need as identity functions instead - see that file's docblock), and
// update_post_meta() below unslashes what it is given before storing, so a name with an
// apostrophe and a backslash must come back exactly as it went in, not doubly escaped and not
// stripped.
$KEY_SLASH = 'recSEED0000001401';
WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id' => $KEY_SLASH,
			'name'      => "O'Reilly \\ Co",
			'notes'     => "A quote ' and a backslash \\ together",
		)
	)
);
ck( 'a name with an apostrophe and a backslash round-trips exactly',
	array( WPCPM_Sponsors_Index::row( $KEY_SLASH )['name'], WPCPM_Sponsors_Index::row( $KEY_SLASH )['notes'] ),
	array( "O'Reilly \\ Co", "A quote ' and a backslash \\ together" )
);

// The post's title is written by wp_insert_post() and wp_update_post(), which unslash it as core's
// do (the stand-ins above), and a rename through patch() writes the row and the title again: each
// keeps a backslash only when it is handed over as a slashed copy.
$slashed_typed  = 'Kept in C:\drafts\sponsors, two in a row \\\\ here, and a "quoted" word.';
$slashed_rename = 'Renamed in C:\drafts\later, two more \\\\ of them, and "again".';
$slashed_title  = static function () use ( $KEY_SLASH ) {
	return $GLOBALS['posts'][ WPCPM_Sponsors_Index::post_id_of( $KEY_SLASH ) ]->post_title;
};
$slashed_first  = $slashed_title();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_SLASH, 'name' => $slashed_typed ) ) );
$slashed_again = array( $slashed_title(), WPCPM_Sponsors_Index::row( $KEY_SLASH )['name'] );
WPCPM_Sponsors_Index::patch( $KEY_SLASH, array( 'name' => $slashed_rename, 'notes' => $slashed_rename ) );
ck( 'and its post is titled with the name exactly, when it is made, when it is written again over its key, and when patch() renames it, the row with it',
	array( $slashed_first, $slashed_again, array( $slashed_title(), WPCPM_Sponsors_Index::row( $KEY_SLASH )['name'], WPCPM_Sponsors_Index::row( $KEY_SLASH )['notes'] ) ),
	array( "O'Reilly \\ Co", array( $slashed_typed, $slashed_typed ), array( $slashed_rename, $slashed_rename, $slashed_rename ) )
);

echo "\n=== the row shape ===\n";

ck( 'the row has no offer mirror keys and no logo facts, and carries the six tier keys, the report stamp and the owed term, the seventh and the eighth, and the event allowance, the ninth',
	array_keys( WPCPM_Sponsors_Index::empty_row() ),
	array(
		'record_id',
		'slug',
		'name',
		'website',
		'status',
		'option',
		'support',
		'product_type',
		'anything',
		'contact_person',
		'contact_email',
		'manager',
		'mentors',
		'consent',
		'created',
		'agreement',
		'interests',
		'dashboard_account',
		'notes',
		'tier',
		'tier_until',
		'funded_line',
		'description',
		'tier_requested',
		'tier_mailed_for',
		'report_drafted_for',
		'report_owed',
		'event_allowance',
		'test',
	)
);
ck( 'a row says whether it is a test sponsor, never listed publicly (1.123.18): false unless set, and read as a flag whatever was stored', array( WPCPM_Sponsors_Index::empty_row()['test'], WPCPM_Sponsors_Index::is_test( array( 'test' => '1' ) ), WPCPM_Sponsors_Index::is_test( array( 'test' => '' ) ), WPCPM_Sponsors_Index::is_test( array() ) ), array( false, true, false, false ) );

// A row as 1.111.0 stored it: nineteen keys, none of the six tier keys 1.112.0 added and not the
// report stamp 1.115.0 added. Every sponsor on the live site was written that way, and until
// something patches it the stored row still lacks them, so rows(), which reads every row, is where
// a row gets its shape: the Edit form and the Sponsorship card read
// them without isset(), and the daily run reads the stamp the same way.
reset_store();

$KEY_OLDER = 'recSEED0000001501';
$older_id  = 702;

$GLOBALS['posts'][ $older_id ] = new WP_Post(
	array(
		'ID'          => $older_id,
		'post_type'   => WPCPM_Sponsors_Index::POST_TYPE,
		'post_status' => 'private',
		'post_title'  => 'Older Release Co',
		'post_name'   => 'older-release-co',
		'post_author' => 0,
	)
);
$GLOBALS['pmeta'][ $older_id ][ WPCPM_Sponsors_Index::META_KEY ] = $KEY_OLDER;
$GLOBALS['pmeta'][ $older_id ][ WPCPM_Sponsors_Index::META_ROW ] = array(
	'record_id'         => $KEY_OLDER,
	'slug'              => 'older-release-co',
	'name'              => 'Older Release Co',
	'website'           => 'https://older.example/',
	'status'            => 'Approved',
	'option'            => '',
	'support'           => array(),
	'product_type'      => 'Hosting',
	'anything'          => '',
	'contact_person'    => '',
	'contact_email'     => '',
	'manager'           => '',
	'mentors'           => array(),
	'consent'           => true,
	'created'           => '2026-09-01',
	'agreement'         => array(
		'status'       => '',
		'accepted_on'  => '',
		'has_document' => false,
	),
	'interests'         => '',
	'dashboard_account' => false,
	'notes'             => '',
);
WPCPM_Sponsors_Index::forget();

$older_row      = WPCPM_Sponsors_Index::row( $KEY_OLDER );
$older_approved = WPCPM_Sponsors_Index::approved();

ck( 'rows() shapes a row an older release stored: the six tier keys and the report stamp read as empty strings, the owed term and the event allowance as none, and row() and approved() agree',
	array(
		array_keys( (array) $older_row ),
		array_intersect_key( (array) $older_row, array_flip( array( 'tier', 'tier_until', 'funded_line', 'description', 'tier_requested', 'tier_mailed_for', 'report_drafted_for', 'report_owed', 'event_allowance' ) ) ),
		isset( $older_approved[ $KEY_OLDER ] ) ? $older_approved[ $KEY_OLDER ] === $older_row : 'not approved',
	),
	array(
		array_keys( WPCPM_Sponsors_Index::empty_row() ),
		array(
			'tier'               => '',
			'tier_until'         => '',
			'funded_line'        => '',
			'description'        => '',
			'tier_requested'     => '',
			'tier_mailed_for'    => '',
			'report_drafted_for' => '',
			'report_owed'        => array(),
			'event_allowance'    => array(),
		),
		true,
	)
);

// The owed term a renewal on the Sponsors screen leaves: a tier and a day with the line the term's
// money funded, kept as three trimmed strings, the line empty when the term had none; anything else
// a row holds under the key, half a term among it, reads as none.
$KEY_OWED = 'recSEED0000001502';
$owed_shapes = array();
foreach ( array( array( 'tier' => ' backer ', 'until' => '2026-09-30', 'funded_line' => ' Mentor stipends ', 'extra' => 'dropped' ), array( 'tier' => 'backer', 'until' => '2026-09-30' ), array( 'tier' => 'backer' ), 'backer 2026-09-30', array( 'tier' => '', 'until' => '2026-09-30', 'funded_line' => 'A line' ) ) as $given ) {
	WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_OWED, 'name' => 'Owed Term Co', 'report_owed' => $given ) ) );
	$owed_shapes[] = WPCPM_Sponsors_Index::row( $KEY_OWED )['report_owed'];
}
ck( 'the owed term is a tier, a day and the term\'s funded line, or nothing',
	$owed_shapes,
	array( array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => 'Mentor stipends' ), array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => '' ), array(), array(), array() )
);

// The extra co-hosted events the Tier box grants for one term: the last day of the term they were
// granted for, kept trimmed, and how many, kept as a whole number, a count written as its digits
// read as that number. Anything else a row holds under the key reads as none: a day with no count
// or a count with no day, a count of none, below it or no whole number, and a value that is no pair.
$KEY_ALLOWED      = 'recSEED0000001503';
$allowance_shapes = array();
foreach ( array(
	array( 'until' => ' 2027-01-31 ', 'extra' => 2, 'other' => 'dropped' ),
	array( 'until' => '2027-01-31', 'extra' => ' 3 ' ),
	array( 'until' => '2027-01-31', 'extra' => 0 ),
	array( 'until' => '2027-01-31' ),
	array( 'extra' => 2 ),
	array( 'until' => '  ', 'extra' => 2 ),
	array( 'until' => '2027-01-31', 'extra' => -1 ),
	array( 'until' => '2027-01-31', 'extra' => '2 more' ),
	array( 'until' => '2027-01-31', 'extra' => 1.5 ),
	array( 'until' => '2027-01-31', 'extra' => true ),
	array( 'until' => array( '2027-01-31' ), 'extra' => 2 ),
	'2027-01-31 2',
) as $given ) {
	WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_ALLOWED, 'name' => 'Allowed Events Co', 'event_allowance' => $given ) ) );
	$allowance_shapes[] = WPCPM_Sponsors_Index::row( $KEY_ALLOWED )['event_allowance'];
}
ck( 'the event allowance is a day and a whole count of one or more, or nothing',
	$allowance_shapes,
	array( array( 'until' => '2027-01-31', 'extra' => 2 ), array( 'until' => '2027-01-31', 'extra' => 3 ), array(), array(), array(), array(), array(), array(), array(), array(), array(), array() )
);

echo "\n=== The migration ===\n";

/** A site with nothing on it: no posts, options, user meta or transients, nothing recorded. */
function reset_site() {
	reset_store();

	$GLOBALS['opts']          = array();
	$GLOBALS['umeta']         = array();
	$GLOBALS['option_reads']  = array();
	$GLOBALS['autoload']      = array();
	$GLOBALS['transients']    = array();
	$GLOBALS['transient_ttl'] = array();
	$GLOBALS['cleared']       = array();
	$GLOBALS['offers']        = array();
	$GLOBALS['seeded']        = array();
	$GLOBALS['insert_fail']   = '';
	$GLOBALS['seed_fail']     = '';
	$GLOBALS['lock_seen']     = array();
}

$KEY_MANGO = 'recSEED0000000001';
$KEY_DREAM = 'recSEED0000000002';
$KEY_WEX   = 'recSEED0000000003';

// The option as the sponsors sync left it, `v` 1: the migration must take the rows whatever `v`
// says, so nothing here needs it to be any particular value. Each row still carries the offer mirror
// and the logo facts the new row has no place for. Mango's manager is user 7 by address and it
// has offer text and no offer; DreamHost's manager has no account on the site; Wexample names
// no manager and has an offer already. The fourth entry is not a sponsor key at all, so it is
// no row: it is skipped and not counted.
$old_index = array(
	'v'    => 1,
	'read' => 1758600000,
	'rows' => array(
		$KEY_MANGO  => array(
			'record_id'         => $KEY_MANGO,
			'name'              => 'Mango Example ',
			'website'           => 'https://mango.example/',
			'status'            => 'Approved',
			'option'            => 'Sponsor a mentor',
			'support'           => array( 'Sponsor tools or services' ),
			'product_type'      => 'Hosting',
			'offer'             => 'A year of hosting for every student.',
			'instructions'      => 'Claim the code on the checkout page.',
			'more_info'         => 'https://mango.example/students',
			'coupon_link'       => 'https://mango.example/checkout?coupon=STUDENTS',
			'anything'          => 'We run a student program already.',
			'contact_person'    => 'Rep One',
			'contact_email'     => 'administrator+mango@example.test',
			'manager'           => 'recTEAM0000000001',
			'mentors'           => array( 'recSEED0000000901' ),
			'logo'              => array(
				array(
					'id'  => 'attSEED',
					'url' => 'https://mango.example/logo.png',
				),
			),
			'consent'           => true,
			'created'           => '2026-03-02',
			'agreement'         => array(
				'status'       => 'Accepted',
				'accepted_on'  => '2026-04-01',
				'has_document' => true,
			),
			'interests'         => '2026-05-01 by Rep One: Sponsor tools or services',
			'dashboard_account' => true,
		),
		$KEY_DREAM  => array(
			'record_id' => $KEY_DREAM,
			'name'      => 'DreamHost',
			'status'    => 'In review',
			'offer'     => '',
			'manager'   => 'recTEAM0000000002',
		),
		$KEY_WEX    => array(
			'record_id' => $KEY_WEX,
			'name'      => 'Wexample',
			'status'    => 'Paused',
			'offer'     => 'Two seats for the whole cohort.',
			'manager'   => '',
		),
		'not-a-key' => array( 'name' => 'Malformed' ),
	),
);

// The team the sync held, keyed by Team Members record ID, addresses lowercased as it wrote
// them. Seven and eight are program managers with accounts, and eight already has a booking link
// of their own; the second member has no account on the site; nine has one and is no manager.
$old_team = array(
	'v'    => 1,
	'read' => 1758600000,
	'rows' => array(
		'recTEAM0000000001' => array(
			'name'     => 'Manager Seven',
			'email'    => 'administrator@example.test',
			'calendly' => 'https://calendly.example/seven',
		),
		'recTEAM0000000002' => array(
			'name'     => 'Nobody Here',
			'email'    => 'nobody@team.example',
			'calendly' => 'https://calendly.example/nobody',
		),
		'recTEAM0000000003' => array(
			'name'     => 'Manager Eight',
			'email'    => 'administrator+eight@example.test',
			'calendly' => 'https://calendly.example/eight-from-the-team',
		),
		'recTEAM0000000004' => array(
			'name'     => 'Student Nine',
			'email'    => 'administrator+nine@example.test',
			'calendly' => 'https://calendly.example/nine',
		),
	),
);

// The old sync's own state, written out by name the way a site that upgrades still holds it; the
// migration keeps the same names as constants since the class that declared them went (1.111.0),
// and a check below compares the two.
$sync_options = array( 'wpcpm_sponsors_state', 'wpcpm_sponsors_report', 'wpcpm_sponsors_last_sync', 'wpcpm_sponsors_last_error', 'wpcpm_sponsors_lock', 'wpcpm_sponsors_sync_shrink' );

/** The site the upgrade finds: the held index and team, the sync's options, three accounts. */
function seed_old_site() {
	global $old_index, $old_team, $sync_options, $KEY_WEX;

	reset_site();

	$GLOBALS['opts']['wpcpm_sponsors_index'] = $old_index;
	$GLOBALS['opts']['wpcpm_team_members']   = $old_team;

	foreach ( $sync_options as $name ) {
		$GLOBALS['opts'][ $name ] = array( 'held' => true );
	}

	$GLOBALS['users']  = array(
		7 => new WP_User( 7, 'Manager Seven', 'administrator@example.test' ),
		8 => new WP_User( 8, 'Manager Eight', 'administrator+eight@example.test' ),
		9 => new WP_User( 9, 'Student Nine', 'administrator+nine@example.test' ),
	);
	$GLOBALS['manage'] = array( 7, 8 );

	WPCPM_Manager_Booking::set( 8, 'https://calendly.example/eight-own' );

	// Wexample's offer was made on the site before this release.
	$GLOBALS['offers'][ $KEY_WEX ] = array( 4001 => array( 'id' => 4001 ) );
}

/** Each sponsor post there is, as its ID and status, in ID order. */
function sponsor_posts() {
	$out = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( WPCPM_Sponsors_Index::POST_TYPE === $post->post_type ) {
			$out[] = array( $post->ID, $post->post_status );
		}
	}

	return $out;
}

/** How many times the old option has been read since the site was reset. */
function reads_of_index() {
	return isset( $GLOBALS['option_reads']['wpcpm_sponsors_index'] ) ? $GLOBALS['option_reads']['wpcpm_sponsors_index'] : 0;
}

seed_old_site();

$report_1 = WPCPM_Sponsors_Migration::run();
$rows_1   = WPCPM_Sponsors_Index::rows();
$posts_1  = sponsor_posts();

ck( 'an old index of three rows becomes three private posts under the same keys, and the report says complete',
	array(
		array_keys( $rows_1 ),
		array_column( $posts_1, 1 ),
		$rows_1[ $KEY_MANGO ]['manager'],
		$rows_1[ $KEY_MANGO ],
		array( $report_1['complete'], $report_1['rows'], $report_1['posts'] ),
		WPCPM_Sponsors_Migration::report() === $report_1,
	),
	array(
		array( $KEY_MANGO, $KEY_DREAM, $KEY_WEX ),
		array( 'private', 'private', 'private' ),
		'7',
		array(
			'record_id'          => $KEY_MANGO,
			'slug'               => 'mango-example',
			'name'               => 'Mango Example',
			'website'            => 'https://mango.example/',
			'status'             => 'Approved',
			'option'             => 'Sponsor a mentor',
			'support'            => array( 'Sponsor tools or services' ),
			'product_type'       => 'Hosting',
			'anything'           => 'We run a student program already.',
			'contact_person'     => 'Rep One',
			'contact_email'      => 'administrator+mango@example.test',
			'manager'            => '7',
			'mentors'            => array( 'recSEED0000000901' ),
			'consent'            => true,
			'created'            => '2026-03-02',
			'agreement'          => array(
				'status'       => 'Accepted',
				'accepted_on'  => '2026-04-01',
				'has_document' => true,
			),
			'interests'          => '2026-05-01 by Rep One: Sponsor tools or services',
			'dashboard_account'  => true,
			'notes'              => '',
			'tier'               => '',
			'tier_until'         => '',
			'funded_line'        => '',
			'description'        => '',
			'tier_requested'     => '',
			'tier_mailed_for'    => '',
			'report_drafted_for' => '',
			'report_owed'        => array(),
			'event_allowance'    => array(),
			'test'               => false,
		),
		array( true, 3, 3 ),
		true,
	)
);

ck( 'a manager the site has no account for is left empty and named in the report',
	array( $rows_1[ $KEY_DREAM ]['manager'], $rows_1[ $KEY_WEX ]['manager'], $report_1['unmapped'] ),
	array( '', '', array( 'DreamHost' ) )
);

ck( 'a row with offer text and no primary offer seeds a draft offer with the four legacy values; a row that has an offer does not',
	array( $GLOBALS['seeded'], $report_1['seeded'] ),
	array(
		array(
			array(
				$KEY_MANGO,
				array(
					'offer'        => 'A year of hosting for every student.',
					'instructions' => 'Claim the code on the checkout page.',
					'more_info'    => 'https://mango.example/students',
					'coupon_link'  => 'https://mango.example/checkout?coupon=STUDENTS',
				),
			),
		),
		1,
	)
);

// "Once": the second run below finds seven's link changed by seven and leaves it.
ck( 'the booking link is seeded once from the team by address, and an existing link is kept',
	array( WPCPM_Manager_Booking::url_for( 7 ), WPCPM_Manager_Booking::url_for( 8 ), WPCPM_Manager_Booking::url_for( 9 ) ),
	array( 'https://calendly.example/seven', 'https://calendly.example/eight-own', '' )
);

ck( 'when every row is a post, the old option, the team option, the six sync options and the two hooks are gone',
	array(
		array_values( array_intersect( array_merge( array( 'wpcpm_sponsors_index', 'wpcpm_team_members' ), $sync_options ), array_keys( $GLOBALS['opts'] ) ) ),
		$GLOBALS['cleared'],
	),
	array( array(), array( 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick' ) )
);

// One list of each, read by the clean-up above and by the Sponsors module's uninstall, so the two
// places that remove what the sync kept cannot drift apart.
$migration_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-migration.php' );
ck( 'the six option names and the two hook names are the migration\'s own constants, the lists its clean-up reads',
	array(
		defined( 'WPCPM_Sponsors_Migration::SYNC_OPTIONS' ) ? constant( 'WPCPM_Sponsors_Migration::SYNC_OPTIONS' ) : null,
		defined( 'WPCPM_Sponsors_Migration::SYNC_HOOKS' ) ? constant( 'WPCPM_Sponsors_Migration::SYNC_HOOKS' ) : null,
		substr_count( $migration_src, 'self::SYNC_OPTIONS' ),
		substr_count( $migration_src, 'self::SYNC_HOOKS' ),
	),
	array( $sync_options, array( 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick' ), 1, 1 )
);

ck( 'the report is kept autoloaded, since every request reads it until the migration is complete, and it counts its runs',
	array( WPCPM_Sponsors_Migration::OPT_REPORT, $GLOBALS['autoload']['wpcpm_sponsors_migrated'], $report_1['runs'] ),
	array( 'wpcpm_sponsors_migrated', true, 1 )
);

// The same option again, as if the removal had never happened. Between the two runs the site
// moved on: a manager noted something about Mango and handed it to manager eight, and manager
// seven changed their own booking link.
$GLOBALS['opts']['wpcpm_sponsors_index'] = $old_index;
$GLOBALS['opts']['wpcpm_team_members']   = $old_team;
$seeded_before                           = $GLOBALS['seeded'];

WPCPM_Sponsors_Index::patch(
	$KEY_MANGO,
	array(
		'notes'   => 'Renewal in March.',
		'manager' => '8',
	)
);
WPCPM_Manager_Booking::set( 7, 'https://calendly.example/seven-changed' );

$rows_between = WPCPM_Sponsors_Index::rows();
$report_2     = WPCPM_Sponsors_Migration::run();

ck( 'a second run changes nothing: same posts, nothing seeded, still complete',
	array( sponsor_posts(), $GLOBALS['seeded'], array( $report_2['seeded'], $report_2['complete'], $report_2['rows'], $report_2['posts'], $report_2['runs'] ) ),
	array( $posts_1, $seeded_before, array( 0, true, 3, 3, 2 ) )
);

ck( 'and it never writes over a row the store already holds, or a booking link a manager has changed since',
	array( WPCPM_Sponsors_Index::rows(), WPCPM_Manager_Booking::url_for( 7 ) ),
	array( $rows_between, 'https://calendly.example/seven-changed' )
);

seed_old_site();
$GLOBALS['insert_fail'] = 'DreamHost';

$refused = WPCPM_Sponsors_Migration::run();
$held    = array(
	WPCPM_Sponsors_Migration::report() === $refused,
	isset( $GLOBALS['opts']['wpcpm_sponsors_index'] ),
	isset( $GLOBALS['opts']['wpcpm_team_members'] ),
	isset( $GLOBALS['opts']['wpcpm_sponsors_state'] ),
	$GLOBALS['cleared'],
);

$GLOBALS['insert_fail'] = '';
$retried                = WPCPM_Sponsors_Migration::run();

ck( 'a row the store refused keeps the old option and the report says so',
	array(
		array( $refused['complete'], $refused['rows'], $refused['posts'] ),
		$held,
		array( $retried['complete'], $retried['rows'], $retried['posts'], isset( $GLOBALS['opts']['wpcpm_sponsors_index'] ) ),
		array_keys( WPCPM_Sponsors_Index::rows() ),
	),
	array(
		array( false, 3, 2 ),
		array( true, true, true, true, array() ),
		array( true, 3, 3, false ),
		array( $KEY_MANGO, $KEY_WEX, $KEY_DREAM ),
	)
);

// Mango's draft offer is refused on the first run, and so is DreamHost's row. The second run
// finds Mango's row a post already, writes nothing over it, and must still seed its offer.
seed_old_site();
$GLOBALS['insert_fail'] = 'DreamHost';
$GLOBALS['seed_fail']   = $KEY_MANGO;

$first_try   = WPCPM_Sponsors_Migration::run();
$after_first = array(
	$first_try['complete'],
	$first_try['unseeded'],
	$first_try['seeded'],
	isset( $GLOBALS['opts']['wpcpm_sponsors_index'] ),
	WPCPM_Sponsor_Offers::offers_of( $KEY_MANGO ),
);

$GLOBALS['insert_fail'] = '';
$GLOBALS['seed_fail']   = '';
$second_try             = WPCPM_Sponsors_Migration::run();

ck( 'a draft offer that failed to seed is tried again by the next run, though the site already holds its row',
	array(
		$after_first,
		array( $second_try['complete'], $second_try['unseeded'], $second_try['seeded'], isset( $GLOBALS['opts']['wpcpm_sponsors_index'] ) ),
		count( WPCPM_Sponsor_Offers::offers_of( $KEY_MANGO ) ),
		array_column( $GLOBALS['seeded'], 0 ),
	),
	array(
		array( false, array( 'Mango Example' ), 0, true, array() ),
		array( true, array(), 1, false ),
		1,
		array( $KEY_MANGO, $KEY_MANGO ),
	)
);

// The same refusal with every row a post: the missing offer alone keeps the migration open,
// because the old option is the only copy of its text.
seed_old_site();
$GLOBALS['seed_fail'] = $KEY_MANGO;

$offer_missing = WPCPM_Sponsors_Migration::run();

ck( 'while a draft offer that was due is missing the migration is not complete, even with every row a post, and the old option stays',
	array(
		array( $offer_missing['rows'], $offer_missing['posts'], $offer_missing['complete'], $offer_missing['unseeded'] ),
		WPCPM_Sponsors_Migration::report()['unseeded'],
		array( isset( $GLOBALS['opts']['wpcpm_sponsors_index'] ), isset( $GLOBALS['opts']['wpcpm_team_members'] ), $GLOBALS['cleared'] ),
	),
	array(
		array( 3, 3, false, array( 'Mango Example' ) ),
		array( 'Mango Example' ),
		array( true, true, array() ),
	)
);

// Team member four's address belongs to user 9, who is an account on the site and no program
// manager; Mango's team member, user 7, is one, and still maps.
seed_old_site();

$KEY_NINE = 'recSEED0000000004';

$GLOBALS['opts']['wpcpm_sponsors_index']['rows'][ $KEY_NINE ] = array(
	'record_id' => $KEY_NINE,
	'name'      => 'Ninth Example',
	'status'    => 'Approved',
	'manager'   => 'recTEAM0000000004',
);

$capability_run = WPCPM_Sponsors_Migration::run();

ck( 'a team address whose account lacks the program capability leaves the sponsor unmapped and listed',
	array( WPCPM_Sponsors_Index::row( $KEY_NINE )['manager'], $capability_run['unmapped'], WPCPM_Sponsors_Index::row( $KEY_MANGO )['manager'] ),
	array( '', array( 'DreamHost', 'Ninth Example' ), '7' )
);

reset_site();
$fresh = WPCPM_Sponsors_Migration::run();

ck( 'a site with no old index reports complete with zero rows',
	array( $fresh['complete'], $fresh['rows'], $fresh['posts'], $fresh['seeded'], $fresh['unmapped'], WPCPM_Sponsors_Migration::report()['complete'], sponsor_posts() ),
	array( true, 0, 0, 0, array(), true, array() )
);

// A row that fails on every attempt, then a minute passing (the transient taken away), then the
// failure cleared, then another minute: the old option is read on the first request and once a
// minute after, and never again once the report says complete.
seed_old_site();
$GLOBALS['insert_fail'] = 'DreamHost';
$reads                  = array();

WPCPM_Sponsors_Migration::maybe_run();
$reads[] = reads_of_index();
WPCPM_Sponsors_Migration::maybe_run();
$reads[] = reads_of_index();
$ttl     = isset( $GLOBALS['transient_ttl']['wpcpm_sponsors_migrating'] ) ? $GLOBALS['transient_ttl']['wpcpm_sponsors_migrating'] : null;

unset( $GLOBALS['transients']['wpcpm_sponsors_migrating'] );
WPCPM_Sponsors_Migration::maybe_run();
$reads[] = reads_of_index();

$GLOBALS['insert_fail'] = '';
unset( $GLOBALS['transients']['wpcpm_sponsors_migrating'] );
WPCPM_Sponsors_Migration::maybe_run();
$reads[] = reads_of_index();

unset( $GLOBALS['transients']['wpcpm_sponsors_migrating'] );
WPCPM_Sponsors_Migration::maybe_run();
$reads[] = reads_of_index();

ck( 'maybe_run() does nothing once complete and at most once a minute before',
	array( $reads, $ttl, WPCPM_Sponsors_Migration::report()['complete'] ),
	array( array( 1, 1, 2, 3, 3 ), 60, true )
);

// The Collaboration Agreement used to mark a document whose
// Airtable write failed, for the sponsors sync to finish. Nothing reads the mark since the record
// moved onto the site, so the clean-up removes what an earlier failure left, and only the mark.
$agreement_id = 800;

seed_old_site();
$GLOBALS['posts'][ $agreement_id ] = new WP_Post(
	array(
		'ID'          => $agreement_id,
		'post_type'   => 'wpcpm_sponsor_agr',
		'post_status' => 'private',
		'post_title'  => 'Agreement',
	)
);
$GLOBALS['pmeta'][ $agreement_id ] = array(
	'_wpcpm_sagr_airtable_pending' => 1,
	'_wpcpm_sagr_state'            => 'submitted',
);
$GLOBALS['insert_fail'] = 'DreamHost';
WPCPM_Sponsors_Migration::run();
$mark_while_open        = $GLOBALS['pmeta'][ $agreement_id ];
$GLOBALS['insert_fail'] = '';
WPCPM_Sponsors_Migration::run();

ck( 'the clean-up removes the mark an earlier failed Airtable write left on an agreement, and nothing else of it, once the move completes',
	array( $mark_while_open, $GLOBALS['pmeta'][ $agreement_id ], WPCPM_Sponsors_Migration::report()['complete'] ),
	array(
		array(
			'_wpcpm_sagr_airtable_pending' => 1,
			'_wpcpm_sagr_state'            => 'submitted',
		),
		array( '_wpcpm_sagr_state' => 'submitted' ),
		true,
	)
);

echo "\n=== Test sponsors (1.123.18) ===\n";

// A test sponsor is never listed publicly. A sponsor whose name, trimmed, starts with "TEST" in
// capitals is one from the move onto the site and from the upgrade on: the live site holds
// "TEST - WordPress Education Dashboard (do not use)". The rule is the capitals: "Testing Co" and
// "Contest Labs" are real names. And since 1.123.23 the word: "TEST" alone, or followed by something
// that is neither a letter nor a digit in any script, so a real company named "TESTAMENT", "TESTIFY"
// or "TEST2 Labs" is not marked a test sponsor, nor pre-ticked as one at approval.
ck( 'is_test_name(): a trimmed name starting with the word TEST in capitals, and nothing else', array_map( array( 'WPCPM_Sponsors_Index', 'is_test_name' ), array( 'TEST - WordPress Education Dashboard (do not use)', '  TEST Minted Sponsor', 'TEST', ' TEST ', 'TEST - Acme', 'TEST: Acme', 'TEST Acme', 'TEST(1)', "TEST\tAcme", 'TEST_Acme', 'TESTING', 'TESTAMENT', 'TESTIFY', 'TEST2 Labs', "TEST\u{00C9}cole", "TEST\u{0661}", "TEST\u{4E2D}", 'Test Acme', 'test acme', 'Test Co', 'Contest Labs', 'test', '' ) ), array( true, true, true, true, true, true, true, true, true, true, false, false, false, false, false, false, false, false, false, false, false, false, false ) );

// A name that is not valid UTF-8 cannot be read by the Unicode-aware rule, which then answers
// nothing at all: it is read byte by byte instead, "TEST" first and then no ASCII letter or digit, as
// the first four letters alone once read it, so such a name is no less a test than it was.
ck( 'is_test_name(): a name that is not valid UTF-8 is read byte by byte, the word TEST first', array_map( array( 'WPCPM_Sponsors_Index', 'is_test_name' ), array( "TEST\xFF Acme", "TEST - \xC3", "TEST\xC3", "TESTA\xFF", "TEST2\xFF", "Test\xFF", "\xFFTEST", " TEST \xFF " ) ), array( true, true, true, false, false, false, false, true ) );

seed_old_site();
$old_test_key = 'recSEED0000000077';
$GLOBALS['opts']['wpcpm_sponsors_index']['rows'][ $old_test_key ] = array( 'record_id' => $old_test_key, 'name' => ' TEST - WordPress Education Dashboard (do not use)', 'status' => 'Approved' );
$old_word_key = 'recSEED0000000078';
$GLOBALS['opts']['wpcpm_sponsors_index']['rows'][ $old_word_key ] = array( 'record_id' => $old_word_key, 'name' => 'TESTAMENT Publishing', 'status' => 'Approved' );
WPCPM_Sponsors_Migration::run();
ck( 'the move onto the site marks a held sponsor named TEST as a test sponsor, and no other, TESTAMENT included', array( WPCPM_Sponsors_Index::row( $old_test_key )['test'], WPCPM_Sponsors_Index::row( $KEY_MANGO )['test'], WPCPM_Sponsors_Index::row( $KEY_DREAM )['test'], WPCPM_Sponsors_Index::row( $old_word_key )['test'] ), array( true, false, false, false ) );

reset_site();
$t_live  = 'recTESTLIVE000001';
$t_named = 'recTESTLIVE000002';
$t_plain = 'recTESTLIVE000003';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $t_live, 'name' => 'TEST - WordPress Education Dashboard (do not use)', 'status' => 'Approved' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $t_named, 'name' => 'Test Co', 'status' => 'Approved' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $t_plain, 'name' => 'Plain Sponsor', 'status' => 'Approved' ) ) );
$t_word = 'recTESTLIVE000004';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $t_word, 'name' => 'TESTIFY Labs', 'status' => 'Approved' ) ) );
ck( 'before the upgrade a sponsor the store already holds is no test sponsor, whatever its name', array( WPCPM_Sponsors_Index::row( $t_live )['test'], WPCPM_Sponsors_Index::test_keys() ), array( false, array() ) );
WPCPM_Sponsors_Index::maybe_mark_tests();
ck( 'the upgrade marks every sponsor the store holds whose name starts with the word TEST, once, and records that it ran', array( WPCPM_Sponsors_Index::row( $t_live )['test'], WPCPM_Sponsors_Index::row( $t_named )['test'], WPCPM_Sponsors_Index::row( $t_plain )['test'], WPCPM_Sponsors_Index::row( $t_word )['test'], WPCPM_Sponsors_Index::test_keys(), get_option( WPCPM_Sponsors_Index::OPT_TESTS_MARKED ) ), array( true, false, false, false, array( $t_live ), 1 ) );
WPCPM_Sponsors_Index::patch( $t_live, array( 'test' => false ) );
WPCPM_Sponsors_Index::maybe_mark_tests();
ck( 'and only once: a flag an Administrator cleared since stays cleared', WPCPM_Sponsors_Index::row( $t_live )['test'], false );
ck( 'the module runs the upgrade on init right after the move onto the site', substr_count( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), "add_action( 'init', array( 'WPCPM_Sponsors_Migration', 'maybe_run' ), 6 );\n\n\t\t// A test sponsor is never listed publicly (1.123.18)" ), 1 );

echo "\n=== One run at a time ===\n";

// The pace above is a transient, read and then set: two first requests after an upgrade can both
// find it unset and both run, and insert()'s own check-then-create can then make two posts for
// one key. A run takes the lock option first, with add_option(), which answers true only to the
// request that creates the row, so a second run finds it held and does nothing at all.
$lock_timeout = defined( 'WPCPM_Sponsors_Migration::LOCK_TIMEOUT' ) ? constant( 'WPCPM_Sponsors_Migration::LOCK_TIMEOUT' ) : 300;

seed_old_site();
$GLOBALS['opts']['wpcpm_sponsors_migrate_lock'] = time();

$locked_run = WPCPM_Sponsors_Migration::run();
WPCPM_Sponsors_Migration::maybe_run();

ck( 'a run cannot start while another holds the lock',
	array( $locked_run, sponsor_posts(), reads_of_index(), WPCPM_Sponsors_Migration::report()['runs'], $GLOBALS['seeded'], isset( $GLOBALS['opts']['wpcpm_sponsors_index'] ), isset( $GLOBALS['opts']['wpcpm_sponsors_migrate_lock'] ) ),
	array( null, array(), 0, 0, array(), true, true )
);

seed_old_site();
$released_run = WPCPM_Sponsors_Migration::run();

ck( 'the lock is released after a run, every post of the run was made holding it, and it is never autoloaded',
	array( is_array( $released_run ) ? $released_run['complete'] : $released_run, $GLOBALS['lock_seen'], array_key_exists( 'wpcpm_sponsors_migrate_lock', $GLOBALS['autoload'] ) ? $GLOBALS['autoload']['wpcpm_sponsors_migrate_lock'] : 'never taken', isset( $GLOBALS['opts']['wpcpm_sponsors_migrate_lock'] ) ),
	array( true, array( true, true, true ), false, false )
);

seed_old_site();
$GLOBALS['opts']['wpcpm_sponsors_migrate_lock'] = time() - $lock_timeout - 1;
$stale_run                                      = WPCPM_Sponsors_Migration::run();

ck( 'a lock older than the timeout belonged to a run that died holding it, and the next run takes it over and gives it back',
	array( is_array( $stale_run ) ? $stale_run['complete'] : $stale_run, count( sponsor_posts() ), $GLOBALS['lock_seen'], isset( $GLOBALS['opts']['wpcpm_sponsors_migrate_lock'] ) ),
	array( true, 3, array( true, true, true ), false )
);

// Wired where the design puts it: on every request, right after the post type exists, and at
// activation, where a site that already holds an index migrates at once instead of scheduling
// the sync.
$module_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$boot       = substr( $module_src, (int) strpos( $module_src, 'public function boot()' ) );
$boot       = substr( $boot, 0, (int) strpos( $boot, "\n\t}\n" ) );
$activate   = substr( $module_src, (int) strpos( $module_src, 'public function activate()' ) );
$activate   = substr( $activate, 0, (int) strpos( $activate, "\n\t}\n" ) );

ck( 'the module runs the migration on init right after the post type is registered, and at activation',
	array(
		false !== strpos( $boot, "add_action( 'init', array( 'WPCPM_Sponsors_Index', 'register_post_type' ), 5 );\n\n" ),
		substr_count( $boot, "add_action( 'init', array( 'WPCPM_Sponsors_Migration', 'maybe_run' ), 6 );" ),
		substr_count( $activate, 'WPCPM_Sponsors_Migration::maybe_run();' ),
	),
	array( true, 1, 1 )
);

printf( "\n%s (%d checks)\n", $fails ? "$fails FAILED" : 'ALL PASS', (int) $total );
exit( $fails ? 1 : 0 );
