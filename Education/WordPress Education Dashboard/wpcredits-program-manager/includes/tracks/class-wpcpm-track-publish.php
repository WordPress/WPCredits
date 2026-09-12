<?php
/**
 * Publishing a track, and everything that has to be true first.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * What publishing would do, and what it would refuse.
 *
 * The definition's own rules belong to `WPCPM_Track_Store::check()`, the same call the editor
 * makes, so the two can never give different answers (T2b's decision 2). What is added here is
 * everything only Airtable can answer: whether a column exists and what type it is, how many
 * columns the table would hold afterward, and whether the track's status is a `Status` choice.
 */
final class WPCPM_Track_Publish {

	/**
	 * The most columns an Airtable table holds.
	 *
	 * Airtable refuses the request that passes this and keeps everything created before it, so a
	 * run that would cross the line is refused whole rather than leaving half a track (7.1).
	 *
	 * @var int
	 */
	const FIELD_CEILING = 500;

	/**
	 * Where a table stops being comfortable and the screen says so.
	 *
	 * @var int
	 */
	const FIELD_WARNING = 450;

	/**
	 * The column both tables carry the track's status in.
	 *
	 * @var string
	 */
	const STATUS_COLUMN = 'Status';

	/**
	 * The option one run claims while it is going, so two cannot create the same column twice.
	 *
	 * @var string
	 */
	const OPT_LOCK = 'wpcpm_track_publish_lock';

	/**
	 * How long a lock is honoured before a run that never let it go is treated as dead.
	 *
	 * `add_option()` is what makes claiming the lock atomic: it returns false when the option is
	 * already there, whatever value is stored. That is unrelated to T2a's final review, which
	 * found that changing an option's value while still holding the lock is not atomic - this
	 * value is written once when the lock is claimed and never touched again while it is held, so
	 * storing the claim time here does not reopen that finding.
	 *
	 * The time is what lets a lock a live run still holds be told apart from one a run left
	 * behind after being killed - a PHP execution timeout, a php-fpm request_terminate_timeout, a
	 * gateway cutting the request off, a fatal - none of which reach the `delete_option()` on the
	 * normal paths out of `run()`. This is the longest-running foreground request the plugin
	 * makes: one HTTP POST per column, in series, each with its own 20-second timeout. Twenty
	 * minutes is not task 11's 29 columns with a little room (that run took about 580 seconds, so
	 * ten minutes would leave it roughly twenty seconds of margin - too thin for a track with more
	 * questions, or one slow response, to stay inside); it is a very large track covered with room
	 * to spare, while still freeing a lock a killed run wedged well within one working session.
	 * Long enough that a run genuinely still going is never overtaken by a second press creating
	 * the same columns twice; short enough that a killed run does not wedge every track's Publish
	 * button until somebody deletes the option over SSH (final review, finding 2; re-review).
	 *
	 * @var int
	 */
	const LOCK_TIMEOUT = 1200;

	/**
	 * Where a run records the steps that landed, so the next press resumes rather than repeats.
	 *
	 * @var string
	 */
	const META_RUN = '_wpcpm_track_run';

	/**
	 * Where the checklist's ticks are kept: who ticked each item, and when.
	 *
	 * @var string
	 */
	const META_CHECKLIST = '_wpcpm_track_checklist';

	/**
	 * The three things the site cannot do, in the order they have to happen.
	 *
	 * The site cannot see an Airtable automation either way, so an unticked item never blocks
	 * publishing; the track list counts it until somebody ticks it (7.3).
	 *
	 * @var string[]
	 */
	const CHECKLIST = array( 'automation', 'welcome', 'choices' );

	/**
	 * How long a Learn course's reachability is trusted before being asked again.
	 *
	 * A course's reachability does not change minute to minute, and every tick, verify and
	 * publish on the publish screen redirects straight back to a fresh preflight, so without this
	 * a person working down the checklist would pay a five-second-timeout HEAD request on every
	 * single page load (Task 9 review, M4). The schema read the rest of the preflight does is
	 * never cached this way: that one has to say what the base looks like now.
	 *
	 * @var int
	 */
	const COURSE_CACHE_TTL = 300;

