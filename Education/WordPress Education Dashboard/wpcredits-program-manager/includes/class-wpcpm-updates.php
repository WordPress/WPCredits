<?php
/**
 * Program updates, listed on the Report Cards.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Links to the posts in the "Updates" category.
 *
 * Announcements are ordinary posts rather than anything of this plugin's own, so they are
 * written, categorised and scheduled with the editor everybody already knows, and they have a
 * permalink somebody can send to somebody else.
 *
 * **Who sees what is decided by `WPCPM_Content_Access`, not here.** A post's "Program access"
 * level does all the work: Public reaches everybody, "Student level" reaches students, and so
 * on, with program managers able to read every level. Two things enforce that, deliberately:
 *
 * - `WPCPM_Content_Access::filter_queries()` is on `pre_get_posts`, so the query below comes
 *   back already narrowed, and
 * - `can_view()` is called again on every post that survives.
 *
 * The second pass is not redundant. It is the check that still holds if the query filter is
 * ever bypassed — `suppress_filters`, a caching layer that hands back somebody else's rows, a
 * future caller that builds the list a different way. An access list with one enforcement point
 * fails silently and completely; this one fails closed.
 */
class WPCPM_Updates {

	/**
	 * How many links the column shows.
	 *
	 * A column beside the Resources buttons, not an archive. The newest few are what somebody
	 * glancing at their Report Card has not read yet; everything older is what the category
	 * archive is for.
	 */
	const LIMIT = 5;

	/**
	 * The category slug announcements are filed under.
	 */
	const CATEGORY = 'updates';

	/**
	 * The "Updates" term, or null when the site has no such category.
	 *
	 * Looked up by slug and then by name, because a site can reasonably have called it either.
	 * Never created: this plugin does not own the site's taxonomy, and a category conjured out
	 * of nowhere is harder to explain than an empty column.
	 *
	 * @return WP_Term|null
	 */
	public static function term() {
		/**
		 * Filter the category announcements are read from.
		 *
		 * @param string $slug Category slug.
		 */
		$slug = (string) apply_filters( 'wpcpm_updates_category', self::CATEGORY );
		$term = get_term_by( 'slug', $slug, 'category' );

		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'name', ucfirst( $slug ), 'category' );
		}

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * The access levels a card shows, given whose card it is.
	 *
	 * Public plus that audience's own level, and nothing else — so the Mentor Report Card lists
	 * mentor announcements and the Student Report Card lists student ones.
	 *
	 * @param string $audience `student`, `mentor` or `institution`.
	 * @return string[]|null Levels, or null for "ask the viewer instead".
	 */
	private static function levels_for( $audience ) {
		$map = array(
			'student'     => WPCPM_Roles::ROLE_STUDENT,
			'mentor'      => WPCPM_Roles::ROLE_MENTOR,
			'institution' => WPCPM_Roles::ROLE_INSTITUTION,
		);

		if ( ! isset( $map[ $audience ] ) ) {
			return null;
		}

		return array( 'public', $map[ $audience ] );
	}

	/**
	 * Published updates to show, newest first.
	 *
	 * **Two gates, and they are not the same question.**
	 *
	 * `$audience` asks *whose card is this* — the same thing the "Need help?" button asks, and
	 * for the same reason. `can_view()` asks *may this person read it*, and program managers may
	 * read every level. Asking only that put mentor announcements on the Student Report Card
	 * whenever a manager looked at one, and the manager had no way of telling which of the two
	 * lists a student would actually see.
	 *
	 * Both must pass. The audience narrows the list to what that card is for; `can_view()` is the
	 * floor underneath, so the audience can never widen access beyond what the reader is
	 * entitled to. Called without an audience, it is `can_view()` alone as before.
	 *
	 * @param int    $max      Stop once this many have been found.
	 * @param string $audience `student` or `mentor`; empty to ask the viewer only.
	 * @return WP_Post[]
	 */
	public static function posts( $max = self::LIMIT, $audience = '' ) {
		$max    = max( 1, (int) $max );
		$term   = self::term();
		$levels = self::levels_for( $audience );

		if ( ! $term ) {
			return array();
		}

		// More rows than are wanted, because `can_view()` below may drop some: asking for exactly
		// five and then filtering is how a list of five silently becomes a list of two.
		$found = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'cat'                 => $term->term_id,
				'numberposts'         => $max * 4,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'suppress_filters'    => false,
				'ignore_sticky_posts' => true,
			)
		);

		$allowed = array();

		foreach ( $found as $post ) {
			if ( null !== $levels && ! in_array( WPCPM_Content_Access::get_level( $post ), $levels, true ) ) {
				continue;
			}

			if ( ! WPCPM_Content_Access::can_view( $post ) ) {
				continue;
			}

			$allowed[] = $post;

			if ( count( $allowed ) >= $max ) {
				break;
			}
		}

		return $allowed;
	}

	/**
	 * The "Updates" half of the Resources section.
	 *
	 * Built from the same `wpcpm-student__*` classes as everything else on the card, so the
	 * theme dresses it without being told about it.
	 *
	 * @param string $audience `student` or `mentor`, deciding whose announcements are listed.
	 * @return string
	 */
	public static function render_column( $audience = '' ) {
		// One more than are shown, which is what says whether there is anything past the column
		// without a second query — and says it about posts *this* reader may see. The term's own
		// count includes levels they cannot read, so it would offer "All updates" to somebody
		// whose archive holds exactly what they are already looking at.
		$posts = self::posts( self::LIMIT + 1, $audience );
		$more  = count( $posts ) > self::LIMIT;
		$posts = array_slice( $posts, 0, self::LIMIT );

		$out  = '<div class="wpcpm-resources__col wpcpm-resources__col--updates">';
		$out .= sprintf(
			'<h3 class="wpcpm-student__heading">%s</h3>',
			esc_html__( 'Program updates and announcements', 'wpcredits-program-manager' )
		);

		if ( ! $posts ) {
			// Said rather than left blank: an empty half-column under a heading reads as
			// something that failed to load.
			$out .= sprintf(
				'<p class="wpcpm-student__note wpcpm-updates__empty">%s</p>',
				esc_html__( 'Nothing new right now.', 'wpcredits-program-manager' )
			);

			return $out . '</div>';
		}

		$out .= '<ul class="wpcpm-updates">';

		foreach ( $posts as $post ) {
			$out .= sprintf(
				'<li class="wpcpm-updates__item"><a class="wpcpm-updates__link" href="%1$s">%2$s</a>'
					. '<span class="wpcpm-updates__date">%3$s</span></li>',
				esc_url( (string) get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				esc_html( get_the_date( '', $post ) )
			);
		}

		$out .= '</ul>';

		if ( $more ) {
			$term = self::term();

			$out .= sprintf(
				'<p class="wpcpm-student__note"><a class="wpcpm-updates__more" href="%1$s">%2$s</a></p>',
				esc_url( $term ? (string) get_term_link( $term ) : '' ),
				esc_html__( 'All updates', 'wpcredits-program-manager' )
			);
		}

		return $out . '</div>';
	}
}
