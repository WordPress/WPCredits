<?php
/**
 * Tools - the Track Builder screen's markup.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draws the Track Builder's list.
 *
 * Separated from the tool for the reason the Student Duplicate Finder's screen is: the tool
 * answers presses and the screen prints, so a change to the wording never touches a handler.
 * Everything printed here arrives ready in the row (`WPCPM_Track_Builder::rows()`), so this class
 * asks nothing of the store and nothing of Airtable.
 */
final class WPCPM_Track_Builder_Screen {

	/**
	 * The list of tracks.
	 *
	 * @param array $args `rows` from `WPCPM_Track_Builder::rows()`, the screen's `url` for the Edit
	 *                    links, and the `flash` the last press left.
	 */
	public static function render_list( array $args ) {
		$rows  = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();

		self::render_notice( $flash );

		// Above the table, and there with no tracks at all: New track is the way in that needs
		// nothing to copy (the design's section 5).
		if ( '' !== $url ) {
			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url( add_query_arg( 'wpcpm_new', 1, $url ) ),
				esc_html__( 'New track', 'wpcredits-program-manager' )
			);
		}

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No tracks yet. The four the program runs today appear here the first time this site loads after the update.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped wpcpm-tracks"><thead><tr>';

		foreach ( array(
			__( 'Track', 'wpcredits-program-manager' ),
			__( 'Status', 'wpcredits-program-manager' ),
			__( 'State', 'wpcredits-program-manager' ),
			__( 'Students', 'wpcredits-program-manager' ),
			__( 'Last published', 'wpcredits-program-manager' ),
			__( 'Actions', 'wpcredits-program-manager' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}

		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			self::render_row( $row, $url );
		}

		echo '</tbody></table>';
	}

	/**
	 * One track.
	 *
	 * @param array  $row One row from `WPCPM_Track_Builder::rows()`.
	 * @param string $url The screen's URL, for the Edit link.
	 */
	private static function render_row( array $row, $url ) {
		$skipped = isset( $row['skipped'] ) ? (array) $row['skipped'] : array();
		$classes = 'wpcpm-tracks__row' . ( empty( $skipped ) ? '' : ' wpcpm-tracks__row--skipped' ) . ( empty( $row['unlisted'] ) ? '' : ' wpcpm-tracks__row--unlisted' );

		printf( '<tr class="%s">', esc_attr( $classes ) );

		printf( '<td><strong>%s</strong>', esc_html( (string) $row['label'] ) );
		self::render_course( $row );
		echo '</td>';

		printf( '<td>%s<br /><code class="wpcpm-tracks__key">%s</code></td>', esc_html( (string) $row['status'] ), esc_html( (string) $row['key'] ) );

		printf( '<td>%s', esc_html( self::state_label( (string) $row['state'] ) ) );
		self::render_skipped( $skipped );
		self::render_unlisted( $row );
		echo '</td>';

		printf( '<td>%s</td>', esc_html( number_format_i18n( (int) $row['students'] ) ) );
		printf( '<td>%s</td>', esc_html( self::published_line( $row ) ) );

		echo '<td class="wpcpm-list__actions">';
		self::render_actions( $row, $url );
		echo '</td></tr>';
	}

	/**
	 * The buttons a row offers.
	 *
	 * @param array  $row One row.
	 * @param string $url The screen's URL.
	 */
	private static function render_actions( array $row, $url ) {
		// Four links into this screen, on every row: Preview draws any track's draft as its students
		// would see it; History is the published copy against the draft, every save against the one
		// before, and the publish log (decision 28).
		if ( '' !== $url ) {
			foreach ( array(
				'wpcpm_track'     => __( 'Edit', 'wpcredits-program-manager' ),
				'wpcpm_duplicate' => __( 'Duplicate', 'wpcredits-program-manager' ),
				'wpcpm_preview'   => __( 'Preview', 'wpcredits-program-manager' ),
				'wpcpm_history'   => __( 'History', 'wpcredits-program-manager' ),
			) as $arg => $words ) {
				printf(
					'<a href="%1$s">%2$s</a> ',
					esc_url( add_query_arg( $arg, (int) $row['id'], $url ) ),
					esc_html( $words )
				);
			}
		}

		// Publishing is a screen of its own: it has a preflight to read, a checklist to work
		// through and, when a column is missing, a list to take to Airtable. A trashed track is
		// not offered it, because the store refuses to publish out of the trash.
		if ( '' !== $url && 'trash' !== $row['state'] ) {
			printf(
				'<a href="%1$s">%2$s</a> ',
				esc_url( add_query_arg( 'wpcpm_publish', (int) $row['id'], $url ) ),
				esc_html( self::publish_link_label( $row ) )
			);
		}

		// Delete is offered where the store's `delete()` deletes: on a track not in publish status
		// that was never published (decision 25). Every other track is the record of what was
		// created in the base, one of the four original tracks among them (the design's decision
		// 38), and the store refuses it, so the button is not drawn where it could only fail. A
		// draft holding one of the four original tracks' statuses that was never published is
		// offered it like any draft, since the store deletes it: beside a published original it is
		// a stray that holds up that track's Save and Publish (TRACKS-1). `published` and `changed`
		// are the states of a track in publish status (`WPCPM_Track_Store::state()`), whatever its
		// log says.
		$published = in_array( (string) $row['state'], array( 'published', 'changed' ), true ) || ! empty( $row['ever_published'] );

		if ( ! $published ) {
			self::render_delete( (int) $row['id'], (string) $row['label'] );
		}
	}