	/**
	 * What publishing this track would do, without doing any of it.
	 *
	 * @param int $post_id The track.
	 * @return array {
	 *     @type array  $refusals    Findings that stop publishing, each `code`, `column`, `message`.
	 *     @type array  $warnings    Findings that do not, in the same shape.
	 *     @type array  $columns     `create` and `ready`, each a list of column names, plus
	 *                               `detail`: `create`'s names mapped to what
	 *                               `WPCPM_Track_Columns::field()` says of them.
	 *     @type array  $choices     `reports` and `students`, each `ok`, `near` or `missing`.
	 *     @type array  $fields      `now` and `after`, how many columns the reports table holds.
	 *     @type bool   $adds_status Whether publishing appends the status to `student_statuses`.
	 *     @type bool   $ready       Whether nothing refuses it.
	 * }
	 */
	public static function preflight( $post_id ) {
		$post_id    = (int) $post_id;
		$definition = WPCPM_Track_Store::get( $post_id );
		$refusals   = array();
		$warnings   = array();

		if ( ! is_array( $definition ) ) {
			return self::answer( array( self::finding( 'no_definition', '', __( 'This track has no definition to publish.', 'wpcredits-program-manager' ) ) ), array() );
		}

		// The store's own rules, asked once: a status or key another track holds, a column the
		// syncs own. Its messages travel as they are, so the editor and this screen read the same.
		foreach ( (array) WPCPM_Track_Store::check( $post_id, $definition ) as $error ) {
			$refusals[] = self::finding(
				isset( $error['code'] ) ? (string) $error['code'] : 'invalid',
				isset( $error['column'] ) ? (string) $error['column'] : '',
				isset( $error['message'] ) ? (string) $error['message'] : ''
			);
		}

		// A built-in track's definition must match its PHP: a difference means the editing UI and
		// the PHP are out of sync, and publishing would write the wrong version to the live table.
		$php_diffs = WPCPM_Track_Store::php_differences( $post_id, $definition );

		if ( ! empty( $php_diffs ) ) {
			$refusals[] = self::finding(
				'builtin_changed',
				'',
				self::builtin_diff_message( $php_diffs )
			);
		}

		$settings = WPCPM_Settings::get();
		$client   = new WPCPM_Airtable();
		$schema   = $client->fetch_schema();

		if ( is_wp_error( $schema ) ) {
			// Nothing below can be answered without the base, and guessing would be worse than
			// saying so: every column would look like one to create.
			$refusals[] = self::finding( 'schema_unreadable', '', $schema->get_error_message() );

			return self::answer( $refusals, $warnings );
		}

		$reports  = isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '';
		$students = isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '';
		$columns  = isset( $schema[ $reports ]['columns'] ) ? (array) $schema[ $reports ]['columns'] : array();
		$create   = array();
		$ready    = array();
		$detail   = array();

		$verdicts = self::judge_columns( $definition, $columns );

		foreach ( $verdicts as $column => $verdict ) {
			if ( 'create' === $verdict ) {
				// A control that never creates a column and is not in the base is a question
				// nothing can answer, so it is refused rather than queued for creation.
				$question = isset( $definition['questions'][ $column ] ) ? $definition['questions'][ $column ] : array();
				$field    = WPCPM_Track_Columns::field( $column, (array) $question );

				if ( null === $field ) {
					$refusals[] = self::finding( 'column_uncreatable', $column, __( 'This control cannot have a column created for it, and the base does not have one by this name.', 'wpcredits-program-manager' ) );
					continue;
				}

				// The name, the type and the options: what somebody creating this column by hand
				// in Airtable needs, so a guess is never the only way to get the type right
				// (design spec 7.2, Task 9 review L8).
				$create[]          = $column;
				$detail[ $column ] = $field;
				continue;
			}

			if ( 'ok' === $verdict ) {
				$ready[] = $column;
				continue;
			}

			$refusals[] = self::finding( 'column_' . $verdict, $column, self::column_message( $verdict ) );
		}

		$now   = count( $columns );
		$after = $now + count( $create );

		if ( $after > self::FIELD_CEILING ) {
			$refusals[] = self::finding(
				'fields_ceiling',
				'',
				sprintf(
					/* translators: 1: how many columns the table would hold, 2: the limit. */
					__( 'Publishing would take this table to %1$d columns, past Airtable\'s limit of %2$d. Airtable refuses the request that crosses it and keeps every column made before, so nothing is created until the track has fewer questions or the table has fewer columns.', 'wpcredits-program-manager' ),
					$after,
					self::FIELD_CEILING
				)
			);
		} elseif ( $after > self::FIELD_WARNING ) {
			$warnings[] = self::finding(
				'fields_near_ceiling',
				'',
				sprintf(
					/* translators: 1: how many columns the table would hold, 2: the limit. */
					__( 'Publishing would take this table to %1$d columns, close to Airtable\'s limit of %2$d.', 'wpcredits-program-manager' ),
					$after,
					self::FIELD_CEILING
				)
			);
		}

		$status  = isset( $definition['status'] ) ? (string) $definition['status'] : '';
		$choices = array(
			'reports'  => self::choice_state( $status, $schema, $reports ),
			'students' => self::choice_state( $status, $schema, $students ),
		);

		foreach ( $choices as $where => $state ) {
			if ( 'near' === $state ) {
				$warnings[] = self::finding(
					'status_choice_near',
					'',
					sprintf(
						/* translators: 1: the status, 2: the table's name. */
						__( 'The "%1$s" choice on %2$s is nearly this track\'s status but not exactly: the syncs match it letter for letter, so a student on this track would be missed. Check the choice in Airtable.', 'wpcredits-program-manager' ),
						$status,
						'reports' === $where ? __( 'Students Reports', 'wpcredits-program-manager' ) : __( 'Students', 'wpcredits-program-manager' )
					)
				);
			}
		}

		if ( '' !== (string) ( isset( $definition['course_url'] ) ? $definition['course_url'] : '' ) && ! self::course_answers( (string) $definition['course_url'] ) ) {
			$warnings[] = self::finding(
				'course_unreachable',
				'',
				__( 'The Learn course did not answer. The link still publishes: it is shown to students, and a course that is private or moved is worth checking.', 'wpcredits-program-manager' )
			);
		}

		return self::answer(
			$refusals,
			$warnings,
			array(
				'columns'     => array(
					'create' => $create,
					'ready'  => $ready,
					'detail' => $detail,
				),
				'choices'     => $choices,
				'fields'      => array(
					'now'   => $now,
					'after' => $after,
				),
				'adds_status' => 'builtin' !== WPCPM_Track_Store::source( $post_id ),
			)
		);
	}

