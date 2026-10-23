<?php
/**
 * The one-time migration of theme 1.32.1: the saved copy of the Partners template gets the Partner
 * levels and Levels at a glance blocks in place of the Custom HTML blocks that held its levels, and the
 * saved copy of the Education Hub template gets its Program index marked `editable`.
 *
 * Once an Administrator saves a template in the Site Editor, WordPress draws the saved copy (a
 * `wp_template` post) and no theme release changes its content again. Live's copy of the Partners
 * template (`page-partners`) was saved from 1.31.11, so its levels are two Custom HTML blocks: the cards
 * (`ul.wpc-partners-levels__grid`) in the #levels section and the comparison table in the #glance one.
 * Live's copy of the Education Hub template (`page-education-hub`) was migrated by 1.32.0, whose Program
 * index holds its cards but carries no mark, since the mark came with 1.32.1 (see
 * `wpcredits_hub_index_is_legacy()`). On the first request after the theme updates, after the 1.32.0
 * migration has run, this replaces those parts, and only those (the design of 22 October 2026, D7, as
 * ruling R1 has it):
 *
 * - in the Partners template, the Custom HTML block that holds the cards becomes the Partner levels
 *   block, its levels read from that markup, the short amounts from the table's head, so an
 *   Administrator's edits there are kept. A well-formed grid that holds no level (every card removed,
 *   or none with a name) becomes the emptied block, so the page and the sponsor application still show
 *   none; the theme's own levels are written only for markup that is not a readable grid, and that is
 *   said. The Custom HTML block that holds the table becomes the Levels at a glance block, which draws
 *   the table from those levels, its head word and its two cell words kept where an Administrator
 *   changed them, but only where there are levels to draw it from: the cards converted in the same run,
 *   or a levels block the template already holds. A table without either stays its Custom HTML block,
 *   still shown;
 * - in the Education Hub template, a Program index that holds Program cards and is not marked gains
 *   `"editable":true`, written first among its attributes as the Site Editor writes them. The page draws
 *   it as it did; the mark only tells an index an Administrator emptied from the 1.31 index.
 *
 * Every other byte of each template is kept as it was: each part is cut out and its replacement put in
 * its place, with nothing parsed and written back around it. The copy of the template as it was and the
 * write are the 1.32.0 migration's own (`wpcredits_migrate_keep_copy()`, `wpcredits_migrate_1_32_0_write()`):
 * a revision that holds it byte for byte, or the record where revisions are off. What was done to each
 * template, or why it was left, is recorded in the option `wpcredits_migrated_1_32_1`, written last; a
 * record that holds the only copy of a template and cannot be stored is stored without it, saying the
 * copy was lost rather than kept. Whatever throws is caught and recorded as a failure. Run again, it finds
 * nothing to replace and changes nothing.
 *
 * The 1.32.0 migration runs first and this one waits for it: on a site that updates from 1.31 straight
 * to 1.32.1 both run on the first request, in that order, and neither undoes the other (the index 1.32.0
 * writes is marked already, and 1.32.0 leaves the Partners template alone). Where the 1.32.0 runner
 * failed it is not run again, and this one leaves that hub's 1.31 index to it. The repair of a save from
 * an editor tab opened before the update, and the Site Editor's notice, live in inc/migrate-1-32-0.php and
 * cover both migrations.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The option that says the migration ran, and holds what it did. */
const WPCREDITS_MIGRATED_1_32_1 = 'wpcredits_migrated_1_32_1';

/** The slugs of the saved templates the 1.32.1 migration, and the repair of a save, reach. */
const WPCREDITS_MIGRATE_1_32_1_SLUGS = array( 'page-partners', 'page-education-hub' );

/**
 * A saved template's content with its 1.31 levels replaced by the blocks, or its Program index marked:
 * pure, so it is tested on live's saved copies.
 *
 * @param string $content The template's saved content.
 * @param string $slug    Its slug.
 * @return array{content: string, changed: string[], skipped: string[]} The content; what was replaced
 *         (`partner-levels`, `partner-levels-emptied` for a grid that holds no level, `partner-glance`,
 *         `program-index-marked`); and why a part was left (`partner-levels-done`,
 *         `partner-levels-missing`, `partner-glance-done`, `partner-glance-missing`,
 *         `partner-glance-no-levels`, `program-index-marked-done`, `program-index-emptied`,
 *         `program-index-legacy`, `program-index-missing`, `other-template`), or, with `partner-levels`
 *         changed, `partner-levels-unreadable`: the markup is not a readable grid, so the theme's own
 *         levels were written.
 */
