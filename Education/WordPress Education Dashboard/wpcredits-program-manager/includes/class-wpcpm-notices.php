<?php
/**
 * A notice at the top of the page, addressed to one audience.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a program manager's notice to the people it is for, and nobody else.
 *
 * One notice per audience — students, mentors, institutions, administrators. An empty
 * notice is simply off, so there is no separate switch to forget to turn back on.
 *
 * **A person can be in more than one audience, and sees every notice that applies.** An
 * administrator who also mentors gets both, in that order. The alternative — picking the
 * "most specific" audience — would silently withhold a notice from exactly the people who
 * hold two roles, which is the group most likely to need it.
 *
 * **Each notice is a piece of markup in one option, edited in the classic editor.** A notice
 * is a paragraph or two with a link in it. Holding four of those in an option and editing
 * them where the rest of the plugin's settings are edited keeps the whole feature on one
 * screen; the block editor briefly used instead had to be a post type to exist at all, which
 * bought revisions and autosave for content that does not need either, and moved editing off
 * to a screen with no way back to the tool.
 */
class WPCPM_Notices {

	/** Option holding audience slug → notice markup. */
	const OPTION = 'wpcpm_notices';

	/** Records which recovery revision this site has been brought up to. */
	const OPT_PLAIN = 'wpcpm_notices_plain';

	/**
	 * The current recovery revision. Bump this to run the recovery again.
	 *
	 * A counter rather than a boolean, for the reason the page-title migration found out the
	 * hard way: a flag records only *that* it ran, so a site it did not fully recover can
	 * never be revisited. Revision 2 adds the fallback to the old settings keys, which
	 * revision 1 ignored — and which on at least one site were the only surviving copy.
	 */
	const MIGRATION_VERSION = 2;

	/**
	 * The post type notices briefly used, kept for the one-time move back off it.
	 *
	 * Not registered any more. Named here so the migration below and `uninstall.php` can
	 * still find what that version left behind.
	 */
	const POST_TYPE = 'wpcpm_notice';

	/** Post meta the old posts carried, naming the audience. */
	const META_AUDIENCE = '_wpcpm_notice_audience';

	/** Option flag the post-backed version set. Read by the migration, deleted on uninstall. */
	const OPT_MIGRATED = 'wpcpm_notices_migrated';

	const STYLE = 'wpcpm-notices';

