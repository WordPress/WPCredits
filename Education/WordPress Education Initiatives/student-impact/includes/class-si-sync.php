<?php
/**
 * Sync engine: pulls Graduate students from Airtable, resolves their WordPress.org
 * profile + logged hours, scrapes each profile's contribution impact, ranks them,
 * and stores the top-N showcase.
 *
 * The sync is resumable: each invocation works within a wall-clock budget and, if
 * there is more to do, reschedules itself immediately. This keeps every request
 * short so it survives restrictive host timeouts (e.g. WordPress.com).
 *
 * @package Student_Impact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SI_Sync {

	/** Wall-clock budget (seconds) per invocation before yielding. */
	const TIME_BUDGET = 18;

	/** Profiles scraped per invocation cap (belt-and-suspenders with the time budget). */
	const BATCH_CAP = 40;

	/** HTTP timeout per profile fetch. */
	const HTTP_TIMEOUT = 8;

	/**
	 * Composite ranking weights. Impact and contributions are the dominant
	 * criteria; hours is only a minor factor (a gentle tiebreaker), never the
	 * top criterion. Weights should sum to 1.0.
	 */
	const RANK_W_IMPACT  = 0.45;
	const RANK_W_CONTRIB = 0.45;
	const RANK_W_HOURS   = 0.10;

	/**
	 * Kick off a fresh sync (called by the "Sync now" button).
	 * Resets state and schedules an immediate background run.
	 */
	public static function start() {
		update_option(
			SI_OPT_STATE,
			array(
				'phase'      => 'fetch',
				'candidates' => array(),
				'cursor'     => 0,
				'results'    => array(),
				'teams'      => array(),
				'started'    => time(),
			),
			false
		);
		delete_option( SI_OPT_LASTERR );
		self::schedule_next();
	}

	/**
	 * Schedule the next sync step ASAP and nudge WP-Cron to run it.
	 */
	private static function schedule_next() {
		if ( ! wp_next_scheduled( SI_CRON_RUN ) ) {
			wp_schedule_single_event( time(), SI_CRON_RUN );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * True while a sync is in progress.
	 *
	 * @return bool
	 */
	public static function is_running() {
		$state = get_option( SI_OPT_STATE, array() );
		return is_array( $state ) && ! empty( $state['phase'] ) && ! in_array( $state['phase'], array( 'done', 'error' ), true );
	}

	/**
	 * Human-readable progress string for the admin screen.
	 *
	 * @return string
	 */
	public static function progress_label() {
		$state = get_option( SI_OPT_STATE, array() );
		if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
			return '';
		}
		switch ( $state['phase'] ) {
			case 'fetch':
				return __( 'Fetching graduates from Airtable…', 'student-impact' );
			case 'scrape':
				$total = count( $state['candidates'] );
				$done  = (int) $state['cursor'];
				/* translators: 1: profiles processed, 2: total profiles. */
				return sprintf( __( 'Reading WordPress.org profiles (%1$d / %2$d)…', 'student-impact' ), $done, $total );
			case 'rank':
				return __( 'Ranking students…', 'student-impact' );
			default:
				return '';
		}
	}

	/**
	 * One step of the sync state machine. Registered on both cron hooks.
	 */
	public static function run_step() {
		$settings = si_get_settings();
		if ( empty( $settings['airtable_pat'] ) ) {
			self::fail( __( 'No Airtable Personal Access Token configured.', 'student-impact' ) );
			return;
		}

		$state = get_option( SI_OPT_STATE, array() );
		if ( ! is_array( $state ) || empty( $state['phase'] ) || in_array( $state['phase'], array( 'done', 'error' ), true ) ) {
			// A scheduled (daily) run with no active state: start one.
			self::start();
			$state = get_option( SI_OPT_STATE, array() );
		}

		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$deadline = time() + self::TIME_BUDGET;

		try {
			if ( 'fetch' === $state['phase'] ) {
				$state = self::phase_fetch( $settings, $state );
				update_option( SI_OPT_STATE, $state, false );
			}

			if ( 'scrape' === $state['phase'] ) {
				$state = self::phase_scrape( $state, $deadline );
				update_option( SI_OPT_STATE, $state, false );
			}

			if ( 'rank' === $state['phase'] ) {
				self::phase_rank( $settings, $state );
				return; // Done.
			}

			// More work remains — continue in the background.
			if ( self::is_running() ) {
				self::schedule_next();
			}
		} catch ( Exception $e ) {
			self::fail( $e->getMessage() );
		}
	}

	/**
	 * Phase 1: pull graduates + reports + teams from Airtable, build the candidate list.
	 *
	 * @param array $settings Plugin settings.
	 * @param array $state    Current sync state.
	 * @return array Updated state.
	 * @throws Exception On API failure.
	 */
	private static function phase_fetch( $settings, $state ) {
		$grads = self::airtable_all(
			$settings,
			$settings['students_table'],
			array( 'Full Name', 'Email', 'Status' ),
			sprintf( '{Status}="%s"', str_replace( '"', '', $settings['status_value'] ) )
		);

		$reports = self::airtable_all(
			$settings,
			$settings['reports_table'],
			array( 'Email', 'WordPress Profile', 'Hours', 'Main Contribution Team' ),
			''
		);

		// Team id -> name map.
		$teams = array();
		$team_recs = self::airtable_all( $settings, $settings['teams_table'], array( 'Contribution teams or areas' ), '' );
		foreach ( $team_recs as $t ) {
			$teams[ $t['id'] ] = isset( $t['fields']['Contribution teams or areas'] ) ? $t['fields']['Contribution teams or areas'] : '';
		}

		// Index reports by normalized email.
		$rep_by_email = array();
		foreach ( $reports as $r ) {
			$email = self::norm_email( isset( $r['fields']['Email'] ) ? $r['fields']['Email'] : '' );
			if ( $email ) {
				$rep_by_email[ $email ][] = $r['fields'];
			}
		}

		$candidates = array();
		$grad_count = 0;    // Every graduate, whether or not they have a WP.org profile.
		$grad_hours = 0.0;  // Total logged hours across every graduate.
		foreach ( $grads as $g ) {
			$f     = $g['fields'];
			$email = self::norm_email( isset( $f['Email'] ) ? $f['Email'] : '' );
			$rs    = isset( $rep_by_email[ $email ] ) ? $rep_by_email[ $email ] : array();

			$wp    = '';
			$hours = 0.0;
			$team  = '';
			foreach ( $rs as $rf ) {
				if ( empty( $wp ) && ! empty( $rf['WordPress Profile'] ) ) {
					$wp = $rf['WordPress Profile'];
				}
				if ( isset( $rf['Hours'] ) && is_numeric( $rf['Hours'] ) ) {
					$hours = max( $hours, (float) $rf['Hours'] );
				}
				if ( empty( $team ) && ! empty( $rf['Main Contribution Team'] ) ) {
					$tid  = is_array( $rf['Main Contribution Team'] ) ? reset( $rf['Main Contribution Team'] ) : $rf['Main Contribution Team'];
					$team = isset( $teams[ $tid ] ) ? $teams[ $tid ] : '';
				}
			}

			$grad_count++;
			$grad_hours += $hours;

			$username = self::extract_username( $wp );
			if ( ! $username ) {
				continue; // No usable WordPress.org profile — skip from the showcase/scrape.
			}

			$candidates[] = array(
				'name'     => isset( $f['Full Name'] ) ? trim( $f['Full Name'] ) : $username,
				'username' => $username,
				'hours'    => (int) round( $hours ),
				'team'     => $team,
			);
		}

		$state['candidates'] = $candidates;
		$state['teams']      = $teams;
		$state['grad_count'] = $grad_count;
		$state['grad_hours'] = (int) round( $grad_hours );
		$state['cursor']     = 0;
		$state['results']    = array();
		$state['phase']      = $candidates ? 'scrape' : 'rank';
		return $state;
	}

	/**
	 * Phase 2: scrape WordPress.org profiles within the time budget.
	 *
	 * @param array $state    Current sync state.
	 * @param int   $deadline Unix time to stop by.
	 * @return array Updated state.
	 */
	private static function phase_scrape( $state, $deadline ) {
		$total     = count( $state['candidates'] );
		$processed = 0;

		while ( $state['cursor'] < $total && time() < $deadline && $processed < self::BATCH_CAP ) {
			$cand = $state['candidates'][ $state['cursor'] ];
			$metrics = self::scrape_profile( $cand['username'] );
			$state['results'][] = array_merge( $cand, $metrics );
			$state['cursor']++;
			$processed++;
		}

		if ( $state['cursor'] >= $total ) {
			$state['phase'] = 'rank';
		}
		return $state;
	}

	/**
	 * Phase 3: compute composite ranking and publish the top-N showcase.
	 *
	 * @param array $settings Plugin settings.
	 * @param array $state    Current sync state.
	 */
	private static function phase_rank( $settings, $state ) {
		$rows = $state['results'];

		$max_s = 0;
		$max_c = 0;
		$max_h = 0;
		foreach ( $rows as $r ) {
			$max_s = max( $max_s, $r['score'] );
			$max_c = max( $max_c, $r['contributions'] );
			$max_h = max( $max_h, $r['hours'] );
		}
		$max_s = $max_s ?: 1;
		$max_c = $max_c ?: 1;
		$max_h = $max_h ?: 1;

		foreach ( $rows as &$r ) {
			$r['composite'] = round(
				100 * (
					self::RANK_W_IMPACT * ( $r['score'] / $max_s )
					+ self::RANK_W_CONTRIB * ( $r['contributions'] / $max_c )
					+ self::RANK_W_HOURS * ( $r['hours'] / $max_h )
				),
				1
			);
		}
		unset( $r );

		// Sort by composite, then impact, then contributions — hours never leads.
		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['composite'] !== $b['composite'] ) {
					return $b['composite'] <=> $a['composite'];
				}
				if ( $a['score'] !== $b['score'] ) {
					return $b['score'] <=> $a['score'];
				}
				return $b['contributions'] <=> $a['contributions'];
			}
		);

		$count = max( 1, (int) $settings['count'] );
		$top   = array_slice( $rows, 0, $count );

		$data = array();
		$rank = 1;
		foreach ( $top as $r ) {
			$data[] = array(
				'rank'          => $rank++,
				'name'          => $r['name'],
				'username'      => $r['username'],
				'url'           => 'https://profiles.wordpress.org/' . $r['username'] . '/',
				'avatar'        => $r['avatar'],
				'team'          => $r['team'],
				'score'         => $r['score'],
				'contributions' => $r['contributions'],
				'high'          => $r['high'],
				'recent90'      => $r['recent90'],
				'hours'         => $r['hours'],
				'composite'     => $r['composite'],
			);
		}

		update_option( SI_OPT_DATA, $data );

		// Aggregate totals across ALL graduates (not just the top-N showcase).
		$sum_contrib = 0;
		$sum_impact  = 0;
		$sum_high    = 0;
		$active      = 0;
		foreach ( $rows as $r ) {
			$sum_contrib += (int) $r['contributions'];
			$sum_impact  += (int) $r['score'];
			$sum_high    += (int) $r['high'];
			if ( (int) $r['recent90'] > 0 ) {
				$active++;
			}
		}
		update_option(
			SI_OPT_TOTALS,
			array(
				'students'      => isset( $state['grad_count'] ) ? (int) $state['grad_count'] : count( $rows ),
				'profiles'      => count( $rows ),
				'hours'         => isset( $state['grad_hours'] ) ? (int) $state['grad_hours'] : 0,
				'contributions' => $sum_contrib,
				'impact'        => $sum_impact,
				'high'          => $sum_high,
				'active'        => $active,
			)
		);

		update_option( SI_OPT_LASTSYNC, time() );
		delete_option( SI_OPT_LASTERR );

		$state['phase'] = 'done';
		update_option( SI_OPT_STATE, $state, false );
	}

	/**
	 * Scrape a single WordPress.org profile for its impact metrics + avatar.
	 *
	 * @param string $username WordPress.org username.
	 * @return array Metrics.
	 */
	private static function scrape_profile( $username ) {
		$out = array(
			'score'         => 0,
			'contributions' => 0,
			'high'          => 0,
			'recent90'      => 0,
			'avatar'        => '',
		);

		$resp = wp_remote_get(
			'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/',
			array(
				'timeout'    => self::HTTP_TIMEOUT,
				'user-agent' => 'Student-Impact/' . SI_VERSION . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return $out;
		}
		$html = wp_remote_retrieve_body( $resp );

		// Avatar (gravatar hash).
		if ( preg_match( '#gravatar\.com/avatar/([a-f0-9]+)#', $html, $m ) ) {
			$out['avatar'] = 'https://secure.gravatar.com/avatar/' . $m[1] . '?s=224&d=mm&r=g';
		}

		// Flatten to text for label-based parsing. Tags are replaced with a SPACE
		// (not stripped) because the impact panel packs the number and its label
		// into adjacent tags with no whitespace between them —
		// e.g. `<span class="n">77</span><span class="u">contributions</span>`.
		// wp_strip_all_tags() would glue those into "77contributions" and the
		// whitespace-based regex below would never match (all metrics read as 0).
		$text = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $html );
		$text = preg_replace( '/<[^>]+>/', ' ', $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		$d365 = self::parse_window( $text, 'Last 12 months' );
		$d90  = self::parse_window( $text, 'Last 90 days' );
		if ( $d365 ) {
			$out['score']         = $d365['score'];
			$out['contributions'] = $d365['contributions'];
			$out['high']          = $d365['high'];
		}
		if ( $d90 ) {
			$out['recent90'] = $d90['contributions'];
		}

		return $out;
	}

	/**
	 * Parse one "Recent impact" time window from flattened profile text.
	 *
	 * @param string $text  Flattened text.
	 * @param string $label Window label, e.g. "Last 12 months".
	 * @return array|null
	 */
	private static function parse_window( $text, $label ) {
		$pattern = '/' . preg_quote( $label, '/' )
			. '\s+([\d,]+)\s+contributions?\s+high\s+([\d,]+)\s+medium\s+([\d,]+)\s+score\s+([\d,]+)/i';
		if ( ! preg_match( $pattern, $text, $m ) ) {
			return null;
		}
		return array(
			'contributions' => (int) str_replace( ',', '', $m[1] ),
			'high'          => (int) str_replace( ',', '', $m[2] ),
			'medium'        => (int) str_replace( ',', '', $m[3] ),
			'score'         => (int) str_replace( ',', '', $m[4] ),
		);
	}

	/**
	 * Fetch every record from an Airtable table (handles pagination).
	 *
	 * @param array  $settings Plugin settings.
	 * @param string $table    Table id or name.
	 * @param array  $fields   Fields to request.
	 * @param string $formula  Optional filterByFormula.
	 * @return array Records.
	 * @throws Exception On API failure.
	 */
	private static function airtable_all( $settings, $table, $fields, $formula = '' ) {
		$records = array();
		$offset  = '';

		do {
			$args = array( 'pageSize' => 100 );
			if ( $fields ) {
				$args['fields'] = $fields;
			}
			if ( $formula ) {
				$args['filterByFormula'] = $formula;
			}
			if ( $offset ) {
				$args['offset'] = $offset;
			}

			$url = 'https://api.airtable.com/v0/' . rawurlencode( $settings['base_id'] ) . '/' . rawurlencode( $table )
				. '?' . self::build_query( $args );

			$resp = wp_remote_get(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'Authorization' => 'Bearer ' . $settings['airtable_pat'] ),
				)
			);

			if ( is_wp_error( $resp ) ) {
				throw new Exception( 'Airtable request failed: ' . esc_html( $resp->get_error_message() ) );
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( 200 !== $code ) {
				$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
				throw new Exception( 'Airtable error: ' . esc_html( $msg ) );
			}

			if ( ! empty( $body['records'] ) ) {
				$records = array_merge( $records, $body['records'] );
			}
			$offset = isset( $body['offset'] ) ? $body['offset'] : '';
		} while ( $offset );

		return $records;
	}

	/**
	 * Build an Airtable query string, expanding array params (fields[]) correctly.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	private static function build_query( $args ) {
		$parts = array();
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					$parts[] = rawurlencode( $key ) . '%5B%5D=' . rawurlencode( $v ); // key[]=v
				}
			} else {
				$parts[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
			}
		}
		return implode( '&', $parts );
	}

	/**
	 * Extract a WordPress.org username from a profile URL / handle.
	 *
	 * @param string $url Raw value from Airtable.
	 * @return string|null
	 */
	private static function extract_username( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return null;
		}
		if ( preg_match( '#profiles\.wordpress\.org/(?:website-redirect/)?([^/?\#]+)#i', $url, $m ) ) {
			return $m[1];
		}
		if ( '@' === substr( $url, 0, 1 ) ) {
			return trim( substr( $url, 1 ) );
		}
		return null; // Non-wp.org (personal blog, form, etc.).
	}

	/**
	 * Normalize an email for matching.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function norm_email( $email ) {
		return strtolower( trim( (string) $email ) );
	}

	/**
	 * Record a sync failure.
	 *
	 * @param string $message Error message.
	 */
	private static function fail( $message ) {
		update_option( SI_OPT_LASTERR, $message );
		$state          = get_option( SI_OPT_STATE, array() );
		$state          = is_array( $state ) ? $state : array();
		$state['phase'] = 'error';
		update_option( SI_OPT_STATE, $state, false );
	}
}
