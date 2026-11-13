<?php
/**
 * What each mentor has been told about, and the email for a new student.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells a mentor about the current students new to their list, once per enrollment.
 *
 * The mentors sync hands this every mentor it read, with the rows it is about to store as that
 * mentor's list. This keeps no hooks and draws no screen: it remembers, works out who is new,
 * applies two guards and sends "Student assigned, to the mentor".
 *
 * **It remembers what each mentor was told, not their last list.** `OPT_TOLD` holds, for each
 * mentor's Airtable record ID, the Students Reports record IDs that mentor has been told about or
 * that were on their list when this first ran. It only grows. A list that shrinks and comes back
 * (a short read, a mentor made inactive and active again, a mentor record moved to another
 * account) announces nobody twice, because nothing is ever taken out of it. Keyed by the mentor's
 * record ID rather than their account, so a record linked to another account keeps its history.
 *
 * **Nothing is announced the first time.** With no stored set of this version, the run records
 * every row on every list, past ones included, and emails nobody: the students mentors already
 * have are not news. `WPCPM_Institution_Announce` seeds its set the same way. The seed does not
 * trust one read: it also takes the lists the accounts held before the sync touched them, which the
 * sync reads through `stored_lists()` before its revoke step deletes any and hands over as `known`,
 * and whatever the accounts still hold. A first read with no ID at all writes nothing, so it cannot
 * fix an empty set.
 *
 * **Only what was accepted is remembered.** A student's ID goes into the set once their mentor's
 * email was accepted for sending, and the set is written after each email, so a run that dies
 * midway repeats at most one email and a refused one is sent again by the next run. A past
 * student and a test institution's student are remembered without an email: neither is ever
 * announced, so remembering them is always safe. With the switch off every new ID is remembered
 * and nothing is sent, so switching it on later sends no backlog.
 *
 * **Two guards.** At most `MAX_EMAILS` mentors are emailed in a run; the rest wait, unremembered,
 * for the next. And a run that would tell more than half of the mentors who have students, and at
 * least `BRAKE_MIN` of them, is something wrong with the data rather than with the week (every
 * record ID changed, say): nobody is emailed and nothing new is remembered. A sync an
 * Administrator starts by hand passes the brake; the ceiling still holds. Mentors whose email was
 * refused are kept in the set's `failed` list and go last at the next run, so mentors the mailer
 * keeps refusing cannot hold the others back. The quiet way past the brake is the switch: while
 * it is off every new ID is remembered and nothing is sent, so turning it off for one sync and on
 * again takes the students in. A write of the set that is refused stops the sending, so a database
 * that will not keep it cannot have the same mentors emailed every sync, and the answer says
 * `unsaved` so the sync's report says it. The set also keeps the sync's start (`run`) with the
 * emails accepted under it, so the ceiling holds when a sync's last step runs again.
 *
 * Nothing here puts a student's name or address in a subject or in what it answers: the mail log
 * keeps subjects, and the sync's report is read by every Administrator.
 */
final class WPCPM_Mentor_Assigned {

	/** Option: for each mentor's record ID, the student report IDs they have been told about. */
	const OPT_TOLD = 'wpcpm_mentees_told';

	/** Bumped when the set has to be seeded again. */
	const SCHEMA_VERSION = 1;

	/** The most emails a run sends, and the most it attempts. */
	const MAX_EMAILS = 25;

	/** The brake holds only when at least this many mentors would be told at once. */
	const BRAKE_MIN = 20;

	/** The email's catalog ID. */
	const CONTEXT = 'student-assigned-mentor';

