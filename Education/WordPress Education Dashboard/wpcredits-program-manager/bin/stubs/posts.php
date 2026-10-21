<?php
/**
 * Enough of WordPress's post, post meta and slug functions for a suite that loads
 * WPCPM_Sponsors_Index (1.111.0) but was written for the option it used to read.
 *
 * The suites that load it are the ones `grep -l "require_once.*stubs/posts.php" bin/test-*.php`
 * lists: everything that requires the real class and exercises rows(), row(), has(), insert(),
 * patch() or write() now calls wp_insert_post(), get_posts(), wp_unique_post_slug() and post meta
 * functions the old, option-only class never touched, and most suites' own stand-ins were never
 * asked for them. bin/test-sponsors-store.php defines every other function here itself and loads
 * the file for the one it shares, the fold of a `name` query (`wpcpm_test_query_name()`). One file,
 * guarded function by function, beats near-identical copies drifting apart.
 *
 * Every function is declared only `if ( ! function_exists( ... ) )`, so a suite that already
 * stubs one of these (for its own posts - Applications, Agreement documents, Track posts) keeps
 * its own and this file fills only the gap. All of it reads and writes `$GLOBALS['posts']`
 * (objects keyed by ID) and `$GLOBALS['pmeta']` (`$GLOBALS['pmeta'][ $id ][ $key ] = $value`,
 * unwrapped, not core's multi-value array), the shape bin/test-track-store.php uses. A suite
 * whose own post store already exists but reads a different global (an incompatible shape
 * rather than an absent function) is reconciled in its own file instead, where it declares the
 * post functions in its own shape before loading this one, so this file's copies of them are
 * never declared.
 *
 * A suite that loads this and also declares its own `class WP_Post` must give that class a
 * `post_name` property: the store's insert() and patch() read and write it, and a suite that
 * never needed a slug before this release does not have one to lose.
 *
 * Loaded with `require_once __DIR__ . '/stubs/posts.php';` from a suite's header, after the
 * suite's own stand-ins and before the real classes are required.
 */

// A suite that never stored a post before this release has neither global yet.
$GLOBALS['posts'] = isset( $GLOBALS['posts'] ) ? $GLOBALS['posts'] : array();
$GLOBALS['pmeta']  = isset( $GLOBALS['pmeta'] ) ? $GLOBALS['pmeta'] : array();

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
	}
}

if ( ! function_exists( 'wp_unique_post_slug' ) ) {
	/**
	 * Append -2, -3 on a clash with another post of the same type, as core does.
	 *
	 * @param string $slug        Candidate slug.
	 * @param int    $post_id     The post being saved; excluded from its own clash search.
	 * @param string $post_status Unused here; core's own signature carries it.
	 * @param string $post_type   Only posts of this type can clash.
	 * @param int    $post_parent Unused here; core's own signature carries it.
	 * @return string
	 */
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
}

