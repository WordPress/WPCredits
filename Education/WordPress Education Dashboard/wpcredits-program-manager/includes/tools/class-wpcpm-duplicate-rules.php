<?php
/**
 * Student Duplicate Finder: the rules.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which of a student's rows may be deleted, and why the others stay.
 *
 * **Pure.** No WordPress function, no request and no clock. The scan hands it the rows of one
 * address in the three tables, what the site points at, and a context read from the site once per
 * run (the live statuses and the work columns); it hands back a proposal and its reasons for every
 * row. The delete handler asks the same questions of a live re-read (`recheck()`), so the list a
 * manager ticked and the delete that follows cannot disagree about a row.
 *
 * Reasons are codes with their facts, never sentences: the screen words them, so nothing here is
 * translated and every check reads the same in any language.
 *
 * The rules are section 5 of docs/specs/2026-09-11-student-duplicate-finder-design.md.
 */
final class WPCPM_Duplicate_Rules {

	/** The three tables, in the order the screen lists them. */
	const TABLES = array( 'students', 'reports', 'feedback' );

	/**
	 * The order a delete runs in: children first.
	 *
	 * A run that stops midway, on a refused batch or a rate limit, then leaves the Students row
	 * standing, which is the row the creation automation starts from (spec decision 3.8).
	 */
	const DELETE_ORDER = array( 'feedback', 'reports', 'students' );

	/**
	 * The columns each table is read by, spelled as the base spells them.
	 *
	 * The three tables name the same facts differently (`Full Name` and `Name`, `Educational
	 * Institutions` and `Educational institution`), and Feedback's `Course` holds a program where
	 * the other two hold a student's status. An empty string is a fact that table does not keep.
	 */
	const COLUMNS = array(
		'students' => array(
			'name'        => 'Full Name',
			'status'      => 'Status',
			'institution' => 'Educational Institutions',
			'mentor'      => 'Mentor',
			'start'       => 'Start Date',
			'end'         => 'End Date',
			'hours'       => 'Total hours',
			'notes'       => 'Notes',
		),
		'reports'  => array(
			'name'        => 'Name',
			'status'      => 'Status',
			'institution' => 'Educational institution',
			'mentor'      => 'Mentor',
			'start'       => 'Internship Start Date',
			'end'         => 'Internship End Date',
			'hours'       => 'Hours',
			'notes'       => '',
		),
		'feedback' => array(
			'name'        => 'Name',
			'status'      => 'Course',
			'institution' => 'Institution',
			'mentor'      => '',
			'start'       => '',
			'end'         => '',
			'hours'       => '',
			'notes'       => '',
		),
	);

	/** The column all three tables keep the address in, and the only join between them. */
	const EMAIL = 'Email';

	/** Statuses that record a finished placement. */
	const GRADUATED = array( 'Graduate', 'Pending graduation' );

	/** Statuses a student who left, or never started, is filed under. */
	const ABANDONED = array( 'Not moving forward', 'Dropped out', 'SPAM', 'Duplicated', 'Fail' );

	/** Not a track, and still somebody's student: live for these rules (spec 5.2). */
	const PAUSED = 'Paused';

	/** The Feedback columns that say who a row is about rather than what they answered (spec 5.2). */
	const FEEDBACK_IDENTITY = array( 'Name', 'Email', 'Course', 'Institution', 'Students', 'F1 - Mentor', 'F2 - Mentor', 'F3 - Mentor' );

	/** The most rows one confirmation deletes (spec 7.1). */
	const MAX_ROWS = 100;

	const KEEP   = 'keep';
	const DELETE = 'delete';
	const REVIEW = 'review';

	const READY  = 'ready';
	const DECIDE = 'decide';

	/**
	 * The reasons that hold a row back, as against the ones that only describe it.
	 *
	 * `recheck()` refuses a row that has gained one of these since the scan: the manager ticked it
	 * knowing what the list said, not what the base says now.
	 */
	const HOLDS = array( 'graduation', 'live', 'work', 'answers', 'hours', 'notes', 'lacks-institution', 'lacks-mentor', 'lacks-status', 'site', 'same-second', 'newest-abandoned', 'tie', 'inverted', 'site-older' );