	/**
	 * Remember, work out who is new, and tell each mentor about their new students.
	 *
	 * @param array $lists   Mentor record ID => array( 'user_id' => int, 'rows' => array[] ), one
	 *                       entry for every mentor the run read, each row a mentee row as the sync
	 *                       stores it.
	 * @param array $options 'by_hand' (bool) passes the brake; 'alive' (callable answering bool) is
	 *                       asked before every email after the first, and false stops the sending;
	 *                       'known' (mentor record ID => report IDs, as `stored_lists()` answers) is
	 *                       what the accounts held before the sync replaced their lists, read by the
	 *                       first run only and ignored once a set of this version exists; 'run'
	 *                       (int, the sync's start) keeps the ceiling across a sync whose last step
	 *                       runs again: the emails accepted under the same run count against it.
	 * @return array{seeded: bool, told: int, students: int, waiting: int, failed: int, braked: int, unsaved: bool, run_sent?: int}
	 *               Mentors emailed, students in those emails, mentors left for the next run, mentors
	 *               whose email was refused, mentors the brake held, and whether a write of what was
	 *               taken in or told was refused, so the next run tries again.
	 */
	public static function run( array $lists, array $options = array() ) {
		$answer = array(
			'seeded'   => false,
			'told'     => 0,
			'students' => 0,
			'waiting'  => 0,
			'failed'   => 0,
			'braked'   => 0,
			'unsaved'  => false,
		);

		$on      = (bool) WPCPM_Settings::get_value( 'mentor_assigned_email', true );
		$by_hand = ! empty( $options['by_hand'] );
		$run_id  = isset( $options['run'] ) && is_numeric( $options['run'] ) ? max( 0, (int) $options['run'] ) : 0;
		$alive   = null;

		// An `alive` given but not something to call is read as answering false: the run stops after
		// its first email, which is the safe side of a caller that meant to pass a check.
		if ( array_key_exists( 'alive', $options ) ) {
			$alive = is_callable( $options['alive'] ) ? $options['alive'] : static function () {
				return false;
			};
		}

		$stored = get_option( self::OPT_TOLD );

		// No set of this version, or one with no usable entry: seeded again, silently. A seed whose
		// write is refused is not one, and the next run seeds again.
		if ( ! self::is_set( $stored ) ) {
			$refused_seed      = false;
			$answer['seeded']  = self::seed( $lists, isset( $options['known'] ) && is_array( $options['known'] ) ? $options['known'] : array(), $refused_seed );
			$answer['unsaved'] = $refused_seed;

			return $answer;
		}

		$seeded = isset( $stored['seeded'] ) ? (int) $stored['seeded'] : time();
		$told   = self::clean_set( $stored['told'] );
		$failed = self::clean_failed( isset( $stored['failed'] ) ? $stored['failed'] : array() );
		$run    = self::clean_run( isset( $stored['run'] ) ? $stored['run'] : null );
		$dirty  = false;

		// The emails already accepted under this run. Answered whichever way the call ends (the
		// switch off, the brake, the sending), so a step run again reports what its earlier call sent.
		$accepted = $run_id > 0 && array() !== $run && $run_id === $run['id'] ? $run['sent'] : 0;

		if ( $run_id > 0 ) {
			$answer['run_sent'] = $accepted;
		}

		// Mentor record ID => array( 'user_id' => int, 'rows' => new current rows, 'ids' => their IDs ).
		$pending = array();

		// The mentors who have students: an account and at least one current row.
		$field = 0;

		foreach ( $lists as $mentor_id => $entry ) {
			$mentor_id = trim( (string) $mentor_id );
			$user_id   = is_array( $entry ) && isset( $entry['user_id'] ) ? (int) $entry['user_id'] : 0;

			// No account yet: nothing is sent or remembered, so they are told once they have one.
			if ( '' === $mentor_id || $user_id <= 0 ) {
				continue;
			}

			// A mentor's entry that is there but is not a list was damaged, and what it knew is lost.
			// Their list as this run reads it is taken in silently, every row remembered and nobody
			// emailed, rather than every student of theirs announced as new.
			$damaged = array_key_exists( $mentor_id, $told ) && ! is_array( $told[ $mentor_id ] );
			$rows    = isset( $entry['rows'] ) && is_array( $entry['rows'] ) ? $entry['rows'] : array();
			$known   = ! $damaged && isset( $told[ $mentor_id ] ) ? array_flip( $told[ $mentor_id ] ) : array();
			$current = false;
			$fresh   = array();
			$silent  = array();

			foreach ( $rows as $row ) {
				$id = is_array( $row ) ? self::id_of( $row ) : '';

				if ( '' === $id ) {
					continue;
				}

				$past = ! empty( $row['is_past'] );

				if ( ! $past ) {
					$current = true;
				}

				if ( isset( $known[ $id ] ) ) {
					continue;
				}

				$known[ $id ] = true;
				$institution  = trim( (string) ( isset( $row['institution'] ) ? $row['institution'] : '' ) );

				if ( $damaged || $past || ( '' !== $institution && WPCPM_Test_Institutions::is_test( $institution ) ) ) {
					$silent[] = $id;
				} else {
					$fresh[ $id ] = $row;
				}
			}

			if ( $current ) {
				++$field;
			}

			// Past and test-institution rows never produce an email, so they are remembered now,
			// whatever the brake or the ceiling decide below.
			if ( array() !== $silent ) {
				self::remember( $told, $mentor_id, $silent );

				$dirty = true;
			}

			if ( array() !== $fresh ) {
				$pending[ $mentor_id ] = array(
					'user_id' => $user_id,
					'rows'    => array_values( $fresh ),
					'ids'     => array_map( 'strval', array_keys( $fresh ) ),
				);
			}
		}

		// The switch off: every new ID is remembered in one write and nothing is sent. The brake is
		// never applied here, which makes this the quiet way to take students in when it holds.
		if ( ! $on ) {
			foreach ( $pending as $mentor_id => $item ) {
				self::remember( $told, (string) $mentor_id, $item['ids'] );

				$dirty = true;
			}

			if ( $dirty && ! self::write( $seeded, $told, $failed, $run ) ) {
				$answer['unsaved'] = true;
			}

			return $answer;
		}

		$waiting = count( $pending );

		if ( ! $by_hand && $waiting >= self::BRAKE_MIN && 2 * $waiting > $field ) {
			$answer['braked'] = $waiting;

			if ( $dirty && ! self::write( $seeded, $told, $failed, $run ) ) {
				$answer['unsaved'] = true;
			}

			return $answer;
		}

		// Told in order of the mentor's record ID, so the same run over the same data tells the
		// same mentors first when more wait than the ceiling allows. Mentors whose email was refused
		// at an earlier run go last, so mentors the mailer refuses cannot hold the others back.
		ksort( $pending, SORT_STRING );

		// The refused ones follow in the order of the stored list: those not tried at the last run
		// first, then those refused at it, so they take turns when more are refused than one run tries.
		$behind = array();

		foreach ( $failed as $mentor_id ) {
			if ( isset( $pending[ $mentor_id ] ) ) {
				$behind[ $mentor_id ] = $pending[ $mentor_id ];
			}
		}

		$pending = array_diff_key( $pending, $behind ) + $behind;

		$card = (string) WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_MENTOR );
		$link = '' !== $card ? $card : home_url( '/' );

