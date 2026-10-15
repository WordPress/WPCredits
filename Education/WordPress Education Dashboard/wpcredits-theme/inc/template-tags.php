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
 * plugin is deactivated - which is exactly when somebody is debugging.
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
 * Matched the same three ways as the mentor page - the page the plugin creates,
 * the block on any page, or the shortcode - and guarded on the class so a plugin
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
 * Whether this request is the Administrator Dashboard.
 *
 * Matched three ways, like the other three pages: the stored page ID, the block, or the
 * shortcode, because the dashboard is reachable all three ways.
 *
 * @return bool
 */
function wpcredits_is_administrator_page() {
	if ( ! wpcredits_plugin_active() || ! is_singular() || ! class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
		return false;
	}

	$page_id = (int) get_option( WPCPM_Administrators_Dashboard::OPT_PAGE );

	if ( $page_id && get_queried_object_id() === $page_id ) {
		return true;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return has_block( WPCPM_Administrators_Dashboard::BLOCK, $post )
		|| has_shortcode( (string) $post->post_content, WPCPM_Administrators_Dashboard::SHORTCODE );
}

/**
 * Whether this request is the Sponsor Dashboard.
 *
 * Matched three ways, like the other four pages: the stored page ID, the block, or the
 * shortcode, because the dashboard is reachable all three ways.
 *
 * @return bool
 */
function wpcredits_is_sponsor_page() {
	if ( ! wpcredits_plugin_active() || ! is_singular() || ! class_exists( 'WPCPM_Sponsors_Dashboard' ) ) {
		return false;
	}

	$page_id = (int) get_option( WPCPM_Sponsors_Dashboard::OPT_PAGE );

	if ( $page_id && get_queried_object_id() === $page_id ) {
		return true;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return has_block( WPCPM_Sponsors_Dashboard::BLOCK, $post )
		|| has_shortcode( (string) $post->post_content, WPCPM_Sponsors_Dashboard::SHORTCODE );
}

/**
 * Whether this request is any of the dashboards.
 *
 * The five pages share a shell, so anything that dresses that shell - the card,
 * the insets, the type - applies to all of them.
 *
 * **The institution page was missing from this for two releases**, which is why it
 * did not look like the other two however much its own stylesheet was worked on:
 * the skin below is what gives a dashboard its card, its measure and its type, and
 * none of it was loading there. Anything added here has to be added to the body
 * class in functions.php as well, since every rule in the skin is prefixed with it.
 *
 * The Administrator Dashboard joined in 1.17.0, added here and in
 * `wpcredits_body_class()` in the same commit.
 *
 * The Sponsor Dashboard joined in 1.18.0, added here and in
 * `wpcredits_body_class()` in the same commit.
 *
 * @return bool
 */
function wpcredits_is_dashboard_page() {
	return wpcredits_is_mentor_page() || wpcredits_is_student_page() || wpcredits_is_institution_page() || wpcredits_is_administrator_page() || wpcredits_is_sponsor_page();
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
 * The institution page's URL, or an empty string.
 *
 * @return string
 */
function wpcredits_institution_page_url() {
	if ( ! wpcredits_plugin_active() || ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
		return '';
	}

	return (string) WPCPM_Institutions_Dashboard::page_url();
}

/**
 * The sponsor page's URL, or an empty string.
 *
 * @return string
 */
function wpcredits_sponsor_page_url() {
	if ( ! wpcredits_plugin_active() || ! class_exists( 'WPCPM_Sponsors_Dashboard' ) ) {
		return '';
	}

	return (string) WPCPM_Sponsors_Dashboard::page_url();
}

/**
 * Whether this request is the mentor page.
 *
 * Matched on the plugin's own stored page ID and then on the block or shortcode,
 * because the dashboard is reachable three ways - the page the plugin creates,
 * the block dropped on any page, or the shortcode - and the skin has to load for
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
	// Airtable mentor record - the sync never gives an administrator the Mentor
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
 * "WordPress **Education** Initiatives" - bold ink, brand blue, muted gray. The
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
 * sync runs, and read with `count_total` rather than by fetching the users - the page needs the
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

/**
 * The program figure block's outcome figures, in the order the hero draws them. The one list: the loop
 * over what the plugin publishes and the figure block's guard both read it, so a figure added here is
 * an outcome figure to both. The block's one other outcome stat is the source line they share,
 * `outcomes-source`; its words are in `wpcredits_outcome_stat_html()`.
 */
const WPCREDITS_OUTCOME_FIGURES = array( 'graduation', 'recommend', 'keep' );

/**
 * The program's three outcome figures, as the plugin may show them (plugin 1.122.24 and later).
 *
 * Read from `WPCPM_Outcome_Figures::published()`, guarded on the class and the method, so a theme on
 * an older plugin, or with none, shows no figure. What the plugin hands over is checked as well: a
 * figure that is not whole numbers that make sense together (a share from 0 to 100, a count above
 * zero and no more than its n) is left out, so nothing but a figure the plugin stored is ever drawn.
 *
 * @return array Empty when no figure may be shown; else `read`, the Unix time the figures were read,
 *               and `figures`, `graduation`, `recommend` and `keep` in that order, those that may be
 *               shown, each `array( 'share' => int, 'count' => int, 'n' => int )`.
 */
function wpcredits_outcome_figures() {
	if ( ! class_exists( 'WPCPM_Outcome_Figures' ) || ! method_exists( 'WPCPM_Outcome_Figures', 'published' ) ) {
		return array();
	}

	$set = WPCPM_Outcome_Figures::published();

	if ( ! is_array( $set ) || ! isset( $set['read'], $set['figures'] ) || ! is_int( $set['read'] ) || $set['read'] <= 0 || ! is_array( $set['figures'] ) ) {
		return array();
	}

	$figures = array();

	foreach ( WPCREDITS_OUTCOME_FIGURES as $key ) {
		$figure = isset( $set['figures'][ $key ] ) && is_array( $set['figures'][ $key ] ) ? $set['figures'][ $key ] : array();

		if ( ! isset( $figure['share'], $figure['count'], $figure['n'] ) || ! is_int( $figure['share'] ) || ! is_int( $figure['count'] ) || ! is_int( $figure['n'] ) ) {
			continue;
		}

		if ( $figure['share'] < 0 || $figure['share'] > 100 || $figure['count'] < 1 || $figure['count'] > $figure['n'] ) {
			continue;
		}

		$figures[ $key ] = array(
			'share' => $figure['share'],
			'count' => $figure['count'],
			'n'     => $figure['n'],
		);
	}

	if ( array() === $figures ) {
		return array();
	}

	return array(
		'read'    => $set['read'],
		'figures' => $figures,
	);
}

/**
 * An outcome figure, or the figures' source line, as the program figure block prints it.
 *
 * A figure is its number, its label and its base, all inside the block, so a figure that may not be
 * shown takes its words with it; the label is the block's own when one is typed into it. The source
 * line gives the date the figures were read, in the site's date format and time zone, and names the
 * public impact dashboard whose definitions they share.
 *
 * @param string $stat  The block's stat: `graduation`, `recommend`, `keep` or `outcomes-source`.
 * @param string $label The block's label, '' for the figure's own.
 * @return string|null Escaped markup, '' when there is nothing to show, or null for a stat that is not
 *                     an outcome figure.
 */
function wpcredits_outcome_stat_html( $stat, $label = '' ) {
	// Not an outcome figure, so a count stat: before anything is looked up, built or translated for it.
	if ( 'outcomes-source' !== $stat && ! in_array( $stat, WPCREDITS_OUTCOME_FIGURES, true ) ) {
		return null;
	}

	$set = wpcredits_outcome_figures();

	if ( 'outcomes-source' === $stat ) {
		if ( array() === $set ) {
			return '';
		}

		return sprintf(
			'<p %1$s>%2$s</p>',
			get_block_wrapper_attributes( array( 'class' => 'wpc-figure-source' ) ),
			wp_kses(
				sprintf(
					/* translators: 1: the date the figures were read, 2: the public impact dashboard's address. */
					__( 'As of %1$s, counted by this site from the program\'s records with the same definitions as the <a href="%2$s">WordPress Credits impact dashboard</a>.', 'wpcredits-theme' ),
					esc_html( wp_date( (string) get_option( 'date_format' ), $set['read'] ) ),
					esc_url( 'https://wordpress.github.io/WPCredits/' )
				),
				array( 'a' => array( 'href' => true ) )
			)
		);
	}

	if ( ! isset( $set['figures'][ $stat ] ) ) {
		return '';
	}

	$figure = $set['figures'][ $stat ];
	$label  = trim( (string) $label );
	$labels = array(
		/* translators: Follows the share printed above it in another element, such as "87%", which is not part of this string. */
		'graduation' => __( 'of WordPress Credits students graduate rather than drop out', 'wpcredits-theme' ),
		/* translators: Follows the share printed above it in another element, such as "88%", which is not part of this string. */
		'recommend'  => __( 'of graduates would recommend the program', 'wpcredits-theme' ),
		/* translators: Follows the share printed above it in another element, such as "79%", which is not part of this string. */
		'keep'       => __( 'of graduates plan to keep contributing to WordPress', 'wpcredits-theme' ),
	);

	if ( 'graduation' === $stat ) {
		/* translators: 1: students who graduated, 2: students who graduated or dropped out. */
		$base = sprintf( _n( '%1$s of %2$s student who graduated or dropped out', '%1$s of %2$s students who graduated or dropped out', $figure['n'], 'wpcredits-theme' ), number_format_i18n( $figure['count'] ), number_format_i18n( $figure['n'] ) );
	} else {
		/* translators: %s: how many answered the question on the final survey. */
		$base = sprintf( _n( '%s answer to the final survey', '%s answers to the final survey', $figure['n'], 'wpcredits-theme' ), number_format_i18n( $figure['n'] ) );
	}

	return sprintf(
		'<div %1$s><p class="wpc-figure__value">%2$s</p><p class="wpc-figure__label">%3$s</p><p class="wpc-figure__base">%4$s</p></div>',
		get_block_wrapper_attributes( array( 'class' => 'wpc-figure' ) ),
		/* translators: %s: a share, a whole number from 0 to 100. */
		esc_html( sprintf( _x( '%s%%', 'a share, as a percentage', 'wpcredits-theme' ), number_format_i18n( $figure['share'] ) ) ),
		esc_html( '' !== $label ? $label : $labels[ $stat ] ),
		esc_html( $base )
	);
}
