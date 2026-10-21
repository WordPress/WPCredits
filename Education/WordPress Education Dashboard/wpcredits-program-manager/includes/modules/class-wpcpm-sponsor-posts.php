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

	/**
	 * Jetpack Subscriptions' own mark for a post it must never email to the site's subscribers
	 * (1.123.18): set on every sponsor post, beside the category it leaves out
	 * (`exclude_from_subscriptions()`), so either guard alone keeps a sponsor post out of the mail.
	 */
	const META_NO_SUBSCRIBER_MAIL = '_jetpack_dont_email_post_to_subs';

	/** When it went back, Unix time. */
	const META_RETURNED = '_wpcpm_sponsor_returned';

	/** A post the daily run, `WPCPM_Sponsor_Tiers_Run`, put back to pending: the Y-m-d it happened. */
	const META_DORMANT = '_wpcpm_spost_dormant';

	/** A return note's longest, in characters as the manager typed it (`WPCPM_Typed_Text::box_length()`). */
	const MAX_NOTE = 2000;

	/** A manager switches posting for a sponsor, from wp-admin. Nonce `wpcpm_sponsor_flags_<record>`. */
	const ACTION_FLAGS = 'wpcpm_sponsor_flags';

	/** Sponsor Dashboard: a manager publishes a pending post. Nonce keyed to the post. */
	const ACTION_POST_PUBLISH = 'wpcpm_sponsor_post_publish';

	/** Sponsor Dashboard: a manager returns a pending post as a draft with a note. */
	const ACTION_POST_RETURN = 'wpcpm_sponsor_post_return';

	/** What a refused return keeps its note under, before the post it returns (`WPCPM_Typed_Text::box_form()`). */
	const TYPED_RETURN = 'post-return';

	/** The mail log's context for the note to the author. */
	const MAIL_RETURNED = 'sponsor-post-returned';

	/** Audit kinds this class writes. */
	const LOG_POSTING   = 'posting_switched';
	const LOG_PUBLISHED = 'post_published';
	const LOG_RETURNED  = 'post_returned';

	/** The card lists this many posts at most; the Tools section this many guides per sponsor. */
	const CARD_LIMIT  = 50;
	const TOOLS_LIMIT = 5;

	/** A limit for posts_of() when a caller wants no real cap; five hundred in flight is not a cap problem. */
	const COUNT_LIMIT = 500;

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
		// A test sponsor's posts are in no front-end listing anybody but an Administrator reads (1.123.18).
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_test_posts' ) );
		// Nor are they found by their own link (1.123.20): the post's page and its REST route answer as
		// not found for every reader but an Administrator, its sponsor's members and its author, and so
		// does its oEmbed, in filter_oembed() below.
		add_filter( 'posts_results', array( __CLASS__, 'hide_test_post_page' ), 10, 2 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'hide_test_post_rest' ), 10, 3 );
		// And a 404 for one is no redirect to it: core's canonical redirect, its guess from part of a
		// slug and its old-slug redirect would each hand over the post's address.
		add_filter( 'redirect_canonical', array( __CLASS__, 'hide_test_post_redirect' ), 10, 2 );
		add_filter( 'pre_redirect_guess_404_permalink', array( __CLASS__, 'hide_test_post_guess' ) );
		add_filter( 'old_slug_redirect_post_id', array( __CLASS__, 'hide_test_post_old_slug' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'scope_media' ) );
		add_filter( 'rest_attachment_query', array( __CLASS__, 'scope_media' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'scope_attachment_details' ), 10, 2 );
		add_filter( 'upload_mimes', array( __CLASS__, 'limit_mimes' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_access_box' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'trim_menu' ), 999 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'trim_bar' ), 999 );
		// In wp-admin a member sees their own category alone: the editor's category list (REST),
		// the term lists the screens build, and no category filter over posts that are all theirs.
		add_filter( 'rest_category_query', array( __CLASS__, 'scope_categories' ), 10, 2 );
		add_filter( 'get_terms_args', array( __CLASS__, 'scope_terms_args' ), 10, 2 );
		add_filter( 'disable_categories_dropdown', array( __CLASS__, 'hide_category_filter' ), 10, 2 );
		add_filter( 'the_author', array( __CLASS__, 'filter_author_name' ) );
		add_filter( 'get_the_author_display_name', array( __CLASS__, 'filter_author_name' ), 10, 2 );
		// After the access gate at 5: a refused reader gets the notice and no banner.
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 6 );
		// After the gate's own oembed_response_data filter at 10, which answers a refusal first.
		add_filter( 'oembed_response_data', array( __CLASS__, 'filter_oembed' ), 20, 2 );
		// Jetpack Subscriptions emails a post each time it moves to publish, a sponsor post an
		// Administrator approves and a dormant one the daily run wakes among them. Its own filter,
		// which only Jetpack applies, so without Jetpack this does nothing and asks for nothing.
		add_filter( 'jetpack_subscriptions_exclude_these_categories', array( __CLASS__, 'exclude_from_subscriptions' ) );
	}

	/**
	 * `pre_get_posts`: a test sponsor is never listed publicly (1.123.18), and its posts are posts of
	 * the site, which the blog, its archives, its feeds and its search list to every reader the
	 * access gate admits. On the front end, for anybody but an Administrator, a listing leaves out
	 * every post stamped with a test sponsor's key. The same queries the gate stands aside from are
	 * left alone: wp-admin, a single post's own page, which the gate answers and
	 * `hide_test_post_page()` answers as not found for a test sponsor's post (1.123.20), a query that
	 * names none of the post types a sponsor post can be, and the plugin's own queries that stand
	 * outside the gate by name (`QUERY_UNGATED`), so the test sponsor's own members keep their Posts
	 * card.
	 * The store is read only for a query that can list a post, so reading it, itself a query of
	 * the sponsors' own post type, never comes back here.
	 *
	 * @param WP_Query $query The query being prepared.
	 */
	public static function hide_test_posts( $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || $query->get( WPCPM_Content_Access::QUERY_UNGATED ) || $query->is_singular() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( $user_id && user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		// An unset post type is the blog index, a feed or a search, which list posts.
		$post_types = array_filter( array_map( 'strval', (array) $query->get( 'post_type' ) ), 'strlen' );

		if ( ! empty( $post_types ) && ! in_array( 'post', $post_types, true ) && ! in_array( 'any', $post_types, true ) ) {
			return;
		}

		$tests = WPCPM_Sponsors_Index::test_keys();

		if ( empty( $tests ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => WPCPM_Sponsor_Policy::META_POST_SPONSOR,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => WPCPM_Sponsor_Policy::META_POST_SPONSOR,
				'value'   => $tests,
				'compare' => 'NOT IN',
			),
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Whether a post is a test sponsor's that this reader may not reach by its own link (1.123.20): a
	 * post stamped with the key of a sponsor that is a test sponsor today, read by anybody but an
	 * Administrator, a member of that same sponsor, or the post's author. The one answer for the
	 * post's page (`hide_test_post_page()`), its REST route (`hide_test_post_rest()`) and its oEmbed
	 * (`filter_oembed()`), so the three cannot drift apart. Nothing remembers it: the sponsor's row
	 * is read for each request, so a sponsor that stops being a test sponsor is reachable at once.
	 *
	 * @param WP_Post|int $post    The post.
	 * @param int|null    $user_id The reader; null for the current user, 0 for a visitor.
	 * @return bool
	 */
	public static function is_hidden_test_post( $post, $user_id = null ) {
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$record = trim( (string) get_post_meta( $post->ID, WPCPM_Sponsor_Policy::META_POST_SPONSOR, true ) );

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) || ! WPCPM_Sponsors_Index::is_test( $record ) ) {
			return false;
		}

		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;

		if ( $user_id < 1 ) {
			return true;
		}

		return ! user_can( $user_id, WPCPM_Roles::CAP_MANAGE )
			&& (int) $post->post_author !== $user_id
			&& ! WPCPM_Sponsor_Members::is_member( $user_id, $record );
	}

	/**
	 * `posts_results`: the request for a test sponsor's post, its page or its embed, finds no post for
	 * a reader `is_hidden_test_post()` keeps it from (1.123.20). The main query alone, and only a
	 * single post's: WordPress then answers as it does for a post that does not exist, through its
	 * own 404, with the 404 status, no caching, and the theme's 404 template. Before the access gate's
	 * own guard on `template_redirect`, which would send a visitor to the login form for it, and so
	 * say the post is there. Nothing in wp-admin.
	 *
	 * @param array    $posts The posts the query found.
	 * @param WP_Query $query The query.
	 * @return array
	 */
	public static function hide_test_post_page( $posts, $query ) {
		if ( is_admin() || ! is_array( $posts ) || ! $query instanceof WP_Query || ! $query->is_main_query() || ! $query->is_singular() ) {
			return $posts;
		}

		return array_values(
			array_filter(
				$posts,
				static function ( $post ) {
					return ! ( $post instanceof WP_Post && self::is_hidden_test_post( $post ) );
				}
			)
		);
	}

	/**
	 * `rest_request_before_callbacks`: a read of a test sponsor's post through the REST API's single-post
	 * route answers as core answers for a post that is not there, `rest_post_invalid_id` with a 404,
	 * for a reader `is_hidden_test_post()` keeps it from (1.123.20).
	 *
	 * The route is known by the handler core matched it to, a posts controller's `get_item()`, and
	 * the post by the id that handler reads, `(int) $request['id']`, never by the path as spelled:
	 * core matches routes whatever their case and reads `/posts/0123` as post 123, so a comparison
	 * of paths let `/POSTS/<id>`, a leading zero and the same through `?rest_route=` through.
	 *
	 * A read alone: every write is wp-admin's, and so is the block editor's own read of a post it
	 * edits (`context=edit`), for a person who may edit that post. For anybody else the edit context
	 * answers as not found too, since core's own answer to it, a forbidden context, would say the
	 * post is there. An answer another filter already gave goes through.
	 *
	 * @param mixed           $response Null, or the answer already decided.
	 * @param array           $handler  The handler the route matched.
	 * @param WP_REST_Request $request  The request.
	 * @return mixed
	 */
	public static function hide_test_post_rest( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}

		$callback = is_array( $handler ) && isset( $handler['callback'] ) ? $handler['callback'] : null;

		if ( ! is_array( $callback ) || ! isset( $callback[0], $callback[1] ) || ! $callback[0] instanceof WP_REST_Posts_Controller || 'get_item' !== $callback[1] ) {
			return $response;
		}

		if ( ! in_array( strtoupper( (string) $request->get_method() ), array( 'GET', 'HEAD' ), true ) ) {
			return $response;
		}

		$post = get_post( (int) $request->get_param( 'id' ) );

		if ( ! $post instanceof WP_Post || ! self::is_hidden_test_post( $post ) ) {
			return $response;
		}

		if ( 'edit' === $request->get_param( 'context' ) && current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}

		return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'wpcredits-program-manager' ), array( 'status' => 404 ) );
	}

	/**
	 * `redirect_canonical`: a 404 for a test sponsor's post, for a reader `is_hidden_test_post()` keeps
	 * it from (1.123.20), redirects nowhere. Core sends `?p=` and `?page_id=` on a 404 to the post's
	 * permalink without asking who may read it, so a visitor counting through `/?p=N` would learn the
	 * slug of every such post, and its guess from part of a slug lands here as the URL to redirect to.
	 * The post is the one the query vars name, or the one the URL names (`url_to_postid()`).
	 *
	 * @param string|false $redirect_url  Where core would redirect.
	 * @param string       $requested_url What was asked for.
	 * @return string|false
	 */
	public static function hide_test_post_redirect( $redirect_url, $requested_url = '' ) {
		unset( $requested_url );

		if ( ! is_string( $redirect_url ) || '' === $redirect_url || ! is_404() ) {
			return $redirect_url;
		}

		foreach ( array( (int) get_query_var( 'p' ), (int) get_query_var( 'page_id' ), (int) url_to_postid( $redirect_url ) ) as $post_id ) {
			if ( $post_id > 0 && self::is_hidden_test_post( $post_id ) ) {
				return false;
			}
		}

		return $redirect_url;
	}

	/**
	 * `pre_redirect_guess_404_permalink`: core's guess of a permalink from part of a slug is never a
	 * test sponsor's post, for a reader `is_hidden_test_post()` keeps it from (1.123.20). Core offers
	 * no filter on what it guessed, so the guess is made here, once, with this filter standing aside
	 * while core makes it, and handed back as core's own answer, or no guess at all for such a post.
	 * A guess another filter already made goes through.
	 *
	 * @param mixed $pre Null, or the guess already made.
	 * @return mixed
	 */
	public static function hide_test_post_guess( $pre ) {
		static $guessing = false;

		if ( null !== $pre || $guessing ) {
			return $pre;
		}

		$guessing = true;
		$guess    = redirect_guess_404_permalink();
		$guessing = false;

		if ( is_string( $guess ) && '' !== $guess ) {
			$post_id = (int) url_to_postid( $guess );

			if ( $post_id > 0 && self::is_hidden_test_post( $post_id ) ) {
				return false;
			}
		}

		return $guess;
	}

	/**
	 * `old_slug_redirect_post_id`: an old slug of a test sponsor's post leads nowhere, for a reader
	 * `is_hidden_test_post()` keeps it from (1.123.20), rather than to the post's new address.
	 *
	 * @param int $post_id The post the old slug belonged to, or 0.
	 * @return int
	 */
	public static function hide_test_post_old_slug( $post_id ) {
		return (int) $post_id > 0 && self::is_hidden_test_post( (int) $post_id ) ? 0 : $post_id;
	}

	/**
	 * `jetpack_subscriptions_exclude_these_categories`: the Sponsors category and every sponsor's
	 * own under it join the categories Jetpack never emails to the site's subscribers (1.123.18).
	 * Read by slug and never created here: with no Sponsors category yet there is no sponsor post
	 * to leave out, and the list goes back as it came.
	 *
	 * @param mixed $categories The category IDs or slugs left out so far.
	 * @return array
	 */
	public static function exclude_from_subscriptions( $categories ) {
		$categories = is_array( $categories ) ? $categories : array();
		$parent     = get_term_by( 'slug', self::PARENT_SLUG, 'category' );

		if ( ! is_object( $parent ) || empty( $parent->term_id ) ) {
			return $categories;
		}

		$categories[] = (int) $parent->term_id;
		$children     = get_term_children( (int) $parent->term_id, 'category' );

		if ( is_array( $children ) ) {
			sort( $children );

			foreach ( $children as $child ) {
				$categories[] = (int) $child;
			}
		}

		return array_values( array_unique( $categories, SORT_REGULAR ) );
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

		// A submission the tier's cap has no room for is a draft, never a refusal: this filter
		// can only change the data (spec inventory 3c), so the card is where a member reads why.
		// A post already pending or published holds its slot already - re-saving it (a dormant
		// one included) is not a new claim on the cap, so only a post about to enter pending for
		// the first time, from a draft, an auto-draft or brand new, asks whether there is room
		// (review finding: the clamp was drafting an edit whenever the sponsor's OTHER posts
		// alone already reached the cap, which is the normal state while any post sits dormant).
		if ( 'pending' === $data['post_status'] ) {
			$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
			$prior   = $post_id > 0 ? get_post_status( $post_id ) : false;

			if ( ! in_array( $prior, array( 'pending', 'publish' ), true ) ) {
				$record = WPCPM_Sponsor_Members::sponsor_of();

				if ( '' !== $record && '' !== self::write_reason( $record ) ) {
					$data['post_status'] = 'draft';
				}
			}
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

		// An edit is a new submission: the daily run may only wake a post nobody has touched
		// since it parked it, so a member's own save - classic or REST, both raise save_post_post
		// - takes the stamp off, whatever the run left here before (review finding: the stamp
		// used to survive an edit, so wake() would have published text nobody reviewed).
		delete_post_meta( (int) $post_id, self::META_DORMANT );
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
		if ( $post_id < 1 || ! WPCPM_Sponsors_Index::is_key( (string) $record ) ) {
			return;
		}

		$terms = array_filter( array( self::parent_term_id(), self::ensure_terms( $record ) ) );

		if ( ! empty( $terms ) ) {
			wp_set_post_terms( $post_id, array_values( $terms ), 'category', false );
		}

		update_post_meta( $post_id, WPCPM_Content_Access::META_KEY, WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS );
		update_post_meta( $post_id, WPCPM_Sponsor_Policy::META_POST_SPONSOR, $record );
		update_post_meta( $post_id, self::META_NO_SUBSCRIBER_MAIL, 1 );
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
	 * @param string $record Sponsor key.
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
	 * @param string $record Sponsor key.
	 * @return bool
	 */
	public static function posting_enabled( $record ) {
		return ! empty( self::flags( $record )['posts'] );
	}

	/**
	 * Switch posting for a sponsor and apply it to every live member at once.
	 *
	 * @param string $record   Sponsor key.
	 * @param bool   $on       The new state.
	 * @param int    $actor_id Who switched it.
	 * @return bool True when the flag changed; false for a bad record or a switch to the state it was in.
	 */
	public static function set_posting( $record, $on, $actor_id ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
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
	 * @param string $record  Sponsor key.
	 */
	public static function apply_caps( $user_id, $record ) {
		if ( ! WPCPM_Sponsors_Index::is_key( (string) $record ) || ! self::posting_enabled( $record ) ) {
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
	 * @param string $record Sponsor key.
	 * @return int Term id, or 0.
	 */
	public static function ensure_terms( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
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

		// The name as a slashed copy: core unslashes a term's name, and the company's name is
		// read from its record unslashed. The slug is `sanitize_title()`'s, which core reads as given.
		$made = wp_insert_term(
			wp_slash( $name ),
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
	 * @param string $record Sponsor key.
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
	 * Rename the child terms of the sponsors a save just renamed. The slug is kept on purpose:
	 * links to the category keep working, and the name is what readers see. The Sponsors
	 * screen's Edit form is the caller since 1.111.0, when the sponsor record moved onto the site
	 * and the sync that called this went with it.
	 *
	 * @param array $before The rows as the store held them before the save, keyed by sponsor key.
	 * @param array $after  The rows as saved, keyed by sponsor key.
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
				// Slashed, as `ensure_terms()` hands it over: core merges the arguments with the
				// stored term, which it slashes, and unslashes the name.
				wp_update_term( $term_id, 'category', wp_slash( array( 'name' => $new ) ) );
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
		self::clear_dormant_stamps();

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

	/**
	 * Uninstall: every post still carrying META_DORMANT loses the stamp, across every status a
	 * stamped post could be in. Not the daily run's own call: waking every dormant post at once
	 * is exactly what it must never do, and this is uninstall's own cleanup, nothing else's. The
	 * posts themselves are never touched here - they are site content, the same as the category
	 * terms a few lines up.
	 */
	private static function clear_dormant_stamps() {
		$stamped = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'private', 'trash' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'meta_key'         => self::META_DORMANT,
				'meta_compare'     => 'EXISTS',
				'suppress_filters' => false,
			)
		);

		foreach ( (array) $stamped as $id ) {
			delete_post_meta( (int) $id, self::META_DORMANT );
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
	 * @param string   $record   Sponsor key.
	 * @param string[] $statuses Post statuses.
	 * @param int      $limit    How many at most.
	 * @return WP_Post[]
	 */
	public static function posts_of( $record, array $statuses = array( 'draft', 'pending', 'publish' ), $limit = self::CARD_LIMIT ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
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
	 * How many of a sponsor's posts hold a slot toward its tier's cap: pending or published,
	 * one named by id left out. A pending post the daily run demoted still holds its slot, so it
	 * is not left out here the way pending_all() leaves it out of the manager's queue.
	 *
	 * @param string $record    Sponsor key.
	 * @param int    $except_id A post already counted to leave out, or 0 to leave none out.
	 * @return int
	 */
	public static function post_count( $record, $except_id = 0 ) {
		$count = 0;

		foreach ( self::posts_of( $record, array( 'pending', 'publish' ), self::COUNT_LIMIT ) as $post ) {
			if ( (int) $post->ID !== (int) $except_id ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * A sponsor's published posts, as posts_of() orders them.
	 *
	 * @param string $record Sponsor key.
	 * @return WP_Post[]
	 */
	public static function published_of( $record ) {
		return self::posts_of( $record, array( 'publish' ), self::COUNT_LIMIT );
	}

	/**
	 * A sponsor's pending posts the daily run put back from publish, stamped META_DORMANT.
	 *
	 * @param string $record Sponsor key.
	 * @return WP_Post[]
	 */
	public static function dormant_of( $record ) {
		$out = array();

		foreach ( self::posts_of( $record, array( 'pending' ), self::COUNT_LIMIT ) as $post ) {
			if ( '' !== (string) get_post_meta( $post->ID, self::META_DORMANT, true ) ) {
				$out[] = $post;
			}
		}

		return $out;
	}

	/**
	 * A post's state in the sponsor's words: draft, returned (a draft with a note), pending,
	 * dormant (a pending post the daily run put back), published.
	 *
	 * @param WP_Post $post The post.
	 * @return string One of `draft`, `returned`, `pending`, `dormant`, `published`.
	 */
	public static function state_of( WP_Post $post ) {
		if ( 'publish' === $post->post_status ) {
			return 'published';
		}

		if ( 'pending' === $post->post_status ) {
			return '' !== (string) get_post_meta( $post->ID, self::META_DORMANT, true ) ? 'dormant' : 'pending';
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
			'dormant'   => __( 'Dormant', 'wpcredits-program-manager' ),
			'published' => __( 'Published', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
	}

	/*
	 * The tier's post cap
	 * --------------------------------------------------------------------
	 */

	/**
	 * The tier's post cap for a sponsor, or null for any number; null too while the switch is
	 * off, so nothing below is ever asked to spell out a benefit the switch has not turned on.
	 *
	 * @param string $record Sponsor key.
	 * @return int|null
	 */
	private static function cap_of( $record ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return null;
		}

		$row = WPCPM_Sponsors_Index::row( $record );

		return WPCPM_Sponsor_Tiers::post_cap( WPCPM_Sponsor_Tiers::effective( is_array( $row ) ? $row : array() ) );
	}

	/**
	 * A sponsor's effective tier, in words.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	private static function tier_label( $record ) {
		$row = WPCPM_Sponsors_Index::row( $record );

		return WPCPM_Sponsor_Tiers::label( WPCPM_Sponsor_Tiers::effective( is_array( $row ) ? $row : array() ) );
	}

	/**
	 * The sentence write_reason() asks for: '' while the switch is off; the tier's own sentence
	 * while it includes no posts at all; '' while the sponsor's count has not reached the cap;
	 * the sentence once it has. The editor fence's own clamp does not call this any more (review
	 * finding): a post already pending or published is left alone there before this is ever
	 * asked, so there is no post to except out of the count here either.
	 *
	 * A count sentence never spells "one" into the plural form's own placeholder: `1 === $cap`
	 * is answered by its own sentence, so `_n()`'s pair only ever fires for two or more, and its
	 * singular form still carries `%2$d` rather than the English word, for every locale whose
	 * plural rule picks that form for a count other than one.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	private static function reason( $record ) {
		$cap = self::cap_of( $record );

		if ( null === $cap ) {
			return '';
		}

		// The tier posts start at, named: for a Friend the next tier, Supporter, includes no
		// posts either, so "the next tier" would send it to one without them.
		if ( 0 === $cap ) {
			return sprintf(
				/* translators: 1: the sponsor's tier's name, 2: the name of the lowest tier that includes posts. */
				__( 'Your tier, %1$s, includes no posts. Posts start at %2$s: talk to your program contact.', 'wpcredits-program-manager' ),
				self::tier_label( $record ),
				WPCPM_Sponsor_Tiers::label( WPCPM_Sponsor_Tiers::BACKER )
			);
		}

		if ( self::post_count( $record ) < $cap ) {
			return '';
		}

		if ( 1 === $cap ) {
			/* translators: %s: the tier's name. */
			return sprintf( __( 'Your tier, %s, includes one post at a time, in review or published. The next can follow once the program returns this one or takes it down.', 'wpcredits-program-manager' ), self::tier_label( $record ) );
		}

		return sprintf(
			/* translators: 1: the tier's name, 2: how many posts it includes at a time. */
			_n( 'Your tier, %1$s, includes %2$d post at a time, in review or published.', 'Your tier, %1$s, includes %2$d posts at a time, in review or published.', $cap, 'wpcredits-program-manager' ),
			self::tier_label( $record ),
			$cap
		);
	}

	/**
	 * Why Write a post cannot be pressed, or '' when it can: the card's own words for the
	 * editor fence's reason().
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	public static function write_reason( $record ) {
		return self::reason( $record );
	}

	/**
	 * Why a manager's Publish is refused, or '' when it is not: only the published count is
	 * asked, since a sponsor's cap counts pending and published together (`post_count()`) and
	 * publishing a pending post keeps that total the same rather than growing it.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	public static function publish_reason( $record ) {
		$cap = self::cap_of( $record );

		if ( null === $cap || count( self::published_of( $record ) ) < $cap ) {
			return '';
		}

		/* translators: %s: the tier's name. */
		return sprintf( __( 'Not published: the sponsor\'s tier, %s, includes no more published posts. The tier is on the Sponsors screen.', 'wpcredits-program-manager' ), self::tier_label( $record ) );
	}

	/**
	 * A published post back to pending, with the stamp that marks it the daily run's own move
	 * rather than a submission: pending_all() leaves it out. Core has no status-only helper for
	 * publish to pending, so this goes through wp_update_post(): under cron there is no user, and
	 * kses runs on the re-save regardless, which changes nothing for a member's own content
	 * (their own save already ran the same filters) and can strip markup a manager added with
	 * unfiltered_html - accepted here, for a post the run only parks rather than changes.
	 *
	 * @param int    $post_id The post.
	 * @param string $today   `Y-m-d`; '' for WPCPM_Sponsor_Tiers::today().
	 * @return bool Whether the post was published and is now dormant.
	 */
	public static function set_dormant( $post_id, $today = '' ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return false;
		}

		$moved = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'pending',
			),
			true
		);

		if ( is_wp_error( $moved ) || ! $moved ) {
			return false;
		}

		update_post_meta( $post->ID, self::META_DORMANT, '' !== (string) $today ? (string) $today : WPCPM_Sponsor_Tiers::today() );

		return true;
	}

	/**
	 * A dormant post back to publish, its stamp cleared. No date moves: the post keeps the day
	 * it first went live, whichever run that was. wake() is only ever the daily run's own move,
	 * for a post nobody has touched since it went dormant: an edit (enforce()), a manager's
	 * Publish or a manager's Return each take the stamp off on their own the moment they happen,
	 * because every one of those is a submission again, never the run's to bring back on its own.
	 *
	 * @param int $post_id The post.
	 * @return bool Whether the post was pending and stamped, and is now published.
	 */
	public static function wake( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post || 'pending' !== $post->post_status || '' === (string) get_post_meta( $post->ID, self::META_DORMANT, true ) ) {
			return false;
		}

		// wp_publish_post(), not wp_update_post(): the daily run has no user and nothing new to
		// save, so a full re-save here would run the member's already-reviewed content through
		// the content filters a second time for no reason, under cron. handle_publish()'s own
		// reason for avoiding wp_publish_post() - a pending post's untouched zero GMT date - does
		// not apply here: a dormant post's GMT date was already set the day it first went live.
		// Jetpack's mark first, for a post published before the mark existed: Jetpack reads it the
		// moment the post goes live again, and would otherwise email it to the subscribers anew.
		update_post_meta( $post->ID, self::META_NO_SUBSCRIBER_MAIL, 1 );
		wp_publish_post( $post->ID );

		// wp_publish_post() answers nothing and never checks its own database write, so a failed one is read back here rather than assumed.
		if ( 'publish' !== get_post_status( $post->ID ) ) {
			return false;
		}

		delete_post_meta( $post->ID, self::META_DORMANT );

		return true;
	}

	/**
	 * The card's own words for a dormant post: nothing about the tiers before the switch is on
	 * (the rule for every sponsor-facing surface: a switch flipped on and off again before launch
	 * must not leave a sponsor reading their tier), the daily run's own wording whenever the tier
	 * already covers the post and only the run itself has not caught up yet, and otherwise the cap
	 * in words.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	public static function dormant_note( $record ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return __( 'Held back by the program: not shown right now. It returns by itself when the program sets it live again.', 'wpcredits-program-manager' );
		}

		$cap = self::cap_of( $record );

		// Two cases read the same: a tier with no cap always covers any number of posts, so a
		// post dormant under one is only waiting on the next daily run to wake it, never stuck
		// ((int) null would otherwise print "0 published posts", which is wrong on its face, and
		// the offers card's dormant sentence reads a tier with no cap the same way); and a tier
		// with a cap that already has room for this post - a manager raised the tier since the
		// run parked it - is the same wait, not a manager's decision still to make (review
		// finding).
		if ( null === $cap || count( self::published_of( $record ) ) < $cap ) {
			return __( 'Held back until the next daily run, which brings back what your tier has room for.', 'wpcredits-program-manager' );
		}

		if ( 0 === $cap ) {
			return __( 'Dormant: your tier includes no posts, so a published post is held back. It returns by itself when an Administrator sets a tier that includes it.', 'wpcredits-program-manager' );
		}

		if ( 1 === $cap ) {
			return __( 'Dormant: your tier includes one published post at a time, so the others are held back. They return by themselves when an Administrator sets a tier that includes them.', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %d: how many published posts the tier includes at a time. */
			_n( 'Dormant: your tier includes %d published post at a time, so the others are held back. They return by themselves when an Administrator sets a tier that includes them.', 'Dormant: your tier includes %d published posts at a time, so the others are held back. They return by themselves when an Administrator sets a tier that includes them.', $cap, 'wpcredits-program-manager' ),
			$cap
		);
	}

	/*
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Posts card on the Sponsor Dashboard: every post with its state, edit links for the
	 * author, Write a post when the flag is on, and for a program manager the way to this sponsor's
	 * posts in wp-admin. No decision is drawn here: a pending post is published or returned from
	 * the Administrator Dashboard's Sponsor posts to review card, which `render_decision()` draws.
	 * The same section-around-a-disclosure shape as every card on the page.
	 *
	 * Folded until a flash names it. The row says how many of the posts listed are published and how
	 * many wait for review, each read by its own state. The posts listed are the card's own list, at
	 * most CARD_LIMIT, the newest change first, drafts among them: the list the count beside the
	 * title counts, so the row and the title agree however many posts there are. While the card is
	 * folded, Write a post follows the fold, the editor's address, exactly when the button inside is
	 * drawn: for a member, with posting on and room in the tier. The card's first line says where a
	 * published post appears.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$record     = trim( (string) $record );
		$can_manage = ! empty( $context['can_manage'] );
		$viewer     = isset( $context['viewer'] ) && $context['viewer'] instanceof WP_User ? $context['viewer'] : wp_get_current_user();
		$is_member  = WPCPM_Sponsor_Members::is_member( $viewer, $record );
		$posts      = self::posts_of( $record );
		$open       = isset( $context['open'] ) && self::CARD === $context['open'];
		$posting    = $is_member && ! $can_manage && self::posting_enabled( $record );
		$why        = $posting ? self::write_reason( $record ) : '';
		$counted    = array(
			'published' => 0,
			'pending'   => 0,
		);

		foreach ( $posts as $post ) {
			$state = self::state_of( $post );

			if ( isset( $counted[ $state ] ) ) {
				++$counted[ $state ];
			}
		}

		WPCPM_Dashboard_Card::open(
			array(
				'key'    => self::CARD,
				'title'  => __( 'Posts', 'wpcredits-program-manager' ),
				'count'  => count( $posts ),
				'open'   => $open,
				'state'  => sprintf(
					/* translators: 1: how many of the sponsor's posts are published, 2: how many are waiting for review. */
					__( '%1$s published, %2$s waiting', 'wpcredits-program-manager' ),
					number_format_i18n( $counted['published'] ),
					number_format_i18n( $counted['pending'] )
				),
				'action' => $posting && '' === $why ? array(
					'label'   => __( 'Write a post', 'wpcredits-program-manager' ),
					'href'    => admin_url( 'post-new.php' ),
					/* translators: read after "Write a post" by a screen reader alone. */
					'context' => __( 'in the WordPress editor', 'wpcredits-program-manager' ),
				) : null,
			)
		);

		// Where a published post appears, said here since the group's lead is one sentence for its
		// four cards (the design the owner approved on 27 September 2026).
		echo '<p class="wpcpm-student__note">' . esc_html__( 'A post appears under your offer in Tools from our sponsors, on the Student Report Card and the Mentor Report Card, once an Administrator publishes it.', 'wpcredits-program-manager' ) . '</p>';

		if ( $is_member && ! $can_manage ) {

			if ( $posting ) {
				if ( '' === $why ) {
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
					// The tier's cap has no room right now: the sentence stands where Write a post
					// would, and the way into wp-admin stays, for editing what already exists.
					printf( '<p class="wpcpm-student__note">%s</p>', esc_html( $why ) );
					printf(
						'<p class="wpcpm-posts__actions"><a class="wpcpm-posts__admin" href="%1$s">%2$s</a></p>',
						esc_url( admin_url( 'edit.php' ) ),
						esc_html__( 'Your posts in wp-admin', 'wpcredits-program-manager' )
					);
				}
			} else {
				echo '<p class="wpcpm-student__note">' . esc_html__( 'The program has not enabled posting for this sponsor.', 'wpcredits-program-manager' ) . '</p>';
			}
		} else {

			if ( $can_manage && ! self::posting_enabled( $record ) ) {
				echo '<p class="wpcpm-student__note">' . esc_html__( 'Posting is off for this sponsor. Switch it on from Manage accounts on the Sponsors screen\'s Accounts tab.', 'wpcredits-program-manager' ) . '</p>';
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
			// The Updates list's own classes, so the posts here look exactly like "Program updates
			// and announcements" on the Student Report Card and the Mentor Report Card (the owner
			// asked for one look): a bold title, the state and date under it, a hairline between.
			echo '<ul class="wpcpm-updates wpcpm-updates--posts">';

			foreach ( $posts as $post ) {
				self::render_item( $post );
			}

			echo '</ul>';
		}

		if ( $is_member && ! empty( self::dormant_of( $record ) ) ) {
			printf( '<p class="wpcpm-student__note">%s</p>', esc_html( self::dormant_note( $record ) ) );
		}

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * One post on the card, in the Updates list's shape: the title (a link to the post once
	 * published, to the editor while its author may still edit it), then one muted line with the
	 * state and the date; a returned post shows the manager's note under them. No controls: the
	 * sponsor's accounts submit, and a program manager publishes or returns a pending post from
	 * the Administrator Dashboard's Sponsor posts to review card, which `render_decision()` draws.
	 *
	 * @param WP_Post $post The post.
	 */
	private static function render_item( WP_Post $post ) {
		$state = self::state_of( $post );
		$title = '' !== trim( (string) $post->post_title ) ? $post->post_title : __( '(no title)', 'wpcredits-program-manager' );

		printf( '<li class="wpcpm-updates__item wpcpm-posts__item--%1$s" id="wpcpm-post-%2$d">', esc_attr( $state ), (int) $post->ID );

		if ( 'published' === $state ) {
			printf( '<a class="wpcpm-updates__link" href="%1$s">%2$s</a>', esc_url( get_permalink( $post ) ), esc_html( $title ) );
		} elseif ( current_user_can( 'edit_post', $post->ID ) ) {
			printf( '<a class="wpcpm-updates__link" href="%1$s">%2$s</a>', esc_url( get_edit_post_link( $post->ID, 'raw' ) ), esc_html( $title ) );
		} else {
			printf( '<span class="wpcpm-updates__link">%s</span>', esc_html( $title ) );
		}

		printf(
			'<span class="wpcpm-updates__date">%1$s &middot; %2$s</span>',
			esc_html( self::state_label( $state ) ),
			esc_html( get_the_modified_date( '', $post ) )
		);

		if ( 'returned' === $state ) {
			printf( '<p class="wpcpm-posts__note">%s</p>', esc_html( (string) get_post_meta( $post->ID, self::META_RETURN_NOTE, true ) ) );
		}

		echo '</li>';
	}

	/**
	 * The two categories a member's posts live in: Sponsors and the company's own, read and never
	 * created here (pin() creates the child on the first save). Cached for the request.
	 *
	 * @return int[]
	 */
	private static function own_terms() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$record = WPCPM_Sponsor_Members::sponsor_of();
		$cache  = array();

		if ( '' !== $record ) {
			$cache = array_values( array_filter( array( self::parent_term_id(), self::term_of( $record ) ) ) );
		}

		return $cache;
	}

	/**
	 * `rest_category_query`: the block editor's category list shows a member their own two.
	 *
	 * @param array           $args    The query arguments.
	 * @param WP_REST_Request $request The request.
	 * @return array
	 */
	public static function scope_categories( $args, $request = null ) {
		if ( ! self::fenced() ) {
			return $args;
		}

		$terms = self::own_terms();

		if ( ! empty( $terms ) ) {
			$args            = is_array( $args ) ? $args : array();
			$args['include'] = $terms;
			$args['exclude'] = array();
		}

		return $args;
	}

	/**
	 * `get_terms_args`: every category list wp-admin builds for a member (Quick Edit, the
	 * classic box) is their own two. Guarded against itself: reading the parent term runs
	 * get_terms() and would meet this filter again.
	 *
	 * @param array    $args       The query arguments.
	 * @param string[] $taxonomies The taxonomies asked for.
	 * @return array
	 */
	public static function scope_terms_args( $args, $taxonomies ) {
		static $busy = false;

		if ( $busy || ! self::fenced() || ! is_admin() || ! in_array( 'category', (array) $taxonomies, true ) ) {
			return $args;
		}

		$busy  = true;
		$terms = self::own_terms();
		$busy  = false;

		if ( ! empty( $terms ) ) {
			$args            = is_array( $args ) ? $args : array();
			$args['include'] = $terms;
		}

		return $args;
	}

	/**
	 * `disable_categories_dropdown`: no category filter on a member's Posts screen, where every
	 * post is theirs and in their category.
	 *
	 * @param bool   $disable   Whether core would hide it.
	 * @param string $post_type The screen's post type.
	 * @return bool
	 */
	public static function hide_category_filter( $disable, $post_type ) {
		return self::fenced() ? true : $disable;
	}

	/**
	 * Every sponsor post waiting for review, oldest first, as the facts the Administrator
	 * Dashboard's queue prints: no post object leaves this class, so the cards class needs to
	 * know nothing about posts.
	 *
	 * @param int $limit How many at most.
	 * @return array[] `id`, `title`, `record`, `company`, `author`, `at` (Unix time, GMT), `preview`.
	 */
	public static function pending_all( $limit = 50 ) {
		$found = get_posts(
			array(
				'post_type'                         => 'post',
				'post_status'                       => 'pending',
				'numberposts'                       => max( 1, (int) $limit ),
				'orderby'                           => 'date',
				'order'                             => 'ASC',
				// A dormant post is excluded here, in the query, rather than skipped afterward:
				// a PHP-side skip still lets an older dormant post (it keeps the date it first
				// went live) spend one of $limit's slots before being dropped, which can push a
				// newer, genuine submission out of a tight window (review finding).
				'meta_query'                        => array(
					'relation' => 'AND',
					array(
						'key'     => WPCPM_Sponsor_Policy::META_POST_SPONSOR,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => self::META_DORMANT,
						'compare' => 'NOT EXISTS',
					),
				),
				WPCPM_Content_Access::QUERY_UNGATED => true,
				'suppress_filters'                  => false,
			)
		);

		$rows = array();

		foreach ( (array) $found as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$record = trim( (string) get_post_meta( $post->ID, WPCPM_Sponsor_Policy::META_POST_SPONSOR, true ) );

			if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
				continue;
			}

			$row    = WPCPM_Sponsors_Index::row( $record );
			$author = get_user_by( 'id', (int) $post->post_author );

			$rows[] = array(
				'id'      => (int) $post->ID,
				'title'   => '' !== trim( (string) $post->post_title ) ? (string) $post->post_title : __( '(no title)', 'wpcredits-program-manager' ),
				'record'  => $record,
				'company' => is_array( $row ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record,
				'author'  => $author instanceof WP_User ? (string) $author->display_name : '',
				'at'      => (int) get_post_time( 'U', true, $post ),
				'preview' => (string) get_preview_post_link( $post ),
			);
		}

		return $rows;
	}

	/**
	 * A manager's decision on a pending sponsor post, in the request cards' own shape: Preview,
	 * Publish, and a folded "Return with a note" whose form opens only when chosen. Drawn by the
	 * Administrator Dashboard's queue (spec 7.4); the Sponsor Dashboard carries no controls.
	 *
	 * @param int    $post_id The post.
	 * @param string $return  `WPCPM_Return::DASHBOARD` to come back to the dashboard, else ''.
	 */
	public static function render_decision( $post_id, $return = '' ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post || 'pending' !== $post->post_status || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		echo '<div class="wpcpm-request__decide wpcpm-sponsor-post__decide">';

		printf( '<a class="button" href="%1$s">%2$s</a>', esc_url( get_preview_post_link( $post ) ), esc_html__( 'Preview', 'wpcredits-program-manager' ) );

		printf( '<form class="wpcpm-sponsor-post__form" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION_POST_PUBLISH . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_POST_PUBLISH ) );
		printf( '<input type="hidden" name="wpcpm_post" value="%d" />', (int) $post->ID );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $return, 'sponsor-posts' );
		}

		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html__( 'Publish', 'wpcredits-program-manager' ) );
		echo '</form>';

		// A refused return comes back with the note as it was typed, its fold open so the box is
		// where it was left.
		$note = WPCPM_Typed_Text::kept_box( WPCPM_Typed_Text::box_form( self::TYPED_RETURN, $post->ID ), 'note' );

		echo null === $note ? '<details class="wpcpm-sponsor-post__return">' : '<details class="wpcpm-sponsor-post__return" open>';
		printf( '<summary class="button">%s</summary>', esc_html__( 'Return with a note', 'wpcredits-program-manager' ) );
		printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'The post goes back to the sponsor\'s account as a draft, and your note is sent to its author by email.', 'wpcredits-program-manager' ) );
		printf( '<form class="wpcpm-sponsor-post__form wpcpm-sponsor-post__form--return" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION_POST_RETURN . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_POST_RETURN ) );
		printf( '<input type="hidden" name="wpcpm_post" value="%d" />', (int) $post->ID );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $return, 'sponsor-posts' );
		}

		printf(
			'<label class="screen-reader-text" for="wpcpm-post-note-%1$d">%2$s</label><textarea id="wpcpm-post-note-%1$d" name="wpcpm_note" rows="2" maxlength="%3$d" required placeholder="%4$s">%5$s</textarea>',
			(int) $post->ID,
			esc_html( self::note_label() ),
			// Drawn empty, so the room is the limit: nothing in it is held as an entity.
			(int) WPCPM_Typed_Text::drawn_limit( '', self::MAX_NOTE ),
			esc_attr__( 'What should change before it is published', 'wpcredits-program-manager' ),
			// The parser drops one line feed right after the opening tag, so a kept note that
			// begins with a line break keeps it.
			null === $note ? '' : "\n" . esc_textarea( $note )
		);
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Return it with this note', 'wpcredits-program-manager' ) );
		echo '</form>';
		echo '</details>';

		echo '</div>';
	}

	/**
	 * A sponsor's published guides under its offer in the Tools section, for a viewer the level
	 * admits. Nothing is printed when there is none.
	 *
	 * @param string  $record Sponsor key.
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
			/* translators: %s: the longest note allowed, in characters. */
			'post-note-long'    => array( 'error', sprintf( __( 'The note is longer than %s characters. Shorten it and return the post again.', 'wpcredits-program-manager' ), number_format_i18n( self::MAX_NOTE ) ) ),
			'post-note-loss'    => array( 'error', WPCPM_Typed_Text::loss_message( self::note_label(), 'return' ) ),
			'post-not-pending'  => array( 'error', __( 'Only a post waiting for review can be published or returned here.', 'wpcredits-program-manager' ) ),
			// The Administrator Dashboard's own flash names the status alone, with no detail
			// (leave(), below), so this sentence names no tier: publish_reason() supplies the
			// tier-specific one as the detail the Sponsor Dashboard shows beside it.
			'post-cap'          => array( 'error', __( 'Not published: the sponsor\'s tier includes no more published posts. The tier is on the Sponsors screen.', 'wpcredits-program-manager' ) ),
			'post-failed'       => array( 'error', __( 'The post could not be changed right now. Try again later.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The return note's box, by the words its form labels it with, so the form and the sentence
	 * that refuses a note name it alike.
	 *
	 * @return string
	 */
	private static function note_label() {
		return __( 'A note for the author', 'wpcredits-program-manager' );
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
		// A decision taken on the Administrator Dashboard goes back there, its sentence on that
		// page's channel; every other caller returns to the Sponsor Dashboard's card.
		if ( class_exists( 'WPCPM_Return' ) && WPCPM_Return::DASHBOARD === WPCPM_Request::posted_key( WPCPM_Return::FIELD ) ) {
			WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );
			wp_safe_redirect( WPCPM_Return::url( home_url( '/' ) ) );
			exit;
		}

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

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || ! WPCPM_Sponsors_Index::is_key( $record ) ) {
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
	 * live, so the feeds and the archives file it there; the return note, if any, and a dormant
	 * stamp, if any, are cleared - a post a manager just published is not the daily run's to
	 * revisit.
	 */
	public static function handle_publish() {
		$opened = self::begin( self::ACTION_POST_PUBLISH );
		$post   = $opened['post'];

		// No detail: publish_reason()'s own sentence, if this leaves with it, would all but
		// repeat the flash the Sponsor Dashboard already prints for post-cap from messages()
		// (review finding).
		if ( '' !== self::publish_reason( $opened['record'] ) ) {
			self::leave( 'post-cap', $opened['record'] );
		}

		// Jetpack's mark before the post goes live, which is when Jetpack reads it, so a post a
		// member saved before 1.123.18, which carries none, is not emailed to the subscribers.
		update_post_meta( $post->ID, self::META_NO_SUBSCRIBER_MAIL, 1 );

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
		delete_post_meta( $post->ID, self::META_DORMANT );

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
	 * the post and mailed to the author. Any dormant stamp goes too: a returned post is the
	 * member's draft again, not something the daily run still has a claim on.
	 *
	 * The note is counted as the manager typed it (`WPCPM_Typed_Text::box_length()`, on what
	 * `WPCPM_Request::posted_raw()` read): the entities the cleaner writes for a "<" and the quote
	 * marks and ampersands after it, a reference it writes in core's longer form (`&#9;` as
	 * `&#009;`), and the two bytes of a line break, are as many characters as the person typed
	 * them with, as the box counts them. One over `MAX_NOTE` is refused rather than cut, because a
	 * note cut short reaches the author missing its end. It is kept as the cleaner left it, and
	 * mailed as it was typed.
	 *
	 * A note the cleaner would take words from is refused before anything is written or mailed:
	 * returned, it would reach the author missing them without a word said. That refusal and the
	 * one over `MAX_NOTE` give the form back the note as it was typed
	 * (`WPCPM_Typed_Text::keep_box()`).
	 */
	public static function handle_return() {
		$opened = self::begin( self::ACTION_POST_RETURN );
		$post   = $opened['post'];
		$form   = WPCPM_Typed_Text::box_form( self::TYPED_RETURN, $post->ID );

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		$typed = WPCPM_Request::posted_raw( 'wpcpm_note' );
		$note  = isset( $_POST['wpcpm_note'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['wpcpm_note'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::leave( 'post-note-loss', $opened['record'] );
		}

		if ( '' === $note ) {
			self::leave( 'post-note-missing', $opened['record'] );
		}

		// Counted on what the manager typed, as the box counts it (`WPCPM_Typed_Text::box_length()`):
		// past the loss check, the typing and what the cleaner keeps hold the same words, and only
		// the typing tells an entity typed out from one the cleaner wrote. A note the box took is
		// never refused for what the cleaner made of it.
		$length = WPCPM_Typed_Text::box_length( $typed );

		if ( $length > self::MAX_NOTE ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::leave( 'post-note-long', $opened['record'] );
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

		// Slashed, because post meta unslashes what it is handed and the note is not.
		update_post_meta( $post->ID, self::META_RETURN_NOTE, wp_slash( $note ) );
		update_post_meta( $post->ID, self::META_RETURNED, time() );
		delete_post_meta( $post->ID, self::META_DORMANT );

		// A mail is plain text: the title as WordPress stored it (kses writes each "&" of a
		// member's title as `&amp;`) and the note as the cleaner left it are handed over as stored
		// and read back to what was typed once, as every value is (`WPCPM_Mail_Templates::fill()`),
		// and the subject is marked plain text, so the mail layer keeps a "<" and an "&" in it as
		// they are: "Q&A" arrives as "Q&A".
		$title = (string) $post->post_title;
		$said  = (string) $note;

		WPCPM_Mail::send(
			(int) $post->post_author,
			self::MAIL_RETURNED,
			static function ( WP_User $author ) use ( $title, $said ) {
				return WPCPM_Mail_Templates::mail(
					self::MAIL_RETURNED,
					array(
						'post_title'  => $title,
						'author_name' => $author->display_name,
						'note'        => $said,
						'edit_link'   => admin_url( 'edit.php' ),
					),
					array( 'plain_subject' => true )
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
				// The note's length as the limit counted it, never the note: the audit is about the act.
				'data'     => array(
					'post'        => (int) $post->ID,
					'note_length' => $length,
				),
			)
		);

		self::leave( 'post-returned', $opened['record'] );
	}

	/**
	 * A manager switches posting for a sponsor, from wp-admin. The capability, the nonce keyed
	 * to the record, the policy, then the switch; back to the sponsor's accounts on the Sponsors
	 * screen's Accounts tab, where the switch is drawn, with a flash on the module's own channel,
	 * or to that tab itself for a record the index does not hold, which has no accounts to show.
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
		if ( ! empty( $decision['allowed'] ) && WPCPM_Sponsors_Index::is_key( $record ) && WPCPM_Sponsors_Index::has( $record ) ) {
			self::set_posting( $record, $on, get_current_user_id() );
			$status = $on ? 'posting-on' : 'posting-off';
		}

		$back = WPCPM_Sponsors_Index::has( $record ) ? WPCPM_Sponsors::accounts_view_url( $record ) : admin_url( 'admin.php?page=wpcpm-sponsors&tab=accounts' );

		WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $status );
		wp_safe_redirect( WPCPM_Return::url( $back ) );
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

		return WPCPM_Sponsors_Index::is_key( $record ) ? $record : '';
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
	 * A test sponsor's post answers as not found, for a reader `is_hidden_test_post()` keeps it from
	 * (1.123.20), as its page and its REST route do.
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

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return $data;
		}

		if ( self::is_hidden_test_post( $post ) ) {
			return false;
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
