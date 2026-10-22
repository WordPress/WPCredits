<?php
/**
 * The sponsors index: what the site knows about each sponsor, one private post per company
 * since 1.111.0.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One private post per sponsor carries two meta values, the key and the whole row; one option
 * per sponsor still holds which Media Library attachment is its logo. `rows()` runs a single
 * `get_posts()` and holds the map in a request-level static, dropped by `forget()`, which every
 * writer calls after writing, so a request that reads a sponsor right after writing it never
 * sees the row it is about to replace. `patch()` never merges into that map: it reads the row
 * afresh, so it cannot write another request's save back over with what this one read first.
 *
 * A key is either the sponsor's old Airtable record ID, kept for ever so that nothing already
 * keyed by it - offers, codes, claims, posts, the logo option, member accounts, the agreement -
 * needs rekeying, or, for a sponsor the site itself creates, a minted `spo` key of the same
 * length. `is_key()` is the one check, and it replaces every `WPCPM_Mentors_Sync::is_record_id()`
 * call in the sponsor classes (design decision 3 of 23 September 2026); the Airtable-only check
 * stays where it belongs, in the syncs that still talk to Airtable.
 *
 * A sponsor's manager is a site user, resolved through `get_userdata()` and answered only while
 * that user holds the program's capability, so the store keeps no team of its own. `OPT_TEAM`
 * names the Team Members option the sponsors sync kept until it went in 1.111.0: only the
 * migration reads it, and the migration's clean-up and `delete_all()` remove it.
 */
final class WPCPM_Sponsors_Index {

	const POST_TYPE       = 'wpcpm_sponsor_co';
	const META_KEY        = '_wpcpm_sponsor_key';   // The key, one meta per post, queried by value.
	const META_ROW        = '_wpcpm_sponsor_row';   // The whole row, `empty_row()` shape.
	const OPT_NAME        = 'wpcpm_sponsors_index'; // The OLD option; read by the migration only.
	const OPT_TEAM        = 'wpcpm_team_members';   // Legacy; see the class docblock.
	const OPT_LOGO_PREFIX = 'wpcpm_sponsor_logo_';

	/**
	 * Set once the upgrade of 1.123.18 has marked the test sponsors the store already held
	 * (`maybe_mark_tests()`), so a flag an Administrator clears afterward stays cleared.
	 * Autoloaded: every request asks it, and it is one small row.
	 */
	const OPT_TESTS_MARKED = 'wpcpm_sponsor_tests_marked';

	/**
	 * Every post status WordPress has, named rather than left to `'any'`: core's own `'any'` is
	 * shorthand for every status but trash and auto-draft, and both `post_id_of()` and
	 * `delete_all()` need the two it leaves out.
	 */
	const POST_STATUSES = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' );

	/** The status that means a sponsor is in the program. */
	const STATUS_APPROVED = 'Approved';

	/** Every status a sponsor can hold, spelled as the base spelled them (design section 4). */
	const STATUSES = array( 'Approved', 'In review', 'Paused', 'Not Moving Forward', 'Rejected' );

	/**
	 * A migrated Airtable record ID, or a minted site key: the one shape sponsor code checks.
	 *
	 * `\z`, not `$`: `$` lets a trailing newline through in PCRE, and the check this replaced
	 * (`WPCPM_Mentors_Sync::is_record_id()`) trimmed its argument first, so nothing already
	 * relied on that leniency.
	 */
	const KEY_PATTERN = '/^(rec|spo)[A-Za-z0-9]{14}\z/';

	/**
	 * The request-level cache `rows()` builds and every writer drops with `forget()`.
	 *
	 * @var array<string, array>|null
	 */
	private static $rows_cache = null;

	/**
	 * Register the post type. Private everywhere: no public URL, no admin list, no REST route,
	 * and a capability type nobody is granted, because a sponsor's row is edited through the
	 * Sponsors screen's own forms, never through wp-admin's post editor.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Sponsor companies', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Sponsor company', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'capability_type'     => array( self::POST_TYPE, self::POST_TYPE . 's' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Whether a value is a key the store could hold: a migrated Airtable record ID or a minted
	 * site key. The one check every sponsor class uses instead of testing the Airtable shape.
	 *
	 * @param mixed $value Candidate key.
	 * @return bool
	 */
	public static function is_key( $value ) {
		return is_string( $value ) && 1 === preg_match( self::KEY_PATTERN, $value );
	}

