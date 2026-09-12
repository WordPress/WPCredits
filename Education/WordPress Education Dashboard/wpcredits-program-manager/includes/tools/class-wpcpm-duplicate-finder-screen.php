<?php
/**
 * Student Duplicate Finder: the markup.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the finder prints: the list, the confirmation, a copy and the log.
 *
 * Kept apart from `WPCPM_Duplicate_Finder`, which decides what happens, so that each file does one
 * thing: every method here takes the facts it prints as arguments and reads nothing else.
 *
 * The rules speak in codes (`WPCPM_Duplicate_Rules`); the sentences are here, translated, and one
 * sentence serves a code wherever it is printed, so the list, the confirmation and the notice after
 * a delete cannot tell a manager two different things about one row (spec sections 6 and 7).
 */
final class WPCPM_Duplicate_Finder_Screen {

	/**
	 * The tables, as the screen names them.
	 *
	 * @return array<string, string>
	 */
	public static function table_names() {
		return array(
			'students' => __( 'Students', 'wpcredits-program-manager' ),
			'reports'  => __( 'Students Reports', 'wpcredits-program-manager' ),
			'feedback' => __( 'Feedback', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Why a row is proposed as it is, in a sentence.
	 *
	 * @param array $reason A reason from `WPCPM_Duplicate_Rules::classify()`.
	 * @param bool  $course Whether the row's status is Feedback's Course.
	 * @return string
	 */
	public static function reason( array $reason, $course = false ) {
		$status = isset( $reason['status'] ) ? (string) $reason['status'] : '';

		switch ( $reason['code'] ) {
			case 'only':
				return __( 'The only row in this table.', 'wpcredits-program-manager' );
			case 'newest':
				return __( 'The newest row.', 'wpcredits-program-manager' );
			case 'older':
				/* translators: %s: the row's status. */
				return '' !== $status ? sprintf( __( 'An older row at %s with nothing attached.', 'wpcredits-program-manager' ), $status ) : __( 'An older row with nothing attached.', 'wpcredits-program-manager' );
			case 'graduation':
				/* translators: %s: the row's status. */
				return sprintf( __( 'Records a graduation (%s).', 'wpcredits-program-manager' ), $status );
			case 'live':
				/* translators: %s: the row's status. */
				return sprintf( __( 'Still at a live status (%s).', 'wpcredits-program-manager' ), $status );
			case 'work':
				/* translators: %s: number of filled columns. */
				return sprintf( _n( 'Holds %s work field (grades, posts, hours or the project).', 'Holds %s work fields (grades, posts, hours or the project).', (int) $reason['count'], 'wpcredits-program-manager' ), number_format_i18n( (int) $reason['count'] ) );
			case 'answers':
				/* translators: %s: number of filled columns. */
				return sprintf( _n( 'Holds %s survey answer.', 'Holds %s survey answers.', (int) $reason['count'], 'wpcredits-program-manager' ), number_format_i18n( (int) $reason['count'] ) );
			case 'hours':
				/* translators: %s: the Total hours value. */
				return sprintf( __( 'Has Total hours set (%s).', 'wpcredits-program-manager' ), (string) $reason['hours'] );
			case 'notes':
				return __( 'Has Notes in Airtable.', 'wpcredits-program-manager' );
			case 'lacks-institution':
				return __( 'The newest row has no institution link and this one does.', 'wpcredits-program-manager' );
			case 'lacks-mentor':
				return __( 'The newest row has no mentor and this one does.', 'wpcredits-program-manager' );
			case 'lacks-status':
				return $course ? __( 'The newest row has no Course and this one does.', 'wpcredits-program-manager' ) : __( 'The newest row has no status and this one does.', 'wpcredits-program-manager' );
			case 'site':
				/* translators: %s: what on the site points at the row, for example "a site account". */
				return sprintf( __( 'The site points at it: %s. Delete the other one instead.', 'wpcredits-program-manager' ), self::refs( isset( $reason['refs'] ) ? (array) $reason['refs'] : array() ) );
			case 'same-second':
				return __( 'Created in the same second as the newest row.', 'wpcredits-program-manager' );
			case 'newest-abandoned':
				/* translators: %s: the newest row's status. */
				return sprintf( __( 'The newest row is at %s, so this one may be the row to keep.', 'wpcredits-program-manager' ), $status );
			case 'tie':
				return __( 'Created in the same second as the row before it, so "newest" decides nothing.', 'wpcredits-program-manager' );
			case 'inverted':
				/* translators: %s: the newest row's status. */
				return sprintf( __( 'This newest row is at %s while an older row is live or graduated.', 'wpcredits-program-manager' ), $status );
			case 'site-older':
				return __( 'The site uses an older row for this student, so this newest one may be the copy to delete.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * Why a row was left out of a delete, in a sentence.
	 *
	 * @param string $code A code from `expand()` or `recheck()`.
	 * @return string
	 */
	public static function refusal( $code ) {
		$sentences = array(
			'unknown'        => __( 'It is no longer in the list.', 'wpcredits-program-manager' ),
			'not-ready'      => __( 'This student needs a decision, so their rows are ticked one by one.', 'wpcredits-program-manager' ),
			'not-selectable' => __( 'This row cannot be deleted from the finder.', 'wpcredits-program-manager' ),
			'limit'          => __( 'It is over the 100 rows one confirmation deletes.', 'wpcredits-program-manager' ),
			'gone'           => __( 'It is no longer in Airtable under this address.', 'wpcredits-program-manager' ),
			'site'           => __( 'The site points at it now.', 'wpcredits-program-manager' ),
			'changed'        => __( 'It changed since the scan.', 'wpcredits-program-manager' ),
			'last-row'       => __( 'It is the last row this address has in the table.', 'wpcredits-program-manager' ),
		);

		return isset( $sentences[ $code ] ) ? $sentences[ $code ] : '';
	}

	/**
	 * What the site points at a row with, in words.
	 *
	 * @param array $refs The row's site references.
	 * @return string
	 */
	public static function refs( array $refs ) {
		$counts = array();

		foreach ( $refs as $ref ) {
			$label            = self::ref_label( (string) $ref['kind'], (string) $ref['key'] );
			$counts[ $label ] = ( isset( $counts[ $label ] ) ? $counts[ $label ] : 0 ) + 1;
		}

		$parts = array();

		foreach ( $counts as $label => $n ) {
			/* translators: 1: how many, 2: what, for example "mentor call note(s)". */
			$parts[] = $n > 1 ? sprintf( __( '%1$s × %2$s', 'wpcredits-program-manager' ), number_format_i18n( $n ), $label ) : $label;
		}

		return implode( ', ', $parts );
	}

	/**
	 * The list: the scan, the tiles, the students, the selection bar and the log.
	 *
	 * @param array $args `report`, `progress`, `last`, `next`, `enabled`, `log`, `flash`, `url`, and
	 *                    `checked` (`keys` and `pairs` to tick, when coming back from the confirmation).
	 */
	public static function render_list( array $args ) {
		$report   = (array) $args['report'];
		$progress = (array) $args['progress'];
		$running  = ! empty( $progress['running'] );

		echo '<div class="wrap wpcpm-wrap wpcpm-duplicates">';
		printf( '<h1>%s</h1>', esc_html__( 'Student Duplicate Finder', 'wpcredits-program-manager' ) );

		self::render_notice( is_array( $args['flash'] ) ? $args['flash'] : array() );

		if ( ! empty( $progress['error'] ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last scan error:', 'wpcredits-program-manager' ),
				esc_html( (string) $progress['error'] )
			);
		}

		self::render_scan_panel( $progress, (int) $args['last'], (int) $args['next'] );

		if ( ! $report ) {
			echo '<p>' . esc_html__( 'No scan has finished yet. Scan now reads Students, Students Reports and Feedback and lists every student with more than one row.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			self::render_tiles( $report );

			if ( empty( $args['enabled'] ) ) {
				printf(
					'<div class="notice notice-info inline"><p>%s</p></div>',
					wp_kses(
						sprintf(
							/* translators: %s: link to the settings screen. */
							__( 'Deleting is switched off, so this list is read-only. A program manager turns it on under %s.', 'wpcredits-program-manager' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ) . '">' . esc_html__( 'WPCredits Program > Settings', 'wpcredits-program-manager' ) . '</a>'
						),
						array( 'a' => array( 'href' => true ) )
					)
				);
			}

			self::render_groups_form( $report, ! empty( $args['enabled'] ), $running, (string) $args['url'], isset( $args['checked'] ) ? (array) $args['checked'] : array() );
		}

		self::render_log( (array) $args['log'], (string) $args['url'] );

		echo '</div>';
	}

	/**
	 * The confirmation: exactly what a press of Delete removes.
	 *
	 * @param array $args `report`, `chosen` (`expand()`'s answer), `keys`, `pairs`, `nonce`, `enabled`,
	 *                    `running`, `url`.
	 */
	public static function render_confirm( array $args ) {
		$report = (array) $args['report'];
		$rows   = (array) $args['chosen']['rows'];
		$names  = self::table_names();
		$tally  = array_fill_keys( array_keys( $names ), 0 );

		foreach ( $rows as $pick ) {
			++$tally[ $pick['table'] ];
		}

		echo '<div class="wrap wpcpm-wrap wpcpm-duplicates">';
		printf( '<h1>%s</h1>', esc_html__( 'Student Duplicate Finder', 'wpcredits-program-manager' ) );
		printf( '<h2>%s</h2>', esc_html__( 'Confirm the delete', 'wpcredits-program-manager' ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing in this selection can be deleted.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: rows in total, 2: Students rows, 3: Students Reports rows, 4: Feedback rows. */
						_n( '%1$s row will be deleted from Airtable: Students %2$s, Students Reports %3$s, Feedback %4$s. A sealed copy of each row is kept on this site for 30 days.', '%1$s rows will be deleted from Airtable: Students %2$s, Students Reports %3$s, Feedback %4$s. A sealed copy of each row is kept on this site for 30 days.', count( $rows ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) ),
						number_format_i18n( $tally['students'] ),
						number_format_i18n( $tally['reports'] ),
						number_format_i18n( $tally['feedback'] )
					)
				)
			);
		}

		self::render_dropped( (array) $args['chosen']['dropped'], $report );

		$by_student = array();
		foreach ( $rows as $pick ) {
			$by_student[ $pick['key'] ][] = $pick;
		}

		foreach ( $by_student as $key => $picks ) {
			$group = $report['groups'][ $key ];

			echo '<section class="wpcpm-duplicates__group">';
			printf( '<h3>%1$s <span class="wpcpm-duplicates__email">%2$s</span></h3>', esc_html( '' !== $group['name'] ? $group['name'] : __( '(no name)', 'wpcredits-program-manager' ) ), esc_html( $group['email'] ) );
			echo '<div class="wpcpm-duplicates__scroll"><table class="widefat striped"><thead><tr>';
			foreach ( array( __( 'Table', 'wpcredits-program-manager' ), __( 'Record', 'wpcredits-program-manager' ), __( 'Created', 'wpcredits-program-manager' ), __( 'Status or Course', 'wpcredits-program-manager' ), __( 'Why', 'wpcredits-program-manager' ) ) as $heading ) {
				printf( '<th scope="col">%s</th>', esc_html( $heading ) );
			}
			echo '</tr></thead><tbody>';

			foreach ( $picks as $pick ) {
				$row = self::find_row( $group, $pick['table'], $pick['id'] );
				printf(
					'<tr><td>%1$s</td><td><code>%2$s</code></td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
					esc_html( $names[ $pick['table'] ] ),
					esc_html( $pick['id'] ),
					esc_html( substr( (string) $row['created'], 0, 10 ) ),
					esc_html( (string) $row['status'] ),
					esc_html( self::reasons( $row, 'feedback' === $pick['table'] ) )
				);
			}

			echo '</tbody></table></div></section>';
		}

		$blocked = '';
		if ( empty( $args['enabled'] ) ) {
			$blocked = __( 'Deleting is switched off under WPCredits Program > Settings.', 'wpcredits-program-manager' );
		} elseif ( ! empty( $args['running'] ) ) {
			$blocked = __( 'A scan is running and is about to write a new list. Wait for it to finish, then review the selection again.', 'wpcredits-program-manager' );
		}

		if ( '' !== $blocked ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $blocked ) );
		}

		echo '<div class="wpcpm-duplicates__actions">';

		if ( ! empty( $rows ) ) {
			printf( '<form method="post" action="%s" class="wpcpm-duplicates__inline">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( (string) $args['nonce'] );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Duplicate_Finder::ACTION_DELETE ) );
			self::hidden_selection( (array) $args['keys'], (array) $args['pairs'] );
			printf(
				'<button type="submit" class="button button-primary wpcpm-duplicates__delete"%1$s>%2$s</button>',
				'' !== $blocked ? ' disabled' : '',
				esc_html(
					sprintf(
						/* translators: %s: number of rows. */
						_n( 'Delete %s row from Airtable', 'Delete %s rows from Airtable', count( $rows ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) )
					)
				)
			);
			echo '</form>';
		}

		// Back with the same ticks: a form, because the selection travels in the post, not the URL.
		printf( '<form method="post" action="%s" class="wpcpm-duplicates__inline">', esc_url( (string) $args['url'] ) );
		wp_nonce_field( WPCPM_Duplicate_Finder::ACTION_REVIEW );
		echo '<input type="hidden" name="wpcpm_back" value="1" />';
		self::hidden_selection( (array) $args['keys'], (array) $args['pairs'] );
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Back to the list', 'wpcredits-program-manager' ) );
		echo '</form>';

		echo '</div></div>';
	}

	/**
	 * One copy, unsealed, for typing a row back into Airtable by hand.
	 *
	 * @param int            $copy_id The copy's post ID.
	 * @param array|WP_Error $record  `WPCPM_Duplicate_Vault::view()`'s answer.
	 * @param string         $url     The finder's screen.
	 */
	public static function render_copy( $copy_id, $record, $url ) {
		echo '<div class="wrap wpcpm-wrap wpcpm-duplicates">';
		printf( '<h1>%s</h1>', esc_html__( 'Student Duplicate Finder', 'wpcredits-program-manager' ) );
		printf( '<h2>%s</h2>', esc_html__( 'A deleted row', 'wpcredits-program-manager' ) );

		if ( is_wp_error( $record ) ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $record->get_error_message() ) );
		} else {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: record ID, 2: when the row was created. */
						__( 'Record %1$s, created %2$s, as it was when it was deleted. There is no automatic restore: re-creating a Students row can make the Airtable automation build a new report and feedback pair, so type back only what is needed.', 'wpcredits-program-manager' ),
						(string) $record['id'],
						substr( (string) $record['createdTime'], 0, 10 )
					)
				)
			);
			echo '<div class="wpcpm-duplicates__scroll"><table class="widefat striped"><tbody>';

			foreach ( (array) $record['fields'] as $column => $value ) {
				printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( (string) $column ), esc_html( self::cell( $value ) ) );
			}

