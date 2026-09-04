<?php
/**
 * The Documentation template - its contents list, and its anchors.
 *
 * **Why the theme carries a contents block at all.** WordPress does have one, `core/table-of-contents`,
 * and it is registered on this site - but by the Gutenberg plugin rather than by core, and its render
 * callback returns nothing at all unless it is running inside `the_content`:
 *
 *     if ( ! in_array( 'the_content', $wp_current_filter, true ) ) { return $content; }
 *
 * A contents list belongs in the sidebar, and a sidebar lives in the template, which is outside
 * `the_content` by definition. So the core block cannot be used where this design needs one, and a
 * theme that depended on it would also lose its contents list the day the Gutenberg plugin is
 * deactivated.
 *
 * The list is read from the page's own headings. `bin/build-docs.php` writes an anchor onto every
 * heading in the three program guides, so the ids are part of the document rather than something a
 * stylesheet invents at render time - a shared link keeps working. `wpcredits_guide_anchor_ids()`
 * below is the safety net for a heading typed into the editor afterwards, which will have none.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The template that turns all of this on. */
const WPCREDITS_GUIDE_TEMPLATE = 'page-documentation';

/**
 * Whether the page being rendered uses the Documentation template.
 *
 * @return bool
 */
function wpcredits_is_guide_page() {
	if ( ! is_singular() ) {
		return false;
	}

	return WPCREDITS_GUIDE_TEMPLATE === get_page_template_slug( get_queried_object_id() );
}

/**
 * A slug for a heading, matching what `bin/build-docs.php` writes.
 *
 * @param string $text  Heading text.
 * @param array  $taken Slugs already used on this page, by reference.
 * @return string
 */
function wpcredits_guide_slug( $text, array &$taken ) {
	$slug = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', wp_strip_all_tags( (string) $text ) ), '-' ) );
	$slug = '' !== $slug ? $slug : 'section';

	if ( isset( $taken[ $slug ] ) ) {
		++$taken[ $slug ];

		return $slug . '-' . $taken[ $slug ];
	}

	$taken[ $slug ] = 1;

	return $slug;
}

/**
 * The headings of a page, in document order.
 *
 * Read from the stored content rather than from the rendered page, because the sidebar is drawn
 * before the content it describes - there is nothing rendered to read yet.
 *
 * A heading with no id is listed with the slug it *would* have; `wpcredits_guide_anchor_ids()`
 * puts that same id on the heading itself, so the two agree without either knowing about the other.
 *
 * @param int $post_id Page ID.
 * @param int $max     Deepest level to include.
 * @return array<int, array{level:int, text:string, id:string}>
 */
function wpcredits_guide_headings( $post_id, $max = 3 ) {
	static $cache = array();

	$key = (int) $post_id . ':' . (int) $max;

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	$found = array();
	$taken = array();

	// The stored content is block markup, and a heading block stores the real `<h2>` inside it - so
	// the tags are here to be read without rendering anything.
	if ( preg_match_all( '#<h([2-6])\b([^>]*)>(.*?)</h\1>#is', $post->post_content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$level = (int) $match[1];

			if ( $level > (int) $max ) {
				continue;
			}

			$text = trim( wp_strip_all_tags( $match[3] ) );

			if ( '' === $text ) {
				continue;
			}

			$id = preg_match( '/\bid=["\']([^"\']+)["\']/', $match[2], $has_id )
				? $has_id[1]
				: wpcredits_guide_slug( $text, $taken );

			// An id written into the document still claims its slug, so a later heading with the
			// same words is numbered rather than colliding with it.
			if ( ! empty( $has_id ) ) {
				$taken[ $id ] = isset( $taken[ $id ] ) ? $taken[ $id ] + 1 : 1;
			}

			$found[] = array(
				'level' => $level,
				'text'  => $text,
				'id'    => $id,
			);
		}
	}

	$cache[ $key ] = $found;

	return $found;
}

/**
 * Give any heading without an id the one the contents list is pointing at.
 *
 * Only on this template, and only for headings that have none - the guides' own anchors are written
 * at build time and are left exactly as they are.
 *
 * @param string $content Post content.
 * @return string
 */
function wpcredits_guide_anchor_ids( $content ) {
	if ( ! in_the_loop() || ! is_main_query() || ! wpcredits_is_guide_page() ) {
		return $content;
	}

	$headings = wpcredits_guide_headings( get_the_ID() );

	if ( empty( $headings ) ) {
		return $content;
	}

	$index = 0;

	return preg_replace_callback(
		'#<h([2-6])\b([^>]*)>#i',
		static function ( $match ) use ( $headings, &$index ) {
			$level = (int) $match[1];

			// Deeper headings are not in the list and are not counted against it, so the two stay
			// in step whatever the page contains.
			if ( ! isset( $headings[ $index ] ) || $headings[ $index ]['level'] !== $level ) {
				return $match[0];
			}

			$heading = $headings[ $index ];
			++$index;

			if ( false !== stripos( $match[2], 'id=' ) ) {
				return $match[0];
			}

			return sprintf( '<h%1$d%2$s id="%3$s">', $level, $match[2], esc_attr( $heading['id'] ) );
		},
		$content
	);
}
add_filter( 'the_content', 'wpcredits_guide_anchor_ids', 9 );

/**
 * The Documentation template's stylesheet.
 *
 * Its own file, loaded only where the template is used: it is a page layout with a sidebar, and
 * nothing else on the site has one.
 */
function wpcredits_guide_assets() {
	if ( ! wpcredits_is_guide_page() ) {
		return;
	}

	wp_enqueue_style(
		'wpcredits-guide',
		get_theme_file_uri( 'assets/css/guide.css' ),
		array( 'wpcredits-style' ),
		WPCREDITS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'wpcredits_guide_assets', 20 );
