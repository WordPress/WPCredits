<?php
/**
 * The program's updates in the Administrator Dashboard's frame (1.123.71).
 *
 * The owner, 11 November 2026, of the updates listed on the Reference view: "Move to sidebar, posts should
 * open like the Administrator guide, on the right sidebar should be a list of other/previous posts". The
 * menu's Program updates opens the newest; each update an Administrator reads, a post the Administrator
 * Dashboard's own list holds (`WPCPM_Updates::lists( $post, 'administrator' )`: the Updates category, public
 * or at the Administrators level, published or private), is drawn in the frame: the dashboard's menu with
 * Program updates marked, the post's title as the page's one H1 and its date, the post, and beside it the
 * list of the updates, newest first, the one being read marked.
 *
 * Only for an Administrator, in a theme that draws the post as a bare canvas, naming it in
 * `add_theme_support( 'wpcpm-dashboard-frame', array( ..., 'administrator-updates' ) )` and asking
 * `draws()` which posts to: a public update is read by everybody, and anybody else gets the post as the
 * theme draws it. The layout is the Administrator guide's, its stylesheet and its script.
 *
 * Since 1.123.78 an update somebody who acts for a sponsor reads, one the Sponsor Dashboard's list holds
 * (`WPCPM_Updates::lists( $post, 'sponsor' )`), is drawn the same way in the Sponsor Dashboard's frame, where the
 * theme names `sponsor-updates` (`audience_of()`). An Administrator always reads in the Administrator
 * Dashboard's frame.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A program update's post in the frame.
 */
class WPCPM_Administrator_Updates_View {

	/** The page's key in the theme's list and after `--` in the frame's class. */
	const FRAME_PAGE = 'administrator-updates';

	/** How many updates the list beside the post holds. */
	const LIST_MAX = 20;

	/**
	 * Whether an update has been drawn on this request: the frame is drawn once.
	 *
	 * @var bool
	 */
	private static $drawn = false;

