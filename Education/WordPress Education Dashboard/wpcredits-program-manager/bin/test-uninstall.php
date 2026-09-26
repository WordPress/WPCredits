<?php
/**
 * uninstall.php, run whole and the way WordPress runs it (the deep check of 1.109.1, TRACKS-2
 * with SURFACES-1 and TESTS-DOCS-1, and SURFACES-7).
 *
 * WordPress includes the file from inside `uninstall_plugin()` in wp-admin/includes/plugin.php,
 * and `wp plugin uninstall` calls the same function, so the file's top level is a function's
 * scope and not the global one: a global the file reads has to be declared there. No suite ran
 * the file before this one. bin/test-track-store.php reads it as text, and neither a text check
 * nor an include at a script's own top level can see a missing `global $wpdb;`, which is how the
 * option sweep came to die on a call to `get_col()` on null on every attempt to delete the
 * plugin, with the Track Builder's clean-up and everything after it never run.
 *
 * So the file is included here whole, from inside a function shaped like core's, and every class
 * it requires is the plugin's own, loaded by the file itself: a class the clean-up reaches for and
 * the file forgets to require is a fatal here as it is on a site, which is why nothing below loads
 * a plugin class first and the names the site is seeded with are written out. WordPress is a small
 * site in memory: an options table with its transients, posts with their meta and revisions, users
 * with their meta, roles and capabilities, the cron schedule, the mail sent, and a `$wpdb` global
 * that runs the few statements the clean-up sends against those tables and keeps each one. A
 * statement, or a query argument, that the stand-ins do not model is kept apart and fails a check,
 * so nothing passes by being quietly ignored.
 *
 * The run also covers what the Track Builder keeps outside its posts and options, which the file
 * did not remove: the lock a publish run holds, and the copies of the base's schema and of Learn's
 * courses the track editor reads (SURFACES-7).
 *
 * Run from the plugin root:  php bin/test-uninstall.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

// WordPress defines these in `wp_initial_constants()`, before any plugin code runs, the uninstall
// request included, and the clean-up reads one of them: the Sponsors module reads each offer on
// the way to deleting it, and the offer reads the settings' defaults. The plugin's own constants
// (WPCPM_VERSION, WPCPM_PLUGIN_DIR and the rest) are left undefined: uninstall.php does not load
// the main plugin file, and neither does core before including it, so a class that reached for
// one on the way out would be a fatal on a site as it would be here.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'YEAR_IN_SECONDS', 31536000 );

$GLOBALS['opts']      = array(); // The options table: name => value.
$GLOBALS['posts']     = array(); // ID => WP_Post.
$GLOBALS['pmeta']     = array(); // Post ID => key => value. A row may outlive its post.
$GLOBALS['users']     = array(); // User ID => its roles and the capabilities granted to it alone.
$GLOBALS['umeta']     = array(); // User ID => key => value.
$GLOBALS['roles']     = array(); // Slug => WP_Role.
$GLOBALS['cron']      = array(); // Each scheduled event: its hook and its arguments.
$GLOBALS['mail']      = array(); // Each message handed to wp_mail().
$GLOBALS['unmodeled'] = array(); // Anything asked of a stand-in that it does not model.
$GLOBALS['next_id']   = 100;

class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_title = '', $post_content = '', $post_parent = 0, $post_author = 0;
}
class WP_Role {
	public $name, $capabilities;
	public function __construct( $name, $capabilities ) { $this->name = $name; $this->capabilities = $capabilities; }
	public function remove_cap( $cap ) { unset( $this->capabilities[ $cap ] ); }
}

/** An account, read from and written back to the users table above, as WordPress keeps it in its meta. */
class WP_User {
	public $ID = 0, $roles = array(), $caps = array();
	public function __construct( $id = 0 ) {
		$this->ID = (int) $id;
		if ( isset( $GLOBALS['users'][ $this->ID ] ) ) {
			$this->roles = $GLOBALS['users'][ $this->ID ]['roles'];
			$this->caps  = $GLOBALS['users'][ $this->ID ]['caps'];
		}
	}
	public function exists() { return isset( $GLOBALS['users'][ $this->ID ] ); }
	public function remove_role( $role ) { $this->roles = array_values( array_diff( $this->roles, array( $role ) ) ); $this->save(); }
	public function set_role( $role ) { $this->roles = array( $role ); $this->save(); }
	public function remove_cap( $cap ) { unset( $this->caps[ $cap ] ); $this->save(); }
	private function save() { $GLOBALS['users'][ $this->ID ] = array( 'roles' => $this->roles, 'caps' => $this->caps ); }
}

/**
 * The database, as far as the clean-up reaches it.
 *
 * `esc_like()` escapes as WordPress does and `prepare()` quotes as SQL does, and a statement is
 * read back against the options and posts tables above, a LIKE pattern matching the way MySQL
 * matches one: `\_` is an underscore, `_` any one character and `%` any run, compared without
 * regard to case as the table's collation compares. Every statement is kept in `$queries`, in
 * the order it was sent.
 */
class WPCPM_Test_Wpdb {
	public $prefix  = 'wp_';
	public $options = 'wp_options';
	public $posts   = 'wp_posts';
	public $queries = array();

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$next = 0;