	/**
	 * A key for a sponsor the site creates itself: `spo` and fourteen characters from the same
	 * alphabet Airtable's own record IDs use, so `is_key()` accepts either shape without knowing
	 * which one it is looking at.
	 *
	 * Checked against the store rather than generated and trusted, because a caller that inserts
	 * under a colliding key would silently replace another sponsor's row; five tries make that
	 * astronomically unlikely, and the sixth is not spent chasing a coincidence that will not
	 * repeat (the last generated key is still fine to insert under - a replace, never a
	 * duplicate).
	 *
	 * @return string
	 */
	public static function mint_key() {
		$key = '';

		for ( $try = 0; $try < 5; $try++ ) {
			$key = 'spo' . wp_generate_password( 14, false, false );

			if ( 0 === self::post_id_of( $key ) ) {
				break;
			}
		}

		return $key;
	}

	/**
	 * The row shape, every key, in the order the design's record table lists them. `shape()`
	 * writes each of them on every row, so an empty value means the site holds nothing for that
	 * field, never that the row was not built.
	 *
	 * @return array
	 */
	public static function empty_row() {
		return array(
			'record_id'          => '',
			'slug'               => '',
			'name'               => '',
			'website'            => '',
			'status'             => '',
			'option'             => '',
			'support'            => array(),
			'product_type'       => '',
			'anything'           => '',
			'contact_person'     => '',
			'contact_email'      => '',
			'manager'            => '',
			'mentors'            => array(),
			'consent'            => false,
			'created'            => '',
			'agreement'          => array(
				'status'       => '',
				'accepted_on'  => '',
				'has_document' => false,
			),
			'interests'          => '',
			'dashboard_account'  => false,
			'notes'              => '',
			'tier'               => '',
			'tier_until'         => '',
			'funded_line'        => '',
			'description'        => '',
			'tier_requested'     => '',
			'tier_mailed_for'    => '',
			// The term the daily run drafted the annual sponsor report for, as `tier_mailed_for` is
			// the term the thirty-day mail went out for: stamped by
			// `WPCPM_Sponsor_Report::maybe_draft()` once a report for that term exists, its own draft
			// stored or one a manager made first, so a term ends in one report however many runs see it.
			'report_drafted_for' => '',
			// The term a renewal on the Sponsors screen moved the day off before its report existed:
			// `tier`, `until` and `funded_line` (the line as the row held it when the save moved the
			// day), or nothing. Written by the Edit save, read before the row's own term by the daily
			// run, the due list and a manager's draft for that day, and cleared once a report for that
			// day exists (`WPCPM_Sponsor_Report::owed_after_save()`).
			'report_owed'        => array(),
			// The co-hosted events a program manager granted for one term beyond the one the tier
			// includes: `until`, the last day of the term they were granted for, and `extra`, how many,
			// or nothing. Written by the Sponsors screen's Tier box with the term's last day as the save
			// leaves it, and counted by `WPCPM_Sponsor_Event::extra_events()` for that term alone, so a
			// renewal that moves the day leaves them with the term they were granted for.
			'event_allowance'    => array(),
			// A test sponsor (1.123.18): left out of every public surface, the Partners page, the
			// strip, the handbook listing, the public feed and its counts, and of what students and
			// mentors are shown, its offers, posts, events and track partnerships; its own accounts
			// see their Sponsor Dashboard as any sponsor's do. Set on the Sponsors screen's Edit
			// form and beside Approve on an application, and by the move onto the site and the
			// upgrade for a name that starts with the word "TEST" (`is_test_name()`).
			'test'               => false,
		);
	}

