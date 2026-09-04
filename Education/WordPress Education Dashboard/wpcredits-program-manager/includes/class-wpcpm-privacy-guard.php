<?php
/**
 * Keeps WordPress from listing the people this plugin provisions.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closes the three places WordPress publishes its user list, and keeps the plugin's own records
 * from feeding them.
 *
 * Every account this plugin provisions carries a person's real name as its display name and a
 * login built from their WordPress.org handle or the local part of their email. WordPress will
 * hand both to anybody who asks: `?author=N` for N = 1, 2, 3 ... answers 200 with an archive
 * whose heading is the display name for any account that exists (`WP::handle_404()` will not
 * 404 an author who has no posts), and for an account that has authored one `publish` row of
 * any post type, `redirect_canonical()` first 301s to `/author/<login>/`. The users REST route
 * and the users sitemap are the same list through two more doors.
 *
 * Three answers, all for anyone without `WPCPM_Roles::CAP_MANAGE`:
 *
 * - Author archives are a 404, sent from `template_redirect` at priority 0 so the canonical
 *   redirect, which runs at 10, never gets to send the login-bearing 301.
 * - The `users` sitemap provider is removed.
 * - `/wp/v2/users` is refused, except one's own record, which core screens read.
 *
 * The other half is the records themselves. The plugin's calls, notes, group sessions and
 * audit rows are posts whose author is the person who acted, and they used to be inserted
 * `publish`, which is the one status core reads as "this person has published". They are
 * `private` now, and `maybe_upgrade()` flips the rows written before that, once. Real content,
 * the dashboard pages, Updates posts and handbook pages, stays `publish`.
 */
class WPCPM_Privacy_Guard {

	/**
	 * Where the author-archive guard runs on `template_redirect`.
	 *
	 * Core hooks `redirect_canonical()` on the same action at the default priority of 10, and
	 * that is the function that turns `?author=N` into a 301 to `/author/<login>/`. A guard at
	 * 10 or later answers the request the redirect has already left; at 0 it answers first and
	 * exits, so the redirect never runs. bin/test-privacy-guard.php pins the ordering.
	 */
	const AUTHOR_GUARD_PRIORITY = 0;

	/** Option holding the version of the one-time record flip, so it runs once. */
	const OPT_VERSION = 'wpcpm_privacy_version';