		return preg_replace_callback(
			'/%[sd]/',
			function ( $m ) use ( $args, &$next ) {
				$arg = isset( $args[ $next ] ) ? $args[ $next ] : '';
				++$next;

				return '%d' === $m[0] ? (string) (int) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
			},
			$query
		);
	}

	public function get_col( $query ) {
		$this->queries[] = $query;

		if ( preg_match( '/^SELECT option_name FROM wp_options WHERE (.+)$/s', $query, $m ) ) {
			$patterns = like_patterns( $m[1] );

			if ( null !== $patterns ) {
				return array_values( array_filter( array_map( 'strval', array_keys( $GLOBALS['opts'] ) ), function ( $name ) use ( $patterns ) { return like_any( $patterns, $name ); } ) );
			}
		}

		if ( preg_match( "/^SELECT ID FROM wp_posts WHERE post_type = '([^']*)'$/", $query, $m ) ) {
			$ids = array();

			foreach ( $GLOBALS['posts'] as $post ) {
				if ( $post->post_type === $m[1] ) {
					$ids[] = (string) $post->ID; // As a column comes back from MySQL: strings.
				}
			}

			return $ids;
		}

		$GLOBALS['unmodeled'][] = 'get_col: ' . $query;

		return array();
	}

	public function query( $query ) {
		$this->queries[] = $query;

		if ( preg_match( '/^DELETE FROM wp_options WHERE (.+)$/s', $query, $m ) ) {
			$patterns = like_patterns( $m[1] );

			if ( null !== $patterns ) {
				$gone = 0;

				foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
					if ( like_any( $patterns, (string) $name ) ) {
						unset( $GLOBALS['opts'][ $name ] );
						++$gone;
					}
				}

				return $gone;
			}
		}

		if ( preg_match( '/^DROP TABLE IF EXISTS \w+$/', $query ) ) {
			return true;
		}

		$GLOBALS['unmodeled'][] = 'query: ' . $query;

		return false;
	}
}

/**
 * The patterns of a WHERE clause made of `option_name LIKE '...'` tests joined by OR, the one shape
 * the clean-up sends; null for anything else, which is kept as unmodeled.
 *
 * @param string $where The clause.
 * @return string[]|null
 */
function like_patterns( $where ) {
	if ( ! preg_match_all( "/option_name LIKE '((?:[^']|'')*)'/", $where, $found ) ) {
		return null;
	}

	$rebuilt = implode( ' OR ', array_map( function ( $p ) { return "option_name LIKE '" . $p . "'"; }, $found[1] ) );

	return $rebuilt === $where ? array_map( function ( $p ) { return str_replace( "''", "'", $p ); }, $found[1] ) : null;
}

/**
 * Whether a name matches any of the patterns, as MySQL's LIKE reads one.
 *
 * @param string[] $patterns The patterns.
 * @param string   $name     The option name.
 * @return bool
 */
function like_any( $patterns, $name ) {
	foreach ( $patterns as $pattern ) {
		$regex = '';
		$len   = strlen( $pattern );

		for ( $i = 0; $i < $len; ++$i ) {
			$c = $pattern[ $i ];

			if ( '\\' === $c && $i + 1 < $len ) {
				++$i;
				$regex .= preg_quote( $pattern[ $i ], '/' );
			} elseif ( '%' === $c ) {
				$regex .= '.*';
			} elseif ( '_' === $c ) {
				$regex .= '.';
			} else {
				$regex .= preg_quote( $c, '/' );
			}
		}

		if ( 1 === preg_match( '/^' . $regex . '$/is', $name ) ) {
			return true;
		}
	}

	return false;
}

$wpdb = new WPCPM_Test_Wpdb();

function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/' ) . '/'; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }
function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => '/srv/example/wp-content/uploads', 'baseurl' => 'https://example.test/wp-content/uploads' ); }

function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $name ] : $default; }
function delete_option( $name ) {
	if ( ! array_key_exists( $name, $GLOBALS['opts'] ) ) {
		return false; // WordPress answers false for an option that is not there.
	}
	unset( $GLOBALS['opts'][ $name ] );
	return true;
}

// Transients as WordPress keeps them where no object cache is installed: two rows in the options
// table, the value and its timeout.
function set_transient( $name, $value, $expiration = 0 ) {
	if ( $expiration ) {
		$GLOBALS['opts'][ '_transient_timeout_' . $name ] = time() + (int) $expiration;
	}
	$GLOBALS['opts'][ '_transient_' . $name ] = $value;
	return true;
}
function get_transient( $name ) { return get_option( '_transient_' . $name ); }
function delete_transient( $name ) {
	$gone = delete_option( '_transient_' . $name );
	if ( $gone ) {
		delete_option( '_transient_timeout_' . $name );
	}
	return $gone;
}

/** Every status WordPress registers; `any` in a query means all but the ones excluded from search. */
function get_post_stati( $args = array() ) {
	if ( ! empty( $args ) ) {
		$GLOBALS['unmodeled'][] = 'get_post_stati: ' . wp_json( $args );
	}
	$stati = array( 'publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit', 'request-pending', 'request-confirmed', 'request-failed', 'request-completed' );
	return array_combine( $stati, $stati );
}
function wp_json( $value ) { return (string) json_encode( $value ); }
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function get_post_meta( $id, $key = '', $single = false ) {
	$row = isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : null;
	if ( $single ) {
		return null === $row ? '' : $row;
	}
	return null === $row ? array() : array( $row );
}

/**
 * Posts as `get_posts()` finds them: by type and status, `any` as WordPress reads it (a trashed
 * post and an auto-draft are not "any"), five unless a number is asked for, and the two meta tests
 * the clean-up uses.
 *
 * @param array $args The query.
 * @return array
 */