	/**
	 * Whether a sponsor is a test sponsor, never listed publicly (1.123.18).
	 *
	 * @param string|array $sponsor A key, or a row as `rows()` reads it.
	 * @return bool False for a key the store does not hold.
	 */
	public static function is_test( $sponsor ) {
		$row = is_array( $sponsor ) ? $sponsor : self::row( (string) $sponsor );

		return is_array( $row ) && ! empty( $row['test'] );
	}

	/**
	 * The keys of every test sponsor the store holds, whatever its status.
	 *
	 * @return string[]
	 */
	public static function test_keys() {
		$keys = array();

		foreach ( self::rows() as $record => $row ) {
			if ( ! empty( $row['test'] ) ) {
				$keys[] = (string) $record;
			}
		}

		return $keys;
	}

	/**
	 * Whether a name marks a test sponsor by the program's own convention: trimmed, it starts with
	 * the word "TEST" in capitals, as the live site's "TEST - WordPress Education Dashboard (do not
	 * use)" does. Case counts, so "Test Co" and "Contest Labs" are real companies, and so does the
	 * word (1.123.23): "TEST" alone, or followed by a character that is neither a letter nor a digit
	 * in any script, such as a space, a hyphen, a colon or a bracket. "TESTAMENT", "TESTIFY" and
	 * "TEST2 Labs" are real companies too, which the first four letters alone marked as tests. A
	 * sponsor marked or cleared under that earlier rule keeps its flag: the rule decides a name when
	 * it is read, at the move onto the site, the upgrade and the box beside Approve, and nothing
	 * reads a flag again.
	 *
	 * A name that is not valid UTF-8 the Unicode-aware match cannot read at all, and it answers
	 * false for an error rather than for a name; such a name is read byte by byte instead, "TEST"
	 * first and then nothing or a byte that is no ASCII letter or digit, so it is no less a test
	 * than the four letters alone once made it.
	 *
	 * @param string $name A sponsor's or an applicant's name.
	 * @return bool
	 */
	public static function is_test_name( $name ) {
		$name    = trim( (string) $name );
		$matched = preg_match( '/^TEST(?![\p{L}\p{N}])/u', $name );

		if ( false !== $matched ) {
			return 1 === $matched;
		}

		return 0 === strncmp( $name, 'TEST', 4 ) && ( 4 === strlen( $name ) || 1 !== preg_match( '/^[A-Za-z0-9]$/', $name[4] ) );
	}

	/**
	 * The upgrade of 1.123.18, once per site, on `init` right after the move onto the site: every
	 * sponsor the store holds whose name starts with the word "TEST" (`is_test_name()`) becomes a
	 * test sponsor before anything public is drawn on the same request. Once it has run, a flag an
	 * Administrator clears stays cleared, and a site where it ran under the earlier rule is not
	 * marked again under this one.
	 */
	public static function maybe_mark_tests() {
		if ( get_option( self::OPT_TESTS_MARKED ) ) {
			return;
		}

		foreach ( self::rows() as $record => $row ) {
			if ( empty( $row['test'] ) && self::is_test_name( (string) $row['name'] ) ) {
				self::patch( (string) $record, array( 'test' => true ) );
			}
		}

		update_option( self::OPT_TESTS_MARKED, 1, true );
	}

	/**
	 * Every sponsor, keyed by key, in the order their posts were created.
	 *
	 * One `get_posts()` call, held for the rest of the request: a screen that reads the index
	 * ten times while drawing one page must not run ten queries for it, and a request that
	 * writes a sponsor and then reads it back must see the row it just wrote, which is what
	 * `forget()` is for.
	 *
	 * @return array[] Rows keyed by key, each in `empty_row()`'s shape.
	 */
	public static function rows() {
		if ( is_array( self::$rows_cache ) ) {
			return self::$rows_cache;
		}

		$posts = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'private',
				'numberposts'            => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		$rows = array();

		foreach ( $posts as $post ) {
			$key = get_post_meta( $post->ID, self::META_KEY, true );

			if ( ! self::is_key( $key ) ) {
				continue;
			}

			$row = get_post_meta( $post->ID, self::META_ROW, true );

			if ( ! is_array( $row ) ) {
				continue;
			}

			// Shaped as it is read, not only as it is written: a row an older release stored lacks
			// every key added since (1.111.0 wrote nineteen, 1.112.0 added the six tier keys, 1.115.0
			// the report stamp and the owed term, and the event allowance came after them) until
			// something patches it, so the reader, not every writer, is where a shape belongs (the
			// review of 1.112.0): the Edit form and the Your sponsorship card read those keys without
			// isset(), and the daily run reads the stamp and the owed term the same way.
			// Idempotent for a row already shaped, and a few dozen rows once a request, since the
			// cache holds the result.
			$rows[ $key ] = self::shape( $row );
		}

		self::$rows_cache = $rows;

		return $rows;
	}