	/**
	 * The key a student's rows are grouped under: a hash of the address, trimmed and lowercased.
	 *
	 * The base holds addresses as they were typed, and one pair differs only by case, so the
	 * address is compared as a mailbox. A hash rather than the address itself, because the key
	 * travels in form fields and the finder's own URLs.
	 *
	 * @param string $email An address as the base holds it.
	 * @return string Sixteen hex characters, or '' for no address.
	 */
	public static function key( $email ) {
		$email = strtolower( trim( (string) $email ) );

		return '' === $email ? '' : substr( md5( $email ), 0, 16 );
	}

	/**
	 * Whether a cell holds anything.
	 *
	 * Airtable leaves an empty cell out of `fields` altogether, but a cell emptied by a script can
	 * come back as '', an empty list or false, and none of those is an answer.
	 *
	 * @param mixed $value A cell.
	 * @return bool
	 */
	public static function is_filled( $value ) {
		return ! ( null === $value || '' === $value || array() === $value || false === $value );
	}

	/**
	 * The filled columns of a row that a deletion would lose: work on a report, answers on feedback.
	 *
	 * Work is any column the Student Report Card's report form writes for a live track, which the
	 * scan reads from the site and hands over as `$work_columns`, and Hours when it is above zero.
	 * An answer is any Feedback column that is not identity, so legacy survey columns count too.
	 * The Students table carries neither.
	 *
	 * @param string $table        One of `TABLES`.
	 * @param array  $fields       The record's `fields`.
	 * @param array  $work_columns Column names the report form writes.
	 * @return string[] Column names, sorted.
	 */
	public static function work_filled( $table, array $fields, array $work_columns ) {
		$filled = array();

		foreach ( $fields as $name => $value ) {
			$name = (string) $name;

			if ( ! self::is_filled( $value ) ) {
				continue;
			}

			if ( 'reports' === $table ) {
				if ( 'Hours' === $name ) {
					if ( is_numeric( $value ) && (float) $value > 0 ) {
						$filled[] = $name;
					}
				} elseif ( in_array( $name, $work_columns, true ) ) {
					$filled[] = $name;
				}
			} elseif ( 'feedback' === $table && ! in_array( $name, self::FEEDBACK_IDENTITY, true ) ) {
				$filled[] = $name;
			}
		}

		sort( $filled );

		return $filled;
	}

	/**
	 * One Airtable record, cut down to what the rules and the screen read.
	 *
	 * The scan keeps this and not the record, because a run holds three tables at once and a
	 * report row with its practical-lesson notes is kilobytes. For the same reason `work` is the
	 * number of filled work or answer columns, not their names: about 3,100 rows sit in the scan's
	 * state between pages, and the rules and the screen only ever ask how many.
	 *
	 * @param string $table   One of `TABLES`.
	 * @param array  $record  `array( 'id' => ..., 'createdTime' => ..., 'fields' => array )`.
	 * @param array  $context `work_columns` (string[]).
	 * @return array The reduced row.
	 */
	public static function reduce( $table, array $record, array $context ) {
		$columns = self::COLUMNS[ $table ];
		$fields  = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

		$text = static function ( $column ) use ( $fields ) {
			return ( '' !== $column && isset( $fields[ $column ] ) ) ? trim( self::flatten( $fields[ $column ] ) ) : '';
		};

		$links = static function ( $column ) use ( $fields ) {
			if ( '' === $column || ! isset( $fields[ $column ] ) || ! is_array( $fields[ $column ] ) ) {
				return array();
			}

			$ids = array();

			foreach ( $fields[ $column ] as $item ) {
				$id = is_array( $item ) && isset( $item['id'] ) ? (string) $item['id'] : ( is_string( $item ) ? $item : '' );

				if ( 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', $id ) ) {
					$ids[] = $id;
				}
			}

			return $ids;
		};

		$mentor = '' !== $columns['mentor'] && isset( $fields[ $columns['mentor'] ] ) && self::is_filled( $fields[ $columns['mentor'] ] );

		return array(
			'id'          => isset( $record['id'] ) ? (string) $record['id'] : '',
			'created'     => isset( $record['createdTime'] ) ? (string) $record['createdTime'] : '',
			'email'       => $text( self::EMAIL ),
			'name'        => $text( $columns['name'] ),
			'status'      => $text( $columns['status'] ),
			'institution' => $links( $columns['institution'] ),
			'mentor'      => $mentor,
			'start'       => $text( $columns['start'] ),
			'end'         => $text( $columns['end'] ),
			'hours'       => $text( $columns['hours'] ),
			'notes'       => '' !== $text( $columns['notes'] ),
			'work'        => count( self::work_filled( $table, $fields, isset( $context['work_columns'] ) ? (array) $context['work_columns'] : array() ) ),
		);
	}