function get_posts( $args = array() ) {
	$known = array( 'post_type', 'post_status', 'numberposts', 'posts_per_page', 'fields', 'orderby', 'order', 'suppress_filters', 'meta_query', 'meta_key', 'meta_value' );

	foreach ( array_diff( array_keys( $args ), $known ) as $key ) {
		$GLOBALS['unmodeled'][] = 'get_posts: ' . $key;
	}

	$types    = (array) ( isset( $args['post_type'] ) ? $args['post_type'] : 'post' );
	$statuses = isset( $args['post_status'] ) ? $args['post_status'] : 'publish';
	$statuses = 'any' === $statuses ? array_values( array_diff( array_keys( get_post_stati() ), array( 'trash', 'auto-draft', 'request-pending', 'request-confirmed', 'request-failed', 'request-completed' ) ) ) : (array) $statuses;
	$limit    = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : ( isset( $args['numberposts'] ) ? (int) $args['numberposts'] : 5 );
	$found    = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ! in_array( $post->post_type, $types, true ) || ! in_array( $post->post_status, $statuses, true ) ) {
			continue;
		}

		$meta = isset( $GLOBALS['pmeta'][ $post->ID ] ) ? $GLOBALS['pmeta'][ $post->ID ] : array();

		if ( isset( $args['meta_key'] ) && ( ! array_key_exists( $args['meta_key'], $meta ) || ( isset( $args['meta_value'] ) && (string) $meta[ $args['meta_key'] ] !== (string) $args['meta_value'] ) ) ) {
			continue;
		}

		foreach ( (array) ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() ) as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'], $clause['compare'] ) || 'EXISTS' !== $clause['compare'] ) {
				$GLOBALS['unmodeled'][] = 'get_posts: meta_query ' . wp_json( $clause );
				continue;
			}

			if ( ! array_key_exists( $clause['key'], $meta ) ) {
				continue 2;
			}
		}

		$found[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $post->ID : $post;

		if ( $limit > 0 && count( $found ) >= $limit ) {
			break;
		}
	}

	return $found;
}

/**
 * A post goes as WordPress removes one for good: its revisions and its meta with it. An attachment,
 * which WordPress hands to `wp_delete_attachment()`, and a post or page asked for without force,
 * which it only trashes, are not modeled and are kept as such.
 *
 * @param int  $id    The post.
 * @param bool $force Whether to skip the trash.
 * @return WP_Post|false
 */
function wp_delete_post( $id, $force = false ) {
	$post = get_post( $id );

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( 'attachment' === $post->post_type || ( ! $force && in_array( $post->post_type, array( 'post', 'page' ), true ) ) ) {
		$GLOBALS['unmodeled'][] = 'wp_delete_post: ' . wp_json( array( $post->post_type, $force ) );
		return false;
	}

	foreach ( $GLOBALS['posts'] as $child ) {
		if ( 'revision' === $child->post_type && (int) $child->post_parent === (int) $post->ID ) {
			unset( $GLOBALS['posts'][ $child->ID ], $GLOBALS['pmeta'][ $child->ID ] );
		}
	}

	unset( $GLOBALS['posts'][ $post->ID ], $GLOBALS['pmeta'][ $post->ID ] );

	return $post;
}

/**
 * Metadata as `delete_metadata()` removes it with `$delete_all`: the key from every object of the
 * type, whatever its value, a row whose object is already gone included.
 *
 * @param string $type       'user' or 'post'.
 * @param int    $object_id  Ignored with `$delete_all`, as in WordPress.
 * @param string $key        The key.
 * @param mixed  $value      Only '' is modeled: every value.
 * @param bool   $delete_all Whether to remove the key from every object.
 * @return bool
 */
function delete_metadata( $type, $object_id, $key, $value = '', $delete_all = false ) {
	$store = array( 'user' => 'umeta', 'post' => 'pmeta' );

	if ( ! isset( $store[ $type ] ) || ! $delete_all || '' !== $value ) {
		$GLOBALS['unmodeled'][] = 'delete_metadata: ' . wp_json( array( $type, $object_id, $key, $value, $delete_all ) );
		return false;
	}

	$gone = false;

	foreach ( array_keys( $GLOBALS[ $store[ $type ] ] ) as $id ) {
		if ( array_key_exists( $key, $GLOBALS[ $store[ $type ] ][ $id ] ) ) {
			unset( $GLOBALS[ $store[ $type ] ][ $id ][ $key ] );
			$gone = true;
		}
	}

	return $gone;
}

/**
 * Users as `get_users()` finds them: by role, or by a meta key and value; IDs or accounts, every one
 * unless a number is asked for.
 *
 * @param array $args The query.
 * @return array
 */
function get_users( $args = array() ) {
	foreach ( array_diff( array_keys( $args ), array( 'role', 'fields', 'number', 'meta_key', 'meta_value' ) ) as $key ) {
		$GLOBALS['unmodeled'][] = 'get_users: ' . $key;
	}

	$found = array();

	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( isset( $args['role'] ) && ! in_array( $args['role'], $user['roles'], true ) ) {
			continue;
		}

		if ( isset( $args['meta_key'] ) ) {
			$meta = isset( $GLOBALS['umeta'][ $id ] ) ? $GLOBALS['umeta'][ $id ] : array();

			if ( ! array_key_exists( $args['meta_key'], $meta ) || ( isset( $args['meta_value'] ) && (string) $meta[ $args['meta_key'] ] !== (string) $args['meta_value'] ) ) {
				continue;
			}
		}

		$found[] = ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) ? $id : new WP_User( $id );

		if ( isset( $args['number'] ) && (int) $args['number'] > 0 && count( $found ) >= (int) $args['number'] ) {
			break;
		}
	}

	return $found;
}
function get_user_by( $field, $value ) {
	if ( 'id' !== $field ) {
		$GLOBALS['unmodeled'][] = 'get_user_by: ' . $field;
		return false;
	}
	return isset( $GLOBALS['users'][ (int) $value ] ) ? new WP_User( (int) $value ) : false;
}
function get_role( $role ) { return isset( $GLOBALS['roles'][ $role ] ) ? $GLOBALS['roles'][ $role ] : null; }
function remove_role( $role ) { unset( $GLOBALS['roles'][ $role ] ); }

/** Clears the events of a hook that were scheduled with these arguments, as WordPress does. */
function wp_clear_scheduled_hook( $hook, $args = array() ) {
	$cleared = 0;
	foreach ( $GLOBALS['cron'] as $i => $event ) {
		if ( $event['hook'] === $hook && $event['args'] === $args ) {
			unset( $GLOBALS['cron'][ $i ] );
			++$cleared;
		}
	}
	return $cleared;
}
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	$GLOBALS['mail'][] = array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
	);
	return true;
}