	/**
	 * One row, or null for a malformed or unknown key.
	 *
	 * @param string $record A key.
	 * @return array|null
	 */
	public static function row( $record ) {
		$record = trim( (string) $record );

		if ( ! self::is_key( $record ) ) {
			return null;
		}

		$rows = self::rows();

		return isset( $rows[ $record ] ) ? $rows[ $record ] : null;
	}

	/**
	 * One row by its public slug, or null: the one sponsor post of that name, read on its own. The
	 * badge route is the one reader that has a slug and no key, and it answers the visitors of
	 * other sites, a request each, so it reads that one post, never every sponsor the store holds.
	 *
	 * The slug is a sponsor's post's name, which `insert()` and `patch()` copy into the row. Core
	 * stores a name in another script as its UTF-8 bytes, percent-encoded in lowercase, while a
	 * request spells the same slug in the capitals a browser encodes with, or as the raw bytes: the
	 * request's spelling is decoded once and asked for as core stores it, lowercase with each byte
	 * outside ASCII encoded, so all three find the post. The post found must then be the slug decoded
	 * once and no more: a slug encoded twice names no sponsor, rather than the one it would name
	 * decoded twice, and a spelling core's query folds into a stored name (a dot for a dash, a
	 * character it drops) names none either. A post that `rows()` would not read, with no key or no
	 * row, is no sponsor here.
	 *
	 * @param string $slug The sponsor's slug, as stored or as a request spells it.
	 * @return array|null
	 */
	public static function row_by_slug( $slug ) {
		$slug = rawurldecode( trim( (string) $slug ) );

		if ( '' === $slug ) {
			return null;
		}

		$stored = strtolower(
			(string) preg_replace_callback(
				'/[\x80-\xff]/',
				static function ( $byte ) {
					return '%' . bin2hex( $byte[0] );
				},
				$slug
			)
		);

		$posts = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'private',
				'name'                   => $stored,
				'numberposts'            => 1,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		$post = isset( $posts[0] ) ? $posts[0] : null;

		if ( ! $post instanceof WP_Post || rawurldecode( (string) $post->post_name ) !== $slug ) {
			return null;
		}

		$key = get_post_meta( $post->ID, self::META_KEY, true );
		$row = get_post_meta( $post->ID, self::META_ROW, true );

		return self::is_key( $key ) && is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Whether the store holds a sponsor.
	 *
	 * @param string $record A key.
	 * @return bool
	 */
	public static function has( $record ) {
		return null !== self::row( $record );
	}

	/**
	 * The sponsors in the program.
	 *
	 * @return array[] Rows keyed by key.
	 */
	public static function approved() {
		$out = array();

		foreach ( self::rows() as $record => $row ) {
			if ( self::STATUS_APPROVED === $row['status'] ) {
				$out[ $record ] = $row;
			}
		}

		return $out;
	}

	/**
	 * How many sponsors hold each status, in the order a status first appears.
	 *
	 * @return array<string, int>
	 */
	public static function status_counts() {
		$counts = array();

		foreach ( self::rows() as $row ) {
			$status = $row['status'];

			$counts[ $status ] = isset( $counts[ $status ] ) ? $counts[ $status ] + 1 : 1;
		}

		return $counts;
	}

	/**
	 * Put one row in the store: creates a post for a key nothing holds yet, or replaces the row
	 * of the post that already carries the key.
	 *
	 * The slug is set once, from the name, when the post is created, and is never moved by a
	 * later insert() over the same key: a company that changes its name must not break a link
	 * or a bookmark already handed out for its old one (design section 3, decision 3).
	 *
	 * @param array $row A row in `empty_row()`'s shape; `record_id` is required.
	 * @return bool Whether the row was written.
	 */
	public static function insert( array $row ) {
		$key = isset( $row['record_id'] ) ? trim( (string) $row['record_id'] ) : '';

		if ( ! self::is_key( $key ) ) {
			return false;
		}

		$row['record_id'] = $key;
		$row              = self::shape( $row );
		$id               = self::post_id_of( $key );

		if ( $id > 0 ) {
			$post        = get_post( $id );
			$row['slug'] = $post instanceof WP_Post ? $post->post_name : $row['slug'];

			// The key is the key, whatever WordPress's own status says: post_id_of() finds a key's
			// post in any status now, including one a WP-CLI command trashed
			// outside the plugin, and a write over that key brings its post back to private rather
			// than updating a row nothing but mint_key() and delete_all() can see.
			$updated = wp_update_post(
				wp_slash(
					array(
						'ID'          => $id,
						'post_title'  => $row['name'],
						'post_status' => 'private',
					)
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				return false;
			}
		} else {
			$row['slug'] = wp_unique_post_slug( sanitize_title( $row['name'] ), 0, 'private', self::POST_TYPE, 0 );

			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'   => self::POST_TYPE,
						'post_status' => 'private',
						'post_title'  => $row['name'],
						'post_name'   => $row['slug'],
						'post_author' => 0,
					)
				),
				true
			);

			if ( is_wp_error( $id ) ) {
				return false;
			}
		}

