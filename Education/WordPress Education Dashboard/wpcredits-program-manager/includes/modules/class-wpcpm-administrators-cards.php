<?php
/**
 * The Administrator Dashboard's cards: what each reads, what each draws.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every queue a program manager works, read once and drawn as cards.
 *
 * Each card reads through one static method on the class that owns the data and posts to
 * the handler that already exists (design of 4 September 2026, decision 2): nothing here
 * queries posts or options itself, so the page can never show a count the owner would
 * compute differently. `collect()` reads everything once, because the attention strip and
 * the cards draw the same arrays.
 *
 * Sponsors' cards join in the Sponsor module's last phase; the ids here are the anchors
 * `WPCPM_Return::ANCHORS` names, so a decision posted from a card lands back on it.
 */
final class WPCPM_Administrators_Cards {
	/** Most rows a list draws before it says how many more there are. */
	const LIMIT = 50;
	/** Closed requests shown under the open ones. */
	const CLOSED_SHOWN = 20;
	/** The track keys `WPCPM_Program::track()` answers, in the order the strip draws them. */
	const TRACKS = array( '150h', '50h', 'dev' );

	/*
	 * --------------------------------------------------------------------
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * Every dataset the page draws, read once.
	 *
	 * @return array
	 */
	public static function collect() {
		$now    = time();
		$review = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) ) * DAY_IN_SECONDS;

		$awaiting = array();
		$overdue  = 0;

		foreach ( WPCPM_Institution_Agreement::awaiting_review( 200 ) as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$at   = (int) get_post_time( 'U', true, $post );
			$late = ( $now - $at ) > $review;

			if ( $late ) {
				++$overdue;
			}

			$awaiting[] = array(
				'id'      => (int) $post->ID,
				'record'  => (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
				'at'      => $at,
				'overdue' => $late,
			);
		}

		$open_requests   = array();
		$overdue_request = 0;

		foreach ( WPCPM_Institution_Request::open_requests( 200 ) as $request_id ) {
			$facts = WPCPM_Institution_Request::facts( (int) $request_id );

			if ( empty( $facts ) ) {
				continue;
			}

			if ( ! empty( $facts['overdue'] ) ) {
				++$overdue_request;
			}

			$open_requests[] = $facts;
		}

		$closed_requests = array();

		foreach ( WPCPM_Institution_Request::closed_requests( self::CLOSED_SHOWN ) as $request_id ) {
			$facts = WPCPM_Institution_Request::facts( (int) $request_id );

			if ( ! empty( $facts ) ) {
				$closed_requests[] = $facts;
			}
		}

		// "This semester" is the current half-year, the same window the semester report and
		// the roster strip use, so the three cannot disagree about what the phrase means.
		$from = WPCPM_Cohort::range( WPCPM_Cohort::current() );
		$from = '' !== $from['from'] ? (int) strtotime( $from['from'] . ' 00:00:00 UTC' ) : 0;

		$open   = WPCPM_Institution_Application::applications( WPCPM_Institutions::open_states() );
		$closed = WPCPM_Institution_Application::applications( array( WPCPM_Institution_Application::STATE_REJECTED, WPCPM_Institution_Application::STATE_SPAM ) );

		return array(
			// The full lists are fetched above (the totals need every row), but only the first
			// self::LIMIT of each is kept for drawing: the wp-admin queue caps its own list at
			// WPCPM_Institutions::QUEUE_MAX, which is the same number, and a page reading the
			// same rows must never draw a "complete" list the queue would call partial.
			'applications' => array(
				'open'         => array_slice( $open, 0, self::LIMIT ),
				'open_total'   => count( $open ),
				'closed'       => array_slice( $closed, 0, self::LIMIT ),
				'closed_total' => count( $closed ),
			),
			'agreements'   => array(
				'awaiting' => $awaiting,
				'returned' => self::agreement_rows( WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_RETURNED, self::LIMIT ) ),
				'revoked'  => self::agreement_rows( WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_REVOKED, self::LIMIT ) ),
				'overdue'  => $overdue,
			),
			'reports'      => array(
				'queue'    => WPCPM_Semester_Report::queue(),
				'due'      => WPCPM_Semester_Report::due( wp_date( 'Y-m-d' ) ),
				'approved' => WPCPM_Semester_Report::approved_since( $from ),
			),
			'requests'     => array(
				'open'    => $open_requests,
				'closed'  => $closed_requests,
				'overdue' => $overdue_request,
			),
			'locked'       => WPCPM_Institution_Roster::locked_today(),
			'programs'     => self::programs(),
			'health'       => self::health(),
		);
	}

	/**
	 * The eight tiles of the attention strip, from the arrays the cards draw.
	 *
	 * @param array $data What `collect()` returned.
	 * @return array[] `label`, `n`, `card`, keyed in the strip's order.
	 */
	public static function counts( array $data ) {
		return array(
			'applications'       => array(
				'label' => __( 'Applications waiting', 'wpcredits-program-manager' ),
				// The total, not count() of the (possibly capped) list that is actually drawn:
				// the strip's number is a fact about the queue, and must not shrink just because
				// the card beneath it stopped listing every row.
				'n'     => isset( $data['applications']['open_total'] ) ? (int) $data['applications']['open_total'] : count( $data['applications']['open'] ),
				'card'  => 'applications',
			),
			'agreements'         => array(
				'label' => __( 'Agreements to review', 'wpcredits-program-manager' ),
				'n'     => count( $data['agreements']['awaiting'] ),
				'card'  => 'agreements',
			),
			'overdue_agreements' => array(
				'label' => __( 'Agreements overdue', 'wpcredits-program-manager' ),
				'n'     => (int) $data['agreements']['overdue'],
				'card'  => 'agreements',
			),
			'drafts'             => array(
				'label' => __( 'Semester reports to review', 'wpcredits-program-manager' ),
				'n'     => count( $data['reports']['queue'] ),
				'card'  => 'reports',
			),
			'due'                => array(
				'label' => __( 'Semesters due for drafting', 'wpcredits-program-manager' ),
				'n'     => count( $data['reports']['due'] ),
				'card'  => 'reports',
			),
			'requests'           => array(
				'label' => __( 'Mentor requests open', 'wpcredits-program-manager' ),
				'n'     => count( $data['requests']['open'] ),
				'card'  => 'requests',
			),
			'overdue_requests'   => array(
				'label' => __( 'Mentor requests overdue', 'wpcredits-program-manager' ),
				'n'     => (int) $data['requests']['overdue'],
				'card'  => 'requests',
			),
			'locked'             => array(
				'label' => __( 'Locked accounts', 'wpcredits-program-manager' ),
				'n'     => count( $data['locked'] ),
				'card'  => 'health',
			),
		);
	}

	/**
	 * The programs running: totals per track, and one row per institution with somebody in progress.
	 *
	 * Reads only `status`, `start`, `end`, `reports` and `mentor_name` off roster rows, and
	 * never Airtable. "Signed up this semester" is a start date in the current half-year, on a
	 * track status, and it stays that definition even for a row that has since paused,
	 * graduated or left: only a track status says which track a signed-up row was on.
	 * "finished this semester" is an end date inside it on the exact status `Graduate`, the
	 * same test `WPCPM_Cohort::participation()` uses to bucket a row as graduated rather than
	 * withdrawn - so a student who dropped out is not "finished", because nothing was; that
	 * student is in neither number, the way `participation()` also leaves them out of both
	 * `graduated` and `signed_up`'s finished-sounding buckets. One number rather than one per
	 * track because a graduate's status no longer says which track they were on (the spec's
	 * per-track finished count has no source in a row). Mentors are counted by distinct name:
	 * a roster row carries no mentor record ID.
	 *
	 * @return array `tracks`, `finished`, `rows`, `quiet`, `read`, `semester`.
	 */
	public static function programs() {
		$tracked  = WPCPM_Mentors_Sync::tracked_statuses();
		$active   = isset( $tracked['active'] ) ? (array) $tracked['active'] : array();
		$semester = WPCPM_Cohort::current();
		$window   = WPCPM_Cohort::range( $semester );
		$tracks   = array();
		$finished = 0;
		$rows     = array();
		$quiet    = 0;
		$read     = 0;

		foreach ( self::TRACKS as $track ) {
			$tracks[ $track ] = array(
				'in_progress' => 0,
				'signed_up'   => 0,
			);
		}

		foreach ( WPCPM_Institutions_Index::rows() as $record => $institution ) {
			$record   = (string) $record;
			$envelope = WPCPM_Roster_Index::read( $record );
			$roster   = isset( $envelope['rows'] ) && is_array( $envelope['rows'] ) ? $envelope['rows'] : array();

			if ( empty( $roster ) ) {
				continue;
			}

			// The oldest non-zero read, not the newest: "Read from the program records on..." is
			// supposed to answer how stale the numbers on this page might be, and the newest
			// roster hides exactly the institution that answer is about - the one nobody has
			// synced in a while.
			$this_read = isset( $envelope['read'] ) ? (int) $envelope['read'] : 0;

			if ( $this_read > 0 && ( 0 === $read || $this_read < $read ) ) {
				$read = $this_read;
			}

			$in_progress = 0;
			$by_status   = array();
			$mentors     = array();
			$waiting     = 0;
			$earliest    = '';
			$latest      = '';

			foreach ( $roster as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

				if ( in_array( $status, WPCPM_Roster_Index::NEVER_SHOWN, true ) ) {
					continue;
				}

				$track = WPCPM_Program::track( $status );
				$end   = self::day( isset( $row['end'] ) ? $row['end'] : '' );

				if ( '' !== $track && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) === $semester ) {
					++$tracks[ $track ]['signed_up'];
				}

				if ( in_array( $status, $active, true ) ) {
					++$in_progress;

					$label               = WPCPM_Program::label( $status );
					$by_status[ $label ] = ( isset( $by_status[ $label ] ) ? $by_status[ $label ] : 0 ) + 1;

					if ( '' !== $track ) {
						++$tracks[ $track ]['in_progress'];
					}

					if ( empty( $row['reports'] ) ) {
						++$waiting;
					}

					$mentor = isset( $row['mentor_name'] ) ? trim( (string) $row['mentor_name'] ) : '';

					if ( '' !== $mentor ) {
						$mentors[ $mentor ] = true;
					}

					if ( '' !== $end ) {
						if ( '' === $earliest || $end < $earliest ) {
							$earliest = $end;
						}

						if ( $end > $latest ) {
							$latest = $end;
						}
					}
				} elseif ( 'Graduate' === $status && '' !== $end && $end >= $window['from'] && $end <= $window['to'] ) {
					// Graduate only, not every `past_statuses` entry: WPCPM_Cohort::participation()'s
					// private bucket() buckets 'Graduate' as graduated and 'Dropped out' as withdrawn,
					// and this count has to agree with that rule rather than inventing its own - a
					// student who left is not "finished", because nothing about the program was.
					++$finished;
				}
			}

			if ( 0 === $in_progress ) {
				++$quiet;
				continue;
			}

			$reports = WPCPM_Semester_Report::reports_of( $record );
			$report  = 'none';

			if ( ! empty( $reports ) ) {
				$first  = reset( $reports );
				$report = $first instanceof WP_Post ? WPCPM_Semester_Report::state( $first ) : 'none';
			}

			$summary = WPCPM_Institution_Agreement::summary( $record );

			$rows[] = array(
				'record'      => $record,
				'name'        => self::institution_name( $record ),
				'in_progress' => $in_progress,
				'by_status'   => $by_status,
				'waiting'     => $waiting,
				'mentors'     => count( $mentors ),
				'earliest'    => $earliest,
				'latest'      => $latest,
				'agreement'   => isset( $summary['state'] ) ? (string) $summary['state'] : 'none',
				'report'      => $report,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return array(
			'tracks'   => $tracks,
			'finished' => $finished,
			'rows'     => $rows,
			'quiet'    => $quiet,
			'read'     => $read,
			'semester' => $semester,
		);
	}

	/**
	 * The syncs, the private storage probe, the last mail and the invitation run.
	 *
	 * @return array
	 */
	public static function health() {
		$syncs = array(
			'students'     => array(
				'label'    => __( 'Students', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Students_Sync::progress(),
				'last'     => (int) get_option( WPCPM_Students_Sync::OPT_LAST, 0 ),
				'next'     => (int) wp_next_scheduled( WPCPM_Students_Sync::CRON_AUTO ),
				'screen'   => admin_url( 'admin.php?page=wpcpm-students' ),
			),
			'mentors'      => array(
				'label'    => __( 'Mentors', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Mentors_Sync::progress(),
				'last'     => (int) get_option( WPCPM_Mentors_Sync::OPT_LAST, 0 ),
				'next'     => (int) wp_next_scheduled( WPCPM_Mentors_Sync::CRON_DAILY ),
				'screen'   => admin_url( 'admin.php?page=wpcpm-mentors' ),
			),
			'institutions' => array(
				'label'    => __( 'Institutions', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Institutions_Sync::progress(),
				'last'     => (int) WPCPM_Institutions_Sync::last_read(),
				'next'     => (int) wp_next_scheduled( WPCPM_Institutions_Sync::CRON_DAILY ),
				'screen'   => admin_url( 'admin.php?page=wpcpm-institutions' ),
			),
		);

		$probe = WPCPM_Private_Files::probe_result();
		$log   = WPCPM_Mail::log();

		return array(
			'syncs'   => $syncs,
			'probe'   => array(
				'verdict' => is_array( $probe ) ? WPCPM_Private_Files::verdict( $probe ) : 'unknown',
				'time'    => is_array( $probe ) && isset( $probe['time'] ) ? (int) $probe['time'] : 0,
			),
			'mail'    => isset( $log[0] ) && is_array( $log[0] ) ? $log[0] : array(),
			'invites' => array(
				'run'    => WPCPM_Mail::run(),
				'queued' => (int) WPCPM_Mail::queued(),
			),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * Drawing
	 * --------------------------------------------------------------------
	 */

	/**
	 * The attention strip: eight counts, each an anchor to its card. A zero is muted, not
	 * hidden, so the strip always has the same shape and a manager learns where to look.
	 *
	 * @param array $counts What `counts()` returned.
	 */
	public static function render_strip( array $counts ) {
		printf( '<section class="wpcpm-administrator__card wpcpm-attention" id="wpcpm-attention" aria-label="%s">', esc_attr__( 'Needs attention', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-attention__tiles">';

		foreach ( $counts as $tile ) {
			printf(
				'<li class="wpcpm-attention__tile%1$s"><a class="wpcpm-attention__link" href="#wpcpm-%2$s"><span class="wpcpm-attention__n">%3$s</span><span class="wpcpm-attention__l">%4$s</span></a></li>',
				0 === (int) $tile['n'] ? ' wpcpm-attention__tile--zero' : '',
				esc_attr( $tile['card'] ),
				esc_html( number_format_i18n( (int) $tile['n'] ) ),
				esc_html( $tile['label'] )
			);
		}

		echo '</ul></section>';
	}

	/**
	 * Institution applications: the open ones with the six decisions, the closed ones folded.
	 *
	 * @param array $data `open`, `open_total`, `closed`, `closed_total`.
	 */
	public static function render_applications( array $data ) {
		$open         = isset( $data['open'] ) ? (array) $data['open'] : array();
		$open_total   = isset( $data['open_total'] ) ? (int) $data['open_total'] : count( $open );
		$closed       = isset( $data['closed'] ) ? (array) $data['closed'] : array();
		$closed_total = isset( $data['closed_total'] ) ? (int) $data['closed_total'] : count( $closed );
		$module       = class_exists( 'WPCPM_Modules' ) ? WPCPM_Modules::get( 'institutions' ) : null;

		self::card_open( 'applications', __( 'Institution applications', 'wpcredits-program-manager' ), $open_total );

		if ( empty( $open ) ) {
			self::empty_line( __( 'No application is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $open as $post ) {
			if ( $post instanceof WP_Post ) {
				self::render_application( $post, $module );
			}
		}

		if ( $open_total > count( $open ) ) {
			self::render_more_line( $open_total - count( $open ) );
		}

		if ( ! empty( $closed ) ) {
			printf(
				'<details class="wpcpm-administrator__closed"><summary>%s</summary>',
				esc_html(
					sprintf(
						/* translators: %s: a number of applications. */
						_n( '%s rejected or spam application', '%s rejected or spam applications', count( $closed ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $closed ) )
					)
				)
			);

			foreach ( $closed as $post ) {
				if ( $post instanceof WP_Post ) {
					self::render_application( $post, $module );
				}
			}

			if ( $closed_total > count( $closed ) ) {
				self::render_more_line( $closed_total - count( $closed ) );
			}

			echo '</details>';
		}

		self::card_close();
	}

	/**
	 * "N more are waiting on the Institutions screen", linked there - printed after a list
	 * this page cut at self::LIMIT, the number WPCPM_Institutions::QUEUE_MAX also caps the
	 * wp-admin queue at, so neither screen ever claims to be showing more than it draws.
	 *
	 * @param int $n How many were left off the list.
	 */
	private static function render_more_line( $n ) {
		printf(
			'<p class="wpcpm-administrator__more"><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=wpcpm-institutions' ) ),
			esc_html(
				sprintf(
					/* translators: %s: a number of applications. */
					_n( '%s more is waiting on the Institutions screen.', '%s more are waiting on the Institutions screen.', $n, 'wpcredits-program-manager' ),
					number_format_i18n( $n )
				)
			)
		);
	}

	/**
	 * One application: its facts, its answers behind a disclosure, and the module's decisions.
	 *
	 * @param WP_Post     $post   The application.
	 * @param object|null $module The Institutions module, which owns the answers and the forms.
	 */
	private static function render_application( WP_Post $post, $module ) {
		$state   = WPCPM_Institutions::application_state( $post );
		$country = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY, true );
		$name    = trim( (string) $post->post_title );
		$at      = (int) get_post_time( 'U', true, $post );
		$manager = WPCPM_Countries::contact_of( $country );

		echo '<article class="wpcpm-administrator__item wpcpm-application">';
		printf(
			'<h4 class="wpcpm-administrator__item-title">%1$s <code class="wpcpm-administrator__ref">%2$s</code></h4>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( WPCPM_Institutions::application_reference( $post ) )
		);
		echo '<p class="wpcpm-administrator__facts">';
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( ucfirst( $state ) ) );
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( WPCPM_Institutions::country_name( $country, (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ) ) ) );

		if ( '' !== $manager ) {
			printf(
				'<span class="wpcpm-administrator__fact">%s</span>',
				esc_html(
					sprintf(
						/* translators: %s: a program manager's name or address. */
						__( 'Routed to %s', 'wpcredits-program-manager' ),
						$manager
					)
				)
			);
		}

		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( self::received( $at ) ) );
		echo '</p>';

		printf( '<details class="wpcpm-administrator__answers"><summary>%s</summary>', esc_html__( 'Read the application', 'wpcredits-program-manager' ) );

		if ( is_object( $module ) && method_exists( $module, 'render_application_answers' ) ) {
			$module->render_application_answers( $post );
		}

		echo '</details>';

		if ( is_object( $module ) && method_exists( $module, 'render_application_actions' ) ) {
			echo '<div class="wpcpm-administrator__actions">';
			$module->render_application_actions( $post, $state, WPCPM_Return::DASHBOARD );
			echo '</div>';
		}

		echo '</article>';
	}

	/**
	 * Collaboration Agreements: awaiting review with the panel's review block, then the
	 * returned and the revoked, folded.
	 *
	 * @param array $data `awaiting`, `returned`, `revoked`, `overdue`.
	 */
	public static function render_agreements( array $data ) {
		$awaiting = isset( $data['awaiting'] ) ? (array) $data['awaiting'] : array();
		$returned = isset( $data['returned'] ) ? (array) $data['returned'] : array();
		$revoked  = isset( $data['revoked'] ) ? (array) $data['revoked'] : array();

		self::card_open( 'agreements', __( 'Collaboration Agreements', 'wpcredits-program-manager' ), count( $awaiting ) );
		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Awaiting review', 'wpcredits-program-manager' ) );

		if ( empty( $awaiting ) ) {
			self::empty_line( __( 'No signed agreement is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $awaiting as $row ) {
			printf( '<article class="wpcpm-administrator__item wpcpm-agreement%s">', ! empty( $row['overdue'] ) ? ' wpcpm-administrator__item--overdue' : '' );
			printf( '<h4 class="wpcpm-administrator__item-title">%s</h4>', self::institution_link( $row['record'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
			echo '<p class="wpcpm-administrator__facts">';
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( self::uploaded( (int) $row['at'] ) ) );

			if ( ! empty( $row['overdue'] ) ) {
				printf( '<span class="wpcpm-administrator__fact wpcpm-administrator__fact--overdue">%s</span>', esc_html__( 'Overdue', 'wpcredits-program-manager' ) );
			}

			echo '</p>';
			WPCPM_Institution_Panel::render_review( (int) $row['id'] );
			echo '</article>';
		}

		self::render_agreement_list(
			$returned,
			/* translators: %s: a number of institutions. */
			__( '%s returned, waiting for a new upload', 'wpcredits-program-manager' ),
			/* translators: %s: a number of institutions. */
			__( '%s returned, waiting for new uploads', 'wpcredits-program-manager' ),
			false
		);
		self::render_agreement_list(
			$revoked,
			/* translators: %s: a number of institutions. */
			__( '%s revoked agreement', 'wpcredits-program-manager' ),
			/* translators: %s: a number of institutions. */
			__( '%s revoked agreements', 'wpcredits-program-manager' ),
			true
		);

		self::card_close();
	}

	/**
	 * A folded list of returned or revoked agreements; the revoked ones offer Reinstate.
	 *
	 * @param array  $rows      `id`, `record`, `at`, `note`.
	 * @param string $singular  The summary, singular, with %s for the count.
	 * @param string $plural    The summary, plural.
	 * @param bool   $reinstate Whether to draw the Reinstate form.
	 */
	private static function render_agreement_list( array $rows, $singular, $plural, $reinstate ) {
		if ( empty( $rows ) ) {
			return;
		}

		// The sentences are translated by the caller, so _n() would translate them twice;
		// the count picks the form here.
		printf( '<details class="wpcpm-administrator__closed"><summary>%s</summary>', esc_html( sprintf( 1 === count( $rows ) ? $singular : $plural, number_format_i18n( count( $rows ) ) ) ) );

		foreach ( $rows as $row ) {
			echo '<article class="wpcpm-administrator__item wpcpm-agreement">';
			printf( '<h4 class="wpcpm-administrator__item-title">%s</h4>', self::institution_link( $row['record'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
			printf( '<p class="wpcpm-administrator__facts"><span class="wpcpm-administrator__fact">%s</span></p>', esc_html( self::when( (int) $row['at'] ) ) );

			if ( '' !== $row['note'] ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $row['note'] ) );
			}

			if ( $reinstate ) {
				self::render_reinstate_form( (int) $row['id'] );
			}

			echo '</article>';
		}

		echo '</details>';
	}

	/**
	 * The Reinstate form, drawn here because nothing else draws one: the handler and its nonce
	 * exist in the agreement class, and the queue never offered the button.
	 *
	 * @param int $post_id The revoked document.
	 */
	private static function render_reinstate_form( $post_id ) {
		printf(
			'<form class="wpcpm-administrator__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Reinstating', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Institution_Agreement::ACTION_REINSTATE . '_' . (int) $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Agreement::ACTION_REINSTATE ) );
		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );
		printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Reinstate', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Semester reports: drafts to review, cohorts due for drafting, approved this semester.
	 *
	 * No approve button here: approval belongs in the editor, where the manager has read the
	 * document. Every link opens the Institution Dashboard as that institution.
	 *
	 * @param array $data `queue`, `due`, `approved`.
	 */
	public static function render_reports( array $data ) {
		$queue    = isset( $data['queue'] ) ? (array) $data['queue'] : array();
		$due      = isset( $data['due'] ) ? (array) $data['due'] : array();
		$approved = isset( $data['approved'] ) ? (array) $data['approved'] : array();

		self::card_open( 'reports', __( 'Semester reports', 'wpcredits-program-manager' ), count( $queue ) + count( $due ) );

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'To review', 'wpcredits-program-manager' ) );

		if ( empty( $queue ) ) {
			self::empty_line( __( 'No draft is waiting for review.', 'wpcredits-program-manager' ) );
		} else {
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Semester', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Drafted', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Still in progress', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Age', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $queue as $row ) {
				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td class="wpcpm-admin-table__n">%4$s</td><td>%5$s</td><td><a class="wpcpm-button wpcpm-button--secondary" href="%6$s">%7$s</a></td></tr>',
					self::institution_link( $row['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( WPCPM_Cohort::label( $row['cohort'] ) ),
					esc_html( self::drafted( (int) $row['generated'], (string) $row['origin'] ) ),
					esc_html( number_format_i18n( (int) $row['in_progress'] ) ),
					esc_html( sprintf( /* translators: %s: a number of days. */ _n( '%s day', '%s days', (int) $row['age_days'], 'wpcredits-program-manager' ), number_format_i18n( (int) $row['age_days'] ) ) ),
					esc_url( self::report_link( $row['institution'], $row['cohort'] ) ),
					esc_html__( 'Review', 'wpcredits-program-manager' )
				);
			}

			echo '</tbody></table>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Due for drafting', 'wpcredits-program-manager' ) );

		if ( empty( $due ) ) {
			self::empty_line( __( 'Nothing is waiting: every finished semester has a report, or is not finished yet.', 'wpcredits-program-manager' ) );
		} else {
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Semester', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Window ended', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Still in progress', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $due as $pair ) {
				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td class="wpcpm-admin-table__n">%4$s</td><td>',
					self::institution_link( $pair['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( WPCPM_Cohort::label( $pair['cohort'] ) ),
					esc_html( $pair['window_end'] ),
					esc_html( number_format_i18n( (int) $pair['in_progress'] ) )
				);
				// The dashboard return: a refusal (the semester already has a report, or the
				// ceiling for today is spent) comes back here rather than to the wp-admin
				// default; a successful draft still opens the new draft in the editor either
				// way (final review, Important 2).
				WPCPM_Semester_Report_Screen::render_draft_form( $pair['institution'], $pair['cohort'], '', WPCPM_Return::DASHBOARD );
				echo '</td></tr>';
			}

			echo '</tbody></table>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Approved this semester', 'wpcredits-program-manager' ) );

		if ( empty( $approved ) ) {
			self::empty_line( __( 'No report has been approved this semester yet.', 'wpcredits-program-manager' ) );
		} else {
			echo '<ul class="wpcpm-administrator__list">';

			foreach ( $approved as $row ) {
				$by = ! empty( $row['approved_by'] ) ? get_user_by( 'id', (int) $row['approved_by'] ) : false;

				printf(
					'<li>%1$s, %2$s: %3$s <a href="%4$s">%5$s</a></li>',
					self::institution_link( $row['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( WPCPM_Cohort::label( $row['cohort'] ) ),
					esc_html(
						$by instanceof WP_User
							? sprintf( /* translators: 1: a date, 2: a program manager's name. */ __( 'approved on %1$s by %2$s', 'wpcredits-program-manager' ), self::when( (int) $row['approved_at'] ), $by->display_name )
							: sprintf( /* translators: %s: a date. */ __( 'approved on %s', 'wpcredits-program-manager' ), self::when( (int) $row['approved_at'] ) )
					),
					esc_url( self::report_link( $row['institution'], $row['cohort'] ) ),
					esc_html__( 'View', 'wpcredits-program-manager' )
				);
			}

			echo '</ul>';
		}

		self::card_close();
	}

	/**
	 * Mentor requests: the open ones with the request class's two decisions, the closed folded.
	 *
	 * @param array $data `open`, `closed` (facts rows), `overdue`.
	 */
	public static function render_requests( array $data ) {
		$open   = isset( $data['open'] ) ? (array) $data['open'] : array();
		$closed = isset( $data['closed'] ) ? (array) $data['closed'] : array();

		self::card_open( 'requests', __( 'Mentor requests', 'wpcredits-program-manager' ), count( $open ) );

		if ( empty( $open ) ) {
			self::empty_line( __( 'No request is open.', 'wpcredits-program-manager' ) );
		}

		foreach ( $open as $facts ) {
			printf( '<article class="wpcpm-administrator__item wpcpm-request%s">', ! empty( $facts['overdue'] ) ? ' wpcpm-administrator__item--overdue' : '' );
			printf( '<h4 class="wpcpm-administrator__item-title">%1$s <span class="wpcpm-administrator__kind">%2$s</span></h4>', self::institution_link( $facts['institution'] ), esc_html( $facts['kind_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
			echo '<p class="wpcpm-administrator__facts">';
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( /* translators: %s: how long ago. */ __( 'Opened %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $facts['at'], time() ) ) ) );

			if ( ! empty( $facts['overdue'] ) ) {
				printf( '<span class="wpcpm-administrator__fact wpcpm-administrator__fact--overdue">%s</span>', esc_html__( 'Overdue', 'wpcredits-program-manager' ) );
			}

			echo '</p>';

			if ( '' !== (string) $facts['note'] ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $facts['note'] ) );
			}

			WPCPM_Institution_Request::render_decisions( (int) $facts['id'], WPCPM_Return::DASHBOARD );
			echo '</article>';
		}

		if ( ! empty( $closed ) ) {
			printf( '<details class="wpcpm-administrator__closed"><summary>%s</summary><ul class="wpcpm-administrator__list">', esc_html__( 'Recently closed', 'wpcredits-program-manager' ) );

			foreach ( $closed as $facts ) {
				printf(
					'<li>%1$s, %2$s: %3$s, %4$s</li>',
					self::institution_link( $facts['institution'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( $facts['kind_label'] ),
					esc_html( WPCPM_Institution_Request::STATE_DONE === $facts['state'] ? __( 'Handled', 'wpcredits-program-manager' ) : __( 'Declined', 'wpcredits-program-manager' ) ),
					esc_html( self::when( (int) $facts['at'] ) )
				);
			}

			echo '</ul></details>';
		}

		self::card_close();
	}

	/**
	 * The programs running: a tile per track and one for the finished, then the institutions.
	 *
	 * @param array $programs What `programs()` returned.
	 */
	public static function render_programs( array $programs ) {
		$rows = isset( $programs['rows'] ) ? (array) $programs['rows'] : array();

		self::card_open( 'programs', __( 'Programs running', 'wpcredits-program-manager' ), count( $rows ) );

		$names = array(
			'150h' => WPCPM_Program::label( WPCPM_Program::STATUS_150H ),
			'50h'  => WPCPM_Program::label( WPCPM_Program::STATUS_50H ),
			'dev'  => WPCPM_Program::label( WPCPM_Program::STATUS_DEV ),
		);

		echo '<ul class="wpcpm-programs__tiles">';

		foreach ( self::TRACKS as $track ) {
			$tile = isset( $programs['tracks'][ $track ] ) ? $programs['tracks'][ $track ] : array(
				'in_progress' => 0,
				'signed_up'   => 0,
			);

			printf(
				'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
				esc_html( $names[ $track ] ),
				esc_html( number_format_i18n( (int) $tile['in_progress'] ) ),
				esc_html( sprintf( /* translators: %s: a number of students. */ __( 'in progress, %s signed up this semester', 'wpcredits-program-manager' ), number_format_i18n( (int) $tile['signed_up'] ) ) )
			);
		}

		printf(
			'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
			esc_html__( 'Finished this semester', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( isset( $programs['finished'] ) ? (int) $programs['finished'] : 0 ) ),
			esc_html__( 'across every track', 'wpcredits-program-manager' )
		);
		echo '</ul>';

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No institution has a student in progress.', 'wpcredits-program-manager' ) );
		} else {
			echo '<table class="wpcpm-admin-table wpcpm-programs__table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'In progress', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Mentors', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Waiting for a mentor', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'End dates', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Agreement', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Report', 'wpcredits-program-manager' ) );
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$breakdown = array();

				foreach ( $row['by_status'] as $label => $n ) {
					$breakdown[] = number_format_i18n( (int) $n ) . ' ' . $label;
				}

				printf(
					'<tr><td>%1$s</td><td class="wpcpm-admin-table__n">%2$s<span class="wpcpm-admin-table__breakdown">%3$s</span></td><td class="wpcpm-admin-table__n">%4$s</td><td class="wpcpm-admin-table__n">%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td></tr>',
					self::institution_link( $row['record'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- institution_link() escapes the name and the href; escaping it again would print its own markup.
					esc_html( number_format_i18n( (int) $row['in_progress'] ) ),
					esc_html( implode( ', ', $breakdown ) ),
					esc_html( number_format_i18n( (int) $row['mentors'] ) ),
					esc_html( number_format_i18n( (int) $row['waiting'] ) ),
					esc_html(
						'' === $row['earliest']
							? __( 'no end date', 'wpcredits-program-manager' )
							: (
								$row['earliest'] === $row['latest']
									? $row['earliest']
									: sprintf( /* translators: 1: earliest end date, 2: latest end date. */ __( '%1$s to %2$s', 'wpcredits-program-manager' ), $row['earliest'], $row['latest'] )
							)
					),
					esc_html( str_replace( '_', ' ', (string) $row['agreement'] ) ),
					esc_html( (string) $row['report'] )
				);
			}

			echo '</tbody></table>';
		}

		if ( ! empty( $programs['quiet'] ) ) {
			printf(
				'<p class="wpcpm-administrator__quiet">%s</p>',
				esc_html( sprintf( /* translators: %s: a number of institutions. */ _n( '%s more institution with no student in progress.', '%s more institutions with no student in progress.', (int) $programs['quiet'], 'wpcredits-program-manager' ), number_format_i18n( (int) $programs['quiet'] ) ) )
			);
		}

		printf(
			'<p class="wpcpm-administrator__read">%s</p>',
			esc_html(
				empty( $programs['read'] )
					? __( 'These students have not been read from the program records yet.', 'wpcredits-program-manager' )
					: sprintf( /* translators: 1: date and time, 2: how long ago. */ __( 'Read from the program records on %1$s (%2$s ago).', 'wpcredits-program-manager' ), self::when( (int) $programs['read'] ), human_time_diff( (int) $programs['read'], time() ) )
			)
		);

		self::card_close();
	}

	/**
	 * Syncs and health: read-only, every row linking out to the screen that owns it.
	 *
	 * @param array     $health What `health()` returned.
	 * @param WP_User[] $locked Accounts the roster's refusal ceiling locked today.
	 */
	public static function render_health( array $health, array $locked ) {
		$syncs = isset( $health['syncs'] ) ? (array) $health['syncs'] : array();

		self::card_open( 'health', __( 'Syncs and health', 'wpcredits-program-manager' ), count( $locked ) );

		echo '<table class="wpcpm-admin-table"><thead><tr>';
		printf( '<th scope="col">%s</th>', esc_html__( 'Sync', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'State', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Last run', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Next run', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Last error', 'wpcredits-program-manager' ) );
		echo '<th scope="col"></th></tr></thead><tbody>';

		foreach ( $syncs as $sync ) {
			$progress = isset( $sync['progress'] ) && is_array( $sync['progress'] ) ? $sync['progress'] : array();
			$state    = ! empty( $progress['running'] )
				? sprintf( /* translators: %s: what the sync is doing. */ __( 'Running: %s', 'wpcredits-program-manager' ), isset( $progress['label'] ) ? (string) $progress['label'] : '' )
				: __( 'Idle', 'wpcredits-program-manager' );

			printf(
				'<tr class="wpcpm-health__sync"><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td><a href="%6$s">%7$s</a></td></tr>',
				esc_html( $sync['label'] ),
				esc_html( $state ),
				esc_html( empty( $sync['last'] ) ? __( 'never', 'wpcredits-program-manager' ) : self::when( (int) $sync['last'] ) ),
				esc_html( empty( $sync['next'] ) ? __( 'not scheduled', 'wpcredits-program-manager' ) : self::when( (int) $sync['next'] ) ),
				esc_html( isset( $progress['error'] ) ? (string) $progress['error'] : '' ),
				esc_url( $sync['screen'] ),
				esc_html__( 'Open', 'wpcredits-program-manager' )
			);
		}

		echo '</tbody></table>';

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Locked accounts', 'wpcredits-program-manager' ) );

		if ( empty( $locked ) ) {
			self::empty_line( __( 'No account is locked today.', 'wpcredits-program-manager' ) );
		} else {
			$names = array();

			foreach ( $locked as $user ) {
				if ( $user instanceof WP_User ) {
					$names[] = $user->display_name . ' (' . $user->user_login . ')';
				}
			}

			printf(
				'<p class="wpcpm-administrator__note">%1$s %2$s</p>',
				esc_html( implode( ', ', $names ) ),
				esc_html__( 'Each was refused more claims than the daily ceiling allows; the lock lifts by itself tomorrow.', 'wpcredits-program-manager' )
			);
		}

		$probe   = isset( $health['probe'] ) ? (array) $health['probe'] : array(
			'verdict' => 'unknown',
			'time'    => 0,
		);
		$mail    = isset( $health['mail'] ) ? (array) $health['mail'] : array();
		$invites = isset( $health['invites'] ) ? (array) $health['invites'] : array(
			'run'    => array(),
			'queued' => 0,
		);

		echo '<ul class="wpcpm-administrator__list">';
		printf(
			'<li>%s</li>',
			esc_html( sprintf( /* translators: 1: the verdict, 2: a date. */ __( 'Private storage: %1$s (probed %2$s).', 'wpcredits-program-manager' ), $probe['verdict'], empty( $probe['time'] ) ? __( 'never', 'wpcredits-program-manager' ) : self::when( (int) $probe['time'] ) ) )
		);
		printf(
			'<li>%s</li>',
			esc_html(
				empty( $mail )
					? __( 'No mail has been sent yet.', 'wpcredits-program-manager' )
					: sprintf( /* translators: 1: a mail context, 2: a masked address, 3: a date, 4: sent or failed. */ __( 'Last mail: %1$s to %2$s on %3$s, %4$s.', 'wpcredits-program-manager' ), isset( $mail['context'] ) ? $mail['context'] : '', isset( $mail['to'] ) ? $mail['to'] : '', self::when( isset( $mail['time'] ) ? (int) $mail['time'] : 0 ), ! empty( $mail['sent'] ) ? __( 'sent', 'wpcredits-program-manager' ) : __( 'failed', 'wpcredits-program-manager' ) )
			)
		);

		// Only while the run is still going: run() also answers the last run to finish, and its
		// 'finished' is a non-zero timestamp then - printing "N of M sent" for a run that is
		// over reads as a live count of something that stopped moving days ago.
		if ( ! empty( $invites['run'] ) && isset( $invites['run']['total'] ) && empty( $invites['run']['finished'] ) ) {
			$total = (int) $invites['run']['total'];
			$sent  = max( 0, $total - (int) $invites['queued'] );

			printf(
				'<li>%s</li>',
				esc_html( sprintf( /* translators: 1: messages sent, 2: messages in the run. */ __( 'Invitations: %1$s of %2$s sent.', 'wpcredits-program-manager' ), number_format_i18n( $sent ), number_format_i18n( $total ) ) )
			);
		}

		echo '</ul>';

		self::card_close();
	}

	/*
	 * --------------------------------------------------------------------
	 * The small pieces
	 * --------------------------------------------------------------------
	 */

	/**
	 * Open one card: the canonical disclosure, open when there is something in it.
	 *
	 * The extra class comes before the two shared ones so the attribute still ends in
	 * `wpcpm-group__disclosure`, which is what the suites and the theme's rules key on.
	 *
	 * @param string $id    The anchor, one of WPCPM_Return::ANCHORS.
	 * @param string $title The heading.
	 * @param int    $count What the badge says; open when above zero.
	 */
	private static function card_open( $id, $title, $count ) {
		printf( '<section class="wpcpm-administrator__card" id="wpcpm-%s">', esc_attr( $id ) );
		printf( '<details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure"%s>', (int) $count > 0 ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html( $title ),
			esc_html( number_format_i18n( (int) $count ) )
		);
		echo '<div class="wpcpm-group__body">';
	}

	/** Close the card `card_open()` opened. */
	private static function card_close() {
		echo '</div></details></section>';
	}

	/**
	 * An empty half under a heading reads as something that failed to load, so it says so.
	 *
	 * @param string $text The sentence.
	 */
	private static function empty_line( $text ) {
		printf( '<p class="wpcpm-administrator__empty">%s</p>', esc_html( $text ) );
	}

	/**
	 * Rows for a folded agreement list.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array[] `id`, `record`, `at`, `note`.
	 */
	private static function agreement_rows( array $ids ) {
		$rows = array();

		foreach ( $ids as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$rows[] = array(
				'id'     => (int) $post->ID,
				'record' => (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
				'at'     => (int) get_post_modified_time( 'U', true, $post ),
				'note'   => trim( (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_NOTE, true ) ),
			);
		}

		return $rows;
	}

	/**
	 * The institution's name from the index, trimmed; the record ID when the index lost it.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function institution_name( $record ) {
		$row  = WPCPM_Institutions_Index::row( $record );
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : (string) $record;
	}

	/**
	 * The institution's name, linked to its dashboard through the switcher argument.
	 *
	 * Escaped here: every caller prints it into markup as is.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function institution_link( $record ) {
		$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? (string) WPCPM_Institutions_Dashboard::page_url() : '';
		$name = esc_html( self::institution_name( $record ) );

		if ( '' === $page || '' === (string) $record ) {
			return $name;
		}

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $page ) ), $name );
	}

	/**
	 * The report editor on the Institution Dashboard, as that institution.
	 *
	 * @param string $record Institutions record ID.
	 * @param string $cohort Cohort key.
	 * @return string
	 */
	private static function report_link( $record, $cohort ) {
		$url = (string) WPCPM_Semester_Report_Screen::report_url( $cohort );

		return '' === $url ? '' : add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $url );
	}

	/**
	 * A date-only string's leading `Y-m-d`, or '' for anything else.
	 *
	 * @param mixed $value A roster row's date.
	 * @return string
	 */
	private static function day( $value ) {
		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', trim( (string) $value ), $found ) ? $found[1] : '';
	}

	/**
	 * A date and time in the site's format.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function when( $timestamp ) {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
	}

	/**
	 * "Received <when> (<ago>)".
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function received( $timestamp ) {
		/* translators: 1: date and time, 2: how long ago. */
		return sprintf( __( 'Received %1$s (%2$s ago)', 'wpcredits-program-manager' ), self::when( $timestamp ), human_time_diff( (int) $timestamp, time() ) );
	}

	/**
	 * "Uploaded <when> (<ago>)".
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function uploaded( $timestamp ) {
		/* translators: 1: date and time, 2: how long ago. */
		return sprintf( __( 'Uploaded %1$s (%2$s ago)', 'wpcredits-program-manager' ), self::when( $timestamp ), human_time_diff( (int) $timestamp, time() ) );
	}

	/**
	 * "<when>, by the site" or "<when>, by a manager".
	 *
	 * @param int    $timestamp Unix time.
	 * @param string $origin    `auto` or `manager`.
	 * @return string
	 */
	private static function drafted( $timestamp, $origin ) {
		return self::when( $timestamp ) . ', ' . ( 'auto' === $origin ? __( 'by the site', 'wpcredits-program-manager' ) : __( 'by a manager', 'wpcredits-program-manager' ) );
	}
}
