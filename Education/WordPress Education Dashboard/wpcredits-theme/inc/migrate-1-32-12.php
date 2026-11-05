<?php
/**
 * The one-time migration of theme 1.32.12: the saved copy of the homepage's template gets the band of
 * role cards in place of its "Who this is for" section, directly under the hero.
 *
 * Once an Administrator saves a template in the Site Editor, WordPress draws the saved copy (a
 * `wp_template` post) and no theme release changes its content again. Live's homepage is drawn by its
 * saved copy of the `page-education-hub` template (post 1654, last saved on 27 October 2026), whose
 * sections are inlined, not pattern references, so the theme's new pattern (patterns/hub-roles.php) does
 * not reach it. On the first request after the theme updates, after the 1.32.1 migration has run, this
 * replaces that one section, and only where it has the shape the theme shipped
 * (`wpcredits_migrate_1_32_12_matches()`):
 *
 * - the group of class `wpc-hub-doors`, exactly as theme 1.32.11's "Education Hub doors" pattern wrote
 *   it: the same blocks in the same nesting with the same attributes (a block's metadata aside, which
 *   holds its name in List View and the pattern it came from), and the same elements with the same
 *   classes in each, with two doors (students, institutions) or, where the parked content was shown,
 *   three (and companies). The words in it and the links' addresses are not its shape: they are an
 *   Administrator's to edit, and the band carries the design's own;
 * - becomes the band, `wpcredits_hub_roles_markup()`, in its place when it stands directly under the
 *   hero (group `wpc-hub-hero`), as on live; where it stands further down, it is taken out (with the two
 *   blank lines before it) and the band put directly under the hero.
 *
 * A template whose section has any other shape (a card added or removed, a block added, a style
 * changed), that holds two such sections or none, that has no hero, or that already holds the band is
 * left whole, and the record says why, which the Site Editor's notice tells Administrators, with how to
 * show the band by hand. Every other byte of the template is kept as it was: the section is cut out and the
 * band put in, with nothing parsed and written back around them. The copy of the template as it was and
 * the write are the 1.32.0 migration's own (`wpcredits_migrate_keep_copy()`, `wpcredits_migrate_1_32_0_write()`);
 * the record is the option `wpcredits_migrated_1_32_12`, written last, in the runners' lock. Run again, it
 * finds the band and changes nothing.
 *
 * Unlike the 1.32.0 and 1.32.1 migrations, a later save that brings the section back is not repaired: the
 * section is editable content, not a part the Site Editor could not edit, and an Administrator who restores
 * it from a revision means to.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The option that says the migration ran, and holds what it did. */
const WPCREDITS_MIGRATED_1_32_12 = 'wpcredits_migrated_1_32_12';

/**
 * A saved template's content with its "Who this is for" section replaced by the band of roles under the
 * hero: pure, so it is tested on live's saved copy.
 *
 * @param string $content The template's saved content.
 * @param string $slug    Its slug.
 * @return array{content: string, changed: string[], skipped: string[]} The content; what was done
 *         (`roles-band`, and `roles-band-moved` when the section stood elsewhere); and why the template was
 *         left (`roles-done`, `doors-missing`, `doors-edited`, `hero-missing`, `other-template`).
 */
