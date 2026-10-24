<?php
/**
 * The one-time migration of theme 1.32.0: the saved copy of the Education Hub template gets the
 * editable blocks in place of the two parts the Site Editor could not edit.
 *
 * Once an Administrator saves a template in the Site Editor, WordPress draws the saved copy (a
 * `wp_template` post) and no theme release changes its content again. Live's copy of the Education Hub
 * template (`page-education-hub`) was saved before 1.32.0, so it still holds the 1.31 Program index (a
 * self-closing block that drew the theme's PHP list) and "Find where you fit" as a Custom HTML block.
 * On the first request after the theme updates, this replaces those two, and only those two, in this
 * theme's saved copy (the design of 22 October 2026, D7):
 *
 * - the old Program index becomes the index holding a Program card for each of the theme's programs,
 *   live and in development, followed by the Courses band if the template has none, as the hub
 *   pattern writes them, keeping whatever an Administrator set on the old block, such as a lock; the
 *   index is written marked `editable` (theme 1.32.1), and a self-closing index that already carries
 *   the mark is one an Administrator emptied, which stays empty;
 * - the Custom HTML block that holds the flow's rows becomes the Field of study rows block, its rows
 *   and its labels read from that saved markup, so an Administrator's edits there are kept, and the
 *   theme's own rows written only when none can be read.
 *
 * Every other byte of the template is kept as it was: the two parts are cut out and the blocks put in
 * their place, with nothing parsed and written back around them. The template as it was is kept first
 * as a revision, written with kses turned off and read back to be byte for byte the template (in the
 * record itself where revisions are off for it, or where that revision could not be made exact); the
 * content is written with kses turned off around the write and read back, and what was done to each
 * template, or why it was left, is recorded in the option `wpcredits_migrated_1_32_0`, written last.
 * It runs in a lock, so a request that comes while it runs does nothing; a request that dies part way
 * (a timeout) leaves its lock behind, which is taken over ten minutes on, and the migration runs again
 * (`wpcredits_migrate_lock()`). Whatever throws is caught and recorded as a failure, which is told to
 * Administrators once and not tried again. Run again, it finds nothing to replace and changes nothing.
 * A later save of the template that brings either part back, from an editor opened before the update,
 * is repaired the same way at once. Each Administrator is told what it did on the Site Editor until
 * they dismiss the notice or a week has passed. The Partners template is left alone in 1.32.0; the
 * 1.32.1 migration (inc/migrate-1-32-1.php) replaces its levels and marks the hub's index, after this
 * one has run, and the repair of a save and the notice here cover both.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The option that says the migration ran, and holds what it did. */
const WPCREDITS_MIGRATED_1_32_0 = 'wpcredits_migrated_1_32_0';

/**
 * The block comment attributes' pattern of core's block parser: a JSON object, up to the brace that
 * closes the comment, never past it.
 */
const WPCREDITS_BLOCK_ATTRS = '(\{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?';

/**
 * A saved template's content with its 1.31 parts replaced by the editable blocks: pure, so it is
 * tested on live's saved copy.
 *
 * @param string $content The template's saved content.
 * @param string $slug    Its slug.
 * @return array{content: string, changed: string[], skipped: string[]} The content, what was replaced
 *         (`program-index`, `course-band`, `fit-list`, or `fit-list-theme-rows` when the rows could not
 *         be read), and why a part was left (`program-index-done`, `program-index-emptied`,
 *         `program-index-missing`, `fit-list-done`, `fit-list-missing`, `other-template`).
 */
function wpcredits_migrate_1_32_0_content( $content, $slug ) {
	$result = array(
		'content' => (string) $content,
		'changed' => array(),
		'skipped' => array(),
	);

	if ( 'page-education-hub' !== $slug ) {
		$result['skipped'][] = 'other-template';

		return $result;
	}

	return wpcredits_migrate_1_32_0_fit( wpcredits_migrate_1_32_0_index( $result ) );
}

/**
 * The 1.31 Program index, a self-closing block, replaced by the index holding a card for each of the
 * theme's programs and, when the template has no band of courses, the band after it. A self-closing
 * index marked `editable` is not the 1.31 index but one an Administrator emptied, and is left as it is.
 *
 * @param array $result As `wpcredits_migrate_1_32_0_content()` builds it.
 * @return array The same, the index replaced.
 */