	/**
	 * Bump when a stored record has to be migrated.
	 *
	 * 1: calls, notes, group sessions and audit rows moved from `publish` to `private`.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Stand-in for ending the request. Null means `exit`. See for_tests().
	 *
	 * @var callable|null
	 */
	private static $terminate = null;

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'deny_author_archives' ), self::AUTHOR_GUARD_PRIORITY );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'drop_users_sitemap' ), 10, 2 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'refuse_user_routes' ), 10, 3 );

		// Same moment as the roles and settings upgrades, and before anything reads the
		// records: the modules' queries ask for `private` rows from this version on.
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
	}

	/**
	 * The plugin's post types whose rows are private records about named people.
	 *
	 * Calls and group sessions share one type, mentor and institution notes another. These are
	 * the types `maybe_upgrade()` flips and bin/test-privacy-guard.php holds to `private`.
	 *
	 * @return string[]
	 */
	public static function private_post_types() {
		return array(
			WPCPM_Mentor_Calls::POST_TYPE,
			WPCPM_Mentor_Notes::POST_TYPE,
			WPCPM_Institution_Audit::POST_TYPE,
		);
	}

	/**
	 * Answer an author archive with a 404 for anyone who does not run the program.
	 *
	 * Runs before `redirect_canonical()` (see AUTHOR_GUARD_PRIORITY). Feeds of an author
	 * archive are archives too and get the same answer.
	 */
	public static function deny_author_archives() {
		if ( ! is_author() || current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		self::not_found();
	}

	/**
	 * Keep the users sitemap out of `wp-sitemap.xml`.
	 *
	 * Core only lists users with published posts of a public type, which this plugin's
	 * records are not; the provider goes anyway, because the next public type with an author
	 * would put the list back.
	 *
	 * @param WP_Sitemaps_Provider|mixed $provider Provider being registered.
	 * @param string                     $name     Its name.
	 * @return WP_Sitemaps_Provider|false False drops the provider.
	 */
	public static function drop_users_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Refuse the users REST routes to anyone who does not run the program.
	 *
	 * One's own record stays reachable: `/wp/v2/users/me` and `/wp/v2/users/<own id>` (and the
	 * application-password routes under them) are what the profile screen and the block editor
	 * read for the person using them, and they disclose nobody else. Everything else under
	 * `/wp/v2/users` is the site's user list, which is a list of program participants here.
	 *
	 * @param mixed           $result  Response to return instead of dispatching. Null to carry on.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request being dispatched.
	 * @return mixed
	 */
	public static function refuse_user_routes( $result, $server, $request ) {
		unset( $server );

		if ( null !== $result || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}

		$route = (string) $request->get_route();

		if ( ! preg_match( '#^/wp/v2/users(?:/(me|\d+))?(?:/|$)#', $route, $m ) ) {
			return $result;
		}

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return $result;
		}

		if ( isset( $m[1] ) && is_user_logged_in() ) {
			if ( 'me' === $m[1] || get_current_user_id() === (int) $m[1] ) {
				return $result;
			}
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to list users on this site.', 'wpcredits-program-manager' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Move the private records written as `publish` to `private`, once.
	 *
	 * In the shape of `WPCPM_Roles::maybe_upgrade()`, so a site updated by dropping in new
	 * files is covered. Done with one UPDATE per type rather than `wp_update_post()` per row:
	 * that would run the save hooks, re-slug and kses-filter several hundred notes and calls
	 * inside one request, and every one of those side effects is unwanted for a status flip.
	 * The post cache is cleared by ID afterwards so nothing keeps serving the old status.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_VERSION ) >= self::SCHEMA_VERSION ) {
			return;
		}

		foreach ( self::private_post_types() as $post_type ) {
			self::make_private( $post_type );
		}

		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
	}

	/**
	 * Replace what ends the request. Test-only.
	 *
	 * The 404 answer ends the request, and a suite cannot assert anything after `exit`.
	 * Nothing in the plugin calls this; bin/test-privacy-guard.php does, with a callable that
	 * throws instead.
	 *
	 * @param callable|null $terminate Called in place of `exit`, or null for the real thing.
	 */
	public static function for_tests( $terminate = null ) {
		self::$terminate = $terminate;
	}

	/**
	 * Every `publish` row of one type becomes `private`.
	 *
	 * @param string $post_type Post type.
	 * @return int Rows changed.
	 */
	private static function make_private( $post_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-time flip over every row of the type; see maybe_upgrade().
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $post_type ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
		$changed = $wpdb->update(
			$wpdb->posts,
			array( 'post_status' => 'private' ),
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);

		foreach ( $ids as $id ) {
			clean_post_cache( (int) $id );
		}

		return false === $changed ? 0 : (int) $changed;
	}

	/**
	 * Send the 404 and end the request.
	 *
	 * The main query is marked, the status and no-cache headers are sent, and the theme's 404
	 * template is loaded through the same filter core's template loader applies, so a theme or
	 * plugin that swaps templates sees the request it expects.
	 */
	private static function not_found() {
		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();

		// Core's own hook, re-applied on purpose: the 404 template goes through the same filter
		// the template loader would have run, so a theme that swaps templates still gets its say.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$template = apply_filters( 'template_include', get_404_template() );

		if ( is_string( $template ) && '' !== $template && file_exists( $template ) ) {
			include $template;
		}

		if ( null !== self::$terminate ) {
			call_user_func( self::$terminate );

			return;
		}

		exit;
	}
}