	/**
	 * The proposal for every row of one address, and the address's verdict.
	 *
	 * @param array $group   Table => reduced rows (`reduce()`), in any order.
	 * @param array $refs    Record ID => what the site points at it with (any non-empty list locks).
	 * @param array $context `live` (string[]): the active tracked statuses; Paused is added here.
	 * @return array `verdict`, `name`, `rows` (table => rows with `proposal`, `locked`, `selectable`
	 *               and `reasons`), `counts` (table => n) and `flags` (string[]).
	 */
	public static function classify( array $group, array $refs, array $context ) {
		$live       = isset( $context['live'] ) ? array_map( 'strval', (array) $context['live'] ) : array();
		$live[]     = self::PAUSED;
		$out        = array(
			'verdict' => self::DECIDE,
			'name'    => '',
			'rows'    => array(),
			'counts'  => array(),
			'flags'   => array(),
		);
		$candidates = 0;
		$doubts     = 0;
		$spellings  = array();

		foreach ( self::TABLES as $table ) {
			$rows = isset( $group[ $table ] ) ? array_values( (array) $group[ $table ] ) : array();
			usort( $rows, array( __CLASS__, 'by_created' ) );

			$n                       = count( $rows );
			$out['counts'][ $table ] = $n;
			$out['rows'][ $table ]   = array();

			$newest       = $n ? $rows[ $n - 1 ] : null;
			$status_rules = 'feedback' !== $table;
			$older_held   = false;
			$older_used   = false;

			foreach ( array_slice( $rows, 0, max( 0, $n - 1 ) ) as $row ) {
				if ( $status_rules && ( in_array( $row['status'], $live, true ) || in_array( $row['status'], self::GRADUATED, true ) ) ) {
					$older_held = true;
				}
				if ( ! empty( $refs[ $row['id'] ] ) ) {
					$older_used = true;
				}
			}

			$tie      = $n > 1 && $rows[ $n - 1 ]['created'] === $rows[ $n - 2 ]['created'];
			$inverted = $n > 1 && $status_rules && in_array( $newest['status'], self::ABANDONED, true ) && $older_held;
			$used     = $n > 1 && $older_used && empty( $refs[ $newest['id'] ] );

			foreach ( $rows as $i => $row ) {
				$spellings[ $row['email'] ] = true;
				$locked                     = ! empty( $refs[ $row['id'] ] );
				$reasons                    = array();

				if ( 1 === $n ) {
					$proposal  = self::KEEP;
					$reasons[] = array( 'code' => 'only' );
				} elseif ( $i === $n - 1 ) {
					$proposal = self::KEEP;
					if ( $tie ) {
						$reasons[] = array( 'code' => 'tie' );
					}
					if ( $inverted ) {
						$reasons[] = array(
							'code'   => 'inverted',
							'status' => $row['status'],
						);
					}
					if ( $used ) {
						$reasons[] = array( 'code' => 'site-older' );
					}
					if ( $reasons ) {
						$proposal = self::REVIEW;
						++$doubts;
					} else {
						$reasons[] = array( 'code' => 'newest' );
					}
				} else {
					$reasons  = self::holds( $table, $row, $newest, $refs, $live, $status_rules );
					$proposal = $reasons ? self::REVIEW : self::DELETE;

					if ( ! $reasons ) {
						$reasons[] = array(
							'code'   => 'older',
							'status' => $row['status'],
						);
						++$candidates;
					}
				}

				$row['proposal']   = $proposal;
				$row['locked']     = $locked;
				$row['selectable'] = self::KEEP !== $proposal && ! $locked;
				$row['reasons']    = $reasons;
				$row['codes']      = array_values( array_unique( array_column( $reasons, 'code' ) ) );

				$out['rows'][ $table ][] = $row;
			}
		}

		$review = false;
		foreach ( $out['rows'] as $rows ) {
			foreach ( $rows as $row ) {
				if ( self::REVIEW === $row['proposal'] ) {
					$review = true;
				}
			}
		}

		$out['verdict'] = ( $candidates > 0 && ! $review && 0 === $doubts ) ? self::READY : self::DECIDE;
		$out['name']    = self::display_name( $out['rows'] );

		if ( $out['counts']['students'] > 0 && $out['counts']['reports'] > $out['counts']['students'] ) {
			$out['flags'][] = 'refire';
		}
		if ( $out['counts']['students'] > $out['counts']['reports'] ) {
			$out['flags'][] = 'pending';
		}
		if ( count( $spellings ) > 1 ) {
			$out['flags'][] = 'spelling';
		}

		return $out;
	}