function wpcredits_migrate_1_32_0_index( array $result ) {
	$content = $result['content'];

	if ( ! preg_match( '#<!--\s+wp:wpcredits/program-index\s+' . WPCREDITS_BLOCK_ATTRS . '/-->#s', $content, $found, PREG_OFFSET_CAPTURE ) ) {
		$result['skipped'][] = preg_match( '#<!--\s+wp:wpcredits/program-index\s#', $content ) ? 'program-index-done' : 'program-index-missing';

		return $result;
	}

	$attributes = isset( $found[1] ) && '' !== $found[1][0] ? json_decode( trim( $found[1][0] ), true ) : array();
	$attributes = is_array( $attributes ) ? $attributes : array();

	// A self-closing index marked `editable` is one an Administrator emptied, not the 1.31 index (theme
	// 1.32.1): it stays empty, with no band added under it either.
	if ( ! wpcredits_hub_index_is_legacy( array( 'attrs' => $attributes ) ) ) {
		$result['skipped'][] = 'program-index-emptied';

		return $result;
	}

	$markup  = wpcredits_hub_program_index_markup( wpcredits_hub_programs(), $attributes );
	$changed = array( 'program-index' );

	if ( ! preg_match( '#<!--\s+wp:wpcredits/course-band\s#', $content ) ) {
		$bands = wpcredits_hub_course_bands_markup( wpcredits_hub_programs() );

		if ( '' !== $bands ) {
			$markup   .= "\n\n" . $bands;
			$changed[] = 'course-band';
		}
	}

	$result['content'] = substr_replace( $content, $markup, $found[0][1], strlen( $found[0][0] ) );
	$result['changed'] = array_merge( $result['changed'], $changed );

	return $result;
}

/**
 * "Find where you fit", the Custom HTML block that holds the flow's rows, replaced by the Field of
 * study rows block, its rows and labels read from that markup.
 *
 * @param array $result As `wpcredits_migrate_1_32_0_content()` builds it.
 * @return array The same, the flow replaced.
 */
function wpcredits_migrate_1_32_0_fit( array $result ) {
	$content = $result['content'];
	$offset  = 0;

	while ( preg_match( '#<!--\s+wp:html\s+' . WPCREDITS_BLOCK_ATTRS . '-->(.*?)<!--\s+/wp:html\s+-->#s', $content, $found, PREG_OFFSET_CAPTURE, $offset ) ) {
		$offset = $found[0][1] + strlen( $found[0][0] );

		if ( false === strpos( $found[2][0], 'wpc-hub-flow__rows' ) ) {
			continue;
		}

		$rows   = wpcredits_fit_rows_from_html( $found[2][0] );
		$markup = wpcredits_hub_fit_list_markup( $rows ? $rows : wpcredits_hub_fit_rows(), wpcredits_fit_labels_from_html( $found[2][0] ) );

		$result['content']   = substr_replace( $content, $markup, $found[0][1], strlen( $found[0][0] ) );
		$result['changed'][] = $rows ? 'fit-list' : 'fit-list-theme-rows';

		return $result;
	}

	$result['skipped'][] = preg_match( '#<!--\s+wp:wpcredits/fit-list\s#', $content ) ? 'fit-list-done' : 'fit-list-missing';

	return $result;
}

/**
 * Some markup, parsed to be read, or null where PHP's DOM extension is missing.
 *
 * @param string $html The markup.
 * @return DOMXPath|null
 */
function wpcredits_migrate_1_32_0_xpath( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return null;
	}

	$document = new DOMDocument();
	$errors   = libxml_use_internal_errors( true );

	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $errors );

	return new DOMXPath( $document );
}

/**
 * An XPath test that an element carries a class, as a whole word of its class attribute.
 *
 * @param string $name The class.
 * @return string
 */
function wpcredits_migrate_1_32_0_class( $name ) {
	return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")';
}

/**
 * The text of a node, with any visually hidden label in it left out, whitespace run together.
 *
 * @param DOMNode|null $node The node.
 * @return string
 */