	/**
	 * Hooks. Called from `WPCPM_Administrators::boot()`.
	 */
	public static function init() {
		// After the access check (5), the blocks (9), the paragraphs (10) and the shortcodes (11).
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 99 );
	}

	/**
	 * Whether the post is drawn in a frame for this reader. The theme asks it too, to know which posts to draw
	 * as a bare canvas.
	 *
	 * @param mixed $post A post, or anything else.
	 * @return bool
	 */
	public static function draws( $post ) {
		return '' !== self::audience_of( $post );
	}

	/**
	 * Whose frame the post is drawn in for this reader (1.123.78): `administrator` for an update the
	 * Administrator Dashboard lists, read by an Administrator; `sponsor` for one the Sponsor Dashboard lists,
	 * read by somebody who acts for a sponsor and is no Administrator; '' for anything else, or where the
	 * theme does not name that frame's updates.
	 *
	 * @param mixed $post A post, or anything else.
	 * @return string
	 */
	public static function audience_of( $post ) {
		if ( ! $post instanceof WP_Post || ! is_user_logged_in() ) {
			return '';
		}

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return WPCPM_Dashboard_Frame::owns_page( self::FRAME_PAGE ) && WPCPM_Updates::lists( $post, 'administrator' ) ? 'administrator' : '';
		}

		if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) && class_exists( 'WPCPM_Sponsor_Members' ) && WPCPM_Sponsor_Members::is_member()
			&& WPCPM_Dashboard_Frame::owns_page( WPCPM_Sponsors_Dashboard::FRAME_PAGE . '-updates' ) && WPCPM_Updates::lists( $post, 'sponsor' ) ) {
			return 'sponsor';
		}

		return '';
	}

	/**
	 * Whether this call of `the_content` is an update being drawn in the frame.
	 *
	 * @return bool
	 */
	private static function applies() {
		if ( self::$drawn || is_admin() || is_feed() || doing_filter( 'get_the_excerpt' ) ) {
			return false;
		}

		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}

		// The post the address asked for, not one a Query Loop inside it draws: the loop sets the global
		// post and leaves the main query as it was (the release's review, 1.123.71).
		$post = get_post();

		return $post instanceof WP_Post && (int) get_queried_object_id() === (int) $post->ID && self::draws( $post );
	}

	/**
	 * The update's content, in the frame.
	 *
	 * @param string $content The post's content, rendered.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( ! self::applies() ) {
			return $content;
		}

		self::$drawn = true;

		$post = get_post();

		return self::render( (string) $content, $post, self::audience_of( $post ) );
	}

	/**
	 * The frame, the head, the list of updates and the post.
	 *
	 * @param string  $content  The post, rendered.
	 * @param WP_Post $post     The post.
	 * @param string  $audience `administrator` or `sponsor`: whose frame, menu and list.
	 * @return string
	 */
	public static function render( $content, WP_Post $post, $audience = 'administrator' ) {
		$audience = 'sponsor' === $audience ? 'sponsor' : 'administrator';
		$menu     = 'sponsor' === $audience
			? WPCPM_Sponsors_Dashboard::menu_args( WPCPM_Sponsors_Dashboard::VIEW_UPDATES )
			: WPCPM_Administrators_Dashboard::menu_args( WPCPM_Administrators_Dashboard::VIEW_UPDATES );

		WPCPM_Administrator_Guide_View::register_assets();
		wp_enqueue_style( WPCPM_Administrator_Guide_View::STYLE );
		wp_enqueue_script( WPCPM_Administrator_Guide_View::SCRIPT );

		ob_start();

		WPCPM_Dashboard_Frame::open( $menu );

		printf( '<div class="wpcpm-guide wpcpm-guide--updates" data-wpcpm-table-label="%s">', esc_attr__( 'Table, scrolls sideways', 'wpcredits-program-manager' ) );

		// The post's own title, not get_the_title(): core prefixes a private post's with "Private:", and
		// every update here is one an Administrator may read.
		printf(
			'<header class="wpcpm-head wpcpm-guide__head"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__title">%1$s</h1><p class="wpcpm-head__facts"><time datetime="%2$s">%3$s</time></p></div></header>',
			esc_html( '' !== trim( $post->post_title ) ? $post->post_title : __( 'Program update', 'wpcredits-program-manager' ) ),
			esc_attr( (string) get_post_time( 'c', true, $post ) ),
			esc_html( (string) get_the_date( '', $post ) )
		);

		self::others( $post, $audience );

		echo '<div class="wpcpm-guide__body">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The post's own content, filtered by the_content.

		echo '</div>';

		WPCPM_Dashboard_Frame::close();

		return (string) ob_get_clean();
	}

	/**
	 * The list of updates, newest first, the one being read marked. Its heading folds it on a narrow main
	 * area, as the guide's does; a list of the one post alone is not drawn.
	 *
	 * @param WP_Post $current  The post being read.
	 * @param string  $audience `administrator` or `sponsor`: whose list.
	 */
	private static function others( WP_Post $current, $audience = 'administrator' ) {
		$posts = WPCPM_Updates::posts( self::LIST_MAX, 'sponsor' === $audience ? 'sponsor' : 'administrator' );

		// The update being read is always in the list: one older than the newest twenty takes the last
		// place, so the list keeps its length and its order (the release's review, 1.123.71).
		if ( ! in_array( (int) $current->ID, array_map( 'intval', wp_list_pluck( $posts, 'ID' ) ), true ) ) {
			$posts   = array_slice( $posts, 0, self::LIST_MAX - 1 );
			$posts[] = $current;
		}

		if ( count( $posts ) < 2 ) {
			return;
		}

		$label = __( 'Program updates', 'wpcredits-program-manager' );

		echo '<nav class="wpcpm-guide__contents" aria-labelledby="wpcpm-guide-contents-title" data-wpcpm-guide-contents>';

		printf(
			'<h2 class="wpcpm-guide__contents-title" id="wpcpm-guide-contents-title"><span class="wpcpm-guide__contents-label">%1$s</span><button class="wpcpm-guide__contents-toggle" type="button" aria-expanded="false" aria-controls="%2$s" hidden>%1$s%3$s</button></h2>',
			esc_html( $label ),
			esc_attr( WPCPM_Administrator_Guide_View::LIST_ID ),
			WPCPM_Icons::svg( 'chevron-down', 16 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
		);

		printf( '<ul class="wpcpm-guide__list" id="%s">', esc_attr( WPCPM_Administrator_Guide_View::LIST_ID ) );

		foreach ( $posts as $post ) {
			printf(
				'<li class="wpcpm-guide__entry"><a class="wpcpm-guide__link wpcpm-guide__link--post" href="%1$s"%2$s>%3$s<span class="wpcpm-guide__date">%4$s</span></a></li>',
				esc_url( (string) get_permalink( $post ) ),
				(int) $post->ID === (int) $current->ID ? ' aria-current="page"' : '',
				esc_html( $post->post_title ),
				esc_html( (string) get_the_date( '', $post ) )
			);
		}

		echo '</ul></nav>';
	}
}