	/**
	 * Turn a posted selection into the rows it stands for, against the stored report.
	 *
	 * A student key stands for that student's delete candidates, and only while the student is
	 * Ready. A `table:record` pair stands for that row, and only while the report marks it
	 * selectable. Everything else is dropped with its reason, never guessed at (spec 7.1).
	 *
	 * @param array $report       The stored report: `groups` keyed by `key()`.
	 * @param array $student_keys Posted student keys.
	 * @param array $pairs        Posted `table:record` pairs.
	 * @return array `rows` (each `key`, `table`, `id`, `via` and the scan's `codes`) and `dropped`
	 *               (each `code`, with the `key`, `table` and `id` it is about where known).
	 */
	public static function expand( array $report, array $student_keys, array $pairs ) {
		$groups  = isset( $report['groups'] ) && is_array( $report['groups'] ) ? $report['groups'] : array();
		$index   = array();
		$rows    = array();
		$dropped = array();
		$seen    = array();

		foreach ( $groups as $key => $group ) {
			foreach ( self::TABLES as $table ) {
				foreach ( isset( $group['rows'][ $table ] ) ? $group['rows'][ $table ] : array() as $row ) {
					$index[ $table . ':' . $row['id'] ] = array( (string) $key, $table, $row );
				}
			}
		}

		$add = static function ( $key, $table, array $row, $via ) use ( &$rows, &$seen ) {
			if ( isset( $seen[ $table . ':' . $row['id'] ] ) ) {
				return;
			}

			$seen[ $table . ':' . $row['id'] ] = true;
			$rows[]                            = array(
				'key'   => $key,
				'table' => $table,
				'id'    => $row['id'],
				'via'   => $via,
				'codes' => isset( $row['codes'] ) ? (array) $row['codes'] : array(),
			);
		};

		foreach ( array_values( array_unique( array_map( 'strval', $student_keys ) ) ) as $key ) {
			if ( ! isset( $groups[ $key ] ) ) {
				$dropped[] = array(
					'key'  => $key,
					'code' => 'unknown',
				);
				continue;
			}

			if ( self::READY !== $groups[ $key ]['verdict'] ) {
				$dropped[] = array(
					'key'  => $key,
					'code' => 'not-ready',
				);
				continue;
			}

			foreach ( self::TABLES as $table ) {
				foreach ( isset( $groups[ $key ]['rows'][ $table ] ) ? $groups[ $key ]['rows'][ $table ] : array() as $row ) {
					if ( self::DELETE === $row['proposal'] ) {
						$add( (string) $key, $table, $row, 'student' );
					}
				}
			}
		}

		foreach ( array_values( array_unique( array_map( 'strval', $pairs ) ) ) as $pair ) {
			if ( ! isset( $index[ $pair ] ) ) {
				$dropped[] = array(
					'pair' => $pair,
					'code' => 'unknown',
				);
				continue;
			}

			list( $key, $table, $row ) = $index[ $pair ];

			if ( empty( $row['selectable'] ) ) {
				$dropped[] = array(
					'key'   => $key,
					'table' => $table,
					'id'    => $row['id'],
					'code'  => 'not-selectable',
				);
				continue;
			}

			$add( $key, $table, $row, 'row' );
		}

		if ( count( $rows ) > self::MAX_ROWS ) {
			foreach ( array_slice( $rows, self::MAX_ROWS ) as $over ) {
				$dropped[] = array(
					'key'   => $over['key'],
					'table' => $over['table'],
					'id'    => $over['id'],
					'code'  => 'limit',
				);
			}

			$rows = array_slice( $rows, 0, self::MAX_ROWS );
		}

		return array(
			'rows'    => $rows,
			'dropped' => $dropped,
		);
	}

