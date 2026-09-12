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