		// The emails already accepted under this run count against the ceiling, so a sync whose last
		// step runs again (after a fatal, say) never emails more than `MAX_EMAILS` mentors in all.
		$attempts = $accepted;
		$tried    = array();
		$refused  = array();
		$stopped  = false;

		foreach ( $pending as $mentor_id => $item ) {
			// Attempts, accepted or not, so a broken mailer cannot loop through every mentor; an
			// accepted email is always an attempt, so this also caps the emails sent.
			if ( $attempts >= self::MAX_EMAILS ) {
				break;
			}

			if ( $attempts > 0 && null !== $alive && ! call_user_func( $alive ) ) {
				break;
			}

			++$attempts;
			--$waiting;

			$tried[ (string) $mentor_id ] = true;

			$sent = WPCPM_Mail::send( $item['user_id'], self::CONTEXT, self::build( $item['rows'], $link ) );

			if ( true !== $sent ) {
				++$answer['failed'];

				$refused[] = (string) $mentor_id;

				continue;
			}

			self::remember( $told, (string) $mentor_id, $item['ids'] );

			++$answer['told'];
			$answer['students'] += count( $item['rows'] );

			$dirty = false;

			if ( $run_id > 0 ) {
				$run = array(
					'id'   => $run_id,
					'sent' => ++$accepted,
				);
			}

			// Written before the next mentor, so a run that dies later repeats nobody told here. A
			// set that cannot be written would have every later email sent again at every sync, so a
			// refused write stops the sending and the mentors not yet emailed wait. The value always
			// changes here, so false is a real failure and not an unchanged option. The refused list
			// is carried through as it was read; the new one is written once, below.
			if ( ! self::write( $seeded, $told, $failed, $run ) ) {
				$stopped           = true;
				$answer['unsaved'] = true;

				break;
			}
		}

		$answer['waiting'] = $waiting;

		// Refused next time, in the order they are tried then: first those refused before who still
		// wait and were not tried now, then those refused now. A mentor emailed, or with nobody new,
		// drops out.
		$still = array();

		foreach ( $failed as $mentor_id ) {
			if ( isset( $pending[ $mentor_id ] ) && ! isset( $tried[ $mentor_id ] ) ) {
				$still[] = $mentor_id;
			}
		}

		$next = self::clean_failed( array_merge( $still, $refused ) );