function wpcredits_migrate_1_32_12_content( $content, $slug ) {
	$result = array(
		'content' => (string) $content,
		'changed' => array(),
		'skipped' => array(),
	);

	if ( 'page-education-hub' !== $slug ) {
		$result['skipped'][] = 'other-template';

		return $result;
	}

	$blocks = wpcredits_migrate_1_32_12_blocks( $result['content'] );
	$roles  = wpcredits_migrate_1_32_12_with_class( $blocks, 'wpc-hub-roles' );
	$doors  = wpcredits_migrate_1_32_12_with_class( $blocks, 'wpc-hub-doors' );
	$hero   = wpcredits_migrate_1_32_12_with_class( $blocks, 'wpc-hub-hero' );

	if ( $roles ) {
		$result['skipped'][] = 'roles-done';

		return $result;
	}

	if ( ! $doors ) {
		$result['skipped'][] = 'doors-missing';

		return $result;
	}

	if ( 1 !== count( $doors ) || ! wpcredits_migrate_1_32_12_matches( substr( $result['content'], $doors[0]['start'], $doors[0]['end'] - $doors[0]['start'] ) ) ) {
		$result['skipped'][] = 'doors-edited';

		return $result;
	}

	if ( ! $hero ) {
		$result['skipped'][] = 'hero-missing';

		return $result;
	}

	$content = $result['content'];
	$band    = wpcredits_hub_roles_markup();
	$section = $doors[0];
	$under   = $hero[0]['end'];

	if ( $section['start'] === $under + 2 && "\n\n" === substr( $content, $under, 2 ) ) {
		$result['content'] = substr_replace( $content, $band, $section['start'], $section['end'] - $section['start'] );
		$result['changed'] = array( 'roles-band' );

		return $result;
	}

	// The section stands elsewhere: out of its place, with the whitespace that stood before it, then the band
	// directly under the hero. The section comes after the hero, or before it, and either way the later cut
	// is made first, so the earlier offset still holds.
	$gap   = strlen( substr( $content, 0, $section['start'] ) ) - strlen( rtrim( substr( $content, 0, $section['start'] ) ) );
	$cut   = array( $section['start'] - $gap, $section['end'] );
	$after = $section['start'] > $under;

	if ( $after ) {
		$content = substr_replace( $content, '', $cut[0], $cut[1] - $cut[0] );
		$content = substr_replace( $content, "\n\n" . $band, $under, 0 );
	} else {
		$content = substr_replace( $content, "\n\n" . $band, $under, 0 );
		$content = substr_replace( $content, '', $cut[0], $cut[1] - $cut[0] );
	}

	$result['content'] = $content;
	$result['changed'] = array( 'roles-band', 'roles-band-moved' );

	return $result;
}

/**
 * The blocks of some content, as core's parser finds their comments, each with where it starts and ends:
 * a void block its comment, any other from its opening comment to the end of its closing one.
 *
 * @param string $content Block markup.
 * @return array[] Each block: `name` (with its namespace, `core/` for a core block), `attrs` (decoded, an
 *                 array), `start` and `end` (byte offsets), in the order the blocks open.
 */