function wpcredits_migrate_1_32_0_text( $node ) {
	if ( ! $node ) {
		return '';
	}

	$node = $node->cloneNode( true );

	// The DOM extension's own property names, not snake case.
	foreach ( iterator_to_array( ( new DOMXPath( $node->ownerDocument ) )->query( './/*[' . wpcredits_migrate_1_32_0_class( 'screen-reader-text' ) . ']', $node ) ) as $hidden ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$hidden->parentNode->removeChild( $hidden ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	return trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
}

/**
 * The rows of a "Find where you fit" flow, read from its markup as theme 1.31 wrote it and an
 * Administrator may have edited it: each row's field, its teams and its proof, as plain text.
 *
 * @param string $html The Custom HTML block's markup.
 * @return array[] Each `field`, `teams` and `proof`, as `wpcredits_hub_fit_rows()` gives them; an empty
 *                 list when no row can be read.
 */
function wpcredits_fit_rows_from_html( $html ) {
	$xpath = wpcredits_migrate_1_32_0_xpath( $html );
	$rows  = array();

	if ( ! $xpath ) {
		return $rows;
	}

	foreach ( $xpath->query( '//li[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__row' ) . ']' ) as $row ) {
		$field = wpcredits_migrate_1_32_0_text( $xpath->query( './/*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__field' ) . ']', $row )->item( 0 ) );
		$teams = array();

		foreach ( $xpath->query( './/*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__tag' ) . ']', $row ) as $tag ) {
			$team = wpcredits_migrate_1_32_0_text( $tag );

			if ( '' !== $team ) {
				$teams[] = $team;
			}
		}

		if ( '' !== $field ) {
			$rows[] = array(
				'field' => $field,
				'teams' => $teams,
				'proof' => wpcredits_migrate_1_32_0_text( $xpath->query( './/*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__shown' ) . ']', $row )->item( 0 ) ),
			);
		}
	}

	return $rows;
}

/**
 * The labels of a "Find where you fit" flow, read from its markup: the header line's three names and
 * the two labels a screen reader hears, each only where the markup has it.
 *
 * @param string $html The Custom HTML block's markup.
 * @return array<string, string> As `wpcredits_hub_fit_labels()` names them, as plain text.
 */
function wpcredits_fit_labels_from_html( $html ) {
	$xpath  = wpcredits_migrate_1_32_0_xpath( $html );
	$labels = array();

	if ( ! $xpath ) {
		return $labels;
	}

	$queries = array(
		'fieldHeading' => '//*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__step--field' ) . ']',
		'teamsHeading' => '//*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__step--teams' ) . ']',
		'proofHeading' => '//*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__step--proof' ) . ']',
		'teamsLabel'   => '//*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__teams' ) . ']/*[' . wpcredits_migrate_1_32_0_class( 'screen-reader-text' ) . ']',
		'proofLabel'   => '//*[' . wpcredits_migrate_1_32_0_class( 'wpc-hub-flow__shown' ) . ']/*[' . wpcredits_migrate_1_32_0_class( 'screen-reader-text' ) . ']',
	);

	foreach ( $queries as $label => $query ) {
		$node = $xpath->query( $query )->item( 0 );
		$text = $node ? trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- The DOM extension's own name.

		if ( '' !== $text ) {
			$labels[ $label ] = $text;
		}
	}

	return $labels;
}

/**
 * The migration, once: this theme's saved Education Hub template, a revision of it kept first, its
 * content written, and what was done recorded last. It runs in its lock (`wpcredits_migrate_lock()`),
 * so a request that comes while it runs does nothing.
 */
function wpcredits_migrate_1_32_0() {
	if ( false !== get_option( WPCREDITS_MIGRATED_1_32_0, false ) ) {
		return;
	}

	$taken = wpcredits_migrate_lock( WPCREDITS_MIGRATED_1_32_0 );

	if ( ! $taken ) {
		return;
	}

	// The lock is let go of once the option is stored, and whatever happens after it was taken.
	try {
		// Another request may have run the migration between the check above and the lock: the option is
		// read again, from the database, and where it is there now this request does nothing more.
		if ( false !== wpcredits_migrate_option_now( WPCREDITS_MIGRATED_1_32_0 ) ) {
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
					$record['templates'][] = wpcredits_migrate_1_32_0_template( $template );
				}
			}
		} catch ( Throwable $error ) {
			$record['failed'] = 'update-failed';
		}

		add_option( WPCREDITS_MIGRATED_1_32_0, $record, '', false );
	} finally {
		wpcredits_migrate_unlock( WPCREDITS_MIGRATED_1_32_0, $taken );
	}
}
add_action( 'init', 'wpcredits_migrate_1_32_0', 20 );

/**
 * A runner's lock, taken before it reads the templates. Without it, two requests that come at once after
 * an update both run the migration: the second can read a template the first has written, keep a second
 * copy of it and store its own record, which says there was nothing to do, before the first stores its
 * own, which is then refused, with the only copy of a template as it was that it may hold.
 *
 * The lock is an option beside the runner's own, its name with `_lock` after it, not autoloaded,
 * holding the time it was taken. add_option() stores nothing where the option is there already, so the
 * request that adds it holds it; core checks before it inserts, so two requests that add it in the same
 * instant can both hold it, a window of that instant rather than of the whole run. A lock taken less
 * than ten minutes ago is a request still running, and this one does nothing. An older one was left by
 * a request that died before it let go (a timeout, a fatal error), and is taken over, the time written
 * in it again, so a lock left behind never stops the migration for good; so is one that holds no time,
 * or a time more than ten minutes ahead, which no running request wrote.
 *
 * The lock alone leaves one window: a request that found the runner's option missing just before
 * another stored it and let go of its lock is then granted the lock. So the runner reads its option
 * again once the lock is taken, from the database (`wpcredits_migrate_option_now()`), and stops where it
 * is there now.
 *
 * @param string $option The runner's option.
 * @return int The time written in the lock, which this request now holds; 0 where it holds none.
 */