function wpcredits_migrate_1_32_1_content( $content, $slug ) {
	$result = array(
		'content' => (string) $content,
		'changed' => array(),
		'skipped' => array(),
	);

	if ( 'page-partners' === $slug ) {
		return wpcredits_migrate_1_32_1_partners( $result );
	}

	if ( 'page-education-hub' === $slug ) {
		return wpcredits_migrate_1_32_1_index( $result );
	}

	$result['skipped'][] = 'other-template';

	return $result;
}

/**
 * The Partners template's two Custom HTML blocks of theme 1.31, the cards and the table, replaced by the
 * Partner levels block, its levels read from the cards and the table's head, and the Levels at a glance
 * block. Each is the first Custom HTML block that holds it; one block that holds both becomes both
 * blocks, in the order its markup had them.
 *
 * A template that already holds the Partner levels block, or the Levels at a glance block, was saved
 * from an editor of 1.32.1, the only one that can insert them: a Custom HTML block beside it that holds
 * the same part is an Administrator's own, not a stale tab's, and is left whole.
 *
 * The cards' grid that holds no level, well formed, becomes the emptied levels block: the theme's levels
 * written there would put back levels an Administrator removed, on the page and in the sponsor
 * application. The table becomes its block only where there are levels to draw it from, the cards
 * converted here or a levels block already in the template: the block draws nothing without them,
 * where the Custom HTML block shows the table.
 *
 * @param array $result As `wpcredits_migrate_1_32_1_content()` builds it.
 * @return array The same, the two replaced.
 */