	/**
	 * The same questions, asked of the base as it is now, just before the delete.
	 *
	 * The stored report is the menu, not the authority (spec decision 3.6). A row is refused when
	 * it is no longer under its address, when the site points at it now, when it arrived through a
	 * student checkbox and is no longer a clean candidate, when it gained a reason to stay since
	 * the scan, or when deleting it would leave its address with no row in its table.
	 *
	 * @param array $selection `expand()`'s rows.
	 * @param array $live      Key => table => reduced rows, read from Airtable just now.
	 * @param array $refs      Record ID => what the site points at it with, read just now.
	 * @param array $context   As for `classify()`.
	 * @return array `go` (selection rows) and `refused` (each `key`, `table`, `id` and `code`).
	 */
	public static function recheck( array $selection, array $live, array $refs, array $context ) {
		$classified = array();
		$go         = array();
		$refused    = array();

		foreach ( $selection as $pick ) {
			$key   = (string) $pick['key'];
			$table = (string) $pick['table'];

			if ( ! isset( $classified[ $key ] ) ) {
				$classified[ $key ] = self::classify( isset( $live[ $key ] ) ? $live[ $key ] : array(), $refs, $context );
			}

			$now = null;
			foreach ( $classified[ $key ]['rows'][ $table ] as $row ) {
				if ( $row['id'] === $pick['id'] ) {
					$now = $row;
					break;
				}
			}

			$code = '';

			if ( null === $now ) {
				$code = 'gone';
			} elseif ( $now['locked'] ) {
				$code = 'site';
			} elseif ( self::KEEP === $now['proposal'] ) {
				// Kept now: either the only row left, because the newer one went since the scan, or
				// the newest after all. The first is the rule of 5.7 and says so, however the row
				// was ticked.
				$code = 1 === $classified[ $key ]['counts'][ $table ] ? 'last-row' : 'changed';
			} elseif ( 'student' === $pick['via'] && self::DELETE !== $now['proposal'] ) {
				$code = 'changed';
			} elseif ( array_diff( array_intersect( $now['codes'], self::HOLDS ), (array) $pick['codes'] ) ) {
				$code = 'changed';
			}

			if ( '' !== $code ) {
				$refused[] = array(
					'key'   => $key,
					'table' => $table,
					'id'    => $pick['id'],
					'code'  => $code,
				);
				continue;
			}

			$go[] = $pick;
		}

		// Never the last row an address has in a table (spec 5.7). Counted on what survived the
		// checks above, against every row the base holds for that address in that table now.
		$going = array();
		foreach ( $go as $pick ) {
			$going[ $pick['key'] . '|' . $pick['table'] ][] = $pick;
		}

		$kept = array();
		foreach ( $going as $slot => $picks ) {
			list( $key, $table ) = explode( '|', $slot );

			if ( count( $picks ) >= $classified[ $key ]['counts'][ $table ] ) {
				foreach ( $picks as $pick ) {
					$refused[] = array(
						'key'   => $key,
						'table' => $table,
						'id'    => $pick['id'],
						'code'  => 'last-row',
					);
				}
				continue;
			}

			foreach ( $picks as $pick ) {
				$kept[] = $pick;
			}
		}

		return array(
			'go'      => $kept,
			'refused' => $refused,
		);
	}