	/**
	 * Publish on a draft, or Publishing once the track is live.
	 *
	 * @param array $row One row.
	 * @return string
	 */
	private static function publish_link_label( array $row ) {
		return 'draft' === (string) $row['state']
			? __( 'Publish', 'wpcredits-program-manager' )
			: __( 'Publishing', 'wpcredits-program-manager' );
	}

	/**
	 * Delete, behind a confirmation that names the track (decision 25).
	 *
	 * @param int    $track The track.
	 * @param string $label Its name.
	 */
	private static function render_delete( $track, $label ) {
		$confirm = sprintf(
			/* translators: %s: the track's name. */
			__( 'Delete %s? It was never published, so nothing in Airtable or on the live site refers to it. This cannot be undone.', 'wpcredits-program-manager' ),
			$label
		);

		printf(
			'<form method="post" action="%1$s" class="wpcpm-tracks__delete" onsubmit="return confirm(\'%2$s\');">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_js( $confirm )
		);
		wp_nonce_field( WPCPM_Track_Editor::ACTION_DELETE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Editor::ACTION_DELETE ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
		printf( '<button type="submit" class="button button-link-delete">%s</button>', esc_html__( 'Delete', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * The publish screen: what would happen, what a person has to do, and the button.
	 *
	 * @param array $args `track`, `label`, `state`, `reserved` (whether the track holds one of the
	 *                    four original tracks' statuses), `preflight`, `checklist`, `can_make`
	 *                    (whether a schema token is configured), the screen's `url` and the `flash`.
	 */
	public static function render_publish( array $args ) {
		$track     = isset( $args['track'] ) ? (int) $args['track'] : 0;
		$label     = isset( $args['label'] ) ? (string) $args['label'] : '';
		$state     = isset( $args['state'] ) ? (string) $args['state'] : '';
		$flight    = isset( $args['preflight'] ) && is_array( $args['preflight'] ) ? $args['preflight'] : array();
		$checklist = isset( $args['checklist'] ) && is_array( $args['checklist'] ) ? $args['checklist'] : array();
		$can_make  = ! empty( $args['can_make'] );
		$reserved  = ! empty( $args['reserved'] );
		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';

		self::render_notice( isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array() );

		/* translators: %s: the track's name. */
		echo '<h2>' . esc_html( sprintf( __( 'Publishing %s', 'wpcredits-program-manager' ), $label ) ) . '</h2>';

		if ( '' !== $url ) {
			printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to the track list', 'wpcredits-program-manager' ) );
		}

		self::render_findings( $flight );
		self::render_columns( $flight, $can_make );
		self::render_adds_status( $flight );
		self::render_checklist( $checklist, $track, isset( $flight['choices'] ) && is_array( $flight['choices'] ) ? $flight['choices'] : array() );
		self::render_publish_actions( $flight, $state, $track, $can_make, $reserved, $label );
	}

	/**
	 * The draft's form as a student sees it: empty answers, the controls enabled, nothing to press.
	 *
	 * Drawn through the report form's own renderer, inside the sheet that dresses it on the
	 * student's page, so the preview is faithful to the form's structure rather than to the theme's
	 * pixels (the design's decision 27). The wrapper carries `wpcpm-dashboard` because that is the
	 * element the plugin's stylesheet sets its tokens on; without it the form would draw untokened.
	 *
	 * @param array $args `track`, `label`, `fields`, `state` and `course`, as
	 *                    `WPCPM_Track_Builder::preview()` gives them, the screen's `url`, and the
	 *                    `flash` the last press left.
	 */
	public static function render_preview( array $args ) {
		$track  = isset( $args['track'] ) ? (int) $args['track'] : 0;
		$label  = isset( $args['label'] ) ? (string) $args['label'] : '';
		$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
		$state  = isset( $args['state'] ) ? (string) $args['state'] : '';
		$url    = isset( $args['url'] ) ? (string) $args['url'] : '';

		self::render_notice( isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array() );

		echo '<h2>';
		printf(
			/* translators: %s: the track's name. */
			esc_html__( 'Previewing %s', 'wpcredits-program-manager' ),
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

		echo '<p class="wpcpm-tracks__preview-note">';
		echo esc_html__( 'The form as a student sees it, with empty answers and no student\'s record. Nothing typed here is kept: there is no Save button and no form behind the controls.', 'wpcredits-program-manager' );
		echo ' ';
		echo esc_html( self::preview_line( $state ) );
		echo '</p>';

		if ( array() === $fields ) {
			echo '<p>' . esc_html__( 'This track has no questions yet, so there is nothing to draw.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		// The course decides where the hours box sits, as it does on the student's page (TRACKS-3).
		echo '<div class="wpcpm-dashboard wpcpm-tracks__preview">';
		WPCPM_Student_Report_Form::render_preview( $fields, isset( $args['course'] ) ? (string) $args['course'] : '' );
		echo '</div>';
	}

	/**
	 * What the preview is a preview of, against what students have now.
	 *
	 * @param string $state The track's state.
	 * @return string
	 */
	private static function preview_line( $state ) {
		switch ( $state ) {
			case 'published':
				return __( 'The published copy is the same as this draft, so this is the form students on the track have.', 'wpcredits-program-manager' );
			case 'changed':
				return __( 'Students on the track still have the published copy; this draft has changes they do not see yet.', 'wpcredits-program-manager' );
			case 'trash':
				return __( 'This track is in the trash.', 'wpcredits-program-manager' );
		}

		return __( 'Nothing reaches students until the track is published.', 'wpcredits-program-manager' );
	}

	/**
	 * What the preflight refused and what it only warned about.
	 *
	 * @param array $flight The preflight's answer.
	 * @return void
	 */
	private static function render_findings( array $flight ) {
		foreach ( array( 'refusals', 'warnings' ) as $kind ) {
			$findings = isset( $flight[ $kind ] ) ? (array) $flight[ $kind ] : array();

			if ( array() === $findings ) {
				continue;
			}

			$lede = 'refusals' === $kind
				? esc_html__( 'This track cannot be published yet:', 'wpcredits-program-manager' )
				: esc_html__( 'Worth knowing before you publish:', 'wpcredits-program-manager' );

			printf(
				'<div class="notice notice-%1$s inline"><p><strong>%2$s</strong></p><ul class="wpcpm-tracks__findings">',
				'refusals' === $kind ? 'error' : 'warning',
				$lede // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two escaped literals above.
			);

			foreach ( $findings as $finding ) {
				$column = isset( $finding['column'] ) ? (string) $finding['column'] : '';

				echo '<li>';

				if ( '' !== $column ) {
					printf( '<code>%s</code> ', esc_html( $column ) );
				}

				echo esc_html( isset( $finding['message'] ) ? (string) $finding['message'] : '' );
				echo '</li>';
			}

			echo '</ul></div>';
		}
	}

	/**
	 * The columns publishing would create, or the list to make by hand.
	 *
	 * Nothing at all when the preflight stopped before judging a column: no definition, a trashed
	 * track, a base it could not read or a Students Reports table setting the schema does not name.
	 * It then answers `definition` null and no columns, and "Every column this track writes to is
	 * already in the base" would be a verdict nobody reached, drawn under the refusal that says why
	 * (the final fix wave of the deep check of 1.109.1). Asked with `array_key_exists()`, since
	 * `isset()` cannot tell a null from a missing key.
	 *
	 * @param array $flight   The preflight's answer.
	 * @param bool  $can_make Whether a schema token is configured.
	 * @return void
	 */
	private static function render_columns( array $flight, $can_make ) {
		if ( array_key_exists( 'definition', $flight ) && null === $flight['definition'] ) {
			return;
		}

		$create = isset( $flight['columns']['create'] ) ? (array) $flight['columns']['create'] : array();
		$detail = isset( $flight['columns']['detail'] ) ? (array) $flight['columns']['detail'] : array();

		echo '<h3>' . esc_html__( 'Columns', 'wpcredits-program-manager' ) . '</h3>';

		if ( array() === $create ) {
			echo '<p>' . esc_html__( 'Every column this track writes to is already in the base.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<p>';

		if ( $can_make ) {
			esc_html_e( 'Publishing creates these columns on Students Reports, one at a time:', 'wpcredits-program-manager' );
		} else {
			esc_html_e( 'These columns are missing, and no schema token is configured, so somebody has to create them in Airtable first. Publish again once they are there.', 'wpcredits-program-manager' );
		}

		echo '</p><ul class="wpcpm-tracks__columns">';

		foreach ( $create as $column ) {
			self::render_column(
				(string) $column,
				isset( $detail[ $column ] ) && is_array( $detail[ $column ] ) ? $detail[ $column ] : array()
			);
		}

		echo '</ul>';

		$fields = isset( $flight['fields']['after'] ) ? (int) $flight['fields']['after'] : 0;

		if ( $fields > 0 ) {
			echo '<p class="wpcpm-tracks__count">';
			echo esc_html(
				sprintf(
					/* translators: %d: how many columns the table would hold afterward. */
					__( 'The table would hold %d columns afterward.', 'wpcredits-program-manager' ),
					$fields
				)
			);
			echo '</p>';
		}
	}

	/**
	 * That publishing adds this track's status to the program's settings.
	 *
	 * The preflight says so (decision 13, 7.2 step 2) and nothing showed it: a person publishing a
	 * track had no way to see, before pressing the button, that doing so changes Settings (final
	 * review, finding 5). It says so of every track, a published track's status being what its
	 * students carry; the line is drawn when the preflight says so.
	 *
	 * @param array $flight The preflight's answer.
	 * @return void
	 */
	private static function render_adds_status( array $flight ) {
		if ( empty( $flight['adds_status'] ) ) {
			return;
		}

		echo '<p class="wpcpm-tracks__count">' . esc_html__( 'Publishing adds this track\'s status to "Currently mentoring" in Settings.', 'wpcredits-program-manager' ) . '</p>';
	}

	/**
	 * One column of the by-hand list: its name, the type Airtable needs, and a select's choices.
	 *
	 * Design spec 7.2 asks for the name, the type and the options, because this list is what
	 * somebody takes to Airtable to create the column: a wrong guess at the type earns
	 * `wpcpm_track_column_conflict` the next time this track is published (Task 9 review, L8).
	 *
	 * @param string $column The column name.
	 * @param array  $field  What `WPCPM_Track_Columns::field()` answered for it: `type`, and for
	 *                       a select, `options.choices`.
	 * @return void
	 */
	private static function render_column( $column, array $field ) {
		$type    = isset( $field['type'] ) ? (string) $field['type'] : '';
		$choices = isset( $field['options']['choices'] ) && is_array( $field['options']['choices'] ) ? $field['options']['choices'] : array();

		echo '<li>';
		printf( '<code>%s</code>', esc_html( $column ) );

		if ( '' !== $type ) {
			printf( ' - %s', esc_html( $type ) );
		}

		if ( array() !== $choices ) {
			$names = array();

			foreach ( $choices as $choice ) {
				$names[] = isset( $choice['name'] ) ? (string) $choice['name'] : '';
			}

			echo ' (';
			printf(
				/* translators: %s: a comma-separated list of the choices a select column needs. */
				esc_html__( 'choices: %s', 'wpcredits-program-manager' ),
				esc_html( implode( ', ', $names ) )
			);
			echo ')';
		}

		echo '</li>';
	}

	/**
	 * The three things the site cannot do, each with its tick.
	 *
	 * @param array $checklist What `WPCPM_Track_Publish::checklist()` answered.
	 * @param int   $track     The track.
	 * @param array $choices   The preflight's `choices` (`reports` and `students`, each `ok`,
	 *                         `near` or `missing`), so item 3 shows which table has the choice
	 *                         already and which does not (final review, finding 4).
	 * @return void
	 */
	private static function render_checklist( array $checklist, $track, array $choices = array() ) {
		if ( array() === $checklist ) {
			return;
		}

		echo '<h3>' . esc_html__( 'What the site cannot do', 'wpcredits-program-manager' ) . '</h3>';
		echo '<p>' . esc_html__( 'The site cannot see an Airtable automation either way, so none of these stops a track being published. Each shows here as not done until somebody ticks it.', 'wpcredits-program-manager' ) . '</p>';
		echo '<ul class="wpcpm-tracks__checklist">';

		foreach ( $checklist as $item => $entry ) {
			printf( '<li class="wpcpm-tracks__item%s">', empty( $entry['ticked'] ) ? '' : ' wpcpm-tracks__item--done' );
			printf( '<strong>%s</strong>', esc_html( (string) $entry['label'] ) );
			printf( '<span class="wpcpm-tracks__detail">%s</span>', esc_html( (string) $entry['detail'] ) );

			if ( 'choices' === $item ) {
				self::render_choice_states( $choices );
			}

			if ( ! empty( $entry['ticked'] ) ) {
				// A tick in nobody's name is the site's own: it ticks the four original tracks' items
				// when it publishes them itself, and says so as `when_and_who()` says it of a save.
				$by = (int) $entry['by'];

				if ( $by <= 0 ) {
					$name = __( 'the site itself', 'wpcredits-program-manager' );
				} else {
					$who  = get_userdata( $by );
					$name = $who ? $who->display_name : __( 'somebody', 'wpcredits-program-manager' );
				}

				echo '<span class="wpcpm-tracks__ticked">';
				printf(
					/* translators: 1: who ticked the item, a person's name or "the site itself", 2: a date. */
					esc_html__( 'Ticked by %1$s on %2$s.', 'wpcredits-program-manager' ),
					esc_html( $name ),
					esc_html( wp_date( 'j F Y', (int) $entry['at'] ) )
				);
				echo '</span>';
			}

			self::render_tick(
				empty( $entry['ticked'] ) ? WPCPM_Track_Builder::ACTION_TICK : WPCPM_Track_Builder::ACTION_UNTICK,
				$track,
				(string) $item,
				empty( $entry['ticked'] ) ? __( 'I have done this', 'wpcredits-program-manager' ) : __( 'Undo', 'wpcredits-program-manager' )
			);

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * The Status choice's state on each table, so checklist item 3 is not the only place a person
	 * can see it: the preflight works this out for both tables (spec 7.1) and only turned a `near`
	 * state into a warning above, leaving `ok` and `missing` unshown (final review, finding 4).
	 *
	 * @param array $choices `reports` and `students`, each `ok`, `near` or `missing`.
	 * @return void
	 */
	private static function render_choice_states( array $choices ) {
		$tables = array(
			'reports'  => __( 'Students Reports', 'wpcredits-program-manager' ),
			'students' => __( 'Students', 'wpcredits-program-manager' ),
		);

		$lines = array();

		foreach ( $tables as $where => $label ) {
			$lines[] = self::choice_line( $label, isset( $choices[ $where ] ) ? (string) $choices[ $where ] : 'missing' );
		}

		printf( '<span class="wpcpm-tracks__detail">%s</span>', esc_html( implode( ' ', $lines ) ) );
	}

	/**
	 * One table's line for `render_choice_states()`.
	 *
	 * @param string $table_label The table's name, already translated.
	 * @param string $state       `ok`, `near` or `missing`.
	 * @return string
	 */
	private static function choice_line( $table_label, $state ) {
		if ( 'ok' === $state ) {
			/* translators: %s: the table's name. */
			return sprintf( __( '%s already has this choice.', 'wpcredits-program-manager' ), $table_label );
		}

		if ( 'near' === $state ) {
			/* translators: %s: the table's name. */
			return sprintf( __( '%s has a choice close to this one, but not an exact match.', 'wpcredits-program-manager' ), $table_label );
		}

		/* translators: %s: the table's name. */
		return sprintf( __( '%s does not have this choice yet.', 'wpcredits-program-manager' ), $table_label );
	}

	/**
	 * Publish, unpublish and verify, as the track's state allows; one of the four original tracks
	 * is not offered the unpublish, and is told why (the design's decision 38).
	 *
	 * Publish is drawn only when it could actually succeed. With columns pending and no schema
	 * token, `WPCPM_Track_Publish::run()` can only refuse with `wpcpm_track_columns_by_hand` -
	 * design spec 7.2 waits for the preflight to find the columns instead - so that combination
	 * withholds the button rather than handing over one that can only fail (Task 9 review, M1).
	 *
	 * A publish that creates columns is drawn with a box for the track's name, which the handler
	 * compares before anything is created (PUBLISH-LEARN-3).
	 *
	 * @param array  $flight   The preflight's answer.
	 * @param string $state    The track's state.
	 * @param int    $track    The track.
	 * @param bool   $can_make Whether a schema token is configured.
	 * @param bool   $reserved Whether the track holds one of the four original tracks' statuses.
	 * @param string $label    The track's name, which a publish that creates columns asks for.
	 * @return void
	 */
	private static function render_publish_actions( array $flight, $state, $track, $can_make, $reserved = false, $label = '' ) {
		echo '<p class="wpcpm-list__actions">';

		$pending     = isset( $flight['columns']['create'] ) ? (array) $flight['columns']['create'] : array();
		$can_publish = ! empty( $flight['ready'] ) && ( array() === $pending || $can_make );

		if ( $can_publish && in_array( $state, array( 'draft', 'changed' ), true ) ) {
			$button = 'changed' === $state
				? __( 'Publish the changes', 'wpcredits-program-manager' )
				: __( 'Publish this track', 'wpcredits-program-manager' );

			if ( array() === $pending ) {
				self::render_button( WPCPM_Track_Builder::ACTION_PUBLISH, $track, $button );
			} else {
				self::render_publish_confirm( $track, $label, $pending, $button );
			}
		}

		$live = in_array( $state, array( 'published', 'changed' ), true );

		if ( $live ) {
			self::render_button( WPCPM_Track_Builder::ACTION_VERIFY, $track, __( 'Check it against Airtable', 'wpcredits-program-manager' ) );
		}

		// Not on one of the four original tracks, which the store never takes off (the design's
		// decision 38): the 150-hour track's form is the one every student on no track reads
		// (decision 34), and the other three are the program's base statuses. The button is not
		// drawn where it could only be refused, and the line under it says why.
		if ( $live && ! $reserved ) {
			self::render_button( WPCPM_Track_Builder::ACTION_UNPUBLISH, $track, __( 'Take it off the live site', 'wpcredits-program-manager' ) );
		}

		echo '</p>';

		if ( $live && $reserved ) {
			echo '<p class="wpcpm-tracks__count">' . esc_html__( 'The program\'s original tracks always run: edit the track and publish the change instead.', 'wpcredits-program-manager' ) . '</p>';
		}
	}

	/**
	 * Publish behind the track's name, typed: publishing creates columns in Airtable that the site
	 * can never remove, so one press of a link must not make them (the design's section 6; the
	 * product owner, 23 September 2026; PUBLISH-LEARN-3). `handle_publish()` compares the name, and
	 * the columns listed here, which the form carries one hidden field each, with what the
	 * preflight would create at the press (the fix round of PUBLISH-LEARN-3).
	 *
	 * @param int      $track   The track.
	 * @param string   $label   Its name, which is what has to be typed.
	 * @param string[] $pending The columns publishing creates, as the screen lists them.
	 * @param string   $button  What the button says.
	 * @return void
	 */
	private static function render_publish_confirm( $track, $label, array $pending, $button ) {
		$field   = 'wpcpm-confirm-' . (int) $track;
		$columns = count( $pending );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpcpm-tracks__confirm">';
		wp_nonce_field( WPCPM_Track_Builder::ACTION_PUBLISH );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_PUBLISH ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );

		foreach ( $pending as $column ) {
			printf(
				'<input type="hidden" name="%1$s[]" value="%2$s" />',
				esc_attr( WPCPM_Track_Builder::FIELD_CONFIRM_COLUMNS ),
				esc_attr( (string) $column )
			);
		}

		printf(
			'<label for="%1$s">%2$s</label> ',
			esc_attr( $field ),
			esc_html(
				sprintf(
					/* translators: 1: how many columns publishing creates, 2: the track's name. */
					_n(
						'Publishing creates %1$d column in Airtable, and the site can never remove it. To go ahead, type the name of the track, %2$s:',
						'Publishing creates %1$d columns in Airtable, and the site can never remove them. To go ahead, type the name of the track, %2$s:',
						(int) $columns,
						'wpcredits-program-manager'
					),
					(int) $columns,
					(string) $label
				)
			)
		);
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" required /> ',
			esc_attr( $field ),
			esc_attr( WPCPM_Track_Builder::FIELD_CONFIRM )
		);
		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html( $button ) );
		echo '</form>';
	}

	/**
	 * One checklist button, which carries the item as well as the track.
	 *
	 * @param string $action The action.
	 * @param int    $track  The track.
	 * @param string $item   The checklist item.
	 * @param string $label  What the button reads.
	 * @return void
	 */
	private static function render_tick( $action, $track, $item, $label ) {
		printf( '<form method="post" action="%s" class="wpcpm-list__form">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( $action );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
		printf( '<input type="hidden" name="item" value="%s" />', esc_attr( $item ) );
		printf( '<button type="submit" class="button">%s</button>', esc_html( $label ) );
		echo '</form>';
	}

	/**
	 * One track's properties, to read or to edit.
	 *
	 * @param array $args `form` from `WPCPM_Track_Builder::form()`, the screen's `url`, and the
	 *                    `flash` the last press left, whose `values` win over the stored ones so a
	 *                    refusal never makes somebody type their change again. A refused Add or
	 *                    Save of a question flashes `question_values` instead, which belong to the
	 *                    add form below and never to the track's own properties (the whole-branch
	 *                    review).
	 */
	public static function render_form( array $args ) {
		$form            = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
		$url             = isset( $args['url'] ) ? (string) $args['url'] : '';
		$flash           = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
		$typed           = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();
		$question_values = isset( $flash['question_values'] ) && is_array( $flash['question_values'] ) ? $flash['question_values'] : array();
		$lesson          = isset( $args['lesson'] ) ? (int) $args['lesson'] : 0;

		self::render_notice( $flash );

		printf(
			'<p><a href="%1$s">%2$s</a> <a href="%3$s">%4$s</a> <a href="%5$s">%6$s</a></p>',
			esc_url( $url ),
			esc_html__( 'Back to every track', 'wpcredits-program-manager' ),
			esc_url( add_query_arg( 'wpcpm_preview', isset( $form['id'] ) ? (int) $form['id'] : 0, $url ) ),
			esc_html__( 'Preview', 'wpcredits-program-manager' ),
			esc_url( add_query_arg( 'wpcpm_history', isset( $form['id'] ) ? (int) $form['id'] : 0, $url ) ),
			esc_html__( 'History', 'wpcredits-program-manager' )
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( WPCPM_Track_Builder::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_SAVE ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $form['id'] );

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( self::track_labels() as $field => $heading ) {
			// The course's ID is not typed since T3c: it is what the link resolves to, shown on the
			// row after the link (decision 31).
			if ( 'learn_course_id' === $field ) {
				continue;
			}

			$value = array_key_exists( $field, $typed ) ? $typed[ $field ] : ( isset( $form[ $field ] ) ? $form[ $field ] : '' );

			printf(
				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
				esc_attr( $field ),
				esc_html( $heading ),
				esc_attr( (string) $value )
			);

			if ( 'course_url' === $field ) {
				self::render_course_row( $form );
			}
		}

		echo '</tbody></table>';

		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Save the track', 'wpcredits-program-manager' ) );
		echo '</form>';

		self::render_course_press( $form );
		self::render_questions( $form, $url, $question_values, $lesson );
	}

	/**
	 * The course the link resolves to, or why it did not, on the row after the link (decision 31).
	 *
	 * @param array $form The track as `WPCPM_Track_Builder::form()` gives it.
	 */
	private static function render_course_row( array $form ) {
		$course = isset( $form['course'] ) && is_array( $form['course'] ) ? $form['course'] : array();
		$id     = isset( $course['id'] ) ? (int) $course['id'] : 0;
		$title  = isset( $course['title'] ) ? (string) $course['title'] : '';
		$error  = isset( $course['error'] ) ? (string) $course['error'] : '';

		if ( '' === ( isset( $form['course_url'] ) ? (string) $form['course_url'] : '' ) ) {
			return;
		}

		if ( '' === $error ) {
			$text = sprintf(
				/* translators: 1: the course's title, 2: its post ID on Learn. */
				__( '%1$s (course %2$d)', 'wpcredits-program-manager' ),
				$title,
				$id
			);
		} elseif ( $id > 0 ) {
			$text = sprintf(
				/* translators: 1: why the link did not resolve, 2: the course's post ID on Learn. */
				__( '%1$s The link last resolved to course %2$d, which the track keeps.', 'wpcredits-program-manager' ),
				$error,
				$id
			);
		} else {
			$text = $error;
		}

		printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html__( 'Learn course', 'wpcredits-program-manager' ), esc_html( $text ) );
	}

	/**
	 * "Read the course again", for the day a lesson is added on Learn (decision 31). Its own form,
	 * outside the properties form, since a form cannot sit inside another.
	 *
	 * @param array $form The track as `WPCPM_Track_Builder::form()` gives it.
	 */
	private static function render_course_press( array $form ) {
		if ( '' === ( isset( $form['course_url'] ) ? (string) $form['course_url'] : '' ) ) {
			return;
		}

		// A <div>, not a <p>: `render_button()` draws a <form>, which is flow content and would
		// close a paragraph early (the final review of T3c).
		echo '<div class="wpcpm-tracks__course-press">';
		self::render_button( WPCPM_Track_Builder::ACTION_COURSE, isset( $form['id'] ) ? (int) $form['id'] : 0, __( 'Read the course again', 'wpcredits-program-manager' ) );
		echo '</div>';
	}

	/**
	 * The question list under the properties, drawn by the editor's own screen class.
	 *
	 * @param array  $form   The track as `WPCPM_Track_Builder::form()` gives it.
	 * @param string $url    The screen's URL.
	 * @param array  $typed  What a refused Add carried, for the add form to draw again.
	 * @param int    $lesson The lesson "Add a question under this lesson" named, for its group's add form.
	 */
	private static function render_questions( array $form, $url, array $typed = array(), $lesson = 0 ) {
		WPCPM_Track_Editor_Screen::render_questions(
			array(
				'track'     => isset( $form['id'] ) ? (int) $form['id'] : 0,
				'key'       => isset( $form['key'] ) ? (string) $form['key'] : '',
				'questions' => isset( $form['questions'] ) && is_array( $form['questions'] ) ? $form['questions'] : array(),
				'others'    => isset( $form['others'] ) && is_array( $form['others'] ) ? $form['others'] : array(),
				'schema'    => isset( $form['schema'] ) && is_array( $form['schema'] ) ? $form['schema'] : array(),
				'locked'    => isset( $form['locked'] ) && is_array( $form['locked'] ) ? $form['locked'] : array(),
				'lessons'   => isset( $form['lessons'] ) && is_array( $form['lessons'] ) ? $form['lessons'] : array(),
				'learn'     => isset( $form['learn'] ) ? (string) $form['learn'] : '',
				'lesson'    => (int) $lesson,
				'typed'     => $typed,
				'url'       => $url,
			)
		);
	}

	/**
	 * Every track property the form edits, in the words it uses for them.
	 *
	 * One map, read by the track form, by New track for its first four, and by History for a
	 * diff line about the track itself (the design's decision 28).
	 *
	 * @return string[] Property => what its row is called.
	 */
	public static function track_labels() {
		return array(
			'label'           => __( 'Name', 'wpcredits-program-manager' ),
			'status'          => __( 'Airtable status', 'wpcredits-program-manager' ),
			'key'             => __( 'Key', 'wpcredits-program-manager' ),
			'course_url'      => __( 'Learn course', 'wpcredits-program-manager' ),
			'learn_course_id' => __( 'Learn course ID', 'wpcredits-program-manager' ),
			'hours_target'    => __( 'Hours target', 'wpcredits-program-manager' ),
			'hue'             => __( 'Key chip color', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * A track being started from nothing: its name, its Airtable status and its key (the design's 5).
	 *
	 * @param array $args The screen's `url`, and the `flash` the last press left.
	 */
	public static function render_new( array $args ) {
		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
		$typed = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();

		self::render_notice( $flash );

		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );

		echo '<p>' . esc_html__( 'A track from nothing: its name, its Airtable status and its key, and the link of the Learn course it follows, when it follows one. With a link, the course\'s lessons are listed beside the questions, and the name is taken from the course when it is left empty. Everything else about the track, and every question, is edited on the page that opens next.', 'wpcredits-program-manager' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( WPCPM_Track_Builder::ACTION_NEW );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_NEW ) . '" />';

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( array_intersect_key( self::track_labels(), array_flip( array( 'label', 'status', 'key', 'course_url' ) ) ) as $field => $heading ) {
			printf(
				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
				esc_attr( $field ),
				esc_html( $heading ),
				esc_attr( array_key_exists( $field, $typed ) ? (string) $typed[ $field ] : '' )
			);
		}

		echo '</tbody></table>';

		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Create the track', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * A copy being started: the three things it cannot share with the track it comes from.
	 *
	 * @param array $args `form` from `WPCPM_Track_Builder::duplicate_form()`, the screen's `url`,
	 *                    and the `flash` the last press left.
	 */
	public static function render_duplicate( array $args ) {
		$form  = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
		$typed = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();

		self::render_notice( $flash );

		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: the name of the track being copied. */
					__( 'Copying %s. Its questions come with the copy. A name, a status and a key of its own are asked for below; the copy starts with no Learn course and no hours target, which are set on its page.', 'wpcredits-program-manager' ),
					(string) $form['from']
				)
			)
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( WPCPM_Track_Builder::ACTION_DUPLICATE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_DUPLICATE ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $form['id'] );

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( array(
			'label'  => __( 'Name', 'wpcredits-program-manager' ),
			'status' => __( 'Airtable status', 'wpcredits-program-manager' ),
			'key'    => __( 'Key', 'wpcredits-program-manager' ),
		) as $field => $heading ) {
			$value = array_key_exists( $field, $typed ) ? $typed[ $field ] : ( isset( $form[ $field ] ) ? $form[ $field ] : '' );

			printf(
				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
				esc_attr( $field ),
				esc_html( $heading ),
				esc_attr( (string) $value )
			);
		}

		echo '</tbody></table>';

		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Make the copy', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * One button that posts to `admin-post.php`.
	 *
	 * @param string $action The admin-post action.
	 * @param int    $track  The track it acts on.
	 * @param string $label  What the button says.
	 */
	private static function render_button( $action, $track, $label ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
		printf( '<button type="submit" class="button-link">%s</button>', esc_html( $label ) );
		echo '</form>';
	}

	/**
	 * The track's Learn course, when it has one.
	 *
	 * @param array $row One row.
	 */
	private static function render_course( array $row ) {
		$course = isset( $row['course'] ) ? (string) $row['course'] : '';

		if ( '' === $course ) {
			return;
		}

		echo '<br /><a class="wpcpm-tracks__course" href="' . esc_url( $course ) . '">' . esc_html__( 'Learn course', 'wpcredits-program-manager' ) . '</a>';
	}

	/**
	 * What the last compile left out, and why.
	 *
	 * @param array $skipped The rules it failed.
	 */
	private static function render_skipped( array $skipped ) {
		if ( empty( $skipped ) ) {
			return;
		}

		$sentence = sprintf(
			/* translators: %s: the rules a track failed, separated by commas. */
			__( 'Left out of the live site by the last compile: %s. Students on it see the 150-hour form until it passes.', 'wpcredits-program-manager' ),
			implode( ', ', array_map( 'strval', $skipped ) )
		);

		echo '<br /><span class="wpcpm-tracks__skipped">' . esc_html( $sentence ) . '</span>';
	}

	/**
	 * A track the site runs from its definition whose status is missing from "Currently
	 * mentoring": the students sync reads only the statuses listed there, and treats every student
	 * it did not read as gone (the deep check of 1.109.1, BUILDER-3).
	 *
	 * @param array $row One row; its `unlisted` is the status the list lacks, or ''.
	 */
	private static function render_unlisted( array $row ) {
		if ( empty( $row['unlisted'] ) ) {
			return;
		}

		$sentence = sprintf(
			/* translators: %s: the Airtable status the track runs under. */
			__( 'Its status, "%s", is not in "Currently mentoring" in Settings, so the next students sync treats everybody on this track as having left the program. Add it back there.', 'wpcredits-program-manager' ),
			(string) $row['unlisted']
		);

		echo '<br /><span class="wpcpm-tracks__unlisted">' . esc_html( $sentence ) . '</span>';
	}

	/**
	 * Who published the track last, and when.
	 *
	 * @param array $row One row.
	 * @return string
	 */
	private static function published_line( array $row ) {
		$at = isset( $row['published_at'] ) ? (int) $row['published_at'] : 0;

		if ( ! $at ) {
			return __( 'Never', 'wpcredits-program-manager' );
		}

		return self::when_and_who( $at, isset( $row['published_by'] ) ? (int) $row['published_by'] : 0 );
	}

	/**
	 * A date and time, and who: the words the list, History's saves and the publish log share
	 * (the T3b final review, which found the two screens each keeping a copy).
	 *
	 * A save with nobody signed in, which WP-CLI and the seeding make, is the site's own; an
	 * account since deleted is said to be gone.
	 *
	 * @param int $at A Unix timestamp.
	 * @param int $by A user ID, or 0 for nobody.
	 * @return string
	 */
	public static function when_and_who( $at, $by ) {
		$by = (int) $by;

		if ( $by <= 0 ) {
			$who = __( 'the site itself', 'wpcredits-program-manager' );
		} else {
			$user = get_userdata( $by );
			$who  = $user ? $user->display_name : __( 'somebody since removed', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: 1: a date and time, 2: who saved or published the track. */
			__( '%1$s by %2$s', 'wpcredits-program-manager' ),
			wp_date( 'Y-m-d H:i', (int) $at ),
			$who
		);
	}

	/**
	 * A state in the words the list uses.
	 *
	 * @param string $state What `WPCPM_Track_Store::state()` answered.
	 * @return string
	 */
	private static function state_label( $state ) {
		$states = array(
			'draft'     => __( 'Draft', 'wpcredits-program-manager' ),
			'published' => __( 'Published', 'wpcredits-program-manager' ),
			'changed'   => __( 'Unpublished changes', 'wpcredits-program-manager' ),
			'trash'     => __( 'In the trash', 'wpcredits-program-manager' ),
		);

		return isset( $states[ $state ] ) ? $states[ $state ] : $state;
	}

	/**
	 * The notice the last press left.
	 *
	 * @param array $flash `status` and `message`.
	 */
	private static function render_notice( array $flash ) {
		if ( empty( $flash['message'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( isset( $flash['status'] ) && in_array( $flash['status'], array( 'error', 'warning' ), true ) ? $flash['status'] : 'success' ),
			esc_html( (string) $flash['message'] )
		);
	}
}