	/**
	 * Publish a track: create the columns it needs, then put it live.
	 *
	 * One run at a time. Each column that lands is recorded on the post, so a run stopped by
	 * Airtable can be pressed again and starts at the first step not recorded (decision 18). A
	 * name already taken is read again rather than treated as a failure: somebody making the
	 * column by hand is the documented way to work without a schema token (7.2 step 1).
	 *
	 * @param int $post_id The track.
	 * @param int $user_id Who pressed Publish, for the log; 0 for the current user.
	 * @return array|WP_Error `created` and `published`, or why nothing more was done.
	 */
	public static function run( $post_id, $user_id = 0 ) {
		$post_id = (int) $post_id;
		$flight  = self::preflight( $post_id );

		if ( ! $flight['ready'] ) {
			$first = isset( $flight['refusals'][0]['message'] ) ? (string) $flight['refusals'][0]['message'] : '';

			return new WP_Error( 'wpcpm_track_preflight', $first, array( 'refusals' => $flight['refusals'] ) );
		}

		// Not a diff against `$landed`: `create` already reflects a schema read taken moments ago,
		// so a column that really landed is already `ok` and not in it. Subtracting the post's own
		// record on top would be a no-op except in the one case it gets wrong - a column the record
		// claims landed that the base no longer has - where it would skip recreating it (final
		// review, finding 1). `$landed` still seeds the created list below: it is the log's and the
		// count's record of what this run and any before it made, not what decides what is pending.
		$landed  = self::landed( $post_id );
		$pending = $flight['columns']['create'];

		// Without the token the site cannot make a column, so it says which ones to make instead
		// of failing halfway. Publishing waits for the preflight to find them (7.2).
		if ( array() !== $pending && ! WPCPM_Settings::has_schema_token() ) {
			return new WP_Error(
				'wpcpm_track_columns_by_hand',
				__( 'This track needs columns the base does not have, and no schema token is configured. Create them in Airtable from the list on this screen, then publish again.', 'wpcredits-program-manager' ),
				array( 'columns' => $pending )
			);
		}

		if ( ! self::acquire_lock() ) {
			return new WP_Error( 'wpcpm_track_publish_running', __( 'Another track is being published right now. Wait for that to finish and try again.', 'wpcredits-program-manager' ) );
		}

		$settings   = WPCPM_Settings::get();
		$table      = isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '';
		$client     = new WPCPM_Airtable();
		$definition = WPCPM_Track_Store::get( $post_id );
		$questions  = is_array( $definition ) && isset( $definition['questions'] ) ? (array) $definition['questions'] : array();

		foreach ( $pending as $column ) {
			$field = WPCPM_Track_Columns::field( $column, (array) ( $questions[ $column ] ?? array() ) );

			if ( null === $field ) {
				continue;
			}

			$made = $client->create_field( $table, $field );

			if ( is_wp_error( $made ) ) {
				if ( 'wpcpm_airtable_field_exists' !== $made->get_error_code() ) {
					self::record( $post_id, $landed );
					delete_option( self::OPT_LOCK );

					return $made;
				}

				// Taken. Read the base again: the same name on a column of the right type is one
				// somebody made by hand, and it counts as landed. Of the wrong type it is a
				// conflict, because writing to it would put the answer in the wrong shape.
				$again = $client->fetch_schema();

				if ( is_wp_error( $again ) ) {
					self::record( $post_id, $landed );
					delete_option( self::OPT_LOCK );

					return $again;
				}

				$there   = isset( $again[ $table ]['columns'] ) ? (array) $again[ $table ]['columns'] : array();
				$verdict = WPCPM_Track_Columns::judge( $column, (array) ( $questions[ $column ] ?? array() ), $there );

				if ( 'ok' !== $verdict ) {
					self::record( $post_id, $landed );
					delete_option( self::OPT_LOCK );

					return new WP_Error(
						'wpcpm_track_column_conflict',
						sprintf(
							/* translators: %s: column name. */
							__( 'Airtable already has a column named "%s", and it is not the type this question needs. Rename one of them in Airtable, or change the question.', 'wpcredits-program-manager' ),
							$column
						),
						array( 'column' => $column )
					);
				}
			}

			$landed[] = $column;
		}

		self::record( $post_id, $landed );

		$published = WPCPM_Track_Store::publish( $post_id, $user_id );

		if ( is_wp_error( $published ) ) {
			delete_option( self::OPT_LOCK );

			return $published;
		}

		if ( array() !== $landed ) {
			WPCPM_Track_Store::log( $post_id, 'columns', $user_id, array( 'columns' => $landed ) );
		}

		delete_post_meta( $post_id, self::META_RUN );
		delete_option( self::OPT_LOCK );

		return array(
			'created'   => $landed,
			'published' => true,
		);
	}