/**
 * A post of the site, returned by ID.
 *
 * @param string $type   Its type.
 * @param string $status Its status.
 * @param array  $meta   Its meta.
 * @param int    $parent Its parent, for a revision.
 * @return int
 */
function a_post( $type, $status = 'private', $meta = array(), $parent = 0 ) {
	$post              = new WP_Post();
	$post->ID          = ++$GLOBALS['next_id'];
	$post->post_type   = $type;
	$post->post_status = $status;
	$post->post_parent = $parent;

	$GLOBALS['posts'][ $post->ID ] = $post;
	$GLOBALS['pmeta'][ $post->ID ] = $meta;

	return $post->ID;
}

/**
 * An account of the site, returned by ID.
 *
 * @param string[] $roles Its roles.
 * @param string[] $caps  Capabilities granted to it alone.
 * @param array    $meta  Its meta.
 * @return int
 */
function a_user( $roles, $caps = array(), $meta = array() ) {
	$id = ++$GLOBALS['next_id'];

	$GLOBALS['users'][ $id ] = array(
		'roles' => $roles,
		'caps'  => array_fill_keys( $caps, true ),
	);
	$GLOBALS['umeta'][ $id ] = $meta;

	return $id;
}

/**
 * The value of a constant once the run has loaded its class, or null.
 *
 * @param string $name `Class::NAME`.
 * @return mixed
 */
function declared( $name ) {
	return defined( $name ) ? constant( $name ) : null;
}

/**
 * Runs uninstall.php as `uninstall_plugin()` runs it: the constant defined, then the file included
 * from inside this function, so that the file's top level is this function's scope and the site's
 * globals reach it only where it declares them. Core's two locals are here, under core's names,
 * and nothing else is.
 *
 * @param string $plugin The plugin's basename, as core is handed it.
 * @return string The message of anything thrown on the way, which is where a call on a null
 *                `$wpdb` lands; empty when the file ran to its end.
 */
function run_uninstall( $plugin ) {
	$file = $plugin;

	define( 'WP_UNINSTALL_PLUGIN', $file );

	try {
		include_once dirname( __DIR__ ) . '/uninstall.php';
	} catch ( Throwable $e ) {
		return sprintf( '%s: %s (%s:%d)', get_class( $e ), $e->getMessage(), basename( $e->getFile() ), $e->getLine() );
	}

	return '';
}

$fails = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label What is asked.
 * @param mixed  $got   What happened.
 * @param mixed  $want  What should have happened.
 */
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

/*
 * The site the plugin is deleted from. Every name is written out: the classes that declare them are
 * not loaded until uninstall.php loads them, and loading them first would hide a require the file
 * forgot. The last section compares these names with the classes' own once the run has loaded them.
 */

// The options the file names, directly or through a class it calls.
$named_options = array(
	'wpcpm_settings',                      // WPCPM_Settings::OPT_NAME.
	'wpcpm_settings_version',              // WPCPM_Settings::OPT_VERSION.
	'wpcpm_privacy_version',               // WPCPM_Privacy_Guard::OPT_VERSION.
	'wpcpm_airtable_backoff',              // WPCPM_Airtable::BACKOFF_OPTION.
	'wpcpm_countries',                     // WPCPM_Countries::OPT_NAME.
	'wpcpm_sponsor_members_caps_repaired', // WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED.
	'wpcpm_roles_version',                 // WPCPM_Roles::OPT_VERSION, through unregister().
	'wpcpm_notices',                       // WPCPM_Notices::OPT_NAME, through delete_all().
	'wpcpm_notices_migrated',              // WPCPM_Notices::OPT_MIGRATED.
	'wpcpm_student_modules',
	'wpcpm_notices_plain',                 // WPCPM_Notices::OPT_PLAIN.
	'wpcpm_tracks',                        // WPCPM_Tracks::OPT_TRACKS, through WPCPM_Track_Store::delete_all().
	'wpcpm_tracks_skipped',                // WPCPM_Track_Store::OPT_SKIPPED.
	'wpcpm_tracks_seeded',                 // WPCPM_Track_Store::OPT_SEEDED.
	'wpcpm_tracks_upgrade_lock',           // WPCPM_Track_Store::OPT_UPGRADE_LOCK, through delete_all().
	'wpcpm_track_fields_growth',           // The form of the track post below.
	'wpcpm_track_fields_listed',           // The form of a track the index lists and no post holds.
);

foreach ( $named_options as $name ) {
	$GLOBALS['opts'][ $name ] = array( 'seeded' => true );
}

$GLOBALS['opts']['wpcpm_tracks'] = array( array( 'key' => 'growth' ), array( 'key' => 'listed' ) );

// What the prefix loop sweeps: two institutions' module orders and a form neither a post nor the
// index knows of, written by a compile that never finished.
$swept_options = array( 'wpcpm_institution_modules_recSEED0000000001', 'wpcpm_institution_modules_recSEED0000000002', 'wpcpm_track_fields_orphan' );

foreach ( $swept_options as $name ) {
	$GLOBALS['opts'][ $name ] = array( 'seeded' => true );
}

// What the Track Builder keeps outside its posts (SURFACES-7): the lock a publish run holds while
// it creates columns, which a run killed halfway leaves behind for good; the claim a request holds
// while it upgrades the seeding, which a request killed inside the upgrade leaves behind; the copy of
// the base's schema the track editor reads, fifteen minutes; and the copies of a Learn course and of
// a course's structure, a day each, named after the course.
$GLOBALS['opts']['wpcpm_track_publish_lock']  = time() - DAY_IN_SECONDS;
$GLOBALS['opts']['wpcpm_tracks_upgrade_lock'] = time() - HOUR_IN_SECONDS;
set_transient( 'wpcpm_airtable_schema', array( 'read' => time() ), 15 * MINUTE_IN_SECONDS );
set_transient( 'wpcpm_learn_course_example-course', array( 'id' => 1 ), DAY_IN_SECONDS );
set_transient( 'wpcpm_learn_structure_1', array(), DAY_IN_SECONDS );

