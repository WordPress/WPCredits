<?php
/**
 * Sync engine — a faithful PHP port of the upstream Python builder
 * (scripts/build_dashboard.py in wordpress/WPCredits-Tracker).
 *
 * It pulls the WordPress Credits program data from Airtable, scrapes each
 * student's profiles.wordpress.org page for translation activity, computes the
 * same public aggregates the GitHub dashboard shows, and stores the resulting
 * data blob for the front-end to render.
 *
 * The sync is a resumable state machine: each invocation works within a
 * wall-clock budget and, if there is more to do, reschedules itself. This keeps
 * every request short so it survives restrictive host timeouts (e.g. WordPress.com).
 * The pattern mirrors ESS_Sync in the sibling education-student-stories plugin.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCT_Sync {

	/** Wall-clock budget (seconds) per invocation before yielding. */
	const TIME_BUDGET = 18;

	/** Profiles scraped per invocation cap (belt-and-suspenders with the time budget). */
	const BATCH_CAP = 40;

	/** HTTP timeout per profile fetch (seconds). */
	const HTTP_TIMEOUT = 12;

	/** Airtable API root. */
	const API_URL = 'https://api.airtable.com/v0';

	/**
	 * Airtable table IDs. Overridable via the `wpct_tables` filter.
	 *
	 * @return array
	 */
	public static function tables() {
		return apply_filters(
			'wpct_tables',
			array(
				'students_reports'   => 'tbljYkkVGbeoaWEtY',
				'students'           => 'tbla8GZg5x6NY7aWt',
				'mentors'            => 'tblJmEYgBWYxVuzUw',
				'institutions'       => 'tbl4V0FEbzRP7I2w2',
				'sponsors'           => 'tbluji8wknOZr55fa',
				'languages'          => 'tblaxEPaabmlccHWn',
				'contribution_areas' => 'tblUBEXiS3QKUCXHf',
				'countries'          => 'tbltB7GSRoTtSi4Ps',
				'lessons'            => 'tblGYMK0VpwMv3Bsy',
				'feedback'           => 'tblx3TH6fp4edQJDm',
			)
		);
	}

	/**
	 * Airtable field IDs, grouped by table. Overridable via the `wpct_fields` filter.
	 * Copied verbatim from the upstream build_dashboard.py FIELDS map.
	 *
	 * @return array
	 */
	public static function fields() {
		return apply_filters(
			'wpct_fields',
			array(
				'students_reports' => array(
					'name'                     => 'fldyXVlkChJaO9Q47',
					'email'                    => 'fldGfPd4fF9lxAXYI',
					'status'                   => 'fldCMdqqJGAUQ9nbV',
					'institution'              => 'fldRqJlE4nwZQR3QO',
					'hours'                    => 'fld7msftOzCxAG5E3',
					'wp_profile'               => 'fld2rGCjmvTZg5DLg',
					'teams'                    => 'fldwPGiajTLTu1Vqi',
					'internship_end_date'      => 'fldLwLXupWurmimc7',
					'internship_start_date'    => 'fldeadC0FAkXAxa17',
					'first_contribution'       => 'fld9AmBSoV87iaeMU',
					'event_url'                => 'fldJODnqx8jxGn0ra',
					'website_url'              => 'fld2n1L2vcwDtlPlg',
					'lessons'                  => 'fldE1rkXbTWJe8bBq',
					'mentor'                   => 'fldSBTwMgno8ecQ2X',
					'grade_open_source'        => 'fld8OJCdWSIvt31ay',
					'grade_decisions'          => 'fldo4NPCj2kyRvgiZ',
					'grade_etiquette'          => 'fldER6s99C6hxxwg9',
					'grade_voice'              => 'fld8Utsr9D5roQYo0',
					'grade_conflict'           => 'fldwKJ8RlnXB0nytX',
					'grade_beginner_user'      => 'fldTNFxjYdNligmj5',
					'grade_intermediate_user'  => 'fldqcMjyR2jtdmxyZ',
					'grade_advanced_user'      => 'fldKK2MJbLClz6MUv',
					'grade_beginner_dev'       => 'fldGJ9A04aH2UWxTt',
					'grade_intermediate_theme' => 'fldy56jmos6xu9FvR',
					'grade_beginner_designer'  => 'fldsxMTOWSK0QUlVS',
				),
				'students'         => array(
					'full_name'           => 'fldvGRKcyRBACeX9t',
					'email'               => 'fldIj9twnzJ0oISpy',
					'start_date'          => 'fldoM2MDWAJAs2seh',
					'field_of_study'      => 'fldwVUA9HZUhZFxJL',
					'internship_end_date' => 'fld6DQEFvDcaM9PuZ',
					'wp_profile'          => 'fldqZWsRYplXlc8E4',
				),
				'mentors'          => array(
					'full_name'       => 'fldHYNbsylHn4SPQI',
					'wp_profile'      => 'fld8RNVcZ861zDRp5',
					'status'          => 'fldxe86OLwnyWqRSD',
					'country'         => 'fldEdaWeBP8er8XwM',
					'languages'       => 'fldXfYWBQgoo8LNpQ',
					'students'        => 'fld7b9hj4UG14KohI',
					'student_reports' => 'fldnbYOM2vHPwJjpg',
					'sponsored'       => 'fldFypFl2gQkUszXn',
					'sponsor_company' => 'fldtdhRzXWpfcb13w',
				),
				'institutions'     => array(
					'name'          => 'fldZQBu7XS2Z29jx4',
					'city'          => 'fldinUAUulxqjZ7d5',
					'country'       => 'fldMZYV5XmC6FbewY',
					'current_stage' => 'fld4l5x6ScLSLaJZl',
				),
				'sponsors'         => array(
					'company_name' => 'fldezMq2OBVeqn0DK',
					'status'       => 'fld4woELctFTrNzNa',
				),
				'languages'        => array(
					'name' => 'flducizfXx3Lz4cid',
				),
				'contribution_areas' => array(
					'name' => 'flduk4myvdidZsAlH',
				),
				'countries'        => array(
					'name' => 'fldtNqCEbpwdo9F2t',
				),
				'lessons'          => array(
					'f3_impact'    => 'fld5idMQUwwpiljWf',
					'f3_recommend' => 'fldKXSzK5zkGRe8OC',
					'f3_keep'      => 'fld4yTcZWUxdYiiiz',
				),
				'feedback'         => array(
					'ease'         => 'fldn7M6U4xXurEgJ2',
					'satisfaction' => 'fldJPTEix369b8NOw',
					'impact'       => 'fldxtEUsunNgjyKXV',
					'confidence'   => 'fldgnTd61qnS22D81',
					'recommend'    => 'fldZXbvOTIGad6r90',
					'keep'         => 'fld8RZMdCLYKtwcLN',
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * State-machine control
	 * ------------------------------------------------------------------- */

	/**
	 * Kick off a fresh sync (called by "Sync now" and by the scheduled event).
	 */
	public static function start() {
		update_option(
			WPCT_OPT_STATE,
			array(
				'phase'   => 'fetch',
				'cursor'  => 0,
				'started' => time(),
			),
			false
		);
		delete_option( WPCT_OPT_LASTERR );
		self::schedule_next();
	}

	/**
	 * Schedule the next sync step ASAP and nudge WP-Cron to run it.
	 */
	private static function schedule_next() {
		if ( ! wp_next_scheduled( WPCT_CRON_RUN ) ) {
			wp_schedule_single_event( time(), WPCT_CRON_RUN );
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
		$state = get_option( WPCT_OPT_STATE, array() );
		return is_array( $state ) && ! empty( $state['phase'] ) && ! in_array( $state['phase'], array( 'done', 'error' ), true );
	}

	/**
	 * Human-readable progress string for the admin screen.
	 *
	 * @return string
	 */
	public static function progress_label() {
		$state = get_option( WPCT_OPT_STATE, array() );
		if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
			return '';
		}
		switch ( $state['phase'] ) {
			case 'fetch':
				return __( 'Fetching program data from Airtable…', 'wpcredits-tracker' );
			case 'scrape':
				$total = isset( $state['students'] ) ? count( $state['students'] ) : 0;
				$done  = (int) $state['cursor'];
				/* translators: 1: profiles processed, 2: total profiles. */
				return sprintf( __( 'Reading WordPress.org profiles (%1$d / %2$d)…', 'wpcredits-tracker' ), $done, $total );
			case 'finalize':
				return __( 'Building the dashboard…', 'wpcredits-tracker' );
			default:
				return '';
		}
	}

	/**
	 * One step of the sync. Registered on both cron hooks.
	 */
	public static function run_step() {
		$settings = wpct_get_settings();
		if ( empty( $settings['airtable_pat'] ) ) {
			self::fail( __( 'No Airtable Personal Access Token configured.', 'wpcredits-tracker' ) );
			return;
		}

		$state = get_option( WPCT_OPT_STATE, array() );
		if ( ! is_array( $state ) || empty( $state['phase'] ) || in_array( $state['phase'], array( 'done', 'error' ), true ) ) {
			self::start();
			$state = get_option( WPCT_OPT_STATE, array() );
		}

		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$deadline = time() + self::TIME_BUDGET;

		try {
			if ( 'fetch' === $state['phase'] ) {
				$state = self::phase_fetch( $settings );
				update_option( WPCT_OPT_STATE, $state, false );
			}

			if ( 'scrape' === $state['phase'] ) {
				$state = self::phase_scrape( $state, $deadline );
				update_option( WPCT_OPT_STATE, $state, false );
			}

			if ( 'finalize' === $state['phase'] ) {
				self::phase_finalize( $state );
				return; // Done.
			}

			if ( self::is_running() ) {
				self::schedule_next();
			}
		} catch ( Exception $e ) {
			self::fail( $e->getMessage() );
		}
	}

	/* ---------------------------------------------------------------------
	 * Phase 1: fetch + compute everything except translation stats
	 * ------------------------------------------------------------------- */

	/**
	 * Pull all Airtable tables, compute the aggregates, and build the scrape list.
	 *
	 * @param array $settings Plugin settings.
	 * @return array New state (phase => scrape).
	 * @throws Exception On API failure.
	 */
	private static function phase_fetch( $settings ) {
		$tables = self::tables();
		$fields = self::fields();

		$students_reports = self::airtable_all( $settings, $tables['students_reports'], array_values( $fields['students_reports'] ) );
		$students_recs    = self::airtable_all( $settings, $tables['students'], array_values( $fields['students'] ) );
		$mentors_recs     = self::airtable_all( $settings, $tables['mentors'], array_values( $fields['mentors'] ) );
		$institutions     = self::airtable_all( $settings, $tables['institutions'], array_values( $fields['institutions'] ) );
		$sponsors         = self::airtable_all( $settings, $tables['sponsors'], array_values( $fields['sponsors'] ) );
		$languages        = self::airtable_all( $settings, $tables['languages'], array_values( $fields['languages'] ) );
		$contrib_areas    = self::airtable_all( $settings, $tables['contribution_areas'], array_values( $fields['contribution_areas'] ) );
		$countries        = self::airtable_all( $settings, $tables['countries'], array_values( $fields['countries'] ) );
		$lessons          = self::airtable_all( $settings, $tables['lessons'], array_values( $fields['lessons'] ) );
		$feedback_recs    = self::airtable_all( $settings, $tables['feedback'], array_values( $fields['feedback'] ) );

		$F = $fields; // Shorthand.

		// ---- Lookups ------------------------------------------------------
		$institutions_lookup   = array();
		$confirmed_institutions = array();
		foreach ( $institutions as $rec ) {
			$name  = self::fv( $rec, $F['institutions']['name'] );
			$stage = self::select_name( self::fv( $rec, $F['institutions']['current_stage'] ) );
			if ( $name ) {
				$institutions_lookup[ $rec['id'] ] = array( 'name' => self::title_case( $name ), 'stage' => $stage );
				if ( 'confirmed' === self::status_key( $stage ) ) {
					$confirmed_institutions[ self::title_case( $name ) ] = true;
				}
			}
		}

		$contrib_lookup = array();
		foreach ( $contrib_areas as $rec ) {
			$name = self::fv( $rec, $F['contribution_areas']['name'] );
			if ( $name ) {
				$contrib_lookup[ $rec['id'] ] = self::title_case( $name );
			}
		}

		$countries_lookup = array();
		foreach ( $countries as $rec ) {
			$name = self::fv( $rec, $F['countries']['name'] );
			if ( $name ) {
				$countries_lookup[ $rec['id'] ] = self::title_case( $name );
			}
		}

		// Students table cross-reference (join on email, fall back to normalized name).
		$students_by_email = array();
		$students_by_name  = array();
		foreach ( $students_recs as $rec ) {
			$name = self::fv( $rec, $F['students']['full_name'] );
			if ( $name ) {
				$students_by_name[ self::norm_name( $name ) ] = $rec;
			}
			$email = self::fv( $rec, $F['students']['email'] );
			if ( $email ) {
				$students_by_email[ self::norm_email( $email ) ] = $rec;
			}
		}

		// ---- Status sets (keep in sync with the upstream builder) ---------
		$active_statuses = array( 'In Sensei', 'In Sensei Self-onboarding', 'In Sensei 50h', 'Pending graduation' );
		$included_keys   = array();
		foreach ( array_merge( $active_statuses, array( 'Graduate' ) ) as $s ) {
			$included_keys[ self::status_key( $s ) ] = true;
		}
		$graduate_key    = self::status_key( 'Graduate' );
		$dropout_key     = self::status_key( 'Dropped out' );
		$not_forward_key = self::status_key( 'Not moving forward' );

		$institution_aliases = array(
			'cnm ingenuity' => 'Central New Mexico Community College',
		);

		$grade_fields = array(
			'grade_open_source', 'grade_decisions', 'grade_etiquette', 'grade_voice',
			'grade_conflict', 'grade_beginner_user', 'grade_intermediate_user',
			'grade_advanced_user', 'grade_beginner_dev', 'grade_intermediate_theme',
			'grade_beginner_designer',
		);

		// ---- Process students --------------------------------------------
		$students          = array();
		$team_distribution = array();
		$active_count      = 0;
		$graduate_count    = 0;
		$dropout_count     = 0;
		$not_forward_count = 0;
		$total_hours       = 0.0;
		$fos_stats         = array();
		$first_contrib     = 0;
		$event_parts       = 0;
		$sites_created     = 0;
		$inst_quarters     = array(); // institution name => set of "y-q" keys.

		foreach ( $students_reports as $rec ) {
			$name       = self::fv( $rec, $F['students_reports']['name'] );
			$status_obj = self::fv( $rec, $F['students_reports']['status'] );
			$hours      = self::fv( $rec, $F['students_reports']['hours'] );
			$hours      = is_numeric( $hours ) ? (float) $hours : 0.0;
			$wp_profile = self::fv( $rec, $F['students_reports']['wp_profile'] );
			$end_date   = self::fv( $rec, $F['students_reports']['internship_end_date'] );
			$teams_ids  = self::fv( $rec, $F['students_reports']['teams'] );
			$teams_ids  = is_array( $teams_ids ) ? $teams_ids : array();

			$completed_courses = 0;
			foreach ( $grade_fields as $gf ) {
				if ( null !== self::fv( $rec, $F['students_reports'][ $gf ] ) ) {
					$completed_courses++;
				}
			}

			if ( ! $name ) {
				continue;
			}

			$status   = self::select_name( $status_obj );
			$status_n = self::status_key( $status_obj );

			// Tally exits before filtering.
			if ( $status_n === $dropout_key ) {
				$dropout_count++;
			} elseif ( $status_n === $not_forward_key ) {
				$not_forward_count++;
			}

			// Contributions-tab metrics (counted across all reports).
			if ( self::fv( $rec, $F['students_reports']['first_contribution'] ) ) {
				$first_contrib++;
			}
			if ( self::fv( $rec, $F['students_reports']['event_url'] ) ) {
				$event_parts++;
			}
			if ( self::fv( $rec, $F['students_reports']['website_url'] ) ) {
				$sites_created++;
			}

			// Repeat-cohort tracking: distinct year-quarters each school enrolled in.
			$fc_email = self::fv( $rec, $F['students_reports']['email'] );
			$fc_srec  = $fc_email ? ( isset( $students_by_email[ self::norm_email( $fc_email ) ] ) ? $students_by_email[ self::norm_email( $fc_email ) ] : null ) : null;
			$fc_inst  = self::fv( $rec, $F['students_reports']['institution'] );
			$fc_inst  = is_array( $fc_inst ) ? $fc_inst : array();
			if ( $fc_inst && isset( $institutions_lookup[ $fc_inst[0] ] ) ) {
				$fc_sd = ( $fc_srec ? self::parse_iso_date( self::fv( $fc_srec, $F['students']['start_date'] ) ) : null );
				if ( ! $fc_sd ) {
					$fc_sd = self::parse_iso_date( self::fv( $rec, $F['students_reports']['internship_start_date'] ) );
				}
				if ( ! $fc_sd ) {
					$fc_sd = self::parse_iso_date( isset( $rec['createdTime'] ) ? $rec['createdTime'] : null );
				}
				if ( $fc_sd ) {
					$fc_name = $institutions_lookup[ $fc_inst[0] ]['name'];
					$alias   = strtolower( trim( $fc_name ) );
					if ( isset( $institution_aliases[ $alias ] ) ) {
						$fc_name = $institution_aliases[ $alias ];
					}
					$q = (int) floor( ( $fc_sd['m'] - 1 ) / 3 );
					$inst_quarters[ $fc_name ][ $fc_sd['y'] . '-' . $q ] = true;
				}
			}

			// Only active + graduate students continue into the showcase aggregates.
			if ( ! isset( $included_keys[ $status_n ] ) ) {
				continue;
			}

			$is_graduate = ( $status_n === $graduate_key );

			// Teams.
			$teams = array();
			foreach ( $teams_ids as $tid ) {
				if ( isset( $contrib_lookup[ $tid ] ) ) {
					$team_name = $contrib_lookup[ $tid ];
					$teams[]   = $team_name;
					$team_distribution[ $team_name ] = isset( $team_distribution[ $team_name ] ) ? $team_distribution[ $team_name ] + 1 : 1;
				}
			}

			// Field of study (email first, then normalized name).
			$field_of_study = null;
			$email_key      = $fc_email ? self::norm_email( $fc_email ) : '';
			$name_key       = self::norm_name( $name );
			$student_rec    = isset( $students_by_email[ $email_key ] ) ? $students_by_email[ $email_key ] : ( isset( $students_by_name[ $name_key ] ) ? $students_by_name[ $name_key ] : null );
			if ( $student_rec ) {
				$fos_obj = self::fv( $student_rec, $F['students']['field_of_study'] );
				$field_of_study = self::select_name( $fos_obj );
			}

			if ( $is_graduate ) {
				$graduate_count++;
			} else {
				$active_count++;
			}
			$total_hours += $hours;

			if ( $field_of_study ) {
				if ( ! isset( $fos_stats[ $field_of_study ] ) ) {
					$fos_stats[ $field_of_study ] = array( 'active' => 0, 'graduated' => 0 );
				}
				if ( $is_graduate ) {
					$fos_stats[ $field_of_study ]['graduated']++;
				} else {
					$fos_stats[ $field_of_study ]['active']++;
				}
			}

			$students[] = array(
				'status'       => $status,
				'is_graduate'  => $is_graduate,
				'fieldOfStudy' => $field_of_study,
				'total_strings' => 0, // Filled in during the scrape phase.
				'wp_username'  => self::extract_wp_username( $wp_profile ),
				'courses'      => $completed_courses + ( $is_graduate ? 1 : 0 ),
			);
		}

		// ---- Mentors (only the aggregate figures the public blob needs) ---
		$active_mentors  = 0;
		$vetted_mentors  = 0;
		$mentor_countries = array();
		foreach ( $mentors_recs as $rec ) {
			$name = self::fv( $rec, $F['mentors']['full_name'] );
			if ( ! $name ) {
				continue;
			}
			$status_n = self::status_key( self::fv( $rec, $F['mentors']['status'] ) );
			if ( $status_n === self::status_key( 'Vetted - positive' ) ) {
				$vetted_mentors++;
			}
			if ( $status_n !== self::status_key( 'Active' ) ) {
				continue;
			}
			$active_mentors++;
			$country_ids = self::fv( $rec, $F['mentors']['country'] );
			$country_ids = is_array( $country_ids ) ? $country_ids : array();
			foreach ( $country_ids as $cid ) {
				if ( isset( $countries_lookup[ $cid ] ) ) {
					$cname = $countries_lookup[ $cid ];
					$mentor_countries[ $cname ] = isset( $mentor_countries[ $cname ] ) ? $mentor_countries[ $cname ] + 1 : 1;
				}
			}
		}

		// ---- Sponsors -----------------------------------------------------
		$approved_sponsors = 0;
		foreach ( $sponsors as $rec ) {
			if ( self::status_key( self::fv( $rec, $F['sponsors']['status'] ) ) === self::status_key( 'Approved' ) ) {
				$approved_sponsors++;
			}
		}

		// ---- Confirmed partner institutions by country --------------------
		$inst_countries = array();
		foreach ( $institutions as $rec ) {
			if ( self::status_key( self::fv( $rec, $F['institutions']['current_stage'] ) ) !== 'confirmed' ) {
				continue;
			}
			$country_ids = self::fv( $rec, $F['institutions']['country'] );
			$country_ids = is_array( $country_ids ) ? $country_ids : array();
			foreach ( $country_ids as $cid ) {
				if ( isset( $countries_lookup[ $cid ] ) ) {
					$cname = $countries_lookup[ $cid ];
					$inst_countries[ $cname ] = isset( $inst_countries[ $cname ] ) ? $inst_countries[ $cname ] + 1 : 1;
				}
			}
		}

		// ---- Growth over time --------------------------------------------
		$growth = self::build_growth( $students_reports, $lessons, $students_by_email, $F, $graduate_key );

		// ---- Feedback aggregate ------------------------------------------
		$feedback = self::build_feedback( $feedback_recs, $F );

		// ---- Repeat schools ----------------------------------------------
		$today           = array( 'y' => (int) gmdate( 'Y' ), 'q' => (int) floor( ( (int) gmdate( 'n' ) - 1 ) / 3 ) );
		$current_quarter = $today['y'] * 4 + $today['q'];
		$eligible        = array();
		foreach ( $inst_quarters as $iname => $qset ) {
			if ( empty( $qset ) ) {
				continue;
			}
			$min = PHP_INT_MAX;
			foreach ( array_keys( $qset ) as $qk ) {
				list( $qy, $qq ) = array_map( 'intval', explode( '-', $qk ) );
				$min = min( $min, $qy * 4 + $qq );
			}
			if ( $min < $current_quarter ) {
				$eligible[ $iname ] = $qset;
			}
		}
		$repeat_total = count( $eligible );
		$repeat_count = 0;
		foreach ( $eligible as $qset ) {
			if ( count( $qset ) >= 2 ) {
				$repeat_count++;
			}
		}
		$repeat_schools = array(
			'pct'    => $repeat_total ? (int) round( 100 * $repeat_count / $repeat_total ) : null,
			'count'  => $repeat_count,
			'total'  => $repeat_total,
			'tooNew' => count( $inst_quarters ) - $repeat_total,
		);

		// ---- Global stats -------------------------------------------------
		$total_courses = 0;
		foreach ( $students as $s ) {
			$total_courses += (int) $s['courses'];
		}

		$global = array(
			'activeStudents'      => $active_count,
			'graduates'           => $graduate_count,
			'dropouts'            => $dropout_count,
			'notMovingForward'    => $not_forward_count,
			'totalHours'          => (int) round( $total_hours ),
			'partnerInstitutions' => count( $confirmed_institutions ),
			'sponsorCount'        => $approved_sponsors,
			'activeMentors'       => $active_mentors,
			'vettedMentors'       => $vetted_mentors,
			'teamDistribution'    => $team_distribution,
			'totalCourses'        => $total_courses,
			'instCountries'       => $inst_countries,
			'mentorCountries'     => $mentor_countries,
			'fieldOfStudy'        => $fos_stats,
			'firstContributions'  => $first_contrib,
			'eventParticipants'   => $event_parts,
			'sitesCreated'        => $sites_created,
			'repeatSchools'       => $repeat_schools,
		);

		return array(
			'phase'    => $students ? 'scrape' : 'finalize',
			'cursor'   => 0,
			'students' => $students,
			'global'   => $global,
			'growth'   => $growth,
			'feedback' => $feedback,
			'translationTotals' => array( 'suggested' => 0, 'translated' => 0, 'reviewed' => 0, 'total' => 0 ),
			'started'  => time(),
		);
	}

	/**
	 * Build the monthly growth series (joined vs graduated, cumulative joined).
	 *
	 * @param array $reports           Students-reports records.
	 * @param array $lessons           Lessons records.
	 * @param array $students_by_email Email => student record.
	 * @param array $F                 Field-id map.
	 * @param string $graduate_key     Normalized "Graduate" status key.
	 * @return array
	 */
	private static function build_growth( $reports, $lessons, $students_by_email, $F, $graduate_key ) {
		// Form-3 submission dates keyed by lessons record id.
		$form3_date = array();
		foreach ( $lessons as $rec ) {
			$is_form3 = false;
			foreach ( array( 'f3_impact', 'f3_recommend', 'f3_keep' ) as $f ) {
				if ( null !== self::fv( $rec, $F['lessons'][ $f ] ) ) {
					$is_form3 = true;
					break;
				}
			}
			if ( $is_form3 ) {
				$d = self::parse_iso_date( isset( $rec['createdTime'] ) ? $rec['createdTime'] : null );
				if ( $d ) {
					$form3_date[ $rec['id'] ] = $d;
				}
			}
		}

		$joined_statuses = array( 'In Sensei', 'In Sensei Self-onboarding', 'In Sensei 50h', 'Pending graduation', 'Graduate', 'Dropped out', 'Paused', 'Fail' );
		$joined_keys     = array();
		foreach ( $joined_statuses as $s ) {
			$joined_keys[ self::status_key( $s ) ] = true;
		}
		$current_month = gmdate( 'Y-m' );

		$joined_by_month    = array();
		$graduated_by_month = array();
		foreach ( $reports as $rec ) {
			$status_n = self::status_key( self::fv( $rec, $F['students_reports']['status'] ) );
			if ( ! isset( $joined_keys[ $status_n ] ) ) {
				continue;
			}
			$created = self::parse_iso_date( isset( $rec['createdTime'] ) ? $rec['createdTime'] : null );

			$email    = self::fv( $rec, $F['students_reports']['email'] );
			$srec     = $email && isset( $students_by_email[ self::norm_email( $email ) ] ) ? $students_by_email[ self::norm_email( $email ) ] : null;
			$start    = $srec ? self::parse_iso_date( self::fv( $srec, $F['students']['start_date'] ) ) : null;
			if ( ! $start ) {
				$start = self::parse_iso_date( self::fv( $rec, $F['students_reports']['internship_start_date'] ) );
			}
			if ( ! $start ) {
				$start = $created;
			}
			$mk = self::month_key( $start );
			if ( $mk && $mk <= $current_month ) {
				$joined_by_month[ $mk ] = isset( $joined_by_month[ $mk ] ) ? $joined_by_month[ $mk ] + 1 : 1;
			}

			if ( $status_n === $graduate_key ) {
				$lesson_ids = self::fv( $rec, $F['students_reports']['lessons'] );
				$lesson_ids = is_array( $lesson_ids ) ? $lesson_ids : array();
				$grad_date  = null;
				$min_serial = PHP_INT_MAX;
				foreach ( $lesson_ids as $lid ) {
					if ( isset( $form3_date[ $lid ] ) ) {
						$serial = $form3_date[ $lid ]['y'] * 372 + $form3_date[ $lid ]['m'] * 31 + $form3_date[ $lid ]['d'];
						if ( $serial < $min_serial ) {
							$min_serial = $serial;
							$grad_date  = $form3_date[ $lid ];
						}
					}
				}
				if ( ! $grad_date ) {
					$grad_date = self::parse_iso_date( self::fv( $rec, $F['students_reports']['internship_end_date'] ) );
				}
				$mk = self::month_key( $grad_date );
				if ( $mk && $mk <= $current_month ) {
					$graduated_by_month[ $mk ] = isset( $graduated_by_month[ $mk ] ) ? $graduated_by_month[ $mk ] + 1 : 1;
				}
			}
		}

		$growth = array( 'months' => array(), 'joined' => array(), 'graduated' => array(), 'cumulativeJoined' => array() );
		$all_months = array_unique( array_merge( array_keys( $joined_by_month ), array_keys( $graduated_by_month ) ) );
		if ( $all_months ) {
			sort( $all_months );
			$first = $all_months[0];
			$y = (int) substr( $first, 0, 4 );
			$m = (int) substr( $first, 5, 2 );
			$cy = (int) substr( $current_month, 0, 4 );
			$cm = (int) substr( $current_month, 5, 2 );
			$cum = 0;
			while ( ( $y * 12 + $m ) <= ( $cy * 12 + $cm ) ) {
				$mk  = sprintf( '%04d-%02d', $y, $m );
				$cum += isset( $joined_by_month[ $mk ] ) ? $joined_by_month[ $mk ] : 0;
				$growth['months'][]           = $mk;
				$growth['joined'][]           = isset( $joined_by_month[ $mk ] ) ? $joined_by_month[ $mk ] : 0;
				$growth['graduated'][]        = isset( $graduated_by_month[ $mk ] ) ? $graduated_by_month[ $mk ] : 0;
				$growth['cumulativeJoined'][] = $cum;
				if ( $m < 12 ) {
					$m++;
				} else {
					$m = 1;
					$y++;
				}
			}
		}
		return $growth;
	}

	/**
	 * Build the aggregate feedback figures (experience-only, no individuals).
	 *
	 * @param array $recs Feedback records.
	 * @param array $F    Field-id map.
	 * @return array
	 */
	private static function build_feedback( $recs, $F ) {
		$rating_avg = static function ( $field ) use ( $recs, $F ) {
			$vals = array();
			foreach ( $recs as $r ) {
				$v = self::fv( $r, $F['feedback'][ $field ] );
				if ( is_int( $v ) || is_float( $v ) ) {
					$vals[] = $v;
				}
			}
			if ( ! $vals ) {
				return array( 'avg' => null, 'n' => 0 );
			}
			return array( 'avg' => round( array_sum( $vals ) / count( $vals ), 1 ), 'n' => count( $vals ) );
		};

		$pct_positive = static function ( $field, $positive ) use ( $recs, $F ) {
			$keys = array();
			foreach ( $recs as $r ) {
				$k = self::status_key( self::fv( $r, $F['feedback'][ $field ] ) );
				if ( $k ) {
					$keys[] = $k;
				}
			}
			if ( ! $keys ) {
				return array( 'pct' => null, 'n' => 0 );
			}
			$pos = 0;
			foreach ( $keys as $k ) {
				if ( isset( $positive[ $k ] ) ) {
					$pos++;
				}
			}
			return array( 'pct' => (int) round( 100 * $pos / count( $keys ) ), 'n' => count( $keys ) );
		};

		$conf_labels = array(
			'very confident'     => 'Very confident',
			'confident'          => 'Confident',
			'neutral'            => 'Neutral',
			'not very confident' => 'Not very confident',
		);
		$conf_counts = array( 'Very confident' => 0, 'Confident' => 0, 'Neutral' => 0, 'Not very confident' => 0 );
		$conf_n      = 0;
		foreach ( $recs as $r ) {
			$k = self::status_key( self::fv( $r, $F['feedback']['confidence'] ) );
			if ( isset( $conf_labels[ $k ] ) ) {
				$conf_counts[ $conf_labels[ $k ] ]++;
				$conf_n++;
			}
		}

		// NOTE: no overall "responses" count. A Feedback row is pre-created for
		// every student whether or not they answer, so counting rows overstates
		// the sample; each metric carries its own honest denominator (n) instead.
		return array(
			'ratings'    => array(
				'ease'         => $rating_avg( 'ease' ),
				'satisfaction' => $rating_avg( 'satisfaction' ),
				'impact'       => $rating_avg( 'impact' ),
			),
			'recommend'  => $pct_positive( 'recommend', array( 'likely' => true ) ),
			'keep'       => $pct_positive( 'keep', array( 'likely' => true ) ),
			'confidence' => array( 'dist' => $conf_counts, 'n' => $conf_n ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Phase 2: scrape WordPress.org profiles for translation stats
	 * ------------------------------------------------------------------- */

	/**
	 * Scrape profiles within the time budget; resumable via the cursor.
	 *
	 * @param array $state    Current state.
	 * @param int   $deadline Unix time to stop by.
	 * @return array Updated state.
	 */
	private static function phase_scrape( $state, $deadline ) {
		$total     = count( $state['students'] );
		$processed = 0;

		while ( $state['cursor'] < $total && time() < $deadline && $processed < self::BATCH_CAP ) {
			$idx      = $state['cursor'];
			$username = isset( $state['students'][ $idx ]['wp_username'] ) ? $state['students'][ $idx ]['wp_username'] : '';
			if ( $username ) {
				$stats = self::scrape_translation( $username );
				$state['students'][ $idx ]['total_strings'] = $stats['total'];
				$state['translationTotals']['suggested']   += $stats['suggested'];
				$state['translationTotals']['translated']  += $stats['translated'];
				$state['translationTotals']['reviewed']    += $stats['reviewed'];
				$state['translationTotals']['total']       += $stats['total'];
			}
			$state['cursor']++;
			$processed++;
		}

		if ( $state['cursor'] >= $total ) {
			$state['phase'] = 'finalize';
		}
		return $state;
	}

	/**
	 * Fetch translation stats from a WordPress.org profile page.
	 * Parses the activity feed for "Suggested/Translated/Reviewed N string(s)".
	 *
	 * @param string $username WordPress.org username.
	 * @return array {suggested, translated, reviewed, total}
	 */
	private static function scrape_translation( $username ) {
		$out = array( 'suggested' => 0, 'translated' => 0, 'reviewed' => 0, 'total' => 0 );

		$resp = wp_remote_get(
			'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/',
			array(
				'timeout'    => self::HTTP_TIMEOUT,
				'user-agent' => 'WPCredits-Tracker/' . WPCT_VERSION . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return $out;
		}
		$html = wp_remote_retrieve_body( $resp );

		if ( preg_match_all( '/Suggested (\d+) strings?/', $html, $m ) ) {
			$out['suggested'] = array_sum( array_map( 'intval', $m[1] ) );
		}
		if ( preg_match_all( '/Translated (\d+) strings?/', $html, $m ) ) {
			$out['translated'] = array_sum( array_map( 'intval', $m[1] ) );
		}
		if ( preg_match_all( '/Reviewed (\d+) strings?/', $html, $m ) ) {
			$out['reviewed'] = array_sum( array_map( 'intval', $m[1] ) );
		}
		$out['total'] = $out['suggested'] + $out['translated'] + $out['reviewed'];
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Phase 3: assemble + publish the public data blob
	 * ------------------------------------------------------------------- */

	/**
	 * Store the final public data blob and mark the sync complete.
	 *
	 * @param array $state Current state.
	 */
	private static function phase_finalize( $state ) {
		// Public rows only — anonymous, exactly the fields the front-end reads.
		$public_students = array();
		foreach ( $state['students'] as $s ) {
			$public_students[] = array(
				'status'        => $s['status'],
				'is_graduate'   => (bool) $s['is_graduate'],
				'fieldOfStudy'  => $s['fieldOfStudy'],
				'total_strings' => (int) $s['total_strings'],
			);
		}

		$blob = array(
			'global'            => $state['global'],
			'translationTotals' => $state['translationTotals'],
			'growth'            => $state['growth'],
			'feedback'          => $state['feedback'],
			'students'          => $public_students,
		);

		update_option( WPCT_OPT_DATA, $blob, false );
		update_option( WPCT_OPT_LASTSYNC, time() );
		delete_option( WPCT_OPT_LASTERR );

		$state['phase'] = 'done';
		update_option( WPCT_OPT_STATE, $state, false );
	}

	/* ---------------------------------------------------------------------
	 * Airtable transport
	 * ------------------------------------------------------------------- */

	/**
	 * Fetch every record from an Airtable table (paginated), requesting fields
	 * by ID. Falls back to fetching all fields if Airtable rejects the field
	 * list with a 422 (a mapped field was renamed/deleted) — matching upstream.
	 *
	 * @param array  $settings  Plugin settings.
	 * @param string $table_id  Table id.
	 * @param array  $field_ids Field ids to request.
	 * @return array Records.
	 * @throws Exception On API failure.
	 */
	private static function airtable_all( $settings, $table_id, $field_ids ) {
		$records         = array();
		$offset          = '';
		$use_field_filter = ! empty( $field_ids );

		do {
			$args = array( 'pageSize' => 100, 'returnFieldsByFieldId' => 'true' );
			if ( $use_field_filter ) {
				$args['fields'] = $field_ids;
			}
			if ( $offset ) {
				$args['offset'] = $offset;
			}

			$url = self::API_URL . '/' . rawurlencode( $settings['base_id'] ) . '/' . rawurlencode( $table_id )
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

			// 422 with a field filter: a requested field id is gone. Drop the
			// filter and restart this table so the build degrades gracefully.
			if ( 422 === $code && $use_field_filter ) {
				$use_field_filter = false;
				$records          = array();
				$offset           = '';
				continue;
			}

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

	/* ---------------------------------------------------------------------
	 * Helpers (ports of the upstream Python helpers)
	 * ------------------------------------------------------------------- */

	/**
	 * Safely read a field value from an Airtable record.
	 *
	 * @param array  $record   Record.
	 * @param string $field_id Field id.
	 * @return mixed|null
	 */
	private static function fv( $record, $field_id ) {
		return isset( $record['fields'][ $field_id ] ) ? $record['fields'][ $field_id ] : null;
	}

	/**
	 * Extract the display name from a single-select value (array or scalar).
	 *
	 * Select names are third-party text — whoever administers the Airtable base
	 * chooses them — and some of them are rendered on the public dashboard, so
	 * they are stripped of markup here rather than trusted downstream.
	 *
	 * @param mixed $value Value.
	 * @return mixed|null
	 */
	private static function select_name( $value ) {
		if ( is_array( $value ) ) {
			$value = isset( $value['name'] ) ? $value['name'] : null;
		}
		return is_string( $value ) ? sanitize_text_field( $value ) : $value;
	}

	/**
	 * Normalize a select value for tolerant comparison (trim + casefold).
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	private static function status_key( $value ) {
		$name = self::select_name( $value );
		if ( ! is_string( $name ) ) {
			return null;
		}
		return strtolower( trim( $name ) );
	}

	/**
	 * Title-case a string, keeping Spanish prepositions lower and parenthesized
	 * acronyms upper — mirrors the upstream title_case().
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function title_case( $text ) {
		if ( ! $text ) {
			return $text;
		}
		$lower = array( 'de', 'del', 'la', 'las', 'los', 'el', 'en', 'y', 'e', 'al' );
		$words = preg_split( '/\s+/u', trim( $text ) );
		$out   = array();
		foreach ( $words as $i => $word ) {
			if ( false !== strpos( $word, '(' ) && false !== strpos( $word, ')' ) ) {
				$out[] = $word;
			} elseif ( 0 === $i ) {
				$out[] = self::py_capitalize( $word );
			} elseif ( in_array( mb_strtolower( $word, 'UTF-8' ), $lower, true ) ) {
				$out[] = mb_strtolower( $word, 'UTF-8' );
			} else {
				$out[] = self::py_capitalize( $word );
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * Python str.capitalize(): first char upper, the rest lower.
	 *
	 * @param string $word Word.
	 * @return string
	 */
	private static function py_capitalize( $word ) {
		if ( '' === $word ) {
			return $word;
		}
		return mb_strtoupper( mb_substr( $word, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_strtolower( mb_substr( $word, 1, null, 'UTF-8' ), 'UTF-8' );
	}

	/**
	 * Extract a WordPress.org username from a profile URL.
	 *
	 * @param mixed $url Raw value.
	 * @return string|null
	 */
	private static function extract_wp_username( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || false === strpos( $url, 'profiles.wordpress.org' ) ) {
			return null;
		}
		$parts = explode( '/', rtrim( $url, '/' ) );
		$last  = end( $parts );
		return $last ? $last : null;
	}

	/**
	 * Parse an ISO date/datetime string to ['y','m','d'] or null.
	 *
	 * @param mixed $s Value.
	 * @return array|null
	 */
	private static function parse_iso_date( $s ) {
		if ( ! is_string( $s ) || strlen( $s ) < 10 ) {
			return null;
		}
		$y = (int) substr( $s, 0, 4 );
		$m = (int) substr( $s, 5, 2 );
		$d = (int) substr( $s, 8, 2 );
		if ( $m < 1 || $m > 12 || $d < 1 || $d > 31 ) {
			return null;
		}
		return array( 'y' => $y, 'm' => $m, 'd' => $d );
	}

	/**
	 * YYYY-MM bucket for a parsed date.
	 *
	 * @param array|null $d Parsed date.
	 * @return string|null
	 */
	private static function month_key( $d ) {
		return $d ? sprintf( '%04d-%02d', $d['y'], $d['m'] ) : null;
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
	 * Normalize a name for matching (collapse whitespace, lowercase).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function norm_name( $name ) {
		return trim( preg_replace( '/\s+/u', ' ', mb_strtolower( (string) $name, 'UTF-8' ) ) );
	}

	/**
	 * Record a sync failure.
	 *
	 * @param string $message Error message.
	 */
	private static function fail( $message ) {
		update_option( WPCT_OPT_LASTERR, $message );
		$state          = get_option( WPCT_OPT_STATE, array() );
		$state          = is_array( $state ) ? $state : array();
		$state['phase'] = 'error';
		update_option( WPCT_OPT_STATE, $state, false );
	}
}
