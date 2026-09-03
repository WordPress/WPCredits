<?php
/**
 * Helpers shared by the blocks, the patterns and the dashboard skin.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the WPCredits Program Manager plugin is active.
 *
 * Everything the theme adds around the mentor page is guarded on this. A theme
 * that assumes its companion plugin is present breaks the site the moment the
 * plugin is deactivated — which is exactly when somebody is debugging.
 *
 * @return bool
 */
function wpcredits_plugin_active() {
	return class_exists( 'WPCPM_Mentors_Dashboard' );
}

/**
 * The mentor page's permalink.
 *
 * @return string Empty string when the plugin is inactive or the page is gone.
 */
function wpcredits_mentor_page_url() {
	if ( ! wpcredits_plugin_active() ) {
		return '';
	}

	return (string) WPCPM_Mentors_Dashboard::page_url();
}

/**
 * Whether this request is the student program page.
 *
 * Matched the same three ways as the mentor page — the page the plugin creates,
 * the block on any page, or the shortcode — and guarded on the class so a plugin
 * older than the Students module does not fatal.
 *
 * @return bool
 */
function wpcredits_is_student_page() {
	if ( ! wpcredits_plugin_active() || ! is_singular() || ! class_exists( 'WPCPM_Students_Dashboard' ) ) {
		return false;
	}

	$page_id = (int) get_option( WPCPM_Students_Dashboard::OPT_PAGE );

	if ( $page_id && get_queried_object_id() === $page_id ) {
		return true;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return has_block( 'wpcpm/student-dashboard', $post )
		|| has_shortcode( (string) $post->post_content, 'wpcpm_student_dashboard' );
}

/**
 * Whether this request is the institution dashboard.
 *
 * Matched the same three ways as the other two, and guarded on the class so a
 * plugin older than the Institutions module does not fatal.
 *
 * @return bool
 */
function wpcredits_is_institution_page() {
	if ( ! wpcredits_plugin_active() || ! is_singular() || ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
		return false;
	}

	$page_id = (int) get_option( WPCPM_Institutions_Dashboard::OPT_PAGE );

	if ( $page_id && get_queried_object_id() === $page_id ) {
		return true;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return has_block( WPCPM_Institutions_Dashboard::BLOCK, $post )
		|| has_shortcode( (string) $post->post_content, WPCPM_Institutions_Dashboard::SHORTCODE );
}

/**
 * Whether this request is any of the dashboards.
 *
 * The three pages share a shell, so anything that dresses that shell — the card,
 * the insets, the type — applies to all of them.
 *
 * **The institution page was missing from this for two releases**, which is why it
 * did not look like the other two however much its own stylesheet was worked on:
 * the skin below is what gives a dashboard its card, its measure and its type, and
 * none of it was loading there. Anything added here has to be added to the body
 * class in functions.php as well, since every rule in the skin is prefixed with it.
 *
 * @return bool
 */
function wpcredits_is_dashboard_page() {
	return wpcredits_is_mentor_page() || wpcredits_is_student_page() || wpcredits_is_institution_page();
}

/**
 * The student page's URL, or an empty string.
 *
 * @return string
 */
function wpcredits_student_page_url() {
	if ( ! wpcredits_plugin_active() || ! class_exists( 'WPCPM_Students_Dashboard' ) ) {
		return '';
	}

	return (string) WPCPM_Students_Dashboard::page_url();
}

/**
 * Whether this request is the mentor page.
 *
 * Matched on the plugin's own stored page ID and then on the block or shortcode,
 * because the dashboard is reachable three ways — the page the plugin creates,
 * the block dropped on any page, or the shortcode — and the skin has to load for
 * all three.
 *
 * @return bool
 */
function wpcredits_is_mentor_page() {
	if ( ! wpcredits_plugin_active() || ! is_singular() ) {
		return false;
	}

	$page_id = (int) get_option( WPCPM_Mentors_Dashboard::OPT_PAGE );

	if ( $page_id && get_queried_object_id() === $page_id ) {
		return true;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return has_block( WPCPM_Mentors_Dashboard::BLOCK, $post )
		|| has_shortcode( (string) $post->post_content, WPCPM_Mentors_Dashboard::SHORTCODE );
}

/**
 * Whether the current user holds the plugin's Mentor role.
 *
 * @return bool
 */
function wpcredits_viewer_is_mentor() {
	if ( ! wpcredits_plugin_active() || ! class_exists( 'WPCPM_Roles' ) ) {
		return false;
	}

	// The plugin's own test, which also counts an administrator matched to an
	// Airtable mentor record — the sync never gives an administrator the Mentor
	// role, so a role check alone would call them a non-mentor and label their own
	// list as somebody else's. Guarded so an older plugin still works.
	if ( method_exists( 'WPCPM_Mentors_Dashboard', 'is_mentor' ) ) {
		return (bool) WPCPM_Mentors_Dashboard::is_mentor();
	}

	return (bool) WPCPM_Roles::user_has_role( null, WPCPM_Roles::ROLE_MENTOR );
}

/**
 * Set the site title the way the reference site sets it: the accent word in brand
 * blue, and whatever follows it in muted gray at normal weight.
 *
 * "WordPress **Education** Initiatives" — bold ink, brand blue, muted gray. The
 * reference theme hard-codes those three spans; here they are derived from the
 * real site title, so renaming the site in Settings keeps working and a title
 * without the accent word simply comes out plain.
 *
 * @param string $text Plain text.
 * @return string Escaped HTML with at most one `<em>` and one `<i>`.
 */
function wpcredits_accent_word( $text ) {
	$text = trim( (string) $text );

	/**
	 * The word picked out in brand blue in the site title.
	 *
	 * @param string $word Default "Education".
	 */
	$word = (string) apply_filters( 'wpcredits_accent_word', __( 'Education', 'wpcredits-theme' ) );

	if ( '' === $word ) {
		return esc_html( $text );
	}

	// Limited to one split so only the first match is the accent: a title that
	// repeats the word should not end up half blue.
	$parts = preg_split(
		'/\b(' . preg_quote( $word, '/' ) . ')\b/u',
		$text,
		2,
		PREG_SPLIT_DELIM_CAPTURE
	);

	if ( ! is_array( $parts ) || count( $parts ) < 3 ) {
		return esc_html( $text );
	}

	$out  = esc_html( $parts[0] ) . '<em>' . esc_html( $parts[1] ) . '</em>';
	$tail = trim( $parts[2] );

	if ( '' !== $tail ) {
		$out .= ' <i>' . esc_html( $tail ) . '</i>';
	}

	return $out;
}

/**
 * A person's initials, for the avatar fallback.
 *
 * @param string $name Display name.
 * @return string Up to two letters.
 */
function wpcredits_initials( $name ) {
	$name  = trim( wp_strip_all_tags( (string) $name ) );
	$words = preg_split( '/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY );

	if ( empty( $words ) ) {
		return '';
	}

	$first = function_exists( 'mb_substr' ) ? mb_substr( $words[0], 0, 1 ) : substr( $words[0], 0, 1 );
	$last  = '';

	if ( count( $words ) > 1 ) {
		$tail = (string) end( $words );
		$last = function_exists( 'mb_substr' ) ? mb_substr( $tail, 0, 1 ) : substr( $tail, 0, 1 );
	}

	return function_exists( 'mb_strtoupper' )
		? mb_strtoupper( $first . $last )
		: strtoupper( $first . $last );
}

/**
 * How many accounts hold one of the program's roles.
 *
 * The count behind the landing page's figure. Cached for an hour because it only changes when a
 * sync runs, and read with `count_total` rather than by fetching the users — the page needs the
 * number, not the people.
 *
 * @param string $role Role slug.
 * @return int
 */
function wpcredits_program_count( $role ) {
	$role = sanitize_key( $role );
	$key  = 'wpcredits_count_' . $role;

	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	$query = new WP_User_Query(
		array(
			'role'        => $role,
			'number'      => 1,
			'count_total' => true,
			'fields'      => 'ID',
		)
	);

	$count = (int) $query->get_total();

	set_transient( $key, $count, HOUR_IN_SECONDS );

	return $count;
}
