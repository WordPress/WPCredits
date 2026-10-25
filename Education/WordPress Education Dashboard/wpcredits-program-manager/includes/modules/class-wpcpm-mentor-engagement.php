<?php
/**
 * Mentor engagement: how each mentor uses the site, read for the Engagement tab, the
 * Administrator Dashboard's card and `wp wpcredits mentor_engagement` (1.122.26).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One reader and one set of rules, so the tab, the card and the command cannot disagree.
 *
 * The rules are the ones `analyze.py` used for the owner's question of 15 October 2026 (spec, section
 * 2): an active mentor is one Airtable holds as Active who is not an Administrator; a held call is
 * noted when its mentor wrote a note about one of its students, dated no earlier than a day before the
 * call; a program action is a note, a saved schedule, a group session created or a call the mentor
 * canceled, never a student's booking; the call figures count every call on the site.
 *
 * `compute()` holds every rule and reads nothing, so a suite pins each one on plain arrays; `read()`
 * reads the site once (Task 4). Counts and dates only: never a note's text, a call's topic or a
 * student's name.
 */
final class WPCPM_Mentor_Engagement {
	/** The views of the Engagement tab, also `--view` of the command. */
	const VIEW_ALL      = 'all';
	const VIEW_ACTIVE   = 'active';
	const VIEW_STUDENTS = 'students';
	const VIEW_NEVER    = 'never';
	const VIEW_QUIET    = 'quiet';
	const VIEW_IDLE     = 'idle';
	const VIEW_NO_HOURS = 'no-hours';
	const VIEW_STAFF    = 'staff';

	/** What a sort may name, column key => row key. */
	const SORTS = array(
		'mentor'       => 'name',
		'students_now' => 'students_now',
		'last_visit'   => 'last_visit',
		'last_sign_in' => 'last_sign_in',
		'notes'        => 'notes',
		'calls'        => 'calls_held',
	);

	/** The columns of the command's per-mentor output, in order. */
	const EXPORT_FIELDS = array( 'id', 'name', 'active', 'administrator', 'students_now', 'students_past', 'last_visit', 'last_sign_in', 'schedule', 'notes', 'last_note', 'calls_held', 'calls_upcoming', 'calls_noted', 'sessions_held', 'sessions_upcoming' );

	/**
	 * Every row and figure, from the site's facts.
	 *
	 * @param array[] $accounts The mentor accounts (see the class's suite for the shape).
	 * @param array[] $notes    The notes.
	 * @param array[] $calls    The calls and group sessions.
	 * @param int     $now      Unix time the figures are as of.
	 * @return array{rows: array[], summary: array<string, int>}
	 */
	public static function compute( array $accounts, array $notes, array $calls, $now ) {
		$now            = (int) $now;
		$notes_by       = array();
		$noted_sessions = array();
		$calls_by       = array();

		foreach ( $notes as $note ) {
			$notes_by[ (int) $note['author'] ][] = $note;

			if ( (int) $note['session'] > 0 ) {
				$noted_sessions[ (int) $note['session'] ] = true;
			}
		}

		foreach ( $calls as $call ) {
			$calls_by[ (int) $call['mentor'] ][] = $call;
		}

		$rows = array();

		foreach ( $accounts as $account ) {
			$id     = (int) $account['id'];
			$rows[] = self::row(
				$account,
				isset( $notes_by[ $id ] ) ? $notes_by[ $id ] : array(),
				isset( $calls_by[ $id ] ) ? $calls_by[ $id ] : array(),
				$noted_sessions,
				$now
			);
		}

		return array(
			'rows'    => $rows,
			'summary' => self::summary( $rows, $calls, $notes_by, $noted_sessions, $now ),
		);
	}

	/**
	 * What `read()` returns in place of the site, while a suite has set it.
	 *
	 * @var array|null
	 */
	private static $input = null;

	/**
	 * Every row and figure as of now (or `$now`), read from the site once.
	 *
	 * @param int $now Unix time, or 0 for now.
	 * @return array{rows: array[], summary: array<string, int>}
	 */
	public static function report( $now = 0 ) {
		$input = self::read();

		return self::compute( $input['accounts'], $input['notes'], $input['calls'], (int) $now > 0 ? (int) $now : time() );
	}

	/**
	 * The site's facts, as `compute()` takes them.
	 *
	 * @return array{accounts: array[], notes: array[], calls: array[]}
	 */
	public static function read() {
		if ( null !== self::$input ) {
			return self::$input;
		}

		$posts = self::read_posts();

		return array(
			'accounts' => self::read_accounts(),
			'notes'    => $posts['notes'],
			'calls'    => $posts['calls'],
		);
	}