// The mail log holds the addresses mail went to; the queue, the people still waiting for one; the
// run, the counts and times of the last bulk invite (the final fix wave, item 6).
$GLOBALS['opts']['wpcpm_mail_log']     = array( array( 'to' => 'student-one@example.test' ) );
$GLOBALS['opts']['wpcpm_invite_queue'] = array( 7 );
$GLOBALS['opts']['wpcpm_invite_run']   = array( 'total' => 241, 'started' => time() - HOUR_IN_SECONDS, 'finished' => 0 );

// What is not the plugin's, and the key the signed agreements are encrypted with, which the file
// keeps on purpose.
$GLOBALS['opts']['blogname']          = 'Example';
$GLOBALS['opts']['admin_email']       = 'admin@example.test';
$GLOBALS['opts']['wpcpm_private_key'] = 'kept';
set_transient( 'feed_example', 'kept', HOUR_IN_SECONDS );

// One post of every type the clean-up removes, private as the modules keep them; an offer is found
// through its sponsor, and every offer has one.
$plugin_types = array( 'wpcpm_notice', 'wpcpm_dup_copy', 'wpcpm_track', 'wpcpm_inst_report', 'wpcpm_inst_request', 'wpcpm_inst_app', 'wpcpm_agreement', 'wpcpm_sponsor_app', 'wpcpm_audit_entry', 'wpcpm_import_batch', 'wpcpm_offer', 'wpcpm_mentor_call', 'wpcpm_mentor_note', 'wpcpm_inst_invite', 'wpcpm_sponsor_agr', 'wpcpm_handbook' );

foreach ( $plugin_types as $type ) {
	a_post( $type, 'private', 'wpcpm_offer' === $type ? array( '_wpcpm_offer_sponsor' => 'recSEED0000000003' ) : array() );
}

// An agreement that names a signed file, which puts it in the inventory mailed on the way out; a
// trashed report and a trashed track, which go like the rest.
a_post( 'wpcpm_agreement', 'private', array( '_wpcpm_agr_file' => array( 'path' => '/srv/example/wp-content/uploads/.wpcpm-private/agreement-one.pdf.enc', 'sha256' => str_repeat( 'a', 64 ) ), '_wpcpm_agr_institution' => 'recSEED0000000001' ) );
a_post( 'wpcpm_inst_report', 'trash' );
a_post( 'wpcpm_track', 'trash' );

// A canceled call: canceling trashes the call (wp_trash_post()), and `any` leaves the trash out,
// so a call somebody canceled outlived the plugin (the final fix wave, item 5).
$canceled_call = a_post( 'wpcpm_mentor_call', 'trash' );

// A published track and its two revisions, each holding a definition.
$growth = a_post( 'wpcpm_track', 'publish', array( '_wpcpm_track_definition' => '{"key":"growth","status":"Growth Track"}' ) );
a_post( 'revision', 'inherit', array( '_wpcpm_track_definition' => '{"key":"growth"}' ), $growth );
a_post( 'revision', 'inherit', array( '_wpcpm_track_definition' => '{"key":"growth"}' ), $growth );

// A page of the site's own, with an access level on it and a row of core's; and the rows the
// notices and the calls left behind for posts somebody deleted by hand.
$page = a_post( 'page', 'publish', array( '_wpcpm_access_level' => 'students', '_edit_last' => '1' ) );
$GLOBALS['pmeta'][ 99999 ] = array( '_wpcpm_notice_audience' => 'students', '_wpcpm_call_reminded' => '1' );

// The roles: Administrator with the program's capabilities beside its own, the four program roles.
$program_caps = array( 'wpcpm_view_student_content', 'wpcpm_view_mentor_content', 'wpcpm_view_institution_content', 'wpcpm_view_sponsor_content', 'wpcpm_manage_program' );

$GLOBALS['roles']['administrator'] = new WP_Role( 'administrator', array_fill_keys( array_merge( array( 'manage_options', 'edit_posts' ), $program_caps ), true ) );
$GLOBALS['roles']['editor']        = new WP_Role( 'editor', array( 'edit_posts' => true ) );
$GLOBALS['roles']['subscriber']    = new WP_Role( 'subscriber', array( 'read' => true ) );

foreach ( array( 'wpcpm_student', 'wpcpm_mentor', 'wpcpm_institution', 'wpcpm_sponsor' ) as $i => $role ) {
	$GLOBALS['roles'][ $role ] = new WP_Role( $role, array( 'read' => true, $program_caps[ $i ] => true ) );
}

$admin   = a_user( array( 'administrator' ) );
$student = a_user(
	array( 'wpcpm_student' ),
	array(),
	array(
		'wpcpm_student_institution' => 'recSEED0000000001', // WPCPM_Students_Sync::META_INSTITUTION.
		'wpcpm_flash'               => array( 'Saved.' ),   // WPCPM_Flash::META.
		'wpcpm_student_modules'     => array( 'hours' ),
		'_wpcpm_report_images'      => array( 12 ),         // WPCPM_Student_Report_Form::META_IMAGES.
		'nickname'                  => 'student-one',
	)
);
$mentor  = a_user( array( 'editor', 'wpcpm_mentor' ) );
$sponsor = a_user( array( 'wpcpm_sponsor' ), array( 'edit_posts', 'delete_posts', 'upload_files' ), array( 'wpcpm_sponsor_active' => 1 ) );