function wpcredits_migrate_lock( $option ) {
	$lock = $option . '_lock';
	$now  = time();

	if ( add_option( $lock, $now, '', false ) ) {
		return $now;
	}

	$held = get_option( $lock, 0 );

	if ( is_numeric( $held ) && abs( $now - (int) $held ) < 10 * MINUTE_IN_SECONDS ) {
		return 0;
	}

	return update_option( $lock, $now, false ) ? $now : 0;
}

/**
 * A runner's lock let go of (see `wpcredits_migrate_lock()`), but only while it is still this request's:
 * a run that outlived ten minutes had its lock taken over by another request, whose lock it leaves. The
 * lock is read from the database, since this request's object cache holds the time it wrote itself.
 *
 * @param string $option The runner's option.
 * @param int    $taken  The time this request wrote in the lock.
 */
function wpcredits_migrate_unlock( $option, $taken ) {
	$lock = $option . '_lock';
	$held = (string) wpcredits_migrate_option_now( $lock );

	if ( (string) $taken === $held ) {
		delete_option( $lock );
	}
}

/**
 * An option that is not autoloaded as the database holds it now, not as this request's object cache
 * remembers it: get_option() answers an option this request found missing from the cache's
 * `notoptions`, and one it read or wrote from the cache, so it would not see what another request
 * stored since. That option is taken out of `notoptions` and out of the cache, and read again.
 *
 * @param string $option The option.
 * @return mixed Its value, false where there is none.
 */
function wpcredits_migrate_option_now( $option ) {
	$notoptions = wp_cache_get( 'notoptions', 'options' );

	if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
		unset( $notoptions[ $option ] );
		wp_cache_set( 'notoptions', $notoptions, 'options' );
	}

	wp_cache_delete( $option, 'options' );

	return get_option( $option, false );
}

/**
 * Whether a post is this theme's saved Education Hub template: a `wp_template` named
 * `page-education-hub` whose wp_theme term names this theme. Another theme's copy is not ours.
 *
 * @param mixed $post A post.
 * @return bool
 */
function wpcredits_migrate_1_32_0_ours( $post ) {
	return $post instanceof WP_Post && 'wp_template' === $post->post_type && 'page-education-hub' === $post->post_name && has_term( get_stylesheet(), 'wp_theme', $post );
}

/**
 * One saved template migrated: its content's 1.31 parts replaced and, when any was, written. Where
 * the template as it was is kept: in a revision that holds it exactly, or, where revisions are off for
 * it or no such revision could be made, in the record itself, as `before`.
 *
 * @param WP_Post  $template The template.
 * @param callable $migrate  The migration's content function, which takes the content and the slug and
 *                           answers as `wpcredits_migrate_1_32_0_content()` does: this migration's, the
 *                           1.32.1 migration's, or both (the repair of a save).
 * @return array What was done: `id`, `slug`, `changed`, `skipped` and `kept` (`revision`, `record`,
 *               or '' when nothing was written), and, when something was, `revision` (the revision's
 *               ID, 0 when `kept` is `record`) and, when it is `record`, `before`.
 */
function wpcredits_migrate_1_32_0_template( $template, $migrate = 'wpcredits_migrate_1_32_0_content' ) {
	$before = (string) $template->post_content;
	$result = call_user_func( $migrate, $before, $template->post_name );
	$entry  = array(
		'id'      => (int) $template->ID,
		'slug'    => (string) $template->post_name,
		'changed' => $result['changed'],
		'skipped' => $result['skipped'],
		'kept'    => '',
	);

	if ( ! $result['changed'] ) {
		return $entry;
	}

	$saved = wpcredits_migrate_1_32_0_write( $template->ID, $result['content'] );

	// A write that failed changed nothing; one that saved something else did change the template.
	if ( 'update-failed' === $saved['saved'] ) {
		$entry['changed'] = array();
	} else {
		$entry['kept']     = $saved['kept'];
		$entry['revision'] = $saved['revision'];
	}

	if ( true !== $saved['saved'] ) {
		$entry['skipped'][] = $saved['saved'];
	}

	if ( 'record' === $entry['kept'] ) {
		$entry['before'] = $before;
	}

	return $entry;
}