	/**
	 * Why an older row stays, or nothing when it may go (spec 5.2 and 5.3).
	 *
	 * @param string $table        One of `TABLES`.
	 * @param array  $row          The older row.
	 * @param array  $newest       The newest row of the same table.
	 * @param array  $refs         Record ID => site references.
	 * @param array  $live         Live statuses, Paused included.
	 * @param bool   $status_rules False for Feedback, whose Course names a program, not a state.
	 * @return array[] Reasons.
	 */
	private static function holds( $table, array $row, array $newest, array $refs, array $live, $status_rules ) {
		$holds = array();

		if ( $status_rules && in_array( $row['status'], self::GRADUATED, true ) ) {
			$holds[] = array(
				'code'   => 'graduation',
				'status' => $row['status'],
			);
		}
		if ( $status_rules && in_array( $row['status'], $live, true ) ) {
			$holds[] = array(
				'code'   => 'live',
				'status' => $row['status'],
			);
		}
		if ( 'reports' === $table && $row['work'] > 0 ) {
			$holds[] = array(
				'code'  => 'work',
				'count' => (int) $row['work'],
			);
		}
		if ( 'feedback' === $table && $row['work'] > 0 ) {
			$holds[] = array(
				'code'  => 'answers',
				'count' => (int) $row['work'],
			);
		}
		if ( 'students' === $table && is_numeric( $row['hours'] ) && (float) $row['hours'] > 0 ) {
			$holds[] = array(
				'code'  => 'hours',
				'hours' => $row['hours'],
			);
		}
		if ( 'students' === $table && $row['notes'] ) {
			$holds[] = array( 'code' => 'notes' );
		}
		if ( $row['institution'] && ! $newest['institution'] ) {
			$holds[] = array( 'code' => 'lacks-institution' );
		}
		if ( $row['mentor'] && ! $newest['mentor'] ) {
			$holds[] = array( 'code' => 'lacks-mentor' );
		}
		if ( '' !== $row['status'] && '' === $newest['status'] ) {
			$holds[] = array( 'code' => 'lacks-status' );
		}
		if ( ! empty( $refs[ $row['id'] ] ) ) {
			$holds[] = array(
				'code' => 'site',
				'refs' => array_values( (array) $refs[ $row['id'] ] ),
			);
		}
		if ( $row['created'] === $newest['created'] ) {
			$holds[] = array( 'code' => 'same-second' );
		}
		if ( $status_rules && in_array( $newest['status'], self::ABANDONED, true ) && ( in_array( $row['status'], $live, true ) || in_array( $row['status'], self::GRADUATED, true ) ) ) {
			$holds[] = array(
				'code'   => 'newest-abandoned',
				'status' => $newest['status'],
			);
		}

		return $holds;
	}

	/**
	 * Oldest first, and a fixed order between two rows created in the same second.
	 *
	 * @param array $a A reduced row.
	 * @param array $b Another.
	 * @return int
	 */
	private static function by_created( array $a, array $b ) {
		$by_time = strcmp( (string) $a['created'], (string) $b['created'] );

		return 0 !== $by_time ? $by_time : strcmp( (string) $a['id'], (string) $b['id'] );
	}

	/**
	 * The name to head a student's card with: the newest Students row's, else a report's or feedback's.
	 *
	 * @param array $rows Table => classified rows, oldest first.
	 * @return string
	 */
	private static function display_name( array $rows ) {
		foreach ( self::TABLES as $table ) {
			foreach ( array_reverse( isset( $rows[ $table ] ) ? $rows[ $table ] : array() ) as $row ) {
				if ( '' !== $row['name'] ) {
					return $row['name'];
				}
			}
		}

		return '';
	}

	/**
	 * A cell as text: a select's name, a list joined, a scalar as it is.
	 *
	 * The site's own `WPCPM_Airtable::flatten()` does the same, and is not called here so that
	 * this class loads with nothing else.
	 *
	 * @param mixed $value A cell.
	 * @return string
	 */
	private static function flatten( $value ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) {
				return (string) $value['name'];
			}

			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$parts[] = (string) $item;
				} elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
					$parts[] = (string) $item['name'];
				}
			}

			return implode( ', ', array_filter( $parts, 'strlen' ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