		// One write at the end, and only if something changed. After a refused write nothing more
		// is tried. A refused write here that held rows remembered silently loses them, so it is
		// said; one that held only the refused list leaves the order as it was.
		if ( ! $stopped && ( $dirty || $next !== $failed ) && ! self::write( $seeded, $told, $next, $run ) && $dirty ) {
			$answer['unsaved'] = true;
		}

		// Every email accepted under this run, the ones an earlier call of the same step sent
		// included, so the sync's report counts them all when the step runs again.
		if ( $run_id > 0 ) {
			$answer['run_sent'] = $accepted;
		}

		return $answer;
	}

	/**
	 * One student's line in the email: name, track, institution, start date and address.
	 *
	 * A missing institution or start date leaves its part out (an institution still an Airtable
	 * record ID counts as missing), and white space inside a value,
	 * line breaks included, is one space, so a student is always one line. Plain text: what the
	 * program typed is kept as typed.
	 *
	 * @param array $row A mentee row.
	 * @return string
	 */
	public static function line( array $row ) {
		$name  = self::flat( isset( $row['name'] ) ? $row['name'] : '' );
		$parts = array( '' !== $name ? $name : __( 'A student', 'wpcredits-program-manager' ) );

		$status = self::flat( isset( $row['status'] ) ? $row['status'] : '' );

		if ( '' !== $status ) {
			$parts[] = self::flat( WPCPM_Program::label( $status ) );
		}

		$institution = self::flat( isset( $row['institution'] ) ? $row['institution'] : '' );

		// A row from before the lookups resolved its school may still hold the record ID: left out,
		// like an empty one, since an ID tells a mentor nothing.
		if ( '' !== $institution && 1 !== preg_match( '/^rec[A-Za-z0-9]{14}$/', $institution ) ) {
			$parts[] = $institution;
		}

		$raw   = self::flat( isset( $row['start'] ) ? $row['start'] : '' );
		$start = WPCPM_Dates::day( $raw );

		// A day `day()` could read begins with its `Y-m-d`, so the two compare as strings. Today is
		// the site's day, so a student moved over mid-semester reads "started" and not "starts".
		if ( '' !== $start && substr( $raw, 0, 10 ) < (string) wp_date( 'Y-m-d' ) ) {
			/* translators: %s: the day a student started, in the site's date format. */
			$parts[] = sprintf( __( 'started %s', 'wpcredits-program-manager' ), $start );
		} elseif ( '' !== $start ) {
			/* translators: %s: the day a student starts, in the site's date format. */
			$parts[] = sprintf( __( 'starts %s', 'wpcredits-program-manager' ), $start );
		}

		$line  = implode(
			', ',
			array_filter(
				$parts,
				static function ( $part ) {
					return '' !== $part;
				}
			)
		);
		$email = self::flat( isset( $row['email'] ) ? $row['email'] : '' );

		return '' !== $email ? $line . ': ' . $email : $line;
	}

	/**
	 * The email's builder: the lines and the count are made when the mail layer runs it, which is
	 * in the mentor's language.
	 *
	 * @param array[] $rows The new rows.
	 * @param string  $link The Mentor Report Card, or the site's front page.
	 * @return callable
	 */
	private static function build( array $rows, $link ) {
		return static function () use ( $rows, $link ) {
			$lines = array();

			foreach ( $rows as $row ) {
				$lines[] = self::line( $row );
			}

			$n = count( $rows );

			return WPCPM_Mail_Templates::mail(
				self::CONTEXT,
				array(
					'site_name'      => WPCPM_Mail::site_name(),
					'count'          => sprintf( /* translators: %s: a number of students. */ _n( '%s student', '%s students', $n, 'wpcredits-program-manager' ), number_format_i18n( $n ) ),
					'students_list'  => implode( "\n", $lines ),
					'dashboard_link' => $link,
				)
			);
		};
	}

	/**
	 * Whether the stored set is a valid set of this version: the first run has happened.
	 *
	 * @return bool
	 */
	public static function has_baseline() {
		return self::is_set( get_option( self::OPT_TOLD ) );
	}

	/**
	 * Every stored list as report IDs, read from every account that holds one.
	 *
	 * Keyed by the account's mentor record ID; an account with no record ID, and a list with no ID,
	 * is left out. IDs only, trimmed, once each: what the sync keeps in its state is never a name or
	 * an address. The sync reads this before its revoke step deletes any list, for the first run.
	 *
	 * @return array<string, string[]> Mentor record ID => Students Reports record IDs.
	 */
	public static function stored_lists() {
		$lists    = array();
		$accounts = get_users(
			array(
				'number'     => -1,
				'fields'     => 'ID',
				'meta_query' => array(
					array(
						'key'     => WPCPM_Mentors_Sync::META_MENTEES,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( is_array( $accounts ) ? $accounts : array() as $account ) {
			$user_id = is_object( $account ) && isset( $account->ID ) ? (int) $account->ID : (int) $account;
			$record  = $user_id > 0 ? get_user_meta( $user_id, WPCPM_Mentors_Sync::META_RECORD_ID, true ) : '';
			$record  = is_scalar( $record ) ? trim( (string) $record ) : '';

			// A list on an account with no mentor record ID belongs to no entry of the set.
			if ( '' === $record ) {
				continue;
			}

			self::remember( $lists, $record, self::ids_of( get_user_meta( $user_id, WPCPM_Mentors_Sync::META_MENTEES, true ) ) );
		}

		return array_filter( self::clean_set( $lists ) );
	}

	/**
	 * The first run: every row a mentor is known to have, past ones included, remembered silently.
	 *
	 * One read is not trusted alone. The set is the union of this run's rows for every mentor with an
	 * account, `$known` (the lists the accounts held before the sync replaced or deleted them), and
	 * whatever the accounts still hold, each under that account's mentor record ID, so a mentor this
	 * read happened to miss is not announced in full at the next. When the union holds no ID at all
	 * nothing is written: an empty or account-less first read must not fix an empty set at this
	 * version, after which every student would be news.
	 *
	 * @param array $lists   As `run()` takes them.
	 * @param array $known   Mentor record ID => report IDs, as `stored_lists()` answers.
	 * @param bool  $refused Set to true when the write was refused.
	 * @return bool Whether a set was written.
	 */
	private static function seed( array $lists, array $known, &$refused ) {
		$told = array();

		foreach ( $lists as $mentor_id => $entry ) {
			$mentor_id = trim( (string) $mentor_id );

			if ( '' === $mentor_id || ! is_array( $entry ) || ! isset( $entry['user_id'] ) || (int) $entry['user_id'] <= 0 ) {
				continue;
			}

			self::remember( $told, $mentor_id, self::ids_of( isset( $entry['rows'] ) ? $entry['rows'] : array() ) );
		}

		foreach ( array( $known, self::stored_lists() ) as $source ) {
			foreach ( $source as $mentor_id => $ids ) {
				$mentor_id = trim( (string) $mentor_id );

				if ( '' === $mentor_id || ! is_array( $ids ) ) {
					continue;
				}

				self::remember( $told, $mentor_id, array_filter( $ids, 'is_scalar' ) );
			}
		}

		$told = array_filter( self::clean_set( $told ) );

		if ( array() === $told ) {
			return false;
		}

		$refused = ! self::write( time(), $told );

		return ! $refused;
	}

	/**
	 * Whether a stored value is a set of this version with at least one usable entry.
	 *
	 * The seed only writes a set in which some mentor's entry holds an ID, so a `told` that, made
	 * safe to read, holds none (empty, blank mentor keys, damaged or empty entries only) is damage,
	 * and is seeded again.
	 *
	 * @param mixed $stored The option's value.
	 * @return bool
	 */
	private static function is_set( $stored ) {
		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::SCHEMA_VERSION !== (int) $stored['v'] || ! isset( $stored['told'] ) || ! is_array( $stored['told'] ) ) {
			return false;
		}

		foreach ( self::clean_set( $stored['told'] ) as $ids ) {
			if ( is_array( $ids ) && array() !== $ids ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add IDs to one mentor's entry, which a damaged entry becomes a list again by.
	 *
	 * @param array  $told      Mentor record ID => report IDs, changed in place.
	 * @param string $mentor_id The mentor's record ID.
	 * @param array  $ids       Report IDs.
	 */
	private static function remember( array &$told, $mentor_id, array $ids ) {
		$had = isset( $told[ $mentor_id ] ) && is_array( $told[ $mentor_id ] ) ? $told[ $mentor_id ] : array();

		$told[ $mentor_id ] = array_merge( $had, array_map( 'strval', $ids ) );
	}

	/**
	 * Store the set, each mentor's IDs once each, with autoload off.
	 *
	 * @param int      $seeded When the set was seeded.
	 * @param array    $told   Mentor record ID => report IDs.
	 * @param string[] $failed Mentor record IDs whose email was refused and who have not been emailed since.
	 * @param array    $run    The last run's id and the emails accepted under it, or an empty array.
	 * @return bool What `update_option()` answered.
	 */
	private static function write( $seeded, array $told, array $failed = array(), array $run = array() ) {
		return (bool) update_option(
			self::OPT_TOLD,
			array(
				'v'      => self::SCHEMA_VERSION,
				'seeded' => (int) $seeded,
				'told'   => self::clean_set( $told ),
				'failed' => self::clean_failed( $failed ),
				'run'    => self::clean_run( $run ),
			),
			false
		);
	}

	/**
	 * A stored set made safe to read: trimmed mentor record ID => unique, non-empty string IDs.
	 *
	 * An entry that is not a list, or a list holding items none of which is a usable ID, is kept as
	 * `false`, the mark of a damaged entry, so `run()` can take that mentor's list in silently and a
	 * write does not turn it into an empty list. A list that is empty stays an empty list.
	 *
	 * @param array $told The stored `told`.
	 * @return array<string, string[]|false>
	 */
	private static function clean_set( array $told ) {
		$clean = array();

		foreach ( $told as $mentor_id => $ids ) {
			$mentor_id = trim( (string) $mentor_id );

			if ( '' === $mentor_id ) {
				continue;
			}

			$usable = array();

			foreach ( is_array( $ids ) ? $ids : array() as $id ) {
				$id = is_scalar( $id ) ? trim( (string) $id ) : '';

				if ( '' !== $id ) {
					$usable[] = $id;
				}
			}

			if ( ! is_array( $ids ) || ( array() !== $ids && array() === $usable ) ) {
				if ( ! array_key_exists( $mentor_id, $clean ) ) {
					$clean[ $mentor_id ] = false;
				}

				continue;
			}

			$keep = isset( $clean[ $mentor_id ] ) && is_array( $clean[ $mentor_id ] ) ? array_flip( $clean[ $mentor_id ] ) : array();

			foreach ( $usable as $id ) {
				$keep[ $id ] = true;
			}

			$clean[ $mentor_id ] = array_map( 'strval', array_keys( $keep ) );
		}

		return $clean;
	}

	/**
	 * The stored list of refused mentors made safe to read: trimmed record IDs, once each, in the
	 * order stored, which is the order they are tried in at the next run.
	 *
	 * A value that is not a list reads as an empty one, so a set stored before the list existed is
	 * read as it always was.
	 *
	 * @param mixed $failed The stored `failed`.
	 * @return string[]
	 */
	private static function clean_failed( $failed ) {
		$keep = array();

		foreach ( is_array( $failed ) ? $failed : array() as $mentor_id ) {
			$mentor_id = is_scalar( $mentor_id ) ? trim( (string) $mentor_id ) : '';

			if ( '' !== $mentor_id ) {
				$keep[ $mentor_id ] = true;
			}
		}

		return array_map( 'strval', array_keys( $keep ) );
	}

	/**
	 * The stored run made safe to read: its id and the emails accepted under it, or an empty array.
	 *
	 * A set stored without one, or with one that is not a positive id, reads as no run.
	 *
	 * @param mixed $run The stored `run`.
	 * @return array{id?: int, sent?: int}
	 */
	private static function clean_run( $run ) {
		if ( ! is_array( $run ) || ! isset( $run['id'] ) || ! is_numeric( $run['id'] ) || (int) $run['id'] <= 0 ) {
			return array();
		}

		return array(
			'id'   => (int) $run['id'],
			'sent' => isset( $run['sent'] ) && is_numeric( $run['sent'] ) ? max( 0, (int) $run['sent'] ) : 0,
		);
	}

	/**
	 * The report IDs of a list of rows, rows without one ignored.
	 *
	 * @param mixed $rows A list of mentee rows.
	 * @return string[]
	 */
	private static function ids_of( $rows ) {
		$ids = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$id = is_array( $row ) ? self::id_of( $row ) : '';

			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * A row's report ID, or '' for a row without one.
	 *
	 * @param array $row A mentee row.
	 * @return string
	 */
	private static function id_of( array $row ) {
		return isset( $row['record_id'] ) && is_scalar( $row['record_id'] ) ? trim( (string) $row['record_id'] ) : '';
	}

	/**
	 * A value on one line: every run of white space, line breaks included, is one space.
	 *
	 * @param mixed $value A value from a row.
	 * @return string
	 */
	private static function flat( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$flat  = preg_replace( '/[\s\x{2028}\x{2029}]+/u', ' ', $value );

		if ( null === $flat ) {
			$flat = preg_replace( '/\s+/', ' ', $value );
		}

		return trim( (string) $flat );
	}
}