		update_post_meta( $id, self::META_KEY, $key );
		update_post_meta( $id, self::META_ROW, wp_slash( $row ) );

		self::forget();

		return true;
	}

	/**
	 * Change a few keys of one row in place, leaving every other field as the row holds it: the
	 * fields a dashboard card or the Sponsors screen's Edit form saved, the `dashboard_account`
	 * flag, an agreement decision.
	 *
	 * **The row as the table holds it, read at the write.** A row is one meta value, written whole,
	 * so the keys given are merged into the row read afresh here, this request's copy of the post
	 * dropped first, and never into the request cache `rows()` filled or the object cache behind it:
	 * another request's save since this one first read the sponsor, a manager's Pause, tier or term
	 * while a member saves the profile, or a renewal while the daily run stamps a report, would
	 * otherwise be written back over with the row as this request first read it. What is left is the
	 * few statements between that read and the write.
	 *
	 * @param string $record Key.
	 * @param array  $fields Keys of `empty_row()` and their new values; anything else is ignored.
	 * @return bool Whether the row existed.
	 */
	public static function patch( $record, array $fields ) {
		$record = trim( (string) $record );
		$id     = self::post_id_of( $record );

		if ( 0 === $id ) {
			return false;
		}

		wp_cache_delete( $id, 'posts' );
		wp_cache_delete( $id, 'post_meta' );

		$post = get_post( $id );

		// post_id_of() finds a key's post in any status now, so a stray
		// trash - a WP-CLI command outside the plugin, never this plugin's own screens - must
		// refuse a patch rather than write a row has() cannot see: has() reads rows(), which is
		// private posts only, so the two must agree on what "the sponsor exists" means.
		if ( ! $post instanceof WP_Post || 'private' !== $post->post_status ) {
			return false;
		}

		$stored = get_post_meta( $id, self::META_ROW, true );

		// A post with no row is no sponsor to rows() either, which skips it.
		if ( ! is_array( $stored ) ) {
			return false;
		}

		$row = self::shape( $stored );

		foreach ( array_intersect_key( $fields, self::empty_row() ) as $key => $value ) {
			$row[ $key ] = $value;
		}

		$row['record_id'] = $record;
		$row['slug']      = $post->post_name;
		$row              = self::shape( $row );

		update_post_meta( $id, self::META_KEY, $record );
		update_post_meta( $id, self::META_ROW, wp_slash( $row ) );

		// Only when the name actually moved: most patches never touch it, and
		// a post row's own last-modified stamp has no reason to move on a save that left it alone.
		// $post was already read above for the slug, so this costs no second read of the post.
		$current_title = $post instanceof WP_Post ? trim( (string) $post->post_title ) : null;

		if ( array_key_exists( 'name', $fields ) && trim( (string) $row['name'] ) !== $current_title ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'         => $id,
						'post_title' => $row['name'],
					)
				)
			);
		}

		self::forget();

		return true;
	}

	/**
	 * Insert every row of a batch: not a path production code calls (the migration writes a row
	 * at a time through `insert()`, so an incomplete run reports honestly), but the one bulk path
	 * a suite has to seed a store with several sponsors at once.
	 *
	 * @param array $rows Rows keyed by key; a row's own `record_id` is overwritten by its key.
	 * @param int   $read Unix time the batch was read. Ignored: no option records it any more,
	 *                    and the parameter stays only so a caller built for the old writer keeps
	 *                    compiling.
	 * @return int How many rows were written.
	 */
	public static function write( array $rows, $read ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- kept only so a caller built for the old writer keeps compiling; see the docblock.
		$written = 0;

		foreach ( $rows as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row['record_id'] = trim( (string) $key );

			if ( self::insert( $row ) ) {
				++$written;
			}
		}

		return $written;
	}

	/**
	 * The post ID behind a key, or 0.
	 *
	 * @param string $record Key.
	 * @return int
	 */
	public static function post_id_of( $record ) {
		$record = trim( (string) $record );

		if ( ! self::is_key( $record ) ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				// A key already minted for a sponsor now in the trash must still resolve to its
				// post - mint_key() checks this to avoid colliding with it.
				'post_status'      => self::POST_STATUSES,
				'meta_key'         => self::META_KEY,
				'meta_value'       => $record,
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);

		return empty( $found ) ? 0 : (int) $found[0];
	}

	/**
	 * The assigned program manager of a sponsor: a site user now, not a Team Members record, and
	 * only while that user still holds the program's capability.
	 *
	 * A stored id outlives the role that made it a contact: a manager who leaves the team and keeps
	 * the account, as a mentor say, would otherwise go on receiving what a contact is sent (a
	 * student's name and address in a claim problem, an event's call link) and be shown to the
	 * sponsor's members as their contact. Such a manager is nobody's contact here, so the mails fall
	 * back to the program's own addresses and the contact block prints nothing. The row keeps the
	 * id, and the program's own screens name it marked "(not an Administrator now)" until somebody
	 * chooses another (`manager_label()`).
	 *
	 * @param string $record Key.
	 * @return array|null `name`, `email`, `calendly`, or null when none is assigned, the user is
	 *                     gone or the user no longer holds the program's capability.
	 */
	public static function manager_of( $record ) {
		$row = self::row( $record );

		if ( ! is_array( $row ) ) {
			return null;
		}

		$manager = (string) $row['manager'];

		if ( '' === $manager || ! ctype_digit( $manager ) ) {
			return null;
		}

		$id   = (int) $manager;
		$user = get_userdata( $id );

		if ( false === $user || ! user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
			return null;
		}

		// Behind a guard, so the store still answers where the booking class is not loaded: several
		// suites load the store and the cards that read a manager without it, and a manager there
		// simply has no link.
		$calendly = method_exists( 'WPCPM_Manager_Booking', 'url_for' ) ? (string) WPCPM_Manager_Booking::url_for( $id ) : '';

		return array(
			'name'     => $user->display_name,
			'email'    => $user->user_email,
			'calendly' => $calendly,
		);
	}

	/**
	 * The assigned program manager as the program's own screens name them: the name while the
	 * account holds the program's capability; once it does not, the name marked "(not a program
	 * manager now)", or the stored id so marked when the account is gone; '' when nobody is
	 * assigned. The Edit form keeps such an id selectable until somebody chooses another, and the
	 * Sponsors index and the Administrator Dashboard's *Sponsor terms ending* card name it the same
	 * way, since a blank there, or "Nobody yet", would read as a sponsor that never had a manager
	 * and hide an assignment waiting to be replaced. Never for a sponsor's own pages: there such a
	 * manager is nobody (`manager_of()`).
	 *
	 * @param array $row A sponsor's row, read for `manager`.
	 * @return string
	 */
	public static function manager_label( array $row ) {
		$manager = isset( $row['manager'] ) && is_scalar( $row['manager'] ) ? trim( (string) $row['manager'] ) : '';

		if ( '' === $manager ) {
			return '';
		}

		$user = ctype_digit( $manager ) ? get_userdata( (int) $manager ) : false;

		if ( $user instanceof WP_User && user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
			return $user->display_name;
		}

		return sprintf(
			/* translators: %s: a person's name. */
			__( '%s (not an Administrator now)', 'wpcredits-program-manager' ),
			$user instanceof WP_User ? $user->display_name : $manager
		);
	}

	/**
	 * The option that records a sponsor's logo attachments.
	 *
	 * @param string $record Key.
	 * @return string
	 */
	public static function logo_option( $record ) {
		return self::OPT_LOGO_PREFIX . trim( (string) $record );
	}

	/**
	 * What the site holds for a sponsor's logo.
	 *
	 * `source` says who put it there: `airtable` (the old sync copied Airtable's attachment,
	 * before this release) or `site` (the sponsor uploaded it, Phase S4).
	 *
	 * @param string $record Key.
	 * @return array `colour` (attachment ID or 0), `white` (attachment ID or 0), `source` ('' when none), `airtable_id`.
	 */
	public static function logo_record( $record ) {
		$stored = get_option( self::logo_option( $record ) );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'colour'      => isset( $stored['colour'] ) ? (int) $stored['colour'] : 0,
			'white'       => isset( $stored['white'] ) ? (int) $stored['white'] : 0,
			'source'      => isset( $stored['source'] ) ? (string) $stored['source'] : '',
			'airtable_id' => isset( $stored['airtable_id'] ) ? (string) $stored['airtable_id'] : '',
		);
	}

	/**
	 * Record a sponsor's logo attachments.
	 *
	 * A writer like the others, so it calls `forget()` too: the logo is in
	 * the public feed's answer, and an upload or a removal must reach it at once rather than when
	 * the fifteen minutes run out.
	 *
	 * @param string $record Key.
	 * @param array  $logo   As `logo_record()` returns it.
	 */
	public static function write_logo_record( $record, array $logo ) {
		update_option( self::logo_option( $record ), array_merge( self::logo_record( $record ), $logo ), false );

		self::forget();
	}

	/**
	 * The logo to draw for a sponsor: the site's own attachment, never a URL borrowed from
	 * somewhere that can expire.
	 *
	 * @param string $record Key.
	 * @return array|null `id` and `url`, or null when the site holds no logo for this sponsor.
	 */
	public static function display_logo( $record ) {
		$logo = self::logo_record( $record );
		$id   = $logo['colour'] > 0 ? $logo['colour'] : $logo['white'];

		if ( $id < 1 ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $id, 'medium' );

		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		return array(
			'id'  => $id,
			'url' => $url,
		);
	}

	/**
	 * Delete every post of the type, the logo options, the old option, the held team and the
	 * migration's report. Uninstall only; the attachments stay in the Media Library.
	 *
	 * The logo options are swept by prefix through `$wpdb`, rather than only the ones the store
	 * currently holds a post for, so a sponsor removed between a write and an uninstall does not
	 * leave its logo option behind for ever.
	 */
	public static function delete_all() {
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				// 'any' skips trash and auto-draft, which would leave behind a post a WP-CLI
				// command trashed outside the plugin (nothing in the plugin's own screens trashes
				// one) or one core itself left as auto-draft, after an uninstall that promises
				// every sponsor post gone.
				'post_status'      => self::POST_STATUSES,
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}

		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPT_LOGO_PREFIX ) . '%'
			)
		);

		delete_option( self::OPT_NAME );
		delete_option( self::OPT_TEAM );

		// The migration's own report, whether or not the migration ever ran.
		delete_option( WPCPM_Sponsors_Migration::OPT_REPORT );

		self::forget();
	}

	/**
	 * Drop the request cache `rows()` built. Every writer calls this after writing; tests call
	 * it to start a new request's worth of reads.
	 *
	 * A sponsor's identity changing is also when the public feed's cached answer must change, so
	 * this is where that cache is dropped too (1.111.0). Guarded, like `manager_of()`'s own
	 * `WPCPM_Manager_Booking` check above: plenty of suites load this store without the feed.
	 */
	public static function forget() {
		self::$rows_cache = null;

		if ( class_exists( 'WPCPM_Sponsors_Feed' ) ) {
			delete_transient( WPCPM_Sponsors_Feed::TRANSIENT );
		}
	}

	/**
	 * Every key present, every scalar typed.
	 *
	 * @param array $row A row as a caller built it, or as `rows()` read it back from a post.
	 * @return array
	 */
	private static function shape( array $row ) {
		$out = self::empty_row();

		foreach ( $out as $key => $default ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}

			// A term is a tier and a day, or nothing: half of one owes no report. The funded line is
			// that term's own, and a term may have had none.
			if ( 'report_owed' === $key ) {
				$given  = is_array( $row['report_owed'] ) ? $row['report_owed'] : array();
				$tier   = isset( $given['tier'] ) && is_scalar( $given['tier'] ) ? trim( (string) $given['tier'] ) : '';
				$until  = isset( $given['until'] ) && is_scalar( $given['until'] ) ? trim( (string) $given['until'] ) : '';
				$funded = isset( $given['funded_line'] ) && is_scalar( $given['funded_line'] ) ? trim( (string) $given['funded_line'] ) : '';

				$out['report_owed'] = '' !== $tier && '' !== $until ? array(
					'tier'        => $tier,
					'until'       => $until,
					'funded_line' => $funded,
				) : array();
				continue;
			}

			// An allowance is a day and a count of one or more, or nothing: no extra event is no
			// allowance, and neither is a count no whole number spells. The day is kept as the save
			// wrote it, for the reader to match against a term's last day.
			if ( 'event_allowance' === $key ) {
				$given = is_array( $row['event_allowance'] ) ? $row['event_allowance'] : array();
				$until = isset( $given['until'] ) && is_scalar( $given['until'] ) ? trim( (string) $given['until'] ) : '';
				$extra = isset( $given['extra'] ) ? self::whole_count( $given['extra'] ) : 0;

				$out['event_allowance'] = '' !== $until && $extra > 0 ? array(
					'until' => $until,
					'extra' => $extra,
				) : array();
				continue;
			}

			if ( 'agreement' === $key ) {
				$given = is_array( $row['agreement'] ) ? $row['agreement'] : array();

				$out['agreement'] = array(
					'status'       => isset( $given['status'] ) ? trim( (string) $given['status'] ) : '',
					'accepted_on'  => isset( $given['accepted_on'] ) ? trim( (string) $given['accepted_on'] ) : '',
					'has_document' => ! empty( $given['has_document'] ),
				);
				continue;
			}

			if ( is_array( $default ) ) {
				$out[ $key ] = is_array( $row[ $key ] ) ? $row[ $key ] : array();
			} elseif ( is_bool( $default ) ) {
				$out[ $key ] = ! empty( $row[ $key ] );
			} elseif ( 'name' === $key ) {
				// Trimmed now: the site is the record, so nothing needs the base's bytes the way
				// the old index kept them for a byte-for-byte match against Airtable.
				$out[ $key ] = trim( (string) $row[ $key ] );
			} else {
				$out[ $key ] = (string) $row[ $key ];
			}
		}

		return $out;
	}

	/**
	 * A count as a row keeps it: a whole number, or the digits of one as a value stored as text
	 * would hold them, and 0 for anything else, a number below zero, a fraction and a flag included.
	 *
	 * @param mixed $value The stored count.
	 * @return int
	 */
	private static function whole_count( $value ) {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		$digits = is_string( $value ) ? trim( $value ) : '';

		return '' !== $digits && ctype_digit( $digits ) ? (int) $digits : 0;
	}
}