// Both identity, on purpose, and only together: none of the ten suites' own update_post_meta()
// unslashes what it is given (it stores the argument exactly), so a real wp_slash() here would
// add backslashes nothing ever takes back out - a value with a quote or a backslash in it would
// come back from row()/read() with the escaping still in it. Making wp_unslash() identity too,
// and not just wp_slash(), matters just as much: the two suites without their own
// update_post_meta() (test-sponsor-cards.php, test-sponsor-logo.php) fall through to this
// file's own below, which calls wp_unslash() on the way in - a real stripslashes() there would
// then corrupt a genuine backslash that wp_slash() never added in the first place. Checked by
// the apostrophe-and-backslash fixture in bin/test-sponsors-store.php, which has its own real
// slash/unslash pair and unslashes on the way in, so the round trip is correct there without this.
if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) {
		return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID = 0;
		public $post_type = 'post';
		public $post_status = 'draft';
		public $post_title = '';
		public $post_name = '';
		public $post_author = 0;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * A suite's own `WP_Post` (nine of the ten do) was declared before this file loads and
	 * before the store existed to need a slug, so its constructor is whatever that suite wrote
	 * for its own posts - some take an array, one takes `($id, $post_date)`, most take nothing.
	 * `newInstanceWithoutConstructor()` sidesteps every one of those signatures rather than
	 * guessing which a given suite expects.
	 *
	 * @param array $postarr Post fields.
	 * @param bool  $wp_error Unused; core's own signature carries it.
	 * @return int
	 */
	function wp_insert_post( $postarr, $wp_error = false ) {
		$postarr                  = wp_unslash( $postarr );
		$GLOBALS['posts_next_id'] = isset( $GLOBALS['posts_next_id'] ) ? $GLOBALS['posts_next_id'] : 100;
		$id                       = $GLOBALS['posts_next_id']++;
		$fields                   = array(
			'ID'          => $id,
			'post_type'   => isset( $postarr['post_type'] ) ? $postarr['post_type'] : 'post',
			'post_status' => isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'draft',
			'post_title'  => isset( $postarr['post_title'] ) ? $postarr['post_title'] : '',
			'post_name'   => isset( $postarr['post_name'] ) ? $postarr['post_name'] : '',
			'post_author' => isset( $postarr['post_author'] ) ? (int) $postarr['post_author'] : 0,
		);
		$post                     = ( new ReflectionClass( 'WP_Post' ) )->newInstanceWithoutConstructor();

		foreach ( $fields as $key => $value ) {
			$post->$key = $value;
		}

		$GLOBALS['posts'][ $id ] = $post;

		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr, $wp_error = false ) {
		$postarr = wp_unslash( $postarr );
		$id      = (int) ( isset( $postarr['ID'] ) ? $postarr['ID'] : 0 );

		if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
			return $wp_error ? new WP_Error( 'invalid_post' ) : 0;
		}

		foreach ( array( 'post_title', 'post_status', 'post_name' ) as $field ) {
			if ( isset( $postarr[ $field ] ) ) {
				$GLOBALS['posts'][ $id ]->$field = $postarr[ $field ];
			}
		}

		return $id;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		if ( is_object( $id ) ) {
			return $id;
		}

		return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $key, $value ) {
		$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );
		return true;
	}
}

// The store's patch() drops a post's cached copy before it reads the row afresh. This file keeps
// no object cache, every read already being the table's, so there is nothing to drop.
if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $id, $key ) {
		unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) {
		unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] );
		return true;
	}
}

if ( ! function_exists( 'wpcpm_test_query_name' ) ) {
	/**
	 * The name core's query asks for, as `sanitize_title_for_query()` folds an ASCII one:
	 * lowercase, a dot a dash, what a slug cannot hold dropped, and runs of dashes one. A name in
	 * another script reaches it already encoded, as the store asks for it, and its octets are kept.
	 *
	 * @param string $name The name asked for.
	 * @return string
	 */
	function wpcpm_test_query_name( $name ) {
		$name = str_replace( '.', '-', strtolower( (string) $name ) );
		$name = preg_replace( '/\s+/', '-', preg_replace( '/[^%a-z0-9 _-]/', '', $name ) );

		return trim( (string) preg_replace( '/-+/', '-', $name ), '-' );
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		// So a check can read what the last call asked for (a date_query bound among them), the
		// way bin/test-sponsor-posts.php's own get_posts() already records it for its own suite,
		// and every call a step made, which is how a check counts the queries it ran.
		$GLOBALS['last_get_posts']    = $args;
		$GLOBALS['get_posts_calls'][] = $args;

		$out = array();

		foreach ( (array) $GLOBALS['posts'] as $post ) {
			if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
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

			// A post's name, as core's WP_Query matches `name`: the `post_name` column against the
			// name asked for, folded as core folds it, so a spelling core folds into a stored name
			// finds that post here too.
			if ( isset( $args['name'] ) && (string) $post->post_name !== wpcpm_test_query_name( $args['name'] ) ) {
				continue;
			}

			if ( isset( $args['meta_key'] ) ) {
				// Through get_post_meta() itself, not a direct read of $GLOBALS['pmeta']: a
				// suite whose own get_post_meta() wraps a stored value (core's multi-value
				// shape) unwraps it there, and a raw read here would compare against the
				// wrapper instead of the value.
				$have = get_post_meta( $post->ID, $args['meta_key'], true );

				if ( '' === $have || (string) $have !== (string) ( isset( $args['meta_value'] ) ? $args['meta_value'] : '' ) ) {
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
}