	/**
	 * Read this instead of the site. Test-only: nothing in the plugin calls it.
	 *
	 * @param array|null $input `accounts`, `notes`, `calls`; null to read the site again.
	 */
	public static function for_tests( $input ) {
		self::$input = is_array( $input ) ? $input : null;
	}

	/**
	 * The mentor accounts: the Mentor role, and any account holding a mentor record, once each,
	 * their users and meta read in one go. The IDs go through `WPCPM_Roles::id_of()`: on this
	 * program's site `get_users()` hands back row objects whatever `fields` asks for.
	 *
	 * @return array[]
	 */
	private static function read_accounts() {
		$ids = array_merge(
			(array) get_users(
				array(
					'role'   => WPCPM_Roles::ROLE_MENTOR,
					'fields' => 'ID',
					'number' => -1,
				)
			),
			(array) get_users(
				array(
					'meta_query' => array(
						array(
							'key'     => WPCPM_Mentors_Sync::META_RECORD_ID,
							'value'   => '',
							'compare' => '!=',
						),
					),
					'fields'     => 'ID',
					'number'     => -1,
				)
			)
		);
		$ids = array_values( array_unique( array_filter( array_map( array( 'WPCPM_Roles', 'id_of' ), $ids ) ) ) );
		sort( $ids );

		if ( empty( $ids ) ) {
			return array();
		}

		cache_users( $ids );

		$accounts = array();

		foreach ( $ids as $id ) {
			$user = get_userdata( $id );

			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$stored   = get_user_meta( $id, WPCPM_Mentor_Availability::META, true );
			$schedule = WPCPM_Mentor_Availability::get( $id );

			$accounts[] = array(
				'id'             => $id,
				'name'           => (string) $user->display_name,
				'login'          => (string) $user->user_login,
				'email'          => (string) $user->user_email,
				'roles'          => array_values( (array) $user->roles ),
				'active'         => 1 === self::int( get_user_meta( $id, WPCPM_Mentors_Sync::META_ACTIVE, true ) ),
				'students_now'   => self::int( get_user_meta( $id, WPCPM_Mentors_Sync::META_COUNT, true ) ),
				'students_past'  => self::int( get_user_meta( $id, WPCPM_Mentors_Sync::META_PAST_COUNT, true ) ),
				'last_sign_in'   => self::int( get_user_meta( $id, WPCPM_Sign_Ins::META_SIGN_IN, true ) ),
				'last_visit'     => self::int( get_user_meta( $id, WPCPM_Sign_Ins::META_VISIT, true ) ),
				'schedule_saved' => is_array( $stored ),
				'weekly_hours'   => WPCPM_Mentor_Availability::is_published( $id ),
				'meeting_link'   => '' !== (string) $schedule['meeting_url'],
			);
		}

		return $accounts;
	}

	/**
	 * The notes and the calls: the posts, then their meta, in two statements and joined here. No
	 * GROUP_CONCAT, which MySQL cuts at 1,024 characters and a full group session's students reach.
	 * Never the posts' content or title.
	 *
	 * @return array{notes: array[], calls: array[]}
	 */
	private static function read_posts() {
		global $wpdb;

		$types = array( WPCPM_Mentor_Notes::POST_TYPE, WPCPM_Mentor_Calls::POST_TYPE );
		$keys  = array( WPCPM_Mentor_Calls::META_MENTOR, WPCPM_Mentor_Calls::META_START, WPCPM_Mentor_Calls::META_CAPACITY, WPCPM_Mentor_Calls::META_CANCELLED_BY, WPCPM_Mentor_Calls::META_RECORD, WPCPM_Mentor_Notes::META_STUDENT, WPCPM_Mentor_Notes::META_SESSION );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Two reads of a few hundred rows, on three screens an Administrator opens; nothing to cache across requests.
		$posts = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, post_type, post_status, post_author, post_date_gmt FROM {$wpdb->posts} WHERE post_type IN (%s, %s) AND post_status <> %s ORDER BY ID", $types[0], $types[1], 'auto-draft' ),
			ARRAY_A
		);
		$meta  = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Ten placeholders, filled from one array: the two post types, 'auto-draft' and the seven meta keys.
				"SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN (%s, %s) AND p.post_status <> %s AND pm.meta_key IN (%s, %s, %s, %s, %s, %s, %s) ORDER BY pm.meta_id",
				array_merge( $types, array( 'auto-draft' ), $keys )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$by = array();