/**
 * A save that brings the replaced parts back, repaired at once: an editor tab opened before an update
 * holds the template as it was, and saved after the migration it writes the old parts back. When this
 * theme's saved Education Hub or Partners template is saved still holding any, it is migrated as the
 * runners migrate it, both migrations in order, in one write, keeping every other edit of the save:
 * the 1.31 Program index and the Custom HTML flow (1.32.0), the unmarked index and the Custom HTML
 * levels and table (1.32.1). The repair is added to the newer migration's record, the 1.32.1 one once it
 * has run. The migrations' own writes fire this hook too, and are left alone.
 *
 * @param int     $post_id The post saved.
 * @param WP_Post $post    The post, as saved, its terms set.
 */
function wpcredits_migrate_1_32_0_on_save( $post_id, $post ) {
	// A save is never failed by the repair: whatever throws in it, reading the template's terms and
	// recording the repair in the option included, is caught, and the save stands.
	try {
		if ( wpcredits_migrate_1_32_0_writing() || ! wpcredits_migrate_1_32_1_ours( $post ) ) {
			return;
		}

		$entry = wpcredits_migrate_1_32_0_template( $post, 'wpcredits_migrate_1_32_1_repaired' );

		if ( ! $entry['changed'] && ! in_array( 'update-failed', $entry['skipped'], true ) ) {
			return;
		}

		if ( 'page-partners' === $entry['slug'] ) {
			wpcredits_partner_levels_reset();
		}

		$option = is_array( get_option( WPCREDITS_MIGRATED_1_32_1, false ) ) ? WPCREDITS_MIGRATED_1_32_1 : WPCREDITS_MIGRATED_1_32_0;
		$record = get_option( $option, false );

		// The runner records the template itself when it has not run yet.
		if ( is_array( $record ) ) {
			$record['repairs'][] = $entry + array( 'at' => time() );

			// The record that holds the only copy of the template as it was, and could not be stored, is
			// stored saying the copy was lost, never that it was kept.
			if ( ! update_option( $option, $record, false ) && 'record' === $entry['kept'] ) {
				array_pop( $record['repairs'] );

				$record['repairs'][] = wpcredits_migrate_copies_lost( array( $entry ) )[0] + array( 'at' => time() );

				update_option( $option, $record, false );
			}
		}
	} catch ( Throwable $error ) {
		return;
	}
}
add_action( 'wp_after_insert_post', 'wpcredits_migrate_1_32_0_on_save', 10, 2 );

/**
 * Whether the migration is writing a template now, so the save it makes does not set it off again.
 *
 * @param bool|null $now True or false to say it starts or stops writing; null to ask.
 * @return bool
 */
function wpcredits_migrate_1_32_0_writing( $now = null ) {
	static $writing = false;

	if ( null !== $now ) {
		$writing = (bool) $now;
	}

	return $writing;
}

/**
 * The newest revision of a post: a revision, not an autosave, which the next autosave replaces and no
 * one would restore from, as core's wp_save_post_revision() reads the newest one.
 *
 * @param int $id The post's ID.
 * @return WP_Post|null Null where the post has none.
 */
function wpcredits_migrate_newest_revision( $id ) {
	foreach ( wp_get_post_revisions( $id ) as $revision ) {
		if ( false !== strpos( $revision->post_name, $revision->post_parent . '-revision' ) ) {
			return $revision;
		}
	}

	return null;
}

/**
 * A revision of a post as it is now, asked of core with three changes to how core makes one, each a way
 * the revision of live's run failed to hold the template:
 *
 * - kses is off while it is written: the run was a visitor's request, whose saves kses filters, and
 *   it stripped every svg from the revision;
 * - core is told to keep a revision though its comparison with the newest one, whitespace run
 *   together, finds no change, so a newest revision that differs by a newline does not stand for the
 *   copy;
 * - its author is the current user or, with none, the post's own: core's revision data carries no
 *   author, and wp_insert_post() gave the visitor's request the author 0.
 *
 * Kses and the two filters are put back whatever happens. What core answers is not trusted: the caller
 * reads the revision back.
 *
 * @param WP_Post $post The post.
 */
