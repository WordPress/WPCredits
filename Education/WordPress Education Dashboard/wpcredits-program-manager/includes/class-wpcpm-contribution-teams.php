<?php
/**
 * Contribution team names, and where each one lives on make.wordpress.org.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a contribution team name into a link to that team's site.
 *
 * The list belongs to the Contributor Team Matcher plugin, which already maintains it
 * and lets a site edit it. So it is *read* from there whenever that plugin is present,
 * rather than copied — a second copy is a second thing to update and the one that goes
 * stale is always the copy. The bundled list below is only the fallback for a site
 * running this plugin without that one.
 *
 * Airtable's own list is longer than the matcher's, and deliberately not padded out
 * here: a team with no known page renders as plain text. Guessing a URL from a team
 * name is how somebody ends up linked to a 404.
 */
class WPCPM_Contribution_Teams {

	/**
	 * Team name → team URL, with the matcher's list preferred.
	 *
	 * @return array<string, string> Keyed by lowercased team name.
	 */
	public static function map() {
		$map = array();

		// `get_teams()` rather than `get_default_teams()`: the former respects whatever
		// the site has saved in the matcher's own settings, which is the point of reading
		// from it at all.
		if ( class_exists( 'CONTTEMA_Quiz_Data' ) && method_exists( 'CONTTEMA_Quiz_Data', 'get_teams' ) ) {
			foreach ( (array) CONTTEMA_Quiz_Data::get_teams() as $team ) {
				$name = isset( $team['name'] ) ? trim( (string) $team['name'] ) : '';
				$url  = isset( $team['url'] ) ? trim( (string) $team['url'] ) : '';

				if ( '' !== $name && '' !== $url ) {
					$map[ self::key( $name ) ] = $url;
				}
			}
		}

		if ( empty( $map ) ) {
			$map = self::fallback();
		}

		/**
		 * Filter the contribution team links.
		 *
		 * @param array<string, string> $map Lowercased team name to URL.
		 */
		return (array) apply_filters( 'wpcpm_contribution_team_links', $map );
	}

	/**
	 * The bundled list, used only when the Team Matcher plugin is absent.
	 *
	 * A copy of that plugin's defaults as of Contributor Team Matcher 1.x. Only the
	 * teams it actually publishes a URL for — Airtable also carries BuddyPress,
	 * bbPress, GlotPress, Mobile, Tide, Data Liberation and "DEIB + diverse speakers",
	 * which have no make.wordpress.org page in that list and so stay unlinked.
	 *
	 * @return array<string, string>
	 */
	private static function fallback() {
		return array(
			'core'             => 'https://make.wordpress.org/core/',
			'design'           => 'https://make.wordpress.org/design/',
			'accessibility'    => 'https://make.wordpress.org/accessibility/',
			'polyglots'        => 'https://make.wordpress.org/polyglots/',
			'support'          => 'https://make.wordpress.org/support/',
			'documentation'    => 'https://make.wordpress.org/docs/',
			'themes'           => 'https://make.wordpress.org/themes/',
			'plugins'          => 'https://make.wordpress.org/plugins/',
			'community'        => 'https://make.wordpress.org/community/',
			'meta'             => 'https://make.wordpress.org/meta/',
			'training'         => 'https://make.wordpress.org/training/',
			'test'             => 'https://make.wordpress.org/test/',
			'tv'               => 'https://make.wordpress.org/tv/',
			'cli'              => 'https://make.wordpress.org/cli/',
			'hosting'          => 'https://make.wordpress.org/hosting/',
			'openverse'        => 'https://make.wordpress.org/openverse/',
			'photos'           => 'https://make.wordpress.org/photos/',
			'core performance' => 'https://make.wordpress.org/performance/',
			'playground'       => 'https://make.wordpress.org/playground/',
			'core ai'          => 'https://make.wordpress.org/ai/',
			'core program'     => 'https://make.wordpress.org/program/',
		);
	}

	/**
	 * Team name → Dashicon slug.
	 *
	 * **Not taken from make.wordpress.org.** That site publishes no per-team icon: its
	 * team list is plain headings, every team site serves the same generic WordPress
	 * favicon, and the team badges on a wordpress.org profile are text chips. So this
	 * mapping is authored here, from Dashicons — WordPress's own icon set, GPL like this
	 * plugin, already registered in core under the `dashicons` handle, and the icons
	 * WordPress itself uses in wp-admin for these same concepts.
	 *
	 * Every slug below was checked against the shipped `dashicons.min.css`. A slug that
	 * does not exist renders as a blank box rather than an error, which is the kind of
	 * thing nobody notices until a screenshot.
	 *
	 * @return array<string, string> Keyed by lowercased team name.
	 */
	public static function icons() {
		$icons = array(
			'core'                    => 'wordpress',
			'design'                  => 'art',
			'accessibility'           => 'universal-access-alt',
			'polyglots'               => 'translation',
			'glotpress'               => 'translation',
			'support'                 => 'sos',
			'documentation'           => 'media-document',
			'themes'                  => 'admin-appearance',
			'plugins'                 => 'admin-plugins',
			'community'               => 'groups',
			'meta'                    => 'networking',
			'training'                => 'welcome-learn-more',
			'test'                    => 'clipboard',
			'tv'                      => 'video-alt3',
			'cli'                     => 'editor-code',
			'hosting'                 => 'cloud',
			'openverse'               => 'images-alt2',
			'photos'                  => 'camera',
			'core performance'        => 'performance',
			'playground'              => 'desktop',
			'mobile'                  => 'smartphone',
			'buddypress'              => 'buddicons-buddypress-logo',
			'bbpress'                 => 'buddicons-bbpress-logo',
			'data liberation'         => 'database-export',
			'deib + diverse speakers' => 'megaphone',
			'tide'                    => 'chart-line',
		);

		/**
		 * Filter the Dashicon used for each contribution team.
		 *
		 * @param array<string, string> $icons Lowercased team name to Dashicon slug,
		 *                                     without the `dashicons-` prefix.
		 */
		return (array) apply_filters( 'wpcpm_contribution_team_icons', $icons );
	}

