<?php
/**
 * The Partners page, `/partners/`: the program lead's partner page, where the program's sponsors
 * are listed as its partners, in one list in alphabetical order. Created in draft, published by
 * the launch switch, put back to draft when the switch goes off and kept a draft whoever saves it
 * while the switch is off, so nothing about the tiers is public before the program says so (spec
 * section 11).
 *
 * The theme prints the partner page's own text around the page's content, through its
 * page-partners.html template (1.123.18): the levels, their amounts and what each includes, and
 * the ways to apply. The content the plugin writes is the list's heading, one sentence and the
 * list. It is written once, at creation, and never again by the plugin: the page is a program
 * manager's to edit from then on, like every page the plugin creates.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page.
 */
final class WPCPM_Sponsors_Page {

	const OPT_PAGE = 'wpcpm_sponsors_page_id';

	/** The page's slug, which the theme's page-partners.html template is chosen by. */
	const SLUG = 'partners';

	/**
	 * Hooks: the page exists before the switch can publish it; the switch follows the settings
	 * option; no save takes the page out of draft while the switch is off; a visitor is refused
	 * the page while the switch is off.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_ensure' ), 6 );
		add_action( 'update_option_' . WPCPM_Settings::OPT_NAME, array( __CLASS__, 'follow_switch' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'keep_draft' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'guard' ) );
	}

	/**
	 * Create the page when there is none, a trashed one counting as none, on a program manager's
	 * admin requests only, and put it where the switch says: a page trashed after the launch
	 * comes back published.
	 *
	 * The activation hook does not fire on an in-place update, so a site updated in place would
	 * never get the page from `activate()` alone; an admin request is where the switch is
	 * flipped, so the page is made there, at the cost of one autoloaded option read and, once
	 * the page exists, a read of its status.
	 */
	public static function maybe_ensure() {
		// A manager's request alone: `is_admin()` is true for admin-post.php and admin-ajax.php
		// too, where the public application form and every signed-in sponsor, student and mentor
		// form post, so the page would be made by whoever posted a form next, as its author. The
		// page is a manager's to edit, and `follow_switch()` makes it as the manager who flips
		// the switch anyway.
		if ( ! is_admin() || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) || self::page_id() > 0 ) {
			return;
		}

