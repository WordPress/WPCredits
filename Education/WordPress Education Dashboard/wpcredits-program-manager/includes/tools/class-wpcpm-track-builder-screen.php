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

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No tracks yet. The four the program runs today appear here the first time this site loads after the update.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped wpcpm-tracks"><thead><tr>';

		foreach ( array(
			__( 'Track', 'wpcredits-program-manager' ),
			__( 'Status', 'wpcredits-program-manager' ),
			__( 'Runs from', 'wpcredits-program-manager' ),
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
		$builtin = isset( $row['source'] ) && 'builtin' === $row['source'];
		$classes = 'wpcpm-tracks__row' . ( empty( $skipped ) ? '' : ' wpcpm-tracks__row--skipped' );

		printf( '<tr class="%s">', esc_attr( $classes ) );

		printf( '<td><strong>%s</strong>', esc_html( (string) $row['label'] ) );
		self::render_course( $row );
		echo '</td>';

		printf( '<td>%s<br /><code class="wpcpm-tracks__key">%s</code></td>', esc_html( (string) $row['status'] ), esc_html( (string) $row['key'] ) );

		echo '<td>';

		if ( $builtin ) {
			echo '<span class="wpcpm-tracks__readonly">' . esc_html__( 'Its hand-written form, so it cannot be edited here', 'wpcredits-program-manager' ) . '</span>';
		} else {
			echo esc_html__( 'Its definition', 'wpcredits-program-manager' );
		}

		echo '</td>';

		printf( '<td>%s', esc_html( self::state_label( (string) $row['state'] ) ) );
		self::render_skipped( $skipped );
		self::render_equivalence( $row );
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
		if ( '' !== $url ) {
			printf(
				'<a href="%1$s">%2$s</a> ',
				esc_url( add_query_arg( 'wpcpm_track', (int) $row['id'], $url ) ),
				esc_html__( 'Edit', 'wpcredits-program-manager' )
			);
		}

		if ( '' !== $url ) {
			printf(
				'<a href="%1$s">%2$s</a> ',
				esc_url( add_query_arg( 'wpcpm_duplicate', (int) $row['id'], $url ) ),
				esc_html__( 'Duplicate', 'wpcredits-program-manager' )
			);
		}

		// Publishing is a screen of its own: it has a preflight to read, a checklist to work
		// through and, when a column is missing, a list to take to Airtable. A trashed track is
		// not offered it, because the store refuses to publish out of the trash.
		if ( '' !== $url && 'trash' !== $row['state'] ) {
			printf(
				'<a href="%1$s">%2$s</a> ',
				esc_url( add_query_arg( 'wpcpm_publish', (int) $row['id'], $url ) ),
				'draft' === $row['state']
					? esc_html__( 'Publish', 'wpcredits-program-manager' )
					: esc_html__( 'Publishing', 'wpcredits-program-manager' )
			);
		}

		if ( ! empty( $row['stale'] ) ) {
			self::render_button( WPCPM_Track_Builder::ACTION_REFRESH, (int) $row['id'], __( 'Refresh from the plugin', 'wpcredits-program-manager' ) );
		}

		if ( 'builtin' === $row['source'] && empty( $row['equivalence'] ) ) {
			self::render_button( WPCPM_Track_Builder::ACTION_SWITCH_DEFINITION, (int) $row['id'], __( 'Run from its definition', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( $row['switched'] ) ) {
			self::render_button( WPCPM_Track_Builder::ACTION_SWITCH_BUILTIN, (int) $row['id'], __( 'Run from its hand-written form', 'wpcredits-program-manager' ) );
		}
	}

	/**
	 * The publish screen: what would happen, what a person has to do, and the button.
	 *
	 * @param array $args `track`, `label`, `state`, `preflight`, `checklist`, `can_make` (whether
	 *                    a schema token is configured), the screen's `url` and the `flash`.
	 */
	public static function render_publish( array $args ) {
		$track     = isset( $args['track'] ) ? (int) $args['track'] : 0;
		$label     = isset( $args['label'] ) ? (string) $args['label'] : '';
		$state     = isset( $args['state'] ) ? (string) $args['state'] : '';
		$flight    = isset( $args['preflight'] ) && is_array( $args['preflight'] ) ? $args['preflight'] : array();
		$checklist = isset( $args['checklist'] ) && is_array( $args['checklist'] ) ? $args['checklist'] : array();
		$can_make  = ! empty( $args['can_make'] );
		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';

		self::render_notice( isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array() );

		echo '<h2>';
		printf(
			/* translators: %s: the track's name. */
			esc_html__( 'Publishing %s', 'wpcredits-program-manager' ),
			esc_html( $label )
		);
		echo '</h2>';

		if ( '' !== $url ) {
			printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to the track list', 'wpcredits-program-manager' ) );
		}

		self::render_findings( $flight );
		self::render_columns( $flight, $can_make );
		self::render_adds_status( $flight );
		self::render_checklist( $checklist, $track, isset( $flight['choices'] ) && is_array( $flight['choices'] ) ? $flight['choices'] : array() );
		self::render_publish_actions( $flight, $state, $track, $can_make );
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
	 * @param array $flight   The preflight's answer.
	 * @param bool  $can_make Whether a schema token is configured.
	 * @return void
	 */
	private static function render_columns( array $flight, $can_make ) {
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
	 * Whether publishing would add this track's status to the program's settings.
	 *
	 * The preflight works this out (decision 13, 7.2 step 2) and nothing showed it: a person
	 * publishing a track of their own had no way to see, before pressing the button, that doing
	 * so changes Settings (final review, finding 5).
	 *
	 * @param array $flight The preflight's answer.
	 * @return void
	 */
	private static function render_adds_status( array $flight ) {
		echo '<p class="wpcpm-tracks__count">';

		echo esc_html(
			empty( $flight['adds_status'] )
				? __( 'This track runs from its hand-written form, so publishing it does not add anything to "Currently mentoring" in Settings.', 'wpcredits-program-manager' )
				: __( 'Publishing adds this track\'s status to "Currently mentoring" in Settings.', 'wpcredits-program-manager' )
		);

		echo '</p>';
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
		echo '<p>' . esc_html__( 'The site cannot see an Airtable automation either way, so none of these stops a track being published. The track list counts them until they are ticked.', 'wpcredits-program-manager' ) . '</p>';
		echo '<ul class="wpcpm-tracks__checklist">';

		foreach ( $checklist as $item => $entry ) {
			printf( '<li class="wpcpm-tracks__item%s">', empty( $entry['ticked'] ) ? '' : ' wpcpm-tracks__item--done' );
			printf( '<strong>%s</strong>', esc_html( (string) $entry['label'] ) );
			printf( '<span class="wpcpm-tracks__detail">%s</span>', esc_html( (string) $entry['detail'] ) );

			if ( 'choices' === $item ) {
				self::render_choice_states( $choices );
			}

			if ( ! empty( $entry['ticked'] ) ) {
				$who = get_userdata( (int) $entry['by'] );

				echo '<span class="wpcpm-tracks__ticked">';
				printf(
					/* translators: 1: a person's name, 2: a date. */
					esc_html__( 'Ticked by %1$s on %2$s.', 'wpcredits-program-manager' ),
					esc_html( $who ? $who->display_name : __( 'somebody', 'wpcredits-program-manager' ) ),
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
	 * Publish, unpublish and verify, as the track's state allows.
	 *
	 * Publish is drawn only when it could actually succeed. With columns pending and no schema
	 * token, `WPCPM_Track_Publish::run()` can only refuse with `wpcpm_track_columns_by_hand` -
	 * design spec 7.2 waits for the preflight to find the columns instead - so that combination
	 * withholds the button rather than handing over one that can only fail (Task 9 review, M1).
	 *
	 * @param array  $flight   The preflight's answer.
	 * @param string $state    The track's state.
	 * @param int    $track    The track.
	 * @param bool   $can_make Whether a schema token is configured.
	 * @return void
	 */
	private static function render_publish_actions( array $flight, $state, $track, $can_make ) {
		echo '<p class="wpcpm-list__actions">';

		$pending     = isset( $flight['columns']['create'] ) ? (array) $flight['columns']['create'] : array();
		$can_publish = ! empty( $flight['ready'] ) && ( array() === $pending || $can_make );

		if ( $can_publish && in_array( $state, array( 'draft', 'changed' ), true ) ) {
			self::render_button(
				WPCPM_Track_Builder::ACTION_PUBLISH,
				$track,
				'changed' === $state
					? __( 'Publish the changes', 'wpcredits-program-manager' )
					: __( 'Publish this track', 'wpcredits-program-manager' )
			);
		}

		if ( in_array( $state, array( 'published', 'changed' ), true ) ) {
			self::render_button( WPCPM_Track_Builder::ACTION_VERIFY, $track, __( 'Check it against Airtable', 'wpcredits-program-manager' ) );
			self::render_button( WPCPM_Track_Builder::ACTION_UNPUBLISH, $track, __( 'Take it off the live site', 'wpcredits-program-manager' ) );
		}

		echo '</p>';
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
	 *                    refusal never makes somebody type their change again.
	 */
	public static function render_form( array $args ) {
		$form  = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
		$typed = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();

		self::render_notice( $flash );

		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );

		if ( ! empty( $form['read_only'] ) ) {
			echo '<p class="wpcpm-tracks__readonly">' . esc_html__( 'This track runs from its hand-written form, so it cannot be edited here. Duplicate it to start a track of your own, or switch it to its definition first.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( WPCPM_Track_Builder::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_SAVE ) . '" />';
		printf( '<input type="hidden" name="track" value="%d" />', (int) $form['id'] );

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( array(
			'label'           => __( 'Name', 'wpcredits-program-manager' ),
			'status'          => __( 'Airtable status', 'wpcredits-program-manager' ),
			'key'             => __( 'Key', 'wpcredits-program-manager' ),
			'course_url'      => __( 'Learn course', 'wpcredits-program-manager' ),
			'learn_course_id' => __( 'Learn course ID', 'wpcredits-program-manager' ),
			'hours_target'    => __( 'Hours target', 'wpcredits-program-manager' ),
			'hue'             => __( 'Key chip color', 'wpcredits-program-manager' ),
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

		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Save the track', 'wpcredits-program-manager' ) );
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
					__( 'Copying %s. Its questions come with the copy; a name, a status and a key of its own do not.', 'wpcredits-program-manager' ),
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
	 * How a built-in track's definition compares with the hand-written form its PHP runs.
	 *
	 * Shown on the rows the switch applies to, because it is the switch's whole condition: the two
	 * must be identical, in both directions (spec decision 3.5, and T2a's final review).
	 *
	 * @param array $row One row.
	 */
	private static function render_equivalence( array $row ) {
		$builtin     = 'builtin' === $row['source'];
		$differences = isset( $row['equivalence'] ) ? (array) $row['equivalence'] : array();

		if ( ! $builtin && empty( $row['switched'] ) ) {
			return;
		}

		if ( empty( $differences ) ) {
			echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html__( 'Identical to its hand-written form.', 'wpcredits-program-manager' ) . '</span>';

			return;
		}

		if ( array( 'not_published' ) === $differences ) {
			echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html__( 'Not published yet, so there is nothing to compare with its hand-written form.', 'wpcredits-program-manager' ) . '</span>';

			return;
		}

		$sentence = sprintf(
			/* translators: %s: what differs, separated by commas. */
			__( 'Differs from its hand-written form: %s. It cannot switch until they match.', 'wpcredits-program-manager' ),
			implode( ', ', array_map( 'strval', $differences ) )
		);

		echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html( $sentence ) . '</span>';
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

		$user = get_userdata( isset( $row['published_by'] ) ? (int) $row['published_by'] : 0 );

		return sprintf(
			/* translators: 1: a date and time, 2: who published the track. */
			__( '%1$s by %2$s', 'wpcredits-program-manager' ),
			wp_date( 'Y-m-d H:i', $at ),
			$user ? $user->display_name : __( 'somebody since removed', 'wpcredits-program-manager' )
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
			esc_attr( isset( $flash['status'] ) && 'error' === $flash['status'] ? 'error' : 'success' ),
			esc_html( (string) $flash['message'] )
		);
	}
}