	/**
	 * Audience slug → label, in the order notices are shown.
	 *
	 * Administrators last on purpose. Somebody who is both an administrator and a mentor is
	 * reading as a mentor most of the time, and the notice for the work they are doing
	 * should come before the one about running the program.
	 *
	 * @return array<string, string>
	 */
	public static function audiences() {
		return array(
			'student'     => __( 'Students', 'wpcredits-program-manager' ),
			'mentor'      => __( 'Mentors', 'wpcredits-program-manager' ),
			'institution' => __( 'Institutions', 'wpcredits-program-manager' ),
			'sponsor'     => __( 'Sponsors', 'wpcredits-program-manager' ),
			'admin'       => __( 'Administrators', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		// On `init` rather than only in the admin: the front end reads the option, so a site
		// whose notices are still in the old posts would show nothing at all until somebody
		// happened to open the tool screen.
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ) );

		/**
		 * Filter whether the plugin places the notices itself.
		 *
		 * Return false to suppress the automatic output and call
		 * `WPCPM_Notices::render()` from a theme instead.
		 *
		 * @param bool $auto Whether to place the notices.
		 */
		if ( apply_filters( 'wpcpm_notices_auto_render', true ) ) {
			// Prepended to the content rather than printed on `wp_body_open`. On
			// `wp_body_open` a notice lands above the site header — outside the page, over
			// the chrome — where what a reader wants is a notice at the top of the page they
			// are actually on. `the_content` puts it inside the content area, which in this
			// program's theme is the top of the dashboard card.
			add_filter( 'the_content', array( __CLASS__, 'prepend' ), 5 );
		}

		// The stylesheet has to be decided at enqueue time, before any content filter has
		// run, so it is registered always and enqueued only when a notice will appear.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Bring any notice written on the post-backed version into the option. Runs once.
	 *
	 * `do_blocks()` is applied here rather than at render time: a notice written in the block
	 * editor is block markup, and this is the last moment anything knows that. Converting it
	 * on the way in means the stored value is plain HTML — which is what the classic editor
	 * expects to be handed, and what everything downstream now assumes.
	 *
	 * The posts are left in place. They are invisible with the type unregistered, and
	 * deleting somebody's only copy of their own words as a side effect of an upgrade is not
	 * this function's business; `uninstall.php` removes them with everything else.
	 */
	public static function maybe_migrate() {
		if ( (int) get_option( self::OPT_PLAIN ) >= self::MIGRATION_VERSION ) {
			return;
		}

		update_option( self::OPT_PLAIN, self::MIGRATION_VERSION, true );

		$settings = WPCPM_Settings::get();
		$stored   = self::bodies();

		foreach ( array_keys( self::audiences() ) as $slug ) {
			// Anything written since the upgrade wins over every older copy. This is what
			// makes the recovery safe to re-run: a notice somebody has rewritten is never
			// reverted, and one they deliberately emptied is only refilled if an older copy
			// still exists — which, for a notice that has been through the new editor, it
			// does not.
			if ( isset( $stored[ $slug ] ) && '' !== $stored[ $slug ] ) {
				continue;
			}

			// Newest surviving copy first: the post the block-editor version wrote, then the
			// settings key that predates all of this. Revision 1 read only the post, so a
			// site whose posts were created empty — because the post-backed migration set its
			// own flag before the content was in place — lost sight of text that was sitting
			// in the settings option the whole time.
			$body = self::legacy_body( $slug );

			if ( '' === $body ) {
				$key  = 'notice_' . $slug;
				$body = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
			}

			$body = trim( $body );

			if ( '' !== $body ) {
				$stored[ $slug ] = wp_kses_post( do_blocks( $body ) );
			}
		}

		update_option( self::OPTION, $stored, true );
	}

	/**
	 * The content of the old notice post for one audience.
	 *
	 * Queried with `WP_Query` directly because the post type is no longer registered, so the
	 * usual helpers have no type object to work from.
	 *
	 * @param string $slug Audience slug.
	 * @return string
	 */
	private static function legacy_body( $slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The post type is unregistered, so WP_Query has no type object; runs once per site.
		$body = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.post_content FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.post_type = %s AND m.meta_key = %s AND m.meta_value = %s
				 ORDER BY p.ID ASC LIMIT 1",
				self::POST_TYPE,
				self::META_AUDIENCE,
				(string) $slug
			)
		);