// The cron schedule: the ten hooks the file clears itself, every hook the modules and the tools
// clear, and one of core's.
$file_hooks  = array( 'wpcpm_mentors_daily', 'wpcpm_mentors_sync_tick', 'wpcpm_students_daily', 'wpcpm_students_sync_tick', 'wpcpm_send_call_reminders', 'wpcpm_drain_invite_queue', 'wpcpm_institutions_sync_daily', 'wpcpm_institutions_sync_tick', 'wpcpm_handbook_sync_daily', 'wpcpm_handbook_sync_tick' );
$other_hooks = array( 'wpcpm_ceiling_sweep', 'wpcpm_purge_applications', 'wpcpm_agreement_discard', 'wpcpm_agreement_reminders', 'wpcpm_invite_expire', 'wpcpm_report_ask_queue', 'wpcpm_report_autodraft', 'wpcpm_sponsor_agreement_discard', 'wpcpm_purge_sponsor_applications', 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick', 'wpcpm_checker_weekly_check', 'wpcpm_duplicates_scan', 'wpcpm_duplicates_tick', 'wpcpm_duplicates_purge' );

foreach ( array_merge( $file_hooks, $other_hooks, array( 'wp_version_check' ) ) as $hook ) {
	$GLOBALS['cron'][] = array(
		'hook' => $hook,
		'args' => array(),
	);
}

/** The hooks still scheduled. */
function scheduled() {
	return array_values( array_map( function ( $event ) { return $event['hook']; }, $GLOBALS['cron'] ) );
}

/**
 * The posts still there of the given types, each as its ID, type and status.
 *
 * @param string[] $types The types.
 * @return string[]
 */
function left_of( $types ) {
	$left = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( in_array( $post->post_type, $types, true ) ) {
			$left[] = sprintf( '%d %s %s', $post->ID, $post->post_type, $post->post_status );
		}
	}

	return $left;
}

echo "=== It runs to its end, from inside a function, as WordPress includes it ===\n";

$raised = array();

set_error_handler(
	function ( $level, $message, $where, $line ) use ( &$raised ) {
		$names    = array( E_WARNING => 'Warning', E_NOTICE => 'Notice', E_DEPRECATED => 'Deprecated', E_USER_WARNING => 'Warning', E_USER_NOTICE => 'Notice', E_USER_DEPRECATED => 'Deprecated' );
		$raised[] = sprintf( '%s: %s (%s:%d)', isset( $names[ $level ] ) ? $names[ $level ] : 'Error ' . $level, $message, basename( $where ), $line );

		return true;
	}
);

$thrown = run_uninstall( 'wpcredits-program-manager/wpcredits-program-manager.php' );

restore_error_handler();

ck( 'uninstall.php runs to its end, included from inside a function as uninstall_plugin() includes it', $thrown, '' );
ck( 'and raises no warning or notice on the way', $raised, array() );
ck( 'every statement it sent the database, and everything it asked of a stand-in, is modeled here', $GLOBALS['unmodeled'], array() );

echo "\n=== The options ===\n";

ck( 'every option the file names goes, the Track Builder\'s included', array_values( array_intersect( $named_options, array_keys( $GLOBALS['opts'] ) ) ), array() );
ck( 'the sweep takes every institution\'s module order and every Track Builder form, one nothing else knew of included', array_values( array_intersect( $swept_options, array_keys( $GLOBALS['opts'] ) ) ), array() );

// The sweeps as the statements they sent: one a prefix, each escaped for LIKE, so that an
// underscore in a prefix is an underscore and not any character. The last two are the rows of the
// Learn copies (SURFACES-7).
$sweep = array(
	"SELECT option_name FROM wp_options WHERE option_name LIKE 'wpcpm\\_institution\\_modules\\_%'",
	"SELECT option_name FROM wp_options WHERE option_name LIKE 'wpcpm\\_track\\_fields\\_%'",
	"SELECT option_name FROM wp_options WHERE option_name LIKE '\\_transient\\_wpcpm\\_learn\\_%'",
	"SELECT option_name FROM wp_options WHERE option_name LIKE '\\_transient\\_timeout\\_wpcpm\\_learn\\_%'",
);

