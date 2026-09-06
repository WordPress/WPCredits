<?php
/**
 * Sponsor posts: the posting flag, the account capabilities, the category terms, the editor
 * fence, the Posts card, publish and return, the byline (Sponsors module, Phase S3).
 *
 * WordPress's own editor writes the post; this class enforces the one rule the editor cannot:
 * a sponsor's post is pending, in the sponsor's category, at the students-and-mentors level,
 * with the sponsor's stamp, whatever the form said. A program manager publishes.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sponsor posts: the posting flag, the account capabilities, the category terms, the editor
 * fence, the Posts card, publish and return, the byline.
 */
class WPCPM_Sponsor_Posts {

	/** The card's anchor and flash key on the Sponsor Dashboard. */
	const CARD = 'posts';

	/** Per-sponsor flags: `array( 'posts' => bool )`, absent means posting is on. */
	const OPT_FLAGS_PREFIX = 'wpcpm_sponsor_flags_';

	/** Per-sponsor child term id under the Sponsors category. */
	const OPT_TERM_PREFIX = 'wpcpm_sponsor_term_';

	/** Set once the default flag has reached the accounts attached before this class existed. */
	const OPT_APPLIED = 'wpcpm_sponsor_posts_applied';

	/** The parent category's slug; found by slug each time, never stored. */
	const PARENT_SLUG = 'sponsors';

	/** A manager's note when a post goes back to its author; cleared on publish. */
	const META_RETURN_NOTE = '_wpcpm_sponsor_return_note';

	/** When it went back, Unix time. */
	const META_RETURNED = '_wpcpm_sponsor_returned';

	/** A manager switches posting for a sponsor, from wp-admin. Nonce `wpcpm_sponsor_flags_<record>`. */
	const ACTION_FLAGS = 'wpcpm_sponsor_flags';

	/** Sponsor Dashboard: a manager publishes a pending post. Nonce keyed to the post. */
	const ACTION_POST_PUBLISH = 'wpcpm_sponsor_post_publish';

	/** Sponsor Dashboard: a manager returns a pending post as a draft with a note. */
	const ACTION_POST_RETURN = 'wpcpm_sponsor_post_return';

	/** The mail log's context for the note to the author. */
	const MAIL_RETURNED = 'sponsor-post-returned';

	/** Audit kinds this class writes. */
	const LOG_POSTING   = 'posting_switched';
	const LOG_PUBLISHED = 'post_published';
	const LOG_RETURNED  = 'post_returned';

	/** The card lists this many posts at most; the Tools section this many guides per sponsor. */
	const CARD_LIMIT  = 50;
	const TOOLS_LIMIT = 5;

	/**
	 * Booted by WPCPM_Sponsors::boot(). The handlers, the fence and the byline are all registered
	 * for everyone; each callback below decides for itself whether the current user is fenced.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_FLAGS, array( __CLASS__, 'handle_flags' ) );
		add_action( 'admin_post_' . self::ACTION_POST_PUBLISH, array( __CLASS__, 'handle_publish' ) );
		add_action( 'admin_post_' . self::ACTION_POST_RETURN, array( __CLASS__, 'handle_return' ) );
		// Once per site, before anything reads the caps: the sponsors provisioned before 1.95.0.
		add_action( 'init', array( __CLASS__, 'maybe_apply_caps' ), 5 );
		// A role change on the Users screen discards the per-user caps; a live member gets them back.
		add_action( 'set_user_role', array( __CLASS__, 'heal_caps' ), 10, 1 );
		// The fence is hooked for everyone and decides per call: whether the current user is a
		// sponsor member without the program capability is read when the hook fires, after
		// authentication, so a REST request whose user core resolves late (application
		// passwords, or any determine_current_user callback that waits for REST_REQUEST) is
		// fenced the same as a wp-admin one. A manager is never fenced.
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'filter_insert_data' ), 10, 2 );
		add_action( 'save_post_post', array( __CLASS__, 'enforce' ), 20, 2 );
		add_action( 'rest_after_insert_post', array( __CLASS__, 'enforce_rest' ), 20, 1 );
		add_action( 'pre_get_posts', array( __CLASS__, 'scope_admin_list' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'scope_media' ) );
		add_filter( 'rest_attachment_query', array( __CLASS__, 'scope_media' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'scope_attachment_details' ), 10, 2 );
		add_filter( 'upload_mimes', array( __CLASS__, 'limit_mimes' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_access_box' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'trim_menu' ), 999 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'trim_bar' ), 999 );
		add_filter( 'the_author', array( __CLASS__, 'filter_author_name' ) );
		add_filter( 'get_the_author_display_name', array( __CLASS__, 'filter_author_name' ), 10, 2 );
		// After the access gate at 5: a refused reader gets the notice and no banner.
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 6 );
		// After the gate's own oembed_response_data filter at 10, which answers a refusal first.
		add_filter( 'oembed_response_data', array( __CLASS__, 'filter_oembed' ), 20, 2 );
	}

	/*
	 * The fence
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether the current user is fenced: a live sponsor member without the program capability.
	 * Cached per user id for the request; keyed by id because a REST request can change its user
	 * after the first call (WP_REST_Server::serve_request() re-determines it).
	 *
	 * @return bool
	 */
	private static function fenced() {
		static $cache = array();

		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			return false;
		}

