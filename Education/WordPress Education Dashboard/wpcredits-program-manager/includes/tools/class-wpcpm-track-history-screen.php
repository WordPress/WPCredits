<?php
/**
 * The Track Builder's History screen: what publishing would change, every save against the one
 * before it, and the publish log (the design's decision 28).
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draws what `WPCPM_Track_Builder::history()` read, and asks nothing of the store itself.
 *
 * Three parts, in the order a person wants them (the design's section 5): the published copy
 * against the draft, which is the question before pressing Publish; the revisions, newest first,
 * each as the question-level diff against the one before; and the publish log. A diff prints in
 * the words the question screen and the track form use for the same properties, so a line here
 * reads as the row it points at. Where the site caps how many saves it keeps, the saves part says
 * so and stops calling its oldest entry the creation (the final review of T3b).
 */
final class WPCPM_Track_History_Screen {

	/**
	 * The screen.
	 *
	 * @param array $args `track`, `label`, `pending` (the published copy against the draft, or
	 *                    null when nothing was published), `revisions` (each `at`, `by`, a `diff`
	 *                    or a `created` count, and `pruned`), `more` (whether older saves exist
	 *                    than are shown), `cap` (how many saves this site keeps, -1 for no cap),
	 *                    `pruned` (whether the oldest shown may be a survivor of the site's
	 *                    pruning), `log` (newest first), `items` (checklist item => its label, for
	 *                    the log's ticks), and the screen's `url`.
	 */
	public static function render( array $args ) {
		$track     = isset( $args['track'] ) ? (int) $args['track'] : 0;
		$label     = isset( $args['label'] ) ? (string) $args['label'] : '';
		$pending   = isset( $args['pending'] ) && is_array( $args['pending'] ) ? $args['pending'] : null;
		$revisions = isset( $args['revisions'] ) && is_array( $args['revisions'] ) ? $args['revisions'] : array();
		$cap       = isset( $args['cap'] ) ? (int) $args['cap'] : -1;
		$log       = isset( $args['log'] ) && is_array( $args['log'] ) ? $args['log'] : array();
		$items     = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';

		echo '<h2>';
		printf(
			/* translators: %s: the track's name. */
			esc_html__( 'History of %s', 'wpcredits-program-manager' ),
			esc_html( $label )
		);
		echo '</h2>';

		if ( '' !== $url ) {
			printf(
				'<p><a href="%1$s">%2$s</a> <a href="%3$s">%4$s</a></p>',
				esc_url( add_query_arg( 'wpcpm_track', $track, $url ) ),
				esc_html__( 'Back to the track', 'wpcredits-program-manager' ),
				esc_url( $url ),
				esc_html__( 'Back to every track', 'wpcredits-program-manager' )
			);
		}

		self::render_pending( $pending );
		self::render_revisions( $revisions, ! empty( $args['more'] ), ! empty( $args['pruned'] ), $cap );
		self::render_log( $log, $items );
	}