function wpcredits_migrate_put_revision( $post ) {
	$id     = (int) $post->ID;
	$author = get_current_user_id() ? get_current_user_id() : (int) $post->post_author;
	$always = static function () {
		return false;
	};
	$stamp  = static function ( $data ) use ( $id, $author ) {
		if ( isset( $data['post_type'], $data['post_parent'] ) && 'revision' === $data['post_type'] && (int) $data['post_parent'] === $id ) {
			$data['post_author'] = $author;
		}

		return $data;
	};

	kses_remove_filters();
	add_filter( 'wp_save_post_revision_check_for_changes', $always, 99 );
	add_filter( 'wp_insert_post_data', $stamp, 99 );

	try {
		wp_save_post_revision( $id );
	} catch ( Throwable $error ) {
		// A hook that threw may have done so after the revision was stored: the caller reads it back.
		unset( $error );
	} finally {
		remove_filter( 'wp_insert_post_data', $stamp, 99 );
		remove_filter( 'wp_save_post_revision_check_for_changes', $always, 99 );
		kses_init();
	}
}

/**
 * A post as it is now, kept: as a revision that holds its content byte for byte, the newest revision
 * itself where it already does (core keeps one for every save, so a template an Administrator saved
 * has it), and otherwise one written for it and read back. Where revisions are off for the post, or no
 * revision that exact could be made, the record has to keep it.
 *
 * Asking core for a revision and trusting its answer is not enough, as live's run showed: with kses on
 * for a visitor's request the revision held the template without its svg, and the record said it was
 * kept all the same.
 *
 * @param int $id The post's ID.
 * @return array `kept` (`revision`, or `record`), `revision` (the revision's ID, 0 for `record`) and
 *               `before` (the content, for the record to keep, when `kept` is `record`; '' otherwise).
 */
function wpcredits_migrate_keep_copy( $id ) {
	$id   = (int) $id;
	$post = get_post( $id );
	$copy = array(
		'kept'     => 'record',
		'revision' => 0,
		'before'   => $post ? (string) $post->post_content : '',
	);

	if ( ! $post || ! wp_revisions_enabled( $post ) ) {
		return $copy;
	}

	$revision = wpcredits_migrate_newest_revision( $id );

	if ( ! $revision || $revision->post_content !== $copy['before'] ) {
		wpcredits_migrate_put_revision( $post );

		$revision = wpcredits_migrate_newest_revision( $id );
	}

	if ( $revision && $revision->post_content === $copy['before'] ) {
		return array(
			'kept'     => 'revision',
			'revision' => (int) $revision->ID,
			'before'   => '',
		);
	}

	return $copy;
}

/**
 * A template's new content written: a copy of it as it was kept first (a revision that holds it
 * exactly, or the record, see `wpcredits_migrate_keep_copy()`), then the content, slashed as
 * wp_update_post() expects, with kses turned off around the write, so it does not rewrite markup an
 * Administrator saved on a request whose visitor could not have saved it. What is saved is read back,
 * from the database: a write that reported a failure may have been stored before a hook after it
 * threw, and another filter that changed it is reported. The revision kept as the copy is read back
 * after the write as well, since the revision core keeps of the write can make it delete the copy (a
 * site that keeps one revision); a copy that is gone is the record's to keep.
 *
 * @param int    $id       The template's post ID.
 * @param string $content  Its new content.
 * @param bool   $revision Whether to keep a revision of it as it was first; false leaves the copy to the
 *                         record, as it is where revisions are off.
 * @return array `saved`: true, or why it was not saved: `update-failed`, or `saved-differently` when
 *               what was saved is not what was written; `kept` and `revision`: where the copy of the
 *               template as it was is, as `wpcredits_migrate_keep_copy()` says. When `kept` is
 *               `record`, the caller keeps the template as it was in the record.
 */
function wpcredits_migrate_1_32_0_write( $id, $content, $revision = true ) {
	$post   = get_post( $id );
	$before = $post ? (string) $post->post_content : '';
	$copy   = $revision ? wpcredits_migrate_keep_copy( $id ) : array(
		'kept'     => 'record',
		'revision' => 0,
	);

	kses_remove_filters();
	wpcredits_migrate_1_32_0_writing( true );

	// A write that throws is a write that failed, until the post is read back; kses and the guard are
	// restored whatever happens.
	try {
		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => $id,
					'post_content' => $content,
				)
			),
			true
		);
	} catch ( Throwable $error ) {
		$updated = false;
	} finally {
		wpcredits_migrate_1_32_0_writing( false );
		kses_init();
	}

	$reported = ! is_wp_error( $updated ) && $updated;

	if ( ! $reported ) {
		// Core cleans the post's cache after the database write, so a hook that threw between the two
		// leaves the post as it was in the cache, and as it is in the database.
		clean_post_cache( $id );
	}

	$post = get_post( $id );
	$now  = $post ? (string) $post->post_content : null;

	if ( null === $now || ( ! $reported && $now === $before ) ) {
		$saved = 'update-failed';
	} else {
		$saved = $now === $content ? true : 'saved-differently';
	}

	// The copy is read back after the write too: core keeps a revision of what was written, and where the
	// site keeps few (WP_POST_REVISIONS) it then deletes the oldest beyond the limit, which, with one kept,
	// is the copy. A copy no longer there, or no longer the template as it was, is the record's to keep.
	if ( 'revision' === $copy['kept'] && ! wpcredits_migrate_revision_holds( $copy['revision'], $id, $before ) ) {
		$copy = array(
			'kept'     => 'record',
			'revision' => 0,
		);
	}

	return array(
		'saved'    => $saved,
		'kept'     => $copy['kept'],
		'revision' => $copy['revision'],
	);
}