		foreach ( (array) $meta as $m ) {
			$by[ (int) $m['post_id'] ][ (string) $m['meta_key'] ][] = (string) $m['meta_value'];
		}

		$notes = array();
		$calls = array();

		foreach ( (array) $posts as $p ) {
			$id    = (int) $p['ID'];
			$m     = isset( $by[ $id ] ) ? $by[ $id ] : array();
			$first = static function ( $key ) use ( $m ) {
				return isset( $m[ $key ][0] ) ? $m[ $key ][0] : '';
			};

			if ( WPCPM_Mentor_Notes::POST_TYPE === $p['post_type'] ) {
				$notes[] = array(
					'id'      => $id,
					'author'  => (int) $p['post_author'],
					'created' => (int) strtotime( $p['post_date_gmt'] . ' UTC' ),
					'records' => isset( $m[ WPCPM_Mentor_Notes::META_STUDENT ] ) ? $m[ WPCPM_Mentor_Notes::META_STUDENT ] : array(),
					'session' => self::int( $first( WPCPM_Mentor_Notes::META_SESSION ) ),
				);
				continue;
			}

			$calls[] = array(
				'id'           => $id,
				'author'       => (int) $p['post_author'],
				'mentor'       => self::int( $first( WPCPM_Mentor_Calls::META_MENTOR ) ),
				'start'        => self::int( $first( WPCPM_Mentor_Calls::META_START ) ),
				'cancelled_by' => self::int( $first( WPCPM_Mentor_Calls::META_CANCELLED_BY ) ),
				'status'       => (string) $p['post_status'],
				'group'        => '' !== $first( WPCPM_Mentor_Calls::META_CAPACITY ),
				'records'      => isset( $m[ WPCPM_Mentor_Calls::META_RECORD ] ) ? $m[ WPCPM_Mentor_Calls::META_RECORD ] : array(),
			);
		}