	/**
	 * The published copy against the draft: the question a person has before pressing Publish.
	 *
	 * @param array|null $pending The diff, or null when the track was never published.
	 */
	private static function render_pending( $pending ) {
		echo '<h3>' . esc_html__( 'What publishing would change', 'wpcredits-program-manager' ) . '</h3>';

		if ( null === $pending ) {
			echo '<p>' . esc_html__( 'This track has never been published, so there is no published copy to compare the draft with.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		if ( ! empty( $pending['same'] ) ) {
			echo '<p>' . esc_html__( 'The published copy is the same as the draft: publishing would change nothing.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		self::render_diff( $pending );
	}

	/**
	 * Every save, newest first, each against the one before it.
	 *
	 * @param array[] $revisions Each `at`, `by`, a `diff` or a `created` count, and `pruned`.
	 * @param bool    $more      Whether older saves exist than are shown.
	 * @param bool    $pruned    Whether the list stands at the site's cap, so the oldest entry may
	 *                           be a survivor of the site's pruning rather than the creation.
	 * @param int     $cap       How many saves this site keeps, -1 when nothing caps them.
	 */
	private static function render_revisions( array $revisions, $more, $pruned, $cap ) {
		echo '<h3>' . esc_html__( 'Saves', 'wpcredits-program-manager' ) . '</h3>';

		if ( array() === $revisions ) {
			// WordPress keeps a copy of each save only while revisions are on (`WP_POST_REVISIONS`),
			// and the store cannot make one where the site refuses them.
			echo '<p>' . esc_html__( 'No saves have been kept for this track. WordPress keeps a copy of each save only while revisions are on.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<ol class="wpcpm-history">';

		foreach ( $revisions as $revision ) {
			echo '<li class="wpcpm-history__revision">';
			echo '<p class="wpcpm-history__meta">' . esc_html( WPCPM_Track_Builder_Screen::when_and_who( isset( $revision['at'] ) ? (int) $revision['at'] : 0, isset( $revision['by'] ) ? (int) $revision['by'] : 0 ) ) . '</p>';

			if ( isset( $revision['created'] ) ) {
				$count = (int) $revision['created'];

				if ( ! empty( $revision['pruned'] ) ) {
					// The list stands at the site's cap, so what this save has no predecessor for
					// is the pruning, not the creation. Both sentences are one string: a translator
					// needs the whole thought to order it.
					$sentence = sprintf(
						/* translators: %d: how many questions the track had at the oldest save kept. */
						_n( 'The oldest save kept, with %d question. The saves before it were not kept.', 'The oldest save kept, with %d questions. The saves before it were not kept.', $count, 'wpcredits-program-manager' ),
						$count
					);
				} else {
					$sentence = sprintf(
						/* translators: %d: how many questions the track started with. */
						_n( 'Created, with %d question.', 'Created, with %d questions.', $count, 'wpcredits-program-manager' ),
						$count
					);
				}

				echo '<p>' . esc_html( $sentence ) . '</p>';
			} elseif ( isset( $revision['diff'] ) && is_array( $revision['diff'] ) ) {
				if ( ! empty( $revision['diff']['same'] ) ) {
					echo '<p>' . esc_html__( 'Nothing in the definition changed.', 'wpcredits-program-manager' ) . '</p>';
				} else {
					self::render_diff( $revision['diff'] );
				}
			} else {
				echo '<p>' . esc_html__( 'This save kept no definition.', 'wpcredits-program-manager' ) . '</p>';
			}

			echo '</li>';
		}

		echo '</ol>';

		if ( $pruned ) {
			// Why the oldest entry is not the creation, said once under the list rather than on
			// every row: the cap is the site's, not the track's.
			$kept = sprintf(
				/* translators: %d: how many saves of a track this site keeps. */
				__( 'This site keeps the last %d saves of a track; earlier ones were discarded.', 'wpcredits-program-manager' ),
				(int) $cap
			);

			echo '<p>' . esc_html( $kept ) . '</p>';
		}

		if ( $more ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %d: how many saves are shown. */
					__( 'Older saves exist; the last %d are shown.', 'wpcredits-program-manager' ),
					count( $revisions )
				)
			) . '</p>';
		}
	}

	/**
	 * The publish log, newest first: every entry the store keeps, with who and when.
	 *
	 * @param array[] $log   The entries.
	 * @param array   $items Checklist item => its label, for the ticks.
	 */
	private static function render_log( array $log, array $items ) {
		echo '<h3>' . esc_html__( 'Publish log', 'wpcredits-program-manager' ) . '</h3>';

		if ( array() === $log ) {
			echo '<p>' . esc_html__( 'Nothing yet: the log starts when the track is first published.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<ul class="wpcpm-history__log">';

		foreach ( $log as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			echo '<li>' . esc_html(
				sprintf(
					/* translators: 1: what happened, 2: a date and time, then who did it. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					self::what( $entry, $items ),
					WPCPM_Track_Builder_Screen::when_and_who( isset( $entry['at'] ) ? (int) $entry['at'] : 0, isset( $entry['by'] ) ? (int) $entry['by'] : 0 )
				)
			) . '</li>';
		}

		echo '</ul>';
	}

	/**
	 * One diff, as lines: the track's own properties, then the columns added, removed and moved,
	 * then each question whose properties changed, named as the question screen names them.
	 *
	 * @param array $diff What `WPCPM_Track_Diff::between()` answered.
	 */
	private static function render_diff( array $diff ) {
		echo '<ul class="wpcpm-history__diff">';

		if ( ! empty( $diff['track'] ) ) {
			echo '<li>' . esc_html(
				sprintf(
					/* translators: %s: the track properties that changed, in the track form's words. */
					__( 'Track: %s', 'wpcredits-program-manager' ),
					implode( ', ', self::named( (array) $diff['track'], WPCPM_Track_Builder_Screen::track_labels() ) )
				)
			) . '</li>';
		}

		foreach ( array(
			'added'   => __( 'Added:', 'wpcredits-program-manager' ),
			'removed' => __( 'Removed:', 'wpcredits-program-manager' ),
			'moved'   => __( 'Moved:', 'wpcredits-program-manager' ),
		) as $part => $heading ) {
			if ( empty( $diff[ $part ] ) ) {
				continue;
			}

			echo '<li>' . esc_html( $heading ) . ' ';
			self::render_columns( (array) $diff[ $part ] );
			echo '</li>';
		}

		if ( ! empty( $diff['changed'] ) && is_array( $diff['changed'] ) ) {
			$labels = WPCPM_Track_Editor_Screen::property_labels();

			foreach ( $diff['changed'] as $column => $properties ) {
				echo '<li><code>' . esc_html( (string) $column ) . '</code>: ' . esc_html( implode( ', ', self::named( (array) $properties, $labels ) ) ) . '</li>';
			}
		}

		echo '</ul>';
	}

	/**
	 * Column names, each in its own code element.
	 *
	 * @param string[] $columns The columns.
	 */
	private static function render_columns( array $columns ) {
		$first = true;

		foreach ( $columns as $column ) {
			echo ( $first ? '' : ', ' ) . '<code>' . esc_html( (string) $column ) . '</code>';
			$first = false;
		}
	}

	/**
	 * Properties in the words a screen uses for them; one no screen names prints as itself.
	 *
	 * @param string[] $properties The properties.
	 * @param string[] $labels     Property => its label.
	 * @return string[]
	 */
	private static function named( array $properties, array $labels ) {
		$named = array();

		foreach ( $properties as $property ) {
			$property = (string) $property;
			$named[]  = isset( $labels[ $property ] ) ? (string) $labels[ $property ] : $property;
		}

		return $named;
	}

	/**
	 * What a log entry says happened.
	 *
	 * @param array $entry The entry: `did`, and `detail` when the store kept one.
	 * @param array $items Checklist item => its label.
	 * @return string
	 */
	private static function what( array $entry, array $items ) {
		$did   = isset( $entry['did'] ) ? (string) $entry['did'] : '';
		$words = array(
			'publish'           => __( 'Published', 'wpcredits-program-manager' ),
			'unpublish'         => __( 'Unpublished', 'wpcredits-program-manager' ),
			'switch_definition' => __( 'Switched to run from its definition', 'wpcredits-program-manager' ),
			'switch_builtin'    => __( 'Switched back to its hand-written form', 'wpcredits-program-manager' ),
		);

		if ( isset( $words[ $did ] ) ) {
			return $words[ $did ];
		}

		if ( 'columns' === $did ) {
			$columns = isset( $entry['detail']['columns'] ) ? array_map( 'strval', (array) $entry['detail']['columns'] ) : array();

			return sprintf(
				/* translators: %s: the columns created, comma-separated. */
				__( 'Created in Airtable: %s', 'wpcredits-program-manager' ),
				implode( ', ', $columns )
			);
		}

		foreach ( array(
			/* translators: %s: a checklist item. */
			'tick-'   => __( 'Ticked "%s"', 'wpcredits-program-manager' ),
			/* translators: %s: a checklist item. */
			'untick-' => __( 'Unticked "%s"', 'wpcredits-program-manager' ),
		) as $prefix => $pattern ) {
			if ( 0 === strpos( $did, $prefix ) ) {
				$item = substr( $did, strlen( $prefix ) );

				return sprintf( $pattern, isset( $items[ $item ] ) ? (string) $items[ $item ] : $item );
			}
		}

		return $did;
	}
}