/**
 * Whether a revision of a post is there and holds some content byte for byte.
 *
 * @param int    $revision The revision's ID.
 * @param int    $id       The post's ID.
 * @param string $content  The content.
 * @return bool
 */
function wpcredits_migrate_revision_holds( $revision, $id, $content ) {
	$revision = (int) $revision > 0 ? get_post( (int) $revision ) : null;

	return $revision instanceof WP_Post && 'revision' === $revision->post_type && (int) $revision->post_parent === (int) $id && (string) $revision->post_content === (string) $content;
}

/**
 * What the migration did, said in a sentence or two for each template it reached: what it changed,
 * and what it left and why.
 *
 * @param array $record The option's record.
 * @return string Plain text, '' when it reached no template.
 */
function wpcredits_migrate_1_32_0_summary( $record ) {
	$said = array(
		'program-index'         => __( 'the Program index now holds a Program card for each of the theme\'s programs', 'wpcredits-theme' ),
		'course-band'           => __( 'the Courses band of WordPress Credits\' courses was added under the index', 'wpcredits-theme' ),
		'fit-list'              => __( '"Find where you fit" is now Field of study rows, read from its Custom HTML block', 'wpcredits-theme' ),
		'fit-list-theme-rows'   => __( '"Find where you fit" is now Field of study rows, the theme\'s own, as its Custom HTML block could not be read', 'wpcredits-theme' ),
		'program-index-done'    => __( 'its Program index already holds Program cards', 'wpcredits-theme' ),
		'program-index-emptied' => __( 'its Program index was emptied in the Site Editor, so it stays empty', 'wpcredits-theme' ),
		'program-index-missing' => __( 'it holds no Program index', 'wpcredits-theme' ),
		'fit-list-done'         => __( '"Find where you fit" is already Field of study rows', 'wpcredits-theme' ),
		'fit-list-missing'      => __( 'it holds no "Find where you fit" Custom HTML block', 'wpcredits-theme' ),
		'update-failed'         => __( 'the update could not be saved', 'wpcredits-theme' ),
	);

	$sentences = array();
	$templates = is_array( $record ) && isset( $record['templates'] ) && is_array( $record['templates'] ) ? $record['templates'] : array();

	foreach ( $templates as $template ) {
		$changed = array_values( array_intersect_key( $said, array_flip( (array) $template['changed'] ) ) );
		$skipped = array_values( array_intersect_key( $said, array_flip( (array) $template['skipped'] ) ) );

		if ( $changed ) {
			/* translators: %s: what the update changed in the template, a list. */
			$sentence = sprintf( __( 'The theme\'s update to 1.32.0 changed the saved Education Hub template, so every word of it can be edited here: %s.', 'wpcredits-theme' ), implode( '; ', $changed ) );

			if ( $skipped ) {
				/* translators: %s: what the update left as it was, and why, a list. */
				$sentence .= ' ' . sprintf( __( 'It left the rest as it was: %s.', 'wpcredits-theme' ), implode( '; ', $skipped ) );
			}

			if ( in_array( 'saved-differently', (array) $template['skipped'], true ) ) {
				$sentence .= ' ' . __( 'What was saved differs from what the update wrote, so compare the template with its revisions.', 'wpcredits-theme' );
			}

			$sentences[] = $sentence . ' ' . ( isset( $template['kept'] ) && 'record' === $template['kept'] ? __( 'The template as it was before is kept in the theme\'s migration record.', 'wpcredits-theme' ) : __( 'The template as it was before is kept in its revisions.', 'wpcredits-theme' ) );
		} elseif ( $skipped ) {
			/* translators: %s: why the update left the template as it was, a list. */
			$sentences[] = sprintf( __( 'The theme\'s update to 1.32.0 left the saved Education Hub template as it was: %s.', 'wpcredits-theme' ), implode( '; ', $skipped ) );
		}
	}

	if ( is_array( $record ) && ! empty( $record['failed'] ) ) {
		$sentences[] = __( 'The theme\'s update to 1.32.0 could not finish updating the saved Education Hub template; a template it did not reach is as it was.', 'wpcredits-theme' );
	}

	return implode( ' ', $sentences );
}