ck( 'it asks the options table once a prefix, through the site\'s own database object', array_values( array_intersect( $wpdb->queries, $sweep ) ), $sweep );
ck( 'what is not the plugin\'s stays, and so does the key the kept agreements are encrypted with', array( get_option( 'blogname' ), get_option( 'admin_email' ), get_transient( 'feed_example' ), get_option( 'wpcpm_private_key' ) ), array( 'Example', 'admin@example.test', 'kept', 'kept' ) );
ck( 'no option of the plugin\'s is left but that key', array_values( preg_grep( '/^(_transient_(timeout_)?)?wpcpm_/', array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ), array( 'wpcpm_private_key' ) );

echo "\n=== The posts ===\n";

ck( 'a canceled call goes, though canceling put it in the trash', null === get_post( $canceled_call ), true );
ck( 'every post of the plugin\'s goes, a trashed track, a trashed report, a canceled call and a legacy Handbook page included', left_of( $plugin_types ), array() );
ck( 'with the revisions of each track', left_of( array( 'revision' ) ), array() );
ck( 'and a page of the site\'s own stays', null !== get_post( $page ), true );

echo "\n=== The roles ===\n";

ck( 'the four program roles are gone and the site\'s own stay', array_keys( $GLOBALS['roles'] ), array( 'administrator', 'editor', 'subscriber' ) );
ck( 'Administrator gives back the program\'s capabilities and keeps its own', array_keys( $GLOBALS['roles']['administrator']->capabilities ), array( 'manage_options', 'edit_posts' ) );
ck( 'an account that held a program role alone is a Subscriber now, and one that held another role besides keeps that one', array( $GLOBALS['users'][ $admin ]['roles'], $GLOBALS['users'][ $student ]['roles'], $GLOBALS['users'][ $mentor ]['roles'] ), array( array( 'administrator' ), array( 'subscriber' ), array( 'editor' ) ) );
ck( 'a sponsor account gives back the posting capabilities its membership granted', $GLOBALS['users'][ $sponsor ], array( 'roles' => array( 'subscriber' ), 'caps' => array() ) );

echo "\n=== The cron hooks ===\n";

ck( 'the ten hooks the file clears itself are cleared', array_values( array_intersect( $file_hooks, scheduled() ) ), array() );
ck( 'and so is every hook the modules and the tools clear', array_values( array_intersect( $other_hooks, scheduled() ) ), array() );
ck( 'a hook of core\'s is left alone', scheduled(), array( 'wp_version_check' ) );

echo "\n=== The meta ===\n";

ck( 'the user meta the file names goes, and an account\'s own stays', $GLOBALS['umeta'][ $student ], array( 'nickname' => 'student-one' ) );
ck( 'the access level goes from the site\'s own page, and core\'s row stays', $GLOBALS['pmeta'][ $page ], array( '_edit_last' => '1' ) );
ck( 'the audience and reminder rows of posts deleted by hand go too', $GLOBALS['pmeta'][ 99999 ], array() );

echo "\n=== The mail log and the queue ===\n";

ck( 'the mail log goes, with the addresses in it', get_option( 'wpcpm_mail_log', 'gone' ), 'gone' );
ck( 'and so does everybody still waiting for an invitation', get_option( 'wpcpm_invite_queue', 'gone' ), 'gone' );
ck( 'and the counts and times of the last bulk invite', get_option( 'wpcpm_invite_run', 'gone' ), 'gone' );

echo "\n=== What the Track Builder keeps outside its posts (SURFACES-7) ===\n";

ck( 'the publish lock a killed run left behind goes', get_option( 'wpcpm_track_publish_lock', 'gone' ), 'gone' );
ck( 'and so does the claim on the upgrade of the seeding a killed request left behind', get_option( 'wpcpm_tracks_upgrade_lock', 'gone' ), 'gone' );
ck( 'the copy of the base\'s schema goes, with its timeout', array_values( preg_grep( '/wpcpm_airtable_schema/', array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ), array() );
ck( 'every copy of a Learn course and of a course\'s structure goes, with its timeout', array_values( preg_grep( '/wpcpm_learn_/', array_map( 'strval', array_keys( $GLOBALS['opts'] ) ) ) ), array() );
ck( 'while a transient that is not the plugin\'s stays, with its timeout', array( get_transient( 'feed_example' ), isset( $GLOBALS['opts']['_transient_timeout_feed_example'] ) ), array( 'kept', true ) );

echo "\n=== What the file keeps on purpose ===\n";

ck( 'the inventory of the signed agreements it leaves is mailed to the site\'s admin address, naming the file', array( count( $GLOBALS['mail'] ), isset( $GLOBALS['mail'][0] ) ? $GLOBALS['mail'][0]['to'] : '', isset( $GLOBALS['mail'][0] ) && false !== strpos( $GLOBALS['mail'][0]['message'], 'agreement-one.pdf.enc' ) ), array( 1, 'admin@example.test', true ) );

echo "\n=== The names this suite seeds ===\n";

// Written out above so that no class is loaded before the file loads it; compared here with the
// classes' own, so a renamed constant fails this check instead of leaving a check that passes on
// a name nothing uses any more.
$names = array(
	'WPCPM_Settings::OPT_NAME'                      => 'wpcpm_settings',
	'WPCPM_Settings::OPT_VERSION'                   => 'wpcpm_settings_version',
	'WPCPM_Privacy_Guard::OPT_VERSION'              => 'wpcpm_privacy_version',
	'WPCPM_Airtable::BACKOFF_OPTION'                => 'wpcpm_airtable_backoff',
	'WPCPM_Countries::OPT_NAME'                     => 'wpcpm_countries',
	'WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED'      => 'wpcpm_sponsor_members_caps_repaired',
	'WPCPM_Roles::OPT_VERSION'                      => 'wpcpm_roles_version',
	'WPCPM_Notices::OPT_NAME'                       => 'wpcpm_notices',
	'WPCPM_Notices::OPT_MIGRATED'                   => 'wpcpm_notices_migrated',
	'WPCPM_Notices::OPT_PLAIN'                      => 'wpcpm_notices_plain',
	'WPCPM_Tracks::OPT_TRACKS'                      => 'wpcpm_tracks',
	'WPCPM_Tracks::OPT_FIELDS_PREFIX'               => 'wpcpm_track_fields_',
	'WPCPM_Track_Store::OPT_SKIPPED'                => 'wpcpm_tracks_skipped',
	'WPCPM_Track_Store::OPT_SEEDED'                 => 'wpcpm_tracks_seeded',
	'WPCPM_Track_Store::OPT_UPGRADE_LOCK'           => 'wpcpm_tracks_upgrade_lock',
	'WPCPM_Track_Store::META_DEFINITION'            => '_wpcpm_track_definition',
	'WPCPM_Institutions_Dashboard::OPT_MODULES_PREFIX' => 'wpcpm_institution_modules_',
	'WPCPM_Mail::LOG_OPTION'                        => 'wpcpm_mail_log',
	'WPCPM_Mail::QUEUE_OPTION'                      => 'wpcpm_invite_queue',
	'WPCPM_Mail::RUN_OPTION'                        => 'wpcpm_invite_run',
	'WPCPM_Private_Files::OPT_KEY'                  => 'wpcpm_private_key',
	'WPCPM_Track_Publish::OPT_LOCK'                 => 'wpcpm_track_publish_lock',
	'WPCPM_Airtable::SCHEMA_TRANSIENT'              => 'wpcpm_airtable_schema',
	'WPCPM_Notices::POST_TYPE'                      => 'wpcpm_notice',
	'WPCPM_Duplicate_Vault::POST_TYPE'              => 'wpcpm_dup_copy',
	'WPCPM_Track_Store::POST_TYPE'                  => 'wpcpm_track',
	'WPCPM_Semester_Report::POST_TYPE'              => 'wpcpm_inst_report',
	'WPCPM_Institution_Request::POST_TYPE'          => 'wpcpm_inst_request',
	'WPCPM_Institution_Application::POST_TYPE'      => 'wpcpm_inst_app',
	'WPCPM_Institution_Agreement::POST_TYPE'        => 'wpcpm_agreement',
	'WPCPM_Sponsor_Application::POST_TYPE'          => 'wpcpm_sponsor_app',
	'WPCPM_Institution_Audit::POST_TYPE'            => 'wpcpm_audit_entry',
	'WPCPM_Institution_Import::POST_TYPE'           => 'wpcpm_import_batch',
	'WPCPM_Sponsor_Offers::POST_TYPE'               => 'wpcpm_offer',
	'WPCPM_Mentor_Calls::POST_TYPE'                 => 'wpcpm_mentor_call',
	'WPCPM_Mentor_Notes::POST_TYPE'                 => 'wpcpm_mentor_note',
	'WPCPM_Institution_Invite::POST_TYPE'           => 'wpcpm_inst_invite',
	'WPCPM_Sponsor_Agreement::POST_TYPE'            => 'wpcpm_sponsor_agr',
	'WPCPM_Sponsor_Offers::META_SPONSOR'            => '_wpcpm_offer_sponsor',
	'WPCPM_Institution_Agreement::META_FILE'        => '_wpcpm_agr_file',
	'WPCPM_Institution_Agreement::META_INSTITUTION' => '_wpcpm_agr_institution',
	'WPCPM_Content_Access::META_KEY'                => '_wpcpm_access_level',
	'WPCPM_Notices::META_AUDIENCE'                  => '_wpcpm_notice_audience',
	'WPCPM_Mentor_Calls::META_REMINDED'             => '_wpcpm_call_reminded',
	'WPCPM_Students_Sync::META_INSTITUTION'         => 'wpcpm_student_institution',
	'WPCPM_Flash::META'                             => 'wpcpm_flash',
	'WPCPM_Student_Report_Form::META_IMAGES'        => '_wpcpm_report_images',
	'WPCPM_Sponsor_Members::META_ACTIVE'            => 'wpcpm_sponsor_active',
	'WPCPM_Roles::ROLE_STUDENT'                     => 'wpcpm_student',
	'WPCPM_Roles::ROLE_MENTOR'                      => 'wpcpm_mentor',
	'WPCPM_Roles::ROLE_INSTITUTION'                 => 'wpcpm_institution',
	'WPCPM_Roles::ROLE_SPONSOR'                     => 'wpcpm_sponsor',
	'WPCPM_Roles::CAP_VIEW_STUDENT'                 => 'wpcpm_view_student_content',
	'WPCPM_Roles::CAP_VIEW_MENTOR'                  => 'wpcpm_view_mentor_content',
	'WPCPM_Roles::CAP_VIEW_INSTITUTION'             => 'wpcpm_view_institution_content',
	'WPCPM_Roles::CAP_VIEW_SPONSOR'                 => 'wpcpm_view_sponsor_content',
	'WPCPM_Roles::CAP_MANAGE'                       => 'wpcpm_manage_program',
	'WPCPM_Mentors_Sync::CRON_DAILY'                => 'wpcpm_mentors_daily',
	'WPCPM_Mentors_Sync::CRON_TICK'                 => 'wpcpm_mentors_sync_tick',
	'WPCPM_Students_Sync::CRON_AUTO'                => 'wpcpm_students_daily',
	'WPCPM_Students_Sync::CRON_TICK'                => 'wpcpm_students_sync_tick',
	'WPCPM_Mentor_Calls::CRON_REMINDERS'            => 'wpcpm_send_call_reminders',
	'WPCPM_Mail::CRON_QUEUE'                        => 'wpcpm_drain_invite_queue',
	'WPCPM_Institutions_Sync::CRON_DAILY'           => 'wpcpm_institutions_sync_daily',
	'WPCPM_Institutions_Sync::CRON_TICK'            => 'wpcpm_institutions_sync_tick',
	'WPCPM_Ceiling::CRON_SWEEP'                     => 'wpcpm_ceiling_sweep',
	'WPCPM_Institutions::CRON_PURGE'                => 'wpcpm_purge_applications',
	'WPCPM_Institution_Agreement::CRON_DISCARD'     => 'wpcpm_agreement_discard',
	'WPCPM_Institution_Agreement::CRON_REMINDERS'   => 'wpcpm_agreement_reminders',
	'WPCPM_Institution_Invite::CRON_EXPIRE'         => 'wpcpm_invite_expire',
	'WPCPM_Semester_Report_Screen::CRON_ASK'        => 'wpcpm_report_ask_queue',
	'WPCPM_Semester_Report_Screen::CRON_AUTODRAFT'  => 'wpcpm_report_autodraft',
	'WPCPM_Sponsor_Agreement::CRON_DISCARD'         => 'wpcpm_sponsor_agreement_discard',
	'WPCPM_Sponsor_Application::CRON_PURGE'         => 'wpcpm_purge_sponsor_applications',
	'WPCPM_Sponsors_Sync::CRON_DAILY'               => 'wpcpm_sponsors_daily',
	'WPCPM_Sponsors_Sync::CRON_TICK'                => 'wpcpm_sponsors_sync_tick',
	'WPCPM_Mentor_Checker_Runner::CRON_HOOK'        => 'wpcpm_checker_weekly_check',
	'WPCPM_Duplicates_Scan::CRON_SCAN'              => 'wpcpm_duplicates_scan',
	'WPCPM_Duplicates_Scan::CRON_TICK'              => 'wpcpm_duplicates_tick',
	'WPCPM_Duplicate_Vault::CRON_PURGE'             => 'wpcpm_duplicates_purge',
);

ck( 'the names seeded above are the ones the classes declare', array_map( 'declared', array_keys( $names ) ), array_values( $names ) );

// The Learn copies have no constant: the class builds each name where it reads and writes it.
$learn = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wpcpm-learn.php' );

ck( 'and the Learn class still names its copies with the prefix the sweep reads', array( false !== strpos( $learn, "'wpcpm_learn_course_' . \$slug" ), false !== strpos( $learn, "'wpcpm_learn_structure_' . \$course_id" ) ), array( true, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