	/**
	 * The icon for the *row label*, given whatever the student's team value is.
	 *
	 * Three answers, and the question mark is the point of it: a row labelled with a team
	 * icon says "this is the team", and a row labelled with a question mark says "nobody
	 * has picked one" — which is a different fact from an empty value cell, and the one a
	 * mentor scanning a list of students is looking for.
	 *
	 * A student can be on more than one team. The first recognised one supplies the icon,
	 * because a label has room for one and the value cell lists them all anyway.
	 *
	 * @param string $value Stored team value, possibly comma joined.
	 * @return string
	 */
	public static function label_icon( $value ) {
		$names = array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' );

		if ( empty( $names ) ) {
			return self::dashicon( 'editor-help', 'wpcpm-team__icon wpcpm-team__icon--unset' );
		}

		$icons = self::icons();

		foreach ( $names as $name ) {
			$key = self::key( $name );

			if ( isset( $icons[ $key ] ) ) {
				return self::dashicon( $icons[ $key ], 'wpcpm-team__icon' );
			}
		}

		// A team that is set but not in the map. Not a question mark — one *is* chosen —
		// so a neutral "team" glyph rather than a claim that nothing was picked.
		return self::dashicon( 'groups', 'wpcpm-team__icon' );
	}

	/**
	 * One Dashicon span.
	 *
	 * @param string $slug  Dashicon slug, without the prefix.
	 * @param string $classes Extra classes.
	 * @return string
	 */
	private static function dashicon( $slug, $classes ) {
		return sprintf(
			'<span class="dashicons dashicons-%1$s %2$s" aria-hidden="true"></span>',
			esc_attr( $slug ),
			esc_attr( $classes )
		);
	}

	/**
	 * The lookup key for a team name.
	 *
	 * Lowercased and space-collapsed, because Airtable and the matcher do not agree on
	 * capitalization — Airtable has "Core performance" where the matcher has "Core
	 * Performance", and a case-sensitive match would drop it.
	 *
	 * @param string $name Team name.
	 * @return string
	 */
	private static function key( $name ) {
		$name = trim( preg_replace( '/\s+/', ' ', (string) $name ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
	}

	/**
	 * The URL for one team, or an empty string.
	 *
	 * @param string $name Team name.
	 * @return string
	 */
	public static function url( $name ) {
		$map = self::map();
		$key = self::key( $name );

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * A team list rendered as links, with unknown teams left as text.
	 *
	 * The stored value is whatever `WPCPM_Mentors_Sync::resolve_links()` produced, which
	 * is a comma-joined list of names — a student can be on more than one team — so it is
	 * split, linked name by name, and rejoined.
	 *
	 * @param string $value Stored team value, possibly comma joined.
	 * @return string HTML, already escaped.
	 */
	public static function links( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$names  = array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' );
		$output = array();

		foreach ( $names as $name ) {
			$url = self::url( $name );

			if ( '' === $url ) {
				$output[] = esc_html( $name );
				continue;
			}

			$output[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $url ),
				esc_html( $name )
			);
		}

		return implode( ', ', $output );
	}

	/**
	 * Every team Airtable knows about, for the student's own team picker.
	 *
	 * Airtable's `Main Contribution Team` is a linked-record field, so the options are
	 * the records in the Contribution areas table — not this class's URL list, which is
	 * shorter. The names come from the map the sync already built and stored, so no
	 * request is made to draw the form.
	 *
	 * @return array<string, string> Airtable record ID → team name.
	 */
	public static function options() {
		// `lookups()` rather than the raw option: it applies the `LOOKUPS_VERSION` check
		// that discards maps written by the version which stored the wrong column, and
		// reading around it would put those bad names back on screen.
		$teams = WPCPM_Mentors_Sync::lookups()['teams'];

		$options = array();

		foreach ( $teams as $record_id => $name ) {
			$name = trim( (string) $name );

			if ( WPCPM_Mentors_Sync::is_record_id( $record_id ) && '' !== $name ) {
				$options[ $record_id ] = $name;
			}
		}

		natcasesort( $options );

		return $options;
	}
}