		if ( ! isset( $cache[ $user_id ] ) ) {
			$cache[ $user_id ] = ! user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) && '' !== WPCPM_Sponsor_Members::sponsor_of( $user_id );
		}

		return $cache[ $user_id ];
	}

	/**
	 * A member's post is pending unless it is a draft or in the trash, is dated now, and is
	 * theirs. `wp_insert_post_data`, priority 10, two arguments.
	 *
	 * @param array $data    Slashed post data about to be written.
	 * @param array $postarr The raw array passed in.
	 * @return array
	 */
	public static function filter_insert_data( $data, $postarr ) {
		if ( ! self::fenced() || ! isset( $data['post_type'] ) || 'post' !== $data['post_type'] ) {
			return $data;
		}

		$status = isset( $data['post_status'] ) ? (string) $data['post_status'] : '';

		if ( ! in_array( $status, array( 'draft', 'auto-draft', 'trash' ), true ) ) {
			$data['post_status'] = 'pending';
		}

		$data['post_author'] = get_current_user_id();

		// No scheduling: a date in the future becomes now, so a post cannot go live by itself
		// after a manager approved a different text. Both dates are read against one clock
		// reading, and the local one is clamped too: a programmatic insert may give post_date
		// alone, with post_date_gmt left as the zero date.
		$now     = current_time( 'mysql' );
		$now_gmt = current_time( 'mysql', true );

		if ( ( isset( $data['post_date_gmt'] ) && (string) $data['post_date_gmt'] > $now_gmt )
			|| ( isset( $data['post_date'] ) && (string) $data['post_date'] > $now ) ) {
			$data['post_date']     = $now;
			$data['post_date_gmt'] = $now_gmt;
		}

		return $data;
	}

	/**
	 * `save_post_post`, priority 20: pin the categories, the level and the stamp.
	 *
	 * @param int     $post_id The post.
	 * @param WP_Post $post    The post.
	 */
	public static function enforce( $post_id, $post ) {
		if ( ! self::fenced() || ! $post instanceof WP_Post || get_current_user_id() !== (int) $post->post_author || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		self::pin( (int) $post_id, WPCPM_Sponsor_Members::sponsor_of() );
	}

	/**
	 * `rest_after_insert_post`: the same pin, after REST wrote the terms and the meta.
	 *
	 * @param WP_Post $post The post.
	 */
	public static function enforce_rest( $post ) {
		if ( ! self::fenced() || ! $post instanceof WP_Post || get_current_user_id() !== (int) $post->post_author ) {
			return;
		}

		self::pin( (int) $post->ID, WPCPM_Sponsor_Members::sponsor_of() );
	}

	/**
	 * The one rule the editor cannot enforce: exactly Sponsors and the company's category, the
	 * students-and-mentors level, the sponsor's stamp. Always, for a member's save: a member
	 * cannot edit a published post, so this never undoes a manager's widening.
	 *
	 * @param int    $post_id The post.
	 * @param string $record  The member's sponsor; '' does nothing.
	 */
	public static function pin( $post_id, $record ) {
		if ( $post_id < 1 || ! WPCPM_Mentors_Sync::is_record_id( (string) $record ) ) {
			return;
		}

		$terms = array_filter( array( self::parent_term_id(), self::ensure_terms( $record ) ) );

		if ( ! empty( $terms ) ) {
			wp_set_post_terms( $post_id, array_values( $terms ), 'category', false );
		}

		update_post_meta( $post_id, WPCPM_Content_Access::META_KEY, WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS );
		update_post_meta( $post_id, WPCPM_Sponsor_Policy::META_POST_SPONSOR, $record );
	}

	/**
	 * Every query in wp-admin for a fenced user is the member's own: the screen's main query
	 * (Posts, Media in list mode) and the ones admin-ajax runs on its own - find_posts lists
	 * every post and page in any status, the link dialog every published one - which the access
	 * gate leaves alone because they are admin queries.
	 *
	 * @param WP_Query $query The query.
	 */
	public static function scope_admin_list( $query ) {
		if ( ! self::fenced() || ! is_admin() || ! is_object( $query ) ) {
			return;
		}

		$types = $query->get( 'post_type' );
		$types = ( '' === $types || null === $types ) ? array( 'post' ) : (array) $types;

		if ( in_array( 'any', $types, true ) || array_intersect( $types, array( 'post', 'page', 'attachment' ) ) ) {
			$query->set( 'author', get_current_user_id() );
		}
	}

	/**
	 * The media modal and the REST media list show the member's own files.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function scope_media( $args ) {
		if ( ! self::fenced() ) {
			return $args;
		}

		$args           = is_array( $args ) ? $args : array();
		$args['author'] = get_current_user_id();

		return $args;
	}

	/**
	 * Admin-ajax's get-attachment answers any id to anyone with upload_files, naming the uploader
	 * and the post it was attached to. A fenced user gets details of their own files only.
	 *
	 * @param array|false $response The prepared attachment.
	 * @param WP_Post     $attachment The attachment.
	 * @return array|false
	 */
	public static function scope_attachment_details( $response, $attachment ) {
		if ( ! self::fenced() || ! $attachment instanceof WP_Post ) {
			return $response;
		}

		return get_current_user_id() === (int) $attachment->post_author ? $response : false;
	}

	/**
	 * Uploads are images: png, jpg, jpeg, webp, gif. Nothing that runs, nothing that embeds.
	 *
	 * @param array $mimes The site's allowed types.
	 * @return array
	 */
	public static function limit_mimes( $mimes ) {
		if ( ! self::fenced() ) {
			return $mimes;
		}

		$allowed = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		);

		return array_intersect_key( $allowed, is_array( $mimes ) ? $mimes : array() );
	}

	/** The level is a manager's decision: the box goes for a member. */
	public static function remove_access_box() {
		if ( ! self::fenced() ) {
			return;
		}

		remove_meta_box( 'wpcpm-access-level', 'post', 'side' );
	}

	/** Comments and Tools open with edit_posts; neither is a sponsor's. */
	public static function trim_menu() {
		if ( ! self::fenced() ) {
			return;
		}

		remove_menu_page( 'edit-comments.php' );
		remove_menu_page( 'tools.php' );
	}

	/**
	 * The toolbar's Comments node goes with the menu.
	 *
	 * @param WP_Admin_Bar $bar The toolbar.
	 */
	public static function trim_bar( $bar ) {
		if ( ! self::fenced() ) {
			return;
		}

		if ( is_object( $bar ) && method_exists( $bar, 'remove_node' ) ) {
			$bar->remove_node( 'comments' );
		}
	}

	/*
	 * The flag and the capabilities
	 * --------------------------------------------------------------------
	 */

	/**
	 * The three capabilities a posting member's account carries: write and delete its own drafts,
	 * upload images. Never the capability that lets an account make a post live on its own (a
	 * manager publishes), never one that reaches over another author's posts or over what has
	 * already gone out. Asserted byte for byte by bin/test-sponsor-posts.php.
	 *
	 * @return string[]
	 */
	public static function caps() {
		return array( 'edit_posts', 'delete_posts', 'upload_files' );
	}

	/**
	 * A sponsor's flags, with the default the design chose: posting is on until a manager
	 * switches it off (decision 6 wants sponsors writing).
	 *
	 * @param string $record Airtable record ID.
	 * @return array `array( 'posts' => bool )`.
	 */
	public static function flags( $record ) {
		$stored = get_option( self::OPT_FLAGS_PREFIX . trim( (string) $record ), array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'posts' => array_key_exists( 'posts', $stored ) ? (bool) $stored['posts'] : true,
		);
	}

	/**
	 * Whether the sponsor's accounts may write posts.
	 *
	 * @param string $record Airtable record ID.
	 * @return bool
	 */
	public static function posting_enabled( $record ) {
		return ! empty( self::flags( $record )['posts'] );
	}

	/**
	 * Switch posting for a sponsor and apply it to every live member at once.
	 *
	 * @param string $record   Airtable record ID.
	 * @param bool   $on       The new state.
	 * @param int    $actor_id Who switched it.
	 * @return bool True when the flag changed; false for a bad record or a switch to the state it was in.
	 */
	public static function set_posting( $record, $on, $actor_id ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return false;
		}

		$flags = self::flags( $record );

		// Nothing to write and nothing to log when the flag already reads that way: a double
		// press is not an event.
		if ( $flags['posts'] === (bool) $on ) {
			return false;
		}

		$flags['posts'] = (bool) $on;

		update_option( self::OPT_FLAGS_PREFIX . $record, $flags, false );

		foreach ( WPCPM_Sponsor_Members::members_of( $record ) as $member ) {
			self::apply_caps( $member->ID, $record );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_POSTING,
				'sponsor'  => $record,
				'subject'  => 'posts',
				'actor'    => (int) $actor_id,
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $on
					? __( 'Posting was switched on for this sponsor.', 'wpcredits-program-manager' )
					: __( 'Posting was switched off for this sponsor.', 'wpcredits-program-manager' ),
				'data'     => array( 'posts' => (bool) $on ),
			)
		);

		return true;
	}

	/**
	 * Give an account the sponsor's current flag: the three caps when posting is on, none
	 * otherwise. Called by attach() and by every switch.
	 *
	 * @param int    $user_id The account.
	 * @param string $record  Airtable record ID.
	 */
	public static function apply_caps( $user_id, $record ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( (string) $record ) || ! self::posting_enabled( $record ) ) {
			self::drop_caps( $user_id );

			return;
		}

		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		foreach ( self::caps() as $cap ) {
			$user->add_cap( $cap );
		}
	}

	/**
	 * Take the three caps off an account. Called by detach() and by a switch to off.
	 *
	 * @param int $user_id The account.
	 */
	public static function drop_caps( $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		foreach ( self::caps() as $cap ) {
			$user->remove_cap( $cap );
		}
	}

	/**
	 * A role change on the Users screen resets per-user caps: a live member gets the sponsor's
	 * flag back at once, so posting does not silently stop after an unrelated edit.
	 *
	 * @param int $user_id The account whose role changed.
	 */
	public static function heal_caps( $user_id ) {
		$record = WPCPM_Sponsor_Members::sponsor_of( (int) $user_id );

		if ( '' !== $record ) {
			self::apply_caps( (int) $user_id, $record );
		}
	}

	/**
	 * Whether an account writes posts here: a live member of a sponsor whose flag is on. A
	 * manager is not one (the flag is about members), and neither is a detached account.
	 *
	 * @param int|WP_User|null $user The account; null for the current user.
	 * @return bool
	 */
	public static function may_post( $user = null ) {
		$user   = WPCPM_Roles::resolve_user( $user );
		$record = WPCPM_Sponsor_Members::sponsor_of( $user );

		return '' !== $record && self::posting_enabled( $record );
	}

	/*
	 * The terms
	 * --------------------------------------------------------------------
	 */

	/**
	 * The parent category, created on first use: slug `sponsors`, name "Sponsors" (decision 6
	 * spells the structure out). The one place the plugin creates a top-level term.
	 *
	 * @return int Term id, or 0 when the taxonomy refused.
	 */
	public static function parent_term_id() {
		$term = get_term_by( 'slug', self::PARENT_SLUG, 'category' );

		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$made = wp_insert_term( __( 'Sponsors', 'wpcredits-program-manager' ), 'category', array( 'slug' => self::PARENT_SLUG ) );

		if ( is_wp_error( $made ) ) {
			$data = $made->get_error_data();

			return is_numeric( $data ) ? (int) $data : 0;
		}

		return (int) $made['term_id'];
	}

	/**
	 * The sponsor's child category, created on first use and recorded per record. The slug is
	 * the company name's, suffixed `-2`, `-3` and so on when another term holds it.
	 *
	 * @param string $record Airtable record ID.
	 * @return int Term id, or 0.
	 */
	public static function ensure_terms( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return 0;
		}

		$have = self::term_of( $record );

		if ( $have > 0 ) {
			return $have;
		}

		$parent = self::parent_term_id();

		if ( $parent < 1 ) {
			return 0;
		}

		$row  = WPCPM_Sponsors_Index::row( $record );
		$name = is_array( $row ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record;
		$base = sanitize_title( $name );
		$base = '' === $base ? sanitize_title( $record ) : $base;
		$slug = $base;
		$n    = 2;

		while ( term_exists( $slug, 'category' ) ) {
			$slug = $base . '-' . $n;
			++$n;

			if ( $n > 50 ) {
				return 0;
			}
		}

		$made = wp_insert_term(
			$name,
			'category',
			array(
				'slug'   => $slug,
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $made ) ) {
			return 0;
		}

		update_option( self::OPT_TERM_PREFIX . $record, (int) $made['term_id'], false );

		return (int) $made['term_id'];
	}

	/**
	 * The recorded child term, if it still exists.
	 *
	 * @param string $record Airtable record ID.
	 * @return int Term id, or 0.
	 */
	public static function term_of( $record ) {
		$id = (int) get_option( self::OPT_TERM_PREFIX . trim( (string) $record ), 0 );

		if ( $id < 1 ) {
			return 0;
		}

		$term = get_term( $id, 'category' );

		return ( $term && ! is_wp_error( $term ) ) ? $id : 0;
	}

	/**
	 * Rename the child terms of companies the sync just saw renamed. The slug is kept on
	 * purpose: links to the category keep working, and the name is what readers see.
	 *
	 * @param array $before The index rows held before the sync, keyed by record.
	 * @param array $after  The rows just read, keyed by record.
	 */
	public static function rename_terms( array $before, array $after ) {
		foreach ( $after as $record => $row ) {
			$new = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			$old = isset( $before[ $record ]['name'] ) ? trim( (string) $before[ $record ]['name'] ) : '';

			if ( '' === $new || $new === $old ) {
				continue;
			}

			$term_id = self::term_of( (string) $record );

			if ( $term_id > 0 ) {
				wp_update_term( $term_id, 'category', array( 'name' => $new ) );
			}
		}
	}

	/**
	 * Once per site: the accounts attached before this class existed get the default flag.
	 * attach() applies it from now on; without this pass a sponsor provisioned in Phase S1 could
	 * never write, although the flag reads on for it.
	 */
	public static function maybe_apply_caps() {
		if ( '1' === (string) get_option( self::OPT_APPLIED, '' ) ) {
			return;
		}

		foreach ( WPCPM_Sponsor_Members::live_accounts() as $user ) {
			if ( $user instanceof WP_User ) {
				self::apply_caps( $user->ID, WPCPM_Sponsor_Members::sponsor_of( $user ) );
			}
		}

		update_option( self::OPT_APPLIED, '1', true );
	}

	/**
	 * Uninstall, first half: the three caps come off every live account. They are the plugin's,
	 * not the person's, and an account should not keep editor rights the plugin is no longer
	 * there to fence. Nothing else about the account is touched.
	 */
	public static function uninstall_accounts() {
		foreach ( WPCPM_Sponsor_Members::live_accounts() as $user ) {
			if ( $user instanceof WP_User ) {
				self::drop_caps( $user->ID );
			}
		}
	}

	/**
	 * Uninstall: the flags and the term records. The terms themselves stay: a category with
	 * posts in it is site content.
	 */
	public static function delete_all() {
		global $wpdb;

		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			foreach ( array( self::OPT_FLAGS_PREFIX, self::OPT_TERM_PREFIX ) as $prefix ) {
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( $prefix ) . '%'
					)
				);
			}

			delete_option( self::OPT_APPLIED );

			return;
		}

		// The suites have no database: walk the options they can see.
		if ( isset( $GLOBALS['opts'] ) && is_array( $GLOBALS['opts'] ) ) {
			foreach ( array_keys( $GLOBALS['opts'] ) as $key ) {
				if ( 0 === strpos( $key, self::OPT_FLAGS_PREFIX ) || 0 === strpos( $key, self::OPT_TERM_PREFIX ) ) {
					delete_option( $key );
				}
			}

			delete_option( self::OPT_APPLIED );
		}
	}

	/*
	 * Reading the posts
	 * --------------------------------------------------------------------
	 */

	/**
	 * A sponsor's posts by its stamp, newest change first. The access gate is opted out by name
	 * with `QUERY_UNGATED`, since `pre_get_posts` fires before `suppress_filters` is read: a
	 * member's own pending post sits at a level the member cannot read, and the gate would
	 * otherwise hide the sponsor's own list from the sponsor. The card is gated by the sponsor
	 * policy instead, and `render_tools()` re-checks `can_view()` on every post it prints.
	 *
	 * @param string   $record   Airtable record ID.
	 * @param string[] $statuses Post statuses.
	 * @param int      $limit    How many at most.
	 * @return WP_Post[]
	 */
	public static function posts_of( $record, array $statuses = array( 'draft', 'pending', 'publish' ), $limit = self::CARD_LIMIT ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'                         => 'post',
				'post_status'                       => $statuses,
				'numberposts'                       => max( 1, (int) $limit ),
				'orderby'                           => 'modified',
				'order'                             => 'DESC',
				'meta_key'                          => WPCPM_Sponsor_Policy::META_POST_SPONSOR,
				'meta_value'                        => $record,
				// The access gate runs on pre_get_posts, which fires before suppress_filters is
				// read, so it is asked to stand aside by name: a member does not hold the level
				// their own pending post sits at, and this list is theirs by policy.
				WPCPM_Content_Access::QUERY_UNGATED => true,
				'suppress_filters'                  => false,
			)
		);

		return array_values(
			array_filter(
				(array) $found,
				static function ( $post ) {
					return $post instanceof WP_Post;
				}
			)
		);
	}

	/**
	 * A post's state in the sponsor's words: draft, returned (a draft with a note), pending,
	 * published.
	 *
	 * @param WP_Post $post The post.
	 * @return string One of `draft`, `returned`, `pending`, `published`.
	 */
	public static function state_of( WP_Post $post ) {
		if ( 'publish' === $post->post_status ) {
			return 'published';
		}

		if ( 'pending' === $post->post_status ) {
			return 'pending';
		}

		return '' !== (string) get_post_meta( $post->ID, self::META_RETURN_NOTE, true ) ? 'returned' : 'draft';
	}

	/**
	 * A state's label.
	 *
	 * @param string $state From state_of().
	 * @return string
	 */
	public static function state_label( $state ) {
		$labels = array(
			'draft'     => __( 'Draft', 'wpcredits-program-manager' ),
			'returned'  => __( 'Returned with a note', 'wpcredits-program-manager' ),
			'pending'   => __( 'Waiting for review', 'wpcredits-program-manager' ),
			'published' => __( 'Published', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
	}

	/*
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Posts card on the Sponsor Dashboard: every post with its state, edit links for the
	 * author, Write a post when the flag is on, Publish and Return for a manager on a pending
	 * post. The same section-around-a-disclosure shape as every card on the page.
	 *
	 * @param string $record  Airtable record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$record     = trim( (string) $record );
		$can_manage = ! empty( $context['can_manage'] );
		$viewer     = isset( $context['viewer'] ) && $context['viewer'] instanceof WP_User ? $context['viewer'] : wp_get_current_user();
		$is_member  = WPCPM_Sponsor_Members::is_member( $viewer, $record );
		$posts      = self::posts_of( $record );
		$open       = isset( $context['open'] ) && self::CARD === $context['open'];

		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD ), $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Posts', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $posts ) ) )
		);
		echo '<div class="wpcpm-group__body">';

		if ( $is_member && ! $can_manage ) {
			echo '<p class="wpcpm-student__note">' . esc_html__( 'Guides and stories for students and mentors, written in the site\'s editor. A program manager publishes what you submit; it then appears under your offer on the Student Report Card and the Mentor Report Card, with your company as its author.', 'wpcredits-program-manager' ) . '</p>';

			if ( self::posting_enabled( $record ) ) {
				// Write a post opens the editor; the second link is the Posts screen in wp-admin,
				// where the member's own posts are listed and Add New sits (the fence scopes that
				// list to theirs). The owner asked for the way into wp-admin to be on the card.
				printf(
					'<p class="wpcpm-posts__actions"><a class="wpcpm-button" href="%1$s">%2$s</a> <a class="wpcpm-posts__admin" href="%3$s">%4$s</a></p>',
					esc_url( admin_url( 'post-new.php' ) ),
					esc_html__( 'Write a post', 'wpcredits-program-manager' ),
					esc_url( admin_url( 'edit.php' ) ),
					esc_html__( 'Your posts in wp-admin', 'wpcredits-program-manager' )
				);
			} else {
				echo '<p class="wpcpm-student__note">' . esc_html__( 'The program has not enabled posting for this sponsor.', 'wpcredits-program-manager' ) . '</p>';
			}
		} else {
			echo '<p class="wpcpm-student__note">' . esc_html__( 'Guides and stories the sponsor writes in the site\'s editor. A post waiting for review is published or returned with a note from here; once published it appears under the sponsor\'s offer on the Student Report Card and the Mentor Report Card, with the company as its author.', 'wpcredits-program-manager' ) . '</p>';

			if ( $can_manage && ! self::posting_enabled( $record ) ) {
				echo '<p class="wpcpm-student__note">' . esc_html__( 'Posting is off for this sponsor. Switch it on the Sponsors screen in wp-admin.', 'wpcredits-program-manager' ) . '</p>';
			}

			if ( $can_manage ) {
				// The Posts screen in wp-admin, filtered to this sponsor's category when it exists.
				$term = self::term_of( $record );

				printf(
					'<p class="wpcpm-posts__actions"><a class="wpcpm-posts__admin" href="%1$s">%2$s</a></p>',
					esc_url( $term > 0 ? add_query_arg( 'cat', $term, admin_url( 'edit.php' ) ) : admin_url( 'edit.php' ) ),
					esc_html__( 'This sponsor\'s posts in wp-admin', 'wpcredits-program-manager' )
				);
			}
		}

		if ( empty( $posts ) ) {
			echo '<p class="wpcpm-student__note">' . esc_html__( 'No posts yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<ul class="wpcpm-posts">';

			foreach ( $posts as $post ) {
				self::render_item( $post, $can_manage );
			}

			echo '</ul>';
		}

		echo '</div></details></section>';
	}

	/**
	 * One post on the card.
	 *
	 * @param WP_Post $post       The post.
	 * @param bool    $can_manage Whether the viewer is a program manager.
	 */
	private static function render_item( WP_Post $post, $can_manage ) {
		$state = self::state_of( $post );
		$title = '' !== trim( (string) $post->post_title ) ? $post->post_title : __( '(no title)', 'wpcredits-program-manager' );

		printf( '<li class="wpcpm-posts__item wpcpm-posts__item--%1$s" id="wpcpm-post-%2$d">', esc_attr( $state ), (int) $post->ID );

		// The title, its state and its date on one line: one flex row the theme spaces, rather
		// than inline text with a space between each part.
		echo '<div class="wpcpm-posts__head">';

		if ( 'published' === $state ) {
			printf( '<a class="wpcpm-posts__title" href="%1$s">%2$s</a>', esc_url( get_permalink( $post ) ), esc_html( $title ) );
		} elseif ( current_user_can( 'edit_post', $post->ID ) ) {
			printf( '<a class="wpcpm-posts__title" href="%1$s">%2$s</a>', esc_url( get_edit_post_link( $post->ID, 'raw' ) ), esc_html( $title ) );
		} else {
			printf( '<span class="wpcpm-posts__title">%s</span>', esc_html( $title ) );
		}

		printf( '<span class="wpcpm-posts__state">%s</span>', esc_html( self::state_label( $state ) ) );
		printf( '<span class="wpcpm-posts__when">%s</span>', esc_html( get_the_modified_date( 'Y-m-d', $post ) ) );
		echo '</div>';

		if ( 'returned' === $state ) {
			printf( '<p class="wpcpm-posts__note">%s</p>', esc_html( (string) get_post_meta( $post->ID, self::META_RETURN_NOTE, true ) ) );
		}

		if ( $can_manage && 'pending' === $state ) {
			self::render_decide( $post );
		}

		echo '</li>';
	}

	/**
	 * Publish and Return, for a manager on a pending post. Two forms: a link that changes state
	 * is followed by every prefetcher that meets it.
	 *
	 * @param WP_Post $post The post.
	 */
	private static function render_decide( WP_Post $post ) {
		echo '<div class="wpcpm-posts__decide">';

		printf( '<a class="wpcpm-posts__preview" href="%1$s">%2$s</a>', esc_url( get_preview_post_link( $post ) ), esc_html__( 'Preview', 'wpcredits-program-manager' ) );

		printf( '<form class="wpcpm-posts__form" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION_POST_PUBLISH . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_POST_PUBLISH ) );
		printf( '<input type="hidden" name="wpcpm_post" value="%d" />', (int) $post->ID );
		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html__( 'Publish', 'wpcredits-program-manager' ) );
		echo '</form>';

		// The note form stays folded until a manager chooses to return the post: three controls
		// on one row, and the box only when it is wanted (the owner found the open box and its
		// button loose on the page).
		echo '<details class="wpcpm-posts__return">';
		printf( '<summary class="wpcpm-posts__return-toggle">%s</summary>', esc_html__( 'Return with a note', 'wpcredits-program-manager' ) );
		printf( '<form class="wpcpm-posts__form wpcpm-posts__form--return" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION_POST_RETURN . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_POST_RETURN ) );
		printf( '<input type="hidden" name="wpcpm_post" value="%d" />', (int) $post->ID );
		printf(
			'<label class="wpcpm-posts__label" for="wpcpm-post-note-%1$d">%2$s</label><textarea id="wpcpm-post-note-%1$d" name="wpcpm_note" rows="3" required></textarea>',
			(int) $post->ID,
			esc_html__( 'What should change before it is published', 'wpcredits-program-manager' )
		);
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Send back to the author', 'wpcredits-program-manager' ) );
		echo '</form>';
		echo '</details>';

		// The one thing about publishing a manager cannot see from the button: the level it keeps.
		echo '<p class="wpcpm-posts__hint">' . esc_html__( 'Publishing keeps the Students and mentors level. To open the post to everyone, widen its level in the editor after publishing.', 'wpcredits-program-manager' ) . '</p>';

		echo '</div>';
	}

	/**
	 * A sponsor's published guides under its offer in the Tools section, for a viewer the level
	 * admits. Nothing is printed when there is none.
	 *
	 * @param string  $record Airtable record ID.
	 * @param WP_User $viewer Who is reading.
	 * @param string  $name   The company name the offer already printed.
	 */
	public static function render_tools( $record, WP_User $viewer, $name ) {
		$posts = array();

		foreach ( self::posts_of( $record, array( 'publish' ), self::TOOLS_LIMIT * 2 ) as $post ) {
			if ( WPCPM_Content_Access::can_view( $post, $viewer ) ) {
				$posts[] = $post;
			}

			if ( count( $posts ) >= self::TOOLS_LIMIT ) {
				break;
			}
		}

		if ( empty( $posts ) ) {
			return;
		}

		echo '<div class="wpcpm-tools__posts">';
		/* translators: %s: the company name. */
		printf( '<p class="wpcpm-tools__posts-lead">%s</p>', esc_html( sprintf( __( 'Guides from %s', 'wpcredits-program-manager' ), $name ) ) );
		echo '<ul class="wpcpm-tools__posts-list">';

		foreach ( $posts as $post ) {
			printf(
				'<li><a href="%1$s">%2$s</a> <span class="wpcpm-tools__posts-when">%3$s</span></li>',
				esc_url( get_permalink( $post ) ),
				esc_html( $post->post_title ),
				esc_html( get_the_date( 'Y-m-d', $post ) )
			);
		}

		echo '</ul></div>';
	}

	/**
	 * The card's outcomes, merged by WPCPM_Sponsors_Dashboard::messages().
	 *
	 * @return array
	 */
	public static function messages() {
		return array(
			'post-published'    => array( 'success', __( 'The post is published. It appears under the sponsor\'s offer on the Student Report Card and the Mentor Report Card.', 'wpcredits-program-manager' ) ),
			'post-returned'     => array( 'success', __( 'The post went back to its author as a draft, with your note.', 'wpcredits-program-manager' ) ),
			'post-note-missing' => array( 'error', __( 'Write a note for the author before returning the post.', 'wpcredits-program-manager' ) ),
			'post-not-pending'  => array( 'error', __( 'Only a post waiting for review can be published or returned here.', 'wpcredits-program-manager' ) ),
			'post-failed'       => array( 'error', __( 'The post could not be changed right now. Try again later.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Back to the page at this card, through the dashboard's one door. Called by array callable
	 * so bin/check-references.php meets the door where it lives.
	 *
	 * @param string $status Flash key.
	 * @param string $record The sponsor, for a manager's switcher.
	 * @param string $detail Optional detail.
	 */
	private static function leave( $status, $record = '', $detail = '' ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record, $detail );
	}

	/**
	 * The shared opening of publish and return: the nonce first, then the post, its stamp, the
	 * claim (metered) on the stamp's sponsor, and the decision on the post itself.
	 *
	 * @param string $action `ACTION_POST_PUBLISH` or `ACTION_POST_RETURN`.
	 * @return array `post`, `record`, `claim`; leaves on any refusal.
	 */
	private static function begin( $action ) {
		if ( ! is_user_logged_in() ) {
			self::leave( 'refused' );
		}

		// The nonce before any lookup, as every handler in the module does: a request without
		// one learns nothing about which post ids are sponsor posts.
		$post_id = WPCPM_Request::posted_id( 'wpcpm_post' );

		check_admin_referer( $action . '_' . $post_id );

		$post   = $post_id > 0 ? get_post( $post_id ) : null;
		$record = $post instanceof WP_Post ? trim( (string) get_post_meta( $post->ID, WPCPM_Sponsor_Policy::META_POST_SPONSOR, true ) ) : '';

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			self::leave( 'refused' );
		}

		$claim = WPCPM_Sponsor_Roster::claim( $record, WPCPM_Sponsor_Policy::ACT_PUBLISH_POST );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused' );
		}

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_PUBLISH_POST, WPCPM_Sponsor_Policy::subject_post( $post ) );

		if ( empty( $decision['allowed'] ) ) {
			self::leave( 'refused' );
		}

		if ( 'pending' !== $post->post_status ) {
			self::leave( 'post-not-pending', $record );
		}

		return array(
			'post'   => $post,
			'record' => $record,
			'claim'  => $claim,
		);
	}

	/**
	 * A manager publishes a pending post: the status and both dates carry the moment it went
	 * live, so the feeds and the archives file it there; the return note, if any, is cleared.
	 */
	public static function handle_publish() {
		$opened = self::begin( self::ACTION_POST_PUBLISH );
		$post   = $opened['post'];

		// Not wp_publish_post(): it flips the status and leaves a pending post's dates alone, so
		// the post would carry the zero GMT date and be filed under the member's last save
		// rather than the moment it went live.
		$published = wp_update_post(
			array(
				'ID'            => $post->ID,
				'post_status'   => 'publish',
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', true ),
				'edit_date'     => true,
			),
			true
		);

		if ( is_wp_error( $published ) || ! $published ) {
			self::leave( 'post-failed', $opened['record'] );
		}

		delete_post_meta( $post->ID, self::META_RETURN_NOTE );
		delete_post_meta( $post->ID, self::META_RETURNED );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_PUBLISHED,
				'sponsor'  => $opened['record'],
				'subject'  => (string) $post->ID,
				'actor'    => get_current_user_id(),
				'ground'   => $opened['claim']['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'A sponsor post was published.', 'wpcredits-program-manager' ),
				'data'     => array( 'post' => (int) $post->ID ),
			)
		);

		self::leave( 'post-published', $opened['record'] );
	}

	/**
	 * A manager returns a pending post to its author as a draft, with a note that is kept on
	 * the post and mailed to the author.
	 */
	public static function handle_return() {
		$opened = self::begin( self::ACTION_POST_RETURN );
		$post   = $opened['post'];
		$note   = isset( $_POST['wpcpm_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wpcpm_note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$note   = trim( mb_substr( $note, 0, 2000 ) );

		if ( '' === $note ) {
			self::leave( 'post-note-missing', $opened['record'] );
		}

		$moved = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $moved ) || ! $moved ) {
			self::leave( 'post-failed', $opened['record'] );
		}

		update_post_meta( $post->ID, self::META_RETURN_NOTE, $note );
		update_post_meta( $post->ID, self::META_RETURNED, time() );

		$title = (string) $post->post_title;

		WPCPM_Mail::send(
			(int) $post->post_author,
			self::MAIL_RETURNED,
			static function ( WP_User $author ) use ( $title, $note ) {
				return array(
					/* translators: %s: the post title. */
					'subject' => sprintf( __( 'Your post "%s" needs a change before it is published', 'wpcredits-program-manager' ), $title ),
					'body'    => sprintf(
						/* translators: 1: the author's name, 2: the post title, 3: the manager's note, 4: the wp-admin posts list URL. */
						__( "Hello %1\$s,\n\nA program manager read your post \"%2\$s\" and sent it back as a draft with this note:\n\n%3\$s\n\nEdit it here and submit it for review again: %4\$s\n\nThe WordPress Credits program", 'wpcredits-program-manager' ),
						$author->display_name,
						$title,
						$note,
						admin_url( 'edit.php' )
					),
				);
			}
		);

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_RETURNED,
				'sponsor'  => $opened['record'],
				'subject'  => (string) $post->ID,
				'actor'    => get_current_user_id(),
				'ground'   => $opened['claim']['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'A sponsor post was returned to its author with a note.', 'wpcredits-program-manager' ),
				// The note's length, never the note: the audit is about the act.
				'data'     => array(
					'post'        => (int) $post->ID,
					'note_length' => mb_strlen( $note ),
				),
			)
		);

		self::leave( 'post-returned', $opened['record'] );
	}

	/**
	 * A manager switches posting for a sponsor, from wp-admin. The capability, the nonce keyed
	 * to the record, the policy, then the switch; back to the Sponsors screen with a flash on
	 * the module's own channel.
	 */
	public static function handle_flags() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Request::posted_text( 'wpcpm_sponsor' );

		check_admin_referer( self::ACTION_FLAGS . '_' . $record );

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_MANAGE_MEMBERS, WPCPM_Sponsor_Policy::subject_sponsor( $record ) );
		$on       = '1' === WPCPM_Request::posted_key( 'wpcpm_on' );
		$status   = 'refused';

		// set_posting() answers false for the state the flag is already in - a no-op, not a
		// refusal - so the flash follows the decision, never the write. The record is checked
		// here too, so a bad one never flashes success for a write that never happened: decide()
		// grants a manager every row of the map without ever asking the index, so a well-formed
		// record the index does not hold would otherwise leave a flag behind for no sponsor.
		if ( ! empty( $decision['allowed'] ) && WPCPM_Mentors_Sync::is_record_id( $record ) && WPCPM_Sponsors_Index::has( $record ) ) {
			self::set_posting( $record, $on, get_current_user_id() );
			$status = $on ? 'posting-on' : 'posting-off';
		}

		WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $status );
		wp_safe_redirect( WPCPM_Return::url( admin_url( 'admin.php?page=wpcpm-sponsors' ) ) );
		exit;
	}

	/*
	 * The byline
	 * --------------------------------------------------------------------
	 */

	/**
	 * The sponsor a post in the loop belongs to, or ''.
	 *
	 * @return string
	 */
	private static function record_in_loop() {
		$post = get_post();

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return '';
		}

		$record = trim( (string) get_post_meta( $post->ID, WPCPM_Sponsor_Policy::META_POST_SPONSOR, true ) );

		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}

	/**
	 * `the_author` and `get_the_author_display_name`: a sponsor post carries the company, not a
	 * person's name (spec 7.5). Only for the post's own author: the same filters fire for other
	 * people named on the page (a "posts by" widget), who keep their names. A front-end matter
	 * only: in wp-admin a manager reads who submitted the post.
	 *
	 * @param string $name    The display name WordPress would print.
	 * @param int    $user_id The user being named, when the filter passes one; 0 for `the_author`,
	 *                        whose author is the loop's.
	 * @return string
	 */
	public static function filter_author_name( $name, $user_id = 0 ) {
		if ( is_admin() ) {
			return $name;
		}

		$record = self::record_in_loop();

		if ( '' === $record ) {
			return $name;
		}

		$post = get_post();

		if ( $user_id > 0 && $post instanceof WP_Post && (int) $post->post_author !== (int) $user_id ) {
			return $name;
		}

		$row = WPCPM_Sponsors_Index::row( $record );

		return is_array( $row ) && '' !== trim( (string) $row['name'] )
			? trim( (string) $row['name'] )
			: __( 'A sponsor of the program', 'wpcredits-program-manager' );
	}

	/**
	 * The oEmbed response's author fields read the account directly, past the_author: for a sponsor
	 * post they carry the company and its site, never the person and the author archive (spec 7.5).
	 *
	 * @param array|false $data The response, or false when the gate refused.
	 * @param WP_Post     $post The post.
	 * @return array|false
	 */
	public static function filter_oembed( $data, $post ) {
		if ( ! is_array( $data ) || ! $post instanceof WP_Post ) {
			return $data;
		}

		$record = trim( (string) get_post_meta( $post->ID, WPCPM_Sponsor_Policy::META_POST_SPONSOR, true ) );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return $data;
		}

		$row  = WPCPM_Sponsors_Index::row( $record );
		$name = is_array( $row ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : __( 'A sponsor of the program', 'wpcredits-program-manager' );
		$site = is_array( $row ) ? WPCPM_Field_Value::clean_url( (string) $row['website'] ) : '';

		$data['author_name'] = $name;
		$data['author_url']  = '' !== $site ? $site : home_url( '/' );

		return $data;
	}

	/**
	 * `the_content`, priority 6: the banner above a sponsor post, for a reader the level admits.
	 *
	 * @param string $content The content after the access gate at 5.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$record = self::record_in_loop();

		if ( '' === $record || ! WPCPM_Content_Access::can_view( get_post() ) ) {
			return $content;
		}

		$row  = WPCPM_Sponsors_Index::row( $record );
		$row  = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
		$name = '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : __( 'A sponsor', 'wpcredits-program-manager' );
		$site = WPCPM_Field_Value::clean_url( (string) $row['website'] );
		$logo = WPCPM_Sponsors_Index::display_logo( $record );

		$html = '<aside class="wpcpm-sponsor-byline">';

		if ( is_array( $logo ) && ! empty( $logo['url'] ) ) {
			$html .= sprintf( '<span class="wpcpm-sponsor-byline__logo"><img src="%s" alt="" loading="lazy" decoding="async" /></span>', esc_url( $logo['url'] ) );
		} else {
			$html .= sprintf( '<span class="wpcpm-sponsor-byline__initials" aria-hidden="true">%s</span>', esc_html( mb_substr( $name, 0, 1 ) ) );
		}

		$company = '' !== $site
			? sprintf( '<a href="%1$s" rel="external noopener">%2$s</a>', esc_url( $site ), esc_html( $name ) )
			: esc_html( $name );

		$html .= sprintf(
			'<p class="wpcpm-sponsor-byline__text">%s</p>',
			/* translators: %s: the company name, possibly linked. */
			sprintf( esc_html__( 'A guide from %s, a sponsor of the WordPress Credits program.', 'wpcredits-program-manager' ), $company )
		);
		$html .= '</aside>';

		return $html . $content;
	}
}