function wpcredits_migrate_1_32_1_partners( array $result ) {
	$content = $result['content'];
	$cards   = null;
	$table   = null;
	$has     = array(
		'levels' => (bool) preg_match( '#<!--\s+wp:wpcredits/partner-levels\s#', $content ),
		'glance' => (bool) preg_match( '#<!--\s+wp:wpcredits/partner-glance\s#', $content ),
	);

	preg_match_all( '#<!--\s+wp:html\s+' . WPCREDITS_BLOCK_ATTRS . '-->(.*?)<!--\s+/wp:html\s+-->#s', $content, $blocks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

	foreach ( $blocks as $at => $block ) {
		$holds_cards = wpcredits_migrate_1_32_1_has_class( $block[2][0], 'wpc-partners-levels__grid' );
		$holds_table = wpcredits_migrate_1_32_1_has_class( $block[2][0], 'wpc-partners-glance__table' );

		// A block holding a part the template already has as a block is left whole, all it holds.
		if ( ( $holds_cards && $has['levels'] ) || ( $holds_table && $has['glance'] ) ) {
			continue;
		}

		if ( null === $cards && $holds_cards ) {
			$cards = $at;
		}

		if ( null === $table && $holds_table ) {
			$table = $at;
		}
	}

	// The table is drawn from the template's levels: with no cards converted here and no levels block, the
	// block would draw nothing, so the Custom HTML block that shows the table is left.
	$no_levels = null !== $table && null === $cards && ! $has['levels'];

	if ( $no_levels ) {
		$table = null;
	}

	if ( null === $cards ) {
		$result['skipped'][] = $has['levels'] ? 'partner-levels-done' : 'partner-levels-missing';
	}

	if ( null === $table ) {
		$result['skipped'][] = $no_levels ? 'partner-glance-no-levels' : ( $has['glance'] ? 'partner-glance-done' : 'partner-glance-missing' );
	}

	// Each block's replacement, by its place in the blocks found, written from the last to the first so
	// the offsets of those before it stay true.
	$replace = array();

	if ( null !== $cards ) {
		$levels  = wpcredits_partner_levels_from_html( $blocks[ $cards ][2][0] . ( null !== $table && $table !== $cards ? $blocks[ $table ][2][0] : '' ) );
		$emptied = ! $levels && wpcredits_migrate_1_32_1_grid_found( $blocks[ $cards ][2][0] );

		// Only markup that is not a readable grid gets the theme's levels; a grid that holds none stays empty.
		if ( ! $levels && ! $emptied ) {
			$levels              = wpcredits_partner_levels_defaults();
			$result['skipped'][] = 'partner-levels-unreadable';
		}

		$replace[ $cards ]   = array( wpcredits_partner_levels_markup( $levels ) );
		$result['changed'][] = $emptied ? 'partner-levels-emptied' : 'partner-levels';
	}

	if ( null !== $table ) {
		$glance = wpcredits_migrate_1_32_1_glance_markup( $blocks[ $table ][2][0] );

		if ( $table === $cards ) {
			$html  = $blocks[ $table ][2][0];
			$first = wpcredits_migrate_1_32_1_class_at( $html, 'wpc-partners-glance__table' ) < wpcredits_migrate_1_32_1_class_at( $html, 'wpc-partners-levels__grid' );

			$replace[ $table ] = $first ? array( $glance, $replace[ $table ][0] ) : array( $replace[ $table ][0], $glance );
		} else {
			$replace[ $table ] = array( $glance );
		}

		$result['changed'][] = 'partner-glance';
	}

	krsort( $replace );

	foreach ( $replace as $at => $markup ) {
		$content = substr_replace( $content, implode( "\n\n", $markup ), $blocks[ $at ][0][1], strlen( $blocks[ $at ][0][0] ) );
	}

	$result['content'] = $content;

	return $result;
}

/**
 * Where in some markup an element first carries a class, as a whole word of its class attribute.
 *
 * @param string $html The markup.
 * @param string $name The class.
 * @return int|null The offset of the class attribute, or null where no element carries it.
 */
function wpcredits_migrate_1_32_1_class_at( $html, $name ) {
	$word = preg_quote( $name, '#' );

	return preg_match( '#(?<![\w-])class\s*=\s*(["\'])(?:(?!\1).)*?(?<![\w-])' . $word . '(?![\w-])#s', (string) $html, $found, PREG_OFFSET_CAPTURE ) ? (int) $found[0][1] : null;
}

/**
 * Whether an element in some markup carries a class.
 *
 * @param string $html The markup.
 * @param string $name The class.
 * @return bool
 */
function wpcredits_migrate_1_32_1_has_class( $html, $name ) {
	return null !== wpcredits_migrate_1_32_1_class_at( $html, $name );
}

/**
 * Whether some markup holds the cards' grid as theme 1.31 wrote it: the first element that carries the
 * class `wpc-partners-levels__grid` is a list (`ul`). Such a grid that holds no level was emptied by an
 * Administrator; markup without it is not a grid that can be read. False where PHP's DOM extension is
 * missing, so nothing is taken as emptied that could not be read.
 *
 * @param string $html The Custom HTML block's markup.
 * @return bool
 */
function wpcredits_migrate_1_32_1_grid_found( $html ) {
	$xpath = wpcredits_migrate_1_32_0_xpath( $html );
	$grid  = $xpath ? $xpath->query( '(//*[' . wpcredits_partners_class( 'wpc-partners-levels__grid' ) . '])[1]' )->item( 0 ) : null;

	return $grid && 'ul' === strtolower( $grid->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- The DOM extension's own property name.
}

/**
 * The Levels at a glance block for the 1.31 table in some markup: its head word and the words of its
 * Yes and No cells, read from the table, are written as the block's attributes where they are not the
 * theme's, so an Administrator who changed them keeps them; the theme's words write no attribute, as the
 * Site Editor leaves out an attribute at its default.
 *
 * @param string $html The Custom HTML block's markup.
 * @return string Block markup.
 */
function wpcredits_migrate_1_32_1_glance_markup( $html ) {
	$xpath = wpcredits_migrate_1_32_0_xpath( $html );

	if ( ! $xpath ) {
		return wpcredits_partner_glance_markup();
	}

	$table   = '(//table[' . wpcredits_partners_class( 'wpc-partners-glance__table' ) . '])[1]';
	$queries = array(
		'label' => $table . '/thead/tr[1]/th[1]',
		'yes'   => $table . '//td[' . wpcredits_partners_class( 'wpc-partners-glance__yes' ) . ']',
		'no'    => $table . '//td[' . wpcredits_partners_class( 'wpc-partners-glance__no' ) . ']',
	);
	$theirs  = wpcredits_partner_glance_words( array() );
	$words   = array();

	foreach ( $queries as $word => $query ) {
		$text = wpcredits_partners_text( $xpath->query( $query )->item( 0 ) );

		if ( '' !== $text && $theirs[ $word ] !== $text ) {
			$words[ $word ] = $text;
		}
	}

	return $words ? wpcredits_hub_block_markup( 'wpcredits/partner-glance', $words ) : wpcredits_partner_glance_markup();
}

/**
 * The Education Hub template's Program index marked `editable`, where it holds Program cards and is
 * not marked yet: the mark is put first in its opening comment, and every other byte stays as it was.
 * An index already marked, an emptied one (marked, self-closing), the 1.31 index (self-closing and not
 * marked, which only the 1.32.0 migration replaces) and an unmarked index with no card (which the page
 * draws as the 1.31 index, so the mark would change it) are left as they are.
 *
 * @param array $result As `wpcredits_migrate_1_32_1_content()` builds it.
 * @return array The same, the index marked.
 */
function wpcredits_migrate_1_32_1_index( array $result ) {
	$content = $result['content'];
	$reasons = array();

	preg_match_all( '#<!--\s+wp:wpcredits/program-index\s+' . WPCREDITS_BLOCK_ATTRS . '(/?)-->#s', $content, $indexes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

	foreach ( $indexes as $index ) {
		$text       = isset( $index[1] ) && -1 !== $index[1][1] ? $index[1][0] : '';
		$attributes = '' !== $text ? json_decode( trim( $text ), true ) : array();
		$marked     = is_array( $attributes ) && isset( $attributes['editable'] ) && true === $attributes['editable'];
		$closed     = '/' === $index[2][0];
		$end        = $index[0][1] + strlen( $index[0][0] );

		if ( $marked ) {
			$reasons[] = $closed ? 'program-index-emptied' : 'program-index-marked-done';
			continue;
		}

		if ( $closed || ! wpcredits_migrate_1_32_1_index_has_card( $content, $end ) ) {
			$reasons[] = 'program-index-legacy';
			continue;
		}

		if ( '' === $text ) {
			// No attributes: the mark goes before the comment's end, after the space that ends the name.
			$content = substr_replace( $content, '{"editable":true} ', $end - 3, 0 );
		} elseif ( is_array( $attributes ) && ! array_key_exists( 'editable', $attributes ) && '{' === $text[0] ) {
			// The mark goes first in the object, the rest of it untouched.
			$content = substr_replace( $content, '"editable":true' . ( $attributes ? ',' : '' ), $index[1][1] + 1, 0 );
		} else {
			// A mark that is not the boolean true, or attributes that cannot be read: written again, the
			// mark first, as the Site Editor would.
			$content = substr_replace( $content, serialize_block_attributes( array( 'editable' => true ) + ( is_array( $attributes ) ? $attributes : array() ) ), $index[1][1], strlen( rtrim( $text ) ) );
		}

		$result['content']   = $content;
		$result['changed'][] = 'program-index-marked';

		return $result;
	}

	$result['skipped'][] = $reasons ? $reasons[0] : 'program-index-missing';

	return $result;
}

/**
 * Whether a Program index whose opening comment ends at an offset holds a Program card before its
 * closing comment.
 *
 * @param string $content The template's content.
 * @param int    $from    Where the index's opening comment ends.
 * @return bool
 */
function wpcredits_migrate_1_32_1_index_has_card( $content, $from ) {
	if ( ! preg_match( '#<!--\s+/wp:wpcredits/program-index\s+-->#', $content, $close, PREG_OFFSET_CAPTURE, $from ) ) {
		return false;
	}

	return (bool) preg_match( '#<!--\s+wp:wpcredits/program-card\s#', substr( $content, $from, $close[0][1] - $from ) );
}

/**
 * A saved template's content through both migrations, the 1.32.0 one first: what the repair of a save
 * writes. A migration that does not reach the template's slug says nothing about it.
 *
 * @param string $content The template's content, as saved.
 * @param string $slug    Its slug.
 * @return array{content: string, changed: string[], skipped: string[]} As the migrations' content
 *         functions give it, the two migrations' lists one after the other.
 */
function wpcredits_migrate_1_32_1_repaired( $content, $slug ) {
	$first  = wpcredits_migrate_1_32_0_content( $content, $slug );
	$second = wpcredits_migrate_1_32_1_content( $first['content'], $slug );

	return array(
		'content' => $second['content'],
		'changed' => array_merge( $first['changed'], $second['changed'] ),
		'skipped' => array_values( array_diff( array_merge( $first['skipped'], $second['skipped'] ), array( 'other-template' ) ) ),
	);
}

/**
 * The migration, once, after the 1.32.0 migration's: this theme's saved Partners and Education Hub
 * templates, a copy of each kept first, its content written, and what was done recorded last.
 */
function wpcredits_migrate_1_32_1() {
	// The 1.32.0 migration runs first: until its option is set, this one waits.
	if ( false !== get_option( WPCREDITS_MIGRATED_1_32_1, false ) || false === get_option( WPCREDITS_MIGRATED_1_32_0, false ) ) {
		return;
	}

	$record = array(
		'ran'       => time(),
		'templates' => array(),
	);

	// Whatever throws is caught and recorded, and the option is set all the same: a failure is reported
	// to Administrators once, not tried again on every request.
	try {
		foreach ( WPCREDITS_MIGRATE_1_32_1_SLUGS as $slug ) {
			$templates = get_posts(
				array(
					'post_type'      => 'wp_template',
					'post_status'    => 'any',
					'name'           => $slug,
					'posts_per_page' => 20,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			foreach ( $templates as $template ) {
				if ( wpcredits_migrate_1_32_1_ours( $template ) ) {
					$record['templates'][] = wpcredits_migrate_1_32_0_template( $template, 'wpcredits_migrate_1_32_1_content' );
				}
			}
		}
	} catch ( Throwable $error ) {
		$record['failed'] = 'update-failed';
	}

	// The levels read in this request were read from the template before it was written.
	wpcredits_partner_levels_reset();

	if ( ! add_option( WPCREDITS_MIGRATED_1_32_1, $record, '', false ) ) {
		$lost = wpcredits_migrate_copies_lost( $record['templates'] );

		// The record was the only copy of a template as it was, and could not be stored: the record
		// stored in its place says the copy was lost, never that it was kept.
		if ( $lost !== $record['templates'] ) {
			$record['templates'] = $lost;

			add_option( WPCREDITS_MIGRATED_1_32_1, $record, '', false );
		}
	}
}
add_action( 'init', 'wpcredits_migrate_1_32_1', 21 );

/**
 * Whether a post is one of this theme's saved templates the 1.32.1 migration reaches: a `wp_template`
 * named `page-partners` or `page-education-hub` whose wp_theme term names this theme.
 *
 * @param mixed $post A post.
 * @return bool
 */
function wpcredits_migrate_1_32_1_ours( $post ) {
	return $post instanceof WP_Post && 'wp_template' === $post->post_type && in_array( $post->post_name, WPCREDITS_MIGRATE_1_32_1_SLUGS, true ) && has_term( get_stylesheet(), 'wp_theme', $post );
}

/**
 * What the migrations recorded for some templates, with every copy the record was to keep said lost:
 * what is stored in place of a record that holds the only copy of a template and could not be stored.
 *
 * @param array[] $entries What was done to each template, as `wpcredits_migrate_1_32_0_template()` says.
 * @return array[] The same, each `kept` that was `record` now `lost`, its `before` taken out.
 */
function wpcredits_migrate_copies_lost( array $entries ) {
	foreach ( $entries as $at => $entry ) {
		if ( is_array( $entry ) && isset( $entry['kept'] ) && 'record' === $entry['kept'] ) {
			$entries[ $at ]['kept'] = 'lost';
			unset( $entries[ $at ]['before'] );
		}
	}

	return $entries;
}

/**
 * What the migration did, said in a sentence or two for each template it reached: what it changed, what
 * it left and why, and where the template as it was is kept.
 *
 * @param array $record The option's record.
 * @return string Plain text, '' when it reached no template.
 */
function wpcredits_migrate_1_32_1_summary( $record ) {
	$said = array(
		'partner-levels'            => __( 'the partnership levels are now the Partner levels block, read from its Custom HTML block', 'wpcredits-theme' ),
		'partner-levels-emptied'    => __( 'its Custom HTML block of partnership levels held no level, so it is now an empty Partner levels block: until levels are added to it, the page shows no levels and no table, and the sponsor application lists none', 'wpcredits-theme' ),
		'partner-glance'            => __( 'the Levels at a glance table is now its own block, drawn from those levels', 'wpcredits-theme' ),
		'program-index-marked'      => __( 'its Program index is now marked as edited in the Site Editor, so removing every card from it leaves it empty', 'wpcredits-theme' ),
		'partner-levels-done'       => __( 'its partnership levels are already the Partner levels block', 'wpcredits-theme' ),
		'partner-levels-missing'    => __( 'it holds no Custom HTML block of partnership levels', 'wpcredits-theme' ),
		'partner-glance-done'       => __( 'its Levels at a glance table is already its own block', 'wpcredits-theme' ),
		'partner-glance-missing'    => __( 'it holds no Custom HTML block of the Levels at a glance table', 'wpcredits-theme' ),
		'partner-glance-no-levels'  => __( 'its Levels at a glance table stays a Custom HTML block, as the template holds no partnership levels to draw it from', 'wpcredits-theme' ),
		'program-index-marked-done' => __( 'its Program index is already marked', 'wpcredits-theme' ),
		'program-index-emptied'     => __( 'its Program index was emptied in the Site Editor, so it stays empty', 'wpcredits-theme' ),
		'program-index-legacy'      => __( 'its Program index is still as theme 1.31 saved it, so it still shows the theme\'s own programs', 'wpcredits-theme' ),
		'program-index-missing'     => __( 'it holds no Program index', 'wpcredits-theme' ),
		'update-failed'             => __( 'the update could not be saved', 'wpcredits-theme' ),
	);

	$templates = array(
		/* translators: %s: what the update changed in the template, a list. */
		'page-partners'      => array( __( 'The theme\'s update to 1.32.1 changed the saved Partners template, so its partnership levels are edited here and the sponsor application shows the same ones: %s.', 'wpcredits-theme' ), __( 'The theme\'s update to 1.32.1 left the saved Partners template as it was: %s.', 'wpcredits-theme' ) ),
		/* translators: %s: what the update changed in the template, a list. */
		'page-education-hub' => array( __( 'The theme\'s update to 1.32.1 changed the saved Education Hub template: %s.', 'wpcredits-theme' ), __( 'The theme\'s update to 1.32.1 left the saved Education Hub template as it was: %s.', 'wpcredits-theme' ) ),
	);

	$sentences = array();
	$reached   = is_array( $record ) && isset( $record['templates'] ) && is_array( $record['templates'] ) ? $record['templates'] : array();

	foreach ( $reached as $template ) {
		if ( ! is_array( $template ) || ! isset( $template['slug'] ) || ! isset( $templates[ $template['slug'] ] ) ) {
			continue;
		}

		$changed = array_values( array_intersect_key( $said, array_flip( (array) $template['changed'] ) ) );
		$skipped = array_values( array_intersect_key( $said, array_flip( (array) $template['skipped'] ) ) );

		if ( $changed ) {
			$sentence = sprintf( $templates[ $template['slug'] ][0], implode( '; ', $changed ) );

			if ( $skipped ) {
				/* translators: %s: what the update left as it was, and why, a list. */
				$sentence .= ' ' . sprintf( __( 'It left the rest as it was: %s.', 'wpcredits-theme' ), implode( '; ', $skipped ) );
			}

			if ( in_array( 'partner-levels-unreadable', (array) $template['skipped'], true ) ) {
				$sentence .= ' ' . __( 'Its partnership levels could not be read from its Custom HTML block, so the theme\'s own levels were written in their place: compare them with the template as it was.', 'wpcredits-theme' );
			}

			if ( in_array( 'saved-differently', (array) $template['skipped'], true ) ) {
				$sentence .= ' ' . __( 'What was saved differs from what the update wrote, so compare the template with its revisions.', 'wpcredits-theme' );
			}

			$kept = isset( $template['kept'] ) ? $template['kept'] : '';

			if ( 'lost' === $kept ) {
				$sentence .= ' ' . __( 'The template as it was before could not be kept, in its revisions or in the theme\'s migration record.', 'wpcredits-theme' );
			} else {
				$sentence .= ' ' . ( 'record' === $kept ? __( 'The template as it was before is kept in the theme\'s migration record.', 'wpcredits-theme' ) : __( 'The template as it was before is kept in its revisions.', 'wpcredits-theme' ) );
			}

			$sentences[] = $sentence;
		} elseif ( $skipped ) {
			$sentences[] = sprintf( $templates[ $template['slug'] ][1], implode( '; ', $skipped ) );
		}
	}

	if ( is_array( $record ) && ! empty( $record['failed'] ) ) {
		$sentences[] = __( 'The theme\'s update to 1.32.1 could not finish updating the saved Partners and Education Hub templates; a template it did not reach is as it was.', 'wpcredits-theme' );
	}

	return implode( ' ', $sentences );
}