		if ( self::ensure_page() ) {
			self::set_published( WPCPM_Sponsor_Tiers::live() );
		}
	}

	/**
	 * The recorded page, or 0 when there is none or it is in the trash.
	 *
	 * @return int
	 */
	public static function page_id() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id ) {
			return 0;
		}

		$status = get_post_status( $page_id );

		return $status && 'trash' !== $status ? $page_id : 0;
	}

	/**
	 * The page: the stored one, else the page at the slug, else a new draft.
	 *
	 * Pages alone are looked up at the slug. Asked for one type, core's lookup searches
	 * attachments too and answers a file uploaded as "partners" and never attached when no page
	 * matches, a file core counts as published: adopted, `page_url()` would answer the file's
	 * address for good and no page would ever be made.
	 *
	 * @return int The page ID, or 0.
	 */
	public static function ensure_page() {
		$page_id = self::page_id();

		if ( $page_id ) {
			return $page_id;
		}

		$existing = get_page_by_path( self::SLUG, OBJECT, array( 'page' ) );

		if ( $existing instanceof WP_Post && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
			update_option( self::OPT_PAGE, (int) $existing->ID, true );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => self::title(),
				'post_name'    => self::SLUG,
				'post_content' => self::content(),
			),
			true
		);

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, true );

		return (int) $page_id;
	}

	/**
	 * The page's title.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Partners', 'wpcredits-program-manager' );
	}

	/**
	 * The content the page is created with: the heading "Our partners", one sentence that they are
	 * listed in alphabetical order, and the full listing.
	 *
	 * The theme's page-partners.html template prints the rest of the partner page around this
	 * content: the levels with their amounts and what each includes, the products partners may
	 * offer, and the ways to apply. None of it is the plugin's to write here, so a manager edits it
	 * in the theme's template, and the plugin's text can never say a level differently from it.
	 *
	 * The heading opens the content at h2: the page's title is its h1 and the listing's upcoming
	 * events sit under an h3, so without it the outline would step from the title straight to them.
	 *
	 * @return string Block markup.
	 */
	public static function content() {
		$out  = '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Our partners', 'wpcredits-program-manager' ) . '</h2><!-- /wp:heading -->' . "\n\n";
		$out .= self::paragraph( __( 'Our partners are listed in alphabetical order.', 'wpcredits-program-manager' ) );
		$out .= '<!-- wp:wpcpm/sponsors {"variant":"full","align":"wide"} /-->' . "\n";

		return $out;
	}

	/**
	 * One paragraph block.
	 *
	 * @param string $text The sentence(s).
	 * @return string
	 */
	private static function paragraph( $text ) {
		return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->' . "\n\n";
	}

	/**
	 * The switch, followed: on publishes the page, off returns it to draft. Runs on the
	 * settings option's update action, which fires only when the stored value changed.
	 *
	 * @param mixed $before The settings before the save.
	 * @param mixed $after  The settings after it.
	 */
	public static function follow_switch( $before, $after ) {
		$was = is_array( $before ) && ! empty( $before[ WPCPM_Sponsor_Tiers::OPT_LIVE ] );
		$is  = is_array( $after ) && ! empty( $after[ WPCPM_Sponsor_Tiers::OPT_LIVE ] );

		if ( $was === $is ) {
			return;
		}

		self::set_published( $is );
	}

	/**
	 * While the switch is off, a save of the page lands as a draft whatever status it asked for,
	 * the trash apart: the block editor's, Quick Edit's, a schedule and a restore from the trash
	 * all pass through `wp_insert_post()`, so none of them makes the tier copy public before the
	 * launch. Turning the switch on is unaffected: the settings option's update action fires after
	 * the option is written, so `live()` already reads on when `set_published()` publishes.
	 *
	 * Known by the recorded ID, compared before anything else is read: this runs on every save of
	 * every post on the site. A page in the trash keeps that ID until an admin request makes
	 * another, so a restore of it straight to a public status in the meantime lands as a draft too.
	 *
	 * @param array $data    The post's fields, about to be written.
	 * @param array $postarr The fields the save was given, the post's ID among them on an update.
	 * @return array
	 */
	public static function keep_draft( $data, $postarr ) {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || ! isset( $postarr['ID'] ) || (int) $postarr['ID'] !== $page_id ) {
			return $data;
		}

		if ( ! isset( $data['post_status'] ) || in_array( $data['post_status'], array( 'draft', 'trash', 'auto-draft' ), true ) || WPCPM_Sponsor_Tiers::live() ) {
			return $data;
		}

		$data['post_status'] = 'draft';

		return $data;
	}

	/**
	 * Publish the page, making it first when there is none, or return it to draft. With no page,
	 * a trashed one counting as none, there is nothing to return to draft, so the switch turned
	 * off makes no page.
	 *
	 * @param bool $on True to publish.
	 * @return bool Whether the page ends where the switch puts it: published when on; when off,
	 *              neither public nor scheduled, which is so when there is no page at all.
	 */
	public static function set_published( $on ) {
		if ( ! $on && 0 === self::page_id() ) {
			return true;
		}

		$page_id = self::ensure_page();

		if ( ! $page_id ) {
			return false;
		}

		$status = get_post_status( $page_id );
		$want   = $on ? 'publish' : 'draft';

		if ( $want === $status ) {
			return true;
		}

		// Off takes back what is public or about to be: a published page, and a scheduled one, a
		// publish waiting to happen. Private and pending are neither, and a manager chose them.
		if ( ! $on && ! in_array( $status, array( 'publish', 'future' ), true ) ) {
			return true;
		}

		$result = wp_update_post(
			array(
				'ID'          => $page_id,
				'post_status' => $want,
			),
			true
		);

		return ! is_wp_error( $result ) && get_post_status( $page_id ) === $want;
	}

	/**
	 * The page's address, or '' while there is no published page.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = self::page_id();

		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * While the switch is off, the page's own view is a 404 to everyone but a program manager.
	 *
	 * The belt: `keep_draft()` keeps the page a draft while the switch is off and `follow_switch()`
	 * returns it to draft when the switch goes off, so this answers only for a page published while
	 * the switch reads off all the same, the settings turned off beneath it by a write that fired
	 * no update action (a database restored from a backup, a direct query), or the page published
	 * by a write outside `wp_insert_post()`, which no code path of the plugin makes. It covers the
	 * page's own view and nothing more: a page published that way would still be served by the
	 * REST API's pages endpoint, the site's search, the sitemap and the oEmbed endpoint, none of
	 * which is that view. The tier copy is content, and content is what a page cache serves, so
	 * the refusal is a 404, never the page, sent with the headers that keep the refusal itself out
	 * of the cache once the switch goes on. The embed view goes with it: the template loader asks
	 * for an embed before it asks for a 404, and the embed would print the page's title and
	 * excerpt.
	 *
	 * Every front-end request passes through here, so whether this one is the page is asked
	 * before any post is read: the option is autoloaded, and a request that is not the page costs
	 * nothing more. The page's status comes after, a page in the trash counting as none, as
	 * `page_id()` counts it.
	 */
	public static function guard() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}

		if ( 0 === self::page_id() || WPCPM_Sponsor_Tiers::live() || current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		$wp_query->is_embed = false;
		status_header( 404 );
		nocache_headers();
	}
}