		return array(
			'notes' => $notes,
			'calls' => $calls,
		);
	}

	/**
	 * The tab's views, slug => label, in the order the tab prints them.
	 *
	 * @return array<string, string>
	 */
	public static function views() {
		return array(
			self::VIEW_ALL      => __( 'All', 'wpcredits-program-manager' ),
			self::VIEW_ACTIVE   => __( 'Active', 'wpcredits-program-manager' ),
			self::VIEW_STUDENTS => __( 'With students now', 'wpcredits-program-manager' ),
			self::VIEW_NEVER    => __( 'Never signed in', 'wpcredits-program-manager' ),
			self::VIEW_QUIET    => __( 'Not seen in 30 days', 'wpcredits-program-manager' ),
			self::VIEW_IDLE     => __( 'No program action', 'wpcredits-program-manager' ),
			self::VIEW_NO_HOURS => __( 'Students, no weekly hours', 'wpcredits-program-manager' ),
			self::VIEW_STAFF    => __( 'Administrators', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Whether a row is in a view. Every follow-up view counts the active mentors only, so its count
	 * is the figure of the same name; the Administrators view counts the active Administrators who
	 * mentor, so its count is the figure `apart()` says; a view the tab does not have is All.
	 *
	 * @param array  $row  A row.
	 * @param string $view A view.
	 * @return bool
	 */
	public static function in_view( array $row, $view ) {
		$active = $row['active'] && ! $row['staff'];

		switch ( (string) $view ) {
			case self::VIEW_ACTIVE:
				return $active;
			case self::VIEW_STUDENTS:
				return $active && $row['students_now'] > 0;
			case self::VIEW_NEVER:
				return $active && 0 === $row['last_seen'];
			case self::VIEW_QUIET:
				return $active && $row['last_seen'] > 0 && ! $row['seen_30'];
			case self::VIEW_IDLE:
				return $active && ! $row['acted'];
			case self::VIEW_NO_HOURS:
				return $active && $row['students_now'] > 0 && ! $row['weekly_hours'];
			case self::VIEW_STAFF:
				return $row['active'] && $row['staff'];
			default:
				return true;
		}
	}

	/**
	 * The rows in a column's order. With no column, or one that cannot sort, students now, most
	 * first. A number column opens most first, the name A to Z; ties go by name, then by ID.
	 *
	 * @param array[] $rows    Rows.
	 * @param string  $orderby A key of SORTS.
	 * @param string  $order   `asc` or `desc`.
	 * @return array[]
	 */
	public static function sorted( array $rows, $orderby = '', $order = '' ) {
		if ( ! isset( self::SORTS[ $orderby ] ) ) {
			$orderby = 'students_now';
			$order   = 'desc';
		}

		$key   = self::SORTS[ $orderby ];
		$order = strtolower( (string) $order );
		$dir   = 'asc' === $order ? 1 : ( 'desc' === $order ? -1 : ( 'name' === $key ? 1 : -1 ) );

		usort(
			$rows,
			static function ( $a, $b ) use ( $key, $dir ) {
				$cmp = 'name' === $key ? strcasecmp( $a['name'], $b['name'] ) : ( $a[ $key ] <=> $b[ $key ] );

				if ( 0 !== $cmp ) {
					return $dir * $cmp;
				}

				$cmp = strcasecmp( $a['name'], $b['name'] );

				return 0 !== $cmp ? $cmp : ( $a['id'] <=> $b['id'] );
			}
		);

		return $rows;
	}

	/**
	 * Whether a row matches a search: its name, username or email, in any case.
	 *
	 * @param array  $row    A row.
	 * @param string $search The words searched.
	 * @return bool
	 */
	public static function matches( array $row, $search ) {
		$search = trim( (string) $search );

		if ( '' === $search ) {
			return true;
		}

		foreach ( array( 'name', 'login', 'email' ) as $field ) {
			if ( false !== stripos( (string) $row[ $field ], $search ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The headline figures as the card's tiles and the tab's list say them.
	 *
	 * @param array $summary The summary.
	 * @return array[] `name`, `n`, `line`.
	 */
	public static function tiles( array $summary ) {
		$sessions_held = (int) $summary['sessions_held'];

		/* translators: %s: a number of group sessions held. */
		$sessions = sprintf( _n( '%s group session held', '%s group sessions held', $sessions_held, 'wpcredits-program-manager' ), number_format_i18n( $sessions_held ) );

		return array(
			array(
				'name' => __( 'Active mentors', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['active'],
				'line' => __( 'not counting Administrators', 'wpcredits-program-manager' ),
			),
			array(
				'name' => __( 'Seen in the last 30 days', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['seen_30'],
				/* translators: %s: a number of mentors. */
				'line' => sprintf( __( '%s in the last 7 days', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['seen_7'] ) ),
			),
			array(
				'name' => __( 'Took a program action', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['acted'],
				/* translators: %s: a number of mentors. */
				'line' => sprintf( __( '%s signed in without one', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['idle'] ) ),
			),
			array(
				'name' => __( 'Never signed in', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['never'],
				'line' => '',
			),
			array(
				'name' => __( 'With students now', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['with_students'],
				/* translators: 1: a number of mentors with weekly hours, 2: a number of mentors who wrote notes. */
				'line' => sprintf( __( '%1$s with weekly hours, %2$s wrote notes', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['with_students_hours'] ), number_format_i18n( (int) $summary['with_students_notes'] ) ),
			),
			array(
				'name' => __( 'Calls held', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['calls_held'],
				/* translators: 1: calls with a note, 2: calls to come, 3: the group sessions held, with their noun (for example "2 group sessions held"). */
				'line' => sprintf( __( '%1$s with a note, %2$s upcoming; %3$s', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['calls_noted'] ), number_format_i18n( (int) $summary['calls_upcoming'] ), $sessions ),
			),
		);
	}

	/**
	 * The sentence under the tiles: the Administrators who mentor, counted apart.
	 *
	 * @param array $summary The summary.
	 * @return string
	 */
	public static function apart( array $summary ) {
		/* translators: %s: a number of Administrators. */
		return sprintf( __( 'Administrators who also mentor are counted apart: %s.', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['staff'] ) );
	}

	/**
	 * A row's schedule in words.
	 *
	 * @param array $row A row.
	 * @return string
	 */
	public static function schedule_label( array $row ) {
		if ( $row['weekly_hours'] ) {
			return $row['meeting_link'] ? __( 'Weekly hours, meeting link', 'wpcredits-program-manager' ) : __( 'Weekly hours, no link', 'wpcredits-program-manager' );
		}

		return $row['schedule_saved'] ? __( 'Saved, no hours', 'wpcredits-program-manager' ) : __( 'Not set', 'wpcredits-program-manager' );
	}

	/**
	 * A row as the command prints it: EXPORT_FIELDS, dates in UTC, no email.
	 *
	 * @param array $row A row.
	 * @return array<string, int|string>
	 */
	public static function export_row( array $row ) {
		$day = static function ( $t ) {
			return $t > 0 ? gmdate( 'Y-m-d H:i', (int) $t ) : '';
		};

		return array(
			'id'                => $row['id'],
			'name'              => $row['name'],
			'active'            => $row['active'] ? 'yes' : 'no',
			'administrator'     => $row['staff'] ? 'yes' : 'no',
			'students_now'      => $row['students_now'],
			'students_past'     => $row['students_past'],
			'last_visit'        => $day( $row['last_visit'] ),
			'last_sign_in'      => $day( $row['last_sign_in'] ),
			'schedule'          => self::schedule_label( $row ),
			'notes'             => $row['notes'],
			'last_note'         => $day( $row['last_note'] ),
			'calls_held'        => $row['calls_held'],
			'calls_upcoming'    => $row['calls_upcoming'],
			'calls_noted'       => $row['calls_noted'],
			'sessions_held'     => $row['sessions_held'],
			'sessions_upcoming' => $row['sessions_upcoming'],
		);
	}

	/**
	 * One mentor's row.
	 *
	 * @param array   $account        The account's facts.
	 * @param array[] $notes          The notes the mentor wrote.
	 * @param array[] $calls          The calls on the mentor's calendar.
	 * @param array   $noted_sessions Session IDs a note names.
	 * @param int     $now            Unix time.
	 * @return array
	 */
	private static function row( array $account, array $notes, array $calls, array $noted_sessions, $now ) {
		$id        = (int) $account['id'];
		$sign_in   = self::int( $account['last_sign_in'] );
		$visit     = self::int( $account['last_visit'] );
		$last_seen = max( $sign_in, $visit );

		$row = array(
			'id'                  => $id,
			'name'                => (string) $account['name'],
			'login'               => (string) $account['login'],
			'email'               => (string) $account['email'],
			'active'              => ! empty( $account['active'] ),
			'staff'               => in_array( WPCPM_Roles::ROLE_ADMIN, (array) $account['roles'], true ),
			'students_now'        => self::int( $account['students_now'] ),
			'students_past'       => self::int( $account['students_past'] ),
			'last_sign_in'        => $sign_in,
			'last_visit'          => $visit,
			'last_seen'           => $last_seen,
			'seen_7'              => $last_seen > 0 && $last_seen >= $now - 7 * DAY_IN_SECONDS,
			'seen_30'             => $last_seen > 0 && $last_seen >= $now - 30 * DAY_IN_SECONDS,
			'schedule_saved'      => ! empty( $account['schedule_saved'] ),
			'weekly_hours'        => ! empty( $account['weekly_hours'] ),
			'meeting_link'        => ! empty( $account['meeting_link'] ),
			'notes'               => count( $notes ),
			'notes_30'            => 0,
			'last_note'           => 0,
			'calls_booked'        => 0,
			'calls_held'          => 0,
			'calls_upcoming'      => 0,
			'calls_noted'         => 0,
			'cancelled_by_mentor' => 0,
			'sessions_created'    => 0,
			'sessions_held'       => 0,
			'sessions_upcoming'   => 0,
			'sessions_noted'      => 0,
		);

		foreach ( $notes as $note ) {
			$created = self::int( $note['created'] );

			if ( $created >= $now - 30 * DAY_IN_SECONDS ) {
				++$row['notes_30'];
			}

			$row['last_note'] = max( $row['last_note'], $created );
		}

		foreach ( $calls as $call ) {
			$trashed = 'trash' === (string) $call['status'];
			$when    = self::when( $call, $now );

			if ( $trashed && $id === (int) $call['cancelled_by'] ) {
				++$row['cancelled_by_mentor'];
			}

			if ( ! empty( $call['group'] ) ) {
				if ( $id === (int) $call['author'] ) {
					++$row['sessions_created'];
				}

				if ( ! $trashed && 'held' === $when ) {
					++$row['sessions_held'];

					if ( isset( $noted_sessions[ (int) $call['id'] ] ) ) {
						++$row['sessions_noted'];
					}
				} elseif ( ! $trashed && 'upcoming' === $when ) {
					++$row['sessions_upcoming'];
				}

				continue;
			}

			if ( $trashed ) {
				continue;
			}

			++$row['calls_booked'];

			if ( 'held' === $when ) {
				++$row['calls_held'];

				if ( self::noted( $call, $notes ) ) {
					++$row['calls_noted'];
				}
			} elseif ( 'upcoming' === $when ) {
				++$row['calls_upcoming'];
			}
		}

		$row['acted'] = $row['notes'] > 0 || $row['schedule_saved'] || $row['sessions_created'] > 0 || $row['cancelled_by_mentor'] > 0;

		return $row;
	}

	/**
	 * The headline figures (spec, section 2).
	 *
	 * @param array[] $rows           Every row.
	 * @param array[] $calls          Every call on the site.
	 * @param array   $notes_by       Notes by author.
	 * @param array   $noted_sessions Session IDs a note names.
	 * @param int     $now            Unix time.
	 * @return array<string, int>
	 */
	private static function summary( array $rows, array $calls, array $notes_by, array $noted_sessions, $now ) {
		$s = array_fill_keys(
			array( 'active', 'signed_in', 'seen_30', 'seen_7', 'never', 'acted', 'idle', 'with_students', 'with_students_hours', 'with_students_link', 'with_students_notes', 'with_students_seen_30', 'calls_held', 'calls_upcoming', 'calls_noted', 'sessions_held', 'sessions_upcoming', 'sessions_noted', 'staff' ),
			0
		);

		foreach ( $rows as $row ) {
			if ( ! $row['active'] ) {
				continue;
			}

			if ( $row['staff'] ) {
				++$s['staff'];
				continue;
			}

			++$s['active'];
			$s['signed_in'] += $row['last_seen'] > 0 ? 1 : 0;
			$s['never']     += 0 === $row['last_seen'] ? 1 : 0;
			$s['seen_30']   += $row['seen_30'] ? 1 : 0;
			$s['seen_7']    += $row['seen_7'] ? 1 : 0;
			$s['acted']     += $row['acted'] ? 1 : 0;
			$s['idle']      += ! $row['acted'] && $row['last_seen'] > 0 ? 1 : 0;

			if ( $row['students_now'] > 0 ) {
				++$s['with_students'];
				$s['with_students_hours']   += $row['weekly_hours'] ? 1 : 0;
				$s['with_students_link']    += $row['weekly_hours'] && $row['meeting_link'] ? 1 : 0;
				$s['with_students_notes']   += $row['notes'] > 0 ? 1 : 0;
				$s['with_students_seen_30'] += $row['seen_30'] ? 1 : 0;
			}
		}

		foreach ( $calls as $call ) {
			if ( 'trash' === (string) $call['status'] ) {
				continue;
			}

			$when = self::when( $call, $now );

			if ( ! empty( $call['group'] ) ) {
				if ( 'held' === $when ) {
					++$s['sessions_held'];
					$s['sessions_noted'] += isset( $noted_sessions[ (int) $call['id'] ] ) ? 1 : 0;
				} elseif ( 'upcoming' === $when ) {
					++$s['sessions_upcoming'];
				}

				continue;
			}

			if ( 'held' === $when ) {
				++$s['calls_held'];
				$mine              = isset( $notes_by[ (int) $call['mentor'] ] ) ? $notes_by[ (int) $call['mentor'] ] : array();
				$s['calls_noted'] += self::noted( $call, $mine ) ? 1 : 0;
			} elseif ( 'upcoming' === $when ) {
				++$s['calls_upcoming'];
			}
		}

		return $s;
	}

	/**
	 * Whether a call has started (`held`), has not (`upcoming`), or has no start at all (`''`).
	 *
	 * @param array $call A call.
	 * @param int   $now  Unix time.
	 * @return string
	 */
	private static function when( array $call, $now ) {
		$start = self::int( $call['start'] );

		if ( $start <= 0 ) {
			return '';
		}

		return $start < $now ? 'held' : 'upcoming';
	}

	/**
	 * Whether one of the mentor's notes is about one of the call's students, dated no earlier than a
	 * day before the call started.
	 *
	 * @param array   $call  A held call.
	 * @param array[] $notes The notes its mentor wrote.
	 * @return bool
	 */
	private static function noted( array $call, array $notes ) {
		$from = self::int( $call['start'] ) - DAY_IN_SECONDS;

		foreach ( $notes as $note ) {
			if ( self::int( $note['created'] ) >= $from && array_intersect( (array) $note['records'], (array) $call['records'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A stored number as an int; anything that is not a number is 0.
	 *
	 * @param mixed $value The value.
	 * @return int
	 */
	private static function int( $value ) {
		return is_numeric( $value ) ? (int) $value : 0;
	}
}