function wpcredits_migrate_1_32_12_blocks( $content ) {
	$blocks = array();
	$open   = array();

	preg_match_all( '#<!--\s+(/)?wp:([a-z][a-z0-9_-]*/)?([a-z][a-z0-9_-]*)\s+' . WPCREDITS_BLOCK_ATTRS . '(/)?-->#s', (string) $content, $comments, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

	foreach ( $comments as $comment ) {
		$name = ( '' !== $comment[2][0] && -1 !== $comment[2][1] ? $comment[2][0] : 'core/' ) . $comment[3][0];

		if ( '/' === $comment[1][0] ) {
			$at = array_pop( $open );

			if ( null !== $at && $blocks[ $at ]['name'] === $name ) {
				$blocks[ $at ]['end'] = $comment[0][1] + strlen( $comment[0][0] );
			}

			continue;
		}

		$attrs    = isset( $comment[4] ) && -1 !== $comment[4][1] && '' !== trim( $comment[4][0] ) ? json_decode( trim( $comment[4][0] ), true ) : array();
		$blocks[] = array(
			'name'  => $name,
			'attrs' => is_array( $attrs ) ? $attrs : array(),
			'start' => $comment[0][1],
			'end'   => $comment[0][1] + strlen( $comment[0][0] ),
		);

		if ( ! isset( $comment[5] ) || '/' !== $comment[5][0] ) {
			$open[] = count( $blocks ) - 1;
		}
	}

	return $blocks;
}

/**
 * The group blocks whose class list holds a class, as a whole word.
 *
 * @param array[] $blocks As `wpcredits_migrate_1_32_12_blocks()` gives them.
 * @param string  $name   The class.
 * @return array[]
 */
function wpcredits_migrate_1_32_12_with_class( array $blocks, $name ) {
	return array_values(
		array_filter(
			$blocks,
			static function ( $block ) use ( $name ) {
				$classes = isset( $block['attrs']['className'] ) && is_string( $block['attrs']['className'] ) ? preg_split( '/\s+/', $block['attrs']['className'], -1, PREG_SPLIT_NO_EMPTY ) : array();

				return 'core/group' === $block['name'] && in_array( $name, $classes, true );
			}
		)
	);
}

/**
 * Some block markup's shape: each block comment as its name and attributes, a block's metadata left out
 * and the attributes in one order, and each element as its name and its classes, every other attribute
 * and every word left out. Two copies of a section with the same shape differ only in words, addresses,
 * names in List View, and how the Site Editor wrote the same attributes.
 *
 * @param string $markup Block markup.
 * @return string
 */
function wpcredits_migrate_1_32_12_shape( $markup ) {
	$shape = '';

	preg_match_all( '#<!--\s+(/)?wp:([a-z][a-z0-9_-]*/)?([a-z][a-z0-9_-]*)\s+' . WPCREDITS_BLOCK_ATTRS . '(/)?-->|<(/?)([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>#s', (string) $markup, $tokens, PREG_SET_ORDER );

	foreach ( $tokens as $token ) {
		if ( '' !== $token[3] ) {
			$attrs = isset( $token[4] ) && '' !== trim( $token[4] ) ? json_decode( trim( $token[4] ), true ) : array();
			$attrs = is_array( $attrs ) ? $attrs : array( 'unreadable' => $token[4] );

			unset( $attrs['metadata'] );

			$shape .= '[' . $token[1] . ( '' !== $token[2] ? $token[2] : 'core/' ) . $token[3] . ( $attrs ? ' ' . wp_json_encode( wpcredits_migrate_1_32_12_sorted( $attrs ) ) : '' ) . ( isset( $token[5] ) && '/' === $token[5] ? ' /' : '' ) . ']';

			continue;
		}

		$class  = preg_match( '/\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $token[8], $found ) ? trim( (string) preg_replace( '/\s+/', ' ', '' !== $found[1] ? $found[1] : ( $found[2] ?? '' ) ) ) : '';
		$shape .= '<' . $token[6] . strtolower( $token[7] ) . ( '' !== $class ? ' .' . $class : '' ) . '>';
	}

	return $shape;
}

/**
 * An array's keys sorted, at every depth, so attributes the Site Editor wrote in another order read alike.
 *
 * @param array $value The array.
 * @return array
 */
function wpcredits_migrate_1_32_12_sorted( array $value ) {
	foreach ( $value as $key => $item ) {
		if ( is_array( $item ) ) {
			$value[ $key ] = wpcredits_migrate_1_32_12_sorted( $item );
		}
	}

	if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
		ksort( $value );
	}

	return $value;
}

/**
 * Whether a saved "Who this is for" section has the shape the theme shipped: theme 1.32.11's doors, two or
 * three (`wpcredits_migrate_1_32_12_doors_shipped()`), compared shape for shape.
 *
 * @param string $markup The section's block markup.
 * @return bool
 */
function wpcredits_migrate_1_32_12_matches( $markup ) {
	$shape = wpcredits_migrate_1_32_12_shape( $markup );

	return wpcredits_migrate_1_32_12_shape( wpcredits_migrate_1_32_12_doors_shipped( false ) ) === $shape
		|| wpcredits_migrate_1_32_12_shape( wpcredits_migrate_1_32_12_doors_shipped( true ) ) === $shape;
}

/**
 * The "Who this is for" section as theme 1.32.11's doors pattern wrote it (patterns/hub-doors.php, which
 * 1.32.12 removed), its words left out, as the migration compares it: the screen-reader heading and a
 * column for each door, students and institutions, and companies where the parked content was shown, each
 * door its picture (a Custom HTML block of the door's own elements), eyebrow, heading, line and link.
 *
 * @param bool $companies Whether the companies' door, parked since 21 October 2026, is in it.
 * @return string Block markup.
 */
function wpcredits_migrate_1_32_12_doors_shipped( $companies ) {
	$pictures = array(
		'<div class="wpc-hub-pic__piece wpc-hub-pic__badge"><span class="wpc-hub-art__mark"></span><span><span class="wpc-hub-art__strong"></span><span class="wpc-hub-art__small"></span></span></div>',
		'<div class="wpc-hub-pic__piece wpc-hub-pic__list"><span class="wpc-hub-art__strong"></span><span class="wpc-hub-pic__row"></span><span class="wpc-hub-pic__row"></span></div>',
	);

	if ( $companies ) {
		$pictures[] = '<div class="wpc-hub-pic__piece wpc-hub-pic__profile"><span class="wpc-hub-pic__person"></span><span><span class="wpc-hub-art__strong"></span><span class="wpc-hub-art__small"></span></span></div>';
	}

	$columns = '';

	foreach ( $pictures as $picture ) {
		$columns .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"is-style-card wpc-hub-door","layout":{"type":"default"}} --><div class="wp-block-group is-style-card wpc-hub-door">'
			. '<!-- wp:html --><div class="wpc-hub-pic" aria-hidden="true">' . $picture . '</div><!-- /wp:html -->'
			. '<!-- wp:paragraph {"className":"is-style-eyebrow"} --><p class="is-style-eyebrow"></p><!-- /wp:paragraph -->'
			. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"></h3><!-- /wp:heading -->'
			. '<!-- wp:paragraph {"className":"wpc-hub-door__text"} --><p class="wpc-hub-door__text"></p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph {"className":"wpc-hub-door__go"} --><p class="wpc-hub-door__go"><a href=""><span aria-hidden="true"></span></a></p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:group --></div><!-- /wp:column -->';
	}

	return '<!-- wp:group {"align":"wide","className":"wpc-hub-doors","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} --><div class="wp-block-group alignwide wpc-hub-doors" style="padding-top:48px;padding-bottom:48px">'
		. '<!-- wp:heading {"className":"screen-reader-text"} --><h2 class="wp-block-heading screen-reader-text"></h2><!-- /wp:heading -->'
		. '<!-- wp:columns {"className":"wpc-hub-doors__grid wpc-hub-stack"} --><div class="wp-block-columns wpc-hub-doors__grid wpc-hub-stack">' . $columns . '</div><!-- /wp:columns -->'
		. '</div><!-- /wp:group -->';
}