	/**
	 * Claim the right to run a publish: `add_option()`'s test-and-set, with a stale takeover.
	 *
	 * The house pattern (`WPCPM_Duplicates_Scan::acquire_lock()` and the four syncs): a fresh
	 * claim wins outright, a held claim younger than `LOCK_TIMEOUT` refuses, and one older than
	 * that is taken over rather than left to block every track forever (final review, finding 2).
	 *
	 * @return bool Whether this request now holds the lock.
	 */
	private static function acquire_lock() {
		if ( add_option( self::OPT_LOCK, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( self::OPT_LOCK );

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		update_option( self::OPT_LOCK, time(), false );

		return true;
	}

	/**
	 * What the site cannot do, with the exact values to use.
	 *
	 * @param int $post_id The track.
	 * @return array One entry per item of `CHECKLIST`, each `label`, `detail`, `ticked`, `by`, `at`.
	 */
	public static function checklist( $post_id ) {
		$post_id    = (int) $post_id;
		$definition = WPCPM_Track_Store::published( $post_id );

		if ( ! is_array( $definition ) ) {
			$definition = WPCPM_Track_Store::get( $post_id );
		}

		$status = ( is_array( $definition ) && isset( $definition['status'] ) ) ? (string) $definition['status'] : '';
		$ticks  = self::ticks( $post_id );
		$list   = array();

		$labels = array(
			'automation' => array(
				__( 'Add the status to the reports automation', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: the track's status. */
					__( 'In Airtable, add "%s" to the condition of the automation named "Add students to Students Reports and Feedback". Without it, no student on this track gets a report row, and their Student Report Card stays empty.', 'wpcredits-program-manager' ),
					$status
				),
			),
			'welcome'    => array(
				__( 'Create the welcome email automation', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: the track's status. */
					__( 'Each of the four tracks has one. Copy an existing welcome email automation and set its condition to "%s".', 'wpcredits-program-manager' ),
					$status
				),
			),
			'choices'    => array(
				__( 'Add the two Status choices', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: the track's status. */
					__( 'Add "%s" as a choice of the Status column on both Students Reports and Students. No token can add a choice to a single select, so this one is always by hand.', 'wpcredits-program-manager' ),
					$status
				),
			),
		);

		foreach ( self::CHECKLIST as $item ) {
			$list[ $item ] = array(
				'label'  => $labels[ $item ][0],
				'detail' => $labels[ $item ][1],
				'ticked' => isset( $ticks[ $item ] ),
				'by'     => isset( $ticks[ $item ]['by'] ) ? (int) $ticks[ $item ]['by'] : 0,
				'at'     => isset( $ticks[ $item ]['at'] ) ? (int) $ticks[ $item ]['at'] : 0,
			);
		}

		return $list;
	}

	/**
	 * Tick one item, recording who and when.
	 *
	 * Ticking the reports automation is what puts this track's status into
	 * `WPCPM_Tracks::confirmed_automation_statuses()`, which the institution import and institution
	 * create read (the design's decision 10). The compiled row carries that flag, so the tick
	 * recompiles: without it the gate would stay shut until something unrelated published.
	 *
	 * @param int    $post_id The track.
	 * @param string $item    One of `CHECKLIST`.
	 * @param int    $user_id Who ticked it; 0 for the current user.
	 * @return true|WP_Error
	 */
	public static function tick( $post_id, $item, $user_id = 0 ) {
		return self::set_tick( $post_id, $item, $user_id, true );
	}

	/**
	 * Take a tick back, for an item somebody ticked by mistake.
	 *
	 * @param int    $post_id The track.
	 * @param string $item    One of `CHECKLIST`.
	 * @param int    $user_id Who unticked it; 0 for the current user.
	 * @return true|WP_Error
	 */
	public static function untick( $post_id, $item, $user_id = 0 ) {
		return self::set_tick( $post_id, $item, $user_id, false );
	}

	/**
	 * Take a track off the live site, once nobody is on it.
	 *
	 * Refused while any synced student holds the status, with the count, because unpublishing
	 * compiles the track out and those students would open a page with no form on it. Airtable is
	 * not touched and the status stays in "Currently mentoring": taking it out is a manager's
	 * decision in Settings, since `revoke_departed()` would take the Student role from everybody
	 * on the track (7.5).
	 *
	 * @param int $post_id The track.
	 * @param int $user_id Who pressed it, for the log; 0 for the current user.
	 * @return int|WP_Error The post ID, or why nothing was done.
	 */
	public static function take_down( $post_id, $user_id = 0 ) {
		$post_id    = (int) $post_id;
		$definition = WPCPM_Track_Store::get( $post_id );

		if ( ! is_array( $definition ) ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		$status = isset( $definition['status'] ) ? (string) $definition['status'] : '';
		$held   = WPCPM_Students_Sync::count_on_status( $status );

		if ( $held > 0 ) {
			return new WP_Error(
				'wpcpm_track_in_use',
				sprintf(
					/* translators: 1: how many students, 2: the track's status. */
					_n(
						'%1$d student is on this track in Airtable, and unpublishing it would leave their Student Report Card with no form on it. Move them off "%2$s" first.',
						'%1$d students are on this track in Airtable, and unpublishing it would leave their Student Report Cards with no form on them. Move them off "%2$s" first.',
						$held,
						'wpcredits-program-manager'
					),
					$held,
					$status
				),
				array( 'students' => $held )
			);
		}

		return WPCPM_Track_Store::unpublish( $post_id, $user_id );
	}

	/**
	 * Check a published track against the base, changing nothing.
	 *
	 * A column renamed in the base otherwise surfaces as a silently empty answer: the form keeps
	 * writing to a name nothing reads any more (7.4).
	 *
	 * @param int $post_id The track.
	 * @return array|WP_Error `columns` with `missing` and `wrong`, and `choices`.
	 */
	public static function verify( $post_id ) {
		$post_id    = (int) $post_id;
		$definition = WPCPM_Track_Store::published( $post_id );

		if ( ! is_array( $definition ) ) {
			return new WP_Error( 'wpcpm_track_not_published', __( 'That track has never been published, so there is nothing live to check against Airtable.', 'wpcredits-program-manager' ) );
		}

		$settings = WPCPM_Settings::get();
		$client   = new WPCPM_Airtable();
		$schema   = $client->fetch_schema();

		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		$reports  = isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '';
		$students = isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '';
		$columns  = isset( $schema[ $reports ]['columns'] ) ? (array) $schema[ $reports ]['columns'] : array();
		$missing  = array();
		$wrong    = array();

		$verdicts = self::judge_columns( $definition, $columns );

		foreach ( $verdicts as $column => $verdict ) {
			if ( 'create' === $verdict ) {
				$missing[] = $column;
				continue;
			}

			if ( 'ok' !== $verdict ) {
				$wrong[] = $column;
			}
		}

		$status = isset( $definition['status'] ) ? (string) $definition['status'] : '';

		return array(
			'columns' => array(
				'missing' => $missing,
				'wrong'   => $wrong,
			),
			'choices' => array(
				'reports'  => self::choice_state( $status, $schema, $reports ),
				'students' => self::choice_state( $status, $schema, $students ),
			),
		);
	}

	/**
	 * Record or clear one tick.
	 *
	 * @param int    $post_id The track.
	 * @param string $item    One of `CHECKLIST`.
	 * @param int    $user_id Who did it; 0 for the current user.
	 * @param bool   $on      Whether it is now ticked.
	 * @return true|WP_Error
	 */
	private static function set_tick( $post_id, $item, $user_id, $on ) {
		$post_id = (int) $post_id;
		$item    = (string) $item;

		if ( ! in_array( $item, self::CHECKLIST, true ) ) {
			return new WP_Error( 'wpcpm_track_checklist_item', __( 'That is not one of the checklist items.', 'wpcredits-program-manager' ) );
		}

		// Every other entry point checks this, through `preflight()`'s `no_definition` refusal or
		// `track_post()` behind `WPCPM_Track_Store::save()`; this one wrote post meta to whatever
		// ID it was handed (final review, finding 3).
		if ( ! is_array( WPCPM_Track_Store::get( $post_id ) ) ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$ticks   = self::ticks( $post_id );

		if ( $on ) {
			$ticks[ $item ] = array(
				'by' => $user_id,
				'at' => time(),
			);
		} else {
			unset( $ticks[ $item ] );
		}

		update_post_meta( $post_id, self::META_CHECKLIST, $ticks );

		// Item 1 is the one the site reads: the compiled row carries the flag that opens the
		// institution gate, so the tick has to recompile for it to mean anything today.
		if ( 'automation' === $item ) {
			if ( $on ) {
				update_post_meta( $post_id, WPCPM_Track_Store::META_AUTOMATION, '1' );
			} else {
				delete_post_meta( $post_id, WPCPM_Track_Store::META_AUTOMATION );
			}

			WPCPM_Track_Store::compile();
		}

		WPCPM_Track_Store::log( $post_id, ( $on ? 'tick-' : 'untick-' ) . $item, $user_id );

		return true;
	}

	/**
	 * The ticks recorded on a track.
	 *
	 * @param int $post_id The track.
	 * @return array
	 */
	private static function ticks( $post_id ) {
		$ticks = get_post_meta( (int) $post_id, self::META_CHECKLIST, true );

		return is_array( $ticks ) ? $ticks : array();
	}

	/**
	 * How each column in the definition is judged against the base.
	 *
	 * @param array $definition The track definition.
	 * @param array $columns    The base table's columns, from the schema.
	 * @return array Map of column names to verdict strings: `ok`, `create`, `computed`, `type_mismatch`, `foreign_link`.
	 */
	private static function judge_columns( array $definition, array $columns ) {
		$verdicts = array();

		foreach ( (array) ( isset( $definition['questions'] ) ? $definition['questions'] : array() ) as $column => $question ) {
			$column              = (string) $column;
			$verdicts[ $column ] = WPCPM_Track_Columns::judge( $column, (array) $question, $columns );
		}

		return $verdicts;
	}

	/**
	 * The columns an earlier run of this track already created.
	 *
	 * @param int $post_id The track.
	 * @return string[]
	 */
	private static function landed( $post_id ) {
		$run = get_post_meta( (int) $post_id, self::META_RUN, true );

		return ( is_array( $run ) && isset( $run['columns'] ) ) ? array_values( (array) $run['columns'] ) : array();
	}

	/**
	 * Record what has landed, so the next press resumes here.
	 *
	 * @param int   $post_id The track.
	 * @param array $columns The columns created so far.
	 * @return void
	 */
	private static function record( $post_id, array $columns ) {
		if ( array() === $columns ) {
			return;
		}

		update_post_meta( (int) $post_id, self::META_RUN, array( 'columns' => array_values( $columns ) ) );
	}

	/**
	 * Whether a status is one of a table's `Status` choices, nearly one, or absent.
	 *
	 * @param string $status The track's status.
	 * @param array  $schema The base schema.
	 * @param string $table  Table ID.
	 * @return string `ok`, `near` or `missing`.
	 */
	private static function choice_state( $status, array $schema, $table ) {
		$options = isset( $schema[ $table ]['columns'][ self::STATUS_COLUMN ]['options']['choices'] )
			? (array) $schema[ $table ]['columns'][ self::STATUS_COLUMN ]['options']['choices']
			: array();

		$near = false;

		foreach ( $options as $choice ) {
			$name = isset( $choice['name'] ) ? (string) $choice['name'] : '';

			if ( $name === $status ) {
				return 'ok';
			}

			if ( self::loosen( $name ) === self::loosen( $status ) ) {
				$near = true;
			}
		}

		return $near ? 'near' : 'missing';
	}

	/**
	 * A status with the differences that make a sync miss silently taken out of it.
	 *
	 * Case, runs of space, and the two apostrophes a keyboard produces: the syncs match a status
	 * letter for letter, so a choice differing by any of these matches nobody (7.1).
	 *
	 * @param string $value The status or choice.
	 * @return string
	 */
	private static function loosen( $value ) {
		$value = str_replace( array( "\xe2\x80\x99", '`' ), "'", (string) $value );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return trim( strtolower( (string) $value ) );
	}

	/**
	 * Whether the Learn course answers, asked at most once per `COURSE_CACHE_TTL`.
	 *
	 * A warning at worst: the link is shown to students and publishing never depends on it, so a
	 * slow or private course must not stop a track going live (7.1). The answer is cached behind
	 * a transient keyed by the URL rather than asked fresh on every preflight, because a course's
	 * reachability does not change minute to minute (Task 9 review, M4) - unlike the schema read
	 * above, which is never cached this way.
	 *
	 * @param string $url The course URL.
	 * @return bool
	 */
	private static function course_answers( $url ) {
		$key    = 'wpcpm_track_course_' . md5( $url );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 3,
			)
		);

		$answers = false;

		if ( ! is_wp_error( $response ) ) {
			$code    = (int) wp_remote_retrieve_response_code( $response );
			$answers = $code >= 200 && $code < 400;
		}

		set_transient( $key, $answers ? '1' : '0', self::COURSE_CACHE_TTL );

		return $answers;
	}

	/**
	 * Why a column already in the base cannot be used.
	 *
	 * @param string $verdict What `WPCPM_Track_Columns::judge()` said.
	 * @return string
	 */
	private static function column_message( $verdict ) {
		if ( 'computed' === $verdict ) {
			return __( 'Airtable works this column out for itself, so nothing can be written to it: a student\'s answer would be thrown away.', 'wpcredits-program-manager' );
		}

		if ( 'foreign_link' === $verdict ) {
			return __( 'This column links to another table, and a link carries a reverse column into it. Main Contribution Team is the one link a question may use.', 'wpcredits-program-manager' );
		}

		return __( 'The column in the base is a different type from the one this question needs, so the answer would arrive in the wrong shape.', 'wpcredits-program-manager' );
	}

	/**
	 * Why a built-in track's definition does not match its PHP.
	 *
	 * @param string[] $differences The fields that differ: `form`, `label`, `course`, `course_id`,
	 *                              or `hours`.
	 * @return string
	 */
	private static function builtin_diff_message( array $differences ) {
		$labels = array(
			'form'      => __( 'form', 'wpcredits-program-manager' ),
			'label'     => __( 'title', 'wpcredits-program-manager' ),
			'course'    => __( 'course URL', 'wpcredits-program-manager' ),
			'course_id' => __( 'Learn course ID', 'wpcredits-program-manager' ),
			'hours'     => __( 'hours target', 'wpcredits-program-manager' ),
		);

		$names = array();

		foreach ( $differences as $diff ) {
			if ( isset( $labels[ $diff ] ) ) {
				$names[] = $labels[ $diff ];
			}
		}

		if ( empty( $names ) ) {
			return __( 'This track no longer matches its hand-written form.', 'wpcredits-program-manager' );
		}

		/* translators: %s is a comma-separated list of field names that differ */
		$message = __( 'This track no longer matches its hand-written form: %s differ.', 'wpcredits-program-manager' );

		return sprintf( $message, implode( ', ', $names ) );
	}

	/**
	 * One finding.
	 *
	 * @param string $code    Its code.
	 * @param string $column  The column it is about, or an empty string.
	 * @param string $message What a person reads.
	 * @return array
	 */
	private static function finding( $code, $column, $message ) {
		return array(
			'code'    => (string) $code,
			'column'  => (string) $column,
			'message' => (string) $message,
		);
	}

	/**
	 * The preflight's answer, with the parts a caller always gets.
	 *
	 * @param array $refusals Findings that stop publishing.
	 * @param array $warnings Findings that do not.
	 * @param array $rest     What the run would do, when it got far enough to work it out.
	 * @return array
	 */
	private static function answer( array $refusals, array $warnings, array $rest = array() ) {
		return array_merge(
			array(
				'refusals'    => $refusals,
				'warnings'    => $warnings,
				'columns'     => array(
					'create' => array(),
					'ready'  => array(),
					'detail' => array(),
				),
				'choices'     => array(
					'reports'  => 'missing',
					'students' => 'missing',
				),
				'fields'      => array(
					'now'   => 0,
					'after' => 0,
				),
				'adds_status' => true,
				'ready'       => array() === $refusals,
			),
			$rest
		);
	}
}