		return null === $body ? '' : trim( (string) $body );
	}

	/**
	 * Every stored notice, keyed by audience.
	 *
	 * @return array<string, string>
	 */
	public static function bodies() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$out = array();

		// Keyed off the audience list rather than the option, so a slug that is no longer an
		// audience cannot be resurrected by editing the database.
		foreach ( array_keys( self::audiences() ) as $slug ) {
			$out[ $slug ] = isset( $stored[ $slug ] ) ? trim( (string) $stored[ $slug ] ) : '';
		}

		return $out;
	}

	/**
	 * One stored notice, as written.
	 *
	 * @param string $slug Audience slug.
	 * @return string
	 */
	public static function body( $slug ) {
		$bodies = self::bodies();

		return isset( $bodies[ $slug ] ) ? $bodies[ $slug ] : '';
	}

	/**
	 * Replace the stored notices.
	 *
	 * Each body is filtered through `wp_kses_post()` here, so what is stored is already safe
	 * — the same filter runs again at render time, because a value that reaches the database
	 * some other way must not be trusted either.
	 *
	 * @param array<string, string> $input Audience slug → markup, unslashed.
	 */
	public static function save( array $input ) {
		$out = array();

		foreach ( array_keys( self::audiences() ) as $slug ) {
			$body = isset( $input[ $slug ] ) ? (string) $input[ $slug ] : '';

			$out[ $slug ] = trim( wp_kses_post( $body ) );
		}

		update_option( self::OPTION, $out, true );
	}

	/**
	 * Register the stylesheet, and load it only when there is a notice to dress.
	 */
	public static function enqueue() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/notices.css',
				array(),
				WPCPM_VERSION
			);
		}

		if ( ! empty( self::current() ) ) {
			wp_enqueue_style( self::STYLE );
		}
	}

	/**
	 * Whether a user belongs to one audience.
	 *
	 * Deliberately delegating: `is_student()` and `is_mentor()` each count somebody matched
	 * to an Airtable record without holding the role, which is how an administrator who
	 * mentors is recognised — the sync never gives an administrator the Mentor role.
	 * Testing roles here instead would miss exactly those people.
	 *
	 * @param string           $slug Audience slug.
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function applies_to( $slug, $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		switch ( $slug ) {
			case 'student':
				return WPCPM_Students_Dashboard::is_student( $user );

			case 'mentor':
				return WPCPM_Mentors_Dashboard::is_mentor( $user );

			case 'institution':
				return WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_INSTITUTION );

			case 'sponsor':
				return WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_SPONSOR );

			case 'admin':
				return user_can( $user->ID, WPCPM_Roles::CAP_MANAGE );
		}

		return false;
	}

	/**
	 * The notices to show the current user, in audience order.
	 *
	 * @return array<string, string> Audience slug → rendered notice HTML.
	 */
	public static function current() {
		// Asked twice per request — once to decide whether the stylesheet is needed, once to
		// render — and each call runs four audience checks.
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$out = array();

		// Nobody is in an audience while logged out.
		if ( ! is_user_logged_in() ) {
			$cached = $out;

			return $cached;
		}

		foreach ( self::bodies() as $slug => $body ) {
			if ( '' === $body || ! self::applies_to( $slug ) ) {
				continue;
			}

			// `wpautop()` for the case where somebody used the Text tab and typed bare lines;
			// `wp_kses_post()` after it, because that is the last thing to touch the markup
			// before it goes on the page.
			$out[ $slug ] = wp_kses_post( wpautop( $body ) );
		}

		/**
		 * Filter the notices about to be shown.
		 *
		 * @param array<string, string> $out Audience slug → rendered HTML.
		 */
		$cached = (array) apply_filters( 'wpcpm_current_notices', $out );

		return $cached;
	}

	/**
	 * Put the notices at the top of the page's content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function prepend( $content ) {
		static $done = false;

		// The main query's singular post, once. `the_content` also runs for excerpts, other
		// loops, feeds and REST responses, none of which is the top of the page.
		if ( $done || ! is_singular() || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		$markup = self::markup();

		if ( '' === $markup ) {
			return $content;
		}

		$done = true;

		return $markup . $content;
	}

	/**
	 * The notices as HTML, or an empty string.
	 *
	 * @return string
	 */
	public static function markup() {
		$notices = self::current();

		if ( empty( $notices ) ) {
			return '';
		}

		$out = '<div class="wpcpm-notices" role="region" aria-label="'
			. esc_attr__( 'Program notices', 'wpcredits-program-manager' ) . '">';

		foreach ( $notices as $slug => $body ) {
			$out .= sprintf(
				'<div class="wpcpm-notices__item wpcpm-notices__item--%1$s">%2$s</div>',
				esc_attr( $slug ),
				$body
			);
		}

		return $out . '</div>';
	}

	/**
	 * Print the notices, for a theme placing them itself.
	 */
	public static function render() {
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by markup(), which filters each body through wp_kses_post().
	}

	/**
	 * Delete the notices, and anything the post-backed version left. Called on uninstall.
	 */
	public static function delete_all() {
		delete_option( self::OPTION );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The post type is unregistered, so WP_Query has no type object; uninstall only.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", self::POST_TYPE ) );

		foreach ( $ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}