/**
 * The migration, once, after the 1.32.1 migration's: this theme's saved homepage template, a copy kept
 * first, its content written, and what was done recorded last. It runs in its lock
 * (`wpcredits_migrate_lock()`), so a request that comes while it runs does nothing.
 */
function wpcredits_migrate_1_32_12() {
	// The 1.32.1 migration runs first: until its option is set, this one waits, its lock not taken.
	if ( false !== get_option( WPCREDITS_MIGRATED_1_32_12, false ) || false === get_option( WPCREDITS_MIGRATED_1_32_1, false ) ) {
		return;
	}

	// The band's addresses are the plugin's pages, read as the band is written, so a run while the plugin is not
	// active would write their fallbacks into the saved template for good: it waits, recording nothing, and the
	// next request with the plugin active runs it.
	if ( ! wpcredits_plugin_active() ) {
		return;
	}

	$taken = wpcredits_migrate_lock( WPCREDITS_MIGRATED_1_32_12 );

	if ( ! $taken ) {
		return;
	}

	// The lock is let go of once the option is stored, and whatever happens after it was taken.
	try {
		// Another request may have run the migration between the check above and the lock: both options are
		// read again, from the database, and where this one's is there now this request does nothing more.
		if ( false !== wpcredits_migrate_option_now( WPCREDITS_MIGRATED_1_32_12 ) || false === wpcredits_migrate_option_now( WPCREDITS_MIGRATED_1_32_1 ) ) {
			return;
		}

		$record = array(
			'ran'       => time(),
			'templates' => array(),
		);

		// Whatever throws is caught and recorded, and the option is set all the same: a failure is reported
		// to Administrators once, not tried again on every request.
		try {
			$templates = get_posts(
				array(
					'post_type'      => 'wp_template',
					'post_status'    => 'any',
					'name'           => 'page-education-hub',
					'posts_per_page' => 20,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			foreach ( $templates as $template ) {
				if ( wpcredits_migrate_1_32_0_ours( $template ) ) {
					$record['templates'][] = wpcredits_migrate_1_32_0_template( $template, 'wpcredits_migrate_1_32_12_content' );
				}
			}
		} catch ( Throwable $error ) {
			$record['failed'] = 'update-failed';
		}

		if ( ! add_option( WPCREDITS_MIGRATED_1_32_12, $record, '', false ) ) {
			$lost = wpcredits_migrate_copies_lost( $record['templates'] );

			// The record was the only copy of a template as it was, and could not be stored: the record stored
			// in its place says the copy was lost, never that it was kept.
			if ( $lost !== $record['templates'] ) {
				$record['templates'] = $lost;

				add_option( WPCREDITS_MIGRATED_1_32_12, $record, '', false );
			}
		}
	} finally {
		wpcredits_migrate_unlock( WPCREDITS_MIGRATED_1_32_12, $taken );
	}
}
add_action( 'init', 'wpcredits_migrate_1_32_12', 22 );

/**
 * What the migration did, said in a sentence or two for the template it reached: what it changed and where
 * the template as it was is kept, or why it left it, and then how to show the band by hand.
 *
 * @param array $record The option's record.
 * @return string Plain text, '' when it reached no template.
 */
function wpcredits_migrate_1_32_12_summary( $record ) {
	$sentences = array();
	$reached   = is_array( $record ) && isset( $record['templates'] ) && is_array( $record['templates'] ) ? $record['templates'] : array();

	foreach ( $reached as $template ) {
		if ( ! is_array( $template ) ) {
			continue;
		}

		$changed = (array) ( $template['changed'] ?? array() );
		$skipped = (array) ( $template['skipped'] ?? array() );

		if ( $changed ) {
			$sentence = __( 'The theme\'s update to 1.32.12 changed the saved homepage template: the "Who this is for" section is now the band of ways to take part, directly under the hero, its sponsors\' card shown while the Partners page is public.', 'wpcredits-theme' );

			if ( in_array( 'saved-differently', $skipped, true ) ) {
				$sentence .= ' ' . __( 'What was saved differs from what the update wrote, so compare the template with its revisions.', 'wpcredits-theme' );
			}

			$kept = isset( $template['kept'] ) ? $template['kept'] : '';

			if ( 'lost' === $kept ) {
				$sentence .= ' ' . __( 'The template as it was before could not be kept, in its revisions or in the theme\'s migration record.', 'wpcredits-theme' );
			} else {
				$sentence .= ' ' . ( 'record' === $kept ? __( 'The template as it was before is kept in the theme\'s migration record.', 'wpcredits-theme' ) : __( 'The template as it was before is kept in its revisions.', 'wpcredits-theme' ) );
			}

			$sentences[] = $sentence;

			continue;
		}

		$why  = array(
			'doors-edited'  => __( 'its "Who this is for" section was changed in the Site Editor, so the band of ways to take part was not put in its place', 'wpcredits-theme' ),
			'doors-missing' => __( 'it holds no "Who this is for" section to put the band of ways to take part in place of', 'wpcredits-theme' ),
			'hero-missing'  => __( 'it holds no hero to put the band of ways to take part under', 'wpcredits-theme' ),
			'roles-done'    => __( 'it already holds the band of ways to take part', 'wpcredits-theme' ),
			'update-failed' => __( 'the update could not be saved', 'wpcredits-theme' ),
		);
		$said = array_values( array_intersect_key( $why, array_flip( $skipped ) ) );

		if ( ! $said ) {
			continue;
		}

		/* translators: %s: why the update left the template as it was. */
		$sentence = sprintf( __( 'The theme\'s update to 1.32.12 left the saved homepage template as it was: %s.', 'wpcredits-theme' ), implode( '; ', $said ) );

		if ( in_array( 'doors-edited', $skipped, true ) ) {
			$sentence .= ' ' . __( 'To show it, insert the "Ways to take part" pattern under the hero and remove the section.', 'wpcredits-theme' );
		} elseif ( array_intersect( array( 'doors-missing', 'hero-missing' ), $skipped ) ) {
			$sentence .= ' ' . __( 'To show it, insert the "Ways to take part" pattern where it should stand.', 'wpcredits-theme' );
		}

		$sentences[] = $sentence;
	}

	if ( is_array( $record ) && ! empty( $record['failed'] ) ) {
		$sentences[] = __( 'The theme\'s update to 1.32.12 could not finish updating the saved homepage template; a template it did not reach is as it was.', 'wpcredits-theme' );
	}

	return implode( ' ', $sentences );
}