			echo '</tbody></table></div>';
		}

		printf( '<p><a class="button" href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to the list', 'wpcredits-program-manager' ) );
		echo '</div>';
	}

	/**
	 * The notice a press left.
	 *
	 * @param array $flash `status` and what it carries.
	 */
	private static function render_notice( array $flash ) {
		if ( empty( $flash['status'] ) ) {
			return;
		}

		$status   = (string) $flash['status'];
		$sentence = '';
		$type     = 'success';

		switch ( $status ) {
			case 'started':
				$sentence = __( 'Scan started. Progress is shown below and updates as it runs.', 'wpcredits-program-manager' );
				break;
			case 'running':
				$type     = 'info';
				$sentence = __( 'A scan is already running; its progress is shown below.', 'wpcredits-program-manager' );
				break;
			case 'cancelled':
				$type     = 'info';
				$sentence = __( 'Scan canceled. The last list stays.', 'wpcredits-program-manager' );
				break;
			case 'error':
				$type     = 'error';
				$sentence = __( 'That action could not be completed. See the error below.', 'wpcredits-program-manager' );
				break;
			case 'switched-off':
				$type     = 'warning';
				$sentence = __( 'Nothing was deleted: deleting is switched off under WPCredits Program > Settings.', 'wpcredits-program-manager' );
				break;
			case 'scan-running':
				$type     = 'warning';
				$sentence = __( 'Nothing was deleted: a scan is running and is about to write a new list. Review the selection again when it finishes.', 'wpcredits-program-manager' );
				break;
			case 'no-seal':
				$type     = 'error';
				$sentence = __( 'Nothing was deleted: this site cannot encrypt, so it cannot keep the copy a delete needs.', 'wpcredits-program-manager' );
				break;
			case 'nothing':
				$type     = 'warning';
				$sentence = __( 'Nothing in that selection could be deleted.', 'wpcredits-program-manager' );
				break;
			case 'read-failed':
				$type = 'error';
				/* translators: %s: the error Airtable returned. */
				$sentence = sprintf( __( 'Nothing was deleted: Airtable could not be read to check the rows again (%s).', 'wpcredits-program-manager' ), (string) $flash['detail'] );
				break;
			case 'copy-failed':
				$type = 'error';
				/* translators: %s: the error. */
				$sentence = sprintf( __( 'Nothing was deleted: a copy could not be kept (%s).', 'wpcredits-program-manager' ), (string) $flash['detail'] );
				break;
			case 'deleted':
			case 'stopped':
				$tally = isset( $flash['deleted'] ) ? (array) $flash['deleted'] : array();
				$sum   = array_sum( $tally );

				$sentence = sprintf(
					/* translators: 1: rows in total, 2: Students rows, 3: Students Reports rows, 4: Feedback rows, 5: date the copies are kept until. */
					_n( 'Deleted %1$s row: Students %2$s, Students Reports %3$s, Feedback %4$s. A sealed copy of each is kept until %5$s.', 'Deleted %1$s rows: Students %2$s, Students Reports %3$s, Feedback %4$s. A sealed copy of each is kept until %5$s.', $sum, 'wpcredits-program-manager' ),
					number_format_i18n( $sum ),
					number_format_i18n( isset( $tally['students'] ) ? (int) $tally['students'] : 0 ),
					number_format_i18n( isset( $tally['reports'] ) ? (int) $tally['reports'] : 0 ),
					number_format_i18n( isset( $tally['feedback'] ) ? (int) $tally['feedback'] : 0 ),
					wp_date( 'j F Y', isset( $flash['until'] ) ? (int) $flash['until'] : time() )
				);

				if ( 'stopped' === $status ) {
					$type = 'warning';
					/* translators: 1: what was deleted, 2: what Airtable said, which after a rate limit is when to try again. */
					$sentence = sprintf( __( 'Airtable stopped the delete part of the way through: %2$s %1$s Rows it did not confirm are checked again by the daily run.', 'wpcredits-program-manager' ), $sentence, (string) $flash['detail'] );
				}
				break;
			default:
				return;
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p>', esc_attr( $type ), esc_html( $sentence ) );

		if ( ! empty( $flash['refused'] ) ) {
			echo '<p>' . esc_html__( 'Left out, and not deleted:', 'wpcredits-program-manager' ) . '</p><ul class="wpcpm-duplicates__refused">';

			foreach ( (array) $flash['refused'] as $refusal ) {
				$names = self::table_names();
				printf(
					'<li>%1$s</li>',
					esc_html(
						trim(
							( isset( $refusal['table'], $names[ $refusal['table'] ] ) ? $names[ $refusal['table'] ] . ' ' : '' )
							. ( isset( $refusal['id'] ) ? $refusal['id'] . ': ' : '' )
							. self::refusal( (string) $refusal['code'] )
						)
					)
				);
			}

			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * The scan's card: when it last ran and when it runs next, or the progress while it runs.
	 *
	 * The progress markup is the syncs' own, which assets/js/admin.js polls.
	 *
	 * @param array $progress `WPCPM_Duplicates_Scan::progress()`.
	 * @param int   $last     When the last scan finished.
	 * @param int   $next     When the next one is scheduled.
	 */
	private static function render_scan_panel( array $progress, $last, $next ) {
		echo '<div class="wpcpm-card">';
		printf( '<h2>%s</h2>', esc_html__( 'Scan', 'wpcredits-program-manager' ) );
		echo '<p class="description">' . esc_html__( 'Reads Students, Students Reports and Feedback every three hours and lists every student with more than one row in any of them. A scan writes nothing to Airtable.', 'wpcredits-program-manager' ) . '</p>';

		if ( ! empty( $progress['running'] ) ) {
			printf(
				'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
				esc_attr( WPCPM_Duplicate_Finder::ACTION_TICK ),
				esc_attr( wp_create_nonce( WPCPM_Duplicate_Finder::ACTION_TICK ) )
			);
			echo '<p class="wpcpm-progress__head"><span class="spinner is-active" aria-hidden="true"></span> ';
			printf( '<strong data-wpcpm-label>%s</strong>', esc_html( (string) $progress['label'] ) );
			printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( (string) $progress['step_label'] ) );
			echo '</p>';
			$percent = (int) $progress['percent'];
			printf(
				'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar><div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div></div>',
				(int) $percent,
				esc_attr__( 'Scan progress', 'wpcredits-program-manager' )
			);
			echo '<p class="wpcpm-progress__meta">';
			printf( '<span data-wpcpm-percent>%d%%</span> - ', (int) $percent );
			printf( '<span data-wpcpm-detail>%s</span> - ', esc_html( (string) $progress['detail'] ) );
			/* translators: %s: elapsed time as a clock value. */
			$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
			printf(
				'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
				esc_attr( $elapsed_label ),
				esc_html( sprintf( $elapsed_label, WPCPM_Mentors::format_duration( (int) $progress['elapsed'] ) ) )
			);
			echo '</p>';
			printf(
				'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
				! empty( $progress['stalled'] ) ? '' : ' hidden',
				esc_html__( 'No progress for over two minutes. The scan may have been interrupted: cancel it and start again.', 'wpcredits-program-manager' )
			);
			echo '<noscript><meta http-equiv="refresh" content="15" /></noscript>';
			echo '</div>';
			printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( WPCPM_Duplicate_Finder::ACTION_CANCEL );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Duplicate_Finder::ACTION_CANCEL ) );
			submit_button( __( 'Cancel scan', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			if ( $last ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: date and time, 2: human-readable time difference. */
							__( 'Last scan finished %1$s (%2$s ago).', 'wpcredits-program-manager' ),
							wp_date( 'Y-m-d H:i', $last ),
							human_time_diff( $last, time() )
						)
					)
				);
			} else {
				echo '<p>' . esc_html__( 'No scan has run yet.', 'wpcredits-program-manager' ) . '</p>';
			}

			if ( $next ) {
				/* translators: %s: date and time of the next scan. */
				printf( '<p>%s</p>', esc_html( sprintf( __( 'Next scan: %s.', 'wpcredits-program-manager' ), wp_date( 'Y-m-d H:i', $next ) ) ) );
			}

			printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( WPCPM_Duplicate_Finder::ACTION_SCAN );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Duplicate_Finder::ACTION_SCAN ) );
			submit_button( __( 'Scan now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * The numbers above the list.
	 *
	 * @param array $report The stored report.
	 */
	private static function render_tiles( array $report ) {
		$counts = $report['counts'];
		$names  = self::table_names();
		$tiles  = array(
			/* translators: %s: number. */
			sprintf( _n( '%s duplicated student', '%s duplicated students', (int) $counts['addresses'], 'wpcredits-program-manager' ), number_format_i18n( (int) $counts['addresses'] ) ),
			/* translators: %s: number. */
			sprintf( _n( '%s ready: every older row is a clean delete candidate', '%s ready: every older row is a clean delete candidate', (int) $counts['ready'], 'wpcredits-program-manager' ), number_format_i18n( (int) $counts['ready'] ) ),
			/* translators: %s: number. */
			sprintf( _n( '%s needs a decision', '%s need a decision', (int) $counts['decide'], 'wpcredits-program-manager' ), number_format_i18n( (int) $counts['decide'] ) ),
		);

		foreach ( $names as $table => $name ) {
			$tiles[] = sprintf(
				/* translators: 1: table name, 2: rows proposed for deletion, 3: rows held for a decision. */
				__( '%1$s: %2$s proposed for deletion, %3$s held', 'wpcredits-program-manager' ),
				$name,
				number_format_i18n( (int) $counts['candidates'][ $table ] ),
				number_format_i18n( (int) $counts['held'][ $table ] )
			);
		}

		echo '<ul class="wpcpm-duplicates__tiles">';
		foreach ( $tiles as $tile ) {
			printf( '<li>%s</li>', esc_html( $tile ) );
		}
		echo '</ul>';
	}

	/**
	 * The students, Ready first, inside the form that posts a selection to the confirmation.
	 *
	 * @param array  $report  The stored report.
	 * @param bool   $enabled Whether deleting is switched on.
	 * @param bool   $running Whether a scan is running.
	 * @param string $url     The finder's screen.
	 * @param array  $checked `keys` and `pairs` to tick.
	 */
	private static function render_groups_form( array $report, $enabled, $running, $url, array $checked ) {
		$ready  = array();
		$decide = array();

		foreach ( $report['groups'] as $key => $group ) {
			if ( WPCPM_Duplicate_Rules::READY === $group['verdict'] ) {
				$ready[ $key ] = $group;
			} else {
				$decide[ $key ] = $group;
			}
		}

		printf( '<form method="post" action="%s" class="wpcpm-duplicates__form" data-wpcpm-duplicates>', esc_url( $url ) );
		wp_nonce_field( WPCPM_Duplicate_Finder::ACTION_REVIEW );
		echo '<input type="hidden" name="wpcpm_review" value="1" />';

		/* translators: %s: number of students. */
		printf( '<h2>%s</h2>', esc_html( sprintf( __( 'Ready: every older row is a clean delete candidate (%s)', 'wpcredits-program-manager' ), number_format_i18n( count( $ready ) ) ) ) );
		foreach ( $ready as $key => $group ) {
			self::render_group( (string) $key, $group, $enabled, $checked );
		}

		/* translators: %s: number of students. */
		printf( '<h2>%s</h2>', esc_html( sprintf( __( 'Needs a decision (%s)', 'wpcredits-program-manager' ), number_format_i18n( count( $decide ) ) ) ) );
		foreach ( $decide as $key => $group ) {
			self::render_group( (string) $key, $group, $enabled, $checked );
		}

		$can_review = $enabled && ! $running;

		echo '<div class="wpcpm-duplicates__bar" data-wpcpm-selection>';
		printf(
			'<p class="wpcpm-duplicates__count" data-wpcpm-selection-count data-template="%1$s" aria-live="polite">%2$s</p>',
			/* translators: 1: rows in total, 2: Students rows, 3: Students Reports rows, 4: Feedback rows. */
			esc_attr__( '%1$s rows selected: Students %2$s, Students Reports %3$s, Feedback %4$s', 'wpcredits-program-manager' ),
			esc_html( $can_review ? __( 'Tick the students and rows to delete, then press Review selection.', 'wpcredits-program-manager' ) : ( $running ? __( 'A scan is running. Review selection comes back when it finishes.', 'wpcredits-program-manager' ) : __( 'Deleting is switched off, so nothing can be selected.', 'wpcredits-program-manager' ) ) )
		);

		if ( $can_review ) {
			printf( '<button type="button" class="button" data-wpcpm-select-ready hidden>%s</button> ', esc_html__( 'Select all ready', 'wpcredits-program-manager' ) );
			printf( '<button type="button" class="button" data-wpcpm-clear hidden>%s</button> ', esc_html__( 'Clear', 'wpcredits-program-manager' ) );
		}

		printf( '<button type="submit" class="button button-primary"%1$s>%2$s</button>', $can_review ? '' : ' disabled', esc_html__( 'Review selection', 'wpcredits-program-manager' ) );
		echo '</div></form>';
	}

	/**
	 * One student: the header, the flags, and a table of every row in the three tables.
	 *
	 * @param string $key     The student key.
	 * @param array  $group   The classified group.
	 * @param bool   $enabled Whether deleting is switched on.
	 * @param array  $checked `keys` and `pairs` to tick.
	 */
	private static function render_group( $key, array $group, $enabled, array $checked ) {
		$names  = self::table_names();
		$ready  = WPCPM_Duplicate_Rules::READY === $group['verdict'];
		$name   = '' !== $group['name'] ? $group['name'] : __( '(no name)', 'wpcredits-program-manager' );
		$tables = array();
		$going  = 0;
		$keys   = isset( $checked['keys'] ) ? (array) $checked['keys'] : array();
		$pairs  = isset( $checked['pairs'] ) ? (array) $checked['pairs'] : array();

		foreach ( $group['rows'] as $table => $rows ) {
			foreach ( $rows as $row ) {
				if ( WPCPM_Duplicate_Rules::DELETE === $row['proposal'] ) {
					$tables[] = $table;
					++$going;
				}
			}
		}

		printf( '<section class="wpcpm-duplicates__group" id="%s">', esc_attr( 'wpcpm-dup-' . $key ) );
		echo '<header class="wpcpm-duplicates__head">';

		if ( $ready && $enabled ) {
			printf(
				'<label class="wpcpm-duplicates__pick"><input type="checkbox" name="wpcpm_students[]" value="%1$s" data-rows="%2$s" data-ready%3$s /> %4$s</label>',
				esc_attr( $key ),
				esc_attr( implode( ' ', $tables ) ),
				in_array( $key, $keys, true ) ? ' checked' : '',
				esc_html(
					sprintf(
						/* translators: 1: number of rows, 2: student name. */
						_n( 'Delete the %1$s older row of %2$s', 'Delete the %1$s older rows of %2$s', $going, 'wpcredits-program-manager' ),
						number_format_i18n( $going ),
						$name
					)
				)
			);
		} else {
			printf( '<h3>%s</h3>', esc_html( $name ) );
		}

		printf( ' <span class="wpcpm-duplicates__email">%s</span>', esc_html( (string) $group['email'] ) );
		printf(
			' <span class="wpcpm-duplicates__counts">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: Students rows, 2: Students Reports rows, 3: Feedback rows. */
					__( 'Students %1$s · Students Reports %2$s · Feedback %3$s', 'wpcredits-program-manager' ),
					number_format_i18n( (int) $group['counts']['students'] ),
					number_format_i18n( (int) $group['counts']['reports'] ),
					number_format_i18n( (int) $group['counts']['feedback'] )
				)
			)
		);
		printf( ' <span class="wpcpm-duplicates__badge wpcpm-duplicates__badge--%1$s">%2$s</span>', esc_attr( $ready ? 'ready' : 'decide' ), esc_html( $ready ? __( 'Ready', 'wpcredits-program-manager' ) : __( 'Needs a decision', 'wpcredits-program-manager' ) ) );
		echo '</header>';

		foreach ( (array) $group['flags'] as $flag ) {
			printf( '<p class="wpcpm-duplicates__flag">%s</p>', esc_html( self::flag( (string) $flag, $group['counts'] ) ) );
		}

		echo '<div class="wpcpm-duplicates__scroll"><table class="widefat striped"><thead><tr>';
		$headings = array( __( 'Delete', 'wpcredits-program-manager' ), __( 'Table', 'wpcredits-program-manager' ), __( 'Proposal', 'wpcredits-program-manager' ), __( 'Why', 'wpcredits-program-manager' ), __( 'Created', 'wpcredits-program-manager' ), __( 'Status or Course', 'wpcredits-program-manager' ), __( 'Institution', 'wpcredits-program-manager' ), __( 'Mentor', 'wpcredits-program-manager' ), __( 'Dates', 'wpcredits-program-manager' ), __( 'Hours', 'wpcredits-program-manager' ), __( 'Work or answers', 'wpcredits-program-manager' ), __( 'On the site', 'wpcredits-program-manager' ), __( 'Record', 'wpcredits-program-manager' ) );
		foreach ( $headings as $heading ) {
			printf( '<th scope="col">%s</th>', esc_html( $heading ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( WPCPM_Duplicate_Rules::TABLES as $table ) {
			foreach ( $group['rows'][ $table ] as $row ) {
				$pair   = $table . ':' . $row['id'];
				$reason = 'wpcpm-dup-why-' . $row['id'];

				echo '<tr>';

				if ( ! $ready && $enabled && ! empty( $row['selectable'] ) ) {
					printf(
						'<td><input type="checkbox" name="wpcpm_rows[]" value="%1$s" data-rows="%2$s"%3$s aria-describedby="%4$s" aria-label="%5$s" /></td>',
						esc_attr( $pair ),
						esc_attr( $table ),
						in_array( $pair, $pairs, true ) ? ' checked' : '',
						esc_attr( $reason ),
						/* translators: 1: table name, 2: record ID, 3: student name. */
						esc_attr( sprintf( __( 'Delete the %1$s row %2$s of %3$s', 'wpcredits-program-manager' ), $names[ $table ], $row['id'], $name ) )
					);
				} elseif ( ! empty( $row['locked'] ) ) {
					printf(
						'<td><input type="checkbox" disabled aria-describedby="%1$s" aria-label="%2$s" /></td>',
						esc_attr( $reason ),
						/* translators: 1: table name, 2: record ID, 3: student name. */
						esc_attr( sprintf( __( 'The %1$s row %2$s of %3$s cannot be deleted', 'wpcredits-program-manager' ), $names[ $table ], $row['id'], $name ) )
					);
				} else {
					echo '<td></td>';
				}

				printf(
					'<td>%1$s</td><td><span class="wpcpm-duplicates__badge wpcpm-duplicates__badge--%2$s">%3$s</span></td><td id="%4$s">%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td><td>%9$s</td><td>%10$s</td><td>%11$s</td><td>%12$s</td><td>%13$s</td><td><a href="%14$s" target="_blank" rel="noopener"><code>%15$s</code></a></td>',
					esc_html( $names[ $table ] ),
					esc_attr( $row['proposal'] ),
					esc_html( self::proposal( (string) $row['proposal'] ) ),
					esc_attr( $reason ),
					esc_html( self::reasons( $row, 'feedback' === $table ) ),
					esc_html( substr( (string) $row['created'], 0, 10 ) ),
					esc_html( (string) $row['status'] ),
					esc_html( self::institutions( (array) $row['institution'] ) ),
					esc_html( $row['mentor'] ? __( 'Linked', 'wpcredits-program-manager' ) : '' ),
					esc_html( trim( $row['start'] . ( '' !== $row['end'] ? ' - ' . $row['end'] : '' ) ) ),
					esc_html( (string) $row['hours'] ),
					esc_html( $row['work'] ? number_format_i18n( (int) $row['work'] ) : '' ),
					esc_html( self::refs( isset( $group['refs'][ $row['id'] ] ) ? (array) $group['refs'][ $row['id'] ] : array() ) ),
					esc_url( self::airtable_url( $table, (string) $row['id'] ) ),
					esc_html( (string) $row['id'] )
				);

				echo '</tr>';
			}
		}

		echo '</tbody></table></div></section>';
	}

	/**
	 * The log: every row the finder deleted, newest first.
	 *
	 * @param array  $entries `WPCPM_Duplicate_Vault::entries()`.
	 * @param string $url     The finder's screen.
	 */
	private static function render_log( array $entries, $url ) {
		$names = self::table_names();

		echo '<div class="wpcpm-card">';
		printf( '<h2>%s</h2>', esc_html__( 'Deleted rows', 'wpcredits-program-manager' ) );

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing has been deleted yet.', 'wpcredits-program-manager' ) . '</p></div>';
			return;
		}

		echo '<div class="wpcpm-duplicates__scroll"><table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'When', 'wpcredits-program-manager' ), __( 'Who', 'wpcredits-program-manager' ), __( 'Table', 'wpcredits-program-manager' ), __( 'Record', 'wpcredits-program-manager' ), __( 'Created', 'wpcredits-program-manager' ), __( 'Status or Course', 'wpcredits-program-manager' ), __( 'Copy', 'wpcredits-program-manager' ) ) as $heading ) {
			printf( '<th scope="col">%s</th>', esc_html( $heading ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			$who = get_userdata( (int) $entry['by'] );

			if ( WPCPM_Duplicate_Vault::STATE_DELETED === $entry['state'] ) {
				$copy = sprintf(
					'%1$s <a href="%2$s">%3$s</a>',
					esc_html(
						sprintf(
							/* translators: %s: date. */
							__( 'Kept until %s.', 'wpcredits-program-manager' ),
							wp_date( 'j F Y', (int) $entry['expires'] )
						)
					),
					esc_url( wp_nonce_url( add_query_arg( 'wpcpm_copy', (int) $entry['copy'], $url ), WPCPM_Duplicate_Finder::ACTION_VIEW . '_' . (int) $entry['copy'] ) ),
					esc_html__( 'View copy', 'wpcredits-program-manager' )
				);
			} elseif ( WPCPM_Duplicate_Vault::STATE_PENDING === $entry['state'] ) {
				$copy = esc_html__( 'Airtable did not confirm this delete; the daily run checks it again.', 'wpcredits-program-manager' );
			} else {
				$copy = esc_html__( 'Erased after 30 days.', 'wpcredits-program-manager' );
			}

			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td><code>%4$s</code></td><td>%5$s</td><td>%6$s</td><td>%7$s</td></tr>',
				esc_html( wp_date( 'Y-m-d H:i', (int) $entry['when'] ) ),
				esc_html( $who ? $who->display_name : '' ),
				esc_html( isset( $names[ $entry['table'] ] ) ? $names[ $entry['table'] ] : $entry['table'] ),
				esc_html( $entry['record'] ),
				esc_html( $entry['created'] ),
				esc_html( $entry['status'] ),
				$copy // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped piece by piece above.
			);
		}

		echo '</tbody></table></div></div>';
	}

	/**
	 * The rows a posted selection left out, before the rows it keeps.
	 *
	 * @param array $dropped `expand()`'s dropped rows.
	 * @param array $report  The stored report.
	 */
	private static function render_dropped( array $dropped, array $report ) {
		if ( empty( $dropped ) ) {
			return;
		}

		$names = self::table_names();

		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Left out of this delete:', 'wpcredits-program-manager' ) . '</p><ul class="wpcpm-duplicates__refused">';

		foreach ( $dropped as $one ) {
			$who = isset( $one['key'], $report['groups'][ $one['key'] ] ) ? $report['groups'][ $one['key'] ]['name'] . ': ' : '';
			$row = isset( $one['table'], $one['id'], $names[ $one['table'] ] ) ? $names[ $one['table'] ] . ' ' . $one['id'] . ': ' : '';

			printf( '<li>%s</li>', esc_html( $who . $row . self::refusal( (string) $one['code'] ) ) );
		}

		echo '</ul></div>';
	}

	/**
	 * The selection, as hidden fields, for the delete and for Back to the list.
	 *
	 * @param string[] $keys  Student keys.
	 * @param string[] $pairs `table:record` pairs.
	 */
	private static function hidden_selection( array $keys, array $pairs ) {
		foreach ( $keys as $key ) {
			printf( '<input type="hidden" name="wpcpm_students[]" value="%s" />', esc_attr( $key ) );
		}
		foreach ( $pairs as $pair ) {
			printf( '<input type="hidden" name="wpcpm_rows[]" value="%s" />', esc_attr( $pair ) );
		}
	}

	/**
	 * A row's reasons, joined.
	 *
	 * @param array $row    A classified row.
	 * @param bool  $course Whether its status is Feedback's Course.
	 * @return string
	 */
	private static function reasons( array $row, $course ) {
		$sentences = array();

		foreach ( (array) $row['reasons'] as $reason ) {
			$sentences[] = self::reason( $reason, $course );
		}

		return implode( ' ', array_filter( $sentences ) );
	}

	/**
	 * A proposal, as its badge says it.
	 *
	 * @param string $proposal `keep`, `delete` or `review`.
	 * @return string
	 */
	private static function proposal( $proposal ) {
		$labels = array(
			'keep'   => __( 'Keep', 'wpcredits-program-manager' ),
			'delete' => __( 'Delete candidate', 'wpcredits-program-manager' ),
			'review' => __( 'Review', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $proposal ] ) ? $labels[ $proposal ] : $proposal;
	}

	/**
	 * A flag on a student, in a sentence.
	 *
	 * @param string $flag   `refire`, `pending` or `spelling`.
	 * @param array  $counts Table => rows.
	 * @return string
	 */
	private static function flag( $flag, array $counts ) {
		switch ( $flag ) {
			case 'refire':
				/* translators: 1: Students Reports rows, 2: Students rows. */
				return sprintf( __( 'More Students Reports rows (%1$s) than Students rows (%2$s): either the automation fired more than once for one Students row, or an older Students row has already been deleted by hand.', 'wpcredits-program-manager' ), number_format_i18n( (int) $counts['reports'] ), number_format_i18n( (int) $counts['students'] ) );
			case 'pending':
				/* translators: 1: Students rows, 2: Students Reports rows. */
				return sprintf( __( 'More Students rows (%1$s) than Students Reports rows (%2$s): a newer Students row has no report yet, and gets a report and a Feedback row as soon as it matches the automation\'s conditions.', 'wpcredits-program-manager' ), number_format_i18n( (int) $counts['students'] ), number_format_i18n( (int) $counts['reports'] ) );
			case 'spelling':
				return __( 'The address is spelled more than one way (case or spaces), so a lookup that compares it exactly finds only some of these rows.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * One kind of site reference, in words.
	 *
	 * @param string $kind `user` or a post type.
	 * @param string $key  The meta key.
	 * @return string
	 */
	private static function ref_label( $kind, $key ) {
		if ( 'user' === $kind && 'wpcpm_student_record_id' === $key ) {
			return __( 'a site account', 'wpcredits-program-manager' );
		}
		if ( 'user' === $kind && 'wpcpm_feedback_record' === $key ) {
			return __( 'a student\'s surveys', 'wpcredits-program-manager' );
		}

		$labels = array(
			'wpcpm_mentor_note' => __( 'a mentor call note', 'wpcredits-program-manager' ),
			'wpcpm_mentor_call' => __( 'a booked call', 'wpcredits-program-manager' ),
			'wpcpm_audit_entry' => __( 'an audit log entry', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : $kind;
	}

	/**
	 * Institution record IDs as names, where the site knows them.
	 *
	 * @param string[] $ids Record IDs.
	 * @return string
	 */
	private static function institutions( array $ids ) {
		$names = array();

		foreach ( $ids as $id ) {
			$row     = class_exists( 'WPCPM_Institutions_Index' ) ? WPCPM_Institutions_Index::row( $id ) : null;
			$names[] = ( is_array( $row ) && ! empty( $row['name'] ) ) ? trim( (string) $row['name'] ) : $id;
		}

		return implode( ', ', $names );
	}

	/**
	 * A cell as text, for View copy.
	 *
	 * @param mixed $value A cell.
	 * @return string
	 */
	private static function cell( $value ) {
		if ( is_array( $value ) ) {
			$parts = array();

			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					$parts[] = isset( $item['url'] ) ? (string) $item['url'] : ( isset( $item['name'] ) ? (string) $item['name'] : ( isset( $item['id'] ) ? (string) $item['id'] : '' ) );
				} else {
					$parts[] = (string) $item;
				}
			}

			return implode( ', ', array_filter( $parts, 'strlen' ) );
		}

		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'wpcredits-program-manager' ) : __( 'No', 'wpcredits-program-manager' );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A row's address in Airtable.
	 *
	 * @param string $table  `students`, `reports` or `feedback`.
	 * @param string $record Record ID.
	 * @return string
	 */
	private static function airtable_url( $table, $record ) {
		$settings = WPCPM_Settings::get();
		$key      = WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ];

		return 'https://airtable.com/' . rawurlencode( (string) $settings['base_id'] ) . '/' . rawurlencode( (string) $settings[ $key ] ) . '/' . rawurlencode( $record );
	}

	/**
	 * One row of a group, by table and record ID.
	 *
	 * @param array  $group  The classified group.
	 * @param string $table  Table.
	 * @param string $id     Record ID.
	 * @return array
	 */
	private static function find_row( array $group, $table, $id ) {
		foreach ( $group['rows'][ $table ] as $row ) {
			if ( $row['id'] === $id ) {
				return $row;
			}
		}

		return array(
			'created' => '',
			'status'  => '',
			'reasons' => array(),
		);
	}
}