/**
 * The migrations whose records the Site Editor's notice tells, oldest first: each record's option, and
 * the function that says what the record holds.
 *
 * @return array<string, callable>
 */
function wpcredits_migrate_notice_records() {
	return array(
		WPCREDITS_MIGRATED_1_32_0 => 'wpcredits_migrate_1_32_0_summary',
		WPCREDITS_MIGRATED_1_32_1 => 'wpcredits_migrate_1_32_1_summary',
	);
}

/**
 * The migrations' notice, on the Site Editor: one editor notice saying what the 1.32.0 and 1.32.1
 * migrations did to the saved templates, the older one's sentences first, for each Administrator until
 * they dismiss it or a week has passed since the newer of the runs that has something to tell, whichever
 * comes first. A record the Administrator dismissed before is not told again, and its option not read.
 * A default editor notice shows only inside the editor, not on the Site Editor's browse view, so it is
 * not taken as seen when it is merely enqueued: its dismissal tells the site which records it told
 * (`wpcredits_migrate_1_32_0_dismiss()`).
 */
function wpcredits_migrate_1_32_0_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'site-editor' !== $screen->id || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$told      = array();
	$sentences = array();
	$newest    = 0;

	foreach ( wpcredits_migrate_notice_records() as $option => $summary ) {
		if ( get_user_meta( get_current_user_id(), $option . '_seen', true ) ) {
			continue;
		}

		// The options are not autoloaded, so each is read once the notice is known to be for this request.
		$record  = get_option( $option, array() );
		$message = is_array( $record ) && ! empty( $record['ran'] ) ? call_user_func( $summary, $record ) : '';

		if ( '' !== $message ) {
			$told[]      = $option;
			$sentences[] = $message;
			$newest      = max( $newest, (int) $record['ran'] );
		}
	}

	if ( ! $sentences || time() > $newest + WEEK_IN_SECONDS ) {
		return;
	}

	$script = 'wp.domReady( function () {'
		. ' wp.data.dispatch( "core/notices" ).createNotice( "info", %1$s, {'
		. ' id: "wpcredits-migration-1-32-0",'
		. ' isDismissible: true,'
		. ' onDismiss: function () {'
		. ' var body = new window.FormData();'
		. ' body.append( "action", "wpcredits_migration_1_32_0_seen" );'
		. ' body.append( "_ajax_nonce", %2$s );'
		. ' body.append( "told", %4$s );'
		. ' window.fetch( %3$s, { method: "POST", credentials: "same-origin", body: body } );'
		. ' } } ); } );';

	wp_register_script( 'wpcredits-migration-1-32-0', false, array( 'wp-data', 'wp-notices', 'wp-dom-ready' ), WPCREDITS_VERSION, true );
	wp_enqueue_script( 'wpcredits-migration-1-32-0' );
	wp_add_inline_script( 'wpcredits-migration-1-32-0', sprintf( $script, wp_json_encode( implode( ' ', $sentences ) ), wp_json_encode( wp_create_nonce( 'wpcredits_migration_1_32_0_seen' ) ), wp_json_encode( admin_url( 'admin-ajax.php' ) ), wp_json_encode( implode( ',', $told ) ) ) );
}
add_action( 'enqueue_block_editor_assets', 'wpcredits_migrate_1_32_0_notice' );

/**
 * The notice dismissed, told by its request to admin-ajax.php: the Administrator who dismissed it is
 * not told the records it told again. The request names them (`told`), and only the migrations' own
 * records are marked; a request that names none was sent by a notice theme 1.32.0 printed, which told
 * the 1.32.0 record alone. A request without the reader's nonce, or from someone who cannot edit the
 * theme, marks nothing.
 */
function wpcredits_migrate_1_32_0_dismiss() {
	check_ajax_referer( 'wpcredits_migration_1_32_0_seen' );

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( null, 403 );
	}

	$told = isset( $_POST['told'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['told'] ) ) ) : array( WPCREDITS_MIGRATED_1_32_0 );

	foreach ( array_intersect( array_keys( wpcredits_migrate_notice_records() ), $told ) as $option ) {
		update_user_meta( get_current_user_id(), $option . '_seen', time() );
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_wpcredits_migration_1_32_0_seen', 'wpcredits_migrate_1_32_0_dismiss' );
